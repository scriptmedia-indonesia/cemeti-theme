<?php
// Run: php tests/flows-content.php — content model, fields, status, relations, What's on, queries, settings, SEO, security, redirects.
require __DIR__ . '/harness/bootstrap.php';
qa_boot();

/* ------------------------------------------------------- content model */
$types = CemetiWP::$post_types;
foreach ( array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal', 'person', 'application' ) as $t ) {
	ok( isset( $types[ $t ] ), "type registered: $t" );
	ok( strlen( $t ) <= 20, "type key within WordPress limit: $t" );
}
ok( 'public-program' === $types['public_program']['rewrite']['slug'] && 'public-program' === $types['public_program']['has_archive'], 'public_program uses the /public-program/ URL' );
ok( false === $types['person']['public'] ? true : empty( $types['person']['public'] ), 'person is not public' );
ok( false === $types['application']['has_archive'] && false === $types['application']['rewrite'], 'application has no front-end URL' );
ok( isset( CemetiWP::$taxonomies['exhibition_type'], CemetiWP::$taxonomies['project'], CemetiWP::$taxonomies['journal_category'] ), 'taxonomies registered' );
ok( false === CemetiWP::$taxonomies['person_group'][1]['public'], 'person_group is not public' );
foreach ( array( 'exhibition_type' => array( 'Solo', 'Group' ), 'residency_type' => array( 'Regular', 'Research' ), 'journal_category' => array( 'News', 'Essay', 'Curatorial Essay' ), 'person_group' => array( 'Advisory Board', 'Team' ) ) as $tax => $names ) {
	foreach ( $names as $n ) {
		ok( null !== term_exists( $n, $tax ), "default term seeded: $tax / $n" );
	}
}
$count = count( CemetiWP::$terms['exhibition_type'] );
do_action( 'init' );
ok( count( CemetiWP::$terms['exhibition_type'] ) === $count, 'seeding the terms twice does not duplicate them' );
$flushed = CemetiWP::$options['_flushed'];
do_action( 'init' );
ok( CemetiWP::$options['_flushed'] === $flushed, 'rewrite rules are flushed once per theme version, not on every request' );
CemetiWP::$options['cemeti_rewrite_version'] = '0.0.1';
do_action( 'init' );
ok( CemetiWP::$options['_flushed'] === $flushed + 1 && CemetiWP::$options['cemeti_rewrite_version'] === CEMETI_VERSION, 'a theme update triggers one flush' );
ok( preg_match( '/^\d+\.\d+\.\d+$/', CEMETI_VERSION ) === 1, 'version read from style.css: ' . CEMETI_VERSION );
$pll = apply_filters( 'pll_get_post_types', array() );
ok( isset( $pll['exhibition'] ) && ! isset( $pll['application'] ), 'Polylang: content is translatable, applications are not' );

/* ------------------------------------------------------------- field types: list, file */
$list = array( 'type' => 'list' );
ok( "Maharani Mancanagara\nSuvi Wahyudianto" === cemeti_sanitize_field( $list, array( 'Maharani Mancanagara', '', '  ', 'Suvi Wahyudianto', 'Maharani Mancanagara' ) ), 'list: blank rows and repeats dropped, order kept' );
ok( "A\nB" === cemeti_sanitize_field( $list, "A\r\n\r\nB" ), 'list: old newline text still accepted' );
ok( false === strpos( cemeti_sanitize_field( $list, array( '<b>x</b>' ) ), '<' ), 'list: markup stripped' );
ok( 40 === count( explode( "\n", cemeti_sanitize_field( $list, array_map( static function ( $n ) { return "n$n"; }, range( 1, 80 ) ) ) ) ), 'list: at most 40 names' );
$file = array( 'type' => 'file' );
ok( 901 === cemeti_sanitize_field( $file, '901' ), 'file: a PDF attachment is kept' );
ok( 0 === cemeti_sanitize_field( $file, '5' ), 'file: an image is refused' );
ok( 0 === cemeti_sanitize_field( $file, array( '901' ) ) && 0 === cemeti_sanitize_field( $file, 'x' ), 'file: junk refused' );
$bios = cemeti_field_def( 'exhibition', 'biographies' );
$rows = cemeti_sanitize_field( $bios, array( 7 => array( 'name' => 'Zed', 'photo' => '5', 'bio' => 'Z' ), 2 => array( 'name' => 'Abe', 'photo' => '', 'bio' => "Line1\nLine2" ) ) );
ok( 'Zed' === $rows[0]['name'] && 'Abe' === $rows[1]['name'] && array( 0, 1 ) === array_keys( $rows ), 'repeater: rows keep the order they were submitted in' );

/* ------------------------------------------------------------- fields */
$ids = qa_seed();
do_action( 'add_meta_boxes' );
foreach ( array_keys( cemeti_config( 'fields' ) ) as $type ) {
	ok( isset( CemetiWP::$meta_boxes[ $type ]['cemeti_details'] ), "meta box registered for $type" );
	$sample = CemetiWP::post( array( 'post_type' => $type, 'post_title' => "Sample $type" ) );
	ob_start();
	call_user_func( CemetiWP::$meta_boxes[ $type ]['cemeti_details'], CemetiWP::obj( $sample ) );
	$html = ob_get_clean();
	ok( false !== strpos( $html, 'cemeti_fields_nonce' ) && false !== strpos( $html, 'name="cemeti_fields[' ), "meta box renders for $type" );
	$problems = qa_check_html( '<html><body><h1>x</h1><h2>Details</h2>' . $html . '</body></html>' );
	$problems = array_filter( $problems, static function ( $p ) { return 0 !== strpos( $p, 'form control without a label' ) && false === strpos( $p, 'img without alt' ); } );
	ok( ! $problems, "meta box markup for $type is well formed: " . implode( '; ', $problems ) );
}
// Repeater: the hidden template must not be submitted twice and must carry the index placeholder.
ob_start();
call_user_func( CemetiWP::$meta_boxes['exhibition']['cemeti_details'], CemetiWP::obj( $ids['ex_current'] ) );
$box = ob_get_clean();
ok( 3 === substr_count( $box, '<div class="cemeti-repeater__row"' ), 'repeater: two stored rows plus one hidden template' );
ok( false !== strpos( $box, '__INDEX__' ), 'repeater: template placeholder present' );
ok( false === strpos( $box, 'value="20260306"' ) && false !== strpos( $box, 'value="2026-03-06"' ), 'date field shows Y-m-d' );

