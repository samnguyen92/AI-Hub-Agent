# Kế Hoạch Triển Khai — Convert to Telegram v3

> Kế thừa `convert-to-telegram-v2.md` (streaming, media intake). v3 sửa lỗi thẻ duyệt bị `expired`. v3 cũng đổi chính sách duyệt: chỉ thao tác global cần duyệt. Trang đã publish được sửa trên bản clone nháp.

## Bối Cảnh & Bằng Chứng

### Lỗi 1: Thẻ duyệt báo `expired` ngay sau khi tạo (`logs/php/error.log`, 20-Oct-2026 07:45–07:47 UTC)

| Thời điểm (UTC) | Sự kiện |
|---|---|
| 07:46:01 | Request mới tới. 07:46:03 bot gửi ack `⏳ I received your request...` |
| 07:46:07 | Runner A queue `pw_CWRJAjPQwID6hwrg` (header) + `pw_cpqPTbuyaty8d8Wo` (footer) |
| 07:46:09 → 07:46:25 | Runner B vẫn chạy song song 5 step (`tool_args_bytes: 88`) |
| 07:46:17, 07:46:20 | User bấm duyệt → `not_found` → thẻ bị gạch, báo "15-minute limit" |
| 07:46:40 | User gửi "làm lại đi" → `Runner lock active` |
| 07:46:49 | Runner B queue `pw_NFelbdE5Doa4Oi77` (save_template header) → thẻ cũng `expired` |

Nguyên nhân gốc:

1. **Ghi đè do cache cũ (lost update)**:
   - Toàn bộ hội thoại + `pending_writes` nằm trong 1 user meta (`includes/class-agent-conversation.php:90-152`).
   - Site không có `wp-content/object-cache.php`. `get_user_meta` đọc cache RAM của từng tiến trình PHP.
   - Runner chạy lâu đọc blob cũ. `add_assistant_message` / `add_tool_message` (`:249`, `:281`) gọi `save_state` ghi đè cả blob. Pending của tiến trình khác bị xoá.
2. **Khoá runner hết hạn giữa chừng**:
   - `LOCK_TTL = 120` (`includes/class-gateway-telegram-runner.php:26`).
   - Một lượt có tối đa 12 turn × 300s. Khoá hết hạn nên runner thứ 2 chạy song song.
3. **Tin nhắn mới xoá sạch pending**:
   - `add_user_message` → `close_orphan_tool_calls` gán `pending_writes = array()` (`includes/class-agent-conversation.php:228`, `:419`).
   - Thẻ cũ trên Telegram vẫn còn nút bấm.
4. **Callback chạy đồng bộ, không khoá**:
   - `handle_callback_query` chạy trong request webhook (`includes/class-gateway-webhook.php:220`).
   - `resume_runner` gọi tối đa 12 turn LLM trước khi trả HTTP (`includes/class-gateway-telegram-approval.php:270-300`). Hàm này không lấy `align_agent_tg_lock`.
5. **Thẻ gửi lặp**:
   - Duyệt thẻ 1 → `resume_runner` gửi lại toàn bộ pending còn lại (`includes/class-gateway-telegram-approval.php:274-281`). Thẻ footer xuất hiện 2 lần.
6. **Thông báo sai**:
   - Mọi `not_found` đều báo "15-minute limit" (`includes/class-agent-approval-service.php:64`).

### Lỗi 2: Duyệt quá nhiều thao tác nhỏ (`logs/php/error.log`, 07:53:08 → 07:54:49 UTC)

- 5 thẻ `patch_content` cho Page #52, cách nhau 20–30s.
- Chuỗi lặp: duyệt → `resume_runner` → LLM patch tiếp → thẻ mới.
- `Align_Agent_Loop::run_step` queue mọi tool `type = write` (`includes/class-agent-loop.php:263-275`). Không phân biệt global và local.

### Tài nguyên tái sử dụng

- `duplicate_post` đã clone trang qua `Align_Gateway_Pages::extract_decoupled_parts()` + `create-item` + `save-page` (`includes/tools/content/class-agent-tools-posts.php:387-420`).
- CSS/JS builder lưu theo slug (`assets/builder/style-page-{slug}.css`). Apply clone vào trang gốc phải ghi qua `save-page` với `post_id` gốc, để file theo slug gốc được ghi đúng.
- `Align_Agent_Audit_Log` đã có snapshot để hoàn tác.

