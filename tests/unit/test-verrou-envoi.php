<?php
/**
 * Une commande ne peut pas partir deux fois chez le transporteur (Phase 31.4).
 *
 * 🚨 LE DÉFAUT. `auto_send()` lisait `META_TRACKING` pour décider « déjà
 * expédié ? », puis **tout l'aller-retour HTTP — timeout 30 s — tenait entre
 * cette lecture et l'écriture** du suivi. Deux requêtes concurrentes passaient
 * donc toutes deux la garde : **deux colis expédiés, deux encaissements COD chez
 * le même client**. Même famille que la 31.2 — un `if` suivi d'un `set` n'est pas
 * une garde — mais autour d'un appel réseau, donc une fenêtre de SECONDES au lieu
 * de microsecondes. Elle ne demande aucune malchance : deux confirmateurs sur une
 * file partagée, ou un seul dont le navigateur expire et qui reclique.
 *
 * 🚨 ET LE CHEMIN MANUEL N'AVAIT AUCUNE GARDE DU TOUT. `ajax_send()` ne lisait
 * même pas `META_TRACKING` : deux clics créaient deux colis de façon
 * DÉTERMINISTE, sans qu'aucune course soit nécessaire. Plus grave que le défaut
 * documenté, et documenté nulle part.
 *
 * ⚠️ CE QUE CE FICHIER PROUVE, ET CE QU'IL NE PROUVE PAS. Il tourne dans UN seul
 * processus : il ne peut pas provoquer la course, exactement comme le cliquet de
 * la 31.2. Il garde deux choses qu'un test mono-processus peut réellement établir :
 *
 *   - R1/R2 : le verrou est bien EXCLUSIF et se reprend quand il est périmé ;
 *   - R3/R4 : le câblage tient — rien ne crée de colis hors du goulot, et la
 *     relecture après verrou est là.
 *
 * La preuve du MÉCANISME de bout en bout (deux envois → un seul colis) a été
 * faite à part, sur un transporteur BOUCHONNÉ — jamais une vraie API : quota de
 * production et configuration du gérant. Elle est relatée dans le commit.
 *
 * ⚠️ R3 EST LA RÈGLE QUI TIENDRA DANS LE TEMPS, pas R1. Un verrou qui fonctionne
 * ne protège que le code qui passe par lui : un futur `$carrier->create_parcel()`
 * appelé en direct — le geste le plus naturel du monde — rouvrirait le défaut
 * entier pendant que R1 resterait vert.
 *
 * ⚠️ IL N'ÉCRIT NI COMMANDE NI RÉGLAGE. Le verrou se teste sur un identifiant de
 * commande INEXISTANT : la primitive ne prend qu'un entier. R5 le vérifie.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Verrou d’envoi 31.4 — R0 : le socle est chargé' );

$aod_vrl_ok = class_exists( 'AOD_Shipping' ) && defined( 'AOD_Shipping::LOCK_PREFIX' );
AOD_Test::ok(
	$aod_vrl_ok,
	'AOD_Shipping expose le verrou d’envoi',
	$aod_vrl_ok ? '' : 'AOD_Shipping::LOCK_PREFIX introuvable'
);

if ( ! $aod_vrl_ok ) {
	return;
}

$aod_vrl_ship = AOD_Shipping::instance();

$aod_vrl_acquire = new ReflectionMethod( 'AOD_Shipping', 'acquire_send_lock' );
$aod_vrl_acquire->setAccessible( true );
$aod_vrl_release = new ReflectionMethod( 'AOD_Shipping', 'release_send_lock' );
$aod_vrl_release->setAccessible( true );

// Identifiant de commande volontairement INEXISTANT : la primitive ne manipule
// qu'un entier, donc aucune commande réelle n'est touchée.
$aod_vrl_id  = 999000731;
$aod_vrl_cle = AOD_Shipping::LOCK_PREFIX . $aod_vrl_id;
delete_option( $aod_vrl_cle );

/* =========================================================================
 * R1 — Le verrou est EXCLUSIF
 * ========================================================================= */

AOD_Test::group( 'Verrou d’envoi 31.4 — R1 : un seul détenteur à la fois' );

$aod_vrl_1 = (bool) $aod_vrl_acquire->invoke( $aod_vrl_ship, $aod_vrl_id );
AOD_Test::ok( $aod_vrl_1, 'Le premier envoi obtient le verrou' );

