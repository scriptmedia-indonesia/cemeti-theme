# Moving the old site's content (category-based) to the new theme

The old site told content apart by **category**. The new theme uses **content types**
(Exhibition, Residency, Public Program, Open Studio, Open Call, Journal) with a few
terms (Solo/Group, Reguler/Research, Journal categories). **Tools → Cemeti Converter**
maps one to the other for all posts in a few clicks.

## Old text structure → new structure (automatic)

The old posts keep everything in one body. When a post is converted (Converter or "Move to …") the theme reads the
headings and splits the body (option "Split the old text into the new structure", on by default):

| In the old text | In the new entry |
|---|---|
| pictures under *Foto Karya / Artwork Images / Galeri / Exhibition View* | **Gallery** (+ caption if one short line) |
| pictures under *Dokumentasi / Documentation / Opening Documentation* | **Documentation** (+ caption) |
| *Biografi / Biography / Profil Seniman*: a bold name starts each artist, the picture before it is the photo | **Biographies** (one block per artist) |
| a PDF link anywhere in the text | **Booklet** link |
| *Daftar Isi* + its links, "Kembali ke Daftar Isi", ⇪, *English \| Indonesia*, *Terbitan Terkait* | dropped (the site builds the contents bar, back links, language switch; set Related by hand) |
| everything else (curatorial statement, essay, practical information) | stays in the text (Foreword) |

A post with no recognised section headings is left untouched. The original text is kept in the post and returns on
**Revert**. Checked on the real export: 167 of 561 published posts have such sections (41 gallery, 118 documentation,
60 with biographies = 125 artists, 17 of them without a readable name: check those). Run a small batch first and look
at five entries before converting everything.

## Where to set the category of imported posts

Imported content first sits under **Posts**. You decide its type and category in either of two places:

- **Posts → All Posts**: tick posts, choose **Bulk actions → "Move to Exhibition — Solo / Group, Residency — Regular /
  Research, Public Program, Open Studio, Open Call, Journal — News / Essay / Curatorial Essay"**, press **Apply**.
  Dates, artists (from tags), language and the old address for the 301 redirect are carried over like in the Converter.
- **Edit a single post**: the box **"Cemeti: move this post"** beside the editor, choose, press **Move**.
- **All at once by the old site's categories**: **Tools → Cemeti Converter** (below).

After moving, the entry lives under its own menu (Exhibition, Residency …) where you fill gallery, biographies, booklet.

## Steps

1. **Back up** the database of the site you convert on.
2. Get the old posts into the new site: on the old site use *Tools → Export → All content*,
   then on the new site *Tools → Import → WordPress*. (Or install this theme on a copy of
   the old site and convert there.) Media and categories come with the export.
3. Activate this theme and open **Tools → Cemeti Converter**.
4. **Choose the mode.** For the old cemeti.art site use **Cemeti rules** (default): the sorting is already
   built from its export, see `docs/CATEGORY-MAPPING.md` for what it does and the decisions to confirm.
   Install and activate **Polylang** (Indonesian and English) first so each post gets its language, convert,
   then press **Link English/Indonesian translations**. Set the old permalink pattern
   (`%year%/%monthnum%/%day%/%postname%` for cemeti.art) so the 301 redirects match the old URLs.
   For any other site use **Manual** and map categories yourself. Examples:

   | Old category | Becomes | Term |
   |---|---|---|
   | Exhibition – Solo | Exhibition | `solo` |
   | Exhibition – Group | Exhibition | `group` |
   | Residency | Residency | (or `reguler` / `research`) |
   | News, Essay, Curatorial Essay | Journal | `__name` (uses the category's own name) |
   | Uncategorized | Skip | |

   A post in several categories takes the first mapped category in the table order.
5. **Event dates.** In Cemeti rules mode dates are read from the text. In Manual mode, if the old site kept
   dates in a custom field, enter its key (start, and optionally end); otherwise the post's publish date is used. Formats like
   `20260306`, `2026-03-06` and `6 March 2026` are read.
6. **Preview** shows counts per type, three sample titles each, posts that would be
   skipped and posts without a readable date. Nothing changes yet.
7. **Convert.** Runs 50 posts per step and continues by itself. **Revert all** undoes it.

## What is kept

- Slug, text, featured image and publish date (the post is the same post).
- The old URL path and old categories, saved on each post (`_cemeti_old_path`, `_cemeti_old_cats`).
- Old links: a request that would 404 is redirected (301) to the converted post, and old
  category archives (`/category/…`) go to the matching archive (with `?type=` for Exhibition
  and Residency). The path is recorded while the post is still a normal post, so convert on a
  site whose permalink structure matches the old one.

## What still needs a person

- Posts without a readable date (reported after each step): add the dates by hand.
- Content that lived in page-builder blocks may need tidying.
- Fields with no old equivalent (gallery, documentation, biographies, related) stay empty
  until filled, or until a custom import is written for them.
- Polylang: language pairs must be linked on the site where the posts were created.
- Check Residency posts for Reguler/Research when one category held both.

## Checks after converting

- Counts per type match the preview.
- Open the Exhibition, Residency, Journal archives and a few single entries.
- Open ten old URLs and confirm they land on the right entry.
- *Settings → Permalinks → Save* once.
