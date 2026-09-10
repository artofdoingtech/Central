<?php
/**
 * Carte premium (2026-09-10) — « ne demander que les choix nécessaires ».
 *
 * Le panneau de variantes est passé d'une fenêtre flottante à un panneau
 * INLINE dans la carte. Ce cliquet ne garde pas l'animation (CSS, hors de
 * portée d'un test PHP) — il garde la logique qui décide QUAND ouvrir ce
 * panneau, qui est nouvelle et ne se voit pas à l'œil nu :
 *
 *   • `AOD_COD_Form::needs_variant_choice()` — un produit à options n'a pas
 *     forcément de VRAI choix à faire (une section peut n'avoir plus qu'UNE
 *     valeur disponible, les autres étant en rupture).
 *   • `AOD_COD_Form::only_choice_for()` — la combinaison jouable d'un
 *     produit sans vrai choix, prête pour un ajout direct.
 *   • `option_sections_html()` pré-coche/pré-sélectionne cette valeur unique
 *     — pastilles ET dropdown — pour que la fiche produit CLASSIQUE n'exige
 *     plus un clic sur un choix qui n'en est pas un.
 *   • `AOD_COD_Cart::needschoice_attrs()` — le contrat `data-needschoice` /
 *     `data-autoopt` que lit `aod-cart.js` pour sauter le panneau.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Carte premium 2026-09-10 — needs_variant_choice() / only_choice_for()' );

$form = AOD_COD_Form::instance();
$cart = AOD_COD_Cart::instance();

/**
 * Construit un produit jetable portant `_aod_options`, l'exécute dans le
 * bloc donné, puis le supprime — même patron que test-variant-stock.php.
 */
function aod_cp_avec_produit( $options, $cb ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'Produit carte premium (jetable)' );
	$p->set_regular_price( '1000' );
	$p->set_status( 'publish' );
	$p->update_meta_data( '_aod_options', $options );
	$pid = $p->save();
	try {
		$cb( wc_get_product( $pid ) );
	} finally {
		wp_delete_post( $pid, true );
	}
}

/* ---------------------------------------------------------------------------
 * Chaque section n'a plus qu'UNE valeur disponible → rien à demander.
 * ------------------------------------------------------------------------- */
aod_cp_avec_produit(
	array(
		array(
			'label'  => 'Couleur',
			'values' => array(
				array( 'name' => 'Rouge', 'stock' => '5' ),
				array( 'name' => 'Bleu', 'stock' => '0' ), // rupture
				array( 'name' => 'Vert', 'stock' => '0' ), // rupture
			),
		),
		array(
			'label'  => 'Pointure',
			'values' => array(
				array( 'name' => '41', 'stock' => '' ), // non géré = toujours disponible
			),
		),
	),
	function ( $p ) use ( $form ) {
		AOD_Test::ok(
			false === $form->needs_variant_choice( $p ),
			'Chaque section a EXACTEMENT une valeur jouable → aucun vrai choix (needs_variant_choice = false)'
		);
		$combo = $form->only_choice_for( $p );
		AOD_Test::eq( 'Rouge', isset( $combo[0] ) ? $combo[0] : null, 'only_choice_for() : Couleur → Rouge (seule survivante)' );
		AOD_Test::eq( '41', isset( $combo[1] ) ? $combo[1] : null, 'only_choice_for() : Pointure → 41 (seule valeur)' );
	}
);

/* ---------------------------------------------------------------------------
 * Une section garde ≥2 valeurs disponibles → un vrai choix reste à faire,
 * même si UNE AUTRE section, elle, n'a plus qu'une survivante.
 * ------------------------------------------------------------------------- */
aod_cp_avec_produit(
	array(
		array(
			'label'  => 'Couleur',
			'values' => array(
				array( 'name' => 'Rouge', 'stock' => '5' ),
				array( 'name' => 'Bleu', 'stock' => '3' ),
			),
		),
		array(
			'label'  => 'Pointure',
			'values' => array(
				array( 'name' => '41', 'stock' => '' ),
				array( 'name' => '42', 'stock' => '0' ), // rupture : seule 41 reste
			),
		),
	),
	function ( $p ) use ( $form ) {
		AOD_Test::ok(
			true === $form->needs_variant_choice( $p ),
			'🔴 Couleur a 2 valeurs jouables → un vrai choix reste, MÊME si Pointure, elle, n’en a plus qu’une'
		);
	}
);

/* ---------------------------------------------------------------------------
 * Une section n'a PLUS AUCUNE valeur disponible (rupture totale d'un axe) :
 * le choix reste nécessaire — pour que l'acheteur VOIE la rupture, plutôt
 * qu'un refus muet.
 * ------------------------------------------------------------------------- */
aod_cp_avec_produit(
	array(
		array(
			'label'  => 'Couleur',
			'values' => array(
				array( 'name' => 'Rouge', 'stock' => '0' ),
				array( 'name' => 'Bleu', 'stock' => '0' ),
			),
		),
	),
	function ( $p ) use ( $form ) {
		AOD_Test::ok(
			true === $form->needs_variant_choice( $p ),
			'🔴 Section entièrement en rupture (0 valeur jouable) → le panneau reste nécessaire, pour DIRE la rupture'
		);
		AOD_Test::eq( array(), $form->only_choice_for( $p ), 'only_choice_for() : aucune combinaison jouable → tableau vide' );
	}
);

/* ---------------------------------------------------------------------------
 * Produit sans options du tout : les deux méthodes se replient proprement.
 * ------------------------------------------------------------------------- */
