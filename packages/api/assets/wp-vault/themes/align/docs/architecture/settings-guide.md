# Hướng Dẫn Hệ Thống Admin Settings & Performance Subsystems

> [!NOTE]
> Trang **Align Settings** trong Admin WordPress (`core/includes/admin/class-align-admin-settings.php`) cung cấp giao diện quản trị hiện đại, tích hợp các bộ công cụ tối ưu hiệu năng, nạp tài nguyên, tùy biến mã nguồn và quản lý hệ thống.
> 
> **Quy chuẩn giao diện bắt buộc**: Mọi thay đổi trên trang này bắt buộc phải tuân theo [**Align Settings UI Style Guide**](./settings-style-guide.md) (Button 100% transparent / không background, Card Design System, Accordion default closed, Zero emojis).

---

## 1. Tổng Quan Giao Diện Admin Settings

Hệ thống Align Settings được quy hoạch thành 8 Master Hubs chuyên biệt dạng Tabs với thanh điều hướng hiện đại (Modern Segmented Navigation Bar), thanh lưu cố định (Sticky Save Bar) và cơ chế **Tab State Persistence** (lưu vị trí tab vào `localStorage` và URL Hash `#tab-name`, tự động mở lại đúng tab khi reload/lưu form). **Site Protection** được đặt ở vị trí số 1 mặc định khi mở trang để quản trị viên có thể bật/tắt bảo vệ nhanh chóng:

```mermaid
graph TD
    AdminPanel[WP Admin: Align Settings] --> Master1[1. Site Protection (#site-protect) - Mặc định]
    AdminPanel --> Master2[2. Builder & AI (#builder-studio)]
    AdminPanel --> Master3[3. SEO & Multi-Language (#seo-languages)]
    AdminPanel --> Master4[4. Performance & Media (#performance)]
    AdminPanel --> Master5[5. Developer Tools (#system-tools)]
    AdminPanel --> Master6[6. Integrations (#integrations)]
    AdminPanel --> Master7[7. AI REST API (#api)]
    AdminPanel --> Master8[8. Updates (#updates)]

    Master1 --> ProtectEngine[HTTP Basic Auth 401 Prompt + Multi-Tier No-Index + Localhost Bypass + 30-Day Cookie]
    Master2 --> BuilderEngine[External Assets Storage + HTML Converter + AI Hub]
    Master3 --> SEOEngine[Active Languages + Redirections + Translation Matrix + Bulk AI]
    Master4 --> MediaEngine[WebP Media + Scripts Delay + HTML Minify]
    Master5 --> ToolsEngine[Custom Code & Tracking + Asset Loader]
    Master6 --> IntegrationsEngine[Telegram Notifications + Webhooks + External Platforms]
    Master7 --> APIEngine[Dedicated AI REST API Spec & Interactive Console]
    Master8 --> UpdatesEngine[Git Theme Core Updater & Deploy Console]
```

> [!TIP]
> **Tab Persistence & Non-Blocking Multi-Tab Validation**:
> - Mỗi thẻ nav tab đều có thuộc tính `data-tab` (`tab-site-protect`, `tab-builder-studio`, `tab-seo-languages`...) và hỗ trợ mở trực tiếp tab thông qua đường dẫn URL hash (ví dụ `admin.php?page=align-settings#site-protect` hoặc `#builder-studio`). Khi người dùng nhấn nút **Save Changes**, giao diện sẽ tự động hiển thị lại đúng tab vừa chọn thông qua `localStorage` và hash state.
> - Thẻ `<form id="align-settings-form">` sử dụng thuộc tính `novalidate` kết hợp với backend sanitization an toàn để triệt tiêu lỗi `An invalid form control is not focusable` khi submit từ các tab đang bị ẩn.

---

## 2. Chi Tiết Các Module Settings

### 2.0 Site Protection & Access Control (`class-align-site-protection.php` & `tab-site-protection.php`)
**Master Tab số 1 (Mặc định khi mở trang)**: Cung cấp giải pháp bảo vệ toàn diện môi trường Staging / Pre-launch tương tự tính năng Password Protection của WP Engine và Kinsta:
- **Smart Environment-Aware Detection (Auto Override)**: Tự động phân định môi trường hoạt động (`local`, `staging`, `live`, `custom`) thông qua đối chiếu host hiện tại với 2 cấu hình tên miền:
  - **Live Domain** (`align_site_protect_live_domain`): Khi host trùng khớp với Live Domain, Site Protection và No-Index barrier tự động **TẮT** (`false`) hoàn toàn nhằm đảm bảo an toàn truy cập công khai và bảo vệ SEO ranking cho website chính thức.
  - **Staging Domain** (`align_site_protect_staging_domain`): Khi host trùng khớp với Staging Domain, Site Protection và No-Index barrier tự động **BẬT** (`true`) để bảo mật dữ liệu trước khi launch và ngăn crawler tìm kiếm index nội dung thử nghiệm.
  - **Local Development**: Tự động nhận diện localhost, local IP, các đuôi tên miền dev (`.local`, `.test`, `.localhost`, `.internal`, `.lndo.site`, `.ddev.site`) hoặc `wp_get_environment_type() === 'local'` để bypass bảo vệ và no-index.
  - **Custom / Manual Fallback**: Khi host không trùng khớp với các cấu hình trên, hệ thống vận hành theo checkbox bật/tắt thủ công `align_site_protect_enabled`.
  - **Dung Sai Tên Miền Linh Hoạt (Domain Normalization)**: Hàm `normalize_domain()` tự động làm sạch scheme (`http://`, `https://`), port, path, trailing slash và tiền tố `www.`, đảm bảo nhận diện chính xác tuyệt đối mà không phụ thuộc vào cách người dùng nhập liệu.
- **HTTP Basic Authentication (Early Intercept)**: Chặn request ngay tại hook `init` (priority 1) trước khi bất kỳ template HTML hay asset CSS/JS nào được nạp. Phát mã `HTTP/1.1 401 Unauthorized` + Header `WWW-Authenticate: Basic realm="..."` để trình duyệt bật native prompt alert.
- **Persistent Access Cookie**: Cấp HttpOnly cookie `align_site_protect_token` mã hóa HMAC-SHA256 liên kết với secret salt của WordPress (`wp_salt('auth')`), có thời hạn ghi nhớ tùy chỉnh (mặc định 30 ngày) để người dùng không phải nhập lại mật khẩu nhiều lần. Sử dụng `hash_equals()` kháng Timing Attack.
- **Localhost & Dev Auto-Bypass**: Tự động nhận diện và bóc tách port cho các môi trường nội bộ (`127.0.0.1`, `::1`, `localhost:8080`, `.local`, `.test`, `.internal`, `.lndo.site`, `.ddev.site`, hoặc `wp_get_environment_type() === 'local'`).
- **Full-Site No-Index SEO Shield**: Tự động chặn index toàn diện qua 4 tầng: (1) HTTP header `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet`, (2) Thẻ meta robots trong `wp_head`, (3) Filter core `pre_option_blog_public` ép về `'0'`, (4) Virtual `robots.txt` disallow all.
- **System Bypasses**: Tự động bỏ qua cho WP-CLI, WP Cron (`DOING_CRON`), GitHub Webhook (`?align-git-webhook`), và tài khoản quản trị WordPress (`manage_options`) đã đăng nhập.

