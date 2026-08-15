<?php
/**
 * Cliquet sur la décomposition de `dashboard.js` (Phase 33) — le volet JS de la
 * décomposition du fichier-dieu (Phase 30, PHP, soldée).
 *
 * CE QUE CE TEST GARDE. `dashboard.js` faisait 3488 lignes dans UNE SEULE IIFE :
 * la portée est fermée, on ne coupe pas une clôture en deux balises <script>.
 * L'architecture de la Phase 33 : la coque (dashboard.js) garde les helpers
 * partagés + `init()` (32.5) + un registre avec ORDRE CANONIQUE d'exécution ;
 * chaque section vit dans un module `section-*.js` — une IIFE qui reçoit
 * `window.aodCdCore` et pose des ALIAS LOCAUX, corps de fonctions VERBATIM.
 *
 *   R1 · PLAFOND DE LIGNES de la coque — même logique que le cliquet PHP
 *        (test-decomposition.php) : descendre est un progrès, remonter exige de
 *        venir s'expliquer ici.
 *   R2 · PAS DE RÉ-ABSORPTION : un binder extrait est déclaré dans SON module
 *        et n'est PLUS déclaré dans la coque.
 *   R3 · ORDRE ⇄ REGISTRE : tout nom `register()`é figure dans ORDER, tout nom
 *        d'ORDER est `register()`é quelque part. Le runner signale déjà l'écart
 *        en console (échec nommé, politique 32.5) — ce contrôle l'attrape AVANT
 *        le navigateur.
 *   R4 · TOUT fichier `assets/js/*.js` du plugin est chargé par
 *        `dashboard_scripts()`, la coque en PREMIER. Un module créé mais non
 *        listé serait une section morte EN SILENCE — la leçon de la 18.1 : une
 *        feuille nouvelle recrée le trou hors des listes écrites en dur.
 *
 * ⚠️ Test STATIQUE : il prouve le câblage (motifs dans les sources + reflection
 * sur la liste de chargement), pas l'exécution. La preuve fonctionnelle est la
 * porte `rtl` de la recette : 12 sections rendues au navigateur, console propre.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les modules extraits de la coque, et les binders que chacun porte.
 *
 * 🧩 CETTE TABLE GRANDIT À CHAQUE EXTRACTION (miroir de aod_test_decomp_sections
 * côté PHP). Un binder listé ici doit être déclaré dans SON module et ne plus
 * l'être dans la coque.
 *
 * @return array<string,string[]> fichier => noms de binders extraits.
 */
function aod_test_djs_modules() {
	return array(
		'section-whatsapp.js'  => array( 'bindWhatsappTest' ),
		// `bindHomeCounters` RETIRÉ le 2026-08-06 : il n'a pas disparu, il a été
		// EXTRAIT vers `widget-counters.js` sous le nom `bindCounters` — le composant
		// KPI qu'il anime est partagé avec Stock, il n'appartenait donc pas au module
		// de l'Accueil. R2 garde les deux bouts : la fonction doit être dans le widget
		// et ne plus être dans la coque.
		'section-home.js'      => array( 'bindCharts' ),
		'section-marketing.js' => array( 'bindCapiTest', 'bindPixelCards' ),
		'section-account.js'   => array( 'bindResetShop' ),
		'section-stock.js'     => array( 'bindStock' ),
		'section-team.js'      => array( 'bindTeam' ),
		'section-tracking.js'  => array( 'bindTracking' ),
		'section-shipping.js'  => array( 'bindShippingFree', 'bindCarrierRows', 'bindCarrierTest', 'bindShippingSearch', 'bindImportFees' ),
		// bindOptions et bindOfferRows n'apparaissent pas dans ORDER : re-init
		// DYNAMIQUES, appelés via init() à l'intérieur de bindProducts (ajout de
		// lignes au formulaire produit). Ils vivent dans le module et R2 les garde.
		'section-products.js'  => array( 'bindProducts', 'bindOptions', 'bindOfferRows', 'bindCategories', 'bindColorPalette', 'bindCatDropdown', 'bindPromoRange', 'bindGallery' ),
		'section-landing.js'   => array( 'bindLanding' ),
		// La DERNIÈRE (33.6) — applyStatus et initOrderEdit sont des helpers INTERNES
		// au module (pas des binders) : R2 les garde aussi.
		// 47.3 — `bindOrderNote` et `submitOrderNote` RETIRÉS de cette table, et ce
		// n'est pas un assouplissement : ils n'ont pas été déplacés, ils ont été
		// SUPPRIMÉS. La note du confirmateur n'est plus une invite postée à un
		// handler dédié (`aod_cd_order_note`, qui écrivait dans l'historique Woo) mais
		// un champ du formulaire d'édition, enregistré avec le reste. Les laisser ici
		// exigerait de garder du code mort pour faire taire un cliquet — l'inverse de
		// ce qu'il garde. Leur disparition est gardée ailleurs, et fonctionnellement :
		// `test-note-livreur.php` R4 (`has_action()` + `method_exists()`).
		'section-orders.js'    => array( 'bindStatus', 'applyStatus', 'bindOrderDetail', 'initOrderEdit', 'bindOrderBulkDelete' ),
		// 🆕 2026-08-06 — LE PREMIER MODULE QUI N'EST PAS UNE SECTION, et le nom du
		// fichier le dit (`widget-`, pas `section-`). Le calendrier maison s'accroche
		// à tout `<input type="date">` : six pages le chargent, aucune ne le possède.
		// ⚠️ Il aurait pu vivre dans la COQUE au titre de « primitive partagée »
		// (l'argument de `toast()`, `sharedModal()` et `confirmModal()`) — c'est
		// justement pour ça qu'il n'y vit pas : ces trois-là sont des dizaines de
		// lignes, celui-ci en fait trois cents. Le plafond R1 aurait dû monter d'un
		// tiers pour un widget, ce qui est exactement le retour du fichier-dieu que
		// R1 existe pour empêcher. Un module à part coûte une ligne de chargement.
		'widget-datepicker.js' => array( 'bindDatePickers' ),
		'widget-counters.js'  => array( 'bindCounters' ),
	);
}

