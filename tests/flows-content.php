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
foreach ( array( 'exhibition_type' => array( 'Solo', 'Group' ), 'residency_type' => array( 'Reguler', 'Research' ), 'journal_category' => array( 'News', 'Essay', 'Curatorial Essay' ), 'person_group' => array( 'Advisory Board', 'Team' ) ) as $tax => $names ) {
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
	$problems = qa_check_html( '<html><body><h1>x</h1>' . $html . '</body></html>' );
	$problems = array_filter( $problems, static function ( $p ) { return 0 !== strpos( $p, 'form control without a label' ) && false === strpos( $p, 'img without alt' ); } );
	ok( ! $problems, "meta box markup for $type is well formed: " . implode( '; ', $problems ) );
}
// Repeater: the hidden template must not be submitted twice and must carry the index placeholder.
ob_start();
call_user_func( CemetiWP::$meta_boxes['exhibition']['cemeti_details'], CemetiWP::obj( $ids['ex_current'] ) );
$box = ob_get_clean();
ok( 3 === substr_count( $box, '<div class="cemeti-repeater__row">' ), 'repeater: two stored rows plus one hidden template' );
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
ok( 'Sedang berlangsung' === cemeti_status_label( 'current' ) && 'Mendatang' === cemeti_status_label( 'upcoming' ) && 'Sebelumnya' === cemeti_status_label( 'previous' ) && '' === cemeti_status_label( 'bogus' ), 'status labels (public helper)' );

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
$q = $run( array( 'post_type' => 'exhibition' ), array( 'pta' => 'exhibition' ), array( 'type' => "group' OR 1=1" ) );
ok( array() === $q->posts, 'archive ?type= with SQL-looking text matches nothing and is sanitized' );
$q = $run( array( 'post_type' => 'residency' ), array( 'pta' => 'residency' ), array( 'type' => 'reguler' ) );
ok( array( $ids['res_current'] ) === wp_list_pluck_ids( $q->posts ), 'residency ?type=reguler' );
$q = $run( array( 'post_type' => 'journal' ), array( 'pta' => 'journal' ) );
ok( 20 === $q->get( 'posts_per_page' ), 'journal: 20 per page' );
$q = $run( array( 's' => 'x' ), array( 'search' => true ) );
ok( in_array( 'open_call', $q->get( 'post_type' ), true ) && ! in_array( 'person', $q->get( 'post_type' ), true ) && ! in_array( 'application', $q->get( 'post_type' ), true ), 'search: content types only, never people or applications' );
$entries = cemeti_archive_entries();
ok( 10 === count( $entries ), 'open archive: 5 exhibitions, 2 residencies, 2 public programs, 1 open studio (' . count( $entries ) . ' entries)' );
ok( ! in_array( 'Rangkaian Perayaan #37thCemeti', array_column( $entries, 'title' ), true ), 'open archive: journal excluded' );

/* ------------------------------------------------------------ settings */
ok( 'Cemeti – Institut untuk Seni dan Masyarakat' === cemeti_option( 'institution_name' ), 'option: built-in default' );
update_option( 'cemeti_settings_id', array( 'institution_name' => 'Nama ID', 'address' => '' ) );
update_option( 'cemeti_settings_en', array( 'institution_name' => 'Name EN' ) );
ok( 'Nama ID' === cemeti_option( 'institution_name' ), 'option: the active language wins' );
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
