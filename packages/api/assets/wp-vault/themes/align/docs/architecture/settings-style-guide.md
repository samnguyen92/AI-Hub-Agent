# 🎨 Align Settings UI Design System & Style Guide

> **MANDATORY POLICY**: Mọi AI Agent và Developer khi xây dựng, chỉnh sửa hoặc bổ sung bất kỳ thành phần nào trên trang **Align Settings** (`/wp-admin/options-general.php?page=align-settings`) **BẮT BUỘC** phải tuân thủ nghiêm ngặt theo tài liệu quy chuẩn phong cách này.

---

## 1. Triết Lý Thiết Kế Cốt Lõi (Design Principles)

1. **Typography-First & Zero Emojis**:
   - Tuyệt đối **KHÔNG SỬ DỤNG EMOJIS** (`📦`, `🤖`, `🖼️`, `✨`, `💡`, `❌`, `🎉`...) trong tiêu đề, nhãn nút, hướng dẫn hay mô tả.
   - Sử dụng font hệ thống hiện đại (`-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto...`), phân cấp trực quan qua `font-size`, `font-weight`, và màu chữ Slate.
   - Hạn chế Dashicons trang trí; chỉ dùng khi có mục đích tương tác vật lý (như mũi tên accordion).
2. **Transparent / No-Background Button System**:
   - Triệt tiêu 100% các nút bấm có nền xám đục mặc định của WordPress (`#f6f7f7`) hoặc viền xanh thô (`#2271b1`).
   - Tất cả các nút bấm đều có **nền trong suốt (`background: transparent !important`)**, viền mảnh tinh tế và phản hồi hover mượt mà.
3. **Card Design System Độc Lập**:
   - Mọi khu vực cài đặt phải được đóng gói bên trong thẻ Card bo góc chuẩn mực (`.align-settings-card`). Tuyệt đối không để form controls nằm tự do ngoài card hoặc dùng thẻ `<hr>` thô sơ.
4. **Accordion-First Cho Bảng Dữ Liệu & Công Cụ Nặng**:
   - Các bảng danh sách dài (như Registered Media Sizes) hoặc công cụ chẩn đoán/tái tạo (như Force Thumbnail Regenerator) **BẮT BUỘC ở trạng thái ĐÓNG (Closed, `display: none`) khi tải trang**.
5. **Cách Ly Admin Notices (Notice Isolation)**:
   - Các thông báo hệ thống của WordPress (`div.notice`, `div.updated`) bắt buộc hiển thị ở đầu trang, **tuyệt đối không được để lọt vào bên trong banner header**.

---

## 2. Bảng Màu & Thông Số Thiết Kế (Design Tokens)

| Token | Giá Trị CSS | Mục Đích Sử Dụng |
| :--- | :--- | :--- |
| **Primary Accent** | `#0284c7` (Sky 600) | Màu thương hiệu chính, nút Primary, icon active, link focus |
| **Primary Hover** | `#0369a1` (Sky 700) | Trạng thái hover của nút Primary và link |
| **Accent Light** | `#f0f9ff` (Sky 50) | Nền hover của nút Secondary, tab active phụ, preset active |
| **Border Accent** | `#bae6fd` (Sky 200) | Viền của nút Secondary và callout banner |
| **Text Main** | `#0f172a` (Slate 900) | Tiêu đề card, brand title, chữ quan trọng |
| **Text Body** | `#1e293b` (Slate 800) | Nội dung form, nhãn cài đặt |
| **Text Muted** | `#64748b` (Slate 500) | Mô tả card, hướng dẫn phụ, trạng thái chờ |
| **Card Border** | `#e2e8f0` (Slate 200) | Viền card, viền chia hàng, viền tab bar |
| **Danger Accent** | `#dc2626` (Red 600) | Chữ nút Delete, Cancel Process, cảnh báo lỗi |
| **Danger Border** | `#fecaca` (Red 200) | Viền nút Delete, viền hộp lỗi |
| **Danger Light** | `#fef2f2` (Red 50) | Nền hover nút Delete, nền thông báo lỗi |
| **Success Accent** | `#16a34a` (Green 600) | Badge trạng thái sẵn sàng, thông báo thành công |
| **Success Light** | `#f0fdf4` (Green 50) | Nền pill badge online / ready |
| **Border Radius** | `8px` (Card), `6px` (Button/Input), `20px` (Pill Save) | Chuẩn bo góc |

