/**
 * Recette des 3 pages publiques : Accueil · Boutique · Contact.
 *
 * Contrôle les règles non négociables du template, dans un VRAI navigateur et en
 * VISITEUR NON CONNECTÉ — un admin connecté voit une barre d'admin et parfois des
 * menus différents, ce qui masquerait précisément ce qu'on cherche.
 *
 *   node tools/recette/public-3-pages.js
 *
 * Ce qu'on vérifie :
 *   · la nav montre EXACTEMENT 3 entrées, desktop ET mobile (le piège est mobile :
 *     sans menu assigné à `mobile_menu`, Astra retombe sur wp_page_menu() et
 *     réexpose Panier / Commande / Mon compte) ;
 *   · aucun lien panier / commande / compte, ni bouton « Ajouter au panier »,
 *     nulle part ;
 *   · les URLs du tunnel ne mènent à aucun panier, même tapées à la main ;
 *   · la page produit porte bien le formulaire COD ;
 *   · l'arabe bascule la page en RTL et traduit les libellés du menu ;
 *   · aucune erreur PHP servie.
 */
const { launch, scanPhp, ok, BASE } = require( './lib' );

// Un lien vers le tunnel = règle 4 violée. On cherche l'URL, pas le libellé : un
// clone peut avoir renommé « Cart » en « Panier » ou en arabe.
const TUNNEL_HREF = /\/(cart|panier|checkout|commande|my-account|mon-compte)(\/|$|\?)/i;

let fails = 0;
const check = ( cond, msg, extra ) => {
	if ( ! ok( cond, msg, extra ) ) { fails++; }
};

/**
 * Liens qui ajoutent RÉELLEMENT au panier.
 *
 * On cherche `?add-to-cart=`, pas la classe `.add_to_cart_button`. La classe est
 * cosmétique — un thème peut la poser sur un bouton parfaitement inoffensif, et un
 * autre remplir le panier sans jamais l'utiliser. Le lien, lui, ne ment pas.
 */
async function addToCartLinks( page ) {
	return page.$$eval( 'a[href*="add-to-cart="], button[name="add-to-cart"]', ( els ) =>
		els.map( ( e ) => e.getAttribute( 'href' ) || 'button[name=add-to-cart]' )
	);
}

/** Liens du tunnel présents dans la page. */
async function tunnelLinks( page ) {
	return page.$$eval( 'a[href]', ( as ) =>
		as.map( ( a ) => a.getAttribute( 'href' ) ).filter( Boolean )
	).then( ( hrefs ) => hrefs.filter( ( h ) => /\/(cart|panier|checkout|commande|my-account|mon-compte)(\/|$|\?)/i.test( h ) ) );
}

/**
 * Cibles distinctes des entrées de menu.
 *
 * On compte les HREF UNIQUES, pas les `.menu-item` : le même menu est assigné à
 * plusieurs emplacements (desktop, off-canvas, pied de page), donc les entrées
 * apparaissent plusieurs fois dans le DOM. C'est normal. Ce qui ne le serait pas,
 * c'est une 4e destination.
 *
 * `.menu-item` est du HTML de WordPress lui-même (wp_nav_menu), pas d'Astra : ce
 * test tient sur n'importe quel thème.
 */
async function menuTargets( page ) {
	const hrefs = await page.$$eval( '.menu-item > a[href]', ( as ) =>
		as.map( ( a ) => a.href )
	);
	return [ ...new Set( hrefs ) ];
}

