# 🤖 Align Agent — Autonomous AI Agent & Gateway Plugin

Align Agent là plugin cổng giao tiếp (Gateway) kết nối WordPress & Align Theme với các mô hình Autonomous AI Agent (OpenAI, Claude, v.v.) và Telegram Bot, hỗ trợ quản lý trang, điều phối CSS/Design Context, và thực thi các thao tác điều khiển từ xa có kiểm soát (Sandbox Dry-Run Validator & Rollback).

---

## 🧭 Quy Chuẩn Tích Hợp Menu Quản Trị (Align Ecosystem Admin Menu)

Để duy trì giao diện quản trị đồng bộ và chuyên nghiệp trong hệ sinh thái Align:
- Khi Align Theme đang kích hoạt (`defined('ALIGN_CORE_PATH')`), plugin tự động đăng ký submenu bên trong menu chính **Align Theme** (`align-settings`) ở dưới cùng thanh sidebar WordPress.
- Khi sử dụng theme khác, plugin tự động fallback về `options-general.php` (Settings).
- Luôn sử dụng `add_submenu_page()`, không sử dụng `add_options_page()`.

```php
public static function register_menu(): void {
    $parent_slug = defined( 'ALIGN_CORE_PATH' ) ? 'align-settings' : 'options-general.php';

    add_submenu_page(
        $parent_slug,
        __( 'Align Agent Gateway', 'align-agent' ),
        __( 'Align Agent', 'align-agent' ),
        'manage_options',
        self::MENU_SLUG,
        array( __CLASS__, 'render_page' )
    );
}
```

---

## 🛠️ Kiến Trúc & Module Lõi
- **Gateway Auth**: Xác thực bearer token và quyền hạn truy cập API.
- **Telegram Webhook & Bot Gateway**: Nhận lệnh và phản hồi trực tiếp qua Telegram.
- **Telegram Pairing Controller**: Ghép nối bot bằng mã 6 số, tự bắt đúng chat và lưu Chat ID (`includes/class-gateway-telegram-pairing.php`).
- **AI Provider Controller**: Kết nối các LLM qua chuẩn tương thích OpenAI.
- **Pages CRUD & Rollback Controller**: Tạo và rollback các thay đổi trang an toàn.
- **Sandbox Dry-Run Validator**: Chạy thử nghiệm các thay đổi trước khi ghi vào database.
- **Chatbox Tools Registry & Loader**: Quản lý toàn bộ 86 công cụ chia theo 9 nhóm (`includes/tools/`), nạp tập trung qua `Align_Agent_Tools_Loader` (xem chi tiết tại [`includes/tools/README.md`](includes/tools/README.md)).

---

## ⚡ v4 Site Administration Tools (Mở Rộng Quản Trị Website)
Plugin mở rộng 21 công cụ quản trị nâng cao điều khiển đồng thời qua Chatbox & Telegram:
- **Code Injection Slots**: `get_code_slots`, `save_code_slot` (head tracking, body-open, footer, skip_admins).
- **WordPress Settings**: `get_site_settings`, `update_site_settings` (permalinks, blog_public, timezone, comment rules).
- **Navigation Menus**: `list_nav_links`, `update_nav_links` (sửa đồng bộ desktop và mobile nav trong header template part).
- **Multi-language (i18n)**: `list_languages`, `get_translations`, `translation_overview`, `create_translation`, `manage_language`, `scan_unlocalized_urls`, `localize_post_urls`.
- **Site Protection**: `get_site_protection`, `set_site_protection` (bảo vệ HTTP Basic Auth cho staging, cấm sửa credential).
- **Post Password Lock**: `get_post_lock`, `set_post_lock` (khoá mật khẩu trang/bài viết an toàn).
- **SEO & Operations Diagnostics**: `audit_seo_coverage`, `list_audit_logs`, `undo_audit_action`, `site_health` (hoàn tác option, chẩn đoán hệ thống và che mờ token nhạy cảm).

---

## 🖥️ Giao Diện Cài Đặt & Cổng Kích Hoạt License (Settings UI & License Gate)

