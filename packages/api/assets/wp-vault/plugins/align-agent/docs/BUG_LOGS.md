# 🐞 Align Agent — Bug Logs & Prevention Memory

Tài liệu lưu vết lỗi kỹ thuật, nguyên nhân gốc rễ và quy tắc phòng ngừa lỗi cho plugin Align Agent (`wp-content/plugins/Align-Agent/`).

---

## [BUG-20261024-0950] Lỗi Thay Ảnh Bỏ Sót Thuộc Tính Cha (`data-cursor-img`), Lưu URL Tuyệt Đối Chứa Domain Local, Clone Bản Dịch Nguyên Văn Có Hậu Tố `[LANG]` & Lọc Sai Ngôn Ngữ Bài Viết

- **Mã định danh lỗi**: `BUG-20261024-0950`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent-main/`
- **Tệp tin ảnh hưởng**:
  - `includes/tools/media/class-agent-tools-media.php:43-1190`
  - `includes/tools/i18n/class-agent-tools-i18n.php:48-446`
  - `includes/tools/content/class-agent-tools-finder.php:66-402`
  - `includes/tools/content/class-agent-tools-pages.php:42-255`
  - `includes/tools/content/class-agent-tools-posts.php:46-535`
  - `includes/tools/content/class-agent-tools-patch.php:249-258`
  - `includes/class-gateway-pages.php:58-158`
  - `includes/class-agent-loop.php:68-103`
  - `includes/class-agent-mcp-adapter.php:54-120`
- **Triệu chứng & Bằng chứng log**:
  1. Khi thay ảnh mục nhân sự `"Sa Ngo Tinh"` trên trang `About Us` (`#4733`), thẻ `<picture>/<img>` đổi sang `/wp-content/uploads/telegram-photo-1780.webp` nhưng thẻ cha `<li class="mem" data-cursor-img="/wp-content/uploads/samuel.webp">` vẫn giữ ảnh cũ `samuel.webp`.
  2. Các công cụ trả về và lưu URL tuyệt đối chứa domain cục bộ (`http://trusted-union.local/...`) thay vì Dynamic Root-Relative URL (`/wp-content/uploads/...`, `/slug/`).
  3. Khi dịch trang qua `create_translation`, bản dịch mới giữ nguyên nội dung tiếng Anh, tiêu đề dính hậu tố `[JA]` (`Course 001 [JA]`), hoặc khi sửa tiêu đề sang chữ Nhật (`コース 001`) thì URL slug bị đổi thành `%e3%82%b3%e3%83%bc%e3%82%b9-001`.
  4. Khi tìm/xóa bài viết theo ngôn ngữ (`"xóa các bài tiếng Nhật"`), LLM nhét tên ngôn ngữ vào tham số từ khóa `search`/`s` thay vì lọc theo meta `_align_lang`.

### Nguyên Nhân Gốc Rễ (Root Cause)
1. **Thiếu công cụ thay ảnh chuyên biệt có đồng bộ container bao ngoài**: Trước đây không có `list_page_images` và `replace_page_image`; khi thay `<picture>/<img>`, các thuộc tính ảnh trên phần tử cha bao ngoài (`data-cursor-img`, `data-hover-img`, `data-bg`, `style="background-image:url(...)"`) không được quét và cập nhật đồng bộ.
2. **Sử dụng trực tiếp `get_permalink()` và `wp_get_attachment_url()` nguyên bản**: Các hàm WordPress trả về URL tuyệt đối gắn cứng domain môi trường hiện tại (`http://trusted-union.local`), gây lỗi ảnh và link khi di chuyển môi trường.
3. **`create_translation` chỉ clone bản nháp thô và `rename_item` ghi đè `post_name` theo `title`**: `Align_Language_Manager::create_translation()` tạo bản sao gắn `[JA]` vào tiêu đề mà không nhận tham số `title`/`html` đã dịch ngay trong 1 bước; đồng thời `Align_Builder_Crud_Handler::rename_item()` tự động gọi `sanitize_title($new_title)` làm mất slug ASCII chuẩn SEO (`<source-slug>-<lang>`).
4. **Các công cụ đọc danh sách (`list_pages`, `list_posts`, `find_content`) thiếu tham số `lang` và kiểm chứng mã ngôn ngữ**: Không có bộ lọc `meta_query` theo `_align_lang` và thiếu hàm kiểm tra mã ngôn ngữ hợp lệ đối chiếu trực tiếp với `Align_Language_Manager::get_languages()`.

### Giải Pháp Đã Áp Dụng
1. **Xây dựng bộ 4 SRP Media Tools & Đồng bộ Enclosing Container (`class-agent-tools-media.php`)**:
   - Bổ sung `upload_media` (nhận `base64_data` hoặc `url`, chống trùng qua `_align_tg_file_unique_id`, kích hoạt `media_handle_sideload()` để chạy `Align_Media_Normalizer` + `Align_Image_Sizes` + `Align_WebP`).
   - Bổ sung `list_page_images` và `replace_page_image` tích hợp `Align_Builder_Image_Resolver::resolve_picture_tag()`, hỗ trợ `mobile_size`/`tablet_size`/`desktop_size`, phát hiện `related_attributes` và tự động đồng bộ `data-cursor-img`, `data-hover-img`, `data-bg`, `style:background-image` trong cùng container bao ngoài (`synced_attributes`).
2. **Chuẩn hóa 100% Dynamic Root-Relative URL**:
   - Thêm `Align_Agent_Tools_Media::to_root_relative_url()` và `Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic()`, áp dụng xuyên suốt trên toàn bộ công cụ Media, Pages, Posts, Finder, Patch và i18n.
3. **Nâng cấp Atomic `create_translation` & Bảo toàn SEO Slug (`class-agent-tools-i18n.php`, `class-agent-tools-pages.php`)**:
   - Cho phép truyền `title`, `html`, `status` ngay trong `create_translation`, tự động cắt hậu tố `[LANG]`, khóa cứng SEO slug `<source-slug>-<target_lang>`, và trả cờ `translation_completed` để ép LLM dịch hoàn tất nếu gọi thiếu tham số.
   - Bảo toàn `post_name` ASCII hiện có trong `save_page` khi đổi `title` sang ngôn ngữ khác, trừ khi truyền tham số `slug` tường minh.
