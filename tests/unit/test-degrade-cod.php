<?php
/**
 * `/gestion` ne meurt pas sans le plugin COD (Phase 32.1).
 *
 * 🚨 LE DÉFAUT. Les deux plugins maison ont des interrupteurs d'activation
 * INDÉPENDANTS. Le chargeur du tableau de bord déclare une « dépendance forte
 * (NON BLOQUANTE) » : sans `aod-cod-form`, « ces parties de l'espace de gestion
 * se désactivent PROPREMENT ». Le code ne tenait pas la promesse — `/gestion`
 * tombait en erreur fatale : `Class "AOD_Carrier" not found`.
 *
 * 🚨 ET LA GARDE EXISTAIT. `count_tracking_status()` commence bien par
 * `if ( ! class_exists( 'AOD_Carrier' ) ) { return 0; }`. Mais l'appel s'écrivait
 * `$this->count_tracking_status( AOD_Carrier::ST_FAILED )` : **PHP résout la
 * constante pour construire l'appel, donc AVANT d'entrer dans la méthode**. Une
 * garde placée derrière ce qu'elle protège n'est pas une garde — elle est pire,
 * parce qu'elle donne à la relecture toutes les apparences de la sûreté.
 *
 * ⚠️ CE FICHIER N'EST PAS LA PREUVE, C'EST UN FILET PRÉCOCE. Un test unitaire
 * tourne DANS un WordPress où `aod-cod-form` est actif : il ne peut pas éprouver
 * l'absence. La preuve est la porte `bash tools/recette/run.sh degrade`, lancée
 * via `wp --skip-plugins=aod-cod-form` — un processus isolé, sans jamais
 * désactiver quoi que ce soit sur le site. Ici on garde le MOTIF, pour attraper
 * la récidive avant qu'on pense à lancer la porte.
 *
 * ⚠️ ET C'EST UN CONTRÔLE TEXTUEL, donc faillible — la 31.4 l'a montré : une
 * assertion sur le texte du code survit à des défauts réels. Il complète la
 * porte, il ne la remplace pas.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Dégradation 32.1 — R0 : le socle est chargé' );

$aod_dgu_ok = class_exists( 'AOD_CD_Cod' ) && method_exists( 'AOD_CD_Cod', 'active' );
AOD_Test::ok( $aod_dgu_ok, 'AOD_CD_Cod est disponible', $aod_dgu_ok ? '' : 'class-aod-cd-cod.php non chargée' );

if ( ! $aod_dgu_ok ) {
	return;
}

// Sur ce processus le plugin COD est actif : `active()` doit le dire. C'est
// l'ancrage inverse de celui de la porte — les deux états sont donc vus.
AOD_Test::ok(
	AOD_CD_Cod::active(),
	'ANCRAGE : ici le socle COD est PRÉSENT, et active() le rapporte',
	'si active() rendait toujours false, la dégradation serait « propre » partout et le tableau de bord vide'
);

/*
 * 🚨 IL N'Y A PAS DE R1, ET C'EST UN CONSTAT, PAS UN OUBLI.
 *
 * La règle naturelle serait : « aucune constante `AOD_Carrier::X` passée en
 * ARGUMENT d'un appel » — la forme exacte du défaut. Elle a été écrite, puis
 * retirée : elle signalait **30 sites** dont **un seul** était réellement en
 * faute (l'audit indépendant en a confirmé un, celui de `section-home.php`).
 * Les 29 autres sont parfaitement sûrs — ils vivent dans une méthode dont la
 * PREMIÈRE ligne est un `class_exists()`.
 *
 * Le problème est décidable en lecture, pas en `preg_match` : savoir si une
 * ligne est gardée demande de remonter la portée, puis les appelants. Un
 * contrôle textuel ne peut pas trancher, et **un cliquet qui crie au loup sur du
 * code sain finit par être ignoré** — c'est-à-dire par ne plus rien garder du
 * tout. Mieux vaut pas de règle qu'une règle qu'on apprend à contourner.
 *
 * LA PREUVE EST AILLEURS, et elle est meilleure : `bash tools/recette/run.sh
 * degrade` REND les 12 sections dans un processus démarré sans le plugin COD, et
 * refuse de conclure si l'état dégradé n'est pas en place. Éprouvée au rouge sur
 * la signature exacte du défaut : la ligne d'origine remise, la porte rapporte
 * `Class "AOD_Carrier" not found @ class-aod-cd-section-home.php:555`.
 *
 * Ce fichier garde donc ce qui EST décidable textuellement : R2 et R3.
 */

