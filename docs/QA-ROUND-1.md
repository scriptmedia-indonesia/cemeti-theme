# QA round 1 (Project_Timeline_Website_Cemeti.xlsx) — status per test case

Version 0.8.0. "Fixed" means changed in the theme and covered by a test; "Check" means it depends on content or
settings on the site and needs your look; "Info" is not a defect.

| Test | Finding | Status |
|---|---|---|
| CMT_001 | Main language English | Fixed: English is the default (UI strings, settings, created pages). Set English as Polylang's default language. |
| CMT_002 | Language switch does not change content (Back → Kembali) | Fixed. Cause: the Cemeti content types were registered with Polylang under a wrong value, so Polylang ignored them: no language on those archives, strings did not follow. Also language slugs like `en-us`, `in`, `id_ID` are now understood. Needs Polylang with English and Indonesian. |
| Admin | Some menus have no language selector | Fixed by the same cause (Exhibition, Residency, Public Program, Open Studio, Open Call, Journal, People and their taxonomies now have it). Settings → Cemeti and the Dashboard warn if Polylang or a type is missing. |
| CMT_003 | "Plan your Route >>" under the address, address links to Google Maps | Fixed: route link and linked address in the menu; default route link set (editable in Settings → Cemeti). |
| CMT_004, 016-, 026-, 030, 044, 056, 066, 081 | Featured image not shown | Fixed for moved entries: when there is no featured image, the first picture of the text / attachment is used. Entries with no picture at all stay without one. |
| CMT_005 | Section title hover colour | Fixed: #1C75BC. |
| CMT_006 and every "page title" row | Titles: parallax / stay in place | Fixed: titles stay under the header while their section scrolls (desktop; off with reduced-motion). Please judge the feel against the prototype. |
| CMT_007 | Journal category size = date | Fixed (24 / 24). |
| CMT_008 | "Previous Journal" too big | Fixed: all "Previous …" buttons are 272 px wide. |
| CMT_009, 010 | Address link; social links missing | Fixed: defaults for address link and Instagram / Youtube / Facebook (from your note), editable in Settings → Cemeti. |
| CMT_011 | About title | Fixed: yellow #F6D141, same size as other titles. |
| CMT_012 | Where to update About | Info: Pages → About → Featured image (picture) and editor (text); a help box on the page says so. |
| CMT_013 | About text 2 columns | Fixed: one column (column blocks are stacked). |
| CMT_014, 015 | Advisory Board 3 columns with photo, title, quote; Team kept | Fixed: 3 columns, square photo left, name / title / quote right. New "Short quote" field on People. |
| Exhibition | Solo / Group not separable | Fixed: Solo/Group is set from the title ("Pameran Tunggal/Kelompok") or the number of artists when an exhibition is saved or converted; button "Set Solo/Group on exhibitions without a type" in Settings → Cemeti for the existing ones. Choices made by hand are never changed. |
| Lists | Pagination by scroll | Fixed: the next page loads while scrolling (Older/Newer links remain without JavaScript). |
| Detail pages | Contents bar at the top, anchors | Fixed: the bar is sticky under the header; links jump to the section, the section being read is marked. |
| Detail pages | Language links repeated | Fixed: the old "English | Indonesia" links inside entries are hidden; the header switch is the only one. |
| Detail pages | "Back to contents" opens another page, too big | Fixed: scrolls up on the same page, 18 px, colour from the design. |
| Detail pages | Section titles, 2x2 photos | Check: titles are Vampiro One; there is no decorative line in this theme. If you still see lines or a different font you are looking at another theme or a cached page. |
| Detail pages | Share buttons | Fixed: icon and app name (WhatsApp, Telegram, More). |
| Residency | Regular / Research under the title | Fixed: both are shown with their text (default texts, editable under Residency Types). "Reguler" is renamed "Regular". |
| Open Call | No detail page needed; several calls; description; button to the form | Fixed: the list shows every call in the design layout with its description; the button goes to the form. |
| Journal | Author, description, download button | Fixed: description (excerpt) shown; file/download buttons are small. Author shows when the "Author" field is filled (the migration does not know authors). |
| Support Us | Description missing | Fixed for new installs (opening text on the page); on an existing site write the text under Pages → Support Us. |
| Docs | Figma cross-check | Chrome, cards, Home, Open Call, detail page, lists were measured from Figma (DESIGN-NOTES.md). Text sizes of the Journal / About / Support / Open Archive / menu / search frames could not be read (Figma tool limit). |
