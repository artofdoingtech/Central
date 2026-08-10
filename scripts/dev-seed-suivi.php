<?php
/**
 * Colis de DÉMONSTRATION pour « Suivi commande » — dev local uniquement.
 *
 * Créer :  docker compose exec -T wpcli wp eval-file /scripts/dev-seed-suivi.php create
 * Purger : docker compose exec -T wpcli wp eval-file /scripts/dev-seed-suivi.php cleanup
 *
 * Prérequis : des commandes existent déjà — `scripts/dev-seed-commandes.php create`
 * en fabrique trente en une fois.
 *
 * 🔑 CE SCRIPT NE CRÉE AUCUNE COMMANDE, ET C'EST LE POINT.
 * Expédier, dans ce produit, n'est pas créer un objet : c'est POSER QUATRE MÉTAS
 * sur une commande qui existe déjà (`AOD_Carrier::ship_order()` écrit
 * `_aod_ship_carrier`, `_aod_ship_tracking`, `_aod_ship_status`,
 * `_aod_ship_status_at`). Un semeur qui fabriquerait ses propres commandes
 * produirait un jeu d'essai à la forme APPROCHANTE — pas de client, pas de
 * lignes, pas d'assignation — et la page de suivi paraîtrait juste alors qu'elle
 * afficherait autre chose que la réalité. Ici, on marque de vraies commandes.
 *
 * ⚠️ AUCUN APPEL RÉSEAU, ET C'EST DÉLIBÉRÉ. `ship_order()` contacte le
 * transporteur : jouer ce chemin créerait des colis RÉELS, facturés, chez
 * Yalidine ou E-com Delivery. Le semeur écrit donc les mêmes métas à la main.
 * Corollaire assumé : les numéros de suivi sont fictifs, « Actualiser » sur une
 * de ces lignes échouera — c'est le comportement juste, l'API ne connaît pas ces
 * colis.
 *
 * CE QU'IL COUVRE — les NEUF états que la page sait afficher, y compris les deux
 * que personne ne voit jamais sur un clone de démonstration :
 *   · les 7 statuts normalisés (créé, transit, en livraison, livré, retourné,
 *     échec, annulé) ;
 *   · **2 colis BLOQUÉS** : statut non terminal + aucun événement depuis plus de
 *     `TRACK_BLOCKED_DAYS` jours. C'est le seul état que le produit CALCULE au
 *     lieu de le lire, donc le seul qu'un jeu d'essai naïf ne produit jamais.
 *
 * 🚨 `_aod_ship_status_at` EST UN TIMESTAMP UNIX, pas une date MySQL. Ce piège a
 * déjà coûté un jeu d'essai affichant « il y a 57 ans » (corrigé le 2026-08-08
 * dans `recette-seed-transverse.php`) : `current_time('mysql')` s'y écrit sans
 * erreur et se relit en 1970.
 *
 * Marque : méta `_aod_seed_suivi` sur les commandes touchées. `cleanup` ne retire
 * QUE les métas de suivi de ces commandes-là — il ne supprime aucune commande, et
 * ne touche pas à un colis réellement expédié.
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

const AOD_SUIVI_MARK = '_aod_seed_suivi';

/** Toutes les commandes marquées par ce semeur. */
function aod_suivi_marquees() {
	return wc_get_orders( array(
		'type'       => 'shop_order',
		'limit'      => -1,
		'meta_query' => array( array( 'key' => AOD_SUIVI_MARK, 'compare' => 'EXISTS' ) ),
	) );
}

/** Retire les métas de suivi posées par ce semeur (les commandes restent). */
function aod_suivi_cleanup() {
	$n = 0;
	foreach ( aod_suivi_marquees() as $o ) {
		foreach ( array(
			AOD_Carrier::META_CARRIER,
			AOD_Carrier::META_TRACKING,
			AOD_Carrier::META_STATUS,
			AOD_Carrier::META_STATUS_LABEL,
			AOD_Carrier::META_STATUS_AT,
			AOD_SUIVI_MARK,
		) as $k ) {
			$o->delete_meta_data( $k );
		}
		$o->save();
		++$n;
	}
	WP_CLI::log( sprintf( '↺ %d commande(s) dépouillée(s) de leur suivi de démonstration.', $n ) );
	return $n;
}

$aod_suivi_action = isset( $args[0] ) ? $args[0] : 'create';

if ( 'cleanup' === $aod_suivi_action ) {
	aod_suivi_cleanup();
	return;
}

// `create` est IDEMPOTENT : il purge d'abord sa propre marque. Relancé, il rend
// exactement le même jeu — sinon deux passages doubleraient les colis et la page
// mentirait sur le volume.
aod_suivi_cleanup();

$aod_suivi_jour = DAY_IN_SECONDS;
// Le seuil est LU sur la classe qui le fait respecter, pas recopié : un 7 en dur
// ici resterait vert le jour où le produit passerait à 10, et le semeur
// prétendrait produire des colis bloqués qui ne le seraient plus. Repli à 7
// seulement si le tableau de bord n'est pas chargé (le semeur n'en dépend pas).
$aod_suivi_bloc = defined( 'AOD_CD_Section_Tracking::TRACK_BLOCKED_DAYS' )
	? (int) constant( 'AOD_CD_Section_Tracking::TRACK_BLOCKED_DAYS' )
	: 7;

