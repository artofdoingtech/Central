<?php
/**
 * Cliquet sur les `phpcs:ignore` — la 171ᵉ annotation ne passera pas en silence.
 *
 * CE QUE CE TEST REMPLACE. Les annotations `phpcs:ignore` désarment un contrôle de
 * sécurité statique, une ligne à la fois. Le dépôt en compte beaucoup et les a
 * auditées **à la main** (2026-07-19, puis étape 18.3) — 0 faille. Mais un audit
 * est un instantané : rien n'empêchait la suivante d'arriver non relue. C'est la
 * dette **D6**, et c'est la forme la plus pure du défaut que ce dépôt traque depuis
 * la Phase 17 : un contrôle humain sans cliquet ne protège que le passé.
 *
 * 🚨 CE QUE L'AUDIT PASSÉ NE COUVRAIT PAS — et que la dette annonçait mal.
 * D6 parlait de « **130** annotations toutes relues, 0 faille ». C'est exact, et
 * c'est trompeur : **130 est le compte de la seule famille `EscapeOutput`**. Le
 * dépôt en portait **170**. Les **40** autres — `NonceVerification` (CSRF),
 * `ValidatedSanitizedInput` (entrée non sanitisée), `WordPress.DB` (SQL) — les
 * familles les **plus** sensibles, n'étaient dans le périmètre d'**aucun** audit.
 * Un chiffre qui sonne comme une couverture totale mais nomme un sous-ensemble :
 * exactement le mode d'échec de la « règle du témoin ».
 *
 * LE CONTRAT, en trois règles mécaniques :
 *
 *   R1 · AUCUNE annotation NUE. `// phpcs:ignore` sans nom de sniff désarme **tous**
 *        les contrôles de la ligne, pas un seul — un blanc-seing, là où on croyait
 *        lever une exception précise. Règle ABSOLUE : zéro, aujourd'hui et demain.
 *
 *   R2 · Le CENSUS par famille est déclaré ici. Un ajout fait diverger le compte et
 *        **rougir** : personne n'ajoute une suppression de contrôle CSRF sans qu'un
 *        humain touche ce fichier.
 *
 *   R3 · Le stock d'annotations SANS raison (`-- …`) ne remonte pas.
 *
 * ⚠️ POURQUOI R3 EST UN PLANCHER ET NON UN ZÉRO. 142 des 170 annotations ne portent
 * pas de raison. Exiger zéro imposerait un diff mécanique de 142 lignes **dans le
 * code de production**, sans le moindre changement de comportement, pour satisfaire
 * un test : la queue qui remue le chien, et un risque bien réel pour un gain
 * documentaire. On fige donc le stock au lieu de le nier — la prochaine devra
 * s'expliquer. Baisser ce plancher est un progrès libre ; le remonter, non.
 *
 * ⚠️ R2 n'est PAS un test de qualité, c'est un RALENTISSEUR DÉLIBÉRÉ. Si un jour
 * quelqu'un ajuste le chiffre sans lire la ligne ajoutée, le cliquet n'aura servi à
 * rien — mais il aura au moins exigé le geste.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Census attendu, par famille de sniff.
 *
 * ⚠️ Préfixe `aod_test_pcs_` : `tests/all.php` charge tous les fichiers par
 * `glob()` dans le même espace global.
 *
 * Les familles de SÉCURITÉ sont marquées : elles désarment un contrôle qui protège
 * l'utilisateur, pas un contrôle de style. Une hausse y est un événement.
 *
 * @return array<string,array{n:int,securite:bool,quoi:string}>
 */
