<?php
/**
 * Assignation des paniers abandonnés — Phase 43.3.
 *
 * Ce que ce cliquet garde, et que rien d'autre ne garde :
 *
 *  R1. Le SCHÉMA — la colonne `assigned_confirmer` existe réellement sur la
 *      table `aod_cod_leads`. Sans elle, tout le reste de la story est du
 *      code qui écrit dans le vide.
 *  R2. `AOD_CD_Roles::valid_confirmer()` — le helper PARTAGÉ extrait de
 *      `Section_Orders` : mêmes cas de confiance zéro qu'avant l'extraction
 *      (non-régression du chemin commandes, qui délègue désormais ici).
 *  R3. `apply_lead_assignment()` — assigne / réassigne / désassigne, et
 *      REFUSE un ID hors vivier. ⚠️ Différence VOULUE avec les filtres
 *      (43.1/43.2) : un filtre GET ignore silencieusement un ID invalide,
 *      une ÉCRITURE le refuse.
 *  R4. La PROPAGATION à la conversion — le point le plus subtil de la story :
 *      une pré-assignation du gérant ne doit pas être écrasée par le
 *      confirmateur qui décroche le téléphone.
 *  R5. Le FILTRE par confirmateur sur Abandonnées (reprise de 43.2, possible
 *      seulement depuis que la colonne existe) — ancrage inverse.
 *  R6. Le GATE GÉRANT — ni sélecteur, ni colonne, ni nom de confirmateur pour
 *      un confirmateur, paramètre posté ignoré.
 *
 * 🚨 Nettoyage HPOS-safe : `wc_get_order( $id )->delete( true )`, jamais
 * `wp_delete_post()` (piège documenté en 43.1). Les leads, eux, sont des
 * lignes d'une table maison : purge par `$wpdb->delete()` sur leur id.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AOD_CD_Roles' ) || ! class_exists( 'AOD_CD_Section_Abandoned' ) || ! class_exists( 'AOD_COD_Leads' ) ) {
	return;
}

global $wpdb;

$aod_aa_user_avant = get_current_user_id();
$aod_aa_sec        = AOD_CD_Section_Abandoned::instance();
$aod_aa_ref        = new ReflectionObject( $aod_aa_sec );
$aod_aa_table      = AOD_COD_Leads::table();

/** Appelle une méthode protégée de la section (patron `AOD_Test::call`). */
$aod_aa_call = function ( $name, $args = array() ) use ( $aod_aa_ref, $aod_aa_sec ) {
	$m = $aod_aa_ref->getMethod( $name );
	$m->setAccessible( true );
	return $m->invokeArgs( $aod_aa_sec, $args );
};

