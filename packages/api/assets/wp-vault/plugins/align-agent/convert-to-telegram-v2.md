# Kế Hoạch Triển Khai — Convert to Telegram v2

> Kế thừa `convert-to-telegram-v1.md` (đã triển khai runner, approval, formatter, lệnh). v2 sửa lỗi timeout khi sinh trang lớn và cho Telegram nhận ảnh làm content/logo.

## Bối Cảnh & Bằng Chứng

### Endpoint AI đang dùng

- Option `align_agent_ai_endpoint` = `https://openrouter.ai/api/v1/chat/completions`. Model `deepseek/deepseek-v4.1-flash-1017`.
- `https://api.lsia.site/v1/chat/completions` chỉ là `DEFAULT_ENDPOINT` fallback (`includes/class-gateway-ai.php:28`). Hệ thống **không** dùng giá trị này.
- Kết luận: request đi qua OpenRouter. OpenRouter hỗ trợ SSE streaming, gồm cả delta `tool_calls`.

### Lỗi 1: Timeout khi sinh landing page (`logs/php/error.log`, 20-Oct-2026 03:52–03:55 UTC)

| Step | `llm_ms` | Tool calls | Ghi chú |
|---|---|---|---|
| 1 | 12671 | 3 | `list_pages` |
| 2 | 2696 | 3 | Đọc header/footer |
| 3 | 19228 | 1 | Content 0 byte, chỉ tool args |
| 4 | 41236 | 1 | Tool args lớn dần |
| 5 | 16529 | 2 | |
| 6 | **90011** | 0 | `cURL error 28 ... 90002 milliseconds with 469 bytes received` |

- Step 6 sinh toàn bộ HTML/CSS trong 1 tool call, vượt `'timeout' => 90` cố định (`includes/class-agent-loop.php:151`).
- `chat_completion()` gọi `wp_remote_post` không stream (`includes/class-gateway-ai.php:186-199`), nên phải chờ toàn bộ output.
- Runner đã ACK sớm qua `fastcgi_finish_request()` (`includes/class-gateway-telegram-runner.php:114`). Telegram không phải nguyên nhân.
- Closure `shutdown` không gọi `set_time_limit(0)` / `ignore_user_abort(true)`.

### Lỗi 2: Ảnh gửi qua Telegram bị bỏ qua

- Webhook chỉ đọc `message.text`. Webhook trả `200` và thoát khi `text` rỗng (`includes/class-gateway-webhook.php:245-249`).
- Update `photo` / `document` / `caption` không được xử lý. AI không nhận được ảnh.
- Telegram gửi mỗi ảnh thành 1 update riêng. Ảnh có thể tới **sau** tin nhắn text (đúng như phiên lỗi lúc 10:52).

### Lỗi phụ

- Loopback runner dùng `sanitize_text_field()` cho `text` (`includes/class-gateway-telegram-runner.php:94`). Hàm này xóa xuống dòng, nên prompt nhiều dòng bị gộp thành 1 dòng.

## Hiểu Yêu Cầu & Ranh Giới

- **Mục tiêu**: Telegram tạo được landing page lớn không bị timeout, và dùng ảnh user gửi làm content/logo.
- **Nghiệm thu**:
  - Prompt "tạo landing page có header + footer + giới thiệu gói subscription" chạy xong, không có `cURL error 28`.
  - Log có `stream` metrics (`ttfb_ms`, `bytes`, `chunks`) cho mỗi step.
  - Ảnh gửi qua Telegram (photo, file, có hoặc không có caption) vào Media Library, và AI nhận `[Attached Media: attachment_id=..., url=..., file=...]`.
  - Chatbox vẫn chạy như cũ (fallback non-stream vẫn hoạt động).
- **Non-goals**:
  - Stream token trực tiếp về Telegram (`editMessageText` liên tục).
  - Async job + polling cho Chatbox.
  - Nhận video, audio, sticker, SVG.
  - Đổi provider/model.
- **File ảnh hưởng**:
  - Tạo mới: `includes/class-gateway-ai-stream.php`, `includes/class-gateway-telegram-media.php`
  - Sửa: `includes/class-gateway-ai.php`, `includes/class-agent-loop.php`, `includes/class-gateway-telegram-runner.php`, `includes/class-gateway-webhook.php`, `includes/class-agent-conversation.php`, `align-agent.php`, `AGENTS.md`, `README.md`, `docs/BUG_LOGS.md`

