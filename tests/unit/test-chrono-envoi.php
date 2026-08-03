<?php
/**
 * Le temps que le confirmateur ATTEND est mesuré (instrumentation du cadrage D27).
 *
 * 🚨 CE QUE CE CLIQUET GARDE, ET POURQUOI IL VAUT LA PEINE. D27 a vécu des mois
 * en annonçant « HTTP jusqu'à 30 s » alors que le plafond était retombé à **10 s**
 * en Phase 32.4. Personne ne l'a vu, parce que ce chiffre n'était mesuré NULLE
 * PART : `AOD_Carrier::remote()` ne journalisait que les échecs, jamais une durée.
 * On a donc arbitré — ou plutôt reporté d'arbitrer — sur une impression.
 *
 * L'instrumentation est le préalable au choix, pas un confort. Si un futur
 * nettoyage retire ces compteurs (ils ne « servent » à aucune fonctionnalité),
 * l'arbitrage redevient impossible et la dette repart pour un tour. Ce cliquet
 * existe pour que ça coûte un rouge.
 *
 * ⚠️ Aucun octet ne part sur le réseau : les appels sont bouchonnés par
 * `pre_http_request`, avec une temporisation connue. On mesure l'INSTRUMENT,
 * pas un transporteur.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Chrono envoi D27 — R1 : l’instrument existe' );

$aod_ch_socle = class_exists( 'AOD_Carrier' )
	&& method_exists( 'AOD_Carrier', 'chrono_reset' )
	&& method_exists( 'AOD_Carrier', 'chrono' );
AOD_Test::ok(
	$aod_ch_socle,
	'🔴 `AOD_Carrier::chrono_reset()` et `chrono()` existent',
	'sans eux, le temps d’attente du confirmateur n’est mesuré nulle part — et D27 redevient inarbitrable'
);

if ( ! $aod_ch_socle ) {
	return;
}

AOD_Test::ok(
	false !== strpos( (string) file_get_contents( AOD_COD_PATH . 'includes/class-aod-shipping.php' ), 'AOD_Carrier::chrono_reset()' ),
	// 🔁 47.2 — LIBELLÉ CORRIGÉ, ASSERTION INCHANGÉE. La méthode s'appelait
	// `auto_send()` ; elle s'appelle `ship_order()`. L'assertion, elle, grepe le
	// FICHIER : elle serait donc restée VERTE en annonçant une méthode qui
	// n'existe plus. Un libellé qui ment est pire qu'un test absent — il fait
	// croire que la règle est gardée, et on ne va pas vérifier. Même famille que
	// le défaut trouvé au red-proof de la 47.1 (une assertion satisfaite par
	// autre chose que ce qu'elle annonce).
	'🔴 `ship_order()` remet le chronomètre à zéro avant l’envoi',
	'sans reset, le cumul d’un envoi embarquerait les appels du précédent'
);
AOD_Test::ok(
	(bool) preg_match( '/AOD_COD_Log::debug\(\s*\'carrier\'/', (string) file_get_contents( AOD_COD_PATH . 'includes/class-aod-shipping.php' ) ),
	'🔴 …et rend compte par UNE ligne de journal',
	'une mesure qui ne sort pas du process ne sert à personne'
);

/* =========================================================================
 * R2 — L'instrument MESURE (et ne se contente pas d'exister)
 * ========================================================================= */

AOD_Test::group( 'Chrono envoi D27 — R2 : il compte les appels et le temps' );

$aod_ch_delai = 220000; // 220 ms par appel bouchonné.
$aod_ch_stub  = function () use ( $aod_ch_delai ) {
	usleep( $aod_ch_delai );
	return array(
		'headers'  => array(),
		'body'     => '{}',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $aod_ch_stub, 10, 3 );

try {
	$aod_ch_ship = AOD_Shipping::instance();
	$aod_ch_ref  = new ReflectionObject( $aod_ch_ship );
	$aod_ch_get  = $aod_ch_ref->getMethod( 'carrier' );
	$aod_ch_get->setAccessible( true );
	$aod_ch_car = $aod_ch_get->invoke( $aod_ch_ship, 'yalidine' );

	AOD_Test::ok( $aod_ch_car instanceof AOD_Carrier, 'ANCRAGE : un transporteur est instanciable pour la mesure' );

	$aod_ch_rem = ( new ReflectionObject( $aod_ch_car ) )->getMethod( 'remote' );
	$aod_ch_rem->setAccessible( true );

	AOD_Carrier::chrono_reset();
	$aod_ch_vide = AOD_Carrier::chrono();
	AOD_Test::ok(
		0 === $aod_ch_vide['appels'] && 0 === $aod_ch_vide['ms'],
		'🔴 `chrono_reset()` remet bien à zéro',
		sprintf( 'lu : %d appel(s), %d ms', $aod_ch_vide['appels'], $aod_ch_vide['ms'] )
	);

	$aod_ch_rem->invokeArgs( $aod_ch_car, array( 'GET', 'https://exemple.invalid/ping', array() ) );
	$aod_ch_rem->invokeArgs( $aod_ch_car, array( 'GET', 'https://exemple.invalid/ping', array() ) );
	$aod_ch_m = AOD_Carrier::chrono();

	AOD_Test::eq( 2, $aod_ch_m['appels'], '🔴 Deux appels réseau sont COMPTÉS' );
	// Bornes larges à dessein : on prouve que la durée est RÉELLEMENT mesurée
	// (et non figée à 0 ou à une constante), pas la précision de `usleep`.
	$aod_ch_attendu = (int) ( 2 * $aod_ch_delai / 1000 );
	AOD_Test::ok(
		$aod_ch_m['ms'] >= $aod_ch_attendu * 0.8 && $aod_ch_m['ms'] < $aod_ch_attendu * 3,
		sprintf( '🔴 …et leur DURÉE est mesurée (%d ms pour ~%d ms bouchonnés)', $aod_ch_m['ms'], $aod_ch_attendu ),
		'hors bornes : le compteur est figé, ou il ne mesure pas ce qu’il croit'
	);
	AOD_Test::ok(
		$aod_ch_m['ms'] > 0,
		'ANCRAGE INVERSE : la durée n’est pas 0 — un compteur toujours nul passerait toutes les bornes hautes'
	);

	AOD_Carrier::chrono_reset();
} finally {
	remove_filter( 'pre_http_request', $aod_ch_stub, 10 );
}

AOD_Test::group( 'Chrono envoi D27 — R3 : aucun résidu' );
AOD_Test::ok(
	! has_filter( 'pre_http_request', $aod_ch_stub ),
	'Le bouchon HTTP est retiré (aucun appel réel n’a été fait, aucun ne le sera)'
);
$aod_ch_fin = AOD_Carrier::chrono();
AOD_Test::ok(
	0 === $aod_ch_fin['appels'],
	'Le chronomètre est rendu à zéro pour la suite de la suite'
);
