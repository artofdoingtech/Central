<?php
/**
 * Fixtures de la recette « parcours confirmateur » (Phase 22, étape 22.2).
 *
 * Créer  :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase22.php create
 * Purger :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase22.php cleanup
 *
 * `create` écrit `P22=1`, `P22LOGIN=<login>` et `P22PASS=<mot de passe>`
 * (jetons lus par run.sh, passés à la porte en variables d'environnement).
 *
 * CE QU'ON SÈME — un confirmateur portant 4 des 7 CONFIRMER_CAPS :
 *
 *   accordées : aod_cap_confirm_orders · aod_cap_shipping_prices
 *               aod_cap_stock          · aod_cap_tracking
 *   refusées  : aod_cap_abandoned · aod_cap_stats_confirm · aod_cap_stats_delivery
 *
 * 🚨 POURQUOI UN JEU *PARTIEL*. Un confirmateur « tout coché » ne prouverait que la
 * moitié du contrat : que les droits accordés fonctionnent. Jamais que les droits
 * refusés bloquent — et c'est la direction dangereuse, celle des TROUS. Le jeu
 * ci-dessus rend les deux mesurables dans un seul parcours.
 *
 * 🚨 POURQUOI CES 4-LÀ. `aod_cap_shipping_prices` est exactement la capability dont
 * `ajax_test_carrier` divergeait avant la Phase 21 (il exigeait `manage_woocommerce`).
 * La porte que ce compte alimente AURAIT DONC ROUGI sur ce défaut : c'est le témoin
 * de régression de la phase précédente, pas un choix décoratif.
 *
 * Les caps sont posées PAR UTILISATEUR (`WP_User::add_cap()`), jamais sur le rôle :
 * le rôle confirmateur reste vide, les droits sont à la carte. Cf. AOD_CD_Roles.
 *
 * ⚠️ PURGE CIBLÉE, JAMAIS PAR RÔLE. Un clone en service porte de VRAIS confirmateurs
 * créés par le gérant dans la section Équipe. Une purge `--role=aod_confirmateur`
 * les détruirait — c'est le mode d'échec exact qu'a déjà eu `restore_accent()`, qui
 * « restaurait » l'accent en le supprimant. `cleanup` ne touche QU'AUX comptes
 * portant la méta AOD_P22_MARK.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'AOD_CD_Roles' ) ) {
	WP_CLI::error( 'Le plugin aod-client-dashboard n’est pas actif.' );
}

const AOD_P22_MARK   = '_aod_seed_phase22';
const AOD_P22_LOGIN  = 'aod_recette_conf22';
const AOD_P22_LOGIN2 = 'aod_recette_conf22b';

/** Marque des COMMANDES témoins (Phase 47.5) + option où leurs IDs sont consignés. */
const AOD_P22_OMARK  = '_aod_seed_phase22_order';
const AOD_P22_OOPT   = 'aod_p22_seed_orders';

/** Confirmations attribuées au compte B, puis à « quelqu'un d'autre ». */
const AOD_P22_CONF_B      = 3;
const AOD_P22_CONF_AUTRES = 5;

/**
 * Commandes ASSIGNÉES à B (Phase 48) — volontairement ≠ AOD_P22_CONF_B.
 *
 * 🚨 SI CES DEUX NOMBRES ÉTAIENT ÉGAUX, LA PORTE NE PROUVERAIT RIEN : « la carte
 * affiche 3 » serait vrai que le code lise les commandes assignées ou les
 * confirmations. Assignation et confirmation sont deux notions orthogonales
 * (l'une est un état PRÉSENT, réassignable ; l'autre est FIGÉE) — le décor doit
 * les croiser pour que l'assertion puisse les distinguer.
 */
const AOD_P22_ASG_B = 4;

/** Les 4 capabilities du compte A (sous-ensemble de CONFIRMER_CAPS). */
function aod_p22_caps() {
	return array(
		'aod_cap_confirm_orders',
		'aod_cap_shipping_prices',
		'aod_cap_stock',
		'aod_cap_tracking',
	);
}