## Đánh Giá Phương Án

### Điểm yếu (Hạn chế / Rủi ro)

- Gộp delta `tool_calls` theo `index` phức tạp. Lỗi gộp làm JSON arguments hỏng.
- OpenRouter gửi comment keep-alive `: OPENROUTER PROCESSING`. Idle timeout không bắt được trường hợp provider treo nhưng vẫn gửi keep-alive, nên cần thêm trần thời gian tổng.
- `request_terminate_timeout` của PHP-FPM trên shared host vẫn có thể cắt runner chạy nền.
- URL file Telegram chứa bot token (`https://api.telegram.org/file/bot<TOKEN>/...`). Lộ URL này vào log hoặc LLM là lộ token.
- `includes/class-gateway-ai.php` 452 dòng, `includes/tools/media/class-agent-tools-media.php` 498 dòng. Không thể thêm code vào 2 file này.
- Upload ảnh từ Telegram ghi vào Media mà không qua thẻ duyệt.

### Điểm mạnh (Ưu điểm / Khả thi)

- OpenRouter hỗ trợ stream chuẩn OpenAI, có sẵn trên endpoint hiện tại.
- Interface `chat_completion()` giữ nguyên. Caller không phải sửa.
- Chatbox đã có format `[Attached Media: attachment_id=..., url=..., file=...]` (`assets/chatbox-upload.js:244`). Telegram dùng lại format này, nên tool `set_featured_image`, `attach_media` chạy được ngay.
- Kênh hội thoại đã tách (`Align_Agent_Conversation::set_channel`). Hàng đợi ảnh lưu theo kênh, không ảnh hưởng Chatbox.

### Giải pháp đề xuất & Lý do

- *Giải pháp:* Tách stream ra class mới `Align_Gateway_AI_Stream`, dùng `curl_*` với `CURLOPT_WRITEFUNCTION`, `CURLOPT_LOW_SPEED_LIMIT=1`, `CURLOPT_LOW_SPEED_TIME=45` và trần `CURLOPT_TIMEOUT` -> *Lý do:* `wp_remote_post` không hỗ trợ callback từng chunk. Tách file để giữ giới hạn < 500 dòng.
- *Giải pháp:* Đặt timeout theo kênh: `telegram` = 300s tổng, `chatbox` = 90s -> *Lý do:* Telegram chạy nền sau ACK. Chatbox còn giữ kết nối browser, nên giữ 90s.
- *Giải pháp:* Tải file Telegram phía server, sideload trực tiếp bằng `media_handle_sideload`. Chỉ đưa `attachment_id` + URL Media nội bộ cho LLM -> *Lý do:* Token không rời server. Không cần đi qua tool `sideload_media`.
- *Giải pháp:* Ảnh không có caption vào hàng đợi `pending_attachments` của phiên Telegram. Tin nhắn text kế tiếp lấy hết ảnh trong hàng đợi -> *Lý do:* Telegram gửi ảnh thành update riêng, giống cơ chế chip đính kèm của Chatbox.

## Mốc Thực Thi

### Mốc 1: Streaming + idle timeout (REQ-01, REQ-02, REQ-03)

