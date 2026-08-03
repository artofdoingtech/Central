<?php
/**
 * La note du confirmateur voyage — et SEULEMENT là où elle peut voyager (Phase 47.3).
 *
 * Ce que ce cliquet garde tient en une phrase : `supports_driver_note()` ne doit
 * jamais mentir. L'écran s'appuie dessus pour prévenir le confirmateur que sa
 * consigne ne partira pas (décision D3 de §A.23) ; un transporteur qui répondrait
 * « oui » sans placer la note dans sa charge utile produirait exactement le défaut
 * MUET que la Phase 26 a passé son temps à chasser — pire que l'absence de note,
 * puisque l'écran affirmerait le contraire.
 *
 * 🚨 TOUTES LES RÈGLES SONT FONCTIONNELLES. Aucune ne cherche un motif dans la
 * source. C'est la leçon des deux étapes précédentes, et elle a coûté deux
 * red-proofs : en 47.1 une assertion se satisfaisait d'un COMMENTAIRE citant la
 * bonne méthode ; en 47.2 une autre se satisfaisait d'un FRAGMENT de source
 * toujours présent, précédé d'un `false &&` que le motif ne voyait pas. Ici on
 * construit les charges utiles et on y CHERCHE la note.
 *
 *   R0  témoins : les constantes existent, le registre compte 29 instances / 6 classes ;
 *   R1  la note est dans la charge utile SI ET SEULEMENT SI `supports_driver_note()` ;
 *   R2  la longueur est bornée, et l'arabe n'est jamais coupé en deux ;
 *   R3  sans note, la clé reste présente et vide (la charge utile ne se dégrade pas) ;
 *   R4  `aod_cd_order_note` n'existe plus, sous AUCUNE forme ;
 *   R5  `data-note-perdue` dit la vérité, sur les quatre croisements + le cas sans transporteur.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AOD_Shipping' ) || ! class_exists( 'AOD_Carrier' ) ) {
	AOD_Test::ok( false, 'Note livreur 47.3 — module d’expédition disponible', 'AOD_Shipping/AOD_Carrier introuvable' );
	return;
}

/* =========================================================================
 * R0 — Témoins
 * ========================================================================= */

AOD_Test::group( 'Note livreur 47.3 — R0 : témoins' );

AOD_Test::ok(
	defined( 'AOD_Carrier::META_DRIVER_NOTE' ) && '' !== AOD_Carrier::META_DRIVER_NOTE,
	sprintf( 'La méta de la note existe (%s)', defined( 'AOD_Carrier::META_DRIVER_NOTE' ) ? AOD_Carrier::META_DRIVER_NOTE : '—' ),
	'sans elle, tout le reste du fichier est sans objet'
);
AOD_Test::ok(
	defined( 'AOD_Carrier::NOTE_MAX' ) && AOD_Carrier::NOTE_MAX > 0,
	sprintf( 'La borne de longueur existe (%d caractères)', defined( 'AOD_Carrier::NOTE_MAX' ) ? AOD_Carrier::NOTE_MAX : 0 )
);

$aod_nl_ship     = AOD_Shipping::instance();
$aod_nl_carriers = $aod_nl_ship->carriers();
$aod_nl_classes  = array();
foreach ( $aod_nl_carriers as $c ) {
	$aod_nl_classes[ get_class( $c ) ] = true;
}

// 🚨 CE TÉMOIN N'EST PAS DÉCORATIF. R1 est une boucle : sur un registre vide, elle
// serait VERTE sans avoir rien examiné. On fige donc les deux cardinaux — 29
// instances (EcoTrack + 23 white-labels + Noest + Maystro + Yalidine + Yalitec +
// E-com Delivery) réparties sur 6 classes.
AOD_Test::eq( 29, count( $aod_nl_carriers ), sprintf( 'TÉMOIN : le registre rend %d transporteurs', count( $aod_nl_carriers ) ) );
AOD_Test::eq( 6, count( $aod_nl_classes ), sprintf( 'TÉMOIN : %d implémentations distinctes derrière ces 29 instances', count( $aod_nl_classes ) ) );