$aod_aa_gerants = get_users( array( 'role__in' => array( 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );
if ( empty( $aod_aa_gerants ) ) {
	AOD_Test::group( 'Assignation abandonnées 43.3' );
	AOD_Test::ok( false, 'Aucun compte administrateur trouvé sur ce clone — impossible de tester en gérant' );
	$_GET = array();
	return;
}
$aod_aa_gerant_id = (int) $aod_aa_gerants[0];
wp_set_current_user( $aod_aa_gerant_id );

/* =========================================================================
 * R1 — Le schéma porte bien la colonne (sinon tout le reste écrit dans le vide)
 * ========================================================================= */

AOD_Test::group( 'Assignation abandonnées 43.3 — R1 : schéma' );

$aod_aa_cols = $wpdb->get_col( "DESCRIBE $aod_aa_table", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = $wpdb->prefix (nom contrôlé, non paramétrable). Table custom : pas d'API WP, cache non pertinent.
AOD_Test::ok(
	in_array( 'assigned_confirmer', (array) $aod_aa_cols, true ),
	'La colonne `assigned_confirmer` existe sur la table aod_cod_leads (migration 43.3)',
	'sans elle, apply_lead_assignment() écrirait dans le vide et le filtre ne filtrerait rien'
);
AOD_Test::ok(
	in_array( 'assigned_by', (array) $aod_aa_cols, true ),
	'La colonne `assigned_by` existe sur la table aod_cod_leads (migration 43.4)',
	'sans elle, build_skeleton_order() ne peut pas reporter l’origine de l’assignation : une commande née d’un prospect pré-assigné par le gérant se lirait « automatique »'
);
AOD_Test::eq(
	'3',
	(string) AOD_COD_Leads::DB_VERSION,
	'DB_VERSION est bien passée à « 3 » (sans quoi maybe_migrate() ne rejouerait jamais dbDelta sur un clone existant)'
);

/* =========================================================================
 * Fixtures : 2 confirmateurs actifs + 2 prospects, un par confirmateur
 * ========================================================================= */

$aod_aa_make_confirmer = function ( $login ) {
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

$aod_aa_a = $aod_aa_make_confirmer( 'aod_test_aa_a' );
$aod_aa_b = $aod_aa_make_confirmer( 'aod_test_aa_b' );

// Confirmateur DÉSACTIVÉ (l'Équipe le désactive sans supprimer le compte) : il
// doit être refusé par valid_confirmer() — c'est le seul des quatre cas de
// confiance zéro qui ne se voit NI au rôle NI à l'existence du compte.
//
// 🚨 `add_cap( AOD_CD_CAP, false )`, PAS `remove_cap()` — et la nuance n'est pas
// cosmétique : le RÔLE confirmateur accorde lui-même AOD_CD_CAP
// (`create_confirmer_role()`). `remove_cap()` ne retire qu'une cap posée AU
// NIVEAU UTILISATEUR ; la cap du rôle reprend aussitôt le dessus et le compte
// reste actif. Seul un DENY explicite au niveau utilisateur bat le rôle. C'est
// exactement ce que fait la section Équipe (`class-aod-cd-section-team.php:354`)
// — reproduire son geste, pas en inventer un plus intuitif, sinon ce cliquet
// éprouverait un état qui n'existe sur aucun clone.
$aod_aa_inactif = $aod_aa_make_confirmer( 'aod_test_aa_off' );
if ( $aod_aa_inactif ) {
	( new WP_User( $aod_aa_inactif ) )->add_cap( AOD_CD_CAP, false );
}

$aod_aa_make_lead = function ( $token, $name, $assigned ) use ( $wpdb, $aod_aa_table ) {
	$wpdb->insert( $aod_aa_table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table custom aod_cod_leads : pas d'API WP, cache non pertinent. $wpdb->insert() prépare les valeurs.
		'token'              => $token,
		'product_id'         => 0,
		'name'               => $name,
		'phone'              => '0555000111',
		'wilaya'             => 16,
		'commune'            => 'Alger',
		'delivery'           => 'home',
		'qty'                => 1,
		'status'             => 'pending',
		'assigned_confirmer' => $assigned,
		'created_at'         => current_time( 'mysql' ),
		'updated_at'         => current_time( 'mysql' ),
	) );
	return (int) $wpdb->insert_id;
};

$aod_aa_lead_a = $aod_aa_make_lead( 'zzAA43a', 'ProspectAssignA', $aod_aa_a );
$aod_aa_lead_b = $aod_aa_make_lead( 'zzAA43b', 'ProspectAssignB', $aod_aa_b );

/** Relit la colonne d'assignation d'un prospect. */
$aod_aa_col = function ( $lead_id ) use ( $wpdb, $aod_aa_table ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT assigned_confirmer FROM $aod_aa_table WHERE id = %d", $lead_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = $wpdb->prefix (nom contrôlé) ; le prepare() borne l'id. Table custom : pas d'API WP, cache non pertinent.
};

/* =========================================================================
 * R2 — Le helper PARTAGÉ valid_confirmer() (non-régression du chemin commandes)
 * ========================================================================= */

AOD_Test::group( 'Assignation abandonnées 43.3 — R2 : AOD_CD_Roles::valid_confirmer() (confiance zéro)' );

AOD_Test::ok(
	AOD_CD_Roles::valid_confirmer( $aod_aa_a ) instanceof WP_User,
	'Un confirmateur ACTIF porteur de aod_cap_confirm_orders est accepté'
);
AOD_Test::ok(
	false === AOD_CD_Roles::valid_confirmer( 999999999 ),
	'🔴 Un ID inexistant est refusé'
);
AOD_Test::ok(
	false === AOD_CD_Roles::valid_confirmer( $aod_aa_gerant_id ),
	'🔴 Le GÉRANT lui-même est refusé — il n’est pas un confirmateur (rôle), quelles que soient ses capabilities'
);
AOD_Test::ok(
	false === AOD_CD_Roles::valid_confirmer( $aod_aa_inactif ),
	'🔴 Un confirmateur DÉSACTIVÉ (AOD_CD_CAP retirée par l’Équipe) est refusé — le compte existe et porte le rôle, seule la cap le distingue'
);

// Le chemin COMMANDES délègue désormais ici : il doit rendre EXACTEMENT la même
// chose. Sans cette assertion, l'extraction pourrait diverger sans bruit.
if ( class_exists( 'AOD_CD_Section_Orders' ) ) {
	$aod_aa_orders_ref = new ReflectionObject( AOD_CD_Section_Orders::instance() );
	$aod_aa_vc         = $aod_aa_orders_ref->getMethod( 'valid_confirmer' );
	$aod_aa_vc->setAccessible( true );
	$aod_aa_delegue = $aod_aa_vc->invokeArgs( AOD_CD_Section_Orders::instance(), array( $aod_aa_a ) );
	AOD_Test::ok(
		$aod_aa_delegue instanceof WP_User && (int) $aod_aa_delegue->ID === $aod_aa_a,
		'Section_Orders::valid_confirmer() délègue au helper partagé et rend le MÊME utilisateur (non-régression 42.4)'
	);
	AOD_Test::ok(
		false === $aod_aa_vc->invokeArgs( AOD_CD_Section_Orders::instance(), array( $aod_aa_inactif ) ),
		'…et refuse les mêmes comptes (confirmateur désactivé)'
	);
}

/* =========================================================================
 * R3 — apply_lead_assignment() : assigne / réassigne / refuse / désassigne
 * ========================================================================= */

AOD_Test::group( 'Assignation abandonnées 43.3 — R3 : apply_lead_assignment()' );

$aod_aa_r = $aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_b, $aod_aa_a ) );
AOD_Test::ok( true === $aod_aa_r['ok'], 'Assignation à un confirmateur valide : acceptée' );
AOD_Test::eq( $aod_aa_a, $aod_aa_col( $aod_aa_lead_b ), '…et la colonne porte bien son ID' );

// RÉASSIGNATION sans restriction (invariant de la story : c'est un routage de
// visibilité, jamais un verrou — même règle que les commandes en 42.4).
$aod_aa_r = $aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_b, $aod_aa_b ) );
AOD_Test::ok( true === $aod_aa_r['ok'], 'Réassignation à un AUTRE confirmateur : acceptée (aucune restriction)' );
AOD_Test::eq( $aod_aa_b, $aod_aa_col( $aod_aa_lead_b ), '…et la colonne suit' );

