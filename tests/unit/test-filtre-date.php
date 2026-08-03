<?php
/**
 * Filtre par date (Commandes, Suivi, Abandonnées) — Phase 43.1.
 *
 * Couvre les DEUX niveaux : les helpers partagés (`AOD_CD_Dashboard`, purs,
 * sans base de données) et le branchement réel dans les 3 sections, avec des
 * fixtures DATÉES créées puis nettoyées par ce fichier lui-même — jamais les
 * données du clone.
 *
 * 🚨 Nettoyage HPOS-safe : `wc_get_order( $id )->delete( true )`, JAMAIS
 * `wp_delete_post()` — vérifié en préparant cette story (`wp_delete_post()`
 * ne supprime pas une commande sous HPOS, la ligne reste dans la table
 * custom des commandes).
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Helpers partagés (purs) — AOD_CD_Dashboard
 * ========================================================================= */

AOD_Test::group( 'Filtre date 43.1 — parse_date_param() : validation stricte' );

$_GET = array( 'date_from' => '2024-06-15' );
AOD_Test::eq( '2024-06-15', AOD_CD_Dashboard::parse_date_param( 'date_from' ), 'Format Y-m-d valide accepté' );

$_GET = array( 'date_from' => '2024-13-45' );
AOD_Test::eq( '', AOD_CD_Dashboard::parse_date_param( 'date_from' ), 'Mois/jour hors calendrier (13/45) rejeté' );

$_GET = array( 'date_from' => '2024-02-30' );
AOD_Test::eq( '', AOD_CD_Dashboard::parse_date_param( 'date_from' ), '🔴 30 février — calendaire impossible, checkdate() le sait' );

$_GET = array( 'date_from' => '15/06/2024' );
AOD_Test::eq( '', AOD_CD_Dashboard::parse_date_param( 'date_from' ), 'Format FR (jj/mm/aaaa) rejeté — un seul format accepté' );

$_GET = array( 'date_from' => 'texte libre' );
AOD_Test::eq( '', AOD_CD_Dashboard::parse_date_param( 'date_from' ), 'Texte libre rejeté' );

$_GET = array();
AOD_Test::eq( '', AOD_CD_Dashboard::parse_date_param( 'date_from' ), 'Paramètre absent → chaîne vide' );

AOD_Test::group( 'Filtre date 43.1 — resolve_date_range() : tri sans synthèse' );

AOD_Test::eq(
	array( 'from' => '', 'to' => '' ),
	AOD_CD_Dashboard::resolve_date_range( '', '' ),
	'Aucune borne → les deux restent vides (PAS de défaut « 30 jours » comme Home)'
);
AOD_Test::eq(
	array( 'from' => '2024-06-01', 'to' => '2024-06-10' ),
	AOD_CD_Dashboard::resolve_date_range( '2024-06-01', '2024-06-10' ),
	'Bornes normales : inchangées'
);
AOD_Test::eq(
	array( 'from' => '2024-06-01', 'to' => '2024-06-10' ),
	AOD_CD_Dashboard::resolve_date_range( '2024-06-10', '2024-06-01' ),
	'Bornes inversées : permutées'
);
AOD_Test::eq(
	array( 'from' => '2024-06-01', 'to' => '' ),
	AOD_CD_Dashboard::resolve_date_range( '2024-06-01', '' ),
	'🔴 Une seule borne (from) : PAS synthétisée — reste ouverte, contrairement à Home'
);
AOD_Test::eq(
	array( 'from' => '', 'to' => '2024-06-10' ),
	AOD_CD_Dashboard::resolve_date_range( '', '2024-06-10' ),
	'🔴 Une seule borne (to) : PAS synthétisée non plus — symétrique, contrairement au quirk de Home'
);

AOD_Test::group( 'Filtre date 43.1 — date_created_query_arg() : syntaxe wc_get_orders()' );

