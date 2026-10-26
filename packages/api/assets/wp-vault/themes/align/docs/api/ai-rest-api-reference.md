# 🤖 Dedicated AI REST API Reference (`align-ai/v1`)

> **Phiên bản API**: `1.0.0`  
> **Namespace**: `/wp-json/align-ai/v1`  
> **Bộ điều khiển lõi**: [`class-align-ai-endpoint.php`](file:///d:/Workspace/Personal/Align/palmeto-stg/app/public/wp-content/themes/align/core/includes/ai-endpoint/class-align-ai-endpoint.php) & [`class-align-ai-page-controller.php`](file:///d:/Workspace/Personal/Align/palmeto-stg/app/public/wp-content/themes/align/core/includes/ai-endpoint/class-align-ai-page-controller.php)  
> **Giao diện quản trị**: WP Admin > Settings > Align Settings > Tab **API** (`options-general.php?page=align-settings#api`)

---

## 📌 1. Tổng Quan & Mục Tiêu Kiến Trúc

Phân hệ **Dedicated AI REST API Endpoint** được thiết kế chuyên biệt nhằm cung cấp một giao thức giao tiếp lập trình (Programmatic Interface) chuẩn RESTful với hiệu năng cao, bảo mật chặt chẽ và không phụ thuộc giao diện người dùng, phục vụ tối ưu cho:
1. **Autonomous AI Agents (Antigravity, Cursor, LangChain, AutoGPT...)**: Tự động hóa hoàn toàn quy trình sinh trang (Generate Landing Page), cập nhật nội dung (Update Page), trích xuất và tối ưu mã nguồn (Audit & Refactor HTML/CSS/JS).
2. **External CI/CD & Developer Workflows**: Cho phép kiểm thử tự động, tích hợp Postman/Insomnia, hoặc triển khai mã nguồn từ các kho lưu trữ bên ngoài thẳng vào WordPress.
3. **Decoupled Architecture (Kiến trúc phân rã 3 tầng)**: Tự động tách biệt và quản lý độc lập 3 luồng dữ liệu của trang:
   - **HTML Body State**: Cấu trúc nội dung trực quan thuần túy.
   - **Scoped CSS State**: CSS tùy biến theo trang (`style-page-{slug}` hoặc file CSS vật lý độc lập).
   - **Scoped JS State**: Javascript theo trang (`script-page-{slug}` hoặc file JS độc lập).
   - Tự động đóng gói an toàn vào Gutenberg Block `<!-- wp:align/custom-html ... -->` mà không bị can thiệp bởi bộ lọc KSES làm mất thẻ `style` hay `script`.

---

## 🔐 2. Cơ Chế Xác Thực Kép (Dual-Mode Authentication)

Để phục vụ linh hoạt cả AI Agents chạy bên ngoài lẫn Dashboard Admin chạy trực tiếp trên trình duyệt, hệ thống áp dụng cơ chế xác thực kép thông qua hàm `Align_AI_Endpoint::check_permission()`:

```mermaid
graph TD
    Req[REST API Request Đến /wp-json/align-ai/v1/*] --> CheckHeader{Có Header Authorization: Basic<br>hoặc wp_is_application_password_request?}
    CheckHeader -- CÓ (AI Agent / Postman) --> CheckAuthUser[Xác thực thông tin User qua WP Application Password]
    CheckHeader -- KHÔNG (Browser Session) --> CheckCookie[Kiểm tra Cookie Session & X-WP-Nonce]
    CheckAuthUser --> CheckCaps{User có quyền edit_pages<br>hoặc manage_options?}
    CheckCookie --> CheckCaps
    CheckCaps -- HỢP LỆ --> Allow[Cho phép thực thi Endpoint (200 / 201)]
    CheckCaps -- KHÔNG HỢP LỆ --> Deny[Từ chối truy cập (401 / 403 Forbidden)]
```

### Chế độ 1: Dành cho Autonomous AI Agents & Postman (Application Passwords)
- **Chuẩn xác thực**: HTTP Basic Authentication (`Authorization: Basic <base64(username:app_password)>`).
- **Cách tạo**:
  1. Đăng nhập WP Admin > Users > Profile (`wp-admin/profile.php`).
  2. Cuộn xuống mục **Application Passwords**.
  3. Nhập tên định danh (ví dụ: `Antigravity-AI` hoặc `Postman-Dev`) -> Bấm **Add New Application Password**.
  4. Sao chép chuỗi mật khẩu 24 ký tự dạng `xxxx xxxx xxxx xxxx xxxx xxxx`.
- **Ưu điểm**: Hoạt động tức thì, không bị hết hạn phiên theo Cookie, không yêu cầu WP Nonce.

### Chế độ 2: Dành cho WP Admin Dashboard (Cookie Session & REST Nonce)
- Tự động sử dụng phiên đăng nhập hiện tại của Administrator.
- Yêu cầu đính kèm header: `X-WP-Nonce: <wp_rest nonce>` (được khởi tạo tự động trong giao diện Admin qua `wp_create_nonce('wp_rest')`).

---

## ⚡ 3. Danh Mục Chi Tiết 6 REST Endpoints

### 1. Kiểm tra kết nối & Xác thực (Ping)
* **Route**: `GET /wp-json/align-ai/v1/ping`
* **Quyền hạn**: Đã đăng nhập (`edit_pages` hoặc `manage_options`).
* **Mục đích**: Xác thực credential, kiểm tra quyền hạn và trạng thái sẵn sàng của API.
* **cURL mẫu**:
  ```bash
  curl -X GET "https://example.com/wp-json/align-ai/v1/ping" \
    -u "admin_username:YOUR_APPLICATION_PASSWORD" \
    -H "Accept: application/json"
  ```
* **Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Align Dedicated AI REST API is active and operational.",
    "user": {
      "id": 1,
      "login": "admin_username",
      "roles": ["administrator"]
    },
    "version": "1.0.0",
    "auth_type": "application_password"
  }
  ```

---

### 2. Lấy danh sách trang (List Pages)
* **Route**: `GET /wp-json/align-ai/v1/pages`
* **Query Parameters**:
  - `per_page` (int, default: 10, max: 100): Số trang trên một lượt.
  - `page` (int, default: 1): Trang hiện tại.
  - `search` (string, optional): Từ khóa tìm kiếm theo tiêu đề/nội dung.
  - `status` (string, optional, default: `publish`): `publish`, `draft`, `pending`, `any`.
* **Response Headers**:
  - `X-WP-Total`: Tổng số trang tìm thấy.
  - `X-WP-TotalPages`: Tổng số trang phân trang.
* **Response (200 OK)**:
  ```json
  [
    {
      "id": 102,
      "title": "Home Landing Page",
      "slug": "home",
      "status": "publish",
      "url": "https://example.com/home",
      "author": 1,
      "date": "2026-09-11 08:30:00",
      "modified": "2026-09-11 09:15:00",
      "lang": "en",
      "has_custom_html": true
    }
  ]
  ```

---

### 3. Đọc chi tiết trang đã phân rã (Get Page Detail)
* **Route**: `GET /wp-json/align-ai/v1/pages/{id}`
* **Mục đích**: Đọc toàn diện một trang với cấu trúc phân rã sạch (`html`, `css`, `js` độc lập). Tự động nạp CSS/JS từ file vật lý nếu trang đang lưu dưới dạng external assets (`Align_Builder_Assets`).
* **Response (200 OK)**:
  ```json
  {
    "id": 102,
    "title": "Home Landing Page",
    "slug": "home",
    "status": "publish",
    "url": "https://example.com/home",
    "html": "<section class=\"hero\"><h1>Welcome to Align</h1></section>",
    "css": ".hero { padding: 60px 20px; text-align: center; }",
    "js": "console.log('Hero initialized');",
    "author": 1,
    "date": "2026-09-11 08:30:00",
    "modified": "2026-09-11 09:15:00",
    "lang": "en"
  }
  ```

---

### 4. Tạo trang mới bằng 1 Single Request (Create Page)
* **Route**: `POST /wp-json/align-ai/v1/pages`
* **Mục đích**: Cho phép AI tạo trọn vẹn một Landing Page mới chỉ bằng 1 HTTP request duy nhất.
* **Request JSON Payload**:
  ```json
  {
    "title": "Sample AI Landing Page",
    "slug": "sample-ai-landing",
    "status": "publish",
    "lang": "en",
    "html": "<section class=\"hero-section\">\n  <h1>Autonomous Creation</h1>\n  <p>Engineered with Align Dedicated AI REST API.</p>\n</section>",
    "css": ".hero-section { padding: 80px 20px; text-align: center; }\n.hero-section h1 { font-size: 40px; color: #0f172a; font-weight: 800; }",
    "js": "console.log(\"[Align AI] Page mounted dynamically.\");"
  }
  ```
* **Quy trình xử lý tự động trong Backend**:
  1. Kiểm tra trường `title` bắt buộc.
  2. Tạo bản ghi bài viết mới (`wp_insert_post` post_type `page`).
  3. Xử lý lưu CSS/JS ra file vật lý tách biệt qua `Align_Builder_Assets::save_external_assets()` nếu hệ thống bật chế độ external assets.
  4. Nén tối ưu HTML bằng `Align_Builder_Minifier::minify_payload()` (nếu được bật).
  5. Đóng gói vào Gutenberg Block comment `<!-- wp:align/custom-html ... -->`.
  6. Lưu mã ngôn ngữ vào post meta `_align_lang`.
* **Response (201 Created)**: Trả về object chi tiết trang kèm ID mới được tạo.

---

### 5. Cập nhật trang (Update Page)
* **Route**: `PUT /wp-json/align-ai/v1/pages/{id}`
* **Mục đích**: Cập nhật toàn bộ hoặc một phần (Partial Update) của trang. Nếu một trường như `css` hoặc `js` không được truyền lên, hệ thống sẽ tự động giữ nguyên dữ liệu hiện có mà không làm mất code cũ.
* **Request JSON Payload** (ví dụ cập nhật CSS và HTML):
  ```json
  {
    "html": "<section class=\"hero-v2\"><h1>New Modern Hero</h1></section>",
    "css": ".hero-v2 { padding: 100px 20px; background: #f8fafc; }"
  }
  ```
* **Response (200 OK)**: Trả về object trang sau khi đã cập nhật thành công.

---

### 6. Xóa trang & Dọn dẹp tài nguyên (Delete Page)
* **Route**: `DELETE /wp-json/align-ai/v1/pages/{id}`
* **Quy trình xử lý an toàn**:
  1. Kiểm tra tồn tại của Page ID và quyền xóa bài viết (`delete_post`).
  2. Dọn dẹp triệt để các file CSS/JS tĩnh trên ổ đĩa liên kết với ID thông qua `Align_Builder_Asset_Cleaner::delete_post_assets( $post_id )`.
  3. Xóa vĩnh viễn bài viết khỏi cơ sở dữ liệu (`wp_delete_post( $post_id, true )`).
* **Response (200 OK)**:
  ```json
  {
    "deleted": true,
    "id": 102,
    "title": "Sample AI Landing Page",
    "message": "Page 102 successfully deleted and associated disk assets purged."
  }
  ```

---

## 🖥️ 4. Giao Diện Quản Trị Tab API (Admin Settings UI)

Tại đường dẫn `wp-admin/options-general.php?page=align-settings#api`, trang quản trị cung cấp giao diện trực quan tuân thủ tuyệt đối [Align Settings Style Guide](../architecture/settings-style-guide.md):

