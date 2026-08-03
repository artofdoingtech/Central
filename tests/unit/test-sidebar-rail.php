<?php
/**
 * Barre latérale : défilement (43.7) et rail replié/déplié (43.8).
 *
 * 🚨 CE QUE CE CLIQUET GARDE, ET QUE RIEN D'AUTRE NE PEUT GARDER. La 43.8 repose
 * sur une décision d'accessibilité invisible à la relecture : les libellés du rail
 * s'effacent en `opacity:0` et **jamais** en `display:none`/`visibility:hidden`.
 * La différence ne se voit pas à l'écran — les deux « masquent » — mais les deux
 * dernières SORTENT les liens de l'ordre de tabulation. `:focus-within` ne
 * pourrait alors plus jamais se déclencher au TAB : la barre resterait repliée
 * sous le focus, et un utilisateur clavier naviguerait dans une barre invisible.
 *
 * C'est le genre de « nettoyage » qu'une future passe ferait de bonne foi
 * (« pourquoi opacity:0 alors que display:none est plus propre ? »). Le
 * commentaire du CSS l'explique ; seul ce cliquet l'empêche.
 *
 * ⚠️ Contrôles STATIQUES sur `dashboard.css` : ils prouvent que les règles sont
 * ÉCRITES, pas qu'elles RENDENT. Le rendu (rail à 64 px, dépliage au survol ET au
 * focus, RTL, mobile intact, mouvement réduit) est éprouvé au navigateur par
 * `tools/recette/a11y-clavier.js`. Les deux sont nécessaires : un test unitaire
 * ne mesure pas une largeur rendue, une porte ne relit pas une intention.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Sidebar 43.7/43.8 — la feuille est lisible' );

$aod_sr_fichier = AOD_CD_PATH . 'assets/css/dashboard.css';
$aod_sr_ok      = is_readable( $aod_sr_fichier );
AOD_Test::ok( $aod_sr_ok, 'dashboard.css est lisible' );

if ( ! $aod_sr_ok ) {
	return;
}

$aod_sr_css = (string) file_get_contents( $aod_sr_fichier );

/**
 * Feuille DÉCOMMENTÉE — c'est sur elle que portent les interdits (R4).
 *
 * 🚨 Trouvé en éprouvant ce cliquet, et c'est instructif : la première version
 * lisait le CSS brut et échouait… à cause du COMMENTAIRE qui explique pourquoi
 * `display:none` est proscrit. Documenter la règle enfreignait la règle. Un
 * contrôle qui lit la prose au lieu du code ne contrôle pas le code — c'est le
 * même défaut que la 31.4 (« une assertion textuelle qu'une mention sans rapport
 * suffit à satisfaire ne prouve rien »), pris par l'autre bout.
 */
$aod_sr_code = (string) preg_replace( '#/\*.*?\*/#s', '', $aod_sr_css );

/**
 * Extrait un bloc `@media` complet à partir d'un marqueur, en COMPTANT les
 * accolades — une regex gloutonne avalerait les media queries suivantes, une
 * regex paresseuse s'arrêterait à la première règle. Le comptage est le seul
 * découpage exact d'un bloc imbriqué.
 *
 * @param string $css     Feuille entière.
 * @param string $marqueur Texte présent JUSTE avant le `@media` visé.
 * @return string Contenu du bloc (accolades extérieures exclues), '' si absent.
 */
function aod_sr_bloc_media( $css, $marqueur ) {
	$pos = strpos( $css, $marqueur );
	if ( false === $pos ) {
		return '';
	}
	$debut = strpos( $css, '@media', $pos );
	if ( false === $debut ) {
		return '';
	}
	$ouvre = strpos( $css, '{', $debut );
	if ( false === $ouvre ) {
		return '';
	}
	$niveau = 0;
	$len    = strlen( $css );
	for ( $i = $ouvre; $i < $len; $i++ ) {
		if ( '{' === $css[ $i ] ) {
			$niveau++;
		} elseif ( '}' === $css[ $i ] ) {
			$niveau--;
			if ( 0 === $niveau ) {
				return substr( $css, $ouvre + 1, $i - $ouvre - 1 );
			}
		}
	}
	return '';
}

/* =========================================================================
 * R1 — 43.7 : la barre défile au lieu de déborder en silence
 * ========================================================================= */

AOD_Test::group( 'Sidebar 43.7 — R1 : débordement vertical géré' );

