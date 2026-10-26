# ALIGN THEME — MASTER CONTEXT FILE
# AI Navigation & 360-Degree Docs Lookup
# Version: 1.0 | Generated: 2026-09-12
# ──────────────────────────────────────────────────────────────────

## INSTRUCTIONS FOR AI AGENTS

Load this file ONCE at the start of any task related to the Align Theme.
It gives you a complete map of all documentation, keywords, and file locations.

### Reply Style (Mandatory):
- **Short and focused** — answer only what is asked, nothing more.
- **No preamble, no recap, no summary at the end** — go straight to the point.
- **No rambling** — if the answer fits in 3 lines, write 3 lines.
- **Code blocks over prose** — show the solution directly, explain only if non-obvious.
- When ambiguous: ask 1 short question instead of writing a long assumption.

### Media / Images Rule (Mandatory — Builder & Core):
- **Always use images from the WordPress Media Library** (`wp_get_attachment_url`, `wp_get_attachment_image_src`, or attachment ID).
- **Never crop, extract, or use images from design mockups or reference screenshots.**
- If no specific image is specified: **pick any existing image from the media library** as a placeholder — do not leave `src` empty or use placeholder.com.
- Query available attachments via: `wp post list --post_type=attachment --fields=ID,post_title,guid`

### Lookup Workflow:
1. Extract keywords from the user's request (English or Vietnamese).
2. Scan the FILE INDEX below — match keywords against each entry's [KEYS].
3. Read the matched file(s) for precise technical details.
4. For structured machine lookup, use `manifest.json` (same directory) — it contains the same index as JSON for programmatic search.

### Quick CLI Search:
```bash
node -e "
  const m = require('./manifest.json');
  const q = process.argv[1].toLowerCase();
  m.files.filter(f => f.keywords.some(k => k.includes(q)) || f.summary.toLowerCase().includes(q))
         .forEach(f => console.log(f.path, '|', f.summary.slice(0,100)));
" "YOUR KEYWORD HERE"
```

---

## MANDATORY: PROMPT CLASSIFICATION (Do This Before Anything Else)

Before reading any file or writing any code, classify the user's prompt into one of 3 contexts.
This determines which manifest, which docs, and which files to load.

### Classification Rules

**CONTEXT A — BUILDER (Visual Canvas / Frontend Editor)**
Trigger when prompt mentions ANY of:
- "builder", "canvas", "kéo thả", "drag", "section", "block", "visual",
- "vvveb", "iframe", "tree navigator", "sidebar panel", "inspector",
- "serialize", "clean state", "DOM", "toolbar", "right panel", "left panel",
- "align studio", "studio", "live preview", "CodeMirror",
- "shortcode preview", "SSR preview", "shadow DOM",
- "SortableJS", "reorder", "swap section", "nesting",
- "builder JS", "builder CSS", "builder PHP class",
- "save page", "load page", "render block", "render shortcode",
- "component", "vvvebjs", "block editor canvas"

→ Load: `align/core/builder/MANIFEST.md` first, then run:
  `node align/core/builder/manifest/search.js "keyword"`
→ Docs: `docs/builder/` folder entries in this CONTEXT.md
→ Do NOT assume any function/class exists — verify via manifest search first.

---

**CONTEXT B — CORE BACKEND (PHP Modules / Settings / API / DB)**
Trigger when prompt mentions ANY of:
- "settings", "admin", "hub", "tab", "panel settings",
- "PHP", "hook", "filter", "REST API", "endpoint", "AJAX",
- "shortcode register", "add_shortcode", "wp_register",
- "database", "SQL", "wp_posts", "wp_postmeta", "wpdb",
- "language", "dịch", "translate", "hreflang", "multilingual",
- "redirect", "rank math", "redirection importer",
- "git updater", "deploy", "update theme",
- "media", "WebP", "image", "LCP", "preload",
- "checkout", "cart", "FluentCart", "WooCommerce",
- "login", "register", "authentication",
- "module", "inc/", "includes/", "class-align-",
- "functions.php", "init.php", "bootstrap"

→ Load: `docs/CONTEXT.md` FILE INDEX (this file), match keywords to relevant category.
→ Docs: `docs/architecture/`, `docs/api/`, `docs/translate/`, `docs/modules/` entries.
→ Use `grep_search` or `find_by_name` on `align/core/includes/` to locate PHP classes.

---

**CONTEXT C — CODING WITH BUILDER (Build a Page / CSS / JS / Shortcode)**
Trigger when prompt mentions ANY of:
- "build page", "tạo trang", "làm trang", "viết trang",
- "CSS token", "design token", "màu", "color", "spacing", "font",
- "class name", "utility class", "root.css",
- "slider", "Splide", "carousel", "swiper",
- "AJAX pagination", "load more", "query builder",
- "lazyload", "lazy image", "data-src",
- "reuse block", "reusable block", "[reuse-block",
- "shortcode dùng", "nhúng shortcode", "embed shortcode",
- "style-page-", "style-block-", "style-part-", "style-template-",
- "page ID", "post ID", "slug", "tìm trang", "tìm page",
- "WP-CLI", "wp post get", "query DB",
- "content type", "phân biệt page", "template part"

→ Load: `docs/coding-with-builder/` — start with `coding-with-builder/README.md`
→ Decision tree inside that README will point to the exact sub-file needed.
→ Always check `align-child/assets/root.css` for token values before writing any CSS.

---

### When BOTH A + B or A + C apply:
- Start with the simpler context first (C or B)
- Layer in Builder manifest search for any function/class references
- Never assume Builder internals without checking `manifest.jsonl`

### Classification Decision Table

| Keyword signal | Context |
|:---|:---:|
| "canvas", "vvveb", "iframe editor", "tree navigator" | A — Builder |
| "sidebar panel", "inspector", "DOM serialize", "clean state" | A — Builder |
| "REST API endpoint", "CRUD handler", "save_page PHP" | A + B |
| "PHP class", "hook", "filter", "admin settings", "hub" | B — Core BE |
| "shortcode register", "add_shortcode", "functions.php" | B — Core BE |
| "translate", "language", "hreflang", "deepseek" | B — Core BE |
| "build page", "CSS token", "color variable", "class name" | C — Coding |
| "reuse-block shortcode", "nhúng block", "embed block" | C — Coding |
| "slider config", "Splide", "lazyload update" | C — Coding |
| "tìm page ID", "query slug", "WP-CLI" | C — Coding |
| Ambiguous / mixed | Ask 1 clarifying question: "Builder canvas hay PHP backend?" |

