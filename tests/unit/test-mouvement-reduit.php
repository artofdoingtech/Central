<?php
/**
 * `prefers-reduced-motion` ne peut plus se défaire en silence (2026-08-05).
 *
 * 🚨 CE FICHIER NAÎT DE TROIS DÉFAUTS TROUVÉS LE MÊME JOUR, tous du même patron :
 * un mouvement ajouté à un composant, et sa neutralisation posée sur les voisins
 * mais pas sur lui.
 *
 *   · `.aod-cd-carriersel-menu` (47.1) recevait `aod-cd-pop` — un glissement de
 *     8 px — et n'était que le CINQUIÈME des six sélecteurs dans la liste qui
 *     l'annule.
 *   · `.aod-cd-card:hover` se soulevait de 3 px sans neutralisation du tout.
 *   · `.aod-cd-todo-tile:hover .aod-cd-todo-tile__go` ÉTAIT neutralisé, mais sa
 *     surcharge RTL pèse (0,4,1) contre (0,3,0) : le chevron continuait de glisser
 *     **en arabe uniquement**.
 *
 * 🚨 POURQUOI AUCUNE PORTE NE POUVAIT LES VOIR. La recette `run.sh mouvement`
 * mesure le rendu sous `prefers-reduced-motion` — mais un audit de rendu ne peut
 * constater QUE ce qu'il pense à regarder : le bon widget, dans la bonne langue,
 * dans le bon état. Les trois défauts vivent dans un menu qu'il faut ouvrir, une
 * carte d'une autre section, et une langue sur deux. Ils se LISENT en revanche
 * tous les trois dans le fichier, en comparant deux listes.
 *
 * 🚨 ET LE FICHIER SE PROTÉGEAIT DÉJÀ — PAR ÉCRIT. Le bloc de fin de
 * `dashboard.css` porte, depuis la Phase 16, un avertissement en toutes lettres :
 * « ses sélecteurs doivent être AUSSI SPÉCIFIQUES que ceux qu'ils annulent », et
 * « jamais d'animation non désactivable posée après coup ». Les trois défauts ont
 * été commis SOUS ce paragraphe. *Une règle écrite au-dessus d'une liste ne
 * protège pas la ligne qu'on ajoutera demain ; seul un cliquet le fait.* C'est
 * mot pour mot la leçon de la Phase 46, appliquée ici à l'accessibilité.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Découpe une feuille en règles (sélecteur, corps), commentaires retirés.
 *
 * ⚠️ Préfixe `aod_test_mr_` : `tests/all.php` charge tous les fichiers par glob()
 * dans le même espace global — un helper au nom générique provoque un
 * `Cannot redeclare` (déjà arrivé, cf. test-charte-sidebar.php).
 *
 * @param string $css Feuille.
 * @return array<int,array{0:string,1:string}>
 */
function aod_test_mr_regles( $css ) {
	$css   = (string) preg_replace( '#/\*.*?\*/#s', '', (string) $css );
	$out   = array();
	$reste = $css;
	while ( preg_match( '/([^{}]+)\{([^{}]*)\}/', $reste, $m, PREG_OFFSET_CAPTURE ) ) {
		$sel = trim( preg_replace( '/\s+/', ' ', $m[1][0] ) );
		if ( '' !== $sel && '@' !== $sel[0] ) {
			$out[] = array( $sel, $m[2][0] );
		}
		$reste = substr( $reste, $m[0][1] + strlen( $m[0][0] ) );
	}
	return $out;
}

/**
 * Les règles vivant DANS un bloc `@media(prefers-reduced-motion:reduce)`.
 *
 * On isole les blocs par comptage d'accolades plutôt qu'avec une expression
 * régulière : le bloc contient lui-même des règles, donc des accolades.
 *
 * @param string $css Feuille.
 * @return array<int,array{0:string,1:string}>
 */
function aod_test_mr_blocs_reduits( $css ) {
	$out = array();
	$pos = 0;
	while ( false !== ( $i = strpos( $css, 'prefers-reduced-motion', $pos ) ) ) {
		$ouvre = strpos( $css, '{', $i );
		if ( false === $ouvre ) {
			break;
		}
		$prof = 0;
		$fin  = $ouvre;
		for ( $j = $ouvre, $n = strlen( $css ); $j < $n; $j++ ) {
			if ( '{' === $css[ $j ] ) {
				++$prof;
			} elseif ( '}' === $css[ $j ] ) {
				--$prof;
				if ( 0 === $prof ) {
					$fin = $j;
					break;
				}
			}
		}
		$out = array_merge( $out, aod_test_mr_regles( substr( $css, $ouvre + 1, $fin - $ouvre - 1 ) ) );
		$pos = $fin + 1;
	}
	return $out;
}