function aod_test_pcs_census() {
	return array(
		'WordPress.Security.EscapeOutput'         => array(
			// 163 → 160 le 2026-08-09 (refonte de « Pixels & Tracking »). ⬇️ Une BAISSE,
			// et elle n'achète aucune dérogation nouvelle : les trois cartes CAPI
			// (Meta / TikTok / Snapchat) étaient trois blocs jumeaux recopiés, portant
			// chacun le MÊME jeu d'annotations sur les mêmes appels (`AOD_CD_Icon::svg`,
			// `AOD_CD_Dashboard::pill`). Une seule boucle les rend désormais, donc un
			// seul jeu. Deux dérogations apparaissent en regard sur le helper `stage_open()`
			// — l'icône du catalogue fermé et le bloc déjà échappé qu'il rend — soit un
			// solde de −3. 🚨 Ce que ça vaut au-delà du chiffre : trois copies d'une même
			// dérogation, c'est trois endroits où la relire, et deux où oublier de la
			// corriger. Le census n'a jamais su dire ça — il compte, il ne déduplique pas.
			//
			// 160 → 163 le 2026-08-09 (refonte de « WhatsApp »). ⬆️ Une HAUSSE, donc
			// elle se justifie ici, et les 161ᵉ à 163ᵉ le sont par le MÊME motif : les
			// trois appels `AOD_CD_Dashboard::pill()` de la carte d'état (« Actives »,
			// « Numéro ou clé manquant », « Désactivées »). C'est le patron déjà couvert
			// par les dérogations de « Pixels & Tracking » sur ce même helper, et il est
			// sûr PAR CONSTRUCTION, pas par relecture : `pill()` échappe son libellé par
			// `esc_html()` et son ton par `esc_attr()` sur une LISTE BLANCHE de cinq
			// valeurs (`ok|warn|bad|info|neutral`, repli `neutral`). Aucun des trois
			// n'est de la donnée saisie : ce sont des littéraux traduits.
			// ⚠️ Les trois sont rendues EN MÊME TEMPS et départagées en CSS — donc
			// l'écran n'en montre qu'une, mais le HTML en porte trois. Un futur lecteur
			// qui compterait les pastilles à l'écran croirait ce census faux d'un
			// facteur trois.
			//
			// 163 → 167 le 2026-08-10 (les régies ouvrent une fenêtre). ⬆️ Une HAUSSE
			// de QUATRE, toutes du même motif et toutes déjà couvertes ailleurs dans
			// ce fichier : les en-têtes des deux `<dialog>` (cartes pixel et cartes
			// CAPI) portent chacun `AOD_CD_Icon::svg()` pour l'écusson de marque et
			// `AOD_CD_Icon::svg( 'close' )` pour la croix de fermeture. `AOD_CD_Icon`
			// est un CATALOGUE FERMÉ : `svg()` ne rend que des fichiers du dépôt,
			// résolus par un nom validé contre une liste — aucune donnée saisie n'y
			// entre, et `tests/unit/test-icones.php` garde cette fermeture.
			// ⚠️ Ces quatre-là existent parce que l'en-tête de la ligne est REDIT dans
			// la fenêtre. C'est voulu (c'est ce qui fait reconnaître la régie qu'on
			// vient de cliquer) mais ça se paie ici : redire un fragment, c'est
			// redire ses dérogations.
			//
			// 167 → 168 le 2026-08-10 (bandeau de la page Suivi commande). ⬆️ Une
			// HAUSSE d'UNE, sur `render_tracking_pulse()`, qui appelle le composant
			// partagé `AOD_CD_Dashboard::kpis()` — même motif que les appels à
			// `page_head()` et `empty_state()` déjà recensés ici : le composant
			// échappe LUI-MÊME chacune de ses entrées (`esc_html` sur les libellés,
			// `wp_kses_post` sur les valeurs, `esc_url` sur les URLs, `esc_attr` sur
			// la tonalité), et rend du balisage que `esc_html()` détruirait.
			// ⚠️ Ce que l'appelant lui passe est vérifiable d'un coup d'œil : trois
			// libellés `__()`, trois entiers castés en chaîne, trois noms d'icône du
			// catalogue fermé, et des URLs construites par `add_query_arg()` sur
			// `home_url()`. Aucune donnée saisie par un utilisateur n'entre là.
			//
			// 168 → 167 le 2026-08-10 : « Devise de reporting » quitte la page
			// Marketing (demande owner) et emporte l'annotation posée sur son appel
			// à `stage_open()`. ⬇️ Une BAISSE, donc la direction sûre — un contrôle
			// revient, il n'en part pas. Elle est notée quand même : ce cliquet
			// existe pour qu'aucun mouvement du census n'arrive en silence, et un
			// chiffre qui descend sans explication se relit, dans six mois, comme
			// un chiffre qu'on a ajusté pour faire passer le test.
			//
			// 167 → 176 le 2026-08-10 (section Finance). ⬆️ Une hausse de NEUF, toutes
			// du même motif déjà recensé plus haut : `AOD_CD_Icon::svg()` (catalogue
			// FERMÉ — un nom validé contre une liste, aucune donnée saisie n'y entre)
			// et les composants partagés `page_head()` / `empty_state()` / `wc_price()`,
			// qui échappent EUX-MÊMES leurs entrées et rendent du balisage que
			// `esc_html()` détruirait.
			// ⚠️ AUCUNE ne couvre une donnée saisie par un utilisateur. Le seul contenu
			// libre de cette section est le NOM d'un calcul enregistré : il passe par
			// `sanitize_text_field()` à l'écriture et par `esc_html()` à l'affichage —
			// il n'y a pas de dérogation sur ce chemin, et c'est délibéré.
			// 176 → 180 le 2026-08-10 (« Rentabilité par produit »). ⬆️ QUATRE de plus,
			// même motif que les neuf précédentes : `page_head()`, `empty_state()`,
			// `AOD_CD_Icon::svg()` (catalogue fermé) et `wc_price()`, qui échappent
			// eux-mêmes leurs entrées. ⚠️ Les NOMS DE PRODUITS de cet écran, eux,
			// passent par `esc_html()` sans dérogation — c'est le seul texte de la
			// page qui vienne d'une saisie.
			//
			// 180 → 189 le 2026-08-10 (module « Dépenses »). ⬆️ NEUF de plus, et pas
			// un motif nouveau : `page_head()` et `empty_state()` (composants qui
			// échappent eux-mêmes leurs entrées), `AOD_CD_Icon::svg()` × 4 (catalogue
			// FERMÉ), `AOD_CD_Dashboard::kpis()` (même motif que le bandeau de Suivi,
			// recensé plus haut), et les DEUX de `render_depenses_bar()` — la barre
			// « Où part l'argent » et sa légende, assemblées en PHP.
			// ⚠️ CES DEUX-LÀ MÉRITENT D'ÊTRE LUES : ce sont les seules du lot qui
			// concatènent du balisage au lieu d'appeler un composant. Tout ce qui y
			// est variable passe par un échappement à la construction — libellés de
			// postes par `esc_html()` (et ce sont des littéraux `__()`, pas de la
			// saisie), largeurs par `number_format()` sur un flottant, indice de
			// couleur par un cast `(int)`, montants par `wp_kses_post( wc_price() )`.
			// 🔒 Aucune donnée saisie n'atteint ces deux `echo` : les postes viennent
			// d'une LISTE FERMÉE (`depenses_cats()`), et le libellé libre d'une
			// dépense — le seul texte saisi de l'écran — n'y entre pas du tout. Il est
			// rendu dans le tableau, par `esc_html()`, sans dérogation.
			//
			// 189 → 193 le 2026-08-11 (fiche « Entreprise »). ⬆️ QUATRE de plus,
			// toutes du motif déjà recensé : `page_head()` (composant qui échappe
			// lui-même ses entrées) et `AOD_CD_Icon::svg()` × 3 (catalogue FERMÉ —
			// un nom validé contre une liste, aucune donnée saisie n'y entre).
			// ⚠️ Aucune ne couvre un champ de la fiche. Raison sociale, RC, NIF, RIB
			// et l'adresse publique sont TOUS rendus par `esc_attr()` dans la valeur
			// d'un `<input>`, sans dérogation — et c'est le contenu le plus sensible
			// de l'écran : une dérogation sur ce chemin ferait d'un champ de saisie
			// gérant un vecteur d'injection stockée.
			//
			// 193 → 192 le 2026-08-11 (Livraison passe en cartes + fenêtres). ⬇️ Une
			// BAISSE d'UNE, donc la direction sûre — un contrôle revient, il n'en part
			// pas. Elle est notée quand même : ce cliquet existe pour qu'aucun
			// mouvement du census n'arrive en silence, et un chiffre qui descend sans
			// explication se relit, dans six mois, comme un chiffre ajusté pour faire
			// passer le test.
			// Cause : les quatre en-têtes d'accordéon portaient chacun leur appel
			// `AOD_CD_Icon::svg()` avec sa dérogation, écrits QUATRE FOIS. Ils passent
			// par `card_open()`, un seul corps — qui en porte trois (l'écusson de la
			// carte, celui de la fenêtre, la croix de fermeture) pour les quatre
			// cartes. 🚨 Ce que ça vaut au-delà du chiffre : quatre copies d'une même
			// dérogation, c'est quatre endroits où la relire et trois où oublier de la
			// corriger. Le census n'a jamais su dire ça — il compte, il ne déduplique
			// pas.
			//
			// 192 → 193 le 2026-09-13 (volet « Description du produit » sous la
			// galerie, class-aod-cod-form.php). ⬆️ UNE de plus :
			// `render_gallery_description()` imprime `wp_kses_post( $html )`, où
			// `$html = apply_filters( 'woocommerce_short_description', $post->post_excerpt )`
			// — EXACTEMENT l'appel que `single-product/short-description.php` du
			// cœur Woo fait déjà, sans dérogation là-bas puisque ce n'est pas nous
			// qui l'imprimons d'habitude. Le déplacement sous la galerie ne change
			// ni la donnée (la description que le gérant a écrite, pas une saisie
			// visiteur) ni l'échappement (`wp_kses_post()`, même filtre que le
			// template natif) — seul l'endroit où ce même contenu de confiance
			// s'imprime a changé.
			'n'        => 193,
			'securite' => true,
			'quoi'     => 'Sortie non échappée (XSS). 130 relues à la main (97 le 2026-07-19, 33 en 18.3) + la 131ᵉ : '
				. 'l’annotation NUE de section-products.php:500, resserrée sur ce sniff le 2026-07-21. '
				. '🚨 Elle avait échappé à l’audit de la 18.3 pour une raison instructive — cet audit grepait '
				. 'PAR NOM DE SNIFF, et une annotation nue n’en porte aucun. Le scénario « 131ᵉ annotation non '
				. 'relue » que D6 annonçait était donc DÉJÀ RÉALISÉ, dans l’angle mort de l’audit lui-même. '
				. 'La 133ᵉ (étape 29.1, class-aod-cd-access.php) : `echo AOD_CD_Customize::css_vars()` sur la '
				. 'page login — MÊME motif, MÊME sûreté que dashboard.php:394 (valeurs hex issues de '
				. 'sanitize_hex_color / formatage interne, aucune donnée externe). '
				. 'La 134ᵉ (étape 35.1, class-aod-cd-section-shipping.php, carte Panier) : '
				. '`echo AOD_CD_Icon::svg( \'cart\' )` — catalogue fermé, sûreté démontrée une fois pour '
				. 'tous les appels (18.3) et gardée par test-icones.php ; annotation AVEC raison (R3 stable). '
				. 'La 135ᵉ (étape 36.3, class-aod-cod-cart.php, suggestions) : `echo $p->get_image()` — '
				. 'markup d’image généré par le cœur Woo (wp_get_attachment_image, attributs échappés par le '
				. 'cœur), aucune donnée externe ; annotation AVEC raison. '
				. 'Les 136ᵉ et 137ᵉ (étape 37.2, class-aod-cod-cart.php, bouton flottant + fermeture du '
				. 'panneau) : `echo AOD_COD_Icon::svg( … )` — MÊME motif et MÊME sûreté que la 134ᵉ, à ceci '
				. 'près que le catalogue fermé est celui du PUBLIC (AOD_COD_Icon, jumeau de AOD_CD_Icon : les '
				. 'deux plugins ne peuvent pas partager la classe, la dépendance ne va que du dashboard vers '
				. 'le formulaire COD). Attributs RECONSTRUITS au rendu, contenu lu dans des fichiers du '
				. 'dépôt, jamais d’une saisie ; un nom hors catalogue rend \'\' et crie. Gardé par '
				. 'test-panier-peau.php (R6 : les SVG copiés sont identiques à ceux du dashboard). '
				. 'Les 138ᵉ à 140ᵉ (étape 38.3, class-aod-cod-form.php, modes de livraison + réassurance) : '
				. '`echo AOD_COD_Icon::svg( \'home\' | \'desk\' | \'lock\' )` — elles REMPLACENT les emojis '
				. '🏠 🏢 🔒 par le même catalogue fermé public que les 136ᵉ/137ᵉ, MÊME motif et MÊME sûreté ; '
				. 'un emoji était rendu par l’OS, donc invisible en tant qu’icône dessinée. Trois icônes '
				. 'ajoutées au catalogue (house/storefront/lock), copiées verbatim de /gestion et gardées '
				. 'octet à octet par R6. '
				. 'La 141ᵉ (étape 39.2, class-aod-cd-section-orders.php, render_order_detail) : '
				. '`echo $this->status_select_html( … )` rend le sélecteur de statut DANS la modale de '
				. 'détail (statut choisissable dans le flux de conversion) — MÊME appel, MÊME sûreté que '
				. 'render_order_row:236 (le markup est échappé à l’intérieur de status_select_html()), '
				. 'annotation AVEC raison. '
				. 'La 142ᵉ (étape 42.4, même fichier, render_order_detail) : `echo $this->assign_select_html( … )` '
				. '— jumeau du sélecteur de statut ci-dessus, MÊME sûreté (markup échappé à l’intérieur de la '
				. 'méthode), rendu GÉRANT uniquement. '
				. 'Les 143ᵉ et 144ᵉ (étape 42.7, colonne « Assigné à » des tableaux Commandes et Suivi) : '
				. '`echo self::assigned_confirmer_label( $order )` (class-aod-cd-section-orders.php) et son '
				. 'jumeau `echo AOD_CD_Section_Orders::assigned_confirmer_label( $order )` '
				. '(class-aod-cd-section-tracking.php) — MÊME motif que les 141ᵉ/142ᵉ : le markup est échappé '
				. '(esc_html) À L’INTÉRIEUR de la méthode appelée, le sniff ne le voit simplement pas depuis '
				. 'le site d’appel. Colonne rendue GÉRANT uniquement (même garde que 42.4). '
				. 'Les 145ᵉ et 146ᵉ (étape 43.3, class-aod-cd-section-abandoned.php, render_row) : '
				. '`echo AOD_CD_Roles::confirmer_label_html( … )` et `echo $this->lead_assign_select_html( … )` '
				. '— JUMEAUX EXACTS des 142ᵉ/143ᵉ, transposés des commandes aux PROSPECTS : mêmes deux '
				. 'appels (l’étiquette d’assignation, le sélecteur), même sûreté (esc_html/esc_attr à '
				. 'L’INTÉRIEUR des méthodes appelées — confirmer_label_html() est d’ailleurs le code même de '
				. 'assigned_confirmer_label(), extrait pour être partagé), même garde GÉRANT uniquement. '
				. 'Aucun motif nouveau : deux occurrences de plus du seul et même patron « je rends du '
				. 'markup déjà échappé par la méthode qui l’a construit ». '
				. 'La 147ᵉ (étape 43.6, class-aod-cd-section-abandoned.php, render_row) : '
				. '`echo self::lead_amount_html( $lead )` — TROISIÈME occurrence du même patron, '
				. 'et la plus simple : la méthode rend soit un tiret sourd littéral, soit la sortie '
				. 'de `wc_price()` (échappée par WooCommerce), éventuellement précédée d’un `<abbr>` '
				. 'dont le seul contenu variable est un `esc_attr__()`. Aucune donnée de prospect '
				. 'n’atteint le HTML : ni le nom du produit, ni la commune, ni rien de saisi — '
				. 'seulement un NOMBRE calculé et formaté. Contrairement aux 145ᵉ/146ᵉ, cette '
				. 'colonne n’est PAS gérant-only (un confirmateur qui rappelle doit voir le montant), '
				. 'ce qui ne change rien à sa sûreté : il n’y a pas de surface d’injection. '
				. 'La 148ᵉ (étape 47.1, class-aod-cd-section-orders.php, render_order_row) : '
				. '`echo $this->carrier_select_html( $order )` — QUATRIÈME occurrence du patron des '
				. '141ᵉ/142ᵉ/143ᵉ (« je rends du markup déjà échappé par la méthode qui l’a construit »), '
				. 'ici pour le sélecteur de transporteur d’une commande. Aucun motif nouveau : la méthode '
				. 'échappe TOUT à l’intérieur (esc_attr sur l’id de commande et l’id de transporteur, '
				. 'esc_html sur les libellés), et ses seules données variables viennent du CATALOGUE '
				. 'FERMÉ des 29 transporteurs (`AOD_Shipping::configured()`) — des libellés écrits dans '
				. 'le dépôt, jamais une saisie du gérant ni de l’acheteur. Les deux SVG (chevron, coche) '
				. 'sont des littéraux du fichier. ⚠️ Ce rendu n’est PAS gérant-only, contrairement aux '
				. '142ᵉ/143ᵉ : il est ouvert au confirmateur (décision D-4 de la story 47.1) — ce qui, '
				. 'comme pour la 147ᵉ, ne change rien à sa sûreté, l’élargissement portant sur QUI voit '
				. 'le contrôle, pas sur ce qui entre dans le HTML. '
				. 'Les 149ᵉ à 154ᵉ (étape 47.6, six zones de dépôt de fichier : quatre dans '
				. 'class-aod-cd-section-products.php — photo principale, galerie, photos d’options, '
				. 'vignettes de catégories — et deux dans class-aod-cd-section-landing.php) : '
				. '`echo AOD_CD_Upload::limit_note()`. CINQUIÈME occurrence du patron des '
				. '141ᵉ/142ᵉ/143ᵉ/148ᵉ, et la plus étroite de toutes : la méthode rend un `<span>` '
				. 'dont le SEUL contenu variable est `size_format( max_bytes() )`, c’est-à-dire un '
				. 'NOMBRE d’octets formaté par WordPress — aucune saisie, d’aucun acteur, n’y entre. '
				. 'La chaîne entière passe par `esc_html()` À L’INTÉRIEUR de la méthode. '
				. '⚠️ Six occurrences pour UN SEUL corps : c’est le point. Écrire l’aide six fois '
				. 'aurait donné six chaînes à traduire et six chances de diverger de la borne qui '
				. 'refuse réellement — le motif de dérive que la Phase 46 a passé huit étapes à fermer. '
				. 'La 155ᵉ (2026-08-02, panneau de CHOIX du panier COD, class-aod-cod-cart.php) : '
				. '`echo AOD_COD_Icon::svg( \'close\' )` sur le bouton de fermeture du panneau. '
				. 'MÊME patron que les deux SVG déjà présents dans ce fichier (bouton flottant, '
				. 'fermeture du tiroir) : `AOD_COD_Icon` est un CATALOGUE FERMÉ — le nom d’icône est '
				. 'un littéral du dépôt, la méthode refuse tout nom hors catalogue, et aucune saisie '
				. 'du gérant ni de l’acheteur n’atteint le markup rendu. `wp_kses_post()` y mangerait '
				. 'les attributs SVG, ce qui est la raison d’être de la dérogation. '
				. '⚠️ La hausse est venue AVEC son cliquet rouge : le census a refusé le panneau avant '
				. 'sa relecture. C’est le comportement voulu — une dérogation d’échappement ne doit '
				. 'jamais entrer sans passer par ce paragraphe. '
				. 'La 156ᵉ (2026-08-04, avertissement de marge partielle, class-aod-cd-section-home.php) : '
				. '`echo AOD_CD_Icon::svg( \'warning\' )` devant la note qui prévient que la marge '
				. 'affichée est une estimation. MÊME patron que la 134ᵉ et la 155ᵉ — catalogue FERMÉ, '
				. 'nom d’icône littéral du dépôt, la méthode refuse tout nom hors catalogue, aucune '
				. 'saisie du gérant ni de l’acheteur n’atteint le markup ; `wp_kses_post()` mangerait '
				. 'les attributs SVG. Le TEXTE de la note, lui, passe par `esc_html__()` juste après. '
				. '⚠️ Comme la 155ᵉ, la hausse est venue avec son rouge : le census a refusé '
				. 'l’avertissement avant sa relecture — troisième fois que ce garde-fou joue son rôle. '
				. 'Les 157ᵉ et 158ᵉ (2026-08-06, MARQUE de la barre latérale) sont un COUPLE et se '
				. 'relisent ensemble. `AOD_CD_Access::brand_mark()` rend soit le logo du client, soit '
				. 'un sceau ; `class-aod-cd-dashboard.php` l’imprime. '
				. '· Le SVG du sceau : MÊME patron que la 134ᵉ, la 155ᵉ et la 156ᵉ — `AOD_CD_Icon` est '
				. 'un catalogue FERMÉ, le nom d’icône est un littéral du dépôt, `wp_kses_post()` '
				. 'mangerait les attributs SVG. '
				. '· L’`echo` de la coque : la valeur vient d’UNE méthode du plugin, dont les deux '
				. 'branches échappent à la source — `esc_url()` sur l’URL du logo, catalogue fermé sur '
				. 'le sceau. Aucune saisie d’aucun acteur n’y entre. '
				. '🚨 CE QUI MÉRITE D’ÊTRE SU : l’URL du logo vient d’un ATTACHEMENT WordPress '
				. '(`get_theme_mod(\'custom_logo\')` → `wp_get_attachment_image_src()`), donc d’une '
				. 'donnée que le gérant contrôle — c’est le seul des quatre cas de cette famille où '
				. 'ce soit vrai. Elle est échappée par `esc_url()` DANS la méthode, jamais ici : '
				. 'déplacer cet échappement vers l’appelant rendrait la dérogation fausse. '
				. '⚠️ Quatrième fois que ce census refuse une entrée avant relecture. '
				. 'La 159ᵉ (2026-08-06, ICÔNES DES ONGLETS de l’Accueil, class-aod-cd-section-home.php) : '
				. '`AOD_CD_Icon::svg()` passé en `%4$s` du `printf()` qui rend chaque onglet. '
				. 'MÊME patron que la 134ᵉ, la 155ᵉ, la 156ᵉ et le sceau de la 157ᵉ — catalogue FERMÉ, '
				. 'nom d’icône littéral du dépôt (une table `slug => icône` écrite dans la méthode, '
				. 'jamais une donnée), la méthode refuse tout nom hors catalogue, et `wp_kses_post()` '
				. 'mangerait les attributs SVG. '
				. '⚠️ CE QUI EST NOUVEAU ICI, et qui vaut d’être lu avant de recopier le motif : la '
				. 'dérogation porte sur un `printf()` À PLUSIEURS ARGUMENTS, pas sur un `echo` d’une '
				. 'seule valeur. Elle couvre donc, en une ligne, quatre autres arguments qui sont bien '
				. 'échappés (`esc_attr`, `esc_url`, un littéral, `esc_html`) — un futur argument ajouté '
				. 'à ce même appel entrerait sous une dérogation qu’il n’a pas demandée, SANS que rien '
				. 'ne rougisse. C’est pourquoi la raison nomme le NUMÉRO de l’argument couvert. '
				. 'Les 160ᵉ à 163ᵉ (2026-08-07, CAMEMBERT de l’Accueil, class-aod-cd-section-home.php, '
				. 'render_stats_donut) : quatre dérogations pour DEUX motifs, tous deux déjà relus. '
				. '· 160ᵉ/161ᵉ — les deux `AOD_CD_Dashboard::empty_state( \'chart\', … )` des cas sans '
				. 'vente : patron identique à tous les autres appels d’état vide du dépôt (catalogue '
				. 'FERMÉ `AOD_CD_Icon` pour le glyphe, libellé échappé À L’INTÉRIEUR de la méthode). '
				. '· 162ᵉ/163ᵉ — `echo $svg` et `echo $lignes`, deux chaînes ASSEMBLÉES quelques lignes '
				. 'plus haut dans la même méthode. C’est le patron des 141ᵉ/142ᵉ (« je rends du markup '
				. 'déjà échappé par le code qui l’a construit »), à ceci près que le constructeur n’est '
				. 'pas une méthode séparée mais la boucle juste au-dessus — ce qui le rend PLUS facile '
				. 'à relire, pas moins : tout ce qui entre dans ces chaînes est visible d’un seul '
				. 'regard. Chaque attribut variable passe par `esc_attr()`, chaque texte par '
				. '`esc_html()`, et les seules valeurs numériques sortent de `number_format()` — donc '
				. 'de chiffres, un point et un signe moins, jamais d’une saisie. '
				. '🚨 CE QUI MÉRITE D’ÊTRE SU AVANT DE RECOPIER LE MOTIF : le NOM DE PRODUIT qui entre '
				. 'dans la légende est, lui, une donnée saisie par le gérant — la seule surface '
				. 'd’injection de tout ce bloc. Elle est fermée par `esc_html()` au moment de la '
				. 'concaténation, jamais ici. Déplacer cet échappement (par exemple en construisant la '
				. 'légende ailleurs) rendrait ces deux dérogations FAUSSES sans qu’aucun cliquet ne le '
				. 'voie : le census compte les annotations, il ne relit pas ce qu’elles couvrent.',
		),
		'WordPress.Security.NonceVerification'    => array(
			// 18 → 19 le 2026-08-10 : `AOD_CD_Section_Finance::entrees_du_post()`.
			// Même contrat que les deux `capi_token_from_post()` déjà recensés — elle
			// CONVERTIT le POST en nombres et ne décide de rien ; le nonce est vérifié
			// par son unique appelant, `ajax_finance_save()`. 🔒 Ce n'est pas une
			// promesse de commentaire : elle est inscrite dans `aod_test_nonce_helpers()`,
			// qui EXIGE que tous ses appelants noncent et rougit au premier qui ne le
			// ferait pas.
			// 19 → 20 le 2026-08-11 (fiche « Entreprise »). ⬆️ UNE de plus, et elle est
			// du motif le plus sûr de cette famille : la lecture est dans une CLÔTURE
			// déclarée À L'INTÉRIEUR de `ajax_entreprise()`, cinq lignes sous son
			// `check_ajax_referer()`. Le nonce est donc vérifié avant que la clôture
			// n'existe, et elle n'est appelable de nulle part ailleurs — contrairement
			// à `entrees_du_post()`, méthode privée dont le contrat repose sur ses
			// appelants et que `test-nonce-contract.php` garde à ce titre.
			// ⚠️ Ce que la dérogation NE dispense pas : la valeur passe par
			// `wp_unslash()` puis `sanitize_text_field()` puis une coupe à 120
			// caractères, dans le corps même de la clôture.
			'n'        => 20,
			'securite' => true,
			'quoi'     => 'Contrôle CSRF désarmé. Majoritairement des lectures $_GET de navigation (langue, section) sans effet de bord. '
				. 'La 18ᵉ (étape 47.6, class-aod-cd-upload.php, handle()) : lecture de `$_FILES[ $field ]` '
				. 'pour comparer la TAILLE du fichier à la borne, avant `media_handle_upload()`. '
				. 'Le nonce n’est pas absent, il est vérifié PLUS TÔT : les SIX appelants de ce goulot '
				. 'passent tous par `check_ajax_referer( \'aod_cd\' )` **et** `current_user_can( \'edit_products\' )` '
				. '— c’est le préambule de la classe (32.2) qui l’établit, et il n’a pas changé. '
				. 'La ligne ne lit qu’un entier (`size`) et un code d’erreur PHP (`error`) ; elle n’écrit '
				. 'rien, ne stocke rien, et son seul effet possible est de REFUSER un téléversement — '
				. 'la direction sûre. Revérifier le nonce ici le vérifierait deux fois par requête '
				. 'sans fermer quoi que ce soit.',
		),
		'WordPress.Security.ValidatedSanitizedInput' => array(
			// 9 → 10 le 2026-08-10 : la boucle sur `$_POST['pub']` d'`ajax_finance_produits()`.
			// Le sniff exige une sanitisation AVANT la boucle ; ici elle est faite
			// DANS la boucle, et plus strictement que ce qu'il demanderait — la clé est
			// castée en entier et rejetée si ≤ 0, la valeur passe par un flottant borné
			// aux nombres finis positifs. Aucune des deux n'est jamais rendue à l'écran
			// ni concaténée dans du SQL : elles finissent en clés/valeurs d'une option.
			'n'        => 10,
			'securite' => true,
			'quoi'     => 'Entrée non validée/sanitisée au sens du sniff — sanitisée autrement sur ces lignes.',
		),
		'WordPress.DB'                            => array(
			// 29 → 30 le 2026-08-10 : le `meta_query` qui isole les colis LIVRÉS dans
			// `produits_data()`. Une clé, une valeur, sur une méta indexée, une fois par
			// affichage d'écran — et le résultat est plafonné (MAX_COMMANDES).
			// 28 → 29 le 2026-08-10 : `tracking_count()` (page Suivi) compte les colis
			// d'un état. Le `meta_query` n'est PAS recopié — c'est le corps que la
			// liste emploie (`tracking_meta_query()`), justement pour que le nombre
			// affiché et la liste qu'il ouvre ne puissent pas diverger. Mesuré :
			// médiane 2142 ms avec les trois comptages, contre 2964 ms sur la page
			// Commandes qui n'en fait aucun — le coût est sous le bruit de ce montage.
			'n'        => 30,
			'securite' => false,
			'quoi'     => 'Requêtes directes / meta_query lentes. Performance, pas sécurité — sauf PreparedSQL, surveillé à part. '
				. 'La 13ᵉ (étape 25.1, aod-client-dashboard/uninstall.php) énumère les porteurs d’une capability : '
				. 'aucune API WordPress ne le fait, et get_users( array( "role" => … ) ) manquerait les caps « à la carte », '
				. 'posées par utilisateur sans le rôle. Requête unique, préparée, en contexte de désinstallation. '
				. 'La 14ᵉ (étape 31.4, class-aod-shipping.php) reprend un verrou d’envoi PÉRIMÉ : la condition '
				. 'd’ancienneté doit vivre DANS le WHERE pour rester atomique — un get_option() suivi d’un '
				. 'update_option() rouvrirait exactement la fenêtre de concurrence que le verrou ferme. '
				. 'Requête unique, préparée, cache purgé juste après. '
				. 'La 15ᵉ (étape 32.2, class-aod-cod-leads.php) demande si un jeton de prospect existe DÉJÀ, '
				. 'pour distinguer une création d’une simple mise à jour avant d’entamer le plafond par IP : '
				. 'la table {prefix}aod_cod_leads est une table maison, sans API WordPress ni cache pertinent, '
				. 'et la colonne token est indexée. Requête unique, préparée, en lecture seule. '
				. 'Les 16ᵉ et 17ᵉ (étape 39.1, class-aod-cd-section-abandoned.php, ajax_convert_lead) lisent la '
				. 'MÊME table maison aod_cod_leads (pas d’API WP, cache non pertinent) : la 16ᵉ récupère le '
				. 'prospect par son id (existence + périmètre + statut), la 17ᵉ RELIT son statut sur instance '
				. 'fraîche après le verrou d’anti-doublon (leçon 31.4 — un verrou sérialise, il n’annule pas). '
				. 'Les deux sont préparées et bornées par un id. '
				. 'La 18ᵉ (étape 39.6, même fichier, ajax_delete_leads) SUPPRIME une sélection de prospects '
				. '(suppression groupée gérant-only) : DELETE … WHERE id IN (…) sur la même table maison, '
				. 'préparé (placeholders %d générés comme where_sql), IDs bornés. Gérant only (manage_woocommerce). '
				. 'Les 19ᵉ et 20ᵉ (étape 42.1, class-aod-cd-section-orders.php, count_active_assigned()) filtrent '
				. 'wc_get_orders() par meta_key/meta_value sur _aod_assigned_confirmer (mesure de charge du '
				. 'tourniquet) : pas d’API wc_get_orders dédiée à ce filtre, clé indexable, requête paginée '
				. '(limit=1, total seul). '
				. 'La 21ᵉ (étape 42.3, même fichier, scoped_orders()) fusionne un meta_query de périmètre '
				. '(_aod_assigned_confirmer) avec un éventuel meta_query déjà présent (ex. non-expédiées) : '
				. 'gouverné par capability (aod_cap_confirm_orders), clé indexable, jamais tout le catalogue. '
				. 'Les 22ᵉ et 23ᵉ (étape 42.5, même fichier, render() et count_unassigned()) sont le jumeau '
				. 'GÉRANT ONLY du filtre ci-dessus : « Non assignées » (rattrapage groupé), même fusion AND, '
				. 'même clé indexable, réservé à manage_woocommerce. '
				. 'Les 24ᵉ et 25ᵉ (étape 42.7, même fichier, unassign_confirmer()) filtrent wc_get_orders() par '
				. 'meta_key/meta_value sur _aod_assigned_confirmer pour libérer les commandes d’un confirmateur '
				. 'AVANT sa suppression (sans quoi wp_delete_user() laisse la méta pointer vers un compte '
				. 'fantôme) — MÊME motif exact que les 19ᵉ/20ᵉ (count_active_assigned()), requête paginée par '
				. 'lots de 50, bornée à CE confirmateur. '
				. 'La 26ᵉ (étape 43.2, même fichier, render()) est le jumeau EXACT de la 21ᵉ (scoped_orders()) '
				. 'pour le nouveau filtre par confirmateur (GÉRANT UNIQUEMENT) : fusion AND avec un éventuel '
				. 'meta_query déjà présent, même clé indexable _aod_assigned_confirmer. '
				. 'Les 27ᵉ et 28ᵉ (étape 43.3, class-aod-cd-section-abandoned.php, apply_lead_assignment()) '
				. 'lisent puis écrivent la colonne assigned_confirmer sur la MÊME table maison aod_cod_leads '
				. 'que les 15ᵉ à 18ᵉ (pas d’API WP, cache non pertinent) : la 27ᵉ vérifie l’existence du '
				. 'prospect par son id (SELECT préparé, borné), la 28ᵉ pose l’assignation via $wpdb->update() '
				. '(valeurs et clause WHERE préparées par l’API $wpdb elle-même). GÉRANT UNIQUEMENT, et '
				. 'l’ID du confirmateur est validé « confiance zéro » AVANT l’écriture '
				. '(AOD_CD_Roles::valid_confirmer()).',
		),
		'WordPress.WP.AlternativeFunctions'       => array(
			'n'        => 2,
			'securite' => false,
			'quoi'     => 'Fonction PHP native préférée à son équivalent WordPress. La 2ᵉ (étape 37.2, '
				. 'class-aod-cod-icon.php) : `file_get_contents()` sur un SVG du catalogue fermé, chemin '
				. 'construit depuis AOD_COD_PATH et un nom de fichier du catalogue — ni URL, ni saisie, '
				. 'donc aucune des raisons pour lesquelles WPCS pousse vers wp_remote_get(). Même motif '
				. 'que la lecture d’icônes du dashboard.',
		),
	);
}

