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
function aod_fa_surface( $accent, $taux ) {
	static $mix = null, $rgb = null, $hex = null;
	if ( null === $mix ) {
		$mix = new ReflectionMethod( 'AOD_CD_Customize', 'mix' );
		$rgb = new ReflectionMethod( 'AOD_CD_Customize', 'rgb' );
		$hex = new ReflectionMethod( 'AOD_CD_Customize', 'to_hex' );
		$mix->setAccessible( true );
		$rgb->setAccessible( true );
		$hex->setAccessible( true );
	}
	return $hex->invoke( null, $mix->invoke( null, $rgb->invoke( null, $accent ), 255, $taux ) );
}

/** Fond de PAGE dérivé d'un accent. */
function aod_fa_bg( $accent ) {
	return aod_fa_surface( $accent, AOD_CD_Customize::BG_MIX );
}

/** Surface des CARTES dérivée d'un accent (2026-08-04, cf. CARD_MIX). */
function aod_fa_card( $accent ) {
	return aod_fa_surface( $accent, AOD_CD_Customize::CARD_MIX );
}

/**
 * Tous les tokens que `css_vars()` produit pour un accent donné, lus DANS SA SORTIE.
 *
 * 🚨 Pourquoi passer par là plutôt que par la réflexion, comme le reste du fichier :
 * `$strong` et son encre ne sont pas des helpers, ils sont calculés EN LIGNE dans
 * `css_vars()`. Les recalculer ici serait une copie de la formule — précisément ce
 * que `side()` interdit en toutes lettres, parce qu'une copie ne voit ni les
 * garde-fous ni ce qu'on ajoutera demain. On règle donc l'accent, on interroge le
 * vrai code, et on relit sa sortie. (Même geste que `test-charte-surfaces.php`.)
 *
 * @param string $accent Accent à éprouver.
 * @return array<string,string> Tokens hex, sans le préfixe `--cd-`.
 */
function aod_fa_vars( $accent ) {
	$avant = get_theme_mod( 'aod_cd_accent', null );
	set_theme_mod( 'aod_cd_accent', $accent );
	$css = AOD_CD_Customize::css_vars();
	if ( null === $avant ) {
		remove_theme_mod( 'aod_cd_accent' );
	} else {
		set_theme_mod( 'aod_cd_accent', $avant );
	}

	$out = array();
	if ( preg_match_all( '/--cd-([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{6})/', $css, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $set ) {
			$out[ $set[1] ] = strtolower( $set[2] );
		}
	}
	return $out;
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

AOD_Test::group( 'Surface des cartes dérivée de l’accent — lisibilité AA (2026-08-04)' );

/*
 * 🚨 MÊME MOTIF QUE LE FOND, ET C'EST BIEN LE PROBLÈME : la veille, `--cd-bg` est
 * devenu dérivé et ce fichier est né pour le tenir. Le lendemain, `--cd-card` a
 * suivi — et rien ici ne le regardait. *Un test écrit pour un token ne couvre pas
 * son jumeau, même quand les deux sortent de la même formule.*
 *
 * Trois contrôles, et ils forment un système fermé : chacun interdit la sortie
 * facile du précédent.
 *   1. AA sous `--cd-muted` — on peut y répondre en éclaircissant la carte…
 *   2. …donc la carte ne doit pas devenir blanche, sinon on retombe sur la demande
 *      owner qui a créé ce token (« toutes les petites sections sont blanches ») ;
 *   3. …et elle doit rester STRICTEMENT plus claire que le fond, sinon la carte
 *      cesse de se lire comme posée dessus. C'est cette borne-là qui fixe le
 *      couloir : `BG_MIX < CARD_MIX < 1`.
 */
foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_card  = aod_fa_card( $aod_fa_hex );
	$aod_fa_rcard = aod_fa_ratio( '#666666', $aod_fa_card );
	AOD_Test::ok(
		$aod_fa_rcard >= 4.5,
		sprintf( '🔴 %s → carte %s : texte secondaire à %.2f:1', $aod_fa_nom, $aod_fa_card, $aod_fa_rcard ),
		'AA exige 4,5:1. Remonter CARD_MIX (carte plus claire) — jamais baisser ce seuil.'
	);
}

foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_card = aod_fa_card( $aod_fa_hex );
	AOD_Test::ok(
		strtolower( $aod_fa_card ) !== '#ffffff',
		sprintf( '🔴 %s : la carte (%s) n’est pas blanche — c’est la demande qui a créé ce token', $aod_fa_nom, $aod_fa_card )
	);
}

foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_lb = aod_fa_luminance( aod_fa_bg( $aod_fa_hex ) );
	$aod_fa_lc = aod_fa_luminance( aod_fa_card( $aod_fa_hex ) );
	AOD_Test::ok(
		$aod_fa_lc > $aod_fa_lb,
		sprintf(
			'🔴 %s : la carte (%s) est plus claire que le fond (%s) — elle doit se lire posée dessus',
			$aod_fa_nom,
			aod_fa_card( $aod_fa_hex ),
			aod_fa_bg( $aod_fa_hex )
		),
		'CARD_MIX est passé sous BG_MIX : la carte s’enfonce dans la page au lieu de s’en détacher.'
	);
}

