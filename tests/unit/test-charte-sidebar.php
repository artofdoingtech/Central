<?php
/**
 * Sidebar dérivée de l'accent (ROADMAP Phase 15, étape 15.5).
 *
 * Le fond de la sidebar n'est plus une couleur écrite dans le CSS : il est
 * TEINTÉ de l'accent choisi par le client (15 % d'accent dans le neutre). C'est
 * la seule forme acceptable pour un template — une couleur de marque en dur
 * serait héritée par tous les futurs clones (règle n°1).
 *
 * 🚨 Ce que ce test protège, et qu'aucune recette visuelle ne peut voir.
 * Teinter le fond change le contraste de TOUT ce qui est posé dessus, et le
 * client peut choisir n'importe quelle couleur du cube. Une recette navigateur
 * essaie 5 accents ; ici on en balaie 4 096. Surtout : la dégradation est
 * SILENCIEUSE — un contraste qui passe de 7:1 à 4:1 ne casse rien, ne lève
 * aucune erreur, et ne se voit pas à l'œil sur l'accent qu'on a sous les yeux.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ratio de contraste WCAG entre deux luminances.
 *
 * Nom propre à ce fichier, et non un `function_exists` autour de l'homonyme de
 * test-contraste.php : les deux fichiers sont chargés par un simple glob, donc
 * celui qui gagne dépend de l'ordre alphabétique. Un garde aurait fait dépendre
 * le comportement de qui arrive en premier — et c'est exactement ce qui a fait
 * tomber ce test la première fois (fatal « Cannot redeclare »).
 *
 * @param float $l1 Luminance.
 * @param float $l2 Luminance.
 * @return float
 */
function aod_test_side_ratio( $l1, $l2 ) {
	return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
}

/**
 * Sidebar dérivée pour un accent donné, via la VRAIE implémentation.
 *
 * 🚨 On appelle `side()`, on ne recopie PAS sa formule. Une copie du mélange
 * aurait balayé 4 096 accents sans jamais voir le plafond de luminance — le test
 * se serait déclaré satisfait après avoir mesuré autre chose que le code livré.
 * C'est pour ça que `side()` accepte un accent en paramètre.
 *
 * @param string $hex Accent.
 * @return array array(r,g,b).
 */
function aod_test_side_rgb( $hex ) {
	return AOD_Test::call_static( 'AOD_CD_Customize', 'rgb', array( AOD_CD_Customize::side( $hex ) ) );
}

AOD_Test::group( 'Charte — mix() accepte toujours une cible achromatique' );

// mix() a été généralisé en 15.5 pour accepter un array(r,g,b). Ses trois appels
// HISTORIQUES passent un scalaire (0 = noir, 255 = blanc) et pilotent
// `--cd-accent-strong`, `--cd-accent-soft` et readable_on(). Une régression ici
// délaverait toute la palette sans toucher à la sidebar.
// 100 + (255-100) × 0.5 = 177.5 → 178 = 0xb2 sur les trois canaux.
$scal = AOD_Test::call_static( 'AOD_CD_Customize', 'mix', array( array( 100, 100, 100 ), 255, 0.5 ) );
AOD_Test::eq( '#b2b2b2', AOD_Test::call_static( 'AOD_CD_Customize', 'to_hex', array( $scal ) ), 'mix() vers un scalaire (255) : la forme historique marche encore' );

// #00A651 = (0,166,81) → (24×0.85, 166×0.15+24×0.85, 81×0.15+24×0.85) = #142d21,
// la valeur mesurée et publiée dans docs/ICONES-ET-CHARTE-GESTION.md §4.3.
$arr = AOD_Test::call_static( 'AOD_CD_Customize', 'mix', array( array( 0, 166, 81 ), array( 24, 24, 24 ), 0.85 ) );
AOD_Test::eq( '#142d21', AOD_Test::call_static( 'AOD_CD_Customize', 'to_hex', array( $arr ) ), 'mix() vers un array : le vert de référence donne bien le noir-vert documenté' );

AOD_Test::group( 'Charte — la sidebar est réellement TEINTÉE (sinon la dérivation ne sert à rien)' );

// Une dérivation qui rendrait un gris serait pire qu'inutile : tout le coût du
// mécanisme, aucun bénéfice. On vérifie qu'un accent saturé laisse une trace
// chromatique mesurable (écart entre canal max et canal min).
foreach ( array( '#00A651', '#1878f0', '#b42318' ) as $hex ) {
	$s     = aod_test_side_rgb( $hex );
	$chrom = max( $s ) - min( $s );
	AOD_Test::ok(
		$chrom >= 8,
		sprintf( 'Accent %s : sidebar %s, écart chromatique %d — la teinte se lit', $hex, AOD_Test::call_static( 'AOD_CD_Customize', 'to_hex', array( $s ) ), $chrom ),
		sprintf( 'sidebar quasi grise (écart %d) : la dérivation ne produit plus de teinte', $chrom )
	);
}

