# Hệ Thống Nhật Ký Lỗi & Chống Tái Diễn (Bug Logs & Anti-Regression Memory)

Tài liệu này lưu trữ toàn bộ các lỗi hệ thống đã được xác minh nguyên nhân gốc rễ (Root Cause) và đã xử lý dứt điểm. Mọi AI Agent khi hoạt động trong codebase BẮT BUỘC phải đọc tài liệu này để không lặp lại các lỗi tương tự.

---

### [BUG-20261022-1530] — HTML ZIP Importer Media Attachment Overwrite, Missing Srcset/Data-* Rewrite & Reading Settings Wipe (M-1 -> M-5, R-1 -> R-3)
- **Triệu chứng (Symptom):** Khi tải lên file ZIP lớn chứa 498 media files, hệ thống chỉ tạo được 184 attachments (314 file bị xóa mất do trùng tên không đuôi, vd `01.webp` bị ghi đè chéo giữa các case study, `gia-capital.svg` xóa `gia-capital.webp`). Nhiều ảnh trả về lỗi HTTP 404 trên console do `srcset`, `data-cursor-img` và `url()` không dấu nháy không được viết lại. Trang `Settings > Reading` bị reset về "Your latest posts" và không phục hồi trang chủ khi thiếu file home hoặc khi chạy `overwrite = false`.
- **Nguyên nhân gốc (Root Cause):**
  1. `delete_existing_attachment_by_name()` xóa attachment theo tên file không thư mục, không đuôi (`name = sanitize_title(stem)`), khiến 498 file chỉ còn 184 tên duy nhất (M-1).
  2. `rewrite_html_assets()` chỉ xử lý `src`, `data-src`, `data-bg-src`, `poster`, `href`; hoàn toàn bỏ qua `srcset`, `data-cursor-img` và các thuộc tính `data-*` media (M-2).
  3. Regex `url()` trong `rewrite_css_urls()` yêu cầu bắt buộc có dấu nháy (`\1`), thất bại với cú pháp `url(/assets/...)` không dấu nháy (M-3).
  4. Template parts ngôn ngữ phụ nhân bản (`header-vi.html`) sao chép HTML gốc nhưng chỉ rewrite link nội bộ mà quên rewrite media (M-4).
  5. Quét `url_mapping` bằng vòng lặp tuyến tính và khớp theo tên không đuôi gây chậm và gán nhầm file (M-5).
  6. `execute_full_site_reset()` xóa `page_on_front` và đặt `show_on_front = posts`; việc gán lại trang chủ nằm rải rác trong `assemble_page()` khiến Reading bị hỏng nếu ZIP không có trang chủ hoặc khi chạy không reset (R-1, R-2, R-3).
- **Cách đã xử lý (Fix Applied):**
  1. Tạo mới `class-align-zip-media-index.php` (`Align_Zip_Media_Index`):
     - Định danh media theo đường dẫn tương đối trong ZIP (`rel_path`) và lưu meta `_align_zip_rel`.
     - Xóa bỏ hoàn toàn hàm `delete_existing_attachment_by_name()`.
     - Tra cứu media O(1) qua bảng băm trực tiếp `url_mapping` và `unique_basenames`.
     - Viết lại toàn diện `srcset`, `data-srcset`, `imagesrcset` (giữ nguyên descriptor độ phân giải `480w`, `720w`, loại bỏ query `?v=`) và các thuộc tính `data-*` media qua `rewrite_extra_attributes()`.
     - Nâng cấp regex `url()` hỗ trợ cả dạng không nháy, nháy đơn, nháy kép.
     - Thêm phương thức `apply_front_page()` tập trung quản trị cài đặt Reading an toàn tại `finalize_session()`.
  2. Sửa `class-align-zip-media-rewriter.php`: Giảm từ 497 xuống 376 dòng, chuyển giao toàn bộ logic chỉ mục và giải quyết đường dẫn sang `Align_Zip_Media_Index`.
  3. Sửa `class-align-zip-page-assembler.php`: Gỡ bỏ việc gán `update_option( 'page_on_front' )` rải rác.
  4. Sửa `class-align-zip-lang-resolver.php`: Thêm bước rewrite media cho template parts nhân bản trước khi rewrite link.
  5. Sửa `class-align-zip-batch-runner.php` và `class-align-zip-importer.php`: Gọi `apply_front_page()` ở cuối phiên import.
- **Quy tắc cho AI sau (Rule for Future AI):**
  - **NÊN:** Luôn định danh tệp media import theo đường dẫn tương đối (`rel_path`) kèm meta `_align_zip_rel`. Mọi thuộc tính `srcset`, `data-*` mang URL media và inline CSS `url()` phải được viết lại đầy đủ. Cài đặt Reading `page_on_front` chỉ được chốt tập trung ở bước `finalize_session`.
  - **KHÔNG NÊN:** Tuyệt đối không xóa attachment dựa theo tên file không đuôi `sanitize_title(stem)` trong WordPress. Không đặt `page_on_front` rải rác trong vòng lặp tạo trang.

---