/* -------------------------------------------------------------------------
 * Commande témoin — une VRAIE commande, en livraison à domicile.
 *
 * ⚠️ « à domicile » n'est pas un détail : en stop-desk, plusieurs `build_payload()`
 * résolvent un code de bureau, ce qui peut sortir sur le réseau. On construit la
 * charge utile, on n'expédie rien.
 * ---------------------------------------------------------------------- */

$aod_nl_cmd = wc_create_order();
if ( ! $aod_nl_cmd instanceof WC_Order ) {
	AOD_Test::ok( false, 'Commande jetable créée', 'wc_create_order() a échoué' );
	return;
}
$aod_nl_cmd->set_billing_first_name( 'Témoin' );
$aod_nl_cmd->set_billing_last_name( '47.3' );
$aod_nl_cmd->set_billing_phone( '0550000000' );
$aod_nl_cmd->set_billing_address_1( 'Rue du test' );
$aod_nl_cmd->update_meta_data( '_aod_delivery_type', 'home' );
$aod_nl_cmd->update_meta_data( '_aod_wilaya_code', 16 );
$aod_nl_cmd->update_meta_data( '_aod_wilaya_name', 'Alger' );
$aod_nl_cmd->update_meta_data( '_aod_commune', 'Alger Centre' );
// ⚠️ ARMÉE AU STATUT « CONFIRMÉE ». `ship_cell_html()` n'offre le bouton
// « Envoyer » qu'à une commande confirmée — garde restituée après la revue de la
// Phase 47, la 47.2 ayant emporté cette condition avec le hook qu'elle débranchait.
// Sans cette ligne, la cellule ne rend plus de bouton du tout et R5 mesurerait du
// VIDE : ses 4 croisements de `data-note-perdue` seraient tous « absents », donc
// tous verts par vacuité. C'est exactement pour ça que le TÉMOIN existe.
$aod_nl_cmd->set_status( 'aod-confirmed' );
$aod_nl_cmd->save();
$aod_nl_id = (int) $aod_nl_cmd->get_id();

// E-com Delivery (`class-aod-carrier-procolis.php`) est le SEUL dont
// `resolve_commune()` appelle le réseau sans regarder s'il est configuré. On amorce
// son cache pour qu'il n'y aille pas : un test qui sort sur Internet n'est pas un
// test, c'est un pari sur le réseau du poste.
$aod_nl_trans_key = 'aod_ecomdz_communes_16';
$aod_nl_trans_old = get_transient( $aod_nl_trans_key );
set_transient( $aod_nl_trans_key, array( 'Alger Centre' ), HOUR_IN_SECONDS );

/** Cherche une aiguille dans un tableau de profondeur quelconque. */
function aod_nl_payload_contient( $payload, $aiguille ) {
	if ( is_array( $payload ) ) {
		foreach ( $payload as $v ) {
			if ( aod_nl_payload_contient( $v, $aiguille ) ) {
				return true;
			}
		}
		return false;
	}
	return is_string( $payload ) && false !== strpos( $payload, $aiguille );
}

/* =========================================================================
 * R1 — La note voyage SI ET SEULEMENT SI le transporteur la porte
 * ========================================================================= */

AOD_Test::group( 'Note livreur 47.3 — R1 : la note est dans la charge utile ⇔ supports_driver_note()' );

$aod_nl_temoin = 'AOD-NOTE-47-3-TEMOIN-XYZ';
$aod_nl_cmd->update_meta_data( AOD_Carrier::META_DRIVER_NOTE, $aod_nl_temoin );
$aod_nl_cmd->save();
$aod_nl_relu = wc_get_order( $aod_nl_id );

$aod_nl_declares = 0;
$aod_nl_portent  = 0;
$aod_nl_fautifs  = array();

foreach ( $aod_nl_carriers as $aod_nl_cid => $aod_nl_c ) {
	$aod_nl_declare = (bool) $aod_nl_c->supports_driver_note();
	$aod_nl_payload = $aod_nl_c->build_payload( $aod_nl_relu );
	$aod_nl_porte   = aod_nl_payload_contient( $aod_nl_payload, $aod_nl_temoin );

	if ( $aod_nl_declare ) {
		++$aod_nl_declares;
	}
	if ( $aod_nl_porte ) {
		++$aod_nl_portent;
	}
	if ( $aod_nl_declare !== $aod_nl_porte ) {
		$aod_nl_fautifs[] = $aod_nl_cid . ( $aod_nl_declare ? ' (annonce oui, n’envoie pas)' : ' (envoie, n’annonce pas)' );
	}
}

