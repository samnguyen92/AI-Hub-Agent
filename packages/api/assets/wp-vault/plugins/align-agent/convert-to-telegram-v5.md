# Kế Hoạch Triển Khai — Convert to Telegram v5 (Auto-Execute + Instant Undo + Content Finder)

> Kế thừa `convert-to-telegram-v4.md`. v5 đổi mô hình an toàn: **bỏ cổng duyệt trước, thay bằng hoàn tác tức thì sau**. Đồng thời sửa cách liệt kê nội dung khi site có nhiều page/post/CPT.
>
> **Worker AI đọc xong file này thì code theo thứ tự Mốc 0 → Mốc 7.** Mọi câu hỏi chặn đã chốt tại mục 9 (D1–D3). Không hỏi lại.

---

## 0. Bối Cảnh & Bằng Chứng (quét 21-Oct-2026)

### 0.1 Hiện trạng cổng duyệt

| Điểm | Vị trí | Hành vi hiện tại |
|---|---|---|
| Điểm quyết định duy nhất | `includes/class-agent-approval-policy.php:28` `classify()` | Chatbox luôn `approve`. Telegram: `approve` / `auto_clone` / `auto` |
| Rẽ nhánh thực thi | `includes/class-agent-loop.php:297-366` | `approve` → `queue_pending_write()`. `auto_clone` → `Align_Agent_Page_Clone::handle_write()`. `auto` → `execute()` + `log_action()` |
| Clone → thẻ duyệt | `includes/class-gateway-telegram-runner.php:391-414` | Mỗi clone sinh 1 pending `apply_page_clone` |
| Thẻ Telegram | `includes/class-gateway-telegram-approval.php:26`, `:285` | Callback `a|`, `c|`, `A|`, `C|` |
| Audit log | `includes/class-agent-audit-log.php:45` | Lưu `details.args` thô. Max 100 bản ghi. Không lưu trạng thái "before" cho nội dung |
| Undo hiện có | `includes/tools/site/class-agent-tools-audit.php:22-29` `UNDOABLE` | Chỉ 6 tool option-based. Nội dung trang phải gọi tay `restore_revision` + `restore_asset_history` |

**Khoảng trống chính**: nhánh `auto` (`class-agent-loop.php:345-351`) chỉ log `args`, không chụp trạng thái trước khi ghi. Bỏ duyệt mà giữ nguyên nhánh này = mất khả năng hoàn tác.

### 0.2 Hiện trạng liệt kê nội dung (Ảnh 1)

- LLM gọi `list_posts` / `list_pages` rồi in toàn bộ danh sách. Không có giới hạn trình bày.
- `list_posts` (`includes/tools/content/class-agent-tools-posts.php:152`) trả `total`, `total_pages`, tối đa 50 item/trang.
- `list_pages` (`includes/tools/content/class-agent-tools-pages.php:143`) mặc định 20, tối đa 50.
- `site_overview` (`includes/tools/site/class-agent-tools-site.php:315`) chỉ đếm `post` + `page`. Thiếu CPT.
- System prompt (`includes/class-agent-loop.php:56`) dặn "Use list_pages ... before answering" → LLM luôn dump list.
- Site 500 page: 1 lượt tốn token lớn, tin Telegram bị chặt nhiều chunk 4096 ký tự (`class-gateway-telegram-format.php:22`).

### 0.3 Ràng buộc kích thước file (< 500 dòng)

| File | Dòng | Hệ quả |
|---|---|---|
| `includes/class-gateway-telegram-runner.php` | 477 | Không nhồi thêm logic. Lệnh `/undo`, `/history` tách file mới |
| `includes/class-gateway-telegram-approval.php` | 460 | Callback `u|` tách file mới |
| `includes/class-agent-loop.php` | 438 | Chỉ thay nhánh rẽ, gọi sang executor mới |

---

## 1. Hiểu Yêu Cầu & Ranh Giới