/* =========================================================================
 * R1 — le plafond de lignes de la coque
 * ========================================================================= */

AOD_Test::group( 'Décomposition JS 33 — R1 : la coque ne regrossit pas' );

// Plafond constaté le 2026-07-23, APRÈS la 33.1 (pilote whatsapp) : 3488 l
// d'origine → 3506 avec le registre (+ORDER +register, −bindWhatsappTest).
// Oui, la coque GROSSIT au pilote : le registre est un investissement payé une
// fois, remboursé dès les extractions suivantes. Descend à chaque étape.
// 33.2 (home, marketing, account, stock, team, tracking) : 3506 → 2948.
// 33.3 (shipping) : 2948 → 2561.
// 33.4 (products + catégories) : 2561 → 1539.
// 33.5 (landing) : 1539 → 1177.
// 33.6 (orders, la DERNIÈRE) : 1177 → 635. Le chantier est FINI : la coque
// n'est plus que les helpers partagés, init() (32.5), le registre + ORDER et
// les 4 binders transverses (nav, selects, accordéons, formulaires réglages).
// Ce plafond garde désormais contre un RETOUR du fichier-dieu JS.
// 39.6 : 635 → 636. +1 ligne = le nom du binder de section
// `bindAbandonedBulkDelete` ajouté à ORDER (la suppression groupée des prospects).
// Croissance COQUE légitime — un nom de binder de section, pas de logique
// réabsorbée (le corps vit dans section-abandoned.js) ; même geste que 39.1 côté
// PHP (933 → 935 pour bindAbandonedConvert + sa chaîne).
// 46.3 : 636 → 696. La région live du toast (`toastRegion()`, son appel au
// DOMContentLoaded, et le `toast()` qui n'écrit plus que dedans), plus le bloc de
// commentaire qui porte la règle — l'essentiel des +60.
// Croissance COQUE légitime, et c'est le cas d'école de ce que ce plafond
// AUTORISE : `toast()` est le canal de retour PARTAGÉ par les 11 modules de
// section, il ne peut vivre que dans la coque. Rien n'est réabsorbé — aucun
// module n'a maigri. Ce que le plafond interdit, c'est du code de SECTION qui
// remonte ici ; R2/R3 de ce même fichier gardent cette frontière-là, et eux ne
// bougent pas.
// ⚠️ Et la règle documentée est le genre qu'on ne redécouvre pas tout seul (un
// nœud rempli AVANT son insertion n'est jamais annoncé) : la tailler pour rentrer
// sous un compteur de lignes serait payer la lisibilité du garde-fou au prix de
// la connaissance qu'il protège.
// 47.4 : 696 → 752. `sharedModal()` — le pilote UNIQUE de la modale partagée
// `#aod-cd-modal` (ouverture, fermeture, piège de focus, `Échap`, délégation
// `[data-close]`, politique du fond) — plus le bloc de commentaire qui porte ses
// deux raisons d'être.
// Croissance COQUE légitime, et c'est le même cas que `toastRegion()` en 46.3 :
// l'élément est imprimé UNE fois en pied de page et DEUX modules de section se le
// partagent (`section-orders.js` pour le détail d'une commande, `section-tracking.js`
// pour la timeline) — il ne peut donc vivre que dans la coque. Rien n'est
// réabsorbé : les deux modules ont MAIGRI (1281 → 1272 et 147 → 140), leurs
// `open`/`close`/`Escape` locaux ont disparu au lieu d'être recopiés une
// troisième fois avec la politique du fond.
// ⚠️ La somme des trois fichiers MONTE malgré tout (2105 → 2161) et ce n'est pas
// un raté de la mutualisation : le code déplacé tient en ~44 lignes contre ~16
// supprimées, le reste est de la DOCUMENTATION que les deux copies n'avaient
// pas — dont la mesure qui décide de la forme du correctif (retirer `data-close`
// après le démarrage est INERTE, l'écouteur ayant été posé par référence). Même
// arbitrage qu'en 46.3 : tailler ce commentaire pour rentrer sous un compteur de
// lignes paierait la lisibilité du garde-fou au prix de la connaissance qu'il
// protège.
// REMONTÉE ASSUMÉE 752 → 830 (+78, étape 47.7) : `confirmModal()` et sa
// documentation. C'est LE cas d'école de « croissance COQUE légitime » : une
// primitive PARTAGÉE par 7 modules, qui remplace les 13 dialogues natifs du
// dépôt. L'écrire ailleurs qu'ici serait la faire recopier — la dérive que la
// Phase 46 a passé huit étapes à fermer.
// ⚠️ Et les MODULES ont maigri ou stagné, ce qui est la contrepartie attendue :
// ils perdent un appel bloquant et gagnent une continuation. Une hausse forte
// d'un `section-*.js` aurait signalé qu'on y avait recopié de la logique de
// fenêtre — ce plafond garde la coque, pas la propreté des modules.
// 🚨 La documentation pèse ici plus que le code, à dessein (même arbitrage qu'en
// 46.3 et 47.4) : le piège que ce docblock nomme — `if ( ! confirmModal(…) )`,
// toujours faux parce qu'une Promise est truthy — vaut 13 suppressions sans
// confirmation. Tailler le commentaire pour rentrer sous un compteur paierait
// la lisibilité du garde-fou au prix de la connaissance qu'il protège.
// REMONTÉE ASSUMÉE 830 → 840 (+10, 2026-08-10) : `bindSettingsForms()` honore
// enfin `res.data.reload`, et le commentaire pèse plus que le code — à dessein.
// Le drapeau était respecté par les binders de Livraison, Équipe et Suivi, et
// IGNORÉ par le générique : « Rentabilité par produit » le renvoyait, son bouton
// annonçait « Enregistrer et recalculer », et les bénéfices affichés restaient
// ceux d'avant la saisie. Croissance de COQUE légitime : c'est un contrat de
// réponse PARTAGÉ par tous les formulaires de réglages, l'écrire dans un module
// le ferait recopier — la dérive que la Phase 46 a passé huit étapes à fermer.
// (+1 ligne aussi pour `bindDepenses` dans ORDER : la liste est la coque.)
// REMONTÉE ASSUMÉE 840 → 850 (+10, 2026-08-11) : `bindSettingsForms()` cible le
// bouton RÉELLEMENT cliqué (`e.submitter`) et cherche son message dans SON pied.
// Croissance de COQUE légitime, et elle est née d'un défaut réel : la page
// Livraison porte désormais une barre d'enregistrement PAR FENÊTRE, et le
// `querySelector` d'origine désactivait le bouton de la première tout en
// écrivant la confirmation dans une fenêtre fermée — donc nulle part. Le binder
// est PARTAGÉ par tous les formulaires de réglages : l'écrire dans un module
// section-*.js le ferait recopier, la dérive que la Phase 46 a fermée.
// REMONTÉE ASSUMÉE 850 → 896 (+46, 2026-08-15) : `placeFloatingMenu()` — le
// placement UNIQUE des menus flottants (statut, assignation, transporteur,
// `.aod-cd-csel`, assignation de prospect) — plus le bloc de commentaire qui
// porte sa raison d'être, lequel pèse ici plus que le code.
// Croissance de COQUE légitime, et c'est le cas le plus net depuis `toast()` :
// le corps existait en CINQ COPIES identiques au caractère près (dashboard.js,
// section-orders.js ×3, section-abandoned.js). Les modules MAIGRISSENT — chacun
// passe de 13 lignes à une : la contrepartie attendue est bien là, rien n'a été
// réabsorbé.
// 🚨 Et il ne s'agit pas d'esthétique : ces cinq copies portaient toutes le même
// défaut, un menu qui s'ouvrait HORS DE L'ÉCRAN et devenait inatteignable dès que
// la page était trop courte pour défiler. `position:fixed` ne se résout par
// rapport au viewport que si aucun ancêtre ne porte `transform`/`filter`/
// `contain`/`will-change` — or `.aod-cd-table tbody tr` portait un
// `animation:…both` dont le `transform:none` retenu se résout en matrice
// identité. Mesuré : `top` demandé à 322 px, menu rendu à 939 px sous une fenêtre
// de 855. Corriger cinq copies aurait laissé la sixième naître sans le correctif.
// La règle que le docblock nomme ne se redécouvre pas toute seule ; la tailler
// pour rentrer sous un compteur paierait la lisibilité du garde-fou au prix de la
// connaissance qu'il protège (même arbitrage qu'en 46.3, 47.4 et 2026-08-10).
$aod_djs_plafond = 896;