// Saving through the real save_post hook.
$post = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'New' ) );
$post_save = static function ( $id, array $fields, $nonce = 'valid' ) {
	$_POST = array( 'cemeti_fields_nonce' => $nonce, 'cemeti_fields' => $fields );
	do_action( 'save_post', $id );
	$_POST = array();
};
$post_save( $post, array( 'start_date' => '2026-05-01', 'end_date' => '', 'artists' => "A\nB", 'gallery' => '5,5,6', 'public_program_info' => '<b>x</b>', 'booklet_url' => 'javascript:alert(1)', 'seo_description' => 'Deskripsi' ) );
ok( '20260501' === get_post_meta( $post, 'cemeti_start_date', true ), 'save: date stored as Ymd' );
ok( '20260501' === get_post_meta( $post, 'cemeti_end_date', true ), 'save: empty end date = start date' );
ok( array( 5, 6 ) === get_post_meta( $post, 'cemeti_gallery', true ), 'save: image IDs unique and ordered' );
ok( 'x' === get_post_meta( $post, 'cemeti_public_program_info', true ), 'save: HTML stripped from text' );
ok( '' === get_post_meta( $post, 'cemeti_booklet_url', true ), 'save: javascript: URL rejected' );
$post_save( $post, array( 'start_date' => '2026-05-10', 'end_date' => '2026-05-01' ) );
ok( '20260510' === get_post_meta( $post, 'cemeti_end_date', true ), 'save: end before start is corrected to start' );
ok( false === array_key_exists( 'cemeti_artists', CemetiWP::$meta[ $post ] ), 'save: emptied field deletes its meta' );
$before = CemetiWP::$meta[ $post ];
$post_save( $post, array( 'start_date' => '2030-01-01' ), 'forged' );
ok( CemetiWP::$meta[ $post ] === $before, 'save: wrong nonce changes nothing' );
CemetiWP::$caps = false;
$post_save( $post, array( 'start_date' => '2030-01-01' ) );
ok( CemetiWP::$meta[ $post ] === $before, 'save: user without permission changes nothing' );
CemetiWP::$caps = true;
$_POST = array();
do_action( 'save_post', $post );
ok( CemetiWP::$meta[ $post ] === $before, 'save: a request without the nonce (quick edit, API) leaves fields alone' );

// Two-way related, max 4.
$a = CemetiWP::post( array( 'post_type' => 'journal', 'post_title' => 'A' ) );
$others = array();
for ( $i = 0; $i < 6; $i++ ) {
	$others[] = CemetiWP::post( array( 'post_type' => 'journal', 'post_title' => "R$i" ) );
}
$post_save( $a, array( 'related' => $others ) );
ok( 4 === count( get_post_meta( $a, 'cemeti_related', true ) ), 'related: at most four kept' );
foreach ( get_post_meta( $a, 'cemeti_related', true ) as $r ) {
	ok( in_array( $a, get_post_meta( $r, 'cemeti_related', true ), true ), "related: link mirrored on $r" );
}
$kept = get_post_meta( $a, 'cemeti_related', true );
$post_save( $a, array( 'related' => array_slice( $kept, 0, 2 ) ) );
ok( ! in_array( $a, (array) get_post_meta( $kept[3], 'cemeti_related', true ), true ), 'related: removed link disappears on the other side' );
wp_delete_post_stub( $a );
function wp_delete_post_stub( $id ) { do_action( 'before_delete_post', $id ); unset( CemetiWP::$posts[ $id ] ); }
foreach ( array_slice( $kept, 0, 2 ) as $r ) {
	ok( ! in_array( $a, (array) get_post_meta( $r, 'cemeti_related', true ), true ), 'related: deleting a post unlinks it everywhere' );
}

/* ---------------------------------------------------------- status */
qa_reset_data();
$ids = qa_seed();
$status = static function ( $post, $today ) { CemetiWP::$today = $today; return cemeti_get_status( $post ); };
ok( 'current' === $status( $ids['ex_current'], '20260315' ), 'status: current' );
ok( 'current' === $status( $ids['ex_current'], '20260306' ) && 'current' === $status( $ids['ex_current'], '20260418' ), 'status: first and last day are included' );
ok( 'upcoming' === $status( $ids['ex_current'], '20260305' ), 'status: the day before is upcoming' );
ok( 'previous' === $status( $ids['ex_current'], '20260419' ), 'status: the day after is previous' );
ok( '' === $status( $ids['ex_nodate'], '20260315' ), 'status: no dates, no status' );
ok( '' === $status( $ids['j_news'], '20260315' ), 'status: journal has none' );
CemetiWP::$today = '20260315';
foreach ( array( 'current' => array( $ids['ex_current'] ), 'upcoming' => array( $ids['ex_future'] ), 'previous' => array( $ids['ex_past'] ) ) as $st => $expect ) {
	$q = cemeti_status_query( 'exhibition', $st );
	ok( wp_list_pluck_ids( $q->posts ) === $expect, "status query $st: " . implode( ',', wp_list_pluck_ids( $q->posts ) ) );
}
function wp_list_pluck_ids( $posts ) { return array_map( static function ( $p ) { return $p->ID; }, $posts ); }
$q = cemeti_status_query( 'open_call', 'current' );
ok( array( $ids['oc_open'], $ids['oc_external'], $ids['oc_nodocs'] ) === wp_list_pluck_ids( $q->posts ), 'open calls before the deadline, soonest first' );
ok( array( $ids['oc_closed'] ) === wp_list_pluck_ids( cemeti_status_query( 'open_call', 'previous' )->posts ), 'open calls after the deadline' );
ok( array() === cemeti_status_query( 'journal', 'current' )->posts, 'status query on a dateless type returns nothing and does not crash' );
ok( $ids['ex_current'] === cemeti_featured_post( 'exhibition' )->ID, 'featured: the current one' );
CemetiWP::$today = '20260101';
ok( $ids['ex_current'] === cemeti_featured_post( 'exhibition' )->ID, 'featured: else the soonest upcoming' );
CemetiWP::$today = '20270101';
ok( $ids['ex_future'] === cemeti_featured_post( 'exhibition' )->ID, 'featured: else the latest previous' );
CemetiWP::$today = '20260315';
ok( $ids['j_news'] === cemeti_featured_post( 'journal' )->ID, 'featured: journal = newest' );
ok( 'Current' === cemeti_status_label( 'current' ) && 'Upcoming' === cemeti_status_label( 'upcoming' ) && 'Previous' === cemeti_status_label( 'previous' ) && '' === cemeti_status_label( 'bogus' ), 'status labels (public helper)' );

