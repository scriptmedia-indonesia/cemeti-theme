# Moving the old site's content (category-based) to the new theme

The old site told content apart by **category**. The new theme uses **content types**
(Exhibition, Residency, Public Program, Open Studio, Open Call, Journal) with a few
terms (Solo/Group, Reguler/Research, Journal categories). **Tools → Cemeti Converter**
maps one to the other for all posts in a few clicks.

## Steps

1. **Back up** the database of the site you convert on.
2. Get the old posts into the new site: on the old site use *Tools → Export → All content*,
   then on the new site *Tools → Import → WordPress*. (Or install this theme on a copy of
   the old site and convert there.) Media and categories come with the export.
3. Activate this theme and open **Tools → Cemeti Converter**.
4. **Map categories.** For each old category pick what it becomes. Examples:

   | Old category | Becomes | Term |
   |---|---|---|
   | Exhibition – Solo | Exhibition | `solo` |
   | Exhibition – Group | Exhibition | `group` |
   | Residency | Residency | (or `reguler` / `research`) |
   | News, Essay, Curatorial Essay | Journal | `__name` (uses the category's own name) |
   | Uncategorized | Skip | |

   A post in several categories takes the first mapped category in the table order.
5. **Event dates.** If the old site kept dates in a custom field, enter its key
   (start, and optionally end). Otherwise the post's publish date is used. Formats like
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
