/**
 * Shows the "More" share button only where the Web Share API exists.
 */
( function () {
	'use strict';

	if ( ! navigator.share ) {
		return;
	}
	document.querySelectorAll( '.cemeti-share__more' ).forEach( function ( button ) {
		button.hidden = false;
		button.addEventListener( 'click', function () {
			navigator.share( { title: button.getAttribute( 'data-title' ), url: button.getAttribute( 'data-url' ) } ).catch( function () {} );
		} );
	} );
}() );
