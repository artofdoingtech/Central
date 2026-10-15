<?php
/**
 * Contrôle d'accès du gérant client.
 *
 * - Bloque l'accès à wp-admin (redirige vers /gestion) sauf AJAX autorisé.
 * - Masque la barre d'administration en front.
 * - Redirige vers /gestion après connexion.
 * - Marque la page de connexion aux couleurs de la boutique.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Access {

	/** @var AOD_CD_Access|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'block_wp_admin' ), 1 );
		add_action( 'after_setup_theme', array( $this, 'hide_admin_bar' ) );
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );

		// Marquage de la page de connexion.
		add_action( 'login_enqueue_scripts', array( $this, 'login_styles' ) );
		add_filter( 'login_headerurl', array( $this, 'login_url' ) );
		add_filter( 'login_headertext', array( $this, 'login_title' ) );
	}

	/**
	 * L'utilisateur relève-t-il de l'espace /gestion ? (gérant ou confirmateur,
	 * hors super-admin) : wp-admin bloqué, barre d'admin masquée, connexion
	 * redirigée vers /gestion.
	 *
	 * @param WP_User|null $user
	 * @return bool
	 */
	public static function is_client( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		// Un admin garde tous ses droits ; seuls les rôles dédiés sont restreints.
		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}
		$roles = (array) $user->roles;
		return in_array( AOD_CD_ROLE, $roles, true ) || in_array( AOD_CD_ROLE_CONFIRMER, $roles, true );
	}

	/**
	 * Empêche le gérant client d'ouvrir wp-admin : on le renvoie sur /gestion.
	 * AJAX (admin-ajax.php) reste autorisé pour les actions du dashboard.
	 */
	public function block_wp_admin() {
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( ! self::is_client() ) {
			return;
		}
		wp_safe_redirect( home_url( '/' . AOD_CD_SLUG . '/' ) );
		exit;
	}

	/**
	 * Masque la barre d'admin en front pour le gérant client.
	 */
	public function hide_admin_bar() {
		if ( self::is_client() ) {
			show_admin_bar( false );
		}
	}

	/**
	 * Après connexion, envoie le gérant client vers son dashboard.
	 *
	 * @param string  $redirect
	 * @param string  $requested
	 * @param WP_User $user
	 * @return string
	 */
	public function login_redirect( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && self::is_client( $user ) ) {
			return home_url( '/' . AOD_CD_SLUG . '/' );
		}
		return $redirect;
	}

	/* ============================================================
	 * Marquage de la page de connexion
	 * ========================================================== */

	/**
	 * URL du logo de la boutique — page de connexion ET barre latérale de /gestion.
	 *
	 * Priorité : logo du thème (personnalisateur) → icône du site → aucune (texte).
	 *
	 * 🔁 RENDUE PUBLIQUE ET RENOMMÉE LE 2026-08-06. Elle s'appelait
	 * `login_logo_url()` et ne servait qu'à la page de connexion — pendant que la
	 * barre latérale de `/gestion`, elle, n'affichait AUCUN logo : juste le nom du
	 * site et un chevron dessiné en CSS. Or `TEMPLATE.md` §5 promet en toutes
	 * lettres « logo + nom via le Personnalisateur → le branding du dashboard/login
	 * suit automatiquement ». La moitié « login » était vraie, la moitié
	 * « dashboard » ne l'a jamais été, et rien ne pouvait le signaler : un logo
	 * absent ne casse pas un écran, il le laisse simplement générique.
	 * 🚨 *Une promesse écrite au singulier sur deux surfaces n'est vérifiée que sur
	 * celle qu'on regarde.* Un seul corps, deux appelants — la règle de `side()`.
	 *
	 * @return string URL ou '' si aucun logo défini.
	 */
	public static function brand_logo_url() {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $src ) {
				return $src[0];
			}
		}
		if ( function_exists( 'get_site_icon_url' ) ) {
			$icon = get_site_icon_url( 192 );
			if ( $icon ) {
				return $icon;
			}
		}
		return '';
	}

	/**
	 * La MARQUE de la barre latérale : le logo du client, ou le sceau du template.
	 *
	 * 🎨 POURQUOI UN REPLI DESSINÉ, ET PAS « rien ». Un clone neuf n'a pas de logo :
	 * la barre affichait alors un nom de boutique posé sur du vide, sans point
	 * d'ancrage pour l'œil. Le repli est un sceau — la vitrine du catalogue fermé
	 * `AOD_CD_Icon`, posée sur une pastille d'accent — et non une lettre ou un
	 * monogramme : une initiale serait fausse dans la moitié des cas (elle ne dit
	 * rien d'une boutique nommée « Art Of Doing »), et carrément illisible en arabe.
	 * Une vitrine dit ce que l'espace gère, dans toutes les langues.
	 *
	 * 🔒 ZÉRO COULEUR ICI. La pastille prend `--cd-accent` et son encre
	 * `--cd-accent-ink` (best_ink) : le sceau est donc à la couleur du client sur
	 * chaque clone, et lisible pour tout accent du cube — règle template n°1.
	 *
	 * ⚠️ `alt=""` sur le logo, à dessein : le nom de la boutique est juste à côté,
	 * dans le même bloc. Un `alt` le répéterait, et une synthèse vocale lirait deux
	 * fois la même marque.
	 *
	 * @return string HTML sûr.
	 */
	public static function brand_mark() {
		$logo = self::brand_logo_url();
		if ( '' !== $logo ) {
			return '<span class="aod-cd-brand-mark"><img class="aod-cd-brand-logo" src="' . esc_url( $logo ) . '" alt=""></span>';
		}
		// Sortie de AOD_CD_Icon : catalogue fermé, jamais une saisie.
		return '<span class="aod-cd-brand-mark aod-cd-brand-mark--seal">'
			. AOD_CD_Icon::svg( 'storefront', array( 'size' => 19, 'weight' => 'fill' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG d'un catalogue FERMÉ, nom d'icône littéral du dépôt ; wp_kses_post() mangerait ses attributs.
			. '</span>';
	}

	public function login_styles() {
		// Neutre sombre pour le nom du site en repli (pas de logo), posé sur le fond
		// CLAIR de la page. Ce n'est PAS la marque : un accent de client clair (le
		// défaut est un jaune vif) y serait illisible sur fond clair — le piège
		// inverse de celui que `--cd-accent-ink` règle sur le bouton. Neutre = sûr
		// quel que soit l'accent. Le branding, lui, passe par le logo et le bouton.
		$ink_neutre = '#111827';
		$logo       = self::brand_logo_url();
		?>
		<style>
			<?php // Même charte que /gestion : l'accent du Personnalisateur et son encre
			// lisible (best_ink), au lieu d'une couleur en dur. Le bouton de connexion
			// prend ainsi la couleur de marque du client — Règle d'or n°1 (branding via
			// le Personnalisateur, jamais codé en dur). `css_vars()` émet `:root{…}`. ?>
			<?php echo AOD_CD_Customize::css_vars(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- valeurs hex issues de sanitize_hex_color / formatage interne ?>
			<?php // 🔁 2026-08-08 — L'ÉCRAN DE CONNEXION PORTE ENFIN LE MÊME FOND QUE
			// `/gestion`. Il gardait `background:#f3f4f6`, un gris écrit en dur : la porte
			// d'entrée de l'espace de gestion était la seule page à ne pas ressembler à ce
			// qu'elle ouvre. Les deux tons étaient déjà là — `css_vars()` les injecte
			// au-dessus depuis la 29.1 —, personne ne les lisait.
			// ⚠️ `dashboard.css` N'EST PAS CHARGÉE ICI : seuls les tokens émis par
			// `css_vars()` existent sur cette page. Ne pas y écrire `var(--cd-line)` ou
			// `var(--cd-ink)` — ils n'ont aucune valeur, et un `var()` sans repli ne
			// produit AUCUNE erreur : la déclaration est ignorée, en silence. ?>
			body.login {
				background-color:var(--cd-page);
				background-image:linear-gradient(155deg,var(--cd-page) 0%,var(--cd-page-2) 100%);
				background-repeat:no-repeat; background-attachment:fixed;
			}
			<?php // Le balayage suit le sens de lecture, comme dans `/gestion`. WordPress
			// pose lui-même la classe `rtl` sur le body de la connexion quand `is_rtl()`. ?>
			body.login.rtl {
				background-image:linear-gradient(205deg,var(--cd-page) 0%,var(--cd-page-2) 100%);
			}
			<?php if ( $logo ) : ?>
			.login h1 a {
				background-image:url('<?php echo esc_url( $logo ); ?>');
				background-size:contain; background-position:center; background-repeat:no-repeat;
				width:auto; max-width:320px; height:84px; margin:0 auto 18px;
				text-indent:-9999px; overflow:hidden;
			}
			<?php else : ?>
			.login h1 a {
				background-image:none;
				width:auto; height:auto;
				font-size:26px; font-weight:800; line-height:1.2;
				text-indent:0; color:<?php echo esc_html( $ink_neutre ); ?>;
				text-decoration:none;
			}
			<?php endif; ?>
			<?php // La carte de connexion prend la surface des cartes de `/gestion`, et les
			// champs celle des champs : même règle « le blanc est un RÔLE, pas un défaut ».
			// Le trait, lui, reste un gris PUR en dur : `--cd-line` vit dans
			// `dashboard.css`, qui n'est pas chargée ici (cf. l'avertissement plus haut).
			// C'est un neutre sans teinte — la charte l'autorise en dur, contrairement à
			// une couleur. ?>
			.login form { background:var(--cd-card); border-radius:14px; border:1px solid #e2e2e2; box-shadow:0 6px 24px rgba(0,0,0,.06); }
			.login form .input, .login input[type="text"], .login input[type="password"] { background:var(--cd-field); }
			.wp-core-ui .button-primary {
				background:var(--cd-accent); border-color:var(--cd-accent);
				color:var(--cd-accent-ink);
				border-radius:10px;
			}
			<?php // 🔴 CORRIGÉ LE 2026-08-08 : le survol lisait `--cd-accent-ink`, l'encre
			// calculée pour l'accent de BASE, alors que son fond est `--cd-accent-strong`.
			// C'est mot pour mot le défaut mesuré et corrigé le 2026-08-05 dans
			// `dashboard.css` — 4 accents sur 8 tombent sous AA au survol quand on
			// réutilise l'encre de base, parce que `$strong` DÉPLACE la luminance et peut
			// franchir le seuil de bascule de `best_ink()` que la base n'avait pas
			// franchi. La correction n'avait jamais atteint CETTE page : elle ne se voit
			// ni au repos, ni sur l'accent par défaut (où les deux encres coïncident).
			// C'est le motif « une décision appliquée à un jumeau et pas à l'autre »,
			// gardé désormais par R4 de `test-login-charte.php`. ?>
			.wp-core-ui .button-primary:hover, .wp-core-ui .button-primary:focus {
				background:var(--cd-accent-strong); border-color:var(--cd-accent-strong);
				color:var(--cd-accent-strong-ink);
			}
			.login #backtoblog, .login #nav { text-align:center; }
		</style>
		<?php
	}

	public function login_url() {
		return home_url( '/' );
	}

	public function login_title() {
		return get_bloginfo( 'name' );
	}
}
