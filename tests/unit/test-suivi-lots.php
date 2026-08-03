<?php
/**
 * La synchronisation du suivi tient un budget de temps et reprend où elle
 * s'est arrêtée (Phase 32.4, dette D23).
 *
 * 🚨 LE DÉFAUT. « Actualiser tout » appelait `run_sync()` EN SYNCHRONE dans la
 * requête AJAX : jusqu'à 1200 commandes hydratées et 30 appels HTTP séquentiels
 * à 30 s de timeout chacun. La requête mourait sur le timeout PHP ou le
 * reverse-proxy, le gérant lisait « Erreur réseau » et RECLIQUAIT — relançant
 * tout depuis zéro. Le même travail en cron pouvait dépasser
 * `max_execution_time` sans trace ni reprise : le suivi se figeait ALORS QUE le
 * cron tournait — pas le symptôme que `docs/CRON-SERVEUR.md` décrit, donc un
 * diagnostic impossible.
 *
 * CE QUE LA 32.4 CHANGE : un moteur par tranches (`run_sync_batch()`) sous
 * budget de temps, dont la reprise ne se code pas — elle SE LIT DANS LA DONNÉE
 * (`META_STATUS_AT` estampillé à chaque commande traitée, `pending_orders()`
 * servant les moins récentes d'abord). Le timeout transporteur descend de 30 s
 * à 10 s (les API DZ répondent en < 2 s : 30 s ne rattrapaient rien), et chaque
 * passage se journalise via `AOD_COD_Log` (31.3).
 *
 * ⚠️ TRANSPORTEUR BOUCHONNÉ, JAMAIS UNE VRAIE API — même contrainte qu'en 31.4
 * (quota de production, configuration du gérant). Le bouchon est injecté dans
 * la table des transporteurs d'`AOD_Shipping` par réflexion, et RETIRÉ en fin
 * de test ; les commandes sont jetables et supprimées. Les candidates sont
 * INJECTÉES dans `run_sync_batch()` (3ᵉ argument, réservé aux tests) : les
 * commandes réelles du clone ne sont jamais traversées.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Suivi 32.4 — R0 : le socle est chargé' );

$aod_sl_ok = class_exists( 'AOD_Ship_Tracking' )
	&& method_exists( 'AOD_Ship_Tracking', 'run_sync_batch' )
	&& defined( 'AOD_Ship_Tracking::CRON_BUDGET_S' )
	&& defined( 'AOD_Ship_Tracking::AJAX_BUDGET_S' );
AOD_Test::ok(
	$aod_sl_ok,
	'AOD_Ship_Tracking expose le moteur par tranches (run_sync_batch + budgets)',
	$aod_sl_ok ? '' : 'run_sync_batch()/CRON_BUDGET_S/AJAX_BUDGET_S introuvables'
);

if ( ! $aod_sl_ok ) {
	return;
}

/*
 * Le bouchon : compte les appels réseau simulés, peut ralentir (panne lente) et
 * mettre des colis précis en échec. Duck-typé comme en 31.4 — le moteur ne
 * type-hint pas, c'est ce qui permet d'éprouver le VRAI comportement sans API.
 */
if ( ! class_exists( 'AOD_Test_Track_Stub' ) ) {
	class AOD_Test_Track_Stub {
		public $calls    = 0;
		public $seen     = array();
		public $delay_ms = 0;
		public $fail     = array();
		public $bulk     = 1;
		public function id() {
			return 'stub-32-4';
		}
		public function label() {
			return 'Transporteur bouchonné 32.4';
		}
		public function supports_tracking() {
			return true;
		}
		public function bulk_tracking_limit() {
			return $this->bulk;
		}
		public function get_tracking_bulk( $trackings ) {
			$this->calls++;
			if ( $this->delay_ms > 0 ) {
				usleep( $this->delay_ms * 1000 );
			}
			$out = array();
			foreach ( (array) $trackings as $t ) {
				$this->seen[] = $t;
				$out[ $t ]    = in_array( $t, $this->fail, true )
					? new WP_Error( 'aod_stub_down', 'panne simulée' )
					: array(
						'status'    => AOD_Carrier::ST_IN_TRANSIT,
						'raw_label' => 'stub',
						'history'   => array(),
					);
			}
			return $out;
		}
	}
}

