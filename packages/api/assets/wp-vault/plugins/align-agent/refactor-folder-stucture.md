# Plan: Refactor tool folder structure (`includes/tools/`)

> **Đối tượng:** Worker agent thực thi.
> **Phạm vi:** Chỉ plugin `wp-content/plugins/Align-Agent/`. KHÔNG sửa file nào trong theme `Align-Real-Builder`.
> **Bản chất:** Refactor thuần cấu trúc. KHÔNG đổi logic, tên class, tên tool, schema, capability, group hay hành vi.

---

## 0. Bối cảnh hiện tại (đo ngày 2026-10-19)

- 10 class tool + 1 adapter nằm phẳng trong `includes/`.
- Nạp thủ công tại `align-agent.php:77-87` (`require_once`) và `align-agent.php:179-189` (`::init()`), trong hàm `align_agent_bootstrap()` (hook `plugins_loaded`).
- Tổng 57 tool đăng ký (tool SEO ghi chỉ bật 1 trong 3 theo `align_agent_seo_provider`).

| File hiện tại | Dòng | Class | Tool (registry `group`) |
|---|---|---|---|
| `includes/class-agent-tools-pages.php` | 496 | `Align_Agent_Tools_Pages` | `list_pages`, `get_page`, `get_revisions`, `validate_markup`, `save_page`, `create_page`, `restore_revision` (content) |
| `includes/class-agent-tools-posts.php` | 497 | `Align_Agent_Tools_Posts` | `list_post_types`, `list_posts`, `get_post`, `create_post`, `save_post`, `set_post_status`, `trash_post`, `restore_post`, `duplicate_post`, `update_post_attributes` (content) |
| `includes/class-agent-tools-taxonomy.php` | 361 | `Align_Agent_Tools_Taxonomy` | `list_terms`, `create_term`, `update_term`, `delete_term`, `assign_terms` (taxonomy) |
| `includes/class-agent-tools-templates.php` | 479 | `Align_Agent_Tools_Templates` | `list_templates`, `get_template`, `list_reuse_blocks`, `get_reuse_block`, `save_template`, `create_reuse_block`, `save_reuse_block` (structure) |
| `includes/class-agent-tools-site.php` | 382 | `Align_Agent_Tools_Site` | `set_homepage`, `update_site_identity` (structure); `flush_cache`, `site_overview` (ops) |
| `includes/class-agent-tools-seo.php` | 478 | `Align_Agent_Tools_Seo` | `check_seo_output`, `save_seo_rank_math`, `save_seo_yoast`, `save_seo_theme_default` (seo) |
| `includes/class-agent-seo-adapters.php` | 407 | `Align_Agent_Seo_Adapters` | (không phải tool, adapter ghi SEO) |
| `includes/class-agent-tools-redirects.php` | 426 | `Align_Agent_Tools_Redirects` | `list_redirects`, `create_redirect`, `delete_redirect`, `list_404_logs`, `create_redirect_from_404` (seo) |
| `includes/class-agent-tools-media.php` | 498 | `Align_Agent_Tools_Media` | `list_media`, `set_featured_image`, `attach_media`, `sideload_media`, `update_media_meta`, `trash_media` (media) |
| `includes/class-agent-tools-comments.php` | 259 | `Align_Agent_Tools_Comments` | `list_comments`, `moderate_comment`, `reply_comment` (comments) |
| `includes/class-agent-tools-users.php` | 493 | `Align_Agent_Tools_Users` | `list_users`, `create_user`, `update_user_role`, `update_user_profile`, `send_password_reset`, `delete_user` (users) |

**Phụ thuộc chéo (giữ nguyên, chỉ cần class đã được nạp):**
- `Align_Agent_Tools_Posts` và `Align_Agent_Tools_Templates` gọi `Align_Agent_Tools_Pages::guard_html_and_css`.
- `Align_Agent_Tools_Seo`, `Align_Agent_Tools_Redirects`, `Align_Agent_Loop` (`includes/class-agent-loop.php:69`) gọi `Align_Agent_Seo_Adapters::*`.

**Không di chuyển** (hạ tầng agent, không phải tool): `class-agent-tool-registry.php`, `class-agent-audit-log.php`, `class-agent-conversation.php`, `class-agent-loop.php`, `class-agent-chat-controller.php`, toàn bộ `class-gateway-*.php`, `class-git-updater.php`.

