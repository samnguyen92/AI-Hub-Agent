# 🚀 Kế Hoạch Triển Khai SortableJS Swap Cho Builder Section

Tài liệu chi tiết về lộ trình và hướng dẫn kỹ thuật tích hợp cơ chế kéo thả đổi chỗ (Swap) các section trong Align Builder (hỗ trợ cả Sidebar Section List và trực tiếp trong Iframe Canvas).

---

## 📌 Tổng Quan Kiến Trúc
- **Thư viện sử dụng**: SortableJS v1.15.6 Complete bundle (tích hợp sẵn plugin `Swap`, `AutoScroll`, `MultiDrag`).
- **Vị trí tài nguyên**: `core/builder/libs/sortable/sortable.complete.min.js`.
- **Mục tiêu**: Cho phép người dùng hoán đổi vị trí (Swap) giữa 2 section nhanh chóng, có hiệu ứng mượt mà, đồng bộ hai chiều giữa Sidebar và Iframe Canvas, tích hợp hoàn chỉnh với hệ thống `Vvveb.Undo`.

---

## 🗺️ Lộ Trình 4 Giai Đoạn

### ✅ Giai Đoạn 1: Setup Thư Viện & Nạp Vào Builder (ĐÃ HOÀN THÀNH)
- [x] Tạo thư mục `core/builder/libs/sortable/` chứa:
  - `sortable.complete.min.js` (45KB - Production bundle).
  - `sortable.complete.js` (126KB - Full source).
- [x] Cập nhật `core/builder/editor.html` để nhúng script `libs/sortable/sortable.complete.min.js` ngay sau Bootstrap JS và trước `builder.js`.
- [x] Đã nghiệm thu QC: Cú pháp chuẩn xác, không xung đột scripts, `window.Sortable` sẵn sàng hoạt động.

---

### ✅ Giai Đoạn 2: Tích Hợp SortableJS Swap & Nested Re-parenting Vào Sidebar Section List & Tree Navigator (ĐÃ HOÀN THÀNH)
**Mục tiêu**: Nâng cấp danh sách section ở thanh Sidebar bên trái (`.sections-container`) và cây Cấu trúc Navigator (`#tree-list`) sang SortableJS với khả năng hoán đổi cùng cấp (Swap/Reorder), lồng vào phần tử con (Nesting Inside), và di chuyển ra ngoài danh sách cha (Cross-Parent Re-parenting).

#### Các giải pháp kỹ thuật đã hoàn thiện:
1. **Sidebar Section List (`builder-section-list.js`)**:
   - Sử dụng `new Sortable(container, { swap: true, swapClass: 'sortable-swap-highlight', animation: 150, handle: '.section-item' })` để hoán đổi 2 section top-level.
   - Ghi nhận mutation `type: 'swap'` vào Undo manager và đồng bộ Tree Navigator.
2. **DOM Tree Navigator Vertical 3-Zone / 2-Zone Trigger (`builder-tree-sortable.js`)**:
   - Sử dụng `document.elementFromPoint` và tính toạ độ trên **Row Label (`:scope > label` ~26px)** thay vì toàn bộ subtree `<li>` (tránh lỗi lệch tỉ lệ khi node có nhiều con).
   - **Phân loại đích thả qua `Vvveb.TreeList.canAcceptChildren(targetNode)`**:
     - **Container Elements** (`div`, `section`, `article`, `main`, `header`, `footer`, `nav`, `aside`, `blockquote`, `ul`, `ol`, `li`, `form`, `fieldset`, `figure`, `table`...) hỗ trợ **3-Zone Trigger**:
       - **Top 25%**: `dropMode = "before"` (Chèn trước làm sibling).
       - **Middle 50%**: `dropMode = "inside"` (Thêm vào làm con của container đích) -> Viền sáng bao trọn node `.tree-drop-inside`.
       - **Bottom 25%**: `dropMode = "after"` (Chèn sau làm sibling).
     - **Leaf / Typography / Media / Blackbox Elements** (`_leafTags`: `p`, `h1`–`h6`, `span`, `a`, `button`, `label`, `img`, `input`, `textarea`, `select`, `option`, `iframe`, `video`, `audio`, `picture`, `svg`, `canvas`, `code`, `pre`, `strong`, `em`... và các khối `[data-component-shortcode]`, `[data-component-wp-block]`, `[data-component-post-content]`) tự động chuyển sang **2-Zone Trigger (50% `before` / 50% `after`)**, ngăn chặn tuyệt đối việc lồng nhầm thẻ vào bên trong thẻ chữ hoặc media.
   - **Zero Live-DOM Mutation**: Cấu hình `group: { name: 'tree-navigator', pull: false, put: false }`, `sort: false` và `onMove` trả về `false` để SortableJS không bao giờ tự di chuyển phần tử `<li>` qua lại giữa các danh sách `<ol>` trong lúc đang kéo.
   - **Zero-Shift Drop Indicator (`editor-tree-navigator.css`)**: Sử dụng `box-shadow: inset 0 2px 0 0 #0d6efd` (`.tree-drop-before > label`) và `box-shadow: inset 0 -2px 0 0 #0d6efd` (`.tree-drop-after > label`) thay cho `border-top`/`border-bottom`, giữ chiều cao dòng 26px cố định tuyệt đối (`0px` lệch chữ).