// Le bloc de base de `.aod-cd-side` (hors media query).
preg_match( '/\.aod-cd-side\{(.*?)\}/s', $aod_sr_code, $aod_sr_m );
$aod_sr_base = isset( $aod_sr_m[1] ) ? $aod_sr_m[1] : '';

AOD_Test::ok( '' !== $aod_sr_base, 'TÉMOIN : le bloc `.aod-cd-side{…}` est trouvé', 'la feuille a changé de forme — adapter ce cliquet, pas le supprimer' );
AOD_Test::ok(
	false !== strpos( $aod_sr_base, 'height:100vh' ),
	'ANCRAGE : la barre est toujours en `height:100vh` — c’est ce qui rend le débordement possible'
);
AOD_Test::ok(
	(bool) preg_match( '/overflow-y\s*:\s*auto/', $aod_sr_base ),
	'🔴 `overflow-y:auto` : un contenu plus haut que l’écran est ATTEIGNABLE (constat 5 de A.20)'
);
AOD_Test::ok(
	false !== strpos( $aod_sr_base, 'scrollbar-width' ) && false !== strpos( $aod_sr_base, 'scrollbar-color' ),
	'La barre de défilement est stylée (fine et teintée) et non celle du système, qui tranche sur un fond sombre'
);
AOD_Test::ok(
	false !== strpos( $aod_sr_code, '.aod-cd-side::-webkit-scrollbar' ),
	'…avec le repli `::-webkit-scrollbar` pour les WebKit antérieurs à la syntaxe standard'
);

/* =========================================================================
 * R2 — 43.8 : le rail est DESKTOP, et il ne pousse pas le contenu
 * ========================================================================= */

AOD_Test::group( 'Sidebar 43.8 — R2 : rail desktop, hors du flux' );

$aod_sr_desktop = aod_sr_bloc_media( $aod_sr_code, '@media(min-width:781px){' );
AOD_Test::ok( '' !== $aod_sr_desktop, 'TÉMOIN : le bloc desktop du rail est extrait (comptage d’accolades)' );
AOD_Test::ok(
	(bool) preg_match( '/@media\(min-width:\s*781px\)/', $aod_sr_code ),
	'Le rail est borné au DESKTOP (`min-width:781px`) — le hamburger mobile reste un mécanisme séparé'
);
AOD_Test::ok(
	(bool) preg_match( '/position\s*:\s*fixed/', $aod_sr_desktop ),
	'🔴 La barre sort du flux : le contenu ne se DÉCALE PAS quand elle se déplie'
);
AOD_Test::ok(
	(bool) preg_match( '/\.aod-cd-main\{[^}]*margin-inline-start\s*:\s*var\(\s*--cd-rail\s*\)/', $aod_sr_desktop ),
	'🔴 Le contenu est décalé par `margin-inline-start` (propriété LOGIQUE : correct en RTL sans règle miroir)'
);
AOD_Test::ok(
	(bool) preg_match( '/inset-inline-start/', $aod_sr_desktop ),
	'…et la barre est ancrée par `inset-inline-start`, jamais `left` (même raison)'
);

/* =========================================================================
 * R3 — UN état visuel, DEUX déclencheurs (exigence explicite de D2)
 * ========================================================================= */

AOD_Test::group( 'Sidebar 43.8 — R3 : survol ET focus, dans la MÊME règle' );

AOD_Test::ok(
	(bool) preg_match( '/\.aod-cd-side:hover,\s*\.aod-cd-side:focus-within\{[^}]*width\s*:\s*var\(\s*--cd-side-w\s*\)/', $aod_sr_desktop ),
	'🔴 `:hover` et `:focus-within` partagent la MÊME déclaration d’état déplié',
	'deux règles séparées finiraient par diverger — D2 exige un seul état visuel, deux déclencheurs'
);

/* =========================================================================
 * R4 — L'INVARIANT D'ACCESSIBILITÉ : opacity, jamais display/visibility
 * ========================================================================= */

AOD_Test::group( 'Sidebar 43.8 — R4 : les libellés s’effacent SANS quitter l’ordre de tabulation' );

