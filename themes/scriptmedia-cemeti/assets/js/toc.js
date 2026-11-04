/**
 * Table of contents on detail pages: marks the section being read (aria-current) while the visitor scrolls.
 * A section is "being read" once its start has reached the line where an anchor jump puts it (just under the header
 * and the contents bar, the page's scroll-padding), so the link just clicked is the one marked, even when the next
 * section is short and already on screen; at the very bottom of the page the last section that is visible wins.
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
	var chosenY = null;
	var timer = 0;
	var chosen = -1; // The link the visitor just clicked: it stays marked while its section cannot scroll higher.

	function update() {
		queued = false;
		if ( chosen > -1 && null !== chosenY && Math.abs( window.pageYOffset - chosenY ) > 40 ) {
			chosen = -1;
		}
		var pad = parseFloat( window.getComputedStyle( document.documentElement ).scrollPaddingTop );
		var line = ( isNaN( pad ) ? window.innerHeight * 0.2 : pad ) + 8;
		var current = -1;
		targets.forEach( function ( target, index ) {
			if ( target && target.getBoundingClientRect().top <= line ) {
				current = index;
			}
		} );
		// The end of the page: sections that can never scroll up to the line are still the ones being read.
		var atBottom = window.pageYOffset + window.innerHeight >= document.documentElement.scrollHeight - 2;
		if ( atBottom && window.pageYOffset > 0 && chosen < 0 ) {
			targets.forEach( function ( target, index ) {
				var top = target ? target.getBoundingClientRect().top : Infinity;
				if ( top > line && top < window.innerHeight * 0.75 ) {
					current = index;
				}
			} );
		}
		if ( chosen > -1 && atBottom && targets[ chosen ] ) {
			var chosenTop = targets[ chosen ].getBoundingClientRect().top;
			if ( chosenTop >= 0 && chosenTop < window.innerHeight ) {
				current = chosen;
			}
		}
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

	links.forEach( function ( link, index ) {
		link.addEventListener( 'click', function () {
			chosen = index;
			chosenY = null;
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				chosenY = window.pageYOffset; // Where the jump ended; moving away from it ends the choice.
			}, 900 );
		} );
	} );
	[ 'wheel', 'touchmove', 'keydown' ].forEach( function ( type ) {
		window.addEventListener( type, function () {
			chosen = -1;
		}, { passive: true } );
	} );
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