---

## CODEBASE ROOT

All paths below are relative to: `align/docs/`
Theme root: `align/` (at `wp-content/themes/align/`)
Child theme: `align-child/` (at `wp-content/themes/align-child/`)

---

## FILE INDEX

Format: [ID] path | CATEGORY | [KEYS: keyword1, keyword2, ...] | One-line summary

### CATEGORY: architecture
Files covering system structure, admin panel, git, and import tooling.

[architecture-overview] architecture/overview.md
KEYS: architecture, overview, init.php, functions.php, core modules, bootstrap lifecycle,
      hybrid block theme, template assets loader, admin settings, visual builder,
      reusable blocks, wp_block, LCP preload, font preload, webp, minify, breakpoints,
      reuse-block shortcode, data-align optimize, pagespeed, performance,
      Align_Seo, class-align-seo.php, _align_seo_title, _align_seo_description
SUMMARY: 4 core engine modules (Custom Blocks Engine, Visual Builder & Asset Engine,
         Admin Settings & Performance Engine including Built-in Lightweight SEO Engine Align_Seo, Cloud API), bootstrap lifecycle, key design principles.
PHP ENTRY: core/init.php > functions.php

[settings-guide] architecture/settings-guide.md
KEYS: admin settings, align settings, site protection, HTTP basic auth, staging, password,
      live domain, staging domain, environment detection, localhost bypass, manual fallback,
      noindex, builder studio, SEO languages, performance media, developer tools, integrations,
      tab persistence, localStorage, URL hash, webp converter, thumbnail regenerator,
      image sizes, minify HTML, custom code, tracking code, google analytics, GTM, meta pixel,
      font preload, asset loader, git updater, translation matrix, telegram, webhooks,
      endpoint tester, disable wpautop, block converter, HTML converter, cookie, HMAC
SUMMARY: 8 Master Hubs in Align Settings Admin: Site Protection (HTTP Basic Auth, smart environment-aware domain detection for Live & Staging, noindex),
         Builder & AI, SEO & Languages, Performance & Media, Developer Tools, Integrations, Dedicated AI REST API, Git Updates.
         Tab persistence via localStorage + URL hash.
PHP: core/includes/admin/class-align-admin-settings.php

[plugin-menu-integration-guide] architecture/plugin-menu-integration-guide.md
KEYS: plugin menu, admin menu, submenu, align-settings, align plugin, align-files,
      align-seo-checker, align-deploy, align-agent, hook, add_submenu_page,
      align_register_admin_submenus, ALIGN_CORE_PATH, parent slug
SUMMARY: Architectural rules for Align ecosystem plugins to hook submenus under the Align Theme
         master menu ('align-settings') at the bottom of the WP sidebar (position 99).
PHP: core/includes/admin/class-align-admin-menu.php

[settings-style-guide] architecture/settings-style-guide.md
KEYS: style guide, UI design, button standard, transparent button, button-primary,
      button-secondary, button-danger, button-disabled, card design, accordion,
      zero emoji, typography-first, admin notice, dashicons, spinner, settings-layout.css,
      align-settings-card, is-accordion, border, hover, 32px, 6px, 12.5px
SUMMARY: Mandatory UI standards: 100% transparent buttons, Card system, Accordion default-closed,
         Zero emoji policy, admin notice isolation. CSS class standards for all button variants.

[html-zip-importer] architecture/html-zip-importer.md
KEYS: zip importer, HTML importer, import, ZIP upload, template import, html project,
      static html, auto assembly, Align_Zip_Importer, Align_Zip_Scanner, wp_template_part,
      wp_block, wp_insert_post, header part, footer part, sideload media, webp, dummy image,
      zip slip, security, overwrite, site reset, internal links, tab-import-zip.php, starter template
SUMMARY: HTML Project ZIP Importer: auto-detects Header/Footer parts, Reusable Blocks (wp_block),
         sideloads media to WebP, dummy fallback, internal link resolution, Zip Slip guard.
PHP: core/includes/admin/importer/

[template-zip-rules] architecture/template-zip-rules.md
KEYS: zip rules, template zip, zip structure, naming convention, folder structure, zip format,
      upload rules, importer rules, template upload, checklist, header.html, footer.html,
      parts/, blocks/, pages/, assets/, file naming, kebab-case
SUMMARY: Required folder structure and naming rules for ZIP template files uploaded to Align Importer.

[git-updater] architecture/git-updater.md
KEYS: git updater, git update, theme update, GitHub, pull code, deploy, push-to-deploy, zipball,
      GitHub API, maintenance mode, rollback, align_git_pull_code, align_git_test_connection,
      WP_Filesystem, backup, clean replace, rollback guard, terminal console, live log
SUMMARY: Git Theme Core Updater: pulls from GitHub (no Git CLI needed), WP Maintenance Mode,
         backs up theme, clean-replaces, auto-rollback on failure, live terminal console UI.
PHP: core/includes/admin/class-align-git-updater.php

[git-workflow] architecture/git-workflow.md
KEYS: git workflow, git commit, push, commit message, gitignore, repository structure,
      themes/, sub-repo, git status, 6-step commit, commit standards, exclude files
SUMMARY: Git repo structure (root at themes/), .gitignore list, 6-step commit/push workflow.

[local-agent-cli] architecture/local-agent-cli.md
KEYS: agent-page, cli, local agent, headless builder, pull, push, diff, sync, db direct,
      single source of truth, dual storage, template sync, dynamic shortcode, dynamic block,
      safety guards, post_content, wp_posts, align-child/templates, asset files
SUMMARY: CLI tool (align/bin/agent-page) and architectural guide for local AI agents to safely read,
         modify, and push pages and FSE templates directly to CMS/DB and disk in compliance with Align Builder.
CLI: align/bin/agent-page (php core: align/bin/agent-page-core.php)

---

### CATEGORY: builder
Files covering the Visual Builder, Align Studio, serializer, and media standards.

