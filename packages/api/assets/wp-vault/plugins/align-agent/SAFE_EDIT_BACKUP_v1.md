# PLAN v1 - Safe Edit: Backup bắt buộc, Global Vault, Bản đồ trang

- **Version**: v1
- **Ngày**: 2026-10-24
- **Loại**: Sửa lỗi + Tính năng mới
- **Plan trước**: `FIX_AGENT_STEP_LIMIT_v1.md` (đã DONE, chỉ liên quan giới hạn lượt)
- **Trạng thái**: DONE
- **Repo**: plugin `Align-Agent-main` (root của file này)

## 1. Yêu Cầu Gốc Của User (Nguyên Văn)
> tôi nghĩ tôi cần 1 giải pháp cho việc nếu có yêu cầu từ việc chỉnh sửa html, sửa lỗi function thì phải làm sao . cần load context gì để AI Agent hiểu

> 1. Không cho đọc PHP nhé 2. Ok 3. Không agent align này chỉ sửa code html,css,js các function js trên web thôi

> tôi cần 1 giải pháp chuẩn chính xác , backup trước khi sửa cho phép revert etc đặc biệt các Global scope

> khoan nếu lưu trong DB vậy thì đâu có lưu nhiều được ? vì có khi page nó năng thì sao /

> nhưng phải đảm bảo fix các điểm yéu này đi chứ

> giảm còn 10 bản thôi

> Lưu 7 ngày thôi không lưu 30 ngày nhé site lớn thì mệt đó

## 2. Hiểu Yêu Cầu (STE)
- **Vấn đề 1**: Agent không có bản đồ trang. Agent mất 8 giai đoạn để biết header nào đang hiển thị (`tp_header`), và kết luận `header-vi` là bản legacy.
- **Vấn đề 2**: Sửa header/footer (scope global) không có backup. Hoàn tác báo thành công nhưng không khôi phục gì.
- **Vấn đề 3**: Trang lớn hơn 512 KB bị hỏng khi hoàn tác.
- **Mục tiêu**:
  - Không có backup hợp lệ thì không có lệnh sửa (fail-closed).
  - Phần global có kho backup riêng, khôi phục được, xem diff được.
  - Agent tìm đúng chỗ sửa trong 1–2 lần gọi tool.
- **Phạm vi**:
  - Agent CHỈ sửa HTML, CSS, JS (gồm các function JS trên web).
  - Agent KHÔNG đọc và KHÔNG sửa file PHP.
  - KHÔNG sửa Middleware Hub (repo khác).
  - KHÔNG làm lọc tool theo ý định (để plan sau).
- **Nghiệm thu**: Xem mục 9.

