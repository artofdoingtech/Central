<?php
/**
 * Porte de recette « charge » — l'Accueil de /gestion tient-il à volume ? (31.6)
 *
 * Lancement : `bash tools/recette/run.sh charge`
 * (serveur, sans navigateur — même forme que `recette-capi.php`).
 *
 * 🚨 POURQUOI CETTE PORTE EXISTE. Aucune porte du dépôt ne SÈME du volume. Les
 * défauts de tenue en charge ne peuvent donc se manifester nulle part en
 * recette : ils resteront verts jusqu'au jour où un clone réussit
 * commercialement — c'est-à-dire au pire moment possible, chez le client qui a
 * le plus à perdre. Un défaut de charge ne casse rien tant que le volume est
 * faible ; il ne se voit pas, il se mesure.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * 🚨 SÉCURITÉ DES DONNÉES — la partie à lire avant de toucher à ce fichier
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Cette porte est la SEULE du dépôt à écrire massivement. Elle tourne sur le
 * clone d'un vrai client, dont les commandes sont son gagne-pain. Trois règles,
 * dans cet ordre :
 *
 *   1. **Tout ce qu'elle crée porte une marque** (`_aod_charge_temoin`), et la
 *      purge ne supprime QUE ce qui la porte. Jamais un `DELETE` par date, par
 *      statut ou par plage d'ID : une commande réelle peut toujours tomber dans
 *      un critère « qui ne devrait concerner que les nôtres ».
 *   2. **On compte le réel AVANT, on le recompte APRÈS**, et l'écart doit être
 *      nul. Sans cette mesure, une purge trop large ne se verrait pas — elle
 *      rendrait le même « ✅ purgé » qu'une purge correcte.
 *   3. **Si la purge échoue, la porte échoue.** Elle ne laisse pas 10 000
 *      commandes fantômes dans le tableau de bord du gérant en se déclarant
 *      verte sur la performance.
 *
 * ⚠️ Leçon consignée en dette D26 : une sonde de recette a déjà effacé la grille
 * tarifaire de ce clone en postant une charge vide. On ne mesure pas « ce qu'on
 * croit avoir touché », on mesure l'état réel, avant et après.
 *
 * @package AOD_Recette
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Réglages
 * ---------------------------------------------------------------------- */

const AOD_CHARGE_META   = '_aod_charge_temoin';
const AOD_CHARGE_SKU    = 'AOD-CHARGE-31-6';
const AOD_CHARGE_CLIENT = 'ChargeRecette';

$aod_ch_n = (int) ( getenv( 'AOD_CHARGE_N' ) ?: 10000 );
$aod_ch_n = max( 100, min( 50000, $aod_ch_n ) );

/** Seuils d'échec. Généreux à dessein : on traque l'effondrement, pas la lenteur. */
$aod_ch_max_sec = (float) ( getenv( 'AOD_CHARGE_MAX_SEC' ) ?: 20.0 );
$aod_ch_max_mo  = (float) ( getenv( 'AOD_CHARGE_MAX_MO' ) ?: 256.0 );

/**
 * 🚨 LES COMPTEURS SONT STATIQUES, PAS GLOBAUX — et ce n'est pas un détail de
 * style. `wp eval-file` inclut ce fichier DANS une fonction : ses variables de
 * premier niveau ne sont donc PAS globales, et un `global $aod_ch_fail` dans
 * `aod_ch_ok()` pointerait sur une variable vide. Mesuré au pilote : la porte a
 * imprimé un ✗ puis conclu « 0 échec » — et serait sortie en code 0.
 *
 * C'est très exactement la classe de défaut corrigée par la 31.5 : un contrôle
 * qui rend vert sans rien prouver. Une porte qui ne sait pas compter ses propres
 * échecs est pire qu'une porte absente.
 */
function aod_ch_score( $ajout = null ) {
	static $pass = 0;
	static $fail = 0;
	if ( true === $ajout )  { $pass++; }
	if ( false === $ajout ) { $fail++; }
	return array( 'pass' => $pass, 'fail' => $fail );
}