### 2.1 Visual Builder Performance & Storage (`tab-builder.php` & `part-block-converter.php`)
Tập trung chuyên biệt vào việc tối ưu hiệu năng render của Visual Builder và công cụ chuyển đổi markup:
- **Disable wpautop** (`align_disable_wpautop`, mặc định tắt): Checkbox trong **Visual Builder Performance & Storage**, ngăn tự chèn `<p>`/`<br>` qua `the_content` cho pages/posts và REST rendered content. Không ảnh hưởng excerpt. Bỏ chọn vẫn giữ bảo vệ HTML Builder hiện có. Nhấn **Save Changes** để lưu.
- **Dán HTML trong Gutenberg**: Post/Page/Template tự dùng converter API khi dán HTML vào visual canvas. Cấu hình API dùng chung với công cụ HTML to Gutenberg Block Converter; không cần convert rồi copy markup thủ công.
- **External Builder Assets** (`align_builder_external_assets`): Tùy chọn lưu trữ toàn bộ CSS và JS của Visual Builder thành các file tĩnh độc lập trên đĩa (`wp-content/themes/align-child/assets/builder/style-page-{slug}.css`) và tự động enqueue ngoài WordPress frontend, giúp giảm kích thước payload HTML, tận dụng browser/CDN caching và tăng điểm Core Web Vitals.
- **HTML to Gutenberg Block Converter Playground** (`part-block-converter.php`): Giao diện tương tác 2 cột cho phép dán mã HTML bất kỳ và chuyển đổi trực tiếp sang chuẩn Gutenberg blocks của Align Theme thông qua microservice `/api/convert`. Nút bấm tuân thủ chuẩn Transparent / No-Background Button System (`Convert to Blocks`, `Copy Markup`, `Clear`).

### 2.2 Connect API & AI Hub (`class-align-ai-connection.php`, `tab-connect-api.php` & `class-align-endpoint-tester.php`)
Trung tâm quản trị toàn bộ các kết nối API, dịch vụ AI đám mây và microservices của Align Theme:
1. **AI & LLM Connection Hub (Kiến trúc 2 Tab Độc Lập)**:
   - **Tách biệt 2 Tab hoàn toàn độc lập**:
     - **Tab 1: OpenRouter**: Cấu hình Base URL (`https://openrouter.ai/api/v1`), API Key riêng (`align_openrouter_api_key`), Model mặc định (`align_openrouter_model`, mặc định: `google/gemini-2.5-flash`), kèm chips gợi ý model nhanh (Gemini Flash, Claude Sonnet, DeepSeek Chat, GPT-4o, Llama).
     - **Tab 2: OpenAI (Official & Compatible)**: Cấu hình Base URL (`align_openai_base_url`, mặc định: `https://api.openai.com/v1`), API Key riêng (`align_openai_api_key`), Model mặc định (`align_openai_model`, mặc định: `gpt-4o-mini`), Organization ID (`align_openai_organization`), kèm các nút One-Click Quick Presets cho OpenAI Official, DeepSeek, Groq, và Ollama Local.
   - **Chuyển đổi Active Provider Tức Thì (1-Click Switch)**:
     - Cho phép lưu trữ song song cả 2 bộ API key trong database mà không bao giờ bị ghi đè lẫn nhau.
     - Dễ dàng kiểm tra kết nối (Live Test) của từng bên độc lập mọi lúc.
     - Chuyển đổi nhà cung cấp hoạt động trên toàn website chỉ với 1 click bấm nút `Activate OpenRouter` hoặc `Activate OpenAI / Compatible`.
   - **Bộ Kiểm Tra Kết Nối Tức Thì (Live Test Connection)**:
     - Gửi test request AJAX `align_test_ai_connection` thực tế đến endpoint chat completions tương ứng của từng tab.
     - Đo lường và hiển thị độ trễ chính xác theo mili-giây (latency ms), trạng thái HTTP status và model phản hồi thực tế.
     - Thông báo lỗi chi tiết, rõ ràng khi sai API key, sai URL hoặc vượt hạn mức quota.
   - **Tự động di chuyển dữ liệu (Auto-migration)**: Tự động di trú các key cũ từ `align_ai_api_key` và `align_openrouter_api_key` sang hệ thống mới mà không làm mất cấu hình đang có.
2. **Align Cloud Microservices (`class-align-endpoint-tester.php`)**:
   - **External API Converter URL**: Cấu hình URL dịch vụ biên dịch SCSS, WebP, Minify, Unminify và HTML block (mặc định: `https://convertb.alignvn.cloud`).
   - **Unified Multi-Endpoint Health Check ("Test All 6 Endpoints")**: Bộ chẩn đoán tập trung 1 chạm kiểm tra đồng loạt 6 microservice endpoints (`/health`, `/api/convert-image-to-webp`, `/api/scss-to-css`, `/api/minify`, `/api/unminify`, `/api/convert`), hiển thị bảng kết quả trực quan (Status Pill Badges, Latency ms, HTTP Method badges, chi tiết lỗi nếu có).
3. **REST API Sync Token**: Bearer token bảo mật dành cho endpoint đồng bộ bài viết `/wp-json/align-sync/v1/create-page`.

### 2.2 Unified Media Settings Engine (`tab-media.php`, `part-webp-converter.php`, `part-media-sizes-table.php` & `part-thumbnail-regenerator.php`)
Hợp nhất toàn bộ các công cụ quản trị hình ảnh và media của WordPress vào một tab duy nhất với 3 thành phần chính:
1. **WebP Media Converter (`class-align-webp.php` & `part-webp-converter.php`)**:
   - **On-The-Fly WebP Upload Conversion**: Tự động chuyển đổi trực tiếp các file ảnh JPG/PNG/GIF khi tải lên thư viện Media sang định dạng `.webp` thông qua endpoint `/api/convert-image-to-webp`.
   - **Full Sub-Sizes & Metadata Synchronization**: Cập nhật toàn bộ mảng `sizes` thumbnail cùng `post_mime_type: image/webp` vào `wp_update_attachment_metadata`.
   - **Quality Setting**: Tự chỉnh chất lượng nén WebP (1-100, mặc định: 80).
   - **Bulk Media Converter (Async Worker Pool & Concurrency Throttling)**: Quét và chuyển đổi hàng loạt toàn bộ ảnh cũ trong Media Library bằng mô hình Worker Pool bất đồng bộ với giới hạn cứng `CONCURRENCY = 2`. Mỗi worker gửi 1 request riêng lẻ cho từng Attachment ID, tự động gối đầu qua `.finally()` khi request hoàn thành. Trang bị cơ chế kháng lỗi non-blocking (lỗi mạng/timeout không làm đơ queue) và chốt chặn phòng thủ backend `array_slice( $ids, 0, 2 )` bảo vệ an toàn server Cloud microservice và PHP-FPM.
