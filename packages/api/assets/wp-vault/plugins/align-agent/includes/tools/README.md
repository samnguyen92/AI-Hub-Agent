# Align Agent Tools (`includes/tools/`)

## 1. Purpose

The `includes/tools/` directory contains all tool definitions and execution callbacks exposed to the Align Agent Chatbox via `Align_Agent_Tool_Registry`. All operations execute internally within the PHP runtime and interface directly with WordPress Core APIs and Align Theme classes without making loopback HTTP requests.

## 2. Folder Map

| Folder | Registry Group | Classes | Tool Count |
|---|---|---|---|
| `content/` | `content` | `Align_Agent_Tools_Pages`, `Align_Agent_Tools_Posts`, `Align_Agent_Tools_Patch`, `Align_Agent_Tools_Clone`, `Align_Agent_Tools_Asset_History`, `Align_Agent_Tools_Post_Lock`, `Align_Agent_Tools_Finder`, `Align_Agent_Working_Notes` | 26 (7 pages + 10 posts + 1 patch + 1 clone + 2 asset history + 2 post lock + 2 finder + 1 working note) |
| `taxonomy/` | `taxonomy` | `Align_Agent_Tools_Taxonomy` | 5 |
| `structure/` | `structure` | `Align_Agent_Tools_Templates`, `Align_Agent_Tools_Navigation`, `Align_Agent_Tools_Render_Map`, `Align_Agent_Nav_Dom_Engine` | 11 (7 templates + 2 navigation + 2 render map) |
| `site/` | `structure`, `ops`, `site` | `Align_Agent_Tools_Site`, `Align_Agent_Tools_Global_Code`, `Align_Agent_Tools_Code_Slots`, `Align_Agent_Tools_Settings`, `Align_Agent_Tools_Protection`, `Align_Agent_Tools_Audit`, `Align_Agent_Tools_Health`, `Align_Agent_Tools_Backups`, `Align_Agent_Option_Writer` | 20 (2 structure + 2 ops + 2 global code + 2 code slots + 2 settings + 2 protection + 2 audit + 2 health + 4 backups) |
| `seo/` | `seo` | `Align_Agent_Tools_Seo`, `Align_Agent_Tools_Redirects`, `Align_Agent_Tools_Seo_Audit`, `Align_Agent_Seo_Adapters` | 10 (4 seo + 5 redirects + 1 seo audit) |
| `media/` | `media` | `Align_Agent_Tools_Media` | 9 (`list_media`, `upload_media`, `list_page_images`, `replace_page_image`, `set_featured_image`, `attach_media`, `sideload_media`, `update_media_meta`, `trash_media`) |
| `comments/` | `comments` | `Align_Agent_Tools_Comments` | 3 |
| `users/` | `users` | `Align_Agent_Tools_Users` | 6 |
| `i18n/` | `i18n` | `Align_Agent_Tools_I18n`, `Align_Agent_Tools_I18n_Urls` | 7 (5 i18n + 2 i18n urls) |
| **Total** | — | **26 tool classes + 1 adapter + 1 loader + 3 helper engines** | **97 tools** |

## 3. Full Tool Table