function aod_ch_ok( $cond, $msg, $detail = '' ) {
	$cond = (bool) $cond;
	aod_ch_score( $cond );
	if ( $cond ) {
		echo "  \033[32m✓\033[0m {$msg}\n";
	} else {
		echo "  \033[31m✗\033[0m {$msg}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
	}
	return $cond;
}

function aod_ch_info( $msg ) {
	echo "  \033[2mⓘ\033[0m {$msg}\n";
}

/** Toutes les commandes témoins de cette porte (par la MARQUE, jamais par date/ID). */
function aod_ch_temoins() {
	return wc_get_orders( array(
		'limit'      => -1,
		'status'     => 'any',
		'type'       => 'shop_order',
		'return'     => 'ids',
		'meta_key'   => AOD_CHARGE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Porte de recette hors production : c'est la seule façon de retrouver EXACTEMENT ce que la porte a créé.
		'meta_value' => '1',             // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Idem.
	) );
}

/** Nombre de commandes RÉELLES (tout sauf nos témoins). */
function aod_ch_compte_reel() {
	global $wpdb;
	$table = method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' )
		&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
		? $wpdb->prefix . 'wc_orders'
		: $wpdb->posts;

	$temoins = aod_ch_temoins();
	$total   = ( $table === $wpdb->posts )
		? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'" ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Comptage de contrôle d'une porte de recette ; aucune API ne compte sans hydrater.
		: (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE type = 'shop_order'" );          // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Idem.

	return $total - count( $temoins );
}

// Les e-mails WooCommerce partent à chaque création/changement de statut : sur
// 10 000 commandes, c'est 30 000 tentatives SMTP vers un serveur absent. On les
// débranche le temps de la porte — on mesure des AGRÉGATS, pas la messagerie.
add_filter( 'woocommerce_email_enabled_new_order', '__return_false', 99 );
add_filter( 'woocommerce_email_enabled_customer_processing_order', '__return_false', 99 );
add_filter( 'woocommerce_email_enabled_customer_completed_order', '__return_false', 99 );
add_filter( 'pre_wp_mail', '__return_true', 99 );

echo "\n\033[1m▶ Tenue en charge de l'Accueil /gestion (31.6)\033[0m\n\n";

/* =========================================================================
 * 0 · GARDE-FOU — état réel AVANT toute écriture
 * ========================================================================= */

$aod_ch_reel_avant  = aod_ch_compte_reel();
$aod_ch_restes      = aod_ch_temoins();

aod_ch_info( sprintf( 'commandes réelles du clone, relevées AVANT : %d', $aod_ch_reel_avant ) );

if ( $aod_ch_restes ) {
	aod_ch_info( sprintf( 'purge de %d témoin(s) d’une exécution précédente', count( $aod_ch_restes ) ) );
	foreach ( $aod_ch_restes as $id ) {
		$o = wc_get_order( $id );
		if ( $o ) {
			$o->delete( true );
		}
	}
}

/* =========================================================================
 * 1 · Produit jetable + semis
 * ========================================================================= */

$aod_ch_pid = wc_get_product_id_by_sku( AOD_CHARGE_SKU );
if ( ! $aod_ch_pid ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'Produit charge 31.6' );
	$p->set_sku( AOD_CHARGE_SKU );
	$p->set_regular_price( 2500 );
	$p->set_catalog_visibility( 'hidden' );
	$p->set_status( 'private' );
	$aod_ch_pid = $p->save();
}
$aod_ch_prod = wc_get_product( $aod_ch_pid );
aod_ch_ok( $aod_ch_prod instanceof WC_Product, sprintf( 'Produit jetable prêt (#%d)', $aod_ch_pid ) );

echo "\n  semis de {$aod_ch_n} commandes témoins…\n";

$aod_ch_t0     = microtime( true );
$aod_ch_statuts = array( 'wc-processing', 'wc-aod-confirmed', 'wc-completed' );

