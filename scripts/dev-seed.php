<?php
/**
 * Jeu de données de recette — DÉVELOPPEMENT LOCAL UNIQUEMENT.
 *
 * Charger :  docker compose exec -T wpcli wp eval-file /scripts/dev-seed.php
 * Purger  :  docker compose exec -T wpcli wp eval-file /scripts/dev-seed.php purge
 *
 * Tout ce qui est créé porte la méta `_aod_seed` = 1. La purge ne supprime QUE
 * ce qui porte cette marque : des données réelles créées à côté survivent.
 *
 * Refuse de s'exécuter si le site n'est pas sur localhost (garde-fou prod).
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'AOD_Carrier' ) ) {
	WP_CLI::error( 'WooCommerce et/ou aod-cod-form ne sont pas actifs.' );
}

$purge = isset( $args[0] ) && 'purge' === $args[0];

/* -------------------------------------------------------------------------
 * PURGE
 * ---------------------------------------------------------------------- */
if ( $purge ) {
	$n_o = 0;
	foreach ( wc_get_orders( array( 'limit' => -1, 'return' => 'ids', 'meta_key' => '_aod_seed', 'meta_value' => '1' ) ) as $oid ) {
		$o = wc_get_order( $oid );
		if ( $o ) {
			$o->delete( true );
			$n_o++;
		}
	}

	$n_p = 0;
	foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_aod_seed', 'meta_value' => '1' ) ) as $pid ) {
		wp_delete_post( $pid, true );
		$n_p++;
	}

	$n_t = 0;
	foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ) ) as $tid ) {
		if ( get_term_meta( $tid, '_aod_seed', true ) ) {
			wp_delete_term( $tid, 'product_cat' );
			$n_t++;
		}
	}

	$n_u = 0;
	foreach ( get_users( array( 'meta_key' => '_aod_seed', 'meta_value' => '1', 'fields' => 'ID' ) ) as $uid ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $uid );
		$n_u++;
	}

	if ( get_option( 'aod_seed_carrier' ) ) {
		delete_option( 'aod_carrier_yalidine' );
		delete_option( 'aod_seed_carrier' );
	}

	WP_CLI::success( "Purge : $n_o commandes, $n_p produits, $n_t catégories, $n_u utilisateurs, réglages transporteur factices supprimés." );
	return;
}

/* -------------------------------------------------------------------------
 * 1. Catégories
 * ---------------------------------------------------------------------- */
$cats = array();
foreach ( array( 'Électronique', 'Beauté', 'Mode' ) as $name ) {
	$t = term_exists( $name, 'product_cat' );
	if ( ! $t ) {
		$t = wp_insert_term( $name, 'product_cat' );
		add_term_meta( $t['term_id'], '_aod_seed', 1 );
	}
	$cats[ $name ] = (int) $t['term_id'];
}

/* -------------------------------------------------------------------------
 * 2. Produits
 *    stock : Casque(1) + Écouteurs(0) + Baskets(2) => 3 en « stock bas »
 *            (seuil Woo par défaut = 2). La crème ne gère pas le stock : exclue.
 * ---------------------------------------------------------------------- */
$specs = array(
	array( 'Montre connectée Aura', 4900, 3200, 25, true, 'Électronique' ),
	array( 'Casque Bluetooth Neo', 3200, 1900, 1, true, 'Électronique' ),
	array( 'Écouteurs Sport Pulse', 2400, 1400, 0, true, 'Électronique' ),
	array( 'Parfum Oud Royal', 6500, 4100, 12, true, 'Beauté' ),
	array( 'Crème Hydratante Bio', 2100, 1100, 0, false, 'Beauté' ),
	array( 'Baskets Urban Flex', 5400, 3300, 2, true, 'Mode' ),
);