/**
 * Tous les fichiers PHP des deux plugins maison.
 *
 * 🚨 Balayage RÉCURSIF depuis la racine des plugins, jamais une liste de fichiers.
 * C'est la leçon de la 18.1, rejouée dans le test même censé la prévenir : une
 * liste nommée en dur laisse hors champ le fichier créé demain, et le cliquet se
 * déclare vert sur un périmètre qui a rétréci sans que personne le voie.
 *
 * @return string[]
 */
function aod_test_pcs_fichiers() {
	$cod    = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : dirname( AOD_CD_PATH ) . '/aod-cod-form/';
	$out    = array();
	foreach ( array( AOD_CD_PATH, $cod ) as $racine ) {
		if ( ! is_dir( $racine ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $racine, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
				$out[] = $f->getPathname();
			}
		}
	}
	sort( $out );
	return $out;
}

/**
 * Relève chaque annotation : fichier, ligne, sniffs cités, et raison éventuelle.
 *
 * @return array<int,array{fichier:string,ligne:int,sniffs:string[],raison:string,brut:string}>
 */
function aod_test_pcs_relever() {
	$out = array();
	foreach ( aod_test_pcs_fichiers() as $chemin ) {
		$lignes = file( $chemin );
		if ( ! $lignes ) {
			continue;
		}
		foreach ( $lignes as $i => $ligne ) {
			if ( false === strpos( $ligne, 'phpcs:ignore' ) ) {
				continue;
			}
			// Tout ce qui suit `phpcs:ignore` : les sniffs, puis `-- raison`.
			$reste = substr( $ligne, strpos( $ligne, 'phpcs:ignore' ) + strlen( 'phpcs:ignore' ) );
			$parts = explode( '--', $reste, 2 );
			$noms  = preg_split( '/[\s,]+/', trim( $parts[0] ), -1, PREG_SPLIT_NO_EMPTY );

			$out[] = array(
				'fichier' => str_replace( dirname( dirname( AOD_CD_PATH ) ) . '/', '', $chemin ),
				'ligne'   => $i + 1,
				'sniffs'  => $noms ? $noms : array(),
				'raison'  => isset( $parts[1] ) ? trim( $parts[1] ) : '',
				'brut'    => trim( $ligne ),
			);
		}
	}
	return $out;
}

