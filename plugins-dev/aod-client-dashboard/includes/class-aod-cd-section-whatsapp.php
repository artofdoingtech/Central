<?php
/**
 * Section « WhatsApp » : notification instantanée au gérant à chaque commande,
 * via le service gratuit CallMeBot.
 *
 * 🧩 EXTRACTION (Phase 30.1, pilote). Cette section vivait dans le monolithe
 * `class-aod-cd-dashboard.php` (4229 lignes). Elle en sort SANS changer un octet de
 * comportement : mêmes réglages (`aod_cod_whatsapp`), mêmes actions AJAX
 * (`aod_cd_save_whatsapp`, `aod_cd_test_whatsapp`) — donc `dashboard.js` est
 * inchangé —, même rendu. C'est la première pièce d'un chantier qui finit le
 * pattern `AOD_CD_Section_*` déjà appliqué à home/stock/team/landing/products/
 * abandoned : le routeur (`render_section`) appelle désormais cette classe comme
 * les six autres.
 *
 * Les helpers d'UI partagés (`page_head`, `pill`…) RESTENT sur `AOD_CD_Dashboard`
 * en `public static` : ils servent toutes les sections, les déplacer casserait
 * 20+ appels. On les invoque donc via `AOD_CD_Dashboard::…`.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_WhatsApp {

	/** @var AOD_CD_Section_WhatsApp|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_aod_cd_save_whatsapp', array( $this, 'ajax_save_whatsapp' ) );
		add_action( 'wp_ajax_aod_cd_test_whatsapp', array( $this, 'ajax_test_whatsapp' ) );
	}

	/**
	 * Réglages WhatsApp, avec valeurs par défaut (option `aod_cod_whatsapp`).
	 *
	 * @return array{enabled:int,phone:string,apikey:string}
	 */
	protected function settings() {
		$saved = get_option( 'aod_cod_whatsapp', array() );
		return wp_parse_args( (array) $saved, array(
			'enabled' => 0,
			'phone'   => '',
			'apikey'  => '',
		) );
	}

	public function render() {
		$s = $this->settings();

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'WhatsApp', 'aod-client-dashboard' ),
			__( 'Recevez un message instantané à chaque nouvelle commande.', 'aod-client-dashboard' )
		);
		?>
		<form class="aod-cd-form aod-cd-settings-form" data-action="aod_cd_save_whatsapp">
			<div class="aod-cd-panel aod-cd-panel--narrow">
				<div class="aod-cd-panel__head">
					<span class="aod-cd-panel__ic" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'whatsapp', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-panel__name"><?php esc_html_e( 'Notifications WhatsApp', 'aod-client-dashboard' ); ?></span>
				</div>
				<div class="aod-cd-panel__body">
					<p class="aod-cd-note"><?php esc_html_e( 'Service gratuit via CallMeBot.', 'aod-client-dashboard' ); ?></p>

					<div class="aod-cd-steps">
						<strong><?php esc_html_e( 'Mise en route (une seule fois) :', 'aod-client-dashboard' ); ?></strong>
						<ol>
							<li><?php printf( esc_html__( 'Enregistrez le numéro %s dans vos contacts (nom : CallMeBot).', 'aod-client-dashboard' ), '<code>+34 644 84 71 89</code>' ); ?></li>
							<li><?php printf( esc_html__( 'Sur WhatsApp, envoyez-lui : %s', 'aod-client-dashboard' ), '<code>I allow callmebot to send me messages</code>' ); ?></li>
							<li><?php esc_html_e( 'Vous recevez votre clé API (apikey). Collez-la ci-dessous.', 'aod-client-dashboard' ); ?></li>
						</ol>
					</div>

					<div class="aod-cd-field">
						<label class="aod-cd-check">
							<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>>
							<?php esc_html_e( 'Envoyer un WhatsApp à chaque commande', 'aod-client-dashboard' ); ?>
						</label>
					</div>

					<label class="aod-cd-field aod-cd-field--md">
						<span class="aod-cd-label"><?php esc_html_e( 'Votre numéro WhatsApp', 'aod-client-dashboard' ); ?></span>
						<input type="text" name="phone" value="<?php echo esc_attr( $s['phone'] ); ?>" placeholder="213550123456">
						<span class="aod-cd-help"><?php esc_html_e( 'Avec l’indicatif pays, sans « + » ni espaces. Algérie = 213. Ex : 213550123456.', 'aod-client-dashboard' ); ?></span>
					</label>

					<label class="aod-cd-field aod-cd-field--md">
						<span class="aod-cd-label"><?php esc_html_e( 'Clé API (apikey)', 'aod-client-dashboard' ); ?></span>
						<input type="text" name="apikey" value="<?php echo esc_attr( $s['apikey'] ); ?>">
						<span class="aod-cd-help"><?php esc_html_e( 'Fournie par CallMeBot dans sa réponse WhatsApp.', 'aod-client-dashboard' ); ?></span>
					</label>
				</div>
			</div>

			<div class="aod-cd-form-foot aod-cd-savebar">
				<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Enregistrer', 'aod-client-dashboard' ); ?></button>
				<?php
				// Le bouton n'est PLUS désactivé tant que rien n'est enregistré : un
				// bouton grisé sans explication, c'est le clic qui « ne fait rien ».
				// Le serveur, lui, sait dire précisément ce qui manque.
				?>
				<button type="button" class="aod-cd-btn aod-cd-wa-test"><?php esc_html_e( 'Envoyer un message test', 'aod-client-dashboard' ); ?></button>
				<span class="aod-cd-wa-test-msg" aria-live="polite"></span>
				<span class="aod-cd-form-msg"></span>
			</div>
		</form>
		<?php
	}

	/** AJAX : enregistre les réglages WhatsApp (option aod_cod_whatsapp). */
	public function ajax_save_whatsapp() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$settings = array(
			'enabled' => isset( $_POST['enabled'] ) ? 1 : 0,
			'phone'   => isset( $_POST['phone'] ) ? preg_replace( '/[^\d+]/', '', wp_unslash( $_POST['phone'] ) ) : '',
			'apikey'  => isset( $_POST['apikey'] ) ? sanitize_text_field( wp_unslash( $_POST['apikey'] ) ) : '',
		);
		update_option( 'aod_cod_whatsapp', $settings );
		wp_send_json_success( array( 'message' => __( 'Réglages WhatsApp enregistrés.', 'aod-client-dashboard' ) ) );
	}

	/** AJAX : envoie un message WhatsApp de test via CallMeBot (bloquant). */
	public function ajax_test_whatsapp() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$s = $this->settings();
		if ( empty( $s['enabled'] ) || '' === $s['phone'] || '' === $s['apikey'] ) {
			wp_send_json_error( array( 'message' => __( 'Cochez l’envoi, renseignez le numéro et la clé, puis enregistrez avant de tester.', 'aod-client-dashboard' ) ), 400 );
		}

		$url = add_query_arg(
			array(
				'phone'  => preg_replace( '/\D+/', '', $s['phone'] ),
				'text'   => rawurlencode( __( '✅ Test : la notification WhatsApp de votre boutique fonctionne.', 'aod-client-dashboard' ) ),
				'apikey' => rawurldecode( $s['apikey'] ),
			),
			'https://api.callmebot.com/whatsapp.php'
		);

		$res = wp_remote_get( $url, array( 'timeout' => 20 ) );

		// Journalisation (31.3). Le verdict s'affiche au gérant, mais il ne
		// SURVIT pas à la fermeture de l'onglet — or c'est nous qu'on appellera
		// trois jours plus tard pour dire « le test ne passe pas ».
		// 🔒 `$url` porte la clé CallMeBot (`apikey`) : elle ne peut partir au
		// journal qu'à travers `AOD_COD_Log`, qui la masque (couche 2).
		// `class_exists` : le plugin public peut être désactivé — le diagnostic
		// n'a pas le droit de provoquer l'erreur fatale qu'il est censé aider à
		// résoudre.
		$journal = class_exists( 'AOD_COD_Log' );

		if ( is_wp_error( $res ) ) {
			if ( $journal ) {
				AOD_COD_Log::error(
					'whatsapp',
					'Test manuel depuis /gestion : appel réseau impossible — ' . $res->get_error_message(),
					array( 'url' => $url )
				);
			}
			wp_send_json_error( array( 'message' => __( 'Échec de l’envoi : ', 'aod-client-dashboard' ) . $res->get_error_message() ), 502 );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_success( array( 'message' => __( 'Message test envoyé. Vérifiez votre WhatsApp.', 'aod-client-dashboard' ) ) );
		}
		if ( $journal ) {
			AOD_COD_Log::error(
				'whatsapp',
				sprintf( 'Test manuel depuis /gestion : CallMeBot a refusé (HTTP %d)', $code ),
				array( 'url' => $url )
			);
		}
		wp_send_json_error( array( 'message' => sprintf( __( 'Réponse inattendue (HTTP %d). Vérifiez votre numéro et votre clé.', 'aod-client-dashboard' ), $code ) ), 502 );
	}
}