$pids = array();
foreach ( $specs as $s ) {
	list( $name, $price, $cost, $stock, $manage, $cat ) = $s;
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_status( 'publish' );
	$p->set_regular_price( (string) $price );
	$p->set_price( (string) $price );
	$p->set_short_description( 'Produit de démonstration — jeu de recette.' );
	$p->set_description( 'Article créé par scripts/dev-seed.php pour la recette de l’espace /gestion.' );
	$p->set_category_ids( array( $cats[ $cat ] ) );
	$p->set_manage_stock( $manage );
	if ( $manage ) {
		$p->set_stock_quantity( $stock );
		$p->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );
	}
	$p->update_meta_data( '_aod_cost_price', (string) $cost );
	$p->update_meta_data( '_aod_seed', 1 );
	$pids[ $name ] = $p->save();
}

/* -------------------------------------------------------------------------
 * 3. Confirmateur au périmètre restreint (2 produits sur 6)
 * ---------------------------------------------------------------------- */
$conf_id = username_exists( 'confirmateur' );
if ( ! $conf_id ) {
	$conf_id = wp_insert_user( array(
		'user_login'   => 'confirmateur',
		'user_pass'    => 'confirm_dev_2026',
		'user_email'   => 'confirmateur@example.test',
		'first_name'   => 'Amina',
		'last_name'    => 'Belkacem',
		'display_name' => 'Amina Belkacem',
		'role'         => AOD_CD_ROLE_CONFIRMER,
	) );
}
$u = new WP_User( $conf_id );
$u->set_role( AOD_CD_ROLE_CONFIRMER );
$u->add_cap( 'aod_cap_confirm_orders' );
$u->add_cap( 'aod_cap_tracking' );
// La restriction n'existe que si _aod_products_limited est coché ET qu'au moins
// un produit est sélectionné (cf. AOD_CD_Roles::assigned_products()).
update_user_meta( $conf_id, '_aod_products_limited', 1 );
update_user_meta( $conf_id, '_aod_assigned_products', array( $pids['Casque Bluetooth Neo'], $pids['Écouteurs Sport Pulse'] ) );
update_user_meta( $conf_id, '_aod_seed', 1 );

/* -------------------------------------------------------------------------
 * 4. Commandes
 *    Période courante (J-0 → J-29) : gros volume, 4 annulées.
 *    Période précédente (J-30 → J-59) : volume plus faible, 1 annulée.
 *    => CA en hausse (delta VERT) et annulations en hausse (delta ROUGE).
 * ---------------------------------------------------------------------- */
$clients = array(
	array( 'Karim', 'Benali', '0551234567', 16, 'Bab Ezzouar', 'Cité 5 Juillet, Bt 12' ),
	array( 'Yasmine', 'Haddad', '0662345678', 31, 'Bir El Djir', 'Rue des Frères Bouzid, 4' ),
	array( 'Sofiane', 'Meziane', '0773456789', 25, 'Constantine', 'Av. Aouati Mostefa, 21' ),
	array( 'Lina', 'Cherif', '0554567890', 9, 'Blida', 'Cité Ben Boulaid, Bt C' ),
	array( 'Rachid', 'Boumediene', '0665678901', 6, 'Bejaia', 'Quartier Sidi Ahmed, 8' ),
	array( 'Nawal', 'Zerrouki', '0776789012', 19, 'Setif', 'Cité El Hidhab, Bt 7' ),
	array( 'Mehdi', 'Larbi', '0557890123', 16, 'Hydra', 'Rue Larbi Alik, 33' ),
	array( 'Sarah', 'Ould Ali', '0668901234', 31, 'Oran', 'Bd Millénium, 15' ),
);

$prod_names = array_keys( $pids );