$aod_djs_coque  = AOD_CD_PATH . 'assets/js/dashboard.js';
$aod_djs_src    = is_readable( $aod_djs_coque ) ? (string) file_get_contents( $aod_djs_coque ) : '';
// count( file() ) : la MÊME jauge que le cliquet PHP (test-decomposition.php) —
// deux jauges différentes pour le même plafond, c'est un faux rouge garanti.
$aod_djs_lignes = is_readable( $aod_djs_coque ) ? count( file( $aod_djs_coque ) ) : -1;

AOD_Test::ok(
	$aod_djs_lignes > 0,
	sprintf( 'TÉMOIN : dashboard.js lu (%d lignes)', $aod_djs_lignes ),
	'Fichier illisible : R1 verdirait sur du vide.'
);
AOD_Test::ok(
	$aod_djs_lignes <= $aod_djs_plafond,
	sprintf( 'R1 — dashboard.js à %d lignes (plafond %d, ne doit pas remonter)', $aod_djs_lignes, $aod_djs_plafond ),
	'La coque a REGROSSI. Du code de section va dans son module section-*.js, pas dans la coque.'
);
AOD_Test::ok(
	$aod_djs_lignes >= $aod_djs_plafond - 60,
	sprintf( 'R1 — le plafond suit la réalité : %d lignes pour un plafond à %d — descends-le', $aod_djs_lignes, $aod_djs_plafond ),
	'Le fichier a bien maigri : mets à jour le plafond vers le bas pour garder le cliquet serré.'
);

