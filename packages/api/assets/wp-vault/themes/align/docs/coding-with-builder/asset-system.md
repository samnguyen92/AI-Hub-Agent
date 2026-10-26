# Asset System — File-First SOT, Naming & Enqueue

Align Builder content uses a **File-First Single Source of Truth (SOT) persistence model**: all CSS/JS lives cleanly in physical files on disk (`align-child/assets/builder/`), while the WordPress Database (`wp_posts.post_content`) and disk template files (`templates/*.html`, `parts/*.html`) store only semantic HTML markup with block metadata.

---

## 1. Persistence & SOT Architecture

```
┌─────────────────────────────────────────────────────────┐
│  SINGLE SOURCE OF TRUTH (SOT): Physical Files on Disk   │
│  align-child/assets/builder/style-*.css & script-*.js   │
│  → Priority #1 loaded by Visual Builder Canvas          │
│  → Proactively enqueued via <link rel="stylesheet">     │
│  → Git version-controlled, browser & CDN cached         │
└─────────────────────────┬───────────────────────────────┘
                          │ 
┌─────────────────────────▼───────────────────────────────┐
│  DATABASE & DISK TEMPLATES: Pure Clean HTML Markup      │
│  wp_posts.post_content / templates/*.html / parts/*.html │
│  → Zero inline <style> / <script> tags                  │
│  → Block attributes store identifiers:                  │
│    <!-- wp:align/custom-html {"blockId":"...","styleId":"style-page-xxx","scriptId":"script-page-xxx"} -->
└─────────────────────────────────────────────────────────┘
```

### Backward Compatibility (Legacy Sites & Fallback Guard)
For older sites or revisions where `<style>` / `<script>` were previously inlined into DB `post_content`:
1. **Frontend Rendering (`render.php`)**:
   - If physical file exists on disk: enqueues external stylesheet and strips inline `<style>` to prevent duplicate CSS.
   - If physical file does NOT exist on disk: safely **preserves inline `<style>` / `<script>`** so styles never break.
2. **Editor Canvas (`class-builder-content-handler.php`)**:
   - `load_external_css()` loads from physical file first. If missing, automatically falls back to inline `<style>` extracted from `post_content`.
3. **Automatic Migration on Save**:
   - Saving any legacy page/template in Align Builder extracts CSS/JS to disk and cleans `post_content` automatically.

---

## 2. File Naming Convention

The naming pattern directly reflects the content type. Always follow this:

### Pages
```
assets/builder/style-page-{post_name}.css     ← Scoped CSS for page
assets/builder/script-page-{post_name}.js     ← Scoped JS for page
```
Examples from the project:
```
style-page-home.css              (22KB)
style-page-about.css             (21KB)
style-page-contact-us.css        (19KB)
style-page-sitemap.css           (8KB)
style-page-support-and-faqs.css  (12KB)
style-page-login-page.css        (6KB)
style-page-all-categories.css    (12KB)
style-page-interior-kit-builder.css (6KB)
style-page-privacy-policy.css    (7KB)
style-page-return-and-refund-policy.css
style-page-hub-page-search.css
```

### Templates
```
assets/builder/style-template-{slug}.css
assets/builder/script-template-{slug}.js
```
Examples:
```
style-template-home.css          (24KB)  ← Homepage template
style-template-single.css        (8KB)   ← Blog single post template
style-template-index.css         (6KB)   ← General index template
style-template-404.css
style-template-single-fluent-products.css
```

### Template Parts
```
assets/builder/style-part-{slug}.css
assets/builder/script-part-{slug}.js
```
Examples:
```
style-part-header.css            (13KB)  ← Global header styles
style-part-footer.css            (15KB)  ← Global footer styles
script-part-header.js            (7KB)   ← Header interactions
script-part-footer.js            (3.8KB) ← Footer JS + autoDetectActiveNav
```

### Reusable Blocks
```
assets/builder/style-block-{slug}.css
assets/builder/script-block-{slug}.js
```
Examples:
```
style-block-banner-full-top.css  (4.8KB)
style-block-banner-half-top.css  (5.4KB)
style-block-cta-banner.css       (1.9KB)
style-block-newsletter-box.css   (8.3KB)
style-block-float-actions.css    (0.4KB)
```

---

## 3. Source vs Compiled Asset Tiers

The child theme has 3 tiers of CSS organization:

### Tier 1: Root Design Tokens (always loaded globally)
```
assets/root.css                          ← :root variables (colors, spacing, typography, grid)
assets/bootstrap-reboot.min.css          ← CSS reset
assets/bootstrap-utilities.min.css       ← Bootstrap utility classes
```

### Tier 2: General Styles (always loaded globally)
```
assets/src/general/css/custom.css        ← Source
assets/dist/general/css/custom.min.css   ← Minified build (served on frontend)
assets/custom-live-search.css            ← Live search global overrides
```

### Tier 3: Conditional CSS (Shared Section Classes)
Pre-built CSS for commonly reused section patterns. Reference these classes in page HTML instead of re-writing the same CSS:

```
assets/src/general/conditional-css/banner.css       ← Full-height hero banner (Reuse Block 27)
assets/src/general/conditional-css/banner-half.css  ← Half-height hero banner (Reuse Block 85)
assets/src/general/conditional-css/cta.css          ← CTA section (Reuse Block 32)
assets/src/general/conditional-css/newsletter.css   ← Newsletter section (Reuse Block 34)
```

### Tier 4: Template-specific Conditional CSS (loaded per template)
```
assets/src/template/{template-name}/css/important.css
```
Examples:
```
assets/src/template/template-policy/css/important.css
assets/src/template/template-homepage/css/important.css
assets/src/template/template-contact/css/important.css
```
These are enqueued only when the matching template is active.

