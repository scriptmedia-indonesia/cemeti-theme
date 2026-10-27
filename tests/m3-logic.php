<?php
// Run: php tests/m3-logic.php  (stubs WordPress; checks the pure helpers used by the M3 templates).
define( 'ABSPATH', '/' );
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses( $s ) { return $s; }
require dirname( __DIR__ ) . '/themes/scriptmedia-cemeti/inc/templates.php';

$fail = 0;
function ok( $c, $m ) { global $fail; echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; if ( ! $c ) { $fail++; } }

$entries = array(
	array( 'title' => '“Dalam Gelap, Kugenggam Ingatan”', 'url' => '/d' ),
	array( 'title' => 'Berbalik dan Melihat ke Belakang', 'url' => '/b' ),
	array( 'title' => 'apa kabar', 'url' => '/a' ),
	array( 'title' => 'Nyawiji Ibu Bumi', 'url' => '/n' ),
	array( 'title' => '900mdpl', 'url' => '/9' ),
	array( 'title' => 'Zine', 'url' => '/z' ),
	array( 'title' => 'Ada', 'url' => '/a2' ),
);
$g = cemeti_group_az( $entries );
ok( array_keys( $g ) === array( 'A', 'B', 'D', 'N', 'Z', '#' ), 'letters sorted A-Z with # last: ' . implode( ',', array_keys( $g ) ) );
ok( $g['D'][0]['title'] === '“Dalam Gelap, Kugenggam Ingatan”', 'leading quote ignored for letter' );
ok( array_column( $g['A'], 'url' ) === array( '/a2', '/a' ), 'titles sorted inside a letter (Ada before apa)' );
ok( ! isset( $g['A'][0]['sort'] ), 'sort key removed' );
list( $l, $r ) = cemeti_split_az( $g );
ok( array_keys( $l ) === array( 'A', 'B', 'D', '#' ) && array_keys( $r ) === array( 'N', 'Z' ), 'split A-M / N-Z, # on the left' );
ok( cemeti_group_az( array() ) === array(), 'empty input' );
$links = cemeti_share_links( 'https://x.test/a b', 'Judul & "ok"' );
ok( strpos( $links['WhatsApp'], 'wa.me/?text=' ) !== false && strpos( $links['WhatsApp'], '&' ) === false || substr_count( $links['WhatsApp'], '&' ) === 0, 'WhatsApp link encoded' );
ok( strpos( $links['Telegram'], 'url=https%3A%2F%2Fx.test%2Fa%20b' ) !== false, 'Telegram url encoded' );
ok( cemeti_multiline( "a\n<b>" ) === "a<br />\n&lt;b&gt;", 'multiline escapes html' );
echo $fail ? "\n$fail FAILED\n" : "\nALL OK\n";
exit( $fail ? 1 : 0 );
