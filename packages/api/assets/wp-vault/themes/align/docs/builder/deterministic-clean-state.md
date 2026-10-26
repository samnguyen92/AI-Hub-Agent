# 🏛️ Kiến Trúc Deterministic Clean State, Schema Serializer Engine & Canvas Isolation

> **Tài liệu theo dõi và hướng dẫn kỹ thuật dành cho Developer**: Giải quyết dứt điểm vấn đề rác từ Browser Extension (Brave, Grammarly, Google Translate, LastPass...), loại bỏ cơ chế lọc Regex/Replace chắp vá, đồng thời bảo toàn 100% WordPress Shortcodes, Vector SVG, Interactive Datasets, và WebGL 3D Animations (Unicorn Studio).

---

## 1. Bối Cảnh & Vấn Đề Gốc Rễ (Root Cause)

### 1.1 Vấn đề "DOM-as-State" (Cào DOM làm Database)
Trong các Page Builder cổ điển, dữ liệu lưu trữ thường được trích xuất trực tiếp từ Live Canvas (`iframe.contentDocument.body.innerHTML`). 
Tuy nhiên, Live Canvas là một **môi trường công cộng (Public Execution Environment)**:
- **Brave Browser / AdBlockers**: Tự động inject `bis_skin_checked="1"`, `bis_status="ok"`, `bis_frame_id="609"` vào các thẻ `<body>` và `<div>` khi quét màu nền (cosmetic skin filter).
- **Google Translate**: Khi bật chế độ tự dịch, nó tự động bọc toàn bộ văn bản bằng hàng trăm thẻ `<font class="...">` làm nát cấu trúc DOM.
- **Grammarly / LanguageTool**: Tự động tiêm các thẻ `grammarly-desktop-integration`, shadow root và các thuộc tính `data-gramm="true"`, `data-gr-ext-installed` vào các vùng `contenteditable`.
- **Password Managers (LastPass, 1Password)**: Chèn container `div[data-lastpass-root]` và icon vào các thẻ `<form>`, `<input>`.
- **Animation Engine Runtime Mutation**: Khi các file script hiệu ứng (như `main.js`) chạy trong canvas iframe, chúng tự ý băm text thành thẻ `<span class="ln">`, gắn `style="--p:..."`, tính toán `clip-path` và gắn class `is-px` lên `.hero-pin`. Khi người dùng lưu trang, các runtime styles này bị đóng cứng vĩnh viễn vào database.

### 1.2 Tại sao Regex / Replace là "Anti-Pattern"?
- **Bất định (Non-deterministic)**: Regex chỉ đi sau đuôi để "chữa cháy". Mỗi khi có extension mới xuất hiện, lập trình viên lại phải viết thêm một dòng `preg_replace` hoặc `removeAttribute()`.
- **Dễ gãy vỡ (Fragile)**: Regex trên chuỗi HTML lớn rất dễ nuốt nhầm ký tự đặc biệt, làm sai lệch cấu trúc dấu nháy trong Shortcodes và attributes.

---

## 2. Kiến Trúc 2 Tầng: Schema Registry & Normalizer Engine

