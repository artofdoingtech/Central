<?php
/**
 * Confirmer une commande n'expédie plus rien : l'envoi est un GESTE (Phase 47.2).
 *
 * 🚨 LE DÉFAUT. `add_action( 'woocommerce_order_status_aod-confirmed', 'auto_send' )`
 * faisait partir un COLIS RÉEL — appel réseau, verrou, écriture du suivi — comme
 * effet de bord d'un changement de statut, dans la requête du confirmateur, écran
 * figé. Personne ne DÉCIDAIT cet envoi : on basculait un statut, et le colis
 * partait. C'est ce qui a fait vivre la recette dans la peur de ce statut —
 * `tools/recette/a11y-souris.js` l'évitait en écrivant, en toutes lettres,
 * « colis réel chez le livreur ». *Un produit dont la recette doit se détourner
 * est un produit qui a un problème de conception.*
 *
 * 🚨 CE FICHIER ARME LA SITUATION AVANT DE CONCLURE, ET C'EST TOUT LE SUJET.
 * Un clone de développement a `auto['enabled'] = 0` et aucun transporteur
 * configuré : « passer une commande à Confirmée n'expédie rien » y est vrai
 * DEPUIS TOUJOURS. Une assertion posée en l'état aurait été verte **avant comme
 * après** la 47.2 — elle n'aurait rien gardé du tout. R2 remet donc le monde
 * dans l'état où l'ancien code EXPÉDIAIT (transporteur bouchonné configuré,
 * `enabled = 1`, commande COD réelle) et vérifie que, malgré tout ça, rien ne
 * part. C'est la règle du témoin appliquée à un test d'ABSENCE : il faut prouver
 * que le déclencheur était armé.
 *
 * ⚠️ ON NE COMPTE PAS AVEC LE CHRONOMÈTRE ICI, ET C'EST DÉLIBÉRÉ.
 * `AOD_Carrier::chrono()` ne compte que les appels passés par `remote()` — un
 * transporteur bouchonné n'en fait aucun, donc le chrono resterait à 0 même
 * quand l'envoi a bien eu lieu, et l'ancrage inverse serait faux. On compte
 * l'invocation de `create_parcel()` sur le bouchon : c'est le geste qu'on
 * surveille, mesuré à sa source.
 *
 * ⚠️ IL ÉCRIT UNE COMMANDE JETABLE et la supprime en sortie (R4). Parc de
 * transporteurs et réglage global sauvegardés puis restaurés. Aucun appel réseau,
 * aucune clé d'API réelle lue.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Envoi = geste 47.2 — R0 : le socle a changé de nature' );

$aod_eg_ok = class_exists( 'AOD_Shipping' ) && method_exists( 'AOD_Shipping', 'ship_order' );
AOD_Test::ok(
	$aod_eg_ok,
	'`AOD_Shipping::ship_order()` existe (l’envoi est une primitive appelable)',
	$aod_eg_ok ? '' : 'méthode absente'
);
AOD_Test::ok(
	! method_exists( 'AOD_Shipping', 'auto_send' ),
	'🔴 `auto_send()` n’existe plus — le nom a suivi la nature',
	'un nom qui annonce « automatique » sur un geste manuel finit par être cru'
);

if ( ! $aod_eg_ok || ! function_exists( 'wc_create_order' ) ) {
	return;
}

/* =========================================================================
 * R1 — Plus AUCUN écouteur sur le statut « Confirmée »
 *
 * Lu dans `$wp_filter` — l'état RÉEL des hooks — et non dans la source. Un
 * `add_action` commenté satisferait une recherche textuelle ; seul le registre
 * dit ce qui est branché. C'est aussi ce qui attrape un hook rebranché AILLEURS
 * (un autre fichier, un plugin de clone).
 * ========================================================================= */

AOD_Test::group( 'Envoi = geste 47.2 — R1 : le statut « Confirmée » ne déclenche rien' );

global $wp_filter;
$aod_eg_hook = 'woocommerce_order_status_' . AOD_Shipping::STATUS;

