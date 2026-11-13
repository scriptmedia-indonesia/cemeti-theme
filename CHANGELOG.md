# Changelog

Versions follow `themes/scriptmedia-cemeti/style.css`. Bump it there (and nowhere else), add a line here, run
`bash scripts/build-zip.sh`.

## 0.9.57

Each language keeps its own PDF. Polylang's "Synchronization → Custom fields" copied every Cemeti field between the
translations on each save, so removing the English PDF in the Indonesian editor changed the English entry too. Now
the PDF (file, file link, booklet), texts, credits and captions are copied once when a translation is made and are
not synchronised afterwards. Gallery, dates and featured image are still shared. Test and mutation added.

## 0.9.56

Full QA round on both languages and across browsers.

- **Contents bar on a detail page (large screens):** the link just clicked could lose its mark to the section above while
  the smooth jump was still gliding, or when the jump stopped a few pixels short at the end of the page. The clicked link
  now stays marked until the visitor scrolls themselves.
- Checked again at this version: every page of the site in **English and Indonesian** (2898 checks: markup, interface text
  left in the other language, links that keep the language, the EN/ID switch, menus, pictures, messy imported entries),
  **421 browser checks**, the **sweep** of 124 pages at 18 screen sizes plus six touch and desktop profiles, and the
  compatibility of the CSS and scripts with Safari 14.1 / iOS 14.5 / Chrome and Edge 84 / Firefox 78 (and the graceful
  fallbacks below that). Scripts are ES2017 (Safari 11+).

## 0.9.55

- **Open Archive starts empty.** The booklets of the entries and the News posts are no longer added by themselves: the page
  lists only the **Archive items** the team creates and the content ticked **"Show in the Open Archive"**. (The Media tool's
  "3. Fetch booklets" is unchanged: it still brings PDFs from the old site into the Media Library, to be chosen as the file of
  an Archive item.)

## 0.9.54

- **Open Archive: editor pages with a download button, and a tick for other content.** The front (A–Z, two columns) is
  unchanged.
  - New content type **Open Archive** in the admin menu (**Archive item**): title, the text in the editor, featured image,
    authors, year, a kind (Writing / Booklet / Catalog) and the **file to download** (a PDF from the Media Library, or a
    link). Its page has the **Download Booklet** button — **Unduh Booklet** in Indonesian — and the Open Archive lists it
    by title with a link to that page. Translate with "Create ID version (copy)" (the Indonesian copy shows the English one's
    file until it has its own).
  - New side box **Open Archive → "Show in the Open Archive"** on Exhibition, Residency, Public Program, Open Studio, Open
    Call and Journal: tick it and that page is listed in the Open Archive, whatever its kind.
  - The booklets of the entries and the News posts are still added by themselves; a page that is ticked is listed once, with
    its page (not its booklet file).

## 0.9.53

- **"3. Fetch booklets" finished at once with 0.** It only looked at the booklet link of the Exhibition / Residency / Public
  Program / Open Studio entries, but the old site's booklets are separate **Journal posts** ("Booklet Pameran Kelompok …",
  "Buklet …") with the PDF linked in their text. The step now also reads the text of those posts (and the original text the
  converter kept), takes the first PDF under the old site's `/wp-content/uploads/` and brings it into the Media Library as
  the post's **Booklet (PDF)**. In the **Open Archive**, such a post now links straight to its PDF. When a "0" remains, the
  panel lists the PDF links it found and why they were not fetched (other website, not under uploads, download failed).
- **Pinned titles ("parallax") in Firefox and Safari.** A Mac with "Reduce motion" on (System Settings → Accessibility →
  Display) made the theme turn the pinning off in Firefox and Safari, which follow that setting. Pinning is positioning, not
  animation, so the titles and the header stay pinned for those visitors too. If it still does not work, open any page
  with `?cemeti-debug=1` at the end of the address: a black box shows what the browser supports and how the titles are
  positioned (send a screenshot of it).

## 0.9.52

Older browsers, checked against the browsers' published support data (doiuse / caniuse for the CSS, es-check for the
scripts) with a baseline of Safari 14.1 / iOS 14.5 / Chrome and Edge 84 / Firefox 78 for the full design and graceful
fallbacks below it.

