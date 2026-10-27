# Architecture — Cemeti on Scriptmedia

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
| Field groups | loader | — | `acf-json/` (cemeti_ prefix) | dynamic tags | storage | per-language values |
| Site Settings ID/EN | options pages | — | `cemeti_option()` | — | options | `id` / `en` option ids |
| Status current/upcoming/previous | `cemeti_get_status` equivalents, cron | — | labels | — | date fields | — |
| Related (max 4, two-way) | save hooks | — | `template-parts/related` | — | relationship | — |
| Header, overlay menu, search bar, What's on, footer | — | menu locations | **done in M1** | PHP fallback is primary | — | switcher |
| Archive grid, detail pages, Journal rows | — | base templates | templates + parts | optional widgets | — | — |
| Open Call form | handler, CPT, download | — | form markup | — | — | email language |
| Search | shared query | — | `search.php`, overlay | — | — | language filter |
| Redirects, SEO, security | all | — | — | — | — | — |

## M1 status (this commit)

Implemented in the child theme: tokens, self-hosted fonts, header with sticky bar, overlay menu, search strip, What's on panel, footer, ID/EN switcher (Polylang-safe), `cemeti_option()` with language fallback, `cemeti_format_date_range()`, image sizes, versioned/deferred assets, font preload, core bridge with dependency notice.

Not yet: content model, status logic, templates, forms, SEO, hardening. These are M2–M5.

## Open assumptions (must be verified)

1. **Scriptmedia Core and parent theme were not available** when this repo was created. `inc/core-bridge.php` hooks `scriptmedia_post_types`, `scriptmedia_taxonomies` and `scriptmedia_options_pages`; these names are placeholders for the pattern in the architecture brief and need to be replaced with the real filters (or the registrar API) before M2.
2. **Parent theme stub.** Verification ran with a one-line stub named `scriptmedia`, not the real parent. Parent fallback templates (`single.php`, `archive.php`, `page.php`) and menu/Elementor locations were therefore not exercised.
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
