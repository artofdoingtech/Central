<?php
/**
 * Filtre par confirmateur (Commandes, Suivi) — Phase 43.2.
 *
 * GÉRANT UNIQUEMENT (cf. story) : voir la liste des confirmateurs par nom est
 * l'information que `assigned_confirmer_label()`/`assign_select_html()`
 * protègent déjà ailleurs dans ce dépôt. Confiance zéro dans l'ID posté.
 *
 * 🚨 `AOD_CD_Section_Orders::assignment_scope()`/`product_scope()` sont
 * mémoïsées PAR INSTANCE sur le singleton — correct en production (un
 * process = une requête = un utilisateur), trompeur ici où ce fichier
 * simule plusieurs identités via `wp_set_current_user()` dans le MÊME
 * process. Reset par réflexion, patron IDENTIQUE à
 * `test-assignation-commandes.php:39-58` — trouvé et vérifié en préparant
 * cette story (cf. Dev Agent Record de 43.2, Task 2).
 *
 * Nettoyage HPOS-safe : `wc_get_order( $id )->delete( true )`, jamais
 * `wp_delete_post()` (piège documenté en 43.1).
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AOD_CD_Roles' ) || ! class_exists( 'AOD_CD_Section_Orders' ) || ! class_exists( 'AOD_CD_Section_Tracking' ) ) {
	return;
}

$aod_fc_user_avant = get_current_user_id();
$aod_fc_orders      = AOD_CD_Section_Orders::instance();

/** Reset du cache de périmètre mémoïsé — artefact de test uniquement, cf. en-tête. */
$aod_fc_reset_scope_cache = function ( $obj ) {
	$ref = new ReflectionObject( $obj );
	foreach ( array( 'assignment_scope', 'product_scope' ) as $prop ) {
		if ( $ref->hasProperty( $prop ) ) {
			$rp = $ref->getProperty( $prop );
			$rp->setAccessible( true );
			$rp->setValue( $obj, null );
		}
	}
};