/**
 * Compte B — le COMPLÉMENT EXACT de A.
 *
 * 🚨 Pourquoi un second compte. Avec A seul, les 3 caps qu'il n'a pas ne sont
 * éprouvées que dans UN sens : « pas la cap → pas la section ». Rien ne prouve que
 * les accorder ouvrirait quoi que ce soit — une cap devenue inopérante (renommée,
 * jamais lue) rendrait exactement le même vert. B est le miroir : ce qui est fermé
 * chez A doit être ouvert chez B, et réciproquement. Les **7** capabilities sont
 * ainsi exercées **dans les deux sens**.
 *
 * A ∪ B = CONFIRMER_CAPS, et A ∩ B = ∅ — vérifié à l'exécution ci-dessous.
 */
function aod_p22_caps_b() {
	return array_values( array_diff( AOD_CD_Roles::CONFIRMER_CAPS, aod_p22_caps() ) );
}

/**
 * Crée un confirmateur marqué, pose ses caps, et RELIT ce qui a été réellement posé.
 *
 * Un `add_cap()` silencieusement sans effet rendrait la porte verte sur un compte
 * sans droits : elle croirait mesurer un refus légitime là où il n'y a qu'un seed
 * cassé. On échoue ici plutôt que de mentir plus loin.
 *
 * @param string   $login
 * @param string[] $caps
 * @return array{pass:string,caps:string[]}
 */
function aod_p22_creer( $login, $caps ) {
	$pass = wp_generate_password( 24, false );

	$uid = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => $pass,
			'user_email'   => $login . '@example.invalid',
			'display_name' => 'Recette ' . $login,
			'role'         => AOD_CD_ROLE_CONFIRMER,
		)
	);
	if ( is_wp_error( $uid ) ) {
		WP_CLI::error( 'Création de « ' . $login . ' » impossible : ' . $uid->get_error_message() );
	}

	update_user_meta( $uid, AOD_P22_MARK, '1' );

	$user = new WP_User( $uid );
	foreach ( $caps as $cap ) {
		$user->add_cap( $cap );
	}

	$relu = array();
	foreach ( AOD_CD_Roles::CONFIRMER_CAPS as $cap ) {
		if ( $user->has_cap( $cap ) ) {
			$relu[] = $cap;
		}
	}
	sort( $relu );
	$attendu = $caps;
	sort( $attendu );
	if ( $relu !== $attendu ) {
		WP_CLI::error(
			'« ' . $login . ' » : capabilities posées ≠ attendues. Posées : [' . implode( ',', $relu )
			. '] — attendues : [' . implode( ',', $attendu ) . ']'
		);
	}

	return array(
		'pass' => $pass,
		'caps' => $relu,
	);
}

/**
 * Sème les CONFIRMATIONS qui rendent l'onglet « Confirmation » discriminant (47.5).
 *
 * 🚨 POURQUOI IL FAUT SEMER. Sans ces fixtures, la porte serait CREUSE. Mesuré
 * sur ce clone au repos : UNE SEULE commande porte `_aod_confirmed_by` (celle de
 * l'admin). Le compte B n'ayant jamais confirmé, sa ligne vaut 0 et le total de
 * la boutique vaut 1 — un contrôle « B voit 0 » passerait donc aussi bien sur un
 * rendu CASSÉ (qui servirait le total de la boutique) que sur un rendu CORRECT,
 * et « 0 contre 1 » est un écart trop mince pour distinguer quoi que ce soit.
 *
 * Après semis : B lit **3**, la boutique en compte au moins **8** de plus, et le
 * pourcentage « Part » trahit le dénominateur global. Les trois nombres sont
 * distincts et non nuls — la porte peut affirmer l'écart DANS LES DEUX SENS.
 *
 * 🔴 AUCUN ENVOI TRANSPORTEUR. Ce clone porte un compte transporteur réellement
 * configuré. On n'écrit que des MÉTAS de commande ; le hook d'envoi automatique
 * (`woocommerce_order_status_aod-confirmed`) est débranché depuis la 47.2, et ces
 * commandes n'ont AUCUN article — rien à expédier, aucun stock à décrémenter.
 *
 * ⚠️ `_aod_confirmed_at` est en heure LOCALE du site (`wp_date`), jamais en UTC :
 * `confirmation_data()` exclut EN SILENCE un délai négatif, ce qui rendrait le
 * « Délai moyen » vide sans que rien ne le dise.
 *
 * @param int $uid_b Compte B — celui que la porte inspecte.
 * @param int $uid_a Compte A — « quelqu'un d'autre », pour que le total dépasse B.
 * @return int Nombre de commandes créées.
 */