### [BUG-20261022-1400] — Multilingual Link Cross-contamination, Post Deletion Across Languages & Unpaired Translations (A-1 -> A-7, C-1 -> C-4)
- **Triệu chứng (Symptom):** Link menu trong header tiếng Anh (`/about`) bị nhảy sang URL tiếng Việt `/vi/about/`. Bài viết blog tiếng Anh trùng slug với bài viết tiếng Việt (`what-is-mascot`) bị xóa mất khi import `overwrite = true`. 0/61 bài viết được ghép nhóm bản dịch do khác slug. Tiêu đề Root Page tiếng Việt hiển thị "Vietnamese Root". Frontend thiếu `<html lang="vi">`, in lặp thẻ hreflang và sai `og:locale`. Màn hình quản trị Pages thiếu cột và tab lọc ngôn ngữ.
- **Nguyên nhân gốc (Root Cause):**
  1. `slug_to_id_map` toàn cục không phân biệt ngôn ngữ, khiến fallback của link `/about` trong trang tiếng Anh trỏ nhầm sang ID trang tiếng Việt (A-1).
  2. `assemble_lang_root()` không truyền `post_title` khi cập nhật Root Page (A-2).
  3. Trang chủ tiếng Anh (`path_key = 'home'`) và Root Page tiếng Việt (`path_key = 'vi'`) không đồng nhất khóa, dẫn đến không ghép được nhóm dịch (A-3).
  4. `assemble_post()` tìm bài viết trùng tên không lọc theo ngôn ngữ, xóa nhầm bài viết tiếng Anh khi import bài tiếng Việt cùng slug (A-4).
  5. Khác slug giữa 2 ngôn ngữ khiến việc ghép cặp bài viết theo đường dẫn thuần túy thất bại 100% (A-5, A-6).
  6. Template parts tiếng Việt kế thừa link sai từ bản tiếng Anh đã rewrite (A-7).
  7. Core `Align_Hreflang` và `Align_Language_URL_Localizer` in trùng hreflang, thiếu hỗ trợ `<html lang="vi">` và `og:locale` cho tiếng Việt (C-1 -> C-4).
- **Cách đã xử lý (Fix Applied):**
  1. Tạo mới `class-align-zip-link-resolver.php`: Quản lý bản đồ liên kết 2 tầng `link_map[lang][key]`, cô lập hoàn toàn theo ngữ cảnh ngôn ngữ, ngăn chặn nhảy link chéo.
  2. Chuẩn hóa tiêu đề Root Page `vi` thành `"Home"` và chuẩn hóa khóa trang chủ về `''` để ghép nhóm `_align_lang_group`.
  3. Cập nhật `class-align-zip-post-assembler.php`: Chỉ kiểm tra trùng và xóa bài viết trong cùng một ngôn ngữ (`_align_lang`); slug trùng với ngôn ngữ khác tự động gắn hậu tố `-<lang>`.
  4. Tạo mới `class-align-zip-pairing.php`: Ghép nhóm bài viết song ngữ qua DOMDocument trích xuất slug tiếng Anh từ ảnh hero banner (`/assets/img/blog/<slug>/hero...`), alias dựng sẵn và manifest.
  5. Cập nhật `class-align-zip-lang-resolver.php`: Tự động nhân bản template parts `header-<lang>.html` và `footer-<lang>.html` từ bản gốc và rewrite link với ID ngôn ngữ phụ.
  6. Sửa `class-align-hreflang.php`: Thêm phát hiện `<html lang="vi">`, in đúng 1 bộ 3 thẻ hreflang, chuẩn hóa `og:locale = vi_VN`.
  7. Sửa `class-align-language-url-localizer.php`: Gỡ bỏ hook in hreflang trùng lặp.
  8. Tạo mới `class-align-page-language-admin.php`: Thêm cột Languages và filter tabs cho `post_type = page`.
- **Quy tắc cho AI sau (Rule for Future AI):**
  - **NÊN:** Luôn cô lập bản đồ liên kết theo từng ngôn ngữ `link_map[lang][key]`. Tìm kiếm bài viết trùng và xóa đè phải luôn có điều kiện `meta_query` theo `_align_lang`. Ghép cặp bài viết khác slug qua dữ liệu ảnh hero hoặc manifest.
  - **KHÔNG NÊN:** Không bao giờ fallback link sang ngôn ngữ khác trong bộ rewrite link nội bộ. Không in thẻ hreflang ở nhiều class khác nhau trong core.

---

### [BUG-20261022-1300] — HTML ZIP Importer Multilingual Folders Ignored & Created as Regular "Vi" Page (RC-11 -> RC-14)
- **Triệu chứng (Symptom):** Khi tải lên file ZIP chứa thư mục ngôn ngữ (vd: `vi/`), hệ thống tạo ra một page thường tên "Vi", các trang con (`vi/services/`) trở thành con của page này thay vì liên kết với hệ sinh thái đa ngôn ngữ Align, `vi/blog/*.html` biến thành page thay vì post, và `vi/header/` bị coi là page.
- **Nguyên nhân gốc (Root Cause):**
  1. Scanner/Page Tree không có khái niệm thư mục ngôn ngữ. Thư mục `vi/` bị coi là page thường (RC-11).
  2. `BLOG_DIRS` chỉ nhận diện ở cấp gốc ZIP, khiến `vi/blog/` rơi vào luồng page thường (RC-12).
  3. Header/footer chỉ nhận diện ở cấp gốc. `vi/header/` thành page (RC-13).
  4. Importer không gán `_align_lang`, `_align_lang_group` và không sử dụng Root Lang Page (RC-14).
- **Cách đã xử lý (Fix Applied):**
  1. Tạo mới `class-align-zip-lang-resolver.php` (`Align_Zip_Lang_Resolver`): Chứa whitelist `KNOWN_LANGS`, nhận diện thư mục ngôn ngữ cấp gốc (`detect_lang_dirs`), unwrap ngôn ngữ mặc định (REQ-20), quét chuyên biệt thư mục ngôn ngữ (`scan_language_folder`), tự động thêm ngôn ngữ qua `Align_Language_Manager::add_language()` (D-5 = A), sửa chữa meta Root Lang Page cũ (`repair_root_page_meta`), lắp ráp Root Lang Page từ `vi/index.html` (`assemble_lang_root`), gắn thẻ `_align_lang` (`tag_entity`), ghép nhóm bản dịch (`link_translation_groups`), và flush rewrite rules 1 lần (`finalize_multilingual`).
  2. Sửa `class-align-zip-scanner.php`: Tích hợp nhận diện ngôn ngữ cấp gốc, loại trừ khỏi luồng quét trang gốc, quét sub-root cho từng thư mục ngôn ngữ và tích hợp template part `header-<lang>` / `footer-<lang>`.
  3. Sửa `class-align-zip-post-assembler.php`: Thêm tham số `$lang = ''`, tự động gán suffix `<slug>-<lang>` khi trùng slug post giữa các ngôn ngữ.
  4. Sửa `class-align-zip-page-assembler.php`: Nhận diện template part area cho slug dạng `header-*` và `footer-*`.
  5. Sửa `class-align-zip-batch-runner.php`: Đảm bảo ngôn ngữ và seed `path_to_id_map` trong `reset_and_register_assets()`, gọi `assemble_lang_root()` cho entry `lang_root`, tag `_align_lang`, và gọi `finalize_multilingual()` trước khi rewrite link.
  6. Cập nhật `class-align-zip-sample-generator.php`, UI `tab-import-zip.php` và tài liệu kỹ thuật liên quan.