| Tool | Folder | Type | Scope | Capability | Requires Builder | Danger | Needs Approval | Align Core / WP API Called |
|---|---|---|---|---|---|---|---|---|
| `list_pages` | `content` | read | local | `manage_options` | no | no | no | `Align_Gateway_Pages::handle_get_pages`, `get_posts` |
| `get_page` | `content` | read | local | `manage_options` | no | no | no | `Align_Editor_Mode_Lock`, `get_post` |
| `get_revisions` | `content` | read | local | `manage_options` | no | no | no | `Align_Builder_Revision_Handler::get_revisions` |
| `validate_markup` | `content` | read | local | `manage_options` | no | no | no | `Align_Gateway_Validator::validate_markup` |
| `save_page` | `content` | write | local | `manage_options` | yes | no | yes | `Align_Builder_Crud_Handler::save_page`, `Align_Editor_Mode_Lock::is_locked_to` |
| `create_page` | `content` | write | local | `manage_options` | yes | no | yes | `Align_Builder_Crud_Handler::create_item`, `Align_Gateway_Validator::validate_markup` |
| `restore_revision` | `content` | write | local | `manage_options` | yes | no | yes | `Align_Builder_Revision_Handler::restore_revision`, `Align_Editor_Mode_Lock::is_locked_to` |
| `apply_page_clone` | `content` | write | global | `manage_options` | yes | no | yes | `Align_Agent_Page_Clone::apply`, `wp_trash_post` |
| `list_asset_history` | `content` | read | local | `edit_theme_options` | yes | no | no | `Align_Builder_Asset_History::list_snapshots` |
| `restore_asset_history` | `content` | write | local | `edit_theme_options` | yes | yes | yes | `Align_Builder_Asset_History::restore`, `Align_Agent_Audit_Log::log_action` |
| `list_post_types` | `content` | read | local | `edit_posts` | no | no | no | `get_post_types` |
| `list_posts` | `content` | read | local | `edit_posts` | no | no | no | `get_posts`, `get_post_meta` |
| `get_post` | `content` | read | local | `edit_posts` | no | no | no | `Align_Editor_Mode_Lock`, `get_post` |
| `create_post` | `content` | write | local | `edit_posts` | yes | no | yes | `Align_Builder_Crud_Handler::create_item`, `wp_insert_post` |
| `save_post` | `content` | write | local | `edit_posts` | yes | no | yes | `Align_Builder_Crud_Handler::save_page`, `Align_Editor_Mode_Lock::is_locked_to` |
| `patch_content` | `content` | write | conditional | `edit_posts` | yes | conditional | yes | `Align_Agent_Patch_Engine::apply_edits`, target save tool |
| `set_post_status` | `content` | write | local | `edit_posts` | no | no | yes | `wp_update_post`, `Align_Agent_Audit_Log::log_action` |
| `trash_post` | `content` | write | global | `delete_posts` | no | yes | yes | `wp_trash_post`, `Align_Agent_Audit_Log::log_action` |
| `restore_post` | `content` | write | local | `delete_posts` | no | no | yes | `wp_untrash_post`, `Align_Agent_Audit_Log::log_action` |
| `duplicate_post` | `content` | write | local | `edit_posts` | yes | no | yes | `Align_Builder_Crud_Handler::create_item`, `update_post_meta` |
| `update_post_attributes` | `content` | write | local | `edit_posts` | no | no | yes | `wp_update_post`, `set_post_thumbnail`, `Align_Builder_Crud_Handler::rename_item` |
| `get_post_lock` | `content` | read | local | `edit_posts` | no | no | no | `Align_Post_Password_Lock::is_post_locked` |
| `set_post_lock` | `content` | write | local | `edit_posts` | no | no | yes (if live) | `Align_Post_Password_Lock::set_password` / `disable_lock` |
| `count_content` | `content` | read | local | `edit_posts` | no | no | no | `get_post_types`, `wp_count_posts` |
| `find_content` | `content` | read | local | `edit_posts` | no | no | no | `get_post`, `WP_Query`, direct `wpdb` query, `get_permalink` |
| `save_working_note` | `content` | read | local | `edit_posts` | no | no | no | `Align_Agent_Working_Notes::save`, `update_user_meta` |
| `list_terms` | `taxonomy` | read | local | `manage_categories` | no | no | no | `get_terms` |
| `create_term` | `taxonomy` | write | local | `manage_categories` | no | no | yes | `wp_insert_term`, `Align_Agent_Audit_Log::log_action` |
| `update_term` | `taxonomy` | write | local | `manage_categories` | no | no | yes | `wp_update_term`, `Align_Agent_Audit_Log::snapshot_term` |
| `delete_term` | `taxonomy` | write | global | `manage_categories` | no | yes | yes | `wp_delete_term`, `Align_Agent_Audit_Log::snapshot_term` |
| `assign_terms` | `taxonomy` | write | local | `edit_posts` | no | no | yes | `wp_set_object_terms`, `Align_Agent_Audit_Log::log_action` |
| `list_templates` | `structure` | read | local | `edit_theme_options` | no | no | no | `get_posts` |
| `get_template` | `structure` | read | local | `edit_theme_options` | yes | no | no | `Align_Builder_Template_Structure::detect_mode`, `get_post` |
| `list_reuse_blocks` | `structure` | read | local | `edit_posts` | no | no | no | `get_posts` |
| `get_reuse_block` | `structure` | read | local | `edit_posts` | yes | no | no | `Align_Builder_Assets::load_external_css`, `get_post` |
| `save_template` | `structure` | write | global | `edit_theme_options` | yes | yes | yes | `Align_Builder_Crud_Handler::save_page`, `Align_Editor_Mode_Lock::is_locked_to` |
| `create_reuse_block` | `structure` | write | global | `edit_posts` | yes | no | yes | `Align_Builder_Crud_Handler::create_item` |
| `save_reuse_block` | `structure` | write | global | `edit_posts` | yes | no | yes | `Align_Builder_Crud_Handler::save_page`, `Align_Builder_Crud_Handler::rename_item` |
| `list_nav_links` | `structure` | read | local | `edit_theme_options` | no | no | no | `Align_Agent_Tools_Templates::tool_get_template`, DOM parsing |
| `update_nav_links` | `structure` | write | global | `edit_theme_options` | yes | yes | yes | `Align_Agent_Nav_Dom_Engine`, `tool_save_template` |
| `inspect_render` | `structure` | read | local | `edit_theme_options` | no | no | no | `Align_Language_Manager`, `get_block_template`, `Align_Agent_Working_Notes` |
| `find_in_render` | `structure` | read | local | `edit_theme_options` | no | no | no | `Align_Agent_Tools_Templates`, `Align_Gateway_Pages::extract_decoupled_parts` |
| `set_homepage` | `site` | write | global | `manage_options` | no | no | yes | `update_option`, `Align_Agent_Audit_Log::snapshot_options` |
| `update_site_identity` | `site` | write | global | `manage_options` | no | no | yes | `update_option`, `set_theme_mod`, `Align_Agent_Audit_Log::snapshot_options` |
| `flush_cache` | `site` | write | local | `manage_options` | no | no | yes | `Align_Gateway_System::handle_cache_flush`, `wp_cache_flush` |
| `site_overview` | `site` | read | local | `manage_options` | no | no | no | `get_bloginfo`, `get_option`, `wp_get_theme` |
| `get_global_code` | `site` | read | local | `manage_options` | no | no | no | `get_option` (`align_custom_css`, `align_custom_js`, `align_tracking_code`) |
| `save_global_code` | `site` | write | global | `manage_options` | no | yes | yes | `update_option`, `Align_Agent_Patch_Engine`, `Align_Agent_Content_Guard`, `Align_Gateway_Validator` |
| `get_code_slots` | `site` | read | local | `manage_options` | no | no | no | `get_option` (tracking, body_open, footer, skip_admins) |
| `save_code_slot` | `site` | write | global | `unfiltered_html` | no | yes | yes | `Align_Agent_Option_Writer::write`, `guard_code` |
| `get_site_settings` | `site` | read | local | `manage_options` | no | no | no | `get_option` (reading, discussion, permalinks, tz, formats) |
| `update_site_settings` | `site` | write | global | `manage_options` | no | yes | yes | `Align_Agent_Option_Writer::write`, `flush_rewrite_rules` |
| `get_site_protection` | `site` | read | local | `manage_options` | no | no | no | `get_option` (protect options, environment) |
| `set_site_protection` | `site` | write | global | `manage_options` | no | yes | yes | `Align_Agent_Option_Writer::write` |
| `list_audit_logs` | `site` | read | local | `manage_options` | no | no | no | `Align_Agent_Audit_Log::get_logs` |
| `undo_audit_action` | `site` | write | global | `manage_options` | no | yes | yes | `Align_Agent_Option_Writer::restore` |
| `site_health` | `site` | read | local | `manage_options` | no | no | no | versions, runtime, cron, disk, active plugins, log tail |
| `list_backups` | `site` | read | local | `edit_theme_options` | no | no | no | `Align_Agent_Global_Vault`, `get_posts` |
| `search_backups` | `site` | read | local | `edit_theme_options` | no | no | no | `Align_Agent_Global_Vault::read_payload` |
| `diff_backup` | `site` | read | local | `edit_theme_options` | no | no | no | `Align_Agent_Global_Vault::read_payload`, `read_live_state` |
| `restore_backup` | `site` | write | global | `edit_theme_options` | no | yes | yes | `Align_Agent_Global_Vault::store`, `restore_internal` |
| `check_seo_output` | `seo` | read | local | `edit_posts` | no | no | no | `wp_remote_get`, DOMDocument `<head>` parser |
| `save_seo_rank_math` | `seo` | write | local | `edit_posts` | no | no | yes | `Align_Agent_Seo_Adapters::save_rank_math`, `Align_Builder_Seo_Handler` |
| `save_seo_yoast` | `seo` | write | local | `edit_posts` | no | no | yes | `Align_Agent_Seo_Adapters::save_yoast` |
| `save_seo_theme_default` | `seo` | write | local | `edit_posts` | no | no | yes | `Align_Agent_Seo_Adapters::save_theme_default`, `Align_Seo::save_meta` |
| `list_redirects` | `seo` | read | local | `manage_options` | no | no | no | `\RankMath\Redirections\DB::get_redirections` |
| `create_redirect` | `seo` | write | local | `manage_options` | no | no | yes | `\RankMath\Redirections\DB::add`, `purge_rankmath_cache` |
| `delete_redirect` | `seo` | write | global | `manage_options` | no | yes | yes | `\RankMath\Redirections\DB::delete`, `purge_rankmath_cache` |
| `list_404_logs` | `seo` | read | local | `manage_options` | no | no | no | `get_option( 'align_404_logs' )` |
| `create_redirect_from_404` | `seo` | write | local | `manage_options` | no | no | yes | `\RankMath\Redirections\DB::add`, `update_option( 'align_404_logs' )` |
| `audit_seo_coverage` | `seo` | read | local | `edit_posts` | no | no | no | `Align_Agent_Seo_Adapters::get_provider`, `get_posts`, meta scan |
| `list_languages` | `i18n` | read | local | `edit_posts` | no | no | no | `Align_Language_Manager::get_languages` |
| `get_translations` | `i18n` | read | local | `edit_posts` | no | no | no | `Align_Language_Manager::get_translations` |
| `translation_overview` | `i18n` | read | local | `edit_posts` | no | no | no | `Align_Language_Manager`, post cluster matrix |
| `create_translation` | `i18n` | write | local | `edit_posts` | no | no | yes (if front) | `Align_Language_Manager::create_translation`, `save_page` |
| `manage_language` | `i18n` | write | global | `manage_options` | no | yes | yes | `Align_Language_Manager::add|update|remove_language` |
| `scan_unlocalized_urls` | `i18n` | read | local | `edit_posts` | no | no | no | `Align_Bulk_Url_Scanner::detect_unlocalized_urls` |
| `localize_post_urls` | `i18n` | write | local | `edit_posts` | yes | no | yes (auto_clone) | `Align_Language_Url_Localizer::localize_html`, `save_page` |
| `list_media` | `media` | read | local | `upload_files` | no | no | no | `get_posts`, `source`/`telegram_message_id` filter, Dynamic URLs |
| `upload_media` | `media` | write | local | `upload_files` | no | no | yes | `media_handle_sideload`, `Align_Media_Normalizer`, `Align_Image_Sizes`, `Align_WebP` |
| `list_page_images` | `media` | read | local | `edit_posts` | no | no | no | `Align_Gateway_Pages::extract_decoupled_parts`, DOM & `related_attributes` scanner |
| `replace_page_image` | `media` | write | local | `edit_posts` | yes | no | yes | `Align_Builder_Image_Resolver::resolve_picture_tag`, container `synced_attributes`, `save_page` |
| `set_featured_image` | `media` | write | local | `edit_posts` | no | no | yes | `set_post_thumbnail`, `delete_post_thumbnail` |
| `attach_media` | `media` | write | local | `upload_files` | no | no | yes | `wp_update_post` |
| `sideload_media` | `media` | write | local | `upload_files` | no | no | yes | `download_url`, `wp_handle_sideload`, `wp_insert_attachment` |
| `update_media_meta` | `media` | write | local | `upload_files` | no | no | yes | `update_post_meta`, `wp_update_post` |
| `trash_media` | `media` | write | global | `delete_posts` | no | yes | yes | `wp_delete_attachment`, `Align_Agent_Audit_Log::log_action` |
| `list_comments` | `comments` | read | local | `moderate_comments` | no | no | no | `get_comments` |
| `moderate_comment` | `comments` | write | local | `moderate_comments` | no | no | yes | `wp_set_comment_status`, `wp_trash_comment`, `wp_spam_comment` |
| `reply_comment` | `comments` | write | local | `moderate_comments` | no | no | yes | `wp_new_comment` |
| `list_users` | `users` | read | global | `list_users` | no | no | no | `get_users` |
| `create_user` | `users` | write | global | `create_users` | no | no | yes | `wp_create_user`, `wp_update_user` |
| `update_user_role` | `users` | write | global | `promote_users` | no | no | yes | `WP_User::set_role`, `get_userdata` |
| `update_user_profile` | `users` | write | global | `edit_users` | no | no | yes | `wp_update_user` |
| `send_password_reset` | `users` | write | global | `edit_users` | no | no | yes | `retrieve_password` |
| `delete_user` | `users` | write | global | `delete_users` | no | yes | yes | `wp_delete_user`, `get_userdata` |

