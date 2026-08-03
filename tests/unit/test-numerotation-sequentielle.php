<?php
/**
 * Le numéro de commande est RÉSERVÉ, pas calculé (Phase 31.2).
 *
 * 🚨 LE DÉFAUT. `aod_assign_sequential_number()` incrémentait le compteur en trois
 * temps — `get_option()`, `++`, `update_option()`. Entre la lecture et l'écriture,
 * rien ne retenait une seconde requête PHP. Deux commandes soumises dans la même
 * seconde — le cas ORDINAIRE pendant une campagne publicitaire — lisaient toutes
 * deux N et écrivaient toutes deux N+1 : **deux commandes portaient le même
 * numéro**, celui donné au client, imprimé sur le bordereau du transporteur, et
 * cherché au téléphone par le confirmateur.
 *
 * Le `static $in_progress` du fichier ne protégeait que la réentrance dans UN
 * processus (`$order->save()` re-déclenchant `woocommerce_new_order` sous HPOS).
 * Deux workers PHP-FPM ne partagent aucun statique.
 *
 * 🚨 CE QUE CE TEST NE PROUVE PAS — et c'est important. Il tourne dans UN seul
 * processus : il ne peut pas provoquer la course. La preuve de concurrence a été
 * faite à la main, hors suite, avec 6 processus parallèles et la fenêtre élargie à
 * 2 s : le motif d'avant rendait 6 fois « 1 », le motif actuel 1 à 6. Ce que ce
 * fichier garde, c'est que le MOTIF ne redevienne pas celui d'avant — R2 — et que
 * la réservation reste monotone — R1.
 *
 * ⚠️ IL N'ÉCRIT JAMAIS DANS LE COMPTEUR RÉEL. Il travaille sur une option bac à
 * sable, et R3 le vérifie. Un test qui avancerait la numérotation des commandes du
 * client à chaque exécution serait un défaut, pas un contrôle.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Numérotation 31.2 — R0 : le socle est chargé' );

$aod_seq_ok = function_exists( 'aod_seq_reserve_number' ) && function_exists( 'aod_assign_sequential_number' );
AOD_Test::ok(
	$aod_seq_ok,
	'Le mu-plugin de numérotation séquentielle est chargé',
	$aod_seq_ok ? '' : 'aod_seq_reserve_number() introuvable — le mu-plugin n’est pas dans wp-content/mu-plugins/'
);

if ( ! $aod_seq_ok ) {
	return;
}

// Le compteur RÉEL, relevé AVANT toute manipulation. R3 le recomparera.
$aod_seq_reel_avant = get_option( 'aod_seq_order_counter', null );

$aod_seq_bac = 'aod_seq_test_sandbox';
delete_option( $aod_seq_bac );

/* =========================================================================
 * R1 — La réservation est monotone et ne rend jamais deux fois le même numéro
 * ========================================================================= */

AOD_Test::group( 'Numérotation 31.2 — R1 : chaque réservation rend un numéro neuf' );

$aod_seq_rendus = array();
for ( $aod_seq_i = 0; $aod_seq_i < 5; $aod_seq_i++ ) {
	$aod_seq_rendus[] = aod_seq_reserve_number( $aod_seq_bac );
}

AOD_Test::eq( array( 1, 2, 3, 4, 5 ), $aod_seq_rendus, 'Cinq réservations depuis un compteur neuf rendent 1, 2, 3, 4, 5' );

AOD_Test::eq(
	count( $aod_seq_rendus ),
	count( array_unique( $aod_seq_rendus ) ),
	'🚨 Aucun numéro n’est rendu deux fois (le défaut coûtait un doublon sur le bordereau)'
);

AOD_Test::eq( 5, (int) get_option( $aod_seq_bac ), 'Le compteur en base suit exactement le nombre de réservations' );

