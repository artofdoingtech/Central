#!/usr/bin/env php
<?php
/**
 * verifier-licence.php — Décode et vérifie une clé de licence AOD Gestion
 * sans avoir besoin de WordPress. Utile pour le support (« est-ce que cette
 * clé que le client me colle est valide/expirée/pour le bon domaine ? »).
 *
 * Usage : php verifier-licence.php "AODG1.xxxx.yyyy"
 */

if ( 'cli' !== php_sapi_name() ) {
	http_response_code( 403 );
	exit( "Outil réservé à la ligne de commande.\n" );
}

const SECRET = 'e2b7f7f4a6c2b9d1e5a08f3c6d4b1a9e7c5f2d8b0a4e6c1f9b3d7a5e8c2f0b46';
const PREFIX = 'AODG1';

function base64url_decode( $data ) {
	$pad = strlen( $data ) % 4;
	if ( $pad ) {
		$data .= str_repeat( '=', 4 - $pad );
	}
	return base64_decode( strtr( $data, '-_', '+/' ) );
}

function base64url_encode( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

$token = $argv[1] ?? '';
if ( '' === trim( $token ) ) {
	fwrite( STDERR, "Usage : php verifier-licence.php \"AODG1.xxxx.yyyy\"\n" );
	exit( 1 );
}

$parts = explode( '.', trim( $token ) );
if ( 3 !== count( $parts ) || PREFIX !== $parts[0] ) {
	echo "❌ Format invalide (attendu AODG1.<payload>.<signature>).\n";
	exit( 1 );
}

list( , $payload_b64, $sig_b64 ) = $parts;

$expected_sig = base64url_encode( hash_hmac( 'sha256', $payload_b64, SECRET, true ) );
if ( ! hash_equals( $expected_sig, $sig_b64 ) ) {
	echo "❌ Signature invalide — cette clé n'a pas été émise avec ce secret.\n";
	exit( 1 );
}

$data = json_decode( base64url_decode( $payload_b64 ), true );
if ( ! is_array( $data ) ) {
	echo "❌ Contenu illisible.\n";
	exit( 1 );
}

$now       = time();
$expires   = (int) ( $data['expires'] ?? 0 );
$remaining = (int) floor( ( $expires - $now ) / 86400 );

echo "✅ Signature valide.\n\n";
echo 'Client      : ' . ( $data['client'] ?? '—' ) . "\n";
echo 'Domaine     : ' . ( $data['domain'] ?? '*' ) . "\n";
echo 'Émise le    : ' . ( ! empty( $data['issued'] ) ? gmdate( 'Y-m-d', (int) $data['issued'] ) . ' UTC' : '—' ) . "\n";
echo 'Expire le   : ' . ( $expires ? gmdate( 'Y-m-d', $expires ) . ' UTC' : '—' ) . "\n";
echo 'Statut      : ' . ( $remaining >= 0 ? "valide ({$remaining} j restants)" : 'EXPIRÉE depuis ' . abs( $remaining ) . ' j' ) . "\n";
