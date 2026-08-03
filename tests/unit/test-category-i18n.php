<?php
/**
 * Noms de catégories bilingues (`AOD_COD_Catalog_Filters::filter_term`) — étape 7.5.
 *
 * La 7.2 filtrait des CHAÎNES d'affichage. Ici c'est impossible : WooCommerce échoue
 * `$category->name` sans aucun hook, à l'accueil (`wc-template-functions.php:1253`)
 * comme dans le fil d'ariane (`class-wc-breadcrumb.php:177,189,243,390`). On filtre
 * donc l'OBJET terme, via `get_term` — le point de passage obligé (`populate_terms()`
 * l'appelle pour chaque terme de chaque requête, cache compris).
 *
 * Filtrer un objet plutôt qu'une chaîne déplace le danger. Ce qu'on prouve ici :
 *
 *   • la garde de TAXONOMIE : un terme qui n'est pas `product_cat` ressort intact ;
 *   • la garde de PORTÉE : hors contexte public arabe, aucun swap — c'est ce qui
 *     tient le FR dans `/gestion`, les e-mails et les bordereaux ;
 *   • l'invariant n°1 : une catégorie NON TRADUITE ressort à l'OCTET ;
 *   • 🚨 la NON-MUTATION : l'objet reçu sort du cache d'objets de WordPress. Le muter
 *     empoisonnerait toute la requête. Le filtre doit rendre un CLONE.
 *
 * ⚠️ L'invariant n°1 se teste sur un `WP_Term` construit À LA MAIN, jamais sur un terme
 * créé en base : `pre_term_name` est filtré par `sanitize_text_field`
 * (`wp-includes/default-filters.php:33`), donc `wp_insert_term()` TRIME le nom à
 * l'enregistrement. Un test qui passerait par la base comparerait « Rayons » à
 * « Rayons » et passerait même sur un code faux — le « faux témoin » qui a déjà mordu
 * en 7.3 et en 7.4. (Contrainte propre aux termes : `post_title` n'est pas trimé, donc
 * le patron de `test-catalog-filters.php` ne se transpose pas ici.)
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fabrique un WP_Term en mémoire — sans base, donc sans `pre_term_name`.
 *
 * @param string $name     Nom canonique (français).
 * @param string $taxonomy Taxonomie.
 * @param int    $term_id  Identifiant (0 = terme sans méta).
 * @return WP_Term
 */
$fx_term = static function ( $name, $taxonomy = 'product_cat', $term_id = 0 ) {
	return new WP_Term(
		(object) array(
			'term_id'  => $term_id,
			'name'     => $name,
			'slug'     => 'fx',
			'taxonomy' => $taxonomy,
		)
	);
};

// ── Fixture : une VRAIE catégorie traduite (il faut une term-meta à lire). ──
$fx_cat = wp_insert_term( 'Chaussures', 'product_cat' );
$fx_cat_id = is_wp_error( $fx_cat ) ? 0 : (int) $fx_cat['term_id'];
if ( $fx_cat_id ) {
	update_term_meta( $fx_cat_id, '_aod_cat_name_ar', 'أحذية' );
}

// Une catégorie NON traduite (aucune méta `_ar`).
$fx_cat_plain = wp_insert_term( 'Sacs', 'product_cat' );
$fx_cat_plain_id = is_wp_error( $fx_cat_plain ) ? 0 : (int) $fx_cat_plain['term_id'];

