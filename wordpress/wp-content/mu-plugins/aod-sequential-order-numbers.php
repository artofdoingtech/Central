<?php
/**
 * Plugin Name: AOD — Numéros de commande séquentiels (à partir de 1)
 * Description: Attribue un numéro de commande séquentiel commençant à 1, indépendant de l'ID interne. Compatible HPOS.
 * Author: Art of Doing
 * Version: 1.0.0
 *
 * À déposer dans wp-content/mu-plugins/ (chargé automatiquement, pas besoin de l'activer).
 * Le compteur est stocké dans l'option 'aod_seq_order_counter' ; le mettre à 0 = repart à 1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AOD_SEQ_OPTION = 'aod_seq_order_counter';
const AOD_SEQ_META   = '_aod_seq_number';

/**
 * Réserve le prochain numéro, de façon ATOMIQUE. Une seule instruction SQL.
 *
 * 🚨 CE QUI ÉTAIT FAUX (Phase 31.2). Le compteur s'incrémentait en trois temps —
 * `get_option()`, `++`, `update_option()`. Entre la lecture et l'écriture, rien ne
 * retenait une seconde requête PHP : deux commandes soumises dans la même seconde
 * (le cas ORDINAIRE pendant une campagne pub) lisaient toutes deux N et écrivaient
 * toutes deux N+1. **Deux commandes portaient alors le même numéro** — celui donné
 * au client, imprimé sur le bordereau, et cherché au téléphone par le confirmateur.
 *
 * Le `static $in_progress` en place ne pouvait rien contre ça : il ne protège que
 * la RÉENTRANCE dans le même processus PHP (`$order->save()` re-déclenchant
 * `woocommerce_new_order` sous HPOS). Deux workers PHP-FPM ne partagent aucun
 * statique. La garde était bonne, elle ne traitait simplement pas ce risque-là.
 *
 * ⚠️ POURQUOI `LAST_INSERT_ID(expr)` ET PAS UNE RELECTURE. Incrémenter puis relire
 * l'option rouvrirait exactement la même fenêtre. `LAST_INSERT_ID(expr)` mémorise
 * la valeur affectée **pour la connexion courante** : chaque requête PHP relit
 * DONC LA SIENNE, jamais celle d'une voisine. C'est ce qui rend la réservation
 * atomique de bout en bout, et pas seulement l'écriture.
 *
 * ⚠️ ON ÉCRIT EN SQL DIRECT, donc le cache d'objets de WordPress ignore le
 * changement : sans la purge explicite, un `get_option()` plus loin dans la même
 * requête servirait l'ancienne valeur.
 *
 * Éprouvé au rouge : 6 processus concurrents, fenêtre élargie à 2 s. Motif d'avant
 * → 6 commandes numérotées « 1 ». Motif actuel → 1 à 6, distincts.
 *
 * ⚠️ `$option` n'est PAS un réglage : c'est une couture de test, et la seule du
 * fichier. Sans elle, le cliquet `tests/unit/test-numerotation-sequentielle.php`
 * devrait s'exercer sur le compteur RÉEL — donc avancer la numérotation des
 * commandes du client à chaque `bash tests/run.sh`. Un test n'a pas le droit
 * d'écrire dans les données de production pour se prouver lui-même. La production
 * n'appelle jamais cette fonction avec un argument.
 *
 * @param string $option Nom de l'option compteur. Bac à sable pour les tests.
 * @return int Numéro réservé (≥ 1), ou 0 si la réservation a échoué.
 */
function aod_seq_reserve_number( $option = AOD_SEQ_OPTION ) {
	global $wpdb;

	$sql = $wpdb->prepare(
		"UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s",
		$option
	);

	$maj = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Réservation atomique : l'API des options ne sait pas incrémenter sans lire d'abord. Requête préparée juste au-dessus ; cache purgé juste en dessous.

	if ( ! $maj ) {
		// Première commande du site : la ligne n'existe pas encore. `add_option()`
		// s'appuie sur l'index UNIQUE de `option_name` — si une autre requête l'a
		// créée dans l'intervalle, il renvoie false et on repasse par l'UPDATE.
		if ( add_option( $option, '1', '', false ) ) {
			return 1;
		}
		$maj = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Idem : seconde tentative après une création concurrente.
		if ( ! $maj ) {
			return 0;
		}
	}

	wp_cache_delete( $option, 'options' );

	return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
}

/**
 * Attribue le prochain numéro séquentiel à une commande qui n'en a pas encore.
 */
function aod_assign_sequential_number( $order ) {
	// Garde-fou : $order->save() ci-dessous re-déclenche 'woocommerce_new_order'
	// en HPOS ; ce verrou par commande évite un double incrément.
	static $in_progress = array();

	if ( ! ( $order instanceof WC_Order ) ) {
		return null;
	}
	$order_id = $order->get_id();
	if ( isset( $in_progress[ $order_id ] ) ) {
		return $order->get_meta( AOD_SEQ_META );
	}
	$existing = $order->get_meta( AOD_SEQ_META );
	if ( $existing ) {
		return $existing;
	}

	$in_progress[ $order_id ] = true;

	$counter = aod_seq_reserve_number();

	if ( $counter < 1 ) {
		// La réservation a échoué (base indisponible). On ne pose AUCUN numéro
		// plutôt qu'un zéro ou un doublon : la commande reste valide, et le filtre
		// d'affichage retombe sur le numéro interne de WooCommerce. Un numéro faux
		// serait pire qu'un numéro absent — il partirait sur le bordereau.
		unset( $in_progress[ $order_id ] );
		return null;
	}

	$order->update_meta_data( AOD_SEQ_META, $counter );
	$order->save();

	unset( $in_progress[ $order_id ] );

	return $counter;
}

// Attribution au moment où le formulaire COD a fini de construire la commande
// (action émise une seule fois par le plugin aod-cod-form -> numérotation
//  fiable, dans l'ordre de création). On évite 'woocommerce_new_order' qui se
//  déclenche en double sous HPOS.
add_action( 'aod_cod_order_created', 'aod_assign_sequential_number', 10, 1 );

// Filets de sécurité pour les commandes créées hors formulaire COD
// (checkout WooCommerce classique/blocks, création manuelle en admin…).
add_action( 'woocommerce_checkout_order_processed', function ( $order_id ) {
	aod_assign_sequential_number( wc_get_order( $order_id ) );
}, 10, 1 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'aod_assign_sequential_number', 10, 1 );

// Affichage : remplace le numéro affiché par le numéro séquentiel.
// IMPORTANT : ce filtre n'attribue JAMAIS de numéro (WooCommerce appelle
// get_order_number() de nombreuses fois en interne pendant la création, y
// compris sur des brouillons transitoires -> attribuer ici fausse le compteur).
// Il se contente d'afficher le numéro déjà attribué par les actions ci-dessus.
add_filter( 'woocommerce_order_number', function ( $number, $order ) {
	$seq = $order->get_meta( AOD_SEQ_META );
	return $seq ? (string) $seq : $number;
}, 10, 2 );
