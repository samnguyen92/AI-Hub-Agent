# Palmetto - Header Navigation, Mega Menu & Dynamic Active Menu Technical Documentation

## 1. Overview & Architecture

The **Header Navigation, Mega Menu & Dynamic Active Menu System** provides an intelligent, automatic active state detection mechanism, high-performance desktop Mega Menu layout, and integrated Gutenberg Live Search for the Palmetto Classics child theme.

### Key Capabilities:
1. **100% Dynamic & Generic URL Detection**: No hardcoded links in HTML templates. Adding or editing `href` attributes in HTML requires zero modifications to JavaScript.
2. **Two-Tier Parent-Child Route Hierarchy**: When visiting any sub-page, child tab, or archive/single post type (e.g. `/my-account/payment-method/`, `/product/bronco-carpet-kit/`), the parent menu item (`HI, [NAME]` or `SHOP`) is automatically activated.
3. **Dropdown & Mega Menu Traversal**: Detects links nested inside the Desktop Mega Menu panel (`#mega-menu-panel`) or User Account Dropdown (`.align-user-dropdown-menu`) and automatically bubbles the active state to the parent trigger button.
4. **Desktop Mega Menu 3-Column Spacing Architecture**: Precision flex grid with balanced spacing (`145px` labels, `32px` gap 1, `150px` col 1, `36px` gap 2, `flex: 1` col 2) preventing word wrapping on long text items like `"Available for Immediate Ship"`.
5. **Integrated Live Search Box (`wp:align/live-search`)**: Replaced duplicate static mockups with a fully interactive Gutenberg live search block styled with 10px rounded corners, transparent button with divider border, and theme red stroke icons.
6. **Pure CSS Dynamic Indicator**: Replaces hardcoded HTML indicator spans with CSS pseudo-elements (`.nav-link-btn.active::after`), providing a consistent 3px red bottom bar (`var(--color-7)`) and cream background (`#efe8db`).
7. **Browser Button Reset**: Normalizes `<button>` elements (such as `SHOP` and User Dropdown) with `background: transparent !important`, eliminating default gray browser user-agent styling.
8. **Synchronous Desktop & Mobile Drawer Support**: Updates desktop nav items (`.nav-link-btn`) and mobile drawer items (`.mobile-nav-link`, `.mobile-user-toggle`) simultaneously.

---

## 2. Directory Structure & Key Files

```
wp-content/themes/align-child/
├── parts/
│   ├── header.html                     # Block template part with primary navigation, mega menu markup & live search block
│   └── footer.html                     # Block template part embedding autoDetectActiveNav script
├── assets/
│   ├── builder/
│   │   ├── style-part-header.css       # Scoped header styles, mega menu grid & active indicator pseudo-elements
│   │   ├── script-part-header.js       # Header interactions (mega menu, mobile drawer, cart badge)
│   │   ├── style-part-footer.css       # Scoped footer styles
│   │   └── script-part-footer.js       # Footer execution & autoDetectActiveNav loader
│   ├── src/general/css/
│   │   └── custom.css                  # Global general CSS with mega menu typography & live search overrides
│   ├── dist/general/css/
│   │   └── custom.min.css              # Minified general CSS
│   ├── custom-live-search.css          # Global align live search styles with mega menu overrides
│   └── css/
│       └── custom-live-search.css      # Mirrored custom live search stylesheet
├── inc/
│   ├── login-page/
│   │   ├── class-login-page-shortcode.php  # [align_login_register] shortcode output
│   │   ├── template-login-dropdown.php     # User dropdown navigation menu
│   │   └── assets/css/login-page.css       # User button & dropdown active state styling
│   └── profile-account/
│       └── ...                             # /my-account/ pages and sub-routes
└── docs/
    └── header-navigation-active-system.md  # This technical documentation
```

---

## 3. Mega Menu Layout & Column Spacing System

### 3.1 Column Grid Hierarchy
The left side of the Mega Menu (`.mega-menu-categories`, `col-lg-7`) is structured into a 3-column horizontal flow:

