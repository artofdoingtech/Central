<?php
/**
 * Les questions du tableau de bord passent par la fenêtre MAISON (Phase 47.7).
 *
 * 🚨 CE QUE CE CLIQUET GARDE, ET POURQUOI IL A DEUX RÈGLES ET NON UNE.
 * Le Constat 10 demandait « un test qui compte zéro `window.confirm|alert|prompt` ».
 * Un zéro seul est un contrôle FAIBLE : il resterait VERT si quelqu'un supprimait
 * les treize appels **sans rien mettre à la place** — c'est-à-dire en retirant
 * treize confirmations, dont celle de la remise à zéro de la boutique. C'est le
 * mode d'échec exact que cette phase a déjà rencontré trois fois : une assertion
 * satisfaite par l'absence.
 *
 * D'où R2, le RECENSEMENT : les 13 questions doivent EXISTER, au même endroit et
 * en même nombre. Ensemble, R1 et R2 encadrent le remplacement des deux côtés —
 * plus de dialogue natif, et pas une question de moins.
 *
 * ⚠️ CE FICHIER CONTIENT LUI-MÊME LES MOTS INTERDITS (dans ses commentaires et sa
 * table). Le balayage porte donc sur `plugins-dev/`, JAMAIS sur `tests/` — sinon
 * il rougirait sur lui-même, et la seule façon de le faire verdir serait de ne
 * plus expliquer ce qu'il garde. Vérifié, pas supposé : R0 le mesure.
 *
 * 🚨 ET IL NE CHERCHE PAS « confirmModal » DANS LES FICHIERS. La 47.1 a été
 * satisfaite par un COMMENTAIRE, la 47.2 par un FRAGMENT DE SOURCE, deux fois.
 * R2 compte des SITES D'APPEL par fichier et les confronte à une table écrite
 * ici — le patron de `test-phpcs-ignore.php`, qui a déjà fait ses preuves : un
 * ajout fait diverger le compte et oblige un humain à venir s'expliquer.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les questions du tableau de bord, PAR FICHIER — le contrat de la 47.7.
 *
 * ⚠️ Préfixe `aod_test_dlg_` : `tests/all.php` charge tout dans le même espace.
 *
 * @return array<string,int> chemin relatif => nombre d'appels à la fenêtre maison.
 */
function aod_test_dlg_recensement() {
	return array(
		// Remise à zéro de la boutique — le geste le plus destructif de /gestion.
		'aod-client-dashboard/assets/js/section-account.js'   => 1,
		// Supprimer un bloc · remplacer tous les blocs par ceux d'un autre produit.
		'aod-client-dashboard/assets/js/section-landing.js'   => 2,
		// Afficher un mot de passe engendré (AVIS, `okOnly`) · supprimer un confirmateur.
		'aod-client-dashboard/assets/js/section-team.js'      => 2,
		// Appliquer une grille de tarifs importée · restaurer la précédente.
		'aod-client-dashboard/assets/js/section-shipping.js'  => 2,
		// Convertir un prospect · supprimer un lot de prospects.
		'aod-client-dashboard/assets/js/section-abandoned.js' => 2,
		// Supprimer un produit · supprimer une catégorie.
		'aod-client-dashboard/assets/js/section-products.js'  => 2,
		// Supprimer un lot de commandes · écraser des assignations existantes.
		'aod-client-dashboard/assets/js/section-orders.js'    => 2,
	);
}

/** Tous les `.js` des deux plugins — c'est le périmètre du Constat 10. */
function aod_test_dlg_fichiers() {
	$out = array();
	foreach ( array( 'aod-cod-form', 'aod-client-dashboard' ) as $plugin ) {
		$dir = dirname( AOD_CD_PATH ) . '/' . $plugin . '/assets/js';
		foreach ( (array) glob( $dir . '/*.js' ) as $f ) {
			$out[ $plugin . '/assets/js/' . basename( $f ) ] = $f;
		}
	}
	return $out;
}

AOD_Test::group( 'Dialogues maison 47.7 — R0 : témoins de balayage' );

$aod_dlg_fichiers = aod_test_dlg_fichiers();
AOD_Test::ok( count( $aod_dlg_fichiers ) >= 15, 'Balayage : ' . count( $aod_dlg_fichiers ) . ' fichiers JS lus dans les DEUX plugins (un glob vide se déclarerait vert sinon)' );

// 🚨 Le périmètre exclut `tests/` — et ce fichier-ci contient les mots interdits.
// Le vérifier plutôt que le supposer : si le balayage se voyait lui-même, la
// seule façon de le faire verdir serait d'effacer ses propres explications.
$aod_dlg_soi = false;
foreach ( array_keys( $aod_dlg_fichiers ) as $aod_dlg_k ) {
	if ( false !== strpos( $aod_dlg_k, 'test-' ) ) {
		$aod_dlg_soi = true;
	}
}
AOD_Test::ok( ! $aod_dlg_soi, '🔴 Le balayage ne se voit PAS lui-même (aucun fichier de tests/ dans le périmètre)' );

/* =========================================================================
 * R1 — ZÉRO dialogue natif.
 * ========================================================================= */

AOD_Test::group( 'Dialogues maison 47.7 — R1 : plus aucun dialogue du navigateur' );