/** À quelle famille du census une annotation appartient-elle ? (préfixe le plus long) */
function aod_test_pcs_famille( $sniffs ) {
	$familles = array_keys( aod_test_pcs_census() );
	// Du plus spécifique au plus général : `WordPress.Security.EscapeOutput.X`
	// doit tomber dans `WordPress.Security.EscapeOutput`, pas ailleurs.
	usort(
		$familles,
		function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}
	);
	foreach ( $sniffs as $s ) {
		foreach ( $familles as $f ) {
			if ( 0 === strpos( $s, $f ) ) {
				return $f;
			}
		}
	}
	return '';
}

/* =========================================================================
 * LE TEST
 * ======================================================================= */

AOD_Test::group( 'phpcs:ignore — cliquet (D6) : aucune suppression de contrôle n’arrive en silence' );

$aod_pcs_all     = aod_test_pcs_relever();
$aod_pcs_census  = aod_test_pcs_census();
$aod_pcs_nb_fich = count( aod_test_pcs_fichiers() );

// TÉMOIN (DoD §C.2) — publier le périmètre réellement balayé. Un `glob` qui ne
// trouve rien rendrait « 0 annotation non justifiée » : vert, et vide de sens.
AOD_Test::ok(
	$aod_pcs_nb_fich >= 30,
	sprintf( 'Témoin : %d fichiers PHP balayés dans les deux plugins (un balayage vide se déclarerait vert)', $aod_pcs_nb_fich )
);
AOD_Test::ok(
	count( $aod_pcs_all ) >= 100,
	sprintf( 'Témoin : %d annotations relevées', count( $aod_pcs_all ) )
);

