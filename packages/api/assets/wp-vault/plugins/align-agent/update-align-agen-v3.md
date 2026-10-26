# Align Agent v3 — Sửa Nội Dung An Toàn (Đợt 1)

> Bổ sung cho `update-align-agent-v1.md` (roadmap M0–M8) và `update-align-agent-v2.md` (tool spec & handler mapping).
> Nguồn: báo cáo `/joe_explore` ngày 2026-10-19 và câu trả lời của User trong cùng session.
> Trạng thái: **Kế hoạch — chờ duyệt.** Chưa viết code trước cổng phê duyệt.

---

## 1. Yêu cầu, ranh giới, nghiệm thu

- **Mục tiêu**: Chatbox sửa được HTML/CSS/JS (theo trang và toàn site) mà không mất dữ liệu. User thấy mọi thay đổi trước khi duyệt.
- **Nghiệm thu**:
  - Lưu page/template/reusable block không đổi status, không xoá CSS/JS nếu User không yêu cầu rõ.
  - AI sửa được 1 dòng CSS mà không gửi lại toàn bộ HTML.
  - Mọi thẻ duyệt hiển thị JS sắp ghi, kèm cảnh báo mẫu rủi ro.
  - Đọc/ghi được CSS/JS toàn site (`align_custom_css`, `align_custom_js`), có `danger: true` và bắt buộc duyệt.
- **Số tool sau đợt 1**: 49 → **52 luôn đăng ký** (tối đa 58 khi dùng Rank Math). Thêm `patch_content`, `get_global_code`, `save_global_code`.

### Quyết định đã chốt

| Chủ đề | Quyết định | Nguồn |
|---|---|---|
| Mức nguy hiểm CSS/JS toàn site | `danger: true`, bắt buộc duyệt | User, câu 1 = A |
| Quản lý plugin/theme (REQ-10) | Loại vĩnh viễn khỏi phạm vi | User, câu 2 |
| Hotfix status `save_page` | Sửa ngay, tách riêng. **Đã sửa trong working tree, đã test, chưa commit.** | User, câu 3 = A |
| Commit hotfix riêng | Commit riêng (Khuyến nghị; áp dụng vì User chuyển sang `/joe_plan`) | Vòng 2, câu 1 |
| Phạm vi đợt 1 | An toàn JS + REQ-02 + REQ-01 | Vòng 2, câu 2 |
| `patch_content` khớp nhiều chỗ | Từ chối, trả `patch_ambiguous_match` | Vòng 2, câu 3 |

### Non-goals (đợt 2 hoặc sau)
- REQ-03 menu, REQ-04 WP settings, REQ-05 post meta, REQ-06 dịch, REQ-07 export/import, REQ-08 undo qua audit log, REQ-09 redirect dự phòng.
- REQ-10 quản lý plugin/theme: **không bao giờ làm**.
- Ghi `align_tracking_code`: chỉ đọc ở đợt 1 (xem Câu hỏi chặn 2).
- Telegram gateway: chỉ làm cho chatbox.

### File ảnh hưởng (đã xác minh tồn tại, trừ file ghi MỚI)

| File | Số dòng hiện tại | Mốc |
|---|---|---|
| `includes/tools/content/class-agent-tools-pages.php` | 499 | M0, M2, M3 |
| `includes/tools/content/class-agent-tools-posts.php` | 497 | M2, M3 |
| `includes/tools/structure/class-agent-tools-templates.php` | 479 | M1, M2, M3 |
| `includes/class-agent-tool-registry.php` | 236 | M3 |
| `assets/chatbox-render.js` | 384 | M3 |
| `assets/chatbox-content.css` | — | M3 |
| `includes/class-agent-content-guard.php` | MỚI | M3 |
| `includes/class-agent-patch-engine.php` | MỚI | M4 |
| `includes/tools/content/class-agent-tools-patch.php` | MỚI | M4 |
| `includes/tools/site/class-agent-tools-global-code.php` | MỚI | M5 |
| `includes/tools/class-agent-tools-loader.php` | 63 | M4, M5 |
| `align-agent.php` | — | M3, M4 (require class helper mới) |
| `includes/tools/README.md`, `includes/tools/content/README.md`, `includes/tools/structure/README.md`, `includes/tools/site/README.md`, `README.md` | — | Mỗi mốc, cùng commit |

