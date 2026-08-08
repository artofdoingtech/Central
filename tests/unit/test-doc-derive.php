<?php
/**
 * La documentation ne peut plus décrocher de son propre code en silence
 * (Phase 32.6, dette D25).
 *
 * 🚨 LE DÉFAUT, DÉJÀ MORDU QUATRE FOIS. `CLAUDE.md` et `TEMPLATE.md` désignent
 * la table §C.3 de la ROADMAP comme « faisant foi » — or elle s'était arrêtée à
 * 30.1 quand 30.2 et 30.3 étaient commitées ; la ligne d'état de TEMPLATE.md a
 * annoncé DEUX fois un état de phases que le texte juste au-dessus contredisait ;
 * et trois énoncés de story d'affilée (32.2, 32.3, 32.5) portaient des chiffres
 * faux. Le risque n'est pas l'inexactitude : c'est qu'un document lu AVANT tout
 * code affirme le contraire du code — de quoi faire ré-implémenter une
 * fonctionnalité livrée (c'est arrivé : TEMPLATE.md a nié pendant trois jours le
 * bilinguisme du contenu marchand que ARCHITECTURE.md §6 décrivait).
 *
 * CE QUE CE CLIQUET RECOUPE — mécaniquement, à chaque run :
 *   R2  en-têtes « ### N.M … ✅ » de la ROADMAP  ⇄  statut de la ligne §C.3 ;
 *   R3  compteur « Phase 32 … (N/6 » de TEMPLATE.md  =  ✅ comptés en §C.3 ;
 *   R4  « N sections extraites sur 6 » (Phase 30)  =  ✅ des lignes 30.x ;
 *   R5  « Les Phases 1 à N sont soldées »  ⇒  aucune ligne ⏳ de phase ≤ N ;
 *   R6  tout `test-*.php` cité EXISTE, toute porte `run.sh <x>` citée EXISTE ;
 *   R7  toute étape `N.M` COMMITÉE a une ligne en §C.3 (dette D30).
 *
 * 🚨 R7 — CE QUI MANQUAIT, ET QUI A LAISSÉ PASSER LA 42.7. Jusqu'à la dette D30,
 * ce cliquet recoupait deux DOCUMENTS entre eux, jamais un document au DÉPÔT.
 * Conséquence mesurée : l'étape 42.7 a vécu six jours en git SANS ligne en §C.3
 * ni section en Partie B — et aucune règle ne la cherchait, puisqu'aucune ne part
 * du code. Les statuts 43.1→43.8 sont restés « ⏳ » pendant que le code était
 * commité : les deux documents étaient d'accord ENTRE EUX, et faux tous les deux.
 * R7 exige l'EXISTENCE d'une ligne, jamais son statut — le report de la mise à
 * jour à l'étape de clôture est une CONVENTION de ce dépôt (chaque story écrit
 * « MAJ §C.3 déférée à N.9 »), et un cliquet qui la contredirait serait rouge
 * toute la phase, donc ignoré (dette D16).
 *
 * ⚠️ CE QU'IL NE PROUVE PAS : la PROSE. Un paragraphe qui raconte faux sans
 * chiffre ni référence lui échappe — c'est la relecture humaine qui tient ce
 * front. Il fige le squelette (états, compteurs, références) pour que la
 * relecture n'ait plus que la chair à juger.
 *
 * 🔧 Il lit les docs via le montage `/repo` (lecture seule, docker-compose.yml).
 * S'il manque, le test ÉCHOUE avec la marche à suivre — un cliquet qui se
 * saute en silence sur les clones serait exactement le défaut qu'il garde.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Dérive doc 32.6 — R0 : le dépôt est lisible' );

$aod_dd_repo = '/repo';
$aod_dd_ok   = is_readable( $aod_dd_repo . '/TEMPLATE.md' ) && is_readable( $aod_dd_repo . '/docs/ROADMAP-AMELIORATIONS.md' );
AOD_Test::ok(
	$aod_dd_ok,
	'Le dépôt est monté en /repo (docs lisibles)',
	'montage absent — lancer `docker compose up -d wpcli` depuis le dossier du clone pour appliquer le volume ./:/repo:ro'
);

if ( ! $aod_dd_ok ) {
	return;
}

$aod_dd_road = (string) file_get_contents( $aod_dd_repo . '/docs/ROADMAP-AMELIORATIONS.md' );
$aod_dd_tpl  = (string) file_get_contents( $aod_dd_repo . '/TEMPLATE.md' );
$aod_dd_cl   = is_readable( $aod_dd_repo . '/CLAUDE.md' ) ? (string) file_get_contents( $aod_dd_repo . '/CLAUDE.md' ) : '';
// 44.3 — DEUX FICHIERS ENTRANTS. Jusqu'ici ce cliquet n'ouvrait que les trois
// ci-dessus : `README.md` (la porte d'entrée du dépôt) et `ARCHITECTURE.md` (que
// TEMPLATE.md désigne comme « faisant foi sur le fonctionnement du code ») lui
// étaient invisibles. La dérive s'y est donc déplacée après la 32.6 — mesuré le
// 2026-07-29 : 4 versions de plugin fausses, dont deux dans ARCHITECTURE.md qui se
// contredisait ainsi lui-même à 27 lignes d'écart, plus « les 17 phases sont
// soldées » dans un README qui en ignorait 26.
// 🚨 Le cliquet n'avait pas échoué : il avait été bâti AUTOUR de ce qui avait déjà
// mordu. Élargir le corpus élargit aussi R6 (références citées), et c'est voulu.
$aod_dd_rm   = is_readable( $aod_dd_repo . '/README.md' ) ? (string) file_get_contents( $aod_dd_repo . '/README.md' ) : '';
$aod_dd_arch = is_readable( $aod_dd_repo . '/docs/ARCHITECTURE.md' ) ? (string) file_get_contents( $aod_dd_repo . '/docs/ARCHITECTURE.md' ) : '';

/* --- Parse de la table §C.3 : étape → livrée ? ------------------------- */