/* --- R1 · aucune annotation NUE (règle absolue) ------------------------ */

// Une annotation sans nom de sniff désarme TOUT sur sa ligne. Il y en avait une,
// trouvée par ce test : `echo $checked; // phpcs:ignore` — le contenu était
// inoffensif, mais la suppression, elle, était un blanc-seing. Resserrée sur
// `EscapeOutput`.
$aod_pcs_nues = array();
foreach ( $aod_pcs_all as $a ) {
	if ( ! $a['sniffs'] ) {
		$aod_pcs_nues[] = $a['fichier'] . ':' . $a['ligne'];
	}
}
AOD_Test::eq(
	0,
	count( $aod_pcs_nues ),
	'R1 — aucune annotation NUE (sans nom de sniff = tous les contrôles désarmés)'
	. ( $aod_pcs_nues ? ' — nue(s) : ' . implode( ', ', $aod_pcs_nues ) : '' )
);

/* --- R4 · aucune annotation INERTE (séparateur em-dash) ---------------- */

// 🚨 Trouvée par le tri phpcs du 2026-07-21 : deux annotations écrivaient leur
// raison après un tiret CADRATIN « — » au lieu du « -- » que phpcs exige. phpcs
// lit alors « SNIFF — raison » comme une LISTE DE SNIFFS, ne reconnaît rien, et
// **n'honore pas l'annotation** : la ligne reste flaguée. L'exception paraissait
// documentée et relue ; elle ne supprimait rien. C'est l'annotation NUE de R1 un
// cran plus loin — non pas absente, mais INERTE, ce qui se voit encore moins.
// Ces deux-là couvraient du code sûr, donc zéro impact ; mais le format est un
// piège qui se recopie. Règle absolue : le séparateur de raison est « -- ».
//
// ⚠️ On regarde les JETONS DE SNIFF (portion après `phpcs:ignore`, avant `--`),
// PAS la ligne entière : un em-dash dans le CODE de la ligne (`$name . ' — ' .`)
// n'a rien à voir avec l'annotation. C'est ce qu'un premier jet, qui grepait
// `brut`, prenait à tort pour une annotation inerte (class-aod-shipping.php:429).
$aod_pcs_inertes = array();
foreach ( $aod_pcs_all as $a ) {
	foreach ( $a['sniffs'] as $sniff ) {
		if ( false !== strpos( $sniff, '—' ) ) {
			$aod_pcs_inertes[] = $a['fichier'] . ':' . $a['ligne'];
			break;
		}
	}
}
AOD_Test::eq(
	0,
	count( $aod_pcs_inertes ),
	'R4 — aucune annotation INERTE (raison après « — » em-dash : phpcs ne l’honore pas ; séparateur = « -- »)'
	. ( $aod_pcs_inertes ? ' — inerte(s) : ' . implode( ', ', $aod_pcs_inertes ) : '' )
);