## 3. Danh Sách Yêu Cầu (REQ-ID)
| REQ-ID | Mô tả | Giai đoạn | Trạng thái |
|---|---|---|---|
| REQ-01 | Adapter `post` và `template` đọc đúng `id_arg` trong undo spec. `tp_id` được chuyển thành post ID thật. | 1 | DONE |
| REQ-02 | `patch_content` chọn adapter theo `target_type` (`template`, `reuse_block` dùng adapter `template` với `tp_id`/`post_id`). | 1 | DONE |
| REQ-03 | Backup Gate fail-closed: tool có adapter `post`, `template`, `option` mà snapshot rỗng hoặc object ID = 0 thì KHÔNG ghi, trả `backup_failed`. | 1 | DONE |
| REQ-04 | Revert kiểm tra `is_wp_error()`, đọc lại và so hash. Lệch thì trả `revert_mismatch`, không trả `success: true`. | 1 | DONE |
| REQ-05 | Sửa L8: bản nén lưu vào trường riêng `html_gz`, không đè `html`. Trang > 2 MB revert qua `wp_restore_post_revision()`. | 1 | DONE |
| REQ-06 | Global Vault dùng CPT ẩn `align_agent_backup`. 1 bản backup = 1 dòng `wp_posts`, 0 dòng `wp_postmeta`. | 2 | DONE |
| REQ-07 | Giới hạn vault: **10 bản / đối tượng**, 500 bản toàn site, 50 MB dữ liệu nén, TTL **7 ngày**. Dọn khi ghi và qua WP-Cron hằng ngày. Luôn giữ tối thiểu 3 bản mới nhất mỗi đối tượng khi dọn theo dung lượng. | 2 | DONE |
| REQ-08 | Chống trùng bằng hash: nội dung giống bản gần nhất thì không lưu thêm. | 2 | DONE |
| REQ-09 | Chỉ backup trường sắp bị sửa (`html`, `css`, `js` hoặc từng option key). | 2 | DONE |
| REQ-10 | Chia mảnh 512 KB dữ liệu nén. Đọc lại, ghép, giải nén, so hash. Sai thì xóa bản lỗi và chặn ghi (`backup_failed`). | 2 | DONE |
| REQ-11 | Nội dung gốc > 20 MB: chặn ghi, trả `backup_too_large`. | 2 | DONE |
| REQ-12 | CPT đăng ký `can_export => false`. Mỗi bản lưu `home_url`. Khác domain thì đánh dấu `foreign`, ẩn, cấm restore, tự xóa sau 7 ngày. | 2 | DONE |
| REQ-13 | Nút `Purge Backups` ở tab `Updates`, có xác nhận, nonce `align_agent_admin_nonce`. | 2 | DONE |
| REQ-14 | Kiểm tra sau ghi: đọc lại object, so hash mong đợi. Lệch thì tự khôi phục từ vault, trả `write_verify_failed`. | 2 | DONE |
| REQ-15 | Tool `list_backups`, `search_backups`, `diff_backup` (read) và `restore_backup` (write, `danger: true`, `scope: global`, luôn cần duyệt, tự backup trạng thái hiện tại trước khi khôi phục). | 2 | DONE |
| REQ-16 | Tool read-only `inspect_render(url \| post_id)` trả bản đồ trang (mục 6). | 3 | DONE |
| REQ-17 | Tool read-only `find_in_render(url, selector \| text)` trả đoạn code và nguồn chính xác, gồm function JS tham chiếu selector. | 3 | DONE |
| REQ-18 | Thêm khối `RENDER MODEL`, luật `Diagnose-first`, luật `Global-safe` vào `build_system_prompt()`. | 3 | DONE |
| REQ-19 | Working Notes theo phiên và kênh (user meta, tối đa 1 KB), chèn vào system prompt khi Continue. | 4 | DONE |
| REQ-20 | Cập nhật `MANIFEST`, `includes/tools/README.md`, README từng group, `AGENTS.md` §4–§5. | 4 | DONE |

## 4. Thay Đổi So Với v0
Không có v0. Đây là plan đầu tiên của tính năng này.

## 5. Context Đã Đọc (Lỗi Đã Xác Minh)

| # | Mức | Vị trí | Lỗi |
|---|---|---|---|
| L1 | Nghiêm trọng | `includes/class-agent-undo-adapters.php:84-85` | Adapter `template` chỉ đọc `template_id`/`block_id`/`id`, bỏ qua `$spec['id_arg']`. `save_template` (`includes/tools/structure/class-agent-tools-templates.php:59`) và `update_nav_links` (`includes/tools/structure/class-agent-tools-navigation.php:48`) gửi `tp_id = "tp_header"`, nên `absint()` = 0 và không có snapshot. |
| L2 | Nghiêm trọng | `includes/tools/content/class-agent-tools-patch.php:26` | `patch_content` khai báo adapter `post` + `id_arg: post_id`. Khi `target_type=template`, model gửi `tp_id`, nên không có snapshot. |
| L3 | Cao | `includes/class-agent-undo-adapters.php:345-364` | Revert `template` không kiểm tra kết quả `save_page()`, luôn trả `success: true`. |
| L4 | Cao | `includes/class-agent-write-executor.php:26` | Snapshot rỗng nhưng lệnh ghi vẫn chạy. |
| L5 | Trung bình | `includes/tools/structure/class-agent-tools-templates.php:75` | `save_reuse_block` khai báo `id_arg: post_id`, adapter không đọc `post_id`. Chỉ có snapshot khi model gửi alias `id`. |
| L6 | Trung bình | `includes/class-agent-change-set.php:20-21` | Backup global dùng chung `MAX_PER_USER = 30`, `TTL_SECONDS = 604800` với sửa đổi cục bộ. |
| L7 | Trung bình | — | Không đọc lại sau ghi để đối chiếu hash. |
| L8 | Nghiêm trọng | `includes/class-agent-change-set.php:100-117`, `:72-76`, `includes/class-agent-undo-adapters.php:293` | `post_content` > 512 KB: base64 gzip đè lên `before['html']`. Khi đọc chỉ giải nén vào `post_content`. Revert ưu tiên `before['html']`, nên ghi chuỗi base64 vào trang. > 2 MB: bỏ HTML, giữ `revision_id`, nhưng revert không dùng `revision_id`. |

