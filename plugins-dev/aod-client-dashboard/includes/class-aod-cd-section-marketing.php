<?php
/**
 * Section « Pixels & Tracking » (marketing) : les identifiants de régies
 * publicitaires (Meta/TikTok/Snapchat/Google) et les jetons Conversions API (CAPI),
 * plus la devise de reporting.
 *
 * 🧩 EXTRACTION (Phase 30.2). Sortie du monolithe `class-aod-cd-dashboard.php` à
 * comportement CONSTANT : mêmes réglages (`aod_cod_pixels`), mêmes actions AJAX
 * (`aod_cd_save_pixels`, `aod_cd_test_capi`) — `dashboard.js` inchangé —, même
 * rendu. Move verbatim ; seuls `self::page_head`/`self::pill` (helpers d'UI
 * partagés, restés sur AOD_CD_Dashboard) deviennent `AOD_CD_Dashboard::…`.
 *
 * 🔒 `capi_token_from_post()` reste `private static` (Phase 24.1, dette D15) : elle
 * lit `$_POST` sans le recevoir en argument, son seul appelant `ajax_save_pixels()`
 * nonce en premier. `tests/unit/test-nonce-contract.php` garde ce contrat — sa
 * référence a suivi la méthode dans cette classe.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Marketing {

	/** @var AOD_CD_Section_Marketing|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_aod_cd_save_pixels', array( $this, 'ajax_save_pixels' ) );
		add_action( 'wp_ajax_aod_cd_test_capi', array( $this, 'ajax_test_capi' ) );
	}

	/** Valeurs par défaut + valeurs enregistrées de l'option pixels. */
	protected function pixels_settings() {
		$saved = get_option( 'aod_cod_pixels', array() );
		return wp_parse_args( (array) $saved, array(
			'meta'                     => '',
			'tiktok'                   => '',
			'snapchat'                 => '',
			'google_ads'               => '',
			'google_label'             => '',
			'meta_domain_verification' => '',
			// Conversions API (CAPI) Meta : jeton SECRET (jamais ré-affiché en
			// clair) + code d'événement test optionnel. Vide = pixel navigateur seul.
			'meta_capi_token'          => '',
			'meta_test_event_code'     => '',
			'tiktok_capi_token'        => '', // Events API TikTok (Phase 13.1), secret.
			'snapchat_capi_token'      => '', // Conversions API Snapchat (Phase 13.2), secret.
			'tiktok_test_event_code'   => '', // Code d'événement test TikTok (Phase 13.3), non secret.
			// Devise de reporting : Meta refuse DZD → la valeur des achats n'est
			// pas mesurée. Vides = DZD tel quel (défaut). Cf. AOD_COD_Pixels::to_reporting().
			'report_currency'          => '',
			'report_rate'              => '',
		) );
	}

	/** Nettoie un identifiant de pixel (mêmes caractères sûrs que le plugin COD). */
	protected static function clean_pixel_id( $v ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $v );
	}

	/** Nettoie un code de devise ISO 4217 (3 lettres majuscules, ex. USD). */
	protected static function clean_currency( $v ) {
		return substr( strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $v ) ), 0, 3 );
	}

	/**
	 * Extrait le code de vérification de domaine Meta (code brut OU balise
	 * <meta name="facebook-domain-verification" content="…"> collée telle quelle).
	 */
	protected static function clean_domain_verification( $v ) {
		$v = (string) $v;
		if ( preg_match( '/content\s*=\s*[\'"]([^\'"]+)[\'"]/i', $v, $m ) ) {
			$v = $m[1];
		}
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', trim( $v ) );
	}

	/**
	 * Résout le jeton CAPI à enregistrer depuis le POST sans jamais l'exiger en
	 * clair : case « effacer » → ''  ; nouveau jeton saisi → nettoyé ; champ vide
	 * → jeton existant conservé. Miroir de AOD_COD_Pixels::capi_token_from_post()
	 * (le dashboard duplique volontairement les helpers pixels pour ne pas
	 * dépendre du plugin COD). Nonce vérifié par l'appelant (check_ajax_referer).
	 *
	 * 🔒 `private` À DESSEIN (Phase 24.1, dette D15) — même raison que son jumeau
	 * `AOD_COD_Pixels::capi_token_from_post()`. Elle lit `$_POST` sans le recevoir
	 * en argument ; `protected` laissait la porte ouverte à une sous-classe future
	 * qui l'appellerait sans nonce. Seul appelant : `ajax_save_pixels()`, qui nonce
	 * en premier. → `tests/unit/test-nonce-contract.php` garde la visibilité.
	 *
	 * @param string $existing Jeton déjà enregistré.
	 * @param string $field    Champ POST (défaut `meta_capi_token` ; case « effacer »
	 *                         = `{$field}_clear`). Généralisé pour TikTok (13.1).
	 * @return string
	 */
	private static function capi_token_from_post( $existing, $field = 'meta_capi_token' ) {
		if ( ! empty( $_POST[ $field . '_clear' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}
		$posted = isset( $_POST[ $field ] ) ? trim( (string) wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return '' !== $posted ? sanitize_text_field( $posted ) : (string) $existing;
	}

	public function render() {
		$s = $this->pixels_settings();

		// Une carte par régie : le gérant voit d'un coup d'œil ce qui est branché.
		// Le PREMIER champ est celui qui décide de l'activation — pour Google, c'est
		// l'ID de conversion : le libellé seul ne pose que la balise de base, et
		// pour Meta, la vérification de domaine ne fait rien sans pixel.
		$platforms = array(
			array(
				'name'   => __( 'Meta — Facebook & Instagram', 'aod-client-dashboard' ),
				'ic'     => 'meta',
				'tone'   => 'blue',
				'brand'  => 'meta',
				'fields' => array(
					array( 'meta', __( 'ID du pixel', 'aod-client-dashboard' ), '1234567890', __( 'Gestionnaire d’événements Meta → Paramètres → ID du pixel.', 'aod-client-dashboard' ) ),
					array( 'meta_domain_verification', __( 'Vérification de domaine', 'aod-client-dashboard' ), 'q7tv7ppepuk3m4z9op7bjcr3747t93', __( 'Pour confirmer que le domaine vous appartient. Collez ici la balise donnée par Meta (ou seulement son code) : elle sera ajoutée dans l’en-tête du site. Puis cliquez « Vérifier le domaine » dans Meta.', 'aod-client-dashboard' ) ),
				),
			),
			array(
				'name'   => __( 'TikTok', 'aod-client-dashboard' ),
				'ic'     => 'tiktok',
				'tone'   => 'slate',
				'brand'  => 'tiktok',
				'fields' => array(
					array( 'tiktok', __( 'ID du pixel', 'aod-client-dashboard' ), 'CXXXXXXXXXXXXXXXXXXX', __( 'TikTok Ads → Outils → Événements → Pixel Web.', 'aod-client-dashboard' ) ),
				),
			),
			array(
				'name'   => __( 'Snapchat', 'aod-client-dashboard' ),
				'ic'     => 'snapchat',
				'tone'   => 'amber',
				'brand'  => 'snapchat',
				'fields' => array(
					array( 'snapchat', __( 'ID du pixel', 'aod-client-dashboard' ), '00000000-0000-0000-0000-000000000000', __( 'Snapchat Ads → Gestionnaire d’événements → Pixel ID.', 'aod-client-dashboard' ) ),
				),
			),
			array(
				'name'   => __( 'Google Ads', 'aod-client-dashboard' ),
				'ic'     => 'google',
				'tone'   => 'red',
				'brand'  => 'google',
				'fields' => array(
					array( 'google_ads', __( 'ID de conversion', 'aod-client-dashboard' ), 'AW-123456789', __( 'Commence par AW-. Google Ads → Objectifs → Conversions.', 'aod-client-dashboard' ) ),
					array( 'google_label', __( 'Libellé de conversion', 'aod-client-dashboard' ), 'AbC-D_efG-h12', __( 'La partie après « / » dans send_to. Sans lui, seule la balise de base est posée.', 'aod-client-dashboard' ) ),
				),
			),
		);

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Pixels & Tracking', 'aod-client-dashboard' ), // Même libellé que la barre latérale.
			__( 'Une carte par régie : collez l’identifiant, laissez vide pour désactiver.', 'aod-client-dashboard' )
		);
		?>
		<form class="aod-cd-form aod-cd-settings-form" data-action="aod_cd_save_pixels">
			<p class="aod-cd-note"><?php esc_html_e( 'Le PageView est suivi sur tout le site ; l’achat est mesuré sur la page de remerciement (une fois par commande).', 'aod-client-dashboard' ); ?></p>

			<div class="aod-cd-pxcards">
				<?php foreach ( $platforms as $p ) :
					$on = '' !== trim( (string) $s[ $p['fields'][0][0] ] ); ?>
					<div class="aod-cd-pxcard aod-cd-pxcard--brand aod-cd-pxcard--<?php echo esc_attr( $p['brand'] ); ?><?php echo $on ? ' is-on' : ''; ?>">
						<div class="aod-cd-pxcard__head">
							<span class="aod-cd-pxcard__ic t-<?php echo esc_attr( $p['tone'] ); ?>" aria-hidden="true"><?php echo AOD_CD_Icon::svg( $p['ic'], array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé. ?></span>
							<span class="aod-cd-pxcard__name"><?php echo esc_html( $p['name'] ); ?></span>
							<span class="aod-cd-px-on"<?php echo $on ? '' : ' hidden'; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Actif', 'aod-client-dashboard' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<span class="aod-cd-px-off"<?php echo $on ? ' hidden' : ''; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Inactif', 'aod-client-dashboard' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
						<div class="aod-cd-pxcard__body">
							<?php foreach ( $p['fields'] as $i => $f ) :
								list( $key, $label, $ph, $desc ) = $f; ?>
								<label class="aod-cd-field">
									<span class="aod-cd-label"><?php echo esc_html( $label ); ?></span>
									<input type="text" class="<?php echo 0 === $i ? 'aod-cd-px-id' : ''; ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" placeholder="<?php echo esc_attr( $ph ); ?>">
									<span class="aod-cd-help"><?php echo esc_html( $desc ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php $capi_on = '' !== trim( (string) $s['meta_capi_token'] ); ?>
			<div class="aod-cd-pxcard aod-cd-pxcard--brand aod-cd-pxcard--meta<?php echo $capi_on ? ' is-on' : ''; ?>" style="margin-top:var(--cd-sp-4)">
				<div class="aod-cd-pxcard__head">
					<span class="aod-cd-pxcard__ic t-blue" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'meta', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-pxcard__name"><?php esc_html_e( 'Meta — API de Conversion (CAPI)', 'aod-client-dashboard' ); ?></span>
					<span class="aod-cd-px-on"<?php echo $capi_on ? '' : ' hidden'; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Actif', 'aod-client-dashboard' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-px-off"<?php echo $capi_on ? ' hidden' : ''; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Inactif', 'aod-client-dashboard' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</div>
				<div class="aod-cd-pxcard__body">
					<p class="aod-cd-note"><?php esc_html_e( 'Envoie aussi vos achats à Meta depuis le serveur (Facebook/Instagram), en plus du pixel du navigateur : vous ne perdez plus les conversions bloquées par iOS ou les bloqueurs de pub. La déduplication est automatique. Nécessite l’ID du pixel Meta ci-dessus.', 'aod-client-dashboard' ); ?></p>
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Jeton d’accès (secret)', 'aod-client-dashboard' ); ?></span>
						<input type="password" name="meta_capi_token" value="" autocomplete="off" placeholder="<?php echo $capi_on ? esc_attr__( '•••••••• enregistré — laisser vide pour le conserver', 'aod-client-dashboard' ) : 'EAAG...'; ?>">
						<span class="aod-cd-help"><?php esc_html_e( 'Gestionnaire d’événements Meta → Paramètres → Conversions API → Générer un jeton d’accès. Laisser vide pour garder le pixel du navigateur seul.', 'aod-client-dashboard' ); ?></span>
					</label>
					<?php if ( $capi_on ) : ?>
						<label class="aod-cd-check" style="display:flex;gap:8px;align-items:center;margin-block:var(--cd-sp-2)">
							<input type="checkbox" name="meta_capi_token_clear" value="1">
							<span><?php esc_html_e( 'Effacer le jeton enregistré', 'aod-client-dashboard' ); ?></span>
						</label>
					<?php endif; ?>
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Code d’événement test (optionnel)', 'aod-client-dashboard' ); ?></span>
						<input type="text" name="meta_test_event_code" value="<?php echo esc_attr( $s['meta_test_event_code'] ); ?>" placeholder="TEST12345">
						<span class="aod-cd-help"><?php esc_html_e( 'Pour voir vos achats arriver dans le Gestionnaire d’événements → onglet « Test des événements ». À vider une fois validé.', 'aod-client-dashboard' ); ?></span>
					</label>
					<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:var(--cd-sp-3)">
						<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-capi-test" data-platform="meta"><?php esc_html_e( 'Tester la connexion', 'aod-client-dashboard' ); ?></button>
						<span class="aod-cd-capi-test-msg" aria-live="polite"></span>
					</div>
					<p class="aod-cd-help"><?php esc_html_e( 'Le test utilise le jeton enregistré : enregistrez d’abord si vous venez de le coller.', 'aod-client-dashboard' ); ?></p>
				</div>
			</div>

			<?php $tt_capi_on = '' !== trim( (string) $s['tiktok_capi_token'] ); ?>
			<div class="aod-cd-pxcard aod-cd-pxcard--brand aod-cd-pxcard--tiktok<?php echo $tt_capi_on ? ' is-on' : ''; ?>" style="margin-top:var(--cd-sp-4)">
				<div class="aod-cd-pxcard__head">
					<span class="aod-cd-pxcard__ic t-slate" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'tiktok', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-pxcard__name"><?php esc_html_e( 'TikTok — API de Conversion (Events API)', 'aod-client-dashboard' ); ?></span>
					<span class="aod-cd-px-on"<?php echo $tt_capi_on ? '' : ' hidden'; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Actif', 'aod-client-dashboard' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-px-off"<?php echo $tt_capi_on ? ' hidden' : ''; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Inactif', 'aod-client-dashboard' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</div>
				<div class="aod-cd-pxcard__body">
					<p class="aod-cd-note"><?php esc_html_e( 'Envoie aussi vos achats à TikTok depuis le serveur, en plus du pixel du navigateur : vous ne perdez plus les conversions bloquées côté navigateur. La déduplication est automatique. Nécessite l’ID du pixel TikTok ci-dessus.', 'aod-client-dashboard' ); ?></p>
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Jeton d’accès (secret)', 'aod-client-dashboard' ); ?></span>
						<input type="password" name="tiktok_capi_token" value="" autocomplete="off" placeholder="<?php echo $tt_capi_on ? esc_attr__( '•••••••• enregistré — laisser vide pour le conserver', 'aod-client-dashboard' ) : '...'; ?>">
						<span class="aod-cd-help"><?php esc_html_e( 'TikTok Ads → Outils → Événements → gérer l’Events API → générer un jeton d’accès. Laisser vide pour garder le pixel du navigateur seul.', 'aod-client-dashboard' ); ?></span>
					</label>
					<?php if ( $tt_capi_on ) : ?>
						<label class="aod-cd-check" style="display:flex;gap:8px;align-items:center;margin-block:var(--cd-sp-2)">
							<input type="checkbox" name="tiktok_capi_token_clear" value="1">
							<span><?php esc_html_e( 'Effacer le jeton enregistré', 'aod-client-dashboard' ); ?></span>
						</label>
					<?php endif; ?>
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Code d’événement test (requis pour tester)', 'aod-client-dashboard' ); ?></span>
						<input type="text" name="tiktok_test_event_code" value="<?php echo esc_attr( $s['tiktok_test_event_code'] ); ?>" placeholder="TEST12345">
						<span class="aod-cd-help"><?php esc_html_e( 'TikTok Ads → Événements → onglet « Test ». Requis pour le bouton « Tester » ci-dessous (l’événement de test n’entre pas dans vos statistiques). À vider une fois validé.', 'aod-client-dashboard' ); ?></span>
					</label>
					<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:var(--cd-sp-3)">
						<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-capi-test" data-platform="tiktok"><?php esc_html_e( 'Tester la connexion', 'aod-client-dashboard' ); ?></button>
						<span class="aod-cd-capi-test-msg" aria-live="polite"></span>
					</div>
					<p class="aod-cd-help"><?php esc_html_e( 'Le test utilise le jeton enregistré : enregistrez d’abord si vous venez de le coller.', 'aod-client-dashboard' ); ?></p>
				</div>
			</div>

			<?php $sc_capi_on = '' !== trim( (string) $s['snapchat_capi_token'] ); ?>
			<div class="aod-cd-pxcard aod-cd-pxcard--brand aod-cd-pxcard--snapchat<?php echo $sc_capi_on ? ' is-on' : ''; ?>" style="margin-top:var(--cd-sp-4)">
				<div class="aod-cd-pxcard__head">
					<span class="aod-cd-pxcard__ic t-slate" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'snapchat', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-pxcard__name"><?php esc_html_e( 'Snapchat — API de Conversion', 'aod-client-dashboard' ); ?></span>
					<span class="aod-cd-px-on"<?php echo $sc_capi_on ? '' : ' hidden'; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Actif', 'aod-client-dashboard' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-px-off"<?php echo $sc_capi_on ? ' hidden' : ''; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Inactif', 'aod-client-dashboard' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</div>
				<div class="aod-cd-pxcard__body">
					<p class="aod-cd-note"><?php esc_html_e( 'Envoie aussi vos achats à Snapchat depuis le serveur, en plus du pixel du navigateur : vous ne perdez plus les conversions bloquées côté navigateur. La déduplication est automatique. Nécessite l’ID du pixel Snapchat ci-dessus.', 'aod-client-dashboard' ); ?></p>
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Jeton d’accès (secret)', 'aod-client-dashboard' ); ?></span>
						<input type="password" name="snapchat_capi_token" value="" autocomplete="off" placeholder="<?php echo $sc_capi_on ? esc_attr__( '•••••••• enregistré — laisser vide pour le conserver', 'aod-client-dashboard' ) : '...'; ?>">
						<span class="aod-cd-help"><?php esc_html_e( 'Snapchat Ads → Gestionnaire d’événements → Conversions API → générer un jeton d’accès. Laisser vide pour garder le pixel du navigateur seul.', 'aod-client-dashboard' ); ?></span>
					</label>
					<?php if ( $sc_capi_on ) : ?>
						<label class="aod-cd-check" style="display:flex;gap:8px;align-items:center;margin-block:var(--cd-sp-2)">
							<input type="checkbox" name="snapchat_capi_token_clear" value="1">
							<span><?php esc_html_e( 'Effacer le jeton enregistré', 'aod-client-dashboard' ); ?></span>
						</label>
					<?php endif; ?>
					<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:var(--cd-sp-3)">
						<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-capi-test" data-platform="snapchat"><?php esc_html_e( 'Tester la connexion', 'aod-client-dashboard' ); ?></button>
						<span class="aod-cd-capi-test-msg" aria-live="polite"></span>
					</div>
					<p class="aod-cd-help"><?php esc_html_e( 'Le test utilise le jeton enregistré : enregistrez d’abord si vous venez de le coller.', 'aod-client-dashboard' ); ?></p>
				</div>
			</div>

			<div class="aod-cd-pxcard" style="margin-top:var(--cd-sp-4)">
				<div class="aod-cd-pxcard__head">
					<span class="aod-cd-pxcard__ic t-slate" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'conversion', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-pxcard__name"><?php esc_html_e( 'Devise de reporting', 'aod-client-dashboard' ); ?></span>
				</div>
				<div class="aod-cd-pxcard__body">
					<p class="aod-cd-note"><?php esc_html_e( 'Meta refuse le dinar algérien : la valeur de vos achats n’est pas comptée dans vos publicités. Déclarez une devise reconnue et son taux pour rendre cette valeur mesurable. Laissez vide pour ne rien changer.', 'aod-client-dashboard' ); ?></p>
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Devise (code à 3 lettres)', 'aod-client-dashboard' ); ?></span>
						<input type="text" name="report_currency" value="<?php echo esc_attr( $s['report_currency'] ); ?>" placeholder="USD" maxlength="3" style="text-transform:uppercase">
						<span class="aod-cd-help"><?php esc_html_e( 'Ex. USD, EUR — une devise que les régies reconnaissent.', 'aod-client-dashboard' ); ?></span>
					</label>
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Taux : 1 DZD = ?', 'aod-client-dashboard' ); ?></span>
						<input type="number" step="0.0001" min="0" name="report_rate" value="<?php echo esc_attr( $s['report_rate'] ); ?>" placeholder="0.0075">
						<span class="aod-cd-help"><?php esc_html_e( 'Combien vaut 1 dinar dans la devise choisie (ex. 0,0075 pour le dollar).', 'aod-client-dashboard' ); ?></span>
					</label>
				</div>
			</div>

			<div class="aod-cd-form-foot aod-cd-savebar">
				<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Enregistrer les pixels', 'aod-client-dashboard' ); ?></button>
				<span class="aod-cd-form-msg"></span>
			</div>
		</form>
		<?php
	}

	/** AJAX : enregistre les pixels (option aod_cod_pixels). */
	public function ajax_save_pixels() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$existing = $this->pixels_settings();
		$settings = array(
			'meta'         => isset( $_POST['meta'] ) ? self::clean_pixel_id( wp_unslash( $_POST['meta'] ) ) : '',
			'tiktok'       => isset( $_POST['tiktok'] ) ? self::clean_pixel_id( wp_unslash( $_POST['tiktok'] ) ) : '',
			'snapchat'     => isset( $_POST['snapchat'] ) ? self::clean_pixel_id( wp_unslash( $_POST['snapchat'] ) ) : '',
			'google_ads'   => isset( $_POST['google_ads'] ) ? self::clean_pixel_id( wp_unslash( $_POST['google_ads'] ) ) : '',
			'google_label' => isset( $_POST['google_label'] ) ? self::clean_pixel_id( wp_unslash( $_POST['google_label'] ) ) : '',
			'meta_domain_verification' => isset( $_POST['meta_domain_verification'] ) ? self::clean_domain_verification( wp_unslash( $_POST['meta_domain_verification'] ) ) : '',
			// Jeton CAPI (secret) : « vide = inchangé » (jamais ré-affiché en clair),
			// case « effacer » = suppression explicite. Nonce vérifié ci-dessus.
			'meta_capi_token'      => self::capi_token_from_post( $existing['meta_capi_token'] ),
			'meta_test_event_code' => isset( $_POST['meta_test_event_code'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_test_event_code'] ) ) : '',
			'tiktok_capi_token'    => self::capi_token_from_post( $existing['tiktok_capi_token'], 'tiktok_capi_token' ),
			'tiktok_test_event_code' => isset( $_POST['tiktok_test_event_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tiktok_test_event_code'] ) ) : '',
			'snapchat_capi_token'  => self::capi_token_from_post( $existing['snapchat_capi_token'], 'snapchat_capi_token' ),
			'report_currency' => isset( $_POST['report_currency'] ) ? self::clean_currency( wp_unslash( $_POST['report_currency'] ) ) : '',
			'report_rate'     => isset( $_POST['report_rate'] ) ? (string) max( 0, (float) wp_unslash( $_POST['report_rate'] ) ) : '',
		);
		update_option( 'aod_cod_pixels', $settings );
		wp_send_json_success( array( 'message' => __( 'Pixels enregistrés.', 'aod-client-dashboard' ) ) );
	}

	/**
	 * AJAX : teste la connexion à la Conversions API de Meta avec le jeton
	 * ENREGISTRÉ (délègue au plugin COD, propriétaire de la logique CAPI).
	 * Ne renvoie jamais le jeton — seulement un verdict lisible par le gérant.
	 */
	public function ajax_test_capi() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_COD_Pixels' ) || ! method_exists( 'AOD_COD_Pixels', 'instance' ) ) {
			wp_send_json_error( array( 'message' => __( 'Le module de pixels n’est pas actif.', 'aod-client-dashboard' ) ) );
		}

		// Plateforme whitelistée → méthode de test dédiée (Phase 13.3). Défaut = meta
		// (rétro-compatible avec le bouton Meta de la 10.2).
		$platform = isset( $_POST['platform'] ) ? sanitize_key( wp_unslash( $_POST['platform'] ) ) : 'meta';
		$methods  = array(
			'meta'     => 'test_capi_connection',
			'tiktok'   => 'test_tiktok_connection',
			'snapchat' => 'test_snapchat_connection',
		);
		if ( ! isset( $methods[ $platform ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Plateforme inconnue.', 'aod-client-dashboard' ) ) );
		}

		$pixels = AOD_COD_Pixels::instance();
		if ( ! method_exists( $pixels, $methods[ $platform ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Le module de pixels n’est pas actif.', 'aod-client-dashboard' ) ) );
		}
		$res = $pixels->{ $methods[ $platform ] }();
		if ( ! empty( $res['ok'] ) ) {
			wp_send_json_success( array( 'message' => $res['message'] ) );
		}
		wp_send_json_error( array( 'message' => isset( $res['message'] ) ? $res['message'] : __( 'Échec du test.', 'aod-client-dashboard' ) ) );
	}
}