- **Quy tắc cho AI sau (Rule for Future AI):**
  - **NÊN:** Mọi thư mục ngôn ngữ cấp gốc (`vi/`, `zh/`...) phải được phân giải qua `Align_Zip_Lang_Resolver`. Root Page phải tạo qua `ensure_root_lang_page()` và có đủ meta `_align_lang_root`, `_align_lang`, `_align_hide_from_nav`. Mọi thực thể con phải có `post_parent` trỏ về Root Lang Page và có `_align_lang`. Ghép nhóm bản dịch phải dùng format `grp-` + 12 hex md5.
  - **KHÔNG NÊN:** Không bao giờ tạo page thường cho thư mục ngôn ngữ cấp gốc. Không flush rewrite rules trong vòng lặp batch, chỉ flush 1 lần duy nhất tại bước finalize.

---

### [BUG-20261022-1200] — HTML ZIP Importer Blog Posts Mistakenly Created as Child Pages of "blog"
- **Triệu chứng (Symptom):** Khi tải lên file ZIP chứa thư mục blog (vd: `blog/hello-world.html`), hệ thống tạo ra `page` thay vì `post`, có `post_parent` trỏ tới page `blog`, và không kích hoạt Align Builder mode.
- **Nguyên nhân gốc (Root Cause):**
  1. `collect_pages()` coi mọi thư mục không bị loại trừ là cây page; `$exclude_page_dirs` thiếu thư mục blog (RC-8).
  2. `assemble_page()` gán cứng `post_type = 'page'`, không có luồng nào cho `post` (RC-9).
  3. Toàn bộ importer không có module nào tạo `post`; `'post'` chỉ xuất hiện trong danh sách xóa của reset (RC-10).
- **Cách đã xử lý (Fix Applied):**
  1. Thêm `Align_Zip_Page_Tree::BLOG_DIRS` khai báo các tên thư mục blog cấp gốc.
  2. Thêm hàm `Align_Zip_Page_Tree::collect_posts()`: Quét các thư mục blog cấp gốc, bóc tách entry `entity = 'post'`, riêng `blog/index.html` trả về `entity = 'page'`.
  3. Cập nhật `Align_Zip_Scanner::scan_directory()`: Tách thư mục blog cấp gốc khỏi luồng page thường và merge kết quả `collect_posts()`.
  4. Cập nhật `Align_Zip_Page_Tree::normalize_pages()`: Bỏ qua entry `entity = 'post'` khi sinh placeholder draft; sort `page` trước, `post` sau.
  5. Tạo mới `class-align-zip-post-assembler.php`: Xử lý `assemble_post()` với `post_type = 'post'`, `post_status = 'publish'`, `post_parent = 0`, bóc tách tiêu đề/ngày đăng qua `DOMDocument`, chống đụng độ slug page/post (`<slug>-post`), đóng gói `align/custom-html`, và khóa mode `builder` qua `Align_Editor_Mode_Lock::set_mode_and_lock()`.
  6. Sửa `class-align-zip-page-assembler.php`: Chuyển `prepare_component_payload()` và `build_custom_html_block()` sang `public static` để tái dùng mã nguồn.
  7. Sửa `class-align-zip-batch-runner.php` và `class-align-zip-importer.php`: Gọi `assemble_post()` khi entry là `post`.
  8. Cập nhật sample generator, UI tab-import-zip.php, và tài liệu kỹ thuật liên quan.
- **Quy tắc cho AI sau (Rule for Future AI):**
  - **NÊN:** Các thư mục blog cấp gốc (`blog/`, `news/`, `tin-tuc/`...) chỉ tạo duy nhất 1 Page `blog` tĩnh nếu có `index.html`. Toàn bộ các file HTML khác trong thư mục blog phải tạo bài viết WordPress (`post_type = 'post'`) với mode Align Builder và không có `post_parent`.
  - **KHÔNG NÊN:** Không bao giờ gán `post_parent` cho `post` là page `blog`, và không bao giờ đổi `page_for_posts` hoặc `show_on_front` khi import file ZIP blog.

---

### [BUG-20261022-1130] — HTML ZIP Importer Flattening Sub-folders & Missing Parent-Child Page Hierarchy
- **Triệu chứng (Symptom):** Khi tải lên file ZIP có cấu trúc lồng nhau (vd: `services/index.html` và `services/web-design/index.html`), importer làm phẳng toàn bộ thành trang cấp gốc, `services/index.html` bị đè thành `home-2`, không gán `post_parent`, và các đường dẫn tương đối `<a href="web-design/">` bị rewrite sai thành `/serviceswebdesign/`.
- **Nguyên nhân gốc (Root Cause):**
  1. `class-align-zip-scanner.php` chỉ duyệt thư mục cấp 1, `inspect_page_folder()` chỉ lưu 1 file HTML duy nhất cho mỗi folder (RC-1).
  2. Catalog dùng `slug` đơn lẻ làm key, dẫn đến các file trùng tên ở 2 thư mục khác nhau bị đè nhau (RC-2).
  3. Mọi file `index` đều bị đổi thành `home` bất kể nằm ở thư mục con hay thư mục gốc (RC-3).
  4. `wp_insert_post()` trong `class-align-zip-page-assembler.php` không truyền `post_parent` (RC-4).
  5. Check trùng slug không xét parent (`get_posts name=`), khiến các trang con cùng tên ở nhánh khác bị suffix không cần thiết (RC-5).
  6. `rewrite_internal_links()` bỏ toàn bộ dấu gạch chéo rồi `sanitize_title` chuỗi đường dẫn, biến `services/web.html` thành `servicesweb` (RC-6).
  7. Catalog chỉ lưu `slug_to_id_map`, thiếu `path_to_id_map` để tra cứu theo cấp bậc cây trang (RC-7).
