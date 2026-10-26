# Kế Hoạch Triển Khai — Convert to Telegram v4 (Mở Rộng Quản Trị Website)

> Kế thừa `convert-to-telegram-v3.md` (approval policy, clone nháp, pending store, run report). v4 bổ sung các tool quản trị còn thiếu thuộc **nhóm P1 và P2**. Nhóm P3 (plugin/theme install, backup/import, widgets, fonts, image sizes) bị loại vì phải xử lý local, không điều khiển qua Telegram.
>
> **Worker AI đọc file này xong thì code luôn theo đúng thứ tự Mốc 0 → Mốc 8.** Mỗi mốc có file, chữ ký hàm, guard và lệnh kiểm chứng. Không hỏi lại các điểm đã chốt ở mục "Quyết định đã chốt".

---

## 0. Bối Cảnh & Bằng Chứng

### 0.1 Hiện trạng (đã quét 21-Oct-2026)

- 63 tool trong `includes/tools/README.md`. 17 REST route trong `includes/class-gateway-router.php` + `includes/class-agent-chat-controller.php`.
- `includes/tools/README.md` §7 tự ghi chưa hỗ trợ: menu, theme settings, plugin, widget.
- Site là **block theme** (`wp_is_block_theme() === true`). `get_registered_nav_menus() === []`. 0 bản ghi `wp_navigation`.
- Menu thật nằm trong HTML template part: `themes/Align-Real-Builder/align-child/parts/header.html` có 2 thẻ:
  - `<nav class="sh-nav" aria-label="Menu chính">` (desktop)
  - `<nav class="sh-drawer-nav" aria-label="Menu di động">` (mobile drawer)
  - Hai nav lặp cùng danh sách link. Sửa tay bằng `patch_content` dễ lệch 2 bản.
- `option align_languages` hiện chỉ có `en` (default).

### 0.2 Khoảng trống được xử lý trong v4

| ID | Khoảng trống | Bằng chứng | Mốc |
|---|---|---|---|
| GAP-01 | Thiếu slot code `align_body_open_code`, `align_footer_html_code`, ghi `align_tracking_code`, `align_custom_code_skip_admins` | `includes/tools/site/class-agent-tools-global-code.php:120` chỉ đọc 3 option, tracking read-only | Mốc 2 |
| GAP-02 | Thiếu WP Settings (permalink, `blog_public`, timezone, format, reading, discussion) | Không tool nào gọi `update_option` cho các key này | Mốc 3 |
| GAP-03 | Thiếu tool menu điều hướng | Header nav là HTML trong `tp_header`; không có tool đồng bộ 2 nav | Mốc 4 |
| GAP-04 | Thiếu đa ngôn ngữ | `Align_Language_Manager` (theme `core/includes/core/class-align-language-manager.php:39-381`) có API nhưng plugin không gọi | Mốc 5 |
| GAP-05 | Thiếu Site Protection | `Align_Site_Protection` + 9 option `align_site_protect_*` (`admin/class-align-admin-settings.php:241-285`) | Mốc 6 |
| GAP-06 | Thiếu khoá mật khẩu từng trang | `Align_Post_Password_Lock::set_password()` / `disable_lock()` (`core/class-align-post-password-lock.php:275`, `:300`) | Mốc 6 |
| GAP-07 | Thiếu kiểm tra độ phủ SEO hàng loạt | Logic chỉ nằm trong AJAX `Align_Bulk_Seo_Generator::ajax_scan_items` (`admin/class-align-bulk-seo-generator.php:31-124`) | Mốc 7 |
| GAP-08 | Thiếu đọc Audit Log + Undo | `Align_Agent_Audit_Log::get_logs()` có sẵn, không có tool | Mốc 7 |
| GAP-09 | Thiếu chẩn đoán hệ thống (read-only) | `site_overview` chỉ có số liệu nội dung | Mốc 7 |

### 0.3 Tài nguyên tái sử dụng (đã xác minh)

| API | Vị trí | Ghi chú |
|---|---|---|
| `Align_Agent_Tool_Registry::register()` | `includes/class-agent-tool-registry.php:39` | Nhận `scope` (`global`/`local`), `danger`, `hidden`, `summary_callback` |
| `Align_Agent_Approval_Policy::classify()` | `includes/class-agent-approval-policy.php:28` | **Chưa** đọc `scope` từ registry. Mốc 0 sửa |
| `Align_Agent_Audit_Log::snapshot_options()` | `includes/class-agent-audit-log.php:112` | Trả `array<option_key, value>` |
| `Align_Agent_Audit_Log::log_action( $user_id, $tool, $object_id, $action_type, $details )` | `:45` | Undo đọc `details.before` |
| `Align_Agent_Patch_Engine::patch_string( $content, $find, $replace )` | `includes/class-agent-patch-engine.php:25` | Dùng cho `edits` của code slot |
| `Align_Agent_Content_Guard::check_js( $js )` | `includes/class-agent-content-guard.php:23` | Trả `warnings` |
| `Align_Agent_Tools_Templates::tool_get_template()` / `tool_save_template()` | `includes/tools/structure/class-agent-tools-templates.php:255`, `:275` | Public static. Nav tool gọi trực tiếp |
| `Align_Gateway_Pages::extract_decoupled_parts( WP_Post )` | `includes/class-gateway-pages.php:308` | Trả `html`, `css`, `js` |
| `Align_Builder_Crud_Handler::save_page( WP_REST_Request )` | theme builder | Mẫu dùng: `includes/tools/content/class-agent-tools-posts.php:412-420` |
| `Align_Agent_Run_Report::record_event()` | `includes/class-agent-run-report.php:234` | Fallback in tên tool thô → Mốc 8 thêm nhãn |
| `Align_Language_Manager::get_languages|get_language|get_default_lang|add_language|update_language|remove_language|get_post_lang|get_translations|create_translation` | theme `core/class-align-language-manager.php` | Trả `true|int|WP_Error` |
| `Align_Language_Url_Localizer::localize_html( $html, $lang )` | theme `core/class-align-language-url-localizer.php:97` | Pure function |
| `Align_Bulk_Url_Scanner::detect_unlocalized_urls( $html, $lang )` | theme `admin/class-align-bulk-url-scanner.php:203` | Class admin, có thể chưa load ở REST/shutdown |
| `Align_Site_Protection::get_current_environment()`, `normalize_domain()` | theme `core/class-align-site-protection.php` | Static |
| `Align_Post_Password_Lock::is_post_locked|set_password|disable_lock` | theme `core/class-align-post-password-lock.php:46`, `:275`, `:300` | Static |

---

## 1. Hiểu Yêu Cầu & Ranh Giới

- **Mục tiêu**: Telegram + Chatbox quản trị được toàn bộ nhóm P1 + P2 ở bảng 0.2 qua tool registry chuẩn.
- **Quyết định đã chốt**:
  1. Chỉ làm P1 + P2. P3 là non-goal.
  2. Giữ nguyên policy v3. Chatbox duyệt 100% thao tác ghi. Telegram chỉ duyệt thao tác `scope = global`.
  3. Mọi tool mới khai báo `scope` tường minh. Không dựa vào danh sách fallback `$global_names` trong registry.
- **Nghiệm thu** (chạy trên Telegram):
  - "Thêm link Pricing /pricing vào menu sau FAQ" → 1 thẻ duyệt đỏ → Approve → cả 2 nav trong header có link mới, cùng class với link cũ.
  - "Gắn mã Google Tag Manager vào body" → 1 thẻ duyệt đỏ, preview mã, cảnh báo JS (nếu có) → Approve → `align_body_open_code` đổi.
  - "Đổi permalink sang /%postname%/" → thẻ duyệt → Approve → rewrite rules được flush.
  - "Tạo bản tiếng Trung cho trang About" (sau khi đã có ngôn ngữ `zh`) → không có thẻ → trang draft mới có `_align_lang = zh`, có CSS builder.
  - "Bật bảo vệ staging" → thẻ duyệt đỏ có cảnh báo khoá site.
  - "Khoá trang #52 bằng mật khẩu" → trang publish nên có thẻ duyệt. Log và output không chứa mật khẩu.
  - "Trang nào thiếu SEO description?" → danh sách, không ghi gì.
  - "Hoàn tác thay đổi settings vừa rồi" → thẻ duyệt → option quay về giá trị cũ.
  - "Kiểm tra sức khoẻ site" → báo cáo PHP/WP/cron/disk, log đã che token.