```
+---------------------------------------------------------------------------------------------------+
| .mega-menu-categories (col-lg-7, max content width ~662px)                                        |
|                                                                                                   |
|  [ Col 1: Labels ]     (gap: 32px)     [ Col 2: Categories 1 ]    (gap: 36px)   [ Col 3: Cat 2 ]  |
|  width: 145px (fixed)                  width: 150px (fixed)                     flex: 1 1 auto    |
|  - SHOP BY MAKE/ MODEL                 - All Categories                         - Headliner       |
|  - BROWSE ALL                          - Carpet Kits                            - Accessories     |
|                                        - Center Console                         - Apparels        |
|                                        - Bimini Top                             - Interior Kit    |
|                                        - Seats                                  ----------------- |
|                                        - Sample Kits                            - Available for   |
|                                        - Audio Kits                               Immediate Ship  |
|                                        - Seat Belts                             - BestSellers     |
|                                        - Dash Pad                                                 |
|                                        - Panels Sets                                              |
+---------------------------------------------------------------------------------------------------+
```

### 3.2 Spacing Specifications:
- **Column 1 (`.mega-label-col`)**: `145px` fixed width. Houses group titles (`SHOP BY MAKE/ MODEL`, `BROWSE ALL`) without wasted horizontal space.
- **Gap 1 (Col 1 to Col 2)**: `32px`. Clean visual separation between category groups and product lists.
- **Column 2 (`.mega-subcol-1`)**: `150px` fixed width. Comfortably contains the first 10 category items (`All Categories` through `Panels Sets`).
- **Gap 2 (Col 2 to Col 3)**: `36px`. Distinct division between column 1 and column 2 of the browse links.
- **Column 3 (`.mega-subcol-2`)**: `flex: 1 1 auto` (allocating **~260px - 300px**). Ample breathing room for longer items (`Interior Accessories`, `Available for Immediate Ship`, `Ready-to-ship items`).
- **Typography Wrap Prevention**: `.category-header`, `.mega-link`, `.group-highlight` have `white-space: nowrap !important;` to ensure labels never wrap inappropriately onto multiple lines.

### 3.3 CSS Implementation:
```css
@media (min-width: 1101px) {
  .mega-menu-categories .mega-category-row {
    display: flex !important;
    flex-direction: row !important;
    gap: 32px !important;
    align-items: flex-start !important;
  }
  .mega-menu-categories .mega-label-col {
    flex: 0 0 145px !important;
    width: 145px !important;
    min-width: 145px !important;
    max-width: 145px !important;
  }
  .mega-menu-categories .mega-content-col {
    flex: 1 1 0 !important;
    min-width: 0 !important;
  }
  .mega-menu-categories .mega-subgrid {
    display: flex !important;
    flex-direction: row !important;
    gap: 36px !important;
    width: 100% !important;
  }
  .mega-menu-categories .mega-subcol-1 {
    flex: 0 0 150px !important;
    width: 150px !important;
    min-width: 150px !important;
    max-width: 150px !important;
  }
  .mega-menu-categories .mega-subcol-2 {
    flex: 1 1 auto !important;
    min-width: 0 !important;
  }
}
```

---

## 4. Mega Menu Live Search Integration

### 4.1 Architecture & Design Parity
The mega menu features the dynamic Gutenberg search block `wp:align/live-search` (`.align-live-search`), styled to match the site's compact luxury search design:

| Property | Value / Specification | Description |
| :--- | :--- | :--- |
| **Container Height** | `44px` | Aligned with mega menu form proportions |
| **Border Radius** | `10px` | Soft rounded rectangle (distinct from global 9999px pill search) |
| **Border** | `1px solid var(--color-7)` | Brand red border (`#AD1A24`) |
| **Background** | `var(--color-6, #fbf9f4)` | Cream background tone |
| **Input Field** | Transparent background, 15px font | Placeholder text `#a8a8a8` |
| **Submit Button** | Transparent background, 44px width | Has left divider line `border-left: 1px solid var(--color-7)` |
| **Search Icon** | SVG Stroke `var(--color-7)` | Thin red stroke icon with no fill |
| **Focus State** | Red glow ring | `box-shadow: 0 0 0 3px rgba(173, 26, 36, 0.2)` |
| **Results Dropdown** | `z-index: 99999`, top: `calc(100% + 8px)` | High z-index popup displaying live results without clipping |

