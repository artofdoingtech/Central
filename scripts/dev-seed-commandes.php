<?php
/**
 * Commandes de DÉMONSTRATION — 30 commandes fraîches + 15 paniers abandonnés.
 *
 * Créer :  docker compose exec -T wpcli wp eval-file /scripts/dev-seed-commandes.php create
 * Purger : docker compose exec -T wpcli wp eval-file /scripts/dev-seed-commandes.php cleanup
 *
 * Prérequis : `scripts/dev-seed-catalogue.php create` (les deux produits de démo).
 *
 * CE QU'IL SÈME
 *   · 30 commandes au statut `processing` — EXACTEMENT le statut qu'écrit
 *     `AOD_COD_Form::handle_submit()` : « en cours », non confirmées, et
 *     **assignées par le tourniquet** (`aod_cod_order_created` → Phase 42.1),
 *     donc réparties au confirmateur éligible le moins chargé — 10/10/10 avec
 *     trois confirmateurs, et rien du tout si le vivier est vide. Étalées sur
 *     les **30 dernières heures**, dont la moitié sur les 8 dernières, sur les
 *     deux produits, avec variantes, quantités, offres de lot, wilayas et
 *     communes RÉELLES, domicile et stop-desk.
 *   · 15 prospects dans `{prefix}aod_cod_leads` (« Commandes abandonnées ») :
 *     tous plus vieux que le seuil de 30 min, donc réellement « abandonnés »,
 *     et volontairement INCOMPLETS à des degrés divers — c'est ce qui les
 *     distingue d'une commande.
 *
 * DEUX DÉCISIONS QUI COMPTENT
 *
 * 1. **Le prix n'est pas recalculé ici.** Le seed appelle
 *    `AOD_COD_Form::instance()->estimate_lines()` — le pont public déjà utilisé
 *    par le panneau du panier COD, qui passe par `build_order_lines()` +
 *    `price_lines()`. Une formule recopiée dans un seed dériverait au premier
 *    changement d'offre, et le jeu d'essai mentirait sans que rien ne le dise.
 *    Corollaire assumé : le seed hérite aussi des GARDES (rupture de variante,
 *    `orderable_stock()` de la 44.1). Il ne peut donc pas fabriquer une commande
 *    que le vrai formulaire aurait refusée.
 *
 * 2. **L'anti-bot n'est pas joué.** Honeypot, time-trap, plafond IP, verrou
 *    « 1 commande / 24 h par téléphone » et Turnstile vivent dans
 *    `handle_submit()`, que ce script n'emprunte pas. C'est voulu : un seed n'est
 *    pas un visiteur, et poser le transient `aod_cod_ph_*` empêcherait ensuite
 *    l'owner de commander pour de vrai avec ces numéros. Éprouver l'anti-bot
 *    reste le travail de `tools/recette/run.sh cod`.
 *
 * ⚠️ WooCommerce DÉCRÉMENTE le stock produit sur le statut `processing`
 * (`wc-stock-functions.php`). Les 30 commandes consomment donc du stock Woo —
 * c'est le comportement réel, pas un effet de bord du seed. Le stock PAR
 * VARIANTE, lui, ne bouge qu'à la confirmation (décision D3 / Phase 40.5).
 *
 * ⚠️ TARIFS DE LIVRAISON : sans grille, toutes les commandes auraient 0 DA de
 * livraison et le jeu d'essai serait faux. Le seed comble donc les wilayas SANS
 * tarif — il n'écrase JAMAIS celles que le gérant a déjà réglées — et note
 * lesquelles il a remplies pour que `cleanup` ne remette à blanc que celles-là.
 *
 * Marques : méta `_aod_seed_commandes` sur les commandes, préfixe de jeton
 * `demo-seed-` sur les prospects. `cleanup` ne supprime QUE ça.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'AOD_COD_Form' ) || ! class_exists( 'AOD_COD_Leads' ) ) {
	WP_CLI::error( 'WooCommerce et/ou aod-cod-form ne sont pas actifs.' );
}

const AOD_CMD_MARK         = '_aod_seed_commandes';
const AOD_CMD_LEAD_PREFIX  = 'demo-seed-';
const AOD_CMD_TARIF_OPTION = '_aod_seed_commandes_tarifs';

/* =========================================================================
 * PURGE
 * ====================================================================== */

