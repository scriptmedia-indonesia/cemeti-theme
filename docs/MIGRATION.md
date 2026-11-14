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
**Revert**. Checked on the full real export (561 published posts): **245 posts have such sections** (61 gallery,
192 documentation, 167 with biographies). Sections inside block groups/columns, headings written as `h1`, artist names
written as headings, "Fota Karya", curly apostrophes, artwork titles between pictures and a "⇪" heading are handled.
**Nothing is dropped silently**: descriptions of the works, videos and embeds inside a picture section stay in the text,
and a picture that is not in the Media Library stays in the text (counted per post, listed under "0. Media").
Run a small batch first and look at five entries before converting everything.

## Pictures: do "0. Media" first

The importer brings the pictures only when it can download them from the old site, and it gives them new IDs. The old
text still says `cemeti.art/wp-content/uploads/…` and `wp-image-123`, so the theme recognises a picture **by its file
path** (host and size suffix ignored); an old `wp-image` number is trusted only when it is the same file.
On the real export the posts mention 10,087 pictures: 6,406 have a Media Library item in the export, and 2,388 do not
(they were never in the library), so they cannot appear unless they are downloaded.

**Tools → Cemeti Converter → "0. Media"** (old site host is set in section 2, default `cemeti.art`):

1. **Restore files**: every Media Library item whose file is missing is downloaded again and its sizes are made.
2. **Fix posts**: every picture written in a post's text gets a Media Library item (downloaded if needed); a post without
   a valid featured image gets the first picture of its text.

Both run in steps of about 12 seconds and continue by themselves; run them again any time (finished work is skipped).
A report shows restored / failed files and the last problems (usually: the old site no longer has the file, or the
uploads folder is not writable). If a conversion was already done before the media was repaired, press **Revert all**
and **Convert** again: the gallery and documentation are then built from the repaired pictures.

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
   then on the new site *Tools → Import → WordPress*, tick **Download and import file attachments** (or install this
   theme on a copy of the old site and convert there). If the importer stops or the pictures do not show, do not
   re-run it: use **"0. Media"** below.
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

**Converting again is safe** (tested): Convert never touches a post that is already converted, so pressing it twice
repeats nothing. **Revert all** brings every post back exactly as imported (type, text, categories, tags; the converter's
fields and terms are removed), and Convert after it gives the same result as the first time, however many rounds you do.
One thing a Revert cannot keep: **text or gallery you edited by hand on a converted entry** goes back to the imported
text, so do the bulk conversion first and the hand editing after. Pictures added by "0. Media" and featured images
stay (they are the point of repeating).

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