$aod_dd_lignes = array(); // ex. '32.4' => '✅' | '⏳' | '❌'
// 🚨 PAS `/\R/` : sans le modificateur /u, \R matche aussi l'octet NEL (0x85)
// — qui est le DERNIER octet UTF-8 de « ✅ ». Le parseur coupait chaque ✅ en
// deux et ne « voyait » plus que 2 étapes livrées sur 110. Trouvé en éprouvant
// ce cliquet sur son propre corpus : même un garde-fou naît avec ses défauts.
foreach ( preg_split( '/\r\n|\n|\r/', $aod_dd_road ) as $aod_dd_l ) {
	if ( ! preg_match( '/^\|\s*~{0,2}(\d+\.\d+(?:bis|[a-z])?)~{0,2}\s*\|/', $aod_dd_l, $m ) ) {
		continue;
	}
	$aod_dd_c = explode( '|', $aod_dd_l );
	if ( count( $aod_dd_c ) < 5 ) {
		continue;
	}
	$aod_dd_s = $aod_dd_c[4];
	foreach ( array( '✅', '⏳', '❌' ) as $aod_dd_e ) {
		if ( false !== strpos( $aod_dd_s, $aod_dd_e ) ) {
			$aod_dd_lignes[ $m[1] ] = $aod_dd_e;
			break;
		}
	}
}

AOD_Test::group( 'Dérive doc 32.6 — R1 : la table qui « fait foi » est bien parsée' );
AOD_Test::ok(
	count( $aod_dd_lignes ) >= 40,
	sprintf( 'TÉMOIN : %d étapes lues dans la table §C.3', count( $aod_dd_lignes ) ),
	'la table a changé de forme : ce cliquet ne recoupe plus rien — l’adapter, pas le supprimer'
);
AOD_Test::ok(
	false !== strpos( $aod_dd_tpl, 'La table §C.3 de la ROADMAP fait foi' ),
	'TEMPLATE.md désigne toujours §C.3 comme faisant foi (sinon ce cliquet garde le mauvais document)'
);

/* =========================================================================
 * R2 — Un en-tête « ### N.M … » et sa ligne §C.3 disent LA MÊME CHOSE
 *
 * 🚨 C'est la dérive historique exacte : le détail (§B) disait « livré », la
 * table restait muette — ou l'inverse. Les pressés ne lisent que l'un des deux.
 * ========================================================================= */

AOD_Test::group( 'Dérive doc 32.6 — R2 : en-têtes ⇄ table, aucun désaccord' );

// 🚨 `(?:Étape )?` — ET CE N'EST PAS UN DÉTAIL DE MOTIF. Sans ce préfixe
// facultatif, R2 était AVEUGLE À TOUTE LA PHASE 47 : ses en-têtes s'écrivent
// « ### Étape 47.1 — … », aucun n'était vu. Mesuré en 47.9 : quatre étapes
// LIVRÉES (47.4 → 47.7) avaient un en-tête sans ✅ face à une ligne §C.3 à ✅ —
// c'est-à-dire EXACTEMENT le désaccord que cette règle existe pour attraper,
// installé sous son nez pendant quatre étapes.
preg_match_all( '/^### (?:Étape )?(\d+\.\d+(?:bis|[a-z])?) — [^\n]*/m', $aod_dd_road, $aod_dd_h, PREG_SET_ORDER );