for ( $i = 0; $i < $aod_ch_n; $i++ ) {
	$o = new WC_Order();
	$o->add_product( $aod_ch_prod, 1 + ( $i % 3 ) );
	$o->set_billing_first_name( AOD_CHARGE_CLIENT );
	$o->set_billing_last_name( 'N' . $i );
	$o->set_billing_phone( '0550000000' );
	$o->set_payment_method( 'cod' );
	// Réparties sur 90 jours : les bacs du graphe et le versionnement par bac de
	// date n'auraient aucun sens sur une seule journée.
	$o->set_date_created( time() - ( ( $i % 90 ) * DAY_IN_SECONDS ) - 3600 );
	$o->update_meta_data( AOD_CHARGE_META, '1' );
	$o->update_meta_data( '_aod_delivery_type', 0 === $i % 2 ? 'home' : 'desk' );
	$o->update_meta_data( '_aod_wilaya_code', 1 + ( $i % 58 ) );
	// 🚨 SANS CECI, DEUX DES TROIS MESURES PORTERAIENT SUR ZÉRO COMMANDE.
	// `delivery_data` ne retient que les commandes EXPÉDIÉES (méta de suivi) et
	// `confirmation_data` s'appuie sur `_aod_confirmed_by`. Mesuré au pilote :
	// 0.00 s et 0.01 s — les meilleurs temps possibles, obtenus sur un ensemble
	// vide. Une mesure vide n'est pas une mesure rapide.
	if ( 0 === $i % 3 ) {
		$o->update_meta_data( '_aod_ship_tracking', 'CHARGE-' . $i );
		$o->update_meta_data( '_aod_ship_status', 0 === $i % 6 ? 'delivered' : 'in_transit' );
	}
	$o->update_meta_data( '_aod_confirmed_by', 1 );
	$o->update_meta_data( '_aod_confirmed_at', gmdate( 'Y-m-d H:i:s', time() - ( ( $i % 90 ) * DAY_IN_SECONDS ) ) );
	$o->set_status( str_replace( 'wc-', '', $aod_ch_statuts[ $i % 3 ] ) );
	$o->calculate_totals( false );
	$o->save();

	if ( 0 === ( $i + 1 ) % 500 ) {
		echo '    ' . ( $i + 1 ) . "/{$aod_ch_n}\n";
	}
}

$aod_ch_semis_sec = microtime( true ) - $aod_ch_t0;
aod_ch_info( sprintf( 'semis terminé en %.1f s', $aod_ch_semis_sec ) );

$aod_ch_semees = count( aod_ch_temoins() );
aod_ch_ok(
	$aod_ch_semees === $aod_ch_n,
	sprintf( 'ANCRAGE : %d commandes témoins retrouvées par leur marque', $aod_ch_semees ),
	sprintf( 'attendu %d — sans cet ancrage, une mesure sur une base VIDE rendrait le meilleur temps possible', $aod_ch_n )
);

/* =========================================================================
 * 2 · MESURE — les agrégats de l'Accueil, cache FROID
 * ========================================================================= */

echo "\n  \033[1mmesure des agrégats (cache froid)\033[0m\n";

// Cache froid obligatoire : mesurer un transient chaud mesurerait `get_transient`.
AOD_CD_Section_Home::bump_cache_version();

$aod_ch_home = AOD_CD_Section_Home::instance();
$aod_ch_from = gmdate( 'Y-m-d', time() - 89 * DAY_IN_SECONDS );
$aod_ch_to   = gmdate( 'Y-m-d' );
$aod_ch_range = array( 'from' => $aod_ch_from, 'to' => $aod_ch_to, 'preset' => '90j' );
$aod_ch_filtres = array( 'produit' => 0, 'confirmateur' => 0 );

$aod_ch_mesures = array();

