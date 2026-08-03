<?php
/**
 * Le transporteur est un choix PAR COMMANDE — et il n'a qu'une résolution (47.1).
 *
 * 🚨 LE MANQUE, MESURÉ LE 2026-08-01. `auto_settings()` rendait UNE valeur pour
 * toute la boutique (défaut `'ecotrack'`), alors que `carriers()` en compte 29.
 * Router deux commandes vers deux sociétés différentes exigeait donc de changer
 * un réglage GLOBAL entre les deux envois. Aucune méta ne portait de transporteur
 * CHOISI : `_aod_ship_carrier` existe, mais `store_result()` l'écrit APRÈS
 * l'envoi — elle dit qui a expédié, jamais qui doit expédier.
 *
 * ⚠️ CE QUE CE FICHIER GARDE EN PRIORITÉ, ET POURQUOI CE N'EST PAS LA BRANCHE 1.
 * R3 est la règle qui tiendra dans le temps : **une seule résolution**. Le jour
 * où quelqu'un ajoutera une règle de choix (« ce transporteur pour cette
 * wilaya »), il l'écrira à un seul endroit — ou R3 rougira. Sans elle, les
 * branches R1/R2 resteraient vertes pendant qu'un second `auto_settings()`
 * ressusciterait ailleurs, et les deux répondraient différemment à la même
 * question. C'est exactement le mode de dérive de la Phase 46 (« une règle
 * écrite deux fois ne tient pas ; seul un cliquet la tient »).
 *
 * ⚠️ TRANSPORTEURS BOUCHONNÉS, JAMAIS LES VRAIS. Un clone de développement n'a
 * aucun transporteur configuré : sans bouchon, `carrier_for()` renverrait
 * toujours la même erreur et TOUTES les branches seraient vraies par vacuité.
 * Le tableau privé `AOD_Shipping::$carriers` est donc remplacé par réflexion le
 * temps du fichier, puis RESTAURÉ — aucune clé d'API réelle n'est lue, aucun
 * appel réseau n'est fait (`create_parcel()` du bouchon lève, et rien ne
 * l'appelle : cette story n'envoie rien).
 *
 * ⚠️ IL ÉCRIT UNE COMMANDE JETABLE et la supprime en sortie (R5), sur le patron
 * de `test-verrou-envoi.php`. Le réglage global `aod_shipping_auto` est
 * sauvegardé puis remis tel quel.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Transporteur par commande 47.1 — R0 : le socle est là' );

$aod_tpc_ok = class_exists( 'AOD_Shipping' )
	&& defined( 'AOD_Shipping::META_CARRIER_CHOICE' )
	&& method_exists( 'AOD_Shipping', 'carrier_for' );
AOD_Test::ok(
	$aod_tpc_ok,
	'AOD_Shipping expose `META_CARRIER_CHOICE` et `carrier_for()`',
	$aod_tpc_ok ? '' : 'la méta du choix ou la résolution unique manquent'
);

if ( ! $aod_tpc_ok || ! function_exists( 'wc_create_order' ) ) {
	return;
}

AOD_Test::ok(
	AOD_Shipping::META_CARRIER_CHOICE !== AOD_Carrier::META_CARRIER,
	'🔴 Le choix (`' . AOD_Shipping::META_CARRIER_CHOICE . '`) est une méta DISTINCTE du constat d’envoi (`' . AOD_Carrier::META_CARRIER . '`)',
	'fondues, un colis déjà parti réécrirait le choix — ou un choix ferait croire à un envoi'
);

/* -------------------------------------------------------------------------
 * Bouchons : trois transporteurs sous notre contrôle.
 * ---------------------------------------------------------------------- */

if ( ! class_exists( 'AOD_Test_Carrier_Bouchon' ) ) {
	/**
	 * Transporteur de test : ne parle à personne, et le dit si on essaie.
	 */
	class AOD_Test_Carrier_Bouchon extends AOD_Carrier {
		/** @var string */
		protected $test_id;
		/** @var bool */
		protected $test_configure;

		public function __construct( $id, $configure ) {
			$this->test_id        = (string) $id;
			$this->test_configure = (bool) $configure;
		}
		public function id() {
			return $this->test_id;
		}
		public function label() {
			return 'Bouchon ' . strtoupper( $this->test_id );
		}
		protected function defaults() {
			return array();
		}
		public function is_configured() {
			return $this->test_configure;
		}
		public function render_settings_fields() {}
		public function sanitize_settings( $input ) {
			return array();
		}
		public function build_payload( $order ) {
			return array();
		}
		public function create_parcel( $order ) {
			// Filet : cette story n'envoie rien. Si un jour un chemin d'envoi
			// atterrit ici, mieux vaut une erreur bruyante qu'un faux colis.
			return new WP_Error( 'aod_test_bouchon', 'Le bouchon de test n’expédie pas.' );
		}
	}
}