4. **Lọc ngôn ngữ chuẩn Agentic (`validate_site_lang_code` & `build_lang_meta_query`)**:
   - Thêm tham số `lang` vào `list_pages`, `list_posts`, `find_content` với cơ chế kiểm tra trực tiếp từ `Align_Language_Manager::get_languages()` (không hardcode danh sách ngôn ngữ).

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **CẤM**: Tuyệt đối không lưu hoặc trả về URL tuyệt đối chứa `http://` / `https://` của domain nội bộ trong HTML, CSS hoặc kết quả tool; luôn dùng Dynamic Root-Relative URL bắt đầu bằng `/`.
- **CẤM**: Tuyệt đối không hardcode bảng từ điển ngôn ngữ; luôn đối chiếu động qua `Align_Language_Manager::get_languages()`.
- **NÊN**: Khi thay thế ảnh trên trang bằng `replace_page_image`, luôn kiểm tra và đồng bộ các thuộc tính ảnh (`data-cursor-img`, `data-bg`, `background-image`) trên thẻ mở của phần tử container bao ngoài.

---

## [BUG-20261021-1725] Lỗi Bitwise Định Tuyến REST Server Trả 404 & Thiếu Cập Nhật Tiêu Đề Khi Revert Trang

- **Mã định danh lỗi**: `BUG-20261021-1725`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent/`
- **Tệp tin ảnh hưởng**:
  - `includes/class-gateway-router.php:44`
  - `includes/class-agent-undo-adapters.php:301-325`
- **Triệu chứng & Bằng chứng log**:
  - Gọi endpoint `/align-agent/v1/ping` bị trả về HTTP 404: `{"code":"rest_no_route","message":"No route was found matching the URL and request method."}` dù route đã đăng ký trong `rest_get_server()`.
  - Khi thực hiện `Align_Agent_Undo_Service::undo()` trên trang bài viết (`adapter: post`), nội dung HTML/CSS/JS khôi phục thành công nhưng `post_title` trong database `wp_posts` không được hoàn tác về tiêu đề cũ.

### Nguyên Nhân Gốc Rễ (Root Cause)
1. **Toán tử bitwise trên chuỗi ký tự trong PHP**: Trong `includes/class-gateway-router.php:44`, khai báo `'methods' => WP_REST_Server::READABLE | WP_REST_Server::CREATABLE`. Do cả hai hằng số này là string (`'GET'` và `'POST'`), toán tử bitwise `|` tạo ra kết quả chuỗi biến dạng `'WOWT'` thay vì mảng phương thức hợp lệ, khiến WordPress REST Server không khớp được phương thức HTTP `GET` hoặc `POST`.
2. **`save_page` của Builder không cập nhật trường tiêu đề**: Khi adapter `post` gọi `Align_Builder_Crud_Handler::save_page()`, handler chỉ lưu HTML, CSS, JS vào post content và files đĩa. Trường `post_title` không được cập nhật lại vào `wp_posts` nếu không gọi bổ sung `wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) )`.

### Giải Pháp Đã Áp Dụng
1. **Chuẩn hóa khai báo phương thức route**: Đổi `WP_REST_Server::READABLE | WP_REST_Server::CREATABLE` thành mảng tường minh `array( 'GET', 'POST' )` trong `includes/class-gateway-router.php:44`.
2. **Đồng bộ tiêu đề khi Revert post**: Trong `includes/class-agent-undo-adapters.php:322-326`, bổ sung lệnh `wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) )` ngay sau khi Builder lưu page, đảm bảo tiêu đề trang được phục hồi 100%.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **CẤM**: Tuyệt đối không dùng toán tử bitwise `|` với các hằng số chuỗi của `WP_REST_Server` trong PHP 8+. Luôn dùng mảng `array( 'GET', 'POST' )` hoặc chuỗi phân tách dấu phẩy.
- **NÊN**: Mọi adapter revert nội dung đối tượng WordPress bắt buộc phải khôi phục đầy đủ cả metadata định danh (`post_title`, `post_status`, `post_name`) cùng với nội dung body (`post_content`).

---

## [BUG-20261021-1545] Lỗi Webhook Trả 404 Do Tunnel Hết Hạn & Treo Tin Nhắn Telegram Khi Không Có Truy Cập Web

- **Mã định danh lỗi**: `BUG-20261021-1545`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent/`
- **Tệp tin ảnh hưởng**:
  - `includes/class-gateway-webhook.php:40-60`
  - `includes/class-gateway-telegram.php:125-140`
  - `includes/class-gateway-agent.php:42-70`
- **Triệu chứng & Bằng chứng log**:
  - Người dùng gửi tin nhắn trên Telegram nhưng bot không có bất kỳ phản hồi nào (`reply` trống).
  - Kiểm tra Telegram API `getWebhookInfo` ghi nhận: `last_error_message: "Wrong response from the webhook: 404 Not Found"`, `pending_update_count: 2`, `last_error_date: 1792572220`.
  - Phương thức `getUpdates` (polling) trả về HTTP 409 Conflict: `can't use getUpdates method while webhook is active`.

### Nguyên Nhân Gốc Rễ (Root Cause)
1. **Lưu cứng URL tunnel tạm thời vào database**: Option `align_agent_telegram_webhook_url` lưu URL cũ của LocalWP Live Link tunnel (`evanescent-base.localsite.io`). Khi tunnel hết hạn hoặc bị đóng, URL trả về HTTP 404. Telegram thử gửi tin nhắn tới webhook nhưng thất bại và giữ lại trong hàng đợi.
2. **Khóa phương thức polling khi Webhook chết**: Telegram Bot API tự động vô hiệu hóa `getUpdates` khi Webhook đang được kích hoạt. Ngay cả khi người dùng muốn chuyển sang lấy tin thủ công, yêu cầu cũng bị chặn với mã 409.
3. **Môi trường LocalWP thiếu traffic kích hoạt WP-Cron**: WordPress trên Local máy tính cá nhân là tiến trình PHP thụ động, chỉ thức dậy khi có HTTP request truy cập web. Khi người dùng nhắn tin qua Telegram mà không ai duyệt trang web, `align_agent_cron_poll` không được thực thi.

