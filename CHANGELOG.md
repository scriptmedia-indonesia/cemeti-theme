# Changelog

Versions follow `themes/scriptmedia-cemeti/style.css`. Bump it there (and nowhere else), add a line here, run
`bash scripts/build-zip.sh`.

## 0.5.1 — quality pass
Fixed
- Home had no `<h1>` (accessibility and SEO); it now has one for the institution name.
- Archive headings read "Exhibitions", "Residencies" …; the design uses the singular ("Exhibition", "Residency").
- Detail pages centred the title block; the design left-aligns it under a centred poster.
- A mistyped e-mail in the application form showed "fill in the required fields" instead of "the e-mail address is not valid".
- Admin meta boxes printed empty `id=""` attributes and a label pointing at nothing (repeater fields).
- Deleting an Application left its PDFs on disk; they are now deleted with the entry.
- Search toggle and the ID/EN links were smaller than the 24px touch-target minimum (WCAG 2.2).
- Skip link was invisible when the page was scrolled; it is now fixed to the viewport.
- Page heading and Back link crowded each other on 360px screens; they now stack.
- Converter: the old-site host field kept characters after a slash.

Changed
- Logo ships as 480/960px WebP (15 KB / 31 KB) with `srcset` instead of one 161 KB file on every page.
- Version is read from `style.css` (`CEMETI_VERSION`); no second place to update.
- Rewrite rules are flushed once after a theme update, so new content types work without visiting Permalinks.
- `cemeti_config` filter: config arrays (post types, fields, migration rules …) can be changed from a child theme or plugin.
- `cemeti_pre_move_upload` filter (lets tests replace `move_uploaded_file`); download path logic split into `cemeti_application_file_path()`.
- Removed dead code (`cemeti_core_active`).

Added
- Test suite on a simulated WordPress (`tests/`), browser tests, static consistency checks, `tests/run.sh`, `scripts/build-zip.sh`, CI workflow, `docs/MAINTENANCE.md`.

## 0.5.0 — M5: SEO and hardening
Meta description, Open Graph, JSON-LD, noindex rules, robots.txt, sitemap tweaks, security headers, hardening, launch checklist.

## 0.4.0 — M4: applications and search
Open Call application form with PDF uploads and anti-spam, private Applications, notification e-mails, search by people.
Converter "Cemeti rules" mode built from the old site's export; event categories go to Public Program.

## 0.3.1 — category converter
Tools → Cemeti Converter with preview, batches and revert; 301 redirects for old URLs and categories.

## 0.3.0 — M3: templates
Home, archives, detail pages, About, Open Archive, Support Us, Contact, search, 404.

## 0.2.0 — M2: content model
Post types, taxonomies, fields, status, two-way related, What's on feed.

## 0.1.0 — M1: foundation
Tokens, fonts, header, overlay menu, search strip, footer, language switch, Site Settings.
