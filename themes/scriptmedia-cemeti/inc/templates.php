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
		echo '<p class="cemeti-toc-back"><a href="#toc">' . esc_html( cemeti_t( 'back_to_toc' ) ) . '</a></p>';
	}
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
		$bio = (string) $row['bio'];
		if ( '' !== $row['name'] ) {
			echo '<p><strong>' . esc_html( $row['name'] ) . '</strong>' . ( '' !== $bio ? ' ' : '' ) . '</p>';
		}
		if ( '' !== $bio ) {
			echo wp_kses_post( wpautop( esc_html( $bio ) ) );
		}
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
		echo '<a class="cemeti-share__btn" href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
	}
	echo '<button type="button" class="cemeti-share__btn cemeti-share__more" hidden data-title="' . esc_attr( $title ) . '" data-url="' . esc_url( $url ) . '">' . esc_html( cemeti_t( 'share_more' ) ) . '</button>';
	echo '</div>';
}