### 1. Cổng Khóa Kích Hoạt License (License Activation Gate)
- Khi chưa kích hoạt License Key (`! Align_Gateway_Admin::is_license_active()`), trang Settings chỉ hiển thị **License Activation Gate** (ẩn toàn bộ thanh tab điều hướng và ẩn Global Chatbox Drawer/FAB trên toàn bộ WP Admin).
- Quản trị viên nhập **Middleware URL** và **License Key** (`ak_...`) cấp riêng cho domain hiện tại, hệ thống gọi `POST <middleware_url>/api/license/activate` kèm headers `X-Api-Key` và `X-Site-Domain` (domain chuẩn hóa chữ thường, bỏ `www.`).
- Khi kích hoạt thành công, plugin lưu trạng thái vào `align_bridge_license_key`, `align_bridge_middleware_url`, `align_agent_license_data`, đồng thời tự động gọi `Align_Gateway_Admin::maybe_auto_sync_bridge()` nếu đã có sẵn cấu hình Bridge để chuyển trạng thái site sang `connected` ngay lập tức.

### 2. Bố Cục 4 Tab Tinh Gọn (4-Tab Workspace)
Khi License đã kích hoạt, trang cài đặt hiển thị bố cục **4 tab ngang** (`.align-nav-tab` + `.align-agent-tab-panel`), mỗi lần chỉ hiện 1 panel để tránh scroll sâu. Tab active lưu vào URL hash (`#tab-bridge`...):
1. **Bridge** — Thanh trạng thái License (`Active`, `Site ID`, `Domain`, `Key` che mờ, nút `Change Key`) và 3 thẻ cấu hình hợp nhất:
   - **1. Telegram Bot & Pairing**: Nhập `Telegram Bot Token`, ghép nối mã 6 số (`Generate Pairing Code`), và `Chat ID (Allowed User IDs)`.
   - **2. AI / LLM Provider**: Cấu hình `LLM Base URL`, `LLM API Key`, `LLM Model` và nút `Test AI` dùng chung cho cả Middleware Bridge và WP Chatbox.
   - **3. Middleware Hub & WordPress MCP Auth**: Cấu hình `Middleware URL`, chính sách `Approval Mode` (`auto` / `manual`), `WP Username`, `WP Application Password`, và nút `Save & Sync to Bridge`.
2. **MCP Tools** — Kiểm tra kết nối AI/Tool Calling và danh mục công cụ MCP (`views/tab-mcp.php`).
3. **SEO** — Chọn nguồn SEO (`rank_math`, `yoast`, `theme_default`, `others`) bằng radio (`views/tab-seo.php`).
4. **Updates** — Quản lý cập nhật từ GitHub repository (`views/tab-updates.php`).

### 3. Đồng Bộ Cấu Hình Sang Middleware (`/api/license/config`)
- Nút **Save & Sync to Bridge** (`wp_ajax_align_agent_register_bridge`) và cơ chế tự động đồng bộ (`maybe_auto_sync_bridge()`) gửi cấu hình (`telegram`, `mcp`, `llm`) tới `POST <middleware_url>/api/license/config` xác thực bằng `X-Api-Key` + `X-Site-Domain` (không dùng `BRIDGE_ADMIN_TOKEN`).
- Đối với các tên miền nội bộ (`.local`, `.test`, `localhost`, `127.0.0.1`), plugin tự động ép `mcp.url` về giao thức `http://` (`set_url_scheme( $mcp_url, 'http' )`) để tránh lỗi chứng chỉ SSL tự ký (`DEPTH_ZERO_SELF_SIGNED_CERT`) khi Node.js Middleware gọi ngược vào WordPress.

### Ghép Nối Telegram Bằng Mã 6 Số (Pairing Code)

Luồng kết nối bot ngay trong tab **Bridge**:
1. Dán **Telegram Bot Token** (từ @BotFather) vào thẻ **1. Telegram Bot & Pairing**.
2. Bấm `Generate Pairing Code` để sinh **mã 6 số**.
3. Mở bot trong Telegram, gửi mã đó cho bot.
4. Trình duyệt tự dò (`align_agent_pair_poll`, 2.5s/lần, tối đa ~2 phút), khớp đúng chat gửi mã → lưu **Chat ID**, điền tự động vào ô `Allowed User IDs`, gửi tin xác nhận trên Telegram và gọi `Align_Gateway_Admin::maybe_auto_sync_bridge()` để đồng bộ tức thì sang Middleware Hub.

> Mã lưu dạng transient (`align_agent_tg_pair_code`, TTL 600s). Chỉ chat gửi đúng mã mới được bind, tránh bắt nhầm chat khác.

### Vận Hành Telegram Bot Gateway & Inline Approval (v1)