- **Non-goals**:
  - Cài, bật, tắt, cập nhật plugin/theme. Backup, import, export. Widgets. Fonts. Image sizes. Thumbnail regen.
  - Đổi `admin_email`, `users_can_register`, `default_role`, `WPLANG`, `siteurl`, `home`.
  - Đổi user/password của Site Protection qua chat.
  - Gọi AI generator của theme (`Align_Bulk_Seo_Generator::ajax_process_item`) — tốn phí, agent tự viết meta qua `save_seo_*`.
  - Sửa `wp_nav_menu` classic hoặc `wp_navigation` (site không dùng).
  - Dịch nội dung tự động trong tool. Tool chỉ tạo bản nháp; LLM dịch bằng `patch_content`.

---

## 2. Đánh Giá Phương Án

### Điểm yếu (Hạn chế / Rủi ro)

- Code slot nhận `<script>` hợp lệ (GTM, GA). Mã độc từ prompt injection có thể lọt nếu User duyệt không đọc.
- `blog_public = 0` hoặc đổi permalink sai làm rơi index Google toàn site.
- Site Protection bật nhầm trên domain live khoá toàn bộ khách truy cập.
- Nav tool sửa HTML bằng DOMDocument. DOMDocument có thể đổi định dạng attribute hoặc entity trong fragment nav.
- `Align_Language_Manager::create_translation()` sao chép `post_status` của trang nguồn (có thể là `publish`) và đi thẳng `wp_insert_post`, bỏ qua builder asset.
- Mật khẩu khoá trang đi qua chat Telegram và nằm trong pending store tối đa 900s.
- Undo chỉ hỗ trợ thao tác có snapshot option. Không hoàn tác được nội dung trang (đã có `restore_revision`).
- Thêm 22 tool làm schema OpenAI dài hơn, tăng token mỗi lượt.

### Điểm mạnh (Ưu điểm / Khả thi)

- Theme đã có static API cho i18n, protection, password lock, URL localizer. Plugin chỉ bọc.
- `register_setting()` của theme gắn `sanitize_option_{key}`. `update_option()` tự chạy sanitizer gốc (`sanitize_custom_code`, `normalize_domain`, `absint`).
- Policy v3 có 1 điểm quyết định. Mốc 0 thêm 1 dòng đọc `scope` là mọi tool global mới tự cần duyệt.
- Audit snapshot có sẵn. Undo cho option chi phí thấp.
- Không route REST mới. Không đổi API công khai. Không đổi schema DB.

### Giải pháp & Lý do

* Giải pháp:* `classify()` trả `approve` khi registry `scope === 'global'`. -> *Lý do:* Tool global mới không cần sửa danh sách cứng.
* Giải pháp:* Tạo helper `Align_Agent_Option_Writer` (snapshot → `update_option` → audit). -> *Lý do:* Settings, code slot, protection và undo dùng chung 1 đường ghi.
* Giải pháp:* Nav tool cắt từng fragment `<nav>…</nav>` theo offset, parse riêng, ghép lại. -> *Lý do:* Phần HTML ngoài nav giữ nguyên từng byte.
* Giải pháp:* `create_translation` ép `draft` rồi ghi lại parts qua `save-page`. -> *Lý do:* Không publish ngoài ý muốn, có đủ CSS/JS builder theo slug mới.
* Giải pháp:* Tool khoá trang và protection không bao giờ trả hoặc log mật khẩu. -> *Lý do:* `AGENTS.md` §4.3 cấm lộ secret trong tool output.

---

## 3. Bảng Tool Mới (21 tool + 1 helper)

| Tool | File | Type | Group | Scope | Danger | Capability | Builder | Policy Telegram |
|---|---|---|---|---|---|---|---|---|
| `get_code_slots` | `site/class-agent-tools-code-slots.php` | read | `site` | local | no | `manage_options` | no | — |
| `save_code_slot` | `site/class-agent-tools-code-slots.php` | write | `site` | global | **yes** | `unfiltered_html` | no | approve |
| `get_site_settings` | `site/class-agent-tools-settings.php` | read | `site` | local | no | `manage_options` | no | — |
| `update_site_settings` | `site/class-agent-tools-settings.php` | write | `site` | global | **yes** | `manage_options` | no | approve |
| `list_nav_links` | `structure/class-agent-tools-navigation.php` | read | `structure` | local | no | `edit_theme_options` | no | — |
| `update_nav_links` | `structure/class-agent-tools-navigation.php` | write | `structure` | global | **yes** | `edit_theme_options` | **yes** | approve |
| `list_languages` | `i18n/class-agent-tools-i18n.php` | read | `i18n` | local | no | `edit_posts` | no | — |
| `get_translations` | `i18n/class-agent-tools-i18n.php` | read | `i18n` | local | no | `edit_posts` | no | — |
| `translation_overview` | `i18n/class-agent-tools-i18n.php` | read | `i18n` | local | no | `edit_posts` | no | — |
| `create_translation` | `i18n/class-agent-tools-i18n.php` | write | `i18n` | local | no | `edit_posts` | no | auto (approve nếu nguồn là front page) |
| `manage_language` | `i18n/class-agent-tools-i18n.php` | write | `i18n` | global | **yes** | `manage_options` | no | approve |
| `scan_unlocalized_urls` | `i18n/class-agent-tools-i18n-urls.php` | read | `i18n` | local | no | `edit_posts` | no | — |
| `localize_post_urls` | `i18n/class-agent-tools-i18n-urls.php` | write | `i18n` | local | no | `edit_posts` | **yes** | auto / auto_clone |
| `get_site_protection` | `site/class-agent-tools-protection.php` | read | `site` | local | no | `manage_options` | no | — |
| `set_site_protection` | `site/class-agent-tools-protection.php` | write | `site` | global | **yes** | `manage_options` | no | approve |
| `get_post_lock` | `content/class-agent-tools-post-lock.php` | read | `content` | local | no | `edit_posts` | no | — |
| `set_post_lock` | `content/class-agent-tools-post-lock.php` | write | `content` | local | no | `edit_posts` | no | approve nếu bài `publish`, còn lại auto |
| `audit_seo_coverage` | `seo/class-agent-tools-seo-audit.php` | read | `seo` | local | no | `edit_posts` | no | — |
| `list_audit_logs` | `site/class-agent-tools-audit.php` | read | `ops` | local | no | `manage_options` | no | — |
| `undo_audit_action` | `site/class-agent-tools-audit.php` | write | `ops` | global | **yes** | `manage_options` | no | approve |
| `site_health` | `site/class-agent-tools-health.php` | read | `ops` | local | no | `manage_options` | no | — |
| *(helper, không phải tool)* `Align_Agent_Option_Writer` | `site/class-agent-option-writer.php` | — | — | — | — | — | — | — |

Tổng sau v4: **63 + 21 = 84 tool** (helper không tính).

---

## 4. Quy Ước Code Bắt Buộc Cho Mọi File Mới

1. Header file giống `includes/tools/site/class-agent-tools-site.php:1-14` (docblock, `@package Align_Agent`, guard `ABSPATH`).
2. `public static function init(): void` gọi `Align_Agent_Tool_Registry::register()` cho từng tool.
3. Tool phụ thuộc class theme: kiểm `class_exists()` **trong callback**, không trong `init()`. Thiếu class → trả `array( 'success' => false, 'error' => 'theme_feature_unavailable', 'message' => ... )`. Lý do: theme load sau plugin.
4. Mỗi definition khai báo đủ: `type`, `group`, `scope`, `danger`, `capability`, `requires_builder`, `description`, `parameters`, `callback`, và `summary_callback` cho tool write.
5. `description` tiếng Anh, kết thúc bằng "Requires user approval." nếu `scope = global`.
6. Kết quả thành công luôn có `success => true`, `message`, và `ui_card => array( 'title', 'message' [, 'url'] )`.
7. Mọi tool write gọi `Align_Agent_Audit_Log::log_action()` với `details.before` + `details.after`.
8. Log: `error_log( '[Align Agent] <Tool>: ...' )`. Cấm log giá trị mật khẩu, token, toàn văn code slot.
9. Mỗi file < 500 dòng. Gần 450 dòng thì tách file.
10. Comment, log, UI string 100% tiếng Anh.
11. Không SQL thô. Không ghi file vào theme. Không gọi HTTP loopback (trừ `site_health` không gọi HTTP).

