<?php
/**
 * Challenge anti-robot Cloudflare Turnstile pour le formulaire COD.
 *
 * Parade de fond contre le spam de fausses commandes : les bots qui postent
 * directement sur admin-ajax.php n'ont jamais de jeton Turnstile valide et sont
 * donc rejetés côté serveur, quelle que soit leur rotation d'IP. Turnstile est
 * gratuit, sans image à cliquer (invisible/« managed »), et respecte la vie privée.
 *
 * Configuration : Réglages → « COD Anti-spam » (ou constantes
 * AOD_COD_TURNSTILE_SITE_KEY / AOD_COD_TURNSTILE_SECRET_KEY dans wp-config.php).
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Turnstile {

	/** @var AOD_COD_Turnstile|null */
	protected static $instance = null;

	const OPTION      = 'aod_cod_turnstile';

	/**
	 * État de santé du challenge (étape 26.1) — échecs consécutifs de vérification.
	 * Transient : c'est un état d'incident, pas un réglage. Purgé à la
	 * désinstallation par le motif `_transient_aod_%` de `uninstall.php`.
	 */
	const HEALTH_TRANSIENT = 'aod_cod_ts_health';

	/** Fenêtre de l'état de santé : au-delà, un incident ancien est oublié. */
	const HEALTH_TTL = HOUR_IN_SECONDS;
	const VERIFY_URL  = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	const SCRIPT_URL  = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Clés                                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * Clé de site (publique). Constante wp-config prioritaire, sinon option.
	 *
	 * @return string
	 */
	public function site_key() {
		if ( defined( 'AOD_COD_TURNSTILE_SITE_KEY' ) && AOD_COD_TURNSTILE_SITE_KEY ) {
			return (string) AOD_COD_TURNSTILE_SITE_KEY;
		}
		$o = get_option( self::OPTION, array() );
		return ( is_array( $o ) && ! empty( $o['site'] ) ) ? (string) $o['site'] : '';
	}

	/**
	 * Clé secrète (privée). Constante wp-config prioritaire, sinon option.
	 *
	 * @return string
	 */
	public function secret_key() {
		if ( defined( 'AOD_COD_TURNSTILE_SECRET_KEY' ) && AOD_COD_TURNSTILE_SECRET_KEY ) {
			return (string) AOD_COD_TURNSTILE_SECRET_KEY;
		}
		$o = get_option( self::OPTION, array() );
		return ( is_array( $o ) && ! empty( $o['secret'] ) ) ? (string) $o['secret'] : '';
	}

	/**
	 * Le challenge est-il actif ? (les deux clés présentes)
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return '' !== $this->site_key() && '' !== $this->secret_key();
	}

	/* --------------------------------------------------------------------- */
	/* Front : script + widget                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Charge l'API Turnstile (rendu automatique des éléments .cf-turnstile).
	 */
	public function enqueue_script() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		// null en version : URL Cloudflare, pas de cache-buster à ajouter.
		wp_enqueue_script( 'cf-turnstile', self::SCRIPT_URL, array(), null, true );
	}

	/**
	 * Widget à insérer dans le formulaire (juste avant le bouton « Confirmer »).
	 * Turnstile y injecte automatiquement l'input caché « cf-turnstile-response ».
	 *
	 * @return string
	 */
	public function widget_html() {
		if ( ! $this->is_enabled() ) {
			return '';
		}
		return '<div class="aod-cod__field aod-cod__turnstile">'
			. '<div class="cf-turnstile" data-sitekey="' . esc_attr( $this->site_key() ) . '" data-action="cod_submit" data-theme="auto" data-size="flexible"></div>'
			. '</div>';
	}

	/* --------------------------------------------------------------------- */
	/* Serveur : vérification du jeton                                       */
	/* --------------------------------------------------------------------- */

	/**
	 * Vérifie un jeton Turnstile auprès de Cloudflare.
	 *
	 * Politique : fail-CLOSED sur jeton absent/invalide (c'est exactement le cas
	 * des bots qui postent en direct → rejetés), mais fail-OPEN si Cloudflare est
	 * injoignable (erreur réseau) pour ne pas bloquer un vrai client si le service
	 * a un incident.
	 *
	 * @param string $token Jeton « cf-turnstile-response » reçu du formulaire.
	 * @param string $ip    IP du client (REMOTE_ADDR).
	 * @return bool True si valide (ou challenge non configuré), false si à rejeter.
	 */
	public function verify( $token, $ip = '' ) {
		if ( ! $this->is_enabled() ) {
			return true; // Non configuré : ne bloque rien.
		}
		$token = is_string( $token ) ? trim( $token ) : '';
		if ( '' === $token ) {
			return false; // Aucun jeton = robot (POST direct sur admin-ajax).
		}

		$resp = wp_remote_post( self::VERIFY_URL, array(
			'timeout' => 8,
			'body'    => array(
				'secret'   => $this->secret_key(),
				'response' => $token,
				'remoteip' => $ip,
			),
		) );

		if ( is_wp_error( $resp ) ) {
			return $this->fail_open( 'reseau', $resp->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( 200 !== $code || ! is_array( $data ) ) {
			return $this->fail_open( 'reponse', 'HTTP ' . $code );
		}

		// Cloudflare a répondu : le service est sain, on repart de zéro.
		$this->reset_health();
		return ! empty( $data['success'] );
	}

	/* --------------------------------------------------------------------- */
	/* Fail-open BORNÉ + visible (étape 26.1)                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Le challenge n'a pas pu être vérifié. Laisse-t-on passer ?
	 *
	 * 🔴 CE QUE CETTE MÉTHODE CORRIGE. `verify()` retournait `true` — donc « valide »
	 * — dès que Cloudflare était injoignable, **sans borne, sans trace, sans fin**.
	 * L'intention était bonne (ne pas bloquer un vrai client pendant un incident),
	 * mais elle produisait trois effets qu'aucun réglage ne révélait :
	 *
	 *   1. Le contournement coûtait UN caractère. Un bot postant en direct sur
	 *      `admin-ajax.php` avec `cf_turnstile=x` franchissait le test « jeton
	 *      vide », déclenchait l'appel HTTP, le voyait échouer — et passait.
	 *   2. Sur un clone SANS réseau sortant, l'échec est permanent, pas
	 *      accidentel. Or TEMPLATE.md §3 pose « pas d'internet sortant fiable par
	 *      défaut » comme le cas NOMINAL. La couche était donc ouverte en
	 *      permanence sur l'installation type, pas seulement pendant une panne.
	 *   3. Rien ne le disait. La page de réglages affichait « ✅ Actif », le
	 *      gérant n'avait aucun moyen d'apprendre que sa protection ne protégeait
	 *      plus. **C'est ça, le vrai défaut** — pas la politique, son silence.
	 *
	 * LE CONTRAT : le fail-open reste, mais BORNÉ et COMPTÉ. Tant que les échecs
	 * consécutifs restent sous le seuil, on laisse passer (un incident Cloudflare ne
	 * doit pas coûter les ventes de la journée). Au-delà, ce n'est plus un incident,
	 * c'est un état : on bascule en strict. Dans les deux cas l'état est enregistré,
	 * et `/gestion` le montre.
	 *
	 * @param string $cause  'reseau' ou 'reponse'.
	 * @param string $detail Message technique, pour le diagnostic.
	 * @return bool True = on laisse passer (fail-open), false = on rejette.
	 */
	protected function fail_open( $cause, $detail = '' ) {
		$sante = self::health();
		$fails = (int) $sante['fails'] + 1;

		set_transient(
			self::HEALTH_TRANSIENT,
			array(
				'fails'  => $fails,
				'last'   => time(),
				'cause'  => (string) $cause,
				'detail' => (string) $detail,
			),
			self::HEALTH_TTL
		);

		$ouvert = $fails <= self::max_failopen();

		// Journalisation (31.3). La Phase 26 a rendu cet état visible dans
		// `/gestion` — bonne réponse pour le GÉRANT, qui doit savoir que sa
		// protection est dégradée. Le journal répond à l'autre question, la
		// nôtre : POURQUOI. « Le compteur est à 14 » ne dit pas si c'est le DNS,
		// une clé révoquée ou un 5xx de Cloudflare.
		AOD_COD_Log::error( 'turnstile', sprintf(
			'Vérification impossible (%s) — échec consécutif n°%d/%d, la requête %s. Détail : %s',
			(string) $cause,
			$fails,
			self::max_failopen(),
			$ouvert ? 'PASSE (fail-open)' : 'est REJETÉE (bascule stricte)',
			(string) $detail
		) );

		return $ouvert;
	}

	/** Le service a répondu : on efface l'état de dégradation. */
	protected function reset_health() {
		if ( false !== get_transient( self::HEALTH_TRANSIENT ) ) {
			delete_transient( self::HEALTH_TRANSIENT );
		}
	}

	/**
	 * Nombre d'échecs consécutifs tolérés avant bascule en strict.
	 *
	 * Volontairement généreux : le but est de distinguer un INCIDENT (rare, borné)
	 * d'un ÉTAT (clone sans réseau, clé révoquée). Filtrable pour un exploitant qui
	 * préfère un autre curseur — mais on ne descend pas à 0 par défaut : ce serait
	 * fail-closed déguisé, et une panne Cloudflare arrêterait toutes les ventes.
	 *
	 * @return int
	 */
	public static function max_failopen() {
		$max = defined( 'AOD_COD_TURNSTILE_MAX_FAILOPEN' ) ? (int) AOD_COD_TURNSTILE_MAX_FAILOPEN : 20;
		/** Nombre d'échecs de vérification tolérés avant de rejeter. */
		return max( 0, (int) apply_filters( 'aod_cod_turnstile_max_failopen', $max ) );
	}

	/**
	 * État de santé du challenge — lu par `/gestion` pour alerter le gérant.
	 *
	 * @return array{fails:int,last:int,cause:string,detail:string,degraded:bool,strict:bool}
	 */
	public static function health() {
		$brut  = get_transient( self::HEALTH_TRANSIENT );
		$sante = is_array( $brut ) ? $brut : array();
		$fails = isset( $sante['fails'] ) ? (int) $sante['fails'] : 0;

		return array(
			'fails'    => $fails,
			'last'     => isset( $sante['last'] ) ? (int) $sante['last'] : 0,
			'cause'    => isset( $sante['cause'] ) ? (string) $sante['cause'] : '',
			'detail'   => isset( $sante['detail'] ) ? (string) $sante['detail'] : '',
			// Dégradé : au moins un échec récent — la protection ne protège plus.
			'degraded' => $fails > 0,
			// Strict : le seuil est franchi, les commandes non vérifiées sont rejetées.
			'strict'   => $fails > self::max_failopen(),
		);
	}

	/* --------------------------------------------------------------------- */
	/* Admin : page de réglages (Réglages → COD Anti-spam)                   */
	/* --------------------------------------------------------------------- */

	public function menu() {
		add_options_page(
			__( 'COD Anti-spam (Turnstile)', 'aod-cod-form' ),
			__( 'COD Anti-spam', 'aod-cod-form' ),
			'manage_woocommerce',
			'aod-cod-turnstile',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'aod_cod_turnstile_group',
			self::OPTION,
			array( $this, 'sanitize' )
		);
	}

	/**
	 * Nettoie les clés saisies.
	 *
	 * @param mixed $input
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'site'   => isset( $input['site'] ) ? sanitize_text_field( $input['site'] ) : '',
			'secret' => isset( $input['secret'] ) ? sanitize_text_field( $input['secret'] ) : '',
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o        = get_option( self::OPTION, array() );
		$site     = is_array( $o ) && isset( $o['site'] ) ? $o['site'] : '';
		$secret   = is_array( $o ) && isset( $o['secret'] ) ? $o['secret'] : '';
		$by_const = defined( 'AOD_COD_TURNSTILE_SITE_KEY' ) || defined( 'AOD_COD_TURNSTILE_SECRET_KEY' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'COD Anti-spam — Cloudflare Turnstile', 'aod-cod-form' ); ?></h1>
			<p>
				<?php esc_html_e( 'Challenge anti-robot gratuit et invisible pour le formulaire de commande COD. Crée un widget « Turnstile » sur le tableau de bord Cloudflare (type de widget : Managed), puis colle les deux clés ci-dessous.', 'aod-cod-form' ); ?>
				<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">dash.cloudflare.com → Turnstile</a>
			</p>
			<p>
				<strong><?php echo $this->is_enabled() ? '✅ ' . esc_html__( 'Actif', 'aod-cod-form' ) : '⚠️ ' . esc_html__( 'Inactif (clés manquantes)', 'aod-cod-form' ); ?></strong>
			</p>
			<?php if ( $by_const ) : ?>
				<p><em><?php esc_html_e( 'Des clés sont définies dans wp-config.php (constantes) : elles ont priorité sur les champs ci-dessous.', 'aod-cod-form' ); ?></em></p>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'aod_cod_turnstile_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aod_ts_site"><?php esc_html_e( 'Clé de site (Site Key)', 'aod-cod-form' ); ?></label></th>
						<td><input name="<?php echo esc_attr( self::OPTION ); ?>[site]" id="aod_ts_site" type="text" class="regular-text" value="<?php echo esc_attr( $site ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_ts_secret"><?php esc_html_e( 'Clé secrète (Secret Key)', 'aod-cod-form' ); ?></label></th>
						<td><input name="<?php echo esc_attr( self::OPTION ); ?>[secret]" id="aod_ts_secret" type="password" class="regular-text" value="<?php echo esc_attr( $secret ); ?>" autocomplete="off"></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
