# Architecture — Cemeti on Scriptmedia

> **Standalone mode.** No Scriptmedia parent theme or Core plugin could be found, so the theme currently ships standalone: no `Template:` header, its own Site Settings page (`inc/settings.php`, Settings → Cemeti), and `cemeti_option()` reads that first, then SCF/ACF if present. The layer table below is the target architecture; `inc/core-bridge.php` stays as an optional hook for Core.

## Layers

| Layer | Package | Owns | Must not own |
|---|---|---|---|
| Plugin | `scriptmedia-core` | CPTs, taxonomies, field groups (SCF local JSON), options pages, status/relations logic, Open Call form, search, redirects, SEO/security, Polylang/SCF integration, widgets, dynamic tags, WP-CLI | Cemeti look and feel |
| Parent theme | `scriptmedia` | theme supports, Elementor/menu locations, generic rendering helpers, base CSS, fallback PHP templates | Cemeti data model |
| Child theme | `scriptmedia-cemeti` (**this repo**) | tokens, fonts, logo, Cemeti templates and template-parts, CSS/JS, `config/*.php` handed to core, Elementor kit | CPT/taxonomy registration code |

Content must survive a theme switch, so CPTs and taxonomies are declared as config arrays in `themes/scriptmedia-cemeti/config/` and registered by Core through filters. The child never calls `register_post_type()`.

## Feature map (target)

| Feature | Core plugin | Parent | Child | Elementor | SCF | Polylang |
|---|---|---|---|---|---|---|
| CPTs / taxonomies | registrar | — | config arrays | archive/single templates optional | — | translatable |
| Field groups | loader | — | `config/fields.php` meta box (standalone) | dynamic tags | storage | per-language values |
| Site Settings ID/EN | options pages | — | `cemeti_option()` | — | options | `id` / `en` option ids |
| Status current/upcoming/previous | `cemeti_get_status` equivalents, cron | — | labels | — | date fields | — |
| Related (max 4, two-way) | save hooks | — | `template-parts/related` | — | relationship | — |
| Header, overlay menu, search bar, What's on, footer | — | menu locations | **done in M1** | PHP fallback is primary | — | switcher |
| Archive grid, detail pages, Journal rows | — | base templates | templates + parts | optional widgets | — | — |
| Open Call form | handler, CPT, download | — | form markup | — | — | email language |
| Search | shared query | — | `search.php`, overlay | — | — | language filter |
| Redirects, SEO, security | all | — | — | — | — | — |

## M1 status

Implemented in the theme: tokens, self-hosted fonts, header with sticky bar, overlay menu, search strip, What's on panel, footer, ID/EN switcher (Polylang-safe), `cemeti_option()` with language fallback, `cemeti_format_date_range()`, image sizes, versioned/deferred assets, font preload.

## M2 status (content model)

| Piece | Where |
|---|---|
| Post types: `exhibition`, `residency`, `public_program`, `open_studio`, `open_call`, `journal` (URLs match the menu) | `config/post-types.php`, `inc/content-model.php` |
| Taxonomies: Exhibition Type (Solo, Group), Residency Type (Reguler, Research), Journal Category (News, Essay, Curatorial Essay); default terms seeded once | `config/taxonomies.php` |
| Fields as a "Details" meta box (no SCF needed): dates, artists, opening, venue, gallery, documentation, biographies (repeater), related, Open Call deadline + URL, Journal author | `config/fields.php`, `inc/fields.php`, `assets/js/admin-fields.js` |
| Status current / upcoming / previous, computed at read time in the site timezone (`cemeti_get_status`, `cemeti_status_query`) | `inc/status.php` |
| Related: max 4, two-way, unlinked on delete | `inc/relations.php` |
| What's on feed: current Exhibition, Open Studio, Residency, cached and invalidated on save | `inc/whats-on.php` |
| Polylang: all types and taxonomies registered as translatable | `inc/content-model.php` |

Poster / cover = featured image. Dates are stored as `Ymd`; a one-day event gets end = start. Logic tests: `php tests/m2-logic.php`.

Not yet: page templates for these types (M3), Open Call form handling and search (M4), SEO and hardening (M5).

## M3 status (templates)

| Page | Template | Notes |
|---|---|---|
| Home | `front-page.php` | Featured entry per program (current, else soonest upcoming, else latest previous) + 3 Journal rows |
| Exhibition, Public Program, Open Studio archives | `archive.php` + `template-parts/card.php` | 4-col grid, 12 per page, newest start date first; `?type=solo\|group` filters Exhibition |
| Residency archive | `archive-residency.php` | Reguler / Research intros are the **term descriptions** of Residency Type |
| Journal, Open Call archives | `archive-journal.php`, `archive-open_call.php` | Ruled rows |
| Exhibition, Public Program, Open Studio, Residency detail | `single.php` | Contents, Foreword, Gallery, Documentation, Biography, Booklet, Related, Share; each section only when filled |
| Journal detail | `single-journal.php` | |
| Open Call detail | `single-open_call.php` | Button hidden after the deadline |
| About | `page-about.php` (slug `about`) | Advisory Board and Team come from the **People** type, grouped by Person Group |
| Open Archive | `page-open-archive.php` (slug `open-archive`) | A–Z of Exhibition, Residency, Public Program, Open Studio titles, two columns |
| Support Us | `page-support-us.php` (slug `support-us`) | Accordion rows are the **child pages** of Support Us |
| Contact Us, Search, 404 | `page-contact-us.php`, `search.php`, `404.php` | Derived (not in the PDF) |
| Any other page | `page.php` | |