---

## 5. Mốc Thực Thi

### Mốc 0: Nền tảng — policy theo scope, helper ghi option, folder i18n (REQ-00)

- **File**:
  - `includes/class-agent-approval-policy.php` → `evaluate_telegram_policy()`, khối registry (dòng ~48-60). Thêm sau kiểm `users`:
    ```php
    // Global scope tools always require approval.
    if ( 'global' === ( $def['scope'] ?? 'local' ) ) {
        return 'approve';
    }
    ```
    - Thêm 3 nhánh trước bước 7 (`return 'auto'`):
      ```php
      // set_post_lock: changing access on a live page is global.
      if ( 'set_post_lock' === $tool ) {
          $post_id = absint( $args['post_id'] ?? 0 );
          return ( $post_id > 0 && self::is_published_post( $post_id ) ) ? 'approve' : 'auto';
      }
      // create_translation: front page translation rewrites the language root page.
      if ( 'create_translation' === $tool ) {
          $source_id = absint( $args['source_id'] ?? 0 );
          return ( $source_id > 0 && $source_id === (int) get_option( 'page_on_front' ) ) ? 'approve' : 'auto';
      }
      ```
    - Thêm `'localize_post_urls'` vào mảng `$content_write_tools` (bước 6). Bài publish → `auto_clone`.
    - Hồi quy: các tool cũ có `scope = global` (đã tính trong registry `:52-58`) đều đã `approve` ở v3. Nhánh mới không đổi kết quả của chúng.
  - `includes/tools/site/class-agent-option-writer.php` (mới) — `final class Align_Agent_Option_Writer` (không có `init()` tool; loader gọi `init()` rỗng):
    ```php
    /**
     * Snapshot, write and audit a set of options atomically (best effort).
     *
     * @param string $tool    Tool name for the audit entry.
     * @param array  $changes array<option_key, new_value>. Keys must already be whitelisted by caller.
     * @param array  $mask    Option keys whose values are replaced by '***' in audit details.
     * @return array{success: bool, before: array, after: array, changed: string[], audit_id: string}
     */
    public static function write( string $tool, array $changes, array $mask = array() ): array;

    /** Restore an option snapshot produced by write() or snapshot_options(). Null value => delete_option(). */
    public static function restore( array $snapshot ): array; // returns array<key, bool>
    ```
    - `write()`: `$before = Align_Agent_Audit_Log::snapshot_options( array_keys( $changes ) )` → so sánh, bỏ key không đổi → `update_option( $key, $value )` (sanitizer theme chạy tự động) → đọc lại `get_option()` làm `after` → `log_action( get_current_user_id(), $tool, 'options', 'update', array( 'before' => ..., 'after' => ..., 'masked' => $mask ) )`.
    - Giá trị trong `$mask` bị thay bằng `'***'` ở cả `before` và `after` của audit. Khi đó gắn `details.undo_supported = false`.
    - Không key nào đổi → trả `success => true, changed => []`, **không** ghi audit.
    - `restore()`: `null === $value` → `delete_option( $key )`; ngược lại `update_option()`. Đặc biệt key `theme_mod:custom_logo` → `set_theme_mod( 'custom_logo', $value )`.
  - `includes/tools/class-agent-tools-loader.php` → `MANIFEST` thêm theo thứ tự (helper trước tool dùng nó):
    ```php
    'site/class-agent-option-writer.php'         => 'Align_Agent_Option_Writer',
    'site/class-agent-tools-code-slots.php'      => 'Align_Agent_Tools_Code_Slots',
    'site/class-agent-tools-settings.php'        => 'Align_Agent_Tools_Settings',
    'site/class-agent-tools-protection.php'      => 'Align_Agent_Tools_Protection',
    'site/class-agent-tools-audit.php'           => 'Align_Agent_Tools_Audit',
    'site/class-agent-tools-health.php'          => 'Align_Agent_Tools_Health',
    'structure/class-agent-tools-navigation.php' => 'Align_Agent_Tools_Navigation',
    'i18n/class-agent-tools-i18n.php'            => 'Align_Agent_Tools_I18n',
    'i18n/class-agent-tools-i18n-urls.php'       => 'Align_Agent_Tools_I18n_Urls',
    'content/class-agent-tools-post-lock.php'    => 'Align_Agent_Tools_Post_Lock',
    'seo/class-agent-tools-seo-audit.php'        => 'Align_Agent_Tools_Seo_Audit',
    ```
    - `Align_Agent_Option_Writer::init()` là hàm rỗng để khớp vòng `init()` của loader.
  - `includes/tools/i18n/README.md` (mới, ở Mốc 5).
- **Kiểm chứng**:
  ```bash
  php -l includes/class-agent-approval-policy.php && php -l includes/tools/site/class-agent-option-writer.php && php -l includes/tools/class-agent-tools-loader.php
  ```
  ```bash
  cd ../../.. && S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'Align_Agent_Conversation::set_channel("telegram"); foreach (array("save_template","create_page","delete_user","trash_post") as $t) echo $t," => ",Align_Agent_Approval_Policy::classify($t,array()),PHP_EOL; $r = Align_Agent_Option_Writer::write("test_writer", array("blogdescription" => get_option("blogdescription")." x")); echo json_encode($r["changed"]),PHP_EOL; Align_Agent_Option_Writer::restore($r["before"]); echo get_option("blogdescription"),PHP_EOL;'
  ```
- **Kỳ vọng**:
  - Không lỗi cú pháp.
  - `save_template => approve`, `create_page => auto`, `delete_user => approve`, `trash_post => approve` (không đổi so với v3).
  - `["blogdescription"]`, sau đó tagline quay về giá trị gốc.

---

### Mốc 1: (Dự phòng) Kiểm tra loader và schema sau khi thêm manifest (REQ-01)

> Mốc ngắn, chạy sau khi tạo **file rỗng có class + `init()` rỗng** cho mọi entry ở Mốc 0. Mục đích: không để loader log `Tool file missing` trong lúc làm các mốc sau.

- **Kiểm chứng**:
  ```bash
  cd ../../.. && S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'wp_set_current_user(1); echo count(Align_Agent_Tool_Registry::get_openai_tools_schema()),PHP_EOL;'; grep -c "Tool file missing\|Tool class not loaded" ../../../../logs/php/error.log
  ```
- **Kỳ vọng**: số tool ≥ 63, không có dòng mới `Tool file missing`.

---

### Mốc 2: Code slots — GAP-01 (REQ-02)

- **File**: `includes/tools/site/class-agent-tools-code-slots.php` — `Align_Agent_Tools_Code_Slots`.
- **Hằng số**:
  ```php
  private const SLOTS = array(
      'tracking_code'    => 'align_tracking_code',     // printed in <head>
      'body_open_code'   => 'align_body_open_code',    // printed after <body>
      'footer_html_code' => 'align_footer_html_code',  // printed before </body>
  );
  private const MAX_BYTES = 65536;
  ```
- **Tool `get_code_slots`** (read):
  - Không tham số.
  - Trả `slots` = mỗi slot `{ option, code, bytes }` + `skip_admins` (bool từ `align_custom_code_skip_admins`).
  - Không động vào `align_custom_css` / `align_custom_js` (đã có `get_global_code`).
