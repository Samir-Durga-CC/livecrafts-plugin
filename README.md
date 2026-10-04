# Livecrafts (prototype v0.5)

Click any element on any WordPress page, find its REAL source (ACF field / Elementor setting), change it, and verify the change.
Where no source is found it falls back to a non-destructive patch (CSS + text) stored in the database.
The DORA Live Editor (`dora-agent/plugin-template`) remains the editor for DORA-generated sites that carry `data-dora-id`.

## Files (`livecrafts/livecrafts/`)
| File | Job |
|---|---|
| `livecrafts.php` | Bootstrap. Prints saved CSS for all visitors, loads the editor only for logged-in editors. |
| `includes/store.php` | Patch storage, CSS allowlist, selector/value validation, activity log. |
| `includes/targets.php` | ACF scan + render trace, verified ACF write-back (validate -> write -> READ BACK -> rollback), debug endpoints. |
| `includes/elementor.php` | Elementor adapter: widget id -> setting, saved through Elementor's own `save()`, verified by read-back. |
| `includes/audit.php` | `POST debug/locate`: where in the database does a given text live (diagnostic). |
| `includes/rest.php` | REST routes: save, revert, undo, target, debug/*. Needs login + nonce (or an Application Password). |
| `includes/admin.php` | Settings > Livecrafts: patches + activity log. |
| `assets/editor.js` | The editor UI, resolvers (ACF / Elementor / patch), save + page verification, Debug box, `window.Livecrafts.*`. |

## Measuring accuracy on any site: `Livecrafts.audit()`
Read-only. Scans every visible text / link / image, reports how much is linked to a real source, checks that the stored value
equals what the page shows (proves the mapping), and searches the database for what could not be linked.
Console: `await Livecrafts.audit()`  -  Panel: Debug > Run site audit.

## Known limits
- Sources covered: ACF top-level fields, Elementor classic widgets. Not yet: Gutenberg/post_content, WPBakery, Divi, menus/widgets/options, ACF repeaters.
- Desktop styles only; fallback text patches are applied by JavaScript.