/* =========================================================================
 * R2 — un binder extrait vit dans son module, PAS dans la coque
 * ========================================================================= */

AOD_Test::group( 'Décomposition JS 33 — R2 : une section extraite ne réapparaît pas dans la coque' );

foreach ( aod_test_djs_modules() as $aod_djs_mod => $aod_djs_binders ) {
	$aod_djs_mf  = AOD_CD_PATH . 'assets/js/' . $aod_djs_mod;
	$aod_djs_msrc = is_readable( $aod_djs_mf ) ? (string) file_get_contents( $aod_djs_mf ) : '';

	AOD_Test::ok(
		'' !== $aod_djs_msrc,
		sprintf( '%s : le module existe et se lit', $aod_djs_mod ),
		'Module extrait introuvable : la section correspondante est morte au navigateur.'
	);

	foreach ( $aod_djs_binders as $aod_djs_b ) {
		AOD_Test::ok(
			false !== strpos( $aod_djs_msrc, 'function ' . $aod_djs_b . '(' ),
			sprintf( '%s : function %s() y est déclarée', $aod_djs_mod, $aod_djs_b ),
			'Binder attendu absent du module : l’extraction a perdu du comportement en route.'
		);
		AOD_Test::ok(
			false === strpos( $aod_djs_src, 'function ' . $aod_djs_b . '(' ),
			sprintf( '🧹 function %s() a bien QUITTÉ la coque (%s)', $aod_djs_b, $aod_djs_mod ),
			'Ré-absorption : la fonction est encore déclarée dans dashboard.js. Extraire, c’est déplacer — pas dupliquer.'
		);
	}
}

/* =========================================================================
 * R3 — ORDER ⇄ census des register() : rien de listé sans module, rien
 *      d'enregistré hors liste
 * ========================================================================= */

AOD_Test::group( 'Décomposition JS 33 — R3 : ORDER et le registre disent la même chose' );

preg_match( '/var ORDER = \[(.*?)\];/s', $aod_djs_src, $aod_djs_om );
preg_match_all( "/'(bind\w+)'/", isset( $aod_djs_om[1] ) ? $aod_djs_om[1] : '', $aod_djs_on );
$aod_djs_order = array_unique( $aod_djs_on[1] );

