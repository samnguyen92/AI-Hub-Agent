---
name: motion-design
description: Specify restrained, useful, accessible motion as part of a web design handoff.
---

# Motion Design

Treat motion as part of the interaction design. Use it to show state changes, guide attention, or explain spatial relationships. Do not animate every section or use movement to compensate for weak hierarchy.

## Motion Spec

For each motion that matters, record:

- Element or component.
- Trigger: entrance, hover, focus, click, scroll, or state change.
- Properties to animate, preferably `transform` and `opacity`.
- Duration and easing.
- Reduced-motion behavior.
- Whether the effect must be verified by interacting with the page.
- Motion family: `css_transition`, `css_keyframes`, `javascript_runtime`, `visual_animation`, or `webgl_glsl`.
- Runtime and fallback: required browser capability, static/CSS fallback, and expected CPU/GPU cost.
- Performance budget: when the effect starts/stops, mobile quality limits, and any frame or asset-size target relevant to the effect.

Example:

```json
{
  "family": "css_transition",
  "element": ".feature-card",
  "trigger": "hover and focus",
  "properties": ["transform", "box-shadow"],
  "duration_ms": 180,
  "easing": "ease-out",
  "reduced_motion": "remove transform; preserve state feedback",
  "runtime_fallback": "keep color and focus feedback without movement",
  "performance_budget": "transform only; no continuous rendering"
}
```

## Design Rules

- Keep interaction transitions short and consistent; use longer movement only for a clear entrance or large layout transition.
- Prefer a small motion vocabulary shared across the page over unrelated effects.
- Avoid autoplaying, looping, flashing, parallax-heavy, or large-distance movement unless the brief calls for it.
- Preserve clear hover, focus-visible, active, and disabled states without relying on motion alone.
- Define a reduced-motion alternative for every nonessential animation.
- Include motion in the handoff even when the correct choice is no animation.

## Motion Families

Choose the simplest family that meets the brief; mention the family in each motion spec.

| Family | Use for | Constraints |
| --- | --- | --- |
| CSS transitions | Hover, focus, toggles, small state changes | Prefer transform/opacity; keep feedback immediate. |
| CSS keyframes | Short entrance, loading indicator, one-off emphasis | Avoid decorative infinite loops; support reduced motion. |
| JavaScript runtime | Scroll sequencing, gesture response, coordinated timelines | Use an existing project runtime where available; avoid adding packages without approval. |
| Visual animation | SVG/Lottie/canvas illustration or data visualization | Keep a static or reduced-motion alternative and avoid blocking content. |
| WebGL & GLSL shaders | High-impact 3D, generative backgrounds, shader effects | Treat as progressive enhancement; cap quality on mobile, pause offscreen, and provide a non-WebGL fallback. |

Do not choose WebGL/GLSL for ordinary UI feedback. Specify the user benefit, runtime requirement, fallback, performance budget, and verification method before implementation.
