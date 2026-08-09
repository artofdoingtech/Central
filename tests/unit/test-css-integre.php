<?php
/**
 * Une feuille de style CASSÉE ne doit plus passer pour verte (2026-08-09).
 *
 * 🚨 POURQUOI CE FICHIER EXISTE. En écrivant la refonte de « Pixels & Tracking »,
 * une FERMETURE de commentaire (étoile-barre) de trop s'est glissée au milieu
 * d'un commentaire de `dashboard.css` :
 *
 *     … aurait été le défaut inverse. ⟨fermeture⟩
 *        ⚠️ `.aod-cd-form input.aod-cd-code` et non `.aod-cd-code` : … ⟨fermeture⟩
 *     .aod-cd-form input.aod-cd-code{ font-family:var(--cd-mono); … }
 *
 * (Les marqueurs sont écrits en toutes lettres ici : les poser tels quels
 * refermerait CE bloc — la démonstration se saborderait elle-même, ce qu'elle a
 * d'ailleurs fait au premier jet.)
 *
 * Le commentaire se refermait quatre lignes trop tôt ; sa fin devenait du texte
 * nu, et le navigateur jetait en silence la règle qui suivait. Les champs
 * restaient en Barlow au lieu de passer en chasse fixe — sans erreur, sans
 * console, sans rien.
 *
 * 🔴 CE QUI EST GRAVE N'EST PAS LA COQUILLE, C'EST QUI L'A TROUVÉE. Les 1918
 * tests étaient verts. Les trois portes navigateur étaient vertes. Le défaut n'a
 * été vu que parce qu'une mesure ad hoc relevait `getComputedStyle().fontFamily`
 * sur ces champs-là, ce jour-là. Une feuille de 6 000 lignes peut donc perdre
 * une règle — ou cent, si le déséquilibre tombe plus haut — sans qu'aucun
 * contrôle du dépôt ne bronche. C'est le trou, et c'est lui qu'on ferme ici.
 *
 * Le contrôle est délibérément ÉLÉMENTAIRE : il ne valide pas le CSS (il n'y a
 * pas de parseur ici, et il n'en faut pas). Il vérifie deux invariants qu'aucune
 * feuille correcte ne viole, et dont la violation détruit tout ce qui suit :
 *
 *   R1 — les marqueurs de commentaire s'apparient dans l'ORDRE. Un `⟨fermeture⟩` qui
 *        n'ouvre rien, ou un `/*` jamais refermé, sont tous deux fatals.
 *   R2 — les accolades s'équilibrent, commentaires exclus.
 *
 * ⚠️ R1 doit être SÉQUENTIEL, pas un simple comptage. Le défaut du jour avait
 * autant de `/*` que de `⟨fermeture⟩` — deux ouvrants, deux fermants — et un comptage
 * l'aurait déclaré sain. C'est l'ORDRE qui était faux.
 *
 * ⚠️ Et il faut le prouver au ROUGE. R3 fait tourner le scanner sur trois
 * feuilles fabriquées (une saine, une à fermeture orpheline, une à commentaire
 * ouvert) : sans cela, un scanner devenu aveugle rendrait « 0 anomalie » sur
 * tout, c'est-à-dire le vert par accident que ce fichier existe pour interdire.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parcourt une feuille caractère par caractère et rend ses anomalies.
 *
 * Les chaînes (`"…"`, `'…'`) sont traversées pour qu'un `/*` cité — dans un
 * `content:"/*"` ou une URL de données — ne soit pas pris pour un commentaire.
 *
 * ⚠️ Préfixe `aod_cssint_` : `tests/all.php` charge tous les fichiers par
 * `glob()` dans le même espace global ; un nom générique provoque un
 * `Cannot redeclare` (c'est déjà arrivé, cf. test-charte-surfaces.php).
 *
 * @param string $css Contenu de la feuille.
 * @return array{orphelins:array<int>,ouvert:int,accolades:int}
 *         `orphelins` = lignes portant un `⟨fermeture⟩` qui ne referme rien ;
 *         `ouvert`    = ligne du `/*` jamais refermé, 0 si aucun ;
 *         `accolades` = solde `{` moins `}` hors commentaires et hors chaînes.
 */