// [ jours, statut, produit, qté, livraison, suivi(status|null), âge du suivi en jours ]
$orders = array(
	// ---- Période courante ----
	array( 0, 'pending', 'Montre connectée Aura', 1, 600, null, 0 ),
	array( 1, 'pending', 'Casque Bluetooth Neo', 2, 0, null, 0 ),        // LIVRAISON GRATUITE
	array( 2, 'pending', 'Parfum Oud Royal', 1, 800, null, 0 ),
	array( 3, 'pending', 'Baskets Urban Flex', 1, 600, null, 0 ),
	array( 5, 'pending', 'Écouteurs Sport Pulse', 3, 600, null, 0 ),
	array( 1, 'aod-confirmed', 'Montre connectée Aura', 2, 600, 'in_transit', 0 ),
	array( 3, 'aod-confirmed', 'Casque Bluetooth Neo', 1, 600, 'out_for_delivery', 0 ),
	array( 4, 'aod-confirmed', 'Parfum Oud Royal', 1, 800, 'failed', 1 ),       // → tuile « Échecs »
	array( 12, 'aod-confirmed', 'Écouteurs Sport Pulse', 2, 600, 'in_transit', 12 ), // → BLOQUÉ (> 7 j)
	array( 10, 'aod-confirmed', 'Baskets Urban Flex', 1, 600, 'created', 9 ),        // → BLOQUÉ (> 7 j)
	array( 6, 'completed', 'Montre connectée Aura', 1, 600, 'delivered', 2 ),
	array( 8, 'completed', 'Parfum Oud Royal', 2, 800, 'delivered', 4 ),
	array( 11, 'completed', 'Crème Hydratante Bio', 3, 600, 'delivered', 7 ),
	array( 14, 'completed', 'Casque Bluetooth Neo', 1, 600, 'delivered', 10 ),
	array( 17, 'completed', 'Baskets Urban Flex', 2, 600, 'delivered', 13 ),
	array( 20, 'completed', 'Montre connectée Aura', 1, 800, 'delivered', 16 ),
	array( 7, 'cancelled', 'Écouteurs Sport Pulse', 1, 600, null, 0 ),
	array( 13, 'cancelled', 'Parfum Oud Royal', 1, 600, null, 0 ),
	array( 19, 'cancelled', 'Crème Hydratante Bio', 2, 600, null, 0 ),
	array( 24, 'cancelled', 'Casque Bluetooth Neo', 1, 600, null, 0 ),
	// ---- Période précédente (J-30 → J-59) : moins de CA, 1 seule annulée ----
	array( 32, 'completed', 'Crème Hydratante Bio', 1, 600, 'delivered', 28 ),
	array( 35, 'completed', 'Écouteurs Sport Pulse', 1, 600, 'delivered', 31 ),
	array( 38, 'completed', 'Casque Bluetooth Neo', 1, 600, 'delivered', 34 ),
	array( 41, 'completed', 'Crème Hydratante Bio', 2, 600, 'returned', 37 ),
	array( 45, 'completed', 'Baskets Urban Flex', 1, 600, 'delivered', 41 ),
	array( 49, 'aod-confirmed', 'Montre connectée Aura', 1, 600, 'delivered', 45 ),
	array( 52, 'completed', 'Crème Hydratante Bio', 1, 600, 'delivered', 48 ),
	array( 55, 'cancelled', 'Parfum Oud Royal', 1, 600, null, 0 ),
	array( 58, 'pending', 'Crème Hydratante Bio', 1, 600, null, 0 ),
);

$labels = array(
	'created'          => 'Colis enregistré',
	'in_transit'       => 'En transit vers le centre',
	'out_for_delivery' => 'Sorti en livraison',
	'delivered'        => 'Livré au client',
	'failed'           => 'Tentative de livraison échouée',
	'returned'         => 'Retourné à l’expéditeur',
);

