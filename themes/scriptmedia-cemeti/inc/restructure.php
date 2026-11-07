<?php
/**
 * Split an old post's text into the new structure.
 *
 * The old site wrote everything in one body: a hand-made "Daftar Isi", the English | Indonesia links, then sections
 * under headings ("Foto Karya", "Dokumentasi", "Biografi Seniman", "Terbitan Terkait" …) with the pictures and the
 * biographies inside. The new site keeps them in fields (gallery, documentation, biographies, booklet) and builds the
 * contents bar, the section titles and the related list itself. This file reads the old body once and returns:
 *
 *  - foreword : what stays in the text (statement, essay, practical information)
 *  - gallery / documentation : picture IDs (and a caption when the section had one line of text)
 *  - biographies : rows with name, photo and text
 *  - booklet : a PDF link when the text offered one
 *
 * Nothing is thrown away silently: the converter keeps the original text in post meta so a revert restores it.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a heading of the old site stands for.
 *
 * @param string $text Heading text.
 * @return string "toc", "documentation", "gallery", "biography", "related" or "".
 */
function cemeti_restructure_heading_kind( $text ) {
	$t = strtolower( trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) ) );
	$t = str_replace( array( "\u{2019}", "\u{2018}" ), "'", $t );
	$t = trim( $t, " \t:.-–" );
	if ( '' === $t || mb_strlen( $t ) > 60 ) {
		return '';
	}
	if ( preg_match( '/^(daftar isi( .*)?|table of contents?( .*)?|contents?)$/u', $t ) ) {
		return 'toc';
	}
	if ( preg_match( '/terbitan terkait|publikasi terkait|related publications?|khazanah pendukung/u', $t ) ) {
		return 'related';
	}
	if ( preg_match( '/biograf|biography|profil seniman|artist(s\'|\'s|s)? profile/u', $t ) ) {
		return 'biography';
	}
	if ( preg_match( '/dokumentasi|documentation/u', $t ) ) {
		return 'documentation';
	}
	if ( preg_match( '/fo(to|ta) karya|artworks? images?|^galeri$|^gallery$|exhibition view|installation views?|tampak pameran|foto pameran/u', $t ) ) {
		return 'gallery';
	}
	return '';
}

/**
 * Does a heading read like a person's name (so it starts a biography) rather than a title or an address?
 *
 * @param string $text Heading text.
 * @return bool
 */
function cemeti_restructure_looks_like_name( $text ) {
	$t = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	if ( '' === $t || mb_strlen( $t ) > 70 || str_word_count( $t ) > 9 ) {
		return false;
	}
	return 1 !== preg_match( '/cemeti|jl\.|jalan|yogyakarta|\d{1,2}[:.]\d{2}|\b20\d\d\b|@|instagram|www\.|:$/iu', $t );
}

/**
 * Attachment ID of a picture in the old text: by its address first (the importer rewrites it), then by the
 * wp-image-ID class when that ID really is an attachment.
 *
 * @param DOMElement $img Image element.
 * @return int
 */
function cemeti_restructure_image_id( DOMElement $img ) {
	// By the file path (any host), then by the old wp-image-ID only when it is the same file (see inc/media.php).
	$id = cemeti_media_img_id( (string) $img->getAttribute( 'src' ), (string) $img->getAttribute( 'class' ) );
	/**
	 * Filters the attachment ID found for a picture of the old text.
	 *
	 * @param int        $id  ID, 0 when unknown.
	 * @param DOMElement $img Image element.
	 */
	return (int) apply_filters( 'cemeti_restructure_image_id', $id, $img );
}

/**
 * Is this node only a spacer, separator or empty?
 *
 * @param DOMNode $node Node.
 * @return bool
 */
function cemeti_restructure_is_filler( DOMNode $node ) {
	if ( XML_TEXT_NODE === $node->nodeType ) {
		return '' === trim( $node->textContent );
	}
	if ( ! $node instanceof DOMElement ) {
		return true;
	}
	$class = (string) $node->getAttribute( 'class' );
	return 'hr' === $node->nodeName || false !== strpos( $class, 'wp-block-spacer' ) || false !== strpos( $class, 'wp-block-separator' ) || ( 'p' === $node->nodeName && '' === trim( str_replace( "\xc2\xa0", '', $node->textContent ) ) && 0 === $node->getElementsByTagName( 'img' )->length );
}

