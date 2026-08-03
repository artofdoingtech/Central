<?php
/**
 * Assignation des commandes aux confirmateurs (Phase 42.1) — le périmètre
 * existant filtre par PRODUIT (`assigned_products()`), jamais par COMMANDE :
 * deux confirmateurs affectés au même produit voient et peuvent agir sur les
 * MÊMES commandes. Ce cliquet couvre le premier des deux chemins
 * d'assignation automatique — le formulaire public (`aod_cod_order_created`).
 * L'autre chemin (conversion d'un abandon, Phase 39) est couvert par 42.2,
 * dans ce même fichier (groupe à venir) — PAS ici : `build_skeleton_order()`
 * ne déclenche pas ce hook, à dessein (cf. ROADMAP §A.19).
 *
 * Couvre :
 *   - AOD_CD_Roles::eligible_confirmers()      — vivier cumulé (actif + cap +
 *     périmètre produit), déterminisme (tri par ID).
 *   - AOD_CD_Section_Orders::count_active_assigned() — mesure de charge,
 *     exclut les statuts terminaux (completed/cancelled/refunded).
 *   - AOD_CD_Section_Orders::assign_least_loaded() — le tourniquet lui-même :
 *     charge égale → plus petit ID ; charge inégale → le moins chargé ;
 *     vivier vide → AUCUNE méta posée.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Contexte : socle + témoin d'identité
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.1 — préparation du contexte' );

$aod_ac_socle = class_exists( 'AOD_CD_Roles' ) && class_exists( 'AOD_CD_Section_Orders' ) && class_exists( 'WC_Order' );
AOD_Test::ok( $aod_ac_socle, 'Socle présent (AOD_CD_Roles + AOD_CD_Section_Orders + WooCommerce)' );

if ( ! $aod_ac_socle ) {
	return;
}

$aod_ac_user_avant = get_current_user_id();
$orders             = AOD_CD_Section_Orders::instance();

/**
 * `product_scope()`/`assignment_scope()` sont mémoïsées PAR INSTANCE — correct en
 * production (un process = une requête = un utilisateur), mais `$orders` est un
 * SINGLETON que ce fichier réutilise à travers plusieurs `wp_set_current_user()`
 * simulés : sans ce reset, le cache du premier utilisateur fuit sur les suivants.
 * Artefact de test uniquement — ne change rien à la mémoïsation réelle.
 */
$aod_ac_reset_scope_cache = function ( $obj ) {
	$ref = new ReflectionObject( $obj );
	foreach ( array( 'assignment_scope', 'product_scope' ) as $prop ) {
		if ( $ref->hasProperty( $prop ) ) {
			$rp = $ref->getProperty( $prop );
			$rp->setAccessible( true );
			$rp->setValue( $obj, null );
		}
	}
};

/** Fabrique un produit simple publié, renvoie son ID. */
$aod_ac_make_product = function ( $name ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_status( 'publish' );
	return $p->save();
};

/** Fabrique un confirmateur de test : actif, avec/sans cap, scope libre/restreint. */
$aod_ac_make_confirmer = function ( $login, $has_cap, $active, $scope_pids = array() ) {
	$uid = wp_insert_user( array(
		'user_login' => $login,
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => $login . '@example.invalid',
		'role'       => AOD_CD_ROLE_CONFIRMER,
	) );
	if ( is_wp_error( $uid ) ) {
		return 0;
	}
	$u = new WP_User( (int) $uid );
	if ( $has_cap ) {
		$u->add_cap( 'aod_cap_confirm_orders' );
	}
	if ( ! $active ) {
		$u->add_cap( AOD_CD_CAP, false ); // Surcharge : désactivé (patron Team::ajax_save_member()).
	}
	if ( $scope_pids ) {
		update_user_meta( $uid, '_aod_products_limited', 1 );
		update_user_meta( $uid, '_aod_assigned_products', array_map( 'absint', $scope_pids ) );
	}
	return (int) $uid;
};

/** Fabrique une commande à une ligne (produit $pid, qty 1), statut $status, éventuellement assignée. */
$aod_ac_make_order = function ( $pid, $status, $assigned_to = 0 ) {
	$order = new WC_Order();
	$item  = new WC_Order_Item_Product();
	$item->set_product( wc_get_product( $pid ) );
	$item->set_quantity( 1 );
	$order->add_item( $item );
	if ( $assigned_to ) {
		$order->update_meta_data( '_aod_assigned_confirmer', (int) $assigned_to );
	}
	$order->set_status( $status );
	$order->save();
	return $order->get_id();
};

/**
 * Supprime VRAIMENT une commande — jamais `wp_delete_post()` : ce site est en
 * HPOS (cf. TEMPLATE.md §3), où une commande n'est plus un post. `wp_delete_post()`
 * y détruit seulement le post-placeholder cosmétique de compatibilité ; la
 * VRAIE ligne dans `wp_wc_orders` survit, invisible du nettoyage, et
 * `wc_get_order()` continue de la trouver. Vérifié empiriquement : ce défaut
 * a laissé des dizaines de commandes fictives (facturation vide) en base à
 * chaque exécution de la suite, chacune encore marquée assignée à un
 * confirmateur de test lui-même déjà supprimé — la cause du mystère « Non
 * assignées = 0 » mais une colonne « Assigné à » qui affiche « — ».
 *
 * @param WC_Order|int $order_or_id
 */
$aod_ac_del_order = function ( $order_or_id ) {
	$o = ( $order_or_id instanceof WC_Order ) ? $order_or_id : wc_get_order( $order_or_id );
	if ( $o instanceof WC_Order ) {
		$o->delete( true );
	}
};

$pidX = $aod_ac_make_product( 'P42.1 produit X' );
$pidY = $aod_ac_make_product( 'P42.1 produit Y' );

$confA = $aod_ac_make_confirmer( 'aod_test_42_1_a', true, true );              // actif, cap, SANS restriction.
$confB = $aod_ac_make_confirmer( 'aod_test_42_1_b', true, true, array( $pidX ) ); // actif, cap, restreint à X.
$confC = $aod_ac_make_confirmer( 'aod_test_42_1_c', false, true );             // actif, SANS cap.
$confD = $aod_ac_make_confirmer( 'aod_test_42_1_d', true, false );             // cap, INACTIF.

AOD_Test::ok( $confA && $confB && $confC && $confD, 'Les 4 confirmateurs de test sont créés' );
AOD_Test::ok( $pidX && $pidY, 'Les 2 produits de test sont créés' );

wp_set_current_user( 0 );

