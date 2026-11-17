<?php
// Run: php tests/flows-move.php — "Move to …" for imported posts on the Posts screen (bulk action and single post).
require __DIR__ . '/harness/bootstrap.php';
qa_boot();
qa_reset_data();

$cat = static function ( $slug, $name ) {
	CemetiWP::$next_term++;
	$id = CemetiWP::$next_term + 5000;
	CemetiWP::$terms['category'][ $id ] = array( 'name' => $name, 'slug' => $slug, 'description' => '' );
	return $id;
};
$post = static function ( $title, $body, array $slugs = array(), array $tags = array() ) use ( $cat ) {
	$id  = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => $title, 'post_content' => $body, 'post_date' => '2025-01-10 10:00:00' ) );
	$ids = array();
	foreach ( $slugs as $s ) {
		$ids[] = $cat( $s, ucfirst( $s ) );
	}
	CemetiWP::$rel[ $id ]['category'] = $ids;
	if ( $tags ) {
		wp_set_object_terms( $id, $tags, 'post_tag' );
	}
	return $id;
};
$term_of = static function ( $id, $tax ) {
	$t = get_the_terms( $id, $tax );
	return $t ? $t[0]->name : '';
};
CemetiWP::$caps = true;

/* ---- the choices */
$choices = cemeti_move_choices();
ok( isset( $choices['exhibition|Solo'], $choices['exhibition|Group'], $choices['residency|Regular'], $choices['residency|Research'], $choices['public_program|'], $choices['open_studio|'], $choices['open_call|'], $choices['journal|Essay'] ), 'choices: every type, with Solo/Group, Regular/Research and the Journal categories' );
ok( ! isset( $choices['person|'] ) && ! isset( $choices['application|'] ), 'choices: no private types' );
ok( 'Exhibition — Solo' === $choices['exhibition|Solo'], 'choice labels read "Exhibition — Solo"' );

/* ---- bulk action */
$actions = apply_filters( 'bulk_actions-edit-post', array( 'edit' => 'Edit' ) );
ok( isset( $actions['cemeti_move|exhibition|Group'] ) && isset( $actions['edit'] ), 'Posts list: "Move to …" actions are added to the bulk menu' );
$a = $post( 'Dalam Gelap', '21 November – 28 Desember 2025', array( 'uncategorized' ), array( 'Maharani', 'Suvi' ) );
$b = $post( 'Another one', '8 – 29 January 2026', array( 'uncategorized' ) );
$redirect = apply_filters( 'handle_bulk_actions-edit-post', 'http://cemeti.test/wp-admin/edit.php', 'cemeti_move|exhibition|Group', array( $a, $b ) );
ok( 'exhibition' === get_post_type( $a ) && 'exhibition' === get_post_type( $b ), 'bulk: the chosen posts become exhibitions' );
ok( 'Group' === $term_of( $a, 'exhibition_type' ) && 'Group' === $term_of( $b, 'exhibition_type' ), 'bulk: with the chosen term (Group)' );
ok( '20251121' === get_post_meta( $a, 'cemeti_start_date', true ) && '20251228' === get_post_meta( $a, 'cemeti_end_date', true ), 'bulk: dates are read from the text (' . get_post_meta( $a, 'cemeti_start_date', true ) . ')' );
ok( false !== strpos( (string) get_post_meta( $a, 'cemeti_artists', true ), 'Maharani' ), 'bulk: tags become artists' );
ok( '' !== (string) get_post_meta( $a, '_cemeti_old_path', true ), 'bulk: the old address is remembered for the redirect' );
ok( false !== strpos( $redirect, 'cemeti_moved=2' ), 'bulk: the result count is shown (' . $redirect . ')' );

