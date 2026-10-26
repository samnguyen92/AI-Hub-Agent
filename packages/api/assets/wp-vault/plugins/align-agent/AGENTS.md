# 🤖 AI Agent & Developer Execution Rules — Align Agent Plugin

> **MANDATORY POLICY**: Mọi AI Agent khi làm việc với plugin **Align Agent** bắt buộc phải tuân thủ nghiêm ngặt các quy tắc dưới đây:

---

## 🧭 1. Quy Chuẩn Admin Menu Hook (Align Ecosystem Standard)

Plugin hoạt động độc lập (Standalone), nhưng khi chạy trên website có **Align Theme**, plugin **BẮT BUỘC** phải tự động gắn vào menu cha của Align Theme ở dưới cùng sidebar WordPress:

```php
/**
 * Register admin menu with Context-Aware parent detection
 */
public static function register_menu(): void {
    // 1. Tự động nhận diện Align Theme
    $parent_slug = defined( 'ALIGN_CORE_PATH' ) ? 'align-settings' : 'options-general.php';

    // 2. Luôn dùng add_submenu_page, TUYỆT ĐỐI KHÔNG dùng add_options_page()
    add_submenu_page(
        $parent_slug,
        __( 'Align Agent Gateway', 'align-agent' ),
        __( 'Align Agent', 'align-agent' ),
        'manage_options',
        self::MENU_SLUG,
        array( __CLASS__, 'render_page' )
    );
}
add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
```

### 🚫 Điều Cấm (Strict Prohibitions):
1. **CẤM** gọi `add_options_page()` vì hàm này ép cứng menu cha vào `options-general.php` (Settings).
2. **CẤM** bỏ qua Sandbox Dry-Run Validator trước khi thực thi lệnh ghi dữ liệu tự động từ AI hoặc Telegram Bot.
3. **CẤM** bỏ qua phê duyệt cho thao tác global: Chỉ thao tác global (`Align_Agent_Approval_Policy::classify() === 'approve'`) bắt buộc duyệt. Trang publish chỉ sửa qua clone nháp.

---

## ⚡ 2. Quy Chuẩn Kỹ Thuật (Coding Standards)
1. **Giới Hạn File (< 500 dòng/file)**: Đảm bảo SRP và phân rã các class xử lý gateway. Ví dụ: luồng ghép nối Telegram tách riêng tại `includes/class-gateway-telegram-pairing.php`, KHÔNG nhồi vào `class-gateway-telegram.php`.
2. **Observability**: Ghi log chi tiết `error_log( '[Align Agent] ...' )` cho toàn bộ webhook, REST call, và AI generation.
3. **Ngôn ngữ**: 100% comments, logs, UI strings viết bằng tiếng Anh chuẩn.

---

## 🎨 3. Quy Chuẩn Giao Diện Settings (Settings UI Standard)

1. **Cổng Khóa Kích Hoạt License & Bố Cục 4 Tab Ngang**:
   - Khi chưa kích hoạt License (`! Align_Gateway_Admin::is_license_active()`), trang Settings chỉ render **License Activation Gate** (`POST <middleware_url>/api/license/activate` với `key` dạng `ak_...` và `domain` chuẩn hóa), đồng thời ẩn hoàn toàn thanh tab và không nạp Global Chatbox Drawer/FAB.
   - Khi đã kích hoạt License, trang Settings hiển thị đúng **4 tab ngang** (`Bridge`, `MCP Tools`, `SEO`, `Updates`) dùng `.align-nav-tab[data-align-tab]` + `.align-agent-tab-panel[data-align-panel]`, mỗi lần chỉ hiện 1 panel (JS switcher trong `assets/admin.js`). Đã loại bỏ các tab cũ (`Authentication`, `Telegram`, `AI Provider`, `Sandbox Test`, `API Reference`) và xóa `views/tab-telegram.php`.
   - Tab **Bridge** hợp nhất thanh trạng thái License (`Active`, `Site ID`, `Domain`, `Key`, nút `Change Key`) và 3 thẻ cấu hình: `1. Telegram Bot & Pairing`, `2. AI / LLM Provider`, `3. Middleware Hub & WordPress MCP Auth` (gộp `Approval Mode` `auto`/`manual` vào chung thẻ số 3).
   - Đồng bộ cấu hình sang Middleware qua `POST <middleware_url>/api/license/config` với headers `X-Api-Key` và `X-Site-Domain` (không dùng `BRIDGE_ADMIN_TOKEN`). Tự động gọi `Align_Gateway_Admin::maybe_auto_sync_bridge()` ngay sau khi Activate License và sau khi Pair Telegram 6 số thành công; tự động hạ scheme `mcp.url` xuống `http` cho các domain nội bộ (`.local`, `.test`, `localhost`, `127.0.0.1`) để tránh lỗi SSL tự ký trên Node.js Middleware.