/* ------------------------------------------------------------ What's on */
$items = apply_filters( 'cemeti_whats_on_items', array() );
$titles = array_column( $items, 'title' );
ok( array( 'Harta yang Paling Berharga adalah Keluarga', 'Open Studio: Angki Purbandono', 'Residency Program 2026' ) === $titles, "What's on: current exhibition, open studio, residency: " . implode( ' | ', $titles ) );
ok( 'Exhibition' === $items[0]['label'] && '' !== $items[0]['meta'] && 0 === strpos( $items[0]['url'], 'http' ), "What's on: label, dates and url" );
CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Baru', 'meta' => array( 'cemeti_start_date' => '20260301', 'cemeti_end_date' => '20260401' ) ) );
ok( 'Harta yang Paling Berharga adalah Keluarga' === apply_filters( 'cemeti_whats_on_items', array() )[0]['title'], "What's on: served from the cache until content changes" );
do_action( 'save_post', $ids['ex_current'] );
ok( 'Baru' === apply_filters( 'cemeti_whats_on_items', array() )[0]['title'], "What's on: refreshed after a save (the exhibition ending first is shown)" );
CemetiWP::$today = '20250101';
do_action( 'save_post', $ids['ex_current'] );
ok( array() === apply_filters( 'cemeti_whats_on_items', array() ), "What's on: nothing is on in 2025" );
CemetiWP::$today = '20260315';
do_action( 'save_post', $ids['ex_current'] );

/* ------------------------------------------------------ main-query rules */
$run = static function ( array $vars, array $flags, array $get = array() ) {
	$_GET = $get;
	$q = new WP_Query();
	$q->query_vars = $vars;
	$q->flags = array_merge( array( 'main' => true ), $flags );
	do_action( 'pre_get_posts', $q );
	$q->run();
	return $q;
};
$q = $run( array( 'post_type' => 'exhibition' ), array( 'pta' => 'exhibition' ) );
ok( 12 === $q->get( 'posts_per_page' ), 'archive: 12 per page' );
$q = $run( array( 'post_type' => 'exhibition' ), array( 'pta' => 'exhibition' ), array( 'type' => 'group' ) );
ok( array( $ids['ex_current'] ) === wp_list_pluck_ids( $q->posts ), 'archive ?type=group filters by exhibition type' );
foreach ( CemetiWP::$terms['exhibition_type'] as $tid => $t ) {
	if ( 'Group' === $t['name'] ) {
		CemetiWP::$terms['exhibition_type'][ $tid ]['slug'] = 'group-en'; // Polylang's name for a second-language copy.
	}
}
$q = $run( array( 'post_type' => 'exhibition' ), array( 'pta' => 'exhibition' ), array( 'type' => 'group' ) );
ok( array( $ids['ex_current'] ) === wp_list_pluck_ids( $q->posts ), 'archive ?type=group still works when Polylang made the slug "group-en"' );
$q = $run( array( 'post_type' => 'exhibition' ), array( 'pta' => 'exhibition' ), array( 'type' => "group' OR 1=1" ) );
ok( array() === $q->posts, 'archive ?type= with SQL-looking text matches nothing and is sanitized' );
$q = $run( array( 'post_type' => 'residency' ), array( 'pta' => 'residency' ), array( 'type' => 'regular' ) );
ok( array( $ids['res_current'] ) === wp_list_pluck_ids( $q->posts ), 'residency ?type=regular' );
$q = $run( array( 'post_type' => 'journal' ), array( 'pta' => 'journal' ) );
ok( 20 === $q->get( 'posts_per_page' ), 'journal: 20 per page' );
$q = $run( array( 's' => 'x' ), array( 'search' => true ) );
ok( in_array( 'open_call', $q->get( 'post_type' ), true ) && ! in_array( 'person', $q->get( 'post_type' ), true ) && ! in_array( 'application', $q->get( 'post_type' ), true ), 'search: content types only, never people or applications' );
$entries = cemeti_archive_entries();
ok( 10 === count( $entries ), 'open archive: 5 exhibitions, 2 residencies, 2 public programs, 1 open studio (' . count( $entries ) . ' entries)' );
ok( ! in_array( 'Rangkaian Perayaan #37thCemeti', array_column( $entries, 'title' ), true ), 'open archive: journal excluded' );

