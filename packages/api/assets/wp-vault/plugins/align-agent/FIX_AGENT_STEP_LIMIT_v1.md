# FIX v1 - Agent hết số bước giữa chừng khi sửa HTML

- **Version**: v1
- **Ngày**: 2026-10-24
- **Loại**: Sửa lỗi
- **Plan trước**: Không có
- **Trạng thái**: HOÀN TẤT (DONE)
- **Repo**: plugin `Align-Agent-main` (root của file này)

## 1. Yêu Cầu Gốc Của User (Nguyên Văn)
> vậy là 12 step không đủ nếu cần edit html nhỉ ?

> vậy thì tăng lên 20 tool call đi

> tạo file markdown ở root cho worker làm nhé

> ê cái lỗi trên là ví dụ chứ không phải kêu mày lập plan fix riêng cho nó ?

## 2. Hiểu Yêu Cầu (STE)
- **Vấn đề**: Một yêu cầu sửa HTML cần nhiều bước: tìm trang, đọc nội dung, sửa, kiểm tra, trả lời. Agent hết số bước khi việc còn dở và dừng lại.
- **Ví dụ thực tế (chỉ để minh họa, KHÔNG sửa trong plan này)**: Lệnh sửa nút chuyển ngôn ngữ lúc 11:23. Agent đã ghi `header` lúc 11:26:32, rồi trả `Vượt quá số bước xử lý cho phép.` Nó không báo việc đã làm và không làm nốt phần còn lại.
- **Mục tiêu**: Agent có đủ bước cho một lần sửa HTML thông thường. Khi vẫn chạm giới hạn, agent báo tiến độ và cho người dùng bấm tiếp tục.
- **Phạm vi**: Giới hạn lượt, xử lý khi chạm giới hạn, giảm số bước cần cho một lần sửa HTML. Áp dụng cho Telegram runner và Chatbox.
- **Nghiệm thu**: Lệnh sửa HTML nhiều vị trí chạy xong trong giới hạn. Khi chạm giới hạn, người dùng thấy tóm tắt tiến độ và nút Continue.

## 3. Danh Sách Yêu Cầu (REQ-ID)
| REQ-ID | Mô tả | Trạng thái |
|---|---|---|
| REQ-01 | Giới hạn lượt tự động là 20, đồng bộ PHP + JS. Không còn chuỗi/comment nào ghi số cũ (12, 6). | HOÀN TẤT |
| REQ-02 | Khi chạm giới hạn, kết quả trả về có cờ `limit_reached: true`. Tin trả lời liệt kê việc đã làm (từ `Align_Agent_Run_Report`) và nói rõ việc chưa xong. | HOÀN TẤT |
| REQ-03 | Telegram: khi chạm giới hạn, gửi kèm nút `▶️ Continue`. Bấm nút thì agent chạy tiếp từ ngữ cảnh cũ thêm 20 lượt. | HOÀN TẤT |
| REQ-04 | Chatbox: khi chạm giới hạn, hiện nút `Continue` trong bong bóng lỗi. Bấm nút thì chạy tiếp thêm 20 lượt. | HOÀN TẤT |
| REQ-05 | System prompt hướng dẫn agent sửa HTML ít bước: gom mọi sửa đổi vào 1 lần `patch_content` (tối đa 10 edits), gọi tool đọc song song, không đọc lại toàn trang sau mỗi lần sửa. | HOÀN TẤT |
| REQ-06 | MCP gateway ghi đúng kênh `mcp` cho lệnh ghi, không ghi cứng `telegram`. | HOÀN TẤT |

## 4. Thay Đổi So Với v0
Không có. (`FIX_LANG_SWITCHER_v1.md` là plan lập sai phạm vi. Bỏ qua file đó.)

## 5. Context Đã Đọc

