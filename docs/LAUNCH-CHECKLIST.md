# Launch checklist

Everything here is a manual step in WordPress or at the host. The theme cannot do it for you.

## 1. Before installing
- [ ] Full backup (files and database) of the target site.
- [ ] PHP 8.1+ and WordPress 6.6+.
- [ ] Install and activate **Polylang** (Indonesian default, English second) if the site is bilingual.

## 2. Install the theme
- [ ] Appearance → Themes → Upload `scriptmedia-cemeti.zip`, activate.
- [ ] Settings → Permalinks → choose **Post name**, press Save once (types and redirects need it).
- [ ] Settings → Cemeti: fill address, hours, e-mail, social links, route URL (Google Maps link),
      "Application notifications go to", and the site description, in both languages.

## 3. Content
- [ ] Move the old posts with **Tools → Cemeti Converter** (see `MIGRATION.md`, `CATEGORY-MAPPING.md`); run it on a copy first.
- [ ] Create pages with the slugs `about`, `open-archive`, `support-us` (+ four child pages), `contact-us`.
- [ ] About: add People (Advisory Board, Team).
- [ ] Residency Type: write the Reguler and Research introductions in the term descriptions.
- [ ] Set Solo/Group on exhibitions, Reguler/Research on residencies.
- [ ] Fill the entries the converter cannot: gallery, documentation, biographies, related.
- [ ] Press "Link translations" (Polylang) and check twins on five entries.

## 4. Assets and decisions from the client
- [ ] Akzidenz-Grotesk Next web font files (licensed). Add the `@font-face` in `assets/css/tokens.css`.
- [ ] Vector logo (SVG) to replace `assets/img/logo.webp`; cream line-drawing watermark for What's on.
- [ ] Colour contrast: the design colours fail WCAG AA in several pairs (`DESIGN-NOTES.md`, section 4). Client decides per row.
- [ ] Where the What's on panel opens from (the PDF does not show it).
- [ ] Derived pages approved: Contact Us, Search, 404, Open Call list, People grid (`DESIGN-NOTES.md`, section 6).
- [ ] Mobile layouts (not in the PDF).

## 5. Test on the live host
- [ ] Menu, search, What's on, language switch, footer on phone and desktop.
- [ ] Send a test application: e-mails arrive (team and applicant), PDFs appear under Applications, download works only when logged in as administrator.
- [ ] Open `…/wp-content/uploads/cemeti-applications/` and a PDF URL from it: both must be refused (403/404). On Nginx add a `deny` rule for that folder; the `.htaccess` only works on Apache.
- [ ] Ten old URLs redirect (301) to the right new pages.
- [ ] `/wp-sitemap.xml` loads and lists the entries; `/robots.txt` is sane.
- [ ] View source of one Exhibition: description, Open Graph and JSON-LD present. Check it in Google's Rich Results Test.
- [ ] `/?author=1` and `/wp-json/wp/v2/users` do not reveal user names; `/xmlrpc.php` is refused.
- [ ] Response headers (nosniff, frame, referrer, permissions) via browser dev tools or securityheaders.com.

## 6. Hosting and caching
- [ ] HTTPS everywhere; set the canonical host (www or not) and redirect the other.
- [ ] Full-page cache: exclude Open Call pages (the form has a nonce and a time token) or keep cache under 12 hours.
- [ ] `post_max_size` / `upload_max_filesize` of at least 25 MB (four PDFs of 5 MB); a PHP limit below that makes uploads fail silently.
- [ ] Mail: use SMTP (a mail plugin) so application e-mails are delivered.
- [ ] Automatic backups and updates for WordPress and plugins.
- [ ] Strong admin passwords and two-factor sign-in for administrators; limit login attempts (host or plugin).

## 7. Not covered by the theme
- A Content-Security-Policy header (needs a list of every external script and embed the editors will use).
- Image optimisation and CDN, malware scanning, WAF: host or plugin level.