- **Mục tiêu 1**: Thao tác ghi chạy ngay, không chờ Admin duyệt. Mọi thay đổi có nút hoàn tác 1 chạm.
- **Mục tiêu 2**: Hỏi "liệt kê trang/bài" → trả số lượng theo loại + 1 câu hỏi "Bạn muốn tìm bài nào?". Hỏi tìm → trả link.
- **Nghiệm thu**:
  - Telegram "Đổi tiêu đề hero trang chủ thành X" → sửa thẳng trang live, không thẻ duyệt → báo cáo có nút `↩️ Undo` → bấm → trang về nội dung cũ, CSS/JS cũ.
  - Lượt có 3 thao tác ghi → 1 nút `↩️ Undo all` hoàn tác cả 3 theo thứ tự ngược.
  - `/undo` hoàn tác change set gần nhất. `/history` liệt kê 10 change set gần nhất kèm nút Undo.
  - Hoàn tác khi trang đã bị sửa tay sau đó → cảnh báo xung đột, yêu cầu bấm `Force undo`.
  - "Liệt kê các trang" trên site 500 page → 1 tin nhắn ngắn: số lượng theo loại + câu hỏi tìm kiếm.
  - "Tìm bài về subscription" → tối đa 5 kết quả: tiêu đề, loại, trạng thái, link.
- **Non-goals**:
  - Không đổi schema DB. Không thêm bảng mới.
  - Không hoàn tác được thao tác ra bên ngoài site (gửi email reset mật khẩu).
  - Không xoá vĩnh viễn để "hoàn tác" thao tác tạo. Hoàn tác tạo = `wp_trash_post()`.

---

## 2. Đánh Giá Phương Án

### Điểm yếu (Hạn chế / Rủi ro)

- Prompt injection (nội dung trang, ảnh caption, comment) giờ ghi thẳng lên site live. Không còn lớp người đọc trước.
- Trang publish bị sửa trực tiếp. Khách truy cập thấy bản lỗi trong khoảng thời gian trước khi User bấm Undo.
- `wp_save_post_revision()` trả `null` khi nội dung trùng revision mới nhất. Cần fallback lấy revision mới nhất.
- `WP_POST_REVISIONS` có thể bị tắt trên host khác. Undo nội dung phải tự lưu snapshot, không phụ thuộc hoàn toàn vào revision.
- Snapshot HTML lớn nằm trong `wp_options` làm DB phình. Cần giới hạn số change set và TTL.
- Một số thao tác không hoàn tác thuần được: `send_password_reset`, `delete_user`, `sideload_media` khi `MEDIA_TRASH` tắt.
- Hoàn tác chuỗi: change set B sửa đè change set A. Undo A trước B gây mất B.
- Template part (header/footer) sửa sai làm vỡ toàn site ngay lập tức.

### Điểm mạnh (Ưu điểm / Khả thi)

- Điểm quyết định duy nhất `classify()`. Đổi 1 hàm là đổi toàn bộ policy.
- Hạ tầng snapshot có sẵn: `snapshot_options`, `snapshot_post_meta`, `snapshot_term`, `snapshot_user` (`class-agent-audit-log.php:112-193`).
- Hạ tầng hoàn tác có sẵn: `restore_revision`, `restore_asset_history`, `undo_audit_action`.
- `Align_Agent_Pending_Store` có mẫu lưu option riêng + mutex claim. Change set store sao chép mẫu này.
- Inline keyboard + callback xác thực Chat ID đã chạy ổn (`class-gateway-telegram-approval.php:293-299`).
- Phần liệt kê chỉ cần 1 tool đếm + 1 tool tìm + 1 dòng prompt. Không đổi API công khai.

### Giải pháp & Lý do