- **Cách đã xử lý (Fix Applied):**
  1. Tạo `class-align-zip-page-tree.php` (`Align_Zip_Page_Tree`) chứa toàn bộ logic quét đệ quy (`collect_pages()`), chuẩn hóa (`normalize_pages()`), tự động sinh trang cha draft placeholder (D-1), xử lý thư mục nhiều HTML thành page con (D-2), tra cứu parent ID (`resolve_parent_id()`), check trùng theo parent (`find_existing_page()`), và phân giải URL tương đối (`resolve_href_to_key()`).
  2. Sửa `class-align-zip-scanner.php`: Chuyển logic quét trang sang `Align_Zip_Page_Tree::collect_pages()`.
  3. Sửa `class-align-zip-batch-runner.php`: Chuẩn hóa danh sách trang bằng `normalize_pages()`, lưu song song `path_to_id_map` và `slug_to_id_map`, truyền parent ID khi assemble trang.
  4. Sửa `class-align-zip-page-assembler.php`: Nhận `$parent_id`, truyền `post_parent` vào `wp_insert_post()`, lưu `_align_zip_placeholder = 1` nếu là placeholder draft.
  5. Sửa `class-align-zip-media-rewriter.php`: Thêm tham số `$source_path_key` vào `rewrite_internal_links()`, tra cứu `path_to_id_map` trước khi fallback về slug, bảo toàn `data-align-link-id`.
  6. Cập nhật `class-align-zip-sample-generator.php` và `tab-import-zip.php` với sơ đồ cây trang phân cấp.
  7. Tối ưu mã nguồn đảm bảo toàn bộ file trong `align/core/includes/admin/importer/*.php` đều `< 500` dòng.
- **Quy tắc cho AI sau (Rule for Future AI):**
  - **NÊN:** Khi quét thư mục ZIP hoặc xử lý cây trang, luôn duy trì và sử dụng `path_key` (phân cách bằng `/`) làm khóa định danh duy nhất; sắp xếp các trang theo `depth` tăng dần để trang cha luôn được tạo trước trang con; gán `post_parent` chính xác.
  - **KHÔNG NÊN:** Không bao giờ làm phẳng (flatten) đường dẫn của trang con thành slug đơn lẻ trong catalog hoặc rewrite link bằng cách xóa bỏ cấu trúc thư mục.

---

### [BUG-20261020-0915] — File-First Asset Deletion Bug (Mất Toàn Bộ CSS/JS Khi Lưu Trang Từ Gutenberg Hoặc Agent Patch)
- **Triệu chứng (Symptom):** Khi lưu bài viết/trang từ Gutenberg hoặc qua công cụ patch/restore của Align Agent, các tệp vật lý `assets/builder/style-*.css` và `script-*.js` bị tự động xóa trắng khỏi đĩa cứng (`Empty CSS, removed file`). Trang mất hoàn toàn giao diện CSS (ví dụ trang 52 `Gói Subscription` bị mất 8162 bytes CSS).
- **Nguyên nhân gốc (Root Cause):**
  1. Theo kiến trúc File-First Single Source of Truth (SOT), `post_content` chỉ lưu mã HTML thuần, không chứa thẻ `<style id="...">` / `<script id="...">`.
  2. Hàm `Align_Builder_Persistence_Service::sync_post_assets_from_content()` (hook vào `save_post`) đọc inline CSS/JS từ `post_content`. Do không có inline tag, `$css` và `$js` nhận giá trị chuỗi rỗng `''`.
  3. Hàm gọi tiếp `Align_Builder_Assets::save_external_assets()` với chuỗi rỗng. Hàm `save_single_asset()` coi chuỗi rỗng là ý định xóa tệp và gọi `unlink()`.
  4. Các luồng cập nhật HTML-only từ Align Agent (`class-agent-tools-pages.php:479`, `class-gateway-pages.php:414`, `class-align-ai-page-controller.php:293`) cũng truyền chuỗi rỗng xuống tầng lưu trữ, gây xóa tệp hàng loạt.
- **Cách đã xử lý (Fix Applied):**
  1. Tạo `Align_Builder_Asset_History` (`class-builder-asset-history.php`): Tự động sao lưu snapshot vào `assets/builder/.history/` trước mọi thao tác ghi đè hoặc xóa; giới hạn lưu 5 bản mới nhất (prune); hỗ trợ khôi phục qua `restore()`.
  2. Phân tách API ghi tệp thành Destructive và Non-Destructive trong `Align_Builder_Asset_Writer` (`class-builder-asset-writer.php`):
     - `save_external_assets()`: Giữ nguyên cơ chế xóa tệp khi có chỉ định xóa rõ ràng từ Builder Save.
     - `sync_external_assets()`: Thêm cờ `$allow_delete = false`, nghiêm cấm xóa tệp khi chuỗi rỗng, ghi log `[Align Assets] Skip delete (non-destructive sync)`.
  3. Chuyển đổi `sync_post_assets_from_content()` và `sync_template_assets_from_content()` sang `Align_Builder_Asset_Sync` (`class-builder-asset-sync.php`): Return sớm khi nội dung không có inline style/script (`File-First, no inline assets`), chuyển sang gọi `sync_external_assets()`.
  4. Cập nhật các luồng gọi trong Align Agent (`class-agent-tools-pages.php`, `class-gateway-pages.php`, `class-align-ai-page-controller.php`) để không xóa tệp khi cập nhật HTML-only.
  5. Bổ sung công cụ Align Agent `restore_asset_history` và đăng ký vào manifest loader.
  6. Khôi phục nguyên vẹn 8162 bytes CSS cho trang 52 `style-page-goi-subscription.css` từ nhật ký audit log.