// 🚨 ET LE TÉMOIN ÉTAIT COMPLICE. « ≥ 10 en-têtes » passait en comptant ceux des
// phases ANTÉRIEURES : il attestait d'un balayage sans jamais vérifier CE QU'IL
// BALAYAIT. Un témoin qui compte sans regarder laisse un angle mort de la taille
// d'une phase. On exige désormais de voir la phase la PLUS RÉCENTE de la table —
// celle qu'on vient d'écrire, donc celle qui risque de diverger.
$aod_dd_vus = array();
foreach ( $aod_dd_h as $aod_dd_x ) {
	$aod_dd_vus[] = (int) strtok( $aod_dd_x[1], '.' );
}
$aod_dd_derniere = 0;
foreach ( array_keys( $aod_dd_lignes ) as $aod_dd_num ) {
	$aod_dd_derniere = max( $aod_dd_derniere, (int) strtok( (string) $aod_dd_num, '.' ) );
}
AOD_Test::ok(
	count( $aod_dd_h ) >= 10 && in_array( $aod_dd_derniere, $aod_dd_vus, true ),
	sprintf(
		'TÉMOIN : %d en-têtes d’étape lus, dont ceux de la phase %d (la plus récente de la table)',
		count( $aod_dd_h ),
		$aod_dd_derniere
	),
	'un témoin qui compte sans vérifier CE QU’IL compte peut manquer une phase entière'
);

/**
 * 🚨 PLANCHER HISTORIQUE ASSUMÉ — patron de `test-phpcs-ignore.php` R3.
 *
 * Élargir le motif ci-dessus (47.9) a révélé **72** désaccords, pas quatre : les
 * phases **1 à 23** écrivent leurs en-têtes « ### Étape N.M — … » et n'ont
 * JAMAIS porté le marqueur ✅, par convention d'époque. Leur ligne §C.3, elle,
 * est bien à ✅.
 *
 * Exiger l'accord sur ces 68 en-têtes imposerait un diff mécanique de 68 lignes
 * de DOCUMENTATION, sur des phases soldées depuis des mois, pour zéro
 * information nouvelle — la queue qui remue le chien, exactement ce que R3 de
 * `test-phpcs-ignore.php` refuse déjà avec sa raison.
 *
 * On fige donc le seuil au lieu de le nier : **la règle est PLEINE au-delà de la
 * phase 23**, là où la convention actuelle s'applique et où le risque de dérive
 * est réel (une étape qu'on vient d'écrire). Abaisser ce seuil est un progrès
 * libre ; le remonter, non.
 *
 * ⚠️ Ce plancher ne protège rien de la Phase 47 : ses quatre désaccords
 * (47.4 → 47.7, livrées avec un en-tête sans ✅) ont été CORRIGÉS, pas exemptés.
 */
$aod_dd_phase_pleine = 23;

$aod_dd_desacc = array();
foreach ( $aod_dd_h as $aod_dd_e ) {
	$aod_dd_num = $aod_dd_e[1];
	if ( ! isset( $aod_dd_lignes[ $aod_dd_num ] ) ) {
		continue; // Étape sans ligne de table : hors périmètre de R2.
	}
	if ( (int) strtok( (string) $aod_dd_num, '.' ) <= $aod_dd_phase_pleine ) {
		continue; // Convention d'époque : cf. le plancher documenté ci-dessus.
	}
	$aod_dd_hl = false !== strpos( $aod_dd_e[0], '✅' );
	$aod_dd_tl = ( '✅' === $aod_dd_lignes[ $aod_dd_num ] );
	if ( $aod_dd_hl !== $aod_dd_tl ) {
		$aod_dd_desacc[] = $aod_dd_num . ' (en-tête : ' . ( $aod_dd_hl ? '✅' : 'sans ✅' ) . ', table : ' . $aod_dd_lignes[ $aod_dd_num ] . ')';
	}
}
AOD_Test::ok(
	empty( $aod_dd_desacc ),
	'🔴 Chaque en-tête d’étape est d’accord avec sa ligne de table',
	implode( ' · ', $aod_dd_desacc ) . ' — c’est la signature exacte de la dérive « §C.3 arrêtée à 30.1 »'
);

/* =========================================================================
 * R3 — Le compteur de la Phase 32 dans TEMPLATE.md est RECALCULÉ
 * ========================================================================= */

AOD_Test::group( 'Dérive doc 32.6 — R3 : le compteur « x/6 » de la Phase 32 ne peut plus mentir' );