- **No `clamp()`** (Safari 13.0 and older, Chrome 78 and older): the fluid text, gutter and header sizes now have fixed
  fallbacks in three steps (phone, tablet, desktop); the page container and the menu panel have a width without `min()`.
- **No flex `gap`** (Safari 14.0 and older, iOS up to 14.4, Chrome 83 and older): `compat.js` detects it and the spacing of
  the card text, search bar, share buttons, related list, pagination, contents bar and file blocks comes from margins.
- **Logical shorthands** (`margin-inline`, `padding-block`, `border-block` … Safari before 14.1): 25 declarations now have
  their left / right / top / bottom form first (the page container was not centred without it); `inset` (card text,
  overlays) likewise.
- Scripts checked as ES2017 (Safari 11+): nothing newer is used.
- Static checks now also fail for logical shorthands or `inset` without their older form, tokens without the `clamp()`
  fallback, and the flex-gap fallbacks missing.
- Docs: "Browser support" in MAINTENANCE.md lists what is fully supported and what degrades.

## 0.9.51

Cross-browser round (Safari and Firefox), after a test in an older Safari (macOS Mojave / Safari 14) showed no logo, no
pictures and no pinned titles.

- **Logo missing in Safari 14 and older** (it showed an empty box): the logo was WebP only, which Safari shows only from
  macOS 11. It is now a `<picture>`: WebP for the browsers that have it, **PNG** for the others (`logo-480.png`,
  `logo-960.png`). The SEO logo is the PNG too.
- **Pinned section titles ("parallax")**: `position: -webkit-sticky` added for older Safari, and the new `pin-heading.js`
  takes over with a transform **only if** the browser does not honour the CSS (it checks after the visitor scrolls; where
  the CSS works it never does anything; visitors with "reduce motion" keep the static titles).
- **Rules older browsers dropped whole**: a rule such as `a:hover, a:focus-visible { … }` is ignored completely by a browser
  that does not know `:focus-visible` (Safari before 15.4), so hover colours were lost. Those selectors now have their own
  rules (6). `inset: 0` has its four sides before it (Safari before 14.1: the menu overlay, card pictures), and the archive
  card keeps its 308 × 386 box with a padding fallback where `aspect-ratio` does not exist (Safari before 15).
- **Tests:** the static checks now fail when CSS or JS uses something older browsers cannot read without a fallback
  (shared `:focus-visible` / `:has()` rules, sticky or inset without their older form, optional chaining, `??`,
  `replaceAll`, `.at()`, …). The browser sweep also covers 1180, 1366, 1536 and 430 px wide screens.
- **Not possible here:** Safari and Firefox themselves could not be run in this environment (only Chromium is installed,
  and the download of other engines is blocked), so the fixes above come from the browsers' documented support and were
  checked in Chromium (including a run where CSS sticky is switched off, to prove the script takes over).

## 0.9.50

- **Open Archive keeps its design again**: the A–Z list in two columns (A–M and N–Z), titles in quotes, exactly as before.
  The filters and the Download / Read buttons added in 0.9.49 are removed. Only the content changed: the list is made of
  the booklets of the entries and the News posts (each title links to its file or post).
- **Booklets from the existing website.** New step **"3. Fetch booklets"** in Tools → Cemeti Converter → "0. Media": the
  PDFs that entries still link to on the old site (`…/wp-content/uploads/….pdf`) are downloaded into this site's Media
  Library and set as the entry's **Booklet (PDF)** (the link is kept). So the files stay downloadable after the old site is
  replaced, like the pictures. Same safeguards as the picture repair: only the old site is downloaded from, steps of about
  12 seconds that continue by themselves and retry after a server error, failed files listed and retried by pressing the
  button again; PDFs on other websites are left as they are.

## 0.9.49

Client review of the structure of the site.

