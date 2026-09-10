/**
 * Porte `panier` — le panier COD togglable, de bout en bout (Phases 35 + 36).
 *
 * Prouve les DEUX faces du toggle :
 *   OFF → pas un octet : ni bouton, ni fab, ni asset demandé (requêtes réseau
 *         surveillées — un enqueue oublié derrière sa garde se verrait ici),
 *         ni surimpression sur les grilles, ni suggestions sous le formulaire
 *         (36.4) ;
 *   ON  → parcours visiteur réel : ajout depuis la BOUTIQUE sans navigation
 *         (surimpression 36.1), bloc de gestion sur la page du formulaire
 *         (quantités/retrait, récapitulatif qui suit — 36.2), suggestions
 *         (36.3), puis le parcours 35 : badge, panneau peint depuis le serveur
 *         (FR puis AR — titres et chrome dans la langue courante),
 *         « Commander » → 1ʳᵉ fiche + ancre, soumission → UNE commande à
 *         2 lignes RELUE EN BASE aux prix serveur, panier vidé.
 *
 * Fixtures : scripts/recette-seed-panier.php (produits marqués, option
 * capturée/restaurée par le cleanup — leçon D26). URLs via AOD_P35_URL1/2.
 * Le toggle est basculé par docker compose depuis l'HÔTE (comme run.sh).
 */
'use strict';

const { execSync, execFileSync } = require( 'child_process' );
const path = require( 'path' );
const { launch, scanPhp, SHOTS, BASE, PURGE_VERROUS_COD } = require( './lib' );

const URL1 = process.env.AOD_P35_URL1 || '';
const URL2 = process.env.AOD_P35_URL2 || '';
// Gamma — le témoin À VARIANTES (2026-08-02). Sans lui, la porte ne voyait que
// des produits simples, c'est-à-dire la moitié du catalogue réel.
const URL3 = process.env.AOD_P35_URL3 || '';
// Les deux vignettes témoins (taille `woocommerce_thumbnail`, comme le panneau) :
// celle du PRODUIT Gamma, et celle de sa variante « Rouge ».
const IMGPROD = process.env.AOD_P35_IMGPROD || '';
const IMGVAR  = process.env.AOD_P35_IMGVAR || '';
const REPO = path.resolve( __dirname, '..', '..' );
const PHONE = '05' + String( Math.floor( 10000000 + Math.random() * 89999999 ) );

function wp( cmd ) {
	return execSync( 'docker compose exec -T wpcli wp ' + cmd, { cwd: REPO } ).toString().trim();
}

// Variante SANS shell (arguments passés tels quels), pour les bouts de PHP qui
// portent leurs propres apostrophes : `wp()` ci-dessus concatène dans une ligne
// de shell, où il faudrait les ré-échapper. Patron de `commande-cod.js`.
function wpArgs( args ) {
	return execFileSync( 'docker', [ 'compose', 'exec', '-T', 'wpcli', 'wp', ...args ], { cwd: REPO, encoding: 'utf8' } ).trim();
}

let fails = 0;
function ok( cond, label ) {
	console.log( ( cond ? '  ✅ ' : '  ❌ ' ) + label );
	if ( ! cond ) fails++;
}