// Le neutre reste neutre : un accent achromatique ne doit pas inventer de couleur.
// `(int)` : round() rend un float, et AOD_Test::eq compare en strict — sans le
// cast, 0.0 !== 0 faisait échouer une assertion pourtant vraie.
$grey = aod_test_side_rgb( '#808080' );
AOD_Test::eq( 0, (int) ( max( $grey ) - min( $grey ) ), 'Un accent gris laisse la sidebar parfaitement neutre' );

AOD_Test::group( 'Charte — le plafond de luminance : une sidebar toujours aussi sombre' );

// Sans plafond, le mélange transmettait la CLARTÉ de l'accent autant que sa teinte :
// le jaune du template sortait à 4,0× la luminance du neutre (kaki #3b3619), quand
// tous les autres accents tenaient sous 2,4×. Le test porte sur l'écart entre
// accents, pas sur une valeur absolue — c'est l'uniformité qui est la propriété.
$lum_neutral = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( array( 24, 24, 24 ) ) );
$worst_lift  = 0.0;
$worst_lift_h = '';
for ( $r = 0; $r <= 255; $r += 17 ) {
	for ( $g = 0; $g <= 255; $g += 17 ) {
		for ( $b = 0; $b <= 255; $b += 17 ) {
			$hex  = sprintf( '#%02x%02x%02x', $r, $g, $b );
			$lift = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( aod_test_side_rgb( $hex ) ) ) / $lum_neutral;
			if ( $lift > $worst_lift ) {
				$worst_lift   = $lift;
				$worst_lift_h = $hex;
			}
		}
	}
}
AOD_Test::ok(
	$worst_lift <= 2.6,
	sprintf( 'Aucun accent n\'éclaircit la sidebar au-delà de 2,6× le neutre (pire %.1f× sur %s)', $worst_lift, $worst_lift_h ),
	sprintf( 'la sidebar part à %.1f× le neutre sur %s : le plafond ne joue plus', $worst_lift, $worst_lift_h )
);

// Le témoin nommé : l'accent PAR DÉFAUT du template est le cas qui a révélé le
// problème, et le seul des six mesurés à déclencher le plafond. Un seuil chiffré
// se contourne par accident ; un témoin non.
$def = AOD_Test::call_static( 'AOD_CD_Customize', 'to_hex', array( aod_test_side_rgb( AOD_CD_Customize::DEFAULT_ACCENT ) ) );
AOD_Test::ok(
	'#3b3619' !== $def,
	sprintf( 'Le jaune par défaut ne rend plus le kaki clair d\'avant plafond (sidebar %s)', $def ),
	'la sidebar du template est repartie à #3b3619 : SIDE_MAX_LUM ne s\'applique plus'
);

// …et le vert de référence de la documentation, lui, doit être INTACT : un plafond
// qui déplacerait tout le monde ne serait plus un plafond, ce serait un second
// mélange déguisé.
AOD_Test::eq(
	'#142d21',
	AOD_Test::call_static( 'AOD_CD_Customize', 'to_hex', array( aod_test_side_rgb( '#00A651' ) ) ),
	'Le vert de référence passe sous le plafond sans être touché (valeur documentée §4.3)'
);

AOD_Test::group( 'Charte — tout ce qui est posé sur la sidebar reste lisible, sur TOUT accent' );

$txt   = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( array( 207, 207, 207 ) ) ); // --cd-side-txt #cfcfcf
$white = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( array( 255, 255, 255 ) ) );  // outline de focus

$worst_txt   = 21.0;
$worst_txt_h = '';
$worst_wht   = 21.0;
$worst_wht_h = '';
$darker      = 0;
$total       = 0;

for ( $r = 0; $r <= 255; $r += 17 ) {
	for ( $g = 0; $g <= 255; $g += 17 ) {
		for ( $b = 0; $b <= 255; $b += 17 ) {
			++$total;
			$hex  = sprintf( '#%02x%02x%02x', $r, $g, $b );
			$side = aod_test_side_rgb( $hex );
			$ls   = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( $side ) );

			$rt = aod_test_side_ratio( $ls, $txt );
			if ( $rt < $worst_txt ) {
				$worst_txt   = $rt;
				$worst_txt_h = $hex;
			}
			$rw = aod_test_side_ratio( $ls, $white );
			if ( $rw < $worst_wht ) {
				$worst_wht   = $rw;
				$worst_wht_h = $hex;
			}
			if ( $ls < 0.00913 ) {
				++$darker;
			}
		}
	}
}