$aod_dlg_natifs = array();
foreach ( $aod_dlg_fichiers as $aod_dlg_rel => $aod_dlg_path ) {
	$aod_dlg_src = (string) file_get_contents( $aod_dlg_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	// `window.` EXIGÉ : c'est la forme employée dans tout le dépôt, et chercher
	// `confirm(` nu ferait rougir sur `confirmModal(` — un faux positif qui
	// pousserait à renommer la primitive pour satisfaire le test.
	if ( preg_match_all( '/window\.\s*(confirm|alert|prompt)\s*\(/', $aod_dlg_src, $m ) ) {
		$aod_dlg_natifs[ $aod_dlg_rel ] = count( $m[0] );
	}
}
AOD_Test::eq(
	array(),
	$aod_dlg_natifs,
	'🎯 Aucun window.confirm / alert / prompt dans les assets JS des deux plugins'
);

/* =========================================================================
 * R2 — …et les 13 questions EXISTENT TOUJOURS.
 * ========================================================================= */

AOD_Test::group( 'Dialogues maison 47.7 — R2 : recensement (le zéro seul ne prouverait rien)' );

$aod_dlg_attendu = aod_test_dlg_recensement();
$aod_dlg_vu      = array();
$aod_dlg_total   = 0;
foreach ( $aod_dlg_fichiers as $aod_dlg_rel => $aod_dlg_path ) {
	$aod_dlg_src = (string) file_get_contents( $aod_dlg_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	// Les SITES D'APPEL, pas la mention : `confirmModal(` suivi d'une parenthèse
	// ouvrante. La déclaration et l'alias de module ne comptent pas — ils
	// n'ouvrent aucune parenthèse d'appel.
	$aod_dlg_n = preg_match_all( '/(?<![\w.])confirmModal\s*\(\s*\{/', $aod_dlg_src, $m2 ) ? count( $m2[0] ) : 0;
	if ( $aod_dlg_n ) {
		$aod_dlg_vu[ $aod_dlg_rel ] = $aod_dlg_n;
		$aod_dlg_total             += $aod_dlg_n;
	}
}

ksort( $aod_dlg_attendu );
ksort( $aod_dlg_vu );
AOD_Test::eq( $aod_dlg_attendu, $aod_dlg_vu, '🎯 Les 13 questions sont là, fichier par fichier — une question retirée fait DIVERGER ce recensement' );
AOD_Test::eq( 13, $aod_dlg_total, '🔴 13 au total : exactement le compte du Constat 10 (12 confirmations + 1 avis)' );

/* =========================================================================
 * R3 — la primitive vit dans la COQUE, et une seule fois.
 * ========================================================================= */

AOD_Test::group( 'Dialogues maison 47.7 — R3 : une primitive, un propriétaire' );

$aod_dlg_coque = (string) file_get_contents( AOD_CD_PATH . 'assets/js/dashboard.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
AOD_Test::eq(
	1,
	preg_match_all( '/function\s+confirmModal\s*\(/', $aod_dlg_coque, $m3 ) ? count( $m3[0] ) : 0,
	'🎯 `confirmModal` est DÉCLARÉE une seule fois, et dans la coque'
);
AOD_Test::ok( false !== strpos( $aod_dlg_coque, 'confirmModal: confirmModal' ), 'Elle est exposée sur `window.aodCdCore` (sinon aucun module ne l’atteint)' );

// Aucun module ne doit refabriquer un scaffold de fenêtre : la primitive est
// bâtie SUR `openOverlayModal`, qui reste le seul CRÉATEUR d'overlay.
//
// 🚨 ON CHERCHE LA CRÉATION, PAS LA MENTION — et la nuance a été trouvée au
// rouge. Une première rédaction cherchait le nom de classe nu : elle rougissait
// sur `section-orders.js`, qui l'emploie dans un SÉLECTEUR
// (`[data-cancel]:not(.aod-cd-modal-backdrop)`) pour désigner le bouton Annuler
// d'une fenêtre… ouverte par `openOverlayModal`. C'est-à-dire exactement l'usage
// correct. La règle aurait poussé à renommer un sélecteur légitime pour
// satisfaire un test — la queue qui remue le chien. Le marqueur de CRÉATION est
// l'attribut de classe dans du markup construit, jamais la classe seule.
$aod_dlg_scaffold = array();
foreach ( $aod_dlg_fichiers as $aod_dlg_rel => $aod_dlg_path ) {
	if ( false !== strpos( $aod_dlg_rel, 'dashboard.js' ) ) {
		continue;
	}
	$aod_dlg_src = (string) file_get_contents( $aod_dlg_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false !== strpos( $aod_dlg_src, 'class="aod-cd-modal-backdrop"' ) ) {
		$aod_dlg_scaffold[] = $aod_dlg_rel;
	}
}
AOD_Test::eq( array(), $aod_dlg_scaffold, '🔴 Aucun module ne refabrique le squelette d’une fenêtre (le fond reste la propriété de la coque)' );

/* =========================================================================
 * R4 — les libellés des boutons viennent du SITE, pas du navigateur.
 * ========================================================================= */

AOD_Test::group( 'Dialogues maison 47.7 — R4 : les boutons parlent la langue du gérant' );

// C'était LE défaut du dialogue natif : « OK / Cancel » venaient du navigateur.
foreach ( array( 'Confirmer', 'Confirmation', 'Annuler' ) as $aod_dlg_s ) {
	$aod_dlg_ar = AOD_Test::in_arabic( function () use ( $aod_dlg_s ) {
		unload_textdomain( 'aod-client-dashboard' );
		load_textdomain( 'aod-client-dashboard', WP_PLUGIN_DIR . '/aod-client-dashboard/languages/aod-client-dashboard-ar.mo' );
		return __( $aod_dlg_s, 'aod-client-dashboard' ); // phpcs:ignore WordPress.WP.I18n
	} );
	unload_textdomain( 'aod-client-dashboard' );
	AOD_Test::ok(
		(bool) preg_match( '/\p{Arabic}/u', $aod_dlg_ar ),
		'🎯 « ' . $aod_dlg_s .' » existe en arabe (« ' . $aod_dlg_ar . ' ») — le dialogue natif, lui, empruntait ses boutons au navigateur'
	);
}
