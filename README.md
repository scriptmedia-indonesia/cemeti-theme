# Cemeti — website theme

Standalone WordPress theme `scriptmedia-cemeti` for **Cemeti – Institut untuk Seni dan Masyarakat**. It needs no parent theme. Scriptmedia Core is an optional integration.

- Install: zip `themes/scriptmedia-cemeti/`, upload via Appearance → Themes, activate. Edit address, hours and social links under Settings → Cemeti. Content types (Exhibition, Residency, Public Program, Open Studio, Open Call, Journal) appear in the admin menu; after activating, open Settings → Permalinks and click Save once if URLs 404. Create pages with the slugs `about`, `open-archive`, `support-us` (plus four child pages for its accordion) and `contact-us`; see `docs/ARCHITECTURE.md`.
- Requires: WordPress 6.6+, PHP 8.1+. Optional: Polylang (ID/EN switcher), Secure Custom Fields, Scriptmedia Core.
- Docs: [`docs/MIGRATION.md`](docs/MIGRATION.md) (moving the old category-based posts with Tools → Cemeti Converter), [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md), [`docs/DESIGN-NOTES.md`](docs/DESIGN-NOTES.md).
- Design source: `design/cemeti-design.pdf`.

Status: **M1 (foundation), M2 (content model) and M3 (templates) complete**; M4–M5 pending. See `docs/ARCHITECTURE.md`.