## 4. SEO Dynamic Routing

`Align_Agent_Tools_Seo` inspects the active provider option `align_agent_seo_provider` at bootstrap and registers exactly one SEO write tool:
- `rank_math` → registers `save_seo_rank_math` (requires Rank Math plugin).
- `yoast` → registers `save_seo_yoast` (requires Yoast SEO plugin).
- `theme_default` → registers `save_seo_theme_default` (uses theme `_align_seo_*` meta).
- `others` → registers no write tool (read-only `check_seo_output` remains active).

## 5. Align Core Dependencies

All tool handlers execute internal static methods in the Align Real Builder theme:

| Theme Class | Methods Called | Theme Source File |
|---|---|---|
| `Align_Builder_Crud_Handler` | `create_item`, `save_page`, `rename_item` | `themes/Align-Real-Builder/align/core/inc/builder/class-builder-crud-handler.php` |
| `Align_Builder_Revision_Handler` | `get_revisions`, `restore_revision` | `themes/Align-Real-Builder/align/core/inc/builder/class-builder-revision-handler.php` |
| `Align_Builder_Assets` | `load_external_css`, `load_external_js` | `themes/Align-Real-Builder/align/core/inc/builder/class-builder-assets.php` |
| `Align_Builder_Persistence_Service` | `sync_post_assets_from_content` | `themes/Align-Real-Builder/align/core/inc/builder/class-builder-persistence-service.php` |
| `Align_Builder_Seo_Handler` | `evaluate_rank_math_score` | `themes/Align-Real-Builder/align/core/inc/builder/class-builder-seo-handler.php` |
| `Align_Builder_Template_Structure` | `detect_mode`, `get_persisted_content` | `themes/Align-Real-Builder/align/core/inc/builder/class-builder-template-structure.php` |
| `Align_Seo` | `has_external_seo_plugin`, `save_meta` | `themes/Align-Real-Builder/align/core/includes/core/class-align-seo.php` |
| `Align_Editor_Mode_Lock` | `get_effective_mode`, `is_locked`, `is_locked_to` | `themes/Align-Real-Builder/align/core/includes/admin/class-align-editor-mode-lock.php` |