function aod_cssint_scan( $css ) {
	$n         = strlen( $css );
	$ligne     = 1;
	$dans_com  = false;
	$com_debut = 0;
	$guillemet = '';
	$orphelins = array();
	$solde     = 0;

	for ( $i = 0; $i < $n; $i++ ) {
		$c = $css[ $i ];
		if ( "\n" === $c ) {
			++$ligne;
			continue;
		}

		if ( $dans_com ) {
			if ( '*' === $c && $i + 1 < $n && '/' === $css[ $i + 1 ] ) {
				$dans_com = false;
				++$i;
			}
			continue;
		}

		// Dans une chaîne : seule sa fermeture compte (l'échappement est traversé).
		if ( '' !== $guillemet ) {
			if ( '\\' === $c ) {
				++$i;
			} elseif ( $c === $guillemet ) {
				$guillemet = '';
			}
			continue;
		}

		if ( '"' === $c || "'" === $c ) {
			$guillemet = $c;
			continue;
		}
		if ( '/' === $c && $i + 1 < $n && '*' === $css[ $i + 1 ] ) {
			$dans_com  = true;
			$com_debut = $ligne;
			++$i;
			continue;
		}
		if ( '*' === $c && $i + 1 < $n && '/' === $css[ $i + 1 ] ) {
			$orphelins[] = $ligne; // Ferme un commentaire qui n'est pas ouvert.
			++$i;
			continue;
		}
		if ( '{' === $c ) {
			++$solde;
		} elseif ( '}' === $c ) {
			--$solde;
		}
	}

	return array(
		'orphelins' => $orphelins,
		'ouvert'    => $dans_com ? $com_debut : 0,
		'accolades' => $solde,
	);
}

// Les feuilles servies par les DEUX plugins maison. Le défaut du jour était dans
// `dashboard.css`, mais rien ne le rend propre à ce fichier : la même coquille
// dans `aod-landing.css` casserait une page de vente, côté acheteur.
$aod_cssint_racine_cd  = rtrim( AOD_CD_PATH, '/' );
$aod_cssint_racine_cod = defined( 'AOD_COD_PATH' ) ? rtrim( AOD_COD_PATH, '/' ) : dirname( $aod_cssint_racine_cd ) . '/aod-cod-form';

$aod_cssint_feuilles = array(
	'dashboard.css'      => $aod_cssint_racine_cd . '/assets/css/dashboard.css',
	'aod-cod-form.css'   => $aod_cssint_racine_cod . '/assets/css/aod-cod-form.css',
	'aod-cod-pages.css'  => $aod_cssint_racine_cod . '/assets/css/aod-cod-pages.css',
	'aod-landing.css'    => $aod_cssint_racine_cod . '/assets/css/aod-landing.css',
	'aod-cart.css'       => $aod_cssint_racine_cod . '/assets/css/aod-cart.css',
);

AOD_Test::group( 'CSS intègre — R0 : les feuilles sont lisibles' );

$aod_cssint_src = array();
foreach ( $aod_cssint_feuilles as $aod_cssint_nom => $aod_cssint_p ) {
	$aod_cssint_src[ $aod_cssint_nom ] = is_readable( $aod_cssint_p ) ? (string) file_get_contents( $aod_cssint_p ) : '';
	AOD_Test::ok(
		'' !== $aod_cssint_src[ $aod_cssint_nom ],
		sprintf( '%s lisible', $aod_cssint_nom ),
		'chemin : ' . $aod_cssint_p
	);
}

AOD_Test::group( 'CSS intègre — R1 : les commentaires s’apparient dans l’ordre' );

foreach ( $aod_cssint_src as $aod_cssint_nom => $aod_cssint_css ) {
	if ( '' === $aod_cssint_css ) {
		continue;
	}
	$aod_cssint_r = aod_cssint_scan( $aod_cssint_css );
	AOD_Test::ok(
		array() === $aod_cssint_r['orphelins'],
		sprintf( '%s — aucun « */ » qui ne referme rien', $aod_cssint_nom ),
		'ligne(s) : ' . implode( ', ', $aod_cssint_r['orphelins'] )
	);
	AOD_Test::ok(
		0 === $aod_cssint_r['ouvert'],
		sprintf( '%s — aucun commentaire laissé ouvert', $aod_cssint_nom ),
		'ouvert ligne ' . $aod_cssint_r['ouvert']
	);
}

