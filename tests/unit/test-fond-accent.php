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

/*
 * 🔁 `aod_fa_surface()` A ÉTÉ SUPPRIMÉ LE 2026-08-06, et sa disparition est le
 * correctif — pas un nettoyage. Il recomposait le mélange à la main (réflexion
 * sur `mix`/`rgb`/`to_hex`) pour éviter de recopier la formule ; c'était juste
 * tant que la formule ÉTAIT le mélange. Depuis que chaque cran passe par un
 * plancher de luminance, ce helper rendait la couleur d'AVANT relèvement — donc
 * une couleur que le produit n'affiche jamais, et un rapport de contraste
 * PESSIMISTE pour les accents sombres (4,21:1 mesuré là où l'écran sert 4,75:1).
 * 🚨 *Un helper qui reproduit une étape sur deux est plus dangereux qu'une copie
 * assumée : il porte le nom du vrai code et fait croire qu'il l'appelle.*
 * Les cinq accesseurs ci-dessous appellent la classe, sans intermédiaire.
 */

/** Surface CREUSÉE dans les cartes, dérivée d'un accent (zébrures, encarts…). */
function aod_fa_bg( $accent ) {
	return AOD_CD_Customize::bg( $accent );
}

/**
 * Fond de l'APPLICATION dérivé d'un accent (2026-08-05, cf. PAGE_SAT / PAGE_LUM /
 * PAGE_MIN_LUM — `PAGE_MIX` et `PAGE_DESAT` ont vécu ici jusqu'au 2026-08-08).
 *
 * 🚨 On appelle la VRAIE méthode, pas `aod_fa_surface()`. `page()` ne se réduit pas
 * à un mélange : elle relève ensuite le résultat par paliers jusqu'au plancher de
 * lisibilité. Recopier le seul mélange rendrait ce fichier vert en mesurant une
 * couleur que le produit n'affiche jamais — pour l'accent presque-noir, 4,21:1 au
 * lieu des 4,72:1 réellement servis, donc un ROUGE inventé. Le sens inverse est
 * plus dangereux encore et c'est celui qu'on garde : si le plancher disparaissait
 * du code, ce test doit rougir, et il ne peut le faire qu'en lisant le vrai code.
 */
function aod_fa_page( $accent ) {
	return AOD_CD_Customize::page( $accent );
}

/** Surface des CARTES dérivée d'un accent (2026-08-04, cf. CARD_MIX). */
function aod_fa_card( $accent ) {
	return AOD_CD_Customize::card( $accent );
}

/** Surface des CHAMPS de saisie — 5ᵉ cran (2026-08-06, cf. FIELD_MIX). */
function aod_fa_field( $accent ) {
	return AOD_CD_Customize::field( $accent );
}