- **Tool `save_code_slot`** (write, `scope = global`, `danger = true`, capability `unfiltered_html`):
  - Tham số:
    ```json
    {
      "slot":        { "type": "string", "enum": ["tracking_code","body_open_code","footer_html_code"] },
      "code":        { "type": "string", "description": "Full replacement. Empty string clears the slot." },
      "edits":       { "type": "array", "items": { "find": "string", "replace": "string" } },
      "skip_admins": { "type": "boolean", "description": "Optional. Hide all custom code slots for logged-in admins." }
    }
    ```
    - `required`: không bắt buộc field nào, nhưng phải có ít nhất 1 trong `code`, `edits`, `skip_admins` → nếu không: `empty_payload`.
    - `code` và `edits` cùng lúc → lỗi `conflicting_payload`.
    - `code` / `edits` cần `slot` → thiếu: `missing_slot`.
  - Luồng callback:
    1. Tính `$new` = `code` hoặc áp lần lượt `edits` qua `Align_Agent_Patch_Engine::patch_string( $current, $find, $replace )`. Edit lỗi → trả nguyên lỗi patch engine (`not_found` / `ambiguous`).
    2. Guard `self::guard_code( string $new ): array{ok: bool, error?: string, warnings: string[]}`:
       - `strlen > MAX_BYTES` → `too_large`.
       - Chứa `<?php` hoặc `<?=` → `php_forbidden`.
       - Regex `#<script\b[^>]*\bsrc\s*=\s*["\']?(?!https://)#i` khớp → `insecure_script_src` (chỉ cho `https://`).
       - Regex `#\bon[a-z]+\s*=#i` hoặc `#javascript:#i` → thêm warning `inline_event_handler` (không chặn).
       - Gom nội dung các `<script>…</script>` inline → `Align_Agent_Content_Guard::check_js()` → nối `warnings`.
    3. `Align_Agent_Option_Writer::write( 'save_code_slot', $changes )` với `$changes` gồm option slot và/hoặc `align_custom_code_skip_admins` (`1`/`0`).
    4. Nếu `class_exists( 'Align_Gateway_System' )` thì không flush. Code slot render động, không cần cache flush. Ghi chú trong `message`: "Clear page cache if a caching plugin is active."
  - `summary_callback` `summarize_save_code_slot( $args )`:
    - `title`: `Update {Slot label} code`.
    - `description`: `⚠️ This code runs on every page of the website.`
    - `preview_js`: nội dung `<script>` inline gom được (để `Align_Agent_Tool_Registry::summarize_write_action` chạy `check_js`).
    - `meta`: `slot`, `bytes_before`, `bytes_after`, `external_scripts` (danh sách host của `src`).
    - Không trả toàn văn code dài hơn 1500 ký tự trong `description` (Telegram giới hạn 4096 ký tự).
- **Kiểm chứng**:
  ```bash
  php -l includes/tools/site/class-agent-tools-code-slots.php; wc -l includes/tools/site/class-agent-tools-code-slots.php
  ```
  ```bash
  cd ../../.. && S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'wp_set_current_user(1); $o=get_option("align_body_open_code"); print_r(Align_Agent_Tool_Registry::execute("save_code_slot", array("slot"=>"body_open_code","code"=>"<script src=\"http://x.test/a.js\"></script>"))["error"]); echo PHP_EOL; $r=Align_Agent_Tool_Registry::execute("save_code_slot", array("slot"=>"body_open_code","code"=>"<!-- v4 test -->")); echo $r["success"]?"OK":"FAIL",PHP_EOL; update_option("align_body_open_code",$o);'
  ```
- **Kỳ vọng**: `insecure_script_src`, sau đó `OK`. File < 500 dòng.

---

### Mốc 3: WP Settings — GAP-02 (REQ-03)

- **File**: `includes/tools/site/class-agent-tools-settings.php` — `Align_Agent_Tools_Settings`.
- **Whitelist** (hằng số `FIELDS`, key = tên tham số tool = tên option):

  | Field | Kiểu | Validator | Ghi chú |
  |---|---|---|---|
  | `blog_public` | int | `in_array( $v, [0,1], true )` | `0` = noindex toàn site. Summary cảnh báo đỏ |
  | `permalink_structure` | string | enum `''`, `/%postname%/`, `/%year%/%monthnum%/%postname%/`, `/%year%/%monthnum%/%day%/%postname%/`, `/%category%/%postname%/`, `/archives/%post_id%` | Sau ghi: `flush_rewrite_rules( false )` |
  | `timezone_string` | string | `in_array( $v, timezone_identifiers_list(), true )` | Ghi kèm `gmt_offset = ''` |
  | `date_format` | string | `sanitize_option( 'date_format', $v )`, ≤ 30 ký tự | |
  | `time_format` | string | `sanitize_option( 'time_format', $v )`, ≤ 30 ký tự | |
  | `posts_per_page` | int | 1..100 | |
  | `default_comment_status` | string | `open` \| `closed` | |
  | `comment_moderation` | int | 0 \| 1 | |
  | `comment_registration` | int | 0 \| 1 | |
  | `close_comments_for_old_posts` | int | 0 \| 1 | |
  | `close_comments_days_old` | int | 1..3650 | |

  - Key ngoài whitelist → lỗi `field_not_allowed` liệt kê key sai. **Không** ghi bất kỳ key nào nếu có 1 key sai.
- **Tool `get_site_settings`** (read): trả mọi field trong whitelist + `permalink_label` (tên preset) + `site_url`, `home_url` (chỉ đọc).
- **Tool `update_site_settings`** (write, `scope = global`, `danger = true`):
  - `parameters.properties` sinh từ `FIELDS` (có `enum` cho field enum). Không có `required`.
  - Luồng: validate toàn bộ → `Align_Agent_Option_Writer::write( 'update_site_settings', $changes )` → nếu `permalink_structure` nằm trong `changed` thì `flush_rewrite_rules( false )` và log `[Align Agent] Settings: rewrite rules flushed`.
  - `summary_callback`: liệt kê `field: old → new`. Nếu có `blog_public = 0`: `description` thêm `⚠️ Search engines will stop indexing the whole website.` Nếu có `permalink_structure`: thêm `⚠️ All page URLs will change. Old links may break.`
- **Kiểm chứng**:
  ```bash
  php -l includes/tools/site/class-agent-tools-settings.php
  ```
  ```bash
  cd ../../.. && S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'wp_set_current_user(1); echo Align_Agent_Tool_Registry::execute("update_site_settings", array("admin_email"=>"a@b.c"))["error"],PHP_EOL; $o=get_option("posts_per_page"); $r=Align_Agent_Tool_Registry::execute("update_site_settings", array("posts_per_page"=>7)); echo get_option("posts_per_page"),PHP_EOL; update_option("posts_per_page",$o);'
  ```
- **Kỳ vọng**: `field_not_allowed`, sau đó `7`.

---

### Mốc 4: Menu điều hướng trong template part — GAP-03 (REQ-04)

- **File**: `includes/tools/structure/class-agent-tools-navigation.php` — `Align_Agent_Tools_Navigation`.
- **Nguồn dữ liệu**: `Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => $tp_id ) )` → `html`, `css`, `js`.
  - `tp_id` mặc định `tp_header`. Cho phép `tp_footer` hoặc tp_id bất kỳ của `wp_template_part`.
- **Hàm nội bộ dùng chung**:
  ```php
  /** @return array<int, array{offset: int, length: int, html: string}> */
  private static function find_nav_fragments( string $html ): array;
  // preg_match_all( '#<nav\b[^>]*>.*?</nav>#is', $html, $m, PREG_OFFSET_CAPTURE )

  private static function load_fragment( string $fragment ): ?DOMDocument;
  // $doc->loadHTML( '<?xml encoding="UTF-8"><div id="__align_nav_root">' . $fragment . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING )

  private static function save_fragment( DOMDocument $doc ): string;
  // Serialize children of #__align_nav_root via $doc->saveHTML( $child ), concatenated.

  /** @return array<int, array{label: string, href: string, depth: int}> */
  private static function extract_links( DOMElement $nav ): array;
  // depth = số tổ tiên <ul>/<ol> nằm trong nav, trừ 1. Bỏ link rỗng label.
  ```
- **Tool `list_nav_links`** (read):
  - Tham số: `tp_id` (string, optional).
  - Trả `navs`: mỗi nav `{ index, aria_label, class, links: [{ label, href, depth }] }` + `in_sync` (bool: mọi nav có cùng danh sách `href` cấp 0).
  - Không có nav → `success => true, navs => []`, `message` gợi ý dùng `get_template`.
