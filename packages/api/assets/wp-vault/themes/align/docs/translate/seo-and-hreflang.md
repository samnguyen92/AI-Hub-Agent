# 🔍 Chuẩn SEO Đa Ngôn Ngữ & Thẻ Hreflang (SEO & Hreflang)

Tài liệu này đặc tả kiến trúc tối ưu hóa công cụ tìm kiếm (SEO) đa ngôn ngữ, cơ chế sinh thẻ `hreflang`, canonical URL và bộ chuyển đổi ngôn ngữ (Language Switcher) trong Theme Align.

---

## 🌐 1. Google Có Đánh Giá Trùng Lặp Nội Dung (Duplicate Content) Không?

> [!NOTE]
> **Câu Trả Lời Ngắn**: **KHÔNG BỊ PHẠT**, nếu website được cấu hình chuẩn SEO đa ngôn ngữ!

Theo **Nguyên tắc Quản trị Trang web của Google (Google Search Central Guidelines)**:
1. **Nội dung được dịch sang ngôn ngữ khác KHÔNG phải là nội dung trùng lặp (Duplicate Content)**:
   Google coi nội dung tiếng Anh và nội dung tiếng Trung là hai tài nguyên hoàn toàn khác nhau dành cho hai nhóm đối tượng độc giả khác nhau.
2. **Điều kiện để Google hiểu và xếp hạng tốt**:
   - URL phải tách biệt và rõ ràng (Align sử dụng `/` cho tiếng Anh và `/zh/` cho tiếng Trung).
   - Khai báo đầy đủ thẻ `<link rel="alternate" hreflang="..." />` liên kết 2 chiều giữa các phiên bản.
   - Thẻ `<link rel="canonical" href="..." />` phải trỏ đúng về chính phiên bản ngôn ngữ đó (Self-referencing canonical).

---

## 🏷️ 2. Cơ Chế Sinh Thẻ Hreflang Tự Động

Trong phần `<head>` của trang, Theme Align tự động quét nhóm dịch thuật `_align_lang_group` và xuất thẻ `hreflang` chuẩn xác:

```html
<!-- Khi người dùng đang ở trang Tiếng Trung: /zh/insights/tieu-de-zh/ -->
<link rel="canonical" href="https://example.com/zh/insights/tieu-de-zh/" />

<!-- Khai báo các phiên bản ngôn ngữ thay thế (Hreflang tags) -->
<link rel="alternate" hreflang="en" href="https://example.com/insights/tieu-de-en/" />
<link rel="alternate" hreflang="zh" href="https://example.com/zh/insights/tieu-de-zh/" />
<link rel="alternate" hreflang="x-default" href="https://example.com/insights/tieu-de-en/" />
```

### Giải Thích Ý Nghĩa Các Thẻ:
- **`hreflang="en"`**: Khai báo phiên bản tiếng Anh cho người dùng tìm kiếm bằng tiếng Anh.
- **`hreflang="zh"`**: Khai báo phiên bản tiếng Trung cho người dùng tìm kiếm bằng tiếng Trung.
- **`hreflang="x-default"`**: Phiên bản mặc định dự phòng dành cho người dùng ở các quốc gia khác hoặc ngôn ngữ trình duyệt chưa được hỗ trợ.
- **`canonical` độc lập (Self-Referencing)**: Trang tiếng Trung phải trỏ canonical về chính trang tiếng Trung. Tuyệt đối không trỏ canonical của trang tiếng Trung về trang tiếng Anh (vì sẽ làm Google không index trang tiếng Trung).

---

## 🔄 3. Bộ Chuyển Đổi Ngôn Ngữ Frontend (Language Switcher)

Hệ thống cung cấp sẵn class [`Align_Language_Switcher`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/core/class-align-language-switcher.php) để nhúng bộ chuyển đổi vào giao diện:

### 3.1. Sử Dụng Qua Shortcode
Có thể chèn bộ chuyển đổi ngôn ngữ ở bất kỳ đâu (Header, Footer, Sidebar, Gutenberg Block, Elementor):

```text
[align_language_switcher]
```

### 3.2. Tùy Chọn Thuộc Tính Shortcode
- `type`: Kiểu hiển thị (`dropdown` | `list` | `inline-flags`). Mặc định: `dropdown`.
- `show_flags`: Hiển thị emoji cờ quốc gia (`true` | `false`). Mặc định: `true`.
- `show_names`: Hiển thị tên ngôn ngữ (`true` | `false`). Mặc định: `true`.

Ví dụ:
```text
[align_language_switcher type="inline-flags" show_names="false"]
```

### 3.3. Thông Minh Khi Chuyển Trang (Smart Fallback Redirect)
Khi người dùng đang xem trang `/insights/article-10/` và bấm vào cờ Tiếng Trung:
1. Bộ chuyển đổi kiểm tra xem `article-10` đã có bản dịch tiếng Trung hay chưa qua `_align_lang_group`.
2. Nếu **đã có bản dịch**: Chuyển thẳng người dùng đến `/zh/insights/article-10-zh/`.
3. Nếu **chưa có bản dịch**: Chuyển hướng người dùng về trang chủ của ngôn ngữ đó (`/zh/`) kèm thông báo nhẹ nhàng, không bao giờ để người dùng gặp lỗi 404.

