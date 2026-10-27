/**
 * Cemeti meta box helpers: media picker (single and multiple), repeater rows and
 * the 4-item limit of relation selects. Plain JS, no build step.
 */
( function () {
	'use strict';

	var doc = document;

	function thumbs( box ) {
		return box.querySelector( '.cemeti-media__thumbs' );
	}

	function setIds( box, ids, images ) {
		box.querySelector( 'input[type="hidden"]' ).value = ids.join( ',' );
		var holder = thumbs( box );
		holder.innerHTML = '';
		images.forEach( function ( url ) {
			var img = doc.createElement( 'img' );
			img.src = url;
			img.width = 80;
			img.height = 80;
			holder.appendChild( img );
		} );
	}

	doc.addEventListener( 'click', function ( event ) {
		var pick = event.target.closest( '.cemeti-media__pick' );
		var clear = event.target.closest( '.cemeti-media__clear' );
		var add = event.target.closest( '.cemeti-repeater__add' );
		var remove = event.target.closest( '.cemeti-repeater__remove' );

		if ( pick && window.wp && wp.media ) {
			var box = pick.closest( '.cemeti-media' );
			var frame = wp.media( { title: 'Choose', multiple: box.getAttribute( 'data-multiple' ) === '1', library: { type: 'image' } } );
			frame.on( 'select', function () {
				var ids = [];
				var urls = [];
				frame.state().get( 'selection' ).each( function ( item ) {
					var data = item.toJSON();
					ids.push( data.id );
					urls.push( ( data.sizes && data.sizes.thumbnail ) ? data.sizes.thumbnail.url : data.url );
				} );
				setIds( box, ids, urls );
			} );
			frame.open();
		} else if ( clear ) {
			setIds( clear.closest( '.cemeti-media' ), [], [] );
		} else if ( add ) {
			var repeater = add.closest( '.cemeti-repeater' );
			var template = repeater.querySelector( '.cemeti-repeater__template' );
			var index = parseInt( repeater.getAttribute( 'data-next' ), 10 ) || 0;
			repeater.setAttribute( 'data-next', index + 1 );
			var wrapper = doc.createElement( 'div' );
			wrapper.innerHTML = template.innerHTML.replace( /__INDEX__/g, String( index ) );
			repeater.querySelector( '.cemeti-repeater__rows' ).appendChild( wrapper.firstElementChild );
		} else if ( remove ) {
			remove.closest( '.cemeti-repeater__row' ).remove();
		}
	} );

	doc.addEventListener( 'change', function ( event ) {
		var select = event.target.closest( '.cemeti-relation' );
		if ( ! select ) {
			return;
		}
		var max = parseInt( select.getAttribute( 'data-max' ), 10 ) || 4;
		var chosen = Array.prototype.filter.call( select.options, function ( option ) {
			return option.selected;
		} );
		// Keep the first picks when more than the limit are selected.
		chosen.slice( max ).forEach( function ( option ) {
			option.selected = false;
		} );
	} );
}() );