// 🚨 LES FERMETURES COMPTENT AUSSI, ET CE N'EST PAS DE LA PARANOÏA — la première
// version de ce bloc ne regardait que les callbacks `array( $objet, 'methode' )`.
// Au red-proof, rebrancher le hook par une CLOSURE liée à `$this` l'a laissée
// VERTE : la mutation expédiait bel et bien, et R1 ne voyait rien. Or une closure
// est très exactement la forme qu'aurait un rebranchement « vite fait ».
// `getClosureThis()` donne l'objet lié — c'est lui qu'on interroge.
$aod_eg_ecouteurs = array();
$aod_eg_total     = 0;
if ( isset( $wp_filter[ $aod_eg_hook ] ) ) {
	foreach ( $wp_filter[ $aod_eg_hook ]->callbacks as $aod_eg_prio => $aod_eg_cbs ) {
		foreach ( $aod_eg_cbs as $aod_eg_cb ) {
			++$aod_eg_total;
			$aod_eg_f = $aod_eg_cb['function'];
			if ( is_array( $aod_eg_f ) && is_object( $aod_eg_f[0] ) && $aod_eg_f[0] instanceof AOD_Shipping ) {
				$aod_eg_ecouteurs[] = 'AOD_Shipping::' . $aod_eg_f[1];
			} elseif ( $aod_eg_f instanceof Closure ) {
				$aod_eg_lie = ( new ReflectionFunction( $aod_eg_f ) )->getClosureThis();
				if ( $aod_eg_lie instanceof AOD_Shipping ) {
					$aod_eg_ecouteurs[] = 'closure liée à AOD_Shipping';
				}
			}
		}
	}
}
AOD_Test::ok(
	empty( $aod_eg_ecouteurs ),
	sprintf( '🔴 `%s` : aucun écouteur d’AOD_Shipping (trouvé : %d)', $aod_eg_hook, count( $aod_eg_ecouteurs ) ),
	implode( ', ', $aod_eg_ecouteurs ) . ' — le hook rouvrirait d’un coup la latence de D27 dans la requête de l’utilisateur ET l’expédition que personne n’a décidée'
);

/* -------------------------------------------------------------------------
 * Bouchon : un transporteur qui COMPTE ses expéditions et ne parle à personne.
 * ---------------------------------------------------------------------- */

if ( ! class_exists( 'AOD_Test_Carrier_Compteur' ) ) {
	/**
	 * Transporteur de test : rend un faux colis et compte les appels.
	 */
	class AOD_Test_Carrier_Compteur extends AOD_Carrier {
		/** @var int Nombre d'appels à create_parcel() depuis le dernier reset. */
		public static $appels = 0;

		public static function reset() {
			self::$appels = 0;
		}
		public function id() {
			return 'bouchoncompteur';
		}
		public function label() {
			return 'Bouchon Compteur';
		}
		protected function defaults() {
			return array();
		}
		public function is_configured() {
			return true;
		}
		public function render_settings_fields() {}
		public function sanitize_settings( $input ) {
			return array();
		}
		public function build_payload( $order ) {
			return array();
		}
		public function create_parcel( $order ) {
			++self::$appels;
			return array(
				'tracking'  => 'TEST-47-2-' . (int) $order->get_id(),
				'label'     => '',
				'import_id' => 0,
			);
		}
	}
}

$aod_eg_ship = AOD_Shipping::instance();

$aod_eg_prop = new ReflectionProperty( 'AOD_Shipping', 'carriers' );
$aod_eg_prop->setAccessible( true );
$aod_eg_orig_carriers = $aod_eg_prop->getValue( $aod_eg_ship );
$aod_eg_orig_auto     = get_option( AOD_Shipping::AUTO_OPTION, array() );

// Le parc est réduit au bouchon : il devient donc l'UNIQUE transporteur
// configuré, et le réglage global le désigne. Rien ne peut « ne pas partir »
// faute de destination — si rien ne part, c'est bien parce que rien ne le
// déclenche.
$aod_eg_prop->setValue( $aod_eg_ship, array( 'bouchoncompteur' => new AOD_Test_Carrier_Compteur() ) );
update_option( AOD_Shipping::AUTO_OPTION, array( 'enabled' => 1, 'carrier' => 'bouchoncompteur' ) );

