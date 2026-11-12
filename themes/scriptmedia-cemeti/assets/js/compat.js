/**
 * Small feature checks for older browsers. `gap` on a flex container (Safari 14.0 and older, Chrome 83 and older) is the
 * one the layout relies on: without it the page gets the class `cemeti-no-flex-gap`, and the spacing comes from margins.
 */
( function () {
	'use strict';

	try {
		var box = document.createElement( 'div' );
		box.style.cssText = 'display:flex;flex-direction:column;row-gap:1px;position:absolute;visibility:hidden;';
		box.appendChild( document.createElement( 'span' ) );
		box.appendChild( document.createElement( 'span' ) );
		document.body.appendChild( box );
		var works = box.scrollHeight === 1;
		document.body.removeChild( box );
		if ( ! works ) {
			document.documentElement.classList.add( 'cemeti-no-flex-gap' );
		}
	} catch ( e ) {
		// Nothing to decide: leave the page as it is.
	}
}() );
