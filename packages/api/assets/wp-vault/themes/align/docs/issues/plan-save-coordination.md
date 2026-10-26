# WordPress and Align Builder Save Coordination

Status: Proposed implementation plan for worker review. No save logic has been changed by this document.

## Required outcome

The native WordPress Save / Update / Publish action must save the active Align Builder content together with the appropriate WordPress entity changes. Page/Post/CPT and Site Editor Template/Template Part saves have different lifecycles and must retain their native behavior.

When embedded, the Builder Save button must use the same coordinator. Do not keep two independent writers racing to update the same entity content.

“Saved” means that the captured Builder snapshot, persisted WordPress data, and required derived files represent the same version. Failures must remain visible and retryable. Do not promise an atomic transaction across WordPress, plugins, the database, and the filesystem without implementing and verifying such guarantees.

## Mandatory preflight

1. Read `AGENTS.md`, `align/docs/README.md`, and the current `align/docs/CONTEXT.md`.
2. Classify this task as A + B: Builder JavaScript and PHP/REST persistence.
3. Run the Builder manifest search for save, template, and serializer. Manifest line counts may be stale; inspect current code.
4. Read `align/docs/builder/gutenberg-embedded-builder.md`, `align/docs/coding-with-builder/content-types.md`, `align/docs/coding-with-builder/asset-system.md`, and relevant issues documentation.
5. Check Git status. Existing export/import work and other uncommitted changes are unrelated; preserve them.
6. Verify the actual WordPress version, editor stores, save actions, REST routes, and installed plugin behavior before selecting interception points. Do not invent selectors or hooks.

## Findings from source inspection

These are static code findings, not proof that a specific live page is already corrupted. Re-check locations before implementation.

| Location | Current behavior | Consequence |
| --- | --- | --- |
| `align/core/builder/gutenberg/align-site-editor-integration.js`, around lines 389–429 | Both a store subscriber and Save button click listener send `ALIGN_BUILDER_TRIGGER_SAVE`. | One user action can trigger multiple Builder saves. Native WordPress saving does not wait for Builder completion. |
| Same file, around lines 466–481 | `ALIGN_BUILDER_SAVED` calls `core/editor.editPost({ content: cleanHtml })`. | Updates editor state after Builder persistence; does not itself complete a native save or reconcile a specific Template/Part entity. |
| Same file, around lines 459–463 | Builder dirty state touches `_align_dirty` on `core/editor`. | Must verify real dirty tracking for each editor and all HTML/CSS/JS/override changes. |
| `align/core/builder/js/modules/wp-save-handler.js`, around lines 159–198 and 378–419 | Captures canvas HTML, CSS, JS and overrides, then POSTs to the Builder save endpoint. Sends client `htmlContent` back as `cleanHtml`. | Parent receives a different representation from the final server-assembled content. |
| Same file, around lines 473–495 | Message handler directly calls `executeSave()`; no in-flight request guard is present there. | Duplicate or overlapping saves can run. |
| `align/core/inc/builder/class-builder-crud-handler.php`, around lines 73–146 | Writes assets, assembles `align/custom-html`, writes database content, then writes Template/Part HTML. | Several writes can partially succeed; there is no demonstrated all-or-nothing transaction. |
| Same file, around lines 114–173 | Template update and file write results are not fully checked before success response. | Success can be reported without proving all required writes succeeded. |
| `align/core/inc/builder/class-builder-content-handler.php`, around lines 163–225 | Template HTML is loaded from database first, then fallback sources/files. Page HTML comes from database. | Editing a template file does not necessarily change the content the Builder loads. |
| `align/core/inc/builder/class-builder-assets.php`, around lines 337 onward | Nonempty external CSS/JS takes precedence over inline fallback content. | Stale files can override newer canonical asset data. |

No general native WordPress save-to-Builder-files synchronization hook was found in the theme paths inspected. Confirm this across relevant plugins and current changes before concluding it is absent everywhere.

## Proposed architecture

### 1. One save coordinator, separate entity adapters

Use one coordinator for collecting, validating, and acknowledging a save snapshot. Use separate adapters for native post editing and Site Editor entity saving.

Entity identity must include the entity kind, post type, and exact ID. Preserve theme-qualified Template/Part IDs. A numeric Page ID is not interchangeable with a Template slug or a Template database post ID.