/*
 * Témoin du SENS (règle du témoin, pt 5). Les trois boucles ci-dessus sont vertes
 * aujourd'hui ; si `aod_fa_surface()` rendait n'importe quoi — un helper renommé,
 * une réflexion qui échoue — elles resteraient vertes en ne mesurant rien. On
 * ancre donc la formule sur un cas connu et calculé à la main : l'accent par
 * défaut du template, dont la carte est le repli inscrit dans `dashboard.css`.
 */
AOD_Test::eq(
	'#fffef5',
	strtolower( aod_fa_card( '#FFE21E' ) ),
	'TÉMOIN : la formule rend bien la carte de l’accent par défaut (celle inscrite en repli dans dashboard.css)'
);

AOD_Test::group( 'Survol du bouton principal — l’accent renforcé a SON encre (2026-08-05)' );

/*
 * 🚨 CE CONTRÔLE EST NÉ D'UN DÉFAUT QUE PERSONNE NE POUVAIT VOIR. `--cd-accent-strong`
 * existait depuis des phases, calculé à chaque page et lu par AUCUNE règle — le dépôt
 * l'avait consigné (« un manque de design », `test-charte-surfaces.php` R4) sans en
 * chercher la cause. La cause était un piège : lui donner un lecteur exigeait une
 * encre, et réutiliser `--cd-accent-ink` — calibrée sur l'accent de BASE — casse AA
 * sur la variante.
 *
 * Le mécanisme est mécanique, pas accidentel : `best_ink()` bascule du noir au blanc
 * vers une luminance de ~0,179, et `$strong` DÉPLACE la luminance (−12 % vers le noir
 * pour un accent clair, +18 % vers le blanc pour un accent sombre). La variante peut
 * donc franchir le seuil que la base n'a pas franchi.
 *
 * 🔴 Mesuré à l'écriture : 4 accents sur 8 tombaient sous AA avec l'encre de base —
 * 4,48 pour le turquoise (l'accent de CE client), 4,17 pour le rouge pur, 4,25 pour un
 * vert moyen, 4,36 pour un bleu-gris. Et le défaut ne se voyait qu'AU SURVOL : aucune
 * capture d'écran au repos ne pouvait le montrer, aucune relecture non plus.
 *
 * Les deux accents supplémentaires ne sont pas décoratifs : ce sont ceux dont la
 * luminance encadre le seuil de bascule. Sans eux, les six accents de la recette
 * laissaient passer la moitié du problème.
 */
$aod_fa_strong = array_merge(
	$aod_fa_accents,
	array(
		'#2f6b3a' => 'vert moyen (encadre le seuil de bascule)',
		'#4a5a8a' => 'bleu-gris (idem)',
	)
);

foreach ( $aod_fa_strong as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_vars = aod_fa_vars( $aod_fa_hex );
	$aod_fa_rs   = aod_fa_ratio( $aod_fa_vars['accent-strong'], $aod_fa_vars['accent-strong-ink'] );
	AOD_Test::ok(
		$aod_fa_rs >= 4.5,
		sprintf(
			'🔴 %s → survol %s / encre %s : %.2f:1',
			$aod_fa_nom,
			$aod_fa_vars['accent-strong'],
			$aod_fa_vars['accent-strong-ink'],
			$aod_fa_rs
		),
		'L’encre du survol doit être choisie sur la luminance de --cd-accent-strong, jamais héritée de --cd-accent-ink.'
	);
}

/*
 * Le témoin qui donne son mordant au contrôle ci-dessus (règle du témoin pt 5) : il
 * REJOUE le défaut. Sur le turquoise du client, l'ancienne encre doit être vue comme
 * fautive. Sans lui, une future régression qui ferait retomber `strong_ink` sur
 * `--cd-accent-ink` passerait au vert sur les 8 accents… et la moitié serait fausse.
 */
$aod_fa_turq = aod_fa_vars( '#0d9488' );
AOD_Test::ok(
	aod_fa_ratio( $aod_fa_turq['accent-strong'], $aod_fa_turq['accent-ink'] ) < 4.5,
	sprintf(
		'TÉMOIN DE MISE AU ROUGE : l’ANCIENNE encre (%s, calibrée sur l’accent de base) est bien sous AA sur le survol turquoise (%.2f:1)',
		$aod_fa_turq['accent-ink'],
		aod_fa_ratio( $aod_fa_turq['accent-strong'], $aod_fa_turq['accent-ink'] )
	),
	'Le défaut ne se reproduit plus : soit la formule de $strong a changé, soit ce témoin ne mesure plus ce qu’il croit.'
);
