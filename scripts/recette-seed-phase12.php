<?php
/**
 * Fixtures de la recette « Confirmée → expédiée se déplace vers Suivi » (Phase 12,
 * étape 12.2).
 *
 * Créer  :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase12.php create
 * Purger :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase12.php cleanup
 *
 * `create` écrit `P12=1` (jeton de présence lu par run.sh).
 *
 * Ce qu'on sème (toutes marquées `_aod_seed_phase12` — `cleanup` ne supprime QUE ça) :
 *   • Commande « ExpedieRecette »  : statut Confirmée + `_aod_ship_tracking` posé
 *       (colis créé chez le transporteur) → EXPÉDIÉE : doit QUITTER « Commandes »
 *       et apparaître dans « Suivi commande ».
 *   • Commande « AConfirmerRecette » : statut Confirmée, SANS tracking → reste
 *       dans « Commandes » (à expédier), absente de « Suivi ».
 *
 * L'envoi auto est désactivé le temps de la création : AUCUN appel transporteur
 * réel n'est fait (le tracking de la commande expédiée est posé à la main pour
 * simuler un colis déjà créé). Rejouable : `create` purge d'abord tout reliquat.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce n’est pas actif.' );
}

const AOD_P12_MARK = '_aod_seed_phase12';

/** Purge idempotente : ne supprime QUE les commandes marquées. */
function aod_p12_cleanup() {
	$ids = wc_get_orders( array(
		'limit'      => -1,
		'return'     => 'ids',
		'status'     => 'any',
		'meta_query' => array( array( 'key' => AOD_P12_MARK, 'value' => '1' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	foreach ( $ids as $id ) {
		$o = wc_get_order( $id );
		if ( $o ) { $o->delete( true ); }
	}
	return count( $ids );
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	WP_CLI::log( 'PURGE=' . aod_p12_cleanup() );
	return;
}
if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-phase12.php create|cleanup' );
}

aod_p12_cleanup(); // Repart propre.

// Désactive l'envoi auto le temps de créer (zéro appel transporteur réel).
$auto_saved = get_option( 'aod_shipping_auto' );

// 🚨 Restauration GARANTIE. La remise en état vivait uniquement en fin de fichier :
// une fatale PHP, un OOM du conteneur wpcli (cas prévu par run.sh) ou un Ctrl-C
// entre ici et la fin laissait le clone avec l'ENVOI AUTOMATIQUE DÉSACTIVÉ. Le
// gérant ne s'en apercevait que le jour où ses commandes cessaient de partir chez
// le transporteur. `register_shutdown_function` s'exécute même après une fatale,
// contrairement à un `try/finally`. Idempotent avec la restauration nominale.
register_shutdown_function( function () use ( $auto_saved ) {
	if ( false === $auto_saved ) {
		delete_option( 'aod_shipping_auto' );
	} else {
		update_option( 'aod_shipping_auto', $auto_saved );
	}
} );

$auto            = is_array( $auto_saved ) ? $auto_saved : array();
$auto['enabled'] = 0;
update_option( 'aod_shipping_auto', $auto );

// Commande EXPÉDIÉE : Confirmée + colis créé (tracking posé à la main).
// Date FIXE et PASSÉE (pas « maintenant ») — réutilisée par le filtre date de
// la Phase 43.1 (commandes-suivi.js) : une fixture datée dans le présent ne
// permettrait pas de distinguer une plage qui la couvre d'une plage qui ne la
// couvre pas au jour près.
$o1 = wc_create_order();
$o1->set_address( array( 'first_name' => 'Client', 'last_name' => 'ExpedieRecette', 'phone' => '0551230000', 'city' => 'Alger', 'state' => 'DZ-16', 'country' => 'DZ' ), 'billing' );
$o1->set_date_created( '2024-06-15 10:00:00' );
$o1->update_meta_data( '_aod_wilaya_code', 16 );
$o1->update_meta_data( '_aod_delivery_type', 'home' );
$o1->update_meta_data( '_aod_ship_carrier', 'yalidine' );
$o1->update_meta_data( '_aod_ship_tracking', 'ZZ12TRACK001' ); // colis créé → expédiée
$o1->update_meta_data( AOD_P12_MARK, '1' );
$o1->set_total( 3000 );
$o1->set_status( 'aod-confirmed' ); // 47.2 : plus aucun hook d'envoi ici — ce statut n'appelle personne.
$o1->save();

// Commande NON EXPÉDIÉE : Confirmée, sans tracking (reste dans « Commandes »).
$o2 = wc_create_order();
$o2->set_address( array( 'first_name' => 'Client', 'last_name' => 'AConfirmerRecette', 'phone' => '0551230001', 'city' => 'Oran', 'state' => 'DZ-31', 'country' => 'DZ' ), 'billing' );
$o2->set_date_created( '2024-06-15 10:00:00' ); // même date fixe que ci-dessus (43.1).
$o2->update_meta_data( '_aod_wilaya_code', 31 );
$o2->update_meta_data( AOD_P12_MARK, '1' );
$o2->set_total( 3500 );
$o2->set_status( 'aod-confirmed' );
$o2->save();

// Restaure l'envoi auto tel qu'il était.
if ( false === $auto_saved ) { delete_option( 'aod_shipping_auto' ); } else { update_option( 'aod_shipping_auto', $auto_saved ); }

WP_CLI::log( 'SHIPNUM=' . $o1->get_order_number() );
WP_CLI::log( 'UNSHIPNUM=' . $o2->get_order_number() );
WP_CLI::log( 'P12=1' );