$aod_tpc_ship = AOD_Shipping::instance();

$aod_tpc_prop = new ReflectionProperty( 'AOD_Shipping', 'carriers' );
$aod_tpc_prop->setAccessible( true );
$aod_tpc_orig_carriers = $aod_tpc_prop->getValue( $aod_tpc_ship );
$aod_tpc_orig_auto     = get_option( AOD_Shipping::AUTO_OPTION, array() );

/** Remplace le parc de transporteurs par des bouchons. */
$aod_tpc_parc = static function ( array $defs ) use ( $aod_tpc_prop, $aod_tpc_ship ) {
	$parc = array();
	foreach ( $defs as $id => $configure ) {
		$parc[ $id ] = new AOD_Test_Carrier_Bouchon( $id, $configure );
	}
	$aod_tpc_prop->setValue( $aod_tpc_ship, $parc );
};

/** Réglage global de la boutique. */
$aod_tpc_global = static function ( $carrier_id ) {
	update_option( AOD_Shipping::AUTO_OPTION, array( 'enabled' => 1, 'carrier' => $carrier_id ) );
};

$aod_tpc_cmd = wc_create_order();
if ( ! $aod_tpc_cmd instanceof WC_Order ) {
	AOD_Test::ok( false, 'Commande jetable créée pour éprouver la résolution', 'wc_create_order() a échoué' );
	$aod_tpc_prop->setValue( $aod_tpc_ship, $aod_tpc_orig_carriers );
	update_option( AOD_Shipping::AUTO_OPTION, $aod_tpc_orig_auto );
	return;
}
$aod_tpc_cmd_id = (int) $aod_tpc_cmd->get_id();

/** Pose (ou retire) le choix sur la commande jetable, puis résout. */
$aod_tpc_resoudre = static function ( $choix ) use ( $aod_tpc_ship, $aod_tpc_cmd_id ) {
	$order = wc_get_order( $aod_tpc_cmd_id );
	if ( null === $choix ) {
		$order->delete_meta_data( AOD_Shipping::META_CARRIER_CHOICE );
	} else {
		$order->update_meta_data( AOD_Shipping::META_CARRIER_CHOICE, $choix );
	}
	$order->save();
	return $aod_tpc_ship->carrier_for( wc_get_order( $aod_tpc_cmd_id ) );
};

/* =========================================================================
 * R1 — Sans choix, RIEN NE CHANGE : c'est le réglage global qui décide
 *
 * C'est la forme même de la non-régression de la story : un clone qui n'a
 * jamais touché au nouveau contrôle expédie exactement comme avant.
 * ========================================================================= */

AOD_Test::group( 'Transporteur par commande 47.1 — R1 : sans choix, la boutique décide' );

$aod_tpc_parc( array( 'bouchona' => true, 'bouchonb' => true, 'bouchonz' => false ) );
$aod_tpc_global( 'bouchona' );

$aod_tpc_r = $aod_tpc_resoudre( null );
AOD_Test::ok(
	$aod_tpc_r instanceof AOD_Carrier && 'bouchona' === $aod_tpc_r->id(),
	'🔴 Méta absente → transporteur GLOBAL (obtenu : ' . ( $aod_tpc_r instanceof AOD_Carrier ? $aod_tpc_r->id() : 'erreur' ) . ')',
	'si l’absence de méta ne repliait pas sur le global, toutes les commandes antérieures d’un clone changeraient de destination le jour de la mise à jour'
);

$aod_tpc_r = $aod_tpc_resoudre( '' );
AOD_Test::ok(
	$aod_tpc_r instanceof AOD_Carrier && 'bouchona' === $aod_tpc_r->id(),
	'Méta VIDE → même chose que l’absence (une chaîne vide n’est pas un choix)'
);

/* =========================================================================
 * R2 — Un choix explicite l'emporte sur le réglage global
 * ========================================================================= */

