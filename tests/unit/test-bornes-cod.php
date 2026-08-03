<?php
/**
 * Bornes serveur de la soumission COD — quantité, articles, stock Woo (Phase 44.1).
 *
 * 🚨 CE QUI ÉTAIT FAUX, ET POURQUOI AUCUN TEST NE POUVAIT LE VOIR. Le chemin
 * d'achat ne bornait la quantité que par le BAS (`max( 1, absint( $qty ) )`), et
 * sa seule vérification de disponibilité était `is_purchasable()` — dont le corps
 * est `exists() && statut publish && '' !== prix` (source WooCommerce 10.8.1,
 * `abstract-wc-product.php:1757`). Ni stock, ni backorders. Comme la commande naît
 * en `processing` et que Woo décrémente sur ce statut exact
 * (`wc-stock-functions.php:126`), un `items[0][qty]=100000` anonyme poussait le
 * stock d'un produit DANS LE NÉGATIF : produit « en rupture », boutique morte pour
 * les vrais acheteurs, sans qu'un centime ait été payé (c'est du COD).
 *
 * La garde JUMELLE existait pourtant depuis la 40.4, à quelques lignes de là —
 * pour les variantes maison. Elle n'avait jamais été portée au stock Woo du
 * produit. C'est le fil rouge de la Phase 44 : *un correctif qui ne cherche pas
 * ses jumeaux est un correctif à moitié fait.*
 *
 *   R1 · PLAFOND DE QUANTITÉ. Au plafond ça passe, au-dessus c'est REFUSÉ —
 *        jamais écrêté en silence (une commande que l'acheteur n'a pas voulue
 *        serait traitée comme réelle par le gérant).
 *   R2 · STOCK WOO. Stock géré insuffisant ⇒ refus ; backorders autorisés ⇒
 *        accepté ; stock non géré ⇒ jamais bloqué (cas nominal d'un clone neuf).
 *   R3 · PLAFOND D'ARTICLES. Un payload à N > MAX_ITEMS est écrêté, avec la
 *        constante DÉJÀ EXISTANTE du panier — pas une seconde.
 *   R4 · NON-RÉGRESSION 43.6. En mode TOLÉRANT, un produit en rupture rend
 *        toujours un montant : la colonne « Montant » des paniers abandonnés ne
 *        doit pas se vider parce qu'on a durci le chemin d'achat.
 *   R5 · NON-RÉGRESSION DU PRIX. Une commande nominale rend exactement le même
 *        total qu'avant : 44.1 ajoute des REFUS, jamais un calcul.
 *
 * `handle_submit()` termine le processus (`wp_send_json_*`) : il est inappelable
 * d'ici. On éprouve donc le funnel unique `build_order_lines()` par réflexion —
 * et c'est précisément pour cette raison que les bornes y ont été posées, plutôt
 * que dans `handle_submit()` où elles auraient été invérifiables.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Bornes COD 44.1 — fixtures' );

$aod_bc_form = AOD_COD_Form::instance();

/**
 * Sème un produit simple à 1000 DZD avec un régime de stock donné.
 *
 * @param string   $nom
 * @param bool     $manage
 * @param int|null $qty
 * @param string   $backorders
 * @return int
 */
$aod_bc_seed = function ( $nom, $manage, $qty = null, $backorders = 'no' ) {
	$p = new WC_Product_Simple();
	$p->set_name( $nom );
	$p->set_regular_price( '1000' );
	$p->set_status( 'publish' );
	$p->set_manage_stock( $manage );
	if ( $manage ) {
		$p->set_stock_quantity( (int) $qty );
		$p->set_backorders( $backorders );
	}
	$p->save();
	return $p->get_id();
};

// P1 : stock GÉRÉ à 3, backorders interdits — le cas qui portait le défaut.
$aod_bc_p1 = $aod_bc_seed( 'Recette 44.1 — stock géré 3', true, 3, 'no' );
// P2 : stock GÉRÉ à 0 mais backorders AUTORISÉS — doit rester commandable.
$aod_bc_p2 = $aod_bc_seed( 'Recette 44.1 — backorders', true, 0, 'yes' );
// P3 : stock NON géré — le cas nominal d'un clone neuf, jamais bloqué.
$aod_bc_p3 = $aod_bc_seed( 'Recette 44.1 — stock non géré', false );
// P4 : stock GÉRÉ à 0, backorders interdits — en rupture franche.
$aod_bc_p4 = $aod_bc_seed( 'Recette 44.1 — rupture', true, 0, 'no' );

$aod_bc_r1 = wc_get_product( $aod_bc_p1 );
$aod_bc_r3 = wc_get_product( $aod_bc_p3 );
$aod_bc_r4 = wc_get_product( $aod_bc_p4 );

AOD_Test::ok(
	$aod_bc_p1 && $aod_bc_p2 && $aod_bc_p3 && $aod_bc_p4,
	sprintf( 'TÉMOIN : 4 produits témoins semés (%d, %d, %d, %d)', $aod_bc_p1, $aod_bc_p2, $aod_bc_p3, $aod_bc_p4 )
);