- **Tool `update_nav_links`** (write, `scope = global`, `danger = true`, `requires_builder = true`):
  - Tham số:
    ```json
    {
      "tp_id":      { "type": "string" },
      "nav_index":  { "type": "integer", "description": "Optional. Apply to one nav only. Default: all navs (desktop + mobile)." },
      "operations": { "type": "array", "items": {
        "type": "object",
        "properties": {
          "op":         { "type": "string", "enum": ["add","remove","rename","change_href","move"] },
          "href":       { "type": "string", "description": "Target link href (exact match after trim). Required except for add." },
          "label":      { "type": "string" },
          "new_href":   { "type": "string" },
          "after_href": { "type": "string", "description": "Insert/move after this link. Omit to append at the end." }
        },
        "required": ["op"]
      } }
    }
    ```
    - `required`: `operations` (1..20 phần tử).
  - Validate href (`add`, `change_href`): chấp nhận `/…`, `#…`, `https://…`. Từ chối `javascript:`, `data:`, `http://` → `invalid_href`.
    - Href nội bộ `/…` không resolve được (`url_to_postid( home_url( $href ) ) === 0` và không phải `/`) → thêm warning `unresolved_href`, **không** chặn.
  - Thuật toán cho mỗi nav đích:
    - Tìm `<a>` theo `href` (so sánh sau `untrailingslashit` trừ `/` và `#…`). Không tìm thấy trong nav nào → lỗi `link_not_found` (toàn bộ thao tác huỷ, không ghi).
    - `add`: phần tử mẫu = `<li>` cấp 0 cuối cùng chứa `<a>` (nếu nav có `<ul>`), hoặc `<a>` cấp 0 cuối cùng (nếu nav không có `<ul>`). `cloneNode( true )` → đặt `href`, `textContent` của `<a>` = `label` → gỡ `aria-current`, gỡ class `is-active|current|active|current-menu-item` → bỏ `<ul>` con (sub-menu) khỏi bản clone → chèn sau phần tử chứa `after_href` hoặc cuối danh sách.
    - `remove`: xoá `<li>` chứa `<a>` (hoặc chính `<a>` nếu không có `<li>`).
    - `rename`: đổi `textContent` của `<a>`. Nếu `<a>` có phần tử con (icon), chỉ đổi text node cuối cùng không rỗng.
    - `change_href`: đổi attribute `href`.
    - `move`: tách node, chèn lại theo `after_href`.
  - Ghép: duyệt fragment theo **offset giảm dần**, `substr_replace( $html, $new_fragment, $offset, $length )`. Phần ngoài `<nav>` không đổi.
  - Ghi: `Align_Agent_Tools_Templates::tool_save_template( array( 'tp_id' => $tp_id, 'html' => $new_html, 'css' => $payload['css'], 'js' => $payload['js'] ) )`. Truyền **cả** `css` và `js` cũ để không mất asset.
    - `tool_save_template` đã chạy `guard_html_and_css`, `validate_markup`, kiểm `Align_Editor_Mode_Lock`. Không lặp lại.
  - Audit: `log_action( ..., 'update_nav_links', $tp_id, 'update', array( 'operations' => ..., 'before_links' => ..., 'after_links' => ... ) )`. Hoàn tác bằng `restore_revision` / `get_revisions` của template (đã có).
  - `summary_callback`: chạy thuật toán ở chế độ dry-run (không ghi) → `description` liệt kê `➕ Pricing → /pricing (after FAQ)`, `➖ ...`; `meta.navs_affected`; `preview_html` = nav sau sửa của nav đầu tiên. Dry-run lỗi → `title` = `Menu update (invalid)` + lỗi trong `description`.
  - Tách thuật toán vào `private static function apply_operations( string $html, array $ops, ?int $nav_index ): array{ok, html, before, after, warnings, error?}` để callback và summary dùng chung.
- **Kiểm chứng**:
  ```bash
  php -l includes/tools/structure/class-agent-tools-navigation.php; wc -l includes/tools/structure/class-agent-tools-navigation.php
  ```
  ```bash
  cd ../../.. && S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'wp_set_current_user(1); require_once get_template_directory() . "/core/inc/builder/class-builder-rest-api.php"; $r=Align_Agent_Tool_Registry::execute("list_nav_links", array("tp_id"=>"tp_header")); echo count($r["navs"])," navs, in_sync=",var_export($r["in_sync"],true),PHP_EOL; $s=Align_Agent_Tool_Registry::summarize_write_action("update_nav_links", array("tp_id"=>"tp_header","operations"=>array(array("op"=>"add","label"=>"Pricing","href"=>"/pricing","after_href"=>"#faq")))); echo $s["description"],PHP_EOL;'
  ```
  - Telegram: "Thêm link Pricing /pricing vào menu sau FAQ" → Approve → mở trang chủ desktop và mobile drawer.
- **Kỳ vọng**:
  - `2 navs, in_sync=true`.
  - Summary có dòng `➕ Pricing → /pricing` và warning `unresolved_href` nếu chưa có trang `/pricing`.
  - Sau Approve: cả 2 nav có link mới cùng class với link anh em. `git diff` của `align-child/parts/header.html` (mirror) chỉ đổi trong 2 khối `<nav>`.

---

### Mốc 5: Đa ngôn ngữ — GAP-04 (REQ-05)

- **Folder mới**: `includes/tools/i18n/` + `README.md`. Registry group: `i18n`.
- **Load class admin khi cần** (dùng chung, đặt trong `Align_Agent_Tools_I18n_Urls`):
  ```php
  private static function ensure_url_scanner(): bool {
      if ( class_exists( 'Align_Bulk_Url_Scanner' ) ) return true;
      $file = get_template_directory() . '/core/includes/admin/class-align-bulk-url-scanner.php';
      if ( file_exists( $file ) ) require_once $file;
      return class_exists( 'Align_Bulk_Url_Scanner' );
  }
  ```
  - Worker **phải** xác minh đường dẫn bằng `ls "$(wp eval 'echo get_template_directory();')/core/includes/admin/"` trước khi dùng. Nếu file nằm ở `align/core/includes/admin/`, sửa hằng đường dẫn tương ứng.

#### 5.1 `includes/tools/i18n/class-agent-tools-i18n.php` — `Align_Agent_Tools_I18n`

- **`list_languages`** (read): trả `languages` (code, name, flag, slug, script, is_default, root_page_id, `has_prompt_hint`), `default`. Không trả toàn văn `prompt_hint` dài > 300 ký tự (cắt).
- **`get_translations`** (read):
  - Tham số: `post_id` (required).
  - Trả `post_id`, `lang` (`get_post_lang()` hoặc default), `translations` (từ `get_translations()`), `missing` = mã ngôn ngữ chưa có bản dịch.
- **`translation_overview`** (read):
  - Tham số: `post_type` enum `page|post|all` (default `all`), `limit` 1..200 (default 100).
  - Logic (viết lại từ `Align_Translation_Matrix::ajax_get_translation_matrix`, **không** gọi AJAX): `get_posts( status publish+draft )` → khoá cụm = `_align_lang_group` ?: `_align_lang_parent` ?: post_id → mỗi cụm `{ title, items: { lang => post_id }, missing: [] }`.
  - Trả `groups`, `stats { total_groups, fully_translated, missing_count }`.