Telegram bot được đồng bộ hóa hoàn toàn với kiến trúc `Align_Agent_Loop`:
- **Định danh quản trị viên (Bound Admin)**: Chọn tài khoản Administrator thực thi trong tab Telegram (`align_agent_telegram_user_id`).
- **Phân lập phiên hội thoại**: Lịch sử chat được lưu tại `align_agent_conversation_telegram`, tách biệt hoàn toàn với Chatbox trên WP Admin.
- **Webhook ACK sớm & Chống trùng**: Trả `200 OK` tức thì và xử lý nền qua `shutdown`; transient `align_agent_tg_upd_{id}` (TTL 1h) ngăn chặn triệt để gửi lặp bản tin từ Telegram.
- **Inline Keyboard Approval, Batch Cards & Continue**: Thao tác ghi yêu cầu phê duyệt sẽ gửi thẻ xác nhận kèm 2 nút bấm `✅ Approve` và `❌ Cancel` (`callback_data` dạng `a|{pending_id}` hoặc `c|{pending_id}`). Khi có nhiều thao tác trong cùng lượt, tự động gộp thành thẻ batch (`A|{batch_id}` / `C|{batch_id}`) để duyệt nhanh trong 1 lần bấm. Khi chạm giới hạn 20 lượt tự động (`limit_reached: true`), gửi kèm nút `▶️ Continue` (`callback_data = k|{conversation_id}`, xử lý qua `Align_Gateway_Telegram_Continue`) để chạy tiếp thêm 1 đợt 20 lượt mới. Thẻ cũ tự động chuyển sang trạng thái `superseded` khi có câu lệnh mới.
- **Chính Sách Duyệt Theo Phạm Vi (`Align_Agent_Approval_Policy`)**:
  - `approve` (Phạm vi Toàn cục / Nguy hiểm): Sửa Template Part, Header, Footer, Reusable Block toàn trang, xóa người dùng hoặc xóa redirect bắt buộc phải được người dùng phê duyệt qua thẻ Inline Keyboard.
  - `auto_clone` (Trang Đã Publish): Mọi yêu cầu sửa trang đang publish kích hoạt luồng clone nháp để bảo vệ tính sẵn sàng của website.
  - `auto` (Nội Dung Cục Bộ / Bản Nháp): Tạo trang nháp mới, tạo bài viết mới, sửa trang nháp, tải ảnh lên, cập nhật SEO meta, và tạo redirect hợp lệ được thực thi tự động và lưu vết vào Audit Log.
- **Luồng Clone Nháp Cho Trang Live (`Align_Agent_Page_Clone`)**:
  - Tự động tạo bản nháp `[Draft] {Title}` liên kết qua meta `_align_agent_clone_of` và lưu timestamp `_align_agent_clone_base_modified`.
  - Mọi thao tác chỉnh sửa liên tiếp trong lượt hội thoại được gom chung vào cùng 1 clone, trang publish bên ngoài hoàn toàn không bị ảnh hưởng.
  - Cuối lượt, bot gửi thẻ duyệt `apply_page_clone` kèm link xem trước (`preview_url`). Khi người dùng bấm Apply, nội dung được cập nhật vào trang gốc, clone được chuyển vào thùng rác (`wp_trash_post`) và dọn sạch file assets thừa.
- **Báo Cáo Tiến Độ Tự Động (`Align_Agent_Run_Report`)**: Server tự động tổng hợp danh sách thay đổi theo chuẩn ASD-STE100 ngắn gọn (mỗi câu <= 20 từ, thể chủ động, loại bỏ ID kỹ thuật).
- **Tiếp Nhận Ảnh Qua Telegram (Photo & File Document)**:
  - Hỗ trợ gửi ảnh trực tiếp (`photo`) hoặc gửi dưới dạng tệp tin (`document`) như PNG, JPG, WebP, GIF (tối đa 5 MB/tệp; cấm SVG).
  - *Mẹo tối ưu chất lượng*: Đối với logo hoặc đồ họa cần giữ độ sắc nét và nền trong suốt, người dùng nên gửi qua Telegram dưới dạng **File/Document** (thay vì Photo nén mặc định của Telegram).
  - Khi gửi ảnh không kèm chữ, ảnh sẽ được tải an toàn và xếp vào hàng đợi chờ (`Saved image #... (1 queued). Send your instruction to use it.`). Khi người dùng gửi câu lệnh hoặc kèm caption, toàn bộ ảnh trong hàng đợi sẽ được chuyển thành các thẻ ngữ cảnh đính kèm `[Attached Media: attachment_id=..., url=..., file=...]` để AI trực tiếp chèn vào trang hoặc đặt làm Featured Image.