---

## 1. Cấu trúc đích

Quy tắc: **tên folder = giá trị `group` trong registry**. Ngoại lệ duy nhất: `site/` chứa `Align_Agent_Tools_Site` (2 tool `structure` + 2 tool `ops`).

```
includes/tools/
├── README.md                          ← INDEX cho AI: bảng toàn bộ 57 tool
├── class-agent-tools-loader.php       ← Manifest + nạp + init (MỚI)
├── content/
│   ├── README.md
│   ├── class-agent-tools-pages.php
│   └── class-agent-tools-posts.php
├── taxonomy/
│   ├── README.md
│   └── class-agent-tools-taxonomy.php
├── structure/
│   ├── README.md
│   └── class-agent-tools-templates.php
├── site/
│   ├── README.md
│   └── class-agent-tools-site.php
├── seo/
│   ├── README.md
│   ├── class-agent-seo-adapters.php
│   ├── class-agent-tools-seo.php
│   └── class-agent-tools-redirects.php
├── media/
│   ├── README.md
│   └── class-agent-tools-media.php
├── comments/
│   ├── README.md
│   └── class-agent-tools-comments.php
└── users/
    ├── README.md
    └── class-agent-tools-users.php
```

**Giữ nguyên tên file** khi di chuyển. Không đổi tên class.

---

## 2. Các bước thực thi (làm đúng thứ tự)

### Bước 1 — Ghi baseline (TRƯỚC khi đụng code)

```bash
cd /Users/agent/Documents/LocalWP/aignrealbuilder/app/public/wp-content/plugins/Align-Agent
git status --short > /tmp/align-refactor-status-before.txt
```

Ghi danh sách tool đang đăng ký (chạy trong LocalWP site shell, `wp` có sẵn tại `/Users/agent/.local/bin/wp`):

```bash
wp eval 'foreach ( Align_Agent_Tool_Registry::get_openai_tools_schema() as $t ) { echo $t["function"]["name"], PHP_EOL; }' --user=1 | sort > /tmp/align-tools-before.txt
```

- Nếu cấu trúc schema khác `$t["function"]["name"]`, đọc `includes/class-agent-tool-registry.php:115` rồi sửa biểu thức cho đúng. KHÔNG sửa registry.
- Nếu `wp` không chạy được (DB không kết nối), dùng fallback: `grep -ohE "(register|self::reg)\(\s*'[a-z_0-9]+'" includes/class-agent-tools-*.php | grep -oE "'[a-z_0-9]+'" | sort > /tmp/align-tools-before.txt` (phải ra 57 dòng).

### Bước 2 — Tạo folder và di chuyển file

- Nhiều file đang **untracked** (`??` trong `git status`). Dùng `git mv` cho file đã track, `mv` cho file untracked. Kiểm tra từng file bằng `git ls-files --error-unmatch <file>`.
- KHÔNG commit, KHÔNG stash, KHÔNG revert thay đổi đang dở của người khác trong các file này. Chỉ di chuyển.

```bash
mkdir -p includes/tools/{content,taxonomy,structure,site,seo,media,comments,users}
```

| Từ | Đến |
|---|---|
| `includes/class-agent-tools-pages.php` | `includes/tools/content/class-agent-tools-pages.php` |
| `includes/class-agent-tools-posts.php` | `includes/tools/content/class-agent-tools-posts.php` |
| `includes/class-agent-tools-taxonomy.php` | `includes/tools/taxonomy/class-agent-tools-taxonomy.php` |
| `includes/class-agent-tools-templates.php` | `includes/tools/structure/class-agent-tools-templates.php` |
| `includes/class-agent-tools-site.php` | `includes/tools/site/class-agent-tools-site.php` |
| `includes/class-agent-seo-adapters.php` | `includes/tools/seo/class-agent-seo-adapters.php` |
| `includes/class-agent-tools-seo.php` | `includes/tools/seo/class-agent-tools-seo.php` |
| `includes/class-agent-tools-redirects.php` | `includes/tools/seo/class-agent-tools-redirects.php` |
| `includes/class-agent-tools-media.php` | `includes/tools/media/class-agent-tools-media.php` |
| `includes/class-agent-tools-comments.php` | `includes/tools/comments/class-agent-tools-comments.php` |
| `includes/class-agent-tools-users.php` | `includes/tools/users/class-agent-tools-users.php` |

