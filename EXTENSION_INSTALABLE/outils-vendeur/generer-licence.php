#!/usr/bin/env php
<?php
/**
 * generer-licence.php — Outil VENDEUR pour émettre une clé de licence
 * annuelle pour l'extension AOD Gestion (/gestion).
 *
 * ⚠️ Ce fichier ne fait PAS partie du paquet livré au client — il reste dans
 * ce dépôt, chez le fournisseur. `build.sh` ne le copie jamais dans les .zip
 * distribués (voir outils-vendeur/README.md).
 *
 * Usage :
 *   php generer-licence.php "Nom du client" [domaine] [jours]
 *
 * Exemples :
 *   php generer-licence.php "Boutique Yasmine"                    # 1 an, tout domaine
 *   php generer-licence.php "Boutique Yasmine" boutique-yasmine.dz # 1 an, lié au domaine
 *   php generer-licence.php "Boutique Yasmine" boutique-yasmine.dz 730  # 2 ans
 *
 * Le SECRET ci-dessous DOIT être identique à
 * plugins/aod-gestion-license/includes/class-aodg-license.php::SECRET
 * (constante AODG_License::SECRET). Si tu le changes, tu invalides toutes
 * les clés déjà émises.
 */

if ( 'cli' !== php_sapi_name() ) {
	http_response_code( 403 );
	exit( "Outil réservé à la ligne de commande.\n" );
}

const SECRET = 'e2b7f7f4a6c2b9d1e5a08f3c6d4b1a9e7c5f2d8b0a4e6c1f9b3d7a5e8c2f0b46';
const PREFIX = 'AODG1';

function base64url_encode( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

function usage_and_exit( $code = 1 ) {
	fwrite( STDERR, "Usage : php generer-licence.php \"Nom du client\" [domaine|*] [jours=365]\n" );
	exit( $code );
}

$args = $argv;
array_shift( $args ); // nom du script

if ( empty( $args[0] ) ) {
	usage_and_exit();
}

$client = trim( $args[0] );
$domain = isset( $args[1] ) && '' !== trim( $args[1] ) ? trim( $args[1] ) : '*';
$days   = isset( $args[2] ) ? (int) $args[2] : 365;

if ( $days <= 0 ) {
	fwrite( STDERR, "Le nombre de jours doit être positif.\n" );
	exit( 1 );
}

// Normalise le domaine comme le fait le plugin côté vérification.
if ( '*' !== $domain ) {
	$domain = strtolower( $domain );
	$domain = preg_replace( '#^https?://#', '', $domain );
	$domain = preg_replace( '#^www\.#', '', $domain );
	$domain = rtrim( $domain, '/' );
}

$now     = time();
$expires = $now + ( $days * 86400 );

$payload = array(
	'client'  => $client,
	'domain'  => $domain,
	'issued'  => $now,
	'expires' => $expires,
	'id'      => bin2hex( random_bytes( 8 ) ),
);

$payload_json = json_encode( $payload, JSON_UNESCAPED_UNICODE );
$payload_b64  = base64url_encode( $payload_json );
$sig_b64      = base64url_encode( hash_hmac( 'sha256', $payload_b64, SECRET, true ) );

$token = PREFIX . '.' . $payload_b64 . '.' . $sig_b64;

echo "=============================================================\n";
echo " Licence AOD Gestion\n";
echo "=============================================================\n";
echo "Client      : {$client}\n";
echo 'Domaine     : ' . ( '*' === $domain ? '(tout domaine)' : $domain ) . "\n";
echo 'Émise le    : ' . gmdate( 'Y-m-d', $now ) . " UTC\n";
echo 'Expire le   : ' . gmdate( 'Y-m-d', $expires ) . " UTC ({$days} jours)\n";
echo "-------------------------------------------------------------\n";
echo "Clé à transmettre au client :\n\n";
echo $token . "\n\n";
echo "-------------------------------------------------------------\n";
echo "Le client colle cette clé dans :\n";
echo "  wp-admin → Licence AOD Gestion → Activer une clé\n";
echo "=============================================================\n";