// Le témoin qui rend le reste du fichier interprétable : si `is_purchasable()`
// se mettait un jour à regarder le stock, R2 deviendrait tautologique et
// verdirait sans rien prouver. On l'affirme donc explicitement.
AOD_Test::ok(
	$aod_bc_r4->is_purchasable(),
	'TÉMOIN : un produit EN RUPTURE reste `is_purchasable()` — c’est la lacune que R2 couvre',
	'`is_purchasable()` regarde désormais le stock : R2 ne prouve plus rien, la réécrire'
);

// L'option panier est un réglage RÉEL du clone : capturée, restaurée à la fin.
$aod_bc_opt = get_option( 'aod_cod_cart', null );

try {
	/* --- R0 · le helper partagé, éprouvé seul ----------------------------- */
	AOD_Test::group( 'Bornes COD 44.1 — R0 : `orderable_stock()`, la réponse unique' );

	AOD_Test::ok( AOD_COD_Form::orderable_stock( $aod_bc_r1, 3 ), 'R0 — stock 3, demande 3 : commandable' );
	AOD_Test::ok( ! AOD_COD_Form::orderable_stock( $aod_bc_r1, 4 ), 'R0 — stock 3, demande 4 : refusé' );
	AOD_Test::ok( AOD_COD_Form::orderable_stock( wc_get_product( $aod_bc_p2 ), 9999 ), 'R0 — backorders autorisés : commandable quelle que soit la quantité' );
	AOD_Test::ok( AOD_COD_Form::orderable_stock( $aod_bc_r3, 9999 ), 'R0 — stock NON géré : jamais bloqué' );
	AOD_Test::ok( ! AOD_COD_Form::orderable_stock( $aod_bc_r4, 1 ), 'R0 — rupture franche : refusé' );
	AOD_Test::ok( ! AOD_COD_Form::orderable_stock( null, 1 ), 'R0 — non-produit : refusé (jamais un fatal)' );

	/* --- R1 · plafond de quantité ----------------------------------------- */
	AOD_Test::group( 'Bornes COD 44.1 — R1 : la quantité a enfin une borne HAUTE' );

	delete_option( 'aod_cod_cart' ); // Panier coupé : le défaut de tout clone.

	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => AOD_COD_MAX_QTY_PER_LINE ) ),
		$aod_bc_r3,
		&$aod_bc_err,
	) );
	AOD_Test::eq( array(), $aod_bc_err, 'R1 — pile au plafond : accepté' );
	AOD_Test::eq( 1, count( $aod_bc_lines ), 'R1 — la ligne au plafond existe' );

	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => AOD_COD_MAX_QTY_PER_LINE + 1 ) ),
		$aod_bc_r3,
		&$aod_bc_err,
	) );
	AOD_Test::eq( 1, count( $aod_bc_err ), 'R1 — un cran au-dessus : UNE erreur' );
	AOD_Test::eq( array(), $aod_bc_lines, 'R1 — et AUCUNE ligne : on refuse, on n’écrête pas' );

	// Le témoin de la story : la valeur de l'attaque réelle.
	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => 100000 ) ),
		$aod_bc_r3,
		&$aod_bc_err,
	) );
	AOD_Test::eq( array(), $aod_bc_lines, 'R1 — TÉMOIN NOMMÉ : `qty=100000`, la charge exacte de l’audit, ne produit aucune ligne' );

	/* --- R2 · stock Woo ---------------------------------------------------- */
	AOD_Test::group( 'Bornes COD 44.1 — R2 : le stock WooCommerce est enfin regardé' );

	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => 3 ) ),
		$aod_bc_r1,
		&$aod_bc_err,
	) );
	AOD_Test::eq( array(), $aod_bc_err, 'R2 — stock 3, demande 3 : accepté' );

	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => 4 ) ),
		$aod_bc_r1,
		&$aod_bc_err,
	) );
	AOD_Test::eq( 1, count( $aod_bc_err ), 'R2 — stock 3, demande 4 : refusé' );
	AOD_Test::eq( array(), $aod_bc_lines, 'R2 — aucune ligne : le stock ne partira pas dans le négatif' );

	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => 20 ) ),
		wc_get_product( $aod_bc_p2 ),
		&$aod_bc_err,
	) );
	AOD_Test::eq( array(), $aod_bc_err, 'R2 — backorders autorisés : accepté malgré un stock à 0' );

	// 🚨 ORDRE DES BORNES, vérifié plutôt que supposé. La première écriture de ce
	// test demandait 500 unités ici : il a rougi sur le PLAFOND DE QUANTITÉ, pas sur
	// le stock. Les deux bornes sont indépendantes et la quantité passe en premier —
	// un backorder illimité n'ouvre donc PAS une quantité illimitée. C'est le bon
	// comportement, et il vaut d'être affirmé : sans cette assertion, un futur
	// déplacement des gardes le casserait en silence.
	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => AOD_COD_MAX_QTY_PER_LINE + 1 ) ),
		wc_get_product( $aod_bc_p2 ),
		&$aod_bc_err,
	) );
	AOD_Test::eq( array(), $aod_bc_lines, 'R2 — backorders illimités ≠ quantité illimitée : le plafond passe AVANT le stock' );

	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => 5 ) ),
		$aod_bc_r3,
		&$aod_bc_err,
	) );
	AOD_Test::eq( array(), $aod_bc_err, 'R2 — stock NON géré : jamais bloqué (aucune régression sur un clone neuf)' );

	/* --- R3 · plafond d'articles ------------------------------------------ */
	AOD_Test::group( 'Bornes COD 44.1 — R3 : le chemin qui ÉCRIT est borné comme celui qui LIT' );

	$aod_bc_max  = AOD_COD_Cart::MAX_ITEMS;
	$aod_bc_gros = array_fill( 0, $aod_bc_max + 25, array( 'qty' => 1 ) );
	$aod_bc_err  = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		$aod_bc_gros,
		$aod_bc_r3,
		&$aod_bc_err,
	) );
	AOD_Test::eq(
		$aod_bc_max,
		count( $aod_bc_lines ),
		sprintf( 'R3 — %d articles soumis, %d retenus (plafond du panier, PARTAGÉ)', count( $aod_bc_gros ), $aod_bc_max )
	);

	/* --- R4 · non-régression 43.6 (mode tolérant) -------------------------- */
	AOD_Test::group( 'Bornes COD 44.1 — R4 : la colonne « Montant » des abandons ne se vide pas' );

	// 🚨 QUANTITÉ MODESTE, ET C'EST TOUT L'ENJEU. La première écriture demandait
	// 9 999 : la contre-épreuve « en mode strict, c'est refusé » restait alors VERTE
	// même en désarmant la garde de stock — elle mesurait le plafond de quantité, pas
	// le stock. Elle passait pour la BONNE raison affichée et la MAUVAISE raison
	// réelle. Trouvé en forçant au rouge (DoD §C.2 pt 5), pas à la relecture. Avec
	// une quantité sous le plafond, seule la garde de stock peut refuser cette ligne.
	$aod_bc_err    = array();
	$aod_bc_approx = false;
	$aod_bc_lines  = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => 5 ) ),
		$aod_bc_r4,          // produit EN RUPTURE.
		&$aod_bc_err,
		true,                // mode tolérant (43.6).
		&$aod_bc_approx,
	) );
	AOD_Test::eq( 1, count( $aod_bc_lines ), 'R4 — en mode TOLÉRANT, un produit en rupture rend toujours une ligne' );
	AOD_Test::eq( array(), $aod_bc_err, 'R4 — et aucune erreur : estimer n’est pas commander' );

	// Contre-épreuve : le MÊME payload en mode strict doit, lui, être refusé — et
	// il ne peut l'être QUE par la garde de stock.
	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => 5 ) ),
		$aod_bc_r4,
		&$aod_bc_err,
	) );
	AOD_Test::eq( array(), $aod_bc_lines, 'R4 — CONTRE-ÉPREUVE : le même payload en mode STRICT est refusé (par le STOCK seul)' );

	/* --- R5 · non-régression du prix --------------------------------------- */
	AOD_Test::group( 'Bornes COD 44.1 — R5 : 44.1 ajoute des refus, jamais un calcul' );

	$aod_bc_err   = array();
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'build_order_lines', array(
		array( array( 'qty' => 2 ) ),
		$aod_bc_r3,
		&$aod_bc_err,
	) );
	$aod_bc_lines = AOD_Test::call( $aod_bc_form, 'price_lines', array( $aod_bc_lines ) );
	AOD_Test::eq( 2000.0, (float) $aod_bc_lines[0]['line_total'], 'R5 — 2 × 1000 DZD = 2000, au centime, comme avant' );
} finally {
	if ( null === $aod_bc_opt ) {
		delete_option( 'aod_cod_cart' );
	} else {
		update_option( 'aod_cod_cart', $aod_bc_opt );
	}
	foreach ( array( $aod_bc_p1, $aod_bc_p2, $aod_bc_p3, $aod_bc_p4 ) as $aod_bc_del ) {
		if ( $aod_bc_del ) {
			wp_delete_post( $aod_bc_del, true );
		}
	}
}

AOD_Test::group( 'Bornes COD 44.1 — aucun résidu' );
AOD_Test::ok(
	! wc_get_product( $aod_bc_p1 ) && ! wc_get_product( $aod_bc_p4 ),
	'Les produits témoins sont purgés'
);
AOD_Test::eq( $aod_bc_opt, get_option( 'aod_cod_cart', null ), 'L’option aod_cod_cart est revenue à son état d’origine' );