AOD_Test::ok(
	empty( $aod_nl_fautifs ),
	sprintf( '29 charges utiles construites : la note y est ⇔ le transporteur l’annonce (%d annoncent, %d la portent)', $aod_nl_declares, $aod_nl_portent ),
	'divergence(s) : ' . implode( ' · ', $aod_nl_fautifs )
);

// Le chiffre du plan (« 26 transporteurs sur 29 ») est PROUVÉ ici, pas répété.
// S'il change, c'est que le parc a bougé — et il faut le relire, pas l'ajuster.
AOD_Test::eq( 26, $aod_nl_portent, sprintf( '26 des 29 transporteurs reçoivent la note (mesuré : %d)', $aod_nl_portent ) );

/* =========================================================================
 * R2 — La longueur est bornée, et l'arabe n'est pas coupé en deux
 * ========================================================================= */

AOD_Test::group( 'Note livreur 47.3 — R2 : la note est clippée avant l’envoi' );

$aod_nl_eco = $aod_nl_ship->carrier( 'ecotrack' );

if ( ! $aod_nl_eco instanceof AOD_Carrier ) {
	AOD_Test::ok( false, 'EcoTrack disponible pour éprouver driver_note()' );
} else {
	$aod_nl_long = str_repeat( 'a', AOD_Carrier::NOTE_MAX + 50 );
	$aod_nl_cmd->update_meta_data( AOD_Carrier::META_DRIVER_NOTE, $aod_nl_long );
	$aod_nl_cmd->save();
	$aod_nl_clip = AOD_Test::call( $aod_nl_eco, 'driver_note', array( wc_get_order( $aod_nl_id ) ) );

	AOD_Test::eq(
		AOD_Carrier::NOTE_MAX,
		mb_strlen( $aod_nl_clip, 'UTF-8' ),
		sprintf( 'Une note de %d caractères sort à %d', AOD_Carrier::NOTE_MAX + 50, mb_strlen( $aod_nl_clip, 'UTF-8' ) )
	);

	// 🚨 L'ARABE EST LE CAS QUI COMPTE. Un `substr` brut couperait un caractère en
	// deux et produirait un UTF-8 invalide — que l'API refuse sans dire pourquoi,
	// APRÈS le verrou d'envoi et au milieu d'un lot.
	$aod_nl_ar = str_repeat( 'ك', AOD_Carrier::NOTE_MAX + 50 );
	$aod_nl_cmd->update_meta_data( AOD_Carrier::META_DRIVER_NOTE, $aod_nl_ar );
	$aod_nl_cmd->save();
	$aod_nl_clip_ar = AOD_Test::call( $aod_nl_eco, 'driver_note', array( wc_get_order( $aod_nl_id ) ) );

	AOD_Test::eq(
		AOD_Carrier::NOTE_MAX,
		mb_strlen( $aod_nl_clip_ar, 'UTF-8' ),
		sprintf( 'Une note ARABE de %d caractères sort à %d', AOD_Carrier::NOTE_MAX + 50, mb_strlen( $aod_nl_clip_ar, 'UTF-8' ) )
	);
	AOD_Test::ok(
		mb_check_encoding( $aod_nl_clip_ar, 'UTF-8' ),
		'…et le résultat reste un UTF-8 valide (aucun caractère coupé en deux)'
	);
}

/* =========================================================================
 * R3 — Sans note, la charge utile ne se dégrade pas
 * ========================================================================= */

AOD_Test::group( 'Note livreur 47.3 — R3 : sans note, la clé reste présente et vide' );

$aod_nl_cmd->update_meta_data( AOD_Carrier::META_DRIVER_NOTE, '' );
$aod_nl_cmd->save();
$aod_nl_vide = wc_get_order( $aod_nl_id );