### Hạ tầng tái sử dụng
- `Align_Agent_Undo_Adapters::capture()` / `capture_after()` / `revert()`: `includes/class-agent-undo-adapters.php:24`, `:209`, `:278`.
- `Align_Agent_Write_Executor::run()`: `includes/class-agent-write-executor.php:26`.
- `Align_Agent_Change_Set`: `includes/class-agent-change-set.php` (claim mutex, gc, index).
- `Align_Agent_Undo_Service::undo()`: `includes/class-agent-undo-service.php:25`.
- `Align_Agent_Option_Writer::write()` / `restore()`: `includes/tools/site/class-agent-option-writer.php:35`, `:137`.
- `Align_Gateway_Pages::extract_decoupled_parts()`: tách `body_html`, `css`, `js`.
- `Align_Agent_Tools_Templates::tool_list_templates()`: tạo `tp_id` từ `$p->id` (`class-agent-tools-templates.php:161`).
- `Align_Agent_Loop::build_system_prompt()`: `includes/class-agent-loop.php:35`.
- Số dòng hiện tại (giới hạn < 500): `class-agent-undo-adapters.php` 451, `class-agent-change-set.php` 298, `class-agent-loop.php` 436, `class-gateway-admin.php` 494. **Code mới PHẢI nằm ở file mới.**

## 6. Thiết Kế Chi Tiết

### Giai đoạn 1: Backup bắt buộc, fail-closed
- File mới `includes/class-agent-undo-resolver.php`:
  - `resolve_object_id( array $spec, array $args, string $tool ): array` trả `{ id, type, tp_id }`.
  - Đọc `$spec['id_arg']` trước, sau đó mới tới các alias `post_id`, `page_id`, `id`, `template_id`, `block_id`.
  - `tp_id`: bỏ tiền tố `tp_`/`tpl_` nếu cần, gọi `get_block_template( $id, 'wp_template_part' )` hoặc `'wp_template'`, lấy `wp_id`.
  - Template gốc của theme (chưa có `wp_id`): trả `id = 0`, `type = 'theme_file'` và kèm `content` của block template làm `before`.
  - `patch_content`: `target_type` `template` → adapter `template` + `tp_id`; `reuse_block` → adapter `template` + `post_id`.
- `Align_Agent_Undo_Adapters::capture()`: dùng resolver cho `post` và `template`.
- `Align_Agent_Write_Executor::run()`: thêm Backup Gate trước khi gọi callback.
  - Điều kiện chặn: adapter thuộc `post|template|option` và (`before` rỗng HOẶC object ID = 0 với `type !== 'theme_file'`).
  - Trả `array( 'success' => false, 'error' => 'backup_failed', 'message' => 'Backup could not be created. The edit was blocked.' )`.
  - Log: `[Align Agent] Backup gate blocked | tool: %s | target: %s`.
- Revert `template` và `post`: kiểm tra `is_wp_error()`. Sau khi ghi, `md5( post_content )` phải khớp hash của `before`. Lệch thì trả `revert_mismatch`.
- Sửa L8 trong `Align_Agent_Change_Set::append()` / `get()`:
  - Nén vào `before['html_gz']` (và `before['post_content_gz']`). KHÔNG ghi đè `before['html']`.
  - `get()` giải nén vào đúng trường gốc.
  - Có `html_dropped` và `revision_id > 0` thì revert gọi `wp_restore_post_revision( $revision_id )`.