$c = $post( 'Belajar', 'Kamis, 5 Februari 2026', array( 'uncategorized' ) );
apply_filters( 'handle_bulk_actions-edit-post', 'x', 'cemeti_move|residency|Research', array( $c ) );
ok( 'residency' === get_post_type( $c ) && 'Research' === $term_of( $c, 'residency_type' ), 'bulk: Residency — Research' );
$d = $post( 'Essay text', 'No date needed', array( 'uncategorized' ) );
apply_filters( 'handle_bulk_actions-edit-post', 'x', 'cemeti_move|journal|Essay', array( $d ) );
ok( 'journal' === get_post_type( $d ) && 'Essay' === $term_of( $d, 'journal_category' ), 'bulk: Journal — Essay' );

/* ---- guards */
$e = $post( 'Guard', 'text', array() );
CemetiWP::$caps = false;
apply_filters( 'handle_bulk_actions-edit-post', 'x', 'cemeti_move|exhibition|Solo', array( $e ) );
ok( 'post' === get_post_type( $e ), 'guard: without permission nothing moves' );
CemetiWP::$caps = true;
apply_filters( 'handle_bulk_actions-edit-post', 'x', 'cemeti_move|nonsense|X', array( $e ) );
ok( 'post' === get_post_type( $e ), 'guard: an unknown destination moves nothing' );
ok( 'x' === apply_filters( 'handle_bulk_actions-edit-post', 'x', 'trash', array( $e ) ), 'guard: other bulk actions are left alone' );
ok( 'skipped' === cemeti_move_post( $a, 'journal|Essay' ), 'guard: an entry that is no longer a plain post is not moved again' );

/* ---- single post */
$f = $post( 'Single move', '1 – 3 March 2026', array( 'uncategorized' ) );
$_GET = $_REQUEST = array( 'post' => $f, 'choice' => 'public_program|', '_wpnonce' => 'valid' );
try { cemeti_move_handle_single(); ok( false, 'single: redirects' ); } catch ( CemetiRedirect $r ) {
	ok( 'public_program' === get_post_type( $f ) && false !== strpos( $r->url, 'post=' . $f ), 'single: moved and the entry opens in its editor' );
}
$g = $post( 'Single bad', 'x', array() );
$_GET = $_REQUEST = array( 'post' => $g, 'choice' => 'bogus|', '_wpnonce' => 'valid' );
try { cemeti_move_handle_single(); ok( false, 'single bad: redirects' ); } catch ( CemetiRedirect $r ) { ok( 'post' === get_post_type( $g ), 'single: a bad choice changes nothing' ); }
$_GET = $_REQUEST = array( 'post' => $g, 'choice' => 'exhibition|Solo' );
try { cemeti_move_handle_single(); ok( false, 'single: nonce' ); } catch ( CemetiDie $x ) { ok( 'post' === get_post_type( $g ), 'single: needs a valid nonce' ); }

/* ---- the screens */
CemetiWP::$ctx = array( 'screen_id' => 'edit-post' );
$_GET = array();
ob_start(); cemeti_move_notice(); $n = ob_get_clean();
ok( false !== strpos( $n, 'Move to' ) && false !== strpos( $n, 'cemeti-converter' ), 'Posts list: a notice says how to move posts and links to the Converter' );
$_GET = array( 'cemeti_moved' => '3' );
ob_start(); cemeti_move_notice(); ok( false !== strpos( ob_get_clean(), '3 posts moved' ), 'Posts list: result notice' );
CemetiWP::$ctx = array( 'screen_id' => 'dashboard' );
ob_start(); cemeti_move_notice(); ok( '' === ob_get_clean(), 'the notice is only on the Posts list' );
$h = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Box', 'post_status' => 'publish' ) );
CemetiWP::$meta_boxes = array();
do_action( 'add_meta_boxes_post', CemetiWP::obj( $h ) );
ok( isset( CemetiWP::$meta_boxes['post']['cemeti_move'] ), 'edit screen of a post: a "move this post" box' );
ob_start(); call_user_func( CemetiWP::$meta_boxes['post']['cemeti_move'], CemetiWP::obj( $h ) ); $box = ob_get_clean();
ok( false !== strpos( $box, 'Exhibition — Solo' ) && false !== strpos( $box, 'cemeti_move_post' ), 'the box lists the destinations and has the Move button' );

qa_done();