- **Quy tắc cho AI sau (Rule for Future AI):**
  - **NÊN:** Mọi thao tác đồng bộ từ nội dung (`sync_*_from_content`) hoặc các endpoint cập nhật nội dung từng phần (partial HTML update) BẮT BUỘC phải dùng `Align_Builder_Assets::sync_external_assets()` hoặc kiểm tra nạp CSS/JS hiện hữu từ đĩa cứng trước khi lưu.
  - **KHÔNG NÊN:** Tuyệt đối không bao giờ gọi `save_external_assets()` với chuỗi rỗng `''` trừ khi người dùng chủ động xóa sạch CSS/JS từ giao diện Builder.

---

### [BUG-20261018-2140] — Lỗi HTTP 500 `rest_meta_database_error` Không Thể Cập Nhật `_align_editor_locked` Khi Lưu Bài Viết / CPT Qua Gutenberg REST API
- **Triệu chứng (Symptom):** Khi bấm Save/Update trên trang (`page`), bài viết (`post`) hoặc Custom Post Type (CPT, ví dụ `resource`) trong Gutenberg / Embedded Align Builder, REST request `POST /wp-json/wp/v2/<rest_base>/<id>` trả về lỗi HTTP 500 `rest_meta_database_error`: *"Could not update the meta value of _align_editor_locked in database."*
- **Nguyên nhân gốc (Root Cause):**
  1. `align/core/includes/admin/class-align-editor-mode-lock.php:33` — Hook `on_save_post` được gắn vào `save_post` (chạy bên trong `wp_insert_post()` trước khi `WP_REST_Posts_Controller::update_item()` gọi `handle_meta_data()`). Ngay trong `on_save_post`, hệ thống đã ghi `_align_editor_locked = '1'`. Khi `WP_REST_Posts_Controller` xử lý tiếp payload `meta` từ client gửi kèm `_align_editor_locked: '0'` (giá trị cũ trong store), `update_metadata()` bị `filter_protect_locked_mode_meta` chặn và trả về `false`, khiến WordPress REST API coi là lỗi ghi database (`rest_meta_database_error`).
  2. `class-align-editor-mode-lock.php:312` — `filter_protect_locked_mode_meta` trả về `false` cho mọi lệnh `update_post_metadata` trên `_align_editor_locked` và `_align_editor_mode` ngay cả khi giá trị mới gửi lên trùng khớp với giá trị đang lưu trong DB (no-op write).
  3. `align/core/builder/gutenberg/align-editor-mode-switch.js:161` & `align/core/builder/gutenberg/align-save-coordinator.js:176` — Client gọi `editPost({ meta })` mà chưa loại bỏ khóa chỉ-đọc `_align_editor_locked` khỏi payload `meta` của store `core/editor`.
- **Cách đã xử lý (Fix Applied):**
  1. Trong `class-align-editor-mode-lock.php`: Chuyển hook khóa tự động từ `save_post` sang `wp_after_insert_post` (priority 25) để chạy sau khi `WP_REST_Posts_Controller` đã hoàn tất `handle_meta_data()`.
  2. Trong `filter_protect_locked_mode_meta` (`class-align-editor-mode-lock.php:312-323`): Cho phép các lệnh ghi idempotent (khi `(string) $meta_value === $current_lock` hoặc `(string) $meta_value === $current_mode`) đi qua để `update_metadata()` trả về no-op hợp lệ thay vì `false`.
  3. Trong `align-editor-mode-switch.js` và `align-save-coordinator.js`: Luôn clone `currentMeta` và `delete nextMeta._align_editor_locked` trước khi gọi `editPost({ meta: nextMeta })`.
- **Quy tắc cho AI sau (Rule for Future AI):**
  - **NÊN:** Khi tự động ghi hoặc khóa post meta trong quá trình lưu bài viết, luôn hook vào `wp_after_insert_post` thay vì `save_post` để không xung đột với `WP_REST_Posts_Controller::handle_meta_data()`; đồng thời trong các filter `update_{$meta_type}_metadata` bảo vệ meta, luôn cho phép giá trị trùng khớp hiện tại (idempotent no-op) đi qua.
  - **KHÔNG NÊN:** Tuyệt đối không đẩy các meta chỉ-đọc do server quản lý (`_align_editor_locked`) vào `editPost({ meta })` ở phía client JS.

---

