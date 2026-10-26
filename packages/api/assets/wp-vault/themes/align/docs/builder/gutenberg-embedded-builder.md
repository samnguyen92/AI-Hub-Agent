# 🖼️ Gutenberg Embedded Align Builder Canvas & Two-Way Save Bridge

> **Dedicated Architectural & Technical Documentation**
> Hệ thống nhúng trực tiếp **Align Visual Builder** vào không gian soạn thảo của WordPress Gutenberg (Block Editor & Site Editor) qua Iframe Sandbox, kết hợp bảo toàn Topbar / Settings Sidebar bản địa của WordPress, cầu nối dữ liệu hai chiều (**Two-Way Save Bridge**), bảo toàn thanh công cụ Align Builder Toolbar và giao diện tràn viền 100% không cuộn dọc (**Zero Scroll / Full Viewport Height**).

---

## 📌 1. Tổng Quan & Bài Toán (Overview & Rationale)

### 1.1 Vấn Đề Của Quy Trình Cũ
Trước khi có giải pháp Embedded Builder:
1. **Phân mảnh trải nghiệm người dùng**: Khi người dùng mở trang biên tập mặc định của WordPress (`/wp-admin/post.php?post=1&action=edit`), Gutenberg hiển thị Writing Flow mặc định (Paragraph, Heading, List...) vốn không tương thích với layout phức tạp của Align Theme.
2. **Mất liên kết với công cụ SEO & Post Meta của Gutenberg**: Nếu mở Align Builder ở tab riêng (`/wp-admin/admin.php?page=align-builder`), người dùng mất đi các công cụ quan trọng được tích hợp sẵn trong Gutenberg:
   - Gutenberg Topbar: Trạng thái xuất bản (Publish / Draft), Preview link, Post URL / Slug.
   - Gutenberg Settings Sidebar: Featured Image (Thumbnail đại diện), Excerpt (Tóm tắt bài viết), Categories, Tags, Author, Discussion, Page Attributes, và metabox từ các plugin SEO (Rank Math, Yoast SEO).
3. **Tràn khung hình & cuộn thừa (Vertical Scroll Issue)**: Các iframe nhúng thông thường nếu dùng `min-height: calc(100vh - 60px)` kết hợp với thanh Footer mặc định của Gutenberg (`.interface-interface-skeleton__footer`, thanh breadcrumbs màu trắng hiển thị chữ "Post") sẽ gây tràn chiều cao màn hình, đẩy thanh đáy của Builder (nút `<> Code editor` và breadcrumb bar) xuống dưới nếp gấp màn hình.

### 1.2 Giải Pháp: Gutenberg Embedded Builder Canvas
- **Tận dụng WordPress Shell**: Giữ nguyên vẹn Topbar và Inspector Sidebar của Gutenberg để quản lý Title, Featured Image, Excerpt, SEO, Taxonomy.
- **Thay thế Canvas 100% (Zero Scroll)**:
  - Thay thế khu vực viết chữ mặc định của Gutenberg (`.interface-interface-skeleton__content`) bằng Iframe tải trực tiếp Align Builder với tham số `?embedded=1`.
  - Khung chứa ngoài khoá `overflow: hidden`, iframe dùng `position: absolute; inset: 0; width: 100%; height: 100%;` để lấp đầy chính xác 100% không gian từ mép dưới Topbar tới chân màn hình.
  - Ẩn hoàn toàn thanh Footer trắng bản địa của Gutenberg (`.interface-interface-skeleton__footer`, `.edit-post-layout__footer`, `.block-editor-block-breadcrumb`). Nút **`<> Code editor`** và breadcrumbs `body > p` luôn nằm cố định sát đáy màn hình.
- **Bảo toàn Toolbar & Hành động của Align Builder**:
  - Không ẩn `#vvveb-builder #top-panel` như thiết kế sơ khởi. Giữ lại thanh `top-panel` cao 38px để hiển thị đầy đủ:
    - **Cụm bên trái**: Undo, Redo, Revisions, Chế độ chỉnh sửa.
    - **Cụm ở giữa**: Element Action Toolbar (`#select-actions`: Up, Down, Clone, Delete, Hide, Convert dynamic) và Responsive Viewports (Desktop, Tablet, Mobile).
    - **Cụm bên phải**: Nút Save của Builder.
  - Chỉ ẩn duy nhất nút `#back-to-cms-btn` (icon WordPress logo) vì đã nằm trong shell Gutenberg.
- **Dọn dẹp Gutenberg Topbar thông minh**:
  - Ẩn các nút không cần thiết: Block Inserter (`+`), Undo/Redo của Gutenberg, View Post Link (`↗`), Device Preview (`💻`), More Options (`⋮`).
  - **Bảo tồn tuyệt đối**: Tiêu đề trang (Center), nút Toggle Settings Sidebar (`◨`), và nút **Save / Update / Publish** của Gutenberg.
- **Tối ưu hiệu năng Sidebar Tree (Dynamic Fallback)**:
  - Chỉ nạp `Pages`, `Templates`, `Template Parts`, `Reusable Blocks` trong cây thư mục bên trái của Builder để tránh query hàng nghìn bài viết Blog làm quá tải database.
  - Khi sửa bài viết `post` hoặc bất kỳ CPT nào, hệ thống tự động kích hoạt **Dynamic Post Content Fallback** (`wp-filemanager.js`) fetch trực tiếp `/page-content?id=ID` vào Canvas.
