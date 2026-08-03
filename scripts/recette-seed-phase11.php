<?php
/**
 * Fixtures de la recette « Commandes abandonnées » (Phase 11, étape 11.3).
 *
 * Créer  :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase11.php create
 * Purger :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase11.php cleanup
 *
 * `create` écrit `P11=1` sur stdout (jeton de présence lu par run.sh).
 *
 * Ce qu'on sème dans la table {prefix}aod_cod_leads (tokens préfixés « ZZP11 » —
 * `cleanup` ne supprime QUE ça) :
 *   • AbandonRecette (pending, updated_at il y a 1 h)  → onglet « Abandonnés » (>30 min)
 *   • EnCoursRecette (pending, updated_at maintenant)  → onglet « En cours » (<30 min)
 *   • ConvertiRecette (converted)                      → ne doit JAMAIS apparaître
 *   • FiltreDateRecette (pending, updated_at 2024-06-15, date FIXE et PASSÉE,
 *       distincte au JOUR près de $now/$old ci-dessous) → fixture du filtre par
 *       date (Phase 43.1, abandonnees.js) : une plage « maintenant » n'aurait pas
 *       pu distinguer inclusion et exclusion à la granularité du jour.
 *   • AssignRecetteA / AssignRecetteB (pending, assignés respectivement aux deux
 *       confirmateurs semés ci-dessous) → fixtures de l'assignation et du filtre
 *       par confirmateur (Phase 43.3). Leads DÉDIÉS, jamais convertis ni
 *       supprimés par les autres scénarios de la porte : sans quoi les
 *       assertions 43.3 dépendraient de l'ordre d'exécution du fichier.
 *
 * Et DEUX confirmateurs (43.3), actifs, porteurs de `aod_cap_confirm_orders` +
 * `aod_cap_abandoned` (sans cette seconde cap, un confirmateur n'atteint même
 * pas la page — le témoin d'identité serait vide de sens). Mots de passe
 * ENGENDRÉS à chaque exécution, jamais versionnés (patron
 * `recette-seed-assignation.php`).
 *
 * Les noms sont discriminants (n'apparaissent nulle part ailleurs). Rejouable :
 * `create` purge d'abord tout reliquat.
 *
 * Vit dans `scripts/` : seul dossier de code monté dans le conteneur wpcli.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'AOD_COD_Leads' ) ) {
	WP_CLI::error( 'WooCommerce et/ou aod-cod-form (module leads) ne sont pas actifs.' );
}

$table = AOD_COD_Leads::table();

/** Méta qui marque les comptes témoins de CE seed (43.3) — base de la purge ciblée. */
const AOD_P11_MARK = '_aod_recette_p11';

/**
 * Purge idempotente : ne supprime QUE les lignes marquées (token ZZP11…).
 *
 * 🚨 Purge AUSSI la vraie commande WooCommerce qu'une conversion (Phase 39,
 * porte `abandonnees`) a créée depuis AbandonRecette : le lead témoin en porte
 * l'order_id. On ne touche QUE les commandes pointées par un token ZZP11 — jamais
 * une commande réelle du clone. La commande de conversion échappe autrement au
 * nettoyage (le seed ne connaît que les leads) et s'accumulerait à chaque run,
 * faussant les stats. ConvertiRecette porte un order_id bidon (999999) :
 * wc_get_order() rend false → sauté.
 */
function aod_p11_cleanup( $table ) {
	global $wpdb;
	$oids = $wpdb->get_col( "SELECT order_id FROM $table WHERE token LIKE 'ZZP11%' AND order_id IS NOT NULL AND order_id > 0" ); // phpcs:ignore WordPress.DB
	foreach ( $oids as $oid ) {
		if ( function_exists( 'wc_get_order' ) ) {
			$o = wc_get_order( (int) $oid );
			if ( $o ) {
				$o->delete( true );
			}
		}
	}

	// Confirmateurs témoins (43.3). 🚨 PURGE CIBLÉE, JAMAIS PAR RÔLE (leçon
	// D26/Phase 22) : un clone en service porte de VRAIS confirmateurs. On ne
	// supprime QUE les comptes portant AOD_P11_MARK, revérifiée juste avant le
	// geste irréversible.
	if ( function_exists( 'get_users' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$users = get_users( array(
			'meta_key'   => AOD_P11_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery
			'number'     => -1,
			'fields'     => 'all',
		) );
		foreach ( $users as $u ) {
			if ( '1' !== (string) get_user_meta( $u->ID, AOD_P11_MARK, true ) ) {
				continue; // Double contrôle avant une suppression.
			}
			wp_delete_user( $u->ID );
		}
	}

	return (int) $wpdb->query( "DELETE FROM $table WHERE token LIKE 'ZZP11%'" ); // phpcs:ignore WordPress.DB
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	WP_CLI::log( 'PURGE=' . aod_p11_cleanup( $table ) );
	return;
}
if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-phase11.php create|cleanup' );
}

global $wpdb;
aod_p11_cleanup( $table ); // Repart propre.

// Un produit existant (facultatif — sert juste la colonne « Produit »).
$pid = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' LIMIT 1" );

$now = current_time( 'mysql' );
$old = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 3600 ); // 1 h → abandonné.

$wpdb->insert( $table, array( 'token' => 'ZZP11_ab', 'product_id' => $pid, 'name' => 'AbandonRecette', 'phone' => '0551110011', 'wilaya' => 16, 'commune' => 'Bab Ezzouar', 'delivery' => 'home', 'qty' => 2, 'status' => 'pending', 'created_at' => $old, 'updated_at' => $old ) );
$wpdb->insert( $table, array( 'token' => 'ZZP11_rc', 'product_id' => $pid, 'name' => 'EnCoursRecette', 'phone' => '0770002233', 'wilaya' => 31, 'commune' => 'Oran', 'delivery' => 'desk', 'qty' => 1, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now ) );
$wpdb->insert( $table, array( 'token' => 'ZZP11_cv', 'product_id' => $pid, 'name' => 'ConvertiRecette', 'phone' => '0660009988', 'wilaya' => 16, 'commune' => 'Alger', 'delivery' => 'home', 'qty' => 1, 'status' => 'converted', 'order_id' => 999999, 'created_at' => $old, 'updated_at' => $old ) );

