# Align Core: Universal Password Lock Architecture (v1)

## 1. Overview & Objective

The **Align Universal Password Lock** engine provides robust, singular-level password protection across all public post types (pages, posts, and custom post types). It completely replaces the legacy WordPress native password system (`post_password_required()`, `wp-login.php?action=postpass`, and cleartext database storage) with a modern, high-performance, and secure access guard.

---

## 2. Key Limitations of Native WordPress Password Protection

1. **Cleartext Password Storage**: Native WordPress stores the password directly in the `post_password` column of `wp_posts` in plaintext.
2. **Template Quirks & Content Leakage**: Native `post_password_required()` only filters `the_content` and `the_excerpt`. Page headers, custom template parts, hero blocks, and visual builder elements often render regardless, exposing layout structures and private meta.
3. **SEO & Caching Risks**: Native password protected posts can be indexed by search engines with generic descriptions, or cached improperly by edge CDN rules.
4. **Poor Visitor UX**: The native WordPress password form is coupled to theme templates and forces a round-trip form submission to `wp-login.php`.

---

## 3. Architecture & Enforcement Lifecycle

### 3.1 Interception Layer (`template_redirect`, Priority 1)

```
Incoming Singular Request
           │
           ▼
[is_singular() & is_post_locked()] ──No──► Normal Template Execution
           │
          Yes
           │
           ▼
[is_preview() || current_user_can('edit_post')] ──Yes──► Normal Template Execution (Bypass)
           │
           No
           │
           ▼
[verify_cookie( $post_id )] ──Valid──► Normal Template Execution (Unlocked)
           │
        Invalid
           │
           ▼
Send Anti-Indexing & Anti-Caching Headers (HTTP 200)
           │
           ▼
Render Minimal HTML Prompt Shell (window.prompt() + REST fetch)
           │
           ▼
         exit;
```

1. **Interception Point**: Hooked at `template_redirect` with priority `1` inside `Align_Post_Password_Lock::intercept_locked_post()`.
2. **Strict HTTP Headers**:
   - `HTTP/1.1 200 OK` (avoids 404 or crawler redirection traps).
   - `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet`.
   - `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`.
   - `Pragma: no-cache`.
   - `Expires: 0`.
3. **SEO Barrier Persistence**: Even when unlocked for an authorized visitor, `send_headers` and `wp_robots` filters ensure search engines never index or cache locked content.

---

## 4. Cryptographic Authentication & Cookie Protocol

### 4.1 Cookie Format
- **Cookie Name**: `align_post_lock_{post_id}`
- **Cookie Value**: `{expiry}.{hmac}`
- **Cookie Flags**: `HttpOnly`, `SameSite=Lax`, `Secure` (when SSL is active).

### 4.2 HMAC Generation & Validation
```php
HMAC = hash_hmac( 'sha256', "{$post_id}|{$expiry}|{$version}", wp_salt( 'auth' ) );
```

- **Post Version Counter (`_align_password_lock_version`)**:
  Every time a post password is updated or the lock is disabled, `_align_password_lock_version` increments.
  This immediately and automatically invalidates all previously issued visitor authentication cookies without requiring database table cleanups.

---

## 5. REST Verification API & Rate Limiting

- **Endpoint**: `POST /wp-json/align/v1/post-password/verify`
- **Permissions**: Public (`__return_true`)
- **Payload**:
  ```json
  {
    "post_id": 123,
    "password": "user_submitted_password"
  }
  ```

### 5.1 Rate Limiting Defense
To prevent brute-force attacks on singular passwords:
- Tracks failed verification attempts via WordPress Transients: `align_pwlock_fail_{md5(ip_postid)}`.
- Maximum 5 failed attempts per 5 minutes per IP address.
- Returns HTTP 429 (`Too many failed attempts`) when the threshold is exceeded.

### 5.2 Password Verification & Auto-Upgrade
- Checks password against phpass hash stored in `_align_password_lock_hash` using `wp_check_password()`.
- **Legacy Fallback & Auto-Migration**: If no hash exists but the legacy `wp_posts.post_password` column contains a password, it verifies and immediately hashes the password, stores `_align_password_lock_hash`, and clears `wp_posts.post_password`.

