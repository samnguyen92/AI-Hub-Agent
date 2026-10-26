# Users tools

**Registry group:** `users`  
**Classes:** `Align_Agent_Tools_Users` (`class-agent-tools-users.php`)

## Tools

### `list_users`
- **Type:** read
- **Capability:** `list_users`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `role` (string, optional) — Filter by user role: administrator, editor, author, contributor, subscriber. Default 'all'.
  - `search` (string, optional) — Search keyword in login, display name, or email.
  - `paged` (integer, optional) — Page number (default 1).
  - `number` (integer, optional) — Users per page (default 20, max 50).
- **Calls:** `get_users`
- **Guards:** Capability check (`list_users`).

### `create_user`
- **Type:** write (needs approval)
- **Capability:** `create_users`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `user_login` (string, required) — Username for login.
  - `user_email` (string, required) — Valid user email address.
  - `role` (string, optional) — User role: subscriber (default), contributor, author, editor, administrator.
  - `display_name` (string, optional) — Display name.
  - `send_notification` (boolean, optional) — Send WordPress welcome email with password setup link (default true).
- **Calls:** `wp_create_user`, `wp_update_user`, `wp_new_user_notification`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Assigning `administrator` role requires `manage_options` capability; passwords are auto-generated and NEVER returned in tool response.

### `update_user_role`
- **Type:** write (needs approval)
- **Capability:** `promote_users`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `user_id` (integer, required) — Numeric user ID.
  - `role` (string, required) — Target role: subscriber, contributor, author, editor, administrator.
- **Calls:** `WP_User::set_role`, `get_userdata`, `get_users`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Self-demotion protection (`self_demotion_forbidden`), last administrator protection (`last_admin_protection`), promoting to `administrator` requires `manage_options`.

### `update_user_profile`
- **Type:** write (needs approval)
- **Capability:** `edit_users`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `user_id` (integer, required) — Numeric user ID.
  - `display_name` (string, optional) — Public display name.
  - `first_name` (string, optional) — First name.
  - `last_name` (string, optional) — Last name.
  - `user_email` (string, optional) — New email address.
  - `description` (string, optional) — Biographical info.
- **Calls:** `wp_update_user`, `get_userdata`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Capability check (`edit_user`, `$user_id`), email uniqueness validation.

### `send_password_reset`
- **Type:** write (needs approval)
- **Capability:** `edit_users`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `user_id` (integer, required) — Numeric user ID to send password reset email to.
- **Calls:** `retrieve_password`, `get_userdata`, `Align_Agent_Audit_Log::log_action`
- **Guards:** User existence; reset keys/links are strictly dispatched via core WP mailer and NEVER leaked in tool responses.

### `delete_user`
- **Type:** write (needs approval)
- **Capability:** `delete_users`
- **Requires Builder:** no
- **Danger:** yes
- **Parameters:**
  - `user_id` (integer, required) — Numeric user ID to delete.
  - `reassign` (integer, optional) — User ID to reassign posts to (default 0).
- **Calls:** `wp_delete_user`, `get_userdata`, `get_users`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Self-deletion protection (cannot delete active session account), last administrator protection, reassign post validation, `danger: true` flag.

## Notes
- Strict administrator safety policies: No agent can demote or delete the last remaining administrator on the site.
- Zero credential disclosure: Passwords and password reset tokens are never outputted to LLM context or tool payload.
