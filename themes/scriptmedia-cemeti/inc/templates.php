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
			echo wp_get_attachment_image( (int) $row['photo'], 'cemeti-card', false, array( 'class' => 'cemeti-bio__photo', 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
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
	if ( $thumbnail_id ) {
		return $thumbnail_id;
	}
	$post = get_post( $post );
	if ( ! $post || ! array_key_exists( $post->post_type, cemeti_config( 'post-types' ) ) || 'application' === $post->post_type ) {
		return $thumbnail_id;
	}
	static $cache = array();
	if ( isset( $cache[ $post->ID ] ) ) {
		return $cache[ $post->ID ] ? $cache[ $post->ID ] : $thumbnail_id;
	}
	$found   = 0;
	$content = (string) $post->post_content;
	if ( preg_match( '/wp-image-(\d+)/', $content, $match ) ) {
		$found = (int) $match[1];
	}
	if ( ! $found ) {
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
		$found = $attached ? (int) $attached[0] : 0;
	}
	if ( ! $found && function_exists( 'attachment_url_to_postid' ) && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match ) ) {
		$found = (int) attachment_url_to_postid( $match[1] );
	}
	$cache[ $post->ID ] = $found;
	return $found ? $found : $thumbnail_id;
}
add_filter( 'post_thumbnail_id', 'cemeti_fallback_thumbnail_id', 10, 2 );