3. **Thao Tác DOM-First, Stable AST IDs & Coalesced Render Scheduler (`builder-ast-parser.js`, `builder-tree-render.js`)**:
   - **DOM-First Manipulation**: Kéo-thả trên Tree Navigator (`builder-tree-sortable.js`) thực hiện trực tiếp `insertBefore` / `appendChild` trên DOM Canvas, ghi nhận mutation `type: 'move'` vào `Vvveb.Undo`, tự động mở rộng nhánh cha khi drop `inside`, và chọn node theo tham chiếu DOM trực tiếp (`nodeA`).
   - **Stable AST IDs (`AstParser.syncDom`)**: Giữ nguyên `data-ast-id` hiện có trên các DOM node, tự động phát hiện và cấp ID mới (`ast_N`) cho node chưa có ID hoặc node clone bị trùng ID (`duplicatesFixed`), đảm bảo `expandedAstIds` không bao giờ bị lệch.
   - **Coalesced Render (`builder-tree-render.js`)**: Gộp mọi lời gọi `loadComponents()` trong cùng 1 frame qua `requestAnimationFrame` thành 1 lần render duy nhất, bảo toàn `scrollTop` của `.tree` và hàng đợi `_pendingSelectNode`.

---

### ✅ Giai Đoạn 3: Tích Hợp 3-Zone Nesting & Cross-Parent Re-parenting (ĐÃ HOÀN THÀNH)
**Mục tiêu**: Cho phép người dùng kéo đổi chỗ và lồng phần tử xuyên cấp cha-con (3-zone hit testing: `before`, `inside`, `after`) mượt mà không chớp cây.

#### Các giải pháp kỹ thuật đã hoàn thiện:
1. **3-Zone / 2-Zone Hit Testing trên Row Label (`builder-tree-sortable.js`)**:
   - Container: Top 25% `before` | Middle 50% `inside` | Bottom 25% `after`. Leaf/Typography/Media/Blackbox (`canAcceptChildren(targetNode) === false`): 50% `before` | 50% `after`.
   - Chặn kéo phần tử cha vào bên trong con cháu của chính nó (`nodeA.contains(targetNode)`).
2. **Coalesced Tree Render & Scroll Preservation (`builder-tree-render.js`)**:
   - Gộp nhiều lệnh gọi `Vvveb.TreeList.loadComponents()` trong cùng 1 frame thành 1 lần render duy nhất.
   - Bảo toàn `scrollTop` và `expandedAstIds` qua các lần thao tác.
3. **Đồng bộ Debounced Code Editor (`builder-code-editors.js`)**:
   - Đồng bộ ngược từ DOM sang CodeMirror 6 qua `scheduleSync(300)` khi Code Editor đang mở, tránh serialize toàn trang đồng bộ ở mỗi thao tác kéo-thả.

---

### ✅ Giai Đoạn 4: Tích Hợp Undo/Redo Toàn Diện & Xử Lý Edge Cases (ĐÃ HOÀN THÀNH)
**Mục tiêu**: Đảm bảo `Ctrl/Cmd+Z` và `Ctrl/Cmd+Shift+Z` hoàn tác/làm lại chính xác mọi thao tác kéo-thả, nhân bản (Duplicate), dán (Paste) và xóa (Delete).

#### Các bước đã hoàn thiện:
1. **Hỗ trợ đầy đủ mutation `swap`, `move`, `childList` trong `core/builder/libs/builder/undo.js`**:
   - `swap`: Hoán đổi vị trí 2 section top-level trong `builder-section-list.js`.
   - `move`: Lưu `oldParent`, `newParent`, `oldNextSibling`, `newNextSibling` cho mọi thao tác kéo-thả 3-zone trong `builder-tree-sortable.js`.
   - `childList`: Ghi nhận đầy đủ khi Duplicate (`cloneSelectedNodes`), Paste (`pasteCopiedElement`), và Delete (`deleteSelectedNodes`).
2. **Xử lý Edge Cases**:
   - Vô hiệu hóa kéo thả khi đang ở chế độ Content Mode (`Vvveb.Builder.contentMode === true`).
   - Dọn dẹp (`destroy()`) toàn bộ SortableJS instances cũ trước khi khởi tạo lại trong `initSortable()` để chống rò rỉ bộ nhớ.
   - Giữ nguyên instance ID độc lập khi nhân bản Reusable Block (`_disambiguateReuseBlockDom`).

---
*Tài liệu này đã hoàn tất nghiệm thu toàn bộ 4 giai đoạn.*