/** Surface de SURVOL / ligne sélectionnée — 3ᵉ cran (cf. WASH_MIX). */
function aod_fa_wash( $accent ) {
	return AOD_CD_Customize::wash( $accent );
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

// Les accents de `tools/recette/run.sh` — choisis pour être HOSTILES.
// Le presque-noir est le pire cas : c'est lui qui borne `BG_MIX`.
// 🔁 `#FFE21E` A CESSÉ D'ÊTRE LE DÉFAUT LE 2026-08-08 (cf. DEFAULT_ACCENT) et il est
// GARDÉ quand même, sous son vrai titre : un accent très clair et très saturé reste
// le pire cas de plusieurs règles (l'anneau de focus, la lisibilité sur aplat).
// Le retirer parce qu'il n'est plus le défaut aurait fait PERDRE une couverture que
// le dépôt ne devait qu'au hasard du réglage d'usine. Le nouveau défaut s'y ajoute :
// l'accent réellement servi doit être éprouvé comme les autres, pas à sa place.
$aod_fa_accents = array(
	'#116048' => 'vert (défaut du template)',
	'#0E5C66' => 'pétrole (défaut le 2026-08-08 — gardé : un pétrole profond reste éprouvé)',
	'#FFE21E' => 'jaune très clair, très saturé',
	'#0d9488' => 'turquoise',
	'#1878f0' => 'bleu de marque',
	'#7B1E3A' => 'bordeaux',
	'#ff0000' => 'rouge pur',
	'#111827' => 'presque-noir',
);


/*
 * ═══════════════════════════════════════════════════════════════════════════
 * L'ÉCHELLE DE SURFACES (2026-08-06) — demande owner : « je ne veux pas voir de
 * blanc dedans ».
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * 🚨 CE BLOC REMPLACE SIX BOUCLES ÉCRITES SURFACE PAR SURFACE, et la raison
 * d'écrire un balayage à la place est celle que ce fichier avait déjà consignée
 * sans en tirer la conséquence : « un test écrit pour un token ne couvre pas son
 * jumeau, même quand les deux sortent de la même formule ». Le fichier est né
 * pour `--cd-bg`, `--cd-card` a été ajouté le lendemain, `--cd-page` deux jours
 * après, `--cd-field` aujourd'hui — chaque fois en recopiant la boucle
 * précédente. Le cinquième cran serait entré de la même façon, et le sixième
 * aurait fini par ne pas entrer du tout. La table ci-dessous est maintenant le
 * seul endroit à mettre à jour.
 *
 * ⚠️ LE GRIS N'EST PLUS ÉCRIT ICI. `#666666` était recopié dans chaque
 * assertion ; il vaut #4f4f4f depuis que l'échelle est descendue, et un test qui
 * mesure l'ancien gris rendrait des rapports que personne n'a sous les yeux —
 * trop OPTIMISTES, donc verts à tort. On le lit dans la feuille : la mesure
 * porte alors sur le couple réellement servi.
 */
$aod_fa_css = (string) file_get_contents( AOD_CD_PATH . 'assets/css/dashboard.css' );

/** Valeur d'un token de couleur, lue dans le `:root` de la feuille. */
function aod_fa_token( $nom, $css ) {
	return preg_match( '/' . preg_quote( $nom, '/' ) . '\s*:\s*(#[0-9a-fA-F]{6})/', $css, $m )
		? strtolower( $m[1] ) : '';
}

/*
 * 🔴 LES TROIS ENCRES, ET LA TROISIÈME EST ARRIVÉE PAR UN ROUGE DE LA RECETTE.
 * Ce contrôle n'a longtemps mesuré que `--cd-muted`, au motif — écrit dans le
 * code — que c'était « lui qui fixe le plancher ». C'était vrai des surfaces
 * quasi blanches d'avant l'échelle. Une fois les crans descendus, ce sont les
 * encres SÉMANTIQUES qui décident : `--cd-ok` (#146c2e) et `--cd-bad` (#b42318)
 * ont une luminance de 0,11 contre 0,08 pour le gris, donc elles exigent une
 * surface PLUS CLAIRE que lui. Mesuré le 2026-08-06 : le prix vert de la page
 * Produits tombait à **4,44:1** sur le creux des cartes, pour trois accents sur
 * six — et ce fichier était vert.
 * 🚨 *Un cliquet qui mesure « l'encre la plus contraignante » doit remesurer
 * LAQUELLE l'est à chaque fois que les surfaces bougent. Le nom du pire cas n'est
 * pas une propriété du produit, c'est le résultat d'une comparaison.*
 * Les trois y sont désormais, et c'est la comparaison qui tranche.
 */
$aod_fa_encres = array();
$aod_fa_vides  = array();
foreach ( array( '--cd-muted', '--cd-ok', '--cd-bad' ) as $aod_fa_e ) {
	$aod_fa_encres[ $aod_fa_e ] = aod_fa_token( $aod_fa_e, $aod_fa_css );
	if ( '' === $aod_fa_encres[ $aod_fa_e ] ) {
		$aod_fa_vides[] = $aod_fa_e;
	}
}
$aod_fa_muted = $aod_fa_encres['--cd-muted'];

AOD_Test::eq(
	'',
	implode( ' · ', $aod_fa_vides ),
	sprintf( 'TÉMOIN : les 3 encres sont lues dans la feuille (%s)', implode( ' ', $aod_fa_encres ) )
);

/*
 * Les cinq crans, DU PLUS PROFOND AU PLUS CLAIR. L'ordre de ce tableau EST le
 * contrat : c'est lui que la monotonie ci-dessous vérifie.
 */
$aod_fa_crans = array(
	'fond d’application' => 'aod_fa_page',
	'creux des cartes'   => 'aod_fa_bg',
	'survol / sélection' => 'aod_fa_wash',
	'carte'              => 'aod_fa_card',
	'champ de saisie'    => 'aod_fa_field',
);

/*
 * R1 — chaque cran porte le texte secondaire à AA, pour chaque accent hostile.
 *
 * 🚨 C'est la contrepartie du plancher de luminance, pas sa redite. Les
 * `*_MIN_LUM` énoncent une intention ; ici on mesure le RAPPORT obtenu après le
 * relèvement par paliers. Une erreur de facteur dans la déduction d'un plancher
 * se lit juste et rougit ici.
 */
foreach ( $aod_fa_crans as $aod_fa_role => $aod_fa_fn ) {
	foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
		$aod_fa_s    = call_user_func( $aod_fa_fn, $aod_fa_hex );
		$aod_fa_pire = 21.0;
		$aod_fa_qui  = '';
		foreach ( $aod_fa_encres as $aod_fa_en => $aod_fa_val ) {
			// ⚠️ Le fond d'APPLICATION est exempté des encres sémantiques, et c'est un
			// arbitrage, pas un oubli : aucune ne s'y pose (mesuré par `run.sh
			// contraste`, qui balaie les 12 sections). L'y soumettre forcerait son
			// plancher au niveau du creux — les deux se rejoindraient, et la demande
			// « un fond plus foncé » serait annulée par une contrainte sans objet.
			if ( 'aod_fa_page' === $aod_fa_fn && '--cd-muted' !== $aod_fa_en ) {
				continue;
			}
			$aod_fa_r = aod_fa_ratio( $aod_fa_val, $aod_fa_s );
			if ( $aod_fa_r < $aod_fa_pire ) {
				$aod_fa_pire = $aod_fa_r;
				$aod_fa_qui  = $aod_fa_en;
			}
		}
		AOD_Test::ok(
			$aod_fa_pire >= 4.5,
			sprintf( '🔴 %s · %s → %s : pire encre %s à %.2f:1', $aod_fa_role, $aod_fa_nom, $aod_fa_s, $aod_fa_qui, $aod_fa_pire ),
			'AA exige 4,5:1. Remonter le plancher du cran (surface plus claire) — jamais baisser ce seuil, jamais éclaircir une encre pour y répondre.'
		);
	}
}

