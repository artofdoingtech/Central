<?php
/**
 * Emblèmes des transporteurs — `AOD_Carrier_Emblem` + la pastille qui les porte.
 *
 * CE QUE CES TESTS GARDENT, ET POURQUOI ÇA NE SE VOIT PAS À L'ŒIL.
 *
 * 1. Un nom d'emblème inconnu NE CASSE RIEN de visible : `svg()` rend '' et la
 *    pastille retombe sur le monogramme. La ligne reste belle, simplement le
 *    dessin a disparu — sur un tableau de 29 lignes, personne ne le remarque.
 *    Même trou que celui gardé par `test-icones.php` sur le catalogue de /gestion.
 *
 * 2. Une couleur écrite en dur dans un emblème le rendrait ILLISIBLE sur certaines
 *    marques seulement. Les 23 white-labels EcoTrack déclarent 23 couleurs hors de
 *    notre contrôle : un dessin blanc en dur disparaît sur l'ambre de Rocket
 *    (#f59e0b), et reste parfait sur les 22 autres. Un défaut qui ne se montre
 *    qu'à un lecteur sur vingt-trois n'est jamais relevé en revue.
 *
 * 3. 🚨 LE DÉGRADÉ PEUT DÉFAIRE `brand_ink()` EN SILENCE. `brand_ink()` choisit
 *    noir ou blanc en mesurant le contraste SUR LA COULEUR PLEINE. La pastille
 *    peint désormais DEUX couleurs sous le même monogramme. Assombrir sans
 *    regarder ferait chuter l'encre noire — celle retenue précisément sur les
 *    marques claires — sous le seuil AA, pendant que `brand_ink()` continuerait de
 *    renvoyer la bonne réponse à la mauvaise question. R3 mesure les DEUX bouts.
 *
 * 4. Un white-label ajouté sans clé `emblem` hérite du DÉFAUT de
 *    `AOD_Carrier_Ecotrack`, qui est l'emblème d'EcoTrack lui-même : il
 *    s'afficherait déguisé en sa plateforme. R2 l'interdit.
 *
 * ⚠️ Test STATIQUE et par RÉFLEXION. Il n'éprouve pas le rendu — c'est la porte
 * `rtl` de la recette qui déplie le tableau des transporteurs au navigateur.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Transporteurs — emblèmes de marque (AOD_Carrier_Emblem)' );

/**
 * Luminance relative WCAG d'un #RRGGBB.
 *
 * ⚠️ Préfixe `aod_test_emb_` : `tests/all.php` charge tout dans le même espace.
 *
 * @param string $hex
 * @return float
 */