- **Tối Ưu Chống Timeout (AI Streaming)**:
  - Tích hợp streaming SSE với idle timeout 45s và trần thời gian chạy nền lên tới 300s cho Telegram runner, đảm bảo quá trình khởi tạo landing page hoặc bài viết lớn không bị ngắt kết nối `cURL error 28`.
- **Bộ lệnh tích hợp**:
  - `/new`: Xoá phiên Telegram, xoá hàng đợi ảnh đính kèm và bắt đầu cuộc trò chuyện mới.
  - `/status`: Kiểm tra số lượng tin nhắn, số ảnh đang trong hàng đợi chờ và các thao tác đang chờ duyệt.
  - `/help`: Xem danh sách hướng dẫn lệnh.
- **Mô Hình Vận Hành Linh Hoạt (Local vs Production)**:
  - **Môi trường Local**: Sử dụng chế độ **Polling Mode** (`getUpdates`). Chỉ cần bấm **Remove Webhook** tại tab Telegram; không phụ thuộc Live Link tunnel và không sợ lỗi 404.
  - **Môi trường Server Production**: Sử dụng chế độ **Webhook Mode** (`POST`). Sau khi đưa mã nguồn lên server có tên miền HTTPS thật, bấm **Reset** và **Register Webhook** một lần duy nhất để nhận tin nhắn tức thì (0s delay).
- **Lưu ý sau khi cập nhật plugin**: Người dùng cần vào tab Telegram và bấm lại nút **"Register Webhook"** để Telegram cập nhật quyền nhận `callback_query`.

---

## WP Admin Conversational Chatbox (Global Slide-in Drawer & FAB)

Khung chatbox hỗ trợ điều khiển WordPress bằng hội thoại, hoạt động trên **toàn bộ các trang WP Admin** cho người dùng có quyền `manage_options`:
- **Giao diện Global Drawer & Backdrop**: Khung chat trượt từ cạnh phải (`width: 420px; height: 100vh; z-index: 100001;`) đè lên toàn bộ viewport kể cả `#wpadminbar`, đi kèm lớp nền mờ `backdrop-filter: blur(4px);` (`z-index: 100000`) làm mờ toàn bộ trang quản trị nền để tập trung thị giác.
- **Nút Nổi Toàn Cục (FAB)**: Nút `#align-chatbox-fab` cố định ở góc dưới bên phải màn hình (`right: 24px; bottom: 24px; z-index: 99998;`) trên mọi trang WP Admin giúp bật/tắt nhanh khung chat.
- **Trang Cài Đặt Settings (`?page=align-agent`)**: Cột chatbox tĩnh đã được gỡ bỏ, giải phóng `.align-agent-workspace` thành 1 cột full-width để mở rộng không gian cấu hình và dùng chung Global Drawer.
- **Lazy Loading & Accessibility**: Chỉ nạp lịch sử hội thoại (`loadHistory()`) ở lần đầu tiên mở Drawer; tự động đóng khi nhấn phím `Escape`, bấm backdrop hoặc bấm nút đóng; quản lý tiêu điểm và ngăn ngừa Keyboard Focus Leak bằng `visibility: hidden;` khi Drawer đóng (chuẩn ADA/WCAG 2.1 AA).

### Kiến Trúc & Bảo Mật
- **Xác thực**: Dùng phiên đăng nhập WordPress (`is_user_logged_in() && current_user_can('manage_options')`) kèm `X-WP-Nonce` (`wp_rest`). Tuyệt đối không dùng chung Secret Key của bot ngoài.
- **Vòng lặp Client-Driven**: Mỗi request tới `POST /wp-json/align-agent/v1/chat/step` chạy đúng 1 lượt LLM + thực thi các tool đọc (`list_pages`, `get_page`, `get_revisions`, `validate_markup`, `list_media`, `check_seo_output`), giới hạn tối đa 20 lượt `continue` liên tiếp (`MAX_CONTINUE_TURNS = 20`, kèm nút `Continue` qua `assets/chatbox-continue.js` khi chạm giới hạn) và 20 tin nhắn/phút/user.
- **Duyệt Thao Tác Ghi & Rollback**: Các tool ghi trang (`save_page`, `create_page`, `restore_revision`) luôn đi qua `Align_Builder_Crud_Handler` / `Align_Builder_Revision_Handler`, `create_page` mặc định `status = draft`, `save_page` giữ nguyên trạng thái hiện tại khi không truyền `status`, bắt buộc người dùng bấm `Approve` trên thẻ xác nhận (`POST /wp-json/align-agent/v1/chat/approve`), có bản xem trước HTML trong `<iframe sandbox="" srcdoc>` và nút `Rollback` khôi phục revision tức thì.
- **Định Tuyến Nguồn SEO (`align_agent_seo_provider`)**:
  - Chỉ đăng ký cho LLM **đúng 1 tool ghi SEO** khớp với lựa chọn trong tab SEO (`save_seo_rank_math`, `save_seo_yoast`, `save_seo_theme_default`, hoặc không có tool ghi khi chọn `others`).
  - Mỗi adapter có guard dòng 1 kiểm hằng số (`RANK_MATH_VERSION`, `WPSEO_VERSION`, `Align_Seo`), trả `plugin_not_active` nếu plugin chưa bật, và tự động chạy `check_seo_output` (`wp_remote_get` + `DOMDocument`) ở cuối luồng ghi.