---

## 3. Quy Chuẩn Nút Bấm (Button Design System)

Tất cả các nút bấm trong `.align-settings-wrap` được quản lý 100% bằng CSS tập trung tại `settings-layout.css`.

> [!CAUTION]
> **CẤM INLINE STYLES CHO NÚT BẤM**: Tuyệt đối không viết inline `style="color: ...; border-color: ...; background: ..."` vào thẻ `<button>`. Hãy chỉ định đúng class ngữ nghĩa dưới đây.

### 3.1 Cấu Trúc CSS & Phân Loại Class Nút Bấm

```html
<!-- 1. Default Neutral Button (Thao tác phụ, Copy, Clear, Browse) -->
<button type="button" class="button">
    Neutral Action
</button>

<!-- 2. Secondary / Accent Button (Thao tác chức năng: + Add Size, Test Connection, Check Status) -->
<button type="button" class="button button-secondary">
    + Add Custom Size
</button>

<!-- 3. Primary Action Button (Thao tác chính: Convert to Blocks, Update Theme, Start Import) -->
<button type="button" class="button button-primary">
    Convert to Blocks
</button>

<!-- 4. Danger / Delete Button (Thao tác xóa: Delete size, Cancel, Reset Data) -->
<button type="button" class="button button-danger">
    Delete
</button>

<!-- 5. Disabled State (Tự động áp dụng khi có thuộc tính disabled) -->
<button type="button" class="button button-primary" disabled>
    Processing...
</button>
```

### 3.2 Quy Định Trực Quan Của Từng Loại Nút

| Loại Nút | Class | Nền | Viền | Chữ | Hover |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Neutral** | `.button` | `transparent` | `1px solid #cbd5e1` | `#334155` | Nền `#f8fafc`, viền `#94a3b8`, chữ `#0f172a` |
| **Secondary** | `.button.button-secondary` | `transparent` | `1px solid #bae6fd` | `#0284c7` | Nền `#f0f9ff`, viền `#0284c7`, chữ `#0369a1` |
| **Primary** | `.button.button-primary` | `transparent` | `1.5px solid #0284c7` | `#0284c7` | Nền `#0284c7`, viền `#0284c7`, **chữ `#ffffff`** |
| **Danger** | `.button.button-danger` | `transparent` | `1px solid #fecaca` | `#dc2626` | Nền `#fef2f2`, viền `#ef4444`, chữ `#b91c1c` |
| **Disabled** | `:disabled`, `[disabled]` | `transparent` | `1px solid #e2e8f0` | `#94a3b8` | Giữ nguyên, `opacity: 0.55`, `cursor: not-allowed` |

### 3.3 Quy Chuẩn Căn Chỉnh Icon / Dashicons Trong Nút Bấm (Button Icon Alignment Standard)

Khi đặt icon Dashicons hoặc spinner bên trong thẻ nút (`.button`), bắt buộc tuân thủ nguyên tắc căn chỉnh tuyệt đối sau:

1. **Căn giữa tự động qua CSS tập trung (`settings-layout.css`)**:
   - Mọi nút `.button` đã được cấu hình flexbox: `display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important;`.
   - Toàn bộ `.dashicons` và pseudo-element `::before` bên trong nút được cố định kích thước chuẩn: `width: 16px !important; height: 16px !important; font-size: 16px !important; line-height: 1 !important; margin: 0 !important; padding: 0 !important; flex-shrink: 0 !important;`.
2. **Khoảng cách Icon và Nhãn chữ**:
   - Khoảng cách giữa icon và chữ được xử lý hoàn toàn tự động thông qua `gap: 6px !important` của container nút.