## Hiểu Yêu Cầu & Ranh Giới

- **Mục tiêu**:
  - Thẻ duyệt không còn `expired` sai. Duyệt xong thì AI chạy tiếp.
  - Chỉ thao tác global cần duyệt. Thao tác khác tự chạy.
  - Trang đã publish: AI sửa trên clone nháp. User bấm duyệt thì áp vào trang gốc.
  - Cuối lượt: bot gửi báo cáo ngắn, theo ASD-STE100, người không rành kỹ thuật đọc hiểu được.
- **Quyết định đã chốt với User**:
  1. Sửa `AGENTS.md` §1 cấm số 3: cho phép tự ghi các thao tác không global.
  2. Trang publish → clone nháp → sửa → User duyệt → apply vào gốc.
  3. Tự chạy mọi thao tác, trừ nhóm global (bảng ở Mốc 3).
  4. Chỉ áp dụng cho kênh **Telegram**. Chatbox giữ nguyên luồng duyệt cũ (Chatbox sẽ gỡ sau).
  5. Redirect (`create_redirect`, `create_redirect_from_404`) tự chạy, không hỏi.
  6. Clone trang bị chuyển vào thùng rác ngay sau Apply hoặc Discard. Không giữ lại.
  7. Trả lời comment và SEO meta tự chạy. Comment trả lời hiển thị công khai ngay.
- **Nghiệm thu**:
  - Prompt sửa header + footer: 1 thẻ gộp, bấm `Approve all` → cả 2 chạy, AI trả lời tiếp. Không có thẻ `expired`.
  - Gửi tin mới khi đang có thẻ chờ: thẻ cũ đổi thành "Superseded", không còn nút.
  - Prompt sửa nhiều chỗ trên trang publish: 0 thẻ trong lúc sửa. Cuối lượt có 1 thẻ "Apply to live page" + link preview clone.
  - Tạo trang mới: tự chạy, `post_status = draft`, không có thẻ.
  - Báo cáo cuối lượt: mỗi câu ≤ 20 từ, có danh sách thay đổi, link xem, việc User cần làm.
- **Non-goals**:
  - Thêm persistent object cache (Redis/Memcached).
  - Mọi thay đổi hành vi hoặc giao diện Chatbox. Chatbox vẫn duyệt mọi thao tác ghi.
  - Tự dọn clone theo thời gian.
  - Merge 3 chiều khi trang gốc bị sửa tay trong lúc clone đang chờ (chỉ cảnh báo, xem Mốc 4).
- **File ảnh hưởng**:
  - Tạo mới: `includes/class-agent-pending-store.php`, `includes/class-agent-approval-policy.php`, `includes/class-agent-page-clone.php`, `includes/class-agent-run-report.php`, `includes/class-gateway-telegram-lock.php`, `includes/tools/content/class-agent-tools-clone.php`
  - Sửa: `includes/class-agent-conversation.php`, `includes/class-agent-approval-service.php`, `includes/class-agent-loop.php`, `includes/class-agent-tool-registry.php`, `includes/class-gateway-telegram-runner.php`, `includes/class-gateway-telegram-approval.php`, `includes/class-gateway-webhook.php`, `includes/tools/class-agent-tools-loader.php`, `align-agent.php`, `AGENTS.md`, `README.md`, `includes/tools/README.md`, `includes/tools/content/README.md`, `docs/BUG_LOGS.md`

## Đánh Giá Phương Án

### Điểm yếu (Hạn chế / Rủi ro)

- Tự ghi không duyệt làm tăng rủi ro AI sửa sai. Audit snapshot là lớp hoàn tác duy nhất.
- Clone nháp tạo thêm post và file CSS/JS theo slug clone. Phải dọn sau khi apply hoặc huỷ.
- Trang gốc có thể bị sửa tay khi clone đang chờ duyệt. Apply sẽ ghi đè thay đổi đó.
- Hai kênh có hai chính sách khác nhau. Prompt giống nhau cho kết quả khác nhau giữa Chatbox và Telegram.
- Redirect tự chạy có thể tạo vòng lặp redirect hoặc chặn URL đang có traffic. Audit Log là lớp hoàn tác duy nhất.
- Clone bị trash ngay sau Apply. Muốn xem lại bản nháp phải khôi phục từ thùng rác hoặc dùng snapshot Audit Log.
- Callback chuyển sang chạy nền cần môi trường có `fastcgi_finish_request` hoặc loopback hoạt động.

