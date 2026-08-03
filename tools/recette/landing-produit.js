/**
 * Recette de la page de vente (landing) par produit — Phase 6.
 *
 * En VISITEUR NON CONNECTÉ, comme la recette des 3 pages : un admin verrait la barre
 * d'admin et — surtout — l'aperçu `?aod_landing_preview=1` fonctionnerait pour lui,
 * ce qui masquerait une landing accidentellement éteinte.
 *
 *   AOD_LANDING_PID=123 node tools/recette/landing-produit.js
 *
 * Le produit testé est fourni par `run.sh`, qui le découvre via WP-CLI
 * (`_aod_landing_enabled`). Pas de produit avec landing sur ce clone → le scénario
 * se retire (sortie 0) : la page de vente est optionnelle par produit, son absence
 * n'est pas un défaut.
 *
 * Ce qu'on vérifie — les INVARIANTS du template, pas le contenu du gérant :
 *   · la landing s'affiche, dans l'en-tête et le pied du THÈME (nav à 3 entrées
 *     conservée, switcher FR/AR conservé) ;
 *   · le formulaire COD est dedans, et l'ancre #aod-commander y mène ;
 *   · toujours AUCUN lien panier / compte / add-to-cart (règle 4 — la landing ne
 *     rouvre pas le tunnel) ;
 *   · l'arabe bascule la page en RTL, sans chaîne de chrome restée en français ;
 *   · mobile 390 px sans débordement horizontal (l'écran blanc RTL vient de là) ;
 *   · un produit SANS landing ne charge ni le CSS ni le JS de la landing ;
 *   · l'aperçu `?aod_landing_preview=1` ne montre RIEN à un visiteur ;
 *   · aucune erreur PHP servie, aucune erreur JS.
 */
const { launch, scanPhp, ok, BASE } = require( './lib' );

const PID = parseInt( process.env.AOD_LANDING_PID || '0', 10 );
const OTHER = parseInt( process.env.AOD_PLAIN_PID || '0', 10 );

let fails = 0;
const check = ( cond, msg, extra ) => {
	if ( ! ok( cond, msg, extra ) ) { fails++; }
};