### Giải Pháp Đã Áp Dụng
1. **Xóa Webhook chết và làm sạch option**: Gọi `Align_Gateway_Webhook::delete_webhook()` và `delete_option("align_agent_telegram_webhook_url")` để giải phóng hàng đợi tin nhắn kẹt trên Telegram API.
2. **Kích hoạt Realtime Poller Daemon trên Local**: Thiết lập tiến trình daemon nền (`Align_Gateway_Agent::sync_and_process_latest()`) quét liên tục mỗi 2 giây, đảm bảo nhận tin và phản hồi tức thì mà không cần tunnel.
3. **Chuẩn hóa kiến trúc 2 chế độ**: Xác lập quy chuẩn: Môi trường Local dùng Polling Mode (không cần tunnel, không sợ 404); Môi trường Server Production dùng Webhook Mode (tên miền HTTPS thật, phản hồi 0s).

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **CẤM**: Tuyệt đối không tự động đăng ký lại Webhook với URL tunnel tạm thời nếu chưa xác thực URL đó đang trả về HTTP 200.
- **NÊN**: Luôn kiểm tra `getWebhookInfo` khi bot Telegram không phản hồi. Nếu phát hiện `last_error_message` 404/401, phải thông báo hoặc gọi `deleteWebhook` để khôi phục hàng đợi.

---

## [BUG-20261020-1700] Lỗi Xóa Mất CSS/JS Khi Cập Nhật Trang HTML-Only & Khôi Phục Revision

- **Mã định danh lỗi**: `BUG-20261020-1700`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent/`
- **Tệp tin ảnh hưởng**:
  - `includes/class-gateway-pages.php:414-428`
  - `includes/tools/content/class-agent-tools-pages.php:479-485`
  - `includes/tools/content/class-agent-tools-asset-history.php` (tạo mới)
  - `includes/tools/class-agent-tools-loader.php:33`
- **Triệu chứng & Bằng chứng log**:
  - Khi agent gọi tool `patch_content` với trường `html` hoặc gọi `restore_revision`, tệp CSS vật lý trên đĩa bị xóa hoàn toàn (`Empty CSS, removed file: .../style-page-goi-subscription.css`).
  - Trang mất toàn bộ giao diện và vỡ layout do tệp CSS rỗng bị xóa đĩa.

### Nguyên Nhân Gốc Rễ (Root Cause)
1. **Truyền CSS/JS rỗng khi lưu HTML**: Khi cập nhật trang chỉ có HTML (`patch_content`), `write_asset_files()` trong `class-gateway-pages.php` trước đây gọi `save_external_assets()` với `$css = ''` và `$js = ''`, khiến tầng lưu trữ coi là thao tác xóa tệp chủ đích và gọi `unlink()`.
2. **Kích hoạt sync asset khi revision không có metadata**: Trong `class-agent-tools-pages.php`, hàm `restore_revision` tự động gọi `sync_post_assets_from_content()` ngay cả khi revision không có CSS/JS, dẫn tới xóa các tệp asset hiện hữu.

### Giải Pháp Đã Áp Dụng
1. **Phân biệt luồng lưu Asset**: Trong `write_asset_files()`, kiểm tra flag `$is_explicit`. Nếu request chỉ cập nhật HTML thì chuyển sang dùng `sync_external_assets()` không phá hủy (non-destructive) hoặc giữ nguyên tệp hiện có.
2. **Bảo vệ Revision Restore**: Chỉ kích hoạt đồng bộ asset khi revision có metadata CSS/JS tường minh (`_align_css`, `_align_js`).
3. **Thêm Tool Quản Lý Lịch Sử Asset**: Bổ sung `Align_Agent_Tools_Asset_History` (`list_asset_history`, `restore_asset_history`) có phân quyền `edit_theme_options`, bảo vệ chống Path Traversal và chỉ cho phép đuôi `.css`/`.js`.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **CẤM**: Tuyệt đối không gọi `save_external_assets()` với chuỗi rỗng `''` khi thực hiện các tác vụ cập nhật HTML-only hoặc patch nội dung.
- **NÊN**: Luôn dùng `sync_external_assets()` cho mọi luồng cập nhật không mang theo payload CSS/JS rõ ràng.

---

## [BUG-20261020-1530] Lỗi Thẻ Duyệt Bị "Expired" (15-Minute Limit), Mất Pending Writes Khi Cập Nhật Hội Thoại & Đụng Độ Khóa Runner

- **Mã định danh lỗi**: `BUG-20261020-1530`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent/`
- **Tệp tin ảnh hưởng**:
  - `includes/class-agent-pending-store.php` (tạo mới)
  - `includes/class-agent-approval-service.php`
  - `includes/class-agent-conversation.php`
  - `includes/class-gateway-telegram-lock.php` (tạo mới)
  - `includes/class-gateway-telegram-approval.php`
  - `includes/class-gateway-telegram-runner.php`
  - `includes/class-gateway-webhook.php`
- **Triệu chứng & Bằng chứng log**:
  - Bằng chứng log `07:46 UTC 20-Oct-2026`: Người dùng gửi yêu cầu qua Telegram, bot phản hồi thẻ duyệt Inline Keyboard (`✅ Approve` / `❌ Cancel`). Ngay khi người dùng nhấn nút duyệt (chỉ sau vài giây), bot trả về thông báo lỗi: `⚠️ This action has expired (15-minute limit) or has already been processed.`
  - Thao tác ghi không thể thực thi và rơi vào trạng thái bế tắc; người dùng buộc phải gõ lại toàn bộ câu lệnh từ đầu.

### Nguyên Nhân Gốc Rễ (Root Cause)
1. **Pending Store phụ thuộc Blob Conversation**: Toàn bộ danh sách `pending_writes` được lưu chung bên trong trường meta `user_meta` `align_agent_conversation_telegram`. Khi bot thực hiện cập nhật tin nhắn (`add_assistant_message`) hoặc khi user gửi tin mới, toàn bộ blob được ghi đè, dẫn tới việc các bản ghi pending writes chưa kịp xử lý bị xóa sạch hoặc mất đồng bộ.
2. **Stale Cache tại `user_meta`**: WordPress Object Cache không tự động giải phóng trước khi đọc meta trong tiến trình chạy nền. Khi webhook callback truy vấn danh sách `pending_writes`, hàm đọc trúng giá trị bộ nhớ đệm cũ (stale cache) thiếu `pending_id` mới tạo.
3. **Khoá Mutex Runner Thiếu Cơ Chế Gia Hạn & Tự Phục Hồi**: Khóa runner đơn luồng có TTL ngắn hoặc bị giữ chặt khi script kết thúc đột ngột, gây đụng độ race condition giữa tiến trình webhook callback duyệt thẻ và runner tin nhắn mới.
4. **Thẻ Phê Duyệt Đơn Lẻ (Single Cards)**: Khi một lượt LLM gọi nhiều tool ghi, bot gửi N thẻ phê duyệt rời rạc. Mỗi click duyệt phải kích hoạt 1 chu kỳ runner độc lập, gây nghẽn hàng đợi và làm tăng nguy cơ hết hạn của các thẻ còn lại.
5. **Callback Query Đồng Bộ Gây Nghẽn (Telegram Timeout 30s)**: Endpoint Webhook xử lý các thao tác ghi nặng (như tạo trang, compile CSS, render builder) trực tiếp trong chu kỳ phản hồi HTTP của Telegram, khiến callback query bị timeout sau 30 giây và Telegram báo lỗi đồng hồ cát hoặc retry.
6. **Thiếu Cơ Chế Vô Hiệu Thẻ Cũ (Superseded Cards)**: Khi người dùng gửi câu lệnh mới hoặc phát sinh lượt tương tác mới, các thẻ phê duyệt cũ trên Telegram vẫn giữ nguyên nút bấm hoạt động, khiến người dùng bấm nhầm vào thẻ của phiên cũ và nhận lỗi expired.

