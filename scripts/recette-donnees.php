<?php
/**
 * Recette « avec données » : ce que seule une base peuplée peut prouver.
 *
 * IMPORTANT : un seul utilisateur par exécution. AOD_CD_Dashboard mémorise le
 * périmètre produits (`$this->product_scope`) — légitime en HTTP (1 requête =
 * 1 utilisateur), mais un script qui bascule d'admin à confirmateur dans le
 * même processus testerait un cache périmé.
 *
 *   wp eval-file /scripts/recette-donnees.php gerant
 *   wp eval-file /scripts/recette-donnees.php confirmateur
 */
defined( 'ABSPATH' ) || exit;

$who = isset( $args[0] ) ? $args[0] : 'gerant';

$ok = 0;
$ko = 0;
$chk = function ( $label, $cond, $extra = '' ) use ( &$ok, &$ko ) {
	if ( $cond ) {
		$ok++;
		WP_CLI::log( "  ✅ $label" );
	} else {
		$ko++;
		WP_CLI::log( "  ❌ $label" . ( $extra ? " — $extra" : '' ) );
	}
};

$dash   = AOD_CD_Dashboard::instance();
$ref    = new ReflectionClass( $dash );
$render = function ( $method ) use ( $dash, $ref ) {
	$m = $ref->getMethod( $method );
	$m->setAccessible( true );
	ob_start();
	$m->invoke( $dash );
	return ob_get_clean();
};
$orders_in = function ( $html ) {
	preg_match_all( '#data-order="(\d+)"#', $html, $m );
	return array_values( array_unique( $m[1] ) );
};

if ( 'gerant' === $who ) {
	$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	wp_set_current_user( $admin[0]->ID );

	WP_CLI::log( "\n=== GÉRANT — Suivi : le filtrage des colis fonctionne (HPOS) ===" );
	$html = $render( 'section_tracking' );
	$rows = count( $orders_in( $html ) );
	WP_CLI::log( "  · Colis listés : $rows (18 attendus ; 29 = meta_query ignoré)" );
	$chk( 'La page liste les colis, pas toutes les commandes', 18 === $rows, "obtenu $rows" );

	WP_CLI::log( "\n=== GÉRANT — Commandes : compteurs du segmented control ===" );
	$html = $render( 'section_orders' );
	preg_match_all( '#aod-cd-seg__count[^>]*>\s*(\d+)#', $html, $m );
	WP_CLI::log( '  · Compteurs : ' . implode( ' · ', $m[1] ) );
	$chk( '5 compteurs (Toutes/Attente/Confirmées/Terminées/Annulées)', 5 === count( $m[1] ) );
	$chk( 'Compteur « Toutes » = 29', isset( $m[1][0] ) && '29' === $m[1][0] );
	$chk( 'Pastille de statut rendue', false !== strpos( $html, 'aod-cd-statussel-btn' ) );
	$chk( 'Colonne de sélection présente (suppression groupée)', false !== strpos( $html, 'aod-cd-ordercheck' ) );
} else {
	$conf = get_user_by( 'login', 'confirmateur' );
	wp_set_current_user( $conf->ID );

	$scope = array_map( 'intval', AOD_CD_Roles::assigned_products( $conf ) );
	WP_CLI::log( "\n=== CONFIRMATEUR — cloisonnement (correctif de sécurité 5.4) ===" );
	$chk( 'Périmètre restreint actif (2 produits sur 6)', 2 === count( $scope ) );

	$in_scope = function ( $oid ) use ( $scope ) {
		$o = wc_get_order( (int) $oid );
		foreach ( $o->get_items() as $it ) {
			if ( in_array( (int) $it->get_product_id(), $scope, true ) ) {
				return true;
			}
		}
		return false;
	};

	$seen = $orders_in( $render( 'section_orders' ) );
	$leak = count( array_filter( $seen, function ( $o ) use ( $in_scope ) { return ! $in_scope( $o ); } ) );
	WP_CLI::log( '  · Commandes visibles : ' . count( $seen ) . ' / 29' );
	$chk( 'Commandes : il en voit une partie seulement', count( $seen ) > 0 && count( $seen ) < 29, count( $seen ) . ' visibles' );
	$chk( 'Commandes : AUCUNE hors périmètre', 0 === $leak, "$leak fuite(s)" );

	$html = $render( 'section_tracking' );
	$seen = $orders_in( $html );
	$leak = count( array_filter( $seen, function ( $o ) use ( $in_scope ) { return ! $in_scope( $o ); } ) );
	WP_CLI::log( '  · Colis visibles dans Suivi : ' . count( $seen ) . ' / 18' );
	$chk( 'Suivi : AUCUN colis hors périmètre (fuite fermée en 5.4)', 0 === $leak, "$leak fuite(s)" );
	$chk( 'Suivi : pas de colonne de sélection pour lui', false === strpos( $html, 'aod-cd-ordercheck' ) );
}

WP_CLI::log( "\n=== RÉSULTAT ($who) : $ok OK / $ko KO ===" );
