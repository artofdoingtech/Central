<?php
/**
 * Pixels de tracking publicitaire : Meta (Facebook), TikTok, Snapchat, Google Ads.
 *
 * - PageView injecté dans le <head> sur tout le site.
 * - Événement d'achat/conversion déclenché sur la page de remerciement
 *   WooCommerce (woocommerce_thankyou), une seule fois par commande.
 *
 * Comme le formulaire COD crée la commande puis redirige vers la page de
 * remerciement, c'est là que la conversion réelle est mesurée.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Pixels {

	/** @var AOD_COD_Pixels|null */
	protected static $instance = null;

	const OPTION = 'aod_cod_pixels';

	/**
	 * Version de l'API Graph de Meta pour la Conversions API (CAPI).
	 * Constante = facile à bumper quand Meta déprécie une version.
	 */
	const CAPI_API_VERSION = 'v21.0';

	/** Endpoint de l'Events API TikTok (v1.3). Constante = facile à bumper. */
	const TIKTOK_API_URL = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

	/**
	 * Base de la Conversions API Snapchat (v3). L'ID du pixel se place DANS l'URL
	 * (…/v3/<pixel>/events), contrairement à TikTok/Meta. Constante = facile à bumper.
	 */
	const SNAPCHAT_API_BASE = 'https://tr.snapchat.com/v3/';

	/**
	 * Hook de l'envoi différé des conversions serveur (Phase 14.1).
	 * Un événement ponctuel par commande, planifié dans la requête acheteur.
	 */
	const DISPATCH_HOOK = 'aod_capi_dispatch';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Pixels de base + PageView (front uniquement).
		add_action( 'wp_head', array( $this, 'head_pixels' ), 5 );

		// Conversion sur la page de remerciement.
		add_action( 'woocommerce_thankyou', array( $this, 'purchase_pixels' ), 10, 1 );

		// Conversions serveur-à-serveur (Meta CAPI, TikTok Events API, Snapchat
		// Conversions API), dédupliquées avec les pixels navigateur par le n° de
		// commande. Branchées sur la création de commande COD (le tunnel
		// « commande reçue » n'existe pas ici) — même hook que la notif WhatsApp,
		// donc l'instance est bien amorcée au bootstrap avant que le hook ne parte.
		//
		// ⚠️ On MET EN FILE, on n'envoie pas (Phase 14.1). Les trois envois étaient
		// synchrones, à 8 s de timeout chacun, AVANT wp_send_json_success : jusqu'à
		// 24 s de sablier pour l'acheteur, sur la configuration même que la Phase 13
		// promeut. Le fail-open protégeait le RÉSULTAT, jamais la latence.
		add_action( 'aod_cod_order_created', array( $this, 'queue_server_conversions' ), 20, 1 );

		// Exécution différée : envoie les trois régies (chacune garde son opt-in et
		// sa garde d'envoi unique).
		add_action( self::DISPATCH_HOOK, array( $this, 'dispatch_server_conversions' ), 10, 1 );

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
			'meta'                     => '',
			'tiktok'                   => '',
			'snapchat'                 => '',
			'google_ads'               => '',
			'google_label'             => '',
			'meta_domain_verification' => '',
			// Conversions API (CAPI) Meta. Le jeton est un SECRET : jamais
			// ré-affiché en clair, jamais loggé. Vide = CAPI désactivée (opt-in),
			// seul le pixel navigateur reste actif. test_event_code = débogage
			// (Gestionnaire d'événements → onglet Test), optionnel.
			'meta_capi_token'          => '',
			'meta_test_event_code'     => '',
			// Events API TikTok (Phase 13.1) : jeton d'accès SECRET (même contrat
			// que le jeton CAPI Meta). Vide = TikTok reste au pixel navigateur seul.
			'tiktok_capi_token'        => '',
			// Conversions API Snapchat (Phase 13.2) : jeton d'accès SECRET (même
			// contrat). Vide = Snapchat reste au pixel navigateur seul.
			'snapchat_capi_token'      => '',
			// Code d'événement test TikTok (Phase 13.3) : NON secret (comme
			// meta_test_event_code). Renseigné = le bouton « Tester » envoie un
			// événement dans l'onglet Test (hors production). Vide = pas de test possible.
			'tiktok_test_event_code'   => '',
			// Devise de reporting : Meta (et d'autres régies) refusent le dinar
			// algérien comme devise → la VALEUR de l'achat est rejetée. Vides =
			// comportement historique (on envoie DZD tel quel). Cf. to_reporting().
			'report_currency'          => '',
			'report_rate'              => '',
		) );
	}

	/**
	 * Nettoie un code de devise ISO 4217 (3 lettres, ex. USD, EUR).
	 *
	 * @param string $v
	 * @return string
	 */
	protected static function clean_currency( $v ) {
		$v = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $v ) );
		return substr( $v, 0, 3 );
	}

	/**
	 * Applique la devise de reporting configurée à un couple (valeur, devise).
	 *
	 * Pourquoi : Meta rejette la devise DZD (« Parameter 'currency' is invalid »),
	 * donc la VALEUR de chaque achat est ignorée — un gérant qui pilote au ROAS
	 * pilote sur du vide. En déclarant aux régies une devise reconnue (USD/EUR…)
	 * et en convertissant la valeur au taux saisi, la valeur redevient mesurable.
	 *
	 * Générique et NON destructif : sans réglage (devise vide OU taux ≤ 0), il
	 * rend le couple inchangé — le défaut reste « DZD tel quel », rien ne change
	 * pour un clone qui n'a rien configuré.
	 *
	 * @param float  $value    Montant dans la devise de la commande.
	 * @param string $currency Devise de la commande (typiquement DZD).
	 * @param array  $settings Réglages pixels (report_currency, report_rate).
	 * @return array [ float $value, string $currency ]
	 */
	public static function to_reporting( $value, $currency, $settings ) {
		$rc   = isset( $settings['report_currency'] ) ? self::clean_currency( $settings['report_currency'] ) : '';
		$rate = isset( $settings['report_rate'] ) ? (float) $settings['report_rate'] : 0.0;
		if ( '' === $rc || $rate <= 0 ) {
			return array( (float) $value, (string) $currency );
		}
		return array( round( (float) $value * $rate, 2 ), $rc );
	}

	/**
	 * Nettoie un identifiant de pixel (caractères sûrs uniquement).
	 *
	 * @param string $v
	 * @return string
	 */
	protected static function clean_id( $v ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $v );
	}

	/**
	 * Extrait le code de vérification de domaine Meta.
	 *
	 * Accepte aussi bien le code brut que la balise complète collée telle quelle :
	 * <meta name="facebook-domain-verification" content="XXXX" />. Ne conserve
	 * que le contenu de l’attribut content, nettoyé.
	 *
	 * @param string $v
	 * @return string
	 */
	protected static function clean_domain_verification( $v ) {
		$v = (string) $v;
		if ( preg_match( '/content\s*=\s*[\'"]([^\'"]+)[\'"]/i', $v, $m ) ) {
			$v = $m[1];
		}
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', trim( $v ) );
	}

	/**
	 * Résout le jeton CAPI à enregistrer depuis le POST, sans jamais exiger son
	 * ré-affichage en clair :
	 * - case « effacer » cochée → chaîne vide ;
	 * - nouveau jeton saisi (non vide) → ce jeton, nettoyé ;
	 * - champ laissé vide → on CONSERVE le jeton existant.
	 * Une seule règle, partagée par la page admin et l'éditeur `/gestion`, et par
	 * chaque plateforme (Meta, TikTok…) via `$field`. Le nonce est vérifié par
	 * l'appelant (check_admin_referer / check_ajax_referer).
	 *
	 * 🔒 `private` À DESSEIN (Phase 24.1, dette D15). Cette méthode lit `$_POST`
	 * sans le recevoir en argument : sa sûreté dépend entièrement de son appelant.
	 * Tant qu'elle était `public static`, ce contrat n'était qu'une phrase de
	 * docblock, et n'importe quel code du site pouvait l'appeler sans nonce.
	 * `private` le rend STRUCTUREL : hors de cette classe, l'appel ne compile plus.
	 * Le seul appelant légitime est `maybe_save()`, qui nonce en premier.
	 * → Ne pas rouvrir la visibilité. `tests/unit/test-nonce-contract.php` le garde.
	 *
	 * @param string $existing Jeton déjà enregistré.
	 * @param string $field    Nom du champ POST (défaut `meta_capi_token` ; la case
	 *                         « effacer » est `{$field}_clear`).
	 * @return string
	 */
	private static function capi_token_from_post( $existing, $field = 'meta_capi_token' ) {
		if ( ! empty( $_POST[ $field . '_clear' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}
		$posted = isset( $_POST[ $field ] ) ? trim( (string) wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== $posted ) {
			return sanitize_text_field( $posted );
		}
		return (string) $existing;
	}

	/* --------------------------------------------------------------------- */
	/* Sortie front                                                          */
	/* --------------------------------------------------------------------- */

	/**
	 * Pixels de base + PageView dans le <head>.
	 */
	public function head_pixels() {
		if ( is_admin() ) {
			return;
		}
		$s = $this->settings();

		// --- Vérification de domaine Meta ---
		// Balise statique dans le <head> (jamais injectée par JS, sinon Meta ne la
		// trouve pas). Présente sur toutes les pages, donc sur l’accueil exigé.
		if ( '' !== $s['meta_domain_verification'] ) {
			printf(
				"<meta name=\"facebook-domain-verification\" content=\"%s\" />\n",
				esc_attr( $s['meta_domain_verification'] )
			);
		}

		// --- Meta (Facebook) Pixel ---
		if ( '' !== $s['meta'] ) {
			$id = self::clean_id( $s['meta'] );
			?>
<!-- Meta Pixel (AOD) -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?php echo wp_json_encode( $id ); ?>);
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo esc_attr( $id ); ?>&ev=PageView&noscript=1"/></noscript>
<!-- /Meta Pixel -->
			<?php
		}

		// --- TikTok Pixel ---
		if ( '' !== $s['tiktok'] ) {
			$id = self::clean_id( $s['tiktok'] );
			?>
<!-- TikTok Pixel (AOD) -->
<script>
!function (w, d, t) {w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
ttq.load(<?php echo wp_json_encode( $id ); ?>);
ttq.page();
}(window, document, 'ttq');
</script>
<!-- /TikTok Pixel -->
			<?php
		}

		// --- Snapchat Pixel ---
		if ( '' !== $s['snapchat'] ) {
			$id = self::clean_id( $s['snapchat'] );
			?>
<!-- Snapchat Pixel (AOD) -->
<script>
(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?
a.handleRequest.apply(a,arguments):a.queue.push(arguments)};a.queue=[];var s='script';
var r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];
u.parentNode.insertBefore(r,u);})(window,document,'https://sc-static.net/scevent.min.js');
snaptr('init', <?php echo wp_json_encode( $id ); ?>);
snaptr('track', 'PAGE_VIEW');
</script>
<!-- /Snapchat Pixel -->
			<?php
		}

		// --- Google Ads (gtag) ---
		if ( '' !== $s['google_ads'] ) {
			$id = self::clean_id( $s['google_ads'] );
			?>
<!-- Google Ads (AOD) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?php echo wp_json_encode( $id ); ?>);
</script>
<!-- /Google Ads -->
			<?php
		}
	}

	/**
	 * Événement d'achat/conversion sur la page de remerciement.
	 *
	 * @param int $order_id
	 */
	public function purchase_pixels( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Anti double comptage : une seule fois par commande.
		if ( $order->get_meta( '_aod_pixels_fired' ) ) {
			return;
		}

		$s = $this->settings();
		if ( '' === $s['meta'] && '' === $s['tiktok'] && '' === $s['snapchat'] && '' === $s['google_ads'] ) {
			return;
		}

		$value    = (float) $order->get_total();
		$currency = $order->get_currency();
		// Bascule vers la devise de reporting si le clone en a déclaré une (sinon
		// couple inchangé). Une seule source de vérité, partagée avec le payload CAPI.
		list( $value, $currency ) = self::to_reporting( $value, $currency, $s );
		$tx       = (string) $order->get_order_number();
		// Identifiant d'événement stable (= n° de commande) pour la déduplication
		// Meta « pixel ↔ Conversions API » : même event_name + même event_id =
		// un seul achat comptabilisé. Cf. fbq(..., { eventID }).
		$event_id = $tx;

		$content_ids = array();
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			if ( $pid ) {
				$content_ids[] = (string) $pid;
			}
		}

		// Marque tout de suite pour éviter un re-déclenchement (refresh).
		$order->update_meta_data( '_aod_pixels_fired', 1 );
		$order->save();

		echo "\n<!-- AOD COD : conversions -->\n<script>\n";

		if ( '' !== $s['meta'] ) {
			printf(
				"if(window.fbq){fbq('track','Purchase',{value:%s,currency:%s,content_ids:%s,content_type:'product'},{eventID:%s});}\n",
				wp_json_encode( $value ),
				wp_json_encode( $currency ),
				wp_json_encode( $content_ids ),
				wp_json_encode( $event_id )
			);
		}

		// ⚠️ event_id / client_dedup_id sont OBLIGATOIRES ici depuis que les envois
		// serveur TikTok (13.1) et Snapchat (13.2) existent : sans eux, ce rendu et
		// l'envoi serveur ne partagent aucune clé et chaque achat compte DEUX fois.
		// Le chemin AJAX (aod-cod-form.js) les portait déjà ; ce chemin-ci, non.
		if ( '' !== $s['tiktok'] ) {
			printf(
				"if(window.ttq){ttq.track('CompletePayment',{value:%s,currency:%s,content_type:'product',content_id:%s},{event_id:%s});}\n",
				wp_json_encode( $value ),
				wp_json_encode( $currency ),
				wp_json_encode( $content_ids ? $content_ids[0] : '' ),
				wp_json_encode( $event_id )
			);
		}

		if ( '' !== $s['snapchat'] ) {
			// Snapchat n'a pas de 3ᵉ argument : l'identifiant de déduplication vit
			// DANS les propriétés (transaction_id ne sert PAS à dédupliquer).
			printf(
				"if(window.snaptr){snaptr('track','PURCHASE',{price:%s,currency:%s,transaction_id:%s,client_dedup_id:%s});}\n",
				wp_json_encode( $value ),
				wp_json_encode( $currency ),
				wp_json_encode( $tx ),
				wp_json_encode( $event_id )
			);
		}

		if ( '' !== $s['google_ads'] ) {
			$send_to = self::clean_id( $s['google_ads'] );
			if ( '' !== $s['google_label'] ) {
				$send_to .= '/' . self::clean_id( $s['google_label'] );
			}
			printf(
				"if(window.gtag){gtag('event','conversion',{send_to:%s,value:%s,currency:%s,transaction_id:%s});}\n",
				wp_json_encode( $send_to ),
				wp_json_encode( $value ),
				wp_json_encode( $currency ),
				wp_json_encode( $tx )
			);
		}

		echo "</script>\n<!-- /AOD COD : conversions -->\n";
	}

	/**
	 * Charge utile de conversion (achat) prête à être déclenchée côté client.
	 *
	 * Le formulaire COD ne redirige PAS vers la page « commande reçue » de
	 * WooCommerce (tunnel checkout exclu de ce template) : le hook
	 * woocommerce_thankyou ne se déclenche donc jamais et purchase_pixels() ne
	 * mesurerait aucun achat. On expose ici les mêmes événements pour qu'ils
	 * soient émis en JS juste après la création de la commande (réponse AJAX du
	 * formulaire).
	 *
	 * Marque la commande comme déjà comptée — anti double comptage partagé avec
	 * purchase_pixels(). Retourne null si déjà comptée ou si aucun pixel n'est
	 * configuré.
	 *
	 * @param WC_Order $order
	 * @return array|null
	 */
	public function purchase_payload( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return null;
		}

		// Anti double comptage : une seule fois par commande.
		if ( $order->get_meta( '_aod_pixels_fired' ) ) {
			return null;
		}

		$s = $this->settings();
		if ( '' === $s['meta'] && '' === $s['tiktok'] && '' === $s['snapchat'] && '' === $s['google_ads'] ) {
			return null;
		}

		$content_ids = array();
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			if ( $pid ) {
				$content_ids[] = (string) $pid;
			}
		}

		// Marque tout de suite pour éviter un re-déclenchement (refresh, ou hook
		// woocommerce_thankyou s'il venait à s'exécuter).
		$order->update_meta_data( '_aod_pixels_fired', 1 );
		$order->save();

		// Même bascule de devise que la sortie JS (une seule source de vérité).
		list( $value, $currency ) = self::to_reporting( (float) $order->get_total(), $order->get_currency(), $s );

		$payload = array(
			'value'          => $value,
			'currency'       => $currency,
			'transaction_id' => (string) $order->get_order_number(),
			// Déduplication Meta pixel ↔ Conversions API (même event_id côté serveur).
			'event_id'       => (string) $order->get_order_number(),
			'content_ids'    => array_values( $content_ids ),
			'meta'           => ( '' !== $s['meta'] ),
			'tiktok'         => ( '' !== $s['tiktok'] ),
			'snapchat'       => ( '' !== $s['snapchat'] ),
		);

		if ( '' !== $s['google_ads'] ) {
			$send_to = self::clean_id( $s['google_ads'] );
			if ( '' !== $s['google_label'] ) {
				$send_to .= '/' . self::clean_id( $s['google_label'] );
			}
			$payload['google_send_to'] = $send_to;
		}

		return $payload;
	}

	/* --------------------------------------------------------------------- */
	/* Conversions API (CAPI) Meta — envoi serveur-à-serveur                 */
	/* --------------------------------------------------------------------- */

	/**
	 * Hache une donnée personnelle (PII) en SHA-256, comme l'exige Meta :
	 * minuscules + espaces de bord retirés, puis sha256. Chaîne vide → ''.
	 *
	 * @param string $value
	 * @return string
	 */
	protected static function hash_pii( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return '' === $value ? '' : hash( 'sha256', $value );
	}

	/**
	 * Enveloppe une PII hachée dans le tableau attendu par le champ user_data
	 * de Meta. Chaîne vide → tableau vide (retiré ensuite par array_filter :
	 * on n'envoie jamais un hash de chaîne vide, qui « matcherait » à tort).
	 *
	 * @param string $value
	 * @return array
	 */
	protected static function hashed_list( $value ) {
		$h = self::hash_pii( $value );
		return '' === $h ? array() : array( $h );
	}

	/**
	 * Normalise un téléphone algérien pour le hachage. Chiffres seuls, indicatif
	 * pays, sans 0. « 0551 23 45 67 » → « 213551234567 ».
	 * (Même conversion 0→213 que le lien WhatsApp des prospects.)
	 *
	 * @param string $phone
	 * @param bool   $e164 Préfixer d'un « + » (format E.164). Meta veut SANS `+`
	 *                     (défaut) ; TikTok veut l'E.164 AVEC `+` (13.1). Le défaut
	 *                     `false` préserve le comportement Meta à l'octet.
	 * @return string
	 */
	protected static function normalize_phone( $phone, $e164 = false ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( '' === $digits ) {
			return '';
		}
		if ( '0' === substr( $digits, 0, 1 ) ) {
			$digits = '213' . substr( $digits, 1 );
		}
		return $e164 ? '+' . $digits : $digits;
	}

	/**
	 * IDs de produits d'une commande (chaînes), pour `content_ids` / `contents`.
	 * Mutualisé entre les envois serveur (Meta, TikTok…).
	 *
	 * @param WC_Order $order
	 * @return string[]
	 */
	protected static function order_content_ids( $order ) {
		$ids = array();
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			if ( $pid ) {
				$ids[] = (string) $pid;
			}
		}
		return $ids;
	}

	/**
	 * Lignes d'une commande AVEC leur quantité, pour les régies qui déclarent des
	 * unités (TikTok `contents[].quantity`, Snapchat `num_items`).
	 *
	 * ⚠️ Distinct d'`order_content_ids()`, qui ne rend que des IDs et reste le
	 * contrat de Meta (`content_ids` n'attend pas de quantité). Les offres par lot
	 * et les packs de ce template créent UNE ligne de quantité N : compter les
	 * lignes au lieu de sommer les quantités déclarait « 1 article » pour une
	 * commande de 3, pendant que `value` portait bien le total des 3.
	 *
	 * @param WC_Order $order
	 * @return array[] Liste de `[ 'id' => string, 'qty' => int ]`.
	 */
	protected static function order_contents( $order ) {
		$rows = array();
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			if ( ! $pid ) {
				continue;
			}
			$qty    = (int) $item->get_quantity();
			$rows[] = array(
				'id'  => (string) $pid,
				'qty' => $qty > 0 ? $qty : 1,
			);
		}
		return $rows;
	}

	/**
	 * Somme des unités d'une commande (et non le nombre de lignes).
	 *
	 * @param WC_Order $order
	 * @return int
	 */
	protected static function order_num_items( $order ) {
		$n = 0;
		foreach ( self::order_contents( $order ) as $row ) {
			$n += (int) $row['qty'];
		}
		return $n;
	}

	/**
	 * Cookies d'appariement posés par les pixels NAVIGATEUR des trois régies.
	 * Ce sont les identifiants les plus forts dont nous disposons : ils relient
	 * l'achat à la session publicitaire qui l'a produit.
	 */
	const MATCH_COOKIES = array( '_fbp', '_fbc', '_ttp', 'ttclid', '_scid', '_scclick' );

	/** Méta de commande où ces cookies sont rangés (Phase 14.1). */
	const COOKIES_META = '_aod_conv_cookies';

	/**
	 * Range les cookies d'appariement dans la commande.
	 *
	 * 🚨 À appeler DANS LA REQUÊTE ACHETEUR, c'est le seul moment où ils existent.
	 * Les envois serveur étant désormais différés (14.1), ils s'exécutent en cron
	 * — un contexte SANS aucun cookie. Sans cette capture, on aurait troqué de la
	 * latence contre de la qualité d'appariement, et en silence : le socle est
	 * fail-open et ne lit jamais sa réponse.
	 *
	 * (L'IP et l'user-agent, eux, viennent de la commande et survivent au différé.)
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public static function capture_match_cookies( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$found = array();
		foreach ( self::MATCH_COOKIES as $name ) {
			if ( ! empty( $_COOKIE[ $name ] ) ) {
				$found[ $name ] = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
			}
		}
		if ( ! $found ) {
			return; // Aucun cookie : on n'écrit pas une méta vide.
		}
		$order->update_meta_data( self::COOKIES_META, $found );
		$order->save();
	}

	/**
	 * Lit un cookie d'appariement : la méta d'abord (envoi différé), le cookie
	 * vivant ensuite (envoi synchrone, ou commande antérieure à la 14.1).
	 *
	 * Ce repli est ce qui rend la capture NEUTRE : le payload est identique que
	 * l'envoi parte tout de suite ou plus tard.
	 *
	 * @param WC_Order $order
	 * @param string   $name Nom du cookie.
	 * @return string Chaîne vide si absent (l'appelant n'ajoute alors pas la clé).
	 */
	protected static function match_cookie( $order, $name ) {
		$saved = $order->get_meta( self::COOKIES_META );
		if ( is_array( $saved ) && ! empty( $saved[ $name ] ) ) {
			return (string) $saved[ $name ];
		}
		return ! empty( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
	}

	/* --------------------------------------------------------------------- */
	/* Envoi DIFFÉRÉ des conversions serveur (Phase 14.1)                    */
	/* --------------------------------------------------------------------- */

	/**
	 * Régies dont l'envoi serveur est réellement armé (pixel ET jeton saisis).
	 *
	 * Sert à l'opt-in de la mise en file : un clone qui n'a configuré aucune
	 * conversion serveur ne doit pas voir passer une tâche cron fantôme.
	 * Le vrai opt-in reste dans chaque `send_*_purchase()` — ceci n'est qu'un
	 * garde-fou de planification, jamais une seconde source de vérité.
	 *
	 * @return string[] Identifiants de régie (`meta`, `tiktok`, `snapchat`).
	 */
	protected function armed_server_platforms() {
		$s     = $this->settings();
		$armed = array();
		$map   = array(
			'meta'     => array( 'meta', 'meta_capi_token' ),
			'tiktok'   => array( 'tiktok', 'tiktok_capi_token' ),
			'snapchat' => array( 'snapchat', 'snapchat_capi_token' ),
		);
		foreach ( $map as $id => $keys ) {
			$pixel = self::clean_id( isset( $s[ $keys[0] ] ) ? $s[ $keys[0] ] : '' );
			$token = trim( (string) ( isset( $s[ $keys[1] ] ) ? $s[ $keys[1] ] : '' ) );
			if ( '' !== $pixel && '' !== $token ) {
				$armed[] = $id;
			}
		}
		return $armed;
	}

	/**
	 * Met les conversions serveur EN FILE — ne les envoie pas.
	 *
	 * S'exécute dans la requête acheteur, donc :
	 *  1. c'est ici — et seulement ici — qu'on peut capturer les cookies
	 *     d'appariement (cf. capture_match_cookies) ;
	 *  2. rien de bloquant ne doit s'y produire : c'est tout l'objet de l'étape.
	 *
	 * ⚠️ « Mis en file » n'est PAS « envoyé ». On ne pose aucune garde
	 * `_aod_capi_*_sent` ici : elles restent posées par les envois eux-mêmes, au
	 * moment de l'appel réseau. Sinon un cron mort ferait compter comme transmis
	 * un achat qui ne partirait jamais — et il ne serait jamais rattrapé.
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function queue_server_conversions( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// À faire AVANT toute sortie anticipée : même si aucune régie n'est armée
		// aujourd'hui, le gérant peut en configurer une plus tard, et la commande
		// gardera ses identifiants d'appariement.
		self::capture_match_cookies( $order );

		// Opt-in : aucune régie armée ⇒ aucune tâche planifiée.
		if ( ! $this->armed_server_platforms() ) {
			return;
		}

		$order_id = $order->get_id();
		$args     = array( $order_id );

		// Déjà en file (hook rejoué) : ne pas empiler deux tâches identiques.
		if ( wp_next_scheduled( self::DISPATCH_HOOK, $args ) ) {
			return;
		}

		wp_schedule_single_event( time(), self::DISPATCH_HOOK, $args );
	}

	/**
	 * Exécute les conversions serveur mises en file.
	 *
	 * Chaque envoi conserve son opt-in et sa garde d'envoi unique : appeler cette
	 * méthode deux fois n'envoie jamais deux fois. Un envoi qui n'a pas abouti
	 * (cron mort, régie configurée après coup) reste donc rejouable.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public function dispatch_server_conversions( $order_id ) {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof WC_Order ) {
			return; // Commande supprimée entre-temps : rien à envoyer.
		}
		$this->send_capi_purchase( $order );
		$this->send_tiktok_purchase( $order );
		$this->send_snapchat_purchase( $order );
	}

	/**
	 * POST HTTP mutualisé des envois serveur de conversion (CAPI Meta, Events API
	 * TikTok…). Modèle Turnstile : timeout court, **fail-open** — l'appelant avale
	 * le résultat (l'acheteur passe avant le marketing) ; aucun secret loggé.
	 *
	 * Le `Content-Type: application/json` est toujours posé ; `$headers` ajoute
	 * l'auth propre à la plateforme (ex. `Access-Token` pour TikTok — Meta, lui,
	 * met son jeton dans le corps).
	 *
	 * @param string $url
	 * @param array  $headers En-têtes additionnels (auth de la plateforme).
	 * @param array  $body    Corps (encodé JSON ici).
	 * @return array|WP_Error Résultat brut de wp_remote_post.
	 */
	protected static function capi_http_post( $url, $headers, $body ) {
		$resp = wp_remote_post( $url, array(
			'timeout' => 8,
			'headers' => array_merge( array( 'Content-Type' => 'application/json' ), (array) $headers ),
			'body'    => wp_json_encode( $body ),
		) );

		// Journalisation (31.3) — l'appelant AVALE le résultat par conception
		// (fail-open : l'acheteur passe avant le marketing). Sans trace ici, un
		// envoi qui échoue à chaque vente ne se voit donc NULLE PART. C'est très
		// exactement le défaut TikTok de la Phase 13 : « le pixel paraissait actif
		// et zéro achat serveur n'arrivait ».
		//
		// 🔒 `$headers` porte l'auth de TikTok (`Access-Token`) et de Snapchat
		// (`Authorization: Bearer`), `$body` porte celle de Meta (`access_token`),
		// et l'URL de test Meta porte le jeton en query arg. Les trois formes sont
		// couvertes par les trois couches d'`AOD_COD_Log` — c'est pour ce point
		// précis qu'elles existent.
		if ( is_wp_error( $resp ) ) {
			AOD_COD_Log::error( 'capi', sprintf(
				'Conversion serveur : appel réseau impossible vers %s — %s',
				$url,
				$resp->get_error_message()
			) );
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			// Rédiger AVANT de tronquer (cf. la même précaution dans AOD_Carrier).
			$extrait = AOD_COD_Log::redact_string( (string) wp_remote_retrieve_body( $resp ) );
			if ( strlen( $extrait ) > 500 ) {
				$extrait = substr( $extrait, 0, 500 ) . '…';
			}
			AOD_COD_Log::error(
				'capi',
				sprintf( 'Conversion serveur refusée (HTTP %d) par %s', $code, $url ),
				array( 'reponse' => $extrait )
			);
		}

		return $resp;
	}

	/**
	 * Envoie l'événement « Purchase » à la Conversions API de Meta, en
	 * serveur-à-serveur, dédupliqué avec le pixel navigateur par event_id
	 * (= n° de commande — le MÊME des deux côtés, cf. purchase_payload()).
	 *
	 * Trois garanties tenues :
	 * - **Opt-in** : sans pixel Meta ET jeton CAPI, aucun appel réseau.
	 * - **Fail-open** : une erreur/lenteur de Meta ne casse jamais la commande
	 *   (on avale toute WP_Error, aucun secret n'est loggé).
	 * - **PII hachées** : téléphone, nom, ville, wilaya partent en SHA-256 ;
	 *   seuls IP, user-agent et cookies _fbp/_fbc (identifiants d'appariement
	 *   fournis par le pixel) partent en clair, comme Meta l'exige.
	 *
	 * @param WC_Order $order
	 */
	public function send_capi_purchase( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$s     = $this->settings();
		$pixel = self::clean_id( $s['meta'] );
		$token = trim( (string) $s['meta_capi_token'] );

		// Opt-in : le pixel Meta ET le jeton CAPI doivent être configurés.
		if ( '' === $pixel || '' === $token ) {
			return;
		}

		// Envoi unique par commande. Le hook aod_cod_order_created ne part
		// qu'une fois, mais ce filet couvre un rejeu éventuel ; la déduplication
		// Meta par event_id couvre le reste (pixel + serveur = un seul achat).
		if ( $order->get_meta( '_aod_capi_sent' ) ) {
			return;
		}

		// Valeur/devise : même bascule de reporting que le pixel navigateur
		// (Meta refuse le DZD) — une seule source de vérité, to_reporting().
		list( $value, $currency ) = self::to_reporting( (float) $order->get_total(), $order->get_currency(), $s );

		$content_ids = self::order_content_ids( $order );

		// user_data : PII HACHÉES (array_filter retire les champs vides pour ne
		// jamais envoyer un hash de chaîne vide).
		$user_data = array_filter( array(
			'ph'      => self::hashed_list( self::normalize_phone( $order->get_billing_phone() ) ),
			'fn'      => self::hashed_list( $order->get_billing_first_name() ),
			'ln'      => self::hashed_list( $order->get_billing_last_name() ),
			'ct'      => self::hashed_list( $order->get_billing_city() ),
			'st'      => self::hashed_list( (string) $order->get_meta( '_aod_wilaya_name' ) ),
			'country' => self::hashed_list( 'dz' ),
		) );

		// En clair (Meta l'exige ainsi pour ces identifiants d'appariement).
		$ip = $order->get_customer_ip_address();
		if ( $ip ) {
			$user_data['client_ip_address'] = $ip;
		}
		$ua = $order->get_customer_user_agent();
		if ( $ua ) {
			$user_data['client_user_agent'] = $ua;
		}
		// Cookies posés par le pixel navigateur : forte amélioration du matching.
		// Lus par match_cookie() (méta de commande, capturée dans la requête
		// acheteur) — l'envoi étant différé, $_COOKIE seul serait vide ici.
		$fbp = self::match_cookie( $order, '_fbp' );
		if ( '' !== $fbp ) {
			$user_data['fbp'] = $fbp;
		}
		$fbc = self::match_cookie( $order, '_fbc' );
		if ( '' !== $fbc ) {
			$user_data['fbc'] = $fbc;
		}

		$event = array(
			'event_name'       => 'Purchase',
			'event_time'       => time(),
			// Déduplication pixel ↔ CAPI : IDENTIQUE à purchase_payload().
			'event_id'         => (string) $order->get_order_number(),
			'action_source'    => 'website',
			'event_source_url' => home_url( '/' ),
			'user_data'        => $user_data,
			'custom_data'      => array(
				'value'        => $value,
				'currency'     => $currency,
				'content_ids'  => array_values( $content_ids ),
				'content_type' => 'product',
			),
		);

		$body = array(
			'data'         => array( $event ),
			'access_token' => $token,
		);
		// Débogage : rattache l'événement à l'onglet « Test » du Gestionnaire
		// d'événements Meta si un code est renseigné (étape 10.2).
		$test_code = isset( $s['meta_test_event_code'] ) ? trim( (string) $s['meta_test_event_code'] ) : '';
		if ( '' !== $test_code ) {
			$body['test_event_code'] = $test_code;
		}

		// Marque AVANT l'appel : même si le hook se rejouait, pas de second envoi.
		$order->update_meta_data( '_aod_capi_sent', 1 );
		$order->save();

		$url = 'https://graph.facebook.com/' . self::CAPI_API_VERSION . '/' . rawurlencode( $pixel ) . '/events';

		// Meta met son jeton dans le CORPS (access_token) : aucun en-tête d'auth.
		// Socle mutualisé fail-open — on avale le résultat (l'acheteur passe avant
		// le marketing ; le bouton « Tester » de la 10.2 sert au diagnostic).
		self::capi_http_post( $url, array(), $body );
	}

	/**
	 * Envoie l'événement d'achat « CompletePayment » à l'Events API de TikTok, en
	 * serveur-à-serveur, dédupliqué avec le pixel navigateur par `event_id`
	 * (= n° de commande — le MÊME des deux côtés, cf. ttq.track de la 13.1). Même
	 * patron que la CAPI Meta (socle mutualisé), aux différences de plateforme près :
	 * jeton en en-tête `Access-Token`, champs `user` propres (téléphone en **E.164
	 * avec `+`**, `first_name`/`last_name`…), `properties.contents`.
	 *
	 * Opt-in (pixel TikTok + jeton), fail-open, PII hachées SHA-256, envoi unique
	 * par commande (`_aod_capi_tiktok_sent`) : les 4 garanties de la Phase 10.
	 *
	 * @param WC_Order $order
	 */
	public function send_tiktok_purchase( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$s     = $this->settings();
		$pixel = self::clean_id( $s['tiktok'] );
		$token = trim( (string) $s['tiktok_capi_token'] );

		// Opt-in : le pixel TikTok ET le jeton Events API doivent être configurés.
		if ( '' === $pixel || '' === $token ) {
			return;
		}
		// Envoi unique par commande (méta DISTINCTE de celle de Meta : activer les
		// deux plateformes doit envoyer aux deux). La dédup TikTok par event_id
		// couvre un éventuel rejeu.
		if ( $order->get_meta( '_aod_capi_tiktok_sent' ) ) {
			return;
		}

		list( $value, $currency ) = self::to_reporting( (float) $order->get_total(), $order->get_currency(), $s );

		$contents = array();
		foreach ( self::order_contents( $order ) as $row ) {
			$contents[] = array( 'content_id' => $row['id'], 'quantity' => $row['qty'] );
		}

		// user : PII HACHÉES.
		// ⚠️ DEUX écarts avec Meta, à ne pas confondre (ils se ressemblent) :
		//  · le téléphone part en E.164 AVEC « + » (Meta le veut sans) ;
		//  · chaque identifiant est une CHAÎNE. `hashed_list()` enveloppe le hash
		//    dans un tableau — c'est la convention `user_data` de Meta (et de
		//    Snapchat v3, aligné Meta), PAS celle de l'Events API TikTok, dont le
		//    schéma v1.3 type ces champs en chaîne. Envoyer `["<sha256>"]` faisait
		//    refuser l'événement en silence (le socle est fail-open, la réponse
		//    n'est pas lue) : le pixel paraissait actif et zéro achat serveur
		//    n'arrivait chez TikTok.
		// `email` est l'identifiant au poids de correspondance le plus fort chez
		// TikTok — il manquait alors que le formulaire COD le collecte (facultatif)
		// et que Snapchat l'envoie déjà. array_filter retire les champs vides.
		$user = array_filter( array(
			'email'      => self::hash_pii( $order->get_billing_email() ),
			'phone'      => self::hash_pii( self::normalize_phone( $order->get_billing_phone(), true ) ),
			'first_name' => self::hash_pii( $order->get_billing_first_name() ),
			'last_name'  => self::hash_pii( $order->get_billing_last_name() ),
			'city'       => self::hash_pii( $order->get_billing_city() ),
			'state'      => self::hash_pii( (string) $order->get_meta( '_aod_wilaya_name' ) ),
			'country'    => self::hash_pii( 'dz' ),
		) );

		// En clair (identifiants d'appariement TikTok).
		$ip = $order->get_customer_ip_address();
		if ( $ip ) {
			$user['ip'] = $ip;
		}
		$ua = $order->get_customer_user_agent();
		if ( $ua ) {
			$user['user_agent'] = $ua;
		}
		// Cookie/param posés par le pixel navigateur TikTok : matching renforcé.
		// Via match_cookie() (méta capturée dans la requête acheteur) — cf. 14.1.
		$ttp = self::match_cookie( $order, '_ttp' );
		if ( '' !== $ttp ) {
			$user['ttp'] = $ttp;
		}
		$ttclid = self::match_cookie( $order, 'ttclid' );
		if ( '' !== $ttclid ) {
			$user['ttclid'] = $ttclid;
		}

		$event = array(
			'event'      => 'CompletePayment',
			'event_time' => time(),
			// Déduplication pixel ↔ Events API : IDENTIQUE au ttq.track navigateur.
			'event_id'   => (string) $order->get_order_number(),
			'user'       => $user,
			'properties' => array(
				'currency' => $currency,
				'value'    => $value,
				// `content_type` est requis dès que `contents` est présent. Il était
				// absent côté serveur alors que le pixel NAVIGATEUR du même fichier
				// l'envoie (« product ») : les deux côtés se contredisaient, et le
				// catalogue restait non attribué (retargeting par jeu de produits HS).
				'content_type' => 'product',
				'contents'     => $contents,
			),
		);

		$body = array(
			'event_source'    => 'web',
			'event_source_id' => $pixel, // = ID du pixel TikTok.
			'data'            => array( $event ),
		);

		// Marque AVANT l'appel (comme Meta) : pas de second envoi si le hook rejoue.
		$order->update_meta_data( '_aod_capi_tiktok_sent', 1 );
		$order->save();

		// TikTok met le jeton dans l'EN-TÊTE `Access-Token` (≠ Meta, corps). Socle
		// mutualisé fail-open.
		self::capi_http_post( self::TIKTOK_API_URL, array( 'Access-Token' => $token ), $body );
	}

	/**
	 * Envoie l'événement d'achat « PURCHASE » à la Conversions API de Snapchat
	 * (v3), en serveur-à-serveur, dédupliqué avec le pixel navigateur par
	 * `client_dedup_id` (= n° de commande — le MÊME des deux côtés, cf. snaptr de
	 * la 13.2). Même socle mutualisé que Meta/TikTok, aux différences de plateforme
	 * près : Snapchat v3 est **aligné sur la spec Meta** (conteneur `user_data`,
	 * téléphone haché **SANS `+`** comme Meta — PAS l'E.164 `+` de TikTok — champs
	 * `em/ph/fn/ln/ct/st/country`), l'ID pixel est **dans l'URL** et le jeton en
	 * en-tête `Authorization: Bearer` (le jeton ne transite pas dans l'URL).
	 *
	 * Opt-in (pixel Snapchat + jeton), fail-open, PII hachées SHA-256, envoi unique
	 * par commande (`_aod_capi_snap_sent`) : les 4 garanties de la Phase 10.
	 *
	 * @param WC_Order $order
	 */
	public function send_snapchat_purchase( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$s     = $this->settings();
		$pixel = self::clean_id( $s['snapchat'] );
		$token = trim( (string) $s['snapchat_capi_token'] );

		// Opt-in : le pixel Snapchat ET le jeton Conversions API doivent être configurés.
		if ( '' === $pixel || '' === $token ) {
			return;
		}
		// Envoi unique par commande (méta DISTINCTE de Meta/TikTok : activer les trois
		// plateformes doit envoyer aux trois). La dédup Snap par client_dedup_id
		// couvre un éventuel rejeu.
		if ( $order->get_meta( '_aod_capi_snap_sent' ) ) {
			return;
		}

		list( $value, $currency ) = self::to_reporting( (float) $order->get_total(), $order->get_currency(), $s );

		$content_ids = self::order_content_ids( $order );

		// user_data : PII HACHÉES, format Snapchat = Meta (champs em/ph/fn/ln/ct/st/
		// country). ⚠️ Téléphone SANS « + » (normalize_phone par défaut), PAS l'E.164
		// « + » de TikTok. array_filter retire les champs vides (em facultatif…).
		$user = array_filter( array(
			'em'      => self::hashed_list( $order->get_billing_email() ),
			'ph'      => self::hashed_list( self::normalize_phone( $order->get_billing_phone() ) ),
			'fn'      => self::hashed_list( $order->get_billing_first_name() ),
			'ln'      => self::hashed_list( $order->get_billing_last_name() ),
			'ct'      => self::hashed_list( $order->get_billing_city() ),
			'st'      => self::hashed_list( (string) $order->get_meta( '_aod_wilaya_name' ) ),
			'country' => self::hashed_list( 'dz' ),
		) );

		// En clair (identifiants d'appariement Snapchat).
		$ip = $order->get_customer_ip_address();
		if ( $ip ) {
			$user['client_ip_address'] = $ip;
		}
		$ua = $order->get_customer_user_agent();
		if ( $ua ) {
			$user['client_user_agent'] = $ua;
		}
		// Cookies posés par le pixel navigateur Snapchat : matching renforcé.
		// Via match_cookie() (méta capturée dans la requête acheteur) — cf. 14.1.
		$scid = self::match_cookie( $order, '_scid' );
		if ( '' !== $scid ) {
			$user['sc_cookie1'] = $scid;
		}
		$scclick = self::match_cookie( $order, '_scclick' );
		if ( '' !== $scclick ) {
			$user['sc_click_id'] = $scclick;
		}

		$event = array(
			'event_name'    => 'PURCHASE',
			'event_time'    => time(),
			'action_source' => 'web',
			// Déduplication pixel ↔ Conversions API : IDENTIQUE au snaptr navigateur.
			'client_dedup_id' => (string) $order->get_order_number(),
			'user_data'     => $user,
			'custom_data'   => array(
				'currency'    => $currency,
				'value'       => $value,
				'content_ids' => array_values( $content_ids ),
				// Somme des UNITÉS, pas le nombre de lignes : une offre par lot ou
				// un pack tient sur une seule ligne de quantité N. `count()` déclarait
				// « 1 article » pour une commande de 3, alors que `value` portait le
				// total des 3 — panier moyen faux côté régie.
				'num_items'   => self::order_num_items( $order ),
			),
		);

		$body = array( 'data' => array( $event ) );

		// Marque AVANT l'appel (comme Meta/TikTok) : pas de second envoi si le hook rejoue.
		$order->update_meta_data( '_aod_capi_snap_sent', 1 );
		$order->save();

		// Snapchat v3 : ID pixel DANS l'URL, jeton en en-tête `Authorization: Bearer`
		// (pas dans l'URL → jamais loggé). Socle mutualisé fail-open.
		$url = self::SNAPCHAT_API_BASE . rawurlencode( $pixel ) . '/events';
		self::capi_http_post( $url, array( 'Authorization' => 'Bearer ' . $token ), $body );
	}

	/**
	 * Teste la connexion CAPI SANS envoyer d'événement : interroge le nœud du
	 * pixel Meta avec le jeton enregistré (GET). Un couple pixel/jeton valide
	 * renvoie l'ID du pixel ; sinon Meta renvoie une erreur explicite. On ne
	 * renvoie JAMAIS le jeton — seulement un verdict lisible.
	 *
	 * @return array [ 'ok' => bool, 'message' => string ]
	 */
	public function test_capi_connection() {
		$s     = $this->settings();
		$pixel = self::clean_id( $s['meta'] );
		$token = trim( (string) $s['meta_capi_token'] );

		if ( '' === $pixel ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord l’ID du pixel Meta, puis enregistrez.', 'aod-cod-form' ) );
		}
		if ( '' === $token ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord le jeton d’API Conversions, puis enregistrez.', 'aod-cod-form' ) );
		}

		$url  = 'https://graph.facebook.com/' . self::CAPI_API_VERSION . '/' . rawurlencode( $pixel )
			. '?fields=id,name&access_token=' . rawurlencode( $token );
		$resp = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'message' => __( 'Meta est injoignable (réseau). Réessayez.', 'aod-cod-form' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( 200 === $code && is_array( $data ) && ! empty( $data['id'] ) ) {
			$name = ! empty( $data['name'] ) ? $data['name'] : $data['id'];
			return array( 'ok' => true, 'message' => sprintf( /* translators: %s = nom ou ID du pixel. */ __( 'Connexion réussie : pixel « %s » accessible.', 'aod-cod-form' ), $name ) );
		}

		// Message d'erreur renvoyé par Meta (utile au gérant pour corriger) —
		// jamais le jeton.
		$err = ( is_array( $data ) && isset( $data['error']['message'] ) ) ? (string) $data['error']['message'] : '';
		return array( 'ok' => false, 'message' => '' !== $err
			? sprintf( /* translators: %s = message d'erreur renvoyé par Meta. */ __( 'Refus de Meta : %s', 'aod-cod-form' ), $err )
			: __( 'Jeton ou pixel invalide.', 'aod-cod-form' ) );
	}

	/**
	 * Teste la connexion à l'Events API TikTok (Phase 13.3). TikTok n'a PAS
	 * d'endpoint de validation en lecture (≠ Meta) : le seul test propre est
	 * d'envoyer un événement AVEC un `test_event_code` — il atterrit dans l'onglet
	 * « Test » du Gestionnaire d'événements, HORS reporting de production. Sans ce
	 * code, on refuse d'émettre quoi que ce soit (garantie « zéro pollution »).
	 * On ne renvoie JAMAIS le jeton — seulement un verdict lisible.
	 *
	 * @return array [ 'ok' => bool, 'message' => string ]
	 */
	public function test_tiktok_connection() {
		$s     = $this->settings();
		$pixel = self::clean_id( $s['tiktok'] );
		$token = trim( (string) $s['tiktok_capi_token'] );
		$code  = trim( (string) $s['tiktok_test_event_code'] );

		if ( '' === $pixel ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord l’ID du pixel TikTok, puis enregistrez.', 'aod-cod-form' ) );
		}
		if ( '' === $token ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord le jeton de l’Events API TikTok, puis enregistrez.', 'aod-cod-form' ) );
		}
		if ( '' === $code ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez un « code d’événement test » TikTok (puis enregistrez) : le test l’utilise pour ne PAS toucher vos statistiques de production.', 'aod-cod-form' ) );
		}

		// Événement NEUTRE (pas d'achat, aucune PII d'acheteur) : on valide l'auth,
		// pas une conversion. Le test_event_code le route vers l'onglet Test.
		//
		// ⚠️ TikTok exige au moins un identifiant dans `user`. Un événement sans
		// aucun n'est pas refusé pour la bonne raison : il est refusé tout court —
		// un jeton parfaitement valide ressortait « invalide », et aucune
		// configuration correcte ne pouvait laver le verdict. On envoie donc les
		// identifiants EN CLAIR de la requête du gérant lui-même (c'est ainsi que
		// TikTok les attend) — jamais ceux d'un acheteur.
		$user = array();
		$ip   = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address()
			: ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		if ( '' !== $ip ) {
			$user['ip'] = $ip;
		}
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user['user_agent'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		$event = array(
			'event'      => 'ViewContent',
			'event_time' => time(),
		);
		if ( $user ) {
			$event['user'] = $user;
		}

		$body = array(
			'event_source'    => 'web',
			'event_source_id' => $pixel,
			'test_event_code' => $code,
			'data'            => array( $event ),
		);
		$resp = self::capi_http_post( self::TIKTOK_API_URL, array( 'Access-Token' => $token ), $body );

		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'message' => __( 'TikTok est injoignable (réseau). Réessayez.', 'aod-cod-form' ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );

		// L'Events API renvoie { code: 0, message: "OK" } en cas de succès.
		if ( is_array( $data ) && isset( $data['code'] ) && 0 === (int) $data['code'] ) {
			return array( 'ok' => true, 'message' => __( 'Connexion réussie : événement de test accepté par TikTok (onglet « Test des événements »).', 'aod-cod-form' ) );
		}

		$err = ( is_array( $data ) && ! empty( $data['message'] ) ) ? (string) $data['message'] : '';
		return array( 'ok' => false, 'message' => '' !== $err
			? sprintf( /* translators: %s = message d'erreur renvoyé par TikTok. */ __( 'Refus de TikTok : %s', 'aod-cod-form' ), $err )
			: __( 'Jeton ou pixel TikTok invalide.', 'aod-cod-form' ) );
	}

	/**
	 * Teste la connexion à la Conversions API Snapchat (Phase 13.3). Snapchat v3
	 * expose un endpoint de VALIDATION dédié `…/events/validate` qui vérifie sans
	 * écrire en production — le test est donc propre par construction (≠ TikTok).
	 * Jeton en en-tête `Authorization: Bearer` (jamais dans l'URL, jamais renvoyé).
	 *
	 * @return array [ 'ok' => bool, 'message' => string ]
	 */
	public function test_snapchat_connection() {
		$s     = $this->settings();
		$pixel = self::clean_id( $s['snapchat'] );
		$token = trim( (string) $s['snapchat_capi_token'] );

		if ( '' === $pixel ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord l’ID du pixel Snapchat, puis enregistrez.', 'aod-cod-form' ) );
		}
		if ( '' === $token ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord le jeton de la Conversions API Snapchat, puis enregistrez.', 'aod-cod-form' ) );
		}

		// Événement NEUTRE minimal (sans PII) posté à l'endpoint de validation.
		$body = array(
			'data' => array(
				array(
					'event_name'    => 'PAGE_VIEW',
					'event_time'    => time(),
					'action_source' => 'web',
				),
			),
		);
		$url  = self::SNAPCHAT_API_BASE . rawurlencode( $pixel ) . '/events/validate';
		$resp = self::capi_http_post( $url, array( 'Authorization' => 'Bearer ' . $token ), $body );

		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'message' => __( 'Snapchat est injoignable (réseau). Réessayez.', 'aod-cod-form' ) );
		}
		$http = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );

		// ⚠️ Un HTTP 200 ne suffit PAS : `/events/validate` répond 200 dès que la
		// requête est bien formée et met son verdict DANS LE CORPS. Se contenter du
		// code rendait vert un pixel qui n'appartient pas au compte du jeton — le
		// gérant arrêtait d'investiguer pendant qu'aucun achat n'atteignait Snapchat
		// (le chemin d'envoi est fail-open et ne lit jamais sa réponse). C'est
		// exactement le cas que ce bouton existe pour attraper.
		if ( 200 === $http ) {
			$status = is_array( $data ) && isset( $data['status'] ) ? strtoupper( (string) $data['status'] ) : '';
			$reason = '';
			if ( is_array( $data ) ) {
				foreach ( array( 'reason', 'error_message', 'message' ) as $k ) {
					if ( ! empty( $data[ $k ] ) ) {
						$reason = (string) $data[ $k ];
						break;
					}
				}
			}
			// Verdict explicite d'échec, ou compteur d'événements refusés non nul.
			// ⚠️ Comparaison EXACTE, jamais une sous-chaîne : « VALIDATION_ERROR »
			// CONTIENT « VALID » — un test par strpos() prenait donc l'erreur pour
			// un succès (attrapé par la recette).
			$ok_statuses = array( 'VALID', 'VALIDATED', 'SUCCESS', 'OK' );
			$failed      = ( '' !== $status && ! in_array( $status, $ok_statuses, true ) )
				|| ( is_array( $data ) && ! empty( $data['events_rejected'] ) );
			if ( $failed ) {
				return array( 'ok' => false, 'message' => '' !== $reason
					? sprintf( /* translators: %s = motif de refus renvoyé par Snapchat. */ __( 'Snapchat a refusé la validation : %s', 'aod-cod-form' ), $reason )
					: __( 'Snapchat a refusé la validation. Vérifiez que l’ID de pixel appartient bien au compte de ce jeton.', 'aod-cod-form' ) );
			}
			return array( 'ok' => true, 'message' => __( 'Connexion réussie : pixel Snapchat accessible (validation OK).', 'aod-cod-form' ) );
		}

		// 401/403 = jeton refusé ; relayer le message si Snapchat en donne un.
		$err = ( is_array( $data ) && ! empty( $data['error_message'] ) ) ? (string) $data['error_message']
			: ( ( is_array( $data ) && ! empty( $data['message'] ) ) ? (string) $data['message'] : '' );
		return array( 'ok' => false, 'message' => '' !== $err
			? sprintf( /* translators: %s = message d'erreur renvoyé par Snapchat. */ __( 'Refus de Snapchat : %s', 'aod-cod-form' ), $err )
			: __( 'Jeton ou pixel Snapchat invalide.', 'aod-cod-form' ) );
	}

	/* --------------------------------------------------------------------- */
	/* Admin                                                                 */
	/* --------------------------------------------------------------------- */

	public function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Pixels & Tracking', 'aod-cod-form' ),
			__( 'Pixels & Tracking', 'aod-cod-form' ),
			'manage_woocommerce',
			'aod-cod-pixels',
			array( $this, 'render_page' )
		);
	}

	public function maybe_save() {
		if ( ! isset( $_POST['aod_cod_pixels_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		check_admin_referer( 'aod_cod_pixels_save' );

		$existing = $this->settings();

		$settings = array(
			'meta'         => isset( $_POST['meta'] ) ? self::clean_id( wp_unslash( $_POST['meta'] ) ) : '',
			'tiktok'       => isset( $_POST['tiktok'] ) ? self::clean_id( wp_unslash( $_POST['tiktok'] ) ) : '',
			'snapchat'     => isset( $_POST['snapchat'] ) ? self::clean_id( wp_unslash( $_POST['snapchat'] ) ) : '',
			'google_ads'   => isset( $_POST['google_ads'] ) ? self::clean_id( wp_unslash( $_POST['google_ads'] ) ) : '',
			'google_label' => isset( $_POST['google_label'] ) ? self::clean_id( wp_unslash( $_POST['google_label'] ) ) : '',
			'meta_domain_verification' => isset( $_POST['meta_domain_verification'] ) ? self::clean_domain_verification( wp_unslash( $_POST['meta_domain_verification'] ) ) : '',
			// Jeton CAPI (secret) : « vide = inchangé » (le champ n'affiche jamais
			// le jeton en clair, un envoi vide ne l'efface donc pas), la case
			// « effacer » le supprime explicitement. Cf. capi_token_from_post().
			'meta_capi_token'      => self::capi_token_from_post( $existing['meta_capi_token'] ),
			'meta_test_event_code' => isset( $_POST['meta_test_event_code'] ) ? sanitize_text_field( wp_unslash( $_POST['meta_test_event_code'] ) ) : '',
			'tiktok_capi_token'    => self::capi_token_from_post( $existing['tiktok_capi_token'], 'tiktok_capi_token' ),
			'tiktok_test_event_code' => isset( $_POST['tiktok_test_event_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tiktok_test_event_code'] ) ) : '',
			'snapchat_capi_token'  => self::capi_token_from_post( $existing['snapchat_capi_token'], 'snapchat_capi_token' ),
			'report_currency' => isset( $_POST['report_currency'] ) ? self::clean_currency( wp_unslash( $_POST['report_currency'] ) ) : '',
			'report_rate'     => isset( $_POST['report_rate'] ) ? (string) max( 0, (float) wp_unslash( $_POST['report_rate'] ) ) : '',
		);
		update_option( self::OPTION, $settings );

		add_settings_error( 'aod_cod_pixels', 'saved', __( 'Pixels enregistrés.', 'aod-cod-form' ), 'updated' );
	}

	public function render_page() {
		$s = $this->settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pixels & Tracking publicitaire', 'aod-cod-form' ); ?></h1>
			<?php settings_errors( 'aod_cod_pixels' ); ?>

			<p><?php esc_html_e( 'Colle l’identifiant de chaque régie. Laisse vide pour désactiver. Le PageView est suivi sur tout le site ; l’achat est mesuré sur la page de remerciement (une fois par commande).', 'aod-cod-form' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'aod_cod_pixels_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aod_px_meta"><?php esc_html_e( 'Meta (Facebook) Pixel ID', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_px_meta" name="meta" value="<?php echo esc_attr( $s['meta'] ); ?>" class="regular-text" placeholder="1234567890">
							<p class="description"><?php esc_html_e( 'Gestionnaire d’événements Meta → Paramètres → ID du pixel.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_tiktok"><?php esc_html_e( 'TikTok Pixel ID', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_px_tiktok" name="tiktok" value="<?php echo esc_attr( $s['tiktok'] ); ?>" class="regular-text" placeholder="CXXXXXXXXXXXXXXXXXXX">
							<p class="description"><?php esc_html_e( 'TikTok Ads → Outils → Événements → Pixel Web.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_snapchat"><?php esc_html_e( 'Snapchat Pixel ID', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_px_snapchat" name="snapchat" value="<?php echo esc_attr( $s['snapchat'] ); ?>" class="regular-text" placeholder="00000000-0000-0000-0000-000000000000">
							<p class="description"><?php esc_html_e( 'Snapchat Ads → Gestionnaire d’événements → Pixel ID.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_google"><?php esc_html_e( 'Google Ads — ID de conversion', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_px_google" name="google_ads" value="<?php echo esc_attr( $s['google_ads'] ); ?>" class="regular-text" placeholder="AW-123456789">
							<p class="description"><?php esc_html_e( 'Commence par AW-. Google Ads → Objectifs → Conversions.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_google_label"><?php esc_html_e( 'Google Ads — Libellé de conversion', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_px_google_label" name="google_label" value="<?php echo esc_attr( $s['google_label'] ); ?>" class="regular-text" placeholder="AbC-D_efG-h12">
							<p class="description"><?php esc_html_e( 'La partie après la barre « / » dans send_to (étiquette de l’action de conversion « Achat »). Sans elle, seule la balise de base est posée.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_meta_dv"><?php esc_html_e( 'Meta — Vérification de domaine', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_px_meta_dv" name="meta_domain_verification" value="<?php echo esc_attr( $s['meta_domain_verification'] ); ?>" class="regular-text" placeholder="q7tv7ppepuk3m4z9op7bjcr3747t93">
							<p class="description"><?php esc_html_e( 'Colle la balise <meta name="facebook-domain-verification" …> fournie par Meta (ou juste son code). Injectée dans le <head> du site pour valider le domaine.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_capi_token"><?php esc_html_e( 'Meta — Jeton d’API Conversions (CAPI)', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="password" id="aod_px_capi_token" name="meta_capi_token" value="" autocomplete="off" class="regular-text" placeholder="<?php echo '' !== $s['meta_capi_token'] ? esc_attr__( '•••••••• (jeton enregistré — laisser vide pour le conserver)', 'aod-cod-form' ) : 'EAAG...'; ?>">
							<?php if ( '' !== $s['meta_capi_token'] ) : ?>
								<label style="display:inline-block;margin-inline-start:8px"><input type="checkbox" name="meta_capi_token_clear" value="1"> <?php esc_html_e( 'Effacer le jeton', 'aod-cod-form' ); ?></label>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Double le pixel navigateur d’un envoi serveur des achats (Facebook/Instagram), dédupliqué automatiquement. Gestionnaire d’événements Meta → Paramètres → Conversions API → Générer un jeton d’accès. Nécessite l’ID du pixel Meta ci-dessus. Laisser vide pour garder le pixel navigateur seul.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_capi_test"><?php esc_html_e( 'Meta — Code d’événement test (optionnel)', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_px_capi_test" name="meta_test_event_code" value="<?php echo esc_attr( $s['meta_test_event_code'] ); ?>" class="regular-text" placeholder="TEST12345">
							<p class="description"><?php esc_html_e( 'Pour vérifier la réception dans le Gestionnaire d’événements → onglet « Test des événements ». À vider une fois la mise en production validée.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_tiktok_capi"><?php esc_html_e( 'TikTok — Jeton Events API (conversions serveur)', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="password" id="aod_px_tiktok_capi" name="tiktok_capi_token" value="" autocomplete="off" class="regular-text" placeholder="<?php echo '' !== $s['tiktok_capi_token'] ? esc_attr__( '•••••••• (jeton enregistré — laisser vide pour le conserver)', 'aod-cod-form' ) : '...'; ?>">
							<?php if ( '' !== $s['tiktok_capi_token'] ) : ?>
								<label style="display:inline-block;margin-inline-start:8px"><input type="checkbox" name="tiktok_capi_token_clear" value="1"> <?php esc_html_e( 'Effacer le jeton', 'aod-cod-form' ); ?></label>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Double le pixel navigateur TikTok d’un envoi serveur des achats, dédupliqué automatiquement. TikTok Ads → Outils → Événements → gérer l’Events API → générer un jeton d’accès. Nécessite l’ID du pixel TikTok ci-dessus. Laisser vide pour garder le pixel navigateur seul.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_tiktok_test"><?php esc_html_e( 'TikTok — Code d’événement test (optionnel)', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_px_tiktok_test" name="tiktok_test_event_code" value="<?php echo esc_attr( $s['tiktok_test_event_code'] ); ?>" class="regular-text" placeholder="TEST12345">
							<p class="description"><?php esc_html_e( 'TikTok Ads → Événements → onglet « Test ». Requis par le bouton « Tester la connexion » de /gestion (l’événement de test n’entre pas dans vos statistiques). À vider une fois validé.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_snapchat_capi"><?php esc_html_e( 'Snapchat — Jeton Conversions API (conversions serveur)', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="password" id="aod_px_snapchat_capi" name="snapchat_capi_token" value="" autocomplete="off" class="regular-text" placeholder="<?php echo '' !== $s['snapchat_capi_token'] ? esc_attr__( '•••••••• (jeton enregistré — laisser vide pour le conserver)', 'aod-cod-form' ) : '...'; ?>">
							<?php if ( '' !== $s['snapchat_capi_token'] ) : ?>
								<label style="display:inline-block;margin-inline-start:8px"><input type="checkbox" name="snapchat_capi_token_clear" value="1"> <?php esc_html_e( 'Effacer le jeton', 'aod-cod-form' ); ?></label>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Double le pixel navigateur Snapchat d’un envoi serveur des achats, dédupliqué automatiquement. Snapchat Ads → Gestionnaire d’événements → Conversions API → générer un jeton d’accès. Nécessite l’ID du pixel Snapchat ci-dessus. Laisser vide pour garder le pixel navigateur seul.', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aod_px_report_currency"><?php esc_html_e( 'Devise de reporting (régies pub)', 'aod-cod-form' ); ?></label></th>
						<td>
							<input type="text" id="aod_px_report_currency" name="report_currency" value="<?php echo esc_attr( $s['report_currency'] ); ?>" class="regular-text" placeholder="USD" maxlength="3" style="text-transform:uppercase">
							<input type="number" step="0.0001" min="0" id="aod_px_report_rate" name="report_rate" value="<?php echo esc_attr( $s['report_rate'] ); ?>" placeholder="0.0075">
							<p class="description"><?php esc_html_e( 'Meta refuse le dinar algérien (DZD) : la valeur des achats n’est pas mesurée. Déclare ici une devise reconnue (USD, EUR…) et le taux « 1 DZD = ? » pour convertir la valeur envoyée aux régies. Laisse vide pour envoyer DZD tel quel (défaut).', 'aod-cod-form' ); ?></p>
						</td>
					</tr>
				</table>
				<p><button type="submit" name="aod_cod_pixels_save" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'aod-cod-form' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
