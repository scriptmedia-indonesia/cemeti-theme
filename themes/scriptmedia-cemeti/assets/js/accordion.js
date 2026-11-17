/**
 * Support Us: an address with #row opens that dropdown row (the sub-pages' own addresses lead here).
 */
( function () {
	'use strict';

	function openFromHash() {
		var id = decodeURIComponent( window.location.hash.replace( /^#/, '' ) );
		if ( ! id ) {
			return;
		}
		var row = document.getElementById( id );
		if ( row && row.tagName === 'DETAILS' ) {
			row.open = true;
			row.scrollIntoView( { block: 'start' } );
		}
	}

	openFromHash();
	window.addEventListener( 'hashchange', openFromHash );
}() );