### Điểm mạnh (Ưu điểm / Khả thi)

- Lỗi expired có nguyên nhân rõ, sửa trong 3 file lõi.
- `duplicate_post`, `Align_Builder_Crud_Handler`, `Audit_Log` đã có. Clone/apply chủ yếu là ghép lại.
- Một điểm quyết định duy nhất (`Align_Agent_Approval_Policy`). Gate theo kênh nên Chatbox không bị ảnh hưởng.
- Gỡ Chatbox sau này không cần sửa policy.
- Số lần bấm giảm từ N xuống 0–1 mỗi lượt.

### Giải pháp đề xuất & Lý do

* Giải pháp:* Lưu mỗi pending thành option riêng `align_agent_pw_{id}` (không autoload, có `expires_at`). -> *Lý do:* Ghi đè blob hội thoại không còn xoá được pending.
* Giải pháp:* `get_state()` gọi `wp_cache_delete( $user_id, 'user_meta' )` trước khi đọc. -> *Lý do:* Tiến trình chạy lâu luôn đọc dữ liệu mới.
* Giải pháp:* Khoá runner TTL 900s, gia hạn sau mỗi turn, dùng chung cho message và callback. -> *Lý do:* Chỉ 1 runner chạy tại một thời điểm.
* Giải pháp:* `Align_Agent_Approval_Policy::classify()` trả `auto` | `auto_clone` | `approve`. -> *Lý do:* Quy tắc duyệt nằm ở 1 chỗ, dễ kiểm và dễ sửa.
* Giải pháp:* Báo cáo cuối lượt do server dựng từ sự kiện thật (`Align_Agent_Run_Report`). LLM chỉ viết thêm 1–2 câu. -> *Lý do:* Báo cáo đúng sự thật, ngắn, không phụ thuộc LLM.

## Mốc Thực Thi

### Mốc 1: Toàn vẹn trạng thái pending (REQ-01, REQ-02, REQ-03)

- **File**:
  - `includes/class-agent-pending-store.php` (mới) — `Align_Agent_Pending_Store`:
    - `add( int $user_id, string $channel, array $item ): string` → option `align_agent_pw_{id}`, `autoload = false`.
    - `get()`, `take( $id )` (đọc + xoá; trả `null` kèm lý do `expired` | `superseded` | `already_decided` | `not_found`).
    - `list_for( $user_id, $channel )` qua index option `align_agent_pw_index_{channel}_{user_id}`.
    - `supersede_all( $user_id, $channel )`: đánh dấu `superseded`, trả danh sách `tg_message_id` để sửa thẻ.
    - Dọn bản ghi hết hạn khi gọi `list_for`.
    - `take()` dùng `add_option( 'align_agent_pw_claim_{id}' )` làm mutex. Hai click đồng thời chỉ 1 click thắng.
  - `includes/class-agent-conversation.php`:
    - `get_state()`: `wp_cache_delete( $user_id, 'user_meta' )` trước `get_user_meta`.
    - `queue_pending_write()` / `pop_pending_write()` chuyển sang `Align_Agent_Pending_Store`. Giữ nguyên chữ ký hàm.
    - `close_orphan_tool_calls()`: gọi `supersede_all()` thay vì gán mảng rỗng. Trả danh sách message id bị supersede.
    - `get_state()['pending_writes']` lấy từ store để `/status` và Chatbox không đổi.
  - `includes/class-agent-approval-service.php`:
    - Trả `status` theo lý do: `expired`, `superseded`, `already_decided`, `not_found`.
    - Mỗi lý do có 1 thông báo tiếng Anh riêng. Bỏ câu cứng "15-minute limit".
  - `includes/class-gateway-telegram-lock.php` (mới) — `Align_Gateway_Telegram_Lock`:
    - `acquire(): bool` qua `add_option( 'align_agent_tg_lock', time() + 900, '', 'no' )`, tự giải phóng khoá quá hạn.
    - `refresh()`, `release()`.
  - `includes/class-gateway-telegram-runner.php`:
    - Thay transient `LOCK_KEY` bằng `Align_Gateway_Telegram_Lock`. Gọi `refresh()` sau mỗi `run_step`.
    - Log `[Align Agent] TG lock acquired|refreshed|released|busy`.
