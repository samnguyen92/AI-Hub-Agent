# Next State & Context Tracking: Align Theme

**Date**: 2026-10-02
**Session Goal**: Fix whole-page JavaScript error / missing WebGL 3D hero banner & component animations after saving in Align Builder.

---

## 1. Accomplishments & Architectural Changes

### A. Serializer Schema Decoupling (`align-serializer-schema.js`)
- **Location**: `align/core/builder/js/modules/align-serializer-schema.js` (402 lines, < 500 lines).
- **Namespace**: `window.AlignBuilderModules.AlignSerializerSchema`.
- **Key Features**:
  - $O(1)$ lookup via native JavaScript `Set`.
  - Comprehensive whitelist for standard HTML5/SVG attributes.
  - Whitelist of 30+ interactive datasets: `data-us-project` (Unicorn Studio WebGL 3D scene), `data-t` (button roll hover), `data-stack` (services cards stacking), `data-pop` (clients radar diagram), `data-split` (kinetic typography), `data-scene`, `data-flow`, `data-par`, `data-sp`, `data-reveal`, `data-rise`, `data-open`, `data-reveal-now`, `data-scatter`, `data-clock`, `data-top`, `data-nl`, `data-filter`, `data-filter-grid`, `data-cursor-*`, `data-theme`, `data-lang-link`, `data-480`, `data-720`, `data-1120`, `data-society`.
  - Strict conditional `MEDIA_TAGS_FOR_DATA_SRC` (`img`, `video`, `audio`, `source`, `iframe`, `picture`): non-media elements with `data-src` are stripped automatically.
  - Hard block on 100% `on*` inline event handlers (`onclick`, `onerror`, `onload`...).
  - Strict URL Anti-XSS validation (`javascript:`, `vbscript:`, `data:text/html` blocked).
  - Removal of dangerous SVG SMIL elements (`<animate>`, `<set>`, `<animatetransform>`).
  - Extensible via `window.AlignCustomSerializerRules` and `schema.extend(rules)`.

### B. Normalizer Engine Refactoring (`align-serializer.js`)
- **Location**: `align/core/builder/js/modules/align-serializer.js` (348 lines, < 500 lines).
- **Namespace**: `window.AlignBuilderModules.AlignSerializer`.
- **Key Features**:
  - Consumes `AlignSerializerSchema` with internal fallback `FALLBACK_SCHEMA` (strict default-deny).
  - Pre-serialization unwrapping pass for lingering runtime `<span class="ln">` text wrappers.
  - Clean HTML export into `wp-save-handler.js`.

### C. Canvas Isolation Guard (`align-child/assets/js/main.js`)
- **Location**: `align-child/assets/js/main.js`.
- **Key Features**:
  - Multi-tier detection `isBuilderCanvas()` detecting `#iframe1`, `Vvveb`, `AlignBuilderModules`, and editor DOM markers.
  - When in Builder Canvas:
    - Bails out before `runSplit()` (no text fragmentation into `<span>` nodes).
    - Bails out before `pxBuild()`, `pxStep()`, and continuous rAF `frame()` loop (no `is-px`, no `clip-path`, no `style="--p:..."`).
    - Statically applies `is-in` to `[data-reveal]` and `[data-rise]` so elements are immediately visible for WYSIWYG editing.
  - When outside Builder (standard visitors): runs 100% full animation pipeline without regression.

### D. Enqueue & PHP Hardening (`class-builder-ui-renderer.php`)
- **Location**: `align/core/inc/builder/class-builder-ui-renderer.php` (421 lines, < 500 lines).
- **Key Features**:
  - Injects `align-serializer-schema.js` immediately before `align-serializer.js`.
  - Enforces `current_user_can('edit_posts')` authorization guard.
  - Replaced all `json_encode` with `wp_json_encode` to prevent script breakout XSS.

### E. Database Restoration (Post 3911 - Home)
- Restored `post_content` of Post 3911 from pristine Revision 3912.
- Verified:
  - `data-us-project="7MTdZqbsnH5I6co8AFs9"`: 1 (Unicorn Studio 3D WebGL banner restored).
  - `data-t`: 10 (Button roll text hover restored).
  - `data-stack`: 1 (Services card stacking restored).
  - `data-pop`: 55 (Radar diagram pulsing circles restored).
  - 0 `is-px`, 0 `clip-path` on `.hero-pin`, 0 `--p:`, 0 `.ln` spans.

### F. Git Commit & Push to Remote (Align Core)
- **Repository**: `https://github.com/bibikeo1996/Align-Real-Builder.git`
- **Branch**: `main`
- **Commit SHA**: `6e302a3d31484ecb04ca242cc3c24165848e21ec`
- **Status**: Pushed successfully to `origin/main` (`ebebb9a..6e302a3`).

---

## 2. Updated Documentation Index

1. [`align/docs/CONTEXT.md`](file:///d:/Workspace/Personal/Align/align-live/app/public/wp-content/themes/align/docs/CONTEXT.md): Master context index updated with schema module and canvas isolation.
2. [`align/docs/builder/deterministic-clean-state.md`](file:///d:/Workspace/Personal/Align/align-live/app/public/wp-content/themes/align/docs/builder/deterministic-clean-state.md): Comprehensive 2-tier architecture guide updated.
3. [`align/docs/issues/resolved-issues.md`](file:///d:/Workspace/Personal/Align/align-live/app/public/wp-content/themes/align/docs/issues/resolved-issues.md): Sự Cố 98 documented with full RCA, solution, and QC metrics.
4. [`align-child/docs/session-features-and-enhancements.md`](file:///d:/Workspace/Personal/Align/align-live/app/public/wp-content/themes/align-child/docs/session-features-and-enhancements.md): Section 2.8 and Session 2026-10-02 checklist added.

---

## 3. Environment & Local Info

- **Local Site URL**: `http://localhost:10029/`
- **Front Page ID**: `3911` (Home)
- **MySQL Host/Port**: `127.0.0.1:10028` (user: `root`, pass: `root`, db: `local`)
- **All QC Subagent Audits**: PASS (UI & Frontend, Security & Schema, Logic & Constraints, Canvas Isolation).
