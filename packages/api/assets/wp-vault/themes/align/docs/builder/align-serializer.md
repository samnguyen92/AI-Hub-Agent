# Align Serializer — Deep Dive Technical Reference

> **Scope**: Toàn bộ pipeline xử lý của `align-serializer.js` khi người dùng bấm Save trong Builder.
> Tài liệu này là nguồn sự thật duy nhất (SOT) cho ai muốn hiểu, sửa đổi, hoặc mở rộng Serializer.

---

## 1. Vị Trí Trong Luồng Save

```
User click Save
    │
    ▼
wp-save-handler.js  (line 253)
    const frameBody = window.Vvveb?.Builder?.frameBody
    htmlContent = serializer.serialize(frameBody, { debug: false })
    │
    ▼
align-serializer.js  ←── TÀI LIỆU NÀY
    │
    ▼
StyleManager CSS + JsManager JS
    │
    ▼
REST API → WP DB
```

**Input**: `frameBody` — live DOM node của canvas iframe (`<body>` element đang chạy animation).
**Output**: Chuỗi HTML thuần túy, sạch 100% artifact runtime, sẵn sàng lưu vào `wp_posts.post_content`.

---

## 2. Nguyên Tắc Cốt Lõi (Non-Negotiable Rules)

| Rule | Diễn giải |
|---|---|
| **No absolute selector** | Không được dùng class name cứng (`.is-in`, `span.ln`, v.v.) làm tiêu chí lọc. Phải dùng giá trị, cấu trúc, hoặc so sánh snapshot. |
| **User edits are sacred** | Mọi thay đổi người dùng thực hiện trong contenteditable phải được bảo toàn 100% qua serialize. |
| **Library-agnostic** | Thuật toán phải hoạt động đúng với mọi animation library (GSAP, SplitType, AOS, Locomotive, custom) — không hardcode tên class của bất kỳ thư viện nào. |
| **Clone-first** | Serialize LUÔN làm việc trên bản `cloneNode(true)` — tuyệt đối không mutate live DOM. |
| **< 500 lines** | File `align-serializer.js` phải duy trì dưới 500 dòng. |

---

## 3. Kiến Trúc Pipeline — 5 Bước Tuần Tự

```
cloneNode(true)
    │
    ├─ Snapshot Transfer (BFS)
    │
    ├─ Bước 1: Remove Editor Helper Elements
    │
    ├─ Bước 1b: Universal Runtime Animation Wrapper Unwrapping (.pin-spacer, etc.)
    │
    ├─ Bước 2: Universal Text Restore (data-split containers)
    │   ├─ 2a. Primary: [data-split][data-src] → textContent restore
    │   └─ 2b. Fallback: Structural span heuristic
    │
    ├─ Bước 3: Per-element Loop
    │   ├─ 3a. Remove dangerous tags (script, extension tags, SVG SMIL)
    │   ├─ 3b. Zero-Preview-Persistence (shortcode, wp-block, post-content)
    │   ├─ 3c. Featured image shortcode preservation
    │   ├─ 3d. Universal Snapshot Diff (class + style cleanup)
    │   │   └─ clip-path:path() universal cleanup
    │   └─ 3e. Schema Attribute Filtering (whitelist/blocklist)
    │
    └─ Bước 4: Output HTML
```

---

## 4. Pre-Animation Baseline Snapshot

### 4.1 Tại Sao Cần Snapshot?

Live Canvas có animation chạy liên tục (scroll loop, GSAP, SplitText). Mỗi frame, engine inject:
- **Class runtime**: `is-in`, `is-buried`, `is-px`, bất kỳ class nào từ thư viện third-party.
- **Inline style runtime**: `--p: 0.73`, `--cover: 0.45`, `clip-path: path(...)`.

Nếu serialize lấy trực tiếp từ live DOM, các giá trị này sẽ bị lưu vào DB → trang frontend bị "đóng cứng" ở một trạng thái giữa chừng của animation.

**Giải pháp cũ (DEPRECATED)**: Hardcode class names + CSS var names để xóa.
```javascript
// XẤU — phụ thuộc vào tên cụ thể của Align theme
el.classList.remove('is-in');
el.classList.remove('is-buried');
el.style.removeProperty('--p');
```

**Giải pháp hiện tại**: Chụp snapshot state TRƯỚC KHI animation chạy, rồi diff để xóa mọi thứ bị thêm vào sau.