- **History is its own page** (slug `history`), in the menu directly below About (not a submenu, not a section of
  About). Layout of Support Us: page title, description (the page's text), then one dropdown row per year. It is edited on
  the page itself: Pages → History → box "History (chronology)", **Add Year** each year (the chronology 1988 – 2026 from the
  supplied documents is built in; what was saved on About in 0.9.37 – 0.9.48 is used until the History page has its own).
  The History pages (English and Indonesian) are created by themselves after the update; the menu shows "History" in both
  languages.
- **Journal = writings and essays to read on the web.** The Journal list, Home and "featured" show Essay and Curatorial
  Essay only. Entries of the category **News** are no longer listed there (they are not deleted).
- **Open Archive = a database of what can be read or downloaded freely**, no longer an A–Z list of titles. It is made by
  itself from the **booklets** of the entries (the "Booklet (PDF)" or booklet link of Exhibition, Residency, Public
  Program, Open Studio → Download) and the **News** posts (→ Read). Filters: All / Booklet / News and Year, applied at
  once; newest first; works without JavaScript through the links. In Indonesian, entries that have no Indonesian version
  yet are included.
- Docs updated (MAINTENANCE, ARCHITECTURE, DESIGN-NOTES); the A–Z code and styles were removed.

## 0.9.48

- **Residency editor:** the same information fields as Exhibition — **Opening**, **Public program info** and **Venue and
  hours** — shown on the entry's page like an exhibition's (and copied into the other-language version).
- **Home:** more space between the sections (Residency, Public Program, Journal …).
- **Public Program / Open Studio in Indonesian: scrolling stuttered and many thumbnails were missing.**
  - What the second-language list works out (which entries are shown once) is remembered for an hour and renewed when an
    entry is saved, trashed or deleted, instead of being worked out at every page and every scroll step.
  - The next page is requested earlier (1400 px before the end of the list), so scrolling does not wait.
  - An entry without a picture finds its twin in the other language also when only the **start date** agrees (the two
    versions often have different end dates), provided the titles share a word, so a different event on the same day is
    never taken for it.

## 0.9.47

- **Journal rows showed "Bahasa Indonesia" / "English" as the category.** Polylang's own "language" taxonomy was taken for a
  category. Only the site's own categories are shown now (Journal category: News / Essay / Curatorial Essay, taken from
  the old site's categories by the Converter; Solo/Group; Regular/Research; Project).
- **New bilingual QA suite** (`tests/flows-bilingual.php`, part of `bash tests/run.sh`): every kind of page (home, the six
  lists, entry, Open Call, Journal, About, Open Archive, Support Us, Contact Us, search, 404) is rendered in English **and**
  Indonesian with linked entries and pages, and checked for: valid markup, PHP messages in the text, interface text left in
  the other language, language names used as categories, section links that stay in the other language, the language
  switch leading to the real translation (entries and fixed pages), the overlay menu opening each language's own pages,
  Support Us rows per language, pictures on Indonesian cards (from the English twin), and "messy" imported entries
  (no dates, broken picture numbers, odd stored values, empty titles). The pages of both languages also go through the
  browser sweep (14 screen sizes).
- Bugs that suite found and that are fixed: an entry whose stored gallery/documentation was not a list (stray data from an
  import) stopped the whole page with a PHP error; an entry with an empty title produced a link without any text (now
  "Untitled" / "Tanpa judul").

## 0.9.46

- **Editing box (Details) of an entry came up empty** (Residency). If one stored value cannot be shown (unexpected data in a
  field), only that field now says "This field could not be shown: …" with the reason, and the other fields and boxes
  still appear, instead of the whole Meta Boxes area staying blank.

## 0.9.45

- **Admin list of an entry type (for example Public Program) did not open (Cloudflare 524).** Since 0.9.41 the site looked
  for a stand-in picture for every row of the list. The admin screens now show what is stored, and on the public site
  the result of that search is remembered for an hour (also "none"), so a list of entries does not repeat it at every
  visit. "Set missing featured images" still stores the pictures for good.

## 0.9.44

- **Indonesian lists (Public Program, Open Studio …) showed only the entries that already have an Indonesian version.**
  The second language's lists now also show the **default language's entries that have no version there yet** (their
  English title and text), so the list is as complete as the English one; an entry that does have an Indonesian version
  (linked as its translation, or the same event with the same dates) is shown once, in Indonesian. Paging / scrolling keeps
  working. This uses Polylang's filter `pll_filter_query_excluded_query` (Polylang 2.x and later); if an older Polylang
  does not have it the lists stay as before.

## 0.9.43

- **"Set missing featured images" ended with Cloudflare "A timeout occurred (524)".** It handled every entry in one request.
  It now works in **steps of about 8 seconds on the page itself** (progress shown, continues by itself, retries after a
  server error, Stop button), like the Media tool; progress is saved, so pressing the button again continues. Entries for
  which no picture exists are counted ("have no picture to use") and skipped, so the run ends.

## 0.9.42

- **Indonesian thumbnails: the real cause.** The entries did have a featured image, but it pointed to a picture number that
  is not in this site's Media Library (the editor says "Could not retrieve the featured image data"), so the site used
  it, found nothing and showed an empty card, and "Set missing featured images" counted only 7 entries. A featured image
  that points to nothing now counts as **none**: the site shows the other language's / the twin's / the gallery's / the
  text's picture instead, and the button counts and **replaces** those entries too (or removes the broken number when there
  is nothing to put in its place).

