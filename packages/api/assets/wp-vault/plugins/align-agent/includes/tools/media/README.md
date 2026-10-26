# Media tools

**Registry group:** `media`  
**Classes:** `Align_Agent_Tools_Media` (`class-agent-tools-media.php`)

## Tools

### `list_media`
- **Type:** read
- **Capability:** `upload_files`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `mime_type` (string, optional) — Filter MIME type: image/jpeg, image/png, image/webp, image/gif, or any. Default 'any'.
  - `search` (string, optional) — Search keyword in title, caption, or description.
  - `page` (integer, optional) — Page number (default 1).
  - `per_page` (integer, optional) — Items per page (default 20, max 50).
- **Calls:** `get_posts`
- **Guards:** Capability check (`upload_files`).

### `set_featured_image`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Target post/page ID.
  - `media_id` (integer, required) — Attachment ID to assign, or 0 to remove.
- **Calls:** `set_post_thumbnail`, `delete_post_thumbnail`, `get_post`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Post existence, attachment existence, capability check (`edit_post`, `$post_id`).

### `attach_media`
- **Type:** write (needs approval)
- **Capability:** `upload_files`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `media_id` (integer, required) — Attachment ID to attach.
  - `post_id` (integer, required) — Target post ID to attach to.
- **Calls:** `wp_update_post`, `get_post`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Attachment existence, post existence, capability check.

### `sideload_media`
- **Type:** write (needs approval)
- **Capability:** `upload_files`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `url` (string, required) — Direct HTTPS image URL to download. Must appear verbatim in user message.
  - `post_id` (integer, optional) — Post ID to attach the uploaded image to (default 0).
  - `desc` (string, optional) — Image description or alt text.
- **Calls:** `download_url`, `wp_check_filetype_and_ext`, `wp_handle_sideload`, `wp_insert_attachment`, `wp_generate_attachment_metadata`, `wp_update_attachment_metadata`, `Align_Agent_Audit_Log::log_action`
- **Guards:** HTTPS scheme required, URL must appear verbatim in the active user conversation, permitted image extensions (`jpg|jpeg|png|webp|gif`, strictly NO SVG), file size limit <= 5 MB, MIME verification via `wp_check_filetype_and_ext`.

### `update_media_meta`
- **Type:** write (needs approval)
- **Capability:** `upload_files`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `media_id` (integer, required) — Attachment ID.
  - `title` (string, optional) — Attachment title.
  - `alt_text` (string, optional) — Alt text for accessibility.
  - `caption` (string, optional) — Excerpt / caption text.
  - `description` (string, optional) — Content description.
- **Calls:** `wp_update_post`, `update_post_meta`, `get_post`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Attachment existence, capability check (`edit_post`, `$media_id`).

### `trash_media`
- **Type:** write (needs approval)
- **Capability:** `delete_posts`
- **Requires Builder:** no
- **Danger:** yes
- **Parameters:**
  - `media_id` (integer, required) — Attachment ID to move to trash.
- **Calls:** `wp_delete_attachment( $id, false )`, `get_post`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Constant `MEDIA_TRASH` must be enabled to prevent permanent deletion; attachment must not currently be in use (neither as featured image nor referenced in published post content); `danger: true` flag.

## Notes
- `sideload_media` enforces strict SSRF protections: only HTTPS URLs that match verbatim in user-sent messages are permitted.
- SVG uploads are blocked to eliminate XSS attack surfaces.
- Media deletion is strictly trash-only and guarded against orphaning featured images.
