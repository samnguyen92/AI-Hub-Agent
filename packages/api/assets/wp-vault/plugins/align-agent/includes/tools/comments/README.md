# Comments tools

**Registry group:** `comments`  
**Classes:** `Align_Agent_Tools_Comments` (`class-agent-tools-comments.php`)

## Tools

### `list_comments`
- **Type:** read
- **Capability:** `moderate_comments`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, optional) — Filter comments by post/page ID.
  - `status` (string, optional) — Filter: all, approve, hold, spam, trash. Default 'all'.
  - `search` (string, optional) — Search keyword in comment content, author, or email.
  - `page` (integer, optional) — Page number (default 1).
  - `per_page` (integer, optional) — Items per page (default 20, max 50).
- **Calls:** `get_comments`
- **Guards:** Capability check (`moderate_comments`).

### `moderate_comment`
- **Type:** write (needs approval)
- **Capability:** `moderate_comments`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `comment_id` (integer, required) — Numeric WordPress comment ID.
  - `action` (string, required) — Action: approve, hold, spam, trash, unspam, untrash.
- **Calls:** `wp_set_comment_status`, `wp_trash_comment`, `wp_spam_comment`, `wp_untrash_comment`, `wp_unspam_comment`, `get_comment`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Comment existence check, permitted action validation.

### `reply_comment`
- **Type:** write (needs approval)
- **Capability:** `moderate_comments`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `comment_id` (integer, required) — Parent comment ID to reply to.
  - `content` (string, required) — Reply text content.
- **Calls:** `wp_new_comment`, `get_comment`, `wp_get_current_user`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Parent comment existence check, non-empty content string.

## Notes
- All comment moderation actions log their operations through `Align_Agent_Audit_Log`.
- Moderation operations never permanently delete comments; they only set status to trash or spam.
