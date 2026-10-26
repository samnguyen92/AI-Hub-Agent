# Palmetto Classics - Kit Builder & Header UI Enhancements Documentation

## 1. Overview

This document summarizes all features, architectural improvements, and UI bug fixes implemented in this session for the Palmetto Classics child theme (`align-child`).

---

## 2. Feature & Enhancement Details

### 2.1 Kit Builder Step Navigation Overhaul (`inc/kit-builder/`)

#### A. Desktop Typography, Height & Clean Minimalist Appearance (`>= 992px`)
* **Height & Dimensions**: Standardized step tab button height to `78px` (`height: 78px; min-height: 78px; box-sizing: border-box;`) on desktop viewports.
* **Minimalist Visual Styling**: Transparent background (`background-color: transparent !important;`), refined 1px border (`border: 1px solid #DFD8CB !important;`), and zero box-shadows across all states. Active state cleanly accented with brand red border & text (`#AD1A24`).
* **Typography & Centered 2-Line Text Wrapping**:
  - Step Number (`.step-num`): Font size `11px`, bold Barlow font (`var(--font-family-2)`), uppercase, line-height 1.
  - Step Title (`.step-title`): Font size `13px`, Barlow bold (`font-weight: 700`), centered, with `max-width: 100px; margin: 0 auto; white-space: normal;` ensuring balanced 2-line wrapping with zero layout shift.
* **Grid Layout**: Structured as a 7-column CSS Grid (`grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 12px;`), ensuring equal spacing across all 7 configuration steps.

#### B. Touch Swipe & Mouse Drag-to-Scroll (`< 992px`)
* **Touch & Mouse Drag Interaction**: Integrated smooth horizontal drag-to-scroll on `.builder-steps-scroll` via `kit-builder.js`:
  - Supports mouse drag with mouse down/move/up events and mobile touch gestures.
  - Added click suppression: Prevents accidental step tab activation when a user performs a drag gesture.
* **Workspace Swipe Navigation**: Users on mobile devices can swipe left/right across the builder workspace to seamlessly advance to the next step or return to the previous step.

#### C. Visual Swipe Hint & Dynamic Blur/Fade Indicator (`< 992px`)
* **Blur Effect & Gradient Fade**:
  - Implemented `::before` (left edge) and `::after` (right edge) pseudo-elements on `.builder-steps-nav`.
  - Styled with `backdrop-filter: blur(2px)` and smooth linear gradient fade towards `--color-9` (cream background).
* **Dynamic Scroll State Classes**:
  - `has-scroll-left`: Fades in the left blur indicator once the user scrolls away from the start.
  - `at-scroll-end`: Fades out the right blur indicator when reaching the end of the scrollable track.

---

### 2.2 Incognito vs Admin Bar Spacing Calibration

#### Problem
In incognito / logged-out mode (without `#wpadminbar`), the fixed header card (`min-height: 80px` + top margin) caused the top section of the kit builder to overlap beneath the header. In logged-in mode, the 32px WordPress admin bar created a different vertical offset.

#### Calibration Matrix
| Viewport | Mode | `padding-top` on `.align-kit-builder-wrap` | Sticky Visual Column `top` |
| :--- | :--- | :--- | :--- |
| **Desktop (`>= 992px`)** | Incognito / Guest | `132px` | `116px` |
| **Desktop (`>= 992px`)** | Admin Bar Logged In | `164px !important` | `148px !important` |
| **Tablet (`768px - 991px`)** | Incognito / Guest | `104px` | `N/A` (stacks vertically) |
| **Tablet (`768px - 991px`)** | Admin Bar Logged In | `136px !important` | `N/A` |
| **Mobile (`< 768px`)** | Incognito / Guest | `98px` | `N/A` |
| **Mobile (`< 768px`)** | Admin Bar Logged In | `150px !important` | `N/A` |

---

### 2.3 Header & Footer Logo Resolution & Boundary Fix

#### A. Logo Asset Replacement
* **Issue**: Intermediate resized uploads (`/wp-content/uploads/2026/07/Logo-150x60.webp`) caused visible cropping and blurriness in `<source>` tags.
* **Fix**: Replaced all `<source>` and `<img>` instances in `parts/header.html` and `parts/footer.html` with the original uncropped `/wp-content/uploads/2026/07/Logo.webp` (`204x60`).

#### B. Header Boundary Padding
* Added `padding: 0 24px !important;` to `.header-nav-bar` to prevent the logo from clipping against the 20px rounded corner of the floating card (`.header-floating-card`) on large viewports.

---

### 2.4 Header Cart Button ("YOUR CART") & Badge Fix

#### A. Issue Diagnosis
* On intermediate viewports (`1101px - 1340px`), `.header-actions` and `.cart-btn` lacked `flex-shrink: 0` and `white-space: nowrap`.
* The label `"YOUR CART"` broke into two lines (`"YOUR"` and `"CART"`).
* Bootstrap's `.badge` default `vertical-align: baseline` pulled the count badge down to the second line, making it overlap the bottom button border.

#### B. Architectural Solution (Aligned with `root.css` & Figma)
* **Button Container (`.cart-btn`)**:
  - `position: relative !important;`
  - `display: inline-flex !important; align-items: center !important;`
  - `padding: 10px 24px 10px 20px !important;`
  - `white-space: nowrap !important; flex-shrink: 0 !important;`
  - Red background `var(--color-7)` with rounded pill shape (`border-radius: 9999px`).
* **Cart Icon (`.cart-icon`, SVG)**:
  - Width/Height: `20px x 20px !important; flex-shrink: 0;`