AOD_Test::ok(
	(bool) preg_match( '/opacity\s*:\s*0/', $aod_sr_desktop ),
	'ANCRAGE : les libellés du rail sont bien masqués par `opacity:0`'
);
AOD_Test::ok(
	! preg_match( '/display\s*:\s*none/', $aod_sr_desktop ),
	'🔴 AUCUN `display:none` dans le bloc du rail',
	'`display:none` sortirait les liens de l’ordre de tabulation : `:focus-within` ne pourrait plus jamais déplier la barre au TAB, et l’utilisateur clavier naviguerait dans une barre invisible'
);
AOD_Test::ok(
	! preg_match( '/visibility\s*:\s*hidden/', $aod_sr_desktop ),
	'🔴 AUCUN `visibility:hidden` dans le bloc du rail',
	'même conséquence exacte que `display:none` sur l’ordre de tabulation'
);
AOD_Test::ok(
	(bool) preg_match( '/:focus-within\s+\.aod-cd-navlink\s+span/', $aod_sr_desktop ),
	'🔴 Le focus clavier RÉVÈLE les libellés (pas seulement le survol souris)'
);

/* =========================================================================
 * R5 — Mouvement réduit : instantané, mais toujours dépliable
 * ========================================================================= */

AOD_Test::group( 'Sidebar 43.8 — R5 : `prefers-reduced-motion` neutralise l’animation, pas la fonction' );

$aod_sr_pos_rail = strpos( $aod_sr_code, '@media(min-width:781px){' );
$aod_sr_pos_pref = strpos( $aod_sr_code, 'prefers-reduced-motion:reduce' );
// On cherche LA règle de mouvement réduit qui suit le bloc du rail.
$aod_sr_pos_pref_apres = false !== $aod_sr_pos_rail ? strpos( $aod_sr_code, 'prefers-reduced-motion:reduce', $aod_sr_pos_rail ) : false;

AOD_Test::ok(
	false !== $aod_sr_pos_pref_apres,
	'Une règle `prefers-reduced-motion` existe APRÈS le bloc du rail',
	'placée avant, elle serait annulée par la règle qu’elle est censée neutraliser — à spécificité égale, la dernière gagne'
);

$aod_sr_reduit = aod_sr_bloc_media( $aod_sr_code, '@media(min-width:781px) and (prefers-reduced-motion:reduce)' );
AOD_Test::ok(
	'' !== $aod_sr_reduit && (bool) preg_match( '/transition\s*:\s*none/', $aod_sr_reduit ),
	'🔴 La bascule devient INSTANTANÉE (`transition:none`)'
);
AOD_Test::ok(
	! preg_match( '/width\s*:/', $aod_sr_reduit ),
	'🔴 …et la largeur n’y est PAS touchée : la barre se déplie toujours, elle ne s’anime plus',
	'neutraliser le dépliage priverait l’utilisateur de la nav au lieu de lui épargner une animation'
);
AOD_Test::ok(
	(bool) preg_match( '/min-width:\s*781px\).*prefers-reduced-motion/s', substr( $aod_sr_code, (int) $aod_sr_pos_rail ) ),
	'…et elle reste bornée au desktop, comme le rail qu’elle accompagne'
);

/* =========================================================================
 * R6 — Le mécanisme MOBILE n'a pas été absorbé
 * ========================================================================= */

AOD_Test::group( 'Sidebar 43.8 — R6 : le hamburger mobile est intact' );

AOD_Test::ok(
	false !== strpos( $aod_sr_code, '.aod-cd-side.is-open' ),
	'🔴 Le mécanisme `.is-open` (hamburger mobile) existe toujours',
	'D2 est explicite : deux mécanismes indépendants, l’un ne remplace pas l’autre'
);
AOD_Test::ok(
	false === strpos( $aod_sr_desktop, 'is-open' ),
	'…et le bloc DESKTOP ne le touche pas (aucune adhérence entre les deux)'
);

/* =========================================================================
 * R7 — 45.5 : le rail REPLIÉ a sa propre géométrie verticale
 *
 * 🚨 CE QUE CE GROUPE GARDE, ET QU'UNE PORTE NE PEUT PAS GARDER. Deux règles de
 * la 45.5 sont invérifiables au navigateur sur la machine de recette :
 *
 *  1. « Repliée, la barre de défilement ne prend AUCUNE largeur » (D7 de A.21).
 *     Le Chromium de la recette rend des barres en SURIMPRESSION :
 *     `offsetWidth − clientWidth` y vaut 0 avant comme après, et vaudrait
 *     encore 0 si on retirait la règle. Éprouvé : réinjecter le remède rejeté
 *     (`scrollbar-gutter:stable both-edges`) n'a fait rougir AUCUNE assertion
 *     de la porte `clavier`. Une assertion qui ne peut pas échouer là où elle
 *     tourne ne prouve rien — c'est la leçon `FS_METHOD` (TEMPLATE.md §3).
 *
 *  2. « Le rythme vertical est le MÊME dans les deux états ». C'est le principe
 *     ANTI-GLISSEMENT : le rail se déplie au survol, si sa densité changeait en
 *     s'ouvrant les liens fileraient sous le curseur au moment précis où il
 *     vient les chercher. La porte mesure la CONSÉQUENCE (le glissement reste
 *     borné) ; seule la feuille dit l'INTENTION — une seule variable, employée
 *     hors du sélecteur d'état replié.
 * ========================================================================= */