/* ------------------------------------------------------------ Open Call: one call, switched on and off by the team */
$current = cemeti_current_open_call();
ok( $current && cemeti_open_call_is_open( $current->ID ), 'open call page: the call shown is an active one (' . ( $current ? $current->post_title : 'none' ) . ')' );
$newest_open = $current->ID;
foreach ( array( 'oc_open', 'oc_external', 'oc_nodocs' ) as $k ) {
	update_post_meta( $ids[ $k ], 'cemeti_active', '0' );
}
$none = cemeti_current_open_call();
ok( $none && ! cemeti_open_call_is_open( $none->ID ), 'no active call: the newest call is shown with its closed notice' );
update_post_meta( $ids['oc_closed'], 'cemeti_active', '1' );
$forced = cemeti_current_open_call();
ok( $ids['oc_closed'] === $forced->ID && cemeti_open_call_is_open( $ids['oc_closed'] ), 'a call ticked Active is open even past its deadline (the team decides)' );
foreach ( array( 'oc_open', 'oc_external', 'oc_nodocs', 'oc_closed' ) as $k ) {
	delete_post_meta( $ids[ $k ], 'cemeti_active' );
}
ok( cemeti_open_call_is_open( $ids['oc_open'] ) && ! cemeti_open_call_is_open( $ids['oc_closed'] ), 'never touched: open until the deadline, as before' );
// The tick in the editor: unticked is stored as 0, not forgotten.
$def = cemeti_field_def( 'open_call', 'active' );
ok( '1' === cemeti_sanitize_field( $def, '1' ) && '0' === cemeti_sanitize_field( $def, '0' ) && '0' === cemeti_sanitize_field( $def, '' ) && '0' === cemeti_sanitize_field( $def, 'yes' ), 'checkbox: only "1" ticks it' );
CemetiWP::$caps = true;
$post_save( $ids['oc_open'], array( 'deadline' => '2026-08-14', 'active' => '0' ) );
ok( '0' === get_post_meta( $ids['oc_open'], 'cemeti_active', true ) && ! cemeti_open_call_is_open( $ids['oc_open'] ), 'saving with the tick off closes the call' );
$post_save( $ids['oc_open'], array( 'deadline' => '2026-08-14', 'active' => '1' ) );
ok( cemeti_open_call_is_open( $ids['oc_open'] ), 'saving with the tick on opens it again' );
do_action( 'add_meta_boxes' );
ob_start();
call_user_func( CemetiWP::$meta_boxes['open_call']['cemeti_details'], CemetiWP::obj( $ids['oc_closed'] ) );
$box = ob_get_clean();
ok( false !== strpos( $box, 'type="checkbox"' ) && false !== strpos( $box, 'name="cemeti_fields[active]"' ) && false === strpos( $box, 'value="1" checked' ), 'editor: the Active tick is there, unticked for a call past its deadline' );
// List column and the one-click action.
ok( isset( cemeti_open_call_columns( array( 'title' => 'Title', 'date' => 'Date' ) )['cemeti_active'] ), 'admin list: an Active column' );
ob_start(); cemeti_open_call_column_content( 'cemeti_active', $ids['oc_closed'] ); $cell = ob_get_clean();
ok( false !== strpos( $cell, 'Closed' ), 'admin list: shows Closed for a call past its deadline' );
$actions = cemeti_open_call_row_actions( array(), CemetiWP::obj( $ids['oc_open'] ) );
ok( isset( $actions['cemeti_toggle'] ) && false !== strpos( $actions['cemeti_toggle'], 'Close call' ), 'admin list: "Close call" under an active call' );
$actions = cemeti_open_call_row_actions( array(), CemetiWP::obj( $ids['oc_closed'] ) );
ok( false !== strpos( $actions['cemeti_toggle'], 'Reopen call' ), 'admin list: "Reopen call" under a closed one' );
$_GET = $_REQUEST = array( 'post' => $ids['oc_open'], 'to' => '0', '_wpnonce' => 'valid' );
try { cemeti_handle_toggle_call(); ok( false, 'toggle redirects' ); } catch ( CemetiRedirect $r ) { ok( ! cemeti_open_call_is_open( $ids['oc_open'] ) && false !== strpos( $r->url, 'post_type=open_call' ), 'one-click Close: the call is closed and the list opens again' ); }
$_GET = $_REQUEST = array( 'post' => $ids['oc_open'], 'to' => '1', '_wpnonce' => 'valid' );
try { cemeti_handle_toggle_call(); } catch ( CemetiRedirect $r ) { ok( cemeti_open_call_is_open( $ids['oc_open'] ), 'one-click Reopen: open again' ); }
CemetiWP::$caps = false;
try { cemeti_handle_toggle_call(); ok( false, 'toggle needs permission' ); } catch ( CemetiDie $x ) { ok( true, 'one-click toggle needs permission' ); }
CemetiWP::$caps = true;
$_GET = $_REQUEST = array( 'post' => $ids['p_contact'], 'to' => '0', '_wpnonce' => 'valid' );
try { cemeti_handle_toggle_call(); ok( false, 'toggle only for open calls' ); } catch ( CemetiDie $x ) { ok( true, 'one-click toggle works only on open calls' ); }
$_GET = $_REQUEST = array();

