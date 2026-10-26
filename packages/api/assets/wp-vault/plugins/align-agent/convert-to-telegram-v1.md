# Kế Hoạch Triển Khai — Convert to Telegram v1

> Telegram dùng chung engine Chatbox (`Align_Agent_Loop`), chạy dưới 1 admin cố định, phiên hội thoại riêng, mọi thao tác ghi bắt buộc duyệt qua inline keyboard.

## Quyết Định Đã Chốt

1. Telegram thao tác dưới quyền **1 Administrator cố định**, chọn trong Settings.
2. Phiên hội thoại Telegram **tách riêng** khỏi Chatbox.
3. **Gỡ engine cũ** `CREATE_PAGE` / `UPDATE_PAGE`. Mọi thao tác ghi bắt buộc duyệt.

## Bối Cảnh & Nguyên Nhân

- Lỗi `cURL error 28: Operation timed out after 90001 milliseconds` do `timeout => 90` tại `includes/class-agent-loop.php:151`. Request LLM không stream nên chờ toàn bộ response.
- Webhook gọi `process_message()` đồng bộ trước khi trả `200` (`includes/class-gateway-webhook.php:239`). Telegram gửi lại update khi phản hồi chậm.
- Luồng webhook không chống trùng `update_id`. Chỉ luồng polling có chống trùng (`includes/class-gateway-agent.php:411-454`).

### Khoảng cách Telegram và Chatbox

| Hạng mục | Chatbox (`Align_Agent_Loop`) | Telegram (`Align_Gateway_Agent::process_message`) |
|---|---|---|
| Engine | Tool calling, nhiều bước | Gọi LLM 1 lần, AI trả JSON |
| Thao tác | Toàn bộ tool registry | Chỉ `CREATE_PAGE`, `UPDATE_PAGE`, `REPLY` |
| Duyệt ghi | `needs_approval` | Không duyệt, ghi thẳng và `publish` |
| Validator | Có chặn | Gọi `validate_markup()` nhưng bỏ qua kết quả (`class-gateway-agent.php:316`, `:357`) |
| User WP | `get_current_user_id()` | User `0`, `current_user_can()` luôn sai |
| Lịch sử | `Align_Agent_Conversation` (user meta) | Không lưu |
| Nút bấm | Thẻ duyệt UI | `allowed_updates` chỉ có `message`, `edited_message` (`class-gateway-webhook.php:100`) |

**Rủi ro bảo mật hiện tại:** Telegram cho AI ghi và publish trang không qua duyệt. Luồng này vi phạm `AGENTS.md` §1 và §4.3.

## Hiểu Yêu Cầu & Ranh Giới

- **Mục tiêu**: Telegram chạy chung engine `Align_Agent_Loop`, dưới 1 admin cố định, phiên riêng, mọi thao tác ghi duyệt bằng nút inline.
- **Nghiệm thu**:
  - Webhook trả `200` dưới 1s và không xử lý trùng `update_id`.
  - Tool đọc chạy end-to-end qua Telegram.
  - Tool ghi chỉ thực thi sau khi bấm `✅ Approve`.
  - Engine cũ `CREATE_PAGE` / `UPDATE_PAGE` không còn.
- **Non-goals**:
  - Gửi ảnh từ Telegram vào Media (phase 2).
  - Streaming LLM và async polling cho Chatbox.
  - Nhiều Chat ID / nhiều user.
- **File ảnh hưởng**:
  - Sửa: `includes/class-gateway-webhook.php`, `includes/class-gateway-agent.php`, `includes/class-gateway-telegram.php`, `includes/class-agent-conversation.php`, `includes/class-agent-chat-controller.php`, `views/settings-page.php`, `align-agent.php`, `AGENTS.md`, `README.md`, `docs/BUG_LOGS.md`
  - Tạo mới: `includes/class-gateway-telegram-runner.php`, `includes/class-agent-approval-service.php`, `includes/class-gateway-telegram-approval.php`, `includes/class-gateway-telegram-format.php`, `views/tab-telegram.php`

## Đánh Giá Phương Án

### Điểm yếu (Hạn chế / Rủi ro)

- Runner lặp tối đa 12 step, mỗi step timeout 90s. `request_terminate_timeout` của PHP-FPM có thể cắt giữa chừng.
- Apache `mod_php` không có `fastcgi_finish_request()`, nên phải dùng loopback. Một số host chặn loopback.
- Cron poll (`align-agent.php:115`) cũng gọi engine cũ. Phải chuyển luồng này sang runner.
- `views/settings-page.php` đã 485 dòng. Thêm field sẽ vượt giới hạn 500 dòng.
- `callback_data` của Telegram giới hạn 64 byte.