// 🔴 LE point qui distingue une écriture d'un filtre : ici on REFUSE.
$aod_aa_r = $aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_b, $aod_aa_gerant_id ) );
AOD_Test::ok(
	false === $aod_aa_r['ok'],
	'🔴 ID hors vivier : REFUSÉ (une écriture refuse, là où un filtre GET ignore silencieusement — différence voulue)'
);
AOD_Test::eq(
	$aod_aa_b,
	$aod_aa_col( $aod_aa_lead_b ),
	'…et la colonne est restée INCHANGÉE (un refus ne doit rien écrire au passage)'
);

$aod_aa_r = $aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_b, 0 ) );
AOD_Test::ok( true === $aod_aa_r['ok'], 'confirmer_id = 0 : dé-assignation acceptée' );
AOD_Test::eq( 0, $aod_aa_col( $aod_aa_lead_b ), '…et la colonne revient à 0 (jamais NULL — 0 EST l’absence, cf. schéma)' );

$aod_aa_r = $aod_aa_call( 'apply_lead_assignment', array( 999999999, $aod_aa_a ) );
AOD_Test::ok( false === $aod_aa_r['ok'], '🔴 Prospect inexistant : refusé' );

// Remise en état de la fixture pour la suite (R5 attend B sur ce prospect).
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_b, $aod_aa_b ) );

