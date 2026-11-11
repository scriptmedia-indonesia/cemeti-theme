<?php
/**
 * Media of imported posts.
 *
 * Two jobs:
 *  1. Find the Media Library item of a picture that is written in an old post. The old address (cemeti.art) is
 *     not the new site's address and the old "wp-image-123" number is not the new ID, so the file path under
 *     /wp-content/uploads/ is what identifies a picture.
 *  2. Repair media after an import that did not bring the files: download the missing files from the old site,
 *     create Media Library items for pictures that have none, and give posts without a valid featured image the
 *     first picture of their text (Tools → Cemeti Converter → "0. Media").
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Does a path climb out of its folder ("../x", "a/../b")? A file NAME with two dots ("photo-2017..jpg") is fine.
 *
 * @param string $rel Relative path.
 * @return bool
 */
function cemeti_media_path_climbs( $rel ) {
	return 1 === preg_match( '#(^|/)\.\.(/|$)#', str_replace( '\\', '/', (string) $rel ) );
}

/**
 * Path of an upload relative to the uploads folder ("2021/06/photo.jpg"), whatever the host.
 *
 * @param string $url Address.
 * @return string Empty when it is not an uploads address.
 */
function cemeti_media_rel_path( $url ) {
	$url = preg_replace( '/[?#].*$/', '', (string) $url );
	if ( ! preg_match( '#/wp-content/uploads/(.+)$#', $url, $m ) ) {
		return '';
	}
	$rel = rawurldecode( $m[1] );
	return cemeti_media_path_climbs( $rel ) ? '' : $rel;
}

/**
 * The names a file can be stored under: as written, without the "-300x200" size, and the "-scaled" original.
 *
 * @param string $rel Relative path.
 * @return string[]
 */
function cemeti_media_rel_candidates( $rel ) {
	$plain = preg_replace( '/-\d+x\d+(\.[A-Za-z0-9]+)$/', '$1', $rel );
	$plain = preg_replace( '/-scaled(\.[A-Za-z0-9]+)$/', '$1', $plain );
	$out   = array( $rel, $plain, preg_replace( '/(\.[A-Za-z0-9]+)$/', '-scaled$1', $plain ) );
	return array_values( array_unique( $out ) );
}

/**
 * Media Library item for an uploads address, found by the file path.
 *
 * @param string $url Address of the picture or file.
 * @return int Attachment ID, 0 when there is none.
 */
function cemeti_media_find_by_url( $url ) {
	$rel = cemeti_media_rel_path( $url );
	if ( '' === $rel ) {
		return 0;
	}
	foreach ( cemeti_media_rel_candidates( $rel ) as $candidate ) {
		$found = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'meta_key'         => '_wp_attached_file', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $candidate, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		if ( $found ) {
			return (int) $found[0];
		}
	}
	return 0;
}

/**
 * Name of a file without size and "-scaled", lower case, to compare two addresses of the same picture.
 *
 * @param string $path Path or address.
 * @return string
 */
function cemeti_media_basename_key( $path ) {
	$name = strtolower( basename( preg_replace( '/[?#].*$/', '', (string) $path ) ) );
	$name = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/', '$1', $name );
	return preg_replace( '/-scaled(\.[a-z0-9]+)$/', '$1', $name );
}

/**
 * Attachment ID of a picture of an old text: by its address (any host), else by its wp-image-ID class, but
 * only when that item is really the same file (the importer gives new IDs, so an old number can name another
 * picture).
 *
 * @param string $src   The picture's src.
 * @param string $class The picture's class attribute.
 * @return int 0 when unknown.
 */
function cemeti_media_img_id( $src, $class = '' ) {
	$src  = (string) $src;
	$bare = preg_replace( '/\?.*$/', '', $src );
	$bare = preg_replace( '/-\d+x\d+(\.[a-z]+)$/i', '$1', $bare );
	$id   = function_exists( 'attachment_url_to_postid' ) && '' !== $bare ? (int) attachment_url_to_postid( $bare ) : 0;
	if ( ! $id ) {
		$id = cemeti_media_find_by_url( $src );
	}
	if ( ! $id && preg_match( '/wp-image-(\d+)/', (string) $class, $m ) && 'attachment' === get_post_type( (int) $m[1] ) ) {
		$file = (string) get_post_meta( (int) $m[1], '_wp_attached_file', true );
		if ( '' === $src || '' === $file || cemeti_media_basename_key( $file ) === cemeti_media_basename_key( $src ) ) {
			$id = (int) $m[1];
		}
	}
	return $id;
}

