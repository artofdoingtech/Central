<?php
/**
 * Désinstallation propre du plugin AOD Client Dashboard.
 *
 * Exécuté par WordPress uniquement quand l'administrateur SUPPRIME le plugin
 * (pas à la simple désactivation). Retire le rôle « Gestion Boutique » et la
 * capability custom partout où elle a pu être posée, pour laisser le site net.
 *
 * NB : les réglages Pixels/WhatsApp (options « aod_cod_* ») ne sont PAS touchés
 * ici — ils appartiennent au plugin AOD COD Form, qui les possède et les édite.
 * Le dashboard n'en est qu'un éditeur front-end ; les supprimer casserait la
 * config du COD s'il reste actif. Ce nettoyage relève de l'uninstall du COD.
 *
 * @package AOD_Client_Dashboard
 */

// Sécurité : ne s'exécute que dans le contexte de désinstallation WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Valeurs en dur : les constantes du plugin (AOD_CD_ROLE / AOD_CD_CAP…) ne sont
// pas chargées dans le contexte uninstall. Miroir de aod-client-dashboard.php.
$aod_cd_role      = 'aod_shop_client';
$aod_cd_confirmer = 'aod_confirmateur';
$aod_cd_cap       = 'aod_use_dashboard';

/**
 * TOUTES les capabilities posées par le plugin : la capability d'accès + les 7
 * du confirmateur (miroir littéral de AOD_CD_Roles::CONFIRMER_CAPS).
 *
 * 🔒 Étape 25.1. Ces 7-là sont attribuées PAR UTILISATEUR (`WP_User::add_cap()`,
 * class-aod-cd-section-team.php:354 et :416), jamais au rôle — c'est le principe
 * des « droits à la carte ». Conséquence longtemps ignorée ici : ni
 * `remove_role()`, ni une boucle sur `$roles->role_objects` n'en retire la
 * moindre. Elles vivaient dans le usermeta `{prefix}capabilities` de chaque
 * compte, et SURVIVAIENT à la suppression du plugin.
 * → Un droit qu'on croit avoir révoqué et qui ne l'est pas : à la réinstallation,
 *   ou pour tout code lisant ces caps, les permissions redevenaient actives.
 */
$aod_cd_all_caps = array(
	'aod_use_dashboard',
	'aod_cap_confirm_orders',
	'aod_cap_tracking',
	'aod_cap_stock',
	'aod_cap_shipping_prices',
	'aod_cap_stats_confirm',
	'aod_cap_stats_delivery',
	'aod_cap_abandoned',
);

/**
 * Nettoie un site : retire les capabilities (rôles ET utilisateurs), les rôles,
 * puis les options et les caches du plugin.
 */
$aod_cd_cleanup = static function () use ( $aod_cd_role, $aod_cd_confirmer, $aod_cd_cap, $aod_cd_all_caps ) {
	global $wpdb;
	$roles = wp_roles();

	// 1) Retirer la capability custom de tout rôle qui l'aurait reçue
	//    (ex. administrateur/shop_manager à qui on l'aurait accordée).
	foreach ( $roles->role_objects as $role ) {
		if ( $role->has_cap( $aod_cd_cap ) ) {
			$role->remove_cap( $aod_cd_cap );
		}
	}

	// 2) Retirer les capabilities posées PAR UTILISATEUR, et nos deux rôles, de
	//    tout compte qui les porte (étape 25.1 — cf. $aod_cd_all_caps ci-dessus).
	//    On cible par le usermeta plutôt que par `get_users( array( 'role' => … ) )` :
	//    une cap du confirmateur est « à la carte », un compte peut donc la porter
	//    SANS avoir le rôle — filtrer par rôle en manquerait.
	//    `get_blog_prefix()` : en multisite la clé des capabilities est par site.
	$aod_cd_users = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
			$wpdb->get_blog_prefix() . 'capabilities',
			'%' . $wpdb->esc_like( 'aod_' ) . '%'
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Aucune API WP n'énumère les porteurs d'une cap ; requête unique, contexte uninstall.
	foreach ( $aod_cd_users as $aod_cd_uid ) {
		$aod_cd_user = new WP_User( (int) $aod_cd_uid );
		if ( ! $aod_cd_user->exists() ) {
			continue;
		}
		foreach ( $aod_cd_all_caps as $aod_cd_one_cap ) {
			$aod_cd_user->remove_cap( $aod_cd_one_cap );
		}
		// Les rôles du plugin restent inscrits sur le compte après remove_role() :
		// les retirer ici, sinon l'utilisateur garde un rôle qui n'existe plus.
		$aod_cd_user->remove_role( $aod_cd_role );
		$aod_cd_user->remove_role( $aod_cd_confirmer );
	}

	// 3) Supprimer les rôles du plugin (gérant + confirmateur).
	//    APRÈS l'étape 2 : on énumère les porteurs tant que tout est encore en place.
	remove_role( $aod_cd_role );
	remove_role( $aod_cd_confirmer );

	// 4) Options du plugin + transients d'agrégats (Accueil ET Stock).
	delete_option( 'aod_cd_roles_version' );
	delete_option( 'aod_cd_home_cache_ver' );
	// Compteurs d'invalidation par mois (31.6) — même nature que la ligne
	// ci-dessus : un état de cache, sans valeur métier, qui n'a rien à faire en
	// base après la désinstallation.
	delete_option( 'aod_cd_home_cache_buckets' );
	// Motif « aod_cd_% » et non « aod_cd_home_% » : la section Stock a repris le
	// même mécanisme de cache avec SON préfixe (`aod_cd_stock_`, étape 25.1) et
	// échappait au balayage. Un motif nommant une seule section dérive dès la
	// deuxième — celui-ci couvre le plugin entier. Pur cache régénérable.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aod_cd_%' OR option_name LIKE '_transient_timeout_aod_cd_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};

// User metas du plugin (globales, pas par site) : suivi de connexion des
// confirmateurs + périmètre produits (étapes 1.4/1.5).
foreach ( array( 'aod_cd_last_login', '_aod_products_limited', '_aod_assigned_products' ) as $aod_cd_meta ) {
	delete_metadata( 'user', 0, $aod_cd_meta, '', true );
}

/**
 * CONSERVÉ À DESSEIN — les term metas des catégories (`_aod_cat_home`,
 * `_aod_cat_home_order`, `_aod_cat_name_ar`).
 *
 * Étape 25.1 : ce n'était jusqu'ici ni un choix ni un oubli documenté, juste un
 * silence. C'est maintenant une décision écrite. Ces métas sont du CONTENU du
 * gérant (nom arabe d'une catégorie, mise en avant sur l'accueil), de même nature
 * que la table des prospects que le mode prudent du plugin COD préserve. Une
 * désinstallation peut être temporaire ; effacer le travail de traduction du
 * client parce qu'il a désactivé un plugin serait une perte nette, irréversible
 * et silencieuse. Elles sont inertes sans le plugin, et intactes s'il revient.
 *
 * Même raisonnement pour les post metas du catalogue (`_aod_landing*`,
 * `_aod_title_ar`, `_aod_selling_points*`, `_aod_options`, `_aod_offers`…), qui
 * relèvent de l'uninstall du plugin COD qui les possède.
 */

// Prise en charge multisite : nettoyer chaque sous-site.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		$aod_cd_cleanup();
		restore_current_blog();
	}
} else {
	$aod_cd_cleanup();
}