---

## 🗺️ 4. Tích Hợp XML Sitemap & Robots

1. **XML Sitemap**:
   - Tất cả các URL phân cấp (`/zh/...`) và bài viết (`/zh/insights/...`) đều tự động được nhận diện và liệt kê vào WordPress Core Sitemap (`/wp-sitemap.xml`) hoặc Yoast / RankMath / SEOPress.
2. **Robots Meta Tag**:
   - Mỗi bản dịch có thể tùy chỉnh độc lập thẻ `noindex, follow` hoặc `index, follow` tùy thuộc vào việc bản dịch đã hoàn thiện hay đang là bản nháp thử nghiệm.

---

## 5. Module SEO Mặc Định Tích Hợp Sẵn (`Align_Seo`)

Khi website không cài đặt plugin SEO bên thứ ba, module [`Align_Seo`](file:///Volumes/JoeSSD/SymLinks/Local_Sites_Home/align-stuff/app/public/wp-content/themes/align/core/includes/core/class-align-seo.php) (được nạp tại `align/core/includes/index.php`) đảm nhiệm toàn bộ đầu ra SEO cho mọi public post type (`get_post_types( array( 'public' => true ) )`, trừ `attachment`).

### 5.1. Cơ Chế Tự Động Nhường Quyền (Third-Party SEO Plugin Detection)
- `Align_Seo::is_third_party_seo_active()` kiểm tra sự tồn tại của hằng số `RANK_MATH_VERSION` (Rank Math) hoặc `WPSEO_VERSION` (Yoast SEO), có thể mở rộng qua filter `align_seo_third_party_active`.
- Nếu phát hiện Rank Math hoặc Yoast đang bật: `Align_Seo` **tự động tắt hoàn toàn** metabox quản trị và các hook xuất thẻ `<head>` (`pre_get_document_title`, `wp_head`, `wp_robots`) để tuyệt đối không sinh trùng thẻ `<title>`, `<meta name="description">`, `<link rel="canonical">` hoặc OpenGraph.
- Ngay cả khi Rank Math / Yoast đang bật, `Align_Seo::register_meta()` vẫn đăng ký 7 khóa post meta `_align_seo_*` vào REST API để dữ liệu không bị mất và sẵn sàng cho công cụ AI/head-check đọc.

### 5.2. Danh Sách 7 Khóa Post Meta Chuẩn (`_align_seo_*`)
Toàn bộ các khóa được đăng ký qua `register_post_meta` với `show_in_rest => true` và `auth_callback` yêu cầu `edit_post`:

| Post Meta Key | Kiểu | Chức Năng & Fallback Tự Động |
| :--- | :--- | :--- |
| `_align_seo_title` | `string` | Tiêu đề SEO (`pre_get_document_title`). Nếu trống: tự động dùng `Post Title — Site Name`. |
| `_align_seo_description` | `string` | Thẻ `<meta name="description">`. Nếu trống: trích 160 ký tự đầu của `post_excerpt` hoặc `wp_strip_all_tags( strip_shortcodes( post_content ) )`. |
| `_align_seo_focus_keyword` | `string` | Từ khóa trọng tâm phục vụ kiểm tra SEO và AI Agent (`save_seo_theme_default`). |
| `_align_seo_canonical` | `string` | URL chuẩn (`esc_url_raw`). Khi có giá trị: gỡ `rel_canonical` mặc định của WP Core ở `template_redirect` và in `<link rel="canonical">` riêng. |
| `_align_seo_robots` | `string` | Chỉ thị robots (`index,follow`, `noindex,follow`, `index,nofollow`, `noindex,nofollow`) điều phối qua filter `wp_robots` của WordPress Core. |
| `_align_seo_og_title` | `string` | Tiêu đề OpenGraph / Twitter Card (`og:title`, `twitter:title`). Fallback về `_align_seo_title` $\rightarrow$ `get_the_title()`. |
| `_align_seo_og_image` | `integer` | Attachment ID cho `og:image` & `twitter:image` (`absint`). Nếu bằng `0`: fallback về `get_post_thumbnail_id()`. |

### 5.3. Hooks Mở Rộng & Tích Hợp AI Agent (`Align-Agent`)
- **Filter `align_seo_fields`**: Cho phép theme con hoặc plugin mở rộng danh sách trường meta của `Align_Seo::get_meta_keys()`.
- **Action `align_seo_saved( int $post_id, array $values )`**: Phát tín hiệu sau khi lưu metabox hoặc gọi `Align_Seo::save_for_post( $post_id, $data )`.
- **Tích hợp `Align-Agent`**: Khi quản trị viên chọn nguồn SEO là **Align Theme Default SEO** (`theme_default`) trong tab SEO của plugin Align Agent, AI Chatbox chỉ đăng ký tool `save_seo_theme_default` (ủy quyền trực tiếp cho `Align_Seo::save_for_post()`) và tự động kiểm tra đầu ra thực tế bằng `check_seo_output`.