### Giải Pháp Đã Áp Dụng
1. **Tách biệt hoàn toàn kho lưu trữ `Align_Agent_Pending_Store`**:
   - Lưu trữ mỗi mục pending thành một option riêng biệt `align_agent_pw_{id}` (autoload: false, TTL 900s).
   - Quản lý danh mục theo chỉ mục `align_agent_pw_index_{channel}_{user_id}`.
   - Cơ chế claim nguyên tử (`Align_Agent_Pending_Store::take()`) dùng option lock `align_agent_pw_claim_{id}` ngăn chặn triệt để race condition khi bấm nút đúp.
2. **Đảm bảo tính tươi mới của bộ nhớ đệm (Cache Invalidation)**:
   - Gọi `wp_cache_delete( $user_id, 'user_meta' )` trước mọi thao tác truy vấn trạng thái hội thoại trong `Align_Agent_Conversation::get_state()`.
3. **Mutex Lock Chuyên Biệt `Align_Gateway_Telegram_Lock`**:
   - Quản lý khóa runner tập trung với TTL 900s, tự động phục hồi khi phát hiện khóa mồ côi (`stale recovery`), và tự động gia hạn lease sau mỗi lượt gọi tool (`refresh()`).
4. **Thẻ Phê Duyệt Gộp (Batch Approval Cards)**:
   - Khi có từ 2 thao tác ghi trở lên trong cùng 1 lượt, `Align_Gateway_Telegram_Approval` tự động gộp thành thẻ duy nhất với callback data `A|{batch_id}` / `C|{batch_id}`, cho phép duyệt hoặc hủy toàn bộ chỉ với 1 click.
5. **Xử Lý Nền Bất Đồng Bộ Cho Callback Query**:
   - Webhook trả ngay `200 OK` với thông báo `answerCallbackQuery( "Processing..." )` tức thì trong vòng < 500ms, sau đó chuyển toàn bộ thao tác apply nặng vào hook `shutdown` hoặc FastCGI background process.
6. **Cơ Chế Thu Hồi Thẻ Lỗi Thời (`mark_superseded`)**:
   - Khi có tin nhắn mới hoặc batch mới, hệ thống tự động gọi Telegram API để gỡ bỏ inline keyboard của các thẻ cũ và cập nhật nội dung thành `⏭ Superseded by a new message.`.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **CẤM**: Tuyệt đối không lưu trữ trạng thái có vòng đời độc lập (như pending approvals, queues) bên trong blob trạng thái hội thoại gộp.
- **CẤM**: Tuyệt đối không thực thi các tác vụ ghi dữ liệu nặng trực tiếp trong phản hồi HTTP đồng bộ của Telegram Webhook callback query.
- **NÊN**: Luôn sử dụng mutex lock với cơ chế stale recovery và tự động gia hạn cho các runner chạy nền dài hơn 30 giây.

---

## [BUG-20261019-1510] Lỗi `save_template` Multi-Field Rỗng Tham Số & `patch_content` Gọi Trên Nội Dung Trống

