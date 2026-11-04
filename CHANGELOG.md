# Changelog

Versions follow `themes/scriptmedia-cemeti/style.css`. Bump it there (and nowhere else), add a line here, run
`bash scripts/build-zip.sh`.

## 0.9.3

- English and Indonesian now look the same. Cause of the difference seen on "Tentang": the Indonesian page had its
  own slug, so the theme did not know it is the About page and drew it with the plain page template (blue title,
  picture as a content image, no Advisory Board / Team). Now a page is recognised by (1) the new "Cemeti page design"
  selector in the page editor, (2) the design of its Polylang translation, (3) well-known slugs (tentang, arsip-terbuka,
  dukung-kami, hubungi-kami …).
- A page or entry without a picture uses the picture of its translation; on About the picture that opens the text is
  lifted into the hero slot, so the size is the same in both languages.
- Test: every main template is rendered in English and in Indonesian and must have the same structure.

## 0.9.2

- About: the Advisory Board cards are denser (photo about half of the cell, larger name / title / quote, tighter
  rows). The Team block is now a centred list as on the old site: each role in bold with its names under it, between
  two rules; people with the same role are grouped; no photos.

## 0.9.1

- Fixed: people added under People did not appear on About. The page asked for the group by exact slug; Polylang
  renames a second-language copy of a term ("advisory-board-en") and hides entries that have no language. Groups are
  now recognised by slug or name (also "Dewan Penasihat"), a person with no group is shown under Team, and when the
  current language has nobody, people of all languages are shown. The Solo/Group filter (?type=) accepts "-en" / "-id" slugs too.

## 0.9.0

- Converter and "Move to …" now split the old text into the new structure: Foto Karya / Galeri → Gallery, Dokumentasi →
  Documentation, Biografi → Biographies (one block per artist, with photo), PDF link → Booklet; the old Daftar Isi,
  language links, back links and Terbitan Terkait are dropped. The original text is kept and restored by Revert.
  Option on the Converter page (on by default). Run against the real export: 167 of 561 published posts have sections.

## 0.8.4

- Imported posts can be sorted where they are: Posts → bulk action "Move to …" (type and term) and a "Move this post"
  box on the edit screen, using the same conversion as the Converter. A notice on the Posts list explains it.

## 0.8.3

- Public Program and Open Studio detail pages follow their Figma template: no contents bar at the top (the "back to
  contents" links stay), speakers 40 px, then the day with its weekday ("Jumat, 30 Januari 2026" / "Friday, 30 January
  2026") and the time at 24 px, and a poster that keeps its own proportions (at most 655 px high).

## 0.8.2

- Residency detail now has the same sections as Exhibition (Foreword, Gallery, Documentation, Biography with several
  residents, Booklet, Related) in the editor and on the page, with the same Figma measurements. Tests check both.

## 0.8.1

- Pinned section titles (Home sections, page titles) now stay on top of the content that scrolls under them, as in the
  prototype (the content no longer covers them). Hover: title blue and underlined; a Home entry turns salmon and
  underlined as a whole (title, names, date) and is one clickable block.

## 0.8.0

QA round 1 (see `docs/QA-ROUND-1.md` for every test case).

- Languages: English is the main language; Cemeti content types and taxonomies are now registered correctly with
  Polylang (they were ignored, which removed the language selector on those menus and the string switching);
  language slugs such as en-us / in / id_ID are understood; warnings in the admin when Polylang or a type is missing.
- Pictures: entries without a featured image use their first picture. Default route / social links; linked address.
- Layout: sticky section titles, sticky contents bar, back-to-contents fixed, hover colours, Previous buttons equal,
  About (yellow title, one column, 3-column Advisory Board with quote), share icons, small download buttons,
  Open Call list in the design layout, Residency Regular / Research, scroll loading for lists.
- Content: Solo/Group set automatically (and by a button), old "English | Indonesia" links hidden, term "Reguler" renamed "Regular".
- Admin: help box on each fixed page, Maintenance buttons on Settings → Cemeti, Cemeti settings with English first.

## 0.7.1

- Fixed: About, Open Archive, Support Us and Contact Us gave "page not found" on a fresh install, because the
  templates only apply to pages with those slugs and nothing created the pages. They are now created on
  activation (one per language with Polylang, linked as translations, Support Us with its four rows), an
  administrator notice with a "Create them" button appears if one is missing, and the page keeps its template
  even if the slug changes.
- Contact Us block follows the reading column; Support Us text 848 wide and About picture 848 x 602 as in Figma.
- Tests: `flows-pages.php` (creation, no duplicates, edits kept, notice, permissions, template, rendering, Polylang).

## 0.7.0

- Whole site matched to the Figma file: header (140 px, logo 200 px, Menu/language 24 Medium, shadow), footer (254 px,
  211 px text block, 22/12 px type), page headings (64 px), archive cards (308 x 386 with the title block over the
  poster, 16 px gaps), Home sections (272 x 340 poster, 48/40 px text, 272 x 31 buttons, residency 416 x 333),
  journal rows (24/40/24), Open Call (416 x 520 picture, 48 px title, 24 px text), Open Archive and Support sizes.
- Medium (500) is now the "semi-bold" weight everywhere (was 600/700, which fell back to Bold).
- Buttons use the cream text colour of the design.
- Browser tests check these numbers at 1440 px (`figma:` and `figma home:` checks).

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
