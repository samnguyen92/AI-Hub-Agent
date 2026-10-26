# Align Agent v1 — Full Site Control via Chatbox

> Phạm vi: WP Admin Chatbox (`Align_Agent_Loop` + `Align_Agent_Tool_Registry`).
> Telegram tạm hoãn. Sau này Telegram sẽ dùng lại toàn bộ tool qua `Align_Agent_Loop::run_step()`.

---

## 1. Hiện trạng

### Tools đã có

| Nhóm | Tool | Loại |
|---|---|---|
| Pages | `list_pages`, `get_page`, `get_revisions`, `validate_markup` | read |
| Pages | `save_page`, `create_page`, `restore_revision` | write (`needs_approval`) |
| Media | `list_media` | read |
| Media | `set_featured_image`, `attach_media`, `sideload_media` | write |
| SEO | `check_seo_output` | read |
| SEO | 1 trong `save_seo_rank_math` / `save_seo_yoast` / `save_seo_theme_default` | write |

### Hạ tầng đã có
- REST: `/chat/step`, `/chat/approve`, `/chat/history`, `/chat/reset`, `/chat/session`.
- Dual Auth, rate limit, upload M1, approval queue (`queue_pending_write` / `pop_pending_write`).

### Hạn chế hiện tại
- Mọi tool chỉ nhận `post_type = page` (`includes/class-agent-tools-pages.php:176`, `:314`).
- `execute()` chặn mọi write tool khi Builder tắt (`includes/class-agent-tool-registry.php:183`), kể cả SEO và Media.
- Không có kiểm quyền theo từng tool. Chỉ kiểm `manage_options` ở controller.
- Không có audit log và snapshot cho thao tác ghi không phải page.
- `MAX_CONTINUE_TURNS = 6`.

---

## 2. Phát hiện từ Align Theme (core)

| Đối tượng | Lưu trữ | Đường ghi |
|---|---|---|
| Page | `wp_posts` (`page`) | `Align_Builder_Crud_Handler::save_page()` |
| Post / CPT | `wp_posts` (`post`, CPT như `fluent-products`) | `save_page()` (dynamic fallback cho post/CPT) |
| Header / Footer | `wp_template_part` (`tp_header`, `tp_footer`) | `Align_Builder_Persistence_Service::persist_template()` |
| Template FSE | `wp_template` (`home`, `page`, `single`, `archive`, `404`, ...) | `persist_template()` |
| Reuse Block | `wp_block`, nhúng `[reuse-block id="N"]` | `create_item()` + `save_page()` |
| Asset | `align-child/assets/builder/style-{page,part,template,block}-{slug}.css` + `script-*.js` | Persistence Service |
| Menu | HTML tĩnh trong `post_content` của `wp_template_part` header (DB = SSOT). Không dùng `wp_navigation` / `register_nav_menus` | Sửa qua `save_template` trên `tp_header` |
| Redirect | Bảng `rank_math_redirections`, chỉ khi Rank Math bật (`Align_Redirection_Importer`) | Theo provider SEO |
| 404 log | Option của `Align_404_Collector` | `record_404`, `ajax_create_redirect_from_404` |
| Form | Theme không lưu submission | Ngoài phạm vi |

Lưu ý:
- SSOT của template / template part là Database (`wp_posts.post_content`). `persist_template()` ghi DB bằng `wp_update_post()` trước, sau đó mirror ra `{child}/templates|parts/{slug}.html` bằng `atomic_file_put_contents()`. File `.html` chỉ là bản sao/fallback.
- Agent không bao giờ ghi trực tiếp file `.html` hay chạy raw SQL. Mọi thao tác ghi đi qua `persist_template()`.
- `save_page()` với `tp_id` trả `editor_mode_locked` (409) khi template khóa Gutenberg.
- Tham chiếu: `themes/Align-Real-Builder/align/docs/coding-with-builder/content-types.md`, `align-child/docs/header-navigation-active-system.md`.

---

## 3. Quyết định đã chốt

1. Quản lý toàn bộ: blog, page và mọi CPT public.
2. Header / Footer / Template / Reuse Block xử lý theo Align Theme core (bảng mục 2).
3. SEO và Redirect đi đúng flow plugin SEO đang chọn (`align_agent_seo_provider`).
4. Không quản lý plugin.
5. Quản lý user đầy đủ: tạo account, set role (bao gồm administrator).
6. Xóa nội dung = chỉ trash. Không xóa vĩnh viễn.
7. Đa ngôn ngữ (`Align_Language_Manager`) để sau.
8. Thứ tự: M0 → M1 → M2 → M3 → M4 → M5 → M6 → M7 → M8.

---

## 4. Milestones

### M0 — Nền tảng Tool Registry
- [ ] Thêm cờ `requires_builder` cho từng tool. `execute()` chỉ chặn tool có cờ này khi Builder tắt.
- [ ] Thêm trường `capability` cho từng tool. `execute()` kiểm `current_user_can( $capability )` trước khi chạy.
- [ ] Audit log thao tác ghi: user, tool, object ID, tóm tắt trước/sau, thời gian. Lưu bảng riêng hoặc CPT log.
- [ ] Snapshot trước khi ghi cho meta, option, term, user, để rollback.
- [ ] Nâng `MAX_CONTINUE_TURNS` lên 12.
- [ ] Nạp tool theo nhóm (`group`: content, taxonomy, structure, seo, media, comments, users, ops) để giảm schema gửi LLM.
- Kiểm chứng: SEO và Media vẫn ghi được khi Builder tắt. User không đủ quyền nhận lỗi `forbidden`.

