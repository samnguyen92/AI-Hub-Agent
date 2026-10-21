# Reference analysis — Viet Thanh Crocodile

- Homepage: https://vietthanhcrocodile.vn/
- Company story: https://vietthanhcrocodile.vn/our-company/
- Journal: https://vietthanhcrocodile.vn/journals/
- Store location: https://vietthanhcrocodile.vn/store-locators/
- Homepage captured at desktop 1440 × 1000 (`previews/reference-desktop.png`). The direct web reader initially timed out; Chromium loaded and captured the page.
- User-supplied brief in AI Hub chat: brand Việt Thành; collection mega menu divided into bags, wallets, and accessories.

## Observations

- Warm ivory canvas, restrained navigation, editorial typography, and prominent product photography establish a quiet luxury tone.
- Homepage moves from collection highlights into brand/craft storytelling and journal content.
- The company page states that Viet Thanh was established in 1988. Store locator lists 137 Dong Khoi Street, Ben Nghe Ward, District 1, Ho Chi Minh City.
- The brand's own sourcing, certification, and production claims are not repeated unless represented by source-backed details.

## Adaptations in this homepage

- Kept the warm neutral palette, product-led hero, generous spacing, and craft storytelling.
- Added the requested three-category mega menu, a journal section linked to published source articles, and a contact section linking to official contact/store pages.
- Created original AI-generated illustrative photography for the crocodile-leather hero, product categories, and craft section. These are concept images, not verified inventory photographs.
- Motion is limited to CSS hover/reveal transitions and a small mobile navigation interaction. Reduced-motion preferences are respected; no WebGL/GLSL is used because it does not serve this editorial direction.

## SEO scope

- Added Vietnamese page title/description, Open Graph title/description/locale/type, robots metadata, semantic headings, descriptive image alt text, high-priority hero image loading, lazy-loading for below-fold images, and Organization JSON-LD using the verified brand/store details.
- Canonical URL and absolute Open Graph image are omitted until the deployment domain/path is confirmed.

## Library persistence

The reference screenshot and notes remain alongside the local artifact. They were not added to the application's project reference library because this workspace session has no active project ID or access to the agent's `capture_reference` tool.


## Navigation QA and follow-up

- User spotted that header links such as “Nghề thủ công” opened `about.html#craft`, which both changed route and scrolled past the page introduction. Main navigation and footer links now open the destination page at its top; category mega-menu links also open the collection page consistently.
- Workflow improvement: after page generation, audit all internal links against the approved sitemap and check that navigation labels resolve to the intended page. Keep fragment links only where a deliberate in-page jump is part of the interaction.
- Maintainability improvement: secondary HTML files were emitted as single-line documents. Reformat generated HTML into readable, reviewable structure before treating this output as a production handoff.
- SEO production follow-up: set canonical URLs and absolute Open Graph image URLs once a stable deployment domain is known.
