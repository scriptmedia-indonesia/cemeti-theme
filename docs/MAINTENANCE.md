# Maintenance guide

For whoever looks after the theme next. Read this first, then `ARCHITECTURE.md`.

## 1. Where things live

```
themes/scriptmedia-cemeti/
  style.css            theme header; the ONLY place the version is written
  functions.php        loads inc/*.php in order (the list is the table of contents)
  config/              data, not code: post-types, taxonomies, fields, migration-rules
  inc/                 behaviour, one concern per file (see below)
  template-parts/      pieces reused by templates (card, row, menu, search, form …)
  *.php (root)         WordPress templates: front-page, archive*, single*, page-*, search, 404
  assets/{css,js,fonts,img}
tests/                 simulated WordPress, flow tests, static checks, browser tests
scripts/build-zip.sh   builds the installable zip
docs/                  you are here
```

| `inc/` file | Owns |
|---|---|
| `i18n.php` | language helpers, **all UI strings (Indonesian + English)**, language switcher |
| `options.php`, `settings.php` | Site Settings (Settings → Cemeti), per language, with defaults |
| `content-model.php` | registers post types/taxonomies from `config/`, seeds default terms, rewrite flush |
| `fields.php`, `relations.php` | the "Details" meta box, saving, two-way "related" |
| `status.php` | Current / Upcoming / Previous from dates; `cemeti_status_query()` |
| `queries.php` | main-query rules for archives and search, A–Z list |
| `templates.php` | helpers used by templates (headings, photos, share, A–Z grouping) |
| `navigation.php` | overlay menu definition and rendering |
| `applications.php` | Open Call form handler, uploads, e-mails, admin side |
| `search.php`, `seo.php`, `security.php` | people-aware search; meta/JSON-LD/robots; hardening and headers |
| `redirects.php`, `migration*.php` | old-site redirects; the converter and its rules |
| `core-bridge.php` | `cemeti_config()` loader + optional hand-over to Scriptmedia Core |

Rules the code follows: every function is prefixed `cemeti_`; every file starts with the `ABSPATH` guard;
text domain is `scriptmedia-cemeti`; tabs for indentation; output is escaped at the point of output.
`php tests/static-checks.php` enforces most of this.

## 2. Everyday recipes

**Change a text the visitor sees** (button, label, message): edit the entry in `inc/i18n.php → cemeti_ui_strings()`
(both `id` and `en`). Editors can also override it in Polylang → Strings. Use `cemeti_t( 'key' )` in templates.
Placeholders (`%s`) must be the same in both languages (the static check verifies it).

**Change a menu entry**: `inc/navigation.php → cemeti_default_menu()`, or filter `cemeti_default_menu`.
Paths must be a content-type URL (`config/post-types.php`) or one of the pages listed in the static check.

**Add or change a field** on a content type: `config/fields.php` (types: text, textarea, date, url, image,
images, relation, repeater). It appears in the "Details" box and is stored as post meta `cemeti_<key>`.
Read it with `cemeti_get_field( 'key', $post_id )`. Show it in the template (`single.php` etc.).

**Add a content type**: add it to `config/post-types.php` (key ≤ 20 chars, a URL `slug`), its fields to
`config/fields.php`, a menu entry, and a template (`single-<type>.php` or reuse `single.php`). Rewrite rules
refresh by themselves after a theme update (`cemeti_flush_after_update`). Add dates to `cemeti_date_keys()` in
`inc/status.php` if it has a time window. Then run the tests: the static check will tell you what is missing.

**Add a taxonomy**: `config/taxonomies.php` (`terms` are created once). **Add a page template**:
`page-<slug>.php` is picked by WordPress for the page with that slug.

**Change colours, sizes, spacing**: `assets/css/tokens.css` (CSS variables) first; component rules live in
`components.css`, layout in `layout.css`, page-specific rules in `pages.css`. Colours come from the design PDF
(`DESIGN-NOTES.md`); several fail WCAG AA contrast and need a client decision before being changed.

