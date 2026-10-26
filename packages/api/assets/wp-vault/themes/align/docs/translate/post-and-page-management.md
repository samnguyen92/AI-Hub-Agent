# 📝 Quản Lý Bài Viết & Trang Đa Ngôn Ngữ (Post & Page Management)

Tài liệu này hướng dẫn chi tiết quy trình tạo, quản lý và liên kết bản dịch cho bài viết (Blog Posts) và trang tĩnh (Pages) trong hệ thống Theme Align.

---

## 🏷️ 1. Cấu Trúc Dữ Liệu Post Meta (Data Schema)

Hệ thống Align sử dụng 3 trường Post Meta cốt lõi để quản lý trạng thái ngôn ngữ:

| Meta Key | Kiểu Dữ Liệu | Mục Đích Sử Dụng |
| :--- | :--- | :--- |
| `_align_lang` | `string` (e.g. `'en'`, `'zh'`) | Mã ngôn ngữ của bài viết/trang hiện tại. |
| `_align_lang_group` | `string` (UUID v4) | Mã định danh nhóm dịch thuật. Tất cả các bản dịch của cùng một bài viết sẽ chia sẻ chung mã UUID này. |
| `_align_lang_parent` | `int` (Post ID) | ID của bài viết gốc (thường là bài viết ở ngôn ngữ mặc định). |

### Ví Dụ Thực Tế Trong Database:
```sql
-- Bài viết tiếng Anh (gốc): ID = 100
meta_key: _align_lang        => 'en'
meta_key: _align_lang_group  => 'grp_9b2d8f1e'

-- Bài viết tiếng Trung (bản dịch): ID = 105
meta_key: _align_lang        => 'zh'
meta_key: _align_lang_group  => 'grp_9b2d8f1e'
meta_key: _align_lang_parent => 100
```

---

## 📰 2. Quản Lý Bài Viết Blog (`post`) Trên Gutenberg

### 2.1. Bảng Điều Khiển Language Sidebar
Khi biên tập bài viết trong Gutenberg Editor, hệ thống tự động hiển thị bảng **Language** ở cột bên phải (Sidebar):
- **Current Language**: Cho phép xem và chuyển đổi mã ngôn ngữ của bài viết hiện tại.
- **Translations List**: Liệt kê toàn bộ các ngôn ngữ đang kích hoạt trên website.
  - Ngôn ngữ đã có bản dịch: Hiển thị icon bút chì `Edit [Language]` dẫn trực tiếp sang bài viết dịch.
  - Ngôn ngữ chưa có bản dịch: Hiển thị nút **+ Add Translation** để tạo bản dịch mới.

### 2.2. Tính Năng 1-Click Copy & Translate (Tạo Bản Dịch Bằng AI)
Khi bấm tạo bản dịch mới cho bài viết:
1. Hệ thống tự động nhân bản nội dung bài gốc sang bài mới.
2. Gán cùng mã `_align_lang_group` để liên kết 2 bài viết.
3. Tích hợp AI (OpenAI / Claude) để tự động dịch:
   - Tiêu đề bài viết (`post_title`).
   - Nội dung thuần túy của các Gutenberg Core Blocks (`post_content`).
   - Dữ liệu SEO (SEO Title, Meta Description, Keywords).

> [!IMPORTANT]
> **Quy Chuẩn Bắt Buộc: Zero Align Custom Blocks trên Blog (`post`)**:
> - Bài viết blog là nơi biên tập nội dung thuần túy qua Gutenberg Core blocks (Paragraph, Heading, Image, List, Quote, Table...).
> - Tuyệt đối không sử dụng Align Custom Blocks (`align/*`) trên bài viết blog để đảm bảo tương thích 100% với AI Translator và tối ưu SEO.