* **Badge (`.cart-count`, `.badge`)**:
  - `position: absolute !important; bottom: 7px !important; left: 30px !important;`
  - Overlaps the bottom-right corner of the cart icon without taking up horizontal flex space.
  - Circular dark badge (`min-width: 18px; height: 18px; background-color: #1F1F1F; color: #FFFFFF; font-size: 9.5px; font-weight: 700;`).
* **Label (`.cart-label`)**:
  - `display: inline-block !important; white-space: nowrap !important;`
  - `font-weight: 700 !important; font-size: 14px !important; letter-spacing: 0.05em;`
  - `color: #FFFFFF !important; text-transform: uppercase;`

---

### 2.5 User Profile Dropdown Menu & My Account Tab Routing Integration

#### A. Tab Alignment & Replacement of WP Dashboard
* **Removal of WP Dashboard**: Replaced the administrative `WP Dashboard` link with **`Payment Method`** across both Desktop Dropdown (`template-login-dropdown.php`) and Mobile Drawer sublist (`class-login-page-shortcode.php`).
* **Direct Account Tab Mapping**:
  1. 👤 **My Account** (`/my-account/#my-account` or `/my-account/`): Direct access to Profile Information, Address, and Change Password.
  2. 💳 **Payment Method** (`/my-account/#payment-method`): Direct access to saved payment cards and billing settings with credit card SVG icon.
  3. 📦 **Order History** (`/my-account/#my-order`): Direct access to All Orders list and order production lifecycle tracker.
  4. 🚪 **Log Out** (`wp_logout_url()`): Secure nonce-protected user logout with redirect.

#### B. Dynamic URL Resolution & Filter Hooks
* Added helper methods `get_payment_method_url()` and `get_orders_url()` in `Align_Login_Page_Shortcode`.
* Supported tab anchor parameter in `get_account_url($custom_url, $tab)`.
* Added standard WordPress filters: `align_account_url`, `align_payment_method_url`, `align_orders_url`.

#### C. JavaScript Tab Switching & Hash Navigation (`profile-account.js` & `login-page.js`)
* **Hash & Query Sync**: `initAccountTabs()` automatically activates the matching tab from `window.location.hash` or `?tab=...` on load.
* **Live `hashchange` Event**: When a user is already on `/my-account/` and clicks a dropdown item (e.g. from My Account to Payment Method or Order History), the tab switches immediately without a full page reload.
* **Auto-Close Dropdown**: Added click listener on menu links to close the user dropdown menu automatically when an item is selected.

---

### 2.6 My Account Multi-Address Management with Accordion UI (`inc/profile-account/`)

#### A. Multi-Address Support (Max 3 Addresses)
* **Maximum 3 Addresses**: Users can save up to 3 distinct shipping addresses on their account (`Address #1 (Default)`, `Address #2`, `Address #3`).
* **`(X/3 Saved)` Counter**: Header badge indicates current address count.
* **`+ Add New Address` Dynamic Action**:
  - Dynamically adds a new address form block with pre-populated country list.
  - Automatically hides the add button once 3 addresses are created.
  - Newly added address is automatically expanded with autofocus on the street address input.
* **`Remove` Button**: Allows removing secondary addresses (`Address #2`, `#3`) with automatic re-indexing.

#### B. Default Closed Accordion UI
* **Compact Layout**: All saved address cards default to a collapsed (closed) state on page load.
* **Interactive Header**:
  - Displays address badge, `Default` tag on primary address, animated rotating chevron icon, and a live **Summary Preview string** (e.g. `789 Pine Street, Columbia, SC 29201, United States`).
  - Summary preview updates in real time as the user edits fields.
* **Dynamic Country-State Synchronization**: Country change triggers AJAX lookup (`align_get_states_by_country`) and updates the state dropdown specifically for that address card.

#### C. 2-Way Backend & FluentCart Synchronization
* **WordPress User Meta**: Saves all addresses in `user_saved_addresses` and mirrors Address #1 to standard `shipping_address_1`, `shipping_country`, etc.
* **FluentCart Database**: Synchronizes up to 3 records in `fct_customer_addresses` (`type='shipping'`) with `is_primary = 1` for Address #1 and `is_primary = 0` for others, and updates `fct_customers`.

---

### 2.7 My Order Real Data Loading & Mock Removal (`inc/profile-account/`)

#### A. Real Customer Order Querying
* **FluentCart Customer Resolution**: Resolves all customer records belonging to the logged-in user via `user_id` or `email`.
* **Database Query**: Queries real orders from `\FluentCart\App\Models\Order` where `customer_id` belongs to the user.
* **Zero Mock Fallbacks**:
  - Removed all hardcoded mock orders (`123456`, `Bronco Interior Custom Kit`, `Material Sample Kit`).
  - Renders clean **"No Orders Yet"** empty state when an account has 0 orders.

#### B. Secure Order Details Inspection & Authorization
* **Ownership Verification**: Non-admin users are strictly prevented from accessing order details of other accounts.
* **Dynamic AJAX Loading**: Clicking *"View Details"* shows a loading spinner and loads authentic order items, 5-stage production tracking stepper, specs, and real delivery addresses.
* **Mock Cleanup**: Removed hardcoded mock fallbacks (`mock-1`, `Luan Jenkins`, `403 East Main Street`).

### 2.8 Product Details Slider & Swatch Decoupling Architecture (`inc/details-product-page/` & `inc/core/variation/`)

