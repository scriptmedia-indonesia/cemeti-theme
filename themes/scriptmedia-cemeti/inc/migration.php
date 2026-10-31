<?php
/**
 * Category converter (Tools → Cemeti Converter).
 *
 * Turns the posts of an old site, which were told apart by category, into Cemeti
 * content types: you map each old category to a type (and optionally a term), preview
 * the result, then convert in batches. Every converted post remembers its old URL
 * and categories, so the conversion can be reverted and old links keep working
 * (see inc/redirects.php).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CEMETI_CONVERT_BATCH = 50;

/**
 * Target types: post type => array( label, taxonomy or '' ).
 *
 * @return array<string, array{0:string,1:string}>
 */
function cemeti_convert_targets() {
	$targets = array();
	foreach ( cemeti_config( 'post-types' ) as $type => $args ) {
		if ( isset( $args['public'] ) && ! $args['public'] ) {
			continue;
		}
		$targets[ $type ] = array( $args['singular'], isset( $args['taxonomies'][0] ) ? $args['taxonomies'][0] : '' );
	}
	return $targets;
}

/**
 * Saved settings.
 *
 * @return array{mode:string,cats:array,start_key:string,end_key:string,old_permalink:string,old_host:string}
 */
function cemeti_convert_settings() {
	$saved = get_option( 'cemeti_convert_map', array() );
	return array(
		'mode'          => isset( $saved['mode'] ) && 'manual' === $saved['mode'] ? 'manual' : 'rules',
		'cats'          => isset( $saved['cats'] ) && is_array( $saved['cats'] ) ? $saved['cats'] : array(),
		'start_key'     => isset( $saved['start_key'] ) ? (string) $saved['start_key'] : '',
		'end_key'       => isset( $saved['end_key'] ) ? (string) $saved['end_key'] : '',
		'old_permalink' => isset( $saved['old_permalink'] ) ? (string) $saved['old_permalink'] : '%year%/%monthnum%/%day%/%postname%',
		'old_host'      => isset( $saved['old_host'] ) ? (string) $saved['old_host'] : 'cemeti.art',
	);
}

/**
 * Pick the target for a post: the first mapped category in map order wins.
 *
 * @param int[]                                       $post_cats Category IDs of the post.
 * @param array<int, array{type:string,term:string}> $map       Category ID => target, in priority order.
 * @return array{type:string,term:string,cat:int}|null
 */
function cemeti_convert_pick_target( array $post_cats, array $map ) {
	foreach ( $map as $cat_id => $target ) {
		if ( in_array( (int) $cat_id, array_map( 'intval', $post_cats ), true ) && ! empty( $target['type'] ) ) {
			return array(
				'type' => $target['type'],
				'term' => isset( $target['term'] ) ? $target['term'] : '',
				'cat'  => (int) $cat_id,
			);
		}
	}
	return null;
}

/**
 * Normalise a date value to Ymd. Accepts Ymd (as ACF stores it) or anything strtotime reads.
 *
 * @param mixed $value Raw value.
 * @return string Ymd, or '' when it cannot be read.
 */
function cemeti_parse_ymd( $value ) {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';
	if ( '' === $value ) {
		return '';
	}
	if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $value, $m ) ) {
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
	}
	$time = strtotime( $value );
	return false === $time ? '' : gmdate( 'Ymd', $time );
}

/**
 * IDs of the posts still to convert (old posts in mapped categories, not yet converted).
 *
 * @param array $settings Converter settings.
 * @param int   $limit    Max IDs (-1 for all).
 * @return int[]
 */