/* ------------------------------------------------------------ language links pasted into old entries */
ok( '<p>Real text</p>' === cemeti_strip_language_links( '<p><a href="/en/x">English</a> | <a href="/x">Indonesia</a></p><p>Real text</p>' ), 'old "English | Indonesia" links are removed' );
ok( '<p>Real text</p>' === cemeti_strip_language_links( '<p><strong>Indonesia</strong> – <strong>English</strong></p><p>Real text</p>' ), 'also with a dash and bold' );
ok( '<p>Real text</p>' === cemeti_strip_language_links( '<p>English | Indonesia</p><p>Real text</p>' ), 'also as plain text' );
$keep = '<p>She studied English | and Indonesia is where she lives.</p>';
ok( $keep === cemeti_strip_language_links( $keep ), 'a sentence that merely mentions the words is kept' );
CemetiWP::request( '/exhibition/x/', array( 'singular' => true ) );
$GLOBALS['post'] = CemetiWP::obj( $ids['ex_current'] );
$shown = apply_filters( 'the_content', '<p><a href="/en/">English</a> | <a href="/">Indonesia</a></p><p>Real</p>' );
ok( false === strpos( $shown, 'English' ) && false !== strpos( $shown, 'Real' ), 'the text of an entry is cleaned when shown (the_content filter)' );
CemetiWP::request( '/', array() );
$GLOBALS['post'] = null;
ok( false !== strpos( cemeti_share_icon( 'WhatsApp' ), 'aria-hidden="true"' ) && '' === cemeti_share_icon( 'Nope' ), 'share icons are decorative; unknown app gives none' );

/* ------------------------------------------------------------ Solo / Group */
$mk = static function ( $title, $artists, $terms = array() ) {
	$id = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => $title, 'meta' => '' === $artists ? array() : array( 'cemeti_artists' => $artists ) ) );
	if ( $terms ) {
		wp_set_object_terms( $id, $terms, 'exhibition_type' );
	}
	do_action( 'save_post', $id );
	return $id;
};
$type_of = static function ( $id ) {
	$t = get_the_terms( $id, 'exhibition_type' );
	return $t ? $t[0]->name : '';
};
ok( 'Solo' === $type_of( $mk( 'Pameran Tunggal Enka Komariah', '' ) ), 'type: "Pameran Tunggal" in the title means Solo' );
ok( 'Group' === $type_of( $mk( 'Pameran Kelompok: Dalam Gelap', "A\nB" ) ), 'type: "Pameran Kelompok" means Group' );
ok( 'Solo' === $type_of( $mk( 'Solo Exhibition: X', '' ) ) && 'Group' === $type_of( $mk( 'Group Exhibition: Y', '' ) ), 'type: English titles work too' );
ok( 'Solo' === $type_of( $mk( 'Untitled show', 'Only One' ) ), 'type: one artist means Solo' );
ok( 'Group' === $type_of( $mk( 'Untitled show', "One\nTwo\n\nThree" ) ), 'type: two or more artists mean Group' );
ok( '' === $type_of( $mk( 'No hint at all', '' ) ), 'type: nothing to go on, nothing set' );
ok( 'Group' === $type_of( $mk( 'Pameran Tunggal but chosen Group', '', array( 'Group' ) ) ), 'type: a choice made by hand is never changed' );
$left = cemeti_exhibitions_without_type();
ok( count( $left ) >= 1 && ! in_array( $mk( 'Pameran Tunggal Z', '' ), $left, true ), 'type: the list of exhibitions without a type' );
$bulk = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Pameran Kelompok Bulk' ) );
CemetiWP::$caps = true;
$_GET = $_REQUEST = array( '_wpnonce' => 'valid' );
try { cemeti_handle_fill_types(); ok( false, 'bulk button redirects' ); } catch ( CemetiRedirect $r ) { ok( 'Group' === $type_of( $bulk ) && false !== strpos( $r->url, 'cemeti_types_filled=' ), 'bulk button: fills the missing types and returns to Settings' ); }
/* ---- entries without a featured image get a picture: the other language's, the gallery / documentation, the text */
CemetiWP::$pictures = array( 8801, 8802, 8811, 8821, 8899 );
$mkp = static function ( $title, $meta = array(), $content = '' ) {
	return CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => $title, 'post_content' => $content, 'meta' => $meta ) );
};
$p_gal = $mkp( 'Has gallery', array( 'cemeti_gallery' => array( 8801, 8802 ) ) );
$p_doc = $mkp( 'Has documentation', array( 'cemeti_documentation' => array( 8811 ) ) );
$p_orig = $mkp( 'Converted', array( '_cemeti_original_content' => '<img src="x.jpg" class="wp-image-8821">' ) );
$p_none = $mkp( 'Nothing at all' );
ok( 8801 === (int) get_post_thumbnail_id( $p_gal ) && 8811 === (int) get_post_thumbnail_id( $p_doc ), 'thumbnail: an entry without a featured image shows the first picture of its gallery, else of its documentation' );
ok( ! get_post_thumbnail_id( $p_none ), 'thumbnail: nothing to show stays empty' );
$p_broken = $mkp( 'Old number', array( '_thumbnail_id' => 4242, 'cemeti_gallery' => array( 8802 ) ) );
$p_broken_none = $mkp( 'Old number, nothing else', array( '_thumbnail_id' => 4343 ) );
ok( 8802 === (int) get_post_thumbnail_id( $p_broken ), 'thumbnail: a featured image that points to a picture which is not in the Media Library counts as none (gallery used instead)' );
ok( ! get_post_thumbnail_id( $p_broken_none ) && ! has_post_thumbnail( $p_broken_none ), 'thumbnail: a broken featured image and nothing else to show: no picture, not a broken one' );
$real = $mkp( 'Has its own', array( '_thumbnail_id' => 8899, 'cemeti_gallery' => array( 8801 ) ) );
ok( 8899 === (int) get_post_thumbnail_id( $real ), 'thumbnail: a real featured image always wins' );
CemetiWP::$caps = true;
$_GET = $_REQUEST = array( '_wpnonce' => 'valid' );
try { cemeti_handle_fill_thumbnails(); } catch ( CemetiRedirect $r ) { ok( false !== strpos( $r->url, 'cemeti_thumbs_filled=' ), 'thumbnail button: returns to Settings with the count' ); }
ok( 8802 === (int) get_post_meta( $p_broken, '_thumbnail_id', true ) && ! get_post_meta( $p_broken_none, '_thumbnail_id', true ), 'thumbnail button: a broken featured image is replaced, or removed when there is nothing to put in its place' );
ok( 8801 === (int) get_post_meta( $p_gal, '_thumbnail_id', true ) && 8899 === (int) get_post_meta( $real, '_thumbnail_id', true ) && ! get_post_meta( $p_none, '_thumbnail_id', true ), 'thumbnail button: stores the picture where one was found and changes nothing else' );
/* ---- one field that cannot be shown must not blank the whole editing box */
$bad = CemetiWP::post( array( 'post_type' => 'residency', 'post_title' => 'Odd data', 'meta' => array( 'cemeti_gallery' => array( array( 'x' ), 'y' ), 'cemeti_artists' => "A\nB" ) ) );
set_error_handler( static function ( $no, $str ) { throw new ErrorException( $str, 0, $no ); } );
ob_start(); cemeti_render_meta_box( CemetiWP::obj( $bad ) ); $box = ob_get_clean();
restore_error_handler();
ok( false !== strpos( $box, 'This field could not be shown' ) && false !== strpos( $box, 'cemeti_fields[artists]' ) && false !== strpos( $box, 'cemeti_fields[documentation]' ), 'editing box: a field with unexpected data says so and the other fields are still shown' );

