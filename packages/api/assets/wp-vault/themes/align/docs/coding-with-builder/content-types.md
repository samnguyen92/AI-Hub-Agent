# Content Types in Align Builder

Understanding the 4 content types is the most critical prerequisite before coding anything in Align Builder. Each type has a different storage mechanism, ID format, asset naming, and embedding method.

---

## Overview: 4 Content Types

| Type | WordPress Object | Builder ID Param | Asset Naming | How to Embed |
|:---|:---|:---|:---|:---|
| **Page** | `wp_posts` (`post_type = page`) | `post_id` (numeric, e.g. `686`) | `style-page-{slug}.css` | Accessed directly via URL |
| **Template** | FSE `wp_template` | `tpl_id` (e.g. `tpl_home`) | `style-template-{slug}.css` | Applied automatically by WordPress to matching post types |
| **Template Part** | FSE `wp_template_part` | `tp_id` (e.g. `tp_header`) | `style-part-{slug}.css` | `<!-- wp:template-part {"slug":"header"} /-->` in `.html` files |
| **Reusable Block** | `wp_posts` (`post_type = wp_block`) | `post_id` (numeric, e.g. `611`) | `style-block-{slug}.css` | `[reuse-block id="N"]` or `[reuse-block slug="..."]` |

---

## 1. Page (WordPress Page)

A standard WordPress page with content authored in Align Visual Builder.

### Storage
- **Database**: `wp_posts` table, `post_type = 'page'`
- **Content format**: Wrapped in `<!-- wp:align/custom-html -->` block
- **Physical assets**: `align-child/assets/builder/style-page-{slug}.css` and `script-page-{slug}.js`

### Content structure in `post_content`
```html
<!-- wp:align/custom-html {"blockId":"align-xxxx","enableShortcode":true} -->
<style id="vvvebjs-styles">/* or: id="style-page-{slug}" */
  /* Scoped page CSS — synced with assets/builder/style-page-{slug}.css */
</style>

<div data-component-shortcode="" data-shortcode="[reuse-block id='611']">
  [reuse-block id='611']
</div>

<main>
  <!-- Page HTML body content -->
</main>

<script id="vvvebjs-scripts">
  /* or: id="script-page-{slug}" */
  (function(){ /* Scoped page JS */ })();
</script>
<!-- /wp:align/custom-html -->
```

### Builder URL to open page in editor
```
/wp-admin/admin.php?page=align-builder&post_id=686
```

### How to identify from a page URL
```bash
# Extract body class to find page-id-{N}
curl -s "https://yoursite.test/my-page/" | grep -o 'class="[^"]*page-id-[0-9]*[^"]*"'
# Returns: class="page-id-686 ..."  → Post ID = 686
```

---

## 2. Template (FSE Block Template)

A Full Site Editing template applied globally to one or more post types. Lives in `align-child/templates/*.html`.

### Storage
- **File system**: `align-child/templates/{slug}.html` (e.g. `home.html`, `single.html`, `page.html`)
- **Physical assets**: `align-child/assets/builder/style-template-{slug}.css` and `script-template-{slug}.js`

### Available templates (align-child/templates/)
```
404.html                        → 404 error page template
archive-fluent-products.html    → FluentCart product archive
archive.html                    → General archive (posts)
home.html                       → Homepage (overrides front page)
index.html                      → Default fallback template
page.html                       → Default page template (3 lines: header + post-content + footer)
search.html                     → Search results page
single-fluent-products.html     → Single FluentCart product detail
single.html                     → Single blog post template
taxonomy-product-categories.html → Product category taxonomy archive
```

