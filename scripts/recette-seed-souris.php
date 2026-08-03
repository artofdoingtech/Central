<?php
/**
 * Fixture de la recette « non-régression souris » (`run.sh souris`).
 *
 * Créer  :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-souris.php create
 * Purger :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-souris.php cleanup
 *
 * `create` écrit `SOURISID=<id>` (lu par run.sh, passé au scénario Playwright).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 🚨 POURQUOI CETTE FIXTURE EXISTE
 *
 * `a11y-souris.js` doit prouver qu'une option de menu flottant se sélectionne
 * bien À LA SOURIS — ce qui exige un clic RÉEL suivi d'un AJAX RÉEL. Jusqu'ici il
 * le faisait sur `.aod-cd-statussel` **de la première commande de la liste**,
 * c'est-à-dire une VRAIE commande du gérant :
 *
 *   • si le statut tiré au sort était « Confirmée », le hook
 *     `woocommerce_order_status_aod-confirmed` appelait `AOD_Shipping::auto_send()`
 *     → un COLIS RÉEL partait chez Yalidine / ZR / Ecotrack, pour une commande qui
 *     n'aurait jamais dû être expédiée. Remettre `data-value` à sa valeur d'origine
 *     ne rappelle pas un colis ;
 *     🔁 47.2 — CE POINT PRÉCIS EST CADUC : le hook n'existe plus, confirmer
 *     n'envoie plus rien. Le récit est conservé (il est vrai à sa date, et il
 *     explique pourquoi cette graine existe) ; les deux points suivants, eux,
 *     restent d'actualité et suffisent à la justifier ;
 *   • si c'était « En cours » ou « Terminée », `reduce_pack_components_stock()` et
 *     WooCommerce décrémentaient du VRAI stock — que le retour arrière ne
 *     réincrémente pas ;
 *   • et une interruption entre l'aller et le retour laissait la commande du
 *     client dans le mauvais statut.
 *
 * Un audit d'accessibilité ne doit pas pouvoir expédier une commande. On sème donc
 * une commande jetable, marquée, **sans aucune ligne produit** (les hooks de stock
 * n'ont alors rien à décrémenter), et le scénario ne touche qu'elle.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Vit dans `scripts/` : seul dossier de code monté dans le conteneur wpcli.
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

const AOD_SOURIS_MARK = '_aod_seed_souris';

/** Purge idempotente : ne supprime QUE les commandes marquées. */
function aod_souris_cleanup() {
	$ids = wc_get_orders( array(
		'limit'      => -1,
		'return'     => 'ids',
		'status'     => 'any',
		'meta_query' => array( array( 'key' => AOD_SOURIS_MARK, 'value' => '1' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	foreach ( $ids as $id ) {
		$o = wc_get_order( $id );
		if ( $o ) {
			$o->delete( true );
		}
	}
	return count( $ids );
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	WP_CLI::log( 'PURGE=' . aod_souris_cleanup() );
	return;
}
if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-souris.php create|cleanup' );
}

aod_souris_cleanup(); // Repart propre (rejouable).

// Commande jetable : AUCUNE ligne produit (rien à décrémenter côté stock), statut
// « en attente » — le point de départ le plus inerte du cycle WooCommerce.
$o = wc_create_order();
$o->set_address( array(
	'first_name' => 'Client',
	'last_name'  => 'SourisRecette',
	'phone'      => '0551239999',
	'city'       => 'Alger',
	'state'      => 'DZ-16',
	'country'    => 'DZ',
), 'billing' );
$o->update_meta_data( '_aod_wilaya_code', 16 );
$o->update_meta_data( AOD_SOURIS_MARK, '1' );
$o->set_total( 0 );
$o->set_status( 'pending' );
$o->save();

WP_CLI::log( 'SOURISID=' . $o->get_id() );
WP_CLI::log( 'SOURIS=1' );