/** Sélecteurs individuels d'une règle groupée, normalisés. */
function aod_test_mr_sels( $selecteur ) {
	return array_values(
		array_filter(
			array_map( 'trim', explode( ',', $selecteur ) ),
			static function ( $s ) {
				return '' !== $s;
			}
		)
	);
}

$aod_mr_css     = (string) file_get_contents( AOD_CD_PATH . 'assets/css/dashboard.css' );
$aod_mr_reduits = aod_test_mr_blocs_reduits( $aod_mr_css );
$aod_mr_toutes  = aod_test_mr_regles( $aod_mr_css );

/*
 * Les règles HORS mouvement réduit : toutes celles qui ne sont pas dans un bloc.
 * On compare par (sélecteur, corps) — deux règles peuvent porter le même
 * sélecteur dedans et dehors, c'est même exactement le principe.
 */
$aod_mr_dedans = array();
foreach ( $aod_mr_reduits as $aod_mr_r ) {
	$aod_mr_dedans[] = $aod_mr_r[0] . '|' . $aod_mr_r[1];
}
$aod_mr_dehors = array();
foreach ( $aod_mr_toutes as $aod_mr_r ) {
	if ( ! in_array( $aod_mr_r[0] . '|' . $aod_mr_r[1], $aod_mr_dedans, true ) ) {
		$aod_mr_dehors[] = $aod_mr_r;
	}
}

AOD_Test::group( 'Mouvement réduit — TÉMOINS (sans eux, tout serait vert par vacuité)' );

AOD_Test::ok(
	count( $aod_mr_toutes ) > 400,
	sprintf( 'TÉMOIN : %d règles CSS découpées', count( $aod_mr_toutes ) ),
	'le découpeur ne reconnaît plus la forme des règles — l’adapter, pas contourner'
);
AOD_Test::ok(
	count( $aod_mr_reduits ) > 10,
	sprintf( 'TÉMOIN : %d règles trouvées dans les blocs prefers-reduced-motion', count( $aod_mr_reduits ) ),
	'aucun bloc de mouvement réduit repéré : soit ils ont disparu, soit l’isolateur est cassé — et TOUS les contrôles ci-dessous deviendraient vrais par vacuité'
);

AOD_Test::group( 'Mouvement réduit — R1 : toute surface qui s’OUVRE en glissant sait s’en abstenir' );

/*
 * `aod-cd-pop` glisse de 8 px vers le bas. La neutralisation ne met pas
 * `animation:none` — ça supprimerait aussi le fondu, et la surface apparaîtrait
 * d'un coup, l'état même que la Phase 17 a corrigé — mais bascule sur
 * `aod-cd-fade`, qui ne fait que l'opacité. Les deux listes doivent donc être
 * RIGOUREUSEMENT les mêmes.
 */
$aod_mr_pop  = array();
$aod_mr_fade = array();
foreach ( $aod_mr_dehors as $aod_mr_r ) {
	if ( false !== strpos( $aod_mr_r[1], 'aod-cd-pop' ) ) {
		$aod_mr_pop = array_merge( $aod_mr_pop, aod_test_mr_sels( $aod_mr_r[0] ) );
	}
}
foreach ( $aod_mr_reduits as $aod_mr_r ) {
	if ( false !== strpos( $aod_mr_r[1], 'aod-cd-fade' ) ) {
		$aod_mr_fade = array_merge( $aod_mr_fade, aod_test_mr_sels( $aod_mr_r[0] ) );
	}
}
sort( $aod_mr_pop );
sort( $aod_mr_fade );

AOD_Test::ok(
	count( $aod_mr_pop ) >= 5,
	sprintf( 'TÉMOIN : %d sélecteur(s) reçoivent réellement `aod-cd-pop`', count( $aod_mr_pop ) ),
	'plus aucune surface ne reçoit l’animation d’ouverture : R1 serait vert sans rien garder'
);
AOD_Test::eq(
	implode( ' · ', $aod_mr_pop ),
	implode( ' · ', $aod_mr_fade ),
	sprintf( '🔴 R1 — les %d surfaces qui glissent à l’ouverture sont TOUTES neutralisées', count( $aod_mr_pop ) )
);

AOD_Test::group( 'Mouvement réduit — R2 : tout creux d’appui sait s’en abstenir' );

