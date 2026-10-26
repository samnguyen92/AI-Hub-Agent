# Structure tools

**Registry group:** `structure`  
**Classes:** `Align_Agent_Tools_Templates` (`class-agent-tools-templates.php`), `Align_Agent_Tools_Navigation` (`class-agent-tools-navigation.php`), `Align_Agent_Nav_Dom_Engine` (`class-agent-nav-dom-engine.php`), `Align_Agent_Tools_Render_Map` (`class-agent-tools-render-map.php`)

## Tools

### `list_templates`
- **Type:** read | **Capability:** `edit_theme_options` | **Requires Builder:** no

### `get_template`
- **Type:** read | **Capability:** `edit_theme_options` | **Requires Builder:** yes

### `save_template`
- **Type:** write (needs approval) | **Capability:** `edit_theme_options` | **Requires Builder:** yes | **Danger:** yes

### `list_reuse_blocks`
- **Type:** read | **Capability:** `edit_posts` | **Requires Builder:** no

### `get_reuse_block`
- **Type:** read | **Capability:** `edit_posts` | **Requires Builder:** yes

### `create_reuse_block`
- **Type:** write (needs approval) | **Capability:** `edit_posts` | **Requires Builder:** yes

### `save_reuse_block`
- **Type:** write (needs approval) | **Capability:** `edit_posts` | **Requires Builder:** yes

### `list_nav_links`
- **Type:** read | **Capability:** `edit_theme_options` | **Requires Builder:** no
- **Parameters:** `tp_id` (string, optional, default: `tp_header`)
- **Description:** Parses `<nav>` elements in template parts, returning structured links and checking desktop/mobile drawer sync.

### `update_nav_links`
- **Type:** write (needs approval) | **Capability:** `edit_theme_options` | **Requires Builder:** yes | **Danger:** yes
- **Parameters:** `tp_id`, `nav_index` (optional), `operations` (array of op objects: add, remove, rename, change_href, move)
- **Description:** Updates navigation menus simultaneously across desktop and mobile navs while keeping external HTML intact.

### `inspect_render`
- **Type:** read | **Capability:** `edit_theme_options` | **Requires Builder:** no
- **Parameters:** `url` (string, optional), `post_id` (integer, optional)
- **Description:** Returns a complete render map (`page`, `language`, `template`, `active_parts`, `unused_parts`, `reuse_blocks`, `global_code`, `code_slots`, `clone`, `vault`) and automatically saves a summary to Working Notes.

### `find_in_render`
- **Type:** read | **Capability:** `edit_theme_options` | **Requires Builder:** no
- **Parameters:** `url` (string, optional), `post_id` (integer, optional), `selector` (string, optional), `text` (string, optional)
- **Description:** Locates up to 5 exact snippets (`page:<id>`, `tp:<slug>`, `block:<id>`, `option:align_custom_js`, `option:align_custom_css`) and extracts enclosing JS functions without reading PHP files.
