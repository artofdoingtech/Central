<?php
/**
 * Cliquet sur la décomposition du fichier-dieu `class-aod-cd-dashboard.php`.
 *
 * CE QUE CE TEST GARDE. Le monolithe faisait 4229 lignes. Le chantier de la Phase
 * 30 le découpe en finissant le pattern `AOD_CD_Section_*` déjà appliqué à six
 * sections. Ce cliquet fait deux choses, à comportement constant :
 *
 *   R1 · PLAFOND DE LIGNES. dashboard.php ne doit plus GROSSIR. À chaque section
 *        extraite, on descend le plafond — un fichier-dieu ne se redécoupe que si
 *        rien ne le laisse regonfler entre-temps. (Même logique que le plancher de
 *        test-phpcs-ignore.php : baisser est un progrès, remonter exige de venir
 *        écrire ici pourquoi.)
 *
 *   R2 · PAS DE RÉ-ABSORPTION. Une section extraite vit dans SA classe et NE
 *        RÉAPPARAÎT PAS dans le monolithe. On garde donc les deux bouts : la classe
 *        de section porte bien ses méthodes, ET le Dashboard ne les porte plus.
 *        Sans ce second contrôle, on pourrait « extraire » en laissant l'original
 *        en place — un déplacement qui n'allège rien.
 *
 * ⚠️ Test STATIQUE et par RÉFLEXION : il lit les fichiers et interroge les classes
 * chargées. Il ne prouve pas le rendu (c'est le rôle de la porte `rtl` de la
 * recette, qui rend les 12 sections au navigateur) ni le comportement.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les sections extraites du monolithe, et le contrat de chacune.
 *
 * 🧩 CETTE TABLE GRANDIT À CHAQUE EXTRACTION. Un nouvel élément = une section de
 * plus sortie de dashboard.php. `dead` = les méthodes qui DEVAIENT quitter le
 * monolithe (leur présence sur AOD_CD_Dashboard est une ré-absorption).
 *
 * ⚠️ Préfixe `aod_test_decomp_` : `tests/all.php` charge tout dans le même espace.
 *
 * @return array<string,array{classe:string,vivantes:string[],mortes:string[]}>
 */