#### A. Issue Diagnosis & Architectural Problem
* **Symptom**: On product details pages with color variations (e.g. Bimini Top Color #465, Bimini Top for Krawlers Edge #491, T-shirt #546), 48x48 swatch icon squares / fabric texture tiles (from `_product_color_variations`) were inadvertently registered into the main hero slider (`#main-carousel`) and thumbnail strip carousel (`#thumbnail-carousel`), doubling the slider count and displaying raw color swatch tiles alongside actual vehicle/product photos.
* **Root Causes**:
  1. `align_get_variation_gallery_urls()`: Merged `wp_fct_product_meta` product photos with `$n_var->media_id` / `$extra['swatch_image_url']` without filtering out swatch icons.
  2. `Align_Details_Product_Page_Builder::build_bundle_data()`: Lacked a swatch URL blacklist guard in `$register_image()`, and ran an indiscriminate `get_posts(['post_parent' => $product_id, 'post_type' => 'attachment'])` query that pulled all uploaded swatch media into `$images[]`.
  3. `Align_Details_Product_Page_Enricher::build_bundle_groups()`: Registered `$var_item['gallery_images']` without filtering out swatch texture URLs.

#### B. Architectural Solution & Strict Swatch Decoupling
* **Variation Gallery Filter (`align_get_variation_gallery_urls`)**:
  - Checks `wp_fct_product_meta` (`object_type = 'product_variant_info'`) for genuine FluentCart variation photos.
  - Implemented `is_swatch_image` checker: Evaluates `swatch_image_url` and `swatch_image_id`, bypassing `$n_var->media_id` and `$extra['image_url']` if they match the swatch icon.
  - Returns strictly clean variation product photo URLs.
* **Global Swatch Blacklist in Data Builder (`build_bundle_data`)**:
  - Compiles `$swatch_blacklist` from all variation swatches on the active product.
  - `$register_image()` blocks any image matching the swatch blacklist from entering `$images[]`.
  - Restricted Media Library attachment queries to execute only when the gallery is completely empty.
* **Enricher Swatch Isolation (`class-details-product-page-enricher.php`)**:
  - Swatch icons (`swatch_image_url`, `swatch_hex`) are strictly assigned to `$node['img']` / `$node['hex']` for the option card buttons.
  - Slider `$images[]` only receives authentic product and variation photos from FluentCart.


---

### 2.9 Checkout Page Variation Thumbnail Fix & Anti-Regression Pipeline (`inc/checkout-page/`)

#### A. Problem Diagnosis
* **Issue**: On the Checkout Page (`palmetto.local/checkout/`), line items with color variations (such as `ToastTAN`) rendered a 48x48 fabric texture swatch image instead of the authentic variation product photo.
* **Root Cause**:
  - In `template-checkout-page.php`, thumbnail resolution prioritized `$var_model->media_id`.
  - In the database, `$var_model->media_id` points to the attachment ID of the custom swatch tile (`ToastTAN.webp`, Attachment ID `417`).
  - FluentCart's native variation photo is actually stored in `wp_fct_product_meta` (`object_type = 'product_variant_info'`, `meta_key = 'product_thumbnail'`), accessible via `$var_model->thumbnail` (`image_1-2.webp`).

#### B. Solution & Resolution Pipeline
* Replaced the thumbnail resolution logic in `template-checkout-page.php` with a 5-tier fallback:
  1. **Tier 1 (`featured_media`)**: Directly reads `$item['featured_media']` / `$item['thumbnail']` populated during Add-to-Cart.
  2. **Tier 2 (`$var_model->thumbnail`)**: Uses FluentCart native model accessor querying `wp_fct_product_meta`.
  3. **Tier 3 (`align_get_variation_gallery_urls`)**: Fallback to variation gallery resolver with active swatch blacklist protection.
  4. **Tier 4 (`$var_model->product->thumbnail`)**: Fallback to parent product thumbnail (`get_the_post_thumbnail_url`).
  5. **Tier 5**: Clean SVG placeholder.
* Verified with live database query: Variation `88` (`ToastTAN`) correctly resolves to `http://palmetto.local/wp-content/uploads/2026/08/image_1-2.webp`.

### 2.7 Product Details Fixed Bottom Price & Actions Bar & Routing Collision Guard (`inc/details-product-page/` & `inc/shop-page/`)

#### A. Problem Diagnosis & Requirements
* **Requirement**: Convert the product total price display (`SELECTED TOTAL`) and action controls (Quantity picker, `ADD TO CART`, `CHECK OUT NOW`) on the Product Details page into a permanent fixed-position bar pinned to the bottom of the viewport (`position: fixed; bottom: 0;`), strictly matching the provided design mock.
* **Route Collision Issue**:
  - The Shop Page rewrite rule `'^shop/([^/]+)/?$'` (priority `'top'`) intercepted single product permalinks (`/shop/{product-slug}/`).
  - Single product requests were treated as category subroutes of the Shop archive (ID 598), preventing single product configurator pages from rendering.

#### B. Architectural Solution
1. **Single Product Routing Disambiguation (`inc/shop-page/class-shop-page-init.php`)**:
   - In `modify_shop_request()`, added an existence check querying `post_type = 'fluent-products'` for candidate slugs matching `shop/([^/]+)` or `$query_vars['shop_category']`.
   - If matched, unsets `pagename`, `page_id`, and `shop_category`, and sets `$query_vars['post_type'] = 'fluent-products'` and `$query_vars['name'] = $candidate_slug`, allowing single products to resolve directly with HTTP 200.
2. **Fixed Bottom Bar Structure & Positioning**:
   - Anchored `.product-fixed-bottom-bar` at the bottom of `#section-1` before closing tag:
     - `position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; width: 100% !important; z-index: 1050 !important;`
     - Background `#FAF7F2`, border-top `1px solid #E2DDD6`, box-shadow `0 -4px 20px rgba(0, 0, 0, 0.05)`.
     - Left controls: `.action-controls-row` (Quantity picker pill, `ADD TO CART` solid red button `#AD1A24`, `CHECK OUT NOW` outline red button).
     - Right total box: `.selected-total-box` (Rose Red `#C85A5A`, 14px rounded box, bold uppercase `SELECTED TOTAL` + dynamic `#total-price-display`).
   - `#section-1` assigned `padding-bottom: 96px !important;` (Desktop/Tablet) and `padding-bottom: 125px !important;` (Mobile) to prevent content occlusion.
   - Replaced in-column total box in `.product-form-col` with `.product-invoiced-note` (`* Balance invoiced on completion.`).
3. **Responsive Design Matrix**:
   - **Desktop (1440px) & Laptop (1024px)**: 4 elements horizontally aligned in single row (`justify-content: space-between`).
   - **Tablet (577px - 992px)**: Compact 44px control heights, scaled fonts, 20px container padding.
   - **Mobile (≤ 576px)**: Two stacked rows (`order: 1` Total Price box on top, `order: 2` Action controls row on bottom with full-width flex buttons and icon truncation safeguards).
4. **Dynamic Price Recalculation**:
   - Because all action elements remain inside `#section-1`, existing JavaScript listeners in `product-details.js` bind automatically without script alterations.
   - Real-time variation switches (e.g. `Ash` +$102.18) recalculate and update `#total-price-display` inside the fixed bottom bar instantly.
5. **Full Prototype Synchronization**:
   - Synchronized `template-details-product-page.php`, `assets/css/product-details.css`, `section-1/index.html`, `details-product-page.html`, and `section-1/style.css`.

---

### 2.7 Shop Page Category Filter Isolation (Kit Builder Detection Exclusion)

#### A. Problem Description
On the customer-facing Shop catalog page (`/shop/`), the left sidebar filter accordion rendered an unwanted filter group:
- **`IS KIT BUILDER`**
  - `[ ] Bronco | 1978 - 1979`
This occurred because `is Kit Builder` (ID: `40`, slug: `is-kit-builder`) was registered as a top-level (`parent => 0`) taxonomy term in `product-categories` with child year terms (e.g. `Bronco | 1978 - 1979`, ID: `41`). This taxonomy is strictly an internal technical tag created to detect and associate configurator options inside the **Interior Kit Builder**, and should never be visible or selectable by customers browsing the shop.

#### B. Architectural Solution & Safeguards
1. **Dynamic Term & Child Traversal in SSR Shortcode (`class-shop-page-shortcode.php`)**:
   - Added `'is-kit-builder'`, `'kit-builder'`, `'is_kit_builder'`, `'kit_builder'` and term ID `40` to `$excluded_category_slugs` and `$excluded_category_ids`.
   - Utilized `get_term_children()` to dynamically resolve and exclude all present and future child category IDs belonging to `is-kit-builder`.
   - Applied defensive substring checks (`strpos($p_slug, 'kit-builder') !== false || stripos($parent_cat->name, 'Kit Builder') !== false`) at both parent and child loop levels.
   - Stripped detection category slugs from `$active_categories` to prevent activating the filter via query strings.
   - Maintained file length strictly at 493 lines (< 500 lines threshold).
2. **AJAX Catalog Sanitization (`class-shop-page-ajax.php`)**:
   - Filtered out all `kit-builder` and `add-on` slugs from incoming AJAX filter parameters.
3. **SEO Title & Metadata Protection (`class-shop-page-seo.php`)**:
   - Disallowed `is-kit-builder` from setting or overriding document titles and dynamic H1 headings.
4. **Client-Side URL & Hash Parser (`shop-hash-filter.js`)**:
   - Ignored `kit-builder` terms in both Pretty URL pathname and query parameter parsing. Maintained file length at 496 lines.
5. **Universal Category Archives Consistency**:
   - Updated `class-category-page.php` and `class-archive-page-renderer.php` to exclude `kit-builder` terms from sidebar filters and archive pill lists.

### 2.8 Shop Page Responsive `<picture>` Sources & On-Demand Image Cropping Engine

#### A. Problem Description & Root Cause
On the customer-facing Shop catalog page (`/shop/`), the design specifies an asymmetric masonry layout with:
- **Wide Cards (`644 x 312`)** at card index 0 and 3 in modulo 7 (`$card_index % 7 === 0 || $card_index % 7 === 3`).
- **Square Cards (`312 x 312`)** at all other positions.

When inspecting the rendered HTML, cards were outputting the raw, uncropped original images (e.g. `image_1-3.webp` at 638x643 or full size) instead of the required cropped dimensions.
- **Root Cause 1 (Missing Database Crop Metadata)**: Existing media uploaded to WordPress only had intermediate sizes named `'312x312'` and `'644x644'` from legacy settings. They lacked registered intermediate sizes for `'shop_card_square'` or `'shop_card_wide'`. When `wp_get_attachment_image_url()` was called, WordPress returned `false` and fell back to the original full-size image.
- **Root Cause 2 (Variation Cards Missing Attachment IDs)**: Product variations often only provided a string image URL (`thumb_url`) rather than a numerical attachment ID, bypassing WordPress core image size generation.

#### B. Architectural Solution & Implementation
1. **Registered Standard & Retina Image Sizes (`class-shop-page-init.php`)**:
   - `shop_card_square`: `312 x 312` (hard crop: true)
   - `shop_card_square_2x`: `624 x 624` (hard crop: true)
   - `shop_card_wide`: `644 x 312` (hard crop: true)
   - `shop_card_wide_2x`: `1288 x 624` (hard crop: true)
2. **Dedicated Card Renderer Module (`class-shop-page-card-renderer.php`)**:
   - Extracted card rendering logic from `class-shop-page-ajax.php` into `Align_Shop_Page_Card_Renderer` (230 lines, SRP-compliant).
   - **On-Demand Cropping & Resolution Engine (`resolve_image_size()`)**:
     - Evaluates target size metadata. If missing, checks known aliases (`312x312`, `644x312`, `644x644`).
     - If neither exists on disk, dynamically invokes `image_make_intermediate_size($file, $width, $height, true)`, updates the attachment metadata via `wp_update_attachment_metadata()`, and returns the cropped image URL.
     - Resolves attachment IDs from URLs via `attachment_url_to_postid()`.
3. **Semantic Responsive `<picture>` Source Structure**:
   - **Wide Cards (`grid-span-2`)**:
     - Desktop (≥ 992px): `<source media="(min-width: 992px)" srcset="...-644x312.webp 1x, ...-1288x624.webp 2x">`
     - Tablet/Mobile (< 992px): Card collapses to 1 column -> `<source media="(max-width: 991px)" srcset="...-312x312.webp 1x, ...-624x624.webp 2x">`
     - Fallback `<img>` with width="644" height="312".
   - **Square Cards (`grid-span-1`)**:
     - `<source srcset="...-312x312.webp 1x, ...-644x644.webp 2x">`
     - Fallback `<img>` with width="312" height="312".
4. **Card Image Box Dimensions & Centering (`assets/css/shop-page.css`)**:
   - Updated `.card-image-box` height to `312px` (matching the `312x312` and `644x312` card heights) with flex centering and `overflow: hidden`.
5. **Taxonomy Query Resilience (`class-shop-page-ajax.php`)**:
   - Added `'all'` and `'all-categories'` to `$excluded_category_slugs` to prevent accidental term matching on "All Categories" filter requests.
   - Streamlined parameter parsing, keeping file length at 494 lines (< 500 lines threshold).

### 2.9 Shop Page Native FluentCart Variation Images Resolution

#### A. Problem Description & Root Cause
On the customer-facing Shop catalog page (`/shop/`), unpacked variation cards (`is_real_product == 1`) were displaying swatch texture icons (e.g. `Black.webp` or `ToastTAN.webp` at 312x312) instead of authentic vehicle product photos configured on native FluentCart variation records (`wp_fct_product_meta.product_thumbnail`).
- **Root Cause 1 (Media ID Contamination during Sync)**: In `align_get_product_color_variations()` (`variation-sync.php`), `$thumb_id = ($n_media_id > 0) ? $n_media_id : intval($parent_thumb_id);`. Because `n_media_id` in `wp_fct_product_variations` had previously been populated with `swatch_image_id` (ID `359`, `Black.webp`) during auto-healing, it erroneously returned the swatch ID as the variation's `image_id`.
- **Root Cause 2 (Hardcoded Swatch Fallback in Shop Ajax)**: `class-shop-page-ajax.php` had an explicit fallback `if (!$var_thumb_id && !empty($var['swatch_image_id'])) { $var_thumb_id = intval($var['swatch_image_id']); }` which locked in the swatch texture ID.
- **Root Cause 3 (Decoupled Image Storage in FluentCart)**: FluentCart stores genuine variation product photos inside `wp_fct_product_meta` with `object_type = 'product_variant_info'`, `meta_key = 'product_thumbnail'`, and `meta_value = [{"id": 506, "title": "Black", "url": "http://.../Black-1.webp"}]`.

#### B. Architectural Solution & Implementation
1. **Direct Native Variant Metadata Ingestion (`variation-sync.php`)**:
   - Updated `align_get_product_color_variations()` to query `wp_fct_product_meta` for `product_thumbnail`.
   - Extracted the true attachment ID (e.g. ID `506` for Black, ID `479` for ToastTAN) and mapped it directly to `image_id` and `image_url`.
   - Maintained file length strictly at 497 lines (< 500 lines threshold).
2. **Swatch Blacklist Guard & Decoupling (`class-shop-page-ajax.php`)**:
   - Removed the faulty swatch fallback code.
   - Enforced validation: Any candidate image matching `swatch_image_id` or `swatch_image_url` is rejected by the Swatch Blacklist Guard. Swatches are strictly prohibited from catalog cards.
   - Implemented reverse URL lookup via `attachment_url_to_postid()` to recover attachment IDs for `Align_Shop_Page_Card_Renderer` when given valid variation photo URLs.
   - Maintained file length strictly at 491 lines (< 500 lines threshold).

---

### 2.10 Product Details Option Card Height & Badge Spacing Clearance

#### A. Problem Description & Visual Root Cause
On the Product Details configurator page (`/shop/{product-slug}/`), option cards in option groups (e.g. "Select Color", "Select Material") exhibited visual overlap:
- `.var-badges-wrapper` was positioned with `position: absolute; top: 0; right: 0; height: 24px;`.
- Concurrently, `.card-text` started with standard `8px` top padding, directly in line with the badges.
- When two badges were active (e.g. `BEST SELLER` + `READY TO SHIP` on `ToastTAN`), their combined width (~160px) occluded almost the entire text box, covering the option name `ToastTAN`. Single badges (`MADE TO ORDER`) also collided with longer names like `Toast Tweed`, `Tresco Birch`, and `Walnut Brown Tweed`.

#### B. Architectural Solution & Implementation
1. **Increased Card Min-Height & Centering (`product-details.css` & `section-1/style.css`)**:
   - Raised `.option-card` `min-height` from `68px` to `82px` with `align-items: center; padding: 8px 10px !important;`.
   - The 48x48px swatch thumbnail preview is vertically centered on the left side of the card.
2. **Refined Badge Sizing & Corner Radii**:
   - Decreased `.var-badge` height to `20px` with `font-size: 9.5px; font-weight: 700; padding: 0 6px; letter-spacing: 0.25px;`.
   - Styled corner radiuses: `border-top-right-radius: 7px;` and `border-bottom-left-radius: 4px;`.
3. **Dedicated Text Clearance Spacing (`.has-badges` / `:has(.var-badges-wrapper)`)**:
   - Added `#section-1 .option-card.has-badges .card-text, #section-1 .option-card:has(.var-badges-wrapper) .card-text { padding-top: 18px !important; }`.
   - Result: Option titles start at `y = 26px`, establishing a clean **6px vertical gap** below the 20px badge banner. Option titles have 100% horizontal width available, completely preventing overlap.
4. **Enhanced Typography & Grid Scroll Container**:
   - Option title (`span.fs-body`) upgraded to `font-weight: 600 !important; margin-bottom: 2px !important;`.
   - Increased `.option-cards-grid` `max-height` to `290px` (was 250px) so 3 full rows (9 option cards) display cleanly without cutting off the bottom row.
5. **SSR & Client-Side Synchronicity**:
   - Server-side template `template-details-product-page.php` assigns `has-badges` class to option cards (435 lines, < 500).
   - Dynamic render engines `product-details-render.js` (415 lines, < 500) and `product-details.js` assign `has-badges` during DOM construction.

### 2.11 Kit Builder Universal Custom Request Banner & UI System Refinements (`inc/kit-builder/`)

#### A. Universal "Have a Custom Request?" Bottom Banner Across All 7 Steps
* **Universal Step Coverage**: Rendered across all 7 configurator steps (`panel-step-1` through `panel-step-7`) right after `.panel-nav-actions` / `.panel-final-actions` and before `</section>`.
* **DRY Architecture via Reusable PHP Closure**: Defined `$render_custom_request_banner` closure in `template-kit-builder.php`, preventing markup duplication and avoiding repeated HTML IDs.
* **Dynamic Contact Us Linking via Page ID `492`**: Resolves the live Contact Page permalink via `get_permalink(492)` with fallback to slug `contact-us`, automatically appending the anchor `#custom-builder` (`/contact-us/#custom-builder`).
* **Direct Frictionless Navigation**: Replaced the previous modal popup trigger with a direct link (`<a class="btn-custom-quote">`), allowing instant quote inquiries from any step.

#### B. Custom Dropdown Selects & Step Navigation UI System
* **Dropdown Toggle & Menu Theme Harmony**:
  - Set `background-color: var(--color-9, #F9F2E8) !important;` on `.align-custom-select-toggle` and `.align-custom-select-menu` for seamless visual integration with the cream canvas.
  - Hover, focus, and selected states (`:hover, .is-focused, .is-selected`): Completely removed harsh pink/grey background blocks (`rgba(173, 26, 36, 0.08)`). Items maintain transparent background and transition text color to brand red `var(--color-7, #AD1A24)`.
* **Step Tab Button Standard (`.step-tab-btn`)**:
  - Equalized fixed height to `78px` (`height: 78px; min-height: 78px; box-sizing: border-box;`).
  - Transparent backgrounds across all states, 1px `#DFD8CB` border, zero box-shadows.
  - Centered 2-line title wrapping (`max-width: 100px; margin: 0 auto; white-space: normal;`) with 11px step number and 13px Barlow bold title.

---

## 3. Files Created or Modified

| File Path | Summary of Changes |
| :--- | :--- |
| `inc/core/variation/variation-sync.php` | Queries `wp_fct_product_meta` directly for `product_thumbnail`, extracts true attachment ID, enforces swatch blacklist (497 lines, < 500). |
| `inc/shop-page/class-shop-page-ajax.php` | Removed swatch fallback, added Swatch Blacklist Guard & reverse URL lookup, maintained at 491 lines (< 500). |
| `inc/details-product-page/assets/css/product-details.css` | Increased `.option-card` to 82px min-height, centered thumbs, 20px badge pills, added 18px text padding-top on `.has-badges`, and set grid max-height to 290px. |
| `inc/details-product-page/section-1/style.css` | Synchronized option card min-height, badge styles, and text clearance padding in prototype stylesheet. |
| `inc/details-product-page/template-details-product-page.php` | Appended `has-badges` class to option card button in SSR loop (435 lines, < 500). |
| `inc/details-product-page/assets/js/product-details-render.js` | Appended `has-badges` class dynamically in JS render engine (415 lines, < 500). |
| `inc/details-product-page/assets/js/product-details.js` | Appended `has-badges` class dynamically in interactive runtime module. |
| `inc/details-product-page/docs/product-details.md` | Documented Option Card Layout & Badge Spacing Clearance (Section 3.B.1), 290px grid max-height, and added VC-13. |
| `inc/shop-page/class-shop-page-card-renderer.php` | [NEW] Dedicated product card renderer module (230 lines): On-demand image cropping engine, responsive `<picture>` markup, 644x312 and 312x312 slot resolution. |
| `inc/shop-page/class-shop-page-init.php` | Registered standard and Retina crop sizes (`shop_card_square`, `shop_card_square_2x`, `shop_card_wide`, `shop_card_wide_2x`). |
| `inc/shop-page/assets/css/shop-page.css` | Updated `.card-image-box` to 312px height with flex alignment for picture tags (490 lines, < 500). |
| `inc/shop-page/docs/shop-page.md` | Documented Native Variation Image Resolution (Section 3.E) and updated verification checklist (VC-29). |
| `docs/README.md` | Updated child theme index and modules map linking to Shop Page architecture documentation. |
| `inc/shop-page/class-shop-page-shortcode.php` | Excluded `is-kit-builder` category and its children from SSR sidebar filters, resolved child terms dynamically, compacted parameter parsing (< 500 lines). |
| `inc/shop-page/class-shop-page-seo.php` | Prevented `is-kit-builder` from triggering dynamic H1 or SEO document titles. |
| `inc/shop-page/assets/js/shop-hash-filter.js` | Ignored `kit-builder` categories in URL path and query param parser (< 500 lines). |
| `inc/details-product-page/class-category-page.php` | Excluded `is-kit-builder` and child categories from category archive sidebar. |
| `inc/archive-page/class-archive-page-renderer.php` | Excluded `is-kit-builder` and child categories from archive pills. |
| `inc/details-product-page/template-details-product-page.php` | Integrated `.product-fixed-bottom-bar`, replaced in-column total box with `.product-invoiced-note`. |
| `inc/details-product-page/assets/css/product-details.css` | Fixed bottom bar styling, responsive breakpoints (Desktop, 1024px, 768px, <= 576px), and section bottom padding. |
| `inc/details-product-page/section-1/style.css` | Synchronized fixed bottom bar CSS and responsive media queries in prototype stylesheet. |
| `inc/details-product-page/section-1/index.html` | Synchronized HTML prototype with fixed bottom bar DOM structure. |
| `inc/details-product-page/details-product-page.html` | Synchronized root HTML prototype with fixed bottom bar DOM structure. |
| `inc/details-product-page/docs/product-details.md` | Documented Fixed Bottom Bar Architecture (Section 3.K), Routing Guard (Section 3.L), and verification scenarios. |
| `inc/details-product-page/section-1/docs/spec.md` | Updated DOM structure specification and quantitative design tokens table. |
| `inc/shop-page/class-shop-page-init.php` | Added `fluent-products` post existence guard in `modify_shop_request()` to prevent single product route collisions. |
| `inc/checkout-page/template-checkout-page.php` | Fixed variation thumbnail resolution pipeline to prioritize `featured_media` and `$var_model->thumbnail` over swatch `media_id`. |
| `inc/checkout-page/docs/checkout-page.md` | Comprehensive architectural and technical documentation for the Checkout Page module. |
| `inc/core/variation/docs/fluentcart-variation-architecture.md` | Added Section 6 detailing Cart & Checkout Variation Thumbnail Resolution Rules and anti-regression safeguards. |
| `inc/details-product-page/class-details-product-page-builder.php` | Added global `$swatch_blacklist` in `$register_image()` and restricted attachment query fallback. |
| `inc/details-product-page/class-details-product-page-enricher.php` | Strictly decoupled Option Card swatch icons from slider gallery registration in `align_enrich_bundle_tree_nodes()` and `build_bundle_groups()`. |
| `inc/profile-account/my-account/class-my-account-helper.php` | Multi-address data loader (up to 3), real FluentCart order query, mock data removal. |
| `inc/profile-account/my-account/class-my-account-ajax.php` | Multi-address saving endpoint (`align_save_address_info`) & authenticated order detail fetch. |
| `inc/profile-account/my-account/template-my-account-tab.php` | Multi-address accordion layout (default closed), live summary preview, template blueprint. |
| `inc/profile-account/my-account/template-order-detail.php` | Real shipping address rendering, empty state handling, mock cleanup. |
| `inc/profile-account/template-profile-account.php` | Order list empty state ("No Orders Yet") and dynamic order detail loading placeholder. |
| `inc/profile-account/assets/js/profile-account.js` | Multi-address manager, accordion toggle, live summary preview, dynamic country/state sync. |
| `inc/profile-account/assets/css/profile-account.css` | Accordion card styling, animated chevron arrow, address badge, and remove button styles. |
| `inc/profile-account/docs/profile-account.md` | Comprehensive technical documentation updated with multi-address accordion and order sync. |
| `inc/profile-account/my-account/docs/my-account.md` | Submodule documentation updated. |
| `inc/login-page/template-login-dropdown.php` | Replaced WP Dashboard with Payment Method (with credit card SVG icon) and updated tab link URLs. |
| `inc/login-page/class-login-page-shortcode.php` | Added `get_payment_method_url()`, `get_orders_url()`, filter hooks, and updated mobile drawer list. |
| `inc/login-page/assets/js/login-page.js` | Added auto-close behavior when clicking dropdown items. |
| `parts/header.html` | Updated logo `<source>` tags, added `.header-nav-bar` padding, fixed cart button markup & inline styles. |
| `parts/footer.html` | Updated logo `<source>` tags to uncropped full resolution. |
| `assets/builder/style-part-header.css` | Scoped header CSS with cart button absolute badge positioning & no-wrap rules. |
| `assets/src/general/css/custom.css` | General CSS updated with cart button, badge & logo rules. |
| `assets/dist/general/css/custom.min.css` | Production minified general CSS synchronized. |
| `inc/kit-builder/template-kit-builder.php` | Integrated universal custom request banner closure `$render_custom_request_banner` across Steps 1 to 7 with dynamic Contact Page ID 492 anchor link. |
| `inc/kit-builder/assets/css/kit-builder.css` | Standardized 78px transparent step tabs, 11px/13px Barlow typography, custom select `#F9F2E8` background with text-only red highlight. |
| `inc/kit-builder/assets/js/kit-builder.js` | Drag-to-scroll, click suppression, edge blur class toggling, and gesture swipe navigation. |
| `docs/session-features-and-enhancements.md` | Comprehensive session technical documentation. |

---

## 4. Verification & Testing Checklist

- [x] **Product Details Fixed Bottom Bar**: Pinned permanently at bottom of viewport (`position: fixed; bottom: 0; z-index: 1050;`) across all scroll states.
- [x] **Dynamic Real-Time Price Calculation**: Option switches (e.g. `Ash` +$102.18) recalculate and update `#total-price-display` inside the fixed bottom bar live without page reloads.
- [x] **Quantity Picker & Cart Actions in Fixed Bar**: Minus/plus quantity buttons, `ADD TO CART` AJAX submission, and `CHECK OUT NOW` execute reliably from the fixed bar.
- [x] **Single Product Route Disambiguation**: Pretty category rewrite rules in `Align_Shop_Page_Init` verify product existence and route `/shop/{product-slug}/` to single product template with HTTP 200 without collision.
- [x] **Clearance Spacing**: `#section-1` has 96px (125px on mobile) bottom padding, preventing fixed bottom bar from occluding info tabs or related products.
- [x] **Responsive Verification**: Verified across Desktop (1440px), Laptop (1024px), Tablet (768px), and Mobile (<= 576px).
- [x] **Checkout Variation Thumbnail Resolution**: Cart line items in checkout table strictly render authentic product photos (`image_1-2.webp`) from `$var_model->thumbnail` / `wp_fct_product_meta` instead of 48x48 texture swatches.
- [x] **Product Details Slider Swatch Decoupling**: Swatch texture icons (48x48) are completely removed from hero slider and thumbnail strip carousel across all product types (Simple Variation, Bundle Tree, Simple Products).
- [x] **Option Card Swatches**: Swatch tiles and color previews on Option Cards continue to render correctly (48x48 preview) with active bidirectional slider sync.
- [x] **Desktop (>= 992px)**: Step tabs render at 78px equal height with transparent background, 11px step number, 13px Barlow bold title, and zero box-shadow in 7 equal columns.
- [x] **Kit Builder Universal Custom Request Banner**: Rendered across all 7 steps (Steps 1 to 7) linking directly to `/contact-us/#custom-builder` via dynamic Page ID 492.
- [x] **Kit Builder Custom Dropdown Selects**: Toggle and menu styled with `#F9F2E8` (`--color-9`) background, text-only red `#AD1A24` highlight on hover/selected, pink/grey background blocks removed.
- [x] **Mobile (< 992px)**: Horizontal drag-to-scroll and swipe gesture navigation work smoothly.
- [x] **Edge Blur Indicator**: Gradient blur correctly hints scrollable content and dynamically fades out at edges.
- [x] **Incognito vs Admin Mode**: Page spacing maintains consistent 36px breathing room below header in both modes.
- [x] **Logo Display**: Logo renders crisp and uncropped across all breakpoints.
- [x] **Cart Button**: `"YOUR CART"` stays on 1 line with the count badge positioned neatly over the cart icon bottom-right corner.
- [x] **User Dropdown Links**:
  - `My Account` links directly to `/my-account/` (Tab 1).
  - `Payment Method` links directly to `/my-account/#payment-method` (Tab 2).
  - `Order History` links directly to `/my-account/#my-order` (Tab 3).
  - `WP Dashboard` is removed from the dropdown.
- [x] **SPA Tab Switching**: Clicking dropdown links while on `/my-account/` switches tabs instantly via `hashchange`.
- [x] **My Orders Real Data**: Displays strictly user-owned orders from FluentCart; renders "No Orders Yet" when 0 orders exist.
- [x] **My Orders Access Control**: Prevents cross-account order inspection.
- [x] **Multi-Address Management**: Allows saving up to 3 addresses with synchronization to FluentCart `CustomerAddresses`.
- [x] **Address Accordion**: All address cards default to closed; headers show live summary preview and toggle open/closed smoothly.
- [x] **Address Actions**: Adding an address auto-opens the card and focuses inputs; removing secondary addresses re-indexes list.
- [x] **Kit Builder Detection Category Exclusion**: Category `is Kit Builder` (ID: 40) and all child terms (e.g. `Bronco | 1978 - 1979`, ID: 41) are 100% excluded from Shop sidebar filters, SSR rendering, AJAX requests, and SEO titles.
- [x] **Shop Page File Limits (< 500 lines)**: `class-shop-page-shortcode.php` (493 lines) and `shop-hash-filter.js` (496 lines) both strictly comply with the theme coding standard.
- [x] **Shop Page Responsive `<picture>` Resolution**: Wide cards (`644x312` desktop, `312x312` mobile) and square cards (`312x312` all viewports) output semantic `<picture>` tags with exact crop dimensions on both SSR and AJAX.
- [x] **On-Demand Image Cropping & Metadata Persistence**: Missing crop sizes are generated dynamically on first load via `image_make_intermediate_size()` and stored permanently in attachment metadata.
- [x] **Shop Page Native Variation Image Resolution**: Unpacked variation cards (`is_real_product == 1`) query `wp_fct_product_meta` directly for authentic variation vehicle photography (`meta_key = 'product_thumbnail'`), eliminating swatch textures (`Black.webp`, `ToastTAN.webp`) from catalog cards.
- [x] **Product Details Option Card Height & Badge Clearance**: Option cards render at `min-height: 82px` with 20px badge pills; `.card-text` receives `padding-top: 18px` on `.has-badges` / `:has(.var-badges-wrapper)`, creating 6px vertical clearance so badges never overlap option titles.
- [x] **Coding Standards (<500 lines/file)**: All newly created and modified files (`class-shop-page-ajax.php`: 491 lines, `variation-sync.php`: 497 lines, `template-details-product-page.php`: 435 lines, `product-details-render.js`: 415 lines, `class-shop-page-card-renderer.php`: 230 lines) strictly comply with the theme coding standard.

