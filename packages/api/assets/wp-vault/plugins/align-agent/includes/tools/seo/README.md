# SEO tools

**Registry group:** `seo`  
**Classes:**
- `Align_Agent_Tools_Seo` (`class-agent-tools-seo.php`)
- `Align_Agent_Tools_Redirects` (`class-agent-tools-redirects.php`)
- `Align_Agent_Tools_Seo_Audit` (`class-agent-tools-seo-audit.php`)
- `Align_Agent_Seo_Adapters` (`class-agent-seo-adapters.php`) — Dedicated write adapter for SEO providers

## Tools

### `check_seo_output`
- **Type:** read
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric WordPress post/page ID to inspect.
- **Calls:** `wp_remote_get`, DOMDocument `<head>` parser, `get_post`
- **Guards:** Post existence check; returns `status="unreachable"` if post is draft or password-protected.

### `save_seo_rank_math`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric WordPress page/post ID.
  - `title` (string, optional) — SEO title (max 70 characters recommended).
  - `description` (string, optional) — Meta description (max 160 characters recommended).
  - `focus_keyword` (string, optional) — Primary focus keyword or comma-separated keywords.
  - `canonical` (string, optional) — Custom canonical URL.
  - `og_title` (string, optional) — OpenGraph / Twitter social title.
  - `og_description` (string, optional) — OpenGraph / Twitter social description.
  - `og_image_id` (integer, optional) — WordPress Media Library attachment ID for og:image.
  - `noindex` (boolean, optional) — Set true to mark page as noindex.
- **Calls:** `Align_Agent_Seo_Adapters::save_rank_math`, `Align_Builder_Seo_Handler::evaluate_rank_math_score`
- **Guards:** Provider setting check (`rank_math`), Rank Math plugin active check (`defined( 'RANK_MATH_VERSION' )`).

### `save_seo_yoast`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - Same parameters as `save_seo_rank_math`.
- **Calls:** `Align_Agent_Seo_Adapters::save_yoast`
- **Guards:** Provider setting check (`yoast`), Yoast SEO plugin active check (`defined( 'WPSEO_VERSION' )`).

### `save_seo_theme_default`
- **Type:** write (needs approval)
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_id` (integer, required) — Numeric WordPress page/post ID.
  - `title` (string, optional) — SEO title.
  - `description` (string, optional) — Meta description.
  - `focus_keyword` (string, optional) — Primary focus keyword.
  - `og_title` (string, optional) — OpenGraph social title.
  - `og_description` (string, optional) — OpenGraph social description.
  - `og_image_id` (integer, optional) — Attachment ID for og:image.
  - `noindex` (boolean, optional) — Set true to mark page as noindex.
- **Calls:** `Align_Agent_Seo_Adapters::save_theme_default`, `Align_Seo::save_meta`
- **Guards:** Provider setting check (`theme_default`).

### `list_redirects`
- **Type:** read
- **Capability:** `manage_options`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `search` (string, optional) — Search query matching source or destination URL.
  - `page` (integer, optional) — Page number (default 1).
  - `per_page` (integer, optional) — Items per page (default 20, max 50).
- **Calls:** `\RankMath\Redirections\DB::get_redirections`, `self::is_rank_math_active`
- **Guards:** Rank Math Redirections module active check (`Align_Redirection_Importer` or `\RankMath\Helper::is_module_active( 'redirections' )`).

### `create_redirect`
- **Type:** write (needs approval)
- **Capability:** `manage_options`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `source` (string, required) — Source URL path or regex pattern to redirect from.
  - `destination` (string, required) — Target destination URL or relative path.
  - `code` (integer, optional) — HTTP redirect status code: 301 (default), 302, or 307.
  - `comparison` (string, optional) — Match comparison: exact (default), contains, start, end, regex.
- **Calls:** `\RankMath\Redirections\DB::add`, `self::purge_rankmath_cache`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Non-empty source and destination, Rank Math redirections module active check.

### `delete_redirect`
- **Type:** write (needs approval)
- **Capability:** `manage_options`
- **Requires Builder:** no
- **Danger:** yes
- **Parameters:**
  - `redirect_id` (integer, required) — Numeric Rank Math redirection ID to delete.
- **Calls:** `\RankMath\Redirections\DB::delete`, `self::purge_rankmath_cache`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Rank Math redirections module active check, `danger: true` flag.

### `list_404_logs`
- **Type:** read
- **Capability:** `manage_options`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `search` (string, optional) — Filter matching request path or referrer.
  - `page` (integer, optional) — Page number (default 1).
  - `per_page` (integer, optional) — Items per page (default 20, max 50).
- **Calls:** `get_option( 'align_404_logs' )`
- **Guards:** Capability check (`manage_options`).

### `create_redirect_from_404`
- **Type:** write (needs approval)
- **Capability:** `manage_options`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `uri` (string, required) — 404 request path or URL to resolve.
  - `destination` (string, required) — Target destination path or full URL.
  - `code` (integer, optional) — HTTP status code: 301 (default), 302, or 307.
- **Calls:** `\RankMath\Redirections\DB::add`, `self::purge_rankmath_cache`, `update_option( 'align_404_logs' )`, `Align_Agent_Audit_Log::log_action`
- **Guards:** Rank Math redirections active check, URI and destination non-empty.

### `audit_seo_coverage`
- **Type:** read
- **Capability:** `edit_posts`
- **Requires Builder:** no
- **Danger:** no
- **Parameters:**
  - `post_types` (array, optional) — Post types to audit. Default: `["page", "post"]`.
  - `lang` (string, optional) — Language code filter or `all`. Default: `all`.
  - `scope` (string, optional) — `missing`, `needs_optimization`, or `all`. Default: `missing`.
  - `limit` (integer, optional) — Max items to scan (1..200). Default: 50.
- **Calls:** `Align_Agent_Seo_Adapters::get_provider`, `get_posts`, post meta scans.

## Notes
- **Dynamic Single-Tool SEO Routing:** At runtime, `Align_Agent_Tools_Seo` registers only the single SEO write tool matching `align_agent_seo_provider` (`rank_math`, `yoast`, or `theme_default`). If set to `others`, no SEO write tool is registered.
- `Align_Agent_Seo_Adapters` is also invoked directly by `Align_Agent_Loop` to resolve active SEO metadata for tool calls and prompt context.
- Rank Math redirection tools are conditionally registered only when the Rank Math Redirections module is active.