### Giới hạn lượt
- `includes/class-agent-loop.php:21`: `const MAX_CONTINUE_TURNS = 20;` (đã đổi từ 12, chưa commit).
- `includes/class-agent-loop.php:6`: comment cũ còn ghi `enforces the 6-turn limit`.
- `includes/class-agent-loop.php:153-167`: nhánh chạm giới hạn. Trả `status => 'done'` và `reply => $limit_note` (tiếng Anh). Không có cờ phân biệt với trả lời bình thường.
- `includes/class-agent-loop.php:126-139`: `run_step()`. Có `message` thì thêm user message và đặt `$turn = 0`. `message` rỗng thì đọc lại state cũ và giữ `$turn` do caller truyền vào.
- `assets/chatbox.js:11`: `var MAX_CONTINUE_TURNS = 20;` (đã đổi, chưa commit).
- `assets/chatbox.js:187-192`: `runStepLoop()`. Chạm giới hạn thì `appendMessage('error', i18n.maxTurnsError || 'Maximum automatic tool turns (12) reached.')`. Chuỗi fallback còn ghi `12`.
- `includes/class-gateway-admin.php:111`: `maxTurnsError` đã đổi sang `sprintf( ..., Align_Agent_Loop::MAX_CONTINUE_TURNS )` (chưa commit).
- Tài liệu đã đổi sang 20 (chưa commit): `AGENTS.md:103`, `README.md:158`, `browser-test-align-agent-v3.md:165`.

### Telegram runner
- `includes/class-gateway-telegram-runner.php:381-397`: lượt đầu `run_step( $uid, $text, 0 )`, rồi vòng `while ( 'continue' === status && $turn_count < MAX_CONTINUE_TURNS )`. Sau mỗi lượt gọi `Align_Gateway_Telegram_Lock::refresh()`.
- `includes/class-gateway-telegram-runner.php:411-447`: gửi Run Report (kèm nút Undo `u|{cs_id}`) rồi gửi `reply`.
- `includes/class-gateway-telegram-runner.php:349-353`: lấy lock. Lock bận thì báo bận và dừng.
- `includes/class-gateway-telegram-approval.php:285-330`: `handle_callback_query()` tách `callback_data` theo `type|target_id`. `u`/`U` chuyển sang `Align_Gateway_Telegram_Undo::handle_callback()`. Các type khác lấy lock 900s rồi xử lý approve/cancel.
- `includes/class-gateway-telegram-approval.php:451`: luồng sau khi Approve cũng có vòng `while ... MAX_CONTINUE_TURNS`.
- Số dòng hiện tại: `class-gateway-telegram-runner.php` 491, `class-gateway-telegram-approval.php` 466, `assets/chatbox.js` 497, `class-agent-loop.php` 429. Quy định file < 500 dòng (`AGENTS.md` mục 2.1). Code mới PHẢI tách ra file riêng.

### Hiệu quả tool khi sửa HTML
- `includes/tools/content/class-agent-tools-patch.php:30-68`: `patch_content` đã nhận 1–10 edits `{field, find, replace}` trong 1 lần gọi, áp dụng cho `page`, `post`, `template`, `reuse_block`.
- `includes/class-agent-loop.php:35-115`: `build_system_prompt()`. Dòng 64-65 có quy tắc về `patch_content`. Chưa có quy tắc gom edits hoặc gọi tool song song.

### MCP gateway
- `includes/class-gateway-mcp.php:285`: `Align_Agent_Write_Executor::run( $target_tool_name, $args, get_current_user_id(), 'telegram' );`. Lệnh ghi qua MCP bị gắn nhãn `telegram` trong change set và log.

### Quy định bắt buộc (`AGENTS.md`)
- Log PHP prefix `[Align Agent]`, log JS prefix `[Align UI]`. Chuỗi UI, comment, log viết tiếng Anh.
- Callback Telegram phải trả 200 ACK ngay và xử lý nặng qua `shutdown`. Chống chạy song song bằng `Align_Gateway_Telegram_Lock`.
- Chỉ Chat ID đã ghép nối mới được bấm nút inline.
- Chatbox: mọi button cao 28px theo `.align-agent-chatbox .button`.