- **Cầu nối lưu dữ liệu hai chiều (Two-Way Save Bridge)**:
  - Khi người dùng nhấn nút **Save / Update** trên Gutenberg Topbar, Gutenberg gửi `postMessage` yêu cầu Align Builder chụp snapshot HTML/CSS/JS mới nhất.
  - Parent Coordinator đồng bộ snapshot vào WordPress store rồi gọi native save của Gutenberg/Site Editor; Builder iframe chỉ nhận ACK theo đúng `requestId` và `_capturedVersion`.
- **Tinh gọn các điểm điều hướng (Streamlined Navigation Entry Points)**:
  - Do thao tác bấm **Edit** mặc định trên Posts/Pages giờ đây mở trực tiếp giao diện Gutenberg nhúng sẵn Align Builder, toàn bộ các lối tắt thừa trước đây đã được loại bỏ để làm sạch giao diện:
    - Loại bỏ nút *"Edit with Align Builder"* trên thanh WordPress Admin Bar ngoài frontend (`admin_bar_menu`).
    - Loại bỏ hành động hover *"Edit with Align Builder"* trong danh sách Pages/Posts (`page_row_actions`, `post_row_actions`).
    - Đăng ký trang Builder dạng headless (`add_submenu_page( null, ... )`) để bảo toàn endpoint `admin.php?page=align-builder` cho iframe mà không tạo menu rác trong sidebar WP Admin.

---

## 🗂️ 2. Cấu Trúc File & Module (File Structure)

Hệ thống được tổ chức tuân thủ nghiêm ngặt **Quy chuẩn < 500 dòng/file** theo `AGENTS.md`:

```text
align/
├── core/
│   ├── includes/admin/
│   │   ├── class-align-editor-mode-lock.php   <-- Khóa Editor Mode (_align_editor_locked), Lazy Lock, Guard meta & REST /switch-editor-mode (481 lines)
│   │   └── class-align-page-settings.php      <-- Đăng ký REST meta _align_editor_mode & _align_editor_locked cho mọi CPT eligible (280 lines)
│   │
│   ├── inc/builder/
│   │   ├── class-builder-admin.php            <-- Enqueue block editor assets; đăng ký headless page cho iframe nhúng (350 lines)
│   │   ├── class-builder-ui-renderer.php      <-- Xử lý tham số ?embedded=1, inject .is-embedded & VvvebWpConfig.isEmbedded (406 lines)
│   │   ├── class-builder-crud-handler.php     <-- REST /save-page kèm guard 409 editor_mode_locked (368 lines)
│   │   ├── class-builder-template-structure.php <-- Giữ header/footer template-part & post-content attrs khi lưu template (126 lines)
│   │   └── class-builder-content-handler.php  <-- REST /pages nhẹ chỉ query Pages/Templates/Parts/Blocks (486 lines)
│   │
│   └── builder/
│       ├── gutenberg/
│       │   ├── align-editor-mode-switch.js      <-- Controller phân giải mode, khóa nút khi đang save, modal xác nhận & gọi REST switch (482 lines)
│       │   ├── align-site-editor-integration.js <-- Controller nhúng iframe, watcher DOM, post_id 4 tầng & postMessage bridge (348 lines)
│       │   ├── align-template-markup.js         <-- Dựng block markup từ snapshot; mirror Align_Builder_Template_Structure (155 lines)
│       │   ├── align-template-mode-lock.js      <-- Khóa mode template theo nội dung đã lưu; xóa nội dung khi đổi mode (89 lines)
│       │   ├── align-save-coordinator.js        <-- Single Save Coordinator đồng bộ snapshot 2 chiều & isBusy() (467 lines)
│       │   └── align-editor-overlay.css         <-- CSS full-height 100% (position: absolute), ẩn Gutenberg icons phụ & footer (219 lines)
│       │
│       ├── css/theme/
│       │   └── editor-dark-theme.css           <-- CSS chế độ .is-embedded: giữ #top-panel 38px, canvas tràn viền, dock bottom-panel (471 lines)
│       │
│       └── js/modules/
│           ├── wp-filemanager.js               <-- Dynamic fallback nạp canvas trực tiếp cho bất kỳ post_id nào ngoài tree (448 lines)
│           └── wp-save-handler.js              <-- Chụp snapshot, gửi dirty/request-save, nhận ACK từ Coordinator (481 lines)
```

---

## 🔄 3. Luồng Dữ Liệu & Vận Hành (Data Flow & Sequence)