/* =========================================================================
 * R4 — Propagation à la CONVERSION : une pré-assignation ne s'écrase pas
 * ========================================================================= */

AOD_Test::group( 'Assignation abandonnées 43.3 — R4 : propagation à la conversion' );

$aod_aa_convertir = function ( $lead_id, $converter_id ) use ( $wpdb, $aod_aa_table, $aod_aa_call ) {
	$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $aod_aa_table WHERE id = %d", $lead_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = $wpdb->prefix (nom contrôlé) ; le prepare() borne l'id. Table custom : pas d'API WP, cache non pertinent.
	wp_set_current_user( $converter_id );
	$order = $aod_aa_call( 'build_skeleton_order', array( $lead, null ) );
	$aod_aa_call( 'maybe_assign_converter', array( $order ) );
	return wc_get_order( $order->get_id() );
};

// (a) Prospect PRÉ-ASSIGNÉ à A, converti par B → la commande reste à A.
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_a, $aod_aa_a ) );
$aod_aa_o1 = $aod_aa_convertir( $aod_aa_lead_a, $aod_aa_b );
AOD_Test::eq(
	$aod_aa_a,
	(int) $aod_aa_o1->get_meta( '_aod_assigned_confirmer' ),
	'🔴 Prospect pré-assigné à A, converti PAR B : la commande reste assignée à A — la pré-assignation du gérant prime (changement de contrat 43.3)'
);
$aod_aa_o1->delete( true );

// (b) Prospect NON assigné, converti par B → comportement 42.2 INCHANGÉ.
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_a, 0 ) );
$aod_aa_o2 = $aod_aa_convertir( $aod_aa_lead_a, $aod_aa_b );
AOD_Test::eq(
	$aod_aa_b,
	(int) $aod_aa_o2->get_meta( '_aod_assigned_confirmer' ),
	'🔴 ANCRAGE INVERSE : prospect NON assigné, converti par B → la commande revient à B (comportement 42.2 préservé, la garde ne bloque pas tout)'
);
$aod_aa_o2->delete( true );

// (c) Prospect NON assigné, converti par le GÉRANT → rien n'est posé.
$aod_aa_o3 = $aod_aa_convertir( $aod_aa_lead_a, $aod_aa_gerant_id );
AOD_Test::eq(
	0,
	(int) $aod_aa_o3->get_meta( '_aod_assigned_confirmer' ),
	'Prospect non assigné converti par le GÉRANT : commande non assignée (le gérant n’est pas un confirmateur — 42.2 inchangé)'
);
$aod_aa_o3->delete( true );

wp_set_current_user( $aod_aa_gerant_id );
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_a, $aod_aa_a ) );

/* =========================================================================
 * R5 — Filtre par confirmateur sur Abandonnées (reprise de 43.2)
 * ========================================================================= */

AOD_Test::group( 'Assignation abandonnées 43.3 — R5 : filtre par confirmateur (ancrage inverse)' );

$aod_aa_render = function ( $get ) use ( $aod_aa_sec ) {
	$_GET = $get;
	ob_start();
	$aod_aa_sec->render();
	return ob_get_clean();
};

