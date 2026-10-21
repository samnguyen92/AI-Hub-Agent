---
name: design-quality-rubric
description: Score website design and implementation consistently using observable evidence.
---

# Design Quality Rubric

Score each category from 0 to 4. Use `not assessed` when evidence is missing; do not award a zero for missing tooling. For assessed categories, calculate weighted points as `category weight × score / 4`. Divide earned points by the total weight of assessed categories, then multiply by 100. If the motion requirement is explicitly absent, exclude its 10% weight from the denominator and state that exclusion.

| Category | Weight | Evidence to inspect |
| --- | ---: | --- |
| Visual hierarchy and task clarity | 20 | Screenshot, heading order, primary action visibility, content grouping. |
| Typography, spacing and consistency | 15 | Screenshot, computed styles, token use and repeated component patterns. |
| Brief, brand and reference fit | 20 | User brief, approved tokens, `references_used`, attributed observations and adaptations. |
| Responsive behavior | 20 | Desktop/mobile screenshots, viewport dimensions, overflow and visible controls. |
| Accessibility | 15 | `run_checks`, semantic structure, labels, contrast evidence, focus state and keyboard behavior when exercised. |
| Motion quality | 10 | Motion family/spec, reduced-motion path, timing, fallback and interaction evidence. Use 0 weight when the brief has no motion requirement and report that choice. |

## Score Anchors

- **0 — Broken:** core task cannot be completed or the category has a critical failure.
- **1 — Weak:** substantial issues make the experience confusing, inaccessible or visibly inconsistent.
- **2 — Partial:** core intent works, but there are clear issues that should be fixed before release.
- **3 — Strong:** meets the brief with only small, non-blocking issues.
- **4 — Excellent:** clear, consistent and polished; evidence shows the category meets the brief across relevant states.

## Release Decision

- **90–100:** ready when there are no critical findings and all required evidence is present.
- **80–89:** conditionally ready; list each remaining warning and owner/action.
- **Below 80:** needs revision.
- Any critical accessibility failure, broken primary task, missing mobile layout, or unhandled required reduced-motion behavior blocks release regardless of total.

## Reporting

Use a markdown table with category, score, weighted points, severity, and concrete evidence. Include the calculation and status for every `not assessed` category. Cite screenshot paths, source files, tool results, or reference IDs. Do not claim pixel-level inspection unless `inspect_screenshots` returned image observations. Screenshots cannot prove hover, keyboard, scroll or animation behavior; mark those states unverified unless exercised.