---

## 2. Phát hiện làm căn cứ cho kế hoạch

### Điểm yếu (Hạn chế / Rủi ro)
- **BUG-A (đã sửa trong working tree)**: `save_page` mặc định `status = draft`, gỡ trang đang live (`class-agent-tools-pages.php:351`, trước khi sửa).
- **BUG-B (mới phát hiện, mất dữ liệu)**: `save_template` gán `css`/`js` = `''` khi AI không gửi (`class-agent-tools-templates.php:278-280`). `save_reuse_block` cũng vậy (`:439-441`).
  - Theme sau đó xoá file asset: `save_single_asset()` gọi `unlink( $file )` khi code rỗng (`themes/.../builder/class-builder-assets.php`, nhánh `elseif ( file_exists( $file ) )`).
  - Hậu quả: lệnh "đổi text footer" âm thầm xoá CSS/JS của footer.
- **Điểm mù JS**: `guard_html_and_css()` chỉ kiểm HTML và CSS (`class-agent-tools-pages.php:251`). Thẻ duyệt chỉ render `preview_html` / `preview_css` (`assets/chatbox-render.js:255-256`, `class-agent-tool-registry.php:175-176`).
- **Bắt gửi lại toàn bộ HTML**: Schema của cả 4 tool save đặt `html` là required. Code page/post đã tự lấy lại HTML cũ khi thiếu (`class-agent-tools-pages.php:343`, `class-agent-tools-posts.php:275`).
- **Giới hạn dòng**: `pages.php` (499) và `posts.php` (497) sát giới hạn 500. Logic mới bắt buộc đặt ở file mới.
- **Working tree trộn lẫn**: `class-agent-tools-pages.php` và `README.md` chứa cả hotfix lẫn thay đổi refactor v2 chưa commit. Muốn commit hotfix riêng thì phải tách (xem Câu hỏi chặn 1).

### Điểm mạnh (Ưu điểm / Khả thi)
- Mọi tool save đã đi qua `guard_html_and_css`, editor lock, revision và approval. `patch_content` gọi lại các tool này, không mở đường ghi mới.
- `Align_Gateway_Pages::extract_decoupled_parts()` (`includes/class-gateway-pages.php:306`), `tool_get_template()` (`templates.php:252`), `tool_get_reuse_block()` (`templates.php:352`) đã trả HTML/CSS/JS. Dùng làm fallback cho BUG-B và làm phần đọc của patch.
- Code toàn site lưu trong option chuẩn. `update_option()` kích hoạt hook minify của theme (`Align_Custom_CSS_JS`, `core/includes/core/class-align-custom-css-js.php`). Không cần SQL thô, không ghi file trực tiếp.
- `Align_Agent_Audit_Log::snapshot_options()` (`includes/class-agent-audit-log.php:112`) đã hỗ trợ snapshot option để rollback.

### Giải pháp đề xuất & Lý do
- *Giải pháp:* Sửa BUG-B trước mọi tool mới. -> *Lý do:* `patch_content` gọi `save_template`, nên sẽ thừa hưởng lỗi mất dữ liệu.
- *Giải pháp:* Chuyển `guard_html_and_css` sang class mới `Align_Agent_Content_Guard`, giữ 1 dòng delegate trong `pages.php`. -> *Lý do:* Giải phóng dòng cho `pages.php`. Gom kiểm HTML/CSS/JS về 1 chỗ.
- *Giải pháp:* Thêm `preview_js` tập trung trong `summarize_write_action()`. -> *Lý do:* Phủ mọi tool ghi, không phải sửa từng summary callback.
- *Giải pháp:* Xây `patch_content` trên các tool save sẵn có. -> *Lý do:* Tái dùng guard, lock, revision, audit. Không có đường ghi thứ hai.

---

## 3. Mốc thực thi

Mọi lệnh kiểm chứng CLI dùng socket DB của AignRealBuilder (xem memory `wp-cli-local-db-socket`):

```bash
S="$HOME/Library/Application Support/Local/run/vt_z2ZvJJ/mysql/mysqld.sock"
wp --path=/Users/agent/Documents/LocalWP/aignrealbuilder/app/public --exec="define('DB_HOST','localhost:$S');" eval-file <test.php>
```