// Injection du bouchon dans la table des transporteurs (retiré en R7).
$aod_sl_ship = AOD_Shipping::instance();
$aod_sl_prop = new ReflectionProperty( 'AOD_Shipping', 'carriers' );
$aod_sl_prop->setAccessible( true );
$aod_sl_map               = $aod_sl_prop->getValue( $aod_sl_ship );
$aod_sl_stub              = new AOD_Test_Track_Stub();
$aod_sl_map['stub-32-4']  = $aod_sl_stub;
$aod_sl_prop->setValue( $aod_sl_ship, $aod_sl_map );

$aod_sl_svc = AOD_Ship_Tracking::instance();

// 3 commandes jetables rattachées au bouchon.
$aod_sl_cmds = array();
for ( $aod_sl_i = 1; $aod_sl_i <= 3; $aod_sl_i++ ) {
	$aod_sl_o = wc_create_order();
	if ( $aod_sl_o instanceof WC_Order ) {
		$aod_sl_o->update_meta_data( AOD_Carrier::META_CARRIER, 'stub-32-4' );
		$aod_sl_o->update_meta_data( AOD_Carrier::META_TRACKING, 'S324-' . $aod_sl_i );
		$aod_sl_o->save();
		$aod_sl_cmds[] = $aod_sl_o;
	}
}

if ( 3 !== count( $aod_sl_cmds ) ) {
	AOD_Test::ok( false, 'Commandes jetables créées', 'wc_create_order() a échoué — le reste serait vrai par vacuité' );
	return;
}

/* =========================================================================
 * R1 — Le budget arrête le passage ENTRE deux fournées… mais jamais avant la
 *      PREMIÈRE (garantie de progrès)
 *
 * 🚨 C'est le cœur de la story. Bouchon lent (1,2 s/appel), budget de 1 s,
 * 3 colis en fournées de 1 : la première fournée passe (sinon un budget trop
 * court ne ferait JAMAIS rien, tranche après tranche), puis l'échéance tombe.
 * Le scénario est DÉTERMINISTE : usleep garantit ≥ 1,2 s > 1 s de budget.
 * ========================================================================= */

AOD_Test::group( 'Suivi 32.4 — R1 : le budget de temps est un vrai plafond' );

$aod_sl_stub->delay_ms = 1200;
$aod_sl_r1             = $aod_sl_svc->run_sync_batch( 1, 0, $aod_sl_cmds );

AOD_Test::ok(
	1 === $aod_sl_r1['updated'],
	sprintf( 'Le passage s’arrête après la fournée qui a épuisé le budget (maj : %d)', $aod_sl_r1['updated'] ),
	'0 = rien ne part ; >1 = le budget n’arrête rien — c’est l’auto-DoS d’origine'
);
AOD_Test::ok(
	1 === $aod_sl_stub->calls,
	sprintf( '🔴 UN SEUL appel réseau est parti (appels : %d) — le budget coupe entre les fournées', $aod_sl_stub->calls ),
	'chaque appel de plus, c’est 10 s de plus dans une requête que le gérant regarde mourir'
);
AOD_Test::ok(
	2 === $aod_sl_r1['remaining'] && true === $aod_sl_r1['timed_out'],
	sprintf( 'Le reste est COMPTÉ et annoncé (restantes : %d, interrompu : %s)', $aod_sl_r1['remaining'], $aod_sl_r1['timed_out'] ? 'oui' : 'non' ),
	'un passage interrompu qui ne dit pas ce qu’il laisse rejoue le « suivi figé » muet de D23'
);

