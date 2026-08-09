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

	/**
	 * Ouvre un ÉTAGE de la page : un titre, une phrase, puis des cartes.
	 *
	 * 🧭 La page décrit un CHEMIN DE SIGNAL, pas une liste de marques. Une
	 * conversion part du navigateur de l'acheteur (le pixel) et, en second
	 * recours, du serveur (l'API de conversion). Sans ces deux intitulés, Meta
	 * apparaissait deux fois, TikTok deux fois, Snapchat deux fois — même icône,
	 * même liseré, même pastille — et rien ne disait laquelle était « la carte
	 * Meta ». Nommer l'étage rend le doublon lisible au lieu de le supprimer :
	 * ce sont bien deux réglages distincts de la même régie.
	 *
	 * `<h3>` et non `<h2>` : `page_head()` occupe déjà le rang 2 de la page.
	 *
	 * @param string $ic  Clé d'icône (catalogue fermé AOD_CD_Icon).
	 * @param string $t   Titre de l'étage.
	 * @param string $sub Phrase d'introduction (peut être vide).
	 * @return string
	 */
	protected static function stage_open( $ic, $t, $sub = '' ) {
		$out  = '<section class="aod-cd-pxstage">';
		$out .= '<div class="aod-cd-pxstage__head">';
		$out .= '<span class="aod-cd-pxstage__ic" aria-hidden="true">' . AOD_CD_Icon::svg( $ic, array( 'size' => 18 ) ) . '</span>';
		$out .= '<div class="aod-cd-pxstage__txt">';
		$out .= '<h3 class="aod-cd-pxstage__t">' . esc_html( $t ) . '</h3>';
		if ( '' !== $sub ) {
			$out .= '<p class="aod-cd-pxstage__s">' . esc_html( $sub ) . '</p>';
		}
		$out .= '</div></div>';
		return $out;
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

		// Les trois régies qui savent AUSSI recevoir depuis le serveur. Une seule
		// boucle, et pas trois blocs jumeaux : ces cartes ne diffèrent que par le
		// nom de la clé et l'existence d'un code d'événement test. Écrites trois
		// fois, elles avaient déjà commencé à diverger — Snapchat n'a pas de bloc
		// « Effacer » à la même place que les deux autres si on l'oublie une fois.
		$capis = array(
			array(
				'key'        => 'meta_capi_token',
				'name'       => __( 'Meta', 'aod-client-dashboard' ),
				'ic'         => 'meta',
				'tone'       => 'blue',
				'brand'      => 'meta',
				// 🔤 `ph` = la FORME du jeton, quand on la connaît. Meta préfixe les
				// siens par `EAA` — c'est une vraie aide au collage. TikTok et
				// Snapchat n'ont pas de préfixe documenté ici, et leur marqueur
				// valait `...`, qui ne montre aucune forme : en chasse fixe il se
				// lit « . . . » et, à côté du `••••••••` d'un jeton enregistré, il
				// se confondait avec un état. Vide plutôt qu'inventé.
				'ph'         => 'EAAG...',
				'token_help' => __( 'Gestionnaire d’événements Meta → Paramètres → Conversions API → Générer un jeton d’accès. Laisser vide pour garder le pixel du navigateur seul.', 'aod-client-dashboard' ),
				'test_key'   => 'meta_test_event_code',
				'test_label' => __( 'Code d’événement test (optionnel)', 'aod-client-dashboard' ),
				'test_help'  => __( 'Pour voir vos achats arriver dans le Gestionnaire d’événements → onglet « Test des événements ». À vider une fois validé.', 'aod-client-dashboard' ),
			),
			array(
				'key'        => 'tiktok_capi_token',
				'name'       => __( 'TikTok', 'aod-client-dashboard' ),
				'ic'         => 'tiktok',
				'tone'       => 'slate',
				'brand'      => 'tiktok',
				'ph'         => '',
				'token_help' => __( 'TikTok Ads → Outils → Événements → gérer l’Events API → générer un jeton d’accès. Laisser vide pour garder le pixel du navigateur seul.', 'aod-client-dashboard' ),
				'test_key'   => 'tiktok_test_event_code',
				'test_label' => __( 'Code d’événement test (requis pour tester)', 'aod-client-dashboard' ),
				'test_help'  => __( 'TikTok Ads → Événements → onglet « Test ». Requis pour le bouton « Tester » ci-dessous (l’événement de test n’entre pas dans vos statistiques). À vider une fois validé.', 'aod-client-dashboard' ),
			),
			array(
				'key'        => 'snapchat_capi_token',
				'name'       => __( 'Snapchat', 'aod-client-dashboard' ),
				'ic'         => 'snapchat',
				'tone'       => 'slate',
				'brand'      => 'snapchat',
				'ph'         => '',
				'token_help' => __( 'Snapchat Ads → Gestionnaire d’événements → Conversions API → générer un jeton d’accès. Laisser vide pour garder le pixel du navigateur seul.', 'aod-client-dashboard' ),
				'test_key'   => '', // Snapchat ne propose pas de code d'événement test.
				'test_label' => '',
				'test_help'  => '',
			),
		);

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Pixels & Tracking', 'aod-client-dashboard' ), // Même libellé que la barre latérale.
			__( 'Une carte par régie : collez l’identifiant, laissez vide pour désactiver.', 'aod-client-dashboard' )
		);
		?>
		<form class="aod-cd-form aod-cd-settings-form" data-action="aod_cd_save_pixels">

			<?php
			echo self::stage_open( // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le helper.
				'marketing',
				__( 'Le pixel du navigateur', 'aod-client-dashboard' ),
				__( 'Le PageView est suivi sur tout le site ; l’achat est mesuré sur la page de remerciement (une fois par commande).', 'aod-client-dashboard' )
			);
			?>
			<div class="aod-cd-pxcards">
				<?php foreach ( $platforms as $p ) :
					$on = '' !== trim( (string) $s[ $p['fields'][0][0] ] ); ?>
					<details class="aod-cd-pxcard aod-cd-pxcard--brand aod-cd-pxcard--<?php echo esc_attr( $p['brand'] ); ?><?php echo $on ? ' is-on' : ''; ?>"<?php echo $on ? ' open' : ''; ?>>
						<summary class="aod-cd-pxcard__head">
							<span class="aod-cd-pxcard__ic t-<?php echo esc_attr( $p['tone'] ); ?>" aria-hidden="true"><?php echo AOD_CD_Icon::svg( $p['ic'], array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé. ?></span>
							<span class="aod-cd-pxcard__name"><?php echo esc_html( $p['name'] ); ?></span>
							<span class="aod-cd-px-on"<?php echo $on ? '' : ' hidden'; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Actif', 'aod-client-dashboard' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<span class="aod-cd-px-off"<?php echo $on ? ' hidden' : ''; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Inactif', 'aod-client-dashboard' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</summary>
						<div class="aod-cd-pxcard__body">
							<?php foreach ( $p['fields'] as $i => $f ) :
								list( $key, $label, $ph, $desc ) = $f; ?>
								<label class="aod-cd-field">
									<span class="aod-cd-label"><?php echo esc_html( $label ); ?></span>
									<input type="text" class="aod-cd-code<?php echo 0 === $i ? ' aod-cd-px-id' : ''; ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" placeholder="<?php echo esc_attr( $ph ); ?>">
									<span class="aod-cd-help"><?php echo esc_html( $desc ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
			</section>

			<?php
			echo self::stage_open( // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le helper.
				'locked',
				__( 'L’envoi depuis le serveur', 'aod-client-dashboard' ),
				__( 'En plus du pixel du navigateur, vos achats partent aussi de votre serveur : vous ne perdez plus les conversions bloquées par iOS ou par un bloqueur de publicité. La déduplication est automatique, et chaque envoi exige l’ID du pixel correspondant ci-dessus.', 'aod-client-dashboard' )
			);
			?>
			<div class="aod-cd-pxcards">
				<?php foreach ( $capis as $c ) :
					$capi_on = '' !== trim( (string) $s[ $c['key'] ] ); ?>
					<details class="aod-cd-pxcard aod-cd-pxcard--brand aod-cd-pxcard--<?php echo esc_attr( $c['brand'] ); ?><?php echo $capi_on ? ' is-on' : ''; ?>"<?php echo $capi_on ? ' open' : ''; ?>>
						<summary class="aod-cd-pxcard__head">
							<span class="aod-cd-pxcard__ic t-<?php echo esc_attr( $c['tone'] ); ?>" aria-hidden="true"><?php echo AOD_CD_Icon::svg( $c['ic'], array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé. ?></span>
							<span class="aod-cd-pxcard__name"><?php echo esc_html( $c['name'] ); ?></span>
							<span class="aod-cd-px-on"<?php echo $capi_on ? '' : ' hidden'; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Actif', 'aod-client-dashboard' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<span class="aod-cd-px-off"<?php echo $capi_on ? ' hidden' : ''; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Inactif', 'aod-client-dashboard' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</summary>
						<div class="aod-cd-pxcard__body">
							<label class="aod-cd-field aod-cd-field--secret">
								<span class="aod-cd-label"><?php echo AOD_CD_Icon::svg( 'locked', array( 'size' => 13 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé. ?><?php esc_html_e( 'Jeton d’accès (secret)', 'aod-client-dashboard' ); ?></span>
								<?php
								/* 🔤 LE MARQUEUR DE SUBSTITUTION MONTRE LA FORME ATTENDUE, IL N'INSTRUIT PAS.
								   Il portait « •••••••• enregistré — laisser vide pour le conserver » :
								   une phrase entière, donc traduite, donc EN ARABE sur un site arabe —
								   dans un champ que `.aod-cd-code` force en LTR pour que les jetons ne
								   se retournent pas. L'instruction descend d'un cran, à sa place, dans
								   l'aide ; le marqueur ne garde que les points, qui n'ont pas de sens
								   d'écriture. */
								?>
								<input type="password" class="aod-cd-code" name="<?php echo esc_attr( $c['key'] ); ?>" value="" autocomplete="off" placeholder="<?php echo $capi_on ? '••••••••' : esc_attr( $c['ph'] ); ?>">
								<span class="aod-cd-help">
									<?php if ( $capi_on ) : ?>
										<strong><?php esc_html_e( 'Un jeton est enregistré. Laissez le champ vide pour le conserver.', 'aod-client-dashboard' ); ?></strong>
									<?php endif; ?>
									<?php echo esc_html( $c['token_help'] ); ?>
								</span>
							</label>
							<?php if ( $capi_on ) : ?>
								<label class="aod-cd-check aod-cd-px-clear">
									<input type="checkbox" name="<?php echo esc_attr( $c['key'] ); ?>_clear" value="1">
									<span><?php esc_html_e( 'Effacer le jeton enregistré', 'aod-client-dashboard' ); ?></span>
								</label>
							<?php endif; ?>
							<?php if ( '' !== $c['test_key'] ) : ?>
								<label class="aod-cd-field">
									<span class="aod-cd-label"><?php echo esc_html( $c['test_label'] ); ?></span>
									<input type="text" class="aod-cd-code" name="<?php echo esc_attr( $c['test_key'] ); ?>" value="<?php echo esc_attr( $s[ $c['test_key'] ] ); ?>" placeholder="TEST12345">
									<span class="aod-cd-help"><?php echo esc_html( $c['test_help'] ); ?></span>
								</label>
							<?php endif; ?>
							<div class="aod-cd-pxcard__act">
								<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-capi-test" data-platform="<?php echo esc_attr( $c['brand'] ); ?>"><?php esc_html_e( 'Tester la connexion', 'aod-client-dashboard' ); ?></button>
								<span class="aod-cd-capi-test-msg" aria-live="polite"></span>
							</div>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
			<p class="aod-cd-pxstage__foot"><?php esc_html_e( 'Le test utilise le jeton enregistré : enregistrez d’abord si vous venez de le coller.', 'aod-client-dashboard' ); ?></p>
			</section>

			<?php
			/* La devise n'est PAS une cinquième régie. Elle vivait pourtant dans la
			   même grille de marques, avec le même encadré et la même icône ronde :
			   au balayage, elle se lisait comme un canal publicitaire de plus. Elle
			   a désormais son propre étage, sans liseré de marque et sans pastille
			   actif/inactif — trois signaux qui ne veulent rien dire pour elle. */
			echo self::stage_open( // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le helper.
				'money',
				__( 'Devise de reporting', 'aod-client-dashboard' ),
				__( 'Meta refuse le dinar algérien : la valeur de vos achats n’est pas comptée dans vos publicités. Déclarez une devise reconnue et son taux pour rendre cette valeur mesurable. Laissez vide pour ne rien changer.', 'aod-client-dashboard' )
			);
			?>
			<div class="aod-cd-pxcard aod-cd-pxcard--plain">
				<div class="aod-cd-pxcard__body aod-cd-pxcard__body--2">
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Devise (code à 3 lettres)', 'aod-client-dashboard' ); ?></span>
						<input type="text" class="aod-cd-code aod-cd-code--up" name="report_currency" value="<?php echo esc_attr( $s['report_currency'] ); ?>" placeholder="USD" maxlength="3">
						<span class="aod-cd-help"><?php esc_html_e( 'Ex. USD, EUR — une devise que les régies reconnaissent.', 'aod-client-dashboard' ); ?></span>
					</label>
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Taux : 1 DZD = ?', 'aod-client-dashboard' ); ?></span>
						<input type="number" class="aod-cd-code" step="0.0001" min="0" name="report_rate" value="<?php echo esc_attr( $s['report_rate'] ); ?>" placeholder="0.0075">
						<span class="aod-cd-help"><?php esc_html_e( 'Combien vaut 1 dinar dans la devise choisie (ex. 0,0075 pour le dollar).', 'aod-client-dashboard' ); ?></span>
					</label>
				</div>
			</div>
			</section>

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