* Giải pháp:* Thêm `Align_Agent_Change_Set` lưu snapshot "before" của từng thao tác ghi, nhóm theo lượt. -> *Lý do:* Undo 1 chạm cần trạng thái cũ đầy đủ, không phụ thuộc LLM tự nhớ revision ID.
* Giải pháp:* Mỗi tool ghi khai báo `undo` adapter (`post`, `template`, `option`, `meta`, `term`, `user_role`, `comment`, `create`, `none`). -> *Lý do:* Executor chụp snapshot đúng loại dữ liệu trước khi gọi callback.
* Giải pháp:* Snapshot nội dung tự lưu `post_content` + `post_title` + `post_status` + CSS/JS asset, kèm revision ID nếu có. -> *Lý do:* Không phụ thuộc `WP_POST_REVISIONS`.
* Giải pháp:* Undo kiểm tra `post_modified_gmt` hiện tại khớp giá trị "after" đã ghi. Lệch → trả `conflict`, cần `force`. -> *Lý do:* Không ghi đè thay đổi tay của User.
* Giải pháp:* Undo change set cũ khi có change set mới hơn chạm cùng object → chặn, gợi ý undo theo thứ tự (LIFO). -> *Lý do:* Tránh mất thay đổi trung gian.
* Giải pháp:* Thêm `count_content` + `find_content`, sửa prompt liệt kê. -> *Lý do:* Kết quả cố định ngắn, không phụ thuộc số lượng bài.

---

## 3. Kiến Trúc Mới

```
run_step()  ──►  classify()  ──►  'auto' | 'block'
                                     │
                     ┌───────────────┘
                     ▼
   Align_Agent_Write_Executor::run( $tool, $args, $user_id )
     1. Align_Agent_Undo_Adapters::capture( $tool, $args )   → before
     2. Align_Agent_Tool_Registry::execute( ... )             → result
     3. Align_Agent_Undo_Adapters::capture_after( ... )       → after fingerprint
     4. Align_Agent_Change_Set::append( $cs_id, entry )
     5. Align_Agent_Audit_Log::log_action( ..., { change_set, undo_supported } )
                     │
   Cuối lượt: Run Report + nút  ↩️ Undo  (u|{cs_id})
                     │
   Align_Agent_Undo_Service::undo( $cs_id, $force )
     - claim mutex → kiểm tra xung đột → revert từng entry LIFO → audit 'undo'
```

### 3.1 Policy mới `classify()`

| Kết quả | Khi nào | Hành vi |
|---|---|---|
| `auto` | Mặc định cho mọi tool ghi, mọi kênh | Chạy qua `Write_Executor` |
| `block` | Tool khớp `HARD_GATE` (mục 9.1) hoặc chế độ `manual` | Giữ luồng pending + thẻ duyệt như v4 |

- Bỏ `auto_clone`. `Align_Agent_Page_Clone` giữ lại cho tool `clone_page` thủ công, không còn tự kích hoạt.
- Giữ filter `align_agent_approval_policy` để site khác bật lại duyệt.
- Thêm option `align_agent_approval_mode` (`auto` | `manual`, mặc định `auto`) trong Settings tab. `manual` = hành vi v4. → Công tắc khẩn cấp khi bị prompt injection.

### 3.2 Bảng Undo Adapter