```mermaid
sequenceDiagram
    autonumber
    actor User as Người dùng
    participant G as Gutenberg Parent Shell
    participant Ctrl as align-site-editor-integration.js
    participant IF as Embedded Iframe (Align Builder)
    participant FM as wp-filemanager.js
    participant Save as wp-save-handler.js
    participant REST as WordPress REST API

    User->>G: Truy cập /wp-admin/post.php?post=ID&action=edit (Page, Post, CPT)
    G->>Ctrl: Nạp align-site-editor-integration.js (enqueue_block_editor_assets)
    loop Watcher kiểm tra DOM mỗi 500ms
        Ctrl->>Ctrl: getExactPostId() (4 tầng trích xuất ID) -> resolveBuilderTarget()
        Ctrl->>G: Ẩn writing flow, visual editor & footer "Post"
        Ctrl->>G: Tạo #align-builder-embedded-container & nhúng <iframe> (position: absolute, 100% height)
    end
    G->>IF: Tải /wp-admin/admin.php?page=align-builder&post_id=ID&embedded=1
    Note over IF: UI Renderer gắn class .is-embedded vào <html> và <body>
    Note over IF: editor-dark-theme.css giữ #top-panel (38px), kéo canvas top: 38px, dock #bottom-panel sát đáy
    IF->>FM: Khởi tạo FileManager: get_pages chỉ nạp Pages/Templates/Parts (DB nhẹ)
    FM->>FM: Phát hiện activePostId từ URL -> Kích hoạt Dynamic Fallback: /page-content?id=ID
    FM->>IF: Builder.init(fileUrl) -> Canvas nạp bài viết đang chỉnh sửa vào WYSIWYG
    IF-->>User: Hiển thị Align Builder tràn viền 100%, đầy đủ Toolbar, Viewports, Element Actions (#select-actions) và Code Editor

    User->>G: Click nút "Update / Save" trên Topbar (hoặc Ctrl+S / iframe save button)
    G->>Coord: Intercept save qua AlignSaveCoordinator (Single Save Coordinator)
    Note over Coord: Khóa in-flight save lock, ngăn chặn save trùng lặp và double writers
    Coord->>IF: postMessage({ type: 'ALIGN_COORDINATOR_REQUEST_SNAPSHOT', requestId })
    IF->>Save: captureSnapshotPayload() (HTML, CSS, JS, reusable_overrides)
    Save->>Coord: postMessage({ type: 'ALIGN_COORDINATOR_SNAPSHOT_READY', payload, requestId })
    alt Chế độ Post / Page
        Coord->>G: Package canonical block <!-- wp:align/custom-html --> & editPost({ content })
        Coord->>G: wp.data.dispatch('core/editor').savePost()
        G->>Persist: Hook save_post -> Align_Builder_Persistence_Service::on_native_post_save()
        Persist->>Persist: Trích xuất CSS/JS, ghi file atomic, đồng bộ post meta DB
    else Chế độ Site Editor (wp_template / wp_template_part)
        Coord->>G: Bảo toàn data-component-post-content -> <!-- wp:post-content -->
        Coord->>G: wp.data.dispatch('core').saveEditedEntityRecord('postType', entityType, entityId)
        G->>Persist: Hook rest_after_insert_wp_template -> Align_Builder_Persistence_Service::on_native_template_save()
        Persist->>Persist: Ghi file template atomic, trích xuất CSS/JS đồng bộ DB
    end
    Coord->>IF: postMessage({ type: 'ALIGN_COORDINATOR_SAVE_ACK', success: true, requestId })
    IF->>Save: Reset dirty flag (Builder.hasChanges = false) & hiển thị Toast Success
    Coord-->>User: Gutenberg hiển thị thông báo "Saved / Updated" hoàn tất!
    end
```

---

## 🛠️ 4. Chi Tiết Kỹ Thuật Từng Thành Phần

### 4.1 UI Renderer & URL Injection (`class-builder-ui-renderer.php`)
- Khi request có param `embedded=1`:
  - Thêm class `.is-embedded` vào thẻ `<html>` và `<body>` của builder shell.
  - Gán cờ `isEmbedded: true` vào global config `window.VvvebWpConfig`.
  - Giúp CSS và JS bên trong iframe phát hiện chế độ nhúng để điều chỉnh layout phù hợp.

### 4.2 Bảo Toàn Toolbar & Action Bar (`editor-dark-theme.css`)
```css
/* Layout 100% viewport */
body.is-embedded #container { height: 100% !important; min-height: 100% !important; max-height: 100% !important; width: 100% !important; overflow: hidden !important; }

/* Giữ top-panel 38px: hiển thị toolbar, viewport selector, select-actions, save button */
body.is-embedded #vvveb-builder #top-panel { display: flex !important; height: 38px !important; min-height: 38px !important; max-height: 38px !important; position: relative !important; z-index: 100 !important; }

/* Ẩn duy nhất nút quay lại WordPress CMS */
body.is-embedded #vvveb-builder #top-panel #back-to-cms-btn { display: none !important; }

/* Panels và Canvas bù trừ top: 38px */
body.is-embedded #vvveb-builder #left-panel,
body.is-embedded #vvveb-builder #right-panel,
body.is-embedded #vvveb-builder #canvas { top: 38px !important; height: calc(100% - 38px) !important; }

/* Dock bottom panel (Code editor toggle & breadcrumbs) cố định sát đáy */
body.is-embedded #vvveb-builder #bottom-panel { bottom: 0 !important; position: fixed !important; z-index: 100 !important; }
```