### Điểm mạnh (Ưu điểm / Khả thi)

- Dùng lại `run_step`, `Align_Agent_Tool_Registry`, `Align_Agent_Audit_Log`, `pop_pending_write`.
- `Align_Agent_Conversation` dùng `self::META_KEY` tập trung. Đổi sang key theo kênh chỉ là thay đổi nhỏ.
- Bỏ engine cũ giúp `class-gateway-agent.php` giảm từ 479 dòng xuống khoảng 150 dòng.

### Giải pháp đề xuất & Lý do

- *Giải pháp:* Tách phiên bằng `Align_Agent_Conversation::set_channel( 'telegram' )`, dùng meta key `align_agent_conversation_telegram` trong phạm vi request -> *Lý do:* Không đổi signature của hơn 10 hàm. Chatbox không bị ảnh hưởng.
- *Giải pháp:* Trả ACK trong hook `shutdown`: gọi `fastcgi_finish_request()` / `litespeed_finish_request()`, sau đó chạy runner. Fallback là loopback ký HMAC bằng secret key -> *Lý do:* REST trả `200` trước, xử lý sau, không cần cron.
- *Giải pháp:* Tách Telegram panel ra `views/tab-telegram.php`, theo mẫu `views/tab-seo.php` -> *Lý do:* Giữ đúng giới hạn < 500 dòng và quy chuẩn tab.

## Mốc Thực Thi

### Mốc 1: Tracer — Webhook an toàn + Runner dùng engine mới

REQ-01 chống trùng, REQ-02 ACK sớm, REQ-03 runner, REQ-04 user admin, REQ-05 tách phiên, REQ-06 gỡ engine cũ.

- **File**:
  - `includes/class-gateway-webhook.php`:
    - Chống trùng `update_id` bằng transient `align_agent_tg_upd_{id}` (TTL 1h).
    - Lock `align_agent_tg_lock` để chỉ 1 runner chạy mỗi lúc.
    - Lên lịch xử lý ở `shutdown`, trả `200` ngay.
  - `includes/class-gateway-telegram-runner.php` (mới):
    - `wp_set_current_user( bound_admin )`, sau đó `Align_Agent_Conversation::set_channel( 'telegram' )`.
    - Lặp `Align_Agent_Loop::run_step()` cho tới khi status khác `continue`.
    - Gửi `reply` đã escape.
    - Khi gặp `needs_approval`, tạm báo "pending approval".
    - Endpoint loopback `POST /align-agent/v1/telegram/run`, xác thực bằng HMAC.
  - `includes/class-agent-conversation.php`: thêm `set_channel()` / `get_meta_key()`.
  - `includes/class-gateway-agent.php`:
    - Xóa logic cũ của `process_message`, cùng `find_target_page`, `build_system_prompt`, `get_page_full_context`.
    - `process_message` và `sync_and_process_latest` chỉ chuyển tiếp sang runner.
  - `includes/class-gateway-telegram.php`: thêm option `align_agent_telegram_user_id`. Sanitize kiểm user có `manage_options`.
  - `views/tab-telegram.php` (mới) + `views/settings-page.php`: tách panel, thêm dropdown chọn Administrator.
  - `align-agent.php`: require file mới.
- **Kiểm chứng**:
  ```bash
  for f in includes/*.php views/*.php; do php -l "$f"; done
  ```
  ```bash
  curl -s -o /dev/null -w '%{http_code} %{time_total}\n' -X POST http://aignrealbuilder.local/wp-json/align-agent/v1/telegram/webhook -H 'Content-Type: application/json' -H "X-Telegram-Bot-Api-Secret-Token: $SECRET" -d '{"update_id":900001,"message":{"chat":{"id":<CHAT_ID>},"from":{"first_name":"QA"},"text":"List 3 recent pages"}}'
  ```
  (gửi 2 lần)
  ```bash
  grep '\[Align Agent\]' ../../debug.log | tail -20
  ```
  ```bash
  S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --exec="define('DB_HOST','localhost:$S');" eval 'var_dump( get_user_meta( <ADMIN_ID>, "align_agent_conversation_telegram", true ) !== "" );'
  ```
- **Kỳ vọng**:
  - `200` với `time_total` < 1s ở cả 2 lần gửi.
  - Log có `Chat step` đúng 1 lần, kèm dòng `duplicate update skipped`.
  - Meta phiên Telegram tồn tại. Meta phiên Chatbox không đổi.
  - `wc -l` mọi file < 500 dòng.

### Mốc 2: Duyệt thao tác ghi qua inline keyboard