- **`create_translation`** (write, `scope = local`, `danger = false`):
  - Tham số: `source_id` (int, required), `target_lang` (string, required), `title` (string, optional — tiêu đề đã dịch).
  - Luồng:
    1. `current_user_can( 'edit_post', $source_id )` → không: `forbidden`.
    2. `$new_id = Align_Language_Manager::create_translation( $source_id, $target_lang )`.
       - `WP_Error` code `already_translated` → trả `success => false, error => 'already_translated', existing_id => ...` để LLM chuyển sang sửa bản có sẵn.
    3. Nguồn **không** phải front page → `wp_update_post( array( 'ID' => $new_id, 'post_status' => 'draft' ) )`. Nguồn là front page → giữ nguyên (đã qua duyệt theo policy Mốc 0).
    4. `title` có giá trị → `wp_update_post( 'post_title' => sanitize_text_field( $title ) )`.
    5. Builder có mặt (`Align_Agent_Tool_Registry::is_builder_available()`) và `post_type === 'page'` → ghi lại parts như `tool_duplicate_post` (`class-agent-tools-posts.php:412-420`): `extract_decoupled_parts( get_post( $source_id ) )` → `Align_Builder_Crud_Handler::save_page()` với `post_id = $new_id`, `status` = trạng thái ở bước 3. Lỗi save → giữ bài, thêm warning `assets_not_synced`.
    6. `log_action( ..., 'create_translation', $new_id, 'create', array( 'source_id', 'target_lang' ) )`.
  - Trả `post_id`, `lang`, `status`, `preview_url` (`get_preview_post_link`), `edit_url`, `prompt_hint` của ngôn ngữ đích, và `next_step`: `"Translate the content of post {id} with patch_content. Keep HTML structure and classes."`.
- **`manage_language`** (write, `scope = global`, `danger = true`, `manage_options`):
  - Tham số: `action` enum `add|update|remove` (required), `code` (required), `name`, `flag`, `slug`, `script`, `prompt_hint`.
  - `add` / `update`: thiếu `name` → `missing_name`. Gọi `add_language()` / `update_language()` với đủ 6 tham số theo thứ tự chữ ký.
  - `remove`: gọi `remove_language()`. Theme đã chặn xoá default (`cannot_remove_default`).
  - Snapshot: `Align_Agent_Option_Writer` **không** dùng được (theme tự `update_option`). Thay bằng `$before = Align_Agent_Audit_Log::snapshot_options( array( 'align_languages' ) )` trước khi gọi, ghi `log_action` với `before` + `after` dạng option map → undo (Mốc 7) hỗ trợ.
  - Summary: `add` + `slug` → `description` thêm `A public root page "/{slug}/" will be created.`; `remove` → `Existing translated pages stay online but lose their language label.`

#### 5.2 `includes/tools/i18n/class-agent-tools-i18n-urls.php` — `Align_Agent_Tools_I18n_Urls`

- **`scan_unlocalized_urls`** (read):
  - Tham số: `post_id` (optional), `lang` (optional), `limit` 1..50 (default 20).
  - Có `post_id` → quét 1 bài với `lang` = ngôn ngữ của bài.
  - Không có `post_id` → bắt buộc `lang` khác default; quét bài có `_align_lang = lang`.
  - Dùng `Align_Bulk_Url_Scanner::detect_unlocalized_urls( $parts['html'], $lang )` trên `extract_decoupled_parts()`.
  - Thiếu class → `theme_feature_unavailable`.
  - Trả `items: [{ post_id, title, lang, urls: [...] }]`, chỉ bài có ≥ 1 URL.
- **`localize_post_urls`** (write, `scope = local`, `requires_builder = true`):
  - Tham số: `post_id` (required).
  - Luồng: lang = `get_post_lang()`; lang rỗng hoặc = default → `not_a_translation`.
    - `$parts = extract_decoupled_parts()` → `$new_html = Align_Language_Url_Localizer::localize_html( $parts['html'], $lang )`.
    - Không đổi → `success => true, changed => false`.
    - Đổi → `Align_Agent_Tools_Pages::guard_html_and_css( $new_html, $parts['css'] )` → `Align_Builder_Crud_Handler::save_page()` (giữ `css`, `js`, không truyền `status`).
  - Policy: bài publish → `auto_clone` (Mốc 0). `Align_Agent_Page_Clone::rewrite_args()` đổi `post_id` sang clone. Không cần code thêm.
  - **Cấm** gọi `Align_Bulk_Url_Scanner::ajax_fix_url_problems()` (ghi `wp_update_post` + `file_put_contents` thẳng, vi phạm `AGENTS.md` §4.1).

#### 5.3 System prompt

- `includes/class-agent-loop.php` → `build_system_prompt()`: khi `count( Align_Language_Manager::get_languages() ) > 1`, thêm 1 dòng:
  `Site languages: en (default), zh. To translate a page: create_translation, then patch_content on the new draft, then localize_post_urls.`
  - Bọc `class_exists( 'Align_Language_Manager' )`.
  - Kiểm `wc -l includes/class-agent-loop.php` < 500 sau khi sửa (hiện 425).

- **Kiểm chứng**:
  ```bash
  for f in includes/tools/i18n/*.php; do php -l "$f"; done; wc -l includes/tools/i18n/*.php includes/class-agent-loop.php
  ```
  ```bash
  cd ../../.. && S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'wp_set_current_user(1); require_once get_template_directory() . "/core/inc/builder/class-builder-rest-api.php"; $b=get_option("align_languages"); Align_Agent_Tool_Registry::execute("manage_language", array("action"=>"add","code"=>"zh","name"=>"Chinese","slug"=>"zh")); $src=(int)get_posts(array("post_type"=>"page","post_status"=>"publish","numberposts"=>1,"exclude"=>array((int)get_option("page_on_front"))))[0]->ID; $r=Align_Agent_Tool_Registry::execute("create_translation", array("source_id"=>$src,"target_lang"=>"zh")); echo $r["status"]," ",get_post_meta($r["post_id"],"_align_lang",true),PHP_EOL; wp_trash_post($r["post_id"]); update_option("align_languages",$b);'
  ```
  - Lệnh trên để lại root page `/zh/` (theme tạo). Worker chuyển root page đó vào thùng rác bằng `wp_trash_post()` sau khi kiểm (in ID root page trong lệnh).
- **Kỳ vọng**: `draft zh`. Kênh Telegram: `create_translation` không có thẻ duyệt; `manage_language` có thẻ đỏ.

---

### Mốc 6: Site Protection & khoá mật khẩu trang — GAP-05, GAP-06 (REQ-06)

#### 6.1 `includes/tools/site/class-agent-tools-protection.php` — `Align_Agent_Tools_Protection`

- **Hằng số**:
  ```php
  private const WRITABLE = array(
      'enabled'           => 'align_site_protect_enabled',
      'live_domain'       => 'align_site_protect_live_domain',
      'staging_domain'    => 'align_site_protect_staging_domain',
      'auto_noindex'      => 'align_site_protect_auto_noindex',
      'bypass_localhost'  => 'align_site_protect_bypass_localhost',
      'bypass_admins'     => 'align_site_protect_bypass_admins',
      'cookie_days'       => 'align_site_protect_cookie_days',
  );
  private const FORBIDDEN = array( 'user', 'password' );
  ```
- **`get_site_protection`** (read): trả mọi field `WRITABLE` (ép kiểu int/string), `current_host`, `environment` (`Align_Site_Protection::get_current_environment()` nếu có), `username_set` (bool), `password_set` (bool). **Không** trả giá trị `align_site_protect_user` / `align_site_protect_password`.
- **`set_site_protection`** (write, `scope = global`, `danger = true`):
  - `parameters` chỉ có các key `WRITABLE` (bool cho cờ, string cho domain, int 1..365 cho `cookie_days`). `additionalProperties: false`.
  - Có key `user` hoặc `password` → `credential_fields_forbidden` + `message`: "Set the protection username and password in Align Settings → Site Protection."
  - `enabled = true` và `align_site_protect_password === ''` → `password_not_configured`.
  - Domain đi qua sanitizer theme (`normalize_domain`) tự động khi `update_option`.
  - Ghi qua `Align_Agent_Option_Writer::write( 'set_site_protection', $changes )`.
  - Summary: bật `enabled` khi `current_host` = `live_domain` → `description`: `⚠️ Visitors on the LIVE domain will need a password to view the website.` Tắt `auto_noindex` → `⚠️ Staging pages may appear in search results.`

#### 6.2 `includes/tools/content/class-agent-tools-post-lock.php` — `Align_Agent_Tools_Post_Lock`

