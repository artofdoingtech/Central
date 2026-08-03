/**
 * Porte `variante` — stock par variante, de bout en bout au navigateur (Phase 40).
 *
 * Ce que le cliquet unitaire (test-variant-stock.php) ne peut PAS voir : le RENDU.
 *   (a) Public (visiteur) — la variante en rupture est grisée + « Rupture de stock »
 *       + non sélectionnable ; une variante disponible reste sélectionnable.
 *   (b) /gestion → Stock — le produit à variantes affiche la SOMME + le détail par
 *       variante + « géré par variante » (champ LECTURE SEULE : aucun input de stock).
 *   (c) Décrément/ré-incrément REFLÉTÉS — on confirme la commande témoin (vrai hook,
 *       40.5) : la somme passe de 10 à 7 ; on l'annule : elle revient à 10.
 *   (d) Repli — un produit SANS stock de variante ne porte aucune mention « variante ».
 *
 * 🚨 Toute assertion d'absence s'adosse à une PRÉSENCE mesurée dans le même état
 * (leçon 31.5/27) : « Bleu est en rupture » n'a de sens que parce que « Rouge ne
 * l'est pas » est vérifié à côté, et « la somme vaut 7 » n'a de sens qu'après avoir
 * mesuré 10 juste avant. Les transitions de statut passent par le seed (docker wp
 * eval), comme run.sh — jamais une vraie API.
 *
 * Fixtures : scripts/recette-seed-variante.php (produits marqués + commande témoin,
 * purgés par le cleanup). IDs/URL via AOD_P40_ALPHA_URL / _ALPHA_PID / _REPLI_PID.
 */
'use strict';

const { execSync } = require( 'child_process' );
const path = require( 'path' );
const { launch, login, scanPhp, sectionRendue, BASE } = require( './lib' );

const ALPHA_URL = process.env.AOD_P40_ALPHA_URL || '';
const ALPHA_PID = process.env.AOD_P40_ALPHA_PID || '';
const REPLI_PID = process.env.AOD_P40_REPLI_PID || '';
const REPO = path.resolve( __dirname, '..', '..' );

/** Bascule la commande témoin par le seed (comme run.sh), depuis l'hôte. */
function seed( mode ) {
	return execSync( 'docker compose exec -T wpcli wp eval-file /scripts/recette-seed-variante.php ' + mode, { cwd: REPO } ).toString().trim();
}

let fails = 0;
function ok( cond, label ) {
	console.log( ( cond ? '  ✅ ' : '  ❌ ' ) + label );
	if ( ! cond ) fails++;
}

/** Le nombre de stock affiché pour ALPHA dans la 2ᵉ cellule (« Stock actuel ») de sa ligne. */
async function alphaSum( page ) {
	return page.evaluate( ( pid ) => {
		const tr = document.querySelector( `tr[data-stock-row][data-product="${ pid }"]` );
		if ( ! tr ) { return null; }
		const cell = tr.querySelector( 'td:nth-child(2)' );
		const m = cell ? cell.textContent.match( /\d+/ ) : null;
		return m ? parseInt( m[ 0 ], 10 ) : null;
	}, ALPHA_PID );
}

async function gotoStock( page ) {
	await page.goto( `${ BASE }/gestion/stock`, { waitUntil: 'networkidle' } );
	return sectionRendue( page );
}

