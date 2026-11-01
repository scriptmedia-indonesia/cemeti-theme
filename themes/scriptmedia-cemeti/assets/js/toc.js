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