$aod_eg_cmd = wc_create_order();
if ( ! $aod_eg_cmd instanceof WC_Order ) {
	AOD_Test::ok( false, 'Commande jetable créée', 'wc_create_order() a échoué' );
	$aod_eg_prop->setValue( $aod_eg_ship, $aod_eg_orig_carriers );
	update_option( AOD_Shipping::AUTO_OPTION, $aod_eg_orig_auto );
	return;
}
// Une VRAIE commande COD : sans `_aod_delivery_type`, `ship_order()` refuse
// d'emblée et l'ancrage inverse de R2 serait faux pour la mauvaise raison.
$aod_eg_cmd->update_meta_data( '_aod_delivery_type', 'home' );
$aod_eg_cmd->update_meta_data( '_aod_wilaya_code', 16 );
$aod_eg_cmd->save();
$aod_eg_id = (int) $aod_eg_cmd->get_id();

/* =========================================================================
 * R2 — Le déclencheur est ARMÉ, et pourtant rien ne part
 * ========================================================================= */

AOD_Test::group( 'Envoi = geste 47.2 — R2 : confirmer n’expédie plus (déclencheur armé)' );

AOD_Test::ok(
	1 === count( $aod_eg_ship->configured() )
		&& 'bouchoncompteur' === $aod_eg_ship->auto_settings()['carrier']
		&& ! empty( $aod_eg_ship->auto_settings()['enabled'] ),
	'TÉMOIN : le monde est dans l’état où l’ANCIEN code expédiait (1 transporteur configuré, désigné, envoi auto « activé »)',
	'sans cet armement, « rien ne part » serait vrai depuis toujours sur un clone de dev — l’assertion ne garderait rien'
);

AOD_Test_Carrier_Compteur::reset();
$aod_eg_avant = wc_get_order( $aod_eg_id );
$aod_eg_avant->set_status( 'aod-confirmed' );
$aod_eg_avant->save();

$aod_eg_relu = wc_get_order( $aod_eg_id );
AOD_Test::ok(
	0 === AOD_Test_Carrier_Compteur::$appels,
	sprintf( '🔴 Passage à « Confirmée » → %d appel(s) au transporteur (attendu 0)', AOD_Test_Carrier_Compteur::$appels ),
	'le colis repart en effet de bord d’un changement de statut : c’est le défaut entier de la 47.2'
);
AOD_Test::ok(
	'' === (string) $aod_eg_relu->get_meta( AOD_Carrier::META_TRACKING ),
	'…et aucun numéro de suivi n’a été écrit',
	'assertion SECONDAIRE : une commande sans suivi ne prouve rien à elle seule (l’envoi a pu échouer) — c’est le compteur qui fait foi'
);
AOD_Test::ok(
	'aod-confirmed' === $aod_eg_relu->get_status(),
	'ANCRAGE : le statut a bien été appliqué (sinon rien ne se serait déclenché pour une autre raison)'
);

/* ---- ANCRAGE INVERSE : le geste, lui, expédie ---- */

AOD_Test_Carrier_Compteur::reset();
$aod_eg_res = $aod_eg_ship->ship_order( wc_get_order( $aod_eg_id ) );
$aod_eg_relu2 = wc_get_order( $aod_eg_id );

AOD_Test::ok(
	1 === AOD_Test_Carrier_Compteur::$appels && ! is_wp_error( $aod_eg_res ),
	sprintf( '🔴 ANCRAGE INVERSE : `ship_order()` expédie bel et bien (%d appel)', AOD_Test_Carrier_Compteur::$appels ),
	is_wp_error( $aod_eg_res ) ? $aod_eg_res->get_error_message() : 'sans cet ancrage, R2 serait vrai si l’envoi avait disparu tout court'
);
AOD_Test::ok(
	'TEST-47-2-' . $aod_eg_id === (string) $aod_eg_relu2->get_meta( AOD_Carrier::META_TRACKING ),
	'…et le suivi est enregistré par le goulot (`store_result()` intact)'
);