| Adapter | Tool áp dụng | Snapshot before | Revert |
|---|---|---|---|
| `post` | `save_page`, `save_post`, `patch_content` (page/post), `update_post_attributes`, `localize_post_urls`, `set_post_status`, `restore_revision`, `set_featured_image`, `set_post_lock` | `post_title`, `post_content`, `post_status`, `post_name`, `post_parent`, `menu_order`, `_thumbnail_id`, revision ID, asset basenames + timestamp mới nhất | `wp_update_post()` qua `Align_Builder_Crud_Handler::save_page` với html/css/js cũ |
| `template` | `save_template`, `save_reuse_block`, `patch_content` (template/reuse_block), `update_nav_links` | `post_content` của `wp_template_part`/`wp_block` + asset | `Align_Agent_Tools_Templates::tool_save_template()` với bản cũ |
| `create` | `create_page`, `create_post`, `duplicate_post`, `create_reuse_block`, `create_translation`, `create_redirect`, `create_user` (role khác `administrator`) | ID object mới (đọc từ result) | Post: `wp_trash_post()`. Redirect: xoá rule. User: không tự xoá, đánh dấu `undo_supported=false` |
| `trash` | `trash_post`, `trash_media`, `delete_redirect` | ID + trạng thái cũ / rule cũ | `wp_untrash_post()` + khôi phục `post_status` cũ / thêm lại rule |
| `option` | `update_site_settings`, `save_code_slot`, `save_global_code`, `set_site_protection`, `update_site_identity`, `set_homepage`, `manage_language` | `snapshot_options()` | `Align_Agent_Option_Writer` ghi lại giá trị cũ |
| `meta` | `save_seo_*`, `update_media_meta`, `attach_media` | `snapshot_post_meta()` với key tool khai báo | `update_post_meta` / `delete_post_meta` khi before `null` |
| `term` | `create_term`, `update_term`, `assign_terms` | `snapshot_term()` / `wp_get_object_terms()` | `wp_update_term` / `wp_set_object_terms` / xoá term vừa tạo |
| `user_role` | `update_user_role`, `update_user_profile` | `snapshot_user()` | `WP_User::set_role()` + `wp_update_user()` |
| `comment` | `moderate_comment`, `reply_comment` | `comment_approved` / ID reply | `wp_set_comment_status()` / trash reply |
| `media` | `sideload_media` | ID attachment mới | `wp_trash_post()` khi `MEDIA_TRASH`. Ngược lại `undo_supported=false` |
| `none` | `send_password_reset`, `delete_user`, `flush_cache`, `undo_audit_action` | — | Không hoàn tác. Run report ghi rõ "Không thể hoàn tác" |

- Tool chưa khai báo `undo` → executor dùng `none` + `error_log( '[Align Agent] Undo adapter missing | tool: ...' )`. QC bắt buộc bảng trên phủ 100% tool ghi.

### 3.3 Lưu trữ Change Set

- Option riêng `align_agent_cs_{id}` (autoload `false`). Index `align_agent_cs_index_{user_id}` giữ tối đa **30** change set.
- TTL **7 ngày**. Cron `align_agent_cs_gc` hằng ngày xoá change set hết hạn.
- Cấu trúc:

```php
array(
	'id'         => 'cs_<uuid>',
	'user_id'    => 1,
	'channel'    => 'telegram',
	'created'    => 1761000000,
	'status'     => 'applied', // applied | undone | partial | expired
	'tg_message_id' => 0,
	'entries'    => array(
		array(
			'tool'       => 'patch_content',
			'adapter'    => 'post',
			'object'     => array( 'type' => 'post', 'id' => 80 ),
			'label'      => 'Trang chủ',
			'before'     => array( /* snapshot */ ),
			'after_fp'   => array( 'post_modified_gmt' => '...', 'hash' => 'md5(content)' ),
			'undoable'   => true,
		),
	),
)
```

- Snapshot HTML > 512 KB → nén `gzcompress` + `base64`. Vượt 2 MB → chỉ lưu revision ID, `undoable` phụ thuộc revision còn tồn tại.
- Không lưu secret: adapter `option` bỏ qua key chứa `password|secret|token` (đồng bộ `redact_line`), đánh dấu `undo_supported=false` cho key đó.

---

## 4. Bảng File Thay Đổi

