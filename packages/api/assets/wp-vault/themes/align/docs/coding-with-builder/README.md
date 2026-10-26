# AI Coding with Align Builder — Reference Hub

> Load this README once → understand the complete coding system → navigate to the right file.

This folder contains the complete technical reference for AI Agents and Developers to **build pages, blocks, and shortcodes** within the Align Theme + Align Child Theme ecosystem.

---

## When to Use This Folder

Load files from here when you need to:
- Build or modify a page in Align Builder
- Write CSS that integrates with the existing design system
- Use existing JS patterns (sliders, AJAX, lazyload)
- Insert shortcodes or Reusable Blocks correctly
- Query WordPress data (DB, REST, WP-CLI)
- Understand the difference between Page / Template / Parts / Reusable Block

---

## File Index

| File | Purpose |
|:---|:---|
| [**`content-types.md`**](./content-types.md) | **START HERE**: Difference between Page, Template, Template Part, and Reusable Block. Storage, ID format, asset naming, and shortcode usage for each. |
| [**`asset-system.md`**](./asset-system.md) | Dual-storage persistence (DB + physical file), file naming conventions, enqueue logic, conditional CSS vs scoped CSS. |
| [**`css-design-tokens.md`**](./css-design-tokens.md) | All design tokens from `root.css`: colors, spacing, typography, grid, utility classes. Shared section CSS classes. Zero hardcoded hex rule. |
| [**`js-patterns.md`**](./js-patterns.md) | Splide slider init, Query Builder AJAX pagination, lazyload update, shortcode component container, custom navigator events. |
| [**`data-query-patterns.md`**](./data-query-patterns.md) | Querying DB by post ID or slug, REST API, WP-CLI, attachment slug collision guard, page identity extraction from URL. |
| [**`shortcode-reference.md`**](./shortcode-reference.md) | All registered shortcodes: Reusable Block, Builder modules, single post, checkout, language switcher. Shortcode container pattern for Builder canvas. |

---

## Quick Decision Tree

```
User asks to BUILD / EDIT a page?
  ├── Has Page URL?   → data-query-patterns.md (Scenario 1)
  ├── Has Page ID?    → data-query-patterns.md (Scenario 2)
  └── From scratch?   → content-types.md + asset-system.md + css-design-tokens.md

User asks about CSS class names?
  └── css-design-tokens.md

User asks to insert a dynamic component / shortcode?
  └── shortcode-reference.md

User asks about slider / AJAX / lazyload JS?
  └── js-patterns.md

User is confused about which type to create (page vs block vs part)?
  └── content-types.md (most important file)
```

---

## Core Rules (Always Apply)

1. **Zero hardcoded hex**: All colors via `var(--color-*)`. All spacing via `var(--sp-*)`.
2. **Dual-storage mandatory**: Every save updates BOTH `post_content` in DB AND the physical file in `assets/builder/`.
3. **Lazyload after AJAX**: Always call `window.lazyLoadInstance.update()` after injecting HTML via AJAX.
4. **Shortcode container in Builder**: Never insert raw `[shortcode]` text. Always wrap with `data-component-shortcode` container.
5. **KSES bypass for style tags**: Use `$wpdb->update()` directly — never `wp_update_post()` when content contains `<style>` or `<script>`.
6. **File size limit**: All custom PHP/JS/CSS files must stay under 500 lines (AGENTS.md rule).

---

## Related Docs (Core Theme)

- Builder architecture: [`../builder/builder-guide.md`](../builder/builder-guide.md)
- Builder file map: [`../builder/builder-deep-dive.md`](../builder/builder-deep-dive.md)
- Serializer (DOM clean state): [`../builder/deterministic-clean-state.md`](../builder/deterministic-clean-state.md)
- Align Studio (Reusable Block editor): [`../builder/align-studio-guide.md`](../builder/align-studio-guide.md)
- REST API for AI agents: [`../api/ai-rest-api-reference.md`](../api/ai-rest-api-reference.md)
- Responsive image shortcode rules: [`../builder/responsive-media-shortcode-guide.md`](../builder/responsive-media-shortcode-guide.md)