// Le numéro rendu vient bien de CETTE réservation, pas d'une relecture de
// l'option — c'est toute la différence entre `LAST_INSERT_ID(expr)` et un
// `get_option()` qui rouvrirait la fenêtre.
AOD_Test::eq( 6, aod_seq_reserve_number( $aod_seq_bac ), 'La réservation suivante enchaîne sans trou' );

/* =========================================================================
 * R2 — Le motif fautif ne peut pas revenir en silence
 * ========================================================================= */

AOD_Test::group( 'Numérotation 31.2 — R2 : la réservation reste ATOMIQUE' );

$aod_seq_src = ( new ReflectionFunction( 'aod_seq_reserve_number' ) );
$aod_seq_txt = implode(
	'',
	array_slice(
		(array) file( $aod_seq_src->getFileName() ),
		$aod_seq_src->getStartLine() - 1,
		$aod_seq_src->getEndLine() - $aod_seq_src->getStartLine() + 1
	)
);

AOD_Test::ok(
	false !== strpos( $aod_seq_txt, 'LAST_INSERT_ID(option_value + 1)' ),
	'L’incrément se fait en UNE instruction SQL (LAST_INSERT_ID)',
	'sans elle, lecture et écriture redeviennent deux temps séparés'
);

// La signature du défaut : lire le compteur en PHP pour le réécrire ensuite.
// `add_option()` est autorisé (création de la ligne, protégée par l'index UNIQUE) ;
// `get_option()` + `update_option()` sur le compteur ne le sont pas.
AOD_Test::ok(
	false === strpos( $aod_seq_txt, 'get_option(' ) && false === strpos( $aod_seq_txt, 'update_option(' ),
	'🚨 La réservation ne LIT PLUS le compteur en PHP pour le réécrire (la signature exacte du défaut)',
	'get_option/update_option retrouvés dans la réservation : la course est rouverte'
);

AOD_Test::ok(
	false !== strpos( $aod_seq_txt, 'wp_cache_delete(' ),
	'Le cache d’objets est purgé après l’écriture SQL directe',
	'sans purge, un get_option() ultérieur servirait l’ancienne valeur'
);

// L'appelant doit refuser de poser un numéro invalide plutôt que d'en inventer un.
$aod_seq_appelant = ( new ReflectionFunction( 'aod_assign_sequential_number' ) );
$aod_seq_atxt     = implode(
	'',
	array_slice(
		(array) file( $aod_seq_appelant->getFileName() ),
		$aod_seq_appelant->getStartLine() - 1,
		$aod_seq_appelant->getEndLine() - $aod_seq_appelant->getStartLine() + 1
	)
);

AOD_Test::ok(
	false !== strpos( $aod_seq_atxt, 'aod_seq_reserve_number()' ),
	'L’attribution passe par la réservation atomique'
);
AOD_Test::ok(
	false !== strpos( $aod_seq_atxt, '$counter < 1' ),
	'Une réservation échouée ne pose AUCUN numéro (jamais un 0 sur le bordereau)'
);

/* =========================================================================
 * R3 — Le test n'a pas touché aux données de production
 * =========================================================================
 *
 * 🚨 Cette règle existe parce que la faute a été commise, ici même, le
 * 2026-07-22 : une sonde de recette écrite « inoffensive » a posté une charge vide
 * sur `aod_cd_save_shipping` et EFFACÉ la grille tarifaire des 58 wilayas. Un
 * contrôle qui abîme ce qu'il contrôle n'est pas un contrôle.
 */

AOD_Test::group( 'Numérotation 31.2 — R3 : le contrôle n’abîme pas ce qu’il contrôle' );

delete_option( $aod_seq_bac );

AOD_Test::eq( false, get_option( $aod_seq_bac, false ), 'Le compteur bac à sable est purgé' );

AOD_Test::eq(
	$aod_seq_reel_avant,
	get_option( 'aod_seq_order_counter', null ),
	'🚨 Le compteur RÉEL des commandes est intact — le test n’a pas avancé la numérotation du client'
);