| File | Loại | Nội dung |
|---|---|---|
| `includes/class-agent-change-set.php` | Mới | CRUD change set, index, mutex claim, GC cron |
| `includes/class-agent-undo-adapters.php` | Mới | `capture()`, `capture_after()`, `revert()` theo adapter |
| `includes/class-agent-write-executor.php` | Mới | Bọc execute + snapshot + audit |
| `includes/class-agent-undo-service.php` | Mới | `undo( $user_id, $cs_id, $force )`, phát hiện xung đột, LIFO |
| `includes/class-gateway-telegram-undo.php` | Mới | Nút `u|`/`U|` (force), lệnh `/undo`, `/history` |
| `includes/tools/content/class-agent-tools-finder.php` | Mới | Tool `count_content`, `find_content` |
| `includes/class-agent-approval-policy.php` | Sửa | `classify()` trả `auto` / `block` |
| `includes/class-agent-loop.php` | Sửa | Nhánh `auto` gọi `Write_Executor`. Bỏ nhánh `auto_clone`. Sửa system prompt |
| `includes/class-agent-tool-registry.php` | Sửa | Field `undo` (adapter + `meta_keys`) trong `register()` |
| `includes/class-gateway-telegram-runner.php` | Sửa | Bỏ khối clone → pending (`:391-414`). Gửi report kèm nút Undo. Route `/undo`, `/history` sang file mới |
| `includes/class-gateway-telegram-approval.php` | Sửa | `handle_callback_query()` chuyển `u|`, `U|` sang `Align_Gateway_Telegram_Undo` |
| `includes/class-agent-run-report.php` | Sửa | Nhận `cs_id`, thêm dòng "Có thể hoàn tác" / "Không thể hoàn tác" |
| `includes/class-agent-chat-controller.php` | Sửa | Route `POST /chat/undo` (Dual Auth), trả change set trong response |
| `assets/chatbox*.js` | Sửa | Nút `↩️ Undo` dưới report card, gọi `/chat/undo` |
| `includes/tools/site/class-agent-tools-audit.php` | Sửa | `undo_audit_action` ủy quyền cho `Undo_Service` khi entry có `change_set` |
| `includes/tools/**/class-agent-tools-*.php` | Sửa | Khai báo `undo` cho mọi tool ghi |
| `views/settings-page.php` | Sửa | Radio `align_agent_approval_mode` |
| `AGENTS.md`, `README.md`, `includes/tools/README.md`, `includes/tools/*/README.md` | Sửa | Đồng bộ policy mới + tool mới |

---

## 5. Các Mốc Triển Khai

### Mốc 0 — Registry field `undo`

- `Align_Agent_Tool_Registry::register()` nhận `'undo' => array( 'adapter' => 'post', 'id_arg' => 'post_id', 'meta_keys' => array() )`.
- Thêm `get_undo_spec( string $tool ): array`.
- Kiểm chứng:

```bash
wp eval 'foreach ( Align_Agent_Tool_Registry::get_all() as $n => $d ) { if ( "write" === $d["type"] && empty( $d["undo"] ) ) echo $n, PHP_EOL; }'
```

  Kỳ vọng: rỗng sau Mốc 6.

### Mốc 1 — Change Set Store

- File `includes/class-agent-change-set.php`. Hàm: `open( $user_id, $channel ): string`, `append( $cs_id, array $entry ): void`, `get( $cs_id ): ?array`, `list_for( $user_id, $limit = 10 ): array`, `mark( $cs_id, $status ): void`, `claim( $cs_id ): bool`, `release( $cs_id ): void`, `gc(): int`.
- 1 lượt Telegram/Chatbox = 1 change set. `open()` gọi lazy ở thao tác ghi đầu tiên. ID lưu trong `Align_Agent_Run_Report` instance.
- Đăng ký cron `align_agent_cs_gc` khi activate, gỡ khi deactivate.
- Kiểm chứng: `wp eval` tạo 31 change set → index còn 30, option cũ nhất đã xoá.

### Mốc 2 — Undo Adapters + Write Executor