### 4.2 Timing Injection (PHP Layer)

File: `class-builder-content-handler.php` (line ~383)

Snapshot script được inject **synchronously** ngay sau khi `<body>` render, trước tất cả footer JS (bao gồm GSAP, main.js):

```php
// Sequence in get_page_content():
echo '<!DOCTYPE html><html ...>';
echo '<head>...</head>';
echo '<body ...>';
echo $content_to_render;          // ← page HTML

// NEW: snapshot script injected HERE (before any animation JS)
echo '<script>window.__ALIGN_DOM_SNAPSHOT__ = new WeakMap(); ...</script>';

foreach ($footer_js_assets as $js) {  // ← main.js, GSAP, etc. chạy SAU
    echo '<script src="..."></script>';
}
```

**Tại sao synchronous?** Script `<script>` inline luôn chạy đồng bộ trước `<script src="">` defer/async. Điều này đảm bảo snapshot được ghi xong trước khi `main.js` bắt đầu animate.

### 4.3 Snapshot Script Logic

```javascript
window.__ALIGN_DOM_SNAPSHOT__ = new WeakMap();
window.__alignRefreshDomSnapshot = function(root) {
    const map = window.__ALIGN_DOM_SNAPSHOT__;
    const walk = (el) => {
        if (!el || el.nodeType !== 1) return;
        const entry = { c: el.className || '' };
        if (el.style && el.style.length > 0) {
            entry.s = {};
            for (let i = 0; i < el.style.length; i++) {
                const prop = el.style[i];
                entry.s[prop] = el.style.getPropertyValue(prop);
            }
        }
        map.set(el, entry);
        let child = el.firstElementChild;
        while (child) { walk(child); child = child.nextElementSibling; }
    };
    walk(root || document.body);
};
window.__alignRefreshDomSnapshot();
```

**Dữ liệu snapshot cho mỗi element**:
```javascript
{
    c: "hero-pin",          // className gốc (trước animation)
    s: { "opacity": "0" }  // inline style gốc (có thể là {} nếu không có)
}
```

### 4.4 Snapshot Transfer (Iterative BFS)

Vì `cloneNode(true)` tạo ra các object DOM mới (không có trong WeakMap), Serializer phải transfer data từ live nodes sang clone nodes.

```javascript
// Iterative BFS — KHÔNG dùng recursion (tránh stack overflow trên DOM sâu)
const queue = [[input, rootNode]]; // [live node, clone node]
while (queue.length) {
    const [orig, clone] = queue.shift();
    if (!orig || !clone || orig.nodeType !== 1) continue;
    const snapData = snapMap.get(orig);
    if (snapData) clone.__alignSnap = snapData;  // transfer vào property
    let oChild = orig.firstElementChild;
    let cChild = clone.firstElementChild;
    while (oChild && cChild) {
        queue.push([oChild, cChild]);
        oChild = oChild.nextElementSibling;
        cChild = cChild.nextElementSibling;
    }
}
```

**Tại sao BFS thay vì recursion?** DOM có thể có nhiều lớp lồng sâu (nested sections, mega-menus, complex tables). Recursion có thể gây stack overflow trên những trang phức tạp. BFS dùng queue trên heap — không giới hạn độ sâu.

### 4.5 Universal Snapshot Diff (Step 3d)

Khi có `__alignSnap` trên một clone element:

```javascript
const snap = el.__alignSnap;
if (snap) {
    // Class diff: xóa bất kỳ class nào KHÔNG có trong baseline
    if (hasCL && typeof snap.c === 'string') {
        const baseClasses = new Set(snap.c.trim().split(/\s+/).filter(Boolean));
        Array.from(el.classList).forEach(cls => {
            if (!baseClasses.has(cls)) el.classList.remove(cls);
        });
    }
    // Style diff: xóa bất kỳ property nào KHÔNG có trong baseline
    if (el.style && snap.s) {
        const currentProps = Array.from({ length: el.style.length }, (_, i) => el.style[i]);
        currentProps.forEach(prop => {
            if (!(prop in snap.s)) el.style.removeProperty(prop);
        });
    }
}
```

**Kết quả**: Mọi class và inline style do animation thêm vào (bất kể tên gì, bất kể thư viện nào) đều bị xóa mà không cần biết tên cụ thể. Đây là **universal diff** — không phụ thuộc vào bất kỳ selector nào.

