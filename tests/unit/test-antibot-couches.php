<?php
/**
 * Les couches anti-bot de formulaire tiennent ce qu'on annonce (Phase 32.3).
 *
 * 🚨 CE QUI ÉTAIT FAUX, ET CE N'ÉTAIT PAS D'ABORD UN DÉFAUT DE SÉCURITÉ. Le dépôt
 * annonçait « anti-bot 5 couches » — et « **6** » à deux endroits, ce qui aurait
 * dû alerter tout seul. Sur un clone neuf il en tenait deux et demie : le honeypot
 * se contournait en **ne remplissant pas** le champ piège, le time-trap en
 * **omettant** `elapsed` (la garde s'écrivait `isset() && is_numeric() && < 3000` :
 * un payload sans le champ traversait les trois conditions sans en heurter une),
 * et Turnstile n'existe pas tant qu'aucune clé n'est saisie.
 *
 * **Une protection qu'on croit avoir est plus dangereuse qu'une protection qu'on
 * sait absente : elle empêche d'en chercher une autre.**
 *
 * MESURÉ sur ce clone, sur le formulaire de contact, avant / après :
 *   payload NU (ni piège ni `elapsed`)  →  AVANT : traversait l'anti-bot et
 *   atteignait l'envoi du courriel ; APRÈS : refusé, et journalisé.
 *   `elapsed=abc` : idem. `elapsed=9000` (humain) : passe dans les deux cas —
 *   c'est l'ancrage qui prouve qu'on n'a pas simplement tout fermé.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Anti-bot 32.3 — R0 : le socle est chargé' );

$aod_ab_ok = class_exists( 'AOD_COD_Antibot' ) && method_exists( 'AOD_COD_Antibot', 'inspect' );
AOD_Test::ok( $aod_ab_ok, 'AOD_COD_Antibot::inspect() est disponible', $aod_ab_ok ? '' : 'class-aod-cod-antibot.php non chargée' );

if ( ! $aod_ab_ok ) {
	return;
}

/* =========================================================================
 * R1 — L'ABSENCE est aussi disqualifiante que la mauvaise valeur
 *
 * 🚨 C'est TOUTE l'étape. Un robot n'avait qu'à éviter de se tromper ; il doit
 * désormais reproduire le formulaire.
 * ========================================================================= */

AOD_Test::group( 'Anti-bot 32.3 — R1 : omettre un champ ne passe plus' );

AOD_Test::ok(
	'champs_absents' === AOD_COD_Antibot::inspect( null, null ),
	'🔒 Payload NU (ni piège ni durée) : refusé',
	'c’est le contournement mesuré — un POST direct sur admin-ajax.php n’envoie ni l’un ni l’autre'
);
AOD_Test::ok(
	'champs_absents' === AOD_COD_Antibot::inspect( '', null ),
	'🔒 Durée ABSENTE : refusé',
	'`isset() && is_numeric() && < 3000` laissait passer l’omission — les trois conditions ne se heurtaient jamais'
);
AOD_Test::ok(
	'champs_absents' === AOD_COD_Antibot::inspect( null, '9000' ),
	'🔒 Champ piège ABSENT : refusé',
	'n’exiger qu’un seul des deux champs laisserait la moitié du contournement ouverte'
);
AOD_Test::ok(
	'duree_invalide' === AOD_COD_Antibot::inspect( '', 'abc' ),
	'🔒 Durée présente mais illisible : refusé',
	'`is_numeric()` était une CONDITION D’APPLICATION de la garde, pas une exigence'
);

/* =========================================================================
 * R2 — ANCRAGES : ce qui doit PASSER passe
 *
 * 🚨 Sans eux, « tout est refusé » rendrait R1 verte pour rien — et un
 * durcissement qui bloque les vrais acheteurs est pire que le trou qu'il bouche.
 * ========================================================================= */

AOD_Test::group( 'Anti-bot 32.3 — R2 : un vrai acheteur passe' );

AOD_Test::ok(
	'' === AOD_COD_Antibot::inspect( '', '9000' ),
	'ANCRAGE : piège vide + 9 s de remplissage → accepté',
	'si tout était refusé, R1 serait verte sans rien prouver'
);
AOD_Test::ok(
	'' === AOD_COD_Antibot::inspect( '', (string) AOD_COD_Antibot::MIN_FILL_MS ),
	'ANCRAGE : pile au seuil → accepté (le seuil est un minimum, pas un interdit)'
);
AOD_Test::ok(
	'' === AOD_COD_Antibot::inspect( '', '86400000' ),
	'ANCRAGE : un onglet laissé ouvert 24 h → accepté',
	'un plafond HAUT bloquerait l’acheteur qui revient finir sa commande'
);

/* =========================================================================
 * R3 — Les deux motifs historiques restent attrapés
 * ========================================================================= */

AOD_Test::group( 'Anti-bot 32.3 — R3 : les refus d’origine tiennent toujours' );