AOD_Test::group( 'Transporteur par commande 47.1 — R2 : le choix de la commande prime' );

$aod_tpc_r = $aod_tpc_resoudre( 'bouchonb' );
AOD_Test::ok(
	$aod_tpc_r instanceof AOD_Carrier && 'bouchonb' === $aod_tpc_r->id(),
	'🔴 Choix « bouchonb » → bouchonb, alors que le global est bouchona (obtenu : ' . ( $aod_tpc_r instanceof AOD_Carrier ? $aod_tpc_r->id() : 'erreur' ) . ')',
	'sans cette branche, la colonne « Transporteur » serait un décor : l’écran dirait B et le colis partirait chez A'
);

// ANCRAGE INVERSE : le global n'est pas devenu inopérant pour autant.
$aod_tpc_global( 'bouchonb' );
$aod_tpc_r = $aod_tpc_resoudre( 'bouchona' );
AOD_Test::ok(
	$aod_tpc_r instanceof AOD_Carrier && 'bouchona' === $aod_tpc_r->id(),
	'ANCRAGE INVERSE : global inversé, le choix gagne toujours (ce n’est pas un ordre codé en dur)'
);
$aod_tpc_global( 'bouchona' );

/* =========================================================================
 * R3 — Un choix NON CONFIGURÉ est refusé, jamais remplacé (décision D-3)
 *
 * 🚨 LE PIÈGE EST ICI, et il est silencieux : le repli « un seul configuré »
 * de la branche 3 est LÉGITIME quand la commande ne porte rien, et
 * CATASTROPHIQUE quand elle porte un choix — il expédierait chez une société
 * que personne n'a choisie, ce qui engage un contrat commercial.
 * ========================================================================= */

AOD_Test::group( 'Transporteur par commande 47.1 — R3 : un choix invalide est REFUSÉ' );

// Un seul configuré : le repli de la branche 3 est donc ARMÉ. S'il déborde sur
// un choix invalide, l'assertion suivante le voit.
$aod_tpc_parc( array( 'bouchona' => true, 'bouchonz' => false ) );

$aod_tpc_r = $aod_tpc_resoudre( 'bouchonz' );
AOD_Test::ok(
	is_wp_error( $aod_tpc_r ),
	'🔴 Choix pointant un transporteur NON configuré → refus (obtenu : ' . ( $aod_tpc_r instanceof AOD_Carrier ? $aod_tpc_r->id() : 'WP_Error' ) . ')',
	'le repli « un seul configuré » aurait expédié chez bouchona — une société que personne n’a choisie'
);

$aod_tpc_r = $aod_tpc_resoudre( 'transporteur-qui-nexiste-pas' );
AOD_Test::ok(
	is_wp_error( $aod_tpc_r ),
	'🔴 Choix pointant un identifiant INCONNU → refus',
	'une méta forgée ou un transporteur retiré du catalogue ne doit pas silencieusement en désigner un autre'
);

// ANCRAGE : le repli EXISTE bel et bien, il ne s'applique simplement pas à un
// choix invalide. Sans cet ancrage, R3 serait vrai si le repli avait disparu.
$aod_tpc_global( 'bouchonz' ); // global non configuré…
$aod_tpc_r = $aod_tpc_resoudre( null ); // …et aucun choix sur la commande.
AOD_Test::ok(
	$aod_tpc_r instanceof AOD_Carrier && 'bouchona' === $aod_tpc_r->id(),
	'ANCRAGE : sans choix, le repli « un seul configuré » fonctionne toujours (comportement d’avant la 47.1)',
	'ce repli compensait l’absence de choix par commande — le retirer casserait les clones dont le défaut est resté sur « EcoTrack » générique'
);

// Deux configurés + global non configuré : plus de repli possible → l'erreur
// HISTORIQUE, mot pour mot (elle est traduite, le gérant la reconnaît).
$aod_tpc_parc( array( 'bouchona' => true, 'bouchonb' => true, 'bouchonz' => false ) );
$aod_tpc_r = $aod_tpc_resoudre( null );
AOD_Test::ok(
	is_wp_error( $aod_tpc_r ) && false !== strpos( $aod_tpc_r->get_error_message(), 'livreur par défaut' ),
	'Global non configuré + plusieurs candidats → message historique « le livreur par défaut n’est pas configuré »'
);