| Context | Required behavior |
| --- | --- |
| Page with embedded Builder | Include Builder snapshot in the native Page save while preserving pending title, status, template assignment, metadata, and plugin fields. |
| Native Gutenberg Post/CPT | Preserve native saving. Do not request content from an inactive or unrelated Builder iframe. |
| Supported CPT using Builder | Resolve the actual CPT REST route and supported fields; use its native entity lifecycle. |
| Blog `post` | Preserve the repository rule forbidding `align/*` blocks. Do not extend the existing generic Builder wrapper to blog posts. Explicitly review any legacy Builder-mode posts before enabling a new path. |
| `wp_template` | Save the exact Template entity and preserve its structure and Template Part references. |
| `wp_template_part` | Save the exact Part entity independently of its containing Template. |
| Standalone Builder | Retain a working entry point using the shared persistence/normalization service. Do not require a nonexistent parent window. |

Site Editor can save multiple entities. Respect the native confirmation and selected entity list. Do not save an unchecked entity or assume the visible iframe represents every dirty entity.

### 2. Prepare the snapshot before persistence

Proposed sequence, not an API that already exists:

```text
Native save intent
  -> Resolve target entity/entities and save intent
  -> Await Builder readiness and flush pending editor changes
  -> Capture immutable HTML + CSS + JS + overrides snapshot
  -> Validate target, payload, permissions, and expected version
  -> Feed prepared data into the appropriate native save lifecycle
  -> Persist canonical WordPress entity data
  -> Synchronize required derived files for that version
  -> Reconcile native editor and Builder with the persisted response
  -> Acknowledge completion for that snapshot only
```

Do not implement this by starting the existing Builder `/save-page` writer and the native WordPress writer concurrently. Do not rely only on DOM button labels or a subscriber that observes saving after it has started.

Worker must establish a verified integration point covering mouse actions, keyboard actions, and programmatic native saves. The final design must explain when native saving is delayed and how it resumes without recursion.

### 3. Canonical data and derived files

- Make persisted WordPress data the canonical source for HTML, CSS, JS, and overrides. Select and document their exact storage representation before coding; retain three independent logical states.
- Reuse or extract the existing normalization and packaging logic into a shared service. Both native integration and standalone Builder must use it.
- Return the actual persisted content and identity/version to the caller, not merely the submitted canvas HTML.
- Generate CSS/JS assets and Template/Part HTML from the same canonical version.
- Review the current disk-first CSS/JS loader. A stale file must not silently supersede newer canonical data. Define migration/fallback behavior for existing content.
- Verify write results. Prefer staging and atomic replacement per file where supported; document that this does not make all files plus database one atomic transaction.
- If the database save succeeds but a file fails, record/report incomplete synchronization. Retry generation from the committed version without overwriting newer edits.
- Verify native success notices as well as Builder notices: an asset failure must not leave a misleading overall success state.

### 4. Preserve Template semantics

The current Template loader removes Template Part comments for canvas editing and converts `core/post-content` into a Builder placeholder. The current saver adds default header/footer references. This conversion is not automatically a lossless round trip.

Worker must verify and preserve:

- Existing header/footer slugs, translated variants, attributes, ordering, and additional parts.
- The post-content placeholder without copying a specific Page body into the Template.
- Template structures with several blocks; do not capture only the first `align/custom-html` block and lose siblings.
- Deliberately headerless/footerless layouts; do not introduce defaults solely as a side effect of saving.
- Changes to Page content without modifying its assigned Template, and changes to a Template without overwriting Page content.

### 5. Message protocol and concurrency

- Correlate messages with a request ID, entity identity, and snapshot revision/version.
- Validate both `event.origin` and `event.source`; use an explicit target origin.
- Define explicit ready, prepare, success, and failure outcomes. Names must be introduced and documented as new protocol definitions.
- Deduplicate simultaneous save triggers. Prevent older responses from marking newer edits clean.
- Preserve edits made during an in-flight save, including CSS-only, JS-only, and override-only edits.
- Preserve undo history unless a verified reason requires changing it. Do not unconditionally erase edits created after snapshot capture.
- Detect entity switches and stale iframe responses. Do not inject a Template response into the Page editor store.
- Define timeout/retry behavior that retains unsaved data. Do not unmount the iframe simply because a timeout elapsed.
- Review concurrent tabs/users and native post locking. Document conflict handling; do not silently claim cross-tab consistency without a version check.