2. **Căn chỉnh icon**: Dashicons trong nút và nav tab BẮT BUỘC theo rule gộp 16px (`line-height: 1`, `flex-shrink: 0`, target cả `::before`) trong `assets/admin.css`. Tham chiếu Align Settings Style Guide §3.3–§3.4. CẤM khai báo kích thước icon rải rác từng selector hoặc inline.
3. **Telegram Pairing**: Dùng cặp AJAX `align_agent_pair_start` + `align_agent_pair_poll` (nonce `align_agent_admin_nonce`). Chỉ chat gửi đúng mã 6 số mới được bind Chat ID và tự động kích hoạt `maybe_auto_sync_bridge()`.
4. **Telegram Runner & Channel Isolation**:
   - Telegram chạy chung động cơ `Align_Agent_Loop` như Chatbox nhưng được phân lập phiên hoàn toàn qua `Align_Agent_Conversation::set_channel('telegram')` và lưu trữ dưới user meta key `align_agent_conversation_telegram`.
   - Thao tác thực thi dưới danh nghĩa **1 Administrator cố định** cấu hình tại Settings (`align_agent_telegram_user_id`).
   - Webhook trả `200 OK` tức thì và xử lý nền qua `shutdown` (FastCGI finish request hoặc async loopback HMAC). Callback query nhận Immediate 200 ACK với text "Processing..." và thực thi logic nặng qua `shutdown`. Chống trùng update_id bằng transient TTL 1h (`align_agent_tg_upd_{id}`) và khóa đơn luồng mutex `Align_Gateway_Telegram_Lock` (TTL 900s, stale recovery & gia hạn lease sau mỗi turn).
   - Thao tác ghi (`needs_approval`) hiển thị inline keyboard với nút `✅ Approve` và `❌ Cancel`, callback_data định dạng `a|{pending_id}` hoặc `c|{pending_id}`. Khi có từ 2 thao tác ghi trong cùng lượt, tự động gộp thành thẻ batch (`A|{batch_id}` / `C|{batch_id}`). Khi chạm giới hạn lượt xử lý tự động (`limit_reached: true`), gửi kèm nút `▶️ Continue` (`callback_data = k|{conversation_id}`, xử lý qua `Align_Gateway_Telegram_Continue`) để người dùng chạy tiếp thêm 1 đợt lượt mới từ ngữ cảnh hiện tại. Khi người dùng gửi câu lệnh mới hoặc có thẻ mới, các thẻ cũ được chuyển sang trạng thái `superseded` (`mark_superseded()`, gỡ nút bấm và sửa nội dung thành `⏭ Superseded by a new message.`). Chỉ Chat ID được cấp quyền mới có thể bấm nút duyệt.
   - Hỗ trợ các lệnh tích hợp: `/new` (reset phiên Telegram và dọn hàng đợi ảnh), `/status` (kiểm tra trạng thái phiên, số ảnh chờ, và danh sách hàng đợi duyệt), `/help` (hướng dẫn sử dụng) cùng typing action trước mỗi lượt suy nghĩ.
   - **Telegram Media Intake & Staging (`Align_Gateway_Telegram_Media`)**:
     - Hỗ trợ ảnh `photo` và tệp đính kèm `document` (chỉ nhận `image/jpeg|png|webp|gif`, kích thước `<= 5 MB`, cấm tuyệt đối SVG).
     - Tải tệp an toàn phía server qua `getFile` và `download_url`, kiểm tra MIME thực tế bằng `wp_check_filetype_and_ext`, sideload vào Media Library dưới danh nghĩa Administrator liên kết.
     - Tuyệt đối CẤM log hoặc đưa URL chứa Telegram bot token (`https://api.telegram.org/file/bot<TOKEN>/...`) ra ngoài log hay ngữ cảnh AI.
     - Hàng đợi `pending_attachments` theo kênh `telegram` với TTL 30 phút, tối đa 10 ảnh. Ảnh không kèm caption sẽ được lưu và chờ chỉ dẫn kế tiếp; khi có chỉ dẫn hoặc caption, hàng đợi được tự động tiêu thụ thành các thẻ chuẩn `[Attached Media: attachment_id=..., url=..., file=...]`.
   - **Streaming & Timeouts (`Align_Gateway_AI_Stream`)**:
     - Sử dụng SSE streaming qua cURL với idle timeout 45s (`CURLOPT_LOW_SPEED_TIME = 45`) và trần tổng thời gian phân lập theo kênh: 300s cho Telegram runner chạy nền và 90s cho Chatbox trực tiếp.
     - Tự động gộp các delta `content` và `tool_calls` theo chỉ mục `index`; ghi nhận chi tiết metrics `ttfb_ms`, `total_ms`, `bytes`, `chunks`, `tool_args_bytes` trong log. Tự động fallback sang JSON non-streaming khi thiếu cURL hoặc provider trả phản hồi chuẩn HTTP non-SSE.