- **Mã định danh lỗi**: `BUG-20261019-1510`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent/`
- **Tệp tin ảnh hưởng**:
  - `includes/class-agent-loop.php:68-69, 211, 234-257`
  - `includes/tools/structure/class-agent-tools-templates.php:60-61, 107`
  - `includes/class-agent-patch-engine.php:30`
  - `assets/chatbox-render.js:289-320`
  - `assets/chatbox.css:322-337`
  - `assets/chatbox-content.css:100-111`
  - `includes/class-gateway-admin.php:110`
- **Triệu chứng**:
  - Khi user yêu cầu cập nhật đồng thời HTML và CSS của template part trong một lệnh (ví dụ `tp_header`, `tp_footer`), server báo lỗi `nothing_to_update` ("No fields provided to update (must provide at least one of html, css, js, title)") khi bấm Approve thẻ duyệt.
  - Khi template part chưa có nội dung (HTML/CSS rỗng), AI gọi `patch_content` với chuỗi `find=""` dẫn tới lỗi `patch_invalid_edit` ("The 'find' pattern cannot be empty.").
  - Thẻ duyệt phê duyệt trong chatbox chỉ có nút "Preview HTML", thiếu nút xem trước độc lập cho CSS khi chỉ sửa CSS.

### Nguyên Nhân Gốc Rễ (Root Cause)
1. **Thiếu ràng buộc nội dung tại Agent Loop & Schema**:
   - Khi prompt phức tạp hoặc dài, LLM sinh tool call `save_template` chỉ chứa `{"tp_id": "tp_header"}` mà bỏ quên tham số nội dung.
   - Schema của `save_template` chỉ đánh dấu `required: ['tp_id']`, khiến `Align_Agent_Loop::run_step()` tưởng hợp lệ và tiếp tục đẩy vào hàng đợi duyệt `pending_writes`.
   - Người dùng bấm Approve một tác vụ rỗng, đến tầng backend `tool_save_template()` mới ném lỗi `nothing_to_update`.
2. **Thiếu routing rule và định hướng cho patch engine**:
   - `patch_content` được thiết kế để tìm và thay thế chuỗi con đã có trong DB. Khi nội dung rỗng, `find` bắt buộc phải là chuỗi có độ dài > 0 nên không thể chèn nội dung mới vào target rỗng.
   - System Prompt thiếu quy tắc điều hướng AI chuyển sang dùng `save_template`, `save_page` hoặc `save_post` khi đối tượng chưa có nội dung.
3. **Renderer UI thẻ duyệt thiếu viewer cho CSS**:
   - `createApprovalCard` trong `chatbox-render.js` chỉ hiển thị preview khi `preview_html` tồn tại, làm che giấu mã CSS khi người dùng cập nhật riêng lẻ CSS cho site/template.

### Giải Pháp Đã Áp Dụng
1. **Early Validation tại Agent Loop**: Thêm guard kiểm tra `$has_content` cho `save_template` và `save_reuse_block` tại `class-agent-loop.php:234-257`. Nếu thiếu toàn bộ các trường `html`, `css`, `js`, `title`, lập tức trả lỗi `nothing_to_update` cho lượt gọi của LLM thay vì tạo thẻ duyệt rỗng.
2. **Cập nhật Schema & System Prompt**:
   - Thêm quy tắc 7 và 8 vào `build_system_prompt()` trong `class-agent-loop.php`: Hướng dẫn AI truyền đủ trường cho `save_template` và cấm dùng `patch_content` trên nội dung rỗng.
   - Cập nhật mô tả schema của `save_template` tại `class-agent-tools-templates.php:60-61` khẳng định hỗ trợ truyền đồng thời `html`, `css`, `js`.
3. **Điều hướng lỗi chi tiết cho Patch Engine**: Sửa thông báo lỗi `patch_invalid_edit` tại `class-agent-patch-engine.php:30` gợi ý dùng `save_template`, `save_page`, hoặc `save_post`.
4. **Hỗ trợ View CSS trên Drawer**: Bổ sung nút toggle "View CSS" và container `cssWrap` độc lập trong `assets/chatbox-render.js:289-320` kèm styling trong `assets/chatbox.css` và `assets/chatbox-content.css`.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **NÊN**: Luôn kiểm tra sự hiện diện của ít nhất một trường dữ liệu (`html`, `css`, `js`, `title`) trước khi đưa tác vụ cập nhật vào hàng đợi phê duyệt.
- **NÊN**: Luôn hướng dẫn rõ ràng trong System Prompt về việc truyền đồng thời nhiều tham số (`html` + `css`) khi cập nhật template part.
- **KHÔNG NÊN**: Tuyệt đối không dùng `patch_content` khi đối tượng mục tiêu đang rỗng hoặc với chuỗi `find` rỗng; bắt buộc dùng các tool ghi toàn phần (`save_template`, `save_page`, `save_post`).
- **NÊN**: Đảm bảo mọi loại asset (HTML, CSS, JS) đều có thành phần hiển thị xem trước trực quan tương ứng trên thẻ phê duyệt.

---

## [BUG-20261019-0940] Lỗi `Page #0 was not found.` & Mất Ký Tự Backslash Trong Hội Thoại

- **Mã định danh lỗi**: `BUG-20261019-0940`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent/`
- **Tệp tin ảnh hưởng**:
  - `includes/class-agent-conversation.php:111`
  - `includes/class-agent-loop.php:192-309`
  - `includes/class-agent-chat-controller.php:332`
  - `themes/Align-Real-Builder/align-child/assets/builder/style-page-membership-plans.css`
- **Triệu chứng**:
  - Sau khi quản trị viên bấm Approve thẻ lưu trang `save_page`, khung chat hiển thị lỗi màu đỏ `Page #0 was not found.`.
  - Ký tự escape CSS `\2212` (icon dấu trừ FAQ) bị mất dấu `\` và biến thành chuỗi số `2212`.
  - Lịch sử hội thoại trong `wp_usermeta` bị mất toàn bộ dấu `\` (`\n` thành `n`, `\"` thành `"`), làm hỏng chuỗi JSON `tool_calls[*].function.arguments`.

### Nguyên Nhân Gốc Rễ (Root Cause)
1. **Mất backslash do WordPress Core**: `Align_Agent_Conversation::save_state()` gọi `update_user_meta( $user_id, self::META_KEY, $clean_state )` mà không bọc `wp_slash()`. Hàm `update_metadata()` của WordPress tự động gọi `wp_unslash()` lên toàn bộ mảng trước khi serialize vào database.
2. **AI Proxy bọc `_raw` và thiếu validate `required`**: Mô hình AI sinh CSS chứa escape `\2212`, proxy không parse được chuỗi JSON nên bọc vào `{"_raw": "{\"post_id\": 5, ...}"}`. `Align_Agent_Loop::run_step()` dùng `json_decode()` trực tiếp không xử lý `_raw`, khiến `$args['post_id']` rỗng và nhận giá trị `0`. Hệ thống vẫn xếp hàng vào `pending_writes` với `$post_id = 0` thay vì chặn sớm.

### Giải Pháp Đã Áp Dụng
1. Bọc `wp_slash( $clean_state )` tại `class-agent-conversation.php:111` để bảo toàn nguyên vẹn chuỗi JSON và CSS escape.
2. Thêm hàm `Align_Agent_Loop::parse_tool_arguments()` tại `class-agent-loop.php:269-309` để tự động bóc tách vỏ bọc `_raw` và phục hồi escape backslash trước khi `json_decode()`.
3. Kiểm tra mảng `parameters.required` và chặn giá trị `post_id <= 0` tại `class-agent-loop.php:197-227`, gửi lỗi `invalid_arguments` trực tiếp về LLM context thay vì tạo thẻ duyệt hỏng.
4. Thêm error log khi thao tác ghi đã duyệt thất bại tại `class-agent-chat-controller.php:332`.
5. Khôi phục `content:"\2212"` trong `style-page-membership-plans.css` và reset session sạch qua `Align_Agent_Conversation::reset(1)`.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **NÊN**: Luôn bọc `wp_slash()` khi lưu cấu trúc mảng/đối tượng chứa dữ liệu thô (JSON, CSS, HTML, Code) qua các hàm `update_user_meta()`, `update_post_meta()` hoặc `update_option()`.
- **NÊN**: Luôn thẩm định đầy đủ các trường `required` và giá trị ID hợp lệ (`> 0`) trước khi xếp hàng tác vụ ghi vào `pending_writes`.
- **KHÔNG NÊN**: Không bao giờ đưa trực tiếp chuỗi arguments từ AI vào `json_decode()` mà không có cơ chế xử lý fallback cho wrapper `_raw` hoặc ký tự escape không chuẩn.
- **KHÔNG NÊN**: Không bao giờ chạy truy vấn SQL thô để reset hoặc sửa chữa dữ liệu hội thoại trong bảng meta.

---

## [BUG-20261019-1115] Bất Đồng Bộ Kích Thước Button & Trạng Thái Thinking Nằm Ngoài Luồng Tin Nhắn Chatbox

- **Mã định danh lỗi**: `BUG-20261019-1115`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent/`
- **Tệp tin ảnh hưởng**:
  - `views/chatbox-panel.php:54-75`
  - `assets/chatbox.css:215-224, 379-425`
  - `assets/chatbox.js:16, 64-86, 387-391`
