<?php
/**
 * Page Contact : coordonnées de la boutique + formulaire de contact bilingue.
 *
 * Le template n'en avait aucun. Volontairement minimal — Nom, e-mail, téléphone,
 * message — et bâti sur les MÊMES couches anti-bot que le formulaire COD (honeypot,
 * time-trap, limite par IP, Turnstile) : un formulaire public non protégé devient
 * un relais à spam en quelques jours, et il n'y a aucune raison d'avoir deux
 * défenses différentes dans le même plugin.
 *
 * Les coordonnées affichées viennent des réglages WooCommerce et du Personnalisateur.
 * Rien n'est codé en dur : sur un clone, le gérant les saisit, elles apparaissent.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Contact {

	/** Plafond de messages par IP et par heure. Large : un humain n'en envoie pas 5. */
	const MAX_PER_IP_HOUR = 5;

	/** @var AOD_COD_Contact|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'aod_contact_form', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_ajax_aod_contact_submit', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_aod_contact_submit', array( $this, 'handle_submit' ) );
	}

	/**
	 * Enregistre (sans charger) le JS du formulaire. Il n'est réellement chargé que
	 * sur la page qui porte le shortcode — cf. render().
	 */
	public function register_assets() {
		$path = AOD_COD_PATH . 'assets/js/aod-cod-contact.js';
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : AOD_COD_VERSION;

		wp_register_script( 'aod-cod-contact', AOD_COD_URL . 'assets/js/aod-cod-contact.js', array(), $ver, true );

		wp_localize_script( 'aod-cod-contact', 'AOD_CT', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			// Même nonce que le formulaire COD : même plugin, même origine, et cela évite
			// un second jeton à faire vivre.
			'nonce'    => wp_create_nonce( 'aod_cod_nonce' ),
			'i18n'     => array(
				'sending' => __( 'Envoi en cours…', 'aod-cod-form' ),
				'error'   => __( 'Une erreur est survenue. Veuillez réessayer.', 'aod-cod-form' ),
			),
		) );
	}

	/**
	 * Adresse de destination des messages.
	 *
	 * L'e-mail « expéditeur » de WooCommerce d'abord (c'est celui que le gérant
	 * configure), l'admin du site en secours.
	 *
	 * @return string
	 */
	protected function recipient() {
		$to = get_option( 'woocommerce_email_from_address' );
		if ( ! $to || ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		return (string) $to;
	}

	/**
	 * Coordonnées de la boutique, telles que le gérant les a saisies.
	 *
	 * @return array{address:string,email:string}
	 */
	protected function store_details() {
		$parts = array_filter( array(
			(string) get_option( 'woocommerce_store_address' ),
			(string) get_option( 'woocommerce_store_address_2' ),
			trim( (string) get_option( 'woocommerce_store_postcode' ) . ' ' . (string) get_option( 'woocommerce_store_city' ) ),
		) );

		return array(
			'address' => implode( ', ', $parts ),
			'email'   => $this->recipient(),
		);
	}

	/**
	 * Shortcode [aod_contact_form].
	 *
	 * @return string
	 */
	public function render() {
		// Le JS et le CSS du formulaire COD sont réutilisés : mêmes styles de champs,
		// même mécanique d'envoi AJAX. Pas de second jeu d'assets à maintenir.
		wp_enqueue_style( 'aod-cod-form' );
		wp_enqueue_style( 'aod-cod-pages' );
		wp_enqueue_script( 'aod-cod-contact' );

		if ( class_exists( 'AOD_COD_Turnstile' ) ) {
			AOD_COD_Turnstile::instance()->enqueue_script();
		}

		$store = $this->store_details();

		ob_start();
		?>
		<div class="aod-contact">

			<?php // ── Coordonnées. Chaque bloc disparaît s'il n'est pas renseigné. ── ?>
			<div class="aod-contact__details">
				<h2 class="aod-home__h2"><?php esc_html_e( 'Nous contacter', 'aod-cod-form' ); ?></h2>
				<p class="aod-contact__intro">
					<?php esc_html_e( 'Une question sur un produit, une commande ou une livraison ? Écrivez-nous, nous répondons rapidement.', 'aod-cod-form' ); ?>
				</p>
				<ul class="aod-contact__list">
					<?php if ( $store['address'] ) : ?>
						<li>
							<span class="aod-contact__ico" aria-hidden="true">📍</span>
							<span><?php echo esc_html( $store['address'] ); ?></span>
						</li>
					<?php endif; ?>
					<?php if ( $store['email'] ) : ?>
						<li>
							<span class="aod-contact__ico" aria-hidden="true">✉️</span>
							<a href="mailto:<?php echo esc_attr( $store['email'] ); ?>"><?php echo esc_html( $store['email'] ); ?></a>
						</li>
					<?php endif; ?>
				</ul>
			</div>

			<?php // ── Formulaire. ── ?>
			<form class="aod-cod aod-contact__form" method="post" novalidate>
				<div class="aod-cod__field">
					<label for="aod-ct-name"><?php esc_html_e( 'Nom complet', 'aod-cod-form' ); ?> <span>*</span></label>
					<input type="text" id="aod-ct-name" name="name" required autocomplete="name">
				</div>

				<div class="aod-cod__field">
					<label for="aod-ct-email"><?php esc_html_e( 'E-mail', 'aod-cod-form' ); ?> <span>*</span></label>
					<input type="email" id="aod-ct-email" name="email" required autocomplete="email">
				</div>

				<div class="aod-cod__field">
					<label for="aod-ct-phone"><?php esc_html_e( 'Téléphone (facultatif)', 'aod-cod-form' ); ?></label>
					<input type="tel" id="aod-ct-phone" name="phone" autocomplete="tel" inputmode="tel">
				</div>

				<div class="aod-cod__field">
					<label for="aod-ct-message"><?php esc_html_e( 'Message', 'aod-cod-form' ); ?> <span>*</span></label>
					<textarea id="aod-ct-message" name="message" rows="6" required></textarea>
				</div>

				<?php
				// Honeypot : invisible et non focalisable. Un humain ne le voit jamais ;
				// un robot qui remplit tous les champs se trahit. Même champ que le COD.
				?>
				<div class="aod-cod__hp" aria-hidden="true">
					<label for="aod-ct-url"><?php esc_html_e( 'Ne pas remplir', 'aod-cod-form' ); ?></label>
					<input type="text" id="aod-ct-url" name="aod_url" tabindex="-1" autocomplete="off">
				</div>

				<?php echo class_exists( 'AOD_COD_Turnstile' ) ? AOD_COD_Turnstile::instance()->widget_html() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<button type="submit" class="aod-cod__submit">
					<?php esc_html_e( 'Envoyer le message', 'aod-cod-form' ); ?>
				</button>
				<p class="aod-cod__msg" role="alert"></p>
			</form>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Traite l'envoi AJAX du message.
	 *
	 * Mêmes garde-fous que la commande COD, dans le même ordre, avec le même principe :
	 * en cas de rejet, message neutre — on n'indique jamais au robot quel garde-fou a
	 * réagi, sinon il lui suffit de le contourner un par un.
	 */
	public function handle_submit() {
		check_ajax_referer( 'aod_cod_nonce', 'nonce' );

		$neutral = __( 'Une erreur est survenue. Veuillez réessayer.', 'aod-cod-form' );

		// 1 & 2) Honeypot + time-trap — MÊME garde que la commande (étape 32.3).
		//    Le formulaire de contact partageait déjà les deux couches ; il partageait
		//    donc aussi leurs deux contournements. Les factoriser évite qu'un des deux
		//    formulaires soit durci et l'autre oublié — c'est exactement ce qui était
		//    en train d'arriver.
		$aod_ab = AOD_COD_Antibot::inspect(
			array_key_exists( 'aod_url', $_POST ) ? sanitize_text_field( wp_unslash( $_POST['aod_url'] ) ) : null,
			array_key_exists( 'elapsed', $_POST ) ? sanitize_text_field( wp_unslash( $_POST['elapsed'] ) ) : null
		);
		if ( '' !== $aod_ab ) {
			AOD_COD_Log::error( 'antibot', 'Message de contact refusé — ' . AOD_COD_Antibot::reason( $aod_ab ) . '.' );
			wp_send_json_error( array( 'message' => $neutral ), 400 );
		}

		// 3) Limite par IP.
		// 🔴 Même correctif qu'à l'étape 26.1 côté commande : WC_Geolocation renvoie
		//    « X-Real-IP » brut, donc forgeable. Ce coupe-circuit comptait par en-tête,
		//    pas par visiteur. Cf. class-aod-cod-ip.php.
		$ip = AOD_COD_IP::client();

		if ( '' !== $ip ) {
			$ip_key   = 'aod_ct_ipf_' . md5( $ip );
			$ip_count = (int) get_transient( $ip_key );
			if ( $ip_count >= self::MAX_PER_IP_HOUR ) {
				wp_send_json_error(
					array( 'message' => __( 'Trop de messages envoyés depuis votre connexion. Réessayez plus tard.', 'aod-cod-form' ) ),
					429
				);
			}
			set_transient( $ip_key, $ip_count + 1, HOUR_IN_SECONDS );
		}

		// 4) Turnstile.
		if ( class_exists( 'AOD_COD_Turnstile' ) ) {
			$ts = AOD_COD_Turnstile::instance();
			if ( $ts->is_enabled() ) {
				$token = isset( $_POST['cf_turnstile'] ) ? sanitize_text_field( wp_unslash( $_POST['cf_turnstile'] ) ) : '';
				if ( ! $ts->verify( $token, $ip ) ) {
					wp_send_json_error(
						array( 'message' => __( 'Vérification anti-robot échouée. Rechargez la page et réessayez.', 'aod-cod-form' ) ),
						403
					);
				}
			}
		}

		// --- Validation ---
		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

		$errors = array();
		if ( '' === $name ) {
			$errors[] = __( 'Veuillez indiquer votre nom.', 'aod-cod-form' );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			$errors[] = __( 'Veuillez indiquer un e-mail valide.', 'aod-cod-form' );
		}
		if ( '' === $message ) {
			$errors[] = __( 'Veuillez écrire votre message.', 'aod-cod-form' );
		}
		if ( $errors ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ), 400 );
		}

		// --- Envoi ---
		$to = $this->recipient();
		if ( ! $to || ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => $neutral ), 500 );
		}

		/* translators: %s: nom du visiteur */
		$subject = sprintf( __( 'Message de %s depuis le site', 'aod-cod-form' ), $name );

		$body = array(
			__( 'Nom :', 'aod-cod-form' ) . ' ' . $name,
			__( 'E-mail :', 'aod-cod-form' ) . ' ' . $email,
		);
		if ( '' !== $phone ) {
			$body[] = __( 'Téléphone :', 'aod-cod-form' ) . ' ' . $phone;
		}
		$body[] = '';
		$body[] = $message;

		// Reply-To sur l'e-mail du visiteur : le gérant répond d'un clic. On n'usurpe
		// PAS le From — un e-mail envoyé « de la part » d'un domaine tiers est classé
		// en spam par SPF/DKIM, et le message n'arriverait jamais.
		$headers = array(
			'Reply-To: ' . $name . ' <' . $email . '>',
		);

		$sent = wp_mail( $to, $subject, implode( "\n", $body ), $headers );

		if ( ! $sent ) {
			wp_send_json_error(
				array( 'message' => __( 'L’envoi a échoué. Réessayez ou écrivez-nous directement par e-mail.', 'aod-cod-form' ) ),
				500
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Merci ! Votre message a bien été envoyé, nous vous répondrons rapidement.', 'aod-cod-form' ) )
		);
	}
}