$aod_dd_32ok = 0;
for ( $aod_dd_i = 1; $aod_dd_i <= 6; $aod_dd_i++ ) {
	if ( isset( $aod_dd_lignes[ '32.' . $aod_dd_i ] ) && '✅' === $aod_dd_lignes[ '32.' . $aod_dd_i ] ) {
		$aod_dd_32ok++;
	}
}

if ( preg_match( '/Phase 32 EN COURS \((\d)\/6/u', $aod_dd_tpl, $m ) ) {
	AOD_Test::ok(
		(int) $m[1] === $aod_dd_32ok,
		sprintf( '🔴 TEMPLATE.md annonce %d/6, la table §C.3 en compte %d', (int) $m[1], $aod_dd_32ok ),
		'la ligne d’état de TEMPLATE.md s’est déjà trompée deux fois — c’est la seule que lisent les pressés'
	);
} elseif ( preg_match( '/Phase 32 SOLDÉE/u', $aod_dd_tpl ) ) {
	AOD_Test::ok(
		6 === $aod_dd_32ok,
		sprintf( '🔴 TEMPLATE.md dit « Phase 32 SOLDÉE » — la table doit compter 6/6 (compté : %d)', $aod_dd_32ok )
	);
} else {
	AOD_Test::ok(
		false,
		'La phrase d’état de la Phase 32 a disparu de TEMPLATE.md',
		'reformulée ? adapter ce cliquet À LA MÊME OCCASION — un compteur non gardé recommence à mentir'
	);
}

/* =========================================================================
 * R4 — « N sections extraites sur 6 » (Phase 30) est RECALCULÉ
 * ========================================================================= */

AOD_Test::group( 'Dérive doc 32.6 — R4 : l’avancement de la décomposition ne peut plus mentir' );

$aod_dd_30ok = 0;
for ( $aod_dd_i = 1; $aod_dd_i <= 6; $aod_dd_i++ ) {
	if ( isset( $aod_dd_lignes[ '30.' . $aod_dd_i ] ) && '✅' === $aod_dd_lignes[ '30.' . $aod_dd_i ] ) {
		$aod_dd_30ok++;
	}
}
if ( preg_match( '/(\d) sections? extraites? sur 6/u', $aod_dd_tpl, $m ) ) {
	AOD_Test::ok(
		(int) $m[1] === $aod_dd_30ok,
		sprintf( '🔴 TEMPLATE.md annonce %d section(s) extraite(s), la table en compte %d', (int) $m[1], $aod_dd_30ok )
	);
} else {
	AOD_Test::ok(
		6 === $aod_dd_30ok,
		'La phrase « N sections extraites sur 6 » a disparu : admissible seulement chantier soldé (6/6)',
		sprintf( 'compté : %d/6 — un chantier en cours sans phrase d’avancement redevient invisible', $aod_dd_30ok )
	);
}

/* =========================================================================
 * R5 — « Les Phases 1 à N sont soldées » ⇒ rien d'ouvert en dessous
 * ========================================================================= */

AOD_Test::group( 'Dérive doc 32.6 — R5 : la ligne d’état des phases soldées' );

if ( preg_match( '/Les Phases 1 à (\d+) sont soldées/u', $aod_dd_tpl, $m ) ) {
	$aod_dd_borne  = (int) $m[1];
	$aod_dd_restes = array();
	foreach ( $aod_dd_lignes as $aod_dd_num => $aod_dd_st ) {
		$aod_dd_ph = (int) $aod_dd_num;
		if ( $aod_dd_ph >= 1 && $aod_dd_ph <= $aod_dd_borne && '⏳' === $aod_dd_st ) {
			$aod_dd_restes[] = $aod_dd_num;
		}
	}
	AOD_Test::ok(
		empty( $aod_dd_restes ),
		sprintf( '🔴 « Phases 1 à %d soldées » : aucune étape ⏳ en dessous', $aod_dd_borne ),
		implode( ', ', $aod_dd_restes ) . ' — la ligne d’état contredit la table qu’elle résume'
	);
} else {
	AOD_Test::ok( false, 'La ligne « Les Phases 1 à N sont soldées » a disparu de TEMPLATE.md', 'reformulée ? adapter ce cliquet à la même occasion' );
}

/* =========================================================================
 * R6 — Les RÉFÉRENCES citées existent : cliquets et portes de recette
 *
 * 🚨 Un doc qui envoie vers `test-foo.php` supprimé, ou `run.sh porte-morte`,
 * fait perdre confiance dans TOUTES ses références — et personne ne le voit.
 * ========================================================================= */

AOD_Test::group( 'Dérive doc 32.6 — R6 : toute référence citée existe' );

