<?php
/**
 * Filtres bilingues du catalogue (`AOD_COD_Catalog_Filters`) — étape 7.2.
 *
 * 7.1 a posé la garde ; 7.2 la BRANCHE sur la sortie du cœur WordPress. Ces
 * filtres sont GLOBAUX : ils voient passer le titre de chaque page, de chaque
 * entrée de menu, de chaque e-mail. Ce qu'on prouve ici, ce n'est donc pas tant
 * « l'arabe s'affiche » que **le filtre ne touche à RIEN d'autre** :
 *
 *   • la garde de TYPE : hors produit (page, ID nul) → la chaîne ressort intacte ;
 *   • le court-circuit « AR vide » : un produit NON TRADUIT ressort à l'OCTET —
 *     sans ça, `pick()` le trimmerait et on modifierait un contenu qu'on n'a pas
 *     traduit (l'invariant n°1 de la Phase 7 tomberait) ;
 *   • `single_post_title` reçoit un **WP_Post**, pas un ID : réutiliser le callback
 *     de `the_title` rendrait `''` en silence et le `<title>` ne serait jamais traduit.
 *
 * Les tests tournent dans un vrai WordPress : on crée un produit et une page
 * temporaires, et on les supprime — le test ne laisse rien derrière lui.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

// ── Fixtures : un produit traduit, un produit nu, une page. ──
$fx_product = wp_insert_post(
	array(
		'post_type'    => 'product',
		'post_status'  => 'publish',
		'post_title'   => 'Chaise',
		'post_content' => 'Une chaise en bois.',
	)
);
update_post_meta( $fx_product, '_aod_title_ar', 'كرسي' );
update_post_meta( $fx_product, '_aod_desc_ar', 'كرسي خشبي.' );

$fx_plain = wp_insert_post(
	array(
		'post_type'    => 'product',
		'post_status'  => 'publish',
		'post_title'   => 'Table',
		'post_content' => 'Une table.',
	)
);

$fx_page = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Contact',
	)
);

try {
	AOD_Test::group( 'Catalogue — the_title : la garde de type (ne toucher qu\'aux produits)' );

	AOD_Test::eq(
		'Contact',
		AOD_Test::in_arabic(
			static function () use ( $fx_page ) {
				return AOD_COD_Catalog_Filters::filter_title( 'Contact', $fx_page );
			}
		),
		'Une PAGE n\'est jamais traduite, même en arabe (les 3 pages passent par gettext)'
	);
	AOD_Test::eq(
		'Accueil',
		AOD_Test::in_arabic(
			static function () {
				return AOD_COD_Catalog_Filters::filter_title( 'Accueil', 0 );
			}
		),
		'Un ID nul ressort intact (the_title est appelé sans post dans bien des cas)'
	);

	AOD_Test::group( 'Catalogue — the_title : le produit' );

	AOD_Test::eq(
		'كرسي',
		AOD_Test::in_arabic(
			static function () use ( $fx_product ) {
				return AOD_COD_Catalog_Filters::filter_title( 'Chaise', $fx_product );
			}
		),
		'Produit traduit + site AR : le titre arabe est rendu'
	);
	AOD_Test::eq(
		'Chaise',
		AOD_COD_Catalog_Filters::filter_title( 'Chaise', $fx_product ),
		'Produit traduit + site FR : le titre français est conservé'
	);

	AOD_Test::group( 'Catalogue — invariant n°1 : un produit NON TRADUIT ressort à l\'octet' );

	// Le cas qui piège : garde OUVERTE (site AR) mais aucune traduction. Sans le
	// court-circuit « AR vide », pick() trimmerait et on modifierait le contenu
	// d'un produit qu'on n'a jamais traduit.
	AOD_Test::eq(
		'  Table  ',
		AOD_Test::in_arabic(
			static function () use ( $fx_plain ) {
				return AOD_COD_Catalog_Filters::filter_title( '  Table  ', $fx_plain );
			}
		),
		'Produit sans _aod_title_ar en AR : la chaîne ressort telle quelle, espaces compris'
	);

	AOD_Test::group( 'Catalogue — single_post_title : un WP_Post, PAS un ID' );

	AOD_Test::eq(
		'كرسي',
		AOD_Test::in_arabic(
			static function () use ( $fx_product ) {
				return AOD_COD_Catalog_Filters::filter_single_post_title( 'Chaise', get_post( $fx_product ) );
			}
		),
		'La balise <title> d\'un produit traduit passe en arabe'
	);
	AOD_Test::eq(
		'Contact',
		AOD_Test::in_arabic(
			static function () use ( $fx_page ) {
				return AOD_COD_Catalog_Filters::filter_single_post_title( 'Contact', get_post( $fx_page ) );
			}
		),
		'La balise <title> d\'une page reste intacte'
	);
	AOD_Test::eq(
		'Chaise',
		AOD_Test::in_arabic(
			static function () {
				return AOD_COD_Catalog_Filters::filter_single_post_title( 'Chaise', null );
			}
		),
		'Sans WP_Post : intact (jamais de get_post_meta sur autre chose qu\'un post)'
	);

	AOD_Test::group( 'Catalogue — the_content : la description' );

	$with_post = static function ( $pid, $fn ) {
		// `the_content` ne reçoit pas d'ID : le filtre lit le post courant.
		$prev            = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = get_post( $pid );
		try {
			return $fn();
		} finally {
			$GLOBALS['post'] = $prev;
		}
	};

	AOD_Test::eq(
		'كرسي خشبي.',
		AOD_Test::in_arabic(
			static function () use ( $with_post, $fx_product ) {
				return $with_post(
					$fx_product,
					static function () {
						return AOD_COD_Catalog_Filters::filter_content( 'Une chaise en bois.' );
					}
				);
			}
		),
		'Produit traduit + site AR : la description arabe est rendue'
	);
	AOD_Test::eq(
		"\nUne table.\n",
		AOD_Test::in_arabic(
			static function () use ( $with_post, $fx_plain ) {
				return $with_post(
					$fx_plain,
					static function () {
						return AOD_COD_Catalog_Filters::filter_content( "\nUne table.\n" );
					}
				);
			}
		),
		'Produit non traduit en AR : la description ressort à l\'octet (sauts de ligne compris)'
	);
	AOD_Test::eq(
		'Texte de la page Contact.',
		AOD_Test::in_arabic(
			static function () use ( $with_post, $fx_page ) {
				return $with_post(
					$fx_page,
					static function () {
						return AOD_COD_Catalog_Filters::filter_content( 'Texte de la page Contact.' );
					}
				);
			}
		),
		'Le contenu d\'une PAGE n\'est jamais touché'
	);

	AOD_Test::group( 'Catalogue — priorité des filtres (la mise en forme de l\'AR)' );

	// `wpautop` est en 10 : à priorité 10 notre filtre passerait APRÈS lui et l'AR
	// sortirait sans <p>, alors que le FR serait paragraphé. On doit être en 9.
	AOD_Test::eq(
		9,
		has_filter( 'the_content', array( 'AOD_COD_Catalog_Filters', 'filter_content' ) ),
		'the_content est branché en priorité 9 — avant wpautop (10), sinon l\'AR perd ses paragraphes'
	);
} finally {
	wp_delete_post( $fx_product, true );
	wp_delete_post( $fx_plain, true );
	wp_delete_post( $fx_page, true );
}