### Giai đoạn 2: Global Vault (CPT `align_agent_backup`)
- File mới `includes/class-agent-global-vault.php` (< 400 dòng). File mới `includes/class-agent-vault-gc.php` cho dọn dẹp và cron.
- Đối tượng thuộc vault (`object_key`):
  - `tpl:<slug>`, `tp:<slug>`: `save_template`, `update_nav_links`, `patch_content` (target `template`).
  - `block:<id>`: `save_reuse_block`, `patch_content` (target `reuse_block`).
  - `option:align_custom_css`, `option:align_custom_js`: `save_global_code`.
  - `option:align_tracking_code`, `option:align_body_open_code`, `option:align_footer_html_code`: `save_code_slot`.
- Đăng ký CPT:
  - `public => false`, `show_ui => false`, `show_in_rest => false`, `can_export => false`, `exclude_from_search => true`, `supports => array()`.
  - Mảnh phụ dùng CPT `align_agent_bk_chunk` (<= 20 ký tự), cùng cấu hình.
- Ánh xạ cột (0 dòng `wp_postmeta`):

  | Cột | Giá trị |
  |---|---|
  | `post_title` | `object_key` |
  | `post_name` | `md5` nội dung gốc (chống trùng, có index) |
  | `post_parent` | ID đối tượng gốc (0 với option) |
  | `post_excerpt` | JSON: `tool`, `fields`, `size_raw`, `size_gz`, `chunks`, `home_url`, `channel`, `user_id`, `reason`, `js_functions[]` |
  | `post_content` | Mảnh 1 (gzip + base64, tối đa 512 KB) |
  | `post_mime_type` | `align-backup/v1` |
  | `post_status` | `private` hoặc `foreign` |

- Luồng `Align_Agent_Global_Vault::store()`:
  1. Lấy nội dung các trường sắp bị sửa (REQ-09).
  2. Nội dung gốc > 20 MB → trả `backup_too_large` (REQ-11).
  3. Hash trùng bản gần nhất của `object_key` → trả ID bản cũ (REQ-08).
  4. `gzcompress( $raw, 6 )` → `base64_encode()` → cắt 512 KB.
  5. Ghi bằng `wp_insert_post()`. Mảnh 2..n là post con.
  6. Đọc lại, ghép, giải nén, so hash. Sai → `wp_delete_post( $id, true )` CHỈ cho post vault vừa tạo, trả `backup_failed` (REQ-10).
     - Ghi chú: Lệnh cấm `wp_delete_post( $id, true )` trong `AGENTS.md` §4.3 áp dụng cho nội dung của user. Post vault là dữ liệu nội bộ của plugin. Ghi rõ ngoại lệ này vào `AGENTS.md`.
  7. Gọi `Align_Agent_Vault_Gc::enforce( $object_key )`.
- Dọn dẹp (`Align_Agent_Vault_Gc`, REQ-07):
  - Mỗi `object_key` giữ tối đa **10** bản mới nhất.
  - Toàn site tối đa 500 bản và 50 MB (`size_gz`). Vượt trần thì xóa bản cũ nhất, nhưng mỗi `object_key` giữ tối thiểu 3 bản.
  - Bản cũ hơn 7 ngày bị xóa (trừ 3 bản mới nhất mỗi đối tượng).
  - `home_url` khác site hiện tại → `post_status = foreign`, xóa sau 7 ngày (REQ-12).
  - Cron hook `align_agent_vault_gc`, lịch `daily`. Hủy lịch khi deactivate plugin.
- Kiểm tra sau ghi (REQ-14): `Align_Agent_Write_Executor` gọi `Align_Agent_Global_Vault::verify_after( $object_key, $expected_hash )`. Lệch thì `restore()` và trả `write_verify_failed`.
- Tool mới `includes/tools/site/class-agent-tools-backups.php` (group `site`):

  | Tool | Type | Scope | Capability | Danger | Approval |
  |---|---|---|---|---|---|
  | `list_backups` | read | local | `edit_theme_options` | no | no |
  | `search_backups` | read | local | `edit_theme_options` | no | no |
  | `diff_backup` | read | local | `edit_theme_options` | no | no |
  | `restore_backup` | write | global | `edit_theme_options` | yes | yes |

  - `search_backups( object_key, text )`: giải nén tối đa 10 bản của 1 `object_key` trong bộ nhớ.
  - `diff_backup( backup_id )`: diff theo dòng giữa bản backup và bản hiện tại, tối đa 200 dòng.
  - `restore_backup( backup_id )`:
    - Cấm bản `foreign`.
    - Gọi `store()` cho trạng thái hiện tại trước khi khôi phục.
    - Báo `base_changed` trên thẻ duyệt khi object đã đổi sau thời điểm backup.
    - Ghi qua `Align_Builder_Crud_Handler::save_page` (template/block) hoặc `Align_Agent_Option_Writer::write` (option).
