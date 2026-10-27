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
