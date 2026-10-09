# Livecrafts (WordPress plugin, 0.14)

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

## Setup (from zero)

This repo is the **WordPress plugin**. It needs the Livecrafts **backend** (repo `livecrafts-backend`, a Node app) to
power the AI chat - set that up too, its README has the steps. The plugin alone gives you drafts, history and
releases; the chat and the assistant come from the backend.

### 1. What you need
| | |
|---|---|
| **WordPress 6.2+ and PHP 7.4+** | A site you can log in to as an **administrator**. Try it on a local or staging site first. |
| **The Livecrafts backend** | Running and reachable from your browser (default `http://127.0.0.1:8790`). |
| Optional | **Elementor** and/or **ACF** (ACF PRO for repeaters / flexible content). Block-editor (Gutenberg) content and Additional CSS work without them. |

### 2. Get the plugin into WordPress
The plugin is the `livecrafts/` folder. Pick one:

**A. Upload a zip (easiest).** Build the zip - the folder must be named `livecrafts` inside it:
```bash
git archive --format=zip --prefix=livecrafts/ -o livecrafts.zip HEAD:livecrafts
```
(or zip the `livecrafts` folder yourself so that `livecrafts/livecrafts.php` is inside the zip). Then in wp-admin:
**Plugins -> Add New Plugin -> Upload Plugin**, choose `livecrafts.zip`, **Install Now**, **Activate**.
To update later, upload a new zip and choose *Replace current with uploaded*; raise `LIVECRAFTS_VERSION` in
`livecrafts/livecrafts.php` first so browsers drop the cached `widget.js`.

**B. Copy the folder.** Copy `livecrafts/` to `wp-content/plugins/livecrafts/` on the site (FTP, file manager, or a
symlink on a local site), then activate it under **Plugins**.

On activation the plugin creates its own tables (`livecrafts_changes`, `livecrafts_releases`, `livecrafts_snapshots`),
the capabilities and a secret. Nothing else is needed - there is no `.env` for the plugin.

### 3. Connect it to the backend
1. **Application Password.** In wp-admin: **Users -> Profile -> Application Passwords**, name it `livecrafts`, click
   *Add New* and copy the password (shown once). Use an **administrator** account. (On a live site WordPress only
   offers Application Passwords over HTTPS.)
2. **Tell the backend about the site.** Open the backend app, sidebar -> **Connect a site**, and enter the site URL, the
   WordPress username and that Application Password.
3. **Tell WordPress where the backend is.** **Settings -> Livecrafts Assistant -> Livecrafts backend address**:
   `http://127.0.0.1:8790` when the backend runs on your own computer, otherwise its public `https://` address.
   Here you can also set the assistant's name, model and instructions.
4. **Deploy password (optional).** **Settings -> Livecrafts**: by default deploying asks for the editor's own WordPress
   password; an administrator can set a separate deploy password here, which then replaces it.
5. Log in as an editor/admin and open any page of the site: the Livecrafts chat button appears. Ask for a change - it
   becomes a **draft** only editors see; **Deploy** (password) makes it live.

### 4. Who can do what
`livecrafts_edit` (make drafts, use the chat) and `livecrafts_deploy` (publish drafts, reset) are WordPress capabilities.
Administrators have both, Editors get `livecrafts_edit`; give them to other roles with any role-editor plugin.

### Troubleshooting
| Problem | Fix |
|---|---|
| No chat button | You are logged out or have no `livecrafts_edit` capability; or the plugin is inactive. |
| Chat button opens an empty/failed frame | The backend is not running, or *Livecrafts backend address* is wrong. A site on `https://` needs an `https://` backend (browsers block mixed content). |
| Backend "Connect a site" fails | Wrong Application Password, the user is not an administrator, the plugin is not active, or REST API / Application Passwords are blocked by a security plugin or the host. |
| Old widget after an update | Bump `LIVECRAFTS_VERSION` and hard-refresh (Ctrl+F5). |
| Upgrading from 0.9 | Settings -> Livecrafts shows the old overlay; its styles are moved into a draft CSS block to review. |

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
| `includes/templates.php`, `templates-lib/` | Template library (25 ready sections, shortcodes `[ai_hero ...]`): catalogue + usage endpoint `GET /templates`, `?lc_templates=1` outline for editors, usage table in Settings → Livecrafts. |
| `includes/theme-files.php`, `includes/file-changes.php`, `includes/audit.php` | Theme file access for the backend (every write/delete is recorded in the ledger as `file.write`: revertable by id, undone by Discard all while unreleased, accepted by Deploy, restored by a reset); "where is this text stored" diagnostics. |

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
Needs PHP and a **local** WordPress checkout (never a live site). `tests/integration.php` runs the whole flow against a **local** WordPress (it creates and deletes its own content):

```bash
php tests/integration.php "/path/to/wordpress"
```

Offline checks (no database needed):

```bash
php tests/blocks-offline.php "/path/to/wordpress"
php tests/acf-rows-offline.php
php tests/elementor-offline.php
php tests/file-changes-offline.php
```

## Template library (0.14)
`templates-lib/` is a Tailwind v4 component library (hero, hero_centered, cta, feature_grid, steps, stats, logo_cloud, testimonials, team, pricing, faq,
card, blog_grid, newsletter, contact_form, header, footer, sidebar, breadcrumbs, pagination, announcement, button, badge, alert, empty_state).
The assistant fills them with content only; each is ONE shortcode (`[ai_faq heading="..."][ai_item title="..." text="..."][/ai_faq]`), so it works in
Elementor (Shortcode widget), blocks, Divi, WPBakery, classic content, and from PHP / ACF loops with `ai_component( 'card', array(...) )`.

* **Follows the site:** components read tokens (`--ai-surface`, `--ai-ink`, `--ai-muted`, `--ai-line`, `--ai-radius`, `--ai-max`, `--ai-py`, `--ai-py-sm`, `--ai-brand`).
  Fallback chain: token -> the block theme's own palette (`--wp--preset--color--base / contrast / primary`) -> neutral default. Per section, set them with
  shortcode attributes (`max`, `py`, `py_sm`, `radius`, `brand`, `surface`, `ink`, `muted`, `line`, `font`); the assistant measures the page's own sections and fills them in.
* **See that it is used:** every template renders inside `<div class="ai-t" data-lc-template="faq">`. Open any page as an editor with `?lc_templates=1`:
  template sections are outlined and labelled, with a count in the corner. Settings → Livecrafts → Templates lists, per template, the pages that use it (live or draft)
  and URLs where theme code rendered it. `GET /livecrafts/v1/templates` returns the same for the backend.
* **Develop:** edit `templates-lib/src/templates/*.php` (plain Tailwind classes; use the semantic ones: `bg-surface`, `text-ink`, `text-muted`, `border-line`, `rounded-card`,
  `max-w-wide`, `py-sec-sm sm:py-sec`), then in `templates-lib/`: `python tools/prefix.py && npx tailwindcss -i src/input.css -o assets/css/ai-components.css --minify && python tools/check.py`.
  Never edit `templates/` by hand. If the standalone "AI Components" plugin is active it is used instead of the bundled copy.
* Not run on a live WordPress yet: PHP was syntax-checked and the CSS fit was verified in Chrome on static renders of the templates.

## Known limits
* Elementor support is built on Elementor's own APIs (controls registry, `Document::save`, preview CSS) and covered by
  offline tests, but not yet run on a site with Elementor installed.
* Block-theme navigation (`wp_navigation`) and template parts are not editable yet; classic menus are.
* ACF repeaters / flexible content need ACF PRO on the site.
