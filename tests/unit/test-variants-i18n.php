<?php
/**
 * Variantes bilingues (étape 7.4) — la cascade d'affichage et les CLÉS.
 *
 * Les variantes sont le seul contenu marchand de la Phase 7 qui est aussi une
 * **clé de matching serveur** : le nom FR d'une valeur est ce que le navigateur
 * renvoie (`value=`), ce que le serveur compare, et ce qui finit écrit sur la
 * ligne de commande. Traduire cette clé ne « traduit » rien : ça casse la
 * commande, en silence, et seulement pour les arabophones.
 *
 * D'où trois choses à prouver :
 *   • `localize_variant()` : la cascade saisie > dictionnaire > français ;
 *   • `match_option()` : le FR matche, l'arabe NON — la preuve que la clé est
 *     française et doit le rester ;
 *   • la branche de compat : son libellé ne passe PLUS par gettext (D1bis).
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

$form = AOD_COD_Form::instance();

AOD_Test::group( 'Variantes 7.4 — localize_variant() : saisie > dictionnaire > FR' );

// AC 1.1 — le but de l'étape : ce que le gérant écrit prime sur tout.
// ⚠️ La saisie doit DIFFÉRER de ce que rendrait le dictionnaire, sinon le test
// passe aussi sur l'ancien code et ne prouve rien (le piège de la 7.3).
// Le dictionnaire dirait « أحمر » ; le gérant, lui, a écrit « قرمزي » (cramoisi).
AOD_Test::eq( 'قرمزي', AOD_Test::in_arabic( static function () use ( $form ) {
	return AOD_Test::call( $form, 'localize_variant', array( 'Rouge', 'قرمزي' ) );
} ), 'Saisie AR présente : elle prime sur le dictionnaire (qui aurait dit « أحمر »)' );

// AC 1.2 — le comportement d'AVANT la 7.4, préservé à l'identique.
AOD_Test::eq( 'أحمر', AOD_Test::in_arabic( static function () use ( $form ) {
	return AOD_Test::call( $form, 'localize_variant', array( 'Rouge', '' ) );
} ), 'Sans saisie : le dictionnaire prend le relais (comportement d\'avant 7.4)' );

// AC 1.3 — le trou que la 7.4 existe pour boucher…
AOD_Test::eq( 'Sahara', AOD_Test::in_arabic( static function () use ( $form ) {
	return AOD_Test::call( $form, 'localize_variant', array( 'Sahara', '' ) );
} ), 'Mot inconnu du dictionnaire, sans saisie : le français, jamais du vide' );

// AC 1.4 — …et la preuve qu'elle le bouche.
AOD_Test::eq( 'صحراء', AOD_Test::in_arabic( static function () use ( $form ) {
	return AOD_Test::call( $form, 'localize_variant', array( 'Sahara', 'صحراء' ) );
} ), 'Mot inconnu du dictionnaire MAIS saisi : la saisie le sauve (le but de la 7.4)' );

// AC 1.5 — le repli mot-à-mot du dictionnaire, préservé.
AOD_Test::eq( 'رمادي فاتح', AOD_Test::in_arabic( static function () use ( $form ) {
	return AOD_Test::call( $form, 'localize_variant', array( 'Gris clair', '' ) );
} ), 'Valeur composée : le repli mot-à-mot du dictionnaire fonctionne toujours' );

// AC 1.6 — LA garde : rien d'arabe ne doit fuir sur le site français.
AOD_Test::eq( 'Rouge', AOD_Test::call( $form, 'localize_variant', array( 'Rouge', 'أحمر' ) ), 'Site FR : le français, même avec une saisie AR disponible' );

// AC 1.7 — une saisie blanche n'est pas une saisie.
AOD_Test::eq( 'أحمر', AOD_Test::in_arabic( static function () use ( $form ) {
	return AOD_Test::call( $form, 'localize_variant', array( 'Rouge', '   ' ) );
} ), 'Saisie AR faite d\'espaces : ignorée, on retombe sur le dictionnaire' );

AOD_Test::group( 'Variantes 7.4 — match_option() : la clé est FRANÇAISE (AC 25)' );

// 🚨 Le test qui justifie toute la conception de la story : si un jour quelqu'un
// traduit `value=`, c'est CETTE comparaison qui échoue — et le visiteur arabophone
// ne peut plus commander du tout, sans le moindre message pour le gérant.
$sec = array(
	'label'  => 'Couleur',
	'values' => array(
		array( 'name' => 'Rouge', 'name_ar' => 'أحمر', 'price' => '' ),
		array( 'name' => 'Bleu', 'name_ar' => 'أزرق', 'price' => '' ),
	),
);

$hit = AOD_Test::call( $form, 'match_option', array( $sec, 'Rouge' ) );
AOD_Test::eq( 'Rouge', is_array( $hit ) ? $hit['name'] : null, 'Le nom FR canonique matche : la commande passe' );

AOD_Test::eq( null, AOD_Test::call( $form, 'match_option', array( $sec, 'أحمر' ) ), '🚨 Le nom ARABE ne matche PAS — traduire value= casserait la commande' );

// Et il ne matche pas davantage en contexte arabe : la clé ne dépend pas de la langue.
AOD_Test::eq( null, AOD_Test::in_arabic( static function () use ( $form, $sec ) {
	return AOD_Test::call( $form, 'match_option', array( $sec, 'أحمر' ) );
} ), 'Même en site AR, la clé reste le FR : l\'arabe ne matche toujours pas' );

AOD_Test::eq( null, AOD_Test::call( $form, 'match_option', array( $sec, 'Vert' ) ), 'Valeur inexistante : null (le formulaire refusera la ligne)' );

AOD_Test::group( 'Variantes 7.4 — get_product_options() : traversée + rétro-compat (AC 24)' );

// Un produit d'AVANT la 7.4 : sa méta n'a ni `label_ar` ni `name_ar`. Il ne doit rien
// lever et rendre des chaînes vides — c'est l'invariant n°1 (aucune régression sur
// les clones existants), et ça se prouve, ça ne se suppose pas.
$p_old = new WC_Product_Simple();
$p_old->set_name( 'Produit 7.4 sans traduction' );
$p_old->update_meta_data( '_aod_options', array(
	array(
		'label'  => 'Taille',
		'visual' => false,
		'values' => array( array( 'name' => 'L', 'price' => '' ) ),
	),
) );
$old_id = $p_old->save();

// Un produit traduit : les deux clés doivent traverser jusqu'au rendu.
$p_new = new WC_Product_Simple();
$p_new->set_name( 'Produit 7.4 traduit' );
$p_new->update_meta_data( '_aod_options', array(
	array(
		'label'    => 'Taille',
		'label_ar' => 'المقاس',
		'visual'   => false,
		'values'   => array( array( 'name' => 'L', 'name_ar' => 'كبير', 'price' => '' ) ),
	),
) );
$new_id = $p_new->save();

try {
	$sections = AOD_Test::call( $form, 'get_product_options', array( wc_get_product( $old_id ) ) );
	AOD_Test::eq( '', $sections[0]['label_ar'], 'Produit d\'avant 7.4 : label_ar est une chaîne vide, aucune erreur' );
	AOD_Test::eq( '', $sections[0]['values'][0]['name_ar'], 'Produit d\'avant 7.4 : name_ar est une chaîne vide, aucune erreur' );
	AOD_Test::eq( 'Taille', $sections[0]['label'], 'Produit d\'avant 7.4 : le FR canonique est intact' );

	$sections = AOD_Test::call( $form, 'get_product_options', array( wc_get_product( $new_id ) ) );
	AOD_Test::eq( 'المقاس', $sections[0]['label_ar'], 'Produit traduit : label_ar traverse jusqu\'au rendu (public)' );
	AOD_Test::eq( 'كبير', $sections[0]['values'][0]['name_ar'], 'Produit traduit : name_ar traverse jusqu\'au rendu (public)' );

	// Et côté éditeur : sans ça, la traduction saisie disparaît au rechargement.
	$edit = AOD_CD_Section_Products::instance()->get_product_options( wc_get_product( $new_id ) );
	AOD_Test::eq( 'المقاس', $edit[0]['label_ar'], 'Éditeur /gestion : label_ar est relu (sinon perdu au rechargement)' );
	AOD_Test::eq( 'كبير', $edit[0]['values'][0]['name_ar'], 'Éditeur /gestion : name_ar est relu (sinon perdu au rechargement)' );

	// 🚨 La clé ne bouge pas, même en arabe : c'est tout l'édifice de la 7.4.
	AOD_Test::eq( 'L', AOD_Test::in_arabic( static function () use ( $form, $new_id ) {
		$s = AOD_Test::call( $form, 'get_product_options', array( wc_get_product( $new_id ) ) );
		return $s[0]['values'][0]['name'];
	} ), '🚨 Site AR : le nom canonique reste « L » — la clé ne dépend pas de la langue' );
} finally {
	wp_delete_post( $old_id, true );
	wp_delete_post( $new_id, true );
}

AOD_Test::group( 'Variantes 7.4 — D1bis : la clé de commande ne passe PLUS par gettext' );

// 🔴 Le bug que ce test aurait attrapé : `__( 'Couleur' )` sur la branche de compat
// « ancien produit variable ». La locale AR est ACTIVE pendant la création de la
// commande (AOD_COD_Lang::filter_locale s'applique à admin-ajax), donc la clé du
// meta de ligne partait en arabe — jusque dans le bordereau transporteur.
$vari = new WC_Product_Variable();
$vari->set_name( 'Produit variable de test 7.4' );
$vid = $vari->save();

try {
	$sections = AOD_Test::in_arabic( static function () use ( $form, $vid ) {
		return AOD_Test::call( $form, 'get_product_options', array( wc_get_product( $vid ) ) );
	} );
	// Sans variation achetable, la branche ne produit aucune section : on ne teste
	// alors que ce qui est testable ici — le libellé, lui, est vérifié ci-dessous.
	$label = isset( $sections[0]['label'] ) ? $sections[0]['label'] : null;
	if ( null !== $label ) {
		AOD_Test::eq( 'Couleur', $label, '🔴 Produit variable en AR : la clé de section reste « Couleur » (FR), pas « اللون »' );
	}

	// Le cœur du correctif, sans dépendre des variations : le libellé par défaut
	// est une constante FR, pas une chaîne traduite.
	AOD_Test::eq( 'Couleur', AOD_Test::in_arabic( static function () use ( $form ) {
		return AOD_Test::call( $form, 'default_variant_label', array() );
	} ), '🔴 Le libellé de compat par défaut est une CONSTANTE FR, même en site arabe' );

	// …et il s'affiche quand même en arabe : le dictionnaire s'en charge (D1bis).
	AOD_Test::eq( 'اللون', AOD_Test::in_arabic( static function () use ( $form ) {
		return AOD_Test::call( $form, 'localize_variant', array( AOD_Test::call( $form, 'default_variant_label', array() ), '' ) );
	} ), 'Et le visiteur AR lit bien « اللون » : le correctif ne change RIEN à l\'écran' );
} finally {
	wp_delete_post( $vid, true );
}