[builder-guide] builder/builder-guide.md
KEYS: visual builder, builder guide, builder engine, template assets loader,
      class-align-builder.php, class-builder-assets.php, class-builder-admin.php,
      class-builder-rest-api.php, class-builder-content-handler.php,
      class-builder-crud-handler.php, class-builder-ui-renderer.php,
      class-builder-custom-templates-handler.php, custom templates, layout templates,
      REST API, align-builder/v1, save page, create page, delete page,
      pages tree, canvas, iframe, WYSIWYG, drag drop, kéo thả,
      8 clusters, modular architecture, facade bootstrap, asset resolver,
      admin menus, REST router, CRUD handler, UI renderer, partials, vvvebjs,
      dark mode, template assets, demand-driven asset, data-align optimize,
      lenis, splide, lazyload, WebP replacer, font preload,
      /pages, /page-content, /save-page, /render-shortcode, /render-block,
      /create-item, /delete-item, /search-links, /custom-templates, wp-json/align-builder/v1
SUMMARY: Comprehensive Builder guide: 8-cluster PHP architecture, REST API endpoints
         (/align-builder/v1), Template Assets Loader, JS integration layer.
REST: /wp-json/align-builder/v1/

[builder-deep-dive] builder/builder-deep-dive.md
KEYS: deep dive, builder technical, file map, quick location map, PHP classes, JS modules,
      CSS architecture, class-builder-minifier.php, class-align-post-content.php,
      class-builder-custom-templates-handler.php, plugin-custom-templates.js,
      custom templates, custom layout templates, align_builder_custom_templates,
      customTemplateModal, create template, delete template, code icon,
      core/builder/partials, core/builder/libs/builder/engine,
      core/builder/libs/builder/inputs, core/builder/libs/builder/components,
      core/builder/libs/builder/sections, core/builder/libs/builder/blocks,
      core/builder/libs/media, core/builder/js/modules,
      vvvebjs-wp-integration.js, wp-init.js, wp-style-injector.js,
      wp-filemanager.js, align-serializer.js, wp-save-handler.js,
      wp-media-modal.js, wp-items-crud.js, wp-toolbar-tooltips.js, wp-context-menu.js,
      editor-context-menu.css, contextmenu, chuột phải, right-click, copy element, paste element,
      editor.css, editor.html, SPA, head.html, sidebar-nav.html, canvas.html,
      modals.html, tmpl-inputs.html,
      page indicator, current page pill, align-page-indicator, #align-page-indicator,
      unsaved changes guard, confirm dialog, navigation warning, leave page confirm,
      updatePageIndicator, is-template, is-template-part, is-reusable,
      editor-top-panel.css, top-panel page indicator, save warning, navigate away,
      desktop viewport 1920x1080, full hd canvas, auto fit zoom, calcFitZoom, setViewport,
      translate(-50% -50%) scale, absolute centering canvas, iframe-wrapper centering, responsive viewports