### [BUG-20261018-1955] — Editor Mode Lock Bypass & Cross-Editor Content Corruption (P1-P8)
- **Triệu chứng (Symptom):** Người dùng chuyển đổi tự do giữa Align Builder và Gutenberg trên bài đã lưu nội dung khiến cấu trúc block bị hỏng; tham số `?align_mode=`, `sessionStorage`, hoặc REST API meta có thể ghi đè `_align_editor_mode` trái phép; Custom Post Types (CPTs) không giữ được trạng thái editor mode.
- **Nguyên nhân gốc (Root Cause):** `align/core/builder/gutenberg/align-site-editor-integration.js:211-242` và `align/core/includes/admin/class-align-page-settings.php:84-110` — Thiếu cơ chế khóa editor mode (`_align_editor_locked`) sau lần lưu đầu tiên có nội dung, ưu tiên `?align_mode=` / `sessionStorage` trước post meta mà không kiểm tra trạng thái khóa, không bảo vệ `_align_editor_mode` / `_align_editor_locked` qua metadata filters, và giới hạn đăng ký/chuyển đổi mode cứng ở `post`/`page`.
- **Cách đã xử lý (Fix Applied):** Thêm `Align_Editor_Mode_Lock` (`align/core/includes/admin/class-align-editor-mode-lock.php`) và `align/core/builder/gutenberg/align-editor-mode-switch.js` để tự động khóa mode sau lần lưu đầu có nội dung (hoặc lazy-lock bài cũ qua `parse_blocks()`), chặn sửa/thêm/xóa trực tiếp `_align_editor_mode` và `_align_editor_locked` qua `update/add/delete_post_metadata`, khóa nút switch khi đang save (`AlignSaveCoordinator.isBusy()`), và cung cấp endpoint `POST /wp-json/align-builder/v1/switch-editor-mode` xóa sạch nội dung cũ kèm lưu revision backup.
- **Quy tắc cho AI sau (Rule for Future AI):**
  - **NÊN:** Luôn kiểm tra `Align_Editor_Mode_Lock::is_locked( $post_id )` (PHP) và `AlignEditorModeSwitch.isPostLocked()` (JS) trước khi cho phép đổi chế độ soạn thảo; mọi thao tác đổi editor trên bài đã khóa bắt buộc đi qua `POST /wp-json/align-builder/v1/switch-editor-mode` hoặc `Align_Editor_Mode_Lock::set_mode_and_lock()`.
  - **KHÔNG NÊN:** Tuyệt đối không dùng `?align_mode=`, `sessionStorage`, hoặc gọi trực tiếp `update_post_meta` / `delete_post_meta` lên `_align_editor_mode` và `_align_editor_locked` để vượt khóa trên bài viết đã có nội dung.

---

### [2026-10-07] - Lỗi Tự Động Revert Template Database Khi Nạp Trang (Disk-to-DB Overwrite)
- **Triệu chứng (Symptom):** AI đẩy template FSE mới vào `wp_posts` (`wp_template`) thành công qua SQL/API. Khi mở trang ngoài frontend, dữ liệu trong database bị quay ngược về trạng thái cũ 156 bytes.
- **Nguyên nhân gốc (Root Cause):** `align/core/includes/core/class-align-template-tweaks.php:179` - Hàm `sync_all_templates_with_db()` kích hoạt tại hook `wp_loaded`. Hàm này so sánh nội dung `wp_posts.post_content` với tệp `templates/{slug}.html` trên đĩa. Nếu nội dung khác nhau, hệ thống tự động ghi đè database bằng tệp trên đĩa.
- **Cách đã xử lý (Fix Applied):** Cập nhật `align/bin/agent-page-core.php`. Khi thực thi lệnh `push` cho `wp_template`, hệ thống ghi đồng thời vào `wp_posts.post_content` và tệp tĩnh `align-child/templates/{slug}.html`.
- **Quy tắc cho AI sau (Rule for Future AI):** Luôn ghi đồng thời vào Database và tệp tĩnh `templates/{slug}.html` khi cập nhật bất kỳ block template nào. Tuyệt đối không chỉ cập nhật database đơn lẻ.

---

### [2026-10-07] - Mất Hero Banner Khi Dọn Dẹp Mã HTML Tĩnh Trên Single Post
- **Triệu chứng (Symptom):** Các bài viết Single Post (`post`) và Highlight Kit Builder không hiển thị Hero Banner sau khi dọn sạch mã HTML tĩnh khỏi tệp theme.
- **Nguyên nhân gốc (Root Cause):** `align-child/templates/single.html:1` và `align-child/templates/spotlight-builder.html:1` - Khối Hero Banner trước đây bị hardcode trực tiếp trong tệp theme. Khi tệp template bị rút gọn chỉ còn `<!-- wp:post-content /-->`, bài viết mất hoàn toàn khối banner dùng chung.
- **Cách đã xử lý (Fix Applied):** Đóng gói khối Dynamic Hero Banner thành Gutenberg Block comments hợp lệ (`<!-- wp:align/custom-html -->`, `<!-- wp:group -->`) và đưa vào template trong Database và tệp theme. Khối banner sử dụng shortcode động của theme (`[align_post_hero_image]`, `[align_post_title]`, `[align_post_meta_line]`).
- **Quy tắc cho AI sau (Rule for Future AI):** Luôn đặt các khối bố cục dùng chung (Hero Banner, Meta header, CTA banners, Float actions) tại Template (`wp_template`). Không bắt buộc từng bài viết đơn lẻ phải tự nhân bản khối banner trong `post_content`.

---

### [2026-10-07] - Lệch Tên Thư Mục Không Khớp Giữa Post ID Và Slug Khi Xuất Bản
- **Triệu chứng (Symptom):** Lệnh `agent-page push <id>` báo lỗi `Workspace directory not found` sau khi xuất bản bài viết từ trạng thái nháp (`draft`) sang `publish`.
- **Nguyên nhân gốc (Root Cause):** `align/bin/agent-page-core.php:160` - Khi bài viết ở trạng thái nháp, `post_name` rỗng nên thư mục scratch mang tên ID (ví dụ `.agent-scratch/2738/`). Khi bài viết chuyển sang `publish`, `post_name` được sinh tự động khiến CLI tìm kiếm theo slug mới thay vì ID cũ.
- **Cách đã xử lý (Fix Applied):** Bổ sung cơ chế fallback trong `agent-page-core.php` cho cả 3 lệnh `push`, `cleanup`, và `rollback`. Nếu không tìm thấy thư mục theo slug, hệ thống tự động kiểm tra thư mục theo `$target` và `$post->ID`.
- **Quy tắc cho AI sau (Rule for Future AI):** Luôn hỗ trợ phân giải fallback hai chiều giữa `post_name` (slug) và `post_ID` khi làm việc với thư mục workspace tạm thời.

---