/*
 * R2 — l'échelle est STRICTEMENT ordonnée, pour chaque accent.
 *
 * Sans ce contrôle, R1 se « corrige » en éclaircissant : on remonterait les crans
 * jusqu'à ce qu'ils se rejoignent en haut, et l'écran redeviendrait le mur blanc
 * que la demande owner a retiré. C'est cette borne qui tient la structure — la
 * profondeur se lit à la teinte, donc les teintes doivent différer.
 *
 * ⚠️ Le cas qui rend ce contrôle indispensable est l'accent SOMBRE : les planchers
 * y mordent tous en même temps, et des planchers mal espacés écraseraient les
 * surfaces sur une seule valeur — en gris, cette fois.
 *
 * 🔁 LE FOND D'APPLICATION EST SORTI DE CETTE ÉCHELLE LE 2026-08-07, ET C'EST UN
 * CHANGEMENT DE CONTRAT — à lire avant de « réparer » quoi que ce soit ici.
 *
 * Ce qui l'a révélé : l'owner a arbitré un fond plus clair (`PAGE_MIX`, alors en vigueur, 0,70 →
 * 0,80), et R2 a rougi sur trois accents. Le cliquet avait raison sur le fait —
 * 0,80 dépasse `BG_MIX` (0,78), donc le fond ne PEUT arithmétiquement plus être le
 * cran le plus profond — mais la question qu'il posait n'était pas la bonne.
 *
 * 🚨 `--cd-page` N'A JAMAIS EU D'ARGUMENT POUR ÊTRE DANS CETTE ÉCHELLE : il y est
 * entré le 2026-08-05 en se séparant de `--cd-bg`, et il a hérité de
 * l'appartenance de son jumeau au lieu de la mériter. Or les deux ne se touchent
 * NULLE PART — la note de `dashboard.css` l'écrit noir sur blanc (« `--cd-page` ne
 * se lit que sous l'application ; s'il apparaît dans une carte, les deux rôles
 * recommencent à se confondre »). Comparer la profondeur de deux surfaces qui ne
 * sont jamais adjacentes, c'est garder un rapport que personne ne peut voir.
 * *Un cliquet hérité par filiation, et non par raison, mesure la généalogie d'un
 * token plutôt que ce qu'il fait à l'écran.*
 *
 * ⚠️ CE N'EST PAS UN RELÂCHEMENT, et R2bis existe pour que ça n'en devienne pas
 * un : le rapport RÉELLEMENT visible — le fond est plus foncé que les cartes
 * posées dessus — n'était jusqu'ici garanti que par transitivité, donc jamais
 * asserté pour lui-même. Il l'est maintenant, et il porte sur le seul couple que
 * l'œil rencontre vraiment.
 */