5. **WP Admin Chatbox**:
   - Giao diện Global Slide-in Drawer (`#align-agent-chatbox`, width 420px, height 100vh phủ kín viewport kể cả `#wpadminbar`, `z-index: 100001`) kèm lớp nền mờ Blur Backdrop (`#align-chatbox-backdrop`, `backdrop-filter: blur(4px)`, `z-index: 100000`) và Floating Action Button (`#align-chatbox-fab`, góc dưới phải, `z-index: 99998`), render toàn cục qua `admin_footer` cho người dùng có quyền `manage_options`.
   - Lazy Load: Chỉ tải lịch sử hội thoại (`loadHistory()`) ở lần đầu tiên mở Drawer. Tự động đóng Drawer khi nhấn phím `Escape`, bấm backdrop hoặc bấm nút đóng `#align-chatbox-close-btn`; tự động focus input khi mở và hoàn trả focus về FAB khi đóng.
   - Trang Settings (`?page=align-agent`): Giải phóng `.align-agent-workspace` thành 1 cột full-width, gỡ bỏ cột chatbox tĩnh và dùng chung Drawer toàn cục.
   - Mọi thao tác ghi trang từ Chatbox bắt buộc đi qua `Align_Builder_Crud_Handler` / `Align_Builder_Revision_Handler`, kiểm tra `Align_Editor_Mode_Lock::is_locked_to( $post_id, 'gutenberg' )`, chặn `<script>` trong HTML, chạy `Align_Gateway_Validator::validate_markup()`, và yêu cầu người dùng duyệt (`needs_approval`). Policy tự ghi chỉ áp dụng cho kênh Telegram; Chatbox giữ nguyên 100% manual gate duyệt cho mọi thao tác ghi.
   - **Dynamic Single-Tool SEO Routing (`align_agent_seo_provider`)**: `Align_Agent_Tools_Seo` chỉ đăng ký đúng 1 tool ghi SEO khớp với provider đã chọn (`save_seo_rank_math`, `save_seo_yoast`, `save_seo_theme_default`, hoặc không đăng ký tool ghi khi chọn `others`), kèm `check_seo_output` (read-only). Các hàm ghi trong `Align_Agent_Seo_Adapters` bắt buộc có guard dòng 1 kiểm hằng số (`RANK_MATH_VERSION`, `WPSEO_VERSION`, `class_exists('Align_Seo')`) và trả `plugin_not_active` nếu plugin chưa bật.
   - **Media Upload Security (M1 & M2)**:
     - **M1 (`assets/chatbox-upload.js`)**: Tải ảnh qua `POST /wp-json/wp/v2/media` kèm `X-WP-Nonce`, kiểm tra client-side (`image/jpeg`, `image/png`, `image/webp`, `image/gif`, `<= 5 MB`) và hiển thị thông báo lỗi inline (`role="alert"`).
     - **M2 (`includes/tools/media/class-agent-tools-media.php`)**: `list_media` (read) và `set_featured_image`, `attach_media`, `sideload_media` (write, `needs_approval`). `sideload_media` bắt buộc đối chiếu URL `https` có mặt nguyên văn trong tin nhắn của user, chỉ nhận đuôi `jpg|jpeg|png|webp|gif` (cấm SVG), `<= 5 MB`, và kiểm tra MIME thực bằng `wp_check_filetype_and_ext`.
   - **Session Management & Dual-Auth API**: Endpoint `GET|POST|DELETE /wp-json/align-agent/v1/chat/session` hỗ trợ Dual Auth (Admin cookie + `X-WP-Nonce` hoặc `Authorization: Bearer <secret_key>` / `X-Align-Agent-Key`). Trên UI thanh tiêu đề có 1 nút duy nhất `#align-chatbox-clear-btn` (`.button-danger` với icon `dashicons-trash` và nhãn `Clear`) giúp xoá ngay phiên hiện tại và khởi tạo phiên mới không qua xác nhận trung gian; đồng bộ với `window.AlignChatbox` (`getSessionId`, `getSession`, `newSession`, `deleteSession`, `sendMessage`, `continueRun`, `reloadHistory`) và sự kiện `align-chatbox:session-cleared`. Khi chạm giới hạn lượt (`limit_reached: true`), hiển thị bong bóng trạng thái (`role="status"`) kèm nút `Continue` (`data-action="continue-run"`, xử lý qua `assets/chatbox-continue.js`) để chạy tiếp thêm 20 lượt.
   - **Media Picker, Drag-Drop & Thumbnail Chips (v2)**:
     - **Media Picker & Drag-Drop (`assets/chatbox-media.js`)**: Nút `#align-chatbox-upload-btn` (icon-only `dashicons-format-image`) mở `wp.media` modal chọn ảnh từ WordPress Media Library; hỗ trợ kéo-thả ảnh trực tiếp vào Drawer (`.align-chatbox-dropzone`) và dán ảnh từ clipboard (Paste) trên textarea.
     - **Thumbnail Chips (`assets/chatbox-upload.js`)**: Ảnh đính kèm hiển thị dạng thumbnail 48x48 kèm nút `×` để gỡ; API upload trả về `{ id, url, thumb, filename }` và format payload AI không đổi.
   - **Rich HTML Render & Enhancer (`assets/chatbox-render-enhance.js`, `assets/chatbox-content.css`)**:
     - DOMPurify mở rộng allowlist thẻ và thuộc tính an toàn, chặn hoàn toàn scheme độc hại (`javascript:`, `data:`).
     - Tự động hạ cấp heading (`h1` -> `h3`, `h2` -> `h4`) trong bubble để bảo vệ quy chuẩn SEO 1 `h1`/trang.
     - Bọc `pre > code` với thanh công cụ (Language badge + Nút Copy clipboard + Nút Preview iframe sandbox đối với block HTML).
     - Tự động bọc bảng `table` trong `.align-chat-table-wrap` cuộn ngang.
     - Phân tích và hiển thị dải thumbnail ảnh đính kèm trong bóng chat người dùng.
   - **Bố Cục Nút & Trạng Thái Xử Lý (v2.1)**:
     - **Hệ Thống Button Tinh Gọn 28px**: Tất cả button trong chatbox (`.align-agent-chatbox .button`) khóa cứng chiều cao `28px` (`min-height: 28px`, `max-height: 28px`, `line-height: 1`, `box-sizing: border-box`), căn chỉnh đồng bộ tuyệt đối giữa nút icon (`28px × 28px`) và nút text (`padding: 0 10px`).
     - **Bong Bóng Thinking Dạng AI Reply**: Trạng thái `Thinking...` và `Running tool...` hiển thị trực tiếp trong luồng hội thoại dưới dạng bong bóng assistant `.align-chat-msg.align-chat-msg-assistant.align-chat-msg-thinking` (kèm spinner và nhãn trạng thái), tự động cuộn xuống cuối và tự hủy khi nhận phản hồi chính thức từ LLM (thay thế hoàn toàn thanh trạng thái tách biệt cũ).
     - **Bản Tin & Placeholder Ngắn Gọn**: Text trạng thái rỗng và placeholder ô nhập liệu được chuẩn hóa ngắn gọn trên 1 dòng để chống vỡ bố cục trên Drawer 420px.
   - Log phía JS dùng prefix `[Align UI]`, `[Align DragDrop]` và phía PHP dùng `[Align Agent]`, `[Align Builder]`.

