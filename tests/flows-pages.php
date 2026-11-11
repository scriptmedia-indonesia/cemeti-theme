<?php
// Run: php tests/flows-pages.php — the fixed pages (About, Open Archive, Support Us, Contact Us): created, kept, rendered.
require __DIR__ . '/harness/bootstrap.php';
qa_boot();

$slugs = array( 'about', 'open-archive', 'support-us', 'patron-of-cemeti', 'friends-of-cemeti', 'annual-benefit', 'collaboration', 'contact-us' );
$by_slug = static function ( $slug ) {
	foreach ( CemetiWP::$posts as $id => $p ) {
		if ( 'page' === $p['post_type'] && $slug === $p['post_name'] ) {
			return $id;
		}
	}
	return 0;
};

/* ---- a fresh install has none of them: every menu link would be "page not found" */
ok( 8 === count( cemeti_missing_pages() ), 'fresh site: all 8 pages are missing (' . count( cemeti_missing_pages() ) . ')' );
foreach ( array( 'about', 'open-archive', 'support-us', 'contact-us' ) as $slug ) {
	ok( false !== strpos( cemeti_default_menu_paths(), "|$slug|" ), "menu links to /$slug/" );
}
function cemeti_default_menu_paths() {
	$paths = '|';
	foreach ( cemeti_default_menu() as $column ) {
		foreach ( $column as $group ) {
			foreach ( $group['items'] as $item ) {
				$paths .= $item['path'] . '|';
			}
		}
	}
	return $paths;
}

/* ---- activation creates them */
do_action( 'after_switch_theme' );
foreach ( $slugs as $slug ) {
	$id = $by_slug( $slug );
	ok( $id && 'publish' === CemetiWP::$posts[ $id ]['post_status'], "activation: page /$slug/ exists and is published" );
	ok( $id && $slug === get_post_meta( $id, "_cemeti_page_key", true ), "page /$slug/ remembers its key" );
}
$support = $by_slug( 'support-us' );
foreach ( array( 'patron-of-cemeti', 'friends-of-cemeti', 'annual-benefit', 'collaboration' ) as $child ) {
	ok( $support === (int) CemetiWP::$posts[ $by_slug( $child ) ]['post_parent'], "$child is a row of Support Us" );
}
ok( array( 1, 2, 3, 4 ) === array_map( static function ( $c ) use ( $by_slug ) { return (int) CemetiWP::$posts[ $by_slug( $c ) ]['menu_order']; }, array( 'patron-of-cemeti', 'friends-of-cemeti', 'annual-benefit', 'collaboration' ) ), 'Support Us rows keep their order' );
ok( 'Open Archive' === CemetiWP::$posts[ $by_slug( 'open-archive' ) ]['post_title'] && 'Contact Us' === CemetiWP::$posts[ $by_slug( 'contact-us' ) ]['post_title'], 'titles are the menu names' );
ok( ! cemeti_missing_pages(), 'nothing is missing afterwards' );

/* ---- running again never duplicates or overwrites */
$about = $by_slug( 'about' );
CemetiWP::$posts[ $about ]['post_title'] = 'Tentang Cemeti';
CemetiWP::$posts[ $about ]['post_status'] = 'draft';
$count = count( CemetiWP::$posts );
ok( 0 === cemeti_create_missing_pages() && $count === count( CemetiWP::$posts ), 'second run creates nothing, a draft counts as existing' );
ok( 'Tentang Cemeti' === CemetiWP::$posts[ $about ]['post_title'], 'an edited page is left alone' );
unset( CemetiWP::$posts[ $by_slug( 'contact-us' ) ] );
ok( 1 === count( cemeti_missing_pages() ) && 1 === cemeti_create_missing_pages() && $by_slug( 'contact-us' ), 'a deleted page is created again' );