$aod_vrl_2 = (bool) $aod_vrl_acquire->invoke( $aod_vrl_ship, $aod_vrl_id );
AOD_Test::ok(
	! $aod_vrl_2,
	'🔴 Le second envoi se le voit REFUSER — c’est le second colis qui n’est pas créé',
	$aod_vrl_2 ? 'le verrou a été accordé DEUX fois : il ne verrouille rien' : ''
);

$aod_vrl_release->invoke( $aod_vrl_ship, $aod_vrl_id );
$aod_vrl_3 = (bool) $aod_vrl_acquire->invoke( $aod_vrl_ship, $aod_vrl_id );
AOD_Test::ok(
	$aod_vrl_3,
	'ANCRAGE : une fois rendu, le verrou est de nouveau disponible',
	$aod_vrl_3 ? '' : 'le verrou ne se rend pas — toute commande deviendrait inexpédiable'
);
$aod_vrl_release->invoke( $aod_vrl_ship, $aod_vrl_id );

/* =========================================================================
 * R2 — Un verrou PÉRIMÉ se reprend
 *
 * ⚠️ Sans ça, un processus tué pendant l'appel au transporteur (fatal, timeout
 * PHP, redémarrage) laisserait la commande inexpédiable POUR TOUJOURS, sans que
 * rien ne l'explique. On aurait échangé un défaut coûteux mais rare contre un
 * blocage définitif et muet — un mauvais échange.
 * ========================================================================= */

AOD_Test::group( 'Verrou d’envoi 31.4 — R2 : un verrou abandonné ne bloque pas à vie' );

// Verrou posé « il y a longtemps » : le processus qui le tenait est mort.
delete_option( $aod_vrl_cle );
add_option( $aod_vrl_cle, (string) ( time() - AOD_Shipping::LOCK_TTL - 60 ), '', false );

$aod_vrl_repris = (bool) $aod_vrl_acquire->invoke( $aod_vrl_ship, $aod_vrl_id );
AOD_Test::ok(
	$aod_vrl_repris,
	'Un verrou plus vieux que LOCK_TTL est repris (la commande redevient expédiable)'
);

// …mais un verrou FRAIS, lui, ne se vole pas : sinon la reprise rouvrirait le
// défaut qu'on vient de fermer, avec 5 minutes de latence.
$aod_vrl_vol = (bool) $aod_vrl_acquire->invoke( $aod_vrl_ship, $aod_vrl_id );
AOD_Test::ok(
	! $aod_vrl_vol,
	'🔴 ANCRAGE INVERSE : un verrou FRAIS n’est jamais volé (sinon la reprise rouvrirait le défaut)'
);
$aod_vrl_release->invoke( $aod_vrl_ship, $aod_vrl_id );

/* =========================================================================
 * R3 — ANTI-CONTOURNEMENT : aucun colis n'est créé hors du goulot
 *
 * 🚨 LA RÈGLE QUI PROTÈGE DANS LA DURÉE. Le verrou ne couvre que le code qui
 * passe par `send_parcel()`. Les trois chemins d'envoi (auto, manuel, groupé)
 * appelaient chacun `create_parcel()` en direct — c'est comme ça que le chemin
 * manuel s'est retrouvé sans aucune garde. Un quatrième chemin ajouté demain
 * referait la même chose sans que R1 bronche.
 * ========================================================================= */

AOD_Test::group( 'Verrou d’envoi 31.4 — R3 : rien ne crée de colis hors du goulot' );

$aod_vrl_cod = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : dirname( AOD_CD_PATH ) . '/aod-cod-form/';
$aod_vrl_src = (string) file_get_contents( $aod_vrl_cod . 'includes/class-aod-shipping.php' );

$aod_vrl_nb_create = substr_count( $aod_vrl_src, '->create_parcel(' );
AOD_Test::ok(
	1 === $aod_vrl_nb_create,
	sprintf( 'Un SEUL appel à create_parcel() dans AOD_Shipping (trouvé : %d)', $aod_vrl_nb_create ),
	1 === $aod_vrl_nb_create ? '' : 'un chemin d’envoi contourne le verrou — c’est le défaut d’origine qui revient'
);

$aod_vrl_nb_store = substr_count( $aod_vrl_src, '$this->store_result(' );
AOD_Test::ok(
	1 === $aod_vrl_nb_store,
	sprintf( 'Un SEUL enregistrement de résultat, dans le goulot (trouvé : %d)', $aod_vrl_nb_store )
);