### 4.3 Gutenberg Overlay Layout (`align-editor-overlay.css`)
```css
/* Full-viewport absolute container */
#align-builder-embedded-container {
    position: absolute !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    overflow: hidden !important;
    z-index: 99999 !important;
}

#align-builder-embedded-frame {
    width: 100% !important;
    height: 100% !important;
    border: none !important;
    display: block !important;
}

/* Ẩn Gutenberg Footer (Breadcrumbs trắng "Post") */
.interface-interface-skeleton__footer,
.edit-post-layout__footer,
.block-editor-block-breadcrumb {
    display: none !important;
}

/* Ẩn icon phụ topbar nhưng BẢO TOÀN nút Save / Update và Settings Sidebar (◨) */
body:has(#align-builder-embedded-container) .editor-document-tools__left,
body.is-align-builder-embedded .editor-document-tools__left,
body:has(#align-builder-embedded-container) .edit-post-header-toolbar__left,
body.is-align-builder-embedded .edit-post-header-toolbar__left,
body:has(#align-builder-embedded-container) .editor-header__settings .editor-preview-dropdown,
body.is-align-builder-embedded .editor-header__settings .editor-preview-dropdown,
body:has(#align-builder-embedded-container) .edit-post-header__settings .editor-preview-dropdown,
body.is-align-builder-embedded .edit-post-header__settings .editor-preview-dropdown,
body:has(#align-builder-embedded-container) .editor-header__settings .editor-more-menu,
body.is-align-builder-embedded .editor-header__settings .editor-more-menu,
body:has(#align-builder-embedded-container) .edit-post-header__settings .editor-more-menu,
body.is-align-builder-embedded .edit-post-header__settings .editor-more-menu {
    display: none !important;
}
```

### 4.4 Dynamic Fallback Cho Blog & CPT (`wp-filemanager.js`)
Tránh nạp hàng nghìn bài `post` vào cây thư mục bên trái (`get_pages` chỉ nạp Pages/Templates):
```javascript
// Nếu post_id đang mở không nằm trong tree data (ví dụ blog post hoặc custom post type)
if (activePostId && !pageToLoad) {
    const numId = parseInt(activePostId, 10);
    if (!isNaN(numId) && numId > 0) {
        console.log('[Align FileManager] Dynamic Post Content Fallback for ID:', numId);
        pageToLoad = {
            file: `${window.VvvebWpConfig.restPageContentUrl}?id=${numId}&_wpnonce=${window.VvvebWpConfig.restNonce}`,
            name: `Post #${numId}`,
            title: `Post #${numId}`,
            post_id: numId
        };
    }
}
```

#### Active item deletion and editor identity

After a successful deletion, `wp-items-crud.js` checks the iframe URL/config and the parent post editor ID. If the deleted item is active, it selects the first remaining regular Page from the FileManager registry, excluding the deleted ID, reusable blocks and template parts.

- Embedded Builder: navigate the parent window to `post.php?post=ID&action=edit`. A full editor load synchronizes the WordPress title, metadata, save target and embedded canvas.
- Standalone Builder: navigate to an explicit `post_id`, preserving the selected page language.
- No remaining Pages: return to `edit.php?post_type=page`.
- Inactive item: refresh only the Builder, retaining its current URL parameters. Failed or cancelled deletion does not navigate.

The previous redirect only reloaded the iframe without an item ID. The default first page (often named `404`, because Pages are sorted by title) then appeared while the parent editor still referenced the deleted post. Updating only the visible title would leave the save target incorrect.

Regression checks: `node --test align/tests/builder-delete-navigation.test.cjs`. This change only affects navigation after deletion; content serialization and HTML/CSS/JS persistence are unchanged.

### 4.5 Cầu Nối Single Save Coordinator & Persistence Service
- **Single Save Coordinator (`align-save-coordinator.js`)**:
  - Đóng vai trò là cơ quan đầu não duy nhất (Single Source of Truth) điều phối toàn bộ quy trình lưu giữa WordPress Gutenberg / Site Editor và Embedded Align Builder.
  - Quản lý cờ khóa in-flight (`_activeSave`), ngăn chặn double writers và xung đột race condition.
  - Intercepts cả 3 nguồn trigger: nút Save/Update của Gutenberg/Site Editor, phím tắt Ctrl+S/Cmd+S, và nút Save trong Builder iframe.
  - Sử dụng giao thức snapshot bất đồng bộ (`ALIGN_COORDINATOR_REQUEST_SNAPSHOT` -> `ALIGN_COORDINATOR_SNAPSHOT_READY` -> `ALIGN_COORDINATOR_SAVE_ACK`).
  - **Phân loại Entity chuẩn xác (Entity Resolution without NaN)**:
    - Trong WordPress 6.5+ / 7.1.x, `core/editor` trở thành store hợp nhất cho cả Page/Post và Template/Template Part.
    - Nhận diện `wp_template` và `wp_template_part` theo string ID chuẩn (`theme//slug`, ví dụ `align-child//404`), tuyệt đối không ép kiểu `Number(postId)` để tránh sinh `NaN` và asset ID dị dạng (`style-page-NaN`).
    - Template/Template Part chỉ có `isEmbedded = true` khi iframe Builder đang mount (hoặc body có `.is-align-builder-embedded`). Ở chế độ Gutenberg, Coordinator không chặn nút Save/Ctrl+S, nên WordPress lưu bình thường. Trước đây cờ này luôn `true` cho template, làm nút Save ở chế độ Gutenberg không có tác dụng.
    - Hỗ trợ giải mã tham số URL Site Editor `p` (ví dụ `p=%2Fwp_template%2Falign-child%2F%2F404` hoặc `p=/wp_template_part/align-child//header`) qua regex fallback khi store chưa sẵn sàng.
  - **Đồng bộ Dirty State Hai Chiều (Real-time Two-Way Dirty Bridge)**:
    - Lắng nghe message `ALIGN_BUILDER_DIRTY` từ Builder canvas iframe khi người dùng gõ phím, thay đổi thuộc tính hoặc undo/redo.
    - Cập nhật store bằng **một** lệnh `core.editEntityRecord('postType', entityType, entityId, { content, blocks }, { undoIgnore: true })` cho mọi entity. Không gọi `resetBlocks` trực tiếp: block editor ẩn tự đồng bộ từ entity `blocks`. Page/Post cập nhật thêm meta qua `editPost({ meta }, { undoIgnore: true })`.
    - Bỏ qua cập nhật khi markup không đổi. Snapshot Builder không vào undo stack của Gutenberg (Builder có undo riêng).
    - Markup được dựng bởi `window.AlignTemplateMarkup.build()` (`align-template-markup.js`): blockId ổn định theo entity (lấy từ block `align/custom-html` đã lưu), template/template part có attr `styleId`/`scriptId` giống đường PHP.
    - Kích hoạt trạng thái dirty trên nút Save/Update của WordPress shell, đảm bảo người dùng luôn có thể bấm lưu từ Gutenberg topbar.
  - **Xác minh Lưu Đáng Tin Cậy (Strict ACK Persistence Verification)**:
    - Gọi `core.saveEditedEntityRecord()` với tùy chọn `{ throwOnError: true }` cho Template/Template Part, hoặc `core/editor.savePost()` cho Page/Post.
    - Kiểm tra `getLastPostSaveError()` / `getLastEntitySaveError()` sau khi promise native save resolve để tránh ACK thành công giả.
    - Nếu Builder iframe đã gửi payload trong `ALIGN_BUILDER_REQUEST_SAVE`, Coordinator dùng trực tiếp payload đó thay vì request snapshot lần hai; nếu nút WP Save bị timeout snapshot (5s), Coordinator fallback sang dirty payload cuối cùng khớp entity trước khi báo lỗi.
  - **Đồng bộ Single Source of Truth (SOT)**: Parent Coordinator là writer duy nhất trong embedded mode. Builder iframe không tự REST-save song song; mọi nút Save/Ctrl+S đều đi qua snapshot → WordPress store → native save → ACK.
