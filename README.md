# Cemeti — website theme

Standalone WordPress theme `scriptmedia-cemeti` for **Cemeti – Institut untuk Seni dan Masyarakat**. It needs no parent theme. Scriptmedia Core is an optional integration.

- Install: zip `themes/scriptmedia-cemeti/`, upload via Appearance → Themes, activate. Edit address, hours and social links under Settings → Cemeti. Content types (Exhibition, Residency, Public Program, Open Studio, Open Call, Journal) appear in the admin menu; after activating, open Settings → Permalinks and click Save once if URLs 404. Create pages with the slugs `about`, `open-archive`, `support-us` (plus four child pages for its accordion) and `contact-us`; see `docs/ARCHITECTURE.md`.
- Requires: WordPress 6.6+, PHP 8.1+. Optional: Polylang (ID/EN switcher), Secure Custom Fields, Scriptmedia Core.
- Docs: [`docs/CATEGORY-MAPPING.md`](docs/CATEGORY-MAPPING.md) (what the converter does with the old cemeti.art categories), [`docs/MIGRATION.md`](docs/MIGRATION.md) (moving the old category-based posts with Tools → Cemeti Converter), [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md), [`docs/DESIGN-NOTES.md`](docs/DESIGN-NOTES.md).
- Design source: `design/cemeti-design.pdf`.

Status: **M1–M5 complete** (foundation, content model, templates, applications and search, SEO and hardening). Before launch follow [`docs/LAUNCH-CHECKLIST.md`](docs/LAUNCH-CHECKLIST.md). See `docs/ARCHITECTURE.md`.