### 4.6 Universal Runtime Animation Wrapper Unwrapping (Bước 1b)

#### Tại sao cần Unwrap Runtime Spacers?
Khi các thư viện cuộn trang (GSAP ScrollTrigger, ScrollMagic, Locomotive Scroll, sticky headers) hoạt động trong Live Canvas, chúng bọc các thẻ `<section>` hoặc container vào bên trong một thẻ giữ chỗ đặc biệt, điển hình là:
```html
<div class="pin-spacer" style="order: 0; position: relative; width: 1159px; height: 3236px; padding: 0px 0px 2500px;">
    <section class="horizontal-words-section content-section">...</section>
</div>
```

**Nguy cơ nghiêm trọng nếu không Unwrap:**
1. **Lệch cấu trúc cây DOM (Tree Divergence)**: Thẻ `.pin-spacer` được sinh ra SAU snapshot, làm thay đổi cấu trúc phân cấp DOM. BFS Snapshot transfer không thể map chính xác các node con bên trong.
2. **Khóa cứng kích thước màn hình Canvas vào Database (Hardcoded Viewport Lock)**: Thuộc tính `width: 1159px` (độ rộng iframe tại thời điểm bấm Save) bị lưu vĩnh viễn vào `wp_posts.post_content`. Khi xem trên màn hình thực tế (1440px hay 1920px), section bị kẹp cứng ở `1159px`, kết hợp với `overflow: hidden` sẽ **xén cụt toàn bộ nội dung chuyển động ngang (Horizontal Clipping)**.
3. **Lồng chồng wrapper vô hạn (Nested Spacer Multiplication)**: Mỗi lần mở builder và lưu lại mà không unwrap, ScrollTrigger lại bọc thêm một thẻ `.pin-spacer` mới quanh thẻ cũ (`.pin-spacer > .pin-spacer > section...`).

#### Thuật Toán Unwrap (Bóc Tách Vỏ Container)
Trước khi bước vào khâu xử lý từng element, Serializer quét và unwrap triệt để:
```javascript
let unpinnedSpacersCount = 0;
let pinSpacers = rootNode.querySelectorAll('.pin-spacer');
while (pinSpacers.length > 0) {
    pinSpacers.forEach(spacer => {
        if (spacer.parentNode) {
            while (spacer.firstChild) {
                spacer.parentNode.insertBefore(spacer.firstChild, spacer);
            }
            spacer.remove();
            unpinnedSpacersCount++;
        }
    });
    pinSpacers = rootNode.querySelectorAll('.pin-spacer');
}
```
- **Không làm mất nội dung con**: Di chuyển toàn bộ thẻ con (`<section>`) ra ngoài trước khi hủy wrapper.
- **Xóa 100% inline geometry rác**: Toàn bộ `width`, `height`, `padding`, `inset` tính toán runtime biến mất cùng thẻ wrapper.
- **Hỗ trợ nested wrappers**: Vòng lặp `while` đảm bảo giải phóng mọi tầng bọc lồng nhau.

### 4.7 Đồng Bộ Baseline Snapshot Khi Thay Đổi DOM (Dynamic Re-snapshotting)

#### Vấn Đề Lệch Snapshot Khi Biên Tập Trực Tiếp (Baseline Stale Desync)
Cơ chế Universal Baseline Snapshot lưu snapshot ban đầu vào `WeakMap` (`window.__ALIGN_DOM_SNAPSHOT__`) ngay khi iframe tải trang.
Tuy nhiên, trong quá trình biên tập:
1. **Code Editor Flush**: Người dùng mở modal chỉnh sửa HTML (`#codeEditorModal`), thêm/sửa class name (ví dụ: đổi `btn` thành `btn btn-primary`) hoặc inline style, sau đó bấm Save hoặc đóng modal.
2. **DOM Morphing (`DomMorph.morph`)**: Builder cập nhật các thuộc tính và text nodes trực tiếp trên live element tham chiếu cũ.
3. **Property Change & Undo/Redo**: Thay đổi thuộc tính từ inspector hoặc khôi phục snapshot lịch sử.

