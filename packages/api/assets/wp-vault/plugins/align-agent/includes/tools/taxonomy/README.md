# Taxonomy tools

**Registry group:** `taxonomy`  
**Classes:** `Align_Agent_Tools_Taxonomy` (`class-agent-tools-taxonomy.php`)

## Tools

### `list_terms`
- **Type:** read
- **Capability:** `manage_categories`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `taxonomy` (string, optional) — Target taxonomy (default "category").
  - `hide_empty` (boolean, optional) — Whether to hide terms with 0 posts (default false).
  - `search` (string, optional) — Optional term name search query.
  - `number` (integer, optional) — Max number of terms to return (default 100, max 100).
- **Calls:** `get_terms`
- **Guards:** Taxonomy existence check.

### `create_term`
- **Type:** write (needs approval)
- **Capability:** `manage_categories`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `term` (string, required) — Term name.
  - `taxonomy` (string, optional) — Taxonomy slug (default "category").
  - `parent` (integer, optional) — Parent term ID (for hierarchical taxonomies).
  - `description` (string, optional) — Term description.
- **Calls:** `wp_insert_term`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Non-empty term name, taxonomy existence check.

### `update_term`
- **Type:** write (needs approval)
- **Capability:** `manage_categories`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `term_id` (integer, required) — Numeric term ID.
  - `taxonomy` (string, optional) — Taxonomy slug (default "category").
  - `name` (string, optional) — New term name.
  - `slug` (string, optional) — New term slug.
  - `parent` (integer, optional) — New parent term ID.
  - `description` (string, optional) — New term description.
- **Calls:** `wp_update_term`, `Align_Agent_Audit_Log::snapshot_term`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Term existence check, automatic snapshot before mutation.

### `delete_term`
- **Type:** write (needs approval)
- **Capability:** `manage_categories`
- **Requires Builder:** no
- **Danger:** yes
- **Parameters:**
  - `term_id` (integer, required) — Numeric term ID to delete.
  - `taxonomy` (string, optional) — Taxonomy slug (default "category").
- **Calls:** `wp_delete_term`, `Align_Agent_Audit_Log::snapshot_term`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Term existence check, default category protection, automatic snapshot before deletion.

### `assign_terms`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric post ID.
  - `taxonomy` (string, optional) — Taxonomy slug (default "category").
  - `terms` (array, required) — Array of term names, slugs, or IDs to assign.
  - `append` (boolean, optional) — If true, terms are appended; if false, replaced (default false).
- **Calls:** `wp_set_object_terms`, `get_post`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Post existence check, capability check (`edit_post`, `$post_id`).

## Notes
- Term mutations (`update_term`, `delete_term`) automatically record snapshots via `Align_Agent_Audit_Log::snapshot_term` for audit and rollback capabilities.
- `delete_term` is flagged with `danger: true` to require explicit danger confirmation in the UI.