- Nút `Purge Backups` (REQ-13): AJAX `align_agent_vault_purge`, nonce `align_agent_admin_nonce`, capability `manage_options`, đặt ở tab `Updates`. Code ở file view/handler mới, KHÔNG thêm vào `class-gateway-admin.php` (494 dòng).

### Giai đoạn 3: Bản đồ trang
- File mới `includes/tools/structure/class-agent-tools-render-map.php` (group `structure`, read, `edit_theme_options`).
- `inspect_render( url | post_id )` trả:

  | Khóa | Nội dung |
  |---|---|
  | `page` | `post_id`, `title`, `status`, `editor_lock` |
  | `language` | `lang`, `translations{code:id}`, `missing[]` |
  | `template` | slug template đang dùng, có `post-content` hay không |
  | `active_parts` | `{ header: tp_id, footer: tp_id }` theo area |
  | `unused_parts` | part có area `uncategorized` hoặc không được template tham chiếu, đánh dấu `legacy` |
  | `reuse_blocks` | danh sách `[reuse-block id]` |
  | `global_code` | `align_custom_css`/`align_custom_js` có hay không, kích thước |
  | `code_slots` | slot đang bật |
  | `clone` | clone nháp đang mở (`_align_agent_clone_of`) |
  | `vault` | số bản backup mới nhất của mỗi đối tượng ở trên |

- `find_in_render( url, selector | text )` trả tối đa 5 kết quả `{ source, field, snippet, line }`:
  - `source` thuộc `page:<id>`, `tp:<slug>`, `block:<id>`, `option:align_custom_js`, `option:align_custom_css`.
  - `snippet` tối đa 1.500 ký tự.
  - Với JS: tìm function có chứa selector, class hoặc id (regex `function name(` / `name = function` / `=>`). Trả tên function và đoạn thân.
  - KHÔNG đọc file PHP. KHÔNG đọc file trong theme, trừ nội dung block template mà `get_block_template()` trả về.
- `build_system_prompt()`: thêm 3 dòng.
  - `RENDER MODEL`: template → template part theo area → post-content → reuse block → global CSS/JS → code slots.
  - `DIAGNOSE-FIRST`: với mọi yêu cầu sửa HTML/CSS/JS hoặc sửa lỗi function JS, gọi `inspect_render` hoặc `find_in_render` trước khi ghi. Không đọc và không sửa PHP. Yêu cầu sửa PHP thì báo người dùng liên hệ developer.
  - `GLOBAL-SAFE`: trước khi ghi scope global, nêu `object_key` và phần sẽ đổi. Sau khi ghi, trả backup ID.
  - `class-agent-loop.php` đang 436 dòng. Đặt nội dung prompt mới trong file mới `includes/class-agent-prompt-rules.php`.

### Giai đoạn 4: Working Notes và tài liệu
- File mới `includes/class-agent-working-notes.php`:
  - User meta `align_agent_notes_{channel}`, tối đa 1.024 byte. Ghi đè bản cũ khi đầy.
  - Tool `save_working_note( text )` (read-type, không cần duyệt, group `content`) hoặc tự sinh từ kết quả `inspect_render`.
  - `build_system_prompt()` chèn dòng `WORKING NOTES: ...` khi có notes.
  - `/new` trên Telegram và nút `Clear` trên Chatbox xóa notes.
- Tài liệu: cập nhật `Align_Agent_Tools_Loader::MANIFEST`, `includes/tools/README.md` (bảng tool, tổng số tool), `includes/tools/site/README.md`, `includes/tools/structure/README.md`, `AGENTS.md` §4.3 (Vault, Backup Gate, ngoại lệ xóa post vault) và §5.

## 7. Danh Sách File