Nhằm tuân thủ nguyên tắc KISS và giới hạn < 500 dòng code mỗi file, hệ thống phân tách thành 2 module độc lập:
1. **Schema Module**: [`align-serializer-schema.js`](../../core/builder/js/modules/align-serializer-schema.js) — Đóng vai trò là Registry quy chuẩn chứa toàn bộ whitelist, blocklist, SVG specs, quy tắc an toàn URL và cơ chế mở rộng runtime qua `window.AlignCustomSerializerRules`.
2. **Normalizer Engine**: [`align-serializer.js`](../../core/builder/js/modules/align-serializer.js) — Đóng vai trò là Worker duyệt cây DOM, unwrap các thẻ rác, lọc thuộc tính theo Schema (kèm `FALLBACK_SCHEMA` default-deny) và xuất ra HTML sạch.

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           IFRAME LIVE CANVAS                            │
│           (Được bảo vệ bởi Canvas Isolation Guard trong main.js)        │
└────────────────────────────────────┬────────────────────────────────────┘
                                     │ cloneNode(true) (Tách biệt RAM)
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                  ALIGN SERIALIZER NORMALIZER ENGINE                     │
│               (core/builder/js/modules/align-serializer.js)             │
│                                                                         │
│  [BƯỚC 1: REMOVE HELPERS & UNWRAP RUNTIME SPACERS]                      │
│  → Xóa helper selectors, extension tags (<newsection>, <loom-...>)      │
│  → Unwrap runtime animation wrappers (.pin-spacer, scroll-spacers)      │
│                                                                         │
│  [BƯỚC 2: UNIVERSAL TEXT RESTORE]                                       │
│  → Phục hồi [data-split] containers về textContent gốc, gỡ split spans  │
│                                                                         │
│  [BƯỚC 3: DANGEROUS TAGS & UNIVERSAL BASELINE SNAPSHOT DIFF]            │
│  → Xóa thẻ SVG SMIL độc hại (<animate>, <set>, <animatetransform>)      │
│  → Xóa thẻ <script> không có ID hợp lệ                                  │
│  → Universal Baseline Diff: so khớp __ALIGN_DOM_SNAPSHOT__ xóa 100%    │
│    class và inline styles tính toán runtime (matrix3d, width, clip-path)│
│                                                                         │
│  [BƯỚC 4: SCHEMA ATTRIBUTE FILTERING] (AlignSerializerSchema)           │
│  → Whitelist HTML5 + SVG attributes chuẩn                               │
│  → Whitelist Animation & WebGL: data-us-project, data-split, data-scene,│
│    data-flow, data-par, data-sp, data-reveal, data-rise, data-open,     │
│    data-stack, data-pop, data-t, data-cursor-*, data-theme...           │
│  → Chặn cứng 100% on* event handlers (onclick, onerror, onload...)      │
│  → Anti-XSS URL check: chặn javascript:, vbscript:, data:text/html      │
│  → Ràng buộc data-src: chỉ giữ lại trên img, video, source, iframe      │
│                                                                         │
│  [BƯỚC 5: ZERO-PREVIEW-PERSISTENCE]                                     │
│  → node[data-component-shortcode].innerHTML = "" (Dọn ruột preview)    │
└────────────────────────────────────┬────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      RAW HTML SẠCH THUẦN KHIẾT 100%                     │
│           (Đóng gói cùng StyleManager CSS + JsManager JS -> WP DB)      │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Khóa Canvas Iframe & Cô Lập Script Runtime

### 3.1 Proactive Canvas Isolation ở tầng HTML
Tại [`class-builder-content-handler.php`](../../core/inc/builder/class-builder-content-handler.php):
1. **Chặn Google Translate phá DOM**:
   ```html
   <html lang="en" translate="no" class="notranslate">
   <head>
       <meta name="google" content="notranslate">
   ```
2. **Chặn Grammarly / Check chính tả inject shadow root**:
   ```html
   <body data-gramm="false" data-enable-grammarly="false" spellcheck="false">
   ```

### 3.2 Kiến Trúc Live Animation CMS & Cổng Lọc Tuần Tự (Serialization Gate)
Nhằm mang lại trải nghiệm WYSIWYG chân thực nhất, Align Builder cho phép script animation (`main.js`, GSAP, Splitting, WebGL) chạy tự nhiên 100% bên trong Canvas:
1. **Trải nghiệm Live trong CMS**:
   - Hiệu ứng băm chữ (`splitLines()`), chuyển động WebGL 3D (`data-us-project`), hiệu ứng cuộn mượt (`wheel` + `frame()`), và hiệu ứng reveal (`.is-in`, card stacking `.is-buried`) hoạt động sống động như ngoài frontend.
   - Người dùng có thể lăn chuột, tương tác, chỉnh sửa chữ hoặc đổi hình ảnh trực tiếp.

2. **Cổng Lọc Tuần Tự & Pre-Animation Baseline Snapshot (`AlignSerializer.serialize()`)**:
   Khi người dùng bấm **Save**, cây DOM Live đi qua cổng lọc tuần tự kết hợp đối chiếu Baseline Snapshot để tự động triệt tiêu tác dụng phụ của animation đa thư viện (GSAP, AOS, Locomotive, SplitType, custom script) mà **không phụ thuộc vào bất kỳ tên selector hay class name nào**:

   - **Pre-Animation Baseline Snapshot**: Script đồng bộ chạy ngay khi body vừa render (trước footer JS) ghi lại `{className, inlineStyles}` gốc vào `WeakMap`. Khi serialize, BFS transfer data sang clone nodes, rồi Universal Diff bóc tách bất kỳ class và inline style nào bị thêm vào sau đó — bất kể tên gì, bất kể thư viện nào.
   - **Universal Text Restore**: Elements có `[data-split][data-src]` được phát hiện và collapse về text sạch bằng cách dùng `textContent` hiện tại (preserve 100% user edits) — không hardcode class `span.ln`, `span.wd` hay tên thư viện nào. Nếu không có `data-src`, fallback dùng structural heuristic (kiểm tra shape của span, không check class name).
   - **Universal clip-path Cleanup**: Kiểm tra giá trị `path(...)` thay vì class selector — bắt mọi animated clip-path từ mọi thư viện.
   - **Bảo toàn 100% User Edits & Media**: Toàn bộ cấu trúc SVG vector, video, picture, component kéo thả, và text đã chỉnh sửa qua contenteditable được giữ nguyên vẹn.

   > Xem tài liệu kỹ thuật chuyên sâu tại [`align-serializer.md`](./align-serializer.md).

