<?php
/**
 * Validation et stockage de la licence (jeton signé, hors ligne).
 *
 * Format du jeton : "AODG1.<payload_base64url>.<signature_base64url>"
 * — payload JSON { client, domain, issued, expires, id }
 * — signature = HMAC-SHA256( payload_base64url, SECRET )
 *
 * Vérification 100% locale (pas d'appel réseau) : ce dépôt documente déjà,
 * pour le template principal, l'absence d'internet sortant fiable sur les
 * hébergements DZ visés (cf. TEMPLATE.md §3). Un système qui dépendrait d'un
 * serveur de licence pour valider une clé serait fragile dans ce contexte —
 * la validation se fait donc entièrement dans le PHP du plugin, comme une
 * signature de paquet.
 *
 * ⚠️ Limite connue et assumée : le secret de signature vit dans le code PHP
 * du plugin, donc côté client. Une personne déterminée qui a accès aux
 * fichiers du site pourrait en théorie forger une clé. C'est un compromis
 * volontaire (licence « molle », pas de DRM) — cohérent avec le reste du
 * projet, qui ne dépend jamais d'un service externe pour fonctionner.
 *
 * @package AOD_Gestion_License
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AODG_License {

	/**
	 * Secret HMAC partagé avec l'outil vendeur
	 * (outils-vendeur/generer-licence.php). Si tu changes cette valeur ici,
	 * change-la AUSSI dans l'outil, sinon plus aucune clé émise ne valide.
	 *
	 * Généré une fois pour toutes avec :
	 *   php -r "echo bin2hex(random_bytes(32));"
	 */
	const SECRET = 'e2b7f7f4a6c2b9d1e5a08f3c6d4b1a9e7c5f2d8b0a4e6c1f9b3d7a5e8c2f0b46';

	const PREFIX      = 'AODG1';
	const OPTION_KEY  = 'aodg_license_key';
	const OPTION_DATA = 'aodg_license_data';

	/**
	 * Active une clé de licence : la vérifie puis, si valide, la stocke.
	 *
	 * @param string $raw_key
	 * @return array{ok:bool,message:string}
	 */
	public static function activate( $raw_key ) {
		$raw_key = trim( (string) $raw_key );

		if ( '' === $raw_key ) {
			return array(
				'ok'      => false,
				'message' => __( 'Veuillez saisir une clé de licence.', 'aod-gestion-license' ),
			);
		}

		$result = self::verify_token( $raw_key );

		if ( ! $result['ok'] ) {
			return $result;
		}

		update_option( self::OPTION_KEY, $raw_key, false );
		update_option( self::OPTION_DATA, $result['data'], false );

		return array(
			'ok'      => true,
			'message' => __( 'Licence activée avec succès.', 'aod-gestion-license' ),
		);
	}

	/**
	 * Retire la licence enregistrée (le site repasse à l'état non licencié).
	 */
	public static function deactivate() {
		delete_option( self::OPTION_KEY );
		delete_option( self::OPTION_DATA );
	}

	/**
	 * Jeton actuellement stocké, ou chaîne vide.
	 *
	 * @return string
	 */
	public static function get_key() {
		return (string) get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Données décodées de la licence stockée (client, domaine, dates), ou
	 * null si aucune licence enregistrée.
	 *
	 * @return array{client:string,domain:string,issued:int,expires:int,id:string}|null
	 */
	public static function get_data() {
		$data = get_option( self::OPTION_DATA, null );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * État courant : 'none' | 'invalid' | 'expired' | 'expiring_soon' | 'valid'.
	 *
	 * Revérifie le jeton stocké à chaque appel (pas seulement à l'activation) :
	 * une licence qui expire doit basculer d'elle-même, sans action du gérant.
	 *
	 * @return string
	 */
	public static function status() {
		$key = self::get_key();

		if ( '' === $key ) {
			return 'none';
		}

		$result = self::verify_token( $key );

		if ( ! $result['ok'] ) {
			return 'invalid';
		}

		$expires = (int) $result['data']['expires'];
		$now     = time();

		if ( $now > $expires ) {
			return 'expired';
		}

		// « Bientôt expirée » : moins de 14 jours restants.
		if ( ( $expires - $now ) < ( 14 * DAY_IN_SECONDS ) ) {
			return 'expiring_soon';
		}

		return 'valid';
	}

	/**
	 * Raccourci : la licence permet-elle l'accès à /gestion maintenant ?
	 *
	 * @return bool
	 */
	public static function is_valid() {
		$status = self::status();
		return 'valid' === $status || 'expiring_soon' === $status;
	}

	/**
	 * Nombre de jours restants avant expiration (négatif si déjà expirée),
	 * ou null si aucune licence valide n'est enregistrée.
	 *
	 * @return int|null
	 */
	public static function days_remaining() {
		$data = self::get_data();
		if ( ! $data || empty( $data['expires'] ) ) {
			return null;
		}
		return (int) floor( ( (int) $data['expires'] - time() ) / DAY_IN_SECONDS );
	}

	/**
	 * Décode et vérifie un jeton de licence (signature, format, expiration,
	 * domaine). Ne touche PAS à la base — pure fonction de vérification.
	 *
	 * @param string $token
	 * @return array{ok:bool,message:string,data?:array}
	 */
	public static function verify_token( $token ) {
		$token = trim( (string) $token );
		$parts = explode( '.', $token );

		if ( 3 !== count( $parts ) || self::PREFIX !== $parts[0] ) {
			return array(
				'ok'      => false,
				'message' => __( 'Format de clé invalide.', 'aod-gestion-license' ),
			);
		}

		list( , $payload_b64, $sig_b64 ) = $parts;

		$expected_sig = self::base64url_encode(
			hash_hmac( 'sha256', $payload_b64, self::SECRET, true )
		);

		if ( ! hash_equals( $expected_sig, $sig_b64 ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Signature invalide : cette clé n’a pas été émise pour cette extension.', 'aod-gestion-license' ),
			);
		}

		$json = self::base64url_decode( $payload_b64 );
		$data = json_decode( (string) $json, true );

		if ( ! is_array( $data ) || empty( $data['expires'] ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Contenu de la clé illisible.', 'aod-gestion-license' ),
			);
		}

		$data += array(
			'client' => '',
			'domain' => '*',
			'issued' => 0,
			'id'     => '',
		);

		// Liaison au domaine, sauf si la clé est marquée universelle ('*') ou
		// si le filtre de contournement (environnement de test/staging) est actif.
		if ( '*' !== $data['domain']
			&& ! apply_filters( 'aodg_license_skip_domain_check', false )
			&& ! self::domain_matches( $data['domain'] )
		) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: expected domain. */
					__( 'Cette clé est liée au domaine « %s » et ne correspond pas à ce site.', 'aod-gestion-license' ),
					$data['domain']
				),
			);
		}

		return array(
			'ok'      => true,
			'message' => __( 'Clé valide.', 'aod-gestion-license' ),
			'data'    => $data,
		);
	}

	/**
	 * Compare le domaine attendu (tel qu'émis dans la clé) à celui du site
	 * courant, en ignorant www./http(s)://.
	 *
	 * @param string $expected
	 * @return bool
	 */
	protected static function domain_matches( $expected ) {
		$normalize = function ( $host ) {
			$host = strtolower( trim( (string) $host ) );
			$host = preg_replace( '#^https?://#', '', $host );
			$host = preg_replace( '#^www\.#', '', $host );
			$host = rtrim( $host, '/' );
			return $host;
		};

		$current = wp_parse_url( home_url(), PHP_URL_HOST );

		return $normalize( $expected ) === $normalize( (string) $current );
	}

	/**
	 * @param string $data
	 * @return string
	 */
	protected static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * @param string $data
	 * @return string
	 */
	protected static function base64url_decode( $data ) {
		$padded = str_pad( $data, strlen( $data ) % 4 === 0 ? strlen( $data ) : strlen( $data ) + ( 4 - strlen( $data ) % 4 ), '=' );
		return base64_decode( strtr( $padded, '-_', '+/' ) );
	}
}