## 6. Giả Định Đã Chọn
- Giữ 20 làm giới hạn mỗi đợt -> User đã chốt "tăng lên 20".
- Continue chạy thêm 1 đợt 20 lượt mới, không giới hạn số lần bấm -> Người dùng quyết định khi nào dừng. Mỗi lần bấm là 1 hành động có chủ đích.
- `callback_data` của nút Continue là `k|{conversation_id}` -> Cùng định dạng `type|id` của router hiện có (`class-gateway-telegram-approval.php:301`). Tránh trùng `a`, `c`, `A`, `C`, `u`, `U`.
- Bấm Continue khi `conversation_id` khác phiên hiện tại (đã `/new` hoặc có lệnh mới) -> từ chối với `Session changed. Send a new instruction.` -> Tránh chạy tiếp nhầm ngữ cảnh.
- Tin chạm giới hạn vẫn bằng tiếng Anh -> Theo quy định UI strings tiếng Anh (`AGENTS.md` mục 2.3).
- Code Continue của Telegram đặt trong file mới `includes/class-gateway-telegram-continue.php` (class `Align_Gateway_Telegram_Continue`) -> Runner (491 dòng) và Approval (466 dòng) gần chạm 500.
- Code Continue của Chatbox đặt trong file mới `assets/chatbox-continue.js` -> `chatbox.js` đã 497 dòng.

## 7. Phạm Vi Loại Trừ (Worker KHÔNG Được Làm)
- Không sửa nút chuyển ngôn ngữ, header, footer hay bất kỳ nội dung site nào. Đó chỉ là ví dụ.
- Không sửa agent ngoài (bot "Workflow" gọi qua MCP). Giới hạn bước của agent đó cấu hình ở phía nó.
- Không đổi schema tool, không thêm tool mới.
- Không đổi luồng approve/cancel/undo hiện có, ngoài việc thêm nhánh `k`.
- Không tăng `LOCK_TTL` hay timeout stream.

## 8. Đánh Giá Phương Án
### Điểm yếu (Hạn chế / Rủi ro)
- 20 lượt × tối đa 300s stream mỗi lượt có thể vượt TTL lock 900s. Lock được gia hạn sau mỗi lượt nên không hết hạn giữa chừng, nhưng một đợt có thể chạy rất lâu.
- Continue không giới hạn số lần bấm có thể làm tốn token nếu agent lặp vô ích.
- Thêm nhánh callback `k` làm router phức tạp hơn. Phải kiểm tra quyền Chat ID giống các nhánh khác.
- Quy tắc prompt (REQ-05) không bảo đảm LLM luôn tuân thủ.

### Điểm mạnh (Ưu điểm / Khả thi)
- Người dùng không mất tiến độ khi chạm giới hạn. Họ thấy việc đã làm và bấm 1 nút để chạy tiếp.
- `run_step()` đã hỗ trợ chạy tiếp với `message` rỗng (`class-agent-loop.php:137-139`). Không phải đổi logic hội thoại.
- `patch_content` đã hỗ trợ 10 edits/lần. Chỉ cần hướng dẫn agent dùng đúng là đủ giảm số bước.
- Tách file mới giữ đúng quy định < 500 dòng.

## 9. Nhiệm Vụ Cho Worker

### Task 1: Hoàn tất đồng bộ giới hạn 20 (REQ-01)
- **File mục tiêu**: `includes/class-agent-loop.php`, `assets/chatbox.js`.
- **Hành động**:
  1. Kiểm tra 6 file đã sửa (xem `git diff`) đúng như mục 5.
  2. `includes/class-agent-loop.php:6`: đổi `enforces the 6-turn limit` thành `enforces the MAX_CONTINUE_TURNS limit`.
  3. `assets/chatbox.js:190`: đổi fallback `'Maximum automatic tool turns (12) reached.'` thành `'Maximum automatic tool turns (' + MAX_CONTINUE_TURNS + ') reached.'`.
- **Lệnh kiểm chứng**:
  - `grep -rn "MAX_CONTINUE_TURNS = \|(12)\|6-turn\|12 automatic" includes assets AGENTS.md README.md`
  - `php -l includes/class-agent-loop.php && php -l includes/class-gateway-admin.php && node --check assets/chatbox.js`