2. **Registered Media Sizes Table Accordion (`class-align-image-sizes.php` & `part-media-sizes-table.php`)**:
   - **Accordion Card Mặc Định Đóng (Default Closed)**: Bảng kích thước hình ảnh được gói trong Accordion Card sang trọng, mặc định thu gọn giúp trang gọn gàng. Tự động bung mở mượt mà kèm xoay icon mũi tên 180°.
   - **Chính Sách Mặc Định Zero Hard Crop (Proportional Scaling First)**: Toàn bộ kích thước media (cả Standard Sizes như `thumbnail` lẫn Custom Sizes khi tạo mới) đều có giá trị Hard Crop mặc định là `0` (Tắt). Hình ảnh tải lên giữ nguyên tỉ lệ gốc, không bị cắt xén vật lý mất góc cạnh trừ khi người dùng chủ động tích chọn checkbox "Hard Crop". Bảng cài đặt trang bị hidden input `value="0"` kháng mất state khi submit form và tooltip hướng dẫn chi tiết.
   - **Accordion Deduplication & Kháng Xung Đột (`settings-layout.js`)**: Trang bị bộ lọc thông minh tự động bỏ qua các card đã có controller chuyên biệt (`.align-accordion-toggle`), triệt tiêu hoàn toàn lỗi chèn thừa mũi tên tam giác và xung đột 2 click event listener làm kẹt đóng/mở.
   - **Nút Thêm Mới Tự Động Mở**: Nút `+ Add Custom Size` trên header tuân thủ chuẩn nút Transparent, khi click sẽ tự động mở bung accordion và focus vào ô nhập Display Label.
   - **Bảng Quản Lý Hợp Nhất**: Quản lý toàn bộ kích thước Standard và Custom trong 1 bảng trực quan. Tên Size Name/Slug được cố định (read-only) để tránh làm sai lệch định danh layout. Nút `Delete` xóa kích thước tức thì qua AJAX `align_delete_image_size`.
   - **Đồng Bộ Hai Chiều Với WP Core**: Đồng bộ các thiết lập `thumbnail`, `medium`, `medium_large`, `large`, `1536x1536`, `2048x2048` vào các options chuẩn của WordPress.
   - **Gỡ Bỏ Menu Media Mặc Định Của WordPress (`remove_submenu_page`)**: Vì phân hệ Align Settings đã tích hợp toàn diện bảng quản lý Media Sizes và WebP Converter, menu mặc định "Media" (`options-media.php`) dưới mục Settings của WordPress được tự động gỡ bỏ khỏi sidebar. Nếu quản trị viên truy cập trực tiếp vào `wp-admin/options-media.php`, hệ thống tự động redirect an toàn về tab **Performance & Media -> Media & WebP** (`#performance/perf-media`) kèm đầy đủ guards kiểm tra quyền và chống lỗi WSOD (`headers_sent()`).
3. **Force Thumbnail Regenerator Accordion (`class-align-thumbnail-regenerator.php` & `part-thumbnail-regenerator.php`)**:
   - Giao diện Accordion Card mặc định đóng gọn gàng, tự động bung mở khi trigger từ Media Library.
   - Nút hành động chuẩn mực không background: `Regenerate All Thumbnails`, `Resume Last Job`, `Cancel Process`, `Retry Failed Items`.
   - Tái tạo tuần tự AJAX (1 ảnh/request) với memory limit mở rộng và timeout 300s.
   - Xóa sạch file thumbnail cũ, file mồ côi (orphans regex) và file `.webp`.
   - Checkpoint DB (`align_last_regenerated_id`), Resume khi đóng trình duyệt, Retry Failed và timeout 60s/ảnh chống treo.

### 2.3 Source Code Minify Engine (`core/includes/core/minify.php` & `tab-minify.php`)
Quản lý tối ưu nén mã nguồn đầu ra:
- **HTML Output Minification**: Tùy chọn nén nhỏ dung lượng HTML render ở ngoài frontend, loại bỏ các ký tự xuống dòng và khoảng trắng dư thừa để tăng điểm PageSpeed.

### 2.4 Custom Code & Unified Inline Engine (`core/includes/core/class-align-custom-css-js.php`, `class-align-custom-code-slots.php` & `tab-custom-code.php`)
Quản lý việc chèn mã CSS tùy chỉnh, JavaScript, Tracking Code, Body Open Code, Footer HTML và gộp toàn bộ inline style/script trên toàn site:
- **Header CSS** (`align_custom_css`): Chấp nhận CSS thuần hoặc dán kèm thẻ `<style>`, `<link>`. Tự động bóc tách lồng thẻ, gom chung với các style cốt lõi (`lazyload` shimmer, `lenis` smooth scroll) và xuất vào `<head>` (ưu tiên 99). Mã được lưu trữ thô nguyên bản (không minify) để tránh vỡ code.
- **Custom JS** (`align_custom_js`): Chấp nhận JavaScript thuần hoặc script nhúng bên thứ 3 (kể cả thẻ `<script>` / `<script src="...">`). Nếu chứa thẻ `<script`, hệ thống tự động xuất trực tiếp trước `</body>` (ưu tiên 99) chống bọc lồng thẻ; nếu là JS thuần sẽ gom vào thẻ `<script id="align-core-inline-js">`. Mã được lưu thô nguyên bản không qua minification.
- **Tracking Code** (`align_tracking_code`): Thêm các đoạn mã HTML/Script theo dõi dịch vụ bên thứ 3 (Google Analytics, Google Tag Manager, Meta Pixel, v.v.) trực tiếp vào `<head>` (ưu tiên 1).
- **Body Open Code** (`align_body_open_code`): Đoạn mã HTML/JS chèn ngay sau thẻ mở `<body>` thông qua hook `wp_body_open` (ưu tiên 1), thường dùng cho GTM noscript iframe fallback.
- **Footer HTML** (`align_footer_html_code`): Đoạn mã HTML/JS chèn ngay trước thẻ đóng `</body>` thông qua hook `wp_footer` (ưu tiên 100), thích hợp cho live chat widget, modal nhúng, pixel tải chậm.
- **Bỏ qua cho Quản trị viên** (`align_custom_code_skip_admins`): Khi kích hoạt, toàn bộ Tracking Code, Body Open và Footer HTML sẽ bị ẩn đối với Quản trị viên đã đăng nhập (`manage_options`) nhằm tránh làm sai lệch số liệu thống kê. Header CSS và Custom JS vẫn được thực thi để bảo toàn giao diện.
- **Trình soạn thảo CodeMirror**: Tích hợp trực tiếp thư viện Code Editor chuẩn của WordPress (`wp.codeEditor`) cho toàn bộ 5 ô nhập mã, tự động nhận diện mode (`text/css`, `text/javascript`, `text/html`), tự động làm mới khi chuyển tab (`cm.refresh()`) và đồng bộ hai chiều khi lưu form.