- **Kiểm chứng**:
  ```bash
  for f in includes/*.php includes/tools/*/*.php; do php -l "$f" | grep -v "No syntax errors"; done
  ```
  ```bash
  S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --path=../../.. --exec="define('DB_HOST','localhost:$S');" eval 'Align_Agent_Conversation::set_channel("telegram"); $id = Align_Agent_Conversation::queue_pending_write(1, "call_t1", "save_template", array("tp_id"=>"tp_header"), array()); $s = Align_Agent_Conversation::get_state(1); $s["messages"][] = array("role"=>"user","content"=>"x"); update_user_meta(1, Align_Agent_Conversation::get_meta_key(), array("messages"=>array())); echo Align_Agent_Conversation::pop_pending_write(1, $id) ? "SURVIVED" : "LOST";'
  ```
  ```bash
  wc -l includes/class-agent-conversation.php includes/class-gateway-telegram-runner.php includes/class-agent-pending-store.php
  ```
- **Kỳ vọng**:
  - Không có lỗi cú pháp.
  - Lệnh 2 in `SURVIVED` (ghi đè blob không xoá pending).
  - Mọi file < 500 dòng.

### Mốc 2: Callback chạy nền + thẻ gộp + thẻ superseded (REQ-04, REQ-05, REQ-06)

- **File**:
  - `includes/class-gateway-webhook.php`:
    - Callback: chỉ xác thực chat ID, gọi `answerCallbackQuery` ngay ("Processing..."), rồi đẩy xử lý vào `shutdown` (cùng cơ chế `schedule_run`).
  - `includes/class-gateway-telegram-approval.php`:
    - `send_pending_cards()`: khi 1 step có ≥ 2 pending, gửi **1 thẻ gộp** liệt kê từng thao tác. Nút: `✅ Approve all` (`A|{batch_id}`), `❌ Cancel all` (`C|{batch_id}`). Khi chỉ có 1 pending thì giữ thẻ đơn `a|` / `c|`.
    - Lưu `tg_message_id` vào pending item sau khi gửi.
    - `handle_callback_query()`: chạy trong khoá `Align_Gateway_Telegram_Lock`. Đang bận thì trả callback "Busy, try again in a moment".
    - `resume_runner()`: chỉ gọi `run_step` khi hàng đợi của batch đã trống. **Không** gửi lại thẻ còn chờ.
    - Thêm `mark_superseded( array $message_ids )`: `editMessageText` thành "⏭ Superseded by a new message." và gỡ `reply_markup`.
  - `includes/class-gateway-telegram-runner.php`:
    - Sau `add_user_message`, gọi `mark_superseded()` cho id trả về từ Mốc 1.
- **Kiểm chứng**:
  ```bash
  for f in includes/*.php; do php -l "$f" | grep -v "No syntax errors"; done
  ```
  - Telegram: prompt "đổi màu nền header và footer sang #111". Bấm `Approve all`.
  - Telegram: tạo thẻ chờ, gửi tin "hello", kiểm tra thẻ cũ.
  - Bấm 2 lần thật nhanh lên cùng 1 thẻ.
  ```bash
  grep -E 'TG lock|Approved write|superseded|already_decided' ../../../../logs/php/error.log | tail -20
  ```
- **Kỳ vọng**:
  - 1 thẻ gộp. Bấm xong: 2 lần `Template saved`, sau đó AI trả lời tóm tắt. Không có thẻ lặp.
  - Thẻ cũ đổi thành "Superseded", không còn nút.
  - Click thứ 2 nhận `already_decided`, không chạy tool 2 lần.
  - Webhook callback trả HTTP 200 < 2s.

### Mốc 3: Chính sách duyệt theo phạm vi (REQ-07, REQ-08)