### M1 — Nội dung: Page + Post + CPT
- [ ] `list_post_types` (read): liệt kê post type public, gồm CPT.
- [ ] `list_posts` / `get_post` (read): nhận `post_type`, thay thế dần `list_pages` / `get_page` và giữ alias tương thích.
- [ ] `create_post` / `save_post` (write): blog và CPT. Dùng chung guard `<script>`, `validate_markup`, `Align_Editor_Mode_Lock::is_locked_to()`.
- [ ] `set_post_status` (write): `publish`, `draft`, `private`, `future` (hẹn giờ).
- [ ] `trash_post` / `restore_post` (write): chỉ trash.
- [ ] `duplicate_post` (write).
- [ ] `update_post_attributes` (write): slug, parent, template, excerpt, ngày đăng, author.
- Kiểm chứng: tạo bài blog, hẹn giờ đăng, trash rồi restore, duplicate một CPT.

### M2 — Taxonomy
- [ ] `list_terms` (read): category, tag, taxonomy của CPT.
- [ ] `create_term` / `update_term` / `delete_term` (write).
- [ ] `assign_terms` (write): gán term cho post.
- Kiểm chứng: tạo category, gán cho bài, đổi tên, xóa term rỗng.

### M3 — Cấu trúc site (Align Theme)
- [ ] `list_templates` (read): `wp_template` + `wp_template_part`.
- [ ] `get_template` (read): HTML + CSS + JS qua `tp_id` / `tpl_id`.
- [ ] `save_template` (write): đi qua `persist_template()`. Xử lý `editor_mode_locked`. Menu được sửa ở đây (HTML trong header part).
- [ ] `list_reuse_blocks` / `get_reuse_block` (read).
- [ ] `create_reuse_block` / `save_reuse_block` (write).
- [ ] `set_homepage` (write): `show_on_front`, `page_on_front`, `page_for_posts`.
- [ ] `update_site_identity` (write): `blogname`, `blogdescription`, logo, favicon (`site_icon`).
- Kiểm chứng: thêm 1 link menu vào header, preview, approve, rollback qua revision.

### M4 — SEO mở rộng
- [ ] Mở rộng tool SEO hiện có sang post và CPT.
- [ ] Redirect (`list_redirects`, `create_redirect`, `delete_redirect`): chỉ đăng ký khi provider = Rank Math và `Align_Redirection_Importer::is_rank_math_active()`. Guard dòng 1 trả `plugin_not_active`.
- [ ] `list_404_logs` (read) / `create_redirect_from_404` (write).
- Kiểm chứng: đổi provider sang Yoast, tool redirect biến mất khỏi schema.

### M5 — Media mở rộng
- [ ] `update_media_meta` (write): alt, title, caption, description.
- [ ] `trash_media` (write): cảnh báo khi ảnh đang được dùng (featured, nội dung).
- Kiểm chứng: sửa alt hàng loạt cho 3 ảnh trong một lần duyệt.

### M6 — Comments + Users
- [ ] `list_comments` (read): lọc `hold`, `approve`, `spam`.
- [ ] `moderate_comment` (write): approve, spam, trash.
- [ ] `reply_comment` (write).
- [ ] `list_users` (read).
- [ ] `create_user` (write): mọi role, kể cả administrator. Gửi email set mật khẩu bằng `wp_new_user_notification()`.
- [ ] `update_user_role` (write).
- [ ] `update_user_profile` (write): tên, email, display name.
- [ ] `send_password_reset` (write).
- [ ] `delete_user` (write): bắt buộc chọn user nhận lại nội dung (`reassign`).
- Guard bắt buộc trong code:
  - Cấm hạ quyền hoặc xóa chính user đang thao tác.
  - Cấm hạ quyền hoặc xóa administrator cuối cùng.
  - Không bao giờ trả mật khẩu trong chat hoặc tool output.
  - Tạo hoặc nâng lên administrator: approval card hiển thị cảnh báo đỏ.
- Kiểm chứng: tạo editor, nâng thành administrator, thử tự hạ quyền chính mình (phải bị chặn).

### M7 — Vận hành
- [ ] `flush_cache` (write): bọc endpoint `/cache/flush`.
- [ ] `site_overview` (read): bài mới, comment chờ duyệt, 404 mới, bản cập nhật core.

### M8 — Kiểm thử qua Chatbox
- [ ] Kịch bản test từng nhóm: read, write, approve, reject, rollback.
- [ ] Test khi Builder tắt, Rank Math tắt, template khóa Gutenberg.
- [ ] Test giới hạn turn và nhiều write tool trong một step.

---

## 5. File dự kiến

| File | Nội dung |
|---|---|
| `includes/class-agent-tool-registry.php` | `requires_builder`, `capability`, `group` |
| `includes/class-agent-audit-log.php` | Audit log + snapshot |
| `includes/class-agent-tools-posts.php` | M1 |
| `includes/class-agent-tools-taxonomy.php` | M2 |
| `includes/class-agent-tools-templates.php` | M3 (templates, reuse blocks) |
| `includes/class-agent-tools-site.php` | M3 (homepage, identity) + M7 |
| `includes/class-agent-tools-redirects.php` | M4 |
| `includes/class-agent-tools-comments.php` | M6 |
| `includes/class-agent-tools-users.php` | M6 |

Mọi file < 500 dòng. Log PHP prefix `[Align Agent]`.

---

## 6. Rủi ro

- Quản lý user qua chat: lộ phiên admin có thể dẫn tới mất quyền kiểm soát site. Guard M6 là bắt buộc.
- Sửa header/footer ảnh hưởng toàn site. Cần preview và revision trước khi ghi.
- Khoảng 45 tool làm schema dài. Nạp theo nhóm (M0) là bắt buộc.

---

## 7. Để sau
- Telegram dùng chung `Align_Agent_Loop` + approval qua inline keyboard.
- Đa ngôn ngữ (`Align_Language_Manager`, `/create-translation`).