( async () => {
	if ( ! ALPHA_URL || ! ALPHA_PID || ! REPLI_PID ) {
		console.log( '  ❌ Fixtures absentes (AOD_P40_ALPHA_URL / _ALPHA_PID / _REPLI_PID) — seed manquant ?' );
		process.exit( 1 );
	}

	const { browser, page, console_ } = await launch( { width: 390, height: 844 } ); // mobile d'abord

	/* --- (a) PUBLIC : rupture grisée, disponible sélectionnable ------------ */
	await page.goto( ALPHA_URL, { waitUntil: 'load' } );
	const pubPhp = scanPhp( await page.content() );
	ok( 0 === pubPhp.length, 'Public — aucune erreur PHP sur la fiche' + ( pubPhp.length ? ` (${ pubPhp[ 0 ] })` : '' ) );

	const bleu = await page.evaluate( () => {
		const inp = document.querySelector( '.aod-cod__opts input[type="radio"][value="Bleu"]' );
		if ( ! inp ) { return { present: false }; }
		const label = inp.closest( 'label' );
		return {
			present: true,
			disabled: inp.disabled,
			rupture: !! ( label && label.classList.contains( 'aod-cod__opt--rupture' ) ),
			badge: !! ( label && label.querySelector( '.aod-cod__opt-rupture' ) ),
		};
	} );
	const rouge = await page.evaluate( () => {
		const inp = document.querySelector( '.aod-cod__opts input[type="radio"][value="Rouge"]' );
		return inp ? { present: true, disabled: inp.disabled } : { present: false };
	} );

	// PRÉSENCE d'abord (sans quoi les assertions d'absence seraient vraies par vide).
	ok( rouge.present && bleu.present, 'Présence : les pastilles Rouge ET Bleu sont rendues' );
	ok( rouge.present && ! rouge.disabled, 'Rouge (stock 10) : sélectionnable (pas disabled)' );
	ok( bleu.rupture && bleu.badge, 'Bleu (stock 0) : grisée + badge « Rupture de stock »' );
	ok( bleu.disabled, 'Bleu (stock 0) : non sélectionnable (disabled)' );

	/* --- (b) /gestion Stock : somme + détail + lecture seule --------------- */
	await login( page );
	const rendue = await gotoStock( page );
	ok( 'stock' === rendue, `Routage : /gestion/stock rend bien « stock » (rendu : ${ rendue })` );
	const stockPhp = scanPhp( await page.content() );
	ok( 0 === stockPhp.length, 'Stock — aucune erreur PHP' + ( stockPhp.length ? ` (${ stockPhp[ 0 ] })` : '' ) );

	const alphaView = await page.evaluate( ( pid ) => {
		const tr = document.querySelector( `tr[data-stock-row][data-product="${ pid }"]` );
		if ( ! tr ) { return { present: false }; }
		return {
			present: true,
			hint: !! tr.querySelector( '.aod-cd-stock-variant-hint' ),
			editInput: !! tr.querySelector( 'input.aod-cd-stock-qty' ),
			text: tr.textContent.replace( /\s+/g, ' ' ),
		};
	}, ALPHA_PID );

	ok( alphaView.present, 'Présence : la ligne Stock du produit ALPHA existe' );
	const sumBase = await alphaSum( page );
	ok( 10 === sumBase, `Somme = 10 (Rouge 10 + Bleu 0 ; Vert non géré exclu) — lu : ${ sumBase }` );
	ok( alphaView.hint, 'ALPHA : mention « géré par variante » présente' );
	ok( ! alphaView.editInput, 'ALPHA : LECTURE SEULE — aucun champ de saisie de stock dans la ligne' );
	ok( /Rouge\D+10/.test( alphaView.text ) && /Bleu\D+0/.test( alphaView.text ), 'ALPHA : détail par variante « Rouge → 10 · Bleu → 0 »' );

	// (d) Repli : un produit sans stock de variante ne porte AUCUNE mention variante.
	const repliView = await page.evaluate( ( pid ) => {
		const tr = document.querySelector( `tr[data-stock-row][data-product="${ pid }"]` );
		return tr ? { present: true, hint: !! tr.querySelector( '.aod-cd-stock-variant-hint' ) } : { present: false };
	}, REPLI_PID );
	ok( repliView.present, 'Présence : la ligne Stock du produit REPLI existe' );
	ok( repliView.present && ! repliView.hint, 'Repli : REPLI ne porte PAS « géré par variante » (comportement d’avant 40.1)' );

	/* --- (c) Décrément à la confirmation, reflété dans la somme ------------ */
	const cf = seed( 'confirm' );
	ok( /P40CONFIRM=1/.test( cf ), 'Commande témoin confirmée (vrai hook de statut)' );
	await gotoStock( page );
	const sumAfterConfirm = await alphaSum( page );
	ok( 7 === sumAfterConfirm, `Après confirmation : somme 10 → 7 (qty 3 décrémentée) — lu : ${ sumAfterConfirm }` );

	/* --- (c) Ré-incrément à l'annulation, reflété dans la somme ------------ */
	const cx = seed( 'cancel' );
	ok( /P40CANCEL=1/.test( cx ), 'Commande témoin annulée (vrai hook de statut)' );
	await gotoStock( page );
	const sumAfterCancel = await alphaSum( page );
	ok( 10 === sumAfterCancel, `Après annulation : somme 7 → 10 (restituée) — lu : ${ sumAfterCancel }` );

	/* --- Console propre ---------------------------------------------------- */
	ok( 0 === console_.length, 'Console sans erreur ni warning' + ( console_.length ? ` (${ console_[ 0 ] })` : '' ) );

	await browser.close();
	console.log( fails ? `\n  ❌ VARIANTE : ${ fails } échec(s)` : '  ✅ VARIANTE : rupture, somme, décrément/ré-incrément — tout tient au navigateur.' );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => { console.error( e ); process.exit( 2 ); } );