## 6. Shared Helpers

- **`Align_Agent_Tools_Pages::guard_html_and_css`**: Shared sanitization and guard helper used by `Pages`, `Posts`, and `Templates` to reject `<script>` tags, sanitize HTML, and pass markup through `Align_Gateway_Validator::validate_markup()`.
- **`Align_Agent_Option_Writer`**: Atomic option updater with automatic before/after snapshotting, masking of sensitive fields, and audit log generation.
- **`Align_Agent_Nav_Dom_Engine`**: DOM parsing and synchronization engine for template part navigation menus.
- **`Align_Agent_Seo_Adapters`**: Unified SEO metadata reading and writing adapter used by `Align_Agent_Tools_Seo`, `Align_Agent_Tools_Redirects`, and `Align_Agent_Loop` (`includes/class-agent-loop.php`).

## 7. Not Supported (Non-goals)

The chatbox currently does NOT manage:
- WordPress Plugins installation/activation/deactivation.
- WordPress Widgets and legacy sidebars.
- Backup, import, export, custom font files, and image sizes (P3 — local only).

## 8. How to Add a New Tool

1. **Select folder**: Choose the directory matching the tool's `group` (`content`, `taxonomy`, `structure`, `site`, `seo`, `media`, `comments`, `users`, `i18n`).
2. **Implement tool callback**: Add the execution method and definition in the respective class.
3. **Update Manifest (if adding new class)**: Add the file path and class name to `Align_Agent_Tools_Loader::MANIFEST` in `includes/tools/class-agent-tools-loader.php`.
4. **Declare attributes**: Ensure `type`, `capability`, `group`, `requires_builder`, and `danger` are explicitly set.
5. **Update documentation**: Add the tool entry to `includes/tools/<folder>/README.md` and the master table above in this file.
6. **File size constraint**: Ensure no class file exceeds 500 lines (split into SRP classes if approaching the limit).
