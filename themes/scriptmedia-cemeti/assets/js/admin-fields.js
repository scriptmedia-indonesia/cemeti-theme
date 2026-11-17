/**
 * Cemeti "Details" box: media picker (photos, single image, PDF), name lists, repeater blocks
 * (add, reorder, remove, "Add from Artists") and the 4-item limit of relation selects.
 * Plain JS, no build step. Order on screen = order on the page: PHP reads the form top to bottom.
 */
( function () {
	'use strict';

	var doc = document;

	function closest( node, selector ) {
		return node && node.closest ? node.closest( selector ) : null;
	}

	/* ---- Photos ---- */

	function syncMedia( box ) {
		var ids = Array.prototype.map.call( box.querySelectorAll( '.cemeti-media__item' ), function ( item ) {
			return item.getAttribute( 'data-id' );
		} );
		box.querySelector( 'input[type="hidden"]' ).value = ids.join( ',' );
	}

	function addPhoto( box, id, url, movable ) {
		var holder = box.querySelector( '.cemeti-media__thumbs' );
		var item = doc.createElement( 'span' );
		item.className = 'cemeti-media__item';
		item.setAttribute( 'data-id', id );
		var img = doc.createElement( 'img' );
		img.src = url;
		img.width = 80;
		img.height = 80;
		img.alt = '';
		item.appendChild( img );
		item.insertAdjacentHTML( 'beforeend', buttons( movable ) );
		holder.appendChild( item );
	}

	function buttons( movable ) {
		var html = '';
		if ( movable ) {
			html += '<button type="button" class="button-link cemeti-row__up" aria-label="Move up">&#8593;</button> ';
			html += '<button type="button" class="button-link cemeti-row__down" aria-label="Move down">&#8595;</button> ';
		}
		return html + '<button type="button" class="button-link cemeti-row__remove" aria-label="Remove">&#215;</button>';
	}

	function pickMedia( box ) {
		var multiple = box.getAttribute( 'data-multiple' ) === '1';
		var frame = wp.media( { title: multiple ? 'Add photos' : 'Choose', multiple: multiple, library: { type: 'image' } } );
		frame.on( 'select', function () {
			if ( ! multiple ) {
				box.querySelector( '.cemeti-media__thumbs' ).innerHTML = '';
			}
			var have = {};
			box.querySelectorAll( '.cemeti-media__item' ).forEach( function ( item ) {
				have[ item.getAttribute( 'data-id' ) ] = true;
			} );
			frame.state().get( 'selection' ).each( function ( item ) {
				var data = item.toJSON();
				if ( have[ String( data.id ) ] ) {
					return;
				}
				addPhoto( box, data.id, ( data.sizes && data.sizes.thumbnail ) ? data.sizes.thumbnail.url : data.url, multiple );
			} );
			syncMedia( box );
		} );
		frame.open();
	}

	function pickFile( box ) {
		var frame = wp.media( { title: 'Choose PDF', multiple: false, library: { type: 'application/pdf' } } );
		frame.on( 'select', function () {
			var data = frame.state().get( 'selection' ).first().toJSON();
			box.querySelector( 'input[type="hidden"]' ).value = data.id;
			box.querySelector( '.cemeti-file__name' ).textContent = data.filename || data.title || '';
		} );
		frame.open();
	}

	/* ---- Rows: names, photos and biography blocks ---- */

	var ROW = '.cemeti-list__row, .cemeti-media__item, .cemeti-repeater__row';

	function renumber( repeater ) {
		repeater.querySelectorAll( '.cemeti-repeater__row' ).forEach( function ( row, index ) {
			var name = row.querySelector( '[data-field="' + repeater.getAttribute( 'data-by' ) + '"] input' );
			var title = row.getAttribute( 'data-label' ) + ' ' + ( index + 1 ) + ( name && name.value.trim() ? ' — ' + name.value.trim() : '' );
			row.querySelector( '.cemeti-repeater__title' ).textContent = title;
		} );
	}

	function afterChange( row ) {
		var media = closest( row, '.cemeti-media' );
		var repeater = closest( row, '.cemeti-repeater' );
		if ( media && row.classList.contains( 'cemeti-media__item' ) ) {
			syncMedia( media );
		}
		if ( repeater && row.classList.contains( 'cemeti-repeater__row' ) ) {
			renumber( repeater );
		}
	}

	function moveRow( row, direction ) {
		var sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
		if ( ! sibling ) {
			return;
		}
		if ( direction < 0 ) {
			row.parentNode.insertBefore( row, sibling );
		} else {
			row.parentNode.insertBefore( sibling, row );
		}
		afterChange( row );
	}

	function removeRow( row ) {
		if ( row.classList.contains( 'cemeti-repeater__row' ) ) {
			var typed = Array.prototype.some.call( row.querySelectorAll( 'input[type="text"], textarea' ), function ( field ) {
				return field.value.trim() !== '';
			} );
			if ( typed && ! window.confirm( 'Remove this block and everything typed in it?' ) ) {
				return;
			}
		}
		var media = closest( row, '.cemeti-media' );
		var repeater = closest( row, '.cemeti-repeater' );
		row.remove();
		if ( media ) {
			syncMedia( media );
		}
		if ( repeater ) {
			renumber( repeater );
		}
	}

	function addFromTemplate( container, templateSelector, replacements ) {
		var wrapper = doc.createElement( 'div' );
		var html = container.querySelector( templateSelector ).innerHTML;
		Object.keys( replacements ).forEach( function ( token ) {
			html = html.split( token ).join( replacements[ token ] );
		} );
		wrapper.innerHTML = html;
		return wrapper.firstElementChild;
	}

	function addListRow( list, value ) {
		var row = addFromTemplate( list, '.cemeti-list__template', {} );
		row.querySelector( 'input' ).value = value || '';
		list.querySelector( '.cemeti-list__rows' ).appendChild( row );
		return row;
	}

	function addBlock( repeater, nameValue ) {
		var index = parseInt( repeater.getAttribute( 'data-next' ), 10 ) || 0;
		repeater.setAttribute( 'data-next', index + 1 );
		var count = repeater.querySelectorAll( '.cemeti-repeater__row' ).length + 1;
		var row = addFromTemplate( repeater, '.cemeti-repeater__template', { __INDEX__: String( index ), __NUMBER__: String( count ) } );
		repeater.querySelector( '.cemeti-repeater__rows' ).appendChild( row );
		if ( nameValue ) {
			var field = row.querySelector( '[data-field="' + repeater.getAttribute( 'data-by' ) + '"] input' );
			if ( field ) {
				field.value = nameValue;
			}
		}
		renumber( repeater );
		return row;
	}

	function addFromSource( repeater ) {
		var source = repeater.getAttribute( 'data-from' );
		var by = repeater.getAttribute( 'data-by' );
		var have = {};
		repeater.querySelectorAll( '[data-field="' + by + '"] input' ).forEach( function ( input ) {
			have[ input.value.trim().toLowerCase() ] = true;
		} );
		var added = 0;
		doc.querySelectorAll( 'input[name="' + source + '[]"]' ).forEach( function ( input ) {
			var name = input.value.trim();
			if ( name && ! have[ name.toLowerCase() ] ) {
				have[ name.toLowerCase() ] = true;
				addBlock( repeater, name );
				added++;
			}
		} );
		return added;
	}

	doc.addEventListener( 'click', function ( event ) {
		var target = event.target;
		var hit = function ( selector ) {
			return closest( target, selector );
		};
		var row;
		if ( hit( '.cemeti-media__pick' ) && window.wp && wp.media ) {
			pickMedia( hit( '.cemeti-media' ) );
		} else if ( hit( '.cemeti-media__clear' ) ) {
			var box = hit( '.cemeti-media' );
			box.querySelector( '.cemeti-media__thumbs' ).innerHTML = '';
			syncMedia( box );
		} else if ( hit( '.cemeti-file__pick' ) && window.wp && wp.media ) {
			pickFile( hit( '.cemeti-file' ) );
		} else if ( hit( '.cemeti-file__clear' ) ) {
			var file = hit( '.cemeti-file' );
			file.querySelector( 'input[type="hidden"]' ).value = '';
			file.querySelector( '.cemeti-file__name' ).textContent = '';
		} else if ( hit( '.cemeti-list__add' ) ) {
			addListRow( hit( '.cemeti-list' ) ).querySelector( 'input' ).focus();
		} else if ( hit( '.cemeti-repeater__add' ) ) {
			var block = addBlock( hit( '.cemeti-repeater' ) );
			var first = block.querySelector( 'input[type="text"], textarea' );
			if ( first ) {
				first.focus();
			}
		} else if ( hit( '.cemeti-repeater__from' ) ) {
			addFromSource( hit( '.cemeti-repeater' ) );
		} else if ( hit( '.cemeti-row__up' ) && ( row = hit( ROW ) ) ) {
			moveRow( row, -1 );
		} else if ( hit( '.cemeti-row__down' ) && ( row = hit( ROW ) ) ) {
			moveRow( row, 1 );
		} else if ( hit( '.cemeti-row__remove' ) && ( row = hit( ROW ) ) ) {
			removeRow( row );
		}
	} );

	// Enter inside a name list adds the next name instead of saving the post.
	doc.addEventListener( 'keydown', function ( event ) {
		var input = event.key === 'Enter' ? closest( event.target, '.cemeti-list__row input' ) : null;
		if ( input ) {
			event.preventDefault();
			addListRow( closest( input, '.cemeti-list' ) ).querySelector( 'input' ).focus();
		}
	} );

	// Keep a block's title in step with the name typed in it.
	doc.addEventListener( 'input', function ( event ) {
		var repeater = closest( event.target, '.cemeti-repeater' );
		if ( repeater && closest( event.target, '[data-field="' + repeater.getAttribute( 'data-by' ) + '"]' ) ) {
			renumber( repeater );
		}
	} );

	doc.addEventListener( 'change', function ( event ) {
		var select = closest( event.target, '.cemeti-relation' );
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
