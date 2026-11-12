/**
 * Media repair runner (Tools → Cemeti Converter → 0. Media).
 * Runs one step at a time through admin-ajax and stays on the page. When the server answers with an error page
 * (404, 5xx, a firewall page) it waits and tries again; the progress is saved on the server after every step.
 */
( function () {
	'use strict';
	var box = document.getElementById( 'cemeti-media-runner' );
	if ( ! box || ! window.fetch ) {
		return;
	}
	var ajax = box.getAttribute( 'data-ajax' );
	var nonce = box.getAttribute( 'data-nonce' );
	var action = box.getAttribute( 'data-action' ) || 'cemeti_media_step';
	var status = box.querySelector( '.cemeti-media-status' );
	var stopBtn = box.querySelector( '.cemeti-media-stop' );
	var buttons = box.querySelectorAll( '[data-phase]' );
	var MAX_RETRY = parseInt( box.getAttribute( 'data-retries' ) || '6', 10 );
	var PAUSE = parseInt( box.getAttribute( 'data-pause' ) || '1200', 10 );
	var running = false;
	var stopped = false;

	function say( text, kind ) {
		status.textContent = text;
		status.className = 'cemeti-media-status notice inline notice-' + ( kind || 'info' );
		status.hidden = false;
	}
	function wait( ms ) {
		return new Promise( function ( resolve ) { setTimeout( resolve, ms ); } );
	}
	function lock( on ) {
		running = on;
		Array.prototype.forEach.call( buttons, function ( b ) { b.classList.toggle( 'disabled', on ); b.setAttribute( 'aria-disabled', on ? 'true' : 'false' ); } );
		stopBtn.hidden = ! on;
	}
	function request( phase, fresh ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', nonce );
		body.set( 'phase', phase );
		if ( fresh ) {
			body.set( 'fresh', '1' );
		}
		return fetch( ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( res ) {
			return res.text().then( function ( text ) {
				var data = null;
				try { data = JSON.parse( text ); } catch ( e ) { data = null; }
				if ( ! res.ok || ! data || ! data.success ) {
					var err = new Error( 'HTTP ' + res.status + ( data ? '' : ' (not the expected answer)' ) );
					err.http = res.status;
					throw err;
				}
				return data.data;
			} );
		} );
	}
	function counts( r ) {
		if ( r.filled !== undefined ) {
			return ' — featured image set on ' + r.filled + ' entries' + ( r.nothing ? ', ' + r.nothing + ' have no picture to use' : '' );
		}
		return ' — checked OK ' + r.ok + ', restored ' + r.restored + ', failed ' + r.failed + ' · booklets ' + ( r.booklets || 0 ) + ' · posts fixed ' + r.posts + ', pictures added ' + r.created + ', featured set ' + r.thumbs;
	}
	async function run( phase ) {
		if ( running ) {
			return;
		}
		stopped = false;
		lock( true );
		var label = 'posts' === phase ? 'Fixing posts' : ( 'files' === phase ? 'Restoring files' : ( 'thumbs' === phase ? 'Setting featured images' : ( 'booklets' === phase ? 'Fetching booklets' : 'Working' ) ) );
		var fresh = 'posts' === phase || 'thumbs' === phase || 'booklets' === phase;
		var tries = 0;
		while ( ! stopped ) {
			try {
				var d = await request( phase, fresh );
				tries = 0;
				fresh = false;
				if ( 'reset' === phase || 'done' === d.phase ) {
					say( 'Done. Run the steps again.' + counts( d.report ), 'success' );
					break;
				}
				if ( d.stopped ) {
					say( 'STOPPED: this server cannot reach the old site (see the problems below).' + counts( d.report ), 'error' );
					break;
				}
				if ( 'retry' === phase ) {
					phase = 'files';
				}
				if ( d.remaining <= 0 ) {
					say( label + ': finished.' + counts( d.report ) + ' Reload the page to see the new numbers.', 'success' );
					break;
				}
				say( label + ': ' + d.remaining + ' left, continuing…' + counts( d.report ), 'info' );
				await wait( PAUSE );
			} catch ( e ) {
				tries++;
				if ( tries > MAX_RETRY ) {
					say( 'The server keeps answering with an error (' + e.message + '). Nothing is lost: progress is saved. Wait a few minutes, reload this page and press the same button again to continue.', 'error' );
					break;
				}
				var delay = Math.min( 30000, PAUSE * Math.pow( 2, tries ) );
				say( 'The server answered with an error (' + e.message + '). Trying again in ' + Math.round( delay / 1000 ) + ' s (' + tries + '/' + MAX_RETRY + ')…', 'warning' );
				await wait( delay );
			}
		}
		if ( stopped ) {
			say( 'Stopped. Progress is saved; press a button to continue.', 'warning' );
		}
		lock( false );
	}
	Array.prototype.forEach.call( buttons, function ( b ) {
		b.addEventListener( 'click', function ( ev ) {
			ev.preventDefault();
			var phase = b.getAttribute( 'data-phase' );
			if ( 'reset' === phase && ! window.confirm( 'Forget what was checked and start again?' ) ) {
				return;
			}
			run( phase );
		} );
	} );
	stopBtn.addEventListener( 'click', function () { stopped = true; } );
}() );