/* =========================================================================
 * R2 — Les helpers publics de suivi sont sûrs PAR CONSTRUCTION
 *
 * `tracking_tone()` et `tracking_pill()` sont `public static` et n'avaient
 * aucune garde : elles n'étaient sûres que parce que TOUS leurs appelants en
 * portaient une. Une propriété qui se périme au premier appel écrit ailleurs.
 * ========================================================================= */

AOD_Test::group( 'Dégradation 32.1 — R2 : les statiques publiques se gardent elles-mêmes' );

// Les deux statiques vivent dans AOD_CD_Section_Tracking depuis la 30.5 (elles
// étaient dans le monolithe à la livraison de la 32.1) : R2 a suivi la classe.
$aod_dgu_dash = defined( 'AOD_CD_PATH' ) ? (string) file_get_contents( AOD_CD_PATH . 'includes/class-aod-cd-section-tracking.php' ) : '';

foreach ( array( 'tracking_tone', 'tracking_pill' ) as $aod_dgu_m ) {
	$pos = strpos( $aod_dgu_dash, 'public static function ' . $aod_dgu_m . '(' );
	$corps = false !== $pos ? substr( $aod_dgu_dash, $pos, 500 ) : '';
	AOD_Test::ok(
		false !== strpos( $corps, 'AOD_CD_Cod::active()' ),
		sprintf( '%s() vérifie elle-même la présence du socle COD', $aod_dgu_m ),
		'sûre seulement par ses appelants — un appel écrit demain depuis un chemin non gardé rouvre la fatale'
	);
}

/* =========================================================================
 * R3 — La porte de recette existe et NE désactive rien
 *
 * 🚨 Une porte qui ferait `wp plugin deactivate` sur le clone d'un vrai client
 * couperait la boutique, et la laisserait coupée si elle mourait entre les deux
 * commandes. L'isolation par processus (`--skip-plugins`) rend l'accident
 * impossible au lieu d'essayer de le rattraper. Cf. dette D26.
 * ========================================================================= */

AOD_Test::group( 'Dégradation 32.1 — R3 : la porte n’éteint jamais la boutique' );

$aod_dgu_run = '';
foreach ( array( dirname( ABSPATH ) . '/tools/recette/run.sh', '/tools/recette/run.sh' ) as $c ) {
	if ( is_readable( $c ) ) {
		$aod_dgu_run = (string) file_get_contents( $c );
		break;
	}
}

if ( '' === $aod_dgu_run ) {
	// ⚠️ NON CONCLUANT, ET DIT COMME TEL. `tools/recette/` n'est pas monté dans le
	// conteneur wpcli : R3 ne peut rien inspecter ici. On l'affiche pour que
	// personne ne lise « 2 contrôles verts » là où il n'y en a eu aucun — c'est
	// la règle du témoin (DoD §C.2), et c'est ce qui distingue un contrôle
	// inapplicable d'un contrôle satisfait.
	AOD_Test::ok( true, 'R3 NON ÉPROUVÉE ici (tools/recette non monté dans wpcli) — la porte `degrade` reste la preuve' );
} else {
	AOD_Test::ok(
		false !== strpos( $aod_dgu_run, '--skip-plugins=aod-cod-form' ),
		'La porte `degrade` s’isole par processus (--skip-plugins)'
	);
	AOD_Test::ok(
		false === strpos( $aod_dgu_run, 'plugin deactivate' ),
		'🔒 Aucune porte n’exécute `wp plugin deactivate`',
		'désactiver le plugin COD d’un clone en production coupe la boutique'
	);
}