/* ---- admin screens show what is stored: no stand-in search for every row of a list (it timed the lists out) */
$p_fresh = $mkp( 'Fresh, gallery only', array( 'cemeti_gallery' => array( 8801 ) ) );
CemetiWP::$ctx['admin'] = true;
ok( ! get_post_thumbnail_id( $p_fresh ), 'admin: an entry without a stored featured image shows none in the list, no stand-in is searched' );
ok( 4242 === (int) apply_filters( 'post_thumbnail_id', 4242, $p_broken ), 'admin: even a broken number is left as stored (the front end is where it is replaced)' );
unset( CemetiWP::$ctx['admin'] );

/* ---- the same work in time-boxed steps (a single long request is cut by the host: Cloudflare 524) */
$more = array( $mkp( 'Step A', array( 'cemeti_gallery' => array( 8801 ) ) ), $mkp( 'Step B', array( 'cemeti_gallery' => array( 8802 ) ) ), $mkp( 'Step C, nothing' ) );
$r0 = cemeti_thumbs_step( -1.0, true );
ok( $r0['remaining'] >= 3 && 0 === $r0['filled'] && ! get_post_meta( $more[0], '_thumbnail_id', true ), 'thumbnail steps: when the time is up nothing more is started and the rest is reported' );
$r1 = cemeti_thumbs_step( 30.0 );
ok( 0 === $r1['remaining'] && 8801 === (int) get_post_meta( $more[0], '_thumbnail_id', true ) && 8802 === (int) get_post_meta( $more[1], '_thumbnail_id', true ) && $r1['nothing'] >= 1, 'thumbnail steps: a step with time left finishes, counting the entries that have no picture to use' );
$r2 = cemeti_thumbs_step( 30.0 );
ok( 0 === $r2['remaining'] && $r2['filled'] === $r1['filled'], 'thumbnail steps: running again changes nothing and ends' );
$_POST = $_REQUEST = array( 'nonce' => 'valid', 'fresh' => '1' );
try { cemeti_thumbs_ajax(); } catch ( CemetiJson $j ) { ok( $j->payload['success'] && 0 === $j->payload['data']['remaining'] && isset( $j->payload['data']['report']['filled'] ), 'thumbnail ajax: answers with what is left and the counts' ); }
$_POST = $_REQUEST = array();
try { cemeti_thumbs_ajax(); ok( false, 'thumbnail ajax needs the nonce' ); } catch ( CemetiDie $j ) { ok( true, 'thumbnail ajax needs the nonce' ); } catch ( CemetiJson $j ) { ok( false, 'thumbnail ajax needs the nonce' ); }
CemetiWP::$caps = false;
try { cemeti_handle_fill_thumbnails(); ok( false, 'thumbnail button needs permission' ); } catch ( CemetiDie $e ) { ok( true, 'thumbnail button needs permission' ); }
CemetiWP::$caps = true;
CemetiWP::$caps = false;
try { cemeti_handle_fill_types(); ok( false, 'bulk button needs permission' ); } catch ( CemetiDie $e ) { ok( true, 'bulk button needs permission' ); }
CemetiWP::$caps = true;

/* ------------------------------------------------------------ renamed term and default descriptions */
CemetiWP::$terms['residency_type'] = array( 900 => array( 'name' => 'Reguler', 'slug' => 'reguler', 'description' => '' ) );
update_option( 'cemeti_terms_seeded', '0.0.0' );
do_action( 'init' );
$names = array_column( CemetiWP::$terms['residency_type'], 'name' );
ok( in_array( 'Regular', $names, true ) && ! in_array( 'Reguler', $names, true ) && 2 === count( $names ), 'term "Reguler" is renamed to "Regular", not duplicated (' . implode( ',', $names ) . ')' );
ok( 'regular' === CemetiWP::$terms['residency_type'][900]['slug'] && '' !== CemetiWP::$terms['residency_type'][900]['description'], 'the renamed term keeps its id and gets its default description' );
CemetiWP::$terms['residency_type'][900]['description'] = 'Edited by the team';
update_option( 'cemeti_terms_seeded', '0.0.0' );
do_action( 'init' );
ok( 'Edited by the team' === CemetiWP::$terms['residency_type'][900]['description'], 'an edited description is never overwritten' );