function aod_test_emb_lum( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	$lin = array();
	foreach ( array( 0, 2, 4 ) as $i ) {
		$v     = hexdec( substr( $hex, $i, 2 ) ) / 255;
		$lin[] = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

/**
 * Ratio de contraste WCAG entre deux #RRGGBB.
 *
 * @param string $a
 * @param string $b
 * @return float
 */
function aod_test_emb_ratio( $a, $b ) {
	$la = aod_test_emb_lum( $a );
	$lb = aod_test_emb_lum( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

$emb_ship      = AOD_Shipping::instance();
$emb_carriers  = $emb_ship->carriers();
$emb_catalogue = AOD_Carrier_Emblem::catalog();

/* ------------------------------------------------------------------
 * R0 · ANCRE. Sans elle, tout ce fichier peut passer au vert sur un
 * registre vide — 0 transporteur, 0 faute, 0 information.
 * ---------------------------------------------------------------- */
AOD_Test::ok(
	count( $emb_carriers ) >= 29,
	'Ancre : le registre porte bien les transporteurs (' . count( $emb_carriers ) . ')',
	'registre trop court : les boucles ci-dessous ne mesureraient rien'
);
AOD_Test::ok(
	count( $emb_catalogue ) >= 15,
	'Ancre : le catalogue d\'emblèmes est peuplé (' . count( $emb_catalogue ) . ' emblèmes)'
);

/* ------------------------------------------------------------------
 * R1 · LE CATALOGUE EST PROPRE.
 *
 * `currentColor` UNIQUEMENT : c'est ce qui fait suivre l'encre calculée par
 * `brand_ink()`. Une seule valeur en dur suffit à casser le lien — et seulement
 * sur les marques dont l'encre n'est pas celle-là (cf. point 2 de l'en-tête).
 * ---------------------------------------------------------------- */
$emb_dur   = array();
$emb_vides = array();
foreach ( $emb_catalogue as $emb_nom => $emb_svg ) {
	if ( '' === trim( $emb_svg ) ) {
		$emb_vides[] = $emb_nom;
	}
	// Couleur littérale : #hex, rgb(), hsl(), ou un mot-couleur sur fill/stroke.
	if ( preg_match( '/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(/', $emb_svg )
		|| preg_match( '/(?:fill|stroke)="(?!currentColor|none)[^"]+"/', $emb_svg ) ) {
		$emb_dur[] = $emb_nom;
	}
}
AOD_Test::ok( empty( $emb_vides ), 'Aucun emblème vide au catalogue', implode( ', ', $emb_vides ) );
AOD_Test::ok(
	empty( $emb_dur ),
	'Chaque emblème ne peint qu\'en `currentColor` (l\'encre suit brand_ink())',
	'couleur en dur : ' . implode( ', ', $emb_dur )
);

// Un emblème doit dessiner quelque chose : au moins une forme.
$emb_sans_forme = array();
foreach ( $emb_catalogue as $emb_nom => $emb_svg ) {
	if ( ! preg_match( '/<(?:path|circle|ellipse|rect|polygon)\b/', $emb_svg ) ) {
		$emb_sans_forme[] = $emb_nom;
	}
}
AOD_Test::ok( empty( $emb_sans_forme ), 'Chaque emblème porte au moins une forme', implode( ', ', $emb_sans_forme ) );

// `svg()` refuse un nom inconnu en rendant '' — c'est le repli sur le monogramme,
// pas une exception. Et il n'échappe rien : le markup vient d'ici, jamais d'une
// saisie. Le témoin garde ce contrat dans les deux sens.
AOD_Test::eq( '', AOD_Carrier_Emblem::svg( 'nexiste-pas' ), 'Un nom hors catalogue rend \'\' (repli sur le monogramme)' );
AOD_Test::eq( '', AOD_Carrier_Emblem::svg( '' ), 'Le nom vide rend \'\' (c\'est la valeur des transporteurs sans emblème)' );
AOD_Test::ok(
	false !== strpos( AOD_Carrier_Emblem::svg( 'star' ), 'viewBox="0 0 24 24"' ),
	'Un emblème connu est rendu dans la boîte 24 × 24'
);

/* ------------------------------------------------------------------
 * R2 · CHAQUE TRANSPORTEUR RÉSOUT.
 * ---------------------------------------------------------------- */
$emb_inconnus = array();
$emb_avec     = 0;
foreach ( $emb_carriers as $emb_id => $emb_c ) {
	$emb_n = $emb_c->emblem();
	if ( '' === $emb_n ) {
		continue; // Monogramme : légitime, cf. l'en-tête de class-aod-carrier-emblem.php.
	}
	++$emb_avec;
	if ( ! isset( $emb_catalogue[ $emb_n ] ) ) {
		$emb_inconnus[] = $emb_id . ' → ' . $emb_n;
	}
}
AOD_Test::ok(
	empty( $emb_inconnus ),
	'Chaque emblème de transporteur existe au catalogue (' . $emb_avec . ' transporteurs en portent un)',
	'inconnus : ' . implode( ', ', $emb_inconnus )
);

// 🔒 La clé `emblem` est OBLIGATOIRE dans la table des white-labels, '' compris :
// sans elle, le défaut d'AOD_Carrier_Ecotrack s'applique — l'emblème d'EcoTrack.
$emb_wl = AOD_Test::call( $emb_ship, 'ecotrack_whitelabels' );
$emb_sans_cle = array();
foreach ( $emb_wl as $emb_cfg ) {
	if ( ! array_key_exists( 'emblem', $emb_cfg ) ) {
		$emb_sans_cle[] = $emb_cfg['id'];
	}
}
AOD_Test::ok(
	empty( $emb_sans_cle ),
	'Chaque white-label EcoTrack déclare sa clé `emblem` (' . count( $emb_wl ) . ' transporteurs)',
	'sans clé — ils porteraient l\'emblème d\'EcoTrack : ' . implode( ', ', $emb_sans_cle )
);

// L'EcoTrack générique, lui, garde bien le sien : c'est la seule instance
// construite sans config, et le défaut est écrit pour elle.
AOD_Test::eq( 'route', $emb_carriers['ecotrack']->emblem(), 'EcoTrack générique porte son propre emblème' );

// Recensement : un emblème dessiné mais attribué à personne est du code mort qui
// se relit comme une intention. Même doctrine que le recensement des tailles de
// texte (46.5bis) : on ne l'interdit pas, on le NOMME.
$emb_utilises = array();
foreach ( $emb_carriers as $emb_c ) {
	if ( '' !== $emb_c->emblem() ) {
		$emb_utilises[ $emb_c->emblem() ] = true;
	}
}
$emb_orphelins = array_values( array_diff( array_keys( $emb_catalogue ), array_keys( $emb_utilises ) ) );
AOD_Test::ok(
	empty( $emb_orphelins ),
	'Aucun emblème du catalogue n\'est orphelin (' . count( $emb_utilises ) . ' utilisés sur ' . count( $emb_catalogue ) . ')',
	'dessinés pour personne : ' . implode( ', ', $emb_orphelins )
);

/* ------------------------------------------------------------------
 * R3 · LE DÉGRADÉ NE PEUT PAS FAIRE TOMBER L'ENCRE.
 *
 * C'est le contrôle qui compte. `brand_shade()` doit s'éloigner de l'encre, donc
 * le contraste sur la teinte d'appui est TOUJOURS ≥ celui sur la couleur pleine,
 * lui-même garanti ≥ 4,58:1 par `brand_ink()`. On mesure les 29, pas un témoin :
 * la faute ne se produirait que sur les marques claires.
 * ---------------------------------------------------------------- */
$emb_chute   = array();
$emb_sousaa  = array();
$emb_pireflat = 99;
$emb_pireshade = 99;
foreach ( $emb_carriers as $emb_id => $emb_c ) {
	$emb_ink   = $emb_c->brand_ink();
	$emb_plein = aod_test_emb_ratio( $emb_ink, $emb_c->brand_color() );
	$emb_appui = aod_test_emb_ratio( $emb_ink, $emb_c->brand_shade() );

	$emb_pireflat  = min( $emb_pireflat, $emb_plein );
	$emb_pireshade = min( $emb_pireshade, $emb_appui );

	// Tolérance de 0,01 : arrondi à l'entier de canal dans brand_shade().
	if ( $emb_appui < $emb_plein - 0.01 ) {
		$emb_chute[] = $emb_id . ' (' . round( $emb_plein, 2 ) . ' → ' . round( $emb_appui, 2 ) . ')';
	}
	if ( $emb_appui < 4.5 ) {
		$emb_sousaa[] = $emb_id . ' (' . round( $emb_appui, 2 ) . ':1)';
	}
}
AOD_Test::ok(
	empty( $emb_chute ),
	sprintf(
		'La teinte d\'appui s\'éloigne toujours de l\'encre — pire cas : %s:1 pleine, %s:1 en appui',
		number_format( $emb_pireflat, 2 ),
		number_format( $emb_pireshade, 2 )
	),
	'le dégradé DÉGRADE le contraste sur : ' . implode( ', ', $emb_chute )
);
AOD_Test::ok(
	empty( $emb_sousaa ),
	'Le monogramme tient AA sur les DEUX bouts du dégradé, pour les ' . count( $emb_carriers ) . ' transporteurs',
	'sous 4,5:1 : ' . implode( ', ', $emb_sousaa )
);

// Témoin nommé : l'ambre de Rocket Delivery est la marque la plus claire du
// registre, donc la seule où `brand_ink()` retient le NOIR — et donc la seule que
// l'assombrissement « naturel » aurait cassée. Sans ce témoin, R3 resterait vert
// même si le sens du décalage était figé dans le mauvais sens pour les 28 autres.
AOD_Test::eq( '#000000', $emb_carriers['rocket']->brand_ink(), 'Témoin : l\'ambre de Rocket Delivery prend l\'encre NOIRE' );
AOD_Test::ok(
	aod_test_emb_lum( $emb_carriers['rocket']->brand_shade() ) > aod_test_emb_lum( $emb_carriers['rocket']->brand_color() ),
	'Témoin : sur cette marque-là, la teinte d\'appui ÉCLAIRCIT (elle assombrirait partout ailleurs)'
);
// Témoin INVERSE. ⚠️ Il a d'abord été écrit sur Yalidine, et il a rougi : son
// rouge (#e63329) rend 4,87:1 avec le NOIR contre 4,31:1 avec le blanc, donc
// `brand_ink()` y retient le noir. Contre-intuitif sur un rouge vif, et c'est
// justement pourquoi le témoin doit nommer une marque dont l'encre est mesurée
// et non supposée : le bleu nuit de Maystro (#1e3a8a).
AOD_Test::eq( '#ffffff', $emb_carriers['maystro']->brand_ink(), 'Témoin inverse : le bleu nuit de Maystro prend l\'encre BLANCHE' );
AOD_Test::ok(
	aod_test_emb_lum( $emb_carriers['maystro']->brand_shade() ) < aod_test_emb_lum( $emb_carriers['maystro']->brand_color() ),
	'Témoin inverse : sur une marque à encre blanche, la teinte d\'appui ASSOMBRIT'
);

// Les deux sens sont-ils réellement exercés ? Si le registre n'avait qu'une seule
// encre, R3 serait vert en n'ayant éprouvé qu'une moitié de `brand_shade()`.
$emb_encres = array();
foreach ( $emb_carriers as $emb_c ) {
	$emb_encres[ $emb_c->brand_ink() ] = isset( $emb_encres[ $emb_c->brand_ink() ] ) ? $emb_encres[ $emb_c->brand_ink() ] + 1 : 1;
}
AOD_Test::ok(
	isset( $emb_encres['#000000'], $emb_encres['#ffffff'] ),
	sprintf(
		'Les deux sens du décalage sont exercés par le registre (%d marques à encre noire, %d à encre blanche)',
		isset( $emb_encres['#000000'] ) ? $emb_encres['#000000'] : 0,
		isset( $emb_encres['#ffffff'] ) ? $emb_encres['#ffffff'] : 0
	),
	'une seule encre au registre : R3 n\'éprouve alors qu\'une moitié de brand_shade()'
);

/* ------------------------------------------------------------------
 * R4 · LA PASTILLE REND CE QU'ON CROIT.
 * ---------------------------------------------------------------- */
$emb_html_star = $emb_carriers['negmar']->icon_html();
AOD_Test::ok( false !== strpos( $emb_html_star, '<svg' ), 'Un transporteur à emblème rend bien un SVG' );
AOD_Test::ok(
	false === strpos( $emb_html_star, '>NG<' ),
	'…et il remplace le monogramme au lieu de s\'y ajouter'
);
AOD_Test::ok(
	false !== strpos( $emb_carriers['tsl']->icon_html(), '>TSL<' ),
	'Un transporteur sans emblème garde son monogramme'
);

// `aria-hidden` sur la pastille ENTIÈRE : le nom du transporteur est le texte
// juste à côté. Avant, un lecteur d'écran annonçait « T S L  TSL Express ».
$emb_sans_aria = array();
foreach ( $emb_carriers as $emb_id => $emb_c ) {
	$emb_h = $emb_c->icon_html();
	if ( false === strpos( $emb_h, 'aria-hidden="true"' ) ) {
		$emb_sans_aria[] = $emb_id;
	}
	if ( false === strpos( $emb_h, '--ic-2:' ) ) {
		$emb_sans_aria[] = $emb_id . ' (sans teinte d\'appui)';
	}
}
AOD_Test::ok(
	empty( $emb_sans_aria ),
	'Chaque pastille est décorative (`aria-hidden`) et porte sa teinte d\'appui',
	implode( ', ', $emb_sans_aria )
);

/* ------------------------------------------------------------------
 * R5 · LA FEUILLE DE STYLE PORTE LE DÉGRADÉ ET LE FILET.
 *
 * Le PHP peut calculer une teinte d'appui parfaite que la CSS n'utilise pas : la
 * pastille resterait plate et R3 resterait vert. On lit donc la source.
 * ---------------------------------------------------------------- */
$emb_css = file_get_contents( AOD_CD_PATH . 'assets/css/dashboard.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- fichier local.
AOD_Test::ok(
	false !== strpos( $emb_css, 'linear-gradient(150deg,var(--ic-2,var(--ic,var(--cd-ink)))' ),
	'`.aod-carrier-ic` peint bien le dégradé calculé en PHP'
);
AOD_Test::ok(
	(bool) preg_match( '/\.aod-carrier-ic::after\{[^}]*border:1px solid currentColor/', $emb_css ),
	'Le filet intérieur prend `currentColor` — donc l\'encre, donc il survit aux marques claires'
);
AOD_Test::ok(
	(bool) preg_match( '/\.aod-carrier-ic svg\{[^}]*width:21px/', $emb_css ),
	'L\'emblème est borné dans la pastille de 38 px'
);
