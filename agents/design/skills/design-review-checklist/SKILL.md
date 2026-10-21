---
name: design-review-checklist
description: Review visual quality, responsive behavior, navigation, SEO, accessibility, references, and motion before delivery.
---

# Design Review Checklist

Use evidence from the actual project files and browser previews. Do not mark an item passed when it was not checked. Record `not checked` and why when a tool or environment is unavailable.

## 1. Brief, brand, and references

- The design matches the approved brief, audience, business goal, and brand direction.
- Do not assume dark mode, glassmorphism, gradients, or a specific visual trend. Use them only when they fit the brief or approved reference.
- Distinguish verified business/product facts from assumptions and concept copy.
- Record reference URL/ID, what was inspected (DOM, screenshot, viewport, interaction), what was adapted, and what was intentionally not copied.
- Never label generated or illustrative imagery as an actual product or location photograph.

## 2. Visual hierarchy and content

- Each page has one clear H1, a readable heading hierarchy, useful content, and a primary action appropriate to that page.
- Typography, spacing, contrast, imagery, and layout support the content rather than repeat a generic template.
- Check image crop, loading state, alt text, and whether important content remains visible at every viewport.
- Confirm generated files are readable and maintainable: format HTML/CSS/JS, use semantic sections, and keep shared styles/scripts reusable where appropriate.

## 3. Responsive and interaction review

- Render at desktop 1440px and mobile 390px; include tablet when layout changes materially around that breakpoint.
- Check horizontal overflow, clipped content, hidden categories/CTAs, image crop, text size, and touch targets (minimum 44px where practical).
- Exercise navigation, mega menus, buttons, links, forms, and any animation states with keyboard and pointer/touch where supported.
- Check focus-visible styling and reduced-motion behavior. A static screenshot does not prove an interaction or animation works.
- If only screenshots were captured, explicitly say interaction and motion behavior were not verified.

## 4. Navigation, pages, and assets

- Compare all internal links to the approved sitemap. Main navigation labels should open the intended page; use a fragment only when an in-page jump is intentional and visibly useful.
- Check every local `href`, `src`, and CSS asset path resolves after unzipping and opening the entry HTML locally.
- Check every sitemap entry exists and every implemented page is represented in `sitemap.html`.
- Verify links to sections point to IDs that exist. Avoid duplicate or misleading labels and dead-end pages.
- Render and inspect every page at desktop and mobile before final packaging; do not infer the quality of secondary pages from the homepage.

## 5. SEO, accessibility, and delivery

- Each page has a unique title, description, language, viewport, semantic landmarks, descriptive link text, and appropriate image alt text.
- Review heading order, keyboard access, color contrast, labels, and responsive behavior. Do not claim WCAG conformance from a basic static scan alone.
- Add canonical and absolute social-image URLs only when the deployment origin is known. Do not invent prices, stock, contact details, opening hours, or structured data.
- Rebuild the ZIP after the final edit. Confirm it contains the complete site, relative assets, styles, scripts, and all sitemap pages.
- Final response links to the actual generated files and states what was and was not verified.

## 6. Evidence report

Return a short review with `passed`, `needs work`, or `not checked`, a concrete observation, and the evidence source (screenshot path/viewport, file path, browser action, or tool result). Prioritize issues as blocking, important, or polish. Fix blocking issues before requesting approval or packaging.