/* =========================================================================
 * AOD_CD_Roles::eligible_confirmers() — vivier cumulé (AC2)
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.1 — eligible_confirmers() : filtres cumulés' );

$aod_ac_ids = function ( $users ) {
	return array_map( function ( $u ) {
		return $u->ID;
	}, $users );
};

$aod_ac_vide = $aod_ac_ids( AOD_CD_Roles::eligible_confirmers( array() ) );
AOD_Test::ok( in_array( $confA, $aod_ac_vide, true ), 'Liste vide de produits : le confirmateur SANS restriction est éligible' );
AOD_Test::ok( ! in_array( $confB, $aod_ac_vide, true ), 'Liste vide de produits : le confirmateur RESTREINT (à X) n’est PAS éligible' );
AOD_Test::ok( ! in_array( $confC, $aod_ac_vide, true ), '🔒 Sans aod_cap_confirm_orders : jamais éligible' );
AOD_Test::ok( ! in_array( $confD, $aod_ac_vide, true ), '🔒 Confirmateur INACTIF : jamais éligible' );

$aod_ac_avecX = $aod_ac_ids( AOD_CD_Roles::eligible_confirmers( array( $pidX ) ) );
AOD_Test::ok( in_array( $confA, $aod_ac_avecX, true ), 'Produit X dans la liste : le confirmateur sans restriction reste éligible' );
AOD_Test::ok( in_array( $confB, $aod_ac_avecX, true ), 'Produit X dans la liste : le confirmateur restreint à X devient éligible' );

$aod_ac_avecY = $aod_ac_ids( AOD_CD_Roles::eligible_confirmers( array( $pidY ) ) );
AOD_Test::ok( in_array( $confA, $aod_ac_avecY, true ), 'Produit Y (hors périmètre de B) : le confirmateur sans restriction reste éligible' );
AOD_Test::ok( ! in_array( $confB, $aod_ac_avecY, true ), '🔴 Produit Y (hors périmètre de B) : le confirmateur restreint à X n’est PAS éligible' );

// Déterminisme : trié par ID croissant.
$aod_ac_sorted = $aod_ac_avecX;
$aod_ac_copy   = $aod_ac_avecX;
sort( $aod_ac_copy );
AOD_Test::eq( $aod_ac_copy, $aod_ac_sorted, 'Le vivier est trié par ID croissant (déterminisme du choix à charge égale)' );

/* =========================================================================
 * AOD_CD_Section_Orders::count_active_assigned() — mesure de charge
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.1 — count_active_assigned() : exclut les statuts terminaux' );

$aod_ac_o1 = $aod_ac_make_order( $pidX, 'pending', $confA );
$aod_ac_o2 = $aod_ac_make_order( $pidX, 'processing', $confA );
$aod_ac_o3 = $aod_ac_make_order( $pidX, 'completed', $confA ); // terminal : ne doit PAS compter.
$aod_ac_o4 = $aod_ac_make_order( $pidX, 'cancelled', $confB ); // terminal : ne doit PAS compter.

try {
	AOD_Test::eq( 2, $orders->count_active_assigned( $confA ), 'confA : 2 commandes actives (pending + processing), completed exclue' );
	AOD_Test::eq( 0, $orders->count_active_assigned( $confB ), 'confB : 0 commande active (sa seule commande est cancelled)' );
	AOD_Test::eq( 0, $orders->count_active_assigned( $confC ), 'Un confirmateur sans commande assignée : charge nulle' );
} finally {
	foreach ( array( $aod_ac_o1, $aod_ac_o2, $aod_ac_o3, $aod_ac_o4 ) as $oid ) {
		$aod_ac_del_order( $oid );
	}
}

/* =========================================================================
 * AOD_CD_Section_Orders::assign_least_loaded() — le tourniquet
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.1 — assign_least_loaded() : charge égale, charge inégale, vivier vide' );

// Deux confirmateurs frais, SANS restriction, charge nulle au départ : l'ordre
// de création fixe l'ordre des ID (confE < confF).
$confE = $aod_ac_make_confirmer( 'aod_test_42_1_e', true, true );
$confF = $aod_ac_make_confirmer( 'aod_test_42_1_f', true, true );
AOD_Test::ok( $confE && $confF && $confE < $confF, 'confE et confF créés, confE a le plus petit ID' );

// Isolation du VIVIER (42.7) : TOUS les AUTRES confirmateurs existants (réels
// ou de test — un site de dev peut en porter, cf. ROADMAP Phase 42.7) sont
// temporairement désactivés le temps des 3 scénarios ci-dessous. Sans ça, un
// confirmateur réel d'ID plus petit que confE, à charge égale (0), gagnerait
// le tirage à la place de confE — le test resterait juste par CHANCE plutôt
// que par construction. Restauré EXACTEMENT à l'identique en `finally` (patron
// test-cap-portee.php — un test n'a pas le droit de perdre une vraie config).
$aod_ac_tous       = get_users( array( 'role' => AOD_CD_ROLE_CONFIRMER ) );
$aod_ac_etat_avant = array();
foreach ( $aod_ac_tous as $u ) {
	if ( $u->ID === $confE || $u->ID === $confF ) {
		continue;
	}
	$aod_ac_etat_avant[ $u->ID ] = $u->has_cap( AOD_CD_CAP );
	if ( $aod_ac_etat_avant[ $u->ID ] ) {
		$u->add_cap( AOD_CD_CAP, false );
	}
}

try {
	// 1) Charge égale (0 partout) → le plus petit ID (confE) est choisi.
	$aod_ac_new1  = new WC_Order();
	$aod_ac_item1 = new WC_Order_Item_Product();
	$aod_ac_item1->set_product( wc_get_product( $pidY ) );
	$aod_ac_item1->set_quantity( 1 );
	$aod_ac_new1->add_item( $aod_ac_item1 );
	$aod_ac_new1->save();
	try {
		$orders->assign_least_loaded( $aod_ac_new1 );
		$aod_ac_new1 = wc_get_order( $aod_ac_new1->get_id() );
		AOD_Test::eq(
			(string) $confE,
			(string) $aod_ac_new1->get_meta( '_aod_assigned_confirmer' ),
			'Charge égale (0 vs 0) : le plus petit ID (confE) est choisi'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_new1 );
	}

	// 2) Charge inégale : confF porte déjà une commande active → confE (0) doit être choisi.
	$aod_ac_load  = $aod_ac_make_order( $pidY, 'processing', $confF );
	$aod_ac_new2  = new WC_Order();
	$aod_ac_item2 = new WC_Order_Item_Product();
	$aod_ac_item2->set_product( wc_get_product( $pidY ) );
	$aod_ac_item2->set_quantity( 1 );
	$aod_ac_new2->add_item( $aod_ac_item2 );
	$aod_ac_new2->save();
	try {
		$orders->assign_least_loaded( $aod_ac_new2 );
		$aod_ac_new2 = wc_get_order( $aod_ac_new2->get_id() );
		AOD_Test::eq(
			(string) $confE,
			(string) $aod_ac_new2->get_meta( '_aod_assigned_confirmer' ),
			'🔴 Charge inégale (confF a 1 commande active) : confE (0) est choisi, pas confF'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_new2 );
		$aod_ac_del_order( $aod_ac_load );
	}

	// 3) Vivier vide : confE et confF sont désactivés à leur tour (les seuls
	// encore actifs à ce stade), on prouve qu'aucune méta n'est posée.
	( new WP_User( $confE ) )->add_cap( AOD_CD_CAP, false );
	( new WP_User( $confF ) )->add_cap( AOD_CD_CAP, false );

	$aod_ac_new3  = new WC_Order();
	$aod_ac_item3 = new WC_Order_Item_Product();
	$aod_ac_item3->set_product( wc_get_product( $pidY ) );
	$aod_ac_item3->set_quantity( 1 );
	$aod_ac_new3->add_item( $aod_ac_item3 );
	$aod_ac_new3->save();
	try {
		AOD_Test::eq( array(), AOD_CD_Roles::eligible_confirmers( array( $pidY ) ), 'Témoin : le vivier est bien VIDE une fois tout le monde désactivé' );

		$orders->assign_least_loaded( $aod_ac_new3 );
		$aod_ac_new3 = wc_get_order( $aod_ac_new3->get_id() );
		AOD_Test::eq(
			'',
			(string) $aod_ac_new3->get_meta( '_aod_assigned_confirmer' ),
			'🔴 Vivier vide : AUCUNE méta posée (commande non assignée, comme avant la Phase 42)'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_new3 );
	}
} finally {
	foreach ( $aod_ac_tous as $u ) {
		if ( isset( $aod_ac_etat_avant[ $u->ID ] ) && $aod_ac_etat_avant[ $u->ID ] ) {
			$u->remove_cap( AOD_CD_CAP ); // Retire la surcharge false → retombe sur le grant du rôle.
		}
	}
}

/* =========================================================================
 * AOD_CD_Section_Abandoned::maybe_assign_converter() — assignation DIRECTE
 * à la conversion d'un abandon (Phase 42.2), jamais le tourniquet.
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.2 — maybe_assign_converter() : direct, jamais le tourniquet' );

$aod_ac_abandoned_ok = class_exists( 'AOD_CD_Section_Abandoned' ) && method_exists( 'AOD_CD_Section_Abandoned', 'maybe_assign_converter' );
AOD_Test::ok( $aod_ac_abandoned_ok, 'AOD_CD_Section_Abandoned::maybe_assign_converter() existe' );

if ( $aod_ac_abandoned_ok ) {
	$aod_ac_section = AOD_CD_Section_Abandoned::instance();

	// 1) Un VRAI confirmateur convertit : la commande lui est assignée.
	wp_set_current_user( $confA );
	$aod_ac_conv1 = new WC_Order();
	$aod_ac_conv1->save();
	try {
		AOD_Test::call( $aod_ac_section, 'maybe_assign_converter', array( $aod_ac_conv1 ) );
		$aod_ac_conv1 = wc_get_order( $aod_ac_conv1->get_id() );
		AOD_Test::eq(
			(string) $confA,
			(string) $aod_ac_conv1->get_meta( '_aod_assigned_confirmer' ),
			'Confirmateur réel : la commande convertie lui est assignée DIRECTEMENT'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_conv1 );
	}

	// 2) Le GÉRANT convertit : aucune assignation (il n'est pas un confirmateur).
	$aod_ac_gerants = get_users( array( 'role__in' => array( 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );
	if ( $aod_ac_gerants ) {
		wp_set_current_user( (int) $aod_ac_gerants[0] );
		AOD_Test::ok( current_user_can( 'manage_woocommerce' ), 'Témoin : un gérant est endossé' );

		$aod_ac_conv2 = new WC_Order();
		$aod_ac_conv2->save();
		try {
			AOD_Test::call( $aod_ac_section, 'maybe_assign_converter', array( $aod_ac_conv2 ) );
			$aod_ac_conv2 = wc_get_order( $aod_ac_conv2->get_id() );
			AOD_Test::eq(
				'',
				(string) $aod_ac_conv2->get_meta( '_aod_assigned_confirmer' ),
				'🔴 Gérant : AUCUNE assignation (il n’est pas un confirmateur — le tourniquet 42.1 reste hors de ce chemin)'
			);
		} finally {
			$aod_ac_del_order( $aod_ac_conv2 );
		}
	} else {
		AOD_Test::ok( false, 'Aucun administrateur trouvé : le cas gérant n’a pas pu être joué' );
	}
} else {
	AOD_Test::ok( false, 'maybe_assign_converter() introuvable : le groupe 42.2 n’a pas pu être joué' );
}

wp_set_current_user( 0 );

/* =========================================================================
 * scoped_orders() / order_in_scope() — cumul en lecture (Phase 42.3)
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.3 — scoped_orders()/order_in_scope() : cumul' );

// confA : actif, cap, SANS restriction produit.
// confB : actif, cap, restreint à pidX.
// confC : actif, SANS aod_cap_confirm_orders (ex. tracking/stock seul), sans restriction.
$aod_ac_assigned = $aod_ac_make_order( $pidX, 'processing', $confA );
$aod_ac_unassigned = $aod_ac_make_order( $pidX, 'processing' ); // Jamais assignée.

try {
	// 1) confA (assignataire) : voit et peut agir sur SA commande.
	wp_set_current_user( $confA );
	$aod_ac_reset_scope_cache( $orders );
	$aod_ac_ids_a = (array) $orders->scoped_orders( array( 'type' => 'shop_order', 'limit' => -1, 'return' => 'ids' ) );
	AOD_Test::ok( in_array( $aod_ac_assigned, $aod_ac_ids_a, true ), 'confA (assignataire) : voit SA commande assignée' );
	AOD_Test::ok( ! in_array( $aod_ac_unassigned, $aod_ac_ids_a, true ), '🔴 confA : la commande NON assignée reste invisible' );
	AOD_Test::ok( $orders->order_in_scope( wc_get_order( $aod_ac_assigned ) ), 'confA : order_in_scope() = true sur sa commande' );

	// 2) confB (même produit X dans son périmètre, mais PAS l'assignataire) : ne voit PAS.
	wp_set_current_user( $confB );
	$aod_ac_reset_scope_cache( $orders );
	$aod_ac_ids_b = (array) $orders->scoped_orders( array( 'type' => 'shop_order', 'limit' => -1, 'return' => 'ids' ) );
	AOD_Test::ok( ! in_array( $aod_ac_assigned, $aod_ac_ids_b, true ), '🔴 confB : périmètre produit couvre X, mais la commande n’est PAS assignée à lui → invisible' );
	AOD_Test::ok( ! in_array( $aod_ac_unassigned, $aod_ac_ids_b, true ), 'confB : la commande NON assignée reste invisible aussi' );
	AOD_Test::ok( ! $orders->order_in_scope( wc_get_order( $aod_ac_assigned ) ), '🔴 confB : order_in_scope() = false (défense en profondeur, même périmètre produit)' );

	// 3) confC (SANS aod_cap_confirm_orders) : comportement INCHANGÉ (périmètre produit
	// seul, non filtré par l'assignation — sinon Suivi se viderait à tort pour ce rôle).
	wp_set_current_user( $confC );
	$aod_ac_reset_scope_cache( $orders );
	$aod_ac_ids_c = (array) $orders->scoped_orders( array( 'type' => 'shop_order', 'limit' => -1, 'return' => 'ids' ) );
	AOD_Test::ok( in_array( $aod_ac_assigned, $aod_ac_ids_c, true ), 'confC (sans aod_cap_confirm_orders) : voit la commande assignée à un AUTRE (non filtré)' );
	AOD_Test::ok( in_array( $aod_ac_unassigned, $aod_ac_ids_c, true ), 'confC : voit aussi la commande non assignée (comportement d’avant la Phase 42)' );

	// 4) Gérant : voit tout.
	$aod_ac_gerants2 = get_users( array( 'role__in' => array( 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );
	if ( $aod_ac_gerants2 ) {
		wp_set_current_user( (int) $aod_ac_gerants2[0] );
		$aod_ac_reset_scope_cache( $orders );
		$aod_ac_ids_g = (array) $orders->scoped_orders( array( 'type' => 'shop_order', 'limit' => -1, 'return' => 'ids' ) );
		AOD_Test::ok( in_array( $aod_ac_assigned, $aod_ac_ids_g, true ) && in_array( $aod_ac_unassigned, $aod_ac_ids_g, true ), 'Gérant : voit les deux commandes (assignée et non assignée)' );
	}
} finally {
	$aod_ac_del_order( $aod_ac_assigned );
	$aod_ac_del_order( $aod_ac_unassigned );
}

wp_set_current_user( 0 );

/* =========================================================================
 * eligible_confirmers( …, $ignore_scope ) + apply_assignment() — sélecteur
 * gérant (Phase 42.4)
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.4 — eligible_confirmers( $ignore_scope ) : périmètre ignoré, actif/cap toujours exigés' );

// confB est restreint à pidX (créé en 42.1). Sans le flag, il n'apparaît que
// si pidX est dans la liste ; AVEC le flag, il apparaît même pour un produit
// hors de son périmètre (pidY) — SEUL le filtre périmètre est sauté.
$aod_ac_ids_ign = array_map( function ( $u ) { return $u->ID; }, AOD_CD_Roles::eligible_confirmers( array( $pidY ), true ) );
AOD_Test::ok( in_array( $confB, $aod_ac_ids_ign, true ), '$ignore_scope=true : confB (restreint à X) apparaît même pour le produit Y' );
AOD_Test::ok( in_array( $confA, $aod_ac_ids_ign, true ), '$ignore_scope=true : confA (sans restriction) apparaît toujours' );
AOD_Test::ok( ! in_array( $confC, $aod_ac_ids_ign, true ), '🔒 $ignore_scope=true : confC (SANS aod_cap_confirm_orders) reste exclu — seul le périmètre est ignoré' );
AOD_Test::ok( ! in_array( $confD, $aod_ac_ids_ign, true ), '🔒 $ignore_scope=true : confD (INACTIF) reste exclu' );

// Témoin inverse : SANS le flag, confB reste exclu pour le produit Y (comportement 42.1 inchangé).
$aod_ac_ids_noign = array_map( function ( $u ) { return $u->ID; }, AOD_CD_Roles::eligible_confirmers( array( $pidY ) ) );
AOD_Test::ok( ! in_array( $confB, $aod_ac_ids_noign, true ), 'Témoin : SANS le flag, confB reste exclu pour Y (42.1 inchangé)' );

AOD_Test::group( 'Assignation 42.4 — apply_assignment() : confiance zéro + dé-assignation' );

$aod_ac_o42_4 = $aod_ac_make_order( $pidX, 'aod-confirmed', $confA );
try {
	// 1) Assignation valide (confB, actif + cap).
	$aod_ac_r1 = AOD_Test::call( $orders, 'apply_assignment', array( wc_get_order( $aod_ac_o42_4 ), $confB ) );
	AOD_Test::ok( $aod_ac_r1['ok'], 'Assignation à confB (valide) : ok' );
	AOD_Test::eq( (string) $confB, (string) wc_get_order( $aod_ac_o42_4 )->get_meta( '_aod_assigned_confirmer' ), 'La méta porte bien confB' );

	// 2) Réassignable à TOUT statut (la commande est aod-confirmed, pas un verrou).
	$aod_ac_r2 = AOD_Test::call( $orders, 'apply_assignment', array( wc_get_order( $aod_ac_o42_4 ), $confA ) );
	AOD_Test::ok( $aod_ac_r2['ok'], '🔴 Réassignation à confA sur une commande DÉJÀ CONFIRMÉE : ok (routage, pas un verrou)' );

	// 3) confirmer_id forgé : ID d'un compte qui n'est PAS un confirmateur (ex. confC sans cap, ou un admin).
	$aod_ac_r3 = AOD_Test::call( $orders, 'apply_assignment', array( wc_get_order( $aod_ac_o42_4 ), $confC ) );
	AOD_Test::ok( ! $aod_ac_r3['ok'], '🔒 confirmer_id forgé (confC, sans aod_cap_confirm_orders) : refusé' );
	AOD_Test::eq(
		(string) $confA,
		(string) wc_get_order( $aod_ac_o42_4 )->get_meta( '_aod_assigned_confirmer' ),
		'L’assignation précédente (confA) n’a PAS été altérée par le refus'
	);

	// 4) confirmer_id = 0 : dé-assignation — méta SUPPRIMÉE, jamais '0'.
	$aod_ac_r4 = AOD_Test::call( $orders, 'apply_assignment', array( wc_get_order( $aod_ac_o42_4 ), 0 ) );
	AOD_Test::ok( $aod_ac_r4['ok'], 'Dé-assignation (confirmer_id=0) : ok' );
	AOD_Test::eq( '', (string) wc_get_order( $aod_ac_o42_4 )->get_meta( '_aod_assigned_confirmer' ), '🔴 Méta SUPPRIMÉE (absence), jamais la chaîne \'0\'' );
} finally {
	$aod_ac_del_order( $aod_ac_o42_4 );
}

/* =========================================================================
 * bulk_assign() + count_unassigned() — rattrapage groupé (Phase 42.5)
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.5 — bulk_assign() : D4-rattrapage (jamais d’écrasement)' );

$aod_ac_bulk_o1 = $aod_ac_make_order( $pidX, 'pending' );          // Non assignée.
$aod_ac_bulk_o2 = $aod_ac_make_order( $pidX, 'pending' );          // Non assignée.
$aod_ac_bulk_o3 = $aod_ac_make_order( $pidX, 'pending', $confB );  // DÉJÀ assignée à confB.

try {
	$aod_ac_bulk_res = AOD_Test::call(
		$orders,
		'bulk_assign',
		array( array( $aod_ac_bulk_o1, $aod_ac_bulk_o2, $aod_ac_bulk_o3, 999999999 ), $confA )
	);
	AOD_Test::eq( 2, count( $aod_ac_bulk_res['assigned'] ), 'bulk_assign() : 2 commandes assignées (les 2 non assignées)' );
	AOD_Test::ok( in_array( $aod_ac_bulk_o1, $aod_ac_bulk_res['assigned'], true ) && in_array( $aod_ac_bulk_o2, $aod_ac_bulk_res['assigned'], true ), 'Ce sont bien o1 et o2' );
	AOD_Test::eq( 1, $aod_ac_bulk_res['skipped'], '🔴 o3 (déjà assignée à confB) : SAUTÉE, comptée séparément' );
	AOD_Test::eq( 1, $aod_ac_bulk_res['missing'], 'ID inexistant (999999999) : compté « introuvable », pas une erreur fatale' );

	AOD_Test::eq( (string) $confA, (string) wc_get_order( $aod_ac_bulk_o1 )->get_meta( '_aod_assigned_confirmer' ), 'o1 porte bien confA' );
	AOD_Test::eq( (string) $confA, (string) wc_get_order( $aod_ac_bulk_o2 )->get_meta( '_aod_assigned_confirmer' ), 'o2 porte bien confA' );
	AOD_Test::eq(
		(string) $confB,
		(string) wc_get_order( $aod_ac_bulk_o3 )->get_meta( '_aod_assigned_confirmer' ),
		'🔴 o3 : son assignation d’ORIGINE (confB) n’a PAS été écrasée par le lot'
	);
} finally {
	$aod_ac_del_order( $aod_ac_bulk_o1 );
	$aod_ac_del_order( $aod_ac_bulk_o2 );
	$aod_ac_del_order( $aod_ac_bulk_o3 );
}

AOD_Test::group( 'Assignation 42.5 — count_unassigned() : mesure du filtre « Non assignées »' );

$aod_ac_cu_before = AOD_Test::call( $orders, 'count_unassigned', array( array( 'wc-on-hold' ) ) );
$aod_ac_cu_o1     = $aod_ac_make_order( $pidX, 'on-hold' );          // Non assignée.
$aod_ac_cu_o2     = $aod_ac_make_order( $pidX, 'on-hold', $confA );  // Assignée.
try {
	$aod_ac_cu_after = AOD_Test::call( $orders, 'count_unassigned', array( array( 'wc-on-hold' ) ) );
	AOD_Test::eq( $aod_ac_cu_before + 1, $aod_ac_cu_after, 'count_unassigned() : +1 exactement (la commande assignée ne compte pas)' );
} finally {
	$aod_ac_del_order( $aod_ac_cu_o1 );
	$aod_ac_del_order( $aod_ac_cu_o2 );
}

/* =========================================================================
 * `_aod_assigned_by` — traçabilité de l'ORIGINE d'une assignation (Phase 43.4)
 *
 * 🚨 CE QUE CE GROUPE GARDE, ET QUE LA RELECTURE NE VOIT PAS. L'information
 * utile au gérant n'est pas la présence de la signature : c'est son ABSENCE.
 * Une méta posée en trop (par le tourniquet, ou survivant à une
 * dé-assignation) ne casse rien, n'échoue nulle part, et fait passer une
 * assignation automatique pour un choix délibéré du gérant. D'où un groupe où
 * la MOITIÉ des assertions vérifient qu'on n'a RIEN écrit.
 * ========================================================================= */