---

## 🛠️ 4. Quy Chuẩn Tool Calling & Kiến Trúc v2 (M0–M7 Standard)

1. **Nguyên Tắc Thực Thi Nội Bộ (Zero HTTP & SSOT)**:
   - Gọi nội bộ, không gọi HTTP: Tạo `WP_REST_Request` rồi gọi thẳng static handler của Align Theme (`Align_Builder_Crud_Handler`, `Align_Builder_Content_Handler`, `Align_Builder_Revision_Handler`).
   - Nguồn chân lý duy nhất (SSOT): Toàn bộ trang, bài viết, CPT, `wp_block`, `wp_template`, `wp_template_part` đều lưu tại `wp_posts.post_content`. File `.html` trong `templates/` và `parts/` chỉ là mirror do `persist_template()` tự đồng bộ.
   - Tuyệt đối CẤM chạy truy vấn SQL thô hoặc ghi file trực tiếp vào theme/assets.

2. **Quy Chuẩn Tool Registry & Phân Quyền (`Align_Agent_Tool_Registry`)**:
   - Mọi tool bắt buộc khai báo `capability`, `group` (`content`, `taxonomy`, `structure`, `site`, `seo`, `media`, `comments`, `users`, `ops`, `i18n`), `requires_builder` (bool), và `danger` (bool).
   - Kiểm tra `current_user_can( $capability )` trước khi thực thi tool. Trả mã lỗi `forbidden` khi không đủ quyền.
   - Khi Builder không kích hoạt, tự động lọc bỏ các tool có `requires_builder === true` khỏi OpenAI Schema. Tool dùng WordPress Core vẫn hoạt động bình thường.
   - Giới hạn lượt tiếp tục tự động: `MAX_CONTINUE_TURNS = 20` trên cả Frontend JS và Backend PHP.

