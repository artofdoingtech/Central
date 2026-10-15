<?php
/**
 * Nettoyage à la désinstallation (bouton « Supprimer » depuis wp-admin, pas
 * une simple désactivation) : retire les options de licence.
 *
 * @package AOD_Gestion_License
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'aodg_license_key' );
delete_option( 'aodg_license_data' );
