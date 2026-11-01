/**
 * Table of contents on detail pages: marks the section being read (aria-current) while the visitor scrolls,
 * and keeps the clicked link marked when the page jumps to its anchor. Without JavaScript the links still jump.
 */
( function () {
	'use strict';

	var toc = document.getElementById( 'toc' );
	if ( ! toc || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var links = Array.prototype.slice.call( toc.querySelectorAll( 'a[href^="#"]' ) );
	var targets = links.map( function ( link ) {
		return document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
	} );

	function mark( id ) {
		links.forEach( function ( link ) {
			if ( link.getAttribute( 'href' ) === '#' + id ) {
				link.setAttribute( 'aria-current', 'true' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
	}

	// A heading counts as "being read" while it sits in the upper part of the screen.
	var visible = {};
	var observer = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			visible[ entry.target.id ] = entry.isIntersecting;
		} );
		for ( var i = 0; i < targets.length; i++ ) {
			if ( targets[ i ] && visible[ targets[ i ].id ] ) {
				mark( targets[ i ].id );
				return;
			}
		}
	}, { rootMargin: '0px 0px -60% 0px' } );

	targets.forEach( function ( target ) {
		if ( target ) {
			observer.observe( target );
		}
	} );

	toc.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( 'a[href^="#"]' );
		if ( link ) {
			mark( link.getAttribute( 'href' ).slice( 1 ) );
		}
	} );
}() );