---

## 4. Danh Sách Whitelist Thuộc Tính Đã Cập Nhật

### 4.1 Nhóm WebGL & Hiệu Ứng Nâng Cao (Child Theme Datasets)
- `data-us-project`: ID dự án Unicorn Studio 3D WebGL (bắt buộc cho hero banner).
- `data-t`: Văn bản phụ hiển thị khi hover hiệu ứng cuộn chữ (roll button).
- `data-stack`: Container xếp chồng thẻ tự động (Services section).
- `data-pop`: Vòng tròn nảy tương tác (Radar / Clients diagram).
- `data-split`: Cơ chế cắt chữ (`chars`, `words`, `lines`, `one`).
- `data-scene`: Cảnh cuộn timeline.
- `data-flow`: Chỉ báo luồng nội dung.
- `data-par` & `data-sp`: Parallax scroll factor và tốc độ.
- `data-reveal` & `data-rise`: Container nhóm và phần tử reveal so le.
- `data-open`: Trạng thái mở menu / drawer.
- `data-reveal-now`: Kích hoạt reveal tức thì không chờ viewport.
- `data-scatter`: Chuyển động phân rã kinetic.
- `data-clock`: Đồng hồ múi giờ động thế giới.
- `data-top`: Nút cuộn mượt về đầu trang.
- `data-nl`: Form đăng ký nhận tin (newsletter).
- `data-filter` & `data-filter-grid`: Bộ lọc danh mục works/blog.
- `data-hscroll`: Container cuộn ngang.
- `data-cursor`, `data-cursor-label`, `data-cursor-img`, `data-cursor-wide`: Con trỏ chuột tương tác custom.
- `data-theme`: Nút chuyển đổi giao diện sáng/tối.
- `data-lang-link`: Liên kết chuyển đổi ngôn ngữ.
- `data-480`, `data-720`, `data-1120`, `data-society`: Thuộc tính ảnh responsive và society.

---

## 5. Quy Trình Mở Rộng & Bảo Trì (Extensibility Guide)

### 5.1 Mở rộng an toàn qua `window.AlignCustomSerializerRules`
Để thêm thuộc tính mới mà không cần sửa core, chỉ cần khai báo cấu hình toàn cục trước khi script builder khởi chạy:
```javascript
window.AlignCustomSerializerRules = {
    allowedExactDataAttrs: ['data-my-custom-attr'],
    allowedPrefixes: ['data-lottie-']
};
```
Hệ thống tự động lọc sạch các thuộc tính vi phạm (như chứa `on*`, URL chứa `javascript:`, chuỗi rỗng) trước khi nạp vào whitelist.

---

## 6. Danh Sách File Liên Quan

| Tên file | Vị trí | Trách nhiệm |
| :--- | :--- | :--- |
| [`align-serializer-schema.js`](../../core/builder/js/modules/align-serializer-schema.js) | `core/builder/js/modules/` | Registry trung tâm quản lý Whitelist, Blocklist, Anti-XSS specs (< 410 dòng). |
| [`align-serializer.js`](../../core/builder/js/modules/align-serializer.js) | `core/builder/js/modules/` | Normalizer Engine — 5-bước pipeline, Universal Snapshot Diff, text restore (< 460 dòng). |
| [`main.js`](../../../align-child/assets/js/main.js) | `align-child/assets/js/` | Engine animation, tích hợp Canvas Isolation Guard `isBuilderCanvas()`. |
| [`class-builder-ui-renderer.php`](../../core/inc/builder/class-builder-ui-renderer.php) | `core/inc/builder/` | Enqueue module Schema trước Serializer, bảo vệ `wp_json_encode` (< 430 dòng). |
| [`class-builder-content-handler.php`](../../core/inc/builder/class-builder-content-handler.php) | `core/inc/builder/` | Khóa Canvas Iframe, inject Pre-Animation Baseline Snapshot script (< 500 dòng). |

### Tài Liệu Kỹ Thuật

| Tài liệu | Nội dung |
| :--- | :--- |
| [`align-serializer.md`](./align-serializer.md) | **Deep-dive** toàn bộ pipeline: Snapshot architecture, Text Restore algorithm, anti-patterns, edge cases, debug guide. |
| [`deterministic-clean-state.md`](./deterministic-clean-state.md) | Tổng quan bối cảnh, kiến trúc 2-tầng Schema+Normalizer, Canvas Isolation. |
