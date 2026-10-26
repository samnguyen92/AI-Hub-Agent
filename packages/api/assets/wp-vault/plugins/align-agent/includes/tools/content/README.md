# Content tools

**Registry group:** `content`  
**Classes:**
- `Align_Agent_Tools_Pages` (`class-agent-tools-pages.php`)
- `Align_Agent_Tools_Posts` (`class-agent-tools-posts.php`)
- `Align_Agent_Tools_Clone` (`class-agent-tools-clone.php`)
- `Align_Agent_Tools_Asset_History` (`class-agent-tools-asset-history.php`)
- `Align_Agent_Tools_Post_Lock` (`class-agent-tools-post-lock.php`)
- `Align_Agent_Tools_Finder` (`class-agent-tools-finder.php`)
- `Align_Agent_Tools_Patch` (`class-agent-tools-patch.php`)
- `Align_Agent_Working_Notes` (`includes/class-agent-working-notes.php`)

## Tools

### `list_pages`
- **Type:** read
- **Capability:** `manage_options`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `status` (string, optional) — Post status: publish, draft, pending, private, trash, or any. Default 'any'.
  - `search` (string, optional) — Search keyword.
  - `page` (integer, optional) — Page number for pagination. Default 1.
  - `per_page` (integer, optional) — Items per page (default 20, max 50).
- **Calls:** `Align_Gateway_Pages::handle_get_pages` / `get_posts`, `get_post_meta`
- **Guards:** Capability check (`manage_options`).

### `get_page`
- **Type:** read
- **Capability:** `manage_options`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `page_id` (integer, required) — Numeric WordPress page ID.
- **Calls:** `Align_Editor_Mode_Lock::get_effective_mode`, `Align_Editor_Mode_Lock::is_locked`, `Align_Gateway_Pages::extract_decoupled_parts`, `get_post`
- **Guards:** Capability check, post existence check, post_type === 'page' check.

### `get_revisions`
- **Type:** read
- **Capability:** `manage_options`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `page_id` (integer, required) — Numeric WordPress page ID.
  - `limit` (integer, optional) — Max revisions to return (default 10, max 30).
- **Calls:** `Align_Builder_Revision_Handler::get_revisions`
- **Guards:** Capability check, page existence check.

### `validate_markup`
- **Type:** read
- **Capability:** `manage_options`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `html` (string, required) — HTML content to validate.
- **Calls:** `Align_Gateway_Validator::validate_markup`
- **Guards:** Non-empty HTML check.

### `save_page`
- **Type:** write (needs approval)
- **Capability:** `manage_options`
- **Requires Builder:** yes
- **Danger:** no
- **Parameters:**
  - `page_id` (integer, required) — Numeric WordPress page ID.
  - `html` (string, optional) — Complete page HTML content.
  - `css` (string, optional) — Custom CSS stylesheet.
  - `js` (string, optional) — Custom JavaScript.
  - `title` (string, optional) — Page title.
  - `status` (string, optional) — Post status: publish, draft, pending, private.
- **Calls:** `Align_Editor_Mode_Lock::is_locked_to`, `Align_Agent_Tools_Pages::guard_html_and_css`, `Align_Gateway_Validator::validate_markup`, `Align_Builder_Crud_Handler::save_page`, `Align_Builder_Crud_Handler::rename_item`, `Align_Builder_Persistence_Service::sync_post_assets_from_content`
- **Guards:** Editor mode lock (`gutenberg`), `<script>` tag blocking, markup validation, page existence check.

### `create_page`
- **Type:** write (needs approval)
- **Capability:** `manage_options`
- **Requires Builder:** yes
- **Danger:** no
- **Parameters:**
  - `title` (string, required) — Page title.
  - `html` (string, required) — Complete page HTML content.
  - `css` (string, optional) — Custom CSS stylesheet.
  - `js` (string, optional) — Custom JavaScript.
  - `status` (string, optional) — Post status: publish, draft, pending, private. Default 'draft'.
- **Calls:** `Align_Agent_Tools_Pages::guard_html_and_css`, `Align_Gateway_Validator::validate_markup`, `Align_Builder_Crud_Handler::create_item`, `Align_Builder_Crud_Handler::save_page`, `Align_Builder_Persistence_Service::sync_post_assets_from_content`
- **Guards:** Non-empty title/html, `<script>` tag blocking, markup validation, allowed status validation.

### `restore_revision`
- **Type:** write (needs approval)
- **Capability:** `manage_options`
- **Requires Builder:** yes
- **Danger:** no
- **Parameters:**
  - `page_id` (integer, required) — Numeric WordPress page ID.
  - `revision_id` (integer, required) — Numeric WordPress revision ID to restore.
- **Calls:** `Align_Editor_Mode_Lock::is_locked_to`, `Align_Builder_Revision_Handler::restore_revision`, `Align_Builder_Persistence_Service::sync_post_assets_from_content`
- **Guards:** Editor mode lock (`gutenberg`), page existence, revision existence.