$aod_fa_echelle = array_diff_key( $aod_fa_crans, array( 'fond d’application' => 1 ) );

foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_suite = array();
	$aod_fa_ok    = true;
	$aod_fa_prec  = -1.0;
	foreach ( $aod_fa_echelle as $aod_fa_role => $aod_fa_fn ) {
		$aod_fa_s       = call_user_func( $aod_fa_fn, $aod_fa_hex );
		$aod_fa_l       = aod_fa_luminance( $aod_fa_s );
		$aod_fa_suite[] = $aod_fa_s;
		if ( $aod_fa_l <= $aod_fa_prec ) {
			$aod_fa_ok = false;
		}
		$aod_fa_prec = $aod_fa_l;
	}
	AOD_Test::ok(
		$aod_fa_ok,
		sprintf( '🔴 %s : les %d crans des CARTES montent strictement — %s', $aod_fa_nom, count( $aod_fa_echelle ), implode( ' → ', $aod_fa_suite ) ),
		'Deux crans se sont rejoints ou inversés : la profondeur ne se lit plus à la teinte. Espacer les planchers, pas les supprimer.'
	);
}

/*
 * R2bis — LE seul rapport de profondeur que l'œil rencontre : une carte est posée
 * SUR le fond d'application, donc elle doit être plus claire que lui.
 *
 * 🚨 C'est ce contrôle qui empêche R2 amputé d'être un trou. Sans lui, remonter
 * la clarté du fond jusqu'à le confondre avec les cartes ne ferait rougir personne :
 * les cartes cesseraient de se détacher, l'écran redeviendrait plat, et les quatre
 * crans internes continueraient de monter sagement chacun de leur côté.
 *
 * ⚠️ C'est aussi ce qui tient la demande du 2026-08-06 (« je ne veux pas voir de
 * blanc dedans ») maintenant que la clarté du fond a le droit de remonter : ce n'est plus
 * la valeur du taux qui la garantit, c'est cette inégalité-ci. Mesuré à
 * l'écriture, la marge la plus serrée est celle du rouge pur (0,759 contre 0,788).
 */
foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_pg = aod_fa_page( $aod_fa_hex );
	$aod_fa_cd = aod_fa_card( $aod_fa_hex );
	AOD_Test::ok(
		aod_fa_luminance( $aod_fa_pg ) < aod_fa_luminance( $aod_fa_cd ),
		sprintf(
			'🔴 R2bis — %s : le fond %s reste plus PROFOND que la carte %s (%.4f < %.4f)',
			$aod_fa_nom,
			$aod_fa_pg,
			$aod_fa_cd,
			aod_fa_luminance( $aod_fa_pg ),
			aod_fa_luminance( $aod_fa_cd )
		),
		'Les cartes ne se détachent plus du fond : l’écran est redevenu plat. Baisser PAGE_LUM — c’est LUI qui a bougé, pas la carte.'
	);
}

/*
 * R3 — aucun cran n'est blanc. C'est la demande owner, prise littéralement et
 * gardée : le blanc était la conséquence arithmétique d'un gris de texte, il
 * reviendrait sans bruit le jour où quelqu'un remonterait un plancher pour faire
 * taire R1.
 * 🔒 LE CRITÈRE EST LA DISTANCE AU BLANC SUR LE CANAL LE PLUS BAS, et pas la
 * luminance — une première version plafonnait la luminance à 0,97 et rougissait
 * sur `#fffdef`, le champ de l'accent JAUNE. À tort : ce crème est parfaitement
 * distinct sur une page `#fff6bc`. La luminance d'un jaune est intrinsèquement
 * haute (0,92 pour l'accent lui-même) ; la plafonner revient à interdire les
 * accents clairs, pas le blanc. Ce qui distingue une teinte du blanc, c'est
 * qu'AU MOINS UN canal s'en écarte — ici d'au moins 8 valeurs sur 255.
 * 🚨 *Un seuil qui rougit sur un cas juste finit désactivé (leçon D16) : c'est
 * le critère qu'il faut changer, pas le seuil.*
 */
