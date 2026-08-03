<?php
/**
 * Fixtures de la recette « catégories sur l'accueil » (Phase 9, étape 9.3).
 *
 * Créer  :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase9.php create
 * Purger :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase9.php cleanup
 *
 * `create` écrit sur stdout, une par ligne, les ids que la recette utilise :
 *   SELZ=<id> SELA=<id> SELM=<id> SELN=<id> SELW=<id>
 *
 * Ce qu'on sème (tout porte la méta `_aod_seed_phase9` — `cleanup` ne supprime QUE ça) :
 *   • Zsel91 (cochée « accueil », ordre 1, nom AR « زدسلكت ») — VIDE
 *   • Asel91 (cochée, ordre 2) — VIDE
 *   • Msel91 (cochée, ordre 3) — VIDE
 *   • Nsel91 (NON cochée) — VIDE          → absente dans les deux modes
 *   • Wsel91 (NON cochée) — NON VIDE (1 produit publié) → témoin que la sélection
 *       est un REMPLACEMENT, pas une addition : bien que non vide, elle doit être
 *       ABSENTE tant qu'elle n'est pas cochée.
 *       (Le mode « repli », lui, est prouvé en unitaire — cf. l'encadré plus bas.)
 *
 * Les ordres 1/2/3 sur des noms Z/A/M prouvent que l'ORDRE l'emporte sur l'alphabet.
 * Le nom arabe est discriminant (n'apparaît nulle part ailleurs) : s'il s'affiche, il
 * vient de la saisie (leçon 7.5). `create` purge d'abord tout reliquat (rejouable).
 *
 * Vit dans `scripts/` : seul dossier de code monté dans le conteneur wpcli. Garde
 * localhost, comme les autres seeds.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'AOD_COD_Pages' ) ) {
	WP_CLI::error( 'WooCommerce et/ou aod-cod-form ne sont pas actifs.' );
}

const AOD_P9_MARK = '_aod_seed_phase9';

/** Purge idempotente : ne supprime QUE ce qui porte la marque. */
function aod_p9_cleanup() {
	$n = 0;
	// Produits marqués.
	$pids = get_posts( array(
		'post_type'   => 'product',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => AOD_P9_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	) );
	foreach ( $pids as $pid ) {
		wp_delete_post( $pid, true );
		$n++;
	}
	// Termes marqués.
	foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $t ) {
		if ( '' !== (string) get_term_meta( $t->term_id, AOD_P9_MARK, true ) ) {
			wp_delete_term( $t->term_id, 'product_cat' );
			$n++;
		}
	}
	return $n;
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	WP_CLI::log( 'PURGE=' . aod_p9_cleanup() );
	return;
}

/*
 * ⚠️ Le mode `unflag` a été RETIRÉ (2026-07-19), et il ne faut pas le réintroduire.
 *
 * Il prétendait « basculer en mode repli » en décochant les catégories semées. Mais
 * la bascule s'évalue sur l'ÉTAT GLOBAL du site : `home_categories_shortcode()` reste
 * en mode sélection dès qu'UNE catégorie est cochée, où qu'elle soit. Or cette graine
 * est volontairement ADDITIVE — elle ne touche jamais aux objets du clone — donc son
 * `unflag` ne pouvait pas produire l'état qu'il annonçait. Sur une boutique où le
 * gérant a coché ses propres catégories (l'usage normal), le repli n'était jamais
 * atteint : la porte rougissait sans le moindre défaut, et une assertion voisine
 * passait au vert pour la mauvaise raison.
 *
 * L'étendre à TOUTES les catégories aurait été destructif : `delete_term_meta` ne se
 * rejoue pas, `cleanup` ne sait que supprimer des objets marqués, et une mort du
 * conteneur au mauvais moment aurait laissé la sélection du gérant perdue.
 *
 * La bascule est du code pur : elle est prouvée sans base ni risque dans
 * `tests/unit/test-home-categories.php`, groupe « bascule sélection ⇄ repli ».
 */

if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-phase9.php create|cleanup' );
}

aod_p9_cleanup(); // Repart propre.

/** Crée une catégorie marquée. */
function aod_p9_cat( $name ) {
	$r = wp_insert_term( $name, 'product_cat' );
	if ( is_wp_error( $r ) ) {
		WP_CLI::error( 'Création catégorie ' . $name . ' : ' . $r->get_error_message() );
	}
	$id = (int) $r['term_id'];
	update_term_meta( $id, AOD_P9_MARK, '1' );
	return $id;
}

$z = aod_p9_cat( 'Zsel91' );
$a = aod_p9_cat( 'Asel91' );
$m = aod_p9_cat( 'Msel91' );
$n = aod_p9_cat( 'Nsel91' );
$w = aod_p9_cat( 'Wsel91' );
// Deux ex æquo (même ordre) dont le nom ARABE trie à l'INVERSE du slug : de quoi
// prouver que le départage se fait sur le slug (invariant par langue), pas sur le nom
// traduit — sinon l'ordre s'inverse entre FR et AR (revue 9.2).
$ta = aod_p9_cat( 'Tieauto' ); // slug « tieauto »
$tb = aod_p9_cat( 'Tiebord' ); // slug « tiebord » → tieauto AVANT tiebord dans les 2 langues

// Sélection : Z/A/M cochées avec ordres 1/2/3 ; Z porte un nom arabe discriminant.
update_term_meta( $z, '_aod_cat_home', '1' ); update_term_meta( $z, '_aod_cat_home_order', '1' );
update_term_meta( $z, '_aod_cat_name_ar', 'زدسلكت' );
update_term_meta( $a, '_aod_cat_home', '1' ); update_term_meta( $a, '_aod_cat_home_order', '2' );
update_term_meta( $m, '_aod_cat_home', '1' ); update_term_meta( $m, '_aod_cat_home_order', '3' );
// Ex æquo à l'ordre 5. Noms AR choisis pour trier à l'ENVERS du slug :
//   ألف (alef, tôt en AR) sur Tiebord  ·  ياء (yaa, tard en AR) sur Tieauto
// → tri par nom AR = Tiebord avant Tieauto ; tri par slug = Tieauto avant Tiebord.
update_term_meta( $ta, '_aod_cat_home', '1' ); update_term_meta( $ta, '_aod_cat_home_order', '5' );
update_term_meta( $ta, '_aod_cat_name_ar', 'ياء تي' );
update_term_meta( $tb, '_aod_cat_home', '1' ); update_term_meta( $tb, '_aod_cat_home_order', '5' );
update_term_meta( $tb, '_aod_cat_name_ar', 'ألف تي' );
// Nsel91, Wsel91 : PAS de flag.

// Wsel91 devient NON VIDE : un produit publié classé dedans (témoin du repli).
// ⚠️ Le nom NE DOIT PAS contenir « Wsel91 » : la section « Nos produits » de l'accueil
// l'afficherait, et la recette croirait la CATÉGORIE Wsel91 présente en mode sélection.
$product = new WC_Product_Simple();
$product->set_name( 'Produit temoin de repli' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_regular_price( '1000' );
$product->set_category_ids( array( $w ) );
$pid = $product->save();
update_post_meta( $pid, AOD_P9_MARK, '1' );

WP_CLI::log( 'SELZ=' . $z );
WP_CLI::log( 'SELA=' . $a );
WP_CLI::log( 'SELM=' . $m );
WP_CLI::log( 'SELN=' . $n );
WP_CLI::log( 'SELW=' . $w );