### [2026-10-07] - Thanh Thông Số Bị Thanh Điều Hướng Nổi Che Khuất Trên Kit Builder
- **Triệu chứng (Symptom):** Thanh thông số xe (`.bh-specs-box`) của trang Highlight Kit Builder bị thanh header cố định (`position: fixed`, cao 112px) che mất khi mở trang.
- **Nguyên nhân gốc (Root Cause):** `align-child/assets/builder/style-page-brittany-blue-1966-bronco-interior.css:1` - Container chính `.bh-page-main` thiếu thuộc tính bù trừ khoảng cách đỉnh (`padding-top`).
- **Cách đã xử lý (Fix Applied):** Thêm thuộc tính `padding-top: 112px` vào selector `.bh-page-main` trong tệp CSS trên đĩa và đẩy đồng bộ vào database.
- **Quy tắc cho AI sau (Rule for Future AI):** Khi tạo trang có thanh điều hướng nổi cố định (`fixed/floating header`), luôn cấu hình khoảng cách đệm `padding-top` tối thiểu bằng chiều cao header cho thẻ container gốc.

---

### [BUG-20261018-1930] - Mất Trạng Thái Mở Rộng Nhánh, Nhảy Cuộn Và Không Hoàn Tác Được Khi Thao Tác Trên Tree Navigator
- **Triệu chứng (Symptom):** Khi người dùng kéo thả sắp xếp, nhân bản (Duplicate/Clone), dán (Paste) hoặc xóa (Delete) phần tử trên Tree Navigator (`#tree-list`), các nhánh đang mở bị tự động đóng sập lại, thanh cuộn dọc của cây bị nhảy vị trí, cây vẽ lại nhiều lần gây giật khung hình, và thao tác không thể hoàn tác bằng `Ctrl/Cmd+Z` (hoặc không có tác dụng khi đóng Code Editor).
- **Nguyên nhân gốc (Root Cause):**
  1. `align/core/builder/libs/builder/engine/builder-ast-parser.js:173` — `AstParser.parse()` luôn reset `this._idCounter = 0` và gán lại toàn bộ `data-ast-id` theo thứ tự duyệt tuần tự mỗi lần `syncDom()`, làm dịch chuyển toàn bộ ID phía sau điểm chèn/xóa/di chuyển khiến tập `expandedAstIds` không còn khớp.
  2. `align/core/builder/libs/builder/engine/builder-tree-sortable.js:158` & `builder-selection-manager.js:368` — Luồng kéo-thả, clone và delete đi qua `AstParser.moveNode/cloneNode/removeNode -> toHtml() -> CodeEditor.setValue() -> Builder.setHtml() -> DomMorph.morph()`, vừa bỏ qua hoàn toàn `Vvveb.Undo.addMutation()`, vừa bị `CodeEditor.setValue()` chặn (`if (!this.isActive) return;`) khi trình sửa mã đang đóng.
  3. `align/core/builder/libs/builder/engine/builder-tree-list.js:177` — `loadComponents()` xóa trắng `innerHTML` đồng bộ nhiều lần trong cùng một hành động mà không gom nhóm (coalesce) và không lưu/khôi phục `scrollTop`; đồng thời `selectComponent()` dùng `targetLi.offsetTop` (tương đối theo phần tử cha) làm ghi đè sai `treeContainer.scrollTop` khi chọn phần tử lồng sâu.
- **Cách đã xử lý (Fix Applied):**
  1. Cập nhật `builder-ast-parser.js`: Bảo toàn các `data-ast-id` hợp lệ đã có trên DOM khi `stampDom = true`, khởi tạo `_idCounter = Math.max(this._idCounter, maxExistingId)` và tự động cấp ID mới cho phần tử nhân bản bị trùng ID (`duplicatesFixed`).
  2. Chuyển toàn bộ `builder-tree-sortable.js`, `builder-selection-manager.js` (`cloneSelectedNodes`, `deleteSelectedNodes`) và `wp-context-menu.js` (`pasteCopiedElement`) sang thao tác DOM-first trực tiếp trên Canvas kèm ghi nhận đầy đủ `Vvveb.Undo.addMutation` (`move` / `childList`), và đồng bộ debounced sang CodeMirror 6 qua `Vvveb.CodeEditor.scheduleSync(300)` trong `builder-code-editors.js`.
  3. Tạo module `builder-tree-render.js` (dưới 500 dòng) gom nhóm `loadComponents()` qua `requestAnimationFrame` (hỗ trợ `{ sync: true }`), bảo toàn `scrollTop` + `expandedAstIds`, và sửa `_selectComponentImmediate` trong `builder-tree-list.js` dùng `getBoundingClientRect()` tương đối với `.tree`.
- **Quy tắc cho AI sau (Rule for Future AI):** Tuyệt đối không reset `AstParser._idCounter = 0` khi đóng dấu DOM (`syncDom`) và không bao giờ định tuyến các thao tác cấu trúc trên Canvas/Tree (Move, Clone, Paste, Delete) đi vòng qua `CodeEditor.setValue() -> setHtml()`. Mọi thao tác cấu trúc phải thực hiện DOM-first, ghi nhận vào `Vvveb.Undo`, và gọi `Vvveb.TreeList.loadComponents()` qua bộ lập lịch rAF coalesced.

---

