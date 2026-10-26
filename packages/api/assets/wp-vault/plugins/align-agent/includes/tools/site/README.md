# Site tools

**Registry groups:** `structure` (`set_homepage`, `update_site_identity`), `ops` (`flush_cache`, `site_overview`, `list_audit_logs`, `undo_audit_action`, `site_health`), `site` (`get_global_code`, `save_global_code`, `get_code_slots`, `save_code_slot`, `get_site_settings`, `update_site_settings`, `get_site_protection`, `set_site_protection`, `list_backups`, `search_backups`, `diff_backup`, `restore_backup`)  
**Classes:** `Align_Agent_Tools_Site`, `Align_Agent_Tools_Global_Code`, `Align_Agent_Tools_Code_Slots`, `Align_Agent_Tools_Settings`, `Align_Agent_Tools_Protection`, `Align_Agent_Tools_Audit`, `Align_Agent_Tools_Health`, `Align_Agent_Option_Writer`, `Align_Agent_Tools_Backups`

---

## Tools Overview

### Core & Identity (`structure` / `ops`)
- `set_homepage`: Configure static front page and posts page.
- `update_site_identity`: Update title, tagline, and site icon.
- `flush_cache`: Flush object, rewrite, and transient caches.
- `site_overview`: Read general statistics and WordPress versions.

### Global Code & Slots (`site`)
- `get_global_code`: Read sitewide CSS, JS, and tracking code.
- `save_global_code`: Update sitewide CSS/JS via full replacement or surgical edits.
- `get_code_slots`: Read HTML code injection slots (head, body-open, footer) and `skip_admins`.
- `save_code_slot`: Update code injection slot (HTTPS external scripts only, no PHP tags, check inline JS).

### WordPress Settings (`site`)
- `get_site_settings`: Read blog_public, permalinks, timezone, date/time formats, pagination, comment rules.
- `update_site_settings`: Update settings with whitelist verification; automatically flushes rewrite rules on permalink change.

### Site Protection (`site`)
- `get_site_protection`: Read protection status, domains, bypass options, cookie validity, environment.
- `set_site_protection`: Enable/disable protection, configure domains and bypasses. Strictly disallows user/password edits.

### Global Vault Backups (`site`)
- `list_backups`: List Global Vault backups for templates, template parts, reuse blocks, and global CSS/JS/code slots.
- `search_backups`: Search text inside up to 10 decompressed Global Vault backups of a specific `object_key`.
- `diff_backup`: Generate a line-by-line unified diff (max 200 lines) between a Global Vault backup and live state.
- `restore_backup`: Restore a Global Vault backup (`scope: global`, `danger: true`, always requires approval, rejects `foreign` backups, snapshots current live state before restoring).

### Operations & Auditing (`ops`)
- `list_audit_logs`: Inspect recent agent actions and identify reversible operations.
- `undo_audit_action`: Atomically restore option snapshots of previous configuration changes.
- `site_health`: Compile comprehensive diagnostics (PHP, MySQL, cron, disk space, plugins, redacted error logs).
