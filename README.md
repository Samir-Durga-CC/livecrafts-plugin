# Livecrafts (WordPress plugin, 0.10)

Edit a live WordPress site with an AI assistant or by clicking on the page - safely:

* **Drafts first.** Every change is a draft. Logged-in editors see it on the site (preview); visitors see the live site
  until someone **deploys**. Deploying asks for a password (the person's WordPress password, or a separate deploy
  password set in Settings → Livecrafts).
* **Real sources, no patches.** Changes go into the place the content really lives - Elementor settings (saved with
  Elementor's own `Document::save()`), ACF fields (`update_field`), post title/content/excerpt (`wp_update_post`) and,
  for site-wide styles, WordPress's Additional CSS in named `livecrafts:` blocks. `!important` is refused.
* **Complete history.** Every change is recorded with who, from where (assistant, widget, WP admin, Elementor editor),
  before and after - including changes made outside Livecrafts. Every change can be reverted.
* **Releases.** Each deploy is a release with notes. The site can be reset to any earlier release or to a baseline
  (e.g. the moment it was handed over to the client).
* **Notes.** The assistant keeps notes about the site and each page, stored in WordPress.

## Files (`livecrafts/`)
| File | Job |
|---|---|
| `livecrafts.php` | Bootstrap, activation (tables, capabilities, secret, migration). |
| `includes/schema.php` | Tables: `livecrafts_changes`, `livecrafts_releases`, `livecrafts_snapshots`. |
| `includes/auth.php` | Capabilities `livecrafts_edit` / `livecrafts_deploy`, signed widget tokens, deploy password (5 tries, then 15 min lock). |
| `includes/ledger.php` | The change history and releases. |
| `includes/snapshots.php` | Object data (post fields + content meta, Additional CSS), snapshots, restore. |
| `includes/kinds.php` | What can be changed and how: `post.field`, `acf.field`, `el.setting`, `css.block`, `object.restore`. |
| `includes/drafts.php` | Draft = live + draft changes; preview copies; create / revert / discard; new pages as drafts. |
| `includes/preview.php` | Editors see drafts on the front end; visitors never do; Elementor caches never store draft output. |
| `includes/watch.php` | Records changes made outside Livecrafts (WP admin, block editor, Elementor editor, ACF, Customizer). |
| `includes/deploy.php` | Deploy (check everything → write with each source's own API → read back → snapshot), reset, baseline. |
| `includes/notes.php` | Site and page notes. |
| `includes/rest.php`, `includes/bridge.php` | REST API `livecrafts/v1/*` (see the comment at the top of rest.php). |
| `includes/targets.php`, `includes/elementor.php`, `includes/css.php` | ACF, Elementor and Additional CSS helpers. |
| `includes/migrate.php` | Moves the 0.9 overlay: styles → a draft CSS block to review; texts → a report. |
| `includes/assistant.php`, `assets/widget.*` | The chat widget, the drafts bar (preview / live, discard, deploy dialog). |
| `includes/admin.php` | Settings → Livecrafts: deploy password, old overlay, releases, recent changes. |
| `includes/theme-files.php`, `includes/audit.php` | Theme file access for the backend; "where is this text stored" diagnostics. |

## Testing
`tests/integration.php` runs the whole flow against a **local** WordPress (it creates and deletes its own content):

```bash
php tests/integration.php "/path/to/wordpress"
```

## Known limits
* Elementor preview/deploy is implemented with Elementor's own APIs but not yet tested on a site with Elementor.
* Menus, Gutenberg block-level operations, ACF repeaters and Elementor section operations come next.