/**
 * Paragraphs that only repeat what the new site does by itself: language links, "back to contents", "⇪".
 *
 * @param DOMNode $node Node.
 * @return bool
 */
function cemeti_restructure_is_navigation( DOMNode $node ) {
	$text = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
	if ( '' === $text ) {
		return false;
	}
	if ( preg_match( '/^(\x{21EA}|kembali ke daftar isi|back to (table of )?contents?)\s*$/iu', $text ) ) {
		return true;
	}
	return 1 === preg_match( '/^(english|indonesia(n)?|bahasa indonesia)\s*[|\/–—-]\s*(english|indonesia(n)?|bahasa indonesia)$/iu', $text );
}

/**
 * Split the old body. Returns null when there is nothing to split (the text is left as it is).
 *
 * @param string $html   Old post content.
 * @param int    $thumb  Featured image ID: the same picture inside the text is dropped.
 * @return array{missing:int,foreword:string,gallery:int[],gallery_caption:string,documentation:int[],documentation_caption:string,biographies:array,booklet:string,found:string[]}|null
 */
function cemeti_restructure_content( $html, $thumb = 0 ) {
	$html = preg_replace( '/<!--.*?-->/s', '', (string) $html );
	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return null;
	}
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<' . '?xml encoding="utf-8"?><div id="cemeti-wrap">' . $html . '</div>' );
	libxml_clear_errors();
	$wrap = $dom->getElementById( 'cemeti-wrap' );
	if ( ! $wrap ) {
		return null;
	}

	// Block groups and columns wrap the headings of many old posts: take them apart so every heading is a direct
	// child. Inner wrappers first; spacers and separators stay.
	foreach ( array_reverse( iterator_to_array( $wrap->getElementsByTagName( 'div' ) ) ) as $div ) {
		$class = (string) $div->getAttribute( 'class' );
		if ( $div->parentNode && false === strpos( $class, 'spacer' ) && false === strpos( $class, 'separator' ) ) {
			while ( $div->firstChild ) {
				$div->parentNode->insertBefore( $div->firstChild, $div );
			}
			$div->parentNode->removeChild( $div );
		}
	}

	$out = array(
		'foreword'              => '',
		'gallery'               => array(),
		'gallery_caption'       => '',
		'documentation'         => array(),
		'documentation_caption' => '',
		'biographies'           => array(),
		'booklet'               => '',
		'found'                 => array(),
		'missing'               => 0,
	);
	$mode    = 'keep';
	$keep    = array();
	$photo   = 0;

	// Pictures that are not in the Media Library are never dropped: they stay in the text and are counted.
	$unfound  = array();
	$pictures = static function ( DOMNode $node ) use ( &$unfound, $dom ) {
		$ids = array();
		if ( $node instanceof DOMElement ) {
			$imgs = 'img' === $node->nodeName ? array( $node ) : iterator_to_array( $node->getElementsByTagName( 'img' ) );
			foreach ( $imgs as $img ) {
				$id = cemeti_restructure_image_id( $img );
				if ( $id ) {
					$ids[] = $id;
				} else {
					$unfound[] = $dom->saveHTML( $img );
				}
			}
		}
		return $ids;
	};
	$total_missing = 0;
	$keep_unfound  = static function () use ( &$unfound, &$keep, &$total_missing ) {
		if ( $unfound ) {
			$total_missing += count( $unfound );
			$keep[]         = '<p>' . implode( ' ', $unfound ) . '</p>';
			$unfound        = array();
		}
	};

	foreach ( iterator_to_array( $wrap->childNodes ) as $node ) {
		if ( cemeti_restructure_is_filler( $node ) ) {
			continue;
		}
		$name = $node instanceof DOMElement ? $node->nodeName : '';

		if ( preg_match( '/^h[1-6]$/', $name ) && cemeti_restructure_is_navigation( $node ) ) {
			continue; // A "⇪" or "Kembali ke Daftar Isi" written as a heading.
		}
		if ( preg_match( '/^h[1-6]$/', $name ) ) {
			$kind = cemeti_restructure_heading_kind( $node->textContent );
			if ( '' !== $kind ) {
				$mode            = $kind;
				$out['found'][]  = $kind;
				continue; // The new site writes these titles itself.
			}
			if ( 'biography' === $mode && cemeti_restructure_looks_like_name( $node->textContent ) ) {
				// Some biographies name each artist in a heading instead of a bold first word.
				$name_text = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
				if ( $name_text === mb_strtoupper( $name_text ) ) {
					$name_text = mb_convert_case( mb_strtolower( $name_text ), MB_CASE_TITLE );
				}
				$out['biographies'][] = array( 'name' => $name_text, 'photo' => $photo, 'bio' => '' );
				$photo                = 0;
				continue;
			}
			if ( in_array( $mode, array( 'gallery', 'documentation' ), true ) && cemeti_restructure_looks_like_name( $node->textContent ) ) {
				// An artwork title written as a heading between its pictures: the pictures still belong to the section.
				$next = $node->nextSibling;
				while ( $next && cemeti_restructure_is_filler( $next ) ) {
					$next = $next->nextSibling;
				}
				if ( $next instanceof DOMElement && ( 'img' === $next->nodeName || $next->getElementsByTagName( 'img' )->length ) ) {
					continue; // The original text keeps the title (Revert).
				}
			}
			$mode = 'keep';
		}

		if ( 'toc' === $mode ) {
			// The hand-made contents: paragraphs of links to anchors in the same text.
			$anchors = $node instanceof DOMElement ? $node->getElementsByTagName( 'a' ) : null;
			$all_in  = $anchors && $anchors->length > 0;
			if ( $all_in ) {
				foreach ( $anchors as $a ) {
					if ( 0 !== strpos( (string) $a->getAttribute( 'href' ), '#' ) ) {
						$all_in = false;
					}
				}
			}
			if ( $all_in || cemeti_restructure_is_navigation( $node ) ) {
				continue;
			}
			$mode = 'keep';
		}

		if ( cemeti_restructure_is_navigation( $node ) ) {
			continue;
		}

		switch ( $mode ) {
			case 'related':
				break;
			case 'gallery':
			case 'documentation':
				// Videos and embeds cannot go into a picture field: they stay in the text.
				if ( $node instanceof DOMElement && ( $node->getElementsByTagName( 'iframe' )->length || $node->getElementsByTagName( 'video' )->length || $node->getElementsByTagName( 'audio' )->length || $node->getElementsByTagName( 'embed' )->length ) ) {
					$keep[] = $dom->saveHTML( $node );
					break;
				}
				$ids = $pictures( $node );
				$had_unfound = (bool) $unfound;
				$keep_unfound();
				if ( $ids ) {
					$out[ $mode ] = array_merge( $out[ $mode ], $ids );
				} elseif ( ! $had_unfound && '' === $out[ $mode . '_caption' ] && mb_strlen( trim( $node->textContent ) ) <= 220 ) {
					$out[ $mode . '_caption' ] = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
				} elseif ( ! $ids && '' !== trim( $node->textContent ) ) {
					// Text that is not the one-line caption (descriptions of the works, essays) stays in the text.
					$keep[] = $dom->saveHTML( $node );
				}
				break;
			case 'biography':
				$ids = $pictures( $node );
				$keep_unfound();
				if ( $ids ) {
					$photo = $ids[0];
					if ( $out['biographies'] && 0 === $out['biographies'][ count( $out['biographies'] ) - 1 ]['photo'] ) {
						$out['biographies'][ count( $out['biographies'] ) - 1 ]['photo'] = $photo;
						$photo = 0;
					}
					break;
				}
				$strong = $node instanceof DOMElement ? $node->getElementsByTagName( 'strong' )->item( 0 ) : null;
				$text   = trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
				if ( $strong && 0 === mb_strpos( $text, trim( $strong->textContent ) ) && mb_strlen( trim( $strong->textContent ) ) < 80 ) {
					$name = trim( $strong->textContent );
					$body = trim( mb_substr( $text, mb_strlen( $name ) ) );
					if ( $name === mb_strtoupper( $name ) ) {
						$name = mb_convert_case( mb_strtolower( $name ), MB_CASE_TITLE );
					}
					$out['biographies'][] = array( 'name' => $name, 'photo' => $photo, 'bio' => $body );
					$photo                = 0;
				} elseif ( $out['biographies'] ) {
					$last = count( $out['biographies'] ) - 1;
					$out['biographies'][ $last ]['bio'] = trim( $out['biographies'][ $last ]['bio'] . "\n\n" . $text );
				} else {
					$out['biographies'][] = array( 'name' => '', 'photo' => $photo, 'bio' => $text );
					$photo                = 0;
				}
				break;
			default:
				// A PDF offered in the text becomes the booklet link (the first one only).
				if ( '' === $out['booklet'] && $node instanceof DOMElement ) {
					foreach ( $node->getElementsByTagName( 'a' ) as $a ) {
						if ( preg_match( '/\.pdf(\?.*)?$/i', (string) $a->getAttribute( 'href' ) ) ) {
							$out['booklet'] = (string) $a->getAttribute( 'href' );
							break;
						}
					}
				}
				// The cover picture is the featured image already.
				if ( $thumb && $node instanceof DOMElement ) {
					$here   = $pictures( $node );
					$unfound = array(); // These pictures stay in the text anyway.
					if ( in_array( $thumb, $here, true ) && 0 === mb_strlen( trim( $node->textContent ) ) ) {
						break;
					}
				}
				$keep[] = $dom->saveHTML( $node );
		}
	}

	if ( ! $out['found'] ) {
		return null; // No section headings: nothing to split, the text stays as it is.
	}
	$out['foreword']      = trim( implode( "\n", $keep ) );
	$out['missing']       = $total_missing;
	$out['gallery']       = array_values( array_unique( $out['gallery'] ) );
	$out['documentation'] = array_values( array_unique( $out['documentation'] ) );
	return $out;
}

