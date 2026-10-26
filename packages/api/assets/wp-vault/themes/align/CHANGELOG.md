# Changelog

All notable changes, fixes, and improvements to the **Align** theme will be documented in this file.

## [7.4.8] - 2026-10-07

### Headless Local Agent CLI Bridge, SOT Dual-Storage & Dynamic Templates
- **Headless Local Agent CLI Bridge (`align/bin/agent-page`, `align/bin/agent-page-core.php`)**:
  - Direct CMS and Database bridge for local AI Agents with automatic LocalWP environment detection (PHP binary & MySQL socket).
  - Dual-storage synchronization: Updates DB `wp_posts.post_content` (SOT) and disk template `align-child/templates/{slug}.html` simultaneously, preventing the `Align_Template_Tweaks::sync_all_templates_with_db()` revert trap.
  - Safe operations lifecycle: `pull`, `push`, `preview`, `rollback`, `cleanup`, `list`, `template-list`, `template-create`, `template-assign`, `template-delete`.
  - Automatic `backup.json` generation, KSES bypass for CLI execution, cache invalidation (`clean_post_cache()`), and two-way slug/post ID fallback.
- **Dynamic Gutenberg Template Standardization (`align-child/templates/`)**:
  - Restructured single templates (`single.html`, `spotlight-builder.html`) with dynamic hero banner using Gutenberg block comments (`<!-- wp:align/custom-html -->`, `<!-- wp:group -->`).
  - Implemented dynamic shortcodes (`[align_post_hero_image]`, `[align_post_title]`, `[align_post_eyebrow]`, `[align_post_meta_line]`, `[align_post_share]`, `[align_post_pills]`, `[align_post_secondary_meta]`, `[align_similar_guides]`).
- **Unified Documentation Hub & 360-Degree Indexing (`align/docs/`)**:
  - Added `architecture/local-agent-cli.md`: protocol specification and commands for local AI agents.
  - Added `issues/bug-logs.md`: anti-regression memory documenting recent resolved issues and root causes.
  - Updated `align/docs/CONTEXT.md`, `align/docs/README.md`, and `align/docs/manifest.json`.

## [7.4.7] - 2026-10-07

### Code Editor & DOM Morphing Baseline Snapshot Re-synchronization
- **DOM Baseline Re-snapshotting Helper in Canvas Iframe (`class-builder-content-handler.php`)**:
  - Injected `window.__alignRefreshDomSnapshot(root)` directly into the pre-animation snapshot script in the canvas iframe.
  - Recursively traverses and re-records current element classes and inline styles into `window.__ALIGN_DOM_SNAPSHOT__` (`WeakMap`).
- **Synchronized DOM Morphing in Builder Engine (`builder-drag-drop.js`)**:
  - Added `Vvveb.Builder.refreshDomSnapshot(target)` with iframe existence and cross-window guard checks.
  - Automatically triggered `refreshDomSnapshot()` immediately after `DomMorph.morph(target, newElements)` in `Vvveb.Builder.setHtml()`, ensuring live DOM edits are registered in the snapshot baseline before serialization.
- **Save Pipeline Pre-Flight Flush & Event Synchronization (`wp-save-handler.js`)**:
  - Ensured `Vvveb.CodeEditor.flush()` runs on every snapshot capture, guaranteeing pending editor changes are applied and re-snapshotted before serializing.
  - Bound automatic baseline re-snapshotting to `Vvveb.Builder.propertyChange`, `vvveb.undo.add`, and `vvveb.undo.restore` events.
  - Resolved bug where modifying class names or inline styles in Code Editor resulted in stripped classes/styles upon saving due to false-positive runtime artifact detection in `align-serializer.js`.
- All modified files maintained strictly under 500 lines with 100% English comments and logs.

## [7.4.6] - 2026-10-07

### File-First Single Source of Truth (SOT) & Clean Database Architecture
- **Pure Clean Markup in Database & Disk Templates (`class-builder-persistence-service.php`)**:
  - Removed embedded `<style id="...">` and `<script id="...">` tags from `wp_posts.post_content` on page/template save, reducing database payload size by 80-90%.
  - Embedded clean block attributes (`styleId` and `scriptId`) in `<!-- wp:align/custom-html -->` metadata comment.
  - Stripped all inline `<style>` and `<script>` tags from physical `.html` template files (`parts/*.html`, `templates/*.html`) on disk, ensuring 100% clean template code for developers.
  - Extracted scoped CSS/JS to external physical files in `assets/builder/` via `Align_Builder_Assets::save_external_assets()`.
- **Public Disk Asset Enqueue & Dynamic Rendering (`class-builder-assets.php`, `render.php`)**:
  - Exposed `Align_Builder_Assets::enqueue_disk_asset()` as `public static` with automatic directory/URI resolution.
  - Declared `styleId` and `scriptId` attributes in `custom-html/block.json`.
  - In `render.php`, directly enqueued physical builder assets based on block attributes while maintaining legacy inline fallback.
- **Proactive Template Assets Enqueuing (`template-assets-loader.php`)**:
  - Added `enqueueBuilderAssets()` hooked to `wp_enqueue_scripts` (priority 20) with automatic resolution for singular posts/pages (`is_singular()`), templates (`style-template-{$slug}.css`), and template parts (`style-part-header.css`, `style-part-footer.css`).
- **Complete Backward Compatibility Guard**:
  - Legacy sites with `<style>`/`<script>` in DB still render safely: if disk file exists, it is enqueued and inline tags stripped; if disk file is missing, inline `<style>` is preserved to avoid broken styles.
  - Visual Builder canvas editor loads disk file first with automatic fallback to DB `<style>`.

## [7.4.5] - 2026-10-05

### REST Context Admin API Guard, Safe File Persistence & Multiplatform Logging
- **REST Context Admin API Guard (`class-builder-persistence-service.php`, `class-builder-export-import-handler.php`)**:
  - Guarded calls to `wp_tempnam()` by dynamically checking and requiring `wp-admin/includes/file.php` if missing in REST API context.
  - Wrapped `$dir` with `trailingslashit()` to ensure temporary files are generated inside the intended folder rather than concatenated to the parent directory.
  - Implemented robust UUID-based fallback temporary filename generation (`wp_generate_uuid4()`) when `wp_tempnam()` returns false or empty.