foreach ( array(
	'revenue_data'      => 'Revenus (CA, panier moyen, top produits)',
	'delivery_data'     => 'Livraison',
	'confirmation_data' => 'Confirmation',
) as $methode => $libelle ) {
	if ( ! method_exists( $aod_ch_home, $methode ) ) {
		aod_ch_info( "méthode {$methode} absente — ignorée" );
		continue;
	}
	AOD_CD_Section_Home::bump_cache_version();

	$m = new ReflectionMethod( 'AOD_CD_Section_Home', $methode );
	$m->setAccessible( true );

	// 🚨 CACHE D'OBJETS VIDÉ ENTRE CHAQUE AGRÉGAT.
	// Sans ça, le 2ᵉ et le 3ᵉ agrégat réutilisent les commandes que le 1ᵉʳ a
	// laissées chaudes et rendent des temps FLATTÉS — mesuré : 0,04 s pour
	// « Livraison » derrière « Revenus », contre 0,21 s mesurée seule. C'est un
	// artefact du harnais, qui enchaîne les trois dans UN processus : en
	// production chaque onglet est une requête distincte, à cache froid. Mesurer
	// l'artefact reviendrait à publier des chiffres que la production ne verra
	// jamais.
	wp_cache_flush();

	// 🚨 LE PIC DOIT ÊTRE REMIS À ZÉRO AVANT CHAQUE MESURE.
	// `memory_get_peak_usage()` est MONOTONE sur toute la durée du processus : le
	// semis de N commandes, juste avant, l'a déjà fait monter très haut. Sans ce
	// reset, on soustrait un pic hérité du semis et on croit mesurer l'agrégat —
	// mesuré : le « pic » ne bougeait quasiment plus quand on divisait par 8 la
	// taille des lots, ce qui était le signe que l'instrument, pas le code, était
	// en cause. Un instrument faux est pire qu'aucune mesure : il rend des
	// chiffres qu'on va citer.
	gc_collect_cycles();
	if ( function_exists( 'memory_reset_peak_usage' ) ) {
		memory_reset_peak_usage();
	}
	$mem0 = memory_get_usage( true );
	$t0   = microtime( true );
	$out = $m->invoke( $aod_ch_home, $aod_ch_range, $aod_ch_filtres );
	$sec = microtime( true ) - $t0;
	$mo  = ( memory_get_peak_usage( true ) - $mem0 ) / 1048576;

	$aod_ch_mesures[ $methode ] = array(
		'sec'     => $sec,
		'mo'      => $mo,
		'libelle' => $libelle,
		'vide'    => ! is_array( $out ) || ! array_filter( $out, function ( $v ) {
			return is_array( $v ) ? ! empty( $v ) : ! empty( $v );
		} ),
	);
	printf( "    %-22s %7.2f s   pic +%6.1f Mo\n", $methode, $sec, max( 0, $mo ) );
}

echo "\n";
foreach ( $aod_ch_mesures as $methode => $r ) {
	// ANCRAGE avant chronomètre : un agrégat qui n'a rien vu rend le MEILLEUR
	// temps possible. Sans cette ligne, un filtre devenu trop strict ferait
	// passer la porte au vert en n'auditant rien — le défaut que la 31.5 a
	// corrigé cinq fois ailleurs.
	aod_ch_ok(
		! $r['vide'],
		sprintf( 'ANCRAGE : %s a bien travaillé sur des données', $r['libelle'] ),
		'résultat vide : le chronomètre ci-dessous ne mesurerait rien'
	);
	aod_ch_ok(
		$r['sec'] <= $aod_ch_max_sec,
		sprintf( '%s : %.2f s (plafond %.0f s)', $r['libelle'], $r['sec'], $aod_ch_max_sec ),
		sprintf( 'à %d commandes, cet écran est inutilisable', $aod_ch_n )
	);
}

$aod_ch_pic = 0.0;
foreach ( $aod_ch_mesures as $r ) {
	$aod_ch_pic = max( $aod_ch_pic, $r['mo'] );
}
aod_ch_ok(
	$aod_ch_pic <= $aod_ch_max_mo,
	sprintf( 'Pic mémoire : +%.1f Mo (plafond %.0f Mo)', $aod_ch_pic, $aod_ch_max_mo ),
	'un dépassement de memory_limit rend un écran BLANC, pas un écran lent'
);

