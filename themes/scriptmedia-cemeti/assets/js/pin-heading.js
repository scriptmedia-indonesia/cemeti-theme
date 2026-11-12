/**
 * Section titles stay pinned under the header while their section scrolls (CSS `position: sticky`). If a browser does not
 * honour the CSS for some reason (an older Safari, a Firefox quirk, a plugin's stylesheet), this does the same with a
 * transform. It only starts when it sees, after the visitor scrolls, that a title that should be pinned is not; where the
 * CSS works it never does anything. Visitors who asked for reduced motion get the static titles the CSS gives them.
 */
( function () {
	'use strict';

	if ( ! window.requestAnimationFrame ) {
		return;
	}
	if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}
	var heads = Array.prototype.slice.call( document.querySelectorAll( '.cemeti-page-head, .cemeti-home .cemeti-heading' ) );
	var header = document.querySelector( '.cemeti-header' );
	if ( ! heads.length || ! header ) {
		return;
	}
	var wide = window.matchMedia ? window.matchMedia( '(min-width: 48rem)' ) : { matches: true };
	var active = false;
	var checked = false;
	var ticking = false;

	function pinLine() {
		return header.getBoundingClientRect().bottom + 8;
	}

	// Is the CSS pinning working? Look at the first title whose section is clearly in the middle of the screen.
	function cssWorks() {
		var line = pinLine();
		for ( var i = 0; i < heads.length; i++ ) {
			var box = heads[ i ].parentNode.getBoundingClientRect();
			var h = heads[ i ].getBoundingClientRect();
			if ( box.top < line - 60 && box.bottom > line + h.height + 60 ) {
				return Math.abs( h.top - line ) < 4;
			}
		}
		return true; // Nothing to judge yet.
	}

	function place() {
		ticking = false;
		var line = pinLine();
		heads.forEach( function ( head ) {
			var box = head.parentNode.getBoundingClientRect();
			var current = parseFloat( head.getAttribute( 'data-pin' ) || '0' );
			var natural = head.getBoundingClientRect().top - current; // Where it sits without the transform.
			var room = box.bottom - natural - head.offsetHeight - current; // How far it may move before the section ends.
			var move = Math.max( 0, Math.min( line - natural, room + current ) );
			head.setAttribute( 'data-pin', String( move ) );
			head.style.transform = move > 0 ? 'translateY(' + Math.round( move ) + 'px)' : '';
		} );
	}

	function onScroll() {
		if ( ! wide.matches ) {
			return;
		}
		if ( ! active ) {
			if ( checked || ( window.pageYOffset || document.documentElement.scrollTop ) < 150 ) {
				return;
			}
			if ( cssWorks() ) {
				if ( window.pageYOffset > 400 ) {
					checked = true; // It works here: stay out of the way for good.
				}
				return;
			}
			active = true;
			document.documentElement.classList.add( 'cemeti-pin-fallback' );
		}
		if ( ! ticking ) {
			ticking = true;
			window.requestAnimationFrame( place );
		}
	}

	window.addEventListener( 'scroll', onScroll, { passive: true } );
	window.addEventListener( 'resize', function () {
		if ( active ) {
			onScroll();
		}
	} );
}() );