3. **Quy Chuẩn An Toàn & Bảo Mật Thao Tác Ghi**:
   - Mọi thao tác ghi nội dung bắt buộc chạy qua `guard_html_and_css` (chặn triệt để thẻ `<script>`) và `Align_Gateway_Validator::validate_markup()`.
   - Kiểm tra khóa chế độ soạn thảo: `Align_Editor_Mode_Lock::is_locked_to( $post_id, 'gutenberg' )`.
   - **Xóa an toàn**: Tuyệt đối CẤM gọi `wp_delete_post( $id, true )` đối với nội dung của người dùng (chỉ cho phép `wp_trash_post()`). Ngoại lệ duy nhất: chỉ `Align_Agent_Global_Vault` và `Align_Agent_Vault_Gc` được phép gọi `wp_delete_post( $id, true )` trên các post nội bộ thuộc CPT ẩn `align_agent_backup` và `align_agent_bk_chunk` khi rollback mảnh lỗi hoặc dọn rác vault. Với Media, chỉ cho phép đưa vào thùng rác khi hằng số `MEDIA_TRASH` được bật và ảnh không đang được dùng (`_thumbnail_id` hoặc URL trong `post_content`).
   - **Backup Gate Fail-Closed & Global Vault (`Align_Agent_Write_Executor`, `Align_Agent_Global_Vault`, `Align_Agent_Vault_Gc`)**:
     - Mọi thao tác ghi có undo adapter (`post`, `template`, `option`) bắt buộc chụp snapshot trước khi ghi (`Align_Agent_Undo_Resolver` + `Align_Agent_Undo_Adapters::capture()`). Nếu snapshot rỗng hoặc không phân giải được ID hợp lệ (`id === 0` và `type !== 'theme_file'`), chặn đứng thao tác ghi và trả `backup_failed`.
     - Các đối tượng global (`tpl:<slug>`, `tp:<slug>`, `block:<id>`, `option:align_custom_css|js`, `option:align_tracking_code|body_open_code|footer_html_code`) bắt buộc lưu vào Global Vault (CPT ẩn `align_agent_backup` / `align_agent_bk_chunk`, `can_export => false`, 0 dòng `wp_postmeta`, nén gzip + base64 chia mảnh 512 KB, kiểm tra đọc lại & đối chiếu MD5 trước khi ghi, chặn nội dung > 20 MB với `backup_too_large`).
     - Sau khi ghi đối tượng global, tự động chạy `Align_Agent_Global_Vault::verify_after()` để đối chiếu MD5; nếu lệch thì tự động khôi phục từ vault và trả `write_verify_failed`.
     - Giới hạn lưu trữ Global Vault: tối đa 10 bản/đối tượng, 500 bản/site, 50 MB dữ liệu nén, TTL 7 ngày (luôn giữ tối thiểu 3 bản mới nhất mỗi đối tượng), đánh dấu `foreign` và cấm restore khi khác `home_url`. Hỗ trợ nút `Purge Backups` tại tab `Updates` (`align_agent_vault_purge`).
   - **Bản Đồ Trang & Working Notes (`Align_Agent_Tools_Render_Map`, `Align_Agent_Working_Notes`, `Align_Agent_Prompt_Rules`)**:
     - Cung cấp `inspect_render` và `find_in_render` (group `structure`, read-only) để định vị chính xác template, template part theo area (`tp_header`, `tp_footer`), reuse block, global CSS/JS và hàm JS mà tuyệt đối không đọc file PHP.
     - Tự động lưu tóm tắt cấu trúc trang vào Working Notes theo kênh (`align_agent_notes_{channel}`, tối đa 1 KB) và chèn vào system prompt cùng bộ quy tắc `RENDER MODEL`, `DIAGNOSE-FIRST RULE`, `GLOBAL-SAFE RULE`.
   - **Bảo vệ Quản Trị Viên (M6)**: Cấm tự xóa hoặc tự hạ quyền tài khoản đang thao tác (`self_demotion_forbidden`). Chặn xóa hoặc hạ quyền Administrator cuối cùng của hệ thống (`last_admin_protection`). Tuyệt đối không trả mật khẩu hoặc link reset token trong tool output.
   - **Thẻ Phê Duyệt Nguy Hiểm (`danger: true`)**: Gán cờ `danger: true` cho mọi thao tác sửa Template Part/Header/Footer, xóa dữ liệu, hoặc cấp quyền `administrator`. Hiển thị viền đỏ cảnh báo, icon Dashicons `\f534`, thuộc tính `role="alert"`, và liên kết trợ năng `aria-describedby`.
   - **Audit Log & Snapshot (`Align_Agent_Audit_Log`)**: Mọi thao tác ghi sau khi duyệt đều được lưu vết vào option `align_agent_audit_logs` (tối đa 100 bản ghi, không autoload) kèm cơ chế snapshot để hỗ trợ hoàn tác.
   - **Tách Biệt Trạng Thái Chờ Duyệt (`Align_Agent_Pending_Store`)**: Lưu trữ từng mục chờ duyệt dưới dạng option riêng `align_agent_pw_{id}` (autoload: false, TTL 900s) kèm khóa mutex nguyên tử (`align_agent_pw_claim_{id}`) chống race condition click kép; tách hoàn toàn khỏi blob hội thoại để không bị ghi đè dữ liệu.
   - **Clone Nháp Cho Trang Đã Publish (`Align_Agent_Page_Clone`)**: Tự động nhân bản nháp trang live để chỉnh sửa (`_align_agent_clone_of`), dùng chung 1 clone cho các lần sửa liên tiếp trong lượt, kiểm tra xung đột `base_changed` trước khi apply vào trang gốc, và tự động chuyển clone vào thùng rác (`wp_trash_post`) cùng dọn sạch CSS/JS assets.
   - **Báo Cáo Tự Động Chuẩn ASD-STE100 (`Align_Agent_Run_Report`)**: Báo cáo tổng kết cuối lượt do server dựng từ sự kiện thực tế (`created`, `edited`, `edited_clone`, `uploaded`, `needs_approval`, `failed`), tuân thủ nghiêm ngặt chuẩn ASD-STE100 (mỗi câu <= 20 từ, thể chủ động, loại bỏ hoàn toàn ID kỹ thuật và thuật ngữ khó hiểu).
   - **Ghi Cấu Hình An Toàn & Che Dấu Dữ Liệu Nhạy Cảm**: Mọi thao tác ghi WordPress options bắt buộc đi qua `Align_Agent_Option_Writer` (snapshot, update, audit logging). Công cụ `set_site_protection` nghiêm cấm thay đổi `user`/`password`. Mọi dòng log trong `site_health` bắt buộc chạy qua bộ lọc `redact_line` để che mờ bot token và secret.

---

## 🗂️ 5. Quy Chuẩn Tổ Chức Thư Mục Tool (Tool Folder Standard)

1. **Vị Trí Cố Định**: Toàn bộ tool của Chatbox chỉ được đặt trong `includes/tools/<group>/` (với `<group>` là registry group: `content`, `taxonomy`, `structure`, `site`, `seo`, `media`, `comments`, `users`, `i18n`). Tuyệt đối CẤM tạo file `includes/class-agent-tools-*.php` mới tại thư mục gốc `includes/`.
2. **Khai Báo Manifest Bắt Buộc**: Mọi class tool mới bắt buộc phải khai báo đường dẫn tương đối và tên class vào hằng số `Align_Agent_Tools_Loader::MANIFEST` trong `includes/tools/class-agent-tools-loader.php`.
3. **Đồng Bộ Tài Liệu**: Bất kỳ thay đổi, bổ sung hoặc loại bỏ tool nào phải được cập nhật đồng thời vào bảng tổng quan `includes/tools/README.md` và tệp chi tiết `includes/tools/<group>/README.md` trong cùng một commit.