foreach ( $aod_fa_crans as $aod_fa_role => $aod_fa_fn ) {
	foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
		$aod_fa_s   = call_user_func( $aod_fa_fn, $aod_fa_hex );
		$aod_fa_h   = ltrim( $aod_fa_s, '#' );
		$aod_fa_min = min(
			hexdec( substr( $aod_fa_h, 0, 2 ) ),
			hexdec( substr( $aod_fa_h, 2, 2 ) ),
			hexdec( substr( $aod_fa_h, 4, 2 ) )
		);
		AOD_Test::ok(
			( 255 - $aod_fa_min ) >= 8,
			sprintf( '🔴 %s · %s : %s n’est pas blanc (canal le plus bas à %d)', $aod_fa_role, $aod_fa_nom, $aod_fa_s, $aod_fa_min )
		);
	}
}

/*
 * Témoin du SENS (règle du témoin, pt 5). Les trois familles ci-dessus sont
 * vertes aujourd'hui ; si les accesseurs rendaient n'importe quoi — une méthode
 * renommée, un plancher qui avale tout — R1 et R3 resteraient verts en mesurant
 * du blanc, et R2 rougirait seul. On ancre donc la formule sur un cas connu :
 * l'accent par défaut du template, dont les crans sont les replis inscrits dans
 * `dashboard.css` (que `test-charte-surfaces.php` confronte, lui, à css_vars()).
 */
// 🔴 CE TÉMOIN A ÉTÉ RÉANCRÉ LE 2026-08-08, ET IL AVAIT LE DÉFAUT QU'IL DÉCRIT.
// Il annonçait « l'accent par défaut du template » en passant `#FFE21E` EN DUR.
// Le jour où DEFAULT_ACCENT a changé, il est resté vert — en mesurant une couleur
// que plus aucun écran ne sert, et sous une phrase devenue fausse. C'est mot pour
// mot le mode d'échec que le paragraphe ci-dessus lui confie d'empêcher.
// Il lit désormais la CONSTANTE ; l'attendu, lui, reste écrit à la main, et c'est
// ce qui en fait un témoin : changer l'accent par défaut le fait rougir tant que
// les replis de `dashboard.css` n'ont pas suivi.
AOD_Test::eq(
	'#cbebe2 → #ceded9 → #d4e2de → #e0eae7 → #eef4f2',
	strtolower(
		implode(
			' → ',
			array(
				aod_fa_page( AOD_CD_Customize::DEFAULT_ACCENT ),
				aod_fa_bg( AOD_CD_Customize::DEFAULT_ACCENT ),
				aod_fa_wash( AOD_CD_Customize::DEFAULT_ACCENT ),
				aod_fa_card( AOD_CD_Customize::DEFAULT_ACCENT ),
				aod_fa_field( AOD_CD_Customize::DEFAULT_ACCENT ),
			)
		)
	),
	'TÉMOIN : les 5 crans de l’accent par défaut sont ceux inscrits en repli dans dashboard.css'
);

AOD_Test::group( 'Fond d’application en DÉGRADÉ — les deux tons (2026-08-07)' );

/*
 * Le fond de `/gestion` n'est plus un aplat : `body.aod-cd` balaie de `--cd-page`
 * vers `--cd-page-2` (demande owner : « un background plus moderne, dégradé en
 * deux couleurs »). Deux choses peuvent se défaire en silence, et ce sont deux
 * échecs OPPOSÉS — d'où deux règles, pas une.
 *
 * 🚨 R4 tient la LISIBILITÉ, et c'est un trou que le fond plat ne pouvait pas
 * avoir. Tant qu'il y avait une seule couleur, la mesurer suffisait. Avec un
 * balayage, le texte se pose sur toute une plage de couleurs, et un cliquet qui
 * n'éprouve que le premier ton déclare le fond lisible en n'en ayant regardé que
 * la moitié — vert, et faux exactement là où on ne regarde pas.
 *
 * ⚠️ Comme R1, le second ton n'est éprouvé QUE sous `--cd-muted` : les encres
 * sémantiques ne se posent pas sur le fond d'application (cf. l'arbitrage de R1).
 * Ce n'est pas une clémence, c'est le même périmètre.
 */
foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_p2 = AOD_CD_Customize::page2( $aod_fa_hex );
	$aod_fa_r2 = aod_fa_ratio( $aod_fa_muted, $aod_fa_p2 );
	AOD_Test::ok(
		$aod_fa_r2 >= 4.5,
		sprintf( '🔴 R4 — second ton du fond · %s → %s : texte secondaire à %.2f:1', $aod_fa_nom, $aod_fa_p2, $aod_fa_r2 ),
		'AA exige 4,5:1 SUR TOUT le balayage. Remonter PAGE2_LUM (second ton plus clair) — jamais éclaircir l’encre, jamais baisser ce seuil.'
	);
}

/**
 * Teinte (0..360) d'un hex — MESURE du résultat, pas copie de la dérivation.
 *
 * ⚠️ Elle ne recompose PAS `page2()` : celle-ci a un accesseur et c'est lui qu'on
 * appelle (leçon de `soft()`, exposé le 2026-08-06 parce qu'un test le
 * recomposait et mesurait une couleur morte). Ici on lit une propriété de la
 * couleur RENDUE — comme `aod_fa_ratio()` lit un contraste.
 *
 * @param string $hex Couleur.
 * @return float Teinte en degrés ; 0 pour un gris (teinte indéfinie).
 */
function aod_fa_teinte( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	$r   = hexdec( substr( $hex, 0, 2 ) ) / 255;
	$g   = hexdec( substr( $hex, 2, 2 ) ) / 255;
	$b   = hexdec( substr( $hex, 4, 2 ) ) / 255;
	$max = max( $r, $g, $b );
	$d   = $max - min( $r, $g, $b );
	if ( $d <= 0.0 ) {
		return 0.0;
	}
	if ( $max === $r ) {
		$h = fmod( ( $g - $b ) / $d, 6.0 );
	} elseif ( $max === $g ) {
		$h = ( $b - $r ) / $d + 2.0;
	} else {
		$h = ( $r - $g ) / $d + 4.0;
	}
	return fmod( $h * 60.0 + 360.0, 360.0 );
}

/** Chroma d'un hex : écart entre le canal le plus fort et le plus faible (0..255). */
function aod_fa_chroma( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	$r   = hexdec( substr( $hex, 0, 2 ) );
	$g   = hexdec( substr( $hex, 2, 2 ) );
	$b   = hexdec( substr( $hex, 4, 2 ) );
	return max( $r, $g, $b ) - min( $r, $g, $b );
}

/** Plus grand écart de canal entre deux hex (0..255) — « ces deux couleurs se distinguent-elles ». */
function aod_fa_ecart( $a, $b ) {
	$a = ltrim( (string) $a, '#' );
	$b = ltrim( (string) $b, '#' );
	$d = 0;
	foreach ( array( 0, 2, 4 ) as $i ) {
		$d = max( $d, abs( hexdec( substr( $a, $i, 2 ) ) - hexdec( substr( $b, $i, 2 ) ) ) );
	}
	return $d;
}