Nội dung file **không đổi một byte**. Các file này không dùng `__DIR__`/`__FILE__`/đường dẫn tương đối (đã kiểm tra), chỉ `require_once ABSPATH . 'wp-admin/includes/...'` → vẫn đúng sau khi di chuyển.

### Bước 3 — Tạo loader `includes/tools/class-agent-tools-loader.php`

Yêu cầu:
- Guard `if ( ! defined( 'ABSPATH' ) ) { exit; }` như các file khác.
- Class `Align_Agent_Tools_Loader` với 1 hằng/mảng manifest **có thứ tự cố định** (đường dẫn tương đối `includes/tools/` → tên class):

```php
private const MANIFEST = array(
	'seo/class-agent-seo-adapters.php'           => 'Align_Agent_Seo_Adapters',
	'content/class-agent-tools-pages.php'        => 'Align_Agent_Tools_Pages',
	'content/class-agent-tools-posts.php'        => 'Align_Agent_Tools_Posts',
	'taxonomy/class-agent-tools-taxonomy.php'    => 'Align_Agent_Tools_Taxonomy',
	'structure/class-agent-tools-templates.php'  => 'Align_Agent_Tools_Templates',
	'site/class-agent-tools-site.php'            => 'Align_Agent_Tools_Site',
	'seo/class-agent-tools-seo.php'              => 'Align_Agent_Tools_Seo',
	'seo/class-agent-tools-redirects.php'        => 'Align_Agent_Tools_Redirects',
	'media/class-agent-tools-media.php'          => 'Align_Agent_Tools_Media',
	'comments/class-agent-tools-comments.php'    => 'Align_Agent_Tools_Comments',
	'users/class-agent-tools-users.php'          => 'Align_Agent_Tools_Users',
);
```

  Thứ tự này **trùng khớp** thứ tự `::init()` hiện tại ở `align-agent.php:179-189` → thứ tự tool trong OpenAI schema không đổi.
- `public static function load(): void` — `require_once __DIR__ . '/' . $path` cho từng mục. Nếu file không tồn tại: `error_log( '[Align Agent] Tool file missing: ' . $path );` rồi bỏ qua (không fatal).
- `public static function init(): void` — gọi `$class::init()` theo thứ tự manifest, chỉ khi `class_exists( $class )`; nếu không: `error_log( '[Align Agent] Tool class not loaded: ' . $class );`.
- Không dùng `glob()` tự quét: manifest tường minh giữ thứ tự xác định và lộ ngay file thiếu.
- Comment/log 100% tiếng Anh. File < 80 dòng.

### Bước 4 — Sửa `align-agent.php`

- Xoá 11 dòng `require_once` tại `align-agent.php:77-87` (seo-adapters + 10 tools). Thay bằng:
  ```php
  require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/tools/class-agent-tools-loader.php';
  Align_Agent_Tools_Loader::load();
  ```
  Đặt đúng vị trí cũ: sau `class-agent-conversation.php` (dòng 76), trước `class-agent-loop.php` (dòng 88).
- Xoá 11 dòng `::init()` tại `align-agent.php:179-189` (`Align_Agent_Seo_Adapters::init();` → `Align_Agent_Tools_Users::init();`). Thay bằng `Align_Agent_Tools_Loader::init();` đúng vị trí cũ: sau `Align_Agent_Conversation::init();`, trước `Align_Agent_Loop::init();`.
- Không đổi dòng nào khác.

### Bước 5 — Kiểm chứng code

```bash
for f in align-agent.php includes/tools/class-agent-tools-loader.php includes/tools/*/*.php; do php -l "$f"; done
```
→ Mọi file: `No syntax errors detected`.

```bash
ls includes/class-agent-tools-*.php includes/class-agent-seo-adapters.php 2>&1
```
→ Phải báo `No such file or directory` (không còn file cũ).