**Add the licensed font** (Akzidenz-Grotesk Next): put the WOFF2 files in `assets/fonts/`, add an `@font-face` in
`tokens.css` named exactly `"Akzidenz-Grotesk Next"`, preload it in `inc/enqueue.php`. Nothing else changes.

**Swap the logo**: replace `assets/img/logo.webp` and regenerate `logo-480.webp` / `logo-960.webp` (see the
build note below). Prefer an SVG once the client has one; then use it in `header.php` and drop the two sizes.

**Change how the old site's categories are sorted**: `config/migration-rules.php` (or the `cemeti_config`
filter). Re-run `php tests/flows-migration.php` afterwards.

**Customise from a child theme or plugin without editing this theme**: filters `cemeti_config`,
`cemeti_default_menu`, `cemeti_whats_on_items`, `cemeti_application_documents`, `cemeti_application_max_file`,
`cemeti_security_headers`.

## 3. Tests: what they cover and how to run them

```
bash tests/run.sh                # everything (browser tests too if Playwright is installed)
bash tests/run.sh --no-browser   # what CI runs on every PHP version
php tests/flows-apply.php        # one suite
node tests/browser/serve.js      # then open http://127.0.0.1:8765/build/qa/home.html by hand
```

| Suite | Proves |
|---|---|
| `static-checks.php` | every `inc/` file is loaded; no duplicate or undefined functions; UI strings complete in both languages; CSS and templates agree; config files are consistent; assets exist; no debug leftovers |
| `render-pages.php` | every template renders with realistic data **and on an empty site**, with no PHP warning; valid markup, unique ids, labels, alt text, one `<h1>`, heading order, safe links, escaping against injection |
| `flows-content.php` | post types/taxonomies/terms, saving fields (nonce, permissions, sanitising), two-way related, status logic on the boundary days, What's on and its cache, archive ordering and filters, SEO tags and JSON-LD, security hooks, redirects |
| `flows-apply.php` | the application form from submit to stored entry and e-mails; every refusal (spam, size, type, closed, rate limit); header injection; cleanup on failure; download permissions and path traversal; deleting an entry deletes its files |
| `flows-migration.php` | the converter end to end on a synthetic old site: batches, dates from text, tags, projects, languages, redirects, revert, manual mode |
| `flows-polylang.php` | the theme with Polylang active: URLs per language, strings, switcher, converter linking translations |
| `m2…m5-logic`, `migration-logic` | small pure functions: date reader, rules, pairing, summaries, JSON-LD builders, upload validation |
| `browser/browser-qa.js` | in Chromium: no horizontal scroll at 360/768/1280/1920 px on 16 pages, no script errors, menu/search/dialog behaviour (ESC, focus, scroll lock), keyboard focus rings, 24px touch targets, design measurements (header, menu, footer), fonts really load, form validation, colour contrast report |

**What the tests cannot prove.** They run on a simulation of WordPress (`tests/harness/wp-stubs.php`): SQL is
replaced by PHP, there is no MySQL, no real Polylang, no web server. They prove the theme builds the right
queries and templates, not that a given host behaves the same. Before a release also click through a real
install using `LAUNCH-CHECKLIST.md`. If a test passes but the real site misbehaves, add the missing behaviour to
the stub *and* a test, so it cannot regress.

**When a test fails** read the message: it names the behaviour, not the line. A failing static check usually
means a file, string or CSS rule was added in one place only.

**Adding a test.** Seed data with `qa_seed()` (or `CemetiWP::post()`), call the theme through its real hooks
(`do_action( 'save_post', $id )`, `do_action( 'admin_post_nopriv_cemeti_apply' )`), assert with `ok( $cond, 'what should hold' )`.
Add a stub function to `wp-stubs.php` only when a theme file starts calling it; the file header explains the limits.

