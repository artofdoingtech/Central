<?php
/**
 * Fixtures de la porte `assignation` (clôture Phase 42) — create | cleanup.
 *
 * Usage : wp eval-file /scripts/recette-seed-assignation.php <create|cleanup>
 *
 * CE QU'ON SÈME :
 *   - 1 produit marqué (recette).
 *   - 2 confirmateurs marqués, ACTIFS, porteurs de `aod_cap_confirm_orders`,
 *     SANS restriction produit (patron `recette-seed-phase22.php` : mots de
 *     passe ENGENDRÉS à chaque exécution, jamais versionnés).
 *   - 5 commandes marquées, statut `pending` :
 *       · ASSIGNED_A     → assignée au confirmateur A.
 *       · ASSIGNED_B     → assignée au confirmateur B (MÊME produit que A —
 *         c'est la commande que A ne doit JAMAIS voir : preuve du cumul 42.3
 *         au navigateur, pas seulement en PHP).
 *       · UNASSIGNED     → non assignée (sélecteur « Non assignée », filtre).
 *       · BULK1 / BULK2  → non assignées (scénario d'assignation groupée).
 *
 * 🚨 PURGE CIBLÉE, JAMAIS PAR RÔLE NI STATUT (leçon D26/Phase 22) : un clone en
 * service porte de VRAIS confirmateurs et de VRAIES commandes. `cleanup` ne
 * touche QU'AUX comptes/commandes portant la méta AOD_P42_MARK, revérifiée
 * juste avant toute suppression.
 *
 * @package AOD_Recette
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'AOD_CD_Roles' ) ) {
	WP_CLI::error( 'Le plugin aod-client-dashboard n’est pas actif.' );
}

const AOD_P42_MARK  = '_aod_recette_p42';
const AOD_P42_LOGIN = 'aod_recette_conf42a';
const AOD_P42_LOGIN2 = 'aod_recette_conf42b';

/** Crée un confirmateur marqué, actif, porteur de aod_cap_confirm_orders. */
function aod_p42_creer_confirmateur( $login ) {
	$pass = wp_generate_password( 24, false );
	$uid  = wp_insert_user( array(
		'user_login'   => $login,
		'user_pass'    => $pass,
		'user_email'   => $login . '@example.invalid',
		'display_name' => 'Recette ' . $login,
		'role'         => AOD_CD_ROLE_CONFIRMER,
	) );
	if ( is_wp_error( $uid ) ) {
		WP_CLI::error( 'Création de « ' . $login . ' » impossible : ' . $uid->get_error_message() );
	}
	update_user_meta( $uid, AOD_P42_MARK, '1' );
	$user = new WP_User( $uid );
	$user->add_cap( 'aod_cap_confirm_orders' );
	if ( ! $user->has_cap( 'aod_cap_confirm_orders' ) || ! $user->has_cap( AOD_CD_CAP ) ) {
		WP_CLI::error( '« ' . $login . ' » : capability posée sans effet (seed cassé).' );
	}
	return array( 'id' => (int) $uid, 'pass' => $pass );
}

/** Commande à une ligne (produit $pid, qty 1), statut pending, éventuellement assignée. */
function aod_p42_creer_commande( $pid, $assigned_to = 0 ) {
	$order = new WC_Order();
	$item  = new WC_Order_Item_Product();
	$item->set_product( wc_get_product( $pid ) );
	$item->set_quantity( 1 );
	$order->add_item( $item );
	if ( $assigned_to ) {
		$order->update_meta_data( '_aod_assigned_confirmer', (int) $assigned_to );
	}
	$order->update_meta_data( AOD_P42_MARK, '1' );
	$order->set_status( 'pending' );
	$order->save();
	return $order->get_id();
}