// GARANTIE DE PROGRÈS, isolée : échéance DÉJÀ passée à l'entrée + force_first.
// Sans elle, un budget trop court ne ferait strictement rien, tranche après
// tranche — un « Actualiser tout » qui tourne sans jamais rien actualiser.
// (R1 ci-dessus ne l'exerce pas : à la 1re fournée, l'échéance n'est pas encore
// tombée. Ici, elle l'est dès l'entrée — seul `force_first` peut faire partir
// la fournée.)
$aod_sl_stub->delay_ms = 0;
$aod_sl_stub->calls    = 0;
$aod_sl_sync           = new ReflectionMethod( 'AOD_Ship_Tracking', 'sync_carrier' );
$aod_sl_sync->setAccessible( true );
$aod_sl_passe = microtime( true ) - 10; // échéance dans le PASSÉ
$aod_sl_rf    = $aod_sl_sync->invoke( $aod_sl_svc, $aod_sl_stub, $aod_sl_cmds, $aod_sl_passe, true );
AOD_Test::ok(
	1 === $aod_sl_rf['updated'] && 2 === $aod_sl_rf['left'] && 1 === $aod_sl_stub->calls,
	sprintf( '🔴 Échéance déjà passée : la 1re fournée part QUAND MÊME, le reste est rendu (maj : %d, laissées : %d)', $aod_sl_rf['updated'], $aod_sl_rf['left'] ),
	'sans la garantie, 0 traitement ; sans l’échéance, 3 — les deux moitiés du contrat se vérifient ensemble'
);
// …et SANS force_first, la même échéance passée ne laisse rien partir : c'est
// bien le drapeau qui a fait la différence, pas un trou dans l'échéance.
$aod_sl_stub->calls = 0;
$aod_sl_rn          = $aod_sl_sync->invoke( $aod_sl_svc, $aod_sl_stub, $aod_sl_cmds, $aod_sl_passe, false );
AOD_Test::ok(
	0 === $aod_sl_rn['updated'] && 3 === $aod_sl_rn['left'] && 0 === $aod_sl_stub->calls,
	'ANCRAGE INVERSE : sans le drapeau, l’échéance passée ne laisse RIEN partir',
	'si quelque chose part ici, l’échéance ne coupe pas — le budget est de façade'
);

/* =========================================================================
 * R2 — La reprise se lit dans la donnée : une commande estampillée sort des
 *      candidates de la tranche suivante (curseur `since`)
 * ========================================================================= */

AOD_Test::group( 'Suivi 32.4 — R2 : la tranche suivante reprend où on s’est arrêté' );

// `since` = l'estampille que R1 vient de poser : la commande traitée est
// exclue (son estampille n'est pas STRICTEMENT antérieure), les 2 autres restent.
// ⚠️ R1bis (force_first, ci-dessus) a REstampillé une commande : on remet les
// colis 2 et 3 à zéro pour que R2 reparte de l'état exact de fin de R1.
foreach ( array( 1, 2 ) as $aod_sl_k ) {
	$aod_sl_cmds[ $aod_sl_k ]->delete_meta_data( AOD_Carrier::META_STATUS_AT );
	$aod_sl_cmds[ $aod_sl_k ]->save();
}
$aod_sl_since          = (int) $aod_sl_cmds[0]->get_meta( AOD_Carrier::META_STATUS_AT );
$aod_sl_stub->delay_ms = 0;
$aod_sl_stub->calls    = 0;
$aod_sl_stub->seen     = array();
$aod_sl_r2             = $aod_sl_svc->run_sync_batch( 30, $aod_sl_since, $aod_sl_cmds );

AOD_Test::ok(
	$aod_sl_since > 0,
	'TÉMOIN : la commande traitée en R1 porte bien une estampille (META_STATUS_AT)',
	'sans estampille, le « curseur dans la donnée » n’existe pas'
);
AOD_Test::ok(
	2 === $aod_sl_r2['updated'] && 0 === $aod_sl_r2['remaining'],
	sprintf( 'La tranche 2 traite EXACTEMENT les 2 restantes (maj : %d, restantes : %d)', $aod_sl_r2['updated'], $aod_sl_r2['remaining'] ),
	'moins = des colis oubliés ; plus = on resynchronise ce qui vient de l’être'
);
AOD_Test::ok(
	! in_array( 'S324-1', $aod_sl_stub->seen, true ),
	'🔴 Le colis déjà synchronisé n’est PAS réinterrogé — chaque tranche AVANCE',
	'sans ce filtre, « Actualiser tout » referait les mêmes colis à chaque tranche : la boucle ne finirait jamais'
);

/* =========================================================================
 * R3 — Une commande en erreur n'est pas estampillée (elle sera réessayée),
 *      et n'empêche jamais les autres d'aboutir
 * ========================================================================= */

