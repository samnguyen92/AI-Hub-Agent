# CSS Design Tokens & Utility Classes

All CSS in Align Builder pages MUST use design tokens from `assets/root.css`. Zero hardcoded hex colors or arbitrary pixel values — only CSS variables.

**Source file**: `align-child/assets/root.css`

---

## Rule #1: Zero Hardcoded Values

```css
/* WRONG - never do this */
color: #080808;
background-color: #FFFBF5;
padding: 80px 0;
gap: 32px;

/* CORRECT - always use tokens */
color: var(--color-1);
background-color: var(--color-10);
padding: var(--sp-xl) 0;
gap: var(--sp-base);
```

---

## 1. Color Palette (`--color-1` to `--color-10`)

| Variable | Hex | Name | Common Usage |
|:---|:---|:---|:---|
| `--color-1` | `#080808` | Black | Primary text, darkest backgrounds |
| `--color-2` | `#1F1F1F` | Coal | Dark section backgrounds |
| `--color-3` | `#414141` | Grey 2 | Secondary text, links |
| `--color-4` | `#757575` | Grey 1 | Muted text, placeholders |
| `--color-5` | `#A8A8A8` | Grey 0 | Disabled states, borders |
| `--color-6` | `#FFFFFF` | White | Pure white backgrounds |
| `--color-7` | `#AD1A24` | Engine Red | Brand accent, buttons, CTAs |
| `--color-8` | `#FE372C` | Tomato | Hover states, sale badges |
| `--color-9` | `#F9F2E8` | Cream | Warm section backgrounds |
| `--color-10` | `#FFFBF5` | Light Cream | Page background default |

### RGB variants (for rgba() usage)
```css
background: rgba(var(--color-1-rgb), 0.6);   /* = rgba(8, 8, 8, 0.6) */
background: rgba(var(--color-7-rgb), 0.2);   /* = rgba(173, 26, 36, 0.2) */
```
Available: `--color-1-rgb` through `--color-10-rgb`

### Opacity tokens
```css
--op-5: 0.04;   --op-10: 0.1;   --op-20: 0.2;   --op-30: 0.3;
--op-40: 0.4;   --op-50: 0.5;   --op-60: 0.6;   --op-70: 0.7;
--op-80: 0.8;   --op-90: 0.9;
```

### Color utility classes
```html
<!-- Text color -->
<span class="text-color-7">Engine Red text</span>
<span class="text-color-4">Muted grey text</span>

<!-- Background color -->
<section class="bg-color-10">Light cream section</section>
<div class="bg-color-1">Black background</div>

<!-- Border color -->
<div class="border-color-7">Red border</div>
```

---

## 2. Typography Scale

### Font families
```css
--font-primary-1: 'font-primary-1', 'Barlow', sans-serif;   /* Main body font */
--font-primary-2: 'font-primary-2', sans-serif;               /* Accent / display font */

/* Aliases */
--font-family-1: var(--font-primary-1);
--font-family-2: var(--font-primary-2);
--bs-body-font-family: var(--font-primary-1, 'Barlow', sans-serif);
```

### Font size scale (responsive — values change at breakpoints)

| Variable | Desktop (>992px) | Tablet (491-992px) | Mobile (<490px) |
|:---|:---|:---|:---|
| `--fs-h1` | 58px | 48px | 40px |
| `--fs-h2` | 50px | 42px | 36px |
| `--fs-h3` | 42px | 36px | 30px |
| `--fs-h4` | 32px | 28px | 24px |
| `--fs-h5` | 24px | 22px | 20px |
| `--fs-h6` | 20px | 18px | 18px |
| `--fs-eyebrow` | 16px | 15px | 14px |
| `--fs-body` | 16px | 15px | 14px |
| `--fs-body-light` | 16px | 15px | 14px |
| `--fs-button` | 14px | 14px | 14px |
| `--fs-small` | 12px | 11px | 10px |

### Font weight tokens
```css
--fw-regular: 400;
--fw-medium: 500;
--fw-semibold: 600;
--fw-bold: 700;
```

### Line height tokens
```css
--lh-tight: 1.0;      /* Headings compact */
--lh-snug: 1.2;       /* Headings normal */
--lh-normal: 1.3;     /* Default body */
--lh-relaxed: 1.5;    /* Body text / long reads */
```

### Typography utility classes
```html
<h1 class="fs-h1 fw-bold lh-snug">Big heading</h1>
<p class="fs-body lh-relaxed">Body paragraph</p>
<span class="fs-eyebrow fw-semibold text-color-7">EYEBROW LABEL</span>
<small class="fs-small text-color-4">Fine print</small>

<!-- Font family -->
<div class="ff-1 font-primary-1">Primary font</div>
<div class="ff-2 font-primary-2">Accent font</div>
```

---

## 3. Spacing Scale

| Variable | Value | Usage |
|:---|:---|:---|
| `--sp-3xl` | 112px | Large section separators |
| `--sp-2xl` | 96px | XL section padding |
| `--sp-xl` | 80px | Standard `.spacing-top-bottom` value (desktop) |
| `--sp-lg` | 64px | Large gap between elements |
| `--sp-md` | 48px | Medium section padding, card padding |
| `--sp-base` | 32px | Base gap, floating buttons offset |
| `--sp-sm` | 24px | Content gap, smaller padding |
| `--sp-xs` | 16px | Small gap, icon spacing |
| `--sp-2xs` | 12px | Tight spacing |
| `--sp-3xs` | 8px | Micro spacing |
| `--sp-4xs` | 4px | Hairline gap |