- **Triệu chứng**:
  - Các nút bấm tại Header (`Clear`, `Close`) và Footer (`Attach Image`, `Send`) có chiều cao quá lớn và không đồng đều giữa nút icon và nút text.
  - Trạng thái `Thinking...` / `Running tool...` hiển thị ở thanh status bar tách biệt lơ lửng ngoài khung tin nhắn, gây giật giao diện và tách rời khỏi ngữ cảnh hội thoại AI.
  - Text placeholder rỗng và ô nhập textarea quá dài khiến nội dung bị rớt dòng làm xấu layout Drawer 420px.

### Nguyên Nhân Gốc Rễ (Root Cause)
1. **Thiếu ràng buộc box-sizing và chiều cao cứng**: Quy tắc `.align-agent-chatbox .button` đặt `height: 32px` nhưng thiếu `min-height`, `max-height` và `box-sizing: border-box`, dẫn tới việc bị ghi đè bởi CSS `.wp-core-ui .button` của WordPress Admin và làm lệch chiều cao giữa các icon button (`width: 32px`) và text button.
2. **Kiến trúc phần tử status nằm ngoài container tin nhắn**: Khối `#align-chatbox-tool-status` được đặt tĩnh giữa `#align-chatbox-messages` và `#align-chatbox-form`, không tuân theo mô hình luồng hội thoại tự nhiên của các ứng dụng AI Chat hiện đại.

### Giải Pháp Đã Áp Dụng
1. **Khóa cứng chuẩn Button 28px**: Đặt `height: 28px !important; min-height: 28px !important; max-height: 28px !important; line-height: 1 !important; box-sizing: border-box !important;` cho toàn bộ `.align-agent-chatbox .button`. Chuẩn hóa các icon button thành vuông `28px × 28px` và Dashicons `15px`.
2. **Chuyển đổi Thinking thành bong bóng AI Reply**: Xóa bỏ hoàn toàn phần tử `#align-chatbox-tool-status`. Khởi tạo động bong bóng assistant `.align-chat-msg.align-chat-msg-assistant.align-chat-msg-thinking` (kèm spinner và nhãn trạng thái) ngay trong `#align-chatbox-messages` và tự dọn sạch khi AI trả lời xong.
3. **Chuẩn hóa văn bản ngắn gọn**: Rút gọn text rỗng thành `Ask Align Agent to inspect, draft, or edit pages.` và placeholder thành `Ask Align Agent... (Enter to send)`.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **NÊN**: Luôn khóa cứng cả `min-height`, `max-height` và `box-sizing: border-box` khi thiết lập kích thước button trong môi trường WordPress Admin để tránh bị `.wp-core-ui` phá vỡ layout.
- **NÊN**: Luôn hiển thị trạng thái chờ phản hồi của AI (thinking/typing/tool status) bên trong container tin nhắn dạng message bubble của trợ lý, duy trì tính liền mạch của luồng hội thoại.
- **KHÔNG NÊN**: Không bao giờ đặt các thanh thông báo trạng thái tĩnh giữa danh sách tin nhắn và khung nhập liệu gây phân mảnh trải nghiệm thị giác.
- **KHÔNG NÊN**: Không viết placeholder dài quá 40 ký tự trong các khung drawer có chiều rộng hạn chế (<= 420px).

---

## [FEAT-20261019-1240] Triển Khai Hoàn Chỉnh Align Agent v2 (Milestones M0–M7) & Quy Chuẩn Tool Calling

- **Mã định danh**: `FEAT-20261019-1240`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent/`
- **Tệp tin ảnh hưởng**:
  - `align-agent.php`
  - `includes/class-agent-tool-registry.php`
  - `includes/class-agent-audit-log.php` (Mới)
  - `includes/class-agent-loop.php`
  - `includes/class-agent-chat-controller.php`
  - `includes/class-agent-tools-posts.php` (Mới)
  - `includes/class-agent-tools-taxonomy.php` (Mới)
  - `includes/class-agent-tools-templates.php` (Mới)
  - `includes/class-agent-tools-site.php` (Mới)
  - `includes/class-agent-tools-redirects.php` (Mới)
  - `includes/class-agent-tools-comments.php` (Mới)
  - `includes/class-agent-tools-users.php` (Mới)
  - `includes/class-agent-tools-media.php`
  - `includes/class-agent-tools-pages.php`
  - `includes/class-agent-tools-seo.php`
  - `includes/class-agent-seo-adapters.php`
  - `assets/chatbox-render.js`
  - `assets/chatbox.css`
  - `assets/chatbox.js`

### Tóm Tắt Thay Đổi (Chức Năng & Kỹ Thuật)
1. **M0 (Nền Tảng)**: Bổ sung `requires_builder`, `capability`, `group`, `danger` vào `Align_Agent_Tool_Registry`. Audit Log + snapshot với giới hạn 100 entries (`Align_Agent_Audit_Log`). Tăng `MAX_CONTINUE_TURNS = 12`. Render Approval Card với class `.align-chat-card-danger` và `role="alert"`.
2. **M1 (Nội Dung: Post / Page / CPT)**: 10 tools quản lý toàn diện bài viết, trang, custom post types. Chặn xóa vĩnh viễn (`trash_post` chỉ dùng `wp_trash_post`). Chặn trạng thái hẹn giờ trong quá khứ (`invalid_future_date`).
3. **M2 (Taxonomy)**: 5 tools quản lý danh mục, thẻ tag, CPT terms. Chặn xóa term đang chứa bài viết (`term_not_empty`). Giới hạn `number => 100` chống tràn RAM.
4. **M3 & M7 (Templates & Site Ops)**: 7 tools quản lý FSE Templates, Template Parts, Reusable Blocks. Quét cả shortcode và Gutenberg native `<!-- wp:block {"ref":N} -->`. Bảo vệ khóa Gutenberg (`editor_mode_locked`). Thao tác homepage, identity, xóa cache, và tổng quan site (`site_overview`).
5. **M4 (SEO & Redirects)**: Mở rộng SEO Adapter sang toàn bộ public post types. 5 tools quản lý Rank Math Redirections và 404 Logs. Chặn loop redirect `$clean_source === $clean_dest`.
6. **M5 (Media Mở Rộng)**: `update_media_meta`, `trash_media` có guard kiểm tra ảnh đang dùng (`_thumbnail_id` và URL nội dung bài viết).
7. **M6 (Comments & Users)**: 8 tools kiểm soát bình luận và người dùng với các lớp bảo vệ nghiêm ngặt: cấm tự xóa, cấm tự hạ quyền tài khoản hiện tại, bảo vệ Administrator cuối cùng, tuyệt đối không xuất mật khẩu hoặc reset link ra tool output.
8. **Remediation QC**: Khóa cứng kích thước Drawer 420px trong CSS; tăng specificity cho nút Danger Card `#dc2626`; đồng bộ `MAX_CONTINUE_TURNS = 12` trên cả Frontend và Backend; bổ sung `aria-describedby` hỗ trợ ADA.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **NÊN**: Luôn tách nhỏ file (< 500 dòng/file) theo chuẩn Single Responsibility Principle (SRP).
- **NÊN**: Luôn kiểm tra quyền `capability` trước khi thực thi tool và gán cờ `danger: true` cho mọi thao tác ảnh hưởng đến quyền quản trị hoặc cấu trúc toàn trang (Templates, Admins).
- **KHÔNG NÊN**: Tuyệt đối không xóa cứng dữ liệu (`wp_delete_post( $id, true )`); luôn dùng `wp_trash_post()`.
- **KHÔNG NÊN**: Không bao giờ bỏ qua bước kiểm tra vòng lặp chuyển hướng URL (Redirect loop) khi tạo redirect rules.