- **`get_post_lock`** (read): tham số `post_id`. Trả `post_id`, `title`, `status`, `locked` (`Align_Post_Password_Lock::is_post_locked()`). **Không** trả mật khẩu.
- **`set_post_lock`** (write, `scope = local`, `danger = false`):
  - Tham số: `post_id` (required), `enabled` (bool, required), `password` (string, 4..64 ký tự, required khi `enabled = true`).
  - `current_user_can( 'edit_post', $post_id )` → không: `forbidden`.
  - `enabled = true` → `Align_Post_Password_Lock::set_password( $post_id, $password )`; `false` → `disable_lock( $post_id )`.
  - Audit `details`: `{ enabled_before, enabled_after }`. **Không** lưu password.
  - Output và `ui_card.message`: `Page "{title}" is now password protected.` Không nhắc lại mật khẩu.
  - `summary_callback`: `meta.password` = `'••••'`. **Không** đưa password vào `description` / `preview_html`.
  - Policy: bài `publish` → `approve`, còn lại `auto` (Mốc 0).
- **Che mật khẩu trong log hội thoại**: `includes/class-agent-loop.php`, chỗ log `tool_args` (tìm `error_log` có `json_encode( $args )` hoặc `tool_args_bytes`). Thêm helper `Align_Agent_Tool_Registry::redact_args( array $args ): array` thay giá trị key `password|pass|secret|token` bằng `'***'` trước khi log. Không đổi `$args` thật truyền cho tool.

- **Kiểm chứng**:
  ```bash
  php -l includes/tools/site/class-agent-tools-protection.php && php -l includes/tools/content/class-agent-tools-post-lock.php
  ```
  ```bash
  cd ../../.. && S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'wp_set_current_user(1); echo Align_Agent_Tool_Registry::execute("set_site_protection", array("password"=>"x"))["error"],PHP_EOL; $id=wp_insert_post(array("post_title"=>"Lock test","post_type"=>"page","post_status"=>"draft")); $r=Align_Agent_Tool_Registry::execute("set_post_lock", array("post_id"=>$id,"enabled"=>true,"password"=>"abcd1234")); echo json_encode($r),PHP_EOL; echo Align_Post_Password_Lock::is_post_locked($id)?"LOCKED":"OPEN",PHP_EOL; wp_trash_post($id);'
  ```
  ```bash
  grep -c "abcd1234" ../../../../logs/php/error.log
  ```
- **Kỳ vọng**: `credential_fields_forbidden`; JSON không chứa `abcd1234`; `LOCKED`; grep in `0`.

---

### Mốc 7: SEO coverage, Audit log + Undo, Site health — GAP-07, GAP-08, GAP-09 (REQ-07)

#### 7.1 `includes/tools/seo/class-agent-tools-seo-audit.php` — `Align_Agent_Tools_Seo_Audit`

- **`audit_seo_coverage`** (read):
  - Tham số: `post_types` (array, default `["page","post"]`), `lang` (default `all`), `scope` enum `missing|needs_optimization|all` (default `missing`), `limit` 1..200 (default 50).
  - Provider = `Align_Agent_Seo_Adapters::get_provider()`. Map key:

    | Provider | title | description | keyword | score |
    |---|---|---|---|---|
    | `rank_math` | `rank_math_title` | `rank_math_description` | `rank_math_focus_keyword` | `rank_math_seo_score` |
    | `yoast` | `_yoast_wpseo_title` | `_yoast_wpseo_metadesc` | `_yoast_wpseo_focuskw` | — |
    | `theme_default` | `_align_seo_title` | `_align_seo_description` | `_align_seo_keywords` | — |
    | `others` | trả `provider_unsupported` |
  - `missing` = thiếu title **hoặc** description. `needs_optimization` = `missing` hoặc score < 80 (chỉ rank_math).
  - Trả `provider`, `items [{ post_id, title, url, lang, status, has_title, has_description, keyword, score }]`, `stats { scanned, missing_title, missing_description }`.
  - `description` của tool ghi: `Read-only. To fix, call the active save_seo_* tool per item. Process at most 10 items per turn.` (giới hạn `MAX_CONTINUE_TURNS = 12`).

#### 7.2 `includes/tools/site/class-agent-tools-audit.php` — `Align_Agent_Tools_Audit`

- **Hằng số**:
  ```php
  private const UNDOABLE = array(
      'update_site_settings', 'save_code_slot', 'set_site_protection',
      'update_site_identity', 'set_homepage', 'manage_language', 'save_global_code',
  );
  ```
  - Worker **phải** mở `tool_save_global_code` (`class-agent-tools-global-code.php`) xác minh audit có `details.before` dạng `array<option_key, value>`. Không đúng định dạng → bỏ `save_global_code` khỏi `UNDOABLE`.
  - `update_site_identity` lưu `custom_logo` trong `before` (key không phải option). `Option_Writer::restore()` map key `custom_logo` → `set_theme_mod()` khi tool là `update_site_identity`.
- **`list_audit_logs`** (read):
  - Tham số: `limit` 1..50 (default 20), `tool` (optional filter).
  - Trả `entries` (mảng). Mỗi entry: `id`, `iso_date`, `user_login`, `tool`, `object_id`, `action_type`, `undoable` (bool = tool ∈ `UNDOABLE` + có `details.before` + `undo_supported !== false` + chưa bị undo).
  - Cắt mọi chuỗi trong `details` > 300 ký tự. Che key `password|pass|secret|token|code` bằng `'***'`.
- **`undo_audit_action`** (write, `scope = global`, `danger = true`):
  - Tham số: `audit_id` (required).
  - Không tìm thấy → `audit_not_found`. Tool ngoài `UNDOABLE` → `undo_not_supported` + `message` gợi ý `restore_revision` / `restore_asset_history` / `restore_post`.
  - Đã có entry `tool = undo_audit_action` với `object_id = audit_id` → `already_undone`.
  - Option hiện tại khác `details.after` → thêm warning `changed_since` (vẫn cho undo, summary hiển thị cảnh báo).
  - Thực thi `Align_Agent_Option_Writer::restore( $entry['details']['before'] )` → `permalink_structure` nằm trong snapshot thì `flush_rewrite_rules( false )`.
  - `log_action( ..., 'undo_audit_action', $audit_id, 'restore', array( 'restored_keys' => ... ) )`.

#### 7.3 `includes/tools/site/class-agent-tools-health.php` — `Align_Agent_Tools_Health`

- **`site_health`** (read):
  - Tham số: `include_log` (bool, default false), `log_lines` 10..100 (default 40).
  - Trả:
    - `versions`: `wp`, `php` (`PHP_VERSION`), `db` (`$wpdb->db_version()`), `theme` (name + version), `align_agent` (`ALIGN_AGENT_VERSION` nếu định nghĩa).
    - `runtime`: `memory_limit`, `max_execution_time`, `WP_DEBUG`, `WP_DEBUG_LOG`, `object_cache` (`wp_using_ext_object_cache()`), `https` (`'https' === wp_parse_url( home_url(), PHP_URL_SCHEME )`).
    - `updates`: `wp_get_update_data()['counts']` (chỉ đọc transient, không gọi API).
    - `cron`: `total_events`, `overdue` (timestamp < `time() - 3600`), `disabled` (`DISABLE_WP_CRON`).
    - `disk`: `free_gb` (`disk_free_space( ABSPATH )`, null nếu hàm bị tắt), `uploads_writable` (`wp_is_writable( wp_upload_dir()['basedir'] )`).
    - `plugins`: danh sách plugin đang bật `{ name, version }` (đọc `get_option( 'active_plugins' )` + `get_plugin_data()`; `require_once ABSPATH . 'wp-admin/includes/plugin.php'`).
    - `log` (khi `include_log`): đọc tối đa 256 KB cuối của file log (`WP_DEBUG_LOG` là string → path đó; `true` → `WP_CONTENT_DIR . '/debug.log'`; không có → `ini_get( 'error_log' )`). Lấy `log_lines` dòng cuối khớp `#\[Align|PHP (Fatal|Warning|Parse)#`.
  - Hàm `redact_line( string $line ): string` — bắt buộc chạy trên mọi dòng log:
    ```php
    $patterns = array(
        '#bot\d{6,}:[A-Za-z0-9_-]{20,}#'           => 'bot***',
        '#\bsk-[A-Za-z0-9_-]{16,}#'                => 'sk-***',
        '#Bearer\s+[A-Za-z0-9._\-]+#i'             => 'Bearer ***',
        '#(password|pass|secret|token|key)=\S+#i'  => '$1=***',
        '#[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}#i' => '***@***',
    );
    ```
  - **Không** có tool ghi nào trong file này.