function aod_test_decomp_sections() {
	return array(
		'whatsapp' => array(
			'classe'   => 'AOD_CD_Section_WhatsApp',
			// Doivent exister sur la classe de section (le rendu + les 2 AJAX).
			'vivantes' => array( 'render', 'ajax_save_whatsapp', 'ajax_test_whatsapp' ),
			// Ne doivent PLUS exister sur AOD_CD_Dashboard (ré-absorption interdite).
			'mortes'   => array( 'section_whatsapp', 'whatsapp_settings', 'ajax_save_whatsapp', 'ajax_test_whatsapp' ),
		),
		'marketing' => array(
			'classe'   => 'AOD_CD_Section_Marketing',
			'vivantes' => array( 'render', 'ajax_save_pixels', 'ajax_test_capi' ),
			'mortes'   => array( 'section_marketing', 'pixels_settings', 'ajax_save_pixels', 'ajax_test_capi', 'clean_pixel_id', 'capi_token_from_post' ),
		),
		'account' => array(
			'classe'   => 'AOD_CD_Section_Account',
			'vivantes' => array( 'render', 'ajax_save_account', 'ajax_reset_shop' ),
			'mortes'   => array( 'section_account', 'render_reset_zone', 'ajax_save_account', 'ajax_reset_shop', 'reset_delete_orders', 'reset_delete_cod_leads' ),
		),
		'shipping' => array(
			'classe'   => 'AOD_CD_Section_Shipping',
			'vivantes' => array( 'render', 'ajax_save_shipping', 'ajax_import_fees', 'ajax_test_carrier', 'apply_margin', 'import_apply' ),
			'mortes'   => array( 'section_shipping', 'ajax_save_shipping', 'ajax_import_fees', 'ajax_test_carrier', 'apply_margin', 'import_apply' ),
		),
		'tracking' => array(
			'classe'   => 'AOD_CD_Section_Tracking',
			'vivantes' => array( 'render', 'ajax_tracking_refresh', 'ajax_tracking_detail', 'tracking_tone', 'tracking_pill' ),
			// Les deux helpers de PÉRIMÈTRE (`scoped_orders`, `order_in_scope`) ne
			// figurent PAS ici : restés au Dashboard à dessein jusqu'à la 30.6,
			// ils sont partis avec `orders` (voir l'entrée suivante).
			'mortes'   => array( 'section_tracking', 'tracking_state_choices', 'render_tracking_filters', 'render_tracking_row', 'tracking_tone', 'tracking_pill', 'tracking_pill_html', 'tracking_updated_at', 'render_tracking_timeline', 'ajax_tracking_refresh', 'ajax_tracking_detail' ),
		),
		'orders' => array(
			// 30.6 — la DERNIÈRE extraction : le pattern est fini, le Dashboard
			// n'est plus qu'une coque + routeur + composants d'UI partagés.
			// ⚠️ Ne pas confondre avec `AOD_CD_Orders` (lots, 31.6), sans rapport.
			'classe'   => 'AOD_CD_Section_Orders',
			// Le rendu, les AJAX, le périmètre produits (public : Tracking et
			// Home s'en servent) et le hook de cycle de vie du pack assortiment.
			// 47.3 — `ajax_order_note` RETIRÉ de `vivantes` : la méthode n'a pas
			// déménagé, elle a été SUPPRIMÉE (la note du confirmateur est un champ du
			// formulaire d'édition, plus une invite écrivant dans l'historique Woo).
			// Elle reste dans `mortes` ci-dessous, et c'est toujours VRAI : elle
			// n'existe pas dans la coque. Une méthode supprimée satisfait « morte »
			// aussi bien qu'une méthode déplacée — la règle interdit son RETOUR dans
			// le fichier-dieu, ce qui reste exactement ce qu'on veut.
			'vivantes' => array( 'render', 'ajax_order_status', 'ajax_order_detail', 'ajax_order_save_info', 'ajax_delete_orders', 'scoped_orders', 'order_in_scope', 'product_orders_join_sql', 'reduce_pack_components_stock' ),
			'mortes'   => array( 'section_orders', 'render_order_row', 'status_select_html', 'item_variants', 'order_articles_html', 'ship_cell_html', 'status_labels', 'all_order_status_keys', 'unshipped_meta_query', 'count_orders', 'product_scope', 'order_in_scope', 'scoped_orders', 'scope_join_sql', 'product_orders_join_sql', 'filter_scope_clauses', 'filter_scope_clauses_cpt', 'ajax_order_status', 'ajax_delete_orders', 'ajax_order_note', 'ajax_order_detail', 'ajax_order_save_info', 'resolve_item_options', 'orderable_catalog', 'tier_unit_price', 'render_order_edit_form', 'render_order_detail', 'reduce_pack_components_stock' ),
		),
	);
}

/* =========================================================================
 * R1 — le plafond de lignes du monolithe
 * ========================================================================= */

AOD_Test::group( 'Décomposition 30 — R1 : le fichier-dieu ne regrossit pas' );

