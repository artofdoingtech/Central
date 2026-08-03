<?php
/**
 * Source d'IP du visiteur — la seule du plugin, et elle ne fait confiance à
 * personne par défaut.
 *
 * 🔴 POURQUOI CETTE CLASSE EXISTE (étape 26.1). Les deux coupe-circuits anti-flood
 * — commandes COD et formulaire de contact — comptaient les envois par IP en
 * lisant `WC_Geolocation::get_ip_address()`. Or WooCommerce y renvoie l'en-tête
 * `X-Real-IP` **brut** :
 *
 *     // woocommerce/includes/class-wc-geolocation.php:83-84
 *     if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
 *         return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
 *     }
 *
 * Aucune validation — pas même `rest_is_ip_address()`, que les deux branches
 * SUIVANTES appliquent pourtant. Un en-tête est écrit par le client : il suffisait
 * donc d'envoyer `X-Real-IP: <n'importe quoi d'aléatoire>` à chaque requête pour
 * obtenir une clé de compteur neuve à chaque fois (`md5( $ip )`), et le plafond
 * n'était jamais atteint. **La couche 3 des 5 annoncées était décorative.**
 *
 * Ce n'est pas un défaut de WooCommerce : `get_ip_address()` sert à la
 * géolocalisation commerciale (deviner un pays pour pré-remplir un formulaire), où
 * un visiteur qui se ment à lui-même ne nuit qu'à lui. **Le défaut était de
 * réutiliser une source de CONFORT comme source de SÉCURITÉ.**
 *
 * LE CONTRAT : `REMOTE_ADDR` — la seule valeur que le client ne peut pas forger,
 * parce qu'elle vient de la couche TCP et non d'un en-tête. Les en-têtes de proxy
 * ne sont lus QUE si l'exploitant déclare explicitement se trouver derrière un
 * proxy de confiance, via la constante `AOD_COD_TRUSTED_PROXY` dans `wp-config.php`.
 *
 * ⚠️ QUAND FAUT-IL LA DÉCLARER ? Derrière Cloudflare, un load-balancer ou un
 * reverse-proxy, `REMOTE_ADDR` est l'IP DU PROXY : tous les visiteurs partagent
 * alors un seul compteur, et le coupe-circuit se déclenche pour tout le monde à la
 * 10ᵉ commande du site. C'est un faux positif visible (des acheteurs bloqués),
 * pas une faille — donc le défaut est du bon côté : on préfère un réglage à faire
 * qu'une protection qui ment. Le README de sécurité le documente.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_IP {

	/**
	 * En-têtes de proxy acceptés — dans l'ordre de préférence — LORSQUE, et
	 * seulement lorsque, `AOD_COD_TRUSTED_PROXY` est déclarée.
	 *
	 * @var string[]
	 */
	const PROXY_HEADERS = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' );

	/**
	 * IP du client, pour un usage de SÉCURITÉ (compteur anti-flood, verrou).
	 *
	 * @return string IP valide, ou '' si rien d'exploitable.
	 */
	public static function client() {
		if ( self::trusts_proxy() ) {
			foreach ( self::PROXY_HEADERS as $header ) {
				if ( ! isset( $_SERVER[ $header ] ) ) {
					continue;
				}
				// X-Forwarded-For: client, proxy1, proxy2 → le client est en tête.
				$raw   = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				$first = trim( (string) strtok( $raw, ',' ) );
				$ip    = self::valid( $first );
				if ( '' !== $ip ) {
					return $ip;
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] )
			? self::valid( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) )
			: '';
	}

	/**
	 * L'exploitant a-t-il déclaré un proxy de confiance devant le site ?
	 *
	 * @return bool
	 */
	public static function trusts_proxy() {
		return defined( 'AOD_COD_TRUSTED_PROXY' ) && AOD_COD_TRUSTED_PROXY;
	}

	/**
	 * Normalise et VALIDE une IP. Retourne '' si ce n'en est pas une.
	 *
	 * 🚨 C'est le cœur du correctif : sans cette validation, n'importe quelle
	 * chaîne devient une clé de compteur distincte via `md5()`, et le coupe-circuit
	 * ne compte plus rien. Une IP invalide doit donc valoir « pas d'IP », jamais
	 * « une IP de plus ».
	 *
	 * @param string $value Valeur brute.
	 * @return string
	 */
	public static function valid( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		// Retire le port éventuel : « 1.2.3.4:5678 », « [::1]:8080 », « [::1] ».
		$value = (string) preg_replace( '/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+):\d+$/', '$1', $value );
		$value = (string) preg_replace( '/^\[([^\]]+)\](?::\d+)?$/', '$1', $value );

		return filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
	}
}