AOD_Test::group( 'CSS intègre — R2 : les accolades s’équilibrent' );

foreach ( $aod_cssint_src as $aod_cssint_nom => $aod_cssint_css ) {
	if ( '' === $aod_cssint_css ) {
		continue;
	}
	$aod_cssint_r = aod_cssint_scan( $aod_cssint_css );
	AOD_Test::ok(
		0 === $aod_cssint_r['accolades'],
		sprintf( '%s — solde des accolades nul', $aod_cssint_nom ),
		'solde : ' . $aod_cssint_r['accolades']
	);
}

AOD_Test::group( 'CSS intègre — R3 : le scanner rougit pour LA raison qu’on croit' );

// Une feuille saine, y compris ses pièges : un « /* » cité dans une chaîne et une
// accolade citée ne doivent RIEN déclencher.
$aod_cssint_sain = '/* note */ .a{color:red} .b::after{content:"/* pas un commentaire } non plus"}';
$aod_cssint_t1   = aod_cssint_scan( $aod_cssint_sain );
AOD_Test::ok(
	array() === $aod_cssint_t1['orphelins'] && 0 === $aod_cssint_t1['ouvert'] && 0 === $aod_cssint_t1['accolades'],
	'Témoin sain : aucune anomalie (les « /* » et « } » cités sont traversés)',
	sprintf( 'orphelins=%d ouvert=%d accolades=%d', count( $aod_cssint_t1['orphelins'] ), $aod_cssint_t1['ouvert'], $aod_cssint_t1['accolades'] )
);

// LE défaut du 2026-08-09, reproduit : autant d'ouvrants que de fermants, donc
// invisible à un comptage — c'est l'ordre qui est faux.
$aod_cssint_casse = "/* debut */\n/* suite " . "*" . "/\n   texte nu " . "*" . "/\n.c{color:blue}";
$aod_cssint_t2    = aod_cssint_scan( $aod_cssint_casse );
AOD_Test::ok(
	array( 3 ) === $aod_cssint_t2['orphelins'],
	'Témoin cassé : la fermeture orpheline est vue, et à la bonne ligne',
	'orphelins : ' . implode( ', ', $aod_cssint_t2['orphelins'] )
);
AOD_Test::ok(
	2 === substr_count( $aod_cssint_casse, '/*' ) && 3 === substr_count( $aod_cssint_casse, '*' . '/' ),
	'Témoin cassé : un simple comptage l’aurait manqué s’il ne comparait que les paires ouvertes',
	'un comptage naïf 2/3 signale ici — mais pas le cas 2/2 en ordre inverse'
);

$aod_cssint_ouvert = ".d{color:green}\n/* jamais refermé\n.e{color:black}";
$aod_cssint_t3     = aod_cssint_scan( $aod_cssint_ouvert );
AOD_Test::ok(
	2 === $aod_cssint_t3['ouvert'],
	'Témoin ouvert : le commentaire jamais refermé est vu, et à la bonne ligne',
	'ouvert ligne ' . $aod_cssint_t3['ouvert']
);

// Témoin de COUVERTURE : sans lui, une liste de feuilles devenue fausse (fichier
// renommé, plugin déplacé) rendrait « 0 anomalie » sur zéro feuille — le vert à
// vide que ce fichier existe pour interdire.
$aod_cssint_octets = 0;
foreach ( $aod_cssint_src as $aod_cssint_css ) {
	$aod_cssint_octets += strlen( $aod_cssint_css );
}
AOD_Test::ok(
	$aod_cssint_octets > 200000,
	sprintf( 'Couverture : %d octets de CSS réellement scannés (plancher 200 000)', $aod_cssint_octets ),
	'sous le plancher : la liste des feuilles ne trouve plus ce qu’elle croit lire'
);