// Plafond constaté le 2026-07-22. Descend à chaque section sortie du monolithe :
// 4229 → 4107 (30.1 WhatsApp) → 3764 (30.2 Marketing) → 3425 (30.3 Account)
// → 2836 (30.4 Shipping) → 2397 (30.5 Tracking). Remonter exige de s'expliquer ici.
//
// Deux REMONTÉES ASSUMÉES ont vécu entre la 30.4 et la 30.5 — soldées comme
// annoncé, les lignes sont bien parties avec la section `tracking` :
//   · 2836 → 2849 (+13, étape 32.1) : gardes `AOD_CD_Cod::active()` sur
//     `tracking_tone()`/`tracking_pill()` — sans elles, /gestion mourait
//     quand `aod-cod-form` était désactivé.
//   · 2849 → 2884 (+35, étape 32.4) : `ajax_tracking_refresh()` par tranches
//     (l'auto-DoS D23) — écrit dans le monolithe parce que la section n'était
//     pas encore extraite.
// La 30.5 emporte le tout : 2884 → 2397 (−487, plus que les ~360 l de rendu :
// s'y ajoutent les 2 AJAX et les commentaires de section ; +6 l de docblocks sur
// `scoped_orders()`/`order_in_scope()`, passées `public` avec leur raison).
// 30.6 (orders, la DERNIÈRE) : 2397 → 900 (−1497 : rendu + 5 AJAX + modale de
// détail + périmètre produits + pack assortiment). Le chantier est FINI : ce qui
// reste est la coque (document + routeur) et les composants d'UI partagés
// (`page_head`, `kpis`, `pill`, `empty_state`, `render_pair`, recherche,
// pagination). Ce plafond n'a plus vocation à descendre par extraction — il
// garde désormais le fichier contre un RETOUR du fichier-dieu.
// REMONTÉE ASSUMÉE 900 → 923 (+23, étape 33.1) : `dashboard_scripts()` + la
// boucle de chargement des modules JS — du code de COQUE (c'est le chargeur),
// pas une ré-absorption de section. Puis +1 ligne par module listé, borné par
// construction : 12 modules à terme (11 sections + la coque) → plafond posé à
// l'ÉTAT FINAL (933) pour ne pas remonter ce chiffre à chaque étape 33.x. Toute
// remontée AU-DELÀ redevient inexplicable et doit venir s'écrire ici.
// REMONTÉE ASSUMÉE 933 → 935 (+2, étape 39.1) : la section « Commandes
// abandonnées » gagne son PREMIER binder (conversion abandon → commande), donc un
// 12ᵉ module JS. Les deux lignes sont de niveau COQUE, pas de la logique de section
// réabsorbée : +1 pour `section-abandoned.js` dans le manifeste `dashboard_scripts()`
// (même précédent que la +23 de 33.1), +1 pour sa chaîne de confirmation
// `i18nConvertConfirm` dans l'objet `window.AOD_CD` — au même rang que les 15 autres
// `i18n*Confirm`, qui vivent toutes dans la coque par construction. Le code neuf
// (bouton, AJAX `ajax_convert_lead`, squelette) est, lui, dans la section.
// REMONTÉE ASSUMÉE 935 → 936 (+1, étape 39.6) : la suppression groupée des
// prospects ajoute sa chaîne de confirmation `i18nLeadBulkDelConfirm` dans l'objet
// `window.AOD_CD` — même rang COQUE que les autres `i18n*Confirm`. Le code neuf
// (barre, cases, AJAX `ajax_delete_leads`, binder JS) est, lui, dans la section.
// REMONTÉE ASSUMÉE 936 → 1052 (+116, étape 43.1) : QUATRE helpers partagés de
// filtre date (`parse_date_param()`, `resolve_date_range()`,
// `date_created_query_arg()`, `render_date_fields()`), au même rang que
// `render_search_bar()`/`render_pagination()` juste au-dessus — des composants
// d'UI/utilitaires PARTAGÉS entre plusieurs sections (Commandes, Suivi,
// Abandonnées, et Home qui délègue désormais sa validation de date), pas la
// logique propre à une seule section. Rien de réabsorbé : le code métier de
// chaque page (lecture des filtres, requêtes) reste dans sa propre classe.
//
// REMONTÉE ASSUMÉE 1052 → 1055 (+3, étape 43.5) : DEUX clés `i18n*` de
// `window.AOD_CD` (`i18nBulkAssignSkipped`, `i18nBulkAssignOverwriteConfirm`) et
// leur commentaire. Ce n'est PAS de la ré-absorption : la coque est propriétaire
// de l'objet i18n JS — c'est elle, et elle seule, qui l'imprime (`dashboard_scripts()`),
// aux côtés des ~60 autres clés. Une chaîne consommée par un module `section-*.js`
// n'a aucun autre endroit où être déclarée ; l'une des deux réparait d'ailleurs
// une clé consommée sans jamais avoir été déclarée (43.5, piège 3). La logique
// métier de l'écrasement groupé, elle, est intégralement dans
// `AOD_CD_Section_Orders::bulk_assign()`.
//
// REMONTÉE ASSUMÉE 1055 → 1057 (+2, étape 47.1) : UNE clé `i18n*` de
// `window.AOD_CD` (`i18nBulkCarrierSkipped`) et son commentaire. Rigoureusement
// le même cas que la remontée précédente, et pour la même raison : la coque est
// PROPRIÉTAIRE de l'objet i18n JS — c'est `dashboard_scripts()`, et elle seule,
// qui l'imprime. Une chaîne consommée par `section-orders.js` n'a aucun autre
// endroit où être déclarée ; la déclarer AVEC sa consommation est précisément
// le correctif de la 43.5 (une clé consommée sans jamais avoir été déclarée
// affichait son repli français y compris en arabe, et n'entrait dans aucun
// .pot). La logique du routage groupé, elle, est intégralement dans
// `AOD_CD_Section_Orders::bulk_carrier()`, et la résolution du transporteur
// dans `AOD_Shipping::carrier_for()` — l'autre plugin.
// REMONTÉE ASSUMÉE 1057 → 1072 (+15, étape 47.2) : DOUZE clés `i18n*` de la
// fenêtre d'envoi au transporteur + `maxLotEnvoi` + leur commentaire. Même rang
// COQUE que les remontées de 39.1, 39.6, 43.5 et 47.1 — `dashboard_scripts()` est
// PROPRIÉTAIRE de l'objet i18n JS, il n'existe aucun autre endroit où déclarer
// une chaîne consommée par un `section-*.js`, et la 43.5 a montré le coût de ne
// PAS la déclarer (repli français affiché jusqu'en arabe, absent du .pot).
// ⚠️ `maxLotEnvoi` n'est pas une chaîne : c'est le plafond de sélection d'un lot
// d'envoi, LU depuis `AOD_CD_Section_Orders::MAX_LOT_ENVOI` au lieu d'être
// recopié en JS — l'écran et le handler doivent refuser au MÊME seuil, sinon
// l'un des deux ment. C'est précisément pour éviter une seconde source de vérité
// que cette ligne est ici plutôt que dans le module.
// La logique du lot, elle, est intégralement dans `section-orders.js`
// (`lancerLot()`) et `AOD_CD_Section_Orders::ajax_ship_send()`.
//
// REMONTÉE ASSUMÉE 1072 → 1077 (+5, étape 47.6) : `maxUpload`, `i18nTooBig` et
// leur commentaire de trois lignes. MÊME rang COQUE que les six remontées
// ci-dessus — `dashboard_scripts()` est propriétaire de l'objet i18n JS, et une
// valeur consommée par `section-products.js` n'a aucun autre endroit où être
// déclarée. `maxUpload` relève exactement du cas `maxLotEnvoi` de la 47.2 : ce
// n'est pas une chaîne, c'est une BORNE lue depuis sa source
// (`AOD_CD_Upload::max_bytes()`) au lieu d'être recopiée en JS. Le contrôle
// client et le refus serveur doivent parler du MÊME chiffre — sinon l'un des
// deux ment, et c'est l'écran qui ment, ce qui est le pire des deux.
// ⚠️ Ce fichier était EXACTEMENT à son plafond (1072 pour 1072) : le second
// garde-fou (« le plafond suit la réalité à 40 lignes près ») interdisait aussi
// de le descendre. L'arbitrage est celui de la 47.4 (696 → 752 sur
// `dashboard.js`) et de la 46.3 : on monte le plafond en écrivant pourquoi,
// jamais on ne taille un commentaire pour rentrer sous un compteur.
// La logique de la borne, elle, est intégralement dans `AOD_CD_Upload` — cinq
// lignes de coque contre une classe entière ailleurs.
//
// REMONTÉE ASSUMÉE 1077 → 1081 (+4, étape 47.7) : `i18nConfirm`,
// `i18nConfirmTitle` et leurs deux commentaires, MOINS le doublon `i18nCancel`
// retiré. Même rang COQUE que les sept remontées ci-dessus — `dashboard_scripts()`
// est propriétaire de l'objet i18n JS, et une chaîne consommée par la primitive
// partagée `confirmModal()` n'a aucun autre endroit où être déclarée.
// 🔎 Le retrait du doublon vaut d'être noté : `i18nCancel` était déclaré DEUX
// fois dans le MÊME objet littéral, la 47.3 l'ayant ajouté sans voir celui qui
// existait 65 lignes plus bas. Sans effet visible (même valeur, la seconde
// écrasait la première) — mais c'est exactement le motif que ce plafond existe
// pour ralentir.
//
// REMONTÉE ASSUMÉE 1081 → 1083 (+2, revue de la Phase 47) : `i18nShipAbandoned`
// et son commentaire `translators`. Même rang COQUE que les remontées ci-dessus.
// La chaîne existe parce que le compte rendu d'un lot ARRÊTÉ ne disait pas ce
// qu'il n'avait pas tenté : « 5 envoyée(s), 0 en échec » sur un lot de 30
// interrompu à la 5ᵉ — exact, et lu comme « tout est parti ». La phrase manquante
// est consommée par `lancerLot()`, qui n'a aucun autre endroit où lire une chaîne.
//
// REMONTÉE ASSUMÉE 1083 → 1087 (+4, chantier du 2026-08-06) :
// `dashboard_scripts()` gagne DEUX modules — `widget-datepicker.js` (le
// calendrier maison) et `widget-counters.js` (le décompte des nombres, extrait de
// `section-home.js` parce que le composant KPI qu'il anime est partagé avec
// Stock) — plus deux lignes de commentaire disant pourquoi ces modules ne sont
// PAS des sections. Même rang COQUE que les remontées ci-dessus, et la plus
// contrainte de toutes : R4 de `test-decomposition-js.php` EXIGE que tout fichier
// de `assets/js/` figure dans cette liste — un module non listé serait une
// section morte en silence. Il n'y a donc pas d'autre endroit où l'écrire.
// 🔎 Et le compte NET du dépôt descend : `section-home.js` perd 85 lignes en
// rendant `bindCounters`. Ce plafond garde une COQUE, pas un total.
// 🔎 Ce qui n'a PAS coûté de ligne, et c'est la contrepartie : les deux autres
// besoins PHP du calendrier (l'icône `calendar` exposée au JS, et le tout du
// bilinguisme) sont entrés SANS en ajouter — la première sur la ligne existante
// de `js_map()`, le second nulle part, les noms de mois et de jours venant
// d'`Intl` dans la langue de la page plutôt que de douze chaînes à traduire.
$aod_decomp_plafond = 1087;

