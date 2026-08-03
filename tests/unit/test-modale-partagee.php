<?php
/**
 * Phase 47.4 — La ligne « Client » de Commandes et la modale partagée.
 *
 * ⚠️ CE QUE CE FICHIER PEUT, ET CE QU'IL NE PEUT PAS — à lire avant de s'y fier.
 * Le harnais tourne sous WP-CLI : il n'exécute aucun JavaScript et ne calcule
 * aucune mise en page. La preuve FONCTIONNELLE de cette étape est donc au
 * navigateur (portes `clavier`, `rtl`, `suivi`) : le fond qui ne ferme plus, les
 * deux rangs, les hauteurs. Ici on tient la STRUCTURE — le propriétaire unique de
 * la modale, les contrats de markup, le recensement des exemptions CSS — plus une
 * seule règle fonctionnelle (R5), qui rend une VRAIE ligne.
 * Maquiller un test de motif en preuve de comportement, c'est ce qui a fait deux
 * trous en 47.1 et 47.2 (une assertion satisfaite par un commentaire ; une autre
 * par un fragment de source).
 *
 *   R0  Témoins : les cinq fichiers concernés sont lus, leurs tailles reportées.
 *   R1  UN SEUL fichier de assets/js/ pilote `#aod-cd-modal`, et c'est la coque.
 *   R2  Le markup partagé garde `data-close` SUR LE FOND (la timeline en dépend).
 *   R3  La croix garde `data-close` ET son nom accessible — pas de modale sans issue.
 *   R4  Le recensement des cellules exemptées de la colonne de libellé est FERMÉ.
 *   R5  La ligne de commande rendue tient ses contrats (rendu réel).
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AOD_CD_Dashboard' ) || ! defined( 'AOD_CD_PATH' ) ) {
	AOD_Test::ok( false, 'Modale partagée 47.4 — le tableau de bord est disponible', 'AOD_CD_Dashboard / AOD_CD_PATH introuvable' );
	return;
}

/* =========================================================================
 * R0 — Témoins : sans eux, tout ce qui suit peut verdir sur du vide
 * ========================================================================= */

AOD_Test::group( 'Modale partagée 47.4 — R0 : témoins' );

$aod_mp_fichiers = array(
	'coque JS'      => AOD_CD_PATH . 'assets/js/dashboard.js',
	'module orders' => AOD_CD_PATH . 'assets/js/section-orders.js',
	'module suivi'  => AOD_CD_PATH . 'assets/js/section-tracking.js',
	'feuille CSS'   => AOD_CD_PATH . 'assets/css/dashboard.css',
	'coque PHP'     => AOD_CD_PATH . 'includes/class-aod-cd-dashboard.php',
);
$aod_mp_src = array();
foreach ( $aod_mp_fichiers as $aod_mp_nom => $aod_mp_f ) {
	$aod_mp_src[ $aod_mp_nom ] = is_readable( $aod_mp_f ) ? (string) file_get_contents( $aod_mp_f ) : '';
	AOD_Test::ok(
		'' !== $aod_mp_src[ $aod_mp_nom ],
		sprintf( 'TÉMOIN : %s lu (%d octets)', $aod_mp_nom, strlen( $aod_mp_src[ $aod_mp_nom ] ) ),
		'Fichier illisible : les règles suivantes verdiraient sur une chaîne vide.'
	);
}

/* =========================================================================
 * R1 — UN SEUL propriétaire pour `#aod-cd-modal`
 *
 * 🚨 C'est la règle centrale de l'étape. Avant la 47.4, `bindOrderDetail` et
 * `bindTracking` posaient CHACUN leur fermeture et leur `Échap` sur le même
 * élément — et tous les modules tournent sur toutes les pages. Écrire la
 * politique du fond dans les deux, c'était la dérive de la Phase 46.
 *
 * Deux marqueurs, parce qu'un seul se contourne : la RÉFÉRENCE à l'élément
 * (`getElementById( 'aod-cd-modal' )`) et le CÂBLAGE de ses fermetures
 * (`data-close`). Recensement sur TOUS les `assets/js/*.js`, pas sur une liste
 * écrite à la main — un module neuf entre dans le champ tout seul.
 * ========================================================================= */

