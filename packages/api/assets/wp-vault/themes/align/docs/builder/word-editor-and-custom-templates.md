# 📝 Word Editor Mode & Custom Layout Templates Subsystem

> **Dedicated Architectural & Technical Documentation**
> Tài liệu kỹ thuật chi tiết về hai phân hệ mở rộng của Align Visual Builder:
> 1. **Word Editor Mode**: Chế độ soạn thảo văn bản tự do chuẩn Word / Notion trên Blank Canvas với thanh công cụ nổi (Bubble Toolbar), Gutter Inserter và cơ chế chống chồng đè (Anti-Overlap Engine).
> 2. **Custom Layout Templates Subsystem**: Hệ thống tạo, lưu trữ, chỉnh sửa toàn diện HTML / CSS / JS và đồng bộ 2 tầng (LocalStorage & WordPress REST API) cho các Layout Template tái sử dụng.

---

## 📌 PHẦN 1: WORD EDITOR MODE (SOẠN THẢO VĂN BẢN KHÔNG PHÂN TÂM)

### 1.1 Mục Tiêu & Bài Toán (Rationale)
* **Khắc phục điểm nghẽn Blank Canvas**: Trước đây, khi người dùng mở một trang/bài viết mới hoàn toàn trống, việc phải kéo thả từng Section, Row, Col hoặc Block container khiến trải nghiệm soạn nội dung ban đầu trở nên phức tạp.
* **Trải nghiệm viết thuần túy (Distraction-free Writing)**:
  * Trang soạn thảo hiển thị dạng trang giấy tài liệu (Word Paper) khổ chuẩn A4/Letter căn giữa màn hình.
  * Thao tác phím tự nhiên: Gõ phím tự do, ấn `Enter` tự động sinh thẻ `<p>`, ấn `Shift + Enter` ngắt dòng `<br>`.
  * Hỗ trợ gõ tắt Markdown (Markdown shortcuts) tự biến đổi thành khối tiêu đề, danh sách, trích dẫn.

---

### 1.2 Kiến Trúc & Cấu Trúc File
Tuân thủ nghiêm ngặt quy chuẩn **< 500 dòng/file** theo `AGENTS.md`:

| File | Vai Trò & Mô Tả | Số Dòng |
| :--- | :--- | :--- |
| [`align/core/builder/libs/builder/engine/builder-word-editor.js`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/builder/libs/builder/engine/builder-word-editor.js) | Module điều khiển chính `Vvveb.WordEditor`: Quản lý canvas tài liệu, Bubble Toolbar, Gutter Inserter, phím tắt Markdown và Image actions. | 485 dòng |
| [`align/core/builder/css/components/editor-word-mode.css`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/builder/css/components/editor-word-mode.css) | Toàn bộ styling cho `.align-document-container`, `.word-bubble-toolbar`, `.word-gutter-inserter`, figure ảnh và quy tắc triệt tiêu overlay. | 343 dòng |
| [`align/core/builder/libs/builder/engine/builder-highlight-manager.js`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/builder/libs/builder/engine/builder-highlight-manager.js) | Chặn kích hoạt `#highlight-box` và nhãn badge (`#highlight-name`) khi chuột di chuyển trong tài liệu Word. | 410 dòng |
| [`align/core/builder/libs/builder/engine/builder-selection-manager.js`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/builder/libs/builder/engine/builder-selection-manager.js) | Chặn hiển thị viền xanh `#select-box` khi click/chọn các thẻ văn bản trong `.align-document-container`. | 487 dòng |
| [`align/core/builder/partials/canvas.html`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/builder/partials/canvas.html) | Khai báo cấu trúc DOM của `#word-bubble-toolbar` và `#word-gutter-inserter` bên trong `#iframe-layer`. | 345 dòng |

---

### 1.3 Cơ Chế Kỹ Thuật Trọng Yếu