$aod_aa_html_all = $aod_aa_render( array() );
AOD_Test::ok(
	false !== strpos( $aod_aa_html_all, 'ProspectAssignA' ) && false !== strpos( $aod_aa_html_all, 'ProspectAssignB' ),
	'ANCRAGE : sans filtre, les deux prospects sont visibles (sinon tout le reste serait vrai par vacuité)'
);

$aod_aa_html_a = $aod_aa_render( array( 'confirmateur' => $aod_aa_a ) );
AOD_Test::ok(
	false !== strpos( $aod_aa_html_a, 'ProspectAssignA' ) && false === strpos( $aod_aa_html_a, 'ProspectAssignB' ),
	'Filtre sur A : son prospect seul (B exclu)'
);

$aod_aa_html_b = $aod_aa_render( array( 'confirmateur' => $aod_aa_b ) );
AOD_Test::ok(
	false !== strpos( $aod_aa_html_b, 'ProspectAssignB' ) && false === strpos( $aod_aa_html_b, 'ProspectAssignA' ),
	'🔴 ANCRAGE INVERSE : filtrer sur B exclut A (symétrique — un filtre qui ne rendrait jamais rien passerait le test précédent)'
);

$aod_aa_html_bogus = $aod_aa_render( array( 'confirmateur' => 999999999 ) );
AOD_Test::ok(
	false !== strpos( $aod_aa_html_bogus, 'ProspectAssignA' ) && false !== strpos( $aod_aa_html_bogus, 'ProspectAssignB' ),
	'ID hors vivier : filtre traité comme ABSENT (les deux visibles) — un filtre GET n’est pas une action, il n’émet pas d’erreur'
);

/* =========================================================================
 * R6 — Gate gérant strict : ni sélecteur, ni colonne, ni nom pour un confirmateur
 * ========================================================================= */

AOD_Test::group( 'Assignation abandonnées 43.3 — R6 : gate gérant strict' );

AOD_Test::ok(
	false !== strpos( $aod_aa_html_all, 'aod-cd-leadassignsel' )
		&& false !== strpos( $aod_aa_html_all, 'id="aod-cd-f-leadconfirmer"' ),
	'ANCRAGE : en GÉRANT, le sélecteur d’assignation ET le filtre confirmateur sont bien rendus'
);

// Les classes du widget PROSPECT ne doivent JAMAIS être celles du widget
// COMMANDE : section-orders.js câble `.aod-cd-assignsel` par un querySelectorAll
// global et posterait `aod_cd_order_assign` avec un `order_id` inexistant.
AOD_Test::ok(
	false === strpos( $aod_aa_html_all, 'class="aod-cd-assignsel"' ),
	'🔴 Le widget des prospects n’emprunte PAS les classes du widget des commandes (sinon section-orders.js le câblerait au mauvais handler AJAX)'
);

wp_set_current_user( $aod_aa_a );
$aod_aa_html_conf = $aod_aa_render( array( 'confirmateur' => $aod_aa_b ) );
AOD_Test::ok(
	false === strpos( $aod_aa_html_conf, 'aod-cd-leadassignsel' ),
	'🔴 Aucun sélecteur d’assignation pour un confirmateur'
);
AOD_Test::ok(
	false === strpos( $aod_aa_html_conf, 'id="aod-cd-f-leadconfirmer"' ),
	'🔴 Aucun filtre par confirmateur pour un confirmateur'
);
AOD_Test::ok(
	false === strpos( $aod_aa_html_conf, 'Recette aod_test_aa_b' ),
	'🔴 Le NOM d’un confirmateur ne fuit jamais vers un pair (règle 42.7, transposée aux prospects)'
);
AOD_Test::ok(
	false !== strpos( $aod_aa_html_conf, 'ProspectAssignB' ),
	'…et le paramètre `confirmateur` posté est IGNORÉ : son périmètre habituel s’applique, ni élargi ni restreint'
);