- **Media Picker & Drag-Drop Upload (v2)**:
  - **Media Picker & Upload (`assets/chatbox-media.js`, `assets/chatbox-upload.js`)**: Nút `#align-chatbox-upload-btn` (icon-only `dashicons-format-image`) mở `wp.media` modal chọn ảnh từ WordPress Media Library; hỗ trợ kéo-thả ảnh trực tiếp vào Drawer (`.align-chatbox-dropzone`) và dán ảnh từ clipboard trên textarea. Ảnh đính kèm hiển thị dạng thumbnail 48x48 kèm nút `×` để gỡ bỏ.
  - **Media Tools (`includes/tools/media/class-agent-tools-media.php`)**: Các tool `list_media` (đọc) và `set_featured_image`, `attach_media`, `sideload_media` (ghi, cần duyệt). `sideload_media` chỉ nhận URL `https` do chính user gõ trong hội thoại, đuôi `jpg|jpeg|png|webp|gif` (từ chối SVG), tối đa 5 MB, và kiểm tra MIME thật bằng `wp_check_filetype_and_ext`.
- **Quản Lý Phiên & API Tích Hợp (`/chat/session` & `window.AlignChatbox`)**:
  - **UI**: Thanh tiêu đề có 1 nút duy nhất `#align-chatbox-clear-btn` (`.button-danger` với icon thùng rác `dashicons-trash` và nhãn `Clear`) giúp xoá ngay phiên hiện tại và mở phiên mới chỉ với 1 click (không qua hộp thoại confirm).
  - **REST API (`GET|POST|DELETE /wp-json/align-agent/v1/chat/session`)**: Hỗ trợ **Dual Auth** (phiên Admin kèm `X-WP-Nonce` hoặc tích hợp ngoài qua `Authorization: Bearer <secret_key>` / `X-Align-Agent-Key: <secret_key>`). `GET` trả về thông tin phiên hiện tại (`session_id`, `message_count`, `pending_count`, `messages`); `POST`/`DELETE` xóa sạch phiên cũ (`Align_Agent_Conversation::delete_session()`) và khởi tạo `session_id` mới.
  - **JS Integration API (`window.AlignChatbox`)**: Cung cấp các hàm `open()`, `close()`, `toggle()`, `isOpen()`, `getSessionId()`, `getSession()`, `newSession()`, `deleteSession()`, `sendMessage(text)`, `continueRun()`, `reloadHistory()` và bắn sự kiện `CustomEvent('align-chatbox:session-cleared', { detail })` trên `document`.
- **Rich HTML Rendering & An Toàn (`assets/chatbox-render-enhance.js`, `assets/chatbox-content.css`)**:
  - Phản hồi Markdown được parse qua `marked` (v12.0.2) và lọc nghiêm ngặt qua `DOMPurify` (v3.1.6).
  - Tự động hạ cấp heading (`h1/h2` thành `h3/h4`) trong bóng chat để bảo vệ thứ bậc SEO heading của trang.
  - Khối code được bọc thanh công cụ: hiển thị ngôn ngữ, nút Copy vào clipboard, và nút Preview iframe sandbox cho khối mã HTML.
  - Bảng dữ liệu được bọc tự động trong `.align-chat-table-wrap` để cuộn ngang mượt mà.
  - Tin nhắn người dùng tự động phân tích và hiển thị dải thumbnail ảnh đính kèm.
