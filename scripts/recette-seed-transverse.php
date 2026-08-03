<?php
/**
 * Fixtures des portes TRANSVERSES (solde de la dette D29) — create | cleanup.
 *
 * Usage : wp eval-file /scripts/recette-seed-transverse.php <create|cleanup>
 *
 * 🚨 POURQUOI CE SEED EXISTE. Les portes qui balaient les 12 sections
 * (`contraste`, `noms`, `clavier`, `rtl`, `mouvement`) s'exécutaient sur le clone
 * TEL QUEL. Mesuré le 2026-07-28 sur le clone de développement : **6 sections sur
 * 12 rendent un état vide** — `home`, `orders`, `abandoned`, `products`, `stock`,
 * `tracking` — avec **0 tableau et 0 ligne**. Ces portes auditaient donc des pages
 * quasi nues : ni tableau, ni ligne, ni barre d'action groupée, ni sélecteur de
 * statut, ni pagination. Elles passaient au vert sans avoir regardé la majeure
 * partie de l'interface.
 *
 * C'est la « règle du témoin » (§C.2) appliquée aux **données** et non plus au
 * code : le contrôle rend le même signal qu'un audit exhaustif, en n'ayant rien
 * inspecté. C'est ainsi que `run.sh rtl` — qui mesure pourtant le débordement
 * horizontal à 390 px sur les 12 sections — est resté VERT pendant que
 * `/gestion/orders` débordait de 193 px (cf. commit `1920025`).
 *
 * CE QU'ON SÈME — le minimum pour que les 6 sections creuses portent du contenu :
 *   · 1 produit publié, avec une section d'options (« Couleur ») et un stock géré
 *     → remplit **Produits** et **Stock** ;
 *   · 3 commandes portant ce produit (`pending`, `aod-confirmed`, `completed`)
 *     → remplissent **Commandes** et les agrégats de l'**Accueil** ;
 *   · l'une d'elles expédiée (transporteur + n° de suivi + état normalisé)
 *     → remplit **Suivi commande** ;
 *   · 1 prospect `pending` complet (produit, wilaya, commune, type de livraison)
 *     → remplit **Commandes abandonnées**, colonne « Montant » comprise ;
 *   · une **page de vente** activée sur ce produit → charge `aod-landing.css`,
 *     que la porte `mouvement` audite et ne trouvait jamais (2ᵉ symptôme de D29,
 *     côté public).
 *
 * 🚨 PURGE CIBLÉE, JAMAIS PAR STATUT NI PAR TYPE (leçon D26). Un clone en service
 * porte de VRAIES commandes et de VRAIS prospects. `cleanup` ne touche qu'aux
 * objets portant la marque, revérifiée juste avant toute suppression.
 *
 * @package AOD_Recette
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce n’est pas actif.' );
}

const AOD_D29_MARK  = '_aod_recette_d29';
const AOD_D29_TOKEN = 'ZZD29lead';

$aod_d29_action = isset( $args[0] ) ? $args[0] : 'create';

/** Supprime tout ce que ce seed a semé — et RIEN d'autre. */
function aod_d29_cleanup() {
	global $wpdb;
	$n_cmd = 0;

	// Commandes : retrouvées par la MARQUE, jamais par statut.
	$ids = wc_get_orders( array(
		'limit'      => -1,
		'status'     => 'any',
		'return'     => 'ids',
		'meta_key'   => AOD_D29_MARK,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- purge de fixtures, hors chemin utilisateur.
		'meta_value' => '1',            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- idem.
	) );
	foreach ( $ids as $id ) {
		$o = wc_get_order( $id );
		// Revérification de la marque AVANT suppression : une requête qui
		// dériverait ne doit pas pouvoir emporter une vraie commande.
		if ( $o && '1' === (string) $o->get_meta( AOD_D29_MARK ) ) {
			$o->delete( true );
			++$n_cmd;
		}
	}

	// Produits.
	$n_prod = 0;
	foreach ( get_posts( array(
		'post_type'   => 'product',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => AOD_D29_MARK,  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- purge de fixtures.
		'meta_value'  => '1',           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- idem.
	) ) as $pid ) {
		if ( '1' === (string) get_post_meta( $pid, AOD_D29_MARK, true ) ) {
			wp_delete_post( $pid, true );
			++$n_prod;
		}
	}

	// Prospects : par PRÉFIXE de jeton, propre à ce seed.
	$n_lead = 0;
	if ( class_exists( 'AOD_COD_Leads' ) && method_exists( 'AOD_COD_Leads', 'table' ) ) {
		$table  = AOD_COD_Leads::table();
		$n_lead = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE token LIKE %s", $wpdb->esc_like( AOD_D29_TOKEN ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $table = $wpdb->prefix (nom contrôlé) ; le prepare() borne le motif. Table custom : pas d'API WP.
	}

	return array( $n_cmd, $n_prod, $n_lead );
}

