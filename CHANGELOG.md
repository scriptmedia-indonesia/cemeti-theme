# Changelog

Versions follow `themes/scriptmedia-cemeti/style.css`. Bump it there (and nowhere else), add a line here, run
`bash scripts/build-zip.sh`.

## 0.6.1

- Exhibition detail page measured from the Figma file (frame EXHIBITION ARTICLE TEMPLATE): title 64, dates and names 40,
  text 24 on 34, headings 64, contents bar 18, poster 524 wide at column 4, gallery photos 524 x 351, biography photo 308,
  related cards with 20 px padding; the browser tests check these numbers at 1440 px (`figma:` checks).
- Contents bar is a scroll-spy (`toc.js`): the section past 35% of the screen is marked.
- Biography: the name opens the first paragraph in Medium weight, as in the design.
- Jeruk Bali colour corrected to #f16154 (Figma).

## 0.6.0

- Detail page re-measured from the design: 12-column grid (reading column = columns 2-11, poster from column 4,
  about 520 px wide), larger title/date/names/text, "Daftar Isi" on its own line with the links separated by bars.
- The contents bar is a working anchor menu: the section being read is marked (`toc.js`), "back to contents" returns.
- Admin "Details" box is dynamic and grouped under headings: Artists as one row per name (Enter adds a row),
  photos added/reordered/removed one by one, biography blocks (several artists) with "Add from Artists",
  move up/down and remove, and the Booklet as a PDF from the media library (a link stays as the fallback).
  Existing content keeps working (artists remain stored as lines; `booklet_url` is still read).
- Poster image size raised to 1200 px wide (regenerate thumbnails for old uploads if needed).
- Tests: contents anchors, layout measurements, and the whole editing screen are tested in a real browser.

## 0.5.2

- Akzidenz-Grotesk Next (client-supplied) is now bundled and used for all body text; Hanken Grotesk is only a fallback.
- Detail page (exhibition, public program, open studio, residency) follows the design: one wide reading column
  (66rem) for head, info, text, photos and biographies; poster indented in the column; section headings, captions,
  "back to list" links, related cards and share row use the full container; photos 3:2; biography photo/text columns.

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
