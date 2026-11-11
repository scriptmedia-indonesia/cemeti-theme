/**
 * Residency: choosing Regular or Research filters the list below at once, without leaving the page. Pressing the chosen
 * category again shows everything. The links are ordinary ?type= links (they work without JavaScript and when opened in a
 * new tab); this script only fetches the page they lead to and swaps the intro links and the list.
 */
( function () {
	'use strict';

	var intro = document.querySelector( '.cemeti-residency-intro' );
	var results = document.getElementById( 'cemeti-results' );
	if ( ! intro || ! results || ! window.fetch || ! window.DOMParser || ! window.history || ! history.pushState ) {
		return;
	}
	var token = 0;

	function swap( html ) {
		var doc = new DOMParser().parseFromString( html, 'text/html' );
		var newIntro = doc.querySelector( '.cemeti-residency-intro' );
		var newResults = doc.getElementById( 'cemeti-results' );
		if ( ! newIntro || ! newResults ) {
			return false;
		}
		intro.innerHTML = newIntro.innerHTML;
		results.innerHTML = newResults.innerHTML;
		document.dispatchEvent( new CustomEvent( 'cemeti:list-replaced' ) );
		return true;
	}

	function go( url, push ) {
		var mine = ++token;
		results.setAttribute( 'aria-busy', 'true' );
		fetch( url, { credentials: 'same-origin' } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( String( response.status ) );
				}
				return response.text();
			} )
			.then( function ( html ) {
				if ( mine !== token ) {
					return;
				}
				if ( ! swap( html ) ) {
					throw new Error( 'unexpected page' );
				}
				if ( push ) {
					try {
						history.pushState( { cemetiFilter: true }, '', url );
					} catch ( e ) {
						// An address the browser will not put in the history: the list is already swapped, keep it.
					}
				}
				results.removeAttribute( 'aria-busy' );
			} )
			.catch( function () {
				window.location.href = url; // The ordinary page load always works.
			} );
	}

	intro.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( 'h2 a' ) : null;
		if ( ! link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}
		event.preventDefault();
		go( link.href, true );
	} );

	window.addEventListener( 'popstate', function () {
		go( window.location.href, false );
	} );
}() );