if ( 'cleanup' === $aod_d29_action ) {
	list( $c, $p, $l ) = aod_d29_cleanup();
	WP_CLI::log( "D29CLEAN=1" );
	WP_CLI::log( "D29CLEAN_CMD={$c}" );
	WP_CLI::log( "D29CLEAN_PROD={$p}" );
	WP_CLI::log( "D29CLEAN_LEAD={$l}" );
	return;
}

/* ------------------------------------------------------------------ create */

// Idempotence : on repart d'une ardoise propre plutôt que d'empiler.
aod_d29_cleanup();

// 1) Produit — avec options ET stock géré : c'est ce qui donne du contenu à
//    « Produits » ET à « Stock » (la page Stock ne liste que ce qu'elle peut
//    chiffrer).
$aod_d29_pid = wp_insert_post( array(
	'post_title'   => 'Recette D29 — produit témoin',
	'post_type'    => 'product',
	'post_status'  => 'publish',
	'post_content' => 'Produit semé par la recette transverse. Purgé en sortie.',
) );
wp_set_object_terms( $aod_d29_pid, 'simple', 'product_type' );
update_post_meta( $aod_d29_pid, '_price', 2400 );
update_post_meta( $aod_d29_pid, '_regular_price', 2400 );
update_post_meta( $aod_d29_pid, '_manage_stock', 'yes' );
update_post_meta( $aod_d29_pid, '_stock', 12 );
update_post_meta( $aod_d29_pid, '_stock_status', 'instock' );
update_post_meta( $aod_d29_pid, AOD_D29_MARK, '1' );
update_post_meta( $aod_d29_pid, '_aod_options', array(
	array(
		'label'  => 'Couleur',
		'values' => array(
			array( 'name' => 'Rouge', 'price' => 0, 'stock' => 7 ),
			array( 'name' => 'Bleu', 'price' => 200, 'stock' => 5 ),
		),
	),
) );

// 1bis) PAGE DE VENTE activée sur ce produit — c'est la seule façon de charger
//       `aod-landing.css`, que la porte `mouvement` audite. Sans un produit qui
//       en porte une, elle se rabat sur l'accueil, n'y trouve pas la feuille, et
//       échoue : deuxième symptôme de D29, du côté public cette fois.
if ( class_exists( 'AOD_COD_Landing' ) ) {
	$aod_d29_landing = AOD_COD_Landing::empty_data();
	$aod_d29_landing['blocks'] = array(
		array(
			'type' => 'hero',
			'data' => array(
				'title' => array( 'fr' => 'Recette D29 — héros', 'ar' => 'عنوان تجريبي' ),
				'text'  => array( 'fr' => 'Bloc semé par la recette transverse.', 'ar' => 'كتلة تجريبية.' ),
				'cta'   => array( 'fr' => 'Commander', 'ar' => 'اطلب الآن' ),
			),
		),
		array(
			'type' => 'form',
			'data' => array( 'title' => array( 'fr' => 'Commander', 'ar' => 'اطلب' ) ),
		),
	);
	update_post_meta( $aod_d29_pid, '_aod_landing', AOD_COD_Landing::sanitize( $aod_d29_landing ) );
	update_post_meta( $aod_d29_pid, '_aod_landing_enabled', '1' );
}