---

## 6. Editor Integration & Native UI Suppression

### 6.1 Gutenberg (Block Editor) & Classic Editor
- Password Lock controls have been **removed** from both Gutenberg Document Sidebar (`align-page-settings.js`) and Classic Editor meta box (`class-align-page-settings.php`) to centralize credential management in Quick Edit and prevent accidental autosave/redux leaks.
- **Native UI Suppression**: Still fully active in both editors. Inlines CSS hiding `.editor-post-visibility__dialog-password`, `.components-radio-control__option:has(input[value="password"])`, and `#visibility-radio-password`.
- **Store Guard**: Automatically wipes any accidental `password` property in `core/editor` store.

### 6.2 WordPress Quick Edit (`edit.php`) - Primary Management Interface
- **Class**: `Align_Quick_Edit` in `align/core/includes/admin/class-align-quick-edit.php`.
- **Script**: `align/core/assets/admin/js/align-quick-edit.js`.
- **Dedicated Card Layout**: Rendered in a dedicated, clean card below Status and Private (`.align-quick-edit-password-wrap`), completely isolated from the native `.inline-edit-group`.
- **Cleartext Password Display**:
  - Unprotected post: Field is empty (`""`), badge shows `[No Password]`. Entering a password and saving activates lock and `noindex`.
  - Protected post: Field displays the actual plaintext password so administrators never forget what password was configured. Badge shows `[Password Set]`.
  - Live typing feedback: Modifying the password transitions badge to `[New Password]`. Clearing the input transitions badge to `[Will Remove on Save]`.
- **Empty-Detection Lifecycle**:
  - Non-empty input (`$raw_password !== ''`): Saves plaintext password to `_align_password_lock_password` and bcrypt hash to `_align_password_lock_hash`, sets lock active (`_align_password_lock_enabled = '1'`).
  - Empty input (`$raw_password === ''`): Deletes meta keys, sets `_align_password_lock_enabled = '0'`, unlocks the post and restores indexing.
- **Cache-Busting Asset Loading**: Enqueues `align-quick-edit.js` using `filemtime()` timestamp version to guarantee instant client updates without browser cache stagnation.
- **Save Handler**: Validates capability `edit_post`, verifies nonces (`align_quick_edit_nonce` or `_inline_edit`), and always forces `wp_posts.post_password = ''` in database.

---

## 7. Embedded Content Placeholder Guard

In `Align_Post_Content::render()` (responsible for rendering builder blocks, template parts, and content widgets):
```php
if ( class_exists( 'Align_Post_Password_Lock' ) && Align_Post_Password_Lock::is_locked_for_visitor( $post ) ) {
    return Align_Post_Password_Lock::render_embedded_placeholder( $post );
}
```
When a locked post is rendered inside another page via a template part or shortcode, it cleanly outputs:
```html
<!-- [Align PasswordLock] Content locked -->
```
This prevents sensitive child contents from leaking inside multi-post aggregator layouts.

---

## 8. Database Schema & Meta Keys

| Key | Storage | Description |
|---|---|---|
| `_align_password_lock_enabled` | `wp_postmeta` | `'1'` (enabled) or `'0'` (disabled). |
| `_align_password_lock_password` | `wp_postmeta` | Plaintext password string for authorized admin display in Quick Edit. |
| `_align_password_lock_hash` | `wp_postmeta` | Secure phpass/bcrypt hash generated via `wp_hash_password()` for visitor authentication. |
| `_align_password_lock_secret` | `wp_postmeta` | AES-256-CBC encrypted backup string with `wp_salt('auth')`. |
| `_align_password_lock_version` | `wp_postmeta` | Integer counter for visitor cookie revocation on update/removal. |
| `post_password` | `wp_posts` | Always kept empty (`''`) to suppress native WordPress behavior. |

---

## 9. Observability & Security Compliance

- **Log Prefix**: `[Align PasswordLock]`
- **Privacy Rule**: Cleartext passwords, hashes, salts, and raw cookie values are **NEVER** logged.
- **File Size Constraint**: All related source files are kept strictly under 500 lines.