/* --- R3 · le stock d'annotations sans raison ne remonte pas ------------ */

// Plancher constaté le 2026-07-21. Le BAISSER est un progrès (documenter une
// annotation de plus) : le test le dira, et on ajustera vers le bas. Le remonter
// exige de venir écrire ici pourquoi — ce qui est tout l'objet du cliquet.
// Descendu 141 → 139 → 132 le 2026-07-21. 139 : le tri phpcs a corrigé 2
// annotations em-dash inertes (R4). 132 : la 28.2 a resserré les 7 suppressions
// `WordPress.DB` larges (dette D14) en sniffs précis, chacune AVEC sa raison. Le
// plancher suit la réalité : le baisser est un progrès, le remonter exige de venir
// écrire ici pourquoi.
// Descendu 132 → 124 le 2026-08-09, et sans avoir documenté une seule annotation :
// la refonte de « Pixels & Tracking » a remplacé trois blocs CAPI recopiés par une
// boucle, emportant huit dérogations nues avec les copies. C'est le seul progrès
// que ce plancher enregistre sans effort — supprimer la duplication supprime les
// dérogations en double.
$aod_pcs_plancher_sans_raison = 124;

$aod_pcs_sans_raison = array();
foreach ( $aod_pcs_all as $a ) {
	if ( '' === $a['raison'] ) {
		$aod_pcs_sans_raison[] = $a['fichier'] . ':' . $a['ligne'];
	}
}
AOD_Test::ok(
	count( $aod_pcs_sans_raison ) <= $aod_pcs_plancher_sans_raison,
	sprintf(
		'R3 — annotations sans raison : %d (plancher %d, ne doit pas remonter)',
		count( $aod_pcs_sans_raison ),
		$aod_pcs_plancher_sans_raison
	)
);
// Un plancher qu'on oublie de descendre perd son mordant : on signale l'écart.
AOD_Test::ok(
	count( $aod_pcs_sans_raison ) >= $aod_pcs_plancher_sans_raison - 5,
	sprintf(
		'R3 — le plancher suit la réalité : %d sans raison pour un plancher à %d — descends-le',
		count( $aod_pcs_sans_raison ),
		$aod_pcs_plancher_sans_raison
	)
);