Quy ước script test:
- Dòng đầu assert `home_url() === 'http://aignrealbuilder.local'`.
- Require `get_template_directory() . '/core/inc/builder/class-builder-rest-api.php'` khi thiếu `Align_Builder_Crud_Handler`.
- Tạo item test với `post_content` rỗng để không bị khoá Gutenberg.
- Cuối test chuyển item vào thùng rác bằng `wp_trash_post()`.

### M0: Commit hotfix BUG-A
- **File**: `includes/tools/content/class-agent-tools-pages.php`, `README.md`.
- **Hành động**: Chỉ commit các hunk status: `fix(tools): keep current post status in save_page when status is omitted`. Cách tách theo Câu hỏi chặn 1.
- **Kiểm chứng**: `git show --stat HEAD` và `git show HEAD | grep -c "sanitize_status"`.
- **Kỳ vọng**: Commit chỉ chạm các dòng status trong 2 file trên. Test ngày 2026-10-19 vẫn pass: không gửi status → `publish`; gửi `draft` → `draft`.

### M1: Hotfix BUG-B (giữ CSS/JS của template và reusable block)
- **File**: `includes/tools/structure/class-agent-tools-templates.php`.
- **Hành động**:
  - `tool_save_template`: thiếu `css`/`js`/`html` thì đọc giá trị hiện tại qua `self::tool_get_template()` và dùng lại.
  - `tool_save_reuse_block`: tương tự qua `self::tool_get_reuse_block()`. Thay fallback `(string) $post->post_content` bằng `html` đã tách.
  - Gửi chuỗi rỗng rõ ràng (`"css": ""`) vẫn xoá asset. Chỉ khi **thiếu key** mới giữ nguyên.
  - Log `[Align Agent] save_template kept existing CSS/JS for <tp_id>`.
- **Kiểm chứng**: `php -l` và test CLI: tạo reusable block với `css=".t{color:red}"`, gọi `tool_save_reuse_block` chỉ với `html`, rồi gọi `tool_get_reuse_block`.
- **Kỳ vọng**: `css` vẫn chứa `.t{color:red}`, file asset vẫn tồn tại. Gọi với `"css": ""` thì xoá.

### M2: Bỏ bắt buộc gửi lại toàn bộ HTML (REQ-02a)
- **File**: `class-agent-tools-pages.php` (`save_page`), `class-agent-tools-posts.php` (`save_post`), `class-agent-tools-templates.php` (`save_template`, `save_reuse_block`).
- **Hành động**:
  - Bỏ `html` khỏi `required`. Giữ `post_id` / `tp_id`.
  - Cập nhật description: "Omitted html/css/js keep their current values."
  - Từ chối lệnh không có trường nào trong `html`/`css`/`js`/`title`/`status`, trả lỗi `nothing_to_update`.
- **Kiểm chứng**: `wp eval` dump `Align_Agent_Tool_Registry::get_openai_tools_schema()`, lọc 4 tool. Test CLI gọi `save_page` chỉ với `css`.
- **Kỳ vọng**: Không còn `html` trong `required`. HTML của trang giữ nguyên, CSS được cập nhật.

### M3: An toàn JS cho guard và thẻ duyệt
- **File**:
  - MỚI `includes/class-agent-content-guard.php` (`Align_Agent_Content_Guard`).
  - `class-agent-tools-pages.php`: `guard_html_and_css()` thành delegate, thêm tham số tuỳ chọn thứ 3 `$js`.
  - Nơi gọi trong `pages.php`, `posts.php`, `templates.php` truyền thêm `$js`.
  - `class-agent-tool-registry.php`: `summarize_write_action()` thêm `preview_js` và `js_warnings`.
  - `assets/chatbox-render.js`, `assets/chatbox-content.css`, `align-agent.php` (require).