AOD_Test::group( 'Modale partagée 47.4 — R1 : un seul propriétaire, et c’est la coque' );

$aod_mp_js = glob( AOD_CD_PATH . 'assets/js/*.js' );
AOD_Test::ok(
	count( $aod_mp_js ) >= 12,
	sprintf( 'TÉMOIN : %d fichiers JS recensés', count( $aod_mp_js ) ),
	'Le glob ne trouve rien : R1 serait verte sans avoir rien examiné.'
);

$aod_mp_marqueurs = array(
	'référence à l’élément' => "getElementById( 'aod-cd-modal' )",
	'câblage [data-close]'  => 'data-close',
);
foreach ( $aod_mp_marqueurs as $aod_mp_quoi => $aod_mp_needle ) {
	$aod_mp_porteurs = array();
	foreach ( $aod_mp_js as $aod_mp_f ) {
		$aod_mp_s = (string) file_get_contents( $aod_mp_f );
		if ( false !== strpos( $aod_mp_s, $aod_mp_needle ) ) {
			$aod_mp_porteurs[] = basename( $aod_mp_f );
		}
	}
	AOD_Test::eq(
		array( 'dashboard.js' ),
		$aod_mp_porteurs,
		sprintf( '🔴 %s : le seul porteur est dashboard.js (trouvé : %s)', $aod_mp_quoi, $aod_mp_porteurs ? implode( ', ', $aod_mp_porteurs ) : 'aucun' )
	);
}

// Et le helper existe bien, exposé sur le bus — sans quoi R1 serait verte sur
// deux modules qui ne pilotent plus rien du tout.
AOD_Test::ok(
	false !== strpos( $aod_mp_src['coque JS'], 'function sharedModal(' ),
	'sharedModal() est déclarée dans la coque',
	'Le pilote unique n’existe pas : les deux modules ont perdu la modale au lieu de la partager.'
);
AOD_Test::ok(
	false !== strpos( $aod_mp_src['coque JS'], 'sharedModal: sharedModal' ),
	'sharedModal est exposée sur window.aodCdCore',
	'Non exposée : les modules de section ne peuvent pas la consommer.'
);
AOD_Test::ok(
	false === strpos( $aod_mp_src['coque JS'], "register( 'sharedModal'" ),
	'sharedModal n’est PAS register()ée : ce n’est pas un binder',
	'R3 de test-decomposition-js.php rougirait (« enregistré hors de l’ordre canonique »).'
);
foreach ( array( 'module orders' => 'partagee.open(', 'module suivi' => 'partagee.open(' ) as $aod_mp_nom => $aod_mp_needle ) {
	AOD_Test::ok(
		false !== strpos( $aod_mp_src[ $aod_mp_nom ], $aod_mp_needle ),
		sprintf( '%s consomme bien la modale partagée', $aod_mp_nom ),
		'Le module ne l’ouvre plus : la section a perdu sa fenêtre.'
	);
}
// La politique du fond est UN PARAMÈTRE, et les deux valeurs sont réellement
// employées — sinon « le fond ne ferme plus » serait vrai partout, y compris sur
// la timeline dont §B interdit de changer le comportement.
AOD_Test::ok(
	false !== strpos( $aod_mp_src['module orders'], 'backdropCloses: false' ),
	'Détail de commande : le fond NE FERME PAS (on y saisit)',
	'Sans ce paramètre, un clic à côté jette encore le travail en cours.'
);
AOD_Test::ok(
	false !== strpos( $aod_mp_src['module suivi'], 'backdropCloses: true' ),
	'Timeline de Suivi : le fond ferme TOUJOURS (comportement inchangé)',
	'La 47.4 n’avait pas mandat de changer la timeline.'
);

/* =========================================================================
 * R2 / R3 — le markup PARTAGÉ garde ses deux issues
 *
 * `data-close` reste sur le fond : la timeline en dépend, et la politique se
 * décide AU CLIC, pas par l'attribut (mesuré : le retirer après le démarrage est
 * inerte, l'écouteur ayant été posé par référence).
 * ========================================================================= */