/** Purge idempotente : ne supprime QUE les comptes/commandes marqués. */
function aod_p42_cleanup() {
	require_once ABSPATH . 'wp-admin/includes/user.php';

	// 🚨 HPOS-safe : `get_posts( post_type => 'shop_order' )` ne voit RIEN sur ce
	// clone (les commandes ne sont plus des posts). `wc_get_orders()` couvre les
	// deux modes de stockage (patron déjà établi dans ce dépôt, ex. count_unassigned()).
	$orders = wc_get_orders( array(
		'type'       => 'shop_order',
		'limit'      => 20,
		'status'     => 'any',
		'return'     => 'ids',
		'meta_query' => array( array( 'key' => AOD_P42_MARK, 'value' => '1' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	foreach ( $orders as $oid ) {
		$o = wc_get_order( $oid );
		if ( $o && '1' === (string) $o->get_meta( AOD_P42_MARK ) ) {
			$o->delete( true );
		}
	}

	$products = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'any',
		'posts_per_page' => 5,
		'meta_key'       => AOD_P42_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery
		'fields'         => 'ids',
	) );
	foreach ( $products as $pid ) {
		wp_delete_post( $pid, true );
	}

	$users = get_users( array(
		'meta_key'   => AOD_P42_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value' => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery
		'number'     => -1,
		'fields'     => 'all',
	) );
	$n = 0;
	foreach ( $users as $u ) {
		if ( '1' !== (string) get_user_meta( $u->ID, AOD_P42_MARK, true ) ) {
			continue; // Double contrôle avant un geste irréversible.
		}
		if ( wp_delete_user( $u->ID ) ) {
			++$n;
		}
	}

	return array( 'orders' => count( $orders ), 'products' => count( $products ), 'users' => $n );
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	$r = aod_p42_cleanup();
	WP_CLI::log( 'P42CLEAN=1' );
	return;
}
if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-assignation.php create|cleanup' );
}

// Rejouable : purge d'abord tout reliquat d'une exécution interrompue.
aod_p42_cleanup();

$product = new WC_Product_Simple();
$product->set_name( 'RecetteAssignation Produit' );
$product->set_regular_price( '1000' );
$product->set_status( 'publish' );
$product->save();
$product->update_meta_data( AOD_P42_MARK, '1' );
$product->save();
$pid = $product->get_id();

$a = aod_p42_creer_confirmateur( AOD_P42_LOGIN );
$b = aod_p42_creer_confirmateur( AOD_P42_LOGIN2 );

$oid_assigned_a = aod_p42_creer_commande( $pid, $a['id'] );
$oid_assigned_b = aod_p42_creer_commande( $pid, $b['id'] );
$oid_unassigned = aod_p42_creer_commande( $pid, 0 );
$oid_bulk1      = aod_p42_creer_commande( $pid, 0 );
$oid_bulk2      = aod_p42_creer_commande( $pid, 0 );

WP_CLI::log( 'P42=1' );
WP_CLI::log( 'P42LOGIN=' . AOD_P42_LOGIN );
WP_CLI::log( 'P42PASS=' . $a['pass'] );
// ID numérique du compte (43.2) — distinct du login : le filtre confirmateur
// de Commandes/Suivi est un <select> dont les <option> portent l'ID, pas le
// login. Sans lui, la porte `assignation` ne peut pas construire l'URL
// `?confirmateur=<id>` sans redevoir requêter l'utilisateur elle-même.
WP_CLI::log( 'P42_UID_A=' . $a['id'] );
WP_CLI::log( 'P42LOGIN2=' . AOD_P42_LOGIN2 );
WP_CLI::log( 'P42PASS2=' . $b['pass'] );
WP_CLI::log( 'P42_UID_B=' . $b['id'] );
WP_CLI::log( 'P42_OID_ASSIGNED_A=' . $oid_assigned_a );
WP_CLI::log( 'P42_OID_ASSIGNED_B=' . $oid_assigned_b );
WP_CLI::log( 'P42_OID_UNASSIGNED=' . $oid_unassigned );
WP_CLI::log( 'P42_OID_BULK1=' . $oid_bulk1 );
WP_CLI::log( 'P42_OID_BULK2=' . $oid_bulk2 );
