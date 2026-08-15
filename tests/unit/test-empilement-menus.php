<?php
/**
 * Une animation d'entrée ne doit RIEN retenir qui empile.
 *
 * LE DÉFAUT, DEUX FOIS EN DEUX JOURS, MÊME MÉCANISME.
 * `animation-fill-mode: forwards` (et donc `both`, qui le contient) retient le
 * DERNIER cran des keyframes après la fin. Quand ce dernier cran vaut
 * `transform: none` — l'état naturel de l'élément, écrit là uniquement pour
 * clore un `translateY` —, le navigateur ne retire pas le `transform` : il le
 * calcule, et `none` se résout en `matrix(1, 0, 0, 1, 0, 0)`.
 *
 * Une identité. Invisible à l'œil, et pourtant pas `none` au sens de la cascade :
 * l'élément devient un BLOC CONTENEUR (un `position:fixed` enfant s'y résout au
 * lieu du viewport) ET un CONTEXTE D'EMPILEMENT (un `z-index` enfant, si grand
 * soit-il, ne peut plus franchir l'ancêtre).
 *
 * · 2026-08-14 — la moitié POSITION : les menus de la liste des commandes
 *   s'ouvraient hors écran, `.aod-cd-table tbody tr` portant `aod-cd-rise …both`.
 *   Corrigé, et couvert par une correction de position à la mesure.
 * · 2026-08-15 — la moitié EMPILEMENT, non traitée la veille : le même mode sur
 *   `.aod-cd-modal-body > *` peignait les menus du détail d'une commande DERRIÈRE
 *   les cartes suivantes. `z-index:1000` sur le menu, et il passait dessous.
 *   🚨 Une correction à la mesure ne pouvait pas rattraper celle-ci : on peut
 *   mesurer un référentiel de position, pas se hisser hors d'un contexte
 *   d'empilement d'ancêtre. Il n'y a pas de repli à l'exécution — d'où ce cliquet.
 *
 * CE QUE CE TEST FAIT. Il lit `dashboard.css`, relève chaque `animation:` dont le
 * mode contient `forwards` ou `both`, résout ses `@keyframes`, et REFUSE que le
 * dernier cran déclare un `transform`. `backwards` reste libre : il ne retient
 * que l'état d'AVANT, et c'est presque toujours le seul service qu'on demandait.
 *
 * ⚠️ CE QU'IL NE PROUVE PAS. Il ne regarde QUE les animations. Un `filter`, un
 * `will-change`, un `contain:paint` posé un jour sur un conteneur piégerait un
 * menu exactement pareil, et ce test resterait vert. Il ferme la porte par
 * laquelle le défaut est entré deux fois, pas toutes les portes.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Empilement — aucune animation ne retient de transform' );

$aod_em_css = AOD_CD_PATH . 'assets/css/dashboard.css';

AOD_Test::ok(
	file_exists( $aod_em_css ),
	'dashboard.css est lisible depuis le conteneur',
	'introuvable : ' . $aod_em_css
);

$aod_em_src = file_exists( $aod_em_css ) ? file_get_contents( $aod_em_css ) : '';

/*
 * 1) Les keyframes qui FINISSENT sur un transform. Le dernier cran est `to` ou
 *    `100%` ; à défaut de l'un et l'autre, on prend le dernier bloc écrit —
 *    c'est ce que le navigateur retiendra.
 */
$aod_em_finit_par_transform = array();
if ( preg_match_all( '/@keyframes\s+([\w-]+)\s*\{(.+?)\}\s*(?=@|\.|#|\/\*|\s*$)/s', $aod_em_src, $aod_em_kf, PREG_SET_ORDER ) ) {
	foreach ( $aod_em_kf as $aod_em_bloc ) {
		$aod_em_nom  = $aod_em_bloc[1];
		$aod_em_crans = array();
		if ( preg_match_all( '/([\w%.,\s]+)\{([^}]*)\}/', $aod_em_bloc[2], $aod_em_m, PREG_SET_ORDER ) ) {
			foreach ( $aod_em_m as $aod_em_c ) {
				$aod_em_crans[ trim( $aod_em_c[1] ) ] = $aod_em_c[2];
			}
		}
		if ( ! $aod_em_crans ) {
			continue;
		}
		$aod_em_dernier = '';
		if ( isset( $aod_em_crans['to'] ) ) {
			$aod_em_dernier = $aod_em_crans['to'];
		} elseif ( isset( $aod_em_crans['100%'] ) ) {
			$aod_em_dernier = $aod_em_crans['100%'];
		} else {
			$aod_em_dernier = end( $aod_em_crans );
		}
		if ( false !== strpos( $aod_em_dernier, 'transform' ) ) {
			$aod_em_finit_par_transform[] = $aod_em_nom;
		}
	}
}

/*
 * 2) Les déclarations `animation:` qui RETIENNENT cette fin. On travaille ligne
 *    à ligne pour pouvoir nommer le numéro en cas d'échec : un cliquet qui dit
 *    « quelque part dans 7 000 lignes » se fait contourner, pas corriger.
 */
$aod_em_fautives = array();
$aod_em_vues     = 0;
foreach ( explode( "\n", $aod_em_src ) as $aod_em_i => $aod_em_ligne ) {
	if ( ! preg_match( '/animation:\s*([^;}]+)/', $aod_em_ligne, $aod_em_d ) ) {
		continue;
	}
	$aod_em_decl = $aod_em_d[1];
	if ( ! preg_match( '/\b(forwards|both)\b/', $aod_em_decl ) ) {
		continue;
	}
	++$aod_em_vues;
	foreach ( $aod_em_finit_par_transform as $aod_em_nom ) {
		if ( preg_match( '/\b' . preg_quote( $aod_em_nom, '/' ) . '\b/', $aod_em_decl ) ) {
			$aod_em_fautives[] = 'l.' . ( $aod_em_i + 1 ) . ' → ' . $aod_em_nom;
		}
	}
}

AOD_Test::ok(
	empty( $aod_em_fautives ),
	'aucune animation ne retient un transform après sa fin (donc aucun contexte d’empilement fantôme)',
	empty( $aod_em_fautives )
		? ''
		: implode( ', ', $aod_em_fautives )
			. ' — passe le mode à `backwards` : il garde l’état d’AVANT (ce qu’on voulait pour un retard) sans retenir celui d’APRÈS'
);

/*
 * ANCRAGE INVERSE — un contrôle qui n'a rien inspecté est vert pour la mauvaise
 * raison. Si le motif cesse de mordre (réécriture en propriétés longues, minification),
 * ces deux assertions le disent au lieu de laisser passer.
 */
AOD_Test::ok(
	$aod_em_vues > 0,
	'le contrôle a bien trouvé des animations à mode persistant à inspecter',
	'0 déclaration `animation:` avec forwards/both — le motif ne mord plus'
);
AOD_Test::ok(
	count( $aod_em_finit_par_transform ) > 0,
	'le contrôle a bien lu des @keyframes finissant sur un transform',
	'0 keyframe de ce type — la lecture des @keyframes est cassée, pas le CSS'
);