### Default page template structure (`templates/page.html`)
```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:post-content {"layout":{"type":"constrained"}} /-->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

> [!IMPORTANT]
> Templates MUST contain `<!-- wp:post-content ... /-->` (or PHP `the_content()`). **Never hardcode page content inside a template file** — it will override the CMS-authored content and break Align Builder editing.

### Builder URL to edit a template
```
/wp-admin/admin.php?page=align-builder&tpl_id=tpl_home
```

---

## 3. Template Part (Header / Footer)

Reusable layout components (Header, Footer) shared across templates. Lives in `align-child/parts/*.html`.

### Storage
- **File system**: `align-child/parts/{slug}.html` (only `header.html` and `footer.html` currently)
- **Physical assets**: `align-child/assets/builder/style-part-{slug}.css` and `script-part-{slug}.js`

### How to embed in a template
```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->
<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

### Builder URL to edit a template part
```
/wp-admin/admin.php?page=align-builder&tp_id=tp_header
/wp-admin/admin.php?page=align-builder&tp_id=tp_footer
```

### Part assets
| Part | CSS File | JS File |
|:---|:---|:---|
| Header | `assets/builder/style-part-header.css` (13KB) | `assets/builder/script-part-header.js` (7KB) |
| Footer | `assets/builder/style-part-footer.css` (15KB) | `assets/builder/script-part-footer.js` (3.8KB) |

---

## 4. Reusable Block (wp_block / Synced Pattern)

A self-contained HTML section with its own scoped CSS and JS, stored as a WordPress `wp_block` post. Used for sections that repeat across multiple pages (Hero Banner, CTA, Newsletter, Footer Banner, Floating Actions).

### Storage
- **Database**: `wp_posts` table, `post_type = 'wp_block'`
- **Physical assets**: `align-child/assets/builder/style-block-{slug}.css` and `script-block-{slug}.js`

### Known Reusable Blocks (palmetto-stg)
| Block ID | Slug | Description | Asset File |
|:---|:---|:---|:---|
| 27 | banner (hero full) | Full-height homepage hero banner | `style-block-banner-full-top.css` |
| 32 | cta-banner | Custom Quote CTA section | `style-block-cta-banner.css` |
| 34 | newsletter-box | Newsletter signup section | `style-block-newsletter-box.css` |
| 85 | banner-half | Half-height hero banner (inner pages) | `style-block-banner-half-top.css` |
| 611 | banner-half-top | Banner Half Top used in many pages | `style-block-banner-half-top.css` |
| — | float-actions | Floating action buttons | `style-block-float-actions.css` |

### How to embed a Reusable Block in page content

**Method 1: Shortcode** (renders on frontend):
```
[reuse-block id="611"]
[reuse-block slug="newsletter-box"]
[reuse-block id="34"]
```

Shortcode aliases (all equivalent):
```
[reuse-block id="N"]
[reuse_block id="N"]
[align_block id="N"]
[align_reusable_block id="N"]
[reusable_block id="N"]
```

**Method 2: Shortcode Container in Builder Canvas** (MANDATORY format when inside `post_content`):
```html
<div data-component-shortcode="" data-shortcode="[reuse-block id='611']">
  [reuse-block id='611']
</div>
```

> [!IMPORTANT]
> Never insert a raw `[reuse-block ...]` text directly into `post_content` without the `data-component-shortcode` wrapper. The wrapper enables Align Builder to:
> - Identify the element as a shortcode component on the canvas
> - Render a live server-side preview inside the iframe
> - Expose shortcode attribute controls in the right Inspector panel

### Reusable Block with Per-Page Overrides

The `_align_reusable_overrides` post meta allows overriding specific text/image tokens of a shared Reusable Block without duplicating it:

```php
// Post meta key: _align_reusable_overrides
// Value: JSON array of override objects
[
  {"id": "ov-5538jp73", "value": "CUSTOM PAGE TITLE"},
  {"id": "ov-16ev5yt6", "value": "Custom description text"},
  {"id": "ov-w37kscz3", "value": "https://yoursite.com/wp-content/uploads/image.webp"}
]
```

Access via REST: `GET /wp-json/align-reuse/v1/fields?post_id={page_id}`

---

## 5. CSS & JS State: Independent Per Content Type

Each content type stores its CSS and JS **completely independently** — zero sharing between types:

```
Page 686:
  DB: <style id="vvvebjs-styles"> or <style id="style-page-sitemap">
  File: assets/builder/style-page-sitemap.css

Template "home":
  DB: (inline in home.html FSE template)
  File: assets/builder/style-template-home.css

Template Part "header":
  DB: (inline in parts/header.html)
  File: assets/builder/style-part-header.css

Reusable Block 85:
  DB: post_content of wp_block ID 85
  File: assets/builder/style-block-banner-half-top.css
```

This 4-way isolation **eliminates CSS leakage** between pages and prevents ID conflicts.

---

## 6. KSES Bypass — Preserving `<style>` Tags

WordPress KSES filter strips `<style>` and `<script>` tags from `post_content` when saved via `wp_update_post()` unless the current user has `unfiltered_html` capability.

### Safe pattern (always use this):
```php
global $wpdb;
$wpdb->update(
    $wpdb->posts,
    [ 'post_content' => $post_content ],
    [ 'ID' => $post_id ],
    [ '%s' ],
    [ '%d' ]
);
clean_post_cache( $post_id );
```

### Alternative: Use Align Builder REST endpoint
```
POST /wp-json/align-builder/v1/save-page
{
  "post_id": 686,
  "html": "<style>...</style><main>...</main>",
  "css": "/* scoped css */",
  "js": "/* scoped js */"
}
```
The Builder REST endpoint automatically handles KSES bypass and dual-storage sync.