/* ---- admin notice and button */
CemetiWP::$caps = true;
CemetiWP::$ctx = array( 'screen_id' => 'dashboard' );
ob_start(); cemeti_missing_pages_notice(); $quiet = ob_get_clean();
ok( '' === $quiet, 'no notice when every page exists' );
unset( CemetiWP::$posts[ $by_slug( 'open-archive' ) ] );
ob_start(); cemeti_missing_pages_notice(); $notice = ob_get_clean();
ok( false !== strpos( $notice, 'open-archive' ) && false !== strpos( $notice, 'cemeti_create_pages' ) && false !== strpos( $notice, '_wpnonce' ), 'notice names the missing page and has a nonce link' );
CemetiWP::$ctx = array( 'screen_id' => 'edit-post' );
ob_start(); cemeti_missing_pages_notice(); ok( '' === ob_get_clean(), 'notice only on Dashboard, Themes and Cemeti settings' );
CemetiWP::$ctx = array( 'screen_id' => 'dashboard' );
CemetiWP::$caps = false;
ob_start(); cemeti_missing_pages_notice(); ok( '' === ob_get_clean(), 'notice is for administrators only' );
try { cemeti_handle_create_pages(); ok( false, 'button: refused without permission' ); } catch ( CemetiDie $e ) { ok( true, 'button: refused without permission' ); }
CemetiWP::$caps = true;
$_GET = $_REQUEST = array();
try { cemeti_handle_create_pages(); ok( false, 'button: needs a nonce' ); } catch ( CemetiDie $e ) { ok( ! $by_slug( 'open-archive' ), 'button: needs a nonce, nothing created' ); }
$_GET = $_REQUEST = array( '_wpnonce' => 'valid' );
try { cemeti_handle_create_pages(); ok( false, 'button: redirects' ); } catch ( CemetiRedirect $r ) { ok( $by_slug( 'open-archive' ) && false !== strpos( $r->url, 'cemeti_pages_created=1' ), 'button: recreates the page and goes back to Settings' ); }

/* ---- the template follows the key, not the slug */
$odd = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about-2', 'meta' => array( '_cemeti_page_key' => 'about' ) ) );
$plain = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Free page', 'post_name' => 'free' ) );
CemetiWP::request( '/about-2/', array( 'singular' => true ) );
$GLOBALS['post'] = CemetiWP::obj( $odd );
ok( CEMETI_THEME_DIR . '/page-about.php' === cemeti_page_template_by_key( '/theme/page.php' ), 'page "about-2" still uses page-about.php' );
$GLOBALS['post'] = CemetiWP::obj( $plain );
ok( '/theme/page.php' === cemeti_page_template_by_key( '/theme/page.php' ), 'a free page keeps WordPress\' own template' );

