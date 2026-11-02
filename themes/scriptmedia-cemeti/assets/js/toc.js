/**
 * Table of contents on detail pages: marks the section being read (aria-current) while the visitor scrolls.
 * A section is "being read" once its start has passed 35% of the screen height; the last such section wins.
 * Without JavaScript the links still jump to their anchors.
 */
( function () {
	'use strict';

	var toc = document.getElementById( 'toc' );
	if ( ! toc ) {
		return;
	}

	var links = Array.prototype.slice.call( toc.querySelectorAll( 'a[href^="#"]' ) );
	var targets = links.map( function ( link ) {
		return document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
	} );
	var queued = false;

	function update() {
		queued = false;
		var line = window.innerHeight * 0.35;
		var current = -1;
		targets.forEach( function ( target, index ) {
			if ( target && target.getBoundingClientRect().top <= line ) {
				current = index;
			}
		} );
		links.forEach( function ( link, index ) {
			if ( index === current ) {
				link.setAttribute( 'aria-current', 'true' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
	}

	function queue() {
		if ( ! queued ) {
			queued = true;
			window.requestAnimationFrame( update );
		}
	}

	window.addEventListener( 'scroll', queue, { passive: true } );
	window.addEventListener( 'resize', queue );
	queue();
}() );

/**
 * "Back to contents": scroll up to the top of the article on the same page (never leave the page). The contents
 * bar is sticky, so scrolling "to the bar" would do nothing; the anchor #toc-top marks where it sits in the flow.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( '.cemeti-toc-back a' ) : null;
		if ( ! link ) {
			return;
		}
		event.preventDefault();
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		// The contents bar sticks to the top while reading, so "its place" is the top of the article.
		var top = document.getElementById( 'toc-top' );
		var y = top ? Math.max( 0, top.getBoundingClientRect().top + window.pageYOffset - 1 ) : 0;
		window.scrollTo( { top: y > 120 ? y : 0, behavior: reduce ? 'auto' : 'smooth' } );
		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', '#toc-top' );
		}
	} );
}() );