/* --- R2 · census par famille ------------------------------------------- */

$aod_pcs_vus = array();
foreach ( $aod_pcs_all as $a ) {
	$f = aod_test_pcs_famille( $a['sniffs'] );
	if ( '' === $f ) {
		// Famille inconnue du census : c'est précisément le cas qu'on veut voir.
		$aod_pcs_vus['(INCONNUE) ' . implode( ',', $a['sniffs'] ) . ' @ ' . $a['fichier'] . ':' . $a['ligne'] ] = 1;
		continue;
	}
	$aod_pcs_vus[ $f ] = isset( $aod_pcs_vus[ $f ] ) ? $aod_pcs_vus[ $f ] + 1 : 1;
}

foreach ( $aod_pcs_census as $famille => $attendu ) {
	$vu = isset( $aod_pcs_vus[ $famille ] ) ? $aod_pcs_vus[ $famille ] : 0;
	AOD_Test::eq(
		$attendu['n'],
		$vu,
		sprintf(
			'R2 — census %s : %d attendues%s',
			$famille,
			$attendu['n'],
			$attendu['securite'] ? ' 🔒 FAMILLE DE SÉCURITÉ — une hausse doit être justifiée ici même' : ''
		)
	);
}

// Toute famille hors census (y compris « (INCONNUE) … ») est un échec nommé.
$aod_pcs_hors = array();
foreach ( $aod_pcs_vus as $famille => $n ) {
	if ( ! isset( $aod_pcs_census[ $famille ] ) ) {
		$aod_pcs_hors[] = $famille;
	}
}
AOD_Test::eq(
	0,
	count( $aod_pcs_hors ),
	'R2 — aucune famille de sniff hors census'
	. ( $aod_pcs_hors ? ' — vue(s) : ' . implode( ' | ', $aod_pcs_hors ) : '' )
);

/* --- Garde-fou du cliquet lui-même ------------------------------------- */

// Le total du census doit égaler le total relevé. Sans cette ligne, une famille
// oubliée dans le census ferait passer ses annotations pour inexistantes : chaque
// compte individuel serait juste, et le trou invisible.
$aod_pcs_total_census = 0;
foreach ( $aod_pcs_census as $c ) {
	$aod_pcs_total_census += $c['n'];
}
AOD_Test::eq(
	count( $aod_pcs_all ),
	$aod_pcs_total_census,
	'Cohérence — la somme du census couvre TOUTES les annotations relevées (sinon une famille manque au census)'
);