---

## [REFACTOR-20261019-1300] Tái Cấu Trúc Thư Mục Tool (`includes/tools/`) & Nạp Tập Trung Bằng Manifest Loader

- **Mã định danh**: `REFACTOR-20261019-1300`
- **Codebase bao bọc**: `wp-content/plugins/Align-Agent/`
- **Tệp tin ảnh hưởng**:
  - `align-agent.php` (Thay 11 dòng require và 11 dòng init bằng Loader)
  - `includes/tools/class-agent-tools-loader.php` (Mới: Quản lý manifest tĩnh nạp và init 11 class)
  - `includes/tools/README.md` (Mới: Danh mục kỹ thuật tổng hợp 57 tools)
  - `includes/tools/content/README.md`, `class-agent-tools-pages.php`, `class-agent-tools-posts.php`
  - `includes/tools/taxonomy/README.md`, `class-agent-tools-taxonomy.php`
  - `includes/tools/structure/README.md`, `class-agent-tools-templates.php`
  - `includes/tools/site/README.md`, `class-agent-tools-site.php`
  - `includes/tools/seo/README.md`, `class-agent-seo-adapters.php`, `class-agent-tools-seo.php`, `class-agent-tools-redirects.php`
  - `includes/tools/media/README.md`, `class-agent-tools-media.php`
  - `includes/tools/comments/README.md`, `class-agent-tools-comments.php`
  - `includes/tools/users/README.md`, `class-agent-tools-users.php`
  - `AGENTS.md` (Thêm §5 Tool Folder Standard, sửa đường dẫn M2)
  - `README.md` (Cập nhật đường dẫn tool media, thêm liên kết danh mục 57 tools)

### Tóm Tắt Thay Đổi
1. **Phân Rã Thư Mục Theo Nhóm**: Di chuyển phẳng 10 class tool và 1 adapter từ `includes/` vào 8 thư mục con trong `includes/tools/<group>/` (`content`, `taxonomy`, `structure`, `site`, `seo`, `media`, `comments`, `users`). Toàn bộ nội dung logic và tên class giữ nguyên 100%.
2. **Bộ Nạp Tập Trung Qua Manifest**: Xây dựng `Align_Agent_Tools_Loader` với mảng tĩnh `MANIFEST` cố định thứ tự nạp 11 mục, bảo toàn 100% thứ tự đăng ký tool trong OpenAI JSON Schema. Thay thế toàn bộ 11 lệnh `require_once` và 11 lệnh `::init()` rải rác trong `align-agent.php`.
3. **Bộ Tài Liệu Kỹ Thuật Độc Lập Cho AI**: Tạo mới `includes/tools/README.md` tổng hợp 57 tools với bảng tra cứu đầy đủ thuộc tính và 8 tệp `README.md` chi tiết cho từng thư mục chức năng.
4. **Kiểm Chứng Toàn Diện (Zero Regression)**: Đối chiếu diff 3 baseline (Schema 40 tools, Runtime Reflection 51 tools, Code Parser 57 tools) đạt độ trùng khớp tuyệt đối 100%.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **NÊN**: Luôn đặt các tool Chatbox vào đúng thư mục nhóm con `includes/tools/<group>/`.
- **NÊN**: Luôn cập nhật hằng số `Align_Agent_Tools_Loader::MANIFEST` khi tạo thêm class tool mới, không dùng `glob()` tự động quét để tránh sai lệch thứ tự schema hoặc nạp ngầm file lỗi.
- **KHÔNG NÊN**: Tuyệt đối CẤM tạo file `includes/class-agent-tools-*.php` trực tiếp tại thư mục gốc `includes/`.
- **KHÔNG NÊN**: Không commit thay đổi khi chưa cập nhật đồng thời tài liệu tại `includes/tools/README.md`.

---

## [BUG-20261024-1215] Agent Hết Số Bước Giữa Chừng Khi Sửa HTML Nhiều Vị Trí & Thiếu Cơ Chế Continue

