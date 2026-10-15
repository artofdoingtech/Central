<?php
/**
 * Plugin Name:       AOD Client Dashboard
 * Plugin URI:        https://artofdoing.net
 * Description:       Espace de gestion front-end pour le client de la boutique : commandes, produits, livraison, stats, pixels, WhatsApp — SANS accès à wp-admin. Rôle restreint + interface marquée au thème.
 * Version:           0.75.0
 * Author:            Art Of Doing
 * Author URI:        https://artofdoing.net
 * Text Domain:       aod-client-dashboard
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce, aod-cod-form, aod-gestion-license
 * License:           GPL-2.0-or-later
 *
 * ⚠️ COPIE — ce fichier fait partie du paquet EXTENSION_INSTALABLE (licence
 * annuelle, /gestion sur un site WordPress existant). Il diffère de
 * l'original de plugins-dev/aod-client-dashboard/ par deux modifications
 * SEULES, marquées « PATCH AODG » ci-dessous : l'en-tête `Requires Plugins`
 * ci-dessus, et le garde-fou de licence juste après la vérification
 * WooCommerce. Rien d'autre n'est modifié — voir
 * EXTENSION_INSTALABLE/README.md « Pourquoi 3 plugins et pas 1 » pour le
 * raisonnement, et la procédure de resynchronisation depuis plugins-dev/.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Accès direct interdit.
}

define( 'AOD_CD_VERSION', '0.75.0' );
define( 'AOD_CD_FILE', __FILE__ );
define( 'AOD_CD_PATH', plugin_dir_path( __FILE__ ) );
define( 'AOD_CD_URL', plugin_dir_url( __FILE__ ) );

/** Rôle du gérant de boutique côté client. */
define( 'AOD_CD_ROLE', 'aod_shop_client' );
/** Rôle du confirmateur de commandes : permissions à la carte via caps custom. */
define( 'AOD_CD_ROLE_CONFIRMER', 'aod_confirmateur' );
/** Capability custom qui ouvre l'accès au dashboard /gestion. */
define( 'AOD_CD_CAP', 'aod_use_dashboard' );
/** Slug de la page front-end de gestion. */
define( 'AOD_CD_SLUG', 'gestion' );

require_once AOD_CD_PATH . 'includes/class-aod-cd-roles.php';

/**
 * Activation : crée le rôle restreint et pose les règles de réécriture /gestion.
 */
register_activation_hook( __FILE__, function () {
	AOD_CD_Roles::create_role();
	// La règle de réécriture est ajoutée sur 'init' ; on force un flush ici.
	require_once AOD_CD_PATH . 'includes/class-aod-cd-dashboard.php';
	AOD_CD_Dashboard::add_rewrite();
	flush_rewrite_rules();
} );

/**
 * Désactivation : retire la règle de réécriture (on conserve le rôle et ses users).
 */
register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