### 4.2 CSS Overrides Implementation:
```css
.mega-menu-panel .align-live-search,
.mega-menu-featured .align-live-search,
.site-header .align-live-search {
  max-width: 100% !important;
  width: 100% !important;
  height: 44px !important;
  background-color: var(--color-6, #fbf9f4) !important;
  border: 1px solid var(--color-7, #AD1A24) !important;
  border-radius: 10px !important;
  padding: 0 !important;
  position: relative !important;
  overflow: visible !important;
  transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
  box-sizing: border-box !important;
  margin-top: 1rem !important;
}

.mega-menu-panel .align-live-search:focus-within {
  box-shadow: 0 0 0 3px rgba(173, 26, 36, 0.2) !important;
}

.mega-menu-panel .align-live-search .align-ls-submit {
  position: absolute !important;
  right: 0 !important;
  top: 0 !important;
  bottom: 0 !important;
  width: 44px !important;
  height: 100% !important;
  background: transparent !important;
  border: none !important;
  border-left: 1px solid var(--color-7, #AD1A24) !important;
  border-radius: 0 9px 9px 0 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
}

.mega-menu-panel .align-live-search .align-ls-submit svg {
  width: 20px !important;
  height: 20px !important;
  color: var(--color-7, #AD1A24) !important;
  stroke: var(--color-7, #AD1A24) !important;
  fill: none !important;
}
```

---

## 5. Parent-Child Route Mapping Hierarchy

The system employs a two-tier detection algorithm:

```mermaid
graph TD
    A["User Navigates to Current URL (window.location.pathname)"] --> B{"Tier 1: Contextual Route Patterns"}
    
    B -->|/my-account/*, /login/*, /signup/*| C["Activate: HI, [USER] / LOGIN (Desktop & Mobile)"]
    B -->|/shop/*, /product/*, /product-category/*, /fluent-products/*| D["Activate: SHOP Dropdown (Desktop & Mobile)"]
    B -->|/interior-kit-builder/*, /builder/*| E["Activate: INTERIOR KIT BUILDER"]
    B -->|/the-garage/*, /blog/*, Single Posts| F["Activate: THE GARAGE"]
    B -->|/about/*, /about-us/*| G["Activate: ABOUT"]
    
    B -->|No Direct Route Match| H{"Tier 2: Dynamic Link Scanner"}
    H -->|Match inside #mega-menu-panel| D
    H -->|Match inside .align-user-dropdown-menu| C
    H -->|Match top-level link a[href]| I["Activate Exact Matched Menu Link"]
    H -->|No Match / Homepage /| J["No Active Indicator (Clean Header)"]
```

### Route Mapping Reference Table:

| Top-Level Menu Item | Target Selector (Desktop) | Target Selector (Mobile) | Managed Routes & Child Pages |
| :--- | :--- | :--- | :--- |
| **SHOP** | `#shop-dropdown-toggle` | `#mobile-shop-toggle` | `/shop/`, `/shop/*`, `/product/*`, `/products/*`, `/product-category/*`, `/product-tag/*`, `/fluent-products/*`, all links inside `#mega-menu-panel` |
| **INTERIOR KIT BUILDER** | `a.nav-link-btn` (Builder) | `a.mobile-nav-link` (Builder) | `/interior-kit-builder/`, `/interior-kit-builder/*`, `/builder/*`, `/kit-builder/*` |
| **ABOUT** | `a.nav-link-btn` (About) | `a.mobile-nav-link` (About) | `/about/`, `/about/*`, `/about-us/`, `/about-us/*` |
| **THE GARAGE** | `a.nav-link-btn` (The Garage) | `a.mobile-nav-link` (The Garage) | `/the-garage/`, `/the-garage/*`, `/garage/`, `/blog/`, single blog posts |
| **HI, [NAME] / LOGIN** | `.align-user-btn`, `.align-user-nav-wrapper .nav-link-btn` | `.mobile-user-toggle`, `.mobile-user-section .mobile-nav-link` | `/my-account/`, `/my-account/orders/`, `/my-account/payment-method/`, `/my-account/view-order/*`, `/login/`, `/login-page/`, `/signup/`, `/signup-page/`, `/register/`, `/profile/`, `/edit-account/`, all links inside `.align-user-dropdown-menu` |
| **YOUR CART** | `.cart-btn` | — | `/cart/`, `/cart/*`, `/checkout/`, `/checkout-page/` |

