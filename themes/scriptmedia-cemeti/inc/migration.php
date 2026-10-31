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
 * @return array{cats:array,start_key:string,end_key:string}
 */
function cemeti_convert_settings() {
	$saved = get_option( 'cemeti_convert_map', array() );
	return array(
		'cats'      => isset( $saved['cats'] ) && is_array( $saved['cats'] ) ? $saved['cats'] : array(),
		'start_key' => isset( $saved['start_key'] ) ? (string) $saved['start_key'] : '',
		'end_key'   => isset( $saved['end_key'] ) ? (string) $saved['end_key'] : '',
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
	if ( ! $settings['cats'] ) {
		return array();
	}
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'category__in'   => array_map( 'intval', array_keys( $settings['cats'] ) ),
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
 * Convert one post. Returns a short result code for the report.
 *
 * @param int   $post_id  Post ID.
 * @param array $settings Converter settings.
 * @return string "converted", "skipped" or "no_date".
 */
function cemeti_convert_post( $post_id, array $settings ) {
	$target = cemeti_convert_pick_target( wp_get_post_categories( $post_id ), $settings['cats'] );
	if ( ! $target ) {
		return 'skipped';
	}
	$targets = cemeti_convert_targets();
	if ( ! isset( $targets[ $target['type'] ] ) ) {
		return 'skipped';
	}

	// Remember where the post came from before anything changes.
	$old_path = trim( (string) wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH ), '/' );
	update_post_meta( $post_id, '_cemeti_old_path', $old_path );
	update_post_meta( $post_id, '_cemeti_old_cats', wp_get_post_categories( $post_id ) );

	// Dates for the dated types.
	$result = 'converted';
	$set    = array();
	list( $start_key, $end_key ) = cemeti_date_keys( $target['type'] );
	if ( $end_key ) {
		$start = '' !== $settings['start_key'] ? cemeti_parse_ymd( get_post_meta( $post_id, $settings['start_key'], true ) ) : '';
		$end   = '' !== $settings['end_key'] ? cemeti_parse_ymd( get_post_meta( $post_id, $settings['end_key'], true ) ) : '';
		if ( '' === $start && '' === $settings['start_key'] ) {
			$start = get_post_time( 'Ymd', false, $post_id );
		}
		if ( '' === $start ) {
			$result = 'no_date';
		} else {
			$end = ( '' === $end || $end < $start ) ? $start : $end;
			if ( $start_key ) {
				update_post_meta( $post_id, 'cemeti_' . $start_key, $start );
				$set[] = 'cemeti_' . $start_key;
				update_post_meta( $post_id, 'cemeti_' . $end_key, $end );
			} else {
				update_post_meta( $post_id, 'cemeti_' . $end_key, $end ); // Open Call: deadline only.
			}
			$set[] = 'cemeti_' . $end_key;
		}
	}
	update_post_meta( $post_id, '_cemeti_converted_meta', $set );

	set_post_type( $post_id, $target['type'] );

	// Term: "__name" reuses the old category's name, anything else is an existing term slug.
	$taxonomy = $targets[ $target['type'] ][1];
	if ( $taxonomy && '' !== $target['term'] ) {
		$term = '__name' === $target['term'] ? (string) get_cat_name( $target['cat'] ) : $target['term'];
		if ( '' !== $term ) {
			wp_set_object_terms( $post_id, array( $term ), $taxonomy );
		}
	}

	update_post_meta( $post_id, '_cemeti_converted_at', time() );
	return $result;
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
	foreach ( array( '_cemeti_converted_at', '_cemeti_converted_meta', '_cemeti_old_path', '_cemeti_old_cats' ) as $key ) {
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
 * @return array{total:int,by_type:array<string,int>,skipped:int,no_date:int,samples:array<string,string[]>}
 */
function cemeti_convert_preview( array $settings ) {
	$preview = array( 'total' => 0, 'by_type' => array(), 'skipped' => 0, 'no_date' => 0, 'samples' => array() );
	foreach ( cemeti_convert_pending_ids( $settings ) as $post_id ) {
		++$preview['total'];
		$target = cemeti_convert_pick_target( wp_get_post_categories( $post_id ), $settings['cats'] );
		if ( ! $target ) {
			++$preview['skipped'];
			continue;
		}
		$type = $target['type'];
		$preview['by_type'][ $type ] = isset( $preview['by_type'][ $type ] ) ? $preview['by_type'][ $type ] + 1 : 1;
		if ( ! isset( $preview['samples'][ $type ] ) || count( $preview['samples'][ $type ] ) < 3 ) {
			$preview['samples'][ $type ][] = get_the_title( $post_id );
		}
		list( , $end_key ) = cemeti_date_keys( $type );
		if ( $end_key && '' !== $settings['start_key'] && '' === cemeti_parse_ymd( get_post_meta( $post_id, $settings['start_key'], true ) ) ) {
			++$preview['no_date'];
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
			'cats'      => $cats,
			'start_key' => isset( $_POST['start_key'] ) ? sanitize_key( wp_unslash( $_POST['start_key'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'end_key'   => isset( $_POST['end_key'] ) ? sanitize_key( wp_unslash( $_POST['end_key'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		),
		false
	);

	// Old category archive URLs, for redirects.
	$legacy = array();
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
	$mode     = isset( $_GET['mode'] ) && 'revert' === $_GET['mode'] ? 'revert' : 'convert'; // phpcs:ignore WordPress.Security.NonceVerification
	$settings = cemeti_convert_settings();
	$counts   = array( 'converted' => 0, 'skipped' => 0, 'no_date' => 0 );

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
	$settings = cemeti_convert_settings();
	$targets  = cemeti_convert_targets();
	$cats     = get_categories( array( 'hide_empty' => false ) );
	$ran      = isset( $_GET['ran'] ) ? sanitize_key( wp_unslash( $_GET['ran'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$remaining = isset( $_GET['remaining'] ) ? (int) $_GET['remaining'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$run_url  = static function ( $mode ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_convert_run&mode=' . $mode ), 'cemeti_convert_run' );
	};

	// Rows in saved priority order first, then the remaining categories.
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
					· <?php echo (int) $_GET['no_date']; // phpcs:ignore WordPress.Security.NonceVerification ?> without a readable date (kept as posts of the new type, add the date by hand).
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
		<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p>Mapping saved. Check the preview below.</p></div>
		<?php endif; ?>

		<h2>1. Map categories</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cemeti_convert_save">
			<?php wp_nonce_field( 'cemeti_convert_save' ); ?>
			<p class="description">A post in several categories takes the first mapped category in this order. Leave "Skip" for categories that are not content (e.g. Uncategorized).</p>
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
						<td>
							<input type="text" name="cats[<?php echo (int) $cat->term_id; ?>][term]" value="<?php echo esc_attr( $row['term'] ); ?>" placeholder="term slug, or __name">
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">Term: type an existing term slug (e.g. <code>solo</code>, <code>group</code>, <code>reguler</code>, <code>essay</code>), or <code>__name</code> to use the old category's name as the term (created if missing). Only Exhibition, Residency and Journal have terms.</p>

			<h2>2. Event dates</h2>
			<p>
				Custom field with the <strong>start date</strong>: <input type="text" name="start_key" value="<?php echo esc_attr( $settings['start_key'] ); ?>" placeholder="e.g. start_date">
				&nbsp; <strong>end date</strong>: <input type="text" name="end_key" value="<?php echo esc_attr( $settings['end_key'] ); ?>" placeholder="optional">
			</p>
			<p class="description">Leave the start field empty to use each post's <em>publish date</em>. Values like 20260306, 2026-03-06 or "6 March 2026" are read. Journal has no event date.</p>
			<?php submit_button( 'Save mapping and preview', 'primary', 'submit', false ); ?>
		</form>

		<?php if ( $settings['cats'] ) : ?>
			<h2>3. Preview</h2>
			<?php $preview = cemeti_convert_preview( $settings ); ?>
			<p>Posts still to convert: <strong><?php echo (int) $preview['total']; ?></strong> · skipped (no mapped category): <?php echo (int) $preview['skipped']; ?> · without a readable start date: <?php echo (int) $preview['no_date']; ?></p>
			<ul>
				<?php foreach ( $preview['by_type'] as $type => $count ) : ?>
					<li><strong><?php echo esc_html( $targets[ $type ][0] ); ?></strong>: <?php echo (int) $count; ?> — e.g. <?php echo esc_html( implode( ' · ', $preview['samples'][ $type ] ) ); ?></li>
				<?php endforeach; ?>
			</ul>

			<h2>4. Convert</h2>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $run_url( 'convert' ) ); ?>" onclick="return confirm('Convert now? Make sure you have a backup.');">Convert (<?php echo (int) CEMETI_CONVERT_BATCH; ?> per step, continues by itself)</a>
				<a class="button" href="<?php echo esc_url( $run_url( 'revert' ) ); ?>" onclick="return confirm('Revert every converted post back to a normal post?');">Revert all</a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}