function aod_p22_semer_confirmations( $uid_b, $uid_a ) {
	$ids = array();
	$plan = array(
		$uid_b => AOD_P22_CONF_B,
		$uid_a => AOD_P22_CONF_AUTRES,
	);

	foreach ( $plan as $uid => $combien ) {
		for ( $i = 0; $i < $combien; $i++ ) {
			$ts    = time() - ( 5 * DAY_IN_SECONDS ); // Dans la période par défaut (30 j).
			$order = new WC_Order();
			$order->set_date_created( $ts );
			$order->update_meta_data( AOD_P22_OMARK, '1' );
			$order->update_meta_data( '_aod_confirmed_by', (int) $uid );
			$order->update_meta_data( '_aod_confirmed_at', wp_date( 'Y-m-d H:i:s', $ts + DAY_IN_SECONDS ) );
			$order->set_status( 'completed' );
			$order->save();

			$ids[] = $order->get_id();
			// Consigné À CHAQUE PAS : une exécution interrompue reste purgeable.
			update_option( AOD_P22_OOPT, $ids, false );
		}
	}

	// Assignations de B (Phase 48) — POSÉES À PART, sur des commandes que B n'a
	// PAS confirmées, et en nombre différent. Les empiler sur les commandes déjà
	// tracées `_aod_confirmed_by = B` aurait donné 3 et 3 : la porte n'aurait
	// alors pas pu dire si la carte lit l'assignation ou la confirmation.
	// ⚠️ Elles portent la même marque `AOD_P22_OMARK` et entrent dans la même
	// liste d'IDs : la purge existante les emporte, sans second chemin de
	// nettoyage à tenir à jour.
	for ( $i = 0; $i < AOD_P22_ASG_B; $i++ ) {
		$ts    = time() - ( 5 * DAY_IN_SECONDS );
		$order = new WC_Order();
		$order->set_date_created( $ts );
		$order->update_meta_data( AOD_P22_OMARK, '1' );
		$order->update_meta_data( '_aod_assigned_confirmer', (int) $uid_b );
		$order->set_status( 'processing' ); // Assignée, PAS confirmée.
		$order->save();

		$ids[] = $order->get_id();
		update_option( AOD_P22_OOPT, $ids, false );
	}

	return count( $ids );
}

/**
 * Purge des commandes témoins — jamais `wp_delete_post()` (HPOS, TEMPLATE.md §3).
 *
 * Double contrôle avant un geste irréversible, comme pour les comptes : on relit
 * la marque sur chaque commande. Une liste d'IDs qui aurait dérivé ne doit pas
 * pouvoir emporter une VRAIE commande du gérant.
 */
function aod_p22_cleanup_orders() {
	$ids = (array) get_option( AOD_P22_OOPT, array() );
	$n   = 0;
	foreach ( $ids as $id ) {
		$o = wc_get_order( (int) $id );
		if ( ! $o instanceof WC_Order ) {
			continue;
		}
		if ( '1' !== (string) $o->get_meta( AOD_P22_OMARK ) ) {
			continue;
		}
		$o->delete( true );
		++$n;
	}
	delete_option( AOD_P22_OOPT );
	return $n;
}

/**
 * Purge idempotente : ne supprime QUE les comptes marqués.
 *
 * Le filtrage se fait EN PHP après un `get_users()` sur la méta — mais on ne se
 * fie pas qu'à la meta_query : on revérifie la méta sur chaque utilisateur avant
 * de le supprimer. Supprimer un compte est irréversible ; une meta_query qui
 * dérive (cf. la leçon `get_terms` + term-meta de ce dépôt) ne doit pas pouvoir
 * emporter un vrai confirmateur.
 */