SUMMARY: Complete technical reference for AI & Devs: Quick Location Map for all PHP/JS/CSS/HTML
         files, full system architecture, REST endpoint spec, data flow diagrams.
         Includes: Page Indicator Pill (#align-page-indicator) showing current editing target
         in top panel (4 color variants: Page/Template/Part/Reusable Block), Unsaved Changes
         Guard (confirm dialog before navigating to a different page/template via FileManager),
         and Full HD 1920x1080 Desktop Viewport Engine with calcFitZoom dynamic scaling and
         translate(-50%, -50%) absolute canvas centering architecture.
USE THIS WHEN: You need to find where a specific function, class, or feature lives in the codebase.

[builder-components-and-styles] builder/builder-components-and-styles.md
KEYS: components, blocks, styles, animations, builder blocks, html/heading, html/paragraph,
      html/link, html/image, html/button, html/video, html/form, html/table,
      elements/font-icon, elements/svg-image, elements/gallery, elements/figure,
      bootstrap, grid, container, row, column, navbar, card, modal,
      AOS, Swiper, GSAP, lenis, splide, animations, inspector, sidebar controls,
      input controls, color picker, font family, spacing, border radius, shadow,
      gradient, background image, background video, parallax, VvvebJs, sections, presets,
      responsive, srcset, decoding async, lazyload, lucide, fontawesome, line-awesome
SUMMARY: Complete catalog: Base HTML components, Elements, Bootstrap utilities,
         Animations (AOS, Swiper, GSAP, Splide), all Inspector sidebar controls.

[deterministic-clean-state] builder/deterministic-clean-state.md
KEYS: serializer, clean state, deterministic, schema serializer, align-serializer.js,
      align-serializer-schema.js, canvas isolation, Unicorn Studio, data-us-project,
      DOM normalizer, browser extension, extension junk, brave, grammarly, google translate,
      lastpass, 1password, bis_skin_checked, data-gramm, font tags, DOM pollution,
      DOM-as-state, regex anti-pattern, whitelist, allowlist, HTML5 whitelist, SVG specs,
      AOS dataset, GSAP dataset, shortcode preservation, data-shortcode, cloneNode,
      schema-driven, tree normalizer, iframe canvas, extension attributes, anti-xss
SUMMARY: 2-tier schema-driven DOM normalizer (align-serializer-schema.js + align-serializer.js):
         strips browser extension junk and unsafe tags while preserving 30+ interactive datasets
         (Unicorn Studio WebGL, text roll, stacking cards, radar diagram) with proactive Canvas Isolation.
JS: core/builder/js/modules/align-serializer-schema.js, core/builder/js/modules/align-serializer.js

[dynamic-block-studio-plan] builder/dynamic-block-studio-plan.md
KEYS: dynamic block studio, convert to dynamic, AI auto-bind, AI convert,
      reusable block exporter, dynamic block, align-dynamic-studio,
      admin.php?page=align-dynamic-studio, 1-click AI, field mapper,
      JS inspector, client-side mapping, dynamic tokens, post title token,
      thumbnail token, permalink token, ACF fields, pagination, AJAX load more,
      class-builder-dynamic-studio.php, class-builder-dynamic-ai-binder.php,
      class-builder-dynamic-renderer.php, wp-dynamic-block-converter.js,
      convert toolbar, select-actions, outerHTML extraction, scoped CSS extraction,
      system role prompt, zero hallucination prompt, reuse-block shortcode
SUMMARY: Technical spec for Dynamic Block Studio: 5-pillar architecture — toolbar button,
         standalone studio workspace, 1-click AI auto-bind (strict system prompt),
         client-side JS field mapper, export to wp_block.

[dynamic-studio-live-preview-spec] builder/dynamic-studio-live-preview-arch-spec.md
KEYS: live preview, real data testing, dynamic block studio live preview, architecture spec
SUMMARY: STUB FILE only — placeholder referencing external artifact. Feature not yet implemented.
NOTE: Skip for production lookup; content is in external conversation artifact.

[align-studio-guide] builder/align-studio-guide.md
KEYS: align studio, studio, micro-workspace, reusable block studio, admin.php?page=align-studio,
      CodeMirror 6, live 2-way sync, live preview, iframe sandbox, scoped CSS, scoped JS,
      whitelisted dynamic tokens, 1-click AI auto-bind, custom prompt, SSR token hydration,
      in-place update, wp_block, synced patterns,
      class-builder-dynamic-studio.php, class-builder-dynamic-ai-binder.php,
      class-builder-dynamic-renderer.php, studio.js, studio-preview.js, studio.css,
      studio-editor.js, studio-editor.css, studio-tokens.js, studio-tokens.css,
      studio/index.php, wp-dynamic-block-converter.js,
      HTML tab, CSS tab, JS tab, code editor, format code, token binding, AI bind,
      reuse-block shortcode, standalone workspace, independent workspace
SUMMARY: Align Studio micro-workspace: CodeMirror 6 live 2-way sync (HTML/CSS/JS),
         real-time iframe preview with theme assets, whitelisted dynamic tokens,
         1-click AI auto-bind with custom prompts, SSR hydration, export to wp_block.
URL: /wp-admin/admin.php?page=align-studio
PHP: core/inc/builder/class-builder-dynamic-studio.php
JS: core/builder/studio/studio.js

[gutenberg-embedded-builder] builder/gutenberg-embedded-builder.md
KEYS: gutenberg embedded, embedded builder, iframe canvas, two-way save bridge,
      align-site-editor-integration.js, align-editor-mode-switch.js, class-align-editor-mode-lock.php,
      editor mode lock, _align_editor_locked, _align_editor_mode, switch-editor-mode,
      wp_after_insert_post, rest_meta_database_error, filter_protect_locked_mode_meta,
      align-editor-overlay.css, is-embedded,
      ?embedded=1, ALIGN_BUILDER_TRIGGER_SAVE, ALIGN_BUILDER_SAVED, postMessage,
      zero scroll, full viewport height, top-panel, select-actions, toolbar,
      dynamic post content fallback, wp-filemanager.js, wp-save-handler.js,
      class-builder-ui-renderer.php, class-builder-admin.php,
      isSavingPost, core/editor, editPost, cleanHtml, breadcrumb, footer post
SUMMARY: Full guide for Gutenberg Embedded Align Builder Canvas, Two-Way Save Bridge & Editor Mode Lock:
         Iframe sandbox replacement of Gutenberg canvas with 100% zero-scroll height,
         preserves WordPress Topbar & Settings Sidebar, preserves Builder Top-Panel Toolbar
         (#select-actions, viewports, save button), dynamic fallback for blog posts/CPTs,
         editor mode lock (_align_editor_locked on wp_after_insert_post) & destructive switch endpoint (/switch-editor-mode),
         and bidirectional postMessage save bridge.
JS: core/builder/gutenberg/align-site-editor-integration.js, core/builder/gutenberg/align-editor-mode-switch.js
CSS: core/builder/gutenberg/align-editor-overlay.css
PHP: core/inc/builder/class-builder-ui-renderer.php, core/includes/admin/class-align-editor-mode-lock.php

[word-editor-and-custom-templates] builder/word-editor-and-custom-templates.md
KEYS: word editor, word mode, word-bubble-toolbar, bubble toolbar, floating toolbar,
      align-document-container, checkAndInitEmptyCanvas, markdown shortcuts, gutter inserter,
      word-gutter-inserter, anti-overlap, highlight-name, select-box suppression, is-word-mode,
      custom templates, custom layout templates, plugin-custom-templates.js,
      class-builder-custom-templates-handler.php, customTemplateModal, edit-custom-tpl-btn,
      delete-custom-tpl-btn, custom-tpl-actions, cleanTemplateHtml, afterDrop, restCustomTemplatesUrl,
      align_builder_custom_templates, edit template, custom css, custom js
SUMMARY: Word Editor Mode for distraction-free writing on blank canvas (floating bubble toolbar,
         smart clamping, dynamic arrow, anti-overlap suppression of select-box/highlight-box,
         markdown shortcuts, image toolbar) & Custom Layout Templates Subsystem (2-tier sync
         localStorage & REST API, in-place edit modal for HTML/CSS/JS, dual action buttons).
JS: core/builder/libs/builder/engine/builder-word-editor.js
CSS: core/builder/css/components/editor-word-mode.css
JS: core/builder/libs/builder/plugin-custom-templates.js
PHP: core/inc/builder/class-builder-custom-templates-handler.php

[responsive-media-shortcode-guide] builder/responsive-media-shortcode-guide.md
KEYS: responsive media, shortcode image, picture element, source element, srcset, image sizes,
      data-slot-width, wp-image class, data-id attribute, data-align-sizes, media-size-tags,
      tag picker, multi-size, chips, raw html image paste, mobile breakpoint, tablet breakpoint,
      599px, 992px, ratio-aware algorithm, closest-to-media algorithm, loading lazy, decoding async,
      width height attributes, CLS prevention, LCP optimization, core web vitals,
      wp_get_attachment_image_src, WebP image, attachment ID, media library, libxml,
      self-closing source tag, HTML4 parser, shortcode responsive, image shortcode
SUMMARY: Mandatory <picture> structure standard for all image shortcodes: mobile/tablet/desktop
         sources, Tag-Based Media Size Picker (limit 3 tags), Ratio-Aware proximity scoring algorithm,
         raw HTML paste support, required attributes, libxml self-closing gotcha.

[export-import-page] builder/export-import-page.md
KEYS: export page, import page, export zip, import zip, zip package, index.html, page.css, page.js,
      btn-export-page, btn-import-page, align-import-page-file, class-builder-export-import-handler.php,
      wp-export-import.js, /export-page, /import-page, Zip Slip protection, absolute URL conversion,
      align-reusable-overrides, preview import, zero auto-persist, scoped CSS, scoped JS,
      download zip, upload zip, la-download, la-upload
SUMMARY: Export / Import Page Package (ZIP): Exports currently saved page into a standalone ZIP containing
         index.html (with absolute assets and preserved shortcodes/overrides), page.css, and page.js.
         Imports ZIP package with Zip Slip protection and hydrates canvas preview without writing to database before Save.
PHP: core/inc/builder/class-builder-export-import-handler.php
JS: core/builder/js/modules/wp-export-import.js
REST: /wp-json/align-builder/v1/export-page, /wp-json/align-builder/v1/import-page

---

### CATEGORY: api
Files covering REST API endpoints and cloud microservices.

[ai-rest-api-reference] api/ai-rest-api-reference.md
KEYS: AI REST API, align-ai/v1, wp-json/align-ai/v1, class-align-ai-endpoint.php,
      class-align-ai-page-controller.php, dual-mode authentication, application passwords,
      REST nonce, X-WP-Nonce, Authorization Basic, HTTP Basic Auth,
      AI agent integration, Postman, Insomnia, CI/CD, generate page, update page,
      get page, read page, HTML body state, CSS state, JS state, 3-layer separation,
      align/custom-html block, KSES bypass, style tag, script tag,
      Interactive REST Console, decoupled architecture, Antigravity, Cursor,
      edit_pages capability, manage_options capability
SUMMARY: AI REST API (/wp-json/align-ai/v1): dual-mode auth (App Passwords for AI agents,
         Cookie+Nonce for browser), 3-layer HTML/CSS/JS separation, CRUD for pages,
         packaged into align/custom-html Gutenberg block, Interactive REST Console in Admin.
REST: /wp-json/align-ai/v1/

[align-cloud-api-reference] api/align-cloud-api-reference.md
KEYS: cloud API, align cloud, microservice, convertb.alignvn.cloud, Node.js, Express,
      /api/convert, HTML to Gutenberg, HTML to blocks, convert HTML,
      /api/convert-image-to-webp, WebP compression, image optimization,
      /api/scss-to-css, SCSS compiler, SCSS to CSS,
      /api/minify, minify CSS, minify JS, minify HTML,
      /api/unminify, unminify, code formatting, beautify,
      /health, health check, align_minify_api_url, align/element, Align_Endpoint_Tester
SUMMARY: Align Cloud API (https://convertb.alignvn.cloud): 6 endpoints — HTML-to-blocks converter,
         WebP image compression, SCSS compiler, CSS/JS/HTML minifier, code beautifier, health check.

---

### CATEGORY: translate
Files covering multilingual routing, translation management, SEO.

[translate-readme] translate/README.md
KEYS: multilingual, translation, đa ngôn ngữ, dịch thuật, Native-First, Zero-Bloat,
      no WPML, no Polylang, native multilingual, language manager, _align_lang, _align_lang_group,
      post meta language, class-align-language-manager.php, class-align-post-language-meta.php,
      class-align-template-language.php, class-align-language-switcher.php,
      tab-languages.php, tab-translation-matrix.php, class-align-translation-matrix.php,
      class-align-post-ai-seo.php, source code map, translation group, lang group matrix
SUMMARY: Overview + source code file map for Align Native Multilingual: Native-First Zero-Bloat
         (no WPML/Polylang), page hierarchy via Root Page, posts via Rewrite Rules,
         translation group linking via _align_lang_group post meta.

[translate-architecture-routing] translate/architecture-and-routing.md
KEYS: URL routing, multilingual routing, rewrite rules, language URL, root page, parent page,
      URL structure, /zh/, /zh/about/, /zh/insights/, /en/, /vi/,
      register_post_lang_rewrites, flush permalinks, permalink, 404 after deploy,
      staging to production, URL conflict, language slug, URL prefix, language prefix,
      page hierarchy, static pages routing, blog posts routing, post language URL
SUMMARY: URL routing: hidden Root Page for static pages (/zh/), custom Rewrite Rules for blog
         posts (/zh/insights/%postname%/), flush permalinks after deploy, common 404 gotchas.

[translate-post-page-management] translate/post-and-page-management.md
KEYS: post language, page language, translation management, language sidebar, Gutenberg sidebar,
      language panel, Language tab, _align_lang meta, _align_lang_group meta, post meta,
      language filter, WP admin filter, admin column, visual builder translation,
      builder language switch, template parts translation, header translation, footer translation,
      class-align-post-language-meta.php, class-align-template-language.php,
      rewrite rules, pre_get_posts, language query
SUMMARY: Post/Page translation management: _align_lang/_align_lang_group post meta,
         Language Sidebar in Gutenberg, WP Admin language filter, Visual Builder integration,
         template parts (header/footer) language linking.

[translate-translation-matrix] translate/translation-matrix.md
KEYS: translation matrix, matrix dashboard, translation status, language matrix, content matrix,
      translation overview, tab-translation-matrix.php, class-align-translation-matrix.php,
      translation grid, missing translations, untranslated pages, bulk actions,
      translation management dashboard, all languages view, language coverage, translation progress
SUMMARY: Translation Matrix Dashboard: centralized grid showing all pages × all languages,
         identifies missing translations, bulk action support.

[translate-bulk-ai] translate/bulk-ai-translator.md
KEYS: bulk AI translator, bulk translation, AI translation, auto translate, AI SEO, Rank Math,
      SEO metadata, AI SEO generation, batch translation, sequential translation, timeout resistant,
      4-step process, anti-timeout, style preservation, Gutenberg blocks translation,
      block translation, translate blocks, class-align-post-ai-seo.php,
      translate all pages, mass translation, SEO title translation, SEO description translation
SUMMARY: Bulk AI Translator: 4-step sequential process (anti-timeout), translates Gutenberg blocks
         preserving HTML structure, auto-generates SEO metadata (Rank Math) for translated pages.

[translate-seo-hreflang] translate/seo-and-hreflang.md
KEYS: hreflang, hreflang tag, canonical, canonical URL, multilingual SEO, international SEO,
      duplicate content, language alternate, link rel alternate, language switcher,
      align_language_switcher, sitemap, XML sitemap, Google multilingual, SEO international,
      country targeting, x-default hreflang, Align_Seo, class-align-seo.php,
      _align_seo_title, _align_seo_description, _align_seo_focus_keyword, _align_seo_canonical,
      _align_seo_robots, _align_seo_og_title, _align_seo_og_image, save_seo_theme_default,
      align_seo_fields, align_seo_saved, RANK_MATH_VERSION, WPSEO_VERSION
SUMMARY: International SEO & Built-in Lightweight SEO Engine (Align_Seo): automatic hreflang injection,
         independent canonical URLs per language, XML sitemap, [align_language_switcher] shortcode,
         7 _align_seo_* post meta keys, automatic suppression when Rank Math or Yoast is active, and AI Agent SEO integration.
PHP: core/includes/core/class-align-seo.php, core/includes/core/class-align-language-switcher.php

---

### CATEGORY: modules
Files covering specialized theme modules and child theme features.

[module-media-normalizer] modules/media-normalizer.md
KEYS: media normalizer, filename normalizer, file rename, sanitize filename, lowercase filename,
      kebab-case, CamelCase to kebab, accent removal, unicode filename,
      Windows NTFS, Linux case-sensitive, 404 image, deploy 404, production 404,
      media library, upload sanitize, sanitize_file_name, batch rename, atomic rename,
      two-step rename, safe_rename, class-align-media-normalizer.php,
      class-align-media-normalizer-admin.php, format filenames, row action,
      sub-sizes, WebP sub-sizes, Media Library button, upload.php
SUMMARY: Media Filename Normalizer: auto-sanitizes uploads to lowercase kebab-case (sanitize_file_name),
         batch normalizer for existing files (2-step Windows NTFS-safe rename), syncs sub-sizes/WebP.
         Accessible from Align Settings Tab Media and WordPress Media Library.

[module-checkout-page] modules/checkout-page.md
KEYS: checkout, checkout page, cart, FluentCart, ecommerce, align_checkout_page,
      fluentcart_checkout_page, align_checkout, shortcode checkout, AJAX cart,
      cart update, remove item, stepper quantity, login drawer, password eye,
      class-checkout-page-init.php, class-checkout-page-ajax.php,
      template-checkout-page.php, checkout-page.css, checkout-page.js,
      align-child, inc/checkout-page, preview mock data, Post 490,
      singleton, responsive 992px, no WooCommerce
SUMMARY: Checkout Page module in align-child: FluentCart integration, 3 shortcode aliases,
         AJAX cart operations, semantic HTML template, preview mock data mode. Zero WooCommerce.

[module-live-search] modules/live-search.md
KEYS: live search, search block, align/live-search, dynamic search, autocomplete,
      search dropdown, search results, post types search, taxonomy filter, category filter,
      debounce, transient cache, server cache, client cache, Lenis smooth scroll, scroll conflict,
      block.json, register.php, render.php, render-results.php, editor.js, script.js, style.css,
      postsPerPage, defaultMode, live mode, normal mode, allowModeToggle,
      selectedPostTypes, enableCategoryFilter, categoryFilterTaxonomy,
      REST API search, AJAX search, search placeholder, search input, popup dropdown
SUMMARY: Live Search dynamic block (align/live-search): debounced real-time search across multiple
         post types, taxonomy filter, server transient caching, Lenis scroll conflict fix,
         Gutenberg Inspector Controls, AJAX + REST endpoints.
Location: core/blocks/blocks/dynamic/live-search/

---

### CATEGORY: issues
Files covering bugs, security fixes, resolved history, pending backlog, and plans.

[issues-critical-gotchas] issues/critical-issues-gotchas.md
KEYS: critical issues, gotchas, known issues, technical pitfalls, AJAX query page,
      shortcode AJAX, is_admin check, wp_doing_ajax, align_parse_and_render_builder_components,
      content filters, REST_REQUEST, builder components AJAX, do_blocks, apply_filters the_content,
      lazyload AJAX, lazyLoadInstance update, IntersectionObserver, blank images AJAX,
      white images AJAX, lazy images not loading, libxml HTML4, source tag self-closing,
      picture tag libxml, DOMDocument, data-slot-width, slot-aware picture rendering,
      debug process, debug DB, debug disk, debug frontend, 3-step debug,
      UTF-8 BOM, invisible text node, unwanted spacing, empty text node, strip_bom, 0xEFBBBF
SUMMARY: Critical gotchas: (1) AJAX bypasses is_admin() content filter — must call
         apply_filters('the_content'). (2) Lazyload won't auto-scan AJAX HTML — call
         lazyLoadInstance.update(). (3) libxml breaks self-closing <source>. (4) data-slot-width.
         (5) 3-step debug process (DB/Disk/Frontend). (6) UTF-8 BOM causes invisible text nodes
         and unwanted spacing above navbar / between sections — strip_bom guard and DB sanitization.

[issues-security-fixes] issues/critical-security-fixes.md
KEYS: security fixes, security audit, vulnerability, bug fixes, DOM XSS, XSS vulnerability,
      F-01, F-02, F-03, F-04, F-05, F-06, delete_item arbitrary post,
      authorization check, nonce verification, API return values, debounce security,
      input sanitization, output escaping, CSRF, security review, penetration test,
      security backlog, pending security, audit findings
SUMMARY: Security audit backlog (F-01 to F-06): DOM XSS, arbitrary post deletion,
         API exposures, debounce issues. Each includes description, test case, fix plan.

[issues-resolved] issues/resolved-issues.md
KEYS: resolved issues, fixed bugs, bug history, changelog, issue log, 190 issues,
      refactor history, root cause analysis, RCA log, issue tracker, fix log, patch history,
      resolved bugs, technical history, architecture decisions, past fixes
SUMMARY: LARGE FILE (900KB) — 190+ resolved issues with RCA, solutions, lessons learned.
WARNING: Do NOT read full file. Use grep search for specific keywords instead.

[issues-pending] issues/pending-issues.md
KEYS: pending issues, backlog, deferred, not fixed, todo, known limitations, future fixes,
      workaround, Gutenberg Site Editor, site-editor.php, FSE, Full Site Editing,
      title delete sync, wp.data.subscribe, bi-directional sync,
      align-site-editor-integration.js, overlay, builder overlay,
      Gutenberg block title, core/post-title, List View delete
SUMMARY: Deferred backlog: Cannot delete Title block in Gutenberg Site Editor when page managed
         by Align Builder. Workaround: use 'Edit with Align Builder'. Future: wp.data.subscribe.

[issues-sortablejs-plan] issues/plan-sortablejs-swap.md
KEYS: SortableJS, sortable, drag drop, swap sections, reorder sections,
      sortable.complete.min.js, core/builder/libs/sortable, swap mode, re-parenting,
      cross-parent, nesting, 3-zone tree, 2-zone leaf, canAcceptChildren, _leafTags,
      undo redo mutations, Vvveb.Undo, section swap, section reorder,
      builder-section-list.js, builder-tree-sortable.js, builder-tree-render.js,
      tree navigator, zero live-mutation drag, pull false put false, inset box-shadow,
      expandedAstIds, stable data-ast-id, rAF coalesced loadComponents, scheduleSync
SUMMARY: SortableJS v1.15.6 integration plan (All 4 phases COMPLETED): Library setup,
         Sidebar Section List swap, 3-Zone/2-Zone Tree Navigator hit-testing with zero live-DOM mutation,
         canAcceptChildren leaf guard, rAF-coalesced render, stable data-ast-id, and Vvveb.Undo integration.

[issues-bug-logs] issues/bug-logs.md
KEYS: bug logs, bug log, issue log, resolved bugs, template revert trap, template tweaks,
      sync_all_templates_with_db, missing hero banner, dynamic shortcode discovery,
      single post template, spotlight-builder, single.html, single-builder.html,
      vessel magazine, bronco interior, post 2785, post 2738, lessons learned, root cause,
      BUG-20261018-2140, BUG-20261018-1955, BUG-20261018-1930, BUG-20261018-1950,
      rest_meta_database_error, wp_after_insert_post, filter_protect_locked_mode_meta,
      editor mode lock, _align_editor_locked, switch-editor-mode,
      tree navigator collapse, scrollTop jump, data-ast-id shift, SortableJS ping-pong, pull false put false, canAcceptChildren
SUMMARY: Structured incident log for recent bugs and anti-regression rules: _align_editor_locked HTTP 500 rest_meta_database_error fix (BUG-20261018-2140),
         Template disk-vs-DB revert trap, missing hero banners, workspace slug fallback, fixed header padding,
         Editor Mode Lock bypass & cross-editor content corruption (BUG-20261018-1955), Tree Navigator state/scroll/Undo preservation (BUG-20261018-1930),
         and SortableJS zero live-mutation + leaf nesting guard (BUG-20261018-1950).

---

### CATEGORY: coding-with-builder
Files for AI agents to understand how to build pages with Align Builder: CSS/JS reuse, data queries, content types, and shortcodes.

[coding-readme] coding-with-builder/README.md
KEYS: coding guide, build page, modify page, AI agent, decision tree, core rules,
      dual storage, KSES bypass, lazyload, shortcode, content type, asset system
SUMMARY: Master hub for coding-with-builder. Load first, use decision tree to find the right file.

[coding-content-types] coding-with-builder/content-types.md
KEYS: content type, page, template, template part, reusable block, wp_block,
      wp_template, wp_template_part, synced pattern, post_id, tpl_id, tp_id,
      style-page-, style-template-, style-part-, style-block-,
      reuse-block, reuse_block, align_block, data-component-shortcode,
      shortcode container, shortcode wrapper, vvvebjs-styles, align/custom-html,
      KSES bypass, wp_update_post, wpdb update, FSE, Full Site Editing,
      header part, footer part, parts/header.html, templates/page.html,
      _align_reusable_overrides, per-page overrides, reuse block embed
SUMMARY: The most important file: 4 content types (Page/Template/Part/Reusable Block), their
         storage, Builder ID params, asset naming, embedding methods, KSES bypass, and per-page
         Reusable Block overrides.

[coding-asset-system] coding-with-builder/asset-system.md
KEYS: file first, single source of truth, SOT, clean database, assets/builder, TemplateAssetsLoader,
      style-page-, style-template-, style-part-, style-block-, naming convention,
      root.css, conditional-css, banner.css, cta.css, newsletter.css, important.css,
      template conditional CSS, scoped CSS, scoped JS, locate assets, find CSS file,
      new page template, minimal template, enqueue, backward compatibility
SUMMARY: File-First SOT model (physical files in assets/builder/ are SOT, clean HTML in DB/templates),
         backward compatibility fallback guard, 5-tier CSS organization
         (root.css → general → conditional-css → template CSS → page scoped CSS), complete
         naming conventions, and minimal page asset templates.

[coding-css-design-tokens] coding-with-builder/css-design-tokens.md
KEYS: CSS variables, design tokens, root.css, :root, --color-1, --color-7, --color-10,
      Engine Red, Black, Cream, --color-1-rgb, rgba, --op-5,
      --sp-3xl, --sp-xl, --sp-base, --sp-xs, spacing scale,
      --fs-h1, --fs-body, --fs-eyebrow, --fs-small, font size, --fw-bold, --fw-semibold,
      --lh-relaxed, --lh-snug, --font-primary-1, --font-primary-2, Barlow,
      --radius-pill, --radius-md, --z-header, --z-modal, --z-toast,
      .container-1440, .spacing-top-bottom, Bootstrap 5 grid, .row, .col-lg-6, .g-4,
      .text-color-, .bg-color-, .fs-h1, .fw-bold, .sp-py-xl,
      .glass-box, .glass-card, .btn-custom-quote, .floating-btn,
      .newsletter-form, .newsletter-content, .newsletter-submit,
      breakpoint, mobile 490px, tablet 992px, zero hardcoded hex
SUMMARY: All design tokens from root.css (10 colors, spacing, typography, grid) with responsive
         breakpoints. All utility classes. Shared section component classes: .glass-box,
         .btn-custom-quote, .newsletter-form, .floating-btn. Rule: zero hardcoded hex.

[coding-js-patterns] coding-with-builder/js-patterns.md
KEYS: JavaScript, JS patterns, script.js, Splide, Splide v3, slider,
      .align-splide-slider, alignSliderConfigs, data-splide-config,
      slidesPerView, hookNavigator, alignSplideInit, custom event,
      Query Builder, align-qb-container, align-qb-wrap--grid,
      align-qb-pagination, align-qb-search-input,
      qb-load-more-btn, qb-infinite-trigger, IntersectionObserver,
      align_query_builder_load_more, AJAX, admin-ajax.php,
      lazyLoad, lazyLoadInstance, lazyLoadInstance.update,
      blank images after AJAX, data-src, lazy class,
      data-component-shortcode, shortcode container, Builder canvas,
      debounce, static styles_rendered, IIFE, DOMContentLoaded
SUMMARY: All JS patterns from script.js: Splide v3 slider with full config options, QB AJAX
         pagination (load more + infinite scroll + search), mandatory lazyload.update() after
         AJAX inject, shortcode container DOM, alignSplideInit custom event, debounce utility.

[coding-data-query] coding-with-builder/data-query-patterns.md
KEYS: query, database, SQL, wp_posts, post_id, post_name, slug,
      body class, page-id-686, URL to ID, page identity,
      align-builder/v1/page-content, align-builder/v1/save-page,
      WP-CLI, wp post get, wp post list, Local by Flywheel, MySQL socket,
      _wp_page_template, _align_reusable_overrides, _align_lang, post meta,
      locate CSS file, find asset, attachment slug collision, get_page_by_path,
      KSES safe, 3-step debug, DB check, disk check, frontend check
SUMMARY: All data query patterns: URL-to-postID extraction, safe SQL (attachment slug collision
         guard), Builder REST API read/write, WP-CLI wrapper for Local by Flywheel, locating
         physical asset files, post meta keys reference, and 3-step debug process.

[coding-shortcode-reference] coding-with-builder/shortcode-reference.md
KEYS: shortcode, shortcode container, data-component-shortcode,
      reuse-block, reuse_block, align_block, reuse block by ID, reuse block by slug,
      align_shop_page, align_kit_builder, align_builder_highlights, builder_highlights,
      align_garage_blog, fluentcart_product_details,
      align_post_title, align_post_excerpt, align_post_hero_image,
      single post shortcodes, dynamic field shortcodes,
      align_checkout_page, fluentcart_checkout_page,
      align_login_register, align_language_switcher,
      do_shortcode, shortcode_atts, add_shortcode,
      static styles_rendered, ob_start, ob_get_clean, shortcode renderer
SUMMARY: All registered shortcodes with container pattern. Reusable Block (by ID/slug), Builder
         module shortcodes (shop, kit builder, blog highlights, garage blog, product details),
         single post dynamic fields, checkout, login, language switcher. PHP renderer best practices.

---

## CROSS-REFERENCE: COMMON QUERY PATTERNS

| User asks about... | Read these files |
|---|---|
| How to save a page via REST API | builder-guide, builder-deep-dive, ai-rest-api-reference |
| Builder PHP class locations | builder-deep-dive (Quick Location Map) |
| Button/card UI in admin settings | settings-style-guide |
| How site protection works | settings-guide |
| How to deploy/update the theme | git-updater |
| Adding a new language | translate-readme, translate-architecture-routing |
| Translating pages with AI | translate-bulk-ai |
| Hreflang not showing | translate-seo-hreflang |
| AJAX page content not rendering | issues-critical-gotchas |
| Invisible text node / unwanted spacing above navbar / UTF-8 BOM | issues-critical-gotchas |
| Images blank after AJAX load | issues-critical-gotchas, **coding-js-patterns** |
| `<source>` tag breaking in PHP | issues-critical-gotchas, responsive-media-shortcode-guide |
| Drag-and-drop reordering sections | issues-sortablejs-plan |
| Uploading images with wrong filename | module-media-normalizer |
| Checkout cart not working | module-checkout-page |
| Search block configuration | module-live-search |
| Converting HTML to Gutenberg blocks | align-cloud-api-reference |
| Align Studio vs full Builder | align-studio-guide |
| DOM pollution from browser extensions | deterministic-clean-state |
| Whitelisting attributes in serializer | deterministic-clean-state |
| Image shortcode picture structure | responsive-media-shortcode-guide |
| Dynamic block with real WordPress data | dynamic-block-studio-plan, align-studio-guide |
| Security vulnerabilities found | issues-security-fixes |
| Past bug fix reference | issues-resolved (search by grep) |
| **Difference: Page vs Template vs Part vs Reusable Block** | **coding-content-types** |
| **How to embed a Reusable Block shortcode** | **coding-content-types, coding-shortcode-reference** |
| **How to find CSS file for a page** | **coding-asset-system, coding-data-query** |
| **What CSS class to use for hero section** | **coding-css-design-tokens** |
| **What `--color-7` equals** | **coding-css-design-tokens** |
| **How to set up a Splide slider** | **coding-js-patterns** |
| **AJAX pagination pattern** | **coding-js-patterns** |
| **lazyLoadInstance not updating after AJAX** | **coding-js-patterns, issues-critical-gotchas** |
| **How to query a page by slug or ID** | **coding-data-query** |
| **WP-CLI command on Local by Flywheel** | **coding-data-query** |
| **Attachment slug collision bug** | **coding-data-query** |
| **All shortcodes in child theme** | **coding-shortcode-reference** |
| **[align_shop_page] shortcode usage** | **coding-shortcode-reference** |
| **shortcode container pattern in Builder** | **coding-shortcode-reference, coding-content-types** |
| **How to write a PHP shortcode renderer** | **coding-shortcode-reference** |
| **data-component-shortcode attribute** | **coding-shortcode-reference, coding-content-types** |
| **Build a new page from scratch** | **coding-content-types, coding-asset-system, coding-css-design-tokens** |
| **CLI tool for local AI Agent** | **local-agent-cli** |
| **Direct CMS & DB operations by AI** | **local-agent-cli, coding-content-types** |
| **Template revert trap (disk vs DB)** | **issues-bug-logs, local-agent-cli** |
| **Single post template issues / missing banner** | **issues-bug-logs** |
| **Dynamic shortcode discovery for AI** | **local-agent-cli, coding-shortcode-reference** |

---

## RELATED MANIFEST FILES (BUILDER CODE)

For searching builder source code files (JS modules, PHP classes, CSS):
- `align/core/builder/manifest/manifest.jsonl` — JSONL index of all builder source files
- `align/core/builder/MANIFEST.md` — Human-readable builder code architecture overview
- Search: `node align/core/builder/manifest/search.js "YOUR QUERY"`

For the coding-with-builder docs folder specifically:
- `align/docs/coding-with-builder/manifest.json` — JSON manifest for this folder's 7 files