- **Kết quả kỳ vọng**: Chỉ còn 2 dòng `MAX_CONTINUE_TURNS = 20` (PHP + JS). Không còn `(12)`, `6-turn`, `12 automatic`. Không có lỗi cú pháp.
- **Điểm dừng**: Có lỗi cú pháp sau 2 lần sửa -> `git checkout` file đó và báo cáo.
- **Chặn**: Chỉ làm Task 2 khi Task 1 đạt.

### Task 2: Đánh dấu chạm giới hạn và trả tóm tắt tiến độ (REQ-02)
- **File mục tiêu**: `includes/class-agent-loop.php` (nhánh dòng 153-167).
- **Hành động**:
  1. Thêm `'limit_reached' => true` vào mảng trả về của nhánh chạm giới hạn.
  2. Đổi `$limit_note` để nói rõ: đã chạm giới hạn N lượt, việc có thể chưa xong, bấm Continue hoặc gửi lệnh mới để làm tiếp.
  3. Ghi log: `error_log( sprintf( '[Align Agent] Turn limit reached | user: %d | turns: %d | conversation: %s', ... ) )`.
  4. Các nhánh khác trả `'limit_reached' => false` hoặc không có key. Caller dùng `! empty( $r['limit_reached'] )`.
- **Lệnh kiểm chứng**: `php -l includes/class-agent-loop.php && grep -n "limit_reached" includes/class-agent-loop.php`
- **Kết quả kỳ vọng**: Không lỗi cú pháp. Có `limit_reached` trong nhánh giới hạn.
- **Điểm dừng**: File vượt 500 dòng -> dừng và báo cáo.
- **Chặn**: Chỉ làm Task 3 khi Task 2 đạt.

### Task 3: Nút `▶️ Continue` trên Telegram (REQ-03)
- **File mục tiêu**:
  - Mới: `includes/class-gateway-telegram-continue.php` (class `Align_Gateway_Telegram_Continue`).
  - Sửa ít nhất có thể: `includes/class-gateway-telegram-runner.php` (sau dòng 447), `includes/class-gateway-telegram-approval.php` (router dòng 314-318 và sau vòng dòng 451), file nạp class của plugin (nơi `require` các `class-gateway-telegram-*.php`).
- **Hành động**:
  1. `Align_Gateway_Telegram_Continue::send_button( string $chat_id, string $conversation_id )`: gửi tin ngắn kèm inline keyboard 1 nút `▶️ Continue`, `callback_data = 'k|' . $conversation_id`.
  2. Runner: sau khi gửi reply, nếu `! empty( $step_result['limit_reached'] )` thì gọi `send_button()`. Luồng sau Approve (`approval.php:451`) làm tương tự.
  3. Router `handle_callback_query()`: thêm nhánh `'k' === $type` chuyển sang `Align_Gateway_Telegram_Continue::handle_callback( $cb_id, $target_id, $chat_id, $message_id )`, đặt cạnh nhánh `u`/`U`. Chat ID đã được kiểm tra ở dòng 297 trước khi tới router.
  4. `handle_callback()`:
     - Trả ACK ngay: `answer_callback_query( $cb_id, 'Continuing...' )`.
     - Gỡ nút khỏi tin cũ (`editMessageReplyMarkup` rỗng) để chống bấm 2 lần.
     - So `conversation_id` với `Align_Agent_Conversation::get_state( $uid )['conversation_id']` (kênh `telegram`). Khác thì gửi `Session changed. Send a new instruction.` và dừng.
     - Phần nặng chạy qua `shutdown` giống `Align_Gateway_Telegram_Runner::schedule_run()`: lấy lock, `wp_set_current_user`, `set_channel('telegram')`, chạy `run_step( $uid, '', 1 )` rồi vòng `while` như runner (turn 1..MAX), gửi Run Report + reply, gửi lại nút nếu vẫn `limit_reached`, nhả lock trong `finally`.
     - Dùng lại hàm gửi report/reply của runner nếu có thể. Nếu hàm đó `private`, chuyển sang `public static` thay vì chép code.
  5. Log mọi bước với prefix `[Align Agent] Telegram continue ...`.
  6. Cập nhật mục Telegram trong `AGENTS.md` (mục 3.4): thêm callback `k|{conversation_id}` và nút `▶️ Continue`.