/*
 * R5 — les deux tons sont VRAIMENT deux couleurs.
 *
 * 🚨 C'est la règle qui garde la DEMANDE, et sans elle R4 se satisfait tout seul :
 * la façon la plus simple de faire passer un contraste est de rapprocher le
 * second ton du premier, et à la limite de les confondre. Le fond serait alors
 * irréprochable, parfaitement lisible… et redevenu l'aplat que l'owner ne voulait
 * plus, sans qu'aucun cliquet ne bronche. *Un test de qualité qui ne s'accompagne
 * pas d'un test d'intention se paie en annulant la fonctionnalité.*
 *
 * 🔴 SA PREMIÈRE ÉCRITURE EXIGEAIT « teinte ≥ 15° ET clarté ≥ 0,01 », ET LA MESURE
 * L'A DÉMENTIE sur trois accents — bordeaux (0,002), rouge pur (0,006),
 * presque-noir (0,002). Le `ET` était faux, et il l'était pour une raison
 * STRUCTURELLE qu'il fallait voir : `PAGE_MIN_LUM` relève les deux tons jusqu'au
 * même plancher dès que l'accent est sombre, donc leur clarté se rejoint
 * NÉCESSAIREMENT. Exiger les deux revenait à demander au dégradé de démentir la
 * garantie de lisibilité qui le borne. Un dégradé à clarté constante reste un
 * dégradé — c'est même le plus élégant des deux.
 * 🚨 *Une assertion qui contredit une garantie du système ne signale pas un
 * défaut du produit : elle signale qu'on a écrit le contrat sans regarder ce que
 * le reste du système autorisait.*
 *
 * Le critère mesure donc ce que la demande dit vraiment — « on distingue deux
 * couleurs » — par le plus grand écart de canal entre les deux tons, avec le même
 * yardstick que R3 pour « ce n'est pas du blanc » : 8 valeurs sur 255.
 *
 * 🔁 ET SA DEUXIÈME ÉCRITURE PORTAIT UNE EXEMPTION QUI S'EST VIDÉE LE JOUR MÊME.
 * Elle dispensait les accents dont le fond dérivé était quasi neutre (chroma < 16)
 * — un seul cas alors, le presque-noir. L'apaisement du fond (alors `PAGE_DESAT`)
 * quelques heures plus tard, la chroma est tombée entre 0 et 13 : les six accents
 * passaient par l'exemption, et R5 ne gardait plus rien tout en affichant six
 * lignes vertes. 🚨 *Une exemption calibrée sur l'état du produit devient la règle
 * dès que le produit bouge — et elle est d'autant plus dangereuse qu'elle rend du
 * vert, pas du silence.* Elle est SUPPRIMÉE : le critère d'écart de canal, lui,
 * tient sur les neuf accents mesurés (11 à 18) sans avoir besoin de dispense.
 * Ce que l'exemption protégeait vraiment — la teinte, indéfinie sur un gris — est
 * passé dans R5bis, avec un témoin qui refuse de le laisser se vider à son tour.
 *
 * ⚠️ SEUL LE SEUIL EST LARGE, PAS LE PÉRIMÈTRE : il garde une intention, pas un
 * réglage. PAGE_SAT, PAGE_LUM, PAGE2_HUE, PAGE2_SAT et PAGE2_LUM doivent rester ajustables au goût
 * sans qu'il faille rouvrir ce fichier ; R5 rougit quand le dégradé DISPARAÎT, pas
 * quand il change.
 */
foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_t1 = AOD_CD_Customize::page( $aod_fa_hex );
	$aod_fa_t2 = AOD_CD_Customize::page2( $aod_fa_hex );
	AOD_Test::ok(
		aod_fa_ecart( $aod_fa_t1, $aod_fa_t2 ) >= 8
			&& aod_fa_luminance( $aod_fa_t1 ) > aod_fa_luminance( $aod_fa_t2 ),
		sprintf(
			'🔴 R5 — %s : %s → %s se distinguent (%d d’écart de canal) et le second est le plus profond',
			$aod_fa_nom,
			$aod_fa_t1,
			$aod_fa_t2,
			aod_fa_ecart( $aod_fa_t1, $aod_fa_t2 )
		),
		'Les deux tons se sont rejoints, ou le balayage s’est inversé. Écarter PAGE_LUM / PAGE2_LUM — ne pas relâcher ce seuil pour faire taire R4.'
	);
}

/*
 * R5bis — LA ROTATION DE TEINTE SERT ENCORE À QUELQUE CHOSE.
 *
 * 🚨 SANS CETTE RÈGLE, `PAGE2_HUE` ET `cooler()` SERAIENT DU CODE NON GARDÉ. R5
 * ci-dessus ne mesure qu'un écart de canal : on pourrait supprimer toute la
 * rotation, ne garder que l'écart de mélange, et il resterait vert sur les six
 * accents. C'est le mode d'échec que `--cd-accent-strong` a incarné pendant des
 * phases — un mécanisme calculé à chaque page et gardé par personne.
 *
 * ⚠️ ELLE NE PORTE QUE SUR LES ACCENTS DONT LE FOND GARDE ASSEZ DE COULEUR POUR
 * AVOIR UNE TEINTE (chroma ≥ 8), et ce périmètre est mesuré, pas choisi : depuis
 * que le fond est apaisé (2026-08-07, alors `PAGE_DESAT`), la chroma des tons tombe entre 0
 * et 13 selon l'accent. En dessous de 8, la teinte d'une couleur devient un
 * artefact d'arrondi — l'exiger reviendrait à mesurer du bruit et à rougir au
 * hasard des accents.
 * 🚨 *Le seuil suit ce que le produit rend, pas ce que le test aimerait mesurer —
 * mais il doit rester NON VIDE, sinon la règle est un décor.* D'où le témoin de
 * couverture juste en dessous.
 */
