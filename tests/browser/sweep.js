/**
 * Final-QA sweep: every rendered page (build/qa/*.html) at phone, tablet, laptop and large-screen sizes, and the
 * interactive behaviour (menu, search, contents links, accordion) with touch emulation.
 * Run:  php tests/render-pages.php && php tests/flows-languages.php && node tests/browser/sweep.js
 * Reports: sideways overflow, clipped elements, text under 12 px, tap targets under 24 px, pictures without alt,
 * console / script errors, anything covering a link, pinned bars taller than a third of a short screen.
 */
const { chromium, devices } = require( 'playwright' );
const fs = require( 'fs' );
const path = require( 'path' );
const { start } = require( './serve.js' );
const dir = path.resolve( __dirname, '../../build/qa' );
const pages = fs.readdirSync( dir ).filter( ( f ) => f.endsWith( '.html' ) && ! /^pair-.*-en|admin|infinite/.test( f ) ).map( ( f ) => f.replace( '.html', '' ) );
const sizes = [ [ 320, 568 ], [ 360, 740 ], [ 390, 844 ], [ 412, 915 ], [ 430, 932 ], [ 600, 960 ], [ 768, 1024 ], [ 820, 1180 ], [ 1024, 768 ], [ 1180, 820 ], [ 1280, 720 ], [ 1366, 768 ], [ 1440, 900 ], [ 1536, 864 ], [ 1920, 1080 ], [ 2560, 1440 ], [ 844, 390 ], [ 915, 412 ] ];
const issues = {};
const add = ( k, m ) => { ( issues[ k ] = issues[ k ] || [] ).push( m ); };
( async () => {
	const server = await start();
	const browser = await chromium.launch();
	for ( const [ w, h ] of sizes ) {
		const ctx = await browser.newContext( { viewport: { width: w, height: h }, hasTouch: w < 1024 } );
		const page = await ctx.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		for ( const name of pages ) {
			await page.goto( 'http://127.0.0.1:8765/build/qa/' + name + '.html' );
			const r = await page.evaluate( () => {
				const W = document.documentElement.clientWidth;
				const vis = ( e ) => e.offsetParent !== null && ! e.closest( '[hidden], .cemeti-apply__trap, .screen-reader-text' );
				const out = { over: document.documentElement.scrollWidth - W, clip: [], small: [], tap: [], alt: 0, covered: [], pinned: 0 };
				document.querySelectorAll( 'h1,h2,h3,p,li,span,strong,a,label,input,img' ).forEach( ( e ) => {
					if ( ! vis( e ) ) { return; }
					const b = e.getBoundingClientRect();
					if ( b.width > 0 && ( b.right > W + 1 || b.left < -1 ) ) { out.clip.push( e.tagName + ':' + ( e.textContent || e.alt || '' ).trim().slice( 0, 20 ) ); }
				} );
				document.querySelectorAll( 'p,li,a,span,label,h1,h2,h3,strong' ).forEach( ( e ) => {
					if ( vis( e ) && e.childNodes.length && [ ...e.childNodes ].some( ( n ) => n.nodeType === 3 && n.textContent.trim() ) && parseFloat( getComputedStyle( e ).fontSize ) < 12 && ! e.closest( '.cemeti-footer' ) ) { out.small.push( e.textContent.trim().slice( 0, 20 ) ); }
				} );
				document.querySelectorAll( 'img' ).forEach( ( i ) => { if ( ! i.hasAttribute( 'alt' ) ) { out.alt++; } } );
				document.querySelectorAll( 'button, .cemeti-lang a, .cemeti-back, .cemeti-toc__links a, .cemeti-btn, nav a, summary' ).forEach( ( e ) => {
					if ( vis( e ) ) { const b = e.getBoundingClientRect(); if ( b.height < 24 || b.width < 24 ) { out.tap.push( e.className + ' ' + Math.round( b.width ) + 'x' + Math.round( b.height ) ); } }
				} );
				const hdr = document.querySelector( '.cemeti-header' );
				const bar = document.querySelector( '.cemeti-single__nav--sticky' );
				const pin = ( hdr && getComputedStyle( hdr ).position === 'sticky' ? hdr.getBoundingClientRect().height : 0 ) + ( bar && getComputedStyle( bar ).position === 'sticky' ? bar.getBoundingClientRect().height : 0 );
				out.pinned = Math.round( pin );
				return out;
			} );
			const tag = `${ w }x${ h } ${ name }`;
			if ( r.over > 0 ) { add( 'overflow', tag + ' +' + r.over ); }
			if ( r.clip.length ) { add( 'clipped', tag + ' ' + r.clip.slice( 0, 2 ) ); }
			if ( r.small.length ) { add( 'small text', tag + ' ' + r.small.slice( 0, 2 ) ); }
			if ( r.tap.length ) { add( 'tap target', tag + ' ' + r.tap.slice( 0, 2 ) ); }
			if ( r.alt ) { add( 'no alt', tag ); }
			if ( h < 600 && r.pinned > h * 0.35 ) { add( 'pinned too tall', tag + ' ' + r.pinned + 'px of ' + h ); }
		}
		if ( errors.length ) { add( 'script errors', w + ': ' + errors[ 0 ] ); }
		await ctx.close();
	}
	// Interactive behaviour with touch emulation on phone, tablet and desktop profiles.
	const profiles = { iphone: devices[ 'iPhone 13' ], pixel: devices[ 'Pixel 7' ], ipad: devices[ 'iPad (gen 7)' ], 'ipad-land': devices[ 'iPad (gen 7) landscape' ], laptop: { viewport: { width: 1280, height: 720 } }, big: { viewport: { width: 2560, height: 1440 } } };
	for ( const [ pn, opt ] of Object.entries( profiles ) ) {
		const ctx = await browser.newContext( opt );
		const p = await ctx.newPage();
		const hit = async ( sel ) => { const l = p.locator( sel ).first(); return opt.hasTouch ? l.tap() : l.click(); };
		await p.goto( 'http://127.0.0.1:8765/build/qa/home.html' );
		await hit( '.cemeti-menu-toggle' ); await p.waitForTimeout( 450 );
		const m = await p.evaluate( () => { const el = document.getElementById( 'cemeti-menu' ); const links = [ ...el.querySelectorAll( 'a' ) ].filter( ( a ) => a.offsetParent ); return { open: el.classList.contains( 'is-open' ), covered: links.filter( ( a ) => { const b = a.getBoundingClientRect(); if ( b.bottom > innerHeight || b.top < 0 ) { return false; } const t = document.elementFromPoint( b.x + b.width / 2, b.y + b.height / 2 ); return ! ( t && ( t === a || a.contains( t ) ) ); } ).length, n: links.length }; } );
		if ( ! m.open || m.covered || m.n < 12 ) { add( 'menu', `${ pn }: open=${ m.open } covered=${ m.covered } links=${ m.n }` ); }
		await p.keyboard.press( 'Escape' ); await p.waitForTimeout( 400 );
		if ( ! await p.evaluate( () => document.getElementById( 'cemeti-menu' ).hidden ) ) { add( 'menu', pn + ': Escape does not close' ); }
		await hit( '.cemeti-search-toggle' ); await p.waitForTimeout( 400 );
		if ( ! await p.evaluate( () => { const i = document.querySelector( '#cemeti-search input' ); const b = i.getBoundingClientRect(); return i.offsetParent && b.top >= 0 && b.bottom <= innerHeight; } ) ) { add( 'search', pn + ': input not visible' ); }
		for ( const name of [ 'single-exhibition', 'single-residency-full' ] ) {
			await p.goto( 'http://127.0.0.1:8765/build/qa/' + name + '.html' );
			const hrefs = await p.$$eval( '.cemeti-toc__links a', ( as ) => as.map( ( a ) => a.getAttribute( 'href' ) ) );
			for ( const href of hrefs.slice( 0, -1 ) ) {
				await p.locator( `.cemeti-toc__links a[href="${ href }"]` ).first().scrollIntoViewIfNeeded();
				await hit( `.cemeti-toc__links a[href="${ href }"]` ); await p.waitForTimeout( 650 );
				const r = await p.evaluate( ( h ) => { const t = document.querySelector( h ).getBoundingClientRect().top; const hd = document.querySelector( '.cemeti-header' ).getBoundingClientRect().bottom; return { top: Math.round( t ), hd: Math.round( hd ), cur: ( document.querySelector( '.cemeti-toc__links a[aria-current]' ) || {} ).hash }; }, href );
				if ( r.top < r.hd - 2 || r.cur !== href ) { add( 'contents link', `${ pn } ${ name } ${ href }: lands at ${ r.top }px (header ${ r.hd}), marked ${ r.cur }` ); }
			}
		}
		await p.goto( 'http://127.0.0.1:8765/build/qa/page-support-us.html' );
		const sm = p.locator( 'summary' ).first();
		if ( await sm.count() ) { await ( opt.hasTouch ? sm.tap() : sm.click() ); if ( ! await p.evaluate( () => document.querySelector( 'details' ).open ) ) { add( 'accordion', pn + ': does not open' ); } }
		await ctx.close();
	}
	await browser.close(); server.close();
	const keys = Object.keys( issues );
	console.log( `${ pages.length } pages x ${ sizes.length } sizes + ${ Object.keys( profiles ).length } touch/desktop profiles` );
	if ( ! keys.length ) { console.log( 'NO ISSUES' ); } else { keys.forEach( ( k ) => { console.log( '== ' + k + ' (' + issues[ k ].length + ')' ); issues[ k ].slice( 0, 12 ).forEach( ( m ) => console.log( '   ' + m ) ); } ); }
	process.exit( keys.length ? 1 : 0 );
} )();