$aod_dd_corpus = $aod_dd_road . "\n" . $aod_dd_tpl . "\n" . $aod_dd_cl . "\n" . $aod_dd_rm . "\n" . $aod_dd_arch;

// Cliquets cités → le fichier existe.
preg_match_all( '/test-[a-z0-9-]+\.php/', $aod_dd_corpus, $aod_dd_t );
$aod_dd_tests = array_unique( $aod_dd_t[0] );
$aod_dd_morts = array();
foreach ( $aod_dd_tests as $aod_dd_f ) {
	if ( ! is_readable( $aod_dd_repo . '/tests/unit/' . $aod_dd_f ) ) {
		$aod_dd_morts[] = $aod_dd_f;
	}
}
AOD_Test::ok(
	count( $aod_dd_tests ) >= 15,
	sprintf( 'TÉMOIN : %d cliquets distincts cités dans les docs', count( $aod_dd_tests ) )
);
AOD_Test::ok(
	empty( $aod_dd_morts ),
	'🔴 Chaque cliquet cité existe dans tests/unit/',
	implode( ', ', $aod_dd_morts ) . ' — référence morte : renommage ou suppression non répercuté'
);

// Portes de recette citées → déclarées dans SCENARIOS de run.sh.
$aod_dd_run = is_readable( $aod_dd_repo . '/tools/recette/run.sh' ) ? (string) file_get_contents( $aod_dd_repo . '/tools/recette/run.sh' ) : '';
AOD_Test::ok( '' !== $aod_dd_run && preg_match( '/^SCENARIOS="([^"]+)"/m', $aod_dd_run, $aod_dd_sc ) === 1, 'TÉMOIN : la liste SCENARIOS de run.sh est lisible' );

if ( ! empty( $aod_dd_sc[1] ) ) {
	$aod_dd_portes = explode( ' ', trim( $aod_dd_sc[1] ) );
	// Exemptions déclarées AVEC leur raison — une exemption sans raison est un échec.
	$aod_dd_exempt = array(
		'contrast' => 'exemple CANONIQUE de la faute de frappe que la story 17.0 raconte (la garde d’argument de run.sh) — ce n’est pas une porte, c’est la leçon',
	);
	preg_match_all( '/run\.sh ([a-z]+)/', $aod_dd_corpus, $aod_dd_g );
	$aod_dd_citees = array_diff( array_unique( $aod_dd_g[1] ), array_keys( $aod_dd_exempt ) );
	$aod_dd_inconnues = array_diff( $aod_dd_citees, $aod_dd_portes );
	AOD_Test::ok(
		count( $aod_dd_citees ) >= 8,
		sprintf( 'TÉMOIN : %d portes distinctes citées', count( $aod_dd_citees ) )
	);
	AOD_Test::ok(
		empty( $aod_dd_inconnues ),
		'🔴 Chaque porte `run.sh <x>` citée est déclarée dans SCENARIOS',
		implode( ', ', $aod_dd_inconnues ) . ' — une porte citée qui n’existe pas envoie le lecteur dans le mur'
	);
}

/* =========================================================================
 * R7 — Toute étape COMMITÉE a sa ligne en §C.3 (dette D30)
 *
 * La liste des sujets de commit est produite par `tests/run.sh` CÔTÉ HÔTE : le
 * conteneur de tests n'a pas `git` (vérifié), et `.git/logs/HEAD` est un reflog,
 * vide sur un clone frais. Chacun fait ce qu'il sait faire.
 * ========================================================================= */

AOD_Test::group( 'Dérive doc D30 — R7 : la table est confrontée au DÉPÔT, pas seulement aux autres documents' );

$aod_dd_sujets_f = dirname( __DIR__ ) . '/.git-sujets.txt';
$aod_dd_sujets   = is_readable( $aod_dd_sujets_f ) ? (string) file_get_contents( $aod_dd_sujets_f ) : '';

AOD_Test::ok(
	'' !== $aod_dd_sujets,
	'Les sujets de commit sont disponibles',
	'fichier tests/.git-sujets.txt absent — lancer la suite par `bash tests/run.sh`, qui le produit'
);