AOD_Test::ok(
	count( $aod_djs_order ) >= 25,
	sprintf( 'TÉMOIN : ORDER canonique lu (%d binders)', count( $aod_djs_order ) ),
	'ORDER introuvable dans la coque : le runner n’exécute plus rien — ou le motif a changé, adapter ce cliquet.'
);

/*
 * 🔁 LE BALAYAGE PORTE SUR `*.js` ET NON PLUS SUR `section-*.js` (2026-08-06).
 *
 * Ce n'est pas un assouplissement, c'est le trou que le motif d'origine laissait :
 * il recensait les `register()` des SECTIONS parce qu'à l'époque tout module en
 * était une. Un fichier nommé autrement — `widget-datepicker.js` est le premier —
 * pouvait enregistrer un binder sans que R3 le voie. Deux conséquences, opposées
 * et fausses toutes les deux : son nom listé dans ORDER passait pour « jamais
 * enregistré » (rouge à tort), et un binder enregistré SANS être dans ORDER
 * échappait au contrôle inverse (vert à tort, alors qu'il ne tourne qu'en « hors
 * liste » avec une erreur en console à chaque page).
 * ⚠️ `dashboard.js` est déjà dans `$aod_djs_tout` et le glob l'y remet : les
 * doublons sont absorbés par `array_unique()` juste en dessous. C'est voulu —
 * exclure la coque par son nom rouvrirait une liste écrite en dur, la forme même
 * du défaut qu'on corrige ici.
 */
$aod_djs_tout = $aod_djs_src;
foreach ( glob( AOD_CD_PATH . 'assets/js/*.js' ) as $aod_djs_f ) {
	$aod_djs_tout .= (string) file_get_contents( $aod_djs_f ) . "\n";
}
preg_match_all( "/register\( '(bind\w+)', /", $aod_djs_tout, $aod_djs_rm );
$aod_djs_regs = array_unique( $aod_djs_rm[1] );

$aod_djs_sans_module = array_diff( $aod_djs_order, $aod_djs_regs );
AOD_Test::ok(
	empty( $aod_djs_sans_module ),
	'🔴 Chaque nom d’ORDER est register()é quelque part (coque ou module)',
	implode( ', ', $aod_djs_sans_module ) . ' — « jamais enregistré » s’afficherait en console à CHAQUE page de /gestion'
);
$aod_djs_hors_liste = array_diff( $aod_djs_regs, $aod_djs_order );
AOD_Test::ok(
	empty( $aod_djs_hors_liste ),
	'🔴 Chaque binder register()é figure dans ORDER',
	implode( ', ', $aod_djs_hors_liste ) . ' — il ne s’exécuterait qu’en « hors liste » : l’ajouter à ORDER (dashboard.js)'
);

/* =========================================================================
 * R4 — tout fichier assets/js/*.js est chargé, la coque en premier
 * ========================================================================= */

AOD_Test::group( 'Décomposition JS 33 — R4 : aucun module orphelin, la coque charge en premier' );

$aod_djs_rm2 = new ReflectionMethod( 'AOD_CD_Dashboard', 'dashboard_scripts' );
$aod_djs_rm2->setAccessible( true );
$aod_djs_charges = (array) $aod_djs_rm2->invoke( AOD_CD_Dashboard::instance() );
$aod_djs_charges_noms = array_map( 'basename', $aod_djs_charges );

AOD_Test::ok(
	'dashboard.js' === reset( $aod_djs_charges_noms ),
	'La coque (dashboard.js) est chargée en PREMIER',
	'Un module chargé avant la coque lirait window.aodCdCore indéfini : TypeError sur toutes les pages.'
);

$aod_djs_reels = array_map( 'basename', glob( AOD_CD_PATH . 'assets/js/*.js' ) );
$aod_djs_orphelins = array_diff( $aod_djs_reels, $aod_djs_charges_noms );
AOD_Test::ok(
	empty( $aod_djs_orphelins ),
	'🔴 Chaque fichier de assets/js/ est dans dashboard_scripts()',
	implode( ', ', $aod_djs_orphelins ) . ' — module créé mais jamais chargé : sa section est morte en silence'
);
$aod_djs_fantomes = array_diff( $aod_djs_charges_noms, $aod_djs_reels );
AOD_Test::ok(
	empty( $aod_djs_fantomes ),
	'…et chaque fichier listé existe sur le disque',
	implode( ', ', $aod_djs_fantomes ) . ' — balise <script> vers un 404 à chaque page'
);
