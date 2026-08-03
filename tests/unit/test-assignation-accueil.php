<?php
/**
 * Assignation des commandes aux confirmateurs — cumul dans les stats de
 * l'Accueil (Phase 42.3bis, scindée de 42.3 : `AOD_CD_Section_Home` a son
 * propre mécanisme de périmètre, séparé de `scoped_orders()`).
 *
 * Couvre le PIÈGE CENTRAL de la story : trois sites, trois traitements.
 *   - `count_status()` (tuile « en attente » de `render_todo()`) et
 *     `delivery_data()` (onglet Livraison) DOIVENT filtrer par assignation.
 *   - `confirmation_data()` (onglet Confirmation) NE DOIT PAS — elle ramène
 *     volontairement les lignes de TOUS les confirmateurs (par
 *     `_aod_confirmed_by`, historique) et restreint au RENDU, jamais à la
 *     requête. Un confirmateur ayant confirmé une commande PUIS réassignée à
 *     un autre doit rester visible dans ses données brutes.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Assignation 42.3bis — préparation du contexte' );

$aod_aa_socle = class_exists( 'AOD_CD_Section_Home' ) && class_exists( 'AOD_CD_Roles' ) && class_exists( 'WC_Order' );
AOD_Test::ok( $aod_aa_socle, 'Socle présent (AOD_CD_Section_Home + AOD_CD_Roles + WooCommerce)' );

if ( ! $aod_aa_socle ) {
	return;
}

$aod_aa_user_avant = get_current_user_id();
$home                = AOD_CD_Section_Home::instance();

/**
 * `user_scope()`/`assignment_scope()` sont mémoïsées PAR INSTANCE sur ce
 * singleton — même piège que 42.3 (`AOD_CD_Section_Orders`). Reset par
 * réflexion entre chaque utilisateur simulé.
 */
$aod_aa_reset_scope_cache = function ( $obj ) {
	$ref = new ReflectionObject( $obj );
	foreach ( array( 'user_scope', 'assignment_scope' ) as $prop ) {
		if ( $ref->hasProperty( $prop ) ) {
			$rp = $ref->getProperty( $prop );
			$rp->setAccessible( true );
			$rp->setValue( $obj, null );
		}
	}
};

$aod_aa_make_confirmer = function ( $login, $has_cap ) {
	$uid = wp_insert_user( array(
		'user_login' => $login,
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => $login . '@example.invalid',
		'role'       => AOD_CD_ROLE_CONFIRMER,
	) );
	if ( is_wp_error( $uid ) ) {
		return 0;
	}
	if ( $has_cap ) {
		( new WP_User( (int) $uid ) )->add_cap( 'aod_cap_confirm_orders' );
	}
	return (int) $uid;
};

$aod_aa_make_product = function ( $name ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_status( 'publish' );
	return $p->save();
};

/**
 * Supprime VRAIMENT une commande — jamais `wp_delete_post()` sur ce site en
 * HPOS (cf. TEMPLATE.md §3) : il ne détruit que le post-placeholder
 * cosmétique, jamais la ligne réelle de `wp_wc_orders`, qui survit en
 * silence au nettoyage (patron `test-assignation-commandes.php`, 42.7).
 *
 * @param WC_Order|int $order_or_id
 */
$aod_aa_del_order = function ( $order_or_id ) {
	$o = ( $order_or_id instanceof WC_Order ) ? $order_or_id : wc_get_order( $order_or_id );
	if ( $o instanceof WC_Order ) {
		$o->delete( true );
	}
};

$confA = $aod_aa_make_confirmer( 'aod_test_42_3bis_a', true );
$confB = $aod_aa_make_confirmer( 'aod_test_42_3bis_b', true );
$confC = $aod_aa_make_confirmer( 'aod_test_42_3bis_c', false ); // SANS aod_cap_confirm_orders.
$pid   = $aod_aa_make_product( 'P42.3bis produit' );

AOD_Test::ok( $confA && $confB && $confC && $pid, 'Fixtures créées (3 confirmateurs, 1 produit)' );