```php
<?php
/**
 * Custom Code & Unified Inline Assets Architecture
 */
// 1. Đăng ký style hoặc script inline từ các module core
Align_Custom_CSS_JS::add_inline_css( 'lazyload', Align_Lazyload::SHIMMER_CSS );
Align_Custom_CSS_JS::add_inline_css( 'lenis', Align_Smooth_Scroll::LENIS_CSS );
Align_Custom_CSS_JS::add_inline_js( 'lenis-init', Align_Smooth_Scroll::LENIS_INIT_JS, 'footer' );

// 2. Xuất thẻ HTML hợp nhất ra frontend (tự động loại bỏ thẻ trùng lặp, hỗ trợ skip admin)
add_action( 'wp_head', array( 'Align_Custom_Code_Slots', 'output_tracking_code' ), 1 );
add_action( 'wp_body_open', array( 'Align_Custom_Code_Slots', 'output_body_open_code' ), 1 );
add_action( 'wp_head', array( 'Align_Custom_CSS_JS', 'output_custom_css' ), 99 );  // <style id="align-core-inline-css">
add_action( 'wp_footer', array( 'Align_Custom_CSS_JS', 'output_custom_js' ), 99 ); // <script id="align-core-inline-js"> hoặc direct script
add_action( 'wp_footer', array( 'Align_Custom_Code_Slots', 'output_footer_html_code' ), 100 );
```

### 2.5 Dynamic Load Asset & Font Preload Engine (`core/includes/admin/class-align-admin-assets.php`, `core/includes/core/class-align-custom-css-js.php` & `tab-load-assets.php`)
Hệ thống nạp file CSS, JS và Font tùy chỉnh với kiến trúc tách module `Align_Admin_Assets` chuyên biệt và Native File Uploader chuyên nghiệp:
- **Tách Module `Align_Admin_Assets` Độc Lập**:
  Toàn bộ logic quản lý tài nguyên (Upload native, Phân giải đường dẫn tương đối, Xóa file vật lý an toàn, Sanitize dữ liệu và Migrate legacy) được tách riêng thành class `Align_Admin_Assets` (430 dòng < 500 dòng), tuân thủ chặt chẽ nguyên lý Single Responsibility Principle (SRP).
- **Cơ Chế Xóa Vĩnh Viễn File Vật Lý Trên Ổ Đĩa (Zero Orphaned Files)**:
  - Khi người dùng nhấn nút thùng rác (Delete) trên bảng tài nguyên, hệ thống mở hộp thoại xác nhận `confirm(...)` để chống xóa nhầm.
  - Nút bấm tự động chuyển sang trạng thái loading với icon xoay tròn (`dashicons-update spin`) và bị vô hiệu hóa để chống double-click.
  - Gửi request AJAX `align_delete_asset_file` lên server:
    1. **Bảo mật Sandbox Anti-Traversal**: Dùng `realpath()` chuẩn hóa đường dẫn, kiểm tra file phải nằm 100% bên trong các thư mục hợp lệ (`/themes/align-child/assets/`, `/themes/align/assets/`, `/uploads/align-assets/`, `/uploads/`).
    2. **Whitelist Định Dạng**: Chỉ cho phép xóa các đuôi file asset `.css`, `.js`, `.woff2`, `.woff`, `.ttf`, `.otf`, `.eot`, `.map`. Tuyệt đối ngăn chặn và cảnh báo các file `.php`, `.phtml`, `.htaccess`, `.sql`, v.v.
    3. **Xóa Sạch File & CSDL Tức Thì**: Gọi `unlink()` xóa file vật lý trên đĩa, gọi `clearstatcache(true, $path)` chống cache stat cũ, và tự động loại bỏ URL khỏi option `align_loaded_assets` trong database.
    4. **Cập nhật UI**: Xóa dòng khỏi DOM, tự động đánh lại số thứ tự (`reindexRows()`) và hiển thị hàng thông báo rỗng nếu toàn bộ tài nguyên đã bị xóa.
- **Thanh Công Cụ Toolbar Tối Giản (Chỉ 2 Nút Thao Tác)**:
  Toolbar được tinh gọn tối đa, loại bỏ hoàn toàn nút "Scan Bootstrap Assets" thừa để tối ưu hiệu năng và UX. Hiện tại toolbar chuẩn hóa duy nhất 2 nút bấm thao tác:
  1. **Upload / Add Asset Files**: Nút nhúng cửa sổ chọn file native OS (Windows/macOS), hỗ trợ chọn và tải lên hàng loạt các định dạng file `.css`, `.js`, `.woff2`, `.woff`, `.ttf`, `.otf`, `.eot` trực tiếp vào thư mục theme con (`get_stylesheet_directory() . '/assets/'` - `/wp-content/themes/align-child/assets/`) qua AJAX endpoint `align_upload_asset_file`.
  2. **Add Manual Row**: Nút chèn thêm dòng tài nguyên thủ công để người dùng tự nhập đường dẫn URL tùy chỉnh hoặc CDN từ bên ngoài. Hàng thủ công khi chưa có URL sẽ bị xóa ngay trên DOM mà không gửi AJAX request thừa.
- **Tự Động Migrate Legacy Assets (`admin_init`)**:
  Hệ thống tích hợp hàm `Align_Admin_Assets::migrate_legacy_assets()` tự động phát hiện các file cũ trong `/wp-content/uploads/align-assets/` (ví dụ `audit_48px_buttons.js`, `script.js`, `styles.css`), sao chép sang `/wp-content/themes/align-child/assets/` và cập nhật bản ghi trong `align_loaded_assets`.
- **Đồng Bộ Đầy Đủ CMS Editor & Visual Builder**:
  - **Gutenberg Block Editor & FSE Site Editor**: Hook `enqueue_block_editor_assets` nạp toàn bộ file CSS tùy chỉnh, inject `@font-face` cho font tải lên, và inline Header Custom CSS vào `wp-block-editor` và `wp-edit-blocks`. Tuyệt đối chặn JavaScript trong editor context để bảo vệ React state.
  - **Visual Builder Live Canvas Preview**: `Align_Builder_Content_Handler::get_page_content()` nạp Font Preloads TOP 1, Theme `style.css` (Parent & Child), cùng toàn bộ file CSS/JS từ tab **Load Asset** (`align_loaded_assets`) và **Custom Code** (có gắn cờ `data-align-preview="1"` để tự động bóc tách sạch sẽ khi lưu).
- **Font Preload Support & Cơ Chế Nạp Ưu Tiên TOP 1 (`wp_head`, priority 1)**:
  - Tự động nhận diện và xuất các thẻ Font Preload `<link rel="preload" href="..." as="font" type="font/..." crossorigin>` được nạp ở hook `wp_head` với **Priority 1 (siêu ưu tiên)**.
  - Vị trí này giúp phông chữ nằm ở **DÒNG ĐẦU TIÊN** của thẻ `<head>`, vượt lên trước tất cả các file stylesheet CSS, tracking script, canonical link..., từ đó giúp trình duyệt tìm thấy và tải phông chữ tức thì, triệt tiêu hoàn toàn hiện tượng nhấp nháy chữ (**FOUT/FOIT**) và bảo vệ chỉ số **LCP (Largest Contentful Paint)**.
  - Minh họa đăng ký hook thực thi:
    ```php
    add_action('wp_head', [__CLASS__, 'output_font_preloads_top'], 1);
    ```