function cemeti_convert_pending_ids( array $settings, $limit = -1 ) {
	$manual = 'manual' === $settings['mode'];
	if ( $manual && ! $settings['cats'] ) {
		return array();
	}
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'category__in'   => $manual ? array_map( 'intval', array_keys( $settings['cats'] ) ) : array(),
				'posts_per_page' => $limit,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( array( 'key' => '_cemeti_converted_at', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);
}

/**
 * What a post becomes, in either mode.
 *
 * @param int   $post_id  Post ID.
 * @param array $settings Converter settings.
 * @return array{skip:bool,type:string,term:string,lang:string,projects:string[],cat:int}|null Null when nothing matches.
 */
function cemeti_convert_classify( $post_id, array $settings ) {
	if ( 'manual' === $settings['mode'] ) {
		$target = cemeti_convert_pick_target( wp_get_post_categories( $post_id ), $settings['cats'] );
		if ( ! $target ) {
			return null;
		}
		$term = '__name' === $target['term'] ? (string) get_cat_name( $target['cat'] ) : $target['term'];
		return array( 'skip' => false, 'type' => $target['type'], 'term' => $term, 'lang' => '', 'projects' => array(), 'cat' => $target['cat'] );
	}
	$cats = array();
	foreach ( wp_get_post_categories( $post_id, array( 'fields' => 'all' ) ) as $cat ) {
		$cats[] = array( $cat->slug, $cat->name );
	}
	$info        = cemeti_rules_classify( array( 'title' => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ), 'cats' => $cats ) );
	$info['cat'] = 0;
	return $info;
}

/**
 * Old URL path of a post, built from the old permalink pattern (rules mode) or the live permalink.
 *
 * @param int   $post_id  Post ID.
 * @param array $settings Converter settings.
 * @return string Path without slashes at the ends.
 */
function cemeti_convert_old_path( $post_id, array $settings ) {
	if ( 'manual' !== $settings['mode'] && '' !== $settings['old_permalink'] ) {
		$time = strtotime( get_post_field( 'post_date', $post_id ) );
		$path = strtr(
			$settings['old_permalink'],
			array(
				'%year%'     => gmdate( 'Y', $time ),
				'%monthnum%' => gmdate( 'm', $time ),
				'%day%'      => gmdate( 'd', $time ),
				'%postname%' => get_post_field( 'post_name', $post_id ),
				'%post_id%'  => (string) $post_id,
			)
		);
		return trim( $path, '/' );
	}
	return trim( (string) wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH ), '/' );
}

/**
 * Start and end date (Ymd) of a post for a dated type.
 *
 * @param WP_Post $post     Post.
 * @param string  $type     Target type.
 * @param array   $settings Converter settings.
 * @return array{0:string,1:string,2:bool} Start, end and whether the date was really found (false = publish date used).
 */
function cemeti_convert_dates( $post, $type, array $settings ) {
	$ref   = substr( (string) $post->post_date, 0, 10 );
	$fallb = get_post_time( 'Ymd', false, $post->ID );

	if ( '' !== $settings['start_key'] ) { // A custom field wins in both modes.
		$start = cemeti_parse_ymd( get_post_meta( $post->ID, $settings['start_key'], true ) );
		$end   = '' !== $settings['end_key'] ? cemeti_parse_ymd( get_post_meta( $post->ID, $settings['end_key'], true ) ) : '';
		if ( '' === $start ) {
			return array( $fallb, $fallb, false );
		}
		return array( $start, '' === $end || $end < $start ? $start : $end, true );
	}
	if ( 'manual' === $settings['mode'] ) {
		return array( $fallb, $fallb, true ); // No key: the publish date is what the editor asked for.
	}
	if ( 'open_call' === $type ) {
		$deadline = cemeti_extract_deadline( $post->post_content, $ref );
		return '' === $deadline ? array( $fallb, $fallb, false ) : array( $deadline, $deadline, true );
	}
	$range = cemeti_extract_date_range( $post->post_content, $ref );
	return $range ? array( $range[0], $range[1], true ) : array( $fallb, $fallb, false );
}

/**
 * Convert one post. Returns a short result code for the report.
 *
 * @param int   $post_id  Post ID.
 * @param array $settings Converter settings.
 * @return string "converted", "skipped" or "no_date" (converted, but the date is the publish date).
 */