// Le seul appel doit bien être DANS `send_parcel()` — sinon le compte de 1
// serait satisfait par un appel placé n'importe où ailleurs.
$aod_vrl_pos_send   = strpos( $aod_vrl_src, 'protected function send_parcel(' );
$aod_vrl_pos_create = strpos( $aod_vrl_src, '->create_parcel(' );
$aod_vrl_pos_apres  = strpos( $aod_vrl_src, 'protected function acquire_send_lock(' );
AOD_Test::ok(
	false !== $aod_vrl_pos_send && $aod_vrl_pos_create > $aod_vrl_pos_send && $aod_vrl_pos_create < $aod_vrl_pos_apres,
	'…et cet unique appel est bien à l’intérieur de send_parcel()'
);

/* =========================================================================
 * R4 — La RELECTURE après verrou existe
 *
 * 🚨 LE POINT SUBTIL DE TOUTE LA STORY. Le verrou SÉRIALISE les deux requêtes,
 * il ne les annule pas : la seconde l'obtient dès que la première l'a rendu —
 * donc APRÈS que le premier colis a été créé. Sans relire `META_TRACKING` sur une
 * instance FRAÎCHE une fois le verrou tenu, elle expédierait le second colis
 * quand même. Le verrou n'aurait servi qu'à mettre les deux envois à la queue
 * leu leu, et le défaut serait intact — tout en paraissant corrigé.
 * ========================================================================= */

AOD_Test::group( 'Verrou d’envoi 31.4 — R4 : la relecture après verrou (fonctionnel)' );

$aod_vrl_corps = substr( $aod_vrl_src, $aod_vrl_pos_send, $aod_vrl_pos_apres - $aod_vrl_pos_send );

AOD_Test::ok(
	false !== strpos( $aod_vrl_corps, 'finally' ),
	'Le verrou est rendu dans un `finally` (une exception ne doit pas le laisser posé 5 min)'
);

/*
 * 🚨 POURQUOI CE CONTRÔLE EST FONCTIONNEL ET PAS TEXTUEL — la leçon de sa propre
 * écriture. La première version cherchait « wc_get_order( » et « META_TRACKING »
 * dans le corps de la méthode. Éprouvée au rouge en supprimant la garde, elle
 * restait à moitié VERTE : la constante figurait encore dans un message de
 * journal quelques lignes plus bas. Resserrée sur le motif de lecture complet,
 * elle restait verte pour la MÊME raison. Une assertion textuelle qu'une mention
 * sans rapport suffit à satisfaire ne prouve rien — et sur ce défaut-là, croire
 * la relecture présente alors qu'elle est absente, c'est croire le double colis
 * corrigé alors qu'il ne l'est pas.
 *
 * On exerce donc le VRAI comportement, avec un transporteur BOUCHONNÉ : aucune
 * API réelle n'est appelée (quota de production, configuration du gérant).
 *
 * ⚠️ Les deux envois ci-dessous sont SÉQUENTIELS, à dessein : le verrou est déjà
 * rendu quand le second part. Ce n'est donc PAS le verrou qui peut l'arrêter —
 * seule la relecture le peut. C'est ce qui isole la garde qu'on veut prouver,
 * exactement comme les couches de rédaction de la 31.3 s'éprouvent séparément.
 */

if ( ! class_exists( 'AOD_Test_Carrier_Stub' ) ) {
	class AOD_Test_Carrier_Stub {
		public $appels = 0;
		public function id() {
			return 'stub-31-4';
		}
		public function label() {
			return 'Transporteur bouchonné';
		}
		public function create_parcel( $order ) {
			$this->appels++;
			return array(
				'tracking'  => 'STUB-31-4-' . $this->appels,
				'label'     => '',
				'import_id' => '',
			);
		}
	}
}

$aod_vrl_send = new ReflectionMethod( 'AOD_Shipping', 'send_parcel' );
$aod_vrl_send->setAccessible( true );

$aod_vrl_stub  = new AOD_Test_Carrier_Stub();
$aod_vrl_cmd   = wc_create_order();
$aod_vrl_cmd_id = $aod_vrl_cmd instanceof WC_Order ? $aod_vrl_cmd->get_id() : 0;