$aod_fa_r5b = 0;
foreach ( $aod_fa_accents as $aod_fa_hex => $aod_fa_nom ) {
	$aod_fa_t1 = AOD_CD_Customize::page( $aod_fa_hex );
	$aod_fa_t2 = AOD_CD_Customize::page2( $aod_fa_hex );
	if ( aod_fa_chroma( $aod_fa_t1 ) < 8 || aod_fa_chroma( $aod_fa_t2 ) < 8 ) {
		continue;
	}
	++$aod_fa_r5b;
	$aod_fa_dh = abs( aod_fa_teinte( $aod_fa_t1 ) - aod_fa_teinte( $aod_fa_t2 ) );
	$aod_fa_dh = min( $aod_fa_dh, 360.0 - $aod_fa_dh );
	AOD_Test::ok(
		$aod_fa_dh >= 15.0,
		sprintf( '🔴 R5bis — %s : %s → %s ont bien deux teintes (%.0f° d’écart)', $aod_fa_nom, $aod_fa_t1, $aod_fa_t2, $aod_fa_dh ),
		'la rotation vers le froid ne produit plus rien : PAGE2_HUE est à zéro, ou cooler() a cessé de tourner'
	);
}
AOD_Test::ok(
	$aod_fa_r5b >= 2,
	sprintf( 'TÉMOIN DE COUVERTURE : %d accent(s) portent assez de couleur pour éprouver la rotation', $aod_fa_r5b ),
	'plus aucun accent témoin n’a de teinte mesurable : R5bis ne garde plus rien — baisser PAGE_SAT a vidé la règle, il faut le voir'
);

/*
 * TÉMOIN DU SENS — l'accent GRIS, le cas où la rotation NE DOIT PAS s'appliquer,
 * et il est ici pour qu'on sache que c'est voulu. Un gris n'a pas de teinte : lui
 * en faire tourner une reviendrait à choisir une couleur à la place du client
 * (cf. COOL_HUE). Le dégradé devient un balayage de clarté, ce qui est le bon
 * comportement — mais il faut qu'un lecteur puisse le distinguer d'une panne.
 * 🚨 Sans ce témoin, le jour où `cooler()` cesserait de tourner pour TOUS les
 * accents, R5 rougirait six fois sans que rien ne dise que le cas gris, lui,
 * était normal depuis toujours.
 */
$aod_fa_gris1 = AOD_CD_Customize::page( '#808080' );
$aod_fa_gris2 = AOD_CD_Customize::page2( '#808080' );
AOD_Test::ok(
	0 === aod_fa_chroma( $aod_fa_gris2 )
		&& aod_fa_luminance( $aod_fa_gris1 ) > aod_fa_luminance( $aod_fa_gris2 ),
	sprintf( 'TÉMOIN : un accent GRIS ne se voit pas inventer de teinte — %s → %s, le dégradé reste un balayage de clarté', $aod_fa_gris1, $aod_fa_gris2 ),
	'soit la rotation s’applique à un gris (une couleur choisie à la place du client), soit le second ton a cessé d’être le plus profond'
);

/*
 * TÉMOIN — le repli inscrit dans `dashboard.css` pour l'accent par défaut, même
 * ancrage que celui des 5 crans juste au-dessus, et pour la même raison : c'est
 * lui qui empêche R4/R5 de rester verts en mesurant une couleur qui n'existe plus.
 */
AOD_Test::eq(
	'#cbebe2 → #b1cce2',
	strtolower( AOD_CD_Customize::page( AOD_CD_Customize::DEFAULT_ACCENT ) . ' → ' . AOD_CD_Customize::page2( AOD_CD_Customize::DEFAULT_ACCENT ) ),
	'TÉMOIN : les deux tons de l’accent par défaut sont ceux inscrits en repli dans dashboard.css'
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
