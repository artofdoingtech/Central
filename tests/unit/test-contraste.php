<?php
/**
 * Contraste AA de `/gestion` (Phase 5).
 *
 * L'accent est choisi LIBREMENT par le client dans le Personnalisateur : la
 * lisibilité ne peut donc pas être vérifiée à l'œil sur une couleur d'exemple,
 * elle doit tenir pour TOUTE couleur du cube RGB. C'est exactement ce que ce test
 * mesure — et c'est le genre de garantie qu'une recette visuelle ne peut pas
 * donner (la recette navigateur teste 5 accents ; ici on en balaie des milliers).
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ratio de contraste WCAG entre deux luminances.
 *
 * @param float $l1 Luminance.
 * @param float $l2 Luminance.
 * @return float
 */
function aod_test_ratio( $l1, $l2 ) {
	return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
}

/**
 * Luminance d'un hex, via la vraie implémentation du plugin.
 *
 * @param string $hex Couleur.
 * @return float
 */
function aod_test_lum_hex( $hex ) {
	$hex = ltrim( $hex, '#' );
	$rgb = array(
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
	);
	return AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( $rgb ) );
}

AOD_Test::group( 'Contraste — luminance de référence (sRGB)' );

AOD_Test::ok( abs( aod_test_lum_hex( '#000000' ) - 0.0 ) < 0.001, 'Le noir a une luminance de 0' );
AOD_Test::ok( abs( aod_test_lum_hex( '#ffffff' ) - 1.0 ) < 0.001, 'Le blanc a une luminance de 1' );
// Valeur de référence WCAG pour le gris moyen : 0.2158.
AOD_Test::ok( abs( aod_test_lum_hex( '#808080' ) - 0.2158 ) < 0.001, 'Le gris #808080 tombe sur la luminance WCAG de référence (0,2158)' );

AOD_Test::group( 'Contraste — best_ink() : le texte reste lisible sur TOUT accent' );

// Le plancher annoncé en commentaire du code est 4,58:1. On le vérifie sur un
// balayage du cube RGB (pas de 17, dont les cas cités : #1878f0 le bleu de marque
// banal, et le rouge pur).
//
// 🚨 Le compte est COMPTÉ, pas écrit. Ce message a longtemps annoncé « 4 913
// accents balayés » — le chiffre d'un pas de 16 valeurs sur 17 crans. La boucle,
// elle, en parcourt 16 par canal, donc 4 096 : la couverture était surévaluée de
// 20 % par une constante que plus personne ne recalculait. Un contrôle qui publie
// son périmètre doit le publier MESURÉ (DoD §C.2 pt 1), sinon il déplace le
// mensonge du résultat vers l'annonce.
$worst     = 21.0;
$worst_hex = '';
$n_accents = 0;
for ( $r = 0; $r <= 255; $r += 17 ) {
	for ( $g = 0; $g <= 255; $g += 17 ) {
		for ( $b = 0; $b <= 255; $b += 17 ) {
			++$n_accents;
			$lum   = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( array( $r, $g, $b ) ) );
			$ink   = AOD_Test::call_static( 'AOD_CD_Customize', 'best_ink', array( $lum ) );
			$ratio = aod_test_ratio( $lum, aod_test_lum_hex( $ink ) );
			if ( $ratio < $worst ) {
				$worst     = $ratio;
				$worst_hex = sprintf( '#%02x%02x%02x', $r, $g, $b );
			}
		}
	}
}
AOD_Test::ok(
	$worst >= 4.5,
	sprintf( 'Sur %d accents balayés, le pire contraste texte/bouton reste AA (%.2f:1, pire accent %s)', $n_accents, $worst, $worst_hex ),
	sprintf( 'plancher tombé à %.2f:1 sur %s', $worst, $worst_hex )
);

// Les deux couleurs nommées dans le commentaire du code comme les pires cas connus.
foreach ( array( '#1878f0', '#ff0000' ) as $hex ) {
	$lum   = aod_test_lum_hex( $hex );
	$ink   = AOD_Test::call_static( 'AOD_CD_Customize', 'best_ink', array( $lum ) );
	$ratio = aod_test_ratio( $lum, aod_test_lum_hex( $ink ) );
	AOD_Test::ok( $ratio >= 4.5, sprintf( 'Accent %s : encre %s, contraste %.2f:1 ≥ AA', $hex, $ink, $ratio ) );
}

