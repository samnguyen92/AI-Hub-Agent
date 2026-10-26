# Align Agent Multi-language Tools (`i18n`)

Tools for managing multi-language configurations, inspecting translations, generating translated drafts, and localizing internal page URLs within the Align ecosystem.

---

## Tool Registry

| Tool | Type | Scope | Danger | Capability | Requires Builder | Description |
|---|---|---|---|---|---|---|
| `list_languages` | read | local | no | `edit_posts` | no | List all configured site languages and default language. |
| `get_translations` | read | local | no | `edit_posts` | no | Retrieve translations linked to a post and list missing languages. |
| `translation_overview` | read | local | no | `edit_posts` | no | Sitewide translation coverage overview and completion statistics. |
| `create_translation` | write | local | no | `edit_posts` | no | Create a draft translation copy of a post/page with builder assets. |
| `manage_language` | write | global | yes | `manage_options` | no | Add, update, or remove a supported site language. |
| `scan_unlocalized_urls` | read | local | no | `edit_posts` | no | Find internal links in translated posts pointing to default language. |
| `localize_post_urls` | write | local | no | `edit_posts` | yes | Rewrite internal links to point to localized language URLs. |

---

## Workflow: Translating a Page

1. Call `create_translation` with `source_id` and `target_lang`. This generates a new linked post in `draft` status (or retains live status if source was front page after approval).
2. Translate text content using `patch_content` on the newly created draft post ID.
3. Call `localize_post_urls` with `post_id` to adjust internal navigation and page links to match target language slugs.
