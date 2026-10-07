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
| `includes/kinds.php` | What can be changed and how (see the table below). |
| `includes/drafts.php` | Draft = live + draft changes; preview copies; create / revert / discard; new pages as drafts. |
| `includes/preview.php` | Editors see drafts on the front end; visitors never do; Elementor caches never store draft output. |
| `includes/watch.php` | Records changes made outside Livecrafts (WP admin, block editor, Elementor editor, ACF, Customizer). |
| `includes/deploy.php` | Deploy (check everything → write with each source's own API → read back → snapshot), reset, baseline. |
| `includes/notes.php` | Site and page notes. |
| `includes/rest.php`, `includes/bridge.php` | REST API `livecrafts/v1/*` (see the comment at the top of rest.php). |
| `includes/targets.php` | ACF: scan (groups, repeaters, flexible content), validation, row renumbering. |
| `includes/elementor.php` | Elementor: settings checked against Elementor's controls, structure operations, outline. |
| `includes/blocks.php` | Block content: path addressing, block operations, `data-lc-block` markers for editors. |
| `includes/css.php` | Additional CSS blocks, style rules, entrance animations. |
| `includes/resolve.php` | `POST /resolve`: what a clicked element is and which actions it supports (no AI). |
| `includes/migrate.php` | Moves the 0.9 overlay: styles → a draft CSS block to review; texts → a report. |
| `includes/assistant.php`, `assets/widget.*` | The chat widget, the drafts bar (preview / live, discard, deploy dialog). |
| `includes/admin.php` | Settings → Livecrafts: deploy password, old overlay, releases, recent changes. |
| `includes/theme-files.php`, `includes/audit.php` | Theme file access for the backend; "where is this text stored" diagnostics. |

## Change kinds (`POST /livecrafts/v1/changes`)
| Kind | Target | Value |
|---|---|---|
| `post.field` | `title` / `content` / `excerpt` / `status` | text |
| `post.meta` | `_thumbnail_id`, `_wp_page_template`, menu links: `_menu_item_url`, `_menu_item_target` | id / template / url / yes |
| `acf.field` | ACF field key (top level) | value for the field type |
| `acf.value` | meta name, e.g. `sections_0_title` (groups, rows) | value for the field type |
| `acf.rows` | repeater / flexible content meta name | `{op: add\|remove\|move\|duplicate, index, to, layout}` |
| `el.setting` | `<element id>:<control>`, e.g. `3f2a1c:title_color` | checked against the Elementor control |
| `el.insert` / `el.remove` / `el.duplicate` / `el.move` | `<parent id\|root>:<index\|end>` / `<element id>` | Elementor JSON / - / - / `up`, `down`, `{parent, index}` |
| `block.text` / `block.link` / `block.image` / `block.class` | block path, e.g. `2.0` | inline HTML / url / attachment id / class |
| `block.replace` / `block.insert` / `block.remove` / `block.move` / `block.duplicate` | path / `<parent path>:<index\|end>` | block markup / markup / count / `up`, `down`, index |
| `css.rule` | (derived) | `{selector, media: ''\|desktop\|tablet\|mobile, declarations}` |
| `css.block` | block name | CSS (no `!important`) |

## Testing
`tests/integration.php` runs the whole flow against a **local** WordPress (it creates and deletes its own content):

```bash
php tests/integration.php "/path/to/wordpress"
```

Offline checks (no database needed):

```bash
php tests/blocks-offline.php "/path/to/wordpress"
php tests/acf-rows-offline.php
php tests/elementor-offline.php
```

## Known limits
* Elementor support is built on Elementor's own APIs (controls registry, `Document::save`, preview CSS) and covered by
  offline tests, but not yet run on a site with Elementor installed.
* Block-theme navigation (`wp_navigation`) and template parts are not editable yet; classic menus are.
* ACF repeaters / flexible content need ACF PRO on the site.
