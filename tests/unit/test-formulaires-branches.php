<?php
/**
 * Un formulaire qui ANNONCE une action AJAX doit être BRANCHÉ à quelque chose.
 *
 * 🚨 POURQUOI CE FICHIER EXISTE. Le 2026-08-09, en refondant la page WhatsApp,
 * la classe `aod-cd-settings-form` a été REMPLACÉE par `aod-cd-wa` au lieu d'être
 * complétée par elle. Or c'est cette classe — et elle seule — que
 * `bindSettingsForms()` cherche pour intercepter la soumission.
 *
 * Résultat : le bouton « Enregistrer » a continué d'exister, la barre collante
 * aussi, l'attribut `data-action="aod_cd_save_whatsapp"` aussi. Rien ne manquait
 * à l'œil. Simplement, plus aucun JavaScript n'écoutait : le formulaire repartait
 * en soumission NATIVE, l'URL devenait `?phone=…&apikey=` et le réglage n'était
 * JAMAIS écrit. Mesuré au navigateur : zéro requête vers `admin-ajax.php`.
 *
 * 🔴 ET AUCUNE PORTE NE POUVAIT LE VOIR. `noms`, `rtl` et `mouvement` mesurent
 * des noms accessibles, des débordements et des transitions ; aucune ne SOUMET un
 * formulaire de réglages. Les 1944 tests, eux, ne lisent pas le JavaScript. Le
 * défaut n'a été trouvé qu'en relisant le diff par hasard, un jour plus tard.
 *
 * La leçon est plus générale que ce bug : `data-action` NOMME une action, il ne
 * la BRANCHE pas. Un attribut qui ressemble à un câblage sans en être un est
 * exactement ce qui rend une panne invisible.
 *
 * Deux contrôles statiques :
 *   R1 — tout `<form>` porteur de `data-action` porte aussi la classe que le
 *        binder cherche.
 *   R2 — la valeur de `data-action` correspond à un handler réellement
 *        enregistré (`add_action( 'wp_ajax_… )`). Un nom mal orthographié meurt
 *        aussi en silence : le fetch part, WordPress répond `0`, et le gérant
 *        voit un simple message d'erreur sans jamais savoir pourquoi.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

// La classe-crochet, écrite UNE fois. Si `bindSettingsForms()` change de
// sélecteur un jour, c'est ici qu'on le répercute — et R3 dira si la constante
// ne correspond plus à ce que le JS cherche vraiment.
const AOD_FBR_CLASSE = 'aod-cd-settings-form';

$aod_fbr_racine_cd  = rtrim( AOD_CD_PATH, '/' );
$aod_fbr_includes   = $aod_fbr_racine_cd . '/includes';
$aod_fbr_js         = $aod_fbr_racine_cd . '/assets/js/dashboard.js';

/**
 * Tous les `<form … data-action="…">` d'un dossier, avec leur liste de classes.
 *
 * ⚠️ Préfixe `aod_fbr_` : `tests/all.php` charge tout par `glob()` dans le même
 * espace global (cf. test-charte-surfaces.php).
 *
 * @param string $dir Dossier à balayer.
 * @return array<int,array{fichier:string,classes:string,action:string}>
 */
function aod_fbr_formulaires( $dir ) {
	$out = array();
	foreach ( (array) glob( $dir . '/*.php' ) as $f ) {
		$src = (string) file_get_contents( $f );
		// La balise entière, quel que soit l'ordre des attributs.
		if ( ! preg_match_all( '/<form\b[^>]*>/i', $src, $m ) ) {
			continue;
		}
		foreach ( $m[0] as $balise ) {
			if ( ! preg_match( '/data-action\s*=\s*"([^"]*)"/i', $balise, $a ) ) {
				continue;
			}
			preg_match( '/\bclass\s*=\s*"([^"]*)"/i', $balise, $c );
			$out[] = array(
				'fichier' => basename( $f ),
				'classes' => isset( $c[1] ) ? $c[1] : '',
				'action'  => $a[1],
			);
		}
	}
	return $out;
}

$aod_fbr_forms = aod_fbr_formulaires( $aod_fbr_includes );

AOD_Test::group( 'Formulaires branchés — R0 : le vivier existe' );

// 🚨 TÉMOIN DE COUVERTURE. Sans lui, une regex devenue fausse (balise réécrite,
// attributs sur plusieurs lignes) rendrait ZÉRO formulaire — et les deux règles
// ci-dessous passeraient au vert sans avoir rien regardé. C'est exactement le
// vert à vide que ce dépôt a déjà rencontré trois fois.
AOD_Test::ok(
	count( $aod_fbr_forms ) >= 5,
	sprintf( 'TÉMOIN : %d formulaire(s) à action AJAX trouvé(s) (plancher 5)', count( $aod_fbr_forms ) ),
	'sous le plancher : la détection ne trouve plus les balises qu’elle croit lire'
);