$aod_fc_gerants = get_users( array( 'role__in' => array( 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );
if ( empty( $aod_fc_gerants ) ) {
	AOD_Test::group( 'Filtre confirmateur 43.2' );
	AOD_Test::ok( false, 'Aucun compte administrateur trouvé sur ce clone — impossible de tester en gérant' );
	$_GET = array();
	return;
}
$aod_fc_gerant_id = (int) $aod_fc_gerants[0];

wp_set_current_user( $aod_fc_gerant_id );

/* =========================================================================
 * Fixtures : 2 confirmateurs actifs + 2 commandes, une par confirmateur
 * ========================================================================= */

$aod_fc_make_confirmer = function ( $login ) {
	$uid = wp_insert_user( array(
		'user_login'   => $login,
		'user_pass'    => wp_generate_password( 24, false ),
		'user_email'   => $login . '@example.invalid',
		'display_name' => 'Recette ' . $login,
		'role'         => AOD_CD_ROLE_CONFIRMER,
	) );
	if ( is_wp_error( $uid ) ) {
		return 0;
	}
	( new WP_User( $uid ) )->add_cap( 'aod_cap_confirm_orders' );
	return (int) $uid;
};

$aod_fc_a = $aod_fc_make_confirmer( 'aod_test_fc_a' );
$aod_fc_b = $aod_fc_make_confirmer( 'aod_test_fc_b' );

$aod_fc_order_a = wc_create_order();
$aod_fc_order_a->set_billing_last_name( 'FiltreConfirmA' );
$aod_fc_order_a->update_meta_data( '_aod_assigned_confirmer', $aod_fc_a );
$aod_fc_order_a->set_status( 'pending' );
$aod_fc_order_a->save();

$aod_fc_order_b = wc_create_order();
$aod_fc_order_b->set_billing_last_name( 'FiltreConfirmB' );
$aod_fc_order_b->update_meta_data( '_aod_assigned_confirmer', $aod_fc_b );
$aod_fc_order_b->set_status( 'pending' );
$aod_fc_order_b->save();

// Colis EXPÉDIÉ (pour Suivi — la garde meta_query de cette page exige un
// tracking non vide), assigné au confirmateur A.
$aod_fc_track_a = wc_create_order();
$aod_fc_track_a->set_billing_last_name( 'FiltreConfirmTrackA' );
$aod_fc_track_a->update_meta_data( '_aod_ship_tracking', 'AODFC43TRACK' );
$aod_fc_track_a->update_meta_data( '_aod_assigned_confirmer', $aod_fc_a );
$aod_fc_track_a->set_status( 'pending' );
$aod_fc_track_a->save();

$aod_fc_track_b = wc_create_order();
$aod_fc_track_b->set_billing_last_name( 'FiltreConfirmTrackB' );
$aod_fc_track_b->update_meta_data( '_aod_ship_tracking', 'AODFC43TRACKB' );
$aod_fc_track_b->update_meta_data( '_aod_assigned_confirmer', $aod_fc_b );
$aod_fc_track_b->set_status( 'pending' );
$aod_fc_track_b->save();

/* =========================================================================
 * Confiance zéro dans l'ID posté (AC 2) — gérant, vivier réel
 * ========================================================================= */

AOD_Test::group( 'Filtre confirmateur 43.2 — confiance zéro (AC 2)' );

$aod_fc_reset_scope_cache( $aod_fc_orders );
$_GET = array( 'statut' => 'all', 'confirmateur' => $aod_fc_a );
ob_start();
$aod_fc_orders->render();
$aod_fc_html_valid = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fc_html_valid, 'FiltreConfirmA' ) && false === strpos( $aod_fc_html_valid, 'FiltreConfirmB' ),
	'ID d’un vrai confirmateur actif : filtre appliqué (A visible, B exclu)'
);

$aod_fc_reset_scope_cache( $aod_fc_orders );
$_GET = array( 'statut' => 'all', 'confirmateur' => 999999999 );
ob_start();
$aod_fc_orders->render();
$aod_fc_html_bogus = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fc_html_bogus, 'FiltreConfirmA' ) && false !== strpos( $aod_fc_html_bogus, 'FiltreConfirmB' ),
	'ID inexistant : traité comme absent (les deux visibles), pas d’erreur'
);

$aod_fc_reset_scope_cache( $aod_fc_orders );
$_GET = array( 'statut' => 'all', 'confirmateur' => $aod_fc_gerant_id );
ob_start();
$aod_fc_orders->render();
$aod_fc_html_notconf = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fc_html_notconf, 'FiltreConfirmA' ) && false !== strpos( $aod_fc_html_notconf, 'FiltreConfirmB' ),
	'🔴 ID d’un compte qui n’est PAS un confirmateur (le gérant lui-même) : hors vivier, filtre ignoré'
);

/* =========================================================================
 * Commandes — ancrage inverse (AC 3)
 * ========================================================================= */

AOD_Test::group( 'Filtre confirmateur 43.2 — Commandes : ancrage inverse (AC 3)' );

$aod_fc_reset_scope_cache( $aod_fc_orders );
$_GET = array( 'statut' => 'all' );
ob_start();
$aod_fc_orders->render();
$aod_fc_html_all = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fc_html_all, 'FiltreConfirmA' ) && false !== strpos( $aod_fc_html_all, 'FiltreConfirmB' ),
	'ANCRAGE : sans filtre, les deux commandes sont visibles (le gérant voit tout)'
);

$aod_fc_reset_scope_cache( $aod_fc_orders );
$_GET = array( 'statut' => 'all', 'confirmateur' => $aod_fc_b );
ob_start();
$aod_fc_orders->render();
$aod_fc_html_b = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fc_html_b, 'FiltreConfirmB' ) && false === strpos( $aod_fc_html_b, 'FiltreConfirmA' ),
	'🔴 ANCRAGE INVERSE : filtrer sur B exclut A (symétrique du premier test)'
);