try {
	AOD_Test::group( 'Catégories — la garde de taxonomie (ne toucher qu\'à product_cat)' );

	AOD_Test::eq(
		'Actualités',
		AOD_Test::in_arabic(
			static function () use ( $fx_term ) {
				$t = $fx_term( 'Actualités', 'category', 0 );
				$out = AOD_COD_Catalog_Filters::filter_term( $t, 'category' );
				return $out->name;
			}
		),
		'Un terme `category` traverse le filtre sans être touché'
	);

	AOD_Test::eq(
		'Promo',
		AOD_Test::in_arabic(
			static function () use ( $fx_term ) {
				$t = $fx_term( 'Promo', 'product_tag', 0 );
				$out = AOD_COD_Catalog_Filters::filter_term( $t, 'product_tag' );
				return $out->name;
			}
		),
		'Un terme `product_tag` traverse le filtre sans être touché'
	);

	AOD_Test::group( 'Catégories — le rendu arabe et son repli' );

	AOD_Test::eq(
		'أحذية',
		AOD_Test::in_arabic(
			static function () use ( $fx_term, $fx_cat_id ) {
				$out = AOD_COD_Catalog_Filters::filter_term( $fx_term( 'Chaussures', 'product_cat', $fx_cat_id ), 'product_cat' );
				return $out->name;
			}
		),
		'Site en arabe + catégorie traduite → le nom arabe saisi par le gérant'
	);

	AOD_Test::eq(
		'Sacs',
		AOD_Test::in_arabic(
			static function () use ( $fx_term, $fx_cat_plain_id ) {
				$out = AOD_COD_Catalog_Filters::filter_term( $fx_term( 'Sacs', 'product_cat', $fx_cat_plain_id ), 'product_cat' );
				return $out->name;
			}
		),
		'Site en arabe + catégorie NON traduite → repli sur le français'
	);

	AOD_Test::group( 'Catégories — invariant n°1 : une catégorie non traduite ressort à l\'OCTET' );

	AOD_Test::eq(
		'  Rayons  ',
		AOD_Test::in_arabic(
			static function () use ( $fx_term ) {
				// term_id = 0 → aucune méta `_ar` : le cas « non traduit ».
				$t = $fx_term( '  Rayons  ', 'product_cat', 0 );
				$out = AOD_COD_Catalog_Filters::filter_term( $t, 'product_cat' );
				return $out->name;
			}
		),
		'Les espaces encadrants SURVIVENT (le nom n\'est pas passé par le trim de `pick()`)',
		'Si ce test tombe, c\'est que la garde « AR vide » a été contournée : `pick()` trimme ses deux côtés, et on modifierait un nom qu\'on n\'a jamais traduit.'
	);

	AOD_Test::group( 'Catégories — 🚨 non-mutation : l\'objet du cache n\'est JAMAIS modifié' );

	// ⚠️ On construit le terme à la main : `get_term()` est DÉJÀ filtré (le hook est
	// branché), il rendrait donc un clone arabe — on ne testerait plus la mutation.
	AOD_Test::eq(
		'Chaussures',
		AOD_Test::in_arabic(
			static function () use ( $fx_term, $fx_cat_id ) {
				$original = $fx_term( 'Chaussures', 'product_cat', $fx_cat_id );
				AOD_COD_Catalog_Filters::filter_term( $original, 'product_cat' );
				// On relit l'objet qu'on a PASSÉ au filtre, pas ce qu'il a rendu.
				return $original->name;
			}
		),
		'L\'objet passé au filtre conserve son nom français après le swap',
		'`get_term` sert l\'objet du cache (`wp_cache_get( $id, \'terms\' )`). Sans `clone`, le filtre empoisonnerait le cache pour toute la requête — y compris hors de notre garde.'
	);

	AOD_Test::ok(
		AOD_Test::in_arabic(
			static function () use ( $fx_term, $fx_cat_id ) {
				$original = $fx_term( 'Chaussures', 'product_cat', $fx_cat_id );
				$out      = AOD_COD_Catalog_Filters::filter_term( $original, 'product_cat' );
				return $out !== $original;
			}
		),
		'Le filtre rend un objet DISTINCT de celui qu\'il a reçu (un clone)',
		'Même nom rendu, mais si c\'est la même instance, le cache est déjà corrompu.'
	);

	AOD_Test::group( 'Catégories — la garde de portée (le cœur de l\'étape)' );

	AOD_Test::eq(
		'Chaussures',
		AOD_COD_Catalog_Filters::filter_term( get_term( $fx_cat_id, 'product_cat' ), 'product_cat' )->name,
		'Site en FRANÇAIS → aucun swap, la catégorie traduite reste en français',
		'C\'est aussi ce qui garantit qu\'aucune lecture de term-meta n\'a lieu en FR (`get_term` est appelé des dizaines de fois par page).'
	);

	AOD_Test::group( 'Catégories — le filtre est bien branché, et lui seul' );

	AOD_Test::eq(
		10,
		has_filter( 'get_term', array( 'AOD_COD_Catalog_Filters', 'filter_term' ) ),
		'`get_term` est filtré en priorité 10'
	);

	AOD_Test::ok(
		false === has_filter( 'get_terms', array( 'AOD_COD_Catalog_Filters', 'filter_terms' ) )
		&& false === has_filter( 'single_term_title', array( 'AOD_COD_Catalog_Filters', 'filter_single_term_title' ) ),
		'Ni `get_terms` ni `single_term_title` ne sont filtrés — ils sont redondants',
		'`populate_terms()` appelle `get_term()` pour chaque terme (cache compris), et `get_term_by()` finit par `get_term()`. Les ajouter relirait la term-meta pour rien.'
	);
} finally {
	if ( $fx_cat_id ) {
		wp_delete_term( $fx_cat_id, 'product_cat' );
	}
	if ( $fx_cat_plain_id ) {
		wp_delete_term( $fx_cat_plain_id, 'product_cat' );
	}
}
