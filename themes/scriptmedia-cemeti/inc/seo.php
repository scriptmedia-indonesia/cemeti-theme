<?php
/**
 * SEO: meta description, Open Graph / Twitter cards, JSON-LD structured data,
 * noindex rules and the document title. Stays silent when a dedicated SEO plugin is active.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is another SEO plugin handling meta tags?
 *
 * @return bool
 */
function cemeti_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
}

/**
 * Short plain-text summary: tags and shortcodes removed, cut at a word boundary.
 *
 * @param string $text   HTML or text.
 * @param int    $length Maximum length in characters.
 * @return string
 */
function cemeti_summary( $text, $length = 155 ) {
	$text = html_entity_decode( preg_replace( '/<[^>]*>/', ' ', preg_replace( '/\[[^\]]*\]/', ' ', (string) $text ) ), ENT_QUOTES, 'UTF-8' );
	$text = trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
	if ( mb_strlen( $text ) <= $length ) {
		return $text;
	}
	$cut = mb_substr( $text, 0, $length );
	$pos = mb_strrpos( $cut, ' ' );
	return rtrim( false !== $pos && $pos > $length * 0.6 ? mb_substr( $cut, 0, $pos ) : $cut, " ,.;:-–—" ) . '…';
}

/**
 * Ymd to ISO date (Y-m-d). Anything else gives ''.
 *
 * @param string $ymd Date.
 * @return string
 */
function cemeti_ymd_to_iso( $ymd ) {
	return preg_match( '/^(\d{4})(\d{2})(\d{2})$/', (string) $ymd, $m ) ? $m[1] . '-' . $m[2] . '-' . $m[3] : '';
}

/**
 * Description of the current page.
 *
 * @return string
 */
function cemeti_current_description() {
	if ( is_singular() ) {
		$post_id = get_the_ID();
		$custom  = (string) cemeti_get_field( 'seo_description', $post_id );
		if ( '' !== trim( $custom ) ) {
			return cemeti_summary( $custom, 200 );
		}
		$excerpt = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : '';
		$text    = '' !== $excerpt ? $excerpt : get_post_field( 'post_content', $post_id );
		$text    = cemeti_summary( $text );
		if ( '' !== $text ) {
			return $text;
		}
	}
	$site = trim( cemeti_option( 'site_description' ) );
	return '' !== $site ? cemeti_summary( $site, 200 ) : cemeti_option( 'institution_name' ) . ' — Yogyakarta';
}

/**
 * Social sharing image: the featured image, else the logo.
 *
 * @return string URL.
 */
function cemeti_current_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$src = get_the_post_thumbnail_url( get_the_ID(), 'cemeti-hero' );
		if ( $src ) {
			return $src;
		}
	}
	return CEMETI_URI . '/assets/img/logo.webp';
}

/**
 * Event structured data for dated entries.
 *
 * @param array<string, string> $d Keys: name, url, start (Ymd), end (Ymd), image, description, venue, address.
 * @return array<string, mixed>
 */
function cemeti_schema_event( array $d ) {
	$event = array(
		'@type'               => 'Event',
		'name'                => $d['name'],
		'url'                 => $d['url'],
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'location'            => array(
			'@type'   => 'Place',
			'name'    => $d['venue'],
			'address' => $d['address'],
		),
	);
	if ( '' !== cemeti_ymd_to_iso( $d['start'] ) ) {
		$event['startDate'] = cemeti_ymd_to_iso( $d['start'] );
		$event['endDate']   = '' !== cemeti_ymd_to_iso( $d['end'] ) ? cemeti_ymd_to_iso( $d['end'] ) : $event['startDate'];
	}
	if ( '' !== $d['image'] ) {
		$event['image'] = $d['image'];
	}
	if ( '' !== $d['description'] ) {
		$event['description'] = $d['description'];
	}
	return $event;
}

/**
 * Article structured data for Journal posts.
 *
 * @param array<string, string> $d Keys: name, url, published (ISO), modified (ISO), author, image, publisher.
 * @return array<string, mixed>
 */
function cemeti_schema_article( array $d ) {
	$article = array(
		'@type'            => 'Article',
		'headline'         => $d['name'],
		'url'              => $d['url'],
		'datePublished'    => $d['published'],
		'dateModified'     => '' !== $d['modified'] ? $d['modified'] : $d['published'],
		'publisher'        => array( '@type' => 'Organization', 'name' => $d['publisher'] ),
		'mainEntityOfPage' => $d['url'],
	);
	if ( '' !== $d['author'] ) {
		$article['author'] = array( '@type' => 'Person', 'name' => $d['author'] );
	}
	if ( '' !== $d['image'] ) {
		$article['image'] = $d['image'];
	}
	return $article;
}

/**
 * Organization structured data for the home page.
 *
 * @param array<string, mixed> $d Keys: name, url, logo, address, email, same_as (list of URLs).
 * @return array<string, mixed>
 */
function cemeti_schema_organization( array $d ) {
	$org = array(
		'@type'   => array( 'Organization', 'ArtGallery' ),
		'name'    => $d['name'],
		'url'     => $d['url'],
		'logo'    => $d['logo'],
		'address' => $d['address'],
	);
	if ( '' !== $d['email'] ) {
		$org['email'] = $d['email'];
	}
	if ( $d['same_as'] ) {
		$org['sameAs'] = array_values( $d['same_as'] );
	}
	return $org;
}

/**
 * Breadcrumb structured data.
 *
 * @param array<int, array{0:string,1:string}> $trail Items as [name, url].
 * @return array<string, mixed>
 */
