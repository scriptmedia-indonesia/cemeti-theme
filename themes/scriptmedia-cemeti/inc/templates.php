<?php
/**
 * Template helpers shared by the page templates.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Escaped multi-line text (line breaks kept).
 *
 * @param string $text Plain text.
 * @return string
 */
function cemeti_multiline( $text ) {
	return wp_kses( nl2br( esc_html( (string) $text ) ), array( 'br' => array() ) );
}

/**
 * URL of the "← Back" link: the archive of the current post type, else home.
 *
 * @return string
 */
function cemeti_back_url() {
	if ( is_singular() ) {
		$archive = get_post_type_archive_link( get_post_type() );
		if ( $archive ) {
			return $archive;
		}
	}
	return cemeti_home_url();
}

/**
 * Centered script heading with the Back link at the right.
 *
 * @param string $title   Heading text.
 * @param string $context Colour context: "yellow", "blue" or "home".
 * @param bool   $back    Show the Back link.
 */
function cemeti_page_head( $title, $context = 'yellow', $back = true ) {
	echo '<div class="cemeti-page-head">';
	echo '<h1 class="cemeti-heading cemeti-ctx-' . esc_attr( $context ) . '">' . esc_html( $title ) . '</h1>';
	if ( $back ) {
		echo '<a class="cemeti-back" href="' . esc_url( cemeti_back_url() ) . '"><span aria-hidden="true">←</span> ' . esc_html( cemeti_t( 'back' ) ) . '</a>';
	}
	echo '</div>';
}

/**
 * Heading of an archive page, as in the design: the singular name of the type
 * ("Exhibition", "Residency"), or the term name on a taxonomy archive.
 *
 * @return string
 */
function cemeti_archive_heading() {
	$type   = get_query_var( 'post_type' );
	$type   = is_array( $type ) ? reset( $type ) : $type;
	$object = $type ? get_post_type_object( $type ) : null;
	return $object ? $object->labels->singular_name : single_term_title( '', false );
}

/**
 * Label shown above a title: the first term of the post's taxonomy, else the type name.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function cemeti_post_label( $post ) {
	$taxonomies = get_object_taxonomies( $post->post_type );
	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_the_terms( $post, $taxonomy );
		if ( is_array( $terms ) && $terms ) {
			return $terms[0]->name;
		}
	}
	$object = get_post_type_object( $post->post_type );
	return $object ? $object->labels->singular_name : '';
}

/**
 * The entry to feature for a type: the current one, else the soonest upcoming,
 * else the latest previous. Types without dates (Journal) use the newest post.
 *
 * @param string $post_type Post type.
 * @return WP_Post|null
 */
function cemeti_featured_post( $post_type ) {
	list( , $end_key ) = cemeti_date_keys( $post_type );
	if ( ! $end_key ) {
		$posts = get_posts( array( 'post_type' => $post_type, 'posts_per_page' => 1, 'no_found_rows' => true ) );
		return $posts ? $posts[0] : null;
	}
	foreach ( array( 'current', 'upcoming', 'previous' ) as $status ) {
		$query = cemeti_status_query( $post_type, $status, array( 'posts_per_page' => 1, 'no_found_rows' => true ) );
		if ( $query->posts ) {
			return $query->posts[0];
		}
	}
	return null;
}

/**
 * Group titles by first letter for the Open Archive page.
 * Leading quotes and punctuation are ignored; accents are folded for sorting.
 *
 * @param array<int, array{title:string,url:string}> $entries Entries.
 * @return array<string, array<int, array{title:string,url:string}>> Letter => entries, A–Z then "#".
 */
function cemeti_group_az( array $entries ) {
	$fold = static function ( $text ) {
		$text = trim( preg_replace( '/^[^\p{L}\p{N}]+/u', '', (string) $text ) );
		return function_exists( 'remove_accents' ) ? remove_accents( $text ) : $text;
	};

	$groups = array();
	foreach ( $entries as $entry ) {
		$key    = $fold( $entry['title'] );
		$letter = '' !== $key ? strtoupper( substr( $key, 0, 1 ) ) : '#';
		$letter = preg_match( '/^[A-Z]$/', $letter ) ? $letter : '#';
		$groups[ $letter ][] = array_merge( $entry, array( 'sort' => strtolower( $key ) ) );
	}
	ksort( $groups );
	if ( isset( $groups['#'] ) ) {
		$numbers = $groups['#'];
		unset( $groups['#'] );
		$groups['#'] = $numbers;
	}
	foreach ( $groups as $letter => $items ) {
		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( $a['sort'], $b['sort'] );
			}
		);
		$groups[ $letter ] = array_map(
			static function ( $item ) {
				unset( $item['sort'] );
				return $item;
			},
			$items
		);
	}
	return $groups;
}

