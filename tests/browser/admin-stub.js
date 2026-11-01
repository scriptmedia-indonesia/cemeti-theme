/* Stands in for WordPress' media library in the admin fixture: picking returns the next prepared selection. */
window.__picks = [];
window.wp = { media: function () {
	var handlers = {};
	var frame = {
		on: function ( name, fn ) { handlers[ name ] = fn; return frame; },
		open: function () {
			var picked = window.__picks.shift() || [];
			frame.state = function () { return { get: function () { return { each: function ( fn ) { picked.forEach( function ( p ) { fn( { toJSON: function () { return p; } } ); } ); }, first: function () { return { toJSON: function () { return picked[ 0 ]; } }; } }; } }; };
			handlers.select();
		}
	};
	return frame;
} };
