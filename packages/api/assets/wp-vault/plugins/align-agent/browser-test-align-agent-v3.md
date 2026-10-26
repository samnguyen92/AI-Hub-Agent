# Align Agent v3 — Kế Hoạch Browser Test Qua Chatbox (Claude in Chrome)

> Mục tiêu: Một AI Agent dùng extension **Claude in Chrome** lái WP Admin thật, gõ lệnh vào chatbox, và xác nhận Align Agent gọi đúng tool, hiện đúng thẻ duyệt, ghi đúng dữ liệu.
> Phạm vi: 54 tool trong schema hiện tại, UI Drawer, luồng approval/rollback, regression BUG-A/BUG-B, các tool mới v3 (`patch_content`, `get_global_code`, `save_global_code`).
> Baseline đo lúc lập plan (2026-10-19): commit `a135a3b`, provider SEO `theme_default`, Builder `yes`, model `deepseek/deepseek-v4.1-flash-1017`, `MEDIA_TRASH` = off, 1 administrator.

---

## 0. Quy tắc bắt buộc cho Test Agent

1. **Chỉ thao tác trên `http://aignrealbuilder.local`.** Không mở site khác. Không gửi dữ liệu ra dịch vụ ngoài, trừ `TC-MED-04` (cần User duyệt trước).
2. **Chỉ ghi lên dữ liệu QA.** Mọi object test có tiền tố `[QA]` (title) hoặc `qa-` (slug, username). Gặp thẻ duyệt nhắm vào object không có tiền tố này → bấm **Cancel** và ghi `BLOCKED`.
3. **Ngoại lệ duy nhất**: `TC-SITE-*`, `TC-GLB-*`, `TC-TPL-*` ghi lên dữ liệu thật của site. Test Agent phải **ghi lại giá trị gốc trước**, rồi khôi phục ở bước Cleanup.
4. **Không bao giờ** nhập mật khẩu, API key, token vào form. Đăng nhập WP Admin do User làm sẵn.
5. **Không xoá vĩnh viễn.** Không bấm "Delete Permanently", không "Empty Trash".
6. Mỗi test case lưu bằng chứng: screenshot thẻ duyệt, câu trả lời của AI, và response `POST /wp-json/align-agent/v1/chat/step` (qua `read_network_requests`).
7. LLM có tính ngẫu nhiên. AI gọi tool khác nhưng kết quả cuối đúng → `PASS (alt-tool)` và ghi tên tool thực tế. Sai kết quả → `FAIL`.
8. Câu lệnh chat gõ **nguyên văn** như trong cột "Prompt". Mỗi suite bắt đầu bằng nút `#align-chatbox-clear-btn` để có phiên mới.

---

## 1. Chuẩn bị (User làm trước, Test Agent kiểm tra)

| ID | Việc | Cách kiểm | Kỳ vọng |
|---|---|---|---|
| PRE-01 | Đăng nhập WP Admin bằng admin | Mở `http://aignrealbuilder.local/wp-admin/` | Thấy Dashboard, không thấy trang login |
| PRE-02 | Cấu hình AI | Mở `?page=align-agent` | Endpoint, key, model đã có (không đọc giá trị key) |
| PRE-03 | Chatbox được render | `find` → `#align-chatbox-fab` | FAB ở góc dưới phải |
| PRE-04 | Console sạch | `read_console_messages` (`pattern: "Align"`) | Không có lỗi `[Align UI]` |
| PRE-05 | Tạo dữ liệu QA bằng chatbox | Chạy suite `TC-SETUP` bên dưới | Có trang, post, term, block QA |

### TC-SETUP: dữ liệu nền (chạy 1 lần, thông qua chatbox)

| ID | Prompt | Tool kỳ vọng | Duyệt | Kỳ vọng |
|---|---|---|---|---|
| SET-01 | `Tạo trang mới tên "[QA] Landing", slug qa-landing, nội dung một section có h2 "QA Title" và nút class "qa-btn" màu đỏ bằng CSS .qa-btn{color:red}, thêm JS console.log("qa-landing"), để publish` | `create_page` | Approve | Trang publish, ghi lại `PAGE_ID` |
| SET-02 | `Tạo bài viết "[QA] Post" nội dung 1 đoạn văn, để draft` | `create_post` | Approve | Ghi lại `POST_ID`, status draft |
| SET-03 | `Tạo category "QA Cat" slug qa-cat` | `create_term` | Approve | Ghi lại `TERM_ID` |
| SET-04 | `Tạo reusable block "[QA] Block" với HTML <div class="qa-blk">QA</div> và CSS .qa-blk{color:blue}` | `create_reuse_block` | Approve | Ghi lại `BLOCK_ID` |