/*
 * Le scénario. `il_y_a` est l'âge du DERNIER événement de suivi, en jours : c'est
 * lui qui décide si un colis paraît vivant ou bloqué. Les transporteurs sont pris
 * parmi ceux que ce clone a configurés (cf. `AOD_Shipping::configured()`), plus
 * `yalidine` qui reste le plus courant en Algérie.
 */
$aod_suivi_plan = array(
	array( 'st' => AOD_Carrier::ST_DELIVERED,        'car' => 'yalidine',      'il_y_a' => 0.4, 'lib' => 'Livré au client' ),
	array( 'st' => AOD_Carrier::ST_OUT_FOR_DELIVERY, 'car' => 'zrexpress',     'il_y_a' => 0.1, 'lib' => 'Sorti en livraison' ),
	array( 'st' => AOD_Carrier::ST_IN_TRANSIT,       'car' => 'yalidine',      'il_y_a' => 0.6, 'lib' => 'Au centre de tri d’Alger' ),
	array( 'st' => AOD_Carrier::ST_IN_TRANSIT,       'car' => 'dhd',           'il_y_a' => 1.2, 'lib' => 'En route vers Oran' ),
	array( 'st' => AOD_Carrier::ST_CREATED,          'car' => 'allolivraison', 'il_y_a' => 0.2, 'lib' => 'Colis enregistré' ),
	array( 'st' => AOD_Carrier::ST_FAILED,           'car' => 'zrexpress',     'il_y_a' => 1.0, 'lib' => 'Client injoignable — 2ᵉ tentative demain' ),
	array( 'st' => AOD_Carrier::ST_DELIVERED,        'car' => 'dhd',           'il_y_a' => 2.5, 'lib' => 'Livré au client' ),
	array( 'st' => AOD_Carrier::ST_RETURNED,         'car' => 'yalidine',      'il_y_a' => 3.0, 'lib' => 'Retourné à l’expéditeur' ),
	array( 'st' => AOD_Carrier::ST_CANCELLED,        'car' => 'allolivraison', 'il_y_a' => 4.0, 'lib' => 'Annulé avant ramassage' ),
	// ⛔ LES DEUX BLOQUÉS. Statut NON terminal + dernier événement au-delà du
	// seuil : la page les marque « Bloqué » sans qu'aucun transporteur ne l'ait
	// dit — c'est elle qui le déduit du silence.
	array( 'st' => AOD_Carrier::ST_IN_TRANSIT,       'car' => 'zrexpress',     'il_y_a' => 11.0, 'lib' => 'Au centre de tri' ),
	array( 'st' => AOD_Carrier::ST_CREATED,          'car' => 'yalidine',      'il_y_a' => 16.0, 'lib' => 'Colis enregistré' ),
);

// Des commandes RÉELLES du clone, les plus récentes, et jamais une qui porte déjà
// un suivi : écraser un vrai numéro de colis par un faux serait la seule façon
// pour ce script de faire des dégâts.
$aod_suivi_vivier = wc_get_orders( array(
	'type'    => 'shop_order',
	'limit'   => 60,
	'orderby' => 'date',
	'order'   => 'DESC',
) );

$aod_suivi_libres = array();
foreach ( $aod_suivi_vivier as $o ) {
	if ( '' === (string) $o->get_meta( AOD_Carrier::META_TRACKING ) ) {
		$aod_suivi_libres[] = $o;
	}
}

if ( count( $aod_suivi_libres ) < count( $aod_suivi_plan ) ) {
	WP_CLI::warning( sprintf(
		'%d commande(s) sans suivi disponible(s) pour %d colis prévus — lance d’abord « dev-seed-commandes.php create ».',
		count( $aod_suivi_libres ),
		count( $aod_suivi_plan )
	) );
}

$aod_suivi_n = 0;
foreach ( $aod_suivi_plan as $i => $p ) {
	if ( ! isset( $aod_suivi_libres[ $i ] ) ) {
		break;
	}
	$o  = $aod_suivi_libres[ $i ];
	$at = time() - (int) round( $p['il_y_a'] * $aod_suivi_jour );

	$o->update_meta_data( AOD_Carrier::META_CARRIER, $p['car'] );
	$o->update_meta_data( AOD_Carrier::META_TRACKING, sprintf( 'DEMO-%s-%04d', strtoupper( substr( $p['car'], 0, 3 ) ), 1000 + $i ) );
	$o->update_meta_data( AOD_Carrier::META_STATUS, $p['st'] );
	$o->update_meta_data( AOD_Carrier::META_STATUS_LABEL, $p['lib'] );
	// 🚨 Timestamp UNIX — cf. l'avertissement en tête de fichier.
	$o->update_meta_data( AOD_Carrier::META_STATUS_AT, $at );
	$o->update_meta_data( AOD_SUIVI_MARK, 1 );
	$o->save();
	++$aod_suivi_n;
}

WP_CLI::success( sprintf(
	'%d colis de démonstration posés (dont 2 « bloqués » : dernier événement il y a 11 et 16 jours, seuil %d).',
	$aod_suivi_n,
	$aod_suivi_bloc
) );
WP_CLI::log( 'Purge : wp eval-file /scripts/dev-seed-suivi.php cleanup' );