#### 1. Khởi Tạo Tự Động Trên Trang Trống (`checkAndInitEmptyCanvas`)
* Kiểm tra `Vvveb.Builder.frameBody`: Lọc bỏ các thẻ kỹ thuật (`SCRIPT`, `STYLE`, `LINK`, `META`, `NOSCRIPT`, `TEMPLATE`).
* Nếu trang chưa có nội dung hoặc chỉ có khoảng trắng, hàm tự động tạo khung chứa sạch:
  ```html
  <article class="align-document-container container py-5" contenteditable="true" spellcheck="false">
    <h1 class="align-doc-title" data-placeholder="Document Title..."></h1>
    <p class="align-doc-p" data-placeholder="Start typing your text here (press Enter for new paragraph)..."></p>
  </article>
  ```
* Bổ sung mutation vào `Vvveb.Undo` và đồng bộ lại `Vvveb.TreeList`.

#### 2. Floating Bubble Toolbar & Cơ Chế Chống Tràn Lề / Tự Đảo Vị Trí
* **Kích hoạt**: Bắt sự kiện `selectionchange`, `mouseup`, `keyup`. Khi vùng chọn text có độ dài `> 0`, tính toán bounding rectangle `rect = range.getBoundingClientRect()`.
* **Kẹp lề thông minh (Horizontal Clamping)**:
  Tránh hiện tượng nửa bên trái toolbar bị thò ra ngoài viền giấy (tràn sang vùng xám của canvas) khi chọn văn bản ở đầu dòng:
  ```javascript
  let selCenterX = Math.round(rect.left + (rect.width / 2));
  let clampedX = Math.max(toolbarHalf + 16, Math.min(maxAvailableWidth - toolbarHalf - 16, selCenterX));
  toolbar.style.left = clampedX + "px";
  ```
* **Mũi tên trượt động (`--arrow-x`)**:
  Mũi tên ở đáy toolbar tự động trượt theo biến CSS `--arrow-x: calc(50% + ${arrowOffset}px)` để luôn trỏ đúng tâm đoạn văn bản đang bôi đen dù thân toolbar đã bị kẹp cố định vị trí.
* **Tự đảo vị trí xuống dưới (`.is-below`)**:
  Nếu dòng chữ nằm sát đỉnh màn hình (`rect.top < 65px`), toolbar tự gán class `.is-below`, đặt tại `rect.bottom + 14px` và mũi tên quay ngược lên trên, không bao giờ bị che khuất bởi thanh công cụ trên cùng.

#### 3. Cơ Chế Triệt Tiêu Chồng Đè (Anti-Overlap Engine)
* **Vấn đề đã khắc phục**: Trong Builder truyền thống, khi click vào một thẻ (ví dụ `<li>` hay `<p>`), hệ thống sẽ vẽ khung viền xanh `#select-box` kèm nhãn badge `#highlight-name` (ví dụ `"LI"` hoặc `"P"`) nằm cách lề trên `-22px`. Khi bôi đen chữ, nhãn này đè trực tiếp lên chữ và mũi tên của Bubble Toolbar.
* **Giải pháp đa tầng**:
  1. Trong `builder-highlight-manager.js`: Kiểm tra nếu `Vvveb.WordEditor.isActive` hoặc `event.target.closest(".align-document-container")` thì lập tức `self.highlightBox.hide()` và return.
  2. Trong `builder-selection-manager.js`: Nếu `target.closest(".align-document-container")`, lập tức ẩn `#select-box` và `#select-actions`, bảo toàn state `selectedEl` cho sidebar mà không vẽ khung đè lên chữ.
  3. Trong `editor-word-mode.css`: Cưỡng chế ẩn bằng rule CSS ưu tiên cao (`!important`):
     ```css
     body.is-word-mode #highlight-box,
     body.is-word-mode #select-box,
     body.is-word-mode #section-actions,
     #iframe-layer.word-active #highlight-box,
     #iframe-layer.word-active #select-box {
       display: none !important;
     }
     ```

#### 4. Phím Tắt Markdown (Markdown Inline Shortcuts)
Khi người dùng gõ ký tự phím tắt và ấn phím `Space` tại đầu đoạn văn bản `<p>`:
* `# + Space` -> Chuyển đoạn thành `<h1>`
* `## + Space` -> Chuyển đoạn thành `<h2>`
* `### + Space` -> Chuyển đoạn thành `<h3>`
* `- + Space` hoặc `* + Space` -> Chuyển đoạn thành danh sách `<ul><li>`
* `1. + Space` -> Chuyển đoạn thành danh sách có thứ tự `<ol><li>`
* `> + Space` -> Chuyển đoạn thành trích dẫn `<blockquote>`