/*
 * Le creux de 2 % à l'appui (Phase 16). Même forme : la liste qui le pose et
 * celle qui l'annule sont le même ensemble, ou l'une des deux a été oubliée.
 */
$aod_mr_creux  = array();
$aod_mr_annule = array();
foreach ( $aod_mr_dehors as $aod_mr_r ) {
	if ( false !== strpos( $aod_mr_r[1], 'scale(.98)' ) ) {
		$aod_mr_creux = array_merge( $aod_mr_creux, aod_test_mr_sels( $aod_mr_r[0] ) );
	}
}
foreach ( $aod_mr_reduits as $aod_mr_r ) {
	/*
	 * DEUX formes de neutralisation valides, et il a fallu les deux — mesuré.
	 * `transform:none` convient à un déplacement. Il est un CONTRESENS sur un
	 * liseré qui pousse (`scaleY(0)` → `scaleY(1)`) : `none` vaut identité, donc
	 * le liseré resterait visible en permanence, au repos compris. On y coupe la
	 * TRANSITION — la pousse disparaît, l'apparition reste, instantanée. Refuser
	 * cette seconde forme rendrait le cliquet rouge sur une correction JUSTE,
	 * donc désactivé (leçon D16).
	 */
	if ( false !== strpos( $aod_mr_r[1], 'transform:none' ) || false !== strpos( $aod_mr_r[1], 'transition:none' ) ) {
		$aod_mr_annule = array_merge( $aod_mr_annule, aod_test_mr_sels( $aod_mr_r[0] ) );
	}
}
$aod_mr_creux_orphelins = array_values( array_diff( $aod_mr_creux, $aod_mr_annule ) );
sort( $aod_mr_creux_orphelins );

AOD_Test::ok(
	count( $aod_mr_creux ) >= 5,
	sprintf( 'TÉMOIN : %d contrôle(s) portent réellement le creux d’appui', count( $aod_mr_creux ) )
);
AOD_Test::eq(
	'',
	implode( ' · ', $aod_mr_creux_orphelins ),
	sprintf( '🔴 R2 — les %d contrôles qui se creusent à l’appui sont TOUS neutralisés', count( $aod_mr_creux ) )
);

AOD_Test::group( 'Mouvement réduit — R3 : tout déplacement au survol / à l’appui sait s’en abstenir' );

/*
 * 🚨 LA COMPARAISON PORTE SUR LE SÉLECTEUR AU CARACTÈRE PRÈS, et c'est le cœur du
 * contrôle. Le défaut le plus retors des trois n'était PAS une neutralisation
 * absente : `.aod-cd-todo-tile:hover .aod-cd-todo-tile__go` était bien annulé.
 * C'est sa surcharge `html[dir="rtl"] …` qui pesait plus lourd (0,4,1 contre
 * 0,3,0) et regagnait la cascade — donc un mouvement subsistant EN ARABE
 * SEULEMENT. Exiger le sélecteur identique est la façon la plus simple de
 * garantir une spécificité au moins égale, sans calculer de spécificité.
 *
 * ⚠️ CE QUE R3 NE COUVRE PAS, ET POURQUOI. Les transformations d'ÉTAT qui ne
 * dépendent ni de `:hover` ni de `:active` restent dehors — typiquement
 * `.aod-cd-csel.is-open .aod-cd-csel-caret{transform:rotate(225deg)}`. Pour
 * celui-là, `transform:none` serait un CONTRESENS : la rotation DESSINE le
 * chevron (un carré bordé de deux côtés), l'annuler afficherait un carré. Ce
 * qui est du mouvement, c'est sa TRANSITION — neutralisée à part, en fin de
 * `dashboard.css`. Un cliquet qui exigerait `transform:none` partout serait
 * rouge à tort, donc désactivé, donc inutile (leçon D16).
 */
$aod_mr_bougent = array();
foreach ( $aod_mr_dehors as $aod_mr_r ) {
	$aod_mr_sels = aod_test_mr_sels( $aod_mr_r[0] );
	if ( ! preg_match( '/transform\s*:\s*(?!none)[a-z]/i', $aod_mr_r[1] ) ) {
		continue;
	}
	foreach ( $aod_mr_sels as $aod_mr_s ) {
		if ( preg_match( '/:hover|:active/', $aod_mr_s ) ) {
			$aod_mr_bougent[ $aod_mr_s ] = true;
		}
	}
}
$aod_mr_bougent = array_keys( $aod_mr_bougent );
sort( $aod_mr_bougent );

$aod_mr_manquants = array_values( array_diff( $aod_mr_bougent, $aod_mr_annule ) );
sort( $aod_mr_manquants );

