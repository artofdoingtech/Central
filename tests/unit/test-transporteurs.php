<?php
/**
 * Couche transporteur — normalisation des statuts et des tarifs.
 *
 * Ces deux fonctions sont le point exact où le projet s'est déjà fait avoir :
 *   • un statut non reconnu compté « en transit » a faussé les stats de livraison
 *     pendant deux mois (cf. `scripts/recette-api-transporteur.php`) ;
 *   • un schéma de tarifs non reconnu rendait un tableau vide, sans rien signaler.
 * Elles sont pures et hors réseau : elles se testent sans appeler personne.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

$carrier = new AOD_Carrier_Yalidine();

AOD_Test::group( 'Transporteurs — guess_status() : un statut inconnu n\'est JAMAIS deviné' );

AOD_Test::eq( AOD_Carrier::ST_UNKNOWN, AOD_Test::call( $carrier, 'guess_status', array( 'blah blah statut exotique' ) ), 'Un libellé non reconnu rend `unknown` — surtout pas « en transit »' );
AOD_Test::eq( AOD_Carrier::ST_UNKNOWN, AOD_Test::call( $carrier, 'guess_status', array( '' ) ), 'Un libellé vide rend `unknown`' );
AOD_Test::eq( AOD_Carrier::ST_UNKNOWN, AOD_Test::call( $carrier, 'guess_status', array( '   ' ) ), 'Un libellé blanc rend `unknown`' );

AOD_Test::group( 'Transporteurs — guess_status() : FR, AR, EN et accents' );

$cases = array(
	// FR (avec et sans accents : les APIs renvoient les deux).
	'Livré'                   => AOD_Carrier::ST_DELIVERED,
	'LIVRE AU CLIENT'         => AOD_Carrier::ST_DELIVERED,
	'Retour vers expéditeur'  => AOD_Carrier::ST_RETURNED,
	'Annulé par le client'    => AOD_Carrier::ST_CANCELLED,
	'Tentative échouée'       => AOD_Carrier::ST_FAILED,
	'Client injoignable'      => AOD_Carrier::ST_FAILED,
	'Sorti en livraison'      => AOD_Carrier::ST_OUT_FOR_DELIVERY,
	'Au centre de tri'        => AOD_Carrier::ST_IN_TRANSIT,
	'Colis ramassé'           => AOD_Carrier::ST_IN_TRANSIT,
	'Créé'                    => AOD_Carrier::ST_CREATED,
	'En attente'              => AOD_Carrier::ST_CREATED,
	// EN.
	'Delivered'               => AOD_Carrier::ST_DELIVERED,
	'Out for delivery'        => AOD_Carrier::ST_OUT_FOR_DELIVERY,
	'Returned'                => AOD_Carrier::ST_RETURNED,
	// AR.
	'تم التسليم'              => AOD_Carrier::ST_DELIVERED,
	'مرتجع'                   => AOD_Carrier::ST_RETURNED,
	'قيد التوصيل'             => AOD_Carrier::ST_OUT_FOR_DELIVERY,
);
foreach ( $cases as $text => $expected ) {
	AOD_Test::eq( $expected, AOD_Test::call( $carrier, 'guess_status', array( $text ) ), "« {$text} » → {$expected}" );
}

// L'ordre des règles compte : un libellé qui contient À LA FOIS un mot terminal et
// un mot de transit doit être classé terminal, sinon un colis livré resterait
// éternellement « en transit » dans les stats.
AOD_Test::eq( AOD_Carrier::ST_DELIVERED, AOD_Test::call( $carrier, 'guess_status', array( 'Livré au centre relais' ) ), 'Un libellé mêlant « livré » et « centre » est classé LIVRÉ (le terminal prime sur le transit)' );
AOD_Test::eq( AOD_Carrier::ST_RETURNED, AOD_Test::call( $carrier, 'guess_status', array( 'Retour en transit vers le hub' ) ), 'Un retour en transit reste un RETOUR' );

AOD_Test::group( 'Transporteurs — statuts terminaux' );

$terminal = AOD_Carrier::tracking_terminal_statuses();
AOD_Test::ok( in_array( AOD_Carrier::ST_DELIVERED, $terminal, true ), 'Livré est terminal (on cesse de resynchroniser)' );
AOD_Test::ok( in_array( AOD_Carrier::ST_RETURNED, $terminal, true ), 'Retourné est terminal' );
AOD_Test::ok( in_array( AOD_Carrier::ST_CANCELLED, $terminal, true ), 'Annulé est terminal' );
AOD_Test::ok( ! in_array( AOD_Carrier::ST_FAILED, $terminal, true ), 'Un échec de livraison n\'est PAS terminal (le livreur retentera)' );
AOD_Test::ok( ! in_array( AOD_Carrier::ST_UNKNOWN, $terminal, true ), 'Un statut inconnu n\'est pas terminal (on continue d\'interroger l\'API)' );

AOD_Test::group( 'Transporteurs — normalize_wilaya_fees() : tolérant au schéma, strict sur les valeurs' );

$rows = array(
	array( 'wilaya_id' => 16, 'home' => 400, 'desk' => 250 ),
	array( 'wilaya_id' => 31, 'home' => 600, 'desk' => 0 ),   // Domicile seul.
	array( 'wilaya_id' => 6,  'home' => 0,   'desk' => 300 ), // Stop-desk seul.
	array( 'wilaya_id' => 99, 'home' => 500, 'desk' => 500 ), // Hors 1..58 → ignoré.
	array( 'wilaya_id' => 0,  'home' => 500, 'desk' => 500 ), // Code invalide → ignoré.
	array( 'wilaya_id' => 25, 'home' => 0,   'desk' => 0 ),   // Aucun tarif → ignoré.
	'pas un tableau',                                          // → ignoré.
);
$fees = AOD_Test::call( $carrier, 'normalize_wilaya_fees', array( $rows, array( 'wilaya_id' ), array( 'home' ), array( 'desk' ) ) );

AOD_Test::eq( array( 16, 31, 6 ), array_keys( $fees ), 'Seules les wilayas 1..58 porteuses d\'au moins un tarif sont retenues' );
AOD_Test::eq( array( 'home' => 400, 'desk' => 250 ), $fees[16], 'Wilaya 16 : les deux modes sont repris' );
AOD_Test::eq( array( 'home' => 600, 'desk' => 0 ), $fees[31], 'Une wilaya sans stop-desk garde desk=0 (pas de prix inventé)' );
AOD_Test::eq( array( 'home' => 0, 'desk' => 300 ), $fees[6], 'Une wilaya sans domicile garde home=0' );
AOD_Test::ok( ! isset( $fees[25] ), 'Une wilaya à 0/0 est écartée — elle n\'écrasera pas un tarif existant' );

AOD_Test::eq( array(), AOD_Test::call( $carrier, 'normalize_wilaya_fees', array( array(), array( 'id' ), array( 'h' ), array( 'd' ) ) ), 'Une réponse vide rend un tableau vide (et non une erreur)' );