/* ---- each fixed page explains itself in the editor */
CemetiWP::$meta_boxes = array();
do_action( 'add_meta_boxes_page', CemetiWP::obj( $by_slug( 'about' ) ) );
ok( isset( CemetiWP::$meta_boxes['page']['cemeti_page_help'] ), 'About: a help box beside the editor says where the picture and people come from' );
ob_start(); call_user_func( CemetiWP::$meta_boxes['page']['cemeti_page_help'] ); $help = ob_get_clean();
ok( false !== strpos( $help, 'Featured image' ) && false !== strpos( $help, 'People' ), 'About help: Featured image and People menu' );
CemetiWP::$meta_boxes = array();
do_action( 'add_meta_boxes_page', CemetiWP::obj( $free = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Free' ) ) ) );
ok( ! isset( CemetiWP::$meta_boxes['page']['cemeti_page_help'] ), 'a free page gets no help text (only the design selector)' );

/* ---- language readiness (no Polylang here) */
$issues = cemeti_language_issues();
ok( 1 === count( $issues ) && false !== strpos( $issues[0], 'Polylang is not active' ), 'without Polylang the admin is told so' );
CemetiWP::$ctx = array( 'screen_id' => 'settings_page_cemeti-settings' );
CemetiWP::$caps = true;
ob_start(); cemeti_language_notice(); ok( false !== strpos( ob_get_clean(), 'Polylang is not active' ), 'the warning shows on Settings → Cemeti' );
CemetiWP::$ctx = array( 'screen_id' => 'edit-page' );
ob_start(); cemeti_language_notice(); ok( '' === ob_get_clean(), 'but not on every screen' );

/* ---- every page renders with real content around */
$ids = qa_seed();
$render = static function ( $slug, $template ) use ( $by_slug ) {
	$id = $by_slug( $slug );
	CemetiWP::$posts[ $id ]['post_status'] = 'publish';
	$html = qa_render( $template, array( 'singular' => true ), array( 'post' => $id, 'path' => "/$slug/" ) );
	$problems = qa_check_html( $html );
	ok( ! $problems, "/$slug/ renders cleanly: " . implode( ' | ', array_slice( $problems, 0, 4 ) ) );
	ok( 1 === preg_match( '/<h1[^>]*>[^<]+<\/h1>/', $html ), "/$slug/ has a heading" );
	return $html;
};
$h = $render( 'open-archive', 'page-open-archive.php' );
ok( false !== strpos( $h, 'cemeti-az__letter' ) && false !== strpos( $h, 'Harta yang Paling Berharga' ), 'Open Archive lists the entries A-Z' );
$h = $render( 'support-us', 'page-support-us.php' );
ok( 4 === substr_count( $h, '<summary>' ) && false !== strpos( $h, 'Patron of Cemeti' ) && false !== strpos( $h, 'Coming soon' ), 'Support Us shows its four rows' );
$h = $render( 'contact-us', 'page-contact-us.php' );
ok( false !== strpos( $h, 'cemeti-contact' ) && false !== strpos( $h, 'info@cemeti.art' ) && false !== strpos( $h, 'Jl.' ), 'Contact Us shows address, hours and e-mail' );
$h = $render( 'about', 'page-about.php' );
ok( false !== strpos( $h, 'cemeti-single--about' ), 'About renders' );

/* ---- About → History: dropdown rows per year, edited on the About page, bundled chronology until then */
$ap = $by_slug( 'about' );
CemetiWP::$caps = true;
$rows = cemeti_history_rows( $ap );
ok( count( $rows ) >= 39 && '1988' === $rows[0]['year'] && '2026' === $rows[ count( $rows ) - 1 ]['year'], 'history: the supplied chronology (1988–2026) is shown until the page has its own' );
ok( false !== strpos( $rows[0]['entries'], 'Inaugural Group Exhibition' ) && 11 === count( cemeti_history_entries( $rows[0]['entries'] ) ), 'history: 1988 has its eleven entries, split at the empty lines' );
$h = $render( 'about', 'page-about.php' );
ok( substr_count( $h, '<details class="cemeti-accordion__row">' ) >= 39 && false !== strpos( $h, '<summary>1988</summary>' ) && false !== strpos( $h, 'cemeti-history__item' ), 'history: About shows one dropdown row per year' );
ok( 1 === preg_match( '/<summary>1988<\/summary>.*?<summary>1989<\/summary>/s', $h ), 'history: oldest year first' );
ob_start(); do_action( 'add_meta_boxes_page', CemetiWP::obj( $ap ) ); call_user_func( CemetiWP::$meta_boxes['page']['cemeti_history'], CemetiWP::obj( $ap ) ); $box = ob_get_clean();
ok( false !== strpos( $box, 'cemeti_fields[history][0][year]' ) && false !== strpos( $box, 'Add Year' ), 'history: the box lists the years and an Add Year button' );
$_POST = array( 'cemeti_history_nonce' => 'valid', 'cemeti_fields' => array( 'history' => array(
	array( 'year' => '2027', 'entries' => "375. 1 – 30 January 2027\n\"New\" – Someone <b>x</b>\nOpen Studio\n\n376. 3 March 2027\nTalk" ),
	array( 'year' => '2026', 'entries' => "374. 30 September 2026\nOne" ),
	array( 'year' => '', 'entries' => '' ),
) ) );
do_action( 'save_post_page', $ap );
$own = get_post_meta( $ap, 'cemeti_history', true );
ok( is_array( $own ) && 2 === count( $own ), 'history: saved rows are the page\'s own (empty row dropped)' );
$h = $render( 'about', 'page-about.php' );
ok( 2 === substr_count( $h, '<details class="cemeti-accordion__row">' ) && 1 === preg_match( '/<summary>2026<\/summary>.*?<summary>2027<\/summary>/s', $h ) && false === strpos( $h, '<b>x</b>' ) && false === strpos( $h, '<summary>1988' ), 'history: own rows replace the supplied ones, sorted by year, tags stripped' );
ok( 2 === count( cemeti_history_entries( $own[0]['entries'] ) ) && '375. 1 – 30 January 2027' === cemeti_history_entries( $own[0]['entries'] )[0][0], 'history: the first line of an entry is its number and date' );
$_POST = array( 'cemeti_fields' => array( 'history' => array( array( 'year' => '2030', 'entries' => 'x' ) ) ) );
do_action( 'save_post_page', $ap );
ok( 2 === count( get_post_meta( $ap, 'cemeti_history', true ) ), 'history: without the nonce nothing is saved' );
$_POST = array( 'cemeti_history_nonce' => 'valid', 'cemeti_fields' => array( 'history' => array() ) );
do_action( 'save_post_page', $ap );
ok( ! get_post_meta( $ap, 'cemeti_history', true ) && count( cemeti_history_rows( $ap ) ) >= 39, 'history: emptied = back to the supplied chronology' );
$op = $by_slug( 'open-archive' );
ob_start(); do_action( 'add_meta_boxes_page', CemetiWP::obj( $op ) ); ob_end_clean();
ok( ! isset( CemetiWP::$meta_boxes['page']['cemeti_history'] ) || 'about' === cemeti_page_key( $ap ), 'history: the box belongs to About only' );
$_POST = array();

/* ---- Contact Us is edited on the page itself (like About), with Settings as the fallback */
$cp = $by_slug( 'contact-us' );
ok( 'info@cemeti.art' === cemeti_contact_value( 'email', $cp ) && false !== strpos( cemeti_contact_value( 'address', $cp ), 'Panjaitan' ) && '' === cemeti_contact_value( 'phone', $cp ), 'contact: empty details use Settings (phone has none)' );
CemetiWP::$caps = true;
$_POST = array( 'cemeti_contact_nonce' => 'valid', 'cemeti_contact' => array( 'address' => "Jl. Baru 1\nYogyakarta", 'opening_hours' => "Selasa - Sabtu\n10:00", 'email' => 'halo@cemeti.art', 'phone' => '+62 274 371015', 'route_url' => 'https://maps.app.goo.gl/abc', ) );
do_action( 'save_post_page', $cp );
ok( "Jl. Baru 1\nYogyakarta" === cemeti_contact_value( 'address', $cp ) && 'halo@cemeti.art' === cemeti_contact_value( 'email', $cp ) && '+62 274 371015' === cemeti_contact_value( 'phone', $cp ), 'contact: details saved on the page win over Settings' );
$_POST = array( 'cemeti_contact_nonce' => 'valid', 'cemeti_contact' => array( 'email' => 'not-an-email', 'route_url' => 'https://evil.example/x', 'address' => '', 'phone' => '<b>1</b>' ) );
do_action( 'save_post_page', $cp );
ok( 'info@cemeti.art' === cemeti_contact_value( 'email', $cp ) && '' === (string) get_post_meta( $cp, 'cemeti_contact_route_url', true ) && false !== strpos( cemeti_contact_value( 'address', $cp ), 'Panjaitan' ) && '1' === cemeti_contact_value( 'phone', $cp ), 'contact: bad email and non-Google-Maps link are dropped, empty = back to Settings, tags stripped' );
$_POST = array( 'cemeti_contact' => array( 'email' => 'x@y.org' ) );
do_action( 'save_post_page', $cp );
ok( 'info@cemeti.art' === cemeti_contact_value( 'email', $cp ), 'contact: without the nonce nothing is saved' );
$_POST = array( 'cemeti_contact_nonce' => 'valid', 'cemeti_contact' => array( 'phone' => '+62 274 371015', 'email' => 'halo@cemeti.art' ) );
do_action( 'save_post_page', $cp );
$_POST = array();
CemetiWP::$meta_boxes = array();
do_action( 'add_meta_boxes_page', CemetiWP::obj( $cp ) );
ok( isset( CemetiWP::$meta_boxes['page']['cemeti_contact'] ), 'contact: the details box is on the Contact Us page' );
CemetiWP::$meta_boxes = array();
do_action( 'add_meta_boxes_page', CemetiWP::obj( $by_slug( 'about' ) ) );
ok( ! isset( CemetiWP::$meta_boxes['page']['cemeti_contact'] ), 'contact: and only there' );
$h = $render( 'contact-us', 'page-contact-us.php' );
ok( false !== strpos( $h, 'halo@cemeti.art' ) && false !== strpos( $h, 'href="tel:+622743710' ) && false !== strpos( $h, '+62 274 371015' ), 'contact page: shows the details of the page, with a call link' );
$att2 = CemetiWP::post( array( 'post_type' => 'attachment', 'post_title' => 'photo' ) );
update_post_meta( $cp, '_thumbnail_id', $att2 );
$h = $render( 'contact-us', 'page-contact-us.php' );
ok( false !== strpos( $h, 'cemeti-single__hero' ), 'contact page: has the picture slot of About (featured image)' );
delete_post_meta( $cp, '_thumbnail_id' );

/* ---- a page called "tentang" (or marked in the editor) uses the About design in every language */
qa_reset_data();
do_action( 'after_switch_theme' );
$tentang = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Tentang', 'post_name' => 'tentang' ) );
$random  = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Random', 'post_name' => 'random' ) );
ok( 'about' === cemeti_page_key( $tentang ) && '' === cemeti_page_key( $random ), 'page key: slug "tentang" is About, a free page is nothing' );
$kontak2 = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Halaman Baru', 'post_name' => 'kontak-kami-2' ) );
$kontak3 = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Kontak Kami', 'post_name' => 'zzz-123' ) );
ok( 'contact-us' === cemeti_page_key( $kontak2 ) && 'contact-us' === cemeti_page_key( $kontak3 ), 'page key: "kontak-kami-2" (a copy made by Polylang) and a page titled "Kontak Kami" are Contact Us, so its details box shows' );
CemetiWP::request( '/tentang/', array( 'singular' => true ) );
$GLOBALS['post'] = CemetiWP::obj( $tentang );
ok( CEMETI_THEME_DIR . '/page-about.php' === cemeti_page_template_by_key( '/t/page.php' ), 'page "tentang" is drawn with page-about.php' );
$GLOBALS['post'] = CemetiWP::obj( $random );
ok( '/t/page.php' === cemeti_page_template_by_key( '/t/page.php' ), 'a free page keeps the ordinary template' );
CemetiWP::$caps = true;
$_POST = array( 'cemeti_page_type_nonce' => 'valid', 'cemeti_page_type' => 'support-us' );
do_action( 'save_post_page', $random );
ok( 'support-us' === cemeti_page_key( $random ), 'selector: choosing a design in the editor sets it' );
$_POST = array( 'cemeti_page_type_nonce' => 'valid', 'cemeti_page_type' => 'bogus' );
do_action( 'save_post_page', $random );
ok( '' === cemeti_page_key( $random ), 'selector: an unknown value clears it' );
$_POST = array( 'cemeti_page_type' => 'about' );
do_action( 'save_post_page', $random );
ok( '' === cemeti_page_key( $random ), 'selector: without the nonce nothing is saved' );
$_POST = array();
CemetiWP::$meta_boxes = array();
do_action( 'add_meta_boxes_page', CemetiWP::obj( $random ) );
ob_start(); call_user_func( CemetiWP::$meta_boxes['page']['cemeti_page_type'], CemetiWP::obj( $random ) ); $box = ob_get_clean();
ok( false !== strpos( $box, 'value="about"' ) && false !== strpos( $box, 'Automatic' ), 'selector box lists the designs' );

/* ---- About without a featured image: the picture that opens the text becomes the hero */
$att = CemetiWP::post( array( 'post_type' => 'attachment', 'post_title' => 'photo' ) );
list( $hid, $rest ) = cemeti_take_leading_image( '<!-- wp:image --><figure class="wp-block-image"><img src="x.jpg" class="wp-image-' . $att . '"></figure><!-- /wp:image --><p>Text after</p>' );
ok( $att === $hid && '<p>Text after</p>' === trim( $rest ), 'leading picture: lifted out of the text' );
list( $hid, $rest ) = cemeti_take_leading_image( '<p>Text first</p><figure><img class="wp-image-' . $att . '"></figure>' );
ok( 0 === $hid && false !== strpos( $rest, 'Text first' ), 'leading picture: a picture later in the text stays' );
list( $hid, $rest ) = cemeti_take_leading_image( '<figure><img class="wp-image-' . $att . '"><figcaption>Caption</figcaption></figure><p>x</p>' );
ok( 0 === $hid && false !== strpos( $rest, 'Caption' ), 'leading picture: with a caption it stays in the text' );
$ab = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Tentang', 'post_name' => 'tentang-cemeti', 'post_content' => '<figure><img class="wp-image-' . $att . '"></figure><p>Cemeti adalah.</p>' ) );
$html = qa_render( 'page-about.php', array( 'singular' => true ), array( 'post' => $ab, 'path' => '/tentang-cemeti/' ) );
ok( false !== strpos( $html, 'cemeti-single__hero' ) && 1 === substr_count( $html, '/img/' . $att . '-' ), 'About: the picture sits in the hero slot once, with the same class as the English page' );

/* ---- People added in the admin always appear on About */
$mkp = static function ( $title, array $terms, $status = 'publish' ) {
	$id = CemetiWP::post( array( 'post_type' => 'person', 'post_title' => $title, 'post_status' => $status, 'menu_order' => 1 ) );
	if ( $terms ) {
		wp_set_object_terms( $id, $terms, 'person_group' );
	}
	return $id;
};
qa_reset_data();
do_action( 'after_switch_theme' );
$mkp( 'Person Slug En', array( 'Advisory Board' ) );
foreach ( CemetiWP::$terms['person_group'] as $tid => $t ) {
	if ( 'Advisory Board' === $t['name'] ) {
		CemetiWP::$terms['person_group'][ $tid ]['slug'] = 'advisory-board-en'; // What Polylang makes of a second-language copy.
	}
}
$mkp( 'Person Dewan', array( 'Dewan Penasihat' ) );
$mkp( 'Person Team', array( 'Team' ) );
$mkp( 'Person No Group', array() );
$mkp( 'Person Draft', array( 'Team' ), 'draft' );
$groups = cemeti_people_by_group();
$names = static function ( $list ) { return array_map( static function ( $p ) { return $p->post_title; }, $list ); };
ok( in_array( 'Person Slug En', $names( $groups['advisory-board'] ), true ) && in_array( 'Person Dewan', $names( $groups['advisory-board'] ), true ), 'people: an Advisory Board term is recognised whatever its slug ("-en") or name ("Dewan Penasihat")' );
ok( in_array( 'Person Team', $names( $groups['team'] ), true ) && in_array( 'Person No Group', $names( $groups['team'] ), true ), 'people: Team members, and people with no group, are shown under Team' );
ok( ! in_array( 'Person Draft', array_merge( $names( $groups['team'] ), $names( $groups['advisory-board'] ) ), true ), 'people: drafts stay hidden' );
// Second-language copy without a group term (QA round 3: the Indonesian Advisory Board member showed under Team).
$mk_role = CemetiWP::post( array( 'post_type' => 'person', 'post_title' => 'Person Role Only', 'menu_order' => 1, 'meta' => array( 'cemeti_role' => 'Advisory Board' ) ) );
$mk_tr_en = $mkp( 'Person Tr En', array( 'Advisory Board' ) );
$mk_tr_id = CemetiWP::post( array( 'post_type' => 'person', 'post_title' => 'Person Tr Id', 'menu_order' => 1, 'meta' => array( 'cemeti_role' => 'Penyunting' ) ) );
$GLOBALS['qa_translations'] = array( $mk_tr_id => array( 'en' => $mk_tr_en, 'id' => $mk_tr_id ) );
if ( ! function_exists( 'pll_get_post_translations' ) ) {
	function pll_get_post_translations( $id ) { return isset( $GLOBALS['qa_translations'][ $id ] ) ? $GLOBALS['qa_translations'][ $id ] : array(); }
}
$groups = cemeti_people_by_group();
ok( in_array( 'Person Role Only', $names( $groups['advisory-board'] ), true ), 'people: no group term but the role says Advisory Board -> Advisory Board' );
ok( in_array( 'Person Tr Id', $names( $groups['advisory-board'] ), true ), 'people: a copy without a group term takes the group of its translation' );
ok( in_array( 'Person Team', $names( $groups['team'] ), true ) && ! in_array( 'Person Team', $names( $groups['advisory-board'] ), true ), 'people: an explicit Team term is not overridden' );
$about = $by_slug( 'about' );
$html = qa_render( 'page-about.php', array( 'singular' => true ), array( 'post' => $about, 'path' => '/about/' ) );
ok( false !== strpos( $html, 'Person Slug En' ) && false !== strpos( $html, 'Person No Group' ) && 2 === substr_count( $html, 'class="cemeti-people ' ), 'About page shows both sections with those people' );

/* ---- Team: roles in bold with their names under them */
qa_reset_data();
do_action( 'after_switch_theme' );
$tm = static function ( $name, $role ) {
	$id = CemetiWP::post( array( 'post_type' => 'person', 'post_title' => $name, 'meta' => array( 'cemeti_role' => $role ), 'menu_order' => 1 ) );
	wp_set_object_terms( $id, array( 'Team' ), 'person_group' );
	return $id;
};
$tm( 'Tiara Afriani', 'Manajer Operasional' );
$tm( 'Joko', 'Staf Teknis' );
$tm( 'Rizky Ramadhan Aji', 'Staf Teknis' );
$tm( 'Solo Name', '' );
$grouped = cemeti_group_people_by_role( cemeti_people_by_group()['team'] );
$roles = array_column( $grouped, 'role' );
ok( 3 === count( $grouped ) && in_array( 'Staf Teknis', $roles, true ), 'team: people with the same role are grouped (' . implode( ' | ', $roles ) . ')' );
$tech = array_values( array_filter( $grouped, static function ( $g ) { return 'Staf Teknis' === $g['role']; } ) )[0];
ok( 2 === count( $tech['names'] ), 'team: two names under "Staf Teknis"' );
$html = qa_render( 'page-about.php', array( 'singular' => true ), array( 'post' => $by_slug( 'about' ), 'path' => '/about/' ) );
ok( false !== strpos( $html, 'class="cemeti-team"' ) && false !== strpos( $html, '<strong>Staf Teknis</strong>' ) && false !== strpos( $html, '<span>Joko</span>' ), 'About: Team shows each role in bold with its names under it' );
ok( false === strpos( preg_replace( '#<section class="cemeti-history.*?</section>#s', '', preg_replace( '#<section class="cemeti-people cemeti-people--team.*?</section>#s', '', $html ) ), 'Joko' ) && false === strpos( $html, 'cemeti-people--team">' . '<img' ), 'About: the Team block has no photo grid' );

// An empty site (no entries at all) still renders the archive page.
qa_reset_data();
do_action( 'after_switch_theme' );
$id = $by_slug( 'open-archive' );
$html = qa_render( 'page-open-archive.php', array( 'singular' => true ), array( 'post' => $id, 'path' => '/open-archive/' ) );
ok( ! qa_check_html( $html ) && false !== strpos( $html, 'cemeti-az' ), 'Open Archive on an empty site renders without entries' );

qa_done();
