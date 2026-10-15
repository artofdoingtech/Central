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
		// Les deux chaînes que le gérant doit RECOPIER dans WhatsApp. Elles ne sont
		// pas traduisibles et ne doivent jamais l'être : le numéro est celui du
		// service, et la phrase est le sésame que CallMeBot attend AU MOT PRÈS —
		// traduite, elle ne déclencherait rien.
		$bot_phone = '+34 644 84 71 89';
		$bot_pass  = 'I allow callmebot to send me messages';
		?>
		<?php
		// 🔴 `aod-cd-settings-form` EST OBLIGATOIRE, ET ELLE A DÉJÀ MANQUÉ ICI.
		// En refondant cette page le 2026-08-09, je l'ai remplacée par `aod-cd-wa`
		// au lieu de l'ajouter à côté. C'est cette classe — et elle seule — que
		// `bindSettingsForms()` cherche pour intercepter la soumission : sans elle,
		// « Enregistrer » repartait en soumission NATIVE, l'URL devenait
		// `?phone=…&apikey=` et RIEN n'était écrit. Mesuré : zéro requête AJAX.
		// ⚠️ Le formulaire est resté d'apparence normale, avec son bouton, sa barre
		// collante et son `data-action` — le réglage se perdait en silence.
		// `data-action` NOMME l'action ; il ne la branche pas.
		// Gardé depuis le 2026-08-10 par `tests/unit/test-formulaires-branches.php`.
		?>
		<form class="aod-cd-form aod-cd-settings-form aod-cd-wa" data-action="aod_cd_save_whatsapp">

			<?php
			/* 🚦 L'ÉTAT EN PREMIER, ET IL A TROIS VALEURS, PAS DEUX.
			   Le serveur refuse le test avec un message précis — « cochez l'envoi,
			   renseignez le numéro et la clé » — donc il SAIT distinguer « éteint »
			   de « allumé mais incomplet ». L'écran, lui, n'affichait qu'une case à
			   cocher de 13 px : une boutique pouvait avoir l'envoi coché sans clé,
			   ne rien recevoir, et rien sur cette page ne le disait.
			   Les trois libellés sont rendus puis départagés en CSS (`:has()`), comme
			   la mention « non livrable » de Livraison : pas de compteur à tenir à
			   jour, l'état suit la frappe sans un octet de JS. */
			?>
			<div class="aod-cd-wa-state">
				<span class="aod-cd-wa-state__ic" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'whatsapp', array( 'size' => 26 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé. ?></span>
				<div class="aod-cd-wa-state__txt">
					<h3 class="aod-cd-wa-state__t"><?php esc_html_e( 'Notifications WhatsApp', 'aod-client-dashboard' ); ?></h3>
					<p class="aod-cd-wa-state__s"><?php esc_html_e( 'Service gratuit via CallMeBot.', 'aod-client-dashboard' ); ?></p>
				</div>
				<span class="aod-cd-wa-pill aod-cd-wa-pill--on"><?php echo AOD_CD_Dashboard::pill( __( 'Actives', 'aod-client-dashboard' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput -- pill() échappe son libellé (esc_html) et son ton (esc_attr sur liste blanche). ?></span>
				<span class="aod-cd-wa-pill aod-cd-wa-pill--todo"><?php echo AOD_CD_Dashboard::pill( __( 'Numéro ou clé manquant', 'aod-client-dashboard' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput -- pill() échappe son libellé (esc_html) et son ton (esc_attr sur liste blanche). ?></span>
				<span class="aod-cd-wa-pill aod-cd-wa-pill--off"><?php echo AOD_CD_Dashboard::pill( __( 'Désactivées', 'aod-client-dashboard' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput -- pill() échappe son libellé (esc_html) et son ton (esc_attr sur liste blanche). ?></span>
				<label class="aod-cd-check aod-cd-wa-switch">
					<input type="checkbox" class="aod-cd-wa-enable" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>>
					<span class="aod-cd-wa-switch__ui" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Envoyer un WhatsApp à chaque commande', 'aod-client-dashboard' ); ?></span>
				</label>
			</div>

			<div class="aod-cd-wa-grid">

				<?php
				/* ① ② ③ — LA NUMÉROTATION EST VRAIE ICI, et c'est ce qui la justifie.
				   Sur « Pixels & Tracking » j'ai refusé de numéroter : quatre régies
				   ne sont pas un ordre. Ici, si : on ne peut pas recevoir la clé sans
				   avoir écrit au bot, ni lui écrire sans l'avoir enregistré. Le
				   dispositif structurel dit quelque chose de vrai du contenu.
				   Et les DEUX chaînes à recopier deviennent l'objet principal de la
				   page — c'est littéralement ce que le gérant vient y faire. */
				?>
				<section class="aod-cd-wa-setup">
					<h3 class="aod-cd-wa-h"><?php esc_html_e( 'Mise en route (une seule fois)', 'aod-client-dashboard' ); ?></h3>
					<ol class="aod-cd-wa-steps">
						<li>
							<p><?php esc_html_e( 'Enregistrez ce numéro dans vos contacts, sous le nom CallMeBot.', 'aod-client-dashboard' ); ?></p>
							<code class="aod-cd-wa-copy"><?php echo esc_html( $bot_phone ); ?></code>
						</li>
						<li>
							<p><?php esc_html_e( 'Sur WhatsApp, envoyez-lui exactement ce message :', 'aod-client-dashboard' ); ?></p>
							<code class="aod-cd-wa-copy"><?php echo esc_html( $bot_pass ); ?></code>
						</li>
						<li>
							<p><?php esc_html_e( 'CallMeBot répond avec votre clé API. Collez-la dans le champ « Clé API ».', 'aod-client-dashboard' ); ?></p>
						</li>
					</ol>
				</section>

				<section class="aod-cd-wa-ids">
					<h3 class="aod-cd-wa-h"><?php esc_html_e( 'Vos identifiants', 'aod-client-dashboard' ); ?></h3>

					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Votre numéro WhatsApp', 'aod-client-dashboard' ); ?></span>
						<?php
						/* `type=tel` + `inputmode=numeric` : sur un téléphone — c'est-à-dire
						   sur la quasi-totalité du trafic de ce tableau de bord — un
						   `type=text` ouvre le clavier ALPHABÉTIQUE pour saisir douze
						   chiffres. Le défaut ne se voit sur aucune capture de bureau. */
						?>
						<input type="tel" inputmode="numeric" autocomplete="tel" class="aod-cd-code aod-cd-wa-req" name="phone" value="<?php echo esc_attr( $s['phone'] ); ?>" placeholder="213550123456">
						<span class="aod-cd-help"><?php esc_html_e( 'Avec l’indicatif pays, sans « + » ni espaces. Algérie = 213. Ex : 213550123456.', 'aod-client-dashboard' ); ?></span>
					</label>

					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Clé API (apikey)', 'aod-client-dashboard' ); ?></span>
						<?php
						/* Marqueur `—` et non un exemple : le format des clés CallMeBot
						   n'est pas documenté ici, et un faux exemple se recopie. Il est
						   néanmoins INDISPENSABLE — c'est lui qui rend `:placeholder-shown`
						   utilisable, donc l'état « allumé mais incomplet » visible. */
						?>
						<input type="text" class="aod-cd-code aod-cd-wa-req" name="apikey" value="<?php echo esc_attr( $s['apikey'] ); ?>" placeholder="—">
						<span class="aod-cd-help"><?php esc_html_e( 'Fournie par CallMeBot dans sa réponse WhatsApp.', 'aod-client-dashboard' ); ?></span>
					</label>

					<?php
					/* Le test a QUITTÉ la barre d'enregistrement. Il n'enregistre rien :
					   il éprouve les valeurs DÉJÀ enregistrées. Le laisser à côté de
					   « Enregistrer » faisait porter deux métiers à une même rangée, et
					   suggérait que les deux boutons agissaient sur la saisie en cours. */
					?>
					<div class="aod-cd-wa-testrow">
						<button type="button" class="aod-cd-btn aod-cd-wa-test"><?php esc_html_e( 'Envoyer un message test', 'aod-client-dashboard' ); ?></button>
						<span class="aod-cd-wa-test-msg" aria-live="polite"></span>
					</div>
					<p class="aod-cd-help"><?php esc_html_e( 'Le test utilise les valeurs enregistrées : enregistrez d’abord si vous venez de les coller.', 'aod-client-dashboard' ); ?></p>
				</section>
			</div>

			<div class="aod-cd-form-foot aod-cd-savebar">
				<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Enregistrer', 'aod-client-dashboard' ); ?></button>
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
