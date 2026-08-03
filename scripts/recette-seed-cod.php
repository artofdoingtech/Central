<?php
/**
 * Fixture de la recette « commande COD de bout en bout » (`run.sh cod`).
 *
 * Créer  :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-cod.php create
 * Purger :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-cod.php cleanup
 *
 * `create` écrit `CODPID=<id>` et `CODOPT=<valeur>` (lus par run.sh, passés au
 * scénario Playwright), puis `COD=1`.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 🚨 POURQUOI CETTE FIXTURE EXISTE
 *
 * `commande-cod.js` empruntait le produit à acheter au clone :
 * `wc_get_products( limit 1, status publish )`, c'est-à-dire **le dernier produit
 * publié**, sans vérifier ni ses options, ni son stock, ni son type. La porte
 * passait ou cassait selon ce que le gérant avait saisi en dernier.
 *
 * Et quand ce produit portait des options obligatoires, le formulaire refusait la
 * soumission **côté JavaScript** (`aod-cod-form.js` : `collectItems()` → `missing`
 * → `return` AVANT le `$.post`). Aucune requête ne partait — mais les assertions
 * « honeypot refusé », « time-trap refusé », « verrou 24 h » passaient au VERT,
 * puisqu'elles se contentaient de constater qu'un refus avait eu lieu. La porte
 * censée prouver qu'une boutique encaisse validait des couches anti-bot qu'elle
 * n'atteignait jamais.
 *
 * On sème donc le produit acheté, avec **une section d'option à 2 valeurs** : c'est
 * le cas réaliste d'une boutique COD algérienne (taille, couleur…), et cela oblige
 * la recette à traverser le chemin `collectItems()` au lieu de l'éviter.
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

const AOD_COD_SEED_MARK = '_aod_seed_cod';

/** Valeur d'option que la recette doit sélectionner (nom FR canonique). */
const AOD_COD_SEED_OPT = 'Bleu Recette';

/** Purge idempotente : ne supprime QUE ce qui porte la marque. */
function aod_cod_seed_cleanup() {
	$n    = 0;
	$pids = get_posts( array(
		'post_type'   => 'product',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => AOD_COD_SEED_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	) );
	foreach ( $pids as $pid ) {
		wp_delete_post( $pid, true );
		$n++;
	}
	// Commandes passées par la recette sur ce produit (marquées à la création par
	// le scénario, ou repérées par le nom de client de la recette en dernier filet).
	$oids = wc_get_orders( array(
		'limit'      => -1,
		'return'     => 'ids',
		'status'     => 'any',
		'meta_query' => array( array( 'key' => AOD_COD_SEED_MARK, 'value' => '1' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	foreach ( $oids as $oid ) {
		$o = wc_get_order( $oid );
		if ( $o ) {
			$o->delete( true );
			$n++;
		}
	}
	return $n;
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	WP_CLI::log( 'PURGE=' . aod_cod_seed_cleanup() );
	return;
}
if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-cod.php create|cleanup' );
}

aod_cod_seed_cleanup(); // Repart propre (rejouable).

$p = new WC_Product_Simple();
$p->set_name( 'Produit Recette COD' );
$p->set_status( 'publish' );
$p->set_catalog_visibility( 'visible' );
$p->set_regular_price( '2500' );
$p->set_price( '2500' );
// Stock NON géré : la recette crée une vraie commande, on ne veut pas qu'elle
// décrémente un compteur qu'il faudrait ensuite remettre à la main.
$p->set_manage_stock( false );
$p->set_description( 'Produit jetable de la recette COD.' );
$pid = $p->save();

update_post_meta( $pid, AOD_COD_SEED_MARK, '1' );

// UNE section d'options, 2 valeurs, rendu « cartes » (radios) — le cas par défaut.
// Le nom FR est la CLÉ renvoyée par le navigateur et comparée côté serveur par
// AOD_COD_Form::match_option() : il ne se traduit jamais (leçon 7.4).
update_post_meta( $pid, '_aod_options', array(
	array(
		'label'    => 'Couleur Recette',
		'label_ar' => 'لون المعاينة',
		'visual'   => false,
		'display'  => 'cards',
		'values'   => array(
			array( 'name' => AOD_COD_SEED_OPT, 'name_ar' => 'أزرق المعاينة', 'price' => 0 ),
			array( 'name' => 'Vert Recette', 'name_ar' => 'أخضر المعاينة', 'price' => 0 ),
		),
	),
) );

// UNE offre de lot, donc DEUX cartes au rendu : la carte « 1 produit » implicite
// (posée par offer_cards_html(), et seule porteuse de `.is-selected` au chargement)
// plus celle-ci. Sans elle, la fiche ne rend qu'UNE carte, déjà sélectionnée : la
// porte `mouvement` n'aurait aucune AUTRE carte à cliquer, donc aucun moyen
// d'observer la transition de `.aod-cod__offer-check` — dont l'état est porté par
// un ANCÊTRE (`.aod-cod__offer-card.is-selected`). Cf. story 18.1, T2bis.
// Contrat lu dans get_product_offers() : qty >= 2 et price > 0, le prix étant le
// TOTAL du lot (pas le prix unitaire).
update_post_meta( $pid, '_aod_offers', array(
	array( 'qty' => 2, 'price' => 4500 ),
) );

WP_CLI::log( 'CODPID=' . $pid );
WP_CLI::log( 'CODOPT=' . AOD_COD_SEED_OPT );
WP_CLI::log( 'COD=1' );
