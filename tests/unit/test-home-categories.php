<?php
/**
 * Sélection des catégories « à la une » de l'accueil — étape 9.2.
 *
 * On prouve le TRI, sur du code pur (sans base) : `AOD_COD_Pages::sort_home_category_ids()`
 * reçoit des items `{id, order, name}` et rend les ids ordonnés. C'est ce tri qui, passé
 * en `orderby="include"` au shortcode `[product_categories]`, fixe l'ordre d'affichage.
 *
 * Clé primaire : l'ORDRE (`_aod_cat_home_order`). Tie-breaks : le NOM, puis l'ID — ce
 * dernier rend le tri DÉTERMINISTE même à nom égal (usort n'est stable qu'en PHP ≥ 8).
 *
 * Tests discriminants (leçon 7.3/7.4) : un tri qui ignorerait l'ordre, ou qui ne
 * départagerait pas les ex æquo, tomberait sur au moins une de ces assertions.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

$ids = static function ( $items ) {
	return implode( ',', AOD_COD_Pages::sort_home_category_ids( $items ) );
};

AOD_Test::group( 'Accueil 9.2 — tri des catégories « à la une »' );

AOD_Test::eq(
	'6,5,7',
	$ids( array(
		array( 'id' => 5, 'order' => 2, 'name' => '' ),
		array( 'id' => 6, 'order' => 1, 'name' => '' ),
		array( 'id' => 7, 'order' => 3, 'name' => '' ),
	) ),
	'Ordre croissant : ordres 2/1/3 → ids 6,5,7'
);

AOD_Test::eq(
	'6,5',
	$ids( array(
		array( 'id' => 5, 'order' => 1, 'name' => 'Bottes' ),
		array( 'id' => 6, 'order' => 1, 'name' => 'Accessoires' ),
	) ),
	'À ordre égal, tie-break par NOM : « Accessoires » (id 6) avant « Bottes » (id 5)'
);

AOD_Test::eq(
	'4,9',
	$ids( array(
		array( 'id' => 9, 'order' => 1, 'name' => 'Rayon' ),
		array( 'id' => 4, 'order' => 1, 'name' => 'Rayon' ),
	) ),
	'À ordre ET nom égaux, tie-break par ID : 4 avant 9 (déterminisme)',
	'Sans ce dernier départage, l\'ordre de deux ex æquo serait celui du hasard (usort instable < PHP 8).'
);

AOD_Test::eq(
	'',
	$ids( array() ),
	'Entrée vide → sortie vide'
);

AOD_Test::eq(
	'3',
	$ids( array( array( 'id' => 3, 'order' => 0, 'name' => 'Seule' ) ) ),
	'Un seul item → lui-même'
);

/* ══════════════════════════════════════════════════════════════════════════
   La BASCULE « sélection ⇄ repli » — `home_categories_shortcode()`.

   Pourquoi ici et pas au navigateur : la porte de recette prouvait le repli en
   exigeant un état GLOBAL (« aucune catégorie du site n'est cochée »). La graine
   de recette est volontairement ADDITIVE — elle ne touche jamais aux objets du
   clone — donc incapable de produire cet état : sur une boutique où le gérant a
   coché ses propres catégories, la porte rougissait SANS défaut. Neutraliser ses
   catégories réelles aurait été destructif et non réversible en cas
   d'interruption. La bascule est du code pur : elle se prouve sans base.
   ══════════════════════════════════════════════════════════════════════════ */

AOD_Test::group( 'Accueil 9.2 — bascule sélection ⇄ repli' );

$sc = static function ( $ids_in ) {
	return AOD_COD_Pages::home_categories_shortcode( $ids_in );
};

AOD_Test::eq(
	'[product_categories columns="3" hide_empty="1"]',
	$sc( array() ),
	'Aucune sélection → REPLI : toutes les catégories non vides',
	'C\'est le comportement d\'avant la Phase 9 : un clone qui ne coche rien ne doit rien voir changer.'
);

AOD_Test::eq(
	'[product_categories ids="7,3,9" orderby="include" columns="3" hide_empty="0"]',
	$sc( array( 7, 3, 9 ) ),
	'Sélection → ids dans l\'ORDRE reçu + orderby=include + hide_empty=0'
);

AOD_Test::ok(
	false !== strpos( $sc( array( 4 ) ), 'hide_empty="0"' ),
	'🚨 Sélection : hide_empty=0 — une catégorie cochée mais VIDE reste affichable',
	'C\'est la demande métier de la 9.2 : mettre en avant une catégorie neuve, encore sans produit.'
);

AOD_Test::ok(
	false === strpos( $sc( array() ), 'ids=' ),
	'Repli : aucun ids= (sinon on rendrait une sélection vide, donc RIEN)'
);

AOD_Test::eq(
	'[product_categories columns="3" hide_empty="1"]',
	$sc( array( 0, '', 'abc' ) ),
	'Entrées non numériques ignorées → repli, jamais un ids="0" bancal'
);