- **Dynamic Relative URL Storage & Resolution**: Đường dẫn file được lưu dưới dạng tương đối (ví dụ: `/wp-content/themes/align-child/assets/font.woff2`) giúp dễ dàng di chuyển website giữa các tên miền/môi trường mà không bị cứng URL. Khi render ngoài frontend, hệ thống tự động bổ sung domain động qua `site_url()`.
- **Bộ Lọc 3 Loại Asset (Font, CSS, JS & All) & Dynamic Counter Badges**:
  - Thanh tab filter thông minh đặt ngay đầu bảng cho phép lọc nhanh giữa 4 chế độ: **All**, **CSS**, **JS**, **Font**.
  - Tự động đếm và cập nhật số lượng asset thực tế theo thời gian thực trên từng badge (ví dụ: `CSS (3)`, `JS (2)`, `Font (1)`).
  - Khi đang ở chế độ lọc, các dòng khác loại chỉ ẩn trong DOM (`display: none`) mà không bị xóa, đảm bảo 100% dữ liệu được bảo toàn nguyên vẹn khi bấm Save Changes.
  - Khi đang ở tab lọc (ví dụ `Font`), bấm **Add Manual Row** sẽ tự động chọn Type là `Font`.
- **Kéo Thả Sắp Xếp Vị Trí Nạp Tài Nguyên (Drag & Drop Reordering)**:
  - Cột đầu tiên trang bị icon Drag Handle (`dashicons-menu`) hỗ trợ kéo thả trực quan theo chuẩn **HTML5 Drag & Drop**.
  - Hiển thị hiệu ứng mờ dòng đang kéo (`.align-asset-dragging`) và đường kẻ chỉ dẫn drop phía trên/dưới (`.align-drop-above`, `.align-drop-below`).
  - Hệ thống tự động kích hoạt `reindexRows()` đánh lại số thứ tự `align_loaded_assets[i]` theo đúng thứ tự DOM thực tế, giúp tùy biến chính xác thứ tự nhúng CSS/JS/Font ngoài frontend `<head>` và `<footer>`.
- **Cấu hình Từng Dòng Asset**:
  - **File Name (Typography-First Display & Borderless Text)**:
    - Thay vì hiển thị thẻ input text cồng kềnh có viền xám đục, hệ thống trích xuất tên file ngắn gọn (`main.css`, `script.js`, `inter.woff2`...) qua `align_get_asset_filename()` (PHP) và `getFilenameFromUrl()` (JS) để hiển thị dạng text thuần phẳng không viền (`span.align-asset-filename`, `font-size: 13px`, `font-weight: 500`).
    - Thuộc tính `title="${url}"` hiển thị tooltip native với đầy đủ URL gốc khi hover chuột.
    - Đường dẫn gốc đầy đủ được bảo toàn trong thẻ `<input type="hidden" class="align-asset-url-input">` để đảm bảo 100% dữ liệu form được submit lên `options.php` mà không bị gián đoạn hay sai lệch.
  - **Type (Clean Text Badge & Borderless)**:
    - Hiển thị dạng text in hoa tối giản (`CSS`, `JS`, `FONT`) qua `span.align-asset-type-text`, loại bỏ hoàn toàn khung chọn `<select>` có viền của WordPress.
    - Loại asset được tự động nhận diện qua hàm JavaScript `detectFileType(url)` khi upload file hoặc nhập URL.
    - Giá trị type được lưu trữ ngầm qua thẻ `<input type="hidden" class="align-asset-type-select">` phục vụ form submit và bộ đếm badge bộ lọc.
  - **Location**: Vị trí chèn **Header (`wp_head`)** hoặc **Footer (`wp_footer`)**. Hỗ trợ lưu trữ bền vững (persistence) cả 2 vị trí cho cả CSS và JS. Khi chọn loại `Font`, hệ thống cố định vị trí là Header do yêu cầu kỹ thuật của thẻ preload.
  - **Loading Strategy**:
    - **Đối với JS**: Hỗ trợ 4 chế độ: **Normal (Sync)**, **Defer (`defer`)**, **Async (`async`)**, và **Delay (`data-delay="true"`)**.
    - **Đối với CSS**: Hỗ trợ 2 chế độ: **Normal (Sync)** và **Delay (`data-delay="true"`)**.
    - **Đối với Font**: Luôn khóa ở chế độ Normal để chống layout shift / font flashing.
    - **Ẩn/Hiện Thông Minh Theo Toggle Delay**: Khi tính năng Delay JS & CSS bị tắt trong tab Plugins, tùy chọn `Delay` sẽ tự động ẩn khỏi dropdown và mọi asset đang delay sẽ tự động fallback về `Defer` (với JS) hoặc `Normal` (với CSS).
  - **Is Inline Support (`is_inline`)**:
    - Checkbox chuyên biệt cho phép nhúng trực tiếp nội dung file CSS/JS vào DOM HTML (`<style id="align-inline-...">` hoặc `<script id="align-inline-...">`) thay vì phát sinh HTTP network request tải file.
    - Phù hợp tối ưu Core Web Vitals (CWV) cho các đoạn stylesheet nhỏ hoặc script critical.
    - Tự động khóa và bỏ chọn nếu dòng tài nguyên là `Font`.
  - **Core Plugin Asset Integration**: Các file thư viện core đang kích hoạt (`lenis.min.js`, `splide.min.css`, `splide.min.js`, `splide-extension-auto-scroll.min.js`) tự động xuất hiện trong bảng Load Asset với ô URL readonly và icon ổ khóa 🔒. Người dùng có thể tùy biến Location, Strategy và sắp xếp thứ tự nạp cho các file này độc lập.
  - **Tách Mã Nguồn Tinh Gọn (Modular Architecture)**:
    - Tách mã JavaScript thành file riêng [`align/core/includes/admin/views/tab-load-assets.js`](../../core/includes/admin/views/tab-load-assets.js) (~484 dòng) và view PHP [`align/core/includes/admin/views/tab-load-assets.php`](../../core/includes/admin/views/tab-load-assets.php) (~360 dòng), đảm bảo 100% tuân thủ tiêu chuẩn **< 500 dòng/file**.
- **Tích Hợp Nút "Edit in Theme File Editor" Trực Tiếp Cạnh Ô URL (Mở Tab Mới)**:
  - Tự động bóc tách đường dẫn tương đối và slug theme (`align-child` / `align`) để sinh URL mở trực tiếp trong WordPress Theme File Editor: `admin_url('theme-editor.php?file=' . urlencode($rel_file) . '&theme=' . urlencode($theme_slug))`.
  - Nút **`[ ✏️ ]` Edit** (`dashicons-edit`, `target="_blank"`) được đặt liền kề với ô File URL input và nút Upload `📤`, tạo thành một cụm điều khiển trực quan và đồng nhất.
  - **Bộ lọc thông minh**: Nút Edit hiển thị cho toàn bộ các file CSS/JS tùy biến trong Child Theme (`align-child/assets/`), và tự động ẩn đi đối với:
    - Các file Font nhị phân (`.woff`, `.woff2`, `.ttf`, `.otf`, `.eot`).
    - Các liên kết CDN bên ngoài (`https://fonts.googleapis.com/...`).
    - Các file thư viện plugin lõi cố định của Theme Core (`/align/core/assets/plugins/...` / `is_core = 1`).
  - **Cột Action Thống Nhất 100%**: Cột Action chỉ chứa duy nhất 1 icon (`🗑️` Xóa hàng hoặc `🔒` Khóa Core) căn giữa thẳng hàng tuyệt đối, loại bỏ hoàn toàn hiện tượng lệch hàng hay zic-zac.