### [BUG-20261018-1950] - Dòng Tree Navigator Nhảy Vị Trí Liên Tục, Mất Vùng Thả Và Lồng Nhầm Thẻ Văn Bản Khi Kéo Thả SortableJS
- **Triệu chứng (Symptom):** Khi giữ chuột kéo một mục trên Tree Navigator đi qua các danh sách `<ol>` lồng nhau: (1) dòng đang kéo tự động nhảy chèn vào các nhánh khác ngay lúc chưa thả chuột khiến các dòng mục tiêu nhảy lệch khỏi con trỏ; (2) khi thả chuột thì phần tử quay về chỗ cũ không di chuyển; (3) kéo một thẻ vào giữa dòng `Paragraph` (`<p>`) hoặc `Heading` (`<h1>`–`<h6>`) lại cho phép thả vào trong (`inside`) làm hỏng cấu trúc HTML; (4) vạch xanh `.tree-drop-before`/`.tree-drop-after` làm chữ trong dòng bị giật lên/xuống 1–2px.
- **Nguyên nhân gốc (Root Cause):**
  1. `align/core/builder/libs/builder/engine/builder-tree-sortable.js:98` — Cấu hình SortableJS đặt `group: { name: 'tree-navigator', pull: true, put: true }` và `onMove` trả về `1`. Khi kéo qua các `<ol>` khác cấp, hàm `_onDragOver` của SortableJS tự động di chuyển phần tử `draggedLi` thật sang `<ol>` mới ngay dưới con trỏ chuột. Ngay frame tiếp theo, `document.elementFromPoint(clientX, clientY)` trúng chính `draggedLi` vừa nhảy tới, khiến điều kiện `targetLi !== draggedLi` thất bại và gán `self._activeDrop = null` (mất mục tiêu thả khi nhả chuột).
  2. `builder-tree-sortable.js:57` — Chỉ kiểm tra `voidElements` (`img`, `input`, `br`...) mà chưa chặn các thẻ lá/văn bản (`p`, `h1`–`h6`, `span`, `a`, `button`...) và các khối blackbox (`data-component-shortcode`, `data-component-wp-block`, `data-component-post-content`).
  3. `align/core/builder/css/components/editor-tree-navigator.css:406` — Sử dụng `border-top: 2px solid #0d6efd` và `border-bottom: 2px solid #0d6efd` trên `label` (`height: 26px; box-sizing: border-box`) làm thay đổi hộp nội dung flex và đẩy lệch text 1px khi rê chuột.
- **Cách đã xử lý (Fix Applied):**
  1. Trong `builder-tree-sortable.js`: Đặt `group: { name: 'tree-navigator', pull: false, put: false }`, `sort: false` và trả về `false` trong `onMove` để SortableJS không bao giờ tự ý di chuyển `<li>` trong DOM lúc đang kéo; mọi hit-testing được điều khiển hoàn toàn bởi `updateDropTarget` và `onEnd`.
  2. Bổ sung `Vvveb.TreeList.canAcceptChildren(targetNode)` (`_leafTags` + blackbox attributes) áp dụng đồng nhất tại `updateDropTarget` và `onEnd`: chuyển các thẻ văn bản/lá/blackbox sang chế độ 2 vùng (50% `before` / 50% `after`), giữ chế độ 3 vùng (25% `before` / 50% `inside` / 25% `after`) cho các container thật (`div`, `section`, `blockquote`, `ul`, `ol`, `li`...).
  3. Trong `editor-tree-navigator.css`: Thay `border-top`/`border-bottom` bằng `box-shadow: inset 0 2px 0 0 #0d6efd !important` và `box-shadow: inset 0 -2px 0 0 #0d6efd !important` để triệt tiêu hoàn toàn độ lệch dòng (`0px` shift).
- **Quy tắc cho AI sau (Rule for Future AI):** Khi dùng SortableJS với cơ chế custom 3-zone hit-testing (`elementFromPoint`), BẮT BUỘC phải khóa `pull: false, put: false, sort: false` và `onMove: () => false` để ngăn SortableJS tự di chuyển DOM node đang kéo dưới con trỏ chuột; đồng thời luôn dùng `box-shadow: inset` thay vì `border` cho drop indicator và kiểm tra `canAcceptChildren()` trước khi cho phép thả `inside`.

---

### [BUG-20261020-1430] — Post-Content Guard Drift & Forced Bubble Toolbar Regression trong Content Mode
- **Triệu chứng (Symptom):**
  1. Trong Content Mode, click vào khối `[data-component-post-content]` hoặc shortcode/wp-block vẫn bị lọt vào nhánh `textTags` của `resolveTextEditableElement`, làm người dùng chỉnh sửa nhầm cấu trúc component đóng gói.
  2. Khi click vào text trong Content Mode mà chưa bôi đen selection, thanh công cụ Bubble Toolbar vẫn bị cưỡng chế hiển thị đè lên chữ và che khuất tầm nhìn (hồi quy lỗi Issue 213).
- **Nguyên nhân gốc (Root Cause):**
  1. `align/core/builder/libs/builder/engine/builder-highlight-manager.js:304-320`: Tài liệu Issue 213 ghi nhận có guard `[data-component-post-content]`, nhưng mã nguồn thực tế bị thiếu guard này trong `closest(...)` và không có early guard chặn các nhánh con phân giải sâu vào leaf text của packaged block.
  2. `align/core/builder/libs/builder/engine/builder-word-editor.js:171-177`: Đoạn fallback `contentMode && WysiwygEditor.isActive` cưỡng chế hiển thị bubble toolbar ngay cả khi selection đang collapsed (`length === 0`).
- **Cách đã xử lý (Fix Applied):**
  1. Thêm early guard `if (el.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]')) return null;` trong `builder-highlight-manager.js` và phơi ra API `Vvveb.Builder.resolveTextEditableElement`.
  2. Xóa bỏ hoàn toàn khối fallback cưỡng chế bubble toolbar trong `builder-word-editor.js`, chỉ kích hoạt bubble toolbar khi có vùng chọn thực tế (`range.toString().length > 0`).
- **Quy tắc cho AI sau (Rule for Future AI):**
  - **NÊN:** Luôn chặn phân giải editable node trên các vùng packaged components (`post-content`, `shortcode`, `wp-block`) ngay từ đầu hàm `resolveTextEditableElement`.
  - **KHÔNG NÊN:** Tuyệt đối không cưỡng chế hiển thị Floating Bubble Toolbar khi không có text selection thực sự.

