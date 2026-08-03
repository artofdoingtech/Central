<?php
/**
 * Fixtures de la recette « catalogue bilingue » (Phase 7, étape 7.6).
 *
 * Créer  :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-bilingue.php create
 * Purger :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-bilingue.php cleanup
 *
 * `create` écrit sur stdout, une par ligne, les clés que `tools/recette/run.sh`
 * transmet au script navigateur :  CAT=<id>  TRAD=<id>  TEMOIN=<id>
 *
 * Tout ce qui est créé porte la méta `_aod_seed_bilingue` = 1 : `cleanup` ne
 * supprime QUE ça (idempotent, rejouable après un Ctrl-C). `create` commence par
 * purger un éventuel reliquat : deux exécutions ne s'empilent jamais.
 *
 * ⚠️ Les valeurs arabes sont DISCRIMINANTES à dessein : aucune ne figure dans
 * `variant_dictionary()` ni dans les chaînes des `.po`. Si l'une d'elles
 * s'affiche, elle ne peut venir QUE de la saisie du gérant (ici, du seed) —
 * c'est ce qui rend la recette probante (leçon 7.3/7.4/7.5).
 *
 * Vit dans `scripts/` (pas `tools/recette/`) : c'est le seul dossier de code
 * monté dans le conteneur `wpcli` (docker-compose.yml). Garde-fou localhost :
 * même règle que `dev-seed.php`.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'AOD_COD_Landing' ) ) {
	WP_CLI::error( 'WooCommerce et/ou aod-cod-form ne sont pas actifs.' );
}

const AOD_BIL_MARK = '_aod_seed_bilingue';

/** Purge tout ce qui porte la marque. Rendue idempotente pour le `trap` de run.sh. */
function aod_bilingue_cleanup() {
	$n_p = 0;
	foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => AOD_BIL_MARK, 'meta_value' => '1' ) ) as $pid ) {
		wp_delete_post( $pid, true );
		$n_p++;
	}
	$n_t = 0;
	foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ) ) as $tid ) {
		if ( get_term_meta( $tid, AOD_BIL_MARK, true ) ) {
			wp_delete_term( $tid, 'product_cat' );
			$n_t++;
		}
	}
	return array( $n_p, $n_t );
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	list( $n_p, $n_t ) = aod_bilingue_cleanup();
	WP_CLI::success( "Fixtures bilingues purgées : $n_p produit(s), $n_t catégorie(s)." );
	return;
}
if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-bilingue.php create|cleanup' );
}

aod_bilingue_cleanup(); // Reliquat d'une exécution interrompue : on repart propre.

/* -------------------------------------------------------------------------
 * 1. Catégorie traduite
 * ---------------------------------------------------------------------- */
$t = wp_insert_term( 'Recette Rayon Bilingue', 'product_cat' );
if ( is_wp_error( $t ) ) {
	WP_CLI::error( 'Création de la catégorie impossible : ' . $t->get_error_message() );
}
$cat_id = (int) $t['term_id'];
update_term_meta( $cat_id, '_aod_cat_name_ar', 'رف المعاينة الثنائية' );
update_term_meta( $cat_id, AOD_BIL_MARK, '1' );

/* -------------------------------------------------------------------------
 * 2. Produit ENTIÈREMENT traduit
 *    Arguments : 3 FR / 2 AR → la 3ᵉ puce AR doit replier en FR (le bug 7.3).
 *    Variantes : 1 valeur saisie AR + 1 sans AR ni dico → doit rester FR en AR.
 * ---------------------------------------------------------------------- */