AOD_Test::group( 'Contraste — readable_on() : accent lisible sur fond sombre' );

// La sidebar est sombre : l'accent y sert de couleur de TEXTE. readable_on() doit
// l'éclaircir jusqu'à 4,5:1 — quel que soit l'accent d'origine, même très foncé.
$dark = array( 24, 24, 24 ); // Le fond sombre de la coque.
$l_bg = AOD_Test::call_static( 'AOD_CD_Customize', 'luminance', array( $dark ) );

$worst_ro = 21.0;
$worst_ro_hex = '';
for ( $r = 0; $r <= 255; $r += 51 ) {
	for ( $g = 0; $g <= 255; $g += 51 ) {
		for ( $b = 0; $b <= 255; $b += 51 ) {
			$hex   = AOD_Test::call_static( 'AOD_CD_Customize', 'readable_on', array( array( $r, $g, $b ), $dark ) );
			$ratio = aod_test_ratio( aod_test_lum_hex( $hex ), $l_bg );
			if ( $ratio < $worst_ro ) {
				$worst_ro     = $ratio;
				$worst_ro_hex = sprintf( '#%02x%02x%02x', $r, $g, $b );
			}
		}
	}
}
AOD_Test::ok(
	$worst_ro >= 4.5,
	sprintf( 'Tout accent posé en texte sur la coque sombre est éclairci jusqu\'à AA (pire cas %.2f:1 depuis %s)', $worst_ro, $worst_ro_hex ),
	sprintf( 'plancher tombé à %.2f:1 depuis %s', $worst_ro, $worst_ro_hex )
);

// Une couleur DÉJÀ lisible ne doit pas être délavée au passage : la teinte du client
// doit survivre quand elle n'a rien à corriger.
$already = AOD_Test::call_static( 'AOD_CD_Customize', 'readable_on', array( array( 255, 255, 0 ), $dark ) );
AOD_Test::eq( '#ffff00', strtolower( $already ), 'Un jaune déjà lisible ressort inchangé (pas d\'éclaircissement gratuit)' );

AOD_Test::group( 'Contraste — wash() : les surfaces teintées portent du texte (Phase 17)' );

/*
 * Survol de ligne, ligne sélectionnée, ligne de transporteur dépliée, note du
 * gérant : quatre fonds qui se teintent de l'accent du client ET portent du texte.
 *
 * Ce qu'ils portent n'est PAS seulement `--cd-ink`. C'est tout l'intérêt du test :
 * `--cd-muted` (#666666) vit dans ces cellules — prix barré, coût, variantes,
 * numéro de suivi, date d'une note — et c'est LUI qui fixe le plancher. Une
 * vérification faite sur la seule encre principale aurait été verte à tort ;
 * c'est exactement l'erreur qui a laissé la note du gérant sous AA.
 *
 * On appelle wash(), on ne recopie PAS sa formule : une copie ne verrait ni le
 * taux réel, ni ce qu'on ajoutera demain (même règle que side()).
 */
$aod_encres = array(
	'--cd-ink'   => '#181818',
	'--cd-muted' => '#666666',
	'--cd-ok'    => '#146c2e',
	'--cd-bad'   => '#b42318',
);

$w_worst  = 21.0;
$w_accent = '';
$w_encre  = '';
$w_n      = 0;
for ( $r = 0; $r <= 255; $r += 17 ) {
	for ( $g = 0; $g <= 255; $g += 17 ) {
		for ( $b = 0; $b <= 255; $b += 17 ) {
			$acc  = sprintf( '#%02x%02x%02x', $r, $g, $b );
			$fond = aod_test_lum_hex( AOD_CD_Customize::wash( $acc ) );
			foreach ( $aod_encres as $nom => $encre ) {
				++$w_n;
				$ratio = aod_test_ratio( $fond, aod_test_lum_hex( $encre ) );
				if ( $ratio < $w_worst ) {
					$w_worst  = $ratio;
					$w_accent = $acc;
					$w_encre  = $nom;
				}
			}
		}
	}
}
AOD_Test::ok(
	$w_worst >= 4.5,
	sprintf(
		'Toute surface teintée reste AA pour tout accent — %d couples (accent × encre) mesurés, pire %.2f:1 (accent %s, encre %s)',
		$w_n,
		$w_worst,
		$w_accent,
		$w_encre
	),
	sprintf( 'plancher tombé à %.2f:1 (accent %s, encre %s)', $w_worst, $w_accent, $w_encre )
);

