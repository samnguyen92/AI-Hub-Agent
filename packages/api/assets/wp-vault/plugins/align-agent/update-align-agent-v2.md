# Align Agent v2 — Tool Spec & Handler Mapping

> Bổ sung cho `update-align-agent-v1.md` (roadmap M0–M8).
> File này chốt **Agent gọi handler nào** cho từng tool, để Coder không tự viết đường ghi mới.
> Phạm vi: Chatbox. Telegram để sau.

---

## 1. Nguyên tắc thực thi

1. **Gọi nội bộ, không gọi HTTP:** tạo `WP_REST_Request` rồi gọi thẳng static handler của Align Theme. Mẫu tham chiếu là `tool_save_page` (`includes/class-agent-tools-pages.php:311`).
2. **SSOT = Database:** page, post, CPT, `wp_block`, `wp_template`, `wp_template_part` đều lưu ở `wp_posts.post_content`. File `.html` trong `templates/` và `parts/` chỉ là mirror do `persist_template()` tự ghi.
3. **Cấm:** raw SQL, ghi trực tiếp file `.html`, ghi trực tiếp asset trong `assets/builder/`.
4. **Mọi tool ghi:** `needs_approval` → guard `<script>` → `Align_Gateway_Validator::validate_markup()` → kiểm editor lock → gọi handler → audit log (M0).
5. **Không có Align Builder:** tool cần Builder (`requires_builder = true`) không được đăng ký. Tool dùng WP core vẫn chạy.

---

## 2. Align Theme API đã có (`align-builder/v1`)

| Việc | Route | Handler | Tham số chính |
|---|---|---|---|
| Đọc nội dung | `GET /page-content` | `Align_Builder_Content_Handler::get_page_content` | `id` hoặc `tp_id`, `template_type` |
| Ghi nội dung | `POST /save-page` | `Align_Builder_Crud_Handler::save_page` | `post_id` hoặc `tp_id`, `html`, `css`, `js`, `template_type` |
| Tạo item | `POST /create-item` | `Align_Builder_Crud_Handler::create_item` | `type` (`page` / `template` / `template_part` / `reusable_block`), `title`, `slug` |
| Đổi tên | `POST /rename-item` | `Align_Builder_Crud_Handler::rename_item` | `tp_id` / `post_id`, `title` |
| Xóa item | `POST /delete-item` | `Align_Builder_Crud_Handler::delete_item` | `tp_id` / `post_id` |
| Revision | `GET /revisions` | `Align_Builder_Revision_Handler::get_revisions` | `post_id` hoặc `tp_id` |
| Rollback | `POST /restore-revision` | `Align_Builder_Revision_Handler::restore_revision` | `revision_id`, `tp_id` |
| Đổi editor mode | `POST /switch-editor-mode` | `Align_Editor_Mode_Lock::rest_switch_editor_mode` | — (Agent không dùng) |

Hành vi quan trọng:
- `save_page()` với `tp_id` → `Align_Builder_Persistence_Service::persist_template()`:
  1. Tách `<style>` / `<script>` khỏi HTML.
  2. `Align_Builder_Assets::save_external_assets()` ghi `style-part-{slug}.css` / `script-part-{slug}.js`.
  3. `wp_update_post()` vào `wp_template_part` / `wp_template` (SSOT).
  4. `atomic_file_put_contents()` mirror ra `{child}/parts|templates/{slug}.html`.
- Template khóa Gutenberg → `WP_Error( 'editor_mode_locked' )`, HTTP 409.

---

## 3. Tool spec theo milestone

Ký hiệu: **R** = read, **W** = write (`needs_approval`), **B** = `requires_builder`.

### M1 — Page / Post / CPT

| Tool | Loại | Handler / Hàm | Ghi chú |
|---|---|---|---|
| `list_post_types` | R | `get_post_types( array( 'public' => true ), 'objects' )` | Loại `attachment`, `wp_*` |
| `list_posts` | R | `WP_Query` với `post_type`, `post_status`, `s`, `paged` | Giữ alias `list_pages` |
| `get_post` | R | `Align_Builder_Content_Handler::get_page_content` (B) / `get_post()` fallback | Giữ alias `get_page` |
| `create_post` | W, B | `create_item` (page) hoặc `wp_insert_post()` (post/CPT) + `save_page` | Mặc định `draft` |
| `save_post` | W, B | `Align_Builder_Crud_Handler::save_page` với `post_id` | Nới check `post_type` sang post/CPT/`wp_block` |
| `set_post_status` | W | `wp_update_post( post_status )` | `future` bắt buộc có `post_date` |
| `trash_post` | W | `wp_trash_post()` | Cấm `wp_delete_post( $id, true )` |
| `restore_post` | W | `wp_untrash_post()` | — |
| `duplicate_post` | W, B | `get_page_content` + `create_post` + copy meta SEO | Bản sao luôn `draft` |
| `update_post_attributes` | W | `wp_update_post()` (slug, parent, excerpt, date, author) + `update_post_meta( '_wp_page_template' )` | Slug đổi → gọi `rename_item` cho page Builder |