( async () => {
	// state:false → visiteur anonyme.
	const { browser, page, console_ } = await launch( { state: false } );

	const pages = [
		[ 'Accueil', `${ BASE }/` ],
		[ 'Boutique', `${ BASE }/boutique/` ],
		[ 'Contact', `${ BASE }/contact/` ],
	];

	// ─── 1. Les 3 pages, en desktop ───────────────────────────────────────────
	console.log( '\n▶ Les 3 pages (desktop)' );
	for ( const [ name, url ] of pages ) {
		const res = await page.goto( url, { waitUntil: 'domcontentloaded' } );
		const html = await page.content();

		check( res && res.status() === 200, `${ name } — HTTP 200`, res ? `reçu ${ res.status() }` : 'pas de réponse' );

		const php = scanPhp( html );
		check( php.length === 0, `${ name } — aucune erreur PHP`, php.join( ' | ' ) );

		const targets = await menuTargets( page );
		check( targets.length === 3, `${ name } — nav = 3 destinations`, `${ targets.length } trouvée(s) : ${ targets.join( ', ' ) }` );

		const tunnel = await tunnelLinks( page );
		check( tunnel.length === 0, `${ name } — aucun lien panier/compte`, tunnel.join( ', ' ) );

		const addToCart = await addToCartLinks( page );
		check( addToCart.length === 0, `${ name } — aucun lien qui remplit le panier`, addToCart.join( ', ' ) );
	}

	// ─── 2. Le contenu propre à chaque page ───────────────────────────────────
	console.log( '\n▶ Contenu' );
	await page.goto( `${ BASE }/`, { waitUntil: 'domcontentloaded' } );
	check( !! ( await page.$( '.aod-home__hero' ) ), 'Accueil — le héro est rendu' );
	check( ( await page.$$( '.aod-home__promise' ) ).length === 3, 'Accueil — les 3 promesses de réassurance' );

	await page.goto( `${ BASE }/contact/`, { waitUntil: 'domcontentloaded' } );
	check( !! ( await page.$( '.aod-contact__form' ) ), 'Contact — le formulaire est rendu' );
	check( !! ( await page.$( '#aod-ct-message' ) ), 'Contact — le champ message est là' );
	check( !! ( await page.$( '.aod-cod__hp input' ) ), 'Contact — le honeypot est présent' );

	// ─── 3. La page produit ───────────────────────────────────────────────────
	console.log( '\n▶ Page produit' );
	await page.goto( `${ BASE }/boutique/`, { waitUntil: 'domcontentloaded' } );
	const first = await page.$eval( 'ul.products li.product a.woocommerce-LoopProduct-link, ul.products li.product a[href*="/produit/"], ul.products li.product a[href*="/product/"]',
		( a ) => a.href ).catch( () => null );

	if ( ! first ) {
		check( false, 'Boutique — au moins un produit affiché', 'aucun produit : la boutique est-elle peuplée ? (scripts/dev-seed.php)' );
	} else {
		await page.goto( first, { waitUntil: 'domcontentloaded' } );
		check( !! ( await page.$( 'form.aod-cod, .aod-cod' ) ), 'Produit — le formulaire COD est rendu' );
		const atc = await addToCartLinks( page );
		check( atc.length === 0, 'Produit — aucun lien qui remplit le panier', atc.join( ', ' ) );
		const tunnel = await tunnelLinks( page );
		check( tunnel.length === 0, 'Produit — aucun lien panier/compte', tunnel.join( ', ' ) );
	}

	// ─── 4. Les URLs du tunnel, tapées à la main ──────────────────────────────
	//
	// Ces pages DOIVENT échouer (404 ou redirection). Le navigateur journalise donc
	// une erreur console — attendue, et pas un défaut. On borne la section pour ne
	// pas la compter dans le bilan console final.
	const tunnelFrom = console_.length;
	console.log( '\n▶ URLs du tunnel (saisies directement)' );
	for ( const path of [ '/cart/', '/checkout/', '/my-account/' ] ) {
		await page.goto( `${ BASE }${ path }`, { waitUntil: 'domcontentloaded' } );
		const body = ( await page.textContent( 'body' ) ) || '';
		// Ce qui compte n'est pas le code HTTP mais qu'AUCUN panier ni formulaire de
		// connexion ne s'affiche. 404 ou redirection vers la Boutique : les deux vont.
		const leaked = await page.$$( 'form.woocommerce-cart-form, form.checkout, form.woocommerce-form-login' );
		check( leaked.length === 0, `${ path } — aucun panier ni connexion`, `${ leaked.length } formulaire(s) du tunnel` );
	}

	const tunnelTo = console_.length;

	// ─── 5. Mobile — le vrai piège ────────────────────────────────────────────
	console.log( '\n▶ Mobile (390 × 844) — menu off-canvas' );
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( `${ BASE }/`, { waitUntil: 'domcontentloaded' } );

	// Ouvre le menu burger (le sélecteur d'Astra, avec un repli générique).
	const burger = await page.$( '.menu-toggle, .ast-mobile-menu-trigger-fill, button[aria-label*="enu"]' );
	check( !! burger, 'Mobile — le bouton du menu existe' );
	if ( burger ) {
		await burger.click();
		await page.waitForTimeout( 600 );
	}

	const mTargets = await menuTargets( page );
	check( mTargets.length === 3, 'Mobile — nav = 3 destinations', `${ mTargets.length } : ${ mTargets.join( ', ' ) }` );

	const mTunnel = await tunnelLinks( page );
	check( mTunnel.length === 0, 'Mobile — aucun lien panier/compte', mTunnel.join( ', ' ) );

	// ─── 6. Arabe + RTL ───────────────────────────────────────────────────────
	console.log( '\n▶ Arabe (RTL)' );
	await page.setViewportSize( { width: 1440, height: 900 } );
	await page.context().addCookies( [
		{ name: 'aod_lang', value: 'ar', url: BASE },
	] );
	await page.goto( `${ BASE }/`, { waitUntil: 'domcontentloaded' } );

	const lang = await page.getAttribute( 'html', 'lang' );
	check( !! lang && lang.startsWith( 'ar' ), 'AR — <html lang="ar">', `lang="${ lang }"` );

	const dir = await page.evaluate( () => document.documentElement.getAttribute( 'dir' )
		|| getComputedStyle( document.body ).direction );
	check( dir === 'rtl', 'AR — la page est en RTL', `dir="${ dir }"` );

	// Les libellés du menu viennent de la BASE : s'ils ne sont pas retraduits par le
	// code (AOD_COD_Pages::translate_menu_labels), ils restent en français en arabe.
	// C'est exactement le bug que ce test existe pour attraper.
	const labels = await page.$$eval( '.menu-item > a', ( as ) => as.map( ( a ) => a.textContent.trim() ) );

	// 🚨 ANCRAGE DE L'ABSENCE (31.5). « Aucun libellé resté en français » est
	// TRIVIALEMENT VRAI si `labels` est vide — menu non rendu, thème changé,
	// sélecteur périmé. La porte annoncerait alors un bilinguisme parfait sur une
	// navigation inexistante, c'est-à-dire dans le cas le plus grave. Une absence
	// ne prouve quelque chose que si l'on a d'abord montré une présence.
	check( labels.length >= 3, 'AR — ANCRAGE : le menu est rendu',
		`${ labels.length } entrée(s) lue(s) via .menu-item > a — 3 attendues au minimum` );

	const stillFrench = labels.filter( ( l ) => /^(Accueil|Boutique|Contact)$/i.test( l ) );
	check( stillFrench.length === 0, 'AR — les libellés du menu sont traduits',
		stillFrench.length ? `restés en français : ${ [ ...new Set( stillFrench ) ].join( ', ' ) }` : '' );

	const arTunnel = await tunnelLinks( page );
	check( arTunnel.length === 0, 'AR — aucun lien panier/compte', arTunnel.join( ', ' ) );

	// ─── Bilan ────────────────────────────────────────────────────────────────
	const errs = console_
		.filter( ( _, i ) => i < tunnelFrom || i >= tunnelTo )   // hors 404 attendus du tunnel
		.filter( ( m ) => m.startsWith( '[pageerror]' ) || m.startsWith( '[error]' ) );
	console.log( '\n▶ Console navigateur' );
	check( errs.length === 0, 'Aucune erreur JS', errs.slice( 0, 3 ).join( ' | ' ) );

	console.log( fails === 0
		? '\n✅ Les 3 pages publiques respectent les règles.\n'
		: `\n❌ ${ fails } contrôle(s) en échec.\n` );

	await browser.close();
	process.exit( fails === 0 ? 0 : 1 );
} )();
