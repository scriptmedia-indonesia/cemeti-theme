# Category mapping: old cemeti.art → new site

Based on the export `cemeti.WordPress.2026-10-31.xml` (read as data, not stored in this repo):
**578 posts** (561 published, 15 draft, 2 private), 4,278 attachments, no pages, **221 categories**,
tags are people names. Every post has 1–7 categories (average 2.6). The rules live in
`themes/scriptmedia-cemeti/config/migration-rules.php` and are used by **Tools → Cemeti Converter**
(mode "Cemeti rules").

## How the old site was organised

- Every article exists **twice**, one post per language (Indonesian and English), linked by an
  "English | Indonesia" switch at the top of the text. English project categories carry a trailing `_`
  in the slug (`maintenance-works_`).
- Three kinds of categories are mixed: **type** (Pameran/Exhibition, Diskusi/Discussion, Esai/Essay …),
  **project or series** (Rimpang Nusantara, Maintenance Works, Body Journey …) and **place**
  (Kabupaten Aceh Besar, Yogyakarta …).
- Event dates are not in a field: they are written in the text ("07 Juli - 07 Agustus 2011").

## Result of the rules on the export (dry run)

| New type | Posts | Decided by |
|---|---|---|
| Exhibition | 109 (Solo 6, Group 15, not stated 88) | categories Pameran, Exhibition, Pameran Tunggal/Kelompok, Solo/Group Exhibition |
| Public Program | 141 | Program Publik / Public Program, Belajar dari yang Dekat / Learning Nearby, **plus event categories** (Diskusi, Lokakarya, Pertunjukan, Pemutaran, Simposium, Presentasi and their English twins: 117 posts) |
| Journal | 252 (News 176, Essay 46, Curatorial Essay 30) | everything else; Esai/Essay → Essay; Pengantar … / Introduction → Curatorial Essay |
| Residency | 36 (Reguler 2) | categories Residensi*/Residency* |
| Open Studio | 26 | category Open Studio or title "Open Studio:" |
| Open Call | 8 | title "Open Call", "Panggilan Terbuka", "Lamaran Terbuka" |
| Skipped | 6 | draft templates ("TEMPLATE …", "!!! EDIT …"), left untouched |

- **Language**: 321 Indonesian, 230 English, 21 undecided (set by hand). **215 translation pairs** are found
  from the switch links and linked when Polylang is active.
- **Dates**: read from the text for **280 of 320** dated posts (Indonesian and English month names);
  the other 40 (mostly per-period Residency pages) get the publish date and are listed as "no date in the text".
- **Open Call deadlines** are read from "Deadline / Batas waktu"; all 8 gave a plausible date.
- **Tags** (people) become Artists (Exhibition, Residency, Open Studio) or Speakers (Public Program).
- The remaining **115 project/place categories** become **Project** terms.

## Decisions to confirm

1. **Event posts as Public Program.** 117 posts in discussion/workshop/screening/performance categories now go to
   Public Program because the design's Public Program cards are talks, screenings and workshops. Some of them are
   reports about an activity rather than an event page. To keep them in the Journal set
   `'events_as_public_program' => false` in `config/migration-rules.php` before converting.
2. **Exhibition Solo/Group**: 88 of 109 exhibitions do not say which in their categories. They get no term;
   fill it in afterwards (it drives the Solo/Group menu entries).
3. **Residency Reguler/Research**: only 2 posts are marked; the rest need a term by hand.
4. **Journal "News" is large (176)**: it is the default for project updates. Move some to Essay by hand if wanted.
5. **Open Call from title**: 8 matches; check there are no false positives.