$p_simple = new WC_Product_Simple();
$p_simple->set_name( 'Produit sans options (jetable)' );
$p_simple->set_regular_price( '500' );
$p_simple->set_status( 'publish' );
$pid_simple = $p_simple->save();
try {
	$p_simple_obj = wc_get_product( $pid_simple );
	AOD_Test::ok( false === $form->needs_variant_choice( $p_simple_obj ), 'Sans options : needs_variant_choice() = false (repli)' );
	AOD_Test::eq( array(), $form->only_choice_for( $p_simple_obj ), 'Sans options : only_choice_for() = tableau vide' );
} finally {
	wp_delete_post( $pid_simple, true );
}

AOD_Test::group( 'Carte premium 2026-09-10 — option_sections_html() pré-coche la valeur unique' );

/*
 * 🚨 Ce n'est PAS le même produit dans les deux sections : Couleur (2 valeurs
 * disponibles) reste vierge, Pointure (1 seule) doit sortir cochée — le rendu
 * doit traiter les deux sections INDÉPENDAMMENT dans le MÊME produit.
 */
aod_cp_avec_produit(
	array(
		array(
			'label'  => 'Couleur',
			'values' => array(
				array( 'name' => 'Rouge', 'stock' => '5' ),
				array( 'name' => 'Bleu', 'stock' => '3' ),
			),
		),
		array(
			'label'  => 'Pointure',
			'values' => array(
				array( 'name' => '41', 'stock' => '' ),
				array( 'name' => '42', 'stock' => '0' ),
			),
		),
	),
	function ( $p ) use ( $form ) {
		$html = $form->options_html_for( $p );
		AOD_Test::ok( '' !== $html, 'ANCRAGE : le HTML est bien rendu (sinon tout ce qui suit serait vrai par vacuité)' );

		// Couleur : DEUX valeurs jouables → aucun radio `checked` dans cette section.
		AOD_Test::ok(
			1 === preg_match( '/data-si="0"[\s\S]*?(?=data-si="1")/', $html, $m_couleur ),
			'ANCRAGE : la section Couleur (data-si="0") est isolable dans le HTML'
		);
		if ( isset( $m_couleur[0] ) ) {
			AOD_Test::ok(
				false === strpos( $m_couleur[0], 'checked' ),
				'Couleur (2 valeurs jouables) : AUCUN radio pré-coché — un vrai choix reste à faire'
			);
		}

		// Pointure : UNE seule valeur jouable (42 en rupture) → « 41 » sort coché.
		AOD_Test::ok(
			1 === preg_match( '/value="41"[^>]*/', $html, $m_41 ),
			'ANCRAGE : la valeur « 41 » est bien rendue'
		);
		if ( isset( $m_41[0] ) ) {
			AOD_Test::ok(
				false !== strpos( $m_41[0], 'checked' ),
				'🔴 Pointure (1 seule valeur jouable) : « 41 » sort PRÉ-COCHÉE'
			);
		}
		AOD_Test::ok(
			false !== strpos( $html, 'is-selected' ),
			'…et sa pastille porte déjà `is-selected` (l’écran ne ment pas avant que le JS ne s’exécute)'
		);
	}
);

/* ---------------------------------------------------------------------------
 * Même garantie en mode DROPDOWN (`<select>`) : `selected`, pas `checked`.
 * ------------------------------------------------------------------------- */
aod_cp_avec_produit(
	array(
		array(
			'label'   => 'Couleur',
			'display' => 'dropdown',
			'values'  => array(
				array( 'name' => 'Rouge', 'stock' => '5' ),
				array( 'name' => 'Bleu', 'stock' => '0' ),
			),
		),
	),
	function ( $p ) use ( $form ) {
		$html = $form->options_html_for( $p );
		AOD_Test::ok(
			1 === preg_match( '/<option value="Rouge"[^>]*/', $html, $m ),
			'ANCRAGE : l’option « Rouge » est bien rendue dans le <select>'
		);
		if ( isset( $m[0] ) ) {
			AOD_Test::ok( false !== strpos( $m[0], 'selected' ), '🔴 Dropdown, 1 seule valeur jouable : « Rouge » sort PRÉ-SÉLECTIONNÉE' );
		}
	}
);

AOD_Test::group( 'Carte premium 2026-09-10 — AOD_COD_Cart::needschoice_attrs() (le contrat DOM lu par aod-cart.js)' );

aod_cp_avec_produit(
	array(
		array(
			'label'  => 'Couleur',
			'values' => array( array( 'name' => 'Rouge', 'stock' => '5' ) ),
		),
	),
	function ( $p ) use ( $cart ) {
		list( $needschoice, $autoopt_json ) = AOD_Test::call( $cart, 'needschoice_attrs', array( $p ) );
		AOD_Test::eq( '0', $needschoice, 'Un seul choix jouable : data-needschoice="0" (la carte ajoute directement)' );
		AOD_Test::eq( '{&quot;0&quot;:&quot;Rouge&quot;}', $autoopt_json, 'data-autoopt porte la combinaison, déjà esc_attr() (guillemets encodés)' );
	}
);

aod_cp_avec_produit(
	array(
		array(
			'label'  => 'Couleur',
			'values' => array(
				array( 'name' => 'Rouge', 'stock' => '5' ),
				array( 'name' => 'Bleu', 'stock' => '3' ),
			),
		),
	),
	function ( $p ) use ( $cart ) {
		list( $needschoice, $autoopt_json ) = AOD_Test::call( $cart, 'needschoice_attrs', array( $p ) );
		AOD_Test::eq( '1', $needschoice, '2 valeurs jouables : data-needschoice="1" (le panneau doit ouvrir)' );
		AOD_Test::eq( '{}', $autoopt_json, 'Vrai choix à faire : data-autoopt vide (rien à auto-remplir)' );
	}
);