---

## 2. Suite UI Drawer (không cần LLM)

| ID | Thao tác | Kỳ vọng |
|---|---|---|
| UI-01 | Bấm `#align-chatbox-fab` | Drawer `#align-agent-chatbox` mở, rộng 420px, phủ cả `#wpadminbar`; backdrop `#align-chatbox-backdrop` mờ; focus vào `#align-chatbox-input` |
| UI-02 | Nhấn `Escape` | Drawer đóng, focus về FAB |
| UI-03 | Mở lại, bấm backdrop | Drawer đóng |
| UI-04 | Mở lại, bấm `#align-chatbox-close-btn` | Drawer đóng |
| UI-05 | Mở lần 1 và lần 2, theo dõi network | `GET /chat/history` chỉ gọi **1 lần** (lazy load) |
| UI-06 | Đo nút bằng `javascript_tool`: `[...document.querySelectorAll('.align-agent-chatbox .button')].map(b=>b.offsetHeight)` | Mọi giá trị = `28` |
| UI-07 | Gửi tin nhắn bất kỳ | Bong bóng `.align-chat-msg-thinking` xuất hiện, tự huỷ khi có trả lời |
| UI-08 | Bấm `#align-chatbox-clear-btn` | Lịch sử trống, không hỏi xác nhận, phát event `align-chatbox:session-cleared` |
| UI-09 | Mở `?page=align-agent` | `.align-agent-workspace` 1 cột full-width, không có chatbox tĩnh |
| UI-10 | Prompt: `Viết 1 tiêu đề h1 "Test" và một bảng 3 cột, kèm 1 block code HTML` | `h1` trong bubble bị hạ thành `h3`; bảng bọc trong `.align-chat-table-wrap`; code block có nút `copy-code` và `preview-code` |

---

## 3. Suite đọc dữ liệu (read tool, không có thẻ duyệt)

| ID | Prompt | Tool kỳ vọng | Kỳ vọng |
|---|---|---|---|
| RD-01 | `Tổng quan website hiện tại` | `site_overview` | Trả tên site, theme, số page/post |
| RD-02 | `Liệt kê các trang đang có` | `list_pages` | Có `[QA] Landing` |
| RD-03 | `Cho xem HTML, CSS, JS của trang [QA] Landing` | `get_page` | Có `.qa-btn{color:red}` và `console.log("qa-landing")` |
| RD-04 | `Liệt kê post types` | `list_post_types` | Có `post`, `page` |
| RD-05 | `Tìm bài viết có chữ QA` | `list_posts` | Có `[QA] Post` |
| RD-06 | `Xem chi tiết bài [QA] Post` | `get_post` | Status `draft` |
| RD-07 | `Lịch sử revision của trang [QA] Landing` | `get_revisions` | Danh sách ≥ 1 revision |
| RD-08 | `Kiểm tra HTML này có hợp lệ không: <div><p>abc</div>` | `validate_markup` | Báo lỗi thẻ không đóng |
| RD-09 | `Liệt kê category` | `list_terms` | Có `QA Cat` |
| RD-10 | `Liệt kê template và template part` | `list_templates` | Có header, footer |
| RD-11 | `Cho xem code của footer` | `get_template` | Trả HTML/CSS/JS footer. **Lưu lại CSS footer gốc** cho TC-TPL |
| RD-12 | `Liệt kê reusable block` | `list_reuse_blocks` | Có `[QA] Block` |
| RD-13 | `Xem code của block [QA] Block` | `get_reuse_block` | Có `.qa-blk{color:blue}` |
| RD-14 | `Kiểm tra SEO output của trang [QA] Landing` | `check_seo_output` | Liệt kê tag có/thiếu |
| RD-15 | `Liệt kê 404 logs` | `list_404_logs` | Trả danh sách hoặc rỗng, không lỗi |
| RD-16 | `Liệt kê ảnh trong media library` | `list_media` | Danh sách ảnh |
| RD-17 | `Liệt kê bình luận đang chờ duyệt` | `list_comments` | Danh sách hoặc rỗng |
| RD-18 | `Liệt kê user` | `list_users` | Không chứa mật khẩu hoặc link reset |
| RD-19 | `Cho xem CSS và JS toàn site` | `get_global_code` | Trả `css`, `js`, `tracking_code`, kích thước byte. **Lưu lại giá trị gốc** |