AOD_Test::group( 'Modale partagée 47.4 — R2/R3 : le markup garde ses issues' );

AOD_Test::ok(
	false !== strpos( $aod_mp_src['coque PHP'], 'class="aod-cd-modal-backdrop" data-close' ),
	'R2 — le FOND porte toujours data-close',
	'Retiré : la timeline de Suivi ne se ferme plus au clic à côté — une régression muette.'
);
AOD_Test::ok(
	(bool) preg_match( '/class="aod-cd-modal-x" data-close aria-label="/', $aod_mp_src['coque PHP'] ),
	'R3 — la croix porte data-close ET son nom accessible',
	'Une modale dont la croix ne ferme pas — ou ne s’annonce pas — est un piège.'
);

/* =========================================================================
 * R4 — le recensement des cellules exemptées de la colonne de libellé
 *
 * Sous 780 px, `td::before` prend 40 % de la carte. Trois cellules en sont
 * exemptées et empilent leur libellé au-dessus. Le recensement est FERMÉ : une
 * quatrième exemption écrite ailleurs rougit, même si elle « marche ». C'est le
 * patron de R5 de test-charte-surfaces.php — une règle écrite deux fois ne tient
 * pas ; seul un cliquet la tient.
 * ========================================================================= */

AOD_Test::group( 'Modale partagée 47.4 — R4 : les exemptions de la colonne de libellé sont recensées' );

$aod_mp_css = $aod_mp_src['feuille CSS'];
$aod_mp_attendu = array(
	'.aod-cd-articles',
	'.aod-cd-cell-leadassign',
	'.aod-cd-cell-orderclient',
);

// Le corps qui DÉFAIT la colonne de libellé, où qu'il soit écrit.
preg_match_all( '/([^{}]*)::before\{display:block;flex:none/', $aod_mp_css, $aod_mp_m );
$aod_mp_regles = isset( $aod_mp_m[1] ) ? $aod_mp_m[1] : array();

AOD_Test::eq(
	1,
	count( $aod_mp_regles ),
	sprintf( '🔴 UN SEUL corps pour toutes les exemptions (trouvé : %d)', count( $aod_mp_regles ) )
);

$aod_mp_selecteurs = $aod_mp_regles ? $aod_mp_regles[0] : '';
foreach ( $aod_mp_attendu as $aod_mp_sel ) {
	AOD_Test::ok(
		false !== strpos( $aod_mp_selecteurs, $aod_mp_sel ),
		sprintf( 'Exemption recensée : %s', $aod_mp_sel ),
		'Absente du corps mutualisé : soit la cellule a perdu son exemption, soit elle a été recopiée ailleurs.'
	);
}
// Recensement FERMÉ : autant de sélecteurs que d'entrées attendues, pas une de
// plus. On compte le PRÉFIXE de mode carte, pas les `::before` : le dernier est
// consommé par le motif de capture ci-dessus.
$aod_mp_compte = substr_count( $aod_mp_selecteurs, '.aod-cd-table--cards ' );
AOD_Test::eq(
	count( $aod_mp_attendu ),
	$aod_mp_compte,
	sprintf( '🔴 Le recensement est FERMÉ : %d exemptions attendues, %d écrites', count( $aod_mp_attendu ), $aod_mp_compte )
);

