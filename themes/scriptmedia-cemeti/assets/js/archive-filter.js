/**
 * Open Archive: the type buttons and the year choose what the list shows, at once and without leaving the page.
 * The list always holds every item; the links and the form keep working without this script.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-cemeti-archive]' );
	if ( ! root ) {
		return;
	}
	var items = root.querySelectorAll( '.cemeti-archive__item' );
	var links = root.querySelectorAll( '.cemeti-archive__type' );
	var select = root.querySelector( 'select[name="year"]' );
	var empty = root.querySelector( '[data-cemeti-archive-empty]' );
	var type = '';
	Array.prototype.forEach.call( links, function ( link ) {
		if ( link.classList.contains( 'is-active' ) ) {
			type = link.getAttribute( 'data-type' ) || '';
		}
	} );

	function apply( push ) {
		var year = select ? select.value : '';
		var shown = 0;
		Array.prototype.forEach.call( items, function ( item ) {
			var show = ( ! type || item.getAttribute( 'data-type' ) === type ) && ( ! year || item.getAttribute( 'data-year' ) === year );
			item.hidden = ! show;
			shown += show ? 1 : 0;
		} );
		Array.prototype.forEach.call( links, function ( link ) {
			var on = ( link.getAttribute( 'data-type' ) || '' ) === type;
			link.classList.toggle( 'is-active', on );
			if ( on ) {
				link.setAttribute( 'aria-current', 'true' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
		if ( empty ) {
			empty.hidden = shown > 0;
		}
		if ( push && window.history && history.replaceState ) {
			var params = new URLSearchParams();
			if ( type ) {
				params.set( 'type', type );
			}
			if ( year ) {
				params.set( 'year', year );
			}
			var query = params.toString();
			try {
				history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) );
			} catch ( e ) {
				// The address cannot be changed here: the filter still works.
			}
		}
	}

	root.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( '.cemeti-archive__type' ) : null;
		if ( ! link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0 ) {
			return;
		}
		event.preventDefault();
		type = link.getAttribute( 'data-type' ) || '';
		apply( true );
	} );
	if ( select ) {
		select.addEventListener( 'change', function () {
			apply( true );
		} );
	}
	var form = root.querySelector( 'form' );
	if ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			apply( true );
		} );
	}
}() );
