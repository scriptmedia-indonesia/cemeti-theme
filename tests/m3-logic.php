<?php
// Run: php tests/m3-logic.php  (stubs WordPress; checks the pure helpers used by the M3 templates).
define( 'ABSPATH', '/' );
function add_filter(){} function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses( $s ) { return $s; }
require dirname( __DIR__ ) . '/themes/scriptmedia-cemeti/inc/templates.php';

$fail = 0;
function ok( $c, $m ) { global $fail; echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; if ( ! $c ) { $fail++; } }

$links = cemeti_share_links( 'https://x.test/a b', 'Judul & "ok"' );
ok( strpos( $links['WhatsApp'], 'wa.me/?text=' ) !== false && strpos( $links['WhatsApp'], '&' ) === false || substr_count( $links['WhatsApp'], '&' ) === 0, 'WhatsApp link encoded' );
ok( strpos( $links['Telegram'], 'url=https%3A%2F%2Fx.test%2Fa%20b' ) !== false, 'Telegram url encoded' );
ok( cemeti_multiline( "a\n<b>" ) === "a<br />\n&lt;b&gt;", 'multiline escapes html' );
echo $fail ? "\n$fail FAILED\n" : "\nALL OK\n";
exit( $fail ? 1 : 0 );