wp_set_current_user( $aod_aa_gerant_id );

/* =========================================================================
 * R7 — `assigned_by` : l'ORIGINE de l'assignation survit à la conversion (43.4)
 *
 * 🚨 CE QUE CE GROUPE GARDE. Un prospect pré-assigné par le gérant, converti
 * par un confirmateur, produit une commande assignée — dont l'origine est
 * MANUELLE. Sans report, cette commande n'a aucune signature et se lit
 * « assignée automatiquement » : faux, et rien ne le signale. La colonne
 * `assigned_by` existe UNIQUEMENT pour ce pont ; elle n'est jamais affichée
 * sur cette page (toute assignation de prospect y est gérante par
 * construction, donc un « Admin » constant n'apprendrait rien).
 * ========================================================================= */

AOD_Test::group( 'Assignation abandonnées 43.4 — R7 : `assigned_by` et son report' );

wp_set_current_user( $aod_aa_gerant_id );

/** Relit la colonne de SIGNATURE d'un prospect. */
$aod_aa_col_by = function ( $lead_id ) use ( $wpdb, $aod_aa_table ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT assigned_by FROM $aod_aa_table WHERE id = %d", $lead_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = $wpdb->prefix (nom contrôlé) ; le prepare() borne l'id. Table custom : pas d'API WP, cache non pertinent.
};

// --- AC 8 : apply_lead_assignment() signe, et dé-signe ------------------
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_b, $aod_aa_a ) );
AOD_Test::eq(
	$aod_aa_gerant_id,
	$aod_aa_col_by( $aod_aa_lead_b ),
	'apply_lead_assignment() écrit `assigned_by` = l’utilisateur courant (gérant, par construction du handler)'
);
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_b, 0 ) );
AOD_Test::eq(
	0,
	$aod_aa_col_by( $aod_aa_lead_b ),
	'🔴 Dé-assignation : la signature revient à 0 en même temps (ici 0 EST l’absence — la commande, elle, SUPPRIME sa méta)'
);

// Un refus ne doit rien écrire, signature comprise.
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_b, $aod_aa_a ) );
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_b, $aod_aa_gerant_id ) ); // hors vivier → refus
AOD_Test::eq(
	$aod_aa_gerant_id,
	$aod_aa_col_by( $aod_aa_lead_b ),
	'🔴 Un ID hors vivier est refusé sans toucher à la signature en place'
);

// --- AC 6 : les trois cas du report ------------------------------------
// (a) Prospect assigné ET signé → les DEUX suivent la commande.
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_a, $aod_aa_a ) );
$aod_aa_o7a = $aod_aa_convertir( $aod_aa_lead_a, $aod_aa_b );
AOD_Test::eq(
	$aod_aa_a,
	(int) $aod_aa_o7a->get_meta( '_aod_assigned_confirmer' ),
	'ANCRAGE : la pré-assignation est bien reportée (sans quoi la signature serait vraie par vacuité)'
);
AOD_Test::eq(
	$aod_aa_gerant_id,
	(int) $aod_aa_o7a->get_meta( '_aod_assigned_by' ),
	'🔴 …et sa SIGNATURE aussi : la commande sait qu’elle vient d’un choix du gérant, pas du tourniquet'
);
AOD_Test::ok(
	false !== strpos( AOD_CD_Section_Orders::assigned_confirmer_label( $aod_aa_o7a ), 'aod-cd-assigned-by' ),
	'🔴 Bout en bout : la commande née de ce prospect affiche « Admin » sur la page Commandes'
);
AOD_Test::ok(
	(int) $aod_aa_o7a->get_meta( '_aod_assigned_by' ) !== $aod_aa_b,
	'🔴 …et JAMAIS l’ID du confirmateur qui a converti (ce serait inverser l’information)'
);
$aod_aa_o7a->delete( true );