( async () => {
	if ( ! PID ) {
		console.log( 'ℹ Aucun produit avec page de vente sur ce clone — scénario RETIRÉ (rien n’a été vérifié).' );
		// Code 3 = retiré, pas passé. Sortir à 0 rendait ce retrait indiscernable
		// d'un succès dans le bilan de run.sh : la recette annonçait « tout passe »
		// alors que la porte n'avait rien joué.
		process.exit( 3 );
	}

	// `state: false` : pas de cookie d'admin — on regarde ce que voit un vrai visiteur.
	const { browser, page, console_ } = await launch( { state: false } );
	const URL = `${ BASE }/?p=${ PID }`;

	console.log( `\n▶ La landing (produit ${ PID }) — français` );
	await page.goto( URL, { waitUntil: 'networkidle' } );
	const php = scanPhp( await page.content() );
	check( php.length === 0, 'aucune erreur PHP servie', php[ 0 ] );
	check( !! ( await page.$( '.aod-landing' ) ), 'la landing est rendue' );

	// L'en-tête et le pied restent ceux du THÈME : la nav à 3 entrées et le switcher
	// FR/AR doivent survivre à la substitution de gabarit — c'est la raison d'être
	// de get_header()/get_footer() dans single-product-landing.php.
	const targets = await page.$$eval( '.menu-item > a[href]', ( as ) =>
		[ ...new Set( as.map( ( a ) => a.href ) ) ]
	);
	check( targets.length === 3, 'l’en-tête du thème est conservé : nav à 3 entrées', targets.join( ' · ' ) );
	check( !! ( await page.$( '.aod-lang' ) ), 'le switcher FR/AR est conservé' );

	check( !! ( await page.$( 'form.aod-cod, .aod-cod' ) ), 'le formulaire COD est dans la landing' );
	check( !! ( await page.$( '#aod-commander' ) ), 'l’ancre #aod-commander existe (cible de tous les CTA)' );

	// Règle 4 : la landing ne rouvre pas le tunnel, quel que soit son contenu.
	const atc = await page.$$eval( 'a[href*="add-to-cart="], button[name="add-to-cart"]', ( els ) => els.length );
	check( atc === 0, 'aucun lien qui remplit le panier' );
	const tunnel = await page.$$eval( 'a[href]', ( as ) =>
		as.map( ( a ) => a.getAttribute( 'href' ) ).filter( ( h ) =>
			/\/(cart|panier|checkout|commande|my-account|mon-compte)(\/|$|\?)/i.test( h || '' ) )
	);
	check( tunnel.length === 0, 'aucun lien panier/compte', tunnel.join( ', ' ) );

	console.log( '\n▶ Arabe (RTL)' );
	await page.goto( `${ URL }&aod_lang=ar`, { waitUntil: 'networkidle' } );
	const dir = await page.evaluate( () => document.documentElement.getAttribute( 'dir' ) );
	const lang = await page.evaluate( () => document.documentElement.getAttribute( 'lang' ) );
	check( 'rtl' === dir, 'la page est en RTL' );
	check( /^ar/.test( lang || '' ), '<html lang="ar">', `lang="${ lang }"` );
	// Le CHROME de la landing (pas le contenu du gérant, qu'on ne connaît pas ici) :
	// aucun libellé de code ne doit rester en français.
	const body = ( await page.textContent( 'body' ) ) || '';
	const frLeft = [ 'Commander maintenant', 'Lire la vidéo', 'Fin de l’offre dans' ]
		.filter( ( s ) => body.includes( s ) );
	check( frLeft.length === 0, 'aucune chaîne de chrome restée en français', frLeft.join( ', ' ) );

	console.log( '\n▶ Mobile 390 px (AR — le pire cas)' );
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.waitForTimeout( 400 );
	const over = await page.evaluate(
		() => document.documentElement.scrollWidth - document.documentElement.clientWidth
	);
	check( over <= 1, `aucun débordement horizontal (${ over } px)` );

	// La barre collante, si le gérant l'a activée : elle ne doit pas recouvrir la fin
	// de page (marge sur le body), et son lien doit viser l'ancre du formulaire.
	if ( await page.$( '.aod-landing__sticky' ) ) {
		const pad = await page.evaluate( () => parseFloat( getComputedStyle( document.body ).paddingBottom ) );
		check( pad >= 40, `barre collante : le corps de page est rallongé (${ pad } px)` );
		const href = await page.$eval( '.aod-landing__sticky-cta', ( a ) => a.getAttribute( 'href' ) );
		check( '#aod-commander' === href, 'barre collante : le bouton vise le formulaire', href );
	}

	console.log( '\n▶ L’aperçu est réservé au gérant' );
	// Un visiteur avec `?aod_landing_preview=1` sur un produit SANS landing publiée ne
	// doit rien voir : ce paramètre n'est pas une porte dérobée. Sur CE produit (landing
	// publiée), il est simplement sans effet — le vrai test demande un produit éteint,
	// qu'on n'a pas forcément : on vérifie au moins que le paramètre ne casse rien.
	await page.goto( `${ URL }&aod_landing_preview=1`, { waitUntil: 'domcontentloaded' } );
	check( scanPhp( await page.content() ).length === 0, 'le paramètre d’aperçu ne provoque aucune erreur' );

	// 🚨 LE RETRAIT DE CE BLOC DOIT SE VOIR. Ce fichier a déjà tiré la leçon plus
	// haut (« sortir à 0 rendait ce retrait indiscernable d'un succès »), et ce
	// `if` l'enfreignait : sur un clone où TOUS les produits publiés ont une page
	// de vente, `OTHER` est vide, les 4 contrôles de non-régression disparaissent
	// du bilan, et la porte annonce « la page de vente respecte les règles » sans
	// avoir vérifié qu'un produit ordinaire s'affiche encore normalement. Un
	// contrôle absent doit se lire ; il ne doit jamais se déduire.
	if ( ! OTHER ) {
		console.log( '\nⓘ Non-régression RETIRÉE : aucun produit publié SANS page de vente sur ce clone.' );
		console.log( '  (rien n’a été vérifié de ce côté — ce n’est pas un succès)' );
	}

	if ( OTHER ) {
		console.log( `\n▶ Non-régression (produit ${ OTHER }, sans landing)` );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( `${ BASE }/?p=${ OTHER }`, { waitUntil: 'domcontentloaded' } );
		const html = await page.content();
		check( ! html.includes( 'aod-landing.css' ), 'il ne charge pas le CSS de la landing' );
		check( ! html.includes( 'aod-landing.js' ), 'il ne charge pas le JS de la landing' );
		check( ! ( await page.$( '.aod-landing' ) ), 'il s’affiche comme une fiche produit normale' );
		check( !! ( await page.$( 'form.aod-cod, .aod-cod' ) ), '…avec son formulaire COD' );
	}

	const errs = console_.filter( ( m ) => m.startsWith( '[pageerror]' ) );
	check( errs.length === 0, 'aucune erreur JS', errs[ 0 ] );

	console.log( fails === 0
		? '\n✅ La page de vente respecte les règles.'
		: `\n❌ ${ fails } contrôle(s) en échec.` );
	await browser.close();
	process.exit( fails === 0 ? 0 : 1 );
} )();