- **Cơ Chế Kháng Lỗi API Đám Mây (Cloud Microservice Resilience - Option A)**:
  - **Triết lý Zero-Corruption**: Khi dịch vụ chuyển đổi đám mây (Align Cloud Microservice tại `convertb.alignvn.cloud`) gặp sự cố mạng, timeout hoặc shutdown, hệ thống TUYỆT ĐỐI KHÔNG fallback qua regex PHP hay thư viện GD cục bộ làm suy giảm chất lượng ảnh hoặc làm biến dạng, hư hỏng mã nguồn CSS/JS/HTML.
  - **WebP Converter**: Bỏ qua chuyển đổi, giữ nguyên 100% file ảnh gốc JPG/PNG.
  - **Minifier Engine**: Bỏ qua nén mã nguồn, trả về nguyên bản nội dung CSS/JS.
  - **HTML Block Converter**: Bỏ qua phân tách block phức tạp, tự động bọc nội dung dán vào khối chuẩn `core/html`.
  - **Endpoint Health Tester**: Báo cáo lỗi chi tiết nhưng không gây gián đoạn hay crash bất kỳ luồng hoạt động nào của website.

### 2.6 Unified 1-Step Font Pipeline & Dynamic Global Typography (`class-align-font-manager.php`, `class-align-admin-assets.php`, `tab-load-assets.php` & `tab-load-assets.js`)
Quản lý phông chữ toàn trang tinh gọn qua quy trình 1 bước duy nhất tại tab **Load Asset**, tự động gom nhóm font, sinh mã `@font-face` kép, đồng bộ biến CSS toàn cục và áp dụng font tự động toàn website:
- **Quy Trình 1 Bước Duy Nhất (1-Step Font Pipeline in Asset Loader)**:
  - Loại bỏ hoàn toàn sự phân mảnh giữa tab Font Manager và tab Load Asset. Người dùng chỉ cần tải file font (`.woff2`, `.woff`, `.ttf`, `.otf`, `.eot`) lên trực tiếp tại bảng **Load Asset**.
  - Hệ thống tự động nhận diện loại file (`type: font`), cố định vị trí nạp tại `Header (wp_head)` với chiến lược `Normal` (đồng bộ nạp sớm nhất) và vô hiệu hóa checkbox inline.
  - Dropdown chọn độ dày font (`font_weight`) từ 100 đến 900 (`Thin 100` -> `Black 900`), tự động nhận diện thông minh từ tên file (ưu tiên `semibold`/`600` trước `bold`/`700` để chống nhận diện nhầm).
  - Chip hiển thị tên slot kèm tính năng 1-click copy: Click vào badge `font-primary-1` để sao chép nhanh class CSS `.ff-1` hoặc biến `var(--font-primary-1)`.
  - Lược bỏ tab con "Typography & Fonts" thừa trong Master System Settings, tập trung quản trị font tại duy nhất 1 bảng Asset Loader.
- **Tự Động Gom Nhóm & Định Danh Chuẩn Hóa (`font-primary-1..n`)**:
  - Khi bấm **Save Changes** trong Load Asset, hàm `Align_Font_Manager::compile_from_loaded_assets()` tự động trích xuất các asset loại font, phân tích tên file (`detect_family_from_filename`) để gom nhóm các biến thể trọng số về cùng một họ font.
  - Tự động gán các slot theo định dạng chuẩn hóa:
    - Slot 1: Họ font đầu tiên &rarr; `font-primary-1` (CSS variable `--font-primary-1`, alias tương thích ngược `--font-family-1`).
    - Slot 2: Họ font thứ hai &rarr; `font-primary-2` (CSS variable `--font-primary-2`, alias `--font-family-2`).
    - Slot n: `font-primary-n` tương ứng.
- **Cơ Chế Khai Báo Dual `@font-face` (Bảo Toàn Tương Thích Tuyệt Đối)**:
  - Tự động sinh đồng thời 2 khối `@font-face` cho mỗi file font: một khối với `font-family: 'font-primary-X'` và một khối với `font-family: '{OriginalFontName}'` (ví dụ `Barlow`).
  - Đảm bảo cả hai cách gọi font (qua class chuẩn `.font-primary-1` / `.ff-1` hoặc qua tên font trực tiếp `font-family: 'Barlow'`) đều hoạt động chính xác 100%.
- **Ghi Đè Toàn Cục & Tự Động Áp Dụng Toàn Website (Bootstrap Override & Global Base Typography)**:
  - **Triệt tiêu lỗi font system-ui của Bootstrap Reboot**: `bootstrap-reboot.min.css` mặc định gán `body { font-family: var(--bs-body-font-family); }` trỏ về chuỗi system-ui. Hệ thống tự động bổ sung trong `:root`:
    ```css
    --bs-body-font-family: var(--font-primary-1, '{OriginalFontName}', sans-serif);
    ```
  - **Global Base Typography Rule**: Khai báo quy tắc toàn cục tự động chèn vào đầu trang:
    ```css
    html, body, h1, h2, h3, h4, h5, h6, .heading-text {
      font-family: var(--font-primary-1, '{OriginalFontName}', sans-serif);
    }
    ```
  - Mọi thẻ tiêu đề (`h1` - `h6`, `.heading-text`), đoạn văn bản (`p`, `span`), form input và button đều tự động hiển thị đúng phông chữ chính của website mà không cần phải gán class tiện ích `.ff-1` thủ công vào từng thẻ.
- **Chuyển Đổi Đường Dẫn Tương Đối Thành Tuyệt Đối Khi Inline CSS (Fix 404 Font)**:
  - Khi một file CSS chứa đường dẫn `@font-face` tương đối (ví dụ `url('fonts/Barlow.woff')`) được nhúng nội dòng (`is_inline = 1`), trình duyệt sẽ phân giải URL tương đối dựa trên URL của trang hiện tại thay vì vị trí file CSS, dẫn đến lỗi 404 Not Found.
  - Trong `Align_Custom_CSS_JS::output_custom_assets()`, hệ thống trang bị bộ phân giải regex URL thông minh: tự động chuyển đổi toàn bộ `url(...)` tương đối thành đường dẫn tuyệt đối chuẩn xác dựa trên đường dẫn gốc của file CSS trước khi in ra `<style id="align-inline-...">`.
- **Chuẩn Hóa Preload URL Tuyệt Đối (Loại Bỏ Query String Mismatch)**:
  - Thẻ `<link rel="preload" as="font" crossorigin>` và khai báo `src: url(...)` trong `@font-face` đều sử dụng chung hàm giải quyết URL sạch (`resolve_font_url`, `$with_version = false`), loại bỏ query string `?ver=...`.
  - Đảm bảo trình duyệt Chrome nhận diện đúng 100% tài nguyên đã preload, triệt tiêu hoàn toàn lỗi Chrome warning `"was preloaded using link preload but not used within a few seconds"` và ngăn tải lặp 2 lần gây lãng phí băng thông.