$made = 0;
foreach ( $orders as $i => $spec ) {
	list( $days, $status, $pname, $qty, $ship, $track, $track_age ) = $spec;
	$c  = $clients[ $i % count( $clients ) ];
	$ts = time() - ( $days * DAY_IN_SECONDS ) - ( ( $i % 9 ) * HOUR_IN_SECONDS );

	$o = new WC_Order();
	$o->set_billing_first_name( $c[0] );
	$o->set_billing_last_name( $c[1] );
	$o->set_billing_phone( $c[2] );
	$o->set_billing_address_1( $c[5] );
	$o->set_billing_city( $c[4] );
	$o->set_billing_country( 'DZ' );
	$o->set_currency( get_woocommerce_currency() );
	$o->add_product( wc_get_product( $pids[ $pname ] ), $qty );

	$item = new WC_Order_Item_Shipping();
	$item->set_method_title( $ship > 0 ? 'Livraison à domicile' : 'Livraison gratuite' );
	$item->set_total( (string) $ship );
	$o->add_item( $item );

	$o->update_meta_data( '_aod_wilaya_code', $c[3] );
	$o->update_meta_data( '_aod_wilaya_name', AOD_COD_Data::wilaya_name( $c[3] ) );
	$o->update_meta_data( '_aod_commune', $c[4] );
	$o->update_meta_data( '_aod_delivery_type', 'home' );
	$o->update_meta_data( '_aod_seed', 1 );

	// Traçabilité confirmation (onglet « Confirmation » de l'Accueil).
	if ( in_array( $status, array( 'aod-confirmed', 'completed' ), true ) ) {
		$o->update_meta_data( '_aod_confirmed_by', $conf_id );
		$o->update_meta_data( '_aod_confirmed_at', wp_date( 'Y-m-d H:i:s', $ts + ( 2 + ( $i % 5 ) ) * HOUR_IN_SECONDS ) );
	}

	// Colis + historique de suivi (timeline de la modale).
	if ( $track ) {
		$flow = array( 'created', 'in_transit', 'out_for_delivery' );
		if ( 'created' === $track ) {
			$flow = array( 'created' );
		} elseif ( 'in_transit' === $track ) {
			$flow = array( 'created', 'in_transit' );
		} elseif ( 'out_for_delivery' === $track ) {
			$flow = array( 'created', 'in_transit', 'out_for_delivery' );
		} else {
			$flow[] = $track; // delivered / failed / returned
		}
		$events = array();
		foreach ( $flow as $k => $st ) {
			$events[] = array(
				'status'   => $st,
				'label'    => $labels[ $st ],
				'date'     => wp_date( 'Y-m-d H:i', $ts + ( $k + 1 ) * 8 * HOUR_IN_SECONDS ),
				'location' => AOD_COD_Data::wilaya_name( $c[3] ),
			);
		}
		$o->update_meta_data( AOD_Carrier::META_CARRIER, 'yalidine' );
		$o->update_meta_data( AOD_Carrier::META_TRACKING, sprintf( 'yal-%06d', 100000 + $i * 137 ) );
		$o->update_meta_data( AOD_Carrier::META_STATUS, $track );
		$o->update_meta_data( AOD_Carrier::META_STATUS_LABEL, $labels[ $track ] );
		$o->update_meta_data( AOD_Carrier::META_STATUS_HISTORY, wp_json_encode( $events ) );
		$o->update_meta_data( AOD_Carrier::META_STATUS_AT, time() - ( $track_age * DAY_IN_SECONDS ) );
	}

	$o->calculate_totals( false );
	$o->set_date_created( $ts );
	$o->set_status( $status );
	if ( in_array( $status, array( 'completed', 'aod-confirmed' ), true ) ) {
		$o->set_date_paid( $ts );
	}
	$o->save();
	$made++;
}

/* -------------------------------------------------------------------------
 * 5. Transporteur factice — pour que le filtre « Transporteur » du Suivi
 *    propose au moins une entrée. Clés bidon : aucun appel API ne réussira,
 *    et l'envoi automatique reste désactivé.
 * ---------------------------------------------------------------------- */
if ( ! get_option( 'aod_carrier_yalidine' ) ) {
	update_option( 'aod_carrier_yalidine', array(
		'api_id'           => 'DEMO_ID',
		'api_token'        => 'DEMO_TOKEN',
		'from_wilaya_id'   => 16,
		'from_wilaya_name' => 'Alger',
		'weight'           => 1,
	) );
	update_option( 'aod_seed_carrier', 1 );
}

WP_CLI::success( "Jeu de recette chargé : $made commandes, " . count( $pids ) . ' produits, 3 catégories, 1 confirmateur (confirmateur / confirm_dev_2026).' );
WP_CLI::log( 'À traiter attendu → En attente : 6 · Échecs : 1 · Bloqués : 2 · Stock bas : 3' );
WP_CLI::log( 'Purger : wp eval-file /scripts/dev-seed.php purge' );