### M2 — Taxonomy

| Tool | Loại | Hàm |
|---|---|---|
| `list_terms` | R | `get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) )` |
| `create_term` | W | `wp_insert_term()` |
| `update_term` | W | `wp_update_term()` |
| `delete_term` | W | `wp_delete_term()` (chỉ khi `count = 0`, ngược lại báo user) |
| `assign_terms` | W | `wp_set_object_terms( $post_id, $terms, $tax, $append )` |

### M3 — Template / Template Part / Reuse Block

| Tool | Loại | Handler | Ghi chú |
|---|---|---|---|
| `list_templates` | R | `get_block_templates( array(), 'wp_template' )` + `'wp_template_part'` | Trả `tp_id`, `slug`, `area`, `source` (`custom` = có trong DB, `theme` = chỉ có file) |
| `get_template` | R, B | `Align_Builder_Content_Handler::get_page_content` với `tp_id` + `template_type` | Trả HTML + CSS + JS |
| `save_template` | W, B | `Align_Builder_Crud_Handler::save_page` với `tp_id` + `template_type` | Card cảnh báo "ảnh hưởng toàn site" |
| `list_reuse_blocks` | R | `WP_Query( post_type = wp_block )` | Trả `id`, `slug`, shortcode `[reuse-block id="N"]` |
| `get_reuse_block` | R, B | `get_page_content` với `id` | — |
| `create_reuse_block` | W, B | `create_item( type = reusable_block )` + `save_page` | — |
| `save_reuse_block` | W, B | `save_page` với `post_id` | Card liệt kê số trang đang nhúng block |
| `get_revisions` (mở rộng) | R, B | `Align_Builder_Revision_Handler::get_revisions` + `tp_id` | — |
| `restore_revision` (mở rộng) | W, B | `Align_Builder_Revision_Handler::restore_revision` + `tp_id` | — |
| `set_homepage` | W | `update_option( 'show_on_front' / 'page_on_front' / 'page_for_posts' )` | Snapshot option trước khi ghi |
| `update_site_identity` | W | `update_option( 'blogname' / 'blogdescription' / 'site_icon' )`, `set_theme_mod( 'custom_logo' )` | Snapshot |

**Luồng sửa menu (ví dụ "Thêm menu Blog vào header"):**
1. `list_templates` → tìm `tp_header`.
2. `get_template( tp_header )` → HTML + CSS + JS hiện tại.
3. LLM sửa HTML, thêm link ở **cả** desktop (`.nav-link-btn`) và mobile drawer (`.mobile-nav-link`). Không đụng `#mega-menu-panel` nếu user không yêu cầu.
4. `save_template` → approval card (preview + cảnh báo toàn site).
5. User duyệt → `save_page( tp_id )` → `persist_template()` → DB + asset + mirror.
6. Sai → `get_revisions( tp_id )` → `restore_revision`.

**System prompt bổ sung cho M3:**
- Header/Footer là template part. Sửa qua `get_template` / `save_template`.
- Menu là HTML tĩnh trong header. Luôn cập nhật desktop và mobile cùng lúc.
- Giữ nguyên `data-component-shortcode`, `[reuse-block ...]`, block `wp:align/*`.

### M4 — SEO / Redirect / 404

| Tool | Loại | Handler | Điều kiện đăng ký |
|---|---|---|---|
| `save_seo_*` (mở rộng) | W | `Align_Agent_Seo_Adapters` hiện có | Nhận mọi `post_type` public |
| `list_redirects` | R | Đọc qua `Align_Redirection_Importer` | provider = `rank_math` và `Align_Redirection_Importer::is_rank_math_active()` |
| `create_redirect` | W | Rank Math Redirections API (verify class trước khi code) | Như trên |
| `delete_redirect` | W | Như trên | Như trên |
| `list_404_logs` | R | `get_option( Align_404_Collector::OPTION_KEY )` | `class_exists( 'Align_404_Collector' )` |
| `create_redirect_from_404` | W | Tái dùng logic `Align_404_Collector::ajax_create_redirect_from_404` (tách phần core ra hàm gọi được) | Rank Math active |