- **Đồng Bộ CMS Block Editor & Visual Builder**:
  - Tự động nạp CSS font vào Gutenberg Block Editor (`wp_add_inline_style('wp-block-editor', ...)`) để khi biên tập bài viết trong admin, văn bản hiển thị chuẩn xác phông chữ đã chọn.

### 2.7 Plugins & Enhancements (`core/includes/core/class-align-smooth-scroll.php` & `tab-plugins.php`)
Quản lý các thư viện mở rộng và tối ưu hóa trải nghiệm người dùng / hiệu năng:
- **Smooth Scroll (Lenis Scroll)** (`align_enable_lenis_scroll`):
  - Tự động nạp thư viện **Lenis v1.1.18** (`core/assets/plugins/smooth-scroll/lenis.min.js`) kèm CSS chuẩn và khởi tạo toàn cục `window.lenis = new Lenis({ autoRaf: true, smoothWheel: true })`.
  - Hỗ trợ đầy đủ các API điều khiển cuộn: `window.lenis.scrollTo()`, `window.lenis.stop()` / `window.lenis.start()` khi mở popup/modal, `data-lenis-prevent` cho container con có thanh cuộn riêng, và tương thích hoàn toàn với GSAP ScrollTrigger.
- **Splide.js Slider Library** (`align_enable_splidejs`):
  - Tự động nạp toàn bộ thư viện Splide.js v4.1.4 (CSS & JS) cùng tiện ích Auto-scroll extension trên toàn bộ trang ngoài frontend, giúp lập trình viên thoải mái khởi tạo slider/carousel ở bất kỳ template hoặc shortcode nào mà không phụ thuộc block.
- **Delay JS & CSS Optimization** (`align_enable_delay_assets` & `align_delay_custom_urls`):
  - Trì hoãn việc tải và thực thi JavaScript & CSS không thiết yếu thông qua thuộc tính `data-delay="true"` (hoán đổi `data-src` / `data-href`) cho đến khi có tương tác đầu tiên của người dùng (`scroll`, `touchstart`, `mousemove`, `keydown`, `click`) hoặc sau 4 giây fallback.
  - **Tự Động Đồng Bộ Toàn Bộ Assets (Auto-Sync Lifecycle)**: Khi bật "Enable Delay JS & CSS Loading", hệ thống tự động chuyển toàn bộ file core và custom upload sang chiến lược `Delay` mặc định. Khi tắt, hệ thống tự động đưa về `Defer` / `Normal`.
  - **Custom Delay URLs Textarea**: Cho phép khai báo danh sách URL, đường dẫn thư mục plugin, hoặc từ khóa file (mỗi dòng một giá trị). Engine sẽ tự động quét và gán `data-delay="true"` cho toàn bộ script/style trùng khớp.
  - Tự động áp dụng delay cho khách vãng lai và tài khoản Subscriber để tối đa hóa tốc độ tải trang, trong khi tự động bỏ qua (bypass) đối với các role quản trị và biên tập (Administrator, Editor, Author, Contributor...).

### 2.8 Git Updater & Push-to-Deploy Engine (`core/includes/admin/class-align-git-updater.php` & `tab-git-updater.php`)
Hệ thống kéo mã nguồn cập nhật trực tiếp Align Theme Core từ GitHub Repository theo tiêu chuẩn WordPress Core Upgrader & GitHub REST API:
- **Tải Trực Tiếp Zipball Từ GitHub API**: Sử dụng endpoint `/repos/{owner}/{repo}/zipball/{branch}` để tải file archive mã nguồn mà không yêu cầu máy chủ hosting cài đặt Git CLI.
- **Hỗ Trợ Private Repository**: Tích hợp Personal Access Token (PAT) kèm header `Authorization: token {PAT}` để pull code an toàn từ kho lưu trữ riêng tư.
- **Giải Nén & Ghi Đè Trực Tiếp Vào Theme Core**:
  - Đường dẫn đích (Destination) được gán cố định tự động vào thư mục gốc của theme (`get_template_directory()` - `/wp-content/themes/align/`).
  - Tự động bóc tách thư mục root tạo bởi GitHub (`owner-repo-sha/`) và giải nén đè trực tiếp vào Theme Core bằng `WP_Filesystem`.
- **WordPress Maintenance Mode & Clean Replace**: Tự động bật `.maintenance` và backup an toàn trước khi xóa sạch và thay thế toàn bộ file theme.
- **Console Log Trực Quan**: Bảng log trạng thái hiển thị chi tiết từng bước trên giao diện Dark Terminal.
- *Xem tài liệu kỹ thuật chuyên sâu tại*: [**`git-updater.md`**](./git-updater.md).

### 2.9 HTML Project ZIP Importer Engine (`core/includes/admin/importer/` & `tab-import-zip.php`)
Hệ thống tự động hóa chuyển đổi file ZIP HTML project thành các trang WordPress, Template Parts và Reusable Blocks (`wp_block`) hoàn chỉnh:
- **Auto-Discovery**: Tự động phát hiện thư mục tài nguyên chung `assets/`, Global Header (`header/` -> `parts/header.html`), Global Footer (`footer/` -> `parts/footer.html`), Reusable Blocks (`reuse-blocks/` -> `wp_block`), và các thư mục trang `[folder-page-name]/`.
- **Reusable Block Synced Patterns & Shortcodes**: Tự động tạo post `wp_block`, lưu scoped CSS/JS độc lập tại `assets/builder/style-block-{slug}.css` và sinh mã shortcode `[reuse-block id="..."]` để nhúng linh hoạt.
- **Media Sideload, WebP Conversion & Dummy Fallback**: Tự động chuyển WebP qua Cloud API, thay thế các hình ảnh/video thiếu bằng Dummy Image Placeholder (SVG/PNG) bảo toàn layout, đồng thời bảo vệ nghiêm ngặt các thẻ `<script src="...">` không bị nhầm lẫn.
- **Nested Assets & jQuery Priority**: Nhận diện các thư mục thư viện lồng sâu (`lib/`, `mail/`, `vendor/`), tự động gán jQuery nạp đồng bộ ở Header (`location: header`, `strategy: normal`) để triệt tiêu lỗi `jQuery is not defined`.
- **CDN & Custom Asset Integration**: Tự động bóc tách các stylesheet/script CDN từ project và đăng ký vào tab **Load Asset**.
- **Pre-Import Trigger & Starter Template Download**: Hỗ trợ dọn dẹp sạch toàn bộ dữ liệu site trước khi import mới và cung cấp nút **Download Sample Template (.zip)** để dev tải ngay boilerplate chuẩn về phát triển.
- *Xem tài liệu kỹ thuật chuyên sâu tại*: [**`html-zip-importer.md`**](./html-zip-importer.md).

---

## 3. Bảng Tham Chiếu Cấu Hình Settings Option Keys

> [!IMPORTANT]
> Toàn bộ thiết lập của Align Settings được lưu trữ trong bảng `wp_options` với các option keys chuyên biệt bên dưới.