### Spacing utility classes
```html
<!-- Padding utilities: sp-p-*, sp-pt-*, sp-pb-*, sp-px-*, sp-py-* -->
<div class="sp-py-xl">80px top/bottom padding</div>
<div class="sp-px-md">48px left/right padding</div>

<!-- Margin utilities: sp-mt-*, sp-mb-* -->
<div class="sp-mt-base sp-mb-sm">32px top, 24px bottom margin</div>

<!-- Gap utilities: sp-gap-* -->
<div class="row sp-gap-sm">24px gap between columns</div>
```

---

## 4. Border Radius Tokens

```css
--radius-none: 0;        /* Sharp corners — default for most sections */
--radius-sm: 4px;        /* Subtle rounding */
--radius-md: 8px;        /* Cards, panels */
--radius-lg: 16px;       /* Newsletter form, large containers */
--radius-pill: 9999px;   /* Buttons, badges */
```

---

## 5. Z-Index Scale

```css
--z-behind: -1;      /* Behind page content (background elements) */
--z-base: 0;         /* Default stacking */
--z-content: 1;      /* Content above base */
--z-overlay: 10;     /* Dropdowns, tooltips */
--z-sticky: 50;      /* Sticky elements */
--z-header: 100;     /* Site header (always above content) */
--z-modal: 1000;     /* Modals, drawers, floating actions */
--z-toast: 2000;     /* Toast notifications (topmost) */
```

---

## 6. Layout System

### Container
```css
.container-1440 {
  max-width: 1300px;        /* --container-max */
  margin-left: auto;
  margin-right: auto;
  width: 100%;
}
```

### Section vertical spacing
```css
.spacing-top-bottom {
  padding-top: 80px;    /* --sp-xl desktop */
  padding-bottom: 80px;
}
/* Mobile (≤992px): 40px top/bottom */
```

### Bootstrap 5 Grid (available in root.css)
```html
<div class="row g-4">
  <div class="col-12 col-md-6 col-lg-4">Column 1</div>
  <div class="col-12 col-md-6 col-lg-4">Column 2</div>
  <div class="col-12 col-md-12 col-lg-4">Column 3</div>
</div>
```

Available grid classes:
- `.col`, `.col-auto`, `.col-6`, `.col-4`, `.col-8`, `.col-12`
- `.col-sm-*` (≥576px), `.col-md-*` (≥768px), `.col-lg-*` (≥992px), `.col-xl-*` (≥1200px)
- Gutters: `.g-0` through `.g-5`, `.gx-*`, `.gy-*`, `.g-lg-*`, `.g-md-*`

---

## 7. Shared Component Classes (Conditional CSS)

These classes exist in `assets/src/general/conditional-css/` — reuse them directly in page HTML.

### Hero Banner Full (`banner.css`) — Reuse Block ID: 27
```css
#align-hero-banner         /* Full-height hero wrapper (min-height: 100vh) */
#align-hero-banner .container-1440  /* Flex column centered */
.glass-box                 /* Glassmorphism text card (backdrop-filter: blur(10px)) */
.floating-actions          /* Fixed floating button group (bottom-right) */
.floating-btn              /* 56x56 floating action button */
```

### Hero Banner Half (`banner-half.css`) — Reuse Block ID: 85, 611
```css
#align-hero-banner-half    /* Half-height hero (min-height: 52vh) */
#hero-banner               /* Alias ID used in Reuse Block 611 */
body.admin-bar #align-hero-banner-half   /* Admin bar offset (padding-top: 140px) */
.glass-box                 /* Same glassmorphism card as full banner */
.floating-actions          /* Same floating actions */
.floating-btn              /* Same floating button */
```

### CTA Section (`cta.css`) — Reuse Block ID: 32
```css
.custom-quote-bg           /* CTA wrapper with absolute background image */
.glass-card                /* Frosted glass card (backdrop-filter: blur(16px)) */
.btn-custom-quote          /* Primary CTA pill button (Engine Red) */
```
```html
<!-- Usage example: -->
<section class="custom-quote-bg">
  <div class="container-1440 spacing-top-bottom text-center">
    <div class="glass-card mx-auto">
      <h2>Get a Custom Quote</h2>
      <a href="/contact/" class="btn-custom-quote">
        Request Quote
        <svg>...</svg>
      </a>
    </div>
  </div>
</section>
```

### Newsletter Section (`newsletter.css`) — Reuse Block ID: 34
```css
.newsletter-section        /* Full-width newsletter wrapper */
.newsletter-content        /* Centered flex column (max-width: 680px) */
.newsletter-title          /* Uppercase heading */
.newsletter-description    /* Subtitle (max-width: 520px) */
.newsletter-form           /* Red-bordered pill form container */
.newsletter-input          /* Transparent email input */
.newsletter-submit         /* Engine Red submit button */
```

---

## 8. Base Component Classes (from root.css)

```css
/* Images */
.img-fluid             /* max-width: 100%, height: auto */

/* Lists */
.list-unstyled         /* Remove list bullets and padding */

/* Forms */
.btn                   /* Base button reset */
.form-control          /* Standard text input / textarea */
.form-select           /* Custom dropdown select */
.input-group           /* Input + button combination wrapper */
.input-group-text      /* Inline label within input-group */
.form-label            /* Form field label */

/* Badges */
.badge                 /* Inline badge/count indicator */
```

---

## 9. Breakpoint Reference

```css
/* Desktop: min-width 993px (default / no media query) */

/* Tablet */
@media (min-width: 491px) and (max-width: 992px) { }

/* Mobile */
@media (max-width: 490px) { }

/* Large desktop */
@media screen and (max-width: 1279px) { }
```

> [!TIP]
> Always write CSS mobile-first or use these standard breakpoints. The `--fs-*` tokens already respond automatically to these breakpoints without needing media queries for font sizes.