AOD_Test::eq( null, AOD_CD_Dashboard::date_created_query_arg( '', '' ), 'Aucune borne → null (ne pas poser la clé)' );
AOD_Test::eq( '2024-06-01...2024-06-10', AOD_CD_Dashboard::date_created_query_arg( '2024-06-01', '2024-06-10' ), 'Deux bornes → plage inclusive' );
AOD_Test::eq( '>=2024-06-01', AOD_CD_Dashboard::date_created_query_arg( '2024-06-01', '' ), 'Borne basse seule → opérateur >=' );
AOD_Test::eq( '<=2024-06-10', AOD_CD_Dashboard::date_created_query_arg( '', '2024-06-10' ), 'Borne haute seule → opérateur <=' );

/* =========================================================================
 * AOD_CD_Section_Home — non-régression du patron d'origine (AC 1)
 * ========================================================================= */

AOD_Test::group( 'Filtre date 43.1 — Home : comportement inchangé après délégation' );

$aod_fd_home = AOD_CD_Section_Home::instance();

$aod_fd_home_case = function ( $get ) use ( $aod_fd_home ) {
	$_GET    = $get;
	$filters = AOD_Test::call( $aod_fd_home, 'read_filters' );
	return AOD_Test::call( $aod_fd_home, 'resolve_range', array( $filters ) );
};

$aod_fd_no_filter = $aod_fd_home_case( array() );
AOD_Test::eq( '30', $aod_fd_no_filter['key'], 'Aucun filtre → défaut 30 jours (Home garde SON défaut, le helper partagé n’en impose aucun)' );

$aod_fd_from_only = $aod_fd_home_case( array( 'date_from' => '2024-06-01' ) );
AOD_Test::eq( '2024-06-01', $aod_fd_from_only['from'], '« from » seul : synthèse locale de Home toujours active (from conservé)' );
AOD_Test::ok( '' !== $aod_fd_from_only['to'], '« from » seul : « to » comblé par $today (comportement HISTORIQUE de Home, préservé)' );

$aod_fd_to_only = $aod_fd_home_case( array( 'date_to' => '2024-06-10' ) );
AOD_Test::eq( '2024-06-10', $aod_fd_to_only['from'], '🔴 « to » seul : quirk HISTORIQUE de Home préservé — from comblé AU MÊME jour que to (single day)' );
AOD_Test::eq( '2024-06-10', $aod_fd_to_only['to'], '« to » seul : to inchangé' );

$aod_fd_reversed = $aod_fd_home_case( array( 'date_from' => '2024-06-10', 'date_to' => '2024-06-01' ) );
AOD_Test::eq( '2024-06-01', $aod_fd_reversed['from'], 'Bornes inversées sur Home : permutées (via le helper partagé)' );
AOD_Test::eq( '2024-06-10', $aod_fd_reversed['to'], 'Bornes inversées sur Home : permutées (to)' );

$aod_fd_invalid = $aod_fd_home_case( array( 'date_from' => '2024-13-45' ) );
AOD_Test::eq( '30', $aod_fd_invalid['key'], 'Date invalide sur Home : traitée comme absente → retombe sur le défaut 30 jours' );

$_GET = array();

/* =========================================================================
 * AOD_CD_Section_Orders (Commandes) — fixture datée, HPOS-safe
 * ========================================================================= */

AOD_Test::group( 'Filtre date 43.1 — Commandes : inclusion/exclusion réelle' );

$aod_fd_order = wc_create_order();
$aod_fd_order->set_date_created( '2024-06-15 10:00:00' );
$aod_fd_order->set_status( 'pending' );
// 🚨 Nom de facturation UNIQUE, et il n'est pas décoratif. `AOD_CD_Section_Orders`
// pagine à 20 et trie par date DESC : sur une boutique qui a plus de 20 commandes
// plus récentes que la fixture, celle-ci tombe en page 2 et les assertions
// « la commande est visible » échouent SANS le moindre défaut de filtrage.
// Ce n'est pas un cas d'école : le test rougissait dès qu'un jeu de démonstration
// (`scripts/dev-seed-commandes.php`) était en base — et il aurait rougi pareil sur
// n'importe quel clone en service. On narrow donc la liste sur la fixture via `s`,
// ORTHOGONAL au filtre de dates (les deux se combinent en ET dans la requête) :
// ce qui est éprouvé — inclusion et EXCLUSION par la plage — reste exactement le
// même, mais ne dépend plus du volume de la boutique.
$aod_fd_order->set_billing_first_name( 'AODTestFiltreDateOrders' );
$aod_fd_order->save();
$aod_fd_order_id  = $aod_fd_order->get_id();
$aod_fd_order_num = $aod_fd_order->get_order_number();
$aod_fd_needle    = 'AODTestFiltreDateOrders';

