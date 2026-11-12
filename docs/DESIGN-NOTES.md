# Design notes — Cemeti

Source of truth: `design/cemeti-design.pdf` (CEMETI WEB 3.0, 30 pages, exported from Figma, 1440 px wide frames).
All 30 pages were viewed. Values below were measured from rasterised pages (pdftoppm) unless marked *assumed*.

## 1. Page map

| PDF page | Content | Template / part | Milestone |
|---|---|---|---|
| 1 | Script heading, dark vs blue + underline (hover/active) | `.cemeti-heading` | M1 |
| 2 | ID/EN switcher states (active dark, inactive grey, hover underline) | header `.cemeti-lang` | M1 |
| 3 | Search bar, yellow strip, input + SEARCH + X | `template-parts/search-overlay.php` | M1 |
| 4 | Menu toggle ▼/▲ and black overlay menu | `template-parts/menu-overlay.php` | M1 |
| 5, 7 | Heading link states (yellow, hover underline) | `.cemeti-heading` | M3 |
| 6 | "← Back" normal / hover (salmon underline) | `.cemeti-back` | M1 (CSS), M3 (markup) |
| 8 | Journal row title normal / hover (salmon) | `.cemeti-row` | M1 (CSS) |
| 9 | Green "Previous …" button / hover salmon | `.cemeti-btn` | M1 (CSS) |
| 10 | "Reguler" / "Research" sub-heading with hover underline | Residency | M3 |
| 11–13 | Home title + date, normal / hover (salmon underline) | `front-page.php` | M3 |
| 14 | Blue "Application Submission" button / hover yellow text | `.cemeti-btn--blue` | M1 (CSS) |
| 15 | Quoted title link state | Open Archive | M3 |
| 16 | **What's on** full-screen yellow panel, salmon script text, cream logo watermark, Close | `template-parts/whats-on.php` | M1 |
| 17 | Home | `front-page.php` | M3 |
| 18 | Exhibition archive (4-col grid) | `archive-exhibition.php` + card part | M3 |
| 19 | Open Call detail | `single-open_call.php` | M3 |
| 20 | Residency (Reguler/Research + grid) | `archive-residency.php` | M3 |
| 21 | Exhibition detail (TOC, Galeri, Dokumentasi, Biografi, Publikasi Terkait, Share) | `single-exhibition.php` | M3 |
| 22 | Public Program archive | shared archive grid | M3 |
| 23 | Public Program detail | shared single part | M3 |
| 24 | Open Studio archive | shared archive grid | M3 |
| 25 | Support Us (mission + 4 accordion rows) | `page-support-us.php` | M3 |
| 26 | Open Studio detail | shared single part | M3 |
| 27 | Journal detail | `single-journal.php` | M3 |
| 28 | Journal list | `archive-journal.php` | M3 |
| 29 | About (image, text, Advisory Board, Team) | `page-about.php` | M3 |
| 30 | Open Archive: database of booklets and News to read or download (replaces the A–Z list, client review 0.9.49) | `page-open-archive.php`, `inc/archive.php` | M3 |

No page exists for Contact Us, Search results, 404 or Open Call application form. They will be derived from the design system and listed here as derived designs when built (M3/M4).

## 2. Measured tokens (`assets/css/tokens.css`)

| Token | Value | Where seen |
|---|---|---|
| `--c-cream` | `#FFF8EF` | page background (brief guessed `#FFF8EE`) |
| `--c-panel` | `#F2E8DB` | archive card body |
| `--c-ink` | `#4A4A4D` | body text, Home headings (brief guessed `#4A4A4A`) |
| `--c-yellow` | `#F6D141` | footer, search strip, What's on, archive headings |
| `--c-salmon` | `#F16254` | card titles, link hover, What's on text |
| `--c-blue` | `#1C75BC` | Open Call / Support Us / About headings, footer links, buttons |
| `--c-green` | `#00A14B` | "Previous …" buttons |
| `--c-black` | `#000` | overlay menu |
| `#2D56A5` | poster artwork only | not a UI token |

Layout, measured on 1440 px frames: content width ≈ 1280 px (89 %) — wider than the brief's 1100–1200 px estimate, so `--content-max: 80rem`. Card gap ≈ 16 px. Header height ≈ 141 px with the logo ≈ 200 px wide and "Menu" bottom-left, ID/EN + search bottom-right, soft shadow under the bar. Cards: square image, cream-dark panel, salmon title (max 3 lines), bold date, 2 px dark bottom border. Journal rows: 3 px dark rules, date / title / category stacked. Footer: yellow, name 28 px semibold, address and hours ≈ 14 px, social links blue semibold.

## 3. Typography (client decision)