## 0.9.41

- **Indonesian thumbnails still empty** (Public Program, Exhibition …). The Indonesian and English entries imported from the
  old site are separate posts, not linked as translations, so the Indonesian one could not borrow the English picture.
  An entry without a picture now looks for its **twin in the other language: same type, same dates and the most alike
  title**, and shows that one's featured image (else its gallery / documentation / text). "Set missing featured images" in
  Settings → Cemeti → Maintenance stores it for every entry in one click. Entries with no twin and no picture of their own
  stay empty: add the picture in the entry's Featured image box.

## 0.9.40

- **Indonesian entries without thumbnails.** After "Convert" the pictures live in Gallery / Documentation (no longer in the
  text), so an entry without a featured image found nothing to show. It now shows, in this order: the other language's
  featured image, the first picture of its Gallery, of its Documentation, of its text, of the original text kept by the
  converter, or an attached image. **Settings → Cemeti → Maintenance → "Set missing featured images"** stores that picture
  as the real featured image of every entry that has none, in one click (nothing already set is changed).
- **Support Us / Dukung Kami.** Since 0.9.38 a sub-page whose title begins "Dukungan …" was taken for the Support Us page
  itself (and, being the longest, chosen by the menu), so the menu opened "Dukungan Program dan Donasi" instead of the
  page with the dropdown rows. Sub-pages are never taken for a fixed page now. A sub-page's own address (for example
  /dukung-kami/patron-of-cemeti/) leads to the Support Us page and **opens that row** (#row).

## 0.9.39

- **Residency: choosing Regular or Research filters the list below at once** (no page reload, the address changes so it can
  be shared and the Back button works). The chosen category is shown in red with an underline; pressing it again shows
  everything. Without JavaScript the links still lead to `?type=regular` / `?type=research`. Lists that were swapped keep
  loading the next page while scrolling.

## 0.9.38

- **History did not show on the Indonesian About page.** An Indonesian page is only given the About design (and so its
  History section and the "History (chronology)" box) when the theme recognises it. Titles that merely begin like the page
  are now recognised too: "Tentang Kami", "Tentang Rumah Seni Cemeti" (About), "Hubungi …", "Kontak …" (Contact Us),
  "Dukung …" (Support Us), "Arsip Terbuka …" (Open Archive). If a page is still not recognised, choose it in the side box
  "Cemeti page design" of that page, or in Settings → Cemeti → "Fixed pages in both languages".

## 0.9.37

- **About → History (chronology).** New section at the bottom of the About page: one dropdown row per year, the same
  layout as Support Us (year, then the exhibitions / events of that year with number, date, title, artists, programme).
  The chronology 1988 – September 2026 from the two documents supplied (EN and ID) is built in, so it shows at once.
- **Editable every year:** on Pages → About (and on its Indonesian copy) the box **"History (chronology)"** lists one block
  per year; press **Add Year** for the new year, write the exhibitions / events (an empty line between them; first line =
  number and date) and press Update. Years are shown oldest first whatever the order in the box. From the first Update the
  page keeps its own history; emptying the box brings the built-in one back.

## 0.9.36

- Language switch (EN/ID) on an **entry** (exhibition, residency, journal …) sent the reader back to a home page or to the
  version they came from. It now opens the entry's **translation** when one is linked, otherwise **the same section in the
  other language** (for example the Indonesian Exhibition list). Create the translation with the box "Cemeti: translation"
  in the entry's editor.

## 0.9.35

- **The Indonesian About / Open Archive / Support Us / Contact Us still opened the English pages** (the "Where the overlay
  menu links go" list showed the English address for ID). On this server Polylang's language filter is not applied when the
  theme looks a page up by its slug, so asking for the Indonesian page returned the English one, and the dropdown (which
  checks the language) then showed "— none —". The theme now asks for the pages of every language and **checks each
  page's own language itself**; the same slug in two languages gives each its own page.

## 0.9.34

- **Fixed pages table: the ID dropdown went back to "— none —" after Save and the overlay menu stayed on English.** The
  chosen Indonesian page was linked as a translation but kept no language (or the wrong one), so it was invisible to the
  ID dropdown, the menu and the language switch. Saving now **gives each chosen page the language of its column**, and the
  dropdowns also offer pages that have no language yet (never the other language's pages).

## 0.9.33

- Overlay menu in the Indonesian version led to English pages. Menu addresses are now always built from the **language's own
  home address** (never from the request), and the fixed pages fall back to it only when no page of that language exists.
  **Settings → Cemeti → "Where the overlay menu links go (per language)"** lists, for EN and ID, the address every menu
  entry opens, so a wrong one can be seen at once (an ID entry showing an English page means that ID page is not found:
  choose it in the "Fixed pages in both languages" table above and press Save and link).

## 0.9.32

- **"Convert to blocks" (WordPress) does not move pictures into Documentation / Gallery.** It only changes the text's
  format. New box **"Cemeti: split the old text"** (right column of Exhibition, Residency, Public Program, Open Studio):
  **Split the text into the fields** does for this one entry what the Converter does for all: pictures under "Foto Karya /
  Galeri" → Gallery, "Dokumentasi" → Documentation, "Biografi" → Biographies, PDF link → Booklet; old Daftar Isi / language
  links / "Terbitan Terkait" are dropped. Fields that already have content are never overwritten. **Undo** brings the exact
  original text back and removes only what the split wrote. Pictures not in the Media Library stay in the text (run
  Tools → Cemeti Converter → "0. Media" first). Save the entry before pressing it.

## 0.9.31

- **"2. Fix posts" stopped and showed the root site's "Halaman tidak dapat ditemukan" (404) page.** The tool moved from one
  page load to the next with automatic redirects; when the server (or its firewall) answered one of them with an error page,
  the whole chain was cut. Now the buttons in Tools → Cemeti Converter → "0. Media" run **inside the page** (admin-ajax,
  `assets/js/admin-media.js`): the page stays open and shows progress; if the server answers with an error (404, 5xx,
  a firewall page) it **waits and tries again** (up to 6 times, longer each time) and only then stops with a clear message.
  Progress is saved on the server after every step, so pressing the same button again continues where it stopped.
  A **Stop** button was added. Without JavaScript the old links still work.

## 0.9.30

- Indonesian pages made in Pages did not show / were not linked to the English ones. Two safety nets:
  - **Settings → Cemeti → "Fixed pages in both languages"**: a table with, for About, Open Archive, Support Us and Contact Us,
    a dropdown of the pages of each language. Choose the page and press **Save and link**: they are linked as translations
    (pencil icon, language switch), remembered as that Cemeti page and the Indonesian menu opens the page you chose. A ✔ /
    ✘ shows what is linked. The text of the pages is never changed.
  - The **language switch** (EN/ID) on a fixed page now leads to the other language's page even when they are not linked
    (it led to the home page).

## 0.9.29

- Pages (About, Open Archive, Support Us, Contact Us) after an update or install: the theme now recognises the Indonesian
  pages that already exist ("Tentang", "Kontak Kami", "Dukung Kami", "tentang-2" …, by chosen design, slug or title) instead
  of making a second, empty one that had to be filled again, and it **links them as translations** of the English pages
  (the pencil icon in the Pages list comes back). With several candidates the one that has text wins. The text of
  existing pages is never touched, and a pair the team linked by hand is never rearranged.
  Empty duplicates made by version 0.9.13–0.9.28 can simply be moved to the trash (the page with text is the one linked).

## 0.9.28

- Cross-check with Figma (file node geometry at 1440 px, plus the text styles of the Journal article and the Related
  card). Measured and aligned, with 16 new geometry tests (`figma:` in browser-qa):
  - Page head: the title and Back sit 8 px higher (title ink y 180, Back y 197) and the first row / card / picture of every
    page starts at y 265 (it was 12 px lower): Exhibition, Residency, About, Support Us, Open Call, Journal, Open Archive, Home.
  - Home: sections 508 px apart, button 272 x 31 (all green buttons are 31 high now).
  - Header: Menu, EN/ID and search sit 10 px lower, baseline at y 122 like the design.
  - Residency page: two 524 px columns at x 176 and 740, titles at x 357 / 921, text 79 px under the title, cards 63 px under the text.
  - About: the text block is 1064 px wide (x 188). All detail pages use the 1064 px column (was 1067).
  - Support Us rows 82 px apart; Journal list rows: date, title and category spaced as in the design; Open Archive letters
    and entries (30 px apart, blocks of three 144 px apart).
  - Detail pages: the contents bar is the 107 px band under the header and the poster starts at y 247; Public Program and
    Open Studio posters are a centred 524 x 655 box; gallery and documentation rows 12 px apart; titles 85 px above the photos.
  - Journal article: picture at y 247, caption 605 wide and 121 px in (18 px), title 64, author 40 with the design's spacing,
    no date line under the author (it is not in the design), text block 1064, pictures in the text 906 wide, wide ones 1067.
  - Related publications: a row of 324 px cards 15 px apart that scrolls sideways and fades out at the right edge, like the
    design's slider (it was a grid of four).

## 0.9.27

- Final QA before hand-over: every page fixture at 14 screen sizes (320 to 2560 px, landscape phones) and touch tests on
  iPhone, Pixel, iPad (both orientations), laptop and 2560 px profiles: no overflow, no clipped text, no tap target under
  24 px, no missing alt, no script error, menu / search / contents links / accordion work. The sweep is now a permanent
  script (`tests/browser/sweep.js`, run by `tests/run.sh`).
- Tablets (768 to 1023 px) show archive cards three per row instead of two very large ones.

## 0.9.26

- The "Contact details" box was empty/missing on the Indonesian page "Kontak Kami": the theme only knew the page by its
  exact slug. A copy made by Polylang ("kontak-kami-2", "contact-us-id") or a page simply titled "Kontak Kami" is now
  recognised as Contact Us (also About, Open Archive, Support Us), so the box and the design apply. If a page still is not
  recognised, choose it in the side box "Cemeti page design" and save.

## 0.9.25

- Contact Us is edited on the page itself, like About: **Pages → Contact Us** (and its Indonesian copy). The editor is the
  text, the Featured image (or a picture that opens the text) is the page picture at the same size as About, and a new
  **"Contact details"** box holds the address, opening hours, email, phone / WhatsApp (call link) and the Google Maps
  link per language. A detail left empty still uses Settings → Cemeti. Bad emails and non-Google-Maps links are dropped.

## 0.9.24

- Full visual re-check (69 page fixtures x 13 screen sizes from 320 to 2560 px plus landscape phone, with screenshots of
  desktop and iPhone): no overflow, no script errors. One finding fixed: on archive cards a landscape picture, now shown
  whole, sat in the middle and its bottom edge went under the title panel; it now sits at the top of the card.

## 0.9.23

- Thumbnails (Home posters, archive cards, related, Open Call poster, gallery grids) show the whole picture instead of a
  cropped one: the box keeps its size (308 x 386 cards, 4:5 posters), the picture fits inside it and the free space is
  the panel colour.

## 0.9.22

- iPhone (Safari): a long web address, a wide table or an embed in the text of an Open Call (or any entry) stretched
  the whole page, so Safari zoomed out and the poster looked huge and cropped. All grids now use `minmax(0, 1fr)`, text
  from the editor wraps (links break anywhere, tables scroll inside themselves, embeds shrink) and the page clips
  sideways overflow instead of letting Safari zoom out.
- "Back / Kembali" broke letter by letter ("Kemba li") next to the contents bar on phones: it now stays on one line and
  the contents links wrap instead.
- Indonesian entries with no gallery, documentation or booklet of their own show the other language's (the pictures are
  the same), so their contents bar and sections are no longer missing.
- Tests: a fixture with real-world long content at 320/390 px, the Back button, the translation fallback.

## 0.9.21

- Media repair refused pictures whose file name contains two dots ("museum-of-the-ordinary-things-cemeti-2017..jpg"):
  the safety check against `../` looked for ".." anywhere. It now refuses only a folder called "..".
  Run "2. Fix posts" again after updating (it re-checks the posts that still have missing pictures).

## 0.9.20

- "0. Media → Fix posts" ended in a Cloudflare 524 timeout: one post with 20+ pictures could keep the page waiting for
  minutes. Now every download checks a 12 second clock (the limit is per step, not per post): an unfinished post is left
  unmarked and continues in the next step, a download waits at most 10 s, and after three connection timeouts in a row
  the step stops with "this server could not connect to the old site" instead of repeating the same wait forever.

## 0.9.19

- Revert + Convert again is proven safe: new test `flows-reconvert` (convert, convert again, revert, convert, three more
  rounds) checks that nothing is repeated, a revert gives every post back exactly as imported and the second conversion
  equals the first. The saved original text is never overwritten by an already shortened text.

## 0.9.18

- "0. Media → Fix posts" did nothing on a site where the pictures' addresses had been changed to the new site's (search
  and replace or importer): such a picture was counted as missing without a word. Now an address of this site is
  treated like the old site's for files that do not exist here (they are downloaded from the old site), other websites
  are skipped **with the reason in the report**, and pressing "2. Fix posts" again re-checks the posts that still have
  missing pictures (no need for "Start over").

## 0.9.17

- Polylang's "+" next to a language only reloaded the page for the Indonesian version. New box **"Cemeti: translation"**
  (right column of every entry and page): **"Create ID version (copy)"** makes the other-language draft itself, with the
  title, text, picture, dates, artists, galleries, biographies, booklet and the terms of the new language, links it as the
  translation and opens it; an existing translation is linked instead. Polylang's own "+" now also copies the Cemeti
  fields. The related-publications list is not copied (those are other entries).

## 0.9.17 (addition)

- Residency page in Indonesian showed only "Reguler": "Research" was missing because the page listed only the terms of
  the current language. It now lists Regular and Research every time (Indonesian term when there is one, else the other
  language's term with the Indonesian description), "Reguler"/"Regular" and "Research"/"Riset" counting as the same
  term, so a translation that is not made yet never hides one.

## 0.9.16

- Residency: "Research" (and "Regular") could not be translated: with Polylang the Indonesian entry's editor offered no
  term at all, because the terms existed only in the first language. Now an administrator opening the admin after the
  update gets the second-language copy of every default term (Regular / Research, Solo / Group, News / Essay /
  Curatorial Essay, Advisory Board / Team), linked as translations, with the Indonesian description for Regular and
  Research. An entry takes the term of its own language when saved or converted. The Residency page shows the
  Indonesian description (unless the team edited the text) and `/id/residency/?type=research` finds entries through
  every translation of the term. Tip: on existing Indonesian residencies open the entry once and tick the type.

## 0.9.15

- Import / converter, checked against the full real export (561 posts, 10,087 pictures):
  - **Pictures are found by their file path**, not by the old address or the old `wp-image-123` number (the importer
    changes both: only 2,708 of the 10,087 pictures matched before, 6,406 do now). An old number is only trusted when it
    is the same file, so a card can no longer show a wrong picture. Cards, About and the converter all use this.
  - **Tools → Cemeti Converter → "0. Media"** repairs a failed import: downloads missing files from the old site,
    makes the sizes, creates Media Library items for the 2,388 pictures that were never in the library, and gives posts
    without a valid featured image the first picture of their text. Time-boxed steps that continue by themselves, a
    report with the last problems, and only the old site's host is ever downloaded from.
  - **Split of the old text**: 245 posts are recognised instead of 167 (gallery 61 instead of 41, documentation 192
    instead of 118, biographies 167 instead of 60) because headings inside block groups/columns, `h1` headings, artist
    names written as headings, "Fota Karya", curly apostrophes and "Daftar Isi <name>" are now understood.
  - **Nothing is lost silently**: pictures that are not in the library, descriptions of the works, videos/embeds in a
    picture section stay in the text (they used to disappear from the page and live only in the revert copy).
  - New test suite `flows-media`, more `flows-restructure` cases, 6 more mutation checks.

## 0.9.14

- Deep functional QA on phones, tablets (portrait and landscape), laptop, 1920 and 2560 px with touch emulation
  (menu, search, language, contents links, scroll-spy, back to contents, accordion, share, hit-testing every link for
  things covering it). Found and fixed:
  - Menu links of the fixed pages (About, Open Archive, Support Us, Contact Us) were built from the English slug, so
    on the Indonesian site "About" led to /id/about/ (404) when the page is called "tentang" (also "kontak" ...).
    They now point to the real page of the current language.
  - Contents bar: the link you click is now the one marked, also on tall screens where the next section is already
    on show, and the last section is marked at the end of the page.
  - Landscape phones: the menu and search open in view even if the page was scrolled; scrolling the menu no longer
    scrolls the page behind it.

## 0.9.13

- The Indonesian Open Archive, Support Us and Contact Us pages (with the four Support Us rows) did not exist on a site
  where the theme was updated instead of re-activated: pages were only created on activation. Now an administrator
  opening any admin screen after an update (or after a language is added) creates every missing page in every
  language and links them as translations. It runs once per version; the "Create them" button still works any time.

## 0.9.12

- QA of every Indonesian page: the Home buttons "Previous Exhibition / Residency / Public Program / Open Studio /
  Journal" were English in Indonesian ("Exhibition Sebelumnya" now) and the pagination label was English
  ("Navigasi halaman" now). Everything else was checked: all pages have EN/ID parity in structure and size (browser
  test), no overflow at 320-1920 px in Indonesian, and the only English left is by design (program and menu names,
  "Application Submission"). Tests cover both strings.

## 0.9.11

- About (Indonesian): an Advisory Board member whose Indonesian copy had no Group term was listed under Team. A
  person's group is now taken from their own Group, else from the other-language version, else from the Title / role
  text ("Advisory Board", "Dewan Penasihat"). An explicit Team group is never overridden. Tip: set Group on both copies.

## 0.9.10

- Journal detail follows the design: the picture is 848 px wide (centred, same ratio as About) with its caption under
  it, indented (the caption is the image's Caption in the Media Library). New Journal fields: Documentation (photos)
  and its caption, shown after the article under the heading Documentation / Dokumentasi.

## 0.9.9

- QA round 2: the language switch fades the selected language (it was the other one); Home residency excerpt is
  regular weight and Home titles have space above the date; Advisory Board shows a larger photo with the name
  centred under it; People list in the admin has Title / role and Order columns, sorted by Order (lower = higher
  on the About page; Team divisions follow the order of their first member); archive thumbnails are now cut 4:5
  (616 x 772) like the card instead of square (regenerate thumbnails to apply to old uploads); on screens wider
  than 1600 px the archive cards grow instead of staying at the 1440 px size.

## 0.9.8

- Pre-release QA sweep (every page fixture at 11 widths from 320 to 1920 px plus landscape phones): a very long search
  term no longer widens the page; application form fields fit 320 px screens; Back and contents links have 24 px+
  tap targets (larger on touch screens) without changing the look; on landscape phones the header, contents bar and
  section titles are not pinned (they used to cover 40% of the screen). New browser tests cover each of these.

## 0.9.7

- Responsive pass (390 px phone, 820 px tablet): the reading column is the full width on phones (no 8% indent);
  Home entries are one column on phones, Residency included (it stayed two cramped columns), with more space between
  sections; archive cards are two per row from 352 px (one giant card per screen before); photos stay two per row;
  the contents bar has no bars that start a line; the menu address has air above it. The browser tests check these.

## 0.9.6

- Open Call page shows one call, in the design layout: the newest call that is ticked Active (when none is active, the
  newest call with its closed notice).
- New "Active" tick on every Open Call (Open Call → edit → Details → Status): untick to close it at once, tick to
  open it (even past its deadline: the team decides). Never touched = open until the deadline, as before. The Open
  Call list in the admin has an Active column and a one-click "Close call" / "Reopen call". The application form
  refuses a call that is switched off.

## 0.9.5

- Residency page: Regular and Research sit side by side inside the reading column (two equal columns), each title
  centred over its text at 40 px (the page title is 64 px), the text 24 px regular weight (was bold).

## 0.9.4

- English and Indonesian are tested to be the same design on every main page (home, all archives and filters, all
  detail pages, About / Open Archive / Support Us / Contact Us with an Indonesian copy under its own slug, search, 404):
  same structure (PHP) and same sizes, positions, fonts and colours (browser), no overflow of the longer Indonesian
  text at 360 px.
- Fixed by that test: the Indonesian Support Us page showed no rows (its rows are child pages of the English page); it now
  shows the rows, each through its Indonesian copy when it has one.

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