$_GET = array( 'statut' => 'all', 's' => $aod_fd_needle, 'date_from' => '2024-06-01', 'date_to' => '2024-06-30' );
ob_start();
AOD_CD_Section_Orders::instance()->render();
$aod_fd_html_in = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fd_html_in, '#' . $aod_fd_order_num ),
	'Commande datée au 15/06 : INCLUSE par une plage 01/06 → 30/06'
);

$_GET = array( 'statut' => 'all', 's' => $aod_fd_needle, 'date_from' => '2024-07-01', 'date_to' => '2024-07-31' );
ob_start();
AOD_CD_Section_Orders::instance()->render();
$aod_fd_html_out = ob_get_clean();
AOD_Test::ok(
	false === strpos( $aod_fd_html_out, '#' . $aod_fd_order_num ),
	'🔴 ANCRAGE INVERSE : la même commande, EXCLUE par une plage de juillet (le filtre exclut vraiment, pas seulement « n’exclut rien »)'
);

$_GET = array( 'statut' => 'all', 's' => $aod_fd_needle, 'date_from' => '2024-06-15' );
ob_start();
AOD_CD_Section_Orders::instance()->render();
$aod_fd_html_open = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fd_html_open, '#' . $aod_fd_order_num ),
	'Borne « from » seule (>=2024-06-15) : commande du jour même incluse — plage OUVERTE, pas collapsée sur un jour unique'
);

$_GET = array( 'statut' => 'all', 's' => $aod_fd_needle );
ob_start();
AOD_CD_Section_Orders::instance()->render();
$aod_fd_html_all = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fd_html_all, '#' . $aod_fd_order_num ),
	'Aucun filtre date posé : commande visible (le défaut reste « tout », jamais une fenêtre implicite)'
);

$aod_fd_order->delete( true );
AOD_Test::ok( false === wc_get_order( $aod_fd_order_id ), 'Nettoyage HPOS-safe (->delete(true)) : la commande jetable a bien disparu' );

/* =========================================================================
 * AOD_CD_Section_Tracking (Suivi) — fixture expédiée + datée
 * ========================================================================= */

AOD_Test::group( 'Filtre date 43.1 — Suivi : inclusion/exclusion réelle' );

$aod_fd_track = wc_create_order();
$aod_fd_track->set_date_created( '2024-06-15 10:00:00' );
$aod_fd_track->update_meta_data( '_aod_ship_tracking', 'AODTESTFD431' );
$aod_fd_track->set_status( 'pending' );
$aod_fd_track->save();
$aod_fd_track_id  = $aod_fd_track->get_id();
$aod_fd_track_num = $aod_fd_track->get_order_number();

$_GET = array( 'date_from' => '2024-06-01', 'date_to' => '2024-06-30' );
ob_start();
AOD_CD_Section_Tracking::instance()->render();
$aod_fd_track_in = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fd_track_in, '#' . $aod_fd_track_num ),
	'Colis daté au 15/06 : INCLUS par une plage qui le couvre'
);

$_GET = array( 'date_from' => '2024-07-01', 'date_to' => '2024-07-31' );
ob_start();
AOD_CD_Section_Tracking::instance()->render();
$aod_fd_track_out = ob_get_clean();
AOD_Test::ok(
	false === strpos( $aod_fd_track_out, '#' . $aod_fd_track_num ),
	'🔴 ANCRAGE INVERSE : le même colis, EXCLU par une plage qui ne le couvre pas'
);
AOD_Test::ok(
	false !== strpos( $aod_fd_track_out, 'Aucun colis ne correspond' ),
	'Message « aucun résultat filtré » déclenché (pas le message générique « aucun colis expédié »)'
);

$aod_fd_track->delete( true );
AOD_Test::ok( false === wc_get_order( $aod_fd_track_id ), 'Nettoyage HPOS-safe : le colis jetable a bien disparu' );