- **File**:
  - `includes/class-gateway-ai-stream.php` (mới) — `Align_Gateway_AI_Stream::request( string $endpoint, string $api_key, array $payload, array $limits ): array`:
    - Gửi `stream: true`, header `Accept: text/event-stream`.
    - Parse SSE theo dòng. Bỏ qua dòng comment (`:`). Dừng ở `data: [DONE]`.
    - Gộp `delta.content`.
    - Gộp `delta.tool_calls` theo `index` (`id`, `function.name`, nối `function.arguments`).
    - Lấy `finish_reason`. Bắt `{"error":...}` gửi giữa stream.
    - Nếu `Content-Type` không phải `text/event-stream`, parse body như JSON thường.
    - Log `[Align Agent] AI stream | ttfb_ms | total_ms | bytes | chunks | tool_args_bytes`.
    - Trả cùng shape với `chat_completion()`.
  - `includes/class-gateway-ai.php`:
    - `chat_completion()` thêm option `stream` (bool) và `idle_timeout` (int).
    - Khi `stream` bật và có `curl_init`, gọi `Align_Gateway_AI_Stream::request()`. Ngược lại giữ `wp_remote_post` cũ.
  - `includes/class-agent-loop.php` (dòng 145-152):
    - Truyền `'stream' => true`, `'idle_timeout' => 45`.
    - `'timeout'` lấy theo kênh: `Align_Agent_Conversation::get_channel() === 'telegram' ? 300 : 90`.
  - `includes/class-gateway-telegram-runner.php`:
    - Đầu closure `shutdown` gọi `ignore_user_abort( true )` và `set_time_limit( 0 )`, kèm `function_exists` guard.
    - Sửa `handle_loopback_run`: dùng `sanitize_textarea_field()` thay `sanitize_text_field()`.
  - `includes/class-agent-conversation.php`: thêm `get_channel()` nếu chưa có.
  - `align-agent.php`: require `class-gateway-ai-stream.php`.
- **Kiểm chứng**:
  ```bash
  for f in includes/*.php; do php -l "$f"; done
  ```
  ```bash
  S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --path=../../.. --exec="define('DB_HOST','localhost:$S');" eval '$r = Align_Gateway_AI::chat_completion( array( array( "role" => "user", "content" => "Write a 1500-word HTML landing page." ) ), array( "stream" => true, "idle_timeout" => 45, "timeout" => 300 ) ); echo $r["success"] ? "OK " . strlen( $r["content"] ) : "FAIL " . $r["message"];'
  ```
  ```bash
  grep 'AI stream' ../../../../logs/php/error.log | tail -3
  ```
  - Gửi lại qua Telegram đúng prompt landing page đã lỗi.
  - Chatbox: gửi 1 câu hỏi + 1 thao tác đọc tool qua `/joe_verify`.
- **Kỳ vọng**:
  - `OK` kèm độ dài > 5000 ký tự. Log có `ttfb_ms` và `chunks` > 1.
  - Telegram tạo landing page tới bước thẻ duyệt, không có `cURL error 28`.
  - Chatbox trả lời bình thường, tool call chạy đúng.
  - `wc -l includes/class-gateway-ai.php` < 500.

### Mốc 2: Giảm output mỗi step (REQ-04)

- **File**: `includes/class-agent-loop.php` — `build_system_prompt()` thêm quy tắc 9:
  - Với trang có từ 3 section trở lên: gọi `save_page` với khung trang + section đầu tiên.
  - Sau đó thêm từng section còn lại bằng `patch_content`, chọn `find` là marker cuối đã có, ví dụ `<!-- /section:hero -->`.
  - Mỗi tool call chứa tối đa khoảng 1 section HTML/CSS.
- **Kiểm chứng**:
  - Gửi prompt landing page qua Telegram.
  - `grep 'Chat step' ../../../../logs/php/error.log | tail -15`
- **Kỳ vọng**:
  - Có nhiều step ghi nhỏ thay vì 1 step lớn.
  - Mỗi `llm_ms` < 60000.
  - Trang cuối có đủ header, các section, footer.

### Mốc 3: Nhận ảnh từ Telegram (REQ-05, REQ-06, REQ-07)