function cemeti_convert_post( $post_id, array $settings ) {
	$info = cemeti_convert_classify( $post_id, $settings );
	if ( ! $info || $info['skip'] ) {
		return 'skipped';
	}
	$targets = cemeti_convert_targets();
	if ( ! isset( $targets[ $info['type'] ] ) ) {
		return 'skipped';
	}
	$post = get_post( $post_id );
	$type = $info['type'];

	// Remember where the post came from before anything changes.
	update_post_meta( $post_id, '_cemeti_old_path', cemeti_convert_old_path( $post_id, $settings ) );
	update_post_meta( $post_id, '_cemeti_old_cats', wp_get_post_categories( $post_id ) );
	update_post_meta( $post_id, '_cemeti_old_slug', $post->post_name );
	update_post_meta( $post_id, '_cemeti_head_slugs', cemeti_head_slugs( $post->post_content, $settings['old_host'] ) );

	$result = 'converted';
	$set    = array( '_cemeti_old_slug', '_cemeti_head_slugs' );

	// Dates for the dated types.
	list( $start_key, $end_key ) = cemeti_date_keys( $type );
	if ( $end_key ) {
		list( $start, $end, $found ) = cemeti_convert_dates( $post, $type, $settings );
		if ( ! $found ) {
			$result = 'no_date';
		}
		if ( '' !== $start ) {
			if ( $start_key ) {
				update_post_meta( $post_id, 'cemeti_' . $start_key, $start );
				$set[] = 'cemeti_' . $start_key;
			}
			update_post_meta( $post_id, 'cemeti_' . $end_key, $end );
			$set[] = 'cemeti_' . $end_key;
		}
	}

	// Tags of the old site are people: artists, residents or speakers.
	if ( 'manual' !== $settings['mode'] ) {
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
		if ( $tags && in_array( $type, array( 'exhibition', 'residency', 'open_studio' ), true ) ) {
			update_post_meta( $post_id, 'cemeti_artists', implode( "\n", $tags ) );
			$set[] = 'cemeti_artists';
		} elseif ( $tags && 'public_program' === $type ) {
			update_post_meta( $post_id, 'cemeti_speakers', implode( ', ', $tags ) );
			$set[] = 'cemeti_speakers';
		}
		if ( '' !== $info['lang'] ) {
			update_post_meta( $post_id, '_cemeti_lang', $info['lang'] );
			$set[] = '_cemeti_lang';
		}
	}
	update_post_meta( $post_id, '_cemeti_converted_meta', $set );

	set_post_type( $post_id, $type );

	if ( 'manual' !== $settings['mode'] && '' !== $info['lang'] && function_exists( 'pll_set_post_language' ) ) {
		pll_set_post_language( $post_id, $info['lang'] );
	}

	// Type term (Solo/Group, Reguler, Journal category).
	$taxonomy = $targets[ $type ][1];
	if ( $taxonomy && '' !== $info['term'] ) {
		wp_set_object_terms( $post_id, array( $info['term'] ), $taxonomy );
	}
	// Project / series terms from the other categories.
	if ( $info['projects'] ) {
		wp_set_object_terms( $post_id, $info['projects'], 'project' );
	}

	update_post_meta( $post_id, '_cemeti_converted_at', time() );
	return $result;
}

/**
 * Link Indonesian and English twins as Polylang translations.
 *
 * @return int Pairs linked, or -1 when Polylang is not active.
 */
function cemeti_link_translations() {
	if ( ! function_exists( 'pll_save_post_translations' ) ) {
		return -1;
	}
	$items = array();
	foreach ( cemeti_convert_done_ids() as $post_id ) {
		$lang = (string) get_post_meta( $post_id, '_cemeti_lang', true );
		$slug = (string) get_post_meta( $post_id, '_cemeti_old_slug', true );
		if ( '' === $lang || '' === $slug ) {
			continue;
		}
		$links = get_post_meta( $post_id, '_cemeti_head_slugs', true );
		$items[ $post_id ] = array( 'slug' => $slug, 'lang' => $lang, 'links' => is_array( $links ) ? $links : array() );
	}
	$count = 0;
	foreach ( cemeti_pair_translations( $items ) as $pair ) {
		pll_save_post_translations( array( $items[ $pair[0] ]['lang'] => $pair[0], $items[ $pair[1] ]['lang'] => $pair[1] ) );
		++$count;
	}
	return $count;
}

/**
 * Undo a conversion for one post.
 *
 * @param int $post_id Post ID.
 */
function cemeti_revert_post( $post_id ) {
	$meta = get_post_meta( $post_id, '_cemeti_converted_meta', true );
	foreach ( is_array( $meta ) ? $meta : array() as $key ) {
		delete_post_meta( $post_id, $key );
	}
	foreach ( get_object_taxonomies( get_post_type( $post_id ) ) as $taxonomy ) {
		wp_set_object_terms( $post_id, array(), $taxonomy );
	}
	set_post_type( $post_id, 'post' );
	foreach ( array( '_cemeti_converted_at', '_cemeti_converted_meta', '_cemeti_old_path', '_cemeti_old_cats', '_cemeti_old_slug', '_cemeti_head_slugs', '_cemeti_lang' ) as $key ) {
		delete_post_meta( $post_id, $key );
	}
}