Nếu `WeakMap` không được re-snapshot, các class và inline styles mới do user chủ động thêm vào sẽ **KHÔNG** tồn tại trong snapshot ban đầu. Khi Serializer chạy Diff, nó sẽ nhận định nhầm các class/style này là "runtime animation junk" và gọi `el.classList.remove()` hoặc `el.style.removeProperty()`, làm mất dữ liệu đã sửa của người dùng.

#### Giải Pháp Đồng Bộ Hai Tầng (Two-Tier Re-snapshot Architecture)
1. **Iframe Snapshot Helper (`__alignRefreshDomSnapshot`)**:
   Hàm `window.__alignRefreshDomSnapshot(root)` được inject sẵn trong iframe canvas bởi `class-builder-content-handler.php`. Khi được gọi, nó duyệt lại cây DOM chỉ định (hoặc toàn bộ `document.body`) và cập nhật record mới nhất vào `window.__ALIGN_DOM_SNAPSHOT__`.
2. **Builder Engine Hook (`Vvveb.Builder.refreshDomSnapshot`)**:
   Trong `builder-drag-drop.js`, hàm `Vvveb.Builder.refreshDomSnapshot(target)` bọc an toàn lệnh gọi sang iframe:
   ```javascript
   refreshDomSnapshot: function(target) {
       var frameWindow = this.iframe ? this.iframe.contentWindow : null;
       if (frameWindow && typeof frameWindow.__alignRefreshDomSnapshot === 'function') {
           var rootEl = (target && target.nodeType === 1) ? target : (this.frameBody ? this.frameBody.get(0) : null);
           frameWindow.__alignRefreshDomSnapshot(rootEl);
       }
   }
   ```
3. **Các Điểm Kích Hoạt Tự Động (Automatic Triggers)**:
   - **Ngay sau `DomMorph.morph()`** trong `Vvveb.Builder.setHtml()`: Cập nhật baseline ngay khi HTML từ Code Editor được morph vào iframe canvas.
   - **Trước khi Serialize trong `captureSnapshotPayload()`** (`wp-save-handler.js`): Gọi `Vvveb.CodeEditor.flush()` và `Vvveb.Builder.refreshDomSnapshot()` để đảm bảo bất kỳ thay đổi nào chưa kịp morph đều được đồng bộ vào baseline trước khi serialize.
   - **Event Listeners**: Lắng nghe `Vvveb.Builder.propertyChange`, `vvveb.undo.add`, và `vvveb.undo.restore` để tự động refresh baseline snapshot.

---

## 5. Universal Text Restore (Bước 2)

### 5.1 Vấn Đề: Text Splitting Phá Vỡ Cấu Trúc DOM

`runSplit()` trong `main.js` biến đổi:
```html
<!-- BEFORE animation (saved in DB) -->
<p data-split="lines">Eight more on the works page.</p>

<!-- AFTER runSplit() chạy (live canvas) -->
<p data-split="lines" data-src="Eight more on the works page.">
    <span class="ln">Eight more on</span>
    <span class="ln"> the works page.</span>
</p>
```

Serialize phải đưa `<p>` về dạng BAN ĐẦU với **text hiện tại** (đã bao gồm bất kỳ edit nào của user).

### 5.2 Thuật Toán Primary: data-src Marker

`data-src` được `runSplit()` ghi vào element để cache text gốc trước khi inject spans. Sự hiện diện của `data-src` trên `[data-split]` là **marker đáng tin cậy** rằng spans đã được inject.

```javascript
rootNode.querySelectorAll('[data-split][data-src]').forEach(splitParent => {
    const current = (splitParent.textContent || '').trim();
    splitParent.textContent = current; // ← dùng textContent HIỆN TẠI
    splitParent.removeAttribute('data-src');
    textRestoreCount++;
});
```

> **CRITICAL BUGFIX**: Phải dùng `textContent` HIỆN TẠI — **không** dùng `data-src` value.
>
> **Lý do**: `data-src` chứa text GỐC trước khi user edit. Nếu user sửa "Eight more..." thành "Eight more... 567" qua contenteditable, `data-src` vẫn là "Eight more..." (không được update). Restore từ `data-src` → ghi đè edit của user → **data loss**.
>
> **Logic đúng**:
> - User không edit: `textContent` == `data-src` → kết quả giống nhau.
> - User đã edit: `textContent` = text mới → edit được preserve.
>
> `textContent` setter tự động xóa toàn bộ child nodes (bao gồm tất cả injected spans) và thay bằng một text node duy nhất. Không cần biết class tên gì.