- **Builder Iframe (`wp-save-handler.js`)**:
  - Gắn trực tiếp event listeners vào `frameDoc.body` của canvas (`['vvveb.undo.add', 'vvveb.undo.restore', 'input', 'change', 'paste', 'drop']`).
  - Tự động re-bind khi nhận event `Vvveb.iframe.loaded` hoặc `vvveb.iframe.loaded`.
  - Cơ chế debounce 300ms (`notifyDirtyDebounced`) gom cụm các thao tác gõ phím liên tục để tối ưu hiệu năng postMessage.
  - **Xử lý Race Condition Khi Sửa Lúc Đang Lưu (In-flight Edit Versioning)**:
    - Theo dõi `_currentEditVersion` qua mỗi lần canvas chuyển sang trạng thái dirty.
    - Mỗi snapshot gửi đi kèm theo phiên bản `_capturedVersion`.
    - Khi nhận `ALIGN_COORDINATOR_SAVE_ACK` thành công: kiểm tra nếu `_currentEditVersion > capturedVersion` (người dùng vừa chỉnh sửa thêm trong lúc request lưu đang chạy), iframe giữ nguyên cờ dirty (`hasChanges = true`), không reset undo stack, và tự động gửi lại snapshot dirty mới nhất lên parent shell.
  - Trích xuất snapshot DOM sạch thông qua `captureSnapshotPayload()` gồm HTML, CSS (`StyleManager`), JS (`JsManager`) và Reusable Block Overrides.
  - Khi ở chế độ embedded: không tự ý gọi trực tiếp REST API lưu dữ liệu mà ủy quyền toàn bộ cho Parent Coordinator.
  - Khi ở chế độ standalone: giữ nguyên luồng fallback gọi REST endpoint `/save-page`.
- **Content Mode Sidebar Editing & Selection Stability**:
  - `builder-highlight-manager.js` không kích hoạt inline text editor / bubble toolbar trong Content Mode; canvas click chỉ chọn element và nạp Inspector.
  - `builder-components-registry.js` giữ target element ổn định theo render-time node khi textarea sidebar phát `propertyChange`, tránh việc `document.activeElement` làm lệch target sang field đang focus.
  - Khi người dùng gõ trong Inspector, hệ thống không gọi lại `selectNode()` để tránh cướp focus textarea, nhưng vẫn gọi `Vvveb.TreeList.syncWithBuilderSelection()` để giữ active state trong DOM Tree Navigator.
  - `builder-tree-list.js` sync selection bằng DOM reference và fallback `data-ast-id`, giúp class `.active` vẫn đúng sau các lần mutate text khiến `_treeNode` trong tree bị stale.