/**
 * Attachment IDs of every picture in some HTML, in order (pictures that cannot be found are left out).
 *
 * @param string $html  HTML.
 * @param int    $limit Stop after this many (0 = all).
 * @return int[]
 */
function cemeti_media_ids_in_html( $html, $limit = 0 ) {
	$ids = array();
	if ( preg_match_all( '/<img\b[^>]*>/i', (string) $html, $tags ) ) {
		foreach ( $tags[0] as $tag ) {
			$src   = preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $s ) ? html_entity_decode( $s[1] ) : '';
			$class = preg_match( '/\bclass=["\']([^"\']+)["\']/i', $tag, $c ) ? $c[1] : '';
			$id    = cemeti_media_img_id( $src, $class );
			if ( $id ) {
				$ids[] = $id;
				if ( $limit && count( $ids ) >= $limit ) {
					break;
				}
			}
		}
	}
	return $ids;
}

/* ------------------------------------------------------------------------------------------------------------
 * Repair
 * ---------------------------------------------------------------------------------------------------------- */

/**
 * Address of the old site's root, from the converter's "Old site host".
 *
 * @return string "https://cemeti.art".
 */
function cemeti_media_remote_base() {
	$settings = function_exists( 'cemeti_convert_settings' ) ? cemeti_convert_settings() : array( 'old_host' => 'cemeti.art' );
	$host     = trim( (string) $settings['old_host'] );
	$host     = '' === $host ? 'cemeti.art' : $host;
	return preg_match( '#^https?://#i', $host ) ? untrailingslashit( $host ) : 'https://' . rtrim( $host, '/' );
}

/**
 * Is an address one the repair may download from the old site for: the old site's, or this site's own (the
 * address of a file that was never imported)? Other websites never are.
 *
 * @param string $url Address.
 * @return bool
 */
function cemeti_media_is_old_site( $url ) {
	$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
	if ( '' === $host ) {
		return 0 === strpos( (string) $url, '/wp-content/uploads/' );
	}
	// The old site, and this site: after a search-and-replace of the address (or an importer that rewrote it) the
	// text names THIS site for files that only exist on the old one.
	$norm    = static function ( $h ) {
		return preg_replace( '/^www\./i', '', strtolower( (string) $h ) );
	};
	$allowed = array(
		$norm( wp_parse_url( cemeti_media_remote_base(), PHP_URL_HOST ) ),
		$norm( wp_parse_url( home_url(), PHP_URL_HOST ) ),
		$norm( wp_parse_url( site_url(), PHP_URL_HOST ) ),
	);
	return in_array( $norm( $host ), $allowed, true );
}

/**
 * The time limit of the running repair step and its network trouble, shared by the functions below.
 * A web host (and Cloudflare, which cuts a request after 100 s) will not wait for a page that downloads
 * hundreds of pictures, so every download checks the clock first.
 *
 * @param string     $what 'set' (float deadline), 'passed', 'net_fail' (add one), 'net_count', 'reset'.
 * @param float|null $value Deadline for 'set'.
 * @return mixed
 */
function cemeti_media_clock( $what, $value = null ) {
	static $deadline = 0.0;
	static $net      = 0;
	switch ( $what ) {
		case 'set':
			$deadline = (float) $value;
			$net      = 0;
			return null;
		case 'passed':
			return $deadline > 0 && microtime( true ) >= $deadline;
		case 'net_fail':
			return ++$net;
		case 'net_count':
			return $net;
		default:
			$deadline = 0.0;
			$net      = 0;
			return null;
	}
}

/**
 * Make sure the file of an upload exists on this site, downloading it from the old site when it does not.
 *
 * @param string $rel Path relative to the uploads folder.
 * @return string|WP_Error Full path of the file.
 */