### 2.3. Danh Sách Bài Viết WP Admin (`/wp-admin/edit.php`)
- **Cột Language**: Hiển thị cờ quốc gia và mã ngôn ngữ của từng bài viết.
- **Thanh Lọc Ngôn Ngữ (Quick Filter Views)**: Bổ sung các tab lọc nhanh trên đầu bảng:
  - *All (Tất cả)* | *English (🇺🇸)* | *Chinese (🇨🇳)*...
  - Giúp biên tập viên dễ dàng quản lý riêng từng thị trường ngôn ngữ.

---

## 📄 3. Quản Lý Trang Tĩnh (`page`) Trên Visual Builder

Đối với các trang Landing Page hoặc trang tĩnh được thiết kế bằng **Align Visual Builder**:

1. **Chuyển Đổi Ngôn Ngữ Trực Tiếp (Builder Language Switcher)**:
   - Trên thanh công cụ trên cùng của Visual Builder có nút chuyển đổi ngôn ngữ (`EN`, `ZH`, v.v.).
   - Khi chuyển sang ngôn ngữ khác, hệ thống tự động kiểm tra xem trang con phân cấp tương ứng đã tồn tại hay chưa.
   - Nếu chưa, Builder cung cấp tùy chọn **Duplicate Canvas to [Language]** để sao chép toàn bộ layout sang trang mới.
2. **AI Canvas Translator**:
   - Visual Builder tích hợp module [`class-builder-translator.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/inc/builder/class-builder-translator.php).
   - Cho phép quét toàn bộ text nodes trên canvas và dịch tự động hàng loạt giữ nguyên 100% layout, classes và thuộc tính styling.

---

## 🧩 4. Đồng Bộ Ngôn Ngữ Cho Header, Footer & Reusable Blocks

Hệ thống [`Align_Template_Language`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/core/class-align-template-language.php) quản lý việc nạp template part phù hợp:
1. Khi người dùng truy cập trang tiếng Trung `/zh/...`:
2. Theme kiểm tra xem có bản dịch tương ứng của Header/Footer (`wp_template_part` hoặc `wp_block`) cho ngôn ngữ `zh` hay không.
3. Nếu có, theme nạp bản Header/Footer tiếng Trung.
4. Nếu chưa có, theme tự động fallback về Header/Footer ngôn ngữ mặc định (`en`) để giao diện không bao giờ bị vỡ.

---

## 📦 5. Import ZIP Đa Ngôn Ngữ (Multilingual ZIP Importer)

Hệ thống ZIP Importer tự động hỗ trợ dự án đa ngôn ngữ theo chuẩn kiến trúc Align:
1. **Root Lang Page**:
   - Thư mục `<lang>/index.html` (vd: `vi/index.html`) được nạp trực tiếp vào Root Lang Page của ngôn ngữ đó (`post_name = '<lang>'`, meta `_align_lang_root`, `_align_lang`, `_align_hide_from_nav`).
2. **Page Phân Cấp & Post Blog Ngôn Ngữ Phụ**:
   - Mọi page trong thư mục `<lang>/` được gán `post_parent` trỏ tới Root Lang Page và nhận meta `_align_lang = '<lang>'`.
   - Bài viết blog trong `<lang>/blog/*.html` nhận `_align_lang = '<lang>'` và permalink `/<lang>/<slug>/`.
   - **Lưu ý kỹ thuật (Quyết định D-3)**: Post nhập từ ZIP được đóng gói ở chế độ Align Builder (khác với quy chuẩn §2.2 dành cho post tạo tay trên Gutenberg). AI Translator tự động cho post loại này có thể bị hạn chế.
3. **Template Parts & Translation Groups**:
   - Template part `<lang>/header/` và `<lang>/footer/` tự động được đăng ký thành `header-<lang>` và `footer-<lang>`.
   - Tự động liên kết `_align_lang_group` và `_align_lang_parent` giữa các trang có cùng đường dẫn tương đối.
   - Tự động flush rewrite rules khi phát hiện ngôn ngữ mới hoặc bài viết ngôn ngữ phụ.