- **Backend Persistence Service (`class-builder-persistence-service.php`)**:
  - Ghi file bất khả xâm phạm (atomic write qua file tạm và `rename`).
  - Tự động bảo vệ `<!-- wp:post-content -->` trong block template, giữ nguyên attrs đã lưu (ví dụ `className`) qua `Align_Builder_Template_Structure::find_post_content_block()`.
  - Giữ header/footer bằng `parse_blocks()` (`Align_Builder_Template_Structure::wrap()`), không dùng regex. Regex cũ (`tagName\s*:`) không khớp `"tagName":` nên từng xóa mất header/footer khi lưu template đã có nội dung.
  - Tự động hook vào `save_post` và `rest_after_insert_wp_template` để đồng bộ asset CSS/JS ra đĩa và cập nhật canonical DB state.
  - `rest_after_insert_wp_template` / `_part` luôn mirror nội dung đã lưu ra `templates/` hoặc `parts/` (`mirror_template_to_disk`), kể cả nội dung thuần Gutenberg. Chỉ khi có `align/custom-html` mới bỏ `<style>`/`<script>` và trích asset.
  - `Align_Template_Tweaks::sync_file_template_content` chỉ lấy file disk khi file **mới hơn** bản DB (sửa file trong IDE). Trước đây filter lấy file mỗi khi nội dung khác DB. WordPress gọi `get_block_template()` ngay trong request lưu, nên mọi lần lưu Gutenberg không có block Builder đều bị file cũ ghi đè.

### 4.6 Chế Độ Chuyển Đổi Hai Chiều & Khóa Editor Mode (`Align_Editor_Mode_Lock` & `align-editor-mode-switch.js`)
- **Quy tắc phân định mặc định theo Post Type (Default Mode Strategy)**:
  - **Trang tĩnh (`page`)**: Mặc định là **Align Visual Builder** (`builder`).
  - **Bài viết Blog (`post`) & Custom Post Types (CPTs)**: Mặc định là **WordPress Gutenberg** (`gutenberg`). User có thể chuyển sang Align Builder bằng nút switch trên Topbar cho mọi post type đủ điều kiện (`show_ui => true`).
  - **Templates FSE (`wp_template`, `wp_template_part`)**: Mặc định Gutenberg. **Khóa theo nội dung đã lưu** (`align-template-mode-lock.js` + `Align_Builder_Template_Structure::detect_mode()`), không dùng meta, vì template từ file theme chưa có bản ghi DB:
    - Bỏ qua block khung `core/template-part` và `core/post-content`.
    - Không còn block nào (hoặc chỉ có 1 `align/custom-html` rỗng) thì **chưa khóa**: chuyển tự do, mode lưu trong `sessionStorage`.
    - Còn đúng 1 block `align/custom-html` (hoặc `core/html` kiểu cũ) thì khóa **builder**. Mọi trường hợp khác (block native, trộn nhiều block như `single.html`) khóa **gutenberg**.
    - Khi đã khóa, mode bỏ qua `?align_mode=` và `sessionStorage`. Nút switch mở hộp thoại "Clear content & switch". Khi xác nhận, nội dung riêng bị xóa, chỉ giữ template-part đầu/cuối và block `post-content`. Bản xóa được lưu native (`saveEditedEntityRecord`, có revision), rồi trang reload ở mode mới.
    - `/save-page` trả `409 editor_mode_locked` khi Builder đứng riêng lưu template đang khóa gutenberg.
    - Chuyển Gutenberg → Builder khi template chưa khóa và có edits chưa lưu (`hasEditsForEntityRecord`): hiển thị confirm, vì iframe Builder luôn nạp bản đã lưu trong DB.
    - Builder nhúng luôn mở entity đang sửa. Block template-part đang chọn không còn làm đổi target (trước đây làm snapshot lệch entity, nên nút Save không sáng).
- **Cơ chế Khóa Editor Mode (Editor Mode Lock — `_align_editor_locked`)**:
  - **Trước lần Save đầu có nội dung**: `_align_editor_locked = '0'`. Người dùng chuyển đổi tự do giữa Gutenberg và Align Builder. Nếu editor đang dirty (`isEditedPostDirty()` hoặc `_lastBuilderPayload`), hiển thị confirm `"Unsaved changes will be lost. Continue?"`. Khi đồng bộ `_align_editor_mode` vào store qua `editPost({ meta })`, cả `align-editor-mode-switch.js` và `align-save-coordinator.js` đều loại bỏ khóa chỉ-đọc `_align_editor_locked` khỏi object `meta` để tránh gửi giá trị cũ lên REST API.
  - **Khóa ngay sau khi Save lần đầu có nội dung (`wp_after_insert_post` priority 25)**: Khi `post_content` có nội dung thực (`detect_mode_from_content !== null`), hệ thống tự động chốt `_align_editor_mode` (`builder` | `gutenberg`) và ghi `_align_editor_locked = '1'` tại hook `wp_after_insert_post` (sau khi `WP_REST_Posts_Controller` đã hoàn tất `handle_meta_data()`), độc lập với `Align_Builder_Persistence_Service::$is_syncing_assets`.
  - **Nhận diện tự động bài cũ (Lazy Lock)**: Khi mở bài cũ có nội dung nhưng chưa có meta `_align_editor_locked`, `Align_Editor_Mode_Lock::maybe_lazy_lock_post()` kiểm tra quyền `current_user_can( 'edit_post', $post_id )` và dùng `parse_blocks()` để nhận diện: nếu chỉ có đúng 1 block `align/custom-html` có nội dung $\rightarrow$ khóa `builder`; ngược lại $\rightarrow$ khóa `gutenberg`.
  - **Chặn mọi đường vượt khóa**:
    - JS `getActiveEditorMode(postType)`: Khi bài đã khóa (`isPostLocked`), **luôn** trả về mode đã khóa, bỏ qua `?align_mode=` trên URL và `sessionStorage`.
    - PHP filter `update_post_metadata` / `add_post_metadata` / `delete_post_metadata` (`filter_protect_locked_mode_meta`): Chặn mọi thao tác sửa/thêm/xóa trực tiếp vào `_align_editor_locked` (khi `$allowing_mode_switch === false`) cũng như chặn xóa hoặc đổi `_align_editor_mode` sang giá trị khác trên bài đã khóa, nhưng cho phép các lệnh ghi idempotent (giá trị mới trùng khớp giá trị hiện tại trong DB) đi qua để không gây lỗi `rest_meta_database_error` (HTTP 500).
    - Classic Metabox (`class-align-page-settings.php`): Khóa `disabled` thẻ `<select>` và hiển thị `"Locked to <mode>. Use the switch button to change."`, đồng thời bỏ qua ghi `_align_editor_mode` trong `save_classic_meta()` khi bài đã khóa.
    - Endpoint `/save-page` (`class-builder-crud-handler.php`): Trả `WP_Error` `409` (`editor_mode_locked`) nếu bài đang khóa ở `gutenberg`.
  - **Khóa nút Switch khi đang Save**: Nút `#align-mode-switcher-btn` tự động `disabled` (hiển thị `"Saving…"`) khi `isSavingPost()`, `isAutosavingPost()`, `isSavingNonPostEntityChanges()`, `AlignSaveCoordinator.isBusy()`, hoặc request switch đang chạy.