#### 5. Thanh Công Cụ Thao Tác Ảnh (Image Toolbar)
* Click vào bất kỳ thẻ `<img>` trong tài liệu:
  * Tự động bọc trong `<figure class="align-doc-figure is-selected">`.
  * Hiển thị menu nổi với các công cụ: Căn lề Trái, Giữa, Phải; Resize nhanh theo tỷ lệ chuẩn `25%`, `50%`, `75%`, `100%`; Thêm chú thích ảnh (Caption).

#### 6. Luồng Phím Tắt Soạn Thảo Content Mode (Keyboard Flow)
Trong chế độ **Content Mode** (`.content-mode-active`):
* `Single Click`: Nhấp chuột đơn vào bất kỳ text element nào trên Canvas để bắt đầu inline editing ngay lập tức tại vị trí nhấp (không cần double click).
* `Escape`: Hủy toàn bộ thay đổi vừa gõ và khôi phục nội dung ban đầu (`innerHTML = this.oldValue`) trước khi thoát soạn thảo, không sinh thêm mutation trong Undo stack và không kích hoạt dirty state.
* `Tab` / `Shift + Tab`: Tự động commit nội dung của node hiện tại và chuyển con trỏ soạn thảo sang phần tử văn bản kế tiếp (`Tab`) hoặc trước đó (`Shift + Tab`) theo thứ tự DOM của trang (`TreeWalker`), đồng thời tự động cuộn phần tử vào giữa khung nhìn (`scrollIntoView({block: 'center'})`).
* `Enter`: Xuống dòng tự nhiên bên trong khối văn bản.

---

## 📌 PHẦN 2: CUSTOM LAYOUT TEMPLATES SUBSYSTEM

### 2.1 Mục Tiêu & Kiến Trúc
Hệ thống Layout Template tùy biến cho phép Developer và người dùng thiết kế, lưu trữ và tái sử dụng các khối bố cục hoàn chỉnh bao gồm HTML Structure, Custom CSS và Custom JS riêng biệt.

* **Đồng bộ dữ liệu hai tầng (Two-Tier Sync)**:
  * **Tầng 1 (Local Cache)**: Lưu trữ tức thì vào `localStorage['vvveb_custom_templates']` để giao diện phản hồi 0ms.
  * **Tầng 2 (Database Persistence)**: Đồng bộ qua WordPress REST API endpoint `/wp-json/align-builder/v1/custom-templates` lưu trong `wp_options['align_builder_custom_templates']`.

---

### 2.2 Cấu Trúc File & Thành Phần

| File | Vai Trò & Mô Tả | Số Dòng |
| :--- | :--- | :--- |
| [`align/core/builder/libs/builder/plugin-custom-templates.js`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/builder/libs/builder/plugin-custom-templates.js) | Quản lý vòng đời template: Nạp REST/LocalStorage, bóc tách độc lập CSS/JS qua `afterDrop(node)`, hiển thị thẻ template trong sidebar, xử lý modal Create/Edit. | 486 dòng |
| [`align/core/inc/builder/class-builder-custom-templates-handler.php`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/inc/builder/class-builder-custom-templates-handler.php) | Backend REST API handler: Cung cấp các route `GET` (danh sách), `POST` (tạo mới/cập nhật), `DELETE` (xóa template). | 126 dòng |
| [`align/core/builder/partials/modals.html`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/builder/partials/modals.html) | Modal giao diện `#customTemplateModal`: Form soạn thảo Template Name, HTML, Custom CSS, Custom JS. | 181 dòng |
| [`align/core/builder/libs/builder/engine/builder-section-list.js`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public/wp-content/themes/align/core/builder/libs/builder/engine/builder-section-list.js) | Chặn click lan truyền khi người dùng nhấn nút Edit hoặc Delete trên thẻ template. | 385 dòng |

---

### 2.3 Giao Diện & Tính Năng Chỉnh Sửa