( async () => {
	if ( ! URL1 || ! URL2 || ! URL3 ) {
		console.log( '  ❌ URLs des produits témoins absentes (AOD_P35_URL1/2/3) — seed manquant ?' );
		process.exit( 1 );
	}

	const { browser, ctx } = await launch( { width: 390, height: 844 } ); // mobile d'abord
	const page = await ctx.newPage();

	/* --- Face OFF : pas un octet ------------------------------------------ */
	wp( 'option delete aod_cod_cart 2>/dev/null || true' );
	const cartAssets = [];
	page.on( 'request', ( r ) => { if ( /aod-cart\.(js|css)/.test( r.url() ) ) cartAssets.push( r.url() ); } );
	await page.goto( URL1, { waitUntil: 'load' } );
	// scanPhp rend un TABLEAU — `![]` est toujours faux : comparer la longueur
	// (précédent commande-cod.js:170). L'assertion d'origine ne pouvait jamais
	// être verte ; vue rouge au premier run, à raison mais pour la mauvaise cause.
	const offPhp = scanPhp( await page.content() );
	ok( 0 === offPhp.length, 'OFF — aucune erreur PHP sur la fiche' + ( offPhp.length ? ` (${ offPhp[ 0 ] })` : '' ) );
	ok( await page.locator( '.aod-cod__form' ).count() >= 1, 'OFF — ANCRAGE : le formulaire COD est rendu (sinon tout serait vrai par vacuité)' );
	ok( await page.locator( '.aod-cod__addcart' ).count() === 0, 'OFF — aucun bouton « Ajouter au panier »' );
	ok( await page.locator( '#aod-cart-fab' ).count() === 0, 'OFF — aucun bouton flottant' );
	ok( await page.locator( '.aod-cart__suggest' ).count() === 0, 'OFF — aucune suggestion sous le formulaire (36.3)' );
	await page.goto( `${ BASE }/boutique/`, { waitUntil: 'load' } );
	ok( await page.locator( 'li.product' ).count() >= 2, 'OFF — ANCRAGE : la grille Boutique est rendue' );
	ok( await page.locator( '.aod-cart__carddata, .aod-cart__quickadd' ).count() === 0, 'OFF — aucune surimpression sur les cartes (36.1)' );
	ok( cartAssets.length === 0, 'OFF — zéro asset panier demandé (réseau surveillé, fiche + Boutique)' );

	/* --- Face ON — 36.1 : ajout depuis la Boutique, sans navigation -------- */
	wp( 'option update aod_cod_cart 1' );
	await page.goto( `${ BASE }/boutique/`, { waitUntil: 'load' } );
	ok( await page.locator( '.aod-cart__quickadd' ).count() >= 2, 'ON — surimpressions posées sur les cartes (36.1)' );
	ok( cartAssets.length > 0, 'ON — assets panier chargés' );
	const carte1   = page.locator( 'li.product' ).filter( { has: page.locator( `a[href="${ URL1 }"]` ) } );
	const urlAvant = page.url();
	// 🚨 LU AVANT LE CLIC, et ce n'est pas un détail de style : l'ajout remplace
	// le texte du bouton par « Ajouté ✓ » pendant 1 600 ms. Le lire après, c'est
	// comparer le libellé d'une carte à l'accusé de réception d'une autre — vu
	// rouge au premier run, sur un libellé pourtant correct.
	const libSimple = ( await carte1.locator( '.aod-cart__quickadd' ).first().innerText() ).trim();
	await carte1.locator( '.aod-cart__quickadd' ).first().hover();
	await carte1.locator( '.aod-cart__quickadd' ).first().click();
	await page.waitForTimeout( 400 );
	ok( page.url() === urlAvant, 'ON — clic sur la surimpression = ajout SANS navigation (36.1)' );
	ok( await page.locator( '#aod-cart-fab:not([hidden])' ).count() === 1, 'ON — fab visible après ajout' );
	ok( ( await page.locator( '.aod-cart__badge' ).innerText() ) === '1', 'ON — badge = 1' );

	/* --- Le libellé de la surimpression est UNIQUE (2026-08-02) -------------
	 * Il dépendait de `data-hasopts` : « Choisir les options » sur un produit à
	 * variantes. Comme un catalogue réel en est plein (les deux produits de
	 * démonstration ont une couleur), TOUTES les cartes affichaient ce libellé
	 * et la grille n'annonçait plus qu'il existe un panier. Le libellé est
	 * désormais le même partout ; ce qui reste conditionnel, c'est le CLIC —
	 * un produit à variantes ouvre le panneau de choix (et se replie sur la
	 * fiche), on n'ajoute pas une couleur en devinant. Les quatre assertions
	 * gardent les deux moitiés de cette
	 * décision : sans la 3ᵉ et la 4ᵉ, on pourrait « corriger » le libellé en
	 * ajoutant au panier une variante que l'acheteur n'a jamais choisie. */
	const carteVar = page.locator( 'li.product' ).filter( { has: page.locator( `a[href="${ URL3 }"]` ) } );
	const dataVar  = await carteVar.locator( '.aod-cart__carddata' ).getAttribute( 'data-hasopts' );
	ok( '1' === dataVar, 'ON — ANCRAGE : le témoin Gamma est bien reconnu À VARIANTES (sinon le libellé serait juste par vacuité)' );
	const libVar = ( await carteVar.locator( '.aod-cart__quickadd' ).innerText() ).trim();
	ok( libVar === libSimple && ! /Choisir/i.test( libVar ), `ON — même libellé sur une carte à variantes que sur une carte simple (lu : « ${ libVar } » vs « ${ libSimple } »)` );
	/* --- PANNEAU DE VARIANTES INLINE (refonte 2026-09-10) : le geste aboutit
	 * SUR LA GRILLE, DANS LA CARTE ----------------------------------------
	 * Le bouton disait « Ajouter au panier » et menait à la fiche — il promettait
	 * ce qu'il ne faisait pas. Il ouvre désormais, DANS LA CARTE elle-même, un
	 * panneau qui DEMANDE la variante : on choisit, ça s'ajoute, la page ne
	 * bouge pas. Ce qui ne change pas : on n'ajoute jamais une option devinée.
	 * Ce N'EST PLUS une modale (Phases 35-37 retirées, refonte owner) : pas de
	 * piège de focus à vérifier, pas de fond rendu inerte — c'est un disclosure
	 * ordinaire dans le flux normal de la carte. */
	const badgeAvantVar = await page.locator( '.aod-cart__badge' ).innerText();
	const urlAvantVar   = page.url();
	const panneauVar    = carteVar.locator( '.aod-cart__inline' );
	await carteVar.locator( '.aod-cart__quickadd' ).hover();
	await carteVar.locator( '.aod-cart__quickadd' ).click();
	await page.waitForSelector( 'li.product .aod-cart__inline .aod-cod__optsec', { timeout: 10000 } ).catch( () => {} );
	ok( await panneauVar.isVisible(), 'PANNEAU — le clic sur une carte à variantes OUVRE le panneau, DANS la carte' );
	ok( page.url() === urlAvantVar, `PANNEAU — …SANS quitter la grille (lu : ${ page.url().replace( BASE, '' ) })` );
	ok( badgeAvantVar === ( await page.locator( '.aod-cart__badge' ).innerText() ),
		'PANNEAU — …et sans rien ajouter tant que rien n’est choisi' );
	// Le markup vient du SERVEUR : c'est ce qui fait hériter l'état de rupture et
	// les noms localisés. On vérifie qu'il est bien là, sinon le panneau serait
	// une boîte vide et les assertions suivantes vraies par vacuité.
	ok( await panneauVar.locator( '.aod-cod__optsec' ).count() >= 1,
		'PANNEAU — ANCRAGE : les sections d’options sont servies par le serveur' );
	// Le focus REJOINT le panneau à l'ouverture (disclosure, pas modale : on ne
	// vérifie plus un piège, juste que le clavier n'est pas laissé en arrière).
	ok( await page.evaluate( () => !! ( document.activeElement && document.activeElement.closest && document.activeElement.closest( '.aod-cart__inline' ) ) ),
		'PANNEAU — le focus rejoint le panneau à son ouverture' );

	// Ajouter sans choisir : refusé, et dit.
	await panneauVar.locator( '.aod-cart__inline-add' ).click();
	await page.waitForTimeout( 400 );
	ok( '' !== ( await panneauVar.locator( '.aod-cart__inline-msg' ).innerText() ).trim(),
		'PANNEAU — ajouter sans choisir est refusé, avec un message' );
	ok( badgeAvantVar === ( await page.locator( '.aod-cart__badge' ).innerText() ),
		'PANNEAU — …et le panier n’a pas bougé' );

	// Escape referme le panneau et rend le focus à son déclencheur — le même
	// geste qu'avant, même si ce n'est plus une modale.
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 500 );
	ok( 0 === ( await carteVar.locator( '.aod-cart__inline' ).count() ),
		'PANNEAU — Escape referme le panneau (retiré de la carte)' );
	ok( await page.evaluate( () => !! ( document.activeElement && document.activeElement.classList && document.activeElement.classList.contains( 'aod-cart__quickadd' ) ) ),
		'PANNEAU — …et rend le focus au bouton qui l’a ouvert' );

	// Choisir puis ajouter : le geste aboutit, sur place, DANS la carte.
	await carteVar.locator( '.aod-cart__quickadd' ).click();
	await page.waitForSelector( 'li.product .aod-cart__inline .aod-cod__optsec', { timeout: 10000 } ).catch( () => {} );
	await carteVar.locator( '.aod-cart__inline .aod-cod__opt' ).first().click();
	await carteVar.locator( '.aod-cart__inline .aod-cart__inline-add' ).click();
	await page.waitForTimeout( 1900 );
	ok( 0 === ( await carteVar.locator( '.aod-cart__inline' ).count() ), 'PANNEAU — le choix fait, le panneau se referme (retiré de la carte)' );
	ok( page.url() === urlAvantVar, 'PANNEAU — …toujours sans quitter la grille' );
	ok( '2' === ( await page.locator( '.aod-cart__badge' ).innerText() ), 'PANNEAU — …et l’article EST au panier (badge 1 → 2)' );
	// On revient à un panier d'UN article : la suite de la porte compte dessus.
	await page.evaluate( () => {
		var c = JSON.parse( window.localStorage.getItem( 'aod_cart' ) || '[]' );
		window.localStorage.setItem( 'aod_cart', JSON.stringify( c.slice( 0, 1 ) ) );
	} );
	await page.reload( { waitUntil: 'load' } );
	ok( '1' === ( await page.locator( '.aod-cart__badge' ).innerText() ), 'PANNEAU — ANCRAGE : le décor est rendu à la suite de la porte (badge 1)' );

	/* --- Le repère d'arrivée (2026-08-02) ----------------------------------
	 * Le visiteur vient d'appuyer sur « Ajouter au panier » et rien n'a été
	 * ajouté : la page a sauté, le compteur n'a pas bougé, et RIEN ne reliait
	 * les deux moments (mesuré : atterrissage à 3 567 px, aucun message).
	 * La phrase nomme les deux gestes qui restent. Elle ne doit exister QUE
	 * pour qui arrive par ce chemin — un visiteur venu de la Boutique ou d'un
	 * lien ne l'a pas demandée — et disparaître dès qu'elle est obsolète. */
	// ⚠️ Depuis le panneau de choix, la carte ne navigue plus : ce chemin est
	// devenu celui du REPLI (serveur qui refuse, réseau coupé, coque absente),
	// où la fiche reste la sortie. On l'atteint donc par l'ancre elle-même.
	await page.goto( URL3 + '#aod-options', { waitUntil: 'load' } );
	const repere = page.locator( '.aod-cod__pickhint' );
	await page.waitForTimeout( 900 );
	ok( await repere.isVisible(), 'REPÈRE (repli) — la phrase d’arrivée est affichée quand on atterrit sur l’ancre' );
	const txtRepere = ( await repere.innerText() ).trim();
	const libAjout  = ( await page.locator( '.aod-cod__addcart' ).innerText() ).trim();
	// Elle CITE le bouton, elle ne le paraphrase pas : deux libellés qui
	// divergent enverraient le visiteur chercher un bouton qui n'existe pas.
	ok( txtRepere.indexOf( libAjout ) !== -1, `REPÈRE — elle cite le bouton mot pour mot (« ${ txtRepere } » ⊃ « ${ libAjout } »)` );
	const boite = await repere.boundingBox();
	const vp    = page.viewportSize();
	// ⚠️ Tolérance d'UN pixel, et elle est mesurée : la boîte arrive à y = -0.5
	// (sous-pixel), ce que `Math.round` affichait « 0 » — l'assertion rougissait
	// en imprimant des chiffres qui semblaient la satisfaire. Un contrôle dont le
	// message contredit le verdict est pire qu'un contrôle absent.
	// Ce qu'on veut dire reste entier : la phrase ne doit pas être hors écran.
	ok( boite && boite.y >= -1 && ( boite.y + boite.height ) <= vp.height,
		`REPÈRE — elle est ENTIÈREMENT dans l’écran à l’arrivée (haut ${ boite ? boite.y.toFixed( 1 ) : '?' }, bas ${ boite ? ( boite.y + boite.height ).toFixed( 1 ) : '?' } / ${ vp.height })` );

	// Choisir efface : un message qui a fait son travail devient du bruit.
	// ⚠️ On asserte la TRANSITION, pas l'état final : « elle est masquée » est
	// vrai aussi quand elle n'est jamais apparue. Mise au rouge sur l'ancre, cette
	// assertion restait verte par vacuité — elle ne gardait rien.
	const visibleAvantChoix = await repere.isVisible();
	await page.locator( '.aod-cod__optsec .aod-cod__opt' ).first().click();
	await page.waitForTimeout( 600 );
	ok( visibleAvantChoix && ! ( await repere.isVisible() ),
		`REPÈRE — le choix fait, elle s’efface (visible avant : ${ visibleAvantChoix })` );

	// Visiteur DIRECT : rien de neuf à l'écran.
	await page.goto( URL3, { waitUntil: 'load' } );
	await page.waitForTimeout( 700 );
	ok( ! ( await page.locator( '.aod-cod__pickhint' ).isVisible() ),
		'REPÈRE — ANCRAGE : une visite directe (sans l’ancre) ne l’affiche jamais' );
	await page.goto( `${ BASE }/boutique/`, { waitUntil: 'load' } );

	/* --- PLAFOND DE LIGNES (D35, 2026-08-03) -------------------------------
	 * `MAX_ITEMS` écrêtait dans l'estimation ET dans le funnel de commande ;
	 * côté navigateur, rien ne bornait. Un panier de 31 lignes en montrait 30 et
	 * la commande en portait 30, SANS UN MOT : l'acheteur recevait moins qu'il
	 * n'avait demandé. Le refus est désormais opposé et dit — et ce bloc garde
	 * les deux moitiés : qu'on refuse la 31ᵉ LIGNE, et qu'on ne refuse JAMAIS
	 * une fusion (ajouter une unité à une ligne déjà là ne crée pas de ligne). */
	const panierAvantPlafond = await page.evaluate( () => window.localStorage.getItem( 'aod_cart' ) );
	const maxJs  = await page.evaluate( () => ( window.AOD_CART || {} ).max_items );
	const maxPhp = wpArgs( [ 'eval', 'echo AOD_COD_Cart::MAX_ITEMS;' ] );
	// Le nombre se déclare UNE fois, côté serveur. Un plafond recopié dans le JS
	// divergerait le jour où on touche à la constante — c'est la raison même pour
	// laquelle `build_order_lines()` réutilise celle du panier (44.1).
	ok( String( maxJs ) === String( maxPhp ) && parseInt( maxPhp, 10 ) > 0,
		`PLAFOND — le navigateur reçoit le plafond du SERVEUR, sans le recopier (JS ${ maxJs } = PHP ${ maxPhp })` );
	const MAX = parseInt( maxPhp, 10 );

	// Un panier PLEIN de lignes distinctes. Les produits n'ont pas besoin
	// d'exister : c'est le nombre de LIGNES qui est borné, et la borne est posée
	// avant toute lecture de produit.
	const semerPlein = async ( n, avecReel ) => {
		await page.evaluate( ( [ nb, reel ] ) => {
			const c = [];
			if ( reel ) { c.push( { product_id: reel, qty: 1, opt: {} } ); }
			for ( let i = c.length; i < nb; i++ ) { c.push( { product_id: 900000 + i, qty: 1, opt: {} } ); }
			window.localStorage.setItem( 'aod_cart', JSON.stringify( c ) );
		}, [ n, avecReel || 0 ] );
		await page.reload( { waitUntil: 'load' } );
	};
	const nbLignes = () => page.evaluate( () => JSON.parse( window.localStorage.getItem( 'aod_cart' ) || '[]' ).length );

	await semerPlein( MAX );
	ok( MAX === await nbLignes(), `PLAFOND — ANCRAGE : le panier témoin est bien PLEIN (${ MAX } lignes)` );
	const carteSimple2 = page.locator( 'li.product' ).filter( { has: page.locator( `a[href="${ URL1 }"]` ) } );
	await carteSimple2.locator( '.aod-cart__quickadd' ).hover();
	await carteSimple2.locator( '.aod-cart__quickadd' ).click();
	await page.waitForTimeout( 900 );
	ok( MAX === await nbLignes(), `PLAFOND — 🔴 le ${ MAX + 1 }ᵉ article est REFUSÉ (lignes : ${ await nbLignes() })` );
	// Le refus doit se VOIR. La carte n'a aucune surface de message : le panier
	// s'ouvre avec la phrase — l'acheteur y lit la raison ET voit la ligne à
	// retirer. Un refus muet serait le défaut d'origine, déplacé d'un cran.
	const capRefus = page.locator( '#aod-cart-drawer > p.aod-cart__cap' );
	ok( await page.locator( '#aod-cart-drawer' ).isVisible() && await capRefus.isVisible(),
		'PLAFOND — 🔴 …le panier s’ouvre et DIT le refus' );
	const txtRefus = ( await capRefus.innerText() ).trim();
	ok( txtRefus.indexOf( String( MAX ) ) !== -1,
		`PLAFOND — …et la phrase nomme le nombre (« ${ txtRefus } »)` );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	// La FUSION reste permise au plafond : refuser une unité sur une ligne déjà
	// présente serait un second défaut — l'acheteur ne comprendrait pas qu'on lui
	// refuse un article qui est DÉJÀ dans son panier.
	const pid1 = parseInt( await page.evaluate( ( u ) => {
		const c = document.querySelector( `li.product a[href="${ u }"]` );
		const li = c && c.closest( 'li.product' );
		const d = li && li.querySelector( '.aod-cart__carddata' );
		return d ? d.getAttribute( 'data-pid' ) : '0';
	}, URL1 ), 10 );
	ok( pid1 > 0, `PLAFOND — ANCRAGE : le produit témoin de la fusion est identifié (pid ${ pid1 })` );
	await semerPlein( MAX, pid1 );
	await carteSimple2.locator( '.aod-cart__quickadd' ).hover();
	await carteSimple2.locator( '.aod-cart__quickadd' ).click();
	await page.waitForTimeout( 900 );
	const qFusion = await page.evaluate( ( p ) => {
		const l = JSON.parse( window.localStorage.getItem( 'aod_cart' ) || '[]' ).find( ( it ) => it.product_id === p );
		return l ? l.qty : 0;
	}, pid1 );
	ok( 2 === qFusion && MAX === await nbLignes(),
		`PLAFOND — 🔴 au plafond, une FUSION passe toujours (qty ${ qFusion }, lignes ${ await nbLignes() })` );
	ok( ! ( await page.locator( '#aod-cart-drawer' ).isVisible() ),
		'PLAFOND — …et rien ne s’ouvre : ce n’est pas un refus' );

	// Un store DÉJÀ au-delà (écrit avant cette version, ou à la main) : le
	// serveur est le seul à voir le nombre demandé avant de le réduire. Il le
	// DIT, au lieu de laisser un article s'évaporer de l'écran.
	await semerPlein( MAX + 1 );
	await page.locator( '#aod-cart-fab' ).click();
	await page.waitForSelector( '#aod-cart-drawer .aod-cart__list > p.aod-cart__cap', { timeout: 10000 } ).catch( () => {} );
	const capEcret = page.locator( '#aod-cart-drawer .aod-cart__list > p.aod-cart__cap' );
	ok( await capEcret.count() === 1 && await capEcret.isVisible(),
		`PLAFOND — 🔴 un panier de ${ MAX + 1 } lignes ANNONCE l’écrêtage (il ne l’avalait pas)` );
	// ⚠️ Lecture TOLÉRANTE : mise au rouge, `innerText()` sur un nœud absent lève,
	// la porte s'arrêtait là et taisait les assertions suivantes. Un cliquet doit
	// NOMMER ce qui casse, pas mourir dessus.
	const txtEcret = await capEcret.count() ? ( await capEcret.innerText() ).trim() : '';
	// Deux MOMENTS distincts, donc deux phrases : « retirez-en un » répond à un
	// geste qu'on vient de refuser ; le constat d'un panier déjà au-delà ne
	// s'adresse à personne qui vient de demander quoi que ce soit.
	ok( '' !== txtEcret && txtEcret !== txtRefus,
		`PLAFOND — …avec sa propre phrase, pas celle du refus (« ${ txtEcret } »)` );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	// Le panneau de choix, lui, RESTE OUVERT sur un refus : se fermer rendrait le
	// geste indistinguable d'un ajout réussi — le défaut même qu'il est venu
	// réparer.
	await semerPlein( MAX );
	const carteVar2 = page.locator( 'li.product' ).filter( { has: page.locator( `a[href="${ URL3 }"]` ) } );
	await carteVar2.locator( '.aod-cart__quickadd' ).hover();
	await carteVar2.locator( '.aod-cart__quickadd' ).click();
	await page.waitForSelector( 'li.product .aod-cart__inline .aod-cod__optsec', { timeout: 10000 } ).catch( () => {} );
	await carteVar2.locator( '.aod-cart__inline .aod-cod__opt' ).first().click();
	await carteVar2.locator( '.aod-cart__inline .aod-cart__inline-add' ).click();
	await page.waitForTimeout( 700 );
	ok( 1 === ( await carteVar2.locator( '.aod-cart__inline' ).count() ),
		'PLAFOND — 🔴 le panneau RESTE dans la carte quand le plafond refuse' );
	ok( '' !== ( await carteVar2.locator( '.aod-cart__inline-msg' ).innerText() ).trim() && MAX === await nbLignes(),
		`PLAFOND — …il dit pourquoi, et rien n’est ajouté (lignes ${ await nbLignes() })` );
	await page.keyboard.press( 'Escape' );

	// Décor rendu : la suite de la porte compte sur UN article.
	await page.evaluate( ( v ) => window.localStorage.setItem( 'aod_cart', v ), panierAvantPlafond );
	await page.reload( { waitUntil: 'load' } );
	ok( '1' === ( await page.locator( '.aod-cart__badge' ).innerText() ), 'PLAFOND — ANCRAGE : le décor est rendu à la suite de la porte (badge 1)' );

	/* --- DEUX ONGLETS (D36, 2026-08-03) ------------------------------------
	 * Le store est relu à chaque geste : rien ne se perd. C'est l'ÉCRAN qui
	 * restait périmé — et le formulaire COD soumet le STORE, pas l'écran. Un
	 * onglet affichant « 1 article » pouvait donc envoyer une commande à deux
	 * lignes : l'acheteur payait un article que sa page ne montrait pas. */
	const onglet2 = await ctx.newPage();
	await onglet2.goto( `${ BASE }/boutique/`, { waitUntil: 'load' } );
	const badgeAvant2 = await page.locator( '.aod-cart__badge' ).innerText();
	const carteO2 = onglet2.locator( 'li.product' ).filter( { has: onglet2.locator( `a[href="${ URL2 }"]` ) } );
	await carteO2.locator( '.aod-cart__quickadd' ).hover();
	await carteO2.locator( '.aod-cart__quickadd' ).click();
	await onglet2.waitForTimeout( 400 );
	await page.waitForTimeout( 900 );
	const badgeApres2 = await page.locator( '.aod-cart__badge' ).innerText();
	ok( '1' === badgeAvant2 && '2' === badgeApres2,
		`DEUX ONGLETS — 🔴 le premier onglet SUIT l’ajout fait dans le second (badge ${ badgeAvant2 } → ${ badgeApres2 })` );
	// Le store, lui, était déjà juste : sans cette assertion, on pourrait croire
	// que le correctif porte sur la donnée alors qu'il porte sur l'affichage.
	ok( 2 === await page.evaluate( () => JSON.parse( window.localStorage.getItem( 'aod_cart' ) || '[]' ).length ),
		'DEUX ONGLETS — ANCRAGE : le store, lui, n’a jamais rien perdu (2 lignes)' );
	await onglet2.close();
	// Décor rendu : la suite compte sur UN article.
	await page.evaluate( () => {
		const c = JSON.parse( window.localStorage.getItem( 'aod_cart' ) || '[]' );
		window.localStorage.setItem( 'aod_cart', JSON.stringify( c.slice( 0, 1 ) ) );
	} );
	await page.reload( { waitUntil: 'load' } );
	ok( '1' === ( await page.locator( '.aod-cart__badge' ).innerText() ), 'DEUX ONGLETS — ANCRAGE : le décor est rendu à la suite de la porte (badge 1)' );

	await page.goto( URL2, { waitUntil: 'load' } );
	ok( await page.locator( '.aod-cod__addcart' ).count() === 1, 'ON — bouton « Ajouter au panier » présent sur la fiche' );
	await page.locator( '.aod-cod__addcart' ).click();
	await page.waitForTimeout( 300 );
	ok( ( await page.locator( '.aod-cart__badge' ).innerText() ) === '2', 'ON — badge = 2 (persistance localStorage entre pages)' );

	/* --- 36.2 : le bloc de gestion au-dessus du formulaire ------------------ */
	await page.waitForSelector( '.aod-cart__box:not([hidden]) .aod-cart__line', { timeout: 10000 } );
	ok( 2 === await page.locator( '.aod-cart__box:not([hidden]) .aod-cart__line' ).count(), 'ON — bloc de gestion peint : 2 lignes (36.2)' );
	await page.locator( '.aod-cart__box .aod-cart__more' ).first().click();
	await page.waitForTimeout( 900 );
	ok( '3' === ( await page.locator( '.aod-cart__badge' ).innerText() ), 'ON — quantité +1 depuis le bloc (badge 3)' );
	const sousTotal = ( await page.locator( '.aod-cod__subtotal' ).innerText() ).replace( /[^\d]/g, '' );
	ok( '2500' === sousTotal, `ON — le récapitulatif du formulaire suit le panier (2×1000 + 500, lu : ${ sousTotal })` );
	await page.locator( '.aod-cart__box .aod-cart__less' ).first().click();
	await page.waitForTimeout( 900 );
	ok( '2' === ( await page.locator( '.aod-cart__badge' ).innerText() ), 'ON — quantité −1 depuis le bloc (badge 2)' );

	/* --- 36.3 : suggestions sous le formulaire ------------------------------ */
	ok( await page.locator( '.aod-cart__sugcard' ).count() >= 1, 'ON — suggestions « Complétez votre commande » rendues (36.3)' );
	ok( await page.locator( '.aod-cart__sugcard .aod-cart__carddata' ).count() >= 1, 'ON — les suggestions portent le même nœud d’ajout (36.3)' );

	// Depuis la 36.2, le bloc de gestion duplique lignes et total SUR la page :
	// toutes les assertions du panneau se scopent au drawer, sinon strict mode.
	await page.locator( '#aod-cart-fab' ).click();
	await page.waitForSelector( '#aod-cart-drawer .aod-cart__line', { timeout: 10000 } );
	ok( await page.locator( '#aod-cart-drawer .aod-cart__line' ).count() === 2, 'ON — 2 lignes peintes depuis le serveur' );
	const totalFr = ( await page.locator( '#aod-cart-drawer .aod-cart__total' ).innerText() ).replace( /[^\d]/g, '' );
	ok( '150000' === totalFr || '1500' === totalFr, `ON — total estimé = 1 500,00 (chiffres lus : ${totalFr})` );
	await page.screenshot( { path: SHOTS + '/panier-drawer-fr.png' } );

	/* --- Contraste : cette porte savait tout, sauf VOIR (Phase 37) --------- */
	//
	// 🚨 Le défaut fondateur de la 37 a vécu sous CETTE porte, au vert : notre
	// markup portait `class="button"`, le CSS du thème (imprimé après nos
	// feuilles) posait son fond de marque, et « Ajouter au panier » sortait en
	// vert sur bleu — **1,05:1**, invisible. La porte vérifiait la PRÉSENCE du
	// bouton, jamais sa lisibilité ; la porte `contraste`, elle, n'audite que
	// `/gestion`. Aucune des deux ne pouvait le voir. On mesure donc ici, sur
	// le rendu réel, et sur les ÉTATS — c'est un `:focus` qui a rendu le bouton
	// de fermeture bleu, et un état ne se lit pas au repos.
	const AA = 4.5;

	const lum = ( c ) => {
		const m = ( String( c ).match( /[\d.]+/g ) || [ 0, 0, 0 ] ).map( Number );
		const f = ( v ) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ); };
		return 0.2126 * f( m[ 0 ] ) + 0.7152 * f( m[ 1 ] ) + 0.0722 * f( m[ 2 ] );
	};
	const ratio = ( a, b ) => {
		const l1 = lum( a ), l2 = lum( b );
		return ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
	};

	// Fond EFFECTIF : un fond transparent laisse voir l'ancêtre — mesurer
	// `rgba(0,0,0,0)` reviendrait à mesurer du noir et à rendre vert n'importe quoi.
	const couples = async ( sel, focus ) => {
		if ( focus ) {
			await page.evaluate( ( s ) => { const e = document.querySelector( s ); if ( e ) e.focus(); }, sel );
			await page.waitForTimeout( 350 ); // les transitions doivent finir : sinon on lit la valeur de DÉPART.
		}
		return page.evaluate( ( s ) => {
			const el = document.querySelector( s );
			if ( ! el ) { return null; }
			const alpha = ( c ) => { const m = String( c ).match( /[\d.]+/g ); return m && 4 === m.length ? parseFloat( m[ 3 ] ) : 1; };
			let node = el, bg = getComputedStyle( el ).backgroundColor;
			while ( node && 0 === alpha( bg ) ) {
				node = node.parentElement;
				if ( ! node ) { break; }
				bg = getComputedStyle( node ).backgroundColor;
			}
			if ( ! bg || 0 === alpha( bg ) ) { bg = 'rgb(255, 255, 255)'; }
			return { bg: bg, color: getComputedStyle( el ).color };
		}, sel );
	};

	const mesure = async ( sel, label, focus ) => {
		const c = await couples( sel, focus );
		if ( ! c ) { ok( false, `contraste — ${ label } : élément ABSENT` ); return; }
		const r = ratio( c.bg, c.color );
		ok( r >= AA, `contraste ${ label } = ${ r.toFixed( 2 ) }:1 (≥ ${ AA })` );
	};

	// LE bouton du défaut : il est sur la page, sous le panneau ouvert.
	await mesure( '.aod-cod__addcart', '« Ajouter au panier » (le bouton du défaut)' );
	await mesure( '.aod-cart__quickadd', 'surimpression d’ajout' );
	await mesure( '#aod-cart-fab', 'bouton flottant' );
	await mesure( '.aod-cart__badge', 'compteur du bouton flottant' );
	await mesure( '#aod-cart-drawer .aod-cart__order', '« Commander »' );
	await mesure( '#aod-cart-drawer .aod-cart__linetotal', 'total d’une ligne' );
	await mesure( '#aod-cart-drawer .aod-cart__name', 'nom d’un article' );
	await mesure( '#aod-cart-drawer .aod-cart__note', 'note de livraison' );
	await mesure( '#aod-cart-drawer .aod-cart__remove', 'bouton retirer' );
	// AU FOCUS : l'état que le thème gagnait en (0,1,1) contre nos (0,1,0).
	await mesure( '#aod-cart-drawer .aod-cart__close', '« fermer » AU FOCUS', true );

	// Les noms ne sont plus tronqués à l'ellipse (2 lignes) — en arabe, une
	// troncature ronge le DÉBUT d'un nom latin et rend le produit méconnaissable.
	const coupes = await page.evaluate( () => Array.from( document.querySelectorAll( '#aod-cart-drawer .aod-cart__name' ) )
		.filter( ( n ) => n.scrollHeight > n.clientHeight + 1 ).length );
	ok( 0 === coupes, `noms d’articles entièrement lisibles (tronqués : ${ coupes })` );

	// 🔴 TÉMOIN — sans lui, tout ce bloc serait vert sur une page vide ou avec
	// une formule cassée. On rejoue le couple EXACT du défaut d'origine : il
	// doit être refusé. Un contrôle qu'on n'a jamais vu échouer ne prouve rien.
	const temoin = ratio( 'rgb(4, 107, 210)', 'rgb(0, 128, 96)' );
	ok( temoin < AA, `TÉMOIN : le couple du défaut d’origine est bien refusé (${ temoin.toFixed( 2 ) }:1 < ${ AA })` );

	/* --- AR : le panneau suit la langue courante --------------------------- */
	await page.goto( URL2 + ( URL2.indexOf( '?' ) === -1 ? '?' : '&' ) + 'aod_lang=ar', { waitUntil: 'load' } );
	ok( ( await page.locator( 'html[dir="rtl"]' ).count() ) === 1, 'AR — html dir=rtl' );
	await page.locator( '#aod-cart-fab' ).click();
	await page.waitForSelector( '#aod-cart-drawer .aod-cart__line', { timeout: 10000 } );
	ok( 'سلتك' === ( await page.locator( '#aod-cart-drawer .aod-cart__title' ).innerText() ).trim(), 'AR — chrome du panneau traduit (سلتك)' );
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	ok( overflow <= 0, `AR mobile 390 — pas de débordement horizontal (delta ${overflow}px)` );
	await page.screenshot( { path: SHOTS + '/panier-drawer-ar-390.png' } );

	/* --- Commander : 1ʳᵉ fiche + bandeau + soumission réelle ---------------- */
	await page.goto( URL2, { waitUntil: 'load' } );
	await page.locator( '#aod-cart-fab' ).click();
	await page.waitForSelector( '.aod-cart__order:not([disabled])', { timeout: 10000 } );
	await Promise.all( [
		page.waitForURL( ( u ) => String( u ).indexOf( 'aod-commander' ) !== -1, { timeout: 10000 } ),
		page.locator( '.aod-cart__order' ).click(),
	] );
	ok( page.url().indexOf( 'aod-commander' ) !== -1, '« Commander » mène à la fiche du 1er article + ancre' );
	// 36.2 : le bloc de gestion a remplacé l'ancien bandeau « votre panier (N) ».
	await page.waitForSelector( '.aod-cart__box:not([hidden]) .aod-cart__line', { timeout: 10000 } );
	ok( 2 === await page.locator( '.aod-cart__box:not([hidden]) .aod-cart__line' ).count(), 'Bloc de gestion sur la fiche cible : les 2 lignes du panier (36.2)' );

	// 🚨 Coupe-circuit anti-flood par IP (AOD_COD_MAX_PER_IP_HOUR = 10/h) : cette
	// porte passe une VRAIE commande, donc elle incrémente le compteur à chaque
	// exécution. Au 11ᵉ passage dans l'heure, la commande était refusée et la
	// porte rougissait sur « La commande passe » — un message qui accuse le
	// produit alors que la cause est la porte elle-même.
	// Le helper existait déjà et purge les DEUX verrous (IP + téléphone) ; trois
	// portes qui passent une commande l'appellent (`cod`, `bilingue`, `riche`),
	// celle-ci ne l'appelait pas et re-purgeait le téléphone SEUL, ailleurs, à la
	// main. Même défaut de propagation que ceux qu'on corrige : un bon motif
	// présent dans le dépôt, qui n'a pas atteint son jumeau.
	wpArgs( [ 'eval', PURGE_VERROUS_COD ] );

	await page.fill( '.aod-cod__form input[name="name"]', 'Recette Panier35' );
	await page.fill( '.aod-cod__form input[name="phone"]', PHONE );
	await page.selectOption( '.aod-cod__form select[name="wilaya"]', '16' );
	await page.waitForTimeout( 3400 ); // time-trap 32.3 : jamais sous 3 s.
	await page.locator( '.aod-cod__submit' ).click();
	await page.waitForSelector( '.aod-cod__done, .aod-cod__msg.is-error', { timeout: 20000 } );
	ok( await page.locator( '.aod-cod__done' ).count() >= 1, 'La commande passe (écran de remerciement)' );
	ok( '[]' === ( await page.evaluate( () => window.localStorage.getItem( 'aod_cart' ) ) ), 'Panier vidé après succès' );

	/* --- Relecture EN BASE : 2 lignes aux prix serveur ---------------------- */
	const raw = wp( `eval '
		$os = wc_get_orders( array( "billing_phone" => "${PHONE}", "limit" => 2 ) );
		if ( 1 !== count( $os ) ) { echo "KO:count=" . count( $os ); return; }
		$o = $os[0]; $tot = array();
		foreach ( $o->get_items() as $it ) { $tot[] = (int) $it->get_total(); }
		sort( $tot );
		echo "OK:" . implode( "+", $tot ) . ":" . $o->get_meta( "_aod_wilaya_code" );
	'` );
	ok( 'OK:500+1000:16' === raw, `Relecture base : UNE commande, lignes 500+1000, wilaya 16 (lu : ${raw})` );

	/* --- La ligne du panier montre la photo de la VARIANTE (2026-08-02) ------
	 * Elle montrait la photo du PRODUIT quelle que soit la variante choisie :
	 * « Casque · Bordeaux » s'illustrait du casque noir, et deux lignes du même
	 * produit étaient indistinguables — sur la seule image que l'acheteur voit
	 * avant de valider.
	 * Le témoin Gamma est bâti pour éprouver les DEUX branches sur la même
	 * fixture : « Rouge » a sa photo, « Vert » n'en a pas et doit donc retomber
	 * sur celle du produit. Sans la seconde assertion, on pourrait « corriger »
	 * en montrant n'importe quelle image de variante, y compris celle d'une
	 * autre valeur. Le panier est vide ici (la commande vient de le vider). */
	if ( ! IMGVAR || ! IMGPROD ) {
		ok( false, 'ANCRAGE : les deux images témoins ont été semées (GD indisponible ?)' );
	} else {
		const ligneImg = async ( valeur ) => {
			await page.goto( URL3, { waitUntil: 'load' } );
			await page.evaluate( () => window.localStorage.removeItem( 'aod_cart' ) );
			await page.reload( { waitUntil: 'load' } );
			// ⚠️ On clique le LABEL, pas le radio : les pastilles de variantes
			// masquent leur `<input>` (peau maison). `check()` échoue sur un
			// élément invisible — vu en timeout au premier run.
			await page.locator( '.aod-cod__optsec .aod-cod__opt' )
				.filter( { has: page.locator( `input[value="${ valeur }"]` ) } ).first().click();
			await page.locator( '.aod-cod__addcart' ).click();
			await page.waitForSelector( '.aod-cart__box:not([hidden]) .aod-cart__line img.aod-cart__img', { timeout: 10000 } );
			return page.locator( '.aod-cart__box .aod-cart__line img.aod-cart__img' ).first().getAttribute( 'src' );
		};
		const imgRouge = await ligneImg( 'Rouge' );
		ok( imgRouge === IMGVAR, `La ligne « Rouge » montre la photo de LA VARIANTE${ imgRouge === IMGPROD ? ' (lu : celle du produit — le défaut d’origine)' : ` (lu : ${ String( imgRouge ).split( '/' ).pop() })` }` );
		const imgVert = await ligneImg( 'Vert' );
		ok( imgVert === IMGPROD, `…et « Vert », sans photo à elle, retombe sur celle du PRODUIT (lu : ${ String( imgVert ).split( '/' ).pop() })` );
		await page.evaluate( () => window.localStorage.removeItem( 'aod_cart' ) );
	}

	/* --- A · AUCUN ARTICLE NE S'ÉVAPORE (2026-08-02) ------------------------
	 * Le tri de l'endpoint jugeait la disponibilité, PUIS le funnel de commande
	 * rejugeait — et l'écart entre les deux tombait dans le vide : l'article
	 * sortait du panneau sans un mot, en restant dans le store. Mesuré alors :
	 * badge « 1 » sur un panneau « Votre panier est vide », aucun bouton pour
	 * retirer, et la commande refusée en bloc au nom d'un article absent de
	 * l'écran. Une vente perdue sans issue.
	 * Le geste reproduit ici est CELUI DU GÉRANT : la couleur est ajoutée au
	 * panier alors qu'elle est en stock, puis épuisée pendant que le panier
	 * vit. C'est le seul chemin qui produisait le fantôme. */
	await page.goto( URL3, { waitUntil: 'load' } );
	await page.evaluate( () => window.localStorage.removeItem( 'aod_cart' ) );
	await page.reload( { waitUntil: 'load' } );
	await page.locator( '.aod-cod__optsec .aod-cod__opt' ).filter( { has: page.locator( 'input[value="Bleu"]' ) } ).first().click();
	await page.locator( '.aod-cod__addcart' ).click();
	await page.waitForSelector( '.aod-cart__box:not([hidden]) .aod-cart__line', { timeout: 10000 } );
	ok( '1' === ( await page.locator( '.aod-cart__badge' ).innerText() ), 'A — ANCRAGE : « Bleu » est au panier alors qu’il est EN STOCK' );

	const rupt = wpArgs( [ 'eval-file', '/scripts/recette-seed-panier.php', 'rupture' ] );
	ok( /P35RUPTURE=1/.test( rupt ), 'A — ANCRAGE : le gérant met « Bleu » en rupture (sinon le reste serait vrai par vacuité)' );

	await page.reload( { waitUntil: 'load' } );
	// ⚠️ Attente TOLÉRANTE, et c'est le sabotage qui l'a exigée : quand l'article
	// s'évapore — le défaut même que ce bloc garde — il n'y a plus de ligne à
	// attendre, et un `waitForSelector` strict fait PLANTER la porte sur une pile
	// d'appels au lieu de nommer l'assertion qui a lâché. Une porte qui meurt ne
	// dit pas ce qui ne va pas.
	await page.waitForSelector( '.aod-cart__box:not([hidden]) .aod-cart__line', { timeout: 10000 } ).catch( () => {} );
	const vueA = await page.evaluate( () => {
		const b = document.querySelector( '.aod-cart__box' );
		return {
			lignes: b.querySelectorAll( '.aod-cart__line' ).length,
			mortes: b.querySelectorAll( '.aod-cart__line.is-dead' ).length,
			vide: !! b.querySelector( '.aod-cart__empty' ),
			retraits: b.querySelectorAll( '.aod-cart__line.is-dead .aod-cart__remove' ).length,
			badge: ( document.querySelector( '.aod-cart__badge' ) || {} ).textContent,
			// Le message d'un article VRAIMENT indisponible — sert de témoin au
			// bloc C, qui doit en afficher un AUTRE.
			message: ( ( b.querySelector( '.aod-cart__line.is-dead .aod-cart__dead' ) || {} ).textContent || '' ).trim(),
		};
	} );
	ok( 1 === vueA.lignes && 1 === vueA.mortes && ! vueA.vide,
		`A — l’article refusé par le funnel RESTE une ligne, marquée indisponible (lignes : ${ vueA.lignes }, mortes : ${ vueA.mortes }, panneau vide : ${ vueA.vide })` );
	ok( vueA.badge === String( vueA.lignes ), `A — le badge et le panneau disent le MÊME nombre (badge ${ vueA.badge }, lignes ${ vueA.lignes })` );
	ok( 1 === vueA.retraits, 'A — …et il est RETIRABLE : le cul-de-sac est fermé' );

	// 🚨 …et la MÊME rupture doit interdire l'ajout depuis la grille. C'est le
	// gain de servir le markup de la fiche plutôt que d'en fabriquer un second :
	// l'état `disabled` d'une valeur épuisée arrive tout seul. S'il fallait le
	// réécrire en JS, c'est ici qu'on verrait la divergence — sur une commande
	// impossible.
	await page.goto( `${ BASE }/boutique/`, { waitUntil: 'load' } );
	await page.locator( 'li.product' ).filter( { has: page.locator( `a[href="${ URL3 }"]` ) } )
		.locator( '.aod-cart__quickadd' ).click();
	await page.waitForSelector( 'li.product .aod-cart__inline .aod-cod__optsec', { timeout: 10000 } ).catch( () => {} );
	const etatBleu = await page.evaluate( () => {
		const i = document.querySelector( '.aod-cart__inline input[value="Bleu"]' );
		return i ? { present: true, desactive: i.disabled } : { present: false };
	} );
	ok( etatBleu.present && etatBleu.desactive,
		`A — la variante épuisée est NON SÉLECTIONNABLE dans le panneau (présente : ${ etatBleu.present }, désactivée : ${ etatBleu.desactive })` );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 400 );
	// Retour sur la fiche : le bloc de gestion (`.aod-cart__box`) n'existe QUE là,
	// et c'est lui que la suite interroge. Le détour par la Boutique ci-dessus ne
	// doit pas laisser la porte devant le mauvais écran.
	await page.goto( URL3, { waitUntil: 'load' } );
	await page.waitForSelector( '.aod-cart__box:not([hidden]) .aod-cart__line', { timeout: 10000 } ).catch( () => {} );

	await page.locator( '.aod-cart__box .aod-cart__line.is-dead .aod-cart__remove' ).click();
	await page.waitForTimeout( 700 );
	ok( '[]' === ( await page.evaluate( () => window.localStorage.getItem( 'aod_cart' ) ) ), 'A — le retrait vide bien le panier' );

	/* --- B · « + » AGIT SUR LA LIGNE DÉSIGNÉE -------------------------------
	 * Les boutons ciblaient le `product_id` et la boucle s'arrêtait à la
	 * première correspondance : sur un produit à variantes, « + » sur Vert
	 * incrémentait Rouge, et « retirer » supprimait Rouge. L'acheteur payait une
	 * couleur qu'il n'avait pas choisie. */
	for ( const c of [ 'Rouge', 'Vert' ] ) {
		await page.locator( '.aod-cod__optsec .aod-cod__opt' ).filter( { has: page.locator( `input[value="${ c }"]` ) } ).first().click();
		await page.locator( '.aod-cod__addcart' ).click();
		await page.waitForTimeout( 700 );
	}
	await page.waitForSelector( '.aod-cart__box:not([hidden]) .aod-cart__line:nth-child(2)', { timeout: 10000 } );
	await page.locator( '.aod-cart__box .aod-cart__line' ).nth( 1 ).locator( '.aod-cart__more' ).click();
	await page.waitForTimeout( 900 );
	const storeB = JSON.parse( await page.evaluate( () => window.localStorage.getItem( 'aod_cart' ) ) );
	ok( 2 === storeB.length && 'Rouge' === storeB[ 0 ].opt[ '0' ] && 1 === storeB[ 0 ].qty && 'Vert' === storeB[ 1 ].opt[ '0' ] && 2 === storeB[ 1 ].qty,
		`B — « + » sur la 2ᵉ ligne incrémente LA 2ᵉ (lu : ${ storeB.map( ( it ) => it.opt[ '0' ] + '×' + it.qty ).join( ', ' ) })` );

	/* --- C · UNE LIGNE REFUSÉE SUR SA QUANTITÉ RESTE RÉPARABLE --------------
	 * Un « + » au-delà du stock basculait la ligne en « Produit indisponible »
	 * (faux : le produit l'est, pas à cette quantité), effaçait quantité et
	 * total, et RETIRAIT les boutons − et + : impossible de revenir en arrière
	 * autrement qu'en supprimant la ligne pour repartir de la fiche.
	 * Gamma est borné à 2 en stock : la 3ᵉ unité franchit la limite. */
	await page.locator( '.aod-cart__box .aod-cart__line' ).nth( 1 ).locator( '.aod-cart__more' ).click();
	await page.waitForTimeout( 900 );
	const vueC = await page.evaluate( () => {
		const l = document.querySelectorAll( '.aod-cart__box .aod-cart__line' )[ 1 ];
		return {
			morte: l.classList.contains( 'is-dead' ),
			qty: ( ( l.querySelector( '.aod-cart__qtyval' ) || {} ).textContent || '' ).trim(),
			moins: !! l.querySelector( '.aod-cart__less' ),
			message: ( ( l.querySelector( '.aod-cart__dead' ) || {} ).textContent || '' ).trim(),
		};
	} );
	ok( vueC.morte && '3' === vueC.qty && vueC.moins,
		`C — la ligne au-delà du stock garde sa quantité ET ses commandes (morte : ${ vueC.morte }, qty : ${ vueC.qty }, « − » : ${ vueC.moins })` );
	// 🚨 Comparé au message de l'INDISPONIBLE (bloc A), jamais à un mot français :
	// la porte parcourt cette portion EN ARABE (elle a basculé plus haut et n'est
	// jamais revenue), et chercher « quantité » la rendait rouge sur un produit
	// correct — vu au premier run. Ce qu'on veut dire est de toute façon celui-ci :
	// les deux causes ne se réparent pas du même geste, elles ne peuvent pas
	// partager un message.
	ok( '' !== vueC.message && vueC.message !== vueA.message,
		`C — …et son message DIFFÈRE de celui d’un article indisponible (lu : « ${ vueC.message } » vs « ${ vueA.message } »)` );
	await page.locator( '.aod-cart__box .aod-cart__line' ).nth( 1 ).locator( '.aod-cart__less' ).click();
	await page.waitForTimeout( 1200 );
	ok( false === await page.evaluate( () => document.querySelectorAll( '.aod-cart__box .aod-cart__line' )[ 1 ].classList.contains( 'is-dead' ) ),
		'C — un « − » suffit à la rendre commandable : le geste répare ce que le geste a cassé' );
	await page.evaluate( () => window.localStorage.removeItem( 'aod_cart' ) );

	await browser.close();
	console.log( fails ? `\n  ❌ PANIER : ${fails} échec(s)` : '  ✅ PANIER : le toggle tient ses deux faces.' );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => { console.error( e ); process.exit( 2 ); } );