---

## 6. Detection Algorithm Implementation

The function `autoDetectActiveNav()` is executed at the end of the footer (`parts/footer.html` & `assets/builder/script-part-footer.js`) once the full DOM is available:

```javascript
function autoDetectActiveNav() {
  var header = document.getElementById("site-header") || document.querySelector(".site-header");
  if (!header) return;

  var currentPath = window.location.pathname.replace(/\/+$/, "").toLowerCase() || "/";

  // 1. Reset all active states in header
  header.querySelectorAll(".active").forEach(function(el) {
    el.classList.remove("active");
  });

  var bestDesktopTarget = null;
  var bestDesktopScore = -1;
  var bestMobileTarget = null;
  var bestMobileScore = -1;

  function registerDesktop(el, score) {
    if (el && score > bestDesktopScore) {
      bestDesktopScore = score;
      bestDesktopTarget = el;
    }
  }

  function registerMobile(el, score) {
    if (el && score > bestMobileScore) {
      bestMobileScore = score;
      bestMobileTarget = el;
    }
  }

  // --- TIER 1: Contextual Route Patterns (Child pages & Dynamic URLs) ---
  if (/^\/(my-account|account|login|login-page|signup|signup-page|register|edit-account|customer-logout|profile)(\/|$)/.test(currentPath)) {
    registerDesktop(header.querySelector(".align-user-btn, .align-user-nav-wrapper .nav-link-btn"), 500);
    registerMobile(header.querySelector(".mobile-user-toggle, .mobile-user-section .mobile-nav-link, .mobile-user-section"), 500);
  } else if (/^\/(shop|product|products|product-category|product-tag|fluent-products)(\/|$)/.test(currentPath)) {
    registerDesktop(header.querySelector(".has-megamenu .nav-link-btn, #shop-dropdown-toggle"), 500);
    registerMobile(header.querySelector("#mobile-shop-toggle"), 500);
  } else if (/^\/(interior-kit-builder|builder|kit-builder)(\/|$)/.test(currentPath)) {
    header.querySelectorAll(".header-nav-menu a.nav-link-btn").forEach(function(t) {
      if ((t.textContent || "").toUpperCase().indexOf("BUILDER") !== -1) registerDesktop(t, 500);
    });
    header.querySelectorAll(".mobile-nav a.mobile-nav-link").forEach(function(e) {
      if ((e.textContent || "").toUpperCase().indexOf("BUILDER") !== -1) registerMobile(e, 500);
    });
  } else if (/^\/(the-garage|garage|blog)(\/|$)/.test(currentPath)) {
    header.querySelectorAll(".header-nav-menu a.nav-link-btn").forEach(function(t) {
      if ((t.textContent || "").toUpperCase().indexOf("GARAGE") !== -1) registerDesktop(t, 500);
    });
    header.querySelectorAll(".mobile-nav a.mobile-nav-link").forEach(function(e) {
      if ((e.textContent || "").toUpperCase().indexOf("GARAGE") !== -1) registerMobile(e, 500);
    });
  } else if (/^\/(about|about-us)(\/|$)/.test(currentPath)) {
    header.querySelectorAll(".header-nav-menu a.nav-link-btn").forEach(function(t) {
      if ((t.textContent || "").toUpperCase().indexOf("ABOUT") !== -1) registerDesktop(t, 500);
    });
    header.querySelectorAll(".mobile-nav a.mobile-nav-link").forEach(function(e) {
      if ((e.textContent || "").toUpperCase().indexOf("ABOUT") !== -1) registerMobile(e, 500);
    });
  }

  // --- TIER 2: Dynamic Link Scanner (Nested Mega Menu & Submenu Links) ---
  header.querySelectorAll("a[href]").forEach(function(a) {
    var rawHref = a.getAttribute("href");
    if (!rawHref || rawHref === "#" || rawHref.indexOf("javascript:") === 0 || rawHref.indexOf("tel:") === 0 || rawHref.indexOf("mailto:") === 0) return;
    try {
      var linkUrl = new URL(a.href, window.location.origin);
      var linkPath = linkUrl.pathname.replace(/\/+$/, "").toLowerCase() || "/";
      var isExact = (currentPath === linkPath);
      var isSub = (linkPath !== "/" && (currentPath + "/").indexOf(linkPath + "/") === 0);

      if (isExact || isSub) {
        var score = linkPath.length + (isExact ? 200 : 50);

        if (a.closest("#mega-menu-panel, .mega-menu-panel")) {
          registerDesktop(header.querySelector(".has-megamenu .nav-link-btn, #shop-dropdown-toggle"), score);
          registerMobile(header.querySelector("#mobile-shop-toggle"), score);
        } else if (a.closest(".align-user-nav-wrapper, .align-user-dropdown-menu, .mobile-user-section, .mobile-user-sublist")) {
          registerDesktop(header.querySelector(".align-user-btn, .align-user-nav-wrapper .nav-link-btn"), score);
          registerMobile(header.querySelector(".mobile-user-toggle, .mobile-user-section .mobile-nav-link"), score);
        } else if (a.classList.contains("nav-link-btn")) {
          registerDesktop(a, score);
        } else if (a.classList.contains("mobile-nav-link")) {
          registerMobile(a, score);
        }
      }
    } catch (e) {}
  });

  // Apply winning active elements
  if (bestDesktopTarget) bestDesktopTarget.classList.add("active");
  if (bestMobileTarget) bestMobileTarget.classList.add("active");
}
```