$aod_decomp_fichier = AOD_CD_PATH . 'includes/class-aod-cd-dashboard.php';
$aod_decomp_lignes  = is_readable( $aod_decomp_fichier ) ? count( file( $aod_decomp_fichier ) ) : -1;

AOD_Test::ok(
	$aod_decomp_lignes > 0,
	sprintf( 'Témoin : class-aod-cd-dashboard.php lu (%d lignes)', $aod_decomp_lignes ),
	'Fichier illisible : R1 verdirait sur du vide.'
);
AOD_Test::ok(
	$aod_decomp_lignes <= $aod_decomp_plafond,
	sprintf( 'R1 — dashboard.php à %d lignes (plafond %d, ne doit pas remonter)', $aod_decomp_lignes, $aod_decomp_plafond ),
	'Le monolithe a REGROSSI. Une section extraite ne doit rien y réintroduire ; '
		. 'du code neuf va dans sa section, pas dans le fichier-dieu.'
);
// Un plafond qu'on oublie de descendre perd son mordant : on signale l'écart.
AOD_Test::ok(
	$aod_decomp_lignes >= $aod_decomp_plafond - 40,
	sprintf( 'R1 — le plafond suit la réalité : %d lignes pour un plafond à %d — descends-le', $aod_decomp_lignes, $aod_decomp_plafond ),
	'Le fichier a bien maigri : mets à jour le plafond vers le bas pour garder le cliquet serré.'
);