/**
 * Démarrage.
 */
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'AOD Client Dashboard est inactif : WooCommerce est requis.', 'aod-client-dashboard' )
				. '</p></div>';
		} );
		return;
	}

	// === PATCH AODG (extension installable, licence annuelle) ===============
	// AODG_LICENSE_ACTIVE n'est défini que si « AOD Gestion — Licence » est
	// actif (EXTENSION_INSTALABLE/plugins/aod-gestion-license). Sans lui, le
	// dashboard refuse de démarrer — un client ne peut pas contourner la
	// licence en désactivant simplement le plugin de licence : /gestion ne
	// fonctionne alors ni sans clé, ni sans CE plugin présent. La validité de
	// la clé elle-même (expirée, invalide…) est vérifiée séparément par
	// AODG_Gate, qui bloque /gestion et ses actions AJAX au cas par cas —
	// c'est ce garde-fou-ci qui gère seulement « le plugin de licence a
	// disparu ». Absent du fichier original de plugins-dev/.
	if ( ! defined( 'AODG_LICENSE_ACTIVE' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'AOD Client Dashboard est inactif : le plugin « AOD Gestion — Licence » est requis et doit être activé.', 'aod-client-dashboard' )
				. '</p></div>';
		} );
		return;
	}
	// === /PATCH AODG ==========================================================

	// Sélecteur de langue (FR ⇄ AR) : à initialiser AVANT le chargement des
	// traductions pour que le filtre de locale s'applique aux .mo chargés ici.
	require_once AOD_CD_PATH . 'includes/class-aod-cd-lang.php';
	AOD_CD_Lang::init();

	// Sur `init`, pas ici : depuis WordPress 6.7, charger un textdomain (ou
	// appeler __()) avant `init` déclenche « _load_textdomain_just_in_time was
	// called incorrectly ». Le notice s'imprime avant les en-têtes HTTP et casse
	// alors tout wp_redirect()/setcookie() (« headers already sent »).
	add_action( 'init', function () {
		load_plugin_textdomain( 'aod-client-dashboard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}, 0 );

	// Dépendance forte (non bloquante) : AOD COD Form fournit le statut
	// « Confirmée », les tarifs par wilaya et les transporteurs. Sans lui, ces
	// parties de l'espace de gestion se désactivent proprement — on prévient
	// l'administrateur pour qu'il sache pourquoi elles manquent.
	if ( ! defined( 'AOD_COD_VERSION' ) ) {
		add_action( 'admin_notices', function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'AOD Client Dashboard : le plugin « AOD COD Form » est recommandé. Sans lui, les commandes « Confirmées », les tarifs de livraison par wilaya et l’envoi automatique aux transporteurs ne sont pas disponibles dans l’espace de gestion.', 'aod-client-dashboard' )
				. '</p></div>';
		} );
	}

	require_once AOD_CD_PATH . 'includes/class-aod-cd-roles.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-access.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-customize.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-icon.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-temps.php';
	// Disponibilité du socle COD (32.1) — AVANT les sections, qui l'interrogent.
	require_once AOD_CD_PATH . 'includes/class-aod-cd-cod.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-dashboard.php';
	// Parcours de commandes par lots (31.6) — AVANT les sections qui l'utilisent.
	require_once AOD_CD_PATH . 'includes/class-aod-cd-orders.php';
	// Le seul téléversement du dépôt (32.2) — AVANT les sections qui l'appellent.
	require_once AOD_CD_PATH . 'includes/class-aod-cd-upload.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-home.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-stock.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-abandoned.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-team.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-landing.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-products.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-whatsapp.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-marketing.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-account.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-shipping.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-tracking.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-orders.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-section-finance.php';
	require_once AOD_CD_PATH . 'includes/class-aod-cd-account.php';

	AOD_CD_Roles::instance();
	AOD_CD_Access::instance();
	AOD_CD_Customize::init();
	AOD_CD_Dashboard::instance();
	AOD_CD_Section_Home::instance();
	AOD_CD_Section_Finance::instance(); // hooks AJAX du calculateur au boot.
	AOD_CD_Section_Stock::instance();
	AOD_CD_Section_Abandoned::instance(); // hook AJAX de conversion abandon → commande au boot (39.1)
	AOD_CD_Section_Team::instance();
	AOD_CD_Section_Landing::instance();
	AOD_CD_Section_Products::instance();
	AOD_CD_Section_WhatsApp::instance(); // enregistre ses hooks AJAX au boot (30.1)
	AOD_CD_Section_Marketing::instance(); // hooks pixels/CAPI au boot (30.2)
	AOD_CD_Section_Account::instance(); // hooks Mon compte + reset au boot (30.3)
	AOD_CD_Section_Shipping::instance(); // hooks livraison au boot (30.4)
	AOD_CD_Section_Tracking::instance(); // hooks suivi commande au boot (30.5)
	AOD_CD_Section_Orders::instance(); // hooks commandes + pack au boot (30.6 — dernière extraction)
	AOD_CD_Account::instance();
} );

/**
 * Compatibilité HPOS (stockage des commandes haute performance).
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
