<?php
/**
 * Écran wp-admin d'activation de la licence + notices de rappel d'expiration.
 *
 * @package AOD_Gestion_License
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AODG_Admin {

	const NONCE_ACTION = 'aodg_license_action';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_aodg_activate', array( __CLASS__, 'handle_activate' ) );
		add_action( 'admin_post_aodg_deactivate', array( __CLASS__, 'handle_deactivate' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Licence AOD Gestion', 'aod-gestion-license' ),
			__( 'Licence AOD Gestion', 'aod-gestion-license' ),
			'manage_options',
			'aod-gestion-license',
			array( __CLASS__, 'render_page' ),
			'dashicons-lock',
			99
		);
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_aod-gestion-license' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'aodg-admin',
			AODG_URL . 'assets/css/aodg-admin.css',
			array(),
			AODG_VERSION
		);
	}

	public static function handle_activate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'aod-gestion-license' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		$key    = isset( $_POST['aodg_key'] ) ? wp_unslash( $_POST['aodg_key'] ) : '';
		$result = AODG_License::activate( $key );

		self::redirect_with_notice( $result['ok'] ? 'activated' : 'error', $result['message'] );
	}

	public static function handle_deactivate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'aod-gestion-license' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		AODG_License::deactivate();

		self::redirect_with_notice( 'deactivated', __( 'Licence retirée de ce site.', 'aod-gestion-license' ) );
	}

	protected static function redirect_with_notice( $type, $message ) {
		$url = add_query_arg(
			array(
				'page'         => 'aod-gestion-license',
				'aodg_notice'  => $type,
				'aodg_message' => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = AODG_License::status();
		$data   = AODG_License::get_data();
		$notice = isset( $_GET['aodg_notice'] ) ? sanitize_key( $_GET['aodg_notice'] ) : '';
		$msg    = isset( $_GET['aodg_message'] ) ? sanitize_text_field( wp_unslash( $_GET['aodg_message'] ) ) : '';

		$labels = array(
			'none'          => array( __( 'Aucune licence', 'aod-gestion-license' ), '#94a3b8' ),
			'invalid'       => array( __( 'Licence invalide', 'aod-gestion-license' ), '#ef4444' ),
			'expired'       => array( __( 'Licence expirée', 'aod-gestion-license' ), '#ef4444' ),
			'expiring_soon' => array( __( 'Expire bientôt', 'aod-gestion-license' ), '#f59e0b' ),
			'valid'         => array( __( 'Licence active', 'aod-gestion-license' ), '#22c55e' ),
		);
		list( $label, $color ) = $labels[ $status ];

		echo '<div class="wrap aodg-wrap">';
		echo '<h1>' . esc_html__( 'Licence AOD Gestion', 'aod-gestion-license' ) . '</h1>';

		if ( $notice && $msg ) {
			$class = ( 'error' === $notice ) ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}

		echo '<div class="aodg-card">';
		echo '<p class="aodg-badge" style="background:' . esc_attr( $color ) . '">' . esc_html( $label ) . '</p>';

		if ( $data ) {
			echo '<table class="widefat striped aodg-table"><tbody>';
			echo '<tr><th>' . esc_html__( 'Client', 'aod-gestion-license' ) . '</th><td>' . esc_html( $data['client'] ?: '—' ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Domaine', 'aod-gestion-license' ) . '</th><td>' . esc_html( $data['domain'] ?: '*' ) . '</td></tr>';
			if ( ! empty( $data['issued'] ) ) {
				echo '<tr><th>' . esc_html__( 'Émise le', 'aod-gestion-license' ) . '</th><td>' . esc_html( date_i18n( get_option( 'date_format' ), (int) $data['issued'] ) ) . '</td></tr>';
			}
			if ( ! empty( $data['expires'] ) ) {
				echo '<tr><th>' . esc_html__( 'Expire le', 'aod-gestion-license' ) . '</th><td>' . esc_html( date_i18n( get_option( 'date_format' ), (int) $data['expires'] ) ) . '</td></tr>';
			}
			$days = AODG_License::days_remaining();
			if ( null !== $days ) {
				echo '<tr><th>' . esc_html__( 'Jours restants', 'aod-gestion-license' ) . '</th><td>' . esc_html( $days ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2>' . esc_html__( 'Activer une clé', 'aod-gestion-license' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aodg-form">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<input type="hidden" name="action" value="aodg_activate">';
		echo '<textarea name="aodg_key" rows="3" class="large-text code" placeholder="AODG1.xxxxxxxx.xxxxxxxx" required></textarea>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Activer', 'aod-gestion-license' ) . '</button></p>';
		echo '</form>';

		if ( $data ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Retirer la licence de ce site ?', 'aod-gestion-license' ) ) . '\');">';
			wp_nonce_field( self::NONCE_ACTION );
			echo '<input type="hidden" name="action" value="aodg_deactivate">';
			echo '<p><button type="submit" class="button">' . esc_html__( 'Retirer la licence', 'aod-gestion-license' ) . '</button></p>';
			echo '</form>';
		}

		echo '</div>'; // .aodg-card

		echo '<div class="aodg-card">';
		echo '<h2>' . esc_html__( 'État des extensions liées', 'aod-gestion-license' ) . '</h2>';
		echo '<ul class="aodg-deps">';
		self::dependency_row( 'WooCommerce', class_exists( 'WooCommerce' ) );
		self::dependency_row( 'AOD COD Form', defined( 'AOD_COD_VERSION' ) );
		self::dependency_row( 'AOD Client Dashboard', defined( 'AOD_CD_VERSION' ) );
		echo '</ul>';
		echo '</div>';

		echo '</div>'; // .wrap
	}

	protected static function dependency_row( $name, $ok ) {
		$icon = $ok ? '✅' : '⚠️';
		echo '<li>' . esc_html( $icon . ' ' . $name ) . '</li>';
	}

	/**
	 * Rappels d'expiration + avertissement si le plugin de licence est
	 * présent mais que les deux plugins fonctionnels sont absents/inactifs.
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && 'toplevel_page_aod-gestion-license' === $screen->id ) {
			return; // Déjà tout affiché sur l'écran dédié.
		}

		$status = AODG_License::status();

		if ( 'none' === $status ) {
			return; // Pas encore configuré : pas la peine de harceler partout.
		}

		if ( 'expired' === $status ) {
			echo '<div class="notice notice-error"><p><strong>AOD Gestion —</strong> '
				. esc_html__( 'la licence a expiré. L’espace /gestion est désormais bloqué.', 'aod-gestion-license' )
				. ' <a href="' . esc_url( admin_url( 'admin.php?page=aod-gestion-license' ) ) . '">'
				. esc_html__( 'Renouveler', 'aod-gestion-license' ) . '</a></p></div>';
			return;
		}

		if ( 'invalid' === $status ) {
			echo '<div class="notice notice-error"><p><strong>AOD Gestion —</strong> '
				. esc_html__( 'la clé de licence enregistrée n’est pas valide pour ce site.', 'aod-gestion-license' )
				. ' <a href="' . esc_url( admin_url( 'admin.php?page=aod-gestion-license' ) ) . '">'
				. esc_html__( 'Vérifier', 'aod-gestion-license' ) . '</a></p></div>';
			return;
		}

		if ( 'expiring_soon' === $status ) {
			$days = AODG_License::days_remaining();
			echo '<div class="notice notice-warning"><p><strong>AOD Gestion —</strong> '
				. sprintf(
					/* translators: %d: number of days. */
					esc_html__( 'la licence expire dans %d jour(s).', 'aod-gestion-license' ),
					(int) $days
				)
				. ' <a href="' . esc_url( admin_url( 'admin.php?page=aod-gestion-license' ) ) . '">'
				. esc_html__( 'Renouveler', 'aod-gestion-license' ) . '</a></p></div>';
		}
	}
}