### Tier 5: Page / Part / Block Scoped CSS (loaded per page)
```
assets/builder/style-page-{slug}.css     ← from Tier 2 naming above
```

---

## 4. How TemplateAssetsLoader Enqueues Files

The `TemplateAssetsLoader` (`core/inc/template-assets-loader.php`) automatically detects what is loaded on the current page and enqueues only the needed files:

```
Current page: /about/  (post_id = 123, post_name = "about")
  → Loads root.css (global)
  → Loads style-part-header.css + script-part-header.js (template part)
  → Loads style-part-footer.css + script-part-footer.js (template part)
  → Loads style-template-page.css (if custom template assigned)
  → Loads style-page-about.css + script-page-about.js (page-specific)
  → Renders inline <style id="vvvebjs-styles"> from post_content (fallback)
```

---

## 5. Locating Assets When Given a Page ID or Slug

### Step 1: Resolve slug from post ID
```sql
SELECT post_name, post_title FROM wp_posts WHERE ID = 686;
-- Returns: post_name = "sitemap"
```

### Step 2: Find physical CSS/JS files
```bash
# Find page assets
ls align-child/assets/builder/ | grep "style-page-sitemap"
# → style-page-sitemap.css

# Find by ID pattern (some pages use ID-based naming)
ls align-child/assets/builder/ | grep "*686*"
```

### Step 3: Check for custom template assignment
```sql
SELECT meta_value FROM wp_postmeta
WHERE post_id = 686 AND meta_key = '_wp_page_template';
-- 'default' means uses templates/page.html
-- 'template-policy.html' means specific template
```

### Step 4: Locate template-specific conditional CSS
```
If template = 'template-policy.html':
  Check: assets/src/template/template-policy/css/important.css
```

---

## 6. Writing New Page Assets — Minimal Template

When creating assets for a new page (slug `my-new-page`):

### `style-page-my-new-page.css`
```css
/* Page: My New Page
   Slug: my-new-page
   Post ID: (assign after creation)
   -------------------------------------------------- */

/* Section 1: Hero */
.my-new-page-hero {
  background-color: var(--color-10);
  padding: var(--sp-xl) 0;
}

/* Section 2: Content */
.my-new-page-content {
  color: var(--color-2);
  font-size: var(--fs-body);
  line-height: var(--lh-relaxed);
}

@media (max-width: 992px) {
  .my-new-page-hero {
    padding: var(--sp-lg) 0;
  }
}

@media (max-width: 490px) {
  .my-new-page-hero {
    padding: var(--sp-md) 0;
  }
}
```

### `script-page-my-new-page.js`
```javascript
/* Page: My New Page — Scoped JS */
(function() {
    'use strict';
    function init() {
        console.log('[Align MyNewPage] Initialized');
        // Page-specific interactions here
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

### `post_content` structure in DB (File-First Single Source of Truth)
```html
<!-- wp:align/custom-html {"blockId":"align-abcd1234","enableShortcode":true,"styleId":"style-page-my-new-page","scriptId":"script-page-my-new-page"} -->
<main>
  <section class="my-new-page-hero">
    <div class="container-1440 spacing-top-bottom">
      <h1>My New Page</h1>
    </div>
  </section>
</main>
<!-- /wp:align/custom-html -->
```

---

## 7. Asset Writing API: Destructive vs Non-Destructive Operations

The asset subsystem enforces a strict architectural boundary between **explicit user intent** and **content synchronization / migration**:

### 7.1. Destructive API: `Align_Builder_Assets::save_external_assets()`
- **Purpose**: Explicit-intent saves initiated by the Visual Builder editor or direct API calls with complete asset payloads.
- **Behavior**:
  - Non-empty CSS/JS strings are minified and written atomically to disk files.
  - Empty strings (`''`) signify that the user deliberately cleared styling/scripting; the file on disk is removed via `unlink()`.
  - Prior to any overwrite or file removal, an automated history snapshot is preserved in `.history/`.

### 7.2. Non-Destructive API: `Align_Builder_Assets::sync_external_assets()`
- **Purpose**: Safe synchronization from content parsers (`sync_post_assets_from_content()`, `sync_template_assets_from_content()`), Gutenberg `save_post` hooks, and HTML-only Agent patch tools.
- **Behavior**:
  - Non-empty CSS/JS strings are saved atomically to disk.
  - Empty strings (`''`) **NEVER delete existing physical files**. The operation is safely skipped with log `[Align Assets] Skip delete (non-destructive sync): <file>`.
  - Prevents File-First clean HTML payloads (which lack inline `<style>` tags) from accidentally wiping external stylesheets.

### 7.3. Snapshot History & Recovery (`.history/`)
- All asset files managed by `Align_Builder_Asset_Writer` and `Align_Builder_Asset_Cleaner` automatically create pre-mutation snapshots via `Align_Builder_Asset_History::snapshot($file_path, $reason)`.
- Snapshots are stored in `assets/builder/.history/<basename>.<unix_ts>.<ext>`:
  - Protected with Apache `.htaccess` (`Deny from all`) and `index.php` silence stub.
  - Automatic retention pruning retains the newest **5 snapshots** per asset file (`Align_Builder_Asset_History::prune()`).
  - Restoration is available via PHP API `Align_Builder_Asset_History::restore($basename, $timestamp)` or Align Agent tool `restore_asset_history`.

### 7.4. Rules for Align Agent and Custom Integrations
1. **Explicit CSS/JS updates**: When the Agent or REST client supplies explicit `css` or `js` parameter fields, use `save_external_assets()` to respect intentional clearing.
2. **HTML-only updates (`patch_content` with field `html`)**: Always load the current disk asset content or pass empty parameters through `sync_external_assets()`. Never call `save_external_assets()` with empty strings on partial HTML updates.