// Témoins nommés : le pire cas mathématique, les accents hostiles de la recette,
// le défaut du template, et le vert de référence de la documentation.
foreach ( array( '#000000', '#111827', '#7B1E3A', '#FFE21E', '#00A651' ) as $hex ) {
	$fond  = AOD_CD_Customize::wash( $hex );
	$ratio = aod_test_ratio( aod_test_lum_hex( $fond ), aod_test_lum_hex( '#666666' ) );
	AOD_Test::ok(
		$ratio >= 4.5,
		sprintf( 'Accent %s : surface %s, texte secondaire à %.2f:1 ≥ AA', $hex, $fond, $ratio )
	);
}

/*
 * Le témoin de la RAISON D'ÊTRE. `--cd-accent-soft` existait déjà : sans cette
 * assertion, rien n'expliquerait pourquoi on a ajouté un second token, et un
 * futur contributeur les fusionnerait de bonne foi. Elle échouerait le jour où
 * quelqu'un rapprocherait WASH_MIX de 0.86 — et c'est le but.
 */
$soft_noir = AOD_Test::call_static(
	'AOD_CD_Customize',
	'to_hex',
	array( AOD_Test::call_static( 'AOD_CD_Customize', 'mix', array( array( 0, 0, 0 ), 255, 0.86 ) ) )
);
$soft_ratio = aod_test_ratio( aod_test_lum_hex( $soft_noir ), aod_test_lum_hex( '#666666' ) );
AOD_Test::ok(
	$soft_ratio < 4.5,
	sprintf( 'Pourquoi -wash existe : -soft (%s) tombe à %.2f:1 sous du texte secondaire — insuffisant', $soft_noir, $soft_ratio ),
	'-soft passerait désormais AA : les deux tokens font doublon, en fusionner un'
);

/*
 * Bout en bout, à travers le vrai css_vars() : la valeur mesurée plus haut n'a
 * d'intérêt que si c'est bien celle qui atteint le navigateur. Même forme que le
 * contrôle de bout en bout de test-charte-sidebar.php.
 */
$aod_accent_avant = get_theme_mod( 'aod_cd_accent', null );
set_theme_mod( 'aod_cd_accent', '#7B1E3A' );
$aod_vars = AOD_CD_Customize::css_vars();
AOD_Test::ok(
	(bool) preg_match( '/--cd-accent-wash:(#[0-9a-fA-F]{6})/', $aod_vars, $aod_m ),
	'css_vars() publie bien --cd-accent-wash (sinon le CSS retombe sur son repli sans le dire)'
);
if ( ! empty( $aod_m[1] ) ) {
	AOD_Test::eq(
		strtolower( AOD_CD_Customize::wash( '#7B1E3A' ) ),
		strtolower( $aod_m[1] ),
		'La valeur injectée est exactement celle que wash() calcule (aucune dérive entre les deux)'
	);
}
if ( null === $aod_accent_avant ) {
	remove_theme_mod( 'aod_cd_accent' );
} else {
	set_theme_mod( 'aod_cd_accent', $aod_accent_avant );
}
AOD_Test::eq(
	(string) $aod_accent_avant,
	(string) get_theme_mod( 'aod_cd_accent', null ),
	'L\'accent du clone est rendu intact (un test ne repeint pas la boutique du gérant)'
);

