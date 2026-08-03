<?php
/**
 * Fixtures de la porte `variante` (clôture Phase 40) — create | confirm | cancel | cleanup.
 *
 * create : sème deux produits publiés marqués `_aod_recette_p40` :
 *   · ALPHA — axe « Couleur » à stock GÉRÉ (Rouge 10, Bleu 0 = rupture, Vert '' non géré) ;
 *   · REPLI — axe « Taille » SANS stock de variante (repli : comportement d'avant 40.1).
 *   Plus une commande témoin (ALPHA, « Couleur=Rouge », qty 3), statut `pending` — PAS
 *   confirmée : le décrément n'a pas encore eu lieu (la somme part de 10). Son ID est
 *   rangé dans l'option `_aod_recette_p40_oid` pour les modes confirm/cancel.
 *   Sort ALPHA_PID/ALPHA_URL, REPLI_PID, ORDER_ID.
 *
 * confirm : passe la commande témoin en `aod-confirmed` → déclenche le vrai hook de
 *   décrément (40.5). Rouge 10 → 7.
 * cancel  : passe la commande témoin en `cancelled` → ré-incrément. Rouge 7 → 10.
 *
 * cleanup : purge la commande témoin, les produits marqués, et l'option de suivi.
 *
 * Usage : wp eval-file /scripts/recette-seed-variante.php <create|confirm|cancel|cleanup>
 *
 * @package AOD_Recette
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aod_p40_mode = isset( $args[0] ) ? $args[0] : 'create';

/** Retrouve la commande témoin (ID rangé à la création). */
$aod_p40_order = function () {
	$oid = (int) get_option( '_aod_recette_p40_oid', 0 );
	return $oid ? wc_get_order( $oid ) : false;
};

if ( 'create' === $aod_p40_mode ) {
	// ALPHA — stock géré à la variante (mono-axe Couleur).
	$aod_p40_alpha = new WC_Product_Simple();
	$aod_p40_alpha->set_name( 'RecetteVar Alpha' );
	$aod_p40_alpha->set_regular_price( '1000' );
	$aod_p40_alpha->set_status( 'publish' );
	$aod_p40_alpha->save();
	$aod_p40_alpha->update_meta_data( '_aod_options', array(
		array( 'label' => 'Couleur', 'visual' => false, 'values' => array(
			array( 'name' => 'Rouge', 'name_ar' => 'أحمر', 'price' => '', 'image_id' => 0, 'hex' => '', 'stock' => '10' ),
			array( 'name' => 'Bleu', 'name_ar' => 'أزرق', 'price' => '', 'image_id' => 0, 'hex' => '', 'stock' => '0' ),
			array( 'name' => 'Vert', 'name_ar' => 'أخضر', 'price' => '', 'image_id' => 0, 'hex' => '', 'stock' => '' ),
		) ),
	) );
	$aod_p40_alpha->update_meta_data( '_aod_recette_p40', '1' );
	$aod_p40_alpha->save();
	$aod_p40_alpha_id = $aod_p40_alpha->get_id();

	// REPLI — variantes SANS stock géré (produit d'avant 40.1 dans l'esprit).
	$aod_p40_repli = new WC_Product_Simple();
	$aod_p40_repli->set_name( 'RecetteVar Repli' );
	$aod_p40_repli->set_regular_price( '500' );
	$aod_p40_repli->set_status( 'publish' );
	$aod_p40_repli->save();
	$aod_p40_repli->update_meta_data( '_aod_options', array(
		array( 'label' => 'Taille', 'visual' => false, 'values' => array(
			array( 'name' => 'M', 'name_ar' => 'وسط', 'price' => '', 'image_id' => 0, 'hex' => '', 'stock' => '' ),
			array( 'name' => 'L', 'name_ar' => 'كبير', 'price' => '', 'image_id' => 0, 'hex' => '', 'stock' => '' ),
		) ),
	) );
	$aod_p40_repli->update_meta_data( '_aod_recette_p40', '1' );
	$aod_p40_repli->save();
	$aod_p40_repli_id = $aod_p40_repli->get_id();

	// Commande témoin : ALPHA, Couleur=Rouge, qty 3, statut pending (non confirmée).
	$aod_p40_o  = new WC_Order();
	$aod_p40_it = new WC_Order_Item_Product();
	$aod_p40_it->set_product( wc_get_product( $aod_p40_alpha_id ) );
	$aod_p40_it->set_quantity( 3 );
	$aod_p40_it->add_meta_data( 'Couleur', 'Rouge', true ); // label + name FR canonique.
	$aod_p40_o->add_item( $aod_p40_it );
	$aod_p40_o->update_meta_data( '_aod_recette_p40', '1' );
	$aod_p40_o->save();
	update_option( '_aod_recette_p40_oid', $aod_p40_o->get_id(), false );

	echo "P40=1\n";
	echo 'ALPHA_PID=' . $aod_p40_alpha_id . "\n";
	echo 'ALPHA_URL=' . get_permalink( $aod_p40_alpha_id ) . "\n";
	echo 'REPLI_PID=' . $aod_p40_repli_id . "\n";
	echo 'ORDER_ID=' . $aod_p40_o->get_id() . "\n";
	return;
}

if ( 'confirm' === $aod_p40_mode ) {
	$o = $aod_p40_order();
	if ( $o ) {
		$o->set_status( 'aod-confirmed' ); // déclenche le hook de décrément (40.5).
		$o->save();
		echo "P40CONFIRM=1\n";
	} else {
		echo "P40CONFIRM=0\n";
	}
	return;
}

if ( 'cancel' === $aod_p40_mode ) {
	$o = $aod_p40_order();
	if ( $o ) {
		$o->set_status( 'cancelled' ); // déclenche le ré-incrément (40.5).
		$o->save();
		echo "P40CANCEL=1\n";
	} else {
		echo "P40CANCEL=0\n";
	}
	return;
}

/* --- cleanup ------------------------------------------------------------- */

// 1) Commande témoin (avant les produits : sa ligne les référence).
$aod_p40_oid = (int) get_option( '_aod_recette_p40_oid', 0 );
if ( $aod_p40_oid ) {
	$o = wc_get_order( $aod_p40_oid );
	if ( $o ) {
		$o->delete( true );
	}
	delete_option( '_aod_recette_p40_oid' );
}

// 2) Produits marqués.
$aod_p40_posts = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => 'any',
	'posts_per_page' => 20,
	'meta_key'       => '_aod_recette_p40',
	'meta_value'     => '1',
	'fields'         => 'ids',
) );
foreach ( $aod_p40_posts as $aod_p40_id ) {
	wp_delete_post( $aod_p40_id, true );
}

echo "P40CLEAN=1\n";