AOD_Test::group( 'Suivi 32.4 — R3 : l’erreur d’un colis ne coûte rien aux autres' );

// Estampille SENTINELLE dans le passé : si le moteur estampillait à tort une
// commande en erreur, il poserait time() ≠ sentinelle — détection DÉTERMINISTE.
// (Comparer à l'estampille « d'avant » posée il y a < 1 s serait un faux vert :
// time() retomberait sur la même seconde.)
$aod_sl_avant = time() - 1000;
$aod_sl_cmds[1]->update_meta_data( AOD_Carrier::META_STATUS_AT, $aod_sl_avant );
$aod_sl_cmds[1]->save();

$aod_sl_stub->fail  = array( 'S324-2' );
$aod_sl_stub->calls = 0;
$aod_sl_r3          = $aod_sl_svc->run_sync_batch( 30, 0, array( $aod_sl_cmds[1], $aod_sl_cmds[2] ) );

AOD_Test::ok(
	1 === $aod_sl_r3['updated'] && 1 === $aod_sl_r3['errors'],
	sprintf( 'Le colis en panne est compté en ERREUR, l’autre aboutit (maj : %d, erreurs : %d)', $aod_sl_r3['updated'], $aod_sl_r3['errors'] ),
	'une entrée en erreur ne doit jamais empêcher les autres d’aboutir (contrat de get_tracking_bulk)'
);

// Relecture sur instance FRAÎCHE : l'estampille ne doit pas avoir bougé.
$aod_sl_frais = wc_get_order( $aod_sl_cmds[1]->get_id() );
AOD_Test::ok(
	$aod_sl_frais instanceof WC_Order && (int) $aod_sl_frais->get_meta( AOD_Carrier::META_STATUS_AT ) === $aod_sl_avant,
	'La commande en erreur n’est PAS estampillée : elle reste candidate au prochain passage',
	'l’estampiller « cacherait » l’échec jusqu’au tour suivant du roulement — des heures sans nouvel essai'
);
$aod_sl_stub->fail = array();

/* =========================================================================
 * R4 — Le suivi par lot reste un LOT : 3 colis, 1 appel réseau
 *
 * ⚠️ Sans cet ancrage, une régression qui retomberait sur « un appel par
 * colis » resterait verte partout ailleurs — et multiplierait par 40 la
 * charge sur l'API du transporteur.
 * ========================================================================= */

AOD_Test::group( 'Suivi 32.4 — R4 : le lot n’a pas été cassé par le budget' );

$aod_sl_stub->bulk  = 40;
$aod_sl_stub->calls = 0;
$aod_sl_r4          = $aod_sl_svc->run_sync_batch( 30, 0, $aod_sl_cmds );

AOD_Test::ok(
	3 === $aod_sl_r4['updated'] && 1 === $aod_sl_stub->calls,
	sprintf( '3 colis synchronisés en UN appel réseau (maj : %d, appels : %d)', $aod_sl_r4['updated'], $aod_sl_stub->calls ),
	'le moteur par tranches ne doit rien changer au regroupement par lot de la 12.x'
);

/* =========================================================================
 * R5 — Le câblage : plus personne n'appelle l'auto-DoS, et le timeout est réel
 *
 * 🚨 R1–R4 prouvent le MOTEUR ; R5 prouve qu'on s'en SERT. Un moteur par
 * tranches parfait derrière un appelant resté synchrone, c'est le défaut
 * intact sous un test vert.
 * ========================================================================= */

AOD_Test::group( 'Suivi 32.4 — R5 : les appelants passent par le moteur par tranches' );

// `ajax_tracking_refresh()` vit dans AOD_CD_Section_Tracking depuis la 30.5
// (il était dans le monolithe à la livraison de la 32.4) : R5 a suivi la classe.
$aod_sl_dash = defined( 'AOD_CD_PATH' ) ? (string) file_get_contents( AOD_CD_PATH . 'includes/class-aod-cd-section-tracking.php' ) : '';
AOD_Test::ok( '' !== $aod_sl_dash, 'TÉMOIN : class-aod-cd-section-tracking.php est lisible' );
AOD_Test::ok(
	false !== strpos( $aod_sl_dash, 'run_sync_batch( AOD_Ship_Tracking::AJAX_BUDGET_S' ),
	'« Actualiser tout » (AJAX) appelle le moteur par tranches avec le budget AJAX'
);
AOD_Test::ok(
	false === strpos( $aod_sl_dash, '$svc->run_sync()' ),
	'🔴 ANTI-RETOUR : plus aucun `$svc->run_sync()` synchrone dans le tableau de bord',
	'c’est la signature exacte de l’auto-DoS D23 — la réintroduire referait mourir la requête sous le gérant'
);