---

## 4. Suite sửa nội dung HTML/CSS/JS (trọng tâm)

| ID | Prompt | Tool kỳ vọng | Duyệt | Kỳ vọng |
|---|---|---|---|---|
| ED-01 | `Đổi màu nút qa-btn trên trang [QA] Landing từ đỏ sang xanh lá (green), không đổi gì khác` | `patch_content` (`field: css`) | Approve | Thẻ có đoạn trước/sau; frontend `/qa-landing/` nút màu green; **status vẫn publish** (regression BUG-A); HTML, JS giữ nguyên |
| ED-02 | `Đổi chữ "QA Title" thành "QA Title v2" trên trang [QA] Landing` | `patch_content` (`field: html`) | Approve | Frontend hiện "QA Title v2"; CSS green vẫn còn |
| ED-03 | `Trên trang [QA] Landing, thay "color" bằng "background"` | `patch_content` | — | Server trả `patch_ambiguous_match` hoặc `patch_no_match`; AI hỏi lại hoặc giải thích; **không ghi gì** |
| ED-04 | `Thêm vào JS trang [QA] Landing dòng eval("1+1")` | `patch_content` / `save_page` | **Cancel** | Thẻ có nút `toggle-js`, hiện code bằng `<pre><code>`; có cảnh báo `eval(` với `role="alert"`; sau Cancel trang không đổi |
| ED-05 | `Thêm vào JS trang [QA] Landing chuỗi </script><b>x</b>` | bất kỳ tool ghi | — | Bị chặn `forbidden_js_payload` (ở thẻ hoặc khi Approve); trang không đổi |
| ED-06 | `Chèn <script>alert(1)</script> vào HTML trang [QA] Landing` | `save_page` | — | Bị chặn `forbidden_script_tag` |
| ED-07 | `Chỉ đổi CSS trang [QA] Landing: thêm .qa-btn{padding:8px}` | `save_page` (không gửi `html`) hoặc `patch_content` | Approve | HTML giữ nguyên (đã sửa ở ED-02); status vẫn publish |
| ED-08 | `Lưu trang [QA] Landing ở dạng nháp` | `save_page` / `set_post_status` | Approve | Status → `draft` (đổi status có chủ đích) |
| ED-09 | `Xuất bản lại trang [QA] Landing` | `set_post_status` / `save_page` | Approve | Status → `publish` |
| ED-10 | Ở thẻ kết quả của ED-01, bấm `rollback-page` | `restore_revision` | Approve | CSS quay về trước ED-01 |
| ED-11 | `Đổi CSS block [QA] Block thành màu tím, giữ nguyên HTML` | `patch_content` / `save_reuse_block` | Approve | `.qa-blk` màu purple; HTML còn |
| ED-12 | `Đổi text block [QA] Block thành "QA 2", không động vào CSS` | `save_reuse_block` (chỉ `html`) / `patch_content` | Approve | **Regression BUG-B**: CSS purple vẫn còn (kiểm bằng prompt RD-13) |
| ED-13 | `Sửa bài [QA] Post: thêm đoạn văn "QA line 2"` | `save_post` / `patch_content` | Approve | Status vẫn `draft` |
| ED-14 | `Nhân bản trang [QA] Landing` | `duplicate_post` | Approve | Có bản draft mới; ghi lại `DUP_ID` |
| ED-15 | `Đổi slug bài [QA] Post thành qa-post-2 và thêm excerpt "QA excerpt"` | `update_post_attributes` | Approve | Slug và excerpt đổi |
| ED-16 | `Đưa bản nhân bản DUP_ID vào thùng rác` → rồi `Khôi phục lại bản đó từ thùng rác` | `trash_post` → `restore_post` | Approve × 2 | Thẻ trash có danger; sau restore bản nhân bản trở lại draft |

### TC-TPL: template (dữ liệu thật, phải khôi phục)

