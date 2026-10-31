<?php
// Run: php tests/m4-logic.php  (stubs WordPress; checks upload validation, form token, search clause, required documents).
define( 'ABSPATH', '/' ); define( 'CEMETI_DIR', dirname( __DIR__ ) . '/themes/scriptmedia-cemeti' );
$GLOBALS['field'] = '';
function add_action() {} function add_filter() {} function apply_filters( $t, $v ) { return $v; }
function sanitize_file_name( $s ) { return $s; }
function wp_salt( $s ) { return 'test-salt-' . $s; }
function cemeti_get_field( $k, $id ) { return $GLOBALS['field']; }
function get_post_status( $id ) { return 'publish'; } function cemeti_get_status( $id ) { return $GLOBALS['status'] ?? 'current'; }
require CEMETI_DIR . '/inc/core-bridge.php'; require CEMETI_DIR . '/inc/applications.php'; require CEMETI_DIR . '/inc/search.php';

$fail = 0; function ok( $c, $m ) { global $fail; echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; if ( ! $c ) { $fail++; } }
$tmp = sys_get_temp_dir();
$pdf = tempnam( $tmp, 'p' ); file_put_contents( $pdf, "%PDF-1.7\n1 0 obj\n" );
$exe = tempnam( $tmp, 'e' ); file_put_contents( $exe, "MZ\x90\x00 not a pdf" );
$big = tempnam( $tmp, 'b' ); file_put_contents( $big, '%PDF-' . str_repeat( 'x', 2000 ) );
$mk = fn( $path, $name, $err = 0 ) => array( 'name' => $name, 'tmp_name' => $path, 'size' => filesize( $path ), 'error' => $err );

ok( cemeti_validate_upload( $mk( $pdf, 'cv.pdf' ), 1000, true ) === '', 'valid PDF accepted' );
ok( cemeti_validate_upload( $mk( $pdf, 'CV.PDF' ), 1000, true ) === '', 'extension case ignored' );
ok( cemeti_validate_upload( $mk( $exe, 'cv.pdf' ), 1000, true ) === 'invalid', 'wrong content with .pdf name rejected' );
ok( cemeti_validate_upload( $mk( $pdf, 'cv.exe' ), 1000, true ) === 'invalid', 'wrong extension rejected' );
ok( cemeti_validate_upload( $mk( $big, 'a.pdf' ), 1000, true ) === 'invalid', 'too large rejected' );
ok( cemeti_validate_upload( array( 'error' => UPLOAD_ERR_NO_FILE ), 1000, true ) === 'missing', 'missing required file' );
ok( cemeti_validate_upload( array( 'error' => UPLOAD_ERR_NO_FILE ), 1000, false ) === '', 'missing optional file fine' );
ok( cemeti_validate_upload( $mk( $pdf, 'a.pdf', UPLOAD_ERR_PARTIAL ), 1000, true ) === 'invalid', 'upload error rejected' );

$now = 1800000000;
ok( cemeti_form_token_valid( cemeti_form_token( $now - 10 ), $now ), 'fresh token valid' );
ok( ! cemeti_form_token_valid( cemeti_form_token( $now - 1 ), $now ), 'token too new (bot speed) rejected' );
ok( ! cemeti_form_token_valid( cemeti_form_token( $now - 9000 ), $now ), 'token too old rejected' );
ok( ! cemeti_form_token_valid( ( $now - 10 ) . '.deadbeef', $now ), 'forged token rejected' );
ok( ! cemeti_form_token_valid( '', $now ) && ! cemeti_form_token_valid( 'abc.def', $now ), 'garbage token rejected' );

$wp = " AND (((wp_posts.post_title LIKE '%x%') OR (wp_posts.post_content LIKE '%x%'))) AND (wp_posts.post_password = '') ";
$w  = cemeti_widen_search( $wp, 'wp_posts.ID IN (1)' );
ok( strpos( $w, "AND ( wp_posts.ID IN (1) OR ( (((wp_posts.post_title LIKE '%x%')" ) !== false, 'extra condition goes inside the search group' );
ok( preg_match( "/\)\s+AND \(wp_posts.post_password = ''\)\s*$/", $w ) === 1 && substr_count( $w, '(' ) === substr_count( $w, ')' ), 'password check stays outside, parentheses balanced' );
ok( cemeti_widen_search( '', 'x' ) === '', 'empty search untouched' );
ok( cemeti_widen_search( " AND ((a LIKE 'b'))", 'wp_posts.ID IN (2)' ) === " AND ( wp_posts.ID IN (2) OR ( ((a LIKE 'b')) ) )", 'no password clause: still balanced' );

$GLOBALS['field'] = '';
ok( cemeti_open_call_documents( 1 ) === array( 'cv', 'portfolio', 'proposal', 'motivation' ), 'documents: default all four' );
$GLOBALS['field'] = 'CV, Proposal ,bogus';
ok( cemeti_open_call_documents( 1 ) === array( 'cv', 'proposal' ), 'documents: list, unknown ignored' );
$GLOBALS['field'] = 'none';
ok( cemeti_open_call_documents( 1 ) === array(), 'documents: none' );
$GLOBALS['status'] = 'previous'; ok( ! cemeti_open_call_is_open( 1 ), 'closed after the deadline' );
$GLOBALS['status'] = 'current'; ok( cemeti_open_call_is_open( 1 ), 'open before the deadline' );
echo $fail ? "\n$fail FAILED\n" : "\nALL OK\n"; exit( $fail ? 1 : 0 );