### 6. Native lifecycle compatibility

Local WordPress source inspected exposes `rest_pre_insert_{$this->post_type}` and `rest_after_insert_{$this->post_type}` in both post and template REST controllers. These are candidate integration points, not a complete implementation prescription.

Worker must verify:

- Request shape differences and controller behavior for Page/Post/CPT versus Template/Part.
- Whether a chosen hook can reject saving or only observe it. A post-save action alone cannot establish rollback or propagate a complete transaction result.
- Draft, first Publish, Update, scheduled publication, revisions, and restore behavior.
- Autosave must not overwrite published frontend files or promote autosaved content to canonical published output.
- Plugin metadata/metabox saves may use separate requests. Preserve native behavior and define completion boundaries based on actual integration, including SEO plugins.
- Security and sanitization must remain capability-aware. Do not globally disable KSES for native Gutenberg posts.
- Existing REST consumers, export/import, translation, and revision flows must continue to work with the shared persistence service.

## Implementation scope and order

1. Reproduce existing behavior on disposable test entities. Capture request order, request payload identity, database content, derived files, and editor state before/after saving.
2. Write down the selected native interception points, canonical storage, protocol, failure behavior, and Template structure preservation strategy.
3. Extract minimal shared preparation/persistence helpers without changing unrelated code.
4. Implement Page/native-post and Site Editor entity adapters separately.
5. Route embedded Builder Save and keyboard saves through the same coordinator; retain standalone Builder support.
6. Remove superseded duplicate bridge triggers and raw HTML feedback behavior.
7. Implement canonical file synchronization, error reporting, and editor-state reconciliation.
8. Run the acceptance matrix, update relevant `align/docs/` documentation, and report evidence and remaining limitations.

Keep every changed custom PHP/JS/CSS file under 500 lines. Add structured English logs using existing Align tags. Include request ID, entity identity, version, payload lengths, and stage/error context; avoid logging full sensitive payloads.

## Acceptance matrix

| Test | Required result |
| --- | --- |
| Page HTML/CSS/JS/overrides plus sidebar changes | One user Save persists all supported changes; reload reflects the acknowledged version. |
| Builder Save while embedded | Uses the same coordinator and includes pending native changes within the selected save intent. |
| Native Gutenberg Blog/CPT | No unrelated Builder request and no injected `align/*` blog blocks. |
| Template only / Part only / several selected Site Editor entities | Correct entities saved; unchecked entities untouched; exact references and layout preserved. |
| Empty body / clear CSS / clear JS / clear overrides | Intentional deletion persists; omitted fields are not treated as destructive empty values. |
| Large payload / slow response | No timeout-driven data loss, false success, or duplicate writer. |
| Double click / shortcut plus click / programmatic save | No overlapping conflicting persistence for the same snapshot. |
| Edit again while saving | Older completion does not erase or mark new changes saved. |
| Switch entity while saving | Response is applied only to the captured entity; unsaved state remains correct. |
| REST error / expired nonce / lost response / disk failure | Clear incomplete state and safe retry; no false overall Saved notice. |
| Autosave / revisions / draft / Publish / scheduled publication | Native semantics preserved; autosave does not mutate published assets. |
| Multiple save/reload cycles | Stable content and assets without wrapper duplication or lost blocks. |
| Shortcodes / reusable blocks / external embeds | `data-component-shortcode`, overrides, and references survive. Test supported Studio-exported reusable embeds; do not paste script embeds into canvas. |
| Template header/footer variants and post-content | Exact layout references survive; Page and Template content remain separate. |
| Existing REST/export/import/translation consumers | Compatibility verified against actual callers. |
| Two tabs/users | Stale version handled explicitly; no unsupported claim of conflict-free saving. |

Verify browser state, REST results, database content, and disk outputs together. Compare parsed/canonical content or hashes where formatting/minification changes bytes. Log intended versus actual persisted version.

## Worker handoff deliverable

Provide the reviewed architecture, exact files changed, meaningful test results, and remaining limitations. Distinguish verified behavior from assumptions. Do not declare complete merely because both Save buttons show success or because a single happy-path Page save works.