AOD_Test::ok(
	$worst_txt >= 4.5,
	sprintf( 'Sur %d accents, le texte de navigation (#cfcfcf) reste AA sur la sidebar teintée (pire %.2f:1, accent %s)', $total, $worst_txt, $worst_txt_h ),
	sprintf( 'plancher tombé à %.2f:1 sur %s — SIDE_MIX est trop bas', $worst_txt, $worst_txt_h )
);

// L'anneau de focus de la sidebar est blanc (`.aod-cd-side :focus-visible`). Seuil
// non-textuel WCAG = 3:1 ; on est très au-dessus, mais c'est la marge qui compte.
AOD_Test::ok(
	$worst_wht >= 3.0,
	sprintf( 'L\'anneau de focus blanc reste visible sur la sidebar teintée (pire %.2f:1, accent %s)', $worst_wht, $worst_wht_h ),
	sprintf( 'anneau de focus sous 3:1 (%.2f:1 sur %s)', $worst_wht, $worst_wht_h )
);

AOD_Test::group( 'Charte — le couplage readable_on() : calibrer sur la BONNE surface' );

// `--cd-accent-on-dark` sert sur DEUX fonds différents depuis 15.5 : la sidebar
// (dérivée) et le neutre (pastille de langue active, pagination active). css_vars()
// calibre sur la plus CLAIRE des deux — passer là garantit de passer sur l'autre.
//
// L'hypothèse tentante « la sidebar est toujours la plus claire » est FAUSSE, et
// c'est mesurable : les accents très sombres donnent une sidebar plus sombre que
// le neutre. Ce compte est le témoin de la raison d'être du choix — s'il tombe à
// zéro, c'est que la dérivation a changé et que le `max()` de css_vars() n'a plus
// d'objet.
AOD_Test::ok(
	$darker > 0,
	sprintf( '%d accents sur %d donnent une sidebar plus SOMBRE que le neutre — d\'où le calibrage sur la plus claire', $darker, $total ),
	'plus aucun accent ne produit ce cas : vérifier que css_vars() choisit encore la bonne surface'
);

// Bout en bout, à travers le VRAI css_vars() : c'est lui qui câble le tout, et
// c'est lui qu'un futur refactor casserait. On repose la question sur les deux
// fonds, pour quelques accents dont chacun a une raison d'être ici.
$cases = array(
	AOD_CD_Customize::DEFAULT_ACCENT => 'le défaut du template (jaune, n\'a jamais déclenché readable_on)',
	'#006633'                        => 'un vert de club foncé — le cas qui a motivé l\'étape',
	'#1878f0'                        => 'le bleu de marque banal, pire cas connu de best_ink',
	'#000000'                        => 'un accent noir : sidebar plus sombre que le neutre',
	'#ffffff'                        => 'un accent blanc, l\'autre extrême',
);

$original = get_theme_mod( 'aod_cd_accent', AOD_CD_Customize::DEFAULT_ACCENT );

foreach ( $cases as $hex => $why ) {
	set_theme_mod( 'aod_cd_accent', $hex );
	$css = AOD_CD_Customize::css_vars();

	preg_match( '/--cd-accent-on-dark:(#[0-9a-f]{6})/i', $css, $m1 );
	preg_match( '/--cd-side:(#[0-9a-f]{6})/i', $css, $m2 );

	AOD_Test::ok( ! empty( $m2[1] ), sprintf( 'css_vars() publie bien --cd-side pour %s', $hex ) );

	$l_ink = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( array( 24, 24, 24 ) ) );
	$l_sid = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( AOD_Test::call_static( 'AOD_CD_Customize', 'rgb', array( $m2[1] ) ) ) );
	$l_txt = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( AOD_Test::call_static( 'AOD_CD_Customize', 'rgb', array( $m1[1] ) ) ) );

	$on_side = aod_test_side_ratio( $l_txt, $l_sid );
	$on_ink  = aod_test_side_ratio( $l_txt, $l_ink );

	AOD_Test::ok(
		min( $on_side, $on_ink ) >= 4.5,
		sprintf( 'Accent %s (%s) : accent-on-dark %s lisible sur la sidebar %s (%.2f:1) ET sur le neutre (%.2f:1)', $hex, $why, $m1[1], $m2[1], $on_side, $on_ink ),
		sprintf( 'contraste insuffisant : %.2f:1 sur la sidebar, %.2f:1 sur le neutre', $on_side, $on_ink )
	);
}

set_theme_mod( 'aod_cd_accent', $original );

// Le réglage du site doit ressortir intact : un test qui repeint la boutique du
// client serait un bug, pas un test.
AOD_Test::eq( $original, get_theme_mod( 'aod_cd_accent', AOD_CD_Customize::DEFAULT_ACCENT ), 'L\'accent du site est restauré après le balayage' );