AOD_Test::group( 'Assignation 43.4 — `_aod_assigned_by` : les points d’écriture' );

$aod_ac_gerant = wp_insert_user( array(
	'user_login' => 'aod_test_43_4_gerant',
	'user_pass'  => wp_generate_password( 14 ),
	'role'       => AOD_CD_ROLE,
) );
AOD_Test::ok( $aod_ac_gerant && ! is_wp_error( $aod_ac_gerant ), 'Un compte GÉRANT de test est créé (porteur de manage_woocommerce)' );
$aod_ac_gerant = (int) $aod_ac_gerant;

try {
	/* --- Point 1 : le tourniquet ne signe pas ------------------------- */
	// Pire cas VOLONTAIRE : un humain est connecté pendant l'exécution du
	// tourniquet. S'appuyer sur « get_current_user_id() vaut 0 sur le chemin
	// public » ferait passer ce test pour la mauvaise raison.
	wp_set_current_user( $aod_ac_gerant );
	$aod_ac_o434_1 = $aod_ac_make_order( $pidX, 'processing' );
	try {
		$orders->assign_least_loaded( wc_get_order( $aod_ac_o434_1 ) );
		AOD_Test::ok(
			(int) wc_get_order( $aod_ac_o434_1 )->get_meta( '_aod_assigned_confirmer' ) > 0,
			'ANCRAGE : assign_least_loaded() a bien assigné (sans quoi la suite serait vraie par vacuité)'
		);
		AOD_Test::eq(
			'',
			(string) wc_get_order( $aod_ac_o434_1 )->get_meta( '_aod_assigned_by' ),
			'🔴 Le tourniquet ne signe PAS — même avec un gérant connecté (l’absence EST l’information)'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_o434_1 );
	}

	/* --- Points 2 / 2bis / 3 : les trois branches d'apply_assignment() -- */
	wp_set_current_user( $aod_ac_gerant );
	$aod_ac_o434_2 = $aod_ac_make_order( $pidX, 'pending' );
	try {
		AOD_Test::call( $orders, 'apply_assignment', array( wc_get_order( $aod_ac_o434_2 ), $confA ) );
		AOD_Test::eq(
			(string) $aod_ac_gerant,
			(string) wc_get_order( $aod_ac_o434_2 )->get_meta( '_aod_assigned_by' ),
			'Point 2 — branche écriture : _aod_assigned_by = l’utilisateur courant'
		);

		// Branche REFUS : elle sort avant toute écriture. Le cliquet 42.4 ne
		// gardait que l'assignation ; sans ce jumeau, une signature écrite avant
		// la validation passerait inaperçue.
		$aod_ac_r434 = AOD_Test::call( $orders, 'apply_assignment', array( wc_get_order( $aod_ac_o434_2 ), $confC ) );
		AOD_Test::ok( ! $aod_ac_r434['ok'], 'Point 2bis — ANCRAGE : un confirmer_id forgé est bien refusé' );
		AOD_Test::eq(
			(string) $aod_ac_gerant,
			(string) wc_get_order( $aod_ac_o434_2 )->get_meta( '_aod_assigned_by' ),
			'🔴 Point 2bis — le refus n’écrit RIEN : la signature précédente est intacte'
		);

		// Branche SUPPRESSION : les deux métas partent ensemble.
		AOD_Test::call( $orders, 'apply_assignment', array( wc_get_order( $aod_ac_o434_2 ), 0 ) );
		AOD_Test::eq(
			'',
			(string) wc_get_order( $aod_ac_o434_2 )->get_meta( '_aod_assigned_by' ),
			'🔴 Point 3 — la dé-assignation efface AUSSI la signature (jamais de méta orpheline)'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_o434_2 );
	}

	/* --- Point 3bis : la chaîne réelle 42.7 → tourniquet --------------- */
	// C'est le scénario qui rend le point 3 obligatoire plutôt que cosmétique :
	// `unassign_confirmer()` (suppression d'un confirmateur) délègue à
	// `apply_assignment( $order, 0 )`. Sans effacement, la commande repart au
	// tourniquet en gardant la signature du gérant — et affiche « Admin » sur
	// une assignation que PERSONNE n'a choisie.
	wp_set_current_user( $aod_ac_gerant );
	$aod_ac_o434_3 = $aod_ac_make_order( $pidX, 'pending' );
	try {
		AOD_Test::call( $orders, 'apply_assignment', array( wc_get_order( $aod_ac_o434_3 ), $confA ) );
		AOD_Test::ok(
			'' !== (string) wc_get_order( $aod_ac_o434_3 )->get_meta( '_aod_assigned_by' ),
			'ANCRAGE : la commande est signée avant la libération'
		);
		$orders->unassign_confirmer( $confA );
		AOD_Test::eq(
			'',
			(string) wc_get_order( $aod_ac_o434_3 )->get_meta( '_aod_assigned_by' ),
			'🔴 Point 3bis — après `unassign_confirmer()` (42.7), aucune signature ne survit'
		);
		$orders->assign_least_loaded( wc_get_order( $aod_ac_o434_3 ) );
		AOD_Test::eq(
			'',
			(string) wc_get_order( $aod_ac_o434_3 )->get_meta( '_aod_assigned_by' ),
			'🔴 …et la réassignation par le tourniquet reste NON signée'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_o434_3 );
	}

	/* --- Point 4 : rattrapage groupé ----------------------------------- */
	wp_set_current_user( $aod_ac_gerant );
	$aod_ac_b434_1 = $aod_ac_make_order( $pidX, 'pending' );          // libre → assignée
	$aod_ac_b434_2 = $aod_ac_make_order( $pidX, 'pending', $confB );  // déjà assignée → sautée
	try {
		$aod_ac_bres = AOD_Test::call( $orders, 'bulk_assign', array( array( $aod_ac_b434_1, $aod_ac_b434_2 ), $confA ) );
		AOD_Test::eq( 1, count( $aod_ac_bres['assigned'] ), 'ANCRAGE : compteurs 42.5 inchangés (1 assignée)' );
		AOD_Test::eq( 1, $aod_ac_bres['skipped'], 'ANCRAGE : 1 sautée' );
		AOD_Test::eq(
			(string) $aod_ac_gerant,
			(string) wc_get_order( $aod_ac_b434_1 )->get_meta( '_aod_assigned_by' ),
			'Point 4 — la commande RÉELLEMENT assignée est signée'
		);
		AOD_Test::eq(
			'',
			(string) wc_get_order( $aod_ac_b434_2 )->get_meta( '_aod_assigned_by' ),
			'🔴 Point 4 — la commande SAUTÉE ne reçoit aucune signature (elle n’a pas été assignée ici)'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_b434_1 );
		$aod_ac_del_order( $aod_ac_b434_2 );
	}

	/* --- AC 9 : le rendu ---------------------------------------------- */
	AOD_Test::group( 'Assignation 43.4 — confirmer_label_html() : l’origine, jamais l’identité' );

	$aod_ac_nomA = get_user_by( 'id', $confA )->display_name;

	// NON-RÉGRESSION. Cette chaîne n'était assertée NULLE PART avant la 43.4 :
	// la baseline est créée ici, pas héritée.
	AOD_Test::eq(
		'<span class="aod-cd-assigned-name">' . esc_html( $aod_ac_nomA ) . '</span>',
		AOD_CD_Roles::confirmer_label_html( $confA ),
		'🔴 Appel à UN argument : sortie strictement identique à celle d’avant la 43.4'
	);
	AOD_Test::eq(
		'<span class="aod-cd-muted">—</span>',
		AOD_CD_Roles::confirmer_label_html( 0 ),
		'Non assignée : tiret sourd'
	);
	AOD_Test::eq(
		'<span class="aod-cd-muted">—</span>',
		AOD_CD_Roles::confirmer_label_html( 0, $aod_ac_gerant ),
		'🔴 Signature SANS assignation : tiret sourd quand même (incohérence, pas un état)'
	);
	AOD_Test::eq(
		'<span class="aod-cd-muted">—</span>',
		AOD_CD_Roles::confirmer_label_html( 999999999, $aod_ac_gerant ),
		'Assigné supprimé depuis : tiret sourd'
	);

	$aod_ac_lbl_adm = AOD_CD_Roles::confirmer_label_html( $confA, $aod_ac_gerant );
	AOD_Test::ok(
		false !== strpos( $aod_ac_lbl_adm, 'aod-cd-assigned-by' ),
		'Assignation posée par un GÉRANT : le fragment « Admin » apparaît'
	);
	AOD_Test::ok(
		false !== strpos( $aod_ac_lbl_adm, esc_html( $aod_ac_nomA ) ),
		'ANCRAGE : le nom de l’assigné est toujours là (le fragment annote, il ne remplace pas)'
	);
	AOD_Test::ok(
		false === strpos( $aod_ac_lbl_adm, get_user_by( 'id', $aod_ac_gerant )->display_name ),
		'🔴 Le display_name de l’ASSIGNANT n’apparaît JAMAIS (A.20, Constat 3) — le libellé est littéral'
	);
	AOD_Test::ok(
		false === strpos( AOD_CD_Roles::confirmer_label_html( $confA, $confB ), 'aod-cd-assigned-by' ),
		'Assignation posée par un CONFIRMATEUR (conversion) : aucun fragment'
	);
	AOD_Test::ok(
		false === strpos( AOD_CD_Roles::confirmer_label_html( $confA, 999999999 ), 'aod-cd-assigned-by' ),
		'Assignant supprimé depuis : aucun fragment (dégradation assumée)'
	);

	// Compte RÉTROGRADÉ — atteignable depuis la section Équipe, contrairement à
	// la suppression. Le DENY utilisateur est le geste réel de l'Équipe : un
	// simple `remove_cap()` ne bat pas une cap accordée par le RÔLE (leçon 43.3).
	$aod_ac_g_dem = new WP_User( $aod_ac_gerant );
	$aod_ac_g_dem->add_cap( 'manage_woocommerce', false );
	AOD_Test::ok(
		false === strpos( AOD_CD_Roles::confirmer_label_html( $confA, $aod_ac_gerant ), 'aod-cd-assigned-by' ),
		'🔴 Assignant RÉTROGRADÉ : le fragment s’efface sans erreur (la commande se relit « automatique »)'
	);
	$aod_ac_g_dem->remove_cap( 'manage_woocommerce' );
	AOD_Test::ok(
		false !== strpos( AOD_CD_Roles::confirmer_label_html( $confA, $aod_ac_gerant ), 'aod-cd-assigned-by' ),
		'ANCRAGE INVERSE : le DENY retiré, le fragment revient (le test manipulait bien la bonne cap)'
	);

	/* --- AC 10 : le câblage des colonnes ------------------------------- */
	$aod_ac_o434_4 = $aod_ac_make_order( $pidX, 'pending', $confA );
	try {
		$aod_ac_ord = wc_get_order( $aod_ac_o434_4 );
		$aod_ac_ord->update_meta_data( '_aod_assigned_by', $aod_ac_gerant );
		$aod_ac_ord->save();
		AOD_Test::ok(
			false !== strpos( AOD_CD_Section_Orders::assigned_confirmer_label( wc_get_order( $aod_ac_o434_4 ) ), 'aod-cd-assigned-by' ),
			'🔴 assigned_confirmer_label() transmet bien `_aod_assigned_by` (sinon la colonne n’afficherait jamais rien)'
		);
		// La section Suivi appelle CETTE méthode : elle hérite du fragment sans
		// une ligne de code — vérifié ici plutôt que supposé.
		$aod_ac_ord = wc_get_order( $aod_ac_o434_4 );
		$aod_ac_ord->update_meta_data( '_aod_assigned_by', $confB );
		$aod_ac_ord->save();
		AOD_Test::ok(
			false === strpos( AOD_CD_Section_Orders::assigned_confirmer_label( wc_get_order( $aod_ac_o434_4 ) ), 'aod-cd-assigned-by' ),
			'Colonne : une assignation posée par un confirmateur n’affiche pas « Admin »'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_o434_4 );
	}

	/* =====================================================================
	 * 43.5 — L'assignation groupée peut ÉCRASER, mais seulement sur demande
	 *
	 * 🚨 CE QUE CE GROUPE GARDE. La 42.5 avait délibérément fait un outil qui
	 * ne peut PAS écraser (« saute silencieusement toute commande déjà
	 * assignée »), pour qu'une sélection large ne redistribue jamais par
	 * accident des commandes déjà prises en charge. La 43.5 rouvre cette
	 * porte — donc la moitié des assertions ci-dessous vérifient qu'elle
	 * reste FERMÉE quand on ne demande rien.
	 * ===================================================================== */

	AOD_Test::group( 'Assignation 43.5 — `overwrite` : écraser, mais seulement si on le demande' );

	wp_set_current_user( $aod_ac_gerant );

	// R1 — le défaut est RIGOUREUSEMENT la 42.5.
	$aod_ac_o435_l = $aod_ac_make_order( $pidX, 'pending' );          // libre
	$aod_ac_o435_p = $aod_ac_make_order( $pidX, 'pending', $confB );  // déjà à confB
	try {
		// Appel à DEUX arguments : on éprouve la valeur par défaut du paramètre,
		// pas seulement `false` passé explicitement — c'est ce que fait le code
		// appelant qui n'a pas encore été mis à jour.
		$aod_ac_r435 = AOD_Test::call( $orders, 'bulk_assign', array( array( $aod_ac_o435_l, $aod_ac_o435_p, 999999999 ), $confA ) );
		AOD_Test::eq( 1, count( $aod_ac_r435['assigned'] ), 'Défaut : la commande libre est assignée' );
		AOD_Test::eq( 1, $aod_ac_r435['skipped'], '🔴 Défaut : la commande déjà assignée est SAUTÉE (42.5 intacte)' );
		AOD_Test::eq( 1, $aod_ac_r435['missing'], 'Défaut : l’ID inexistant reste « introuvable »' );
		AOD_Test::eq( 0, count( $aod_ac_r435['reassigned'] ), '🔴 `reassigned` est TOUJOURS vide sans `overwrite` — la forme même de la non-régression' );
		AOD_Test::eq(
			(string) $confB,
			(string) wc_get_order( $aod_ac_o435_p )->get_meta( '_aod_assigned_confirmer' ),
			'🔴 Défaut : l’assignation d’origine n’a pas bougé'
		);
		AOD_Test::eq(
			'',
			(string) wc_get_order( $aod_ac_o435_p )->get_meta( '_aod_assigned_by' ),
			'🔴 Défaut : la commande sautée ne reçoit AUCUNE signature (43.4)'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_o435_l );
		$aod_ac_del_order( $aod_ac_o435_p );
	}

	// R2 — `overwrite = true` réassigne, signe, et se compte à part.
	$aod_ac_o435_r = $aod_ac_make_order( $pidX, 'pending', $confB );
	try {
		$aod_ac_r435 = AOD_Test::call( $orders, 'bulk_assign', array( array( $aod_ac_o435_r ), $confA, true ) );
		AOD_Test::eq( 1, count( $aod_ac_r435['reassigned'] ), '🔴 `overwrite` : la commande sort dans `reassigned`' );
		AOD_Test::eq( 0, count( $aod_ac_r435['assigned'] ), '…et JAMAIS dans `assigned` (le gérant doit voir ce qu’il a REPRIS à quelqu’un)' );
		AOD_Test::eq(
			(string) $confA,
			(string) wc_get_order( $aod_ac_o435_r )->get_meta( '_aod_assigned_confirmer' ),
			'`overwrite` : la commande a bien changé de confirmateur'
		);
		AOD_Test::eq(
			(string) $aod_ac_gerant,
			(string) wc_get_order( $aod_ac_o435_r )->get_meta( '_aod_assigned_by' ),
			'…et porte la signature du gérant : une réassignation groupée EST un geste manuel (43.4)'
		);
	} finally {
		$aod_ac_del_order( $aod_ac_o435_r );
	}

	// R3 — LE PIÈGE : déjà assignée À LA CIBLE ⇒ sautée dans les DEUX modes.
	// Sans cette règle, réassigner X à X réécrirait `_aod_assigned_by` et ferait
	// passer une assignation POSÉE PAR LE TOURNIQUET pour un choix du gérant :
	// la colonne afficherait « Admin » sur une décision que personne n’a prise.
	$aod_ac_o435_c = $aod_ac_make_order( $pidX, 'pending', $confA ); // assignée à confA, SANS signature
	try {
		$aod_ac_r435 = AOD_Test::call( $orders, 'bulk_assign', array( array( $aod_ac_o435_c ), $confA, true ) );
		AOD_Test::eq( 1, $aod_ac_r435['skipped'], '🔴 Déjà assignée à la CIBLE : sautée même avec `overwrite`' );
		AOD_Test::eq( 0, count( $aod_ac_r435['reassigned'] ), '…et surtout pas comptée « réassignée » (rien n’a changé)' );
		AOD_Test::eq(
			'',
			(string) wc_get_order( $aod_ac_o435_c )->get_meta( '_aod_assigned_by' ),
			'🔴 …sa signature reste ABSENTE : le tourniquet ne se fait pas passer pour le gérant'
		);
		$aod_ac_r435 = AOD_Test::call( $orders, 'bulk_assign', array( array( $aod_ac_o435_c ), $confA, false ) );
		AOD_Test::eq( 1, $aod_ac_r435['skipped'], 'Sautée aussi sans `overwrite` (même issue, autre raison)' );
	} finally {
		$aod_ac_del_order( $aod_ac_o435_c );
	}

	// R4 — ANCRAGE INVERSE : `overwrite` n'invente pas de réassignation.
	$aod_ac_o435_n = $aod_ac_make_order( $pidX, 'pending' );
	try {
		$aod_ac_r435 = AOD_Test::call( $orders, 'bulk_assign', array( array( $aod_ac_o435_n ), $confA, true ) );
		AOD_Test::eq( 1, count( $aod_ac_r435['assigned'] ), '🔴 ANCRAGE INVERSE : avec `overwrite`, une commande LIBRE reste une « assignée »' );
		AOD_Test::eq( 0, count( $aod_ac_r435['reassigned'] ), '…jamais une « réassignée » (sinon le compteur mentirait sur ce qui a été repris)' );
	} finally {
		$aod_ac_del_order( $aod_ac_o435_n );
	}

	// R5 — le lot mixte, qui est le cas réel : un de chaque.
	$aod_ac_m1 = $aod_ac_make_order( $pidX, 'pending' );          // libre    → assigned
	$aod_ac_m2 = $aod_ac_make_order( $pidX, 'pending', $confB );  // à confB  → reassigned
	$aod_ac_m3 = $aod_ac_make_order( $pidX, 'pending', $confA );  // à confA  → skipped
	try {
		$aod_ac_r435 = AOD_Test::call( $orders, 'bulk_assign', array( array( $aod_ac_m1, $aod_ac_m2, $aod_ac_m3 ), $confA, true ) );
		AOD_Test::ok(
			1 === count( $aod_ac_r435['assigned'] ) && 1 === count( $aod_ac_r435['reassigned'] ) && 1 === $aod_ac_r435['skipped'],
			sprintf(
				'🔴 Lot mixte : 1 assignée / 1 réassignée / 1 sautée (lu : %d / %d / %d)',
				count( $aod_ac_r435['assigned'] ),
				count( $aod_ac_r435['reassigned'] ),
				$aod_ac_r435['skipped']
			)
		);
	} finally {
		$aod_ac_del_order( $aod_ac_m1 );
		$aod_ac_del_order( $aod_ac_m2 );
		$aod_ac_del_order( $aod_ac_m3 );
	}

	// R6 — confiance zéro : SEULE la chaîne '1' arme le mode destructif.
	// On éprouve la garde du handler telle qu'elle est écrite (le handler lui-même
	// se termine par wp_send_json_*(), donc jamais appelable ici).
	AOD_Test::group( 'Assignation 43.5 — `overwrite` : confiance zéro sur la valeur postée' );
	foreach ( array( '1' => true, '0' => false, '' => false, 'false' => false, 'true' => false, 'oui' => false, '2' => false ) as $aod_ac_v => $aod_ac_att ) {
		$_POST['overwrite'] = $aod_ac_v;
		$aod_ac_lu          = isset( $_POST['overwrite'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['overwrite'] ) );
		AOD_Test::ok(
			$aod_ac_lu === $aod_ac_att,
			sprintf( '`overwrite` = %-8s → %s', "'" . $aod_ac_v . "'", $aod_ac_att ? 'ARMÉ' : 'désarmé' ),
			'`! empty()` accepterait « 0 » et « false » : une garde qui ne REGARDE pas la valeur ne la refuse pas (leçon 32.3)'
		);
	}
	unset( $_POST['overwrite'] );
	AOD_Test::ok(
		! ( isset( $_POST['overwrite'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['overwrite'] ) ) ),
		'`overwrite` ABSENT → désarmé (le défaut est toujours le mode sûr)'
	);
} finally {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $aod_ac_gerant );
	wp_set_current_user( $aod_ac_user_avant );
}

AOD_Test::ok( ! get_user_by( 'login', 'aod_test_43_4_gerant' ), 'Le compte gérant de test est supprimé' );

/* =========================================================================
 * Nettoyage — le fichier ne laisse rien derrière lui
 * ========================================================================= */

AOD_Test::group( 'Assignation 42.1/42.2/42.3/42.4/42.5/43.4 — nettoyage' );

wp_set_current_user( $aod_ac_user_avant );

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $confA, $confB, $confC, $confD, $confE, $confF ) as $uid ) {
	if ( $uid ) {
		wp_delete_user( $uid );
	}
}
wp_delete_post( $pidX, true );
wp_delete_post( $pidY, true );

AOD_Test::ok( ! get_user_by( 'login', 'aod_test_42_1_a' ), 'Les confirmateurs de test sont supprimés' );
AOD_Test::ok( ! wc_get_product( $pidX ), 'Les produits de test sont supprimés' );