```bash
wp eval 'foreach ( Align_Agent_Tool_Registry::get_openai_tools_schema() as $t ) { echo $t["function"]["name"], PHP_EOL; }' --user=1 | sort > /tmp/align-tools-after.txt
diff /tmp/align-tools-before.txt /tmp/align-tools-after.txt && echo TOOLS_MATCH
```
→ Phải in `TOOLS_MATCH`. (Fallback grep: áp lệnh grep Bước 1 lên `includes/tools/*/class-agent-tools-*.php`, phải ra đúng 57 dòng và diff rỗng.)

```bash
wp eval 'echo class_exists("Align_Agent_Tools_Loader") ? "OK" : "FAIL";'
tail -n 50 ../../../../../logs/php/error.log 2>/dev/null | grep "Align Agent"
```
→ `OK`; không có dòng `Tool file missing` / `Tool class not loaded`. (Nếu đường dẫn log khác, tìm `logs/` ở gốc site LocalWP.)

Smoke test UI (tuỳ chọn, nếu có trình duyệt): mở WP Admin → FAB chatbox → hỏi "list pages" → tool `list_pages` chạy, không lỗi.

**Nếu bất kỳ kiểm chứng nào FAILED: DỪNG, báo lại kèm output. Không tự sửa logic tool.**

### Bước 6 — Viết docs cho AI

#### 6a. `includes/tools/README.md` (index, file AI đọc đầu tiên)

Viết bằng tiếng Anh (quy chuẩn ngôn ngữ của plugin). Nội dung bắt buộc:
1. **Purpose** — 2-3 câu: folder chứa toàn bộ tool chatbox gọi qua `Align_Agent_Tool_Registry`; tool gọi trực tiếp class PHP của Align Theme (không HTTP).
2. **Folder map** — bảng `folder | registry group | classes | tool count`.
3. **Full tool table** — 1 dòng / tool, đủ 57 tool, cột:
   `tool | folder | type (read/write) | capability | requires_builder | danger | needs_approval | Align Core / WP API called`
   Lấy giá trị **từ code thực tế** (mảng truyền vào `Align_Agent_Tool_Registry::register()` hoặc helper `self::reg()` / `$opts`). Không suy đoán. Write tool = `needs_approval` yes.
4. **SEO dynamic routing** — chỉ 1 trong `save_seo_rank_math` / `save_seo_yoast` / `save_seo_theme_default` được đăng ký theo option `align_agent_seo_provider`; `others` → không có tool ghi SEO.
5. **Align Core dependencies** — bảng class Align Theme được gọi + method + file nguồn trong theme:
   - `Align_Builder_Crud_Handler` (`create_item`, `save_page`, `rename_item`) → `themes/Align-Real-Builder/align/core/inc/builder/class-builder-crud-handler.php`
   - `Align_Builder_Revision_Handler` (`get_revisions`, `restore_revision`) → `.../inc/builder/class-builder-revision-handler.php`
   - `Align_Builder_Assets` (`load_external_css`, `load_external_js`) → `.../inc/builder/class-builder-assets.php`
   - `Align_Builder_Persistence_Service` (`sync_post_assets_from_content`) → `.../inc/builder/class-builder-persistence-service.php`
   - `Align_Builder_Seo_Handler` (`evaluate_rank_math_score`) → `.../inc/builder/class-builder-seo-handler.php`
   - `Align_Seo` (`has_external_seo_plugin`, `save_meta`) → `.../core/includes/core/class-align-seo.php`
   - `Align_Editor_Mode_Lock` (`get_effective_mode`, `is_locked`, `is_locked_to`) → `.../core/includes/admin/class-align-editor-mode-lock.php`
   Xác minh lại bằng `grep -rhoE "Align_(Builder|Editor|Seo)[A-Za-z_]*::[a-z_]+" includes/tools | sort -u` trước khi ghi; bổ sung nếu có thêm.
6. **Shared helpers** — `Align_Agent_Tools_Pages::guard_html_and_css` (dùng bởi posts, templates); `Align_Agent_Seo_Adapters` (dùng bởi seo, redirects, `class-agent-loop.php`).
7. **Not supported** — liệt kê rõ chatbox CHƯA quản lý: menus, Align Settings/theme options, plugins, widgets.
8. **How to add a new tool** — checklist: chọn folder theo `group` → thêm class/hàm → nếu class mới thì thêm 1 dòng vào `MANIFEST` của loader → khai báo đủ `capability`, `group`, `requires_builder`, `danger` → cập nhật README folder + bảng index → giữ file < 500 dòng.