### 5.3 Structural Fallback (Bước 2b)

Nếu `data-split` có span children nhưng `data-src` không tồn tại (edge case: animation library khác không dùng `data-src` convention):

```javascript
// Heuristic: SPAN là "runtime" nếu:
// 1. Không có nested element children (chỉ chứa text)
// 2. Không có semantic attributes (chỉ có class, style, aria-hidden)
const isRuntimeSplitSpan = n => {
    if (n.nodeType === 3) return true;           // text node — ok
    if (n.nodeType !== 1) return true;            // comment — ignore
    if (n.tagName !== 'SPAN') return false;       // non-span = authored
    if (n.firstElementChild) return false;        // nested elements = authored
    for (let i = 0; i < n.attributes.length; i++) {
        const a = n.attributes[i].name;
        if (a !== 'class' && a !== 'style' && a !== 'aria-hidden') return false;
    }
    return true;
};

if (children.every(isRuntimeSplitSpan)) {
    splitParent.textContent = (splitParent.textContent || '').trim();
} else {
    // Mixed content (có <a>, <strong>...) → giữ nguyên, KHÔNG collapse
    console.warn('[AlignSerializer] [data-split] has mixed children, skipping collapse:', splitParent);
}
```

**Tại sao không dùng class names `ln`, `wd`, `w`, `ch`?** Mỗi thư viện dùng class name khác nhau (SplitType dùng `.st-line`, GSAP SplitText dùng `.split-line`...). Structural check hoạt động với tất cả.

---

## 6. clip-path: Universal Value-Based Cleanup

Thay vì kiểm tra class `.hero-pin`, Serializer check giá trị:

```javascript
const cpVal = el.style.getPropertyValue('clip-path');
if (cpVal && cpVal.trim().startsWith('path(')) {
    el.style.removeProperty('clip-path');
}
```

**Lý do**: `path()` format chỉ được JS inject (GSAP, MotionPath). CSS authored clip-path dùng `polygon()`, `circle()`, `inset()`, `url()` — không bao giờ dùng `path()` string dạng JS-computed. Ngoài ra, `clip-path: path()` cũng bị Universal Snapshot Diff bắt — đây là safety net thứ hai.

---

## 7. Schema Attribute Filtering (Bước 3e)

Mọi attribute được validate qua `align-serializer-schema.js`:

| Loại | Ví dụ | Quyết định |
|---|---|---|
| Standard HTML5/SVG | `class`, `id`, `href`, `src`, `alt` | ALLOW |
| Animation authored | `data-split`, `data-reveal`, `data-scene` | ALLOW (whitelist exact) |
| Media | `data-src` trên `img`/`video`/`source` | ALLOW |
| Extension injected | `bis_skin_checked`, `data-gramm`, `data-gr-*` | STRIP |
| XSS risk | `onclick`, `onerror`, `href="javascript:"` | STRIP |
| Runtime cache | `data-src` trên non-media elements | STRIP |

Extend bằng `window.AlignCustomSerializerRules` — không cần sửa core.

---

## 8. Các Trường Hợp Đặc Biệt (Edge Cases)

### 8.1 [data-split] Có Authored Inline Elements
```html
<p data-split="lines">
    Check our <a href="/work">portfolio</a> now.
</p>
```
Sau `runSplit()`, cấu trúc phức tạp hơn. Structural fallback sẽ phát hiện `<a>` không phải runtime span → **bỏ qua collapse** → `<a>` được bảo toàn.

### 8.2 SVG Elements Không Có classList
Một số SVG child elements (`<path>`, `<use>`, `<g>`) không có `classList` trong một số browsers:
```javascript
const hasCL = el.classList != null; // null guard bắt buộc
```

### 8.3 CSS Var Falsy "0"
CSS var `--p: 0` là giá trị hợp lệ (phần tử ở đầu trang). Check `!== ''` thay vì truthy:
```javascript
// SAI: if (el.style.getPropertyValue('--p')) — bỏ sót "--p: 0"
// ĐÚNG: if (el.style.getPropertyValue('--p') !== '')
```
Tuy nhiên, kể từ khi Universal Snapshot Diff ra đời, hardcoded `--p`, `--cover`, `--sp` đã được xóa hoàn toàn. Snapshot diff xử lý đúng cả trường hợp `--p: 0`.