3. **CẤM KỸ THUẬT (STRICT PROHIBITIONS)**:
   - ❌ **CẤM viết inline `margin-right`, `vertical-align`, hoặc `font-size`** vào thẻ `<span class="dashicons ...">` (gây lệch trọng tâm, méo icon hoặc đè chữ).
   - ❌ **CẤM gán inline `line-height`** (như `line-height: 32px`) trên thẻ `<button>` khi có icon (làm vỡ cơ chế tính toán baseline flexbox căn giữa của trình duyệt).
   - ❌ **CẤM dùng các badge/button màu xanh lá hoặc màu ngoại lai** không nằm trong bảng Design Tokens (giữ giao diện tối giản, sạch sẽ).
4. **Cấu trúc HTML chuẩn mẫu (Template Code)**:

```html
<!-- Nút có icon chuẩn: KHÔNG inline styles trên icon, bọc chữ trong thẻ <span> -->
<button type="button" class="button button-secondary">
    <span class="dashicons dashicons-admin-page"></span>
    <span><?php esc_html_e( 'Copy Base URL', 'align' ); ?></span>
</button>

<button type="button" class="button button-primary">
    <span class="dashicons dashicons-controls-play"></span>
    <span><?php esc_html_e( 'Send Request', 'align' ); ?></span>
</button>
```

### 3.4 Căn Chỉnh Icon Ngoài Nút Bấm: Tab, Segmented Control, Nhãn Inline (Non-Button Icon Alignment)

Icon Dashicons bị lệch không chỉ trong nút bấm mà còn trong nav tab, segmented control và nhãn inline. Nguyên nhân gốc: Dashicons mặc định là `display: inline-block` với `line-height: 1` kế thừa từ WordPress, nên baseline không khớp chữ bên cạnh.

1. **Container bọc icon + chữ phải là flex**:
   - Mọi tab/label chứa icon bắt buộc: `display: inline-flex; align-items: center; gap: 6px;`.
2. **Icon dùng CHUNG quy chuẩn 16px với nút bấm**:
   - Áp dụng đồng loạt cho mọi ngữ cảnh bằng selector gộp, KHÔNG viết riêng từng nơi:
     `width: 16px; height: 16px; font-size: 16px; line-height: 1; margin: 0; padding: 0; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;`
   - Bắt buộc target cả `.dashicons` và `.dashicons::before` vì glyph nằm ở pseudo-element.
3. **CẤM KỸ THUẬT**:
   - ❌ **CẤM `line-height` khác `1`** cho `.dashicons` (ví dụ `line-height: 16px`, `line-height: 20px`) — gây lệch trọng tâm dọc.
   - ❌ **CẤM** khai báo kích thước icon rải rác từng selector riêng. Gộp vào MỘT rule chung để tránh lệch do chồng giá trị.

```css
/* Rule gộp chuẩn: 1 nơi khai báo cho nút + tab + nhãn inline */
.button .dashicons,
.button .dashicons::before,
.align-nav-tab .dashicons,
.align-nav-tab .dashicons::before {
    width: 16px;
    height: 16px;
    font-size: 16px;
    line-height: 1;
    margin: 0;
    padding: 0;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
```

```html
<!-- Nav tab có icon: container tự flex, icon kế thừa rule gộp -->
<button type="button" class="align-nav-tab">
    <span class="dashicons dashicons-shield"></span>
    <?php esc_html_e( 'Authentication', 'align' ); ?>
</button>
```

---

## 4. Quy Chuẩn Khung Card (Card Design System)

Mọi nội dung cài đặt đều phải nằm trong `.align-settings-card`.

### 4.1 Card Tiêu Chuẩn (Standard Card)

```html
<div class="align-settings-card">
    <div class="align-card-header">
        <div>
            <h3 class="align-card-title"><?php esc_html_e( 'Tiêu Đề Phân Hệ', 'align' ); ?></h3>
            <p class="align-card-desc"><?php esc_html_e( 'Mô tả ngắn gọn chức năng của phân hệ.', 'align' ); ?></p>
        </div>
        <!-- Action controls góc phải (nếu có) -->
    </div>
    <div class="align-card-body">
        <!-- Form table, bảng dữ liệu hoặc giao diện tương tác -->
    </div>
</div>
```

---

## 5. Quy Chuẩn Accordion (Collapsible Card Standard)

Dành cho các bảng danh sách nhiều hàng, công cụ console hoặc tiện ích nâng cao:

### 5.1 Cấu Trúc HTML Accordion Chuẩn

```html
<div class="align-settings-card is-accordion" id="my-feature-card">
    <div class="align-card-header" id="my-feature-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle', 'align' ); ?>">
        <div>
            <h3 class="align-card-title"><?php esc_html_e( 'Tiêu Đề Accordion', 'align' ); ?></h3>
            <p class="align-card-desc"><?php esc_html_e( 'Mô tả tính năng.', 'align' ); ?></p>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <!-- Nút hành động nhanh trên header (nếu có) -->
            <button type="button" class="button button-secondary" id="my-header-action-btn">
                + Thao Tác Nhanh
            </button>
            <!-- Toggle indicator bắt buộc -->
            <div class="align-accordion-toggle">
                <span id="my-feature-toggle-label"><?php esc_html_e( 'Show Details', 'align' ); ?></span>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </div>
        </div>
    </div>

    <!-- Body mặc định BẮT BUỘC phải ẩn: style="display: none;" -->
    <div class="align-card-body" id="my-feature-body" style="display: none;">
        <!-- Nội dung chi tiết -->
    </div>
</div>
```

### 5.2 Quy Tắc Hành Vi JavaScript Của Accordion
1. **Trạng thái khởi tạo**: `display: none` (mặc định đóng, không được mở sẵn làm dài trang).
2. **Loại trừ click con**: Sự kiện click trên header phải bỏ qua khi người dùng click vào `button`, `a`, `input`, `label` bên trong header (`if (e.target.closest('button, a, input, label')) return;`).
3. **Tự động mở khi bấm nút con**: Nếu người dùng nhấn nút hành động (ví dụ `+ Add Custom Size`) khi accordion đang đóng, script **bắt buộc phải tự động mở bung accordion trước** rồi mới kích hoạt tính năng.
4. **Viền dưới tự ẩn**: Khi card đóng, CSS sẽ tự động gỡ bỏ `border-bottom` của `.align-card-header` để tạo thành thanh bar bo góc liền mạch. Khi mở (`.is-open`), viền dưới và mũi tên xoay 180° tự động kích hoạt.

---

## 6. Quy Chuẩn Form Controls & Inputs

1. **Chiều cao & Padding**:
   - `input[type="text"]`, `input[type="password"]`, `input[type="url"]`, `input[type="number"]`, `select`: chiều cao tiêu chuẩn `34px`, padding `0 10px`, viền `1px solid #cbd5e1`, bo góc `6px`.
2. **Focus State**:
   - `border-color: #0284c7; outline: none; box-shadow: 0 0 0 1px #0284c7;`
3. **Checkbox & Radio**:
   - Đặt trong thẻ `<label>` có `display: inline-flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; font-size: 13px;`.

---

## 7. Quy Chuẩn Header Banner & Isolation Thông Báo (Notices)

1. **Header Banner (`.align-settings-header`)**:
   - Chiều cao thanh mảnh: `min-height: 56px`, padding `12px 20px`, viền `1px solid #e2e8f0`, bo góc `8px`, nền trắng.
   - Logo thương hiệu: Chữ `A` trắng nền xanh `#0284c7`, kích thước `36x36px`, bo góc `8px`.
   - Tiêu đề: Sử dụng `<div class="align-brand-heading">`, **KHÔNG DÙNG THẺ `<h1>`** bên trong banner để tránh WordPress Core JS (`common.js`) tự động chèn thông báo `.notice` vào làm vỡ layout.
2. **Screen Reader H1 & Notice Placement (Notice Isolation & Deduplication)**:
   - Đặt `<h1 class="screen-reader-text"><?php esc_html_e( 'Align Theme Settings', 'align' ); ?></h1>` ngay đầu trang `.align-settings-wrap`.
   - **Tuyệt đối không gọi `settings_errors()` trong layout view**: WordPress Core (`wp-admin/admin-header.php` -> `wp-admin/options-head.php`) đã tự động gọi `settings_errors()` khi trang thuộc `$parent_file === 'options-general.php'`. Việc gọi thêm `settings_errors()` trong template view sẽ gây lỗi lặp duplicate 2 thông báo.
   - Script `settings-layout.js` có hàm phòng vệ `repositionNotices()` tự động quét các thông báo hệ thống (`.notice`, `.updated`, `.error`), khử trùng lặp (deduplication bằng `Set`) và di chuyển chúng lên đầu trang, đặt ngay trên thanh Segmented Nav Tabs (`.align-nav-tabs-bar`) với viền bo tròn `6px`.

