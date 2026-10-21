---
name: reference-analysis
description: Analyze user-provided or approved website references and preserve them in the project library.
---

# Website Reference Analysis

## Workflow

1. Call `list_library` with `type_filter: "reference"` and review existing captures before requesting a new URL.
2. If the user supplied a URL, use `capture_reference` to validate and save it. If there is no URL, offer up to three relevant candidate websites or reference directions. Verify candidate URLs with `fetch_url` and `save_snapshot: false` before presenting them; report the fetched page title and status, and do not claim to have visually reviewed the page until it is captured. Save a candidate only after the user picks it.
3. Save a short note on what the user wants to learn from each reference, plus useful tags such as `editorial`, `saas`, `commerce`, `typography`, or `motion`.
4. Analyze captured desktop/mobile screenshots, computed-style and DOM summaries, plus title, description, and H1 data. A capture tool may provide screenshots and page summaries without exposing the pixels to the model; only claim visual inspection when the screenshot was actually supplied to a vision-capable review tool or inspected by the agent.
5. Separate reusable design principles from source-specific content and assets. Recreate the principles for the user's brand; do not copy source HTML, text, logos, illustrations, or proprietary assets.
6. Carry reference IDs, URLs, and selected observations into the design handoff so Code Builder can find them in the project library.

## Reference Notes

For each reference, record:

- What to study: composition, type scale, color, spacing, imagery, interactions, or motion.
- What to adapt for this brief and brand.
- What to avoid copying directly.
- Which pages or viewport captures were actually inspected.

Prefer 1–3 relevant references over a large unfiltered list. If the URL cannot be safely captured or returns no HTML page, report that and ask for another URL.

## Browser capability and reporting

- The current Hub sandbox uses headless Playwright to load public URLs, capture full-page desktop/mobile PNGs, and collect basic rendered DOM/style observations. Workspace previews can load local relative CSS/JS/assets. The optional `inspect_screenshots` tool can pass up to two screenshots to a configured vision model.
- These tools do not currently record browser video or provide a general interactive browser session. A screenshot is a static frame; it cannot establish animation timing, hover/focus states, scroll behavior, or form behavior.
- If analysis depends on motion or interaction, state the gap and request a video/action trace or use an explicitly available browser interaction tool. Never imply that a site was recorded or fully explored when only screenshots were captured.
