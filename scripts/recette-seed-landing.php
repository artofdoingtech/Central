<?php
/**
 * Fixture du témoin « produit SANS page de vente » de la recette `run.sh landing`.
 *
 * Créer  :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-landing.php create
 * Purger :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-landing.php cleanup
 *
 * `create` écrit `PLAINPID=<id>` (lu par run.sh, passé au scénario Playwright
 * dans `AOD_PLAIN_PID`), puis `LANDING=1`.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 🚨 POURQUOI CETTE FIXTURE EXISTE — dette `D33`
 *
 * `landing-produit.js` porte 4 contrôles de NON-RÉGRESSION : « un produit publié
 * SANS page de vente s'affiche encore comme une fiche produit normale, avec son
 * formulaire COD ». Ils exigent donc un produit ordinaire.
 *
 * `run.sh` l'EMPRUNTAIT au clone : le premier produit publié qui ne figure pas
 * dans la liste des landings actives. Sur un clone semé avec le catalogue de
 * démonstration, ce vivier est VIDE — `dev-seed-catalogue.php` allume une page de
 * vente sur ses DEUX produits, et un clone de développement n'en a pas d'autre.
 * Mesuré le 2026-07-30 : 2 produits publiés, 2 `_aod_landing_enabled=1`,
 * `AOD_PLAIN_PID` vide ⇒ les 4 contrôles se retiraient.
 *
 * Le retrait était ANNONCÉ (« ⓘ Non-régression RETIRÉE … ce n'est pas un
 * succès ») — ce qui rendait le trou lisible, mais ne le comblait pas. On sème
 * donc le témoin au lieu de le chercher, exactement comme `run.sh cod` sème le
 * produit qu'il achète, et pour la MÊME raison : *une porte qui emprunte sa
 * donnée au clone dépend de ce que le gérant a saisi en dernier.*
 *
 * 🚨 Ce produit doit rester DÉLIBÉRÉMENT ORDINAIRE. Il n'a ni options, ni offres,
 * ni variantes, ni — surtout — de `_aod_landing_enabled`. Lui ajouter quoi que ce
 * soit le rapprocherait du cas qu'il sert justement à distinguer : le témoin de la
 * non-régression n'est pas un produit riche, c'est un produit NU.
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

const AOD_LANDING_SEED_MARK = '_aod_seed_landing';

/** Purge idempotente : ne supprime QUE ce qui porte la marque. */
function aod_landing_seed_cleanup() {
	$n    = 0;
	$pids = get_posts( array(
		'post_type'   => 'product',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => AOD_LANDING_SEED_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	) );
	foreach ( $pids as $pid ) {
		wp_delete_post( $pid, true );
		$n++;
	}
	return $n;
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	WP_CLI::log( 'PURGE=' . aod_landing_seed_cleanup() );
	return;
}
if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-landing.php create|cleanup' );
}

aod_landing_seed_cleanup(); // Repart propre (rejouable).

$p = new WC_Product_Simple();
$p->set_name( 'Produit Recette Sans Landing' );
$p->set_status( 'publish' );
$p->set_catalog_visibility( 'visible' );
$p->set_regular_price( '1900' );
$p->set_price( '1900' );
// Stock NON géré : la porte ne fait que VISITER ce produit (elle ne commande pas),
// et un compteur de stock n'aurait rien à prouver ici.
$p->set_manage_stock( false );
$p->set_description( 'Produit jetable de la recette : témoin SANS page de vente.' );
$pid = $p->save();

update_post_meta( $pid, AOD_LANDING_SEED_MARK, '1' );

// 🚨 Garde-fou : le témoin ne vaut QUE s'il n'a pas de page de vente. Si une
// évolution future du CRUD produit posait ce drapeau par défaut, la porte
// deviendrait verte en comparant une landing à une landing — le défaut exact que
// `D33` a mis au jour, retourné à l'envers. On le vérifie plutôt que de le
// supposer, et on refuse de semer un témoin qui ne témoigne de rien.
if ( '' !== (string) get_post_meta( $pid, '_aod_landing_enabled', true ) ) {
	wp_delete_post( $pid, true );
	WP_CLI::error( 'Le témoin est né AVEC une page de vente : il ne prouverait rien.' );
}

WP_CLI::log( 'PLAINPID=' . $pid );
WP_CLI::log( 'LANDING=1' );
