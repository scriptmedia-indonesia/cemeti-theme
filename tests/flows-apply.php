<?php
// Run: php tests/flows-apply.php — the Open Call application form from submit to stored entry, e-mails and download.
require __DIR__ . '/harness/bootstrap.php';
qa_boot();
$ids = qa_seed();
CemetiWP::$options['admin_email'] = 'admin@cemeti.test';
add_filter( 'cemeti_pre_move_upload', static function ( $pre, $from, $to ) { return copy( $from, $to ); }, 10, 3 );

$tmp = sys_get_temp_dir() . '/cemeti-qa-' . getmypid();
@mkdir( $tmp );
$pdf = static function ( $name ) use ( $tmp ) {
	$p = "$tmp/$name.pdf";
	file_put_contents( $p, "%PDF-1.7\n% $name\n" );
	return array( 'name' => "$name.pdf", 'tmp_name' => $p, 'size' => filesize( $p ), 'error' => 0 );
};
$none = array( 'name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE );
$all  = static function () use ( $pdf ) {
	return array( 'doc_cv' => $pdf( 'cv' ), 'doc_portfolio' => $pdf( 'portfolio' ), 'doc_proposal' => $pdf( 'proposal' ), 'doc_motivation' => $pdf( 'motivation' ) );
};
$form = static function ( array $over = array() ) use ( $ids ) {
	return array_merge(
		array(
			'open_call' => $ids['oc_open'], '_cemeti_nonce' => 'valid', 'cemeti_ts' => cemeti_form_token( time() - 20 ), 'cemeti_website' => '',
			'app_name' => 'Rina Seniman', 'app_email' => 'rina@example.org', 'app_city' => 'Bandung, Indonesia', 'app_link' => 'https://rina.example.org', 'app_message' => 'Halo',
		),
		$over
	);
};
/** Submit and return the result code from the redirect (e.g. "sent", "file"). */
$submit = static function ( array $post, array $files ) {
	$_POST  = $post;
	$_FILES = $files;
	try {
		do_action( 'admin_post_nopriv_cemeti_apply' );
	} catch ( CemetiRedirect $e ) {
		parse_str( (string) parse_url( $e->url, PHP_URL_QUERY ), $q );
		return array( isset( $q['apply'] ) ? $q['apply'] : '?', $e->url );
	}
	return array( 'no-redirect', '' );
};
$applications = static function () {
	return array_filter( CemetiWP::$posts, static function ( $p ) { return 'application' === $p['post_type']; } );
};
$reset_rate = static function () { CemetiWP::$transients = array(); };

/* ------------------------------------------------------------- success */
list( $code, $url ) = $submit( $form(), $all() );
ok( 'sent' === $code, "success: redirected with apply=sent (got $code)" );
ok( false !== strpos( $url, '#apply' ) && 0 === strpos( $url, 'http://cemeti.test/open-call/' ), 'success: back to the Open Call, at the form' );
$apps = $applications();
ok( 1 === count( $apps ), 'success: one application stored' );
$app = key( $apps );
ok( 'Rina Seniman — SEA Artist Residency Program' === CemetiWP::$posts[ $app ]['post_title'], 'success: title = applicant — open call' );
ok( 'rina@example.org' === get_post_meta( $app, 'app_email', true ) && $ids['oc_open'] === get_post_meta( $app, 'app_open_call', true ) && 'Bandung, Indonesia' === get_post_meta( $app, 'app_city', true ), 'success: details stored' );
$stored = get_post_meta( $app, 'app_files', true );
ok( array( 'cv', 'portfolio', 'proposal', 'motivation' ) === array_keys( $stored ), 'success: four documents recorded' );
$dir = CemetiWP::$uploads . '/cemeti-applications';
ok( is_file( "$dir/.htaccess" ) && false !== strpos( file_get_contents( "$dir/.htaccess" ), 'Require all denied' ) && is_file( "$dir/index.php" ), 'success: upload folder is protected' );
foreach ( $stored as $k => $f ) {
	ok( is_file( "$dir/{$f['file']}" ) && false !== strpos( $f['file'], $f['name'] ) && strlen( $f['file'] ) > strlen( $f['name'] ) + 16, "success: $k stored under a random name" );
}
ok( 2 === count( CemetiWP::$mails ), 'success: two e-mails' );
$team = CemetiWP::$mails[0];
$ack  = CemetiWP::$mails[1];
ok( 'admin@cemeti.test' === $team['to'] && false !== strpos( $team['body'], 'rina@example.org' ) && false !== strpos( $team['subject'], 'New application' ), 'success: the team is notified (admin e-mail by default)' );
ok( 'rina@example.org' === $ack['to'] && false !== strpos( $ack['body'], 'Rina Seniman' ) && false !== strpos( $ack['body'], 'SEA Artist Residency Program' ), 'success: the applicant gets a confirmation' );
ok( false !== strpos( $ack['body'], 'Halo' ) || false !== strpos( $ack['body'], 'Hello' ), 'success: confirmation is a real sentence, not a placeholder' );
$reply = implode( ' ', $team['headers'] );
ok( false !== strpos( $reply, 'Reply-To: Rina Seniman <rina@example.org>' ), 'success: team mail can be answered directly' );
update_option( 'cemeti_settings_id', array( 'notify_email' => 'team@cemeti.test' ) );
CemetiWP::$mails = array();
$reset_rate();
$submit( $form( array( 'app_email' => 'b@example.org' ) ), $all() );
ok( 'team@cemeti.test' === CemetiWP::$mails[0]['to'], 'settings: the notification address is used when set' );
update_option( 'cemeti_settings_id', array() );

/* ------------------------------------------------------------- failures */
$expect = static function ( $label, $want, array $post, array $files ) use ( $submit, $applications, $reset_rate ) {
	$reset_rate();
	$before = count( $applications() );
	list( $code ) = $submit( $post, $files );
	ok( $want === $code, "$label: $want (got $code)" );
	ok( count( $applications() ) === $before, "$label: nothing stored" );
};
$expect( 'missing name', 'required', $form( array( 'app_name' => '  ' ) ), $all() );
$expect( 'missing email', 'required', $form( array( 'app_email' => '' ) ), $all() );
$expect( 'bad email', 'email', $form( array( 'app_email' => 'not-an-email' ) ), $all() );
$f = $all(); $f['doc_cv'] = $none;
$expect( 'missing CV', 'required', $form(), $f );
$f = $all(); $bad = "$tmp/bad.pdf"; file_put_contents( $bad, 'MZ not a pdf' ); $f['doc_portfolio'] = array( 'name' => 'portfolio.pdf', 'tmp_name' => $bad, 'size' => filesize( $bad ), 'error' => 0 );
$expect( 'executable renamed .pdf', 'file', $form(), $f );
$f = $all(); $f['doc_proposal']['name'] = 'proposal.exe';
$expect( 'wrong extension', 'file', $form(), $f );
add_filter( 'cemeti_application_max_file', static function () { return 10; } );
$expect( 'file too large', 'file', $form(), $all() );
CemetiWP::$filters['cemeti_application_max_file'] = array();
$f = $all(); $f['doc_motivation']['error'] = UPLOAD_ERR_PARTIAL;
$expect( 'interrupted upload', 'file', $form(), $f );
$expect( 'honeypot filled', 'spam', $form( array( 'cemeti_website' => 'http://spam.example' ) ), $all() );
$expect( 'form submitted too fast', 'spam', $form( array( 'cemeti_ts' => cemeti_form_token( time() ) ) ), $all() );
$expect( 'forged token', 'spam', $form( array( 'cemeti_ts' => ( time() - 30 ) . '.abc' ) ), $all() );
$expect( 'stale form', 'spam', $form( array( 'cemeti_ts' => cemeti_form_token( time() - 99999 ) ) ), $all() );
$expect( 'wrong nonce', 'spam', $form( array( '_cemeti_nonce' => 'nope' ) ), $all() );
$expect( 'closed open call', 'closed', $form( array( 'open_call' => $ids['oc_closed'] ) ), $all() );
$expect( 'target is not an open call', 'spam', $form( array( 'open_call' => $ids['ex_current'] ) ), $all() );
$expect( 'target does not exist', 'spam', $form( array( 'open_call' => 999999 ) ), $all() );
CemetiWP::$posts[ $ids['oc_open'] ]['post_status'] = 'draft';
$expect( 'unpublished open call', 'closed', $form(), $all() );
CemetiWP::$posts[ $ids['oc_open'] ]['post_status'] = 'publish';
$f = $all(); unset( $f['doc_cv'] );
$expect( 'no file field at all', 'required', $form(), $f );

// A document the Open Call does not ask for is ignored, not stored.
$reset_rate();
list( $code ) = $submit( $form( array( 'open_call' => $ids['oc_nodocs'] ) ), $all() );
$last = array_slice( $applications(), -1, 1, true );
ok( 'sent' === $code && array() === get_post_meta( key( $last ), 'app_files', true ), 'documents = none: accepted, uploads ignored' );
CemetiWP::$posts[ $ids['oc_open'] ]['meta'] = null;
CemetiWP::$meta[ $ids['oc_open'] ]['cemeti_documents'] = 'cv, proposal';
$reset_rate();
$f = array( 'doc_cv' => $pdf( 'cv' ), 'doc_proposal' => $pdf( 'proposal' ) );
list( $code ) = $submit( $form(), $f );
$last = array_slice( $applications(), -1, 1, true );
ok( 'sent' === $code && array( 'cv', 'proposal' ) === array_keys( get_post_meta( key( $last ), 'app_files', true ) ), 'documents = "cv, proposal": only those two required and kept' );
unset( CemetiWP::$meta[ $ids['oc_open'] ]['cemeti_documents'] );

/* ---------------------------------------------------- abuse and rate limit */
$reset_rate();
$codes = array();
for ( $i = 0; $i < 7; $i++ ) {
	list( $c ) = $submit( $form( array( 'app_email' => 'x' ) ), $all() );
	$codes[] = $c;
}
ok( array( 'email', 'email', 'email', 'email', 'email', 'rate', 'rate' ) === $codes, 'rate limit: the sixth attempt within the hour is refused: ' . implode( ',', $codes ) );
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
list( $c ) = $submit( $form(), $all() );
ok( 'sent' === $c, 'rate limit: another visitor is not affected' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$reset_rate();

$before = CemetiWP::$mails;
$submit( $form( array( 'app_name' => "Eve\r\nBcc: victim@example.org", 'app_email' => 'eve@example.org' ) ), $all() );
$m = end( CemetiWP::$mails );
$all_headers = implode( "\n", CemetiWP::$mails[ count( $before ) ]['headers'] );
ok( false === strpos( $all_headers, "\r" ) && 1 === substr_count( $all_headers, "\n" ) + 1, 'header injection: a name with line breaks cannot add mail headers' );
$reset_rate();
$submit( $form( array( 'app_name' => '<script>alert(1)</script>Rina', 'app_message' => '<img src=x onerror=alert(1)>pesan', 'app_link' => 'javascript:alert(1)' ) ), $all() );
$last = array_slice( $applications(), -1, 1, true );
$id = key( $last );
ok( false === strpos( get_post_meta( $id, 'app_name', true ), '<' ) && false === strpos( get_post_meta( $id, 'app_message', true ), '<' ) && '' === get_post_meta( $id, 'app_link', true ), 'stored values are plain text and the link is http(s) only' );

// A failing disk: the second file cannot be moved. Nothing is left behind.
$reset_rate();
$moves = 0;
CemetiWP::$filters['cemeti_pre_move_upload'] = array();
add_filter( 'cemeti_pre_move_upload', static function ( $pre, $from, $to ) use ( &$moves ) { return ++$moves < 2 ? copy( $from, $to ) : false; }, 10, 3 );
$count = count( $applications() );
$files_before = glob( "$dir/*" );
list( $c ) = $submit( $form( array( 'app_email' => 'disk@example.org' ) ), $all() );
ok( 'server' === $c && count( $applications() ) === $count && glob( "$dir/*" ) === $files_before, 'disk failure: error shown, no entry, the first file is cleaned up' );
CemetiWP::$filters['cemeti_pre_move_upload'] = array();
add_filter( 'cemeti_pre_move_upload', static function ( $pre, $from, $to ) { return copy( $from, $to ); }, 10, 3 );

/* -------------------------------------------------------------- download */
$first = array_key_first( array_filter( CemetiWP::$posts, static function ( $p ) { return 'application' === $p['post_type']; } ) );
$path  = cemeti_application_file_path( $first, 'cv' );
ok( false !== $path && 0 === strpos( file_get_contents( $path ), '%PDF-' ), 'download: path of a stored file resolves' );
ok( false === cemeti_application_file_path( $first, 'nothing' ), 'download: unknown document' );
CemetiWP::$meta[ $first ]['app_files']['cv']['file'] = '../../../../etc/passwd';
ok( false === cemeti_application_file_path( $first, 'cv' ), 'download: a tampered path outside the folder is refused' );
CemetiWP::$meta[ $first ]['app_files']['cv']['file'] = '../cemeti-applications/../cemeti-applications/' . basename( $path );
ok( false !== cemeti_application_file_path( $first, 'cv' ), 'download: dot segments that stay inside the folder are tolerated' );
$_GET = array( 'app' => $first, 'doc' => 'cv', '_wpnonce' => 'valid' );
CemetiWP::$caps = false;
try {
	do_action( 'admin_post_cemeti_download' );
	ok( false, 'download: non-administrators are refused' );
} catch ( CemetiDie $e ) {
	ok( true, 'download: non-administrators are refused' );
}
CemetiWP::$caps = true;
$_GET = array( 'app' => $ids['ex_current'], 'doc' => 'cv', '_wpnonce' => 'valid' );
try {
	do_action( 'admin_post_cemeti_download' );
	ok( false, 'download: only applications can be downloaded' );
} catch ( CemetiDie $e ) {
	ok( true, 'download: only applications can be downloaded' );
}
$_GET = array( 'app' => $first, 'doc' => 'cv' );
try {
	do_action( 'admin_post_cemeti_download' );
	ok( false, 'download: a link without the nonce is refused' );
} catch ( CemetiDie $e ) {
	ok( true, 'download: a link without the nonce is refused' );
}
$_GET = array();

/* ------------------------------------------------- deleting an application */
$victim = cemeti_application_file_path( $first, 'cv' );
CemetiWP::$meta[ $first ]['app_files']['cv']['file'] = basename( $victim );
$paths = array_filter( array_map( static function ( $d ) use ( $first ) { return cemeti_application_file_path( $first, $d ); }, array_keys( get_post_meta( $first, 'app_files', true ) ) ) );
ok( count( $paths ) >= 1 && is_file( $paths[ array_key_first( $paths ) ] ), 'delete: the PDFs exist before' );
do_action( 'before_delete_post', $first );
ok( ! array_filter( $paths, 'is_file' ), 'delete: removing the entry removes its PDFs from disk' );
do_action( 'before_delete_post', $ids['ex_current'] );
ok( true, 'delete: other post types are ignored' );
$reset_rate();
$submit( $form( array( 'app_email' => 'admin-view@example.org' ) ), $all() );
$second = array_key_last( array_filter( CemetiWP::$posts, static function ( $p ) { return 'application' === $p['post_type']; } ) );

/* -------------------------------------------------------- admin display */
do_action( 'add_meta_boxes' );
ob_start();
call_user_func( CemetiWP::$meta_boxes['application']['cemeti_application'], CemetiWP::obj( $second ) );
$box = ob_get_clean();
ok( false !== strpos( $box, 'Rina Seniman' ) && false !== strpos( $box, 'action=cemeti_download' ) && false !== strpos( $box, '_wpnonce=valid' ), 'admin: the entry shows details and nonce-protected download links' );
ok( false === strpos( $box, '<script' ), 'admin: output is escaped' );
ob_start();
cemeti_application_column_value( 'open_call', $second );
cemeti_application_column_value( 'email', $second );
$cols_html = ob_get_clean();
ok( false !== strpos( $cols_html, 'admin-view@example.org' ) && false !== strpos( $cols_html, 'SEA Artist Residency Program' ), 'admin: list columns show the Open Call and the e-mail' );
$cols = cemeti_application_columns( array( 'cb' => '<input>', 'title' => 'Title', 'date' => 'Date' ) );
ok( isset( $cols['email'], $cols['open_call'] ), 'admin: list has Open Call and Email columns' );

foreach ( glob( "$tmp/*" ) as $f ) { @unlink( $f ); }
@rmdir( $tmp );
qa_done();
