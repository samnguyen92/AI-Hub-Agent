# 📚 Align Child Theme Documentation

Technical guide, architecture reference, and development playbook for `align-child`.

---

## 🗺️ Child Theme Architecture & Documentation Index

| Topic / Section | Description & File Links |
|:---|:---|
| [**`skill-align-builder/`**](./skill-align-builder/SKILL.md) | **Autonomous AI Agent & Developer Playbook**: Core workflows (URL trace, Page ID modification, build from mockup, Shortcode component integration), Dual-Storage persistence (`wp_posts` + `assets/builder/`), Local by Flywheel WP-CLI execution, and headless DevTools verification. See [REFERENCE.md](./skill-align-builder/REFERENCE.md). |
| [**`sitemap-and-cms-page-architecture.md`**](./sitemap-and-cms-page-architecture.md) | Sitemap architecture (`/sitemap/`), Reusable Blocks overrides, style preservation through KSES bypass, and CMS page management. |
| [**`header-navigation-active-system.md`**](./header-navigation-active-system.md) | Dynamic active state resolution for desktop header links and mobile drawer navigation. |
| [**`session-features-and-enhancements.md`**](./session-features-and-enhancements.md) | Changelog and architectural notes on cart badges, UI components, and theme enhancements. |
| [**`shop-page-architecture`**](../inc/shop-page/docs/shop-page.md) | Complete Shop Page architecture: Asymmetric 3-column masonry grid, responsive `<picture>` sources (644x312 wide & 312x312 square), on-demand image cropping engine, hybrid SSR + Pretty URLs (`/shop/{category}/`), native variation photo resolution, and standalone SEO engine. |
| [**`product-details-architecture`**](../inc/details-product-page/docs/product-details.md) | Dynamic Product Details configurator: Cascading bundle trees, scrollable option grids (290px max-height), option card badge clearance, sticky gallery, image sizing engine, and fixed bottom actions bar. |
| [**`kit-builder-architecture`**](../inc/kit-builder/docs/kit-builder.md) | Multi-step vehicle configurator (Adaptive 7-Step vs 2-Step custom workflow), universal "Have a Custom Request?" bottom banner linking to Contact page ID 492, custom dropdowns, and step navigation system. |
| [**`reusable-block-template-part-isolation.md`**](./reusable-block-template-part-isolation.md) | **Reusable Block Isolation & Anti-Ghost Button Architecture**: Complete analysis and standards for reusing blocks across page body and template parts (FSE query context bleed, instance key partitioning, builder save boundaries, and DOM ghost button elimination). |

---

## 🧩 Backend PHP Modules Map (`align-child/inc/`)

All custom backend functionality is structured into modular sub-packages inside `align-child/inc/` adhering to the Single Responsibility Principle (< 500 lines per file):

| Module Folder | Purpose & Key Classes | Shortcodes / Endpoints |
|:---|:---|:---|
| **`details-product-page/`** | Product configurator controller, bundle tree recursion, option cards grid, fixed bottom bar, and draft access control. See [product-details.md](../inc/details-product-page/docs/product-details.md). | `[fluentcart_product_details]` |
| **`builder-highlights/`** | Dynamic queries for blog posts in `Builder Highlights` category. Renders Spotlight and Grid layouts matching Section 7 designs. | `[align_builder_highlights]`<br>`[builder_highlights]` |
| **`single-post/`** | Atomic dynamic field shortcodes for Single Post templates. | `[align_post_title]`, `[align_post_excerpt]`, `[align_post_hero_image]`, etc. |
| **`archive-page/`** | Unified responsive card rendering for both FluentCart Products and Blog Posts in archive/search views. | `Align_Archive_Card_Renderer` |
| **`shop-page/`** | Shop catalog layout with interactive Model tabs, responsive `<picture>` card rendering (`Align_Shop_Page_Card_Renderer`), on-demand cropping, and SSR/AJAX virtual catalog pipeline. See [shop-page.md](../inc/shop-page/docs/shop-page.md). | `[align_shop_page]` |
| **`kit-builder/`** | Multi-step interactive vehicle interior kit configurator (Adaptive 7-step & 2-step custom workflow, universal custom request banner, Fluent Form 7). See [kit-builder.md](../inc/kit-builder/docs/kit-builder.md). | `[align_kit_builder]` |
| **`core/`** | Core FluentCart integrations: Product variations, bundles, paired pricing, extra metadata, and sticky header cart. | `Align_FluentCart_*` |
| **`api-custom-fluentcart/`** | Custom REST API endpoints for batch operations, paired pricing, and taxonomy lookup. | `/wp-json/align/v1/fluentcart/*` |
| **`login-page/` & `checkout-page/`** | Dedicated page handlers, AJAX actions, and custom form templates. | Custom login & checkout workflows |

---

## ⚡ Environment & CLI Quick-Reference (Local by Flywheel)

On macOS with Local by Flywheel, standard shell `wp` or `php` may lack the correct MySQL socket. Always use this command wrapper:

```bash
# Template for running WP-CLI commands reliably:
/Volumes/JoeSSD/SymLinks/Local_System/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php \
  -d "mysqli.default_socket=/Users/joe/Library/Application Support/Local/run/33aX1oovp/mysql/mysqld.sock" \
  /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp \
  --path=/Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto/app/public {COMMAND}
```