if ( ! $aod_vrl_cmd_id ) {
	AOD_Test::ok( false, 'Commande jetable créée pour éprouver le goulot', 'wc_create_order() a échoué' );
} else {
	$aod_vrl_cmd->update_meta_data( '_aod_delivery_type', 'home' );
	$aod_vrl_cmd->save();

	// 1er envoi : le colis doit partir.
	$aod_vrl_r1 = $aod_vrl_send->invoke( $aod_vrl_ship, $aod_vrl_cmd, $aod_vrl_stub );
	AOD_Test::ok(
		! is_wp_error( $aod_vrl_r1 ) && 1 === $aod_vrl_stub->appels,
		'ANCRAGE : le PREMIER envoi crée bien le colis (sinon tout le reste serait vrai par vacuité)',
		is_wp_error( $aod_vrl_r1 ) ? $aod_vrl_r1->get_error_message() : 'appels : ' . $aod_vrl_stub->appels
	);

	// 2e envoi, verrou déjà rendu : SEULE la relecture peut l'arrêter.
	$aod_vrl_r2 = $aod_vrl_send->invoke( $aod_vrl_ship, $aod_vrl_cmd, $aod_vrl_stub );
	AOD_Test::ok(
		is_wp_error( $aod_vrl_r2 ) && 'aod_ship_already_sent' === $aod_vrl_r2->get_error_code(),
		'🔴 Le SECOND envoi est refusé par la relecture — pas par le verrou (il est rendu)',
		is_wp_error( $aod_vrl_r2 ) ? 'code : ' . $aod_vrl_r2->get_error_code() : 'aucune erreur : le second colis est parti'
	);
	AOD_Test::ok(
		1 === $aod_vrl_stub->appels,
		sprintf( '🔴 LE TRANSPORTEUR N’A ÉTÉ APPELÉ QU’UNE FOIS (appels : %d) — c’est TOUT le sujet de la story', $aod_vrl_stub->appels )
	);

	// …et le chemin du verrou, lui, se prouve en le tenant pendant l'envoi.
	$aod_vrl_cmd->delete_meta_data( AOD_Carrier::META_TRACKING );
	$aod_vrl_cmd->save();
	$aod_vrl_acquire->invoke( $aod_vrl_ship, $aod_vrl_cmd_id );
	$aod_vrl_r3 = $aod_vrl_send->invoke( $aod_vrl_ship, $aod_vrl_cmd, $aod_vrl_stub );
	AOD_Test::ok(
		is_wp_error( $aod_vrl_r3 ) && 'aod_ship_in_progress' === $aod_vrl_r3->get_error_code(),
		'🔴 Verrou tenu ailleurs : l’envoi est refusé AVANT tout appel réseau',
		is_wp_error( $aod_vrl_r3 ) ? 'code : ' . $aod_vrl_r3->get_error_code() : 'aucune erreur'
	);
	AOD_Test::ok(
		1 === $aod_vrl_stub->appels,
		sprintf( 'Toujours un seul appel transporteur (appels : %d)', $aod_vrl_stub->appels )
	);
	$aod_vrl_release->invoke( $aod_vrl_ship, $aod_vrl_cmd_id );

	// Ces deux erreurs ne sont PAS des pannes transporteur : les traiter comme
	// telles inventerait un incident là où le garde-fou a fait son travail.
	$aod_vrl_isconc = new ReflectionMethod( 'AOD_Shipping', 'is_concurrency_error' );
	$aod_vrl_isconc->setAccessible( true );
	AOD_Test::ok(
		$aod_vrl_isconc->invoke( $aod_vrl_ship, $aod_vrl_r2 ) && $aod_vrl_isconc->invoke( $aod_vrl_ship, $aod_vrl_r3 ),
		'Les deux refus sont reconnus comme de la concurrence (jamais notés « envoi échoué »)'
	);
	AOD_Test::ok(
		! $aod_vrl_isconc->invoke( $aod_vrl_ship, new WP_Error( 'aod_carrier_http', 'HTTP 500' ) ),
		'ANCRAGE INVERSE : une vraie panne transporteur reste, elle, une panne'
	);

	// Ménage : la commande jetable ne survit pas au test.
	$aod_vrl_cmd->delete( true );
	delete_option( AOD_Shipping::LOCK_PREFIX . $aod_vrl_cmd_id );
	AOD_Test::ok(
		! ( wc_get_order( $aod_vrl_cmd_id ) instanceof WC_Order ),
		'La commande jetable est supprimée'
	);
}

/* =========================================================================
 * R5 — Ce test n'a rien laissé derrière lui
 * ========================================================================= */

AOD_Test::group( 'Verrou d’envoi 31.4 — R5 : aucun résidu' );

delete_option( $aod_vrl_cle );
AOD_Test::ok(
	false === get_option( $aod_vrl_cle, false ),
	'Le verrou témoin est retiré'
);
AOD_Test::ok(
	! ( wc_get_order( $aod_vrl_id ) instanceof WC_Order ),
	'L’identifiant utilisé ne correspond à aucune commande réelle (rien n’a été expédié)'
);