/* =========================================================================
 * Suivi — même patron
 * ========================================================================= */

AOD_Test::group( 'Filtre confirmateur 43.2 — Suivi : ancrage inverse (AC 4)' );

$aod_fc_reset_scope_cache( $aod_fc_orders );
$_GET = array( 'confirmateur' => $aod_fc_a );
ob_start();
AOD_CD_Section_Tracking::instance()->render();
$aod_fc_track_html_a = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fc_track_html_a, 'FiltreConfirmTrackA' ) && false === strpos( $aod_fc_track_html_a, 'FiltreConfirmTrackB' ),
	'Suivi : filtre sur A inclut son colis, exclut celui de B'
);

$aod_fc_reset_scope_cache( $aod_fc_orders );
$_GET = array();
ob_start();
AOD_CD_Section_Tracking::instance()->render();
$aod_fc_track_html_all = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fc_track_html_all, 'FiltreConfirmTrackA' ) && false !== strpos( $aod_fc_track_html_all, 'FiltreConfirmTrackB' ),
	'🔴 ANCRAGE INVERSE : sans filtre, les deux colis sont visibles'
);

/* =========================================================================
 * Un confirmateur (pas le gérant) qui poste `confirmateur` — ignoré (AC 1)
 * ========================================================================= */

AOD_Test::group( 'Filtre confirmateur 43.2 — gate gérant strict (AC 1)' );

$aod_fc_reset_scope_cache( $aod_fc_orders );
wp_set_current_user( $aod_fc_a );
$_GET = array( 'statut' => 'all', 'confirmateur' => $aod_fc_b );
ob_start();
$aod_fc_orders->render();
$aod_fc_html_conf = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fc_html_conf, 'FiltreConfirmA' ),
	'Confirmateur A voit sa propre commande (périmètre habituel, cumul 42.3 intact)'
);
AOD_Test::ok(
	false === strpos( $aod_fc_html_conf, 'FiltreConfirmB' ),
	'🔴 Confirmateur A NE voit PAS la commande de B malgré `confirmateur=B` posté — le paramètre est ignoré, le cumul 42.3 (pas notre filtre) protège déjà ce cas'
);
AOD_Test::ok(
	false === strpos( $aod_fc_html_conf, 'id="aod-cd-f-confirmer"' ),
	'Le <select> confirmateur n’est PAS rendu pour un confirmateur (gate gérant strict)'
);

wp_set_current_user( $aod_fc_gerant_id );

/* =========================================================================
 * Nettoyage — HPOS-safe, jamais wp_delete_post()
 * ========================================================================= */

AOD_Test::group( 'Filtre confirmateur 43.2 — nettoyage' );

$aod_fc_order_a->delete( true );
$aod_fc_order_b->delete( true );
$aod_fc_track_a->delete( true );
$aod_fc_track_b->delete( true );
AOD_Test::ok(
	false === wc_get_order( $aod_fc_order_a->get_id() ) && false === wc_get_order( $aod_fc_track_a->get_id() ),
	'Les 4 commandes jetables ont bien disparu (->delete(true))'
);

require_once ABSPATH . 'wp-admin/includes/user.php';
if ( $aod_fc_a ) {
	wp_delete_user( $aod_fc_a );
}
if ( $aod_fc_b ) {
	wp_delete_user( $aod_fc_b );
}
AOD_Test::ok(
	false === get_user_by( 'id', $aod_fc_a ) && false === get_user_by( 'id', $aod_fc_b ),
	'Les 2 comptes confirmateurs jetables ont bien disparu'
);

wp_set_current_user( $aod_fc_user_avant );
$aod_fc_reset_scope_cache( $aod_fc_orders );
$_GET = array();