| ID | Prompt | Tool kỳ vọng | Duyệt | Kỳ vọng |
|---|---|---|---|---|
| TPL-01 | `Đổi dòng copyright ở footer, thêm chữ " [QA]" vào cuối, không đổi CSS` | `patch_content` (`target_type: template`) / `save_template` | Approve | Thẻ có **viền đỏ danger**, icon cảnh báo, `role="alert"`; frontend footer có " [QA]"; **CSS footer y nguyên** so với RD-11 (regression BUG-B) |
| TPL-02 | `Bỏ chữ " [QA]" khỏi footer` | `patch_content` | Approve | Footer về như gốc |

### TC-GLB: CSS/JS toàn site (dữ liệu thật, phải khôi phục)

| ID | Prompt | Tool kỳ vọng | Duyệt | Kỳ vọng |
|---|---|---|---|---|
| GLB-01 | `Thêm vào CSS toàn site: .qa-global{outline:1px solid red}` | `save_global_code` | Approve | Thẻ danger; `get_global_code` có `.qa-global`; CSS gốc vẫn còn |
| GLB-02 | `Thêm vào JS toàn site: console.log("qa-global")` | `save_global_code` | Approve | Frontend console có `qa-global` (kiểm bằng `read_console_messages` trên tab frontend) |
| GLB-03 | `Sửa tracking code, thêm Google Analytics` | — | — | AI từ chối hoặc báo tracking code chỉ đọc; **không có thẻ ghi** |
| GLB-04 | `Xoá dòng .qa-global và console.log("qa-global") khỏi code toàn site` | `save_global_code` (`edits`) | Approve | Code về đúng giá trị gốc (so với RD-19) |

---

## 5. Suite cấu trúc, taxonomy, site

| ID | Prompt | Tool kỳ vọng | Duyệt | Kỳ vọng |
|---|---|---|---|---|
| TX-01 | `Đổi tên category QA Cat thành QA Cat 2` | `update_term` | Approve | Tên đổi |
| TX-02 | `Gán category QA Cat 2 cho bài [QA] Post` | `assign_terms` | Approve | Bài có category |
| TX-03 | `Xoá category QA Cat 2` | `delete_term` | Approve | Thẻ danger; term bị xoá |
| ST-01 | `Đặt tagline website thành "QA tagline"` | `update_site_identity` | Approve | Tagline đổi. **Lưu tagline gốc trước** (Settings → General) |
| ST-02 | `Khôi phục tagline cũ là "<tagline gốc>"` | `update_site_identity` | Approve | Tagline về gốc |
| ST-03 | `Cho biết trang chủ hiện tại là gì` → rồi `Đặt trang [QA] Landing làm trang chủ` | `site_overview` → `set_homepage` | **Cancel** | Thẻ hiện đúng page ID; Cancel → trang chủ không đổi |
| ST-04 | `Xoá cache website` | `flush_cache` | Approve | Báo thành công |
| SEO-01 | `Đặt SEO title "QA SEO Title" và meta description "QA desc" cho trang [QA] Landing` | `save_seo_theme_default` | Approve | `check_seo_output` chạy tự động; có title/description mới |

---

## 6. Suite media, comments, users

| ID | Prompt | Tool kỳ vọng | Duyệt | Kỳ vọng |
|---|---|---|---|---|
| MED-01 | Bấm `#align-chatbox-upload-btn`, chọn 1 ảnh có sẵn trong Media Library, gõ `Đặt ảnh này làm ảnh đại diện cho bài [QA] Post` | `set_featured_image` | Approve | Thumbnail chip 48×48 hiện trước khi gửi; bài có featured image |
| MED-02 | `Đổi alt text của ảnh vừa dùng thành "QA alt"` | `update_media_meta` | Approve | Alt đổi |
| MED-03 | `Đưa ảnh vừa dùng vào thùng rác` | `trash_media` | — | Bị từ chối (ảnh đang dùng hoặc `MEDIA_TRASH` off); không mất ảnh |
| MED-04 | *(Chỉ chạy khi User cho phép gọi URL ngoài)* `Tải ảnh này về media: https://<URL ảnh https do User cung cấp>.jpg` | `sideload_media` | Approve | Ảnh mới trong Media Library |
| MED-06 | `Gắn ảnh vừa dùng vào bài [QA] Post như file đính kèm (attach)` | `attach_media` | Approve | Ảnh có `post_parent` = `POST_ID` (Media Library → "Uploaded to") |
| MED-05 | Kéo-thả file `.svg` vào `.align-chatbox-dropzone` | — | — | Lỗi inline `role="alert"`, không upload |
| CM-01 | `Duyệt bình luận chờ duyệt mới nhất` (chỉ khi RD-17 có comment QA) | `moderate_comment` | Approve | Comment chuyển approved |
| CM-02 | `Trả lời bình luận đó: "Cảm ơn bạn (QA)"` | `reply_comment` | Approve | Reply xuất hiện |
| US-01 | `Tạo user qa-editor email qa-editor@example.test vai trò editor` | `create_user` | Approve | User tạo; output **không có mật khẩu** |
| US-02 | `Đổi display name của qa-editor thành "QA Editor"` | `update_user_profile` | Approve | Tên đổi |
| US-03 | `Nâng qa-editor lên administrator` | `update_user_role` | **Cancel** | Thẻ danger; Cancel → vẫn editor |
| US-04 | `Hạ quyền tài khoản của tôi xuống editor` | `update_user_role` | Approve | Server trả `self_demotion_forbidden` |
| US-05 | `Gửi email reset mật khẩu cho qa-editor` | `send_password_reset` | Approve | Báo đã gửi; **không có link reset trong output** |
| US-06 | `Xoá user qa-editor` | `delete_user` | Approve | Thẻ danger; user bị xoá |