#### 6b. `includes/tools/<folder>/README.md` (8 file)

Mẫu cố định cho mỗi folder:

```markdown
# <Folder> tools

**Registry group:** `<group>`
**Classes:** `<Class>` (`<file>.php`)

## Tools

### `<tool_name>`
- **Type:** read | write (needs approval)
- **Capability:** `<cap>`
- **Requires Builder:** yes/no
- **Danger:** yes/no
- **Parameters:** `<param>` (type, required?) — mô tả ngắn lấy từ schema
- **Calls:** `<Align Core class::method>` / `<WP function>`
- **Guards:** (ví dụ: editor mode lock, `<script>` block, `validate_markup`, trash-only, last admin protection…)

## Notes
- Các quy tắc đặc thù folder (ví dụ media: `sideload_media` chỉ nhận URL https có trong tin nhắn user, đuôi jpg|jpeg|png|webp|gif, <= 5 MB).
```

Mọi giá trị lấy từ code. Mục nào code không có → ghi `—`, không bịa.

### Bước 7 — Cập nhật docs hiện có

| File | Việc cần làm |
|---|---|
| `AGENTS.md` | Sửa đường dẫn `includes/class-agent-tools-media.php` (mục §3.4 M2) → `includes/tools/media/class-agent-tools-media.php`. Thêm mục mới **§5 Tool Folder Standard**: tool chỉ nằm trong `includes/tools/<group>/`; class mới phải thêm vào `Align_Agent_Tools_Loader::MANIFEST`; mọi thay đổi tool phải cập nhật `includes/tools/README.md` + README folder trong cùng commit; CẤM tạo `includes/class-agent-tools-*.php` mới. |
| `README.md` | Sửa đường dẫn tại `README.md:83`. Trong mục `## 🛠️ Kiến Trúc & Module Lõi` (dòng 31) thêm 1 dòng trỏ tới `includes/tools/README.md` làm danh mục tool. Rà mục `## 🚀 Hệ Thống Tool Calling Mở Rộng v2` (dòng 101) và sửa mọi đường dẫn tool cũ. |
| `docs/BUG_LOGS.md` | KHÔNG sửa các entry cũ (lịch sử). Thêm 1 entry mới ở đầu/cuối theo format sẵn có: refactor cấu trúc tool → `includes/tools/`, liệt kê file đã di chuyển và loader mới. |
| `update-align-agent-v1.md`, `update-align-agent-v2.md` | KHÔNG sửa (tài liệu plan lịch sử). |

Sau khi sửa, chạy:
```bash
grep -rn "includes/class-agent-tools-\|includes/class-agent-seo-adapters" --include='*.md' --include='*.php' --include='*.js' . | grep -v "update-align-agent-v\|docs/BUG_LOGS.md\|refactor-folder-stucture.md"
```
→ Phải rỗng.

### Bước 8 — Bàn giao

- KHÔNG commit trừ khi orchestrator/user yêu cầu (working tree đang có thay đổi dở của người khác).
- Báo cáo theo format: `SUCCESS` hoặc `FAILED` đầu tiên, rồi:
  - `git status --short` sau refactor.
  - Output `php -l`, `diff` (`TOOLS_MATCH`), grep đường dẫn cũ.
  - Danh sách file docs đã tạo/sửa.

---

## 3. Điều cấm

1. CẤM sửa nội dung bên trong 11 file được di chuyển.
2. CẤM đổi tên class, tên tool, schema, `group`, `capability`.
3. CẤM sửa file trong theme `Align-Real-Builder`.
4. CẤM dùng `glob()` / autoload ngầm thay cho manifest.
5. CẤM tách nhỏ class (1 tool / 1 file) trong đợt này.
6. CẤM viết docs từ trí nhớ; mọi giá trị phải đối chiếu code.

## 4. Ngoài phạm vi (ghi nhận, không làm)

- Tách `guard_html_and_css` khỏi `Align_Agent_Tools_Pages` sang helper dùng chung (`includes/tools/shared/`).
- 4 file đang sát giới hạn 500 dòng (`pages` 496, `posts` 497, `media` 498, `users` 493) — cần tách ở đợt sau trước khi thêm tool.
- Sinh `tools.json` máy đọc tự động từ registry.