/**
 * Commande témoin portant le produit ci-dessus.
 *
 * @param int    $pid    Produit.
 * @param string $statut Statut WooCommerce (sans préfixe `wc-`).
 * @return WC_Order
 */
function aod_d29_commande( $pid, $statut ) {
	$order = new WC_Order();
	$item  = new WC_Order_Item_Product();
	$item->set_product( wc_get_product( $pid ) );
	$item->set_quantity( 2 );
	$order->add_item( $item );
	$order->set_address( array(
		'first_name' => 'Recette',
		'last_name'  => 'D29',
		'phone'      => '0555000429',
		'country'    => 'DZ',
		'state'      => 'DZ-16',
		'city'       => 'Alger',
	), 'billing' );
	$order->update_meta_data( AOD_D29_MARK, '1' );
	// Métas COD : sans elles, la commande n'est pas reconnue comme COD et
	// plusieurs colonnes de `/gestion` restent vides.
	$order->update_meta_data( '_aod_wilaya_code', 16 );
	$order->update_meta_data( '_aod_wilaya_name', 'Alger' );
	$order->update_meta_data( '_aod_commune', 'Alger' );
	$order->update_meta_data( '_aod_delivery_type', 'home' );
	$order->update_meta_data( '_aod_source', 'aod-cod-form' );
	$order->set_payment_method( 'cod' );
	$order->calculate_totals();
	$order->set_status( $statut );
	$order->save();
	return $order;
}

$aod_d29_o1 = aod_d29_commande( $aod_d29_pid, 'pending' );
$aod_d29_o2 = aod_d29_commande( $aod_d29_pid, 'aod-confirmed' );
$aod_d29_o3 = aod_d29_commande( $aod_d29_pid, 'completed' );

// 2) Une commande EXPÉDIÉE — c'est la seule façon de peupler « Suivi commande »,
//    qui ne liste que les colis partis. Métas de suivi renseignées « à la main » :
//    aucun appel réseau, aucun transporteur réellement sollicité.
$aod_d29_o3->update_meta_data( '_aod_ship_carrier', 'yalidine' );
$aod_d29_o3->update_meta_data( '_aod_ship_tracking', 'D29-RECETTE-0001' );
$aod_d29_o3->update_meta_data( '_aod_ship_status', 'in_transit' );
$aod_d29_o3->update_meta_data( '_aod_ship_status_label', 'En transit' );
$aod_d29_o3->update_meta_data( '_aod_ship_status_at', current_time( 'mysql' ) );
$aod_d29_o3->save();

// 3) Prospect complet — remplit « Commandes abandonnées », colonne Montant
//    comprise (produit + wilaya + type de livraison : les trois conditions).
$aod_d29_lead = 0;
if ( class_exists( 'AOD_COD_Leads' ) && method_exists( 'AOD_COD_Leads', 'table' ) ) {
	global $wpdb;
	$vieux = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 3600 ); // > 30 min → « Abandonné ».
	$wpdb->insert( AOD_COD_Leads::table(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- table custom, pas d'API WP.
		'token'      => AOD_D29_TOKEN . '01',
		'product_id' => $aod_d29_pid,
		'name'       => 'Prospect D29',
		'phone'      => '0555000430',
		'wilaya'     => 16,
		'commune'    => 'Alger',
		'delivery'   => 'home',
		'qty'        => 2,
		'status'     => 'pending',
		'created_at' => $vieux,
		'updated_at' => $vieux,
	) );
	$aod_d29_lead = (int) $wpdb->insert_id;
}

WP_CLI::log( 'D29=1' );
WP_CLI::log( 'D29_PID=' . (int) $aod_d29_pid );
WP_CLI::log( 'D29_OID=' . (int) $aod_d29_o1->get_id() );
WP_CLI::log( 'D29_OID_SHIP=' . (int) $aod_d29_o3->get_id() );
WP_CLI::log( 'D29_LEAD=' . $aod_d29_lead );