---

## 8. Quy Chuẩn Giới Hạn Kích Thước File (< 500 Dòng)

Theo quy định bắt buộc trong `AGENTS.md`:
- **Mọi file mã nguồn (PHP, CSS, JS) đều phải dưới 500 dòng**.
- Phân chia trách nhiệm rõ ràng:
  - `layout.php`: Khung sườn tổng, banner, nav tabs bar, include sub-tabs.
  - `settings-layout.css`: Toàn bộ style hệ thống, cards, buttons, sticky save bar.
  - `settings-layout.js`: Quản lý tab switching, hash routing, unsaved state tracking.
  - Các file tab độc lập: `tab-builder.php`, `tab-connect-api.php`, `tab-media.php`, v.v. Khi tab phình to, tách thành các partial `part-*.php` hoặc asset JS riêng.

---

## 9. Quy Chuẩn Bảng Dữ Liệu & Hiển Thị Text Thuần Không Viền (Table Design & Borderless Display)

1. **Typography-First Thay Thế Input/Select Cho Dữ Liệu Tĩnh & Định Danh**:
   - Đối với các cột dữ liệu chỉ đọc hoặc định danh nội dung (như **File Name**, **Type** trong bảng Custom Assets):
     - **Tuyệt đối không dùng input text có viền xám đục (`#f6f7f7`) hoặc select box** để hiển thị dữ liệu tĩnh đã định danh.
     - Thay thế bằng thẻ text `<span>` phẳng hoàn toàn không viền (`border: none`).
     - **File Name**: Sử dụng `span.align-asset-filename` với thông số `font-size: 13px`, `font-weight: 500`, màu `Slate 800` (`#1e293b`), `word-break: break-all`.
     - **Type**: Sử dụng `span.align-asset-type-text` với thông số `font-size: 12.5px`, `font-weight: 600`, màu `Slate 600` (`#475569`), `text-transform: uppercase`.
2. **Tooltip Native (`title`) Cho Dữ Liệu Đầy Đủ**:
   - Khi rút gọn đường dẫn URL thành tên file ngắn gọn (basename), bắt buộc gán thuộc tính `title="${url}"` để người dùng có thể hover chuột xem toàn bộ đường dẫn URL gốc mà không gây rối mắt cho bảng.
3. **Bảo Toàn State Form Bằng Hidden Input**:
   - Để không làm mất dữ liệu khi submit form lên WordPress `options.php`, bắt buộc giữ các thẻ `<input type="hidden">` tương ứng mang đúng `name` và class ban đầu (ví dụ: `input.align-asset-url-input` và `input.align-asset-type-select`).

---

## 10. Checklist Tự Kiểm Tra Trước Khi Hoàn Thành (Pre-Flight Checklist)

Mọi thay đổi giao diện Admin Settings bắt buộc kiểm tra qua danh sách này:

- [ ] Tất cả các nút bấm đều sử dụng `background: transparent !important` (không nền đục, không nền xanh WordPress).
- [ ] Không có inline CSS cho màu sắc/viền của nút bấm.
- [ ] Bảng dài / công cụ nặng được bọc trong Accordion với trạng thái mặc định là **Closed**.
- [ ] Cột định danh/chỉ đọc trong bảng hiển thị dạng text thuần không viền (`<span>`), kèm tooltip `title` và `<input type="hidden">`.
- [ ] 0 biểu tượng Emoji trong mã nguồn, văn bản và nhãn.
- [ ] Thẻ `<h1>` không nằm trong banner header.
- [ ] Đã chạy kiểm tra cú pháp PHP: `php -l <file>` (0 lỗi).
- [ ] Đã chạy kiểm tra số dòng: `wc -l <file>` (< 500 dòng).