### 8.4 String Input (Không Có Snapshot)
Khi `input` là chuỗi HTML string (không phải live DOM node):
- `__ALIGN_DOM_SNAPSHOT__` không tồn tại → Snapshot Transfer bị bỏ qua.
- Không có `__alignSnap` trên bất kỳ element nào → Snapshot Diff bị bỏ qua.
- Chỉ các rule universal (clip-path value check, schema filtering) hoạt động.

---

## 9. Debug Mode

Bật bằng cách truyền `{ debug: true }` vào serialize:
```javascript
serializer.serialize(frameBody, { debug: true });
```

Console output có format:
```
[AlignSerializer] Cleaned DOM in 12ms | Nodes: 847 | Stripped Attrs: 3 | Text Restored: 4 | <br> tags: 0 -> 0
  Execution Time: 12 ms
  Total Traversed Nodes: 847
  Removed Helper Elements: 2
  Removed Extension Tags: 0
  Text Restore (data-src + structural fallback): 4
  Total Stripped Attributes: 3
  <br> Tags (Input vs Output): 0 -> 0
```

---

## 10. Files Liên Quan

| File | Trách nhiệm | Giới hạn |
|---|---|---|
| [`align-serializer.js`](../../core/builder/js/modules/align-serializer.js) | Normalizer Engine — pipeline chính | < 500 lines |
| [`align-serializer-schema.js`](../../core/builder/js/modules/align-serializer-schema.js) | Registry whitelist / blocklist / Anti-XSS | < 500 lines |
| [`wp-save-handler.js`](../../core/builder/js/wp-save-handler.js) | Orchestrate save: gọi serialize + REST POST | < 500 lines |
| [`class-builder-content-handler.php`](../../core/inc/builder/class-builder-content-handler.php) | Inject snapshot script vào canvas iframe | < 500 lines |
| [`main.js`](../../../align-child/assets/js/main.js) | Animation engine, `runSplit()`, `isBuilderCanvas()` | — |

---

## 11. CHANGELOG Thay Đổi Quan Trọng

| Version | Thay đổi |
|---|---|
| **7.4.7** | Thêm Dynamic Re-snapshotting (`window.__alignRefreshDomSnapshot` & `Vvveb.Builder.refreshDomSnapshot`) |
| **7.4.7** | Tự động đồng bộ baseline snapshot khi `DomMorph.morph()` xong từ Code Editor |
| **7.4.7** | Tự động flush Code Editor & refresh snapshot trước khi gọi `align-serializer` trong save pipeline |
| **7.4.7** | **BUGFIX**: Sửa lỗi mất class/inline styles khi sửa trực tiếp bằng Code Editor do serializer nhầm với runtime junk |
| **7.4.4** | Thêm Pre-Animation Baseline Snapshot (PHP inject + JS WeakMap + BFS Transfer + Universal Diff) |
| **7.4.4** | Chuyển `span.ln` hardcoded → Universal `[data-split][data-src]` text restore |
| **7.4.4** | Chuyển structural span heuristic (bỏ class coupling `ln/wd/w/ch`) |
| **7.4.4** | Xóa hardcoded fallbacks `.is-in`, `.is-buried`, `.is-px`, `--p`, `--cover`, `--sp` |
| **7.4.4** | BFS thay recursion trong Snapshot Transfer |
| **7.4.4** | **BUGFIX**: text restore dùng `textContent` hiện tại thay vì `data-src` value — bảo toàn user edits |

---

## 12. Anti-Patterns — KHÔNG Làm

```javascript
// ❌ Hardcode class name — vỡ khi đổi thư viện hoặc đổi theme
el.classList.remove('is-in');
rootNode.querySelectorAll('span.ln').forEach(...);

// ❌ Restore text từ data-src — ghi đè user edits!
splitParent.textContent = splitParent.getAttribute('data-src');

// ❌ Regex trên HTML string — fragile, nuốt shortcode, nát dấu nháy
html.replace(/<span class="ln">(.+?)<\/span>/g, '$1');

// ❌ Mutate live DOM — phá animation đang chạy trong canvas
frameBody.querySelectorAll('.is-in').forEach(el => el.classList.remove('is-in'));

// ❌ Recursion trong tree traversal sâu — stack overflow
function walk(el) { ... walk(el.firstElementChild); } // max call stack!
```