/* =========================================================================
 * assignment_scope() — même garde que AOD_CD_Section_Orders (42.3)
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.3bis — assignment_scope() : gouvernée par la capability' );

wp_set_current_user( $confA );
$aod_aa_reset_scope_cache( $home );
AOD_Test::eq( $confA, AOD_Test::call( $home, 'assignment_scope' ), 'Confirmateur avec aod_cap_confirm_orders : renvoie son ID' );

wp_set_current_user( $confC );
$aod_aa_reset_scope_cache( $home );
AOD_Test::eq( 0, AOD_Test::call( $home, 'assignment_scope' ), '🔴 Confirmateur SANS aod_cap_confirm_orders : renvoie 0 (jamais filtré)' );

$aod_aa_gerants = get_users( array( 'role__in' => array( 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );
if ( $aod_aa_gerants ) {
	wp_set_current_user( (int) $aod_aa_gerants[0] );
	$aod_aa_reset_scope_cache( $home );
	AOD_Test::eq( 0, AOD_Test::call( $home, 'assignment_scope' ), 'Gérant : renvoie 0 (jamais filtré)' );
}

/* =========================================================================
 * count_status() — mécanisme de la tuile « en attente » de render_todo()
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.3bis — count_status() : tuile « en attente »' );

$aod_aa_make_order = function ( $pid, $status, $assigned_to = 0, $confirmed_by = 0, $shipped = false ) {
	$order = new WC_Order();
	$item  = new WC_Order_Item_Product();
	$item->set_product( wc_get_product( $pid ) );
	$item->set_quantity( 1 );
	$order->add_item( $item );
	if ( $assigned_to ) {
		$order->update_meta_data( '_aod_assigned_confirmer', (int) $assigned_to );
	}
	if ( $confirmed_by ) {
		$order->update_meta_data( '_aod_confirmed_by', (int) $confirmed_by );
	}
	if ( $shipped ) {
		$order->update_meta_data( '_aod_ship_tracking', 'TRACK-' . wp_generate_password( 6, false ) );
		$order->update_meta_data( '_aod_ship_status', 'in_transit' );
	}
	$order->set_status( $status );
	$order->save();
	return $order->get_id();
};

$aod_aa_oA = $aod_aa_make_order( $pid, 'pending', $confA );
$aod_aa_oB = $aod_aa_make_order( $pid, 'pending', $confB );

try {
	wp_set_current_user( $confA );
	$aod_aa_reset_scope_cache( $home );
	$aod_aa_cA = AOD_Test::call( $home, 'count_status', array( 'pending', '', 0, 0, array(), $confA ) );
	AOD_Test::eq( 1, $aod_aa_cA, 'confA : count_status("pending", assigné=confA) ne compte QUE sa commande (pas celle de B)' );

	wp_set_current_user( $confB );
	$aod_aa_reset_scope_cache( $home );
	$aod_aa_cB = AOD_Test::call( $home, 'count_status', array( 'pending', '', 0, 0, array(), $confB ) );
	AOD_Test::eq( 1, $aod_aa_cB, 'confB : idem, ne compte QUE sa commande' );

	$aod_aa_cNone = AOD_Test::call( $home, 'count_status', array( 'pending', '', 0, 0, array(), 0 ) );
	AOD_Test::ok( $aod_aa_cNone >= 2, '🔴 Sans filtre d’assignation (0) : compte les DEUX (comportement d’avant la story)' );
} finally {
	$aod_aa_del_order( $aod_aa_oA );
	$aod_aa_del_order( $aod_aa_oB );
}

/* =========================================================================
 * delivery_data() — onglet Livraison
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.3bis — delivery_data() : isolation par assignation' );

$aod_aa_range   = array( 'from' => gmdate( 'Y-m-d', strtotime( '-1 year' ) ), 'to' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ) );
$aod_aa_filters = array( 'produit' => 0, 'confirmateur' => 0 );

$aod_aa_shipA = $aod_aa_make_order( $pid, 'processing', $confA, 0, true );
$aod_aa_shipB = $aod_aa_make_order( $pid, 'processing', $confB, 0, true );

try {
	wp_set_current_user( $confA );
	$aod_aa_reset_scope_cache( $home );
	$aod_aa_dA = AOD_Test::call( $home, 'delivery_data', array( $aod_aa_range, $aod_aa_filters ) );
	AOD_Test::eq( 1, $aod_aa_dA['total'], 'confA : delivery_data()[total] ne compte QUE son colis assigné' );

	wp_set_current_user( $confB );
	$aod_aa_reset_scope_cache( $home );
	$aod_aa_dB = AOD_Test::call( $home, 'delivery_data', array( $aod_aa_range, $aod_aa_filters ) );
	AOD_Test::eq( 1, $aod_aa_dB['total'], '🔴 confB : idem, ne partage PAS le cache/résultat de confA (clé de cache correcte)' );
} finally {
	$aod_aa_del_order( $aod_aa_shipA );
	$aod_aa_del_order( $aod_aa_shipB );
}

/* =========================================================================
 * confirmation_data() — RED-PROOF INVERSÉ : ne doit JAMAIS filtrer par
 * assignation. Une commande confirmée par confA puis RÉASSIGNÉE à confB
 * doit rester visible dans la ligne de confA.
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.3bis — confirmation_data() : jamais filtrée par assignation (à dessein)' );

$aod_aa_oReassigned = $aod_aa_make_order( $pid, 'completed', $confB, $confA ); // confirmé par A, assigné à B.

try {
	wp_set_current_user( $confA );
	$aod_aa_reset_scope_cache( $home );
	$aod_aa_conf = AOD_Test::call( $home, 'confirmation_data', array( $aod_aa_range, array( 'produit' => 0 ) ) );
	AOD_Test::ok(
		isset( $aod_aa_conf['rows'][ $confA ] ) && (int) $aod_aa_conf['rows'][ $confA ]['n'] >= 1,
		'🔴 confirmation_data() : la commande confirmée par A apparaît TOUJOURS, même réassignée à B entretemps'
	);
} finally {
	$aod_aa_del_order( $aod_aa_oReassigned );
}

wp_set_current_user( 0 );

/* =========================================================================
 * Nettoyage
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.3bis — nettoyage' );

wp_set_current_user( $aod_aa_user_avant );

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $confA, $confB, $confC ) as $uid ) {
	if ( $uid ) {
		wp_delete_user( $uid );
	}
}
wp_delete_post( $pid, true );

AOD_Test::ok( ! get_user_by( 'login', 'aod_test_42_3bis_a' ), 'Les confirmateurs de test sont supprimés' );
AOD_Test::ok( ! wc_get_product( $pid ), 'Le produit de test est supprimé' );