/* ------------------------------------------------------------ pictures of moved entries */
CemetiWP::post( array( 'ID' => 777, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'a', 'meta' => array( '_wp_attached_file' => '2021/06/a.jpg' ) ) );
$old = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Moved from the old site', 'post_content' => '<p>Text</p><img class="alignnone wp-image-777 size-large" src="http://old.test/a.jpg"><img class="wp-image-778">' ) );
ok( has_post_thumbnail( $old ) && 777 === get_post_thumbnail_id( $old ), 'no featured image: the first picture of the text is used' );
$own = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'With cover', 'post_content' => '<img class="wp-image-777">', 'meta' => array( '_thumbnail_id' => 5 ) ) );
ok( 5 === get_post_thumbnail_id( $own ), 'a real featured image always wins' );
$none = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'No picture', 'post_content' => '<p>Only words</p>' ) );
ok( ! has_post_thumbnail( $none ), 'no picture anywhere: still none' );
$page = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Plain page', 'post_content' => '<img class="wp-image-777">' ) );
ok( ! has_post_thumbnail( $page ), 'pages are left alone' );

/* ------------------------------------------------------------ settings */
ok( 'Cemeti – Institut untuk Seni dan Masyarakat' === cemeti_option( 'institution_name' ), 'option: built-in default' );
update_option( 'cemeti_settings_id', array( 'institution_name' => 'Nama ID', 'address' => '' ) );
update_option( 'cemeti_settings_en', array( 'institution_name' => 'Name EN' ) );
ok( 'Name EN' === cemeti_option( 'institution_name' ), 'option: the active language wins (English is the main language)' );
ok( strpos( cemeti_option( 'address' ), 'Jl. DI Panjaitan' ) === 0, 'option: empty value falls back to the built-in default' );
$s = cemeti_sanitize_settings( array( 'institution_name' => '<b>Cemeti</b>', 'email' => 'bad', 'instagram_url' => 'javascript:alert(1)', 'youtube_url' => 'https://youtube.com/x', 'route_url' => 'https://maps.app.goo.gl/x', 'address' => "a\nb", 'unknown' => 'x' ) );
ok( 'Cemeti' === $s['institution_name'] && '' === $s['email'] && '' === $s['instagram_url'] && 'https://youtube.com/x' === $s['youtube_url'] && ! isset( $s['unknown'] ), 'settings: sanitized, unknown keys dropped' );
ok( '' === cemeti_validate_maps_url( 'https://evil.example/maps' ) && '' === cemeti_validate_maps_url( 'http://www.google.com/maps/x' ) && '' !== cemeti_validate_maps_url( 'https://www.google.com/maps/place/x' ) && '' === cemeti_validate_maps_url( 'https://www.google.com/search?q=x' ), 'maps URL: only HTTPS Google Maps accepted' );
ob_start();
cemeti_render_settings_page();
$html = ob_get_clean();
ok( false !== strpos( $html, 'cemeti_settings_id[institution_name]' ) && false !== strpos( $html, 'cemeti_settings_en[notify_email]' ) && false !== strpos( $html, 'option_page' ), 'settings page renders both languages' );
update_option( 'cemeti_settings_id', array() );
update_option( 'cemeti_settings_en', array() );

/* ------------------------------------------------------------------ SEO */
$render_head = static function ( $post, array $ctx ) {
	CemetiWP::request( '/', $ctx );
	$GLOBALS['post'] = $post ? CemetiWP::obj( $post ) : null;
	ob_start();
	do_action( 'wp_head' );
	return ob_get_clean();
};
$head = $render_head( $ids['ex_current'], array( 'singular' => true ) );
ok( false !== strpos( $head, '<meta name="description" content="Pameran kelompok tentang keluarga dan ingatan.">' ), 'seo: the custom description wins' );
ok( false !== strpos( $head, 'og:title' ) && false !== strpos( $head, 'og:image' ) && false !== strpos( $head, 'twitter:card' ), 'seo: Open Graph and Twitter tags' );
preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#s', $head, $m );
$types_ld = array();
foreach ( $m[1] as $json ) {
	$d = json_decode( $json, true );
	ok( null !== $d && 'https://schema.org' === $d['@context'], 'seo: JSON-LD parses' );
	$types_ld[] = is_array( $d['@type'] ) ? $d['@type'][0] : $d['@type'];
}
ok( in_array( 'Event', $types_ld, true ) && in_array( 'BreadcrumbList', $types_ld, true ), 'seo: exhibition gets Event and BreadcrumbList: ' . implode( ',', $types_ld ) );
// A description and a title full of quotes and angle brackets must not break out of the attribute or the page.
$evil = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'A "quoted" <b>title</b>', 'meta' => array( 'cemeti_seo_description' => '"><script>alert(1)</script>', 'cemeti_start_date' => '20260101', 'cemeti_end_date' => '20260102' ) ) );
$head = $render_head( $evil, array( 'singular' => true ) );
$without_json = preg_replace( '#<script type="application/ld\+json">.*?</script>#s', '', $head );
ok( false === strpos( $without_json, '<script' ) && false === strpos( $without_json, '"><' ), 'seo: a hostile description cannot break out of the meta tag' );
preg_match( '/<meta name="description" content="([^"]*)">/', $head, $dm );
ok( isset( $dm[1] ) && false === strpos( $dm[1], '<' ) && false === strpos( $dm[1], 'script' ) && false !== strpos( $dm[1], '&quot;&gt;' ), 'seo: markup is stripped and what is left is escaped: ' . ( $dm[1] ?? 'no tag' ) );
ok( false === strpos( $without_json, '<b>title' ), 'seo: markup in the title is not printed raw in og:title' );
$head = $render_head( $ids['j_essay'], array( 'singular' => true ) );
ok( false !== strpos( $head, '"@type":"Article"' ) && false !== strpos( $head, 'og:type" content="article' ), 'seo: journal gets Article' );
$head = $render_head( $ids['ex_future'], array( 'singular' => true ) );
ok( 1 === substr_count( $head, '<meta name="description"' ), 'seo: exactly one description tag' );
preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $head, $m );
ok( false === stripos( $m[1], '</script' ) && null !== json_decode( $m[1], true ), 'seo: quotes and angle brackets in a title cannot break the JSON-LD' );
$head = $render_head( null, array( 'front' => true ) );
ok( false !== strpos( $head, 'ArtGallery' ) && false !== strpos( $head, 'Jl. DI Panjaitan' ), 'seo: home gets the organization' );
ok( false === strpos( $render_head( null, array( '404' => true ) ), 'name="description"' ), 'seo: no description or social tags on a 404' );
CemetiWP::request( '/', array() );
$r = cemeti_robots( array( 'max-image-preview' => 'large' ) );
ok( empty( $r['noindex'] ), 'robots: normal pages stay indexable' );
CemetiWP::request( '/?s=x', array( 'search' => true ) );
ok( ! empty( cemeti_robots( array() )['noindex'] ), 'robots: search results are noindex' );
CemetiWP::request( '/x', array( '404' => true ) );
ok( ! empty( cemeti_robots( array() )['noindex'] ), 'robots: 404 is noindex' );
CemetiWP::request( '/x', array() );
$_GET = array( 'apply' => 'sent' );
ok( ! empty( cemeti_robots( array() )['noindex'] ), 'robots: form result pages are noindex' );
$_GET = array();
ok( false !== strpos( apply_filters( 'robots_txt', "User-agent: *\nDisallow: /wp-admin/\n" ), 'Disallow: /?s=' ), 'robots.txt: search disallowed' );
ok( false === apply_filters( 'wp_sitemaps_add_provider', 'x', 'users' ) && 'x' === apply_filters( 'wp_sitemaps_add_provider', 'x', 'posts' ), 'sitemap: no user list' );
CemetiWP::request( '/', array( 'singular' => true ) );
$GLOBALS['post'] = CemetiWP::obj( $ids['ex_current'] );
ok( 'Harta yang Paling Berharga adalah Keluarga – Cemeti – Institut untuk Seni dan Masyarakat' === wp_get_document_title(), 'title: "Page – Institution": ' . wp_get_document_title() );