/* =========================================================================
 * Couleurs de marque des régies — « Pixels & Tracking » (Phase 41)
 *
 * Les cartes de régie portent leur couleur de marque UNIVERSELLE (Meta bleu,
 * TikTok noir, Snapchat jaune, Google rouge — cf. A.18 / ICONES §6.4). L'enjeu :
 * le GLYPHE posé dessus doit rester lisible. Le réflexe « blanc sur la marque »
 * ÉCHOUE AA (blanc sur le rouge Google = 3,92:1, sur le bleu Meta = 4,23:1) — d'où
 * l'encre `best_ink()`, la même qui garde l'accent client. Ce contrôle lit le VRAI
 * `dashboard.css` (pas une copie) et prouve deux choses par marque :
 *   1. l'encre déclarée en CSS EST celle que `best_ink()` calcule ;
 *   2. le couple marque/encre franchit AA (≥ 4,5).
 * Sans (1), une encre « jolie » mais fausse passerait tant que (2) tient par
 * chance ; sans (2), best_ink() lui-même serait la référence non vérifiée.
 * ========================================================================= */

AOD_Test::group( 'Contraste — couleurs de marque des régies (Phase 41)' );

$aod_mk_css_file = dirname( ( new ReflectionClass( 'AOD_CD_Customize' ) )->getFileName(), 2 ) . '/assets/css/dashboard.css';
$aod_mk_css      = is_readable( $aod_mk_css_file ) ? (string) file_get_contents( $aod_mk_css_file ) : '';
AOD_Test::ok(
	'' !== $aod_mk_css,
	'TÉMOIN : dashboard.css est lisible pour auditer le catalogue de marque',
	'introuvable — ce contrôle auditerait une copie, pas le CSS servi'
);

$aod_mk_brands = array( 'meta', 'tiktok', 'snapchat', 'google' );
$aod_mk_seen   = 0;
foreach ( $aod_mk_brands as $aod_mk_b ) {
	// .aod-cd-pxcard--meta{--px-brand:#1877f2;--px-brand-ink:#000000;--px-brand-soft:#e9f1fe}
	if ( ! preg_match( '/\.aod-cd-pxcard--' . $aod_mk_b . '\b[^{]*\{[^}]*--px-brand:\s*(#[0-9a-fA-F]{6})[^}]*--px-brand-ink:\s*(#[0-9a-fA-F]{6})/', $aod_mk_css, $aod_mk_m ) ) {
		AOD_Test::ok( false, sprintf( 'La carte de marque « %s » déclare --px-brand ET --px-brand-ink dans dashboard.css', $aod_mk_b ) );
		continue;
	}
	$aod_mk_seen++;
	$aod_mk_brand = strtolower( $aod_mk_m[1] );
	$aod_mk_ink   = strtolower( $aod_mk_m[2] );

	// 1) L'encre déclarée = best_ink() de la marque (AA garanti par construction).
	$aod_mk_exp = strtolower( AOD_Test::call_static( 'AOD_CD_Customize', 'best_ink', array( aod_test_lum_hex( $aod_mk_brand ) ) ) );
	AOD_Test::ok(
		$aod_mk_ink === $aod_mk_exp,
		sprintf( '%s : l\'encre CSS (%s) = best_ink() de la marque (%s)', $aod_mk_b, $aod_mk_ink, $aod_mk_exp ),
		'l\'encre déclarée n\'est pas celle que best_ink() calcule — AA non garanti (le blanc « de marque » échoue souvent)'
	);

	// 2) Le couple marque/encre franchit AA.
	$aod_mk_ratio = aod_test_ratio( aod_test_lum_hex( $aod_mk_brand ), aod_test_lum_hex( $aod_mk_ink ) );
	AOD_Test::ok(
		$aod_mk_ratio >= 4.5,
		sprintf( '%s : contraste marque/encre = %.2f:1 (≥ 4,5 AA)', $aod_mk_b, $aod_mk_ratio ),
		'le glyphe est illisible sur la couleur de marque'
	);
}
AOD_Test::ok(
	4 === $aod_mk_seen,
	sprintf( 'TÉMOIN : %d/4 cartes de marque auditées', $aod_mk_seen ),
	'une marque a disparu du CSS : le catalogue n\'est plus couvert en entier'
);