/* ---- La garde « déjà expédiée » tient, sur le nouveau chemin ---- */

AOD_Test_Carrier_Compteur::reset();
$aod_eg_res2 = $aod_eg_ship->ship_order( wc_get_order( $aod_eg_id ) );
AOD_Test::ok(
	is_wp_error( $aod_eg_res2 ) && 0 === AOD_Test_Carrier_Compteur::$appels,
	sprintf( '🔴 Un second envoi sur une commande DÉJÀ expédiée est refusé sans appeler personne (%d appel)', AOD_Test_Carrier_Compteur::$appels ),
	'c’est la garde qui empêche un double colis — donc un double encaissement COD chez le même client'
);

/* =========================================================================
 * R3 — Un refus de CONCURRENCE n'est toujours pas une panne (invariant 31.4)
 *
 * `ship_order()` rend désormais l'erreur au lieu d'un `return` muet : il faut
 * re-prouver qu'elle n'écrit RIEN sur la commande dans ce cas — sinon le gérant
 * chercherait une panne transporteur qui n'a jamais eu lieu.
 * ========================================================================= */

AOD_Test::group( 'Envoi = geste 47.2 — R3 : la concurrence n’est pas une panne' );

$aod_eg_isconc = new ReflectionMethod( 'AOD_Shipping', 'is_concurrency_error' );
$aod_eg_isconc->setAccessible( true );

foreach ( array( 'aod_ship_in_progress', 'aod_ship_already_sent' ) as $aod_eg_code ) {
	AOD_Test::ok(
		(bool) $aod_eg_isconc->invoke( $aod_eg_ship, new WP_Error( $aod_eg_code, 'x' ) ),
		sprintf( '`%s` est reconnu comme de la concurrence', $aod_eg_code )
	);
}
AOD_Test::ok(
	! $aod_eg_isconc->invoke( $aod_eg_ship, new WP_Error( 'aod_carrier_http', 'HTTP 500' ) ),
	'ANCRAGE INVERSE : une vraie panne transporteur reste une panne'
);

