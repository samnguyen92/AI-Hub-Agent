# Data Query Patterns

How to retrieve WordPress data (page content, post meta, slugs) when working with Align Builder pages. Covers database queries, REST API, WP-CLI, and safe anti-patterns.

---

## 1. Identify a Page from Its URL

### Step 1: Fetch body classes
```bash
curl -s "https://yoursite.test/my-page/" | grep -o '<body[^>]*class="[^"]*"'
```

**Body class patterns:**
- `page-id-686` → Post ID = `686` (standard page)
- `postid-686` → Post ID = `686` (used on archive / single templates)
- `page-template-template-policy` → Template slug = `template-policy`
- `single-post` → Is a single blog post

### Step 2: Extract post ID from Builder URL
If you have the Builder URL:
```
/wp-admin/admin.php?page=align-builder&post_id=686     → Page ID = 686
/wp-admin/admin.php?page=align-builder&tpl_id=tpl_home → Template = home
/wp-admin/admin.php?page=align-builder&tp_id=tp_header → Part = header
```

---

## 2. Database Queries

### Get page info from post ID
```sql
SELECT ID, post_name, post_title, post_status, post_content
FROM wp_posts
WHERE ID = 686
  AND post_type IN ('page', 'wp_block', 'wp_template', 'wp_template_part');
```

### Get post ID from slug (safe — avoids attachment collision)
```sql
-- CORRECT: Always specify post_type explicitly
SELECT ID FROM wp_posts
WHERE post_name = 'my-page-slug'
  AND post_type = 'page'
  AND post_status IN ('publish', 'draft')
LIMIT 1;
```

> [!CAUTION]
> **Never** use `get_page_by_path($slug)` without specifying post type. WordPress can return a media attachment if an uploaded image has the same slug as your page. This will cause `wp_update_post()` to corrupt the media library entry.

### Safe PHP pattern for slug-to-ID lookup
```php
global $wpdb;
$post_id = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT ID FROM $wpdb->posts
         WHERE post_name = %s
           AND post_type = 'page'
           AND post_status = 'publish'
         LIMIT 1",
        $slug
    )
);
```

### Get post meta
```sql
SELECT meta_key, meta_value FROM wp_postmeta
WHERE post_id = 686
ORDER BY meta_key;
```

Common meta keys:
| Meta Key | Value | Purpose |
|:---|:---|:---|
| `_wp_page_template` | `'default'` or `'template-policy.html'` | Assigned FSE template |
| `_align_reusable_overrides` | JSON array | Per-page overrides for Reusable Blocks |
| `_align_lang` | `'vi'`, `'en'`, `'zh'` | Language of this post |
| `_align_lang_group` | `'group_abc123'` | Translation group ID |

### Find all pages with a specific template
```sql
SELECT p.ID, p.post_name, p.post_title
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = '_wp_page_template'
  AND pm.meta_value = 'template-policy.html'
  AND p.post_type = 'page'
  AND p.post_status = 'publish';
```

### Find all Reusable Blocks
```sql
SELECT ID, post_name, post_title
FROM wp_posts
WHERE post_type = 'wp_block'
  AND post_status = 'publish'
ORDER BY ID;
```

---

## 3. REST API Queries

### Align Builder REST API (read page content)
```bash
# Get page content (HTML + CSS + JS layers)
curl -s \
  -H "X-WP-Nonce: YOUR_NONCE" \
  "https://yoursite.test/wp-json/align-builder/v1/page-content?id=686"

# Response:
{
  "html": "<main>...</main>",
  "css": "/* page scoped CSS */",
  "js": "/* page scoped JS */",
  "post_id": 686,
  "post_name": "sitemap",
  "post_title": "Sitemap"
}
```

### Align Builder REST API (get pages list)
```bash
curl -s \
  -H "X-WP-Nonce: YOUR_NONCE" \
  "https://yoursite.test/wp-json/align-builder/v1/pages"
```

### Save page via REST (KSES-safe)
```bash
curl -X POST \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"post_id": 686, "html": "<main>...</main>", "css": "/* css */", "js": "/* js */"}' \
  "https://yoursite.test/wp-json/align-builder/v1/save-page"
```

> [!TIP]
> Using the Builder's `/save-page` REST endpoint automatically handles KSES bypass AND dual-storage sync (DB + physical file). Prefer this over direct `$wpdb` writes when possible.

