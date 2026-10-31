/**
 * Browser QA: opens the pages rendered by tests/render-pages.php (build/qa/*.html) in Chromium.
 * Run:  php tests/render-pages.php && node tests/browser/browser-qa.js
 * Needs Playwright (require('playwright')). Screenshots are written to build/qa/shots/.
 */
const { chromium } = require( 'playwright' );
const path = require( 'path' );
const fs = require( 'fs' );
const { start } = require( './serve.js' );

const root = path.resolve( __dirname, '../..' );
const dir = path.join( root, 'build/qa' );
const shots = path.join( dir, 'shots' );
fs.mkdirSync( shots, { recursive: true } );

let passed = 0;
const failures = [];
const notes = [];
function ok( cond, msg ) {
	if ( cond ) {
		passed++;
	} else {
		failures.push( msg );
		console.log( 'FAIL ' + msg );
	}
}
const url = ( name ) => 'http://127.0.0.1:8765/build/qa/' + name + '.html';

async function open( browser, name, width, height, extra = {} ) {
	const ctx = await browser.newContext( { viewport: { width, height: height || 800 }, reducedMotion: extra.reducedMotion || 'no-preference' } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( 'pageerror: ' + e.message ) );
	page.on( 'console', ( m ) => {
		if ( 'error' === m.type() && ! /cemeti\.test|ERR_NAME_NOT_RESOLVED|ERR_INTERNET_DISCONNECTED|Failed to load resource/.test( m.text() ) ) {
			errors.push( 'console: ' + m.text() );
		}
	} );
	await page.goto( url( name ) );
	await page.waitForLoadState( 'load' );
	return { page, ctx, errors };
}