AOD_Test::ok(
	count( $aod_mr_bougent ) >= 8,
	sprintf( 'TÉMOIN : %d sélecteur(s) déplacent réellement quelque chose au survol ou à l’appui', count( $aod_mr_bougent ) ),
	'le détecteur ne voit plus aucun déplacement : R3 serait vert sans rien garder'
);
AOD_Test::eq(
	'',
	implode( ' · ', $aod_mr_manquants ),
	sprintf( '🔴 R3 — les %d déplacements d’état sont TOUS neutralisés, sélecteur identique compris', count( $aod_mr_bougent ) )
);

AOD_Test::group( 'Mouvement réduit — mise au ROUGE du détecteur (règle du témoin, pt 5)' );

/*
 * Sans ce témoin, un détecteur qui ne reconnaîtrait plus la forme des règles
 * rendrait trois listes vides — donc trois verts éclatants n'ayant rien mesuré.
 * On lui soumet une feuille jouet reproduisant EXACTEMENT le défaut du
 * 2026-08-05 : deux surfaces animées, une seule neutralisée.
 */
$aod_mr_jouet = '.a-menu:not([hidden]),.b-menu:not([hidden]){animation:aod-cd-pop .14s ease}'
	. '@media(prefers-reduced-motion:reduce){.a-menu:not([hidden]){animation:aod-cd-fade .14s ease}}';

$aod_mr_j_reduits = aod_test_mr_blocs_reduits( $aod_mr_jouet );
$aod_mr_j_dedans  = array();
foreach ( $aod_mr_j_reduits as $aod_mr_r ) {
	$aod_mr_j_dedans[] = $aod_mr_r[0] . '|' . $aod_mr_r[1];
}
$aod_mr_j_pop  = array();
$aod_mr_j_fade = array();
foreach ( aod_test_mr_regles( $aod_mr_jouet ) as $aod_mr_r ) {
	if ( in_array( $aod_mr_r[0] . '|' . $aod_mr_r[1], $aod_mr_j_dedans, true ) ) {
		continue;
	}
	if ( false !== strpos( $aod_mr_r[1], 'aod-cd-pop' ) ) {
		$aod_mr_j_pop = array_merge( $aod_mr_j_pop, aod_test_mr_sels( $aod_mr_r[0] ) );
	}
}
foreach ( $aod_mr_j_reduits as $aod_mr_r ) {
	if ( false !== strpos( $aod_mr_r[1], 'aod-cd-fade' ) ) {
		$aod_mr_j_fade = array_merge( $aod_mr_j_fade, aod_test_mr_sels( $aod_mr_r[0] ) );
	}
}
AOD_Test::eq(
	'.b-menu:not([hidden])',
	implode( ' · ', array_values( array_diff( $aod_mr_j_pop, $aod_mr_j_fade ) ) ),
	'TÉMOIN DE SENS : sur une feuille jouet, R1 désigne bien la surface animée SANS neutralisation'
);

/*
 * Et le témoin du cas RTL, celui qui a réellement échappé à tout le monde : une
 * neutralisation posée sur le sélecteur de base ne couvre pas sa surcharge.
 */
$aod_mr_jouet2 = '.t:hover .go{transform:translateX(2px)}html[dir="rtl"] .t:hover .go{transform:translateX(-2px)}'
	. '@media(prefers-reduced-motion:reduce){.t:hover .go{transform:none}}';
$aod_mr_j2_red = aod_test_mr_blocs_reduits( $aod_mr_jouet2 );
$aod_mr_j2_ann = array();
foreach ( $aod_mr_j2_red as $aod_mr_r ) {
	$aod_mr_j2_ann = array_merge( $aod_mr_j2_ann, aod_test_mr_sels( $aod_mr_r[0] ) );
}
$aod_mr_j2_bou = array();
foreach ( aod_test_mr_regles( $aod_mr_jouet2 ) as $aod_mr_r ) {
	if ( preg_match( '/transform\s*:\s*(?!none)[a-z]/i', $aod_mr_r[1] ) ) {
		$aod_mr_j2_bou = array_merge( $aod_mr_j2_bou, aod_test_mr_sels( $aod_mr_r[0] ) );
	}
}
AOD_Test::eq(
	'html[dir="rtl"] .t:hover .go',
	implode( ' · ', array_values( array_diff( $aod_mr_j2_bou, $aod_mr_j2_ann ) ) ),
	'TÉMOIN DE SENS : R3 voit qu’une surcharge RTL non listée reste en mouvement (le défaut visible en arabe seulement)'
);
