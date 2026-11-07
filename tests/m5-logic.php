<?php
// Run: php tests/m5-logic.php  (stubs WordPress; checks summaries, structured data, dates and security headers).
define( 'ABSPATH', '/' ); define( 'CEMETI_DIR', dirname( __DIR__ ) . '/themes/scriptmedia-cemeti' );
function add_action() {} function add_filter() {} function apply_filters( $t, $v ) { return $v; } function remove_action() {}
require CEMETI_DIR . '/inc/seo.php'; require CEMETI_DIR . '/inc/security.php';

$fail = 0; function ok( $c, $m ) { global $fail; echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; if ( ! $c ) { $fail++; } }

ok( cemeti_summary( '<p>Halo <b>dunia</b>.</p> [gallery ids="1,2"]  Teks  lain&nbsp;di sini.' ) === 'Halo dunia . Teks lain di sini.', 'summary: tags, shortcodes and spaces cleaned' );
$long = str_repeat( 'kata ', 60 );
$s = cemeti_summary( $long, 50 );
ok( mb_strlen( $s ) <= 51 && mb_substr( $s, -1 ) === '…' && mb_substr( $s, -2, 1 ) !== ' ', 'summary: cut at a word with an ellipsis (' . mb_strlen( $s ) . ' chars)' );
ok( cemeti_summary( 'Singkat.', 155 ) === 'Singkat.', 'summary: short text unchanged' );
ok( cemeti_summary( '', 155 ) === '', 'summary: empty' );
ok( cemeti_summary( str_repeat( 'é', 300 ), 100 ) === str_repeat( 'é', 100 ) . '…', 'summary: multibyte safe' );
ok( cemeti_ymd_to_iso( '20260306' ) === '2026-03-06' && cemeti_ymd_to_iso( '' ) === '' && cemeti_ymd_to_iso( '2026-03-06' ) === '', 'ymd to ISO' );

$e = cemeti_schema_event( array( 'name' => 'Pameran', 'url' => 'https://x.test/e', 'start' => '20260306', 'end' => '20260418', 'image' => 'https://x.test/i.jpg', 'description' => 'Deskripsi', 'venue' => 'Cemeti', 'address' => 'Jl. DI Panjaitan, Yogyakarta' ) );
ok( $e['@type'] === 'Event' && $e['startDate'] === '2026-03-06' && $e['endDate'] === '2026-04-18' && $e['location']['@type'] === 'Place', 'event: dates and place' );
$e2 = cemeti_schema_event( array( 'name' => 'x', 'url' => 'u', 'start' => '', 'end' => '', 'image' => '', 'description' => '', 'venue' => 'v', 'address' => 'a' ) );
ok( ! isset( $e2['startDate'] ) && ! isset( $e2['image'] ) && ! isset( $e2['description'] ), 'event: no empty fields' );
$e3 = cemeti_schema_event( array( 'name' => 'x', 'url' => 'u', 'start' => '20260306', 'end' => '', 'image' => '', 'description' => '', 'venue' => 'v', 'address' => 'a' ) );
ok( $e3['endDate'] === '2026-03-06', 'event: one-day event ends the same day' );
$a = cemeti_schema_article( array( 'name' => 'Esai', 'url' => 'u', 'published' => '2025-01-01T10:00:00+07:00', 'modified' => '', 'author' => 'Mira', 'image' => '', 'publisher' => 'Cemeti' ) );
ok( $a['@type'] === 'Article' && $a['dateModified'] === $a['datePublished'] && $a['author']['name'] === 'Mira' && ! isset( $a['image'] ), 'article: modified falls back, author set, no empty image' );
$o = cemeti_schema_organization( array( 'name' => 'Cemeti', 'url' => 'u', 'logo' => 'l', 'address' => 'a', 'email' => '', 'same_as' => array() ) );
ok( ! isset( $o['email'] ) && ! isset( $o['sameAs'] ) && in_array( 'ArtGallery', $o['@type'], true ), 'organization: optional fields omitted' );
$b = cemeti_schema_breadcrumbs( array( array( 'Home', 'u0' ), array( 'Exhibition', 'u1' ), array( 'Judul', 'u2' ) ) );
ok( count( $b['itemListElement'] ) === 3 && $b['itemListElement'][2]['position'] === 3, 'breadcrumbs: positions' );

// JSON-LD must not be breakable by a title containing a closing script tag.
$bad  = cemeti_schema_event( array( 'name' => '</script><script>alert(1)</script>', 'url' => 'u', 'start' => '', 'end' => '', 'image' => '', 'description' => '', 'venue' => 'v', 'address' => 'a' ) );
$json = json_encode( $bad, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
ok( stripos( $json, '</script' ) === false && stripos( $json, '<' ) === false, 'JSON-LD: script breakout escaped' );

$h = cemeti_security_headers();
ok( $h['X-Content-Type-Options'] === 'nosniff' && $h['X-Frame-Options'] === 'SAMEORIGIN' && isset( $h['Referrer-Policy'], $h['Permissions-Policy'] ), 'security headers present' );
$ep = array( '/wp/v2/users' => 1, '/wp/v2/users/(?P<id>[\d]+)' => 1, '/wp/v2/posts' => 1 );
function is_user_logged_in() { return false; }
ok( cemeti_rest_hide_users( $ep ) === array( '/wp/v2/posts' => 1 ), 'REST: user endpoints hidden from visitors' );
echo $fail ? "\n$fail FAILED\n" : "\nALL OK\n"; exit( $fail ? 1 : 0 );