// Un transporteur qui exige la clé refuserait le colis si elle disparaissait — et
// le refus arriverait APRÈS le verrou. La régression serait invisible en dev.
$aod_nl_champs = array(
	'ecotrack' => 'remarque',
	'noest'    => 'remarque',
	'maystro'  => 'note_to_driver',
);
foreach ( $aod_nl_champs as $aod_nl_cid => $aod_nl_champ ) {
	$aod_nl_c = $aod_nl_ship->carrier( $aod_nl_cid );
	if ( ! $aod_nl_c instanceof AOD_Carrier ) {
		AOD_Test::ok( false, sprintf( 'Transporteur %s présent au registre', $aod_nl_cid ) );
		continue;
	}
	$aod_nl_p = $aod_nl_c->build_payload( $aod_nl_vide );
	AOD_Test::ok(
		array_key_exists( $aod_nl_champ, $aod_nl_p ) && '' === $aod_nl_p[ $aod_nl_champ ],
		sprintf( '%s : « %s » présent et vide', $aod_nl_cid, $aod_nl_champ ),
		'la clé a disparu ou porte autre chose que la chaîne vide'
	);
}

/* =========================================================================
 * R4 — `aod_cd_order_note` n'existe plus, sous AUCUNE forme
 * ========================================================================= */

AOD_Test::group( 'Note livreur 47.3 — R4 : l’ancien handler de note a disparu' );

// 🚨 `has_action()` est CLOSURE-PROOF, et c'est pour ça qu'il est ici plutôt qu'une
// inspection de `$wp_filter` par réflexion. La 47.2 s'est fait prendre par un hook
// rebranché en closure liée à `$this` : une règle qui n'inspectait que les callbacks
// `array( $objet, 'methode' )` restait verte. `has_action()` rend `false` quand RIEN
// n'est accroché — quelle que soit la forme du callback.
AOD_Test::ok(
	false === has_action( 'wp_ajax_aod_cd_order_note' ),
	'Aucun callback n’est accroché à `wp_ajax_aod_cd_order_note` (closures comprises)',
	'le handler a été ré-enregistré — la note du confirmateur a deux chemins, dont un qui écrit dans l’historique Woo'
);
AOD_Test::ok(
	! method_exists( 'AOD_CD_Section_Orders', 'ajax_order_note' ),
	'`AOD_CD_Section_Orders::ajax_order_note()` n’existe plus'
);

/* =========================================================================
 * R5 — `data-note-perdue` dit la vérité
 * ========================================================================= */

AOD_Test::group( 'Note livreur 47.3 — R5 : l’écran sait quand la note ne partira pas' );