// 🚨 PREUVE FONCTIONNELLE, ET NON UNE LECTURE DE SOURCE — parce que la lecture
// de source a ÉCHOUÉ. La première version cherchait le motif
// `is_concurrency_error( $result ) ) { return $result;` dans le corps de la
// méthode. Au red-proof, désarmer la garde en écrivant `if ( false &&
// $this->is_concurrency_error( … ) )` l'a laissée VERTE : le fragment cherché
// est toujours là, précédé d'un `false &&` que le motif ne voit pas. Une
// assertion satisfaite par un FRAGMENT ne prouve rien — même famille que le trou
// trouvé au red-proof de la 47.1 (assertion satisfaite par un commentaire).
//
// On PROVOQUE donc la situation : le verrou d'envoi est posé à la main sur une
// commande neuve, `send_parcel()` refuse avec `aod_ship_in_progress`, et on
// regarde ce que la commande porte APRÈS.
$aod_eg_cmd2 = wc_create_order();
if ( $aod_eg_cmd2 instanceof WC_Order ) {
	$aod_eg_cmd2->update_meta_data( '_aod_delivery_type', 'home' );
	$aod_eg_cmd2->update_meta_data( '_aod_wilaya_code', 16 );
	// ⚠️ Statut CONFIRMÉ, sinon on ne mesure plus ce qu'on croit. `ship_order()`
	// refuse toute commande non confirmée (garde restituée après la revue de la
	// Phase 47 : la 47.2 avait emporté cette condition avec le hook). Sans cette
	// ligne, le refus attendu ici — `aod_ship_in_progress`, celui du VERROU —
	// serait remplacé par `aod_ship_not_confirmed`, et l'assertion mesurerait la
	// mauvaise garde tout en restant… rouge. `wc_create_order()` naît en `pending`.
	$aod_eg_cmd2->set_status( 'aod-confirmed' );
	$aod_eg_cmd2->save();
	$aod_eg_id2 = (int) $aod_eg_cmd2->get_id();

	$aod_eg_lock = new ReflectionMethod( 'AOD_Shipping', 'acquire_send_lock' );
	$aod_eg_lock->setAccessible( true );
	$aod_eg_unlock = new ReflectionMethod( 'AOD_Shipping', 'release_send_lock' );
	$aod_eg_unlock->setAccessible( true );

	AOD_Test::ok(
		(bool) $aod_eg_lock->invoke( $aod_eg_ship, $aod_eg_id2 ),
		'TÉMOIN : le verrou d’envoi est posé sur la commande témoin (sinon on mesurerait un envoi normal)'
	);

	AOD_Test_Carrier_Compteur::reset();
	// ⚠️ On compte les notes AVANT, et on mesure le DELTA. Compter « 0 note »
	// supposait une commande vierge d'historique — ce qui a cessé d'être vrai dès
	// qu'on l'arme au statut « Confirmée », car WooCommerce journalise tout
	// changement de statut. L'assertion serait devenue rouge sans qu'aucun défaut
	// n'existe : *une assertion sur un TOTAL mesure aussi tout ce que la fixture a
	// fait ; une assertion sur un DELTA ne mesure que le geste.*
	$aod_eg_notes_avant = count( wc_get_order_notes( array( 'order_id' => $aod_eg_id2 ) ) );
	$aod_eg_conc = $aod_eg_ship->ship_order( wc_get_order( $aod_eg_id2 ) );
	$aod_eg_apres = wc_get_order( $aod_eg_id2 );

	AOD_Test::ok(
		is_wp_error( $aod_eg_conc ) && 'aod_ship_in_progress' === $aod_eg_conc->get_error_code()
			&& 0 === AOD_Test_Carrier_Compteur::$appels,
		'Verrou tenu → refus `aod_ship_in_progress`, sans appeler le transporteur',
		is_wp_error( $aod_eg_conc ) ? $aod_eg_conc->get_error_code() : 'aucune erreur rendue'
	);
	AOD_Test::ok(
		'' === (string) $aod_eg_apres->get_meta( AOD_Carrier::META_ERROR ),
		'🔴 RIEN n’est écrit en erreur sur la commande — un doublon évité n’est pas une panne',
		'meta trouvée : « ' . (string) $aod_eg_apres->get_meta( AOD_Carrier::META_ERROR ) . ' » — le gérant chercherait une panne transporteur qui n’a jamais eu lieu'
	);
	AOD_Test::ok(
		$aod_eg_notes_avant === count( wc_get_order_notes( array( 'order_id' => $aod_eg_id2 ) ) ),
		'🔴 …et aucune note « envoi échoué » n’est ajoutée à l’historique',
		'la note survivrait à tout, et raconterait un incident inexistant'
	);

	$aod_eg_unlock->invoke( $aod_eg_ship, $aod_eg_id2 );
	$aod_eg_apres->delete( true );
}

/* =========================================================================
 * R4 — Aucun résidu
 * ========================================================================= */

AOD_Test::group( 'Envoi = geste 47.2 — R4 : aucun résidu' );

$aod_eg_prop->setValue( $aod_eg_ship, $aod_eg_orig_carriers );
if ( empty( $aod_eg_orig_auto ) ) {
	delete_option( AOD_Shipping::AUTO_OPTION );
} else {
	update_option( AOD_Shipping::AUTO_OPTION, $aod_eg_orig_auto );
}
AOD_Test::ok(
	get_option( AOD_Shipping::AUTO_OPTION, array() ) == $aod_eg_orig_auto, // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- comparaison de tableaux d'options.
	'Le réglage global de la boutique est remis exactement tel qu’il était'
);
AOD_Test::ok(
	count( $aod_eg_ship->carriers() ) === count( $aod_eg_orig_carriers ),
	sprintf( 'Le parc réel de transporteurs est restauré (%d)', count( $aod_eg_ship->carriers() ) )
);

$aod_eg_dispo = wc_get_order( $aod_eg_id );
if ( $aod_eg_dispo instanceof WC_Order ) {
	$aod_eg_dispo->delete( true );
}
AOD_Test::ok(
	! wc_get_order( $aod_eg_id ) instanceof WC_Order,
	'La commande jetable (et son faux suivi) est supprimée'
);
