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

	// Diagnostics for support requests: open any page with ?cemeti-debug=1 to see what this browser does.
	if ( /[?&]cemeti-debug\b/.test( window.location.search ) ) {
		var lines = [];
		var cs = function ( selector, prop ) {
			var el = document.querySelector( selector );
			return el ? window.getComputedStyle( el )[ prop ] : '(none on this page)';
		};
		var yes = function ( v ) { return v ? 'yes' : 'NO'; };
		lines.push( 'Browser: ' + window.navigator.userAgent );
		lines.push( 'Window: ' + window.innerWidth + ' x ' + window.innerHeight + '   pixel ratio ' + window.devicePixelRatio );
		lines.push( 'Reduced motion asked: ' + yes( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ).replace( 'NO', 'no' ) );
		lines.push( 'CSS sticky: ' + yes( window.CSS && CSS.supports && ( CSS.supports( 'position', 'sticky' ) || CSS.supports( 'position', '-webkit-sticky' ) ) ) + '   clamp(): ' + yes( window.CSS && CSS.supports && CSS.supports( 'font-size', 'clamp(1rem, 1vw, 2rem)' ) ) + '   aspect-ratio: ' + yes( window.CSS && CSS.supports && CSS.supports( 'aspect-ratio', '1' ) ) + '   :has(): ' + yes( window.CSS && CSS.supports && CSS.supports( 'selector(:has(a))' ) ) );
		lines.push( 'Flex gap: ' + yes( ! document.documentElement.classList.contains( 'cemeti-no-flex-gap' ) ) + '   WebP: ' + yes( document.createElement( 'canvas' ).toDataURL && document.createElement( 'canvas' ).toDataURL( 'image/webp' ).indexOf( 'data:image/webp' ) === 0 ) );
		lines.push( 'Header position: ' + cs( '.cemeti-header', 'position' ) + '   height var: ' + cs( '.cemeti-header', 'minHeight' ) );
		lines.push( 'Title position: ' + cs( '.cemeti-page-head', 'position' ) + ' / ' + cs( '.cemeti-home .cemeti-heading', 'position' ) + '   top: ' + cs( '.cemeti-page-head', 'top' ) + '   z-index: ' + cs( '.cemeti-page-head', 'zIndex' ) );
		lines.push( 'html overflow-x: ' + window.getComputedStyle( document.documentElement ).overflowX + '   body overflow-x: ' + window.getComputedStyle( document.body ).overflowX );
		lines.push( 'Script fallback for the titles: ' + ( document.documentElement.classList.contains( 'cemeti-pin-fallback' ) ? 'ON (CSS sticky was not working)' : 'off' ) );
		var box = document.createElement( 'pre' );
		box.textContent = lines.join( '\n' );
		box.style.cssText = 'position:fixed;left:8px;bottom:8px;right:8px;z-index:99999;margin:0;padding:10px;background:#000;color:#0f0;font:12px/1.4 monospace;white-space:pre-wrap;overflow:auto;max-height:45vh;';
		document.body.appendChild( box );
	}
}() );