if ( ! class_exists( 'AOD_CD_Section_Orders' ) ) {
	AOD_Test::ok( false, 'AOD_CD_Section_Orders disponible' );
} else {
	if ( ! class_exists( 'AOD_Test_Carrier_Note_Oui' ) ) {
		/** Bouchon configuré qui PORTE la note. */
		class AOD_Test_Carrier_Note_Oui extends AOD_Carrier {
			public function id() {
				return 'bouchonnoteoui';
			}
			public function label() {
				return 'Bouchon Note Oui';
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
			public function supports_driver_note() {
				return true;
			}
			public function build_payload( $order ) {
				return array( 'remarque' => $this->driver_note( $order ) );
			}
			public function create_parcel( $order ) {
				return new WP_Error( 'aod_test_no_send', 'bouchon' );
			}
		}
		/** Bouchon configuré qui NE PORTE PAS la note. */
		class AOD_Test_Carrier_Note_Non extends AOD_Test_Carrier_Note_Oui {
			public function id() {
				return 'bouchonnotenon';
			}
			public function label() {
				return 'Bouchon Note Non';
			}
			public function supports_driver_note() {
				return false;
			}
			public function build_payload( $order ) {
				return array();
			}
		}
	}

	$aod_nl_prop = new ReflectionProperty( 'AOD_Shipping', 'carriers' );
	$aod_nl_prop->setAccessible( true );
	$aod_nl_parc_orig = $aod_nl_prop->getValue( $aod_nl_ship );
	$aod_nl_auto_orig = get_option( AOD_Shipping::AUTO_OPTION, array() );
	$aod_nl_user_orig = get_current_user_id();

	// Le parc réel du clone ne doit RIEN décider ici : deux bouchons, l'un porte la
	// note, l'autre non, tous deux configurés. Restauré en sortie.
	$aod_nl_prop->setValue( $aod_nl_ship, array(
		'bouchonnoteoui' => new AOD_Test_Carrier_Note_Oui(),
		'bouchonnotenon' => new AOD_Test_Carrier_Note_Non(),
	) );
	update_option( AOD_Shipping::AUTO_OPTION, array( 'enabled' => 0, 'carrier' => 'bouchonnoteoui' ) );

	// Le bouton n'est rendu qu'à qui peut confirmer : sans utilisateur, la cellule
	// n'aurait pas de bouton et R5 mesurerait le vide.
	$aod_nl_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( $aod_nl_admins ) {
		wp_set_current_user( (int) $aod_nl_admins[0] );
	}

	$aod_nl_sec = AOD_CD_Section_Orders::instance();

	/** Rend la cellule « Suivi » pour un croisement donné et dit si l'alerte y est. */
	$aod_nl_perdue = function ( $note, $carrier_id ) use ( $aod_nl_id, $aod_nl_sec ) {
		$o = wc_get_order( $aod_nl_id );
		$o->update_meta_data( AOD_Carrier::META_DRIVER_NOTE, $note );
		$o->update_meta_data( AOD_Shipping::META_CARRIER_CHOICE, $carrier_id );
		$o->save();
		$html = (string) AOD_Test::call( $aod_nl_sec, 'ship_cell_html', array( wc_get_order( $aod_nl_id ) ) );
		return array(
			'perdue' => false !== strpos( $html, 'data-note-perdue="1"' ),
			'bouton' => false !== strpos( $html, 'aod-cd-ship-send' ),
		);
	};

	$aod_nl_c1 = $aod_nl_perdue( 'Sonner deux fois', 'bouchonnotenon' );
	$aod_nl_c2 = $aod_nl_perdue( 'Sonner deux fois', 'bouchonnoteoui' );
	$aod_nl_c3 = $aod_nl_perdue( '', 'bouchonnotenon' );
	$aod_nl_c4 = $aod_nl_perdue( '', 'bouchonnoteoui' );

	AOD_Test::ok(
		$aod_nl_c1['bouton'] && $aod_nl_c2['bouton'] && $aod_nl_c3['bouton'] && $aod_nl_c4['bouton'],
		'TÉMOIN : les 4 croisements rendent bien un bouton « Envoyer » (sinon R5 mesurerait du vide)',
		'la cellule ne rend pas de bouton — capability, statut ou transporteur non configuré'
	);
	AOD_Test::ok( $aod_nl_c1['perdue'], 'Note + transporteur SANS champ → l’écran prévient' );
	AOD_Test::ok( ! $aod_nl_c2['perdue'], 'Note + transporteur AVEC champ → aucun avertissement' );
	AOD_Test::ok( ! $aod_nl_c3['perdue'], 'Aucune note + transporteur sans champ → aucun avertissement' );
	AOD_Test::ok( ! $aod_nl_c4['perdue'], 'Aucune note + transporteur avec champ → aucun avertissement' );

	// 🚨 Le cas qui manque partout : AUCUN transporteur résolvable. `carrier_for()`
	// rend alors un WP_Error, et on ne crie pas au loup sur un envoi qui sera de
	// toute façon refusé — avec un message plus précis que le nôtre.
	$aod_nl_prop->setValue( $aod_nl_ship, array() );
	$aod_nl_c5 = $aod_nl_perdue( 'Sonner deux fois', '' );
	AOD_Test::ok( ! $aod_nl_c5['perdue'], 'Aucun transporteur configuré → pas d’avertissement (le refus d’envoi le dira mieux)' );

	// Remise en état.
	$aod_nl_prop->setValue( $aod_nl_ship, $aod_nl_parc_orig );
	update_option( AOD_Shipping::AUTO_OPTION, $aod_nl_auto_orig );
	wp_set_current_user( $aod_nl_user_orig );
}

/* --- Purge : la commande jetable et le cache amorcé ne survivent pas au test --- */

if ( false === $aod_nl_trans_old ) {
	delete_transient( $aod_nl_trans_key );
} else {
	set_transient( $aod_nl_trans_key, $aod_nl_trans_old, HOUR_IN_SECONDS );
}
$aod_nl_final = wc_get_order( $aod_nl_id );
if ( $aod_nl_final instanceof WC_Order ) {
	$aod_nl_final->delete( true );
}
