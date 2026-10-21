---
name: multi-page-site
description: Plan, approve and deliver a complete multi-page website in staged human-reviewed checkpoints.
---

# Staged Multi-page Website Workflow

Use this workflow for website requests that need more than one page. Do not jump directly to implementation.

## Conversation continuity

- Treat each user message in the same project thread as a continuation unless the user clearly changes project or scope. Re-read the approved sitemap, latest artifact, prior user feedback, and current gate state before acting.
- Resolve short replies such as “okay”, “duyệt”, or “continue” against the latest pending checkpoint. An approval advances that checkpoint; it does not restart the workflow or authorize unrelated scope.
- Treat follow-up feedback as edits to the current artifact. Preserve approved decisions and update only affected pages, shared components, previews, and package outputs.
- Do not ask the user to repeat information already present in the thread, project notes, approved sitemap, or reference library. Ask only when a missing choice materially changes the result.
- Report the current stage and the concrete next action in concise Vietnamese. Never claim an unbuilt page, unrun check, or nonexistent ZIP is ready.

## Stage 1: Understand the brief and propose the sitemap

1. Summarize the audience, business goal, primary action, content, constraints, and requested visual direction. Load the relevant design skills and Align style guide.
2. Propose a concise sitemap. For each page include its path, purpose, key sections, and main action. Keep the site scope proportional to the brief; do not add generic pages without a reason.
3. Present the sitemap to the user and open a `plan` gate with `open_gate`. Include the sitemap and ask the user to approve or request changes. Stop after opening the gate. Do not collect references or write website pages before the sitemap is approved.
4. If the user requests rework, revise the sitemap from their feedback and present it for approval again.

## Stage 2: References, analysis, and design direction

After sitemap approval:

1. Search the project's reference library with `list_library(type_filter="reference")`.
2. If the user supplied URLs, capture them with `capture_reference`; analyze their desktop/mobile screenshots and metadata. If no references were supplied, suggest up to three relevant candidates with `fetch_url` verification and ask the user to choose before capturing them. Do not delay by asking if reference selection is optional and no candidate is needed; state the assumption.
3. Record concrete observations and explain how they inform this project's layout, typography, color, content hierarchy, and interaction. Learn principles; do not copy source content, branding, assets, or code.
4. Define shared design tokens, responsive behavior, accessibility requirements, and a motion spec. Choose from CSS transitions/keyframes, JavaScript runtime, visual animation, and WebGL/GLSL shaders only where they serve the brief. Include reduced-motion behavior and a practical fallback/performance budget.
5. Design and implement the homepage only. Put all site files under one dedicated folder, using relative links and reusable local assets/styles/scripts. At this point, do not create the remaining pages or final ZIP.

## Stage 3: Homepage review checkpoint

1. Render desktop (1440px) and mobile (390px) homepage previews, inspect them, and fix observed issues. Run the design review checklist.
2. Present the homepage HTML link and actual desktop/mobile screenshots, concise design rationale, motion behavior, review findings, and known limitations. State clearly if screenshots were not visually inspected or interactions were not exercised.
3. Open a `design` gate using `open_gate`. Ask whether the user approves the homepage to continue with the remaining sitemap pages, or wants changes. Stop after opening the gate.
4. Approval means continue with the approved sitemap and design system. Rework means revise the homepage based on the user's comments and request approval again. Rejection means stop the delivery.

## Stage 4: Complete the site after homepage approval

1. Create the remaining pages from the approved sitemap. Ensure each page has real, page-specific content and a consistent navigation, footer, visual system, and responsive layout.
2. Create `sitemap.html` listing every implemented page with working relative links and short descriptions. Add it to the site footer/navigation. Create `sitemap.xml` only when the user supplied a canonical site URL.
3. Check relative links, section IDs, local assets, page-specific SEO metadata, responsive consistency, and accessibility across all pages. Render and inspect every page at desktop and mobile, including local CSS/JS/assets; exercise key navigation and controls. A successful file write or static link scan is not a browser review.
4. Use the workspace-aware `render_html_to_png` preview for pages with relative assets so Playwright loads the actual local CSS, JS, and images. If rendering fails or an asset is missing, disclose the limitation and do not mark visual review passed.
5. Call `package_design` with the site root, entry HTML, and sitemap HTML. Rebuild it after the last edit. The resulting ZIP must include the complete site and its local assets.
6. Return the homepage HTML link, links for every other page, sitemap link, ZIP download link, screenshots, the exact page list, and a concise evidence-based list of checks and limitations. Preserve the existing standalone HTML preview behavior.

## Delivery rules

- The sitemap proposal and homepage are intentional checkpoints. Never generate all pages or the ZIP before homepage approval.
- Use the final `schemas/design_output.json` structure only for the completed website response. Intermediate sitemap and homepage checkpoint messages are not final delivery JSON and must not claim that a ZIP or unbuilt pages exist.
- Keep every website page independently openable and use relative references so the unzipped site works locally.