- **File**:
  - `includes/class-gateway-telegram-media.php` (mới) — `Align_Gateway_Telegram_Media`:
    - `extract( array $message ): array` lấy `photo` (chọn size lớn nhất ≤ 5 MB) và `document` (chỉ `image/jpeg|png|webp|gif`).
    - `ingest( string $file_id, string $filename ): array|WP_Error`:
      - Gọi `getFile`, tải về `download_url()` (timeout 30s).
      - Kiểm `<= 5 MB`, đuôi `jpg|jpeg|png|webp|gif`, kiểm MIME thực bằng `wp_check_filetype_and_ext`. Cấm SVG.
      - Gọi `media_handle_sideload()` dưới bound admin.
      - Xóa file tạm. Trả `{ id, url, filename }`.
      - Log chỉ ghi `file_id` và `attachment_id`, **không** ghi URL chứa token.
    - `queue( int $user_id, array $att )` / `consume( int $user_id ): string` — hàng đợi `pending_attachments` trong phiên kênh `telegram` (TTL 30 phút, tối đa 10 ảnh). `consume()` trả các dòng `[Attached Media: attachment_id=..., url=..., file=...]`.
  - `includes/class-gateway-webhook.php`:
    - Nhận message có `photo` / `document`, kể cả khi `text` rỗng.
    - Dùng `caption` làm `text` khi có caption.
    - Kiểm chat ID như cũ, sau đó chuyển sang runner.
  - `includes/class-gateway-telegram-runner.php`:
    - Ảnh **không** caption: ingest, queue, rồi trả lời `Saved image #<id> (<n> queued). Send your instruction to use it.`. Không gọi LLM. Không cần lock runner.
    - Ảnh **có** caption, hoặc text khi hàng đợi không rỗng: ingest (nếu có ảnh), gắn `consume()` vào cuối text, rồi chạy `run_step`.
    - Ảnh tới khi runner đang bận: vẫn ingest + queue, báo ảnh sẽ dùng cho lệnh kế tiếp.
    - Lệnh `/status`: hiển thị thêm số ảnh trong hàng đợi.
- **Kiểm chứng**:
  - `php -l` các file sửa.
  - Telegram: gửi 2 ảnh (1 photo, 1 file PNG logo), sau đó gửi text "tạo landing page dùng các ảnh trên, logo là file PNG".
  - Gửi 1 file `.svg` và 1 ảnh > 5 MB.
  - `S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"; wp --path=../../.. --exec="define('DB_HOST','localhost:$S');" post list --post_type=attachment --posts_per_page=3 --fields=ID,post_title,post_mime_type`
  - `grep -c 'api.telegram.org/file/bot' ../../../../logs/php/error.log`
- **Kỳ vọng**:
  - 2 attachment mới trong Media. Bot trả `Saved image #...` cho từng ảnh.
  - Tin nhắn user trong phiên Telegram chứa 2 dòng `[Attached Media: ...]`. HTML trang dùng đúng URL Media.
  - SVG và ảnh > 5 MB bị từ chối, có thông báo lỗi rõ ràng.
  - Log không chứa URL file kèm token (`grep -c` trả `0`).

### Mốc 4: Tài liệu (REQ-08)

- **File**:
  - `AGENTS.md` §3: mục Streaming (`Align_Gateway_AI_Stream`, idle timeout, timeout theo kênh), Telegram Media Intake (hàng đợi, giới hạn, bảo vệ token).
  - `README.md`: hướng dẫn gửi ảnh qua Telegram (gửi logo dạng File để giữ nền trong suốt).
  - `docs/BUG_LOGS.md`: ghi lỗi timeout 90s khi sinh trang lớn, lỗi ảnh Telegram bị bỏ qua, lỗi `sanitize_text_field` xóa xuống dòng.
- **Kiểm chứng**: `git diff --stat` + `grep -n "Stream\|Media Intake" AGENTS.md`
- **Kỳ vọng**: Tài liệu khớp code.

**Nghiệm thu cuối**: Gọi Team QC (Security, Logic, UI/UX, Performance) chạy song song, sau đó `/joe_verify` trên Chatbox và chạy lại kịch bản Telegram lúc 10:52.

## Giả định đã chọn

- Stream bật mặc định. Tự fallback sang non-stream khi thiếu `curl_init` hoặc provider trả JSON thường.
- Idle timeout 45s, trần tổng 300s cho Telegram, 90s cho Chatbox.
- Ảnh user gửi qua Telegram upload thẳng vào Media, không qua thẻ duyệt. Lý do: giống hành vi upload thủ công của Chatbox (M1). Thao tác dùng ảnh (`set_featured_image`, ghi trang) vẫn phải duyệt.
- Media group (album) xử lý từng ảnh như ảnh riêng. Caption của album chỉ nằm ở ảnh đầu.
- Hàng đợi ảnh hết hạn sau 30 phút, tối đa 10 ảnh. Lệnh `/new` xóa hàng đợi.
- Photo Telegram bị nén. README khuyên gửi logo dạng File.

## Câu hỏi chặn

Không có câu hỏi chặn.