// La classe PROPRE, et pourquoi elle l'est : `.aod-cd-cell-client` est PARTAGÉE
// avec Commandes abandonnées (`class-aod-cd-section-abandoned.php`). Styler le
// marqueur partagé aurait déplacé la cellule des prospects au passage — même
// arbitrage qu'en 43.3 pour `.aod-cd-cell-assigned` → `.aod-cd-cell-leadassign`.
$aod_mp_abandon = AOD_CD_PATH . 'includes/class-aod-cd-section-abandoned.php';
$aod_mp_abandon_src = is_readable( $aod_mp_abandon ) ? (string) file_get_contents( $aod_mp_abandon ) : '';
AOD_Test::ok(
	false !== strpos( $aod_mp_abandon_src, 'aod-cd-cell-client' ),
	'TÉMOIN : `.aod-cd-cell-client` est bien PARTAGÉE avec Commandes abandonnées',
	'Si elle ne l’était plus, la classe propre `--orderclient` n’aurait plus de raison d’être.'
);
AOD_Test::ok(
	false === strpos( $aod_mp_abandon_src, 'aod-cd-cell-orderclient' ),
	'…et la classe PROPRE ne déborde pas sur les prospects',
	'La mise en page à deux rangs s’appliquerait à une cellule qui n’a pas ce contenu.'
);

/* =========================================================================
 * R5 — 🎯 la seule règle FONCTIONNELLE : on rend une VRAIE ligne
 *
 * Motif de R5 de test-note-livreur.php : commande jetable + réflexion sur la
 * méthode protégée, puis lecture du HTML PRODUIT. Un test de source aurait
 * validé un `echo` commenté ; celui-ci regarde ce que le navigateur recevra.
 * ========================================================================= */

AOD_Test::group( 'Modale partagée 47.4 — R5 : la ligne rendue tient ses contrats' );

if ( ! class_exists( 'AOD_CD_Section_Orders' ) || ! function_exists( 'wc_create_order' ) ) {
	AOD_Test::ok( false, 'AOD_CD_Section_Orders et WooCommerce disponibles' );
	return;
}

$aod_mp_cmd = wc_create_order();
if ( ! $aod_mp_cmd instanceof WC_Order ) {
	AOD_Test::ok( false, 'Commande jetable créée', 'wc_create_order() a échoué' );
	return;
}
$aod_mp_nom_long = 'ABDELKADER BENYAHIA MOHAMED EL-HADJ';
$aod_mp_cmd->set_billing_first_name( 'ABDELKADER' );
$aod_mp_cmd->set_billing_last_name( 'BENYAHIA MOHAMED EL-HADJ' );
$aod_mp_cmd->set_billing_phone( '0550000000' );
$aod_mp_cmd->update_meta_data( '_aod_wilaya_code', 16 );
$aod_mp_cmd->save();
$aod_mp_id = $aod_mp_cmd->get_id();

$aod_mp_sec  = AOD_CD_Section_Orders::instance();
$aod_mp_opts = array( 'wc-pending' => 'En attente', 'wc-processing' => 'En cours' );

ob_start();
AOD_Test::call( $aod_mp_sec, 'render_order_row', array( wc_get_order( $aod_mp_id ), $aod_mp_opts, false, false, false ) );
$aod_mp_html = (string) ob_get_clean();

AOD_Test::ok(
	strlen( $aod_mp_html ) > 200 && false !== strpos( $aod_mp_html, 'aod-cd-cell-client' ),
	sprintf( 'TÉMOIN : la ligne est rendue (%d octets) et porte sa cellule Client', strlen( $aod_mp_html ) ),
	'Rendu vide : toutes les assertions de R5 seraient fausses pour la mauvaise raison.'
);

// Contrat 1 — le nom vit dans un `<b>` DANS `.aod-cd-client`.
// 🚨 Ce n'est pas de la cosmétique : `section-orders.js` lit `.aod-cd-client b`
// pour NOMMER chaque commande dans la fenêtre d'envoi de la 47.2. Sortir le nom
// du `<b>` viderait ce récapitulatif — en silence, et seulement au moment
// d'expédier, c'est-à-dire au pire endroit possible.
AOD_Test::ok(
	(bool) preg_match( '/<span class="aod-cd-client">.*?<b[^>]*>' . preg_quote( $aod_mp_nom_long, '/' ) . '<\/b>/s', $aod_mp_html ),
	'Contrat `.aod-cd-client b` : le nom est bien dans le <b> (fenêtre d’envoi 47.2)',
	'Le récapitulatif d’envoi afficherait des lignes SANS NOM.'
);
// Contrat 2 — le nom entier reste atteignable malgré l'ellipse CSS.
AOD_Test::ok(
	false !== strpos( $aod_mp_html, 'title="' . $aod_mp_nom_long . '"' ),
	'Le nom porte un `title` (l’ellipse tronque à l’affichage, pas l’information)',
	'Un nom long serait tronqué sans aucun moyen de le lire en entier.'
);

