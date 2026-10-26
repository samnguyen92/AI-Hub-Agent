# 🌐 Hệ Thống Đa Ngôn Ngữ & Dịch Thuật (Align Translation Subsystem)

Chào mừng bạn đến với tài liệu hướng dẫn kỹ thuật của **Phân Hệ Đa Ngôn Ngữ & Dịch Thuật (Translation & Multilingual Subsystem)** trong Theme Align.

---

## 📌 1. Triết Lý Thiết Kế (Core Philosophy)

Phân hệ dịch thuật của Align được thiết kế theo triết lý **Native-First & Zero-Bloat**:
1. **Hoàn toàn độc lập (Zero Heavy Plugins)**: Không phụ thuộc vào các plugin cồng kềnh như WPML, Polylang hay TranslatePress. Toàn bộ tính năng hoạt động trực tiếp trên lõi WordPress.
2. **Bảo tồn phân cấp WordPress nguyên bản (Native Page Hierarchy)**: 
   - Với Pages: Sử dụng cấu trúc phân cấp tự nhiên (`/zh/`, `/zh/about/`, `/zh/contact/`) thông qua Root Page ẩn.
   - Với Blog Posts: Sử dụng Rewrite Rules tinh gọn (`/zh/insights/%postname%/`) ánh xạ mượt mà vào post query.
3. **Liên kết nhóm dịch thuật (Translation Group Matrix)**: Mỗi bản dịch là một Post/Page độc lập trong database, được liên kết với nhau qua post meta `_align_lang_group`.
4. **Tích hợp sâu với Align Visual Builder & Gutenberg**: Hỗ trợ chuyển đổi ngôn ngữ trực tiếp trong Visual Builder, 1-Click dịch thuật bằng AI, và Sidebar chuyên dụng trong Gutenberg.
5. **SEO Chuẩn Quốc Tế**: Tự động sinh thẻ `<link rel="alternate" hreflang="..." href="...">` và canonical URL độc lập cho từng ngôn ngữ, ngăn chặn triệt để lỗi trùng lặp nội dung (duplicate content).

---

## 🗺️ 2. Danh Mục Tài Liệu Chi Tiết

Tất cả tài liệu trong thư mục này được phân tách theo từng khía cạnh kỹ thuật:

```text
align/docs/translate/
├── README.md                           <--- (Tài liệu này) Tổng quan phân hệ & Bản đồ tra cứu
├── architecture-and-routing.md         <--- Kiến trúc URL, Rewrite Rules, Root Page & Routing
├── post-and-page-management.md         <--- Quản lý bài viết, trang tĩnh, Gutenberg & Builder
├── translation-matrix.md               <--- Translation Matrix Dashboard & Ma trận quản lý
├── bulk-ai-translator.md               <--- Dịch hàng loạt & sinh AI SEO Rank Math tự động
└── seo-and-hreflang.md                 <--- Chuẩn SEO đa ngôn ngữ, Hreflang, Canonical & Switcher
```

| Tài liệu | Nội dung tóm tắt |
| :--- | :--- |
| [**`architecture-and-routing.md`**](./architecture-and-routing.md) | Phân tích cơ chế URL routing, Rewrite Rules cho bài viết (`/zh/insights/...`), cơ chế Root Page cho trang tĩnh (`/zh/...`), và các lưu ý then chốt khi triển khai Staging/Production (Flush Permalinks). |
| [**`post-and-page-management.md`**](./post-and-page-management.md) | Cơ chế lưu trữ Post Meta (`_align_lang`, `_align_lang_group`), bảng điều khiển Language Sidebar trên Gutenberg, cột lọc ngôn ngữ trên WP Admin, và tích hợp Align Visual Builder. |
| [**`translation-matrix.md`**](./translation-matrix.md) | Hướng dẫn sử dụng và cấu trúc kỹ thuật của bảng điều khiển **Translation Matrix Dashboard** trên trang Align Settings; quản lý tập trung trạng thái dịch của toàn bộ website. |
| [**`bulk-ai-translator.md`**](./bulk-ai-translator.md) | **Dịch Hàng Loạt & Tạo AI SEO Bằng AI**: Quy trình tuần tự 4 bước kháng timeout, dịch blocks bảo toàn style và tự động trích xuất SEO Metadata (Rank Math) cho toàn bộ trang/bài viết. |
| [**`seo-and-hreflang.md`**](./seo-and-hreflang.md) | Kiến trúc SEO đa ngôn ngữ: Thẻ `hreflang`, canonical URL, sitemap XML, shortcode bộ chuyển đổi ngôn ngữ `[align_language_switcher]` và kỹ thuật tránh phạt Duplicate Content của Google. |

---

## 🗂️ 3. Bản Đồ Mã Nguồn (Source Code File Map)

Toàn bộ logic của phân hệ đa ngôn ngữ được đặt tại các file sau:

### Backend Core:
- [`align/core/includes/core/class-align-language-manager.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/core/class-align-language-manager.php): Khởi tạo ngôn ngữ, CRUD cấu hình ngôn ngữ (`align_languages` option), Root Page generator, lấy danh sách bản dịch.
- [`align/core/includes/core/class-align-post-language-meta.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/core/class-align-post-language-meta.php): Quản lý ngôn ngữ bài viết Gutenberg (`post`), Rewrite Rules (`register_post_lang_rewrites`), lọc query (`pre_get_posts`), meta box sidebar.
- [`align/core/includes/core/class-align-template-language.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/core/class-align-template-language.php): Tích hợp ngôn ngữ cho Template Part (Header, Footer, Reusable Blocks).
- [`align/core/includes/core/class-align-language-switcher.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/core/class-align-language-switcher.php): Widget / Shortcode bộ chọn ngôn ngữ frontend (`[align_language_switcher]`).

### Admin UI & Settings:
- [`align/core/includes/admin/views/tab-languages.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/views/tab-languages.php): Tab cấu hình ngôn ngữ trong Align Settings (Thêm/xóa ngôn ngữ, đặt cờ, slug, mặc định).
- [`align/core/includes/admin/views/tab-translation-matrix.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/views/tab-translation-matrix.php): Giao diện Ma trận Dịch thuật trực quan (Translation Matrix).
- [`align/core/includes/admin/class-align-translation-matrix.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/class-align-translation-matrix.php): Controller xử lý dữ liệu ma trận dịch thuật.
- [`align/core/includes/admin/class-align-post-ai-seo.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/includes/admin/class-align-post-ai-seo.php): Tự động dịch SEO Title & Description bằng AI cho từng ngôn ngữ.

### Visual Builder Integration & Translation Engine:
- [`align/core/inc/builder/class-builder-translator.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/inc/builder/class-builder-translator.php): Engine xử lý dịch nội dung canvas và post blocks qua AI LLM batching.
- [`align/core/inc/builder/class-builder-prompt-helper.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/inc/builder/class-builder-prompt-helper.php): Quản lý các chỉ thị script (Hong Kong Traditional, Taiwan Traditional, Simplified), bộ từ điển chuyên ngành Corporate & Insurance chuẩn Hong Kong (MPF, 僱員補償, 索償, 質素...), và cơ chế gom prompt đa tầng (Language Config + Runtime Overrides).
- [`align/core/inc/builder/class-builder-seo-handler.php`](file:///Users/sam92/Local%20Sites/trusted-union/app/public/wp-content/themes/align/core/inc/builder/class-builder-seo-handler.php): Quản lý SEO Meta per-language trong Visual Builder.