- **Lệnh kiểm chứng**:
  - `php -l includes/class-gateway-telegram-continue.php && php -l includes/class-gateway-telegram-runner.php && php -l includes/class-gateway-telegram-approval.php`
  - `wc -l includes/class-gateway-telegram-*.php` (mọi file < 500)
  - Test thật: tạm đặt `MAX_CONTINUE_TURNS = 2`, gửi lệnh Telegram cần nhiều bước (ví dụ: "đọc trang About rồi sửa 3 chỗ trong header"). Kiểm tra có nút Continue. Bấm nút, kiểm tra agent chạy tiếp. Trả lại `20` sau khi test.
  - `grep "Telegram continue\|Turn limit reached" <php error.log>`
- **Kết quả kỳ vọng**: Khi chạm giới hạn, Telegram hiện Run Report + tin giới hạn + nút `▶️ Continue`. Bấm nút: nút biến mất, agent chạy tiếp và trả kết quả. Bấm nút cũ sau `/new`: nhận `Session changed...`.
- **Điểm dừng**: Webhook không trả 200 ngay hoặc lock bị kẹt sau khi test -> dừng, xóa option `align_agent_tg_lock` qua WP (không dùng SQL thô), báo cáo kèm log.
- **Chặn**: Chỉ làm Task 4 khi Task 3 đạt.

### Task 4: Nút `Continue` trong Chatbox (REQ-04)
- **File mục tiêu**:
  - Mới: `assets/chatbox-continue.js`.
  - Sửa ít nhất có thể: `assets/chatbox.js` (nhánh dòng 187-192 và nơi nhận `limit_reached` từ response `/chat/step`), file enqueue asset Chatbox (đăng ký `chatbox-continue.js` phụ thuộc `chatbox.js`), `includes/class-gateway-admin.php` (thêm i18n `continueLabel`).
- **Hành động**:
  1. Khi response có `limit_reached: true` hoặc client chạm `MAX_CONTINUE_TURNS`: hiện bong bóng thông báo kèm nút `<button type="button" class="button" data-action="continue-run">Continue</button>`.
  2. Bấm nút: vô hiệu hóa nút, gọi lại `runStepLoop('', 1)` (gửi message rỗng, đếm turn lại từ 1).
  3. Lộ `runStepLoop` qua `window.AlignChatbox` (ví dụ `window.AlignChatbox.continueRun`) để `chatbox-continue.js` gọi được.
  4. Log JS prefix `[Align UI]`.
- **Tiêu chuẩn SEO / ADA / Agentic**:
  - Dùng `<button type="button">`, không dùng `<div onClick>`.
  - Nút cao 28px theo `.align-agent-chatbox .button`.
  - Bong bóng thông báo có `role="status"`. Focus chuyển vào nút Continue khi nó xuất hiện. Focus nhìn thấy được.
  - Contrast chữ nút >= 4.5:1.
  - Thuộc tính `data-action="continue-run"`.
- **Lệnh kiểm chứng**:
  - `node --check assets/chatbox.js && node --check assets/chatbox-continue.js && wc -l assets/chatbox.js`
  - Test thật: tạm đặt giới hạn 2 ở cả PHP và JS, mở Drawer, gửi lệnh nhiều bước, bấm Continue, kiểm tra agent chạy tiếp. Trả lại `20`.
- **Kết quả kỳ vọng**: Chạm giới hạn thì hiện nút Continue. Bấm nút thì agent chạy tiếp. Tab tới được nút. Console không có lỗi.
- **Điểm dừng**: `chatbox.js` vượt 500 dòng -> chuyển thêm code sang `chatbox-continue.js`. Không làm được thì dừng và báo cáo.
- **Chặn**: Chỉ làm Task 5 khi Task 4 đạt.

