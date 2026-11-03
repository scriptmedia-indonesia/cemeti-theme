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
		ok( fontsLoaded.some( ( f ) => /Vampiro/.test( f ) ) && fontsLoaded.some( ( f ) => /Akzidenz/.test( f ) ), 'fonts: Vampiro One and Akzidenz-Grotesk Next files actually load (' + fontsLoaded.join( ',' ) + ')' );
		await ctx.close();
	}

	/* ---- 7b. Detail pages follow the design: poster indented in a wide reading column, text left-aligned; heading and Back stack on phones. */
	{
		const { page, ctx } = await open( browser, 'single-exhibition', 1280, 800 );
		ok( 'left' === await page.locator( '.cemeti-single__title' ).evaluate( ( e ) => getComputedStyle( e ).textAlign ), 'design: exhibition title is left-aligned' );
		const col = await page.locator( '.cemeti-single__head' ).boundingBox();
		const pb = await page.locator( '.cemeti-single__poster' ).boundingBox();
		const body = await page.locator( '.cemeti-single__body' ).boundingBox();
		const grid = await page.locator( '.cemeti-photos__grid' ).first().boundingBox();
		ok( ! pb || ( pb.x > col.x + 100 && pb.x + pb.width <= col.x + col.width ), 'design: the poster is indented inside the reading column' );
		ok( Math.abs( col.x - body.x ) < 2 && Math.abs( col.width - body.width ) < 2 && col.width > 900, `design: head and text share one wide reading column (${ Math.round( col.width ) }px)` );
		ok( ! grid || Math.abs( grid.x - col.x ) < 2, 'design: the photo grid lines up with the reading column' );
		ok( 'Akzidenz-Grotesk Next' === await page.evaluate( () => getComputedStyle( document.body ).fontFamily.split( ',' )[ 0 ].replace( /"/g, '' ) ), 'design: body text uses Akzidenz-Grotesk Next' );
		await ctx.close();
		const phone = await open( browser, 'archive-exhibition', 360, 740 );
		const hb = await phone.page.locator( '.cemeti-page-head .cemeti-heading' ).boundingBox();
		const bb = await phone.page.locator( '.cemeti-page-head .cemeti-back' ).boundingBox();
		ok( bb.y + bb.height <= hb.y + 2, `phone: the Back link sits above the heading, not squeezed beside it (${ Math.round( bb.y ) } vs ${ Math.round( hb.y ) })` );
		await phone.ctx.close();
	}

	/* ---- 7d. Detail page: contents bar is an anchor menu; layout follows the 12-column design. */
	{
		const { page, ctx } = await open( browser, 'single-exhibition', 1440, 900, { reducedMotion: 'reduce' } );
		const links = await page.locator( '.cemeti-toc__links a' ).evaluateAll( ( as ) => as.map( ( a ) => a.getAttribute( 'href' ) ) );
		ok( links.length >= 4, 'contents: links for the sections that exist (' + links.join( ' ' ) + ')' );
		const label = await page.locator( '.cemeti-toc__label' ).boundingBox();
		const first = await page.locator( '.cemeti-toc__links a' ).first().boundingBox();
		ok( first.y > label.y + label.height - 2, 'contents: "Daftar Isi" sits on its own line above the links' );
		const seps = await page.locator( '.cemeti-toc__links a' ).evaluateAll( ( as ) => as.slice( 1 ).every( ( a ) => parseFloat( getComputedStyle( a ).borderLeftWidth ) === 1 ) );
		ok( seps, 'contents: links are separated by vertical bars' );
		const col = await page.locator( '.cemeti-single__head' ).boundingBox();
		const cont = await page.locator( '.cemeti-single .cemeti-container' ).boundingBox();
		ok( Math.abs( col.width / cont.width - 10 / 12 ) < 0.01, 'layout: reading column is 10 of 12 columns (' + ( col.width / cont.width ).toFixed(3) + ')' );
		ok( Math.abs( ( col.x - cont.x ) / cont.width - 1 / 12 ) < 0.01, 'layout: reading column starts at column 2' );
		const poster = await page.locator( '.cemeti-single__poster' ).boundingBox();
		ok( ! poster || ( Math.abs( ( poster.x - cont.x ) / cont.width - 0.25 ) < 0.01 && poster.width > 480 && poster.width < 560 ), 'layout: poster starts at column 4 and is about 520px wide (' + ( poster ? Math.round( poster.width ) : 'none' ) + ')' );
		// Values read from the Figma frame "EXHIBITION ARTICLE TEMPLATE" (1440 px): see docs/DESIGN-NOTES.md.
		const px = ( selector, prop ) => page.evaluate( ( [ sel, p ] ) => parseFloat( getComputedStyle( document.querySelector( sel ) )[ p ] ), [ selector, prop ] );
		ok( 64 === await px( '.cemeti-single__title', 'fontSize' ), 'figma: title 64px' );
		ok( 40 === await px( '.cemeti-single__date', 'fontSize' ) && 40 === await px( '.cemeti-single__people', 'fontSize' ), 'figma: date and names 40px' );
		ok( 24 === await px( '.cemeti-single__body p', 'fontSize' ) && Math.abs( 34 - await px( '.cemeti-single__body p', 'lineHeight' ) ) < 0.6, 'figma: text 24px on 34px lines' );
		ok( 64 === await px( '.cemeti-section-heading', 'fontSize' ), 'figma: script headings 64px' );
		ok( 18 === await px( '.cemeti-toc__links a', 'fontSize' ) && 18 === await px( '.cemeti-toc__label', 'fontSize' ), 'figma: contents bar 18px' );
		ok( Math.abs( poster.width - 524 ) < 2 && Math.abs( poster.x - 404 ) < 3, `figma: poster 524 wide at x=404 (${ Math.round( poster.width ) } at ${ Math.round( poster.x ) })` );
		const g = await page.locator( '.cemeti-photos__grid img' ).first().boundingBox();
		ok( Math.abs( g.width - 524 ) < 2 && Math.abs( g.height / g.width - 351 / 525 ) < 0.01 && Math.abs( g.x - 188 ) < 2, `figma: gallery photos 524 wide, 3:2, from x=188 (${ Math.round( g.width ) }x${ Math.round( g.height ) } at ${ Math.round( g.x ) })` );
		const bp = await page.locator( '.cemeti-bio__photo' ).first().boundingBox();
		ok( ! bp || ( Math.abs( bp.width - 308 ) < 3 && Math.abs( bp.height - 308 ) < 3 ), 'figma: biography photo 308px square' );
		ok( 'rgb(241, 97, 84)' === await page.evaluate( () => getComputedStyle( document.querySelector( '.cemeti-toc__links a' ) ).color ), 'figma: links in Jeruk Bali #f16154' );
		for ( const href of links ) {
			await page.locator( '.cemeti-toc__links a[href="' + href + '"]' ).click();
			await page.waitForTimeout( 150 );
			const top = await page.evaluate( ( id ) => document.getElementById( id ).getBoundingClientRect().top, href.slice( 1 ) );
			ok( top >= -2 && top < 420, `contents: ${ href } scrolls its section into view (top ${ Math.round( top ) }px)` );
			ok( 'true' === await page.locator( '.cemeti-toc__links a[href="' + href + '"]' ).getAttribute( 'aria-current' ), `contents: ${ href } is marked as current` );
		}
		await page.locator( '.cemeti-toc-back a' ).first().click();
		await page.waitForTimeout( 150 );
		ok( await page.evaluate( () => document.getElementById( 'toc' ).getBoundingClientRect().top > -2 ), 'contents: "back to contents" returns to the bar' );
		await ctx.close();
		const phone = await open( browser, 'single-exhibition', 360, 740 );
		const poster2 = await phone.page.locator( '.cemeti-single__poster' ).boundingBox();
		const head2 = await phone.page.locator( '.cemeti-single__head' ).boundingBox();
		ok( ! poster2 || Math.abs( ( poster2.x + poster2.width / 2 ) - ( head2.x + head2.width / 2 ) ) < 3, 'phone: poster is centred in the column' );
		await phone.ctx.close();
	}

	/* ---- 7e. Editing screen: dynamic artists, photos, biographies, booklet (fixture from tests/render-pages.php). */
	{
		const { page, ctx } = await open( browser, 'admin-exhibition', 1200, 900 );
		const formData = () => page.evaluate( () => [ ...new FormData( document.getElementById( 'post' ) ).entries() ] );
		const values = async ( prefix ) => ( await formData() ).filter( ( [ k ] ) => k.startsWith( prefix ) ).map( ( [ , v ] ) => v );
		page.on( 'dialog', ( d ) => d.accept() );

		// Names: add with the button and with Enter, reorder, remove.
		const startNames = ( await values( 'cemeti_fields[artists]' ) ).length;
		ok( startNames === 2, 'admin: stored artists appear as one row each (' + startNames + ')' );
		await page.locator( '.cemeti-list__add' ).click();
		await page.keyboard.type( 'Third Artist' );
		await page.keyboard.press( 'Enter' );
		await page.keyboard.type( 'Fourth Artist' );
		ok( ( await values( 'cemeti_fields[artists]' ) ).join( '|' ).endsWith( 'Third Artist|Fourth Artist' ), 'admin: "Add" and Enter add name rows without saving the post' );
		await page.locator( '.cemeti-list__row' ).nth( 3 ).locator( '.cemeti-row__up' ).click();
		ok( ( await values( 'cemeti_fields[artists]' ) ).slice( 2 ).join( '|' ) === 'Fourth Artist|Third Artist', 'admin: a name moves up and the submitted order follows' );
		await page.locator( '.cemeti-list__row' ).nth( 2 ).locator( '.cemeti-row__remove' ).click();
		ok( ( await values( 'cemeti_fields[artists]' ) ).length === 3, 'admin: a name row can be removed' );

		// Biographies: "Add from Artists" adds only missing names; blocks reorder; titles follow.
		const before = await page.locator( '.cemeti-repeater__row' ).count();
		await page.locator( '.cemeti-repeater__from' ).click();
		const afterFrom = await page.locator( '.cemeti-repeater__row' ).count();
		await page.locator( '.cemeti-repeater__from' ).click();
		ok( afterFrom > before && afterFrom === await page.locator( '.cemeti-repeater__row' ).count(), `admin: "Add from Artists" adds blocks for new names once (${ before } -> ${ afterFrom })` );
		const names = await page.locator( '.cemeti-repeater__field[data-field="name"] input' ).evaluateAll( ( is ) => is.map( ( i ) => i.value ) );
		ok( new Set( names.map( ( n ) => n.toLowerCase() ) ).size === names.length, 'admin: no biography block is duplicated (' + names.join( ', ' ) + ')' );
		await page.locator( '.cemeti-repeater__row' ).last().locator( '.cemeti-row__up' ).first().click();
		const titles = await page.locator( '.cemeti-repeater__title' ).allTextContents();
		ok( titles.every( ( t, i ) => t.startsWith( 'Artist ' + ( i + 1 ) ) ), 'admin: block titles are renumbered after moving (' + titles.join( ' / ' ) + ')' );
		ok( ( await values( 'cemeti_fields[biographies]' ) ).length > 0 && ( await formData() ).filter( ( [ k ] ) => /biographies\]\[\d+\]\[name\]/.test( k ) ).map( ( [ , v ] ) => v ).join() === names.slice( 0, -2 ).concat( [ names[ names.length - 1 ], names[ names.length - 2 ] ] ).join(), 'admin: the form submits blocks in the order shown' );
		const ids = await page.locator( '.cemeti-repeater__field input[id]' ).evaluateAll( ( is ) => is.map( ( i ) => i.id ) );
		ok( new Set( ids ).size === ids.length && ! ids.some( ( i ) => i.includes( '__' ) ), 'admin: every field id is unique and no template placeholder leaks' );
		const unlabeled = await page.locator( '.cemeti-repeater__field input[type=text], .cemeti-repeater__field textarea' ).evaluateAll( ( is ) => is.filter( ( i ) => ! document.querySelector( 'label[for="' + i.id + '"]' ) ).length );
		ok( unlabeled === 0, 'admin: every biography field has a label' );
		const added = await page.locator( '.cemeti-repeater__add' ).click().then( () => page.locator( '.cemeti-repeater__row' ).count() );
		ok( added === afterFrom + 1, 'admin: "Add Artist" adds one empty block' );
		await page.locator( '.cemeti-repeater__row' ).last().locator( '.cemeti-row__remove' ).first().click();
		ok( await page.locator( '.cemeti-repeater__row' ).count() === afterFrom, 'admin: an empty block is removed without asking' );

		// Photos: add keeps the existing ones, order can change, single remove, clear.
		const gal = page.locator( '.cemeti-media[data-multiple="1"]' ).first();
		const hidden = () => gal.locator( 'input[type=hidden]' ).inputValue();
		const had = ( await hidden() ).split( ',' ).filter( Boolean );
		await page.evaluate( () => { window.__picks.push( [ { id: 9001, url: 'x.jpg' }, { id: 9002, url: 'y.jpg' } ] ); } );
		await gal.locator( '.cemeti-media__pick' ).click();
		const added2 = ( await hidden() ).split( ',' );
		ok( added2.length === had.length + 2 && had.every( ( id, i ) => added2[ i ] === id ), 'admin: choosing photos adds to the existing ones, in order' );
		await page.evaluate( () => { window.__picks.push( [ { id: 9001, url: 'x.jpg' } ] ); } );
		await gal.locator( '.cemeti-media__pick' ).click();
		ok( ( await hidden() ).split( ',' ).length === added2.length, 'admin: a photo already in the gallery is not added twice' );
		await gal.locator( '.cemeti-media__item' ).last().locator( '.cemeti-row__up' ).click();
		const reordered = ( await hidden() ).split( ',' );
		ok( reordered[ reordered.length - 2 ] === '9002' && reordered[ reordered.length - 1 ] === '9001', 'admin: a photo moves and the saved order follows (' + reordered.join( ',' ) + ')' );
		await gal.locator( '.cemeti-media__item' ).last().locator( '.cemeti-row__remove' ).click();
		ok( ! ( await hidden() ).includes( '9001' ), 'admin: one photo can be removed' );
		await gal.locator( '.cemeti-media__clear' ).click();
		ok( ( await hidden() ) === '' && await gal.locator( '.cemeti-media__item' ).count() === 0, 'admin: "Remove all" empties the gallery' );

		// Booklet PDF.
		const file = page.locator( '.cemeti-file' );
		ok( ( await file.locator( '.cemeti-file__name' ).textContent() ).endsWith( '.pdf' ), 'admin: the chosen booklet shows its file name' );
		await page.evaluate( () => { window.__picks.push( [ { id: 902, filename: 'new-booklet.pdf' } ] ); } );
		await file.locator( '.cemeti-file__pick' ).click();
		ok( await file.locator( 'input[type=hidden]' ).inputValue() === '902' && ( await file.locator( '.cemeti-file__name' ).textContent() ) === 'new-booklet.pdf', 'admin: choosing a PDF stores its id and shows the name' );
		await file.locator( '.cemeti-file__clear' ).click();
		ok( await file.locator( 'input[type=hidden]' ).inputValue() === '', 'admin: the booklet can be removed' );
		ok( ( await page.locator( '.cemeti-fields__section h3' ).allTextContents() ).join( '|' ).includes( 'Biographies' ), 'admin: fields are grouped under section headings' );
		await ctx.close();
	}

	/* ---- 7f. Figma values for the shared chrome and the archive cards (1440 px frames). */
	{
		const { page, ctx } = await open( browser, 'archive-exhibition', 1440, 900, { reducedMotion: 'reduce' } );
		const px = ( selector, prop, index = 0 ) => page.evaluate( ( [ sel, p, i ] ) => parseFloat( getComputedStyle( document.querySelectorAll( sel )[ i ] )[ p ] ), [ selector, prop, index ] );
		const box = ( selector, index = 0 ) => page.locator( selector ).nth( index ).boundingBox();
		const header = await box( '.cemeti-header' );
		ok( Math.abs( header.height - 140 ) < 2, `figma: header is 140px high (${ Math.round( header.height ) })` );
		const logo = await box( '.cemeti-logo img' );
		ok( Math.abs( logo.width - 200 ) < 2 && Math.abs( logo.y - 36 ) < 2 && Math.abs( ( logo.x + logo.width / 2 ) - 720 ) < 2, `figma: logo 200 wide, 36px from the top, centred (${ Math.round( logo.width ) } at y ${ Math.round( logo.y ) })` );
		const menu = await box( '.cemeti-menu-toggle' );
		ok( Math.abs( menu.x - 80 ) < 10 && 24 === await px( '.cemeti-menu-toggle', 'fontSize' ) && 500 === await px( '.cemeti-menu-toggle', 'fontWeight' ), 'figma: Menu 24px Medium at x=80' );
		ok( 24 === await px( '.cemeti-lang a', 'fontSize' ), 'figma: language switch 24px' );
		ok( await page.evaluate( () => getComputedStyle( document.querySelector( '.cemeti-header' ) ).boxShadow.includes( '4px 2px' ) ), 'figma: header shadow 0 4 2' );
		const h = await box( '.cemeti-page-head .cemeti-heading' );
		ok( 64 === await px( '.cemeti-page-head .cemeti-heading', 'fontSize' ) && Math.abs( ( h.x + h.width / 2 ) - 720 ) < 2, 'figma: page heading 64px script, centred' );
		const c0 = await box( '.cemeti-card', 0 );
		const c1 = await box( '.cemeti-card', 1 );
		ok( Math.abs( c0.width - 308 ) < 2 && Math.abs( c0.height - 386 ) < 2 && Math.abs( c0.x - 80 ) < 2, `figma: card 308 x 386 at x=80 (${ Math.round( c0.width ) } x ${ Math.round( c0.height ) })` );
		ok( Math.abs( ( c1.x - c0.x - c0.width ) - 16 ) < 2, 'figma: 16px between cards' );
		ok( 18 === await px( '.cemeti-card__title', 'fontSize' ) && 16 === await px( '.cemeti-card__date', 'fontSize' ) && 500 === await px( '.cemeti-card__title', 'fontWeight' ), 'figma: card title 18 / date 16, Medium' );
		const body = await box( '.cemeti-card__body', 0 );
		ok( Math.abs( ( body.y + body.height ) - ( c0.y + c0.height ) ) < 2 && body.height < 130, 'figma: title block sits on the bottom of the poster' );
		ok( 'rgb(242, 232, 219)' === await page.evaluate( () => getComputedStyle( document.querySelector( '.cemeti-card__body' ) ).backgroundColor ), 'figma: title block colour #f2e8db' );
		const f = await box( '.cemeti-footer' );
		ok( Math.abs( f.height - 254 ) < 4, `figma: footer 254px high (${ Math.round( f.height ) })` );
		ok( 22 === await px( '.cemeti-footer__name', 'fontSize' ) && 22 === await px( '.cemeti-footer__social', 'fontSize' ) && 12 === await px( '.cemeti-footer__address', 'fontSize' ), 'figma: footer 22 / 22 / 12px' );
		const info = await box( '.cemeti-footer__info' );
		ok( Math.abs( info.x - 501 ) < 3 && Math.abs( info.width - 211 ) < 3, `figma: footer text block 211px wide at x=501 (${ Math.round( info.x ) }, ${ Math.round( info.width ) })` );
		await ctx.close();
		const home = await open( browser, 'home', 1440, 900, { reducedMotion: 'reduce' } );
		const hp = home.page;
		const hpx = ( selector, prop ) => hp.evaluate( ( [ sel, p ] ) => parseFloat( getComputedStyle( document.querySelector( sel ) )[ p ] ), [ selector, prop ] );
		const poster = await hp.locator( '.cemeti-home__section--exhibition .cemeti-home__poster img' ).boundingBox();
		const text = await hp.locator( '.cemeti-home__section--exhibition .cemeti-home__text' ).boundingBox();
		ok( Math.abs( poster.width - 272 ) < 2 && Math.abs( poster.height - 340 ) < 2 && Math.abs( poster.x - 80 ) < 2, `figma home: poster 272 x 340 at x=80 (${ Math.round( poster.width ) } x ${ Math.round( poster.height ) })` );
		ok( Math.abs( text.x - 404 ) < 3, `figma home: text starts at x=404 (${ Math.round( text.x ) })` );
		ok( 48 === await hpx( '.cemeti-home__title', 'fontSize' ) && 48 === await hpx( '.cemeti-home__people', 'fontSize' ) && 40 === await hpx( '.cemeti-home__date', 'fontSize' ), 'figma home: title and names 48, date 40' );
		const btn = await hp.locator( '.cemeti-home__section--exhibition .cemeti-btn' ).boundingBox();
		ok( Math.abs( btn.width - 272 ) < 2 && Math.abs( btn.height - 31 ) < 3, `figma home: green button 272 x 31 (${ Math.round( btn.width ) } x ${ Math.round( btn.height ) })` );
		ok( 16 === await hpx( '.cemeti-home__section--exhibition .cemeti-btn', 'fontSize' ), 'figma home: button text 16px script' );
		ok( 24 === await hpx( '.cemeti-row__date', 'fontSize' ) && 40 === await hpx( '.cemeti-row__title', 'fontSize' ) && 24 === await hpx( '.cemeti-row__cat', 'fontSize' ), 'figma home: journal date 24, title 40, category 24' );
		const res = await hp.locator( '.cemeti-home__section--residency .cemeti-home__poster img' ).boundingBox();
		ok( Math.abs( res.width - 416 ) < 2 && Math.abs( res.height - 333 ) < 3, `figma home: residency picture 416 x 333 (${ Math.round( res.width ) } x ${ Math.round( res.height ) })` );
		await home.ctx.close();
	}

	/* ---- 7g. Fixed pages: contact block follows the reading column; Support Us text is 848 wide; rows are 48px caps. */
	{
		const c = await open( browser, 'page-contact-us', 1440, 900, { reducedMotion: 'reduce' } );
		const a = await c.page.locator( '.cemeti-contact' ).boundingBox();
		const b = await c.page.locator( '.cemeti-single__body' ).boundingBox();
		ok( Math.abs( a.x - b.x ) < 2, 'contact: the address block lines up with the page text (' + Math.round( a.x ) + ' vs ' + Math.round( b.x ) + ')' );
		await c.ctx.close();
		const sp = await open( browser, 'page-support-us', 1440, 900, { reducedMotion: 'reduce' } );
		const sb = await sp.page.locator( '.cemeti-single__body' ).boundingBox();
		ok( Math.abs( sb.width - 848 ) < 3 && Math.abs( sb.x - 296 ) < 3, `figma: Support Us text 848 wide at x=296 (${ Math.round( sb.width ) } at ${ Math.round( sb.x ) })` );
		ok( 48 === await sp.page.evaluate( () => parseFloat( getComputedStyle( document.querySelector( '.cemeti-accordion__row summary' ) ).fontSize ) ), 'figma: Support Us rows 48px' );
		const rows = await sp.page.locator( '.cemeti-accordion__row' ).evaluateAll( ( r ) => r.map( ( x ) => x.getBoundingClientRect().height ) );
		ok( rows.length === 4 && rows.every( ( h ) => Math.abs( h - 82 ) < 6 ), 'figma: Support Us rows about 82px apart (' + rows.map( Math.round ).join( ',' ) + ')' );
		await sp.ctx.close();
		const ar = await open( browser, 'page-open-archive', 1440, 900, { reducedMotion: 'reduce' } );
		const l = await ar.page.locator( '.cemeti-az__col' ).nth( 1 ).boundingBox();
		ok( Math.abs( l.x - 729 ) < 3, `figma: Open Archive right column starts at x=729 (${ Math.round( l.x ) })` );
		await ar.ctx.close();
	}

	/* ---- 7h. QA round 1: sticky contents bar, back to contents, sticky titles, hover, scroll loading, share, About. */
	{
		const { page, ctx } = await open( browser, 'single-exhibition', 1440, 900, { reducedMotion: 'reduce' } );
		const headerH = ( await page.locator( '.cemeti-header' ).boundingBox() ).height;
		await page.evaluate( () => window.scrollTo( 0, 1500 ) );
		await page.waitForTimeout( 150 );
		const bar = await page.locator( '.cemeti-single__nav' ).boundingBox();
		ok( Math.abs( bar.y - headerH ) < 3, `qa: the contents bar stays at the top under the header (${ Math.round( bar.y ) } vs ${ Math.round( headerH ) })` );
		const before = page.url();
		await page.locator( '.cemeti-toc-back a' ).nth( 1 ).click();
		await page.waitForTimeout( 250 );
		ok( page.url().split( '#' )[ 0 ] === before.split( '#' )[ 0 ], 'qa: "back to contents" stays on the same page' );
		ok( await page.evaluate( () => document.documentElement.scrollTop ) < 400, 'qa: "back to contents" returns to the top of the page' );
		ok( 18 === await page.evaluate( () => parseFloat( getComputedStyle( document.querySelector( '.cemeti-toc-back a' ) ).fontSize ) ), 'qa: "back to contents" is 18px as in the design' );
		const icons = await page.locator( '.cemeti-share__btn svg' ).count();
		ok( icons >= 2, `qa: share buttons show an icon and the app name (${ icons } icons)` );
		ok( ( await page.locator( '.cemeti-share__btn' ).first().textContent() ).includes( 'WhatsApp' ), 'qa: the app name is written next to the icon' );
		await ctx.close();

		const home = await open( browser, 'home', 1440, 900, { reducedMotion: 'no-preference' } );
		const link = home.page.locator( '.cemeti-home .cemeti-heading a' ).first();
		await link.hover();
		await home.page.waitForTimeout( 100 );
		ok( 'rgb(28, 117, 188)' === await link.evaluate( ( e ) => getComputedStyle( e ).color ), 'qa: a section title turns #1C75BC on hover' );
		await home.page.mouse.move( 5, 400 );
		await home.page.evaluate( () => window.scrollTo( 0, 300 ) );
		await home.page.waitForTimeout( 200 );
		const stuck = await home.page.locator( '.cemeti-home .cemeti-heading' ).first().boundingBox();
		ok( stuck.y >= 100 && stuck.y < 230, `qa: the section title stays under the header while its section scrolls (y=${ Math.round( stuck.y ) })` );
		const top = await home.page.evaluate( () => { const h = document.querySelector( '.cemeti-home .cemeti-heading a' ); const r = h.getBoundingClientRect(); const el = document.elementFromPoint( r.x + r.width / 2, r.y + r.height / 2 ); return el === h || h.contains( el ); } );
		ok( top, 'qa: the pinned section title stays on top of the content that scrolls under it' );
		const txt = home.page.locator( '.cemeti-home__section--exhibition .cemeti-home__text' ).first();
		await home.page.evaluate( () => window.scrollTo( 0, 0 ) );
		await txt.hover();
		await home.page.waitForTimeout( 100 );
		ok( 'rgb(241, 97, 84)' === await home.page.locator( '.cemeti-home__date' ).first().evaluate( ( e ) => getComputedStyle( e ).color ), 'qa: hovering a Home entry turns its title, names and date salmon' );
		await home.ctx.close();

		const inf = await open( browser, 'infinite-1', 1440, 600, { reducedMotion: 'reduce' } );
		await inf.page.waitForFunction( () => document.querySelectorAll( '.cemeti-card' ).length > 4, null, { timeout: 4000 } ).catch( () => {} );
		await inf.page.waitForTimeout( 300 );
		const start = 4;
		const after = await inf.page.locator( '.cemeti-card' ).count();
		ok( after === start + 2, `qa: scrolling to the end loads the next page (${ start } -> ${ after } cards)` );
		ok( ( await inf.page.locator( '.cemeti-card__title' ).allTextContents() ).some( ( t ) => /Second page card B/.test( t ) ), 'qa: the cards of the next page are the real ones' );
		ok( await inf.page.locator( '.pagination[hidden]' ).count() === 1, 'qa: the Older / Newer links are hidden while scroll loading works' );
		await inf.ctx.close();

		const oc = await open( browser, 'archive-open-call', 1440, 900, { reducedMotion: 'reduce' } );
		const op = await oc.page.locator( '.cemeti-opencall__poster' ).count();
		if ( op ) {
			const pb = await oc.page.locator( '.cemeti-opencall__poster' ).first().boundingBox();
			ok( Math.abs( pb.width - 416 ) < 3, `qa: Open Call list uses the design layout (picture ${ Math.round( pb.width ) }px)` );
		}
		const btn = await oc.page.locator( '.cemeti-opencall .cemeti-btn--blue' ).first().boundingBox();
		ok( ! btn || Math.abs( btn.width - 272 ) < 3, 'qa: Application Submission button is 272px wide' );
		await oc.ctx.close();

		const ab = await open( browser, 'page-about', 1440, 900, { reducedMotion: 'reduce' } );
		ok( 'rgb(246, 209, 65)' === await ab.page.locator( '.cemeti-page-head .cemeti-heading' ).evaluate( ( e ) => getComputedStyle( e ).color ), 'qa: About title is yellow #F6D141' );
		const grid = ab.page.locator( '.cemeti-people--advisory-board .cemeti-people__grid' );
		if ( await grid.count() ) {
			const cols = await grid.evaluate( ( e ) => getComputedStyle( e ).gridTemplateColumns.split( ' ' ).length );
			ok( 3 === cols, `qa: Advisory Board is a 3-column grid (${ cols })` );
		}
		const team = ab.page.locator( '.cemeti-team' );
		if ( await team.count() ) {
			ok( 'center' === await team.evaluate( ( e ) => getComputedStyle( e ).textAlign ) && '1px' === await team.evaluate( ( e ) => getComputedStyle( e ).borderTopWidth ) && '1px' === await team.evaluate( ( e ) => getComputedStyle( e ).borderBottomWidth ), 'qa: Team is centred between two rules (roles in bold, names under them)' );
			ok( 500 === await ab.page.locator( '.cemeti-team__role strong' ).first().evaluate( ( e ) => parseFloat( getComputedStyle( e ).fontWeight ) ), 'qa: Team roles are Medium' );
		}
		const li = ab.page.locator( '.cemeti-people--advisory-board .cemeti-people__grid li' ).first();
		if ( await li.count() ) {
			const lb = await li.boundingBox();
			const ib = await li.locator( 'img' ).boundingBox();
			ok( ib.width / lb.width > 0.4, `qa: Advisory Board photo fills about half of its cell (${ Math.round( ib.width / lb.width * 100 ) }%)` );
		}
		await ab.ctx.close();
	}

	/* ---- 7i. Residency detail uses the same template and the same Figma values as Exhibition. */
	{
		const r = await open( browser, 'single-residency-full', 1440, 900, { reducedMotion: 'reduce' } );
		const rpx = ( sel, prop ) => r.page.evaluate( ( [ q, p ] ) => parseFloat( getComputedStyle( document.querySelector( q ) )[ p ] ), [ sel, prop ] );
		ok( 64 === await rpx( '.cemeti-single__title', 'fontSize' ) && 40 === await rpx( '.cemeti-single__date', 'fontSize' ) && 24 === await rpx( '.cemeti-single__body', 'fontSize' ), 'figma residency: title 64, dates 40, text 24' );
		const poster = await r.page.locator( '.cemeti-single__poster' ).boundingBox();
		ok( Math.abs( poster.width - 524 ) < 2, 'figma residency: poster 524 wide' );
		const links = await r.page.locator( '.cemeti-toc__links a' ).allTextContents();
		ok( links.length === 5, 'residency: contents bar lists Foreword, Gallery, Documentation, Biography, Booklet (' + links.join( ', ' ) + ')' );
		const photo = await r.page.locator( '.cemeti-photos__grid img' ).first().boundingBox();
		ok( Math.abs( photo.width - 524 ) < 2, 'figma residency: gallery photos 524 wide' );
		await r.ctx.close();
	}

	/* ---- 7j. Public Program detail: speaker 40, day and time 24, title 64, no contents bar. */
	{
		const e = await open( browser, 'single-public-program', 1440, 900, { reducedMotion: 'reduce' } );
		const epx = ( sel, prop ) => e.page.evaluate( ( [ q, p ] ) => parseFloat( getComputedStyle( document.querySelector( q ) )[ p ] ), [ sel, prop ] );
		ok( 64 === await epx( '.cemeti-single__title', 'fontSize' ) && 40 === await epx( '.cemeti-single__people', 'fontSize' ) && 24 === await epx( '.cemeti-single__when', 'fontSize' ) && 500 === await epx( '.cemeti-single__when', 'fontWeight' ), 'figma public program: title 64, speaker 40, day and time 24 Medium' );
		ok( 0 === await e.page.locator( '.cemeti-toc' ).count() && await e.page.locator( '.cemeti-toc-back a' ).count() >= 1, 'figma public program: no contents bar, back links remain' );
		await e.ctx.close();
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
	const known = [ 'rgb(241, 97, 84) on rgb(242, 232, 219)', 'rgb(246, 209, 65) on rgb(255, 248, 239)', 'rgb(138, 138, 141) on rgb(255, 248, 239)', 'rgb(28, 117, 188) on rgb(246, 209, 65)', 'rgb(241, 97, 84) on rgb(255, 248, 239)', 'rgb(255, 255, 255) on rgb(0, 161, 75)', 'rgb(255, 248, 239) on rgb(0, 161, 75)', 'rgb(74, 74, 77) on rgb(241, 97, 84)', 'rgb(246, 209, 65) on rgb(28, 117, 188)', 'rgb(0, 0, 0) on rgb(0, 0, 0)' ];
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