AOD_Test::group( 'Sidebar 45.5 — R7 : géométrie propre au rail replié' );

$aod_sr_replie = preg_match_all( '/\.aod-cd-side:not\(:hover\):not\(:focus-within\)/', $aod_sr_desktop );
AOD_Test::ok(
	$aod_sr_replie >= 6,
	sprintf( 'TÉMOIN : %d règles portent le sélecteur d’état replié (`:not(:hover):not(:focus-within)`)', (int) $aod_sr_replie ),
	'la 45.5 en pose 9 ; en trouver 0 ou 1 signifie que la feuille a été refondue — adapter ce cliquet, pas le supprimer'
);

AOD_Test::ok(
	(bool) preg_match( '/--cd-navpad-y\s*:\s*clamp\(/', $aod_sr_desktop ),
	'🔴 Le rythme vertical de la nav est une VARIABLE en `clamp()` — il s’adapte à la hauteur de la fenêtre',
	'sans lui le rail ne tient plus sur un écran court : c’est la demande owner du 2026-07-29'
);

// La variable doit être consommée par une règle qui vaut dans LES DEUX états.
preg_match( '/([^{}]*)\{[^{}]*padding-block\s*:\s*var\(\s*--cd-navpad-y\s*\)/', $aod_sr_desktop, $aod_sr_sel );
$aod_sr_sel_pad = isset( $aod_sr_sel[1] ) ? trim( $aod_sr_sel[1] ) : '';
AOD_Test::ok(
	'' !== $aod_sr_sel_pad && false === strpos( $aod_sr_sel_pad, ':not(:hover)' ),
	sprintf( '🔴 ANTI-GLISSEMENT : le rythme s’applique aux DEUX états (sélecteur « %s »)', $aod_sr_sel_pad ),
	'scopé à l’état replié, il rendrait le rail plus dense QUE replié — et le dépliage ferait glisser les liens sous le curseur au moment où il vient les chercher'
);

AOD_Test::ok(
	(bool) preg_match( '/:not\(:hover\):not\(:focus-within\)\s+\.aod-cd-navlink\s+span\{[^}]*height\s*:\s*0/', $aod_sr_desktop ),
	'🔴 Replié, le libellé ne PÈSE plus en hauteur (`height:0`, pas `display:none`)',
	'`opacity:0` ne retire rien à la mise en page : sans cette règle, un texte que le rail n’affiche pas dicte sa hauteur — 1069 px en FR et 1348 px en AR, mesurés'
);

AOD_Test::ok(
	(bool) preg_match( '/:not\(:hover\):not\(:focus-within\)\s+\.aod-cd-navgroup\{[^}]*height\s*:\s*0/', $aod_sr_desktop ),
	'🔴 …et les 6 intitulés de groupe deviennent un filet (185 px en FR, 239 en AR, jamais lus à 64 px de large)'
);

AOD_Test::ok(
	(bool) preg_match( '/:not\(:hover\):not\(:focus-within\)\{[^}]*scrollbar-width\s*:\s*none/', $aod_sr_desktop ),
	'🔴 D7 : replié, la barre de défilement ne prend AUCUNE largeur — sinon elle décentrerait les icônes que la 45.1 vient de centrer',
	'INVÉRIFIABLE au navigateur ici (barres en surimpression) : c’est cette assertion, et elle seule, qui garde la règle'
);
AOD_Test::ok(
	(bool) preg_match( '/overflow-y\s*:\s*auto/', $aod_sr_base ),
	'ANCRAGE : …et ce n’est PAS un retour au défaut de la 43.7 — `overflow-y:auto` est toujours posé, molette et clavier défilent encore'
);