- **Kiểm chứng**:
  ```bash
  for f in includes/tools/seo/class-agent-tools-seo-audit.php includes/tools/site/class-agent-tools-audit.php includes/tools/site/class-agent-tools-health.php; do php -l "$f"; done
  ```
  ```bash
  cd ../../.. && S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'wp_set_current_user(1); $o=get_option("posts_per_page"); Align_Agent_Tool_Registry::execute("update_site_settings", array("posts_per_page"=>9)); $l=Align_Agent_Tool_Registry::execute("list_audit_logs", array("limit"=>1)); $id=$l["entries"][0]["id"]; Align_Agent_Tool_Registry::execute("undo_audit_action", array("audit_id"=>$id)); echo get_option("posts_per_page")==$o?"UNDONE":"FAIL",PHP_EOL; echo Align_Agent_Tool_Registry::execute("undo_audit_action", array("audit_id"=>$id))["error"],PHP_EOL; $h=Align_Agent_Tool_Registry::execute("site_health", array("include_log"=>true)); echo $h["versions"]["php"],PHP_EOL; echo preg_match("#bot\d{6,}:#", json_encode($h)) ? "LEAK" : "CLEAN",PHP_EOL; echo Align_Agent_Tool_Registry::execute("audit_seo_coverage", array("limit"=>5))["provider"],PHP_EOL;'
  ```
- **Kỳ vọng**: `UNDONE`, `already_undone`, version PHP, `CLEAN`, tên provider.

---

### Mốc 8: Run report, Telegram, tài liệu (REQ-08)

- **File**:
  - `includes/class-agent-run-report.php` → `record_event()`:
    - `create_translation` → `record( 'created', array( 'title' => get_the_title( $post_id ), 'url' => preview_url, 'type' => 'translation', 'status' => 'draft' ) )`.
    - `localize_post_urls` → `record( 'edited', array( 'title', 'url', 'type' => 'page links' ) )`.
    - Thêm hằng `LABELS` cho fallback, thay tên tool thô bằng nhãn đời thường:
      ```php
      private const LABELS = array(
          'save_code_slot'       => 'tracking code',
          'update_site_settings' => 'site settings',
          'update_nav_links'     => 'site menu',
          'manage_language'      => 'site languages',
          'set_site_protection'  => 'site protection',
          'set_post_lock'        => 'page password',
          'undo_audit_action'    => 'an earlier change (undo)',
      );
      ```
      Fallback dùng `'title' => self::LABELS[ $fn_name ] ?? ( $args['title'] ?? 'item' )`.
    - File hiện 325 dòng. Giữ < 500.
  - `includes/class-gateway-telegram.php` → `/help`: thêm 3 dòng ví dụ tiếng Anh: menu, tracking code, translation. Không đổi lệnh.
  - `AGENTS.md`:
    - §4.2: thêm group `i18n` vào danh sách group.
    - §5.1: thêm `i18n` vào danh sách folder hợp lệ.
    - §4.3: thêm 3 dòng: `Align_Agent_Option_Writer` (mọi ghi option qua helper), `set_site_protection` cấm user/password, `site_health` bắt buộc `redact_line`.
  - `includes/tools/README.md`: §2 thêm folder `i18n/`, cập nhật số lượng (84 tool); §3 thêm 21 dòng theo bảng mục 3; §7 bỏ dòng "Navigation Menus" và "theme_mods" (đã có nav tool cho template part), giữ "Plugins" và "Widgets", thêm "Backup/import, fonts, image sizes (P3 — local only)".
  - `includes/tools/site/README.md`, `structure/README.md`, `content/README.md`, `seo/README.md`, `i18n/README.md` (mới): bảng tool chi tiết theo mẫu README cùng folder.
  - `README.md` (plugin): thêm mục "v4 Site Administration Tools" 6–8 gạch đầu dòng.
- **Kiểm chứng toàn cục**:
  ```bash
  for f in includes/*.php includes/tools/*.php includes/tools/*/*.php; do php -l "$f" | grep -v "No syntax errors"; done
  ```
  ```bash
  wc -l includes/*.php includes/tools/*/*.php | awk '$1 >= 500 && $2 != "total"'
  ```
  ```bash
  grep -rn "add_options_page\|wp_delete_post([^)]*true\|\$wpdb->query\|file_put_contents" includes/tools/ | grep -v "^.*README"
  ```
  ```bash
  grep -n "i18n\|Option_Writer\|update_nav_links\|save_code_slot" AGENTS.md includes/tools/README.md README.md
  ```
  ```bash
  cd ../../.. && S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'Align_Agent_Conversation::set_channel("telegram"); foreach (array("save_code_slot","update_site_settings","update_nav_links","manage_language","set_site_protection","undo_audit_action","create_translation","localize_post_urls","set_post_lock") as $t) echo $t," => ",Align_Agent_Approval_Policy::classify($t,array()),PHP_EOL; Align_Agent_Conversation::set_channel("chatbox"); echo "chatbox create_translation => ",Align_Agent_Approval_Policy::classify("create_translation",array()),PHP_EOL;'
  ```
- **Kỳ vọng**:
  - Không lỗi cú pháp. Không file ≥ 500 dòng. Grep cấm trả rỗng.
  - Telegram: 6 tool đầu `=> approve`; `create_translation => auto`; `localize_post_urls => auto`; `set_post_lock => auto` (không có post_id).
  - Chatbox: `create_translation => approve`.
  - Mỗi file tài liệu có ít nhất 1 dòng khớp.
- **E2E Telegram**: chạy đủ 9 kịch bản ở mục 1 "Nghiệm thu". Ghi kết quả vào `docs/` theo mẫu `docs` của v3 (báo cáo verification).
  ```bash
  grep -E "\[Align Agent\] (Policy|Settings|Audit log recorded|Code slot|Nav)" ../../../../logs/php/error.log | tail -40
  ```

---

## 6. Thứ Tự Commit Đề Xuất

1. `feat(policy): honor registry scope and add option writer helper` — Mốc 0, 1.
2. `feat(site): add code slot and site settings tools` — Mốc 2, 3.
3. `feat(structure): add navigation link tools for template parts` — Mốc 4.
4. `feat(i18n): add language and translation tools` — Mốc 5.
5. `feat(site): add site protection and post lock tools` — Mốc 6.
6. `feat(ops): add seo coverage, audit undo and site health tools` — Mốc 7.
7. `docs(tools): document v4 administration tools` — Mốc 8 (tài liệu phải đi cùng code theo `AGENTS.md` §5.3: nếu tách commit thì gộp README của từng folder vào commit code tương ứng).

---

## 7. Giả Định Đã Chọn

- Plan đặt ở **root plugin** (`plugins/Align-Agent/convert-to-telegram-v4.md`) cạnh v1–v3, nơi worker code. Root theme chỉ chứa plan của theme.
- Menu = link trong `<nav>` của template part. Không dùng `wp_nav_menu` / `wp_navigation` vì site không đăng ký.
- `update_nav_links` mặc định sửa **mọi** nav trong part để desktop và mobile luôn đồng bộ.
- `create_translation` luôn tạo `draft` (trừ front page đã qua duyệt). LLM dịch nội dung sau bằng `patch_content`.
- Mật khẩu Site Protection không đổi qua chat. Mật khẩu khoá từng trang được phép đặt qua chat vì User cần chia sẻ cho khách xem.
- Undo chỉ áp cho thao tác ghi option. Nội dung trang/template hoàn tác bằng tool revision sẵn có.
- `site_health` chỉ đọc, không gọi HTTP, không gọi `wp_update_plugins()`.
- `save_code_slot` dùng capability `unfiltered_html`. Multisite không cấp quyền này cho admin site con → tool trả `forbidden` (chấp nhận).

## 8. Câu Hỏi Chặn

Không có câu hỏi chặn.
