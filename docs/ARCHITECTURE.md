# ARCHITECTURE — État des lieux technique du template

> Photographie complète du code au **2026-07-29**,
> base de travail pour les évolutions futures (voir [ROADMAP-AMELIORATIONS.md](ROADMAP-AMELIORATIONS.md)
> — sa table §C.3 **fait foi** sur l'état des phases, pas cette ligne).
> Versions analysées : `aod-cod-form` **v1.52.1** · `aod-client-dashboard` **v0.75.0**.
>
> 🔒 Depuis l'étape **44.1**, ce fichier et `README.md` sont dans le corpus du cliquet
> `tests/unit/test-doc-derive.php` : **toute version annoncée dans un TITRE doit égaler
> l'en-tête `Version:` du plugin** (règle R8). Ce document se contredisait lui-même à
> 27 lignes d'écart — en-tête juste, titres de §2 et §3 périmés de 7 et 23 versions
> mineures — et un lecteur qui saute à la section qui l'intéresse lisait la fausse.

---

## 1. Vue d'ensemble

```
Acheteur (mobile, FR/AR)
   │  page produit → formulaire COD (aod-cod-form)
   ▼
Commande WooCommerce (COD, HPOS, n° séquentiel via mu-plugin)
   │  notification WhatsApp (CallMeBot) + pixels (Meta/TikTok/Snap/Google)
   ▼
Gérant dans /gestion (aod-client-dashboard, rôle restreint, sans wp-admin)
   │  statut « Confirmée » → envoi auto/manuel au transporteur
   ▼
Transporteur (Yalidine / Yalitec / Noest / E-com Delivery / Maystro / EcoTrack + 23 marques)
   │  cron aod_ship_tracking_sync (toutes les 2 h ; suivi par lot si l'API le permet)
   ▼
Suivi normalisé dans les métas _aod_ship_status* → page « Suivi commande » de /gestion
```

Les deux plugins sont **bind-montés** dans le conteneur (édition live). Le
formulaire COD est le **seul** tunnel d'achat (pas de panier/compte — règle 4).

---

## 2. Plugin `aod-cod-form` (v1.52.1) — formulaire COD + transporteurs + pages publiques

≈ 11 900 lignes PHP. Dépendance obligatoire : WooCommerce. Compatible HPOS.

### Modules (`includes/`)

| Fichier | Rôle |
|---|---|
| `class-aod-cod-form.php` | Cœur : rendu du formulaire sur la page produit (hook `woocommerce_single_product_summary` prio 35), shortcode `[aod_cod_form]`, offres/lots, variantes visuelles, AJAX de création de commande. **Multi-produits depuis la 35.2 (v1.43.0)** : `items[i][product_id]` accepté **si** le panier COD est activé (`cart_enabled()`, option `aod_cod_cart`) — `build_order_lines()`/`price_lines()` (privées, éprouvées par réflexion dans `test-panier-cod.php`), offres par groupe de produit, rétro-compat totale du payload mono-produit. |
| `class-aod-cod-data.php` | Données géo (`data/dz-places.json` : 58 wilayas / 1541 communes, noms FR + AR), validation commune↔wilaya, tarifs par wilaya, seuil livraison gratuite. |
| `class-aod-cod-admin.php` | Page admin « Livraison COD » (tarifs Domicile/Stop-desk). |
| `class-aod-shipping.php` | Orchestrateur transporteurs : statut custom « Confirmée » (`wc-aod-confirmed`), envoi auto/manuel, metabox + colonne « Livraison », actions groupées. |
| `class-aod-carrier.php` | Classe abstraite des transporteurs : contrat commun, métas de suivi, vocabulaire de statuts normalisé, helpers HTTP. |
| `class-aod-carrier-{yalidine,yalitec,noest,procolis,maystro,ecotrack}.php` | Implémentations par transporteur (cf. matrice ci-dessous). |
| `class-aod-ship-tracking.php` | Cron de synchronisation du suivi colis (`aod_ship_tracking_sync`, toutes les 2 h). Commandes non terminales, **les moins récemment rafraîchies d'abord**, **regroupées par transporteur** : un livreur qui sait interroger ses colis par lot (`bulk_tracking_limit()`) en absorbe jusqu'à `BATCH_BULK` (240) ; les autres restent plafonnés à `BATCH` (30, un appel par colis). Statuts terminaux (livré/retourné/annulé) jamais réinterrogés. |
| `class-aod-cod-leads.php` | Prospects / paniers abandonnés : table custom `{prefix}aod_cod_leads`, capture AJAX, page admin (appel/WhatsApp). |
| `class-aod-cod-pixels.php` | Pixels Meta / TikTok / Snapchat / Google Ads (PageView + conversion, vérif domaine Meta, anti double comptage `_aod_pixels_fired`). **Conversions API (CAPI) Meta — Phase 10 (v1.37.0)** : `send_capi_purchase( $order )` branchée sur `aod_cod_order_created` (prio 20) envoie l'achat **serveur-à-serveur** à `graph.facebook.com/{CAPI_API_VERSION}/{pixel}/events`, dédupliqué avec le pixel navigateur par le **même `event_id`** (= n° de commande, cf. `purchase_payload()`). **Opt-in** (pixel Meta + `meta_capi_token` requis, sinon 0 appel), **fail-open** (modèle HTTP Turnstile, toute `WP_Error` avalée, jamais de secret loggé), **PII hachées SHA-256** (`hash_pii`/`hashed_list`/`normalize_phone` 0→213 ; IP/UA/cookies `_fbp`/`_fbc` en clair comme Meta l'exige), valeur/devise via `to_reporting()`. Envoi unique par commande (méta `_aod_capi_sent`). `test_capi_connection()` : bouton « Tester la connexion » (GET du nœud pixel, aucun événement envoyé). **Conversions serveur TikTok — Phase 13.1 (v1.38.0)** : `send_tiktok_purchase( $order )` (même hook, même patron) envoie `CompletePayment` à l'**Events API** TikTok (`TIKTOK_API_URL` = `business-api.tiktok.com/open_api/v1.3/event/track/`, jeton dans l'**en-tête `Access-Token`**), dédupliqué par le **même `event_id`** que le `ttq.track()` navigateur (3ᵉ argument `{ event_id }` ajouté). Socle **mutualisé** avec Meta : `capi_http_post()` (POST fail-open), `order_content_ids()`, `hash_pii()`/`hashed_list()`, `to_reporting()`. Différences de plateforme : téléphone haché en **E.164 AVEC `+`** (`normalize_phone($p, true)` — Meta le veut sans), champs `user` (`phone`/`first_name`…), `properties.contents`, cookies `_ttp`/`ttclid` en clair. Réglage `aod_cod_pixels['tiktok_capi_token']` (secret, `capi_token_from_post()` généralisé `$field`), garde d'envoi unique **distincte** `_aod_capi_tiktok_sent`. Opt-in (pixel TikTok + jeton). **Conversions serveur Snapchat — Phase 13.2 (v1.39.0)** : `send_snapchat_purchase( $order )` (même hook, même socle) envoie `PURCHASE` à la **Conversions API v3** Snapchat (`SNAPCHAT_API_BASE` = `tr.snapchat.com/v3/` + **ID pixel dans l'URL** `/v3/<pixel>/events`, jeton en **en-tête `Authorization: Bearer`** — jamais dans l'URL), dédupliqué par **`client_dedup_id`** (= n° de commande, == `snaptr('track','PURCHASE',{…,client_dedup_id})` navigateur). ⚠️ Snapchat v3 est **aligné sur la spec Meta, pas TikTok** : conteneur **`user_data`**, téléphone haché **SANS `+`** (`normalize_phone()` par défaut — comme Meta), champs `em/ph/fn/ln/ct/st/country`, `custom_data` (`currency`/`value`/`content_ids`/`num_items`) ; identifiants clairs `client_ip_address`/`client_user_agent` + cookies Snap `sc_cookie1` (`_scid`) / `sc_click_id` (`_scclick`). Réglage `aod_cod_pixels['snapchat_capi_token']` (secret), garde d'envoi unique **distincte** `_aod_capi_snap_sent`. Opt-in (pixel Snapchat + jeton). **Boutons « Tester la connexion » TikTok/Snapchat — Phase 13.3 (v1.40.0)** : `test_tiktok_connection()` et `test_snapchat_connection()` (sœurs de `test_capi_connection()` Meta, inchangée). TikTok n'ayant **pas** d'endpoint de validation, le test envoie un événement **neutre** (`ViewContent`, sans PII) avec un **`test_event_code`** requis (nouveau réglage `tiktok_test_event_code`, non secret) → onglet « Test », **hors production** ; succès si réponse `code === 0`. Snapchat utilise l'**endpoint de validation dédié** `…/v3/<pixel>/events/validate` (jeton en en-tête `Authorization: Bearer`, hors URL) → succès si HTTP `200`. Les deux : opt-in (invite + **zéro requête** si pixel/jeton/(TikTok) test_event_code manquant), fail-safe, **jeton jamais exposé** dans le verdict. L'AJAX `aod_cd_test_capi` route par `platform` **whitelisté** (`meta`\|`tiktok`\|`snapchat`) ; le JS `bindCapiTest()` gère un bouton par carte (`data-platform`). **Correctifs de revue 13.x (v1.40.1 · dashboard v0.43.1)** — la mutualisation avait emporté les conventions **Meta** sur TikTok, en silence (socle fail-open, réponse jamais lue) : (a) les identifiants `user` de TikTok partaient en **tableaux** (`hashed_list()`, forme `user_data` de Meta — et de Snapchat v3, aligné Meta) alors que l'Events API v1.3 les type en **chaîne** → `hash_pii()` direct ; (b) `content_type: 'product'` manquait dans `properties` côté serveur alors que le pixel **navigateur** l'envoyait — les deux côtés se contredisaient ; (c) l'**e-mail** haché (identifiant au poids le plus fort chez TikTok) n'était pas envoyé, alors que Snapchat l'envoyait depuis le même champ ; (d) les **quantités** étaient perdues — `order_content_ids()` ne rend que des IDs (contrat Meta, inchangé), d'où le nouvel helper `order_contents()`/`order_num_items()` : TikTok `contents[].quantity` et Snapchat `num_items` déclarent désormais des **unités**, pas des lignes (une offre par lot ou un pack tient sur UNE ligne de quantité N) ; (e) `test_snapchat_connection()` déclarait un succès sur le seul **HTTP 200**, alors que `/events/validate` répond 200 dès que la requête est bien formée et met son verdict **dans le corps** — un pixel n'appartenant pas au compte du jeton ressortait vert (⚠️ comparaison **exacte** du statut : « VALIDATION_ERROR » *contient* « VALID ») ; (f) l'événement de test TikTok ne portait **aucun** `user`, que l'Events API exige — un jeton valide ressortait « invalide » ; (g) `purchase_pixels()` (rendu `woocommerce_thankyou`) n'avait reçu ni `event_id` TikTok ni `client_dedup_id` Snapchat quand les envois serveur sont arrivés → **double comptage** sur ce chemin. La porte `conversions` passe de 83 à **99 assertions** et crée désormais son **propre produit jetable en quantité 3** (elle empruntait un produit publié « s'il y en avait un » : rouge sur un clone neuf) ; elle assertait `user['phone'][0]`, donc **verrouillait la forme fautive**. **Phase 14.1 — les conversions serveur SORTENT du chemin d'achat (v1.41.0)** : les trois envois étaient **synchrones** sur `aod_cod_order_created` prio 20, **avant** `wp_send_json_success`, à 8 s de timeout chacun → jusqu'à **24 s** d'attente pour l'acheteur sur la configuration même que la Phase 13 promeut (le fail-open protégeait le *résultat*, jamais la *latence*). Désormais `queue_server_conversions()` **met en file** (`wp_schedule_single_event`, hook `aod_capi_dispatch`, un événement par commande) et `dispatch_server_conversions()` envoie. Mesuré : **3 ms** sur le chemin acheteur contre 6 024 ms pour trois régies lentes simulées. 🚨 **Le piège de la phase, et la raison du découpage en deux temps** : les trois payloads lisaient `$_COOKIE` **au moment de l'envoi** (`_fbp`/`_fbc`, `_ttp`/`ttclid`, `_scid`/`_scclick` — les identifiants d'appariement les plus forts), or **un cron n'a aucun cookie** : différer naïvement aurait troqué de la latence contre de la qualité d'attribution, **en silence**. D'où `capture_match_cookies()` (méta `_aod_conv_cookies`, posée **dans la requête acheteur**) et `match_cookie()` (méta d'abord, `$_COOKIE` en repli — ce repli rend la capture **neutre**, donc livrable et prouvable avant tout déplacement : la porte est restée verte **sans modification d'assertion**). ⚠️ **« Mis en file » n'est PAS « envoyé »** : aucune garde `_aod_capi_*_sent` n'est posée à la planification — elles restent posées par les envois eux-mêmes, au moment de l'appel réseau, sinon un cron mort ferait compter comme transmis un achat jamais parti, et jamais rattrapé. Opt-in de planification (`armed_server_platforms()`) : aucune régie armée ⇒ **aucune tâche cron fantôme**. Désactivation du plugin → `wp_unschedule_hook()` et **non** `wp_clear_scheduled_hook()`, qui ne purge que les tâches **sans arguments** (les nôtres portent l'ID de commande). Conséquence portée dans [CRON-SERVEUR.md](CRON-SERVEUR.md) : **le cron conditionne désormais la mesure publicitaire**, plus seulement la logistique (retard assumé ≤ 15 min, sans effet sur l'attribution). Porte `conversions` : 99 → **114 assertions**, dont le test central — cookies vidés avant le dispatch, payload toujours complet. |
| `class-aod-cod-whatsapp.php` | Notification WhatsApp au marchand à chaque commande (CallMeBot), branchée sur l'action `aod_cod_order_created`. |
| `class-aod-cod-turnstile.php` | Anti-robot Cloudflare Turnstile (réglages ou constantes `AOD_COD_TURNSTILE_*`). |
| `class-aod-cod-lang.php` | Switcher de langue public FR⇄AR : cookie `aod_lang` + filtre `locale`, injection dans les menus, shortcode `[aod_lang_switcher]`. |
| `class-aod-cod-block.php` + `class-aod-cod-elementor-widget.php` | Bloc Gutenberg `aod/cod-form` (rendu serveur) + widget Elementor. |
| `class-aod-cod-no-tunnel.php` | **Applique la règle 4** : ni panier ni compte côté acheteur. Front uniquement. |
| `class-aod-cod-pages.php` | Contenu de l'Accueil (shortcode `[aod_home]`) + retraduction des libellés du menu. |
| `class-aod-cod-contact.php` | Page Contact : coordonnées + formulaire bilingue (shortcode `[aod_contact_form]`, AJAX `aod_contact_submit`). Porte **les mêmes couches anti-bot** que la commande COD. |
| `class-aod-cod-catalog-i18n.php` | **Contenu marchand bilingue** (Phase 7) : titre, description, arguments de vente et noms de catégories rendus selon `aod_lang`, avec repli bidirectionnel. Détail en **§6**. |
| `class-aod-cod-catalog-filters.php` | Filtres d'affichage du catalogue adossés au bilinguisme (`get_term`, `the_title`, `the_content`…). Détail en **§6**. |
| `class-aod-cod-landing.php` | **Page de vente (landing) par produit** (v1.28.0, Phase 6 complète). **Propriétaire du format** : schéma du catalogue de blocs, `sanitize()` (liste blanche), accesseurs, rendu. Substitue `templates/single-product-landing.php` au gabarit du thème via `template_include` — **uniquement** si l'interrupteur `_aod_landing_enabled` est ON *et* que la landing a au moins un bloc. **11 blocs** : `hero`, `points`, `gallery`, `video`, `text`, `compare`, `reviews`, `faq`, `urgency`, `form`, `trust` — plus le réglage de page `sticky_cta` (barre « Commander » mobile). Le bloc `form` appelle `AOD_COD_Form::get_form_html()` (aucun second formulaire). `urgency` lit le **vrai** stock Woo, et son rebours vise `get_date_on_sale_to()` : sans chiffre réel, le bloc **ne s'affiche pas**. CSS chargé seulement là où la landing s'affiche ; le JS (`assets/js/aod-landing.js`) **seulement si** l'une des trois choses qui en ont besoin est présente — façade vidéo YouTube, rebours, ou barre collante (`needs_js()`). Une page de vente ordinaire reste 100 % HTML/CSS. |
| `class-aod-cod-cart.php` | **Panier COD public** (v1.44.0, Phase 35.3) — n'existe que si le gérant a activé l'option (`AOD_COD_Form::cart_enabled()`, carte de `/gestion → Livraison`). Bouton flottant + panneau au `wp_footer`, état 100 % navigateur (`localStorage.aod_cart` : IDs + options FR canoniques + qty — jamais de nom/prix stocké), endpoint lecture seule `aod_cod_cart_items` (nonce, plafond 30, produits publiés seuls) qui repeint le panneau **dans la langue courante** et estime via `AOD_COD_Form::estimate_lines()` (la formule exacte du serveur). **Depuis le 2026-08-02, chaque ligne montre la photo de la VARIANTE choisie** — elle montrait celle du produit quelle que soit la variante, si bien que « Casque · Bordeaux » s'illustrait du casque noir et que deux lignes du même produit étaient indistinguables, sur la seule image que l'acheteur voit avant de valider. 🚨 La résolution vit dans `build_order_lines()` (clé `image` de la ligne), **là où l'appariement de la variante est déjà fait** : la refaire ici supposerait de re-matcher `option_meta` contre les sections, donc d'écrire une seconde fois la règle d'appariement. Section **visuelle** d'abord (comme la fiche, qui ne change sa grande photo que pour une `is-visual`), puis toute valeur portant une image ; vide ⇒ repli sur la photo du produit. Les deux branches sont gardées sur la même fixture par `run.sh panier`. 🚨 **Et depuis le 2026-08-02, l'identité d'une ligne est sa POSITION dans le store (`idx`), jamais son `product_id`** — reportée par `build_order_lines()` jusqu'à la ligne peinte. Elle ferme trois défauts d'un même mécanisme. (1) **Aucun article ne s'évapore** : la peinture parcourt les articles DEMANDÉS, pas les lignes rendues, si bien qu'un article refusé par le funnel pour une raison que l'endpoint ignore (variante en rupture, variante renommée par le gérant, quantité au-delà du plafond) ressort en ligne indisponible **et retirable**. Avant, il sortait du panneau en restant dans le store : badge « 1 » sur un panneau « vide », aucun bouton pour le retirer, et la commande refusée en bloc au nom d'un article absent de l'écran — une vente perdue sans issue. ⚠️ *Ne pas « simplifier » cette boucle en parcourant les lignes rendues : le fantôme reviendrait le jour même.* (2) **« + », « − » et « retirer » agissent sur la ligne désignée** : ils ciblaient le produit et s'arrêtaient à la première correspondance, donc « + » sur Bordeaux incrémentait Noir. (3) **Une ligne refusée sur sa QUANTITÉ garde ses commandes** et le dit avec son propre message : elle basculait en « Produit indisponible » sans quantité, sans total et sans boutons — un « + » de trop était irréversible. 🚨 *Une boucle de « réconciliation » explicite a été écrite puis RETIRÉE : mise au rouge, elle n'a rien fait rougir. Elle se serait relue comme le porteur de la garantie.* Gardé par les 10 assertions A/B/C de `run.sh panier`, éprouvées au rouge une par une. **Panneau de CHOIX (2026-08-02)** : un appui sur la carte d'un produit **à variantes** ouvre une modale qui demande la variante — avant, le bouton « Ajouter au panier » de la grille menait à la fiche sans rien ajouter. Second endpoint **lecture seule** `aod_cod_cart_options` (mêmes gardes que l'estimation) qui rend `AOD_COD_Form::options_html_for()`. 🚨 **On sert le markup de la FICHE, on n'en fabrique pas un second** : il porte déjà `disabled` sur une valeur en rupture (40.4), les noms localisés, et les clés canoniques FR du matching (7.4) — un rendu JS parallèle aurait divergé le jour d'une rupture, donc sur une commande impossible. Conséquence assumée : `aod-cart.css` **dépend** de `aod-cod-form.css` (enqueue priorité 20 — une dépendance vers un handle pas encore enregistré est abandonnée en silence), sinon les pastilles sortent nues hors de la fiche. Repli vers la fiche (ancre `#aod-options` + phrase de repère) si le serveur refuse ou si la coque manque. **Tiroir et panneau partagent UNE mécanique de modale** — `inert` sur le fond *et* cycle de tabulation fermé à la main : cf. `D34`, soldée ici. « Commander » renvoie à la fiche du 1er article : le formulaire COD de la page soumet les articles du panier (35.2). **Ni session, ni cookie, ni tunnel Woo — `AOD_COD_No_Tunnel` reste armé, règle 4 intacte.** **Plafond de lignes DIT (D35, 2026-08-03)** : la borne `MAX_ITEMS` (30 **lignes**, pas 30 unités) écrêtait en silence — un store de 31 lignes en montrait 30 et la commande en portait 30. `add()` refuse désormais une ligne **neuve** au plafond et rend un verdict ; une **fusion** reste toujours permise (elle ne crée pas de ligne, et refuser un article DÉJÀ au panier serait un second défaut). Le nombre est **servi** (`max_items`), jamais recopié dans le JS. Le refus se dit là où le geste a eu lieu : le panneau de choix reste **ouvert** (se fermer ressemblerait à un ajout réussi), la fiche passe son message en erreur, et la carte de grille — sans surface de message — **ouvre le tiroir** avec la phrase, donc avec la ligne à retirer sous les yeux. `ajax_items()` renvoie `truncated` et le panneau l'annonce avec une **autre** phrase : « retirez-en un » répond à un geste, le constat d'un store déjà au-delà ne s'adresse à personne qui vient de demander quelque chose. 🚨 *Le fond du défaut n'était pas la borne : c'était la justification écrite en 44.1 pour écrêter au lieu de refuser — « aucune intention d'acheteur à trahir dans une forme que le formulaire ne produit jamais » — vraie d'un formulaire mono-produit, rendue fausse par le panier trois phases plus tôt. Une raison qui repose sur ce que le produit NE FAIT PAS doit être relue le jour où il se met à le faire.* **Synchronisation entre onglets (D36, 2026-08-03)** : un écouteur `storage` rafraîchit badge et vues dans les onglets qui n'ont pas écrit. 🚨 Ce n'est pas cosmétique — le formulaire COD soumet le **store** (`window.aodCart.items()`), pas l'écran : un onglet périmé affichant « 1 article » pouvait envoyer une commande à deux lignes. La donnée, elle, n'a jamais divergé (`read()` relit à chaque geste). Toggle OFF = zéro asset, zéro DOM. |
| `class-aod-cod-desc-video.php` | **Vidéo dans la description PRODUIT** (v1.35.0, étape 8.3) — distinct de la landing. Convertit, sur `the_content` **prio 20** (après le swap AR 9 / `wpautop` 10 / `wp_filter_content_tags` 12, garde produit), chaque lien marqué `<a class="aod-desc-video" href>` en **façade** YouTube (`render_facades()`, pur/testable ; ID re-dérivé et re-validé par `AOD_COD_Landing::youtube_id()`). Enqueue **conditionnel** (`wp_enqueue_scripts`) des assets `aod-landing` sur une fiche produit **non-landing** dont la description effective (FR ou AR) porte une vidéo : lien YouTube → CSS + JS façade ; `<video class="aod-desc-video-file">` (fichier) → CSS seule (largeur bornée ; lecture native, aucun JS). Réutilise entièrement le pattern façade de la landing (6.3). **Correctifs de revue 8.3 (v1.35.1, clôture 8.4)** : absorption du `<p>` **balancée** par conditionnel `(?(1)…)` — un lien vidéo en milieu de paragraphe ne laisse plus de paragraphe orphelin ; `filter_content` aligné sur la garde de `maybe_enqueue` (`is_product()` + skip landing) — plus de façade sans assets hors fiche produit ; court-circuit `render_facades`/`has_video` resserré (le marqueur `aod-desc-video-file` n'est plus confondu avec le lien). |

### Les 3 pages publiques (v1.24.0)

**Le contenu vit dans le CODE, pas dans la base.** Une page WordPress stocke son
texte en base ; `gettext` — donc le switcher FR/AR — ne traduit que le code. Une
page dont le texte serait tapé dans l'éditeur **resterait en français en arabe**,
sans que rien ne le signale. Les 3 pages ne sont donc que des **coquilles** portant
un shortcode, montées par `scripts/pages-publiques.sh` (idempotent) qui les marque
d'une meta `_aod_page_key` ∈ {`home`, `shop`, `contact`}.

Cette meta est la clé d'identité des pages : le code les reconnaît par elle,
**jamais par leur libellé** — un clone peut renommer « Boutique » en arabe.

**`AOD_COD_No_Tunnel` ne masque pas le panier, il le rend impossible à remplir** —
la distinction est le cœur du module. Un bouton « Ajouter au panier » n'est pas une
classe CSS, c'est un lien vers `?add-to-cart=123` :

| Levier | Effet |
|---|---|
| `remove_action( 'wp_loaded', [ 'WC_Form_Handler', 'add_to_cart_action' ], 20 )` | **L'action serveur est débranchée.** Une URL forgée ne fait plus rien. C'est la garantie de fond. |
| `woocommerce_product_add_to_cart_url` → permalien produit | Tout bouton d'ajout au panier, **d'où qu'il vienne**, mène à la fiche produit. Couvre les thèmes qui rendent leur propre bouton (Astra et son `ast-on-card-button`, qui ne passe par aucun hook Woo) : ils appellent tous `$product->add_to_cart_url()`. |
| `wp_dequeue_script( 'wc-add-to-cart' )` | Sans ce script, le clic suit le href au lieu d'ajouter au panier en AJAX. |
| `wp_dequeue_script( 'wc-cart-fragments' )` | Une requête AJAX par page en moins (trafic DZ = mobile). |
| `template_redirect` → Boutique | `/cart`, `/checkout`, `/my-account` renvoient vers la Boutique (si les pages existent encore ; sinon elles 404 d'elles-mêmes). |
| `wp_nav_menu_objects` + `wp_list_pages_excludes` | Purge les entrées du tunnel des menus, **et** du repli `wp_page_menu()`. |
| `astra_woo_header_cart_icon` → `false` | Icône panier de l'en-tête Astra. **Spécifique au thème** — cf. `CHECKLIST-THEME-CHILD.md` C7 pour les autres. |

Recette : `bash tools/recette/run.sh public` — en visiteur non connecté, dans un vrai
navigateur (un admin connecté voit d'autres menus, ce qui masquerait le défaut).

Le **formulaire de contact** réutilise les 4 couches anti-bot du formulaire COD —
**dont 3 sur un clone neuf** — (honeypot `aod_url`, time-trap `elapsed`, limite par
IP, puis Turnstile, qui exige des clés Cloudflare) : pas de seconde défense à
maintenir. Depuis la 32.3, les deux premières sont littéralement la MÊME garde
(`AOD_COD_Antibot`) — elles partageaient déjà leurs deux contournements, il ne
manquait qu'un durcissement appliqué d'un seul côté pour que les formulaires
divergent. Il envoie par `wp_mail()` avec un `Reply-To` sur le visiteur ;
le `From` n'est **pas** usurpé (SPF/DKIM classeraient le message en spam).

### Matrice transporteurs

| Transporteur (id) | Envoi | Stop-desk | Suivi | Étiquette PDF |
|---|---|---|---|---|
| Yalidine (`yalidine`) | ✅ | ✅ (centres via API) | ✅ | ✅ |
| Yalitec (`yalitec`) | ✅ (hérite Yalidine, domaine ≠) | ✅ | ✅ | ✅ |
| E-com Delivery / ex-ZR Express (`zrexpress`) | ✅ (API v2) | ✅ | ✅ | ❌ |
| Maystro (`maystro`) | ✅ | ❌ (domicile only) | ✅ | ❌ |
| Noest (`noest`) | ✅ | ✅ | ✅ | ✅ |
| EcoTrack (`ecotrack`) + **23 white-labels** | ✅ | ✅ | ✅ | ✅ |

White-labels EcoTrack (même API, domaine pré-rempli) : Allo Livraison, Rex,
GOLIVRI, DHD, Anderson, Areex, BAC Express, Conexlog (UPS), Coyote, Distazero,
48HR, Fret Direct, MonoHub, MSM Go, Negmar, Packers, Prest, RB Livraison,
Rocket, Salva, Speed, TSL, Worldexpress.

✅ **Tarification API — Phase 4 livrée (cod-form v1.21.1 · dashboard v0.21.1).**
Contrat normalisé
`AOD_Carrier::get_fees( $force ) → [ wilaya_id => ['home'=>int,'desk'=>int] ]`
(+ `supports_fees()`), repli abstrait « non supporté ». La **couche transporteur**
reste **lecture seule** (n'écrit jamais `aod_cod_delivery_prices`), cache transient
12 h. Implémenté pour :
- **Yalidine / Yalitec** (4.1) : endpoint `fees`, 58 appels (timeout 8 s/req),
  réduction commune→wilaya par **mode** (`fees_from_response()`).
- **E-com Delivery / ex-ZR (`zrexpress`)** + **EcoTrack (+ 23 white-labels)** (4.2) :
  un **seul** appel (`/tarifs` · `get/fees`, timeout 8 s), réduction « une ligne
  par wilaya » par le helper mutualisé `AOD_Carrier::normalize_wilaya_fees()`
  (clés candidates tolérantes) ; cache EcoTrack **par domaine**. Schémas
  **confirmés en recette réseau réelle** le 2026-07-13 (E-com : 2 bugs trouvés et
  corrigés ; EcoTrack : conforme) — cf. §7, item 4.

✅ **Suivi par lot (cod-form v1.23.0).** Contrat
`AOD_Carrier::bulk_tracking_limit()` (1 = pas de lot) +
`get_tracking_bulk( $trackings ) → [ tracking => array|WP_Error ]` (repli par
défaut : une boucle sur `get_tracking()`, donc rien à faire pour les livreurs qui
n'ont pas d'endpoint de masse). **E-com Delivery** le surcharge : `POST
/colis/statuts` (le statut, qui fait foi) + `POST /colis/historique` (la
chronologie) → **40 colis en 2 appels au lieu de 40**. Les deux endpoints sont
requis, et un lot dont l'un des deux échoue n'écrit **rien** (on réessaiera) :
enregistrer un statut sans historique écraserait la chronologie déjà connue de la
commande. Unitaire et lot partagent `tracking_from_parts()` côté transporteur et
`AOD_Ship_Tracking::apply_tracking()` côté écriture — un colis ne peut pas changer
de statut selon la façon dont il a été rafraîchi.

Noest/Maystro : repli abstrait (pas d'API tarifs). **UI d'import (4.3, dashboard
v0.21.1)** : onglet Livraison de `/gestion` → panneau conditionnel (transporteur
configuré **et** outillé), AJAX `aod_cd_import_fees` (`op=preview|apply|restore`,
nonce + `aod_cap_shipping_prices`). Préview **lecture seule** (marge à la volée en
JS) ; `apply` recalcule la marge **côté serveur** (helpers purs
`AOD_CD_Section_Shipping::apply_margin()` / `import_apply()` — sur
`AOD_CD_Dashboard` jusqu'à l'extraction 30.4), n'écrit que les wilayas
cochées, sauvegarde l'état dans `aod_cod_delivery_prices_backup` avant écriture ;
`restore` réécrit ce backup. La page admin « Livraison COD » garde son éditeur
manuel (l'import est réservé au dashboard, là où vivent les identifiants
transporteur) — **déviation validée par l'owner (2026-07-11)**.

### Modèle de données

- **Options** : `aod_cod_delivery_prices`, `aod_cod_free_shipping`,
  `aod_cod_pixels`, `aod_cod_whatsapp`, `aod_cod_turnstile`,
  `aod_shipping_auto`, `aod_carrier_{id}`, `aod_cod_leads_db_version`,
  `aod_cod_cart` (panier COD togglable, Phase 35 — écrite par `/gestion`,
  **possédée ici** : lue par `AOD_COD_Form::cart_enabled()`, purgée par
  l'uninstall de ce plugin ; absente ou ≠ `'1'` = formulaire direct seul).
- **Métas de commande** : `_aod_wilaya_code/_name/_name_ar`, `_aod_commune(_ar)`,
  `_aod_delivery_type` (`home`/`desk`), `_aod_source` ; expédition :
  `_aod_ship_carrier/_tracking/_label/_import_id/_stopdesk/_error` ; suivi :
  `_aod_ship_status/_status_label/_status_history` (JSON) `/_status_at`.
  Statuts normalisés : `unknown, created, in_transit, out_for_delivery,
  delivered, returned, failed, cancelled`.
  Conversions (pixels + envois serveur) : `_aod_pixels_fired` (anti double comptage
  navigateur) ; gardes d'envoi unique **distinctes** `_aod_capi_sent` (Meta, Phase 10),
  `_aod_capi_tiktok_sent` (TikTok, 13.1), `_aod_capi_snap_sent` (Snapchat, 13.2) —
  activer plusieurs régies envoie bien à chacune.
- **Métas produit** (lues) : `_aod_options`, `_aod_variant_label`, `_aod_offers`,
  `_aod_qty_tiers` (legacy), `_aod_selling_points`, `_aod_is_pack`, `_aod_pack_items`,
  `_aod_cost_price` (prix de revient, base des marges de l'Accueil) et
  `_aod_stock_note` (note d'inventaire, Phase 3).
- **Métas produit — bilingue** (Phase 7, cf. §6) : `_aod_title_ar`, `_aod_desc_ar`,
  `_aod_selling_points_ar` — plus `_aod_cat_name_ar` côté **terme** de catégorie.
- **Méta commande — total forcé** : `_aod_manual_total`, écrite par la modale
  d'édition de `/gestion`. ⚠️ Elle **surcharge le total calculé** d'une commande
  réelle : toute lecture de chiffre d'affaires doit en tenir compte.
- **Métas produit — landing** (v1.25.0) : `_aod_landing_enabled` (`'1'` = la fiche
  produit devient une page de vente) et `_aod_landing` (structure complète,
  `version: 1`). Dans cette structure, **tout texte est un couple `{ fr, ar }`**
  (le contenu marchand ne passe pas par `gettext` — cf. §6), **toute image est un
  ID d'attachement** (une URL ne survivrait pas au changement de domaine) et
  **aucun HTML libre** n'est accepté. Écrites par `/gestion` **via**
  `AOD_COD_Landing::sanitize()` — jamais directement : deux implémentations du
  schéma dériveraient au premier bloc ajouté.
- **Term-metas catégorie** (`product_cat`) : `thumbnail_id` (image WooCommerce,
  affichée sur l'accueil), `_aod_cat_name_ar` (nom arabe, 7.5 — swap public par
  le filtre `get_term` de `AOD_COD_Catalog_Filters`), et **`_aod_cat_home`
  (`'1'` = affichée sur l'accueil) + `_aod_cat_home_order` (ordre)** — posées/lues
  par `AOD_CD_Section_Products::ajax_save_category()` (9.1, saisie « Afficher sur
  l'accueil » + ordre dans `/gestion`). Convention « absence = défaut » : décocher
  **supprime** la méta ; un enregistrement d'image ne les envoie pas, donc les
  laisse intactes (helper pur `home_meta_ops()`). **Consommées par l'accueil (9.2,
  cod-form v1.36.0)** : `AOD_COD_Pages::home_categories_markup()` rend la sélection
  cochée dans l'ordre (`orderby="include"`, vides comprises), sinon retombe sur
  toutes les non vides — plafond de 6 retiré (cf. `docs/ROADMAP-AMELIORATIONS.md` §A.9).
- **Table custom** : `{prefix}aod_cod_leads` (prospects — conservée par défaut
  à la désinstallation). `DB_VERSION` **3** : colonne `assigned_confirmer`
  (Phase 43.3) puis `assigned_by` (43.4), toutes deux `BIGINT UNSIGNED NOT NULL
  DEFAULT 0` — ici **`0` EST l'absence**, là où la commande SUPPRIME sa méta
  (deux stockages, deux façons d'exprimer « rien » : ne pas les confondre).
  `assigned_by` n'est **jamais affichée** : elle existe pour survivre à la
  conversion, `build_skeleton_order()` la reportant sur `_aod_assigned_by`.

> 🧹 **Désinstallation — ce qui part et ce qui reste (Phase 25).** Les deux
> `uninstall.php` sont gardés par `tests/unit/test-uninstall-residus.php` : toute
> option, capability ou préfixe de transient découvert dans `plugins-dev/` doit être
> supprimé, couvert par un motif `LIKE`, ou **déclaré avec sa raison**.
> **Partent** : les options des deux plugins (dont `aod_cod_turnstile`, qui porte la
> **clé secrète Cloudflare** et survivait en clair), les transients, les rôles, et —
> depuis la 25.1 — les **8 capabilities posées par utilisateur**, que `remove_role()`
> ne touchait pas : elles vivent dans le usermeta `{prefix}capabilities`, jamais sur
> un rôle (droits « à la carte », cf. `AOD_CD_Roles::CONFIRMER_CAPS`).
> **Restent à dessein** : la table des prospects + son marqueur de version (mode
> prudent), et **tout le contenu marchand** — term-metas ci-dessus, post-metas de la
> page de vente, textes AR, variantes, offres. Une désinstallation peut être
> temporaire ; effacer le travail du gérant serait une perte nette et silencieuse.
> ⚠️ Le cliquet est **statique**, et la **propriété** d'une option n'a aucun signal
> mécanique ici — `aod_seq_order_counter` est écrite par le dashboard sans lui
> appartenir (elle est au mu-plugin), les `aod_cod_*` sont écrites par le dashboard
> en appartenant au COD. « Qui écrit possède » est faux **dans les deux sens** : la
> propriété reste **déclarative**, et le test détecte le non-déclaré, pas le
> mal-déclaré.
- **Statut custom** : `wc-aod-confirmed` (« Confirmée ») → **ne déclenche plus rien**
  depuis la Phase 47.2. L'envoi est un geste : bouton « Envoyer » de la colonne
  Suivi → `aod_cd_ship_send` → `AOD_Shipping::ship_order()`. *(Cette ligne a
  annoncé « déclenche l'envoi auto » jusqu'au 2026-08-01.)*

### AJAX / hooks exposés

- Front (priv+nopriv) : `aod_cod_submit`, `aod_cod_desk_communes`, `aod_cod_lead`,
  `aod_contact_submit` (formulaire de contact — **même surface d'attaque publique**,
  mêmes couches anti-bot que la commande, cf. `class-aod-cod-contact.php`),
  `aod_cod_cart_items` (35.3 — **lecture seule**, refusé si le panier est coupé,
  nonce + plafond 30 items ; ne sert que des données déjà publiques).
- Admin (`manage_woocommerce`) : `aod_ship_send`, `aod_ship_centers`.
- Action interne : `do_action('aod_cod_order_created', $order)` — consommée par
  WhatsApp **et** par le mu-plugin de numérotation séquentielle.
- Pas de REST custom.

### Sécurité du formulaire (6 contrôles : le nonce CSRF + les **5 couches anti-bot**, dont 4 sur un clone neuf)

1. Nonce CSRF (`aod_cod_nonce`) sur tous les AJAX.
2. Honeypot (champ caché `aod_url`).
3. Time-trap (`elapsed` < 3 s ⇒ rejet ; tolérant si absent — cache).
4. Rate-limit IP : 10 commandes/h (`AOD_COD_MAX_PER_IP_HOUR`, transient).
5. Cloudflare Turnstile (fail-closed si jeton invalide, fail-open si CF injoignable).
6. Anti-doublon téléphone : 1 commande / 24 h / numéro.

+ validations serveur : téléphone DZ (`/^0[5-7][0-9]{8}$/`), commune∈wilaya,
prix **recalculé côté serveur** (anti-triche), frais de livraison recalculés.
Détail opérationnel : [RUNBOOK-ANTI-BOT-COD.md](RUNBOOK-ANTI-BOT-COD.md) et
[Turnstile.md](Turnstile.md).

---

## 3. Plugin `aod-client-dashboard` (v0.75.0) — espace gérant `/gestion`

≈ 11 900 lignes PHP dont un cœur monolithique en cours de décomposition
(`class-aod-cd-dashboard.php` : coque, routeur, section Commandes + ses AJAX —
le reste est extrait, cf. Phase 30 ; le compte de lignes courant vit dans
`tests/unit/test-decomposition.php`). Depuis l'étape 1.4, toute **nouvelle** section vit
dans sa propre classe ; l'étape **7.0** a en plus **extrait** l'ancienne section
Produits/Catégories du monolithe (le monolithe est passé de 5 205 à 3 787 lignes) :
`class-aod-cd-section-team.php` (472 l.), `class-aod-cd-section-home.php`
(1 875 l. — l’ancienne section « Statistiques » y a été migrée),
`class-aod-cd-section-stock.php` (748 l.), `class-aod-cd-section-landing.php`
(éditeur de page de vente : modèle de départ, copie inter-produits : il DESSINE le formulaire à partir de
`AOD_COD_Landing::schema()` et RENVOIE le POST à `AOD_COD_Landing::sanitize()`, sans
jamais réimplémenter le format) et `class-aod-cd-section-products.php` (1 845 l. —
Produits **et** Catégories : CRUD, variantes couleur, offres, packs, galerie ;
`render()` + `render_categories()`, ses `get_product_options()`/`get_product_offers()`
publiques restent consommées par l'édition de commande (`AOD_CD_Section_Orders`
depuis la 30.6)). Front : `assets/css/dashboard.css` + le JS décomposé (Phase 33) —
la coque `assets/js/dashboard.js` (helpers partagés, `init()`, registre + ORDRE
canonique) et 11 modules `section-*.js` chargés par `dashboard_scripts()` —,
sans aucune librairie tierce.

Deux classes de service complètent l'ensemble :

- **`AOD_CD_Account`** (`class-aod-cd-account.php`, 201 l.) — page **wp-admin**
  « Utilisateurs → **Accès Gérant** » : crée le compte du gérant avec le bon rôle.
  C'est le point d'entrée nommé dans `TEMPLATE.md` §8.
  ⚠️ **Piège de nommage** : `AOD_CD_Account` (admin) n'a **rien à voir** avec la
  section `account` (« Mon compte » de `/gestion`), extraite en 30.3 vers
  `class-aod-cd-section-account.php` (`AOD_CD_Section_Account`).
- **`AOD_CD_Lang`** (`class-aod-cd-lang.php`, 130 l.) — switcher FR ⇄ AR **de
  `/gestion`**, sur le même cookie `aod_lang` que le public. C'est le pendant de
  `AOD_COD_Lang` côté gérant.

**Une 13ᵉ section existe, invisible dans le menu** : `landing` (Page de vente). Elle
n'est PAS dans `sections()` — c'est ce qui la tient hors de la barre latérale — mais
`hidden_sections()` la rend **atteignable par URL** (`routable_sections()` = menu +
masquées). On y entre depuis la ligne d'un produit ; une entrée de menu mènerait à un
écran sans sujet.

### Les 12 sections de `/gestion` (routeur `$this->sections`)

> Depuis la **Phase 15**, le 2ᵉ élément de chaque entrée de `sections()` est un
> **nom d'icône** du catalogue `AOD_CD_Icon`, plus un emoji : la police emoji du
> système donnait un rendu différent sur chaque machine. Ce tableau a longtemps
> gardé les emojis d'origine — `tests/unit/test-icones.php` porte désormais un
> cliquet qui en interdit le retour dans le code.

| Slug | Section | Contenu |
|---|---|---|
| `home` | Accueil | **Section par défaut** (première du menu, v0.19.0, étape 2.1 — classe dédiée `AOD_CD_Section_Home`). 3 onglets gouvernés par capability : **Revenus** (gérant : contenu de l'ex-section Statistiques — CA, panier moyen, marge `_aod_cost_price`, top-produits, graphe SVG), **Livraison** (`aod_cap_stats_delivery`, v0.19.2, étape 2.3 : sur les commandes **expédiées** de la période — méta `_aod_ship_tracking` non vide, quel que soit le statut Woo — taux **livré / en transit / retourné / échec** + colis annulés d'après le statut de colis normalisé `_aod_ship_status` regroupé en 5 groupes, ventilations **par transporteur** et **par wilaya** (`_aod_wilaya_code`, top 10 + « Autres », noms FR/AR via `AOD_COD_Data::label()` résolus **au rendu** — jamais dans le cache), **délai moyen de livraison** calculé sur l'historique `_aod_ship_status_history` (premier événement horodaté → événement « livré » ; colis sans `ts` exclus et signalés), **colis bloqués** = statut non terminal sans événement depuis > `BLOCKED_DAYS` (7 j, constante) — ⚠️ **depuis la 48.5, le cron de suivi bumpe bien la version de cache** : il écrit `_aod_ship_status*` par `$order->save()`, donc il passe par `woocommerce_update_order` ; la phrase inverse valait avant que ce hook existe, et le TTL 5 min n'est plus le seul borneur ; un confirmateur restreint (`assigned_products`) ne voit que les commandes de son périmètre — jointure SQL dédiée **et** périmètre dans la clé de cache, sélecteur produit borné, `?produit=` hors périmètre ignoré) et **Confirmation** (`aod_cap_stats_confirm`, v0.19.3, étape 2.4 : sur les commandes créées dans la période, tous statuts — **taux de confirmation** (tracées `_aod_confirmed_by/_at` + « non attribuées » = statut Confirmée/Terminée sans traçabilité, antérieures à la Phase 1), **délai moyen création → confirmation** calculé sur les tracées (`_aod_confirmed_at` est en heure locale du site → conversion `get_gmt_from_date()`, délais négatifs exclus ; format adaptatif h/jours, seuil 48 h), **ventilation par confirmateur** (libellés résolus au rendu, gérant auto-confirmant inclus, ligne « Non attribuées ») ; **visibilité par rôle décidée au rendu** : le gérant voit toutes les lignes, un confirmateur ne voit que la sienne — **et depuis la Phase 47.5, ses trois cartes aussi** (un seul `$row_uid` gouverne cartes, période précédente et tableau ; « Reçues » et « Part » restent au périmètre boutique, avec une note qui le dit — cf. §5 « Qui voit quoi dans l'onglet Confirmation ») — les données en cache restent complètes, la clé n'intègre ni le rôle ni le filtre d'écran confirmateur (appliqué en PHP : cartes = chiffres de X, taux rapporté aux Reçues globales) ; périmètre produits (`user_scope`) appliqué en SQL et présent dans la clé). **Barre de filtres commune** (formulaire GET sans JS) : produit, confirmateur (gérant seul), période Jour/Semaine + plage libre `date_from`/`date_to` (inputs date natifs) ; état persistant en query-string, reporté par les liens d'onglet. **Filtres produit/confirmateur appliqués aux chiffres** (v0.19.1, étape 2.2) : produit = seules les **lignes** de ce produit comptent (CA hors livraison et hors autres produits des mêmes commandes ; correspondance parent **ou** variation), confirmateur = commandes portant `_aod_confirmed_by` ; combinables en ET. Filtrage **côté SQL** par jointures armées le temps de la requête (`filtered_orders()` — sous-requête `wc_order_product_lookup` + jointure méta, bimode HPOS `wc_orders_meta` / CPT `postmeta`, patron de `scoped_orders()`). « En attente » ne suit que le filtre produit (une commande non confirmée n'a pas de confirmateur) ; « Produits en ligne » reste global. **Cache** : agrégats en transients 5 min, clé versionnée par l'option `aod_cd_home_cache_ver` incrémentée sur **`woocommerce_update_order`** (48.5 — toute `$order->save()`, donc **les écritures de méta**, dans les deux magasins) / `woocommerce_new_order` / `order_status_changed` / corbeille / suppression (~0,07 s à chaud sur 1 000 commandes, filtres compris ; clé = plage + produit + confirmateur). 🚨 **Le premier de la liste est celui qui manquait** : sans lui, assigner une commande à un confirmateur n'invalidait rien et sa carte « Commandes assignées » servait un chiffre périmé jusqu'à 5 min — cf. §5, encadré 48.5. |
| `orders` | Commandes | Liste filtrée + compteurs, recherche, pagination, statut inline AJAX, détail/édition en modale (articles + total modifiable), suppression groupée, cellule « Suivi » (envoi transporteur). **Depuis la 12.1 (v0.38.0), la liste et les compteurs EXCLUENT les commandes déjà expédiées** — critère `_aod_ship_tracking` non vide, le même que « Suivi commande » : une commande expédiée « se déplace » de « Commandes » vers « Suivi » (partition exacte, rien ne se perd). Helper mutualisé `unshipped_meta_query()` (`meta_query` `NOT EXISTS` ∨ `= ''`, vide si `AOD_Carrier` absent) injecté dans le rendu **et** `count_orders()`. Classe dédiée `AOD_CD_Section_Orders` depuis la 30.6 (rendu, 5 AJAX, périmètre produits, pack) — ne pas confondre avec `AOD_CD_Orders` (lots, 31.6). Invariant : une commande en **erreur d'envoi** (`_aod_ship_error` **sans** tracking) **reste ici** — à retraiter, absente de « Suivi ». N'affecte pas les clauses de périmètre confirmateur de `scoped_orders()` (jointure SQL indépendante du `meta_query`). **Depuis la 34.1 (v0.49.0)**, chaque ligne à téléphone (et la modale de détail) porte le rang **Appeler** (`tel:`) + **WhatsApp** (`wa.me`, 0→213) de la section Abandonnées — rendu pur, peau partagée `.aod-cd-call-actions`, conversion via `AOD_CD_Section_Abandoned::wa_number()` (`public static`, le SEUL exemplaire du dashboard — même précédent inter-sections que les getters de `Section_Products`). **Depuis la Phase 42**, chaque commande porte un confirmateur **assigné** (`_aod_assigned_confirmer`, cumulatif au périmètre produit) : un confirmateur ne voit/n'agit QUE sur ses commandes assignées ; sélecteur d'assignation/réassignation et barre d'assignation groupée réservés au gérant (`aod_cd_order_assign`/`aod_cd_orders_bulk_assign`, action-forte) ; filtre « Non assignées » (`count_unassigned()`). Cf. §3 « Assignation par commande » ci-dessus. **Depuis la 47.3**, la colonne « Note » a DISPARU de la liste et le handler `aod_cd_order_note` avec elle : il écrivait dans l'**historique WooCommerce** (`add_order_note()`), une liste append-only mêlée aux notes système dont **rien ne part chez le transporteur**. La note du confirmateur est désormais un **champ du volet d'édition** de la modale (`<textarea name="note">`, jamais TinyMCE), enregistrée avec le reste par `aod_cd_order_save_info` sous **`AOD_Carrier::META_DRIVER_NOTE`** (`_aod_cd_confirmer_note`) — **aucun handler AJAX de plus, aucune capability de plus** (`aod_cap_confirm_orders` couvrait déjà l'édition). ⚠️ **Deux écritures d'historique existaient : une seule est partie.** Celle de `ajax_order_save_info()` (« Infos client / livraison… modifiées depuis l'espace de gestion ») **reste** — c'est la traçabilité d'une ÉDITION, pas une consigne —, et le bloc « Historique » de `render_order_detail()` aussi. La note voyage jusqu'au livreur chez **26 transporteurs sur 29** (`'remarque'` d'EcoTrack + ses 23 white-labels et de Noest, `'note_to_driver'` de Maystro — trois champs qui existaient déjà et partaient **vides**) ; Yalidine, Yalitec et E-com Delivery n'en ont aucun, et l'**écran le dit** au lieu de bricoler un autre champ (décision D3 : la coller dans `'article'` la mélangerait au contenu du colis). Cet avertissement est calculé **côté serveur** dans `ship_cell_html()` — attribut `data-note-perdue` sur le bouton « Envoyer » — parce que lui seul connaît le transporteur RÉSOLU (`AOD_Shipping::carrier_for()`, dont la 47.1 exige une occurrence unique) ; le JS ne fait que le lire, comme il lit déjà `eligible: !! btn`. Un `WP_Error` (aucun transporteur) vaut `0` : on ne prévient pas d'un envoi qui sera refusé pour une meilleure raison. Le geste **unitaire** ouvre la fenêtre de confirmation de la 47.2 **uniquement** quand `data-note-perdue="1"` — sans quoi un confirmateur, qui n'a aucune case à cocher, n'aurait jamais vu l'avertissement. Longueur bornée par **`AOD_Carrier::NOTE_MAX`** (255 **caractères**, `mb_substr`) : un seul nombre, lu par le `maxlength` du champ, le clip serveur à l'enregistrement et `AOD_Carrier::driver_note()` — sinon l'échec arriverait chez le transporteur, donc **après le verrou d'envoi et au milieu d'un lot**. Cliquet : `tests/unit/test-note-livreur.php` (R1 construit les **29** charges utiles et y cherche la note : présente ⇔ `supports_driver_note()`). |
| `abandoned` | Commandes abandonnées | **Classe dédiée `AOD_CD_Section_Abandoned`** (Phase 11, v0.40.0, cap `aod_cap_abandoned`). Présente dans `/gestion` les **prospects du formulaire COD** restés `status='pending'` dans la table `{prefix}aod_cod_leads` (plugin `aod-cod-form`) — un visiteur qui a saisi son téléphone sans cliquer « Commander ». **Aucune capture, aucune migration** : lecture seule de la table existante (la capture au blur du tél., `upsert()` par token et `mark_converted()` vivent déjà côté COD). Onglets **Tous / Abandonnés (> 30 min) / En cours (< 30 min)** avec compteurs (seuil `ABANDON_AFTER` = 1800 s, aligné sur la page wp-admin des leads), recherche nom/téléphone, pagination, boutons **Appeler** (`tel:`) + **WhatsApp** (`wa.me`, 0→213). Un lead **`converted`** n'apparaît jamais (il n'est plus abandonné) ; un compteur « récupérés en commande » donne le signal de conversion. **Périmètre confirmateur** appliqué **côté SQL** (`product_id IN assigned_products`, jamais un masquage). Table à **PII** (téléphone) → jamais de `nopriv`. **Depuis la 39.1 (v0.51.0)**, chaque ligne à téléphone + produit porte un bouton **« Commander »** (masqué sauf `aod_cap_confirm_orders`) qui appelle l'AJAX `aod_cd_convert_lead` : création d'un **squelette** de commande `pending` (produit racine + coordonnées du prospect, meta `_aod_source='aod-abandoned'`, jamais `aod-confirmed`) puis `mark_converted()` — le lead quitte alors la liste. C'est le premier binder JS de la section (`section-abandoned.js`, `bindAbandonedConvert`) ; la classe est donc instanciée au boot pour enregistrer son AJAX. **Depuis la 39.2 (v0.52.0)**, la conversion **ouvre la modale d'édition existante** sur la commande créée : `openOrderModal( orderId )` est extrait de `bindOrderDetail` et exposé sur `window.aodCdCore` (le shell `#aod-cd-modal` est du chrome global), et `section-abandoned.js` l'appelle après conversion. Le **statut y est choisissable** — `render_order_detail()` rend le même `status_select_html()` que la liste, câblé par `wireStatusWidget()` (extrait de `bindStatus`, aussi appelé par `initOrderEdit` sur le widget injecté). « Confirmée » réutilise `aod_cd_order_status`. ⚠️ **Depuis la Phase 47.2, ce statut n'expédie plus rien** : il ne déclenche aucun appel transporteur, et la bascule vers Suivi (partition `_aod_ship_tracking`, Phase 12) n'a lieu qu'après l'envoi EXPLICITE — bouton « Envoyer » de la colonne Suivi, handler `aod_cd_ship_send` → `AOD_Shipping::ship_order()`. Cette phrase a annoncé une bascule « automatique » jusqu'au 2026-08-01. **Aucun handler AJAX neuf** (toujours 29), refactor JS à comportement constant. **Depuis la 39.3 (v0.53.0)**, le parcours est gardé : cliquet `tests/unit/test-conversion-abandon.php` (R1 symétrie de permission — le **bouton** ET l'AJAX exigent `aod_cap_confirm_orders` : la capability **croisée** `aod_cap_confirm_orders` **en plus** de la cap de section `aod_cap_abandoned` est une divergence VOULUE ; R2 anti-doublon structurel ; R3 squelette `pending` sans envoi ; R4 une seule surface AJAX) et porte `run.sh abandonnees` étendue au parcours conversion → modale → statut (relit en base + purge via `scripts/recette-verify-phase39.php` + le `cleanup` de `recette-seed-phase11.php`). **Depuis la 39.4 (v0.54.0)**, le **téléphone seul** suffit à convertir : le bouton n'exige plus `product_id > 0` (seule la cap + un téléphone), `ajax_convert_lead()` garde le téléphone (422 si absent) et **ne refuse plus** un produit supprimé, et `build_skeleton_order()` accepte un `$product` **nullable** → squelette **sans ligne d'article** que le confirmateur compose dans la modale après appel. Le périmètre `assigned_products()` reste sur le `product_id` du lead (gérant convertit tout prospect à téléphone ; confirmateur restreint borné à son périmètre). Cliquet R1bis + R3 cas `null`, porte : prospect **produit supprimé** converti (ITEMS=0). **Depuis la 39.5 (v0.55.0)**, la **modale d'édition partagée** (`render_order_edit_form`, section Commandes) sait remplir une commande **vide** : le bloc Articles — conteneur `.aod-cd-od-items` (rendu même vide), **« Ajouter un article »** (conditionné au catalogue `orderable_catalog()`, pas aux items) et le champ **Total** — n'est plus enfermé dans `if ( $items )`. C'est le **panier multi-produits dans la modale** : chaque ajout empile une ligne (`buildNewItemRow`, variantes + qté), le submit collecte tout via `aod_cd_order_save_info` (`new_product` multiples + `total_override`). **PHP-only** (serveur + JS inchangés, leurs gardes no-op s'activent) ; critère « commande vide », pas « abandon » → zéro régression sur les commandes à articles. **Depuis la 39.6 (v0.56.0)**, la section gagne une **sélection groupée + suppression** (case par ligne + « tout sélectionner » + barre d'action), patron réutilisé de la page Commandes. Nouvel AJAX `aod_cd_delete_leads` — **GÉRANT uniquement** (`manage_woocommerce`, `action-forte` au cliquet permissions : supprimer est destructif et borné comme `delete_orders`, un confirmateur `aod_cap_abandoned` ne purge pas) — `DELETE … WHERE id IN (…)` préparé sur `aod_cod_leads` ; ne touche pas aux commandes (un lead converti n'est plus listé). Classes CSS comportementales `.aod-cd-leadcheck*` distinctes de la page Commandes (le binder Orders tourne aussi sur la page mais no-op faute de ses cases). Binder `bindAbandonedBulkDelete` (`section-abandoned.js`, `register()` + `ORDER`). La section porte donc désormais **2 handlers AJAX** (`convert_lead` + `delete_leads`, tous deux `action-forte`), aucune écriture de champs/statut de commande (qui restent à `section-orders`). |
| `products` | Produits | CRUD complet, variantes couleur (prix/stock/photo), options, offres/paliers, packs assortiment, galerie, prix de revient (`_aod_cost_price`), upload. **Description riche (8.1, v0.34.0)** : TinyMCE (embarqué WP, chargé « nu » — la coque `/gestion` n'a ni `wp_head` ni `wp_footer`, `wp_enqueue_editor()` y est inopérant) sur les DEUX cases du couple FR/AR, barre courte (gras, italique, H2/H3, listes, alignement, lien), UI traduite par `_WP_Editors::wp_mce_translation()` + `language:` obligatoire ; `tinymce.triggerSave()` AVANT le `FormData` du save (sinon perte silencieuse) ; la frontière reste `wp_kses_post()` (contrat verrouillé par `tests/unit/test-desc-kses.php`). **Correctifs de revue (v0.34.1)** : `wpautop` sur le contenu hérité (un ancien produit réenregistré n'aplatit plus ses paragraphes) ; locale de l'éditeur via `get_locale()` (le switcher FR/AR, pas le profil utilisateur) ; titre d'iframe distinct FR/AR (cases discernables au lecteur d'écran) ; `valid_styles` limité à `text-align` (un collage n'importe plus couleur/police). **Photos dans la description (8.2, v0.35.0)** : bouton image dans les deux éditeurs → upload immédiat (`aod_cd_desc_image`) → insertion d'un `<img class="wp-image-{id}">` **minimal** dans l'éditeur qui a cliqué ; `srcset`/`lazy` ajoutés **au rendu** par `wp_filter_content_tags` (prio 12, après le swap AR prio 9), jamais stockés (kses les strippe). **Vidéo dans la description (8.3, v0.36.0)** : bouton vidéo → modale à DEUX entrées. **Lien YouTube** → un `<a class="aod-desc-video" href>` (kses conserve ; `<iframe>` est strippé) converti **au rendu public** en **façade** cliquable par `AOD_COD_Desc_Video` (côté `aod-cod-form`) — 0 iframe, 0 requête YouTube avant le clic (pattern landing 6.3 réutilisé : JS/CSS/classes `aod-landing__video-*`). **Fichier local** → upload immédiat (`aod_cd_desc_video`) → `<video class="aod-desc-video-file" src preload="none">` natif (kses conserve `<video src>` mais strippe `<source>` : d'où le `src` direct) — aucune façade, aucun JS. `extended_valid_elements` + `convert_urls:false` sur l'éditeur (URLs absolues, `<video>` non strippé). **Correctif de revue 8.3 (v0.36.1, clôture 8.4)** : `ytId()` (validation à la saisie) réécrit en **reflet exact** de `AOD_COD_Landing::youtube_id()` (parsing `URL` + même whitelist d'hôtes, 11 caractères stricts) — un lien `youtube-nocookie.com` valide n'est plus rejeté, et un hôte sosie / un `v=` trop long n'est plus inséré pour finir inerte au rendu. **Hauteur adaptative (47.6)** : plugin **`wpautoresize`** (le fork WordPress — `autoresize` n'existe pas dans `wp-includes`) + **`wp_autoresize_on`**, l'interrupteur sans lequel le plugin se charge et **ne fait rien**, + `autoresize_max_height: 500` — sans plafond, une longue description pousserait « Enregistrer » hors de l'écran. **Aucun `autoresize_min_height`** : le défaut du plugin est la hauteur du `<textarea>`, donc `rows="6"` (mesuré : 132 px, la hauteur d'avant la 47.6) — le coder en dur en ferait une seconde source de vérité qui divergerait le jour où `rows` change. Mesuré au navigateur : **132 → 416 → 500 px**, FR **et** AR. ⚠️ Les deux cases sont une grille `1fr 1fr` : **la case FR hérite de la hauteur de l'AR** (et réciproquement) — ne pas lire la hauteur de l'une comme une propriété de son propre contenu. |
| `stock` | Stock | **Classe dédiée `AOD_CD_Section_Stock`** (v0.20.0, étape 3.1, cap `aod_cap_stock`). Vue de couverture en lecture seule : par produit publié — stock, seuil d'alerte (`get_low_stock_amount()`, repli option globale), vendus sur la période (boucle d'items des commandes encaissées — aucune jointure custom, bimode natif ; variation → parent), vitesse (ventes/jour), couverture (stock ÷ vitesse, « ∞ » si 0 vente), badges Rupture (stock ≤ 0) / Stock bas (≤ seuil) / OK ; tri par couverture croissante, « non suivi » en fin ; packs : stock des composants + nb assemblable (min des floor(stock/qty)) ; périmètre confirmateur = `include` de la liste produits ; filtre période = mêmes paramètres d'URL que l'Accueil ; cache ventes 5 min versionné par `AOD_CD_Section_Home::CACHE_VER_OPTION` (hooks d'invalidation non dupliqués) ; pas de pagination (tri global, petits catalogues assumés). **Ajustement rapide** (v0.20.1, étape 3.2) : édition inline quantité/seuil/note d'inventaire (`_aod_stock_note`, datée, dernière note seulement) via AJAX `aod_cd_save_stock` — garde nonce + `edit_products` + `edit_product` + périmètre (403 sinon), badge recalculé côté serveur dans la réponse, couverture recalculée en JS depuis `data-speed`, tri rejoué au rechargement ; **encart d'alertes** (ruptures + stock bas, 10 noms + « … et N autres ») en tête ; un confirmateur `aod_cap_stock` seul reste en lecture seule (pas d'inputs). |
| `categories` | Catégories | CRUD catégories produit : parent (hiérarchie), image, compteur. |
| `shipping` | Livraison | Tarifs 58 wilayas (Domicile/Stop-desk), livraison gratuite, transporteurs + envoi auto (dépend d'`AOD_COD_Data`). **Carte « Panier » (35.1, v0.50.0)** : toggle de l'option `aod_cod_cart` (panier COD, Phase 35) — carte **réservée au gérant** dans cette section gouvernée, même séparation de portée que le bloc Transporteurs (31.1) ; AJAX dédié `aod_cd_save_cart_mode` (nonce + `manage_woocommerce`, jamais un champ de `save_shipping`), divergence déclarée avec sa raison au cliquet permissions. |
| `tracking` | Suivi commande | Tableau des colis expédiés (état normalisé, dernière MAJ), timeline en modale, « Actualiser tout » (dépend d'`AOD_Shipping`). |
| `marketing` | Pixels & Tracking | Meta/TikTok/Snapchat/Google Ads (édite `aod_cod_pixels`). **Carte « Meta — API de Conversion (CAPI) »** (Phase 10, v0.39.0) : jeton secret (`type=password`, « vide = inchangé » via `capi_token_from_post()`, case « effacer »), code d'événement test, bouton **« Tester la connexion »** (AJAX `aod_cd_test_capi` → `AOD_COD_Pixels::test_capi_connection()`). Le jeton n'est jamais ré-affiché en clair. **Cartes « TikTok — API de Conversion (Events API) » (13.1) et « Snapchat — API de Conversion » (13.2)** : même contrat de jeton secret (`tiktok_capi_token` / `snapchat_capi_token`), **avec bouton « Tester la connexion » (13.3)** — TikTok requiert un `tiktok_test_event_code` (champ dédié) pour tester sans polluer la production ; Snapchat teste via son endpoint `/events/validate`. Un bouton par carte (`data-platform`), routé par `ajax_test_capi`. **Couleurs de marque — Phase 41 (dashboard v0.61.0)** : chaque carte de régie porte sa couleur de marque via une classe modificatrice `aod-cd-pxcard--meta/-tiktok/-snapchat/-google` (catalogue fermé dans `dashboard.css`, jumeau des tons `t-*`) — icône pleine, liseré `inset-inline-start` (pseudo-élément, RTL-safe), en-tête teinté. Couleurs **universelles** de régies tierces (pas l'accent client), encre = `best_ink()` (AA : noir sur Meta/Snapchat/Google, blanc sur TikTok — le blanc « de marque » échoue AA), corps du formulaire sur fond blanc, « Devise » neutre, vert `is-on` sémantique. Gardé par `test-contraste.php` (lit le vrai CSS) + `test-charte-surfaces.php` (couleurs déclarées). Cf. [ICONES-ET-CHARTE-GESTION.md](ICONES-ET-CHARTE-GESTION.md) §6.4. |
| `whatsapp` | WhatsApp | CallMeBot + message test (édite `aod_cod_whatsapp`). |
| `team` | Équipe | **CRUD des confirmateurs** (v0.18.7, classe dédiée `AOD_CD_Section_Team`) : liste (permissions, dernier accès, statut), création (mdp saisi ≥ 10 car. ou généré, affiché une fois), 7 cases (6 caps + affectation produits `_aod_products_limited`) et **sélecteur multi-produits** (v0.18.8 : user meta `_aod_assigned_products`, vide = tous), désactivation réversible (surcharge `aod_use_dashboard` à false), suppression (`wp_delete_user` + réattribution). Gérant uniquement. |
| `account` | Mon compte | Nom/e-mail/mot de passe du gérant + **zone de reset boutique** (phrase de confirmation exacte + `manage_woocommerce`). |

### Rôles & accès

- Rôle `aod_shop_client` (« Gestion Boutique ») = capacités de `shop_manager`
  **moins** la liste `FORBIDDEN` (utilisateurs, plugins/thèmes/éditeur,
  `manage_options`, core, import/export, customize) **plus** `read`,
  `upload_files`, `aod_use_dashboard`.
- Rôle `aod_confirmateur` (« Confirmateur », v0.18.5, étape 1.2) = `read` +
  `aod_use_dashboard` **uniquement**. Ses droits métier viennent des **7** caps
  custom `AOD_CD_Roles::CONFIRMER_CAPS` (`aod_cap_confirm_orders`,
  `aod_cap_tracking`, `aod_cap_stock`, `aod_cap_shipping_prices`,
  `aod_cap_stats_confirm`, `aod_cap_stats_delivery`, **`aod_cap_abandoned`**
  — commandes abandonnées, Phase 11) posées **par utilisateur**
  (`WP_User::add_cap()`), jamais au rôle. Helper central
  `AOD_CD_Roles::user_can( $cap )` — le gérant (`manage_woocommerce`) est
  réputé avoir toutes les caps.
- **Resync auto** (`maybe_sync_role`, `init` prio 20) si un rôle manque ou si
  `aod_cd_roles_version` ≠ version du plugin ; garde-fou anti-drift : le rôle
  confirmateur est ramené à `{ read, aod_use_dashboard }` s'il a gagné
  d'autres caps (jamais `manage_woocommerce`/`delete_*`).
- `AOD_CD_Access` : wp-admin bloqué (redirection `/gestion`), admin bar masquée,
  login redirigé, page de connexion brandée (logo/couleur du thème) — pour le
  gérant **et** le confirmateur (`is_client()`).
- **Routeur & AJAX gouvernés par capability** (v0.18.6, étape 1.3) : carte
  `$section_caps` — `home`→stats_confirm **ou** stats_delivery (v0.19.0 ;
  l'entrée `stats` a disparu, `/gestion/stats` retombe sur la première section
  autorisée), `orders`→confirm, `tracking`→tracking,
  `shipping`→shipping_prices ; toute autre section = gérant uniquement. À
  l'intérieur de l'Accueil, chaque **onglet** revérifie sa propre cap
  (Revenus = `manage_woocommerce` seul). `visible_sections()` filtre la nav
  et le rendu ; section par défaut = première autorisée ; sans aucune cap →
  dashboard vide avec message propre. Les 35 handlers AJAX revérifient la
  capability côté serveur (403 sinon) — le masquage de l'UI n'est qu'un
  confort. La traçabilité « qui a confirmé » existe depuis la v0.18.4
  (étape 1.1) : métas `_aod_confirmed_by` (user ID) / `_aod_confirmed_at`
  (datetime, fuseau du site) posées à la **première** confirmation + note de
  commande nominative (jamais écrasées ensuite).
- **Affectation de produits** (v0.18.8, étape 1.5) : helper
  `AOD_CD_Roles::assigned_products( $user )` — IDs de la user meta
  `_aod_assigned_products` si `_aod_products_limited` est posée ; vide = pas de
  restriction ; le gérant n'est **jamais** filtré. La liste Commandes et ses
  compteurs sont filtrés **côté SQL** (`scoped_orders()` : jointure
  `DISTINCT` sur `wp_wc_order_product_lookup` — product_id **et** variation_id
  — armée le temps de la requête sur `woocommerce_orders_table_query_clauses`
  pour HPOS **et** `posts_clauses` pour le CPT hérité, les deux modes de
  stockage étant couverts). Défense en profondeur : `order_in_scope()` refuse
  (403) tout accès direct à une commande hors périmètre dans les 4 handlers
  commandes. Badge « Produits : n » dans l'en-tête du confirmateur restreint.
- **Assignation par commande (Phase 42, v0.62.0→0.68.0)** — méta
  `_aod_assigned_confirmer` (ID utilisateur, absente/`0` = non assignée),
  **cumulative** au périmètre produit ci-dessus (jamais substituée) : ce sont
  deux notions orthogonales (« quels produits » / « quelle commande précise »).
  Posée automatiquement à la création par deux chemins distincts —
  `AOD_CD_Section_Orders::assign_least_loaded()` sur `aod_cod_order_created`
  (formulaire public : tourniquet par **charge**, parmi le vivier
  `AOD_CD_Roles::eligible_confirmers()` — actifs, porteurs de
  `aod_cap_confirm_orders`, éligibles au produit) et
  `AOD_CD_Section_Abandoned::maybe_assign_converter()` dans
  `ajax_convert_lead()` (conversion d'un abandon, Phase 39 : assignée
  **directement** au confirmateur qui convertit, jamais au tourniquet — il a
  déjà pris possession du prospect). Vivier vide ⇒ aucune méta posée (commande
  non assignée, visible du seul gérant). Filtrage **côté SQL**, cumulatif à
  celui du périmètre produit : `scoped_orders()`/`order_in_scope()`
  (Commandes + Suivi, qui hérite via `AOD_CD_Section_Tracking`) et le jumeau
  `AOD_CD_Section_Home::assignment_scope()` (stats de l'Accueil — mécanisme
  **séparé** de `scoped_orders()`, threadé à `filtered_orders()`/
  `each_filtered_order()`/`count_status()`/`home_join_sql()`). Le filtre suit
  la **capability** `aod_cap_confirm_orders`, pas le rôle : un confirmateur
  « tracking/stock seul » n'a jamais de commande assignée, donc n'est jamais
  filtré. Le gérant n'est **jamais** filtré (assigne/réassigne tout via
  `aod_cd_order_assign`/`aod_cd_orders_bulk_assign` ci-dessous).
  🚨 **Exclusion volontaire** : `AOD_CD_Section_Home::confirmation_data()`
  (performance par confirmateur, `_aod_confirmed_by`) reste **non filtrée** —
  c'est un historique de qui a **réellement confirmé**, jamais réécrit ;
  `_aod_assigned_confirmer` est un état **présent**, réassignable. Filtrer la
  première par la seconde fausserait la vue agrégée du gérant sur une
  commande réassignée après confirmation. Cliquets
  `test-assignation-commandes.php` / `test-assignation-accueil.php`, porte
  `run.sh assignation`.

  🎯 **La restriction est donc AU RENDU — et jusqu'à la Phase 47.5, elle n'y
  était qu'à moitié.** Le tableau « Par confirmateur » restreignait bien à sa
  ligne ; les **trois cartes**, elles, retombaient dans la branche « toute la
  boutique » dès qu'aucun filtre d'écran n'était posé — or `read_filters()`
  force ce filtre à `0` pour tout non-gérant, tandis que `visible_tabs()` ouvre
  l'onglet à quiconque porte `aod_cap_stats_confirm`. Un confirmateur lisait
  donc **toujours** la performance de l'équipe, à trois lignes d'un code qui
  faisait déjà le bon geste. Depuis la 47.5, un seul `$row_uid` — le filtre
  d'écran du gérant, sinon l'utilisateur courant s'il n'est pas gérant, sinon
  `0` — gouverne **les cartes, la période précédente et le tableau** : ils ne
  peuvent plus diverger, et le delta ne peut plus comparer deux périmètres.
  ⚠️ Le déclencheur est **`! manage_woocommerce`**, jamais une capability : ne
  pas y recopier la garde d'`assignment_scope()` ci-dessus. Elle existe pour
  qu'un confirmateur « suivi/stock seul » ne voie pas ses tuiles se vider à
  tort ; ici l'attendu est l'inverse — un confirmateur qui n'a jamais confirmé
  doit lire **0**, ce qui est littéralement sa performance.

  **Qui voit quoi dans l'onglet Confirmation** (47.5, complété par la **Phase 48**) :

  | | Gérant | Gérant + `?confirmateur=X` | Confirmateur |
  |---|---|---|---|
  | Commandes reçues | boutique | boutique | **boutique**, et le libellé le **dit** (48.1) |
  | **Commandes assignées** (48.2) | — | **ligne de X** | **sa ligne** |
  | Confirmées (nb + %) | boutique | ligne de X | **sa ligne** |
  | Délai moyen | boutique | ligne de X | **sa ligne** |
  | Confirmées non attribuées | oui | — | — |
  | Tableau « Par confirmateur » | toutes | ligne de X | **sa ligne seule** |
  | Ligne « Non attribuées » | oui | — | — |
  | Colonne « Part » | ÷ total boutique | ÷ total boutique | **÷ total boutique** |

  🚨 **Pourquoi « Reçues » et « Part » restent au périmètre boutique, et
  pourquoi ce n'est pas un oubli.** Les scoper exigerait un « reçues par moi »,
  donc `_aod_assigned_confirmer` — un état **courant**, qui bouge à chaque
  réassignation — au dénominateur d'un numérateur **figé**
  (`_aod_confirmed_by`, contrat garanti ci-dessus). Une commande confirmée par A
  puis réassignée à B donnerait à A *confirmées 1 / reçues 0* : **division par
  zéro**, ou taux infini ; et à B *0 / 1* pour une commande qu'il n'a jamais
  vue. Deux horloges différentes sur la même carte. C'est la raison déjà écrite
  pour le filtre du gérant (« un taux 100 % mécanique serait trompeur »),
  appliquée à un second lecteur. → Conséquence assumée : **une carte sur trois
  montre encore la boutique**, et l'écran le **dit** — note conditionnelle
  `.aod-cd-note-scope-conf`, rendue au seul non-gérant, sans quoi
  « Confirmées : 3 (9 %) » se lirait « 91 % de mes commandes m'ont échappé ».
  Cliquet du rendu : `tests/unit/test-stats-confirmateur.php` (R0→R4, l'onglet
  **rendu** sous trois identités) ; porte : `run.sh confirmateur`, seule à
  ouvrir cet écran en vrai confirmateur.

  🚨 **Ces chiffres sont EN CACHE, et une assignation doit l'invalider (48.5).**
  Tout l'onglet sort de `confirmation_data()`, qui passe par `cached()`
  (transient, TTL 5 min). L'invalidation reposait sur quatre hooks — création,
  changement de statut, corbeille, suppression — dont **aucun ne voit une
  écriture de méta**. Or assigner une commande n'écrit qu'une méta : la carte
  « Commandes assignées » servait donc un agrégat périmé jusqu'à cinq minutes
  (mesuré : **15 en base, 14 à l'écran**). Le gérant assigne, le confirmateur
  regarde, et le seul chiffre de son écran qui parle de lui lui donne tort — un
  défaut **intermittent, muet et auto-cicatrisant**, celui qu'on déclare corrigé
  en vérifiant une minute trop tard.
  → L'invalidation est branchée sur **`woocommerce_update_order`**, émis par
  `$order->save()` dans les **deux** magasins (HPOS `OrdersTableDataStore` et CPT
  hérité). ⚠️ **Ne pas la déplacer sur les points d'écriture de
  `_aod_assigned_confirmer` : il y en a cinq** (assignation manuelle, lot,
  tourniquet `assign_least_loaded()`, conversion de prospect, dé-assignation) —
  une règle répartie sur cinq sites ne survit pas au sixième. Les quatre hooks
  historiques restent : création et suppressions ne passent pas par `update`.
  Gardé par **R6** du même cliquet, qui **chauffe** le cache, assigne par
  `apply_assignment()` et relit — jamais un `has_action()`.

  ✅ **Phase 48 — le raisonnement ci-dessus valait pour le RATIO, pas pour le
  COMPTEUR.** Il est conservé intact (le taux et « Part » gardent leur
  dénominateur boutique) ; ce qu'il ne couvrait pas, c'est qu'un **compteur**
  « assignées à moi » n'entre dans aucune division et ne mélange donc aucune
  horloge. Constat owner du 2026-08-02 : un confirmateur à qui **12** commandes
  étaient assignées lisait « Commandes reçues : **33** » — un chiffre exact, sous
  un libellé muet, dans un écran où **tout le reste de son `/gestion`** est
  restreint à son assignation (Commandes, Suivi, Abandonnées, onglets
  Revenus/Livraison via `assignment_scope()`). *La cohérence d'un écran ne se juge
  pas carte par carte, mais contre le reste du produit.*
  Livré : **48.1** le libellé nomme la boutique pour le seul lecteur qui peut s'y
  tromper (le gérant garde exactement sa chaîne) et la note remonte **sous les
  cartes**, là où naît le contresens — elle était rendue en fin de page, donc hors
  du premier écran sur mobile ; **48.2** une 4ᵉ carte **« Commandes assignées »**,
  gouvernée par le **même `$row_uid`** que les trois autres (jamais un quatrième
  critère : ce serait rouvrir la divergence que la 47.5 a fermée), donc visible du
  confirmateur **et** du gérant qui filtre `?confirmateur=X`.
  🚨 **Deux pièges qui ne se voient pas en relecture.** (1) Le comptage doit être
  **avant** le `return` de la closure de `confirmation_data()` : la branche `! $by`
  sort tôt, et compté après, une commande **assignée mais pas encore confirmée** —
  le cas le plus courant du chiffre affiché — ne serait jamais vue. (2) La
  ventilation vit dans le payload **en cache** sous forme `uid => n`, comme `rows` :
  la clé n'intègre ni le rôle ni le lecteur, à dessein — y stocker un total « le
  mien » imposerait une clé par utilisateur, et un oubli ferait servir à deux
  confirmateurs la vue l'un de l'autre. La clé est renommée `confirmation2` : la
  forme du payload ayant changé, une entrée encore en cache (TTL 5 min) aurait
  servi un **0 faux**.
  ⚠️ **Une tonalité inconnue ne lève aucune erreur** — `violet` n'existe pas dans
  le catalogue fermé `.t-*`, la carte serait sortie en neutre sans un mot (le
  fantôme de la Phase 17). Corrigée en `indigo` avant livraison.
  Cliquet : **R5** du même fichier (décor **croisant** assignation et
  confirmation — égaux, les deux nombres ne prouveraient rien) ; porte :
  `run.sh confirmateur`, passée de 44 à 50 assertions.

- **Filtres de lecture (Phase 43.1/43.2, v0.69.0→0.70.0)** — formulaires GET sans
  JS. Date : `date_from`/`date_to` sur Commandes, Suivi et Abandonnées, via quatre
  helpers **partagés** promus dans la coque (`parse_date_param()`,
  `resolve_date_range()`, `date_created_query_arg()`, `render_date_fields()`).
  ⚠️ Suivi filtre sur `date_created` comme Commandes — **il n'existe aucune date
  d'expédition** dans le modèle (`_aod_ship_status_at` est la dernière mise à jour
  de suivi). Confirmateur : `<select>` du vivier `eligible_confirmers()`,
  **gérant uniquement**, sur `_aod_assigned_confirmer` (état présent) et jamais
  `_aod_confirmed_by` (historique). Confiance zéro sur le GET : un ID hors vivier
  est **ignoré** (lecture), là où une écriture le **refuserait**.
- **Traçabilité de l'assignation (Phase 43.4, v0.72.0)** — méta
  **`_aod_assigned_by`** : ID de l'utilisateur qui a posé l'assignation,
  **absente = tourniquet automatique** (jamais `0` — même invariant que
  `_aod_assigned_confirmer`). Posée sur les **quatre** chemins manuels
  (`apply_assignment()`, `bulk_assign()`, `maybe_assign_converter()`, report
  depuis un prospect) et **effacée** par la dé-assignation — sans quoi
  `unassign_confirmer()` (42.7) laisserait une signature orpheline sur une
  commande que le tourniquet reprend ensuite. Rendu par
  `AOD_CD_Roles::confirmer_label_html( $id, $assigned_by = 0 )` : fragment
  **littéral « Admin »** si l'assignant porte `manage_woocommerce`, jamais son
  `display_name` (A.20, Constat 3). Un compte supprimé **ou rétrogradé** fait
  disparaître le fragment — dégradation assumée.
- **Écrasement groupé explicite (Phase 43.5, v0.73.0)** — `bulk_assign()` gagne
  `$overwrite` et un 4ᵉ compteur `reassigned`, **toujours vide** sans le drapeau
  (la forme même de la non-régression 42.5). Case à cocher **décochée à chaque
  rendu et réarmée dès que la sélection se vide**. `overwrite` lu en liste
  blanche (`'1' ===`). Une commande déjà assignée **à la cible** est sautée dans
  les deux modes : la réassigner réécrirait `_aod_assigned_by` et ferait passer
  le tourniquet pour le gérant.
- **Montant estimé des prospects (Phase 43.6, v0.74.0 · cod-form v1.51.0)** —
  `AOD_CD_Section_Abandoned::lead_amount_html()` : `AOD_COD_Form::estimate_lines()`
  **+** `AOD_COD_Data::effective_shipping()`, les deux appels de production, zéro
  formule recopiée. ⚠️ `estimate_lines()` ne prend **ni wilaya ni type de
  livraison** — il en faut bien deux. Elle gagne un **mode tolérant opt-in**
  (`$tolerant`) : un prospect ne mémorise aucun choix de variante, donc sans lui
  la colonne serait un tiret sur tout produit à variantes ; le montant est alors
  marqué **« ≈ »**. Le chemin d'achat et le panier public restent stricts.
  **Trois replis en tiret** : produit indisponible, wilaya absente, **type de
  livraison inconnu** — ce dernier parce que `AOD_COD_Data::price_for()`
  normalise EN SILENCE tout type inconnu en `'home'`.
- **Barre latérale (Phases 43.7/43.8, v0.75.0)** — `overflow-y:auto` + barre fine
  teintée (elle débordait en silence sous `height:100vh`). Sur **desktop
  uniquement**, rail de `--cd-rail` (64 px) déplié à `--cd-side-w` (248 px) au
  survol **et** au `:focus-within`, par une **seule** déclaration. La barre passe
  en `position:fixed` et le contenu prend `margin-inline-start` : elle se
  superpose au lieu de pousser — sinon la géométrie de la page dépendrait de la
  souris, et les portes qui mesurent des positions deviendraient non
  reproductibles. 🚨 Les libellés s'effacent en **`opacity`**, jamais
  `display:none`/`visibility:hidden` : ces deux-là sortiraient les liens de
  l'ordre de tabulation et rendraient `:focus-within` inopérant. Gardé par
  `tests/unit/test-sidebar-rail.php`. Mobile et hamburger `.is-open` intacts.

### AJAX (35 handlers, tous nonce `aod_cd` + capability, aucun `nopriv`)

> 🔁 Ce compteur a annoncé **34** jusqu'au 2026-08-01, alors que le code en portait
> **36** (47.1 en avait ajouté deux, 47.2 un troisième) — et il en porte **35**
> depuis que la 47.3 a supprimé `order_note`. Il se recompte, il ne s'estime pas :
> `grep -c "wp_ajax_aod_cd_" plugins-dev/aod-client-dashboard/includes/*.php`.
> Le recensement qui fait foi, lui, est exécutable : `aod_test_perm_contrat()`
> dans `tests/unit/test-permissions-sections.php`, dont une règle vérifie depuis
> la 47.3 qu'aucune entrée ne survit à son handler.

Depuis la v0.18.6 (étape 1.3), les handlers ouverts au confirmateur passent
par `AOD_CD_Roles::user_can()` : `order_status`/`order_detail`/
`order_save_info` → `aod_cap_confirm_orders` (+ garde `order_in_scope()` 403
si la commande est hors du périmètre produits du confirmateur, v0.18.8) ;
`tracking_refresh`/`tracking_detail` → `aod_cap_tracking` ;
`save_shipping`/`import_fees`/`test_carrier` → `aod_cap_shipping_prices`
(les **trois** de la section Livraison, `test_carrier` les ayant rejointes en
**Phase 21**). Tous les autres exigent le gérant (`manage_woocommerce` ou caps
Woo — `save_account` inclus, resserré en 0.18.6).

**Trois divergences confirmateur sont VOULUES** et ne doivent pas être
« alignées » : `delete_orders` exige `delete_shop_orders`/`manage_woocommerce`
dans une section gouvernée par `aod_cap_confirm_orders` (supprimer est plus fort
que confirmer), `save_stock` exige `edit_products` dans une section gouvernée par
`aod_cap_stock` (le confirmateur « stock » a l'écran en **lecture seule**), et
`convert_lead` (39.1) exige `aod_cap_confirm_orders` dans la section
*Commandes abandonnées* gouvernée par `aod_cap_abandoned` (CRÉER une commande à
partir d'un abandon est un acte sur les commandes, pas la lecture de la liste —
l'aligner sur la cap de section serait un **TROU**).

> ⚠️ **Ce paragraphe a été faux jusqu'au 2026-07-21** : il affirmait
> « `save_shipping` → `aod_cap_shipping_prices`. **Tous les autres** exigent le
> gérant », alors qu'`import_fees` passait par `aod_cap_shipping_prices` depuis la
> Phase 4. La carte des permissions du document qu'on lit **avant tout chantier sur
> les plugins** était donc inexacte — un `TEMPLATE.md` périmé, un cran plus bas.
> C'est pourquoi la source de vérité est désormais **exécutable** :
> `tests/unit/test-permissions-sections.php` tient la table handler → capability et
> **échoue** si le code s'en écarte, dans un sens (**MUR**) comme dans l'autre
> (**TROU**). Cette prose la commente ; elle ne la remplace pas.

> 🔒 **Pourquoi `save_account` est réservé au gérant — décision d'owner, arbitrée
> le 2026-07-21 : « le gérant gère les mots de passe ».** Un confirmateur n'a donc
> pas de section « Mon compte » (`visible_sections():209` : une section absente de
> `$section_caps` est manager-only), et le gérant change son mot de passe depuis
> **Équipe**.
>
> ⚠️ **Cette garde paraît inutile si l'on ne lit que le handler** : il est borné à
> `$user->ID` (nom, e-mail, mot de passe — la remise à zéro est un handler séparé),
> donc il « ne protège rien ». C'est faux : elle est la **seconde moitié** d'une
> décision prise dans le routeur. Elle a déjà été prise pour un oubli une fois, et
> l'assouplir aurait de surcroît **verrouillé l'administrateur**, qui ne possède pas
> `AOD_CD_CAP` et entre par le **OU** de `:306`. Ne pas la « corriger ».

Commandes : `aod_cd_order_status` (pose la traçabilité `_aod_confirmed_by/_at` à
la première confirmation ; ⚠️ **il ne déclenche AUCUN envoi** — cette phrase a
annoncé « déclenche l'envoi auto sur Confirmée » jusqu'au 2026-08-01, alors que
la 47.2 avait retiré le hook),
`aod_cd_order_detail`, `aod_cd_order_save_info` (**qui porte aussi la note au
livreur depuis la 47.3** — cf. plus bas), `aod_cd_delete_orders`,
**`aod_cd_order_assign`** (Phase 42.4 — réassigne une
commande à un confirmateur ou « Non assignée » ; `manage_woocommerce` strict,
famille **action-forte**, jamais `aod_cap_confirm_orders` — sinon un
confirmateur pourrait se réassigner des commandes d'un pair), **`aod_cd_orders_bulk_assign`**
(Phase 42.5 — assignation groupée sur une sélection d'IDs, même famille ;
saute silencieusement toute commande déjà assignée du lot). Produits/catégories : `aod_cd_save_product`,
`aod_cd_delete_product`, `aod_cd_save_category`, `aod_cd_delete_category`,
`aod_cd_desc_image` (8.2 — **premier upload IMMÉDIAT** du plugin : une image
insérée au fil de la description a besoin de son URL avant le save ; whitelist de
mimes serveur + `test_form => false` re-spécifié dans `$overrides`),
`aod_cd_desc_video` (8.3 — jumeau vidéo : upload immédiat d'un MP4/WebM/Ogg → un
`<video src>` natif inséré dans la description ; mêmes gardes, whitelist de mimes
vidéo).
Livraison/suivi : `aod_cd_save_shipping`, `aod_cd_test_carrier`,
`aod_cd_import_fees` (Phase 4 — import des tarifs depuis l'API transporteur),
`aod_cd_tracking_refresh`, `aod_cd_tracking_detail`.
Page de vente (classe `AOD_CD_Section_Landing`) : `aod_cd_save_landing`,
`aod_cd_copy_landing` (copie inter-produits). Réglages :
`aod_cd_save_pixels`, `aod_cd_test_capi` (Phase 10 — teste la CAPI Meta,
délègue à `AOD_COD_Pixels::test_capi_connection()`), `aod_cd_save_whatsapp`, `aod_cd_test_whatsapp`,
`aod_cd_save_account`, `aod_cd_reset_shop`. Équipe (classe
`AOD_CD_Section_Team`) : `aod_cd_save_member` (create/edit/toggle_active),
`aod_cd_delete_member` — gérant uniquement, la cible doit porter le rôle
`aod_confirmateur` (un gérant/admin est intouchable même par POST forgé).
Stock (classe `AOD_CD_Section_Stock`) : `aod_cd_save_stock` (quantité,
seuil, note d'inventaire) — nonce + `edit_products` + `edit_product` par ID
+ périmètre `assigned_products` de l'appelant.
Commandes abandonnées (classe `AOD_CD_Section_Abandoned`) : `aod_cd_convert_lead`
(39.1 — convertit un prospect `pending` en **squelette** de commande `pending`,
puis `mark_converted()`) — nonce + `aod_cap_confirm_orders` (divergence voulue,
plus forte que la cap de section `aod_cap_abandoned`) + périmètre
`assigned_products` + anti-doublon (verrou `add_option` **atomique** — jamais un
transient — puis relecture sur instance fraîche, patron 31.4). Depuis la Phase 42.2,
pose aussi `_aod_assigned_confirmer` = le convertisseur via `maybe_assign_converter()`
**si et seulement si** c'est un confirmateur (le gérant qui convertit ne s'auto-assigne
pas). N'écrit AUCUN autre champ de commande hors squelette (frais, statut de
confirmation, envoi : chemin d'édition existant, étape 39.2). C'est le seul hook qui **crée** une commande côté
`/gestion` ; la section est donc instanciée au boot (comme Orders/Products) pour
l'enregistrer, alors que son rendu reste paresseux.

Hooks métier hors AJAX, portés par `AOD_CD_Section_Orders` (cycle de vie,
armés au boot, agissent quel que soit l'écran d'où vient le changement de
statut) :

- `reduce_pack_components_stock` (statuts processing/completed) — décrémente
  le stock des composants d'un pack (drapeau `_aod_pack_stock_done`). Depuis la 30.6.
- **Stock par variante (Phase 40)** — `reduce_variant_stock` (statut
  `aod-confirmed`, + `completed` défensif) décrémente le `stock` de la valeur
  d'option choisie ; `restore_variant_stock` (`cancelled`/`refunded`) le
  ré-incrémente. **Mirroir du pack** : drapeau d'idempotence
  `_aod_variant_stock_reduced` **sur la commande**, helper unique
  `apply_variant_stock_delta($order,$sign)` (décrément borné à 0). Décrément
  **à la confirmation**, jamais à la création (COD : un abandon ne vide pas le
  stock). Cf. §7 pt 3 et le modèle `_aod_options.stock`.

### Les TROIS fenêtres de `/gestion` — et pourquoi les confondre est le prochain défaut

Depuis la 47.7 la coque (`dashboard.js`) en expose trois. Elles se ressemblent à
l'écran et ne se remplacent pas :

| Primitive | Ce qu'elle est | Qui s'en sert |
|---|---|---|
| **`openOverlayModal()`** (`:222`) | Le **scaffold** : crée un overlay **jetable** à chaque appel (fond `[data-cancel]`, piège de focus, `Échap`). Ne décide de rien d'autre. | La fenêtre vidéo (8.3), la fenêtre d'envoi (47.2), et `confirmModal` — qui est bâtie **dessus** |
| **`sharedModal()`** (47.4) | La modale **unique et permanente** `#aod-cd-modal`, imprimée en pied de page (`[data-close]`), avec **un seul propriétaire**. Politique du fond **en paramètre** (`backdropCloses`). | Le détail d'une commande (le fond ne ferme pas : on y saisit) et la timeline de Suivi (il ferme) |
| **`confirmModal()`** (47.7) | La **question** : rend une **Promesse**. Remplace les 13 dialogues natifs. `okOnly` en fait un **avis** (l'ex-`window.alert`), `detail` rend une valeur **copiable**. | Les 7 modules de section, 13 sites |

🚨 **`confirmModal()` rend une PROMESSE, et c'est le piège à ne jamais rouvrir.**
`window.confirm` était **bloquant** et rendait un booléen. Écrire
`if ( ! confirmModal( … ) ) { return; }` **ne se déclenche jamais** — une Promise
est toujours *truthy*. Un remplacement mécanique aurait produit **13 gardes
mortes**, c'est-à-dire treize suppressions sans confirmation, sans qu'aucune
erreur ne le dise. Chaque appelant est en continuation
(`.then( function ( ok ) { … } )`), et quand la question est **conditionnelle**
(deux sites), c'est une `Promise.resolve( true )` qui garde **un seul** chemin de
code plutôt qu'un `if` dupliquant la suite.

⚠️ **Deux d'entre elles cohabitent dans le même DOM.** `#aod-cd-modal` est
**permanente** : masquée, mais présente. Un sélecteur `.aod-cd-modal` sans
`:not(#aod-cd-modal)` la compte donc toujours — c'est ce qui a rendu deux
assertions de la porte `clavier` vertes **par accident** en 47.7 (« la fenêtre
s'ouvre » passait sans qu'aucune fenêtre ne s'ouvre). Cliquet :
`tests/unit/test-dialogues-maison.php` ; porte : `run.sh clavier`, bloc 47.7 —
seule à prouver qu'**Annuler ne déclenche aucune requête**.

### Téléversements — un seul goulot (`AOD_CD_Upload`, Phases 32.2 et 47.6)

**Les SIX téléversements du dépôt passent par `AOD_CD_Upload::handle()`**, et
c'est tout l'intérêt : quatre dans `class-aod-cd-section-products.php` (photo
principale, galerie, photos de valeurs d'options, vignette de catégorie) et deux
dans `class-aod-cd-section-landing.php` (`upload_one()`, images et vidéos des
blocs). Tous sont derrière `check_ajax_referer( 'aod_cd' )` **et**
`current_user_can( 'edit_products' )` : ni acheteur, ni confirmateur n'y accède.

Le goulot porte **deux** garanties, et l'une n'existe que depuis la 47.6 :

1. **La liste blanche MIME explicite** (32.2). `get_allowed_mime_types()` est
   *filtrable* : le jour où un clone installe un plugin qui autorise le SVG, les
   six champs deviendraient une **XSS stockée** sans qu'une ligne du dépôt ait
   changé. Une liste écrite ne bouge pas quand l'environnement bouge.
2. **La borne de poids** (47.6, décision D6). 🚨 **Elle n'est pas `MAX_BYTES`** :
   `max_bytes()` rend **`min( 5 Mo, wp_max_upload_size() )`**. Un hébergeur
   mutualisé plafonne souvent plus bas, et annoncer 5 Mo là où PHP en accepte 2
   serait un **mensonge d'écran**, la panne la plus coûteuse de ce dépôt parce
   qu'elle ne se voit pas. La limite affichée et la limite qui refuse sont donc
   la **même fonction**.
   ⚠️ Et elle traite **deux** façons d'être trop lourd : la nôtre (`size`), et
   celle de PHP (`UPLOAD_ERR_INI_SIZE` / `UPLOAD_ERR_FORM_SIZE`), **où `size`
   vaut 0**. Un contrôle écrit sur la seule taille serait vert au test et
   **inerte en production** partout où l'hébergeur plafonne sous notre borne.

🚨 **Un refus ne vaut que s'il est DIT — et deux sites l'avalaient.** Avant la
47.6, `save_gallery()` et `upload_option_images()` testaient `! is_wp_error()`
**sans `else`** : une image refusée disparaissait sans un mot, sur les deux
champs où l'on dépose *plusieurs* photos à la fois. Ils comptent désormais leurs
refus, qui remontent dans le `warning` de `save_product()` via
`build_warnings()` — lequel **joint** les messages au lieu de les écraser, parce
qu'offres ignorées et images refusées peuvent survenir dans le même
enregistrement.

🚨 **Et le plafond de PHP, lui, appartient au TEMPLATE — pas au client (47.6bis).**
Avant cette étape, `php -i` répondait `Loaded Configuration File => (none)` :
**aucun `php.ini` n'était chargé**, et `upload_max_filesize` valait le défaut
compilé de PHP, **2M**. Une photo de téléphone ordinaire était donc refusée
pendant que l'écran promettait 5 Mo — annoncé honnêtement, mais inatteignable.
Depuis, **`docker/php-aod.ini`** (versionné) pose `upload_max_filesize = 8M` et
`post_max_size = 64M`.

| | Pourquoi |
|---|---|
| `upload_max_filesize` **8M** | marge **au-dessus** de la borne applicative, pour que ce soit **notre** message qui refuse à 5 Mo — jamais un `size = 0` muet de PHP |
| `post_max_size` **64M** | `wp_max_upload_size()` rend le **MIN** des deux : un `post_max_size` bas plafonne même un fichier unique, et la galerie poste plusieurs photos dans **un seul** POST |
| `memory_limit` | **rien** — WordPress le monte à 512M au boot côté web (`WP_MEMORY_LIMIT`) ; pic GD mesuré 162/179/209 Mo pour 5,1/7,7/13,2 Mpx |
| `max_file_uploads`, `max_execution_time` | **rien** — 20 n'est jamais atteint (`post_max_size` borne à 8 fichiers), et le redimensionnement le plus lourd prend 0,34 s pour 30 s disponibles |

⚠️ **Le fichier est monté sur les DEUX conteneurs**, `wordpress` **et** `wpcli`,
et ce n'est pas une précaution : `bash tests/run.sh` s'exécute dans `wpcli`
tandis que les vrais téléversements arrivent dans `wordpress`. Ne le monter que
sur l'un rendrait l'autre menteur — c'est la leçon `FS_METHOD` (dette D28) sous
un autre visage. En production, `setup.sh` écrit un **`.user.ini`** (PHP-FPM /
CGI) ; **rien n'est écrit dans `.htaccess`** — un `php_value` sans `mod_php` est
une erreur 500 sur tout le site, et le bloc `# BEGIN WordPress` est régénéré.

L'annonce (`AOD_CD_Upload::limit_note()`, classe `.aod-cd-help`) est rendue aux
**sept** zones de dépôt depuis **un seul corps** : sept copies auraient donné
sept chaînes à traduire et sept chances de diverger de la borne réelle. Le
contrôle JS (`CD.maxUpload`) est un **confort** — il évite d'attendre un
téléversement condamné ; il ne protège rien, et se contourne en postant
directement, comme les `accept="image/*"`.

Cliquet : `tests/unit/test-upload-borne.php` (R0→R8), qui **appelle** `handle()`.
⚠️ Il ne peut pas mener un téléversement de bout en bout : `_wp_handle_upload()`
exige `is_uploaded_file()`, hors d'atteinte sous WP-CLI — et ce contrôle passe
**avant** la vérification du type, si bien qu'une assertion « un `.txt` est
refusé » serait verte **avec la liste blanche entièrement supprimée**. La
contre-épreuve porte donc sur le **code d'erreur** et sur le **recensement** de
la liste blanche. Porte : `run.sh riche` (l'annonce, et qu'écran et borne JS
disent le même chiffre).

### Couche UI & accessibilité (Phase 5, v0.22.0→0.23.0)

**Un seul point de personnalisation** : `class-aod-cd-customize.php` (362 l.)
expose au Personnalisateur l'**accent** (`theme_mod` `aod_cd_accent`, défaut
`#FFE21E`) et en **dérive** tout le reste en variables CSS (`css_vars()`).
Le client choisit une couleur ; il ne choisit jamais une encre, une teinte ni
une bordure.

Trois tokens portent une garantie de contraste, et les confondre casse
l'accessibilité :

| Token | Rôle | Piège |
|---|---|---|
| `--cd-accent-ink` | encre posée **sur** l'accent | `best_ink()` choisit entre **noir pur** et blanc. Pas `#181818` : avec notre encre habituelle, le meilleur des deux tombe à **4,21:1** pour un bleu de marque banal (`#1878f0`) — sous AA. Le noir pur remonte le plancher du cube RGB entier à 4,58:1. |
| `--cd-accent-on-dark` | accent utilisé **en texte** sur nos surfaces sombres | `readable_on()` éclaircit l'accent jusqu'à 4,6:1. ⚠️ **Depuis la Phase 15, ces surfaces sont DEUX** : la sidebar (teintée, dérivée) et le neutre `#181818` (pastille de langue, pagination). Une seule variable les sert : elle est calibrée sur la **plus claire des deux**. Ne pas la recâbler sur « la sidebar » — 72 accents sur 4 096 donnent une sidebar *plus sombre* que le neutre. |
| `--cd-side` (Phase 15) | fond de la sidebar, **dérivé** de l'accent | `side()` : 15 % d'accent dans `#181818`, **puis un plafond de luminance** (`SIDE_MAX_LUM`). Le plafond n'est pas cosmétique : sans lui, le mélange transmet la *clarté* de l'accent autant que sa teinte, et le jaune par défaut sortait à **4,0×** la luminance du neutre (kaki) quand tous les autres tenaient sous 2,4×. Les 4 096 assertions étaient vertes — c'est la capture d'écran qui l'a montré. |
| `--cd-muted` (`#666`) / `--cd-soft` (`#8A8A8A`) | gris de texte / gris de **décor** | Il n'existe que **deux** gris de texte : le seuil AA tenu sur le pire de nos fonds (badges `#EDEDED`, teintes d'alerte) plafonne à `#666`. `--cd-soft` est **décor uniquement** (bordures, chevrons) — jamais du texte. |
| `--cd-focus` | anneau de focus, **deux couches** | Liseré `--cd-ink` (toujours contrasté) **puis** halo d'accent. 🚨 Un anneau bâti sur le seul accent est invisible dès que le client en choisit un pâle — et le défaut du template EST un jaune pâle : quatre contrôles (`statussel`, `assignsel`, `leadassignsel`, `seg__btn`) écrivaient `outline:2px solid var(--cd-yellow|--cd-accent)`, soit **1,15:1**, alors que la règle inverse était écrite en tête du même fichier. Supprimés en 46.1 ; c'est le socle global (`.aod-cd :focus-visible`, en encre) qui gouverne. Gardé des DEUX côtés : R1 de `test-chrome-conformite.php` tient la règle sur tous les accents à la fois, la porte `contraste` mesure l'anneau **calculé** (463 contrôles focalisés au clavier × 6 accents). |

**Les échelles de tokens sont désormais tenues par des cliquets** (Phase 46) — et
elles ne l'étaient pas : le problème n°1 du diagnostic de la Phase 5 (« ~10 tailles
de police en dur ») était monté à **20 tailles** huit phases plus tard, avec 13 %
seulement des `font-size` passant par un cran. Deux règles closent ce mode de
dérive, toutes deux dans `test-charte-surfaces.php` :

- **R4 — tout token déclaré a un lecteur.** Le cliquet ne regardait que
  usage → déclaration (les « fantômes » de 17.4) et jamais l'inverse. Un token
  orphelin n'est pas un octet mort : c'est une échelle qui *a l'air* appliquée. Deux
  catégories sont admises, aucune écrite en dur — les tokens que `css_vars()`
  **injecte** (la fonction est interrogée, pas recopiée) et, temporairement, ceux
  qu'une étape planifiée doit appliquer. Suppressions qui en découlent :
  `--cd-radius`/`--cd-radius-sm` (doublons exacts de `--cd-r-md`/`--cd-r-sm`),
  `--cd-r-lg`, `--cd-yellow-d`, `--cd-sp-8`.
- **R5 — le nombre de valeurs littérales ne remonte pas.** On ne garde pas
  « l'échelle est appliquée » (invérifiable : une valeur hors échelle peut être
  légitime), mais un plafond décroissant — même forme que le cliquet de
  décomposition. 43 correspondances exactes ont été converties, **prouvées
  pixel-identiques** par empreinte des styles calculés (7 003 éléments, SHA
  inchangé) ; les 110 restantes sont un arbitrage de design (étape 46.5bis).

⚠️ **Le toast est une région live PERMANENTE** (46.3), pas un nœud créé à chaque
message : un lecteur d'écran n'annonce que les **mutations** d'une région déjà
présente dans l'arbre. `toast()` ne fabrique donc plus aucun nœud — c'est cette
absence que R3 vérifie, et non la présence de `role="status"`, qui serait verte sur
le défaut exact corrigé. Conséquence à ne pas défaire : la boîte vivant en continu
en `position:fixed`, elle porte `pointer-events:none`, sans quoi elle intercepterait
les clics de la zone qu'elle recouvre.

**Les icônes : `class-aod-cd-icon.php` (Phase 15).** Tout le chrome de `/gestion`
était en **emojis Unicode**, donc rendu par la police emoji du système : pas le
même visage sur Windows, macOS et Android, aucun contrôle sur le trait ni la
couleur. Ils sont remplacés par des SVG Phosphor (MIT) **versionnés dans le
plugin** — aucun CDN, aucun appel réseau au rendu, un clone sans réseau
fonctionne.

> ⚠️ **Cette phrase n'a longtemps valu que pour les icônes, pas pour la coque qui
> les rend.** La même page chargeait **Barlow depuis Google Fonts**
> (`class-aod-cd-dashboard.php:385-387`) : « un clone sans réseau fonctionne »
> était vrai des SVG et faux de la typographie, qui perdait sa signature en
> silence. ✅ **Corrigé le 2026-07-21 (Phase 20)** — les `woff2` sont embarqués
> dans `assets/fonts/`, la phrase vaut désormais pour **toute** la coque, et
> `tests/unit/test-ressources-locales.php` la garde. Cf. **dette 18** du §7.

`AOD_CD_Icon` est un **catalogue fermé** (58 noms → 57 fichiers `regular`, +14 `fill` pour l’état actif), même patron que
`AOD_COD_Landing::schema()`, et pour la même raison : **rien de ce qui est rendu
ne vient d'une saisie**. C'est ce qui permet d'injecter du SVG sans surface XSS
là où `esc_html()` le détruirait et où `wp_kses_post()` le supprimerait (cf.
dette 14 ci-dessous). Un nom hors catalogue ne rend **rien** et déclenche un
`_doing_it_wrong()` — jamais un repli silencieux.

Deux points à ne pas défaire :

- **L'état actif de la nav se joue sur la GRAISSE** (`regular` → `fill`), pas sur
  une couleur : un signal qui tient quel que soit l'accent du client, là où une
  pastille colorée dépend entièrement de la teinte retenue.
- **`directional()` est volontairement court** (`shipping`, `returned`). Miroiter
  toute la famille en RTL casse le sens : `trend-up` est une courbe qui monte de
  gauche à droite — retournée, elle **descend**, et l'indicateur « Marge estimée »
  dirait l'inverse de sa valeur, **en arabe seulement**. Un test porte la liste
  noire.

Côté `aod-cod-form`, `AOD_Carrier::brand_ink()` fait le même calcul pour le
monogramme d'un transporteur : chaque transporteur déclare **sa** couleur de
marque (et les 23 white-labels EcoTrack en déclarent 23 autres, hors de notre
contrôle) — un monogramme blanc en dur tombait à 2,15:1 sur l'ambre.

**Socle a11y partagé** (`dashboard.js`, en tête de fichier — à réutiliser, ne pas
réinventer ; exposé aux modules `section-*.js` via `window.aodCdCore`, Phase 33) :

- `trapFocus( box, initial )` → `release()` : vrai piège de focus.
  `aria-modal="true"` **ne piège rien** — c'est une promesse faite aux lecteurs
  d'écran, pas un comportement du navigateur. `release()` rend le focus au
  déclencheur.
- `bindListboxKeys( o )` : clavier des dropdowns, modèle **combobox** — le focus
  ne quitte jamais le bouton, l'option visée est désignée par
  `aria-activedescendant` (obligatoire : le menu du sélecteur de transporteur est
  attaché au `<body>`, donc hors du flux de tabulation de son bouton). Le bouton
  doit porter `role="combobox"` — `aria-activedescendant` est invalide sur un
  `<button>` nu. Ne **jamais** écraser `aria-selected` avec le curseur clavier :
  `aria-selected` veut dire « choisi », pas « survolé ».
- `bindFloatingMenu( btn, menu, … )` : un menu flottant se **recolle** au bouton
  quand la page défile — il ne se referme pas. Le refermer au `scroll` cassait le
  **premier clic** (donner le focus à un bouton partiellement visible fait défiler
  la page, et l'évènement `scroll` arrive *après* le `click`).
- Un menu ne prend **jamais** le focus au `mousedown` (comme un `<select>` natif) :
  sinon le `blur` du bouton referme le menu avant que le `click` de l'option ne
  parte, et la sélection à la souris ne marche plus du tout.

**Les DEUX modales du tableau de bord — à ne pas confondre** (Phase 47.4) :

| | `openOverlayModal( opts )` | `sharedModal()` |
|---|---|---|
| Élément | **créé à la volée**, jeté à la fermeture | `#aod-cd-modal`, imprimé **une fois** en pied de page par `AOD_CD_Dashboard` |
| Marqueur de fermeture | `[data-cancel]` | `[data-close]` |
| Qui l'ouvre | `pickVideo`, et tout appelant ponctuel | le **détail d'une commande** (`section-orders.js`) **et** la **timeline de Suivi** (`section-tracking.js`) |
| Politique du fond | ferme (surchargeable par `onCancel`) | **paramètre d'ouverture** : `open( { backdropCloses } )` |

`sharedModal()` est un **singleton** : il a **un seul propriétaire**, la coque.
C'est une correction de la 47.4, et elle a une raison précise. `bindOrderDetail`
et `bindTracking` posaient **chacun** leur `open`/`close` et leur écouteur `Échap`
sur le **même** élément, avec des corps quasi identiques — et `dashboard_scripts()`
charge **tous** les modules sur **toutes** les pages. Sur `/gestion/tracking`, il y
avait donc deux fermetures et deux `Échap` pour une seule modale. Y ajouter la
politique du fond en double aurait été la dérive que la Phase 46 a passé huit
étapes à corriger.

🚨 **La politique du fond est interrogée AU CLIC, par délégation — jamais portée
par l'attribut, et c'est mesuré.** Retirer `data-close` du fond **après** le
démarrage ne change **rien** : l'écouteur a été posé sur l'**élément**, par
référence ; l'attribut n'a servi qu'à le trouver une fois. La délégation règle du
même coup la leçon 47.2 — un écouteur posé sur un nœud **meurt avec le nœud** dès
qu'un `innerHTML` le remplace, et le bouton reste *cliquable et inerte*, le pire
des états. `data-close` **reste** donc dans le markup partagé : la timeline en
dépend, et c'est elle qui garde le fond **armé**.

Ces règles sont vérifiées automatiquement : **`tools/recette/`** (§5) — bloc 8
d'`a11y-clavier.js` (fond désarmé, `Échap`, croix, focus piégé **et rendu**, garde
qui survit à un re-rendu du corps) et `commandes-suivi.js` (les **deux** politiques
éprouvées sur `/gestion/tracking`, seule page où les deux consommateurs
coexistent). Côté structure : `tests/unit/test-modale-partagee.php` R1 tient le
propriétaire unique par **recensement** de tous les `assets/js/*.js`.

---

## 4. mu-plugin — numérotation séquentielle

`wordpress/wp-content/mu-plugins/aod-sequential-order-numbers.php` : numéro de
commande séquentiel à partir de 1 (option `aod_seq_order_counter`, méta
`_aod_seq_number`), HPOS-compatible, branché sur `aod_cod_order_created` avec
filets pour le checkout classique/blocks. Le reset boutique remet le compteur à 0.

---

## 5. Infrastructure

- **Docker** (`docker-compose.yml`) : `db` (MariaDB 11 + healthcheck),
  `wordpress` (php8.2-apache, WP_DEBUG_LOG), `wpcli`, `phpmyadmin`. Conteneurs
  nommés `wp_<service>_${PROJECT_SLUG}`. Bloc `dns: [8.8.8.8, 1.1.1.1]` sur
  wordpress/wpcli (indispensable pour les API transporteurs). Bind-mounts :
  `./wordpress`, `./library` (ro), `./scripts`, et les **2 plugins maison**.
- **`scripts/preflight.sh`** : garde-fou d'isolation (slug/ports/conteneurs) —
  à lancer AVANT `docker compose up`.
- **`scripts/setup.sh`** : install WP idempotente + réglages Algérie/DZD/COD +
  installation auto des `.zip` de `library/`.
- **`tools/recette/`** : recette navigateur de `/gestion` (playwright-core, sort
  en erreur si ça casse) — contraste AA, noms accessibles, clavier, **souris**,
  RTL + mobile 390 px. `bash tools/recette/run.sh`. L'audit de contraste est
  rejoué sur **6 accents** : l'accent par défaut (jaune très clair) masque des
  fautes qu'un accent de marque ordinaire révèle aussitôt. **À relancer sur
  chaque clone une fois l'accent du client réglé.** S'y ajoutent **`mouvement`**
  (Phase 16 — transitions nommées ; ⚠️ n'inspecte que `dashboard.css` sur `/gestion`),
  `public` (les 3 pages publiques), `landing` et `cod` (une vraie commande COD de
  bout en bout). Portes de clôture des phases récentes (seed marqué + purge en
  `trap`, incluses dans `tout`) : `bilingue` (7), `riche` (8), `categories` (9),
  **`conversions` (10 + 13 — recette serveur, HTTP intercepté, 0 appel réel ;
  `capi` en est l'alias), `abandonnees` (11 — /gestion, gérant connecté),
  `suivi` (12 — Confirmée→Suivi)**. Soit **15 portes** au total.
- **`scripts/recette-api-transporteur.php`** : recette **réseau** d'un
  transporteur — confronte nos parseurs (tarifs + suivi) à la vraie API, en
  lecture seule. `wp eval-file /scripts/recette-api-transporteur.php <id> [tracking]`.
  À rejouer quand un livreur annonce une version d'API, et en recette de clone :
  un parseur tolérant qui se trompe ne fait aucun bruit (cf. §7, item 4).
- **`scripts/make-deploiement.sh`** → **`Deploiement/`** (gitignoré) : le paquet de
  mise en production. `bash scripts/make-deploiement.sh <domaine>` produit
  `public_html.zip`, `database.sql.gz` (URLs **déjà réécrites**), un `wp-config.php`
  **modèle** (aucun secret réel) et un **`MANIFESTE.md`** qui inscrit le commit
  d'origine du paquet.
  🚨 **Le manifeste n'est pas décoratif** : `Deploiement/` est gitignoré, donc `git
  status` reste propre quel que soit son âge. Le clone de référence a livré un paquet
  de **129 commits de retard** sans que rien ne le signale. Pour dater un paquet :
  `git log --oneline <commit-du-manifeste>..HEAD | wc -l` — tout sauf `0` mesure ce
  qu'il ne porte pas. *Le dépôt ne peut pas dater ce qu'il ne suit pas.*
  ⚠️ Le script refuse de bâtir depuis un arbre sale (le manifeste désignerait un état
  qui n'a jamais existé), et copie les deux plugins maison depuis `plugins-dev/` :
  sous `wordpress/`, ce ne sont que des points de montage **vides**.
- **`deploy/`** (gitignoré) : *ancien* kit de mise en production, **fait à la main**
  et remplacé par le script ci-dessus. Son `reset-boutique.sql` faisait doublon avec
  la réinitialisation de `/gestion → Mon compte` (mêmes cibles : commandes, produits,
  leads COD), en plus risqué — SQL brut, préfixe `wp_` en dur, aucune capability.
- **`.gitignore`** : `wordpress/` exclu **sauf** les child themes
  `wp-content/themes/*-child/` et les `mu-plugins/` (whitelist en cascade).

---

## 6. i18n — état réel

| Catalogue | Chaînes | État |
|---|---|---|
| `aod-client-dashboard-ar.po` | 691 | **Complet** (seules les 3 chaînes de marque restent volontairement en FR). |
| `aod-cod-form-ar.po` | 399 | **Complet depuis le 2026-07-07** (396 traduites — 3 chaînes de marque non traduites, voulu). Complété par le dictionnaire FR→AR des variantes et les `name_ar` du JSON géo. |

Workflow : `plugins-dev/aod-client-dashboard/bin/make-translations.sh`
(extract → merge → compile → liste les manquantes ; `--strict` pour CI).
Traduction manuelle (pas d'internet sortant) — glossaire dans
`woo-jude-form-premium.1.0.7/`.

⚠️ **Ces deux catalogues ne couvrent que le CODE.** `gettext` ne traduit pas la
base de données : **hors page de vente, le contenu saisi par le gérant reste en
français quand le site passe en arabe.**

| Contenu marchand | En arabe |
|---|---|
| Titre / description d'un produit | ✅ **traduits depuis la 7.2** — métas `_aod_title_ar` / `_aod_desc_ar` saisies dans `/gestion`, rendues par filtres d'affichage gardés (`AOD_COD_Catalog_Filters`). Sans saisie AR → repli FR, rendu identique à l'octet |
| `_aod_selling_points` | ✅ **traduits depuis la 7.3** — méta parallèle `_aod_selling_points_ar`, appariée **par rang** ; rendus au seul site d'affichage (`class-aod-cod-form.php:786`, réutilisé par la page de vente et le bloc). La liste FR est **canonique** : elle fixe le nombre de puces, une ligne sans AR replie sur le FR |
| Noms de catégories | ✅ **traduits depuis la 7.5** — term-meta `_aod_cat_name_ar` saisie dans `/gestion`. Rendus par **un seul** filtre, `get_term` : c'est le seul contenu de la phase où l'on filtre l'**objet** et non une chaîne — WooCommerce échoue `$category->name` **sans aucun hook** à l'accueil (`wc-template-functions.php:1253`) et dans le fil d'ariane (`class-wc-breadcrumb.php:177…`). L'objet venant du cache, le filtre rend un **clone** |
| **Blocs d'une page de vente** (Phase 6) | ✅ **traduits** — chaque champ porte son couple `{ fr, ar }` en base, saisi dans `/gestion` (§7 point 7) |
| Libellés/valeurs de variantes (`_aod_options`) | ✅ **traduits depuis la 7.4** — `label_ar`/`name_ar` **imbriqués dans la méta** (pas de méta parallèle : l'appariement est alors **structurel**, donc indésalignable). Cascade `localize_variant()` : **saisie du gérant > `variant_dictionary()` > FR**. Le dictionnaire (~60 mots, `class-aod-cod-form.php`) est **conservé** et rétrogradé en repli |

Le commentaire du dictionnaire promettait depuis toujours un « repli quand le gérant
n'a pas saisi de traduction arabe explicite » alors que **cette saisie n'existait nulle
part**. La 7.4 a rendu le commentaire vrai.

🚨 **La contrainte propre aux variantes, à ne jamais perdre de vue :** le nom FR d'une
valeur n'est pas qu'un texte, c'est une **clé** — celle que le navigateur renvoie
(`value=`), que le serveur compare (`AOD_COD_Form::match_option()`) et qui est écrite
sur la ligne de commande. La traduction y est **purement d'affichage**. Traduire cette
clé ne traduit rien : elle **casse la commande**, silencieusement, et seulement pour
les arabophones. La 7.4 a soldé à ce titre **deux fuites `gettext` vivantes** :
`__( 'Couleur' )` sur la branche de compat (la locale AR **est active en `admin-ajax`**
→ la clé du meta de commande partait en `اللون`, jusque dans le bordereau transporteur)
et `color_palette()` (dont le JS injectait le nom **traduit** dans le champ du nom
canonique). Les deux sont désormais des **constantes françaises**, la traduction se
faisant à l'affichage. **Leçon générale : une chaîne qui sert d'identifiant se fige ;
seule une chaîne affichée se traduit.**

Le helper de repli historique est `public static` :
`AOD_COD_Data::label( $fr, $ar )` (`class-aod-cod-data.php:106`, repli AR→FR
seulement). La **Phase 6** (landing produit, **livrée**) rend bilingue tout le
contenu d'une page de vente via le couple `{ fr, ar }` :
[LANDING-PRODUIT.md](LANDING-PRODUIT.md) §2.3. Depuis la **7.5**, **tout le contenu marchand est bilingue** : titre/description
produit, arguments de vente, variantes et noms de catégories. **La Phase 7 est
soldée (7.6, 2026-07-17)** — c'était la dette la plus ancienne du template — et sa
preuve est **rejouable par clone** : `bash tools/recette/run.sh bilingue`
(`tools/recette/catalogue-bilingue.js` + fixtures auto-seedées/purgées par
`scripts/recette-seed-bilingue.php`) vérifie en navigateur les 10 surfaces
acheteur dans les deux langues, le repli d'un produit non traduit, et la garde
de bout en bout (commande COD passée site en arabe → ligne de commande, /gestion
et SQL restent FR).

**Étape 7.1 livrée (armature)** : `AOD_COD_Catalog_I18n`
(`class-aod-cod-catalog-i18n.php`) pose la mécanique de traduction **gardée** du
catalogue — `pick()` (repli **bidirectionnel**, pure), `should_translate()` (garde
pure : AR active **ET** hors admin **ET** contexte public), `active()`/`render()`
(les wrappers, câblés à `is_arabic()`, `is_admin()` et la query var `aod_cd` qui
exclut l'éditeur `/gestion`).

> 🚨 **`AOD_COD_Data::is_arabic()` lit la LANGUE, jamais le sens d'écriture**
> (dette `D10`, soldée le 2026-08-03). Elle s'écrivait `… || is_rtl()` : le
> raccourci tenait tant que RTL ⇔ arabe, mais `is_rtl()` répond « le rendu va de
> droite à gauche », pas « le visiteur lit l'arabe ». Un thème RTL déposé sur un
> clone **francophone** — que rien n'interdit — faisait basculer d'un coup les
> 58 wilayas, les 1541 communes et tout le contenu marchand de la page de vente en
> arabe, sur un site en français.
> **Ce qu'elle lit maintenant** : `get_user_locale()` dans le wp-admin réel,
> `get_locale()` partout ailleurs — **même frontière que
> `AOD_COD_Lang::filter_locale()`** (`is_admin() && ! wp_doing_ajax()`), et pour la
> même raison : admin-ajax n'est pas l'admin, c'est le visiteur.
> ⚠️ **La branche admin n'est pas cosmétique** : `AOD_COD_Lang` se retire
> volontairement du wp-admin, donc un administrateur au profil arabe ne devait son
> arabe qu'à `is_rtl()`. Le raccourci rendait **un vrai service par effet de bord** ;
> le retirer sec aurait été une régression déguisée en nettoyage.
> ⚠️ **`AOD_COD_Form::is_arabic()` délègue** — ne lui redonne pas de corps propre :
> les deux avaient déjà divergé en silence (l'une lisait la langue, l'autre y
> ajoutait `is_rtl()`), soit deux réponses possibles à la même question sur la même
> page. Cliquet : `tests/unit/test-langue-vs-sens.php`.

`AOD_COD_Landing::text()` **délègue** désormais à
`pick()` (source de repli mutualisée). **Convention de stockage actée** : le pendant
arabe d'un champ vit dans une méta parallèle `_aod_*_ar` (produit) / term-meta
(catégorie) ; le champ cœur WP reste le FR canonique, jamais modifié ; absence
de `_ar` = repli FR.

**Étape 7.2 livrée (titre + description)** : `AOD_COD_Catalog_Filters`
(`class-aod-cod-catalog-filters.php`) **branche** l'armature. Saisie AR dans
l'éditeur produit de `/gestion` (couple `{ fr, ar }` via
`AOD_CD_Dashboard::render_pair()`, promue en helper partagé) → métas `_aod_title_ar`
/ `_aod_desc_ar` ; le champ cœur reste le FR canonique. Rendu par filtres
**d'affichage uniquement**, tous gardés : `the_title` (grille accueil, Boutique,
`<h1>`, fil d'ariane), `the_content` (**priorité 9** — `wpautop` est en 10 ; après
lui l'AR sortirait sans `<p>`), `single_post_title` (le `<title>` ; ⚠️ ce filtre
reçoit un **`WP_Post`**, pas un ID). Les deux rendus **bruts** que nous possédons
appellent `render()` explicitement : le **repli du héro** de la page de vente
(`class-aod-cod-landing.php`) et la **liste d'un pack** (`class-aod-cod-form.php`).

🚨 **Aucun getter WooCommerce n'est filtré, et il ne faut jamais le faire** :
`$order->add_product()` appelle `$product->get_name()` **et persiste** le résultat
dans `order_item_name`, depuis la requête acheteur `wp_ajax_nopriv_aod_cod_submit`.
Filtrer `woocommerce_product_get_name` graverait le nom **arabe** dans les commandes
— bordereaux, e-mails et WhatsApp le rejoueraient à jamais et le matching serveur
sur le FR canonique casserait. Vérifié en recette : commande COD réelle passée
**site en arabe** → l'article est enregistré **« Écouteurs Sport Pulse »** (FR).

**Limites assumées de 7.2** (documentées, pas découvertes) : le **JSON-LD** reste FR
(`class-wc-structured-data.php` lit les getters bruts) ; la **recherche produit**
interroge `post_title`/`post_content` en base → chercher en arabe ne trouve rien ;
le **slug/URL** reste latin ; l'**`alt`** de l'image produit reste FR (Astra/WC le
dérivent du nom du produit hors de nos filtres — pré-existant, non régressé).

---

## 7. Dette technique & points de vigilance connus

1. ~~Traduction AR du formulaire COD très incomplète~~ — **rattrapée le
   2026-07-07** (277/280). Maintenir via le workflow `make-translations.sh` à
   chaque nouvelle chaîne.
2. **Granularité des permissions : Phase 1 terminée** (étapes 1.1→1.6,
   v0.18.4→0.18.8) : traçabilité de confirmation, rôle `aod_confirmateur` +
   6 caps custom (**7 depuis la Phase 11**, avec `aod_cap_abandoned`) +
   `AOD_CD_Roles::user_can()`, routeur/AJAX gouvernés par
   capability, section Équipe, affectation de produits + filtrage serveur,
   recette FR/AR mobile. **Phase 2 terminée** (étapes 2.1→2.5,
   v0.19.0→0.19.3) : socle Accueil (onglets par capability, filtres communs
   persistants, cache d'agrégats versionné, étape 2.1) ; onglet Revenus
   filtrable (2.2 — filtres produit/confirmateur, jointures SQL bimode) ;
   onglet Livraison (2.3 — taux/ventilations/délai/bloqués sur les statuts
   de colis, périmètre produits du confirmateur) ; onglet Confirmation
   (2.4 — taux/délai/ventilation par confirmateur, visibilité par rôle,
   non-attribuées) ; i18n + recette de phase FR/AR/RTL mobile (2.5 —
   matrice caps × onglets, persistance des filtres inter-onglets, calendrier
   natif RTL validé à 390 px). **Phase 3** : vue Stock livrée
   (étape 3.1, v0.20.0 — couverture/badges/packs, classe dédiée) ;
   ajustement rapide + alertes (étape 3.2, v0.20.1) ; i18n + recette de
   phase FR/AR/RTL mobile (étape 3.3 — parcours d'ajustement au clic réel,
   invalidation croisée stock ↔ ventes validée, messages AJAX localisés).
   ⚠️ **L'état d'avancement ne se lit PAS ici** : la table §C.3 de la ROADMAP fait
   foi, et elle seule. Ce paragraphe a annoncé « Phase 3 en cours » et « Phases 1 à
   6 terminées (33/33) » jusqu'au 2026-07-29, dans un fichier qui décrivait déjà la
   Phase 43 — deux affirmations d'état figées dans le document désigné comme
   « faisant foi sur le fonctionnement du code ». Ce qui suit décrit **ce que fait
   le code**, phase par phase ; les compteurs, eux, sont partis (étape 44.3).
   Phase 5 (refonte UI/UX de `/gestion`, v0.22.0→0.23.0) : design tokens +
   accent unique piloté par le Personnalisateur (5.1), bibliothèque de
   composants (5.2), coque sidebar + fil d'ariane (5.3), refonte page par page
   (5.4→5.7), **accessibilité AA + clavier + recette automatisée** (5.8, cf.
   §3 « Couche UI & accessibilité » et `tools/recette/`).
   Phase 6 (page de vente par produit, cod-form v1.25.0→1.28.0) : socle + schéma
   de blocs + rendu (6.1→6.3), blocs de conversion — avis, FAQ, urgence, barre
   collante (6.4), modèles de départ + copie d'une landing (6.5), entrée dans la
   recette du clone (6.6, `tools/recette/landing-produit.js`).
3. **Stock par variante — LIVRÉ (Phase 40, le 2026-07-25).** Chaque **valeur**
   d'option (`_aod_options`) porte désormais un champ `stock` : **`''` = non géré
   (illimité, défaut) ≠ `0` = rupture (géré)**. Migration nulle (clé absente = `''`).
   Règle **mono-axe (D1bis)** imposée à la normalisation côté serveur (un seul axe
   porte un stock ; le décrément reste néanmoins générique). Le stock **produit** est
   la **somme calculée** des variantes gérées (`AOD_CD_Section_Stock::variant_stock_summary()`),
   **jamais écrite** dans le `_stock` Woo (pas de duplication d'état — leçon Phase 35) ;
   la page Stock l'affiche en lecture seule (« géré par variante »). Une valeur gérée
   ≤ 0 est **en rupture** : grisée côté acheteur **et rejetée** par le funnel serveur
   `AOD_COD_Form::build_order_lines()` (une sélection forgée ne crée aucune commande).
   Décrément/ré-incrément par hook de statut (cf. §3, `AOD_CD_Section_Orders`). Gardes :
   `tests/unit/test-variant-stock.php` (schéma, mono-axe, garde rupture, décrément +
   idempotence) + porte `bash tools/recette/run.sh variante`.
4. **Tarifs de livraison — Phase 4 terminée.** Couche transporteur livrée : 4.1
   (Yalidine/Yalitec, v1.20.0) + 4.2 (E-com Delivery/ex-ZR + EcoTrack/white-labels,
   v1.21.0) — `get_fees()`/`supports_fees()` en lecture seule. **UI d'import
   livrée (4.3, dashboard v0.21.0)** : préview + marge + backup/restore dans
   `/gestion` (AJAX `aod_cd_import_fees`). **i18n + recette de phase (4.4,
   dashboard v0.21.1)** : AR des deux plugins vert (dashboard 540/5 marque ·
   cod-form 281/3 marque), recette structurelle FR/AR/RTL/mobile de la préview
   (flèche `→` retournée en RTL). Noest exclu (pas d'API publique), Maystro à
   confirmer.

   **✅ E-com Delivery confirmé en recette réseau réelle** (2026-07-13, compte de
   production, 1 253 colis, API v2.110 — plugin v1.22.0). Elle a trouvé **deux
   bugs que la lecture du code ne pouvait pas voir**, tous deux nés du parsing
   « défensif » lui-même :
   - `GET /tarifs` renvoie la liste sous **`wilayas`** et le tarif domicile sous
     **`domicile`** — deux noms absents de nos clés candidates. L'import de tarifs
     E-com **échouait intégralement** (« Aucun tarif n'a pu être récupéré »).
     Corrigé : 56/58 wilayas importées, valeurs identiques à l'API (50 et 54 sont
     à `0/0`, non desservies, et sont écartées — les prix saisis restent intacts).
   - `get_tracking()` déduisait le statut courant du **dernier événement
     d'historique**, alors que la vérité est le champ `situation` du colis (et
     surtout `id_situation`, entier stable depuis l'API v2.101). Or l'historique
     d'un colis **annulé** se termine par « Au Bureau », celui d'un « Ne Réponde
     pas » par « Appel Tel ». Résultat mesuré sur 80 colis réels : **14 % des
     colis dans le mauvais groupe**, et les compteurs « Échec de livraison » et
     « Annulés » de l'onglet Livraison affichaient **0 en permanence** — tout
     tombait dans « En transit » (`delivery_group()` range tout non-terminal là).
     Corrigé par la table `id_situation` → statut (`status_from_situation_id()`).

   **✅ EcoTrack confirmé en recette réseau réelle** (2026-07-13, white-label
   « Allo Livraison » — `allolivraison.ecotrack.dz`). Contrairement à E-com, le
   schéma correspondait : `GET get/fees` → `{ livraison: [ { wilaya_id, tarif,
   tarif_stopdesk } ] }`, soit exactement nos clés candidates (montants en
   chaînes — le cast `(int)` s'en charge). **58/58 wilayas, valeurs exactes** ;
   50 et 54 sont sans stop-desk (`0`) → colonne bureau laissée intacte à l'import.
   Suivi : `get/trackings/info` renvoie des codes d'activité **anglais**
   (`order_information_received_by_carrier`), bien couverts. Un correctif mineur
   quand même (v1.22.0) : certaines déclinaisons émettent des codes **français**
   (`prete_a_expedier` sur `get/orders`) — sans eux dans la table, le mot matchait
   « expédi » et un colis encore au dépôt s'affichait « en transit ». La table
   `map_ecotrack_code()` couvre désormais les deux vocabulaires.
   ⚠️ Réserve honnête : le compte de test n'avait **qu'un seul colis, dans un seul
   état** (« Prêt à expédier »). Les chemins livré / annulé / retourné n'ont donc
   **pas** pu être observés en vrai — ils sont couverts par la table, ce qui est
   une inférence, pas une preuve.

   Leçon générale : **un parseur tolérant ne signale jamais qu'il s'est trompé** —
   il renvoie du vide, ou un statut « inconnu » qui se fond dans « en transit ».
   D'où **`scripts/recette-api-transporteur.php`**, à rejouer quand un transporteur
   change d'API (§5). ⚠️ Reste non confronté au réel : **Maystro** (et **Noest**,
   sans API publique, hors périmètre).
5. **`class-aod-cd-dashboard.php` ≈ 4 214 lignes** : règle actée dans la DoD de
   la ROADMAP — toute **nouvelle** section (Équipe, Accueil, Stock, Page de vente)
   vit dans sa propre classe `class-aod-cd-section-*.php`, on n'empile plus dans le
   monolithe. L'étape **7.0 a appliqué la leçon en sens inverse** : elle a **extrait**
   la section Produits/Catégories **existante** (≈ 1 420 lignes) vers
   `class-aod-cd-section-products.php`, préalable au chantier Phase 7 (contenu produit
   bilingue). La **Phase 30** a depuis extrait WhatsApp (30.1), Pixels (30.2),
   Mon compte (30.3), Livraison (30.4) et Suivi (30.5) — il ne reste que
   **Commandes** (30.6). Le compte de lignes COURANT et son plafond vivent dans
   `tests/unit/test-decomposition.php`, jamais ici (un chiffre recopié vieillit).
6. **Stats calculées en PHP à la volée** : les filtres produit/confirmateur de la
   Phase 2 passent par des **jointures sur les lignes de commande** + transients
   (fait en 2.2 pour Revenus, étendu en 2.3 pour Livraison — restriction
   « expédiées » et périmètre produits en jointures supplémentaires — et réutilisé
   tel quel en 2.4 pour Confirmation : les 3 onglets sont servis).

   ✅ **Corrigé le 2026-07-19 — une régression d'un bug déjà soldé.** Ces filtres
   joignaient `wp_wc_order_product_lookup`, la table d'**analytics** de WooCommerce,
   remplie **en différé** par l'Action Scheduler. Une commande qui vient d'arriver
   n'y est pas encore ; si l'analytics est désactivé ou le planificateur en carafe
   (fréquent en mutualisé), elle n'y sera **jamais**. Conséquence mesurée : le CA du
   jour et les statistiques d'un confirmateur restreint **sous-comptaient en
   silence**. Le même piège avait été identifié et corrigé côté liste Commandes en
   **5.5** (`0898855`, « le confirmateur ne voyait pas les commandes fraîches »),
   avec douze lignes de commentaire l'expliquant — et il avait **survécu dans
   `AOD_CD_Section_Home`**, à trois fichiers de distance du raisonnement qui
   l'interdisait. Les deux jointures sont désormais **le même helper**,
   `AOD_CD_Section_Orders::product_orders_join_sql()` (public static — sur
   `AOD_CD_Dashboard` jusqu'à l'extraction 30.6) : la liste
   Commandes, ses compteurs, l'Accueil et le Stock répondent la même chose à la
   même question. **Leçon** : un raisonnement écrit dans un fichier ne protège pas
   les autres — seul un helper partagé le fait.
7. **Le contenu marchand est bilingue dans la landing ET sur le titre/description
   d'un produit** (cf. §6). La Phase 6 (v1.25.0) a apporté le couple `{ fr, ar }` par
   champ dans les blocs d'une page de vente ; l'**étape 7.2** (v1.31.0 / v0.30.0) a
   étendu le mécanisme au **titre** et à la **description** du produit (métas
   `_aod_title_ar` / `_aod_desc_ar`, filtres d'affichage gardés), et l'**étape 7.3**
   (v1.32.0 / v0.31.0) aux **arguments de vente** (`_aod_selling_points_ar`, appariés
   par rang — la liste FR reste canonique et fixe le nombre de puces), et l'**étape
   7.4** (v1.33.0 / v0.32.0) aux **variantes** (`label_ar`/`name_ar` imbriqués ;
   dictionnaire rétrogradé en repli ; **2 fuites `gettext` sur des clés** soldées au
   passage — cf. §6), et l'**étape 7.5** (v1.34.0 / v0.33.0) aux **noms de catégories**
   (term-meta `_aod_cat_name_ar`). **Plus rien du contenu marchand ne reste en français
   quand le site passe en arabe.** Chantier **Phase 7 SOLDÉ** (périmètre figé le
   2026-07-15 — [ROADMAP-AMELIORATIONS.md](ROADMAP-AMELIORATIONS.md) A.7 / Phase 7) :
   **7.0 (extraction), 7.1 (armature `AOD_COD_Catalog_I18n`), 7.2 (titre +
   description), 7.3 (arguments de vente), 7.4 (variantes), 7.5 (catégories) et 7.6
   (recette de clôture, 2026-07-17) livrées.** La 7.6 laisse une preuve rejouable :
   `tools/recette/catalogue-bilingue.js` (porte `bilingue` de la recette, incluse
   dans `public`), fixtures discriminantes seedées/purgées par
   `scripts/recette-seed-bilingue.php`.

   🔎 **Ce que la 7.5 a appris, et qui vaut au-delà d'elle.** Le risque annoncé n'était
   pas le bon : la ROADMAP prévoyait un danger « nom = clé » hérité de la 7.4 — il
   n'existe pas, la clé d'une catégorie est son `term_id`. Le vrai risque était que les
   filtres d'**affichage** de la 7.2 sont **inopérants** ici : Woo échoue
   `$category->name` sans hook (accueil `wc-template-functions.php:1253`, fil d'ariane
   `class-wc-breadcrumb.php:177,189,243,390`). Il faut donc filtrer l'**objet terme** —
   ce qui reste sûr **parce qu'**aucun nom de catégorie n'entre dans une commande.
   **Un seul hook suffit : `get_term`.** `populate_terms()` l'appelle pour chaque terme
   de chaque requête, **cache HIT compris** (`class-wp-term-query.php:795,826,1131`), et
   `get_term_by()` se termine par `get_term()` (`taxonomy.php:1159`) — donc `get_terms`,
   `single_term_title` et `woocommerce_product_categories` sont **redondants** (vérifié :
   le `<title>` et le `<h1>` d'une archive sortent traduits sans les poser), et
   `term_name` est **inopérant** (`get_term()` appelle `sanitize_term()` en `filter='raw'`).
   ⚠️ Deux pièges propres à l'objet : il sort du **cache** (`wp_cache_get( $id, 'terms' )`)
   → le muter empoisonne la requête, on rend un **clone** ; et le hook étant très chaud,
   **la garde passe avant toute lecture de term-meta** (sinon une requête SQL par terme,
   même site en FR — `term_exists()`/`get_term_by()` posent `update_term_meta_cache => false`).
   ⚠️ **Portée de la garde** : `active()` ferme sur **wp-admin** et **`/gestion`**.
   L'**aperçu d'un bloc dans l'éditeur Gutenberg** passe par la **REST API**, où
   `is_admin()` est faux et où le cookie `aod_lang` force la locale : un gérant ayant
   visité le front en arabe voit son aperçu en arabe. **Cosmétique et assumé** (bloc
   dynamique, rien n'est persisté, aucune commande touchée).
   ⚠️ **La règle qui protège les commandes** : on ne filtre QUE des hooks
   d'affichage, **jamais** un getter WooCommerce — `add_product()` persiste
   `$product->get_name()` dans la commande depuis une requête acheteur (cf. §6).
8. **`template_include` est filtré depuis la v1.25.0** (`AOD_COD_Landing`) — c'est
   le **seul** endroit du dépôt qui substitue un gabarit. Il ne s'active que sur
   `is_product()` **et** interrupteur ON **et** landing non vide ; dans tous les
   autres cas il rend la main au thème sans rien toucher. Toute la personnalisation
   produit restante passe, elle, par les hooks Woo
   (`woocommerce_single_product_summary` prio 1 et 35). Il n'y a toujours **aucun**
   dossier `woocommerce/` d'override dans un thème.

9. 🟡 **Pixel Meta & devise DZD — levier de reporting livré, éteint par défaut.**
   Constaté par `tools/recette/run.sh cod` : à la conversion `Purchase`, le pixel
   répond *« Parameter 'currency' is invalid for event 'Purchase' »*. Le dinar
   algérien ne fait pas partie des devises reconnues par Meta ; l'événement part
   bien (l'achat est compté) mais sa **valeur** est rejetée — un gérant qui pilote
   au ROAS pilote sur du vide.
   **Mécanisme livré** (cod-form) : `AOD_COD_Pixels::to_reporting( $value, $currency,
   $settings )` — helper pur, testé (`tests/unit/test-pixels.php`) — bascule la
   valeur ET la devise vers une **devise de reporting** déclarée par le clone
   (`report_currency` + `report_rate`, réglés dans la section Pixels de `/gestion`
   **et** la page admin). Appliqué aux **deux** chemins d'émission (sortie JS
   `purchase_pixels()` et payload CAPI `purchase_payload()`), donc Meta pixel et
   Conversions API restent cohérents.
   **Éteint par défaut** : sans réglage (devise vide **ou** taux ≤ 0), le couple
   passe inchangé — un clone qui ne configure rien continue d'envoyer `DZD` tel quel,
   exactement comme avant. **Le choix reste commercial** (quelle devise, quel taux :
   ça change les chiffres du gestionnaire de pubs) — le template fournit le levier,
   l'owner le règle **par clone**. Vaut aussi pour TikTok/Snapchat/Google (même
   `value`/`currency`). La recette affiche l'avertissement sans échouer.

10. ~~**Trois dédoublonnages différés (revue 8.3, clôture 8.4).**~~ **Résolus le
    2026-07-18 (v0.36.2)**, après la clôture 8.4 : (a) `ajax_desc_video` et
    `ajax_desc_image` partagent désormais `handle_desc_upload( array $mimes )` (nonce
    + cap + fichier + `require` + `media_handle_upload` avec `test_form=>false` ;
    chaque appelant ne garde que sa whitelist de mimes et son payload) ; (b) la
    modale de `pickVideo()` **et** celle de `textPromptModal()` passent par un
    `openOverlayModal( { title, bodyHtml, initialFocus, onCancel, onEscape, onKey } )`
    commun (scaffold + piège de focus + Échap + `[data-cancel]`) ; (c) `htmlEsc`,
    l'`esc` local de `textPromptModal` et `escapeHtml` sont fusionnés en un seul
    `escAttr` au niveau module (échappeur d'attribut, distinct du `esc` texte). Vérifié
    au navigateur (modales vidéo + note : ouverture/fermeture, focus, 0 pageerror) ;
    265 tests + gates a11y verts.

11. **Accueil : la sélection des catégories est aveugle au gérant — ✅ RÉSOLU (Phase 9, soldée 3/3 le 2026-07-18).** La section « Nos catégories » de `[aod_home]` délègue à
    `[product_categories limit="6" columns="3" hide_empty="1"]`
    (`class-aod-cod-pages.php:180`). Deux divergences avec la création de catégorie
    dans `/gestion` (`ajax_save_category()`, qui ne pose ni produit, ni flag, ni
    ordre) : une catégorie **sans produit publié** est **absente** de l'accueil
    (`hide_empty="1"`), et au-delà de **6** catégories seules les 6 premières (nom
    ASC) s'affichent (`limit="6"`) — le gérant ne choisit ni lesquelles ni l'ordre.
    **Résolu (Phase 9, 9.1 + 9.2).** 9.1 (dashboard v0.37.0) : le gérant coche
    « Afficher sur l'accueil » + ordonne chaque catégorie (`_aod_cat_home` +
    `_aod_cat_home_order`). 9.2 (cod-form v1.36.0) : `AOD_COD_Pages::home_categories_markup()`
    rend **exactement la sélection**, dans l'ordre (`[product_categories ids="…"
    orderby="include" hide_empty="0"]`, vides comprises), **plafond de 6 retiré** ;
    repli non-régressif (`hide_empty="1"`) si rien n'est coché. ⚠️ Le filtrage des
    catégories cochées se fait **en PHP** (`get_term_meta`), pas en `meta_query` :
    `get_terms( meta_key/meta_value )` ne remonte pas la term-meta sur cette version
    WP. La porte `run.sh categories` existe (`tools/recette/categories-accueil.js`). Cf. `ROADMAP-AMELIORATIONS.md` §A.9 / Phase 9.

12. 🟠 **La recette pouvait abîmer un clone client, et mentait par endroits
    (audit du 2026-07-19).** La suite complète n'avait jamais été lancée d'un bloc :
    elle sortait à **2**. Deux familles de défauts, dont la première est la plus
    grave parce qu'elle touche des données réelles.

    **Corrigé** :
    - `run.sh` **supprimait l'accent du gérant** — `restore_accent()` faisait un
      `theme mod remove` sans sauvegarde, et le `trap` l'appelait à chaque
      exécution, y compris `run.sh cod`. Alors même que le README demande de
      rejouer la recette **après** avoir réglé l'accent du client. On capture
      désormais la valeur d'origine ; `use_default_accent()` est le geste distinct
      dont la porte `contraste` a besoin.
    - La porte **`souris` changeait le statut d'une vraie commande**. Sur
      « Confirmée », `AOD_Shipping::auto_send()` faisait partir un **colis réel** —
      qu'aucun retour arrière ne rappelle ; sur « En cours »/« Terminée », le stock
      était décrémenté sans être rendu. Elle sème maintenant une commande jetable
      **sans ligne produit** et se borne à des statuts inertes.
      🔁 **Phase 47.2** : le premier des deux dangers a disparu à la racine — le
      hook d'envoi n'existe plus. Le récit reste (il est exact à sa date) ; la
      précaution reste aussi, pour le stock et la traçabilité.
    - La porte **`cod` empruntait le dernier produit publié**. Sur un produit à
      options obligatoires, le refus vient du **JavaScript** (`collectItems()` →
      `return` avant le `$.post`) : aucune requête ne partait, et pourtant honeypot,
      time-trap et verrou 24 h étaient **verts** — ils ne testaient que « un refus a
      eu lieu ». Sept assertions de relecture en base disparaissaient en silence.
      Produit désormais semé (avec option), motif de chaque refus vérifié, commande
      supprimée en fin de porte.
    - `recette-capi.php` désarmait **toutes** les tâches `aod_capi_dispatch` du
      clone (conversions de vraies commandes perdues) ; désarmement ciblé.
    - `recette-capi.php` et `recette-seed-phase12.php` restauraient un réglage
      global (`aod_cod_pixels` avec des **jetons factices**, `aod_shipping_auto`
      **désactivé**) en fin de fichier seulement : une fatale ou un OOM du conteneur
      laissait le clone dans cet état. Filet `register_shutdown_function()`.

    **Leçon générale, à appliquer à toute nouvelle assertion** : *une assertion
    d'ABSENCE doit être adossée à une présence, ou vérifier le motif* — sinon elle
    verdit quand la page est vide, quand le site est mort, ou quand le refus vient
    d'ailleurs. Et *une porte ne doit jamais emprunter les données du clone* : elle
    sème les siennes, marquées et purgées.

13. 🟢 **Second passage sur la recette (2026-07-19) — dette du point 12 soldée, et
    deux de mes constats corrigés.** Chaque affirmation du point 12 a été rejouée
    contre le code avant d'être traitée ; deux ne tenaient pas.

    **Ce qui était FAUX dans le point 12, et pourquoi** :
    - « `rtl-mobile.js` et `a11y-noms.js` verdissent sur une page de **login** » —
      non : `lib.js::login()` sort en code 2 si la connexion échoue. Le vrai trou
      est ailleurs, et il est réel : les deux audits sont bornés à `body.aod-cd`,
      donc **toute** page qui ne rend pas cette coque (plugin désactivé, page
      /gestion dépubliée, « accès refusé », 404) leur fait inspecter zéro élément
      et rendre un vert parfait. Vérifié en pointant les scénarios sur une URL
      inexistante : avant, vert ; après le garde-fou, rouge motivé. À noter — une
      section *inconnue* ne déclenche PAS le garde-fou : le routeur de /gestion
      retombe sur une section valide, la coque est rendue, le vert est mérité.
    - « `suivi` a ses assertions centrales neutralisées par la pagination » — non :
      les listes sont triées `date DESC` et les graines sont créées à l'instant,
      donc toujours en page 1. Seule `abandonnees` était concernée, et par un seul
      biais : son prospect « abandonné » est semé à **-1 h**, donc repoussable en
      page 2 sur une boutique active. Les deux assertions d'onglet sont désormais
      cadrées par `&s=Recette`, ce qui ne laisse qu'une explication possible à une
      absence : le filtre.

    **Ce qui était vrai, et corrigé** :
    - `landing` découvrait ses produits **sans `--post_status=publish`**. Vérifié :
      `wp post list` renvoie bien les brouillons, et *en tête* (tri par date). Le
      scénario visite en visiteur anonyme → 404 → porte rouge sur un clone sain
      dont le gérant a un produit en cours de rédaction. Un faux échec apprend à
      ignorer la recette : c'est aussi grave qu'un faux succès.
    - `clavier` appliquait **trois** politiques d'absence. Motivées : le sélecteur
      de statut est rendu par ligne de commande → son absence n'est légitime que si
      la liste est vide (sinon échec) ; `.aod-cd-catdd-btn` est rendu
      **inconditionnellement** par le formulaire produit → son absence est
      **toujours** un défaut (elle passait en silence, six assertions avec elle).
    - `run.sh` ne distinguait pas « passé » de « **retiré** ». Convention de sortie
      désormais explicite : `0` passé · `3` retiré · autre = échec. Le bilan nomme
      les portes retirées et rappelle qu'elles n'ont rien vérifié.
    - Trois portes vidaient **tous** les transients (`transient delete --all`) alors
      qu'elles ne veulent lever que deux verrous anti-flood. Purge ciblée sur
      `aod_cod_ipf_*` / `aod_cod_ph_*` (`lib.js::PURGE_VERROUS_COD`) : les caches de
      tarifs et de communes des transporteurs — reconstruits par des appels API
      externes — ne sont plus emportés au passage.
    - 🔴 **Trouvé en vérifiant, pas dans le point 12** : `bilingue` et `riche`
      **laissaient leur commande de garde en base**. Leur purge ne tournait qu'au
      DÉBUT de la porte, si bien que la commande créée survivait jusqu'à
      l'exécution suivante — au statut `processing`, donc **comptée dans le CA
      affiché au gérant**. C'est le défaut B4 corrigé pour `cod` au passage
      précédent, et que je n'avais pas cherché ailleurs. Les deux portes
      suppriment désormais leur commande en fin de course, repérée par son
      **téléphone** (unique par exécution) et non par le nom du client.

    **Reste, assumé** : la purge d'amorce par **nom de client** (filet des
    exécutions précédentes) — bornée à `localhost`, et le nom recherché est propre à
    la recette. Le durcir demanderait de marquer les commandes à la création, ce que
    ces portes ne peuvent pas faire : elles commandent par le formulaire public.

14. 🟢 **`phpcs:ignore` : audit intégral — 0 faille — ET cliquet depuis la Phase 23.**
    97 annotations auditées le 2026-07-19, 33 relues le 2026-07-20 (étape 18.3),
    les **40 restantes** le 2026-07-21 (Phase 23). **Périmètre réel : 170.**

    > 🚨 **Ce paragraphe a longtemps dit « périmètre actuel : 130 » — et c'était un
    > sous-ensemble présenté comme un tout.** 130 ne comptait que la famille
    > `EscapeOutput`. Les **40** autres — `NonceVerification` (CSRF),
    > `ValidatedSanitizedInput`, `WordPress.DB` (SQL), soit les familles les **plus**
    > sensibles — n'étaient dans le périmètre d'**aucun** audit. Elles l'ont été en
    > Phase 23 : **0 suspecte**. Un chiffre qui sonne comme une couverture totale
    > mais nomme un sous-ensemble est pire qu'un chiffre absent : il clôt la question.
    >
    > 🚨 **Et le risque « une 131ᵉ arrivera non relue » était déjà réalisé.** Une
    > annotation **NUE** (`// phpcs:ignore`, sans nom de sniff) vivait dans
    > `class-aod-cd-section-products.php:500` : elle désarmait **tous** les contrôles
    > de sa ligne. Elle avait échappé à l'audit de la 18.3 parce que celui-ci grepait
    > **par nom de sniff** — et elle n'en portait aucun.

    **Le cliquet** (`tests/unit/test-phpcs-ignore.php`, Phase 23) : R1 aucune
    annotation nue · R2 census par famille (sécurité marquée `🔒` — un ajout rougit)
    · R3 le stock de non-documentées ne remonte pas · témoin de balayage. Il balaie
    **récursivement** les deux plugins, jamais une liste de fichiers.
    ⚠️ Dette ouverte issue de cet audit : **D14** (7 suppressions plus larges que
    nécessaire — bloquée tant que `phpcs` est absent de l'hôte et du conteneur).
    Cf. ROADMAP §C.5.

    ✅ **D15 soldé (Phase 24, le 2026-07-21) — le contrat d'appelant s'exécute.**
    Les deux `capi_token_from_post()` lisaient `$_POST` **sans le recevoir en
    argument** : leur sûreté tenait à une phrase de docblock, et côté COD le helper
    était `public static`, appelable de n'importe où. Les deux sont désormais
    `private static` — le contrat devient **structurel**. Le cliquet
    `tests/unit/test-nonce-contract.php` énumère **dynamiquement** les **59**
    méthodes de `plugins-dev/` lisant `$_POST`/`$_GET`/`$_REQUEST` : **33** noncent,
    **5** délèguent à une méthode noncée de leur classe, **16** sont **déclarées
    avec leur raison**, et les **5** helpers ne se déclarent pas — le test énumère
    **leurs appelants** et exige que chacun nonce.

    > 🚨 **Deux leçons, l'une et l'autre payées par la mesure.**
    > (1) **Un cliquet qui ne résout pas la délégation crie au loup sur du code
    > sain** : 5 méthodes semblaient sans nonce (`ajax_save_member`,
    > `ajax_delete_member`, `ajax_centers`, `ajax_send`, `Account::render_page`) —
    > toutes délèguent d'un cran. Un contrôle qui rougit à tort se fait assouplir,
    > puis ne mord plus sur rien.
    > (2) **Ne jamais déclarer « lecture d'affichage » une méthode qui mute.**
    > `AOD_CD_Account::render_page` délègue depuis un appel **imbriqué dans un
    > `if`** — forme qu'un résolveur réglé sur les autres cas manque — et son
    > délégué appelle **`wp_insert_user()`**. La déclarer aurait gravé une fausse
    > déclaration de sécurité sur la méthode qui crée les comptes.

    ⚠️ **Ce que ce cliquet ne prouve pas**, écrit dans son en-tête : il est
    **statique** (aucune requête forgée) et **indifférent à l'ordre** — il prouve
    qu'un nonce est vérifié dans le chemin d'appel, **pas** qu'il l'est avant la
    première lecture. Exiger l'ordre ferait rougir 3 méthodes saines.

    **Revue des 33 (Phases 15 et 17), 2026-07-20.** Elles ne forment pas 33 cas
    distincts : **toutes** passent par l'un des deux mêmes points, et la sûreté se
    démontre une fois pour chacun.
    - `AOD_CD_Icon::svg( $name, $args )` — le nom est une clé d'un **catalogue
      fermé et littéral** (58 entrées, 0 valeur contenant `/` ou `..`, donc pas de
      traversée de chemin) ; un nom inconnu rend **la chaîne vide** avec
      `_doing_it_wrong()`, y compris pour les 5 appels à nom **dynamique**
      (`svg( $p['ic'] )`, `svg( $icon )`…). `class` passe par `esc_attr`, `size`
      par `(int)` puis `%d`, `weight` est contraint à `regular|fill`, et
      `data-dir` vient d'une liste fermée. **Tout le HTML sortant est soit
      reconstruit et échappé, soit lu d'un fichier versionné du plugin.**
    - `AOD_CD_Dashboard::empty_state()` — `esc_html` sur le titre et le texte,
      `esc_attr` sur la classe, et l'icône repart dans `svg()`.

    ⚠️ **La prémisse de ce verdict est désormais gardée.** Tout tenait sur un fait
    que rien ne vérifiait : `inner()` recopie **verbatim** ce qu'il y a entre
    `<svg>` et `</svg>` du fichier, sans le filtrer. C'est sûr tant que les
    fichiers le sont. `tests/unit/test-icones.php` asserte donc que les **71** SVG
    embarqués ne portent ni `<script>`, ni gestionnaire d'événement, ni
    `<use href>` — et publie le nombre de fichiers lus, sans quoi un `glob` vide
    se déclarerait vert. **Éprouvé au rouge** en déposant un SVG hostile : deux
    garde-fous indépendants ont rougi.

    ⚠️ **Ce qui reste ouvert (D6 de la ROADMAP §C.5)** : ceci est un **audit**, pas
    un **cliquet**. Rien n'empêche la 131ᵉ annotation d'arriver non relue. Poser
    PHP_CodeSniffer supposerait Composer et un accès réseau que ce template n'a
    pas (§3) — arbitré par l'owner le 2026-07-20 : revue manuelle, sans outil.

    Les deux plugins comptaient 97 commentaires
    `phpcs:ignore …EscapeOutput`, c'est-à-dire 97 fois « fais-moi confiance, cette
    sortie n'a pas besoin d'être échappée ». Un tel volume est en soi suspect : il
    a été audité **un par un**, en remontant chaque variable jusqu'à sa source.

    **Verdict : aucune donnée d'origine utilisateur n'atteint la sortie sans
    échappement.** Le motif est partout le même et il est correct — la variable
    échoée est du HTML assemblé par le code lui-même, dont chaque partie variable
    est déjà passée par `esc_html` / `esc_attr` / `esc_url` / `wp_kses_post`.
    Un balayage complémentaire des `echo` **non** signalés par un `phpcs:ignore`
    (là où une vraie faille se cacherait, personne ne l'ayant annotée) n'a rien
    donné non plus.

    Points vérifiés qui portaient le vrai risque :
    - **Public → gérant** (le scénario le plus dangereux : un visiteur anonyme
      écrit, le gérant lit en admin). Nom, téléphone, adresse, commune, nom
      d'article, métas de variante : tous sortent par `esc_html()`, les métas de
      ligne étant même traitées deux fois (`wp_strip_all_tags` puis `esc_html`).
    - **CSS de l'accent** injecté brut dans un `<style>` : la seule entrée est
      `get_theme_mod('aod_cd_accent')`, filtrée par `sanitize_hex_color()` avec
      repli sur une constante. Vérifié : `</style><script>…`, `red;}body{…}` et
      `javascript:` sont tous rejetés (`NULL`) — pas d'évasion possible.
    - **Erreurs brutes d'API transporteur** réaffichées au gérant
      (`class-aod-shipping.php`) : échappées dans `truck_icon()`.
    - **Contenu marchand du gérant** : `esc_html()` **puis** `wpautop()` — le bon
      ordre ; l'inverse serait exploitable.

    **Deux puits de HTML brut, laissés tels quels — décision motivée.**
    `page_head( …, $actions )` et `kpis()` (champ `$c[4]`) concatènent leur
    argument sans filtre : le contrat « déjà sûr » n'est qu'une convention PHPDoc.
    Les 12 appelants le respectent (vérifié). Le réflexe serait d'y poser un
    `wp_kses_post()` — et il passerait : les chaînes réellement transmises
    survivent **à l'octet près** (testé). **On ne le fait pas quand même** :
    `wp_kses_post()` supprime `<svg>`, et ce tableau de bord est bâti sur des
    icônes SVG inline. Un futur appelant qui mettrait un bouton à icône dans
    `page_head()` verrait son icône **disparaître sans le moindre message** — on
    échangerait une XSS hypothétique contre un échec silencieux bien réel, qui est
    exactement le défaut que les points 12 et 13 ont passé leur temps à chasser.
    Si ce durcissement revient sur la table, la bonne forme est une liste blanche
    qui inclut `svg`, pas `wp_kses_post()` tel quel.

    📌 **La Phase 15 a rendu cet argument beaucoup plus concret.** Quand ces lignes
    ont été écrites, le chrome était en emojis et « bâti sur des icônes SVG » était
    surtout une projection. C’est désormais littéral : 58 noms d’icônes inline via
    `AOD_CD_Icon`, y compris dans les boutons passés à `page_head()`. Poser un
    `wp_kses_post()` sur ces deux puits **effacerait aujourd'hui des icônes bien
    réelles**, silencieusement.

    ⚠️ **Aucun filet mécanique** : il n'y a ni `phpcs.xml`, ni `composer.json`, ni
    PHP_CodeSniffer installé, et le hook `pre-push` ne joue que `tests/run.sh`. Ces
    130 annotations sont de la **documentation de revue** — toutes relues à la main
    (2026-07-19 puis 2026-07-20), mais jamais vérifiées par
    un outil — et une nouvelle sortie non échappée ne serait signalée par rien.
    L'installer supposerait Composer et un accès réseau, que ce template n'a pas
    (§3) : à trancher par l'owner, pas à bricoler.

15. 🟡 **Le vert de la recette est un vert LOCAL : plusieurs correctifs des points 12
    et 13 sont DORMANTS sur ce clone (2026-07-19).** Les deux suites sortent à 0
    (**357** tests unitaires, **15** portes de recette, aucune retirée, aucun résidu en
    base). Ce résultat est réel, mais il ne prouve **pas** que les garde-fous
    ajoutés fonctionnent : les conditions qui les déclenchent sont absentes ici.
    Chacun a été éprouvé en le **forçant à rougir** — c'est la seule preuve qu'on
    ait, et elle est artificielle.

    | Correctif | Ce qui le déclenche | État du clone de dev |
    |---|---|---|
    | Garde-fou racine `body.aod-cd` (`rtl-mobile.js`, `a11y-noms.js`) | une page `/gestion` qui ne rend pas la coque : plugin désactivé, page dépubliée, « accès refusé », 404 | jamais atteint — `/gestion` répond |
    | `--post_status=publish` sur la découverte landing (`run.sh`) | au moins un produit en **brouillon** | **0 brouillon** (vérifié) |
    | Retrait annoncé code 3 sur Turnstile (`commande-cod.js`) | Turnstile **activé** | **inactif** (vérifié) |
    | Cadrage `&s=Recette` des onglets (`abandonnees.js`) | assez de prospects pour **paginer** | boutique trop petite (**30 commandes**) |
    | Absence de `.aod-cd-catdd-btn` = échec (`a11y-clavier.js`) | le bouton disparaît du formulaire produit | rendu inconditionnellement |

    **Conséquence pratique, à retenir avant de crier à la régression** : si l'une de
    ces portes rougit sur une **boutique client** alors qu'elle est verte ici, la
    première hypothèse n'est pas une régression — c'est l'un de ces garde-fous qui
    fait enfin son travail. Lire le motif imprimé par la porte avant de toucher au
    code.

    Corollaire inverse, plus gênant : **le clone de dev ne peut pas servir de
    validation** pour ces cinq correctifs. Pour les exercer pour de vrai, il
    faudrait y créer les conditions (un produit en brouillon, Turnstile activé, une
    boutique peuplée) — ce qui reviendrait à salir le clone pour tester la recette.
    Le bon moment reste le **premier passage sur un vrai clone client**, et il n'a
    pas encore eu lieu.

16. **Phase 16 — les `transition` implicites : soldée, et devenue un cliquet.**
    `transition:.15s` sans propriété vaut `all` : le navigateur anime aussi
    `padding`, `border-radius`, `font-weight` — il repasse par le layout au lieu de
    rester sur le compositeur — et toute propriété ajoutée demain devient animée
    sans que personne l'ait décidé. Le dépôt en comptait **26** : 4 traitées en
    15.6, les **22** autres par la Phase 16. Il n'en reste **aucune**, et la porte
    `run.sh mouvement` refuse désormais toute `transition-property: all`.

    Ce qu'il faut en retenir dépasse le CSS : **une transition tuée par erreur ne
    casse aucune assertion** — elle rend l'interface sèche, et personne ne sait
    pourquoi. C'est pour ça que la conversion a exigé un relevé préalable (16.1) et
    une porte qui déclenche l'état au navigateur (16.3), pas un `sed`.

17. **Phase 17 — la charte ne peut plus se défaire en silence.** Trois acquis
    structurels, tous nés de défauts qu'aucune porte ne signalait :

    - **`--cd-accent-wash`** (`AOD_CD_Customize::wash()`, `WASH_MIX = 0.94`) : les
      surfaces teintées (survol de ligne, sélection, note du gérant) étaient deux
      **jaunes en dur**, hérités de l'accent par défaut — un client au bordeaux
      gardait des lignes jaunes. Dérivé **en PHP** et non en `color-mix()` CSS : la
      garantie « AA pour tout accent » vient d'un test qui balaie le cube en
      appelant **ce** code. L'encre contraignante est `--cd-muted`, pas `--cd-ink`.
    - **Les fantômes.** `--cd-green`, `--cd-bg-2`, `--cd-ink-3` n'avaient jamais été
      déclarées (`--cd-ink-3` rendait 2,58:1 en corps 12) ; le `@keyframes`
      `aod-cd-dd-in` non plus — deux menus se sont ouverts **d'un coup** pendant
      toute leur existence. Une référence non déclarée n'émet aucune erreur : le
      repli prend le relais et le contrat tombe sans bruit.
      `tests/unit/test-charte-surfaces.php` interdit désormais les deux.
    - **La surface flottante** (`statussel` / `csel` / `catdd`) : leur **peau** est
      écrite une fois. Leur **positionnement** reste séparé, et ce n'est pas un
      reliquat — deux menus `fixed` placés par le JS, un panneau `absolute` ancré à
      son parent.

    ⚠️ **Le point de méthode, réutilisable au-delà de cette phase** : la porte
    `contraste` n'auditait que l'état **au repos**. Or le survol d'une ligne exige
    une souris, et la frise de suivi vit dans un panneau qui s'ouvre au clic —
    elles étaient vertes faute d'occasion d'échouer. **Un audit d'état au repos ne
    couvre pas une interface à états** : ce qui n'est pas déclenché n'est pas
    mesuré, et rien ne distingue « vérifié » de « jamais regardé ».

18. 🟢 **La typo de `/gestion` venait d'un CDN — RÉSOLU (Phase 20, le 2026-07-21).**
    *Et c'était le plugin qui l'interdit qui le faisait.*
    `class-aod-cd-dashboard.php:385-387` chargeait **Barlow depuis
    `fonts.googleapis.com`** : deux `preconnect` plus une feuille de styles. Or
    `assets/icons/README.md` justifie l'embarquement des **71 SVG Phosphor** par
    « les conteneurs de ce template n'ont pas d'internet sortant fiable ; un clone
    doit fonctionner le jour de son installation, sans npm, **sans CDN**, sans
    build » — et le §6 ci-dessus revendique « aucun CDN, aucun appel réseau au
    rendu ». C'est vrai **des icônes**. Ça ne l'est pas de la coque qui les rend.

    **Le mode d'échec est celui de la Phase 15, en plus silencieux.** Les emojis
    ont été retirés parce qu'ils n'avaient « pas le même visage sur Windows, macOS
    et Android ». Sans réseau, `font-family` retombe sur `-apple-system, …,
    sans-serif` : exactement le même défaut, sur la même surface — sauf qu'un
    emoji manquant **se voit**, tandis qu'une police de repli laisse l'écran
    parfaitement crédible. Personne ne signale ce qui a l'air normal.

    S'y ajoutent une requête tierce portant l'IP du gérant et le `Referer` à chaque
    page d'un back-office pourtant `noindex,nofollow`, et deux allers-retours
    réseau avant le premier texte peint — sur des connexions algériennes souvent
    médiocres.

    ⚠️ **Pourquoi l'audit de cohérence doc ⇄ code ne l'a pas vu** : il a comparé
    chaque document **au code qu'il décrit**. `icons/README.md` décrit les icônes,
    et les icônes sont conformes. Personne n'a demandé si le **même fichier**
    respectait sa propre règle pour une **autre** ressource. Une règle vérifiée sur
    son exemple canonique paraît tenue partout.

    ✅ **Livré en 20.1** : 5 `woff2` `latin` (113 Ko) + `LICENSE` OFL-1.1 dans
    `assets/fonts/`, `@font-face` en tête de `dashboard.css`, les 3 `<link>`
    retirés. Le cliquet `tests/unit/test-ressources-locales.php` refuse tout **hôte
    externe** — la règle ne nomme aucun domaine, sinon le prochain CDN passerait —
    et **exclut explicitement** ce qui doit sortir : pixels marketing (opt-in),
    façade YouTube (rien avant le clic), liens `wa.me`. Les confondre rendrait le
    test ininterprétable, donc désarmé à terme.

    🚨 **Le contrôle qui compte n'est pas celui qu'on croit.** Retirer le CDN sans
    embarquer les fichiers laisserait les deux premiers groupes d'assertions
    **verts** et la charte cassée : un `@font-face` dont le `src` pointe un fichier
    absent ne produit **aucune erreur**, le navigateur sert le repli. D'où
    l'assertion qui vérifie que chaque `src` existe *et* commence par `wOF2` — une
    page d'erreur enregistrée par `curl` porterait le bon nom de fichier.

    📌 **Une affirmation démentie par la mesure** : « l'`unicode-range` fait qu'un
    écran 100 % arabe ne télécharge rien » — faux. Vérifié en `?aod_lang=ar` : les
    mêmes 4 fichiers qu'en FR, le nom de la boutique et les chiffres restant
    latins. Le gain est **par graisse** (la 500 ne sert nulle part), pas par
    langue. Cf. ROADMAP §C.5 (D9).

---

## 8. Documents liés

- [ROADMAP-AMELIORATIONS.md](ROADMAP-AMELIORATIONS.md) — les chantiers, verdicts de faisabilité et suivi par phases/étapes. **Sa table §C.3 fait foi sur l'état des phases** — ne pas recopier ce compte ici : cette ligne a affiché « Phases 1 à 17 soldées : 80/80 » pendant 26 phases (retiré en 44.3).
- [LANDING-PRODUIT.md](LANDING-PRODUIT.md) — ✅ **spécification** de la page de vente par produit (Phase 6, **livrée**) : la landing remplace le rendu de la fiche produit (même URL, interrupteur par produit — la règle des 3 pages tient), éditeur à blocs dans `/gestion`, et le couple `{ fr, ar }` qui rend enfin le contenu marchand bilingue.
- [UX-GESTION-REFONTE.md](UX-GESTION-REFONTE.md) — spécification UI/UX de `/gestion` (design system, composants, cahier des charges page par page) — **livrée en Phase 5**.
- [../tools/recette/README.md](../tools/recette/README.md) — recette navigateur de `/gestion` : ce qu'elle couvre, et les deux pièges (l'accent ; « accessible au clavier » peut casser la souris).
- [RUNBOOK-ANTI-BOT-COD.md](RUNBOOK-ANTI-BOT-COD.md) — réponse aux floods de fausses commandes.
- [Turnstile.md](Turnstile.md) — configuration Cloudflare Turnstile.
- [DURCISSEMENT-SECURITE.md](DURCISSEMENT-SECURITE.md) — `.htaccess` durci de référence.
- [../TEMPLATE.md](../TEMPLATE.md) — règles d'or et guide du template.
- [../CHECKLIST-THEME-CHILD.md](../CHECKLIST-THEME-CHILD.md) — correctifs à rejouer sur chaque child theme.