/* =========================================================================
 * 3 · Le cache versionné par BAC DE DATE
 *
 * Une commande d'aujourd'hui ne doit pas invalider les agrégats d'un mois
 * passé : ces chiffres ne peuvent plus changer.
 * ========================================================================= */

echo "\n  \033[1mversionnement du cache\033[0m\n";

if ( method_exists( 'AOD_CD_Section_Home', 'cache_version_for' ) ) {
	$vieux_avant = AOD_CD_Section_Home::cache_version_for( '2020-01-01', '2020-01-31' );
	$recent_avant = AOD_CD_Section_Home::cache_version_for( $aod_ch_from, $aod_ch_to );

	$o = new WC_Order();
	$o->add_product( $aod_ch_prod, 1 );
	$o->update_meta_data( AOD_CHARGE_META, '1' );
	$o->set_status( 'processing' );
	$o->calculate_totals( false );
	$o->save();

	$vieux_apres  = AOD_CD_Section_Home::cache_version_for( '2020-01-01', '2020-01-31' );
	$recent_apres = AOD_CD_Section_Home::cache_version_for( $aod_ch_from, $aod_ch_to );

	aod_ch_ok(
		$recent_apres !== $recent_avant,
		'ANCRAGE : une commande d’aujourd’hui invalide bien la période EN COURS'
	);
	aod_ch_ok(
		$vieux_apres === $vieux_avant,
		'…et n’invalide PAS les agrégats d’un mois passé (chiffres figés)',
		'le compteur global invalidait TOUT : sur une boutique à 1 commande / 3 min, un TTL de 5 min ne tenait jamais'
	);
} else {
	aod_ch_ok( false, 'AOD_CD_Section_Home::cache_version_for() existe', 'versionnement par bac de date non implémenté' );
}

/* =========================================================================
 * 4 · PURGE — et la preuve qu'elle n'a rien pris d'autre
 * ========================================================================= */

echo "\n  \033[1mpurge\033[0m\n";

foreach ( aod_ch_temoins() as $id ) {
	$o = wc_get_order( $id );
	if ( $o ) {
		$o->delete( true );
	}
}
if ( $aod_ch_prod instanceof WC_Product ) {
	$aod_ch_prod->delete( true );
}

$aod_ch_restants = count( aod_ch_temoins() );
aod_ch_ok( 0 === $aod_ch_restants, sprintf( 'Toutes les commandes témoins sont supprimées (%d restante(s))', $aod_ch_restants ) );

$aod_ch_reel_apres = aod_ch_compte_reel();
aod_ch_ok(
	$aod_ch_reel_apres === $aod_ch_reel_avant,
	sprintf( '🔒 Les commandes RÉELLES du clone sont intactes : %d avant, %d après', $aod_ch_reel_avant, $aod_ch_reel_apres ),
	'LA PORTE A DÉTRUIT DES DONNÉES DU CLIENT — cf. dette D26'
);

AOD_CD_Section_Home::bump_cache_version();

/* =========================================================================
 * Bilan
 * ========================================================================= */

echo "\n";
aod_ch_info( sprintf(
	'périmètre mesuré — %d commandes semées sur 90 jours, %d agrégat(s) chronométré(s), cache froid à chaque mesure',
	$aod_ch_n,
	count( $aod_ch_mesures )
) );

$aod_ch_score = aod_ch_score();
if ( $aod_ch_score['fail'] ) {
	echo "\n\033[31m✗ charge : {$aod_ch_score['pass']} passé(s), {$aod_ch_score['fail']} échec(s)\033[0m\n\n";
	exit( 1 );
}
if ( ! $aod_ch_score['pass'] ) {
	// Un compteur à zéro des DEUX côtés veut dire qu'aucun contrôle n'a joué :
	// c'est un échec, pas un succès. (Cf. la porte 17.0, verte sans rien jouer.)
	echo "\n\033[31m✗ charge : AUCUN contrôle n'a été exécuté\033[0m\n\n";
	exit( 1 );
}
echo "\n\033[32m✅ charge : {$aod_ch_score['pass']} passé(s), 0 échec\033[0m\n\n";