Editors must create pages with the slugs `about`, `open-archive`, `support-us`, `contact-us` and the four Support Us child pages. Share "More" appears only where the browser supports the Web Share API.

Not yet: Open Call form handling and search tuning (M4), SEO and hardening (M5).

## M4 status (applications and search)

| Piece | Where |
|---|---|
| Built-in Open Call form: name, email, city, link, message and up to four PDFs (CV, Portfolio, Proposal, Motivation letter; per Open Call via the "Required documents" field) | `template-parts/open-call-form.php`, `inc/applications.php` |
| Safeguards: nonce, hidden field, signed time token (3 s – 2 h), 5 tries per IP per hour, closed after the deadline, PDF only (extension and `%PDF-` header), 5 MB per file | `inc/applications.php` |
| Storage: private **Application** entries (Applications menu); PDFs in `uploads/cemeti-applications/` (deny rule, random file names), downloaded only through an administrator-only handler | `inc/applications.php` |
| E-mail: team notification (Settings → Cemeti → "Application notifications go to", default site admin) and confirmation to the applicant, in the site language | `inc/applications.php`, `inc/i18n.php` |
| Search: also matches Artists, Speakers and Journal authors; title matches first, then newest | `inc/search.php`, `inc/queries.php` |

If an external form is preferred, fill "External application URL" on the Open Call: the blue button then goes there and the built-in form is hidden.

Page caching: the form carries a nonce and a time token. Exclude Open Call pages from full-page caches (or keep cache lifetime under 12 hours).

## M5 status (SEO and hardening)

| Piece | Where |
|---|---|
| Meta description (field "Search description", else excerpt, else text, else site description), Open Graph, Twitter card, document title "Page – Institution" | `inc/seo.php` |
| JSON-LD: Organization (home), Event (Exhibition, Residency, Public Program, Open Studio), Article (Journal), BreadcrumbList; escaped so a title cannot close the script tag | `inc/seo.php` |
| `noindex` for search results, 404 and form result pages; robots.txt hides `/?s=`; sitemap without the user list; silent when Yoast, Rank Math, SEOPress or AIOSEO is active | `inc/seo.php` |
| Hardening: XML-RPC off, no generator/RSD/shortlink in the head, REST user list hidden from visitors, author archives redirect home, one generic login error, comments and pings off, no file editor, security headers (nosniff, frame SAMEORIGIN, referrer, permissions), Applications visible and editable by administrators only | `inc/security.php` |
| Redirects for old URLs | `inc/redirects.php` (M2/migration) |

Not covered: Content-Security-Policy, WAF, malware scanning (host level). See `LAUNCH-CHECKLIST.md`.

## Open assumptions (must be verified)

1. **Scriptmedia Core and parent theme were not available (theme is standalone for now)** when this repo was created. The theme registers the content model itself. `inc/core-bridge.php` only hands the `config/` arrays to Scriptmedia Core when `CEMETI_USE_CORE_REGISTRAR` is true, via `scriptmedia_post_types`, `scriptmedia_taxonomies` and `scriptmedia_options_pages`; these names are placeholders for the pattern in the architecture brief and need to be replaced with the real filters (or the registrar API) before M2.
2. **Not tested in a live WordPress.** Only syntax checks and file checksums ran. Install and smoke-test before M2.
3. **SCF vs ACF Pro.** The original Cemeti brief assumed ACF Pro; the architecture says SCF. Repeater, gallery, options pages and local JSON must be audited in SCF before M2. `cemeti_option()` already works with or without `get_field()`.
4. **Polylang free** cannot translate slugs of CPT archives; if `/en/exhibition/` vs `/id/…` slugs differ, that is a limitation to document, not a feature to claim.

## Repository layout

```
design/cemeti-design.pdf          visual source of truth
docs/                             ARCHITECTURE, DESIGN-NOTES, QA screenshots
themes/scriptmedia-cemeti/        child theme (installable as a ZIP of this folder)
  config/                         arrays passed to Scriptmedia Core
  inc/                            i18n, options, dates, setup, enqueue, navigation, core-bridge
  template-parts/                 menu-overlay, search-overlay, whats-on
  assets/{css,js,fonts,img}/
```
`plugins/scriptmedia-core` and `themes/scriptmedia` live in their own repositories and are intentionally absent.