$aod_tpc_parc( array( 'bouchonz' => false ) );
$aod_tpc_r = $aod_tpc_resoudre( null );
AOD_Test::ok(
	is_wp_error( $aod_tpc_r ) && false !== strpos( $aod_tpc_r->get_error_message(), 'aucun transporteur' ),
	'Aucun transporteur configuré → message historique « aucun transporteur n’est configuré »'
);

/* =========================================================================
 * R4 — UNE seule résolution dans tout le plugin (décision D-2)
 *
 * La règle qui tiendra dans le temps. Statique à dessein : elle regarde le
 * CODE, pas un comportement — c'est la duplication qu'on interdit, et une
 * duplication se voit à la lecture, jamais à l'exécution.
 * ========================================================================= */

AOD_Test::group( 'Transporteur par commande 47.1 — R4 : une seule résolution' );

$aod_tpc_f   = rtrim( AOD_COD_PATH, '/' ) . '/includes/class-aod-shipping.php';
$aod_tpc_src = is_readable( $aod_tpc_f ) ? (string) file_get_contents( $aod_tpc_f ) : '';
AOD_Test::ok( '' !== $aod_tpc_src, 'TÉMOIN : `class-aod-shipping.php` lisible' );

// Corps de `ship_order()` : de sa signature jusqu'à la suivante.
//
// 🔁 ELLE S'APPELAIT `auto_send()` JUSQU'À LA 47.2, et ce motif la cherchait sous
// ce nom. Le renommage a fait rougir le TÉMOIN ci-dessous (« 0 caractère »), pas
// les assertions — c'est exactement son rôle : sans lui, R4 aurait verdi sur du
// vide le jour où la méthode a changé de nom, et l'invariant « une seule
// résolution » ne serait plus gardé par rien.
//
// 🚨 COMMENTAIRES RETIRÉS, ET CE N'EST PAS UNE PRÉCAUTION THÉORIQUE. Sans ce
// nettoyage, l'assertion « la méthode DEMANDE le transporteur » restait VERTE
// alors que l'appel avait été remplacé par une résolution en dur : elle se
// satisfaisait du commentaire juste au-dessus, qui cite `carrier_for()` pour
// expliquer la décision. Mesuré au red-proof de cette étape — le cliquet
// prouvait la présence d'une PROSE, pas d'un appel. Même piège que
// `test-sidebar-rail.php`, dont `test-bulkbar-permanente.php` R3 tire déjà la
// leçon en toutes lettres : on ne regarde que le CODE.
$aod_tpc_corps = '';
if ( preg_match( '/public function ship_order\(.*?\n\t\}\n/s', $aod_tpc_src, $aod_tpc_m ) ) {
	$aod_tpc_corps = (string) preg_replace( '#/\*.*?\*/#s', '', $aod_tpc_m[0] );
	$aod_tpc_corps = (string) preg_replace( '#^\s*//.*$#m', '', $aod_tpc_corps );
}
AOD_Test::ok(
	'' !== $aod_tpc_corps && strlen( $aod_tpc_corps ) > 400,
	sprintf( 'TÉMOIN : corps de `ship_order()` isolé (%d caractères)', strlen( $aod_tpc_corps ) ),
	'un motif qui ne trouve rien rendrait les deux assertions suivantes vraies par vacuité'
);

AOD_Test::ok(
	'' !== $aod_tpc_corps && false !== strpos( $aod_tpc_corps, 'carrier_for(' ),
	'🔴 `ship_order()` DEMANDE le transporteur (`carrier_for()`) au lieu de le déduire'
);
AOD_Test::ok(
	'' !== $aod_tpc_corps && false === strpos( $aod_tpc_corps, '$this->configured()' ),
	'🔴 `ship_order()` ne contient plus AUCUN repli « un seul configuré »',
	'deux endroits qui répondent à « quel transporteur ? » finissent par répondre différemment'
);