- **Đổi Editor sau khi đã khóa (`POST /wp-json/align-builder/v1/switch-editor-mode`)**:
  - Bấm nút switch trên bài đã khóa sẽ mở hộp thoại cảnh báo (đã sanitize `typeLabel` chống DOM XSS): xác nhận xóa toàn bộ nội dung hiện tại để bắt đầu canvas trống ở editor mới (kèm tự động lưu revision backup nếu post type hỗ trợ revisions).
  - Yêu cầu quyền `edit_post` trên bài đó + REST nonce (không giới hạn role Admin).
  - Xóa `post_content = ''`, nếu rời Builder thì gọi `Align_Builder_Asset_Cleaner::delete_post_assets( $post_id )` và xóa `_align_reusable_overrides`, sau đó đặt `_align_editor_mode = target_mode`, `_align_editor_locked = '0'` rồi reload trang.
- **Kiểm thử tự động**:
  - Bộ kiểm thử `align/tests/builder-editor-mode.test.cjs` (19/19 tests PASS), `align/tests/builder-template-mode-lock.test.cjs` (6/6 tests PASS) và `align/tests/builder-save-coordinator.test.cjs` (13/13 tests PASS, gồm template ở chế độ Gutenberg không bị coi là embedded, giữ header/footer, post-content attrs, blockId ổn định, không `resetBlocks`).

### 4.8 Tích Hợp WordPress FSE Site Editor Cho Templates (Hybrid Model: FSE Template Parts + Align Content Block)
- **Bài toán & Lý do dùng FSE đi kèm Align Builder (Architecture Rationale)**:
  - **Align Visual Builder** chuyên trách về thiết kế layout, kéo thả visual, styling chi tiết từng section nội dung (WYSIWYG, CSS tự do, responsive controls).
  - **WordPress FSE (Full Site Editing)** chuyên trách về cấu trúc toàn trang (Global Architecture): Header toàn trang (navigation menu, logo, sticky bar), Footer toàn trang (copyright, columns, social icons), và Template Hierarchy của WordPress (`404.html`, `archive.html`, `single.html`...).
  - **Tại sao không thiết kế Header/Footer cứng trong Align Builder?**
    - Nếu mỗi template nhúng cứng mã HTML của Header/Footer, khi người dùng đổi logo hoặc thêm mục menu ở Header, họ sẽ phải mở hàng chục template để sửa lại từng cái một $\rightarrow$ Gây phân mảnh dữ liệu và vi phạm nguyên lý DRY.
    - Làm mất khả năng tận dụng các tính năng bản địa của WordPress FSE (Block Template Parts).
  - **Giải pháp: Mô hình Hybrid (FSE Template Parts bọc quanh Align Custom HTML Block)**:
    - File template (ví dụ `align-child/templates/404.html` hoặc DB post `wp_template` ID 42) có cấu trúc chuẩn hóa:
      ```html
      <!-- wp:template-part {"slug":"header","tagName":"header"} /-->
      <!-- wp:align/custom-html {"blockId":"align-yyn90qr7"} -->
      ... [Mã HTML thiết kế section từ Align Visual Builder] ...
      <!-- /wp:align/custom-html -->
      <!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
      ```
    - Trong **Site Editor** (`site-editor.php`), người dùng quản lý vị trí Header, Footer và các block toàn trang.
    - Khi muốn chỉnh sửa nội dung section của template: Bấm `[ Edit with Align Builder ]`. Align Builder chỉ mở nhúng phần nội dung section để biên tập trực quan.
    - Builder nạp template sẽ loại bỏ mọi `wp:template-part`, nên khi lưu, cả hai đường dùng chung một quy tắc để gắn lại:
      - Site Editor: `AlignTemplateMarkup.build()` (JS). Builder đứng riêng: `Align_Builder_Template_Structure::wrap()` (PHP).
      - Lấy các template-part liền đầu và liền cuối của nội dung đã lưu (giữ nguyên slug và attrs như `theme`). Template chỉ có template-part: tách theo `tagName`/slug `footer`.
      - Nội dung đã lưu rỗng và slug thuộc `home, page, single, index, archive, search, 404`: gắn `header`/`footer` mặc định. Template không có header (ví dụ landing) giữ nguyên, không bị thêm.
      - Giới hạn: template-part nằm giữa nội dung chính không được giữ lại.

