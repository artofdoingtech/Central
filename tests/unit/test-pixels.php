<?php
/**
 * Devise de reporting des pixels (`AOD_COD_Pixels::to_reporting()`).
 *
 * Meta refuse la devise DZD : sans conversion, la VALEUR des achats n'est jamais
 * mesurée (le gérant pilote ses pubs au ROAS sur du vide). Le helper doit :
 *   • ne RIEN changer tant que le clone n'a rien configuré (défaut = DZD tel quel) ;
 *   • convertir proprement dès qu'une devise + un taux sont saisis.
 * Une erreur ici fausse silencieusement les chiffres remontés aux régies.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Pixels — to_reporting() : sans réglage, rien ne change' );

$none = array( 'report_currency' => '', 'report_rate' => '' );
AOD_Test::eq(
	array( 4500.0, 'DZD' ),
	AOD_COD_Pixels::to_reporting( 4500, 'DZD', $none ),
	'Devise et taux vides : le couple (valeur, DZD) passe tel quel'
);
AOD_Test::eq(
	array( 4500.0, 'DZD' ),
	AOD_COD_Pixels::to_reporting( 4500, 'DZD', array() ),
	'Réglages absents (clés manquantes) : couple inchangé, aucune erreur'
);
AOD_Test::eq(
	array( 4500.0, 'DZD' ),
	AOD_COD_Pixels::to_reporting( 4500, 'DZD', array( 'report_currency' => 'USD', 'report_rate' => 0 ) ),
	'Devise fournie mais taux 0 : on ne convertit pas (pas de valeur inventée)'
);
AOD_Test::eq(
	array( 4500.0, 'DZD' ),
	AOD_COD_Pixels::to_reporting( 4500, 'DZD', array( 'report_currency' => '', 'report_rate' => 0.0075 ) ),
	'Taux fourni mais devise vide : on ne convertit pas'
);
AOD_Test::eq(
	array( 4500.0, 'DZD' ),
	AOD_COD_Pixels::to_reporting( 4500, 'DZD', array( 'report_currency' => 'USD', 'report_rate' => -1 ) ),
	'Taux négatif : ignoré, couple inchangé'
);

AOD_Test::group( 'Pixels — to_reporting() : conversion quand c\'est configuré' );

$usd = array( 'report_currency' => 'USD', 'report_rate' => 0.0075 );
AOD_Test::eq(
	array( 33.75, 'USD' ),
	AOD_COD_Pixels::to_reporting( 4500, 'DZD', $usd ),
	'4500 DZD × 0,0075 = 33,75 USD (valeur ET devise basculées)'
);
AOD_Test::eq(
	array( 7.5, 'EUR' ),
	AOD_COD_Pixels::to_reporting( 1000, 'DZD', array( 'report_currency' => 'EUR', 'report_rate' => 0.0075 ) ),
	'La devise renvoyée est bien celle qu\'on a déclarée (EUR)'
);

// La valeur est arrondie à 2 décimales : les régies attendent un montant monétaire,
// pas un flottant à rallonge qui varierait d'une commande à l'autre.
$r = AOD_COD_Pixels::to_reporting( 3333, 'DZD', array( 'report_currency' => 'USD', 'report_rate' => 0.007 ) );
AOD_Test::eq( 23.33, $r[0], '3333 × 0,007 = 23,331 → arrondi à 23,33' );

// Le code de devise est normalisé (minuscules / espaces tolérés à la saisie).
$r = AOD_COD_Pixels::to_reporting( 1000, 'DZD', array( 'report_currency' => 'usd', 'report_rate' => 0.0075 ) );
AOD_Test::eq( 'USD', $r[1], 'Une devise saisie en minuscules ressort en majuscules (USD)' );