---

## 7. CSS & Styling Standards

### A. Button Normalization
`<button>` elements in navigation have explicit transparent resets to prevent browser user-agent gray boxes:

```css
.nav-link-btn,
.nav-link-item {
  letter-spacing: 0.05em;
  height: 100%;
  padding: 0 20px;
  display: flex;
  align-items: center;
  background: transparent !important;
  background-color: transparent !important;
  border: none !important;
  position: relative !important;
  transition: color 0.2s ease, background-color 0.2s ease;
  line-height: var(--lh-tight);
}
```

### B. Active State & Bottom Red Indicator Bar
When any item receives `.active`:
- Background transitions to cream `#efe8db`.
- Text color switches to maroon/red `var(--color-7)` (`#ad1a24`).
- A 3px bottom indicator bar is rendered via `::after`:

```css
.nav-link-btn.active,
.nav-link-btn[aria-expanded="true"],
.nav-link-btn:hover,
.has-megamenu:hover .nav-link-btn,
.align-user-btn.active,
.align-user-btn:hover,
.align-user-nav-wrapper:hover .align-user-btn,
.align-user-btn[aria-expanded="true"] {
  background-color: #efe8db !important;
  color: var(--color-7) !important;
  border-radius: 0 !important;
  height: 100%;
}

.nav-link-btn.active::after,
.align-user-btn.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background-color: var(--color-7);
}

.mobile-nav-link.active,
.mobile-user-toggle.active {
  color: var(--color-7) !important;
}
```

---

## 8. Maintenance & Developer Guide

### Adding a New Navigation Item:
1. Open `parts/header.html`.
2. Add a new menu item within `<nav class="header-nav-menu">`:
   ```html
   <div class="nav-item-wrapper d-flex align-items-center h-100">
     <a href="/new-page/" class="nav-link-btn text-nowrap fw-bold fs-button ff-1 text-color-7 text-decoration-none px-3 py-2">NEW PAGE</a>
   </div>
   ```
3. Add the corresponding mobile item in `.mobile-nav`:
   ```html
   <a href="/new-page/" class="mobile-nav-link text-decoration-none fw-bold fs-body">NEW PAGE</a>
   ```
4. **No JS updates are required.** The generic link scanner will automatically match `/new-page/` and all its child routes `/new-page/*`.

### Modifying Mega Menu Categories:
1. When adding items to `.mega-subcol-1` or `.mega-subcol-2`, use `.mega-link` and `.text-nowrap`.
2. The columns are automatically balanced by the CSS rules (`.mega-label-col: 145px`, `.mega-subcol-1: 150px`, `.mega-subcol-2: flex: 1 1 auto`).
3. The mobile drawer script will automatically clone new items into the mobile accordion view without any manual scripting.