### Task 5: Quy tắc prompt giảm số bước khi sửa HTML (REQ-05)
- **File mục tiêu**: `includes/class-agent-loop.php` (`build_system_prompt()`, khối quy tắc dòng 55-66).
- **Hành động**: Thêm 1 quy tắc tiếng Anh, ngắn, với các ý sau:
  - Gom mọi thay đổi trên cùng 1 đối tượng vào 1 lần `patch_content` (tối đa 10 edits).
  - Các tool đọc không phụ thuộc nhau thì gọi song song trong cùng 1 lượt.
  - Không đọc lại toàn bộ nội dung sau mỗi lần sửa. Dùng kết quả trả về của `patch_content` để xác nhận.
  - Khi việc lớn hơn 1 đợt lượt, ưu tiên hoàn tất từng đối tượng rồi báo tiến độ.
- **Lệnh kiểm chứng**: `php -l includes/class-agent-loop.php && grep -n "patch_content" includes/class-agent-loop.php`
- **Kết quả kỳ vọng**: Có quy tắc mới trong prompt. Không lỗi cú pháp. File < 500 dòng.
- **Điểm dừng**: File vượt 500 dòng -> dừng và báo cáo.
- **Chặn**: Chỉ làm Task 6 khi Task 5 đạt.

### Task 6: Nhãn kênh `mcp` cho lệnh ghi qua MCP (REQ-06)
- **File mục tiêu**: `includes/class-gateway-mcp.php:285`.
- **Hành động**: Đổi tham số `'telegram'` thành `'mcp'`. Kiểm tra `Align_Agent_Write_Executor::run()` và `Align_Agent_Approval_Policy` có xử lý kênh `mcp` không. Nếu policy phân nhánh theo kênh, giữ nguyên hành vi hiện tại của MCP (cùng nhánh với `telegram`) bằng cách thêm `mcp` vào nhánh đó.
- **Lệnh kiểm chứng**:
  - `php -l includes/class-gateway-mcp.php`
  - `grep -rn "'telegram'" includes/class-agent-write-executor.php includes/class-agent-approval-policy.php` (tìm chỗ phân nhánh theo kênh; tên file thực tế xác minh bằng `ls includes | grep -i "executor\|policy"`)
  - Gọi 1 tool ghi qua MCP, kiểm tra log `Change set opened | ... | channel: mcp`.
- **Kết quả kỳ vọng**: Change set từ MCP ghi `channel: mcp`. Hành vi tự ghi/duyệt của MCP không đổi.
- **Điểm dừng**: Lệnh ghi qua MCP bị chặn hoặc đổi sang cần duyệt -> rollback và báo cáo.

## 10. Tiêu Chí Nghiệm Thu (Cho User Kiểm Tra)
- [x] REQ-01: Mở `includes/class-agent-loop.php` và `assets/chatbox.js` -> cả hai ghi `MAX_CONTINUE_TURNS = 20`.
- [x] REQ-02 + REQ-03: Gửi lệnh Telegram sửa HTML nhiều vị trí. Nếu chạm giới hạn -> thấy Run Report, tin giới hạn và nút `▶️ Continue`. Bấm nút -> agent làm tiếp.
- [x] REQ-04: Làm như trên trong Chatbox WP Admin -> thấy nút `Continue`, bấm thì agent làm tiếp.
- [x] REQ-05: Gửi lệnh sửa 3 chỗ trong 1 template -> log chỉ có 1 dòng `Audit log recorded | tool: patch_content` cho template đó.
- [x] REQ-06: Gọi 1 lệnh ghi qua MCP -> log ghi `channel: mcp`.

## 11. Quy Tắc Cho Worker
- Thực thi đúng các Task theo thứ tự. Không làm việc ngoài plan.
- Gặp câu hỏi chặn có bằng chứng: dừng và báo lại. Điểm không chặn: chọn phương án an toàn nhất và ghi lại.
- Thất bại kiểm chứng quá 2 lần ở một Task: dừng và báo cáo. Không sửa mò sang file khác.
- Mọi file PHP/JS phải < 500 dòng. Comment, log, chuỗi UI viết tiếng Anh.
- Trả mọi giá trị test tạm (`MAX_CONTINUE_TURNS = 2`) về `20` trước khi báo cáo.
- Báo cáo cuối: `SUCCESS` hoặc `FAILED` + danh sách file đã sửa + output của lệnh kiểm chứng.