- **File**:
  - `includes/class-agent-approval-policy.php` (mới) — `Align_Agent_Approval_Policy::classify( string $tool, array $args ): string`:

    | Kết quả | Tool / điều kiện |
    |---|---|
    | `approve` (global) | `save_template`; `patch_content` với `target_type` = `template` hoặc `reuse_block`; `create_reuse_block`; `save_reuse_block`; `save_global_code`; `set_homepage`; `update_site_identity`; toàn bộ group `users`; mọi tool `danger = true` (`trash_post`, `trash_media`, `delete_term`, `delete_redirect`, ...); `set_post_status` sang `publish` cho trang đã có; `restore_revision` trên trang đã publish; `apply_page_clone` |
    | `auto_clone` | `save_page`, `save_post`, `patch_content` (`page`/`post`), `update_post_attributes` khi bài đích đang `publish` |
    | `auto` | Còn lại: `create_page` / `create_post` (ép `status = draft`), sửa bài đang `draft`, media (`sideload_media`, `attach_media`, `set_featured_image`, `update_media_meta`), taxonomy (`create_term`, `update_term`, `assign_terms`), comments (`reply_comment`, `moderate_comment`), `save_seo_*`, `create_redirect`, `create_redirect_from_404`, `flush_cache` |

    - **Gate theo kênh**: nếu `Align_Agent_Conversation::get_channel() !== 'telegram'` thì luôn trả `approve` cho mọi tool ghi. Chatbox giữ nguyên hành vi cũ.
    - Cho phép override bằng filter `align_agent_approval_policy`.
  - `includes/class-agent-tool-registry.php`: thêm khoá `scope` (`global` | `local`) vào definition. `classify()` ưu tiên `danger`, sau đó `scope`, sau đó bảng trên.
  - `includes/class-agent-loop.php` (dòng 263-286):
    - Gọi `classify()` cho mọi tool ghi. Kênh Chatbox luôn nhận `approve`.
    - `approve` → queue như cũ.
    - `auto` → `Align_Agent_Tool_Registry::execute()` + `Align_Agent_Audit_Log::log_action()` + `add_tool_message()`.
    - `auto_clone` → chuyển sang Mốc 4.
    - Ghi sự kiện vào `Align_Agent_Run_Report` (Mốc 5).
    - Log `[Align Agent] Policy | tool | decision`.
  - `includes/tools/content/class-agent-tools-pages.php`, `class-agent-tools-posts.php`: `create_page` / `create_post` ép `status = draft` khi chạy `auto`.
  - `includes/tools/seo/class-agent-tools-redirects.php`: `create_redirect` chạy `auto`. Trước khi ghi, kiểm 2 điều kiện:
    - `source` khác `target`.
    - `target` không phải `source` của redirect khác (chặn vòng lặp A→B→A).
    - Vi phạm thì trả lỗi `redirect_loop` cho LLM, không ghi.
- **Kiểm chứng**:
  ```bash
  S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --path=../../.. --exec="define('DB_HOST','localhost:$S');" eval 'Align_Agent_Conversation::set_channel("telegram"); foreach (array(array("save_template",array()),array("patch_content",array("target_type"=>"template")),array("patch_content",array("target_type"=>"page","post_id"=>52)),array("create_page",array()),array("delete_user",array())) as $c) echo $c[0], " => ", Align_Agent_Approval_Policy::classify($c[0],$c[1]), PHP_EOL;'
  ```
  - Chạy lại lệnh trên, đổi `set_channel("telegram")` thành `set_channel("chatbox")` ở đầu `eval`. Thêm case `array("create_redirect",array())` vào danh sách.
- **Kỳ vọng**:
  - Kênh `telegram`: `save_template => approve`, `patch_content(template) => approve`, `patch_content(page 52, publish) => auto_clone`, `create_page => auto`, `delete_user => approve`, `create_redirect => auto`.
  - Kênh `chatbox`: mọi dòng `=> approve`.

### Mốc 4: Clone nháp cho trang đã publish (REQ-09, REQ-10, REQ-11)