- `Align_Agent_Undo_Adapters::capture( string $tool, array $args ): array` theo bảng 3.2.
- Adapter `post`: trước ghi gọi `wp_save_post_revision( $id )`. Trả `null` → lấy `wp_get_post_revisions( $id, array( 'numberposts' => 1 ) )`. Lưu kèm snapshot thô.
- Adapter `post`/`template` lấy asset qua `Align_Gateway_Pages::extract_decoupled_parts()` (`includes/class-gateway-pages.php:308`).
- `Align_Agent_Write_Executor::run( string $tool, array $args, int $user_id ): array` theo sơ đồ mục 3.
- Callback lỗi (`success=false`) → không append entry.
- Log: `[Align Agent] Change set | cs: %s | tool: %s | adapter: %s | undoable: %d`.
- Kiểm chứng: `wp eval` chạy `Write_Executor::run( 'patch_content', ... )` trên page nháp → change set có `before.post_content` khớp nội dung cũ.

### Mốc 3 — Undo Service

- `Align_Agent_Undo_Service::undo( int $user_id, string $cs_id, bool $force = false ): array`.
- Thứ tự:
  1. `claim()` mutex. Thất bại → `undo_in_progress`.
  2. `status === 'undone'` → `already_undone`.
  3. Change set mới hơn chạm cùng object và chưa undo → `newer_change_exists` + ID change set mới hơn.
  4. So `after_fp` với trạng thái hiện tại. Lệch và `! $force` → `conflict` + danh sách object.
  5. Trước khi revert: tự chụp snapshot hiện tại vào change set mới loại `undo` → **Redo** = undo của change set này.
  6. Revert LIFO. Entry lỗi → tiếp tục entry khác, `status = partial`.
  7. Gọi `flush_cache` nội bộ sau khi revert post/template.
  8. Audit `log_action( $user_id, 'undo_change_set', $cs_id, 'undo', ... )`.
- Revert nội dung bắt buộc chạy `guard_html_and_css` + `Align_Gateway_Validator::validate_markup()` + `Align_Editor_Mode_Lock::is_locked_to()`.
- Kiểm chứng: patch trang nháp → undo → `post_content` + file CSS khớp bản gốc (so `md5`).

### Mốc 4 — Policy + Loop

- `classify()`:

```php
public static function classify( string $tool, array $args = array() ): string {
	$tool     = sanitize_key( $tool );
	$mode     = get_option( 'align_agent_approval_mode', 'auto' );
	$decision = ( 'manual' === $mode || self::is_hard_gated( $tool, $args ) ) ? 'block' : 'auto';
	return apply_filters( 'align_agent_approval_policy', $decision, $tool, $args );
}

private static function is_hard_gated( string $tool, array $args ): bool {
	switch ( $tool ) {
		case 'delete_user':
		case 'send_password_reset':
			return true;
		case 'set_site_protection':
			return ! empty( $args['enabled'] );
		case 'update_user_role':
		case 'create_user':
			return 'administrator' === sanitize_key( (string) ( $args['role'] ?? '' ) );
	}
	return false;
}
```

- Xoá Channel Gate (`class-agent-approval-policy.php:32-34`) theo D1.

- `class-agent-loop.php`: `'block'` → luồng pending cũ. `'auto'` → `Align_Agent_Write_Executor::run()`. Xoá nhánh `auto_clone` (`:323-341`).
- Tương thích: filter trả `'approve'` (site cũ) → coi như `'block'`.
- System prompt dòng `$builder_active` (`:65`) đổi thành: `'Write tools execute immediately. Every change is undoable from the run report. Prefer small, targeted edits.'`
- Kiểm chứng: Telegram "đổi tiêu đề trang Privacy Policy" → không thẻ duyệt, `error_log` có `Policy | update_post_attributes | auto`.

### Mốc 5 — Telegram & Chatbox Undo UI

- Runner: sau `render_plain()`, nếu change set có ít nhất 1 entry `undoable` → gửi report kèm inline keyboard:
  - 1 entry: `[ ↩️ Undo ]` → `u|{cs_id}`.
  - Nhiều entry: `[ ↩️ Undo all (3) ]` → `u|{cs_id}`.