( async () => {
	const server = await start();
	const browser = await chromium.launch();

	/* ---- 1. Every page at four widths: no horizontal scroll, no script errors. */
	const pages = [ 'home', 'archive-exhibition', 'archive-residency', 'archive-journal', 'single-exhibition', 'single-exhibition-bare', 'single-public-program', 'single-journal', 'single-open-call', 'single-open-call-sent', 'page-about', 'page-open-archive', 'page-support-us', 'page-contact-us', 'search', 'not-found' ];
	for ( const w of [ 360, 768, 1280, 1920 ] ) {
		for ( const name of pages ) {
			const { page, ctx, errors } = await open( browser, name, w );
			const m = await page.evaluate( () => ( { sw: document.documentElement.scrollWidth, iw: window.innerWidth, h: document.documentElement.scrollHeight } ) );
			ok( m.sw <= m.iw + 1, `${ name } @${ w }px: no horizontal scroll (page is ${ m.sw }px wide in a ${ m.iw }px window)` );
			ok( 0 === errors.length, `${ name } @${ w }px: no script errors ${ errors.join( ' | ' ) }` );
			if ( [ 'home', 'single-exhibition', 'single-open-call', 'archive-exhibition', 'page-about' ].includes( name ) ) {
				await page.screenshot( { path: path.join( shots, `${ name }-${ w }.png` ), fullPage: true } );
			}
			await ctx.close();
		}
	}

	/* ---- 2. Overlay menu behaviour (desktop). */
	{
		const { page, ctx } = await open( browser, 'home', 1280, 800 );
		const menu = page.locator( '#cemeti-menu' );
		const toggle = page.locator( '[data-cemeti-toggle="menu"]' );
		ok( await menu.isHidden(), 'menu: closed on load' );
		ok( 'false' === await toggle.getAttribute( 'aria-expanded' ), 'menu: aria-expanded=false on load' );
		await toggle.click();
		await page.waitForTimeout( 400 );
		ok( await menu.isVisible() && 'true' === await toggle.getAttribute( 'aria-expanded' ), 'menu: opens and sets aria-expanded' );
		const focused = await page.evaluate( () => document.activeElement && document.activeElement.closest( '#cemeti-menu' ) ? document.activeElement.textContent.trim() : null );
		ok( 'Home' === focused, `menu: focus moves to the first link (got ${ focused })` );
		const box = await menu.boundingBox();
		ok( Math.abs( box.x - 71 ) < 3 && box.width > 495 && box.width < 515, `menu: narrow panel under the Menu button (x=${ Math.round( box.x ) }, w=${ Math.round( box.width ) })` );
		const hb = await page.locator( '.cemeti-header__bar' ).boundingBox();
		ok( Math.abs( box.y - ( hb.y + hb.height ) ) < 6, 'menu: sits directly under the header bar' );
		const links = await page.locator( '#cemeti-menu a' ).evaluateAll( ( a ) => a.map( ( x ) => x.textContent.trim() ) );
		ok( [ 'Home', 'About', 'Exhibition', 'Solo', 'Group', 'Residency', 'Public Program', 'Open Studio', 'Open Call', 'Journal', 'Open Archive', 'Support Us', 'Contact Us' ].every( ( l ) => links.includes( l ) ), 'menu: all entries present: ' + links.join( ', ' ) );
		await page.screenshot( { path: path.join( shots, 'menu-open-1280.png' ), clip: { x: 0, y: 0, width: 1280, height: 520 } } );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 450 );
		ok( await menu.isHidden() && 'false' === await toggle.getAttribute( 'aria-expanded' ), 'menu: ESC closes it' );
		ok( await page.evaluate( () => document.activeElement.matches( '[data-cemeti-toggle="menu"]' ) ), 'menu: focus returns to the Menu button' );
		await toggle.click();
		await page.waitForTimeout( 350 );
		await toggle.click();
		await page.waitForTimeout( 450 );
		ok( await menu.isHidden(), 'menu: the button toggles it closed' );
		await toggle.click();
		await page.waitForTimeout( 350 );
		await page.mouse.click( 900, 600 );
		await page.waitForTimeout( 450 );
		ok( await menu.isHidden(), 'menu: a click outside closes it' );
		await ctx.close();
	}

	/* ---- 3. Search strip: replaces the header bar and keeps its height. */
	{
		const { page, ctx } = await open( browser, 'home', 1280, 800 );
		const search = page.locator( '#cemeti-search' );
		const bar = await page.locator( '.cemeti-header__bar' ).boundingBox();
		await page.locator( '[data-cemeti-toggle="search"]' ).click();
		await page.waitForTimeout( 400 );
		ok( await search.isVisible(), 'search: opens' );
		const sb = await search.boundingBox();
		ok( Math.abs( sb.height - bar.height ) < 3 && Math.abs( sb.y - bar.y ) < 3, `search: same size and place as the header bar (${ Math.round( sb.height ) } vs ${ Math.round( bar.height ) })` );
		ok( await page.evaluate( () => document.activeElement.id === 'cemeti-search-input' ), 'search: the input has focus' );
		const input = await page.locator( '#cemeti-search-input' ).boundingBox();
		ok( input.width > 900 && input.height > 28 && input.height < 36, `search: wide input (${ Math.round( input.width ) }x${ Math.round( input.height ) })` );
		await page.keyboard.type( 'harta' );
		ok( 'harta' === await page.inputValue( '#cemeti-search-input' ), 'search: typing works' );
		await page.screenshot( { path: path.join( shots, 'search-open-1280.png' ), clip: { x: 0, y: 0, width: 1280, height: 260 } } );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 450 );
		ok( await search.isHidden(), 'search: ESC closes it' );
		await page.locator( '[data-cemeti-toggle="search"]' ).click();
		await page.waitForTimeout( 350 );
		await page.locator( '.cemeti-search__close' ).click();
		await page.waitForTimeout( 450 );
		ok( await search.isHidden(), 'search: the X closes it' );
		await page.locator( '[data-cemeti-toggle="menu"]' ).click();
		await page.waitForTimeout( 350 );
		await page.locator( '[data-cemeti-toggle="search"]' ).click( { force: true } );
		await page.waitForTimeout( 450 );
		ok( await page.locator( '#cemeti-menu' ).isHidden() && await search.isVisible(), 'search: opening it closes the menu (one panel at a time)' );
		await ctx.close();
	}

	/* ---- 4. What's on dialog: focus trap, scroll lock, ESC (a trigger is injected, the design has none yet). */
	{
		const { page, ctx } = await open( browser, 'home', 1280, 700 );
		await page.evaluate( () => {
			const b = document.createElement( 'button' );
			b.id = 'test-trigger';
			b.textContent = 'open';
			b.setAttribute( 'data-cemeti-toggle', 'whats-on' );
			b.setAttribute( 'aria-expanded', 'false' );
			b.setAttribute( 'aria-controls', 'cemeti-whats-on' );
			document.body.prepend( b );
		} );
		await page.locator( '#test-trigger' ).click();
		await page.waitForTimeout( 400 );
		const dlg = page.locator( '#cemeti-whats-on' );
		ok( await dlg.isVisible(), "what's on: opens" );
		ok( await page.evaluate( () => document.body.classList.contains( 'cemeti-scroll-locked' ) && 'hidden' === getComputedStyle( document.body ).overflow ), "what's on: page scroll is locked" );
		const cover = await dlg.boundingBox();
		ok( cover.width >= 1279 && cover.height >= 699, "what's on: covers the viewport" );
		for ( let i = 0; i < 6; i++ ) {
			await page.keyboard.press( 'Tab' );
		}
		ok( await page.evaluate( () => !! document.activeElement.closest( '#cemeti-whats-on' ) ), "what's on: Tab never leaves the dialog" );
		await page.keyboard.press( 'Shift+Tab' );
		await page.keyboard.press( 'Shift+Tab' );
		ok( await page.evaluate( () => !! document.activeElement.closest( '#cemeti-whats-on' ) ), "what's on: Shift+Tab never leaves the dialog" );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 450 );
		ok( await dlg.isHidden() && await page.evaluate( () => ! document.body.classList.contains( 'cemeti-scroll-locked' ) ), "what's on: ESC closes it and unlocks the page" );
		ok( await page.evaluate( () => document.activeElement.id === 'test-trigger' ), "what's on: focus returns to the trigger" );
		await ctx.close();
	}

	/* ---- 5. Reduced motion: panels close at once. */
	{
		const { page, ctx } = await open( browser, 'home', 1280, 800, { reducedMotion: 'reduce' } );
		await page.locator( '[data-cemeti-toggle="menu"]' ).click();
		await page.waitForTimeout( 100 );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 60 );
		ok( await page.locator( '#cemeti-menu' ).isHidden(), 'reduced motion: the menu closes without waiting for a transition' );
		await ctx.close();
	}

	/* ---- 6. Header stays put, skip link, keyboard focus is visible. */
	{
		const { page, ctx } = await open( browser, 'single-exhibition', 1280, 800 );
		await page.mouse.wheel( 0, 1500 );
		await page.waitForTimeout( 300 );
		const top = await page.locator( '#site-header' ).evaluate( ( e ) => e.getBoundingClientRect().top );
		ok( Math.abs( top ) < 1, 'header: sticky while scrolling' );
		await page.evaluate( () => { window.scrollTo( 0, 0 ); document.activeElement.blur(); } );
		await page.keyboard.press( 'Tab' );
		const first = await page.evaluate( () => document.activeElement.className );
		ok( /skip-link/.test( first ), `keyboard: the skip link is the first stop (${ first })` );
		const visible = await page.evaluate( () => { const r = document.activeElement.getBoundingClientRect(); return r.top >= 0 && r.width > 0; } );
		ok( visible, 'keyboard: the skip link becomes visible on focus' );
		for ( const sel of [ '.cemeti-menu-toggle', '.cemeti-search-toggle', '.cemeti-lang a', '.cemeti-footer a' ] ) {
			await page.locator( sel ).first().focus();
			const o = await page.locator( sel ).first().evaluate( ( e ) => { const c = getComputedStyle( e ); return c.outlineStyle + ' ' + c.outlineWidth; } );
			ok( ! /^none/.test( o ) && ! / 0px$/.test( o ), `keyboard: visible focus ring on ${ sel } (${ o })` );
		}
		await ctx.close();
	}

	/* ---- 7. Footer and header measurements against the design (1280px frame). */
	{
		const { page, ctx } = await open( browser, 'home', 1280, 800 );
		const hb = await page.locator( '#site-header' ).boundingBox();
		ok( hb.height > 112 && hb.height < 132, `design: header is ~122px tall (${ Math.round( hb.height ) })` );
		const logo = await page.locator( '.cemeti-logo img' ).boundingBox();
		ok( Math.abs( ( logo.x + logo.width / 2 ) - 640 ) < 3, 'design: logo centred' );
		const fi = await page.locator( '.cemeti-footer__inner' ).boundingBox();
		ok( Math.abs( fi.x - 445 ) < 6, `design: footer block starts at ~445px (${ Math.round( fi.x ) })` );
		const bg = await page.locator( '.cemeti-footer' ).evaluate( ( e ) => getComputedStyle( e ).backgroundColor );
		ok( 'rgb(246, 209, 65)' === bg, 'design: footer is the brand yellow' );
		ok( 'rgb(255, 248, 239)' === await page.evaluate( () => getComputedStyle( document.body ).backgroundColor ), 'design: page background is the cream' );
		const fam = await page.evaluate( () => getComputedStyle( document.querySelector( '.cemeti-heading' ) ).fontFamily );
		ok( /Vampiro One/.test( fam ), 'design: headings use Vampiro One' );
		const fontsLoaded = await page.evaluate( async () => { await document.fonts.ready; return [ ...document.fonts ].filter( ( f ) => 'loaded' === f.status ).map( ( f ) => f.family ); } );
		ok( fontsLoaded.some( ( f ) => /Vampiro/.test( f ) ) && fontsLoaded.some( ( f ) => /Hanken/.test( f ) ), 'fonts: Vampiro One and Hanken Grotesk files actually load (' + fontsLoaded.join( ',' ) + ')' );
		await ctx.close();
	}

	/* ---- 7b. Detail pages follow the design: poster centred, text left-aligned; heading and Back stack on phones. */
	{
		const { page, ctx } = await open( browser, 'single-exhibition', 1280, 800 );
		ok( 'left' === await page.locator( '.cemeti-single__title' ).evaluate( ( e ) => getComputedStyle( e ).textAlign ), 'design: exhibition title is left-aligned' );
		const pb = await page.locator( '.cemeti-single__poster' ).boundingBox();
		ok( ! pb || Math.abs( ( pb.x + pb.width / 2 ) - 640 ) < 3, 'design: the poster is centred' );
		await ctx.close();
		const phone = await open( browser, 'archive-exhibition', 360, 740 );
		const hb = await phone.page.locator( '.cemeti-page-head .cemeti-heading' ).boundingBox();
		const bb = await phone.page.locator( '.cemeti-page-head .cemeti-back' ).boundingBox();
		ok( bb.y + bb.height <= hb.y + 2, `phone: the Back link sits above the heading, not squeezed beside it (${ Math.round( bb.y ) } vs ${ Math.round( hb.y ) })` );
		await phone.ctx.close();
	}

	/* ---- 7c. Touch targets are at least 24x24 CSS px (WCAG 2.2, 2.5.8). */
	for ( const w of [ 360, 1280 ] ) {
		const { page, ctx } = await open( browser, 'home', w, 800 );
		const small = await page.evaluate( () => [ ...document.querySelectorAll( 'button, .cemeti-lang a, summary, .cemeti-btn, input[type=submit]' ) ]
			.filter( ( e ) => e.offsetParent !== null || 'fixed' === getComputedStyle( e ).position )
			.map( ( e ) => { const b = e.getBoundingClientRect(); return { c: String( e.className || e.tagName ).slice( 0, 40 ), w: Math.round( b.width ), h: Math.round( b.height ) }; } )
			.filter( ( x ) => x.w < 24 || x.h < 24 ) );
		ok( 0 === small.length, `touch targets @${ w }px: none smaller than 24px ${ JSON.stringify( small ) }` );
		await ctx.close();
	}
	{
		const { page, ctx } = await open( browser, 'home', 1280, 800 );
		const logo = await page.locator( '.cemeti-logo img' ).evaluate( ( e ) => ( { src: e.currentSrc, w: e.naturalWidth } ) );
		const bytes = await page.evaluate( async ( src ) => ( await fetch( src ) ).headers.get( 'content-length' ), logo.src );
		ok( /logo-(480|960)\.webp/.test( logo.src ) && Number( bytes ) < 40000, `logo: a small responsive file is served (${ logo.src.split( '/' ).pop() }, ${ bytes } bytes)` );
		await ctx.close();
	}

	/* ---- 8. Open Call form works in the browser (native validation, file input). */
	{
		const { page, ctx } = await open( browser, 'single-open-call', 1280, 900 );
		const valid = await page.locator( '.cemeti-apply__form' ).evaluate( ( f ) => f.checkValidity() );
		ok( false === valid, 'form: empty submission is stopped by the browser (required fields)' );
		const req = await page.locator( '.cemeti-apply__form [required]' ).count();
		ok( 6 === req, `form: name, email and four documents are required (${ req })` );
		await page.fill( '#app_name', 'Rina' );
		await page.fill( '#app_email', 'bukan-email' );
		ok( false === await page.locator( '#app_email' ).evaluate( ( e ) => e.checkValidity() ), 'form: a bad e-mail is flagged by the browser' );
		const trap = await page.locator( '.cemeti-apply__trap' ).boundingBox();
		ok( ! trap || trap.x < -1000 || trap.height === 0, 'form: the hidden trap field is off-screen' );
		await ctx.close();
	}

	/* ---- 9. Colour contrast measured on the rendered pages. */
	const contrast = {};
	for ( const name of [ 'home', 'single-exhibition', 'archive-exhibition', 'archive-journal', 'page-about' ] ) {
		const { page, ctx } = await open( browser, name, 1280, 800 );
		const res = await page.evaluate( () => {
			const lum = ( [ r, g, b ] ) => { const f = ( c ) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 ); }; return 0.2126 * f( r ) + 0.7152 * f( g ) + 0.0722 * f( b ); };
			const parse = ( s ) => { const m = s.match( /rgba?\(([^)]+)\)/ ); if ( ! m ) return null; const p = m[1].split( /[ ,\/]+/ ).map( Number ); return [ p[0], p[1], p[2], p.length > 3 ? p[3] : 1 ]; };
			const bgOf = ( el ) => { let e = el; while ( e ) { const c = parse( getComputedStyle( e ).backgroundColor ); if ( c && c[3] > 0.9 ) return c; e = e.parentElement; } return [ 255, 255, 255, 1 ]; };
			const out = {};
			const walker = document.createTreeWalker( document.body, NodeFilter.SHOW_TEXT );
			let n;
			while ( ( n = walker.nextNode() ) ) {
				if ( ! n.textContent.trim() ) continue;
				const el = n.parentElement;
				const cs = getComputedStyle( el );
				if ( 'none' === cs.display || 'hidden' === cs.visibility || ! el.offsetParent && 'fixed' !== cs.position ) continue;
				if ( el.closest( '[hidden]' ) || el.closest( '.screen-reader-text' ) ) continue;
				const fg = parse( cs.color ); const bg = bgOf( el );
				const l1 = lum( fg ) , l2 = lum( bg );
				const ratio = ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
				const size = parseFloat( cs.fontSize ); const bold = parseInt( cs.fontWeight, 10 ) >= 700;
				const large = size >= 24 || ( size >= 18.66 && bold );
				if ( ratio < ( large ? 3 : 4.5 ) ) {
					const key = `${ cs.color } on ${ 'rgb(' + bg.slice( 0, 3 ).join( ', ' ) + ')' } ${ large ? '(large)' : '' }`;
					out[ key ] = out[ key ] || { ratio: Math.round( ratio * 100 ) / 100, n: 0, sample: el.className || el.tagName };
					out[ key ].n++;
				}
			}
			return out;
		} );
		for ( const [ k, v ] of Object.entries( res ) ) {
			contrast[ k ] = contrast[ k ] || { ...v, n: 0, pages: [] };
			contrast[ k ].n += v.n;
			contrast[ k ].pages.push( name );
		}
		await ctx.close();
	}
	const known = [ 'rgb(241, 98, 84) on rgb(242, 232, 219)', 'rgb(246, 209, 65) on rgb(255, 248, 239)', 'rgb(138, 138, 141) on rgb(255, 248, 239)', 'rgb(28, 117, 188) on rgb(246, 209, 65)', 'rgb(241, 98, 84) on rgb(255, 248, 239)', 'rgb(255, 255, 255) on rgb(0, 161, 75)', 'rgb(74, 74, 77) on rgb(241, 98, 84)', 'rgb(246, 209, 65) on rgb(28, 117, 188)', 'rgb(0, 0, 0) on rgb(0, 0, 0)' ];
	for ( const [ k, v ] of Object.entries( contrast ) ) {
		const documented = known.some( ( x ) => k.startsWith( x ) );
		notes.push( `${ documented ? 'known ' : 'NEW   ' } contrast ${ v.ratio }:1  ${ k }  ×${ v.n } (${ v.sample }; ${ [ ...new Set( v.pages ) ].join( ', ' ) })` );
		if ( ! documented ) {
			ok( false, `contrast: a pair that is not in DESIGN-NOTES fails: ${ k } = ${ v.ratio }:1 (${ v.sample })` );
		}
	}

	await browser.close();
	server.close();
	console.log( '\nContrast report (design colours, see docs/DESIGN-NOTES.md section 4):' );
	notes.forEach( ( n ) => console.log( '  ' + n ) );
	console.log( `\n${ passed } checks passed, ${ failures.length } failed` );
	process.exit( failures.length ? 1 : 0 );
} )().catch( ( e ) => { console.error( e ); process.exit( 2 ); } );