- **Hệ Thống Button Tinh Gọn 28px & Bong Bóng Thinking AI (v2.1)**:
  - **Button 28px Đồng Bộ**: Khóa cứng chiều cao `28px` cho toàn bộ nút bấm trong Chatbox (`.align-agent-chatbox .button`), đồng bộ chuẩn xác giữa nút icon (`28px × 28px`) và nút text (`padding: 0 10px`), giải quyết triệt để lỗi chênh lệch baseline và tràn kích thước giao diện.
  - **Bong Bóng Thinking Dạng Reply Của AI**: Chuyển trạng thái xử lý (`Thinking...`, `Running tool...`) từ thanh status lơ lửng ngoài footer thành bong bóng tin nhắn phản hồi AI động (`.align-chat-msg.align-chat-msg-assistant.align-chat-msg-thinking`) bên trong danh sách tin nhắn. Trạng thái tự động cuộn theo luồng chat và tự hủy khi nhận được phản hồi chính thức từ LLM.
  - **Chuẩn Hóa Text Placeholder**: Rút gọn văn bản trạng thái rỗng và placeholder ô nhập liệu thành câu ngắn trên 1 dòng để giữ bố cục drawer luôn gọn gàng và thoáng đãng.

---

## 🚀 Hệ Thống Tool Calling Mở Rộng v2 (Milestones M0–M7)

Phiên bản v2 mở rộng năng lực của Align Agent thành hệ điều hành quản trị site toàn diện qua Chatbox với 57 tools function calling (xem danh mục kỹ thuật chi tiết tại [`includes/tools/README.md`](includes/tools/README.md)):

| Nhóm | Phân Loại | Tools Chính | Mô Tả & Bảo Vệ |
|---|---|---|---|
| **Content** (M1) | Posts, Pages, CPTs | `list_post_types`, `list_posts`, `get_post`, `create_post`, `save_post`, `set_post_status`, `trash_post`, `restore_post`, `duplicate_post`, `update_post_attributes` | Quản lý toàn bộ bài viết, trang, custom post types. Hỗ trợ hẹn giờ đăng bài tương lai; cấm xóa vĩnh viễn (chỉ dùng `wp_trash_post`). |
| **Taxonomy** (M2) | Danh mục & Thẻ | `list_terms`, `create_term`, `update_term`, `delete_term`, `assign_terms` | Quản lý categories, tags, CPT taxonomies. Chặn xóa term đang chứa bài viết (`term_not_empty`). |
| **Structure** (M3) | Templates & Blocks | `list_templates`, `get_template`, `save_template`, `list_reuse_blocks`, `get_reuse_block`, `create_reuse_block`, `save_reuse_block`, `set_homepage`, `update_site_identity` | Quản lý FSE Templates, Template Parts, Reusable Blocks. Cảnh báo tác động toàn trang; quét shortcode & Gutenberg block native; bảo vệ khóa Gutenberg. |
| **SEO & Redirects** (M4) | SEO & Chuyển Hướng | `save_seo_*`, `list_redirects`, `create_redirect`, `delete_redirect`, `list_404_logs`, `create_redirect_from_404` | Tích hợp sâu Rank Math DB; quản lý 404 logs và tạo chuyển hướng tức thì; chặn loop redirect trùng lặp. |
| **Media** (M5) | Tệp Tin & Ảnh | `list_media`, `update_media_meta`, `trash_media`, `set_featured_image`, `attach_media`, `sideload_media` | Cập nhật ALT/title/caption; chặn xóa ảnh đang được nhúng trong bài viết hoặc làm ảnh đại diện. |
| **Comments & Users** (M6) | Bình Luận & Thành Viên | `list_comments`, `moderate_comment`, `reply_comment`, `list_users`, `create_user`, `update_user_role`, `update_user_profile`, `send_password_reset`, `delete_user` | Kiểm duyệt bình luận; quản lý thành viên đầy đủ với các lớp bảo vệ nghiêm ngặt: chặn tự hạ quyền/tự xóa, bảo vệ Admin cuối cùng, không để lộ mật khẩu trong chat. |
| **Operations** (M7) | Vận Hành Hệ Thống | `flush_cache`, `site_overview` | Xóa bộ nhớ đệm toàn hệ thống; báo cáo tổng quan số lượng bài, bình luận, lỗi 404 và cập nhật WordPress Core. |
| **Core Platform** (M0) | Nền Tảng Điều Khiển | `Align_Agent_Tool_Registry`, `Align_Agent_Audit_Log`, `MAX_CONTINUE_TURNS = 20` | Kiểm soát quyền theo từng tool (`capability`); lưu vết audit log và snapshot (tối đa 100 bản ghi); thẻ duyệt nguy hiểm `align-chat-card-danger` viền đỏ (`role="alert"`). |