function aod_p22_cleanup() {
	require_once ABSPATH . 'wp-admin/includes/user.php';

	$users = get_users(
		array(
			'meta_key'   => AOD_P22_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => '1',          // phpcs:ignore WordPress.DB.SlowDBQuery
			'number'     => -1,
			'fields'     => 'all',
		)
	);

	$n = 0;
	foreach ( $users as $u ) {
		// Double contrôle avant un geste irréversible.
		if ( '1' !== (string) get_user_meta( $u->ID, AOD_P22_MARK, true ) ) {
			continue;
		}
		if ( wp_delete_user( $u->ID ) ) {
			++$n;
		}
	}
	return $n;
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	WP_CLI::log( 'PURGECMD=' . aod_p22_cleanup_orders() );
	WP_CLI::log( 'PURGE=' . aod_p22_cleanup() );
	return;
}
if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-phase22.php create|cleanup' );
}

// Rejouable : purge d'abord tout reliquat d'une exécution interrompue.
aod_p22_cleanup_orders();
aod_p22_cleanup();

// Les deux jeux doivent PARTITIONNER CONFIRMER_CAPS : disjoints, et couvrant tout.
// Si quelqu'un ajoute une 8ᵉ capability sans toucher ici, elle tomberait dans B par
// `array_diff` — ce qui est le bon comportement — mais si A et B se recouvraient,
// la porte mesurerait deux fois la même chose en croyant couvrir les deux sens.
$caps_a = aod_p22_caps();
$caps_b = aod_p22_caps_b();
if ( array_intersect( $caps_a, $caps_b ) ) {
	WP_CLI::error( 'Les jeux A et B se recouvrent — la couverture « dans les deux sens » serait fausse.' );
}
if ( count( $caps_a ) + count( $caps_b ) !== count( AOD_CD_Roles::CONFIRMER_CAPS ) ) {
	WP_CLI::error( 'A ∪ B ne couvre pas CONFIRMER_CAPS — des capabilities ne seraient exercées dans AUCUN sens.' );
}

// Les mots de passe sont ENGENDRÉS à chaque exécution : rien à écrire dans le dépôt
// ni dans .env. Ils ne vivent que le temps de la recette, transmis à la porte par
// l'environnement (comme AOD_SOURIS_OID ou AOD_COD_PID).
$a = aod_p22_creer( AOD_P22_LOGIN, $caps_a );
$b = aod_p22_creer( AOD_P22_LOGIN2, $caps_b );

// B est celui qui porte `aod_cap_stats_confirm` : c'est SA ligne que la porte
// inspecte dans l'onglet Confirmation (47.5).
$uid_a = (int) get_user_by( 'login', AOD_P22_LOGIN )->ID;
$uid_b = (int) get_user_by( 'login', AOD_P22_LOGIN2 )->ID;
$semes = aod_p22_semer_confirmations( $uid_b, $uid_a );
// ⚠️ Le total inclut les commandes ASSIGNÉES (Phase 48), qui ne sont pas des
// confirmations. Ce contrôle a rougi à leur arrivée — et c'était son travail :
// il compte ce que la fonction a réellement créé, pas ce qu'on croit qu'elle
// crée. Le mettre à jour, jamais l'assouplir.
$semes_attendu = AOD_P22_CONF_B + AOD_P22_CONF_AUTRES + AOD_P22_ASG_B;
if ( $semes !== $semes_attendu ) {
	WP_CLI::error( 'Semis des confirmations incomplet : ' . $semes . ' commandes créées sur ' . $semes_attendu . '.' );
}

WP_CLI::log( 'P22=1' );
WP_CLI::log( 'P22CONFB=' . AOD_P22_CONF_B );
WP_CLI::log( 'P22ASGB=' . AOD_P22_ASG_B );
WP_CLI::log( 'P22LOGIN=' . AOD_P22_LOGIN );
WP_CLI::log( 'P22PASS=' . $a['pass'] );
WP_CLI::log( 'P22CAPS=' . implode( ',', $a['caps'] ) );
WP_CLI::log( 'P22LOGIN2=' . AOD_P22_LOGIN2 );
WP_CLI::log( 'P22PASS2=' . $b['pass'] );
WP_CLI::log( 'P22CAPS2=' . implode( ',', $b['caps'] ) );
