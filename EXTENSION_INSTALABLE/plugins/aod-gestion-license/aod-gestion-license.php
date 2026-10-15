<?php
/**
 * Plugin Name:       AOD Gestion — Licence
 * Plugin URI:        https://artofdoing.net
 * Description:       Active et vérifie la licence annuelle de l'espace de gestion /gestion (AOD COD Form + AOD Client Dashboard). Sans ce plugin actif, /gestion ne démarre pas.
 * Version:           1.0.0
 * Author:            Art Of Doing
 * Author URI:        https://artofdoing.net
 * Text Domain:       aod-gestion-license
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 *
 * @package AOD_Gestion_License
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Accès direct interdit.
}

define( 'AODG_VERSION', '1.0.0' );
define( 'AODG_FILE', __FILE__ );
define( 'AODG_PATH', plugin_dir_path( __FILE__ ) );
define( 'AODG_URL', plugin_dir_url( __FILE__ ) );

/**
 * Marqueur de présence lu par la copie d'aod-client-dashboard livrée dans
 * cette extension : sans lui, le dashboard refuse de s'initialiser (voir le
 * commentaire dans aod-client-dashboard.php, section « démarrage »).
 * Volontairement défini ICI, au chargement du fichier — donc avant tout hook
 * — pour être visible quel que soit l'ordre de chargement des plugins.
 */
define( 'AODG_LICENSE_ACTIVE', true );

require_once AODG_PATH . 'includes/class-aodg-license.php';
require_once AODG_PATH . 'includes/class-aodg-gate.php';
require_once AODG_PATH . 'includes/class-aodg-admin.php';

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'aod-gestion-license', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	0
);

add_action( 'plugins_loaded', array( 'AODG_Gate', 'init' ), 1 );

if ( is_admin() ) {
	add_action( 'plugins_loaded', array( 'AODG_Admin', 'init' ) );
}