### 4.9 Đồng Bộ Định Danh Ảnh Media Modal & Cơ Chế Tự Chữa Lành (Image Resolver Self-Healing)
- **Vấn đề đã giải quyết**: Khi thay đổi ảnh trong template (ví dụ ảnh xe 404 trong thẻ `<picture>`), sau khi lưu và F5 lại trang, ảnh bị revert về ảnh gốc cũ (`404.webp`).
- **Nguyên nhân gốc rễ**:
  1. Thẻ wrapper `<picture>` giữ nguyên thuộc tính `data-id` của ảnh cũ, trong khi thẻ con `<img>` được gán URL của ảnh mới.
  2. Hàm `findClosest` trong `wp-media-modal.js` thiếu khai báo biến `bestScore` dẫn đến `ReferenceError`.
  3. `class-builder-image-resolver.php` khi giải mã dynamic srcset trên server dựa vào `data-id` của wrapper `<picture>` cũ để tra cứu attachment, vô tình ghi đè lại URL ảnh cũ đè lên ảnh mới.
- **Giải pháp**:
  - `wp-media-modal.js`: Sửa khai báo `bestScore`, đồng bộ `data-id` và `wp-image-{ID}` xuyên suốt trên cả `<picture>` và `<img>`, phát tín hiệu dirty ngay khi đổi ảnh.
  - `class-builder-image-resolver.php`: Thêm cơ chế **Self-Healing**: Nếu phát hiện `data-id` của wrapper `<picture>` không khớp với `data-id` hoặc `src` của thẻ `<img>` con bên trong, resolver tự động coi `<img>` là nguồn chân lý (Source of Truth), tra cứu attachment mới và tự phục hồi lại `srcset` / `data-id` chính xác.

### 4.7 Đồng Bộ Điều Hướng Toàn Cục, Tạo Trang Mới & Kháng Redirect Loop (Synchronized Navigation & Loop Guard)
- **Vấn đề đã giải quyết**:
  1. Khi người dùng đang ở trang A (ví dụ `post.php?post=582&action=edit`) và bấm tạo trang mới B (`test`) hoặc click chuyển trang trong cây Pages sidebar / đổi ngôn ngữ, nếu chỉ chuyển hướng trong iframe (`window.location.href`), cửa sổ cha Gutenberg sẽ bị kẹt lại ở trang A. Dẫn đến tiêu đề Gutenberg topbar và store `core/editor` bị lệch với canvas, gây nguy cơ lưu đè dữ liệu trang A khi bấm Update.
  2. Khi truy cập trực tiếp URL standalone builder `admin.php?page=align-builder&post_id=...` trên tab trình duyệt, giao diện builder bị cô lập ngoài Gutenberg.
  3. Lệch ngữ cảnh đa ngôn ngữ (`lang=en` trên Gutenberg nhưng thiếu trên iframe nhúng) kết hợp với việc can thiệp vào hàm nạp canvas `loadPage` nội bộ dẫn đến hiện tượng tải lại trang lặp vô hạn (Infinite Redirect Loop).
- **Cơ chế đồng bộ & Bảo vệ đa tầng**:
  - **Server-Side Guard (`class-builder-admin.php`)**: Tự động 302 redirect mọi request trực tiếp vào `admin.php?page=align-builder&post_id={ID}` của Page/Post (không có `embedded=1`) về thẳng Gutenberg `post.php?post={ID}&action=edit`, bảo toàn nguyên vẹn tham số `lang`.
  - **REST API Endpoint (`class-builder-crud-handler.php`)**: Trả về `editor_url` và `admin_edit_url` trỏ thẳng tới Gutenberg `post.php?post={NEW_ID}&action=edit`.
  - **Khi tạo trang mới (`wp-items-crud.js`)**: Điều hướng `window.top || window.parent || window` về `post.php?post={NEW_ID}&action=edit` và phát postMessage `ALIGN_NAVIGATE`.
  - **Khi chọn trang trong sidebar tree (`wp-filemanager.js`)**: Phương thức tập trung `navigateToItem` phát hiện item đích khác item hiện tại, kiểm tra unsaved changes và chuyển hướng cửa sổ cha về đúng `post.php?post={ID}&action=edit`. Giữ nguyên hàm render canvas `loadPage` nội bộ, chỉ kích hoạt khi có click chủ động của người dùng.
  - **Bảo toàn ngữ cảnh Đa ngôn ngữ (`align-site-editor-integration.js`)**: Tự động truyền tham số `&lang={code}` từ Gutenberg vào URL của iframe nhúng.
  - **Cơ chế chống vòng lặp (Loop Prevention Guard)**: Cả `navigateToItem` và listener `ALIGN_NAVIGATE` đều so sánh URL đích với URL hiện tại của cửa sổ điều hướng; nếu trùng khớp, lệnh điều hướng bị bỏ qua ngay lập tức.
- **Kiểm thử tự động**: Bảo vệ bởi bộ test `align/tests/builder-create-navigation.test.cjs` và `align/tests/builder-filemanager-navigation.test.cjs` (18/18 tests pass).

