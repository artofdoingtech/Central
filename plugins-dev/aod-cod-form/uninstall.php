<?php
/**
 * Désinstallation propre du plugin AOD COD Form.
 *
 * Exécuté par WordPress UNIQUEMENT à la suppression du plugin (bouton
 * « Supprimer » dans Extensions, après désactivation) — JAMAIS à la simple
 * désactivation ni pendant l'usage normal du site.
 *
 * Mode par défaut = PRUDENT : retire les réglages et les caches, mais CONSERVE
 * la table des prospects « {prefix}aod_cod_leads » (donnée commerciale : paniers
 * abandonnés). Pour une suppression TOTALE (table comprise), passer la variable
 * $aod_cod_drop_leads ci-dessous à true.
 *
 * @package AOD_COD_Form
 */

// Sécurité : ne s'exécute que dans le contexte de désinstallation WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * true  = supprimer AUSSI la table des prospects (table rase, irréversible).
 * false = conserver la table des prospects (recommandé par défaut).
 */
$aod_cod_drop_leads = false;

$aod_cod_cleanup = static function () use ( $aod_cod_drop_leads ) {
	global $wpdb;

	// 1) Options de réglage possédées par le plugin.
	$options = array(
		'aod_cod_pixels',          // pixels & tracking
		'aod_cod_whatsapp',        // notification WhatsApp (CallMeBot)
		'aod_cod_delivery_prices', // tarifs de livraison par wilaya
		'aod_cod_free_shipping',   // seuil de livraison gratuite
		'aod_shipping_auto',       // envoi automatique aux transporteurs
		// 🔒 CLÉ SECRÈTE (étape 25.1). Contient { site, secret } — `secret` est la
		// clé secrète Cloudflare Turnstile, saisie dans un champ `type="password"`.
		// Elle manquait à cette liste et n'était couverte par aucun motif LIKE
		// ci-dessous : elle survivait donc en clair dans wp_options à la suppression
		// du plugin, indéfiniment — y compris après revente du site. Un secret qu'on
		// croit avoir révoqué et qui ne l'est pas est pire qu'un secret oublié :
		// personne ne va le chercher. NE PAS retirer cette ligne.
		'aod_cod_turnstile',
		// Sauvegarde des tarifs, écrite avant tout import par API (étape 25.1).
		// L'option principale était supprimée, sa sauvegarde restait : le tableau
		// complet des 58 wilayas survivait sans que rien n'en connaisse l'existence.
		'aod_cod_delivery_prices_backup',
		// Panier COD togglable (Phase 35). Écrite par /gestion (dashboard), mais
		// POSSÉDÉE ici : c'est le comportement public de CE plugin qu'elle pilote
		// — même logique, en sens inverse, que `aod_seq_order_counter`.
		'aod_cod_cart',
	);
	foreach ( $options as $opt ) {
		delete_option( $opt );
	}

	// 2) Réglages des transporteurs : clés dynamiques « aod_carrier_{id} »
	//    (yalidine, noest, ecotrack… — supprimées par motif, sans énumérer).
	$like_carrier = $wpdb->esc_like( 'aod_carrier_' ) . '%';
	$wpdb->query(
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_carrier )
	);

	// 2bis) Verrous d'envoi « aod_ship_lock_{id} » (31.4). Éphémères — ils ne
	//    survivent normalement pas à la requête qui les pose — mais un processus
	//    tué pendant l'appel au transporteur en laisse un derrière lui. La leçon
	//    de la Phase 25 s'applique : on cadre le nettoyage par ce que le code
	//    CRÉE, pas par ce qu'on s'attend à trouver.
	//    ⚠️ Le préfixe est écrit EN DUR, pas via `AOD_Shipping::LOCK_PREFIX` :
	//    `uninstall.php` s'exécute sans charger aucune classe du plugin, et une
	//    référence de classe y provoquerait un fatal — pendant la désinstallation,
	//    c'est-à-dire au moment où l'utilisateur ne peut plus rien réparer. Les
	//    motifs voisins font pareil, pour la même raison.
	$like_lock = $wpdb->esc_like( 'aod_ship_lock_' ) . '%';
	$wpdb->query(
		$wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_lock )
	);

	// 3) Caches transitoires du plugin (ex. centres Yalidine par wilaya).
	//    Pur cache régénérable → suppression sûre.
	$like_tr  = $wpdb->esc_like( '_transient_aod_' ) . '%';
	$like_trt = $wpdb->esc_like( '_transient_timeout_aod_' ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$like_tr,
			$like_trt
		)
	);

	// 4) Table des prospects + marqueur de version de schéma.
	if ( $aod_cod_drop_leads ) {
		$table = $wpdb->prefix . 'aod_cod_leads';
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( 'aod_cod_leads_db_version' );
	}
	// Sinon (mode prudent) : on conserve « {prefix}aod_cod_leads » et son option
	// de version, pour préserver les prospects et permettre une réinstallation
	// transparente (le schéma sera reconnu tel quel).
};

// Prise en charge multisite : nettoyer chaque sous-site.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		$aod_cod_cleanup();
		restore_current_blog();
	}
} else {
	$aod_cod_cleanup();
}