| File | Hành động |
|---|---|
| `includes/class-agent-undo-resolver.php` | Mới |
| `includes/class-agent-undo-adapters.php` | Sửa (dùng resolver, revert kiểm tra kết quả) |
| `includes/class-agent-write-executor.php` | Sửa (Backup Gate, gọi vault, verify sau ghi) |
| `includes/class-agent-change-set.php` | Sửa (L8) |
| `includes/tools/content/class-agent-tools-patch.php` | Sửa (undo spec theo `target_type`) |
| `includes/class-agent-global-vault.php` | Mới |
| `includes/class-agent-vault-gc.php` | Mới |
| `includes/tools/site/class-agent-tools-backups.php` | Mới |
| `includes/tools/structure/class-agent-tools-render-map.php` | Mới |
| `includes/class-agent-prompt-rules.php` | Mới |
| `includes/class-agent-working-notes.php` | Mới |
| `includes/class-agent-loop.php` | Sửa (gọi prompt rules + notes) |
| `views/` + handler AJAX purge | Mới (nút `Purge Backups`) |
| `align-agent.php` (bootstrap) | Sửa (require file mới, cron activate/deactivate) |
| `includes/tools/class-agent-tools-loader.php` | Sửa (`MANIFEST`) |
| `includes/tools/README.md`, `includes/tools/site/README.md`, `includes/tools/structure/README.md`, `AGENTS.md` | Sửa |

## 8. Ràng Buộc Bắt Buộc
- Mỗi file < 500 dòng.
- Comment, log, UI string 100% tiếng Anh. Log prefix `[Align Agent]`.
- Không SQL thô. Không ghi file vào theme/assets. Dùng `WP_Query`, `wp_insert_post`, `get_posts`.
- Không đọc và không sửa PHP qua agent.
- Thao tác global giữ nguyên luồng duyệt `Align_Agent_Approval_Policy::classify() === 'approve'`.
- Mọi ghi nội dung vẫn qua `guard_html_and_css` và `Align_Gateway_Validator::validate_markup()`.

## 9. Nghiệm Thu (Lệnh Kiểm Chứng)
Chạy bằng WP-CLI tại `app/public` (Local shell), hoặc qua Chatbox/Telegram.

| # | Kịch bản | Kết quả đúng |
|---|---|---|
| T1 | `save_template(tp_id=tp_header, css=...)`, sau đó Undo | `post_content` của `tp_header` có md5 bằng md5 trước khi sửa. |
| T2 | `patch_content(target_type=template, tp_id=tp_footer, edits=[...])`, sau đó Undo | Khôi phục đúng. Change set có `before` khác rỗng. |
| T3 | Ép snapshot rỗng (tp_id không tồn tại) | Trả `backup_failed`. DB không đổi. |
| T4 | Trang 1 MB, sửa 1 dòng, rồi Undo | Trang khôi phục đúng, không có chuỗi base64 trong `post_content`. |
| T5 | Sửa `tp_header` 12 lần với nội dung khác nhau | `list_backups(tp:header)` trả đúng 10 bản. |
| T6 | Lưu 2 lần cùng nội dung | Chỉ có 1 bản trong vault. |
| T7 | Nội dung nén 1,6 MB | 4 mảnh. `restore_backup` khôi phục đúng hash. |
| T8 | Đổi `home_url` giả lập | Bản cũ chuyển `foreign`, không có trong `list_backups`, `restore_backup` bị từ chối. |
| T9 | `restore_backup(#11)` | Có thẻ duyệt `danger`. Sau khi duyệt, có thêm bản backup của trạng thái trước khi khôi phục. |
| T10 | `inspect_render("/vi/")` | Trả `active_parts.header = tp_header`, `unused_parts` có `header-vi`, `language.missing` có `ja`. |
| T11 | `find_in_render("/vi/", ".lang-switcher")` | Trả nguồn `tp:header` và function JS liên quan. |
| T12 | Yêu cầu "sửa nút chuyển ngôn ngữ" qua Telegram | Agent xong trong ≤ 6 lượt tool. Không đọc PHP. Run Report có backup ID. |
| T13 | `php -l` mọi file sửa/mới, `wc -l` mọi file | Không lỗi cú pháp. Mọi file < 500 dòng. |
| T14 | Tools → Export (All content) | File XML không có `align_agent_backup`. |