AOD_Test::group( 'Formulaires branchés — R1 : `data-action` ne suffit pas, il faut la classe' );

foreach ( $aod_fbr_forms as $f ) {
	$classes = preg_split( '/\s+/', trim( $f['classes'] ) );
	AOD_Test::ok(
		in_array( AOD_FBR_CLASSE, (array) $classes, true ),
		sprintf( '%s — « %s » porte la classe %s', $f['fichier'], $f['action'], AOD_FBR_CLASSE ),
		'classes trouvées : ' . ( '' !== $f['classes'] ? $f['classes'] : '(aucune)' )
			. ' — sans cette classe, aucun JS n’écoute et le formulaire part en soumission NATIVE'
	);
}

AOD_Test::group( 'Formulaires branchés — R2 : l’action nommée existe côté serveur' );

// Tous les handlers réellement enregistrés dans les deux plugins.
$aod_fbr_handlers = array();
foreach ( array( $aod_fbr_includes, dirname( $aod_fbr_racine_cd ) . '/aod-cod-form/includes' ) as $d ) {
	foreach ( (array) glob( $d . '/*.php' ) as $f ) {
		if ( preg_match_all( '/add_action\(\s*[\'"]wp_ajax_(?:nopriv_)?([a-z0-9_]+)[\'"]/i', (string) file_get_contents( $f ), $m ) ) {
			foreach ( $m[1] as $h ) {
				$aod_fbr_handlers[ $h ] = true;
			}
		}
	}
}

AOD_Test::ok(
	count( $aod_fbr_handlers ) >= 20,
	sprintf( 'TÉMOIN : %d handler(s) AJAX recensé(s) (plancher 20)', count( $aod_fbr_handlers ) ),
	'sous le plancher : R2 comparerait à une liste vide, donc rougirait sur tout'
);

foreach ( $aod_fbr_forms as $f ) {
	AOD_Test::ok(
		isset( $aod_fbr_handlers[ $f['action'] ] ),
		sprintf( '%s — « %s » a bien un handler enregistré', $f['fichier'], $f['action'] ),
		'aucun add_action( \'wp_ajax_' . $f['action'] . '\' ) trouvé'
	);
}

AOD_Test::group( 'Formulaires branchés — R3 : la constante suit ce que le JS cherche' );

// 🚨 SANS CECI, R1 SERAIT UN CONTRÔLE SUR LUI-MÊME. Le jour où `bindSettingsForms()`
// changerait de sélecteur, R1 continuerait de vérifier une classe que plus
// personne n'écoute — vert, et faux. On lit donc le sélecteur DANS le JS.
$aod_fbr_src_js = is_readable( $aod_fbr_js ) ? (string) file_get_contents( $aod_fbr_js ) : '';
AOD_Test::ok(
	'' !== $aod_fbr_src_js,
	'dashboard.js lisible',
	'chemin : ' . $aod_fbr_js
);
AOD_Test::ok(
	false !== strpos( $aod_fbr_src_js, "querySelectorAll( '." . AOD_FBR_CLASSE . "' )" ),
	sprintf( 'bindSettingsForms() cherche bien « .%s »', AOD_FBR_CLASSE ),
	'le sélecteur du JS a changé : mets à jour AOD_FBR_CLASSE, sinon R1 garde une classe morte'
);

AOD_Test::group( 'Formulaires branchés — R4 : le contrôle rougit pour LA raison qu’on croit' );

// Mise au rouge sans toucher au moindre fichier : on soumet au matcher la forme
// EXACTE du défaut du 2026-08-09.
$aod_fbr_temoin = '<form class="aod-cd-form aod-cd-wa" data-action="aod_cd_save_whatsapp">';
preg_match( '/\bclass\s*=\s*"([^"]*)"/i', $aod_fbr_temoin, $aod_fbr_tc );
$aod_fbr_tclasses = preg_split( '/\s+/', trim( $aod_fbr_tc[1] ) );
AOD_Test::ok(
	! in_array( AOD_FBR_CLASSE, (array) $aod_fbr_tclasses, true ),
	'Témoin : la balise FAUTIVE du 2026-08-09 est bien rejetée par R1',
	'le matcher accepte une balise sans la classe — il ne garde plus rien'
);
$aod_fbr_ok = '<form class="aod-cd-form aod-cd-settings-form aod-cd-wa" data-action="aod_cd_save_whatsapp">';
preg_match( '/\bclass\s*=\s*"([^"]*)"/i', $aod_fbr_ok, $aod_fbr_oc );
AOD_Test::ok(
	in_array( AOD_FBR_CLASSE, (array) preg_split( '/\s+/', trim( $aod_fbr_oc[1] ) ), true ),
	'Témoin : la balise CORRIGÉE est acceptée (le matcher ne rejette pas tout)',
	'le matcher refuse une balise correcte — il rougirait en permanence, donc serait ignoré'
);
