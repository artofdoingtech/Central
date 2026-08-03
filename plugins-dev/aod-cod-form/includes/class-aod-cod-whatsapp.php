<?php
/**
 * Notification WhatsApp au commerçant à chaque commande COD (via CallMeBot, gratuit).
 *
 * CallMeBot envoie un message WhatsApp vers TON propre numéro.
 * Mise en route (une fois) :
 *   1. Enregistre le numéro +34 644 84 71 89 dans tes contacts (CallMeBot).
 *   2. Envoie « I allow callmebot to send me messages » à ce numéro sur WhatsApp.
 *   3. Tu reçois en retour ta clé API (apikey).
 *   4. Colle ton numéro (avec indicatif, ex : 213550123456) et la clé dans
 *      WooCommerce → Notif WhatsApp.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_WhatsApp {

	/** @var AOD_COD_WhatsApp|null */
	protected static $instance = null;

	const OPTION = 'aod_cod_whatsapp';

	/** Hook de la tâche différée qui envoie réellement la notification (31.3). */
	const DISPATCH_HOOK = 'aod_cod_whatsapp_dispatch';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Mise en file déclenchée par le formulaire COD (front, via AJAX).
		add_action( 'aod_cod_order_created', array( $this, 'notify' ), 10, 1 );
		// …et l'envoi réel, hors de la requête de l'acheteur (31.3, dette D22).
		add_action( self::DISPATCH_HOOK, array( $this, 'dispatch' ), 10, 1 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_init', array( $this, 'maybe_save' ) );
		}
	}

	/**
	 * Réglages enregistrés (avec valeurs par défaut).
	 *
	 * @return array
	 */
	public function settings() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( (array) $saved, array(
			'enabled' => 0,
			'phone'   => '',
			'apikey'  => '',
		) );
	}

	/**
	 * La notification est-elle prête à partir ?
	 *
	 * @return bool
	 */
	public function is_configured() {
		$s = $this->settings();
		return ! empty( $s['enabled'] ) && '' !== $s['phone'] && '' !== $s['apikey'];
	}

	/* --------------------------------------------------------------------- */
	/* Envoi                                                                 */
	/* --------------------------------------------------------------------- */

	/**
	 * Met la notification en file. Ne l'envoie PAS depuis la requête de l'acheteur.
	 *
	 * 🚨 CE QUI ÉTAIT FAUX (dette D22, soldée en 31.3). L'appel partait ici même,
	 * en `blocking => false`, avec ce commentaire : « ne ralentit pas la réponse au
	 * client ». Deux choses clochaient, et elles se renforçaient :
	 *
	 *   1. **La détection d'erreur ne pouvait pas se déclencher.** En
	 *      `blocking => false`, `wp_remote_get()` rend une réponse FACTICE
	 *      immédiate sans attendre le serveur : `is_wp_error()` était donc faux
	 *      dans tous les cas réels. Le bloc « Notification WhatsApp non envoyée »
	 *      était du **code mort**. Clé invalide, CallMeBot en panne, quota
	 *      dépassé → aucune note, aucune trace : **le gérant croyait être notifié
	 *      et ne l'était pas.**
	 *   2. **Et ça ralentissait quand même l'acheteur.** `blocking => false`
	 *      n'évite ni la résolution DNS ni le `connect()` — seulement l'attente de
	 *      la RÉPONSE. Sur un clone sans DNS sortant — le cas NOMINAL de
	 *      TEMPLATE.md §3 — chaque soumission COD attendait le timeout du
	 *      résolveur avant de répondre.
	 *
	 * LA CORRECTION reprend le motif déjà retenu par la Phase 14 pour les
	 * conversions serveur : sortir l'appel du chemin d'achat, et le rendre
	 * BLOQUANT là où plus personne n'attend. L'acheteur ne paie plus rien du tout
	 * (ni DNS, ni connect), et l'échec redevient détectable — donc journalisable,
	 * donc réparable. Ce n'est pas un compromis : les deux progressent.
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function notify( $order ) {
		if ( ! $this->is_configured() || ! $order instanceof WC_Order ) {
			return;
		}

		$args = array( $order->get_id() );

		// Hook rejoué (HPOS re-déclenche volontiers) : ne pas empiler deux envois.
		if ( wp_next_scheduled( self::DISPATCH_HOOK, $args ) ) {
			return;
		}

		wp_schedule_single_event( time(), self::DISPATCH_HOOK, $args );
	}

	/**
	 * Envoie réellement la notification. Exécuté hors de la requête de l'acheteur.
	 *
	 * On re-vérifie la configuration : entre la mise en file et l'exécution, le
	 * gérant a pu désactiver la notification.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public function dispatch( $order_id ) {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof WC_Order || ! $this->is_configured() ) {
			return; // Commande supprimée, ou notification débranchée entre-temps.
		}

		$s   = $this->settings();
		$msg = $this->build_message( $order );

		$url = add_query_arg(
			array(
				'phone'  => preg_replace( '/\D+/', '', $s['phone'] ),
				'text'   => rawurlencode( $msg ),
				'apikey' => rawurldecode( $s['apikey'] ),
			),
			'https://api.callmebot.com/whatsapp.php'
		);

		// BLOQUANT, à dessein : plus personne n'attend derrière, et c'est ce qui
		// rend le résultat lisible. Cf. le bloc explicatif de `notify()`.
		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );

		// 🔒 `$url` porte la clé CallMeBot en query arg (`apikey`). La journaliser
		// telle quelle publierait la clé du gérant : c'est la couche 2 d'
		// `AOD_COD_Log` (masquage par nom de paramètre d'URL) qui l'en empêche.
		if ( is_wp_error( $res ) ) {
			AOD_COD_Log::error( 'whatsapp', sprintf(
				'Notification de la commande %d non envoyée (réseau) : %s',
				$order->get_id(),
				$res->get_error_message()
			), array( 'url' => $url ) );
			$order->add_order_note( sprintf(
				/* translators: %s: message d'erreur */
				__( 'Notification WhatsApp non envoyée : %s', 'aod-cod-form' ),
				$res->get_error_message()
			) );
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			AOD_COD_Log::error( 'whatsapp', sprintf(
				'Notification de la commande %d refusée par CallMeBot (HTTP %d)',
				$order->get_id(),
				$code
			), array( 'url' => $url ) );
			$order->add_order_note( sprintf(
				/* translators: %d: code HTTP */
				__( 'Notification WhatsApp non envoyée (HTTP %d). Vérifiez le numéro et la clé.', 'aod-cod-form' ),
				$code
			) );
		}
	}

	/**
	 * Compose le texte du message.
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	protected function build_message( $order ) {
		$lines   = array();
		$lines[] = '🛒 ' . sprintf(
			/* translators: %s: numéro de commande */
			__( 'Nouvelle commande #%s', 'aod-cod-form' ),
			$order->get_order_number()
		);

		// Produits.
		$products = array();
		foreach ( $order->get_items() as $item ) {
			$products[] = $item->get_name() . ' x' . $item->get_quantity();
		}
		if ( $products ) {
			$lines[] = '📦 ' . implode( ', ', $products );
		}

		$lines[] = '👤 ' . trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$lines[] = '📞 ' . $order->get_billing_phone();

		$wilaya  = $order->get_meta( '_aod_wilaya_name' );
		$commune = $order->get_meta( '_aod_commune' );
		if ( $wilaya || $commune ) {
			$lines[] = '📍 ' . trim( $commune . ( $wilaya ? ', ' . $wilaya : '' ) );
		}

		$addr = $order->get_billing_address_1();
		if ( $addr ) {
			$lines[] = '🏠 ' . $addr;
		}

		$delivery = $order->get_meta( '_aod_delivery_type' );
		if ( $delivery ) {
			$lines[] = '🚚 ' . ( 'desk' === $delivery
				? __( 'Stop-desk (bureau)', 'aod-cod-form' )
				: __( 'À domicile', 'aod-cod-form' ) );
		}

		$lines[] = '💰 ' . html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) );

		return implode( "\n", $lines );
	}

	/**
	 * Envoie un message de test (bloquant pour pouvoir rapporter le résultat).
	 */
	protected function send_test() {
		if ( ! $this->is_configured() ) {
			add_settings_error( 'aod_cod_wa', 'test', __( 'Configure et active d’abord la notification.', 'aod-cod-form' ), 'error' );
			return;
		}

		$s   = $this->settings();
		$url = add_query_arg(
			array(
				'phone'  => preg_replace( '/\D+/', '', $s['phone'] ),
				'text'   => rawurlencode( __( '✅ Test AOD COD : la notification WhatsApp fonctionne.', 'aod-cod-form' ) ),
				'apikey' => rawurldecode( $s['apikey'] ),
			),
			'https://api.callmebot.com/whatsapp.php'
		);

		$res = wp_remote_get( $url, array( 'timeout' => 20 ) );

		if ( is_wp_error( $res ) ) {
			add_settings_error( 'aod_cod_wa', 'test', sprintf(
				/* translators: %s: message d'erreur */
				__( 'Échec de l’envoi : %s', 'aod-cod-form' ),
				$res->get_error_message()
			), 'error' );
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code >= 200 && $code < 300 ) {
			add_settings_error( 'aod_cod_wa', 'test', __( 'Message de test envoyé. Vérifie ton WhatsApp.', 'aod-cod-form' ), 'updated' );
		} else {
			add_settings_error( 'aod_cod_wa', 'test', sprintf(
				/* translators: 1: code HTTP, 2: réponse */
				__( 'Réponse inattendue de CallMeBot (HTTP %1$d). Vérifie ton numéro et ta clé. Détail : %2$s', 'aod-cod-form' ),
				$code,
				esc_html( wp_strip_all_tags( wp_remote_retrieve_body( $res ) ) )
			), 'error' );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Admin                                                                 */
	/* --------------------------------------------------------------------- */

	public function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Notification WhatsApp', 'aod-cod-form' ),
			__( 'Notif WhatsApp', 'aod-cod-form' ),
			'manage_woocommerce',
			'aod-cod-whatsapp',
			array( $this, 'render_page' )
		);
	}

	public function maybe_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Envoi d'un message de test.
		if ( isset( $_POST['aod_cod_wa_test'] ) ) {
			check_admin_referer( 'aod_cod_wa_test' );
			$this->send_test();
			return;
		}

		if ( ! isset( $_POST['aod_cod_wa_save'] ) ) {
			return;
		}
		check_admin_referer( 'aod_cod_wa_save' );

		$settings = array(
			'enabled' => isset( $_POST['enabled'] ) ? 1 : 0,
			'phone'   => isset( $_POST['phone'] ) ? preg_replace( '/[^\d+]/', '', wp_unslash( $_POST['phone'] ) ) : '',
			'apikey'  => isset( $_POST['apikey'] ) ? sanitize_text_field( wp_unslash( $_POST['apikey'] ) ) : '',
		);
		update_option( self::OPTION, $settings );

		add_settings_error( 'aod_cod_wa', 'saved', __( 'Réglages WhatsApp enregistrés.', 'aod-cod-form' ), 'updated' );
	}

	public function render_page() {
		$s = $this->settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Notification WhatsApp des commandes', 'aod-cod-form' ); ?></h1>
			<?php settings_errors( 'aod_cod_wa' ); ?>

			<p><?php esc_html_e( 'Reçois un message WhatsApp instantané sur ton téléphone à chaque nouvelle commande COD. Service gratuit via CallMeBot.', 'aod-cod-form' ); ?></p>

			<div class="notice notice-info inline" style="max-width:680px">
				<p><strong><?php esc_html_e( 'Mise en route (une seule fois) :', 'aod-cod-form' ); ?></strong></p>
				<ol style="margin-left:1.2em">
					<li><?php
						/* translators: %s: numéro WhatsApp CallMeBot */
						printf( esc_html__( 'Enregistre le numéro %s dans tes contacts (nom : CallMeBot).', 'aod-cod-form' ), '<code>+34 644 84 71 89</code>' );
					?></li>
					<li><?php
						/* translators: %s: phrase à envoyer */
						printf( esc_html__( 'Sur WhatsApp, envoie ce message à ce contact : %s', 'aod-cod-form' ), '<code>I allow callmebot to send me messages</code>' );
					?></li>
					<li><?php esc_html_e( 'Tu reçois en réponse ta clé API (apikey). Colle-la ci-dessous.', 'aod-cod-form' ); ?></li>
				</ol>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'aod_cod_wa_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Activer', 'aod-cod-form' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>>
								<?php esc_html_e( 'Envoyer un WhatsApp à chaque commande', 'aod-cod-form' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_wa_phone"><?php esc_html_e( 'Ton numéro WhatsApp', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_wa_phone" name="phone" value="<?php echo esc_attr( $s['phone'] ); ?>" class="regular-text" placeholder="213550123456">
							<p class="description"><?php esc_html_e( 'Avec l’indicatif pays, sans le « + » ni espaces. Algérie = 213. Ex : 213550123456.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_wa_apikey"><?php esc_html_e( 'Clé API (apikey)', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_wa_apikey" name="apikey" value="<?php echo esc_attr( $s['apikey'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Fournie par CallMeBot dans sa réponse WhatsApp.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
				</table>
				<p><button type="submit" name="aod_cod_wa_save" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'aod-cod-form' ); ?></button></p>
			</form>

			<?php if ( $this->is_configured() ) : ?>
				<hr>
				<form method="post">
					<?php wp_nonce_field( 'aod_cod_wa_test' ); ?>
					<p><button type="submit" name="aod_cod_wa_test" class="button"><?php esc_html_e( 'Envoyer un message de test', 'aod-cod-form' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