**Coding standards.** `composer install && composer lint` runs WordPress Coding Standards (`phpcs.xml.dist`);
it is advisory in CI. It was not run while this theme was written (no network access to packagist), so expect a
first round of formatting findings.

## 4. Releasing

1. Change code; run `bash tests/run.sh`.
2. Bump `Version:` in `themes/scriptmedia-cemeti/style.css`; add a section to `CHANGELOG.md`.
3. `bash scripts/build-zip.sh` → `build/scriptmedia-cemeti-<version>.zip` (it re-runs the tests, refuses a zip that
   contains tests, docs or a wrong folder, and prints the checksum).
4. Upload via Appearance → Themes → Add New → Upload (replace the active theme when asked). Content, settings and
   applications are stored in the database and survive a theme update; permalinks refresh by themselves.
5. Smoke test: Home, an exhibition, the Open Call form (send a test), `/wp-sitemap.xml`.

Regenerating the logo sizes (needs Python with Pillow):
```
python3 - <<'PY'
from PIL import Image
src = Image.open('themes/scriptmedia-cemeti/assets/img/logo.webp').convert('RGBA')
for w in (480, 960):
    r = src.resize((w, round(src.height * w / src.width)), Image.LANCZOS)
    r.quantize(colors=128, dither=Image.Dither.NONE).convert('RGBA').save(f'themes/scriptmedia-cemeti/assets/img/logo-{w}.webp', 'WEBP', lossless=True, method=6)
PY
```

## 5. When something is wrong

| Symptom | Look at |
|---|---|
| A content type URL gives 404 | Settings → Permalinks → Save. The theme also flushes once per version (`cemeti_rewrite_version` option) |
| "What's on" is empty | no entry is *current* today (check dates; the day is computed in the site timezone); the list is cached until content is saved |
| Archive shows entries in a strange order | they sort by start date; an entry without a date sorts last — fill the dates |
| Wrong Current/Upcoming/Previous | the date fields (`cemeti_start_date`, `cemeti_end_date`, Open Call `cemeti_deadline`), site timezone under Settings → General |
| Application e-mails do not arrive | the host's mail; use an SMTP plugin; check Settings → Cemeti → notification address |
| Application upload fails silently | PHP `upload_max_filesize`/`post_max_size` below 25 MB; the form then arrives empty and shows "could not be sent" |
| Form says "could not be sent" for normal visitors | a page cache served an old form: exclude Open Call pages from caching (nonce and time token expire) |
| Converter says nothing to convert | everything is already converted (`_cemeti_converted_at`), or no category is mapped in Manual mode |
| Twin posts not linked | Polylang must be active with `id` and `en` *before* converting; then press "Link translations" |
| Old URL does not redirect | the path is built from the permalink pattern on the Converter page; compare with the real old URL |
| Fonts look wrong | files in `assets/fonts/` and `@font-face` in `tokens.css`; Akzidenz-Grotesk Next is not bundled (Hanken Grotesk stands in) |
| White screen after an edit | `wp-content/debug.log` with `WP_DEBUG_LOG`; `php -l` the file you edited |

## 6. Known gaps and open decisions

- **What's on has no trigger.** The panel, its content and its keyboard handling work (tested with an injected
  button), but the design does not show how it opens, so nothing on the site opens it yet. Add a
  `<button data-cemeti-toggle="whats-on" aria-controls="cemeti-whats-on" aria-expanded="false">` where the client wants it.
- Design colours that fail WCAG AA contrast (`DESIGN-NOTES.md` §4) are used as drawn; the browser test lists them and
  fails if a *new* failing pair appears.
- Contact, Search, 404, Open Call list, People grid and all mobile layouts are derived, not in the PDF.
- The Scriptmedia Core hand-over (`core-bridge.php`) is off by default and its filter names were never checked
  against a real Core; verify them before turning on `CEMETI_USE_CORE_REGISTRAR`.
- Content-Security-Policy, malware scanning and a WAF are host-level work (`LAUNCH-CHECKLIST.md` §7).