// Le repli lui-même n'existe qu'UNE fois dans tout le plugin.
$aod_tpc_plugin = rtrim( AOD_COD_PATH, '/' ) . '/includes';
$aod_tpc_replis = 0;
foreach ( (array) glob( $aod_tpc_plugin . '/*.php' ) as $aod_tpc_php ) {
	$aod_tpc_nu = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $aod_tpc_php ) );
	$aod_tpc_nu = (string) preg_replace( '#^\s*//.*$#m', '', $aod_tpc_nu );
	$aod_tpc_replis += preg_match_all( '/1\s*===\s*count\(\s*\$configured\s*\)/', $aod_tpc_nu );
}
AOD_Test::ok(
	1 === $aod_tpc_replis,
	sprintf( '🔴 Le repli « un seul configuré » est écrit UNE fois dans le plugin (trouvé : %d)', $aod_tpc_replis ),
	'0 = le repli a disparu (régression pour les clones au défaut « EcoTrack ») · ≥2 = la résolution s’est dédoublée'
);

/* =========================================================================
 * R5 — L'écran ne peut pas oublier son intitulé de carte mobile
 *
 * 🚨 CETTE RÈGLE EXISTE PARCE QUE RIEN D'AUTRE NE LA PORTE. La story le dit :
 * `test-chrome-conformite.php` R2 travaille au grain du FICHIER, et son propre
 * docblock écrit ce qu'il ne prouve pas — « un fichier avec --cards et des
 * data-label sur un seul tableau passerait ». `class-aod-cd-section-orders.php`
 * porte déjà les deux : R2 y resterait VERT sur une colonne sans `data-label`,
 * et la porte `rtl` ne voit que le débordement, jamais l'intitulé manquant.
 * ========================================================================= */

AOD_Test::group( 'Transporteur par commande 47.1 — R5 : la colonne survit en carte mobile' );

$aod_tpc_of  = rtrim( AOD_CD_PATH, '/' ) . '/includes/class-aod-cd-section-orders.php';
$aod_tpc_osrc = is_readable( $aod_tpc_of ) ? (string) file_get_contents( $aod_tpc_of ) : '';
AOD_Test::ok( '' !== $aod_tpc_osrc, 'TÉMOIN : `class-aod-cd-section-orders.php` lisible' );

$aod_tpc_td = array();
if ( preg_match_all( '/<td[^>]*class="[^"]*aod-cd-cell-carrier[^"]*"[^>]*>/', $aod_tpc_osrc, $aod_tpc_mtd ) ) {
	$aod_tpc_td = $aod_tpc_mtd[0];
}
AOD_Test::ok(
	1 === count( $aod_tpc_td ),
	sprintf( 'TÉMOIN : %d cellule(s) « Transporteur » trouvée(s) — attendu 1', count( $aod_tpc_td ) ),
	'0 trouvée : la colonne a été renommée et l’assertion suivante serait vraie sur du vide'
);
foreach ( $aod_tpc_td as $aod_tpc_cell ) {
	AOD_Test::ok(
		false !== strpos( $aod_tpc_cell, 'data-label' ),
		'🔴 La cellule « Transporteur » porte `data-label` (sans lui, la carte mobile perd son intitulé)',
		'aucun autre cliquet ni aucune porte ne verrait ce manque — cf. le docblock de test-chrome-conformite.php R2'
	);
}

/* =========================================================================
 * R6 — Ni résidu, ni réglage modifié
 * ========================================================================= */

AOD_Test::group( 'Transporteur par commande 47.1 — R6 : aucun résidu' );

$aod_tpc_prop->setValue( $aod_tpc_ship, $aod_tpc_orig_carriers );
if ( empty( $aod_tpc_orig_auto ) ) {
	delete_option( AOD_Shipping::AUTO_OPTION );
} else {
	update_option( AOD_Shipping::AUTO_OPTION, $aod_tpc_orig_auto );
}
AOD_Test::ok(
	get_option( AOD_Shipping::AUTO_OPTION, array() ) == $aod_tpc_orig_auto, // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- comparaison de tableaux d'options, l'ordre des clés n'a pas à être garanti.
	'Le réglage global de la boutique est remis exactement tel qu’il était'
);
AOD_Test::ok(
	count( $aod_tpc_ship->carriers() ) === count( $aod_tpc_orig_carriers ),
	sprintf( 'Le parc réel de transporteurs est restauré (%d)', count( $aod_tpc_ship->carriers() ) )
);

$aod_tpc_dispo = wc_get_order( $aod_tpc_cmd_id );
if ( $aod_tpc_dispo instanceof WC_Order ) {
	$aod_tpc_dispo->delete( true );
}
AOD_Test::ok(
	! wc_get_order( $aod_tpc_cmd_id ) instanceof WC_Order,
	'La commande jetable est supprimée'
);
