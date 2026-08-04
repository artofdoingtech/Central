<?php
/**
 * Export CSV des commandes — les deux neutralisations de `csv_safe()`
 * (demande owner du 2026-08-04).
 *
 * POURQUOI UN CLIQUET ICI. Les deux défauts que cette fonction évite sont
 * INVISIBLES à la relecture du code et ne se voient qu'en ouvrant le fichier
 * dans un tableur — c'est-à-dire chez le gérant, jamais chez nous :
 *
 *   1. INJECTION DE FORMULE. Un tableur exécute toute cellule commençant par
 *      `=`, `+`, `-` ou `@`. Or le NOM et l'ADRESSE viennent de l'acheteur, via
 *      un formulaire public sans compte : c'est une entrée hostile par
 *      construction. Une cellule `=HYPERLINK(...)` s'exécute à l'ouverture, sur
 *      la machine du gérant. (CWE-1236.)
 *   2. TÉLÉPHONES MUTILÉS. Un numéro algérien commence par 0 ; Excel le lit
 *      comme un nombre et écrit `551234567`. En COD, le téléphone EST le moyen
 *      de livrer — un export dont on ne peut pas rappeler les clients ne sert
 *      à rien, et le défaut passe pour « un problème d'Excel ».
 *
 * 🚨 CE QUE CE TEST NE PROUVE PAS. Il éprouve la NEUTRALISATION, pas l'export :
 * ni les en-têtes, ni le BOM, ni le séparateur `;`, ni le respect du périmètre
 * confirmateur (`scoped_orders()`) — cette dernière étant la garantie la plus
 * lourde du lot, puisqu'elle décide qui peut télécharger le fichier client de
 * toute la boutique. Elle se prouve en parcours, pas ici.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Export CSV — neutralisation des cellules (csv_safe)' );

AOD_Test::ok(
	method_exists( 'AOD_CD_Section_Orders', 'csv_safe' ),
	'TÉMOIN : `AOD_CD_Section_Orders::csv_safe()` existe',
	'sans elle, les contrôles ci-dessous seraient verts par absence de sujet'
);

/* --- 1. Injection de formule : les 4 amorces reconnues par les tableurs ----- */
foreach ( array( '=', '+', '-', '@' ) as $aod_csv_c ) {
	$aod_csv_in = $aod_csv_c . 'HYPERLINK("http://x")';
	AOD_Test::eq(
		"'" . $aod_csv_in,
		AOD_CD_Section_Orders::csv_safe( $aod_csv_in ),
		'🔴 Une cellule commençant par « ' . $aod_csv_c . ' » est neutralisée par une apostrophe'
	);
}

/* --- 2. Téléphones algériens : le zéro de tête survit ----------------------- */
AOD_Test::eq(
	"'0551234567",
	AOD_CD_Section_Orders::csv_safe( '0551234567' ),
	'🔴 Un numéro à zéro initial est forcé en texte (sinon Excel écrit 551234567)'
);
AOD_Test::eq(
	"'0770000000",
	AOD_CD_Section_Orders::csv_safe( '0770000000' ),
	'🔴 …quel que soit l’opérateur'
);

/* --- 3. …sans abîmer ce qui est légitime ------------------------------------
 * Un cliquet qui préfixerait TOUT serait vert sur les deux points ci-dessus et
 * rendrait pourtant l'export inutilisable : le comptable lirait `'2500.00` en
 * texte, et aucune somme ne se calculerait. La neutralisation doit être ciblée.
 * ------------------------------------------------------------------------- */
AOD_Test::eq( '2500.00', AOD_CD_Section_Orders::csv_safe( '2500.00' ), 'Un montant reste un nombre (pas de préfixe)' );
AOD_Test::eq( 'Alger', AOD_CD_Section_Orders::csv_safe( 'Alger' ), 'Un nom de wilaya est laissé intact' );
AOD_Test::eq( '', AOD_CD_Section_Orders::csv_safe( '' ), 'Une cellule vide reste vide (et ne devient pas une apostrophe seule)' );
AOD_Test::eq( '0', AOD_CD_Section_Orders::csv_safe( '0' ), '🔴 Un « 0 » SEUL n’est pas un téléphone — il reste un nombre' );
AOD_Test::eq(
	'الجزائر',
	AOD_CD_Section_Orders::csv_safe( 'الجزائر' ),
	'🔴 Une valeur arabe traverse sans altération (l’export est bilingue)'
);

/* --- 4. Les colonnes : une seule source pour l'en-tête ET les lignes -------- */
AOD_Test::group( 'Export CSV — le contrat de colonnes' );

$aod_csv_cols = AOD_CD_Section_Orders::export_columns();
AOD_Test::ok(
	is_array( $aod_csv_cols ) && count( $aod_csv_cols ) >= 10,
	'Le contrat de colonnes est une table non vide (' . count( $aod_csv_cols ) . ' colonnes)'
);

$aod_csv_bad = array();
foreach ( $aod_csv_cols as $aod_csv_label => $aod_csv_fn ) {
	if ( '' === trim( (string) $aod_csv_label ) || ! is_callable( $aod_csv_fn ) ) {
		$aod_csv_bad[] = (string) $aod_csv_label;
	}
}
AOD_Test::eq(
	array(),
	$aod_csv_bad,
	'🔴 Chaque colonne a un libellé non vide ET un extracteur appelable',
	'l’en-tête et les lignes sont produits par CETTE table : une entrée boiteuse décale tout le fichier sans erreur visible'
);

// Le téléphone est la colonne qui justifie la moitié de ce fichier : si elle
// disparaît d'un refactor, les contrôles ci-dessus resteraient verts pour rien.
AOD_Test::ok(
	array_key_exists( __( 'Téléphone', 'aod-client-dashboard' ), $aod_csv_cols ),
	'🔴 La colonne « Téléphone » est présente — c’est le seul moyen de livrer une commande COD'
);
