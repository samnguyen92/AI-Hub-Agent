# JS Patterns in Align Child Theme

All JavaScript patterns documented here are extracted from actual source files in `align-child/assets/`. Reuse these patterns exactly — do not reinvent them.

**Primary source**: `align-child/assets/script.js` (545 lines, IIFE scope)

---

## 1. Splide Slider

All sliders in the theme use **Splide v3** (not Swiper). The global initializer in `script.js` auto-initializes all slider elements that have the correct classes.

### HTML markup (Builder canvas)
```html
<!-- Basic Splide slider -->
<div class="align-splide-slider splide"
     data-slider-id="my-slider-id"
     data-component-type="slider">
  <div class="splide__track">
    <ul class="splide__list">
      <li class="splide__slide">Slide 1</li>
      <li class="splide__slide">Slide 2</li>
      <li class="splide__slide">Slide 3</li>
    </ul>
  </div>
</div>
```

### Config via `window.alignSliderConfigs`
Register slider config in a `<script>` block on the same page:
```javascript
window.alignSliderConfigs = window.alignSliderConfigs || {};
window.alignSliderConfigs['my-slider-id'] = {
    slidesPerView: 3,           // Desktop slides visible
    slidesPerViewTablet: 2,     // Tablet slides visible (491-992px)
    slidesPerViewMobile: 1,     // Mobile slides visible (<490px)
    spaceBetween: 24,           // Gap between slides (px)
    transitionEffect: 'slide',  // 'slide' | 'fade'
    loop: true,                 // Enable loop
    autoplayDelay: 0,           // 0 = no autoplay, e.g. 5000 = 5s
    navigation: true,           // Show prev/next arrows
    showDots: false,            // Show pagination dots
};
```

### Config via `data-splide-config` attribute (JSON inline)
```html
<div class="align-splide-slider splide"
     data-splide-config='{"slidesPerView":1,"transitionEffect":"fade","loop":true,"autoplayDelay":4000,"showDots":true}'>
  <!-- slides -->
</div>
```

### Custom external navigator buttons
```javascript
window.alignSliderConfigs['my-slider-id'] = {
    slidesPerView: 1,
    hookNavigator: true,                    // Enable external buttons
    prevSelector: '.my-prev-btn',          // CSS selector for prev button
    nextSelector: '.my-next-btn',          // CSS selector for next button
};
```
```html
<button class="my-prev-btn">Prev</button>
<div class="align-splide-slider splide" data-slider-id="my-slider-id">...</div>
<button class="my-next-btn">Next</button>
```

### Listen for slider init event
```javascript
const sliderEl = document.querySelector('.align-splide-slider');
sliderEl.addEventListener('alignSplideInit', function(e) {
    const splideInstance = e.detail.splide;
    console.log('[Align Slider] Initialized with', splideInstance.length, 'slides');
});
```

### Access Splide instance directly
```javascript
const sliderEl = document.querySelector('.align-splide-slider');
// After init, the instance is accessible via:
const splide = sliderEl.splide;
if (splide) {
    splide.go(2);  // Jump to slide index 2
}
```

### Wait for Splide library to load
```javascript
function waitForSplide(callback) {
    if (typeof Splide !== 'undefined') {
        callback();
        return;
    }
    const check = setInterval(function() {
        if (typeof Splide !== 'undefined') {
            clearInterval(check);
            callback();
        }
    }, 50);
}
```

### Dot labels (custom text pagination)
```javascript
window.alignSliderConfigs['my-slider-id'] = {
    showDots: true,
    useCustomDotLabels: true,
    dotLabels: 'Ford||Chevy||Dodge',  // Pipe-separated labels
};
```

---

## 2. Query Builder AJAX Grid & Pagination

The global `script.js` handles AJAX pagination for any `.align-qb-container` with a grid inside.

### HTML structure
```html
<div class="align-qb-container"
     data-attributes='{"post_type":"post","posts_per_page":9,"category":"12"}'
     data-ajax-url="/wp-admin/admin-ajax.php"
     data-initialized="false">

  <div class="align-qb-wrap--grid">
    <!-- Initial server-rendered posts HTML -->
    <article>Post 1</article>
    <article>Post 2</article>
  </div>

  <!-- Pagination (rendered server-side) -->
  <div class="align-qb-pagination" data-max-pages="5" data-current-page="1">
    <a class="page-numbers prev" href="#">Prev</a>
    <a class="page-numbers" href="?paged=2">2</a>
    <a class="page-numbers next" href="#">Next</a>
  </div>
</div>
```

### Optional: Load More button
```html
<div class="align-qb-pagination" data-max-pages="5" data-current-page="1">
  <button class="qb-load-more-btn">Load More</button>
</div>
```

### Optional: Infinite scroll trigger
```html
<div class="align-qb-pagination" data-max-pages="5" data-current-page="1">
  <div class="qb-infinite-trigger"></div>
</div>
```

### Search input (within same container)
```html
<div class="align-qb-search-wrap" data-debounce="400">
  <input type="text" class="align-qb-search-input" placeholder="Search...">
</div>
```

### AJAX action and response format
The JS sends `action: align_query_builder_load_more` to `admin-ajax.php`:
```javascript
// Request (FormData):
action: 'align_query_builder_load_more'
nonce: alignQueryBuilderObj.nonce
attributes: '{"post_type":"post","posts_per_page":9}'
paged: 2
s: 'optional search term'

// Expected response:
{
  "success": true,
  "data": {
    "html": "<article>Post HTML</article>...",
    "paginationHtml": "<div class='align-qb-pagination'>...</div>",
    "maxPages": 5
  }
}
```