// (b) Prospect assigné sous la 43.3 (`assigned_by` resté à 0, faute de colonne
//     à l'époque) : la garde IMBRIQUÉE doit ne RIEN poser — surtout pas 0.
//     C'est le cas de rétro-compatibilité : ces prospects ne sont pas
//     backfillés, leur commande se lira « automatique ». Assumé.
$wpdb->update( $aod_aa_table, array( 'assigned_confirmer' => $aod_aa_a, 'assigned_by' => 0 ), array( 'id' => $aod_aa_lead_a ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table custom aod_cod_leads : pas d'API WP, cache non pertinent. $wpdb->update() prépare valeurs et clause WHERE.
$aod_aa_o7b = $aod_aa_convertir( $aod_aa_lead_a, $aod_aa_b );
AOD_Test::eq(
	$aod_aa_a,
	(int) $aod_aa_o7b->get_meta( '_aod_assigned_confirmer' ),
	'ANCRAGE : prospect « 43.3 » — l’assignation est toujours reportée'
);
AOD_Test::eq(
	'',
	(string) $aod_aa_o7b->get_meta( '_aod_assigned_by' ),
	'🔴 …mais AUCUNE signature à 0 n’est posée (garde imbriquée) — invariant « absence, jamais zéro »'
);
$aod_aa_o7b->delete( true );

// (c) Prospect non assigné, converti par un confirmateur → 42.2 signe le
//     convertisseur, et ce n'est PAS un gérant : pas de fragment « Admin ».
$aod_aa_call( 'apply_lead_assignment', array( $aod_aa_lead_a, 0 ) );
$aod_aa_o7c = $aod_aa_convertir( $aod_aa_lead_a, $aod_aa_b );
AOD_Test::eq(
	$aod_aa_b,
	(int) $aod_aa_o7c->get_meta( '_aod_assigned_by' ),
	'Point 5 — conversion d’un prospect LIBRE : `maybe_assign_converter()` signe le confirmateur qui convertit'
);
AOD_Test::ok(
	false === strpos( AOD_CD_Section_Orders::assigned_confirmer_label( $aod_aa_o7c ), 'aod-cd-assigned-by' ),
	'🔴 …et comme ce n’est pas un gérant, la colonne n’affiche PAS « Admin » (seule l’assignation manuelle du gérant se distingue)'
);
$aod_aa_o7c->delete( true );

wp_set_current_user( $aod_aa_gerant_id );

/* =========================================================================
 * Nettoyage
 * ========================================================================= */

AOD_Test::group( 'Assignation abandonnées 43.3/43.4 — nettoyage' );

$wpdb->delete( $aod_aa_table, array( 'id' => $aod_aa_lead_a ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table custom aod_cod_leads : pas d'API WP, cache non pertinent.
$wpdb->delete( $aod_aa_table, array( 'id' => $aod_aa_lead_b ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table custom aod_cod_leads : pas d'API WP, cache non pertinent.
AOD_Test::eq(
	0,
	(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $aod_aa_table WHERE token IN (%s, %s)", 'zzAA43a', 'zzAA43b' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = $wpdb->prefix (nom contrôlé) ; le prepare() borne les jetons. Table custom : pas d'API WP, cache non pertinent.
	'Les 2 prospects jetables ont bien disparu'
);

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $aod_aa_a, $aod_aa_b, $aod_aa_inactif ) as $aod_aa_uid ) {
	if ( $aod_aa_uid ) {
		wp_delete_user( $aod_aa_uid );
	}
}
AOD_Test::ok(
	false === get_user_by( 'id', $aod_aa_a ) && false === get_user_by( 'id', $aod_aa_b ) && false === get_user_by( 'id', $aod_aa_inactif ),
	'Les 3 comptes confirmateurs jetables ont bien disparu'
);

wp_set_current_user( $aod_aa_user_avant );
$_GET = array();
