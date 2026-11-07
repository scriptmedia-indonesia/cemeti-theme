/**
 * Minimal static file server for the browser tests (and for looking at build/qa/*.html by hand).
 * Serves the repository root on http://127.0.0.1:8765 — nothing outside it.
 *   node tests/browser/serve.js        # then open http://127.0.0.1:8765/build/qa/home.html
 */
const http = require( 'http' );
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '../..' );
const types = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.webp': 'image/webp', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.pdf': 'application/pdf' };

function start( port = 8765 ) {
	const server = http.createServer( ( req, res ) => {
		const file = path.resolve( root, '.' + decodeURIComponent( req.url.split( '?' )[0] ) );
		if ( file !== root && ! file.startsWith( root + path.sep ) ) {
			res.writeHead( 403 ).end();
			return;
		}
		fs.readFile( file, ( err, data ) => {
			if ( err ) {
				res.writeHead( 404 ).end();
				return;
			}
			res.writeHead( 200, { 'Content-Type': types[ path.extname( file ) ] || 'application/octet-stream', 'Access-Control-Allow-Origin': '*' } ).end( data );
		} );
	} );
	return new Promise( ( resolve ) => server.listen( port, '127.0.0.1', () => resolve( server ) ) );
}

module.exports = { start };
if ( require.main === module ) {
	start().then( () => console.log( 'Serving ' + root + ' on http://127.0.0.1:8765' ) );
}