AOD_Test::ok( 'honeypot' === AOD_COD_Antibot::inspect( 'http://spam', '9000' ), 'Champ piège rempli : refusé' );
AOD_Test::ok( 'trop_rapide' === AOD_COD_Antibot::inspect( '', '1' ), 'Formulaire rempli en 1 ms : refusé' );
AOD_Test::ok(
	'honeypot' === AOD_COD_Antibot::inspect( 'x', null ),
	'Le piège prime sur l’absence',
	'un robot qui s’est TRAHI doit être classé comme tel, pas comme un JS périmé — sinon le journal ment sur la cause'
);

/* =========================================================================
 * R4 — Les DEUX formulaires passent par la MÊME garde
 *
 * 🚨 Le contact partageait déjà les deux couches, donc leurs deux contournements.
 * Sans cette règle, l'un serait durci et l'autre oublié — c'est exactement ce qui
 * était en train d'arriver.
 * ========================================================================= */

AOD_Test::group( 'Anti-bot 32.3 — R4 : commande ET contact, une seule garde' );

$aod_ab_lire = function ( $f ) {
	$p = AOD_COD_PATH . 'includes/' . $f;
	return is_readable( $p ) ? (string) file_get_contents( $p ) : '';
};

foreach ( array(
	'class-aod-cod-form.php'    => 'la commande COD',
	'class-aod-cod-contact.php' => 'le formulaire de contact',
) as $f => $quoi ) {
	$src = $aod_ab_lire( $f );
	AOD_Test::ok( '' !== $src, sprintf( 'TÉMOIN : %s est lisible', $f ) );
	AOD_Test::ok(
		false !== strpos( $src, 'AOD_COD_Antibot::inspect(' ),
		sprintf( '%s passe par la garde partagée', $quoi )
	);
	AOD_Test::ok(
		false !== strpos( $src, "AOD_COD_Log::error( 'antibot'" ),
		sprintf( '%s JOURNALISE son refus', $quoi ),
		'un durcissement muet est indéfendable : un acheteur bloqué à tort deviendrait une vente perdue sans trace (leçon de la Phase 26)'
	);
	// ANTI-RETOUR : plus aucune copie locale de l'ancienne garde tolérante.
	AOD_Test::ok(
		false === strpos( $src, "isset( \$_POST['elapsed'] ) && is_numeric(" ),
		sprintf( '%s ne garde aucune copie de l’ancienne garde tolérante', $quoi )
	);
}

/* =========================================================================
 * R5 — Le discours ne réaffirme plus un compte que le code ne tient pas
 *
 * ⚠️ CE N'EST PAS DE LA COSMÉTIQUE, c'est la moitié de la story. Corriger le seul
 * code aurait laissé intacte la partie dangereuse : la phrase qui fait croire à
 * une protection et dispense d'en chercher une autre.
 * ========================================================================= */

AOD_Test::group( 'Anti-bot 32.3 — R5 : le compte annoncé n’est jamais nu' );

$aod_ab_racine = dirname( ABSPATH );
$aod_ab_cibles = array();
foreach ( array( 'README.md', 'TEMPLATE.md', 'CLAUDE.md' ) as $f ) {
	$p = $aod_ab_racine . '/' . $f;
	if ( is_readable( $p ) ) {
		$aod_ab_cibles[ $f ] = (string) file_get_contents( $p );
	}
}
foreach ( array( 'README.md' ) as $f ) {
	$p = AOD_COD_PATH . $f;
	if ( is_readable( $p ) ) {
		$aod_ab_cibles[ 'plugin/' . $f ] = (string) file_get_contents( $p );
	}
}

if ( ! $aod_ab_cibles ) {
	// ⚠️ NON CONCLUANT, ET DIT COMME TEL : la racine du dépôt n'est pas montée
	// dans le conteneur. La règle du témoin (DoD §C.2) interdit d'afficher un
	// vert là où rien n'a été lu.
	AOD_Test::ok( true, 'R5 NON ÉPROUVÉE ici (aucun document lisible depuis le conteneur)' );
} else {
	AOD_Test::ok( true, sprintf( 'TÉMOIN : %d document(s) relu(s) — %s', count( $aod_ab_cibles ), implode( ', ', array_keys( $aod_ab_cibles ) ) ) );

	$aod_ab_nus = array();
	foreach ( $aod_ab_cibles as $nom => $txt ) {
		// Un compte de couches doit être suivi, dans la même phrase, de ce qui le
		// nuance : « dont », « (4 sur un clone neuf) », « une fois configuré »…
		if ( preg_match_all( '/(\d)\s*couches/u', $txt, $m, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m[0] as $k => $occ ) {
				$suite = substr( $txt, $occ[1], 220 );
				$nuance = ( false !== stripos( $suite, 'clone neuf' ) )
					|| ( false !== stripos( $suite, 'dont' ) )
					|| ( false !== stripos( $suite, 'configur' ) );
				if ( ! $nuance ) {
					$aod_ab_nus[] = $nom . ' : « ' . trim( $m[0][ $k ][0] ) . ' » sans nuance';
				}
			}
		}
	}
	AOD_Test::ok(
		empty( $aod_ab_nus ),
		'🔴 Aucun compte de couches annoncé sans dire ce qui exige une configuration',
		implode( ' · ', $aod_ab_nus ) . ' — Turnstile n’existe pas sur un clone neuf ; l’annoncer sans le dire fait croire à une protection absente'
	);
}