REQ-07 Approval service, REQ-08 callback_query, REQ-09 danger.

- **File**:
  - `includes/class-agent-approval-service.php` (mới): chuyển logic từ `handle_approve()` (`includes/class-agent-chat-controller.php:296-362`) vào `decide( $user_id, $pending_id, $decision ): array`. Gồm pop, execute, audit log, add_tool_message.
  - `includes/class-agent-chat-controller.php`: `handle_approve()` gọi service. Response JSON giữ nguyên.
  - `includes/class-gateway-telegram-approval.php` (mới):
    - Gửi thẻ gồm summary, `reply_markup` `✅ Approve` / `❌ Cancel`.
    - `callback_data` = `a|{pending_id}` / `c|{pending_id}` (≤ 64 byte).
    - Tool `danger` thêm dòng cảnh báo ⚠️.
    - Xử lý `callback_query`: kiểm `from.id` / `chat.id` khớp Chat ID cấu hình.
    - Gọi `answerCallbackQuery`, gỡ nút bằng `editMessageReplyMarkup`, rồi chạy tiếp runner.
  - `includes/class-gateway-webhook.php`: `allowed_updates` thêm `callback_query`. Route update sang approval handler.
- **Kiểm chứng**:
  - `php -l` các file sửa.
  - Chatbox: duyệt 1 thao tác ghi trên `?page=align-agent` qua `/joe_verify`.
  - Telegram: curl giả lập `callback_query` với `pending_id` có sẵn (lấy qua `wp eval` đọc `pending_writes`).
  - Gửi lại callback lần 2.
- **Kỳ vọng**:
  - Chatbox duyệt vẫn chạy. Audit Log có bản ghi mới.
  - Callback lần 1 thực thi tool. Callback lần 2 trả lỗi `not found or expired`.
  - Callback từ Chat ID lạ bị từ chối và có log.

### Mốc 3: Formatter + lệnh + typing

REQ-10 format/4096, REQ-11 `/new` `/status` `/help`.

- **File**:
  - `includes/class-gateway-telegram-format.php` (mới):
    - Chuyển Markdown/HTML sang tập con Telegram (`b i u s code pre a`).
    - Escape phần còn lại.
    - Chia chunk ≤ 4096 ký tự, không cắt giữa thẻ.
    - Fallback sang plain text khi `send_message` lỗi.
  - `includes/class-gateway-telegram-runner.php`:
    - Lệnh `/new` gọi `delete_session` của kênh Telegram.
    - Lệnh `/status` liệt kê `pending_writes`.
    - Lệnh `/help` liệt kê các lệnh.
    - Gửi `sendChatAction` `typing` trước mỗi step.
- **Kiểm chứng**:
  - `wp eval` gọi formatter với Markdown 10.000 ký tự có code block, in số chunk và độ dài mỗi chunk.
  - curl webhook với `/new`, `/status`, `/help`.
- **Kỳ vọng**:
  - 3 chunk, mỗi chunk ≤ 4096 ký tự, thẻ đóng/mở cân bằng.
  - `/new` reset phiên Telegram, không đụng phiên Chatbox.

### Mốc 4: Tài liệu

REQ-12.

- **File**:
  - `AGENTS.md` §3: mục Telegram Runner, Approval, Channel session.
  - `README.md`: hướng dẫn cấu hình.
  - `docs/BUG_LOGS.md`: ghi lỗi bảo mật "Telegram ghi/publish không duyệt, bỏ qua kết quả validator" và lỗi xử lý trùng do không chống trùng `update_id`.
- **Kiểm chứng**: `git diff --stat` + `grep -n "Telegram" AGENTS.md`
- **Kỳ vọng**: Tài liệu khớp code. Không còn nhắc `CREATE_PAGE` / `UPDATE_PAGE`.

**Nghiệm thu cuối**: Gọi Team QC (Security, Logic, UI/UX, Performance) chạy song song, sau đó `/joe_verify` trên Settings và Chatbox.

## Giả định đã chọn

- Cron poll khi không có webhook -> chuyển sang runner, giữ cơ chế `align_agent_last_update_id`.
- Bound admin chưa cấu hình -> runner từ chối, gửi tin hướng dẫn mở Settings.
- Runner đang chạy mà có tin mới -> trả "Still processing previous request", không xếp hàng.
- Hạn duyệt giữ `PENDING_TTL_SECONDS = 900` như Chatbox.
- `edited_message` -> bỏ qua để tránh chạy lại lệnh.
- Sau khi deploy, user bấm "Register Webhook" lại để cập nhật `allowed_updates`. Hướng dẫn ghi trong README.

## Câu hỏi chặn

Không có câu hỏi chặn.