#### 1. Thẻ Template Trong Sidebar (Dual Action Buttons)
Trên mỗi thẻ template thuộc danh mục **Layout** (`li[data-drag-type="section"]`), hệ thống tự động gắn cụm Action Bar tại góc trên bên phải (`.custom-tpl-actions`):
* **Nút Edit (Sửa)**:
  * Class: `.edit-custom-tpl-btn`
  * Icon: `la la-edit` màu xanh cyan (`#0284c7`), nền `rgba(14, 165, 233, 0.15)`, hover `#0284c7` chữ trắng.
  * Hành động: Mở modal `#customTemplateModal` ở chế độ Edit.
* **Nút Delete (Xóa)**:
  * Class: `.delete-custom-tpl-btn`
  * Icon: `la la-trash` màu đỏ (`#ef4444`), nền `rgba(239, 68, 68, 0.15)`, hover `#ef4444` chữ trắng.
  * Hành động: Xác nhận xóa, loại bỏ khỏi danh sách, xóa khỏi LocalStorage và gọi REST API `DELETE`.
* **Chặn chèn nhầm vào Canvas**:
  Trong `builder-section-list.js`, sự kiện click được kiểm tra:
  ```javascript
  if (e.target.closest(".custom-tpl-actions") || e.target.closest(".delete-custom-tpl-btn") || e.target.closest(".edit-custom-tpl-btn")) return;
  ```
  Đảm bảo thao tác bấm Sửa hoặc Xóa template tuyệt đối không kích hoạt chèn nhầm template vào trang.

#### 2. Modal Chỉnh Sửa HTML, CSS, JS (`#customTemplateModal`)
* **Tinh giản UX**: Toàn bộ Custom Templates mặc định phân loại vào danh mục `Layout`. Trường chọn Category hiển thị trên form đã được lược bỏ để tinh gọn giao diện, thay bằng `<input type="hidden" id="custom-template-group" value="Layout">`. Trường **Template Name** được mở rộng full-width.
* **Hỗ trợ chỉnh sửa toàn diện**:
  * **Template Name**: Tên định danh của layout.
  * **HTML Structure**: Mã HTML cấu trúc của khối.
  * **Custom CSS (Optional)**: Khối CSS tùy biến riêng cho layout này.
  * **Custom JS (Optional)**: Khối JavaScript khởi chạy riêng cho layout này.
* **Kiến trúc bóc tách tài sản độc lập (`cleanTemplateHtml` & `afterDrop`)**:
  Tuân thủ nghiêm ngặt **Quy tắc 2 của `AGENTS.md` (Bảo Toàn State Độc Lập)**, tuyệt đối không nhồi nhét thẻ `<style>` và `<script>` vào bên trong cây DOM của HTML body.
  - **HTML Structure thuần khiết (`cleanTemplateHtml`)**: Tự động lọc bỏ các thẻ `<style>`/`<script>` nhúng sai vị trí trong khối HTML trước khi đăng ký section.
  - **Tự động trích xuất khi Paste**: Khi người dùng dán (paste) mã nguồn thô chứa thẻ `<style>` hoặc `<script>` vào ô HTML Structure trong modal, hệ thống tự động tách CSS và JS vào đúng các trường tương ứng.
  - **Nạp tài sản độc lập qua `afterDrop(node)`**: Khi kéo thả template hoặc chèn từ modal vào Canvas, hook `afterDrop` sẽ tự động hợp nhất CSS vào `Vvveb.StyleManager` & `Vvveb.CssEditor`, và JS vào `Vvveb.JsManager` & `Vvveb.JsEditor`. Do đó, người dùng có thể thoải mái sửa, xóa hoặc thay thế các phần tử HTML trên Canvas mà không bao giờ làm mất CSS/JS của template.
* **Cập nhật dữ liệu tức thì (In-Place Update)**:
  Khi lưu chỉnh sửa (`editingTemplateId`), hệ thống cập nhật đúng vị trí trong mảng `this.templates`, đăng ký lại định nghĩa section trong `Vvveb.Sections.add()`, làm mới UI sidebar và đồng bộ lên WordPress database qua REST API `POST`.