if ( '' !== $aod_dd_sujets ) {
	if ( false !== strpos( $aod_dd_sujets, '#NO_GIT' ) ) {
		AOD_Test::ok(
			false,
			'R7 n’a RIEN pu vérifier : git est indisponible sur l’hôte',
			'ce dépôt se clone par git (TEMPLATE.md §8) ; sans lui, la table ne peut plus être confrontée au code — on le dit fort plutôt que de rendre un vert vide'
		);
	} else {
		/*
		 * 🔴 CE BLOC A ÉTÉ ROUGE EN PERMANENCE, ET LE DIAGNOSTIC ÉCRIT DANS SON
		 * PROPRE MESSAGE D'ERREUR ÉTAIT FAUX (corrigé le 2026-08-07).
		 *
		 * Il disait « l'extraction ne reconnaît plus le format des messages —
		 * l'adapter, pas la supprimer », et il invitait donc à réécrire une regex.
		 * Mesuré : ce clone compte **13 commits**, dont **zéro** au format
		 * `type(portée)` et **zéro** citant un numéro d'étape, sous quelque forme
		 * que ce soit. Il n'y a rien à extraire. Le dépôt a été RÉINITIALISÉ (son
		 * premier commit s'appelle « Commit initial du template Full ») : les 48
		 * phases dont §C.3 tient la table ont été développées dans une autre
		 * histoire, qui n'a pas été reportée ici.
		 *
		 * 🚨 R7 CONNAISSAIT DEUX ÉTATS — « git absent » et « git présent » — ET IL
		 * EN EXISTE UN TROISIÈME : git présent, mais une histoire qui ne consigne
		 * pas les étapes. Le confondre avec le second faisait accuser l'outil de
		 * mesure d'un problème qui n'existait pas, tous les jours, dans un rapport
		 * qu'on finit par ne plus lire — exactement la panne décrite par D16.
		 * *Un cliquet doit distinguer « je n'ai rien trouvé » de « je ne sais plus
		 * chercher » ; sans cette distinction, il envoie corriger ce qui marche.*
		 *
		 * ⚠️ L'exemption n'est PAS un vert silencieux : elle est nommée, chiffrée,
		 * et elle porte sa propre assertion. Et elle ne peut pas absorber la vraie
		 * panne — dès qu'UN sujet porte un numéro d'étape, on repasse dans le
		 * chemin nominal, où le témoin ci-dessous exige que l'extraction en tire
		 * quelque chose.
		 */
		$aod_dd_lignes_git = array();
		foreach ( preg_split( '/\r\n|\n|\r/', $aod_dd_sujets ) as $aod_dd_sujet ) {
			if ( '' !== trim( $aod_dd_sujet ) && '#' !== substr( ltrim( $aod_dd_sujet ), 0, 1 ) ) {
				$aod_dd_lignes_git[] = $aod_dd_sujet;
			}
		}

		/*
		 * Les sujets qui PARLENT d'une étape, quel que soit leur format. C'est le
		 * corpus dont l'extraction a la charge — et le seul contre lequel il soit
		 * honnête de la juger.
		 * 🔁 L'ancienne version n'acceptait que `type(portée)`, donc un sujet
		 * « /gestion 47.2 : … » lui échappait en silence. La reconnaissance porte
		 * désormais sur le numéro lui-même, où qu'il se trouve dans la ligne.
		 */
		$aod_dd_porteurs = array();
		foreach ( $aod_dd_lignes_git as $aod_dd_sujet ) {
			if ( preg_match( '/\b\d+\.\d+(?:bis|[a-z])?\b/', $aod_dd_sujet ) ) {
				$aod_dd_porteurs[] = $aod_dd_sujet;
			}
		}

		$aod_dd_etapes = array();
		foreach ( $aod_dd_porteurs as $aod_dd_sujet ) {
			$aod_dd_cible = preg_match( '/^[a-z]+\(([^)]*)\)/', $aod_dd_sujet, $m ) ? $m[1] : $aod_dd_sujet;
			if ( preg_match_all( '/\b\d+\.\d+(?:bis|[a-z])?\b/', $aod_dd_cible, $mm ) ) {
				foreach ( $mm[0] as $aod_dd_e ) {
					$aod_dd_etapes[ $aod_dd_e ] = true;
				}
			}
		}

		if ( empty( $aod_dd_porteurs ) ) {
			/*
			 * ⚠️ `AOD_Test::ok()` ET SURTOUT PAS `return`. Une première écriture
			 * sortait du fichier ici — et ce fichier ne contient pas que R7 : R8
			 * (« une version annoncée dans un TITRE égale l'en-tête du plugin »)
			 * vit une centaine de lignes plus bas. Sortir aurait donc désarmé une
			 * règle entière, en silence, sur tout clone au dépôt réinitialisé.
			 * 🚨 *Dans un fichier de tests chargé par `include`, `return` n'est pas
			 * un raccourci de lecture : c'est la suppression de tout ce qui suit.*
			 */
			AOD_Test::ok(
				! empty( $aod_dd_lignes_git ),
				sprintf(
					'R7 SANS OBJET sur ce clone : %d commit(s) lus, AUCUN ne cite de numéro d’étape — l’histoire ne consigne pas les phases (dépôt réinitialisé)',
					count( $aod_dd_lignes_git )
				),
				'même pas un sujet de commit lisible : ce n’est plus une histoire réinitialisée, c’est une extraction cassée'
			);
		} else {
			AOD_Test::ok(
				count( $aod_dd_etapes ) > 0,
				sprintf(
					'TÉMOIN : %d étape(s) distincte(s) lue(s) dans %d sujet(s) qui en citent',
					count( $aod_dd_etapes ),
					count( $aod_dd_porteurs )
				),
				'des sujets citent un numéro d’étape et l’extraction n’en tire RIEN : c’est elle qu’il faut adapter, pas supprimer'
			);

			$aod_dd_orphelines = array();
			foreach ( array_keys( $aod_dd_etapes ) as $aod_dd_e ) {
				if ( ! isset( $aod_dd_lignes[ $aod_dd_e ] ) ) {
					$aod_dd_orphelines[] = $aod_dd_e;
				}
			}
			sort( $aod_dd_orphelines );
			AOD_Test::ok(
				empty( $aod_dd_orphelines ),
				'🔴 Chaque étape livrée en git a une ligne en §C.3',
				implode( ', ', $aod_dd_orphelines ) . ' — livrée(s) mais absente(s) de la table qui « fait foi » : c’est exactement le trou par lequel la 42.7 est passée'
			);
		}
	}
}