Guard dòng 1 của mọi hàm ghi redirect: trả `plugin_not_active` nếu Rank Math tắt.
Mục cần verify trước khi code: `ajax_*` dùng `wp_send_json_*`, nên Agent không gọi thẳng được. Phải tìm hàm core bên trong hoặc dùng API của Rank Math.

### M5 — Media

| Tool | Loại | Hàm |
|---|---|---|
| `update_media_meta` | W | `update_post_meta( '_wp_attachment_image_alt' )`, `wp_update_post( post_title / post_excerpt / post_content )` |
| `trash_media` | W | `wp_trash_post()` (chỉ khi `MEDIA_TRASH` bật; ngược lại từ chối, không xóa vĩnh viễn) |

`trash_media` phải kiểm ảnh đang được dùng: `_thumbnail_id` và URL trong `post_content`.

### M6 — Comments / Users

| Tool | Loại | Hàm |
|---|---|---|
| `list_comments` | R | `get_comments( status, post_id, number )` |
| `moderate_comment` | W | `wp_set_comment_status( $id, 'approve' / 'hold' / 'spam' / 'trash' )` |
| `reply_comment` | W | `wp_insert_comment()` với `comment_parent`, `user_id = current user` |
| `list_users` | R | `get_users( role, search )` (không trả `user_pass`, `user_activation_key`) |
| `create_user` | W | `wp_insert_user()` với mật khẩu ngẫu nhiên + `wp_new_user_notification( $id, null, 'user' )` |
| `update_user_role` | W | `( new WP_User( $id ) )->set_role( $role )` |
| `update_user_profile` | W | `wp_update_user()` (không nhận `user_pass`) |
| `send_password_reset` | W | `retrieve_password( $user_login )` |
| `delete_user` | W | `wp_delete_user( $id, $reassign )`, `$reassign` bắt buộc |

Guard bắt buộc trong code (không chỉ prompt):
- `$id === get_current_user_id()` → chặn hạ quyền và xóa.
- Admin cuối cùng (`count( get_users( role = administrator ) ) <= 1`) → chặn hạ quyền và xóa.
- Role phải nằm trong `wp_roles()->get_names()`.
- Tool output không bao giờ chứa mật khẩu hay link reset.
- Tạo hoặc nâng lên `administrator` → approval card có cờ `danger: true`.
- Capability theo tool: `create_users`, `promote_users`, `edit_users`, `delete_users`.

### M7 — Vận hành

| Tool | Loại | Hàm |
|---|---|---|
| `flush_cache` | W | Gọi handler của `/align-agent/v1/cache/flush` (`includes/class-gateway-router.php:53`) |
| `site_overview` | R | `wp_count_posts()`, `wp_count_comments()`, 404 log mới, `get_core_updates()` |

---

## 4. Thay đổi trong code hiện có

| File | Thay đổi |
|---|---|
| `includes/class-agent-tool-registry.php` | Thêm `requires_builder`, `capability`, `group`, `danger` vào `register()`. `execute()` kiểm capability, chỉ chặn tool có `requires_builder` |
| `includes/class-agent-tools-pages.php` | Bỏ check cứng `'page' === $post->post_type`. Tách guard dùng chung (`guard_html_and_css`) ra helper. `get_revisions` / `restore_revision` nhận `tp_id` |
| `includes/class-agent-loop.php` | `MAX_CONTINUE_TURNS` = 12. System prompt thêm dòng M3 |
| `includes/class-agent-chat-controller.php` | `handle_approve` ghi audit log |
| `assets/chatbox-render.js` | Approval card hỗ trợ `danger` (viền đỏ, `role="alert"`) |

---

## 5. Checklist verify trước khi code

- [ ] `get_page_content( tp_id )` khi template chỉ có file, chưa có bản ghi DB: trả gì?
- [ ] `restore_revision` cho `wp_template_part`: revision có được tạo khi `persist_template()` chạy `wp_update_post()` không?
- [ ] `save_page()` với post/CPT: dynamic fallback có hoạt động khi post chưa có block `wp:align/custom-html` không?
- [ ] Rank Math redirect: class/hàm public nào tạo redirect mà không qua AJAX?
- [ ] `Align_Editor_Mode_Lock::is_locked_to()` có áp dụng cho post/CPT không?