// 39.4 : prospect dont le produit N'EXISTE PLUS (product_id pointant un post
// inexistant) — mais avec un téléphone. Le bouton « Commander » DOIT apparaître et
// la conversion DOIT réussir (squelette sans ligne d'article, composé au téléphone).
$wpdb->insert( $table, array( 'token' => 'ZZP11_ps', 'product_id' => 987654, 'name' => 'ProduitSupprimeRecette', 'phone' => '0551117788', 'wilaya' => 16, 'commune' => 'Bab Ezzouar', 'delivery' => 'home', 'qty' => 1, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now ) );

// Filtre par date (43.1) : date FIXE, distincte au jour près de $now/$old.
$aod_p11_fd_date = '2024-06-15 10:00:00';
$wpdb->insert( $table, array( 'token' => 'ZZP11_fd', 'product_id' => $pid, 'name' => 'FiltreDateRecette', 'phone' => '0551119900', 'wilaya' => 16, 'commune' => 'Alger', 'delivery' => 'home', 'qty' => 1, 'status' => 'pending', 'created_at' => $aod_p11_fd_date, 'updated_at' => $aod_p11_fd_date ) );

/* ------------------------------------------------------------------ */
/* Assignation des prospects (43.3) : 2 confirmateurs + 2 leads dédiés  */
/* ------------------------------------------------------------------ */

/**
 * Confirmateur témoin : ACTIF, porteur de `aod_cap_confirm_orders` ET
 * `aod_cap_abandoned` — sans cette seconde cap, le routeur lui refuserait la
 * page et le témoin d'identité (« un confirmateur ne voit ni le sélecteur ni
 * les noms ») n'aurait rien prouvé : il aurait été vert parce que la page
 * entière est absente.
 */
function aod_p11_creer_confirmateur( $login ) {
	$pass = wp_generate_password( 24, false );
	$uid  = wp_insert_user( array(
		'user_login'   => $login,
		'user_pass'    => $pass,
		'user_email'   => $login . '@example.invalid',
		'display_name' => 'Recette ' . $login,
		'role'         => AOD_CD_ROLE_CONFIRMER,
	) );
	if ( is_wp_error( $uid ) ) {
		WP_CLI::error( 'Création de « ' . $login . ' » impossible : ' . $uid->get_error_message() );
	}
	update_user_meta( $uid, AOD_P11_MARK, '1' );
	$user = new WP_User( $uid );
	$user->add_cap( 'aod_cap_confirm_orders' );
	$user->add_cap( 'aod_cap_abandoned' );
	if ( ! $user->has_cap( 'aod_cap_abandoned' ) || ! $user->has_cap( AOD_CD_CAP ) ) {
		WP_CLI::error( '« ' . $login . ' » : capability posée sans effet (seed cassé).' );
	}
	return array( 'id' => (int) $uid, 'pass' => $pass );
}

$aod_p11_ca = aod_p11_creer_confirmateur( 'aod_recette_conf11a' );
$aod_p11_cb = aod_p11_creer_confirmateur( 'aod_recette_conf11b' );

// Leads DÉDIÉS à 43.3 : jamais convertis ni supprimés par les autres scénarios
// de la porte (sinon les assertions dépendraient de l'ordre du fichier).
$wpdb->insert( $table, array( 'token' => 'ZZP11_asa', 'product_id' => $pid, 'name' => 'AssignRecetteA', 'phone' => '0551112211', 'wilaya' => 16, 'commune' => 'Alger', 'delivery' => 'home', 'qty' => 1, 'status' => 'pending', 'assigned_confirmer' => $aod_p11_ca['id'], 'created_at' => $now, 'updated_at' => $now ) );
$wpdb->insert( $table, array( 'token' => 'ZZP11_asb', 'product_id' => $pid, 'name' => 'AssignRecetteB', 'phone' => '0551112212', 'wilaya' => 16, 'commune' => 'Alger', 'delivery' => 'home', 'qty' => 1, 'status' => 'pending', 'assigned_confirmer' => $aod_p11_cb['id'], 'created_at' => $now, 'updated_at' => $now ) );

// Lead NON assigné, dédié à l'assignation AU NAVIGATEUR (le widget agit dessus).
$wpdb->insert( $table, array( 'token' => 'ZZP11_asn', 'product_id' => $pid, 'name' => 'AssignRecetteLibre', 'phone' => '0551112213', 'wilaya' => 16, 'commune' => 'Alger', 'delivery' => 'home', 'qty' => 1, 'status' => 'pending', 'assigned_confirmer' => 0, 'created_at' => $now, 'updated_at' => $now ) );

WP_CLI::log( 'P11=1' );
// 43.3 — le <select> du filtre et le widget portent l'ID NUMÉRIQUE du compte,
// pas son login : sans ces lignes, la porte devrait requêter l'utilisateur
// elle-même (patron identique à recette-seed-assignation.php).
WP_CLI::log( 'P11_UID_A=' . $aod_p11_ca['id'] );
WP_CLI::log( 'P11_UID_B=' . $aod_p11_cb['id'] );
WP_CLI::log( 'P11_LOGIN_A=aod_recette_conf11a' );
WP_CLI::log( 'P11_PASS_A=' . $aod_p11_ca['pass'] );