$p = new WC_Product_Simple();
$p->set_name( 'Recette Produit Bilingue' );
$p->set_status( 'publish' );
$p->set_regular_price( '2500' );
$p->set_price( '2500' );
$p->set_description( 'Description française de la recette bilingue.' );
$p->set_category_ids( array( $cat_id ) );
$p->update_meta_data( '_aod_title_ar', 'منتج المعاينة الثنائي' );
$p->update_meta_data( '_aod_desc_ar', 'وصف المعاينة الثنائية بالعربية.' );
$p->update_meta_data( '_aod_selling_points', array( 'Argument recette un', 'Argument recette deux', 'Argument recette trois' ) );
$p->update_meta_data( '_aod_selling_points_ar', array( 'حجة المعاينة الأولى', 'حجة المعاينة الثانية' ) );
// Format 7.4 : label_ar / name_ar IMBRIQUÉS dans `_aod_options` (jamais de méta
// parallèle — l'appariement est structurel). « Contenance Recette », « Selva » et
// « Duna » sont absents de variant_dictionary() : c'est voulu (cf. en-tête).
$p->update_meta_data( '_aod_options', array(
	array(
		'label'    => 'Contenance Recette',
		'label_ar' => 'سعة المعاينة',
		'visual'   => false,
		'display'  => 'cards',
		'values'   => array(
			array( 'name' => 'Selva', 'name_ar' => 'سلفى المعاينة', 'price' => '', 'image_id' => 0, 'hex' => '' ),
			array( 'name' => 'Duna', 'name_ar' => '', 'price' => '', 'image_id' => 0, 'hex' => '' ),
		),
	),
) );
$p->update_meta_data( AOD_BIL_MARK, '1' );
$trad_id = $p->save();

/* -------------------------------------------------------------------------
 * 2bis. Produit LANDING — porteur de la surface n°10 (héro en repli).
 *    ⚠️ Fixture SÉPARÉE, et ce n'est pas un luxe : une landing allumée REMPLACE
 *    le rendu de la fiche (`template_include`) — la porter sur le produit
 *    traduit ferait disparaître les surfaces « fiche standard » (h1,
 *    description, fil d'ariane) que la recette doit précisément vérifier.
 * ---------------------------------------------------------------------- */
$l = new WC_Product_Simple();
$l->set_name( 'Recette Produit Landing' );
$l->set_status( 'publish' );
$l->set_regular_price( '3100' );
$l->set_price( '3100' );
$l->set_category_ids( array( $cat_id ) );
$l->update_meta_data( '_aod_title_ar', 'منتج معاينة الصفحة البيعية' );
// Landing starter ALLUMÉE, héro sans titre : le héro doit replier sur le nom du
// produit via l'appel explicite à AOD_COD_Catalog_I18n (7.2-AC 11).
// ⚠️ starter() rend des TYPES (chaînes) : on construit des blocs et on passe par
// sanitize(), sinon la landing ne s'affiche jamais (blocs non-tableaux sautés).
$l->update_meta_data( '_aod_landing', AOD_COD_Landing::sanitize( array(
	'blocks' => array_map(
		static function ( $type ) {
			return array( 'type' => $type );
		},
		AOD_COD_Landing::starter()
	),
) ) );
$l->update_meta_data( '_aod_landing_enabled', '1' ); // is_enabled() exige la CHAÎNE '1'.
$l->update_meta_data( AOD_BIL_MARK, '1' );
$land_id = $l->save();

/* -------------------------------------------------------------------------
 * 3. Produit TÉMOIN, non traduit — le repli FR intégral
 * ---------------------------------------------------------------------- */
$w = new WC_Product_Simple();
$w->set_name( 'Recette Produit Temoin' );
$w->set_status( 'publish' );
$w->set_regular_price( '1900' );
$w->set_price( '1900' );
$w->set_description( 'Description française du produit témoin.' );
$w->set_category_ids( array( $cat_id ) );
$w->update_meta_data( AOD_BIL_MARK, '1' );
$temoin_id = $w->save();

if ( ! $trad_id || ! $temoin_id || ! $land_id ) {
	aod_bilingue_cleanup();
	WP_CLI::error( 'Création des produits impossible — fixtures purgées.' );
}

WP_CLI::log( 'CAT=' . $cat_id );
WP_CLI::log( 'TRAD=' . $trad_id );
WP_CLI::log( 'TEMOIN=' . $temoin_id );
WP_CLI::log( 'LAND=' . $land_id );