/**
 * Apply the split to a converted post: write the fields, keep the original text for a revert, shorten the body.
 *
 * @param int    $post_id Post ID (already of its new type).
 * @param string $type    New post type.
 * @return string[] Meta keys that were written (for the converter's revert list).
 */
function cemeti_restructure_post( $post_id, $type ) {
	if ( ! in_array( $type, array( 'exhibition', 'residency', 'public_program', 'open_studio' ), true ) ) {
		return array();
	}
	$post  = get_post( $post_id );
	$split = $post ? cemeti_restructure_content( $post->post_content, (int) get_post_thumbnail_id( $post_id ) ) : null;
	if ( ! $split ) {
		return array();
	}
	$written = array();
	$has     = static function ( $key ) use ( $type ) {
		return null !== cemeti_field_def( $type, $key );
	};
	$gallery = $split['gallery'];
	$docs    = $split['documentation'];
	if ( $gallery && ! $has( 'gallery' ) ) { // Public Program / Open Studio have no gallery: it joins the documentation.
		$docs    = array_merge( $gallery, $docs );
		$gallery = array();
	}
	$map = array(
		'gallery'               => $gallery,
		'gallery_caption'       => $gallery ? $split['gallery_caption'] : '',
		'documentation'         => $docs,
		'documentation_caption' => $docs ? ( $split['documentation_caption'] ? $split['documentation_caption'] : $split['gallery_caption'] ) : '',
		'biographies'           => $split['biographies'],
		'booklet_url'           => $has( 'booklet_url' ) ? $split['booklet'] : '',
	);
	foreach ( $map as $key => $value ) {
		if ( ! $has( $key ) || cemeti_field_is_empty( $value ) ) {
			continue;
		}
		update_post_meta( $post_id, 'cemeti_' . $key, $value );
		$written[] = 'cemeti_' . $key;
	}
	if ( '' === (string) get_post_meta( $post_id, '_cemeti_original_content', true ) ) {
		update_post_meta( $post_id, '_cemeti_original_content', $post->post_content ); // Never overwrite the real original with an already shortened text.
	}
	$written[] = '_cemeti_original_content';
	if ( ! empty( $split['missing'] ) ) {
		update_post_meta( $post_id, '_cemeti_media_missing', (int) $split['missing'] ); // Pictures not in the Media Library: they stay in the text.
		$written[] = '_cemeti_media_missing';
	}
	wp_update_post( array( 'ID' => $post_id, 'post_content' => $split['foreword'] ) );
	return $written;
}
