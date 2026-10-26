---
name: align-builder
description: Fix, edit, build layouts, and integrate dynamic shortcodes in Theme Align and Align Builder. Use when user asks to fix/debug an existing page, sends a page URL or Page ID, asks to build a new page from a mockup, or asks to integrate dynamic shortcodes with dual-storage persistence.
---

# Align Builder Engineering Skill

Autonomous workflow for dynamic asset discovery, dual-storage persistence, shortcode integration, and layout implementation in WordPress Align Theme.

---

## 🧭 4 Core Operating Scenarios

### 🅰️ Scenario 1: User Sends a Page URL
*Example: User provides `https://palmeto.test/sitemap/` or `?page=align-builder&post_id=686`*

1. **DOM & Identity Extraction**:
   - Fetch URL using `read_url_content` or `curl`.
   - Inspect `<body>` class list:
     - `page-id-{ID}` or `postid-{ID}` -> **Target Post ID**.
     - `page-template-{slug}` or `template-{slug}` -> **Template Slug**.
   - If Builder URL: extract `post_id` or `tp_id` parameter directly.
2. **Dynamic Asset Trace**:
   - Inspect `<link rel="stylesheet">` tags in `<head>` to see which files are enqueued.
   - Match slug or ID against `assets/builder/` using glob `*style*{slug}*` or `*style*{id}*`.
   - Inspect DB `wp_posts` (`post_content`) for inline `<style id="vvvebjs-styles">` or `<style id="style-page-*">`.
3. **Execute Edits via Dual-Storage**:
   - Update both: (a) Database `post_content` inside `align/custom-html` and (b) physical file in `assets/builder/` to keep them in 100% sync.

---

### 🅱️ Scenario 2: User Sends a Page ID
*Example: User provides `Page ID: 686` or `tp_id: tpl_...`*

1. **Locate Post Record**:
   - Query DB `wp_posts` for `ID = {post_id}` (or via WP-CLI / REST API `/align-builder/v1/page-content?id={post_id}`).
   - Retrieve `post_name` (slug), `post_title`, and `_wp_page_template` post meta.
2. **Locate Physical Asset Files**:
   - Glob `assets/builder/` for `*style*${slug}*.css` and `*script*${slug}*.js`.
   - If a custom template is assigned (`_wp_page_template` != `default`), check `assets/**/template/{template_slug}/css/important.css`.
3. **Read Existing Content & Modify**:
   - Parse existing HTML/CSS from `post_content`.
   - Make precise edits, maintaining the `<!-- wp:align/custom-html -->` wrapper and scoped `<style>` block.
   - Save back to DB and update the matching file in `assets/builder/`.

---

### 🆎 Scenario 3: Build NEW Page from Design Mockup / Image
*Example: User asks to create a new page based on an image, Figma link, or layout description*

1. **Step 1: Reconnaissance of Existing Assets (NEVER reinvent the wheel)**:
   - **Root Tokens**: Scan `assets/**/root*.css` (grep `:root\s*\{`) to extract available colors (`--color-1` to `--color-10`), font families (`--font-family-1..2`), responsive font sizes (`--fs-*`), and spacing scale (`--sp-*`).
   - **Framework & Layout**: Reuse `.container-1440` (with 72/40/20px padding), `.spacing-top-bottom`, and Bootstrap 5 grid (`.row`, `.col-*`, `.g-0..5`).
   - **Modular Sections (`conditional-css/`)**: Check `assets/**/conditional-css/` for existing section styles (`banner.css`, `banner-half.css`, `cta.css`, `newsletter.css`). If the design calls for a Hero Banner or Newsletter Box, **reuse these existing styles directly** instead of writing new CSS!
   - **Reusable Blocks**: Check if common sections (Header Banner, Footer, Floating Actions, Newsletter) already exist as `wp_block` via `[reuse-block id="..."]` or `[reuse-block slug="..."]`.
2. **Step 2: Generate Scoped Custom CSS (Only for Truly New Elements)**:
   - For novel components not covered by existing tokens/classes, write clean scoped CSS.
   - Use CSS variables (`var(--color-*)`, `var(--sp-*)`) exclusively—**zero hardcoded hex codes or arbitrary pixel margins**.
3. **Step 3: Dual-Storage Registration**:
   - Create post in `wp_posts` (`post_type = 'page'`, status = `publish`).
   - Encapsulate content inside `align/custom-html` and save compiled static CSS to `assets/builder/style-page-{slug}.css`.

---

### 🅾️ Scenario 4: Shortcode & Dynamic Component Integration
*Example: User asks to replace a static layout section with a dynamic shortcode (e.g. `[align_builder_highlights]`)*

1. **Align Builder Shortcode Component Schema**:
   - Align Builder represents shortcode blocks in the DOM canvas with specific data attributes:
     ```html
     <div data-component-shortcode="" data-shortcode="[your_shortcode]">[your_shortcode]</div>
     ```
   - **Rule**: Never insert raw `[shortcode]` text into `post_content` without the `data-component-shortcode` container. The container enables the Align Builder canvas to recognize, preview, and manipulate the block.
2. **Frontend Evaluation**:
   - When WordPress serves the page, `wp:post-content` applies `the_content` filter, which automatically executes `do_shortcode()` on the inner tag.
3. **Self-Contained Fallback CSS**:
   - Ensure the shortcode renderer outputs its own scoped styles (e.g. `<style id="align-*-css">`) guarded by a `static $rendered` flag so the component renders beautifully anywhere, even if the target page does not enqueue the specific builder stylesheet.

---

## ⚡ Local by Flywheel Execution Quick-Reference

When running CLI commands on macOS Local by Flywheel, use the dedicated PHP binary and MySQL socket:

```bash
/Volumes/JoeSSD/SymLinks/Local_System/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php \
  -d "mysqli.default_socket=/Users/joe/Library/Application Support/Local/run/33aX1oovp/mysql/mysqld.sock" \
  /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp \
  --path=/Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto/app/public {COMMAND}
```

---

## ⚠️ WordPress Attachment Slug Collision Guard

- **Danger**: `get_page_by_path($slug, OBJECT, 'post')` matches attachments if an uploaded image shares the same slug. Using `wp_update_post()` on the resulting object will accidentally mutate the media library entry into a post!
- **Safe Practice**: Always query explicitly with post type verification:
  ```php
  global $wpdb;
  $post_id = $wpdb->get_var($wpdb->prepare(
      "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'post' LIMIT 1",
      $slug
  ));
  ```

---

## 📸 Headless Chrome & DevTools Protocol Screenshotting

When verifying page elements below the fold via Chrome DevTools Protocol (`Page.captureScreenshot`):
1. **Viewport & Document Offset**:
   - Calculate exact document position: `y = rect.y + window.scrollY`.
   - Pass `captureBeyondViewport: true` inside `clip` options to prevent blank white screenshot output.
2. **Desktop Browser Verification**:
   - Run `open http://palmetto.local/` to launch the site directly in the user's macOS browser.

---

## 🛡️ Coding & Observability Standards

- **File size**: Maintain < 500 lines per file.
- **Language**: 100% English comments and logs (`error_log('[Align Builder] ...')`, `console.log('[AlignSerializer] ...')`).
- **KSES Bypass Guard**: For pages with custom `<style>`, ensure DB updates bypass WordPress KSES strip filters (use direct `$wpdb` update or standard builder REST endpoint `/save-page`).
- **Clean State**: Strip `data-align-preview`, `data-preview-loading`, `bis_skin_checked` prior to persistence.