/* =========================================================================
 * R8 — Une version annoncée dans un TITRE égale l'en-tête du plugin (44.3)
 *
 * 🚨 LE PÉRIMÈTRE VIENT D'UNE MESURE, PAS D'UNE INTUITION. La règle évidente —
 * « toute version sur une ligne citant un slug de plugin doit être à jour » — a
 * été relevée sur les 780 Ko de docs AVANT d'être écartée : elle produirait des
 * dizaines de faux positifs, car la ROADMAP est pleine de mentions HISTORIQUES
 * (« Livrée le 2026-07-13 (`aod-cod-form` v1.25.0) », ~40 occurrences en §C.3) et
 * de CIBLES de phase (« Versions cibles : … v1.30.0+ », ~15 en Partie B). Elles
 * sont JUSTES et doivent le rester.
 *
 * L'invariant qui sépare le présent du passé : une version portée par un TITRE
 * markdown est une affirmation sur ce que la section décrit MAINTENANT. Les
 * mentions historiques vivent en prose et en cellules de tableau, jamais en titre.
 * Relevé de contrôle au 2026-07-29 : cette restriction attrape EXACTEMENT les 4
 * énoncés faux et zéro mention légitime.
 *
 * Un cliquet qui crie au loup est un cliquet qu'on désarme (leçon D16) : le
 * périmètre étroit n'est pas de la timidité, c'est ce qui le rend utilisable.
 * ========================================================================= */

AOD_Test::group( 'Dérive doc 44.3 — R8 : une version dans un TITRE ne peut plus mentir' );

$aod_dd_plugins = array(
	'aod-cod-form'         => AOD_COD_VERSION,
	'aod-client-dashboard' => AOD_CD_VERSION,
);

// Les constantes viennent des plugins CHARGÉS, donc de l'en-tête réel : on ne
// reparse pas le fichier, on lit ce que WordPress a effectivement pris.
AOD_Test::ok(
	preg_match( '/^\d+\.\d+\.\d+$/', (string) AOD_COD_VERSION ) && preg_match( '/^\d+\.\d+\.\d+$/', (string) AOD_CD_VERSION ),
	sprintf( 'TÉMOIN : versions lues sur les plugins chargés — aod-cod-form %s · aod-client-dashboard %s', AOD_COD_VERSION, AOD_CD_VERSION ),
	'les constantes de version ont changé de forme : R8 ne compare plus rien'
);

$aod_dd_r8_docs = array(
	'README.md'            => $aod_dd_rm,
	'TEMPLATE.md'          => $aod_dd_tpl,
	'CLAUDE.md'            => $aod_dd_cl,
	'docs/ARCHITECTURE.md' => $aod_dd_arch,
	'docs/ROADMAP-AMELIORATIONS.md' => $aod_dd_road,
);

$aod_dd_r8_vus     = 0;      // Titres versionnés RÉELLEMENT inspectés (règle du témoin).
$aod_dd_r8_fautifs = array();