- **Mã định danh lỗi**: `[BUG-20261024-1215]` - Agent hết số bước giữa chừng khi sửa HTML nhiều vị trí và thiếu cơ chế Continue
- **Codebase bao bọc**: `/Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/plugins/Align-Agent-main`
- **Vị trí nguyên nhân gốc**: `includes/class-agent-loop.php:21,153-167`, `assets/chatbox.js:11,187-192`, `includes/class-gateway-mcp.php:285`
- **Cơ chế phát sinh lỗi**: Giới hạn 12 lượt tự động quá thấp cho tác vụ sửa HTML nhiều bước, nhánh dừng không trả cờ `limit_reached` hay nút tiếp tục, và MCP gán nhầm nhãn kênh `telegram`.
- **Mã sửa đổi đã áp dụng**: Đồng bộ `MAX_CONTINUE_TURNS = 20`, trả `limit_reached: true` kèm tóm tắt `Align_Agent_Run_Report`, thêm nút Continue cho Telegram (`includes/class-gateway-telegram-continue.php`) và Chatbox (`assets/chatbox-continue.js`), bổ sung quy tắc gom `patch_content` trong prompt, và đổi kênh MCP thành `mcp`.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **NÊN**: Giữ đồng bộ hằng số `MAX_CONTINUE_TURNS = 20` trên cả `includes/class-agent-loop.php` và `assets/chatbox.js`.
- **NÊN**: Luôn trả cờ `limit_reached: true` kèm báo cáo tiến độ và nút Continue khi vòng lặp chạm trần lượt tự động.
- **NÊN**: Tách logic UI/Callback mới ra file riêng (`class-gateway-telegram-continue.php`, `chatbox-continue.js`) để giữ mọi file dưới 500 dòng.
- **KHÔNG NÊN**: Không ghi cứng chuỗi số lượt cũ (`6`, `12`) trong thông báo fallback hoặc comment.
- **KHÔNG NÊN**: Không truyền nhãn kênh `'telegram'` khi gọi `Align_Agent_Write_Executor::run()` từ cổng MCP (`includes/class-gateway-mcp.php`).

---

## [BUG-20261024-1314] Lỗi `DEPTH_ZERO_SELF_SIGNED_CERT` & Chưa Tự Động Sync Sang Middleware Sau Khi Activate License / Pair Telegram Trên Domain `.local`

- **Mã định danh lỗi**: `[BUG-20261024-1314]` - Lỗi `DEPTH_ZERO_SELF_SIGNED_CERT` và chưa tự động sync sang Middleware sau khi Activate License / Pair Telegram trên domain `.local`
- **Codebase bao bọc**: `/Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/plugins/Align-Agent-main`
- **Vị trí nguyên nhân gốc**: `includes/class-gateway-admin.php:364-365`, `includes/class-gateway-admin.php:244`, `includes/class-gateway-telegram-pairing.php:141`
- **Cơ chế phát sinh lỗi**: Hàm `get_rest_url( null, 'align-agent/v1/mcp' )` tự động ép scheme `https://` khi mở WP Admin qua SSL tự ký trên `.local`, khiến Node.js `fetch` của Middleware bị lỗi `DEPTH_ZERO_SELF_SIGNED_CERT`; đồng thời việc thiếu auto-sync khiến bản ghi trên Middleware đứng ở trạng thái `license-only` nếu user quên bấm `Save & Sync to Bridge`.
- **Mã sửa đổi đã áp dụng**: Bổ sung `Align_Gateway_Admin::maybe_auto_sync_bridge()` khi Activate Key / Pair Telegram và ép `set_url_scheme( $mcp_url, 'http' )` cho các tên miền nội bộ (`.local`, `.test`, `localhost`, `127.0.0.1`).

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **NÊN**: Tự động gọi `Align_Gateway_Admin::maybe_auto_sync_bridge()` ngay sau khi Activate License Key và sau khi Pair Telegram 6 số thành công.
- **NÊN**: Dùng scheme `http` cho MCP URL (`set_url_scheme( $mcp_url, 'http' )`) trên các domain nội bộ (`.local`, `.test`, `localhost`, `127.0.0.1`).
- **KHÔNG NÊN**: Không gửi MCP URL `https://*.local` với chứng chỉ SSL tự ký sang Node.js Middleware.

---

## [BUG-20261024-1518] Sửa Global Scope Không Có Backup, Hỏng Trang > 512 KB Khi Revert & Thiếu Bản Đồ Trang (Safe Edit & Global Vault v1)

- **Mã định danh lỗi**: `[BUG-20261024-1518]` - Sửa Global Scope không có backup, hỏng trang > 512 KB khi revert và thiếu bản đồ trang
- **Codebase bao bọc**: `/Volumes/JoeSSD/SymLinks/Local_Sites_Home/trusted-union/app/public/wp-content/plugins/Align-Agent-main`
- **Vị trí nguyên nhân gốc**: `includes/class-agent-undo-adapters.php:84-85,293,345-364`, `includes/tools/content/class-agent-tools-patch.php:26`, `includes/class-agent-write-executor.php:26`, `includes/class-agent-change-set.php:72-76,100-117`
- **Cơ chế phát sinh lỗi**: Undo adapter `template` bỏ qua `$spec['id_arg']` và chuỗi `tp_id`, `patch_content` gán cứng adapter `post`, `Align_Agent_Write_Executor::run()` vẫn ghi khi snapshot rỗng, `Align_Agent_Change_Set` ghi đè chuỗi nén base64 gzip lên `before['html']` với trang > 512 KB, và Agent không có tool định vị bản đồ trang dẫn tới mất nhiều lượt dò tìm.
- **Mã sửa đổi đã áp dụng**: Tạo `Align_Agent_Undo_Resolver`, chặn ghi fail-closed (`backup_failed`) trong `Align_Agent_Write_Executor`, tách trường nén `html_gz` + fallback `wp_restore_post_revision()`, xây dựng `Align_Agent_Global_Vault` (CPT `align_agent_backup` / `align_agent_bk_chunk` `<= 20` ký tự, 0 `wp_postmeta`, kiểm tra `verify_after()`), `Align_Agent_Vault_Gc`, `Align_Agent_Tools_Backups`, `Align_Agent_Tools_Render_Map` (`inspect_render`, `find_in_render`), `Align_Agent_Working_Notes`, và `Align_Agent_Prompt_Rules`.

### Quy Tắc Chống Tái Lỗi (Rules for AI & Developers)
- **NÊN**: Luôn phân giải ID đối tượng qua `Align_Agent_Undo_Resolver::resolve_object_id()` và chặn đứng thao tác ghi (`backup_failed`) nếu snapshot `before` rỗng.
- **NÊN**: Lưu chuỗi nén gzip + base64 vào trường riêng (`html_gz`, `post_content_gz`), tuyệt đối không ghi đè trường `html` gốc.
- **NÊN**: Đặt tên CPT trong WordPress tối đa 20 ký tự (`align_agent_backup`, `align_agent_bk_chunk`).
- **KHÔNG NÊN**: Không so sánh chuỗi nguyên văn sau khi ghi đối với template/block đã qua `Align_Minifier`; dùng `read_live_fields()` kiểm tra tồn tại DB và bảo toàn dữ liệu không rỗng.
- **KHÔNG NÊN**: Không bao giờ đọc file PHP trong `inspect_render` hoặc `find_in_render`.