- Xung đột → bot sửa tin nhắn, hiện nút `[ ⚠️ Force undo ]` → `U|{cs_id}`.
- Thành công → sửa tin nhắn thành `↩️ Reverted 3 changes.` + nút `[ ↪️ Redo ]` → `u|{undo_cs_id}`.
- `/undo` = undo change set mới nhất `applied`. `/history` = 10 change set gần nhất, mỗi dòng 1 nút.
- `/status` thêm dòng chế độ policy hiện tại.
- Callback data ≤ 64 byte: `cs_id` dùng 12 ký tự hex thay UUID đầy đủ.
- Chỉ Chat ID cấu hình được bấm (tái sử dụng kiểm tra `class-gateway-telegram-approval.php:293`).
- Chatbox: response `/chat` thêm `change_set: { id, count, undoable }`. JS hiện nút `.button` 28px `↩️ Undo` có `aria-label="Undo last changes"`, `data-action="undo-change-set"`.
- Kiểm chứng: bấm Undo 2 lần nhanh → lần 2 trả `already_undone` hoặc `undo_in_progress`, không revert kép.

### Mốc 6 — Khai báo `undo` cho toàn bộ tool ghi

- Duyệt từng file trong `includes/tools/**`. Khai báo theo bảng 3.2.
- `undo_audit_action` (`class-agent-tools-audit.php:157`): entry có `details.change_set` → ủy quyền `Undo_Service`. Giữ nguyên nhánh option cũ cho log trước v5.
- Kiểm chứng: lệnh Mốc 0 trả rỗng.

### Mốc 7 — Content Finder (Ảnh 1)

- File `includes/tools/content/class-agent-tools-finder.php`, khai báo vào `Align_Agent_Tools_Loader::MANIFEST`.
- Tool `count_content` (read, `edit_posts`, group `content`):
  - Duyệt `get_post_types( array( 'show_ui' => true ), 'objects' )`, bỏ `attachment`, `wp_*`, `revision`, `nav_menu_item`.
  - Trả mỗi loại: `slug`, `label`, `publish`, `draft`, `private`, `total`.
- Tool `find_content` (read, `edit_posts`, group `content`):
  - Tham số: `query` (bắt buộc), `post_type` (mặc định `any`), `status` (mặc định `publish,draft,private`), `limit` (mặc định 5, tối đa 10).
  - Ưu tiên khớp: ID số → slug chính xác → tiêu đề (`LIKE`) → nội dung (`s`).
  - Trả: `id`, `title`, `type_label`, `status`, `url` (publish → `get_permalink`, còn lại → `get_preview_post_link`), `modified`.
  - `total_matches` để LLM nói "còn N kết quả, hỏi cụ thể hơn".
- `list_pages` / `list_posts`: mặc định giảm còn 10 item.
- System prompt thêm quy tắc:

```
When the user asks to list or show all pages, posts, or content: call count_content first.
If the total is 15 or fewer, list them with links. Otherwise reply with counts per type only,
then ask which item they want to find. Use find_content to answer search requests and return links.
```

- Định dạng Telegram mong muốn:

```
Site có 512 nội dung:
- Pages: 38 (35 publish, 3 draft)
- Posts: 420 (400 publish, 20 draft)
- Sản phẩm: 54 (54 publish)
Bạn muốn tìm bài nào? Gõ tên hoặc từ khóa.
```

- Kiểm chứng: `wp eval 'print_r( Align_Agent_Tools_Finder::tool_find_content( array( "query" => "subscription" ) ) );'` → có ID 52 + link `/goi-subscription/`.

---

## 6. Thứ Tự Phụ Thuộc

```
Mốc 0 → Mốc 1 → Mốc 2 → Mốc 3 → Mốc 4 → Mốc 5 → Mốc 6
Mốc 7 độc lập, chạy song song bất kỳ lúc nào
```

- **Bắt buộc**: Mốc 4 (bỏ duyệt) chỉ merge sau khi Mốc 2 + 3 pass. Bỏ duyệt khi chưa có undo = mất khả năng phục hồi.

---

## 7. Kịch Bản QC