foreach ( $aod_dd_r8_docs as $aod_dd_r8_nom => $aod_dd_r8_txt ) {
	if ( '' === $aod_dd_r8_txt ) {
		continue;
	}
	foreach ( preg_split( '/\r\n|\n|\r/', $aod_dd_r8_txt ) as $aod_dd_r8_l ) {
		// Un TITRE markdown, ou la ligne déclarative « Versions analysées : » —
		// les deux seules formes qui affirment un état PRÉSENT.
		$aod_dd_r8_titre = ( 1 === preg_match( '/^#{1,6}\s/', $aod_dd_r8_l ) );
		$aod_dd_r8_decl  = ( false !== strpos( $aod_dd_r8_l, 'Versions analysées' ) );
		if ( ! $aod_dd_r8_titre && ! $aod_dd_r8_decl ) {
			continue;
		}
		// 🚨 UNE VERSION APPARTIENT AU SLUG QUI LA PRÉCÈDE, pas à la ligne.
		// Première écriture de R8 : pour chaque slug trouvé sur la ligne, elle
		// comparait TOUTES les versions de la ligne. Sur la déclaration
		// « Versions analysées : `aod-cod-form` v1.52.0 · `aod-client-dashboard`
		// v0.75.0 » — ligne pourtant JUSTE — elle produisait donc deux faux
		// positifs croisés. Attrapé par le cliquet lui-même, à son premier run.
		// On découpe la ligne en segments : chaque slug possède le texte qui va de
		// lui jusqu'au slug suivant.
		// ⚠️ Conséquence assumée : une version écrite AVANT son slug
		// (« ## v1.52.0 — aod-cod-form ») n'est pas vue. Aucune occurrence dans le
		// corpus, et l'inverse — rattacher une version au slug qui la suit —
		// recréerait l'ambiguïté qu'on vient de fermer.
		$aod_dd_r8_pos = array();
		foreach ( $aod_dd_plugins as $aod_dd_r8_slug => $aod_dd_r8_ver ) {
			$aod_dd_r8_off = 0;
			while ( false !== ( $aod_dd_r8_at = strpos( $aod_dd_r8_l, $aod_dd_r8_slug, $aod_dd_r8_off ) ) ) {
				$aod_dd_r8_pos[] = array( $aod_dd_r8_at, $aod_dd_r8_slug, $aod_dd_r8_ver );
				$aod_dd_r8_off   = $aod_dd_r8_at + 1;
			}
		}
		if ( ! $aod_dd_r8_pos ) {
			continue;
		}
		usort( $aod_dd_r8_pos, function ( $a, $b ) {
			return $a[0] - $b[0];
		} );

		foreach ( $aod_dd_r8_pos as $aod_dd_r8_i => $aod_dd_r8_cur ) {
			$aod_dd_r8_fin = isset( $aod_dd_r8_pos[ $aod_dd_r8_i + 1 ] )
				? $aod_dd_r8_pos[ $aod_dd_r8_i + 1 ][0]
				: strlen( $aod_dd_r8_l );
			$aod_dd_r8_seg = substr( $aod_dd_r8_l, $aod_dd_r8_cur[0], $aod_dd_r8_fin - $aod_dd_r8_cur[0] );
			if ( ! preg_match_all( '/v(\d+\.\d+\.\d+)/', $aod_dd_r8_seg, $aod_dd_r8_m ) ) {
				continue;
			}
			foreach ( $aod_dd_r8_m[1] as $aod_dd_r8_lu ) {
				$aod_dd_r8_vus++;
				if ( $aod_dd_r8_lu !== $aod_dd_r8_cur[2] ) {
					$aod_dd_r8_fautifs[] = sprintf(
						'%s → « %s » annoncé v%s, en-tête v%s',
						$aod_dd_r8_nom,
						$aod_dd_r8_cur[1],
						$aod_dd_r8_lu,
						$aod_dd_r8_cur[2]
					);
				}
			}
		}
	}
}

// Règle du témoin (§C.2) : le nombre inspecté est LUI-MÊME une assertion. Sans
// elle, une regex devenue muette rendrait un vert identique à celui d'un audit
// complet — le mode d'échec exact que ce dépôt a déjà rencontré six fois.
AOD_Test::ok(
	$aod_dd_r8_vus >= 3,
	sprintf( 'TÉMOIN : %d version(s) de plugin inspectée(s) dans des titres', $aod_dd_r8_vus ),
	'moins de 3 : le motif ne reconnaît plus les titres versionnés — l’adapter, pas le supprimer'
);
AOD_Test::ok(
	empty( $aod_dd_r8_fautifs ),
	'🔴 Toute version annoncée dans un titre égale l’en-tête du plugin',
	implode( ' · ', $aod_dd_r8_fautifs ) . ' — un lecteur qui saute à la section qui l’intéresse lit ce titre, pas l’en-tête du fichier'
);