function cemeti_media_fetch( $rel ) {
	$rel = ltrim( str_replace( '\\', '/', (string) $rel ), '/' );
	if ( '' === $rel || cemeti_media_path_climbs( $rel ) ) {
		return new WP_Error( 'cemeti_media_path', 'Unusable file path.' );
	}
	$uploads = wp_get_upload_dir();
	$dest    = trailingslashit( $uploads['basedir'] ) . $rel;
	if ( file_exists( $dest ) ) {
		return $dest;
	}
	if ( cemeti_media_clock( 'passed' ) || cemeti_media_clock( 'net_count' ) >= 3 ) {
		return new WP_Error( 'cemeti_media_deadline', 'Out of time for this step.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$remote = cemeti_media_remote_base() . '/wp-content/uploads/' . implode( '/', array_map( 'rawurlencode', explode( '/', $rel ) ) );
	$tmp    = download_url( $remote, 10 );
	if ( is_wp_error( $tmp ) ) {
		if ( 'http_request_failed' === $tmp->get_error_code() ) {
			cemeti_media_clock( 'net_fail' ); // The server could not even reach the old site.
		}
		return new WP_Error( 'cemeti_media_download', $remote . ' - ' . $tmp->get_error_message() );
	}
	if ( preg_match( '/\.(jpe?g|png|gif|webp)$/i', $rel ) && ! @getimagesize( $tmp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		return new WP_Error( 'cemeti_media_image', $remote . ' - not a picture.' );
	}
	wp_mkdir_p( dirname( $dest ) );
	if ( ! @copy( $tmp, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		return new WP_Error( 'cemeti_media_write', 'Could not write ' . $rel . ' (check the uploads folder permissions).' );
	}
	@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
	return $dest;
}

/**
 * Build the sizes of an attachment from its file.
 *
 * @param int    $id   Attachment ID.
 * @param string $file Full path.
 */
function cemeti_media_build_sizes( $id, $file ) {
	if ( ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $file ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$meta = wp_generate_attachment_metadata( $id, $file );
	if ( $meta ) {
		wp_update_attachment_metadata( $id, $meta );
	}
}

/**
 * Restore the file of an attachment whose file is missing.
 *
 * @param int $id Attachment ID.
 * @return string 'ok' (file was there), 'restored', or an error message.
 */
function cemeti_media_restore_attachment( $id ) {
	$rel = (string) get_post_meta( $id, '_wp_attached_file', true );
	if ( '' === $rel ) {
		$rel = cemeti_media_rel_path( (string) get_post_field( 'guid', $id ) );
	}
	if ( '' === $rel ) {
		return 'No file path recorded.';
	}
	$uploads = wp_get_upload_dir();
	if ( file_exists( trailingslashit( $uploads['basedir'] ) . $rel ) ) {
		return 'ok';
	}
	$file = cemeti_media_fetch( $rel );
	if ( is_wp_error( $file ) ) {
		return $file->get_error_message();
	}
	update_post_meta( $id, '_wp_attached_file', $rel );
	cemeti_media_build_sizes( $id, $file );
	return 'restored';
}

/**
 * Create the Media Library item of a picture that has none, by downloading it from the old site.
 *
 * @param string $url    Address in the old text.
 * @param int    $parent Post it belongs to.
 * @return int|string Attachment ID, or an error message.
 */
function cemeti_media_create_from_url( $url, $parent ) {
	$rel = cemeti_media_rel_path( $url );
	if ( '' === $rel || ! cemeti_media_is_old_site( $url ) ) {
		return 'Not a picture of the old site: ' . $url;
	}
	// A size copy ("photo-300x200.jpg") is not kept in the old site's library: ask for the original.
	$original = cemeti_media_rel_candidates( $rel )[1];
	$file     = cemeti_media_fetch( $original );
	if ( is_wp_error( $file ) && $original !== $rel ) {
		$original = $rel;
		$file     = cemeti_media_fetch( $original );
	}
	if ( is_wp_error( $file ) ) {
		return $file->get_error_message();
	}
	$uploads = wp_get_upload_dir();
	$type    = wp_check_filetype( basename( $file ) );
	$id      = wp_insert_attachment(
		array(
			'post_mime_type' => (string) $type['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
			'post_status'    => 'inherit',
			'post_parent'    => (int) $parent,
			'guid'           => trailingslashit( $uploads['baseurl'] ) . $original,
		),
		$file,
		(int) $parent
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 'Could not create the Media Library item for ' . $original;
	}
	update_post_meta( $id, '_wp_attached_file', $original );
	cemeti_media_build_sizes( (int) $id, $file );
	return (int) $id;
}

/**
 * Repair the pictures of one post: every picture of its text gets a Media Library item, and a post without a
 * valid featured image gets the first picture of its text.
 *
 * @param int $post_id Post ID.
 * @return array{created:int,thumb:int,failed:string[],missing:int}
 */
function cemeti_media_repair_post( $post_id ) {
	$post   = get_post( $post_id );
	$result = array( 'created' => 0, 'thumb' => 0, 'failed' => array(), 'missing' => 0, 'incomplete' => false );
	if ( ! $post ) {
		return $result;
	}
	// The original text of a converted post holds the pictures the gallery is built from.
	$html = (string) $post->post_content . "\n" . (string) get_post_meta( $post_id, '_cemeti_original_content', true );
	$ids  = array();
	if ( preg_match_all( '/<img\b[^>]*>/i', $html, $tags ) ) {
		foreach ( $tags[0] as $tag ) {
			if ( cemeti_media_clock( 'passed' ) || cemeti_media_clock( 'net_count' ) >= 3 ) {
				$result['incomplete'] = true; // Out of time: the post is looked at again in the next step.
				return $result;
			}
			$src   = preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $s ) ? html_entity_decode( $s[1] ) : '';
			$class = preg_match( '/\bclass=["\']([^"\']+)["\']/i', $tag, $c ) ? $c[1] : '';
			$id    = cemeti_media_img_id( $src, $class );
			if ( $id ) {
				$uploads = wp_get_upload_dir();
				$rel     = (string) get_post_meta( $id, '_wp_attached_file', true );
				if ( '' !== $rel && ! file_exists( trailingslashit( $uploads['basedir'] ) . $rel ) ) {
					$state = cemeti_media_restore_attachment( $id );
					if ( 'restored' !== $state && 'ok' !== $state ) {
						$result['failed'][] = $state;
						$id                 = 0;
					}
				}
			} elseif ( '' !== $src && cemeti_media_is_old_site( $src ) ) {
				$made = cemeti_media_create_from_url( $src, $post_id );
				if ( is_int( $made ) ) {
					$id = $made;
					++$result['created'];
				} else {
					$result['failed'][] = $made;
				}
			} elseif ( '' !== $src ) {
				$result['failed'][] = 'Skipped (not an address of the old site or of this site): ' . $src;
			}
			if ( $id ) {
				$ids[] = $id;
			} else {
				++$result['missing'];
			}
		}
	}
	$thumb = (int) get_post_meta( $post_id, '_thumbnail_id', true ); // The stored one, not the picture the site would show instead.
	$valid = $thumb && 'attachment' === get_post_type( $thumb );
	if ( $valid ) {
		$uploads = wp_get_upload_dir();
		$rel     = (string) get_post_meta( $thumb, '_wp_attached_file', true );
		if ( '' !== $rel && ! file_exists( trailingslashit( $uploads['basedir'] ) . $rel ) ) {
			$state = cemeti_media_restore_attachment( $thumb );
			if ( 'restored' !== $state && 'ok' !== $state ) {
				$result['failed'][] = $state;
				$valid              = false;
			}
		}
	}
	if ( ! $valid && $ids ) {
		update_post_meta( $post_id, '_thumbnail_id', (int) $ids[0] );
		$result['thumb'] = 1;
	}
	if ( $result['missing'] ) {
		update_post_meta( $post_id, '_cemeti_media_missing', $result['missing'] );
	} else {
		delete_post_meta( $post_id, '_cemeti_media_missing' );
	}
	return $result;
}

/**
 * Post types the repair walks through: normal posts and every Cemeti type.
 *
 * @return string[]
 */
function cemeti_media_post_types() {
	$types = array( 'post' );
	foreach ( array_keys( cemeti_config( 'post-types' ) ) as $type ) {
		if ( 'application' !== $type ) {
			$types[] = $type;
		}
	}
	return $types;
}

/**
 * The saved report of the repair (counts and the last failures).
 *
 * @return array
 */
function cemeti_media_report() {
	$report = get_option( 'cemeti_media_report', array() );
	return array_merge(
		array( 'ok' => 0, 'restored' => 0, 'failed' => 0, 'posts' => 0, 'created' => 0, 'thumbs' => 0, 'errors' => array() ),
		is_array( $report ) ? $report : array()
	);
}

/**
 * Add a failure to the report (the last 15 are kept).
 *
 * @param array  $report  Report.
 * @param string $message Message.
 * @return array
 */
function cemeti_media_report_error( array $report, $message ) {
	if ( false !== strpos( (string) $message, 'Out of time for this step' ) ) {
		return $report; // Not a problem of the picture: the step simply ended.
	}
	$report['errors'][] = (string) $message;
	$report['errors']   = array_slice( array_values( array_unique( $report['errors'] ) ), -15 );
	return $report;
}

/**
 * Attachments whose file has not been checked yet.
 *
 * @param int $limit Maximum.
 * @return array{ids:int[],total:int}
 */
function cemeti_media_unchecked_attachments( $limit ) {
	$q = new WP_Query(
		array(
			'post_type'        => 'attachment',
			'post_status'      => 'any',
			'posts_per_page'   => $limit,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'meta_query'       => array( array( 'key' => '_cemeti_media_state', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return array( 'ids' => array_map( 'intval', $q->posts ), 'total' => (int) $q->found_posts );
}

/**
 * Posts whose pictures have not been repaired yet.
 *
 * @param int $limit Maximum.
 * @return array{ids:int[],total:int}
 */
function cemeti_media_unrepaired_posts( $limit ) {
	$q = new WP_Query(
		array(
			'post_type'        => cemeti_media_post_types(),
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'   => $limit,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'meta_query'       => array( array( 'key' => '_cemeti_media_post', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return array( 'ids' => array_map( 'intval', $q->posts ), 'total' => (int) $q->found_posts );
}

/**
 * One time-boxed step of the repair; returns what is left.
 *
 * @param string $phase 'files' or 'posts'.
 * @param float  $budget Seconds a step may take.
 * @return int Items still to do.
 */
function cemeti_media_step( $phase, $budget = 12.0 ) {
	$report = cemeti_media_report();
	$start  = microtime( true );
	cemeti_media_clock( 'set', $start + $budget );
	if ( 'files' === $phase ) {
		$batch = cemeti_media_unchecked_attachments( 200 );
		foreach ( $batch['ids'] as $id ) {
			$state = cemeti_media_restore_attachment( $id );
			if ( cemeti_media_clock( 'passed' ) && 'ok' !== $state && 'restored' !== $state ) {
				break; // Not checked, not marked: it is done again in the next step.
			}
			if ( cemeti_media_clock( 'net_count' ) >= 3 ) {
				break;
			}
			if ( 'ok' === $state ) {
				++$report['ok'];
				update_post_meta( $id, '_cemeti_media_state', 'ok' );
			} elseif ( 'restored' === $state ) {
				++$report['restored'];
				update_post_meta( $id, '_cemeti_media_state', 'restored' );
			} else {
				++$report['failed'];
				update_post_meta( $id, '_cemeti_media_state', 'failed' );
				$report = cemeti_media_report_error( $report, $state );
			}
			if ( cemeti_media_clock( 'passed' ) ) {
				break;
			}
		}
		return cemeti_media_finish_step( $report, cemeti_media_unchecked_attachments( 1 )['total'] );
	}
	$batch = cemeti_media_unrepaired_posts( 50 );
	foreach ( $batch['ids'] as $post_id ) {
		$one = cemeti_media_repair_post( $post_id );
		if ( $one['incomplete'] ) {
			break; // Out of time or the old site cannot be reached: not marked, done again next step.
		}
		update_post_meta( $post_id, '_cemeti_media_post', 1 );
		++$report['posts'];
		$report['created'] += $one['created'];
		$report['thumbs']  += $one['thumb'];
		foreach ( $one['failed'] as $message ) {
			$report = cemeti_media_report_error( $report, $message );
		}
		if ( cemeti_media_clock( 'passed' ) ) {
			break;
		}
	}
	return cemeti_media_finish_step( $report, cemeti_media_unrepaired_posts( 1 )['total'] );
}

/**
 * Save the report at the end of a step. -1 = stop: the server could not reach the old site (three times in a row),
 * so going on would only make every step wait for the same timeouts.
 *
 * @param array $report    Report.
 * @param int   $remaining Items left.
 * @return int
 */
function cemeti_media_finish_step( array $report, $remaining ) {
	$stopped = cemeti_media_clock( 'net_count' ) >= 3;
	if ( $stopped ) {
		$report = cemeti_media_report_error( $report, 'This server could not connect to ' . cemeti_media_remote_base() . ' (timeouts). Ask the hosting to allow outgoing connections to it, or try again later.' );
	}
	update_option( 'cemeti_media_report', $report, false );
	cemeti_media_clock( 'reset' );
	return $stopped ? -1 : (int) $remaining;
}

/**
 * Prepare a phase: "retry" forgets the failed files, "reset" forgets everything checked, "posts" with $fresh looks again at
 * the posts that still have missing pictures.
 *
 * @param string $phase 'files', 'posts', 'retry' or 'reset'.
 * @param bool   $fresh Pressing the button anew (not a continuation).
 * @return string The phase to run: 'files', 'posts', or 'done' when it was only a reset.
 */
function cemeti_media_prepare( $phase, $fresh = false ) {
	if ( 'reset' === $phase || 'retry' === $phase ) {
		$batch = new WP_Query(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array( array( 'key' => '_cemeti_media_state', 'value' => 'failed' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $batch->posts as $id ) {
			delete_post_meta( (int) $id, '_cemeti_media_state' );
		}
		if ( 'reset' === $phase ) {
			$posts = new WP_Query( array( 'post_type' => cemeti_media_post_types(), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true, 'meta_key' => '_cemeti_media_post' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			foreach ( $posts->posts as $id ) {
				delete_post_meta( (int) $id, '_cemeti_media_post' );
			}
		}
		delete_option( 'cemeti_media_report' );
		return 'done';
	}
	$phase = 'posts' === $phase ? 'posts' : 'files';
	if ( 'posts' === $phase && $fresh ) {
		$again = new WP_Query( array( 'post_type' => cemeti_media_post_types(), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true, 'meta_key' => '_cemeti_media_missing' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $again->posts as $id ) {
			delete_post_meta( (int) $id, '_cemeti_media_post' );
		}
	}
	return $phase;
}

/**
 * Handler of the buttons without JavaScript: one step, then back to the page (which continues by itself while items remain).
 */
function cemeti_media_run() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'scriptmedia-cemeti' ) );
	}
	check_admin_referer( 'cemeti_media_run' );
	$phase = isset( $_GET['phase'] ) ? sanitize_key( wp_unslash( $_GET['phase'] ) ) : 'files'; // phpcs:ignore WordPress.Security.NonceVerification
	$run   = cemeti_media_prepare( $phase, ! empty( $_GET['fresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'done' === $run ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'cemeti-converter', 'media' => 'reset' ), admin_url( 'tools.php' ) ) );
		exit;
	}
	$remaining = cemeti_media_step( $run );
	wp_safe_redirect( add_query_arg( array( 'page' => 'cemeti-converter', 'media' => $run, 'media_left' => (int) $remaining ), admin_url( 'tools.php' ) ) );
	exit;
}
add_action( 'admin_post_cemeti_media_run', 'cemeti_media_run' );

/**
 * One step for the page's own runner (admin-ajax): the page stays open, shows progress and retries by itself when the
 * server answers with an error, instead of walking through a chain of redirects that a host can cut anywhere.
 */
function cemeti_media_ajax() {
	check_ajax_referer( 'cemeti_media_run', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
	}
	$phase = isset( $_POST['phase'] ) ? sanitize_key( wp_unslash( $_POST['phase'] ) ) : 'files'; // phpcs:ignore WordPress.Security.NonceVerification
	$run   = cemeti_media_prepare( $phase, ! empty( $_POST['fresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$left  = 'done' === $run ? 0 : cemeti_media_step( $run );
	wp_send_json_success(
		array(
			'phase'     => $run,
			'remaining' => (int) $left,
			'stopped'   => $left < 0,
			'report'    => cemeti_media_report(),
		)
	);
}
add_action( 'wp_ajax_cemeti_media_step', 'cemeti_media_ajax' );

/**
 * The "0. Media" block of the converter page.
 */
function cemeti_media_panel() {
	$phase  = isset( $_GET['media'] ) ? sanitize_key( wp_unslash( $_GET['media'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$left   = isset( $_GET['media_left'] ) ? (int) $_GET['media_left'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$report = cemeti_media_report();
	$url    = static function ( $p ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_media_run&phase=' . $p ), 'cemeti_media_run' );
	};
	$attachments = (int) ( wp_count_posts( 'attachment' )->inherit ?? 0 );
	?>
	<h2>0. Media (do this first if pictures are missing after the import)</h2>
	<p>The importer only brings the pictures when it can download them from the old site, and posts then show no pictures. This step, using the old site <strong><?php echo esc_html( cemeti_media_remote_base() ); ?></strong> (change the host in section 2):</p>
	<ol>
		<li><strong>Restore files</strong>: every item of the Media Library (<?php echo (int) $attachments; ?>) whose file is missing on this site is downloaded again from the old site and its sizes are made.</li>
		<li><strong>Fix posts</strong>: every picture written in a post's text gets a Media Library item (downloaded if needed) and a post without a valid featured image gets the first picture of its text.</li>
	</ol>
	<p class="description">It works in steps of about 12 seconds and continues by itself. It is safe to run again; pictures already there are not downloaded twice. Afterwards use <strong>Revert all</strong> and <strong>Convert</strong> again, so the gallery and documentation are built from the repaired pictures.</p>
	<?php if ( $phase && 'reset' !== $phase ) : ?>
		<div class="notice notice-info"><p>
			<?php echo 'posts' === $phase ? 'Fixing posts' : 'Restoring files'; ?>: <?php echo (int) $left; ?> left
			<?php echo $left < 0 ? ' — STOPPED: this server cannot reach the old site (see the problem below).' : ( $left > 0 ? ' — continuing automatically…' : ' — finished.' ); ?>
		</p></div>
		<?php if ( $left > 0 ) : ?>
			<meta http-equiv="refresh" content="1;url=<?php echo esc_url( $url( $phase ) ); ?>">
		<?php endif; ?>
	<?php elseif ( 'reset' === $phase ) : ?>
		<div class="notice notice-success"><p>Checks cleared. Run the steps again.</p></div>
	<?php endif; ?>
	<p>
		Files checked OK: <strong><?php echo (int) $report['ok']; ?></strong> · restored: <strong><?php echo (int) $report['restored']; ?></strong> · failed: <strong><?php echo (int) $report['failed']; ?></strong>
		&nbsp;|&nbsp; Posts fixed: <strong><?php echo (int) $report['posts']; ?></strong> · pictures added: <strong><?php echo (int) $report['created']; ?></strong> · featured images set: <strong><?php echo (int) $report['thumbs']; ?></strong>
	</p>
	<?php if ( $report['errors'] ) : ?>
		<details open><summary>Last problems (usually: the old site no longer has that file, or the uploads folder is not writable)</summary>
			<ul><?php foreach ( $report['errors'] as $message ) : ?><li><code><?php echo esc_html( $message ); ?></code></li><?php endforeach; ?></ul>
		</details>
	<?php endif; ?>
	<?php
	$lost = new WP_Query( array( 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => 10, 'meta_key' => '_cemeti_media_missing', 'suppress_filters' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $lost->posts ) :
		?>
		<p><strong>Posts that still have pictures which are not in the Media Library</strong> (the pictures stay in their text; the old site's file may be gone):</p>
		<ul>
			<?php foreach ( $lost->posts as $one ) : ?>
				<li><a href="<?php echo esc_url( get_edit_post_link( $one->ID ) ); ?>"><?php echo esc_html( get_the_title( $one ) ); ?></a> (<?php echo (int) get_post_meta( $one->ID, '_cemeti_media_missing', true ); ?>)</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<div id="cemeti-media-runner" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cemeti_media_run' ) ); ?>">
		<p class="cemeti-media-status notice inline" hidden></p>
		<p>
			<a class="button button-primary" data-phase="files" href="<?php echo esc_url( $url( 'files' ) ); ?>">1. Restore files</a>
			<a class="button button-primary" data-phase="posts" href="<?php echo esc_url( $url( 'posts' ) . '&fresh=1' ); ?>">2. Fix posts</a>
			<a class="button" data-phase="retry" href="<?php echo esc_url( $url( 'retry' ) ); ?>">Retry the failed files</a>
			<a class="button" data-phase="reset" href="<?php echo esc_url( $url( 'reset' ) ); ?>">Start over</a>
			<button type="button" class="button cemeti-media-stop" hidden>Stop</button>
		</p>
	</div>
	<?php
}

/**
 * Load the runner on the converter page only.
 *
 * @param string $hook Admin page hook.
 */
function cemeti_media_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'tools_page_cemeti-converter', 'settings_page_cemeti-settings' ), true ) ) {
		return;
	}
	$rel = 'assets/js/admin-media.js';
	wp_enqueue_script( 'cemeti-admin-media', CEMETI_URI . '/' . $rel, array(), cemeti_asset_version( $rel ), true );
}
add_action( 'admin_enqueue_scripts', 'cemeti_media_admin_assets' );

/**
 * Entries (of every language) that have no usable featured image: none at all, or one that points to a picture which is
 * not in the Media Library (an old number kept by the import).
 *
 * @return int[]
 */
function cemeti_entries_without_thumbnail() {
	$types = array_values( array_intersect( array_keys( cemeti_config( 'post-types' ) ), array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal' ) ) );
	$query = new WP_Query(
		array(
			'post_type'        => $types,
			'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);
	$ids = array_map( 'intval', $query->posts );
	if ( function_exists( 'update_meta_cache' ) ) {
		update_meta_cache( 'post', $ids );
	}
	return array_values(
		array_filter(
			$ids,
			static function ( $post_id ) {
				$thumb = (int) get_post_meta( $post_id, '_thumbnail_id', true );
				return ! $thumb || 'attachment' !== get_post_type( $thumb );
			}
		)
	);
}

/**
 * One time-boxed step of "Set missing featured images": entries are handled until the time is up, then the rest is
 * left for the next step (a long single request is cut by the host or by Cloudflare). Entries for which no picture exists
 * are remembered and skipped, so the run ends.
 *
 * @param float $budget Seconds.
 * @param bool  $fresh  Start from the beginning (forget the entries that had no picture and the counts).
 * @return array{remaining:int,filled:int,nothing:int}
 */
function cemeti_thumbs_step( $budget = 8.0, $fresh = false ) {
	$state = get_option( 'cemeti_thumbs_state', array() );
	if ( $fresh || ! is_array( $state ) ) {
		$state = array();
	}
	$state = array_merge( array( 'filled' => 0, 'skip' => array() ), $state );
	$start = microtime( true );
	$todo  = array_values( array_diff( cemeti_entries_without_thumbnail(), array_map( 'intval', $state['skip'] ) ) );
	foreach ( $todo as $index => $post_id ) {
		if ( microtime( true ) - $start > $budget ) {
			break;
		}
		$found = cemeti_find_entry_picture( get_post( $post_id ) );
		if ( $found ) {
			update_post_meta( $post_id, '_thumbnail_id', $found );
			++$state['filled'];
		} else {
			if ( get_post_meta( $post_id, '_thumbnail_id', true ) ) {
				delete_post_meta( $post_id, '_thumbnail_id' ); // It pointed to nothing: the editor no longer says "Could not retrieve the featured image data".
			}
			$state['skip'][] = $post_id;
		}
		unset( $todo[ $index ] );
	}
	update_option( 'cemeti_thumbs_state', $state, false );
	return array( 'remaining' => count( $todo ), 'filled' => (int) $state['filled'], 'nothing' => count( $state['skip'] ) );
}

/**
 * Settings → Cemeti → "Set missing featured images", without JavaScript: one step, then back to Settings.
 */
function cemeti_handle_fill_thumbnails() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'scriptmedia-cemeti' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'cemeti_fill_thumbs' );
	$result = cemeti_thumbs_step( 8.0, empty( $_GET['more'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	wp_safe_redirect( add_query_arg( array( 'cemeti_thumbs_filled' => $result['filled'], 'cemeti_thumbs_left' => $result['remaining'] ), admin_url( 'options-general.php?page=cemeti-settings' ) ) );
	exit;
}
add_action( 'admin_post_cemeti_fill_thumbs', 'cemeti_handle_fill_thumbnails' );

/**
 * The same step for the page's own runner (admin-ajax): progress on the page, retries after a server error.
 */
function cemeti_thumbs_ajax() {
	check_ajax_referer( 'cemeti_fill_thumbs', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
	}
	$result = cemeti_thumbs_step( 8.0, ! empty( $_POST['fresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	wp_send_json_success(
		array(
			'phase'     => 'thumbs',
			'remaining' => $result['remaining'],
			'stopped'   => false,
			'report'    => array( 'filled' => $result['filled'], 'nothing' => $result['nothing'] ),
		)
	);
}
add_action( 'wp_ajax_cemeti_thumbs_step', 'cemeti_thumbs_ajax' );
