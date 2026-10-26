# Shortcode Reference

All registered shortcodes in Align Theme and Align Child Theme. Includes the shortcode container pattern required when embedding inside Builder pages.

---

## CRITICAL: Shortcode Container Pattern in Builder

**Every shortcode inside a Builder page's `post_content` MUST be wrapped with this container:**

```html
<div data-component-shortcode="" data-shortcode="[your_shortcode attr='value']">
  [your_shortcode attr='value']
</div>
```

**Why:** The Builder canvas recognizes `data-component-shortcode` to:
1. Identify the block as a shortcode component (not raw HTML)
2. Render a live server-side preview inside the iframe editor
3. Expose the shortcode attributes in the Inspector panel

**On the frontend:** `wp:post-content` triggers `the_content` → `do_shortcode()` executes the inner text → shortcode renders normally.

**Shortcode container combined with style:**
```html
<style id="vvvebjs-styles">/* page CSS */</style>

<div data-component-shortcode="" data-shortcode="[reuse-block id='611']">
  [reuse-block id='611']
</div>

<section class="my-section container-1440">
  <div data-component-shortcode="" data-shortcode="[align_shop_page]">
    [align_shop_page]
  </div>
</section>
```

---

## 1. Reusable Block Shortcodes

Embed a `wp_block` (Synced Pattern / Reusable Block) anywhere on a page.

### All equivalent aliases:
```
[reuse-block id="N"]
[reuse_block id="N"]
[align_block id="N"]
[align_reusable_block id="N"]
[reusable_block id="N"]
```

### Usage by ID
```html
<div data-component-shortcode="" data-shortcode="[reuse-block id='611']">
  [reuse-block id='611']
</div>
```

### Usage by slug
```html
<div data-component-shortcode="" data-shortcode="[reuse-block slug='newsletter-box']">
  [reuse-block slug='newsletter-box']
</div>
```

### Known Reusable Blocks (palmetto-stg)
| ID | slug | Description |
|:---|:---|:---|
| 27 | (hero-banner) | Full-height homepage hero banner |
| 32 | cta-banner | Custom Quote CTA section |
| 34 | newsletter-box | Newsletter signup section |
| 85 | (banner-half) | Half-height hero banner |
| 611 | banner-half-top | Hero banner for inner pages |
| — | float-actions | Floating action buttons (fixed bottom-right) |

---

## 2. Builder Module Shortcodes (align-child/inc/)

These shortcodes are registered by PHP modules in `align-child/inc/`. Each has a self-contained inline `<style>` (rendered once via `static $rendered` guard).

### Shop Page
```
[align_shop_page]
```
- Renders: Asymmetric 3-column product grid with Model tabs, responsive `<picture>` cards, on-demand cropping, Pretty URLs (`/shop/{category}/`)
- Source: `align-child/inc/shop-page/`
- Docs: `align-child/inc/shop-page/docs/shop-page.md`

### Interior Kit Builder
```
[align_kit_builder]
```
- Renders: Multi-step vehicle interior configurator (7-step adaptive or 2-step custom workflow)
- Source: `align-child/inc/kit-builder/`
- Docs: `align-child/inc/kit-builder/docs/kit-builder.md`

### Builder Highlights (Blog Posts)
```
[align_builder_highlights]
[builder_highlights]
```
Attributes:
```
[align_builder_highlights layout="spotlight" category="builder-highlights" limit="6" orderby="date" order="DESC"]
[align_builder_highlights layout="grid" category="builder-highlights" limit="9"]
```
- `layout`: `"spotlight"` (featured large first) | `"grid"` (uniform grid)
- `category`: WordPress category slug
- `limit`: number of posts
- Source: `align-child/inc/builder-highlights/`

### Garage Blog (Archive + AJAX tabs)
```
[align_garage_blog]
[garage_blog]
```
- Renders: Dynamic blog catalog with Pretty Category URLs (`/the-garage-blog/{slug}/`), AJAX tab switching, View More pagination
- Source: `align-child/inc/garage-blog/`
- Docs: `align-child/inc/garage-blog/docs/garage-blog.md`

---

## 3. Product Page Shortcodes

### FluentCart Product Details
```
[fluentcart_product_details]
```
- Renders: Dynamic product configurator page with cascading bundle trees, scrollable option grids, sticky gallery, fixed bottom CTA bar
- Source: `align-child/inc/details-product-page/`
- Docs: `align-child/inc/details-product-page/docs/product-details.md`

---

## 4. Single Post Dynamic Shortcodes

Used inside the single blog post template (`templates/single.html`) for dynamic field injection. These pull data from the current queried post.