| Tên Thiết Lập | Option Key / Field Name | Giá Trị Mặc Định | Mô Tả |
| :--- | :--- | :--- | :--- |
| **AI Provider Preset** | `align_ai_provider` | `'openrouter'` | Nhà cung cấp AI (`openrouter`, `openai`, `custom`) |
| **AI Base URL** | `align_ai_base_url` | `https://openrouter.ai/api/v1` | Endpoint baseURL theo chuẩn OpenAI SDK |
| **AI API Key** | `align_ai_api_key` | `''` | Bearer Token API Key xác thực cho OpenAI / OpenRouter |
| **AI Default Model** | `align_ai_model` | `'google/gemini-2.5-flash'` | Model LLM mặc định cho AI Refactor & Block Studio |
| **AI Organization ID** | `align_ai_organization` | `''` | Organization ID tùy chọn cho OpenAI (`OpenAI-Organization`) |
| **API Converter URL** | `align_minify_api_url` | `https://convertb.alignvn.cloud` | Endpoint URL biên dịch SCSS / Block / WebP Converter |
| **External Builder Assets** | `align_builder_external_assets` | `0` (Disabled) | Lưu trữ CSS/JS Visual Builder ra file tĩnh ngoài đĩa |
| **Bật Auto WebP** | `align_webp_enable_auto_convert` | `1` (Enabled) | Tự động chuyển ảnh upload sang `.webp` trực tiếp |
| **WebP Quality** | `align_webp_quality` | `80` | Chất lượng nén ảnh WebP (1-100) |
| **Bật HTML Minify** | `align_enable_html_minify` | `0` (Disabled) | Nén HTML output ngoài frontend |
| **Header CSS** | `align_custom_css` | `''` | Mã CSS tùy chỉnh chèn vào `<head>` (chấp nhận pure CSS hoặc có thẻ `<style>`) |
| **Custom JS** | `align_custom_js` | `''` | Mã JavaScript tùy chỉnh chèn trước `</body>` (chấp nhận pure JS hoặc script thô có thẻ `<script>`) |
| **Tracking Code** | `align_tracking_code` | `''` | Mã HTML/JS theo dõi (Google Analytics, Pixel) chèn vào `<head>` (ưu tiên 1) |
| **Body Open Code** | `align_body_open_code` | `''` | Mã HTML/JS chèn ngay sau `<body>` (`wp_body_open`, ưu tiên 1) |
| **Footer HTML** | `align_footer_html_code` | `''` | Mã HTML/JS chèn trước `</body>` (`wp_footer`, ưu tiên 100) |
| **Skip for Admins** | `align_custom_code_skip_admins` | `0` (Disabled) | Bỏ qua xuất Tracking, Body Open và Footer HTML cho quản trị viên đăng nhập |
| **Kích Thước Ảnh Tùy Chỉnh** | `align_custom_image_sizes` | `array()` | Mảng chứa thông số slug, width, height, crop |
| **Danh Sách File Asset Loaded** | `align_loaded_assets` | `array()` | Mảng danh sách file CSS, JS và Font Preload TOP 1 (`wp_head`, priority 1) động nạp ngoài Frontend |
| **Bật Lenis Smooth Scroll** | `align_enable_lenis_scroll` | `0` (Disabled) | Kích hoạt cuộn mượt Lenis Scroll v1.1.18 trên frontend |
| **Bật Splide.js Global** | `align_enable_splidejs` | `0` (Disabled) | Nạp thư viện Splide.js & Auto-scroll extension toàn trang |
| **Bật Delay JS, CSS** | `align_enable_delay_assets` | `0` (Disabled) | Trì hoãn nạp JS/CSS (`data-delay="true"`) cho đến khi có tương tác |
| **Custom Delay URLs** | `align_delay_custom_urls` | `''` | Danh sách URL/path plugin tùy chỉnh cần trì hoãn nạp JS/CSS |
| **GitHub Repository** | `align_git_repository` | `''` | Handle repository GitHub (`owner/repo`) |
| **Git Branch** | `align_git_branch` | `'main'` | Tên nhánh Git để pull code |
| **GitHub Access Token** | `align_git_token` | `''` | Personal Access Token cho Private Repository |
| **Bật Push-to-Deploy Webhook** | `align_git_webhook_enabled` | `0` | Cho phép cập nhật tự động qua Webhook |
| **Webhook Secret Token** | `align_git_webhook_token` | `''` | Chuỗi token bảo mật ngẫu nhiên cho Webhook |
| **Site Protection Live Domain** | `align_site_protect_live_domain` | `''` | Tên miền Production chính (tự động tắt bảo vệ & no-index) |
| **Site Protection Staging Domain** | `align_site_protect_staging_domain` | `''` | Tên miền Staging/Preview (tự động bật bảo vệ & no-index) |
| **Bật Site Protection** | `align_site_protect_enabled` | `0` (Disabled) | Kích hoạt bảo vệ mật khẩu toàn site qua HTTP Basic Auth |
| **Site Protection Username** | `align_site_protect_user` | `'staging'` | Tên đăng nhập cho popup xác thực Basic Auth |
| **Site Protection Password** | `align_site_protect_password` | `''` | Mật khẩu xác thực truy cập website |
| **Site Protection No-Index** | `align_site_protect_auto_noindex` | `1` (Enabled) | Tự động no-index toàn site (Headers, Meta, Robots.txt) |
| **Site Protection Bypass Local** | `align_site_protect_bypass_localhost` | `1` (Enabled) | Tự động bỏ qua bảo vệ trên môi trường Localhost/Dev |
| **Site Protection Bypass Admins** | `align_site_protect_bypass_admins` | `1` (Enabled) | Tự động bỏ qua cho tài khoản quản trị WP đã đăng nhập |
| **Site Protection Cookie Days** | `align_site_protect_cookie_days` | `30` | Thời hạn lưu token ghi nhớ phiên truy cập (ngày) |

---

## 4. Hướng Dẫn Thao Tác Thêm Custom Code Mới

1. Truy cập **Align Settings** -> chọn tab **Master System** -> subtab **Custom Code & Scripts**.
2. Nhập mã CSS tùy chỉnh tại ô **Header CSS** (chấp nhận CSS thuần hoặc cả thẻ `<style>...</style>`, thẻ `<link>`).
3. Nhập mã JavaScript tại ô **JS** (chấp nhận JavaScript thuần hoặc cả thẻ `<script>...</script>`, `<script src="...">`).
4. Nhập mã nhúng theo dõi (Google Analytics, Meta Pixel...) tại ô **Tracking Code** (chèn vào `<head>`).
5. Nhập mã nhúng ngay sau thẻ mở body (GTM noscript...) tại ô **Body Open Code**.
6. Nhập mã nhúng ngay trước thẻ đóng body (Chat widget, popup...) tại ô **Footer HTML**.
7. Bật tùy chọn **Skip for Administrators** nếu muốn loại trừ admin khỏi các thống kê analytics.
8. Toàn bộ mã được bảo tồn thô nguyên bản (không bị nén/minify gây lỗi mã bên thứ ba) và được hỗ trợ editor CodeMirror.
9. Nhấn **Save Changes** ở cuối trang để lưu cấu hình.
