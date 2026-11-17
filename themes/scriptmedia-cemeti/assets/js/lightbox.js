/**
 * Lightbox for the featured image of an entry page: a click on the picture opens the large version over the page.
 * Close with the button, a click on the dark area, a click on the picture, or ESC. Without JavaScript (or with a
 * click that wants a new tab: Ctrl, Cmd, Shift, middle button) the link just opens the picture.
 */
( function () {
	'use strict';

	var doc = document;
	var box = null;
	var img = null;
	var closeBtn = null;
	var opener = null;
	var timer = null;

	function build( label ) {
		box = doc.createElement( 'div' );
		box.className = 'cemeti-lightbox';
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.hidden = true;
		img = doc.createElement( 'img' );
		img.className = 'cemeti-lightbox__img';
		closeBtn = doc.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'cemeti-lightbox__close';
		closeBtn.textContent = label || 'Close';
		box.appendChild( img );
		box.appendChild( closeBtn );
		doc.body.appendChild( box );
		box.addEventListener( 'click', function ( event ) {
			if ( event.target !== box && event.target !== img && event.target !== closeBtn ) {
				return;
			}
			close();
		} );
	}

	function open( link ) {
		var thumb = link.querySelector( 'img' );
		if ( ! box ) {
			build( link.getAttribute( 'data-close' ) );
		}
		window.clearTimeout( timer );
		opener = link;
		img.alt = thumb ? thumb.getAttribute( 'alt' ) || '' : '';
		img.src = link.getAttribute( 'href' );
		box.setAttribute( 'aria-label', img.alt || closeBtn.textContent );
		box.hidden = false;
		void box.offsetWidth; // Let the fade start from the hidden state.
		box.classList.add( 'is-open' );
		doc.body.classList.add( 'cemeti-scroll-locked' );
		closeBtn.focus();
	}

	function close() {
		if ( ! box || box.hidden ) {
			return;
		}
		box.classList.remove( 'is-open' );
		doc.body.classList.remove( 'cemeti-scroll-locked' );
		timer = window.setTimeout( function () {
			if ( ! box.classList.contains( 'is-open' ) ) {
				box.hidden = true;
				img.removeAttribute( 'src' );
			}
		}, 250 );
		if ( opener ) {
			opener.focus();
			opener = null;
		}
	}

	doc.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( 'a[data-cemeti-lightbox]' ) : null;
		if ( ! link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) {
			return;
		}
		event.preventDefault();
		open( link );
	} );

	doc.addEventListener( 'keydown', function ( event ) {
		if ( ! box || box.hidden ) {
			return;
		}
		if ( event.key === 'Escape' || event.key === 'Esc' ) {
			event.preventDefault();
			close();
		} else if ( event.key === 'Tab' ) {
			event.preventDefault(); // The close button is the only thing to reach: keep the focus on it.
			closeBtn.focus();
		}
	} );
}() );
