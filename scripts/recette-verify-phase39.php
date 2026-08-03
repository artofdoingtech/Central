<?php
/**
 * Vérificateur de la recette « convertir un abandon en commande » (Phase 39).
 *
 *   docker compose exec -T wpcli wp eval-file /scripts/recette-verify-phase39.php check <lead_id>
 *
 * Lit le lead témoin (par son id), suit son order_id, charge la commande créée par
 * la conversion (porte navigateur `abandonnees.js`) et imprime des lignes K=V que
 * la porte relit pour PROUVER que le squelette est conforme (39.1) :
 *
 *   ORDER=<id de la commande, 0 si aucune>
 *   STATUS=<slug de statut SANS le préfixe wc- : attendu « pending »>
 *   SOURCE=<meta _aod_source : attendu « aod-abandoned »>
 *   OKPRODUCT=<1 si une ligne de la commande = le produit du lead, sinon 0>
 *   ITEMS=<nombre de lignes d'article (39.4 : 0 quand le produit a disparu)>
 *   TRACKING=<1 si _aod_ship_tracking présent (ne DOIT pas l'être : squelette neutre)>
 *   LEADSTATUS=<statut du lead après conversion : attendu « converted »>
 *
 * Lecture SEULE — aucune écriture, aucune purge (la purge vit dans
 * recette-seed-phase11.php, liée au token témoin). Réservé au dev local.
 *
 * Vit dans `scripts/` : seul dossier de code monté dans le conteneur wpcli.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'AOD_COD_Leads' ) ) {
	WP_CLI::error( 'WooCommerce et/ou aod-cod-form (module leads) ne sont pas actifs.' );
}

$mode    = isset( $args[0] ) ? $args[0] : '';
$lead_id = isset( $args[1] ) ? (int) $args[1] : 0;
if ( 'check' !== $mode || ! $lead_id ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-verify-phase39.php check <lead_id>' );
}

global $wpdb;
$table = AOD_COD_Leads::table();
$lead  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $lead_id ) ); // phpcs:ignore WordPress.DB

$oid = $lead ? (int) $lead->order_id : 0;
WP_CLI::log( 'LEADSTATUS=' . ( $lead ? (string) $lead->status : '' ) );

$order = $oid ? wc_get_order( $oid ) : null;
if ( ! $order ) {
	WP_CLI::log( 'ORDER=0' );
	return;
}

$okproduct = 0;
foreach ( $order->get_items() as $item ) {
	if ( (int) $item->get_product_id() === (int) $lead->product_id ) {
		$okproduct = 1;
		break;
	}
}

WP_CLI::log( 'ORDER=' . $oid );
WP_CLI::log( 'STATUS=' . $order->get_status() );
WP_CLI::log( 'SOURCE=' . (string) $order->get_meta( '_aod_source' ) );
WP_CLI::log( 'OKPRODUCT=' . $okproduct );
WP_CLI::log( 'ITEMS=' . count( $order->get_items() ) );
WP_CLI::log( 'TRACKING=' . ( $order->get_meta( '_aod_ship_tracking' ) ? 1 : 0 ) );
