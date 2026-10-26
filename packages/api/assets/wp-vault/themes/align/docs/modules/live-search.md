# 🔍 Module Live Search (`core/blocks/blocks/dynamic/live-search/`)

Tài liệu kỹ thuật tổng quan về cấu trúc, cơ chế hoạt động, tham số block, REST API / AJAX endpoints và giải pháp phòng thủ lỗi (Defensive Architecture) của block động **Live Search** (`align/live-search`) trong theme **Align**.

---

## 📌 1. Mục Tiêu & Thiết Kế
- **Mục tiêu**: Cung cấp khối tìm kiếm trực tiếp (Live Search) hiệu năng cao cho WordPress, hỗ trợ tìm kiếm đa Post Types, lọc theo Taxonomy/Category thời gian thực, lưu cache client/server, và tích hợp chống kẹt hiệu ứng cuộn mượt **Lenis Smooth Scroll**.
- **Block Name**: `align/live-search`
- **Category**: `align-category`
- **Vị trí thư mục core**: `wp-content/themes/align/core/blocks/blocks/dynamic/live-search/`

---

## 🗂️ 2. Cấu Trúc Module

```text
align/core/blocks/blocks/dynamic/live-search/
├── block.json               <-- Khai báo schema metadata, attributes & supports (API v3)
├── register.php             <-- Đăng ký block type, REST API endpoint & AJAX handlers, transient cache
├── render.php               <-- Render HTML semantic, form input, category filter & popup dropdown
├── render-results.php       <-- Template render từng item kết quả (thumbnail, title, post type, excerpt)
├── editor.js                <-- Inspector Controls & React Component trong Gutenberg Block Editor
├── script.js                <-- Client-side runtime: debounced fetch, autocomplete, event isolation
└── style.css                <-- Stylesheet nền tảng giao diện mặc định
```

---

## ⚙️ 3. Block Attributes (`block.json`)

| Attribute | Type | Default | Mô Tả |
| :--- | :--- | :--- | :--- |
| `placeholder` | `string` | `"Search parts, model year..."` | Văn bản gợi ý trong ô input tìm kiếm |
| `postsPerPage` | `number` | `5` | Số lượng kết quả hiển thị tối đa trong dropdown live |
| `defaultMode` | `string` | `"live"` | Chế độ mặc định (`"live"` hoặc `"normal"`) |
| `allowModeToggle` | `boolean` | `false` | Cho phép hiển thị nút chuyển đổi Live/Normal |
| `selectedPostTypes` | `array` | `["all"]` | Danh sách Post Types được tìm kiếm (`["all"]`, `["post", "page", "product"]`) |
| `enableCategoryFilter` | `boolean` | `true` | Bật/tắt dropdown chọn danh mục / taxonomy |
| `categoryFilterTaxonomy`| `string` | `"category"` | Taxonomy dùng để lọc (e.g. `category`, `product_cat`, `brand`) |
| `categoryFilterAllLabel`| `string` | `"All Categories"` | Nhãn mặc định của tùy chọn Tất cả danh mục |
| `categoryFilterTerms` | `string` | `"all"` | Danh sách Term IDs cụ thể cần hiển thị hoặc `"all"` |
| `showExcerpt` | `boolean` | `true` | Hiển thị đoạn trích bài viết / sản phẩm |
| `showPostType` | `boolean` | `true` | Hiển thị nhãn loại bài viết / taxonomy |

---

## ⚡ 4. REST API & AJAX Endpoints

### 1. REST API Endpoint:
- **Route**: `POST /wp-json/align/v1/live-search`
- **Rate Limit**: Tối đa 60 requests / IP / phút (phòng thủ tấn công DDoS / brute-force qua transient cache).
- **Cache**: Tự động lưu server transient cache (`align_ls_*`) trong 15 phút. Tự động xóa cache khi có bài viết được thêm/sửa/xóa (`save_post`, `deleted_post`).

### 2. Admin AJAX Fallback:
- **Action**: `align_live_search` (`wp_ajax_align_live_search` & `wp_ajax_nopriv_align_live_search`).

---

## 🛡️ 5. Tương Thích & Phòng Thủ Lỗi (Defensive Architecture)

1. **Chống kẹt cuộn mượt Lenis Smooth Scroll (`Lenis Scroll Isolation`)**:
   - Khắc phục triệt để tình trạng dropdown danh mục hoặc kết quả live search bị Lenis khóa chuột không cuộn được.
   - Thêm thuộc tính: `data-lenis-prevent="true" data-lenis-prevent-wheel="true" data-lenis-prevent-touch="true"` trực tiếp trên `.align-ls-category-menu` và `.align-ls-dropdown`.
   - Bổ sung CSS `overscroll-behavior: contain !important; touch-action: pan-y !important;`.
   - Runtime JS lắng nghe sự kiện `wheel` / `touchmove` và cô lập qua `e.stopPropagation()` đồng thời cập nhật `scrollTop` mượt mà bên trong container.

2. **Quản lý Style Tùy Biến Toàn Trang (`Custom Live Search Asset Engine`)**:
   - Toàn bộ style override chuẩn theo thiết kế (Pill shape, viền đỏ, category dropdown divider, hidden count number, custom scrollbar) được tách độc lập thành `assets/custom-live-search.css`.
   - Được nạp và quản lý trực tiếp qua **WP Admin -> Align Settings -> Tab "Load Custom Assets (CSS, JS & Font)"** (`align_loaded_assets`), tái sử dụng 100% trên mọi trang của website mà không cần viết lại CSS.