---

## 3. LazyLoad — MANDATORY After AJAX

The theme uses a global LazyLoad instance (`window.lazyLoadInstance`). After any AJAX operation that injects new HTML with lazy images, you MUST trigger an update:

```javascript
// Standard pattern — always call this after injecting AJAX HTML
function triggerLazyLoad() {
    if (window.lazyLoadInstance && typeof window.lazyLoadInstance.update === 'function') {
        window.lazyLoadInstance.update();
    } else if (typeof lazyLoadInstance !== 'undefined' && lazyLoadInstance &&
               typeof lazyLoadInstance.update === 'function') {
        lazyLoadInstance.update();
    }
}
```

> [!IMPORTANT]
> If you skip `lazyLoadInstance.update()` after injecting AJAX HTML, all images in the injected content will appear **blank white** (they have `data-src` instead of `src`). This is a critical gotcha — see `issues/critical-issues-gotchas.md`.

### Lazy image HTML format (required)
```html
<img class="lazy" data-src="image.webp" src="data:image/gif;base64,R0lGOD..." alt="...">
<!-- OR using data-bg for background images -->
<div class="lazy" data-bg="image.webp"></div>
```

---

## 4. Shortcode Component Container (Builder DOM Pattern)

When embedding shortcodes inside Builder pages, always use this container format:

```html
<!-- CORRECT: Builder can identify and preview this element -->
<div data-component-shortcode="" data-shortcode="[your_shortcode atts='value']">
  [your_shortcode atts='value']
</div>

<!-- WRONG: Builder cannot recognize raw shortcode text -->
[your_shortcode atts='value']
```

### How WordPress renders this on the frontend:
The `post_content` is processed by `the_content` filter → `do_shortcode()` runs automatically → the shortcode renders. The outer `<div>` with `data-component-shortcode` is a transparent wrapper that only affects Builder canvas behavior.

---

## 5. Custom Event: `alignSplideInit`

Dispatched by `script.js` when a slider with `hookNavigator: true` finishes mounting:

```javascript
// Fired on the slider element
slider.dispatchEvent(new CustomEvent('alignSplideInit', {
    detail: { splide: splideInstance }
}));
```

Use this to run post-init logic:
```javascript
document.querySelector('.my-slider').addEventListener('alignSplideInit', function(e) {
    const splide = e.detail.splide;
    // Update external UI, animate arrows, etc.
    document.querySelector('.slide-counter').textContent =
        '1 / ' + splide.length;
    splide.on('moved', function(newIndex) {
        document.querySelector('.slide-counter').textContent =
            (newIndex + 1) + ' / ' + splide.length;
    });
});
```

---

## 6. Global Video Playback: `window.alignPlayActiveSlideVideo`

If a slider contains `<video>` elements, the global `alignPlayActiveSlideVideo` function handles video play/pause per active slide:

```javascript
// The script.js calls this automatically via Splide lifecycle hooks.
// To integrate: ensure video elements have data-src (lazy) or src set.
// The function reads window.ALIGN_BREAKPOINTS for responsive video sources:
window.ALIGN_BREAKPOINTS = { mobile: 589, tablet: 991 };
```

---

## 7. Debounce Utility (Inline Pattern)

Used in QB AJAX search. Copy this pattern for any debounced input:
```javascript
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            func.apply(context, args);
        }, wait);
    };
}

// Usage:
const debouncedSearch = debounce(function(e) {
    performSearch(e.target.value.trim());
}, 400); // 400ms default delay

searchInput.addEventListener('input', debouncedSearch);
```

---

## 8. Self-Contained Shortcode Styles Pattern

When writing a PHP shortcode renderer, always output scoped inline styles on first render using a `static` guard:

```php
function my_shortcode_renderer( $atts ) {
    static $styles_rendered = false;

    ob_start();

    if ( ! $styles_rendered ) {
        $styles_rendered = true;
        echo '<style id="align-my-shortcode-css">
            .my-shortcode-wrap {
                background-color: var(--color-10);
                padding: var(--sp-md) 0;
            }
            .my-shortcode-title {
                font-size: var(--fs-h3);
                color: var(--color-1);
            }
        </style>';
    }

    // Shortcode HTML output
    echo '<div class="my-shortcode-wrap">';
    echo '<h2 class="my-shortcode-title">...</h2>';
    echo '</div>';

    return ob_get_clean();
}
add_shortcode( 'my_shortcode', 'my_shortcode_renderer' );
```

> [!TIP]
> The `static $styles_rendered` pattern ensures styles are only injected once per page, even if the shortcode appears multiple times (e.g. via AJAX calls or multiple shortcode instances).

---

## 9. Page-Specific Script Init Pattern

Standard IIFE pattern for all page-scoped JS files (`script-page-*.js`):

```javascript
/* Page: [Page Name]
   File: assets/builder/script-page-{slug}.js */
(function() {
    'use strict';

    function init() {
        console.log('[Align PageName] Initialized');

        // Attach event listeners
        const trigger = document.querySelector('.my-element');
        if (!trigger) return;

        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            try {
                // Your logic here
            } catch (err) {
                console.error('[Align PageName] Error:', err);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```