/**
 * IDs of converted posts (for revert), any type.
 *
 * @param int $limit Max IDs (-1 for all).
 * @return int[]
 */
function cemeti_convert_done_ids( $limit = -1 ) {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'orderby'        => 'ID',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_cemeti_converted_at', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);
}

/**
 * Preview: what the conversion would do, without changing anything.
 *
 * @param array $settings Converter settings.
 * @return array{total:int,by_type:array<string,int>,by_term:array<string,int>,lang:array<string,int>,skipped:int,skipped_titles:string[],no_date:int,samples:array<string,string[]>}
 */
function cemeti_convert_preview( array $settings ) {
	$preview = array( 'total' => 0, 'by_type' => array(), 'by_term' => array(), 'lang' => array(), 'skipped' => 0, 'skipped_titles' => array(), 'no_date' => 0, 'samples' => array() );
	foreach ( cemeti_convert_pending_ids( $settings ) as $post_id ) {
		++$preview['total'];
		$info = cemeti_convert_classify( $post_id, $settings );
		if ( ! $info || $info['skip'] ) {
			++$preview['skipped'];
			if ( count( $preview['skipped_titles'] ) < 8 ) {
				$preview['skipped_titles'][] = get_the_title( $post_id );
			}
			continue;
		}
		$type = $info['type'];
		$preview['by_type'][ $type ] = isset( $preview['by_type'][ $type ] ) ? $preview['by_type'][ $type ] + 1 : 1;
		if ( '' !== $info['term'] ) {
			$key = $type . ': ' . $info['term'];
			$preview['by_term'][ $key ] = isset( $preview['by_term'][ $key ] ) ? $preview['by_term'][ $key ] + 1 : 1;
		}
		$lang = '' !== $info['lang'] ? $info['lang'] : '?';
		$preview['lang'][ $lang ] = isset( $preview['lang'][ $lang ] ) ? $preview['lang'][ $lang ] + 1 : 1;
		if ( ! isset( $preview['samples'][ $type ] ) || count( $preview['samples'][ $type ] ) < 3 ) {
			$preview['samples'][ $type ][] = get_the_title( $post_id );
		}
		list( , $end_key ) = cemeti_date_keys( $type );
		if ( $end_key ) {
			list( , , $found ) = cemeti_convert_dates( get_post( $post_id ), $type, $settings );
			if ( ! $found ) {
				++$preview['no_date'];
			}
		}
	}
	return $preview;
}

/**
 * Admin menu entry.
 */
function cemeti_convert_menu() {
	add_management_page( 'Cemeti Converter', 'Cemeti Converter', 'manage_options', 'cemeti-converter', 'cemeti_convert_page' );
}
if ( is_admin() ) {
	add_action( 'admin_menu', 'cemeti_convert_menu' );
}

/**
 * Save the mapping.
 */
