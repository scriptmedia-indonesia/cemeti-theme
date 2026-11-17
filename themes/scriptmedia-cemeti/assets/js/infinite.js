/**
 * Lists load the next page by themselves while the visitor scrolls (QA: "pagination by scroll down").
 * The normal "Newer / Older" links stay in the page as the fallback without JavaScript, and are hidden when
 * this script runs. It only reads the next page's own list, so the markup of the cards is never rebuilt here.
 */
( function () {
	'use strict';

	var observer = null;

	function init() {
	if ( observer ) {
		observer.disconnect();
		observer = null;
	}
	var nav = document.querySelector( '.navigation.pagination, .pagination' );
	var list = document.querySelector( '.cemeti-card-grid, .cemeti-row-list, .cemeti-opencalls' );
	if ( ! nav || ! list || ! ( 'IntersectionObserver' in window ) || ! window.fetch || ! window.DOMParser ) {
		return;
	}

	var itemSelector = list.classList.contains( 'cemeti-opencalls' ) ? ':scope > article' : ':scope > li';
	var next = nav.querySelector( 'a.next' );
	if ( ! next ) {
		return;
	}
	nav.hidden = true;

	var status = document.createElement( 'p' );
	status.className = 'screen-reader-text';
	status.setAttribute( 'role', 'status' );
	list.parentNode.insertBefore( status, list.nextSibling );

	var sentinel = document.createElement( 'div' );
	sentinel.setAttribute( 'aria-hidden', 'true' );
	nav.parentNode.insertBefore( sentinel, nav );

	var loading = false;
	var url = next.href;

	function load() {
		if ( loading || ! url ) {
			return;
		}
		loading = true;
		fetch( url, { credentials: 'same-origin' } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( String( response.status ) );
				}
				return response.text();
			} )
			.then( function ( html ) {
				var doc = new DOMParser().parseFromString( html, 'text/html' );
				var more = doc.querySelector( '.cemeti-card-grid, .cemeti-row-list, .cemeti-opencalls' );
				if ( more ) {
					more.querySelectorAll( itemSelector ).forEach( function ( item ) {
						list.appendChild( document.importNode( item, true ) );
					} );
					status.textContent = '';
					status.textContent = list.querySelectorAll( itemSelector ).length + ' items';
				}
				var link = doc.querySelector( '.pagination a.next' );
				url = link ? link.href : '';
				if ( ! url ) {
					observer.disconnect();
					sentinel.remove();
				}
				loading = false;
			} )
			.catch( function () {
				// Give the visitor the ordinary link back.
				nav.hidden = false;
				observer.disconnect();
				loading = false;
			} );
	}

	observer = new IntersectionObserver( function ( entries ) {
		if ( entries.some( function ( entry ) { return entry.isIntersecting; } ) ) {
			load();
		}
	}, { rootMargin: '0px 0px 1400px 0px' } );
	observer.observe( sentinel );
	}

	init();
	// A list that was swapped in place (Residency categories) gets its own next-page loading.
	document.addEventListener( 'cemeti:list-replaced', init );
}() );