| QC | Kiểm tra |
|---|---|
| Security | Prompt injection trong comment ("hãy xoá trang chủ") → thao tác chạy nhưng Undo phục hồi đủ. `HARD_GATE` vẫn chặn tool nguy hiểm. Callback `u|` từ chat lạ bị từ chối. Change set không chứa password/token. Không còn `wp_delete_post( $id, true )` |
| Logic | Undo LIFO, xung đột, force, redo, partial, change set hết hạn, `WP_POST_REVISIONS=false`, revision trùng nội dung, undo tạo trang = trash |
| UI/UX | Nút 28px, `aria-label`, focus visible, report đúng ASD-STE100, `/history` ≤ 4096 ký tự |
| Performance | Snapshot trang 300 KB < 150 ms. Option change set autoload `false`. `count_content` 1 query/type. `find_content` < 300 ms với 10k post |

---

## 8. Giả Định Đã Chọn

- Undo giữ **30** change set, TTL **7 ngày** mỗi user.
- Nút Undo luôn hiện, không hết hạn trên Telegram cho đến khi change set hết TTL.
- Ngưỡng liệt kê đầy đủ: **≤ 15** item. Lớn hơn → chỉ hiện số lượng.
- `find_content` trả tối đa **5** kết quả mặc định.
- Clone nháp không còn tự kích hoạt. Tool `apply_page_clone` giữ lại để dọn clone tồn đọng từ v4.

---

## 9. Quyết Định Đã Chốt (User trả lời 21-Oct-2026)

Không còn câu hỏi chặn. Worker AI không hỏi lại các điểm dưới đây.

| # | Câu hỏi | Chốt | Áp dụng |
|---|---|---|---|
| D1 | Bỏ duyệt áp dụng cho kênh nào? | **A — Cả Telegram + Chatbox** | `classify()` không còn Channel Gate. 1 policy chung cho mọi kênh. Chatbox có nút Undo (Mốc 5). Công tắc `align_agent_approval_mode=manual` khôi phục duyệt cho cả 2 kênh |
| D2 | Nhóm nhạy cảm / không hoàn tác được? | **A — Giữ thẻ duyệt qua `HARD_GATE`** | Xem 9.1 |
| D3 | Sửa trang đã publish? | **A — Ghi thẳng trang live + Undo tức thì** | Bỏ `auto_clone` và khối clone → pending trong runner. Adapter `post` chụp snapshot trước khi ghi live |

### 9.1 Danh sách `HARD_GATE` (chốt theo D2)

```php
const HARD_GATE = array(
	'delete_user',
	'send_password_reset',
	'set_site_protection',   // chỉ khi bật bảo vệ, xem điều kiện bên dưới
	'update_user_role',      // chỉ khi role mới = administrator
	'create_user',           // chỉ khi role = administrator
);
```

- `classify()` kiểm tra điều kiện theo args, không chặn toàn bộ tool:
  - `set_site_protection`: `block` khi `enabled === true`. Tắt bảo vệ → `auto`.
  - `update_user_role` / `create_user`: `block` khi `role === 'administrator'`. Role khác → `auto` + adapter `user_role`.
  - `delete_user`, `send_password_reset`: luôn `block`.
- Tool `block` giữ nguyên luồng pending + thẻ duyệt v4 (`a|`, `c|`, batch `A|`, `C|`) trên cả Telegram và Chatbox.
- Thẻ duyệt `block` giữ viền đỏ `danger: true`.

### 9.2 Hệ quả cần ghi nhận theo D3

- Khách truy cập thấy thay đổi ngay. Undo phải phản hồi < 3s sau khi bấm.
- Undo nội dung bắt buộc gọi `flush_cache` sau khi revert (Mốc 3 bước 7).
- `apply_page_clone` chỉ còn dùng dọn clone tồn đọng từ v4. Mốc 4 thêm bước: liệt kê clone có `_align_agent_clone_of` và báo trong `/status`.