function cemeti_convert_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'scriptmedia-cemeti' ) );
	}
	check_admin_referer( 'cemeti_convert_save' );

	$targets  = cemeti_convert_targets();
	$cats     = array();
	$posted   = isset( $_POST['cats'] ) && is_array( $_POST['cats'] ) ? wp_unslash( $_POST['cats'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	foreach ( $posted as $cat_id => $row ) {
		$type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : '';
		if ( isset( $targets[ $type ] ) ) {
			$cats[ (int) $cat_id ] = array( 'type' => $type, 'term' => isset( $row['term'] ) ? sanitize_text_field( $row['term'] ) : '' );
		}
	}

	// Priority: the order of the rows as posted.
	update_option(
		'cemeti_convert_map',
		array(
			'mode'          => isset( $_POST['mode'] ) && 'manual' === $_POST['mode'] ? 'manual' : 'rules', // phpcs:ignore WordPress.Security.NonceVerification
			'cats'          => $cats,
			'start_key'     => isset( $_POST['start_key'] ) ? sanitize_key( wp_unslash( $_POST['start_key'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'end_key'       => isset( $_POST['end_key'] ) ? sanitize_key( wp_unslash( $_POST['end_key'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'old_permalink' => isset( $_POST['old_permalink'] ) ? preg_replace( '/[^a-z%_\/-]/i', '', wp_unslash( $_POST['old_permalink'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'old_host'      => isset( $_POST['old_host'] ) ? preg_replace( '/[^a-z0-9.-]/i', '', (string) strtok( preg_replace( '#^https?://#i', '', trim( wp_unslash( $_POST['old_host'] ) ) ), '/' ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		),
		false
	);

	// Old category archive URLs, for redirects.
	$legacy = array();
	if ( ! isset( $_POST['mode'] ) || 'manual' !== $_POST['mode'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( get_categories( array( 'hide_empty' => false ) ) as $cat ) {
			$rule = cemeti_rules_classify( array( 'title' => '', 'cats' => array( array( $cat->slug, $cat->name ) ) ) );
			$path = trim( (string) wp_parse_url( get_category_link( $cat->term_id ), PHP_URL_PATH ), '/' );
			if ( '' !== $path && 'open_call' !== $rule['type'] ) {
				$legacy[ $path ] = array( 'type' => $rule['type'], 'term' => 'exhibition' === $rule['type'] ? sanitize_title( $rule['term'] ) : '' );
			}
		}
	}
	foreach ( $cats as $cat_id => $row ) {
		$path = trim( (string) wp_parse_url( get_category_link( $cat_id ), PHP_URL_PATH ), '/' );
		if ( '' !== $path ) {
			$legacy[ $path ] = array( 'type' => $row['type'], 'term' => '__name' === $row['term'] ? sanitize_title( (string) get_cat_name( $cat_id ) ) : $row['term'] );
		}
	}
	update_option( 'cemeti_legacy_category_paths', $legacy, false );

	wp_safe_redirect( add_query_arg( array( 'page' => 'cemeti-converter', 'saved' => 1 ), admin_url( 'tools.php' ) ) );
	exit;
}
add_action( 'admin_post_cemeti_convert_save', 'cemeti_convert_save' );

/**
 * Run one batch of converting or reverting, then go back to the page.
 */
function cemeti_convert_run() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'scriptmedia-cemeti' ) );
	}
	check_admin_referer( 'cemeti_convert_run' );
	$mode     = isset( $_GET['mode'] ) && in_array( $_GET['mode'], array( 'revert', 'link' ), true ) ? sanitize_key( $_GET['mode'] ) : 'convert'; // phpcs:ignore WordPress.Security.NonceVerification
	$settings = cemeti_convert_settings();
	$counts   = array( 'converted' => 0, 'skipped' => 0, 'no_date' => 0 );

	if ( 'link' === $mode ) {
		$linked = cemeti_link_translations();
		wp_safe_redirect( add_query_arg( array( 'page' => 'cemeti-converter', 'linked' => $linked ), admin_url( 'tools.php' ) ) );
		exit;
	}
	if ( 'revert' === $mode ) {
		foreach ( cemeti_convert_done_ids( CEMETI_CONVERT_BATCH ) as $post_id ) {
			cemeti_revert_post( $post_id );
			++$counts['converted'];
		}
		$remaining = count( cemeti_convert_done_ids() );
	} else {
		foreach ( cemeti_convert_pending_ids( $settings, CEMETI_CONVERT_BATCH ) as $post_id ) {
			$code = cemeti_convert_post( $post_id, $settings );
			if ( 'skipped' === $code ) {
				// Mark it so it is not picked again forever.
				update_post_meta( $post_id, '_cemeti_converted_at', time() );
				update_post_meta( $post_id, '_cemeti_converted_meta', array() );
			}
			if ( 'skipped' !== $code ) {
				++$counts['converted'];
			}
			if ( 'no_date' === $code ) {
				++$counts['no_date'];
			}
			if ( 'skipped' === $code ) {
				++$counts['skipped'];
			}
		}
		$remaining = count( cemeti_convert_pending_ids( $settings ) );
	}
	update_option( 'cemeti_content_version', (int) get_option( 'cemeti_content_version', 0 ) + 1, false );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'      => 'cemeti-converter',
				'ran'       => $mode,
				'done'      => $counts['converted'],
				'no_date'   => $counts['no_date'],
				'remaining' => $remaining,
			),
			admin_url( 'tools.php' )
		)
	);
	exit;
}
add_action( 'admin_post_cemeti_convert_run', 'cemeti_convert_run' );

/**
 * Render the converter page.
 */
function cemeti_convert_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings  = cemeti_convert_settings();
	$targets   = cemeti_convert_targets();
	$cats      = get_categories( array( 'hide_empty' => false ) );
	$ran       = isset( $_GET['ran'] ) ? sanitize_key( wp_unslash( $_GET['ran'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$remaining = isset( $_GET['remaining'] ) ? (int) $_GET['remaining'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$run_url   = static function ( $mode ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_convert_run&mode=' . $mode ), 'cemeti_convert_run' );
	};
	$rules_on = 'rules' === $settings['mode'];

	// Manual rows in saved priority order first, then the remaining categories.
	$ordered = array();
	foreach ( array_keys( $settings['cats'] ) as $cat_id ) {
		foreach ( $cats as $cat ) {
			if ( (int) $cat->term_id === (int) $cat_id ) {
				$ordered[] = $cat;
			}
		}
	}
	foreach ( $cats as $cat ) {
		if ( ! isset( $settings['cats'][ (int) $cat->term_id ] ) ) {
			$ordered[] = $cat;
		}
	}
	?>
	<div class="wrap">
		<h1>Cemeti Converter</h1>
		<p>Turn the old posts, told apart by category, into Cemeti content. <strong>Back up the database first.</strong> Each converted post keeps its old URL and categories; you can revert at any time.</p>

		<?php if ( $ran ) : ?>
			<div class="notice notice-success"><p>
				<?php echo esc_html( 'revert' === $ran ? 'Reverted' : 'Converted' ); ?>: <?php echo (int) ( isset( $_GET['done'] ) ? $_GET['done'] : 0 ); // phpcs:ignore WordPress.Security.NonceVerification ?>
				<?php if ( ! empty( $_GET['no_date'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
					· <?php echo (int) $_GET['no_date']; // phpcs:ignore WordPress.Security.NonceVerification ?> have no date in the text (publish date used; check them).
				<?php endif; ?>
				· Remaining: <?php echo (int) $remaining; ?>
				<?php if ( $remaining > 0 ) : ?>
					— continuing automatically…
				<?php endif; ?>
			</p></div>
			<?php if ( $remaining > 0 ) : ?>
				<meta http-equiv="refresh" content="1;url=<?php echo esc_url( $run_url( $ran ) ); ?>">
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( isset( $_GET['linked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-<?php echo (int) $_GET['linked'] < 0 ? 'warning' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification ?>"><p>
				<?php echo (int) $_GET['linked'] < 0 ? 'Polylang is not active: install and activate it (with Indonesian and English), then run this again.' : 'Translation pairs linked: ' . (int) $_GET['linked']; // phpcs:ignore WordPress.Security.NonceVerification ?>
			</p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p>Settings saved. Check the preview below.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cemeti_convert_save">
			<?php wp_nonce_field( 'cemeti_convert_save' ); ?>

			<h2>1. How to sort the posts</h2>
			<p>
				<label><input type="radio" name="mode" value="rules" <?php checked( $rules_on ); ?>> <strong>Cemeti rules</strong> (recommended): built for the old cemeti.art site. Exhibition, Open Studio, Public Program, Residency and Open Call are recognised from their categories and titles; everything else becomes Journal (News, Essay or Curatorial Essay). Dates are read from the post text, tags become artists, other categories become Projects, and Indonesian/English language is detected.</label><br>
				<label><input type="radio" name="mode" value="manual" <?php checked( ! $rules_on ); ?>> <strong>Manual</strong>: map each category yourself (table below).</label>
			</p>

			<h2>2. Dates and old URLs</h2>
			<p>
				Old permalink pattern: <input type="text" name="old_permalink" class="regular-text" value="<?php echo esc_attr( $settings['old_permalink'] ); ?>">
				&nbsp; Old site host: <input type="text" name="old_host" value="<?php echo esc_attr( $settings['old_host'] ); ?>">
			</p>
			<p class="description">The pattern rebuilds each old URL for the redirects (cemeti.art used <code>%year%/%monthnum%/%day%/%postname%</code>). The host is used to find the English/Indonesian switch links.</p>
			<p>
				Custom field with the <strong>start date</strong> (optional): <input type="text" name="start_key" value="<?php echo esc_attr( $settings['start_key'] ); ?>" placeholder="empty = read from text">
				&nbsp; <strong>end date</strong>: <input type="text" name="end_key" value="<?php echo esc_attr( $settings['end_key'] ); ?>" placeholder="optional">
			</p>
			<p class="description">Leave both empty in Cemeti rules mode: dates such as "07 Juli - 07 Agustus 2011" or "June 16th - July 24th 2021" are read from the text; when none is found the publish date is used and the post is counted as "no date in the text".</p>

			<details <?php echo $rules_on ? '' : 'open'; ?>>
				<summary><strong>Manual mapping</strong> (used only in Manual mode)</summary>
				<p class="description">A post in several categories takes the first mapped category in this order. Leave "Skip" for categories that are not content.</p>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th>Old category</th><th>Posts</th><th>Becomes</th><th>Term (optional)</th></tr></thead>
					<tbody>
					<?php foreach ( $ordered as $cat ) : ?>
						<?php $row = isset( $settings['cats'][ (int) $cat->term_id ] ) ? $settings['cats'][ (int) $cat->term_id ] : array( 'type' => '', 'term' => '' ); ?>
						<tr>
							<td><?php echo esc_html( $cat->name ); ?></td>
							<td><?php echo (int) $cat->count; ?></td>
							<td>
								<select name="cats[<?php echo (int) $cat->term_id; ?>][type]">
									<option value="">Skip</option>
									<?php foreach ( $targets as $type => $info ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $row['type'], $type ); ?>><?php echo esc_html( $info[0] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="text" name="cats[<?php echo (int) $cat->term_id; ?>][term]" value="<?php echo esc_attr( $row['term'] ); ?>" placeholder="term name, or __name"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">Term: type an existing term name (e.g. <code>Solo</code>, <code>Group</code>, <code>Reguler</code>, <code>Essay</code>), or <code>__name</code> to use the old category's name.</p>
			</details>
			<?php submit_button( 'Save settings and preview', 'primary', 'submit', true ); ?>
		</form>

		<?php if ( $rules_on || $settings['cats'] ) : ?>
			<h2>3. Preview</h2>
			<?php $preview = cemeti_convert_preview( $settings ); ?>
			<p>Posts still to convert: <strong><?php echo (int) $preview['total']; ?></strong> · skipped: <?php echo (int) $preview['skipped']; ?><?php echo $preview['skipped_titles'] ? ' (' . esc_html( implode( ', ', $preview['skipped_titles'] ) ) . ')' : ''; ?> · with no date in the text: <?php echo (int) $preview['no_date']; ?></p>
			<ul>
				<?php foreach ( $preview['by_type'] as $type => $count ) : ?>
					<li><strong><?php echo esc_html( $targets[ $type ][0] ); ?></strong>: <?php echo (int) $count; ?> — e.g. <?php echo esc_html( implode( ' · ', $preview['samples'][ $type ] ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $preview['by_term'] ) : ?>
				<p>Terms: <?php echo esc_html( implode( ' · ', array_map( static function ( $k, $v ) { return $k . ' (' . $v . ')'; }, array_keys( $preview['by_term'] ), $preview['by_term'] ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( $rules_on ) : ?>
				<p>Language detected: <?php echo esc_html( implode( ' · ', array_map( static function ( $k, $v ) { return $k . ' (' . $v . ')'; }, array_keys( $preview['lang'] ), $preview['lang'] ) ) ); ?> (? = could not tell; set by hand)</p>
			<?php endif; ?>

			<h2>4. Convert</h2>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $run_url( 'convert' ) ); ?>" onclick="return confirm('Convert now? Make sure you have a backup.');">Convert (<?php echo (int) CEMETI_CONVERT_BATCH; ?> per step, continues by itself)</a>
				<a class="button" href="<?php echo esc_url( $run_url( 'link' ) ); ?>">Link English/Indonesian translations (needs Polylang)</a>
				<a class="button" href="<?php echo esc_url( $run_url( 'revert' ) ); ?>" onclick="return confirm('Revert every converted post back to a normal post?');">Revert all</a>
			</p>
			<p class="description">Install and activate Polylang with Indonesian and English <em>before</em> converting so each post gets its language; run "Link translations" afterwards.</p>
		<?php endif; ?>
	</div>
	<?php
}