/* =========================================================================
 * R2 — chaque section extraite vit dans sa classe, PAS dans le monolithe
 * ========================================================================= */

AOD_Test::group( 'Décomposition 30 — R2 : une section extraite ne réapparaît pas dans le monolithe' );

foreach ( aod_test_decomp_sections() as $aod_decomp_slug => $aod_decomp_c ) {
	$aod_decomp_cls = $aod_decomp_c['classe'];

	AOD_Test::ok(
		class_exists( $aod_decomp_cls ),
		sprintf( '%s : la classe de section %s est chargée', $aod_decomp_slug, $aod_decomp_cls ),
		'La section a été extraite : sa classe doit exister et être requise au boot.'
	);

	foreach ( $aod_decomp_c['vivantes'] as $aod_decomp_m ) {
		AOD_Test::ok(
			method_exists( $aod_decomp_cls, $aod_decomp_m ),
			sprintf( '%s::%s() existe (le comportement est bien passé dans la section)', $aod_decomp_cls, $aod_decomp_m ),
			'Méthode attendue absente : l’extraction a perdu du comportement en route.'
		);
	}

	foreach ( $aod_decomp_c['mortes'] as $aod_decomp_m ) {
		AOD_Test::ok(
			! method_exists( 'AOD_CD_Dashboard', $aod_decomp_m ),
			sprintf( '🧹 AOD_CD_Dashboard::%s() a bien QUITTÉ le monolithe (%s)', $aod_decomp_m, $aod_decomp_slug ),
			'Ré-absorption : la méthode est encore sur le fichier-dieu. Extraire, c’est '
				. 'déplacer — pas dupliquer en laissant l’original.'
		);
	}
}