/**
 * Split A–Z groups into the two columns of the design (A–M and N–Z).
 *
 * @param array $groups Output of cemeti_group_az().
 * @return array{0:array,1:array}
 */
function cemeti_split_az( array $groups ) {
	$left  = array();
	$right = array();
	foreach ( $groups as $letter => $items ) {
		if ( '#' === $letter || $letter <= 'M' ) {
			$left[ $letter ] = $items;
		} else {
			$right[ $letter ] = $items;
		}
	}
	return array( $left, $right );
}

/**
 * Share links for an entry.
 *
 * @param string $url   Absolute URL.
 * @param string $title Title.
 * @return array<string, string> Label key => URL.
 */
function cemeti_share_links( $url, $title ) {
	return array(
		'WhatsApp' => 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url ),
		'Telegram' => 'https://t.me/share/url?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title ),
	);
}

/**
 * Small inline icon for a share button (decorative: the app name is written next to it).
 *
 * @param string $name App name: WhatsApp, Telegram or More.
 * @return string SVG markup.
 */
function cemeti_share_icon( $name ) {
	$paths = array(
		'WhatsApp' => 'M12 3a9 9 0 0 0-7.7 13.6L3 21l4.5-1.2A9 9 0 1 0 12 3zm4.6 12.4c-.2.5-1.1 1-1.6 1-.4.1-.9.1-1.5-.1-.3-.1-.8-.3-1.4-.5-2.4-1-4-3.5-4.1-3.6-.1-.2-1-1.3-1-2.5s.6-1.8.9-2.1c.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .5.4l.8 1.9c.1.1.1.3 0 .4l-.3.5-.4.4c-.1.1-.2.3-.1.4.2.3.7 1.2 1.6 1.9 1.1.9 2 1.2 2.3 1.3.2.1.4.1.5-.1l.7-.9c.1-.2.3-.2.5-.1l1.8.9c.2.1.3.2.4.3 0 .2 0 .8-.2 1.2z',
		'Telegram' => 'M21.5 4.2 2.9 11.4c-.9.4-.9 1.1-.2 1.3l4.7 1.5 1.8 5.6c.2.6.4.7.8.7s.6-.2.8-.5l2.2-2.1 4.6 3.4c.8.5 1.5.2 1.7-.8l3-14.3c.3-1.2-.4-1.7-1.3-1.2zM9 14l8.6-5.4c.4-.3.8-.1.5.2L11 15.4l-.3 3.1L9 14z',
		'More'     => 'M18 16.1c-.8 0-1.5.3-2 .8l-7.1-4.2c.1-.2.1-.5.1-.7s0-.5-.1-.7L15.9 7.2c.5.5 1.2.8 2.1.8 1.7 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3c0 .2 0 .5.1.7L9.1 9.8C8.6 9.3 7.8 9 7 9c-1.7 0-3 1.3-3 3s1.3 3 3 3c.8 0 1.6-.3 2.1-.8l7.2 4.2c0 .2-.1.4-.1.6 0 1.6 1.3 2.9 2.9 2.9s2.9-1.3 2.9-2.9-1.3-3-2.9-3z',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="cemeti-share__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . esc_attr( $paths[ $name ] ) . '"/></svg>';
}

/**
 * Remove the "English | Indonesia" language links that the old site pasted at the top of many entries.
 * The site has its own language switcher in the header, so they only repeat it.
 *
 * @param string $content Post content.
 * @return string
 */
function cemeti_strip_language_links( $content ) {
	$word = '(?:English|Indonesia(?:n)?|Bahasa\s+Indonesia|Bahasa\s+Inggris)';
	$tags = '(?:\s*<[^>]+>\s*)*';
	$out  = preg_replace( '#<(p|div|h[1-6])\b[^>]*>' . $tags . $word . $tags . '\s*[|/\x{2013}\x{2014}-]\s*' . $tags . $word . $tags . '</\1>\s*#iu', '', (string) $content );
	return null === $out ? (string) $content : $out;
}

/**
 * Apply cemeti_strip_language_links() to the text of Cemeti entries.
 *
 * @param string $content Post content.
 * @return string
 */
function cemeti_filter_language_links( $content ) {
	if ( is_admin() || ! is_singular() || ! array_key_exists( get_post_type(), cemeti_config( 'post-types' ) ) ) {
		return $content;
	}
	return cemeti_strip_language_links( $content );
}
add_filter( 'the_content', 'cemeti_filter_language_links', 5 );

/**
 * Take a picture that opens the text out of it, so it can be shown in the page's own picture slot.
 * Used on About when the page has no featured image: the old pages put the picture first in the text.
 *
 * @param string $html Page content.
 * @return array{0:int,1:string} Attachment ID (0 when none) and the content without that picture.
 */
function cemeti_take_leading_image( $html ) {
	$html = (string) $html;
	$re   = '#^\s*(?:<!--.*?-->\s*)*(<figure\b[^>]*>.*?</figure>|<p>\s*(?:<a\b[^>]*>\s*)?<img\b[^>]*>\s*(?:</a>\s*)?</p>|<img\b[^>]*>)\s*(?:<!--.*?-->\s*)*#s';
	if ( ! preg_match( $re, $html, $m ) ) {
		return array( 0, $html );
	}
	$ids = cemeti_media_ids_in_html( $m[1], 1 );
	$id  = $ids ? (int) $ids[0] : 0;
	if ( ! $id || 1 === preg_match( '#<figcaption[^>]*>\s*[^\s<]#i', $m[1] ) || '' !== trim( wp_strip_all_tags( preg_replace( '#<figcaption.*?</figcaption>#is', '', $m[1] ) ) ) ) {
		return array( 0, $html ); // Unknown picture, or a picture with text around it: leave it where it is.
	}
	return array( $id, substr( $html, strlen( $m[0] ) ) );
}

/**
 * Section heading (blue script) with an anchor id.
 *
 * @param string $label  Heading text.
 * @param string $anchor Anchor id.
 */
function cemeti_section_heading( $label, $anchor ) {
	echo '<h2 class="cemeti-section-heading cemeti-script" id="' . esc_attr( $anchor ) . '">' . esc_html( $label ) . '</h2>';
}

/**
 * "Back to contents" link shown after each section.
 *
 * @param bool $show Show the link.
 */
function cemeti_back_to_toc( $show ) {
	if ( $show ) {
		echo '<p class="cemeti-toc-back"><a href="#toc-top">' . esc_html( cemeti_t( 'back_to_toc' ) ) . '</a></p>';
	}
}

/**
 * Address of the booklet: the chosen PDF, else the external link, else ''.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function cemeti_booklet_url( $post_id ) {
	$file = (int) cemeti_get_field( 'booklet', $post_id );
	$url  = $file ? (string) wp_get_attachment_url( $file ) : '';
	return '' !== $url ? $url : (string) cemeti_get_field( 'booklet_url', $post_id );
}

/**
 * Photo grid with caption.
 *
 * @param int[]  $ids     Attachment IDs.
 * @param string $caption Caption text.
 */
function cemeti_render_photos( array $ids, $caption = '' ) {
	echo '<figure class="cemeti-photos"><div class="cemeti-photos__grid">';
	foreach ( $ids as $id ) {
		echo wp_get_attachment_image( (int) $id, 'cemeti-poster', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	if ( '' !== $caption ) {
		echo '<figcaption>' . esc_html( $caption ) . '</figcaption>';
	}
	echo '</figure>';
}

/**
 * Biography rows: photo left, text right.
 *
 * @param array $rows Rows with name, photo and bio.
 */
function cemeti_render_biographies( array $rows ) {
	foreach ( $rows as $row ) {
		echo '<div class="cemeti-bio">';
		if ( ! empty( $row['photo'] ) ) {
			echo wp_get_attachment_image( (int) $row['photo'], 'cemeti-square', false, array( 'class' => 'cemeti-bio__photo', 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<div class="cemeti-bio__text">';
		$bio  = (string) $row['bio'];
		$name = '' !== $row['name'] ? '<strong>' . esc_html( $row['name'] ) . '</strong>' : '';
		// As in the design, the name opens the first paragraph in Medium weight.
		$html = '' !== $bio ? wpautop( esc_html( $bio ) ) : '';
		if ( '' !== $name ) {
			$html = '' !== $html ? preg_replace( '/^<p>/', '<p>' . $name . ' ', $html, 1 ) : '<p>' . $name . '</p>';
		}
		echo wp_kses_post( $html );
		echo '</div></div>';
	}
}

/**
 * Related publications: up to four small cards.
 *
 * @param WP_Post[] $posts Related posts.
 */
function cemeti_render_related( array $posts ) {
	echo '<ul class="cemeti-related">';
	foreach ( $posts as $related ) {
		echo '<li><a href="' . esc_url( get_permalink( $related ) ) . '">';
		echo get_the_post_thumbnail( $related, 'cemeti-card', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<span class="cemeti-related__label">' . esc_html( cemeti_post_label( $related ) ) . '</span>';
		echo '<span class="cemeti-related__title">' . esc_html( get_the_title( $related ) ) . '</span>';
		echo '</a></li>';
	}
	echo '</ul>';
}

/**
 * Share buttons. "More" appears only where the Web Share API exists (see share.js).
 *
 * @param string $url   Absolute URL.
 * @param string $title Title.
 */
function cemeti_render_share( $url, $title ) {
	echo '<div class="cemeti-share"><span class="cemeti-share__label">' . esc_html( cemeti_t( 'share' ) ) . ':</span>';
	foreach ( cemeti_share_links( $url, $title ) as $label => $href ) {
		echo '<a class="cemeti-share__btn" href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . cemeti_share_icon( $label ) . esc_html( $label ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<button type="button" class="cemeti-share__btn cemeti-share__more" hidden data-title="' . esc_attr( $title ) . '" data-url="' . esc_url( $url ) . '">' . cemeti_share_icon( 'More' ) . esc_html( cemeti_t( 'share_more' ) ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

/**
 * Entries moved from the old site usually have their pictures inside the text and no featured image, so cards,
 * posters and share previews stayed empty. When there is no featured image, use the first picture of the entry:
 * a picture with a wp-image-ID class in the text, an image attached to the entry, or an uploaded file whose
 * address appears in the text. A real featured image always wins.
 *
 * Runs through WordPress' own "post_thumbnail_id" filter, so has_post_thumbnail(), the_post_thumbnail() and
 * get_the_post_thumbnail_url() all see it.
 *
 * @param int|false         $thumbnail_id Featured image ID.
 * @param int|WP_Post|null $post         Post.
 * @return int|false
 */
function cemeti_fallback_thumbnail_id( $thumbnail_id, $post = null ) {
	// A featured image that points to a picture which is not in this site's Media Library (an old number kept by the
	// import: the editor says "Could not retrieve the featured image data") counts as none.
	if ( $thumbnail_id && 'attachment' === get_post_type( (int) $thumbnail_id ) ) {
		return $thumbnail_id;
	}
	$broken = (int) $thumbnail_id;
	$post = get_post( $post );
	if ( ! $post || 'application' === $post->post_type ) {
		return $broken ? false : $thumbnail_id;
	}
	$is_entry = array_key_exists( $post->post_type, cemeti_config( 'post-types' ) );
	$is_page  = 'page' === $post->post_type && '' !== cemeti_page_key( $post->ID );
	if ( ! $is_entry && ! $is_page ) {
		return $broken ? false : $thumbnail_id;
	}
	if ( $is_page ) {
		// A page's picture is chosen, never guessed from its text; only the other language's is used.
		$theirs = cemeti_translation_thumbnail_id( $post->ID );
		return $theirs ? $theirs : ( $broken ? false : $thumbnail_id );
	}
	static $cache = array();
	if ( ! isset( $cache[ $post->ID ] ) ) {
		$cache[ $post->ID ] = cemeti_find_entry_picture( $post );
	}
	return $cache[ $post->ID ] ? $cache[ $post->ID ] : ( $broken ? false : $thumbnail_id );
}

/**
 * Featured image of the other language's copy of a post (so both languages show the same one).
 *
 * @param int  $post_id Post ID.
 * @param bool $deep    Also use the first picture of that copy's gallery / documentation.
 * @return int
 */
function cemeti_translation_thumbnail_id( $post_id, $deep = false ) {
	if ( ! function_exists( 'pll_get_post_translations' ) ) {
		return 0;
	}
	foreach ( (array) pll_get_post_translations( $post_id ) as $other ) {
		if ( (int) $other === (int) $post_id ) {
			continue;
		}
		$theirs = (int) get_post_meta( (int) $other, '_thumbnail_id', true );
		if ( $theirs && 'attachment' === get_post_type( $theirs ) ) {
			return $theirs;
		}
		if ( $deep ) {
			foreach ( array( 'cemeti_gallery', 'cemeti_documentation' ) as $key ) {
				$ids = get_post_meta( (int) $other, $key, true );
				if ( is_array( $ids ) && $ids ) {
					return (int) reset( $ids );
				}
			}
		}
	}
	return 0;
}

/**
 * The entry of the other language that is the same event, when the two are not linked as translations: same type, same
 * dates, and (when several match) the most alike title. Imported Indonesian and English entries are separate posts.
 *
 * @param WP_Post $post Entry.
 * @return int Post ID, 0 when none.
 */
function cemeti_counterpart_entry_id( $post ) {
	if ( ! function_exists( 'pll_get_post_language' ) ) {
		return 0;
	}
	$start = (string) get_post_meta( $post->ID, 'cemeti_start_date', true );
	$mine  = (string) pll_get_post_language( $post->ID );
	if ( '' === $start || '' === $mine ) {
		return 0;
	}
	$end   = (string) get_post_meta( $post->ID, 'cemeti_end_date', true );
	$title = function_exists( 'wp_strip_all_tags' ) ? strtolower( wp_strip_all_tags( $post->post_title ) ) : strtolower( $post->post_title );
	$best  = 0;
	$score = -1.0;
	foreach ( get_posts( array( 'post_type' => $post->post_type, 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => 20, 'fields' => 'ids', 'meta_key' => 'cemeti_start_date', 'meta_value' => $start, 'lang' => '', 'suppress_filters' => false ) ) as $other ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$other = (int) ( is_object( $other ) ? $other->ID : $other );
		$lang  = (string) pll_get_post_language( $other );
		if ( $other === (int) $post->ID || '' === $lang || $lang === $mine ) {
			continue;
		}
		if ( '' !== $end && (string) get_post_meta( $other, 'cemeti_end_date', true ) !== $end ) {
			continue;
		}
		similar_text( $title, strtolower( (string) get_post_field( 'post_title', $other ) ), $percent );
		if ( $percent > $score ) {
			$score = $percent;
			$best  = $other;
		}
	}
	return $best;
}

/**
 * The picture that stands for an entry that has no featured image: the other language's, the first of its own gallery
 * or documentation (after "Convert" the pictures live there, no longer in the text), a picture of its text, of the
 * original text kept by the converter, or an image attached to it.
 *
 * @param WP_Post $post Entry.
 * @return int Attachment ID, 0 when none.
 */
function cemeti_find_entry_picture( $post ) {
	$theirs = cemeti_translation_thumbnail_id( $post->ID );
	if ( $theirs ) {
		return $theirs;
	}
	foreach ( array( 'cemeti_gallery', 'cemeti_documentation' ) as $key ) {
		$ids = get_post_meta( $post->ID, $key, true );
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				if ( (int) $id && wp_attachment_is_image( (int) $id ) ) {
					return (int) $id;
				}
			}
		}
	}
	$theirs = cemeti_translation_thumbnail_id( $post->ID, true );
	if ( $theirs ) {
		return $theirs;
	}
	// The same event in the other language, not linked as its translation.
	$twin = cemeti_counterpart_entry_id( $post );
	if ( $twin ) {
		$theirs = (int) get_post_meta( $twin, '_thumbnail_id', true );
		if ( $theirs && 'attachment' === get_post_type( $theirs ) ) {
			return $theirs;
		}
		foreach ( array( 'cemeti_gallery', 'cemeti_documentation' ) as $key ) {
			$ids = get_post_meta( $twin, $key, true );
			if ( is_array( $ids ) && $ids ) {
				return (int) reset( $ids );
			}
		}
		$twin_post = get_post( $twin );
		$in_text   = $twin_post ? cemeti_media_ids_in_html( (string) $twin_post->post_content . "\n" . (string) get_post_meta( $twin, '_cemeti_original_content', true ), 1 ) : array();
		if ( $in_text ) {
			return (int) $in_text[0];
		}
	}
	foreach ( array( (string) $post->post_content, (string) get_post_meta( $post->ID, '_cemeti_original_content', true ) ) as $html ) {
		$in_text = '' !== $html ? cemeti_media_ids_in_html( $html, 1 ) : array();
		if ( $in_text ) {
			return (int) $in_text[0];
		}
	}
	$attached = get_posts(
		array(
			'post_type'        => 'attachment',
			'post_parent'      => $post->ID,
			'post_mime_type'   => 'image',
			'post_status'      => 'inherit',
			'posts_per_page'   => 1,
			'orderby'          => 'menu_order ID',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	return $attached ? (int) $attached[0] : 0;
}
add_filter( 'post_thumbnail_id', 'cemeti_fallback_thumbnail_id', 10, 2 );