// Contrat 3 — « Détail » existe, et il a QUITTÉ le rang Appeler/WhatsApp.
AOD_Test::ok(
	false !== strpos( $aod_mp_html, 'aod-cd-order-detail' ),
	'Le bouton « Détail » est présent',
	'Sans lui, la fenêtre de détail est inatteignable depuis la liste.'
);
preg_match( '/<span class="aod-cd-call-actions">(.*?)<\/span>\s*<\/span><\/td>/s', $aod_mp_html, $aod_mp_rang2 );
$aod_mp_actions = isset( $aod_mp_rang2[1] ) ? $aod_mp_rang2[1] : '';
AOD_Test::ok(
	'' !== $aod_mp_actions,
	'TÉMOIN : le rang 2 (`.aod-cd-call-actions`) est rendu',
	'Rang introuvable : les deux assertions suivantes seraient vides de sens.'
);
AOD_Test::ok(
	false === strpos( $aod_mp_actions, 'aod-cd-order-detail' ),
	'🔴 « Détail » n’est PAS dans `.aod-cd-call-actions` — il est monté au rang 1',
	'Il est resté au rang des boutons de contact : la cellule garde ses cinq hauteurs au pouce.'
);
AOD_Test::ok(
	false !== strpos( $aod_mp_actions, 'wa.me/' ) && false !== strpos( $aod_mp_actions, 'href="tel:' ),
	'Le rang 2 porte bien Appeler et WhatsApp',
	'Les boutons de contact ont disparu du rang qui leur est réservé.'
);

// Contrat 4 — la cellule porte les DEUX classes (marqueur partagé + classe propre)
// et garde son `data-label` : sous 780 px, cet attribut EST l’intitulé de la
// ligne (46.2), le perdre rendrait la carte illisible.
AOD_Test::ok(
	false !== strpos( $aod_mp_html, 'class="aod-cd-cell-client aod-cd-cell-orderclient" data-label="' ),
	'La cellule porte le marqueur partagé, la classe propre ET son data-label',
	'Sans `--orderclient` la mise en page à deux rangs ne s’applique pas ; sans `data-label` la carte mobile perd son intitulé.'
);

// Contrat 5 — le rang 1 est INCONDITIONNEL. Une commande sans téléphone existe
// (squelette créé depuis un abandon, numéro vidé à l’édition) et doit garder
// « Détail » atteignable : c’est la garantie 45.3, reprise par un autre moyen.
$aod_mp_sans_tel = wc_get_order( $aod_mp_id );
$aod_mp_sans_tel->set_billing_phone( '' );
$aod_mp_sans_tel->save();
ob_start();
AOD_Test::call( $aod_mp_sec, 'render_order_row', array( wc_get_order( $aod_mp_id ), $aod_mp_opts, false, false, false ) );
$aod_mp_html2 = (string) ob_get_clean();
AOD_Test::ok(
	false !== strpos( $aod_mp_html2, 'aod-cd-order-detail' ),
	'🔴 Commande SANS téléphone : « Détail » reste atteignable (garantie 45.3)',
	'La fenêtre de détail devient inatteignable pour ces commandes-là — le défaut exact que la 45.3 avait fermé.'
);
AOD_Test::ok(
	false === strpos( $aod_mp_html2, 'aod-cd-call-actions' ),
	'…et le rang 2 n’est pas rendu à vide (pas de hauteur pour rien)',
	'Un rang vide ajoute une gouttière et une hauteur sans aucun contenu.'
);

/* --- Purge : la commande jetable ne survit pas au test --- */
$aod_mp_final = wc_get_order( $aod_mp_id );
if ( $aod_mp_final instanceof WC_Order ) {
	$aod_mp_final->delete( true );
}
