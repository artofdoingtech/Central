<?php
/**
 * Le FOND DE PAGE dérivé de l'accent reste lisible (demande owner 2026-08-04).
 *
 * Depuis cette date, `--cd-bg` n'est plus un gris fixe : il prend la teinte de
 * l'accent choisi dans le Personnalisateur (`AOD_CD_Customize::BG_MIX`). Le
 * client demandait « du turquoise » ; l'écrire en dur aurait été juste chez lui
 * et faux chez le suivant.
 *
 * 🚨 CE FICHIER EXISTE PARCE QUE LA SUITE ÉTAIT VERTE SANS RIEN PROUVER. Au
 * moment d'ajouter cette dérivation, les 1732 tests passaient — mais aucun ne
 * connaissait `--cd-bg` teinté : la couleur venait de naître. *Une suite verte
 * ne dit rien d'un couple qu'elle n'a jamais regardé.* Le contrôle a donc été
 * fait à la main sur les six accents, puis écrit ici pour qu'il se rejoue seul.
 *
 * CE QUI EST ÉPROUVÉ : `--cd-muted` (le sous-titre présent sur chaque écran de
 * `/gestion`) sur le fond dérivé, pour les six accents que la recette navigateur
 * tient pour hostiles. Ce sont eux qui décident, pas le jaune du template — un
 * contraste validé sur l'accent par défaut, très clair, ne prouve rien.
 *
 * 🚨 CE QUE CE TEST NE PROUVE PAS : les autres couples (texte principal, anneaux
 * de focus, pastilles) restent du ressort de `test-contraste.php` et de
 * `bash tools/recette/run.sh contraste`, qui mesurent un RENDU. Ici on éprouve
 * une FORMULE — elle peut être juste pendant qu'une règle CSS la contredit.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Fond de page dérivé de l’accent — lisibilité AA' );

AOD_Test::ok(
	defined( 'AOD_CD_Customize::BG_MIX' ) || class_exists( 'AOD_CD_Customize' ),
	'TÉMOIN : AOD_CD_Customize est chargée'
);

/** Luminance relative WCAG d'un hex. */
function aod_fa_luminance( $hex ) {
	$h = ltrim( (string) $hex, '#' );
	$c = array(
		hexdec( substr( $h, 0, 2 ) ),
		hexdec( substr( $h, 2, 2 ) ),
		hexdec( substr( $h, 4, 2 ) ),
	);
	foreach ( $c as &$v ) {
		$v = $v / 255;
		$v = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/** Rapport de contraste WCAG entre deux hex. */
function aod_fa_ratio( $a, $b ) {
	$l1 = aod_fa_luminance( $a );
	$l2 = aod_fa_luminance( $b );
	if ( $l1 < $l2 ) {
		$t  = $l1;
		$l1 = $l2;
		$l2 = $t;
	}
	return ( $l1 + 0.05 ) / ( $l2 + 0.05 );
}

/**
 * Fond dérivé d'un accent — on passe par le VRAI code (réflexion sur les
 * helpers privés) et non par une copie de la formule : une copie diverge, et
 * le test resterait vert en éprouvant une règle que le produit n'applique plus.
 */
function aod_fa_bg( $accent ) {
	static $mix = null, $rgb = null, $hex = null;
	if ( null === $mix ) {
		$mix = new ReflectionMethod( 'AOD_CD_Customize', 'mix' );
		$rgb = new ReflectionMethod( 'AOD_CD_Customize', 'rgb' );
		$hex = new ReflectionMethod( 'AOD_CD_Customize', 'to_hex' );
		$mix->setAccessible( true );
		$rgb->setAccessible( true );
		$hex->setAccessible( true );
	}
	return $hex->invoke( null, $mix->invoke( null, $rgb->invoke( null, $accent ), 255, AOD_CD_Customize::BG_MIX ) );
}

// Les six accents de `tools/recette/run.sh` — choisis pour être HOSTILES.
// Le presque-noir est le pire cas : c'est lui qui borne `BG_MIX`.
$aod_fa_accents = array(
	'#FFE21E' => 'jaune (défaut du template)',
	'#0d9488' => 'turquoise',
	'#1878f0' => 'bleu de marque',
	'#7B1E3A' => 'bordeaux',
	'#ff0000' => 'rouge pur',
	'#111827' => 'presque-noir',
);

foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_bg    = aod_fa_bg( $aod_fa_hex );
	$aod_fa_ratio = aod_fa_ratio( '#666666', $aod_fa_bg );
	AOD_Test::ok(
		$aod_fa_ratio >= 4.5,
		sprintf( '🔴 %s → fond %s : texte secondaire à %.2f:1', $aod_fa_nom, $aod_fa_bg, $aod_fa_ratio ),
		'AA exige 4,5:1. Si ce contrôle rougit, c’est BG_MIX qu’il faut remonter (fond plus clair), pas ce seuil qu’il faut baisser.'
	);
}

/*
 * Le fond doit rester DISTINCT du blanc des cartes, sinon la page et ses cartes
 * se confondent — c'est le défaut que l'ombre au repos et ce fond corrigent
 * ensemble. Un `BG_MIX` qu'on remonterait pour gagner du contraste finirait par
 * rendre le fond blanc : ce contrôle est la contrepartie du précédent, et les
 * deux se tiennent. Sans lui, « corriger » un rouge de lisibilité effacerait la
 * séparation sans que rien ne le signale.
 */
foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_bg = aod_fa_bg( $aod_fa_hex );
	AOD_Test::ok(
		strtolower( $aod_fa_bg ) !== '#ffffff',
		sprintf( '🔴 %s : le fond (%s) n’est pas blanc — les cartes doivent s’en détacher', $aod_fa_nom, $aod_fa_bg )
	);
}