- **Hành động**:
  - **Chặn cứng** (lỗi `forbidden_js_payload`) khi JS chứa `<script` hoặc `</script`. Hai chuỗi này phá vỡ thẻ bao script.
  - **Cảnh báo** (không chặn, hiển thị trên thẻ): `eval(`, `new Function(`, `document.write(`, `document.cookie`, `localStorage`, và `fetch(` / `XMLHttpRequest` / `import(` tới URL tuyệt đối khác host.
  - **Thẻ duyệt**: nút "View JS" (`data-action="toggle-js"`, `aria-expanded`) hiện JS trong `<pre><code>` qua `textContent`. JS không bao giờ được thực thi. Danh sách cảnh báo có `role="alert"`.
  - **Log**: `[Align Agent] JS guard: <n> warnings for <tool>`.
- **Kiểm chứng**:
  - `php -l` mọi file đã sửa.
  - CLI: `Align_Agent_Content_Guard::check_js( "</script><b>" )` trả `forbidden_js_payload`. `check_js( "eval('1')" )` trả 1 cảnh báo.
  - `/joe_verify`: trong chatbox yêu cầu "thêm console.log vào trang X". Xác nhận thẻ có nút "View JS" và hiển thị code.
- **Kỳ vọng**: Mã lỗi và số cảnh báo đúng như trên. JS hiện trên thẻ trước khi duyệt. `pages.php` ≤ 500 dòng.

### M4: Tool `patch_content` (REQ-02b)
- **File**:
  - MỚI `includes/class-agent-patch-engine.php` (`Align_Agent_Patch_Engine`, logic chuỗi thuần, tái dùng ở M5).
  - MỚI `includes/tools/content/class-agent-tools-patch.php` (`Align_Agent_Tools_Patch`).
  - `includes/tools/class-agent-tools-loader.php` (`MANIFEST`), `includes/tools/README.md`, `includes/tools/content/README.md`, `align-agent.php` (require engine).
- **Hợp đồng tool**:
  - `target_type`: `page` | `post` | `template` | `reuse_block` (bắt buộc).
  - `post_id` (page, post, reuse_block) hoặc `tp_id` + `template_type` tuỳ chọn (template).
  - `edits`: mảng 1–10 phần tử, mỗi phần tử `{ field: html|css|js, find: string, replace: string }`.
  - Cờ registry: `type: write`, `group: content`, `capability: edit_posts` (target template kiểm thêm `edit_theme_options` trong callback), `requires_builder: true`. `danger: true` chỉ với target template (đặt trong summary).
- **Quy tắc engine**:
  - Áp từng edit theo thứ tự trên bản sao trong bộ nhớ.
  - Mỗi `find` phải xuất hiện **đúng 1 lần**. 0 lần → `patch_no_match`. Hơn 1 lần → `patch_ambiguous_match` kèm `count`.
  - `find` rỗng → `patch_invalid_edit`.
  - Sau khi patch, gọi tool save tương ứng (`tool_save_page` / `tool_save_post` / `tool_save_template` / `tool_save_reuse_block`) với đủ 3 trường và **không** truyền `status`.
- **Thẻ duyệt**: mỗi edit hiện 1 đoạn trước/sau (±80 ký tự quanh vị trí khớp), kèm `preview_html`, `preview_css`, `preview_js` của kết quả sau patch.
- **Kiểm lại khi duyệt**: callback đọc lại nội dung và áp lại edit. Nội dung đã đổi trong lúc chờ duyệt thì trả `patch_no_match`, không ghi dữ liệu cũ.
- **Kiểm chứng**: `php -l`. Test CLI trên trang tạm có `css=".a{color:red}.b{color:red}"`:
  1. `find=".a{color:red}"` → thành công, `.b` giữ nguyên.
  2. `find="color:red"` → `patch_ambiguous_match`, `count=2`.
  3. `find="zzz"` → `patch_no_match`.
  4. Status giữ `publish`. `get_revisions` tăng 1 revision.
- **Kỳ vọng**: Đúng như danh sách trên. `get_openai_tools_schema()` chứa `patch_content`.