```
[align_post_title]          ← Post title (h1)
[align_post_excerpt]        ← Post excerpt (p)
[align_post_hero_image]     ← Featured image with responsive <picture>
[align_post_author]         ← Author display name
[align_post_date]           ← Publication date (formatted)
[align_post_categories]     ← Category links
[align_post_tags]           ← Tag links
[align_post_reading_time]   ← Estimated read time (e.g. "5 min read")
```

Source: `align-child/inc/single-post/`

---

## 5. E-Commerce Shortcodes

### Checkout Page
```
[align_checkout_page]
[align_checkout]
[fluentcart_checkout_page]
```
All three aliases render the full checkout experience with AJAX cart operations, login drawer, and FluentCart integration.
- Source: `align-child/inc/checkout-page/`
- Docs: `align/docs/modules/checkout-page.md`

### Login / Register
```
[align_login_register]
```
- Renders: Login/registration form with social login options and user dropdown
- Source: `align-child/inc/login-page/class-login-page-shortcode.php`

---

## 6. Multilingual Shortcodes

### Language Switcher
```
[align_language_switcher]
```
- Renders: Language toggle buttons (flags + language code)
- Can be placed anywhere on page or in header/footer parts
- Docs: `align/docs/translate/seo-and-hreflang.md`

---

## 7. Core Align Theme Shortcodes

### Responsive Image (from Align Core)
```
[align_responsive_image id="123" size="large" alt="Description"]
```
Renders a full `<picture>` element with mobile/tablet/desktop sources.
- Docs: `align/docs/builder/responsive-media-shortcode-guide.md`

### Live Search
```
<!-- Used as Gutenberg block, not a traditional shortcode -->
<!-- wp:align/live-search {"postsPerPage":10,"selectedPostTypes":["post","page"]} /-->
```
- Docs: `align/docs/modules/live-search.md`

---

## 8. Custom Align Cloud Shortcodes (via REST Callbacks)

These shortcodes call `convertb.alignvn.cloud` for server-side processing:

```
[align_convert_html content="<div>...</div>"]     ← Convert HTML to Gutenberg blocks
[align_minify_css content="..."]                  ← Minify CSS via cloud API
```
- Docs: `align/docs/api/align-cloud-api-reference.md`

---

## 9. Quick Reference Table

| Shortcode | Source | What it renders |
|:---|:---|:---|
| `[reuse-block id="N"]` | Core Align | Reusable Block by ID |
| `[reuse-block slug="name"]` | Core Align | Reusable Block by slug |
| `[align_shop_page]` | inc/shop-page | Shop product grid |
| `[align_kit_builder]` | inc/kit-builder | Interior kit configurator |
| `[align_builder_highlights]` | inc/builder-highlights | Blog post grid |
| `[align_garage_blog]` | inc/garage-blog | Blog with category tabs |
| `[fluentcart_product_details]` | inc/details-product-page | Product detail page |
| `[align_post_title]` | inc/single-post | Current post title |
| `[align_post_hero_image]` | inc/single-post | Current post featured image |
| `[align_checkout_page]` | inc/checkout-page | Checkout form |
| `[align_login_register]` | inc/login-page | Login / Register form |
| `[align_language_switcher]` | Core Align | Language toggle |

---

## 10. Shortcode Renderer PHP Best Practices

```php
function align_my_shortcode( $atts ) {
    // 1. Parse attributes with defaults
    $atts = shortcode_atts(
        [
            'layout'   => 'grid',
            'limit'    => 6,
            'category' => '',
        ],
        $atts,
        'align_my_shortcode'
    );

    // 2. Log execution (structured logging, English only)
    error_log( '[Align MyShortcode] Rendering: layout=' . $atts['layout'] . ' limit=' . $atts['limit'] );

    // 3. Self-contained inline styles (rendered once per page)
    static $styles_rendered = false;
    ob_start();
    if ( ! $styles_rendered ) {
        $styles_rendered = true;
        ?>
        <style id="align-my-shortcode-css">
            .my-shortcode-wrap { background: var(--color-10); }
            .my-shortcode-grid { display: grid; gap: var(--sp-sm); }
        </style>
        <?php
    }

    // 4. Query data
    $query = new WP_Query( [
        'post_type'      => 'post',
        'posts_per_page' => (int) $atts['limit'],
        'category_name'  => sanitize_text_field( $atts['category'] ),
        'post_status'    => 'publish',
    ] );

    // 5. Render
    ?>
    <div class="my-shortcode-wrap">
        <div class="my-shortcode-grid">
            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                <article>
                    <h3><?php the_title(); ?></h3>
                </article>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'align_my_shortcode', 'align_my_shortcode' );
```