### `apply_page_clone`
- **Type:** write (needs approval)
- **Scope:** `global`
- **Capability:** `manage_options`
- **Requires Builder:** yes
- **Danger:** no
- **Hidden:** yes (filtered from OpenAI schema, only server-queued)
- **Parameters:**
  - `clone_id` (integer, required) — Numeric ID of the draft clone post.
  - `preview_url` (string, optional) — Preview URL for draft inspection.
- **Calls:** `Align_Agent_Page_Clone::apply`, `Align_Editor_Mode_Lock::is_locked_to`, `Align_Gateway_Validator::validate_markup`, `Align_Builder_Crud_Handler::save_page`, `wp_trash_post`
- **Guards:** Gutenberg editor mode lock, original post modification concurrency check (`base_changed`), markup validator, capability check.

### `list_post_types`
- **Type:** read
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:** None
- **Calls:** `get_post_types`
- **Guards:** Capability check (`edit_posts`).

### `list_posts`
- **Type:** read
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_type` (string, optional) — Target post type slug or "any" (default public types).
  - `post_status` (string, optional) — Comma-separated statuses: publish,draft,private (default).
  - `s` (string, optional) — Keyword search query.
  - `paged` (integer, optional) — Page number (default 1).
  - `posts_per_page` (integer, optional) — Items per page (default 20, max 50).
- **Calls:** `get_posts`, `get_post_meta`, `get_post_type_object`
- **Guards:** Capability check (`edit_posts`).

### `get_post`
- **Type:** read
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric WordPress post ID.
- **Calls:** `Align_Editor_Mode_Lock::get_effective_mode`, `Align_Editor_Mode_Lock::is_locked`, `Align_Gateway_Pages::extract_decoupled_parts`, `get_post`
- **Guards:** Post existence check, capability check (`edit_post`, `$post_id`).

### `create_post`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** yes
- **Danger:** no
- **Parameters:**
  - `title` (string, required) — Post title.
  - `post_type` (string, optional) — Post type slug (default "post").
  - `html` (string, optional) — Decoupled HTML markup for post content.
  - `css` (string, optional) — Custom CSS styling.
  - `js` (string, optional) — Custom JavaScript interactions.
  - `post_status` (string, optional) — Post status: draft, publish, pending, private (default "draft").
  - `excerpt` (string, optional) — Post excerpt.
- **Calls:** `Align_Agent_Tools_Pages::guard_html_and_css`, `Align_Gateway_Validator::validate_markup`, `Align_Builder_Crud_Handler::create_item`, `Align_Builder_Crud_Handler::save_page`, `Align_Builder_Persistence_Service::sync_post_assets_from_content`, `wp_insert_post`
- **Guards:** Non-empty title, `<script>` tag blocking, markup validation, allowed post status.

### `save_post`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** yes
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric WordPress post ID to update.
  - `html` (string, optional) — Decoupled HTML markup for post content.
  - `css` (string, optional) — Custom CSS styling.
  - `js` (string, optional) — Custom JavaScript interactions.
  - `title` (string, optional) — Post title.
  - `post_status` (string, optional) — Post status: publish, draft, pending, private.
- **Calls:** `Align_Editor_Mode_Lock::is_locked_to`, `Align_Agent_Tools_Pages::guard_html_and_css`, `Align_Gateway_Validator::validate_markup`, `Align_Builder_Crud_Handler::save_page`, `Align_Builder_Crud_Handler::rename_item`, `Align_Builder_Persistence_Service::sync_post_assets_from_content`
- **Guards:** Editor mode lock (`gutenberg`), `<script>` tag blocking, markup validation, post existence check.

### `set_post_status`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric post ID.
  - `status` (string, required) — Target status: publish, draft, pending, private.
- **Calls:** `wp_update_post`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Allowed statuses check (`publish`, `draft`, `pending`, `private`), post existence.

### `trash_post`
- **Type:** write (needs approval)
- **Capability:** `delete_posts`
- **Requires Builder:** no
- **Danger:** yes
- **Parameters:**
  - `post_id` (integer, required) — Numeric post ID to move to trash.
- **Calls:** `wp_trash_post`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Post existence check, safe deletion policy (never permanent delete, only `wp_trash_post`).

### `restore_post`
- **Type:** write (needs approval)
- **Capability:** `delete_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric post ID to restore from trash.
- **Calls:** `wp_untrash_post`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Post existence, post_status === 'trash' check.

### `duplicate_post`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** yes
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric post ID to clone.
  - `title` (string, optional) — New post title (default: [Original Title] (Copy)).
  - `status` (string, optional) — Target status (default: draft).
- **Calls:** `Align_Builder_Crud_Handler::create_item`, `Align_Builder_Crud_Handler::save_page`, `get_post_meta`, `update_post_meta`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Source post existence check, capability check on target post type.