- **Title / script headings:** **Vampiro One** (SIL OFL 1.1), self-hosted WOFF2, `font-display: swap`.
- **Sub title and body:** **Akzidenz-Grotesk Next** (Monotype, commercial), bundled as WOFF2 (Light, Regular, Italic, Medium, Medium Italic, Bold, Bold Italic) from the files the client supplied; the client holds the licence. **Hanken Grotesk** (SIL OFL 1.1) stays only as a fallback if the files fail to load.
- Licences of bundled fonts: `assets/fonts/LICENSE-*.txt`.

## 4. Contrast findings (WCAG 2.1 AA, ratios calculated, colours NOT changed)

| Pair | Ratio | AA text 4.5 / large 3 | Candidate that passes |
|---|---|---|---|
| ink on cream | 8.38 | pass | — |
| ink on panel | 7.29 | pass | — |
| black on yellow | 14.12 | pass | — |
| white on black (menu) | 21.0 | pass | — |
| blue `#1C75BC` on cream | 4.61 | pass | — |
| white on blue button | 4.86 | pass | — |
| **salmon on panel** (card titles) | 2.62 | **fail** | `#B42D20` → 5.20 |
| **salmon on cream** (hover links) | 3.01 | fails normal text | `#BB3226` → 5.54 |
| **yellow on cream** (archive headings) | 1.41 | **fail**, even for large text | `#9A7D00` → 3.75 (large text only) |
| **white on green button** | 3.39 | fails (button is script 16 px+) | `#007A39` → 5.46 |
| **ink on salmon** (button hover) | 2.78 | fail | use white-on-`#B42D20`, or keep hover non-text |
| **blue on yellow** (footer links) | 3.27 | fails normal text | `#14568C` → 5.15 |
| **salmon on yellow** (What's on) | 2.13 | **fail** | `#A82A1E` → 4.68 |
| **yellow on blue** (button hover) | 3.27 | fails normal, passes large | — |
| **grey EN on cream** (inactive language) | 3.27 | fails | `#6B6B6E` → 5.04 |

The design colours are used as drawn. The client must decide, per row, whether to accept the failure or adopt the alternative. Headings in yellow remain decorative-brand and will be flagged in the accessibility report.

## 5. Decisions and deviations

- **What's on trigger.** The PDF shows the What's on panel (p.16) but not how it opens. M1 adds a "What's on" button at the bottom of the menu overlay. *Assumed — confirm with client.* Content is fed by the `cemeti_whats_on_items` filter (filled in M2); the panel is empty until then.
- **Menu overlay.** The PDF draws a black dropdown beneath the header, not a full-screen takeover; the dropdown is implemented as drawn. The ▼ icon flips to ▲ when open.
- **Language switcher.** Without Polylang only "ID" renders, since an EN link would have no target.
- **Logo.** `assets/img/logo.webp` is the client-supplied raster logo (2000 × 853). Replace with the vector/SVG logo when available. Cemeti logo also appears as a cream line-drawing watermark on What's on (p.16); not yet implemented because it needs the vector asset.
- **Search wording.** Button reads "Cari" in Indonesian and "Search" in English; the PDF only shows the English wording.
- **Mobile layouts** are not in the PDF (desktop frames, plus narrow frames only for pages 21, 23, 26, 27, 29, 30). Breakpoints 480 / 768 / 1024 px are our decision; the narrow detail frames guided the single-column behaviour.
- **Placeholder data.** Footer address, hours and e-mail defaults come from the PDF and live only as fallbacks in `cemeti_option_defaults()`; Site Settings override them. Support Us text in the PDF is Lorem ipsum.

## 6. Derived designs (M3)

These have no page in the PDF and were derived from the design system. Confirm with the client:

- **Contact Us**: blue script heading, page text, then name, address, hours, e-mail and route link from Site Settings.
- **Search results**: Journal-style ruled rows (date, title, type or category).
- **404**: yellow script heading, one sentence, a "Go to home" button.
- **Open Call list**: Journal-style rows, blue heading.
- **People grid** (About): the PDF only shows the "ADVISORY BOARD" and "TEAM" headings; a 4-column photo, name, role grid is assumed.
- **Residency detail** and **Open Archive entry order**: no page in the PDF; follow the Exhibition detail pattern and alphabetical order.


## Figma values: Exhibition article template (read from the file, 1440 px frame)

Source: Figma file "CEMETI WEB 3.0", page Components, frame `EXHIBITION ARTICLE TEMPLATE` (node 2084:307).
The theme reaches these values at a 1440 px viewport; below that the sizes shrink with `clamp()` (tokens `--fs-single*`).

| Element | Figma | Theme |
|---|---|---|
| Title | Akzidenz Medium 64 | `--fs-single-title` |
| Dates, artists | Medium 40 | `--fs-single-lead` |
| Labels (Pembukaan:) | Medium 24 / 34 | `strong` = 500 |
| Text | Regular 24 / 34, ink #4a4a4d | `--fs-single` |
| Section headings | Vampiro One 64, blue #1c75bc | `--fs-single-script` |
| Contents bar, captions, "Kembali ke Daftar Isi", share | Regular 18; links Jeruk Bali #f16154, underlined; bars 20 px high, 12 px each side | `--fs-single-small` |
| Related card title / label | Medium 20 / Regular 18 salmon; card padding 20; image 331:260 | `--fs-single-card` |
| Reading column | x 188, width 1064 (columns 2-11 of 12; container x 80, width 1280) | `--col: 83.333%` |
| Poster | 524 x 655, x 404 (216 px into the column) | 49.25% wide, 20.3% in |
| Gallery / documentation | 4 photos 525 x 351 (3:2), gap 16 / 13 | 2-column grid |
| Biography | photo 308 square at x 188, text at x 512 (gap 16), 80 px between artists | grid 28.95% / 1fr |

Jeruk Bali is #f16154 in Figma (the PDF showed #f16254); the theme now uses the Figma value.
Not copied: the related list is a horizontal carousel with fades in Figma (6 cards, 324 px wide); the theme shows up to 4 in a grid.
Other frames in the file (Open Studio, Public Program, Journal templates, cards, Home, Open Call) have not been measured yet.


## Figma values: chrome, cards, Home, Open Call (1440 px frames, read from the file)

Calibration used for text boxes: Figma trims text to its cap height (0.72 of the font size), so a 34 px tall name is 48 px type, 45 px = 64 px, 17 px = 24 px, 11 px = 16 px, 13 px = 18 px.

| Part | Figma | Theme |
|---|---|---|
| Header | 140 px high, shadow 0 4 2 rgba(0,0,0,.2); logo 200 px wide, 36 px from the top, centred; Menu 24 Medium at x=80; ID/EN 24 Medium (EN #9a9a9a); search icon 17 px at right 80 | `--header-h`, layout.css |
| Page heading | Vampiro One 64, centred, 40 px below the header, 40 px above the first row; "Back" 18 Regular at the right edge | `--fs-script-xl`, `.cemeti-page-head` |
| Archive card | 308 x 386 (4:5), 16 px / 20 px gaps, four per row; title block (panel #f2e8db, padding 12, gap 24) over the bottom of the poster; title 18 Medium salmon (max 3 lines), date 16 Medium | `.cemeti-card` |
| Footer | 254 px yellow band; text block 211 px wide at x=501 (name 22 Medium, address/hours/e-mail 12); social links 97 px wide, 15 px to the right, 22 Medium blue, 24 px apart | `.cemeti-footer` |
| Buttons | 272 x 31, padding 10, Vampiro One 16, cream text; green (Previous ...), blue (Application Submission) | `.cemeti-btn` |
| Home highlight | poster 272 x 340, text 52 px to its right: title and names 48 Medium, date 40 Medium; button 12 px under the poster; 40 px from a button to the next heading | `.cemeti-home__*` |
| Home residency | picture 416 x 333, text 16 px to its right | |
| Journal row | date 24, title 40 Medium, category 24; rule between rows spanning the full width | `.cemeti-row` |
| Open Call | picture 416 x 520, text 848 wide 16 px to its right: title 48 Medium, text 24 Medium, blue button 272 wide level with the picture's bottom | `.cemeti-opencall` |
| Open Archive (0.9.49) | filters (All / Booklet / News, year) then ruled rows: year, title 40 Medium, kind, Download / Read button | `.cemeti-archive` |
| Support rows | label 48 uppercase, 82 px pitch, full-width rules | `.cemeti-accordion` |

Cream text on the green button is 3.21:1 (below AA); it is the design colour pair and is listed as known.

Not read from Figma yet (the Figma plan's tool-call limit was reached): text sizes of the Journal page and article, About, Support, Open Archive, the Public Program and Open Studio article templates, the menu overlay and the search strip. Their geometry (positions, widths) is in the file and was applied; their type sizes follow the calibration above.


## Figma cross-check (v0.9.28)

Measured from the node geometry of the Figma file (every frame is 1440 wide; header 140, footer 254):
page title ink y 180 and Back y 197; first row / card / picture y 265; detail poster y 247 (524 x 655); reading column x 188,
1064 wide; galleries 524 x 351 in two columns 16 px apart, rows 12 px apart; biography photo 308, text 740 at x 512;
Related cards 324 wide, 15 px apart; Journal article picture 848 x 602 at y 247, in-text pictures 906 (wide 1067);
Support rows 82 px (the History page uses the same rows); Residency columns 524 at x 176 / 740.
`tests/browser/browser-qa.js` keeps these as `figma:` checks.
Not read from Figma (no access to those nodes at the time): the icons of the Share block ("Share this:" label with an image of
icons), the overlay menu and search frames, interactions of the prototype.