### AI REST API (for external AI agent access)
```bash
# Requires Application Password authentication
curl -s \
  -H "Authorization: Basic base64(username:app-password)" \
  "https://yoursite.test/wp-json/align-ai/v1/pages"
```
Full reference: `../api/ai-rest-api-reference.md`

---

## 4. WP-CLI (Local by Flywheel)

On macOS with Local by Flywheel, standard `wp` CLI may lack the MySQL socket. Always use the full wrapper:

```bash
/Volumes/JoeSSD/SymLinks/Local_System/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php \
  -d "mysqli.default_socket=/Users/joe/Library/Application Support/Local/run/33aX1oovp/mysql/mysqld.sock" \
  /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp \
  --path=/Volumes/JoeSSD/SymLinks/Local_Sites_Home/palmetto-stg/app/public \
  {COMMAND}
```

Replace `{COMMAND}` with any WP-CLI command. Example:

```bash
# Get post info
... wp post get 686 --fields=ID,post_name,post_title,post_status,post_type

# Get post meta
... wp post meta get 686 _wp_page_template

# List all pages
... wp post list --post_type=page --fields=ID,post_name,post_title,post_status

# List all Reusable Blocks
... wp post list --post_type=wp_block --fields=ID,post_name,post_title

# Export post content to file
... wp post get 686 --field=post_content > /tmp/page-686-content.html

# Update post content (KSES-safe via direct DB)
... wp db query "UPDATE wp_posts SET post_content='...' WHERE ID=686"

# Flush post cache after direct DB update
... wp cache flush
```

---

## 5. Locating Physical Asset Files

### From a known page slug
```bash
# Find CSS and JS for page "about"
ls align-child/assets/builder/ | grep "about"
# → style-page-about.css
# → script-page-about.js

# From the themes directory:
find . -name "style-page-*.css" | sort
find . -name "style-block-*.css" | sort
find . -name "style-part-*.css" | sort
find . -name "style-template-*.css" | sort
```

### Asset pattern from post ID
Some pages name their assets with the numeric ID:
```bash
ls align-child/assets/builder/ | grep "686"
```

### Check if template conditional CSS exists
```bash
ls align-child/assets/src/template/ | grep "template-policy"
# → template-policy/
ls align-child/assets/src/template/template-policy/css/
# → important.css
```

---

## 6. Inspect Inline Styles from post_content

When `assets/builder/style-page-{slug}.css` doesn't exist yet (new page), the CSS is stored inline in `post_content`:

```sql
SELECT post_content FROM wp_posts WHERE ID = 686;
-- Look for: <style id="vvvebjs-styles">...</style>
-- Or: <style id="style-page-sitemap">...</style>
```

Extract inline style in bash:
```bash
# Extract inline CSS from post content
wp post get 686 --field=post_content \
  | grep -o '<style[^>]*>.*</style>' \
  | sed 's/<[^>]*>//g'
```

---

## 7. Reusable Block Overrides API

Align Builder stores per-page overrides for Reusable Blocks in post meta:

```bash
# Read overrides for page 686 (which uses Reuse Block 611 with custom title)
wp post meta get 686 _align_reusable_overrides
# Returns JSON: [{"id":"ov-5538jp73","value":"CUSTOM HERO TITLE"}]
```

```bash
# Set override via REST
curl -X POST \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"post_id": 686, "block_id": 611, "overrides": [{"id":"ov-5538jp73","value":"NEW TITLE"}]}' \
  "https://yoursite.test/wp-json/align-reuse/v1/save"
```

---

## 8. 3-Step Debug Process

When a page is not rendering correctly, follow this order:

### Step 1: DB check
```sql
SELECT post_content FROM wp_posts WHERE ID = 686;
-- Verify the HTML/CSS/JS is saved correctly in post_content
```

### Step 2: Disk check
```bash
cat align-child/assets/builder/style-page-sitemap.css
# Verify physical file matches DB content
```

### Step 3: Frontend check
```bash
curl -s "https://yoursite.test/sitemap/" | grep -o '<style id="style-page[^"]*"[^>]*>[^<]*'
# Check what is actually rendered in the browser HTML response
```

If Step 1 is correct but Step 3 shows wrong content → CSS cache issue → flush WordPress cache.  
If Step 1 is correct but Step 2 differs → dual-storage is out of sync → rewrite the physical file.
