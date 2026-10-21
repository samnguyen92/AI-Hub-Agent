---
name: motion-implementation
description: Implement the design motion spec with accessible CSS-first interactions and verify the result.
---

# Motion Implementation

## Before Coding

1. Read the latest design artifact and motion spec. Inspect the references named in the handoff through `list_library` and `read_item`.
2. Identify the supported framework and existing dependencies in the workspace. Choose among CSS transitions, CSS keyframes, JavaScript runtime, visual animation (SVG/Lottie/canvas), and WebGL & GLSL shaders according to the approved spec. Do not add a package without approval.
3. Map each specified trigger to a real interaction or state. Do not animate controls that have no meaningful action.

## Implementation Rules

- Centralize repeated durations, easing curves, and distances as CSS custom properties.
- Prefer `transform` and `opacity` for continuous movement; avoid animating layout-heavy properties where possible.
- Implement `:focus-visible` alongside hover states. Keep keyboard and touch feedback usable.
- Add `@media (prefers-reduced-motion: reduce)` to remove or shorten nonessential animation while preserving state changes.
- Respect the design reference without reproducing its source code, text, logo, or assets.
- Avoid animation that blocks content, causes layout shift, or runs indefinitely without a user need.
- Use WebGL/GLSL only when its visual value justifies GPU and battery cost. Provide a static fallback, cap mobile resolution/quality, pause when hidden, and handle WebGL context loss.
- Keep JavaScript timelines interruptible and clean up listeners, observers, animation frames, and runtime instances on unmount.
- For SVG/Lottie/canvas, preserve accessible text/state outside the animation and honor reduced motion.

## Verification

1. Run the configured HTML/accessibility checks and review their findings.
2. Render desktop and mobile previews after implementation.
3. Exercise any click, hover, focus, or scroll-triggered motion in a browser-capable check when available. A static screenshot alone does not prove an interaction works.
4. Compare the implementation with the approved mockup and report any motion states that could not be exercised.
