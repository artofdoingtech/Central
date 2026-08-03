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
	 * URL du logo de la boutique pour la page de connexion.
	 *
	 * Priorité : logo du thème (personnalisateur) → icône du site → aucune (texte).
	 *
	 * @return string URL ou '' si aucun logo défini.
	 */
	protected function login_logo_url() {
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

	public function login_styles() {
		// Neutre sombre pour le nom du site en repli (pas de logo), posé sur le fond
		// CLAIR de la page. Ce n'est PAS la marque : un accent de client clair (le
		// défaut est un jaune vif) y serait illisible sur fond clair — le piège
		// inverse de celui que `--cd-accent-ink` règle sur le bouton. Neutre = sûr
		// quel que soit l'accent. Le branding, lui, passe par le logo et le bouton.
		$ink_neutre = '#111827';
		$logo       = $this->login_logo_url();
		?>
		<style>
			<?php // Même charte que /gestion : l'accent du Personnalisateur et son encre
			// lisible (best_ink), au lieu d'une couleur en dur. Le bouton de connexion
			// prend ainsi la couleur de marque du client — Règle d'or n°1 (branding via
			// le Personnalisateur, jamais codé en dur). `css_vars()` émet `:root{…}`. ?>
			<?php echo AOD_CD_Customize::css_vars(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- valeurs hex issues de sanitize_hex_color / formatage interne ?>
			body.login { background:#f3f4f6; }
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
			.login form { border-radius:14px; border:1px solid #e5e7eb; box-shadow:0 6px 24px rgba(0,0,0,.06); }
			.wp-core-ui .button-primary {
				background:var(--cd-accent); border-color:var(--cd-accent);
				color:var(--cd-accent-ink);
				border-radius:10px;
			}
			.wp-core-ui .button-primary:hover, .wp-core-ui .button-primary:focus {
				background:var(--cd-accent-strong); border-color:var(--cd-accent-strong);
				color:var(--cd-accent-ink);
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