/* ------------------------------------------------------------- security */
ok( false === apply_filters( 'xmlrpc_enabled', true ), 'security: XML-RPC off' );
ok( '' === apply_filters( 'the_generator', 'WordPress 6' ), 'security: generator tag empty' );
ok( DISALLOW_FILE_EDIT, 'security: file editor off' );
ok( false === apply_filters( 'comments_open', true ) && false === apply_filters( 'pings_open', true ), 'security: comments and pings off' );
ok( 'The login details are not correct.' === apply_filters( 'login_errors', 'Unknown username' ), 'security: one generic login error' );
CemetiWP::$logged_in = false;
ok( array( '/wp/v2/posts' => 1 ) === apply_filters( 'rest_endpoints', array( '/wp/v2/users' => 1, '/wp/v2/users/(?P<id>[\d]+)' => 1, '/wp/v2/posts' => 1 ) ), 'security: visitors cannot list users through REST' );
CemetiWP::$logged_in = true;
ok( 3 === count( apply_filters( 'rest_endpoints', array( '/wp/v2/users' => 1, '/wp/v2/users/(?P<id>[\d]+)' => 1, '/wp/v2/posts' => 1 ) ) ), 'security: signed-in users keep the endpoints' );
CemetiWP::$logged_in = false;
CemetiWP::request( '/?author=1', array( 'author' => true ) );
try {
	do_action( 'template_redirect' );
	ok( false, 'security: author archive redirects' );
} catch ( CemetiRedirect $e ) {
	ok( 301 === $e->status, 'security: author archive redirects home (301)' );
}
$h = cemeti_security_headers();
ok( 'nosniff' === $h['X-Content-Type-Options'] && 'SAMEORIGIN' === $h['X-Frame-Options'], 'security: response headers' );
$caps = apply_filters( 'map_meta_cap', array( 'edit_posts' ), 'edit_post', 1, array( CemetiWP::post( array( 'post_type' => 'application' ) ) ) );
ok( array( 'manage_options' ) === $caps, 'security: applications need administrator rights' );
$caps = apply_filters( 'map_meta_cap', array( 'edit_posts' ), 'edit_post', 1, array( $ids['ex_current'] ) );
ok( array( 'edit_posts' ) === $caps, 'security: other content keeps normal rights' );

/* ------------------------------------------------------------ redirects */
CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Ghost Light', 'post_name' => 'ghost-light', 'meta' => array( '_cemeti_old_path' => '2021/06/09/ghost-light' ) ) );
update_option( 'cemeti_legacy_category_paths', array( 'category/pameran-tunggal' => array( 'type' => 'exhibition', 'term' => 'solo' ), 'category/esai' => array( 'type' => 'journal', 'term' => '' ) ) );
$go = static function ( $uri ) {
	CemetiWP::request( $uri, array( '404' => true ) );
	try {
		do_action( 'template_redirect' );
		return null;
	} catch ( CemetiRedirect $e ) {
		return $e;
	}
};
$e = $go( '/2021/06/09/ghost-light/' );
ok( $e && 301 === $e->status && false !== strpos( $e->url, '/exhibition/ghost-light' ), 'redirect: an old post URL goes to the new entry' );
$e = $go( '/category/pameran-tunggal/' );
ok( $e && 'http://cemeti.test/exhibition/?type=solo' === $e->url, 'redirect: an old category goes to the filtered archive' );
$e = $go( '/category/esai/' );
ok( $e && 'http://cemeti.test/journal/' === $e->url, 'redirect: an old journal category goes to the Journal' );
ok( null === $go( '/halaman-yang-tidak-ada/' ), 'redirect: unknown URL stays a 404' );
ok( null === $go( '/' ), 'redirect: the home page is never redirected' );
CemetiWP::request( '/2021/06/09/ghost-light/', array() );
try {
	do_action( 'template_redirect' );
	ok( true, 'redirect: only 404s are touched' );
} catch ( CemetiRedirect $e ) {
	ok( false, 'redirect: a working page was redirected' );
}

qa_done();