- **File**:
  - `includes/class-agent-page-clone.php` (mới) — `Align_Agent_Page_Clone`:
    - `get_or_create( int $original_id ): int`:
      - Tìm clone còn sống qua meta `_align_agent_clone_of = {original_id}` + `_align_agent_clone_channel`.
      - Chưa có thì tạo bằng logic `duplicate_post` (tách hàm dùng chung, không gọi qua tool). Title `[Draft] {title}`, `status = draft`.
      - Lưu `_align_agent_clone_base_modified` = `post_modified_gmt` của trang gốc.
      - **Mọi sửa liên tiếp trên cùng trang gốc dùng chung 1 clone.**
    - `rewrite_args( string $tool, array $args ): array`: đổi `post_id` gốc → clone id.
    - `apply( int $clone_id ): array`:
      - Kiểm `Align_Editor_Mode_Lock::is_locked_to( $original_id, 'gutenberg' )`.
      - Snapshot gốc vào Audit Log.
      - Lấy parts clone qua `extract_decoupled_parts()`. `guard_html_and_css` + `Align_Gateway_Validator::validate_markup()`.
      - Ghi vào trang gốc qua `Align_Builder_Crud_Handler` `save-page` (giữ `status` gốc, file CSS/JS theo slug gốc).
      - `wp_trash_post( $clone_id )` **ngay sau khi apply thành công**. Không giữ clone. Không xoá vĩnh viễn (`AGENTS.md` §4.3 cấm `wp_delete_post( $id, true )`).
      - Trả cảnh báo `base_changed` nếu `post_modified_gmt` gốc khác giá trị đã lưu.
    - `discard( int $clone_id )`: `wp_trash_post()`.
  - `includes/tools/content/class-agent-tools-clone.php` (mới) — tool `apply_page_clone` (write, `scope = global`, group `content`):
    - Không đưa vào OpenAI schema (`hidden = true`). Chỉ server queue.
    - Khai báo trong `Align_Agent_Tools_Loader::MANIFEST`.
  - `includes/class-agent-tool-registry.php`: hỗ trợ cờ `hidden` khi build schema.
  - `includes/class-agent-loop.php`:
    - `auto_clone` → `get_or_create()` → `rewrite_args()` → execute → `add_tool_message()` với ghi chú "Edited draft clone #{id} of page #{orig}".
  - `includes/class-gateway-telegram-runner.php`:
    - Khi lượt kết thúc (`done`), mỗi clone đã sửa trong lượt → queue 1 pending `apply_page_clone`.
    - Thẻ có link preview clone (`get_preview_post_link`). Nút `✅ Apply to live page` / `🗑 Discard draft`.
    - `/status`: liệt kê clone đang chờ (meta `_align_agent_clone_of`) và gửi lại thẻ Apply/Discard nếu thẻ cũ đã hết hạn.
- **Kiểm chứng**:
  ```bash
  S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --path=../../.. --exec="define('DB_HOST','localhost:$S');" eval 'require_once get_template_directory() . "/core/inc/builder/class-builder-rest-api.php"; $c1 = Align_Agent_Page_Clone::get_or_create(52); $c2 = Align_Agent_Page_Clone::get_or_create(52); echo $c1 === $c2 ? "SAME_CLONE " : "DIFF ", get_post_status($c1), " ", get_post_status(52);'
  ```
  - Telegram: "sửa tiêu đề và màu nút trang #52" → kiểm trang live chưa đổi → bấm Apply → kiểm trang live đã đổi, clone ở thùng rác.
  ```bash
  ls ../../themes/Align-Real-Builder/align-child/assets/builder/ | grep -i draft
  ```
- **Kỳ vọng**:
  - `SAME_CLONE draft publish`.
  - Trước Apply: trang #52 live không đổi. Sau Apply: đổi đúng. Audit Log có snapshot.
  - Ngay sau Apply/Discard: clone có `post_status = trash`.
  - Không còn file CSS/JS mồ côi theo slug clone (dọn trong `apply()` / `discard()`).

### Mốc 5: Báo cáo cuối lượt cho người không rành kỹ thuật (REQ-12)