// `bindTracking` vit dans section-tracking.js depuis la 33.2 (décomposition JS).
$aod_sl_js = defined( 'AOD_CD_PATH' ) ? (string) file_get_contents( AOD_CD_PATH . 'assets/js/section-tracking.js' ) : '';
AOD_Test::ok( '' !== $aod_sl_js, 'TÉMOIN : section-tracking.js est lisible' );
AOD_Test::ok(
	false !== strpos( $aod_sl_js, 'res.data.partial' ) && false !== strpos( $aod_sl_js, 'since: state.since' ),
	'section-tracking.js enchaîne les tranches (partial + curseur since)',
	'sans la boucle côté navigateur, le serveur ne traiterait que la première tranche et s’arrêterait'
);

$aod_sl_track = (string) file_get_contents( AOD_COD_PATH . 'includes/class-aod-ship-tracking.php' );
AOD_Test::ok(
	false !== strpos( $aod_sl_track, 'run_sync_batch( self::CRON_BUDGET_S' ),
	'Le cron passe par le même moteur, avec le budget cron',
	'deux chemins de synchro divergents referaient le coup des gardes de la Phase 21'
);
AOD_Test::ok(
	false !== strpos( $aod_sl_track, "AOD_COD_Log::error( 'tracking'" ) && false !== strpos( $aod_sl_track, "AOD_COD_Log::debug( 'tracking'" ),
	'Chaque passage se JOURNALISE (via le rédacteur, jamais autour)',
	'sans trace, « le suivi ne bouge plus » reste indiagnosticable — c’est la moitié de D23'
);

AOD_Test::ok(
	AOD_Carrier::HTTP_TIMEOUT <= 10,
	sprintf( 'Timeout transporteur par défaut : %ds (les API DZ répondent en < 2 s ; 30 s ne rattrapaient rien)', AOD_Carrier::HTTP_TIMEOUT )
);
$aod_sl_carrier = (string) file_get_contents( AOD_COD_PATH . 'includes/class-aod-carrier.php' );
AOD_Test::ok(
	false !== strpos( $aod_sl_carrier, "'timeout' => self::HTTP_TIMEOUT" ) && false === strpos( $aod_sl_carrier, "'timeout' => 30" ),
	'…et remote() l’utilise vraiment (plus de 30 s en dur)',
	'une constante à 10 à côté d’un défaut resté à 30 serait un timeout de façade'
);

/* =========================================================================
 * R6 — Ce test n'a rien laissé derrière lui
 * ========================================================================= */

AOD_Test::group( 'Suivi 32.4 — R6 : aucun résidu (bouchon retiré, commandes supprimées)' );

// Retire le bouchon de la table des transporteurs.
$aod_sl_map = $aod_sl_prop->getValue( $aod_sl_ship );
unset( $aod_sl_map['stub-32-4'] );
$aod_sl_prop->setValue( $aod_sl_ship, $aod_sl_map );
AOD_Test::ok(
	null === $aod_sl_ship->carrier( 'stub-32-4' ),
	'Le transporteur bouchonné est retiré de la table'
);

$aod_sl_restants = 0;
foreach ( $aod_sl_cmds as $aod_sl_o ) {
	$aod_sl_id = $aod_sl_o->get_id();
	$aod_sl_o->delete( true );
	if ( wc_get_order( $aod_sl_id ) instanceof WC_Order ) {
		$aod_sl_restants++;
	}
}
AOD_Test::ok(
	0 === $aod_sl_restants,
	'Les 3 commandes jetables sont supprimées',
	$aod_sl_restants . ' commande(s) jetable(s) ont survécu au test'
);
