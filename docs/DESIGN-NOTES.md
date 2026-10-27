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
| 30 | Open Archive A–Z, two columns A–M / N–Z | `page-open-archive.php` | M3 |

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

## 3. Typography decision (needs client sign-off)

- **Script headings:** the PDF uses a bold, slanted brush script I could not identify (the PDF embeds outlines only). **Kaushan Script** (SIL OFL 1.1, self-hosted WOFF2, `font-display: swap`) is a stand-in. It is lighter and narrower than the PDF; the client should supply the real font file or approve a substitute.
- **Body:** a grotesk resembling Neue Haas / Helvetica Now. **Hanken Grotesk** variable (SIL OFL 1.1) is used. Same caveat.
- Licences: `assets/fonts/LICENSE-*.txt`.

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
- **Logo.** `assets/img/logo-placeholder.png` is a crop of the raster in the PDF. Replace with the vector/SVG logo from the client. Cemeti logo also appears as a cream line-drawing watermark on What's on (p.16); not yet implemented because it needs the vector asset.
- **Search wording.** Button reads "Cari" in Indonesian and "Search" in English; the PDF only shows the English wording.
- **Mobile layouts** are not in the PDF (desktop frames, plus narrow frames only for pages 21, 23, 26, 27, 29, 30). Breakpoints 480 / 768 / 1024 px are our decision; the narrow detail frames guided the single-column behaviour.
- **Placeholder data.** Footer address, hours and e-mail defaults come from the PDF and live only as fallbacks in `cemeti_option_defaults()`; Site Settings override them. Support Us text in the PDF is Lorem ipsum.