- **File**:
  - `includes/class-agent-run-report.php` (mới) — `Align_Agent_Run_Report`:
    - `record( string $kind, array $data )` trong 1 lượt (`created`, `edited`, `edited_clone`, `uploaded`, `needs_approval`, `failed`).
    - `render_plain(): string` theo mẫu cố định, tiếng Anh, ASD-STE100:
      ```
      ✅ Done. I made 3 changes.

      What changed:
      • Created page "Pricing" (draft). View: <link>
      • Edited a draft copy of "Home". View: <link>
      • Added 2 images to the Media Library.

      What you need to do:
      • Check the draft copy of "Home".
      • Tap "Apply to live page" to publish the changes.
      ```
    - Quy tắc: mỗi câu ≤ 20 từ, 1 ý mỗi câu, thể chủ động, không thuật ngữ (`post_id`, `patch`, `HTML`), không ID kỹ thuật trừ link.
    - Mục "What you need to do" chỉ có khi còn thẻ chờ duyệt hoặc có lỗi.
  - `includes/class-agent-loop.php` → `build_system_prompt()`: thêm quy tắc trả lời cuối: tối đa 2 câu, ≤ 20 từ mỗi câu, không lặp danh sách thay đổi (server đã gửi).
  - `includes/class-gateway-telegram-runner.php`: lượt `done` gửi `render_plain()` trước, rồi gửi reply LLM nếu khác rỗng.
- **Kiểm chứng**:
  - Telegram: "tạo trang Pricing và sửa tiêu đề trang Home".
  ```bash
  S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --path=../../.. --exec="define('DB_HOST','localhost:$S');" eval '$r = new Align_Agent_Run_Report(); $r->record("created", array("title"=>"Pricing","url"=>"https://x")); $r->record("edited_clone", array("title"=>"Home","url"=>"https://y")); $t = $r->render_plain(); foreach (preg_split("/(?<=[.!?])\s+|\n/", $t) as $s) { if (str_word_count($s) > 20) echo "TOO_LONG: $s\n"; } echo $t;'
  ```
- **Kỳ vọng**:
  - Không có dòng `TOO_LONG`.
  - Báo cáo đúng mẫu, có link, có mục "What you need to do".

### Mốc 6: Tài liệu & quy tắc (REQ-13)

- **File**:
  - `AGENTS.md`:
    - §1 cấm số 3: đổi thành "Chỉ thao tác global (`Align_Agent_Approval_Policy::classify() === 'approve'`) bắt buộc duyệt. Trang publish chỉ sửa qua clone nháp."
    - §3.4: cập nhật khoá `Align_Gateway_Telegram_Lock` (TTL 900s), thẻ gộp `A|` / `C|`, thẻ superseded, callback chạy nền.
    - §3.5 Chatbox: giữ nguyên. Thêm 1 dòng: "Policy tự ghi chỉ áp dụng cho kênh Telegram."
    - §4.3: thêm `Align_Agent_Pending_Store`, `Align_Agent_Page_Clone`, `Align_Agent_Run_Report`.
  - `README.md`: mô tả chính sách duyệt và luồng clone.
  - `includes/tools/README.md`, `includes/tools/content/README.md`: thêm `apply_page_clone`, cột `scope`.
  - `docs/BUG_LOGS.md`: ghi lỗi expired (nguyên nhân 1–6, bằng chứng log 07:46 UTC).
- **Kiểm chứng**:
  ```bash
  grep -n "Approval_Policy\|Page_Clone\|Pending_Store\|apply_page_clone" AGENTS.md README.md includes/tools/README.md includes/tools/content/README.md docs/BUG_LOGS.md
  ```
- **Kỳ vọng**: mỗi file có ít nhất 1 dòng khớp.

## Giả định đã chọn

- `save_seo_*` trên trang publish ghi thẳng vào trang gốc (không clone). Lý do: SEO meta nhỏ, Audit Log hoàn tác được.
- `delete_redirect` vẫn cần duyệt. Lý do: tool này xoá vĩnh viễn rule trong Rank Math và có cờ `danger = true`. User chỉ yêu cầu tự tạo redirect.
- Clone chưa Apply/Discard được giữ nguyên, không tự dọn. Lệnh `/status` liệt kê các clone đang chờ.
- Thẻ Apply/Discard dùng `Align_Agent_Pending_Store` TTL 900s. Hết hạn thì gõ `/status` để bot gửi lại thẻ cho clone đang chờ.
- Pending TTL giữ 900s.
- UI string, log và comment code viết tiếng Anh theo `AGENTS.md` §2.3.

## Câu hỏi chặn

Không có câu hỏi chặn.
