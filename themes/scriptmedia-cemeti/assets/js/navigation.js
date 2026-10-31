/**
 * Cemeti overlay controller: menu, search bar and What's on panel.
 * Handles aria-expanded, ESC, focus trap for the modal panel and scroll lock.
 * Only transform/opacity are animated (see components in layout.css).
 */
( function () {
	'use strict';

	var doc = document;
	var body = doc.body;
	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';

	var panels = {
		menu: { el: doc.getElementById( 'cemeti-menu' ), modal: false },
		search: { el: doc.getElementById( 'cemeti-search' ), modal: false },
		'whats-on': { el: doc.getElementById( 'cemeti-whats-on' ), modal: true }
	};
	var current = null;
	var opener = null;
	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function toggles( name ) {
		return doc.querySelectorAll( '[data-cemeti-toggle="' + name + '"]' );
	}

	function setExpanded( name, value ) {
		toggles( name ).forEach( function ( button ) {
			button.setAttribute( 'aria-expanded', value ? 'true' : 'false' );
		} );
	}

	function focusables( container ) {
		return Array.prototype.filter.call( container.querySelectorAll( FOCUSABLE ), function ( node ) {
			return node.offsetParent !== null;
		} );
	}

	function open( name, trigger ) {
		var panel = panels[ name ];
		if ( ! panel || ! panel.el ) {
			return;
		}
		if ( current && current !== name ) {
			close( current, true );
		}
		current = name;
		opener = trigger || null;
		panel.el.hidden = false;
		// Force layout so the transition runs from the closed state.
		void panel.el.offsetWidth;
		panel.el.classList.add( 'is-open' );
		setExpanded( name, true );
		if ( panel.modal ) {
			body.classList.add( 'cemeti-scroll-locked' );
		}
		if ( name === 'menu' ) {
			panel.el.querySelectorAll( '.cemeti-menu__item' ).forEach( function ( item, index ) {
				item.style.setProperty( '--i', index );
			} );
		}
		var target = name === 'search' ? panel.el.querySelector( 'input' ) : focusables( panel.el )[ 0 ];
		if ( target ) {
			target.focus( { preventScroll: true } );
		}
	}

	function close( name, silent ) {
		var panel = panels[ name ];
		if ( ! panel || ! panel.el || panel.el.hidden ) {
			return;
		}
		panel.el.classList.remove( 'is-open' );
		setExpanded( name, false );
		if ( panel.modal ) {
			body.classList.remove( 'cemeti-scroll-locked' );
		}
		var finish = function () {
			if ( ! panel.el.classList.contains( 'is-open' ) ) {
				panel.el.hidden = true;
			}
		};
		if ( reduceMotion ) {
			finish();
		} else {
			window.setTimeout( finish, 300 );
		}
		if ( current === name ) {
			current = null;
		}
		if ( ! silent && opener ) {
			opener.focus();
		}
		if ( ! silent ) {
			opener = null;
		}
	}

	doc.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '[data-cemeti-toggle]' );
		if ( toggle ) {
			var name = toggle.getAttribute( 'data-cemeti-toggle' );
			if ( current === name ) {
				close( name );
			} else {
				open( name, toggle );
			}
			return;
		}
		var closer = event.target.closest( '[data-cemeti-close]' );
		if ( closer ) {
			close( closer.getAttribute( 'data-cemeti-close' ) );
			return;
		}
		// Click on a link inside the menu: let navigation happen, nothing to do.
		// Click outside the open menu/search closes it.
		if ( current && ! panels[ current ].modal && ! event.target.closest( '.cemeti-header' ) ) {
			close( current, true );
		}
	} );

	doc.addEventListener( 'keydown', function ( event ) {
		if ( ! current ) {
			return;
		}
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			close( current );
			return;
		}
		if ( event.key === 'Tab' && panels[ current ].modal ) {
			var nodes = focusables( panels[ current ].el );
			if ( ! nodes.length ) {
				return;
			}
			var first = nodes[ 0 ];
			var last = nodes[ nodes.length - 1 ];
			if ( event.shiftKey && doc.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && doc.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}
	} );
}() );