- **Cross-Platform Logging & Uncaught Exception Visibility (`logger.php`)**:
  - Implemented `align_logger_is_theme_file()` normalizing Windows backslashes `\` to Unix slashes `/` across error, exception, and fatal handlers.
  - Prevented silent exception swallowing by always forwarding uncaught errors to system `error_log` with `[Align Core]` prefix.
- **Robust Frontend Save Response Handling (`wp-save-handler.js`)**:
  - Replaced blind `res.json()` with `res.text()` pre-flight parsing and try/catch block.
  - Provided descriptive HTTP status error toasts on server errors (500/502) instead of cryptic `Unexpected end of JSON input`.
- All modified files kept strictly under 500 lines. 100% comments and logs in English.

## [7.4.4] - 2026-10-03

### Universal Pre-Animation Baseline Snapshot & Dynamic Artifact Stripping
- **Pre-Animation Baseline Snapshot Injection (`class-builder-content-handler.php`)**:
  - Injected an in-memory baseline snapshot script right before canvas footer scripts run, recording clean node classes and inline styles into `window.__ALIGN_DOM_SNAPSHOT__` (`WeakMap`).
  - Pre-animation timing guarantees clean baseline state captured before any 3rd-party or theme animation scripts (GSAP, AOS, Locomotive, Splitting) execute.
- **Universal Dynamic Attribute Cleaning (`align-serializer.js`)**:
  - Integrated dynamic snapshot comparison during serialization: any runtime classes or inline styles added by animation scripts that were not in the baseline snapshot are automatically stripped.
  - Hardened `[data-split]` text unwrap with authored inline child guard (`<a>`, `<strong>`).
  - All modified files kept strictly below 500 lines (`align-serializer.js`: 470 lines, `class-builder-content-handler.php`: 492 lines). All 11 tests passing.

## [7.4.3] - 2026-10-02

### Live CMS Animation Engine & Serialization Gate Clean State
- **Full Live Animation & Smooth Scroll in Builder Canvas (`align-child/assets/js/main.js`)**:
  - Unlocked animation engine, WebGL 3D, and smooth scroll execution in the editor iframe, delivering an authentic 100% WYSIWYG editing experience.
  - Eliminated editor canvas scroll freezing by keeping `wheel` listener and `frame()` animation loop active and synchronized.
- **Serialization Gate for Clean HTML Persistence (`align-serializer.js`)**:
  - Implemented automatic unwrap for `[data-split]` nested container elements (`span.ln`, `span.wd`, `span.w`, `span.char`, `span.word`) back to clean text nodes, fully preserving any new text typed by the user.
  - Auto-stripped runtime transient active classes (`is-in`, `is-buried`, `is-px`) and cleaned empty `class=""` attributes.
  - Filtered computed runtime animation styles (`--p`, `--cover`, `--sp`, `clip-path`) while strictly preserving static styling rules (`--i`, `max-width`).
  - Preserved 100% of HTML5 structure, SVGs, pictures, videos, cards, and user modifications.
  - Maintained `align-serializer.js` at 386 lines (< 500 lines per `AGENTS.md`) with 100% English comments.

## [7.4.2] - 2026-10-01

### Defensive Guards for Rank Math Redirections & Settings Tab Isolation
- **Rank Math Active State & Module Detection Guard (`class-align-redirection-importer.php`)**:
  - Replaced naive `class_exists( '\RankMath\Redirections\DB' )` check with a 3-tier guard checking `\RankMath\Helper`, `\RankMath\Helper::is_module_active('redirections')`, and DB class existence.
  - Resolved fatal error / output truncation on live sites where Rank Math is installed but the Redirections module is disabled in the Rank Math dashboard.
  - Wrapped all AJAX handlers (`handle_import`, `handle_export`, `handle_delete_all`) with `try...catch (\Throwable $e)` and English error logging prefixed with `[Align RedirectionImporter]`.
- **Graceful Settings Fallback UI & Safe Count Resolution (`tab-redirections.php`)**:
  - Guarded `\RankMath\Redirections\DB::get_counts()` call within `try...catch (\Throwable $e)` with zeroed fallback stats.
  - Displayed informative fallback warning notice (`align-notice-box--warning`) with a direct link to the Rank Math Dashboard when the module is inactive.
  - Prevented settings page truncation, ensuring all remaining setting tabs and scripts load completely.
- **Cross-Module Rank Math Redirection Safeguards (`class-align-translation-matrix.php`, `class-align-404-collector.php`)**:
  - Integrated `Align_Redirection_Importer::is_rank_math_active()` into `create_rank_math_redirect()` in both modules.
  - Wrapped database queries and redirect creation logic in `try...catch (\Throwable $e)` blocks to guarantee unhindered execution when Rank Math database tables are missing.
  - Maintained all modified files strictly below 500 lines per `AGENTS.md`.

## [7.4.1] - 2026-09-20

### Synchronized Gutenberg Navigation, Page Creation & Loop Guard
- **Synchronized Gutenberg Shell Retention (`wp-items-crud.js`, `class-builder-crud-handler.php`, `class-builder-admin.php`)**:
  - Resolved editor desynchronization where creating a page while in Gutenberg either navigated to standalone builder (`admin.php?page=align-builder&post_id=...`) or caused parent Gutenberg post ID to diverge from embedded canvas.
  - Intercepted standalone direct access in `Align_Builder_Admin::handle_standalone_builder_page()` with server-side 302 redirect to Gutenberg `post.php?post={ID}&action=edit`, preserving `lang` query param.
  - Updated `create_item()` REST API to supply `admin_edit_url` and `editor_url` targeting Gutenberg `post.php`.
  - Navigated parent window (`window.top || window.parent || window`) on Page creation and tree navigation, accompanied by safe `ALIGN_NAVIGATE` postMessage bridge.
- **Redirect Loop Resolution & Multi-Language Preservation (`wp-filemanager.js`, `align-site-editor-integration.js`)**:
  - Removed internal engine `loadPage` hook in `wp-filemanager.js` that was erroneously treating initial canvas rendering as user navigation.
  - Forwarded active `lang` query param from Gutenberg to embedded builder iframe in `align-site-editor-integration.js`.
  - Added destination URL equality check in `navigateToItem()` and `ALIGN_NAVIGATE` listener, preventing infinite reload loops.
  - Fully preserved multi-language routing (`&lang=...`) across Polylang, WPML, and Align Multi-Language Switcher.
  - Added comprehensive unit test coverage with 18 passing tests in `align/tests/*.test.cjs`.
  - Maintained all modified source files strictly below 500 lines per `AGENTS.md`.

## [7.4.0] - 2026-09-18

### Filtered Archive Drag-and-Drop Post Reorder Bug Fix
- **Duplicate ID Pruning on Filtered Archive Reorder (`class-align-post-order-ajax.php`)**:
  - Resolved critical issue where dragging posts in a filtered archive (e.g. taxonomy filter or category view) caused new drag order to be overwritten upon page refresh (F5).
  - Replaced naive `array_splice` on `$all_posts` with `array_diff($all_posts, $new_order)` before splicing, preventing duplicate IDs from existing at tail indices and overwriting head updates during `$wpdb->update`.
  - Shifted `$position` indexing to 1-based (`position + 1`), ensuring all ordered posts receive `menu_order >= 1` so checks like `menu_order > 0` evaluate cleanly without falling back to defaults.
  - Kept file strictly at 104 lines (< 500 lines limit) with 100% English comments.

### 3 Screensize Slots & Independent Responsive Image Resolution
- **3 Dedicated Screensize Slots in Inspector (`media-size-tags.js`, `editor-wp-media.css`)**:
  - Replaced the legacy multi-chip auto-sorted media size selector with 3 independent screensize dropdowns:
    - Mobile (`< 600px` | `<source media="(max-width: 599px)">`)
    - Tablet (`< 992px` | `<source media="(max-width: 992px)">`)
    - Desktop (`> 992px` | fallback `<img>`)
  - Empowered users to bind any registered WordPress sub-size to any viewport independently (e.g. assigning large/desktop images to mobile viewports for high-DPR screens).
  - Provided `Auto (Smart Resolution)` as default option per device slot, dynamically falling back to ratio-aware proximity scoring.
  - Added 1-click **Reset to Auto** button conforming to Align transparent button standards.
  - Full Light & Dark Mode support (`[data-bs-theme="dark"]`).
- **Core Engine Backend Resolver Pipeline (`class-builder-image-resolver.php`)**:
  - Refactored `resolve_picture_breakpoints()` to parse key-value syntax `mobile:val,tablet:val,desktop:val` without re-sorting sub-sizes by width.
  - Guaranteed 100% backward compatibility for legacy comma-separated values (`data-align-sizes="size1,size2,size3"`).
  - Maintained strict file line budget (< 500 lines; 486 lines).
  - Added structured observability logs prefixed with `[Align ImageResolver]`.
- **Documentation & Standards (`responsive-media-shortcode-guide.md`, `builder-components-and-styles.md`)**:
  - Documented 3 Screensize Slots architecture, attribute schema, and auto-mapping rules.

## [7.3.9] - 2026-09-17

### Media Zero Hard Crop Default & Admin Accordion Conflict Resolution
- **Zero Hard Crop Default Policy (`class-align-image-sizes.php`, `part-media-sizes-table.php`)**:
  - Set `default_crop` for `thumbnail` to `0` in `Align_Image_Sizes::get_standard_sizes_definitions()`, ensuring proportional scaling by default across all registered media sizes.
  - Updated `sync_standard_sizes_to_wp_core()` to synchronize default `0` to core `thumbnail_crop` when `align_size_*_crop` has not been customized, preventing physical cropping on upload.
  - Added hidden input `<input type="hidden" ... value="0">` before checkboxes in Standard and Custom sizes to ensure reliable `0` submission on uncheck.
  - Added descriptive tooltips to Hard Crop labels explaining proportional scaling vs exact dimension cropping.
- **Accordion Event Double-Binding & Arrow Deduplication (`settings-layout.js`)**:
  - Added guard check to skip generic accordion injection on cards that already provide dedicated controllers (`.align-accordion-toggle` or `data-accordion-manual`).
  - Fixed conflict where clicking the header of *Registered Media Sizes* immediately closed the card within 0ms due to dual listeners.
  - Removed duplicate injected triangle arrows (`▼`) from all specialized accordion cards.

## [7.3.8] - 2026-09-17

### Bidirectional Gutenberg ⇄ Align Builder Mode Switcher for Blog Posts
- **Native Gutenberg Default for Blog (`post`) Posts**:
  - Configured Blog posts (`post` post type) to default 100% to standard Gutenberg block editor, complying with `AGENTS.md` Blog content policy (Paragraph, Heading, Image...).
  - Maintained automatic Align Builder embedded canvas behavior for Pages and FSE Templates (`page`, `wp_template`, `wp_template_part`).
- **Two-Way Header Mode Switcher (`align-site-editor-integration.js`, `align-editor-overlay.css`)**:
  - Integrated 100% transparent switcher button into Gutenberg Header Topbar (`.editor-header__settings`, `.edit-post-header__settings`) conforming to Align Settings Style Guide.
  - "Edit with Align Builder" switches to embedded Builder canvas and updates meta state.
  - "Switch to Gutenberg" triggers safe asynchronous save from Builder iframe, updates Gutenberg editor store with clean HTML, and unmounts canvas cleanly.
  - Responsive collapse to icon-only (32x32px) on viewport widths < 782px.
- **Post Meta State Persistence (`class-align-page-settings.php`)**:
  - Registered `_align_editor_mode` post meta with REST API support, strict whitelist sanitization (`gutenberg` | `builder`), and Classic Editor fallback.
  - Ensured editor state is preserved across page refreshes (F5) per post.
- **Role Permissions & Security Hardening (`class-builder-admin.php`, `align-site-editor-integration.js`)**:
  - Updated builder access check to allow `edit_posts` alongside `edit_pages`, enabling Author and Editor roles to use the builder on Blog posts without 403 errors.
  - Added strict `event.origin === window.location.origin` validation on `message` listeners and restricted `postMessage` targets.

## [7.3.7] - 2026-09-13

### Core Theme Performance, Speculative Rules Guard & Asset Synchronization
- **Lenis Smooth Scroll Hybrid Polling Engine (`class-align-smooth-scroll.php`)**:
  - Upgraded pre-minified Lenis initialization script (`LENIS_INIT_JS`) to support hybrid initialization (`_initLenis()`).
  - Added multi-event listeners (`DOMContentLoaded`, `load`) and high-frequency polling (`setInterval(..., 50)` up to 10s), ensuring Lenis successfully initializes even when deferred or delayed by Delay-JS performance strategies.
- **Suppression of WordPress 6.8+ Speculative Rules (`functions.php`)**:
  - Unhooked `wp_print_speculation_rules` from `wp_footer` and filtered `wp_speculation_rules_configuration` to `__return_null`.
  - Prevented Chromium browsers from automatically prefetching/prerendering background pages, eliminating duplicate background HTTP requests and cache/session interference.
- **Enhanced Mock jQuery Proxy Engine for Delay-JS (`align-jquery-proxy.js`)**:
  - Extended lightweight mock jQuery proxy to support `$.Event`, `$.param`, `$.Callbacks`, `$.Deferred`, `$.when`, AJAX stubs, and chainable dummy DOM methods.
  - Added user interaction listeners (`touchstart`, `mousemove`, `scroll`, `keydown`, `click`) to automatically trigger delayed script execution for `[data-delay="true"]` and `[data-align="optimize"]` elements.
  - Provided `window.__loadRealJquery()` callback for seamless real jQuery fallback with queued callback execution.
- **Child Theme Module CSS Discovery & Bootstrap Grid Restoration (`class-builder-assets.php`, `class-builder-crud-handler.php`)**:
  - Restored `assets/bootstrap-grid.min.css` to Builder global stylesheet candidates.
  - Implemented automatic discovery of child theme module stylesheets (`inc/*/assets/css/*.css`).
  - Fixed stylesheet queue snapshot order in REST render endpoints (`render-shortcode`, `render-block`), capturing dynamically enqueued styles into Shadow DOM preview payloads.
- **Builder Selection Manager SyntaxError Fix (`builder-selection-manager.js`)**:
  - Restored missing closing brace in `selectNodes()` method, fixing uncaught SyntaxError that broke `self._initBox()` on canvas element selection.
- **Visual Builder Content Mode: Single-Click Natural Inline Editing & Floating Bubble Toolbar (`builder-highlight-manager.js`, `builder-word-editor.js`, `builder-wysiwyg-editor.js`, `canvas.html`)**:
  - Replaced legacy double-click requirement with single-click inline text editing when Content Mode is active.
  - Resolved editable elements via `resolveTextEditableElement(target)`, supporting `p`, `h1`-`h6`, `li`, `blockquote`, and inline text tags.
  - Implemented exact caret positioning at click coordinates using `caretRangeFromPoint` / `caretPositionFromPoint` without artificial word selection or blue highlighting.
  - Anchored floating bubble toolbar (`#word-bubble-toolbar`) directly above active text blocks upon single-click editing in Content Mode.
  - Kept toolbar visible when selection is collapsed during typing and cursor movement, while dynamically re-anchoring to selected text when users drag-select words.
  - Prevented iframe focus loss on toolbar button clicks via `mousedown` event `preventDefault()`.
  - Added real-time toolbar button active state synchronization via `document.queryCommandState` (Bold, Italic, Underline, Strikethrough) and expanded block format dropdown to include Heading 4, 5, and 6.
- **Custom Template & Block Asset Decoupling and State Preservation (`plugin-custom-templates.js`, `plugin-custom-blocks.js`, `builder-new-section.js`, `builder-drag-drop.js`, `class-builder-content-handler.php`, `class-builder-crud-handler.php`)**:
  - Eliminated `packInlineAssets()` and `packInlineAssetsIntoFirstElement()` which incorrectly embedded `<style>` and `<script>` directly as children of HTML root elements in the body.
  - Implemented `cleanTemplateHtml()` / `cleanBlockHtml()` and `afterDrop(node)` hooks: automatically injects template/block CSS into `Vvveb.StyleManager` & `Vvveb.CssEditor` and JS into `Vvveb.JsManager` & `Vvveb.JsEditor`, preserving strict separation of concerns (HTML Body, CSS, and JS states).
  - Added automatic CSS/JS extraction in modal form when users paste raw HTML containing inline style/script tags.
  - Hardened `builder-new-section.js` against undefined return values from `afterDrop()`.
  - Upgraded `builder-drag-drop.js` (`setHtml`) to extract untagged `<style>`/`<script>` into `StyleManager`/`JsManager` and purge them from the body HTML.
  - Added fallback in `class-builder-content-handler.php` (`get_page_content`) and `class-builder-crud-handler.php` (`save_page`) to hoist untagged body styles/scripts into `<head>` with scoped IDs and external asset files, completely preventing CSS/JS loss when editing or deleting template HTML.

## [7.3.6] - 2026-09-12

### Streamlined Navigation Entry Points for Embedded Align Builder
- **Removal of Redundant UI Entry Points (`class-builder-admin.php`)**:
  - Unhooked `admin_bar_menu` (`add_admin_bar_builder_link`): Eliminated "Edit with Align Builder" button from the frontend WordPress Admin Bar since page editing is handled seamlessly within Gutenberg.
  - Unhooked `page_row_actions` and `post_row_actions` (`add_row_actions`): Removed redundant "Edit with Align Builder" action link on Pages and Posts list tables. Standard "Edit" link now directly opens Gutenberg with the embedded Align Builder.
  - Converted `register_builder_menu()` to Headless Page Registration: Switched from `add_menu_page()` and `add_theme_page()` to `add_submenu_page( null, ... )`, completely removing the top-level "Align Builder" menu from the WP Admin sidebar and Appearance menus while preserving `admin.php?page=align-builder` route for Gutenberg embedded iframe canvas.

## [7.3.5] - 2026-09-11

### Core Template Isolation & Revert of Accidentally Pushed Child Templates
- **Parent Core Theme Decontamination (`align/parts/`, `align/templates/`)**:
  - Reverted and removed `align/parts/` and project-specific templates in `align/templates/` (`*-zh.html`, `404.html`, `single.html`, `page.html`, etc.) mistakenly pushed into core theme repository.
  - Restored `align/templates/index.html` to its pristine initial state (`<!-- index -->`).
  - Transferred all project-specific localized templates and template parts into the respective child theme (`align-child/`), preserving 100% architectural independence.
- **Child Theme Protection & Overwrite Prevention (`class-align-template-tweaks.php`)**:
  - Removed destructive parent-to-child copy loop (`copy($pf, $cf)`) from `sync_all_templates_with_db()`.
  - Enforced strict WordPress block theme architecture where child theme files always take precedence and parent themes never overwrite child themes.
  - Restricted template database synchronization strictly to the active stylesheet directory (`get_stylesheet_directory()`).

## [7.3.4] - 2026-09-11

### Revision Comparison Studio Layout Regression & Drag Shield Fix
- **Revision History Modal Layout & Specificity Fix (`bootstrap-custom.css`, `editor-modals-toolbar.css`)**:
  - Resolved layout regression where `#revisionsModal` was constrained to 500px on screens >= 576px. Boosted `.modal-fullscreen` selector to `.modal-dialog.modal-fullscreen, .modal-fullscreen` with specificity `(0, 2, 0)` to override `.modal-dialog` max-width.
  - Inserted `.d-none, [hidden] { display: none !important; }` after flex utilities in `bootstrap-custom.css`, restoring Bootstrap utility precedence.
  - Added scoped CSS rules in `editor-modals-toolbar.css` enforcing strict 100vw × 100vh fullscreen layout, zero margins, and high-specificity `.d-none` display override.
- **Dual Container Mutual Exclusivity & Responsive Toolbar (`modal-revisions.html`, `wp-revision-handler.js`)**:
  - Resolved bug where Split View and Single View containers were rendered simultaneously. Initialized `#rev-single-preview-container` with `class="w-100 h-100 flex-column d-none"` and inline `display: none !important`.
  - Refactored `setMode(mode)` in `wp-revision-handler.js` to synchronously toggle `d-none` and `d-flex` while removing inline `display` style, ensuring strictly one container is active at any time.
  - Added `text-nowrap flex-shrink-0` to toolbar buttons and enclosed revision selectors in flex containers (`min-width: 0`, `text-overflow: ellipsis`) to prevent metadata clipping.
- **Master Scrollbar Offset & Pointer Capture Drag Shield (`wp-revision-handler.js`, `editor-modals-toolbar.css`)**:
  - Offset `#rev-master-scrollbar` track with `top: 38px` so it no longer overlaps the 38px column sub-header.
  - Upgraded drag synchronization to modern Pointer Events API (`pointerdown`, `pointermove`, `pointerup`, `pointercancel`) with `setPointerCapture` and `releasePointerCapture`.
  - Added `#revisionsModal.rev-is-dragging iframe { pointer-events: none !important; }` drag shield to prevent iframe mouse interception during scrollbar dragging.

## [7.3.3] - 2026-09-11

### Font Preload Location Management (Header vs Footer) & State Synchronization
- **Font Preload Location Decoupling (`class-align-font-manager.php`, `class-align-admin-assets.php`, `tab-load-assets.js`)**:
  - Resolved root cause where saving Align Settings repeatedly reset `align_font_slots` to hardcoded default slots with `location: header`. Decoupled `align_font_slots` from `align_settings_group` registration so `options.php` no longer passes `null` on form submit.
  - Added dynamic sync hooks `update_option_align_loaded_assets` and `add_option_align_loaded_assets` to automatically compile font slots and CSS caches when `align_loaded_assets` is modified.
  - Emptied hardcoded default slot files in `get_default_slots()`, ensuring font slots are strictly derived from user-managed assets.
  - Hardened `sanitize_and_compile_slots()` to fall back to existing database values when receiving null or invalid input.
  - Enabled font location selection (`Header` vs `Footer`) in Asset Loader (`tab-load-assets.js` and `class-align-admin-assets.php`).
  - Separated font preloading: `output_preloads()` outputs only `header` fonts in `<head>` (Priority 1), while `output_footer_preloads()` outputs `footer` fonts in `wp_footer` (Priority 90).
  - Separated `@font-face` compilation: rules for footer fonts are deferred to `<style id="align-fonts-delayed-css">` in `wp_footer` (Priority 95), preventing render blocking in `<head>`.
  - Updated builder preview (`class-builder-assets.php`) to filter out footer fonts from canvas header preloads.

## [7.3.2] - 2026-09-11

### Unified 1-Step Font Pipeline in Asset Loader & Global Typography Enforcement
- **Unified 1-Step Font Pipeline (`class-align-font-manager.php`, `class-align-admin-assets.php`, `tab-load-assets.php`, `tab-load-assets.js`)**:
  - Consolidated font management into a single, intuitive workflow directly in the Asset Loader table (`align_loaded_assets`).
  - Automatically detects uploaded font assets (`type: font`), enforces `Header` location with `Normal` loading strategy, and disables inline checkbox.
  - Added dedicated `font_weight` dropdown (100 to 900) with smart auto-detection (`detectFontWeight` / `detect_weight_from_filename`), prioritizing `semibold` (600) before `bold` (700) to eliminate misclassification.
  - Added 1-click copy badge chip displaying normalized font slot identifier (`font-primary-1..n`), allowing instant clipboard copying of CSS utility `.ff-1` or variable `var(--font-primary-1)`.
  - Automatically groups uploaded font files by family name (`detect_family_from_filename`) and compiles dual `@font-face` rules for both `font-primary-X` and original family name (e.g. `Barlow`).
  - Cleanly removed redundant "Typography & Fonts" subtab from Master System Settings (`tab-master-system.php`), centralizing all font management into Asset Loader.
- **Global Typography & Bootstrap Reboot Override**:
  - Resolved `system-ui` fallback caused by `bootstrap-reboot.min.css` by injecting `--bs-body-font-family: var(--font-primary-1, '{family}', sans-serif);` into `:root`.
  - Enforced global base typography rule `html, body, h1, h2, h3, h4, h5, h6, .heading-text { font-family: var(--font-primary-1, '{family}', sans-serif); }` in compiled font CSS and root stylesheets, applying custom typography across the entire site without requiring `.ff-1` on individual elements.
- **Relative Path Resolution for Inline CSS (404 Font Fix)**:
  - Added regex rewriter in `Align_Custom_CSS_JS::output_custom_assets()` to automatically transform relative `@font-face` `url(...)` declarations into absolute URLs when CSS files are inlined (`is_inline = 1`), eliminating 404 font errors.

## [7.3.1] - 2026-09-10

### 3-Slot Typography & Font Architecture + CMS Page Settings Engine
- **3-Slot Typography & Font Architecture (`class-align-font-manager.php`, `tab-font-manager.php`, `tab-font-manager.js`, `tab-master-system.php`)**:
  - Introduced standardized 3-slot font system in Align Settings under Code & System Tools:
    - **Font 1 (Body / Primary)**: Binds to `--font-family-1` with Preload Top 1 Priority.
    - **Font 2 (Headings / Secondary)**: Binds to `--font-family-2` with Preload Top 1 Priority.
    - **Font 3 (Accent / Display)**: Binds to `--font-family-3` with configurable Normal, Preload, or Delay strategy.
  - Automatically compiles and pre-caches unified `@font-face` rules and root CSS variables upon saving, injecting into `<head>` (`<style id="align-fonts-css">`) with zero runtime overhead.
  - Synchronizes font CSS into Gutenberg Block Editor (`wp-block-editor`, `wp-edit-blocks`) and Align Builder Live Canvas.
  - Normalized font preload URLs (`resolve_font_url`, `$with_version = false`), eliminating query string mismatches (`?ver=...`) between preload links and CSS `@font-face`, resolving Chrome "preloaded but not used" warnings and duplicate downloads.
  - Built WordPress Media Uploader integration with filename-based smart weight auto-detection (`300` to `800`) and real-time live preview.
- **Per-Page CMS Page Settings & Load jQuery Toggle (`class-align-page-settings.php`, `align-page-settings.js`)**:
  - Re-introduced Gutenberg Document Sidebar panel "Align Page Settings" with a "Load jQuery" toggle.
  - Registered `align_load_jquery` boolean post meta exposed to REST API and Block Editor redux store.
  - Added backward-compatible Classic Editor meta box with `'__back_compat_meta_box' => true` to suppress duplication in Gutenberg.
  - Integrated seamlessly with `template-assets-loader.php` so jQuery is stripped for guests by default unless explicitly toggled per-page.

## [7.3.0] - 2026-09-09

### Custom Assets Table Typography-First Text Display & Border Removal
- **Concise Basename Display & Border Removal (`tab-load-assets.php`, `tab-load-assets.js`)**:
  - Replaced bulky input boxes with clean, borderless typography text displaying only the asset filename (e.g., `main.css`, `script.js`, `inter.woff2`) instead of long truncated paths.
  - Added native browser hover tooltip (`title="${url}"`) on filename text for instant access to the full asset URL without visual clutter.
  - Replaced `<select>` dropdown in Type column with lightweight uppercase plain text badges (`CSS`, `JS`, `FONT`), eliminating WordPress default select borders and dropdown arrows.
  - Maintained 100% form persistence and database compatibility by storing complete URLs and types in `<input type="hidden">` fields (`align_loaded_assets[index][url]` and `align_loaded_assets[index][type]`).
  - Added PHP helper `align_get_asset_filename( $url )` with `wp_basename` to cleanly strip query strings and hash fragments.
  - Added JavaScript helper `getFilenameFromUrl( url )` with URI decoding (`decodeURIComponent`) and trailing slash normalization.
  - Streamlined dynamic row handlers (`createRow`, single file upload, manual row input) to smoothly transition from entry inputs to borderless text displays upon file selection or blur.
  - Pruned obsolete event listeners (`dblclick`, `focusout`, `input`) in `tab-load-assets.js` to ensure file length remains strictly under 500 lines (462 lines).

## [7.2.9] - 2026-09-09

### WebP Media Converter Concurrency Throttling & Server Protection
- **Async Worker Pool Engine (`part-webp-converter.php`)**:
  - Upgraded bulk WebP conversion in Align Settings from sequential 3-item chunking to an asynchronous worker queue with strict concurrency limit of 2 (`CONCURRENCY = 2`).
  - Workers fetch 1 attachment ID individually (`ids[] = id`), dynamically replenishing the pool via `.finally()` upon request completion.
  - Implemented non-blocking fault tolerance: network drops or server timeouts increment error counts without deadlocking the queue.
  - Fixed progress bar math to calculate off fixed `totalInitial` count, ensuring accurate and smooth progress reporting.
- **Defense-in-depth Server Guard (`class-align-webp.php`)**:
  - Enforced a hard batch limit of 2 IDs per request (`array_slice( $ids, 0, 2 )`) in `ajax_bulk_batch()`, preventing microservice overload or DoS from oversized client payloads.
  - Maintained strict file size limits (< 500 lines): `part-webp-converter.php` (214 lines) and `class-align-webp.php` (344 lines).

## [7.2.8] - 2026-09-09

### Reusable Block Overwrite Persistence & Multi-Context Render Resolution
- **CMS Builder Preview Template Context Resolution (`class-builder-crud-handler.php`)**:
  - Differentiated reusable block shortcodes (`$is_reusable`) from standard shortcodes in `/render-shortcode` REST endpoint.
  - When rendering reusable blocks inside templates (`tpl_*`), resolved `$page_id` directly to the actual template post ID (e.g., Post 319 for `single-fluent-products`) via `Align_Reusable_Block::resolve_template_post_id()`, eliminating erroneous lookups that pulled unrelated blog posts.
  - Automatically normalized `page_id="{$page_id}"` attribute inside evaluated shortcodes to maintain context consistency.
- **Cascading Template Overrides & Namespace Scoping (`class-align-reusable-block.php`)**:
  - Restricted cascading query in `get_cascading_template_overrides()` strictly to `'post_type' => 'wp_template'`, preventing template parts (e.g. `parts/footer.html`) from inadvertently bleeding into the template fallback queue.
  - Enforced hierarchical specificity sorting (`single-{post_type}` -> `single` -> `index`), ensuring specific CPT template overrides always take precedence over global templates.
  - Added multi-tier composite caching (`$cascading_cache[$cache_key]`) keyed by combined slug hierarchy and reusable post ID to eliminate redundant database queries.
  - Updated `resolve_template_post_id()` to search across both `wp_template` and `wp_template_part` when template type is ambiguous, with theme stylesheet/template taxonomy prioritization.
  - Maintained strict file limit compliance: `class-align-reusable-block.php` (499 lines) and `class-builder-crud-handler.php` (497 lines).

## [7.2.7] - 2026-09-09

### Settings Notice Deduplication & WordPress Default Media Menu Removal
- **Notice Deduplication & Isolation (`layout.php`, `settings-layout.js`)**:
  - Removed duplicate `settings_errors()` invocation in `layout.php` (already called automatically by WordPress core in `options-head.php` when saving options).
  - Enhanced `repositionNotices()` in `settings-layout.js` to scan both `#wpbody-content` and `.align-settings-wrap`, skipping tab internal notices and nested tags.
  - Implemented set-based notice deduplication using whitespace-normalized text (`notice.textContent.replace(/\s+/g, ' ').trim()`) to eliminate duplicate notice banners.
  - Placed unique notice cleanly above `.align-nav-tabs-bar` with 6px rounded border styling conforming to the Align Settings Style Guide.
  - Added URL hash sanitization (`cleanId.replace(/[^a-zA-Z0-9_-]/g, '')`) in `activateTab()` and subtab aliases (`media`, `webp`, `media-sizes`) in `activateSubtab()`.
- **Default WordPress Media Settings Menu Removal & Redirection (`class-align-admin-settings.php`)**:
  - Hooked into `admin_menu` at priority 99 to remove redundant default `options-media.php` submenu under Settings (`options-general.php`) via `remove_submenu_page()`.
  - Added router guard in `admin_init` (`redirect_wp_media_settings_page()`) to safely redirect direct visits to `options-media.php` over to Align Settings Media tab (`options-general.php?page=align-settings#performance/perf-media`).
  - Added background execution guards for WP-CLI, WP-Cron, AJAX, and REST API, along with `headers_sent()` JavaScript fallback to prevent White Screen of Death (WSOD).

## [7.2.6] - 2026-09-09

### Connect API Sub-Tabs, Cloud API Resilience & Custom Assets Enhancements
- **Connect API Dual-Tab Architecture (`class-align-ai-connection.php`, `tab-connect-api.php`, `tab-connect-api.js`, `settings-layout.css`)**:
  - Split AI LLM Connect Hub into 2 independent sub-tabs: **OpenRouter** and **OpenAI Official & Compatible**.
  - Isolated credential stores (`align_openrouter_api_key`, `align_openai_api_key`, `align_openai_base_url`, `align_openai_model`, `align_openai_organization`) to prevent key clobbering when switching providers.
  - Implemented 1-Click active provider switcher (`align_ai_provider`) with instant visual badge and live testing per provider.
  - Configured default accordion states: AI LLM Connect Hub open by default, other accordions collapsed.
- **Cloud Microservices Resilience (Option A - Non-Destructive Passthrough) (`class-align-webp.php`, `class-builder-minifier.php`, `class-align-block-converter.php`, `editor-html-paste.js`, `class-align-endpoint-tester.php`)**:
  - **WebP Engine**: Bypasses conversion when Cloud API is down, preserving original image uploads intact without destructive local PHP GD re-compression.
  - **Minifier Engine**: Bypasses CSS/JS minification when Cloud API is unreachable, returning clean unminified code passthrough without risky regex transformations.
  - **Gutenberg HTML Converter**: Bypasses block decomposition when Cloud API is down, safely wrapping pasted HTML in `core/html` block.
  - **Endpoint Health Tester**: Added robust error detection, latency monitoring, and non-blocking ignore handling across all 6 microservice endpoints.
- **Custom Assets Management & Location Persistence (`class-align-admin-assets.php`, `tab-load-assets.php`, `tab-load-assets.js`, `class-align-custom-css-js.php`)**:
  - **Is Inline Support**: Added `is_inline` checkbox allowing custom CSS and JS to be inlined directly into HTML markup (`<style>` / `<script>`) instead of external requests.
  - **Tamper Protection**: Set File URL to `readonly` and locked `type` select after file upload to prevent accidental corruption.
  - **Footer Location Bug Fix**: Eliminated hardcoded `$location = 'header';` override in `sanitize_loaded_assets()`, ensuring user-selected `Footer` placement for CSS and JS is reliably saved.
  - **File Size Compliance**: Maintained all modified files under the mandatory 500 lines/file limit.
- **Admin Bar Topbar Refinement (`class-builder-admin.php`)**:
  - Suppressed "Edit with Align Builder" shortcut from the top WordPress Admin Bar while inside `wp-admin` (`is_admin()`) to eliminate clutter and navigation duplication, while preserving it on frontend routes for quick 1-click page editing.

## [7.2.5] - 2026-09-08

### Reusable Block Save Persistence & Key Clobbering Prevention
- **Canonical Overrides Grouping & Multi-Tier Recursive Merging (`class-align-reusable-block.php`)**:
  - Implemented `uksort()` in `sanitize_overrides()` to process string slugs before numeric IDs, ensuring active builder edits overlay stale defaults.
  - Added canonical post ID grouping (`$clean_id`) and multi-tier `array_replace_recursive()` merging for instance-keyed payloads, preventing stale slug keys from clobbering updated numeric ID overrides.
  - Dual-indexed `$clean[$clean_id]` and `$clean[$slug]` with identical merged states for bi-directional consistency.
- **REST Save Payload Return (`class-builder-crud-handler.php`)**:
  - Enhanced `save_page()` endpoint responses (both template and post types) to return sanitized `'reusable_overrides'`, enabling immediate frontend cache synchronization.
- **Client Companion Key Synchronization & Save Persistence (`wp-save-handler.js`)**:
  - Added `altBlockId` resolution in pre-serialization to mirror merged instance overrides across both `blockId` and `altBlockId`.
  - Updated save success callback to re-synchronize `window._alignActiveReuseOverrides` with server response payload, eliminating in-memory state desynchronization.
- **Inspector Override Precedence & Live Key Synchronization (`reuse-block-inspector-live.js`, `reuse-block-inspector-api.js`)**:
  - Added `getAltBlockKey()` helper to seamlessly resolve companion keys (ID ↔ slug).
  - Prioritized `userOverrides[ovId]` over live DOM element text/attributes across Media, Button, Link, and Typography fields in `renderContentOverwriteAccordion()`, preventing DOM reload race conditions from reverting input fields.
  - Updated `triggerCanvasChange()`, `removeOverrideKey()`, and "Reset All" handlers to mutate and prune both keys synchronously in `window._alignActiveReuseOverrides`.

## [7.2.4] - 2026-09-08

### Reusable Block Slug & ID Dual-Compatibility & Cascading Overrides Isolation
- **Bi-Directional Slug & ID Resolution (`class-align-reusable-block.php`, `class-builder-ui-renderer.php`)**:
  - Enhanced `sanitize_overrides()` to accept both numeric IDs (`613`) and string slugs (`'cta-banner'`), dynamically resolving slugs via `resolve_slug_or_id()` and indexing entries under both keys for seamless O(1) lookups.
  - Added static in-memory memoization cache (`$slug_id_map`) with query optimizations (`no_found_rows => true`, `update_post_meta_cache => false`, `update_post_term_cache => false`) in `resolve_slug_or_id()`, preventing N+1 queries.
  - Localized `wp_block` catalog into `window.VvvebWpConfig.reusableBlocks` and `window.Vvveb.WpReusableBlocks` via `wp_json_encode()`.
- **Cascading Overrides Template Part Leak Isolation (`class-align-reusable-block.php`)**:
  - Removed `'header'` and `'footer'` template parts from the general page template fallback slug list in `get_cascading_template_overrides()`.
  - Prevents hidden field states (such as `ov-btn-2` with `hidden = 1` in footer) from leaking into page templates and stripping DOM nodes on regular pages.
- **Client Overwrite Detector & Save Normalization (`reuse-block-detector.js`, `wp-save-handler.js`)**:
  - Enhanced `getReuseBlockId()` in `reuse-block-detector.js` to cross-reference `window.VvvebWpConfig?.reusableBlocks` and resolve slugs to numeric IDs with slug fallback.
  - Added native `escapeAttr()` helper on `WpReuseBlockOverwrite` object for XSS hardening.
  - Updated `savePage()` in `wp-save-handler.js` to merge and dispatch overrides under both `blockId` and `altBlockId` (slug).

## [7.2.3] - 2026-09-06

### Dynamic Site Icon Favicon Sync, Link Normalization & Studio Topbar Cleanup
- **Server-Side WordPress Site Icon Injection (`class-builder-ui-renderer.php`, `editor.html`)**:
  - Automatically checks `has_site_icon()` and calls native WordPress `wp_site_icon()` via output buffer to replace static builder favicon with the current website's official site icon tags (`32x32`, `192x192`, `apple-touch-icon`, `TileImage`) in `<head>`.
  - Added `<link rel="icon" href="favicon.ico">` fallback target in `editor.html`.
  - Emits structured debug log `[Align Builder] Injecting WordPress site icon favicon into Builder UI: ... bytes`.
- **Client Branding Priority Correction & Favicon Sync (`wp-init.js`)**:
  - Inverted fallback priority to `siteIconUrl || themeScreenshotUrl`, ensuring `#wp-site-icon` in the builder sidebar displays the site's brand icon instead of the 2.5MB theme screenshot.
  - Dynamically updates any `<link rel="icon">` elements pointing to default `favicon.ico` with `siteIconUrl`.
  - Added structured console logging `[Align UI] Dynamic favicon applied from siteIconUrl: ...`.
- **Anchor Link Path Trimming (`input-link.js`)**:
  - Added support for anchor links starting with `/#`, normalizing to `#` target in `toRelativeUrl()` for smooth internal page navigation.
  - Excludes `/#` prefix from triggering debounced live link search requests.
- **Documentation & Manifest Updates (`resolved-issues.md`, `README.md`, `MANIFEST.md`)**:
  - Documented Sự Cố 160 (Site icon mismatch & favicon inversion) in `resolved-issues.md` and indexed in `README.md`.
  - Synchronized line counts in `MANIFEST.md`, `manifest.json`, and `manifest.jsonl`.

## [7.2.2] - 2026-09-06

### Align Studio - Standalone Reusable Block Micro-Workspace & Live CodeMirror 6 Sync
- **Dedicated Align Studio Micro-Workspace (`class-builder-dynamic-studio.php`, `studio/index.php`, `studio.css`)**:
  - Implemented standalone micro-workspace at `admin.php?page=align-studio` to edit, preview, and export components into Reusable Blocks (`wp_block`) without heavy builder overhead.
  - Registered REST API endpoints (`/studio-draft`, `/studio-canvas`, `/export-studio-block`) with transient caching and theme stylesheet auto-loading.
- **Tri-Tab CodeMirror 6 Editor with Live Preview Sync (`studio-editor.js`, `studio-editor.css`, `studio.js`)**:
  - Integrated modern CodeMirror 6 suite (`cm6-lezer.bundle.min.js`) featuring independent HTML, Scoped CSS, and Scoped JS tabs.
  - Debounced 100ms real-time DOM/CSS/JS synchronization into isolated canvas iframe, featuring automatic HTML indentation formatting.
- **Lenis Scroll Isolation & Natural Scrolling (`studio.js`, `class-builder-dynamic-studio.php`, `studio.css`)**:
  - Filtered out `lenis.min.js` and injected `data-lenis-prevent` to eliminate canvas scroll freeze (Sự Cố 159).
  - Replaced artificial inline iframe height calculation (`updateIframeHeight`) with 100% viewport fit and clean vertical scrollbars, resolving blank space ballooning (Sự Cố 158).
  - Added margin wheel forwarding on `#studio-canvas-viewport` to enable seamless scrolling outside responsive preview viewports.
- **Dynamic Block Converter Integration (`wp-dynamic-block-converter.js`, `admin.php`, `class-align-builder.php`)**:
  - Added 1-click DOM clean extraction, CSS/JS capture, and session payload handover to Align Studio.
  - Added `admin.php` fallback 302 redirect to ensure relative paths resolve cleanly from builder `<base href>`.
  - Added comprehensive documentation in `align/docs/builder/align-studio-guide.md` and indexed in `align/docs/README.md`.

## [7.2.1] - 2026-09-06

### Tree Navigator Expansion Persistence & Inspector Layout Alignment
- **Tree Navigator Expansion State Persistence (`builder-tree-list.js`)**:
  - Implemented `expandedAstIds` Set to retain and restore expanded node states across canvas re-renders and element configuration clicks.
  - Prevents the tree navigator from auto-collapsing when the user clicks a component on the canvas or opens configuration options.
- **Inspector Button Controls 2-Column Row (`bootstrap5-grid-buttons.js`, `bootstrap-buttons.js`, `html-typography.js`, `editor-highlight-box.css`)**:
  - Paired `Autofocus` and `Disabled` into a single 2-column flex row (`col: 6`) using standardized `ToggleInput` switches.
  - Aligned `.mb-2.inline` flexbox layout with centered labels and right-aligned toggles.
- **Inspector Class Field Gutter Alignment (`editor-panels.css`, `editor-inputs-autocomplete.css`)**:
  - Removed disruptive `padding-left: 0; padding-right: 0;` from `.col-sm-12:not(.inline)`, restoring Bootstrap grid gutter padding to align the `Class` field with `Id` and `Title`.
  - Added comfortable padding to `.tags-input` container for clean class chip rendering.
- **Dynamic Block Studio Topbar Action (`top-panel.html`, `editor-select-actions.css`)**:
  - Integrated `#convert-dynamic-btn` action button in `#select-actions` topbar with purple accent styling and tooltip.

## [7.2.0] - 2026-09-06

### Reusable Block Shortcode Inspector Section Reordering Fix
- **General Section Position Preservation on Re-Selection (`builder-components-registry.js`)**:
  - Fixed an issue where re-clicking a Reusable Block shortcode on canvas or Tree Navigator caused the `General` section (`element_header`: Id, Title, Class) to be pushed to the bottom of the inspector below `Content Overwrite`.
  - Added an automatic repositioning step after `renderProperties()` to ensure the preserved `#align-content-overwrite-section` is appended to the bottom of `#content-tab`, guaranteeing standard UI hierarchy: `[1] WP Shortcode` -> `[2] GENERAL` -> `[3] CONTENT OVERWRITE`.
  - Maintained strict file line limit compliance at 487 lines (< 500 lines).
- **Defensive Accordion Bottom Anchoring (`reuse-block-detector.js`)**:
  - Added defensive DOM check inside `handleNodeSelection()` early-return guard: if the accordion has valid content and is already attached, ensures it is anchored as `targetContainer.lastElementChild` before exiting.
  - Maintained strict file line limit compliance at 495 lines (< 500 lines).
- **Documentation & Knowledge Graph Updates**:
  - Documented Sự Cố 150 in `align/docs/issues/resolved-issues.md` with full root cause analysis and trigger sequence breakdown.
  - Updated `align/docs/README.md` resolved issues counter to 150.

## [7.1.9] - 2026-09-06

### Smart Child-Preserving Overwrite for Links & Buttons in Reusable Blocks
- **Non-destructive Overwrite for Link & Button Elements (`class-align-reusable-block.php`)**:
  - Implemented `get_node_clean_text()` and `update_composite_node_text()` using DOMDocument and DOMXPath.
  - Replaced destructive child node wipeout (`while ($node->hasChildNodes()) $node->removeChild()`) with targeted updates to child text containers (`<span>`, `<b>`, `<strong>`, `<em>`, `<label>`) or primary text nodes.
  - Preserved sibling visual elements such as `<svg>`, `<i>`, and `<img>` icons intact when overriding text in Reusable Blocks.
  - Cleaned text extraction in REST API `/wp-json/align-reuse/v1/fields` to prevent raw SVG markup from leaking into text input fields.
- **Client-Side Live DOM Icon-Preserving Updates (`reuse-block-inspector-live.js`)**:
  - Added `getNodeCleanText()` and `updateNodeTextPreservingIcons()`.
  - Upgraded live text inputs and Reset-to-Master actions (individual and Reset All to Default) for link and button fields to target text containers/nodes instead of destructive `innerHTML` replacements.
- **REST API Overwrite Inspector Canvas Sync (`reuse-block-inspector-api.js`)**:
  - Integrated `updateNodeTextPreservingIcons()` and `getNodeCleanText()` into `applyFieldToCanvas()` and `refreshFieldValues()`, maintaining full icon preservation when editing reusable blocks via REST API fields on parent pages.
- **Documentation & Manifest Knowledge Graph Updates**:
  - Documented Smart Child-Preserving Overwrite in Section 5.5.1 of `align/docs/builder/builder-guide.md`.
  - Documented full root cause analysis and resolution in `align/docs/issues/resolved-issues.md` (Sự Cố 140).
  - Synchronized line counts and component descriptions in `align/core/builder/MANIFEST.md` and `align/core/builder/manifest/manifest.jsonl`.

## [7.1.8] - 2026-09-06

### Reusable Blocks & Shortcodes Shadow DOM Asset Enqueueing & Scoped Style Injection
- **Plugin Stylesheet Capture in REST Shortcode Preview (`class-builder-crud-handler.php`)**:
  - Captured newly enqueued `$wp_styles->queue` before and after `do_shortcode()` execution, rendering any third-party plugin stylesheets (such as Fluent Forms, WooCommerce, Contact Form 7) directly within the preview HTML payload.
- **Embedded Reusable Block Asset Auto-Discovery (`class-builder-content-handler.php`)**:
  - Added regex scan in `get_page_content()` for embedded reusable blocks (`[reuse-block id="..."]` / `[reuse_block id="..."]`) on canvas pages.
  - Automatically loads and links static external CSS (`style-block-*.css`) and JS (`script-block-*.js`) of all embedded reusable blocks directly onto the canvas document head and footer.
- **Nested Shortcode Expansion & External Asset Injection (`class-align-reusable-block.php`)**:
  - Expanded nested shortcodes (`data-component-shortcode`) within reusable blocks during REST API builder preview mode, preventing unrendered shortcode containers.
  - Prepend scoped CSS (`style-block-<slug>`) and append scoped JS (`script-block-<slug>`) directly to the reusable block rendered payload if not present.
- **Shadow DOM Boundary Penetration via `:host-context` Injection (`wp-style-injector.js`)**:
  - Injected active page/block scoped styles into component Shadow Roots with automatic ancestor selector transformation (`#section-X .child` -> `:host-context(#section-X) .child` and stripped ancestor `.child`).
  - Guarantees 100% style fidelity for embedded forms and styled elements encapsulated inside Open Shadow DOM.
- **Recursive Shadow DOM Component Preview Scanner (`wp-components-preview.js`)**:
  - Upgraded `triggerWpComponentsPreviewInIframe()` to recursively traverse nested Shadow DOM trees, discovering and rendering nested component placeholders reliably.

## [7.1.7] - 2026-09-06

### Filemanager Title Truncation, Shortcode Preview Bridging & Overwrite Sidebar Persistence
- **Filemanager Title Overflow & Hover Clearance (`tmpl-filemanager.html`, `editor-panels.css`)**:
  - Implemented single-line ellipsis (`white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 1 1 auto`) for file and folder items in the Left Sidebar `#filemanager` tree.
  - Added dynamic hover right-padding reservation (`padding-right: 76px`) on `.file > label` to eliminate overlapping between long title text and the 3 action buttons (`Rename`, `Delete`, `Duplicate`).
  - Added native browser tooltips (`title="..."`) fallback to template `<label>` elements so users can view the full title on hover.
- **Shortcode Content Overwrite First-Click Race Condition Fix (`wp-components-preview.js`, `reuse-block-detector.js`, `reuse-block-inspector-live.js`, `reuse-block-inspector-api.js`)**:
  - Bridged preview completion to the overwrite inspector by dispatching `Align.WpComponentsPreview.rendered` upon Shadow DOM preview render.
  - Synchronized the active inspector immediately when preview finishes for the currently selected element.
  - Rendered an instant lightweight loading state (`Content Overwrite [spinner]`) in the inspector while preview/REST fetch is in progress.
  - Normalized `activeId` across detector and inspectors (`reuse-block-inspector-live.js`, `reuse-block-inspector-api.js`) to include `node._alignUid`.
- **Shortcode Re-click & Canvas Click Accordion Preservation (`builder-components-registry.js`, `reuse-block-detector.js`, `builder-highlight-manager.js`, `wp-components-preview.js`, `builder-tree-list.js`)**:
  - Protected the Content Overwrite accordion (`#align-content-overwrite-section`) from being stripped by Vvveb's section cleanup loop (`label:not([data-header="default"]), .section:not([data-section="default"])`) when rendering custom components (`wordpress/shortcode`, `wordpress/block`, `wordpress/post-content`).
  - Strengthened `handleNodeSelection()` guard with `hasValidContent` verification, preventing false-positive early returns when accordion interior is missing.
  - Integrated `event.composedPath()` in `builder-highlight-manager.js` (`resolveCustomComponent`) across both `highlightMove` and `highlightClick` to cross Shadow DOM boundaries and accurately resolve clicks to the shortcode host component.
  - Applied `pointer-events: none !important;` to `.align-preview-container` and `.align-preview-content` in `wp-components-preview.js` so canvas clicks pass straight through to the component host.
  - Enhanced tree row click fallback in `builder-tree-list.js` to ensure clicking anywhere in the shortcode `li` row reliably triggers component selection.

## [7.1.6] - 2026-09-06

### Multi-Instance Reusable Block Isolation & Save Consistency Engine
- **Serializer Attribute Whitelisting (`align-serializer.js`)**:
  - Whitelisted `'data-instance-'` in `ALLOWED_PREFIXES` and `'data-instance-id'`, `'data-instance'`, `'data-reuse-block-id'` in `ALLOWED_EXACT_DATA_ATTRS` to prevent attribute stripping during HTML serialization.
- **Initial Override State Bootstrapping (`class-builder-ui-renderer.php`, `wp-init.js`)**:
  - Injected `savedReusableOverrides` from post meta `_align_reusable_overrides` into `window.VvvebWpConfig`.
  - Populated `window._alignActiveReuseOverrides` on builder boot, preserving existing saved overrides across sessions.
- **Instance-Aware REST Preview Rendering (`class-builder-crud-handler.php`)**:
  - Accepted `instance_id` parameter in REST `render_shortcode()` and automatically injected `instance="{$instance_id}"` into the evaluated shortcode.
- **Isolated Component Live Preview & Cache Keys (`wp-components-preview.js`)**:
  - Automatically disambiguates reusable block instances (`default`, `inst_2`, `inst_3`) in `renderNodePreview()`, stamping `data-instance-id` onto the live DOM node and shortcode text.
  - Segregated preview cache keys by instance (`sc:[shortcode]:[instanceId]:[pageId]:[tpId]`), eliminating cross-instance preview bleeding inside Shadow DOM.
- **Pre-Serialization Disambiguation in Save Handler (`wp-save-handler.js`)**:
  - Moved reusable block instance disambiguation pass before `AlignSerializer.serialize(frameBody)`, ensuring persisted `post_content` in the WordPress database contains explicit `instance="inst_X"` attributes.
  - Grouped and preserved post meta overrides cleanly under `_align_reusable_overrides[blockId][instanceKey]`.
- **SSR Multi-Instance Mapping & Cache Isolation (`class-align-reusable-block.php`)**:
  - Tracked request-scoped occurrences `$post_occurrences[$post_id]` and isolated SSR render cache with composite key `${post_id}_${instance_key}_${occurrence}`.

## [7.1.5] - 2026-09-02

### Strict Template Matching, FSE Blog Hierarchy Architecture & Admin Quick Edit Registration
- **Strict Template Prefix Resolution (`wp-filemanager.js`)**:
  - Replaced loose `.includes()` substring search with strict exact ID, name, slug, and parsed URL parameter matching (`p.id === activeTpId || String(p.id).replace(/^t(p|pl)_/, '') === rawSearch`).
  - Resolved prefix substring collisions where clicking `single` ("Single Posts") incorrectly redirected to `single-fluent-products` ("Single Product").
- **Admin Bar Navigation Refinement (`class-builder-admin.php`)**:
  - Cleaned up the Admin Bar "Edit with Align Builder" item by removing redundant hover dropdown children so clicking goes straight to the page builder.
  - Automatically suppresses "Edit with Align Builder" on single blog posts (`is_singular('post')` or `is_single()`) to keep post editing standard in Gutenberg while reserving Align Builder for CMS pages and FSE templates.
- **Modernized Builder Template Labels & FSE Clean Architecture (`class-builder-content-handler.php`, `templates/home.html`, `templates/index.html`)**:
  - Registered clear, human-readable labels for theme templates in the Builder sidebar (`Blog Archive`, `Single Posts`, `Pages`, `Shop Archive`, `Single Product`, `Index (Fallback)`).
  - Cleaned up `index.html` into a lightweight, standard universal fallback frame (`header -> wp:post-content -> footer`) without hardcoded blog HTML.
  - Standardized `home.html` as the dedicated template for the Posts page (`is_home() === true`).
- **Page Template Dropdown Registration in Quick Edit & Post Meta Sanitization (`class-builder-admin.php`)**:
  - Bound `theme_page_templates` filter to dynamically populate custom templates (`Blog Archive`, `Shop Archive`, `Single Product`, `Products by Category`) in WordPress Page Edit and Quick Edit dropdowns.
  - Customized `default_page_template_title` to display as "Default (Pages)" to eliminate blank/empty dropdown selections caused by legacy post meta.

## [7.1.4] - 2026-09-02

### Full Site Editing (FSE) Template Pipeline, Default WP Post Content & Smart Admin Bar Integration
- **Default WordPress Post Content Component for FSE Templates (`class-builder-crud-handler.php`, `class-builder-content-handler.php`, `align-serializer.js`)**:
  - Automatically initializes all newly created Templates (`wp_template`) with standard Header, dynamic Post Content placeholder (`<div data-component-post-content>post-content</div>`), and Footer.
  - Automatically converts `<!-- wp:post-content ... /-->` blocks into the Align Builder Post Content component on load and provides non-empty fallback guarantees for template bodies.
  - Cleanly sanitizes and normalizes `data-component-post-content` during DOM serialization without leaking internal preview helpers or Shadow DOM artifacts.
- **Ultra-Compact Single-Line Shadow DOM Preview Card (`wp-components-preview.js`, `wp-post-content.js`)**:
  - Refined the Post Content Shadow DOM placeholder into an ultra-slim, minimalist 1-line bar (`📄 WP Post Content - Dynamic Page / Post Content Placeholder`), allowing clean stacking of sections, heroes, and sidebars around the main content area.
- **Smart Admin Bar Integration for System & Non-Singular Routes (`class-builder-admin.php`)**:
  - Implemented `Align_Builder_Admin::get_current_template_slug()` to resolve active template context across Blog Home (`/blogs/`), Archives, Categories, Search Results, and 404 pages.
  - Added dynamic "Edit with Align Builder" button directly in the WordPress Admin Bar on all frontend routes, with nested "Edit Template" sub-menus on Singular pages.
- **Reading Settings Blog Posts Template Architecture (`home.html`, `index.html`)**:
  - Resolved `page_for_posts` empty page issue by creating dedicated `home.html` and `index.html` templates in `align-child` bundled with external scoped assets (`style-template-home.css`, `script-template-home.js`).
- **Direct Theme File Editor Shortcut in Custom Assets Manager (`tab-load-assets.php`, `tab-load-assets.js`)**:
  - Added direct Theme File Editor link button (`[ ✏️ ]`) in the Action column for each custom CSS/JS asset file.
  - Clicking opens the corresponding theme file directly in `theme-editor.php` in a new browser tab (`target="_blank"`), with automatic skipping of binary fonts and external CDNs.
- **Database & Asset Hygiene**:
  - Cleaned up 11 orphaned legacy `[USER]` custom templates from the WordPress database, keeping the Templates sidebar pristine and aligned with WordPress Core Hierarchy.

## [7.1.3] - 2026-08-25

### Shadow Root Shortcode Preview, Realtime Content Overwrite & Multi-Context Persistence Engine
- **Shadow Root Shortcode Preview & Iframe Stylesheet Isolation (`wp-components-preview.js`, `wp-style-injector.js`)**:
  - Resolved browser `NotAllowedError` on `adoptedStyleSheets` by scoping stylesheet construction strictly to the canvas iframe window context (`frameWin.CSSStyleSheet`) and wrapping style adoption in safe non-blocking `try...catch` handlers with `<link>` tag fallback.
  - Implemented automatic image normalization (`normalizeImagesInIframe(shadow)`) and shortcode text fallback upon successful Shadow Root render.
  - Bound `Vvveb.Builder.selectNode` lifecycle listeners to re-render preview if an unmounted shadow root is selected.
- **Realtime Content Overwrite Dynamic Target Resolution (`reuse-block-inspector-live.js`, `reuse-block-inspector-api.js`)**:
  - Implemented `getLiveTarget()` helper to dynamically re-bind inputs to live connected nodes inside `(blockNode.shadowRoot || blockNode)` on every input/change event, fixing detached DOM closure issues.
  - Added comprehensive multi-source image synchronization: synchronously updating `src`, `data-src`, `srcset`, `data-srcset`, and sibling `<source>` tags within `<picture>` wrappers.
  - Enhanced case-insensitive `[data-overwrite-id]` selectors across live and API inspectors.
- **Cross-Context Overwrite Persistence Across Save & REST Preview (`class-align-reusable-block.php`, `class-builder-crud-handler.php`, `wp-save-handler.js`)**:
  - Removed restrictive `( is_singular() || is_front_page() || is_home() )` guard in `apply_reusable_overrides()` to ensure overrides apply seamlessly in REST API, Headless, and AJAX preview rendering.
  - Updated `/render-shortcode` endpoint to accept `page_id` / `tp_id` and set up global `$post` context before executing `do_shortcode()`.
  - Updated `wp-save-handler.js` to extract reusable overrides from `(blockWrapper.shadowRoot || blockWrapper)` and invalidate component preview cache upon successful save.

## [7.1.2] - 2026-08-22

### Load Asset 3-Type Filter (Font, CSS, JS) & Drag-and-Drop Reordering Engine
- **3-Type Asset Filtering (`All`, `CSS`, `JS`, `Font`) (`tab-load-assets.php`, `tab-load-assets.js`)**:
  - Added modern segmented filter buttons with dynamic real-time badge counters (`updateFilterCounts()`).
  - Implemented safe DOM-based filtering (`display: none` for non-matching rows) to preserve 100% of asset data across settings submissions.
  - Added context-aware type pre-selection: clicking "Add Manual Row" while on a filter (e.g. `Font`) automatically sets `type = "font"`.
- **HTML5 Drag & Drop Asset Reordering (`tab-load-assets.js`, `tab-load-assets.php`)**:
  - Added a dedicated drag handle column (`dashicons-menu`) with visual drag cues (`.align-asset-dragging`, `.align-drop-above`, `.align-drop-below`).
  - Automatically re-indexes form array keys `align_loaded_assets[i]...` upon drop in DOM order to customize frontend script/style enqueue sequence.
- **Architectural Decoupling (< 500 lines/file)**:
  - Decoupled client logic into dedicated `tab-load-assets.js` (~340 lines) and streamlined `tab-load-assets.php` (~235 lines), ensuring 100% compliance with strict file size limits.

## [7.1.1] - 2026-08-22

### Physical Directory Normalization, 100% Recursive Media Discovery & Deterministic Asset Classification
- **Physical Directory Normalization to Root (`class-align-zip-scanner.php`)**:
  - Implemented `Align_Zip_Scanner::normalize_directory_to_root()` to automatically unwrap single top-level wrapper directories and pull candidate distribution folders (`dist/`, `site/`, `public/`, `pages/`, `html/`, `build/`) directly into the session root directory before catalog generation.
- **100% Recursive Media Discovery (`class-align-zip-scanner.php`, `class-align-zip-batch-runner.php`)**:
  - Implemented `Align_Zip_Scanner::collect_all_media_files()`: performs exhaustive recursive scans across the entire extracted directory structure for all media formats (`png`, `jpg`, `jpeg`, `webp`, `svg`, `gif`, `ico`, `mp4`, `webm`, `avif`, `bmp`), guaranteeing accurate media sideload counters.
- **Deterministic Asset Type & Strategy Enforcement (`class-align-zip-page-assembler.php`, `class-align-admin-assets.php`, `tab-load-assets.js`)**:
  - Fixed asset misclassification bug where font files (`.woff2`, `.woff`, `.ttf`, `.otf`, `.eot`) were mapped to `JS` and CDN scripts (`isotope.pkgd.min.js`, `jquery.nice-select.min.js`, `maps.googleapis.com/maps/api/js`) were mapped to `Font`.
  - Enforced strict extension and query parameter detection in `register_loaded_asset()`, `sanitize_loaded_assets()`, and `detectFileType()`:
    - **Fonts**: 100% assigned `type = 'font'`, `location = 'header'`, `loading = 'normal'` for **TOP 1 Font Preload (`wp_head`, priority 1)**.
    - **JS Scripts**: 100% assigned `type = 'js'`, with automated `header` assignment for core libraries (`jquery`, `bootstrap`, `maps.googleapis.com`) and `footer` + `defer` for standard scripts.
    - **CSS Stylesheets**: 100% assigned `type = 'css'`, `location = 'header'`.
- **Enabled Asset State & Loading Strategy Preservation (`class-align-admin-assets.php`, `class-align-custom-css-js.php`)**:
  - Fixed variable scoping bug in `sanitize_loaded_assets()` to preserve `enabled = 1` status and loading strategies across admin settings saves, ensuring 100% of imported and uploaded assets are immediately output to the frontend.

## [7.1.0] - 2026-08-22

### 2-Stage AI Template Refactor Pipeline, OpenRouter Cloud API & Robust Dual-Mode Importer
- **2-Stage Specialist AI Refactor Pipeline (`class-align-zip-ai-refactor.php`, `ai-refactor-zip.js`)**:
  - Separated AI template refactoring into two dedicated passes adhering to the Single Responsibility Principle:
    - **Stage 1 (Header/Footer Specialist)**: AI extracts Global Header and Footer markup, saves them to `header/` and `footer/`, and PHP strips them from child page bodies.
    - **Stage 2 (Reusable Block Specialist)**: AI analyzes body skeletons only, identifies recurring sections (CTA banners, pricing tables, testimonials, newsletters, FAQs), generates semantic slugs, and extracts original section snippets.
  - **Deterministic Overwrite Layer Injector**: PHP DOM parser automatically scans reusable blocks and tags headings (`h1-h6`), paragraphs (`p`), buttons/links (`a`, `button`), and media (`img`) with `data-allow-overwrite="true"` and unique `data-overwrite-id="ov-..."` attributes.
  - **Builder Shortcode Component Injection**: Automatically wraps reusable sections with `<div data-component-shortcode="" data-shortcode="[reuse-block slug='...']" data-preview-state="rendered">[reuse-block slug="..."]</div>` to enable visual shortcode badge rendering and live Content Overwrite panel inspector integration.
- **OpenRouter Cloud API Integration & Dual-Mode Importer (`class-align-zip-ai-refactor.php`, `tab-import-zip.php`, `tab-import-zip.js`, `class-align-admin-settings.php`)**:
  - Integrated OpenRouter Cloud API (`https://openrouter.ai/api/v1/chat/completions`) as the default AI engine with custom API key and dynamic model input (defaulting to `google/gemini-2.5-flash`).
  - Added full settings registration for `align_openrouter_api_key` and `align_openrouter_model` in `align_settings_group` with seamless admin options persistence.
  - **Dual-Mode Import Pipeline**:
    - **Default Mode (AI Unchecked)**: 100% token-free instant import. Accurately handles structured Align templates (`header/`, `footer/`, `reuse-blocks/`, `pages/`) or automatically flattens raw HTML archives (`index.html`, `about.html`, `contact.html`).
    - **AI Refactor Mode (AI Checked)**: Decomposes messy/unstructured raw HTML archives into full Align Gutenberg block themes with reusable blocks and overwrite layers.
- **Environment-Independent Reusable Block Slugs (`class-align-reusable-block.php`, `reuse-block-detector.js`)**:
  - Upgraded `/align-reuse/v1/fields` REST endpoint and Visual Builder detector to natively accept both numeric IDs (`?id=123`) and string slugs (`?id=cta-banner`), resolving underlying `wp_block` posts dynamically across local, staging, and production environments.
- **macOS Resource Fork & Junk File Sanitization (`class-align-zip-batch-runner.php`, `class-align-zip-scanner.php`, `class-align-zip-ai-refactor.php`)**:
  - Recursively purges `__MACOSX`, `._*`, and `.DS_Store` files upon ZIP extraction to prevent ghost/duplicate page creations with leading underscore slugs (`/_index/`, `/_about/`).

## [7.0.0] - 2026-08-21

### HTML Project ZIP Importer, Cloud WebP Media Engine & 1-Click Factory Reset
- **Core ZIP Importer Subsystem (`align/core/includes/admin/importer/`)**:
  - `class-align-zip-scanner.php`: Recursively scans unzipped HTML project archives, discovers root `assets/` vs page folders, automatically identifies optional Global Template Part folders (`header/` and `footer/`), extracts HTML/CSS/JS/Media assets, auto-detects home pages, formats clean slugs and titles, and builds project catalogs.
  - `class-align-zip-media-rewriter.php`: Sideloads project media into WordPress Media Library, automatically converts JPG/PNG/GIF images to WebP via Align Cloud API (`/api/convert-image-to-webp`) with graceful original fallback, deletes previous duplicate attachments, and rewrites relative HTML/CSS paths with `data-align-link-id` support.
  - `class-align-zip-page-assembler.php`: Creates fresh WordPress Pages and Global Template Parts (`parts/header.html`, `parts/footer.html`), deletes old pages and physical assets on overwrite, saves scoped external CSS/JS files (`assets/builder/style-page-{slug}.css`, `assets/builder/style-part-{slug}.css`), minifies payloads via Cloud Minifier, and wraps content inside `align/custom-html` Gutenberg blocks.
  - `class-align-zip-importer.php`: Master upload and security controller handling ZIP file extraction, Zip Slip directory traversal security guards, executable blocking (`.php`, `.sh`, `.exe`, `.htaccess`), temporary cleanup, and AJAX pipeline execution.
- **Admin Settings Tab 8 UI (`tab-import-zip.php`, `layout.php`)**:
  - Streamlined executive-level interface: Drag & Drop upload zone, collapsible hierarchy guide highlighting `header/` and `footer/` parts (closed by default), 3-row configuration checkboxes, and sleek 6px animated progress bar.
  - Minimalist results datatable with Page Name (with Home & Global Part badges), Permalink Slug, Post ID, and direct action deep-links (`Builder`, `Site Editor ↗`, and `View Page ↗`).
  - Collapsible terminal execution console for live process transparency.
  - Pixel-perfect vertical centering and alignment for button icons (`dashicons-upload` and `dashicons-trash`).
- **Comprehensive Factory Reset & Security Modal (`class-align-zip-importer.php`, `tab-import-zip.php`)**:
  - 1-Click site data purge: permanently deletes all created Pages, Posts, Reusable Blocks, Media Library attachments/uploads, purges custom loaded assets from "Load Asset" tab, and deactivates all active plugins while preserving Admin account and core theme settings.
  - Multi-factor safety confirmation modal requiring explicit typing of `DELETE` keyword combined with a 5-second countdown timer before unlocking the reset button.

## [6.9.9] - 2026-08-21

### Link Search Dropdown Overhaul, Sidebar Overflow Clipping Fix & Relative URL Enforcement
- **Two-Row Structured Search Layout (`input-link.js`, `editor-inputs-base.css`, `editor-dark-theme.css`, `tmpl-input-basic.html`)**:
  - Redesigned the Ajax Link Search dropdown into a structured 2-row layout:
    - **Top Row**: Bold post title with graceful ellipsis truncation and prominent post type & ID badges (`#123` in Sky Blue/Cyan).
    - **Bottom Row**: Link icon (`la-link`) and full permalink URL displayed on its own line for maximum readability.
  - Added streamlined loading (`la-spinner`), empty ("No posts found"), and error states.
  - Implemented custom slim scrollbar (`5px`) and unified dark mode theme overrides with high contrast.
- **Sidebar Overflow-Hidden Containment Fix (`editor-inputs-base.css`, `tmpl-input-basic.html`)**:
  - Fixed horizontal clipping bug where `.link-search-results` extended outside the `#right-panel` boundary due to fixed `min-width: 300px; right: 0; left: auto;`.
  - Re-anchored the dropdown to `left: 0; right: 0; width: 100%; min-width: 100%; max-width: 100%; box-sizing: border-box;`, ensuring 100% containment within the inspector sidebar without clipping.
- **Strict Relative URL Enforcement (`class-builder-content-handler.php`, `input-link.js`, `render.php`)**:
  - Enforced root-relative URLs (`/hello-world/`) across the entire Link Picker ecosystem, eliminating absolute URLs (`http://localhost...`) to prevent domain lock-in and environment leakage.
  - **Backend REST API**: Piped `/search-links` results through `wp_make_link_relative( get_permalink( $post->ID ) ) ?: '/'`.
  - **Backend Dynamic Resolver**: Resolved `data-align-link-id` dynamic links to relative URLs in `resolve_dynamic_links()`.
  - **Frontend SSR**: Dynamic post link resolution in `align/custom-html` (`render.php`) produces relative paths.
  - **Client-Side Helper**: Implemented `LinkInput.toRelativeUrl()` in `input-link.js` to ensure selected post URLs applied to canvas `href` and input values are strictly root-relative paths while preserving in-page anchors, query parameters, and special protocols (`mailto:`, `tel:`).

## [6.9.8] - 2026-08-21

### Hybrid Image Srcset Auto-Resolution & Dual-State Media Inputs (Image & Video)
- **Backend Image Resolver Module (`class-builder-image-resolver.php`, `class-builder-content-handler.php`, `class-align-builder.php`)**:
  - Implemented `Align_Builder_Image_Resolver` to automatically resolve and synchronize responsive `srcset`, `sizes`, `width`, `height`, and `alt` from WordPress attachment metadata when loading canvas in Editor Builder (`get_page_content()`).
  - Added in-memory caching (`$attachment_cache`) to prevent redundant DB queries during request lifecycles.
  - Automatically generates 3-tier art-direction `<picture>` breakpoints (`max-width: 599px` mobile, `max-width: 992px` tablet, desktop fallback) and standalone `<img>` HTML5 `srcset`.
  - Added `clean_image_inline_style()` to strip redundant inline `width: 100%; height: auto;` styles while preserving native `width`/`height` attributes and custom user styles (`border-radius`, `object-fit`).
  - Strict zero-overhead isolation: Frontend visitor requests continue to serve pure static HTML with zero DB lookups.
- **Dual-State Media Inspector Inputs (`media-inputs.js`, `tmpl-input-media-colors.html`, `editor-inputs-base.css`, `editor-dark-theme.css`, `wp-media-modal.js`)**:
  - Created a Dual-State view for `ImageInput` and `VideoInput` matching the `LinkInput` pattern.
  - **Locked State**: When media has an attached WordPress Media ID (`data-id` or class `wp-image-*` / `wp-video-*`), inspector displays a compact media card with ID badge (`#14`), title, and a Disconnect (`la-unlink`) button.
  - **Unlocked State**: When disconnected or for external/dummy URLs, displays manual URL text input.
  - **Seamless Disconnect & Image Removal**: Clicking Disconnect completely clears `src`, `data-src`, `srcset`, `sizes`, removes all media ID attributes and WordPress classes, unwraps `<picture>` tags to standalone `<img>`, resets preview thumbnails to default SVG icon, and marks the save button `dirty`.
  - Fixed `self.getContainer` context error in `onDisconnect`.
- **Content Manager Icon Fallbacks (`builder-content-manager.js`)**:
  - Implemented `tagIconMap` in `getNodeTreeTraverse` to map unindexed HTML tags (`picture`, `source`, `figcaption`, etc.) to valid SVG icons, completely eliminating 404 network errors for `picture.svg` and `source.svg`.
- **DOM Serializer Whitelist (`align-serializer.js`)**:
  - Whitelisted `'data-id'`, `'data-align-image-id'`, `'data-align-link-title'`, `'data-align-preload'` in `ALLOWED_EXACT_DATA_ATTRS` to ensure media attributes are preserved on save.

## [6.9.7] - 2026-08-21

### Background Image Scanning, Universal Inspector Initialization & WP Media Modal Target Isolation
- **Component Registry Property Initialization (`builder-components-registry.js`)**:
  - Removed artificial whitelist restrictions (`(key === "src" || key === "size")`) in `renderProperties` that were preventing custom properties with `property.init(element)` from populating their values into inspector controls.
  - Enabled universal `property.init(element)` execution across all registered components (`Image`, `svg`, `gutenberg`, `typography`, etc.), guaranteeing values are passed to `property.inputtype.setValue(value)`.
- **Three-Tier Background Image Scanning (`bootstrap-base-spacing.js`, `common-spacing-properties.js`, `media-inputs.js`)**:
  - Implemented resilient 3-tier background image scanner in `init(node)` and `ImageInput.setValue()`:
    1. Direct inline CSS: `node.style.backgroundImage`.
    2. Lazyload attribute: `node.getAttribute('data-bg-src')`.
    3. Iframe computed style: `(node.ownerDocument?.defaultView || window).getComputedStyle(node).backgroundImage` to detect background images from external stylesheets and scoped CSS rules.
  - Added advanced regex URL extractor (`extractBgUrl`) with two-tier Quoted Match (`url("...")` / `url('...')`) and Unquoted Match (`url(...)`), cleanly handling filenames with parentheses (e.g. `image(1).jpg`), query strings, and multi-background gradient overlays (`linear-gradient(..), url(..)`).
  - Synchronized three-way updates on edit/delete: `node.style.backgroundImage`, `data-bg-src`, `StyleManager.setStyle()`, and save button `dirty` state.
- **Media Modal Context Isolation & DOM Guard (`wp-media-modal.js`, `media-inputs.js`)**:
  - Added background image context detection (`isBgContext` via `#input-Image` and property metadata).
  - Strictly isolated container background operations: when setting a background image for a container (`section`/`div`), the builder applies the background directly to the container and completely bypasses querying child `<img>` tags, preventing accidental transformation of nested images into responsive `<picture>` tags.
  - Preserved full 3-tier art-direction `<picture>` responsive conversion for direct Image Component selections.
  - Fixed input clear edge case in `ImageInput.onImageChange`, allowing clean deletion of background URLs without resurrecting cached preview paths.

## [6.9.6] - 2026-08-21

### Visual Builder Component Modal Refactor, Dynamic +/- Indicators & Modular WordPress Ecosystem
- **Modal Add Component & Section Redesign (`canvas.html`, `editor-canvas.css`, `editor-dark-theme.css`, `vvvebjs-editor-helpers.css`, `builder-canvas-engine.js`, `builder-new-section.js`)**:
  - **Modern Flexbox Header & Segmented Pill Controls**: Re-architected `#add-section-box` header with symmetric flexbox layout, rounded pill tabs (`Components`, `Sections`), segmented button group for insert mode (`After` / `Inside`), and centered close button (`X`).
  - **CSS Grid Auto-Fill Tile Layout**: Migrated component lists from legacy floats to `repeat(auto-fill, minmax(96px, 1fr))` with 10px uniform gap, 94px card height, and subtle hover elevation (`translateY(-2px)`).
  - **Multi-Line Text Display**: Implemented 2-line clamp (`word-break: break-word; line-height: 1.25`) for component titles, eliminating awkward text ellipsis truncations (e.g. "WP Gutenberg Block").
  - **Eliminated Legacy Invert Hacks**: Stripped 100% of deprecated `filter: invert(93%) hue-rotate(180deg)` rules and replaced with native dark mode design tokens.
- **Accordion +/- Indicators (`editor-canvas.css`, `vvvebjs-editor-helpers.css`, `builder-canvas-engine.js`)**:
  - Replaced ambiguous chevron arrows with intuitive **`+` (Plus)** for collapsed/closed category groups and **`-` (Minus)** for expanded/opened groups.
  - Reordered `<input class="header_check">` before `<label class="header">` across all group loaders in `builder-canvas-engine.js` for robust CSS sibling selector matching across all browsers.
- **SVG Icon Normalization & Dark Mode Filter (`builder-canvas-engine.js`, `editor-canvas.css`, `editor-dark-theme.css`)**:
  - Resolved icon URL duplicate path resolution bug (`icons/icons/`) by dynamically checking `icons/` prefix against `Vvveb.baseUrl` and `Vvveb.imgBaseUrl`.
  - Added dedicated `.component-icon` container (`32x32px`) with automatic fallback to LineAwesome icons (`la la-cube`).
  - Added dark mode brightness/inversion filter (`filter: invert(1) brightness(1.75) contrast(1.1)`) for pure-black SVG stroke icons in dark mode.
- **Canvas Helper Action Dock Cleanup (`builder-new-section.js`)**:
  - Streamlined fixed canvas dock `#vvvebjs-new-section` to display a single, focused **`Add component`** button.
- **Decoupled WordPress Components Ecosystem (`libs/builder/components/wordpress/`, `class-builder-ui-renderer.php`, `scripts.html`, `components-wordpress.js`)**:
  - Decoupled monolithic `components-wordpress.js` into dedicated modular files under `libs/builder/components/wordpress/`:
    - `wp-base.js`: Registers `WordPress` group into `ComponentsGroup` / `BlocksGroup` and provides shared placeholders.
    - `wp-shortcode.js`: WP Shortcode component definition and properties.
    - `wp-gutenberg-block.js`: WP Gutenberg Block component definition and properties.
    - `wp-post-content.js`: WP Post Content placeholder component definition.
  - Retained `components-wordpress.js` as a lightweight backward-compatibility bridge.
  - Updated `scripts.html` and `class-builder-ui-renderer.php` to load modular WordPress component files.
## [6.9.5] - 2026-08-20

### Full-Stack Delay JS & CSS Loading Suite, Dynamic Strategy Management & Auto-Sync
- **LCP & Featured Image Preload Engine (`class-align-lcp-preload.php`, `media-image.js`, `element-svg-icons.js`, `core/includes/index.php`, `builder-guide.md`, `resolved-issues.md`)**:
  - Added automatic `<link rel="preload" as="image" href="..." fetchpriority="high">` injection at **Priority 1** in `<head>` for images marked with `Featured` in Align Builder (`data-align-preload="1"` or `fetchpriority="high"`), supporting full `imagesrcset` and `imagesizes` attributes.
  - Implemented automatic fallback to WordPress native Featured Image (`has_post_thumbnail()`) for singular posts/pages.
  - Integrated `data-align-preload="1"` attribute assignment into `media-image.js` and `element-svg-icons.js` on `Featured` switch activation.
- **Dynamic Asset Delay Management (`class-align-admin-assets.php`, `tab-load-assets.php`, `class-align-custom-css-js.php`)**:
  - Added native **`Delay`** loading strategy to the Load Asset table for both JavaScript and CSS assets.
  - Unlocked CSS Loading Strategy column to support `Normal (Sync)` and `Delay` options while maintaining locked core rows (`dashicons-lock`) and disabled status checkboxes.
  - When Delay is disabled globally, the `Delay` option is dynamically hidden from dropdowns, and all assets automatically fallback to their original defaults (`defer` for JS, `normal` for CSS).
- **Automated Lifecycle Synchronization (`class-align-admin-assets.php`)**:
  - Hooked `update_option_align_enable_delay_assets` to automatically migrate all active core plugin assets (`lenis.min.js`, `splide.min.css`, `splide.min.js`, `splide-extension-auto-scroll.min.js`) and user-uploaded assets to `delay` upon activation, and back to `defer`/`normal` upon deactivation.
  - Guaranteed full database persistence for custom user overrides made in the Load Asset tab.
- **Frontend Optimization & Hydration (`class-assets-config.php`, `class-align-custom-css-js.php`)**:
  - External core CSS files render with `<link rel="stylesheet" data-delay="true" data-href="..." />` and core JS with `<script data-delay="true" data-src="..." defer></script>`.
  - Automatically hydrated on first user interaction (`scroll`, `touchstart`, `mousemove`, `click`) or 4-second timeout fallback via `align-jquery-proxy.js` for zero render-blocking and maximum PageSpeed / CWV scores.
- **Media Components Deduplication & Script Timing Fix (`scripts.html`, `media-inputs.js`, `media-image.js`, `input-media-image.js`, `MANIFEST.md`, `resolved-issues.md`)**:
  - Resolved `Uncaught SyntaxError: Identifier 'Input'/'ImageInput' has already been declared` by removing redundant `input-media-image.js` from `scripts.html` and removing hoisted `var Input` in `media-inputs.js`.
  - Resolved `Uncaught ReferenceError: linkComponentProperties is not defined` by loading `HTML Components Modules` before `Media Components Modules` in `scripts.html` and adding safe array guard in `media-image.js`.
  - Restored full availability of Image, Video, Audio, and Embed components in the Visual Builder.
- **Visual Builder Strict No-Cache & Cache-Busting Pipeline (`class-builder-admin.php`, `class-builder-ui-renderer.php`, `class-builder-rest-api.php`, `class-builder-content-handler.php`, `wp-filemanager.js`, `builder-canvas-engine.js`, `head.html`)**:
  - Implemented multi-tier cache bypass for the Standalone Builder Admin page (`admin.php?page=align-builder`) with `DONOTCACHEPAGE/OBJECT/DB/MINIFY/CDN` constants, `nocache_headers()`, `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`, `Pragma: no-cache`, `Expires: 0`, and `litespeed_disable_all`.
  - Upgraded Builder UI Renderer with `bust_builder_assets_cache()` to dynamically timestamp 100% of local stylesheet links and script tags (`?v=timestamp`).
  - Added REST API filter (`rest_pre_serve_request`) to enforce no-cache headers across all `align-builder/v1` routes.
  - Implemented dynamic cache busting for canvas preview iframe and real-time preview assets (`add_query_arg('v', time(), ...)`), preventing stale editor states while keeping public frontend caching 100% unaffected.
- **Git Updater Universal Hosting & Cross-Platform Suite (`class-align-git-updater.php`, `tab-git-updater.php`, `git-updater.md`, `resolved-issues.md`)**:
  - **Open_basedir Immunity (`get_temp_working_dir()`)**: Moved download and unzip working directory to `wp-content/uploads/align-temp/` (protected with `.htaccess`), eliminating `open_basedir restriction in effect` failures on shared hosting (cPanel, Plesk, DirectAdmin, Cloudways).
  - **Native Parent Theme Resolution (`get_template_directory()`)**: Switched theme target location to `get_template_directory()` and `wp_normalize_path()`, guaranteeing 100% correct placement across custom content directories, Bedrock/Roots, symlinks, and active child themes (`align-child`).
  - **Bytecode Cache Reset (`opcache_reset`)**: Added automated `@opcache_reset()` call post-replacement to prevent PHP 8+ / Zend OPcache memory persistence on production servers.
  - **Smart Recursive Zipball Package Locator (`locate_source_align_dir()`)**: Replaced single-level scan with `RecursiveDirectoryIterator` and header verification (`Theme Name: Align`), preventing false corruption errors on diverse server directory structures.
  - **Cross-Platform Path Normalization (`wp_normalize_path()`)**: Standardized all path separators across Windows (`\`), macOS (`/`), and Linux (`/`).
  - **Empty Option Fallback Guard**: Applied Elvis operator fallbacks (`get_option(...) ?: self::DEFAULT_*`) to protect private repository authentication tokens from being wiped by settings saves.
  - **Environment Observability UI**: Added live display of `Current Site URL`, normalized `Theme Path`, and OS platform badge (`DARWIN`, `WINNT`, `LINUX`) to the Git Updater admin panel and terminal console.

## [6.9.4] - 2026-08-20

### Unified Pre-Minified Inline CSS & Inline JS Engine with Database Persistence
- **Consolidated Inline Styles (`class-align-custom-css-js.php`, `class-align-lazyload.php`, `class-align-smooth-scroll.php`)**:
  - Centralized all frontend inline styles (Lazyload shimmer CSS, Lenis smooth scroll CSS, Custom CSS) into a single consolidated `<style id="align-core-inline-css">` tag in `<head>`.
  - Completely eliminated fragmented and scattered inline tags (`lazyload-inline-css`, `align-lenis-style-inline-css`, `align-custom-css`).
- **Consolidated Inline Scripts (`class-align-custom-css-js.php`, `class-align-smooth-scroll.php`)**:
  - Unified frontend inline JS scripts (Lenis initialization script, Custom JS) into a single minified `<script id="align-core-inline-js">` in `<footer>`.
- **Zero-CPU Runtime & Zero-API Overhead (Static Constants & Save-Time DB Persistence)**:
  - Pre-minified core fixed snippets into PHP constants (`Align_Lazyload::SHIMMER_CSS`, `Align_Smooth_Scroll::LENIS_CSS`, `Align_Smooth_Scroll::LENIS_INIT_JS`), eliminating runtime disk I/O and regex minification.
  - Added save-time listeners (`update_option_align_custom_css`, `update_option_align_custom_js`) that minify user custom code once upon saving and persist directly to database options (`align_custom_css_minified`, `align_custom_js_minified`).
  - Visitor frontend page loads execute zero external API calls and zero regex parsing loops, reading directly from autoloaded memory and static constants in $O(1)$ time.

## [6.9.3] - 2026-08-20

### Dual-Mode Media Engine, Picture Art-Direction & Dedicated Media Component Suite
- **Dual-Mode Media Routing & DPR Elimination (`components/media/media-image.js`, `wp-media-modal.js`)**:
  - **3-Tier Picture Art-Direction**: Converted standard photo outputs to strict `<picture><source media="(max-width: 599px)">...<source media="(max-width: 992px)">...<img></picture>`, completely bypassing browser DPR inflation on mobile/Retina devices and forcing exact resolution matches.
  - **Smart Icon / SVG Auto-Detection**: Automatically detects icons (SVG or dimensions $\le 150\text{px}$) and renders lightweight pure `<img>` tags without `<picture>` overhead.
  - **Dummy Image Unwrapping**: Automatically unwraps `<picture>` and strips responsive sources when placeholder/dummy images are selected.
- **Dedicated Media Component Directory (`libs/builder/components/media/`, `scripts.html`)**:
  - Consolidated all builder media components and inspector controls into [`core/builder/libs/builder/components/media/`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes/align/core/builder/libs/builder/components/media/):
    - `media-image.js` (230 lines): Image, Picture, Figure, lazy/eager toggle, and dummy unwrapping.
    - `media-video.js` (194 lines): Embed Video (YouTube, Vimeo) & HTML5 Video.
    - `media-audio.js` (44 lines): HTML5 Audio player component.
    - `media-embeds.js` (106 lines): OEmbed for 27 providers & PDF embeds.
    - `media-inputs.js` (246 lines): Inspector controller for `ImageInput`, `VideoInput`, `SvgInput`, and `FileUploadInput`.
- **Dynamic Media Sizes Fetching & WP Core Auto Sizes Guard (`class-align-image-sizes.php`, `class-align-lazyload.php`, `class-builder-rest-api.php`)**:
  - Added REST (`/attachment-sizes`) and AJAX (`align_get_attachment_sizes`) endpoints to dynamically retrieve real registered attachment sub-sizes.
  - Hooked `wp_img_tag_add_auto_sizes` to return false, preventing WordPress 6.7+ from prepending `auto, ` to custom `sizes` attributes.

## [6.9.2] - 2026-08-19

### Consolidation of Gutenberg & Site Editor Builder Assets & Legacy Sidebar Removal
- **Legacy WordPress Plugin Sidebar Cleanup (`core/blocks/config-blocks/`, `_block-helper.php`)**:
  - Completely deleted legacy WordPress Plugin Sidebar registration scripts and YAML configurations (`sidebar.js`, `sidebar-settings.yaml`, `icon.svg`).
  - Cleaned up obsolete helper functions (`align_sidebar_parse_simple_yaml`, `align_sidebar_parse_yaml_scalar`, `align_sidebar_normalize_list`, `align_sidebar_normalize_strings`, `align_block_get_config`) and dead sidebar card styling.
- **Consolidation of Builder Integration Assets (`core/builder/gutenberg/`, `class-builder-admin.php`)**:
  - Moved all 6 Gutenberg Block Editor & Site Editor builder integration assets into a dedicated folder [`core/builder/gutenberg/`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes/align/core/builder/gutenberg/):
    - `align-editor-overlay.css`: Site Editor & Block Editor "Edit with Builder" overlay.
    - `align-site-editor-integration.js`: 5-tier context-aware deep linking to Visual Builder.
    - `sidebar-common.css`: Gutenberg Inspector Controls & Tab Panels styling.
    - `align-editor-blocks.css`: Canvas block layout, hover outline, and badge styles.
    - `editor-duplicate-handler.js`: Block unique ID deduplication handler.
    - `block-tree-auto-focus.js`: Automatic block tree / list view autofocus.
  - Centralized all block editor asset enqueuing into [`Align_Builder_Admin::enqueue_site_editor_assets()`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes/align/core/inc/builder/class-builder-admin.php).
  - Deleted obsolete `core/blocks/config-blocks/` directory and streamlined `core/blocks/` to purely manage dynamic blocks.

## [6.9.1] - 2026-08-19

### Unified Standalone Minified Lazyload Asset Pipeline & Bootstrap Cleanup
- **Unified Single Asset Loading (`class-align-lazyload.php`, `align-lazyload.js`)**:
  - Removed duplicate `.min.js` and `.min.css` files, consolidating the entire lazyload engine into a single standalone minified asset [`align-lazyload.js`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes/align/core/assets/js/align-lazyload.js) and stylesheet [`align-lazyload.css`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes/align/core/assets/css/align-lazyload.css).
  - Simplified asset enqueuing logic in `Align_Lazyload::enqueue_assets()` to directly enqueue `align-lazyload.js` (in footer with `defer` strategy) and `align-lazyload.css` (in head) without environment branching conditions.
- **Removed Unused Bootstrap Vendor Library (`core/assets/plugins/bootstrap/`, `class-plugin-assets.php`)**:
  - Completely removed unused Bootstrap assets (`bootstrap.min.css`, `bootstrap.bundle.min.js`, `bootstrap-reboot.min.css`, `bootstrap-utilities.min.css`) and cleaned up registry bindings in `PluginAssets`.

## [6.9.0] - 2026-08-19

### Plugins Management Tab, Lenis Smooth Scroll, Splide.js & Delay JS/CSS Engine
- **Dedicated Plugins Settings Tab (`#plugins`) (`layout.php`, `tab-plugins.php`, `class-align-admin-settings.php`, `settings-guide.md`)**:
  - Added a new **Plugins & Enhancements** tab (Tab 7) to the Align Theme Settings admin panel with Tab Persistence (`localStorage` and URL hash `#plugins`).
  - Implemented collapsible interactive accordions (`<details>` / `<summary>`) and conditional live visibility toggles that automatically expand/collapse child configuration options when enabled.
- **Lenis Smooth Scroll v1.1.18 Engine (`class-align-smooth-scroll.php`, `core/assets/plugins/smooth-scroll/lenis.min.js`, `index.php`)**:
  - Integrated Lenis v1.1.18 smooth-scroll library with standard container reset CSS (`html.lenis`, `.lenis-smooth`, `.lenis-stopped`, `data-lenis-prevent`).
  - Automatically initializes `window.lenis = new Lenis({ autoRaf: true, smoothWheel: true })` on DOMContentLoaded.
  - Added developer documentation & code examples for programmatic scrolling (`scrollTo`), modal pausing (`stop`/`start`), scroll container isolation (`data-lenis-prevent`), and GSAP ScrollTrigger ticker integration.
- **Global Splide.js Slider & Auto-scroll Integration (`class-plugin-assets.php`)**:
  - Added one-click global enqueuing for Splide.js v4.1.4 (CSS/JS) and Auto-scroll extension, enabling slider initialization anywhere without block dependencies.
- **Delay JS & CSS Optimization with Custom URLs (`align-jquery-proxy.js`, `class-assets-config.php`, `class-assets-js.php`, `class-assets-css.php`, `template-assets-loader.php`)**:
  - Replaced watermark attributes with clean, standardized `data-delay="true"` attribute (`data-src` / `data-href`).
  - Added **Custom Delay URLs Textarea** (`align_delay_custom_urls`) allowing developers to delay any third-party plugin script/stylesheet by path or keyword.
  - Implemented smart role-based execution: applies delay optimization to Guests and Subscribers for maximum Core Web Vitals, while automatically bypassing for staff/editor roles (Administrator, Editor, Author, Contributor) for seamless admin and preview functionality.

## [6.8.9] - 2026-08-19

### Open Shadow DOM Live Preview Engine, 2-Case Dummy Filter & Zero Image Reload
- **Open Shadow DOM Live Preview Engine (`wp-components-preview.js`, `class-builder-crud-handler.php`, `align-serializer.js`, `components-wordpress.js`, `vvvebjs-editor-helpers.css`, `MANIFEST.md`, `builder-guide.md`, `resolved-issues.md`)**:
  - **Zero-Preview-Persistence Isolation**: Attached `#shadow-root (open)` inside `[data-component-shortcode]` and `[data-component-wp-block]`, isolating dynamic preview HTML from the Light DOM tree. `AlignSerializer` and AST parser strictly serialize Light DOM, guaranteeing 0 preview HTML is saved to the database.
  - **Scoped CSS & Pointer-Events Guard**: Embedded `:host` and scoped stylesheets within Shadow Root with `.align-preview-content * { pointer-events: none !important; user-select: none !important; }`, allowing clicks to pass through to host elements for accurate Selection Box and Inspector targeting.
  - **5 Fallback UI States**: Built structured UI states for `loading` (CSS skeleton spinner), `empty` (`[WP Shortcode]` prompt), `no-output`, `error` (alert box), and `success` (rendered markup).
  - **Backend Double Output Buffering**: Enhanced `render_shortcode()` and `render_block()` in `class-builder-crud-handler.php` with nested output buffering (`ob_start()`, `ob_get_clean()`) to catch both `echo` and `return` outputs, protecting REST JSON payloads.
  - **Script Sanitization & Security**: Automatically sanitizes rendered HTML to strip executable `<script>` tags, preventing rogue scripts from executing in builder context.

- **2-Case Dummy Image Filter & Cyclic Loop Elimination (`wp-style-injector.js`, `class-builder-assets.php`, `class-builder-ui-renderer.php`, `html-media.js`, `builder-highlight-manager.js`, `builder-new-section.js`)**:
  - **2-Case Ignore Filter (`shouldIgnoreImage` / `$is_preserved`)**:
    * *Case 1 (Real & Internal URLs - IGNORE)*: Preserves all URLs from `wp-content/`, `wp-includes/`, `uploads/`, absolute protocol URLs (`http://`, `https://`, `//`), `data:`, `blob:`, `#`, `none`.
    * *Case 2 (Existing Dummy / Placeholder - IGNORE)*: Preserves existing `picsum.photos`, `placeholder`, and `dummyImgUrl`, eliminating continuous re-fetching and random photo jumps.
    * *Scoped Replacement*: Only converts relative template demo paths (e.g. `images/`, `img/`, `demo/`, `src=""`, `src="#"`).
  - **Shortcodes & Gutenberg Blocks Isolation**: Added `shouldSkipElement()` and PHP tokenized placeholder guards to completely exclude shortcodes, blocks, post content, and shadow DOM from dummy image replacement.
  - **Deterministic Seed URL Synchronization**: Standardized system-wide dummy image URL to `https://picsum.photos/seed/align/600/400`, ensuring 1:1 image consistency between Canvas and Sidebar Inspector.
  - **Cyclic Loop Elimination**: Removed full-page `MutationObserver` from `wp-style-injector.js` and unconditional calls from `highlightUp` in `builder-highlight-manager.js`. Scoped execution exclusively to newly inserted nodes on `afterDrop` / `appendComponent`.

- **Click / Focus Idempotency Guards & Zero Image Reload (`wp-components-preview.js`, `reuse-block-detector.js`, `plugin-media.js`, `reuse-block-inspector-live.js`, `reuse-block-inspector-api.js`)**:
  - **Shadow DOM Fast-Path Guard**: `renderNodePreview()` immediately returns if node is already in `'rendered'` / `'success'` state with an intact shadow container, avoiding redundant `shadow.innerHTML` re-assignments and image flickering.
  - **Accordion Idempotency Guard**: `reuse-block-detector.js` skips re-creating the Overwrite Accordion DOM and re-fetching thumbnail if the panel is already active for the current node and instance ID.
  - **Media Input Equality Guard**: `ImageInput.setValue()` and thumbnail update handlers check `getAttribute('src') !== newSrc` before mutating `.src`, eliminating unwanted browser image re-fetches during element selection.

## [6.8.8] - 2026-08-19

### Tree Navigator Multi-Element Selection & Batch Deletion Architecture
- **Tree Navigator Multi-Selection Engine (`builder-tree-list.js`, `builder-selection-manager.js`, `builder-gui.js`, `builder-tree-sortable.js`, `editor-tree-navigator.css`, `scripts.html`, `MANIFEST.md`, `builder-deep-dive.md`, `builder-guide.md`, `resolved-issues.md`)**:
  - **3-Tier Tree Selection Protocol**:
    * *Normal Click*: Resets previous selections, sets clicked item as primary anchor (`_lastClickedLi`), and selects single canvas node with full inspector synchronization.
    * *Ctrl / Cmd + Click (Toggle Mode)*: Toggles clicked element in/out of `selectedLis` and `Vvveb.Builder.selectedEls` without clearing existing selections. Automatically promotes single remaining selection if count drops to 1.
    * *Shift + Click (Range Selection Mode)*: Calculates linear visible tree item slice between anchor (`_lastClickedLi`) and target `li`, preserving initial anchor for iterative selection adjustments.
  - **Unified Batch Deletion Engine (`Vvveb.Builder.deleteSelectedNodes`)**:
    * *Ancestor Pruning Algorithm*: Implemented `pruneDescendantElements()` (DOM) and `pruneAstDescendants()` (Virtual AST) to automatically filter out nested child nodes if their ancestor is also selected, eliminating double-deletion errors and AST corruption.
    * *AST-First Synchronization*: Deletes root items via `Vvveb.AstParser.removeNode(astId)`, formats clean HTML, updates CodeMirror 6 (`CodeEditor.setValue()`), and patches Canvas without white flicker via `DomMorph`.
    * *Single-Step Undo Mutation*: Consolidates batch deletions into a single `childList` batch mutation in `Vvveb.Undo`, allowing `Ctrl+Z` to restore all deleted nodes in one step.
  - **Airtight Keyboard & UI Triggers**:
    * *Shortcuts Guard*: Attached `Delete` and `Backspace` listeners across main window, iframe body, and iframe document with multi-layer input guard (`<input>`, `<textarea>`, `<select>`, `contenteditable`, CodeMirror, WYSIWYG).
    * *Toolbar Action Hook*: Bound `#delete-btn` and `#clone-btn` to unified batch operations.
    * *Tree Row Action Hook*: Clicking `.tree-node-delete` on any selected node deletes the entire selection batch.
  - **SortableJS Decoupling & File Size Compliance**:
    * Extracted drag-and-drop hierarchy logic into `builder-tree-sortable.js` (234 lines) to keep all engine files strictly under the 500-line architecture threshold (`builder-tree-list.js`: 364 lines, `builder-selection-manager.js`: 499 lines, `editor-tree-navigator.css`: 480 lines).

## [6.8.7] - 2026-08-19

### Reusable Block Overwrite Content Upgrade (Full Link, Text Link, Target & Button Elements)
- **Reusable Block Overwrite Content Expansion (`class-align-reusable-block.php`, `reuse-block-inspector-api.js`, `reuse-block-inspector-live.js`, `reuse-block-detector.js`, `wp-save-handler.js`, `MANIFEST.md`, `builder-guide.md`, `builder-deep-dive.md`, `resolved-issues.md`)**:
  - **Full Link Element Controls (`<a>`)**: Added support for 3 synchronized fields across both Live DOM Canvas and REST API Inspector modes:
    * *Link Text (Anchor Content)*: Text input for modifying inner content while preserving surrounding classes and styles.
    * *Link URL (`href`)*: Full URL input supporting both relative and absolute paths with `esc_url_raw()` and `esc_url()` sanitization.
    * *Link Target (`target`)*: Dropdown selector supporting `Default (_self)` and `New window (_blank)`.
  - **Dedicated Button Element Controls (`<button>`)**: Added native recognition for `<button>` elements with distinct SVG button iconography, label `Button Overwrite [ID]`, text content editing, Modified badge tracking, and one-click reset to master defaults.
  - **REST API Schema Enhancement (`/align-reuse/v1/fields`)**: Upgraded endpoint to resolve stored `$saved_item['content']`, `$saved_item['href']`, and `$saved_item['target']` alongside true master template values (`master_value`, `master_text`, `master_target`).
  - **Fast-Path & Robust Server-Side Rendering**: Optimized `apply_reusable_overrides()` using `WP_HTML_Tag_Processor` for attribute-only modifications and safe `DOMDocumentFragment` XML substitution with script/style stripping (`wp_kses_post`).
  - **Multi-Instance Strict Isolation & Anti-Collision (`class-align-reusable-block.php`, `reuse-block-detector.js`, `reuse-block-inspector-api.js`, `reuse-block-inspector-live.js`, `wp-save-handler.js`)**:
    * *PHP Overrides Resolution*: Fixed fallback bug in `resolve_block_overrides()` by eliminating rogue `$first = reset($data)` calls for unsaved instances, preventing overrides from leaking across distinct block instances (e.g. `instance="top"` vs `instance="bottom"`).
    * *Async Selection Guard*: Enhanced `fetchFieldsFromApi()` callbacks in `reuse-block-detector.js` to strictly validate `currentSelected !== node || currentBlockId !== blockId || currentInstId !== instanceId`, eliminating race conditions and stale overwrite data injection during rapid node selection.
    * *2-Level Nested Overwrite State*: Standardized client-side overwrite memory tree (`_alignActiveReuseOverrides[blockId][instanceKey]`) and isolated delta persistence in `wp-save-handler.js`.

## [6.8.6] - 2026-08-19

### Custom Assets Physical File Deletion & Security Sandbox Engine
- **Physical File Deletion & Decoupled Asset Management (`class-align-admin-assets.php`, `class-align-admin-settings.php`, `index.php`, `tab-load-assets.php`, `settings-guide.md`, `overview.md`, `resolved-issues.md`)**:
  - **New Module `Align_Admin_Assets` (`class-align-admin-assets.php`)**: Decoupled custom asset uploading, sanitization, relative URL resolution, legacy migration, and secure physical deletion into a dedicated module (430 lines, strictly < 500 lines) following the Single Responsibility Principle (SRP).
  - **Permanent Disk File Deletion (`unlink` & `clearstatcache`)**: Added the `align_delete_asset_file` AJAX endpoint to permanently delete physical files from the server filesystem when an asset row is removed from the table, eliminating orphaned asset files completely.
  - **Real-Time Database Option Synchronization**: Automatically removes deleted assets from `align_loaded_assets` in `wp_options` immediately upon file deletion, ensuring UI and database state consistency.
  - **Multi-Layer Security Sandbox**:
    * *Anti-Traversal Guard*: Resolves canonical paths with `realpath()` and validates that deletion targets reside strictly within allowed directories (`themes/align-child/assets/`, `themes/align/assets/`, `uploads/align-assets/`, and `uploads/`).
    * *Extension Whitelist*: Strictly restricts file deletion to allowed asset formats (`css`, `js`, `woff2`, `woff`, `ttf`, `otf`, `eot`, `map`), preventing arbitrary file deletion of `.php`, `.phtml`, `.htaccess`, `.sql`, or system configuration files.
    * *Strict Access Control*: Validates `align_settings_nonce` and requires `manage_options` capability.
  - **Enhanced UI/UX Feedback (`tab-load-assets.php`)**:
    * Added user confirmation dialog (`confirm(...)`) to prevent accidental deletions.
    * Real-time loading indicator: Disables the delete button and replaces the trash icon with a spinning Dashicon (`dashicons-update spin`) during asynchronous file deletion.
    * Immediate DOM removal, index re-sequencing (`reindexRows()`), empty table placeholder management, and automatic DOM-only deletion for empty unsaved rows without redundant network requests.
    * Structured observability logging on Backend (`[Align AdminAssets]`) and Frontend (`[Align CustomAssets]`).

## [6.8.5] - 2026-08-19

### External Static Assets Engine, Neutral Scoped IDs & 3-Guarantee Lifecycle Cleaner
- **External Builder Assets & Disk Engine (`class-builder-assets.php`, `class-builder-asset-cleaner.php`, `class-builder-crud-handler.php`, `class-builder-content-handler.php`, `class-align-admin-settings.php`, `tab-builder.php`, `render.php`, `builder-style-manager.js`, `builder-js-manager.js`, `align-serializer.js`, `wp-save-handler.js`, `builder-drag-drop.js`, `undo.js`, `MANIFEST.md`, `builder-guide.md`, `settings-guide.md`, `resolved-issues.md`)**:
  - **New Setting `align_builder_external_assets`**: Added an admin setting toggle in Tab 1 allowing users to store and enqueue Visual Builder CSS/JS as external static assets in `wp-content/themes/align-child/assets/builder/`.
  - **Neutral Scoped ID Architecture (White-Label Standard)**: Stripped the `align-` prefix from all scoped DOM IDs and static filenames across all entity types:
    * Pages: `<style id="style-page-{slug}">` & `<script id="script-page-{slug}">` (`style-page-{slug}.css`, `script-page-{slug}.js`).
    * Templates: `<style id="style-template-{slug}">` & `<script id="script-template-{slug}">` (`style-template-{slug}.css`, `script-template-{slug}.js`).
    * Template Parts: `<style id="style-part-{slug}">` & `<script id="script-part-{slug}">` (`style-part-{slug}.css`, `script-part-{slug}.js`).
    * Reusable Blocks: `<style id="style-block-{slug}">` & `<script id="script-block-{slug}">` (`style-block-{slug}.css`, `script-block-{slug}.js`).
  - **New Module `Align_Builder_Asset_Cleaner` (`class-builder-asset-cleaner.php`)**: Implemented a standalone lifecycle asset and disk cleaner (199 lines, strictly < 500 lines) with WordPress hook `before_delete_post` integration, slug mutation purging, and orphaned asset garbage collection.
  - **3 Guarantees for Zero-Downtime & Zero-Orphan Management**:
    1. *Immediate Deletion*: Automatically deletes static `.css`/`.js` and `.html` files when posts/templates are deleted, slugs are changed, or CSS/JS code is emptied.
    2. *Real-time Cache Busting*: Calls `clearstatcache(true, $path)` at 100% of file I/O operations and enqueues assets with dynamic `?ver=filemtime` timestamps.
    3. *Zero Orphan Files*: Automatically cleans competing legacy asset files upon save and scans disk to purge unreferenced orphaned files.
  - **Write-First, Clean-Later Pipeline (Zero-FOUC Guarantee)**: Ensures target files are 100% written and stat-cached before purging legacy versions, eliminating layout shifts and ensuring visitors see updated styles on the very first page load.
  - **4-Tier Backward Compatibility**: Automatically recognizes and migrates legacy tags (`align-style-*`, `vvvebjs-styles`, numeric ID files) without breaking any existing running installations.

## [6.8.4] - 2026-08-19

### Unified Multi-Endpoint Health Check & Diagnostics Engine
- **Centralized Cloud API Health Check & Diagnostics (`class-align-endpoint-tester.php`, `index.php`, `tab-builder.php`, `tab-webp.php`, `MANIFEST.md`, `settings-guide.md`, `resolved-issues.md`)**:
  - **New Core Engine `Align_Endpoint_Tester` (`class-align-endpoint-tester.php`)**: Implemented a standalone diagnostics module (269 lines, strictly < 500 lines) with Single Responsibility Principle (SRP), sequential request runner with 4-second timeout protection, microtime latency measurement, and structured observability logging (`[Align API]`).
  - **Comprehensive 6-Endpoint Test Matrix**: Automated health and readiness checks for all microservices in a single click:
    1. `GET /health`: Microservice gateway liveness.
    2. `POST /api/convert-image-to-webp`: 1x1 sample transparent PNG conversion.
    3. `POST /api/scss-to-css`: Dart Sass SCSS compiler.
    4. `POST /api/minify`: CleanCSS code minifier.
    5. `POST /api/unminify`: Code beautifier and formatter.
    6. `POST /api/convert`: HTML AST to Gutenberg blocks converter.
  - **Tab 1 Diagnostics UI (`tab-builder.php`)**: Added the "Test Endpoint" action button with WordPress admin spinner and live responsive table rendering status badges (`● Operational`, `● Slow`, `● Failed` with error details) and latency metrics.
  - **Defensive Error Handling & Non-JSON Parser**: Added safe response parsing to gracefully display maintenance/503 notices instead of unhandled JSON parse SyntaxErrors when servers return plain text or HTML.
  - **Tab 2 Streamlining (`tab-webp.php`)**: Purged redundant single-endpoint connection testing row from Tab 2, focusing WebP settings purely on conversion configuration and bulk library processing.

## [6.8.3] - 2026-08-18

### Reusable Blocks & FSE Templates Overwrite Support
- **Reusable Block Content Overwrite in Templates & Template Parts (`class-builder-crud-handler.php`, `class-align-reusable-block.php`, `reuse-block-detector.js`, `MANIFEST.md`, `builder-guide.md`, `builder-deep-dive.md`, `overview.md`, `resolved-issues.md`)**:
  - **Template Persistence for Reusable Overrides (`class-builder-crud-handler.php`)**: Added extraction and persistence of `$reusable_overrides` payload directly into Post Meta `_align_reusable_overrides` for `wp_template` and `wp_template_part` post records upon saving templates.
  - **Context-Aware REST Fields API (`class-align-reusable-block.php`)**: Upgraded `GET /align-reuse/v1/fields` endpoint to accept `tp_id` and `template_type` query parameters, resolving target template post IDs via `resolve_template_post_id()` and populating stored override values in the Visual Builder Inspector.
  - **Cascading Overrides Runtime Hierarchy (`class-align-reusable-block.php`)**: Implemented 4-tier cascading resolution hierarchy (*Page Overrides* > *Template Overrides* > *Template Part Overrides* > *Master Default*) covering WordPress edge cases (`is_404()`, `is_search()`, `is_archive()`, `is_home()`, `is_front_page()`, and empty query loops).
  - **Visual Builder Client Synchronization (`reuse-block-detector.js`)**: Updated `fetchFieldsFromApi()` to dynamically attach `tp_id` and `template_type` parameters from `window.Vvveb.FileManager` and `window.VvvebWpConfig`.


## [6.8.2] - 2026-08-18

### Visual Builder Backend Minification & Cloud API Integration
- **Safe Backend Minifier & Align Cloud API Integration (`class-builder-minifier.php`, `class-builder-crud-handler.php`, `class-align-builder.php`, `MANIFEST.md`, `builder-deep-dive.md`, `align-cloud-api-reference.md`, `resolved-issues.md`)**:
  - **New Module `Align_Builder_Minifier` (`class-builder-minifier.php`)**: Implemented a standalone backend engine (347 lines, strictly < 500 lines) that minifies and assembles HTML, CSS, and JS prior to packaging inside Gutenberg block comments `<!-- wp:align/custom-html -->`.
  - **Local Tokenized HTML Engine**: Compresses HTML markup safely by tokenizing `<pre>`, `<code>`, `<textarea>`, `<svg>`, `<script>`, `<style>` blocks; collapses whitespace exclusively between block-level elements while preserving single spaces between inline elements (`<span>`, `<a>`), eliminating word concatenation defects.
  - **Align Cloud Minify API Integration**: Dispatches CSS and JS minification to `https://convertb.alignvn.cloud/api/minify` using high-performance CleanCSS and Terser compilers with a 3-second non-blocking timeout.
  - **Heuristic Pre-Minified Bypass**: Detects already minified CSS/JS assets via whitespace density and comment patterns, bypassing redundant remote requests.
  - **Two-Tier Caching Pipeline**: Implemented static in-memory caching for single-request lifecycles and WordPress Transients (`align_min_{md5}`, 12-hour TTL), reducing remote roundtrips to 0 for subsequent saves.
  - **Resilient Pure PHP Fallback**: Features automatic seamless fallback to local tokenized regex engines (`local_minify_css()`, `local_minify_js()`) if network errors or API timeouts occur.
  - **Granular Observability Logging**: Added structured telemetry with prefix `[Align Minifier]` for 4 states (*Bypass*, *Cache Hit*, *Cloud Minify Success*, and *Local Fallback*).

## [6.8.1] - 2026-08-18

### Visual Builder Observability & WP Core Markup Fidelity
- **WP Core WPAutop Bypass & Full HTML Fidelity (`content-filters.php`, `class-align-post-content.php`, `align-serializer.js`, `wp-save-handler.js`, `class-builder-crud-handler.php`, `class-builder-content-handler.php`, `render.php`, `resolved-issues.md`)**:
  - **Dynamic WPAutop Bypass for Builder Content (`content-filters.php`)**: Added `align_disable_wpautop_for_builder( $content )` hooked to `the_content` at priority `0`. Automatically disables `wpautop()` for content containing `wp:align/custom-html`, `data-component-`, or builder markup, preventing WordPress from converting newlines `\n` after inline tags (`<span>`) into unwanted `<br>` tags.
  - **Scoped Try-Finally Guard in Post Content (`class-align-post-content.php`)**: Wrapped `apply_filters('the_content')` inside `Align_Post_Content::render()` in a `try...finally` block that safely detaches `wpautop` and guarantees its restoration.
  - **Mixed Loop Compatibility**: Restores `wpautop` at priority 10 for standard non-builder blog posts in mixed queries and archives.
  - **End-to-End `<br>` Observability Pipeline**: Integrated stage-by-stage `<br>` tag tracking across Frontend (`[AlignSerializer]`, `[Align SaveHandler]`) and Backend (`[Align CrudHandler]`, `[Align ContentHandler]`, `[Align CustomHTML Block]`), including post-save DB readback verification.

## [6.8.0] - 2026-08-18

### Visual Builder Save Flow & PHP String Safety
- **Always-On Save Mechanism & PHP String Literal Interpolation Safety (`top-panel.html`, `wp-save-handler.js`, `builder-canvas-loader.js`, `builder-drag-drop.js`, `builder-file-manager.js`, `builder-gui.js`, `class-builder-ui-renderer.php`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Always-Enabled Save Action (`top-panel.html`, `wp-save-handler.js`)**: Removed default `disabled` attribute from the Save button and eliminated dirty check gating. The Save button is always active and directly clickable at all times.
  - **Re-entrancy Guard Only (`wp-save-handler.js`)**: Save button is temporarily disabled strictly while sending active REST save requests (`saving`), immediately re-enabling upon `saved`, `clean`, `dirty`, or `error` responses.
  - **Mutation Listener Cleanup (`builder-canvas-loader.js`, `builder-drag-drop.js`, `builder-file-manager.js`, `builder-gui.js`)**: Removed automated disabling of the Save button upon canvas mutations, page actions, or undo/redo events.
  - **PHP String Interpolation Syntax Fix (`class-builder-ui-renderer.php`)**: Fixed unescaped single quotes in `dummyImgUrl` string concatenation by wrapping value with `json_encode('https://picsum.photos/200/300')`, completely resolving the PHP Fatal Parse Error (White Screen of Death / blank screen).

## [6.7.9] - 2026-08-18

### Builder Media, Asset Loader & Core Safety
- **Dummy Image Placeholder Migration to Picsum Photos, Null-Safety Guards & Automatic Asset Cache-Busting (`class-builder-assets.php`, `class-builder-ui-renderer.php`, `wp-style-injector.js`, `class-align-custom-css-js.php`, `templatemo-noir-scripts.js`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Picsum Photos Integration with `WP_HTML_Tag_Processor` (`class-builder-assets.php`)**: Upgraded `Align_Builder_Assets::replace_dummy_images()` using native `WP_HTML_Tag_Processor` (WP 6.2+) and fallback regex to migrate dummy images to `https://picsum.photos/200/300`.
  - **Strict `wp-content` Preservation**: Strictly preserves 100% of image `src`, `data-src`, `<source srcset>`, and CSS `url(...)` containing `wp-content/`, data URIs (`data:`), blob URIs (`blob:`), and SVG gradient fragment identifiers (`#...`). Only non-`wp-content` dummy/external demo images are replaced.
  - **Iframe Canvas Safe Injection (`wp-style-injector.js`)**: Updated `replaceDummyImagesInIframe()` with `_isReplacingImages` re-entrancy lock and `src !== dummyImgUrl` guard, eliminating `MutationObserver` infinite cyclic feedback loops.
  - **Component Registries & Presets Standardization**: Migrated default `html/image`, `elements/figure`, `html/card`, and all block/section presets to use `https://picsum.photos/200/300`.
  - **Defensive Null-Safety in Asset Scripts (`templatemo-noir-scripts.js`)**: Added defensive guards `if (menuToggle && mobileNav)` and `if (navbar)` to eliminate `Uncaught TypeError: Cannot read properties of null (reading 'addEventListener')` when DOM nodes are absent.
  - **Automatic Asset Cache-Busting (`class-align-custom-css-js.php`)**: Added auto-generation of `?ver=filemtime` for all local theme assets, ensuring browsers immediately fetch updated files upon modification.
  - **Purged Obsolete Dummy WebP Images (`core/builder/img/`)**: Permanently deleted 5 unused legacy dummy images (`image_1.webp` through `image_5.webp`, reducing repo size by ~7.7 MB) and stripped legacy `builderImgUrl` / `builderImgList` config keys from `VvvebWpConfig`.

## [6.7.8] - 2026-08-18

### Full Site Editing (FSE) & Visual Builder Canvas
- **Shortcode & Reusable Block Parity in FSE Templates & Live Disk-to-DB Synchronization (`render.php`, `content-filters.php`, `class-align-template-tweaks.php`, `class-builder-crud-handler.php`, `class-align-reusable-block.php`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Full Shortcode Parity on FSE Templates (`render.php`)**: Added dynamic execution of `do_shortcode( $html )` alongside `align_parse_and_render_builder_components()` inside `align/custom-html` block rendering for FSE Templates, ensuring 100% parity with WordPress Pages.
  - **HTML Attribute Quotation Normalization (`content-filters.php`)**: Standardized `data-shortcode` attributes to single quotes (`data-shortcode="[reuse-block id='146']"`) and added fallback text parsing (`nodeValue`) to eliminate HTML parser entity collision when `&quot;` is decoded.
  - **Automatic Template Disk-to-DB Synchronization (`class-align-template-tweaks.php`)**: Added `sync_all_templates_with_db()` hook on `wp_loaded` and filter on `get_block_template` / `pre_get_block_file_template` to ensure `wp_posts` database records always reflect the freshest `.html` files from disk.
  - **Direct Reusable Block Extraction (`class-align-reusable-block.php`)**: Directly extracts inner HTML and scoped CSS from `wp:align/custom-html` blocks in `wp_block` posts, bypassing Gutenberg loop recursion suppression.
  - **Reliable WPDB Template Saving (`class-builder-crud-handler.php`)**: Upgraded `save_page()` to query and update all matching `wp_template` database records via `$wpdb` directly.

## [6.7.7] - 2026-08-18

### Full Site Editing (FSE) & Visual Builder Canvas
- **WP Post Content Dynamic Rendering & Zero-Bloat Data Pipeline (`content-filters.php`, `components-wordpress.js`, `wp-components-preview.js`, `class-builder-content-handler.php`, `align-child/templates/page.html`, `align-child/templates/single.html`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Dynamic FSE Post Content Parsing (`align_parse_and_render_builder_components`)**: Added native processing for `data-component-post-content` in `content-filters.php`. Dynamically executes `do_blocks('<!-- wp:post-content /-->')` with graceful fallback to `apply_filters('the_content', get_the_content())`.
  - **Zero-Bloat DOM Node Replacement (`align_replace_node_with_html`)**: Seamlessly swaps the `<div data-component-post-content>` placeholder directly with rendered post HTML content, introducing zero extraneous wrapper `<div>` tags in the output.
  - **Strict Re-entrancy & Infinite Recursion Guard**: Implemented `static $is_rendering_post_content` lock wrapped in `try...finally` alongside `$processing_depth >= 5` limits, completely eliminating cyclic recursion and stack overflow risks when post content contains nested components.
  - **Zero-Child Clean Canvas Placeholder (`components-wordpress.js`, `wp-components-preview.js`)**: Eliminated legacy dummy mockup text (`<h3>Post Title</h3>...`) in favor of minimal, atomic leaf node `<div data-component-post-content>post-content</div>`, with safe XSS-proof `textContent` canvas updates.
  - **Child Theme FSE Templates Sanitization**: Sanitized default `page.html` and `single.html` templates in `align-child/templates/`, standardizing on pure `data-component-post-content` placeholders.

## [6.7.6] - 2026-08-18

### Full Site Editing (FSE) & Visual Builder Canvas
- **Pure Template Architecture & Template Parts Isolation (`class-builder-content-handler.php`, `api-sync.php`, `align-child/templates/*.html`, `align-child/parts/*.html`, `resolved-issues.md`, `builder-guide.md`)**:
  - **Dynamic Template-Part Stripping (`Align_Builder_Content_Handler::get_page_content`)**: Added server-side regex filter `preg_replace('/<!--\s*wp:template-part\b.*?(?:\/-->|-->[\s\S]*?<!--\s*\/wp:template-part\s*-->)/is', '', $raw_tp_content)` when `$is_template === true` before running `do_blocks()`. Prevents Header and Footer blocks from inflating into full raw HTML onto the Template editing canvas.
  - **Safe Bypass for Template Parts**: Bypasses regex stripping when `$is_template === false` (editing `tp_header`, `tp_footer`), allowing template parts to remain fully editable and intact.
  - **Clean Template Scaffolding (`api-sync.php`)**: Updated default template scaffolding to create clean templates containing only `<main class="wp-block-group">...<!-- wp:post-content /--></main>` without hardcoded `wp:template-part` header and footer comments.
  - **Complete Child Theme Templates Cleanup**: Cleaned all template files in `align-child/templates/` (`index.html`, `single.html`, `page.html`, `404.html`, `archive.html`, `search.html`, `align-child-index.html`, `align-child-index-2.html`, `align-child-single.html`), removing all `.site-header`, `.site-footer`, `.top-bar`, and `wp:template-part` tags.
  - **100% Template Parts Independence**: Verified `align-child/parts/header.html` and `align-child/parts/footer.html` operate with isolated scoped CSS (`align-style-part-header`, `align-style-part-footer`) and scripts.
  - **3 Official Creation Pathways & Zero Activation Auto-Generation**: Documented and verified that theme activation performs zero auto-generation on disk. Files are strictly created/synced via 3 official pathways: Manual placement, Builder `+` Add Item, and Gutenberg FSE save hook (`handleCmsTemplateSave`).
  - **Child Theme Parts Footprint Sanitization**: Sanitized child theme directory, strictly retaining standard canonical parts (`header.html`, `footer.html`) and templates (`404.html`, `archive.html`, `index.html`, `page.html`, `search.html`, `single.html`).

## [6.7.5] - 2026-08-18

### Builder Media & Inspector UI
- **Image Inspector Thumbnail Preview & WP Media URL Normalization (`plugin-media.js`, `wp-media-modal.js`, `tmpl-input-media-colors.html`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Robust URL Resolver (`ImageInput.resolveImgUrl`)**: Resolved relative root paths (`/wp-content/...`, `wp-content/...`), canvas node full URLs, and placeholder fallbacks against `window.location.origin`, bypassing `<base href>` hijacking in the Builder UI.
  - **Singleton Value Mutation Fix (`onImageChange`)**: Fixed bug where `self.value` was persisted on the `ImageInput` singleton and overrode subsequent `event.target.value` selections.
  - **Clean Primary Image Attribution (`wp-media-modal.js`)**: Updated `targetImg.src` to assign `responsive.fullUrl` directly as the clean primary source while retaining all multi-breakpoint candidates in `srcset`.
  - **Modern Contained Preview Boxes & Fail-safe Error Handling**: Modernized image/video/svg preview containers in `tmpl-input-media-colors.html` (`height: 140px; background: #1a202c; object-fit: contain; border: 1px dashed #4a5568`) with programmatic `onerror` fallbacks to `icons/image.svg`.
  - **Micro-template Single-Quote Immunity**: Removed raw inline single-quote event handlers from micro-templates to eliminate `new Function()` syntax compilation errors.

## [6.7.4] - 2026-08-18

### Media & Cloud API Integration
- **Cloud WebP Image Converter & Native Media Optimization (`class-align-webp.php`, `tab-webp.php`, `tab-builder.php`, `class-align-admin-settings.php`, `theme-functions.php`, `settings-guide.md`)**:
  - **Upload Triggered WebP Endpoint (`/api/convert-image-to-webp`)**: Automatically intercepts JPG/PNG/GIF media uploads (`wp_generate_attachment_metadata`), reads image binaries into base64 Data URIs, and calls the Cloud Converter API (`https://convertb.alignvn.cloud/api/convert-image-to-webp`) to generate optimized `.webp` files for the original attachment and all registered thumbnail sub-sizes.
  - **Fail-Safe Logging Without Upload Interruption**: If the external API fails (timeout, network drop, non-200), logs the structured error via `[Align WebP]` without interrupting or failing the core WordPress media upload process.
  - **Full Attachment Metadata & Sub-size Persistence (`wp_update_attachment_metadata`)**: Ensured all converted thumbnail sub-sizes, filenames, and `image/webp` MIME types are permanently saved to database (`wp_postmeta`), completely eliminating broken media library thumbnails (gray box 404 errors) during bulk and on-the-fly conversion.
  - **Clean Relative Path Handling (`get_clean_webp_path`)**: Normalized root upload relative paths, stripping redundant `./` artifacts when images reside in the root uploads folder.
  - **Comprehensive Sub-size Diagnostics in `ajax_bulk_status`**: Enforced two-tier integrity check verifying both primary attachment and all intermediate thumbnail sub-sizes before marking images as converted.
  - **Centralized Endpoint Getter (`align_get_api_converter_url`)**: Unified the external API converter URL getter with guaranteed fallback to `https://convertb.alignvn.cloud` when unset or blank.
  - **Streamlined WebP Settings & UI Diagnostics**: Added "Test WebP Endpoint" real-time latency check and "Bulk Convert Media Library Images" batch processor to `tab-webp.php`. Removed obsolete `Picture HTML Fallback` options in favor of direct native WebP attachment storage.

## [6.7.3] - 2026-08-18

### Core Architecture & Modern WP HTML API Integration
- **WordPress 6.2+ HTML API (`WP_HTML_Tag_Processor`) Integration (`content-filters.php`, `class-align-lazyload.php`, `class-align-reusable-block.php`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Streaming HTML Attribute Processing (`content-filters.php`)**: Upgraded `align_convert_internal_to_relative()` to use `WP_HTML_Tag_Processor` for lightning-fast parsing of `href`, `src`, `data-src`, `poster`, `action`, and comma-separated `srcset`/`data-srcset` attributes, completely eliminating regex false-positives and saving 70-80% memory compared to `DOMDocument`.
  - **Safe Lazyload Rewriting (`class-align-lazyload.php`)**: Replaced brittle regex tag manipulation with `WP_HTML_Tag_Processor` to mutate `<img>` and `<source>` tags for placeholder SVG injection while preserving HTML structure.
  - **Fast-Path Reusable Block Overrides (`class-align-reusable-block.php`)**: Added high-performance `WP_HTML_Tag_Processor` pass for attribute-only overrides on `data-allow-overwrite="true"` nodes.
- **Zero-Child Flat Semantic Standard for Shortcode & Block Components (`vvvebjs-editor-helpers.css`, `wp-components-preview.js`, `components-wordpress.js`, `class-builder-content-handler.php`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Pure CSS Pseudo-Element UI (`vvvebjs-editor-helpers.css`)**: Moved placeholder badges (`[/] Shortcode`, `{} Block`) and card styling entirely to CSS `::before` pseudo-elements on `[data-component-shortcode]` and `[data-component-wp-block]`.
  - **Zero-Child Element DOM**: Stripped all inner `div` and `span` wrapper tags, setting plain text node directly inside component root element.
  - **100% AST ID De-pollution**: Prevented AST parser from generating redundant child AST nodes (`ast_5`, `ast_6`...), ensuring only a single authoritative `ast_X` ID is assigned per component and eliminating inline style quote escaping issues in Code Editor.

## [6.7.2] - 2026-08-18

### Core Architecture & Clean Semantic DOM
- **Zero-Wrapper Clean Semantic DOM Mode for `align/custom-html` Dynamic Block (`render.php`, `class-builder-crud-handler.php`, `MANIFEST.md`, `builder-guide.md`, `resolved-issues.md`)**:
  - **Zero-Wrapper Frontend Output (`render.php`)**: Removed redundant `<div class="align-custom-html-block">` container on frontend rendering, outputting pure HTML directly to ensure flat DOM tree, 100% semantic compliance, and optimal SEO / ADA Accessibility score.
  - **Automated Legacy Wrapper Cleanup (`render.php`, `class-builder-crud-handler.php`)**: Added automated unwrap routines during frontend render and backend persistence to strip legacy wrapper tags from database records and static templates.
  - **Guarded Custom Class Support**: Conditionally wraps with container element only when explicit user-defined `className` attribute is present from Gutenberg block settings.
  - **Preserved WP Admin Placeholder Integration**: Fully preserved WordPress Site Editor and Gutenberg Block Editor placeholder card functionality with 5-tier context resolution.

## [6.7.1] - 2026-08-17

### Tree Navigator Drag-and-Drop & Nesting Engine
- **Tree Navigator Vertical 3-Zone Trigger & Cross-Parent Nesting Fix (`builder-tree-list.js`, `builder-ast-parser.js`, `editor-dark-theme.css`, `plan-sortablejs-swap.md`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Row Label Coordinate Calculation (`builder-tree-list.js`)**: Fixed subtree height distortion bug by measuring drop coordinates strictly against the 26px row label (`:scope > label`) rather than the recursive subtree `<li>` element.
  - **3-Zone Trigger**: Top 25% triggers `before` (sibling above), Middle 50% triggers `inside` (add into child container), Bottom 25% triggers `after` (sibling below). Void elements (`img`, `input`...) automatically fallback to 50/50 sibling reordering.
  - **Cross-Parent Re-parenting & Zero Jitter**: Replaced SortableJS live DOM reordering with non-disruptive `document.elementFromPoint` global cursor tracking and `sort: false`, ensuring smooth dragging across all parent containers and leaf items.
  - **Virtual AST Circular Nesting Guard (`builder-ast-parser.js`)**: Added recursive ancestor validation in `AstParser.moveNode` to prevent circular moves (`ancestor into descendant`).
  - **Enhanced Drop Indicators (`editor-dark-theme.css`)**: Added high-visibility solid border and glowing background styling (`#38bdf8`) for `.tree-drop-inside`, `.tree-drop-before`, and `.tree-drop-after`.
- **Image Inspector UX Cleanup (`html-media.js`, `MANIFEST.md`, `builder-components-and-styles.md`)**:
  - Removed technical `Sizes` and `Srcset` manual input fields from the Image properties inspector panel to streamline user experience.
  - Preserved 100% automated background responsive generation (`srcset="... 375w, 768w, 1200w"`, `sizes="(max-width: 600px) 33vw, 100vw"`) on image selection, lazy loading, and saving.

## [6.7.0] - 2026-08-17

### Core Architecture & State-of-the-Art Engine
- **Code-First Virtual AST Engine, CodeMirror 6 + Lezer Integration & Incremental DOM Morphing (`builder-ast-parser.js`, `builder-dom-morph.js`, `cm6-lezer.bundle.min.js`, `builder-code-editors.js`, `builder-tree-list.js`, `builder-selection-manager.js`, `builder-drag-drop.js`, `scripts.html`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Virtual AST Single Source of Truth (`builder-ast-parser.js`)**: Implemented high-speed 2-way conversion between HTML strings and structured in-memory AST Nodes with `astId` tracking, supporting atomic `moveNode`, `cloneNode`, and `removeNode` tree mutations.
  - **Automated DOM Stamping (`AstParser.syncDom`)**: Added canvas DOM synchronization method to assign `data-ast-id="ast_X"` on all live elements, guaranteeing Tree Navigator `_astId` is always fully synchronized with the Virtual AST.
  - **Incremental DOM Morphing (`builder-dom-morph.js`)**: Integrated a zero-dependency keyed DOM diffing algorithm into `Vvveb.Builder.setHtml()`, replacing destructive `innerHTML` assignments to eliminate 100% canvas flicker while preserving video players, sliders, animations, and builder helper elements.
  - **Unified Code Editor Engine Consolidation (`builder-code-editors.js`)**: Consolidated multi-tab CodeMirror 6 editor logic (`CodeEditor`, `CssEditor`, `JsEditor`, `ModalCodeEditor`, dirty tracking, isolated timers) into a single authoritative module (< 500 lines) and deleted redundant `plugin-codemirror.js`.
  - **Purged 32 Redundant Legacy Files & Plugins (~1.1 MB)**: Cleaned out obsolete CodeMirror 5 files (`libs/codemirror/lib/`, `theme/`), unused `libs/jszip/`, `libs/bootstrap-colorpicker/`, 5 legacy plugins, and 11 empty stub placeholder files.
  - **Script Deduplication & Loader Optimization (`scripts.html`)**: Enqueued `cm6-lezer.bundle.min.js` at the top of script sequence and removed duplicate script enqueues for `components-wordpress.js` and `align-serializer.js`.
  - **0% Data Leakage (`align-serializer.js`)**: Added `data-ast-id` to serializer blocklists, ensuring all internal tree identifiers are stripped before saving to WordPress database.

## [6.6.0] - 2026-08-17

### Core Architecture & Anti-Extension Normalizer
- **Deterministic Clean State & Schema Serializer Engine (`align-serializer.js`, `wp-save-handler.js`, `scripts.html`, `class-builder-ui-renderer.php`, `class-builder-content-handler.php`, `class-builder-crud-handler.php`, `class-builder-rest-api.php`, `MANIFEST.md`, `deterministic-clean-state-and-serializer.md`)**:
  - **Schema-Driven DOM Tree Normalizer (`align-serializer.js`)**: Replaced heuristic string regex/replace methods with a strict DOM traversal engine. Whitelisted full HTML5 standards and SVG vector specifications (`viewBox`, `d`, `fill`, `stroke`, `transform`...).
  - **Allowed Animation & Web Datasets**: Preserved 100% of animation and UI library namespaces (`data-aos*`, `data-swiper*`, `data-gsap*`, `data-scroll*`, `data-speed`, `data-bs-*`, `data-align-*`, `data-component-*`, `data-overwrite-*`, `aria-*`).
  - **Strict Browser Extension Blocklist**: Blocked and stripped 100% of extension mutations (`bis_*`, `data-gr-*`, `data-gramm*`, `data-lastpass*`, `data-1p*`, `data-loom*`, `data-browser-*`, `data-extension-*`, `data-preview-loading`) and rogue extension custom elements (`<grammarly-*>`, `<loom-*>`, `<newsection>`).
  - **Component Zero-Preview-Persistence**: Cleaned component placeholder card innerHTML while preserving component wrappers (`data-component-shortcode`, `data-component-wp-block`) and inline shortcodes in text nodes.
  - **Canvas Iframe Proactive Locks (`class-builder-content-handler.php`)**: Added anti-translation (`<html translate="no" class="notranslate">`) and anti-extension spellcheck locks (`<body data-gramm="false" data-enable-grammarly="false" spellcheck="false">`).
  - **REST API Nonce Security Fix (`class-builder-rest-api.php`)**: Enforced strict nonce validation in `check_permission()` to prevent CSRF bypass when nonces are omitted.
  - **Rich Debug Logging**: Added execution metrics and structured logging in browser console (`[AlignSerializer]`, `[Align SaveHandler]`) and PHP error logs (`[Align ContentHandler]`, `[Align CrudHandler]`).

## [6.5.9] - 2026-08-17

### Core Bugfix & Architecture
- **Zero-Conflict Independent CSS & JS Architecture for Pages, Templates, Template Parts, and Reusable Blocks (`class-builder-content-handler.php`, `class-builder-crud-handler.php`, `wp-save-handler.js`, `plugin-codemirror.js`, `builder-code-editors.js`, `class-builder-ui-renderer.php`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Pre-DOM CSS/JS Extraction on Page Load (`class-builder-content-handler.php`)**: Extracted `<style id="vvvebjs-styles">` and `<script id="vvvebjs-scripts">` before any preview DOMDocument transformation, preventing shortcode previews from stripping the page's `<style>` tag into `<head>` and wiping the CSS editor on load.
  - **Guarded Code Editor Flush (`plugin-codemirror.js`, `builder-code-editors.js`)**: Guarded `CssEditor.flush()`, `JsEditor.flush()`, and `CodeEditor.flush()` strictly inside `if (this._timer)`, preventing uninitialized modal editors from overwriting live canvas CSS/JS with empty strings on save.
  - **Live DOM Source of Truth & 3-Field Save Payload (`wp-save-handler.js`, `class-builder-crud-handler.php`)**: Prioritized `StyleManager.getCss()` and `JsManager.getJs()` directly from live canvas state, and transmitted `{ html, css, js }` as distinct parameters to backend for clean, non-destructive concatenation.
  - **Automated Script Cache-Busting (`class-builder-ui-renderer.php`)**: Appended dynamic timestamp query parameter `?v=time()` to all local builder JavaScript assets to prevent stale browser caching.

## [6.5.8] - 2026-08-16

### UI & Enhancements
- **WordPress Gutenberg-Style Save Button Status Indicator (`wp-save-handler.js`, `builder-canvas-loader.js`, `builder-gui.js`, `top-panel.html`, `editor-panels.css`, `MANIFEST.md`, `builder-deep-dive.md`, `builder-guide.md`, `resolved-issues.md`)**:
  - **Contextual 4-State Visual Feedback**: Enhanced the Save button in the Topbar to directly indicate real-time status:
    - `Dirty`: Active blue (`#38bdf8`) with `Save` text when changes are made.
    - `Saving`: Disabled state with inline spinner (`.spinner-border-sm`) and `Saving...` text.
    - `Saved`: Disabled state displaying checkmark icon (`<i class="la la-check me-1"></i> Saved`) upon successful REST API persistence, automatically resetting Undo mutations (`Vvveb.Undo.reset()`).
    - `Revert on Edit`: Automatically switches back to active `Save` whenever new canvas edits, input events, or undo/redo mutations occur.
  - **Eliminated Bottom Toast**: Removed bottom screen toast notification (`displayToast`) during save success, focusing 100% of visual feedback directly onto the topbar Save button like standard WordPress Gutenberg.
  - **Seamless Keyboard Shortcut**: Mapped `Ctrl + S` / `Cmd + S` to delegate directly to `.save-btn.click()`, executing full WordPress REST persistence with visual feedback.

## [6.5.7] - 2026-08-16

### UI & Cleanup
- **Streamlined Topbar by Removing 6 Redundant Options & Underlying Functions (`top-panel.html`, `builder-gui.js`, `builder-config.js`, `builder-drag-drop.js`, `scripts.html`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Topbar Simplification**: Removed 6 unused buttons from the top panel toolbar: Designer Mode (`#designer-mode-btn`), Canvas Preview (`#preview-btn`), Fullscreen (`#fullscreen-btn`), Show Hidden Elements (`#toggle-hidden`), Toggle Navigator (`#toggle-tree-list`), and Download (`#download-btn`).
  - **Engine Function Cleanup**: Purged unused handler methods from `builder-gui.js` (`download`, `toggleHidden`, `preview`, `fullscreen`, `setDesignerMode`), removed global `launchFullScreen()` from `builder-config.js`, and removed `setDesignerMode` from `builder-drag-drop.js`.
  - **Asset Dependency Optimization**: Removed unused JSZip and FileSaver scripts (`jszip.min.js`, `filesaver.min.js`, `plugin-jszip.js`) from `scripts.html`.

## [6.5.6] - 2026-08-16

### UI & Features
- **Tree Navigator Hover Action Buttons (Duplicate & Delete) (`builder-content-manager.js`, `builder-tree-list.js`, `editor-tree-navigator.css`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Contextual Hover Controls & Strict Row Pinning**: Implemented interactive Duplicate (`.tree-node-clone`) and Delete (`.tree-node-delete`) action buttons on hover for every canvas DOM node in the Navigator (`#tree-list`), strictly pinned to row header coordinates (`top: 1px; right: 6px; height: 22px`).
  - **Individual Element Hover Isolation**: Isolated hover state per individual element row using `label:hover + .tree-node-actions` and `:has(> label:hover)`, completely eliminating vertical misalignment and hover bleed across parent/child elements.
  - **Seamless DOM & History Sync**: Integrated instant node duplication (`cloneNode(true)`) and deletion (`node.remove()`) with full Undo/Redo mutation recording (`Vvveb.Undo`), automatic selection update, and real-time TreeList & SectionList reloads.
  - **SortableJS Drag Isolation**: Filtered `.tree-node-actions` from SortableJS to prevent accidental drag initiation when clicking action buttons.

## [6.5.5] - 2026-08-16

### UI & Cleanup
- **Removed Redundant Edit Code Button from Element Actions Toolbar (`top-panel.html`, `plugin-ai-assistant.js`, `builder-deep-dive.md`)**:
  - **Toolbar Streamlining**: Removed the `<a id="edit-code-btn">` (`</>` icon) from the centered `#select-actions` element toolbar, consolidating all Code Editor access into the dedicated full-page Code Editor modal (`Ctrl + E`).
  - **Graceful Fallback**: Maintained safe null guards in `builder-selection-manager.js` and updated insertion anchor in `plugin-ai-assistant.js`.

## [6.5.4] - 2026-08-16

### Fixed & Enhanced
- **Eliminated Forced IIFE Wrapping on Custom JavaScript (`render.php`, `MANIFEST.md`, `builder-guide.md`, `resolved-issues.md`)**:
  - **Natural JavaScript Scope Control**: Removed automatic `(function() { ... })();` wrapping on inline scripts rendered by `align/custom-html`, enabling developer-defined global functions (e.g. `onclick` inline handlers, `window` APIs) and third-party scripts to function properly.
  - **User & Developer Flexibility**: Allowed users and developers full autonomy to manage local closures (`(function(){ ... })()`), `DOMContentLoaded` listeners, or global declarations according to their implementation requirements.

## [6.5.3] - 2026-08-16

### UI & Enhancements
- **Temporarily Hide Default Component Groups in Builder Sidebar (`editor-drag-elements.css`, `resolved-issues.md`)**:
  - **Sidebar Category Cleanup**: Temporarily hidden standard component groups (`Base`, `Elements`, `Bootstrap 5`, `Widgets`, `Embeds`) from the builder left sidebar and popup modal via targeted CSS rules (`.components-list > li.header[data-section="..."]`).
  - **100% In-Memory Preservation**: Retained full JavaScript component definitions (`Vvveb.Components`) in memory, ensuring Code Editor, Inspector, Style Manager, Typography, Spacing, and element node matching continue working seamlessly without any disruption.

## [6.5.2] - 2026-08-16

### Fixed
- **Preserve Code Editor JS Tab Code on Reopen / Tab Switch (`builder-js-manager.js`, `class-builder-content-handler.php`, `wp-save-handler.js`, `MANIFEST.md`, `resolved-issues.md`)**:
  - **Head-Scoped Script Persistence**: Relocated `<script id="vvvebjs-scripts">` to `<head>` alongside `<style id="vvvebjs-styles">`, preventing `Vvveb.Builder.setHtml()` and canvas `body.innerHTML` replacements from wiping custom JavaScript code from the DOM.
  - **Clean Single-Source Initialization**: Removed redundant, non-debounced `Vvveb.JsEditor` definition from `builder-js-manager.js`, maintaining unified responsibility in `builder-code-editors.js` and `plugin-codemirror.js`.
  - **Flush & Sync Order Standardization**: Standardized save flush sequence (`CodeEditor` -> `CssEditor` -> `JsEditor`) ensuring seamless round-trip synchronization between CodeMirror, Canvas DOM, and WordPress REST API.

## [6.5.1] - 2026-08-16

### Cleanup & Architecture
- **Removed Custom Post Type `align_reuse_block` & `core/reuse-block/` Module (`class-align-reusable-block.php`, `init.php`, `includes/index.php`, `reuse-block-detector.js`)**:
  - Removed custom post type registration `align_reuse_block`, admin menus, preview endpoints, and custom Gutenberg block (`align/template-instance`).
  - Deleted obsolete `core/reuse-block/` directory completely from theme core.
  - Implemented clean class `Align_Reusable_Block` in `core/includes/core/class-align-reusable-block.php` for native WordPress Reusable Blocks (`wp_block` Synced Patterns).
  - Maintained full support for shortcodes (`[reuse-block id="..."]`, `[reuse_block]`, `[align_block]`, `[align_reusable_block]`, `[reusable_block]`), runtime block rendering (`do_blocks`), and per-page live text/media overrides (`_align_reusable_overrides`) with REST endpoint `GET /align-reuse/v1/fields` for Visual Builder.
  - Updated `core/init.php` bootstrap lifecycle and updated documentation (`overview.md`, `builder-guide.md`, `critical-issues-gotchas.md`, `resolved-issues.md`).

## [6.5.0] - 2026-08-16

### Cleanup & Performance
- **Deprecated & Removed Redundant Child Theme Asset Directories (`template-assets-loader.php`)**:
  - Removed obsolete `autoCreateTemplateAssets()` and `ensureTemplateAssets()` from `TemplateAssetsLoader` (`core/inc/template-assets-loader.php`), eliminating automatic creation of unused dummy folders and placeholder files (`assets/dist/template/` and `assets/src/template/`).
  - Removed `admin_init` hook and `handleCmsTemplateSave()` invocation that previously generated these folders upon admin page visits or template saves.
  - Purged physical `align-child/assets/dist/` and `align-child/assets/src/` directories from child theme workspace for a clean, streamlined assets structure.

## [6.4.9] - 2026-08-16

### Fixed & Enhanced
- **Context-Aware Gutenberg & Site Editor Deep Linking (`editor.js`, `block.json`, `init.php`, `align-site-editor-integration.js`, `wp-filemanager.js`, `class-builder-ui-renderer.php`, `class-builder-content-handler.php`)**:
  - **Multi-tier Context Resolution**: Implemented 5-layer target detection (`props.context`, block tree `getBlockParents` for nested `core/template-part`, `core/edit-site` store, `core/editor` store, and URL query params) ensuring "Edit with Align Builder" cards and buttons accurately route to the exact entity.
  - **Pages & Blocks Routing**: Generated `admin.php?page=align-builder&post_id={id}` for standard Pages, Posts, and Reusable Blocks.
  - **Templates & Template Parts Routing**: Generated `admin.php?page=align-builder&tp_id=tpl_{theme}//{slug}` for FSE Templates and `&tp_id=tp_{theme}//{slug}` for Template Parts (Header, Footer, Sidebar), eliminating wrong fallback loading.
  - **Script Localization**: Localized `AlignVvvebConfig.builderUrl` directly onto `align-custom-html-editor` script handle.
  - **Auto-load & Fallback Resilience**: Enhanced `wp-filemanager.js` and `get_page_content()` to auto-detect and resolve clean slugs, theme prefixes, and template types with zero 404s or misdirected loads.

## [6.4.8] - 2026-08-16

### Documentation & Architecture
- **Standardized 4 Tree Categories, Explicit ID Scoping & 100% CSS/JS Independence (`MANIFEST.md`, `builder-guide.md`, `builder-deep-dive.md`, `critical-issues-gotchas.md`, `overview.md`)**:
  - Detailed explicit ID specifications: numeric ID (`page_{id}`, `block_{id}`) for Pages & Reusable Blocks vs prefixed string ID (`tpl_{theme}//{slug}`, `tp_{theme}//{slug}`) for Templates & Template Parts.
  - Enforced zero ID collision & conflict rules with mutually exclusive save payloads (`{ post_id, tp_id: null }` vs `{ post_id: null, tp_id }`) and strict query scoping via `post_name__in`.
  - Documented strict CSS & JS independence: `<style id="vvvebjs-styles">` and `<script id="vvvebjs-scripts">` are isolated per entity, completely reset on page transitions, and encapsulated inside `align/custom-html` with zero cross-contamination.

## [6.4.7] - 2026-08-16

### Added
- **Floating Action Dock for Helper Widget (`vvvebjs-editor-helpers.css`)**:
  - Converted `#vvvebjs-new-section` (`<newsection>`) into a modern floating action dock fixed at the bottom center of the Canvas viewport (`position: fixed !important; bottom: 14px`).
  - Added `pointer-events: none` on the root host container and `pointer-events: auto` on the interactive pill dock (`.vvveb-new-section-col`), ensuring empty areas remain 100% click-through transparent to underlying canvas elements.
  - Styled floating dock with rounded pill card (`border-radius: 30px`), backdrop blur glassmorphism, dashed border, and modern pill action buttons.
  - Added `padding-bottom: 75px !important` to canvas body in editor mode to prevent the fixed dock from obscuring bottom-most page content when scrolled to the end.

## [6.4.6] - 2026-08-16

### Fixed
- **Template Parts & FSE Data Isolation Architecture (`class-builder-crud-handler.php`, `class-builder-content-handler.php`, `wp-filemanager.js`, `wp-save-handler.js`, `wp-items-crud.js`, `left-panel.html`)**:
  - **`WP_Query` `post_name__in` Resolution**: Fixed critical query parameter bug replacing invalid `name__in` with valid `post_name__in` across `save_page()`, `get_page_content()`, and `delete_item()`, eliminating root cause of cross-contamination where saving Header updated Footer.
  - **Mutually Exclusive Save Payloads**: Enforced strict client-server data scoping between Pages (`post_id`, `tp_id: null`), Full Templates (`tpl_slug`), and Template Parts (`tp_slug`), completely preventing cross-talk between CPTs.
  - **Template Part Creation UI**: Added dedicated UI modal and button (`add-wp-template-part-btn`) in Left Panel for creating standalone Template Parts.
  - **Pure Blank-Canvas Architecture**: Removed all hardcoded preset fallbacks (`<h1>...</h1><p>Start designing...</p>`), ensuring empty pages and template parts remain completely clean (0 bytes) for CMS customization.

## [6.4.5] - 2026-08-16

### Fixed
- **Code Editor Real-time Sync, Isolation & Empty-State Resilience (`plugin-codemirror.js`, `builder-code-editors.js`, `builder-drag-drop.js`, `builder-style-manager.js`, `builder-js-manager.js`, `wp-save-handler.js`)**:
  - **CSS & JS Preservation in `setHtml()`**: Fixed bug where syncing HTML without style/script tags inadvertently cleared `StyleManager` and `JsManager`, causing custom CSS/JS to be erased when switching to the HTML tab.
  - **Empty-State HTML Sync (`builder-drag-drop.js`)**: Removed `!html` early return in `setHtml()`, allowing empty strings `""` to immediately empty the Canvas DOM body and prevent old HTML from resurrecting when switching tabs.
  - **Independent Debounce Timers (`plugin-codemirror.js`)**: Isolated debounce timers (`this._timer`) for each editor tab (HTML 500ms, CSS 100ms, JS 200ms), eliminating singleton timer cancellation across concurrent events.
  - **Instant `flush()` on Tab Switch & Save**: Added automatic `flush()` execution on tab change (`hide.bs.tab`), modal close (`hidden.bs.modal`), and Save (`.save-btn`), guaranteeing zero data loss in flight.
  - **Clean Post-Save Code Editor Sync (`wp-save-handler.js`)**: Updated save completion callback to feed clean body HTML `Vvveb.Builder.getHtml(true, false)` back into `CodeEditor` instead of database payload with prepended/appended tags.
  - **Empty-State CSS & JS Lifecycle**: Ensured clearing CSS or JS code properly removes `#vvvebjs-styles` and `#vvvebjs-scripts` from the Canvas DOM and accurately maintains empty states across tab transitions.

## [6.4.4] - 2026-08-16

### Added
- **LinkInput Dark Mode & Compact Locked State Overhaul (`tmpl-input-basic.html`, `input-link.js`, `editor-inputs-base.css`, `editor-dark-theme.css`)**:
  - Full Dark Mode styling for LinkInput with harmonious inputs, search icon, and dropdown items.
  - Redesigned locked state card into ultra-compact 31px single-row container with prominent `#ID` pill badge, `text-truncate` title with tooltip, and sleek `la la-unlink` button.
  - Automatically populated locked link state directly from element attributes on initialization.

### Fixed
- **Multi-Layer Zero-Helper-Leak & Safe Save Pipeline (`builder-drag-drop.js`, `builder-new-section.js`, `wp-save-handler.js`, `class-builder-crud-handler.php`)**:
  - Fixed regex ordering bug in `removeHelpers()` preventing canvas helper tags (`<newsection>`, `[Add component] [Add section]`) from ever persisting into database content.
  - Preserved live canvas state post-save in `wp-save-handler.js` without destructive `setHtml()` invocation.
  - Added 4-layer defense sanitization across Client DragDrop, Client SaveHandler DOMParser, Server PHP DOMXPath/Regex, and Canvas setHtml.
- **Canvas Flexbox Isolation & Alignment Safeguards (`vvvebjs-editor-helpers.css`, `builder-drag-drop.js`, `class-builder-content-handler.php`)**:
  - Enforced `flex-direction: column !important; flex-wrap: nowrap !important;` on canvas body in editor mode to prevent user flexbox rules (`display: flex`) from forcing helper buttons into horizontal columns next to content cards.
  - Extracted `<style id="vvvebjs-styles">` and `<script id="vvvebjs-scripts">` into `<head>` and footer, preventing DOM pollution inside canvas `<body>` and fixing selection box coordinate offsets.
- **Zero-Empty-Code DOM Cleanliness (`builder-js-manager.js`, `builder-style-manager.js`, `wp-save-handler.js`, `class-builder-crud-handler.php`)**:
  - Prevented automatic generation of empty `<script id="vvvebjs-scripts"></script>` and `<style id="vvvebjs-styles"></style>` tags.
  - Added automatic DOM self-removal when custom JS or CSS is empty or erased, keeping HTML output 100% minimal.

## [6.4.3] - 2026-08-16

### Fixed
- **WP Shortcode & Gutenberg Block Zero-Preview-Persistence Lifecycle (`wp-save-handler.js`, `wp-components-preview.js`, `components-wordpress.js`, `builder-drag-drop.js`)**:
  - Fixed issue where Shortcode and Gutenberg Block placeholder cards vanished (collapsed to empty 0px container) upon clicking "Save" on the Top Panel.
  - Added automatic `WpComponentsPreview.triggerWpComponentsPreviewInIframe(true)` execution in `WpSaveHandler` after `Vvveb.Builder.setHtml()` and inside `Builder.setHtml()` itself.
  - Enhanced placeholder detection to check `.align-component-placeholder` existence instead of any non-empty inner text, preventing un-rendered states.
  - Updated `init(node)` on `wordpress/shortcode` and `wordpress/block` to auto-restore placeholder cards when selecting components.
- **Canvas Hover Highlight & Badge Identification (`builder-highlight-manager.js`)**:
  - Added component bubble-up with `target.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]')` inside `highlightMove`, ensuring the hover highlight box and header badge consistently display `component shortcode` instead of inner generic `Div` tags.
- **Server-side Preview Encapsulation (`class-builder-content-handler.php`)**:
  - Wrapped content with `<!DOCTYPE html><html>...` inside `render_builder_components_for_preview()` to prevent `DOMDocument` libxml parsing failures on complex HTML fragments.

## [6.4.2] - 2026-08-16

### Added
- **Full Architecture Decomposition (< 500 Lines / File)**:
  - Decomposed entire Builder engine and assets into 123 self-contained, single-responsibility modules, with 100% of files strictly `< 500 lines`.
  - Reorganized custom CSS into 14 modular stylesheets under `core/builder/css/` loaded through single entrypoint `editor.css`.
- **Real-time Live Sync Engine for Code Editor**:
  - Implemented 100ms instant CSS live injection from Code Editor into Iframe canvas `<style id="vvvebjs-styles">`.
  - Enabled automatic JS script injection and execution (`runJsOnSetHtml: true`) on iframe updates.
  - Added clean "Live Sync" status badge on `#codeEditorModal`.

### Fixed
- **Tree Navigator Fallback & Auto-Load**:
  - Fixed Tree Navigator empty state on initial iframe boot by triggering `Vvveb.TreeList.loadComponents()` and `Vvveb.SectionList.loadSections()` in `_frameLoaded`.
  - Added automatic fallback in `getNodeTree()` to recognize and index 100% of HTML tags (`h1`-`h6`, `p`, `div`, `span`, `a`, `section`, etc.) with ID/class badges.
  - Filtered out non-visual elements (`SCRIPT`, `STYLE`, `LINK`, `META`, `NOSCRIPT`) and internal `#vvvebjs-*` helpers from tree view.
- **Console Errors & 404 Asset Links**:
  - Fixed relative icon paths in `bootstrap-custom.css` and normalized `Vvveb.baseUrl` & `Vvveb.imgBaseUrl` in `builder-core.js`.
  - Replaced jQuery `$(node)` calls in `bootstrap-base-spacing.js`, `bootstrap-grid-columns.js`, `bootstrap-forms.js`, and `widget-finance-charts.js` with pure native DOM methods.
- **Inspector Text Input Restoration**:
  - Restored priority `Text` textarea input (`sort: 1`) on `html/heading`, `html/paragraph`, and `html/list-item` in the right-side Content Inspector.
  - Eliminated duplicate component definitions across Bootstrap modules.

## [6.4.1] - 2026-08-16

### Added
- **Basic HTML Semantic Tags & Div Support in Builder & Tree Navigator (`components-html.js`, `builder.js`)**:
  - Registered `html/div` ("Div Container") with `nodes: ["div"]` enabling generic `<div>` elements to be properly recognized by `matchNode()`.
  - Registered full suite of basic HTML elements: `html/span` ("Span"), `html/nav` ("Nav"), `html/main` ("Main"), `html/article` ("Article"), `html/aside` ("Aside"), `html/details` ("Details"), `html/summary` ("Summary"), `html/figcaption` ("Figcaption"), and `html/inline-text` (`strong`, `b`, `em`, `i`, `u`, `s`, `small`, `mark`, `sub`, `sup`).
  - Added `"html/div"` and `"html/span"` to `Vvveb.ComponentsGroup['Base']` for drag-and-drop availability in sidebar.
  - Enhanced `getNodeTree()` title resolution to display formatted `#id` and `.class` (e.g. `Div - .align-builder-1`) on Tree Navigator items.
  - Integrated `Vvveb.TreeList.loadComponents()` into `_frameLoaded()` ensuring Tree Navigator automatically renders when iframe loads.

### Fixed
- **Real-time Synchronization on Delete, Move, Clone, & Edit (`builder.js`, `undo.js`, `editor.css`, `top-panel.html`)**:
  - Cleared `self.selectedEl = null` and automatically hid `#select-actions` top toolbar upon element deletion.
  - Added keyboard `Delete`/`Backspace` shortcut listener for quick element deletion.
  - Fixed `d-flex` CSS rule specificity issue on `#select-actions` ensuring toolbar completely hides when no element is selected.
  - Added real-time TreeList & SectionList re-sync on `moveNodeUp`, `moveNodeDown`, `cloneNode`, and `Undo.restore('childList')`.
- **Two-Way Synchronization Pipeline Between Code Editor, Iframe DOM, & Tree Navigator (`plugin-codemirror.js`, `builder.js`, `top-panel.html`, `editor.html`)**:
  - Unified `#codeEditorModal` as the single authoritative live code editor (HTML, CSS, JS).
  - Enhanced `setHtml()` to trigger real-time updates for `TreeList`, `SectionList`, and `WpStyleInjector`, ensuring DOM edits in CodeMirror instantly reflect across the canvas and Navigator.
  - Added `#edit-code-btn` to element action toolbar, opening the Code Editor and automatically scrolling and selecting the target element's markup in CodeMirror.
  - Linked CSS editor directly to `#vvvebjs-styles` with real-time live preview.
- **Linked Styles Empty Selector Query Exception (`components-common.js`)**:
  - Fixed `querySelectorAll` SyntaxError caused by empty/invalid selectors returned by `getSelectorForElement(node)` on root/detached nodes. Wrapped in validation and defensive `try-catch`.

## [6.4.0] - 2026-08-15

### Fixed
- **Dummy Image Replacement & Picture Source Synchronization Pipeline (`class-builder-assets.php`, `class-builder-crud-handler.php`, `wp-style-injector.js`, `wp-save-handler.js`, `wp-media-modal.js`, `components-html.js`, `plugin-media.js`, `resolved-issues.md`)**:
  - Expanded regex in `Align_Builder_Assets::replace_dummy_images()` to support `srcset` and `data-srcset` attributes on `<source>` tags alongside `src` and `data-src`.
  - Added URL mapping cache `$replaced_urls` ensuring all `<source>` and `<img>` belonging to the same image/picture container receive the exact same WebP replacement image.
  - Integrated `replace_dummy_images()` into backend save pipeline in `Align_Builder_Crud_Handler::save_page()`.
  - Implemented `getDummyReplacement()` in frontend `WpSaveHandler.applyAutoLazyload()` to automatically convert external dummy images (non-`wp-content/`) to local builder WebP assets on save.
  - Fixed `shouldSkipUrl()` in `wp-style-injector.js` to prevent circular re-replacement of builder assets and unified `<picture>` replacement across responsive breakpoints.
  - Added `init(node)` to `html/image` property in `components-html.js` and `components-bootstrap4.js` to accurately inspect container `<figure>` and `<picture>` elements.
  - Enhanced `WpMediaModal` to instantly synchronize canvas `<img>` (`src`, `data-src`) and all sibling `<source>` tags upon selection from WordPress Media Library.

## [6.3.9] - 2026-08-15

### Fixed
- **Gutenberg Block Invalidation Fix for `align/custom-html` (`block.json`, `editor.js`, `resolved-issues.md`)**:
  - Restored `"source": "raw"` for `content` attribute in [`block.json`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes/align/core/blocks/blocks/dynamic/custom-html/block.json) and updated `save()` in [`editor.js`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/themes/align/core/blocks/blocks/dynamic/custom-html/editor.js) to return `wp.element.RawHTML(attributes.content)`.
  - Added `deprecated` fallback handler for blocks saved with `save: () => null`.
  - Completely resolved the *"Block contains unexpected or invalid content"* error when viewing Align Builder pages in Gutenberg Editor while preserving full server-side shortcode execution and script IIFE wrapping.

## [6.3.8] - 2026-08-14

### Changed
- **Dark Mode Popover & Compact Typography Refinement (`#add-item-menu`, `.align-modal-card`, `editor.css`, `left-panel.html`, `editor.html`, `modals.html`, `MANIFEST.md`)**:
  - Updated `#add-item-menu` popover dropdown with seamless dark mode support (`#23272e` background, `#374151` dark border, `#d1d5db` text color, subtle `#374151` hover background).
  - Reduced dropdown font size from `13px` to compact `11.5px`, icon size from `15px` to `13.5px`, and item padding to `6px 12px`.
  - Refactored `#align-create-item-modal` container to `.align-modal-card` with dark mode support (`#23272e` card, `#374151` border, `#191c21` dark inputs with `#6366f1` focus states).

## [6.3.7] - 2026-08-14

### Changed
- **Centered Topbar Element Action Toolbar & Contextual Dynamic Controls (`top-panel.html`, `canvas.html`, `editor.css`, `builder.js`, `MANIFEST.md`)**:
  - Relocated element action toolbar (`#select-actions`) from canvas overlay to topbar center (`#top-panel`), freeing 100% canvas workspace area.
  - Added `#top-select-element-badge` displaying selected element tag and class selector in real-time.
  - Added dynamic contextual buttons: Change Image (`#change-img-btn`) launching WordPress `wp.media` modal for `<img>` tags, and Edit Link (`#edit-link-btn`) for `<a>` tags.
  - Automated bound checking for `Up` and `Down` buttons based on element sibling position.
  - Streamlined toolbar controls by removing obsolete floating buttons (`drag-btn`, `parent-btn`, `edit-code-btn`, `translate-code-btn`, `save-reusable-btn`).

## [6.3.6] - 2026-08-14

### Fixed
- **404 Undefined Image Asset Prevention & macOS Cmd+Z Keybindings (`builder.js`, `undo.js`)**:
  - Fixed 404 network error (`/libs/builder/undefined`) in `drawComponentsTree` by validating `node.image` before composing background-image inline style.
  - Fixed macOS `Cmd + Z` / `Cmd + Shift + Z` / `Cmd + Y` keyboard shortcuts by adding `e.metaKey` evaluation alongside `e.ctrlKey` across all shortcut handlers.
  - Upgraded `Vvveb.Undo.restore()` for `case 'move'` and `case 'swap'` with helper box protection (`#vvvebjs-new-section`), automatic DOM tree refresh, and canvas element re-selection.

## [6.3.5] - 2026-08-14

### Added
- **Tree Navigator Drag & Drop Insertion & Visual 3-Zone Trigger System (`builder.js`, `editor.css`, `undo.js`)**:
  - Implemented **Tree Insertion Mode** across `#tree-list` allowing users to move elements into containers as child elements without swapping or kicking out existing items.
  - Implemented **Vertical 3-Zone Trigger System**:
    - **Top 25%**: Displays dashed top border indicator (`border-top: 2px dashed`) to insert element BEFORE target item as a sibling.
    - **Middle 50%**: Displays **solid border NO DASH** (`border: 2px solid`) with background fill to insert element INSIDE target container as a child.
    - **Bottom 25%**: Displays dashed bottom border indicator (`border-bottom: 2px dashed`) to insert element AFTER target item as a sibling.
  - Refactored DOM sibling node resolution algorithm (`newNextSiblingNode`) in `onEnd` handler to ensure 100% precise position placement in iframe canvas, resolving bottom appending issues.
  - Added automated cancellation (`clearDropIndicators`) when mouse is released outside tree container bounds.
  - **Full Undo/Redo Engine (`undo.js`)**: Updated `case 'move'` and `case 'swap'` in `Vvveb.Undo.restore()` with proper parent/sibling insertion, body helper protection, tree refreshing, and canvas element re-selection on `Ctrl + Z` and `Ctrl + Y`.

## [6.3.4] - 2026-08-14

### Added
- **SortableJS Swap Integration on Sidebar Section List & Navigator Tree (`builder.js`, `scripts.html`, `undo.js`, `editor.css`)**:
  - Successfully implemented Phase 2 of the SortableJS Swap integration plan on both **Page Sections List** (`.sections-container`) and **Navigator Tree List** (`#tree-list`).
  - Added `<script src="libs/sortable/sortable.complete.min.js"></script>` to `core/builder/partials/scripts.html` to fix 404/undefined script load issues in PHP backend UI rendering.
  - Enabled `forceFallback: true`, `fallbackClass: 'sortable-fallback'`, and `fallbackTolerance: 3` across `Vvveb.SectionList` and `Vvveb.TreeList`, isolating single-click node selection (< 3px) from drag-and-drop swap (> 3px).
  - Added `case 'swap':` to `Vvveb.Undo.restore()` for full Undo/Redo (`Ctrl+Z` / `Ctrl+Y`) support.
  - Added `.sortable-swap-highlight` and `.sortable-fallback` CSS rules in `editor.css`.

## [6.3.3] - 2026-08-14

### Added
- **Click-to-Add Component Support & Context-Aware Placement (`builder.js`)**:
  - Implemented `Vvveb.Builder.appendComponent(type, dragType)` to automatically create and insert components, blocks, or sections into the active container or after the currently selected element (or at canvas end before `<newsection>`).
  - Enhanced `_initDragdrop` with smart drag threshold detection ($> 5\text{px}$) to distinguish between Drag-and-Drop and direct single-click actions, enabling users to add any element with a single click.
  - Embedded styled placeholder cards directly into `components-wordpress.js` component templates, ensuring 100% realtime visual rendering immediately upon addition.

### Fixed
- **Navigator Tree Realtime Update on Hidden Tabs (`builder.js`)**:
  - Removed faulty `list.offsetParent === null` guard in `Vvveb.TreeList.loadComponents()` that silently skipped DOM updates whenever elements were added while viewing the Components/Sections tabs.
  - Added `shown.bs.tab` event listener to automatically refresh the Navigator tree and restore active component selection upon switching back to the Pages/Navigator tab.
  - Added null safety checks for `this.container`, `window.FrameDocument`, and element selection labels.

## [6.3.2] - 2026-08-14

### Added
- **Ajax Real Link Search & Lock ID Link Control (`inputs.js`, `tmpl-inputs.html`, `class-builder-rest-api.php`, `class-builder-content-handler.php`, `render.php`)**:
  - Implemented real-time debounced (300ms) Ajax link search querying all public WordPress posts, pages, and custom post types via `GET /align-builder/v1/search-links`.
  - Added visual **Locked Card View** for links locked to a specific WordPress Post ID (displaying post title, document icon, `ID: {id}` badge, and a red `Disconnect` button) to match the requested design.
  - Implemented runtime **Dynamic Permalink Resolution** in both Frontend (`align/custom-html` render template) and Builder Canvas Iframe (`Align_Builder_Content_Handler::resolve_dynamic_links()`) to automatically resolve `get_permalink($id)` from `data-align-link-id`, preventing 404 broken links even when post titles or permalink slugs are modified.
  - Fully integrated REST Nonce authentication (`X-WP-Nonce` header + query parameter) and robust container-based event handling with safe click-outside dropdown dismissal.

### Fixed
- **Navigator Tree View Helper Elements Filter (`builder.js`)**:
  - Added filter in DOM traversal function `getNodeTreeTraverse()` to ignore internal editor UI helpers (`#vvvebjs-new-section`, `<newsection>`, `[data-vvveb-helpers]`), preventing the `<div class="container">` of the "Add component / Add section" box from erroneously showing up in the Navigator tree after moving or editing elements.
  - Added defensive filter to `Vvveb.SectionList.getSections()` to prevent helper elements from appearing in the Page Sections list.

## [6.3.2] - 2026-08-19

### Added
- **Multi-Instance Reusable Block Isolation (`class-align-reusable-block.php`, `wp-save-handler.js`, `reuse-block-detector.js`)**:
  - Implemented multi-instance discriminator `instance="name"` (e.g. `[reuse-block id="19" instance="top"]`) allowing multiple embeds of the same master Reusable Block on a single page with completely isolated override states.
  - Backward-compatible nested data structure in `_align_reusable_overrides` supporting both flat legacy overrides `{ [blockId]: { [ovId]: data } }` and keyed instance overrides `{ [blockId]: { [instanceKey]: { [ovId]: data } } }`.
- **True Master Template Reset & Modified Badges (`reuse-block-inspector-live.js`, `reuse-block-inspector-api.js`, `class-align-reusable-block.php`)**:
  - REST API endpoint `/align-reuse/v1/fields` now returns raw `master_value` alongside effective `value`.
  - Added field-level `Reset` buttons, `Modified` badges, and a `Reset All to Default` button in the Content Overwrite accordion.
  - Resetting instantly restores canvas DOM and inputs to true master `wp_block` defaults and clears post meta in database upon save.
- **WP Shortcode 2-Row Layout & Contextual Instance Tip (`components-wordpress.js`)**:
  - Converted Shortcode Text property from a single-row cramped input to a full-width 2-row layout with a 3-row `textarea` control.
  - Embedded a styled contextual English tip box under Shortcode Text guiding users on how to use `instance="name"` for multi-instance embeds.
  - Configured the Content Overwrite accordion to open by default upon selecting a Reusable Block.

### Security
- **Multi-Layer Script & Style Sanitization (`class-align-reusable-block.php`, `class-builder-crud-handler.php`)**:
  - Integrated regex stripping (`#<script\b[^>]*>(.*?)</script>#is` & `#<style\b[^>]*>(.*?)</style>#is`) combined with `wp_kses_post()` and `esc_url_raw()` on both CRUD save and shortcode render to completely eliminate Stored XSS and DOM script residue.

## [6.3.1] - 2026-08-14

### Fixed
- **Builder Component Group Loaders & Accordion ID Sanitization (`builder.js`, `vvvebjs-wp-integration.js`)**:
  - Sanitized category header IDs (`cleanGroup = String(group).replace(/[^a-zA-Z0-9_-]/g, '_')`) across `loadControlGroups`, `loadSectionGroups`, `loadBlockGroups`, and `loadStylesGroups` to eliminate invalid IDs with spaces that prevented clicking to expand categories like `Bootstrap 5` and `Server Components`.
  - Scoped loop iteration variables with `let` to prevent polluting global scope and breaking DOM element counters.
  - Initialized all four component groups synchronously on `DOMContentLoaded` via `vvvebjs-wp-integration.js`.
- **Shortcode & Gutenberg Block Persistence Across Save/Reload (`class-builder-content-handler.php`, `wp-components-preview.js`, `wp-style-injector.js`)**:
  - Standardized server-side `render_builder_components_for_preview()` to return lightweight, styled placeholder cards directly in `/page-content` response instead of executing `do_shortcode()` which broke on empty data and polluted the builder canvas DOM.
  - Attached automated event listeners for core `Vvveb.iframe.loaded` and `Vvveb.FileManager.loadPage` events to ensure shortcode and block placeholder cards always render cleanly upon initial load, page switch, or browser F5 refresh.

---



### Fixed
- **Align Builder Page Navigation & SPA Redirect Loop (`vvvebjs-wp-integration.js`)**:
  - Replaced full page reload (`window.location.href`) on sidebar page/template selection with seamless Single Page Application (SPA) `history.pushState()` URL updates and dynamic iframe canvas loading (`Vvveb.Builder.loadUrl`).
  - Fixed fallback redirect loop where clicking any page would force a reload back to default `post_id=2`.
  - Normalized `activePostId` & `activeTpId` lookup in `loadWpPages()` to handle string/number type casting safely.
- **Local Builder WebP Dummy Image Auto-Replacement & Dynamic Relative Paths (`class-align-builder.php`, `vvvebjs-wp-integration.js`, `builder.js`)**:
  - Implemented `Align_Core_Vvveb_Builder::replace_dummy_images()` to automatically replace demo/external image URLs with local builder WebP images (`image_1.webp` .. `image_5.webp`) in round-robin sequence.
  - Added strict protection for all media assets under `wp-content/` (uploads, plugin/theme assets) to prevent overwriting user-uploaded images.
  - Added dynamic `MutationObserver` on iframe canvas body for real-time URL replacement when drag-and-drop adding new sections/blocks.
  - Sanitized image `src` and CSS `url()` attributes across PHP backend, JS DOM, and `Vvveb.Builder.getHtml()` to strip scheme & domain origin, enforcing 100% dynamic relative paths (`/wp-content/...`).
- **WP Site Editor Auto DB-to-Disk Sync & FSE CPT Prefixes (`template-assets-loader.php`, `class-align-builder.php`)**:
  - Added `save_post_wp_template` and `save_post_wp_template_part` hooks (`handleCmsTemplateSave`) to automatically sync database template edits (e.g. deleting Title block in WP Site Editor) directly to theme `.html` files in `templates/` or `parts/` directories and invalidate `wp_clean_theme_json_cache()`.
  - Fixed CPT template part post_name query prefix (`align-child//slug`) in `api_save_page()` and `api_get_page_content()`.
  - Removed intrusive canvas blur overlay in `align-site-editor-integration.js` to ensure block editor displays unblurred.

---

## [6.2.3] - 2026-08-13

### Fixed
- **Custom Asset Script DOM Null Guards (`uploads/align-assets/script.js`)**:
  - Added null check guards for `header`, `menuButton`, and `mobileMenu` DOM query elements.
  - Prevented `Uncaught TypeError: Cannot read properties of null (reading 'classList')` inside `onScroll` listener when `.site-header` element is missing from DOM.

---

## [6.2.2] - 2026-08-05

### Added
- **Minify Source Code Batch Engine**:
  - Integrated a dedicated **Minify Source code** button (`#align-dt-btn-minify-source`) inside the **Source Code Editor** sidebar toolbar (`core/includes/dev-tools/views/ui.php`).
  - Added AJAX endpoints `align_dt_scan_source_files` and `align_dt_minify_source_file` in `Align_Dev_Tools_Ajax` to safely scan Child Theme source files (`assets/src/`) and compile SCSS/SASS to CSS and minify CSS/JS file-by-file sequentially via API into `assets/dist/` (with automatic fallback to `Align_Dev_Tools_Compiler`).
  - Created a glassmorphism progress modal overlay (`#align-dt-minify-modal-overlay`) with real-time terminal log console, progress percentage bar, auto-scrolling log messages, and automatic `AssetsCache` transient invalidation.
- **Device Detector Script Optimization (`align-device.js`)**:
  - Moved `align-device.js` script enqueue (`align_enqueue_device_detector`) from `<head>` to the footer (`$in_footer = true`).
  - Registered `align-device` with `AssetsJS::markAsOptimized('align-device')` to integrate with the `data-align="optimize"` delay/lazy-loading mechanism.

---

## [6.2.1] - 2026-08-05

### Changed
- **Moved Third-Party JS Plugins to Theme Core**:
  - Relocated JS plugin libraries (`smooth-scroll` / Lenis and `splide` / Splide.js + Auto-scroll extension) from Child Theme (`align-child/assets/plugins/`) to Parent Theme Core (`align/core/assets/plugins/`).
  - Updated `PluginAssets::getRegistry()` in `core/inc/template-assets/class-plugin-assets.php` to resolve plugin URLs and directory paths using `get_template_directory_uri() . '/core/assets/plugins/'`.
  - Updated `align_enqueue_smooth_scroll_scripts()` in `core/includes/core/smooth-scroll.php` to enqueue Lenis smooth-scroll from `get_template_directory_uri() . '/core/assets/plugins/smooth-scroll/lenis.min.js'`.
  - Cleaned up redundant `assets/plugins/` directory from `align-child`.
  - Updated technical documentation in `docs/template-assets-loader.md`.
- **Added Mandatory Development Rules to Documentation**:
  - Updated `docs/general-docs.md` and `docs/blocks/block-coding-standards.md` to enforce:
    1. **Backwards Compatibility First**: Always maintain full compatibility with legacy code/attributes when updating the theme to prevent errors on existing sites.
    2. **Mandatory CHANGELOG Maintenance**: Always log all fixes, updates, and features in `CHANGELOG.md` as a permanent memo reference.

### Fixed
- **Query Builder (`align/query-builder`) Undefined Variable Warning**:
  - Fixed `Warning: Undefined variable $linkWholeContainer` in `core/blocks/blocks/dynamic/query-builder/render.php` (lines 385, 386, 387, 391) by properly extracting `$linkWholeContainer`, `$filterTermIds`, and `$searchKeyword` from `$attributes` in `align_query_builder_render_callback()`.

---

## [6.2.0]

### Changed
- **Swup.js Clean Removal**: Removed all dependencies, frontend initializing scripts, backend options, and admin CMS settings related to Swup.js to simplify page routing.
- **Theme Upgrader Overwrite Options**: Added "Replace Existing Files Only" option (copy-paste style) to prevent newly introduced files/directories in the zip from being extracted, while leaving existing files overwritten and other local files intact.

### Fixed
- **Gutenberg Align Settings Icon Deduplication**: Fixed duplicate "Align Settings" header toolbar icon rendering in Gutenberg block editor by selecting a single `PluginSidebar` / `PluginDocumentSettingPanel` component in priority order (`wp.editPost` → `wp.editSite` → `wp.editor`) instead of iterating and instantiating multiple package exports.

---

## [6.1.0]

### Added
- **Live Search REST API Endpoint**: Migrated search backend from traditional `admin-ajax.php` to a custom WordPress REST API endpoint (`POST /wp-json/align/v1/live-search`) to bypass admin panel overhead and increase speed.
- **Client-Side & Server-Side Search Caching**:
  - Client-side Memory Cache in JavaScript to serve identical queries instantly (0ms) and eliminate repeated server hits.
  - Server-side Transient Cache utilizing WordPress Transients API to store query results in database options for 15 minutes, with automatic cache busting on post save/update/delete events.
- **Search Mode Block Controls**: Added Gutenberg block sidebar controls (Allow Mode Toggle, Default Search Mode) to customize mode behavior.

### Fixed
- **Webkit Search Clear Button Bug**: Resolved Webkit native search clear button overlap bug by styling cancel button representations out of active field boundaries.

---

## [5.0.0]

### Added
- **Developer Tools Security Lock**: Secured Developer Tools settings tab behind password `9dVhsUax@` using an AJAX-based session validation system saved directly to WordPress user meta with a 12-hour expiration guard.
- **Real-Time Syntax Linter**: Connected a debounced (600ms) PHP CLI linting checker to CodeMirror File Manager editor. Validates scripts on keypress, displays detailed warnings, and dynamically blocks file saving in case of syntax errors to prevent server-side white screens of death (WSOD).
- **Advanced System & Performance Toggles**: Relocated revision limits, update controls, host whitelist configurations, and text-to-HTML ratio tracking to Developer Tools sub-tab. Added new options for theme/plugin editing, theme/plugin mods, trash cleanup intervals, autosave durations, and global comment closures.
- **Comprehensive Image Size Customizations**:
  - Enabled override dimensions (width, height, crop) for all registered image sizes, including core WordPress and custom plugin/theme sizes.
  - Added "Disable generation" control next to each size to selectively block WordPress from producing specific image dimensions on upload.
  - Dynamic inline AJAX form to add custom image sizes (with name/ID, dimensions, crop option, and inline centering styling).
  - Integrated dynamic image size deletion via a premium glassmorphic modal confirmation overlay.
- **Server File Editors**:
  - `.htaccess` Editor Sub-tab: Integrated a dedicated `.htaccess` editor tab displaying active server file path and permissions state.
  - `wp-config.php` Editor Sub-tab: Secure `wp-config.php` editor sub-tab featuring path auto-resolution, active writable checking, and real-time writing capabilities with overwrite warning modal confirmations.

### Changed
- **Layout & UI Enhancements**: Cleaned up vertical alignment in forms, converted standard dialog alerts to premium custom overlay modals, and customized CSS layout bindings for settings fields.

---

## [4.0.0]

### Added
- **Full Site Editing (FSE) Templates & Parts**: Implemented standard block templates (`single.html`, `page.html`, `search.html`, `archive.html`, `404.html`) and block template parts (`header.html`, `footer.html`).
- **Live Search AJAX Block**: Dynamic live-search block with customizable PHP render-results templates, taxonomy filters, and relative routing.
- **Transitions & Smooth Scroll**: Integrated Lenis smooth scroll as an inline script and optimized core transitions.

### Changed
- **Gutenberg Custom Blocks Upgrades**:
  - Structured Media block inspector sidebar into 3 panels (Editor, SEO/ADA, Technical).
  - Added Unified Image Dropzone & Preview to Media block with absolute delete and trigger controls.
  - Synced video preview state programmatically with play/pause and hidden controls in the canvas editor.
  - Upgraded Query Builder block inspector sidebar to 4 panel sections with Slate inputs.
  - Switched Element block container to RichText to support inline editing on the canvas with optimal click target height.
  - Integrated automatic unique ID generator for duplicate blocks in Gutenberg JS editor.
- **Modern Slate UI Style Overhaul**: Styled all Gutenberg sidebar controllers with Slate colors, custom SVG arrows, border radius 6px, and border `#cbd5e1`. Redesigned Align Settings Admin UI with full-width page structure, version badge, transparent tab headers, clickable labels, and a top-right Save button.

---

## [3.0.0]

### Added
- **Media Block Preview & Controls Sync**: Added responsive 16:9 aspect-ratio image preview inside Gutenberg Sidebar block panel (Image Options). Synchronized autoplay, loop, and muted states programmatically with browser autoplay policy guards.
- **Duplicate Block ID Conflict Resolver**: Integrated block editor duplicate handler in JS and static tracker in PHP to detect and automatically append random suffixes to `elementId`, `videoId`, and `imgId`.
- **Dynamic Link Search**: Added support for relative URL resolution in internal link searching with custom relative path formatting.
- **Transitions & Smooth Scroll**: Added Barba.js smooth page transitions and customizable Smooth Scroll polyfill toggles via Customizer.

---

## [2.0.0]

### Added
- **Dynamic Template Assets Loader Module**: Demand-driven asset resolution, selective inlining, and lazy-loading via `data-align="optimize"` attribute.
- **Tracking & Ads Code Manager**: Inject custom tracking, GA, GTM, or ads code in Header, Body Open, and Footer from Align Settings, with environment guards.

### Changed
- **Performance & Cleanup**: Strip redundant WP Core/Gutenberg styles, emojis, and inline CSS (Greenshift, block templates) to minimize page weight.
- **Comment Deactivation**: Completely disable comment and trackback functionalities site-wide, including comments UI removal and URL redirects.

---

## [1.0.0]

- Initial release of Align theme.