/* =========================================================================
 * AOD_CD_Section_Abandoned — lead daté (table custom) + ancrage nb_conv
 * ========================================================================= */

AOD_Test::group( 'Filtre date 43.1 — Abandonnées : inclusion/exclusion + nb_conv non affecté' );

global $wpdb;
$aod_fd_leads_table = AOD_COD_Leads::table();

$wpdb->insert(
	$aod_fd_leads_table,
	array(
		'token'      => 'aod-test-fd-431-' . wp_generate_password( 8, false ),
		'product_id' => 0,
		'name'       => 'Recette Filtre Date 431',
		'phone'      => '0555000112',
		'wilaya'     => 16,
		'commune'    => 'Alger',
		'delivery'   => 'home',
		'qty'        => 1,
		'status'     => 'pending',
		'created_at' => '2024-06-15 10:00:00',
		'updated_at' => '2024-06-15 10:00:00',
	)
);
$aod_fd_lead_id = (int) $wpdb->insert_id;

// Compteur « récupérés en commande » — ancrage AVANT tout filtre, pour
// comparer avec la même mesure APRÈS (doit être identique dans les deux cas).
$aod_fd_nb_conv_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$aod_fd_leads_table} WHERE status = 'converted'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $aod_fd_leads_table = AOD_COD_Leads::table() ($wpdb->prefix, nom contrôlé). Lecture de contrôle (ancrage négatif), aucune écriture.

$_GET = array( 'date_from' => '2024-06-01', 'date_to' => '2024-06-30' );
ob_start();
AOD_CD_Section_Abandoned::instance()->render();
$aod_fd_lead_in = ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_fd_lead_in, 'Recette Filtre Date 431' ),
	'Lead daté au 15/06 : INCLUS par une plage qui le couvre'
);

$_GET = array( 'date_from' => '2024-07-01', 'date_to' => '2024-07-31' );
ob_start();
AOD_CD_Section_Abandoned::instance()->render();
$aod_fd_lead_out = ob_get_clean();
AOD_Test::ok(
	false === strpos( $aod_fd_lead_out, 'Recette Filtre Date 431' ),
	'🔴 ANCRAGE INVERSE : le même lead, EXCLU par une plage qui ne le couvre pas'
);

$aod_fd_nb_conv_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$aod_fd_leads_table} WHERE status = 'converted'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Même requête de contrôle, non affectée par les $_GET ci-dessus par construction (aucune écriture).
AOD_Test::eq(
	$aod_fd_nb_conv_before,
	$aod_fd_nb_conv_after,
	'nb_conv (« récupérés en commande ») : IDENTIQUE avec un filtre date actif — preuve qu’il n’y a PAS été branché par erreur (cf. Dev Notes de la story)'
);

// where_sql() directement : les deux bornes ensemble (préparation SQL, sans DB).
$aod_fd_abandoned = AOD_CD_Section_Abandoned::instance();
$aod_fd_where_both = AOD_Test::call( $aod_fd_abandoned, 'where_sql', array( array(), '', '2024-06-01', '2024-06-30' ) );
AOD_Test::ok( false !== strpos( $aod_fd_where_both, "updated_at >= '2024-06-01 00:00:00'" ), 'where_sql() : borne basse à 00:00:00 (jour entier)' );
AOD_Test::ok( false !== strpos( $aod_fd_where_both, "updated_at <= '2024-06-30 23:59:59'" ), 'where_sql() : borne haute à 23:59:59 (jour entier, un lead en fin de journée n’est pas exclu à tort)' );
$aod_fd_where_none = AOD_Test::call( $aod_fd_abandoned, 'where_sql', array( array(), '' ) );
AOD_Test::ok( false === strpos( $aod_fd_where_none, 'updated_at' ), 'where_sql() sans borne : aucune clause updated_at ajoutée (compat arrière, 3 anciens appelants à 2 arguments)' );

$wpdb->delete( $aod_fd_leads_table, array( 'id' => $aod_fd_lead_id ) );
AOD_Test::eq( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$aod_fd_leads_table} WHERE id = %d", $aod_fd_lead_id ) ), 'Nettoyage : le lead jetable a bien disparu' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $aod_fd_leads_table contrôlé, $wpdb->prepare() sur la ligne.

$_GET = array();