/**
 * Supprime commandes marquées, prospects du seed, et rend leur blanc aux
 * wilayas dont CE script avait comblé le tarif.
 *
 * @return array{0:int,1:int,2:int} commandes, prospects, tarifs rendus.
 */
function aod_cmd_cleanup() {
	// 🚨 HPOS : `get_posts( 'shop_order' )` ne voit rien ici. `wc_get_orders()`
	// couvre les deux modes de stockage (patron `recette-seed-assignation.php`).
	$n_o    = 0;
	$orders = wc_get_orders( array(
		'type'       => 'shop_order',
		'limit'      => -1,
		'status'     => 'any',
		'return'     => 'ids',
		'meta_query' => array( array( 'key' => AOD_CMD_MARK, 'value' => '1' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	foreach ( $orders as $oid ) {
		$o = wc_get_order( $oid );
		// Double contrôle avant un geste irréversible : une commande RÉELLE ne doit
		// jamais tomber parce qu'une meta_query aurait été trop large (leçon D26).
		if ( $o && '1' === (string) $o->get_meta( AOD_CMD_MARK ) ) {
			// 🚨 RENDRE LE STOCK AVANT DE SUPPRIMER. `delete()` ne le fait pas : la
			// commande naît en `processing`, WooCommerce décrémente sur ce statut, et
			// la supprimer laissait le stock consommé POUR TOUJOURS. Le seed n'était
			// donc pas rejouable — deux `create` de suite et le sac (48 pièces, 18
			// consommées par passe) tombait sous le seuil où `orderable_stock()`
			// refuse les dernières lignes, sans que rien n'explique pourquoi.
			// `wc_maybe_increase_stock_levels()` est l'inverse exact du décrément : il
			// lit `_reduced_stock` posé sur chaque ligne et ne rend que ça.
			wc_maybe_increase_stock_levels( $o );
			$o->delete( true );
			$n_o++;
		}
	}

	global $wpdb;
	$table = AOD_COD_Leads::table();
	$n_l   = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE token LIKE %s", $wpdb->esc_like( AOD_CMD_LEAD_PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = AOD_COD_Leads::table() (préfixe wpdb, nom contrôlé) ; le prepare() borne le motif. Table custom : pas d'API WP.

	// Tarifs : on ne rend à blanc QUE les couples (wilaya, mode) que le seed avait
	// comblés. Restaurer l'option entière effacerait ce que l'owner a réglé depuis.
	$n_t    = 0;
	$filled = get_option( AOD_CMD_TARIF_OPTION, array() );
	if ( is_array( $filled ) && $filled ) {
		$prices = AOD_COD_Data::prices();
		foreach ( $filled as $code => $keys ) {
			foreach ( (array) $keys as $key ) {
				if ( isset( $prices[ $code ][ $key ] ) ) {
					$prices[ $code ][ $key ] = '';
					$n_t++;
				}
			}
		}
		update_option( AOD_COD_Data::OPTION_PRICES, $prices );
		delete_option( AOD_CMD_TARIF_OPTION );
	}

	return array( $n_o, $n_l, $n_t );
}

$aod_cmd_mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $aod_cmd_mode ) {
	list( $n_o, $n_l, $n_t ) = aod_cmd_cleanup();
	WP_CLI::success( "Jeu de commandes purgé : $n_o commande(s), $n_l prospect(s), $n_t tarif(s) rendu(s) à blanc." );
	return;
}
if ( 'create' !== $aod_cmd_mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/dev-seed-commandes.php create|cleanup' );
}

aod_cmd_cleanup(); // Reliquat d'une exécution interrompue : on repart propre.

// Les e-mails WooCommerce sont COUPÉS le temps du seed. Le passage en
// `processing` déclenche la notification « nouvelle commande » au gérant : sur
// cette pile sans SMTP ça n'imprime qu'un `sendmail: connection refused` par
// commande, mais sur un clone où l'envoi marche, ce sont 30 courriels réels pour
// des commandes fictives. Un jeu d'essai ne réveille personne.
add_filter( 'woocommerce_email_enabled_new_order', '__return_false', 99 );
add_filter( 'woocommerce_email_enabled_customer_processing_order', '__return_false', 99 );
add_filter( 'woocommerce_email_enabled_customer_on_hold_order', '__return_false', 99 );
add_filter( 'pre_wp_mail', '__return_false', 99 ); // Ceinture : tout autre envoi déclenché en cascade.

// ─────────────────────────────────────────────────────────────────────────────
// `aod_cod_order_created` a TROIS abonnés. Le seed en veut UN.
//
// · `AOD_CD_Section_Orders::assign_least_loaded` (42.1) — le tourniquet, qui
//   répartit la commande au confirmateur le moins chargé. ON LE VEUT : sans lui,
//   les 30 commandes naissent « non assignée », et le jeu d'essai ne ressemble
//   pas à ce que produit le formulaire public.
// · `AOD_COD_WhatsApp::notify` — appelle CallMeBot. 30 messages RÉELS sur le
//   téléphone du gérant, pour des commandes qui n'existent pas.
// · `AOD_COD_Pixels::queue_server_conversions` — conversions serveur vers
//   Meta / TikTok / Snap / Google. 30 achats fictifs injectés dans de VRAIS
//   comptes publicitaires, qui fausseraient l'optimisation des campagnes.
//
// Les deux derniers sortent de la machine : on les débranche. Et on VÉRIFIE que
// le débranchement a pris — `remove_action()` rend `false` en silence si le
// rappel ne correspond pas exactement à celui posé, et un seed qui croit avoir
// coupé WhatsApp sans l'avoir coupé est exactement le genre d'erreur qu'on ne
// découvre qu'après l'envoi.
foreach ( array(
	array( 'AOD_COD_WhatsApp', 'notify', 10 ),
	array( 'AOD_COD_Pixels', 'queue_server_conversions', 20 ),
) as $aod_cmd_ext ) {
	list( $klass, $method, $prio ) = $aod_cmd_ext;
	if ( ! class_exists( $klass ) || ! method_exists( $klass, 'instance' ) ) {
		continue;
	}
	$cb = array( call_user_func( array( $klass, 'instance' ) ), $method );
	remove_action( 'aod_cod_order_created', $cb, $prio );
	if ( false !== has_action( 'aod_cod_order_created', $cb ) ) {
		WP_CLI::error( "Impossible de débrancher $klass::$method — refus de semer (des envois réels partiraient)." );
	}
}

/* =========================================================================
 * LES DEUX PRODUITS DE DÉMO
 * ====================================================================== */

/**
 * Retrouve un produit du catalogue de démo par son SKU.
 *
 * Par le SKU et non par l'ID : `dev-seed-catalogue.php create` purge et recrée,
 * donc les IDs changent à chaque exécution. Le SKU, lui, est le contrat.
 *
 * @param string $sku
 * @return WC_Product
 */
function aod_cmd_produit( $sku ) {
	$id = wc_get_product_id_by_sku( $sku );
	$p  = $id ? wc_get_product( $id ) : null;
	if ( ! $p ) {
		WP_CLI::error( "Produit « $sku » introuvable — lance d'abord : wp eval-file /scripts/dev-seed-catalogue.php create" );
	}
	return $p;
}

$casque = aod_cmd_produit( 'AURA-ANC40' );
$sac    = aod_cmd_produit( 'NOMAD-25L' );

/* =========================================================================
 * TARIFS DE LIVRAISON — combler les trous, ne rien écraser
 * ====================================================================== */

/**
 * Grille indicative par zone (DA) : [ domicile, stop-desk ].
 * Volontairement grossière — c'est un jeu d'essai, pas une tarification réelle.
 */
$aod_cmd_zones = array(
	// Alger et sa couronne.
	'centre' => array( array( 16, 9, 35, 42, 10, 44, 26 ), 500, 300 ),
	// Grandes villes du Nord.
	'nord'   => array( array( 31, 25, 6, 15, 19, 23, 13, 21, 27, 2, 22, 5, 18, 24, 43, 36, 46, 38, 29, 34, 4, 20, 14, 48, 12, 28 ), 650, 350 ),
	// Hauts-plateaux et Sud proche.
	'sud'    => array( array( 7, 3, 17, 32, 40, 41, 45, 39, 47, 30, 51, 55, 56 ), 850, 450 ),
	// Grand Sud.
	'gsud'   => array( array( 1, 8, 11, 33, 37, 49, 50, 52, 53, 54, 57, 58 ), 1200, 700 ),
);

$aod_cmd_prices = AOD_COD_Data::prices();
$aod_cmd_filled = array();
foreach ( $aod_cmd_zones as $zone ) {
	list( $codes, $home, $desk ) = $zone;
	foreach ( $codes as $code ) {
		if ( ! isset( $aod_cmd_prices[ $code ] ) || ! is_array( $aod_cmd_prices[ $code ] ) ) {
			$aod_cmd_prices[ $code ] = array( 'home' => '', 'desk' => '', 'free' => 0 );
		}
		foreach ( array( 'home' => $home, 'desk' => $desk ) as $key => $val ) {
			// Un tarif déjà saisi par le gérant est INTOUCHABLE : le seed comble, il
			// ne corrige pas. `'' === valeur` est le seul cas « pas encore réglé ».
			$cur = isset( $aod_cmd_prices[ $code ][ $key ] ) ? (string) $aod_cmd_prices[ $code ][ $key ] : '';
			if ( '' !== $cur && (float) $cur > 0 ) {
				continue;
			}
			$aod_cmd_prices[ $code ][ $key ] = (string) $val;
			$aod_cmd_filled[ $code ][]       = $key;
		}
	}
}
if ( $aod_cmd_filled ) {
	update_option( AOD_COD_Data::OPTION_PRICES, $aod_cmd_prices );
	update_option( AOD_CMD_TARIF_OPTION, $aod_cmd_filled, false );
}

/* =========================================================================
 * OUTILS
 * ====================================================================== */

/**
 * Une commune RÉELLE de la wilaya, choisie de façon déterministe.
 *
 * Déterministe et non aléatoire : deux exécutions du seed doivent produire le
 * même jeu, sinon on ne peut plus reproduire un bug constaté dessus. Passe par
 * `AOD_COD_Data::communes()` — jamais une liste recopiée, qui dériverait du
 * référentiel géo au premier ajout de commune.
 *
 * @param int $wilaya
 * @param int $rang   Rang arbitraire (borné par le nombre de communes).
 * @return string
 */
function aod_cmd_commune( $wilaya, $rang ) {
	$list = AOD_COD_Data::communes( $wilaya );
	if ( ! $list ) {
		return '';
	}
	$names = array();
	foreach ( $list as $c ) {
		$n = is_array( $c ) ? ( isset( $c['name'] ) ? $c['name'] : '' ) : (string) $c;
		if ( '' !== $n ) {
			$names[] = $n;
		}
	}
	return $names ? $names[ $rang % count( $names ) ] : '';
}

/**
 * Crée une commande COD, au plus près de `AOD_COD_Form::handle_submit()`.
 *
 * @param array $c Client : name, phone, wilaya, rang (commune), address, delivery.
 * @param array $items [{ product_id, opt, qty }] — format `estimate_lines()`.
 * @param int   $ts    Horodatage de création (passé).
 * @return int ID de commande, ou 0 si les lignes ont été refusées.
 */
function aod_cmd_creer_commande( array $c, array $items, $ts ) {
	$est = AOD_COD_Form::instance()->estimate_lines( $items );
	if ( ! empty( $est['errors'] ) || empty( $est['lines'] ) ) {
		WP_CLI::warning( 'Commande refusée pour ' . $c['name'] . ' : ' . implode( ' ', (array) $est['errors'] ) );
		return 0;
	}

	$order    = wc_create_order();
	$subtotal = 0.0;
	foreach ( $est['lines'] as $line ) {
		$product = wc_get_product( $line['pid'] );
		$item_id = $order->add_product( $product, $line['qty'] );
		$item    = $item_id ? $order->get_item( $item_id ) : null;
		if ( $item ) {
			$item->set_subtotal( $line['line_total'] );
			$item->set_total( $line['line_total'] );
			foreach ( $line['option_meta'] as $label => $value ) {
				$item->add_meta_data( $label, $value, true );
			}
			$item->save();
		}
		$subtotal += (float) $line['line_total'];
	}

	$address = array(
		'first_name' => $c['name'],
		'phone'      => $c['phone'],
		'address_1'  => $c['address'],
		'city'       => $c['commune'],
		'state'      => 'DZ-' . str_pad( $c['wilaya'], 2, '0', STR_PAD_LEFT ),
		'country'    => 'DZ',
	);
	$order->set_address( $address, 'billing' );
	$order->set_address( $address, 'shipping' );

	$base_cost = AOD_COD_Data::price_for( $c['wilaya'], $c['delivery'] );
	$ship_cost = AOD_COD_Data::effective_shipping( $c['wilaya'], $c['delivery'], $subtotal );
	$label     = ( 'desk' === $c['delivery'] ) ? 'Stop-desk' : 'Domicile';
	if ( $base_cost > 0 && $ship_cost <= 0 ) {
		$ship = new WC_Order_Item_Shipping();
		$ship->set_method_title( 'Livraison offerte — ' . AOD_COD_Data::wilaya_name( $c['wilaya'] ) . ' (' . $label . ')' );
		$ship->set_method_id( 'aod_cod' );
		$ship->set_total( 0 );
		$order->add_item( $ship );
	} elseif ( $ship_cost > 0 ) {
		$ship = new WC_Order_Item_Shipping();
		$ship->set_method_title( 'Livraison — ' . AOD_COD_Data::wilaya_name( $c['wilaya'] ) . ' (' . $label . ')' );
		$ship->set_method_id( 'aod_cod' );
		$ship->set_total( $ship_cost );
		$order->add_item( $ship );
	}

	$order->set_payment_method( 'cod' );
	$order->set_payment_method_title( 'Paiement à la livraison' );

	$order->update_meta_data( '_aod_wilaya_code', $c['wilaya'] );
	$order->update_meta_data( '_aod_wilaya_name', AOD_COD_Data::wilaya_name( $c['wilaya'] ) );
	$order->update_meta_data( '_aod_wilaya_name_ar', AOD_COD_Data::wilaya_name_ar( $c['wilaya'] ) );
	$order->update_meta_data( '_aod_commune', $c['commune'] );
	$order->update_meta_data( '_aod_commune_ar', AOD_COD_Data::commune_name_ar( $c['wilaya'], $c['commune'] ) );
	$order->update_meta_data( '_aod_delivery_type', $c['delivery'] );
	$order->update_meta_data( '_aod_source', 'aod-cod-form' );
	$order->update_meta_data( AOD_CMD_MARK, '1' );

	// Antidater AVANT le passage en `processing` : `update_status()` enregistre, et
	// une date posée après serait écrasée par la note de statut.
	$order->set_date_created( $ts );
	$order->calculate_totals();
	$order->update_status( 'processing', 'Commande COD via le formulaire AOD (jeu de démonstration).' );

	// 🚨 LE MÊME `do_action` QUE `handle_submit()`, ET IL N'EST PAS DÉCORATIF.
	// C'est lui — et lui seul — qui déclenche le tourniquet d'assignation
	// (`AOD_CD_Section_Orders::assign_least_loaded`, Phase 42.1). Une commande
	// écrite « à la main », si fidèle soit-elle par ailleurs, reste NON ASSIGNÉE :
	// on obtient un jeu d'essai où le badge « Non assignée » compte tout, alors
	// que le formulaire public, lui, répartit entre les confirmateurs éligibles.
	// Les abonnés à effet externe (WhatsApp, pixels) ont été débranchés plus haut.
	do_action( 'aod_cod_order_created', $order );

	// Le tourniquet a fait son propre `save()` : on relit pour ne pas réécrire
	// par-dessus son assignation avec un objet devenu périmé, puis on repose la
	// date (que `update_status()` et le tourniquet ont pu réenregistrer).
	$order = wc_get_order( $order->get_id() );
	$order->set_date_created( $ts );
	$order->save();

	return $order->get_id();
}

/**
 * Insère un prospect « panier abandonné » directement dans la table des leads.
 *
 * En SQL direct parce que `AOD_COD_Leads::upsert()` est `protected` et que son
 * point d'entrée public est l'AJAX (nonce, plafond IP, jeton de session) : le
 * rejouer depuis WP-CLI demanderait de simuler une requête HTTP pour écrire
 * quatre colonnes. La table est un contrat stable, versionné (`DB_VERSION`).
 *
 * @param array $l token, product_id, name, phone, wilaya, commune, delivery, qty, ts.
 * @return bool
 */
function aod_cmd_creer_prospect( array $l ) {
	global $wpdb;
	$at = gmdate( 'Y-m-d H:i:s', $l['ts'] );
	return false !== $wpdb->insert( AOD_COD_Leads::table(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		'token'              => $l['token'],
		'product_id'         => (int) $l['product_id'],
		'name'               => $l['name'],
		'phone'              => $l['phone'],
		'wilaya'             => (int) $l['wilaya'],
		'commune'            => $l['commune'],
		'delivery'           => $l['delivery'],
		'qty'                => max( 1, (int) $l['qty'] ),
		'status'             => 'pending',
		'order_id'           => 0,
		'assigned_confirmer' => 0,
		'assigned_by'        => 0,
		'created_at'         => $at,
		'updated_at'         => $at,
	) );
}

/* =========================================================================
 * LES 30 COMMANDES
 * ====================================================================== */

$C = $casque->get_id();
$S = $sac->get_id();

// Colonnes : nom · téléphone · wilaya · rang de commune · adresse · livraison
//            · produit · variante · quantité · il y a N minutes.
// Les téléphones respectent le format validé côté serveur (`0[5-7]\d{8}`) et sont
// tous distincts : deux commandes du même numéro se lisent comme un doublon.
// Les heures s'échelonnent sur 72 h, la moitié dans les 24 dernières — « comme si
// elles venaient d'entrer », mais pas toutes à la même seconde.
$aod_cmd_commandes = array(
	array( 'Yacine Belkacem',    '0551234087', 16, 0,  'Cité 200 logements, Bt C n°14',        'home', $C, 'Noir Onyx',      1, 12 ),
	array( 'Amina Haddad',       '0661902344', 31, 2,  '17 rue Larbi Ben M’hidi',              'home', $S, 'Vert Olive',     1, 27 ),
	array( 'Karim Meziane',      '0770458123', 25, 1,  'Lot Bekira, villa 8',                  'desk', $C, 'Blanc Perle',    2, 41 ),
	array( 'Sofiane Boudjema',   '0552877410', 9,  3,  'Cité El Feth, Bt 4',                   'home', $S, 'Noir Carbone',   1, 55 ),
	array( 'Nadia Cherif',       '0664130298', 6,  0,  'Rue des Frères Amrani',                'home', $C, 'Bordeaux',       1, 78 ),
	array( 'Bilal Ouali',        '0779654201', 19, 5,  'Cité Bel Air, Bt 12 n°3',              'desk', $S, 'Jaune Moutarde', 1, 96 ),
	array( 'Meriem Slimani',     '0553092716', 35, 2,  '22 rue de la Gare',                    'home', $C, 'Noir Onyx',      3, 115 ),
	array( 'Riad Benali',        '0668745039', 15, 4,  'Village Tala Athmane',                 'home', $S, 'Vert Olive',     2, 143 ),
	array( 'Lynda Ait Ouarab',   '0774318620', 5,  1,  'Cité Kechida, Bt 7',                   'desk', $C, 'Blanc Perle',    1, 172 ),
	array( 'Mohamed Zerrouki',   '0557201483', 23, 0,  'Rue Gambetta, n°9',                    'home', $S, 'Noir Carbone',   1, 205 ),
	array( 'Sarah Benkhelifa',   '0663558974', 13, 3,  'Cité Boudghene, Bt B',                 'home', $C, 'Noir Onyx',      1, 244 ),
	array( 'Walid Ghezali',      '0771620935', 16, 8,  '5 rue Didouche Mourad',                'desk', $S, 'Jaune Moutarde', 2, 288 ),
	array( 'Imane Larbi',        '0556483017', 2,  1,  'Cité 500 logements, Bt A',             'home', $C, 'Bordeaux',       1, 331 ),
	array( 'Nassim Hamdi',       '0669037142', 31, 7,  'Hai Es Salem, rue 12',                 'home', $S, 'Noir Carbone',   1, 385 ),
	array( 'Chaima Belhadj',     '0775204186', 27, 2,  'Rue de l’Indépendance',                'desk', $C, 'Blanc Perle',    1, 442 ),
	array( 'Anis Djaballah',     '0558316742', 7,  0,  'Cité 5 Juillet, Bt 3',                 'home', $S, 'Vert Olive',     1, 517 ),
	array( 'Hind Merabet',       '0662891053', 16, 14, 'Résidence Les Pins, villa 3',          'home', $C, 'Noir Onyx',      2, 604 ),
	array( 'Toufik Saidi',       '0778145236', 21, 1,  'Rue Emir Abdelkader',                  'desk', $S, 'Noir Carbone',   1, 690 ),
	array( 'Rania Belaid',       '0554720369', 18, 4,  'Cité Sidi Ali, Bt 6',                  'home', $C, 'Bordeaux',       1, 782 ),
	array( 'Fouad Kaci',         '0667304815', 44, 2,  'Rue des Martyrs, n°21',                'home', $S, 'Jaune Moutarde', 1, 878 ),
	array( 'Selma Tounsi',       '0772936048', 30, 0,  'Hai En Nasr, Bt 9',                    'desk', $C, 'Noir Onyx',      1, 967 ),
	array( 'Hamza Rahmani',      '0559648213', 10, 3,  'Cité Bouzegza, villa 11',              'home', $S, 'Vert Olive',     1, 1063 ),
	array( 'Kenza Amrani',       '0665127930', 42, 1,  'Rue Ali Khodja',                       'home', $C, 'Blanc Perle',    1, 1158 ),
	array( 'Adel Bouzid',        '0773581462', 34, 5,  'Cité El Wiam, Bt 2',                   'desk', $S, 'Noir Carbone',   2, 1249 ),
	array( 'Nour El Houda Sahli','0555813627', 26, 2,  'Rue de la Liberté, n°7',               'home', $C, 'Bordeaux',       1, 1342 ),
	array( 'Djamel Ferhat',      '0660495782', 47, 0,  'Ksar Melika, rue 4',                   'home', $S, 'Jaune Moutarde', 1, 1436 ),
	array( 'Assia Benmoussa',    '0776023419', 12, 1,  'Cité Zaouia, Bt 5',                    'desk', $C, 'Noir Onyx',      1, 1531 ),
	array( 'Ilyes Berrahal',     '0551876204', 29, 3,  'Rue Colonel Amirouche',                'home', $S, 'Vert Olive',     1, 1627 ),
	array( 'Souad Guerfi',       '0666209431', 46, 2,  'Cité Sidi Yacoub, Bt 8',               'home', $C, 'Blanc Perle',    1, 1723 ),
	array( 'Redouane Lahmar',    '0779312058', 39, 0,  'Hai El Badr, n°16',                    'desk', $S, 'Noir Carbone',   1, 1820 ),
);

$aod_cmd_now  = time();
$aod_cmd_ids  = array();
$aod_cmd_fail = 0;

foreach ( $aod_cmd_commandes as $row ) {
	list( $name, $phone, $wilaya, $rang, $address, $delivery, $pid, $variante, $qty, $il_y_a ) = $row;

	$client = array(
		'name'     => $name,
		'phone'    => $phone,
		'wilaya'   => $wilaya,
		'commune'  => aod_cmd_commune( $wilaya, $rang ),
		'address'  => $address,
		'delivery' => $delivery,
	);
	$items = array( array(
		'product_id' => $pid,
		'opt'        => array( 0 => $variante ), // section 0 = « Couleur » sur les deux produits.
		'qty'        => $qty,
	) );

	$oid = aod_cmd_creer_commande( $client, $items, $aod_cmd_now - ( $il_y_a * 60 ) );
	if ( $oid ) {
		$aod_cmd_ids[] = $oid;
	} else {
		$aod_cmd_fail++;
	}
}

/* =========================================================================
 * LES 15 PANIERS ABANDONNÉS
 * ====================================================================== */

// Un prospect, c'est un formulaire COMMENCÉ : le téléphone est la seule donnée
// toujours présente (c'est lui qui déclenche la capture). Le reste manque à des
// degrés divers, et c'est précisément ce que le gérant doit voir — un prospect
// complet ressemblerait à une commande qu'on aurait oublié de valider.
// Colonnes : nom · téléphone · wilaya · rang commune · livraison · qty · produit
//            · il y a N minutes (tous > 30 min : le seuil d'« abandonné »).
$aod_cmd_prospects = array(
	array( 'Sabrina Kadri',   '0557412896', 16, 1,  'home', 1, $C, 47 ),
	array( '',                '0668530147', 31, 4,  'home', 1, $S, 63 ),
	array( 'Mourad Belkhir',  '0771968402', 25, 0,  'desk', 2, $C, 88 ),
	array( 'Hocine Talbi',    '0553260918', 0,  0,  '',     1, $S, 124 ),
	array( 'Yasmine Oukaci',  '0664815203', 9,  2,  'home', 1, $C, 176 ),
	array( '',                '0778093261', 0,  0,  '',     1, $C, 233 ),
	array( 'Farid Menasria',  '0556139784', 19, 3,  'home', 3, $S, 298 ),
	array( 'Lamia Zeroual',   '0661472085', 15, 1,  'desk', 1, $C, 371 ),
	array( 'Samir Boukhalfa', '0774806139', 23, 0,  'home', 1, $S, 466 ),
	array( 'Nawel Hamidi',    '0552947613', 6,  5,  'home', 2, $C, 588 ),
	array( '',                '0667158240', 35, 0,  '',     1, $S, 715 ),
	array( 'Rachid Ammar',    '0770634195', 13, 2,  'desk', 1, $C, 892 ),
	array( 'Dalila Benyoub',  '0558471029', 5,  1,  'home', 1, $S, 1104 ),
	array( 'Zakaria Loucif',  '0663520871', 30, 0,  'home', 1, $C, 1388 ),
	array( 'Meriem Bouaziz',  '0775190346', 2,  3,  'desk', 2, $S, 1742 ),
);

$aod_cmd_leads = 0;
foreach ( $aod_cmd_prospects as $i => $row ) {
	list( $name, $phone, $wilaya, $rang, $delivery, $qty, $pid, $il_y_a ) = $row;
	$ok = aod_cmd_creer_prospect( array(
		// Jeton préfixé : c'est LUI qui rend la purge chirurgicale (colonne indexée).
		'token'      => AOD_CMD_LEAD_PREFIX . str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) . '-' . wp_generate_password( 8, false ),
		'product_id' => $pid,
		'name'       => $name,
		'phone'      => $phone,
		'wilaya'     => $wilaya,
		'commune'    => $wilaya ? aod_cmd_commune( $wilaya, $rang ) : '',
		'delivery'   => $delivery,
		'qty'        => $qty,
		'ts'         => $aod_cmd_now - ( $il_y_a * 60 ),
	) );
	if ( $ok ) {
		$aod_cmd_leads++;
	}
}

/* =========================================================================
 * SORTIE
 * ====================================================================== */

$aod_cmd_ca = 0.0;
foreach ( $aod_cmd_ids as $oid ) {
	$o = wc_get_order( $oid );
	if ( $o ) {
		$aod_cmd_ca += (float) $o->get_total();
	}
}

WP_CLI::log( 'COMMANDES=' . count( $aod_cmd_ids ) . ( $aod_cmd_fail ? ' (refusées : ' . $aod_cmd_fail . ')' : '' ) );
WP_CLI::log( 'PROSPECTS=' . $aod_cmd_leads );
WP_CLI::log( 'TOTAL_DA=' . number_format( $aod_cmd_ca, 2, '.', '' ) );
WP_CLI::log( 'STOCK_CASQUE=' . wc_get_product( $C )->get_stock_quantity() );
WP_CLI::log( 'STOCK_SAC=' . wc_get_product( $S )->get_stock_quantity() );
WP_CLI::log( 'TARIFS_COMBLES=' . count( $aod_cmd_filled ) . ' wilaya(s)' );

// Répartition réelle du tourniquet — affichée, jamais supposée. Si le vivier de
// confirmateurs est vide, la ligne dit « non assignées : 30 » au lieu de laisser
// croire à une répartition qui n'a pas eu lieu.
$aod_cmd_par_conf = array();
foreach ( $aod_cmd_ids as $oid ) {
	$o   = wc_get_order( $oid );
	$uid = $o ? (int) $o->get_meta( '_aod_assigned_confirmer' ) : 0;
	$key = $uid ? ( get_userdata( $uid ) ? get_userdata( $uid )->user_login : 'user#' . $uid ) : 'NON ASSIGNÉES';
	$aod_cmd_par_conf[ $key ] = ( isset( $aod_cmd_par_conf[ $key ] ) ? $aod_cmd_par_conf[ $key ] : 0 ) + 1;
}
foreach ( $aod_cmd_par_conf as $who => $n ) {
	WP_CLI::log( 'ASSIGNÉES → ' . $who . ' : ' . $n );
}

if ( $aod_cmd_fail ) {
	WP_CLI::warning( $aod_cmd_fail . ' commande(s) refusée(s) — voir les avertissements ci-dessus (stock ou variante).' );
}
WP_CLI::success( count( $aod_cmd_ids ) . ' commande(s) « en cours » et ' . $aod_cmd_leads . ' panier(s) abandonné(s) créés — aucune confirmée.' );