function cemeti_schema_breadcrumbs( array $trail ) {
	$items = array();
	foreach ( array_values( $trail ) as $i => $crumb ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb[0], 'item' => $crumb[1] );
	}
	return array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
}

/**
 * JSON-LD of the current page.
 *
 * @return array<int, array<string, mixed>>
 */
function cemeti_current_schema() {
	$name   = cemeti_option( 'institution_name' );
	$graph  = array();
	$socials = array_values( array_filter( array( cemeti_option( 'instagram_url' ), cemeti_option( 'youtube_url' ), cemeti_option( 'facebook_url' ) ) ) );

	if ( is_front_page() ) {
		$graph[] = cemeti_schema_organization(
			array(
				'name'    => $name,
				'url'     => cemeti_home_url(),
				'logo'    => CEMETI_URI . '/assets/img/logo.webp',
				'address' => str_replace( "\n", ', ', cemeti_option( 'address' ) ),
				'email'   => is_email( cemeti_option( 'email' ) ) ? cemeti_option( 'email' ) : '',
				'same_as' => $socials,
			)
		);
	}

	if ( is_singular() ) {
		$post_id = get_the_ID();
		$type    = get_post_type( $post_id );
		$url     = get_permalink( $post_id );
		$image   = has_post_thumbnail( $post_id ) ? (string) get_the_post_thumbnail_url( $post_id, 'cemeti-hero' ) : '';
		list( $start_key, $end_key ) = cemeti_date_keys( $type );

		if ( $end_key && 'open_call' !== $type ) {
			$graph[] = cemeti_schema_event(
				array(
					'name'        => get_the_title( $post_id ),
					'url'         => $url,
					'start'       => (string) cemeti_get_field( $start_key, $post_id ),
					'end'         => (string) cemeti_get_field( $end_key, $post_id ),
					'image'       => $image,
					'description' => cemeti_current_description(),
					'venue'       => $name,
					'address'     => str_replace( "\n", ', ', cemeti_option( 'address' ) ),
				)
			);
		} elseif ( 'journal' === $type ) {
			$graph[] = cemeti_schema_article(
				array(
					'name'      => get_the_title( $post_id ),
					'url'       => $url,
					'published' => get_the_date( 'c', $post_id ),
					'modified'  => get_the_modified_date( 'c', $post_id ),
					'author'    => (string) cemeti_get_field( 'author', $post_id ),
					'image'     => $image,
					'publisher' => $name,
				)
			);
		}

		$trail = array( array( $name, cemeti_home_url() ) );
		$archive = get_post_type_archive_link( $type );
		$object  = get_post_type_object( $type );
		if ( $archive && $object ) {
			$trail[] = array( $object->labels->name, $archive );
		}
		$trail[] = array( get_the_title( $post_id ), $url );
		$graph[] = cemeti_schema_breadcrumbs( $trail );
	}
	return $graph;
}

/**
 * Print description, social tags and structured data.
 */
function cemeti_seo_head() {
	if ( cemeti_seo_plugin_active() || is_404() ) {
		return;
	}
	$title       = wp_get_document_title();
	$description = cemeti_current_description();
	global $wp;
	$url = is_singular() ? get_permalink() : ( is_front_page() ? cemeti_home_url() : trailingslashit( home_url( isset( $wp->request ) ? $wp->request : '' ) ) );
	$type        = is_singular() && 'journal' === get_post_type() ? 'article' : 'website';
	$locale      = str_replace( '-', '_', get_locale() );

	echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	$tags = array(
		'og:site_name'   => cemeti_option( 'institution_name' ),
		'og:type'        => $type,
		'og:title'       => $title,
		'og:description' => $description,
		'og:url'         => $url,
		'og:image'       => cemeti_current_image(),
		'og:locale'      => $locale,
	);
	foreach ( $tags as $property => $value ) {
		echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $value ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

	foreach ( cemeti_current_schema() as $node ) {
		echo '<script type="application/ld+json">' . wp_json_encode( array_merge( array( '@context' => 'https://schema.org' ), $node ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
add_action( 'wp_head', 'cemeti_seo_head', 5 );

/**
 * Keep thin or private pages out of search engines.
 *
 * @param array<string, mixed> $robots Directives.
 * @return array<string, mixed>
 */
function cemeti_robots( $robots ) {
	if ( is_search() || is_404() || isset( $_GET['apply'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'cemeti_robots' );

/**
 * Document title: "Page – Institution", home shows the full name.
 *
 * @param array<string, string> $parts Title parts.
 * @return array<string, string>
 */
function cemeti_title_parts( $parts ) {
	$parts['site'] = cemeti_option( 'institution_name' );
	if ( is_front_page() ) {
		unset( $parts['tagline'] );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'cemeti_title_parts' );

/**
 * Title separator.
 *
 * @return string
 */
function cemeti_title_separator() {
	return '–';
}
add_filter( 'document_title_separator', 'cemeti_title_separator' );

/**
 * robots.txt: keep internal search results out of crawlers (WordPress adds the sitemap line).
 *
 * @param string $output robots.txt text.
 * @return string
 */
function cemeti_robots_txt( $output ) {
	return rtrim( $output ) . "\nDisallow: /?s=\nDisallow: /search/\n";
}
add_filter( 'robots_txt', 'cemeti_robots_txt' );

/**
 * Sitemap: no user list (it would reveal account names).
 *
 * @param object|false $provider Provider.
 * @param string       $name     Provider name.
 * @return object|false
 */
function cemeti_sitemap_providers( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'cemeti_sitemap_providers', 10, 2 );
