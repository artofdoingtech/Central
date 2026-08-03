<?php
/**
 * Panier COD multi-produits — le chemin de l'argent (Phase 35.2).
 *
 * `handle_submit()` termine le processus (`wp_send_json_*`) : il est
 * inappelable d'ici. Ce cliquet éprouve donc les deux méthodes extraites
 * exprès pour être jugées — `build_order_lines()` et `price_lines()` — par
 * réflexion (AOD_Test::call), sur des produits SEMÉS par le test et purgés.
 *
 *   R1 · RÉTRO-COMPAT. Un payload d'aujourd'hui (items sans product_id) donne
 *        la formule historique à l'octet : lot du groupe ÷ qty + supplément.
 *   R2 · GROUPES INDÉPENDANTS. Deux produits = deux tarifications : l'offre
 *        de l'un ne déteint jamais sur l'autre, ni sur sa quantité de lot.
 *   R3 · GARDE TOGGLE. Panier coupé, un item visant un autre produit que la
 *        racine est REJETÉ (erreur), jamais re-routé ni ignoré.
 *   R4 · PAYLOAD HOSTILE. Un product_id inconnu ou non publiable est une
 *        erreur explicite — le pire des replis serait de re-tarifer la racine.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Panier COD 35.2 — fixtures' );

$aod_pc_form = AOD_COD_Form::instance();

// Produit A : 1000 DZD, offre « 2 pour 1800 », une section d'options
// (S : +0, L : +100). Produit B : 500 DZD, nu. Produit C : brouillon (hostile).
$aod_pc_a = new WC_Product_Simple();
$aod_pc_a->set_name( 'Recette 35.2 — produit A' );
$aod_pc_a->set_regular_price( '1000' );
$aod_pc_a->set_status( 'publish' );
$aod_pc_a->save();
$aod_pc_a->update_meta_data( '_aod_offers', array( array( 'qty' => 2, 'price' => 1800 ) ) );
$aod_pc_a->update_meta_data( '_aod_options', array(
	array(
		'label'  => 'Taille',
		'values' => array(
			array( 'name' => 'S', 'price' => 0 ),
			array( 'name' => 'L', 'price' => 100 ),
		),
	),
) );
$aod_pc_a->save();

$aod_pc_b = new WC_Product_Simple();
$aod_pc_b->set_name( 'Recette 35.2 — produit B' );
$aod_pc_b->set_regular_price( '500' );
$aod_pc_b->set_status( 'publish' );
$aod_pc_b->save();

$aod_pc_c = new WC_Product_Simple();
$aod_pc_c->set_name( 'Recette 35.2 — produit C (brouillon)' );
$aod_pc_c->set_regular_price( '300' );
$aod_pc_c->set_status( 'draft' );
$aod_pc_c->save();

$aod_pc_ida = $aod_pc_a->get_id();
$aod_pc_idb = $aod_pc_b->get_id();
$aod_pc_idc = $aod_pc_c->get_id();
// On relit la racine par l'API (caches WC posés comme en production).
$aod_pc_root = wc_get_product( $aod_pc_ida );

AOD_Test::ok( $aod_pc_ida && $aod_pc_idb && $aod_pc_idc, 'Les 3 produits témoins sont semés' );

// L'option est un réglage RÉEL du clone : on capture, on restaure à la fin.
$aod_pc_opt_orig = get_option( 'aod_cod_cart', null );

try {
	/* --- R1 · rétro-compat : payload mono-produit, formule historique ------ */
	AOD_Test::group( 'Panier 35.2 — R1 : le payload d’hier donne les prix d’hier' );

	delete_option( 'aod_cod_cart' ); // Panier coupé : le cas de TOUS les clones actuels.
	$aod_pc_err   = array();
	$aod_pc_lines = AOD_Test::call( $aod_pc_form, 'build_order_lines', array(
		array(
			array( 'opt' => array( 'S' ), 'qty' => 1 ),
			array( 'opt' => array( 'L' ), 'qty' => 1 ),
		),
		$aod_pc_root,
		&$aod_pc_err,
	) );
	$aod_pc_lines = AOD_Test::call( $aod_pc_form, 'price_lines', array( $aod_pc_lines ) );

	AOD_Test::eq( array(), $aod_pc_err, 'R1 — aucun refus sur le payload historique' );
	AOD_Test::eq( 2, count( $aod_pc_lines ), 'R1 — 2 combinaisons = 2 lignes' );
	// Offre « 2 pour 1800 » sur qty cumulée 2 → 900/unité ; L porte +100.
	AOD_Test::eq( 900.0, (float) $aod_pc_lines[0]['line_total'], 'R1 — ligne S : 900 (lot 1800 ÷ 2)' );
	AOD_Test::eq( 1000.0, (float) $aod_pc_lines[1]['line_total'], 'R1 — ligne L : 900 + 100 de supplément' );
	AOD_Test::eq( $aod_pc_ida, $aod_pc_lines[0]['pid'], 'R1 — sans product_id, la ligne retombe sur la racine' );

	/* --- R2 · groupes indépendants (panier ON) ----------------------------- */
	AOD_Test::group( 'Panier 35.2 — R2 : deux produits, deux tarifications étanches' );

	update_option( 'aod_cod_cart', '1' );
	$aod_pc_err   = array();
	$aod_pc_lines = AOD_Test::call( $aod_pc_form, 'build_order_lines', array(
		array(
			array( 'opt' => array( 'S' ), 'qty' => 2 ),                        // A ×2 → son offre.
			array( 'product_id' => $aod_pc_idb, 'qty' => 1 ),                  // B ×1 → plein tarif.
		),
		$aod_pc_root,
		&$aod_pc_err,
	) );
	$aod_pc_lines = AOD_Test::call( $aod_pc_form, 'price_lines', array( $aod_pc_lines ) );

	AOD_Test::eq( array(), $aod_pc_err, 'R2 — payload 2 produits accepté (panier actif)' );
	AOD_Test::eq( 2, count( $aod_pc_lines ), 'R2 — 2 lignes' );
	AOD_Test::eq( 1800.0, (float) $aod_pc_lines[0]['line_total'], 'R2 — A ×2 : SON offre de lot (1800)' );
	AOD_Test::eq( 500.0, (float) $aod_pc_lines[1]['line_total'], 'R2 — B ×1 : plein tarif, insensible à l’offre de A' );
	AOD_Test::eq( $aod_pc_idb, $aod_pc_lines[1]['pid'], 'R2 — la ligne B porte bien SON produit' );

	// Contre-épreuve d'étanchéité : 1×A + 1×B ne doivent PAS déclencher
	// l'offre « 2 » de A — la quantité de lot se compte PAR produit.
	$aod_pc_err   = array();
	$aod_pc_lines = AOD_Test::call( $aod_pc_form, 'price_lines', array(
		AOD_Test::call( $aod_pc_form, 'build_order_lines', array(
			array(
				array( 'opt' => array( 'S' ), 'qty' => 1 ),
				array( 'product_id' => $aod_pc_idb, 'qty' => 1 ),
			),
			$aod_pc_root,
			&$aod_pc_err,
		) ),
	) );
	AOD_Test::eq( 1000.0, (float) $aod_pc_lines[0]['line_total'], 'R2 — 1×A + 1×B : A au plein tarif (l’offre exige 2 unités DE A)' );

	/* --- R3 · garde toggle : panier coupé, pas de multi-produits ----------- */
	AOD_Test::group( 'Panier 35.2 — R3 : toggle OFF = la surface serveur d’avant' );

	update_option( 'aod_cod_cart', '0' );
	$aod_pc_err   = array();
	$aod_pc_lines = AOD_Test::call( $aod_pc_form, 'build_order_lines', array(
		array(
			array( 'opt' => array( 'S' ), 'qty' => 1 ),
			array( 'product_id' => $aod_pc_idb, 'qty' => 1 ),
		),
		$aod_pc_root,
		&$aod_pc_err,
	) );
	AOD_Test::ok( ! empty( $aod_pc_err ), 'R3 — 🔴 l’item étranger est REFUSÉ quand le panier est coupé' );
	$aod_pc_pids = wp_list_pluck( $aod_pc_lines, 'pid' );
	AOD_Test::ok( ! in_array( $aod_pc_idb, $aod_pc_pids, true ), 'R3 — aucune ligne du produit étranger n’est construite' );
	AOD_Test::ok( in_array( $aod_pc_ida, $aod_pc_pids, true ), 'R3 — ANCRAGE : la ligne racine, elle, existe (le refus est ciblé, pas global)' );

	/* --- R4 · payload hostile ---------------------------------------------- */
	AOD_Test::group( 'Panier 35.2 — R4 : un produit inconnu ou non publiable est une erreur' );

	update_option( 'aod_cod_cart', '1' );
	foreach ( array( 'inconnu' => 999999999, 'brouillon' => $aod_pc_idc ) as $aod_pc_cas => $aod_pc_pid ) {
		$aod_pc_err   = array();
		$aod_pc_lines = AOD_Test::call( $aod_pc_form, 'build_order_lines', array(
			array( array( 'product_id' => $aod_pc_pid, 'qty' => 1 ) ),
			$aod_pc_root,
			&$aod_pc_err,
		) );
		AOD_Test::ok( ! empty( $aod_pc_err ), "R4 — product_id {$aod_pc_cas} : erreur explicite" );
		AOD_Test::eq( 0, count( $aod_pc_lines ), "R4 — product_id {$aod_pc_cas} : zéro ligne (jamais de repli sur la racine)" );
	}
} finally {
	// Restauration : l'option du clone et la purge des produits témoins.
	if ( null === $aod_pc_opt_orig ) {
		delete_option( 'aod_cod_cart' );
	} else {
		update_option( 'aod_cod_cart', $aod_pc_opt_orig );
	}
	foreach ( array( $aod_pc_ida, $aod_pc_idb, $aod_pc_idc ) as $aod_pc_del ) {
		if ( $aod_pc_del ) {
			wp_delete_post( $aod_pc_del, true );
		}
	}
}

AOD_Test::group( 'Panier 35.2 — aucun résidu' );
AOD_Test::ok( null === wc_get_product( $aod_pc_ida ) || false === wc_get_product( $aod_pc_ida ), 'Les produits témoins sont purgés' );
AOD_Test::eq( $aod_pc_opt_orig, get_option( 'aod_cod_cart', null ), 'L’option aod_cod_cart est revenue à son état d’origine' );
