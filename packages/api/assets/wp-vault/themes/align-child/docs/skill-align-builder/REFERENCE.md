# Align Builder Architecture, Heuristics & Implementation Reference

Detailed technical reference for dynamic discovery, dual-storage persistence, and the 4 user scenarios.

---

## 1. The 4 Operational Scenarios: Deep Technical Walkthrough

### 🅰️ Scenario 1: User Sends a Page URL
When user provides `https://palmeto.test/my-page/`:
1. **Analyze Page Identity**:
   ```bash
   # Extract body classes to identify Post ID and Template Slug
   curl -s "https://palmeto.test/my-page/" | grep -o '<body[^>]*class="[^"]*"'
   ```
   - Look for `page-id-123` -> Post ID is `123`.
   - Look for `page-template-template-xyz` -> Template is `template-xyz`.
2. **Trace Active Stylesheets**:
   - Grep `<head>` for `<link rel="stylesheet"` or `<style id="...">`:
     - If `assets/builder/style-page-{slug}.css` is present -> Page has static scoped assets.
     - If `important.css` is present -> Template asset loaded.
3. **Inspect DB Content**:
   - Read `post_content` from `wp_posts` for that ID.
   - Parse `<!-- wp:align/custom-html -->` block.

---

### 🅱️ Scenario 2: User Sends a Page ID
When user provides `Page ID: 123`:
1. **Fetch Post Meta & Slug**:
   ```sql
   SELECT ID, post_name, post_title, post_content, post_status 
   FROM wp_posts 
   WHERE ID = 123;
   ```
2. **Locate Physical Files in `assets/builder/`**:
   - Search for `*style-page-*{post_name}*.css` or `*style-page-*{ID}*.css`.
   - If template != 'default', locate `assets/**/template/{template_slug}/css/important.css`.
3. **Perform Synchronized Dual-Write**:
   - Update `post_content` in DB.
   - Update file in `assets/builder/style-page-{slug}.css`.

---

### 🆎 Scenario 3: Build NEW Page from Design Mockup / Image
When user asks to build a new page (e.g. from an image, Figma design, or section brief):

#### Step 1: Design Reconnaissance & Existing Assets Audit
1. **Root Design Tokens (`root.css`)**:
   - Grep `:root` in `assets/` to read the active color palette (`--color-1` to `--color-10`), typography scale (`--fs-h1` to `--fs-h6`, `--fs-body`, `--fs-eyebrow`), and spacing units (`--sp-3xl` down to `--sp-4xs`).
2. **Standard Layout**:
   - Wrap page content in `.container-1440` (max-width 1440px, auto centered, with 72px desktop / 40px tablet / 20px mobile padding).
   - Use `.spacing-top-bottom` for section vertical padding (80px desktop / 40px mobile).
   - Use Bootstrap 5 grid: `<div class="row g-4"> <div class="col-lg-6">...</div> </div>`.
3. **Check Existing Conditional CSS (`assets/**/conditional-css/`)**:
   - Banner Half Top? -> Use classes from `banner-half.css`.
   - CTA Section? -> Use classes from `cta.css`.
   - Newsletter Box? -> Use classes from `newsletter.css`.
4. **Check Existing Reusable Blocks (`wp_block`)**:
   - If common parts exist, use shortcode `[reuse-block slug="..."]` or `[reuse-block id="..."]`.

#### Step 2: Write Scoped Custom CSS (Zero Redundancy)
- Only write CSS for layout elements that are truly unique to this new page.
- Exclusively use variables: `background-color: var(--color-2);`, `padding: var(--sp-md) var(--sp-base);`.

#### Step 3: Dual-Storage Implementation
1. **Create the WordPress Page**:
   - Insert into `wp_posts` (`post_type = 'page'`, status = `publish`).
   - Wrap in `<!-- wp:align/custom-html -->` with scoped `<style id="vvvebjs-styles">`.
2. **Save Physical Files on Disk**:
   - Write `assets/builder/style-page-{slug}.css` and `assets/builder/script-page-{slug}.js`.

---

### 🅾️ Scenario 4: Shortcode & Dynamic Component Integration
When replacing a static HTML section with a dynamic PHP shortcode:

#### 1. Align Builder Component Container
Align Builder wraps shortcode elements with a specific data-attribute container:
```html
<div data-component-shortcode="" data-shortcode="[your_shortcode]">[your_shortcode]</div>
```
- **Why**: Allows the drag-and-drop builder canvas to identify the element as a shortcode component, render live server previews, and expose shortcode attribute controls in the right-side inspector.
- **Frontend execution**: In block templates, `wp:post-content` triggers the `the_content` filter, automatically running `do_shortcode()` to render the output HTML.

#### 2. Shortcode Renderer Best Practices
- **Self-Contained Styles**: Render an inline `<style id="align-{name}-css">` block on first execution (using a `static $styles_rendered` guard flag) so the shortcode renders identically across any page or builder template.
- **Param Parsing**: Always use `shortcode_atts()` to support customizable layout (`spotlight` vs `grid`), category filtering, post limits, and ordering.
- **Structured Logging**: Log execution via `error_log('[Align {Module}] ...')`.

---

## 2. Local by Flywheel WP-CLI Wrapper

On Local by Flywheel environments, the MySQL socket must be provided explicitly:

```bash
/Volumes/JoeSSD/SymLinks/Local_System/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php \
  -d "mysqli.default_socket=/Users/joe/Library/Application Support/Local/run/33aX1oovp/mysql/mysqld.sock" \
  /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp \
  --path=/Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto/app/public {COMMAND}
```

---

## 3. WordPress Attachment Slug Collision Guard

When creating or querying posts by slug:
- **Risk**: `get_page_by_path($slug, OBJECT, 'post')` can return media attachments sharing the same slug name.
- **Safe Pattern**: Always verify `post_type = 'post'`:
```php
global $wpdb;
$post_id = $wpdb->get_var( $wpdb->prepare(
    "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'post' LIMIT 1",
    $slug
) );
```

---

## 4. Headless Chrome DevTools Protocol Screenshotting

When taking a screenshot of a specific section below the viewport:
```javascript
// Ensure document top scroll
await send("Runtime.evaluate", { expression: "window.scrollTo(0, 0);" });

// Compute document coordinates
const box = await send("Runtime.evaluate", {
  expression: `(() => {
    const el = document.getElementById("section-7");
    const r = el.getBoundingClientRect();
    return { x: r.x, y: r.y + window.scrollY, width: r.width, height: r.height };
  })()`,
  returnByValue: true
});

// Capture with captureBeyondViewport
const screenshot = await send("Page.captureScreenshot", {
  format: "png",
  clip: {
    x: Math.round(box.result.value.x),
    y: Math.round(box.result.value.y),
    width: Math.round(box.result.value.width),
    height: Math.round(box.result.value.height),
    scale: 1
  },
  captureBeyondViewport: true
});
```

---

## 5. WordPress KSES Bypass Guard (Preserving `<style>` Tags)

In WordPress, standard `wp_update_post()` can invoke KSES filters that strip `<style id="vvvebjs-styles">` or `<script>` tags, causing styles to break.

### Safe Persistence Techniques:
1. **Via Direct `$wpdb` Update**:
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
2. **Dual-Write Enforcement**: Whenever updating `post_content` in DB, also update `assets/builder/style-page-{slug}.css` so external static enqueuing remains 100% in sync.