### M5: Tool CSS/JS toàn site (REQ-01)
- **File**: MỚI `includes/tools/site/class-agent-tools-global-code.php` (`Align_Agent_Tools_Global_Code`), loader `MANIFEST`, `includes/tools/README.md`, `includes/tools/site/README.md`.
- **Tool**:
  - `get_global_code` (read, `manage_options`): trả `css` (`align_custom_css`), `js` (`align_custom_js`), `tracking_code` (chỉ đọc, `align_tracking_code`), kèm kích thước byte.
  - `save_global_code` (write, `manage_options`, `danger: true`):
    - Nhận `css` và/hoặc `js` đầy đủ, **hoặc** `edits` (cùng engine M4, `field: css|js`). Bắt buộc có ít nhất 1.
    - CSS chạy qua `Align_Gateway_Validator::validate_markup( '', $css )`. JS chạy qua `Align_Agent_Content_Guard::check_js()`.
    - Bắt buộc `current_user_can( 'unfiltered_html' )`, thiếu thì trả `unfiltered_html_required`. Lý do: thiếu quyền này, sanitizer của theme chạy `wp_kses_post` và làm hỏng code.
    - Snapshot trước bằng `Align_Agent_Audit_Log::snapshot_options( array( 'align_custom_css', 'align_custom_js' ) )`, rồi ghi bằng `update_option()` để hook minify của theme chạy.
    - Log `[Align Agent] save_global_code updated <keys>`.
  - Đăng ký: chỉ khi `class_exists( 'Align_Custom_CSS_JS' )`. Thiếu class thì log và bỏ qua.
- **Kiểm chứng**: `php -l`. Test CLI: backup giá trị hiện tại, chạy `save_global_code` với `edits` trên CSS, kiểm `get_option( 'align_custom_css' )` và `get_option( 'align_custom_css_minified' )`, rồi khôi phục backup. `/joe_verify`: thẻ có viền đỏ, `role="alert"`, hiển thị JS.
- **Kỳ vọng**: Option được cập nhật, option minified được tạo lại. Audit log có thêm 1 bản ghi kèm snapshot. Cuối test khôi phục giá trị gốc.

### M6: QC & nghiệm thu
- **Hành động**: Chạy song song đội QC (Security, UI/UX, Logic, Performance) trên diff M1–M5. Sau đó `/joe_verify` end-to-end trong chatbox:
  1. Patch CSS trên trang đang publish.
  2. Chỉ sửa HTML footer, xác nhận CSS vẫn còn.
  3. Sửa CSS toàn site.
- **Kiểm chứng**: `find includes -name '*.php' -exec php -l {} \;` và `wc -l` mọi file PHP đã sửa (tất cả ≤ 500).
- **Kỳ vọng**: Mọi QC pass, 3 luồng pass, tài liệu ghi đúng 52 tool.

---

## 4. Giả định đã chọn
- Thiếu key = giữ giá trị cũ; gửi `""` rõ ràng = xoá. -> Khớp hành vi sẵn có của `save_page`/`save_post`.
- Mẫu JS rủi ro chỉ cảnh báo, không chặn. -> Admin dùng `localStorage`, `fetch` hợp lệ. User quyết định trên thẻ duyệt.
- `patch_content` xử lý `template_type` giống `get_template` / `save_template`. -> Cùng handler, không có hợp đồng mới.
- `get_global_code` / `save_global_code` thuộc group `site`, `requires_builder: false`, chỉ đăng ký khi có `Align_Custom_CSS_JS`. -> Option thuộc Align theme, không thuộc Builder.
- Mỗi mốc (M0–M5) 1 commit, tài liệu cập nhật trong cùng commit. -> AGENTS.md §5.3.

## 5. Câu hỏi chặn cần User trả lời
1. Tách hotfix M0 khỏi refactor v2 chưa commit (cùng file) thế nào? -> A: Commit toàn bộ WIP v2 **không gồm** hunk hotfix trước (tạm đảo patch hotfix, commit, áp lại), rồi commit hotfix riêng (Khuyến nghị) | B: Gộp 1 commit "v2 refactor + save_page status fix" -> Chọn sai B: hotfix không revert hoặc cherry-pick riêng được.
2. `save_global_code` có ghi `align_tracking_code` không? Tracking code vốn chứa thẻ `<script>`. -> A: Không, chỉ đọc ở đợt 1 (Khuyến nghị) | B: Cho ghi, có `danger: true` -> Chọn sai B: AI có đường chèn `<script>` bên thứ ba lên toàn site, vượt quy tắc "cấm `<script>` trong HTML".

Duyệt kế hoạch để tiến hành M0? (`ok` = duyệt + dùng phương án Khuyến nghị)