1. **Card 1: Overview & Autonomous AI Authentication (Accordion - Default Closed)**:
   - Hiển thị Base API URL kèm nút Copy 1-click.
   - Hướng dẫn chi tiết thiết lập WordPress Application Passwords và cấu hình trong Postman.
2. **Card 2: API Endpoints & Specifications (Accordion - Default Closed)**:
   - Pill Selector cho phép chuyển đổi tức thì giữa 6 endpoints.
   - Hiển thị đầy đủ Method badge, Route URL, Required Headers.
   - Khung Request Payload JSON định dạng chuẩn kèm nút "Copy JSON".
   - Khung lệnh cURL hoàn chỉnh kèm nút "Copy cURL" để Import thẳng vào Postman.
   - Nút "Load in Console" giúp nạp thẳng cấu hình endpoint vào Interactive Console.
3. **Card 3: Interactive REST Console (Accordion - Default Open)**:
   - **Mở mặc định ngay khi tải trang**, giúp kiểm thử không độ trễ.
   - Nút Quick Template **`Create Page (POST)`** được kích hoạt sẵn (`button-primary is-active`).
   - Form điền sẵn Method `POST`, Route URL đầy đủ, và Payload JSON mẫu hoàn chỉnh.
   - Nút "Send Request" kích hoạt gọi API trực tiếp với `X-WP-Nonce` tự động.
   - Khung Live Response hiển thị HTTP status badge phân biệt màu (2xx xanh, 4xx vàng, 5xx đỏ), latency (ms), và response body định dạng JSON kèm nút Copy.

---

## 🛡️ 5. Tiêu Chuẩn Kỹ Thuật & Bảo Mật

* **File Line Limit (< 500 dòng/file)**: Toàn bộ các file mã nguồn đều được kiểm soát kích thước nghiêm ngặt:
  - `class-align-ai-endpoint.php`: ~135 dòng.
  - `class-align-ai-page-controller.php`: ~240 dòng.
  - `tab-api.php`: 405 dòng.
  - `tab-api.js`: 483 dòng.
* **XSS & Data Sanitization**: 100% output được escape qua `esc_textarea()`, `esc_html_e()`, `esc_attr_e()`, `esc_url()`. Dữ liệu nhập từ REST request được làm sạch qua `sanitize_text_field()`, `sanitize_title()`, `absint()`.
* **Zero Emojis**: 100% giao diện, nhãn nút, tiêu đề đều tuân thủ nguyên tắc Typography-First và Dashicons.
* **Button Standard**: 100% nút bấm kế thừa định dạng viền mỏng 1px, chiều cao 32px, bo góc 6px, nền trong suốt (`background: transparent !important`).