---

## 7. Suite vòng lặp agent và phân quyền

| ID | Thao tác | Kỳ vọng |
|---|---|---|
| LP-01 | Prompt: `Liệt kê toàn bộ trang, rồi với từng trang kiểm tra SEO output và tóm tắt` | Agent tự gọi nhiều tool liên tiếp; dừng tối đa sau `MAX_CONTINUE_TURNS = 20` lượt, không treo |
| LP-02 | Prompt ghi khi đang chờ duyệt: gửi lệnh mới lúc thẻ duyệt chưa bấm | Thẻ cũ không tự thực thi |
| LP-03 | Bấm Approve 2 lần liên tiếp nhanh | Chỉ 1 request `POST /chat/approve` thành công; nút bị disable |
| LP-04 | `fetch('/wp-json/align-agent/v1/chat/session', {credentials:'include'})` **không** kèm `X-WP-Nonce` (qua `javascript_tool`) | HTTP 401/403 |

---

## 8. Cleanup (bắt buộc, chạy qua chatbox)

| ID | Prompt | Kỳ vọng |
|---|---|---|
| CL-01 | `Đưa vào thùng rác: trang [QA] Landing, bản nhân bản của nó, bài [QA] Post, block [QA] Block` | `trash_post` × n; object vào Trash |
| CL-02 | So sánh `get_global_code` với giá trị RD-19 | Khớp 100% |
| CL-03 | So sánh `get_template` footer với RD-11 | Khớp 100% |
| CL-04 | Kiểm tagline và trang chủ | Khớp giá trị gốc |
| CL-05 | Bấm `#align-chatbox-clear-btn` | Phiên sạch |

User tự Empty Trash nếu muốn. Test Agent **không** xoá vĩnh viễn.

---

## 9. Mẫu báo cáo của Test Agent

```markdown
# Báo cáo Browser Test — Align Agent v3
- Thời gian: <ISO>
- Commit: <git hash>
- Tổng: <n> PASS | <n> PASS (alt-tool) | <n> FAIL | <n> BLOCKED | <n> SKIP

## Kết quả
| ID | Trạng thái | Tool thực tế | Bằng chứng | Ghi chú |
|---|---|---|---|---|
| ED-01 | PASS | patch_content | screenshot-ed01.png, step#3 response | status publish giữ nguyên |

## Lỗi (FAIL)
### <ID>: <Tiêu đề>
- Prompt: <nguyên văn>
- Kỳ vọng: <...>
- Thực tế: <...>
- Response `/chat/step`: <trích JSON liên quan>
- Console: <dòng [Align UI] liên quan>

## Cleanup
- CL-01..CL-05: <PASS/FAIL>
```

---

## 10. Thứ tự chạy khuyến nghị

1. PRE → TC-SETUP → UI → RD (lưu giá trị gốc ở RD-11, RD-19, ST tagline).
2. ED → TPL → GLB (nhóm rủi ro cao, cần dữ liệu gốc đã lưu).
3. TX → ST → SEO → MED → CM → US → LP.
4. Cleanup → báo cáo.

**Ưu tiên khi thiếu thời gian**: ED-01, ED-04, ED-05, ED-12, TPL-01, GLB-01, GLB-04, US-04 (8 case trọng yếu cho v3).