### `update_post_attributes`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric post ID.
  - `title` (string, optional) — Post title.
  - `slug` (string, optional) — Post slug (post_name).
  - `excerpt` (string, optional) — Post excerpt.
  - `featured_image_id` (integer, optional) — Attachment ID for featured image, or 0 to remove.
  - `comment_status` (string, optional) — open or closed.
- **Calls:** `wp_update_post`, `set_post_thumbnail`, `delete_post_thumbnail`, `Align_Builder_Crud_Handler::rename_item`, `Align_Agent_Audit_Log::log_action`
### `patch_content`
- **Type:** write (needs approval)
- **Capability:** `edit_posts` (checks `edit_theme_options` when target is `template`)
- **Requires Builder:** yes
- **Danger:** yes (only when target is `template`)
- **Parameters:**
  - `target_type` (string, required) — Target entity: "page", "post", "template", or "reuse_block".
  - `post_id` (integer, optional) — Numeric post ID (for page, post, reuse_block).
  - `tp_id` (string, optional) — Template part or template ID/slug (for template).
  - `template_type` (string, optional) — "wp_template" or "wp_template_part".
  - `edits` (array, required) — 1 to 10 edits: `[{ field: 'html'|'css'|'js', find: string, replace: string }]`.
- **Calls:** `Align_Agent_Patch_Engine::apply_edits`, `tool_save_page`, `tool_save_post`, `tool_save_template`, `tool_save_reuse_block`
- **Guards:** Single-occurrence pattern check (`patch_no_match`, `patch_ambiguous_match`), HTML/CSS/JS guards, capability check.

### `list_asset_history`
- **Type:** read
- **Capability:** `edit_theme_options`
- **Requires Builder:** yes
- **Danger:** no
- **Parameters:**
  - `basename` (string, required) — Asset file basename (e.g. `style-page-52.css`, `script-part-header.js`). Must end in `.css` or `.js`.
- **Calls:** `Align_Builder_Asset_History::list_snapshots`
- **Guards:** Capability check (`edit_theme_options`), extension whitelist (`.css`, `.js`), path traversal sanitization.

### `restore_asset_history`
- **Type:** write (needs approval)
- **Capability:** `edit_theme_options`
- **Requires Builder:** yes
- **Danger:** yes
- **Parameters:**
  - `basename` (string, required) — Asset file basename (e.g. `style-page-52.css`, `script-part-header.js`). Must end in `.css` or `.js`.
  - `timestamp` (integer, optional) — Snapshot timestamp to restore. 0 for newest.
- **Calls:** `Align_Builder_Asset_History::restore`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Capability check (`edit_theme_options`), extension whitelist (`.css`, `.js`), path traversal sanitization.

### `get_post_lock`
- **Type:** read
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric WordPress post ID.
- **Calls:** `Align_Post_Password_Lock::is_post_locked`

### `set_post_lock`
- **Type:** write
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no (requires approval if published)
- **Parameters:**
  - `post_id` (integer, required) — Numeric WordPress post ID.
  - `enabled` (boolean, required) — Lock or unlock post.
  - `password` (string, required if enabled) — Password between 4 and 64 characters.
- **Calls:** `Align_Post_Password_Lock::set_password` / `disable_lock`, `Align_Agent_Audit_Log::log_action`

### `count_content`
- **Type:** read
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:** none
- **Calls:** `get_post_types`, `wp_count_posts`

### `find_content`
- **Type:** read
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `query` (string, required) — Numeric ID, slug, title keyword, or content keyword.
  - `post_type` (string, optional) — Post type slug or "any" (default: any).
  - `status` (string, optional) — Comma-separated statuses (default: publish,draft,private).
  - `limit` (integer, optional) — Max items (default 5, max 10).
- **Calls:** `get_post`, `WP_Query`, direct `wpdb` query, `get_permalink`, `get_preview_post_link`

### `save_working_note`
- **Type:** read
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `text` (string, required) — Concise diagnostic or context note (max 1,024 bytes) to persist across Continue turns in user meta `align_agent_notes_{channel}`.
- **Calls:** `Align_Agent_Working_Notes::save`

## Notes
- `Align_Agent_Tools_Pages::guard_html_and_css` is the shared HTML/CSS guard used by pages, posts, and templates to strip `<script>` tags and reject insecure markup.
- `save_page` and `save_post` enforce `Align_Editor_Mode_Lock::is_locked_to( $id, 'gutenberg' )` to protect Gutenberg-managed content.
- Deletion operations (`trash_post`) strictly use `wp_trash_post()`; permanent deletion (`wp_delete_post( $id, true )`) is strictly prohibited.
- `patch_content` strictly requires non-empty `find` patterns and cannot be used to initialize empty content. When populating or creating content for the first time, use `save_template`, `save_page`, or `save_post`.
