# ROADMAP — Améliorations Template-Full

> **Partie A** : verdict de faisabilité (instantané daté du 2026-07-07, pris sur
> `aod-cod-form` v1.19.1 / `aod-client-dashboard` v0.18.3 — les verdicts restent
> valables, les versions non : la version courante se lit dans l'en-tête `Version:`
> de chaque plugin, qui fait foi. ⚠️ Cette parenthèse a longtemps recopié un couple
> de versions « d'aujourd'hui » — périmé de 10 versions mineures au 2026-07-29.
> Un compteur qu'on ne sait pas tenir se supprime, il ne se corrige pas : c'est la
> conclusion de D25, appliquée ici en 44.3).
> **Partie B** : plan d'implémentation détaillé, découpé en **phases → étapes**
> prêtes à passer en stories BMAD (`bmad-create-story` → `bmad-dev-story`).
> **Partie C** : Definition of Done commune + suivi d'avancement.
>
> **État : les Phases 1 à 46 sont SOLDÉES** (la Phase 46 — conformité du chrome de
> `/gestion`, 8/8 — s'est close le 2026-07-30, la **dette D32** le même jour ; la
> Phase 45 — rail, barre d'action groupée, accès au détail, 5/5 — le 2026-07-29).
> ⚠️ **La Phase 44 est antérieure en numéro, postérieure en histoire** : planifiée
> et close le 2026-07-29 sur sa propre branche (audit A→Z), elle n'a été fusionnée
> dans `main` que le **2026-07-30**, après les Phases 45, 46 et D32 — cf. l'encadré
> « Pourquoi 45 et pas 44 » en tête de la Phase 45. Détail : la Phase 43 — filtres,
> assignation, prix, sidebar — s'est close le 2026-07-28 ; la Phase 42 — assignation
> aux confirmateurs — le 2026-07-26 ; les Phases 40 et 41 le 2026-07-25. Voir la
> table de suivi [§C.3](#c3-suivi-davancement), qui fait foi : elle couvre les
> **184** étapes, phase par phase (comptées dans le tableau, pas annoncées de mémoire). Les Phases 9 à 19 (catégories sur l'accueil,
> conversions serveur Meta/TikTok/Snapchat, commandes abandonnées, Suivi, file
> différée, icônes + charte teintée, transitions nommées, surface flottante,
> dette d'audit, mouvement réduit public) sont décrites en **Partie B**.
>
> ⚠️ Cette ligne a longtemps annoncé « Phases 1 à 8 soldées (44/44) » alors que le
> même document en documentait 17 plus bas. C'est la **première ligne d'état** du
> fichier, donc celle qu'on lit — et elle donnait le chantier pour arrêté à la
> Phase 8.
>
> ⚠️ **Elle a récidivé, et sur les deux nombres à la fois** (relevé le 2026-07-21) :
> « les **17** phases sont soldées — la ROADMAP est **close** », alors que les
> Phases 18 et 19 étaient décrites en Partie B et livrées ; et « les **80** étapes »,
> quand la table en comptait **84**. Les 4 manquantes sont exactement 18.1, 18.2,
> 18.3 et 19.1 : l'en-tête n'a pas suivi l'ajout des deux phases. La consigne
> « comptées dans le tableau, pas annoncées de mémoire » est écrite **sur la ligne
> même** qui l'enfreignait — la formuler ne suffit pas, il faut la rejouer. D'où le
> réflexe : **recompter la table** (`awk '/^\| [0-9]+\.[0-9]+/'`) à chaque étape
> ajoutée, plutôt que d'incrémenter de tête.
>
> ⚠️ **Quatrième récidive** (relevée le 2026-07-28 en soldant la 43.9) : « Phases 1
> à **41** » alors que la 42 était close depuis deux jours, et « **147** étapes »
> quand la table en comptait **167**. Le défaut ne vient pas de la négligence mais
> de la MÉCANIQUE : cette ligne se met à jour à la main, la table se remplit story
> après story, et rien ne les recoupe — `test-doc-derive.php` (32.6) compare les
> en-têtes de la Partie B à la table, et la ligne d'état de `TEMPLATE.md` à la
> table, mais **pas cette ligne-ci**, ni la table au dépôt git. C'est la limite
> connue du cliquet, écrite dans son propre en-tête. Recompté : **168**.
>
> ⚠️ **Cinquième récidive** (relevée le 2026-07-30 en fusionnant la Phase 44 dans la
> branche des Phases 45-46) : « Phases 1 à **44** » et « **171** étapes » quand la
> table en comptait **184**. Elle est instructive parce qu'elle n'est PAS due à
> l'oubli d'une mise à jour : les deux lignes étaient justes **chacune sur sa
> branche** — 44 côté `main`, 46 côté branche de travail — et c'est la fusion qui
> les a rendues fausses toutes les deux. `git merge` a d'ailleurs résolu ce
> paragraphe **sans conflit**, puisqu'un seul des deux côtés l'avait touché : le
> mécanisme même qui garantit qu'on relit un texte n'a rien eu à signaler.
> Corollaire pour la prochaine fusion : **une ligne d'état ne se fusionne pas, elle
> se recalcule** — la table est la seule source, et l'`awk` la seule façon de la
> lire. Recompté : **184**.
>
> ⚠️ **Troisième récidive** (relevée le 2026-07-23 en soldant la 30.6) : « 97 »
> quand la table en comptait déjà **115** avant l'ajout. Recompté : **116**. À
> savoir pour le prochain comptage : l'`awk` ci-dessus, joué sur le fichier
> ENTIER, rend 7 de plus (123) — il attrape aussi la table de la Phase 17 en
> Partie B. Le nombre qui fait foi est celui de la table §C.3 seule.
>
> Pour mémoire, la **Phase 7** (contenu produit
> bilingue FR/AR, périmètre figé le 2026-07-15) s'est close le **2026-07-17** avec
> la **7.6** : recette de clôture **rejouable par clone**
> (`bash tools/recette/run.sh bilingue`) — 10 surfaces acheteur dans les deux
> langues, repli d'un produit non traduit, garde prouvée par une vraie commande
> COD passée site en arabe. **Tout le contenu marchand est bilingue.**
> Cf. verdict A.7, plan Partie B, suivi C.3.
> **Phase 8 (description produit riche — éditeur WYSIWYG) SOLDÉE (4/4)** —
> 8.1 (éditeur TinyMCE), 8.2 (photos), 8.3 (vidéo YouTube façade + fichier local),
> 8.4 (i18n + recette de phase, clôture le 2026-07-18 — recette rejouable
> `tools/recette/run.sh riche`, + 4 correctifs de la revue 8.3 repliés). Cf.
> verdict A.8, plan Partie B, suivi C.3.
>
> **Phase 9 (catégories sur l'accueil — sélection « à la une » + ordre)
> SOLDÉE (3/3), cadrée et close le 2026-07-18 (demande owner).** **9.1** (dashboard
> v0.37.0) : le gérant coche « Afficher sur l'accueil » + ordonne chaque catégorie
> dans `/gestion`. **9.2** (cod-form v1.36.0) : l'accueil affiche **exactement la
> sélection**, dans l'ordre choisi, vides comprises, **plafond de 6 retiré** ; repli
> non-régressif si rien de coché. **9.3** : recette de phase **rejouable par clone**
> (`bash tools/recette/run.sh categories`, incluse dans `public`) — 9 assertions en
> visiteur (ordre, catégorie vide visible, remplacement, AR/RTL, repli). Constat : une catégorie
> créée dans `/gestion` **n'apparaît pas** sur l'accueil tant qu'aucun produit
> publié n'y est classé (l'accueil délègue à `[product_categories … hide_empty="1"
> limit="6"]`, `class-aod-cod-pages.php:180`), alors que la création
> (`ajax_save_category()`, `class-aod-cd-section-products.php:1534`) ne rattache
> aucun produit et ne pose aucun flag d'affichage. **Décision owner** : un réglage
> **« Afficher sur l'accueil » + un ordre par catégorie** dans `/gestion` ; l'accueil
> n'affiche **que** les catégories cochées (vides ou non), dans l'ordre choisi, et
> **le plafond de 6 est retiré**. Repli non-régressif : aucune catégorie cochée ⇒
> comportement actuel (toutes les non-vides). Cf. verdict A.9, plan Partie B, suivi C.3.

> **Phases 10 à 12 — SOLDÉES le 2026-07-18 (8/8 étapes, demande owner).**
> Trois chantiers **indépendants**, chacun une brique métier déjà à moitié présente
> dans le socle, désormais **finie** — avec sa recette rejouable par clone
> (`run.sh capi` / `abandonnees` / `suivi`, incluses dans `tout`).

> **Phase 13 — SOLDÉE le 2026-07-19 (4/4 étapes, demande owner).** A généralisé
> l'envoi **serveur** des conversions (Phase 10, Meta) à **TikTok** (canal n°1 du COD
> algérien, Events API) et **Snapchat** (Conversions API v3), via un **socle mutualisé**.
> **Le piège de la phase, traité :** le pixel **navigateur** TikTok/Snap n'émettait
> **pas** d'identifiant de déduplication (Meta si, `{ eventID }`) — ajouté des deux côtés
> (`event_id` / `client_dedup_id`), sinon chaque achat comptait **deux fois**. Boutons
> « Tester la connexion » par plateforme (13.3) et porte de recette rejouable
> `bash tools/recette/run.sh conversions` (13.4). Cf. verdict A.13, plan Partie B, suivi C.3.
> **Phase 10 — API de Conversion Meta (CAPI, Facebook/Instagram)** : doubler le pixel
> navigateur d'un envoi **serveur-à-serveur** des conversions, dédupliqué par
> `event_id`. L'infra est **déjà pré-câblée** (`event_id` = n° de commande exposé par
> `purchase_payload()`, helper devise `to_reporting()`, hook `aod_cod_order_created`,
> `eventID` rejoué côté JS) : il ne manque que le **token** et l'appel
> `graph.facebook.com`. **Phase 11 — Commandes abandonnées dans `/gestion`** : le
> back-end des paniers abandonnés existe **entièrement** (table `aod_cod_leads`,
> capture au blur du téléphone, `mark_converted()` par token, page wp-admin) — il ne
> manque que la **section `/gestion`** (section dédiée + capability `aod_cap_abandoned`
> pour que les confirmateurs relancent, décision owner). **Phase 12 — Confirmée →
> expédiée « se déplace » vers Suivi commande** : aujourd'hui une commande expédiée
> s'affiche **aux deux endroits** ; une exclusion `meta_query` sur `_aod_ship_tracking`
> la fait **quitter « Commandes » pour ne vivre que dans « Suivi »** (le suivi colis
> existe déjà, cron 2 h). Cf. verdicts A.10/A.11/A.12, plan Partie B, suivi C.3.

---

# Partie A — Verdict de faisabilité

Critères : **validité** (le besoin est-il cohérent avec le socle ?),
**disponibilité** (la donnée/le mécanisme existe-t-il ?), **accessibilité**
(exposable proprement dans `/gestion`, FR/AR + RTL, mobile) et **sécurité**
(contrôle d'accès serveur, traçabilité).

| # | Fonctionnalité | Verdict | Effort |
|---|----------------|---------|--------|
| 1 | Rôles « Confirmateur » + permissions à cocher | ✅ **Faisable** | Moyen–élevé |
| 2 | Accueil `/gestion` : 3 onglets stats + filtres | ✅ **Faisable** (l'onglet Confirmation dépend du #1) | Moyen |
| 3 | Page Stock avec stats par produit | ✅ **Faisable** | Faible–moyen |
| 4 | Récupération automatique des tarifs de livraison | 🟡 **Possible partiellement** (selon transporteur) | Moyen |
| 5 | Refonte UI/UX de `/gestion` | ✅ **Faisable** | Élevé |
| 6 | **Landing page (page de vente) par produit** | ✅ **Faisable** — **exige un mécanisme de contenu bilingue qui n'existe pas** (A.6) | Élevé |
| 7 | **Contenu produit bilingue FR/AR** *(ajouté le 2026-07-15)* | ✅ **Faisable** — l'infra `{ fr, ar }` de la Phase 6 existe ; reste à la généraliser au catalogue (A.7) | Élevé |
| 8 | **Description produit riche (éditeur WYSIWYG)** *(ajouté le 2026-07-17)* | ✅ **Faisable** — `wp_kses_post()` accepte déjà le HTML riche, TinyMCE est embarqué dans WP ; **seule l'UI de saisie manque** (A.8) | Moyen |
| 9 | **Catégories sur l'accueil : sélection « à la une » + ordre** *(ajouté le 2026-07-18)* | ✅ **Faisable** — l'accueil délègue à `[product_categories]` (aucun nouveau moteur) ; il ne manque que 2 term-metas + l'UI `/gestion` (A.9) | Faible–moyen |
| 10 | **API de Conversion Meta (CAPI) — Facebook/Instagram** *(ajouté le 2026-07-18)* | ✅ **Faisable** — infra pré-câblée (`event_id`, `to_reporting()`, hook `aod_cod_order_created`) ; il ne manque que le token + l'appel serveur (A.10) | Faible–moyen |
| 11 | **Commandes abandonnées dans `/gestion`** *(ajouté le 2026-07-18)* | ✅ **Faisable** — back-end complet (`aod_cod_leads`, capture, `mark_converted`, page wp-admin) ; il ne manque que la section `/gestion` (A.11) | Faible–moyen |
| 12 | **Confirmée → expédiée « se déplace » vers Suivi commande** *(ajouté le 2026-07-18)* | ✅ **Faisable** — exclusion `meta_query` sur `_aod_ship_tracking` ; le suivi colis existe déjà (cron 2 h) (A.12) | Faible |
| 13 | **Conversions serveur TikTok + Snapchat (généralisation CAPI)** *(ajouté le 2026-07-18)* | ✅ **Faisable** — réutilise l'infra Phase 10 ; nuance : ajouter l'id de dédup au pixel navigateur TikTok/Snap (A.13) | Faible–moyen |

Rien n'est bloquant : les treize chantiers sont réalisables dans le socle
actuel **sans rien casser** et en restant 100 % générique (règle n°1 du
template — aucune valeur client en dur, tout configurable depuis l'interface).

## A.1 Rôles « Confirmateur » — constat & verdict ✅

- **Constat.** Un seul rôle plat `aod_shop_client` (« Gestion Boutique »,
  `includes/class-aod-cd-roles.php`), copie de `shop_manager` moins une liste
  FORBIDDEN. Aucune permission granulaire par section. Ce rôle possède
  `manage_woocommerce`, qui déverrouille livraison, pixels, WhatsApp **et le
  reset boutique** — un confirmateur ne doit jamais l'avoir. Aucune
  traçabilité : `ajax_order_status()` (`class-aod-cd-dashboard.php:3569`)
  n'enregistre pas **qui** confirme.
- **Disponibilité** : l'API rôles/capabilities WordPress couvre exactement le
  besoin (une capability custom par case à cocher) ; le resync de rôle
  (`maybe_sync_role()`) existe déjà et se réutilise tel quel.
- **Accessibilité** : le routeur de sections est centralisé
  (`$this->sections` :40, `render_section()` :302) → masquer/afficher les
  onglets par capability est direct. i18n FR/AR déjà câblée.
- **Sécurité** : les **18 handlers AJAX** font déjà `check_ajax_referer` +
  `current_user_can(...)` — il suffit d'affiner les checks. Refus côté
  serveur (403), jamais un simple masquage d'UI.

## A.2 Accueil 3 onglets stats — constat & verdict ✅

- **Constat.** `section_stats()` (:1047) : CA, panier moyen, marge
  (`_aod_cost_price`), top-produits, graphe — mais un seul filtre (période).
  Aucune stat de confirmation (personne n'est tracé). **La matière première
  des stats de livraison existe déjà** : le cron de suivi
  (`class-aod-ship-tracking.php`, 2 h) normalise les statuts transporteurs
  (`delivered`, `returned`, `failed`, `in_transit`…) dans les métas
  `_aod_ship_status*`.
- **Disponibilité** : Revenus = extension de l'existant. Livraison =
  agrégation des métas de suivi. Confirmation = disponible **dès que** la
  traçabilité `_aod_confirmed_by` (Phase 1) est posée (les commandes
  antérieures resteront « non attribuées » — normal).
- **Sécurité** : lecture seule ; onglets protégés par capability ; un
  confirmateur ne voit que ses produits affectés et ses propres chiffres.
- **Performance** : ne pas charger toutes les commandes en PHP — passer par la
  table analytique `wp_wc_order_product_lookup` (HPOS-safe) + transients.

## A.3 Page Stock — constat & verdict ✅

- **Constat.** Le stock existe au niveau produit simple (`manage_stock`,
  `stock_quantity`, seuil `low_stock_amount`, décrément auto des packs
  `_aod_pack_items`). ⚠️ Les variantes maison (`_aod_options`) n'ont **pas de
  stock propre** — stock global au produit (refonte séparée si besoin un jour).
- **Disponibilité** : toutes les données existent (stock courant, seuil,
  ventes par période via les lignes de commande).
- **Sécurité** : lecture via `aod_cap_stock` ; modification soumise à
  `edit_products` ; filtrage sur produits affectés pour un confirmateur.

## A.4 Import automatique des tarifs — constat & verdict 🟡

- **Constat.** Tarifs Domicile/Stop-desk 100 % manuels (options
  `aod_cod_delivery_prices` / `aod_cod_free_shipping`). Aucun appel API de
  tarification dans le code ; l'endpoint `/tarifs` ZR Express n'est qu'un
  commentaire (`class-aod-carrier-procolis.php:7`).

| Transporteur | API tarifs | Verdict |
|---|---|---|
| **Yalidine / Yalitec** | Oui — endpoint `fees` | ✅ Implémentable |
| **ZR Express / E-com Delivery (Procolis)** | Oui — `/tarifs` | ✅ Implémentable |
| **EcoTrack + 22 white-labels** | Oui — frais exposés par l'API | ✅ Implémentable |
| **Noest** | Non documentée publiquement | ❌ Saisie manuelle conservée |
| **Maystro** | Inconnue | ❔ À confirmer avec leur support |

- ⚠️ **Intégrité** : le tarif API = **coût facturé au marchand**, pas le prix
  client. → Import en **prévisualisation** (+ marge fixe/%) ; rien n'est écrasé
  sans validation explicite du gérant.

## A.5 Refonte UI/UX de `/gestion` — constat & verdict ✅

- **Constat.** L'espace gérant s'est construit par accumulation (Phases 1→4)
  sans langage de design unifié : pas d'échelle typo/espacement, 4 menus
  déroulants dupliqués, 2 systèmes d'onglets, en-têtes de page incohérents,
  cartes hétérogènes, états (vide/chargement/erreur) inégaux, et **accent jaune
  codé en dur** (non conforme à la règle « branding via Personnalisateur »).
- **Verdict.** ✅ Réalisable sans toucher à la logique métier : on pose un socle
  de **tokens + composants unifiés**, puis on **réorganise le contenu de chaque
  page** dessus. L'accent devient un token dérivé du branding (défaut = jaune
  Laste). Direction validée par l'owner : **élever le thème « Laste »**
  (évolution premium, pas refonte de l'IA).
- 📋 **Spécification détaillée** : [`UX-GESTION-REFONTE.md`](UX-GESTION-REFONTE.md)
  (design system, page par page, plan d'étapes).

## A.6 Landing page (page de vente) par produit — constat & verdict ✅

- **Constat.** La fiche produit est une **fiche catalogue** (gabarit du thème +
  formulaire COD injecté en prio 35). Le COD algérien vend en **page de vente** :
  héro, bénéfices, preuve sociale, FAQ, urgence, et le formulaire à portée de
  pouce en permanence. Le template ne sait pas produire ça.
- **Le vrai obstacle n'est pas le rendu, c'est la langue.** Une landing est du
  **contenu marchand** ; or `gettext` — donc le switcher FR/AR — **ne traduit que
  le code**. C'est exactement pour ça que le contenu des 3 pages vit dans des
  shortcodes. Vérifié dans le code : `_aod_selling_points`, le titre et la
  description d'un produit, les noms de catégories **restent en français quand le
  site passe en arabe**. Seules les variantes sont traduites, par un dictionnaire
  codé en dur de ~60 mots dont le commentaire promet un « repli quand le gérant
  n'a pas saisi de traduction arabe explicite » — **saisie qui n'existe nulle
  part**. Livrer une landing sans régler ça, c'est livrer un site bilingue partout
  **sauf sur la page qui vend**.
- **Verdict.** ✅ Réalisable, à condition d'introduire le **couple `{ fr, ar }`**
  sur chaque champ de contenu, avec repli automatique. Le helper est déjà là et
  n'attend que d'être branché : `AOD_COD_Data::label( $fr, $ar )`
  (`class-aod-cod-data.php:106`, `public static`).
  La landing **remplace le rendu de la page produit** (même URL, interrupteur par
  produit) → **la règle 3 tient** : aucune page WordPress en plus, aucune entrée de
  nav en plus. Éditeur = **blocs maison dans `/gestion`** (catalogue fermé), pas
  Elementor — qui exigerait de rouvrir wp-admin au gérant, ne connaît pas le
  FR/AR, et pèse trop pour l'objectif « < 3 s mobile ».
- 📋 **Spécification détaillée** : [`LANDING-PRODUIT.md`](LANDING-PRODUIT.md)
  (décisions, contrat de données, catalogue de blocs, éditeur, portes de qualité).

## A.7 Contenu produit bilingue FR/AR — constat & verdict ✅

*Ajouté le 2026-07-15. La Phase 6 a prouvé le mécanisme `{ fr, ar }` sur la
landing ; cette phase le généralise au reste du catalogue.*

- **Constat.** Hors page de vente, **tout le contenu marchand reste en français
  quand le site passe en arabe, et rien ne le signale** : la page s'affiche en RTL
  avec du texte français dedans. Vérifié dans le code (cartographie 2026-07-15) —
  cinq contenus, tous stockés **en FR seul**, aucun champ `*_ar` :
  **titre** (`post_title`), **description** (`post_content`),
  **arguments de vente** (`_aod_selling_points`), **libellés/valeurs de variantes**
  (`_aod_options`) et **noms de catégories** (termes `product_cat`). Seules les
  variantes sont « traduites », par un **dictionnaire codé en dur** de ~60 mots
  (`variant_dictionary()`) dont le commentaire promet un repli « quand le gérant a
  saisi une traduction arabe » — **saisie qui n'existe nulle part**.
- **Le point dur, ce n'est pas la saisie, c'est le rendu.** Titre, description et
  noms de catégories sont des champs **cœur WordPress**, affichés par WooCommerce et
  le thème dans des contextes qu'on ne pilote pas (grille Boutique, fil d'ariane,
  `<title>`, `<h1>`). On ne peut pas se contenter d'éditer un template : il faut
  **filtrer la sortie du cœur**, et le faire **strictement côté public** — l'admin,
  les e-mails et les bordereaux transporteur doivent rester en FR (le nom canonique
  sert au matching serveur). C'est le poste le plus coûteux et le plus facile à
  sous-estimer. 10 sites de rendu acheteur recensés.
- **Verdict.** ✅ Réalisable **sans nouvelle dépendance** : l'infrastructure existe
  déjà entièrement (Phase 6) — couple `{ fr, ar }`, repli bidirectionnel
  `AOD_COD_Landing::text()` / `AOD_COD_Data::label()`, UI d'édition à deux champs
  `AOD_CD_Section_Landing::render_pair()`, nettoyage `sanitize_pair()`. Modèle de
  données retenu : **méta parallèle `_aod_*_ar`** (jamais toucher au champ cœur — un
  produit reste valide plugin désactivé) + **swap au rendu par filtres front-only
  gardés** (`is_arabic()` + type `product` + `! is_admin()`). Le dictionnaire de
  variantes devient un **repli** derrière la saisie du gérant.
- **Préalable d'architecture.** La section Produits vit encore dans le monolithe
  `class-aod-cd-dashboard.php` (bloc contigu ~1530 l.). La DoD interdit de l'y
  enrichir : l'**étape 7.0 l'extrait** dans `class-aod-cd-section-products.php`
  **avant** toute nouvelle saisie — c'est le moment justifié pour solder cette dette
  (on va de toute façon éditer lourdement ce code).

## A.8 Description produit riche (éditeur WYSIWYG) — constat & verdict ✅

*Ajouté le 2026-07-17 (demande owner). Donner au gérant une vraie mise en forme
dans la description produit : gras, italique, titres/sous-titres, listes,
alignement, photos et vidéo — depuis `/gestion`, sans wp-admin.*

- **Constat.** Le couple description FR/AR de l'éditeur produit est un simple
  `<textarea>` nu (`AOD_CD_Dashboard::render_pair()`) : aucune barre d'outils, le
  gérant ne peut saisir que du texte plat. Or **le serveur accepte déjà le HTML
  riche** : à l'enregistrement, la description (FR **et** AR) passe par
  `wp_kses_post()` (`ajax_save_product()`) — gras, italique, titres, listes,
  images, liens sont **conservés** — et le rendu public passe par `the_content`
  (donc `wpautop`, et le swap AR de la 7.2 en priorité 9). **Il ne manque que
  l'interface de saisie.**
- **Le point dur : la vidéo.** `wp_kses_post()` **supprime les `<iframe>`** — et
  c'est voulu (pas d'HTML arbitraire en base). L'embed YouTube ne peut donc pas
  être de l'HTML collé : on stocke un **lien**, converti au rendu en **façade**
  cliquable — le pattern déjà prouvé par le bloc vidéo de la landing (6.3 :
  0 iframe en base, 0 requête YouTube avant le clic).
- **Verdict.** ✅ Faisable **sans nouvelle dépendance** : TinyMCE est **embarqué
  dans WordPress** (`wp_enqueue_editor()`, utilisable en front-end donc dans
  `/gestion`), gère le RTL (`directionality`) et est traduit par les packs de
  langue du cœur — rien à charger depuis un CDN (pas d'internet sortant,
  TEMPLATE.md §3). Barre courte et maîtrisée : **la whitelist `wp_kses_post()`
  existante reste la frontière** — la barre n'offre que ce que kses laisse
  passer. Insertion d'images par le mécanisme d'upload **déjà en place** dans le
  formulaire produit (`media_handle_upload`).
- **Périmètre.** La **description seule**. Les « arguments de vente » restent du
  texte simple (découpés ligne à ligne par `split_lines()` +
  `sanitize_text_field()` — le HTML les casserait), et les champs des blocs de
  la page de vente gardent leur éditeur propre (catalogue fermé, Phase 6).

## A.9 Catégories sur l'accueil : sélection « à la une » + ordre — constat & verdict ✅

*Ajouté le 2026-07-18 (demande owner). Le gérant crée une catégorie dans
`/gestion` et s'attend à la voir sur l'accueil, à côté des autres. Aujourd'hui ce
n'est pas garanti.*

- **Constat — la divergence, mesurée.** L'accueil ne fait **ni `get_terms` ni
  `WP_Query`** : il délègue à un shortcode WooCommerce **figé** —
  `do_shortcode( '[product_categories limit="6" columns="3" hide_empty="1"]' )`
  (`plugins-dev/aod-cod-form/includes/class-aod-cod-pages.php:180`). La création de
  catégorie (`ajax_save_category()`,
  `plugins-dev/aod-client-dashboard/includes/class-aod-cd-section-products.php:1534`)
  pose seulement `name`, `parent`, le nom arabe `_aod_cat_name_ar` (7.5) et une
  image optionnelle `thumbnail_id` — **aucun produit rattaché, aucun flag
  d'affichage, aucun ordre**. D'où deux effets, tous deux invisibles pour le
  gérant (son dashboard liste avec `hide_empty => false`, il **voit** la catégorie
  là où l'accueil, lui, la masque) :
  1. **`hide_empty="1"`** → une catégorie **sans produit publié** est **absente**
     de l'accueil. C'est le cas normal d'une catégorie qu'on vient de créer.
  2. **`limit="6"`** → au-delà de 6 catégories, seules **les 6 premières par ordre
     alphabétique** s'affichent ; ni choix ni ordre pour le gérant.
- **Disponibilité.** Rien à inventer : les **term-metas** WordPress couvrent
  exactement le besoin (un flag + un ordre par terme `product_cat`), et le
  shortcode `[product_categories]` accepte déjà `ids="…"` et `hide_empty="0"` —
  l'accueil peut donc rendre **précisément** la sélection, vides comprises, sans
  nouveau moteur de rendu.
- **Accessibilité.** L'UI d'édition des catégories existe
  (`render_categories()` / `render_category_row()`, `class-aod-cd-section-products.php`)
  et suit déjà le design system Phase 5 + i18n FR/AR : y ajouter une case
  « Afficher sur l'accueil » et un champ d'ordre est direct. Ce sont des libellés
  de **chrome** → `__()` + `.po` (pas un couple `{ fr, ar }` : ce n'est pas du
  contenu marchand).
- **Bilinguisme : déjà couvert, à ne pas casser.** Les noms de catégories sont
  traduits en AR côté public par **un seul** filtre `get_term`
  (`AOD_COD_Catalog_Filters`, 7.5) — et `[product_categories]` appelle `get_term()`
  pour chaque terme rendu. Donc **changer la sélection/l'ordre ne touche pas
  l'i18n** : le swap AR continue de s'appliquer. À vérifier en recette, pas à
  reconstruire.
- **Sécurité.** `ajax_save_category()` fait déjà `check_ajax_referer` +
  capability : les deux nouveaux champs sont **bornés côté serveur** (flag = `'1'`
  ou méta supprimée ; ordre = entier). Aucune surface AJAX nouvelle.
- **Verdict.** ✅ Faisable, effort **faible–moyen**, 100 % générique (2 term-metas
  + UI configurable, rien en dur). **Invariant non-régressif à tenir** : un clone
  existant où **aucune** catégorie n'est cochée doit rendre **comme avant** (repli
  sur toutes les catégories non vides) — sinon la mise à jour ferait disparaître
  les catégories de tous les clones déjà en ligne.
- **Périmètre.** L'**accueil** seulement. La page **Boutique** (grille Woo) et le
  fil d'ariane ne changent pas. Pas de refonte du rendu des cartes de catégorie :
  on continue de passer par `[product_categories]` (donc l'i18n `get_term` et le
  style du thème restent acquis) ; seul son jeu d'arguments devient **piloté** par
  la sélection.

## A.10 API de Conversion Meta (CAPI) — Facebook/Instagram — constat & verdict ✅

*Ajouté le 2026-07-18 (demande owner). Doubler le pixel navigateur d'un envoi
serveur-à-serveur des conversions vers Meta — pour ne plus perdre les conversions
que les bloqueurs de pub, iOS et la fin des cookies tiers effacent côté navigateur.*

- **Constat — l'infra est déjà pré-câblée, il ne manque que l'appel.** Le tracking de
  conversion est aujourd'hui **100 % navigateur** (`fbq`/`ttq`/`snaptr`/`gtag`,
  `class-aod-cod-pixels.php:140-222`). Mais tout a été posé **en prévision de la
  CAPI** : `purchase_payload()` (`:332-384`) expose déjà un **`event_id` = numéro de
  commande** (`:367-368`, commentaire « Déduplication Meta pixel ↔ Conversions API »)
  que le JS rejoue dans `fbq('track','Purchase', …, { eventID })`
  (`assets/js/aod-cod-form.js:464-503`) — c'est **exactement** le mécanisme de
  déduplication que Meta attend entre le pixel et la CAPI. La conversion de devise
  (Meta rejette le DZD) est déjà centralisée dans `to_reporting()` (`:96-103`).
  **Ce qui manque** : la clé `aod_cod_pixels['meta_capi_token']`, la méthode d'envoi
  HTTP, et le point de déclenchement serveur.
- **Disponibilité.** Le hook métier `do_action('aod_cod_order_created', $order)`
  (`class-aod-cod-form.php:1239`) est le point d'entrée idéal (l'`$order` est complet,
  déjà en `processing` ; WhatsApp s'y accroche déjà). Le modèle HTTP existe : le
  Turnstile fait exactement le bon `wp_remote_post` (`class-aod-cod-turnstile.php:137-155`,
  timeout court + `is_wp_error()` fail-open + `json_decode`).
- **Accessibilité.** La carte Meta de la section Pixels (`section_marketing()`,
  `class-aod-cd-dashboard.php:2404-2412`) accueille le champ token (en `type=password` :
  c'est un secret) ; sauvegarde par `ajax_save_pixels()` (`:2501-2518`, déjà
  nonce + `manage_woocommerce`) et par la page admin Woo `maybe_save()` (`:401-423`).
- **Sécurité — c'est le poste le plus sensible.** (1) Le **token est un secret** :
  jamais ré-affiché en clair (masqué), jamais loggé (la règle Turnstile). (2) Les
  **données client (PII) doivent être hachées SHA-256** avant l'envoi (téléphone, nom,
  ville — disponibles sur `$order`), jamais en clair. (3) **Fail-open** : Meta
  injoignable ne doit jamais casser la création de commande — l'acheteur passe avant
  le marketing. (4) **Opt-in** : token vide ⇒ aucun appel (comme aujourd'hui, la CAPI
  n'existe pas).
- **Verdict.** ✅ Faisable, effort **faible–moyen**, 100 % générique (le token est un
  réglage, rien en dur). L'`event_id` partagé garantit la déduplication ; sans lui,
  Meta compterait chaque achat **deux fois**.
- **Périmètre.** **Meta (Facebook/Instagram) seul**, événement **Purchase** (celui que
  le pixel émet déjà). TikTok/Snap/Google gardent leur pixel navigateur — leurs API
  serveur sont hors périmètre (chacune est un chantier à part). Le `test_event_code`
  Meta (débogage dans le Gestionnaire d'événements) est un réglage **optionnel** de la
  phase.

## A.11 Commandes abandonnées dans `/gestion` — constat & verdict ✅

*Ajouté le 2026-07-18 (demande owner). Un visiteur remplit le formulaire de commande
— nom, téléphone, wilaya — mais ne clique jamais « Commander ». Aujourd'hui le gérant
ne peut pas les relancer depuis `/gestion`.*

- **Constat — le back-end existe entièrement ; seule la vitrine `/gestion` manque.**
  Le module `AOD_COD_Leads` (`class-aod-cod-leads.php`) capture déjà ces prospects :
  table custom `{prefix}aod_cod_leads` (schéma `:79-97` — token, produit, nom,
  téléphone, wilaya, commune, livraison, qté, **`status` ∈ {`pending`,`converted`}**,
  `order_id`), capture AJAX `aod_cod_lead` (priv + **nopriv**, `:161-185`) déclenchée
  côté JS au **blur du téléphone** (`assets/js/aod-cod-form.js:275-315`, débounce
  900 ms, garde ≥ 8 chiffres), `upsert()` idempotent par token (`:110-129`), et
  **`mark_converted()`** (`:137-152`) appelé par `aod_cod_submit`
  (`class-aod-cod-form.php:1233-1236`) qui fait passer le lead en `converted` dès
  qu'une vraie commande naît avec le même `lead_token`. **Un panier abandonné = un
  lead resté `pending`.** Une page **wp-admin** l'expose déjà (`render_page()`
  `:252-379`, boutons Appeler `tel:` + WhatsApp via `wa_number()` `:244-250`, seuil
  d'affichage « abandonné » à 30 min `:261`).
- **Constat inverse — rien dans `/gestion`.** Aucune section `leads`/`abandoned`/
  `prospect` dans le dashboard : les seules occurrences de « lead » sont le `TRUNCATE`
  du reset boutique (`class-aod-cd-dashboard.php:2960-2970`). Le gérant, qui n'a **pas**
  accès à wp-admin (règle du template), ne voit donc **jamais** ces prospects.
- **Disponibilité & accessibilité.** La section « Commandes » (`section_orders()`
  `:634-759`) est le patron exact à répliquer (onglets à compteurs, `page_head()`,
  toolbar, `render_search_bar()`, tableau `aod-cd-table--cards`, `render_pagination()`).
  Une nouvelle section `abandoned` devient routable dès son ajout à `sections()`
  (`:52-69`) ; la requête vise la table leads `WHERE status='pending'`. Helpers
  réutilisables tels quels : `AOD_COD_Leads::table()`, `wa_number()`,
  `AOD_COD_Data::wilaya_name()`, `wc_get_product()`.
- **Sécurité.** **Décision owner : gérant + confirmateurs** via une **nouvelle
  capability `aod_cap_abandoned`** (le gérant l'a d'office ; attribuable aux
  confirmateurs pour qu'ils relancent — c'est du CA récupérable). Le **périmètre
  produit** du confirmateur (`_aod_assigned_products`) se reproduit sur la colonne
  `product_id` de la table leads (filtrage SQL, patron `scoped_orders()`). La table
  contient des **PII** (téléphone) → lecture réservée, jamais de `nopriv`.
- **Verdict.** ✅ Faisable, effort **faible–moyen**, 100 % générique. Aucune nouvelle
  capture (elle existe), aucune migration de table (schéma en place).
- **Périmètre.** **Affichage + relance** (Appeler/WhatsApp) des `pending`, distinction
  visuelle « abandonné » (> 30 min) / « en cours » (< 30 min), lien vers la commande
  pour un lead `converted`. **Pas** de relance automatique (SMS/WhatsApp auto) — hors
  périmètre : ce serait un chantier à part, avec ses propres questions de consentement.

## A.12 Confirmée → expédiée « se déplace » vers Suivi commande — constat & verdict ✅

*Ajouté le 2026-07-18 (demande owner). Quand une commande confirmée part chez le
transporteur, elle doit **quitter** la liste « Commandes » et n'apparaître plus que
dans « Suivi commande » — là où son colis se suit. Cohérence de flux.*

- **Constat — aujourd'hui elle s'affiche aux DEUX endroits.** Les deux sections
  filtrent sur des critères **orthogonaux et indépendants** : « Commandes »
  (`section_orders()` `:634-759`, requête `:690-702`) filtre **par statut de commande
  seul** — une commande expédiée reste au statut `wc-aod-confirmed` (l'expédition
  n'écrit que des métas, **pas** un changement de statut : `store_result()`
  `class-aod-shipping.php:547-555`) ; « Suivi commande » (`section_tracking()`
  `:1207-1330`) filtre sur **`_aod_ship_tracking != ''`** (`:1232-1238`), non vide dès
  la création du colis. **Résultat : la même commande est visible dans les deux vues.**
  Ce n'est pas un doublon SQL (une seule commande en base), c'est une présentation en
  double.
- **Disponibilité — la modif est chirurgicale.** Il suffit d'**exclure de « Commandes »
  les commandes ayant un `_aod_ship_tracking`** : un `meta_query` `NOT EXISTS / = ''`
  dans `section_orders()` (`:690-702`) **et** dans `count_orders()` (`:1617-1640`) pour
  que les compteurs d'onglets suivent. `wc_get_orders` accepte `meta_query` — pattern
  déjà éprouvé dans ce fichier même (`section_tracking`). **Aucun changement de statut
  WooCommerce**, aucune nouvelle donnée : le colis se suit déjà (cron
  `aod_ship_tracking_sync` 2 h, `apply_tracking()` `class-aod-ship-tracking.php:239-261`).
- **Sécurité & non-régression — deux invariants à tenir.** (1) Une commande en **erreur
  d'envoi** (`_aod_ship_error` **sans** `_aod_ship_tracking`) **reste dans Commandes** —
  c'est voulu : elle est à retraiter, elle n'est pas dans Suivi. (2) L'onglet
  « Confirmées » de Commandes ne montrera plus que les confirmées **pas encore
  expédiées** — c'est exactement l'intention. La colonne « Suivi » du tableau Commandes
  garde son sens pour l'état transitoire (à envoyer / échec).
- **Verdict.** ✅ Faisable, effort **faible**. Le « déplacement » est un effet de bord de
  l'exclusion : rien ne se déplace physiquement, la commande cesse simplement d'être
  listée là où elle n'a plus sa place.
- **Périmètre.** **La bascule de visibilité seulement.** La page « Suivi commande »
  existe et fonctionne (états normalisés, timeline, « Actualiser tout ») — on n'y touche
  que si la recette révèle un manque. Le périmètre confirmateur de Suivi a déjà été
  corrigé (fuite 5.4).

## A.13 Conversions serveur TikTok + Snapchat (généralisation de la CAPI) — constat & verdict ✅

*Ajouté le 2026-07-18 (demande owner). Généralise au canal n°1 du COD algérien
(TikTok) et à Snapchat l'envoi serveur des conversions livré pour Meta (Phase 10).*

- **Constat — l'infra se généralise, avec UNE nuance à ne pas manquer.** La Phase 10 a
  livré la CAPI Meta ; TikTok et Snapchat n'ont aujourd'hui que le **pixel navigateur**
  (`ttq`/`snaptr`, `class-aod-cod-pixels.php:176-205`). Tout le socle se réutilise :
  `event_id` (= n° de commande) exposé par `purchase_payload()`, helpers de hachage
  `hash_pii()`/`hashed_list()`/`normalize_phone()` (Phase 10), `to_reporting()` (devise),
  hook `aod_cod_order_created`, et le pattern `capi_token_from_post()` pour le jeton
  secret. **La nuance, mesurée dans le code :** contrairement à Meta — dont le `fbq`
  navigateur émet déjà `{ eventID }` (`assets/js/aod-cod-form.js:477`) —, les appels
  **TikTok et Snapchat navigateur n'émettent AUCUN identifiant de déduplication**
  (`ttq.track(...)` sans `event_id` `:480`, `snaptr('track','PURCHASE',...)` sans
  `client_dedup_id` `:488`). Une partie du travail est donc d'**ajouter cet id côté
  navigateur** (le même `event_id` serveur), sinon TikTok/Snap comptent chaque achat
  **deux fois**.
- **Disponibilité.** TikTok **Events API** (`POST business-api.tiktok.com/open_api/v1.3/
  event/track/`, en-tête `Access-Token`, événement `CompletePayment`, `event_id` de
  dédup) et Snapchat **Conversions API** (`POST`, identifiants hachés, `client_dedup_id`
  de dédup, événement `PURCHASE`). Même modèle HTTP que le Turnstile / la CAPI Meta
  (`wp_remote_post`, timeout court, fail-open).
- **Accessibilité.** Les cartes **TikTok** et **Snapchat** de la section Pixels
  accueillent chacune un jeton (`type=password`, « vide = inchangé » + case effacer,
  helper `capi_token_from_post()` déjà mutualisé).
- **Sécurité.** Les **quatre garanties** de la Phase 10, à l'identique : jeton secret
  jamais ré-affiché ni loggé ; **PII hachées SHA-256** ; **fail-open** (l'acheteur passe
  avant le marketing) ; **opt-in** (jeton vide ⇒ aucun appel).
- **Verdict.** ✅ Faisable, effort **faible–moyen** (deux plateformes, pattern **prouvé**
  par la Phase 10), 100 % générique. **Occasion d'architecture** : les trois envois
  serveur (Meta livré ; TikTok/Snap à venir) partagent leur ossature (opt-in, hachage,
  `to_reporting`, fail-open) → extraire un **socle commun** plutôt que copier trois fois.
- **Périmètre.** **TikTok + Snapchat**, événement d'achat (`CompletePayment` / `PURCHASE`).
  **Google Ads reste navigateur** : son équivalent serveur (Enhanced Conversions /
  offline, correspondance par gclid) est un mécanisme distinct → chantier à part, hors
  périmètre.

---

## A.14 Sortir les conversions serveur du chemin d'achat — constat & verdict 🔴

**Constat (revue 13.x, 2026-07-19).** Les trois envois serveur (`send_capi_purchase`,
`send_tiktok_purchase`, `send_snapchat_purchase`) sont branchés sur
`aod_cod_order_created` **priorité 20**, donc **synchrones**, **avant**
`wp_send_json_success` (`class-aod-cod-form.php:1239`). Chacun passe par
`capi_http_post()` avec `'timeout' => 8`.

Pire cas mesurable : **24 s** de sablier après « Commander », pour un gérant qui active
les trois régies — c'est-à-dire **exactement la configuration que la Phase 13 promeut**
et que les trois cartes de `/gestion` invitent à faire. Avant la Phase 13, le pire cas
était de 8 s (Meta seul). **La phase n'a pas créé le défaut, elle l'a triplé.**

Le commentaire du socle dit « l'acheteur passe avant le marketing » : c'est vrai du
**résultat** (fail-open, une régie injoignable ne casse jamais la commande), pas de la
**latence**. Sur le mobile DZ que ce template vise, un acheteur qui croit le formulaire
cassé retape ou part — alors que la commande **est déjà créée** côté serveur.

**Verdict : à corriger, et ce n'est pas une retouche.** Déplacer les envois change le
modèle d'exécution, et deux pièges rendent la naïveté coûteuse :

1. 🚨 **Les cookies ne survivent pas au différé.** Les trois payloads lisent
   `$_COOKIE` **au moment de l'envoi** — `_fbp`/`_fbc` (Meta), `_ttp`/`ttclid` (TikTok),
   `_scid`/`_scclick` (Snapchat). Ce sont les identifiants d'appariement **les plus
   forts** dont on dispose. Un envoi rejoué en cron n'a **aucun** cookie : on
   dégraderait la qualité de correspondance en croyant améliorer la latence — et,
   fidèle au reste du dossier, **en silence**. Il faut donc **capturer les cookies en
   méta de commande** au moment de la requête acheteur, et faire lire cette méta par
   l'envoi différé. (L'IP et l'user-agent, eux, viennent déjà de la commande et
   survivent.)
2. **La garde d'envoi unique change de sens.** `_aod_capi_*_sent` est aujourd'hui posée
   **avant** l'appel réseau, ce qui est correct pour un envoi synchrone (un rejeu du
   hook ne redouble pas). En différé il faut distinguer « **mis en file** » de
   « **envoyé** », sinon un envoi planifié mais jamais exécuté (cron mort) est compté
   comme fait et l'achat n'est **jamais** transmis.

**Options d'exécution** (à trancher dans l'étape) : `shutdown` ne suffit pas sous
Apache/mod_php (la réponse n'est pas flushée avant la fin du script) ; un
`wp_schedule_single_event()` est le mécanisme naturel du projet, **mais** la production
tourne avec `DISABLE_WP_CRON` + cron serveur toutes les 15 min
(cf. [CRON-SERVEUR.md](CRON-SERVEUR.md)) → l'achat partirait avec un retard borné à
15 min, ce que les trois régies acceptent sans perte d'attribution (Meta recommande
< 1 h, tolère 7 j). **Compromis à assumer et à documenter**, pas à découvrir en prod.

---

## A.15 Icônes vectorielles + charte teintée de `/gestion` — constat & verdict ✅

**Demande de l'owner (2026-07-19)** : remplacer les emojis de `/gestion` par « des
vrais icônes stylés simples, pas ceux qui ressemblent aux jouets d'enfants », et
adopter les couleurs du **MO Béjaïa** (vert et noir).

**Constat.** Les ~60 icônes de `/gestion` sont des **emojis Unicode** rendus par la
police emoji du **système d'exploitation** : Segoe UI Emoji (Windows) est très
cartoon, Apple Color Emoji est brillant/3D, Noto est différent encore. Le tableau de
bord n'a donc pas le même visage d'une machine à l'autre, et on ne contrôle ni le
trait, ni la couleur, ni l'alignement. Trois traces du problème sont déjà dans le
code : un `filter:grayscale(0)` **orphelin** (`dashboard.css:124`), 7 pastilles KPI
teintées contenant des emojis multicolores qui ne s'accordent avec aucune
(`:1447-1453`), et un mélange d'emojis colorés et de caractères Unicode monochromes
sur le même écran (`class-aod-cd-section-home.php:1348-1351`).

**Verdict : ✅ faisable, et la charte est le vrai sujet.** Le remplacement des icônes
est mécanique (catalogue fermé + `currentColor`). Ce qui ne l'est pas, c'est la
couleur — et la mesure a révélé un piège que l'œil ne voit pas :

> `readable_on()` (`class-aod-cd-customize.php:174`) garantit la lisibilité de
> l'accent sur la sidebar sombre **en l'éclaircissant**. Le jaune par défaut passe à
> 13,65:1 : le mécanisme ne s'est **jamais déclenché**. Un vert de club foncé tombe à
> **2,49:1** — il serait donc **automatiquement délavé** en `#4d9470`, un vert sauge,
> à l'endroit précis où la marque est la plus visible. En silence.

**Solution retenue : teinter la surface sombre au lieu de délaver l'accent.**
`--cd-side` est **dérivé** de l'accent (mélange 85 % vers `#181818`) au lieu d'être
écrit en dur. Balayage de 4 096 accents : pire contraste du texte de nav **7,19:1**,
pire blanc **11,20:1** — très au-dessus d'AA. Pour un vert MOB, la formule produit
`#142d21` : exactement le noir-vert voulu, **sans qu'il soit écrit nulle part**.

C'est la **seule forme acceptable pour un template** : écrire le noir-vert en dur
violerait la règle n°1 de `CLAUDE.md` (chaque futur clone hériterait des couleurs
d'un club de Béjaïa). Avec la dérivation, un seul réglage — l'accent — et chaque
clone obtient une sidebar teintée de **sa** couleur.

**Ce qui est refusé, et pourquoi.** La demande « les icônes aussi doivent avoir les
mêmes couleurs » n'est **pas** appliquée aux 18 KPI : `t-red` = Annulées, `t-amber` =
Échecs de livraison, `t-green` = Livrés. Les peindre en vert de club ferait lire
« Retournés » comme une bonne nouvelle — c'est une **perte d'information**, pas un
arbitrage de style. Les icônes héritent de `currentColor` ; la marque s'exprime par
le fond de la sidebar, la pastille active et le logo.

**Périmètre : `/gestion` uniquement** (décision owner). Conséquence assumée : la
boutique publique ne bougera pas, et le formulaire COD restera sur son vert Shopify
`#008060` en dur (`aod-cod-form.css:8`), sans rapport avec l'accent choisi.

📄 Spécification complète : [`ICONES-ET-CHARTE-GESTION.md`](ICONES-ET-CHARTE-GESTION.md).

## A.16 Convertir une commande abandonnée en commande (depuis `/gestion`) — constat & verdict ✅

*Ajouté le 2026-07-24 (demande owner). La section « Commandes abandonnées » (Phase 11)
n'offre aujourd'hui que **Appeler / WhatsApp** : le confirmateur relance au téléphone
mais ne peut **pas** transformer le prospect en commande depuis `/gestion`. Il veut,
par ligne, un bouton **« Commander »** qui crée la vraie commande — le confirmateur
complète au téléphone les champs que l'acheteur n'a pas remplis (adresse, commune
exacte, variante), pose le **statut**, et « Confirmée » l'envoie au transporteur puis
la fait basculer vers Suivi.*

- **Constat — presque toute la mécanique existe déjà, à un maillon près.** (1) La modale
  « Modifier » d'une commande (`AOD_CD_Section_Orders::render_order_edit_form()` / AJAX
  `aod_cd_order_save_info`) sait déjà écrire **tous** les champs (nom, téléphone, adresse,
  wilaya/commune en cascade, articles + variantes + qté, total forçable), HPOS-safe —
  **pas d'email** (COD = téléphone). (2) Le statut **« Confirmée » (`wc-aod-confirmed`)**
  déclenche déjà l'envoi transporteur (`AOD_Shipping::auto_send()` sur
  `woocommerce_order_status_aod-confirmed`) qui pose `_aod_ship_tracking`. (3) Ce meta
  est le critère **unique** de bascule vers Suivi (Phase 12/A.12). (4)
  `AOD_COD_Leads::mark_converted()` retire le lead de la liste dès qu'une commande naît
  avec son token. **Le seul maillon absent : créer une commande à partir d'un lead.**
  Aujourd'hui **aucune** commande ne naît dans `/gestion` — elles ne naissent que du
  formulaire public (`handle_submit()`, statut initial `processing`).
- **Ne pas dupliquer la construction de commande (décision owner — approche A).** Il
  existe **déjà** deux chemins qui bâtissent/mutent une commande avec prix serveur :
  `handle_submit()` (public) et `ajax_order_save_info()` (édition). Un **troisième**
  serait exactement la dette que ce template combat. → Le bouton crée une commande
  **squelette** minimale à partir du lead (produit racine + qté + téléphone + ce qui est
  présent), marque le lead `converted`, puis **rouvre la modale d'édition existante** —
  le confirmateur complète le reste. Réutilisation maximale, surface neuve minimale.
- **Sécurité (décision owner).** *Voir* la section exige `aod_cap_abandoned` (Phase 11) ;
  **créer** une commande depuis un lead est un acte sur les commandes → exiger **aussi
  `aod_cap_confirm_orders`** (la garde des actions d'édition/statut de la section
  Commandes). Le **périmètre produit** du confirmateur (`assigned_products()`) s'applique
  déjà au lead (colonne `product_id`) **et** à la commande créée (`order_in_scope()`). La
  table leads porte des **PII** → lecture gardée, jamais de `nopriv`.
- **Statut & envoi (décision owner).** Le flux de conversion propose le **statut** ;
  « Confirmée » en un geste → **tentative** d'envoi transporteur. **Bascule vers Suivi
  uniquement si le colis est créé chez le transporteur** (tracking posé) ; en cas
  d'**échec** d'envoi (`_aod_ship_error`, pas de tracking) la commande **reste dans
  Commandes** à retraiter — c'est exactement le comportement de la Phase 12, rien de neuf.
  ⚠️ Sur un clone neuf l'**expédition auto est OFF** : « Confirmée » n'enverra au
  transporteur que si le gérant l'a activée — comportement identique au widget statut
  actuel, à ne pas confondre avec une régression.
- **Anti-doublon.** Deux clics / deux confirmateurs ne doivent pas créer deux commandes :
  garde par le statut du lead (`converted` masque le bouton) **+ verrou serveur avec
  relecture sur instance fraîche** après acquisition (leçon Phase 31.4 : un verrou
  sérialise, il n'annule pas). L'anti-flood téléphone 24 h de `handle_submit()` ne
  s'applique **pas** ici (c'est le confirmateur qui agit, pas l'acheteur).
- **Ce que le lead n'a pas.** Modèle `aod_cod_leads` = `name, phone, wilaya, commune,
  delivery, qty, product_id` — **ni adresse précise, ni email, ni offre/variante, ni
  prix, ni multi-produits**. Ce sont précisément les champs que le confirmateur remplit
  au téléphone dans la modale existante.
- **Verdict.** ✅ Faisable, effort **moyen**, 100 % générique. **Ne viole aucune règle
  d'or** : création de commande **côté gérant**, entièrement serveur — pas le tunnel
  panier/checkout acheteur (règles 3 et 4 intactes). Aucune migration de table.
- **Périmètre.** Bouton « Commander » par ligne `pending` → création d'un squelette →
  modale d'édition existante (avec sélecteur de statut) → « Confirmée » = envoi + bascule
  Suivi **si succès**. **Hors périmètre** : relance auto (SMS/WhatsApp) ; le squelette
  part du **produit racine** du lead (le confirmateur peut **ajouter** des articles dans
  la modale existante).

---

## A.17 Stock par variante — constat & verdict ✅

*Ajouté le 2026-07-25 (demande owner). Un produit a des variantes (taille, couleur…) ;
l'owner veut **un stock par variante**, le stock du produit devenant la **somme** de ses
variantes, et une variante à 0 affichée **en rupture** côté acheteur. Trois décisions
tranchées avec lui avant tout code : granularité **par valeur d'option**, rupture
**grisée + message** non sélectionnable, décrément **à la confirmation** (COD : un abandon
ne doit pas vider le stock).*

- **Constat — les variantes ne sont PAS des variations Woo.** Elles vivent dans la méta
  produit **`_aod_options`** (axes → valeurs `{name, name_ar, price, image_id, hex}`),
  modèle maison rendu par `AOD_COD_Form` (public) et édité par `AOD_CD_Section_Products`.
  Le stock actuel est **au niveau produit** (Woo `manage_stock`), géré par
  `AOD_CD_Section_Stock`. **Aucune matrice de combinaisons** : le cas propre est
  **mono-axe** (un produit à stock ne porte son stock que sur **un** axe).
- **Contrat de données (décision D1).** Chaque **valeur** d'option gagne un champ
  `stock` : **`''` = non géré (illimité, défaut) ≠ `0` = rupture (géré)**. Migration
  **nulle** — une clé absente vaut `''`, donc aucun produit existant ne change.
- **Mono-axe (D1bis).** Contrainte d'intégrité posée **côté serveur** à la
  normalisation : si une valeur d'un axe porte un stock, les autres axes sont forcés à
  `''`. Le décrément reste **générique** (itère les axes) : si D1bis saute un jour, les
  compteurs indépendants marchent déjà.
- **Rupture = pas cosmétique (D2).** Une valeur gérée ≤ 0 est **grisée + « Rupture de
  stock »** et **non sélectionnable**, mais surtout **rejetée côté serveur** dans le
  funnel unique `build_order_lines()` : une sélection forgée ne crée **aucune** commande.
- **Décrément à la confirmation (D3), ré-incrément à l'annulation (D4).** **Mirroir du
  patron pack** `reduce_pack_components_stock()` : hook de statut
  `woocommerce_order_status_aod-confirmed` → décrément ; `cancelled`/`refunded` →
  ré-incrément ; **drapeau d'idempotence sur la commande** (`_aod_variant_stock_reduced`,
  jumeau de `_aod_pack_stock_done`) → une seule fois de chaque. Jamais à la **création**
  (un panier abandonné / un bot ne vide pas le stock).
- **Pas de duplication d'état (leçon Phase 35).** Le stock produit affiché en Stock est la
  **somme calculée** des variantes, **jamais écrite** dans le `_stock` Woo. La somme se
  recalcule à la lecture.
- **Bilinguisme.** Le stock est un **nombre**, pas du contenu marchand → pas de couple
  `{fr,ar}`. Les seuls libellés (« Rupture de stock », « géré par variante »…) sont du
  **chrome** → `__()` + `.po`.
- **Verdict.** ✅ Faisable, effort **moyen**, 100 % générique. **Ne viole aucune règle
  d'or** : le stock se règle depuis `/gestion` (Produits), la rupture est bilingue FR/AR,
  aucune surface acheteur nouvelle (ni panier ni compte). Aucune migration de table.
- **Périmètre.** Champ `stock` par valeur d'option + règle mono-axe → saisie dans Produits
  → somme + détail en Stock (lecture seule) → rupture publique + garde serveur → décrément
  confirmation + ré-incrément annulation → clôture (recette `run.sh variante`, cliquet
  `test-variant-stock.php`, doc). **Hors périmètre** : matrice multi-axes, réservation de
  stock au panier COD (le décrément reste à la confirmation gérant).

---

## A.18 Couleurs de marque par régie (Pixels & Tracking) — constat & verdict ✅

*Ajouté le 2026-07-25 (demande owner). Dans `/gestion → Pixels & Tracking`, l'owner veut que
**chaque carte de régie porte sa couleur de marque** — Snapchat en jaune, Meta (Facebook &
Instagram) en bleu, TikTok en noir, Google en rouge — pour reconnaître la plateforme d'un
coup d'œil. Purement **présentationnel** : aucune donnée, aucune surface serveur, aucune
chaîne neuve.*

- **Constat — la couleur n'est aujourd'hui qu'une pastille douce.** Les 8 cartes
  `.aod-cd-pxcard` (4 pixels + 3 CAPI + Devise) n'affichent la teinte de marque que sur
  l'icône 34 px, en tons doux **mutualisés** (`t-blue`/`t-amber`/`t-red`/`t-slate`). TikTok et
  les CAPI TikTok/Snapchat retombent même sur `t-slate` (gris). Rien ne dit « la carte jaune,
  la carte bleue ».
- **Ce n'est PAS l'accent client (Règle d'or n°1).** Les couleurs de marque des régies
  tierces sont des faits **universels** (Meta est toujours bleu, Snapchat toujours jaune),
  **identiques sur tous les clones** — exactement comme les tons `t-*` déjà en dur, et sans
  aucun rapport avec l'accent du Personnalisateur (`#00A651` sur ce clone). Les inscrire en dur
  ne viole donc pas « rien de spécifique client » : c'est un **catalogue fermé de marques**,
  pas une valeur de client. Cf. [`ICONES-ET-CHARTE-GESTION.md`](ICONES-ET-CHARTE-GESTION.md) §6.4.
- **L'exception que §6.2 réservait déjà.** Le chantier icônes (Phase 15) a POSÉ le principe
  « la couleur de marque apparaît à des **endroits choisis**, pas peinte sur tout » — et refusé
  de peindre les KPI (tonalités **sémantiques** : rouge = Annulées) et la nav (monochrome). Une
  carte de régie est précisément un « endroit choisi » : là, **la couleur EST l'information**
  (le sens de la carte Meta, c'est « Meta »). Colorer ces cartes n'enfreint pas §6, ça
  l'applique.
- **AA non négociable.** Toute glyphe/texte posé SUR une couleur de marque prend une **encre
  calculée** par `AOD_CD_Customize::best_ink()` — la fonction qui garantit déjà ≥ 4,58:1 sur
  tout accent : blanc sur le bleu Meta et le noir TikTok, **noir sur le jaune Snapchat**. Les
  champs du formulaire restent sur **fond blanc** (jamais sur la couleur de marque) → leurs
  libellés/aides gardent leur AA intact. Garanti par extension de `test-contraste.php` (le
  balayage est déjà en place).
- **Le vert « Actif » reste sémantique.** L'état `is-on` (bordure verte + pastille « Actif »)
  ne devient PAS la couleur de marque : on/off est une information **distincte** de l'identité
  de la régie. Marque et statut **coexistent** (même arbitrage que §6.1 sur les KPI) — la
  marque vit sur l'icône + un liseré, le statut sur la bordure/pastille.
- **Devise de reporting = neutre.** Ce n'est pas une régie → pas de marque, reste `t-slate`.
- **Verdict.** ✅ Faisable, effort **faible** (CSS + une classe modificatrice par carte),
  100 % générique. Ne viole aucune règle d'or : rien côté acheteur, aucune surface serveur,
  aucune chaîne i18n neuve (les couleurs ne se traduisent pas). **RTL-safe** (liseré posé en
  `border-inline-start`).
- **Périmètre.** Catalogue fermé {Meta bleu, TikTok noir, Snapchat jaune, Google rouge} + encre
  `best_ink()` → appliqué aux cartes de régie (icône pleine + liseré + teinte d'en-tête douce),
  Devise neutre → clôture (extension `test-contraste.php` qui lit le vrai CSS + `best_ink()`,
  couleurs déclarées dans `test-charte-surfaces.php`, doc).
  **Hors périmètre** : la partie publique (formulaire COD, landing — cf. §6.3, périmètre
  inchangé), les KPI et la nav (§6.1/6.2).

---

## A.19 Assignation des commandes aux confirmateurs (répartition équitable + réassignation) — constat & verdict ✅

*Ajouté le 2026-07-26 (demande owner). Aujourd'hui, une commande est visible par **tout**
confirmateur dont le périmètre produit (`assigned_products()`, Phase 1.5) la couvre — si
plusieurs confirmateurs partagent le même produit (ou si aucun n'a de restriction), ils
voient et peuvent tous agir sur **les mêmes** commandes. L'owner veut qu'une commande
entrante soit **assignée à exactement un confirmateur**, la répartition se faisant
**équitablement**, avec le pouvoir de l'**assigner/réassigner** lui-même à tout moment.*

- **Constat — le périmètre existant est un filtre par PRODUIT, pas une propriété par
  COMMANDE.** `AOD_CD_Roles::assigned_products()` + `scoped_orders()`/`order_in_scope()`
  (`class-aod-cd-section-orders.php:512-644`) répondent à « quels produits ce confirmateur
  a-t-il le droit de voir ? », jamais à « à qui appartient CETTE commande ? ». Deux
  confirmateurs affectés au même produit se marchent dessus : rien n'empêche que l'un
  confirme une commande que l'autre est en train de rappeler.
- **Décision D1 (cumul, tranchée avec l'owner) — les deux périmètres se CUMULENT.** Un
  confirmateur ne voit une commande QUE si (a) elle lui est **assignée** (nouvelle méta
  `_aod_assigned_confirmer`) ET (b) elle reste dans son **périmètre produit** existant. Le
  périmètre produit n'est donc pas retiré — la Team peut continuer à borner un confirmateur
  à une catégorie de produits, **en plus** de ne recevoir qu'une part équitable des
  commandes de cette catégorie. Conséquence directe sur le **tourniquet** (D2) : le vivier de
  confirmateurs éligibles à une commande donnée est déjà filtré par le périmètre produit —
  assigner hors périmètre créerait une commande **invisible pour tous** (le confirmateur ne
  la verrait pas malgré l'assignation, le gérant la croirait traitée).
- **Décision D2 (tranchée avec l'owner) — répartition par ÉQUILIBRAGE DE CHARGE, pas un
  tourniquet cyclique.** À la création, la commande part au confirmateur **éligible** ayant
  actuellement le **moins de commandes actives** assignées (statuts non terminaux : ni
  `completed`, ni `cancelled`, ni `refunded` — même notion que « commande en cours » ailleurs
  dans le dashboard). Un tourniquet cyclique se déséquilibre dès qu'un confirmateur traite
  plus lentement (ou est absent) ; l'équilibrage par charge reste juste dans ce cas, sans
  logique de disponibilité à construire séparément.
- **Décision D3 (tranchée avec l'owner) — un confirmateur inactif ou sans la capability est
  EXCLU du tourniquet.** Le vivier éligible à une commande = confirmateurs (a) actifs
  (`has_cap( AOD_CD_CAP )`, cf. `AOD_CD_Section_Team::render()`), (b) porteurs de
  `aod_cap_confirm_orders`, ET (c) dont le périmètre produit est vide **ou** couvre au moins
  un produit de la commande (D1). Vivier vide (aucun confirmateur éligible — clone neuf sans
  équipe, ou catégorie sans personne d'affecté) ⇒ commande **non assignée**, comme
  aujourd'hui : visible du seul gérant, jamais bloquante côté acheteur (règles 3/4 intactes).
- **Piège trouvé en cadrant la phase — DEUX chemins créent une commande, un seul déclenche
  `aod_cod_order_created`.** Le formulaire public (`AOD_COD_Form::handle_submit()`) le fait ;
  la conversion d'un abandon (`AOD_CD_Section_Abandoned::build_skeleton_order()`, Phase 39)
  **non** — vérifié dans le code, aucun `do_action` équivalent. Un hook unique sur
  `aod_cod_order_created` laisserait donc toute commande née d'une conversion **sans
  assignation**. Traitement **différencié, à dessein** : (1) formulaire public → tourniquet
  D2/D3 ; (2) conversion d'abandon → assignée **directement au confirmateur qui convertit**
  (il a déjà pris possession du prospect en le rappelant — un tourniquet la lui reprendrait
  arbitrairement). Si c'est le **gérant** qui convertit, la commande reste non assignée (le
  gérant n'est pas un confirmateur) — il assigne ensuite manuellement s'il le souhaite (D5).
- **Décision D4 (tranchée avec l'owner) — les commandes historiques (créées avant cette
  phase, jamais assignées) deviennent invisibles aux confirmateurs tant que le gérant ne les
  assigne pas.** Cohérent avec la règle stricte D1 (une commande non assignée n'appartient à
  personne). Conséquence pratique à couvrir (39.6 a déjà posé le patron) : un outil
  d'**assignation groupée** (sélection multiple + « Assigner à… ») pour que le gérant n'ait
  pas à cliquer commande par commande sur un catalogue de plusieurs centaines de commandes —
  cf. 42.5.
- **Décision D5 — pouvoir d'assignation/réassignation, GÉRANT uniquement.** Assigner change
  qui peut agir sur une commande : même famille « action-forte » que `delete_orders` /
  `save_stock` (Phase 21/31.1 — une permission cohérente n'est pas une permission bornée).
  Un confirmateur ne voit ni le sélecteur ni l'AJAX. Réassignable **à tout moment**, quel que
  soit le statut (y compris une commande déjà confirmée/expédiée) : l'assignation est une
  question de **visibilité/routage**, pas un verrou sur l'historique déjà écrit
  (`_aod_confirmed_by/_at`, posé à la première confirmation, n'est **jamais** réécrit — reste
  la trace de qui a réellement confirmé, distincte de qui est assigné **maintenant**).
- **Hors périmètre.** La section « Commandes abandonnées » (leads) garde son périmètre
  produit seul, inchangé — un lead n'est pas une commande, et 39.x a déjà réglé qui peut le
  convertir. Pas de notification (e-mail/WhatsApp) au confirmateur assigné — l'owner ne l'a
  pas demandé ; à cadrer séparément si besoin.
- **Verdict.** ✅ Faisable, effort **moyen**, 100 % générique. Ne viole aucune règle d'or :
  purement interne à `/gestion`, aucune surface acheteur, aucune capability nouvelle (routage
  entre capabilities existantes). Une seule méta de commande ajoutée, aucune migration de
  table.
- **Périmètre.** Méta `_aod_assigned_confirmer` + assignation auto à la création (charge,
  D2/D3) et à la conversion d'abandon (D-conversion) → cumul dans `scoped_orders()`/
  `order_in_scope()` (D1) → sélecteur d'assignation/réassignation gérant (D5) → outil de
  rattrapage groupé (D4) → clôture (i18n, cliquet, porte de recette, docs).

---

## A.20 Commandes / Suivi / Abandonnées : filtres, assignation, prix, sidebar — constat & verdict ✅

*Ajouté le 2026-07-27 (demande owner). Six améliorations distinctes sur les pages
**Commandes**, **Suivi** et **Commandes abandonnées** de `/gestion`, groupées parce
qu'elles touchent les mêmes trois sections et la même coque. Vérifié dans le code
avant tout chiffrage — deux points de la demande ne correspondent pas à ce que le
code fait aujourd'hui, et le changent.*

- **Constat 1 — filtres date/confirmateur : absents des trois pages.** Commandes
  filtre par statut + recherche + « Non assignées » (42.5) ; Suivi filtre par
  transporteur + statut colis ; Abandonnées ne filtre que par recherche nom/téléphone.
  Aucune des trois n'a de filtre période ni de filtre confirmateur — la Phase 42
  (assignation) rend ce dernier enfin exploitable : filtrer « les commandes de X »
  n'avait aucun sens tant que rien ne rattachait une commande à un confirmateur précis.
- **Constat 2 — bouton d'assignation : sur Commandes seulement.** Le sélecteur
  d'assignation/réassignation (42.4, gérant uniquement) existe déjà sur Commandes.
  Rien d'équivalent sur Abandonnées : un prospect n'est aujourd'hui assigné que
  *implicitement*, en conséquence de sa conversion (42.2) — jamais *avant*, par le
  gérant qui voudrait répartir des prospects à rappeler.
- **Constat 3 — traçabilité « assigné par » : n'existe pas.** `_aod_assigned_confirmer`
  (42.1) ne garde que la cible de l'assignation, jamais son auteur. Trois origines
  distinctes écrivent aujourd'hui cette méta sans laisser de trace de qui a agi : le
  tourniquet automatique (42.1), la conversion d'un abandon (42.2, auto-assignée au
  confirmateur qui convertit) et le sélecteur manuel du gérant (42.4). L'owner veut
  distinguer visuellement une assignation **manuelle du gérant** des deux autres, avec
  le libellé littéral « Admin » — jamais le nom du compte gérant précis (plusieurs
  comptes `manage_woocommerce` sont possibles ; le libellé désigne un **rôle**, pas une
  personne, cohérent avec le reste de `/gestion` qui n'expose jamais l'identité d'un
  administrateur au confirmateur).
- **Décision D1 (tranchée avec l'owner) — l'outil d'assignation GROUPÉE peut désormais
  écraser une assignation existante.** Jusqu'ici volontairement un outil de
  *rattrapage* (42.5 : « saute silencieusement toute commande déjà assignée du lot »,
  pour ne jamais écraser en masse par accident). L'owner veut pouvoir réassigner en
  masse. Ce n'est plus le même outil : `ajax_orders_bulk_assign()` doit changer de
  contrat, pas gagner un second mode silencieux — la case « écraser aussi les
  commandes déjà assignées » doit être **explicite** dans l'UI (case à cocher, pas un
  défaut caché), sinon on réintroduit exactement le risque que 42.5 avait écarté
  (une sélection large qui redistribue des commandes déjà prises en charge par un
  confirmateur, sans que le gérant l'ait vu venir).
- **Constat 4 — le montant total est ABSENT de la page Abandonnées, pas erroné.**
  Vérifié : `render_row()` (`class-aod-cd-section-abandoned.php`) n'affiche aujourd'hui
  aucune colonne prix — seulement quand/client/produit/wilaya/livraison/qté. Ce n'est
  donc pas une correction (il n'y a rien à corriger), c'est un **ajout** : une colonne
  montant, calculée en **incluant** les frais de livraison. Bonne nouvelle pour le
  risque : la formule exacte existe déjà et est conçue pour être réutilisée sans
  duplication — `AOD_COD_Form::estimate_lines()` (la même utilisée par le panier COD,
  Phase 35/37, « la formule EXACTE de `handle_submit()`, zéro duplication ») sait déjà
  chiffrer un produit + une quantité + une wilaya + un type de livraison. Un lead porte
  ces quatre informations (`product_id`, `qty`, `wilaya`, `delivery`) : aucune nouvelle
  logique de prix à écrire, seulement un appel et un rendu.
- **Constat 5 — sidebar : pas de débordement géré, pas de collapse.** `.aod-cd-side`
  (`dashboard.css:173`) est en `height:100vh` sans `overflow-y` : un contenu qui dépasse
  l'écran (menu élargi, futur onglet) déborde en silence, sans barre de défilement.
  Aucun mécanisme de repli/dépli n'existe côté **desktop** — la seule bascule
  ouverte/fermée du code actuel est le hamburger **mobile** (`.is-open`, un mécanisme
  différent, à ne pas toucher).
- **Décision D2 (tranchée avec l'owner) — sidebar repliée par défaut sur desktop,
  dépliée au survol souris ET au focus clavier (TAB), refermée quand les deux la
  quittent.** Le hover seul aurait cassé l'accessibilité déjà bâtie phase après phase
  (piège de focus, navigation clavier des Phases 5.8/17) : un utilisateur clavier ou
  lecteur d'écran ne peut pas « survoler ». Le dépli doit donc réagir au **focus** d'un
  lien de nav (`:focus-within`) exactement comme au survol — pas un mécanisme à part,
  le même état visuel, deux déclencheurs. Comportement **desktop uniquement** ; le
  hamburger mobile actuel n'est pas touché (le hover n'a pas de sens sur tactile).
  🚨 Point de vigilance porté en story : `prefers-reduced-motion` doit rester honoré
  (Phase 19) — la bascule replié/déplié est un changement de mise en page utile à
  l'information, pas une animation de confort, donc l'ouverture reste instantanée
  (pas de transition de largeur) quand ce réglage est actif, comme les autres
  bascules d'état du dashboard.
- **Verdict.** ✅ Faisable, effort **moyen** sur l'ensemble (six sous-chantiers de
  taille S/M chacun). Ne viole aucune règle d'or : purement interne à `/gestion`,
  aucune capability nouvelle, une seule méta ajoutée (`_aod_assigned_by`). Le risque
  le plus élevé est UI/a11y (sidebar), pas données — traité en story dédiée, séparée
  des cinq autres pour ne pas bloquer les gains rapides sur un point qui demande plus
  de soin.
- **Périmètre.** Filtres date + confirmateur (Commandes/Suivi/Abandonnées) → bouton
  d'assignation sur Abandonnées (réutilise le sélecteur de 42.4) → traçabilité
  « assigné par » + libellé « Admin » (nouvelle méta, posée aux 4 points d'écriture
  existants) → bulk assign avec écrasement explicite (D1) → colonne montant sur
  Abandonnées via `estimate_lines()` (D-prix) → scrollbar sidebar (correctif isolé,
  bas risque) → collapse/hover/focus sidebar desktop (D2) → clôture (i18n, cliquets,
  porte de recette, docs).

---

## A.21 Chrome de `/gestion` : rail, barre d'action groupée, accès au détail — constat & verdict ✅

*Demande owner du 2026-07-29, en trois points : (1) « les icônes ne sont pas
centrées dans leur cadre de sélection lorsque la sidebar est repliée » ; (2) « la
barre noire qui affiche dedans un bouton supprimer et assignation, j'aime pas
cette barre noire, et elle est cachée lorsqu'un élément n'est pas sélectionné —
je veux que les éléments s'affichent en permanence, je veux que tout soit stylé,
une harmonisation » ; (3) « sur un élément du tableau il y a un numéro id, on
clique dessus et il affiche les détails dans une fenêtre : j'aimerais que ce soit
un bouton juste à côté des deux boutons Appeler / WhatsApp, un bouton qui
s'appelle Détail ».*

**Tous les constats ci-dessous sont MESURÉS au navigateur** (1440 px, gérant
connecté, clone porteur de 30 commandes et 15 prospects), pas lus dans le code.
Le rail se mesure **curseur garé au centre de la barre du haut** (`garerSouris()`,
D29) : le survoler le déplie et rend la mesure fausse.

### Constat 1 — l'icône du rail est décalée de 4 px, et le décalage change de côté en arabe

| Mesure | Valeur |
|---|---|
| Largeur du rail | 64 px |
| Cadre du lien (`.aod-cd-navlink`, c'est lui que peint `.is-active`) | x = 12 → 52, **largeur 40 px, centre 32** |
| Icône (`.aod-cd-i`, 20 px) | x = 26 → 46, **centre 36** |
| **Décalage** | **+4 px vers la fin de ligne** |

Cause : `.aod-cd-nav{padding:16px 12px}` fixe le cadre à 40 px, mais
`.aod-cd-navlink{padding:12px 14px}` place l'icône à **14 px du début** — il en
faudrait 10 pour la centrer. Rien ne centre quoi que ce soit : le libellé reste
dans le flux (`opacity:0`) et pousse l'icône contre le début du cadre. Déplié
(224 px), la question ne se pose pas — c'est un défaut **propre au rail**,
apparu avec lui en 43.8.

🚨 **Le décalage est en `padding-inline-start`, donc il MIROITE** : +4 px à droite
en français, +4 px à gauche en arabe. Un correctif écrit en `left`/`right`
corrigerait une langue et **aggraverait** l'autre.

⛔ **Ce qu'il ne faut surtout pas faire pour corriger** : masquer le libellé en
`display:none` / `visibility:hidden`. C'est l'invariant d'accessibilité de la
43.8, gardé par R4 de `test-sidebar-rail.php` — et il a une seconde raison,
moins citée : le `<span>` porte **le nom accessible du lien** (le SVG est
`aria-hidden`). Le sortir de l'arbre d'accessibilité laisserait 13 liens de
navigation **sans nom**, ce que la porte `noms` refuserait à juste titre.

**Verdict : ✅ faisable, taille S.** Le correctif tient dans la media query
`min-width:781px`, en propriétés logiques.

### Constat 1bis — le rail replié NE TIENT PAS dans l'écran, et sa hauteur dépend d'une langue qu'il n'affiche pas

*Ajouté le 2026-07-29, **après** la livraison de la 45.1, sur retour owner : « la
sidebar n'est pas ajustée entièrement — lorsqu'elle est repliée, sur la droite
des icônes une barre verticale de défilement s'affiche, alors que ce sont les
icônes qui doivent s'adapter à la taille de l'écran ». Mesuré curseur garé
(`garerSouris()`), gérant connecté, 12 liens de navigation visibles.*

| Hauteur de fenêtre | 982 px | 900 px | 768 px | 720 px |
|---|---|---|---|---|
| Débordement du rail replié — **FR** (contenu : **1069 px**) | 87 | 169 | 301 | 349 |
| Débordement du rail replié — **AR** (contenu : **1348 px**) | 366 | 448 | 580 | 628 |

Il déborde **partout** : sur les quatre hauteurs d'écran d'ordinateur portable
testées, dans les deux langues. Ce n'est pas un cas limite d'écran court — c'est
l'état **normal** du rail. La 43.7 avait rendu ce débordement *atteignable*
(`overflow-y:auto`, au lieu d'un contenu muet et inaccessible) ; elle n'a jamais
prétendu le supprimer. Personne n'a ensuite demandé **pourquoi** un rail de
12 icônes réclamait 1069 px de haut.

**Cause — et elle contredit l'intuition.** Le rail replié ne fait que 64 px de
large, mais sa hauteur reste dictée par les **libellés qu'il n'affiche pas**. Le
`<span>` demeure dans le flux en `opacity:0` — c'est obligatoire (invariant R4 de
`test-sidebar-rail.php` ; il porte en outre le nom accessible du lien) — et
`opacity` ne retire **rien** à la hauteur d'une boîte.

| Élément du rail replié (64 px de large) | FR | AR |
|---|---|---|
| `<span>` du lien, jamais lu | 18 px | **31 px** |
| → hauteur du lien qui le contient | 44 px | **55 px** |
| Intitulé de groupe, invisible (× 6) | 33 px | **42 px** |
| « Voir la boutique », invisible — **se replie sur 2 lignes** dans 32 px | 32 px | **56 px** |
| **Hauteur totale du rail** | **1069 px** | **1348 px** |

D'où le constat le plus parlant : **le rail arabe est 279 px plus haut que le
français alors que ni l'un ni l'autre n'affiche un seul caractère.** Une boîte de
texte arabe est plus haute à taille de police égale (31 px contre 18), et un
libellé de deux mots se replie quand on lui donne 32 px de large. La hauteur du
rail est donc pilotée par de la **typographie invisible** — et elle varie avec
une langue dont rien ne se voit.

Ce que le rail paie pour du texte jamais lu : **185 px** d'intitulés de groupe en
FR (**239** en AR), **+11 px par lien** en AR (soit 132 px), **+24 px** sur « Voir
la boutique ». À elles seules ces trois lignes expliquent l'essentiel du
débordement.

🚨 **Et ce débordement défait la 45.1 exactement là où elle vient de corriger.**
Là où le navigateur rend une barre de défilement **classique** (elle prend de la
largeur dans la mise en page, contrairement aux barres en surimpression), le rail
perd cette largeur d'un seul côté : la boîte de contenu passe de 64 px à ~58, et
l'icône que la 45.1 a centrée à 0 px se retrouve décalée de la moitié de la
barre. Autrement dit, le centrage ne tenait que sur les fenêtres assez hautes
pour ne pas défiler. *(Sur le Chromium de la recette, `offsetWidth − clientWidth`
vaut **0** : barres en surimpression. C'est une propriété de la plateforme, pas
une garantie — l'owner, lui, la voit.)*

**Verdict : ✅ faisable, taille S.** Le rail replié doit se voir attribuer sa
**propre géométrie verticale**, réglée sur ce qu'il montre (des icônes) et sur la
hauteur de la fenêtre — au lieu d'hériter du rythme d'une barre de 248 px de
large remplie de texte.

### Constat 2 — la barre noire : la demande RENVERSE une décision prise, et la décision n'était pas gardée

| Mesure | Commandes | Abandonnées |
|---|---|---|
| Conteneur | `#aod-cd-order-bulk` | `#aod-cd-lead-bulk` |
| Fond | `rgb(24,24,24)` = `--cd-ink` | idem |
| Hauteur armée | 52 px | 53 px |
| Au repos | `hidden` → `display:none` | idem |
| Contenu | compteur · sélecteur d'assignation · case « écraser » · **Assigner** · **Supprimer** | compteur · **Supprimer** |

Le masquage n'est pas un oubli : `dashboard.css:1596-1602` raconte qu'avant
correctif la barre s'affichait **en permanence** avec un « Supprimer (0) »
proposé alors que rien n'était sélectionné, et que `.aod-cd-bulkbar[hidden]`
a été ajouté pour ça. La demande owner revient donc sur cette décision.

**Elle est recevable, et voici pourquoi.** Ce qui protégeait l'utilisateur
n'était pas le *masquage*, c'était l'**impossibilité d'agir à vide**. Le masquage
n'en était qu'un moyen — et un moyen coûteux : une barre d'outils qui apparaît et
disparaît fait sauter la page de 52 px à chaque première sélection, et n'annonce
jamais à l'avance ce qu'on pourra faire d'une sélection. On garde donc la
garantie **en changeant de moyen** : les actions restent rendues, mais
`disabled` (+ `aria-disabled`) tant que la sélection est vide.

🚨 **Découverte en instruisant le dossier — la décision de 2026 n'a JAMAIS été
gardée.** Le commentaire annonce, en toutes lettres, un cliquet
`test-charte-surfaces.php` portant la « règle *[hidden] neutralisé* ». Mesuré :
**0 occurrence** de `hidden` dans ce fichier, **0 occurrence** de `bulkbar` dans
tout `tests/`. Ce cliquet n'existe pas. Retirer `[hidden]` aujourd'hui ne
casserait donc **rien** — ce qui est plus grave que l'inverse : un invariant que
personne ne mesure se défait sans un mot. C'est le motif de la Phase 17, et
celui de la « règle du témoin » (§C.2). La phase remplace donc l'invariant *et*
écrit enfin son cliquet.

⚠️ **Conséquence de peau, à ne pas sous-estimer** : trois règles n'existent que
parce que le fond est sombre — `.aod-cd-bulk-ow{color:#fff}`,
`.aod-cd-order-bulk-assign{background:#fff;border-color:#fff}` et le
`.aod-cd-order-bulk-assign-select` blanc. Sur une surface claire, elles
deviennent **blanc sur blanc**. Le changement de fond n'est pas une ligne, c'est
la reprise du bloc.

**Verdict : ✅ faisable, taille M.**

### Constat 3 — le numéro est un bouton déguisé, et il a un jumeau que la demande ne nomme pas

Mesuré : le n° est déjà un `<button class="aod-cd-order-detail">#4</button>`,
souligné en `--cd-yellow` (donc `#00A651` sur ce clone). Trois défauts dans un
seul élément : l'affordance est **cachée** (rien ne dit qu'un numéro s'ouvre),
le nom accessible est **« #4 »** (qui n'annonce aucune action), et le geste est
une **cible de 2 caractères**.

🚨 **Le même motif existe sur « Suivi commande »**
(`class-aod-cd-section-tracking.php:327`, à l'octet près). La demande ne le
nomme pas, mais le mot « harmonisation » l'impose : corriger Commandes seul
créerait deux grammaires de tableau dans la même application.

🚨 **Piège trouvé AVANT d'écrire une ligne — et c'est le vrai risque de
l'étape.** Le rang `.aod-cd-call-actions` n'est rendu **que si la commande porte
un téléphone** (`class-aod-cd-section-orders.php:410`, `if ( $phone )`). Y
glisser « Détail » sans toucher à cette garde **priverait de tout accès au
détail** les commandes sans téléphone — et il en existe : la conversion d'un
abandon crée un squelette qui peut n'avoir aucune ligne d'article (39.5), et une
commande peut être vidée de son téléphone à l'édition. Le rang d'actions doit
donc devenir **inconditionnel**, le téléphone ne conditionnant plus que ses deux
boutons à lui.

**Verdict : ✅ faisable, taille M.**

### Décisions à trancher

| # | Question | Décision retenue | Raison |
|---|---|---|---|
| **D1** | Le n° reste-t-il cliquable en plus du bouton ? | **Non — texte simple.** | Deux chemins vers la même fenêtre, c'est deux choses à apprendre et à tester pour un seul geste. La demande dit « que ce soit un bouton » : on nomme l'action une fois, au bon endroit. Le n° redevient ce qu'il est — un identifiant à lire, pas une commande. |
| **D2** | « Suivi commande » est-il inclus ? | **Oui.** | Même markup, même modale, même geste. L'exclure laisserait la moitié du parcours dans l'ancienne grammaire (Constat 3). |
| **D3** | La barre permanente reste-t-elle au-dessus du tableau ? | **Oui, à sa place actuelle.** | La déplacer (barre flottante, pied de page collant) est un autre chantier, avec ses propres pièges de superposition mobile. La demande porte sur l'apparence et la permanence, pas sur l'emplacement. |
| **D4** | Que devient « Supprimer » quand rien n'est sélectionné ? | **Rendu, `disabled`, compteur `(0)`.** | Conserve la garantie de 2026 (on ne peut pas agir à vide) tout en satisfaisant la demande (les éléments s'affichent en permanence). Le `disabled` est un état **serveur-indépendant** : les gardes de capability des handlers AJAX ne bougent pas d'un octet. |
| **D5** *(Constat 1bis)* | Comment fait-on tenir le rail dans l'écran ? | **On lui donne sa propre géométrie verticale**, réglée sur l'icône et sur la hauteur de fenêtre (`clamp()`), et on retire aux libellés invisibles le droit de peser en hauteur. | Les deux autres pistes ne règlent rien. **Rapetisser les icônes** : l'icône est la *seule* chose lisible dans un rail de 64 px — la rendre plus petite abîme la seule affordance qui reste. **Ne faire défiler que `.aod-cd-nav`** : ça ne retire pas un pixel, ça déplace la barre de défilement. Le vrai gisement, c'est les 185–239 px d'intitulés et la sur-hauteur de 12 boîtes de texte que personne ne voit. |
| **D6** *(Constat 1bis)* | Que deviennent les 6 intitulés de groupe dans le rail replié ? | **Un filet de 1 px**, le texte gardant sa place dans l'arbre d'accessibilité (`height:0` + `overflow:hidden`, **jamais** `display:none`). | Un rail sépare par le vide, pas par le mot — un intitulé illisible à 64 px ne rend aucun service. Mais `display:none` le retirerait de l'arbre d'accessibilité : c'est la même faute que la 43.8 interdit sur les liens, prise par le côté des titres. `height:0` clippe sans effacer. Gain : ~125 px en FR, ~180 px en AR. |
| **D7** *(Constat 1bis)* | Et si le rail ne tient TOUJOURS pas (fenêtre très courte) ? | **La 43.7 reste intacte** (`overflow-y:auto`) ; replié, la barre de défilement ne prend simplement **aucune largeur**. | On ne remplace pas un filet de sécurité par un pari sur la taille des écrans : à 360 px de haut rien ne tiendra jamais, et le contenu doit rester atteignable. 🚨 **Ce qui a été essayé, mesuré et REJETÉ** : `scrollbar-gutter:stable both-edges`, qui réserve la gouttière des deux côtés pour préserver le centre. À la mesure, **20 px pris sur les 64 du rail** → le cadre du lien tombe à 20 px pour une icône de 20, et le retrait de 10 px de la 45.1 (calculé pour un cadre de 40) pousse l'icône **10 px** hors du centre : deux fois et demie le décalage qu'on venait de corriger. Retenu à la place : replié, `scrollbar-width:none`. Molette, pavé tactile et focus clavier défilent toujours, et le seul geste qui donne envie de LIRE le rail — l'y amener — le déplie et rend sa barre stylée. On masque une barre dans l'état où personne ne s'en sert ; on ne supprime pas le défilement. |
| **D8** *(Constat 1bis)* | Le dépliage fait GLISSER les liens verticalement. Acceptable ? | **Oui, et c'est le prix assumé** — mesuré à **120 px** au maximum (FR **et** AR), sur la dernière entrée. | C'est le seul point où la 45.5 dégrade quelque chose, alors il est chiffré plutôt qu'ignoré. Un rail replié qui *tient* est forcément plus court que le même déplié qui *montre ses intitulés de groupe* : la seule façon d'annuler le glissement serait de renoncer à ce que le rail tienne — soit la demande owner elle-même. Trois faits le bornent : (1) **on ne clique jamais un rail replié** — y amener le curseur le déplie, le glissement a donc lieu *avant* que la cible ne soit visée ; (2) il est **ordonné par la profondeur** : les 4 premières entrées (Accueil, Commandes, Abandonnées, Suivi — le travail quotidien) bougent d'au plus 55 px, les grands écarts frappent *Administration*, qu'on visite rarement ; (3) il vient **entièrement des 6 intitulés de groupe**, la hauteur des liens étant désormais identique dans les deux états. ⚠️ Sans le correctif d'interligne, il valait **261 px en arabe** contre 102 en français — c'est *ça* qui n'était pas acceptable, et c'est corrigé. |

- **Périmètre.** Rail (45.1) → barre d'action groupée sur Commandes **et**
  Abandonnées (45.2) → bouton « Détail » sur Commandes **et** Suivi (45.3) →
  clôture i18n / cliquets / portes / docs (45.4) → **le rail tient dans l'écran
  (45.5, ajoutée après retour owner — Constat 1bis)**.
- **Hors périmètre, et pourquoi.** Aucun handler AJAX, aucune capability, aucune
  requête, aucun schéma de données n'est touché : c'est une phase de **chrome**.
  Les défauts de fond relevés par ailleurs sur `/gestion` (4 anneaux de focus
  bâtis sur l'accent, toast sans `role="status"`, tableaux de l'Accueil sans
  `--cards`) ne sont **pas** avalés ici : ils n'ont pas été demandés et ils ont
  leurs propres raisons d'être traités ensemble.

---

## A.22 Conformité du chrome de `/gestion` : les règles écrites que rien ne mesure — constat & verdict ✅

*Analyse demandée par l'owner le 2026-07-30 (« analyse ce projet de A-Z et concentre-toi
sur la partie UI/UX de `/gestion` »), puis exécution déléguée (« je te laisse trancher »).
Tous les chiffres ci-dessous sont **comptés dans le code**, jamais lus dans un document.
Trois des huit points étaient déjà nommés — et explicitement écartés — par le
« hors périmètre » de la Phase 45 ci-dessus ; les cinq autres n'étaient recensés
nulle part.*

**Le fil unique, et c'est lui qui justifie une phase plutôt que huit correctifs.**
Dans six des huit cas, **la bonne décision est déjà écrite dans le fichier, avec sa
raison, appliquée une ou deux fois** — et rien n'empêche les autres occurrences
d'en diverger. Ce n'est pas de la négligence : c'est le motif exact de la Phase 17
(« le reliquat visuel ») et du Constat 2 d'A.21 (*« un invariant que personne ne
mesure se défait sans un mot »*), sur un troisième objet. La phase ne corrige donc
pas huit défauts : elle **transforme six règles de prose en six cliquets**, et les
défauts tombent avec.

### Constat 1 — quatre anneaux de focus sont invisibles avec l'accent PAR DÉFAUT du template

| Fichier | Sélecteur | Écrit |
|---|---|---|
| `dashboard.css:666` | `.aod-cd-statussel-btn:focus-visible` | `outline:2px solid var(--cd-yellow)` |
| `dashboard.css:840-841` | `.aod-cd-assignsel-btn` · `.aod-cd-leadassignsel-btn` | idem |
| `dashboard.css:2025` | `.aod-cd-seg__btn:focus-visible` | `outline:2px solid var(--cd-accent)` |

Le socle du même fichier (`dashboard.css:152-161`) énonce **la règle inverse**, en
toutes lettres : *« L'anneau NE doit PAS dépendre de --cd-accent […] un accent pâle
donnerait un anneau invisible sur fond blanc. »* Avec le défaut `#FFE21E` sur blanc
on est à **~1,15:1** ; SC 1.4.11 en demande **3:1**.

Ce ne sont pas des contrôles secondaires : le sélecteur de statut est le plus
cliqué de Commandes, le segmented control est le filtre principal d'Accueil,
Commandes **et** Abandonnées.

🚨 **Le correctif existe déjà, appliqué exactement une fois.**
`dashboard.css:1791-1798` (étape 45.2) repasse la case « écraser » sur `--cd-focus`
en expliquant précisément ce défaut. La leçon a été écrite la veille ; elle n'a pas
franchi les 1 100 lignes qui la séparent de ses quatre voisins.

🚨 **Pourquoi aucune porte ne l'a vu** : `tools/recette/a11y-contraste.js` contient
**0 occurrence** de `focus` et **0** de `outline`. La porte tourne sur 5 accents
hostiles — elle aurait rougi au premier run si elle regardait les anneaux. Elle ne
mesure que texte/fond. C'est un **angle mort de porte**, pas une porte en panne.

**Verdict : ✅ faisable, taille S** (le correctif) **+ S** (l'angle mort).

### Constat 2 — Stock : le mobile est prêt, l'interrupteur n'est pas mis

`class-aod-cd-section-stock.php:828-834` porte **les 7 `data-label`**, traduits, un
par colonne — c'est-à-dire exactement ce que consomme le patron carte
(`dashboard.css:1296` : `content:attr(data-label)`). Mais `:502` rend
`<table class="aod-cd-table">`, **sans `--cards`**.

À 390 px, un tableau de 7 colonnes **à édition inline** défile donc
horizontalement dans `.aod-cd-tablewrap{overflow-x:auto}` (`:582`). Le travail est
déjà payé ; il manque **un mot**.

🚨 **Et c'est le `overflow-x:auto` qui achète le silence de la porte** :
`rtl-mobile.js` traque le débordement de **page**, que le wrapper absorbe. Un
tableau illisible au pouce est indistinguable, pour elle, d'un tableau parfait.

### Constat 3 — Accueil : 5 tableaux sans `--cards` ni `data-label`

`section-home.php:1209` (top produits), `:1644` (par transporteur), `:1658` (par
wilaya), `:1694`, `:1920`. **0 `data-label`** dans tout le fichier. Contrairement
au Constat 2, c'est du vrai travail. Même angle mort de porte.

### Constat 4 — le toast est muet pour un lecteur d'écran

`dashboard.js:8-18` : un `<div class="aod-cd-toast">` créé, rempli, ajouté au
`body`, retiré après 2 600 ms — **sans `role="status"` ni `aria-live`**. C'est le
canal de retour de **tous** les AJAX de `/gestion`, succès comme erreurs
(SC 4.1.3, AA).

Le motif correct existe pourtant **7 fois** dans le même plugin
(`shipping:302,364`, `marketing:222,256,285`, `whatsapp:112`, `landing:218`). C'est
le helper **partagé** qui en est dépourvu — donc le seul dont le manque se propage
partout.

⚠️ **Piège d'implémentation, à ne pas rater** : poser `role="status"` sur un nœud
**déjà rempli** puis l'insérer n'est pas annoncé de façon fiable. La région live
doit **préexister dans le DOM** et recevoir le texte ensuite. Un correctif qui
ajoute l'attribut au bon endroit sans corriger l'ordre serait vert au grep et muet
à l'oreille.

### Constat 5 — le socle de tokens de la Phase 5 n'a jamais été appliqué (chiffré)

Le diagnostic d'ouverture de la Phase 5 nommait comme **problème n°1** : *« ~10
tailles de police en dur → hiérarchie floue »*. Mesuré aujourd'hui dans
`dashboard.css` :

| | Mesure |
|---|---|
| `font-size` en dur | **153 déclarations**, **20 tailles distinctes** (10 · 10,5 · 11 · 11,5 · 12 · 12,5 · 13 · 13,5 · 14 · 14,5 · 15 · 16 · 17 · 18 · 19 · 20 · 21 · 22 · 26 · 28) |
| `font-size` via token | **23** (13 %) |
| `padding` en dur / via token | **148 / 8** (5 %) |
| Tokens déclarés **jamais utilisés** | `--cd-sp-1`, `--cd-sp-6`, `--cd-sp-8`, `--cd-sp-10`, `--cd-r-lg` |

**Le problème n°1 a doublé après la phase censée le régler.** Illustration :
`body.aod-cd{font-size:14.5px}` (`:149`) alors que `--cd-fs-md:14.5px` est déclaré
vingt lignes plus bas.

Et il y a pire que de la non-adoption — **deux familles de rayons coexistent avec
des valeurs identiques** :

```
--cd-radius:12px      (20 usages)   ==   --cd-r-md:12px    (9 usages)
--cd-radius-sm:8px    (50 usages)   ==   --cd-r-sm:8px     (7 usages)
--cd-r-pill:999px     (3 usages)     +   999px en dur      (6 fois)
```

Deux noms **vivants** pour une même valeur, c'est la dérive garantie : le jour où
l'un bouge, l'autre ne suit pas, et rien ne le dit. C'est le mode d'échec que la
Phase 17 a nommé (`var(--sp-4)` ne déclenche aucune erreur — la déclaration est
simplement ignorée, et le repli passe inaperçu).

### Constat 6 — le cliquet des variables ne regarde que dans un sens

`test-charte-surfaces.php:230-255` vérifie **usage → déclaration** (les
« fantômes » de la 17.4, à raison). Il ne vérifie **jamais** déclaration → usage.
D'où les 5 tokens morts du Constat 5, qui vivent sans un mot.

Or le dépôt applique déjà la règle bidirectionnelle ailleurs, **et l'a écrite** :
`test-icones.php` exige les deux sens — *« catalogue → fichier **et** fichier →
catalogue (aucun SVG orphelin, sinon le dossier grossit en silence à chaque
étape) »*. Le raisonnement est bon, il n'a simplement pas été porté au CSS.

### Constat 7 — cibles tactiles : la barre de la spec n'est pas tenue

`.aod-cd-btn-sm` (`dashboard.css:989` — padding 7/13, corps 12 px,
`line-height:1.2`) fait **~30 px** de haut. C'est le bouton du rang
`Appeler / WhatsApp / Détail / Note`, donc le geste le plus fréquent sur mobile.
`.aod-cd-btn` fait ~37 px. **Aucune media query ne remonte ces hauteurs.**

⚠️ **Soyons exacts sur le niveau d'exigence, sous peine de crier au faux
problème** : SC 2.5.8 (AA, 24×24 px) **est tenu** — ce n'est pas une
non-conformité WCAG. Ce qui n'est pas tenu, c'est la spec du projet :
`UX-GESTION-REFONTE.md` §5.3 (« cibles tactiles ≥ 44 px ») et §2.3 (« 3 hauteurs
32/40/44 »). Aucune des deux hauteurs livrées n'est sur cette échelle.

### Constat 8 — le coût réel du « select unique » abandonné

La spec exigeait 1 composant ; le code en a **5** — `statussel` (52 règles CSS),
`assignsel` (30), `leadassignsel` (30), `csel` (22, généré en JS), `catdd` (18).
*(`filterdd` a bien disparu en 5.4 : il n'en reste qu'un commentaire, `:577`.)*

L'arbitrage documenté est **bon** et n'est pas remis en cause : le positionnement
diffère par nature (deux menus `fixed` placés par le JS, un panneau `absolute`
ancré à son parent). `assignsel` et `leadassignsel` nomment leurs deux sélecteurs
dans **25 règles**.

> ⚠️ **CE CONSTAT A ÉTÉ DÉMENTI PAR LA MESURE, pendant la 46.6 — et il est
> conservé ici avec sa correction plutôt que réécrit.**
>
> Relevé règle par règle : **23 des 25 règles nomment les DEUX jumeaux dans un
> corps UNIQUE** ; les 2 restantes sont légitimement spécifiques (une règle de
> carte mobile propre aux prospects, un `<select>` natif de la barre groupée).
> Autrement dit **il n'existe aucun corps de règle dupliqué** : la peau des
> jumeaux est déjà mutualisée, et l'a toujours été. Ce qui se répète, c'est le
> *nom* dans la liste de sélecteurs — verbeux, mais structurellement incapable de
> divergence, puisqu'un corps partagé ne peut pas dériver.
>
> **Conséquence sur l'argument du coût, qui était faux.** Le Constat 1 affirmait
> que « 3 des 4 anneaux cassés » prouvaient le prix de cette duplication. Les
> trois sélecteurs fautifs étaient bien `statussel`, `assignsel` et
> `leadassignsel` — mais l'anneau n'était écrit que **deux fois** : une pour
> `statussel`, une pour la PAIRE. La duplication réelle n'était donc pas entre les
> jumeaux : elle était entre `statussel` et la famille `assignsel`, deux
> composants **différents** à la peau presque identique.
>
> **Ce qui reste vrai, et qui est le vrai sujet** : fusionner `statussel` avec la
> famille `assignsel` aurait de la valeur — mais c'est un refactor à risque réel
> (`statussel` porte les pastilles sémantiques de statut, `assignsel` non), sans
> commune mesure avec le « merge de peau » que D4 décrivait. Non planifié.

### Décisions à trancher

| # | Question | Décision retenue | Raison |
|---|---|---|---|
| **D1** | Quelle famille de rayons survit ? | **`--cd-r-*`**, et `--cd-radius`/`--cd-radius-sm` **disparaissent** (70 usages renommés). | On ne garde pas un alias : deux noms vivants sont la cause du problème, pas sa solution. `--cd-r-*` est l'échelle **spécifiée**, et la seule complète (elle a `lg` et `pill`, donc elle absorbe aussi les six `999px` en dur). Le renommage est mécanique et **visuellement nul par construction** — les valeurs sont identiques, ce qu'une assertion vérifie AVANT le remplacement. |
| **D2** | Les 4 tokens d'espacement morts : supprimés ou appliqués ? | **Appliqués** quand un littéral exact existe, **supprimés** sinon. | Une échelle à trous (`sp-2,3,4,5` seuls) est pire qu'une échelle non appliquée : elle *invite* à écrire `24px` en dur faute de cran. ⚠️ **Corrigé à l'exécution (46.5)** : cette décision affirmait « `--cd-sp-6/8/10` correspondent à des littéraux réellement présents », **posé sans compter**. Mesuré : vrai de `4px` (10 sites), `24px` (6) et `40px` (1) ; **faux de `32px`, absent de toute propriété d'espacement de la feuille**. Le raisonnement tient, sa prémisse était fausse pour un cran sur quatre — d'où `--cd-sp-8` supprimé, comme `--cd-r-lg` et `--cd-yellow-d` en 46.4. Un cran sans candidat ne comble aucun trou : il en creuse un dans la liste des tokens qu'on croit appliqués. |
| **D3** | Cibles tactiles à 44 px : partout ou mobile seulement ? | **Mobile uniquement** (media query). | La densité desktop est un **atout** sur des listes de 30 lignes qu'on lit à la souris ; l'imposer partout gonflerait chaque tableau de `/gestion` pour résoudre un problème qui n'existe qu'au pouce. La spec dit « mobile-first », pas « mobile-only ». |
| **D4** | Fusionne-t-on `assignsel` et `leadassignsel` ? | **Oui, la PEAU seulement** — comme la 17.6 pour les surfaces flottantes. | Les classes comportementales restent distinctes (leurs binders JS les ciblent séparément, et `section-abandoned.js` doit rester no-op sur Commandes). C'est le même arbitrage qu'en 17.6 : mutualiser la peau, laisser le comportement. |
| **D5** | Le toast : région live permanente ou par message ? | **Une région `role="status"` permanente**, créée à l'init, que `toast()` remplit. | C'est la seule forme fiable (cf. Constat 4). Elle a un second bénéfice : deux toasts successifs ne créent plus deux régions concurrentes. |
| **D6** | Convertit-on la typo au `sed` ? | **Non — par lots homogènes**, comme la Phase 16. | 153 déclarations dont 20 valeurs distinctes, et **seules 6 ont un rung**. Les 14 autres demandent un **arbitrage** (arrondir vers quel rung ?), pas un remplacement. Un `sed` produirait un diff énorme, invérifiable, et figerait 14 décisions non prises. |
| **D7** | Que fait-on des 14 tailles hors échelle ? | **On les rapproche du rung le plus proche**, et tout écart conservé doit porter sa raison en commentaire. | Sinon on rejoue la Phase 5 : une échelle déclarée que personne n'applique. Une valeur hors échelle n'est pas interdite — elle est **motivée ou supprimée**. |

- **Périmètre.** Anneaux de focus + angle mort de la porte (46.1) → tableaux
  mobiles Stock **et** Accueil (46.2) → région live du toast (46.3) → une seule
  famille de rayons + le cliquet bidirectionnel (46.4) → typo et espacement par
  lots (46.5) → cibles tactiles mobiles + peau des jumeaux (46.6) → clôture
  (46.7).
- **Hors périmètre, et pourquoi.** Aucun handler AJAX, aucune capability, aucune
  requête, aucun schéma de données : **phase de chrome**, comme la 45 — c'est son
  critère de non-régression le plus fort. Le **positionnement** des 5 menus
  déroulants n'est pas fusionné (Constat 8, arbitrage 17.6 confirmé). L'`accent
  public` reste ouvert et non planifié (`ICONES-ET-CHARTE-GESTION.md` §6.3).

---

## A.23 Le geste du confirmateur : l'envoi cesse d'être un effet de bord — constat & verdict 🟡

*Dix demandes de l'owner le 2026-08-01, à la suite de l'audit A→Z du même jour.
Les chiffres ci-dessous sont **comptés dans le code**. Le point 6 de la demande
est arrivé vide (« 6- Au niveau. ») : il est **réservé**, pas oublié — cf. Constat 6.*

**Le fil unique, et il est structurel.** Neuf des dix demandes portent sur le même
écran, mais elles ne disent pas « c'est laid » : elles disent que **le confirmateur
ne pilote pas son propre geste**. Aujourd'hui il confirme une commande, et le
colis part — vers un transporteur qu'il n'a pas choisi, sans qu'on lui demande,
pendant qu'il attend l'écran figé. Sa note s'écrit dans un coin de tableau et
finit dans l'historique WooCommerce, où le livreur ne la lira jamais. Ses
statistiques lui montrent les chiffres de la boutique entière. La phase ne
recoud pas dix détails : elle **rend le geste à celui qui le fait**.

🔴 **Et elle solde `D27` sans la corriger.** La dette disait : *« l'envoi
transporteur reste synchrone à la confirmation (plafond ≈ 10 s en régime établi,
≈ 20 s à cache froid) ; c'est un arbitrage produit — retour immédiat du n° de
suivi vs. latence — à cadrer avec l'owner avant d'y toucher. »* La demande 3
**est** cet arbitrage. En sortant l'envoi de la requête de confirmation, la
latence quitte le chemin critique : `D27` ne se répare pas, elle **cesse d'avoir
un objet**. C'est le seul point de cette phase qui touche une dette ouverte.

### Constat 1 — la note du confirmateur n'existe pas ; ce qui existe, c'est l'historique Woo

| Fichier | Écrit |
|---|---|
| `class-aod-cd-section-orders.php:464-467` | colonne « Note » = un bouton par ligne + badge de comptage |
| `class-aod-cd-section-orders.php:1636` | `ajax_order_note()` → `$order->add_order_note( $note, 0, true )` |

`add_order_note()` écrit dans **l'historique de la commande** : une liste
append-only, horodatée, mêlée aux notes système de WooCommerce (« Statut passé
de X à Y »). Ce n'est pas ce que l'owner appelle une note — et surtout, **rien de
cet historique ne part chez le transporteur**.

🔎 **Ce que la demande rend possible, et que personne n'avait vu.** Trois des six
implémentations de transporteur portent **déjà** un champ de note dans leur charge
utile d'envoi — et l'envoient **vide** depuis toujours :

| Transporteur | Champ | Couvre |
|---|---|---|
| EcoTrack — `class-aod-carrier-ecotrack.php:366` | `'remarque' => ''` | **24 des 29** (EcoTrack + 23 white-labels) |
| Noest — `class-aod-carrier-noest.php:109` | `'remarque' => ''` | 1 |
| Maystro — `class-aod-carrier-maystro.php:175` | `'note_to_driver' => ''` | 1 |
| **Yalidine** — `build_payload():319-338` | **aucun** | 1 |
| **Yalitec** (`extends AOD_Carrier_Yalidine`) | **aucun**, par héritage | 1 |
| **E-com Delivery** — `build_payload()` | **aucun** (`'article'` est la liste produits, clippée à 255) | 1 |

Autrement dit **26 transporteurs sur 29 recevront la note sans qu'on ajoute un
seul champ** : la case est déjà dans l'enveloppe, elle part blanche. Pour les
trois autres, notre code n'a nulle part où la mettre.

### Constat 2 — un seul transporteur, pour toute la boutique

`class-aod-shipping.php:204-209` — `auto_settings()` rend `{ enabled, carrier }`,
**une** valeur globale, défaut `'ecotrack'`. Le registre en compte **29**
(`AOD_Shipping::carriers()`, mesuré). Une boutique qui travaille avec deux
sociétés n'a aujourd'hui aucun moyen d'envoyer la commande A chez l'une et la
commande B chez l'autre : il faut changer le réglage global entre deux envois.

Le repli de `auto_send():243-259` le dit à sa manière — *« si un seul
transporteur est configuré, on l'utilise plutôt que d'abandonner »* — c'est le
code qui compense l'absence d'un choix par commande.

### Constat 3 — confirmer une commande, c'est expédier un colis, et personne ne le demande

`class-aod-shipping.php:83` :

```php
add_action( 'woocommerce_order_status_' . self::STATUS, array( $this, 'auto_send' ), 10, 2 );
```

Le passage au statut « Confirmée » déclenche, **dans la même requête HTTP**, la
résolution du bureau stop-desk, un verrou, un ou plusieurs appels réseau au
transporteur et deux écritures. Le chronomètre posé en 32.4 mesure ce que le
confirmateur subit (`:261-298`). Il n'y a **aucune confirmation**, aucun
récapitulatif, et aucun retour en arrière : le colis est créé.

Ce que l'owner demande — un envoi **déclenché au camion**, précédé d'une fenêtre
qui récapitule les commandes sélectionnées et leur destination — n'est pas un
confort : c'est ce qui transforme un effet de bord en acte.

### Constat 4 — la ligne « Client » empile ce qui devrait tenir côte à côte

`class-aod-cd-section-orders.php:423-449` : le nom est un `<b>`, le téléphone un
`<a>` en dessous, puis un rang d'actions (Appeler · WhatsApp · Détail) sur une
troisième ligne. La 45.3 a **bien fait** de sortir le détail du numéro de commande
(« un identifiant se lit, il ne se clique pas ») ; elle l'a posé dans le rang
d'actions, ce qui étire la cellule sur trois hauteurs.

> 🚨 **CORRECTION MESURÉE (2026-08-01, à l'implémentation de la 47.4) — « trois
> hauteurs » n'est vrai qu'au BUREAU, et c'est au pouce que le trafic DZ arrive.**
> Relevé au navigateur sur 20 lignes, `getBoundingClientRect()` :
>
> | Cadrage | Cellule | Contenu dispo. | Ce qu'on voyait |
> |---|---|---|---|
> | FR 1440 | 330 × **105 px** | 298 px | 3 hauteurs — le constat ci-dessus |
> | FR 390 | 354 × **210 px** | **180 px** | **5 hauteurs** — les 3 boutons s'empilent |
> | AR 390 | 354 × **160 px** | **180 px** | **4 hauteurs** |
>
> Cause : sous 780 px, `.aod-cd-table--cards td::before` prend `flex:0 0 40%` —
> **130 px** de libellé (+14 de gouttière) sur 354, il reste **180 px**. Or
> « Appeler » (91) + « WhatsApp » (103) = 194 : ils ne tiennent pas côte à côte et
> passent à la ligne. Pire, **nom (119) + téléphone (69) = 188 > 180** : *même ces
> deux-là seuls* ne tenaient pas. Sans exempter la cellule de la colonne de
> libellé, la demande 4 n'était pas difficile — elle était **impossible**.
>
> Après (47.4, mesuré aux mêmes cadrages) : **98 / 137 / 137 px**, deux rangs
> partout, **0 px** de débordement. La porte `rtl` mesure désormais cette cellule
> aux trois cadrages (60 cellules par passage).

### Constat 5 — la modale de détail se ferme sur un clic à côté, et le travail est perdu

`class-aod-cd-dashboard.php:522` pose `<div class="aod-cd-modal-backdrop" data-close>`,
et `section-orders.js:506` ferme sur **tout** `[data-close]`. Or cette modale n'est
pas un afficheur : elle contient `render_order_edit_form()` — articles, quantités,
adresse, statut, assignation. Un clic à côté pendant la saisie **jette la saisie**,
sans confirmation ni brouillon.

⚠️ **Ce que le correctif ne doit pas casser.** Retirer la fermeture au fond ne doit
pas retirer `Échap` (`section-orders.js:510`) ni la croix : une modale dont on ne
peut plus sortir au clavier est un piège, et la porte `clavier` le verrait.

> 🚨 **CORRECTION (2026-08-01, 47.4) — « la porte `clavier` le verrait » était
> FAUX, et c'est la partie de ce constat qu'il faut retenir.** Vérifié :
> `a11y-clavier.js:140-166` ne teste que **`#aod-cd-team-modal`** — la modale de la
> section Équipe, qui a son propre markup et ses propres `[data-team-close]`.
> `grep` de `backdrop`, `modal-x` ou `data-close` sur `tools/recette/*.js` rendait
> **zéro** occurrence. **Aucune porte du dépôt ne couvrait les chemins de fermeture
> de la modale partagée** : une 47.4 qui aurait désarmé `Échap` en même temps que
> le fond serait partie **verte**. Le filet n'existait pas ; la 47.4 l'a écrit
> (bloc 8 d'`a11y-clavier.js`, plus les deux politiques du fond éprouvées ensemble
> sur `/gestion/tracking` dans `commandes-suivi.js`).
>
> *Invoquer un garde-fou sans vérifier qu'il regarde bien la chose qu'on lui
> confie, c'est se donner une garantie qui n'existe pas.*

### Constat 6 — ABANDONNÉ (demande 6, arrivée vide puis retirée)

Le message de l'owner s'interrompait sur « **6- Au niveau.** ». Interrogé, il a
répondu le 2026-08-01 : « *on l'oublie* ». Le point est donc **clos sans objet**.

La ligne reste ici, et le numéro 6 n'est **pas réattribué** : c'est moins cher que
de laisser une demande numérotée s'évaporer. §C.2 consigne le précédent — les
étapes 17.0 à 17.4 ont vécu sur un plan de session jamais versé, et « la
numérotation au-delà de 17.4 est définitivement perdue ». Un trou qui porte sa
raison n'est pas une dette ; un trou muet, si.

### Constat 7 — le champ Description est un éditeur riche à hauteur fixe

`class-aod-cd-section-products.php:451-465` : `render_pair( … array( 'rows' => 6, 'rich' => true ) )`.
C'est le **seul** site d'appel qui passe `rich` — donc TinyMCE, pas un `<textarea>`
nu. La hauteur ne suit pas le contenu : au-delà de six lignes, le gérant rédige sa
fiche produit dans une lucarne à ascenseur.

⚠️ Le correctif ne peut donc pas être un `rows` plus grand ni un `field-sizing`
CSS : la zone visible est une **iframe** TinyMCE, elle se redimensionne par son
propre mécanisme.

### Constat 8 — aucune borne de taille sur les images

`class-aod-cd-upload.php` — le seul point de téléversement du dépôt — porte une
liste blanche MIME explicite (32.2) et **aucune borne de poids**. Vérifié : le
fichier ne contient pas une seule comparaison de taille. Le seul plafond est
`upload_max_filesize` de l'hébergeur, qui n'est ni le nôtre, ni annoncé, ni le
même d'un clone à l'autre — et dont l'échec se manifeste par un envoi qui
n'aboutit pas, sans message utile.

### Constat 9 — le confirmateur voit les chiffres de la boutique, pas les siens

`class-aod-cd-section-home.php:1900-1913` :

```php
$is_manager = current_user_can( 'manage_woocommerce' );
$fcid       = absint( $filters['confirmateur'] ); // Déjà 0 pour un non-gérant.
if ( $fcid ) { … } else {
    $conf_n = $d['confirmed'];      // ← total de TOUS les confirmateurs
    $delay_sum = $d['delay_sum'];   // ← délai moyen de TOUS
}
```

Pour un confirmateur, `$fcid` vaut **toujours 0** (le filtre est un pouvoir
gérant) : les trois cartes tombent dans le `else` et affichent les agrégats
globaux — « Commandes reçues » (`:1935`) compris. Seul le tableau « Par
confirmateur » est restreint à sa ligne (`:1949-1952`).

🚨 **Et le correctif évident est le mauvais.** `confirmation_data()` ramène
**volontairement** les lignes de tous les confirmateurs — son propre docblock
l'écrit (`:437-440`) — parce que la colonne « Part » (`:1968`) divise par le total
global. Lui appliquer `assignment_scope()` rendrait « Part » égale à 100 % pour
tout le monde, **en silence**. Le défaut est au **rendu**, pas dans la donnée :
c'est la branche `else` qui doit lire *sa* ligne quand l'utilisateur n'est pas
gérant. Quatre lignes, pas un chantier de requête.

### Constat 10 — 13 dialogues du navigateur, alors que la fenêtre maison existe

Compté : **12 `window.confirm()` + 1 `window.alert()`**, sur **7 fichiers** —
`section-abandoned.js` (2), `section-landing.js` (2), `section-orders.js` (2),
`section-products.js` (2), `section-shipping.js` (2), `section-team.js` (2, dont
l'unique `alert`), `section-account.js` (1).

Ils sortent du cadre : pas de charte, pas de RTL, **et leurs boutons « OK /
Annuler » restent dans la langue du navigateur**, pas dans celle du site — un
gérant arabophone lit une question en arabe sous deux boutons anglais. Le
`window.confirm` bloque aussi le fil d'exécution, ce que la modale maison ne fait
pas.

Et la primitive est déjà là, écrite, utilisée ailleurs : `openModal()`
(`dashboard.js:222`) et `textPromptModal()` (`:585`). C'est **exactement** le motif
de la Phase 46 — une bonne décision appliquée à quelques endroits et jamais
propagée aux autres — sur un onzième objet.

### Décisions à trancher

| # | Question | Décision retenue | Raison |
|---|---|---|---|
| **D1** | Forme de la note du confirmateur | **Une zone de texte unique** par commande, dans le volet « modifier la commande » de la modale — pas un fil horodaté. | Arbitrage owner (2026-08-01). La note a une **destination** : elle part chez le transporteur avec le colis. Un fil de discussion n'a pas de forme transmissible ; une zone unique, si. L'historique Woo reste intact à côté, pour ce qu'il sait faire. |
| **D2** | Qui choisit le transporteur d'une commande | **Gérant ET confirmateur.** | Arbitrage owner. Cohérent avec la demande 3 : c'est le confirmateur qui déclenche l'envoi, il ne peut pas déclencher un envoi dont il ne choisit pas la destination. ⚠️ S'écarte du patron de l'assignation (43.x, gérant strict) — **et c'est voulu** : assigner, c'est répartir du travail ; choisir un transporteur, c'est faire le travail. |
| **D3** | Note chez Yalidine / Yalitec / E-com Delivery, qui n'ont pas le champ | **Le dire à l'écran** : la fenêtre de confirmation d'envoi prévient que ce transporteur ne recevra pas la note. | Arbitrage owner. Les deux autres options étaient pires : coller la note dans le champ « article » la mélangerait au contenu du colis et la tronquerait (255 car. chez E-com Delivery) ; ne rien dire ferait croire au confirmateur qu'il a transmis une consigne que le livreur ne verra jamais — **c'est la définition du défaut muet que ce dépôt chasse depuis la Phase 26**. |
| **D4** | Le « compteur d'envoi » | **Un récapitulatif de lot**, pas un compteur persistant : N commandes cochées → « Envoyer » → fenêtre listant les commandes, le transporteur visé et leur nombre. Chaque confirmateur voit **son** lot. | Reformulé avec l'owner. Aucun modèle de données n'est nécessaire : le « compteur » est le cardinal de la sélection courante. Inventer un cumul persistant aurait ajouté une table et une remise à zéro que personne n'a demandées. |
| **D5** | Périmètre des stats confirmateur | **Correction au RENDU**, `confirmation_data()` intouchée. | Cf. Constat 9 : scoper la donnée casse « Part » sans un mot. |
| **D6** | Borne de taille des images | **5 Mo, contrôlés côté SERVEUR**, le contrôle JS n'étant qu'un confort. | Arbitrage owner sur la valeur. Un contrôle client seul se contourne en postant directement — c'est la leçon des `accept="image/*"` déjà écrite dans `class-aod-cd-upload.php` (« la vraie barrière est ici, pas dans le HTML »). |

- **Périmètre.** Transporteur par commande (47.1) → l'envoi devient un geste
  (47.2) → la note du confirmateur (47.3) → la ligne et la modale (47.4) → les
  stats du confirmateur (47.5) → les formulaires Produits (47.6) → les 13
  dialogues natifs (47.7) → **réservé au point 6** (47.8) → clôture (47.9).
- **Hors périmètre, et pourquoi.** Le parcours **acheteur** n'est pas touché : ni
  le formulaire COD, ni le panier, ni les 3 pages publiques. Aucune capability
  nouvelle n'est créée (D2 réutilise `aod_cap_confirm_orders`). Le **suivi** des
  colis (cron 2 h) et la normalisation des statuts transporteurs restent tels
  quels. Les dettes `D10`, `D33` et `A.4` restent ouvertes — elles ne croisent
  aucune de ces dix demandes. *(Périmètre de la Phase 47, écrit avant elle et
  laissé tel quel. `D33` a été **soldée depuis, le 2026-08-01**, hors phase — §C.5
  fait foi sur l'état des dettes, pas ce paragraphe de cadrage.)*

---

## A.24 L'Accueil du confirmateur : « Commandes reçues » est le seul chiffre qui parle de la boutique — constat & verdict 🟡

*Constat de l'owner le 2026-08-02, sur un compte réel : « Bilal OUAZENE a reçu
12 commandes, l'onglet Confirmation en affiche 33 ». Les chiffres ci-dessous sont
**mesurés sur le clone**, jamais déduits de la prose.*

**Le chiffre est juste, et c'est exactement pour ça qu'il est coûteux.** Un
nombre faux se corrige ; un nombre exact sous un libellé qui ne dit pas son
périmètre se **croit**, et il oriente la lecture de tout l'écran. C'est le mode
d'échec que ce dépôt traque depuis la Phase 17 — le défaut qui ne casse rien, et
qu'on lit au lieu de le voir.

**Mesure (clone de dev, 2026-08-02, période « semaine ») :**

| | |
|---|---|
| Commandes créées sur la période (toute la boutique) | **33** |
| assignées à Bilal / Amina / Houssam (`_aod_assigned_confirmer`) | **12 / 11 / 10** |
| confirmées **par** Bilal (`_aod_confirmed_by`) | **0** |
| périmètre produits de Bilal (`_aod_products_limited`) | aucun — il voit tout le catalogue |
| ce que son écran affiche | Reçues **33** · Confirmées **0 (0,0 %)** · Délai **—** |

### Constat 1 — la carte n'est pas restreinte, et c'est une DÉCISION, pas un oubli

`class-aod-cd-section-home.php:1977` construit « Commandes reçues » avec
`$d['total']`, tandis que les deux cartes suivantes lisent `$conf_n` /
`$delay_sum`, eux gouvernés par `$row_uid` (`:1943`) — donc restreints à sa
ligne depuis la 47.5. Et `confirmation_data()` (`:1833`) ne passe volontairement
**pas** `assignment_scope()` : seul le périmètre *produit* entre dans l'agrégat.

C'est écrit, gardé, et assumé : `tests/unit/test-stats-confirmateur.php` porte
l'assertion « **D-3** : « Commandes reçues » reste le total de la boutique même
en confirmateur ». Toute évolution devra la faire évoluer **avec sa raison**, pas
la contourner.

### Constat 2 — c'est le SEUL chiffre de tout son `/gestion` qui parle de la boutique

Partout ailleurs, le confirmateur est restreint à son assignation, **côté SQL** :
liste Commandes et ses compteurs (`scoped_orders()`), Suivi (qui en hérite),
Commandes abandonnées, et les onglets Revenus/Livraison de l'Accueil via
`assignment_scope()` (`:583`, `:1382`). Un seul nombre échappe à cette règle, et
c'est le premier qu'il lit en arrivant.

🚨 **La cohérence d'un écran ne se juge pas carte par carte, mais contre le
reste du produit.** Pris isolément, « 33 » est défendable. Posé au milieu d'une
interface où **tout** le reste répond « 12 », il ne se lit pas comme un total de
boutique : il se lit comme une contradiction.

### Constat 3 — la raison écrite couvre le DÉNOMINATEUR, pas le COMPTEUR

L'argument de la 47.5 (`:2024-2032`) est solide et doit être conservé : scoper le
dénominateur du taux reviendrait à diviser un numérateur **figé**
(`_aod_confirmed_by`, garanti jamais réécrit) par un dénominateur **mobile**
(`_aod_assigned_confirmer`, qui bouge à chaque réassignation). Une commande
confirmée par A puis réassignée à B donnerait à A « confirmées 1 / reçues 0 » —
division par zéro — et à B « 0 / 1 » pour une commande qu'il n'a jamais vue.

Mais cet argument porte sur le **ratio**. Il ne dit rien d'un **compteur**
« assignées à moi », qui est un état présent parfaitement défini et n'entre dans
aucune division. La raison a été écrite pour un usage, puis appliquée à un
second qu'elle ne couvre pas.

### Constat 4 — la note existe, et elle arrive trop tard dans la page

`:2034` rend bien au non-gérant : *« Le pourcentage les rapporte aux commandes
reçues par la boutique — pas seulement aux vôtres »*. Elle est exacte, elle a
été écrite pour ce malentendu précis — et elle est imprimée **sous le tableau
« Par confirmateur »**, plusieurs écrans plus bas que la carte qui le provoque.
Sur mobile (le poste de travail réel d'un confirmateur), elle est hors du premier
écran.

### Constat 5 — sur un clone sans confirmation tracée, l'écran se lit à l'envers

Bilal a **0** confirmation tracée ; les 32 autres commandes sont « non
attribuées » et une est au compte `admin`. Son écran affiche donc
« Reçues 33 · Confirmées 0 (0,0 %) ». Chaque nombre est exact, l'ensemble se lit
« 33 commandes sont arrivées et je n'en ai traité aucune ».

⚠️ **C'est un artefact des données semées sur ce clone, pas un défaut de code** —
et c'est justement pourquoi il faut le consigner : c'est la configuration où le
défaut de lecture est **maximal**, et c'est celle d'un clone client **le premier
jour**, avant toute confirmation.

### Ce qu'il ne faut PAS faire

🔴 **Restreindre `$d['total']` à l'assignation et garder le taux dessus.** C'est
le piège du Constat 3, documenté à l'avance, et il produirait une division par
zéro sur un cas courant (réassignation après confirmation). La carte « Part » du
tableau se calcule sur `$d['confirmed']` (`:2007`) pour la même raison.

🔴 **Déplacer la restriction dans `confirmation_data()`.** L'agrégat est **mis en
cache** avec une clé qui n'intègre **ni le rôle ni le lecteur** (`:1790`), à
dessein : la donnée en cache reste complète, la visibilité se décide **au
rendu**. Un agrégat qui dépendrait du lecteur exigerait une clé par utilisateur —
et deux lecteurs pourraient se servir la vue l'un de l'autre le jour où la clé
serait oubliée.

### Décisions à trancher

- **D-1 — le libellé.** « Commandes reçues » → dire son périmètre pour le
  non-gérant. *Recommandé : oui.* Une chaîne, aucune logique, corrige le
  malentendu à sa source.
- **D-2 — la carte « assignées à moi ».** L'ajouter comme **4ᵉ carte**, sans
  ratio, ou s'en tenir à D-1 ? *Recommandé : l'ajouter* — c'est la question que
  le confirmateur se pose réellement, et le gérant a déjà son équivalent en
  filtrant. Reste à arbitrer si elle s'affiche aussi au gérant qui filtre
  (`?confirmateur=X`), où elle se lirait « assignées à ce confirmateur ».
- **D-3 — la place de la note.** La remonter au niveau des cartes, ou la laisser
  sous le tableau ? *Recommandé : au niveau des cartes*, où le malentendu naît.
- **D-4 — le taux.** Inchangé (dénominateur boutique). *Recommandé : oui*, cf.
  Constat 3 — c'est la garantie que D-1/D-2 servent à rendre lisible, pas à
  remplacer.

**Verdict 🟡 — faisable, et c'est du CHROME.** Aucun handler AJAX, aucune
capability, aucun schéma de données, aucune requête nouvelle : le compteur de
D-2 se calcule dans la boucle qui parcourt déjà chaque commande de la période.
Le critère de non-régression est double et non négociable : **le rendu du gérant
ne bouge pas d'un pixel**, et **le taux comme la colonne « Part » gardent leur
dénominateur boutique**.

---

# Partie B — Plan d'implémentation par phases

> Chaque **étape** ci-dessous = une story BMAD autonome (livrable, testable,
> committable seule). Les étapes d'une phase se font **dans l'ordre** ; la
> [DoD commune](#c2-definition-of-done-commune-toute-étape) s'applique à chacune.
>
> **Règle d'architecture** (dette connue : `class-aod-cd-dashboard.php` fait
> déjà 4 448 lignes) : toute **nouvelle** section (Équipe, Accueil-stats,
> Stock) est écrite dans **sa propre classe** (`includes/class-aod-cd-section-*.php`)
> branchée sur le routeur — on n'empile plus dans le monolithe.

## Phase 1 — Rôles « Confirmateur » + traçabilité 🔴 prioritaire

*Tout le reste s'appuie dessus. Dépendances internes : 1.1 est indépendante et
se livre en premier (les données de confirmation s'accumulent pendant qu'on
développe la suite) ; 1.3 dépend de 1.2 ; 1.4 dépend de 1.2 ; 1.5 dépend de 1.4.*

### Étape 1.1 — Traçabilité de confirmation (S)

- **But** : savoir **qui** a confirmé **quand** — prérequis des stats de
  confirmation (Phase 2.4).
- **Où** : `ajax_order_status()` (`class-aod-cd-dashboard.php:3569`).
- **Tâches** : au passage vers `wc-aod-confirmed`, poser les métas HPOS
  `_aod_confirmed_by` (user id) + `_aod_confirmed_at` (timestamp MySQL, fuseau
  du site) via `$order->update_meta_data()` ; note de commande nominative
  (« Confirmée par {display_name} ») ; ne rien poser sur les autres statuts ;
  ne pas écraser si déjà posé (re-confirmation).
- **Acceptation** : confirmer une commande depuis `/gestion` écrit les 2 métas
  (vérif : `wp eval '$o=wc_get_order(ID); echo $o->get_meta("_aod_confirmed_by");'`) ;
  la note apparaît dans le détail de commande ; un statut non-confirmé n'écrit rien.

### Étape 1.2 — Rôle `aod_confirmateur` + capabilities custom (M)

- **But** : le socle permissions. Une capability par case à cocher.
- **Où** : `includes/class-aod-cd-roles.php`, constantes dans
  `aod-client-dashboard.php` (à côté de `AOD_CD_ROLE`/`AOD_CD_CAP`, :27-29).
- **Tâches** :
  - Nouveau rôle `aod_confirmateur` (libellé i18n « Confirmateur ») : `read` +
    `aod_use_dashboard` **uniquement**. Jamais `manage_woocommerce`, jamais
    `delete_*`.
  - 6 capabilities custom (attribuées **par utilisateur** via
    `WP_User::add_cap()`, pas au rôle) :
    | Case à cocher | Capability |
    |---|---|
    | Confirmation de commandes | `aod_cap_confirm_orders` |
    | Suivi de commande | `aod_cap_tracking` |
    | Gestion de stock | `aod_cap_stock` |
    | Personnalisation des tarifs de livraison | `aod_cap_shipping_prices` |
    | Statistiques de confirmation | `aod_cap_stats_confirm` |
    | Statistiques de livraison | `aod_cap_stats_delivery` |
  - (L'« affectation de produits » n'est pas une capability : user meta —
    étape 1.5.)
  - Étendre `maybe_sync_role()` pour créer/resynchroniser le nouveau rôle
    (même mécanique versionnée `aod_cd_roles_version`).
  - Le gérant (`aod_shop_client` / `manage_woocommerce`) est réputé avoir
    toutes les caps → helper central `AOD_CD_Roles::user_can( $cap )`.
- **Acceptation** : rôle visible dans WP ; un utilisateur `aod_confirmateur`
  sans cap ouvre `/gestion` mais ne voit aucune section métier ;
  `user_can()` renvoie vrai pour le gérant sur toutes les caps.

### Étape 1.3 — Routeur & AJAX gouvernés par capability (M)

- **But** : chaque section/action déclare sa capability ; onglets masqués
  **et** handlers refusés (403) sans elle.
- **Où** : `$this->sections` (:40), `render_section()` (:302), les 18 handlers
  AJAX (:57-74).
- **Tâches** :
  - Carte section → capability :
    | Section | Capability requise |
    |---|---|
    | `orders` | `aod_cap_confirm_orders` |
    | `tracking` | `aod_cap_tracking` |
    | `shipping` | `aod_cap_shipping_prices` |
    | `stats` (puis onglets Phase 2) | `aod_cap_stats_confirm` **ou** `aod_cap_stats_delivery` |
    | `products`, `categories`, `pixels`, `whatsapp`, `account` | gérant uniquement (`manage_woocommerce`) |
  - Carte handler → capability (confirmateur) : `order_status`,
    `order_detail`, `order_note`, `order_save_info` →
    `aod_cap_confirm_orders` ; `tracking_refresh`, `tracking_detail` →
    `aod_cap_tracking` ; `save_shipping` → `aod_cap_shipping_prices` ;
    `delete_orders`, `save_product`, `delete_product`, `save_category`,
    `delete_category`, `test_carrier`, `save_pixels`, `save_whatsapp`,
    `test_whatsapp`, `save_account`, `reset_shop` → **gérant uniquement**
    (inchangés).
  - Nav du dashboard : ne rendre que les onglets autorisés ; section par
    défaut = première section autorisée (aujourd'hui `orders` en dur, :170).
- **Acceptation** : matrice de tests — gérant : rien ne change (les 9 sections,
  toutes actions OK) ; confirmateur avec seulement `aod_cap_confirm_orders` :
  voit Commandes seule, peut confirmer, **mais** un POST AJAX direct sur
  `aod_cd_reset_shop`/`aod_cd_save_shipping` renvoie 403 ; confirmateur sans
  aucune cap : dashboard vide avec message propre.

### Étape 1.4 — Section « Équipe » : CRUD des confirmateurs (M)

- **But** : le gérant crée/édite/désactive ses confirmateurs et coche leurs
  permissions, sans passer par `wp-admin`.
- **Où** : **nouvelle classe** `includes/class-aod-cd-section-team.php` +
  entrée `team` dans le routeur (réservée `manage_woocommerce`).
- **Tâches** : liste des comptes `aod_confirmateur` (nom, login, caps cochées,
  dernier accès) ; formulaire créer/éditer — identifiant, nom affiché, mot de
  passe (généré ou saisi, règles de robustesse), **7 cases à cocher** (les 6
  caps + « Affectation de produits » qui ouvre le sélecteur de l'étape 1.5) ;
  désactivation réversible (retrait de `aod_use_dashboard`) et suppression
  (avec `wp_delete_user` + réattribution) ; handlers AJAX
  `aod_cd_save_member` / `aod_cd_delete_member` (nonce + `manage_woocommerce`,
  interdiction de toucher un compte gérant/admin).
- **Acceptation** : cycle complet créer → connecter (le confirmateur ne voit
  que ses onglets) → modifier les cases (effet immédiat) → désactiver
  (connexion refusée sur `/gestion`) → supprimer. Un confirmateur ne voit
  jamais la section Équipe.

### Étape 1.5 — Affectation de produits + filtrage serveur (M/L)

- **But** : restreindre un confirmateur à un produit ou un ensemble de produits.
- **Où** : user meta `_aod_assigned_products` (tableau d'IDs, vide = tous) ;
  points de lecture : liste Commandes (`section_orders`), détail commande,
  stats (Phase 2), stock (Phase 3).
- **Tâches** : sélecteur multi-produits dans la fiche membre (Équipe) ;
  helper central `AOD_CD_Roles::assigned_products( $user )` ; filtrage **côté
  serveur** : la liste des commandes ne retourne que celles contenant ≥ 1
  produit affecté (jointure `wp_wc_order_product_lookup`, HPOS-safe) ; accès
  direct à une commande hors périmètre → 403 (liste **et** détail **et**
  actions) ; badge discret « Produits : n » dans l'en-tête du confirmateur.
- **Acceptation** : confirmateur affecté au produit A ne voit/n'ouvre/ne
  confirme que les commandes contenant A (y compris par appel AJAX direct avec
  un ID hors périmètre → 403) ; affectation vide = tous les produits ; le
  gérant n'est jamais filtré.

### Étape 1.6 — i18n + recette de la phase (S)

- **Tâches** : `make-translations.sh` + traduction AR manuelle de toutes les
  nouvelles chaînes (section Équipe, libellés de caps, messages 403) ; recette
  complète de la matrice 1.3 en FR **et** AR (RTL), mobile ; mise à jour de
  `docs/ARCHITECTURE.md` (§ rôles & sécurité) et du README du plugin.
- **Acceptation** : `make-translations.sh --strict` passe ; matrice de tests
  rejouée en AR mobile sans casse RTL.

## Phase 2 — Accueil `/gestion` : 3 onglets de statistiques

*Dépend de la Phase 1 (caps + `_aod_confirmed_by`). Ordre conseillé : 2.1 →
2.2 → 2.3 → 2.4 (la traçabilité accumule des données pendant ce temps).*

### Étape 2.1 — Socle accueil : onglets + filtres communs (M)

- **But** : la nouvelle page d'accueil du dashboard = 3 onglets (Revenus /
  Confirmation / Livraison) + barre de filtres partagée.
- **Où** : **nouvelle classe** `includes/class-aod-cd-section-home.php` ;
  devient la section par défaut (à la place de `orders`, :170) pour qui a une
  cap stats ; l'actuelle `section_stats()` (:1047) migre dedans (onglet
  Revenus) puis l'entrée `stats` du menu disparaît.
- **Tâches** : patron d'onglets (même style que la page Commandes) ; filtres :
  **produit** (sélecteur), **confirmateur** (sélecteur, visible gérant
  uniquement), **période** — raccourcis Jour / Semaine + **calendrier** (plage
  libre `date_from`/`date_to`, input natif `type="date"`, OK mobile/RTL) ;
  état des filtres en query-string (partageable) ; requêtes agrégées via
  `wp_wc_order_product_lookup` + transients courts (5 min) invalidés au
  changement de statut de commande.
- **Acceptation** : les 3 onglets s'affichent selon les caps (`Revenus` =
  gérant ; `Confirmation` = `aod_cap_stats_confirm` ; `Livraison` =
  `aod_cap_stats_delivery`) ; les filtres persistent en changeant d'onglet ;
  < 1 s sur 1 000 commandes (transient chaud).

### Étape 2.2 — Onglet Revenus (M)

- **KPI** : CA, nombre de commandes, panier moyen, marge (via
  `_aod_cost_price`), graphe d'évolution, top-produits — l'existant de
  `section_stats()`, désormais filtrable par produit **et** par confirmateur
  (commandes confirmées par X) **et** par plage de dates libre.
- **Sécurité** : onglet réservé au gérant (chiffre d'affaires = donnée
  sensible) ; un confirmateur ne le voit pas.
- **Acceptation** : chiffres identiques à l'ancienne section Stats à filtres
  équivalents (non-régression) ; filtre produit recoupé à la main sur un jeu
  d'essai.

### Étape 2.3 — Onglet Livraison (M)

- **Source** : métas `_aod_ship_status*` écrites par le cron de suivi
  (`class-aod-ship-tracking.php`).
- **KPI** : taux livré / retourné / échec / en transit ; ventilation par
  transporteur et par wilaya ; délai moyen expédition → livraison ; commandes
  « bloquées » (sans événement depuis > N jours).
- **Sécurité** : `aod_cap_stats_delivery` ; confirmateur filtré sur ses
  produits affectés.
- **Acceptation** : les taux recoupent le comptage manuel des métas sur un jeu
  d'essai ; wilayas affichées en FR/AR selon la langue.

### Étape 2.4 — Onglet Confirmation (M)

- **Source** : métas `_aod_confirmed_by` / `_aod_confirmed_at` (étape 1.1).
- **KPI** : taux de confirmation (confirmées / total), délai moyen création →
  confirmation, ventilation par confirmateur (tableau : confirmées, taux,
  délai moyen), commandes « non attribuées » (antérieures à la Phase 1)
  comptées à part.
- **Sécurité** : `aod_cap_stats_confirm` ; un confirmateur ne voit **que sa
  propre ligne**, le gérant voit tout le monde.
- **Acceptation** : deux confirmateurs de test → chacun ne voit que ses
  chiffres ; le gérant voit les deux + les non-attribuées.

### Étape 2.5 — i18n + recette de la phase (S)

- `make-translations.sh` + AR ; recette FR/AR/RTL/mobile des 3 onglets et des
  filtres (calendrier natif en RTL notamment) ; MAJ `docs/ARCHITECTURE.md` +
  README plugin.

## Phase 3 — Page Stock

*Indépendante de la Phase 2 ; nécessite `aod_cap_stock` (Phase 1.2-1.3).*

### Étape 3.1 — Vue Stock : tableau + indicateurs (M)

- **Où** : **nouvelle classe** `includes/class-aod-cd-section-stock.php` +
  entrée `stock` au routeur (`aod_cap_stock`).
- **Tâches** : tableau mobile-first (scroll horizontal) — par produit : stock
  actuel, seuil d'alerte, **vendus sur la période** (filtre période commun),
  vitesse d'écoulement (ventes/jour), **jours de couverture estimés**
  (stock ÷ vitesse), badge Rupture / Stock bas / OK ; tri par couverture
  croissante par défaut (les urgences d'abord) ; packs : afficher le stock des
  composants (`_aod_pack_items`) ; produits sans `manage_stock` listés
  « non suivi » ; filtrage produits affectés pour un confirmateur.
- **Acceptation** : chiffres recoupés sur un jeu d'essai (ventes connues) ;
  badge Rupture dès `stock_quantity ≤ 0`, Stock bas sous le seuil ;
  couverture « ∞ » propre quand 0 vente.

### Étape 3.2 — Ajustement rapide + alertes (S/M)

- **Tâches** : édition inline de la quantité et du seuil (AJAX
  `aod_cd_save_stock`, nonce + `edit_products` — un confirmateur avec
  seulement `aod_cap_stock` **lit** sans modifier) ; note d'inventaire
  optionnelle ; encart « alertes » (ruptures + stock bas) en tête de section.
- **Acceptation** : modification reflétée côté Woo (`wp wc product get`) ;
  confirmateur sans `edit_products` → champs en lecture seule + AJAX 403.

### Étape 3.3 — i18n + recette de la phase (S)

- `make-translations.sh` + AR ; recette FR/AR/RTL/mobile ; MAJ docs.

## Phase 4 — Import des tarifs de livraison depuis l'API transporteur

*Indépendante des Phases 2-3 ; nécessite `aod_cap_shipping_prices` (Phase 1).
Périmètre : Yalidine/Yalitec, ZR Express (Procolis), EcoTrack + white-labels.
Noest exclu (pas d'API publique) ; Maystro à confirmer avec leur support.*

### Étape 4.1 — Abstraction `get_fees()` + Yalidine (M)

- **Où** : `plugins-dev/aod-cod-form/includes/class-aod-carrier.php`
  (abstraite) + `class-aod-carrier-yalidine.php` (couvre Yalitec par héritage).
- **Tâches** : méthode `get_fees()` → tableau normalisé
  `[wilaya_id => ['home' => int, 'desk' => int]]` ; endpoint `fees` Yalidine
  (wilaya de départ = réglage existant du transporteur, mêmes clés API que
  l'envoi de colis) ; timeout court (8 s) + erreurs propres (l'import est une
  commodité, la saisie manuelle reste le repli) ; aucun log de clé en clair.
- **Acceptation** : `get_fees()` renvoie les 58 wilayas sur un compte de test ;
  API indisponible → message d'erreur clair, aucune donnée modifiée.

### Étape 4.2 — ZR Express (Procolis) + EcoTrack (M)

- **Où** : `class-aod-carrier-procolis.php` (endpoint `/tarifs` — déjà
  repéré en commentaire :7) + `class-aod-carrier-ecotrack.php` (frais exposés
  par l'API ; vaut pour les 22 white-labels).
- **Acceptation** : même contrat normalisé que 4.1 pour chaque famille ;
  transporteur sans `get_fees()` (Noest, Maystro) → bouton d'import absent,
  sans erreur.

### Étape 4.3 — UI d'import : prévisualisation + marge + validation (M)

- **Où** : onglet Livraison de `/gestion` + page admin
  `WooCommerce → Livraison COD`.
- **Tâches** : bouton « Importer les tarifs du transporteur » (choix du
  transporteur configuré) → **tableau de prévisualisation** : tarif actuel vs
  tarif API, différences surlignées ; option de **marge** (fixe DZD ou %,
  arrondi configurable) appliquée à la volée dans la préview ; cases par
  wilaya (tout cocher/décocher) ; **rien n'est écrit sans « Appliquer »**
  explicite ; sauvegarde des tarifs précédents dans une option de backup
  (`aod_cod_delivery_prices_backup`) + bouton « Restaurer » ; AJAX
  `aod_cd_import_fees` (nonce + `aod_cap_shipping_prices` ou
  `manage_woocommerce`).
- **Acceptation** : préview sans effet de bord (recharger = aucun changement) ;
  appliquer n'écrase que les wilayas cochées ; restaurer remet l'état
  antérieur ; marge % vérifiée sur cas connus ; en Docker, rappel : le bloc
  `dns: [8.8.8.8, 1.1.1.1]` est requis pour les appels sortants (TEMPLATE.md §3).

### Étape 4.4 — i18n + recette de la phase (S)

- `make-translations.sh` + AR ; recette FR/AR/RTL/mobile de la préview ;
  MAJ docs + README `aod-cod-form`.

## Phase 5 — Refonte UI/UX de `/gestion` (« Laste Premium »)

> Harmonise et embellit l'espace gérant **sans changer la logique métier** :
> socle de design tokens (accent piloté par le Personnalisateur), composants
> unifiés, puis réorganisation du contenu page par page. Spécification complète :
> [`UX-GESTION-REFONTE.md`](UX-GESTION-REFONTE.md). **Le socle (5.1) précède
> tout** — sans lui chaque page re-diverge.

### Étape 5.1 — Socle design tokens + accent branding (M) — ✅ Livrée
Tokens `:root` (typo/espacement/rayons/ombres/sémantiques) ; réglage
Personnalisateur `aod_cd_accent` + amorçage auto ; injection inline en `:root`
dans `render_app()` avec `--cd-accent`, `--cd-accent-strong`, `--cd-accent-ink`
(encre auto par luminance), `--cd-accent-soft`. Le CSS n'utilise plus `#FFE21E`.
Aucun changement de logique ni de contenu.

> **Réalisé.** Nouvelle classe `AOD_CD_Customize` (section « Espace de gestion »,
> contrôle couleur `aod_cd_accent`, défaut `#FFE21E`). Dérivation PHP des variantes
> (`accent-strong`, `accent-soft`, `accent-rgb`) et de l'encre par **contraste WCAG**
> (choix noir/blanc au meilleur ratio — robuste sur les tons moyens). Injection
> `<style id="aod-cd-accent">` dans `render_app()`. `dashboard.css` : alias
> historiques (`--cd-yellow`…) redirigés vers l'accent, échelles typo/espacement/rayons
> ajoutées, plus aucun `#FFE21E`/`rgba(255,226,30…)`/teinte jaune en dur (seuls replis
> documentés dans `:root`). Dégradé SVG du graphe Accueil passé à `var(--cd-accent)`.
> i18n : 4 chaînes FR/AR (`.mo` recompilé). Vérifié en rendu live (accent navy →
> encre blanche auto).

### Étape 5.2 — Bibliothèque de composants unifiés (M/L)
Bouton (variantes + états loading), **select unique** (fusion de `statussel` /
`csel` / `catdd` / `filterdd`), segmented control, carte, KPI (+ delta), pastille
de statut, **en-tête de page `.aod-cd-pagehead`**, état vide, skeleton, champ
standardisé. Clavier + ARIA sur les dropdowns.

> **Scindée en deux pour maîtriser le risque.**
>
> **5.2a — Bibliothèque visuelle (✅ Livrée).** Composants **additifs** (aucun
> sélecteur existant modifié → zéro régression) dans `dashboard.css`, pilotés par
> les tokens 5.1 : `.aod-cd-pagehead` (titre/sous-titre/actions), `.aod-cd-seg`
> (segmented control canonique, remplace à terme `.aod-cd-tabs`), `.aod-cd-kpi`
> (valeur + libellé + delta up/down/flat), `.aod-cd-pill--ok|warn|bad|info|neutral`
> (pastille sémantique unique), `.aod-cd-todo-tile` (tuiles « à traiter »),
> `.aod-cd-empty`, `.aod-cd-skel`, bouton `--ghost` + `.is-loading`. Ajout des
> tokens sémantiques doux (`--cd-ok/warn/bad/info-soft`). Consommés par 5.3-5.7.
>
> **5.2b — Fusion des sélecteurs + clavier/ARIA — ✅ LIVRÉE le 2026-07-13.**
> *(Ce bloc décrit le plan d'origine ; il est conservé pour la trace. Ce qui a été
> réellement livré : `filterdd` supprimé en 5.4, puis clavier + ARIA mutualisés en
> 5.8 via `bindFloatingMenu()` / `bindListboxKeys()`. Le **style** est resté en
> trois exemplaires jusqu'à la **Phase 17**, qui a mutualisé la surface flottante
> en 17.6. Cette ligne a annoncé « à faire » pendant sept jours alors que la table
> §C.3 et la Phase 17 la donnaient livrée — la contradiction même dont ce fichier
> tire sa leçon en §C.2.)* Plan d'origine : unifier les 4
> dropdowns (`statussel` single+AJAX, `catdd` multi-cases, `csel` enrobage de
> `<select>`, `filterdd` filtre) en un composant unique, plus navigation clavier
> et ARIA. Refactor de **flux cœur** (changement de statut, affectation catégories,
> filtre commandes) → à mener **en contexte** lors de la refonte des pages
> concernées (5.4 Commandes, 5.5 Produits/Catégories) avec **recette interactive**,
> pas à l'aveugle. Les 4 dropdowns partagent déjà l'accent/les tokens depuis 5.1.

### Étape 5.3 — Coque & navigation (M) — ✅ Livrée (coque)
Sidebar regroupée par domaine (Pilotage / Ventes / Catalogue / Livraison /
Marketing / Administration), topbar avec fil d'ariane, application de
`.aod-cd-pagehead` sur les 11 pages.

> **Réalisé.** `nav_groups()` : navigation groupée par domaine (6 groupes,
> intitulés `.aod-cd-navgroup`) ; un groupe sans section visible (capabilities)
> est masqué → le filtrage confirmateur est préservé. Topbar : fil d'ariane
> `.aod-cd-crumb` (« Gestion › Domaine ») + titre, via `section_group_label()`.
> i18n : 6 chaînes FR/AR. Vérifié en rendu live (6 groupes, breadcrumb correct).
>
> 🔁 **CORRIGÉ le 2026-08-03 — le regroupement livré ici portait deux défauts, et
> l'un d'eux était DÉJÀ connu du dépôt.** Le domaine « Livraison » ne contenait
> qu'une section, elle aussi nommée « Livraison » : la barre latérale affichait le
> même mot sur deux lignes qui se touchent. Le fil d'ariane, lui, portait depuis
> longtemps un test `$group_label !== $page_label` **dont le commentaire citait
> l'exemple exact** (« Gestion › Livraison › Livraison »). La rustine n'avait donc
> jamais été propagée à l'endroit que le gérant regarde en permanence — le menu.
> 🚨 *Même famille que la Phase 46 (« une règle écrite deux fois ne tient pas ») et
> que la 47 (« une décision propagée à un jumeau et pas à l'autre ») : le défaut
> n'était pas ignoré, il était traité à un seul des deux endroits où il se voit.*
> Second défaut, indépendant : `tracking` (« Suivi commande ») vivait dans
> « Ventes » alors qu'il rend des **états transporteur** (cron 2 h) — d'autant plus
> mal placé depuis la **Phase 47**, qui a fait de l'envoi un geste déclenché au
> camion, donc détaché de la confirmation.
> **Corrigé à la racine, pas au rendu** : le domaine devient « Expédition » (`الشحن`,
> distinct de « Livraison » = `التوصيل`) et accueille `shipping` **+** `tracking`.
> Un seul geste ferme les deux : le domaine cesse d'être un singleton homonyme, et
> « Ventes » cesse de mélanger le tunnel de vente avec le suivi logistique. Toujours
> 6 groupes — la géométrie du rail réglée au pixel en **45.5** n'est pas touchée.
> ⚠️ Le test du fil d'ariane est **conservé** comme garde-fou bon marché pour un
> futur groupe, mais il n'est plus le correctif : son commentaire le dit désormais,
> pour qu'on ne le relise pas comme la preuve que le cas est couvert.
> **Cliquet : `tests/unit/test-nav-groupes.php`** (R1 homonymie · R2 section
> orpheline · R3 double rattachement · R4 slug fantôme · R5 domaine vide · R6
> l'arbitrage `tracking` lui-même, écrit avec sa raison). *La rustine au rendu
> n'empêchait pas la dérive de revenir ; seul un cliquet le fait.*
>
> **Report assumé :** l'application de `.aod-cd-pagehead` (titre + sous-titre +
> actions) se fait **page par page** en 5.4-5.7, là où le sous-titre et les
> actions propres à chaque page sont définis — poser un pagehead générique vide
> maintenant serait du churn sans valeur.

### Étape 5.4 — Pilotage quotidien : Accueil + Commandes + Suivi (L) — ✅ Livrée
Accueil (bandeau « À traiter », KPI + delta, segmented control) ; Commandes
(priorisation des colonnes, segmented control de statut, pastilles unifiées) ;
Suivi (mêmes pastilles, timeline peaufinée).

> **Réalisé.** Première étape qui **consomme** la bibliothèque 5.2a (jusque-là
> écrite mais inutilisée par toutes les pages). Helpers canoniques
> `AOD_CD_Dashboard::page_head()` / `empty_state()` / `tracking_pill()` +
> `tracking_tone()` (mappage état→ton, source unique partagée par Suivi, la
> timeline et l'onglet Livraison). **Accueil** : en-tête « Bonjour {prénom} »,
> bandeau « À traiter » (4 tuiles gouvernées par capability, compteurs scopés,
> cache dédié), onglets → `.aod-cd-seg`, les 3 onglets passent en `.aod-cd-kpi`
> **avec delta vs période précédente** — la **polarité** est déclarée par KPI (une
> hausse des annulations/retours/échecs/délais s'affiche en **rouge**, pas en
> vert), base 0 → pas de delta, taux comparés en **points**. Graphe SVG intact.
> **Commandes** : tableau **13 → 7 colonnes** (+ case conditionnelle), client =
> nom + tél. empilés ; `.aod-cd-filterdd` **supprimé** (PHP+CSS+JS) au profit d'un
> segmented control de liens GET → **1er des 4 sélecteurs de la 5.2b éliminé**.
> **Suivi** : filtres transporteur/état (+ `bloques`, spec §4.3), barre toujours
> rendue (un filtre vide ne peut plus se piéger), pastilles unifiées.
>
> **Correctifs livrés au passage :**
> - 🐛 **`.aod-cd-empty` était défini deux fois** (conflit de cascade introduit par
>   5.2a) : les 15 call-sites rendaient une boîte pointillée dans une carte. Helper
>   `empty_state()` + 15 migrations + suppression de la règle legacy.
> - 🔒 **Fuite de périmètre sur la page Suivi** : `section_tracking()` ne filtrait
>   pas par produits assignés et `ajax_tracking_detail` / `ajax_tracking_refresh`
>   n'avaient **aucune garde `order_in_scope()`** → un confirmateur lisait les
>   commandes hors de son périmètre. Corrigé (`scoped_orders()` + 2 gardes → 403).
> - 🐛 Modale de détail : la ligne « Livraison » était **conditionnelle** (`> 0`) et
>   il n'existait **aucune** ligne « Prix produit » → retirer ces colonnes aurait
>   **perdu de la donnée**. Les deux lignes sont désormais inconditionnelles.
> - RTL : `margin-left:auto` → `margin-inline-start` (bulk-bar) ; chaîne FR en dur
>   du JS (« Chargement… ») supprimée au profit d'un skeleton i18n.
>
> **Recette : 70/70 assertions vertes** (`wp eval-file`, stack template-full) —
> rendu réel des 3 pages sans exception (gérant), helpers, mappage des 8 états,
> polarité du delta, cas limites (base 0, période d'un jour), et **AC 27 prouvé sur
> une vraie commande en livraison gratuite**. i18n `--strict` vert (23 chaînes AR).
> Écart assumé vs maquette : la tuile « À confirmer aujourd'hui » est remplacée par
> **« Échecs de livraison »** (elle aurait mené à la même liste que « En attente »).

> **Recette sur données réelles (2026-07-12)** — jeu de test chargé via
> `scripts/dev-seed.php` (29 commandes sur 4 statuts, 6 produits, 1 confirmateur
> au périmètre restreint, 18 colis sur 6 états). **10/10 assertions vertes** sur
> les deux rôles : le gérant voit 18 colis (et non les 29 commandes), le
> confirmateur 9 commandes / 5 colis **sans aucune fuite hors périmètre**. Deux
> trouvailles d'environnement au passage :
>
> 1. **HPOS n'était pas activé** alors que tout le code le suppose (7 documents
>    l'affirment). En stockage *legacy*, `wc_get_orders()` **ignore silencieusement
>    `meta_query`** → la page Suivi listait *toutes* les commandes comme si
>    c'étaient des colis, et les tuiles « Échecs »/« Bloqués » comptaient tout.
>    ✅ Corrigé : `scripts/setup.sh` active désormais HPOS (`wp wc hpos sync` +
>    `enable`) sur tout nouveau clone.
> 2. ⚠️ **À surveiller (→ 5.5)** : `scope_join_sql()` joint sur
>    `wp_wc_order_product_lookup`, la table d'**analytics** de WooCommerce, qui est
>    peuplée **de façon asynchrone** (Action Scheduler). Conséquences : une commande
>    fraîchement passée peut rester invisible au confirmateur pendant un court
>    délai, et si l'analytics est désactivé, la table n'est jamais alimentée → le
>    confirmateur ne voit **plus rien**. Une jointure sur `wp_woocommerce_order_items`
>    + `order_itemmeta` (toujours peuplées, en synchrone) supprimerait cette
>    dépendance. Filtre critique pour la sécurité : à traiter dans sa propre story,
>    avec revue adverse.

### Étape 5.5 — Catalogue : Produits + Catégories + Stock (L)
Produits (5 blocs, barre d'enregistrement sticky, badge stock) ; Catégories
(carte de création + lignes homogènes) ; Stock (KPI + alertes sémantiques +
inline stylé, lecture seule confirmateur).

### Étape 5.6 — Livraison + Pixels + WhatsApp (M) — ✅ livrée
Livraison en 3 blocs à en-têtes clairs (tarifs / transporteurs / import) ;
Pixels en cartes par plateforme (actif/inactif) ; WhatsApp carte unique + test.

> **Trouvé en recette — à connaître pour les étapes suivantes.**
> 1. **Titre affiché deux fois sur 8 pages** : la barre du haut (5.3) et
>    `page_head()` (5.4-5.5) portaient tous deux le nom de la page. Arbitrage : la
>    barre du haut ne garde que le **fil d'ariane** (qui reprend désormais le nom de
>    la page en dernier maillon), la page est seule propriétaire de son titre.
>    → Les pages d'**Équipe** et **Mon compte** (5.7) doivent donc adopter
>    `page_head()` : elles n'ont plus que leur `<h2>` interne.
> 2. **`min-width:0` obligatoire** sur tout enfant direct de `.aod-cd-settings-form`
>    (grille) qui enferme un tableau large : un élément de grille vaut
>    `min-width:auto` et refuse de rétrécir → la page débordait à 840 px sur un
>    écran de 390. Le `overflow-x:auto` intérieur n'y peut rien.
> 3. **Le garde-fou i18n mentait** : `make-translations.sh` listait les chaînes non
>    traduites avec `grep '^msgid "'`, or gettext replie les **msgid longs** sur
>    plusieurs lignes (la première n'étant que `msgid ""`) — ils étaient donc filtrés
>    et jamais signalés, `--strict` sortant au vert malgré des trous. Corrigé par
>    `msgcat --no-wrap`. **Toute étape antérieure validée « i18n vert » l'a été avec
>    ce trou** : le contrôle est désormais fiable (3 chaînes de marque restantes,
>    volontaires).
> 4. Un accordéon **replié** soumet quand même ses champs (vérifié : les
>    identifiants API des transporteurs et les 58 tarifs survivent à un
>    enregistrement tous blocs fermés) — et un champ invalide qui s'y cache rouvre
>    son bloc (garde-fou généralisé à tout `form.aod-cd-acc-form`).

### Étape 5.7 — Équipe + Mon compte (M) — ✅ livrée
Cartes membres (initiales, dernière connexion, badges) + modale d'édition
(6 toggles de capabilities + périmètre produits) ; compte en sections
Profil/Sécurité ; zone de danger restylée.

> **Décisions & pièges (5.7).**
> 1. **Le pied de la modale est un frère du corps**, pas un bloc `sticky` dedans.
>    Collé à l'intérieur d'une zone qui défile, il laissait filer le contenu sous
>    lui (le rembourrage bas fait partie du scrollport) et sa largeur dépendait de
>    rembourrages qui changent avec l'écran. Les boutons sont rattachés au
>    formulaire par l'attribut `form="aod-cd-team-form"`.
> 2. **`.aod-cd-form` porte `padding:26px`** (elle vit d'ordinaire seule dans une
>    carte) : à mettre à zéro dans une modale, sinon double encadrement.
> 3. **La liste des membres n'est plus un `<table>`** : le JS ancrait ses données
>    sur `closest('tr')`. L'ancre est désormais `[data-member]` — un `closest('tr')`
>    oublié renverrait `null` et le bouton ne ferait plus rien, **sans erreur
>    visible**.
> 4. **Vérifié en recette** : modifier un confirmateur préserve son périmètre
>    produits. Si la modale ne re-cochait pas les produits affectés, un simple
>    « Enregistrer » aurait **élargi son périmètre à toute la boutique** en silence
>    (`ajax_save_member` réécrit `_aod_assigned_products` à chaque envoi).

### Étape 5.8 — Recette globale + a11y (M) — ✅ livrée
Contraste AA sur les 11 pages **et pour tout accent client**, clavier complet sur
les 3 sélecteurs (clôt aussi la 5.2b), piège de focus sur les 3 modales, 184 champs
nommés, recette RTL + mobile 390 px, non-régression 5.5/5.6/5.7.

> **Décisions & pièges (5.8).**
>
> 1. **L'accent par défaut masquait deux trous d'accessibilité.** Le template
>    laisse le client choisir sa couleur d'accent : la recette doit donc se faire
>    avec des accents HOSTILES, pas seulement le jaune du template (qui, très
>    contrasté, passe partout et endort la vigilance).
>    - `best_ink()` choisissait l'encre du texte POSÉ SUR l'accent entre `#181818`
>      et le blanc. Borne du « meilleur des deux » : **4,21:1** dans le pire cas
>      (`#1878f0`, un bleu de marque banal ; le rouge pur plafonne à 4,44) — sous
>      AA. En prenant le **noir pur** comme candidat, le plancher mathématique
>      remonte à **4,58:1, garanti pour toute couleur du cube**.
>    - Trois endroits posaient l'accent **en texte sur nos surfaces sombres**
>      (switcher de langue actif, pagination active, lien de la sidebar). Lisible
>      en jaune, sous AA en bleu, **invisible** avec un accent foncé. D'où
>      `--cd-accent-on-dark`, éclairci vers le blanc juste assez pour atteindre AA.
>    - Même principe pour l'**anneau de focus** : bâti sur l'accent, il disparaît
>      dès qu'on choisit un accent pâle. Il est désormais bâti sur l'encre.
>
> 2. **Il n'existe que DEUX gris de texte, pas trois.** `--cd-soft` (#BDBDBD)
>    servait de « troisième niveau » : 1,88:1, très loin d'AA. Un gris de texte
>    doit tenir 4,5:1 sur le PIRE de nos fonds (#EDEDED, teintes douces d'alerte),
>    ce qui plafonne à ~#666 — un troisième gris plus clair est mathématiquement
>    impossible. `--cd-soft` est donc désormais réservé au **décor** (bordures,
>    chevrons `aria-hidden`), et tout ce qui est du texte est passé sur `--cd-muted`.
>
> 3. **Le monogramme des transporteurs était blanc en dur** — 2,15:1 sur l'ambre
>    d'EcoTrack. Or chaque transporteur déclare SA couleur de marque, et les 22
>    white-labels EcoTrack en déclarent 22 autres, hors de notre contrôle :
>    `AOD_Carrier::brand_ink()` calcule l'encre. Un futur white-label ne peut plus
>    rendre sa pastille illisible.
>
> 4. **Deux bugs SOURIS trouvés en faisant l'audit clavier** (les menus flottants
>    se refermaient sur `scroll`, écoute en phase de capture) :
>    - Cliquer un sélecteur **pas entièrement visible** le focalise → le navigateur
>      défile pour l'amener en vue → l'événement `scroll` arrive APRÈS le `click` :
>      le menu s'ouvrait puis se refermait aussitôt. **Au premier clic, rien ne se
>      passait.**
>    - La molette **à l'intérieur** du menu (29 transporteurs, `max-height` +
>      `overflow`) le refermait sous le curseur : les options du bas étaient
>      inatteignables à la souris.
>    Le menu est maintenant **recollé** au bouton au défilement, et ne se ferme que
>    si le bouton quitte l'écran.
>
> 5. **Le focus ne doit jamais entrer dans le menu de `csel`** : celui-ci vit dans
>    le `<body>` (pour échapper aux `overflow` parents), il n'est donc pas voisin de
>    son bouton dans l'ordre de tabulation. D'où le modèle **combobox** : le focus
>    reste sur le bouton, l'option courante est désignée par `aria-activedescendant`
>    et surlignée (`.is-kbd`), les options gardent `tabindex="-1"`.
>    Corollaire : le menu ne doit PAS voler le focus au `mousedown` (comme un
>    `<select>` natif) — sans ce `preventDefault`, le `blur` du bouton refermerait
>    le menu avant que le `click` sur l'option ne parte, et **la sélection à la
>    souris ne marcherait plus du tout**.
>
> 6. **`aria-modal="true"` ne piège pas le focus** — c'est une promesse faite aux
>    lecteurs d'écran, pas un comportement du navigateur. Sans piège, on tabule
>    derrière la modale, à l'aveugle, dans un formulaire qu'on ne voit plus.
>    `trapFocus()` (partagé par les 3 modales) garde le Tab dedans et rend le focus
>    au déclencheur à la fermeture.
>
> 7. **184 champs sans intitulé accessible**, dont les 174 de la table des tarifs
>    (58 wilayas × 3). Le nom de la wilaya n'existe que dans la cellule VOISINE :
>    en tabulant, un lecteur d'écran annonçait « zone de saisie » sans jamais dire
>    de quelle wilaya il s'agit. Idem pour le stock (nom du produit dans une autre
>    cellule). **Un `placeholder` n'est pas un intitulé** : il disparaît à la
>    première frappe.

---

## Phase 6 — Landing page (page de vente) par produit ✅

*Spécification complète : [`LANDING-PRODUIT.md`](LANDING-PRODUIT.md) — à lire
**avant** la première story. Elle porte les décisions de fond (§2), le contrat de
données (§4) et le catalogue de blocs (§5) ; la roadmap ne porte que le découpage.*

**Versions cibles** : `aod-cod-form` v1.25.0 · `aod-client-dashboard` v0.24.0.

**Le fil rouge de la phase** : la landing est du **contenu marchand**, et
`gettext` ne traduit que le code. Chaque champ texte est donc un **couple
`{ fr, ar }`** avec repli automatique (`AOD_COD_Data::label()`). Le **chrome** de
la landing (« Commander maintenant », « Questions fréquentes ») reste, lui, du
`__()` classique. Confondre les deux = un site bilingue partout sauf sur la page
qui vend.

**Deux invariants, valables à chaque étape :**

- **Un produit sans landing rend exactement comme aujourd'hui.** C'est le premier
  test de chaque recette, pas le dernier.
- **La landing n'a pas son propre formulaire.** Le bloc `form` appelle
  `AOD_COD_Form::get_form_html()` — le point d'entrée que partagent déjà le hook
  produit, le shortcode, le bloc Gutenberg et le widget Elementor. Offres,
  variantes, packs, anti-bot, Turnstile, prospects et pixels suivent **gratuitement**.
  Recopier le formulaire, c'est se condamner à maintenir deux tunnels d'achat.

### Étape 6.1 — Socle : données + rendu (M)
- Classe `AOD_COD_Landing` (`aod-cod-form`) : **propriétaire du schéma** —
  `schema()`, `sanitize()`, `get()`, `text()`. Le dashboard **appellera** ce
  `sanitize()` ; deux copies du schéma dériveraient dès le premier bloc ajouté.
- Métas `_aod_landing_enabled` + `_aod_landing` (schéma `version: 1`, cf. spec §4.2) :
  tout texte = `{ fr, ar }`, toute image = **ID d'attachement** (jamais une URL —
  sinon la mise en ligne casse toutes les images), **aucun HTML libre**.
- Rendu par `template_include` sur `is_product()` + interrupteur ON →
  `templates/single-product-landing.php` (`get_header()` → blocs → `get_footer()`,
  donc nav 3 entrées + switcher FR/AR conservés). Interrupteur OFF ⇒ **le filtre
  ne fait rien**.
- Blocs du socle : `hero`, `points`, **`form`**, `trust`. CSS + JS dédiés, chargés
  **uniquement** quand la landing est active.
- Aperçu gérant : `?aod_landing_preview=1`, réservé à `edit_products`.
- **Recette** : produit sans landing = rendu identique au commit précédent (à
  comparer réellement) ; landing ON = formulaire COD fonctionnel de bout en bout
  (commande créée), pas de panier, nav intacte.

### Étape 6.2 — Éditeur `/gestion` (L)
- Classe dédiée **`AOD_CD_Section_Landing`** (la DoD l'impose : rien de neuf dans
  le monolithe de 5 122 lignes). Écran `/gestion/landing?produit=123`, atteint par
  un bouton « Page de vente » depuis Produits, **masqué de la sidebar**, fil
  d'ariane « Produits › Page de vente — *nom* ». **Gérant uniquement**
  (`edit_products` + `edit_product` sur l'ID) — **aucune nouvelle capability**.
- Liste de blocs : ajouter / réordonner / déplier / supprimer. Chaque champ texte
  = **deux cases, `Français` et `العربية`**. Images en `media_handle_upload()`
  multipart (comme le formulaire produit — le gérant n'a pas `wp.media`).
- AJAX `aod_cd_save_landing` : nonce + `edit_products` + `edit_product` sur l'ID
  (**403**, pas un masquage d'UI) → `AOD_COD_Landing::sanitize()` (liste blanche :
  un champ hors schéma est **jeté**).
- **Une landing sans bloc `form` est refusée à l'enregistrement** — elle ne vend rien.
- Clavier/ARIA : réutiliser `trapFocus` / `bindListboxKeys` / `bindFloatingMenu` de
  `dashboard.js`. Ne pas réinventer le socle a11y.

> **Livrée le 2026-07-13** (`aod-client-dashboard` **v0.24.0**). Deux écarts assumés
> par rapport au plan ci-dessus, et une découverte :
>
> - **Le refus ne porte que sur une landing ALLUMÉE.** Interdire d'enregistrer sans
>   bloc `form` en toutes circonstances, c'était interdire le brouillon : le gérant
>   n'aurait pas pu quitter son écran avant d'avoir fini. L'interrupteur éteint EST
>   le brouillon (les blocs sont conservés, la fiche produit s'affiche comme avant).
> - **Ni `trapFocus` ni `bindListboxKeys` ne s'appliquent** : l'écran n'a ni modale
>   ni liste déroulante maison. Le socle a11y réutilisé est celui des boutons
>   (`focus-visible`, `aria-expanded` sur le repli, cibles tactiles ≥ 36 px, focus
>   qui suit le bloc déplacé).
> - ⚠️ **Découvert au passage** : dans la stack Docker, `wp-content/uploads/<année>`
>   appartient à l'utilisateur de l'hôte en 755 → **Apache (`www-data`) ne peut pas y
>   créer le dossier du mois**. TOUT téléversement depuis `/gestion` échoue (image
>   produit, galerie, vignette de catégorie), pas seulement la landing. L'éditeur le
>   **signale** désormais au lieu d'afficher « Enregistré » sans image, mais la cause
>   reste à corriger dans la stack de dev — **chantier à part**.

### Étape 6.3 — Blocs de contenu (M)
`gallery`, `video`, `text`, `compare`.
- **La vidéo est une façade** : on affiche la couverture, l'`<iframe>` YouTube
  n'est injectée **qu'au clic**. Une iframe posée d'emblée coûte ~1 Mo : l'objectif
  « < 3 s mobile » n'y survit pas.
- Images : `loading="lazy"` + `srcset`.

> **Livrée le 2026-07-14** (`aod-cod-form` **v1.26.0** · `aod-client-dashboard`
> **v0.25.0**). Trois choses à savoir pour la suite :
>
> - **Trois nouveaux types de champ** au schéma (`images`, `video`, `url`) — donc
>   trois de plus que l'éditeur sait dessiner **sans rien savoir des blocs**. Ajouter
>   un bloc de la 6.4 qui n'utilise que des types existants ne demandera **aucune**
>   ligne dans `/gestion`.
> - **La vidéo accepte un lien YouTube OU un fichier téléversé**, et le fichier
>   l'emporte. Le lien n'est jamais concaténé dans un `src` : on en **extrait**
>   l'identifiant (11 caractères), on le valide, et on **rebâtit** l'URL sur
>   `youtube-nocookie.com`. Un lien de travers n'affiche pas de lecteur — il
>   n'injecte rien. Un fichier téléversé, lui, ne charge **aucun JS** (`preload="none"`
>   suffit) : le seul script de la landing n'est chargé que si une façade YouTube
>   existe réellement.
> - **`text` échappe AVANT `wpautop()`**, jamais l'inverse : les paragraphes naissent
>   des sauts de ligne, pas de balises collées par le gérant.

### Étape 6.4 — Blocs de conversion (M)
`reviews`, `faq`, `urgency`, + le **CTA collant mobile** (réglage de page).
- FAQ en `<details>/<summary>` natif : accessible, et zéro JS.
- **`urgency` lit le VRAI stock WooCommerce.** Pas de faux compteur, pas de rebours
  qui se réarme à chaque visite. Le bloc est neutre ; le mensonge, lui, se
  retournerait contre le marchand.
- Le CTA collant ne doit ni masquer le contenu (`padding-block-end`) ni piéger le
  focus.

> **Livrée le 2026-07-14** (`aod-cod-form` **v1.27.0** · `aod-client-dashboard`
> **v0.26.0**). Le catalogue de blocs est **complet**. Quatre choses à retenir :
>
> - **L'urgence se tait plutôt que de mentir.** Mode `stock` : le nombre vient du vrai
>   stock Woo — stock non géré, rupture, ou stock au-dessus du seuil fixé par le
>   gérant → le bloc **ne s'affiche pas**. Mode `sale` : le rebours vise la date de fin
>   de promo **réelle** (`get_date_on_sale_to()`), l'instant exact où Woo retire le prix
>   barré ; sans promo datée, pas de rebours ; à zéro, la section **disparaît**. Les
>   quatre cas sont vérifiés en recette, pas supposés.
> - **`str_replace`, jamais `sprintf`, sur un texte saisi par le gérant.** Le message
>   d'urgence accepte le jeton `%d`. Passé à `sprintf()`, un « -50 % » tapé dans le
>   champ aurait fait tomber la page en erreur fatale (PHP 8) — page blanche sur la
>   boutique du client. **Le piège vaut pour toute chaîne venant de la base.**
> - **Un fichier peut vivre DANS un item** (la photo d'un avis) : son nom d'envoi porte
>   le rang de l'item, et le JS le réécrit depuis le DOM à chaque déplacement. Deux
>   nouveaux types de champ au passage (`number`, `select`), réutilisables tels quels.
> - **La barre collante ne piège pas le focus** : à l'arrivée du formulaire à l'écran
>   elle passe en `visibility: hidden`, ce qui la sort aussi de l'ordre de tabulation.
>   Un simple décalage aurait laissé un bouton focalisable et invisible.

### Étape 6.5 — Modèle de départ + duplication (S) — *optionnelle, arbitrée le 2026-07-14*
Bouton « Partir d'un modèle » (structure type, blocs **vides**) et copie de la
landing d'un produit vers un autre. **Ne pas la faire par défaut** : à décider
après avoir vécu 6.2 sur un vrai clone. → **L'owner a tranché : faite.**

> **Livrée le 2026-07-14** (`aod-cod-form` **v1.28.0** · `aod-client-dashboard`
> **v0.27.0**). Ce qu'il faut savoir :
>
> - **Le modèle vit dans le format** (`AOD_COD_Landing::starter()`), pas dans
>   l'éditeur : 8 blocs dans l'ordre qui vend (héro → bénéfices → galerie → avis →
>   FAQ → urgence → formulaire → réassurance), tous **vides**. Pas de faux contenu :
>   un texte d'exemple partirait dans tous les clones (règle n°1) et le premier
>   gérant pressé le publierait tel quel. Côté écran, le bouton ne fait que cloner
>   les gabarits existants — un type sans gabarit est sauté, pas une erreur.
> - **La copie arrive ÉTEINTE, toujours** : ses textes parlent de l'autre produit,
>   les publier tels quels tromperait le visiteur. L'interrupteur retombe même si la
>   source est allumée ; le gérant adapte, puis rallume. La source n'est jamais
>   modifiée, et les images sont **partagées** (mêmes IDs d'attachement), pas
>   dupliquées.
> - La liste des produits copiables ne propose que ceux qui ont **réellement des
>   blocs** (brouillons compris), jamais le produit courant ; l'AJAX revérifie
>   nonce + `edit_product` sur la cible + **périmètre sur les deux bouts** (copier
>   depuis un produit hors périmètre serait lire un contenu qu'on n'a pas à voir).
> - Copier par-dessus des blocs existants demande **confirmation** à l'écran.

### Étape 6.6 — i18n + recette de la phase (S)
- `make-translations.sh` des **deux** plugins, AR traduit à la main, `--strict` vert.
- Recette FR **et** AR (RTL), mobile **390 px**, **< 3 s**, contraste AA — en
  particulier le **texte du héro posé sur une image**, le piège classique.
- La landing entre dans le scénario `public` de `tools/recette/`.

> **Livrée le 2026-07-14.** L'i18n était déjà verte en continu (6.3, 6.4) ; le
> livrable de cette étape est **`tools/recette/landing-produit.js`**, branché sur
> `run.sh` (`bash tools/recette/run.sh landing`, et joué d'office avec `public`).
> Trois choses à savoir :
>
> - **`run.sh` découvre le produit tout seul** (WP-CLI, méta `_aod_landing_enabled`).
>   Aucune landing sur le clone → le scénario **se retire** (sortie 0) : elle est
>   optionnelle par produit, son absence n'est pas un défaut.
> - Le scénario vérifie les **invariants du template**, pas le contenu du gérant :
>   en-tête/pied du thème conservés (nav 3 entrées, switcher), formulaire COD +
>   ancre, aucun tunnel, chrome AR sans reste de français, 390 px sans débordement,
>   barre collante qui rallonge le `body`, produit sans landing inchangé.
> - **Le piège du héro n'existe pas dans notre gabarit** : le texte est SOUS l'image,
>   jamais posé dessus (vérifié par le chevauchement des boîtes, pas au jugé). Et la
>   landing n'ajoute **aucun domaine tiers** : les requêtes Meta/Google observées
>   viennent des pixels du clone, identiques sur toute page — on a d'abord cru le
>   contraire à cause d'un effet d'ordre de visite dans le test (gtag ne tire sa
>   balise `ad.doubleclick.net` qu'à la première page d'un navigateur vierge).

---

## Phase 7 — Contenu produit bilingue FR/AR

*Planifiée le 2026-07-15 (cf. verdict A.7). Généralise au catalogue le mécanisme
`{ fr, ar }` prouvé par la Phase 6. **Dépend de la Phase 6** (réutilise son infra
bilingue) **et de la Phase 5** (l'UI de saisie AR réutilise le design system).*

**Versions cibles** : `aod-cod-form` v1.30.0+ · `aod-client-dashboard` v0.29.0+.

**Le fil rouge de la phase** : le contenu marchand est **du texte saisi par le
gérant** — `gettext` ne le traduira jamais. Chaque contenu porte donc son couple
`{ fr, ar }` (méta parallèle `_aod_*_ar`, **jamais** le champ cœur), rendu avec
repli bidirectionnel (`AOD_COD_Data::label()` / `AOD_COD_Landing::text()`). Le
**chrome** (libellés d'UI) reste du `__()`. Confondre les deux = un site bilingue
partout sauf sur ce qui vend.

**Trois invariants, valables à chaque étape :**

- **Un produit sans traduction AR rend exactement comme aujourd'hui** (repli FR).
  C'est le premier test de chaque recette, pas le dernier.
- **Les filtres de rendu sont strictement publics et gardés** (`is_arabic()` +
  `! is_admin()` + type `product`). L'admin `/gestion`, les e-mails et les
  bordereaux transporteur restent en **FR** — le nom canonique FR sert au matching
  serveur ; le traduire casserait les commandes et les envois.
- **On ne touche jamais au champ cœur** (`post_title`/`post_content`/nom de terme) :
  la traduction vit dans une méta `_aod_*_ar`. Plugin désactivé, le produit reste
  intact et valide.

### Étape 7.0 — Extraire la section Produits du monolithe (L) — *préalable, refactor pur*
- Déplacer le bloc Produits/Catégories de `class-aod-cd-dashboard.php` (~1530 l.,
  méthodes `section_products` → `ajax_delete_category`) dans une classe dédiée
  **`class-aod-cd-section-products.php`**, branchée sur le routeur (patron des
  sections Home/Stock/Team/Landing déjà extraites).
- Promouvoir `render_search_bar()` et `render_pagination()` en `public static`
  (les autres helpers — `page_head`, `pill`, `kpis`, `empty_state` — le sont déjà).
- **Zéro changement fonctionnel.** Garder synchronisés les consommateurs du format
  d'options restés dans le monolithe (`resolve_item_options()`, `tier_unit_price()`,
  `reduce_pack_components_stock()`).
- **Recette** : les 135 tests unitaires + la recette navigateur de `/gestion`
  (Produits/Catégories : CRUD, options, offres, upload) passent à l'identique. Diff
  de rendu nul.

### Étape 7.1 — Socle bilingue catalogue (M)
- Convention de stockage : **méta parallèle `_aod_*_ar`** (produit) et **term-meta**
  (catégorie). Aucune migration : absence de `_ar` = repli FR.
- Helper de rendu mutualisé (réutilise `AOD_COD_Data::label()` ; pattern
  `AOD_COD_Landing::text()`), et l'armature des **filtres front-only gardés**
  (un point d'entrée unique qui décide « traduire ou non » : `is_arabic()`,
  `! is_admin()`, contexte produit) — pour ne pas réécrire la garde à chaque site.
- **Tests unitaires** : le repli (AR vide → FR, FR vide → AR), et surtout la
  **garde de portée** (en admin / hors contexte produit → jamais de swap).

### Étape 7.2 — Titre + description bilingues (M/L) — *cœur, risque filtres*
- Éditeur produit : champs AR pour titre et description (`render_pair`), sauvegarde
  `_aod_title_ar` / `_aod_desc_ar`.
- Rendu : filtres d'**affichage** gardés front-only — `the_title`, `the_content`
  (**priorité 9** : `wpautop` est en 10 ; après lui, l'AR sortirait sans `<p>`) et
  `single_post_title` (⚠️ il reçoit un **`WP_Post`**, pas un ID).
- 🚨 **Ne JAMAIS filtrer les getters WooCommerce** (`woocommerce_product_get_name`…) —
  la formulation initiale de cette étape le prescrivait ; c'était un piège, corrigé
  à la livraison. `$order->add_product()` (`class-aod-cod-form.php:1098`) appelle
  `$product->get_name()` **et PERSISTE** le résultat dans `order_item_name`, depuis
  la requête acheteur `wp_ajax_nopriv_aod_cod_submit`. Filtrer le getter graverait le
  nom **arabe** dans les commandes : bordereaux transporteur, e-mails et WhatsApp le
  rejoueraient à jamais, et le matching serveur sur le nom FR canonique casserait —
  **irrattrapable** pour les commandes déjà passées. Les rares rendus **bruts** qu'on
  possède (héro landing, liste de pack) appellent `AOD_COD_Catalog_I18n::render()`
  explicitement, chez eux.
- **Fix héro landing** : `render_hero()` tire `$product->get_name()` **brut**
  (`class-aod-cod-landing.php:777`) — il ne passe PAS par `the_title` ; le brancher
  explicitement sur `_aod_title_ar`, sinon le titre y reste FR en mode arabe.
- **Recette** : produit traduit → titre/description AR sur fiche, grille accueil,
  Boutique, `<title>`, fil d'ariane **et** héro landing ; **admin + e-mail de
  commande restent FR** ; produit non traduit inchangé.

### Étape 7.3 — Arguments de vente bilingues (S/M)
- `_aod_selling_points` → couple `{ fr, ar }` (ou méta `_ar` parallèle), champs AR
  dans l'éditeur, rendu bilingue au seul site d'affichage (`class-aod-cod-form.php`
  ~786-815, réutilisé par le formulaire COD et la landing).
- **Recette** : arguments AR affichés en arabe, repli FR sinon.

### Étape 7.4 — Variantes bilingues (M/L)
- `_aod_options` : vraie saisie AR des **libellés de section** et **noms de valeurs**
  (l'UI existe déjà en couple ailleurs). Le **dictionnaire `variant_dictionary()`
  devient le repli** derrière la saisie du gérant — plus jamais la seule source.
- **Le nom canonique FR reste la clé de matching serveur** (offres/options d'une
  ligne de commande) : la traduction est purement d'affichage.
- **Recette** : variante saisie en AR affichée telle quelle ; mot non saisi →
  dictionnaire ; mot inconnu du dictionnaire → FR ; commande créée intacte.

### Étape 7.5 — Catégories bilingues (M/L) — *le plus délicat pour la portée*
- Term-meta `_aod_cat_name_ar`, champ AR dans la gestion des catégories.
- Rendu : filtres sur les noms de termes (`get_term` / `single_term_title` /
  `get_terms`) **strictement publics** — la gestion des catégories dans `/gestion`
  et l'admin WP doivent continuer d'afficher le nom FR.
- **Recette** : noms de catégories AR sur grille accueil, Boutique, fil d'ariane ;
  **édition des catégories dans `/gestion` reste en FR** (sinon le gérant ne s'y
  retrouve plus) ; catégorie non traduite inchangée.

### Étape 7.6 — i18n + recette de la phase (S/M)
- `make-translations.sh --strict` vert sur les 2 plugins (nouvelles chaînes de
  chrome uniquement — le contenu marchand ne passe pas par les `.po`).
- Recette navigateur FR/AR/RTL mobile 390 px : un produit **entièrement traduit**
  affiche l'AR sur les 10 sites de rendu, un produit **non traduit** est identique
  à l'avant-phase, et **l'admin/les e-mails/les bordereaux restent FR** (la garde
  de portée, vérifiée pour de vrai).
- `docs/ARCHITECTURE.md` §6 et §7 (dette n°7) mis à jour (le contenu marchand devient
  bilingue hors landing — la dette la plus ancienne du template, soldée).

---

## Phase 8 — Description produit riche (éditeur WYSIWYG)

*Planifiée le 2026-07-17 (cf. verdict A.8). **Dépend de la Phase 7** : le couple
description `{ fr, ar }` existe (7.2) et le rendu AR passe déjà par `the_content`
(prio 9) — l'éditeur s'applique aux DEUX cases. S'appuie sur la Phase 5 (design
system de `/gestion`). À lancer **après la 7.6** (clôture de la Phase 7).*

**Versions cibles** : `aod-client-dashboard` v0.34.0+ · `aod-cod-form` v1.35.0+.

**Le fil rouge de la phase** : **aucune dépendance externe** (TinyMCE du cœur WP,
pas de CDN — il n'y a pas d'internet sortant) et **aucun nouveau pouvoir en
base** : la whitelist `wp_kses_post()` existante reste la frontière de ce que la
description peut contenir ; la barre d'outils n'offre **que** ce que kses laisse
passer. La vidéo n'est **jamais** un iframe stocké : un lien, converti en façade
au rendu (pattern 6.3).

**Trois invariants, valables à chaque étape :**

- **Un produit à description plate rend exactement comme aujourd'hui.** L'éditeur
  n'est qu'une UI de saisie : le format en base reste du HTML `wp_kses_post`,
  aucune migration, aucun nouveau champ.
- **Le couple `{ fr, ar }` est préservé** : les deux cases reçoivent le même
  éditeur, la case AR en RTL (`directionality`) ; AR vide → repli FR intact (7.2).
- **Mobile d'abord** : la barre d'outils reste utilisable à 390 px — le gérant
  gère sa boutique au téléphone.

### Étape 8.1 — Éditeur riche sur le couple description (M/L)
- Brancher TinyMCE (embarqué WP, `wp_enqueue_editor()` + `wp.editor.initialize()`)
  sur les deux textareas du couple description — via un nouvel argument `rich`
  de `render_pair()` ; **tous les autres appels restent en textarea nu**.
- Barre courte : gras, italique, titre/sous-titre (H2/H3), liste à puces /
  numérotée, alignement, lien, annuler/rétablir. **Pas** de couleur ni de police
  libres : hors whitelist kses pour partie, et un catalogue de polices casserait
  la cohérence du thème du clone.
- Case AR : `directionality: 'rtl'` + `lang: 'ar'` ; l'UI TinyMCE suit la locale
  (packs cœur — sans réseau, elle reste en anglais : acceptable, les libellés de
  chrome autour restent traduits par nos `.po`).
- ⚠️ **Le piège connu de l'étape** : le formulaire produit est enregistré en
  **AJAX** avec une sérialisation manuelle des champs — appeler
  `tinymce.triggerSave()` AVANT de sérialiser, sinon la description postée est
  celle d'**avant** la saisie (perte silencieuse, aucune erreur à l'écran).
- **Recette** : mise en forme saisie (FR et AR) → conservée en base (kses) et
  rendue sur la fiche produit dans les deux langues (RTL compris) ; produit
  existant à description plate rendu **à l'identique** ; le formulaire produit
  complet (options, offres, images, packs) fonctionne toujours ; 390 px.

### Étape 8.2 — Photos dans la description (M)
- Bouton image dans la barre → téléversement par le mécanisme **déjà en place**
  dans le formulaire produit (`media_handle_upload`, mêmes contrôles serveur),
  insertion `<img>` (taille `large`, `loading="lazy"`) au point d'insertion.
- Même parcours d'upload que le reste de `/gestion` → couvert par la sonde
  uploads de `preflight.sh`/`setup.sh` (TEMPLATE.md §3).
- **Recette** : image insérée dans la description FR et AR, visible côté public
  dans les deux langues, `lazy` ; l'envoi échoue proprement (message, pas de
  perte de saisie) si l'upload est refusé.

### Étape 8.3 — Vidéo dans la description (M)
- Bouton vidéo → le gérant colle un **lien YouTube** ; en base, un marqueur
  inoffensif pour `wp_kses_post()` (le lien lui-même) ; au rendu public, la
  **façade** cliquable du pattern 6.3 : **0 iframe en base, 0 requête YouTube
  avant le clic** (perf < 3 s préservée, aucun domaine tiers au chargement).
- `wp_kses_post()` reste **inchangé** — on n'autorise pas l'iframe, on n'en a
  pas besoin.
- **Recette** : vidéo lisible au clic en FR et en AR ; aucune requête YouTube
  avant le clic (vérifié réseau) ; lien invalide → rendu texte inoffensif.

### Étape 8.4 — i18n + recette de la phase (S)
- `make-translations.sh --strict` vert sur les 2 plugins (les nouveaux libellés
  de chrome : boutons, messages d'erreur d'upload — TinyMCE lui-même est traduit
  par les packs cœur, hors périmètre `.po`).
- Recette navigateur FR/AR/RTL 390 px : un produit à description **riche**
  (titres, listes, image, vidéo) correct dans les deux langues ; un produit à
  description **plate** identique à l'avant-phase ; l'enregistrement produit
  complet intact ; admin/e-mails/bordereaux inchangés (la description n'y entre
  pas — vérifier que ça reste vrai).

---

## Phase 9 — Catégories sur l'accueil : sélection « à la une » + ordre

*Planifiée le 2026-07-18 (cf. verdict A.9). **Indépendante** des Phases 6–8 :
elle ne touche ni la landing ni la description. S'appuie sur la Phase 5 (design
system de `/gestion`) et sur le filtre `get_term` de la 7.5 (i18n des noms de
catégorie, à préserver).*

**Versions cibles** : `aod-client-dashboard` v0.37.0+ · `aod-cod-form` v1.36.0+.

**Le fil rouge de la phase** : **aucun nouveau moteur de rendu**. L'accueil
continue de passer par `[product_categories]` — on ne fait que **piloter ses
arguments** (`ids`, `orderby`, `hide_empty`) depuis une sélection stockée en
term-meta. Rien en dur, tout configurable (règle n°1).

**Trois invariants, valables à chaque étape :**

- **Repli non-régressif.** Aucune catégorie cochée ⇒ l'accueil rend **comme
  aujourd'hui** (toutes les catégories non vides, ordre alphabétique). Un clone
  déjà en ligne ne doit **jamais** perdre ses catégories du fait de la mise à jour.
- **Bilinguisme préservé.** Le swap AR des noms passe par `get_term` (7.5) et
  `[product_categories]` l'appelle pour chaque terme : la sélection/l'ordre ne
  doivent **rien** y changer. À prouver, pas à reconstruire.
- **Mobile d'abord.** La case et le champ d'ordre restent utilisables à 390 px
  dans la ligne de catégorie déjà dense (précédent 7.4/7.5 : `render_pair()`
  écarté sur une ligne dense).

### Étape 9.1 — Réglage « Afficher sur l'accueil » + ordre dans `/gestion` (données + UI) (S/M)
- Deux term-metas sur `product_cat`, posées/lues par `ajax_save_category()` :
  **`_aod_cat_home`** (`'1'` = affichée sur l'accueil, méta **supprimée** si
  décochée — même convention « absence = valeur par défaut » que `_aod_cat_name_ar`
  en 7.5) et **`_aod_cat_home_order`** (entier ; défaut stable si vide).
- UI dans `render_category_row()` : une case **« Afficher sur l'accueil »** + un
  champ d'ordre, à côté de l'image et du nom AR. Libellés de **chrome** → `__()`
  (pas de couple `{ fr, ar }`). Repostés par **tous** les sites JS d'enregistrement
  de la ligne (piège 7.5 : un site oublié efface la valeur au 1ᵉʳ save).
- Bornage serveur : flag ∈ {présent, absent}, ordre casté en entier. `check_ajax_referer`
  + capability déjà en place — aucune surface AJAX nouvelle.
- **Recette** : cocher/décocher persiste ; l'ordre se relit ; une catégorie **vide**
  peut être cochée ; décocher **supprime** la méta (pas de `'0'` fantôme en base) ;
  le nom AR et l'image de la 7.5 restent intacts au passage ; 390 px.

### Étape 9.2 — Accueil piloté par la sélection + retrait du plafond (S/M)
- `render_home()` (`class-aod-cod-pages.php:180`) : construire la **liste ordonnée**
  des `term_id` où `_aod_cat_home === '1'` (triés par `_aod_cat_home_order`), puis
  rendre `[product_categories ids="…" hide_empty="0" columns="3"]` **sans `limit`**.
  ⚠️ **Point d'implémentation à trancher dans la story** : `[product_categories]`
  ré-ordonne selon son `orderby` — vérifier que l'ordre de la sélection est **tenu**
  (via `orderby="include"` si supporté, sinon rendu maison minimal réutilisant le
  markup de la carte). Ne pas présumer que `ids="3,7,12"` sort dans cet ordre.
- **Repli non-régressif** (l'invariant de la phase) : **aucune** catégorie cochée
  ⇒ retomber sur `[product_categories columns="3" hide_empty="1"]` (toutes les non
  vides, **plafond retiré**) — le comportement d'aujourd'hui, moins le `limit="6"`.
- Vérifier que le swap AR (`get_term`, 7.5) rend toujours les noms arabes, et que la
  section entière reste masquée s'il n'y a réellement **rien** à afficher (test
  `trim(...) !== ''` conservé).
- **Recette** : catégorie créée **puis cochée**, **sans produit** → **visible** sur
  l'accueil ; 3 catégories cochées avec ordres 2/1/3 → affichées 1‑2‑3 ; > 6
  catégories cochées → **toutes** s'affichent (plafond parti) ; **aucune** cochée →
  rendu identique à l'avant-phase (repli) ; AR + RTL, 390 px.

### Étape 9.3 — i18n + recette de la phase (S)
- `make-translations.sh --strict` vert sur les **2 plugins** (nouveaux libellés :
  « Afficher sur l'accueil », « Ordre » — chrome, donc `.po`).
- Recette navigateur **rejouable par clone** (dans `tools/recette/`, incluse dans
  `public`) : seed d'une catégorie vide cochée + de catégories ordonnées, preuve
  visiteur que la sélection et l'ordre pilotent l'accueil en FR **et** AR/RTL à
  390 px, et que le **repli** (aucune sélection) rend comme avant. Purge en `trap`.
- **Phase rouge** : décocher une catégorie la fait **disparaître** de l'accueil ;
  vider la sélection restaure le repli. DoD [C.2](#c2-definition-of-done-commune-toute-étape).

---

## Phase 10 — API de Conversion Meta (CAPI) : Facebook/Instagram

*Planifiée le 2026-07-18 (cf. verdict A.10). **Indépendante** de toutes les autres
phases. Double le pixel navigateur Meta d'un envoi serveur-à-serveur des conversions,
dédupliqué par l'`event_id` déjà en place.*

**Versions cibles** : `aod-cod-form` v1.37.0+ · `aod-client-dashboard` v0.38.0+.

**Le fil rouge de la phase** : **ne rien réinventer**. L'`event_id` (= n° de commande)
existe déjà des deux côtés du pont (payload serveur `purchase_payload()` + `eventID`
du `fbq` navigateur) ; la devise convertie passe déjà par `to_reporting()`. La CAPI
n'ajoute qu'**un appel `graph.facebook.com`** sur le hook `aod_cod_order_created`, avec
les **PII hachées SHA-256** et le token gardé secret. Faire diverger l'`event_id` des
deux côtés = achats comptés en double par Meta.

**Trois invariants, valables à chaque étape :**

- **Opt-in total.** Token vide ⇒ **aucun** appel réseau, comportement identique à
  aujourd'hui (pixel navigateur seul). C'est le premier test de la recette.
- **L'acheteur passe avant le marketing.** L'envoi CAPI est **fail-open** : Meta lent
  ou injoignable ne retarde ni ne casse **jamais** la création de commande.
- **Aucun secret en clair.** Le token n'est ni loggé ni ré-affiché ; les PII partent
  hachées SHA-256, jamais en clair.

### Étape 10.1 — Réglage token + envoi serveur Purchase dédupliqué (M)
- Nouvelle clé `aod_cod_pixels['meta_capi_token']` (défauts `AOD_COD_Pixels::settings()`
  `:51-66` **et** le miroir `pixels_settings()` du dashboard `:2358-2372`), champ
  `type=password` dans la carte Meta (`section_marketing()` `:2404-2412`), persistée
  par `ajax_save_pixels()` (`:2506-2515`) et `maybe_save()` admin (`:410-419`).
  **Masquage à l'affichage** (jamais le token en clair — ré-afficher un placeholder,
  ne réécrire que si un nouveau token est saisi).
- Méthode `send_capi_purchase( $order )` dans `class-aod-cod-pixels.php` :
  `wp_remote_post` vers `https://graph.facebook.com/vXX.0/{meta_pixel_id}/events`
  (modèle Turnstile `class-aod-cod-turnstile.php:137-155`), corps
  `{ data:[{ event_name:'Purchase', event_id, event_time, action_source:'website',
  user_data:{…SHA-256…}, custom_data:{ value, currency, content_ids } }], access_token }`.
  **`event_id` = n° de commande** (le **même** que `purchase_payload():367-368`),
  valeur/devise via `to_reporting()` (`:96-103`).
- Branchement sur `do_action('aod_cod_order_created', $order)`
  (`class-aod-cod-form.php:1239`) : découplé, `$order` complet, **indépendant** de
  `_aod_pixels_fired` (le flag est posé plus tard par `purchase_payload()`).
- **Recette** : token vide → **0 requête réseau** (vérifié) ; token posé → une requête
  Meta après commande, réponse `events_received:1` ; l'`event_id` serveur **==** celui
  du `fbq` navigateur (déduplication) ; Meta HS → commande créée quand même.

### Étape 10.2 — `test_event_code` + bouton « Tester » + robustesse (S/M)
- Réglage **optionnel** `meta_test_event_code` (débogage dans le Gestionnaire
  d'événements Meta) ; joint au payload s'il est renseigné.
- Bouton « Tester la connexion » (AJAX dédié, nonce + `manage_woocommerce`) qui envoie
  un événement de test et rapporte le retour Meta — **sans jamais afficher le token**.
  Erreurs propres (jeton invalide, pixel inconnu) sans casser la page.
- **Recette** : un test réussi apparaît dans le Gestionnaire d'événements (onglet Test) ;
  un token invalide → message d'erreur clair, aucune fuite du token dans le DOM/les logs.

### Étape 10.3 — i18n + recette de la phase (S)
- `make-translations.sh --strict` vert sur les 2 plugins (chrome : « Jeton d'API
  Conversions », « Code d'événement test », « Tester la connexion », messages d'erreur).
- Recette FR/AR/RTL mobile 390 px de la carte Meta ; **preuve réseau** (bloc DNS
  `dns:[8.8.8.8,1.1.1.1]` requis, TEMPLATE.md §3) : commande réelle → événement reçu
  par Meta, dédupliqué avec le pixel. MAJ `docs/ARCHITECTURE.md` (§2 : nouvelle clé
  `meta_capi_token` ; module Pixels).

---

## Phase 11 — Commandes abandonnées dans `/gestion`

*Planifiée le 2026-07-18 (cf. verdict A.11). **Indépendante** côté données. Porte dans
`/gestion` les prospects déjà capturés dans `{prefix}aod_cod_leads`, pour que le gérant
(et les confirmateurs habilités) relancent les paniers abandonnés.*

**Versions cibles** : `aod-client-dashboard` v0.38.0+ · `aod-cod-form` **non touché**
(le back-end leads existe déjà — sauf si la recette révèle un manque).

**Le fil rouge de la phase** : **aucune nouvelle capture, aucune migration**. La donnée
existe (`status='pending'` = abandonné) ; on la **présente** dans `/gestion` en
répliquant le patron de la section « Commandes ». La table porte des **PII** → lecture
gardée (capability + périmètre), **jamais** de `nopriv`.

**Trois invariants, valables à chaque étape :**

- **Un lead `converted` n'est jamais un abandon.** La page ne liste que les `pending` ;
  `mark_converted()` (déjà appelé par `aod_cod_submit`) fait foi.
- **Le périmètre du confirmateur tient sur les leads.** Un confirmateur restreint ne
  voit que les leads de son périmètre (colonne `product_id`), filtré **côté SQL** —
  jamais un simple masquage d'UI.
- **Mobile d'abord.** Le tableau (nom/tél/produit/wilaya + actions Appeler/WhatsApp)
  reste utilisable à 390 px.

### Étape 11.1 — Capability `aod_cap_abandoned` + Équipe (S)
- Nouvelle capability `aod_cap_abandoned` ajoutée à `AOD_CD_Roles::CONFIRMER_CAPS`
  (resync versionné `maybe_sync_role()`) ; le gérant (`manage_woocommerce`) la possède
  d'office via `AOD_CD_Roles::user_can()`. Case supplémentaire dans la fiche membre de
  la section Équipe (`class-aod-cd-section-team.php`).
- Carte section → capability : `abandoned` → `aod_cap_abandoned` (`$section_caps`).
- **Recette** : un confirmateur sans la cap ne voit pas l'onglet **ni** n'atteint
  `/gestion/abandoned` (403 sur l'AJAX/le rendu) ; avec la cap, il le voit ; le gérant
  l'a toujours.

### Étape 11.2 — Section « Commandes abandonnées » (nouvelle classe) (M)
- **Nouvelle classe `class-aod-cd-section-abandoned.php`** (DoD : nouvelle section =
  nouvelle classe) + entrée `abandoned` dans `sections()` (`:52-69`), groupe nav
  `ventes` (`:251-263`), dispatch (`render_section()`), routable via `/gestion/abandoned`.
- Contenu calqué sur `section_orders()` : `page_head()`, recherche
  (`render_search_bar()`), **onglets « Abandonnés (> 30 min) / En cours (< 30 min) »**
  (seuil `leads.php:261`), tableau `aod-cd-table--cards` (Quand, Nom, Téléphone,
  Produit, Wilaya·Commune, Livraison, Qté, Actions), `render_pagination()`. Requête sur
  `AOD_COD_Leads::table()` `WHERE status='pending'`, triée `updated_at DESC`. Boutons
  **Appeler** (`tel:`) + **WhatsApp** (`wa_number()`), produit via `wc_get_product()`,
  wilaya via `AOD_COD_Data::wilaya_name()`.
- **Périmètre confirmateur** : filtrer sur `product_id ∈ assigned_products()` **en SQL**
  (borné le temps de la requête, patron `scoped_orders()`) ; badge « Produits : n »
  comme ailleurs. La suppression d'un lead (déjà en wp-admin) peut être portée ou non —
  à trancher dans la story (une relance n'exige pas de supprimer).
- (À trancher en recette) exposer aussi les `converted` récents en lecture, avec lien
  vers la commande — utile pour mesurer le taux de récupération, mais à ne pas laisser
  alourdir la page.
- **Recette** : un formulaire rempli sans « Commander » apparaît en « abandonné » ;
  cliquer « Commander » le fait **disparaître** (passé `converted`) ; Appeler/WhatsApp
  ouvrent le bon numéro DZ (`213…`) ; un confirmateur restreint ne voit que ses leads
  (y compris par URL forgée → 403 hors périmètre).

### Étape 11.3 — i18n + recette de la phase (S)
- `make-translations.sh --strict` vert (chrome : « Commandes abandonnées »,
  « Abandonné », « En cours », « Appeler », « Relancer sur WhatsApp »).
- Recette navigateur FR/AR/RTL mobile 390 px ; MAJ `docs/ARCHITECTURE.md` (§3 : nouvelle
  section visible dans le menu, groupe Ventes ; § rôles : nouvelle cap
  `aod_cap_abandoned` dans `CONFIRMER_CAPS`).

---

## Phase 12 — Confirmée → expédiée « se déplace » vers Suivi commande

*Planifiée le 2026-07-18 (cf. verdict A.12). **Indépendante** des autres phases. Fait
quitter à une commande expédiée la liste « Commandes » pour qu'elle ne vive plus que
dans « Suivi commande ».*

**Versions cibles** : `aod-client-dashboard` v0.38.0+ · `aod-cod-form` **non touché**.

**Le fil rouge de la phase** : **un seul critère, appliqué partout où il compte**.
« Expédiée » = `_aod_ship_tracking` non vide (le colis existe chez le transporteur).
Une commande expédiée sort de « Commandes » ; une commande en **erreur d'envoi** (pas
de tracking) y **reste**. Aucun changement de statut WooCommerce, aucune donnée nouvelle.

**Deux invariants, valables à chaque étape :**

- **Rien ne se perd.** Une commande sortie de « Commandes » est **forcément** dans
  « Suivi » (même critère `_aod_ship_tracking`) ; une commande en erreur d'envoi reste
  visible dans « Commandes » (elle n'est pas dans Suivi).
- **Compteurs cohérents.** Les compteurs d'onglets de « Commandes » suivent l'exclusion
  — pas d'onglet qui promet N commandes et en affiche N−k.

### Étape 12.1 — Exclure les commandes expédiées de « Commandes » (S)
- `section_orders()` (`class-aod-cd-dashboard.php:690-702`) : ajouter un `meta_query`
  `relation=OR` → `_aod_ship_tracking` `NOT EXISTS` **ou** `= ''`. Répliquer **le même**
  `meta_query` dans `count_orders()` (`:1617-1640`) pour les compteurs d'onglets.
  Pattern `wc_get_orders + meta_query` déjà éprouvé par `section_tracking()`.
- Vérifier l'interaction avec le **périmètre confirmateur** (`scoped_orders()`) et avec
  chaque onglet de statut (all/pending/confirmed/completed/cancelled).
- **Recette** : une commande confirmée **non** expédiée reste dans Commandes ; dès
  l'envoi (tracking posé) elle **disparaît** de Commandes et **apparaît** dans Suivi ;
  une commande en **erreur d'envoi** reste dans Commandes ; les compteurs collent au
  contenu réel ; un confirmateur restreint voit ce comportement sur son seul périmètre.

### Étape 12.2 — i18n + recette de la phase (S)
- Peu (ou pas) de chaîne nouvelle ; `make-translations.sh --strict` vert.
- Recette navigateur FR/AR/RTL mobile : le **cycle complet d'une vraie commande** —
  Confirmée → envoi transporteur → bascule Commandes⇢Suivi → suivi colis — vérifié de
  bout en bout (données seedées). MAJ `docs/ARCHITECTURE.md` (§3 : « Commandes »
  n'affiche plus les commandes expédiées, désormais dans « Suivi »).

---

## Phase 13 — Conversions serveur TikTok + Snapchat (généralisation CAPI)

*Planifiée le 2026-07-18 (cf. verdict A.13). **Indépendante** des autres phases ;
généralise le mécanisme serveur de la Phase 10 (Meta) à TikTok et Snapchat.*

**Versions cibles** : `aod-cod-form` v1.38.0+ · `aod-client-dashboard` v0.41.0+.

**Le fil rouge de la phase** : **le même id des deux côtés**. L'`event_id` serveur
(= n° de commande) doit être émis **aussi par le pixel navigateur** (TikTok `event_id`,
Snapchat `client_dedup_id`), sinon la plateforme compte l'achat deux fois. Tout le reste
(opt-in, PII SHA-256, `to_reporting`, fail-open, jeton secret) est **déjà écrit** en
Phase 10 — on le **mutualise**, on ne le recopie pas.

**Trois invariants, valables à chaque étape :**

- **Opt-in total.** Jeton vide ⇒ aucun appel réseau ; le pixel navigateur seul, comme
  aujourd'hui. Premier test de chaque recette.
- **Déduplication prouvée.** L'id serveur == l'id navigateur, vérifié en recette (le
  piège même de la phase).
- **Fail-open, secret protégé.** Une plateforme injoignable ne casse jamais la commande ;
  le jeton n'est ni loggé ni ré-affiché.

### Étape 13.1 — Socle serveur mutualisé + TikTok Events API (M)
- **Refactor d'abord** (DoD : on ne recopie pas trois fois) : extraire de
  `send_capi_purchase()` (Meta) le tronc commun — opt-in, `user_data` haché,
  `to_reporting`, `wp_remote_post` fail-open, garde d'envoi unique — en un helper
  réutilisable ; **Meta devient le premier appelant, sans changement de comportement**
  (la recette `run.sh capi` doit rester verte à l'octet).
- **TikTok** : clé `aod_cod_pixels['tiktok_capi_token']` (+ champ dans la carte TikTok),
  méthode d'envoi → `POST …/event/track/` (`CompletePayment`, `event_id`, `user` haché) ;
  **ajouter `{ event_id: px.event_id }` au `ttq.track()` navigateur** (dédup) et l'exposer
  dans `purchase_payload()` si besoin.
- **Recette** : jeton vide → 0 requête ; jeton posé → une requête TikTok ; `event_id`
  serveur **==** `event_id` du `ttq` ; **Meta toujours vert** (non-régression du refactor).

### Étape 13.2 — Snapchat Conversions API (M)
- Clé `aod_cod_pixels['snapchat_capi_token']` (+ champ carte Snapchat), méthode d'envoi
  via le **socle 13.1** (`PURCHASE`, `client_dedup_id`, identifiants hachés) ; **ajouter
  `client_dedup_id: px.event_id` au `snaptr('track','PURCHASE',…)` navigateur**.
- **Recette** : symétrique de 13.1 pour Snapchat (opt-in, envoi, dédup).

### Étape 13.3 — Boutons « Tester la connexion » + robustesse (S/M)
- Un bouton « Tester » par plateforme (AJAX dédiés), sur le modèle
  `test_capi_connection()` de la 10.2 — verdict lisible, **jeton jamais exposé**, erreurs
  propres. `test_event_code` TikTok si l'API le propose.
- **Recette** : test réussi / jeton invalide → message clair, aucune fuite du jeton.

### Étape 13.4 — i18n + recette de la phase (S)
- `make-translations.sh --strict` vert ×2 (chrome des cartes + boutons + messages).
- Recette rejouable étendant `scripts/recette-capi.php` (porte `capi`, ou nouvelle porte
  `conversions`) : envoi serveur **TikTok et Snapchat** prouvé par interception HTTP
  (0 appel réel), **et** présence de l'id de dédup dans les appels navigateur. MAJ
  `docs/ARCHITECTURE.md` §2 (nouvelles clés `*_capi_token`) + §3 + module Pixels.

---

## Phase 14 — Sortir les conversions serveur du chemin d'achat

*Planifiée le 2026-07-19 (cf. verdict A.14, issu de la revue 13.x). **Dépend de la
Phase 13** : elle déplace les trois envois qu'elle a mis en place. Aucune nouvelle
régie, aucune nouvelle capability — c'est un changement de **modèle d'exécution**.*

**Versions cibles** : `aod-cod-form` v1.41.0+.

**Le fil rouge de la phase** : **l'acheteur ne doit rien attendre du marketing.**
La commande est créée, la réponse part ; les conversions suivent. Ce qui se déplace,
ce sont les appels réseau — **pas** la qualité des données envoyées.

**Trois invariants, valables à chaque étape :**

- **Aucune perte de correspondance.** Les cookies d'appariement (`_fbp`/`_fbc`,
  `_ttp`/`ttclid`, `_scid`/`_scclick`) sont **capturés au moment de la requête
  acheteur** et relus par l'envoi différé. Une conversion différée doit porter
  **exactement** les mêmes identifiants qu'aujourd'hui — prouvé en recette, payload
  contre payload.
- **Aucun achat perdu.** « Mis en file » n'est pas « envoyé ». Un envoi planifié et
  jamais exécuté doit rester **rejouable**, jamais compté comme fait.
- **Aucun doublon.** La déduplication navigateur ↔ serveur (`event_id` /
  `client_dedup_id` = n° de commande) est **indépendante du moment** de l'envoi : elle
  doit tenir à l'identique.

**Statut : ✅ SOLDÉE (1/1) le 2026-07-19.** Mécanisme retenu (D3) :
`wp_schedule_single_event()`, cohérent avec le cron déjà en place pour le suivi
colis ; retard borné à 15 min en production, sans effet sur l'attribution, écrit
dans [CRON-SERVEUR.md](CRON-SERVEUR.md).

### Étape 14.1 — Capture des cookies + envoi différé des trois régies (M/L) ✅
- **Capturer d'abord** : à la création de la commande, ranger les 6 cookies
  d'appariement dans une méta (`_aod_conv_cookies`, tableau) — c'est le **seul** moment
  où ils existent. Les trois `send_*_purchase()` lisent cette méta **avec repli sur
  `$_COOKIE`** (donc comportement inchangé si l'envoi reste synchrone : le refactor est
  neutre avant même d'être branché).
- **Puis déplacer** : `aod_cod_order_created` planifie au lieu d'envoyer. Distinguer la
  garde « en file » de la garde « envoyée » (`_aod_capi_*_sent` conserve son sens actuel
  = **envoyé**).
- **Trancher et documenter** le mécanisme (`wp_schedule_single_event` vs alternative) et
  le retard maximal assumé, en cohérence avec `DISABLE_WP_CRON` + cron serveur 15 min.
- Recette : porte `conversions` étendue — payload différé **identique** au payload
  synchrone (cookies compris), rejouabilité après cron mort, dédup intacte. MAJ
  `docs/ARCHITECTURE.md` §2 + `docs/CRON-SERVEUR.md` (le cron devient nécessaire aux
  conversions, plus seulement au suivi colis — c'est une **aggravation de l'enjeu** du
  document, à dire explicitement).

---

## Phase 15 — Icônes vectorielles + charte teintée de `/gestion`

*Planifiée le 2026-07-19 (cf. verdict A.15). Spécification :
[`ICONES-ET-CHARTE-GESTION.md`](ICONES-ET-CHARTE-GESTION.md). Périmètre
**`/gestion` uniquement** — la couche acheteur n'est pas touchée.*

**Versions cibles** : `aod-client-dashboard` v0.45.0+.

**Le fil rouge de la phase** : **rien de la marque n'est écrit en dur.** Les icônes
sont monochromes et héritent de `currentColor` ; la sidebar teintée est **dérivée**
de l'accent du Personnalisateur. Un clone qui change d'accent change de charte
entière sans qu'une ligne de CSS bouge.

**Trois invariants, valables à chaque étape :**

- **Aucune couleur de client dans le dépôt.** Ni `--cd-side` en dur, ni
  `DEFAULT_ACCENT` modifié. Le vert MOB est une **valeur saisie dans le
  Personnalisateur de ce clone**. Un test doit interdire la régression.
- **Aucune perte d'information sémantique.** Les 7 tonalités de KPI
  (`t-blue`…`t-slate`) sont conservées à l'identique. Une icône ne devient jamais
  décorative au point de faire lire un échec comme un succès.
- **Aucune section orpheline.** Toute entrée de `sections()` a une icône au
  catalogue. Un nom inconnu ne rend **rien** et crie en `WP_DEBUG` — jamais un trou
  silencieux dans la nav.

**Statut : ✅ soldée (10/10), le 2026-07-20.** Livrées : **15.0** (recette réparée), **15.1**
(SVG Phosphor versionnés), **15.2** (`AOD_CD_Icon` + tests), **15.3** (sidebar),
**15.4** (24 cartes de KPI), **15.7a** + **15.7b** (collatéral — le périmètre réel
était de ~60 occurrences, pas ~25, d'où le découpage), **15.5** (charte teintée)
et **15.6** (nettoyage CSS).

**L'espace gérant ne contient plus aucun emoji de chrome**, et le fond de sa
sidebar est **dérivé de l'accent** du Personnalisateur — donc teinté aux couleurs
du client sans qu'aucune couleur de client ne soit écrite dans le template.
**15.8** est livrée : l'accent est passé au vert `#00A651`, choisi sur mesure —
les verts foncés de club, réflexe naturel, sont **indiscernables** du vert
sémantique `--cd-ok` (1,03 à 1,09:1). La couleur vit dans le Personnalisateur de
CE clone ; `DEFAULT_ACCENT` reste le jaune du template. Note de périmètre : `dashboard.css` comptait **26** `transition` implicites, 15.6
en a corrigé **4** (la zone sidebar). Les **22** restantes font l'objet de la
**Phase 16** — elles demandent un relevé d'états par composant, pas un `sed`.

✅ **Entrée résolue** : le hex du club n'a jamais été fourni ; l'owner a délégué le choix. Sans blocage pour les étapes 15.1 → 15.4 et 15.6 → 15.7
(les icônes sont agnostiques à la charte). Levée le 2026-07-20 : choix délégué
par l'owner, tranché sur mesure — cf. spec §9.

### Étape 15.0 — Réparer `SECTIONS` dans la recette (S) 🔴 préalable
- `tools/recette/lib.js` listait `'stats'` — slug **supprimé en Phase 2** (renommé
  `home`) — et omettait `home` et `abandoned`. `maybe_render()`
  (`class-aod-cd-dashboard.php:327-329`) retombe **silencieusement** sur la première
  section autorisée quand le slug est inconnu.
- Impact réel, vérifié : **`abandoned` n'était jamais visitée** (vrai trou) ; `home`
  l'était **par accident**, via le repli de `'stats'` — les 18 KPI étaient donc
  balayés, mais un échec aurait été rapporté sur une page **qui n'existe pas**.
- Cible : les **12 sections de menu**. `landing` reste exclue — elle exige
  `?produit=<id>` (sinon état vide « Produit introuvable ») et a sa propre porte
  `landing-produit.js`.
- À faire **avant** tout le reste : un balayage qui se trompe de page ne le dit pas,
  il compte quand même.

### Étape 15.1 — Récupérer et versionner les SVG Phosphor (S)
- Récupération **sur l'hôte** (`@phosphor-icons/core`, MIT — vérifié le 2026-07-19 :
  `registry.npmjs.org` joignable depuis l'hôte, pas depuis les conteneurs), puis
  **versionnement dans le plugin** (`assets/icons/`). Aucune dépendance à l'exécution,
  aucun CDN : un clone sans réseau fonctionne.
- Deux graisses seulement : `regular` et `fill` (+ `light` pour les états vides).

### Étape 15.2 — `AOD_CD_Icon` : catalogue fermé + rendu + RTL (M)
- Modèle éprouvé : `AOD_COD_Landing::schema()`. **Rien ne vient de la base.**
- 🚨 Le piège documenté (dette 14) : **`wp_kses_post()` supprime les `<svg>`**, et les
  points d'injection actuels utilisent `esc_html()` — parfait pour un emoji,
  destructeur pour un SVG. D'où le catalogue fermé plutôt qu'un échappement différent.
- Drapeau « directionnelle » par icône (`Truck`, `ArrowUUpLeft`, `TrayArrowDown`…) :
  **jamais** de miroir sur toute la famille.
- `tests/unit/test-icones.php` : nom → fichier existant, aucune section orpheline,
  aucun `fill`/`stroke` en dur autre que `currentColor`.

### Étape 15.3 — Zone 1 : sidebar, 14 icônes (S/M)
- `sections()` (`:55-66`) + `:464` + point d'injection `:482`.
- État actif porté par la **graisse** (`regular` → `fill`), pas par la couleur — un
  signal qui tient quel que soit l'accent du client.
- 2 corrections de sens : Stock 📊 → `Stack` (un graphique évoque les *stats*, pas
  l'inventaire) ; Pixels 🎯 → `Crosshair` (la cible pleine lit « fléchettes »).

### Étape 15.4 — Zone 2 : KPI, 24 cartes (M)
- `kpis()` (`:990`, champ `$c[2]`) + `class-aod-cd-section-home.php:925-932`,
  `:1346-1354`, `:1629-1634` + `class-aod-cd-section-stock.php:500-503`.
- **Les 7 tonalités ne changent pas.** Seul le glyphe change.
- Au passage : `✖ ↩ ⚠ 🛣` sont aujourd'hui des caractères Unicode **monochromes**
  posés à côté d'emojis colorés — l'onglet Livraison est le pire écran du lot.

### Étape 15.5 — Sidebar dérivée + `readable_on()` recalibré (M)
- `--cd-side` = accent mélangé **85 %** vers `#181818` (calibration mesurée).
- 🚨 **Le couplage à ne pas rater** : `readable_on()` est appelé contre un `#181818`
  **écrit en dur** (cf. commentaire `class-aod-cd-customize.php:97`). Si la sidebar
  devient teintée, `--cd-accent-on-dark` est calibré contre **un fond qui n'existe
  plus**. Rien ne casse visiblement — le contraste se dégrade juste, en silence.
- `tests/unit/test-charte-sidebar.php` : balayage du cube (pas de 17), assertion sur
  le texte de nav **et** sur l'accent en texte sur la sidebar **dérivée**. Références
  mesurées : pire texte 7,19:1, pire blanc 11,20:1.

### Étape 15.6 — Nettoyage du CSS de la coque (S)
- `dashboard.css:124` — `filter:grayscale(0)` **orphelin** (rien ne pose jamais
  `grayscale(1)`) : sans objet une fois les emojis partis.
- `:119` — `transition:.16s` = `transition: all` déguisé : anime aussi `padding` et
  `border-radius`, donc layout + paint au lieu du seul compositeur. Cibler
  `background-color` et `color`.
- Ajouter `:active { transform: scale(0.98) }` sur `.aod-cd-navlink` : 13 items
  cliqués des dizaines de fois par jour, sans aucun accusé de réception aujourd'hui.
- Le stagger du menu mobile (`:185-193`) est **déjà correct** (durée < 300 ms, délais
  40 ms, neutralisé sous `prefers-reduced-motion`) : ne pas y toucher.

### Étape 15.7 — Zone 3 : collatéral, ~60 occurrences (scindée en 15.7a / 15.7b) (M)
- 12 états vides (`empty_state()`), tuiles d'alerte de l'Accueil (`:406-429`),
  accordéons du formulaire produit (`class-aod-cd-section-products.php:432-681`),
  éditeur de page de vente, boutons Stock et Abandonnées.

### Étape 15.8 — Charte appliquée + recette de clôture (S) ✅ livrée
- Accent retenu : **`#00A651`** (choix délégué par l'owner le 2026-07-20). Le
  critère décisif n'était pas l'esthétique mais la **collision sémantique** : un
  vert de club foncé contraste à 1,03-1,09:1 contre `--cd-ok` — marque et
  « succès » deviennent la même couleur. Cf. spec §9.
- Porte `contraste` rejouée après application : **0 occurrence sous AA**.
- MAJ `docs/ARCHITECTURE.md` (nouvelle classe + tokens dérivés) et
  `docs/UX-GESTION-REFONTE.md` §2.2, dont les tokens ont déjà dérivé du livré.

---

## Phase 16 — Les 22 `transition` implicites qui restaient de `/gestion`

**Statut : ✅ soldée (3/3), le 2026-07-20.** Issue de la Phase 15 : l'étape 15.6 a corrigé les
**4** transitions implicites de la zone sidebar. Il en **restait 22** dans
`dashboard.css` ; il n'en reste **aucune** aujourd'hui.

> ⚠️ **Périmètre : `dashboard.css` seul.** Le compte 26 / 4 / 22 n'a jamais couvert
> le plugin **public**, où subsistaient **3** transitions implicites
> (`aod-cod-form.css:422`, `:457`, et le CSS inline de `class-aod-cod-lang.php:188`)
> — **soldées le 2026-07-20**, hors phase. Le dépôt n'en compte plus aucune.
> La porte `run.sh mouvement`, elle, **ne les aurait jamais vues** : elle ne visite
> que `/gestion/` et ne lit que la feuille `dashboard.css`. Le plugin public reste
> non couvert. Cf. §C.5 (D1).

### Pourquoi ce n'est pas un `sed`

`transition:.15s` sans propriété vaut `all` : le navigateur anime aussi `padding`,
`border-radius`, `font-weight` — il repasse par le layout et le paint au lieu de
rester sur le compositeur — et toute propriété ajoutée demain devient animée sans
que personne ne l'ait décidé.

Mais **corriger à l'aveugle supprime des animations en silence**, et rien ne le
rattrape : ni les tests unitaires (ils ne lisent pas le CSS), ni les portes de
recette (elles mesurent le contraste et les noms accessibles, pas le mouvement).
Une transition tuée ne casse aucune assertion — elle rend juste l'interface
sèche, et personne ne saura pourquoi.

Le relevé du 2026-07-20 a fait apparaître **trois angles morts**, chacun capable
de faire écrire une liste de propriétés fausse à qui ne regarde que `:hover` :

| Angle mort | Exemple | Ce qu'on manque |
|---|---|---|
| État posé par une **classe JS** | `.aod-cd-toast` → `.show` (ajoutée par `dashboard.js`) | Chercher `:hover` ne rend **rien**. Conclure « transition morte » et la retirer **tue l'apparition du toast**. |
| État posé sur un **ancêtre** | `.aod-cd-todo-tile:hover .aod-cd-todo-tile__go` | L'élément n'a aucun état propre ; ce qui l'anime vit sur le parent (`color`, `transform`). |
| Propriétés d'un **pseudo-élément** | `.aod-cd-btn.is-loading::after` | Un relevé naïf attribue les `width`/`height` du spinner **au bouton** et croit à tort qu'il anime du layout. |

Le troisième cas s'est produit **pendant le relevé lui-même** : le premier script
a signalé `.aod-cd-btn` comme animant `height`/`width` (🔴 layout). C'était faux —
ces dimensions appartiennent au `::after`. Un plan bâti dessus aurait fait chasser
un problème inexistant.

### Étape 16.1 — Relever, pas deviner (S) ✅ livrée
- Produire l'inventaire des 22 sélecteurs : pour chacun, **la liste exacte** des
  propriétés qui changent d'état, tous déclencheurs confondus — `:hover`,
  `:focus`, `:active`, `.is-*`, **classes ajoutées en JS**, **états portés par un
  ancêtre**, et en distinguant l'élément de ses **pseudo-éléments**.
- Le script de relevé doit **retirer les commentaires avant de parser** : sans ça
  un `/* … */` collé à un sélecteur le rend méconnaissable, et les règles
  concernées sortent silencieusement de l'inventaire (constaté).
- Livrable : un tableau sélecteur → propriétés, revu à la main. **Pas de code.**
- **Un CINQUIÈME angle mort est apparu au relevé**, non anticipé : la règle d'état
  peut être **plus LARGE** que la règle de base — `.aod-cd-form input:focus`
  couvre `.aod-cd-form input[type=text]` sans jamais le contenir. L'outil
  rapportait « aucune propriété » sur six sélecteurs d'`input` : un **faux
  négatif**, résolu à la main.

### Étape 16.2 — Convertir par lots homogènes (M) ✅ livrée
- Convertir en suivant l'inventaire, **par famille** (champs de formulaire,
  boutons, onglets/segments, sélecteurs, galerie, pagination) plutôt qu'en une
  passe unique : un lot qui régresse doit pouvoir être isolé.
- Reprendre les acquis de 15.6 : durées inchangées, `var(--cd-ease-out)`, et
  `ease-in` proscrit.
- Ajouter `:active{transform:scale(.98)}` aux éléments **pressables** qui n'en ont
  pas (boutons, onglets, tuiles) — et le neutraliser sous
  `prefers-reduced-motion`, comme la nav.

### Étape 16.3 — Une porte qui voit le mouvement (M) ✅ livrée
- Aujourd'hui **aucun garde-fou ne verrait la régression** que cette phase risque.
  Ajouter une porte de recette qui, pour une liste de sélecteurs, déclenche l'état
  au navigateur et vérifie que la propriété attendue **change réellement** — sur
  le modèle de la vérification de 15.6 (`mouse.down` puis lecture du `transform`
  calculé, pas une lecture de feuille de style).
- Vérifier aussi qu'aucune `transition-property` ne vaut `all`. C'est le cliquet :
  sans lui, la prochaine règle ajoutée repartira en `transition:.15s`.

> **DoD spécifique** : chaque lot converti est comparé **avant/après au navigateur**
> sur l'état concerné. Un test vert ne suffit pas — c'est exactement le piège que
> la Phase 15 a rencontré deux fois (le kaki de la sidebar, et l'accent délavé),
> où seule une capture d'écran a montré le défaut.

### Ce que la phase a coûté en erreurs — et ce qu'elles ont produit

Trois défauts introduits puis rattrapés, chacun devenu un garde-fou :

| Erreur | Comment elle s'est vue | Ce qui la retient désormais |
|---|---|---|
| Le remplacement automatique a **mangé l'accolade fermante** de 3 règles écrites sur une seule ligne (`transition:.15s}` — le `[^;]+` du motif avale le `}`). La feuille est tombée de **875 règles à 41**. | La porte lisait des styles de navigateur nu (`rgb(0,0,238)`, le bleu de lien) | Invariant exact : `{` du fichier == règles parsées. Un **seuil** ne suffisait pas (une cassure en fin de fichier ne perd que 6 règles). |
| La neutralisation `prefers-reduced-motion` était placée **avant** les règles qu'elle annule, et **moins spécifique** qu'elles. Elle ne s'appliquait pas. | Le contrôle n°4 de la porte | Le bloc est en fin de fichier avec les `:not(:disabled)` recopiés, et un commentaire qui dit pourquoi. |
| Le parcours du CSSOM testait `if ( r.cssRules )` — or depuis le **CSS Nesting**, une `CSSStyleRule` en possède un (liste vide mais *truthy*). Chaque règle de style partait dans la branche récursive : **10 règles inspectées sur 875**, et la porte annonçait « aucune règle n'anime `all` ». | En réinjectant un `transition:.15s` : la porte ne le voyait pas | Un **témoin `inspected`** remonté et vérifié (> 700). Une assertion vide ne peut plus se déclarer verte. |

Le troisième est le plus instructif de tout le chantier : **c'est le garde-fou
lui-même qui était vide**, et il l'aurait été indéfiniment sans l'épreuve du feu.

---

## Phase 17 — Le reliquat visuel de la 5.2b, et ce qu'il cachait

**Statut : ✅ soldée (7/7), le 2026-07-20.**

> ⚠️ **Cette phase a été exécutée avant d'être écrite ici.** Ses étapes 17.0 à
> 17.4 ont été livrées à partir d'un plan de session qui n'a jamais été versé au
> dépôt : quand la session s'est terminée, le plan a disparu avec elle, et la
> phase n'existait plus que dans les messages de commit et une ligne de
> `TEMPLATE.md`. La numérotation d'origine au-delà de 17.4 est **perdue** — un
> commit cite « l'étape 17.7 » pour ce qui est ici la 17.5. La reconstitution
> ci-dessous est faite à partir des commits ; elle est fidèle sur le fond, pas
> nécessairement sur les numéros.
>
> **C'est le mode d'échec que la DoD §C.2 vise déjà pour `TEMPLATE.md`, un cran
> plus haut** : un plan qui ne vit que dans une session n'est pas un plan, c'est
> une mémoire. La leçon est portée en C.2.

### L'objet annoncé

La 5.2b (livrée le 2026-07-13) a mutualisé le **comportement** des trois menus
flottants de `/gestion` — `filterdd` supprimé en 5.4, puis clavier et ARIA
mutualisés en 5.8 (`bindFloatingMenu()`, `bindListboxKeys()`). Elle n'a jamais
mutualisé leur **style** : `statussel`, `csel` et `catdd` recopiaient trois fois
la même surface. Reliquat purement visuel, aucun flux cœur — c'est ce qui l'a
fait différer trois fois.

### Ce qu'on a trouvé en chemin, et qui valait plus que l'objet annoncé

Le reliquat a été traité en 17.6. **Les cinq étapes qui précèdent ont corrigé des
défauts que rien ne signalait**, tous découverts en ouvrant la zone. Le fil est
toujours le même, et c'est la leçon de la phase :

> **Un défaut qui ne casse rien ne se voit pas — il se lit.** Une variable CSS
> jamais déclarée sert son repli. Un `@keyframes` jamais déclaré laisse le
> `animation-name` en place et ne joue rien. Un survol masqué par le zébrage
> s'affiche parfaitement, à l'identique du repos. Une couleur de client en dur
> est juste fausse chez le client suivant. **Aucun de ces quatre défauts ne
> produit d'erreur, de console, ou d'écran cassé.** Trois d'entre eux ont vécu
> sous des portes de recette vertes pendant toute leur existence.

| Étape | Ce qui a été corrigé |
|---|---|
| 17.0 | `run.sh` : un scénario mal orthographié ne déclenchait **aucune** porte et sortait à 0 — `run.sh contrast` rendait le même vert que `run.sh contraste`. |
| 17.1 | Le cliquet anti-emoji de 15.7b effaçait la ligne **jusqu'au bout** à partir du premier appel de traduction (`…\s*\(.*`) — il ratait tout emoji placé après un attribut traduit. |
| 17.2 | Survol et sélection d'une ligne **invisibles une ligne sur deux** : `:not()` et `:nth-child()` donnaient au zébrage plus de poids qu'aux deux états. |
| 17.3 | La **dernière couleur de client en dur** (deux jaunes hérités de l'accent par défaut) → token `--cd-accent-wash`, dérivé en PHP. |
| 17.4 | **Trois variables CSS jamais déclarées** — `--cd-ink-3` rendait 2,58:1 en corps 12. |
| 17.5 | Les portes qui auraient vu 17.2 et 17.4 : états interactifs déclenchés au navigateur, balayage de `wash()`, cliquets statiques sur la feuille. |
| 17.6 | **L'objet annoncé** — la surface flottante écrite une fois. Plus un `@keyframes` fantôme trouvé au passage. |

### Pourquoi les portes ne voyaient rien

Point de méthode réutilisable, et la raison d'être de la 17.5. La porte
`contraste` audite l'état **au repos**. Or deux surfaces qui portent du texte ne
s'affichent **jamais** au repos :

- le **survol** et la **sélection** d'une ligne — il faut une souris ;
- la **frise de suivi** — elle vit dans un panneau qui s'ouvre au clic.

La porte ne mentait pas sur ce qu'elle voyait : elle n'avait jamais eu
l'occasion d'échouer. Généralisation : **un audit d'état au repos ne couvre pas
une interface à états.** Ce qui n'est pas déclenché n'est pas mesuré, et rien ne
distingue « vérifié et conforme » de « jamais regardé ».

### Le fantôme, forme générale

17.4 (variables) et 17.6 (`@keyframes`) sont **le même défaut sur deux objets**,
et le second a survécu au cliquet posé pour le premier — qui ne regardait que les
`--cd-*`. Mesuré au navigateur avant correction :

```
getComputedStyle().animationName → "aod-cd-dd-in"   ← le nom est bien là
getAnimations()                  → []               ← rien ne tourne
```

→ **Quand on pose un cliquet contre une référence non déclarée, se demander
quels AUTRES objets du même fichier ont la même faille.** Le CSS en compte
plusieurs (`var()`, `animation`, `@font-face`, `counter`), tous silencieux.

### Étapes

- **17.0 — Garde d'argument sur `run.sh`** (S) ✅ livrée. Sortie 2 + liste des
  noms valides, recoupée mécaniquement contre le dispatch (17 contre 17).
- **17.1 — Réparer le cliquet anti-emoji** (S) ✅ livrée. Motif à parenthèses
  équilibrées ; deux témoins permanents (voir un emoji APRÈS un appel de
  traduction, en ignorer un DEDANS). Mis au rouge avant d'être cru.
- **17.2 — Rendre les états d'une ligne visibles** (S) ✅ livrée.
- **17.3 — `--cd-accent-wash`** (M) ✅ livrée. `WASH_MIX = 0.94` **mesuré**, pas
  choisi : l'encre contraignante est `--cd-muted`, pas `--cd-ink`.
- **17.4 — Purger les variables fantômes** (S) ✅ livrée.
- **17.5 — Les portes qui auraient vu 17.2 et 17.4** (M) ✅ livrée.
- **17.6 — La surface flottante, écrite une fois** (M) ✅ livrée.

> **DoD spécifique** : cette phase ne touche **que** `/gestion`. Aucun fichier de
> la partie publique n'est modifié — c'est vérifié à chaque commit. Et tout
> défaut corrigé ici doit repartir avec **le cliquet qui l'aurait vu** : c'est la
> seule chose qui distingue cette phase d'un nettoyage cosmétique.

---

## Phase 18 — La dette que l'audit A→Z a laissée derrière lui

**Statut : ✅ soldée (3/3), le 2026-07-20.** Source : **§C.5**, relevé
lors de l'audit de cohérence doc ⇄ code du 2026-07-20. Les points D1, D2 et D3
de ce relevé ont été soldés hors phase (correctifs directs, commits `22348f5`
et `b7e0a54`) ; **cette phase prend ce qui reste.**

> **Pourquoi une phase, et pas trois retouches.** D1 a été corrigé sans que la
> porte qui aurait dû le voir bouge d'un pouce. C'est le motif que la Phase 17
> avait déjà nommé — *un défaut qui ne casse rien ne se voit pas, il se lit* —
> et le corriger une fois de plus sans élargir la porte garantit qu'il revienne.
> D'où l'ordre : **18.1 avant tout le reste.**

### Étape 18.1 — La porte `mouvement` ne regarde que la moitié du dépôt (S/M)

**Le défaut n'est pas dans le CSS : il est dans la porte.** `run.sh mouvement`
n'inspecte que la feuille dont le `href` contient `dashboard.css`
(`tools/recette/mouvement.js:61`) et ne visite que `/gestion/`. Les 3 dernières
`transition` implicites du dépôt vivaient donc **hors de son champ** — elles
n'ont pas échappé au contrôle, elles n'ont jamais été contrôlées.

- Étendre la porte au **plugin public** : visiter une fiche produit (et une page
  de vente) en plus de `/gestion/`, inspecter `aod-cod-form.css` et les styles
  **inline** du switcher FR/AR (`class-aod-cod-lang.php`), aujourd'hui invisibles
  car injectés hors feuille.
- **Faire rougir la porte avant de la retenir** : réintroduire une `transition`
  nue côté public, vérifier l'échec, restaurer. Une porte qu'on n'a pas vue
  échouer ne prouve rien — c'est la règle du témoin (§C.2).
- **Publier ce qui a réellement été inspecté** (nombre de feuilles, d'URL, de
  règles), conformément à la règle posée en 17.5 : un contrôle de couverture
  doit dire ce qu'il a regardé.

**DoD spécifique :** la porte échoue si une `transition` implicite réapparaît
**n'importe où** dans `plugins-dev/`, pas seulement dans `/gestion`.

### Étape 18.2 — Hygiène : clés i18n mortes et `.gitignore` de `library/` (S)

- **D4** — 4 clés fournies par PHP et jamais consommées en JS (`i18nCatDelete`,
  `i18nCatRename`, `i18nImpCurrent`, `i18nImpNew`) : les rebrancher si un usage
  était prévu, sinon les retirer. Elles sont traduites en arabe pour rien.
- **D5** — `.gitignore:12-13` ne couvre que `library/**/*.zip`. Un thème livré en
  `.tar.gz`, un dossier décompressé ou un PDF de licence **serait versionné**,
  contre `TEMPLATE.md` §2 (« `library/` n'est pas versionné : poids + licences »).
  Élargir à `library/` avec l'exception qui garde le dossier présent.

### Étape 18.3 — Revue manuelle des 33 `phpcs:ignore` non audités (M)

L'audit d'échappement du 2026-07-19 portait sur **97** annotations et concluait
à 0 faille. Le dépôt en compte **130** : les Phases 15 et 17 en ont ajouté 33,
principalement autour de l'injection SVG de `class-aod-cd-icon.php`.

**Voie retenue par l'owner (2026-07-20) : revue manuelle, sans outil.** Poser
PHP_CodeSniffer supposerait Composer et un accès réseau, que ce template n'a
pas (§3) — l'introduire pour cette seule dette coûterait plus que la dette.

- Relire **une par une** les 33 annotations postérieures au 2026-07-19, et
  consigner le verdict par annotation (sortie déjà échappée / échappement
  volontairement délégué / **faille réelle**).
- Mettre à jour `ARCHITECTURE.md` §7 dette 14 avec le nouveau périmètre audité
  et sa date.
- ⚠️ **Limite assumée, à écrire noir sur blanc** : une revue manuelle ne protège
  pas des **futures** annotations. Si le compte doit rester fiable, c'est un
  cliquet qu'il faut, pas un audit — hors périmètre de cette étape, à rouvrir si
  l'écart se reproduit.

---

## Phase 19 — Le mouvement réduit s'arrête à la porte de `/gestion`

**Statut : ✅ soldée (1/1), le 2026-07-20.** Source : **§C.5 (D8)**, ouvert par
l'étape 18.1 qui l'a **mesuré** en cherchant à étendre son contrôle au public.

`dashboard.css` honore `prefers-reduced-motion` en **5** endroits. Les trois
feuilles publiques : **0**. Un acheteur qui a activé « réduire les animations »
— réglage système, souvent posé pour un trouble vestibulaire ou une sensibilité
migraineuse — subit quand même le creux d'appui du bouton « Commander »,
l'entrée animée du panneau de remerciement et le glissement de la barre collante.

C'est **le gérant qui est servi, pas l'acheteur** : l'inverse de la priorité du
template, dont le trafic est massivement mobile.

### Étape 19.1 — `prefers-reduced-motion` côté public (S/M)

> 🚨 **Le piège de cette étape : `transform` n'est pas synonyme de mouvement.**
> Sur les 10 `transform` publics, **6 sont du POSITIONNEMENT** (centrage d'un
> label flottant, d'une flèche de galerie, d'un bouton de lecture vidéo — et sa
> variante **RTL** `translate(50%, -50%)`). Un `transform:none` global ne
> « calmerait » pas l'interface : il **déplacerait** ces éléments, et la variante
> RTL partirait du mauvais côté — exactement le mode d'échec de la 17.3.
>
> Deux cas sont **mixtes** et demandent une valeur, pas `none` :
> `.aod-gallery-nav:active` vaut `translateY(-50%) scale(.94)` — il faut retomber
> sur `translateY(-50%)`, pas sur `none`.

- Neutraliser le **mouvement** (déplacement, mise à l'échelle, animation
  d'entrée) en **gardant l'acquittement non-moteur** (fond, couleur, opacité) —
  le précédent posé par `dashboard.css:251-258`.
- **Placer les surcharges en FIN de fichier**, jamais avant les règles visées :
  `dashboard.css:259-261` documente qu'une surcharge placée trop haut *perd la
  cascade en silence*. C'est le défaut que la Phase 16 avait dû corriger.
- **Étendre la porte** : le bloc 4 de `mouvement.js` (aujourd'hui `/gestion`
  seul) doit couvrir le public, et la mention d'exclusion D8 disparaître.

**DoD spécifique :** la porte échoue si une surcharge `prefers-reduced-motion`
publique perd la cascade — c'est-à-dire si, sous `reducedMotion: 'reduce'`, un
élément conserve son mouvement. Et elle vérifie que les éléments **positionnés**
n'ont **pas** bougé.

---

## Phase 20 — La typo de `/gestion` vient d'un CDN

**Statut : ✅ soldée (1/1), le 2026-07-21.** Source : **§C.5 (D9)**, relevé le
2026-07-21 lors de l'analyse A→Z du dépôt.

La coque de `/gestion` charge **Barlow depuis Google Fonts** —
`class-aod-cd-dashboard.php:385-387`, deux `preconnect` plus une feuille
`fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800`.

Ce n'est pas un détail esthétique : c'est la **contrainte fondatrice du template
prise à revers**, dans le plugin qui l'énonce. `assets/icons/README.md` §« Pourquoi
versionnés et pas installés » écrit noir sur blanc que « les conteneurs de ce
template n'ont pas d'internet sortant fiable ; un clone doit fonctionner le jour
de son installation, sans npm, **sans CDN**, sans build » — et
`ICONES-ET-CHARTE-GESTION.md` §2 revendique « aucun CDN, aucun appel réseau au
rendu ». Les **71 SVG** ont été embarqués pour cette raison exacte.

**Le mode d'échec est celui que la Phase 15 a éliminé, à l'identique.** Sans
réseau, la requête échoue en silence, `font-family` retombe sur
`-apple-system, …, sans-serif`, et la charte perd sa signature — pas le même
visage sur Windows, macOS et Android. C'est mot pour mot le reproche fait aux
emojis Unicode (`ARCHITECTURE.md` §6). La différence est qu'un emoji manquant se
voit ; une police de repli, non : **l'écran reste crédible tout en n'étant plus
le produit**. Aucune porte ne le dit, aucun test ne le mesure.

Trois conséquences s'ajoutent, dans l'ordre où elles mordent :

1. **Le clone neuf.** C'est le cas nominal du template — `TEMPLATE.md` §3 : « pas
   d'internet sortant fiable par défaut ». Le gérant voit une charte dégradée dès
   la première ouverture, sans rien pour l'expliquer.
2. **Vie privée.** Chaque chargement de `/gestion` émet une requête vers Google
   portant l'IP du gérant et le `Referer`. Un back-office `noindex,nofollow` qui
   appelle un tiers à chaque page, c'est une fuite gratuite — et un point de
   friction RGPD que rien n'oblige à porter.
3. **Latence.** Deux `preconnect` + une résolution DNS + deux allers-retours
   (CSS puis `woff2`) avant le premier texte peint, sur des connexions
   algériennes souvent médiocres.

### Étape 20.1 — Embarquer Barlow dans le plugin (S/M)

Même geste que les icônes, même justification : **récupérer une fois sur l'hôte**
(qui a du réseau — vérifié le 2026-07-21), puis **versionner**.

> 🚨 **Piège n°1 — Barlow n'a aucun glyphe arabe.** La moitié de l'interface est
> en AR ; elle est **déjà** rendue par la police système via le repli de
> `dashboard.css:88`, et cela doit rester vrai. Embarquer « la police de
> `/gestion` » sans le voir, puis constater que l'arabe n'a pas changé, ferait
> croire à un échec de l'embarquement. **Rien à faire côté AR — c'est le
> comportement attendu, pas un reste à traiter.**

> 🚨 **Piège n°2 — le poids se décide au sous-ensemble, pas à la graisse.**
> Google sert Barlow en 3 sous-ensembles (`latin`, `latin-ext`, `vietnamese`) ×
> 5 graisses = **15 fichiers**. Le français est **intégralement couvert par
> `latin`** (`U+0000-00FF` pour les accentuées, plus `U+0152-0153` pour « œ ») :
> `latin-ext` et `vietnamese` ne serviraient jamais. Copier les 15 par sécurité
> triplerait le poids embarqué pour zéro glyphe utile. **5 fichiers, sous-ensemble
> `latin` seul.**

- Déposer les `woff2` dans `assets/fonts/`, avec le `LICENSE` **OFL-1.1**
  (⚠️ Barlow n'est **pas** MIT comme Phosphor : l'OFL impose de joindre la
  licence) et un `README.md` de provenance sur le modèle de `assets/icons/`.
- Déclarer les `@font-face` **en tête de `dashboard.css`**, avec
  `font-display:swap` conservé et `unicode-range` repris de l'amont : le
  chargement devient **paresseux**, une graisse n'étant tirée que si un caractère
  de sa plage est peint (mesuré : 4 fichiers sur 5, la 500 ne sert nulle part).
  ⚠️ Gain **par graisse, pas par langue** — un écran arabe en tire autant, le nom
  de la boutique et les chiffres restant latins. La rédaction initiale de cette
  étape annonçait « un écran 100 % arabe ne télécharge rien » : la mesure au
  navigateur l'a démentie.
- Retirer les **trois** `<link>` de `class-aod-cd-dashboard.php:385-387`, les
  deux `preconnect` compris : ils n'ont plus d'objet et coûtent une résolution
  DNS chacun.
- Vérifier la **page de connexion** (`class-aod-cd-access.php`) : si elle appelle
  Barlow, elle doit suivre — sinon la typo saute entre le login et le dashboard.

**DoD spécifique — un cliquet, pas une relecture.** Le défaut a vécu parce que
rien ne regardait : la leçon de D1/D6 est qu'une porte ne prouve que ce qu'elle
inspecte. Ajouter au socle de tests un contrôle **statique** qui balaie
`plugins-dev/` et **échoue sur tout `href`/`src`/`url()` pointant un hôte
externe** dans le HTML ou le CSS servi — le pendant exact du cliquet anti-emoji
de la 15.x et du cliquet CSS de la 18.1. Un test qui n'interdirait que
`fonts.googleapis.com` raterait le prochain CDN ; c'est **l'hôte externe** qui
est la règle, pas le nom de domaine.

Et le témoin qui manquait : le contrôle **publie le nombre de fichiers balayés**.
Un `glob` vide se déclarerait vert sinon — le mode d'échec relevé en 18.3.

---

## Phase 21 — La garde d'un handler ne suit pas celle de sa section

**Statut : ✅ soldée (1/1), le 2026-07-21.** Source : **§C.5 (D11)**, relevé le
2026-07-21 lors de l'analyse A→Z du dépôt.

`/gestion` gouverne l'accès **par capability, jamais par masquage d'UI** — et le
fait bien : 23 `check_ajax_referer`, une revérification de cap dans chaque
handler. Mais **la cap exigée par un handler n'est pas toujours celle de sa
section**, et les deux écarts tombent tous les deux sur le **confirmateur** :

| Handler | Exige | Ses voisines de section | Effet |
|---|---|---|---|
| `ajax_test_carrier` (`dashboard.php:2441`) | `manage_woocommerce` | `ajax_save_shipping` (`:2194`), `ajax_import_fees` (`:2335`) → `aod_cap_shipping_prices` | Un confirmateur « tarifs » **importe** des tarifs mais ne peut pas **tester** la connexion transporteur |

**Trois autres divergences existent et sont VOULUES** — c'est ce qui rend l'étape
délicate : `ajax_save_stock` (`edit_products` sur une section `aod_cap_stock` : le
confirmateur a l'écran en lecture seule, délibérément), `ajax_delete_orders`
(supprimer est plus fort que confirmer) et `ajax_save_account` (**resserré en
v0.18.6**, docblock à l'appui). Un balayage naïf en trouve quatre et les traite
toutes comme des défauts.

**Pourquoi personne ne l'a vu : on développe en gérant.** `manage_woocommerce`
satisfait toutes les gardes, donc le gérant ne rencontre jamais ces deux murs.
Il faut se connecter *en confirmateur* pour les toucher — ce qu'aucune porte de
recette ne fait sur ces deux écrans.

> 🚨 **`ajax_save_account` a d'abord été compté comme un second défaut. C'était
> faux — et la façon dont ça s'est effondré est la leçon de la phase.**
> Le raisonnement tenait : le handler est borné à `$user->ID` (nom, e-mail, mot de
> passe ; la remise à zéro est un handler séparé), donc son `manage_woocommerce`
> « ne protège rien ». Le code donnait raison. **Le routeur non** :
> `visible_sections()` (`:209-211`) fait `if ( empty( $section_caps[ $slug ] ) ) { continue; // Réservée au gérant. }`
> — une section absente de `$section_caps` est **manager-only**, et `account` en est
> absente. Le confirmateur **ne voit pas l'écran et ne peut pas y entrer par URL**.
> Le « 403 en cliquant Enregistrer » n'arrive jamais : le symptôme était imaginé.
> Et `ARCHITECTURE.md:338` documente le **durcissement délibéré de la v0.18.6**.
>
> **Lire le handler ne suffit pas — il faut lire qui peut l'atteindre.** Une garde
> qui paraît inutile localement peut être la seconde moitié d'une décision prise
> ailleurs.
>
> 📌 Reste une vraie question, **produit et non technique** : un confirmateur ne
> peut changer son mot de passe **nulle part** dans `/gestion` (il le demande au
> gérant, section Équipe). Voulu ? → **D12**, arbitrage owner, hors périmètre.

### Étape 21.1 — Aligner les gardes, et poser le cliquet (S/M)

Corriger les deux cas est le petit travail. **L'objet de l'étape est le cliquet** :
un test qui, pour **chaque** `wp_ajax_aod_cd_*`, vérifie que la cap exigée est
celle de sa section. Sans lui, on corrige deux cas et le troisième arrivera.

> 🚨 **Piège mesuré avant d'écrire l'étape — c'est lui qui dicte la forme du
> test.** 4 handlers sur 27 (`ajax_save_member`, `ajax_delete_member`,
> `ajax_desc_image`, `ajax_desc_video`) **n'ont aucune vérification chez eux** :
> ils délèguent à une garde partagée (`AOD_CD_Section_Team::guard()`,
> `AOD_CD_Section_Products::handle_desc_upload()`). Un cliquet naïf les
> signalerait comme non gouvernés — **4 faux positifs sur 27**, du code
> parfaitement correct. Un test qui crie à tort finit désarmé : c'est exactement
> le mode d'échec de la DoD initiale de la 20.1, corrigée en cours de route.
> → Le contrôle doit suivre **un niveau d'indirection**, ou tenir une liste
> blanche **nommée** de gardes partagées. Et **publier le compte de handlers
> inspectés** : un scan qui n'en trouverait aucun se déclarerait vert.

- `ajax_test_carrier` : `manage_woocommerce` → `aod_cap_shipping_prices`, pour
  s'aligner sur `ajax_save_shipping` et `ajax_import_fees`. **C'est la seule ligne
  de production que l'étape modifie.**
- Cliquet `tests/unit/test-permissions-sections.php`, sur le patron de
  `test-ressources-locales.php` (Phase 20). Les **trois divergences voulues** y
  entrent avec leur cap réelle **et leur justification** : une divergence sans
  raison écrite est un échec de revue, pas un test vert.
- 🚨 **Énumérer depuis `$GLOBALS['wp_filter']`, jamais depuis une liste de
  fichiers.** 16 des 27 handlers vivent dans `dashboard.php`, qui porte 6 sections ;
  et un 28ᵉ posé dans un 6ᵉ fichier serait **hors scan**, pas « hors table » —
  exactement le défaut de la 18.1 reproduit dans le test censé le prévenir.
- ⚠️ **`AOD_CD_CAP` n'est pas « utilisateur du dashboard »** : l'administrateur ne
  la possède pas (il entre par le **OU** de `:306`). Toute garde écrite
  `current_user_can( AOD_CD_CAP )` seule le verrouille. Une première rédaction de
  l'étape le proposait.

**DoD spécifique :** le test échoue si un handler exige une cap **plus forte** que
sa section (le mur invisible) **ou plus faible** (le trou de sécurité — le sens qui
compte vraiment). Éprouvé **au rouge dans les deux sens**. Et une vérification qui
manquait à toutes les portes : un passage `/gestion` **connecté en confirmateur**,
pas en gérant.

---

## Phase 22 — Toute la recette tourne en gérant, qui passe partout

**Statut : ✅ SOLDÉE le 2026-07-21 (2/2).** Source : **§C.5 (D13)**, **cause
racine** de la Phase 21 — **dette fermée**.

La Phase 21 a corrigé une garde divergente. Elle n'a pas corrigé **ce qui l'a
rendue invisible** : `tools/recette/lib.js:85` (`login()`) est câblé sur un compte
unique — `WP_ADMIN_USER` — et **10 portes** l'appellent. Toute la recette voit donc
`/gestion` avec `manage_woocommerce`, qui satisfait **toutes** les gardes.

**Conséquence, et elle est large** : la couche d'autorisation entière — 7 caps
`aod_cap_*`, périmètre produit par utilisateur (`_aod_products_limited`), écrans en
lecture seule, sections masquées — n'est **jamais exercée au navigateur**. Le
cliquet de la 21.1 est **statique** : il lit du code, il ne verra jamais un 403
réel, ni un écran qui s'affiche vide parce qu'un filtre SQL de périmètre s'est mal
appliqué.

> 🚨 **Le piège de la phase, mesuré le 2026-07-21 — et il fabrique des portes
> vertes qui testent le mauvais rôle.**
> 1. `login()` commence par `if ( ! page.url().includes( 'wp-login' ) ) { return; }`
>    — « session déjà ouverte → on repart ». ⚠️ **Mesuré le 2026-07-21 : ce
>    court-circuit ne se déclenche jamais**, `/wp-login.php` affichant son
>    formulaire même connecté (`login_redirect` ne s'applique qu'**après** une
>    connexion réussie). Il est donc **mort en pratique** — mais sa condition ne
>    porte pas sur *qui* est connecté : le jour où une redirection le réveille, il
>    conserverait l'identité précédente **sans le dire**. Une garde qui ne sait pas
>    de qui elle parle.
> 2. `launch()` lit `.state.json` s'il existe (`:74`) — mais **rien ne l'écrit
>    aujourd'hui** : le mécanisme est **dormant**. Le jour où quelqu'un l'active, une
>    porte « confirmateur » repartirait sur la session **gérant**, verdirait, et
>    prouverait exactement le contraire de ce qu'elle annonce.
>
> → **Une porte qui dépend d'une identité doit ASSERTER l'identité qu'elle a
> réellement**, jamais la supposer. C'est la règle du témoin (§C.2) appliquée non
> plus à la couverture, mais à **qui regarde**.

### Étape 22.1 — `login()` prend un compte, et la recette dit qui elle est (S/M)

- `login( page, { user, pass } )`, les valeurs actuelles restant le **défaut** —
  les **10 portes existantes ne changent pas d'une ligne**.
- Retirer le court-circuit « session déjà ouverte », ou le rendre **conditionnel à
  l'identité demandée**. Mesuré : il ne se déclenche pas aujourd'hui — mais sa
  condition ignore *qui* est connecté, ce qui en fait un piège dormant.
- Bascule de rôle fiable, **mesurée** : `ctx.clearCookies()` puis reconnexion.
  (`wp-login.php?action=logout` exige un nonce ; la purge de cookies n'en a pas
  besoin.)
- **Témoin d'identité** : un helper `whoAmI( ctx )` qui lit l'utilisateur réellement
  connecté et que les portes **assertent**.
  🚨 **Mesuré, et la voie évidente échoue** : `/wp-json/wp/v2/users/me` rend **401**
  (l'auth par cookie exige un `X-WP-Nonce` que `/gestion` n'expose pas). La source
  fiable est le **cookie `wordpress_logged_in_*`**, dont la valeur commence par le
  login — c'est WordPress lui-même qui l'a émis. Il est `HttpOnly`, donc invisible à
  `document.cookie` : il se lit par `ctx.cookies()`, d'où la signature en **`ctx`**
  et non en `page`. Contrat vérifié : `null` si déconnecté.

> ⚠️ **Le risque de cette étape n'est pas dans ce qu'elle ajoute, mais dans ce
> qu'elle touche** : `lib.js` est le socle des **17 suites**. Une régression ici les
> casse toutes à la fois. La 22.1 se termine par un `run.sh tout` vert, pas par un
> `run.sh confirmateur` vert.

### Étape 22.2 — La porte `run.sh confirmateur` (M)

Une porte qui **sème** son confirmateur, le fait vivre, et le purge — jamais un
compte du clone (précédent : toutes les portes sèment leurs données, cf.
`tools/recette/README.md`).

- Pour **chacune** des 7 caps : la section correspondante est visible **avec**,
  absente **sans**.
- Les sections manager-only restent **inatteignables par URL** (pas seulement
  absentes du menu) : `maybe_render()` renvoie sur la première section visible.
- **L'écran en lecture seule de `stock`** rend bien l'en-tête différencié — la
  divergence voulue de la 21.1 devient observable.
- Un handler AJAX gouverné répond **200** avec la cap, **403** sans. C'est le
  contrôle que le cliquet statique ne peut pas faire.
- **Périmètre produit** (`_aod_products_limited`) : un confirmateur limité ne voit
  que ses commandes.

**DoD spécifique :** la porte **publie le compte de caps réellement exercées** (7
attendues) et **l'identité sous laquelle elle a tourné**. Éprouvée au rouge en
desserrant une garde. ⚠️ Et elle doit **échouer si elle se retrouve connectée en
gérant** — sans quoi elle reproduit, dans le contrôle censé le corriger, le défaut
même qu'elle traque.

---

## Phase 23 — Les `phpcs:ignore` étaient relus, jamais gardés

**Statut : ✅ SOLDÉE le 2026-07-21 (1/1).** Source : **§C.5 (D6)**.

Une annotation `phpcs:ignore` désarme un contrôle de sécurité statique, une ligne à
la fois. Le dépôt les avait **toutes relues à la main** — 2026-07-19 puis étape 18.3,
**0 faille**. Mais un audit est un **instantané** : rien n'empêchait la suivante
d'arriver non relue. C'est la forme la plus pure du défaut que ce dépôt traque
depuis la Phase 17 — *un contrôle humain sans cliquet ne protège que le passé.*

### 🚨 Ce que la dette annonçait mal, et que la mesure a corrigé

D6 disait « les **130** annotations ont toutes été relues, 0 faille ». **Exact, et
trompeur** : 130 est le compte de la **seule famille `EscapeOutput`**. Le dépôt en
portait **170**. Les **40** autres — `NonceVerification` (CSRF),
`ValidatedSanitizedInput`, `WordPress.DB` (SQL) — les familles les **plus**
sensibles, n'étaient dans le périmètre d'**aucun** audit.

Un chiffre qui sonne comme une couverture totale mais nomme un sous-ensemble : le
mode d'échec de la « règle du témoin », dans la ligne même censée décrire l'état de
la dette.

### Ce que l'audit des 40 a trouvé

**0 suspecte.** 13 justifiées, 27 sans commentaire explicatif. Aucune donnée brute
n'atteint une sortie sensible : pas d'injection SQL, pas de traversée de chemin,
pas de CSRF exploitable. Les lectures `$_GET` du switcher FR/AR sont validées contre
une liste blanche `fr`/`ar` ; les écritures `$_POST` des jetons CAPI sont bien
couvertes par un `check_ajax_referer` / `check_admin_referer` en amont.

### Le seul vrai défaut — et pourquoi il avait échappé à tout

`class-aod-cd-section-products.php:500` portait une annotation **NUE** :
`// phpcs:ignore`, sans nom de sniff. Elle ne levait pas une exception précise :
elle **désarmait tous les contrôles de la ligne**, un blanc-seing permanent.

🚨 **Elle avait échappé à l'audit de la 18.3 pour une raison qui vaut leçon :** cet
audit grepait **par nom de sniff**, et une annotation nue n'en porte aucun. Le
scénario « 131ᵉ annotation non relue » que D6 annonçait comme un risque futur était
**déjà réalisé** — dans l'angle mort de l'audit lui-même.

### Étape 23.1 — Le cliquet `tests/unit/test-phpcs-ignore.php` (M)

Trois règles mécaniques, plus un témoin :

- **R1 · aucune annotation nue.** Absolue, zéro aujourd'hui et demain.
- **R2 · census par famille**, déclaré dans le test. Un ajout fait diverger le
  compte et rougir : personne n'ajoute une suppression de contrôle CSRF sans qu'un
  humain vienne écrire pourquoi. Les familles de sécurité sont marquées `🔒`.
- **R3 · le stock d'annotations sans raison ne remonte pas** (plancher 141).
- **Témoin** : nombre de fichiers balayés et d'annotations relevées — un `glob` vide
  se déclarerait vert sinon.

⚠️ **R3 est un plancher, pas un zéro, et c'est délibéré.** Exiger une raison sur les
141 imposerait un diff mécanique de 141 lignes **dans le code de production**, sans
le moindre changement de comportement, pour satisfaire un test. On fige le stock au
lieu de le nier.

🚨 **Balayage RÉCURSIF**, jamais une liste de fichiers : la leçon de la 18.1, rejouée
dans le test censé la prévenir.

---

## Phase 24 — « Le nonce est vérifié par l'appelant » : personne ne vérifiait l'appelant

**Statut : ✅ SOLDÉE le 2026-07-21 (1/1).** Source : **§C.5 (D15)**.

`capi_token_from_post()` existe en deux exemplaires et lit `$_POST` **sans le
recevoir en argument**. Sa sûreté tenait à une phrase de docblock — *« le nonce est
vérifié par l'appelant »*. C'était vrai, et c'est resté vrai **par chance** : rien
n'empêchait un 7ᵉ site d'appel non noncé de créer une CSRF en silence. Côté
`aod-cod-form`, le helper était en plus `public static` — appelable depuis
n'importe quel plugin du site.

**Ce n'était pas une faille. C'était une faille qui n'attendait qu'un appelant.**

**Deux lignes de production** : les deux jumeaux passent en `private static`. Le
contrat cesse d'être documentaire et devient **structurel** — hors de sa classe,
l'appel ne compile plus. Mesuré avant : aucune classe n'étend `AOD_COD_Pixels` ni
`AOD_CD_Dashboard` (l'héritage du dépôt ne concerne que les `AOD_Carrier_*`).

Le livrable est le cliquet **`tests/unit/test-nonce-contract.php`** (22 assertions).
Il énumère **dynamiquement** les **59** méthodes de `plugins-dev/` qui lisent une
superglobale et exige de chacune l'une des trois : elle nonce elle-même (**33**),
elle **délègue** à une méthode de sa classe qui nonce (**5**), ou elle est
**déclarée avec sa raison** (**16**). Les **5** helpers de la famille C ne se
déclarent pas : le test **énumère leurs appelants** et exige que chacun soit noncé.
C'est ça, la fermeture de D15 — le contrat devient exécutable.

🚨 **Cinq faux positifs, tués par la mesure.** Le premier passage désignait
`ajax_save_member`, `ajax_delete_member`, `ajax_centers`, `ajax_send` — quatre
handlers AJAX **sans nonce**, soit une CSRF sur la suppression de compte. Ils
délèguent tous d'un cran (`Team::guard():328`, `Shipping::check_ajax():925`).
→ **Un cliquet qui ne résout pas la délégation crie au loup sur du code sain** ; on
l'assouplit dans la semaine, et il ne mord plus sur rien.

🚨 **La 5ᵉ est celle que la story avait ratée**, et c'est la plus instructive.
`AOD_CD_Account::render_page` (`:58` → `handle_create():151`) délègue depuis un
appel **imbriqué dans un `if`** — forme qu'un résolveur réglé sur les 4 autres cas
manque. Si elle ressortait rouge, le réflexe était de la déclarer « lecture
d'affichage, ne mute rien ». **C'eût été faux** : `handle_create()` appelle
`wp_insert_user()`. On aurait gravé une **fausse déclaration de sécurité sur la
méthode qui crée les comptes** — le mode d'échec exact que la phase prétend fermer.
Trouvée par la **relecture indépendante**, pas par l'auteur.

⚠️ **Ce que le cliquet ne prouve pas**, écrit dans son en-tête : il est **statique**
(il n'enverra jamais une requête forgée) et **indifférent à l'ordre** — il prouve
« un nonce est vérifié dans ce chemin d'appel », pas « avant la première lecture ».
Choix assumé : 3 des 5 délégations lisent avant d'appeler leur garde, et exiger
l'ordre ferait rougir du code sain.

**Éprouvé au rouge 3 fois**, puis restauré : appelant non noncé → R4 nomme fichier
et ligne ; entrée retirée de la table → R1 ; visibilité rouverte → R5. Ce dernier
n'existait pas dans la première rédaction : sans lui, repasser un helper en `public`
aurait été une régression **silencieuse et verte** — le défaut de la phase reproduit
un étage plus bas. **453 tests (+17).**

---

## Phase 25 — « Désinstallation propre » : des droits qu'on croit révoqués

**Statut : ✅ SOLDÉE le 2026-07-21 (1/1).** Source : audit des deux `uninstall.php`.

Les deux fichiers annoncent une *« désinstallation propre »* et énumèrent les
résidus dans des **listes écrites à la main**. Une liste en dur dérive dès qu'un
résidu naît ailleurs — et rien ne le signale : une option oubliée n'émet aucune
erreur, elle reste en base.

**Trois résidus réels, mesurés :**

1. 🔴 **Les 8 capabilities posées PAR UTILISATEUR n'étaient jamais retirées.**
   `class-aod-cd-roles.php:43` le dit pourtant : *« attribuées par utilisateur via
   `WP_User::add_cap()`, jamais au rôle »*. Or `uninstall.php` ne bouclait que sur
   `$roles->role_objects`, et `remove_role()` ne touche pas le usermeta
   `{prefix}capabilities`. **Vérifié sur le clone : les 2 confirmateurs réels
   portent chacun `aod_cap_confirm_orders` et `aod_cap_tracking`** — aucun n'aurait
   été nettoyé. Des droits qu'on croit révoqués et qui ne le sont pas.
2. 🔒 **`aod_cod_turnstile` — la clé secrète Cloudflare — survivait en clair.**
   Absente de la liste, couverte par aucun motif `LIKE`. Un secret qu'on croit
   révoqué est pire qu'un secret oublié : personne ne va le chercher.
3. **Le motif de transients du dashboard avait dérivé** : il nommait *une* section
   (`aod_cd_home_`), la section Stock a copié le mécanisme avec son préfixe.
   *(Couverture accidentelle par le motif large du COD — donc nulle : supprimer le
   dashboard seul laissait ses caches.)*

Ajouté au passage : les métas de contenu marchand (page de vente, textes AR,
catégories) sont désormais **conservées à dessein, avec la décision écrite** dans
`uninstall.php`. C'était jusque-là un silence, pas un choix.

🚨 **La v1 de la story était un contre-exemple d'elle-même.** Intitulée « options
orphelines », elle a audité `wp_options`, y a trouvé les points 2 et 3, et déclaré
la désinstallation propre — en laissant le point 1, le plus grave, hors de son
champ. **Le titre avait servi d'œillère.** Trouvé par la relecture indépendante.
→ Leçon : **on cadre un audit par ce que le code CRÉE, jamais par la famille de
stockage qu'on a en tête.**

🚨🚨 **Et le correctif de production a désarmé le cliquet de sa propre story.**
Pour énumérer les porteurs d'une capability, la correction introduit un
`SELECT … FROM usermeta WHERE meta_value LIKE '%' . esc_like( 'aod_' ) . '%'`.
L'extracteur de motifs du cliquet ne regardait pas **à quoi sert** un `LIKE` : il a
moissonné ce filtre de **lecture** comme une preuve de **suppression**. Or `aod_`
préfixe toute option du dépôt → **R1 est devenue universellement verte**.
Détecté **uniquement** parce que la preuve au rouge a refusé de rougir. Le cliquet
ne retient plus que les motifs d'une instruction `DELETE FROM …options`.
→ **Un cliquet qu'on n'a jamais vu échouer ne prouve rien.** Ici, l'omettre coûtait
la story entière : elle aurait été livrée verte, sans rien garder.

⚠️ **Limites écrites dans l'en-tête du test.** Il est **statique** (il lit les
fichiers, il ne désinstalle rien). Et la **propriété** d'une option n'a aucun signal
mécanique ici : `aod_seq_order_counter` est *écrite* par le dashboard sans lui
appartenir (elle est au mu-plugin), les `aod_cod_*` sont écrites par le dashboard en
appartenant au COD — « qui écrit possède » est faux **dans les deux sens**. La
propriété reste une **déclaration humaine motivée**. Le cliquet détecte le
**non-déclaré**, pas le **mal-déclaré**. Angle mort assumé : les noms d'options
entièrement construits (`aod_carrier_*`) ne sont pas découverts.

**Éprouvé au rouge 3 fois**, puis restauré : secret retiré de la liste → R1 le nomme ;
motif de transients re-rétréci → R4 ; déclaration retirée → R1. **R4 ne rougissait
pas d'abord** — elle jugeait sur l'**union** des deux `uninstall.php`, où le motif
large du COD masque la dérive du dashboard ; elle juge désormais chaque plugin sur
**son** fichier. Le retrait des capabilities a par ailleurs été **exécuté pour de
vrai** sur un compte jetable (créé puis supprimé, comptes réels intacts) : caps et
rôles effectivement retirés, usermeta vidé — ce que le test statique ne peut pas
prouver. **489 tests (+36).**

---

## Phase 26 — Deux des cinq couches anti-bot n'en étaient pas

**Statut : ✅ SOLDÉE le 2026-07-21 (1/1).** Source : relecture indépendante des
5 couches, constats **revérifiés ligne à ligne** avant toute correction.

`TEMPLATE.md` annonce un « anti-bot **5 couches** ». Sur un clone tel que livré,
deux ne tenaient pas — et rien ne le disait.

**🔴 Couche 3 — le coupe-circuit IP comptait par EN-TÊTE, pas par visiteur.**
Il s'indexait sur `WC_Geolocation::get_ip_address()`, qui renvoie `X-Real-IP`
**brut** (`class-wc-geolocation.php:83-84`) — sans même le `rest_is_ip_address()`
que ses **deux branches suivantes** appliquent pourtant. Un en-tête est écrit par
le client : `X-Real-IP: <aléatoire>` à chaque requête donnait une clé `md5()`
neuve, et le plafond de 10/heure n'était **jamais** atteint. Une ligne de `curl`
suffisait.
→ Ce n'est **pas** un bug de WooCommerce : `get_ip_address()` sert à deviner un
pays pour pré-remplir un formulaire, où un visiteur qui se ment ne nuit qu'à lui.
**Le défaut était de réutiliser une source de CONFORT comme source de SÉCURITÉ** —
c'est la leçon transposable, plus que la ligne corrigée.

**🔴 Couche 4 — Turnstile laissait passer en silence, sans borne ni fin.**
`verify()` retournait « valide » dès que Cloudflare était injoignable. Politique
assumée (ne pas bloquer un vrai client pendant un incident), mais trois effets
qu'aucun écran ne révélait : le contournement coûtait **un caractère**
(`cf_turnstile=x` franchit le test « jeton vide », déclenche l'appel, le voit
échouer, passe) ; sur un clone **sans réseau sortant** — le cas que TEMPLATE.md §3
pose comme **nominal** — l'échec est permanent, donc la couche était ouverte en
permanence et non pendant une panne ; et la page de réglages affichait « ✅ Actif ».
S'y ajoute que **aucune clé n'est configurée sur un clone neuf** : la couche
n'existe simplement pas tant que le gérant ne l'a pas renseignée.
→ **Le défaut n'était pas la politique, c'était son SILENCE.** Un fail-open assumé
et annoncé se défend ; un fail-open muet et sans fin, non.

**Le correctif.** Source d'IP unique `AOD_COD_IP::client()` — `REMOTE_ADDR`, la
seule valeur que le client ne peut pas forger (elle vient de la couche TCP) ; les
en-têtes de proxy ne sont lus que si `AOD_COD_TRUSTED_PROXY` est déclarée, **et
sont validés** (une valeur non-IP retombe sur `REMOTE_ADDR`, jamais sur une clé de
compteur neuve). Fail-open Turnstile **borné** (`AOD_COD_TURNSTILE_MAX_FAILOPEN`,
20 échecs consécutifs par défaut, filtrable) **et visible** : `/gestion → Accueil`
alerte dès le **premier** échec, en `warn` puis en `bad` une fois le seuil franchi.
Politique arbitrée par l'owner (fail-open borné plutôt que fail-closed strict :
une panne Cloudflare ne doit pas arrêter les ventes de tous les clones).

⚠️ **Effet de bord à connaître à la mise en ligne.** Derrière un proxy (Cloudflare,
load-balancer), `REMOTE_ADDR` est l'IP **du proxy** : sans `AOD_COD_TRUSTED_PROXY`,
tous les acheteurs partagent un compteur et se font bloquer à la 10ᵉ commande du
site. C'est un faux positif **visible** (des acheteurs qui se plaignent), pas une
protection qui ment : le défaut est du bon côté, et c'est documenté dans le runbook.

🚨 **Le cliquet a d'abord rougi sur ses propres correctifs.** Les commentaires qui
expliquent la correction **nomment** forcément `WC_Geolocation` — c'est tout leur
intérêt — et un grep de texte y voyait un appel. Le test passe désormais par
`token_get_all()` et n'analyse que le **code**.
→ Un contrôle qui confond la prose et le code crie au loup sur la documentation,
et pousse à écrire du code **moins expliqué** pour le faire taire.

**Éprouvé au rouge 2 fois**, puis restauré : la faille d'origine réintroduite dans
le helper → R1 rougit en affichant `obtenu 'forge-abc-123'`, soit **la signature
exacte du défaut** ; `WC_Geolocation` rebranché dans la garde COD → R2. Les 4
chaînes UI sont traduites en AR (`--strict` vert). **512 tests (+23).**

---

## Phase 27 — Quatre portes de recette qui verdissaient sans tout éprouver

**Statut : ✅ SOLDÉE le 2026-07-21 (1/1).** Source : relecture indépendante des
portes de recette, constats **revérifiés un à un** avant correction.

La recette est le juge de dernier ressort — c'est elle qui dit « c'est fini ».
Quatre de ses portes annonçaient une couverture qu'elles ne tenaient pas. Même
famille de défaut que tout le reste : **un contrôle qui ne casse rien, donc qu'on
ne voit pas.**

**🔴 `mouvement.js` — un sélecteur mort escamotait un quart du contrôle.**
`PRESSABLES` visait `.aod-cd-tab`, reliquat de l'étape 5.4 (le segmented control
`.aod-cd-seg__btn` l'a remplacé) : il ne matchait plus **rien**. Les trois boucles
font `if ( ! count ) continue;` — donc trois assertions (survol, appui, mouvement
réduit) disparaissaient **en silence à chaque run depuis la Phase 16**. Pire : si
`.aod-cd-btn` était renommé un jour, **tout** le contrôle « appui + mouvement
réduit » de `/gestion` s'évaporerait sans une ligne de rouge. → Entrée morte
retirée, et un **témoin** (bloc 1bis) exige que chaque cible matche au moins un
élément — un sélecteur mort rougit désormais au lieu de sauter.

**🔴 `run.sh` / `a11y-contraste.js` — « six accents » pouvait n'en éprouver qu'un,
et un chemin effaçait l'accent du gérant.** Deux défauts dans la porte que le
README érige en « piège n°1 ». (a) `run.sh` posait l'accent par `wp theme mod set`
(sortie avalée) et la porte JS **ne lisait jamais l'accent** : `set` échoué en
silence (wpcli OOM) → six audits du **même** accent, six verts. (b) L'accent
d'origine était capturé **avant** de relancer wpcli ; capture vide sur service
tombé, indistinguable d'un clone sans accent → `restore_accent()` faisait un
`theme mod remove` et **effaçait la couleur de marque du gérant** (sur ce clone,
`#00A651`). → Témoin d'accent (la porte lit `--cd-accent` sur la page, rougit sur
écart) ; **sentinelle** distinguant « pas d'accent » de « lecture impossible », qui
ne restaure jamais destructivement ; et la porte relance wpcli comme les 9 autres.

**🔴 `a11y-souris.js` — le bloc catégories s'escamotait, là où `a11y-clavier.js`
déclare l'absence impossible.** Le même widget (`.aod-cd-catdd-btn`), rendu
**inconditionnellement** (`section-products.php:491`), était traité par un
`if ( count ) {…}` **sans else** côté souris — un clone sans catégorie perdait le
tiers du contrôle en silence. Le raisonnement « ici l'absence n'est jamais
légitime » était déjà **écrit dans le dépôt**, mais dans l'autre fichier. → Bouton
absent = `t( false )` ; case absente (légitime sans catégorie) = **témoin** publié,
plus un silence.

**🔴 `description-riche.js` — une assertion d'absence rendue vraie par la ligne du
dessus.** Le bloc 4b vérifiait que `/gestion → Commandes` n'affiche pas la
description riche… **après** avoir supprimé la seule commande qui la contenait.
Vraie par construction : une vraie fuite (`/gestion` rendant `the_content`) serait
restée verte. C'est exactement la règle que le README pose (« une absence doit
s'adosser à une présence »), enfreinte. → Contrôle **remonté avant** la suppression,
et **adossé à une présence** (le nom du client de garde DOIT, lui, apparaître).

**Éprouvé au rouge partout où un garde-fou a été posé :** sélecteur mort → témoin
mouvement rouge ; accent qui ne prend pas → témoin contraste rouge (`vu : #00a651`
quand `#123456` est demandé) ; capture échouée → `restore_accent()` ne fait **aucun**
`remove` (prouvé en isolant la logique) ; bouton catégories absent → `t( false )`
rouge. L'accent de marque de l'owner (`#00A651`) est **intact** après la passe
complète. Les 12 sections et les 5 états interactifs de la porte contraste
repassent, sur les 6 accents, chacun **vérifié présent sur la page**.

---

## Phase 28 — Le tri des 316 violations phpcs jamais examinées

**Statut : ✅ SOLDÉE le 2026-07-21 (2/2).** Solde **D14** : phpcs
n'était « absent de l'hôte comme du conteneur » que faute d'avoir essayé — le réseau
sortant du conteneur fonctionne, `phpcs` 4.0.1 + WordPress-Coding-Standards 3.4.0
s'installent et tournent.

Le socle phpcs+WPCS n'avait **jamais** été exécuté ici. Une fois lancé : **316
violations de sécurité non couvertes par une annotation** (les 172 annotations
existantes en suppriment 215 de plus). Toutes triées, à la main et par 3 auditeurs
indépendants, chaque constat sensible **revérifié moi-même** :

| Famille | Hits | Verdict |
|---|---|---|
| Injection SQL (`PreparedSQL`) | 14 | **0 réel** — noms de table `$wpdb->prefix.…` (non paramétrables), fragments préparés concaténés |
| Requêtes directes (`DirectDatabaseQuery`) | 30 | **0 réel** — table custom `aod_cod_leads`, perf pas sécurité |
| CSRF (`NonceVerification`) | 110 | **0 réel** — 20 méthodes, toutes noncées (délégation intra-classe, helpers d'un handler noncé, $_GET de navigation) |
| Sanitisation (`ValidatedSanitizedInput`) | 66 | **0 réel** — sanitiseurs whitelist maison, exception légitime du mot de passe |
| XSS (`EscapeOutput`) | 96 | **0 réel** — helpers d'échappement, déjà-échappé, littéral ; `page_head($actions)` vérifié sur ses **14 appelants** |

**316/316 faux positifs.** WPCS ne suit ni la délégation de nonce, ni les
sanitiseurs/échappeurs maison, ni l'interpolation de nom de table, ni les fragments
SQL préparés entre méthodes. Les deux cliquets existants
(`test-nonce-contract.php`, `test-phpcs-ignore.php`) tenaient déjà la garde. **Aucune
faille réelle — la surface de sécurité des deux plugins est saine.**

🔎 **Défaut latent trouvé PENDANT le tri.** Deux annotations (`pages.php:307`,
`dashboard.php:2091`) écrivaient leur raison après un tiret **cadratin** « — » au
lieu du « -- » que phpcs exige. phpcs lit alors `SNIFF — raison` comme une liste de
sniffs, ne reconnaît rien, et **n'honore pas l'annotation** : l'exception paraissait
relue, elle ne supprimait rien. C'est l'annotation **nue** de R1 un cran plus loin —
**inerte** au lieu d'absente, donc encore moins visible. Les deux couvraient du code
sûr (sortie WooCommerce, pastille échappée), donc zéro impact ; mais le format se
recopie. → Corrigées, et nouvelle règle **R4** au cliquet : aucune annotation ne
porte d'em-dash dans sa portion sniff.
🚨 **R4 a d'abord été trop large** : un premier jet grepait la ligne entière et
flaguait `class-aod-shipping.php:429`, dont l'em-dash est dans le **code**
(`$name . ' — ' . $meta`), pas dans l'annotation. Resserré sur les **jetons de
sniff** (après `phpcs:ignore`, avant `--`). **Éprouvé au rouge**, puis restauré.

⚠️ **Décision laissée à l'owner** : câbler phpcs en CI. La toolchain marche ; la
baseline est 316 faux positifs. Un gating exigerait de tous les annoter (lourd) ou
de générer un baseline phpcs. Hors périmètre du tri. La **28.2** a ensuite **soldé
D14** : les 7 suppressions `WordPress.DB` larges resserrées sur leurs sniffs exacts,
resserrage **vérifié à l'outil** (zéro résidu). Son blocage annoncé — « phpcs
indisponible » — n'était qu'une hypothèse non testée.

---

## Phase 29 — La page de connexion ignorait la marque du client

**Statut : ✅ SOLDÉE le 2026-07-21 (1/1).** Source : le seul écran de `/gestion`
que le Personnalisateur ne repeint pas tout seul.

`wp-login.php` a sa propre feuille (`login_enqueue_scripts`), et elle codait son
accent **en dur** : `$accent = '#111827'`. Le bouton de connexion restait donc
gris-ardoise **quel que soit** l'accent choisi par le client. Un gérant à marque
verte (`#00A651`) — le tout premier écran qu'il voit en arrivant sur SA boutique —
ouvrait sur un bouton qui n'était pas le sien. C'est un manquement à la **Règle
d'or n°1** (« branding via le Personnalisateur, jamais codé en dur »).

**Le correctif** : la page login injecte désormais le **même** bloc de charte que
`/gestion` — `AOD_CD_Customize::css_vars()`, le `:root{--cd-accent…}` dérivé du
client — et le bouton primaire prend `var(--cd-accent)` en fond, `var(--cd-accent-ink)`
en texte.

🚨 **Le piège de contraste, évité des deux côtés.**
- Le **bouton** : `--cd-accent-ink` est l'encre que `best_ink` calcule pour
  contraster sur N'IMPORTE quel accent (garanti AA par `test-contraste.php`). Poser
  le blanc par défaut de WordPress aurait rendu le texte illisible sur un accent
  clair — le jaune par défaut, justement.
- Le **nom du site en repli** (pas de logo) : lui reste sur un neutre sombre, PAS
  l'accent. Il est posé sur le fond **clair** de la page ; un accent de client clair
  y serait illisible — le piège inverse. Le neutre est sûr quel que soit l'accent,
  et ce n'est pas une valeur « client », donc pas une entorse à la règle.

Livrable : le cliquet `tests/unit/test-login-charte.php` (capture le CSS réellement
injecté par `login_styles()`) — R1 la charte du client est injectée, R2 le bouton
lit l'accent + son encre, R3 le fond n'est pas un hex en dur (le retour en arrière
interdit). **Éprouvé au rouge** (bouton reremis en dur → R2/R3 rougissent).
Le cliquet `test-phpcs-ignore.php` a fait son travail au passage : l'`echo css_vars()`
ajouté est une annotation `EscapeOutput` de plus (census 132 → 133), justifiée sur
place (même motif, même sûreté que `dashboard.php:394`). **518 tests (+6).**

---

## Phase 30 — Décomposition du fichier-dieu `/gestion` (chantier soldé)

**Statut : ✅ SOLDÉE le 2026-07-23 (6/6 — la 30.6 clôt le pattern).** Cadré avec
l'owner : **PHP d'abord** (dashboard.js viendra ensuite), **pilote puis on rejuge**.

`class-aod-cd-dashboard.php` fait **4229 lignes**. Mais l'architecture cible existe
déjà : le plugin a une convention `AOD_CD_Section_*`, et **6 sections sont déjà
extraites** (home, abandoned, landing, products, stock, team) — chacune une classe
autonome qui enregistre ses `wp_ajax_*` et expose `render()`. Le monolithe ne garde
que les **6 sections jamais sorties** : orders, tracking, shipping, marketing,
whatsapp, account. **Le chantier ne conçoit rien — il FINIT le pattern.** Le
routeur (`render_section`) est un `switch` qui appelle déjà `Section_X::instance()
->render()` pour les extraites ; extraire = déplacer la section + changer une ligne
du switch + bouger ses `add_action` dans le nouveau constructeur.

**Ordre de risque croissant** (le pilote valide le procédé sur le plus petit) :
whatsapp (~120 l) → marketing → account → shipping → tracking → **orders** (~1200 l,
le plus intriqué, en dernier). Les helpers d'UI partagés (`page_head`, `pill`,
`empty_state`…) **restent** `public static` sur `AOD_CD_Dashboard` : ils servent
toutes les sections, les déplacer casserait 20+ appels.

### 30.1 — Pilote : WhatsApp ✅

`AOD_CD_Section_WhatsApp` (4 méthodes : `render`, `settings`, `ajax_save_whatsapp`,
`ajax_test_whatsapp`). **Extraction à comportement CONSTANT, prouvée** :
- **Move verbatim** : mêmes réglages (`aod_cod_whatsapp`), **mêmes actions AJAX**
  (`aod_cd_save_whatsapp`/`_test_whatsapp`) donc **`dashboard.js` inchangé**, même
  HTML. Annotations `phpcs:ignore` déplacées telles quelles → **census stable**
  (aucune perturbation du cliquet `test-phpcs-ignore.php`).
- **Vérifié à 4 niveaux** : 518 tests unitaires verts ; hooks AJAX enregistrés au
  runtime (`has_action`) ; section rendue à l'identique (2964 octets, tous les
  marqueurs) ; **porte recette `rtl` verte** — `/whatsapp` rendu au navigateur en
  FR desktop, FR mobile **et** AR mobile, 0 erreur.
- **Le monolithe maigrit** : 4229 → **4107 lignes**.

Livrable de garde : le cliquet **`tests/unit/test-decomposition.php`** —
R1 **plafond de lignes** (dashboard.php ≤ 4107, descend à chaque section sortie ;
même logique que le plancher phpcs) ; R2 **anti-ré-absorption** (chaque section
extraite vit dans sa classe ET ses méthodes ont QUITTÉ le monolithe — extraire,
c'est déplacer, pas dupliquer). **Éprouvé au rouge** : un stub `section_whatsapp`
remis sur le Dashboard fait rougir R1 (4108 > 4107) ET R2. La table des sections
extraites **grandit à chaque étape**. 529 tests (+11).

---

## Phase 31 — Portée, concurrence, observabilité (ce que 29 phases d'audit de forme n'ont pas vu)

**Origine** : audit A→Z du 2026-07-22 (5 auditeurs croisés + revérification de
chaque constat majeur sur le code). Les 29 phases précédentes ont audité la
**forme** — annotations `phpcs:ignore`, cohérence des gardes, transitions CSS
implicites, hôtes externes, portes creuses. Elles n'ont jamais posé deux
questions :

1. **« Qu'est-ce que cette permission DONNE ? »** — pas « est-elle cohérente »,
   mais « que contient l'écran qu'elle ouvre ». C'est par là qu'est passée la
   faille de la 31.1, sous trois gardes parfaitement alignées.
2. **« Que se passe-t-il à 10 000 commandes ? »** — aucune porte du dépôt ne sème
   du volume, donc aucun défaut de charge ne peut se manifester en recette. Les
   tests resteront verts jusqu'au jour où un clone réussit commercialement.

S'y ajoutait un manque transversal mesuré : **`grep -rn "error_log\|wc_get_logger"
plugins-dev/` → 0 résultat**, pour 6 intégrations HTTP externes toutes en
fail-open. Un incident de production était un diagnostic à l'aveugle chez un
client, sans accès. ✅ **Comblé par la 31.3** (2026-07-22) — et le même `grep`
doit désormais rendre **un seul fichier**, `class-aod-cod-log.php` : c'est la
règle R4 du cliquet, pas une coïncidence.

### Ordre — le coût réel, pas la difficulté

Sécurité d'abord (31.1), puis ce qui coûte de l'argent à chaque vente (31.2),
puis ce qui rend tout le reste diagnosticable (31.3). Les trois suivantes sont
des filets et de la tenue en charge.

**Avancement : 6 sur 6 — EPIC SOLDÉ** le 2026-07-22.

### 31.1 — La case « tarifs » donnait les comptes transporteurs ✅

Livrée le 2026-07-22 (`2426dc1`). Cf. §C.3.

### 31.2 — Deux commandes pouvaient porter le même numéro ✅

Livrée le 2026-07-22 (`12e532c`). Cf. §C.3. **Moitié restante** : le double colis
(31.4) — même classe de défaut, garde non atomique, mais autour d'un appel HTTP.

### 31.3 — Aucune ligne de journal, nulle part ✅

**Livrée le 2026-07-22.** Cf. §C.3 pour le compte rendu, et
`plugins-dev/aod-cod-form/includes/class-aod-cod-log.php` pour le raisonnement
complet sur les trois couches de rédaction. **Solde également la dette D22.**

> 🚨 **À savoir avant d'y toucher.** La règle qui protège dans la durée n'est pas
> « le rédacteur masque bien » (R1) mais « **rien ne journalise en dehors du
> rédacteur** » (R4). Un `wc_get_logger()->error( $url )` écrit en direct — le
> geste le plus naturel du monde — court-circuiterait les trois couches d'un
> coup, et R1 resterait vert. Si R4 gêne un jour, la réponse est d'élargir
> `AOD_COD_Log`, jamais de contourner.

<details><summary>Énoncé d'origine</summary>

**Problème.** 0 `error_log`, 0 `wc_get_logger` sur 24 412 lignes, pour
6 intégrations externes (4 familles de transporteurs, 3 régies publicitaires,
WhatsApp, Turnstile) **toutes en fail-open documenté**. Le `catch` qui entoure la
création de commande (`class-aod-cod-form.php:1266`) n'inspecte ni ne logue `$e` :
une vente perdue ne laisse **aucune trace**.

**Pourquoi c'est la story qui rend le plus.** Elle ne corrige aucun bug : elle
rend les autres **diagnosticables**. Le bug TikTok documenté en Phase 13 (« le
pixel paraissait actif et zéro achat serveur n'arrivait ») a vécu exactement de ce
silence. La Phase 26 a traité le cas Turnstile en rendant l'échec visible dans
`/gestion` — bonne réponse, mais ponctuelle : elle soigne un symptôme d'un manque
général.

**AC esquissés.** `wc_get_logger()` (déjà fourni par Woo, avec rotation et
visionneuse) sur les 6 sorties + le `catch` de `handle_submit()`. 🚨 **Aucun
secret dans les logs** — l'audit de sécurité a relevé que le dépôt n'en journalise
aucun aujourd'hui, c'est un acquis à ne pas perdre : cliquet exigeant qu'aucun
`api_token` / `capi_token` / clé Turnstile n'atteigne un logger. Niveau `error`
pour un échec, `debug` pour le détail, jamais `info` par défaut (un log qui grossit
sans raison finit désactivé).

</details>

### 31.4 — Double colis chez le transporteur ✅

**Livrée le 2026-07-22.** Cf. §C.3 pour le compte rendu complet.

> 🚨 **Ce qui reste OUVERT et n'a PAS été fait.** L'envoi transporteur tourne
> toujours **en synchrone** dans la requête du confirmateur (30 s d'attente
> possible sur `ajax_order_status`). Le sortir vers Action Scheduler était la
> seconde moitié de l'énoncé ci-dessous — **non faite à dessein** : cela change
> ce que voit le confirmateur (il n'aurait plus le numéro de suivi en retour
> immédiat), donc c'est un arbitrage produit, pas technique. Le verrou rend le
> reclic **inoffensif**, ce qui retire l'urgence. À cadrer avec l'owner si
> l'attente devient gênante — reversé en dette **D27**.

<details><summary>Énoncé d'origine</summary>

**Problème.** `class-aod-shipping.php:220` teste `META_TRACKING`, `:549` l'écrit —
et **tout l'aller-retour HTTP (timeout 30 s) tient entre les deux**. `auto_send()`
est déclenché par `woocommerce_order_status_aod-confirmed`, lui-même appelé
**synchrone** depuis `ajax_order_status`. Deux confirmateurs qui confirment la
même commande à quelques secondes d'intervalle passent tous deux la garde : deux
colis expédiés, deux encaissements COD, un client mécontent. Un seul confirmateur
qui reclique après un timeout navigateur suffit aussi.

**AC esquissés.** Verrou posé **avant** l'appel HTTP et de façon atomique (même
famille que 31.2 : une écriture conditionnelle, pas un `if` suivi d'un `set`).
Sortir l'envoi transporteur de la requête synchrone (Action Scheduler) est la
vraie réponse au reclic ; à cadrer avec l'owner car cela change le retour
utilisateur. ⚠️ **Ne pas éprouver au rouge en appelant une vraie API
transporteur** : quota de production et configuration du gérant. Le mécanisme se
prouve sur un transporteur bouchonné, comme `scripts/recette-capi.php` le fait
déjà pour les régies (interception `pre_http_request`).

</details>

### 31.5 — Deux portes de recette ne prouvent pas ce qu'elles annoncent ✅

**Livrée le 2026-07-22.** Cf. §C.3 — **cinq** défauts distincts, pas deux :
le repli de routage touchait à lui seul **trois** portes.

> 🚨 **La leçon, pour la prochaine porte écrite ici.** Les cinq défauts ont la
> même forme : *une assertion qu'un cas dégénéré suffit à rendre verte*. Menu
> vide, liste paginée, page non routée, propriété CSS qui n'est jamais vide,
> disjonction dont un membre suffit. Aucun ne casse rien, aucun n'émet d'erreur —
> **ils se lisent, ils ne se voient pas.**
>
> Deux règles en découlent, et elles se sont vérifiées pendant l'écriture même de
> cette story : **(a)** une assertion d'ABSENCE doit s'adosser à une PRÉSENCE
> mesurée dans le même état ; **(b)** un ancrage faux est pire qu'un ancrage
> absent — deux des ancrages posés ici étaient eux-mêmes satisfaits par l'écho du
> terme cherché dans le champ de recherche, et n'ont été démasqués qu'en JOUANT
> les portes, pas en les relisant.

<details><summary>Énoncé d'origine</summary>

**Problème 1 — la porte `rtl` est aveugle, et c'est le filet de la Phase 30.**
`class-aod-cd-dashboard.php:320-322` : un slug inconnu ne produit ni 404 ni
erreur, le routeur retombe sur la première section visible. `rtl-mobile.js:42-43`
l'admet en commentaire. La porte rendrait **12 fois `home` en imprimant 12 ✅** —
elle ne peut pas voir qu'une section extraite ne se route plus. Même trou dans
`a11y-noms.js` et `a11y-contraste.js`. Correctif : recopier `sectionRendue()` de
`confirmateur.js:69`.

**Problème 2 — `mouvement.js:263`** : `t( !! bg, … )` est inconditionnellement
vrai (`getComputedStyle().backgroundColor` ne renvoie jamais vide). Son jumeau
public ligne 451 est correctement durci — le durcissement n'a pas été recopié.

Voir aussi, plus bas : `public-3-pages.js` (absences non ancrées en AR),
`commandes-suivi.js` (absence non ancrée par une recherche),
`test-antibot.php:261` (disjonction toujours vraie).

</details>

### 31.6 — L'Accueil de `/gestion` meurt à volume ✅

**Livrée le 2026-07-22 — l'epic Phase 31 est soldé (6/6).** Cf. §C.3.

> 🚨 **À savoir avant d'y toucher.** Trois des défauts de cette story ont été
> trouvés **en mesurant**, aucun en relisant : une clé de requête ignorée en
> silence (`include`), un cache purgé au mauvais endroit, et **l'instrument de
> mesure lui-même faux** (`memory_get_peak_usage()` est monotone). Le premier
> aurait envoyé un CA faux en production sans la moindre erreur PHP.
>
> La règle qui en sort : **sur un sujet de charge, on ne raisonne pas, on
> instrumente — et on vérifie d'abord que l'instrument dit vrai.** L'énoncé
> ci-dessous annonçait « plusieurs Go de pic » : la mesure donne 94 Mo. La
> direction était juste, l'ordre de grandeur non. Une estimation n'est pas une
> mesure, même écrite dans une ROADMAP.

<details><summary>Énoncé d'origine</summary>

**Problème.** 9 × `limit => -1` (relevés), dont 4 sur les commandes avec un N+1
`get_items()` derrière — `WC_Order::get_items()` n'est pas préchargé par
`wc_get_orders()`, c'est une requête par commande. À 10 000 commandes :
~20 000 requêtes et plusieurs Go de pic. Aggravé par un cache dont le compteur de
version est **global** (`section-home.php:69-81`) : une commande toutes les
3 minutes invalide en permanence un TTL de 5 minutes. **Le cache protège la
boutique qui n'en a pas besoin et lâche sur celle qui en a besoin.**

**AC esquissés.** Agrégats en `SUM`/`GROUP BY` sur `wc_order_product_lookup`, ou à
défaut itération par lots. Versionnement du cache **par bac de date**. Et surtout
une porte `tools/recette/run.sh charge` qui sème 10 k commandes et mesure temps +
pic mémoire : sans elle, ces défauts redeviendront invisibles. 🚨 Cette porte sème
du volume — elle doit travailler sur des données **à elle** et les purger, jamais
sur la boutique du clone.

</details>


## Phase 32 — Hors du chemin nominal

**Ouverte le 2026-07-22**, à la clôture de la Phase 31 (6/6).

### Le fil qui relie ces défauts

La Phase 31 traitait des défauts de **conception** : une permission trop large,
une garde non atomique, un silence de diagnostic. Ceux qui restent ont une autre
forme, et c'est elle qui donne son unité à cette phase :

> **Le code est juste tant que tout est présent, activé, bien intentionné et peu
> nombreux. Il casse — ou pire, il ment — dès qu'on en sort.**

Un plugin désactivé, une section jamais instanciée, un fichier hostile, un
formulaire public martelé, un bouton recliqué, un binder qui échoue. Aucun de ces
cas n'apparaît pendant qu'on développe : on développe avec tout installé, en
gérant, sur trois commandes. **C'est exactement le profil des défauts qui
n'atteignent le dépôt que chez le client.**

⚠️ **Ce que cette phase NE contient PAS.** La décomposition du fichier-dieu
(Phase 30) était un chantier à part — resté ouvert pendant toute la Phase 32,
**soldé depuis** (30.6, le 2026-07-23) — avec son propre cliquet
(`test-decomposition.php`) et sa propre porte (`rtl`) — ne pas le fondre ici.

### Ordre — le coût réel

Ce qui empêche le site de fonctionner (32.1), puis ce qui l'expose (32.2, 32.3),
puis ce qui le rend inutilisable sous la main du gérant (32.4), puis les
silences (32.5, 32.6).

### 32.1 — Le démarrage n'est jamais éprouvé en mode dégradé ✅

**Livrée le 2026-07-22.** Cf. §C.3. ⚠️ **D18 s'est révélée être un FAUX POSITIF** et a été retirée : `AOD_CD_Section_Abandoned` n'enregistre aucun hook, ne pas l'instancier au boot est correct.

**Problème.** `/gestion` tombe en **erreur fatale** si `aod-cod-form` est
désactivé : `class-aod-cd-section-home.php` résout `AOD_Carrier::ST_FAILED`
**comme argument d'appel**, donc AVANT le `class_exists()` censé garder la ligne.
Le garde-fou existe, il est simplement évalué trop tard. Deux plugins, deux
interrupteurs indépendants dans wp-admin : un clic suffit, et l'espace gérant est
mort — pas dégradé, mort. (Dette D17.)

S'y ajoute `AOD_CD_Section_Abandoned`, chargée par `require_once` mais **absente
de la liste d'instanciation** : elle ne survit que parce qu'une autre classe
enregistre ses hooks par ricochet. Personne ne l'a vu parce que ça marche. (D18.)

**AC esquissés.** Corriger l'ordre d'évaluation. Puis un cliquet qui **démarre
réellement** `/gestion` avec `aod-cod-form` désactivé et exige zéro fatale — et
un contrôle que **chaque section requise est instanciée**, pas seulement chargée.
🚨 Le cliquet doit rétablir l'état des plugins quoi qu'il arrive : désactiver le
plugin public d'un clone en cours de recette, c'est couper la boutique.

### 32.2 — La surface d'entrée non authentifiée 🟠 ✅ **LIVRÉE le 2026-07-22**

> ⚠️ **Ce titre est faux sur la moitié de son périmètre, et on le laisse tel quel
> pour que la leçon reste lisible.** Les cinq téléversements ne sont PAS une
> surface non authentifiée : ils exigent `check_ajax_referer( 'aod_cd' )` **et**
> `current_user_can( 'edit_products' )`, et aucun confirmateur ne reçoit cette
> capability. Une seule des deux moitiés de la story l'était vraiment —
> `aod_cod_lead`. **Cadrer un correctif par la famille de risque qu'on a en tête
> plutôt que par ce que le code fait**, c'est l'erreur déjà commise en 25.1 sur
> les « options orphelines ». Elle se recommet vite.


**Problème.** Cinq `media_handle_upload()` sans liste blanche MIME (photo
produit, image d'option, galerie, image de catégorie, images de page de vente) :
on s'en remet aux réglages WordPress, qui autorisent bien plus qu'une boutique
n'en a besoin. Et `aod_cod_lead` (`nopriv`) ne porte qu'un nonce, avec un `token`
**fourni par le client** : une ligne neuve par token inventé, sans plafond ni
rate-limit — la table des prospects se remplit à volonté. (D19, D20.)

**AC esquissés.** Liste blanche explicite (JPEG/PNG/WebP/AVIF) sur les cinq
appels, et plafond par IP sur `aod_cod_lead` en réutilisant `AOD_COD_IP::client()`
(la source unique posée par la Phase 26 — ne pas en réintroduire une seconde).
Cliquet : tout `media_handle_upload()` du dépôt passe par le helper à liste
blanche.

### 32.3 — « 5 couches anti-bot » en annonce 5, en tient 1 🟠 ✅ **LIVRÉE le 2026-07-22**

> ⚠️ **Le titre exagérait dans l'autre sens, et il faut le dire aussi.** Sur un
> clone neuf, deux couches sur cinq étaient réellement contournables (honeypot,
> time-trap) ; le plafond IP et le verrou téléphone, eux, tenaient — « en tient
> 1 » ne comptait que ce qui arrête un bot déterminé. Le diagnostic était juste
> sur le fond, faux sur le chiffre. **Un énoncé de story n'est pas une mesure.**


**Problème.** Le README, `CLAUDE.md` et l'écran de réglages annoncent cinq
couches. Sur un clone neuf : le honeypot se contourne en **ne remplissant pas** le
champ, le time-trap (`isset() && is_numeric()`) se saute en **omettant** le champ,
Turnstile n'existe pas tant qu'aucune clé n'est saisie (§3 de TEMPLATE.md), et il
reste le plafond IP + le verrou 24 h par téléphone. (D21.)

**Ce n'est pas d'abord un problème de sécurité, c'est un problème d'annonce.**
Une protection qu'on croit avoir est plus dangereuse qu'une protection qu'on sait
absente : elle empêche d'en chercher une autre.

**AC esquissés.** Rendre l'absence d'un champ **aussi disqualifiante** que sa
mauvaise valeur (honeypot et time-trap), puis **aligner le discours sur le réel** :
dire ce qui est actif sur un clone neuf et ce qui exige une configuration. La
Phase 26 a montré la voie sur Turnstile — un fail-open assumé et annoncé reste
défendable ; muet, non.

### 32.4 — « Actualiser tout » du Suivi est un auto-DoS 🟠 ✅ **LIVRÉE le 2026-07-22**

**Problème.** `run_sync()` tourne **en synchrone** dans la requête AJAX : jusqu'à
1200 commandes hydratées et 30 appels HTTP séquentiels à 30 s de timeout. La
requête meurt sur le timeout PHP ou le reverse-proxy, le gérant voit « Erreur
réseau », **reclique**, et relance tout depuis zéro. Le même travail en cron peut
dépasser `max_execution_time` sans reprise ni trace : **le suivi se fige alors que
le cron tourne** — pas le symptôme que `docs/CRON-SERVEUR.md` décrit. (D23.)

**Livré.** Moteur par tranches `run_sync_batch()` sous budget de temps (AJAX ~10 s
par requête, `dashboard.js` enchaîne ; cron 120 s), timeout transporteur 30 s →
10 s, journal par passage via `AOD_COD_Log`. 🚨 **La reprise ne se code pas, elle
se lit dans la donnée** : `META_STATUS_AT` est le curseur, `pending_orders()`
l'ordonne. Détail complet en §C.3. Cliquet `tests/unit/test-suivi-lots.php`,
éprouvé au rouge ; transporteur bouchonné aux deux niveaux (stub par réflexion,
`pre_http_request` au navigateur) — jamais une vraie API.

### 32.5 — 16 `catch` au corps vide dans `dashboard.js` 🟡 ✅ **LIVRÉE le 2026-07-22**

**Problème.** Sur 27 initialisations, 16 avalent l'exception sans rien dire et 11
ne sont pas protégées du tout. Un binder cassé disparaît **en silence total** :
pas d'erreur console, pas de trace, juste un bouton qui ne répond plus. (D24.)
*(Mesuré à la livraison : 30 initialiseurs, 19 catch muets — l'énoncé
sous-comptait.)*

**Livré.** Helper unique `init( name, fn )` : isolation complète (un binder cassé
n'emporte plus les suivants) + échec **nommé** en console. ⚠️ Le réflexe s'était
déjà recopié côté acheteur : les 3 `catch` muets publics sont **voulus** (appels
tiers autour du chemin d'achat) et désormais recensés avec leur raison — le
cliquet `test-init-binders.php` refuse tout nouveau venu. Détail en §C.3.

### 32.6 — La documentation décroche de son propre code 🟡 ✅ **LIVRÉE le 2026-07-22**

**Problème.** `CLAUDE.md` et `TEMPLATE.md` désignent §C.3 comme **faisant foi** ;
elle s'était arrêtée à 30.1 alors que 30.2 et 30.3 étaient commitées. Le même
fichier a déjà annoncé deux fois un état de phases que le texte juste au-dessus
contredisait. Des compteurs cités comme des garanties (« 130 annotations », « 26
transitions », « 24 412 lignes ») vieillissent en silence. (D25.)

**Livré.** Cliquet `tests/unit/test-doc-derive.php` — il RECALCULE à chaque run :
en-têtes « ### N.M ✅ » ⇄ lignes §C.3 (R2), compteur « x/6 » de la Phase 32 (R3),
« N sections extraites sur 6 » (R4), « Phases 1 à N soldées » ⇒ rien d'ouvert en
dessous (R5), et toute référence citée existe — cliquets `test-*.php` ET portes
`run.sh <x>` (R6). Il lit les docs via le montage `/repo` (lecture seule, ajouté
à `docker-compose.yml` ; clone existant : `docker compose up -d wpcli`). Détail
en §C.3. ⚠️ Il fige le SQUELETTE (états, compteurs, références) — la prose reste
à la relecture humaine.

### Hors périmètre, et pourquoi

| Dette | Pourquoi elle n'est pas une story ici |
|---|---|
| **D26** — seuil de livraison gratuite perdu | Ce n'est pas du code : une **donnée** à ressaisir par l'owner dans `/gestion → Livraison`. Aucun correctif ne la retrouvera. |
| **D27** — envoi transporteur synchrone | **Arbitrage produit**, pas technique : le sortir en asynchrone retire au confirmateur le numéro de suivi en retour immédiat. À cadrer avec l'owner d'abord. |
| **D16** — porte `confirmateur` flanchée une fois | **Non reproductible** (1 fois sur N). Corriger au jugé serait ajouter du bruit à une porte saine. À rouvrir si ça se reproduit. |
| **D10** — `is_arabic()` suit `is_rtl()` | Ne se déclenche qu'avec un **thème RTL sur un site francophone** — configuration qu'aucun clone n'a. Réel, mais spéculatif. |

> ⚠️ *Table de cadrage, écrite avant la phase et laissée telle quelle.* Depuis :
> **`D10` est soldée** (2026-08-03 — et le « spéculatif » ci-dessus était juste sur
> les faits, faux sur la conclusion : un défaut latent n'est pas un défaut moindre,
> il est seulement hors de portée de toute mesure jusqu'au jour où il ne l'est plus),
> **`D26` est close sans objet**, **`D27` close sans objet** (Phase 47.9). **§C.5 fait
> foi sur l'état des dettes**, pas ce paragraphe.

## Phase 33 — Décomposition de `dashboard.js` (le volet JS de la Phase 30)

**Statut : ✅ SOLDÉE le 2026-07-23 (6/6 — ouverte et close le même jour).**
C'est le « `dashboard.js` ensuite » cadré avec l'owner à l'ouverture de la
Phase 30 (PHP d'abord — soldé). Résultat : coque 3488 → **635 l**, 11 modules
`section-*.js`, zéro changement de comportement (portes `rtl`, `riche`,
`categories` vertes, console exigée propre par la porte elle-même).

`assets/js/dashboard.js` fait **3488 lignes** dans une **seule IIFE** : 28 binders
enregistrés au DOMContentLoaded (+2 internes à `bindProducts`), et 14 helpers
partagés. Contrairement au PHP, la portée est **fermée** : on ne peut pas couper
le fichier en deux balises `<script>` sans perdre la clôture. L'architecture
retenue, mesurée sur le graphe d'appels réel (3 variables top-level seulement,
aucune dépendance de section à section) :

- **`dashboard.js` reste la COQUE** — miroir exact du PHP : les 14 helpers
  partagés (`toast`, `esc`, `trapFocus`, `openOverlayModal`…), le `init()` de la
  32.5, un **registre** (`register( name, fn )`) exposé via `window.aodCdCore`,
  l'**ORDRE CANONIQUE d'exécution** (verbatim l'ordre d'avant la décomposition —
  l'ordre des balises `<script>` ne décide de rien), et les 4 binders
  transverses (`bindNavToggle`, `bindSelects`, `bindAccordionForms`,
  `bindSettingsForms`).
- **11 modules `section-*.js`**, un par section (PLATS dans `assets/js/` : un
  sous-dossier échapperait aux globs des cliquets existants, cf. 18.1). Chaque
  module est une IIFE qui reçoit `window.aodCdCore` et pose des **alias
  locaux** (`var toast = core.toast…`) : les corps de fonctions restent
  **VERBATIM**.
- **Échec nommé, jamais muet** (la politique de la 32.5) : un nom listé dans
  ORDER sans implémentation (module non chargé), ou un binder enregistré hors
  liste, sort en `console.error` avec son nom.
- **Chargement** : `render_app()` imprime la liste ordonnée
  (`dashboard_scripts()`, coque en premier) ; tous les modules sur toutes les
  pages — comportement constant (une seule section a son DOM de toute façon).
  TinyMCE reste imprimé AVANT le module `products`, dont l'init de l'éditeur
  riche vit dans `bindProducts`.

**Cliquets** : `test-decomposition-js.php` (R1 plafond de lignes de la coque ·
R2 anti-ré-absorption par binder · R3 ORDER ⇄ census des `register()` · R4 tout
`assets/js/*.js` du plugin est chargé par `dashboard_scripts()` — un module
orphelin serait un écran mort en silence) ; `test-init-binders.php` adapté au
multi-fichiers (R2 accepte `register( 'x', x )` à égalité avec `init( 'x', x )`).

**Ordre de risque croissant, comme en 30.x** : pilote whatsapp (~35 l) →
sections simples (home, marketing, account, stock, team, tracking) → shipping →
products (+catégories) → landing → **orders en dernier**. Une étape = un move
verbatim + cliquets + porte `rtl`. La table §C.3 fait foi de l'avancement.


## Phase 34 — Appeler / WhatsApp depuis la liste des Commandes

**Le constat.** Confirmer une commande COD, c'est **appeler l'acheteur** — c'est
tout le métier du confirmateur (Phase 1). Or la section qui sert à ça est la
moins outillée du dépôt pour le faire : la liste des Commandes n'offre qu'un
lien `tel:` discret sur le numéro (`render_order_row()`), sans bouton visible ni
canal WhatsApp — alors que la section **Commandes abandonnées** (Phase 11), qui
traite des prospects *moins* avancés dans le tunnel, a déjà ses deux boutons
**Appeler** (`tel:`) et **WhatsApp** (`wa.me`, 0→213) posés dans la cellule
client. Un confirmateur au téléphone toute la journée mérite au moins la même
ergonomie sur les commandes réelles que sur les paniers abandonnés.

**Le verdict : faisable, petit, sans surface nouvelle.** Rendu pur — aucun
AJAX, aucune capability, aucune option, aucune chaîne nouvelle (« Appeler » et
« WhatsApp » sont déjà dans les deux catalogues `.po` via la Phase 11). La
section est déjà gouvernée par `aod_cap_confirm_orders` ; les boutons héritent
du périmètre produits existant puisqu'ils vivent dans des lignes déjà filtrées
par `scoped_orders()`.

**Les trois contraintes qui dessinent l'implémentation :**

1. **Le helper `wa_number()` ne se recopie pas une troisième fois.** Il existe
   en `protected` sur `AOD_COD_Leads` (inatteignable), recopié en `protected`
   sur `AOD_CD_Section_Abandoned` — la leçon du §7.6 d'ARCHITECTURE.md (« un
   raisonnement écrit dans un fichier ne protège pas les autres — seul un
   helper partagé le fait ») interdit une 3ᵉ copie. Et la coque est **au
   plafond exact** de `test-decomposition.php` (R1) : le helper n'ira pas sur
   `AOD_CD_Dashboard`. → Il passe `public static` sur
   `AOD_CD_Section_Abandoned`, consommé par `AOD_CD_Section_Orders` — le
   précédent existe et est documenté (les `get_product_options()` de
   `Section_Products` consommées par `Section_Orders` depuis la 30.6).
2. **La peau s'écrit une fois** (leçon 17.7) : la classe CSS
   `.aod-cd-abandon-actions` est renommée `.aod-cd-call-actions` (elle n'a
   jamais rien eu d'« abandonné » — c'est un rang de boutons d'appel) et sert
   les deux sections. Aucune porte de recette ni cliquet ne référence l'ancien
   nom (vérifié).
3. **Mobile d'abord** : c'est en mobile que `tel:`/`wa.me` ont un sens. Les
   lignes du tableau Commandes s'empilent déjà en `data-label` — vérifier le
   rendu 390 px FR **et** AR/RTL avant de dire fini (porte `rtl`).

### 34.1 — Boutons Appeler / WhatsApp sur les lignes Commandes + modale détail ✅

Dans `render_order_row()` : sous le lien `tel:` existant de la cellule client,
le même rang d'actions que la section Abandonnées (bouton **Appeler** +
**WhatsApp** ghost, icônes `phone`/`whatsapp` du catalogue, tailles identiques).
Dans `render_order_detail()` (la modale) : le même rang dans le bloc « Client »,
sous la ligne téléphone. Rien si la commande n'a pas de téléphone (parité avec
la garde de la section Abandonnées). DoD : cliquets verts (`tests/run.sh`),
porte `rtl` verte (console vide, 12 sections), rendu vérifié au navigateur en
FR/AR mobile, `make-translations.sh --strict` des deux plugins sans chaîne
nouvelle, docs mises à jour (cette table, §C.3, ARCHITECTURE.md).

## Phase 35 — Panier COD multi-produits, togglable par le gérant

**La demande (owner, 2026-07-23).** Des clients veulent « un panier ». Arbitré
avec l'owner (option B retenue) : ce que ces clients veulent, c'est **acheter
plusieurs produits en une seule commande** — pas le tunnel WooCommerce avec
compte. On livre donc un **panier COD** : bouton « Ajouter au panier », panneau
panier côté public, et **une seule soumission par le formulaire COD existant**.
Activable par le gérant, **éteint par défaut** (un clone qui ne touche à rien
garde exactement le comportement actuel — non-régressif par construction).

**Ce que ça ne touche PAS — et c'est le cœur du verdict de faisabilité :**

- **La règle d'or n°3 tient.** Aucun compte, aucun tunnel Woo :
  `AOD_COD_No_Tunnel` reste **entièrement armé** (`/cart`, `/checkout`,
  `/my-account` restent morts — notre panier n'est pas le panier Woo), la nav
  reste à 3 entrées (le panier est un bouton flottant + panneau, jamais une
  entrée de menu), `pages-publiques.sh` inchangé.
- **Le pipeline aval est déjà prêt.** `handle_submit()` est multi-LIGNES depuis
  toujours (`items[i][opt]/[qty]`) ; transporteurs (`build_payload()` itère les
  lignes), pixels (`order_content_ids()`/`order_contents()` itèrent les lignes),
  la modale d'édition de `/gestion`, WhatsApp, la numérotation : tous consomment
  des lignes de commande, peu importe qu'elles portent 1 ou N produits.
- **L'anti-bot est le même** : une commande panier passe par le même
  `aod_cod_submit`, donc les 5 couches, le nonce, le recalcul serveur des prix.

**Ce que ça change, précisément :**

1. `handle_submit()` accepte `items[i][product_id]` (multi-produits) avec
   rétro-compat totale (payload actuel = tous les items sur `product_id`
   racine). Prix recalculés **par produit** : offres/paliers appliqués sur la
   quantité cumulée PAR produit (jamais inter-produits), suppléments de
   variantes par ligne, livraison calculée une fois (seuil de gratuité sur le
   sous-total de la commande, comme aujourd'hui).
2. Une option `aod_cod_cart` (`'1'` = panier actif), propriété d'`aod-cod-form`
   (le comportement public vit chez lui), éditée depuis `/gestion` — carte
   **réservée au gérant** dans la section Livraison, même précédent que le bloc
   Transporteurs (31.1) : la section est gouvernée par
   `aod_cap_shipping_prices`, la carte et son AJAX exigent
   `manage_woocommerce` — divergence déclarée **avec sa raison** dans
   `test-permissions-sections.php` (le mode d'achat de la boutique n'est pas
   un tarif).
3. Côté public, toggle ON : bouton « Ajouter au panier » sur la fiche produit
   (à côté de « Commander », qui garde son comportement), panneau panier
   (contenu en `localStorage`, clé `aod_cart` — **aucun état serveur**, pas de
   session, pas de cookie de plus), et le formulaire COD du panneau soumet
   tous les articles. Toggle OFF : **pas un octet de différence** avec
   aujourd'hui (ni bouton, ni JS chargé).

**Les pièges identifiés au cadrage (à traiter dans les stories) :**

- **Les libellés du panier doivent suivre `aod_lang`** : le `localStorage` ne
  stocke que des IDs/quantités/options ; les noms affichés sont (re)demandés au
  serveur (endpoint lecture seule) pour sortir dans la langue courante — un
  titre figé à l'ajout resterait en FR après bascule AR.
- **Le nom FR d'une option reste une CLÉ** (leçon 7.4) : le panier stocke et
  renvoie la valeur canonique FR, l'affichage seul est traduit.
- **`aod_cod_lead`** (suivi prospects) reste mono-produit — assumé, écrit :
  le lead pointe le produit depuis lequel le panier a été soumis.
- **Le verrou « 1 commande / 24 h / téléphone » vaut PAR COMMANDE** — il n'est
  pas assoupli : le panier réduit précisément le besoin de commander deux fois.
- L'option nouvelle entre dans les DEUX `uninstall.php` ? Non — **un seul
  propriétaire** : `aod-cod-form` la supprime ; le dashboard ne fait que
  l'écrire (déclaration au cliquet `test-uninstall-residus.php`, motif
  `aod_seq_order_counter` en sens inverse).

### 35.1 — Le socle : option + carte gérant dans `/gestion` ✅

Option `aod_cod_cart` + helper de lecture côté COD (`AOD_COD_Form::cart_enabled()`).
Carte « Panier » dans la section Livraison, rendue **gérant uniquement**
(précédent 31.1), AJAX dédié `aod_cd_save_cart_mode` (nonce + `manage_woocommerce`),
déclaré dans la table du cliquet permissions avec sa raison. Uninstall : l'option
part avec `aod-cod-form`. DoD : cliquets verts, porte `confirmateur` verte (la
carte est invisible ET le POST forgé refusé en confirmateur), doc-dérive vert.

### 35.2 — Le serveur : `items[i][product_id]` multi-produits ✅

`handle_submit()` : résolution produit **par item** (repli `product_id` racine —
rétro-compat prouvée à l'octet : payload actuel → même commande qu'avant),
validations par produit (achetable, options obligatoires par produit), offres
par groupe de produit, sous-total = somme des groupes, livraison inchangée.
Métas `_aod_*` inchangées. DoD : porte `cod` verte inchangée (non-régression),
nouveau cliquet serveur (commande 2 produits forgée en test : lignes, prix,
métas — éprouvé au rouge sur un produit non publié glissé dans le payload).

### 35.3 — Le public : bouton Ajouter, panneau panier, soumission ✅

Toggle ON seulement : bouton « Ajouter au panier » (fiche produit), bouton
flottant avec compteur, panneau (liste, quantités, retirer, total estimé),
formulaire COD du panneau → `aod_cod_submit` multi-produits. `localStorage`
`aod_cart` (IDs + options canoniques + qty), noms affichés servis par le
serveur dans la langue courante. Assets chargés **uniquement si** toggle ON
(pattern `needs_js()` de la landing). RTL + mobile d'abord. DoD : toggle OFF =
zéro diff DOM (prouvé), FR/AR 390 px au navigateur.

### 35.4 — i18n + recette de phase : la porte `panier` ✅

`make-translations.sh` des deux plugins (AR manuel). Une **nouvelle porte de
recette `panier`** (déclarée dans SCENARIOS par cette étape — la citer en
`run.sh <x>` avant qu'elle existe ferait rougir doc-dérive R6, à raison) :
toggle OFF → aucun bouton, aucun asset panier ;
toggle ON → parcours réel en visiteur (2 produits semés → panier → commande
COD → relecture en base : 2 lignes, prix serveur, métas, purge) ; remise du
toggle à l'état capturé en `trap` (leçon D26 : la porte ne laisse pas le clone
dans son état de test). Checklist TEMPLATE.md §10 rejouée.

## Phase 36 — Panier COD v2 : l'achat au panier de bout en bout

**La demande (owner, 2026-07-23).** La Phase 35 a livré la MÉCANIQUE (option,
serveur multi-produits, panneau) ; l'owner veut maintenant le PARCOURS
d'achat complet, stylé et ajusté côté public :

1. **Ajouter depuis les grilles, sans changer de page.** Sur les cartes
   produit (Boutique, Accueil, produits liés), le survol fait apparaître un
   bouton semi-transparent « Ajouter au panier » posé sur l'image ; le clic
   ajoute l'article et la page ne bouge pas.
2. **Gérer le panier LÀ où on commande.** « Commander » depuis le panneau
   mène à la page du formulaire ; le bandeau « votre panier (N) » de la 35.3
   y devient un **vrai bloc de gestion** : lignes (image, titre, quantité
   +/−, retirer, total de ligne), total du panier, le tout au-dessus du
   formulaire — et le résumé Sous-total/Livraison/Total du formulaire suit
   le panier au lieu du seul produit de la page.
3. **Compléter le panier sans quitter la page.** Sous le formulaire, une
   sélection d'autres produits de la boutique, avec le même bouton d'ajout.

**Ce qui ne change PAS — le contrat de la Phase 35 tient intégralement :**
toggle OFF = pas un octet (assets, DOM, surface serveur) ; tunnel Woo mort
(`AOD_COD_No_Tunnel` entièrement armé) ; store `localStorage` inchangé
(`{ product_id, opt, qty }` — jamais nom ni prix) ; les prix affichés
viennent d'`estimate_lines()` (la formule exacte du serveur) ; la commande
part par le MÊME `aod_cod_submit` (anti-bot, prix serveur, une commande à
N lignes).

**Les pièges identifiés au cadrage (à traiter dans les stories) :**

- **Le survol n'existe pas au tactile.** Mobile d'abord : le bouton est
  TOUJOURS visible sous `@media (hover: none)` ; l'apparition au survol est
  la variante desktop (`hover: hover`), pas le comportement de base.
- **Un produit à variantes ne s'ajoute pas en devinant.** Depuis une grille,
  aucune option n'est choisie : son bouton MÈNE à la fiche (ancre du
  formulaire) au lieu d'ajouter des options vides en silence — le précédent
  35.2 (jamais de repli muet) vaut aussi pour l'UX.
- **Le markup des cartes appartient au THÈME.** Aucun hook de position
  fiable inter-thèmes sur l'image ; et un `<button>` imprimé DANS le `<a>`
  de la carte serait du HTML invalide. PHP n'imprime qu'un nœud de DONNÉES
  (`data-pid`, `data-hasopts`, via `woocommerce_after_shop_loop_item`) ;
  c'est le JS panier qui pose le bouton sur l'image (`preventDefault` pour
  ne pas suivre le lien de la carte).
- **Une seule peinture de panier.** Le bloc de gestion de la page formulaire
  réutilise le rendu du panneau (même endpoint `aod_cod_cart_items`, même
  code de peinture) — pas une seconde implémentation qui divergerait du
  drawer à la première retouche.
- **Les suggestions ne sont pas une 4ᵉ page ni un tunnel.** Une grille
  courte rendue par le serveur (produits publiés, produit courant exclu),
  visible toggle ON uniquement, chaque carte portant le même bouton d'ajout
  — et un lien vers la Boutique, jamais vers `/cart`.

### 36.1 — Le bouton d'ajout sur les cartes produit (grilles) ✅

Nœud de données PHP par carte (`AOD_COD_Cart`, hook
`woocommerce_after_shop_loop_item`, toggle ON seulement) ; le JS panier pose
le bouton en surimpression sur l'image (semi-transparent, survol en desktop,
permanent au tactile), clic = ajout sans navigation + retour visuel (badge +
état « Ajouté ✓ » bref) ; produit à variantes → lien vers la fiche + ancre.
Boutique, Accueil (`[products]`) et produits liés passent par la même boucle
Woo : un seul hook les couvre tous. RTL + `prefers-reduced-motion`.

> 🔁 **Le LIBELLÉ a changé le 2026-08-02, pas le comportement.** Il dépendait de
> `data-hasopts` — « Choisir les options » sur un produit à variantes, « Ajouter
> au panier » sinon. Sur un catalogue réel c'est le premier cas qui domine (les
> deux produits de démonstration ont une couleur) : **toutes** les cartes
> affichaient « Choisir les options », et une grille où aucune carte ne dit
> « Ajouter au panier » n'annonce plus qu'il existe un panier. Libellé unique
> depuis, **clic inchangé** : un produit à variantes mène toujours à la fiche,
> aucune couleur devinée. 🚨 *La porte ne pouvait pas le voir : ses deux témoins
> étaient des produits SIMPLES — la moitié du catalogue qu'un clone rencontre.*
> Elle sème un 3ᵉ témoin à variantes et garde les deux moitiés de la décision
> (même libellé **et** aucun ajout au clic) : garder la première seule, c'est
> autoriser la « correction » qui devine la variante. Éprouvée au rouge sur les
> deux. Cf. §C.3 ligne 36.1.

> 🔁 **Trois défauts du panneau soldés le 2026-08-02** (audit A→Z du panier, hors
> phase — aucune surface AJAX, aucune capability, aucun schéma nouveau) :
> un article refusé par le funnel **s'évaporait** du panneau en restant dans le
> store (badge « 1 » sur un panneau « vide », rien pour le retirer, commande
> refusée en bloc au nom d'un article invisible) ; « + » / « − » / « retirer »
> visaient le **produit** et non la ligne, donc agissaient sur la mauvaise
> variante ; et un « + » au-delà du stock **figeait** la ligne sans retour
> possible. Un seul mécanisme les ferme : l'identité d'une ligne devient sa
> **position dans le panier**. Détail et pièges : `ARCHITECTURE.md`, ligne
> `class-aod-cod-cart.php`.
> 🔁 **Et le 2026-08-02 aussi, le repère d'arrivée** : la carte d'un produit à
> variantes menait à `#aod-commander`, donc au FORMULAIRE, sans un mot — mesuré,
> le visiteur appuyait sur « Ajouter au panier », la page sautait à 3 567 px, le
> compteur ne bougeait pas et rien ne reliait les deux moments. Elle mène
> désormais à `#aod-options` (pastilles à 144 px du haut au lieu de 473) et la
> fiche y révèle une phrase qui nomme les deux gestes restants, effacée dès que
> le choix est complet. ⚠️ **C'est un pansement, et il est assumé comme tel** :
> le bouton de la grille continue de promettre un ajout qu'il ne fait pas. Le
> correctif entier reste le panneau de choix sur la carte — non fait.
> 🔁 **Puis, le même jour, le vrai correctif : le panneau de CHOIX.** Le repère
> expliquait sans régler — le bouton de la grille promettait toujours un ajout
> qu'il ne faisait pas. Un appui sur une carte à variantes ouvre désormais un
> panneau qui DEMANDE la variante : on choisit, ça s'ajoute, la page ne bouge
> pas. 🚨 **Son contenu vient du SERVEUR** (`aod_cod_cart_options`, lecture
> seule) : on sert le markup de la fiche au lieu d'en fabriquer un second, donc
> l'état de rupture (`disabled`), les noms localisés et les clés canoniques FR
> du matching arrivent *gratuitement* — mesuré, un visiteur arabophone qui
> clique « نبيذي » range bien « Bordeaux ». Le repère devient le chemin de
> **repli** (serveur qui refuse, réseau coupé) : le geste aboutit toujours
> quelque part. **D34 soldée au passage** (cf. §C.5).
> **D34, D35 et D36 sont soldées** (cf. §C.5) : plus aucune dette ouverte sur le
> panier COD.

### 36.2 — Le panier géré sur la page du formulaire ✅

Le bandeau « votre panier (N) » devient un bloc de gestion complet au-dessus
du formulaire : les lignes du panneau (peinture partagée, quantités, retrait,
totaux serveur), synchronisé par `aod:cart-change` dans les deux sens
(panneau ⇄ bloc). Le résumé du formulaire (Sous-total/Livraison/Total) suit
le panier quand il est non vide. Panier vidé sur place → le formulaire
retrouve son mode mono-produit d'avant, sans rechargement.

### 36.3 — Suggestions « D'autres produits » sur la page du formulaire ✅

Toggle ON seulement : sous le formulaire, une grille courte de produits
publiés (produit courant exclu), titres/prix servis dans la langue courante,
chaque carte avec le même bouton d'ajout que 36.1. Aucune nouvelle surface
AJAX : rendu serveur au chargement de la page.

### 36.4 — i18n + extension de la porte `panier` ✅

`make-translations.sh --strict` des deux plugins (AR manuel). La porte
`bash tools/recette/run.sh panier` s'étend au nouveau parcours : OFF = pas
un octet Y COMPRIS sur les grilles et les suggestions ; ON = ajout depuis la
Boutique sans navigation, bloc de gestion sur la page formulaire (quantité
modifiée + ligne retirée, totaux re-peints), suggestion cliquée → badge,
puis le parcours 35.4 existant (commande relue en base). Checklist
TEMPLATE.md §10 rejouée.

## Phase 37 — UI/UX du panier COD : lui donner sa propre peau

### 37.1 — Le panier ne porte plus la peau du thème ✅

Refonte **visuelle et structurelle** des quatre surfaces du panier (bouton
flottant + panneau, bloc de gestion, surimpression d'ajout, suggestions).
Épines UX : `_bmad-output/planning-artifacts/ux-designs/ux-panier-cod-2026-07-23/`
(`DESIGN.md` = identité visuelle, `EXPERIENCE.md` = comportement).

**Le défaut fondateur, mesuré au navigateur : contraste 1,05:1** sur « Ajouter
au panier » — le point d'entrée du panier sur la fiche produit était invisible.
Notre propre markup portait `class="button"` ; le CSS de boutons du thème
(Astra l'imprime en `<style>` inline, donc APRÈS nos feuilles) posait son fond
de marque par-dessus notre `background: transparent`, pendant que notre `color`
— seule à porter `!important` — tenait bon. Résultat : vert sur bleu.

Correctifs : plus aucune classe de thème sur notre markup, fonds **explicites**
(jamais `transparent`, qui fait dépendre le contraste de ce qu'il y a derrière),
états `:focus` déclarés à côté des `:hover` (le thème porte un
`button:focus{background}` en (0,1,1) qui bat une règle de composant en
(0,1,0)), jeu de tokens `--ac-*` aligné sur celui du formulaire, ligne de panier
ramenée de 5 colonnes à 2 (les noms ne sont plus tronqués à « Bas… », ce qui en
arabe rongeait le **début** d'un nom latin), bloc aligné sur la largeur du
formulaire. **Zéro nouvelle chaîne d'interface**, zéro changement serveur.

Cliquet `tests/unit/test-panier-peau.php` ; porte `bash tools/recette/run.sh
panier` étendue à la mesure du contraste.

### 37.2 — Le panier prend les icônes de `/gestion` ✅

Le bouton flottant portait un chemin SVG **recopié à la main** dans le PHP, et
les deux boutons de fermeture/retrait un glyphe texte `&times;`. Désormais : les
mêmes icônes **Phosphor** que la sidebar de `/gestion`, servies par un catalogue
fermé `AOD_COD_Icon` — attributs reconstruits au rendu, `currentColor`,
`aria-hidden` (les boutons portent déjà leur `aria-label`).

🚨 **Pourquoi un jumeau de `AOD_CD_Icon` et pas un appel.** La dépendance entre
les deux plugins ne va que dans un sens : `aod-client-dashboard` a besoin de
`aod-cod-form`, jamais l'inverse. Appeler la classe du dashboard depuis le public
ferait tomber la boutique en **erreur fatale** dès qu'un gérant désactive
`/gestion` — le mode dégradé fermé par la Phase 32.1, repris à l'envers. La
contrepartie est la duplication de 3 fichiers SVG, **gardée octet à octet** par
R6 de `test-panier-peau.php`.

### 37.3 — Le formulaire COD reprend le vert du plugin ✅

Arbitrage Q2 tranché (délégation de l'owner) : « Confirmer la commande » —
formulaire de commande **et** formulaire de contact — ne porte plus
`class="button"` et prend le vert `--sh-accent` que son CSS déclarait déjà.

**Ce qui a emporté la décision, c'est le contraste, pas l'esthétique.** La
couleur primaire d'un thème est arbitraire : Astra donne 5,20:1 avec du blanc,
mais un clone à primaire clair rendrait du blanc sur clair — illisible, sur le
bouton le plus important du tunnel d'achat. Toute la Phase 37 a établi qu'aucun
fond actionnable ne dépend du thème ; l'exception aurait contredit le principe
là où il compte le plus.

🚨 **L'arbitrage a été REFORMULÉ en cours de route.** La règle générale
pressentie — « aucune classe de thème sur le markup public » — était **fausse** :
`.aod-home__btn` et les CTA de page de vente **délèguent** leur apparence au
thème **à dessein**, raison écrite dans `aod-cod-pages.css`. La leur retirer les
aurait laissés **nus**. Règle retenue : **s'habiller soi-même OU déléguer, jamais
les deux**, toute délégation déclarée avec sa raison (R7).

⚠️ Piège évité de justesse : la nouvelle base scopée `.aod-cod .aod-cod__submit`
(0,3,0) **battait** la surcharge `prefers-reduced-motion` restée en (0,2,0) — une
media query n'ajoute aucune spécificité. Le creux d'appui aurait survécu au
mouvement réduit, visible seulement de qui a activé le réglage. Surcharge
réalignée ; porte `mouvement` verte.

## Phase 38 — UI/UX de la fiche produit : marges, variantes, forme du formulaire

Suite de la Phase 37, côté **fiche produit** cette fois — pas le panier, le
**formulaire COD** lui-même et son contenant. Demande de l'owner : « trop
d'espace sur les côtés, l'affichage des variantes n'est pas stylé, améliore la
forme du formulaire. » Diagnostic **mesuré au navigateur** avant toute
correction (produit témoin semé puis purgé) — le mot « marge » recouvrait deux
zones distinctes qu'on ne corrige pas au même endroit.

### 38.1 — Désempiler le formulaire : le SOLO ne s'encadre plus ✅

Les pastilles de variantes vivaient au fond de **quatre cadres emboîtés** :
carte d'offre → panneau → bloc « Article » → section d'options. Or, sur un
produit **sans offre configurée** (le cas par défaut d'un clone neuf), il n'y a
**aucun choix d'offre à présenter** : on rendait pourtant toute la mécanique du
choix — carte encadrée, liseré de couleur censé la distinguer de voisines
inexistantes, coche verte indécochable — et, à l'intérieur, un bloc « Article »
qui n'affiche même pas son titre quand il est seul.

**Mesuré sur 390 px : cet empilement mangeait 67 px de CHAQUE côté** (34 % de
l'écran) pour encadrer une décision qui n'existe pas. Les pastilles tombaient à
258 px. Après désempilement : **316 px** (+22 %), et l'écran est passé de 34 %
de décor à zéro.

🚨 **On retire la PEAU, jamais les NŒUDS.** `aod-cod-form.js` lit
`data-qty`/`data-price` sur la carte d'offre et **itère `.aod-cod__unit`** pour
composer une sélection de variantes par article. Supprimer ces éléments
casserait le calcul du prix. Les nœuds restent, marqués `--solo`, et ne perdent
que leur bordure/fond/coche. Le contrat DOM est **inchangé** — les chemins
multi-offres et multi-articles (2-3 produits) rendent exactement comme avant,
vérifié au navigateur. La ligne de prix « 1 produit » survit, dépouillée de son
habit de carte : une **page de vente n'affiche aucun prix hors du formulaire**
(`class-aod-cod-landing.php` n'appelle jamais `wc_price()`), la retirer priverait
les landings de leur ancre de prix.

⚠️ **Piège de cascade, interne à notre feuille.** `.aod-cod__unit--solo`
(0,1,0) et `.aod-cod__unit` (0,1,0) sont à égalité : c'est l'ORDRE du fichier
qui tranche. Placée à sa place logique (avec le bloc SOLO, plus haut), la règle
perdait et le cadre gardait sa marge — **mesuré, pas relu**. Déplacée juste
après `.aod-cod__unit`. On ne compense pas en montant la spécificité, ça
déplacerait le piège d'un cran.

### 38.2 — Les variantes en grille, cibles tactiles, écho de sélection ✅

Les pastilles étaient un `flex-wrap` : six pointures se tassaient à gauche, la
septième partait seule à la ligne — un ruban irrégulier qui se lit comme un
défaut d'affichage. Passées en **grille à colonnes égales** (`auto-fill` +
`minmax`), avec trois mailles selon le contenu (pointures / couleurs à pastille
/ vignettes photo). Cible tactile portée de 32 à **44 px** (le template est
mobile-first). État choisi : anneau **intérieur** (un `box-shadow` extérieur
débordait sur la cellule voisine en grille serrée) + fond `--sh-accent-wash`.

**Écho de la valeur choisie** à côté du libellé (« Pointure · 41 »). 🚨 Il lit
le texte **affiché** de la pastille, jamais son `data-name` — ce dernier porte
le nom canonique **français**, clé de matching serveur (`match_option()`) ;
l'afficher rendrait « Noir » à qui vient de cliquer « أسود ». Effet de bord
voulu : **zéro chaîne traduisible ajoutée**, l'écho est juste dans les deux
langues sans une ligne de `.po`.

⚠️ **Second piège de cascade.** `.aod-cod__field:not(.aod-cod__float) > label`
pèse (0,2,1) — `:not()` ajoute la spécificité de son contenu. Un
`.aod-cod__optlabel` (0,1,0) perdait : le libellé restait `display:block`, l'écho
se collait à l'astérisque (« Pointure \*41 »). Égalisé à `label.aod-cod__optlabel`
> parent. La leçon de la Phase 37 vaut aussi **contre ses propres règles**, pas
seulement contre le thème. Nouveau token appairé `--sh-accent-wash` ⇄
`--ac-accent-wash` (une couleur sans paire est la dérive que R2 garde).

### 38.3 — Trois emojis de chrome chassés du côté acheteur ✅

Le formulaire portait 🏠 / 🏢 sur le choix du mode de livraison et 🔒 sur la
réassurance. Un emoji est **dessiné par l'OS** : trois acheteurs sur trois
systèmes ne voyaient pas le même formulaire, et aucun ne voyait l'icône
dessinée. Remplacés par le catalogue fermé `AOD_COD_Icon` (icônes `house`,
`storefront`, `lock` ajoutées, copiées **verbatim** de `/gestion`, gardées octet
à octet par R6). L'icône de livraison suit l'état choisi par la **couleur**
(rendu `regular`, pas `fill` — d'où `filled()` inchangé et aucun `fill` mort au
catalogue).

🚨 **Le cliquet anti-emoji regardait moins large que son titre.**
`test-icones.php` s'annonce « plus aucun emoji de chrome » et n'a JAMAIS balayé
que `/gestion`. Le plugin **public** — celui que l'acheteur voit — en portait.
Le balayage est étendu **au fichier nettoyé** (`class-aod-cod-form.php`), avec un
**témoin** qui prouve que le chemin public a bien résolu (un glob vide passerait
vert sans rien regarder — le défaut de la Phase 27).

⚠️ **Périmètre assumé.** Le reste du plugin public porte encore des emojis de
chrome **hors du tunnel de commande** — l'Accueil (💵 🚚 📞) et le Contact
(📍 ✉️). Même défaut, mais les corriger, c'est de nouvelles icônes et deux
autres pages : un chantier à part, pas « la fiche produit ». Consigné en mémoire
plutôt que masqué ou avalé en débordement de périmètre.

**Hors plugin, sur CE clone :** largeur de contenu Astra portée de 1200 à
**1600 px** (relevée de 1400 à 1600 le 2026-07-24 : sur un moniteur 2560 px, 1400
laissait ~580 px de vide de chaque côté ; à 1600, ~480 — et à 1920, la marge
tombe de 340 à 160, quasi 0 sur un laptop 1440). ⚠️ **Piège corrigé en cours de
route** : Astra ne lit PAS cette largeur dans les theme mods mais dans l'option
dédiée `astra-settings` — un premier `set_theme_mod("site-content-width", …)`
s'est écrit dans le vide (le conteneur est resté à 1200, mesuré). Bonne manip :
injecter la clé dans `astra-settings` puis purger le cache CSS d'Astra. ✅ **Ce
défaut (1600) est désormais posé AUTOMATIQUEMENT par `setup.sh` (étape 5bis)** pour
tout clone Astra — une option en base ne partant pas avec `git clone`, on la bake
dans le setup (idempotent, non destructif, inerte sous un thème non-Astra) : le
correctif ne se limite plus à CE clone, il descend dans les clones à venir. Reste
**overridable** par l'owner (défaut générique, comme DZD/COD). Noté dans
`CHECKLIST-THEME-CHILD.md` (C9). 🚨 **Élargir le conteneur
ne suffit pas pour la fiche produit** : le formulaire (`.aod-cod`, plafonné à 560,
centré `margin:auto`) se met à FLOTTER dans la colonne « résumé » étirée, décalé
sous le titre. Correctif **dans le template** (tous clones) : `aod-cod-form.css`
ancre le formulaire au DÉBUT de sa colonne et le laisse remplir jusqu'à 640 px dès
≥ 922 px (2 colonnes Astra), en logique `margin-inline` → **correct en RTL** sans
règle miroir ; l'empilement 601-921 px garde son centrage voulu. Cliquet
`test-panier-peau.php` (R2 étendu),
`test-icones.php` (périmètre + témoin), `test-phpcs-ignore.php` (census +3, 138-140
déclarées). Portes `panier`, `public`, `rtl`, `mouvement` vertes ; 998 tests.

---

## Phase 39 — Convertir une commande abandonnée en commande (depuis `/gestion`)

*Planifiée le 2026-07-24 (cf. verdict A.16). **Indépendante** des autres phases. Donne à
la section « Commandes abandonnées » (Phase 11) le maillon qui lui manque : transformer
un prospect `pending` en vraie commande WooCommerce, sans quitter `/gestion`.*

**Versions cibles** : `aod-client-dashboard` v0.39.0+ · `aod-cod-form` **non touché** (le
back-end leads et `mark_converted()` — déjà `public static` — suffisent ; sauf si la
recette révèle un manque).

**Le fil rouge de la phase** : **on ne crée pas un 3ᵉ constructeur de commande.** Le
bouton fabrique un **squelette**, le lead passe `converted`, et la **modale d'édition
existante** (`AOD_CD_Section_Orders`) fait tout le reste. Le statut « Confirmée » emprunte
la **chaîne d'envoi transporteur existante** ; la bascule Suivi ne survient que si le
colis est **réellement** créé.

**Quatre invariants, valables à chaque étape :**

- **Un lead `converted` n'est jamais un abandon.** `mark_converted()` fait foi ; à
  conversion réussie, le lead quitte la liste `pending` et le bouton disparaît.
- **Zéro duplication.** Toute écriture de champs de commande passe par le chemin
  d'édition existant (`aod_cd_order_save_info`) et tout changement de statut par
  `aod_cd_order_status` — jamais une seconde logique de prix/adresse/envoi.
- **Créer = agir sur une commande.** Bouton **et** AJAX gardés par
  `aod_cap_confirm_orders` **en plus** de `aod_cap_abandoned`, périmètre produit filtré
  **côté SQL** (jamais un simple masquage d'UI).
- **Rien ne se perd.** Envoi transporteur échoué = pas de tracking = la commande **reste
  dans Commandes** (elle n'entre dans Suivi que tracking posé).

### 39.1 — Bouton « Commander » + création du squelette ✅
- Colonne **Actions** de `class-aod-cd-section-abandoned.php` : 3ᵉ bouton **« Commander »**
  à côté d'Appeler/WhatsApp, rendu **uniquement** si le lead a un téléphone **et** si
  l'utilisateur possède `aod_cap_confirm_orders` (masqué, pas seulement désactivé).
- **Nouvel AJAX** (nom fixé en story) : nonce `aod_cd`, garde
  `AOD_CD_Roles::user_can('aod_cap_confirm_orders')` + périmètre
  (`product_id ∈ assigned_products()`, patron `scoped_orders()`). Crée une commande
  **minimale** (produit racine du lead, qté, nom, téléphone, wilaya/commune si présents),
  statut initial **neutre qui NE déclenche PAS l'envoi** (p.ex. `pending`), meta
  `_aod_source='aod-abandoned'`, puis `mark_converted($token, $order_id)`.
- **Anti-doublon** : refuser si le lead est déjà `converted` — **relecture sur instance
  fraîche après verrou** (Phase 31.4).
- **Recette** : un lead `pending` → clic « Commander » → il **disparaît** de la liste ;
  la vraie commande existe en base (relue) ; un confirmateur sans `aod_cap_confirm_orders`
  **ne voit pas** le bouton **et** l'AJAX répond 403 (URL forgée).

### 39.2 — Compléter dans la modale d'édition + sélecteur de statut ✅
- Après création, ouvrir la modale d'édition **existante** sur la nouvelle commande
  (réutilise `render_order_detail` / `aod_cd_order_save_info`) : le confirmateur saisit
  adresse, commune exacte, variante, total au téléphone.
- **Sélecteur de statut dans le flux** : la modale montre aujourd'hui le statut en
  lecture seule (il se change depuis le widget de la liste). Exposer le choix du statut
  dans ce flux en **réutilisant** `aod_cd_order_status` (même garde, même relecture du
  résultat d'envoi) — pas un 2ᵉ chemin.
- « Confirmée » → `auto_send()` (si l'expédition auto est activée) → colis créé ⇒
  `_aod_ship_tracking` posé ⇒ **bascule Suivi + sortie de Commandes** ; échec ⇒
  `_aod_ship_error`, **reste dans Commandes**. Le retour AJAX reflète
  `sent` / `failed` / `disabled` comme la section Commandes.
- **Recette** : conversion → modale → compléter → « Confirmée ». Sur transporteur
  **bouchonné** en succès → apparaît dans Suivi et sort de Commandes ; bouchonné en
  échec → reste dans Commandes avec l'erreur. *(S'éprouve sur transporteur stubé, jamais
  une vraie API — comme la Phase 32.4.)*

### 39.3 — i18n + recette + cliquet de la phase ✅
- `make-translations.sh --strict` vert (chrome : « Commander », libellés du sélecteur de
  statut du flux) + AR **manuel**.
- Porte de recette : étendre `run.sh abandonnees` (le parcours de conversion) et vérifier
  la bascule via `run.sh suivi`. **Nouveau cliquet unitaire** (nommé en story) : garde de
  permission (bouton **et** AJAX exigent `aod_cap_confirm_orders`), anti-doublon (un lead
  déjà converti ne recrée rien), non-duplication (la conversion écrit **via** le chemin
  d'édition partagé). MAJ `docs/ARCHITECTURE.md` (nouvel AJAX, nouvelle capability croisée
  sur la section abandonnées).

**Livré** (v0.53.0) — le détail est en §C.3 (ligne 39.3). Choix arbitré à
l'implémentation : la bascule Confirmée→Suivi reste gardée par la porte `suivi`
existante (`commandes-suivi.js`, Phase 12) + le témoin serveur de 39.2 ; la porte
`abandonnees` prouve le parcours **conversion → modale → statut choisissable** et
n'a PAS re-stubé le transporteur (éviter de réécrire un contrat déjà vert).

### 39.4 — le téléphone seul suffit à convertir (produit optionnel) ✅

*Phase 39 ROUVERTE (3/3 → 4/4). **39.1 a posé un prérequis trop strict**, révélé à
l'usage : le bouton « Commander » n'apparaît QUE si le prospect a un `product_id > 0`,
et `ajax_convert_lead()` refuse (« Le produit de ce prospect n'existe plus. », 404)
si ce produit **a été supprimé**. Or le confirmateur **appelle** le client et lui
demande ce qu'il veut : le seul prérequis utile est **un numéro de téléphone** —
avec lui, on peut créer la commande et **composer les articles au téléphone** dans
la modale d'édition (39.2). Le produit d'origine (présent, absent, ou supprimé)
n'est qu'une commodité, jamais une condition.*

- **Règle cible** : *avec un téléphone, on peut convertir un prospect* — point. Le
  produit est **facultatif**.
- **Cause** : (a) le bouton `render_row()` est conditionné à `product_id > 0` ;
  (b) `ajax_convert_lead()` refuse si `wc_get_product()` rend `false` ; (c)
  `build_skeleton_order()` construit la commande **autour du produit**
  (`$order->add_product`). Le produit ne sert **qu'à** la ligne d'article — tout le
  reste (nom, téléphone, wilaya, commune, `_aod_source`) vient du lead.
- **Correctif** :
  - **Bouton** : afficher « Commander » dès qu'il y a un **téléphone** (il est déjà
    dans le bloc `if ('' !== $phone)`), sans exiger `product_id > 0`.
  - **AJAX** : exiger le **téléphone** (sans lui, pas de commande COD possible) ;
    **retirer** le refus « produit n'existe plus » — un produit absent/supprimé
    devient un cas **légitime**.
  - **Squelette** : `build_skeleton_order()` accepte un `$product` **nullable** —
    présent → `add_product` comme aujourd'hui ; absent → **aucune ligne d'article**,
    la note de commande le signale (« à composer au téléphone »). Le confirmateur
    ajoute les articles dans la modale (`aod_cd_order_save_info`, déjà en place).
- **Périmètre inchangé** : nonce + `aod_cap_confirm_orders`, anti-doublon (verrou +
  relecture fraîche), statut `pending`, `_aod_source='aod-abandoned'`. **Ni 2ᵉ
  constructeur, ni 2ᵉ chemin** : MÊME méthode, prérequis assoupli.
  ⚠️ **Le périmètre produit du confirmateur** (`assigned_products()`) se juge
  toujours sur le `product_id` **du lead** : un **gérant** (périmètre vide) convertit
  tout prospect à téléphone ; un **confirmateur restreint** à certains produits reste
  refusé si le `product_id` du lead (0 compris) n'est pas dans son périmètre — il ne
  peut pas s'attribuer un prospect sans produit connu. Décision assumée, à déclarer.
- **Preuve** : cliquet `test-conversion-abandon.php` étendu — `build_skeleton_order`
  avec `$product = null` rend une commande `pending`, `_aod_source='aod-abandoned'`,
  **zéro ligne d'article**, aucun tracking (le cas « produit présent » reste
  couvert) ; le bouton n'exige plus `product_id > 0` (source). Porte
  `run.sh abandonnees` : un prospect **sans produit** (ou produit supprimé) se
  convertit (modale ouverte, prête à composer). MAJ docs (ROADMAP §C.3 39.4,
  ARCHITECTURE, TEMPLATE « Phase 39 soldée (4/4) »).

### 39.5 — la modale d'une commande VIDE doit pouvoir la remplir ✅

*Phase 39 ROUVERTE (4/4 → 5/5). **La 39.4 a créé un trou en aval**, remonté à
l'usage : une commande abandonnée convertie **sans produit** (39.4) ouvre la modale
d'édition… mais celle-ci ne montre **ni « Ajouter un article », ni le choix de
variante, ni le champ Total** quand la commande est vide. Le confirmateur peut donc
créer la commande mais **pas la remplir** — exactement ce que le client dicte au
téléphone. Incohérent.*

- **Cause (mesurée)** : dans `render_order_edit_form()`
  (`class-aod-cd-section-orders.php`), **tout** le bloc Articles — les lignes
  existantes (variantes + qté + « Retirer »), le sélecteur **« Ajouter un article »**,
  la note, **et le champ Total** — est enfermé dans un `if ( $items )`. Une commande
  à **zéro article** (le cas 39.4) saute tout ce bloc : la modale n'affiche que
  Nom / Téléphone / Livraison / Adresse / Wilaya / Commune.
- **Correctif (PHP-only, UI)** : sortir du `if ( $items )` **le conteneur
  `.aod-cd-od-items`** (rendu même vide, pour que le JS y ajoute des lignes), le
  bloc **« Ajouter un article »** (conditionné au **catalogue**, pas aux items) et
  le **champ Total**. Les lignes existantes ne s'affichent que s'il y en a
  (boucle `foreach` inchangée). **Rien côté serveur ni JS** :
  `ajax_order_save_info` gère déjà `new_product` + `total_override`, et le JS du
  formulaire (`section-orders.js`) cherche `.aod-cd-od-additem-btn` /
  `.aod-cd-od-items` / `.aod-cd-od-total` et **no-op** s'ils manquent → il
  fonctionne dès qu'ils sont présents.
- **Périmètre** : la modale est **partagée** avec la section Commandes (39.2 l'a
  voulu ainsi). Le changement n'affecte QUE les commandes à **zéro article** (en
  pratique : une conversion 39.4 sans produit, ou une commande dont on a retiré
  tous les articles) — pour une commande **avec** articles, le rendu est
  **identique** (le bloc s'affichait déjà). Aucune régression sur les commandes
  normales. **Aucun 2ᵉ chemin, aucune capability neuve.**
- **Choix de variante** : il est rendu **par article** (les `select` d'options de
  chaque ligne). Sur une commande vide, il n'y a rien à varier tant qu'on n'a pas
  **ajouté** un article — d'où l'importance de « Ajouter un article » : le
  `buildNewItemRow()` du JS crée la ligne **avec ses `select` de variantes**. Le
  besoin est donc couvert par l'ajout, sans code neuf.
- **Preuve** : porte `run.sh abandonnees` étendue — après conversion d'un prospect
  **sans produit** (fixture 39.4 `ProduitSupprimeRecette`), ouvrir « Modifier »
  dans la modale et vérifier la **présence** du sélecteur « Ajouter un article »
  (`.aod-cd-od-addpick`), du conteneur `.aod-cd-od-items`, et du champ Total
  (`.aod-cd-od-total`) — aujourd'hui **absents**. (Idéalement : ajouter un article
  via l'UI → enregistrer → relire la commande = 1 ligne + total.) MAJ docs
  (ROADMAP §C.3 39.5, ARCHITECTURE, TEMPLATE « Phase 39 soldée (5/5) »).

### 39.6 — sélection groupée + suppression dans « Commandes abandonnées » ✅

*Phase 39 ROUVERTE (5/5 → 6/6). Demande owner : pouvoir **cocher un prospect (ou
tous)** dans « Commandes abandonnées » et les **supprimer**, exactement comme la
page Commandes le permet pour les commandes. Aujourd'hui la section est en lecture
+ actions par ligne (Appeler / WhatsApp / Commander) — aucune suppression.*

- **Réutiliser le patron EXISTANT de la page Commandes** (`AOD_CD_Section_Orders`),
  pas en inventer un : barre d'action `.aod-cd-bulkbar` (masquée, compteur +
  bouton Supprimer `trash`), colonne case à cocher avec **« tout sélectionner »**
  en tête, case par ligne, binder JS qui synchronise compteur + `indeterminate` et
  POST une liste d'IDs. Mêmes libellés (déjà traduits) autant que possible.
- **Capability — GÉRANT uniquement** (`manage_woocommerce`), famille
  `action-forte`. C'est le choix de la page Commandes (`delete_orders` exige
  `delete_shop_orders`/`manage_woocommerce`, **jamais** la cap du confirmateur) :
  supprimer est **plus fort** que consulter/rappeler (leçon 31.1 — une permission
  cohérente n'est pas une permission bornée). Un confirmateur avec `aod_cap_abandoned`
  voit et rappelle les prospects, mais ne les **purge** pas. La case à cocher, la
  barre et le handler sont **absents** pour lui (masqués ET refusés serveur).
- **Cible de suppression** : une ligne de la table custom `{prefix}aod_cod_leads`
  (par `id`). Nouvel AJAX `aod_cd_delete_leads` : nonce `aod_cd` + `manage_woocommerce`,
  `DELETE` préparé sur les IDs reçus. **Ne touche PAS** aux commandes déjà créées
  par une conversion (39.1) — un lead `converted` a son `order_id`, mais supprimer
  le **lead** ne supprime pas la **commande** (deux objets distincts ; le lead
  converti n'apparaît de toute façon plus dans la liste).
- **Périmètre serveur** : gérant only → pas de filtre `assigned_products()` (le
  gérant voit tout). Classes CSS **distinctes** de la page Commandes
  (`.aod-cd-leadcheck` / `.aod-cd-leadcheck-all` / `#aod-cd-lead-bulk`) pour que le
  binder Orders (qui tourne aussi mais ne trouve pas ses `.aod-cd-ordercheck`) et
  le binder Abandonnées ne se marchent pas dessus.
- **Registre JS (Phase 33)** : nouveau binder `bindAbandonedBulkDelete` dans
  `section-abandoned.js` (`register()`) + ajouté à `ORDER` (coque `dashboard.js`,
  +1 ligne). ⚠️ R1 (`test-decomposition-js.php`) est **pile à 635/635** → plafond à
  relever **635 → 636**, assumé et écrit (croissance COQUE légitime : un nom de
  binder de section, précédent `bindAbandonedConvert` en 39.1). Aucune logique de
  section ne remonte dans la coque.
- **Preuve** : nouvel AJAX → `test-permissions-sections.php` gagne l'entrée
  `delete_leads` (`manage_woocommerce`, `action-forte`, avec sa raison). Porte
  `run.sh abandonnees` : « tout sélectionner » coche la sélection, la barre affiche
  le compte, Supprimer retire les lignes et le(s) lead(s) **disparaît(ssent) en
  base** (relu). i18n `--strict` vert (chaînes neuves AR : confirmation,
  « Sélectionner ce prospect »). MAJ docs (ROADMAP §C.3 39.6, ARCHITECTURE row
  `abandoned`, TEMPLATE « Phase 39 soldée (6/6) »).

---

## Phase 40 — Stock par variante

*Planifiée le 2026-07-25 (cf. verdict A.17). **Indépendante** des autres phases. Donne à
chaque **valeur d'option** (`_aod_options`) son propre stock ; le stock produit devient la
**somme** de ses variantes, une variante à 0 s'affiche **en rupture**, et le stock baisse
**à la confirmation** de la commande (jamais à la création).*

**Versions cibles** : `aod-client-dashboard` v0.57.0+ · `aod-cod-form` v1.47.0+ (rendu +
garde serveur de la rupture).

**Le fil rouge de la phase** : **on ne réinvente rien.** Le champ `stock` vit dans la
valeur d'option (même invariant structurel que la traduction 7.4) ; le décrément est le
**mirroir exact** de `reduce_pack_components_stock()` ; la rupture est rejetée dans le
funnel **unique** `build_order_lines()`. Comportement **constant** pour tout produit sans
stock de variante.

**Quatre invariants, valables à chaque étape :**

- **`''` ≠ `0`.** Non géré (illimité, défaut) ne se confond JAMAIS avec rupture (géré à 0).
- **Un seul axe à stock (D1bis), côté serveur.** La normalisation force les autres axes à
  `''` ; l'UI n'est qu'un confort.
- **La rupture est une garde, pas un style.** Rejet serveur dans `build_order_lines()` ;
  le grisage est le confort. Éprouvée **au rouge**.
- **Le stock produit est CALCULÉ, jamais écrit.** La somme se relit ; aucun miroir dans le
  `_stock` Woo (leçon Phase 35).

### 40.1 — Modèle `stock` dans `_aod_options` + règle mono-axe ✅
- Champ `stock` parsé/normalisé dans `AOD_CD_Section_Products` (`''` non géré ≠ `0`
  rupture, négatif → `0`, non-numérique → `''`), exposé par les deux `get_product_options()`
  (public + éditeur). Règle mono-axe imposée à la normalisation (**côté serveur**).
- **Cliquet** `test-variant-stock.php` (groupes PARSE, NORMALISE, MONO-AXE, LECTURE) ;
  mono-axe éprouvée **au rouge**. Repli d'un produit d'avant 40.1 (clé absente → `''`).

### 40.2 — Saisie du stock par variante (Produits) ✅
- Champ `opt_value_stock[si][vi]` rendu dans `render_option_value()` (jamais `value="0"`
  pour un stock non géré) + garde-fou UI mono-axe (`refreshStockGuard()` dans
  `section-products.js`). Correctif de grille au passage (bug préexistant depuis 7.4 :
  `name_ar` écrasait le champ prix).

### 40.3 — Vue Stock : somme + détail par variante ✅
- `variant_stock_summary()` : somme des valeurs gérées + détail. La page Stock affiche la
  **somme**, le détail « Variantes : Rouge → 12 · Bleu → 0 », et passe la ligne en
  **lecture seule** (« géré par variante ») ; `stock_pill` cohérent (jamais « — » là où
  Stock dit « rupture » — pas de divergence Produits ⇄ Stock). `ajax_save_stock()` refuse
  (400) un produit géré à la variante.

### 40.4 — Rupture publique (grisée) + garde serveur ✅
- Côté acheteur (`option_sections_html`, pastilles ET liste déroulante) : valeur gérée ≤ 0
  → grisée + `disabled` + « Rupture de stock » (i18n). **Garde SERVEUR** dans le funnel
  unique `build_order_lines()` : une sélection forgée d'une variante en rupture ne crée
  **aucune** ligne. Éprouvée **au rouge** (sans la garde, la ligne passe). Nom canonique FR
  intact (clé de matching).

### 40.5 — Décrément à la confirmation + ré-incrément à l'annulation ✅
- Mirroir du pack : `reduce_variant_stock()` sur `woocommerce_order_status_aod-confirmed`
  (+ `completed` défensif), `restore_variant_stock()` sur `cancelled`/`refunded`, helper
  unique `apply_variant_stock_delta($order,$sign)` (décrément borné à 0, ré-incrément sans
  borne). Idempotence **bidirectionnelle** par le drapeau `_aod_variant_stock_reduced` sur
  la commande. **2 red-proofs** (double décrément ; variante non gérée mutée). Vérifié via
  le **vrai hook de statut** (`wp eval`).

### 40.6 — Clôture : porte de recette, cliquet, inscription doc ✅
- **Porte `bash tools/recette/run.sh variante`** (seed `recette-seed-variante.php`) :
  rupture grisée (public), somme + lecture seule (Stock), décrément/ré-incrément reflétés
  dans la somme (confirmation → 7, annulation → 10), repli. Éprouvée **au rouge**. Cliquet
  `test-variant-stock.php` (groupe DÉCRÉMENT, vraies `WC_Order`). i18n FR/AR complet.
  Inscription ROADMAP (A.17 + cette phase + §C.3) / TEMPLATE / ARCHITECTURE, `test-doc-derive`
  vert.

---

## Phase 41 — Couleurs de marque par régie (Pixels & Tracking)

*Planifiée le 2026-07-25 (cf. verdict A.18). **Indépendante** des autres phases. Donne à chaque
**carte de régie** de `Pixels & Tracking` sa **couleur de marque** (Snapchat jaune, Meta bleu,
TikTok noir, Google rouge), l'encre restant **AA par construction**. Purement présentationnel —
comportement constant.*

**Versions cibles** : `aod-client-dashboard` v0.61.0+ (CSS `dashboard.css` + rendu de
`AOD_CD_Section_Marketing`).

**Le fil rouge** : **on ne réinvente ni la couleur ni l'encre.** La couleur de marque est un
**catalogue fermé** (jumeau des tons `t-*`), l'encre lisible vient de `best_ink()` (déjà prouvée
AA sur tout accent), et le vert « Actif » reste le vert sémantique. Aucune surface serveur,
aucune chaîne i18n, rien côté acheteur.

**Trois invariants :**

- **Marque ≠ accent client.** Couleurs universelles de régies tierces, en dur, sans lien avec le
  Personnalisateur (Règle d'or n°1 sauve — cf. A.18, ICONES §6.4).
- **AA par construction.** Toute encre sur marque = `best_ink()` ; les champs restent sur fond
  blanc.
- **Marque ≠ statut.** `is-on` (vert « Actif ») reste sémantique et distinct de la couleur de
  régie.

### 41.1 — Couleurs de marque sur les cartes de régie ✅
- Catalogue fermé {`meta`→bleu, `tiktok`→noir, `snapchat`→jaune, `google`→rouge} porté par une
  classe modificatrice par carte (`aod-cd-pxcard--<brand>`) dans `AOD_CD_Section_Marketing`, +
  CSS dans `dashboard.css` : icône **pleine** (encre `best_ink()`), **liseré de marque** en
  **pseudo-élément** `inset-inline-start` (RTL-safe ; PAS une bordure — `is-on` repeint
  `border-color` et l'effacerait), teinte d'en-tête douce. Corps du formulaire sur fond blanc
  **inchangé**. Les 3 cartes CAPI héritent de la marque de leur plateforme (TikTok/Snapchat CAPI
  quittent `t-slate`). « Devise de reporting » reste neutre. Le vert `is-on` reste sémantique.
- 🚨 **best_ink() renvoie NOIR sur Meta, Snapchat ET Google, blanc sur TikTok.** Le réflexe
  « marque = blanc » échoue AA (blanc sur le rouge Google = 3,92, sur le bleu Meta = 4,23,
  sous 4,5). La carte reste franchement colorée (fond de pastille + liseré + en-tête) ; c'est le
  glyphe qui prend l'encre AA. Vérifiée au navigateur en gérant, FR **et** AR/RTL (0 erreur).

### 41.2 — Clôture : contraste AA, doc ✅
- Extension de `test-contraste.php` qui lit le **vrai** `dashboard.css` (pas une copie) et prouve
  par marque : (1) l'encre déclarée en CSS = `best_ink()` de la marque, (2) le couple marque/encre
  franchit AA (≥ 4,5). **Red-proof** : encre Meta forcée à blanc → les deux assertions rougissent.
  Les 6 couleurs de marque déclarées **avec leur raison** dans `test-charte-surfaces.php` (elles
  ne rétrécissent pas : marques universelles, pas de la dette sémantique). Portes `contraste` et
  `rtl` vertes, 1100 tests. Inscription TEMPLATE / ARCHITECTURE, `test-doc-derive` vert.

---

## Phase 42 — Assignation des commandes aux confirmateurs

*Planifiée le 2026-07-26 (cf. verdict A.19, demande owner). **Dépend de la Phase 1**
(rôle confirmateur + `assigned_products()`) et **touche la Phase 11/39** (conversion d'un
abandon crée une commande par un chemin distinct du formulaire public). Donne à chaque
commande un **confirmateur assigné** : répartition équitable **à la création**, cumulée
avec le périmètre produit existant, et pouvoir d'assignation/réassignation **gérant
uniquement** à tout moment.*

**Versions cibles** : `aod-client-dashboard` v0.62.0+ · `aod-cod-form` **non touché**
(l'assignation vit entièrement côté `/gestion` — le hook `aod_cod_order_created` est
consommé, jamais modifié).

**Le fil rouge de la phase** : **l'assignation est un ROUTAGE, pas un verrou.** Elle décide
qui *voit* une commande, jamais ce qu'elle contient ni son statut. Elle se cumule au
périmètre produit existant (D1) au lieu de le remplacer — deux notions orthogonales
(« quels produits » / « quelle commande précise ») qui répondent à deux questions
différentes.

**Quatre invariants, valables à chaque étape :**

- **Cumul, jamais substitution.** Une commande assignée hors du périmètre produit d'un
  confirmateur reste invisible pour lui — le tourniquet (42.1) ne doit donc choisir que
  parmi les confirmateurs déjà éligibles à cette commande par le produit.
- **Deux chemins de création, deux règles d'assignation.** Formulaire public → charge la
  plus faible parmi le vivier éligible (42.1). Conversion d'un abandon (Phase 39) → assignée
  au confirmateur qui convertit, jamais au tourniquet (42.2) — il a déjà pris possession du
  prospect.
- **La traçabilité de confirmation ne bouge pas.** `_aod_confirmed_by`/`_aod_confirmed_at`
  (Phase 1.1) restent la trace de qui a **réellement confirmé** ; l'assignation
  (`_aod_assigned_confirmer`) est un état **présent**, réassignable, distinct.
- **Gérant = toujours tout voir.** Comme le périmètre produit, l'assignation ne s'applique
  jamais au gérant (`manage_woocommerce`) — il voit et peut réassigner toute commande, y
  compris les non-assignées.

### 42.1 — Modèle de données + assignation automatique par charge (formulaire public) ✅
- Nouvelle méta de commande `_aod_assigned_confirmer` (ID utilisateur, absente/`0` = non
  assignée). Écrite en HPOS-safe (`$order->update_meta_data()` + `save()`), jamais
  `postmeta` direct.
- Nouvelle méthode `AOD_CD_Roles::eligible_confirmers( $product_ids = array() )` : vivier
  = confirmateurs (a) `has_cap( AOD_CD_CAP )` (actifs), (b) `user_can( 'aod_cap_confirm_orders' )`,
  (c) périmètre produit vide **ou** intersectant `$product_ids` (D1/D3 de A.19). Réutilisable
  par 42.1 et 42.2.
- Nouvelle méthode `AOD_CD_Section_Orders::assign_least_loaded( $order )` : parmi le vivier
  éligible aux produits de la commande, choisit celui qui porte le **moins** de commandes
  actives assignées (statuts hors `completed`/`cancelled`/`refunded` — requête
  `scoped_orders`-like filtrée sur `_aod_assigned_confirmer`, pas un comptage PHP après coup).
  Branchée sur `aod_cod_order_created` (prio à fixer après `AOD_COD_WhatsApp`/numérotation —
  ne doit dépendre d'aucun des deux). Vivier vide ⇒ **aucune** méta posée (commande non
  assignée, visible du seul gérant — comportement identique à aujourd'hui).
- **Preuve** : cliquet unitaire (nom fixé en story BMAD) — 2 confirmateurs éligibles à charge
  égale → la commande va au premier par ID (déterminisme) ; à charge inégale → au moins
  chargé ; confirmateur inactif/sans cap/hors périmètre produit jamais choisi (red-proof :
  retirer la garde, vérifier qu'il est choisi à tort) ; vivier vide → aucune méta posée.

### 42.2 — Assignation directe à la conversion d'un abandon (Phase 39) ✅
- `AOD_CD_Section_Abandoned::ajax_convert_lead()` pose `_aod_assigned_confirmer` =
  l'utilisateur courant **si et seulement si** c'est un confirmateur porteur de
  `aod_cap_confirm_orders` (le gérant qui convertit ne s'auto-assigne pas — il n'est pas un
  confirmateur, et devrait pouvoir répartir ensuite via 42.4/42.5). **Pas** de passage par
  `assign_least_loaded()` — décision D-conversion de A.19 : le confirmateur a déjà pris
  possession du prospect en le rappelant, un tourniquet la lui reprendrait arbitrairement.
- **Preuve** : cliquet étendu (`test-conversion-abandon.php`) — conversion par un
  confirmateur → commande assignée à lui, quel que soit son périmètre produit (il vient de la
  créer, ça ne peut pas être hors périmètre puisqu'il l'a composée) ; conversion par le gérant
  → aucune assignation.

### 42.3 — Cumul dans le périmètre de lecture (Commandes + Suivi + Accueil) ✅
> **Périmètre étendu — arbitré avec l'owner le 2026-07-26.** L'assignation s'applique
> **partout**, pas seulement à la liste Commandes : Suivi commande et les stats de
> l'Accueil aussi. **Garde-fou nécessaire** (trouvé en cadrant la story) : le filtre
> d'assignation ne doit s'activer QUE pour un confirmateur porteur de
> `aod_cap_confirm_orders` — un confirmateur « tracking seul » ou « stock seul »
> (sans cette cap) n'est **jamais** dans le vivier de 42.1/42.2, donc n'a **jamais** de
> commande assignée ; lui appliquer le filtre viderait Suivi à tort pour un rôle qui n'a
> rien à voir avec la confirmation. Le filtre suit la **capability**, pas seulement le rôle.
- `AOD_CD_Section_Orders::scoped_orders()` gagne un second filtre, **cumulatif** au filtre
  produit existant (jamais substitué, D1) : si l'utilisateur courant n'est pas le gérant **et**
  porte `aod_cap_confirm_orders`, `meta_query` sur `_aod_assigned_confirmer = user_id`
  (simple ajout de clause — contrairement au périmètre produit, `_aod_assigned_confirmer`
  est une méta de COMMANDE, pas de ligne d'article : pas besoin de jointure `order_items`,
  `wc_get_orders( meta_query )` suffit nativement en HPOS **et** CPT). Liste, compteurs
  (onglets) et pagination en héritent automatiquement. **Consommateurs déjà couverts sans
  y toucher** : `AOD_CD_Section_Tracking` appelle `scoped_orders()`/`order_in_scope()`
  directement (Suivi commande hérite du même filtre).
- `order_in_scope()` gagne la même garde en **défense en profondeur** : un ID de commande
  assignée à un autre confirmateur, dans son périmètre produit, reste refusé (403) sur accès
  direct (`order_detail`/`order_status`/`order_note`/`order_save_info`, et les accès directs
  de `AOD_CD_Section_Tracking`) — jusqu'ici seul le périmètre produit était vérifié, une
  commande assignée à un pair et dans le même périmètre produit passait la garde.
- **Accueil (stats) — mécanisme SÉPARÉ, à traiter explicitement.**
  `AOD_CD_Section_Home` ne réutilise PAS `scoped_orders()` (son propre commentaire le dit :
  « paramétré par le périmètre produits… ici le filtre est un choix d'écran du gérant ») —
  elle a son propre point de vérité `user_scope()` (périmètre produit, ~7 sites d'appel) et
  `filtered_orders()`/le parcours par lots (Phase 31.6). Modifier `scoped_orders()` seul ne
  propage donc PAS à l'Accueil : `user_scope()` gagne un jumeau `assignment_scope()` (même
  garde `aod_cap_confirm_orders`), et `filtered_orders()` + le parcours par lots reçoivent un
  paramètre de plus, threadé aux mêmes ~7 sites que `user_scope()`. 🚨 **Ne pas confondre**
  avec le filtre confirmateur EXISTANT de l'Accueil (`_aod_confirmed_by`, Phase 2.2) : c'est
  qui a **réellement confirmé** (historique, jamais réécrit) ; `_aod_assigned_confirmer` est
  qui **possède actuellement** la commande — deux notions distinctes, les DEUX filtres
  peuvent s'appliquer en même temps sans redondance.
- **Non-régression explicite** : le gérant n'est filtré par **aucun** des filtres (déjà
  vrai pour le produit ; l'assignation suit la même exception). Une commande **non assignée**
  n'apparaît chez **aucun** confirmateur tant que 42.1/42.2 ne l'ont pas posée ou que le
  gérant ne l'a pas assignée (42.4/42.5) — conforme à D4. Un confirmateur SANS
  `aod_cap_confirm_orders` (tracking/stock seul) : comportement **inchangé** (périmètre
  produit seul, comme avant la Phase 42).
- **Preuve** : cliquet — confirmateur A ne voit/ne peut agir QUE sur ses commandes assignées
  (même produit qu'un confirmateur B qui, lui, ne les voit pas), dans Commandes **et** Suivi ;
  commande non assignée invisible des deux ; gérant voit tout partout ;
  `order_in_scope()` refuse un accès
  direct hors assignation même dans le périmètre produit.
- **Scindé de l'Accueil (42.3bis), décision prise en écrivant la story.** `AOD_CD_Section_Home`
  ne partage AUCUN mécanisme avec `scoped_orders()` (mécanisme séparé ci-dessus) : au moins 10
  sites d'appel (`filtered_orders()`/`each_filtered_order()`, revenus/livraison/confirmation/
  todo/dropdown produit) devraient chacun recevoir le nouveau paramètre, et les clés de cache
  (transients `CACHE_VER_OPTION`) devraient intégrer la dimension assignation pour ne pas
  servir à un confirmateur le cache calculé pour un autre. Fusionner ça dans 42.3 aurait fait
  courir le risque exact que ce dépôt a déjà rencontré sur ce fichier (bug 5.5 « périmètre
  produit oublié dans Home », réapparu une fois) : un site d'appel oublié ne casse rien
  bruyamment, il fuit en silence. Traité séparément, à effort dédié.

### 42.3bis — Cumul dans les stats de l'Accueil (scindé de 42.3) ✅
- `AOD_CD_Section_Home::user_scope()` gagne un jumeau `assignment_scope()` (même garde
  `aod_cap_confirm_orders` que 42.3), `filtered_orders()`/`each_filtered_order()` gagnent un
  paramètre `$assigned_id` de plus, threadé à **tous** les sites d'appel identifiés (revenus,
  livraison, confirmation, bandeau « À traiter », restriction du sélecteur produit).
  `home_join_sql()` gagne une jointure jumelle de celle du produit/périmètre, sur
  `_aod_assigned_confirmer` (méta de COMMANDE, valeur unique — pas de sous-requête DISTINCT
  nécessaire, contrairement au périmètre produit qui porte sur les lignes d'article).
- **Clé de cache** : chaque transient dont la clé intègre déjà le périmètre produit
  (`user_scope()`) doit aussi intégrer l'assignation — sinon deux confirmateurs du même
  périmètre produit mais assignations différentes partageraient un cache faux pour l'un des
  deux (même piège que le périmètre confirmateur des onglets Livraison/Confirmation, déjà
  dans la clé aujourd'hui pour le PRODUIT, à étendre pour l'ASSIGNATION).
- **Preuve** : chaque site d'appel identifié est couvert par un red-proof (retirer le
  threading sur UN site à la fois, vérifier qu'il redevient rouge) — la leçon de la « règle du
  témoin » (§C.2) : un site oublié doit se voir, pas rester invisible dans un total qui reste
  vert par ailleurs.

### 42.4 — Sélecteur d'assignation/réassignation (gérant uniquement) ✅
- Nouveau contrôle par commande, visible **seulement** du gérant (`manage_woocommerce`) —
  un confirmateur n'a besoin de voir ni le sélecteur ni le nom de l'assigné (c'est toujours
  lui). Réutilise le patron `status_select_html()` (menu personnalisé positionné en JS,
  clavier/ARIA du socle a11y Phase 5 — `bindListboxKeys`) plutôt qu'un `<select>` natif.
  Options = `eligible_confirmers()` **sans** filtre produit pour le gérant (il peut assigner
  n'importe qui, y compris hors du périmètre habituel d'un confirmateur — un gérant peut
  vouloir élargir un périmètre via une assignation ponctuelle) + « Non assignée ».
- Nouvel AJAX `aod_cd_order_assign` : nonce `aod_cd` + `manage_woocommerce` strict (famille
  action-forte, comme `delete_orders`/`save_stock` — **jamais** `aod_cap_confirm_orders`,
  sans quoi un confirmateur pourrait se réassigner des commandes d'un pair). Réassignable à
  tout statut — ne touche à rien d'autre (prix, statut, envoi transporteur).
- Placement : dans la modale de détail (comme le statut) plutôt qu'une colonne de tableau —
  la table est déjà au plafond de 7 colonnes priorisées (héritage 5.4) et l'info n'intéresse
  que le gérant, qui ouvre la modale pour agir.
- **Preuve** : cliquet permissions (`test-permissions-sections.php`) — nouvelle entrée
  `order_assign` = `manage_woocommerce`, `action-forte`, avec sa raison ; un POST forgé en
  confirmateur est refusé (403).

### 42.5 — Rattrapage : assignation groupée des commandes non assignées ✅
- Réutilise le patron de sélection groupée existant (Phase 39.6 — barre `.aod-cd-bulkbar`,
  case « tout sélectionner », compteur) : dans l'onglet Commandes, un filtre **« Non
  assignées »** (nouvel onglet ou segment, à trancher en story) + case à cocher + « Assigner
  à… » (menu des confirmateurs éligibles). Sans cet outil, D4 laisse le gérant cliquer
  commande par commande sur un catalogue historique potentiellement large.
- Nouvel AJAX `aod_cd_orders_bulk_assign` : nonce `aod_cd` + `manage_woocommerce`
  (action-forte, même famille que `aod_cd_delete_leads`/`aod_cd_delete_orders`), écrit
  `_aod_assigned_confirmer` sur les IDs reçus **après** vérification que chaque commande est
  bien non assignée (ou réassignation explicite assumée — à trancher en story si le gérant
  doit pouvoir écraser une assignation existante en masse).
- **Preuve** : porte de recette étendue (`run.sh` — scénario à nommer) : N commandes
  historiques non assignées → sélection + « Assigner à Confirmateur X » → toutes portent la
  méta (relu en base) → apparaissent désormais dans la vue de ce confirmateur.

### 42.6 — Clôture : i18n, cliquet, porte de recette, docs ✅
- `make-translations.sh --strict` vert (chrome neuf : sélecteur d'assignation, « Non
  assignée », « Assigner à… », libellés du rattrapage groupé) + AR manuel.
- Cliquet unitaire dédié (nom à fixer en story, ex. `test-assignation-commandes.php`) :
  couvre 42.1 (charge/vivier/red-proofs), 42.2 (conversion), 42.3 (cumul lecture + défense en
  profondeur), 42.4/42.5 (permission action-forte).
- Porte de recette navigateur (scénario à nommer, ex. `assignation`) : bout en bout — 2
  confirmateurs, commandes réparties par charge, réassignation gérant reflétée côté
  confirmateur (relogin), rattrapage groupé sur des commandes historiques.
- MAJ `docs/ARCHITECTURE.md` (nouvelle méta `_aod_assigned_confirmer`, 2 AJAX neufs, cumul de
  périmètre), `TEMPLATE.md` (« Phase 42 soldée »), `test-doc-derive` vert.

### 42.7 — Colonne « Assigné à » (Commandes + Suivi) + libération à la suppression ✅

*Ajoutée APRÈS la clôture de la 42.6, et restée absente de ce plan comme de la
table §C.3 jusqu'à la 43.9 : elle n'existait qu'en git (`ea8a20d`, correctif
`be6db6a`). Consignée ici pour que la table redevienne complète.*

- `AOD_CD_Section_Orders::assigned_confirmer_label()` (`public static`) : nom de
  l'assigné ou tiret sourd. Colonne **gérant uniquement** sur Commandes et Suivi —
  un confirmateur ne doit jamais voir le nom d'un assigné, y compris le sien.
- `unassign_confirmer()` appelée AVANT `wp_delete_user()` (section Équipe).
  🚨 `wp_delete_user()` ne réattribue que `post_author`, jamais une méta métier :
  sans cet appel, supprimer un confirmateur laissait ses commandes marquées
  « assignées » à un compte fantôme — invisibles du filtre « Non assignées » (qui
  teste une ABSENCE de méta, pas la validité de sa valeur) **et** de tout
  confirmateur. Silencieusement perdues pour tout le monde sauf le gérant, qui
  les croyait prises en charge.

---

## Phase 43 — Commandes / Suivi / Abandonnées : filtres, assignation, prix, sidebar

*Planifiée le 2026-07-27 (cf. verdict A.20, demande owner). **Dépend de la Phase 42**
(assignation — le filtre confirmateur et la traçabilité « assigné par » n'ont de sens
que parce que `_aod_assigned_confirmer` existe) et de la **Phase 35/37** (`estimate_lines()`,
réutilisée telle quelle pour le prix des abandons). Six améliorations groupées parce
qu'elles touchent les mêmes trois sections (`Section_Orders`, `Section_Tracking`,
`Section_Abandoned`) et la coque (`dashboard.css`/`dashboard.js`).*

**Versions cibles** : `aod-client-dashboard` v0.69.0 → **v0.75.0**.
⚠️ **`aod-cod-form` A ÉTÉ touché**, contrairement à ce que cette ligne a annoncé
jusqu'à la 43.9 : migration de schéma des prospects en **43.3** (`assigned_confirmer`)
puis en **43.4** (`assigned_by`), et mode d'estimation tolérant sur `estimate_lines()`
en **43.6** (v1.47.0 → v1.51.0). La promesse était sincère quand elle a été écrite —
le plan datait d'avant ces trois découvertes — mais elle est restée fausse trois
étapes durant. *Une contrainte de périmètre qui ne tient plus doit être corrigée à
l'endroit où on la lit, pas seulement dans la story qui l'a enfreinte.*

**Le fil rouge de la phase** : **cinq gains rapides, un chantier à part.** Les filtres,
le bouton d'assignation sur Abandonnées, la traçabilité « assigné par », l'écrasement
en masse et la colonne montant sont des ajouts bornés sur des mécanismes déjà éprouvés
(patrons de filtre existants, `apply_assignment()`, `estimate_lines()`). La sidebar
hover/focus est différente : c'est une interaction nouvelle sur une zone très
travaillée en accessibilité (Phases 5.8, 17, 19) — elle est volontairement la
**dernière** étape, pour ne pas faire dépendre les cinq autres de sa mise au point.

**Deux invariants, valables à chaque étape :**

- **Gérant uniquement pour tout ce qui touche l'assignation** (bouton sur Abandonnées,
  traçabilité, écrasement en masse) — même famille « action-forte » que le reste de
  l'assignation (Phase 42, D5 de A.19). Le filtre confirmateur, lui, est un filtre de
  **lecture** : à réserver au gérant également (un confirmateur ne doit pas pouvoir
  lister les commandes d'un pair par ce biais, cf. le sélecteur d'assignation qui est
  déjà cette même restriction).
- **Zéro nouvelle formule de prix.** Le montant sur Abandonnées appelle
  `AOD_COD_Form::estimate_lines()` — jamais un recalcul indépendant. Un lead à
  wilaya/commune vide ou un produit supprimé doit rendre un montant **absent** (tiret),
  pas une estimation fausse à 0 DA qui se ferait passer pour un vrai chiffre.

### 43.1 — Filtre par date (Commandes, Suivi, Abandonnées) ✅
- Formulaire GET sans JS (même patron que les filtres transporteur/statut de Suivi,
  `render_tracking_filters()`) : `date_from`/`date_to` (inputs `date` natifs,
  RTL-safe — déjà validé pour l'Accueil en 2.5), plus des raccourcis Jour/Semaine
  cohérents avec ceux de l'Accueil (`Section_Home`).
- Commandes ET Suivi : filtre sur `date_created` de la commande (`wc_get_orders()`,
  paramètre `date_created` en range `"{from}...{to}"`/opérateurs `>=`/`<=`, vérifié
  fonctionnel sur ce clone HPOS). ⚠️ **Correction au cadrage initial** : ce plan
  prévoyait un filtre Suivi sur la « date d'expédition » — vérifié dans le code
  (`render_tracking_row()`), la seule date rendue sur cette page est
  `$order->get_date_created()` (même colonne « Date » que Commandes) ; aucune méta
  de date d'expédition n'est capturée ni affichée (`_aod_ship_status_at` est la
  *dernière mise à jour de suivi*, pas la date d'envoi). Filtrer sur une date
  invisible ailleurs sur l'écran aurait été incohérent avec ce que le gérant voit —
  les deux pages filtrent donc sur la **même** date, celle réellement affichée.
  Abandonnées : filtre sur `updated_at` du lead (dernière activité, la colonne
  « Quand » déjà affichée, `where_sql()` déjà le bon point d'extension).
- État persistant en query-string, reporté par les liens de pagination/tri déjà en
  place — même règle que les filtres existants (D'un filtre à l'autre, le patron ne
  change pas).
- **Preuve** : cliquet unitaire (bornes incluses/exclues, fuseau du site) + porte de
  recette (3 pages, un jeu de dates qui inclut/exclut une fixture connue).

### 43.2 — Filtre par confirmateur (Commandes, Suivi, Abandonnées) ✅
- Même formulaire GET, `<select>` des confirmateurs actifs porteurs de
  `aod_cap_confirm_orders` (réutilise `AOD_CD_Roles::eligible_confirmers()`,
  Phase 42.1) — **gérant uniquement** (invariant ci-dessus).
- Commandes/Suivi : filtre sur `_aod_assigned_confirmer` (assignation, Phase 42 —
  **pas** `_aod_confirmed_by`, qui reste l'historique de qui a réellement confirmé,
  cf. A.19). Abandonnées : un lead n'a pas d'assignation avant la story 43.3 — la
  filtrer par assignation n'a de sens qu'**après** que 43.3 lui en donne une ; ordre
  d'implémentation à respecter (43.3 avant 43.2 sur cette page précise, ou filtre
  ajouté à Abandonnées en fin de 43.3 directement).
- **Preuve** : cliquet + porte de recette (2 confirmateurs, filtre isolant chacun).

### 43.3 — Bouton d'assignation sur « Commandes abandonnées » ✅
- Réutilise le sélecteur de 42.4 (`assign_select_html()`, même patron, même classes
  CSS distinctes) sur chaque ligne de `Section_Abandoned::render_row()` — gérant
  uniquement. Un lead assigné le reste après conversion (39.x) : `maybe_assign_converter()`
  ne doit écraser une assignation manuelle préexistante que si elle n'existe pas déjà
  (sinon une pré-assignation du gérant serait effacée par la conversion du confirmateur
  qui n'est pas forcément celui pré-assigné).
- Nouvel AJAX ou extension d'`ajax_order_assign()` pour accepter un `lead_id` en plus
  d'un `order_id` (à trancher en story : deux handlers distincts sont plus sûrs — un
  lead n'est pas une commande, cf. `A.19` « hors périmètre » — mais dupliquent
  `valid_confirmer()` ; à mutualiser via un helper partagé, pas deux implémentations).
- **Preuve** : cliquet (assignation posée/lue/écrasée par une conversion respecte la
  pré-assignation) + porte de recette (`abandonnees`, étendue).

### 43.4 — Traçabilité « assigné par » + libellé « Admin » ✅
- Nouvelle méta `_aod_assigned_by` (ID utilisateur assignant, `0`/absente = assignation
  automatique/système), posée aux **quatre** points d'écriture : `assign_least_loaded()`
  (42.1, tourniquet → `_aod_assigned_by` **non posée**, c'est le système), 
  `maybe_assign_converter()` (42.2 → l'ID du confirmateur qui convertit),
  `apply_assignment()` (42.4/43.3, sélecteur manuel → l'ID de l'appelant, **gérant** par
  construction puisque l'action est gérant-only), `ajax_orders_bulk_assign()`
  (42.5/43.5 → idem).
- Affichage : `assigned_confirmer_label()` (et son jumeau sur Abandonnées, 43.3) gagne
  un second fragment — si `_aod_assigned_by` correspond à un compte `manage_woocommerce`,
  afficher le libellé **littéral** « Admin » (jamais le `display_name` du gérant —
  A.20, **Constat 3** ; ce plan citait « D3 de A.20 », or A.20 n'a que D1 et D2) ; si c'est un confirmateur (cas conversion), rien de plus n'est affiché
  (le nom de l'assigné suffit, cf. constat 3 : seule l'assignation manuelle du gérant
  a besoin d'être distinguée) ; si absente, rien (tourniquet automatique).
- **Preuve** : cliquet (les 4 chemins posent la bonne valeur, y compris absence pour le
  tourniquet) + porte de recette (assignation manuelle gérant affiche « Admin »,
  assignation par conversion affiche le nom du confirmateur).

### 43.5 — Assignation groupée : écrasement explicite (D1 de A.20) ✅
- `ajax_orders_bulk_assign()` gagne un paramètre `overwrite` (bool, posté par une
  case à cocher **visible et non cochée par défaut** dans l'UI — jamais un défaut
  caché, cf. D1). `overwrite=0` (défaut) = comportement actuel inchangé (saute les
  commandes déjà assignées, 42.5) ; `overwrite=1` = réassigne aussi les commandes
  déjà assignées du lot, en posant `_aod_assigned_by` (43.4) comme toute assignation
  manuelle.
- Réponse AJAX distingue toujours « assignées » de « sautées » (ou « réassignées »
  avec `overwrite`), pour que le gérant voie ce qui a réellement changé — même
  exigence de clarté que le reste de l'assignation groupée.
- **Preuve** : cliquet (les deux modes, y compris qu'`overwrite=0` reste
  rigoureusement identique au comportement de 42.5 — non-régression explicite) +
  porte de recette.

### 43.6 — Montant total sur « Commandes abandonnées » (frais de livraison inclus) ✅
- Nouvelle colonne dans `render_row()`, calculée via `AOD_COD_Form::estimate_lines()`
  à partir de `product_id`/`qty`/`wilaya`/`delivery` du lead — zéro nouvelle formule
  (invariant de tête de phase). Rendu `wc_price()`, cohérent avec le reste de
  `/gestion`.
- Cas de repli explicites : produit supprimé (39.4 a déjà ce cas), wilaya/commune
  absente (lead capturé tôt dans le formulaire, avant le choix de livraison) → tiret,
  jamais un montant à 0 qui se ferait passer pour un vrai calcul (constat de tête de
  phase).
- **Preuve** : cliquet (montant = produit×qty + frais de livraison de la wilaya/type
  exacts, cas de repli couverts) + porte de recette (un lead connu, montant vérifié
  contre le calcul attendu).

### 43.7 — Sidebar : scrollbar verticale sur débordement ✅
- `overflow-y:auto` sur `.aod-cd-side` (`dashboard.css:173`) + une scrollbar fine
  stylée (cohérente avec la charte teintée, Phase 15) plutôt que la scrollbar
  système par défaut, qui tranchait sur un fond sombre dérivé de l'accent.
- Correctif isolé, bas risque, indépendant de 43.8 — livrable et vérifiable seul.
- **Preuve** : porte de recette (sidebar forcée à déborder — ex. zoom navigateur ou
  fenêtre réduite en hauteur — scrollbar présente, contenu accessible en entier).

### 43.8 — Sidebar : repliée par défaut, dépliée au survol ET au focus clavier (D2 de A.20) ✅
- État replié par défaut sur desktop (icônes seules, largeur réduite) ; `:hover` **et**
  `:focus-within` sur `.aod-cd-side` déclenchent le **même** état déplié — un seul
  état visuel, deux déclencheurs, pas un mécanisme séparé pour le clavier (sinon deux
  comportements à maintenir en cohérence dans le temps).
- Comportement **desktop uniquement** ; le hamburger mobile (`.is-open`) n'est pas
  touché — ce sont deux mécanismes indépendants, pas un pour remplacer l'autre.
- `prefers-reduced-motion` : la bascule replié/déplié reste **instantanée** quand ce
  réglage est actif (changement de mise en page informatif, pas un confort visuel —
  même raisonnement que la Phase 19 côté public).
- 🚨 Point d'attention porté par les Phases 5.8/17 : ne pas casser le piège de focus
  existant (`trapFocus`) ni l'ordre de tabulation — un lien de nav qui reçoit le focus
  doit déplier la sidebar **avant** que la ième case suivante ne soit atteinte au TAB
  suivant, sinon le focus visuel et l'état visible divergent.
- **Preuve** : cliquet a11y existant étendu + porte de recette clavier (`a11y-clavier.js`) :
  TAB déplie, Échap/perte de focus replie, aucune régression sur le piège de focus des
  menus flottants déjà en place.

### 43.9 — Clôture : i18n, cliquets, porte de recette, docs ✅
- `make-translations.sh --strict` vert (chrome neuf : filtres date/confirmateur,
  bouton d'assignation Abandonnées, case « écraser », libellé « Admin », colonne
  montant) + AR manuel.
- Cliquets unitaires des étapes 43.1 → 43.6 regroupés/vérifiés ensemble ; porte
  `a11y-clavier`/`souris`/`contraste` rejouées sur la sidebar (43.7/43.8, changement
  de surface visuelle sur toutes les pages de `/gestion`).
- Porte de recette dédiée ou extension des portes existantes (`commandes-suivi`,
  `abandonnees`) — à nommer en story.
- MAJ `docs/ARCHITECTURE.md` (méta `_aod_assigned_by`, filtres, colonne montant,
  comportement sidebar), `TEMPLATE.md` (pointeur §C.3 déjà générique, pas de récit à
  ajouter — cf. dégonflage D25), `test-doc-derive` vert.

---

## Phase 44 — Bornes d'entrée publique, idempotence de l'installeur, cliquet documentaire élargi

*Planifiée le 2026-07-29 à la suite d'un **audit A→Z du dépôt** demandé par l'owner —
pas d'une demande de fonctionnalité, donc **aucune entrée en Partie A** : les verdicts
de faisabilité répondent à « peut-on faire X ? », pas à « qu'est-ce qui est cassé ? ».
Trois défauts sans aucun rapport fonctionnel entre eux, groupés parce qu'ils partagent
**une seule et même cause**.*

**Versions cibles** : `aod-cod-form` v1.51.0 → **v1.52.0** (44.1 seule).
`aod-client-dashboard` **non touché**. 44.2 et 44.3 ne touchent aucun plugin
(`scripts/setup.sh`, `tests/unit/`, `README.md`, `docs/ARCHITECTURE.md`).

**Le fil rouge : trois fois le même défaut de PROPAGATION.** Aucun des trois n'est une
faute d'ignorance. Dans les trois cas, le motif abouti était **déjà écrit dans ce
dépôt** — il n'a simplement pas atteint son jumeau.

| Étape | Le bon motif, déjà présent | Là où il n'avait pas été porté | Écart |
|---|---|---|---|
| 44.1 | garde de rupture des variantes maison (`build_order_lines()`, 40.4) · plafond `AOD_COD_Cart::MAX_ITEMS = 30` du panier en LECTURE (35.3) | le stock **Woo** du produit · le nombre d'articles du chemin d'**ÉCRITURE** | 30 lignes |
| 44.2 | le bloc **5bis** de `setup.sh`, écrit explicitement non destructif (« si l'owner a déjà réglé une largeur, on n'y touche pas ») | l'activation du thème, **six lignes au-dessus** | 1 ligne |
| 44.3 | le cliquet `test-doc-derive.php` (32.6, dette D30) | `README.md` et `docs/ARCHITECTURE.md`, que son corpus **n'ouvre pas** (`:63-65`) | 2 lignes + 1 règle |

*C'est la leçon de la phase, et elle vaut au-delà d'elle : **un correctif qui ne
cherche pas ses jumeaux est un correctif à moitié fait.** Les Phases 17, 21 et 32 ont
chacune consigné cette forme (une règle vérifiée sur son exemple canonique paraît
tenue partout) ; c'est la première fois qu'on la traite comme le sujet d'une phase.*

**Trois invariants, valables à chaque étape :**

- **44.1 ajoute des REFUS, jamais un calcul.** `price_lines()`, `offer_lot_total()` et
  `effective_shipping()` sont **intouchées** — le chemin de l'argent a été audité sain
  le 2026-07-29 et ne doit pas bouger dans une phase de durcissement. Une ligne refusée
  est une ligne **absente**, jamais une ligne à 0.
- **Un plafond se déclare une fois.** 44.1 réutilise `AOD_COD_Cart::MAX_ITEMS` au lieu
  d'introduire une seconde constante pour la même notion : deux plafonds jumeaux
  divergent — c'est littéralement la cause du défaut qu'on corrige.
- **Aucune régression sur 43.6.** La garde de stock ne s'applique **qu'en mode strict**.
  Le mode tolérant sert à estimer le montant d'un panier *abandonné* : un produit tombé
  en rupture depuis n'annule pas ce que le prospect voulait. L'appliquer là rendrait la
  colonne « Montant » vide sur les produits en rupture — exactement le tiret contre
  lequel la 43.6 s'est battue.

### 44.1 — Bornes serveur de la soumission COD : quantité, nombre d'articles, stock Woo ✅

**Ce qui est mesuré** (2026-07-29, sur ce clone, WooCommerce 10.8.1) :

- `class-aod-cod-form.php:1441` — `max( 1, absint( $item['qty'] ) )` ferme le bas,
  **jamais le haut**. Aucun plafond nulle part sur le chemin d'écriture.
- `:1433` — la seule garde produit est `is_purchasable()`. Source WooCommerce
  (`abstract-wc-product.php:1757`) : `exists() && statut publish && '' !== get_price()`.
  **Elle ne regarde ni le stock ni les backorders.** Le seul `is_in_stock()` du plugin
  est en `:351`, dans le chemin d'**affichage** des variantes.
- `:1326` — la commande est créée en `processing`, et `wc-stock-functions.php:126`
  accroche `wc_maybe_reduce_stock_levels` sur **ce statut exact**.
- `class-aod-cd-section-products.php:1230` — `/gestion → Produits` propose une case
  « gérer le stock » (`set_manage_stock`) sur laquelle **toute la section Stock** est
  bâtie. Le template vend donc la fonctionnalité qui rend le défaut atteignable.
- `:1203` — `$items_raw` n'a **aucun plafond**, là où l'endpoint de LECTURE du panier
  en impose un à 30 (`class-aod-cod-cart.php:241`). L'endpoint qui **écrit** est moins
  borné que celui qui **lit**.

**Le scénario, bout en bout** : `items[0][qty]=100000` sur un produit à stock géré →
commande `processing` → stock à **−99 997** → produit affiché « en rupture », boutique
morte pour les vrais acheteurs. Aucun paiement requis (c'est du COD) : **coût nul pour
l'attaquant**, 10 fois par heure et par IP (`AOD_COD_MAX_PER_IP_HOUR`). Le verrou
téléphone 24 h ne freine rien — un numéro par commande, ~30 M de valeurs valides.

**Ce qu'on fait :**

- Nouvelle constante `AOD_COD_MAX_QTY_PER_LINE` (défaut **50**) déclarée dans
  `aod-cod-form.php` **à côté de ses deux jumelles** `AOD_COD_MAX_PER_IP_HOUR` /
  `AOD_COD_MAX_LEADS_PER_IP_HOUR`, `define()`-able avant chargement comme elles.
  ⚠️ Un plafond **atteint** doit être une ERREUR affichée, pas un écrêtage silencieux :
  ramener 100 000 à 50 sans le dire ferait passer une commande que l'acheteur n'a pas
  voulue. Écrêter le *nombre d'articles*, en revanche, est admissible (`array_slice`) —
  c'est une borne de charge sur une forme que le formulaire ne produit jamais.
- `$items_raw` tronqué à `AOD_COD_Cart::MAX_ITEMS` — **la constante existante**.
- Garde de stock produit posée **à côté** de la garde de variante (`:1467`), même forme,
  même message d'erreur numéroté par article. Sémantique alignée sur le cœur
  (`WC_Cart::check_cart_item_stock`) : refus si `! has_enough_stock( $qty )` **et**
  `! backorders_allowed()`. Stock non géré ⇒ jamais bloqué.
- 🚨 **Et le panneau panier avec, sinon la ligne DISPARAÎT.** `AOD_COD_Cart::ajax_items()`
  scinde `$alive`/`$dead` **avant** l'estimation (`:263-270`) précisément pour montrer
  *quelle* ligne est morte. Sa condition est `is_purchasable()` — la même lacune. Sans
  la corriger dans le même geste, un produit en rupture sortirait de `$est['lines']`
  sans entrer dans `$dead` : il s'évaporerait du panneau **sans un mot**. Le tri des
  deux endroits passe donc par **un seul helper** `AOD_COD_Form::orderable_stock()` —
  la duplication est le défaut qu'on corrige, on ne la recrée pas.
- ⚠️ **TOCTOU assumé, et écrit** : deux commandes concurrentes franchissent la garde
  toutes les deux. Le décrément réel reste **différé à la confirmation** (40.5, décision
  D3) : cette garde coupe l'abus grossier, elle ne garantit pas l'exactitude. Ne pas la
  vendre pour ce qu'elle n'est pas.

- **Preuve** : cliquet dédié `tests/unit/test-bornes-cod.php` — vraies `WC_Product`,
  stock géré / non géré / backorders, quantité au plafond et au-dessus, plafond
  d'articles, **et le témoin de non-régression** : le mode tolérant continue de chiffrer
  un produit en rupture (43.6). Red-proof sur les trois bornes. Portes `run.sh cod`
  (commande réelle inchangée) et `run.sh panier` (la ligne en rupture s'affiche
  « indisponible » au lieu de disparaître).

### 44.2 — `setup.sh` cesse d'écraser le thème du clone ✅

**Ce qui est mesuré** : `scripts/setup.sh:90` — `wp theme activate astra` est
**inconditionnel**. Or `README.md:122` et `TEMPLATE.md` §8 présentent le script comme
*idempotent*, ce qui invite explicitement à le relancer. Sur un clone où le thème client
est actif, une relance **renvoie le site sous Astra** — et impose de rejouer
`pages-publiques.sh` (les emplacements de menu changent d'un thème à l'autre,
TEMPLATE.md §4).

L'ironie est à six lignes de là : le bloc **5bis**, juste en dessous, est écrit
explicitement pour ne rien détruire (« si l'owner a déjà réglé une largeur, on n'y
touche pas »). La règle était sous la main ; elle n'a pas été appliquée à la ligne du
dessus.

**Ce qu'on fait :** activer Astra **seulement** en l'absence d'un choix. Le repli comble
une absence, il ne remplace jamais un choix — c'est déjà, mot pour mot, la règle écrite
à l'étape **4bis** pour l'*installation* ; on la porte à l'*activation*.

- Thème actif = Astra ou un enfant d'Astra → rien à faire (idempotent).
- Thème actif = un thème par défaut de WordPress (`twenty*`) → c'est l'état d'une
  installation neuve : on active Astra (le comportement voulu au premier passage).
- Thème actif = **autre chose** → c'est le choix de l'owner : **on n'y touche pas**, et
  on le DIT à l'écran (un silence laisserait croire que l'étape a échoué).
- **Preuve** : le contrôle de cohérence de l'étape 7 (« ce clone est-il utilisable ? »)
  exige déjà un thème actif — il reste vert. Épreuve manuelle : activer un thème tiers,
  relancer `setup.sh`, vérifier que le thème tient. ⚠️ Un cliquet PHP ne peut pas voir
  ce défaut (il vit dans un script shell, hors du processus de test) : c'est une
  vérification de recette, et elle est consignée comme telle plutôt que simulée.

### 44.3 — Cliquet documentaire élargi (R8) + remise en accord de README et ARCHITECTURE ✅

**Ce qui est mesuré** : `test-doc-derive.php:63-65` ne charge que trois fichiers —
`ROADMAP-AMELIORATIONS.md`, `TEMPLATE.md`, `CLAUDE.md`. **`README.md` et
`docs/ARCHITECTURE.md` ne sont jamais ouverts**, alors que TEMPLATE.md désigne le second
comme « faisant foi sur le fonctionnement du code ». C'est exactement là que la dérive
s'est déplacée après la 32.6 : le cliquet n'a pas échoué, il a été **construit autour de
ce qui avait déjà mordu**.

Les quatre énoncés faux, relevés le 2026-07-29 :

| Fichier:ligne | Annoncé | Réel |
|---|---|---|
| `README.md:17` | `aod-cod-form` **v1.41.1** | v1.51.0 |
| `README.md:36` | `aod-client-dashboard` **v0.46.0** | v0.75.0 |
| `docs/ARCHITECTURE.md:33` | `aod-cod-form` **v1.44.0** | v1.51.0 |
| `docs/ARCHITECTURE.md:260` | `aod-client-dashboard` **v0.52.0** | v0.75.0 |

`ARCHITECTURE.md` se contredit donc **lui-même à 27 lignes d'écart** : son en-tête `:6`
annonce les bonnes versions, ses deux titres de section les mauvaises. Un lecteur qui
saute directement à la section du plugin qui l'intéresse lit la fausse.

S'y ajoutent des **compteurs d'état périmés** : `README.md:149` annonce « les 17 phases
sont soldées (80 étapes) » (il y en a 43), `README.md:130` « 357 tests » (mesuré :
**1378**) et `:133` « 15 portes » (réel : **23**) ; `ARCHITECTURE.md` décrit la Phase 43
tout en affirmant « Phase 3 en cours » et « Phases 1 à 17 soldées : 80/80 ».

**Ce qu'on fait :**

- **R8 — une version annoncée dans un TITRE est une affirmation sur le présent.**
  Le corpus s'élargit à `README.md` + `ARCHITECTURE.md`, et toute mention `vX.Y.Z`
  portée par un **titre markdown** (`^#{1,6}`) ou par la ligne déclarative
  `Versions analysées :` doit **égaler** l'en-tête `Version:` du plugin cité.
  🚨 **Le périmètre vient d'une MESURE, pas d'une intuition.** Une règle naïve — « toute
  version sur une ligne citant un slug de plugin » — produirait **des dizaines de faux
  positifs** : la ROADMAP est pleine de mentions *historiques* (« Livrée le 2026-07-13
  (`aod-cod-form` v1.25.0) ») et de *cibles* (« Versions cibles : … v1.30.0+ »), qui
  sont **justes** et doivent le rester. Relevé sur les 780 Ko de docs : la restriction
  aux titres attrape **exactement les 4 énoncés faux et zéro mention légitime**. Un
  cliquet qui crie au loup est un cliquet qu'on désarme (leçon D16).
- **Les compteurs d'état dupliqués sont SUPPRIMÉS, pas corrigés.** « 17 phases soldées »,
  « 357 tests », « 15 portes » ne sont pas des informations : ce sont des copies qui
  re-vieilliront. §C.3 fait foi sur les phases, `bash tests/run.sh` sur les tests,
  `SCENARIOS` sur les portes. On remplace chaque compteur par le renvoi correspondant.
  *On ne garde pas un compteur qu'on ne sait pas tenir — c'est la conclusion de D25, et
  elle n'avait été appliquée qu'à `TEMPLATE.md`.*
- ⚠️ **Élargir le corpus élargit AUSSI R6** (toute référence `test-*.php` et
  `run.sh <porte>` citée doit exister). C'est voulu — et c'est un effet de bord à
  vérifier, pas à découvrir : les deux fichiers entrants n'ont jamais été soumis à cette
  règle.
- **Preuve** : R8 red-proofée dans les deux sens (une version fausse dans un titre
  rougit en NOMMANT fichier, plugin, attendu et lu ; une mention historique en prose
  reste verte), plus un **témoin de couverture** — R8 publie le nombre de titres
  versionnés qu'elle a réellement inspectés, et le compte est lui-même une assertion
  (règle du témoin, §C.2).

## Phase 45 — Chrome de `/gestion` : le rail, la barre d'action groupée, et le n° qui cachait un bouton

*Planifiée le 2026-07-29 (cf. verdict A.21). **Indépendante** de toutes les autres
phases. Phase de **chrome pur** : aucun handler AJAX, aucune capability, aucune
requête, aucun schéma de données n'est touché — ce qui définit aussi son critère
de non-régression le plus fort (voir la DoD de phase, plus bas).*

> 📌 **Pourquoi 45 et pas 44.** Le numéro 44 est **déjà pris** par la branche
> `phase-44-bornes-idempotence-cliquet` (4 commits, clôture incluse), non encore
> fusionnée dans `main` au moment où cette phase est planifiée. Numéroter 44 ici
> produirait deux Phases 44 au premier merge, et §C.3 — la table qui fait foi —
> ne pourrait plus les distinguer. Le trou apparent entre 43 et 45 sur cette
> branche n'est donc pas une perte : c'est une réservation. *(Précédent : la
> numérotation au-delà de 17.4 a été définitivement perdue faute d'avoir versé
> le plan avant les commits — cf. DoD §C.2, dernier point.)*
>
> ✅ **La réservation a tenu — PR #3 fusionnée le 2026-07-30** (`2454729`), après
> les Phases 45, 46 et D32. La Phase 44 est donc **antérieure en numéro,
> postérieure en histoire** : ses trois étapes sont en §C.3 au-dessus des 45.x,
> et son plan ci-dessus, à sa place numérique. Conséquence à connaître avant de
> lire une ligne de la 45 ou de la 46 qui semblerait ignorer un correctif de la
> 44 : ces deux phases ont été écrites **sans** la 44 sous les yeux. Aucune ne
> touche les mêmes fichiers de code (44 = `aod-cod-form` + `setup.sh` ; 45/46 =
> `dashboard.css`/`.js` + sections de `/gestion`) — la fusion n'a produit de
> conflit que dans les documents de pilotage et les catalogues de traduction.

**Le fil qui relie les trois défauts.** Ils viennent tous du même geste : une
décision d'interface prise pour de bonnes raisons, puis **jamais reconfrontée à
ce qu'elle produit à l'écran**. Le rail de la 43.8 a réglé la largeur sans
reprendre le centrage de ce qu'elle contient. Le `[hidden]` de la barre a réglé
un défaut réel en en créant un autre — et son cliquet, pourtant annoncé dans le
commentaire, n'a jamais été écrit. Le numéro-bouton a été retenu quand la ligne
n'avait pas encore de rang d'actions ; depuis la 34.1 elle en a un, et personne
n'est repassé. Aucun des trois ne casse quoi que ce soit : ils se **lisent**,
ils ne plantent pas. C'est exactement le motif de la Phase 17.

### Étape 45.1 — Le rail : l'icône centrée dans son cadre (S) ✅

- **Objet** : sur la sidebar repliée (desktop ≥ 781 px), l'icône est centrée dans
  le cadre de 40 px que peint `.is-active` — **décalage mesuré à 0 px**, en FR
  **et** en AR.
- **Contrainte dure** : le libellé continue de s'effacer en `opacity:0`. Ni
  `display:none`, ni `visibility:hidden`, ni retrait du `<span>` — c'est
  l'invariant R4 de `test-sidebar-rail.php`, **et** le porteur du nom accessible
  du lien (le SVG est `aria-hidden`).
- **Écrit en propriétés logiques**, sans règle miroir : le défaut miroite, le
  correctif doit miroiter avec lui.
- **Attention à la cascade** : le dépliage anime `width`. Toute propriété de
  géométrie ajoutée à l'état replié doit être décidée *explicitement* — soit
  listée dans la `transition` (elle glisse avec la largeur), soit assumée
  instantanée. Pas de `transition` nue (Phase 16), et le bloc
  `prefers-reduced-motion` du rail reste aligné.
- **Preuve** : mesure au navigateur, curseur garé (`garerSouris()`), rail replié
  **et** déplié, FR et AR. Une assertion sur le CSS seul ne verrait pas le
  décalage — il naît de la composition de trois règles.
- **Non-régression** : R1 → R6 de `test-sidebar-rail.php` verts sans modification
  d'assertion ; hamburger mobile intact.

### Étape 45.2 — La barre d'action groupée : hors du noir, et permanente (M) ✅

- **Objet** : `#aod-cd-order-bulk` (Commandes) et `#aod-cd-lead-bulk`
  (Abandonnées) quittent le fond `--cd-ink` pour une **surface de la charte**
  (carte claire : `--cd-card`, `--cd-line`, `--cd-radius`, ombre `--cd-shadow`),
  et sont **rendues en permanence** — plus d'attribut `hidden` piloté par la
  sélection.
- **La garantie de 2026 est conservée, par un autre moyen** (D4 de A.21) : tant
  que la sélection est vide, les actions sont `disabled` + `aria-disabled`, le
  compteur lit « 0 sélectionnée(s) » et le bouton « Supprimer (0) ». On ne peut
  toujours pas agir à vide ; on voit simplement à l'avance ce qu'on pourra faire.
- 🚨 **Reprendre le bloc, pas la seule couleur de fond.** Trois règles n'existent
  que parce que le fond est sombre (`.aod-cd-bulk-ow{color:#fff}`,
  `.aod-cd-order-bulk-assign{background:#fff;border-color:#fff}`, le `<select>`
  blanc). Laissées telles quelles sur une surface claire, elles donnent **blanc
  sur blanc**. Le `#b21f14` en dur du survol de « Supprimer » et les
  `rgba(217,45,32,…)` figés sur l'**ancienne** valeur de `--cd-bad` partent au
  passage : cette phase touche ces lignes, c'est le seul moment où les corriger
  ne coûte rien.
- **Harmonisation** : les deux barres deviennent le **même** composant (elles ne
  diffèrent aujourd'hui que par leur contenu, pas par leur peau) ; hauteurs de
  boutons, rayons et espacements pris dans les tokens, jamais en dur.
- **Le cliquet qui manquait** (A.21, Constat 2) : la phase écrit enfin la règle
  que le commentaire de `dashboard.css` annonce depuis 2026, **retournée** —
  l'invariant n'est plus « la barre est masquée à vide » mais « **aucune action
  destructive n'est activable à vide** », vérifié sur le code de production.
  Éprouvé **au rouge** avant d'être cru (§C.2, règle 5).
- **Mobile** : le repli `flex-wrap` de `max-width:780px` reste — il a été posé
  sur 193 px de débordement mesuré (43.3). À revérifier à 390 px, la surface
  claire ne changeant pas la largeur des items.
- **Contraste** : la barre passe d'un fond sombre à un fond clair, donc **tous**
  ses textes changent de rapport. Porte `contraste` rejouée — sur les six
  accents, pas seulement celui du clone.

### Étape 45.3 — Le détail d'une commande devient un bouton nommé (M) ✅

- **Objet** : le n° de commande redevient du **texte** ; un bouton **« Détail »**
  rejoint « Appeler » et « WhatsApp » dans le rang `.aod-cd-call-actions`, sur
  **Commandes et Suivi commande** (D2 de A.21).
- 🚨 **Le rang d'actions devient inconditionnel.** Aujourd'hui il n'est rendu que
  `if ( $phone )` : y mettre « Détail » sans lever cette garde rendrait la
  fenêtre de détail **inatteignable** pour une commande sans téléphone. Le
  téléphone ne conditionne plus que ses deux boutons.
- **Aucun changement JS attendu** : le binder `bindOrderDetail` cible
  `.aod-cd-order-detail` par `querySelectorAll` — le bouton **conserve cette
  classe**, quel que soit l'élément qui la porte. `data-order` inchangé,
  `core.openOrderModal` (consommé par `section-abandoned.js` après conversion,
  39.2) intact. **Si une modification de `section-orders.js` devient nécessaire,
  c'est le signe qu'on a changé le contrat sans le vouloir : s'arrêter et
  relire.**
- **i18n** : une chaîne neuve (« Détail »), donc `make-translations.sh` sur
  `aod-client-dashboard` **et** traduction AR à la main. Le libellé du bouton est
  du **chrome** : il passe par `__()`, jamais par un couple `{ fr, ar }`.
- **Accessibilité** : le bouton porte un nom lisible (« Détail »), là où le n°
  annonçait « #4 ». Vérifier que le n° redevenu texte **ne garde aucun style
  cliquable** (soulignement, curseur) — un faux affordant est pire qu'un vrai
  absent.
- **Carte mobile** : `.aod-cd-table--cards .aod-cd-cell-num` porte des règles
  dédiées (`font-size:16px` sur `.aod-cd-order-detail`,
  `padding-inline-end:48px` pour la case de sélection). Elles visent un bouton
  qui n'existera plus sous cette forme : à reprendre, pas à laisser pendre.

### Étape 45.4 — Clôture : i18n, cliquets, portes de recette, docs (S) ✅

- `make-translations.sh --strict` vert sur `aod-client-dashboard` (chaîne
  « Détail » traduite en AR à la main — pas d'internet sortant).
- `bash tests/run.sh` vert, **compte de tests publié** (départ : 1378).
- Portes rejouées, avec leur raison : `contraste` (la barre change de fond, sur
  6 accents), `rtl` (rail et rang d'actions en AR à 390 px), `noms` (le bouton
  « Détail » et les 13 liens de nav gardent leur nom accessible), `clavier` et
  `souris` (la modale de détail s'ouvre toujours des deux façons),
  `abandonnees` et `assignation` (les deux barres d'action groupée sont leur
  sujet), `mouvement` (le rail et la barre portent des transitions nommées).
- MAJ `docs/UX-GESTION-REFONTE.md` (§2.3 : la barre d'action groupée devient un
  composant de la charte ; le rang d'actions de ligne devient le porteur des
  actions de détail) et `docs/ARCHITECTURE.md` si — et seulement si — quelque
  chose de structurel a bougé. `TEMPLATE.md` : ligne d'état des phases.
- **DoD spécifique à cette phase** (en plus de §C.2) :
  - [ ] `git diff` sur `includes/class-aod-cd-*.php` ne touche **aucun**
        `wp_ajax_`, **aucun** `current_user_can`, **aucune** requête.
  - [ ] Aucune capability, aucun nonce, aucun contrat AJAX modifié.
  - [ ] Aucune couleur de client en dur (`test-charte-surfaces.php` vert).
  - [ ] Mesuré au navigateur en FR **et** en AR, à 1440 px **et** à 390 px.

### Étape 45.5 — Le rail replié tient dans l'écran (S) ✅

> 📌 **Une étape APRÈS la clôture — pourquoi ce n'est pas une entorse.** La 45.4
> certifie ce qui a été livré ; elle ne certifie pas que la demande a été
> entendue. Le retour owner (« la sidebar n'est pas ajustée entièrement »)
> montre que la 45.1 avait réglé le **centrage horizontal** de l'icône sans
> jamais regarder la **colonne** qui la contient — laquelle déborde de 87 à
> 628 px selon la fenêtre et la langue (Constat 1bis, mesuré). Rouvrir la phase
> est plus honnête que d'ouvrir une Phase 46 : c'est le **même** défaut, dans le
> **même** composant, issu du **même** constat mal instruit. §C.3 porte donc
> 45.5, et la phase passe à 5 étapes.

- **Objet** : à l'état **replié** (desktop ≥ 781 px), le rail tient **sans
  défilement** sur toute fenêtre d'au moins 720 px de haut, **en FR comme en AR**.
- **Le principe, en une phrase** : *dans le rail replié, aucun texte invisible ne
  doit peser en hauteur.* Tout le reste en découle.
  - les intitulés de groupe deviennent un filet de 1 px (D6) ;
  - le lien tire sa hauteur d'une **valeur explicite** — donc de l'icône et de la
    fenêtre (`clamp()`) — au lieu de la ligne de texte du `<span>` ;
  - « Voir la boutique » et « Déconnexion » cessent de se replier sur 2 lignes
    (`white-space:nowrap` + hauteur bornée) ;
  - le nom de la boutique, invisible, ne réserve plus ses 46 px.
- **Contrainte dure, inchangée** : le `<span>` reste dans le flux, en `opacity:0`.
  Ni `display:none`, ni `visibility:hidden`, ni retrait — R4 de
  `test-sidebar-rail.php`, et nom accessible du lien. **Borner une hauteur n'est
  pas masquer** : la boîte reste rendue, focusable et nommée.
- **La 43.7 n'est pas touchée** (D7) : `overflow-y:auto` reste, plus
  `scrollbar-gutter:stable both-edges` pour que l'apparition d'une barre de
  défilement ne **décentre** pas les icônes — sans quoi la 45.1 ne vaudrait que
  sur les écrans hauts.
- **Rien ne change à l'état déplié** : mêmes 248 px, même rythme, même densité.
  Le correctif vit entièrement sous `.aod-cd-side:not(:hover):not(:focus-within)`.
  🚨 Un **seul** jeu de règles, pas un couple « collapsed / restore » : deux
  listes à tenir cohérentes divergent au premier état ajouté (c'est déjà la
  raison invoquée en 45.1 pour ne pas animer la bascule).
- **Preuve — au navigateur, et par un INVARIANT plutôt qu'un seuil** : la hauteur
  du rail replié doit être **la même en FR et en AR** (à quelques pixels près).
  Un seuil (« ≤ 720 px ») se contourne en rognant ; l'invariant, lui, dit ce
  qu'on veut vraiment — *la hauteur ne dépend plus du texte*. Publié avec le
  nombre de couples (fenêtre × langue) réellement inspectés — règle du témoin.
- **Le cliquet qui manque aussi** : la 45.1 a été prouvée par une **sonde
  jetable**, et rien ne garde son résultat (`test-sidebar-rail.php` ne contient
  pas une ligne sur le centrage). La mesure du centrage rejoint donc la porte,
  au même endroit que celle du débordement — même geste, même composant.
- **Non-régression** : R1 → R6 de `test-sidebar-rail.php` verts sans modification
  d'assertion ; l'assertion 43.7 de la porte `clavier` (écran de 360 px → le rail
  DÉFILE) reste vraie ; hamburger mobile (< 781 px) intact ; `noms` vert (les 12
  liens gardent leur nom accessible).
- **Clôture repliée dans l'étape** : aucune chaîne d'interface nouvelle (le
  correctif est 100 % CSS + une porte), donc pas de cycle i18n — mais
  `make-translations.sh --strict`, `tests/run.sh` et les portes `clavier`,
  `souris`, `mouvement`, `rtl`, `noms` sont rejouées, et §C.3 / `TEMPLATE.md`
  mis à jour.

---

## Phase 46 — Conformité du chrome de `/gestion` : six règles écrites, six cliquets

*Planifiée le 2026-07-30 (cf. verdict A.22). **Indépendante** de toutes les autres
phases. Phase de **chrome pur**, comme la 45 : aucun handler AJAX, aucune
capability, aucune requête, aucun schéma de données — critère de non-régression le
plus fort de la phase.*

**Ce que cette phase corrige n'est pas huit défauts, c'est un mode de dérive.**
Dans six des huit constats, la bonne décision est **déjà écrite dans le fichier,
avec sa raison, appliquée une ou deux fois** — l'anneau de focus (`dashboard.css:152`),
la région live (7 occurrences correctes contre 1 helper muet), le patron carte
(7 `data-label` posés, la classe absente), la bidirectionnalité d'un cliquet
(`test-icones.php`, jamais portée au CSS). Corriger les occurrences sans écrire les
cliquets serait donc **garantir le retour du défaut** : c'est précisément ce qui
s'est passé entre la 45.2 (qui a énoncé la règle de l'anneau) et ses quatre voisins
restés faux la même journée.

> 🚨 **Conséquence sur l'ordre des étapes, et elle est contre-intuitive.** On ne
> commence PAS par les portes. Un cliquet écrit avant son correctif laisse la suite
> **rouge pendant toute la phase** — et un test rouge en permanence finit par être
> ignoré (dette D16, explicitement). Chaque étape ci-dessous livre donc
> **le correctif ET son cliquet dans le même commit**, le cliquet étant éprouvé au
> rouge en défaisant temporairement le correctif (DoD §C.2 pt 5), jamais l'inverse.

### Étape 46.1 — L'anneau de focus ne dépend plus de l'accent (S) ✅

- **Objet** : les 4 sélecteurs du Constat 1 passent sur `--cd-focus` (liseré
  `--cd-ink` + halo d'accent), comme la case « écraser » de la 45.2.
- **Le cliquet** : un fichier neuf, **nommé par l'étape qui le livre** — pas ici :
  R6 de `test-doc-derive.php` exige que tout `test-*.php` cité existe, et un plan
  qui nomme un fichier à venir serait, littéralement, le défaut que ce document
  traque. R1 — aucune déclaration `outline*`
  sous un sélecteur `:focus*` ne résout vers la famille accent
  (`--cd-accent`, `--cd-accent-strong|soft|wash`, `--cd-yellow`, `--cd-yellow-d`).
  Liste blanche **explicite et vide** : un cas légitime futur devra s'y inscrire
  avec sa raison, pas passer par omission.
- ⚠️ **`--cd-accent-on-dark` n'est PAS dans la famille interdite** : il est calibré
  AA sur nos surfaces sombres, donc légitime en anneau **sur la sidebar**. Le
  cliquet doit le tolérer là et nulle part ailleurs — sinon il interdit une valeur
  correcte et se fera désarmer.
- **Preuve au rendu** : la porte `contraste` gagne l'audit des anneaux
  (`outline-color` calculé vs fond réel, seuil 3:1) sur ses **6 accents**. C'est
  l'angle mort nommé au Constat 1 : sans elle, le cliquet statique garde la
  *règle*, mais rien ne mesure le *résultat*.
- **Non-régression** : `test-charte-surfaces.php` et `test-contraste.php` verts
  sans modification d'assertion ; portes `clavier` et `noms` vertes (l'anneau
  change de forme, pas d'existence — le focus reste visible et nommé).

### Étape 46.2 — Les tableaux de données sont lisibles au pouce (M) ✅

- **Objet** : Stock reçoit `--cards` (Constat 2, un mot) ; les 5 tableaux de
  l'Accueil reçoivent `--cards` **et** leurs `data-label` (Constat 3).
- **Le cliquet** : R2 — tout `<table class="aod-cd-table">` dont les `<td>` portent
  des `data-label` **doit** porter `--cards`, et réciproquement tout `--cards` doit
  avoir ses `data-label`. Les deux sens, pour la raison du Constat 6 : un patron
  mobile à moitié posé est invisible dans le sens où il a été oublié.
- **Le compte est une assertion** (règle du témoin, pt 1) : le cliquet publie
  combien de tableaux il a inspectés et l'affirme — sinon un motif qui ne
  reconnaîtrait plus `aod-cd-table` serait vert en n'ayant rien vu.
- ⚠️ **Le piège de la 45.4, à ne pas rejouer** : en mode carte,
  `.aod-cd-tablewrap` passe en `overflow:visible` (`dashboard.css:1272`) — donc
  plus rien n'absorbe une colonne large. Un `white-space:nowrap` qui « tenait »
  jusque-là se met à déborder la page. C'est ce qui a fait rougir la porte `rtl` en
  45.4 (35 px en FR, 41 px en AR). Chaque tableau bascule donc **mesuré à 390 px,
  FR et AR**, pas au grep.
- **Non-régression** : les KPI et l'encart d'alertes de Stock, les filtres GET de
  l'Accueil et l'édition inline du stock intacts.

### Étape 46.3 — Le canal de retour de `/gestion` est annoncé (S) ✅

- **Objet** : `toast()` écrit dans une région `role="status" aria-live="polite"`
  **permanente**, créée à l'init de la coque (D5) — jamais un nœud rempli puis
  inséré (Constat 4).
- **Le cliquet** : R3 — le helper partagé possède sa région live, et elle est créée
  **avant** que le texte n'y entre. Un contrôle qui se contenterait de grep
  `role="status"` serait vert sur le défaut exact qu'on corrige : l'ordre est le
  sujet, pas l'attribut.
- **Preuve au rendu** : la porte `noms` vérifie la région dans l'**arbre
  d'accessibilité** de Chrome (CDP), comme la 45.5 l'a fait pour les 12 liens du
  rail — un attribut présent n'est pas une annonce.
- ⚠️ `aria-live="polite"` et non `assertive`, y compris pour les erreurs : le toast
  accompagne une action que l'utilisateur vient de déclencher. `assertive`
  interromprait la lecture en cours pour dire ce qui était attendu.

### Étape 46.4 — Une seule famille de rayons, et un cliquet qui regarde des deux côtés (S/M) ✅

- **Objet** : `--cd-radius`/`--cd-radius-sm` disparaissent au profit de
  `--cd-r-md`/`--cd-r-sm` (D1, 70 usages) ; les six `999px` en dur passent sur
  `--cd-r-pill` ; `--cd-r-lg` trouve ses usages (16px en dur).
- **L'assertion qui rend le renommage sûr** : les valeurs des deux familles sont
  **prouvées identiques** avant le remplacement. C'est ce qui fait de la 46.4 un
  changement **visuellement nul par construction**, et non une refonte à
  reconfronter écran par écran.
- **Le cliquet** : R4 — `test-charte-surfaces.php` gagne le **sens manquant**
  (déclaration → usage). Tout token de `:root` non consommé par un `var()` est un
  orphelin, exactement comme un SVG sans entrée de catalogue.
- ⚠️ **Vérifier AVANT de renommer que `--cd-radius*` n'est lu que par
  `dashboard.css`** : s'il est émis par `css_vars()` ou lu par un autre plugin, le
  renommage casse en silence (le repli d'un `var()` inconnu ne prévient jamais —
  c'est la dette 17.4 elle-même).
- **Portée volontairement limitée** : cette étape ne touche **pas** la typo ni
  l'espacement. Les rayons se traitent seuls parce qu'ils sont un **renommage**
  (mécanique, prouvable), là où la typo est un **arbitrage** (D6/D7).

### Étape 46.5 — Typo et espacement : les correspondances exactes passent aux crans (M) ✅

> 📌 **Cette étape a été RÉDUITE à l'exécution, et scindée.** Elle visait « les 153
> `font-size` et 148 `padding` convergent vers les échelles » (taille L). La mesure
> a séparé deux travaux que ce libellé confondait : **43** occurrences ÉGALES à un
> cran (Δ = 0 px, mécanique, prouvable) et **110** sans cran (Δ ≥ 0,5 px, donc un
> changement visuel sur ~110 sites). Seules les premières sont ici ; les secondes
> sont la **46.5bis**, ci-dessous. Ce n'est pas un abandon : c'est le refus de
> faire passer un arbitrage de design dans une étape dont tout l'argument est
> « visuellement nul par construction ».

- **Objet** : les 153 `font-size` et 148 `padding` en dur convergent vers les
  échelles, **par lots homogènes** (D6) — un lot = une famille de composants, un
  commit, une vérification à l'écran.
- **D7 rendu exigible** : les 14 tailles hors échelle sont soit rapprochées du rung
  le plus proche, soit **conservées avec leur raison en commentaire**. Une valeur
  hors échelle n'est pas interdite ; elle est motivée ou supprimée.
- **Le cliquet** : R5 — plafond **chiffré et décroissant** du nombre de valeurs
  littérales, sur le modèle de `test-decomposition.php` (qui borne le monolithe).
  Un plafond, pas une interdiction : l'objectif est d'empêcher la **remontée**,
  puisque c'est la remontée (10 → 20 tailles) qui a défait la Phase 5.
- ⚠️ **Pourquoi c'est la seule étape sans preuve automatique de non-régression** :
  aucun test ne voit qu'un corps 13 devenu 13,5 a décalé une colonne. Elle se fait
  donc **lot par lot avec capture avant/après**, et c'est la raison de sa taille L
  malgré son apparence de recherche-remplacement.

### Étape 46.5bis — Les 14 tailles sans cran : un arbitrage, pas un `sed` (M) ✅ — *ajoutée à l'exécution*

*Ouverte le 2026-07-30, pendant la 46.5, sur une mesure qui sépare nettement deux
travaux que le plan avait mis dans le même sac.*

**Ce que la 46.5 a livré, et pourquoi elle s'arrête là.** Les `font-size` de
`dashboard.css` se répartissent en deux populations, et une seule est mécanique :

| | Occurrences | Nature |
|---|---|---|
| Tailles **égales** à un cran (11,5 · 13 · 14,5 · 17 · 20 · 26 px) | **43** | Δ = 0 px → conversion **prouvée pixel-identique** (SHA des styles calculés sur 7 003 éléments) |
| Tailles **sans cran** (10 · 10,5 · 11 · 12 · 12,5 · 13,5 · 14 · 15 · 16 · 18 · 19 · 21 · 22 · 28 px) | **110** | Δ ≥ 0,5 px → **changement visuel** sur ~110 sites |

Les 43 sont passées aux crans en 46.5. Les 110 ne peuvent pas suivre le même
chemin : rapprocher `14px` de `--cd-fs-md` (14,5) touche **28 sites**, dont la
quasi-totalité des tableaux ; passer `12px` à 13 en touche 20. Ce sont des
décisions de **design**, pas de conformité — et une phase dont tout l'argument est
« visuellement nul par construction » ne doit pas les prendre en passant.

- **Objet** : arbitrer les 14 tailles, cran par cran, avec capture avant/après.
  Chaque taille est soit rapprochée du cran voisin, soit **conservée avec sa raison
  écrite** (D7). Une valeur hors échelle n'est pas interdite ; elle est motivée.
- **Méthode imposée** : lots homogènes (un lot = une famille de composants, un
  commit), et l'empreinte de styles calculés rejouée à chaque lot — elle ne peut
  plus être identique, donc c'est le **diff** qui est relu, site par site.
- **Le cliquet existe déjà** : R5 de `test-charte-surfaces.php` borne le nombre de
  littéraux. Chaque lot le fait DESCENDRE ; il ne remonte jamais. C'est ce qui rend
  cette étape reprenable sans elle-même dériver.
- ⚠️ **Ce qui serait une faute ici** : convertir au `sed` pour « finir la Phase 5 ».
  Le compte de littéraux tomberait à zéro et le tableau de bord changerait d'aspect
  sans que personne ne l'ait décidé. Le plafond descendrait, la conformité serait
  « atteinte », et on aurait exactement reproduit le geste de la Phase 5 —
  déclarer une intention et laisser le résultat au hasard.

### Étape 46.6 — Cibles tactiles mobiles et peau des jumeaux (M) ✅

- **Objet** : sous 780 px, `.aod-cd-btn`/`.aod-cd-btn-sm` atteignent **44 px**
  (D3, media query — la densité desktop est conservée telle quelle) ; la peau de
  `assignsel`/`leadassignsel` est écrite **une fois** (D4).
- **Les classes comportementales restent distinctes** : leurs binders JS les
  ciblent séparément, et `section-abandoned.js` doit rester no-op sur Commandes.
  On mutualise la peau, pas le comportement — arbitrage 17.6 reconduit.
- **Le cliquet** : R6 — aucune règle de peau n'est écrite deux fois pour les deux
  jumeaux. C'est le cliquet qui **rendait le Constat 1 impossible** (3 des 4
  anneaux cassés étaient sur ces jumeaux) : il ferme la cause, pas le symptôme.
- **Preuve au rendu** : la porte `souris` mesure les hauteurs réelles à 390 px.

### Étape 46.7 — Clôture : i18n, portes, docs (S) ✅

- `make-translations.sh --strict` des deux plugins ; `tests/run.sh` ; portes
  `contraste` (6 accents, accent client restauré), `clavier`, `noms`, `souris`,
  `mouvement`, `rtl`, `assignation`, `abandonnees`.
- MAJ `UX-GESTION-REFONTE.md` (§2.2 : la spec des tokens cesse d'être un vœu),
  `ARCHITECTURE.md` §3 « Couche UI », §C.3, `TEMPLATE.md` §9.
- **Rouge pré-existant à ne pas avaler** : la porte `abandonnees` est à 66 ✓ / 1 ✗
  depuis la 45.4, consigné en **D31**. Il doit être re-mesuré et rester consigné,
  pas absorbé dans le bilan de cette phase.

---

## Phase 47 — Le geste du confirmateur : l'envoi cesse d'être un effet de bord

> Verdict et mesures : **§A.23**. Dix demandes de l'owner du 2026-08-01, dont une
> (le point 6) **arrivée vide et réservée** en 47.8.
>
> 🔴 **Cette phase n'est PAS du chrome**, contrairement aux 45 et 46. Elle touche
> un hook de statut WooCommerce, la charge utile envoyée aux transporteurs, deux
> métas de commande nouvelles et un handler AJAX nouveau. Son critère de
> non-régression n'est donc pas « aucun schéma ne bouge » mais : **le parcours
> acheteur est intact** (formulaire COD, panier, 3 pages publiques — zéro ligne),
> et **aucune capability n'est créée**.
>
> 🔴 **Elle solde `D27`** — non en la corrigeant, mais en supprimant son objet
> (cf. A.23). À retirer de §C.5 à la clôture, avec la raison.

### Ordre — la dépendance, pas le confort

47.1 avant 47.2 : on ne peut pas confirmer l'envoi vers un transporteur tant que
la commande n'en porte pas un. 47.2 avant 47.3 : la fenêtre d'envoi est le seul
endroit où l'avertissement « ce transporteur ne reçoit pas la note » (D3) a un
sens — la note n'existe qu'ensuite. Le reste (47.4 → 47.7) est indépendant et
peut se livrer dans n'importe quel ordre.

### Étape 47.1 — Le transporteur devient un choix par commande (M) ✅

- Meta de commande `_aod_ship_carrier_choice` (HPOS : `update_meta_data()`,
  jamais `postmeta`). **Vide = repli sur le réglage global** `aod_shipping_auto['carrier']` —
  aucun clone existant ne change de comportement le jour de la mise à jour.
- Colonne « Transporteur » dans la liste Commandes : sélecteur par ligne, limité
  aux transporteurs **configurés** (`AOD_Shipping::configured()`), gérant **et**
  confirmateur (D2).
- Action groupée « Transporteur → … » dans la barre de sélection, à côté de
  « Assigner à… ». Elle suit le patron de `ajax_orders_bulk_assign()` — y compris
  `disabled` à sélection vide (garantie 45.2) et la case d'écrasement si le
  comportement le demande.
- Handler `aod_cd_order_carrier` + `aod_cd_orders_bulk_carrier` : nonce
  `aod_cd` + `AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' )` + `order_in_scope()`.
  **Refuser** un transporteur non configuré plutôt que retomber sur le défaut :
  re-router un colis vers une société non choisie est le pire des replis (même
  raisonnement que `build_order_lines()` sur un `product_id` inconnu).
- 🚨 **Ne pas toucher** `auto_settings()` : le réglage global reste le défaut de
  la boutique. Deux sources pour une même question, c'est la dette qu'on vient de
  passer trois phases à fermer — d'où **une seule** fonction de résolution,
  `AOD_Shipping::carrier_for( $order )`, appelée par TOUS les chemins d'envoi.

### Étape 47.2 — L'envoi quitte la confirmation et devient un geste nommé (L) ✅

- **Débrancher** `add_action( 'woocommerce_order_status_aod-confirmed', 'auto_send' )`
  (`class-aod-shipping.php:83`). Le statut « Confirmée » ne déclenche plus aucun
  appel réseau.
- L'icône camion de la colonne « Suivi » (`ship_cell_html()`,
  `class-aod-cd-section-orders.php:642`) devient un **bouton nommé** — pas une
  icône cliquable muette : c'est la leçon 45.3 (« une affordance cachée n'en est
  pas une », nom accessible, cible tactile).
- Fenêtre de confirmation d'envoi (modale maison, jamais `window.confirm`) :
  liste des commandes visées, transporteur de chacune, **cardinal du lot** (D4),
  et l'avertissement D3 quand le transporteur ne porte pas de champ note.
  Sélection multiple prise en charge : c'est le cas d'usage principal.
- Handler `aod_cd_ship_send` côté dashboard, qui délègue au goulot **existant**
  `AOD_Shipping::send_parcel()` — verrou 31.4 et relecture après verrou inchangés.
  ⚠️ **Ne pas recréer un second chemin d'envoi** : le cliquet
  `test-verrou-envoi.php` R3 exige « un SEUL appel à `create_parcel()` dans
  `AOD_Shipping` » et rougira, à raison, si on l'oublie.
- ⚠️ **Le lot est séquentiel, borné, et rend compte ligne à ligne.** N commandes
  = N appels réseau : sans borne, « tout cocher » sur 200 commandes rejoue
  l'auto-DoS de la 32.4. Plafond explicite + compte rendu par commande
  (envoyée / refusée / déjà expédiée), jamais un « c'est fait » global.
- **Non-régression à écrire** : une commande déjà expédiée (`META_TRACKING` non
  vide) reste refusée, et le refus de concurrence n'est toujours pas noté comme
  une panne (`is_concurrency_error()`, 31.4).

### Étape 47.3 — La note du confirmateur, et son voyage jusqu'au livreur (M) ✅

- **Retirer** la colonne « Note » de la ligne (`:464-467`) et son bouton.
- Zone de texte unique dans `render_order_edit_form()`, enregistrée avec le reste
  du formulaire (`ajax_order_save_info`) — meta `_aod_cd_confirmer_note`.
  `sanitize_textarea_field()`, jamais `wp_kses_post()` : cette chaîne part dans une
  API tierce, pas dans une page.
- `ajax_order_note()` et l'écriture dans l'historique Woo : **supprimés**. La
  capability `aod_cap_confirm_orders` couvre déjà l'édition.
- Transmission : `'remarque'` (EcoTrack, Noest) et `'note_to_driver'` (Maystro)
  cessent d'être vides. **26 transporteurs sur 29** couverts sans ajouter un champ.
- Yalidine / Yalitec / E-com Delivery : rien n'est bricolé, l'écran prévient (D3).
- 🚨 **Borner la longueur avant l'envoi.** Une note de 4 000 caractères dans une
  API qui en accepte 255 échoue côté transporteur, donc **après** le verrou et
  au milieu du lot. Clipper à la source, et le dire dans le champ.

### Étape 47.4 — La ligne Commandes et la modale qui ne se dérobe plus (S) ✅

- Nom · téléphone · **Détail** sur une seule ligne (Constat 4). « Appeler » et
  « WhatsApp » restent dans le même rang. Contrainte dure : la cellule doit tenir
  à **390 px** en FR **et** en AR — la porte `rtl` mesure chaque tableau contre son
  conteneur depuis la 46.7, et `D32` a montré ce que coûte un empilement contraint.
- Le fond de la modale ne ferme plus (`data-close` retiré du
  `.aod-cd-modal-backdrop` **pour cette modale**). ⚠️ `Échap` et la croix
  **restent** : la porte `clavier` verrait une modale sans issue.
- ⚠️ **Le markup de la modale est PARTAGÉ** (`class-aod-cd-dashboard.php:521-530`,
  utilisé aussi par `section-tracking.js`). Retirer `data-close` du backdrop dans
  le HTML commun changerait le comportement de la modale de Suivi **sans que la
  demande le dise**. Le retrait se fait donc côté JS, à l'ouverture, ou par un
  attribut porté par la modale qui le veut.

> 🚨 **CORRECTION MESURÉE (2026-08-01, 47.4) — « le retrait se fait côté JS, à
> l'ouverture » est INERTE.** Éprouvé au navigateur : `data-close` retiré du fond
> **à chaud**, après le démarrage, puis clic sur le fond → **la modale se ferme
> quand même**. Les deux binders posaient leur écouteur sur l'**élément**, par
> référence directe ; l'attribut n'a servi qu'à le trouver **une fois**, au boot.
> Le retirer ensuite ne défait rien.
>
> → Le garde est donc **au clic**, par **délégation** sur la modale — ce qui règle
> du même coup la leçon 47.2 (un écouteur posé sur un nœud meurt avec le nœud dès
> qu'un `innerHTML` le remplace, et le bouton reste *cliquable et inerte*).
> `data-close` **reste** dans le markup partagé, comme l'exige le point ci-dessus.
>
> 🚨 **Et le partage allait plus loin que « le markup ».** `bindOrderDetail` et
> `bindTracking` posaient **chacun** leur `open`/`close` et leur écouteur `Échap`
> sur le **même** élément, avec des corps quasi identiques — et tous les modules
> tournent sur **toutes** les pages, donc deux fermetures et deux `Échap` sur
> `/gestion/tracking`. Y ajouter la politique du fond en double aurait été la
> dérive que la Phase 46 a passé huit étapes à corriger. D'où
> **`core.sharedModal()`** dans la coque : un seul propriétaire, la politique du
> fond **en paramètre** (`section-orders` la désarme, `section-tracking` la garde).
> Plafond de `dashboard.js` remonté **696 → 752** avec sa justification écrite
> (même cas que `toastRegion()` en 46.3 : un élément unique que plusieurs modules
> se partagent ne peut vivre que dans la coque).

### Étape 47.5 — Les stats du confirmateur cessent de montrer la boutique (S) ✅

- `tab_confirmation()` (`:1900-1913`) : quand l'utilisateur n'est pas gérant, les
  trois cartes lisent **sa** ligne (`$d['rows'][ get_current_user_id() ]`), comme
  le tableau « Par confirmateur » le fait déjà (`:1949-1952`). Même chose sur la
  période précédente (`$p`), sans quoi le delta comparerait deux périmètres.
- « Commandes reçues » (`:1935`) : à trancher à l'implémentation — le total reçu
  n'est pas assignable de la même façon que le confirmé. **Mesurer avant de
  décider**, et écrire la raison retenue.
- 🚨 **`confirmation_data()` reste intouchée** (D5) : la colonne « Part » a besoin
  du total global. Un cliquet doit garder cette asymétrie, sinon la prochaine
  passe « nettoiera » le scope et cassera « Part » en silence.

> ✅ **SOLDÉE le 2026-08-01** (commits `9c100cf`, `4acde2b`, + docs). Trois
> corrections à ce qui précède, écrites ici plutôt que réécrites au-dessus :
>
> **1. La question ouverte est tranchée : « Commandes reçues » RESTE globale.**
> Mesuré sur ce clone : **33 commandes sur 33** portent `_aod_assigned_confirmer`,
> **1 seule** porte `_aod_confirmed_by`. Ce n'est pas le volume qui décide, c'est
> le **temps**. `_aod_confirmed_by` est **figé** — `test-assignation-accueil.php`
> (42.3bis) l'exige explicitement : une commande confirmée par A puis réassignée à
> B doit **rester** dans la ligne de A. `_aod_assigned_confirmer`, lui, est un
> **état courant** qui bouge à chaque réassignation, y compris sur des périodes
> closes. Un « reçues par moi » serait donc un **numérateur figé sur un
> dénominateur mobile** : pour A, *confirmées = 1, reçues = 0* → la carte
> « Confirmées » calcule `$conf_n / $total`, soit une **division par zéro** ; et
> pour B, *confirmées = 0, reçues = 1* alors qu'il n'a jamais vu cette commande.
> Le plan pressentait ce défaut en écrivant « pas assignable de la même façon » ;
> la mesure dit **par quel mécanisme**. La maison avait d'ailleurs déjà tranché ce
> cas avec la même raison, pour le filtre du gérant (docblock de
> `tab_confirmation()` : « un taux 100 % mécanique serait trompeur »).
> → Conséquence produit : **l'écran le DIT**. Une note conditionnelle
> (`.aod-cd-note-scope-conf`) explique au non-gérant que le pourcentage se
> rapporte aux commandes reçues par la **boutique**. Sans ce mot, « Confirmées :
> 3 (9 %) » se lit « 91 % de mes commandes m'ont échappé ». Chaîne neuve,
> traduite en AR à la main.
>
> **2. 🚨 Le cliquet réclamé sur l'asymétrie EXISTE DÉJÀ — depuis la 42.3bis.**
> `tests/unit/test-assignation-accueil.php`, groupe *« confirmation_data() :
> jamais filtrée par assignation (à dessein) »* (`:196-210`). Il a été **éprouvé
> au rouge** ici (mutation M3) : scoper `confirmation_data()` par
> `assignment_scope()` le fait rougir. **Ne pas le réécrire.** C'est la 5ᵉ
> affirmation de la Phase 47 démentie par la mesure, et la première qui l'est
> *par excès de prudence* plutôt que par erreur. Ce qui manquait n'était pas là :
> **aucun cliquet ne regardait `tab_confirmation()`**, c'est-à-dire le RENDU,
> c'est-à-dire l'endroit exact du défaut. D'où
> `tests/unit/test-stats-confirmateur.php` (R0→R4), qui **rend** l'onglet sous
> trois identités — un cliquet qui aurait interrogé la donnée serait resté vert
> sur le défaut, puisque la donnée était correcte.
>
> **3. La porte `confirmateur` aurait été CREUSE sans semis.** Au repos, le compte
> B n'a rien confirmé (**0**) et la boutique en compte **1** : un contrôle « B voit
> 0 » passe aussi bien sur un rendu cassé que sur un rendu correct. Le seed
> (`recette-seed-phase22.php`) lui attribue donc **3** confirmations et **5** à
> quelqu'un d'autre, purgées en sortie et marquées. Mesuré à l'écran, en compte B :
> **reçues 41 · confirmées boutique 9 · confirmées B 3**. 36 → **45 contrôles**,
> les 36 précédents inchangés.
>
> 🔎 **Et un défaut du cliquet, trouvé en éprouvant M3 plutôt qu'en la supposant** :
> R1 restait **verte** sur la mutation interdite, parce que la clé de `cached()`
> porte plage + produit + périmètre produits — **jamais l'identité ni
> l'assignation**. Le gérant ayant déjà lu cette plage, l'appel sous confA rendait
> SON transient : aucune requête ne partait, donc rien à scoper. R1 lit désormais
> une plage élargie d'un jour (mêmes témoins, clé neuve). *Une règle qui lit un
> cache n'éprouve pas le code qui l'a rempli.*

### Étape 47.6 — Produits : la description respire, les images sont bornées (S/M) ✅

- Éditeur riche à hauteur adaptative (Constat 7) — via le mécanisme de TinyMCE,
  avec un **plafond** : sans lui, une longue description pousse « Enregistrer »
  hors de l'écran.
- Borne **5 Mo côté serveur** dans `AOD_CD_Upload` (D6), message d'erreur
  explicite et traduit, contrôle JS en complément. Annoncer la limite **dans le
  champ**, avant l'envoi — pas seulement dans l'erreur.

> ✅ **SOLDÉE le 2026-08-01** (`74ac990`, `4533425`, `2623344`, + docs). Quatre
> corrections à ce qui précède, écrites ici plutôt que réécrites au-dessus :
>
> **1. Le plugin TinyMCE s'appelle `wpautoresize`, pas `autoresize`.** Mesuré
> dans le conteneur : `wp-includes/js/tinymce/plugins/` ne contient que le fork
> WordPress. Déclarer le nom officiel ferait chercher un fichier inexistant. Et
> il porte un **interrupteur**, `wp_autoresize_on`, sans lequel tout le plugin
> est conditionné à faux (`plugin.js:160,171`) : il se charge et **ne fait
> rien**. Un test statique qui aurait cherché la chaîne « wpautoresize » dans le
> fichier JS serait donc resté **vert sur un éditeur inerte** — c'est pourquoi
> l'étape a écrit une PORTE et pas un cliquet. Avant : **132 px d'iframe** pour
> 216 comme pour 2 215 caractères. Après : **132 → 416 → 500 px**, FR et AR.
>
> **2. 🚨 Le Constat 8 sous-estimait son propre sujet, et la borne SEULE aurait
> été un défaut.** Sur les **six** sites d'appel de `AOD_CD_Upload::handle()`,
> **deux** — la galerie (`:1580`) et les photos d'options (`:1428`) — testaient
> `! is_wp_error()` **sans `else`** : une image refusée disparaissait sans un
> mot. Poser une borne au-dessus de ça transformait « format refusé, en
> silence » en « photo trop lourde, en silence », précisément là où l'on dépose
> plusieurs photos d'un coup et où l'on ne recompte pas. Les deux comptent
> désormais leurs refus, qui remontent dans le `warning` **déjà existant** ;
> `build_warnings()` est extrait pour que deux avertissements se **joignent** au
> lieu de s'écraser.
>
> **3. « 5 Mo » n'est pas la limite : `min( 5 Mo, wp_max_upload_size() )` l'est.**
> Mesuré sur cette pile **au 2026-08-01, avant la 47.6bis** : `upload_max_filesize
> = 2M`. PHP coupe **avant** WordPress — `error = UPLOAD_ERR_INI_SIZE`, `tmp_name`
> vide et **`size` vaut 0**. Un contrôle écrit sur la seule taille serait donc
> **vert au test et inerte en production** sur tout hébergeur mutualisé sous
> 5 Mo. Le goulot traite les **deux** cas. Et l'écran annonce la limite
> **effective** : promettre 5 Mo là où PHP en accepte 2 serait un mensonge
> d'écran, la panne la plus coûteuse de ce dépôt parce qu'elle ne se voit pas.
> 🔎 **Un effet de bord trouvé en chemin** : le champ vidéo d'une page de vente
> annonçait déjà `wp_max_upload_size()` — une note **juste** que la nouvelle borne
> rendait **fausse** sur tout hébergeur au-dessus de 5 Mo. Une seule source, celle
> qui refuse.
>
> ⚠️ **CE PARAGRAPHE EST PÉRIMÉ SUR SON CHIFFRE DEPUIS LA 47.6bis — corrigé ici
> plutôt que réécrit.** Le raisonnement reste **entièrement vrai** (la limite est
> bien le `min()`, et le chemin `UPLOAD_ERR_*` reste indispensable chez un
> hébergeur strict). Mais **`upload_max_filesize` vaut désormais 8M** sur cette
> pile, posé par `docker/php-aod.ini` : la limite effective est **5 Mo**, la borne
> applicative. 🚨 **Et la 47.6 s'est arrêtée trop tôt.** Constater que PHP
> plafonnait à 2 Mo et se contenter de le **dire** n'était pas la demande : la
> demande était que 5 Mo **passent**. L'owner l'a découvert en essayant d'envoyer
> une photo de 2,8 Mo. *Annoncer honnêtement une limite qu'on aurait pu lever,
> c'est décrire le problème au lieu de le résoudre.* Cf. Étape 47.6bis.
>
> **4. ⚠️ Le plafond de `class-aod-cd-dashboard.php` a dû monter (1072 → 1077).**
> Le fichier était **exactement** à son plafond, et le second garde-fou interdit
> aussi de le descendre. `maxUpload` relève du cas `maxLotEnvoi` de la 47.2 : une
> borne **lue à sa source** plutôt que recopiée en JS. Arbitrage 47.4 — on monte
> en écrivant pourquoi, on ne taille jamais un commentaire pour rentrer sous un
> compteur. Deux censures de `test-phpcs-ignore.php` ont aussi rougi
> (`EscapeOutput` 148 → 154, `NonceVerification` 17 → 18) : hausses justifiées
> **dans le fichier de test**, comme la 23.1 l'exige.
>
> 🔎 **Et une affirmation de mon propre correctif a été démentie par le
> red-proof.** J'avais posé `autoresize_min_height: 132` en affirmant, mesure à
> l'appui, que sans lui l'éditeur s'ouvrait collé au plafond. Le retirer ne fait
> **rien** rougir : le produit témoin portait une description **arabe** de 2 124
> caractères et `.aod-cd-lp-langs` est une grille `1fr 1fr` — la case FR hérite
> de la hauteur de l'AR. *Je mesurais le contenu de l'autre case.* Le défaut du
> plugin (`rows="6"`) donne exactement les 132 px d'avant ; la ligne est partie,
> et la porte garde le **résultat** plutôt que le réglage.

### Étape 47.7 — Les 13 dialogues du navigateur passent à la fenêtre maison (M) ✅

- Les **12 `window.confirm()` + 1 `window.alert()`** des 7 fichiers (Constat 10)
  passent à `openModal()` / `textPromptModal()`.
  ⚠️ **DEUX NOMS FAUX, corrigés ici plutôt que réécrits** (47.7) : `openModal()`
  **n'existe pas** — la primitive s'appelle **`openOverlayModal()`**
  (`dashboard.js:222`) ; et **`textPromptModal()` n'existe plus**, supprimée en
  47.3 avec son unique appelant. La 47.7 bâtit **`confirmModal()`** SUR
  `openOverlayModal`.
- ⚠️ **Ce n'est pas un remplacement mécanique** : `window.confirm()` est
  **bloquant** et rend un booléen ; la modale maison est asynchrone. Chaque site
  d'appel doit passer en continuation — un `sed` produirait 13 gardes qui laissent
  toutes passer.
- 🚨 **Cliquet obligatoire, sinon la règle ne tient pas** (leçon 46) : un test
  statique qui compte **zéro** `window.confirm|alert|prompt` dans
  `plugins-dev/**/assets/js/`, avec le **recensement** des modales maison — pour
  qu'un futur ajout rougisse au lieu de se glisser.

### Étape 47.8 — ABANDONNÉE (demande 6 de l'owner, retirée le 2026-08-01)

Sans objet : la demande est arrivée tronquée et l'owner l'a retirée (« on
l'oublie »). **Le numéro n'est pas réattribué et 47.9 ne recule pas** — une
renumérotation romprait le lien avec les lignes §C.3 et les en-têtes déjà écrits,
pour économiser un trou qui ne coûte rien. Cf. Constat 6 d'A.23.

### Étape 47.9 — Clôture : i18n, cliquets, portes, docs (S) ✅

- `make-translations.sh --strict` des deux plugins ; `tests/run.sh`.
- Portes : `assignation`, `abandonnees`, `suivi`, `confirmateur`, `rtl`,
  `clavier`, `noms`, `contraste`, `public` (non-régression acheteur), `cod`.
- **Nouvelle porte à écrire** : l'envoi n'est plus automatique — aucune porte
  existante ne le prouve. Elle doit montrer, au navigateur, qu'une commande
  passée à « Confirmée » **ne crée aucun colis**, puis qu'un clic sur le camion
  en crée un, une seule fois.
- Docs : `ARCHITECTURE.md` (deux métas nouvelles, le handler d'envoi, la note
  transmise), `TEMPLATE.md` §9, §C.3, et **retrait de `D27` de §C.5 avec sa raison**.

---

## Phase 48 — L'Accueil du confirmateur dit de qui il parle

> Verdict et mesures : **§A.24**. Constat de l'owner du 2026-08-02 sur un compte
> réel (12 commandes assignées, 33 affichées).
>
> 🟢 **Phase de CHROME PUR**, comme les 45 et 46 : aucun handler AJAX, aucune
> capability, aucun schéma de données, aucune requête nouvelle, aucune option.
> Le compteur de la 48.2 se calcule dans la boucle qui parcourt **déjà** chaque
> commande de la période.
>
> 🚨 **Deux non-régressions gouvernent toute la phase**, et elles ne se négocient
> pas étape par étape :
> 1. **Le rendu du gérant ne change pas.** Tout ce qui est ajouté est conditionné
>    au lecteur non-gérant (ou au gérant qui filtre explicitement un
>    confirmateur), jamais au cas nominal.
> 2. **Le taux et la colonne « Part » gardent leur dénominateur BOUTIQUE.** C'est
>    la garantie de la 47.5 (§A.24, Constat 3) : un dénominateur mobile sous un
>    numérateur figé produit une division par zéro sur un cas courant. Cette
>    phase existe pour rendre ce dénominateur **lisible**, jamais pour le changer.
>
> ⚠️ **Elle touche un écran déjà corrigé une fois.** La 47.5 a réparé les cartes
> « Confirmées » et « Délai » ; elle a **laissé « Reçues » globale à dessein**.
> Rouvrir cet écran sans lire `class-aod-cd-section-home.php:1907-1921` et
> l'assertion D-3 de `tests/unit/test-stats-confirmateur.php`, c'est refaire le
> chemin dans l'autre sens.

### Ordre — la dépendance, pas le confort

48.1 avant 48.2, **strictement**. La 48.1 fait dire son périmètre au chiffre
existant ; la 48.2 en ajoute un second à côté. Dans l'ordre inverse, on poserait
« Assignées à moi : 12 » à côté d'un « Commandes reçues : 33 » toujours muet sur
son périmètre — deux nombres contradictoires sans clé de lecture, c'est-à-dire le
défaut de §A.24 **aggravé** le temps d'une étape. 48.3 clôt.

### Étape 48.1 — « Commandes reçues » dit son périmètre (S) ✅

- Libellé de la carte (`class-aod-cd-section-home.php:1977`) conditionné au
  lecteur : le gérant garde **exactement** la chaîne actuelle, le non-gérant lit
  un libellé qui nomme la boutique (décision D-1 de §A.24).
- Note de périmètre (`:2034`) **remontée au niveau des cartes**, où le malentendu
  naît — elle est aujourd'hui sous le tableau, donc hors du premier écran sur
  mobile (D-3). Son texte reste vrai : c'est sa **place** qui change.
- ⚠️ **Ne pas dupliquer la chaîne.** Deux libellés pour une carte, c'est deux
  chaînes à traduire et deux occasions de diverger — le mode de dérive que la
  Phase 46 a passé huit étapes à refermer. Un seul site de décision, comme
  `$row_uid` l'est déjà pour les trois cartes.
- **Non-régression à écrire** : l'assertion D-3 du cliquet vérifie le **nombre**
  (`'>' . $recues . '<'`), pas le libellé — elle doit rester verte **sans être
  touchée**. Si elle rougit, c'est que le chiffre a bougé, donc que l'étape a
  débordé de son périmètre.

### Étape 48.2 — La carte « Commandes assignées » (M) ✅

- 4ᵉ carte, rendue **uniquement quand `$row_uid` est non nul** — donc au
  confirmateur connecté et au gérant qui filtre un confirmateur (D-2). Le gérant
  sans filtre garde ses 4 cartes actuelles, dont « Confirmées non attribuées ».
- **Un compteur, jamais un ratio.** Aucun pourcentage n'est bâti dessus : c'est
  précisément ce qui le rend sûr (§A.24, Constat 3).
- 🚨 **Le calcul va dans le payload EN CACHE, sous forme de carte
  `uid => n` — jamais sous forme d'un total dépendant du lecteur.** La clé de
  cache de `confirmation_data()` (`:1790`) n'intègre **ni le rôle ni le lecteur**,
  à dessein : la donnée reste complète, la visibilité se décide au rendu. Ajouter
  un nombre qui dépend de l'utilisateur courant **dans** cet agrégat imposerait
  une clé par utilisateur — et le jour où on l'oublierait, deux lecteurs se
  serviraient la vue l'un de l'autre. Même patron que `rows` (`:1811`), qui est
  déjà une ventilation par confirmateur lue au rendu.
- Coût nul en requêtes : la closure de `each_filtered_order()` (`:1801`) visite
  déjà chaque commande de la période ; il s'agit d'y lire une méta de plus.
- ⚠️ **`0` n'est pas une absence ici.** `_aod_assigned_confirmer` absente ou à `0`
  = commande non assignée (invariant Phase 42). La ventilation ne doit pas créer
  une entrée `0` qui serait ensuite lue comme « assignées à personne = les
  miennes » — le gérant a déjà « Non attribuées » pour un autre concept
  (`_aod_confirmed_by`), et **les confondre ferait dire à l'écran l'inverse du
  vrai**.
- **Non-régression à écrire** : un confirmateur sans aucune commande assignée
  lit **0**, pas le total de la boutique — c'est le témoin symétrique de celui
  que la 47.5 a posé pour « Confirmées » (le confirmateur B qui n'a rien
  confirmé).

### Étape 48.3 — Clôture : i18n, cliquet, porte, docs (S) ✅

- **i18n** : `make-translations.sh` des deux plugins, AR traduit **à la main**
  (glossaire du catalogue existant), `--strict` vert ×2.
- **RTL + mobile** : la 4ᵉ carte à 390 px et en AR — la grille de KPI passe de 3
  à 4 cartes pour ce lecteur, c'est le seul risque de mise en page de la phase.
- **Cliquet** : étendre `tests/unit/test-stats-confirmateur.php` — il tient déjà
  l'onglet **rendu** sous trois identités (gérant / confirmateur A / confirmateur
  B), c'est exactement le harnais qu'il faut. Y ajouter : la carte présente au
  non-gérant et **absente** au gérant sans filtre, le compteur égal aux commandes
  assignées du témoin, le **0** du confirmateur sans assignation, et la
  permanence du dénominateur boutique. L'assertion D-3 existante n'est **pas**
  réécrite : si elle doit évoluer, c'est **avec sa raison**, jamais pour la faire
  taire.
- 🚨 **Éprouver au rouge**, et vérifier que chaque mutation ne fait rougir que
  l'assertion visée (règle du témoin, §C.2, point 5).
- **Porte** : `bash tools/recette/run.sh confirmateur` — la seule qui ouvre cet
  écran en **vrai confirmateur**. Elle doit prouver au navigateur ce qu'un
  cliquet statique ne peut pas : que le chiffre lu par un confirmateur connecté
  est le sien.
- **Docs** : `ARCHITECTURE.md` §3 (le tableau « Qui voit quoi dans l'onglet
  Confirmation » gagne une ligne), `TEMPLATE.md` §9, §C.3.


### Étape 48.4 — La carte en tête, et l'en-tête qui dit « vos » (S) ✅ — *ajoutée à l'exécution*

> 🚨 **Née d'un retour de l'owner sur la livraison des 48.1-48.3 : « rien n'est
> corrigé ».** Il avait raison, et c'est la leçon de la phase.

- La carte « Commandes assignées » était livrée **en 4ᵉ position**. Exacte,
  gardée par un cliquet et une porte — et **inutile** : le premier nombre qu'un
  confirmateur lisait restait celui de la boutique. *Ajouter la bonne
  information ne corrige rien tant qu'elle arrive après celle qui trompe :
  l'ordre de lecture fait partie du message.*
- Le sous-titre annonçait **« Vue d'ensemble de la boutique »** sous
  « Bonjour <prénom> », à quelqu'un dont tout le reste de l'espace est restreint
  à son assignation. C'était l'autre moitié du contresens — deux lignes au-dessus
  de la carte qui venait de dire son périmètre.
- ⚠️ **Aucun calcul ne bouge** : « Reçues », « Confirmées », « Part » et le taux
  gardent leurs valeurs et leurs dénominateurs (D-4 intact). Rien dans ce
  raisonnement n'imposait de mettre la carte en dernier — c'est une prudence
  appliquée à la mise en page alors qu'elle ne portait que sur l'arithmétique.
- Gardé : le cliquet asserte que la carte **précède** « Commandes reçues ». Sans
  lui, un futur `$cards[] = …` la renverrait en queue **sans faire bouger une
  assertion** — toujours rendue, toujours juste, de nouveau sans effet. La porte
  lit désormais les cartes **par libellé** et non par index : un index est une
  hypothèse sur la mise en page, un libellé est le contrat.

### Étape 48.5 — Une assignation invalide le cache (S) ✅ — *ajoutée à l'exécution*

> 🚨 **Née d'un second retour de l'owner, sur la carte livrée en 48.4 :
> « je viens d'assigner 2 commandes à Bilal, 12 + 2 = 14, et la carte affiche
> 12 ».** Il avait raison une seconde fois — et le défaut n'était pas dans la
> carte, il était sous elle.

- **Mesuré avant de conclure** : 14 commandes portaient
  `_aod_assigned_confirmer = 37` en base, **toutes dans la fenêtre de 30 jours**.
  La période n'était donc pas en cause. Reproduit sur le clone en chauffant le
  cache puis en assignant : **base = 15, écran = 14**.
- **La cause.** `confirmation_data()` passe par `cached()` (transient, TTL
  5 min). Le cache était invalidé par quatre hooks — création, changement de
  statut, corbeille, suppression. Une assignation n'écrit qu'une **méta** : elle
  n'appartient à aucun des quatre. Le commentaire au-dessus de ces quatre lignes,
  lui, promettait déjà « **toute** écriture de commande » : *le code tenait
  quatre événements là où son commentaire annonçait une classe entière.*
- **Ce que ça donnait.** Le gérant assigne, le confirmateur regarde, et le seul
  chiffre de son écran qui parle **de lui** lui donne tort — pendant cinq
  minutes, sans que rien ne le signale, et en se corrigeant tout seul avant
  qu'on puisse l'attraper. C'est le pire profil de défaut : *intermittent, muet,
  et auto-cicatrisant.*
- **Le correctif est un hook, pas cinq.** `woocommerce_update_order` part de
  `$order->save()`, voit donc les métas, et il est émis par les **deux** magasins
  (HPOS `OrdersTableDataStore` **et** CPT hérité) avec la même signature
  `$order_id` que les quatre autres — vérifié dans la source de WooCommerce, pas
  supposé. ⚠️ **Pourquoi pas sur les points d'écriture ?** Parce qu'il y en a
  **cinq** (assignation manuelle, lot, tourniquet, conversion de prospect,
  dé-assignation) : une règle répartie sur cinq sites ne survit pas au sixième.
  C'est exactement la dérive des Phases 44 et 46 — *un bon motif présent dans le
  dépôt qui n'atteint pas son jumeau.*
- **Les quatre hooks historiques restent** : la création et les suppressions ne
  passent pas par `update`. Un bump en trop ne coûte qu'un recalcul ; un bump
  manquant coûte un chiffre faux.
- ✅ **Bénéfice non demandé mais réel** : les trois autres agrégats cachés de
  l'Accueil dépendaient des mêmes quatre hooks. Toute écriture de méta de
  commande les rafraîchit désormais aussi.
- **Gardé** — `test-stats-confirmateur.php` **R6** : il **chauffe** le cache,
  assigne par le code de production (`apply_assignment()`, extraite du handler
  AJAX précisément pour être appelable ainsi), puis relit. ⚠️ **Jamais un
  `has_action()`** : décrire un branchement, c'est décrire l'état au lieu de le
  provoquer — et ça reste vert le jour où le hook cesse d'être émis sur ce
  chemin.
- 🚨 **Deux enseignements du red-proof, tous deux payés sur cette étape :**
  1. Le premier jet visait **B** comme assigné. `apply_assignment()` l'a
     **refusé** — et à juste titre : il valide sa cible en confiance zéro, or B
     est privé de `aod_cap_confirm_orders` **exprès** (il ne confirme rien, c'est
     son rôle dans R5). Sans le témoin « `apply_assignment()` a bien écrit », les
     deux assertions suivantes auraient mesuré un **non-événement**. *Un témoin
     qui rougit à la place d'une assertion vient de faire son travail.*
  2. L'assertion jumelle « la dé-assignation redescend » était **verte au
     rouge** : hook retiré, la chaîne casse dès l'ajout, le cache reste figé sur
     la valeur d'avant — qui est précisément l'attendu du retour. *Vraie par
     coïncidence de nombres.* Elle est doublée par l'**empreinte de cache**, qui
     ne peut pas coïncider : elle doit changer à chaque écriture. Le jumeau tient
     désormais **seul**.
---

# Partie C — Pilotage

## C.1 Dépendances entre phases

```
Phase 1 (rôles + traçabilité)
  ├─→ Phase 2 (accueil stats)      [2.4 exige 1.1 ; onglets exigent 1.2-1.3]
  ├─→ Phase 3 (page stock)         [exige aod_cap_stock : 1.2-1.3]
  └─→ Phase 4 (import tarifs)      [exige aod_cap_shipping_prices : 1.2-1.3]
Phases 2, 3, 4 : indépendantes entre elles (parallélisables après la Phase 1).
Phase 5 (refonte UI/UX) : transverse, s'appuie sur l'existant des Phases 1-4 ;
  interne à la phase, 5.1 → 5.2 → 5.3 précèdent les étapes de pages (5.4-5.7).
Phase 6 (landing produit) : indépendante des Phases 1-4 (aucune nouvelle
  capability, aucune donnée de commande). Elle s'appuie en revanche sur la
  Phase 5 : son éditeur réutilise les tokens, les composants et le socle a11y
  de `/gestion` — la faire avant aurait produit un écran hors design system.
  Interne à la phase : 6.1 (données + rendu) → 6.2 (éditeur) précèdent les
  étapes de blocs (6.3, 6.4), parallélisables entre elles ; 6.6 clôt.
Phase 7 (contenu produit bilingue) : dépend de la Phase 6 (infra { fr, ar }) et
  de la Phase 5 (design system de la saisie AR). Interne à la phase : 7.0
  (extraction, refactor pur) puis 7.1 (socle) précèdent les étapes de contenu
  (7.2 titre/desc, 7.3 arguments, 7.4 variantes, 7.5 catégories), parallélisables
  entre elles ; 7.6 clôt. 7.2 et 7.5 portent le risque « filtres cœur ».
Phase 8 (description riche) : dépend de la Phase 7 (couple description { fr, ar }
  de la 7.2 + rendu AR via the_content) — à lancer après la 7.6. Interne : 8.1
  (éditeur) précède 8.2 (photos) et 8.3 (vidéo), parallélisables entre elles ;
  8.4 clôt.
Phase 9 (catégories sur l'accueil) : INDÉPENDANTE des Phases 6-8 (ni landing ni
  description). S'appuie sur la Phase 5 (design system de la ligne de catégorie)
  et sur la 7.5 (filtre get_term à préserver). Interne : 9.1 (données + UI /gestion)
  précède 9.2 (rendu accueil) ; 9.3 clôt.
Phase 10 (API Conversion Meta / CAPI) : INDÉPENDANTE (touche Pixels + le hook
  aod_cod_order_created ; aucune dépendance de phase). Interne : 10.1 (token + envoi)
  précède 10.2 (test/robustesse) ; 10.3 clôt.
Phase 11 (commandes abandonnées) : INDÉPENDANTE côté données (back-end leads déjà là) ;
  réutilise la Phase 1 (capability + périmètre confirmateur) et la Phase 5 (design
  system de la section). Interne : 11.1 (cap) précède 11.2 (section) ; 11.3 clôt.
Phase 12 (Confirmée → Suivi) : INDÉPENDANTE (une exclusion meta_query dans Commandes).
  Interne : 12.1 (exclusion) puis 12.2 (recette de bout en bout) close.
Phases 10, 11, 12 : indépendantes entre elles, parallélisables.
Phase 13 (conversions serveur TikTok + Snapchat) : INDÉPENDANTE ; généralise le
  mécanisme de la Phase 10 (Meta). Interne : 13.1 (socle mutualisé + TikTok) précède
  13.2 (Snapchat), puis 13.3 (boutons Tester) ; 13.4 clôt.
Phase 16 (transitions implicites restantes) : dépend de la Phase 15 — elle en est
  issue (15.6 a traité la zone sidebar, 16 traite les 22 autres) et reprend ses
  acquis : le token --cd-ease-out, la proscription d'ease-in, le :active neutralisé
  sous prefers-reduced-motion. Interne : 16.1 (relevé) précède STRICTEMENT 16.2
  (conversion) — convertir sans l'inventaire est précisément ce que cette phase
  existe pour éviter. 16.3 (porte de recette) peut démarrer en parallèle de 16.2,
  mais doit être VERTE avant que le dernier lot ne soit considéré comme livré :
  c'est le seul garde-fou qui verra une transition tuée par erreur.
Phase 17 (reliquat visuel de la 5.2b) : dépend de la 5.2b/5.8 pour l'objet annoncé
  (le comportement des trois menus est déjà mutualisé — il ne reste que le style),
  et de la Phase 15 pour la charte (le token --cd-accent-wash de 17.3 se dérive
  comme side()). Interne : 17.0 et 17.1 réparent les OUTILS et précèdent tout le
  reste — corriger du CSS sous une porte qui verdit à tort, ou sous un cliquet
  troué, c'est travailler sans filet. 17.2/17.3/17.4 sont parallélisables entre
  elles. 17.5 (les portes) doit être verte AVANT 17.6, faute de quoi la
  mutualisation se ferait sans rien pour voir une régression d'état.
Phase 42 (assignation des commandes) : dépend de la Phase 1 (rôle confirmateur,
  `assigned_products()`, capability `aod_cap_confirm_orders`) et touche la
  Phase 11/39 (la conversion d'un abandon crée une commande par un chemin
  distinct du formulaire public — 42.2 le couvre spécifiquement). Interne :
  42.1 (modèle + tourniquet formulaire public) et 42.2 (assignation directe à
  la conversion) précèdent STRICTEMENT 42.3 (cumul en lecture) — appliquer un
  filtre sur une méta qui n'existe pas encore pour aucune commande masquerait
  tout, à tort. 42.4 (sélecteur gérant) et 42.5 (rattrapage groupé) sont
  parallélisables entre elles mais dépendent de 42.3 (le sélecteur doit lister
  un état de périmètre déjà cohérent). 42.6 clôt.
```

## C.2 Definition of Done commune (toute étape)

- [ ] **Générique** : rien de spécifique à un client en dur ; tout réglage
      passe par l'interface (règle n°1 du template).
- [ ] **Sécurité** : chaque écriture AJAX = nonce + capability vérifiés côté
      serveur ; jamais un masquage d'UI seul ; pas de secret loggé.
- [ ] **HPOS** : métas de commande via `wc_get_order()->get_meta()` /
      `update_meta_data()` — jamais `postmeta` direct.
- [ ] **i18n** : toute chaîne via `__()/_e()` ; `make-translations.sh --strict`
      passe ; AR traduit à la main (glossaire : catalogue existant du dashboard).
- [ ] **RTL + mobile** : testé en AR (RTL) et en viewport mobile avant « fini ».
- [ ] **Non-régression** : le gérant existant garde exactement ses 9 sections
      et toutes ses actions ; le parcours acheteur (formulaire COD) intact.
- [ ] **Lint dans le conteneur** (`docker compose exec wordpress php -l …`) —
      l'hôte n'a pas de PHP.
- [ ] **Docs** : `docs/ARCHITECTURE.md` + README du plugin mis à jour si le
      modèle de données ou les rôles changent.
- [ ] **Clôture de phase = `TEMPLATE.md` à jour** (§9 : phase soldée, compte
      d'étapes, porte de recette nouvelle). C'est le fichier que Claude lit **en
      premier** sur chaque clone, via `@TEMPLATE.md` dans `CLAUDE.md` — un statut
      périmé n'y est pas une coquille, c'est une **instruction fausse** donnée avant
      toute lecture du code. *Constaté le 2026-07-20 : les phases 13 à 16 étaient
      closes et absentes, et le fichier se contredisait sur la 9.3 (§5 « reste à
      faire » vs §9 « soldée 3/3 »). Aucun test ne pouvait le voir — la DoD ne
      parlait que d'`ARCHITECTURE.md`.*
- [ ] **Nouvelle section = nouvelle classe** `class-aod-cd-section-*.php`
      (on n'alourdit plus `class-aod-cd-dashboard.php`).
- [ ] **Tout contrôle de couverture remonte ce qu'il a RÉELLEMENT inspecté**, et
      ce nombre est lui-même une assertion. Cf. la règle détaillée ci-dessous.
- [ ] **Le plan de la phase est DANS le dépôt avant sa première étape.** Une
      phase se planifie ici (§ Partie B) — pas seulement dans la session qui
      l'exécute. *Constaté le 2026-07-20 : les étapes 17.0 à 17.4 ont été livrées
      sur un plan de session jamais versé. La session terminée, le plan a disparu
      avec elle : la phase n'existait plus que dans les messages de commit et une
      ligne de `TEMPLATE.md`, et la numérotation au-delà de 17.4 est définitivement
      perdue (un commit cite « l'étape 17.7 » pour ce qui est aujourd'hui la 17.5).*
      C'est le même défaut que le `TEMPLATE.md` périmé, un cran plus haut : le
      travail était bon, il n'était juste **récupérable par personne**. Un plan qui
      ne vit que dans une session n'est pas un plan, c'est une mémoire.

### La règle du témoin — pourquoi elle est dans la DoD

Un contrôle qui balaie un ensemble — « aucun emoji ne subsiste », « toutes les
sections ont une icône », « aucune règle n'anime `all` » — a un mode d'échec que
l'assertion elle-même ne peut pas voir : **passer au vert en n'ayant regardé
qu'une partie de l'ensemble, voire rien**. Il ne casse pas, il ne prévient pas,
et il donne exactement le même signal qu'un contrôle qui a tout vu.

Ce n'est pas une inquiétude théorique. **Six occurrences** sur les Phases 15 et
16, toutes réelles :

| # | Le contrôle | Ce qu'il regardait vraiment | Ce qui l'a démasqué |
|---|---|---|---|
| 1 | Balayage des KPI | 17 des 18 cartes — le motif était aveugle aux tonalités écrites en ternaire | un **seuil chiffré**, pas l'assertion |
| 2 | Balayage des états vides | 19 sur 23 — le motif s'arrêtait à un `// phpcs:ignore` posé après la parenthèse | un **seuil chiffré** |
| 3 | Balayage du cube RGB (sidebar) | une **copie** de la formule, pas `side()` — donc jamais le plafond de luminance | une relecture avant commit |
| 4 | Relevé des transitions | des sélecteurs rendus méconnaissables par les commentaires collés | des « RIEN TROUVÉ » suspects |
| 5 | Intégrité de la feuille CSS | un **seuil** (`> 600 règles`) — une cassure en fin de fichier n'en perd que 6 | l'épreuve du feu |
| 6 | `aucune règle n'anime all` | **10 règles sur 875** : `CSSStyleRule.cssRules` existe (vide) depuis le CSS Nesting, donc *truthy* | l'épreuve du feu |

Le sixième est le cas limite : **le garde-fou lui-même était vide**, et il le
serait resté indéfiniment.

**Ce qu'on en tire, et qui est maintenant exigible :**

1. **Remonter le compte.** Un contrôle de couverture publie combien d'éléments il
   a inspectés, et l'affirme (`inspected > N`, `count === attendu`). Une
   assertion vide ne peut alors plus se déclarer verte.
2. **Préférer un invariant à un seuil.** Un seuil se contourne par accident : il
   est choisi large pour éviter les faux positifs, donc il laisse passer les
   régressions modestes. `{ du fichier == règles exposées` ne se contourne pas.
3. **Nommer des témoins.** Au moins un cas précis, cité pour ce qu'il prouve —
   `rupture` pour le KPI en ternaire, `#00A651` pour le plafond. Un seuil se
   franchit sans qu'on sache lequel manque ; un témoin nommé, non.
4. **Passer par le code de production.** Un test qui recopie une formule mesure
   une formule, pas le code livré. Ajouter un paramètre à la fonction plutôt que
   la dupliquer dans le test.
5. **Forcer au rouge avant de faire confiance.** Un garde-fou qui n'a jamais
   échoué ne prouve rien (dette 15 de `ARCHITECTURE.md`). Casser exprès ce qu'il
   surveille, vérifier qu'il rougit, restaurer — **et vérifier que l'injection a
   bien eu lieu**, sinon on a seulement éprouvé son propre `sed`.

## C.3 Suivi d'avancement

| Étape | Intitulé | Taille | Statut |
|---|---|---|---|
| 1.1 | Traçabilité `_aod_confirmed_by/_at` | S | ✅ Livrée le 2026-07-07 (`7c58025`) |
| 1.2 | Rôle `aod_confirmateur` + 6 capabilities | M | ✅ Livrée le 2026-07-07 (`c581c19`) |
| 1.3 | Routeur & AJAX par capability | M | ✅ Livrée le 2026-07-07 (`a2fe5af`) |
| 1.4 | Section « Équipe » (CRUD confirmateurs) | M | ✅ Livrée le 2026-07-07 (`336952d`) |
| 1.5 | Affectation produits + filtrage serveur | M/L | ✅ Livrée le 2026-07-08 (`846cde9`) |
| 1.6 | i18n + recette Phase 1 | S | ✅ Livrée le 2026-07-08 — **Phase 1 terminée** |
| 2.1 | Socle accueil : onglets + filtres | M | ✅ Livrée le 2026-07-09 (`0e995e3`) |
| 2.2 | Onglet Revenus | M | ✅ Livrée le 2026-07-09 (`cdabcc6`) |
| 2.3 | Onglet Livraison | M | ✅ Livrée le 2026-07-09 (`e72119e`) |
| 2.4 | Onglet Confirmation | M | ✅ Livrée le 2026-07-09 (`496610a`) |
| 2.5 | i18n + recette Phase 2 | S | ✅ Livrée le 2026-07-09 (`b746724`) — **Phase 2 terminée** |
| 3.1 | Vue Stock (tableau + couverture) | M | ✅ Livrée le 2026-07-09 (`6de16d0`) |
| 3.2 | Ajustement rapide + alertes | S/M | ✅ Livrée le 2026-07-09 (`24e2d70`) |
| 3.3 | i18n + recette Phase 3 | S | ✅ Livrée le 2026-07-09 (`c35c00f`) — **Phase 3 terminée** |
| 4.1 | `get_fees()` + Yalidine | M | ✅ Livrée le 2026-07-11 (`0fea245`) |
| 4.2 | ZR Express + EcoTrack | M | ✅ Livrée le 2026-07-11 (`ca634b7`) |
| 4.3 | UI import (préview + marge + backup) | M | ✅ Livrée le 2026-07-11 (`28bc403`) |
| 4.4 | i18n + recette Phase 4 | S | ✅ Livrée le 2026-07-11 (`5456ebf`) — **Phase 4 terminée** |
| 5.1 | Socle design tokens + accent branding | M | ✅ Livrée le 2026-07-11 |
| 5.2a | Bibliothèque de composants visuels (additive) | M | ✅ Livrée le 2026-07-11 |
| 5.2b | Fusion des sélecteurs + clavier/ARIA | M | ✅ Livrée le 2026-07-13 — `filterdd` supprimé en 5.4 ; `statussel`/`csel`/`catdd` clavier + ARIA en 5.8 |
| 5.3 | Coque & navigation (sidebar groupée + fil d'ariane) | M | ✅ Livrée le 2026-07-11 (pagehead → 5.4-5.7) |
| 5.4 | Accueil + Commandes + Suivi | L | ✅ Livrée le 2026-07-12 (+ 2 bugs et 1 fuite de périmètre corrigés) |
| 5.5 | Produits + Catégories + Stock | L | ✅ Livrée le 2026-07-12 (`0898855`, `66a6b9e`, `bc46509`) — 6 bugs trouvés en recette, dont le confirmateur aveugle aux commandes fraîches et le produit survendu impossible à enregistrer |
| 5.6 | Livraison + Pixels + WhatsApp | M | ✅ Livrée le 2026-07-13 — titre en doublon sur 8 pages et débordement mobile corrigés ; garde-fou i18n `--strict` réparé (il ignorait les chaînes longues) |
| 5.7 | Équipe + Mon compte | M | ✅ Livrée le 2026-07-13 — cartes membres + modale (pied hors défilement) ; périmètre produits préservé à l'enregistrement (vérifié) |
| 5.8 | Recette globale + a11y | M | ✅ Livrée le 2026-07-13 — contraste AA tenu pour **tout accent client** (2 trous que l'accent par défaut masquait) ; 2 bugs souris sur les menus flottants ; 184 champs nommés |
| 6.1 | Socle landing : `AOD_COD_Landing` + rendu `template_include` | M | ✅ Livrée le 2026-07-13 (`aod-cod-form` v1.25.0) — commande réellement passée depuis la landing ; repli FR⇄AR vérifié dans les deux sens ; produit sans landing = rendu inchangé |
| 6.2 | Éditeur `/gestion` (`AOD_CD_Section_Landing`, champs FR/AR) | L | ✅ Livrée le 2026-07-13 (`aod-client-dashboard` v0.24.0) — blocs ajoutés/réordonnés/supprimés depuis l'écran et relus en base ; refus d'allumer sans bloc `form` ; interrupteur éteint = brouillon conservé ; AR + RTL complets |
| 6.3 | Blocs de contenu (galerie, vidéo, texte, comparatif) | M | ✅ Livrée le 2026-07-14 (`aod-cod-form` v1.26.0 · `aod-client-dashboard` v0.25.0) — façade vidéo vérifiée (0 iframe, 0 requête YouTube avant le clic) ; galerie `lazy` + `srcset` ; `text` échappé AVANT `wpautop` ; AR + RTL, 390 px sans débordement |
| 6.4 | Blocs de conversion (avis, FAQ, urgence, CTA collant) | M | ✅ Livrée le 2026-07-14 (`aod-cod-form` v1.27.0 · `aod-client-dashboard` v0.26.0) — urgence sur le **vrai stock Woo** et rebours sur la **vraie date de fin de promo** (les 4 cas où le bloc doit **se taire** sont vérifiés) ; `str_replace` et non `sprintf` sur le texte du gérant (un « % » aurait fait une page blanche) ; FAQ en `<details>` natifs ; barre collante qui ne masque rien et ne piège pas le focus ; photo d'avis (fichier **dans** un item) ; AR + RTL, 390 px sans débordement |
| 6.5 | Modèle de départ + duplication | S | ✅ Livrée le 2026-07-14 (`aod-cod-form` v1.28.0 · `aod-client-dashboard` v0.27.0) — modèle 8 blocs VIDES possédé par le format (`starter()`) ; copie qui arrive ÉTEINTE, source intacte, images partagées, périmètre vérifié aux deux bouts, confirmation avant écrasement |
| 6.6 | i18n + recette Phase 6 | S | ✅ Livrée le 2026-07-14 — `landing-produit.js` dans `tools/recette/` (découverte auto du produit via WP-CLI, retrait propre si aucune landing) ; AR `--strict` vert sur les 2 plugins ; < 3 s vérifié (1,9 s en local, 436 Ko) ; héro sans texte sur image ; aucun domaine tiers ajouté par la landing |
| 7.0 | Extraire la section Produits du monolithe | L | ✅ Livrée le 2026-07-15 (`aod-client-dashboard` v0.29.0) — `class-aod-cd-section-products.php` (Produits **et** Catégories, ≈ 1 460 l.) ; monolithe 5 205 → 3 787 l. ; 4 handlers AJAX déplacés ; `get_product_options()`/`get_product_offers()` publiques (consommées par l'édition de commande restée au monolithe) ; `render_search_bar()`/`render_pagination()` promues `public static` ; **diff de rendu nul** — 135 tests verts + smoke navigateur (Produits/édition/Catégories + modale commande) |
| 7.1 | Socle bilingue catalogue (`_ar` + filtres gardés) | M | ✅ Livrée le 2026-07-15 (`aod-cod-form` v1.30.0) — `AOD_COD_Catalog_I18n` : `pick()` (repli bidirectionnel) + `should_translate()` (garde pure : AR ∧ hors admin ∧ contexte public) + `active()`/`render()` (wrappers ; `/gestion` exclu via query var `aod_cd`) ; `Landing::text()` délègue à `pick()` (mutualisation) ; convention `_aod_*_ar` + term-meta actée. **Armature dormante** (aucun filtre branché — 7.2+) → rendu identique. 15 tests unitaires + 6 tests landing verts (150 au total), smoke public inchangé |
| 7.2 | Titre + description bilingues (+ fix héro landing) | M/L | ✅ Livrée le 2026-07-16 (`aod-cod-form` v1.31.0 · `aod-client-dashboard` v0.30.0) — `AOD_COD_Catalog_Filters` : `the_title` + `the_content` (**prio 9**) + `single_post_title` (**reçoit un `WP_Post`, pas un ID** — le copier-coller du callback `the_title` aurait laissé le `<title>` en FR **en silence**) ; court-circuit « AR vide » → produit non traduit rendu **à l'octet** (sans lui, le `trim()` de `pick()` modifiait un contenu jamais traduit) ; **aucun getter WC filtré** (le piège : `add_product()` grave `get_name()` dans la commande depuis la requête acheteur). Saisie AR via `AOD_CD_Dashboard::render_pair()` (promue en helper partagé, `$args` `required`/`rows`) ; héro landing + liste de pack traduits explicitement. **Recette réelle** : commande COD passée **site en arabe** → article enregistré **en FR** ; `/gestion` en AR affiche le FR canonique ; 12 tests (162 au total). Bug pré-existant corrigé au passage : le `--strict` de `aod-cod-form` excluait la marque du **dashboard** → il criait au loup sur « AOD COD Form » depuis toujours |
| 7.3 | Arguments de vente bilingues | S/M | ✅ Livrée le 2026-07-16 (`aod-cod-form` v1.32.0 · `aod-client-dashboard` v0.31.0) — méta parallèle `_aod_selling_points_ar` + helper pur `AOD_COD_Catalog_I18n::pick_list()` ; **aucun filtre ajouté** (un seul site de rendu, jamais écrit dans une commande). **Le piège de l'étape** : `max( count(fr), count(ar) )` faisait surgir des **puces arabes sur le site français** (au rang où le FR manque, le repli bidirectionnel de `pick()` rend l'AR) → la liste **FR est canonique** et fixe le nombre de puces ; seule une liste FR entièrement vide laisse l'AR mener (produit 100 % arabe). Recette : 3 FR / 2 AR → la 3ᵉ puce replie en FR, et **zéro arabe** côté FR. 10 tests (172 au total) |
| 7.4 | Variantes bilingues (dico → repli) | M/L | ✅ Livrée le 2026-07-16 (`aod-cod-form` v1.33.0 · `aod-client-dashboard` v0.32.0) — stockage **imbriqué** (`label_ar`/`name_ar` **dans** `_aod_options`, pas de méta parallèle) : l'appariement devient **structurel**, donc le désalignement de la 7.3 y est impossible. `localize_variant( $fr, $ar )` : saisie > dictionnaire > FR ; `variant_dictionary()` **inchangé**, rétrogradé en repli. La palette de couleurs devient **bilingue** et pré-remplit les deux cases. **Le vrai piège n'était pas celui prévu** : la story blindait `value=` (risque hypothétique) alors que **deux fuites `gettext` réelles** écrivaient déjà de l'arabe dans des **clés** — `__( 'Couleur' )` sur la branche de compat (la locale AR est active en `admin-ajax` : la clé du meta de commande partait en `اللون`, jusque dans le bordereau) et `color_palette()` (le JS injectait le nom traduit dans le champ `name` canonique). Les deux corrigées, **sans aucun changement visible** (le dictionnaire rend déjà `اللون`). `match_option()` extraite pour que la preuve du matching porte sur le **vrai code** (sinon : tautologie verte à jamais). **Recette réelle** : commande passée **en arabe** sur variante `صحراء` → base = **`[Couleur] = Sahara`**. 21 tests (193 au total) |
| 7.5 | Catégories bilingues | M/L | ✅ Livrée le 2026-07-16 (`aod-cod-form` v1.34.0 · `aod-client-dashboard` v0.33.0) — term-meta `_aod_cat_name_ar`, saisie dans la ligne de catégorie (champ AR adjacent : `render_pair()` écarté sur une ligne dense, précédent 7.4). **Le risque annoncé n'était pas le bon** : le danger « nom = clé » hérité de la 7.4 **n'existe pas** (la clé d'une catégorie est son `term_id` ; aucun nom n'entre dans une commande). Le vrai piège : les filtres d'**affichage** de la 7.2 sont **inopérants** — Woo échoue `$category->name` **sans hook** (accueil `wc-template-functions.php:1253`, fil d'ariane `class-wc-breadcrumb.php:177…`) → il faut filtrer l'**objet**. **Un seul hook : `get_term`** — `populate_terms()` l'appelle pour chaque terme, **cache compris** (`class-wp-term-query.php:795,826,1131`), et `get_term_by()` finit par `get_term()` (`taxonomy.php:1159`) : `get_terms`/`single_term_title`/`woocommerce_product_categories` **redondants** (vérifié en réel : `<title>` + `<h1>` traduits sans eux), `term_name` inopérant (`filter='raw'`). Objet issu du **cache** → **clone** obligatoire ; hook très chaud → **garde AVANT** toute lecture de méta. La relecture adverse a redressé 3 erreurs de la story : `term_exists()` **traverse** bien `get_term` (plus d'accès DB direct depuis WP 6.0) ; le test d'invariant « les espaces survivent » aurait passé sur du code faux (`pre_term_name` = `sanitize_text_field` trime à l'insert → `WP_Term` construit à la main) ; et le champ AR serait resté **vide** (signature `render_category_row` + site d'appel non étendus), effaçant la traduction au 1ᵉʳ enregistrement. **Recette réelle** : accueil AR = 3 occurrences arabes / 0 FR (et l'inverse en FR), `<h1>`+`<title>` d'archive traduits, `/gestion` en arabe **reste FR**, base SQL **reste FR**, et **retirer une image n'efface pas la traduction** (les 4 sites JS reposteront `name_ar`). 10 tests (203 au total) |
| 7.6 | i18n + recette Phase 7 | S/M | ✅ Livrée le 2026-07-17 — **Phase 7 terminée.** La clôture est une **preuve rejouable par clone** : `tools/recette/catalogue-bilingue.js` (porte `bilingue`, incluse dans `public`) + fixtures discriminantes auto-seedées/purgées (`scripts/recette-seed-bilingue.php`, `trap` EXIT). 10 surfaces vérifiées dans les deux sens (zéro arabe visible côté FR — hors symbole DZD « د.ج », légitime —, valeurs AR exactes côté AR, description **avec `<p>`** prouvant la prio 9), témoin non traduit en repli intégral, 390 px sans débordement. **Garde prouvée de bout en bout** : commande COD réelle passée site en arabe, variante choisie sur son libellé arabe → `order_item_name` FR, meta `Contenance Recette=Selva` (clé = libellé FR **brut** — le format `[Couleur]` de la 7.4 est celui de la branche de compat), `/gestion` en AR reste FR, SQL brut FR. **Phase rouge exercée** : sabotage de `_aod_title_ar` → 7 échecs exactement sur les assertions du titre, restauration → vert ; fixtures absentes/fantômes → exit 1 explicite. Découverte d'implémentation : une landing allumée **remplace** la fiche → 4ᵉ fixture dédiée au héro. `--strict` exit 0 ×2 **sans nouvelle chaîne**, 203 tests inchangés, `run.sh public` complet vert (130 ✅). Aucun fichier de plugin modifié → **pas de bump** (précédent 6.6) |
| 8.1 | Éditeur riche sur le couple description | M/L | ✅ Livrée le 2026-07-17 (`aod-client-dashboard` v0.34.0) — 🔴 **le `wp_enqueue_editor()` prescrit par A.8 était inopérant** (4ᵉ « risque annoncé ≠ risque réel ») : la coque `/gestion` est un document autonome sans `wp_head`/`wp_footer` — **TinyMCE chargé « nu »** (`tinymce.min.js` + `_WP_Editors::wp_mce_translation()` inline, `require_once` obligatoire, `language:` obligatoire sinon UI anglaise — défaut `"en"` du minifié). `render_pair()` gagne `rich` (opt-in, seul le site description le passe ; `dir="ltr"` explicite côté FR — la page AR est RTL globale) ; `tinymce.triggerSave()` avant le `FormData` (le piège de la perte silencieuse, prouvé par l'AC 9 « modifier la seule description »). **Zéro changement serveur** : `wp_kses_post` était déjà la frontière — contrat barre ⇄ kses verrouillé par 14 tests (dont discriminance contre `sanitize_textarea_field`, et la normalisation kses du `style` : le `;` final tombe). Vérifié en réel : 21 assertions navigateur (édition LTR dans page RTL, UI FR « Gras », persistance FR+AR, rendu public via swap 7.2, 390 px 0 px de débordement, TinyMCE absent des autres sections) ; `a11y-noms` étendu (`role="presentation"` = hors arbre a11y, le nom vit sur le wrapper TinyMCE) ; 217 tests ; recette `public` verte. **8.2/8.3 : charger l'éditeur passe par la coque, jamais par l'enqueue.** **Correctifs de revue `/code-review high` (v0.34.1)** : 3 bugs (contenu hérité aplati au réenregistrement → `wpautop` ; locale suivant le profil et non le switcher → `get_locale()` ; iframes FR/AR au même nom → titre distinct) + collage couleur/police contourné (→ `valid_styles: text-align`) + 2 resserrages a11y (skip `role=presentation` conditionné à la non-focusabilité, audit des wrappers `[role="button"]`) + 4 nettoyages (plugin `directionality` retiré, helper `is_form_action()`, `block_formats` blindé, docblock). 218 tests. |
| 8.2 | Photos dans la description | M | ✅ Livrée le 2026-07-17 (`aod-client-dashboard` v0.35.0) — **premier upload IMMÉDIAT du plugin** (`aod_cd_desc_image`, nonce + `edit_products` + whitelist de mimes serveur) : les autres uploads sont différés dans le `FormData` du save, mais une image insérée au fil du texte a besoin de son URL avant. Bouton `aodimage` (icône TinyMCE, tooltip traduit → aria-label du wrapper) dans les deux éditeurs ; `pickImage(ed)` insère dans l'éditeur **de la closure** (jamais `activeEditor` — le focus peut changer de case). Markup **minimal** stocké (`<img class="wp-image-{id}">`) : `srcset`/`sizes`/`decoding` sont **strippés par kses** et **régénérés au rendu** par `wp_filter_content_tags` (prio 12, après le swap AR prio 9) — vérifié : base sans srcset, fiche publique avec. **Piège consigné** : `$overrides` custom de `media_handle_upload` doit re-spécifier `test_form => false` (`media.php:295`) sinon tout upload échoue. Vérifié en réel (image 1200×800 réelle) : bouton FR+AR, image dans la bonne case, `.txt` refusé côté serveur (éditeur intact), sans-nonce → 403, persistance, rendu FR (srcset + `fetchpriority`/`lazy` selon l'optimiseur LCP de WP) et AR, 390 px sans débordement, HTTP 200. 224 tests (contrat kses étendu : markup image + srcset strippé). Zéro changement serveur au save ; `aod-cod-form` intouché. |
| 8.3 | Vidéo dans la description (YouTube façade + fichier local natif) | M | ✅ Livrée le 2026-07-18 (`aod-client-dashboard` v0.36.0 · **`aod-cod-form` v1.35.0** — premier bump cod-form de la phase, car la vidéo exige du rendu public). 🔴 **Rupture 8.1/8.2** : là kses acceptait le contenu et le rendu enrichissait seul ; ici `wp_kses_post` **strippe `<iframe>`** → **YouTube** stocke un lien marqué `<a class="aod-desc-video">` (kses conserve, `data-*` compris) que la **nouvelle classe `AOD_COD_Desc_Video`** convertit en **façade** sur `the_content` **prio 20** (après swap AR 9 / wpautop 10 / content-tags 12) — 0 iframe, 0 requête YouTube avant clic (JS/CSS/classes `aod-landing__video-*` de la landing 6.3 **réutilisés** ; enqueue conditionnel sur fiche non-landing). **Fichier local** : kses **conserve `<video src>` mais strippe `<source>`** → `src` **direct** sur `<video preload="none">` (natif, aucune façade/JS) ; endpoint `aod_cd_desc_video` jumeau de la 8.2. Pièges consignés : `convert_urls:false` + `extended_valid_elements` sur TinyMCE (sinon URL relative fragile / `<video>` strippé à la sérialisation) ; `mce-i-embed` absent du skin → `icon: 'media'`. Vérifié en réel : 26 assertions navigateur (façade YouTube 0 requête + iframe nocookie au clic, `<video>` fichier 0 octet avant Lire + HTTP 200 + src absolue, endpoint refuse sans nonce / non-vidéo, 390 px, produit sans vidéo = 0 asset) ; 256 tests unitaires (contrat kses vidéo + `render_facades`) ; `noms`/`clavier`/`bilingue`/`public` verts ; `--strict` ×2. |
| 8.4 | i18n + recette Phase 8 | S | ✅ Livrée le 2026-07-18 (`aod-cod-form` v1.35.1 · `aod-client-dashboard` v0.36.1) — **clôture de la Phase 8**. Livrable : recette rejouable par clone `tools/recette/description-riche.js` (porte `riche`, incluse dans `public`) + seed `scripts/recette-seed-phase8.php` (image GD + MP4 réels, produit à description riche FR/AR + témoin plat, purgés en `trap`). Prouve en visiteur réel : `<h2>`/`<h3>`/listes rendus, `<img>` avec `srcset` régénéré, façade YouTube (0 iframe / 0 requête avant clic → iframe nocookie au clic), `<video>` fichier `preload="none"` (0 octet avant lecture) + HTTP 200 + src absolue, le tout FR **et** AR + RTL à 390 px ; témoin plat inchangé (repli FR en AR) ; garde : commande COD réelle → `order_item_name` = titre (la description n'entre pas dans la commande / `/gestion`). **Phase rouge exercée** (sabotage `_aod_desc_ar` → 3 échecs AR ciblés → restauration → vert). **4 correctifs D7 de la revue `/code-review high` de la 8.3 repliés ici** (bump ×2) : (1) `ytId` client reflète exactement `youtube_id()` serveur ; (2) regex `render_facades` à absorption `<p>` **balancée** (lien en milieu de paragraphe) ; (3) court-circuit `-file` resserré ; (4) `filter_content` aligné sur `maybe_enqueue`. 3 nettoyages (dédoublonnages) **différés** en dette (ARCHITECTURE §7 pt 10). `--strict` ×2 exit 0 sans chaîne nouvelle ; 265 tests unitaires (+9 : régressions D7 + contrat `youtube_id`) ; `tools/recette/run.sh public` vert de bout en bout. |
| 9.1 | Réglage « Afficher sur l'accueil » + ordre (`/gestion`, données + UI) | S/M | ✅ Livrée le 2026-07-18 (`aod-client-dashboard` v0.37.0) — term-metas `_aod_cat_home` + `_aod_cat_home_order`, helper pur `home_meta_ops()` (patron `parent` « absent = intact » → un enregistrement d'image ne décoche ni ne réordonne, piège 7.5-AC12 rendu impossible ; 2 sites JS au lieu de 4). Case + champ ordre pré-remplis (signature `render_category_row` + site d'appel + `<template>` étendus). 275 tests (+10, dont « home absent → leave »). **Recette réelle** : 9 assertions navigateur (création cochée → persistée au rechargement, 🔴 changer l'image garde le flag+ordre, décocher supprime la méta) + 390 px AR/RTL sans débordement, libellé « إظهار في الصفحة الرئيسية ». `--strict` ×2, portes a11y `noms`/`clavier` vertes. `aod-cod-form` **intouché** (le rendu accueil est la 9.2) |
| 9.2 | Accueil piloté par la sélection + retrait du plafond de 6 | S/M | ✅ Livrée le 2026-07-18 (`aod-cod-form` v1.36.0) — `AOD_COD_Pages::home_categories_markup()` : sélection cochée → `[product_categories ids="…" orderby="include" hide_empty="0"]` (ordre préservé — `orderby="include"` **vérifié en réel**, pas de rendu maison ; vides comprises), sinon repli `hide_empty="1"` ; **plafond de 6 retiré** partout. Tri pur testable `sort_home_category_ids()` (ordre → nom → id). ⚠️ Piège trouvé en recette : `get_terms( meta_key/meta_value )` ne remonte pas la term-meta sur cette version WP → filtrage en **PHP** sur `get_term_meta` (cache amorcé par `get_terms`). i18n AR (filtre `get_term` 7.5) **inchangée**, prouvée. 280 tests (+5). **Recette réelle 9/9** (visiteur `curl`) : sélection ordonnée l'emporte sur l'alphabet, catégorie vide cochée visible, non-cochée absente, remplacement (pas d'ajout), nom arabe en AR, repli non-régressif. `run.sh public` vert. `aod-client-dashboard` **intouché** |
| 9.3 | i18n + recette Phase 9 | S | ✅ Livrée le 2026-07-18 — **Phase 9 close.** Porte rejouable `tools/recette/run.sh categories` (incluse dans `public`) : seed marqué `scripts/recette-seed-phase9.php` (create/unflag/cleanup, témoin non vide clone-agnostique) + `tools/recette/categories-accueil.js` (visiteur, **9/9**) : catégorie vide cochée visible, ordre saisi > alphabet, non-cochée absente, **remplacement**, nom AR (7.5), repli non-régressif. Piège consigné : Astra affiche la catégorie sous chaque produit → assertions **cadrées sur la section « Nos catégories »**. `--strict` ×2 (aucune chaîne nouvelle). **Aucun code de plugin touché** ; baseline propre après coup |
| 10.1 | Réglage token CAPI + envoi serveur Purchase dédupliqué | M | ✅ Livrée le 2026-07-18 (`aod-cod-form` v1.37.0 · `aod-client-dashboard` v0.39.0) — `send_capi_purchase()` sur `aod_cod_order_created` (prio 20) → `graph.facebook.com/{version}/{pixel}/events` ; **`event_id` = n° de commande, IDENTIQUE au pixel navigateur** (dédup Meta). **Opt-in** (pixel + jeton requis → sinon 0 appel), **fail-open** (modèle Turnstile, `WP_Error` avalée), **PII SHA-256** (`hash_pii`/`normalize_phone` 0→213 ; IP/UA/`_fbp`/`_fbc` en clair), valeur/devise via `to_reporting()`, envoi unique (`_aod_capi_sent`). Jeton **secret** : jamais ré-affiché en clair (« vide = inchangé » + case effacer, helper `capi_token_from_post()` côté COD **et** dashboard), champ `type=password`. **Vérifié en réel** : 20 assertions (HTTP intercepté par `pre_http_request`) — opt-in ×2, URL/pixel, timeout, jeton+test_code transmis, event_name/action_source/event_id, `ph` = SHA-256 (0 PII en clair), country haché, devise convertie, idempotence, event_id pixel == CAPI. Lint OK, 280 tests verts, `--strict` ×2. |
| 10.2 | `test_event_code` + bouton « Tester » + robustesse | S/M | ✅ Livrée le 2026-07-18 — champ `meta_test_event_code` (joint au payload s'il est renseigné) ; bouton **« Tester la connexion »** (AJAX `aod_cd_test_capi` → `test_capi_connection()` : **GET** du nœud pixel, **aucun événement envoyé**, jeton enregistré). Verdict lisible sans jamais exposer le jeton (message d'erreur Meta relayé). JS `bindCapiTest()` jumeau de `bindWhatsappTest()`, CSS réutilisée. **Vérifié en réel** : 7 assertions (sans pixel/jeton → 0 appel + invite ; succès nomme le pixel ; GET sur le nœud ; jeton absent du message ; erreur Meta relayée). Lint PHP+JS OK, `--strict` ×2. |
| 10.3 | i18n + recette Phase 10 | S | ✅ Livrée le 2026-07-18 — **Phase 10 close.** Recette rejouable **serveur** `scripts/recette-capi.php` (porte `capi` dans `run.sh`, incluse dans `tout`) : la CAPI étant un envoi serveur-à-serveur, elle est prouvée en **interceptant la requête HTTP sortante** (`pre_http_request`, **0 appel réel à Meta**) — auto-suffisante (crée/supprime sa commande, restaure l'option). **23 assertions vertes** : opt-in, URL/pixel/timeout, `event_id` = n° de commande = pixel navigateur (dédup), PII **SHA-256** (tél 0→213, prénom minuscule, **0 PII en clair**), devise `to_reporting`, idempotence, test de connexion (succès/erreur relayée/jeton jamais exposé). i18n `--strict` vert ×2 (15 chaînes AR), docs `ARCHITECTURE` §2/§3. |
| 11.1 | Capability `aod_cap_abandoned` + Équipe | S | ✅ Livrée le 2026-07-18 (`aod-client-dashboard` v0.40.0) — `aod_cap_abandoned` ajoutée à `AOD_CD_Roles::CONFIRMER_CAPS` (7ᵉ cap) + `cap_labels()`/`cap_short_labels()` (la case apparaît **et** se sauve dans la fiche membre, la section Équipe étant pilotée par la boucle sur `CONFIRMER_CAPS`) ; carte `$section_caps['abandoned']`. Le gérant l'a d'office (`user_can()` + `manage_woocommerce`). Test unitaire de la whitelist mis à 7 (+ assertion de présence). |
| 11.2 | Section « Commandes abandonnées » (nouvelle classe) | M | ✅ Livrée le 2026-07-18 (`aod-client-dashboard` v0.40.0) — **nouvelle classe `class-aod-cd-section-abandoned.php`** (DoD : nouvelle section = nouvelle classe) + `sections()`/`nav_groups('ventes')`/`render_section()`/require. Lecture seule de `{prefix}aod_cod_leads` `WHERE status='pending'` (aucune capture, aucune migration) : onglets **Tous / Abandonnés (>30 min) / En cours (<30 min)** à compteurs, recherche nom/tél, pagination, boutons **Appeler** + **WhatsApp** (0→213). Un lead `converted` n'apparaît jamais ; compteur « récupérés » en sous-titre. **Périmètre confirmateur en SQL** (`product_id IN assigned_products`). Réutilise `page_head`/`render_search_bar`/`render_pagination`/`pill`/`empty_state` (design system Phase 5). **Vérifié en réel** : 14 assertions (seed abandonné/en cours/converti → HTML) — le converti est bien exclu, badges, onglets, recherche nom **et** téléphone, Appeler/WhatsApp, capability reconnue. Lint OK, **282 tests** verts. |
| 11.3 | i18n + recette Phase 11 | S | ✅ Livrée le 2026-07-18 — **Phase 11 close.** Recette rejouable navigateur `tools/recette/abandonnees.js` (porte `abandonnees`, incluse dans `tout`) + seed marqué `scripts/recette-seed-phase11.php` (abandonné/en cours/converti, purge en `trap`). **13 assertions vertes** (connecté en gérant) : le prospect abandonné et l'en-cours apparaissent, le **converti jamais**, badges, **Appeler + WhatsApp (0→213)**, onglets Abandonnés/En cours, recherche nom **et** téléphone, **AR + RTL à 390 px** (titre « الطلبات المهجورة », 0 débordement). i18n `--strict` vert (18 chaînes AR dont un **pluriel arabe à 6 formes**), docs `ARCHITECTURE` §3. |
| 12.1 | Exclure les commandes expédiées de « Commandes » | S | ✅ Livrée le 2026-07-18 (`aod-client-dashboard` v0.38.0) — helper mutualisé `unshipped_meta_query()` (`meta_query` `NOT EXISTS` ∨ `= ''` sur `_aod_ship_tracking` ; tableau **vide si `AOD_Carrier` absent** → aucune exclusion, pas de fatal) injecté dans `section_orders()` **et** `count_orders()`. Une commande expédiée quitte « Commandes » pour « Suivi » (même critère). **Vérifié en réel** : partition exacte sur la base (35 = **18 expédiées + 17 non expédiées**) ; une commande en **erreur d'envoi** (sans tracking) reste dans Commandes ; le périmètre confirmateur (`scoped_orders()`) est **orthogonal** au `meta_query` (jointure SQL séparée) donc intact. Lint OK, **280 tests** verts, `--strict` vert. |
| 12.2 | i18n + recette Phase 12 (cycle complet) | S | ✅ Livrée le 2026-07-18 — **Phase 12 close.** Recette rejouable navigateur `tools/recette/commandes-suivi.js` (porte `suivi`, incluse dans `tout`) + seed marqué `scripts/recette-seed-phase12.php` (commande expédiée + non expédiée, **envoi auto désactivé le temps du seed → 0 appel transporteur réel**, purge en `trap`). **9 assertions vertes** (connecté en gérant) : la commande expédiée a **quitté** « Commandes » (onglets Confirmées **et** Toutes) et **apparaît dans « Suivi »** avec son n° de colis ; la non expédiée **reste** dans « Commandes » et est **absente** de « Suivi ». i18n `--strict` vert (aucune chaîne nouvelle), docs `ARCHITECTURE` §3. |
| 13.1 | Socle serveur mutualisé + TikTok Events API | M | ✅ Livrée le 2026-07-18 (`aod-cod-form` v1.38.0 · `aod-client-dashboard` v0.41.0), en **review** (flux BMAD, story `13-1-socle-mutualise-tiktok.md`) — socle mutualisé extrait de la CAPI Meta (`capi_http_post`/`order_content_ids`/`normalize_phone($e164)`, Meta **inchangé à l'octet**), `send_tiktok_purchase()` sur `aod_cod_order_created` (`/event/track/`, jeton en en-tête `Access-Token`, `CompletePayment`, `event_id` = n° commande, PII **E.164 `+`** ≠ Meta, garde distincte `_aod_capi_tiktok_sent`), dédup navigateur `{ event_id }` sur `ttq.track()`, jeton `tiktok_capi_token` (secret) dans `/gestion` + admin Woo. **Vérifié** : recette `run.sh capi` étendue **41/41** (Meta 23 + TikTok 18, HTTP intercepté, 0 appel réel), 282 tests, `--strict` ×2 (5 chaînes AR), lint PHP+JS. |
| 13.2 | Snapchat Conversions API | M | ✅ Livrée le 2026-07-19 (`aod-cod-form` v1.39.0 · `aod-client-dashboard` v0.42.0), en **review** (flux BMAD, story `13-2-snapchat-conversions-api.md`) — `send_snapchat_purchase()` sur `aod_cod_order_created` via le **socle 13.1 non modifié** (`capi_http_post`/`order_content_ids`/`normalize_phone`/`hashed_list`/`to_reporting`), Conversions API **v3** (`SNAPCHAT_API_BASE` + ID pixel dans l'URL `/v3/<pixel>/events`, jeton en en-tête `Authorization: Bearer`, jamais dans l'URL), `PURCHASE`, dédup **`client_dedup_id`** = n° commande (== `snaptr` navigateur). ⚠️ Payload **aligné Meta, pas TikTok** : `user_data`, tél haché **SANS `+`**, champs `em/ph/fn/ln/ct/st/country`, cookies Snap `sc_cookie1`/`sc_click_id`. Jeton `snapchat_capi_token` (secret) dans `/gestion` + admin Woo, garde distincte `_aod_capi_snap_sent`. **Vérifié** : recette `run.sh capi` étendue **60/60** (Meta 23 + TikTok 18 inchangés + Snapchat 19, HTTP intercepté, 0 appel réel), 282 tests, `--strict` ×2 (5 chaînes AR), lint PHP+JS. |
| 13.3 | Boutons « Tester » (TikTok/Snap) + robustesse | S/M | ✅ Livrée le 2026-07-19 (`aod-cod-form` v1.40.0 · `aod-client-dashboard` v0.43.0), en **review** (flux BMAD, story `13-3-boutons-tester-connexion.md`) — `test_tiktok_connection()` + `test_snapchat_connection()` (sœurs de `test_capi_connection()` Meta **inchangée**), AJAX `aod_cd_test_capi` routé par `platform` **whitelisté**, JS `bindCapiTest()` généralisé (un bouton par carte via `data-platform`). **Zéro pollution prod** : Snapchat via l'endpoint dédié `/events/validate` (en-tête Bearer, hors URL) ; TikTok via un événement neutre `ViewContent` + `test_event_code` **requis** (nouveau réglage `tiktok_test_event_code`, non secret) sinon invite + 0 requête. Jeton jamais exposé dans le verdict, fail-safe. **Vérifié** : recette `run.sh capi` étendue **79/79** (60 envois inchangés + 19 tests connexion, HTTP intercepté, 0 appel réel), 282 tests, `--strict` ×2 (17 chaînes AR), lint PHP+JS. |
| 13.4 | i18n + recette Phase 13 | S | ✅ Livrée le 2026-07-19 (**clôture Phase 13**, flux BMAD, story `13-4-i18n-recette-phase.md`) — porte de recette rejouable **`bash tools/recette/run.sh conversions`** (alias `capi` conservé, libellé « Meta / TikTok / Snapchat + tests connexion »), **dédup navigateur au filet** (la recette lit `aod-cod-form.js` et asserte `fbq{eventID}` / `ttq{event_id}` / `snaptr client_dedup_id` — casse si un refactor retire l'id), `--strict` **vert ×2** re-confirmé, doc `ARCHITECTURE.md` §2/§3 (métas de garde `_aod_capi_*`) complétée. **Aucun code plugin modifié** (pas de bump : recette + `run.sh` + doc). **Vérifié** : recette **83/83** (79 + 4 dédup navigateur), 282 tests. |
| 14.1 | Capture des cookies + envoi différé des trois régies | M/L | ✅ Livrée le 2026-07-19 (`aod-cod-form` v1.41.0, **clôture Phase 14**, flux BMAD, story `14-1-conversions-hors-chemin-achat.md`) — `queue_server_conversions()` met en file (`wp_schedule_single_event`, hook `aod_capi_dispatch`, un événement par commande) et `dispatch_server_conversions()` envoie. **Mesuré : 3 ms sur le chemin acheteur** contre 6 024 ms pour trois régies lentes simulées (avant : jusqu'à 24 s d'attente réelle). 🚨 Le piège traité en premier, et séparément : les trois payloads lisaient `$_COOKIE` **au moment de l'envoi** — un cron n'en a aucun. `capture_match_cookies()` (méta `_aod_conv_cookies`, posée dans la requête acheteur) + `match_cookie()` (méta d'abord, `$_COOKIE` en repli). Ce repli rend la capture **neutre** : livrée et vérifiée d'abord, porte **verte sans modification d'assertion** (AC 1), puis seulement le déplacement. ⚠️ « Mis en file » ≠ « envoyé » : aucune garde `_aod_capi_*_sent` à la planification, sinon un cron mort compterait comme transmis un achat jamais parti. Opt-in de planification (`armed_server_platforms()`) → aucune tâche fantôme. Désactivation → `wp_unschedule_hook()` et non `wp_clear_scheduled_hook()` (nos tâches portent l'ID de commande en argument). **Vérifié** : porte `conversions` **114/114** (99 + 15), dont le test central — cookies vidés avant dispatch, payload toujours complet ; 282 tests, lint OK, `--strict` vert ×2 (aucune chaîne nouvelle). Docs : `ARCHITECTURE.md` §2 + `CRON-SERVEUR.md` (le cron conditionne désormais la mesure publicitaire). |
| 15.0 | Réparer `SECTIONS` dans la recette | S | ✅ Livrée le 2026-07-19 — `tools/recette/lib.js` : 12 sections de menu (`'stats'` retiré, `home` + `abandoned` ajoutées ; `landing` exclue, elle exige `?produit=`). **Vérifié au navigateur** : les 12 slugs rendent chacun **leur** page (fil d'ariane relu), et `stats` retombe visiblement sur `home` — le repli silencieux du routeur est reproduit, donc le diagnostic est confirmé, pas supposé. Impact réel mesuré : `abandoned` n'était **jamais** visitée ; `home` l'était par accident, sous un nom inexistant. |
| 15.1 | Récupérer et versionner les SVG Phosphor | S | ✅ Livrée le 2026-07-19 — `@phosphor-icons/core` **2.1.1** (MIT) récupéré **sur l'hôte** (les conteneurs n'ont pas de réseau fiable), **24 `regular` + 13 `fill`** copiés **tels quels** dans `assets/icons/` (160 Ko) + `LICENSE` + `README.md` de provenance. Les fichiers amont portent déjà `viewBox="0 0 256 256"` et `fill="currentColor"` : rien à retoucher. Les 24 noms proposés en spec **existent tous** (y compris `path`, qui était marqué « à vérifier »). |
| 15.2 | `AOD_CD_Icon` : catalogue fermé + rendu + RTL | M | ✅ Livrée le 2026-07-19 — `class-aod-cd-icon.php` : catalogue fermé (nom **logique** → fichier, donc changer de jeu d'icônes ne toucherait qu'une colonne), `svg()` reconstruit les attributs et ne garde du fichier que l'intérieur du `<svg>` (source vérifiable contre l'amont), cache statique, nom inconnu → `''` + `_doing_it_wrong()`. CSS `.aod-cd-i` + miroir RTL **ciblé** `[data-dir]`. 🚨 `directional()` ne contient que `shipping` et `returned` : miroiter `trend-up` ferait **descendre** une courbe montante — « Marge estimée » dirait l'inverse de sa valeur, en arabe seulement. `test-icones.php` : **15 tests** (fichiers présents, aucune section orpheline, aucune couleur en dur, liste noire du miroir). **Vérifié** : 302 tests (+15), lint PHP+JS, porte `contraste` verte sur 6 accents, `--strict` vert (0 chaîne nouvelle). Aucun changement visuel : la classe n'est pas encore branchée. |
| 15.3 | Zone 1 — sidebar, 14 icônes | S/M | ✅ Livrée le 2026-07-19 — `sections()` et `hidden_sections()` portent un NOM D'ICÔNE au lieu d'un emoji ; le rendu (`:482`) choisit la graisse `fill` sur la section active. `.aod-cd-ico` **entièrement supprimée** (0 référence restante), avec l'ancien `filter:grayscale(0)` orphelin qu'elle traînait. `landing` (section masquée mais **routable**) a reçu son icône `rocket-launch` — le test l'a réclamée. **Vérifié au navigateur** : 12/12 liens en SVG, **0 emoji résiduel** dans la sidebar, icône active en `--cd-accent-ink` (`rgb(0,0,0)` sur le jaune), camion **miroité** en RTL (`matrix(-1,0,0,1,0,0)`), rendu mobile 390 px avec le tiroir ouvert. Portes `noms`, `clavier`, `rtl` **vertes** ; 304 tests (+2 : sections routables masquées comprises, et « aucun emoji résiduel »). |
| 15.4 | Zone 2 — KPI, 24 cartes | M | ✅ Livrée le 2026-07-19 — le contrat de `kpis()` change : `$c[2]` est un NOM D'ICÔNE et le composant appelle lui-même `AOD_CD_Icon`, donc les 4 appelants restent déclaratifs et ne peuvent pas injecter de balisage. **L'inventaire de la spec était faux : 24 cartes, pas 18** — « En attente » et les deux « Délai moyen » manquaient, d'où 3 entrées de catalogue non prévues (`pending`, `delay`, `rupture`). Les 7 tonalités sémantiques sont **intactes** ; le glyphe reste sur `--cd-ink` dans toutes, c'est la pastille qui porte le sens. **Vérifié au navigateur** : 24/24 pastilles en SVG sur les 4 écrans, **0 trou**. Portes `contraste` (6 accents), `rtl`, `noms` vertes ; 307 tests (+3). |
| 15.5 | Sidebar dérivée + `readable_on()` recalibré | M | ✅ Livrée le 2026-07-20 (`08fab36`) |
| 15.6 | Nettoyage CSS de la coque | S | ✅ Livrée le 2026-07-20 (`5ce3a68`) — 4 des 26 `transition` implicites traitées ici |
| 15.7a | Zone 3 — composants partagés | M | ✅ Livrée le 2026-07-20 (`f8c5b99`) — `empty_state()` (12 appelants, 6 fichiers), tuiles d'alerte (4), cartes de régie (8), jumeau JS `emptyState()` via `CD.icons` (SVG rendus par PHP : le JS ne fabrique pas d'icône et ne peut pas en inventer hors liste). **Les régies avaient des SUBSTITUTS, pas des icônes** : `f`, `♪`, `👻`, `G`, `$` → vrais logos Meta/TikTok/Snapchat/Google (MIT) et `currency-circle-dollar` (un `$` dans une boutique en dinars). 🚨 Le balayage des sources trouvait 19 noms au lieu de ~23 : le motif butait sur le `// phpcs:ignore` après la parenthèse ouvrante — 4 états vides sautés. Contrôle **inverse** ajouté (aucun SVG orphelin). Portes `contraste`/`noms`/`rtl` vertes ; 310 tests. |
| 15.7b | Zone 3 — le diffus (~45 remplacements) | M | ✅ Livrée le 2026-07-20 — accordéons produit + Livraison, panneaux WhatsApp/Mon compte, boutons d'action (corbeille, actualiser, modifier, enregistrer, note), détail de commande, éditeur de page de vente, alertes Stock, glyphes JS (`textContent` → `innerHTML` : `textContent` aurait affiché le SVG en toutes lettres). **Cliquet de phase** posé et **forcé à rougir** avant d'être retenu (emoji injecté → `team.php:19 (🎯)`, fichier restauré) : un emoji n'est admis que dans un commentaire ou une chaîne traduisible. Restent 3 emojis, tous des CONTENUS assumés (`Bonjour %s 👋`, le corps du message WhatsApp de test, la prose « Cliquez sur ✕ »). **Vérifié au navigateur** : 0 emoji résiduel sur Livraison, Mon compte, Stock, Commandes, Pixels. Portes `contraste`/`noms`/`clavier`/`souris`/`rtl` vertes ; 311 tests (+1). |
| 15.8 | Charte appliquée + recette de clôture | S | ✅ Livrée le 2026-07-20 (`ffce2a1`, `#00A651`) — **Phase 15 soldée (10/10)** |
| 16.1 | Relevé des 22 `transition` implicites | S | ✅ Livrée le 2026-07-20 (`88c81c6`) — relevé sur `dashboard.css` **seul** |
| 16.2 | Conversion par lots homogènes | M | ✅ Livrée le 2026-07-20 (`812dc6c`) |
| 16.3 | Porte de recette « le mouvement existe » | M | ✅ Livrée le 2026-07-20 (`812dc6c`, `run.sh mouvement`) — ne couvre que `/gestion` : cf. §C.5 |
| 17.0 | Garde d'argument sur `run.sh` | S | ✅ Livrée le 2026-07-20 — le dispatch est une SUITE de `if`, pas un `case` : un argument inconnu ne déclenchait aucune porte, n'avertissait pas, et sortait à **0**. `run.sh contrast` rendait donc le même vert que `run.sh contraste` en n'ayant **rien** vérifié. Garde placée **avant** le `npm install` (une faute de frappe n'a pas à installer des dépendances), sortie 2, liste des noms valides **recoupée mécaniquement** contre le dispatch — 17 contre 17, aucun écart dans un sens ni dans l'autre. |
| 17.1 | Réparer le cliquet anti-emoji de 15.7b | S | ✅ Livrée le 2026-07-20 — son neutraliseur de chaînes traduisibles s'écrivait `…\s*\(.*` : à partir du premier appel de traduction, il effaçait la ligne **jusqu'au bout**. Or une ligne d'écho met souvent la traduction dans un ATTRIBUT, donc AVANT le contenu — tout emoji placé après échappait au cliquet, **à chaque exécution des Phases 15 et 16**. Motif à parenthèses équilibrées : on retire l'APPEL, pas la fin de la ligne. Périmètre publié (14 fichiers, 10 638 lignes) + **deux** témoins permanents — sans le second (ignorer un emoji DEDANS), « réparer » aurait pu vouloir dire « ne plus rien neutraliser ». Mis au rouge avant d'être cru : il a nommé `class-aod-cd-dashboard.php:1469` (🔄) AVANT correction. Rayon du trou **mesuré** : un seul emoji ; les quatre autres sont des CONTENUS assumés. |
| 17.2 | Les états d'une ligne redeviennent visibles | S | ✅ Livrée le 2026-07-20 — survol et sélection **invisibles une ligne sur deux**. `:not()` et `:nth-child()` apportent chacun le poids d'une classe : le zébrage pesait (0,3,3) contre (0,2,3) aux deux états. La sélection cumulait un second défaut — elle peignait le `tr` quand le zébrage peint le `td`, donc l'enfant recouvrait le parent. Mesuré sur l'ancien CSS, ligne paire : **survol = repos = sélection = #f4f4f1**, aucun retour ni au survol ni au clic. Après : même spécificité, règles regroupées, et la sélection APRÈS le survol (état persistant — la souris ne doit pas effacer « celle-ci est cochée »). Le zébrage passe à #f4f4f4, franchement achromatique : il couvre la moitié des lignes en permanence, le teinter ferait lire toute la table comme colorée par la marque. |
| 17.3 | `--cd-accent-wash` : la dernière couleur de client en dur | M | ✅ Livrée le 2026-07-20 — survol et sélection étaient écrits #fdf6d8 et #fffdf0 : deux jaunes hérités de l'accent PAR DÉFAUT. Un client au bordeaux gardait des lignes **jaunes** (règle template n°1). Dérivé **en PHP** comme `side()`, non en `color-mix()` CSS : la garantie « AA pour tout accent » vient d'un test qui balaie le cube en appelant CE code ; une valeur calculée par le navigateur obligerait le test à recopier la formule. `WASH_MIX = 0.94` **mesuré** : l'encre contraignante n'est pas `--cd-ink` mais `--cd-muted` (#666666) — réutiliser `--cd-accent-soft` (0.86) donnait 4,15:1, **sous AA**. C'est exactement ce que faisait déjà la note du gérant : une brèche AA préexistante, masquée par l'accent jaune par défaut. Piège évité : la note posait `border-left`, pas `border-left-color` — convertir la seule couleur en logique aurait peint un bord de **largeur nulle** en arabe. |
| 17.4 | Purger les variables fantômes | S | ✅ Livrée le 2026-07-20 — `--cd-green`, `--cd-bg-2` et `--cd-ink-3` n'ont **jamais** été déclarées : ce qui s'affichait était le repli, en silence. `--cd-ink-3` rendait #98a2b3, soit **2,58:1** en corps 12 — très en dessous d'AA. Une variable inexistante ne casse rien de visible ; le contrat de contraste tombe sans un bruit. **Rabattues** sur des tokens qui existent plutôt que déclarées : le système n'avait pas besoin de trois teintes de plus, il avait besoin qu'on cesse d'en inventer. Plus trois replis morts purgés. Recherche mécanique (50 déclarées, 47 utilisées, comparaison d'ensembles), pas à l'œil. Contre-preuve : 2,58:1 sur l'ancien CSS, 5,74:1 après. |
| 17.5 | Les portes qui auraient vu 17.2 et 17.4 | M | ✅ Livrée le 2026-07-20 — la porte `contraste` n'auditait que l'état **au repos** ; le survol lui échappe par nature et la frise de suivi vit dans un panneau qui s'ouvre au clic. États désormais **déclenchés** puis mesurés sur le style calculé. Deux pièges mesurés : on survole une ligne **PAIRE** (celle que le zébrage recouvrait, donc celle qui masquait 17.2), et on **éloigne la souris** après `check()` — sinon on mesure le survol en croyant mesurer la sélection. Le passage publie ce qu'il a touché (2 survols, 2 sélections, 1 panneau / 12 lignes) : sur un clone sans commandes il ne vérifie rien, et ça doit se lire. Plus le balayage de `wash()` sur **16 384** couples accent × encre (pire 5,04:1) et le témoin de sa raison d'être (`-soft` tombe à 4,15:1). Au passage : le compte du balayage `best_ink()` était **écrit** et non compté — 4 913 annoncés pour 4 096 parcourus, couverture surévaluée de 20 %. Nouveau `test-charte-surfaces.php` : aucune couleur de client en dur, aucune variable fantôme, aucun repli mort, et le repli `:root` conforme à ce que `css_vars()` produit. |
| 17.6 | La surface flottante, écrite une fois | M | ✅ Livrée le 2026-07-20 (v0.46.0) — **l'objet annoncé de la phase**. `statussel-menu` et `csel-menu` étaient identiques AU CARACTÈRE PRÈS ; `catdd-panel` en était une copie dérivée avec une ombre à 14px au lieu de 16 — écart non décidé, invisible tant que les blocs étaient séparés. Mutualisé : la **peau**. Séparé : le **positionnement**, et ce n'est pas un reliquat — les deux menus sont `fixed` et placés par le JS (ils doivent échapper au défilement d'un tableau), le panneau est `absolute` et ancré à son parent. 🚨 **Animation fantôme trouvée en ouvrant** : les deux menus demandaient `aod-cd-dd-in`, un `@keyframes` **jamais déclaré** — `animationName` rendait le nom, `getAnimations()` rendait `[]`. Ils se sont ouverts **d'un coup** pendant toute leur existence. Rabattus sur `aod-cd-pop`, qui existe et décrit la même chose (même arbitrage qu'en 17.4). Conséquence assumée — ces surfaces s'animent désormais alors qu'elles ne s'animaient pas — d'où sa neutralisation sous `prefers-reduced-motion`, **pas** en `animation:none` (ça supprimerait aussi le fondu, soit l'état qu'on vient de corriger) mais via `aod-cd-fade`. Piège évité : `[hidden]{display:none}` vient de la feuille du **navigateur** et perd contre toute règle d'auteur posant un `display` — mettre `display:flex` dans la règle commune aurait laissé le panneau de catégories **ouvert en permanence**. Le cliquet des fantômes est généralisé aux `@keyframes`. **Phase 17 soldée (7/7).** |
| 18.1 | La porte `mouvement` étendue au plugin public | S/M | ✅ **Livrée le 2026-07-20** — 5 feuilles au lieu d'une (`dashboard.css` + les 3 publiques + le `<style>` inline du switcher, repéré par `ownerNode.id` : il n'a pas de `href`). **229 + 6 règles** entrent dans le champ, soit 21 % du CSS jamais inspecté. Chaque feuille publie son invariant `count === expected` et son plancher `inspected` **mesuré** (le `700` de dashboard.css sur une feuille de 26 règles rougirait d'office). Feuille absente = **échec nommé**, jamais `exit 3` — ce code est global au process et emporterait l'audit de `/gestion`. Plus un **cliquet statique** sur tout `plugins-dev/` (4 CSS + 3 blocs `<style>`), parce qu'un audit CSSOM ne voit que les feuilles nommées en dur. **Éprouvée au rouge 3 fois** : transition nue en CSS public (les 2 cliquets rougissent, le sélecteur est nommé), transition nue dans le bloc inline, `expected` faussé. Seed COD étendu d'une offre : sans 2ᵉ carte, `.aod-cod__offer-check` — dont l'état est porté par un **ancêtre** — n'avait aucun état observable. 🚨 Piège trouvé en implémentant : les options sont **dupliquées dans chaque carte** et seul le panneau de la carte sélectionnée est visible ; un `.aod-cod__opt` global expirait à 30 s sur une option masquée. |
| 18.2 | Hygiène : 4 clés i18n mortes + `.gitignore` de `library/` | S | ✅ **Livrée le 2026-07-20** — les 4 clés (`i18nCatRename`, `i18nCatDelete`, `i18nImpCurrent`, `i18nImpNew`) retirées après vérification qu'aucun **accès dynamique** (`CD['i18n'+x]`) ne les consommait : un grep littéral seul n'aurait pas suffi à conclure. `.gitignore` passé de `library/**/*.zip` à `library/**` + négations : la règle est **éprouvée par `git check-ignore`** sur un `.tar.gz`, un `.pdf`, un `.zip` et un dossier décompressé — tous ignorés, `.gitkeep` toujours suivi. ⚠️ Les `.gitkeep` n'ont pas pu être créés sur ce clone (`library/` appartient à `root`, créé par Docker) : la règle est correcte et prête, les fichiers restent à poser sur un clone où le dossier est accessible. |
| 18.3 | Revue manuelle des 33 `phpcs:ignore` non audités | M | ✅ **Livrée le 2026-07-20 — 0 faille.** Les 33 ne forment pas 33 cas : **toutes** passent par `AOD_CD_Icon::svg()` ou `empty_state()`, dont la sûreté se démontre une fois. Catalogue **fermé et littéral** (58 entrées, 0 valeur avec `/` ou `..`), nom inconnu → chaîne vide + `_doing_it_wrong()` (y compris pour les 5 appels à nom **dynamique**), `class` en `esc_attr`, `size` en `(int)`. 🚨 **La prémisse du verdict n'était pas gardée** : `inner()` recopie le fichier verbatim. Témoin ajouté — les **71** SVG embarqués sont assertés sans `<script>`/`on*`/`<use href>`, avec le compte de fichiers lus (un `glob` vide se déclarerait vert sinon). **Éprouvé au rouge** : SVG hostile déposé → 2 garde-fous rougissent. 359 tests (+2). ⚠️ Reste un **audit**, pas un cliquet : la 131ᵉ annotation pourra arriver non relue (D6 assumé). |

| 19.1 | `prefers-reduced-motion` côté public | S/M | ✅ **Livrée le 2026-07-20** — surcharges en **fin de fichier** (une surcharge placée avant ses cibles perd la cascade en silence), mouvement retiré, **acquittement non moteur conservé**. 🚨 Le cas mixte traité à la main : `.aod-gallery-nav:active` vaut `translateY(-50%) scale(.94)` — neutralisé en `translateY(-50%)`, **pas** `none`, qui aurait décentré la flèche. Les 6 `transform` de positionnement (dont la variante **RTL** `translate(50%,-50%)`) sont intacts. Porte étendue : bloc 6 de `mouvement.js`. 🚨 **Défaut trouvé dans le contrôle lui-même** : `transform:none` étant aussi la valeur par défaut, la 1ʳᵉ version restait verte quand la cascade était perdue — elle prouvait seulement que `:active` ne s'appliquait pas (le bouton, bas dans la page, n'était jamais atteint faute de `scrollIntoViewIfNeeded`). Corrigé par une mesure en **deux temps** : on établit d'abord que l'appui produit un mouvement SANS le réglage. **Éprouvé au rouge** : surcharge déplacée en tête → 2 échecs. |
| 20.1 | Embarquer Barlow dans le plugin (retirer le CDN) | S/M | ✅ **Livrée le 2026-07-21** — 5 `woff2` `latin` (113 Ko) dans `assets/fonts/` + `LICENSE` OFL-1.1, `@font-face` en tête de `dashboard.css`, les 3 `<link>` retirés. **Vérifié au navigateur** : Barlow sert depuis `/wp-content/…/assets/fonts/`, et `/gestion` sur 5 sections n'émet **aucune** requête externe. 🚨 Piège du contrôle lui-même : le premier relevé a vu 2 appels Gravatar — ils venaient de l'étape de **connexion via `wp-admin`**, pas de `/gestion` ; collecteur réarmé **après** `login()` pour ne pas rapporter un défaut inexistant. 🚨 Une affirmation de la rédaction initiale **démentie par la mesure** : « un écran 100 % arabe ne télécharge rien » — faux, il tire les mêmes 4 fichiers, le nom de boutique et les chiffres restant latins ; le gain de l'`unicode-range` est **par graisse** (la 500 ne sert nulle part), pas par langue. Cliquet `test-ressources-locales.php` (28 assertions), **éprouvé au rouge 2 fois** : `<link>` CDN réintroduit et `@font-face` pointant un fichier absent — ce second cas étant celui qui compte, un `src` mort ne produisant **aucune** erreur navigateur. Portes rejouées : `mouvement` (885 = 885 règles), `rtl` (12 sections × FR/AR/mobile), `contraste` (6 accents). 387 tests (+28). Original — `class-aod-cd-dashboard.php:385-387` charge Barlow depuis `fonts.googleapis.com` (2 `preconnect` + 1 feuille), en contradiction frontale avec `assets/icons/README.md` (« sans npm, sans CDN, sans build ») et `ICONES-ET-CHARTE-GESTION.md` §2 (« aucun CDN, aucun appel réseau au rendu »). Sans réseau — le cas nominal d'un clone neuf — la typo retombe **en silence** sur la police système : le mode d'échec exact que la Phase 15 a éliminé pour les emojis, en pire, car une police de repli reste crédible. S'y ajoutent une requête tierce portant l'IP du gérant à chaque page d'un back-office `noindex`, et 2 allers-retours avant le premier texte peint. 🚨 Deux pièges : **Barlow n'a aucun glyphe arabe** (l'AR est déjà servi par le repli système et doit le rester — pas un reste à traiter) ; et le poids se joue au **sous-ensemble**, pas à la graisse (15 fichiers en amont, mais `latin` couvre tout le français → **5 fichiers**). Licence **OFL-1.1** à joindre (Barlow n'est pas MIT comme Phosphor). DoD : un **cliquet statique** qui refuse tout hôte externe dans `plugins-dev/` — pas une relecture, et pas une liste noire de domaines. |
| 21.1 | Aligner la garde divergente + cliquet des permissions AJAX | S/M | ✅ **Livrée le 2026-07-21** — **une seule ligne de production** : `ajax_test_carrier` passe de `manage_woocommerce` à `AOD_CD_Roles::user_can( 'aod_cap_shipping_prices' )`. Le livrable est le cliquet `tests/unit/test-permissions-sections.php` (37 assertions) : énumération **dynamique** via `$GLOBALS['wp_filter']` + `ReflectionMethod` — **jamais une liste de fichiers**, qui aurait laissé un 28ᵉ handler d'un 6ᵉ fichier **hors scan** (le défaut de la 18.1 dans le test censé le prévenir). **Éprouvé au rouge 3 fois** : desserrer `reset_shop` → « 🚨 TROU », durcir `tracking_detail` → « MUR », retirer une entrée → « HANDLER INCONNU ». Les 3 divergences **voulues** (`delete_orders`, `save_stock`, `save_account`) passent au vert **parce que la table porte leur raison** — sans la famille `action-forte`, le test rougissait sur du code délibéré. 🚨 **Vérifié au navigateur CONNECTÉ EN CONFIRMATEUR**, ce qu'aucune porte ne fait (D13) : `test_carrier` répond **403 avant / 200 après**, et « Mon compte » reste invisible (non-régression D12). Au passage, `ARCHITECTURE.md` §AJAX disait **faux** (« tous les autres exigent le gérant » alors qu'`import_fees` passait par `aod_cap_shipping_prices` depuis la Phase 4) — corrigé. 424 tests (+37). |

| 22.1 | `login()` paramétrable + témoin d'identité | S/M | ✅ **Livrée le 2026-07-21** — `login( page, { user, pass } )`, **les 10 portes inchangées**. `whoAmI( ctx )` lit le cookie `wordpress_logged_in_*` : 🚨 les 3 voies « évidentes » ont été **essayées et écartées** — REST `users/me` rend **401** (nonce REST absent de `/gestion`), le DOM ne montre le login que par accident, `window.AOD_CD` ne porte rien (l'y ajouter aurait modifié le **plugin** pour un **test**). Cookie `HttpOnly` → `ctx.cookies()`. 🚨 **Purge inconditionnelle** : la garde « identité déjà bonne → rendre la main » aurait **menti** (un cookie survit à `wp_destroy_all_sessions()`), et ses branches étaient **inatteignables** (les 10 portes appellent `login()` une fois, sur contexte neuf). `STATE` retiré de ses **4** sites ; **le paramètre `state` conservé** (15 appels dans 7 fichiers). 🚨 **Défaut trouvé en implémentant** : `catalogue-bilingue:296` et `description-riche:303` appellent `login()` SUR un contexte `state:false` — un garde-fou « pas de login sur contexte anonyme » aurait cassé 2 portes. **Non-régression prouvée par `run.sh tout` : 0 échec, 0 porte retirée, 17 témoins d'identité.** 424 tests unitaires verts. |
| 22.2 | Porte `run.sh confirmateur` | M | ✅ **Livrée le 2026-07-21** — **34 assertions, aucune ligne de production touchée** : il n'y avait aucun défaut à corriger, le livrable EST la porte. 🚨 **DEUX comptes aux jeux de caps COMPLÉMENTAIRES** (4 + 3 = les 7) plutôt qu'un seul : un compte « tout coché » ne prouverait que les droits accordés, et un seul compte partiel n'éprouverait ses caps manquantes que dans UN sens — une capability devenue inopérante (renommée, jamais lue) rendrait le même vert. B ouvre exactement ce que A ferme : **les 7 caps sont vues ouvrir ET fermer**. 🚨 **Éprouvée au rouge** : retirer `aod_cap_shipping_prices` au seed → 4 échecs, dont `MUR : test_carrier … HTTP 403` — **la signature exacte du défaut de la Phase 21**, que cette porte aurait donc attrapé. 🚨 **Deux lectures de code démenties par la mesure** : un confirmateur sans cap ne voit PAS l'écran Commandes (`render_app()` garde par `isset( $routable[...] )`, `:573`), et `wp-admin` ne rend pas 403 mais **redirige** vers `/gestion` — une assertion `status >= 400` était rouge sur un produit sain. `carrier=` **vide** obligatoire : nommer un transporteur ferait ÉCRIRE les réglages et appeler l'API du livreur **pour de vrai**. Purge **par méta**, jamais par rôle (2 confirmateurs de l'owner sur le clone). 424 tests verts. |
| 23.1 | Cliquet sur les `phpcs:ignore` | M | ✅ **Livrée le 2026-07-21** — **une seule ligne de production** : l'annotation **NUE** de `section-products.php:500` (`// phpcs:ignore` sans nom de sniff — elle désarmait **tous** les contrôles de la ligne, pas un). 🚨 Elle avait échappé à l'audit de la 18.3 parce que cet audit grepait **par nom de sniff** : le scénario « 131ᵉ annotation non relue » de D6 était **déjà réalisé**, dans l'angle mort de l'audit. 🚨 **D6 annonçait mal sa propre portée** : « 130 relues » ne comptait que la famille `EscapeOutput` — les **40** autres (`NonceVerification` CSRF, `ValidatedSanitizedInput`, `WordPress.DB`), les plus sensibles, n'avaient **jamais** été auditées. Audit des 40 : **0 suspecte**. Livrable = cliquet `test-phpcs-ignore.php` (R1 aucune annotation nue · R2 census par famille, sécurité marquée 🔒 · R3 plancher 141 sans-raison · témoin de balayage). **Éprouvé au rouge** : annotation nue + une de plus en famille CSRF → 3 règles rougissent en NOMMANT fichier et ligne ; restauration contrôlée après coup. R3 est un **plancher assumé**, pas un zéro : exiger une raison sur 141 lignes de production pour satisfaire un test serait la queue qui remue le chien. 436 tests (+12). |
| 24.1 | Cliquet sur le contrat de nonce des lectures de superglobale | M | ✅ **Livrée le 2026-07-21** — **deux lignes de production** : les deux `capi_token_from_post()` passent en `private static`. Elles lisent `$_POST` **sans le recevoir en argument** ; leur sûreté ne tenait qu'à une phrase de docblock (« le nonce est vérifié par l'appelant »), et côté COD le helper était `public static` — appelable de partout. `private` rend le contrat **structurel**. Livrable = cliquet `test-nonce-contract.php` : les **59** méthodes lisant une superglobale sont énumérées **dynamiquement** ; **33** noncent, **5** délèguent (résolu), **16** sont déclarées **avec leur raison**, et les **5** helpers ne se déclarent pas — le test **énumère leurs appelants** et exige que chacun nonce. 🚨 **Cinq faux positifs tués par la mesure** : 4 handlers AJAX (`ajax_save_member`, `ajax_delete_member`, `ajax_centers`, `ajax_send`) semblaient sans nonce — ils délèguent d'un cran. **Un cliquet qui ne résout pas la délégation crie au loup sur du code sain, puis se fait assouplir.** 🚨 **La 5ᵉ, ratée par l'auteur et trouvée par la relecture indépendante** : `AOD_CD_Account::render_page` délègue depuis un appel **imbriqué dans un `if`** ; la classer « lecture d'affichage » aurait gravé une **fausse déclaration de sécurité sur la méthode qui appelle `wp_insert_user()`**. ⚠️ Limites écrites dans l'en-tête : **statique** (aucune requête forgée) et **indifférent à l'ordre** lecture/garde — l'exiger ferait rougir 3 méthodes saines. **Éprouvé au rouge 3 fois** (appelant non noncé → R4 · entrée retirée → R1 · visibilité rouverte → R5). R5 n'existait pas d'abord : sans lui, rouvrir la visibilité était une régression **verte**. 453 tests (+17). |

| 25.1 | Cliquet sur les résidus de désinstallation | M | ✅ **Livrée le 2026-07-21** — **Trois résidus réels.** 🔴 Les **8 capabilities posées PAR UTILISATEUR** (`WP_User::add_cap()`) n'étaient retirées par rien : `uninstall.php` ne bouclait que sur `$roles->role_objects`, et `remove_role()` ne touche pas le usermeta `{prefix}capabilities`. **Vérifié sur le clone : les 2 confirmateurs réels portent chacun 2 caps** qui auraient survécu au plugin — des droits qu'on croit révoqués et qui ne le sont pas. 🔒 `aod_cod_turnstile` (**clé secrète Cloudflare**) survivait en clair. Et le motif de transients du dashboard avait **dérivé** (il nommait une section ; la suivante a copié le mécanisme). Métas de contenu marchand désormais **conservées à dessein, décision écrite**. 🚨 **La v1 de la story était un contre-exemple d'elle-même** : intitulée « options orphelines », elle a audité `wp_options` et déclaré la désinstallation propre — en laissant dehors le résidu le plus grave. **Le titre avait servi d'œillère** ; trouvé par la relecture indépendante. 🚨🚨 **Le correctif de production a désarmé le cliquet de sa propre story** : le `SELECT … usermeta … LIKE '%'.esc_like('aod_').'%'` introduit pour énumérer les porteurs a été moissonné comme une preuve de suppression — `aod_` préfixant toute option, **R1 est devenue universellement verte**. Détecté **uniquement** parce que la preuve au rouge a refusé de rougir ; seuls les motifs d'un `DELETE FROM …options` comptent désormais. ⚠️ Limites en en-tête : **statique**, et la **propriété** d'une option n'a aucun signal mécanique (« qui écrit possède » est faux dans les deux sens) — elle reste **déclarative**. **Éprouvé au rouge 3 fois** ; **R4 ne rougissait pas d'abord** (elle jugeait sur l'**union** des deux fichiers, où le motif large du COD masque la dérive du dashboard) → chaque plugin est jugé sur **son** `uninstall.php`. Retrait des caps **exécuté pour de vrai** sur un compte jetable, comptes réels intacts. 489 tests (+36). |

| 26.1 | Couches anti-bot : source d'IP forgeable + fail-open muet | M | ✅ **Livrée le 2026-07-21** — **Deux des 5 couches annoncées n'en étaient pas.** 🔴 Le coupe-circuit IP s'indexait sur `WC_Geolocation::get_ip_address()`, qui renvoie `X-Real-IP` **brut** (`class-wc-geolocation.php:83-84`, sans le `rest_is_ip_address()` de ses branches suivantes) : `X-Real-IP: <aléatoire>` par requête = compteur neuf, plafond jamais atteint. **Le défaut : réutiliser une source de CONFORT (géoloc commerciale) comme source de SÉCURITÉ.** 🔴 Turnstile retournait « valide » sur erreur réseau **sans borne ni trace** — contournement à un caractère (`cf_turnstile=x`), état **permanent** sur un clone sans réseau sortant (le cas nominal de TEMPLATE.md §3), pendant que l'écran affichait « ✅ Actif ». **Le défaut n'était pas la politique, c'était son silence.** Correctif : `AOD_COD_IP::client()` (REMOTE_ADDR ; en-têtes de proxy seulement si `AOD_COD_TRUSTED_PROXY`, **et validés**) + fail-open **borné** (`AOD_COD_TURNSTILE_MAX_FAILOPEN`, 20) **et visible** (alerte `/gestion → Accueil` dès le 1ᵉʳ échec). Politique arbitrée par l'owner. 🚨 **Le cliquet a rougi sur ses propres commentaires** — ils nomment `WC_Geolocation` pour expliquer la correction ; il analyse désormais les jetons (`token_get_all()`), pas le texte. **Éprouvé au rouge 2 fois**, dont la reproduction exacte de la faille (`obtenu 'forge-abc-123'`). 4 chaînes AR traduites. 512 tests (+23). |
| 27.1 | Quatre portes de recette qui verdissaient sans tout éprouver | M | ✅ **Livrée le 2026-07-21** — **Aucune ligne de production ; le livrable est la recette qui redevient un juge.** 🔴 `mouvement.js` : `.aod-cd-tab` (reliquat 5.4, remplacé par `.aod-cd-seg__btn`) ne matchait plus rien → 3 assertions escamotées **en silence à chaque run depuis la Phase 16** (`if ( ! count ) continue;`). Entrée retirée + **témoin** exigeant que chaque cible existe. 🔴 `a11y-contraste.js` : « 6 accents » pouvait n'en éprouver qu'**un** (la porte ne LISAIT jamais l'accent ; `theme mod set` à sortie avalée) — **et** un chemin destructif effaçait la couleur de marque du gérant (`#00A651`) via `restore_accent()` sur capture vide. Témoin d'accent (`--cd-accent` lu sur la page) + **sentinelle** distinguant « pas d'accent » de « lecture impossible ». 🔴 `a11y-souris.js` : bloc catégories `if ( count )` **sans else** sur un bouton rendu inconditionnellement → `t( false )`, comme `a11y-clavier.js` le fait déjà. 🔴 `description-riche.js` : l'absence de la description dans `/gestion` était vérifiée **après** suppression de la commande — vraie par construction ; **remontée avant**, adossée à une présence. **Éprouvé au rouge** partout ; accent owner intact après la passe. |
| 28.1 | Tri des 316 violations phpcs jamais examinées | M | ✅ **Livrée le 2026-07-21** — phpcs+WPCS **jamais exécutés ici** ; une fois lancés, **316 violations de sécurité non annotées**. Triées (3 auditeurs indépendants + revérif perso des constats sensibles) : **316/316 faux positifs**, **0 faille réelle**. SQL 14 (noms de table + fragments préparés), CSRF 110 (20 méthodes toutes noncées par délégation), sanitize 66 (whitelist maison + mot de passe), XSS 96 (helpers d'échappement ; `page_head($actions)` vérifié sur ses 14 appelants), DirectDB 30 (table custom). WPCS ne suit ni la délégation, ni les sanitiseurs/échappeurs maison, ni l'interpolation de nom de table. 🔎 **Défaut latent corrigé** : 2 annotations à séparateur em-dash « — » (au lieu de « -- ») que phpcs n'honorait pas — **inertes**, l'annotation nue de R1 un cran plus loin ; code sûr, zéro impact. Nouvelle règle **R4** (`test-phpcs-ignore.php`), resserrée sur les jetons de sniff (un 1er jet flaguait un em-dash de CODE). **Éprouvée au rouge.** Débloque D14 (phpcs disponible), sans la solder. 513 tests (+1). |
| 28.2 | Resserrer les 7 suppressions `WordPress.DB` larges (dette D14) | S | ✅ **Livrée le 2026-07-21** — les 7 `// phpcs:ignore WordPress.DB` (qui coupaient TOUTE la famille DB) resserrées sur les sniffs exacts : `WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery` (ligne 136 : `DirectDatabaseQuery` seul, son `prepare()` est sur la ligne suivante), **chacune avec sa raison**. 🔧 **Vérifié à l'outil** : phpcs annotations honorées → **zéro résidu** sur les 7 lignes (les sniffs étroits couvrent exactement ce que `WordPress.DB` couvrait). Le gain : une suppression ne masque plus toute la famille — un `SlowDBQuery`/`RestrictedFunctions` futur sur ces lignes rougirait. Census R2 `WordPress.DB` inchangé (13) ; plancher R3 sans-raison 139 → 132. D14 soldée. **0 ligne de logique changée** (7 commentaires). 513 tests. |
| 29.1 | La page de connexion ignorait la marque du client | S | ✅ **Livrée le 2026-07-21** — `wp-login.php` codait son accent EN DUR (`$accent = '#111827'`) : le bouton de connexion restait gris-ardoise quel que soit l'accent du client — entorse à la Règle d'or n°1 sur le premier écran du gérant. La page injecte désormais la même charte que /gestion (`AOD_CD_Customize::css_vars()`) ; le bouton prend `var(--cd-accent)` + `var(--cd-accent-ink)` (encre lisible best_ink, AA garanti par test-contraste). 🚨 Piège de contraste évité des deux côtés : le nom du site en repli reste sur un NEUTRE (pas l'accent — sur fond clair, un accent clair serait illisible). Livrable = `test-login-charte.php` (capture le CSS injecté ; R1 charte, R2 bouton lit l'accent, R3 pas de hex en dur). **Éprouvé au rouge.** Census EscapeOutput 132 → 133 (echo css_vars, justifié). 518 tests (+6). |
| 30.1 | Pilote de décomposition : extraire la section WhatsApp | M | ✅ **Livrée le 2026-07-22** — 1re pièce du découpage de `class-aod-cd-dashboard.php` (4229 l). L'architecture cible EXISTE déjà (convention `AOD_CD_Section_*`, 6 sections déjà extraites) : le chantier FINIT le pattern. `AOD_CD_Section_WhatsApp` sort du monolithe **à comportement constant** — mêmes actions AJAX (dashboard.js inchangé), move verbatim des annotations (census phpcs stable). **Prouvé à 4 niveaux** : 518 tests, hooks enregistrés au runtime, rendu identique (2964 o), porte recette `rtl` verte (/whatsapp en FR desktop/mobile + AR mobile). Monolithe 4229 → 4107 l. Garde = `test-decomposition.php` : R1 plafond de lignes (descend à chaque section), R2 anti-ré-absorption (la méthode a QUITTÉ le monolithe). **Éprouvé au rouge** (stub section_whatsapp remis → R1+R2 rougissent). 529 tests (+11). Cadré avec l'owner : PHP d'abord, pilote puis on rejuge. |
| 30.2 | Décomposition : extraire la section Pixels & Tracking (marketing) | M | ✅ **Livrée le 2026-07-22** (`e0ee889`) — `AOD_CD_Section_Marketing`. Move verbatim, mêmes actions AJAX (`aod_cd_save_pixels`, `aod_cd_test_capi`), `dashboard.js` inchangé. Monolithe 4107 → 3764 l. Plafond R1 abaissé. |
| 30.3 | Décomposition : extraire la section « Mon compte » (account) | M | ✅ **Livrée le 2026-07-22** (`e5cd0bc`) — `AOD_CD_Section_Account` (+ reset boutique). Move verbatim, mêmes actions AJAX (`aod_cd_save_account`, `aod_cd_reset_shop`). Monolithe 3764 → 3425 l. ⚠️ Ne pas confondre avec `AOD_CD_Account` (écran wp-admin « Accès Gérant »), sans rapport et chargée à côté — piège de nommage relevé à l'audit. |
| 30.4 | Décomposition : extraire la section « Livraison » (shipping) | M | ✅ **Livrée le 2026-07-22** (`70dd2ef`) — `AOD_CD_Section_Shipping`. Move verbatim, mêmes actions AJAX (`aod_cd_save_shipping`, `_import_fees`, `_test_carrier`). Les helpers de calcul `apply_margin()`/`import_apply()` (purs) suivent la section ; `test-tarifs-et-roles.php` a suivi la classe. Monolithe 3425 → **2836 l**. 562 tests. **Reste 2 sections** : `tracking` (~360 l) puis `orders` (~590 l + ~550 l d'AJAX, le plus intriqué, en DERNIER). |
| 30.5 | Décomposition : extraire la section « Suivi commande » (tracking) | M | ✅ **Livrée le 2026-07-22** — `AOD_CD_Section_Tracking`. Move verbatim, mêmes actions AJAX (`aod_cd_tracking_refresh`, `aod_cd_tracking_detail`), `dashboard.js` inchangé. La constante `TRACK_BLOCKED_DAYS` et les statiques `tracking_tone()`/`tracking_pill()` suivent la section (leur unique appelant externe, `AOD_CD_Section_Home::delivery_pill()`, a suivi la classe). Les helpers de PÉRIMÈTRE `scoped_orders()`/`order_in_scope()` restent au Dashboard **à dessein** (passés `public`, raison écrite) : c'est la logique de toutes les listes de commandes, ils partiront avec `orders` (30.6). Monolithe 2884 → **2397 l** (−487) : les deux remontées assumées de la Phase 32 (+13 gardes 32.1, +35 tranches 32.4) sont parties avec la section, comme le cliquet l'annonçait. 3 cliquets ont suivi la classe (`test-suivi-lots` R5, `test-degrade-cod` R2, `test-nonce-contract` exemption) — c'est le cliquet de décomposition lui-même qui les a pointés en rougissant. Porte `rtl` verte. |
| 30.6 | Décomposition : extraire la section « Commandes » (orders) | L | ✅ **Livrée le 2026-07-23** — `AOD_CD_Section_Orders`, la DERNIÈRE : le pattern est fini, le monolithe passe de 2397 à **900 l** (−1497) et n'est plus que la coque (document + routeur) et les composants d'UI partagés (`page_head`, `kpis`, `pill`, `empty_state`, `render_pair`, recherche, pagination). Move verbatim, mêmes actions AJAX (`aod_cd_order_status`, `_order_detail`, `_order_note`, `_order_save_info`, `_delete_orders`), `dashboard.js` inchangé. Les helpers de PÉRIMÈTRE `scoped_orders()`/`order_in_scope()`/`product_orders_join_sql()` suivent la section comme la 30.5 l'annonçait ; leurs appelants externes ont suivi la classe (`AOD_CD_Section_Tracking` ×3, `AOD_CD_Section_Home` ×2), ainsi que l'exemption de `test-nonce-contract.php` (`AOD_CD_Section_Orders::render`, ex-`section_orders`). Le hook de cycle de vie `reduce_pack_components_stock()` (`woocommerce_order_status_*`) suit aussi : il agit quel que soit l'écran d'où vient le changement de statut. ⚠️ Ne pas confondre avec `AOD_CD_Orders` (lots, 31.6) — même piège de nommage qu'en 30.3. 🔎 Trouvé en chemin : `order_articles_html()` n'a plus AUCUN appelant (la colonne « Articles » est partie en 5.4) — déplacée verbatim quand même (la règle du move : une extraction ne supprime rien), purge à faire dans une retouche dédiée. Cliquet éprouvé AU ROUGE (stub `section_orders()` réintroduit → R2 rougit sur la bonne ligne). Plafond R1 abaissé à 900 : il ne descend plus par extraction, il garde désormais contre un RETOUR du fichier-dieu. 832 tests ; portes `rtl` ET `confirmateur` vertes — la seconde parce que le périmètre produits a déménagé : ses 7 capabilities revues ouvrir ET fermer depuis sa nouvelle classe. |
| 33.1 | Décomposition JS — pilote : registre + coque + module WhatsApp | M | ✅ **Livrée le 2026-07-23** — l'infrastructure de la Phase 33, payée une fois : la coque `dashboard.js` gagne un REGISTRE (`register( name, fn )`, exposé via `window.aodCdCore` avec les 14 helpers partagés) et l'ORDRE CANONIQUE d'exécution (verbatim l'ordre d'avant — l'ordre des balises `<script>` ne décide de rien) ; un nom listé sans module ou un binder hors liste = échec NOMMÉ en console (politique 32.5). `render_app()` imprime `dashboard_scripts()` (coque en premier) ; pilote : `section-whatsapp.js` (IIFE à alias locaux, corps verbatim). ⚠️ La coque GROSSIT au pilote (3488 → 3506 l, le registre) et le monolithe PHP remonte de 900 → 923 (`dashboard_scripts()` : du code de COQUE, remontée assumée écrite dans le cliquet PHP). Cliquet `test-decomposition-js.php` (R1 plafond · R2 anti-ré-absorption · R3 ORDER ⇄ census des register · R4 tout fichier de `assets/js/` est chargé, coque en premier — un module orphelin est une section morte en silence) ; `test-init-binders.php` adapté au multi-fichiers (R2 accepte `register( 'x', x )`). **Éprouvé au rouge sur R1, R2 ET R4** (stub ré-absorbé + module fantôme → 3 échecs nommés). 844 tests (+12) ; porte `rtl` verte. |
| 33.2 | Décomposition JS — les 6 sections simples | M | ✅ **Livrée le 2026-07-23** — `section-home.js` (bindCharts), `section-marketing.js` (bindCapiTest, bindPixelCards), `section-account.js` (bindResetShop), `section-stock.js` (bindStock), `section-team.js` (bindTeam), `section-tracking.js` (bindTracking). Coque 3506 → **2948 l**. Alias locaux calculés depuis le corps RÉEL de chaque module (pas de copier-coller d'en-tête : un alias mort mentirait sur les dépendances). Le cliquet `test-suivi-lots.php` (R5, enchaînement des tranches 32.4) a suivi bindTracking dans son module. ⚠️ Plafond PHP posé à l'ÉTAT FINAL (933) : la liste `dashboard_scripts()` grandit d'une ligne par module — croissance de coque bornée par construction (12 fichiers à terme), écrite dans le cliquet plutôt que 4 remontées successives. 864 tests (+20) ; porte `rtl` verte. |
| 33.3 | Décomposition JS — la section Livraison (shipping) | M | ✅ **Livrée le 2026-07-23** — `section-shipping.js` (bindShippingFree, bindCarrierRows, bindCarrierTest, bindShippingSearch, bindImportFees — dont l'import de tarifs, le plus gros binder du dépôt : préview/apply/restore). Coque 2948 → **2561 l**. 🔎 Corrigé en chemin : le commentaire « Miroir JS de `AOD_CD_Dashboard::apply_margin()` » pointait une classe qui n'a plus cette méthode depuis la 30.4 — realigné sur `AOD_CD_Section_Shipping` (même dérive que celle relevée dans ARCHITECTURE.md en 30.6 : un move PHP n'avait pas suivi ses mentions JS). 875 tests (+11) ; porte `rtl` verte. |
| 33.4 | Décomposition JS — Produits & Catégories (products) | L | ✅ **Livrée le 2026-07-23** — `section-products.js` (1036 l : bindProducts — avec l'init TinyMCE de l'éditeur riche 8.1 —, bindOptions et bindOfferRows — re-init DYNAMIQUES hors ORDER, appelés via `init()` dans bindProducts, l'alias `core.init` les couvre —, bindCategories, bindColorPalette, bindCatDropdown, bindPromoRange, bindGallery). Coque 2561 → **1539 l**. TinyMCE reste imprimé AVANT le module (contrat du chargeur, écrit dans `render_app()`). 892 tests (+17) ; porte `rtl` verte. |
| 33.5 | Décomposition JS — l'éditeur de Page de vente (landing) | M | ✅ **Livrée le 2026-07-23** — `section-landing.js` (370 l : bindLanding, l'éditeur de blocs 6.2 avec sa renumérotation). Coque 1539 → **1177 l**. 🔎 Le bandeau de section (`/* Page de vente : éditeur de blocs */`) était resté orphelin dans la coque (l'extracteur attache les commentaires ATTENANTS ; un bandeau séparé par une ligne vide lui échappe) — déplacé à la main dans le module. 895 tests (+3) ; porte `rtl` verte. |
| 33.6 | Décomposition JS — la section Commandes (orders), le chantier est fini | M | ✅ **Livrée le 2026-07-23** — `section-orders.js` (557 l : bindStatus + applyStatus, bindOrderDetail + initOrderEdit — la modale d'édition articles/total —, bindOrderNote + submitOrderNote, bindOrderBulkDelete ; la note historique 5.4 sur bindFilterDropdown a suivi). Coque 1177 → **635 l** (3488 à l'origine) : il n'y reste que les 14 helpers partagés, `init()` (32.5), le registre + ORDER et les 4 binders transverses. Plafond JS gelé à 635 : il garde désormais contre un RETOUR du fichier-dieu. **La preuve console était déjà dans la porte** : `rtl` exige `console_.length === 0` par page (lib.js) — chaque run vert du chantier prouvait AUSSI, au runtime, qu'aucun nom d'ORDER n'est resté sans module et qu'aucun module ne crie « hors liste ». Battery finale : portes `rtl`, `riche` (TinyMCE depuis son module) et `categories` vertes. 910 tests (+15). |
| 34.1 | Boutons Appeler / WhatsApp — lignes Commandes + modale détail | S | ✅ **Livrée le 2026-07-23** — confirmer une commande COD, c'est appeler l'acheteur : la liste Commandes et sa modale de détail portent désormais le même rang **Appeler** (`tel:`) + **WhatsApp** (`wa.me`, 0→213) que la section Abandonnées (Phase 11), sous la même garde (rien sans téléphone — éprouvée au ROUGE sur commande témoin sans numéro, purgée). Rendu pur : aucun AJAX, aucune cap, **aucune chaîne i18n nouvelle** (« Appeler »/« WhatsApp » vivaient déjà dans le `.po` — `make-translations.sh --strict` : 0 nouvelle), aucun JS (liens natifs — `ORDER` et `dashboard_scripts()` intouchés). `wa_number()` promu `public static` sur `AOD_CD_Section_Abandoned` (le SEUL exemplaire du dashboard, consommé par les deux sections — 3ᵉ copie interdite par la leçon §7.6, et la coque est au plafond R1 : 933/933) ; peau écrite une fois : `.aod-cd-abandon-actions` renommée `.aod-cd-call-actions` (zéro référence recette/cliquet à l'ancien nom, grep vide sur `plugins-dev/`). Témoin navigateur : 13/13 lignes à téléphone portent leur rang, modale incluse, AR mobile 390 px sans débordement (اتصال / واتساب). 910 tests verts, porte `rtl` verte. Story BMAD : `_bmad-output/implementation-artifacts/34-1-boutons-appeler-whatsapp-commandes.md`. |
| 35.1 | Panier COD — socle : option `aod_cod_cart` + carte gérant (Livraison) | S/M | ✅ **Livrée le 2026-07-23** — option possédée par `aod-cod-form` (helper `AOD_COD_Form::cart_enabled()`, uninstall COD), carte « Panier » **réservée au gérant** dans la section Livraison (précédent 31.1 : masquée au rendu ET garde serveur), AJAX dédié `aod_cd_save_cart_mode` (`manage_woocommerce` — jamais un champ de `save_shipping`, sinon un confirmateur « tarifs » choisirait le mode d'achat de la boutique), entrée `action-forte` avec sa raison au cliquet permissions, census `EscapeOutput` 133 → 134 justifié (SVG catalogue fermé, AVEC raison — plancher R3 stable). **Éprouvée au ROUGE** : garde désarmée → le témoin voit HTTP 200 et rougit ; réarmée → 403, option intacte. AC1/AC2 au navigateur (confirmateur témoin semé porteur de la cap, purgé), AC3 sur les 4 états de l'option. 🔎 **Trouvé en chemin** : la porte `degrade` appelait encore `AOD_CD_Dashboard::tracking_tone()` — référence morte depuis la 30.5 (les DoD 30.x jouaient `rtl`, pas `degrade`) ; réparée, la porte passe 16 → 17 assertions réelles. 912 tests verts ; portes `confirmateur` 36/0, `degrade` 17/0, `rtl` vertes. Aucun effet public : le toggle n'a pas encore de consommateur (35.2/35.3). Story : `_bmad-output/implementation-artifacts/35-1-panier-cod-socle-toggle.md`. |
| 35.2 | Panier COD — serveur : `items[i][product_id]` multi-produits | M | ✅ **Livrée le 2026-07-23** (cod-form 1.43.0) — `handle_submit()` décomposé en `build_order_lines()` (résolution produit par item, repli racine, garde toggle, validations par produit) + `price_lines()` (offres par GROUPE de produit, jamais inter-produits), extraites exprès pour être jugées par réflexion — `wp_send_json_*` rend le handler inappelable d'un test. Toggle OFF : un item visant un autre produit que la racine est **rejeté**, pas re-routé (« OFF = la surface serveur d'avant » vaut aussi pour un payload forgé) ; un `product_id` inconnu/brouillon = erreur, **jamais de repli silencieux sur la racine** (re-tarifer un autre produit serait le pire des replis). Aval intact : livraison une fois, métas `_aod_*`, verrou téléphone, `aod_cod_order_created`, catch journalisé. Cliquet `test-panier-cod.php` (R1 rétro-compat à la formule historique · R2 étanchéité des groupes, contre-épreuve 1×A+1×B comprise · R3 garde toggle **éprouvée au rouge** (garde désarmée → 2 rouges nommés) · R4 payloads hostiles ; fixtures semées/purgées, option restaurée). 933 tests (+21) ; porte `cod` verte (payload d'hier → commande d'hier, au navigateur, JS inchangé) ; porte `rtl` verte. Story : `_bmad-output/implementation-artifacts/35-2-panier-cod-serveur-multiproduits.md`. |
| 35.3 | Panier COD — public : bouton Ajouter + panneau + soumission | M/L | ✅ **Livrée le 2026-07-23** (cod-form 1.44.0) — `AOD_COD_Cart` (bouton flottant + panneau au `wp_footer`, endpoint lecture seule `aod_cod_cart_items` : nonce, plafond 30 items, produits publiés seuls, indisponibles signalés et exclus du total), store `localStorage.aod_cart` (IDs + options FR canoniques + qty, **jamais** de nom/prix stocké — repeint à chaque ouverture dans la langue courante), `estimate_lines()` = pont public vers la formule EXACTE du serveur (offres comprises, zéro duplication). « Ajouter au panier » sur la fiche (réutilise `collectItems()` — variantes composées incluses) ; « Commander » → fiche du 1er article + ancre `#aod-commander` ; **panier non vide ⇒ la soumission envoie LES ARTICLES DU PANIER** (bandeau au-dessus du bouton, la sélection de page s'ajoute par le bouton Ajouter — exiger ses options bloquerait un visiteur venu du panneau) ; succès → panier vidé. PAS de formulaire dans le drawer (3 pièges mesurés : init JS au chargement de page, Turnstile non auto-rendu en DOM injecté, total = serveur). Toggle OFF : **zéro asset, zéro octet de DOM** (prouvé au témoin, requêtes réseau comprises). Anti-bot inchangé (même chemin de soumission). 🔎 **Le témoin a attrapé un vrai bug avant tout humain** : `display:flex` du drawer **écrasait l'attribut `hidden`** — panneau affiché en permanence, clics de la colonne droite morts ; règle `[hidden]{display:none}` posée avec sa raison. Catch muet `localStorage.setItem` recensé AVEC raison au cliquet R4 (panier volatil voulu en navigation privée). 12 chaînes AR traduites (`--strict` vert). Témoin bout-en-bout mobile 390 : OFF/ON, badge, panneau FR + AR (سلتك, RTL sans débordement), commande réelle relue en base (2 lignes aux prix serveur + livraison wilaya = 2100), purge complète. 935 tests ; portes `cod`, `public`, `rtl` vertes. Story : `_bmad-output/implementation-artifacts/35-3-panier-cod-public.md`. |
| 35.4 | Panier COD — i18n + porte de recette `panier` | S/M | ✅ **Livrée le 2026-07-23** — **clôture de la Phase 35.** Nouvelle porte `bash tools/recette/run.sh panier` (incluse dans `tout`) : les DEUX faces du toggle en visiteur réel mobile 390 — OFF = pas un octet (ni bouton, ni fab, ni asset ; requêtes réseau surveillées, ancrage : le formulaire COD est rendu) ; ON = parcours complet (2 produits semés → badge → panneau peint du serveur FR **et** AR — سلتك, RTL delta 0 px → « Commander » → 1ʳᵉ fiche + ancre → bandeau → commande réelle **relue en base** : UNE commande, lignes 500+1000, wilaya 16 → panier vidé). Fixtures `scripts/recette-seed-panier.php` : option capturée avec sentinelle (« absente » ≠ `'0'`) et restaurée en trap EXIT (leçon D26), produits marqués purgés. 🔎 **La porte a payé dès son premier run — trois défauts attrapés** : (1) son assertion « aucune erreur PHP » testait `! scanPhp(…)` — un TABLEAU, toujours truthy en JS : elle ne pouvait JAMAIS être verte (l'inverse du péché habituel, même leçon : un contrôle jamais vu tourner ne prouve rien) — alignée sur le précédent `commande-cod.js` (`.length === 0`) ; (2) la purge des commandes témoins filtrait `billing_last_name` alors que `handle_submit()` range TOUT le nom dans `first_name` — elle n'aurait jamais rien purgé ; réécrite sur le parcours nom-complet de la porte `cod` ; (3) 🔴 **une vraie course en production** : le bouton « Commander » du panneau naissait SANS `disabled` — un clic entre l'ouverture du panneau et la réponse AJAX tombait dans le vide (`firstUrl` encore vide) ; vu quand le témoin a PERDU la course que son premier run avait gagnée ; la coque rend le bouton `disabled` de naissance, `paint()` l'arme. i18n : `--strict` des deux plugins vert (0 chaîne nouvelle — 35.1/35.3 avaient déjà tout traduit). Checklist TEMPLATE.md §10 rejouée (portes `public` et `cod` vertes) ; 935 tests ; clone pristine vérifié (0 commande témoin, 0 produit, option absente, sentinelle purgée). Règles d'or nuancées dans CLAUDE.md/TEMPLATE.md : le panier COD n'est pas le panier Woo — même traitement documentaire que la landing pour la règle des 3 pages. Story : `_bmad-output/implementation-artifacts/35-4-panier-cod-i18n-recette.md`. |
| 36.1 | Panier COD v2 — bouton d'ajout sur les cartes produit (grilles) | M | ✅ **Livrée le 2026-07-23** — PHP n'imprime qu'un **nœud de données** par carte (`loop_card_data()`, hook `woocommerce_after_shop_loop_item`, toggle ON seulement — le markup des cartes appartient au thème, et un `<button>` dans le `<a>` serait invalide) ; le JS panier enveloppe l'image (`.aod-cart__qwrap`) et pose la surimpression semi-transparente (`role="button"` + `preventDefault`, clavier Enter/Espace). Survol = variante desktop (`hover: hover`) ; au tactile le bouton est **permanent** (`hover: none`, prouvé en émulation mobile : opacité 1, un tap ajoute). Produit à variantes → le bouton MÈNE à la fiche + ancre (jamais d'ajout aux options devinées). Retour visuel « Ajouté ✓ » 1,6 s. 🔁 **Libellé unifié le 2026-08-02** (arbitrage owner) : il disait « Choisir les options » dès qu'un produit portait des options — soit, sur un catalogue réel, **toutes** les cartes, si bien que la grille n'annonçait plus qu'il existe un panier. Il dit « Ajouter au panier » partout ; **le clic, lui, n'a pas bougé**. La porte `panier` sème un 3ᵉ témoin **à variantes** (elle n'avait que des produits simples, donc la moitié du catalogue) et garde les deux moitiés de la décision — même libellé ET aucun ajout au clic ; sans la seconde, on « corrigerait » le libellé en devinant la variante. 45 → 48 assertions, éprouvées au rouge sur les deux. Détail en §B/36.1. Un seul hook couvre Boutique, Accueil (`[products]`) et produits liés. |
| 36.2 | Panier COD v2 — le panier géré sur la page du formulaire | M | ✅ **Livrée le 2026-07-23** — le bandeau « votre panier (N) » est **remplacé** par un bloc de gestion complet au-dessus du formulaire (`.aod-cart__box`, lignes du panneau : quantités +/−, retrait, totaux serveur) — **peinture partagée** avec le drawer (`fetchEstimate`/`paintInto`, une seule implémentation). L'endpoint renvoie désormais aussi le total **brut** ; le récapitulatif Sous-total/Livraison/Total du formulaire **suit le panier** quand il est non vide (seuil de gratuité compris), et retrouve le mode mono-produit quand on le vide sur place — sans rechargement. L'ancien bandeau (JS + CSS + 2 clés i18n `cart_note`/`cart_view`) est **retiré** : il était devenu inatteignable (précédent 18.2, clés mortes). Boutons du bloc durcis contre les styles `button` du thème (padding/min-* remis à zéro — vus gonflés en pastilles chez Astra). |
| 36.3 | Panier COD v2 — suggestions « D'autres produits » | S/M | ✅ **Livrée le 2026-07-23** — section « Complétez votre commande » sous le formulaire de la fiche **classique** (hook `woocommerce_after_single_product` ; une **landing** est une composition du gérant, on ne lui injecte rien). Rendu serveur au chargement, aucune surface AJAX nouvelle : 4 produits publiés achetables, produit courant exclu, titres dans la langue courante (filtres 7.2), prix Woo. Chaque carte porte le même nœud de données → même surimpression d'ajout que 36.1. La 135ᵉ annotation `EscapeOutput` (le `get_image()` du cœur Woo) est justifiée au census du cliquet. |
| 36.4 | Panier COD v2 — i18n + extension de la porte `panier` | S | ✅ **Livrée le 2026-07-23** — **clôture de la Phase 36.** 3 chaînes nouvelles traduites AR (`--strict` ×1 vert, 2 clés mortes purgées). La porte `bash tools/recette/run.sh panier` passe de 21 à **32 assertions** : OFF = pas un octet Y COMPRIS grilles et suggestions (ancrage : la grille Boutique est rendue) ; ON = ajout depuis la Boutique **sans navigation**, bloc de gestion (badge 2→3→2, récapitulatif suivi : 2 500 lu), suggestions portant le nœud d'ajout, puis le parcours 35 inchangé (drawer FR/AR RTL, « Commander », commande **relue en base** 500+1000, panier vidé). ⚠️ Depuis la 36.2, lignes et total existent en DOUBLE sur la page (bloc + drawer) : les assertions du panneau se scopent à `#aod-cart-drawer` — la porte l'a appris au strict mode. Portes `cod` et `public` vertes (checklist §10) ; 935 tests. Version `aod-cod-form` 1.45.0. |
| 37.1 | UI/UX du panier COD — lui donner sa propre peau | M | ✅ **Livrée le 2026-07-23** — refonte visuelle et structurelle des 4 surfaces du panier, épines UX en `_bmad-output/planning-artifacts/ux-designs/ux-panier-cod-2026-07-23/`. 🔴 **Défaut fondateur mesuré : 1,05:1** sur « Ajouter au panier » (vert `#008060` sur le bleu de marque du thème) — le point d'entrée du panier sur la fiche produit était **invisible**, et il l'était sous une porte `panier` VERTE : elle vérifiait la présence et le comportement du bouton, jamais sa lisibilité, pendant que la porte `contraste` n'audite que `/gestion`. Cause : notre propre markup portait `class="button"`, donc le CSS de boutons du thème (imprimé en `<style>` inline, APRÈS nos feuilles) écrasait notre `background: transparent` ; seul notre `color`, porteur d'un `!important`, survivait — d'où le vert sur bleu. 🚨 **Trois pièges mesurés, aucun visible à la relecture** : (1) `!important` ne suffisait pas à corriger le fond, parce que `getComputedStyle` lu juste après une mutation rend la valeur de DÉPART d'une `transition` — mes trois premières stratégies ont été jugées fausses sur une **mesure invalide** ; (2) le réarmement défensif écrit `.aod-cart__drawer button` (0,1,1) battait le thème mais AUSSI nos propres règles de composant (0,1,0) — « Commander » est sorti sans fond, vu à l'écran ; (3) le thème gagne les ÉTATS avec `button:focus` (0,1,1), donc un composant qui ne déclare que `:hover` retombe au thème dès qu'on lui donne le focus — c'est exactement ce qui arrivait au bouton de fermeture, que le panneau focalise à l'ouverture. Correctifs : aucune classe de thème dans notre markup, fonds **explicites** (jamais `transparent` : le contraste dépendrait du thème du clone), `:focus` déclaré à côté de chaque `:hover`, tokens `--ac-*` alignés sur les `--sh-*` du formulaire, ligne de panier ramenée de 5 colonnes à **2** (noms sur 2 lignes — l'ellipse rongeait le DÉBUT d'un nom latin en arabe), bloc aligné sur la largeur du formulaire (552 px mesurés des deux côtés). **Zéro nouvelle chaîne i18n, zéro changement serveur, format `localStorage` et surface AJAX inchangés** — le contrat « toggle OFF = pas un octet » tient. Cliquet `tests/unit/test-panier-peau.php` (R1 aucune classe de thème · R2 les tokens ne dérivent pas · R3 aucun fond actionnable transparent · R4 tout `:hover` déclare `:focus` · R5 témoins), **éprouvé au rouge 2 fois** (R1 a rougi sur le commentaire qui explique le piège ; R2+R5 sur une dérive d'un chiffre du vert). Porte `panier` étendue de 32 à **44 assertions** : 10 contrastes mesurés sur le rendu réel dont un **au focus**, noms non tronqués, et un **TÉMOIN** qui rejoue le couple exact du défaut (1,05:1) et exige qu'il soit refusé — sans lui, tout le bloc serait vert sur une page vide. Portes `mouvement` et `public` vertes ; 973 tests. |
| 37.2 | Le panier prend les icônes vectorielles de `/gestion` | S | ✅ **Livrée le 2026-07-23** — le bouton flottant portait un chemin SVG **recopié à la main** dans le PHP (hors de tout catalogue, donc invérifiable et libre de dériver), et les boutons fermeture/retrait un glyphe texte `&times;`. Désormais les MÊMES icônes **Phosphor** que la sidebar de `/gestion` (`shopping-cart-simple` en graisse `fill` pour le bouton flottant — la graisse que `/gestion` réserve à l'élément de nav ACTIF, qui tient mieux en petit sur un fond plein —, `x` pour fermer et retirer), servies par un catalogue FERMÉ `AOD_COD_Icon` : attributs reconstruits au rendu (jamais lus du fichier, donc une mise à jour du jeu ne change pas la présentation sans qu'on l'ait décidé), `currentColor` (le même fichier sert sur fond vert et sur fond blanc), `aria-hidden` (les boutons portent déjà leur `aria-label` — une icône annoncée en plus est du bruit au lecteur d'écran). Le JS reçoit ses icônes **pré-rendues** via `AOD_CART.icons` (`js_map()`, liste minimale) : il n'en fabrique jamais. 🚨 **Pourquoi un JUMEAU de `AOD_CD_Icon` et pas un appel** : la dépendance entre les deux plugins ne va que dans UN sens — `aod-client-dashboard` a besoin de `aod-cod-form`, jamais l'inverse. Appeler la classe du dashboard depuis le public ferait tomber la boutique en **erreur fatale** dès qu'un gérant désactive `/gestion` : c'est le mode dégradé fermé par la Phase 32.1, repris à l'envers. Contrepartie assumée : 3 fichiers SVG dupliqués, **gardés octet à octet** (R6 de `test-panier-peau.php`, éprouvé au rouge en changeant un `viewBox`) — même parti pris que les tokens `--ac-*`/`--sh-*` : on ne peut pas toujours éviter de recopier, on peut toujours refuser que la copie dérive en silence. 🔎 Trouvé au premier run : `GLOB_BRACE` n'existe pas dans l'image WP-CLI (musl) et faisait tomber TOUT le fichier de test en fatale — remplacé par deux `glob()`. Census `phpcs` mis à jour (+2 `EscapeOutput`, +1 `AlternativeFunctions`, chacune déclarée avec sa raison — le cliquet a refusé de passer tant qu'elles ne l'étaient pas). 981 tests ; portes `panier` et `public` vertes. |
| 37.3 | Le formulaire COD reprend le vert du plugin (arbitrage Q2 tranché) | S | ✅ **Livrée le 2026-07-23** — « Confirmer la commande », sur le formulaire de commande ET le formulaire de contact, ne porte plus `class="button"` et prend le vert `--sh-accent` que son CSS **déclarait déjà** (le rendu contredisait l'intention écrite : bleu du thème mesuré, vert demandé). **Ce qui a emporté la décision, c'est le contraste, pas l'esthétique** : la couleur primaire d'un thème est une valeur ARBITRAIRE qu'on ne contrôle pas — Astra donne 5,20:1 avec du blanc, un clone à primaire clair rendrait du blanc sur clair, illisible, sur le bouton le plus important du tunnel d'achat. Toute la Phase 37 a établi qu'aucun fond actionnable ne dépend du thème ; garder l'exception aurait contredit le principe là où il compte le plus. Mesuré après : **4,93:1 au repos, 6,26:1 au focus**, sur les deux formulaires. 🚨 **L'ARBITRAGE A ÉTÉ REFORMULÉ EN COURS DE ROUTE, et c'est le vrai apport de l'étape.** La règle générale pressentie — « aucune classe de thème sur le markup public » — était **FAUSSE** : vérification faite, `.aod-home__btn` et les CTA de page de vente **délèguent** leur apparence au thème **à dessein**, avec la raison écrite dans `aod-cod-pages.css` (« On ne redéfinit PAS l'apparence du bouton : `.button` est la classe du thème, il l'habille lui-même »). La leur retirer les aurait laissés **NUS** — une règle générale appliquée sans vérifier aurait cassé 3 CTA pour en corriger 2. Règle retenue : **s'habiller soi-même OU déléguer, jamais les deux**, toute délégation déclarée avec sa raison. Gardée par **R7** de `test-panier-peau.php`, qui vérifie les DEUX sens (les autonomes n'ont pas la classe, les délégués l'ont toujours) — éprouvé au rouge en remettant `class="button"` sur le submit. ⚠️ **Piège évité de justesse** : la nouvelle base scopée `.aod-cod .aod-cod__submit` (0,3,0) **battait** la surcharge `prefers-reduced-motion` restée en (0,2,0) — une media query n'ajoute AUCUNE spécificité. Le creux d'appui aurait survécu au mouvement réduit, visible seulement de qui a activé le réglage, c'est-à-dire de personne pendant la revue. Surcharge réalignée dans le bloc de FIN de fichier (la convention y est écrite depuis la 19.1). 🔎 Vérifié et NON touché : le bleu résiduel de la carte d'offre est `--aod-c`, la **couleur de variante du produit** (fonctionnalité, pas fuite du thème). Le `!important` qui portait la couleur du texte a pu être RETIRÉ, le scope `.aod-cod` suffisant. 993 tests ; portes `mouvement`, `public` et `panier` vertes. |
| 38.1 | Désempiler le formulaire : le SOLO ne s'encadre plus | M | ✅ **Livrée le 2026-07-23** — sur un produit sans offre configurée (le défaut d'un clone neuf), les pastilles de variantes vivaient au fond de **4 cadres emboîtés** (carte d'offre → panneau → bloc « Article » → section) alors qu'il n'y a **aucun choix d'offre à présenter**. Mesuré sur 390 px : 67 px de décor de CHAQUE côté (34 % de l'écran) pour encadrer une décision inexistante ; pastilles à 258 px. Après : **316 px** (+22 %). 🚨 On retire la PEAU, jamais les NŒUDS — `aod-cod-form.js` lit `data-qty`/`data-price` sur la carte et **itère `.aod-cod__unit`** pour composer la sélection par article ; les nœuds restent, marqués `--solo`, ne perdant que bordure/fond/coche. Contrat DOM inchangé : multi-offres (2-3 produits) rend comme avant, vérifié au navigateur. La ligne de prix « 1 produit » survit dépouillée — une **page de vente n'affiche aucun prix hors du formulaire** (`class-aod-cod-landing.php` n'appelle jamais `wc_price()`). ⚠️ Piège de cascade INTERNE : `.aod-cod__unit--solo` et `.aod-cod__unit` sont à égalité (0,1,0), l'ORDRE du fichier tranche — placée à sa place logique elle perdait (mesuré, pas relu), déplacée après. Portes `panier`, `public` vertes. |
| 38.2 | Variantes en grille, cibles tactiles, écho de sélection | M | ✅ **Livrée le 2026-07-23** — pastilles passées de `flex-wrap` (six pointures tassées à gauche, la 7ᵉ seule à la ligne) à une **grille à colonnes égales** (`auto-fill`+`minmax`, 3 mailles selon contenu). Cible tactile 32 → **44 px** (mobile-first). État choisi : anneau **intérieur** (un `box-shadow` extérieur débordait en grille serrée) + fond `--sh-accent-wash`. **Écho de la valeur choisie** au libellé (« Pointure · 41 »). 🚨 Il lit le texte AFFICHÉ de la pastille, jamais `data-name` (nom canonique FR, clé de `match_option()`) : l'afficher rendrait « Noir » à qui a cliqué « أسود ». **Zéro chaîne traduisible ajoutée** — juste dans les deux langues sans une ligne de `.po`. ⚠️ 2ᵉ piège de cascade : `.aod-cod__field:not(.aod-cod__float) > label` pèse (0,2,1) (`:not()` compte son contenu), un `.aod-cod__optlabel` (0,1,0) perdait et l'écho se collait à l'astérisque — égalisé à `label.aod-cod__optlabel`. La leçon de la Phase 37 vaut aussi **contre ses propres règles**. Token appairé `--sh-accent-wash` ⇄ `--ac-accent-wash` (R2). |
| 38.3 | Trois emojis de chrome chassés du côté acheteur | S | ✅ **Livrée le 2026-07-23** — le formulaire portait 🏠 / 🏢 (mode de livraison) et 🔒 (réassurance) : un emoji est **dessiné par l'OS**, trois acheteurs sur trois systèmes ne voyaient pas le même écran. Remplacés par `AOD_COD_Icon` (icônes `house`/`storefront`/`lock` copiées **verbatim** de `/gestion`, gardées octet à octet par R6) ; l'état choisi se dit par la COULEUR (rendu `regular`, pas de `fill` mort au catalogue). 🚨 **Le cliquet anti-emoji regardait moins large que son titre** : `test-icones.php` s'annonce « plus aucun emoji de chrome » et ne balayait QUE `/gestion` — le plugin public en portait. Balayage étendu au fichier nettoyé, avec **témoin** que le chemin public a résolu (glob vide = vert sans rien regarder, défaut de la Phase 27). ⚠️ Périmètre assumé : l'Accueil (💵 🚚 📞) et le Contact (📍 ✉️) en portent encore — chantier à part (nouvelles icônes + 2 pages), consigné en mémoire. Hors plugin sur CE clone : largeur Astra 1200 → 1600 px (relevée à 1600 le 2026-07-24, cf. la note « Hors plugin » ci-dessus ; option `astra-settings`, PAS un theme_mod — le premier essai `set_theme_mod` s'est écrit dans le vide ; par clone, noté C9 de `CHECKLIST-THEME-CHILD.md`). Census phpcs +3 (138-140 déclarées). Portes `panier`/`public`/`rtl`/`mouvement` vertes ; **998 tests**. |
| 39.1 | Bouton « Commander » + création du squelette | M | ✅ **Livrée le 2026-07-24** (`aod-client-dashboard` v0.51.0 · `aod-cod-form` **intouché**) — la section *Commandes abandonnées* gagne le maillon qui lui manquait : un bouton **« Commander »** (icône `cart`, dans le rang `.aod-cd-call-actions` à côté d'Appeler/WhatsApp) qui crée la vraie commande WooCommerce à partir de ce que l'acheteur avait déjà saisi. **Fil rouge tenu : pas de 3ᵉ constructeur de commande** — l'AJAX `aod_cd_convert_lead` pose un **squelette minimal** (produit racine + coordonnées du prospect, `_aod_source='aod-abandoned'`, `set_payment_method('cod')`), statut **`pending`** (JAMAIS `aod-confirmed` : aucun envoi transporteur, aucune bascule Suivi ici), puis `mark_converted()` → le lead quitte la liste. La complétion (adresse, variante, total, statut → envoi) est l'étape 39.2, via le chemin d'édition existant. **Garde** : nonce + `aod_cap_confirm_orders` (EN PLUS de `aod_cap_abandoned` — divergence VOULUE, déclarée `action-forte` avec sa raison au cliquet `test-permissions-sections.php`) + périmètre produits **côté SQL** ; bouton **masqué** (pas désactivé) sans la cap. **Anti-doublon (leçon 31.4)** : verrou `add_option` **atomique** (jamais un transient) + **relecture sur instance fraîche** ; libéré avant chaque `wp_send_json` (wp_die → exit ne déclenche pas `finally`). Premier binder JS de la section (`section-abandoned.js` / `bindAbandonedConvert`, dans `ORDER` + `dashboard_scripts()`), instanciée au boot pour son hook. 🚨 **Plafond R1 relevé 933 → 935, assumé et écrit dans `test-decomposition.php`** : +1 module au manifeste + sa chaîne `i18nConvertConfirm` — du niveau COQUE, pas de logique de section réabsorbée. **Vérifié** : 13 assertions serveur (squelette relu — statut, source, produit/qté, wilaya, aucun tracking —, lead converti, verrou atomique, purge), rendu prouvé des deux côtés (bouton présent en gérant, ABSENT pour un confirmateur sans la cap), témoin navigateur 390 px FR **et** AR (RTL, label « إنشاء الطلب », 0 débordement), porte `run.sh rtl` verte (12 sections, console vide). i18n `--strict` vert (10 chaînes AR). Census phpcs `WordPress.DB` 15 → 17 (2 lectures préparées de la table leads, déclarées). **999 tests**. |
| 39.2 | Compléter dans la modale d'édition + sélecteur de statut | M | ✅ **Livrée le 2026-07-24** (`aod-client-dashboard` v0.52.0 · `aod-cod-form` **intouché**) — après conversion (39.1), la **modale d'édition existante** s'ouvre sur la commande créée, et le **statut y devient choisissable**. **Ni 2ᵉ constructeur, ni 2ᵉ chemin de statut, aucun handler AJAX neuf** (toujours 29) : pur câblage + refactor à **comportement constant** de `section-orders.js`. **`openOrderModal( orderId )`** extrait de `bindOrderDetail` et exposé sur le bus `window.aodCdCore` (le shell `#aod-cd-modal` est du chrome global, présent sur `/abandoned`) ; `section-abandoned.js` l'appelle après la conversion (lu au call-time, gardé). **`wireStatusWidget( widget )`** extrait de `bindStatus` : `bindStatus` = `forEach( wireStatusWidget )` (liste inchangée), et `initOrderEdit` le rappelle sur le `.aod-cd-statussel` **injecté** dans la modale (que `bindStatus`, lié au DOMContentLoaded, ne voyait pas). Côté PHP, `render_order_detail()` rend le **même** `status_select_html()` que la liste à la place du texte figé `.aod-cd-od-status`. « Confirmée » réutilise `aod_cd_order_status` → `auto_send()` → la **bascule Suivi est automatique** (partition `_aod_ship_tracking` de la Phase 12, aucun code neuf) : colis créé ⇒ sort de Commandes vers Suivi ; échec (`_aod_ship_error` sans tracking) ⇒ **reste dans Commandes** ; le retour relaie `sent`/`failed`/`disabled` (via `applyStatus`, dont le no-op « pas de `<tr>` » en modale est voulu). **Vérifié** : 8 assertions serveur (sélecteur rendu + lié à la commande, texte figé disparu, et la **partition** prouvée dans les deux sens selon `_aod_ship_tracking`), témoin navigateur (conversion → modale ouverte sur le bon n°, menu de statut avec « Confirmée », « Modifier » ouvre le formulaire ; 390 px FR **et** AR/RTL, 0 débordement), porte `run.sh rtl` verte (12 sections, console vide — refactor JS non cassé). 🚨 **Piège de recette, pas de prod** : le bouton « Commander » demande `window.confirm()` — Playwright headless l'auto-rejette, il faut `page.on('dialog', d => d.accept())` (sinon la conversion est annulée en silence). Zéro chaîne i18n neuve (le widget réutilise les libellés). Census phpcs `EscapeOutput` 140 → 141 (le `status_select_html` rendu dans la modale, déclaré). Plafond R1 **inchangé** (ni coque, ni binder, ni fichier JS neuf). **999 tests**. |
| 39.3 | i18n + recette + cliquet de la phase | S | ✅ **Livrée le 2026-07-24** (`aod-client-dashboard` v0.53.0 · `aod-cod-form` **intouché**) — **clôture de la Phase 39** : la conversion (39.1/39.2) devient rejouable-par-clone et inrégressable. **Aucune ligne de production** (la feature était livrée) — le livrable est la PREUVE. **i18n** : `make-translations.sh --strict` vert (chaînes du parcours déjà en AR — « إنشاء الطلب », « الدفع عند الاستلام », messages d'erreur ; 3 non traduites = **marque**, volontaire) ; seul le `.pot` bouge (refresh des références de lignes). **Porte `abandonnees` étendue** (26 assertions) : sur `AbandonRecette`, clic « Commander » → **ligne disparue + modale ouverte** sur le n° de commande, **sélecteur de statut présent DANS la modale** (AC3), commande **relue en base** via le lead témoin (`_aod_source='aod-abandoned'`, statut `pending`, bon produit, **aucun tracking**, lead `converted`), **anti-doublon** prouvé (2ᵉ appel forgé → **409**, pas de 2ᵉ commande) ; la vraie commande créée est **purgée** (extension du `cleanup` de `recette-seed-phase11.php`, liée au token témoin — jamais une commande réelle) via le vérificateur `scripts/recette-verify-phase39.php`. 🚨 **`window.confirm()` auto-rejeté en headless** → `page.on('dialog', d => d.accept())` (défaut HARNESS, pas produit). **Choix arbitré** : la bascule Confirmée→Suivi reste gardée par la porte `suivi` (`commandes-suivi.js`, Phase 12) + le témoin serveur de 39.2 — **pas de re-stub transporteur** dans une porte permanente (ne pas réécrire un contrat déjà vert). **Cliquet `tests/unit/test-conversion-abandon.php`** (+18, éprouvé au rouge) : R1 **symétrie de permission** (le BOUTON — que `test-permissions-sections` ne voit pas — ET l'AJAX exigent `aod_cap_confirm_orders`), R2 anti-doublon **structurel** (`add_option` atomique, jamais `set_transient` ; relecture fraîche entre verrou et création ; libération avant chaque sortie), R3 **squelette mesuré** (`build_skeleton_order` → `pending`, `_aod_source`, zéro tracking, bon produit/qté ; `mark_converted` sort le lead de `pending`), R4 **une seule surface AJAX** dans la section. 🚨 **Le cliquet est né avec le défaut qu'il traque** : R2 « jamais set_transient » rougissait sur le **docblock** qui écrit « …là où `set_transient()` écraserait… » — corrigé par un strip de commentaires (`token_get_all`), on ne garde QUE le code exécuté. R4 rejoué au rouge (2ᵉ `wp_ajax` injecté → « attendu 1, obtenu 2 »). MAJ `docs/ARCHITECTURE.md`. **1017 tests** (+18). |
| 39.4 | Le téléphone seul suffit à convertir (produit optionnel) | S | ✅ **Livrée le 2026-07-24** (`aod-client-dashboard` v0.54.0 · `aod-cod-form` **intouché**) — **Phase 39 rouverte (3/3 → 4/4)** : 39.1 posait un prérequis trop strict, remonté à l'usage — le bouton « Commander » n'apparaissait qu'avec `product_id > 0`, et `ajax_convert_lead()` refusait (« Le produit de ce prospect n'existe plus. », 404) si le produit avait été **supprimé**. Or le confirmateur **appelle** le client, qui **fournit tout** : le seul prérequis utile est **un numéro de téléphone**. **Règle cible** : *avec un téléphone, on convertit* — le produit est facultatif. **Correctif** (MÊME méthode, ni 2ᵉ constructeur ni 2ᵉ chemin) : (1) le bouton `render_row()` n'exige plus que la cap (toujours dans le bloc `if ('' !== $phone)`) ; (2) `ajax_convert_lead()` ajoute une garde **téléphone** (422 si absent — couvre l'appel forgé) et **retire** le refus « produit n'existe plus » (`$product` chargé en nullable) ; (3) `build_skeleton_order()` accepte `$product` **nullable** — `add_product` seulement `if ( $product instanceof WC_Product )`, sinon squelette **sans ligne d'article** (le confirmateur compose dans la modale via `aod_cd_order_save_info`), note de commande conditionnelle. **Périmètre inchangé** : nonce + `aod_cap_confirm_orders` + `assigned_products()` (sur le `product_id` du lead — gérant convertit tout prospect à téléphone ; confirmateur restreint reste borné à son périmètre, `product_id` 0 compris — **décision assumée**), anti-doublon (verrou + relecture fraîche), statut `pending`, `_aod_source`. **Vérifié** : cliquet `test-conversion-abandon.php` étendu (R1bis : la garde du bouton est la cap SEULE, plus de `product_id` ; l'AJAX ne refuse plus « existe plus » ; garde 422 ; **R3** : `build_skeleton_order( $lead, null )` → commande `pending`, `_aod_source`, **0 ligne d'article**, aucun tracking ; le cas « avec produit » reste couvert) — **éprouvé au rouge** (product_id re-injecté dans la garde). Porte `run.sh abandonnees` : un prospect **`ProduitSupprimeRecette`** (product_id → post inexistant) se convertit — bouton présent, modale ouverte, commande relue en base avec **ITEMS=0**, purgée. 🚨 **Course pré-existante durcie** au passage : le titre/le widget de statut de la modale sont injectés **après** le fetch AJAX — la porte attend désormais `.aod-cd-statussel` avant de les lire (ça passait par chance). **+2 chaînes i18n AR** (garde téléphone + note « sans produit ») ; l'ancien message « produit n'existe plus » devient obsolète (plus émis). **1025 tests**, porte `abandonnees` 33/0. |
| 39.5 | La modale d'une commande vide doit pouvoir la remplir (panier multi-produits) | S | ✅ **Livrée le 2026-07-24** (`aod-client-dashboard` v0.55.0 · `aod-cod-form` **intouché**) — **Phase 39 rouverte (4/4 → 5/5)** : la 39.4 a créé un trou en aval, remonté à l'usage. Une commande abandonnée convertie **sans produit** (39.4) ouvrait la modale d'édition… mais `render_order_edit_form()` enfermait **tout** le bloc Articles — lignes existantes (variantes + qté + « Retirer »), sélecteur **« Ajouter un article »** ET champ **Total** — dans un `if ( $items )`. À **zéro article**, le confirmateur ne voyait que Nom/Téléphone/Livraison/Adresse/Wilaya/Commune : il pouvait créer la commande mais **pas la remplir**. **Correctif PHP-only** : le conteneur `.aod-cd-od-items` est rendu **même vide** (le JS y empile les lignes), l'ajout est conditionné au **catalogue** (`orderable_catalog()`, indépendant des items), le champ **Total** est **toujours** rendu ; une note d'aide s'affiche quand la commande est vide. **Le panier multi-produits existait déjà** : chaque « Ajouter » empile une ligne (`buildNewItemRow` avec ses `select` de variantes), le submit collecte **toutes** les lignes (`new_product` multiples) — l'UI ne faisait que les cacher. **Aucun changement serveur** (`ajax_order_save_info` gère déjà `new_product` + `total_override`), **aucun changement JS** (ses gardes `if ( addBtn && addPick && itemsBox )` deviennent simplement actives), **aucun handler AJAX neuf**. Modale **partagée** avec la section Commandes : le critère est « commande vide », pas « abandon » → une commande normale à ≥ 1 article rend **exactement** comme avant (zéro régression, prouvée porte `rtl`). **Vérifié** : porte `run.sh abandonnees` (39/0) — après conversion du prospect **sans produit** (fixture `ProduitSupprimeRecette`, 39.4), « Modifier » montre `.aod-cd-od-addpick` / `.aod-cd-od-items` / `.aod-cd-od-total`, **2 produits** ajoutés via l'UI → enregistrés → commande relue **ITEMS=2**. +1 chaîne AR (note « aucun article »). **1025 tests**, porte `rtl` verte. |
| 39.6 | Sélection groupée + suppression dans « Commandes abandonnées » | S | ✅ **Livrée le 2026-07-24** (`aod-client-dashboard` v0.56.0 · `aod-cod-form` **intouché**) — **Phase 39 rouverte (5/5 → 6/6)**. Demande owner : cocher un prospect (ou tous) et les supprimer, **comme la page Commandes**. Patron **réutilisé** de `AOD_CD_Section_Orders` (barre `.aod-cd-bulkbar`, case « tout sélectionner », compteur + `indeterminate`, POST d'IDs, retrait des lignes) — pas réinventé. **Capability GÉRANT uniquement** (`manage_woocommerce`, `action-forte`) : supprimer est destructif et borné, exactement comme `delete_orders` (leçon 31.1) — un confirmateur `aod_cap_abandoned` consulte/rappelle/convertit mais **ne purge pas** (cases, barre et handler **absents** pour lui, masqués ET 403). Nouvel AJAX `aod_cd_delete_leads` : nonce + `manage_woocommerce`, `DELETE … WHERE id IN (%d,…)` **préparé** sur la table maison `aod_cod_leads` ; **ne touche pas aux commandes** (un lead converti n'est plus listé — `status pending`). Classes CSS **comportementales distinctes** (`.aod-cd-leadcheck*` / `#aod-cd-lead-bulk`) pour ne pas entrer en conflit avec le binder Commandes qui tourne aussi sur la page (no-op faute de ses cases). Binder JS `bindAbandonedBulkDelete` (`section-abandoned.js`, `register()` + `ORDER`). 🚨 **Deux plafonds relevés** : R1 JS `test-decomposition-js.php` **635 → 636** (nom de binder dans `ORDER`) et R1 PHP `test-decomposition.php` **935 → 936** (chaîne `i18nLeadBulkDelConfirm` dans `window.AOD_CD`) — croissance COQUE légitime, écrite (même geste que 39.1). **Vérifié** : `test-permissions-sections.php` gagne l'entrée `delete_leads` (30 handlers désormais, `manage_woocommerce`, `action-forte`, avec sa raison) ; `test-conversion-abandon.php` R4 passe à **2 surfaces AJAX** (convert + delete, aucune écriture de champs/statut) ; census phpcs `WordPress.DB` 17 → 18 (le DELETE préparé, déclaré). Porte `run.sh abandonnees` (45/0) : « tout sélectionner » → barre + compteur → Supprimer → ligne disparue + prospect **absent en base**. Porte `rtl` verte (console vide — binder sain). +6 chaînes AR. **1027 tests**. |
| 40.1 | Modèle `stock` dans `_aod_options` + règle mono-axe | M | ✅ **Livrée le 2026-07-25** (`aod-client-dashboard` + `aod-cod-form`) — chaque **valeur** d'option gagne un champ `stock` : **`''` non géré (illimité, défaut) ≠ `0` rupture** (négatif → `0`, non-numérique → `''`). Parse/normalisation dans `AOD_CD_Section_Products` (`collect_options`/`normalize_options`), exposé par les **deux** `get_product_options()` (public `AOD_COD_Form` + éditeur). **Règle mono-axe (D1bis) côté serveur** : le premier axe portant un stock est l'axe à stock, les autres forcés à `''`. Migration **nulle** (clé absente = `''`). Cliquet `test-variant-stock.php` (PARSE, NORMALISE, MONO-AXE éprouvée **au rouge**, LECTURE + repli produit d'avant 40.1). |
| 40.2 | Saisie du stock par variante (Produits) | S | ✅ **Livrée le 2026-07-25** (`aod-client-dashboard`) — champ `opt_value_stock[si][vi]` dans `render_option_value()` (jamais `value="0"` pour un non géré) + garde-fou UI mono-axe `refreshStockGuard()` (`section-products.js`). Au passage, correctif d'un bug de grille **préexistant depuis 7.4** (`name_ar` écrasait le champ prix : grille 5 → 6/7 colonnes). Cliquet étendu (SAISIE), red-proofé. |
| 40.3 | Vue Stock : somme + détail par variante | S | ✅ **Livrée le 2026-07-25** (`aod-client-dashboard`) — `variant_stock_summary()` : **somme** des valeurs gérées + détail. La page Stock affiche la somme, la sous-ligne « Variantes : Rouge → 12 · Bleu → 0 », et passe le produit en **lecture seule** (« géré par variante ») ; `stock_pill` cohérent (jamais « — » là où Stock dit « rupture » — pas de divergence). `ajax_save_stock()` refuse (400) un produit géré à la variante. Cliquet étendu (STOCK), red-proofé sur la divergence pastille. |
| 40.4 | Rupture publique (grisée) + garde serveur | M | ✅ **Livrée le 2026-07-25** (`aod-cod-form` v1.48.0) — côté acheteur (`option_sections_html`, pastilles ET liste déroulante) : valeur gérée ≤ 0 → grisée + `disabled` + « Rupture de stock » (i18n FR/AR). 🚨 **Garde SERVEUR** dans le funnel **unique** `build_order_lines()` : une sélection forgée d'une variante en rupture ne crée **aucune** ligne — éprouvée **au rouge** (sans la garde, la ligne passe). Nom canonique FR intact (clé de matching). Cliquet (RUPTURE), vérifié au navigateur en visiteur (LTR + AR/RTL). **1078 tests**. |
| 40.5 | Décrément à la confirmation + ré-incrément à l'annulation | M | ✅ **Livrée le 2026-07-25** (`aod-client-dashboard` v0.60.0) — **mirroir du pack** `reduce_pack_components_stock()` : `reduce_variant_stock()` sur `woocommerce_order_status_aod-confirmed` (+ `completed` défensif), `restore_variant_stock()` sur `cancelled`/`refunded`, helper unique `apply_variant_stock_delta($order,$sign)` (décrément **borné à 0**, ré-incrément sans borne). Idempotence **bidirectionnelle** par le drapeau `_aod_variant_stock_reduced` **sur la commande** (jumeau de `_aod_pack_stock_done`). Décrément **à la confirmation** (D3), jamais à la création. **2 red-proofs** (double décrément ; variante non gérée mutée). Vérifié via le **vrai hook de statut** (`wp eval`). Aucune surface neuve (AJAX/cap/i18n/JS). **1090 tests**. |
| 40.6 | Clôture : porte de recette, cliquet, inscription doc | S | ✅ **Livrée le 2026-07-25** — porte **`run.sh variante`** (seed `recette-seed-variante.php`) : rupture grisée (public), somme + lecture seule (Stock), décrément/ré-incrément **reflétés dans la somme** (confirmation → 7, annulation → 10), repli. Éprouvée **au rouge** (badge rupture neutralisé). Cliquet `test-variant-stock.php` (groupe DÉCRÉMENT, vraies `WC_Order` + 2 red-proofs). i18n FR/AR `--strict`. Inscription ROADMAP (A.17 + Phase 40 + cette table) / TEMPLATE / ARCHITECTURE, **`test-doc-derive` vert** (R2/R5/R6). |
| 41.1 | Couleurs de marque sur les cartes de régie | S | ✅ **Livrée le 2026-07-25** (`aod-client-dashboard` v0.61.0) — catalogue fermé {Meta `#1877f2`, TikTok `#000`, Snapchat `#fffc00`, Google `#ea4335`} porté par une classe modificatrice par carte (`aod-cd-pxcard--<brand>`) dans `AOD_CD_Section_Marketing` + CSS (`dashboard.css`) : icône **pleine** (encre `best_ink()` — noir sur Meta/Snapchat/Google, blanc sur TikTok). 🚨 **Le réflexe « marque = blanc » échoue AA** : blanc sur le rouge Google = 3,92, sur le bleu Meta = 4,23 (< 4,5) — seul le noir passe. **Liseré** de marque en **pseudo-élément** `inset-inline-start` (RTL-safe, survit au `border-color` vert de `is-on`), en-tête légèrement teinté, corps sur fond blanc **inchangé**. Les 3 cartes CAPI héritent de leur plateforme ; « Devise de reporting » reste neutre ; le vert « Actif » reste **sémantique**. Aucune surface serveur, aucune chaîne i18n. Vérifiée au navigateur (gérant, FR **et** AR/RTL) : styles calculés exacts, liseré à droite en AR, **0 erreur console**. |
| 41.2 | Clôture : contraste AA, doc | S | ✅ **Livrée le 2026-07-25** — extension `test-contraste.php` : elle lit le **vrai** `dashboard.css` et prouve, par marque, que l'encre déclarée **=** `best_ink()` (1) ET que le couple marque/encre franchit AA (2). **Red-proof** : encre Meta forcée à blanc → les deux assertions rougissent (4,23 < 4,5). Les 6 couleurs de marque déclarées **avec leur raison** dans `test-charte-surfaces.php` (marques universelles ≠ accent client — elles ne rétrécissent pas, contrairement à la dette sémantique). Portes `contraste` et `rtl` vertes. **1100 tests** (+10). Inscription ROADMAP (A.18 + Phase 41 + cette table) / TEMPLATE / ARCHITECTURE, **`test-doc-derive` vert** (R2/R5/R6). |
| 42.1 | Modèle `_aod_assigned_confirmer` + tourniquet par charge (formulaire public) | M | ✅ **Livrée le 2026-07-26** (`aod-client-dashboard` v0.62.0, commit `1b1f024`) — nouvelle méta de commande `_aod_assigned_confirmer` (ID utilisateur, absente/`0` = non assignée), écrite HPOS-safe (`update_meta_data()` + `save()`). `AOD_CD_Roles::eligible_confirmers( $product_ids, $ignore_scope = false )` : vivier = confirmateurs **actifs** (`has_cap(AOD_CD_CAP)`), porteurs de `aod_cap_confirm_orders`, dont le périmètre produit intersecte `$product_ids` (vide = pas de restriction), triés par ID (déterminisme). `AOD_CD_Section_Orders::assign_least_loaded()` choisit, parmi ce vivier, celui qui porte le **moins** de commandes actives assignées (statuts hors `completed`/`cancelled`/`refunded`) ; vivier vide ⇒ **aucune** méta posée (commande non assignée, visible du seul gérant — comportement identique à avant la Phase 42, conforme à D4). Branché sur `aod_cod_order_created` — `aod-cod-form` **non touché**, seul le hook est consommé. Cliquet `test-assignation-commandes.php` (groupe 42.1) : charge égale → premier par ID, charge inégale → le moins chargé, confirmateur inactif/sans cap/hors périmètre jamais choisi (red-proofé), vivier vide → aucune méta. |
| 42.2 | Assignation directe à la conversion d'un abandon (Phase 39) | S | ✅ **Livrée le 2026-07-26** (`aod-client-dashboard` v0.63.0, commit `1b1f024`) — `AOD_CD_Section_Abandoned::maybe_assign_converter()` (extrait de `ajax_convert_lead()` pour testabilité) pose `_aod_assigned_confirmer` = l'utilisateur courant **si et seulement si** c'est un confirmateur porteur de `aod_cap_confirm_orders` ; **jamais** de passage par `assign_least_loaded()` (D-conversion de A.19) — le confirmateur a déjà pris possession du prospect en le rappelant, un tourniquet la lui reprendrait arbitrairement. Le gérant qui convertit ne s'auto-assigne pas (il n'est pas un confirmateur, il pourra répartir ensuite via 42.4/42.5). Cliquet étendu (groupe 42.2). |
| 42.3 | Cumul dans le périmètre de lecture (Commandes + Suivi) | M | ✅ **Livrée le 2026-07-26** (`aod-client-dashboard` v0.64.0, commit `1b1f024`) — `scoped_orders()` gagne un second filtre **cumulatif** au filtre produit existant (jamais substitué, D1) : si l'utilisateur courant n'est pas le gérant et porte `aod_cap_confirm_orders`, clause `meta_query` sur `_aod_assigned_confirmer = user_id`. 🚨 **Piège d'ordre trouvé et corrigé** : la clause doit être injectée **avant** le `return wc_get_orders( $args )` du chemin « pas de périmètre produit », sinon un confirmateur sans restriction produit mais porteur de la cap voit quand même tout le monde — red-proofé (inverser l'ordre, confirmer que ça redevient rouge). `order_in_scope()` gagne la même garde en défense en profondeur : un ID de commande assignée à un pair, dans le même périmètre produit, reste refusé (403) sur accès direct. `AOD_CD_Section_Tracking` hérite du filtre sans y toucher (il appelle `scoped_orders()`/`order_in_scope()` directement). Un confirmateur SANS `aod_cap_confirm_orders` (tracking/stock seul) : comportement **inchangé** — le filtre suit la capability, pas le rôle. Cliquet (groupe 42.3) : confirmateur A ne voit/n'agit QUE sur ses commandes assignées, même produit qu'un confirmateur B qui, lui, ne les voit pas ; commande non assignée invisible des deux ; gérant voit tout. Accueil scindé en 42.3bis (mécanisme totalement séparé, cf. ci-dessous). |
| 42.3bis | Cumul dans les stats de l'Accueil (scindé de 42.3) | M | ✅ **Livrée le 2026-07-26** (`aod-client-dashboard` v0.65.0, commit `71818d3`) — `AOD_CD_Section_Home::assignment_scope()` (jumeau de `user_scope()`, même garde `aod_cap_confirm_orders`) ; `filtered_orders()`/`each_filtered_order()`/`count_status()` gagnent un paramètre `$assigned_id` threadé à **tous** les sites d'appel (revenus, livraison, todo « À traiter »), `home_join_sql()` gagne une jointure jumelle sur `_aod_assigned_confirmer`. 🚨 **Explicitement NON touchés, décision écrite** : `confirmation_data()`, `revenue_data()` agrégé, `count_tracking_status()`, `count_blocked_parcels()` — appliquer le filtre à `confirmation_data()` aurait corrompu la vue agrégée « performance par confirmateur » du gérant (une commande réassignée fausserait qui a réellement confirmé quoi) ; prouvé par un red-proof **inversé** (assertion que la fonction n'est PAS filtrée). Cliquet dédié `test-assignation-accueil.php`. Scindée de 42.3 en écrivant la story : `AOD_CD_Section_Home` ne partage AUCUN mécanisme avec `scoped_orders()` — fusionner aurait fait courir le risque déjà rencontré sur ce fichier (bug 5.5, « périmètre produit oublié dans Home », réapparu une fois). |
| 42.4 | Sélecteur d'assignation/réassignation (gérant uniquement) | M | ✅ **Livrée le 2026-07-26** (`aod-client-dashboard` v0.66.0, commit `39a7423`) — nouveau contrôle par commande, visible **seulement** du gérant (`manage_woocommerce`) — un confirmateur n'a besoin de voir ni le sélecteur ni le nom de l'assigné (c'est toujours lui, garanti par 42.3/42.3bis). Widget `assign_select_html()` **dupliqué** du patron `status_select_html()` (menu positionné en JS, clavier/ARIA du socle a11y Phase 5) plutôt que partagé — les deux évoluent indépendamment (option « Non assignée » propre à l'assignation). Options = `eligible_confirmers( $product_ids, $ignore_scope = true )` pour le gérant (il peut assigner n'importe qui, y compris hors du périmètre habituel d'un confirmateur). Nouvel AJAX `aod_cd_order_assign` : nonce `aod_cd` + `manage_woocommerce` strict (famille **action-forte**, comme `delete_orders`/`save_stock` — **jamais** `aod_cap_confirm_orders`, sinon un confirmateur pourrait se réassigner des commandes d'un pair). Placement dans la modale de détail (la table est déjà au plafond de 7 colonnes, héritage 5.4). Cliquet `test-permissions-sections.php` : nouvelle entrée `order_assign` = `manage_woocommerce`/action-forte avec sa raison écrite ; POST forgé en confirmateur refusé (403). Census `WordPress.Security.EscapeOutput` 141→142. |
| 42.5 | Rattrapage : assignation groupée des commandes non assignées | M | ✅ **Livrée le 2026-07-26** (`aod-client-dashboard` v0.67.0, commit `39a7423`) — réutilise le patron de sélection groupée de la Phase 39.6 (barre `.aod-cd-bulkbar`, case « tout sélectionner », compteur) : filtre **« Non assignées »** (`.aod-cd-unassigned-toggle`, `count_unassigned()`) + `<select class="aod-cd-order-bulk-assign-select">` natif (pas un 3ᵉ dropdown personnalisé — la barre est pleine largeur, aucun risque de clipping) + bouton `.aod-cd-order-bulk-assign`. Nouvel AJAX `aod_cd_orders_bulk_assign` (nonce `aod_cd` + `manage_woocommerce`, action-forte, même famille que `aod_cd_delete_leads`). **D4-rattrapage tranché en écrivant la story** : `bulk_assign()` **saute** silencieusement toute commande déjà assignée reçue dans le lot (pas d'écrasement en masse — une réassignation individuelle reste possible via 42.4) ; testée au rouge. Cliquet étendu (groupe 42.5). |
| 42.6 | Clôture : i18n, cliquet, porte de recette, docs | S | ✅ **Livrée le 2026-07-26** — `make-translations.sh --strict` vert (chrome neuf : « Non assignée », « Assigner à… », « Assigner », confirmation groupée) + AR manuel. Porte de recette **`run.sh assignation`** (fixtures `scripts/recette-seed-assignation.php` — HPOS-safe, `wc_get_orders()` et non `get_posts()` pour le nettoyage, bug trouvé et corrigé pendant l'écriture du seed) : 20 assertions, bout en bout au navigateur — (a) le sélecteur de la modale affiche le confirmateur assigné ou « Non assignée », (b) le filtre « Non assignées » + son compteur, (c) l'assignation groupée de 2 commandes en un geste, (d) un **VRAI** compte confirmateur (`login(page,{user,pass})` + témoin d'identité `whoAmI` — leçon Phase 22/D13, jamais tester en gérant) ne voit QUE ses commandes assignées, jamais celles d'un pair dans le même périmètre produit, jamais une commande non assignée, et ne voit NULLE PART le sélecteur d'assignation ni la barre groupée (pouvoir gérant strict). **Red-proofée** : `assignment_scope()` neutralisée à `0` → exactement 2 échecs (les 2 assertions de cumul confirmateur), restaurée par édition inverse. MAJ `docs/ARCHITECTURE.md` (méta `_aod_assigned_confirmer`, 3 AJAX neufs) et `TEMPLATE.md` (« Phase 42 soldée »). **1170 tests**, `test-doc-derive` vert (R2/R5/R6). |
| 31.1 | La case « tarifs » donnait les comptes transporteurs | M | ✅ **Livrée le 2026-07-22** (`2426dc1`) — 🔴 **faille réelle, escalade de privilèges.** `aod_cap_shipping_prices` (« Personnalisation des tarifs ») ouvrait aussi le bloc Transporteurs : `render_settings_fields()` imprime les jetons API en clair (`type="text"`), et le domaine EcoTrack n'était validé nulle part → un confirmateur « tarifs » lisait les clés du client PUIS pouvait rediriger l'envoi auto vers son propre hôte (PII acheteurs + SSRF). Correctif : bloc Transporteurs au gérant (garde AUSSI côté serveur, le masquage d'UI ne protège rien), `sanitize_domain()` refuse IP/localhost/port/chemin/`user@host`. 🚨 **RÉ-OUVRE à dessein la divergence fermée par la Phase 21** : sa prémisse (« qui importe doit tester ») confondait lecture et écriture — `test_carrier` ÉCRIT les identifiants postés. Déclaré 'action-forte' avec sa raison. 🚨 **Pourquoi rien ne l'a vu** : `test-permissions-sections.php` vérifie la COHÉRENCE des gardes (elles l'étaient), `confirmateur.js` qu'une cap OUVRE/FERME son écran (attendu). Aucun ne mesure la **PORTÉE**. Livrable = `tests/unit/test-cap-portee.php` : endosse un vrai confirmateur, rend la section, lit ce qui sort ; chaque absence ancrée par deux présences (le gérant VOIT le jeton témoin, le confirmateur VOIT toujours la grille). **Éprouvé au rouge.** 589 tests (+27), recette `confirmateur` 36/0. |
| 31.2 | Deux commandes pouvaient porter le même numéro | S | ✅ **Livrée le 2026-07-22** (`12e532c`) — compteur séquentiel en lecture-modification-écriture sans verrou : deux commandes dans la même seconde (le cas ORDINAIRE en campagne pub) lisaient N et écrivaient N+1 toutes deux → même numéro sur le bordereau et au téléphone. Le `static $in_progress` ne couvre que la réentrance d'UN processus. Correctif : `aod_seq_reserve_number()`, réservation en UNE instruction SQL (`LAST_INSERT_ID(expr)`, mémorisé PAR CONNEXION — relire l'option rouvrirait la fenêtre) ; échec → aucun numéro plutôt qu'un zéro. 🚨 **Sur la preuve** : 10 processus parallèles n'ont RIEN démontré (fenêtre de quelques µs, démarrage ~1 s) — non présenté comme une preuve. Le MÉCANISME a été éprouvé fenêtre élargie à 2 s : motif d'avant → 6 commandes numérotées « 1 » ; motif atomique → 1 à 6. Ne dit rien de la fréquence réelle. Cliquet `test-numerotation-sequentielle.php` (R1 monotonie, R2 interdit le retour du motif, R3 le test n'a pas touché le compteur RÉEL). 601 tests (+12). |
| 31.3 | Journalisation des 6 sorties externes + le catch de `handle_submit()` | M | ✅ **Livrée le 2026-07-22.** 0 `error_log`/`wc_get_logger` sur 24 513 l, pour 6 intégrations HTTP **toutes en fail-open** : le système ne pouvait pas rapporter ses propres pannes (c'est de ce silence qu'a vécu le défaut TikTok de la Phase 13). Le `catch` de `class-aod-cod-form.php` n'inspectait même pas `$e` — une **vente perdue** ne laissait rien. Socle `AOD_COD_Log` branché sur 2 goulots (`AOD_Carrier::remote()` = 29 transporteurs, `capi_http_post()` = 3 régies) + Turnstile + WhatsApp + le `catch` : 5 points, pas 6. 🚨 **Le piège** : 4 intégrations mettent leur secret **dans l'URL** (Noest, EcoTrack, WhatsApp, test Meta) — une URL est UNE CHAÎNE, donc un masqueur par nom de clé n'y voit rien, et « loguer l'URL appelée » publierait le jeton. D'où **3 couches** (nom de clé récursif · nom de paramètre d'URL · valeur connue), collecte des secrets **dynamique** via le registre des transporteurs (une liste en dur aurait vieilli en silence — et son vieillissement EST la fuite). Cliquet `test-journalisation.php` : R1a/R1b/R1c éprouvent **chaque couche isolément** (les tester ensemble laisserait une couche morte couverte par ses voisines), R2 interdit le **sur-masquage** (masquer tout passe R1 et rend les journaux inutiles), **R4 interdit tout journal écrit hors du rédacteur** — c'est R4, pas R1, qui empêchera la fuite de revenir. **Éprouvé au rouge 4 fois** (couche 2 désarmée, masqueur total, `wc_get_logger()` direct, `blocking => false` remis) + bout-en-bout sur HTTP **bouchonné** : jeton masqué à la fois dans l'URL et dans l'écho du corps, hôte et code HTTP lisibles. **Solde aussi D22.** 644 tests (+43). |
| 31.4 | Double colis chez le transporteur (garde non atomique autour d'un HTTP) | M | ✅ **Livrée le 2026-07-22.** 30 s de HTTP entre la garde `META_TRACKING` et son écriture, en synchrone dans la requête du confirmateur : deux colis, deux encaissements COD chez le même client. 🚨 **Trouvé en route, pire que la dette annoncée** : `ajax_send()` — l'envoi MANUEL — n'avait **aucune garde**, pas même la garde non atomique. Deux clics créaient deux colis de façon **déterministe**, sans course. Correctif : goulot unique `send_parcel()` (les 3 chemins — auto, manuel, groupé — l'appelaient chacun en direct), verrou atomique par commande via l'index UNIQUE de `option_name` (`add_option`, pas `set_transient` qui écrase sans rien dire), reprise de verrou périmé par UPDATE conditionnel — la condition d'ancienneté DANS le `WHERE`, sinon on rouvre la fenêtre qu'on ferme. ⚠️ **Le point subtil** : le verrou SÉRIALISE, il n'annule pas — sans **relecture sur instance fraîche** après acquisition, le second envoi partait quand même et le verrou n'aurait servi qu'à mettre les deux à la queue leu leu, tout en paraissant corriger. ⚠️ **Péremption obligatoire** (`LOCK_TTL` 300 s) : un verrou éternel échangerait un défaut rare contre une commande inexpédiable à vie, muette. Cliquet `test-verrou-envoi.php` : R1 exclusivité, R2 reprise du périmé + **ancrage inverse** (un verrou frais ne se vole pas), **R3 anti-contournement** (un seul `create_parcel()` dans toute la classe — c'est R3, pas R1, qui empêche un 4ᵉ chemin de rouvrir le défaut), R4 **fonctionnel** sur transporteur bouchonné. 🚨 **R4 était d'abord TEXTUEL et a survécu au défaut** : la garde supprimée, « relit META_TRACKING » restait vert — la constante figurait encore dans un message de journal. Resserrer le motif n'a rien changé (même cause). Réécrit en fonctionnel : deux envois **séquentiels** (verrou déjà rendu, donc **seule** la relecture peut arrêter le second) → au rouge, `appels : 2`, le second colis part vraiment. **Une assertion textuelle qu'une mention sans rapport suffit à satisfaire ne prouve rien.** Éprouvé au rouge 3 fois. Nettoyage des verrous ajouté à `uninstall.php` (littéral, PAS `AOD_Shipping::LOCK_PREFIX` — `uninstall.php` ne charge aucune classe : un fatal y surviendrait quand l'utilisateur ne peut plus rien réparer). 664 tests (+20). Portes `cod` et `suivi` vertes. |
| 31.5 | Deux portes de recette ne prouvent pas ce qu'elles annoncent | S | ✅ **Livrée le 2026-07-22.** **Cinq** défauts distincts corrigés, pas deux — le repli de routage touchait à lui seul **trois** portes (`rtl`, `noms`, `contraste`). **(1) Le repli silencieux du routeur** — mesuré : `/gestion/section-bidon` rend `home` avec `body.aod-cd` présent, donc TOUS les contrôles passent. Les portes `rtl`, `noms` et `contraste` pouvaient auditer **12 fois `home` en imprimant 12 ✅** — or `rtl` est le filet annoncé de la Phase 30 : une section extraite qui ne se route plus lui était **invisible**. Le piège avait DÉJÀ mordu (`/gestion/stats` rendait `home`, cf. commentaire de `SECTIONS`). `sectionRendue()` remonte dans `lib.js` (la copie locale de `confirmateur.js` est retirée : deux définitions auraient divergé), et `contraste` publie désormais **« N/12 sections réellement auditées »** au lieu d'annoncer `SECTIONS.length`. **(2) `mouvement.js`** — `!! bg` est une tautologie : `getComputedStyle().backgroundColor` ne rend jamais vide, un fond absent vaut `rgba(0, 0, 0, 0)`, qui est vrai. L'assertion passait donc **dans le seul cas qu'elle existe pour attraper**. Le jumeau public était durci depuis l'origine ; le durcissement n'avait pas été recopié. **(3) `public-3-pages.js`** — « aucun libellé resté en français » est vrai si `labels` est **vide** : menu non rendu, sélecteur périmé → la porte annonçait un bilinguisme parfait sur une nav inexistante. Ancré par une présence (≥ 3 entrées). **(4) `commandes-suivi.js`** — « exclue partout » n'inspectait que la 1ʳᵉ page d'une liste **paginée** : sur un clone qui a vendu, la commande serait hors page 1 et la porte rendrait le même vert sans rien établir. Absence désormais ancrée **par une recherche** (qui porte sur toute la table). **(5) `test-antibot.php`** — la disjonction `true === verify() || ! is_enabled()` masquait DEUX cas opposés : sur un clone neuf le 2ᵉ membre suffisait (mesuré) donc `verify()` n'était jamais éprouvé ; sur un clone où Turnstile EST configuré, un jeton vide est correctement rejeté, les deux membres étaient faux et **le test échouait alors que la protection marchait**. Séparé en deux branches. 🚨 **Deux de mes propres assertions étaient fausses, trouvées en jouant les portes** — pas en relisant : `PAGES` de `a11y-noms.js` contient `products?action=new` (comparaison de slug naïve), et le terme cherché est **réaffiché dans le champ de recherche**, donc `has( html, … )` le trouve toujours — vérifié à la source, `wc_get_orders( s=Expedie )` rend bien **0 commande**. On compte donc des **lignes de résultat**, jamais des occurrences dans le HTML. L'ancrage souffrait du même défaut : **un ancrage faux est pire qu'un ancrage absent.** Éprouvé au rouge : section qui ne se route plus (3 échecs `ROUTAGE`), recherche stérile (l'ancrage tombe **pendant que l'assertion d'absence reste verte** — la démonstration même de son utilité), et les deux tautologies évaluées sur leur entrée dégénérée. Portes `rtl`, `confirmateur`, `noms`, `contraste`, `mouvement`, `suivi`, `public` toutes vertes ; accent de marque du gérant (#00A651) vérifié intact après la passe. 664 tests. **Aucune ligne de production n'a changé : le livrable est la recette qui redevient un juge.** |
| 31.6 | Tenue en charge de `/gestion` + porte `run.sh charge` | L | ✅ **Livrée le 2026-07-22.** Les **4** agrégats qui bouclaient sur les commandes (Revenus, Livraison, Confirmation, Ventes/Stock) chargeaient toute la période d'un coup puis appelaient `get_items()` par commande. **MESURÉ à 10 000 commandes, avant → après** : Revenus **94,0 → 26,0 Mo**, Livraison **28,0 → 0,0 Mo**, Confirmation **86,0 → 4,0 Mo** ; temps inchangé (4,79 → 5,01 s · 0,82 → 0,86 · 2,61 → 2,59). C'est le PIC qui compte : dépasser `memory_limit` rend un écran **blanc**, pas lent — sur un mutualisé à 128 Mo l'ancien Accueil ne passait pas, donc **le gérant dont la boutique marchait le mieux perdait son tableau de bord**. Cache désormais versionné **par mois** : le compteur global invalidait TOUT à chaque écriture, donc à une commande / 3 min un TTL de 5 min n'était jamais atteint — *le cache protégeait la boutique qui n'en a pas besoin et lâchait sur celle qui en a besoin*. ⚠️ **Pas de bascule vers `wc_order_product_lookup`**, contrairement à l'énoncé : cette table appartient à WC Analytics (désactivable, vide, ou en retard) — en tirer le CA aurait échangé un écran blanc, visible, contre des **chiffres faux en silence** sur lesquels le gérant décide ses achats. Option « à défaut » retenue : les lots, qui ne changent RIEN au calcul. Prouvé par **comparaison octet à octet** de la sortie des 4 agrégats, avant/après, sur les données réelles du clone. 🚨 **Trois défauts trouvés en mesurant, pas en relisant.** (1) `wc_get_orders( array( 'include' => … ) )` — **clé non supportée, IGNORÉE EN SILENCE** : 5 identifiants demandés → 31 commandes rendues, et le CA passait de 113 000 à 174 500 DZD. Aucune erreur. Corrigé en `post__in` **+ tri par appartenance**, pour rester exact même si la clé cessait d'être honorée. (2) `wp_cache_delete( $id, 'orders' )` n'atteint pas le cache de commandes, qui vit dans un groupe **préfixé** — signe : le pic ne bougeait pas quand on divisait les lots par 8. Passé par l'API `OrderCache`. (3) **Mon propre instrument était faux** : `memory_get_peak_usage()` est monotone et le semis avait déjà fait monter le pic ; sans `memory_reset_peak_usage()` + `wp_cache_flush()` entre agrégats, je citais des chiffres flattés (les 2ᵉ et 3ᵉ agrégats réutilisaient le cache chaud du 1ᵉʳ — artefact du harnais, la production faisant une requête par onglet). 🚨 **Et la porte elle-même comptait mal ses échecs** : sous `wp eval-file` le fichier est inclus DANS une fonction, donc `global $aod_ch_fail` pointait sur du vide — elle imprimait ✗ puis concluait « 0 échec ». Compteurs passés en statiques ; un run sans aucun contrôle exécuté échoue désormais aussi. Porte `run.sh charge` : sème son volume (marque `_aod_charge_temoin`), mesure temps + pic à cache froid, **purge**, et vérifie que le nombre de commandes RÉELLES est inchangé (31 → 31 à chaque run). **Volontairement HORS de `run.sh tout`** — plusieurs minutes de semis. Cliquet `test-charge-agregats.php` (R1 les 4 agrégats en lots, **R2 anti-retour** du `limit => -1` rendant des objets, R3 `include` bannie, R4 versionnement par bac **fonctionnel** avec ancrage). Éprouvé au rouge 3 fois. 676 tests (+11). Portes `charge` et `rtl` vertes ; 31 commandes réelles et accent #00A651 intacts. ⚠️ Les **5** autres `limit => -1` portent sur des **produits / utilisateurs** — quelques dizaines par boutique, pas des dizaines de milliers : laissés tels quels à dessein, R2 les ignore explicitement. |
| 32.1 | Le démarrage n'est jamais éprouvé en mode dégradé (D17) | M | ✅ **Livrée le 2026-07-22.** **Fatale REPRODUITE** avant correctif : `Class "AOD_Carrier" not found @ class-aod-cd-section-home.php:550`. Le chargeur promettait pourtant, noir sur blanc, une « dépendance forte **non bloquante** » où « ces parties se désactivent **proprement** » — le correctif ne change pas le contrat, **il le fait respecter**. 🚨 **La garde EXISTAIT** : `count_tracking_status()` ouvre bien sur `class_exists( 'AOD_Carrier' )`. Mais l'appel s'écrivait `count_tracking_status( AOD_Carrier::ST_FAILED )` — **PHP résout la constante pour construire l'appel, donc AVANT d'entrer dans la méthode**. Une garde placée derrière ce qu'elle protège n'est pas une garde : elle est pire, elle donne à la relecture les apparences de la sûreté. ⚠️ **Et pourquoi personne ne l'avait vue** : la ligne vit dans une closure `cached()`, donc elle ne s'exécute qu'à **cache froid**. Un transient chaud masque la fatale. La porte force donc l'invalidation avant de rendre. Correctif : `AOD_CD_Cod::can_tracking()` — on conditionne la **fonctionnalité** à sa dépendance, une fois, au lieu de garder chaque constante (course sans fin ; il suffit d'en oublier une). + les 2 `public static` `tracking_tone()` / `tracking_pill()` rendues sûres **par construction** : elles ne l'étaient que par leurs appelants, propriété qui se périme au premier appel écrit ailleurs. Porte **`run.sh degrade`** (dans « tout », rapide) : rend les **12 sections en gérant** — le pire cas — dans un processus lancé via **`wp --skip-plugins=aod-cod-form`**. 🔒 **Elle ne désactive RIEN** : isolation par processus, l'installation n'est pas touchée. `wp plugin deactivate` sur le clone d'un vrai client couperait la boutique, et la laisserait coupée si la porte mourait entre les deux commandes — on rend l'accident impossible au lieu d'essayer de le rattraper (leçon D26). Elle **refuse de conclure** si l'état dégradé n'est pas en place. **Éprouvée au rouge 2 fois** : défaut d'origine remis (elle tombe sur la bonne ligne), et lancée sans isolation (elle s'arrête au lieu de verdir). 🚨 **Cliquet volontairement AMPUTÉ de sa règle principale** : « aucune constante COD passée en argument » signalait **30 sites dont 1 seul fautif** — savoir si une ligne est gardée demande de remonter portée puis appelants, ce qu'un `preg_match` ne peut pas faire. **Un cliquet qui crie au loup sur du code sain finit par être ignoré**, donc par ne plus rien garder. Retirée, raison écrite dans le fichier ; la porte fonctionnelle est la preuve. 681 tests. ⚠️ Plafond de `test-decomposition.php` relevé 2836 → **2849** (+13), **avec sa raison** : ce sont les 2 gardes, pas de la ré-absorption. Elles partiront avec la section `tracking` (30.5). |
| 32.1bis | ~~D18 : `AOD_CD_Section_Abandoned` jamais instanciée~~ | — | ❌ **FAUX POSITIF, retiré le 2026-07-22.** Vérification : son `__construct()` est **vide**, elle n'enregistre **aucun hook**, n'a **aucun handler AJAX**, et son unique entrée publique `render()` est appelée à la demande (`class-aod-cd-dashboard.php:616`). **Ne pas l'instancier au boot est correct** — l'ajouter n'aurait rien changé qu'ajouter du bruit. L'audit d'origine avait généralisé depuis les sections qui, elles, enregistrent des hooks dans leur constructeur. 🚨 À retenir : *une anomalie de FORME (« elle n'est pas dans la liste comme les autres ») n'est pas une anomalie de FOND* — il faut regarder ce que la classe fait, pas à quoi elle ressemble. |
| 32.2 | La surface d'entrée non authentifiée (D19 + D20) | M | ✅ **Livrée le 2026-07-22.** ⚠️ **Le titre de la story était faux sur la moitié de son périmètre**, et c'est le premier acquis : les 5 téléversements ne sont **pas** une surface non authentifiée — ils exigent `check_ajax_referer( 'aod_cd' )` **et** `current_user_can( 'edit_products' )`, aucun `wp_ajax_nopriv_*` n'existe dans le tableau de bord, et `edit_products` (capability du cœur WP) n'est dans aucune des 7 `aod_cap_*` qu'un confirmateur peut recevoir : **seuls le gérant et l'administrateur y accèdent**. La liste blanche reste due — non pour ce qu'elle bloque aujourd'hui, mais parce que `get_allowed_mime_types()` est **filtrable** : un clone qui installe un plugin autorisant le SVG transforme les 5 champs en **XSS stockée sans qu'une ligne du dépôt ait changé** (cf. D19). Les 6 appels du dépôt passent désormais par `AOD_CD_Upload::handle()` — **un seul `media_handle_upload()` subsiste**, dans ce helper, et le cliquet R1 refuse le 7ᵉ. 🚨 **Le piège de l'étape** : `upload_one()` de la page de vente sert aussi le champ `video.file` (« Ou fichier vidéo (MP4) », Phase 6) — une liste image-seule appliquée sans énumérer les appelants aurait **cassé en silence une fonctionnalité livrée**, avec pour tout symptôme « format refusé ». D'où une liste **pilotée par le type du schéma**. Prouvé de bout en bout sur HTTP réel : ZIP, PDF et **ZIP renommé `.jpg`** refusés, JPEG et MP4 acceptés, une image refusée par le champ vidéo. 🔴 La VRAIE porte non authentifiée était unique — `aod_cod_lead` : **mesuré à 15 jetons inventés → 15 lignes, 15 × HTTP 200** ; désormais **60 acceptées / 10 refusées en 429** sur 70 tentatives. Le plafond compte les **CRÉATIONS, jamais les requêtes** (le JS republie le prospect à chaque frappe : compter les requêtes aurait exclu les acheteurs qui se reprennent), et un jeton **déjà connu** passe toujours, IP saturée ou non. Cliquet `tests/unit/test-surface-entree.php`, 4 règles éprouvées au rouge. |
| 32.2bis | D28 : le journal n'écrivait rien depuis une requête web | S | ✅ **Livrée le 2026-07-22.** Trouvée en vérifiant que le plafond de la 32.2 laissait bien une trace — *c'est en doutant de sa propre garde qu'on trouve celle d'à côté.* La Phase 31.3 avait bâti `AOD_COD_Log` pour rendre 6 intégrations en fail-open diagnosticables, puis l'avait éprouvée **sous WP-CLI** : sur une requête web, elle n'écrivait rien. Cause hors de notre code (bind-mount ⇒ propriétaires divergents ⇒ WordPress refuse « direct »), correctif `FS_METHOD` dans `docker-compose.yml`, garde `run.sh journal`. 🚨 **Le contrôle ne pouvait pas être un test unitaire** : `tests/run.sh` tourne dans le processus qui fonctionnait déjà. |
| 32.3 | « 5 couches anti-bot » en annonce 5, en tient 1 (D21) | M | ✅ **Livrée le 2026-07-22.** Les deux couches « de formulaire » se contournaient **en ne faisant rien** : le honeypot en ne remplissant pas le piège, le time-trap en **omettant** `elapsed`. 🚨 La garde s'écrivait `isset() && is_numeric() && < 3000` — **`is_numeric()` y était une condition d'APPLICATION, pas une exigence** : un payload sans le champ traversait les trois conditions **sans en heurter une seule**. La garde ne le refusait pas, elle ne le regardait pas. Désormais l'**absence vaut aveu**, dans une garde unique (`AOD_COD_Antibot`) partagée par la commande ET le contact — qui partageaient déjà les deux contournements, et qu'un durcissement appliqué d'un seul côté aurait fait diverger. Chaque refus est **journalisé** (canal `antibot`, motif distinct) alors que l'acheteur voit toujours le **même message neutre**. MESURÉ sur le contact, avant/après : payload nu et `elapsed=abc` **atteignaient l'envoi du courriel** ; ils sont maintenant refusés, tandis que `elapsed=9000` passe toujours (l'ancrage qui prouve qu'on n'a pas tout fermé). ⚠️ **C'est un arbitrage, pas un gain gratuit** : la tolérance d'origine protégeait l'acheteur servi depuis un cache. Elle était mal réglée, pas absurde — ce qui la borne désormais est écrit à la source (mêmes champs, même requête ; jQuery sérialise `undefined` en chaîne vide ; refus récupérable ; refus tracé). Et **la moitié « discours » comptait autant que le code** : 9 fichiers réalignés, dont deux qui annonçaient **6** couches. Cliquet `tests/unit/test-antibot-couches.php` (R5 refuse un compte annoncé sans sa nuance), éprouvé au rouge. |
| 32.4 | « Actualiser tout » du Suivi est un auto-DoS (D23) | M/L | ✅ **Livrée le 2026-07-22.** Moteur par TRANCHES sous budget de temps (`run_sync_batch()`) : l'AJAX ne traite plus que ~10 s par requête puis rend la main, `dashboard.js` enchaîne (curseur `since` = l'instant du clic, progression chiffrée sur le bouton) ; le cron garde 120 s. 🚨 **La reprise ne se code pas, elle se lit dans la donnée** : chaque commande traitée reçoit un `META_STATUS_AT` frais et `pending_orders()` sert les moins récentes d'abord — l'estampille EST le curseur, rien à stocker ni invalider. Une commande en erreur n'est PAS estampillée (elle reste candidate), et c'est le SERVEUR qui conclut la boucle quand une tranche ne progresse plus — sinon elle rejouerait les mêmes échecs à l'infini. Garantie de progrès : la 1ʳᵉ fournée part même budget dépassé (sans elle, un budget court ne ferait jamais rien, tranche après tranche). Timeout transporteur 30 s → **10 s** (`AOD_Carrier::HTTP_TIMEOUT` ; les API DZ répondent en < 2 s — 30 s ne rattrapaient rien, elles transformaient une panne en timeout PHP). Chaque passage se **journalise** (canal `tracking` ; interrompu au budget = `error`, sinon `debug`) : « le suivi ne bouge plus » devient enfin diagnosticable — possible uniquement parce que 32.2bis a réparé le journal web la veille. Les colis en panne sont **annoncés au gérant** (« %d colis n'ont pas répondu… ») au lieu d'être avalés. Éprouvé sur transporteur **bouchonné** deux fois : stub duck-typé injecté par réflexion (unitaire), et bouchon `pre_http_request` HTTP 500 posé le temps d'un clic réel au navigateur — zéro octet vers une vraie API, zéro mutation, boucle conclue en 135 ms, toast + journal vérifiés. Cliquet `tests/unit/test-suivi-lots.php` (R1 budget + garantie de progrès avec ancrage inverse, R2 reprise par estampille, R3 l'erreur d'un colis ne coûte rien aux autres, R4 le lot reste un lot, R5 câblage + anti-retour du `$svc->run_sync()` synchrone), **éprouvé au rouge deux fois** (échéance désarmée : 5 échecs ; signature de l'auto-DoS réintroduite : rouge net). 752 tests (+23). Portes `suivi` et `rtl` vertes. ⚠️ Plafond de `test-decomposition.php` relevé 2849 → **2884** (+35), raison écrite : `ajax_tracking_refresh()` vit encore dans le monolithe et partira avec la section `tracking` (30.5). |
| 32.5 | 16 `catch` au corps vide dans `dashboard.js` (D24) | S | ✅ **Livrée le 2026-07-22.** ⚠️ Mesuré : **30** initialiseurs (pas 27), **19** catch muets (pas 16), 11 nus — *un énoncé de story n'est pas une mesure*, troisième fois d'affilée. Helper unique `init( name, fn )` : chaque binder est ISOLÉ (une exception dans `bindStatus` ne tue plus les 25 suivants — c'est le seul changement de comportement, et il est voulu) et l'échec est NOMMÉ en console (`[aod-cd] init « bindTeam » a échoué : …`). Console et non toast, à dessein : défaut de développement, pas message gérant. 🚨 **Le réflexe s'était DÉJÀ recopié côté acheteur** : 3 `catch ( e ) {}` dans `aod-cod-form.js` — mais ces trois-là sont VOULUS et restent : chacun isole un appel TIERS (Turnstile.reset, gtag, flexslider) autour du chemin d'achat, où le silence est la bonne politique. Ils sont désormais **recensés avec leur raison** dans le cliquet, qui refuse tout nouveau catch muet public (une exemption sans raison écrite est un échec — le motif du census phpcs de la Phase 23). Cliquet `tests/unit/test-init-binders.php` (R1 zéro catch muet dans `/gestion` + ancrage : les 3 replis explicites `JSON.parse` survivent ; R2 chaque initialiseur sans argument passe par `init()` ; R4 census public), **éprouvé au rouge sur les trois règles d'un coup**. Preuve fonctionnelle au navigateur : nominal 4 sections / 0 erreur console, puis panne SIMULÉE de `bindTeam` (sabotage `getElementById` via `addInitScript`) → exactement UNE ligne console qui le nomme, page rendue, navigation intacte — dégradé, pas détruit. 763 tests (+11). Portes `rtl` et `souris` vertes. |
| 32.6 | La documentation décroche de son propre code (D25) | S/M | ✅ **Livrée le 2026-07-22.** Cliquet `tests/unit/test-doc-derive.php`, qui RECALCULE le squelette documentaire à chaque run au lieu de croire les chiffres : R2 chaque en-tête « ### N.M » de la ROADMAP est d'accord avec sa ligne §C.3 (la signature exacte de la dérive « table arrêtée à 30.1 ») ; R3 le compteur « x/6 » de la Phase 32 dans TEMPLATE.md = les ✅ comptés en table (et « SOLDÉE » exige 6/6) ; R4 « N sections extraites sur 6 » = les ✅ des lignes 30.x ; R5 « Phases 1 à N soldées » ⇒ aucune étape ⏳ en dessous ; R6 tout `test-*.php` cité existe dans tests/unit/ et toute porte `run.sh <x>` citée est déclarée dans SCENARIOS (le `contrast` de la story 17.0 est exempté AVEC sa raison : c'est la faute de frappe qu'elle raconte, pas une porte). Lecture des docs via un montage `/repo:ro` ajouté à `docker-compose.yml` — la racine du dépôt n'était PAS visible du conteneur de tests (le R5 de la 32.3 n'avait pu relire qu'un seul document, en le disant) ; sur un clone existant : `docker compose up -d wpcli`, et le cliquet ÉCHOUE avec cette consigne si le montage manque — un cliquet qui se saute en silence serait le défaut qu'il garde. 🚨 **Il a mordu à son premier run honnête** : l'en-tête `### 30.1` livré ne portait pas son ✅ — réparé. 🚨 **Et il est né avec son propre défaut, trouvé en l'éprouvant** : `preg_split('/\R/')` sans `/u` traite l'octet NEL (0x85) comme un saut de ligne — or 0x85 est le DERNIER octet UTF-8 de « ✅ » : le parseur coupait chaque ✅ en deux et ne voyait que 2 étapes livrées sur 110. Éprouvé au rouge sur R2 (données réelles), R3 (compteur faussé) et R6 (cliquet fantôme cité). ⚠️ Il fige le SQUELETTE (états, compteurs, références) ; la prose qui raconte faux sans chiffre reste à la relecture humaine — limite écrite dans son en-tête. 776 tests (+13). |
| 42.7 | Colonne « Assigné à » (Commandes + Suivi) + correctif de fuite HPOS | S | ✅ **Livrée le 2026-07-26** (`ea8a20d`, correctif `be6db6a`) — ⚠️ **étape ajoutée APRÈS la clôture de la 42.6, et restée invisible de cette table jusqu'à la 43.9** : elle n'existait qu'en git. `assigned_confirmer_label()` (`public static`) rend le nom de l'assigné ou un tiret sourd ; colonne **gérant uniquement** sur Commandes et Suivi — un confirmateur ne doit jamais voir le nom d'un assigné, y compris le sien. Ajoute `unassign_confirmer()` : `wp_delete_user()` ne réattribue que `post_author`, jamais cette méta métier — sans cet appel, supprimer un confirmateur laissait ses commandes marquées « assignées » à un compte fantôme, invisibles du filtre « Non assignées » (qui teste une ABSENCE de méta, pas la validité de sa valeur) ET de tout confirmateur. Silencieusement perdues pour tout le monde sauf le gérant, qui les croyait prises en charge. |
| 43.1 | Filtre par date (Commandes, Suivi, Abandonnées) | M | ✅ **Livrée le 2026-07-27** (`352c802`) — formulaire GET sans JS, 4 helpers partagés promus dans la coque (`parse_date_param()`, `resolve_date_range()`, `date_created_query_arg()`, `render_date_fields()`). ⚠️ **Correction au cadrage** : le plan prévoyait un filtre Suivi sur une « date d'expédition » **qui n'existe pas** — la seule date rendue sur cette page est `date_created` (`_aod_ship_status_at` est la dernière mise à jour de suivi, pas la date d'envoi). Les deux pages filtrent donc sur la même date, celle réellement affichée. Cliquet `test-filtre-date.php`. Plafond `test-decomposition.php` relevé 936 → 1052 **avec sa raison** (helpers PARTAGÉS, pas de la ré-absorption). |
| 43.2 | Filtre par confirmateur (Commandes, Suivi) | M | ✅ **Livrée le 2026-07-27** (`6373754`) — `<select>` du vivier `eligible_confirmers()`, **gérant uniquement** (un confirmateur ne doit pas pouvoir lister les commandes d'un pair). Filtre sur `_aod_assigned_confirmer` (l'assignation, état présent) et **jamais** `_aod_confirmed_by` (l'historique de qui a réellement confirmé). Confiance zéro sur `$_GET['confirmateur']` : un ID hors vivier est **ignoré** (c'est une lecture), là où une écriture le refuserait. ⚠️ **Périmètre réduit en cours de story, avec sa raison écrite** : Abandonnées reportée à la 43.3 — un prospect n'avait alors aucune assignation à filtrer. Cliquet `test-filtre-confirmateur.php`. |
| 43.3 | Bouton d'assignation sur « Commandes abandonnées » | M | ✅ **Livrée le 2026-07-28** (`c4615cb`) — lève le blocage dur documenté par la 43.2 par une **vraie migration de schéma** : un prospect n'est pas une commande (table à colonnes fixes, aucun mécanisme de méta) → colonne `assigned_confirmer`, `AOD_COD_Leads::DB_VERSION` 1 → 2, `dbDelta()` suffit. Mutualise `valid_confirmer()` et `confirmer_label_html()` vers `AOD_CD_Roles` (les jumeaux de `Section_Orders` deviennent des délégués). AJAX `aod_cd_lead_assign` sous des classes CSS et un point d'entrée **propres** — `section-orders.js` câble `.aod-cd-assignsel` par un `querySelectorAll` GLOBAL et tous les modules sont chargés sur toutes les pages : un prospect portant les classes « commande » serait parti au mauvais handler. **Changement de contrat de `maybe_assign_converter()`** : elle comble une absence au lieu d'écraser, sans quoi une pré-assignation du gérant serait effacée par le confirmateur qui décroche. 🚨 **La porte de recette a attrapé ce que 1255 tests unitaires ne pouvaient pas voir** : 127 px de débordement mobile — aucun cliquet PHP ne mesure une largeur rendue. |
| 43.4 | Traçabilité « assigné par » + libellé « Admin » | M | ✅ **Livrée le 2026-07-28** (`15a51c2`) — méta `_aod_assigned_by` (**absente = tourniquet automatique**, jamais `0`), fragment **littéral « Admin »** si l'assignant porte `manage_woocommerce` — jamais son `display_name` (A.20, Constat 3). 🚨 **Le plan annonçait 4 points d'écriture ; la mesure en a donné 6, plus une branche de refus à garder muette.** Le manquant qui portait le risque est la **suppression** : `unassign_confirmer()` (42.7) délègue à `apply_assignment( $order, 0 )`, donc sans effacement jumelé une commande libérée puis reprise par le tourniquet aurait affiché « Admin » sur une assignation que personne n'a choisie. Le second, le report depuis un prospect pré-assigné, n'existait pas quand le plan a été écrit et a coûté une migration (`assigned_by`, DB_VERSION 2 → 3), avec une garde **imbriquée** pour ne jamais poser une signature à `0` sur les prospects d'avant. Taille relevée S/M → M. 5 red-proofs. |
| — | *(hors phase)* Barre d'action groupée : affichée en permanence + débordement mobile | S | ✅ **Corrigé le 2026-07-28** (`1920025`) — trouvé en instruisant les 193 px signalés par la porte de la 43.4. Le JS pilote la barre par l'attribut (`bar.hidden = …`) mais `.aod-cd-bulkbar{display:flex}` battait la règle `[hidden]{display:none}` du navigateur : bandeau noir « 0 sélectionnée(s) » avec un bouton « Supprimer (0) » affiché **en permanence**, sur Commandes ET Abandonnées, à toutes les largeurs. 🚨 **La leçon était écrite mot pour mot dans le même fichier CSS** (~l.478), et même appliquée préventivement à un cinquième sélecteur — mais `.aod-cd-bulkbar` a été ajoutée mille lignes plus bas : *un commentaire ne protège que ses voisins*. Second défaut : sans `flex-wrap`, la barre affichée débordait de 193 px en 390 px (« Supprimer » hors de l'écran). 🚨 **`run.sh rtl` auditait DÉJÀ ce débordement sur les 12 sections et était verte** — parce qu'un clone de dev a zéro commande, donc ni tableau ni barre : la règle du témoin appliquée aux **données**. |
| 43.5 | Assignation groupée : écrasement explicite (D1 de A.20) | M | ✅ **Livrée le 2026-07-28** (`1194856`) — `bulk_assign()` **change de contrat** plutôt que de gagner un second mode silencieux : 4ᵉ compteur `reassigned`, **toujours vide** sans `overwrite` (la forme même de la non-régression 42.5 — ses 7 assertions passent sans modification). Case à cocher native, décochée à chaque rendu et **réarmée dès que la sélection se vide** ; confirmation seulement s'il y a réellement des commandes à reprendre. `overwrite` lu en **liste blanche** (`'1' ===`) : `! empty( '0' )` est vrai, une garde qui ne regarde pas la valeur ne la refuse pas (leçon 32.3). **Trois pièges levés** : le JS retirait les lignes de la table même hors de la vue « Non assignées » où elles ont leur place ; réassigner X à X réécrivait `_aod_assigned_by` et faisait passer le tourniquet pour le gérant (désormais sautée dans les deux modes) ; 🔴 `i18nBulkAssignSkipped` était **consommée sans avoir jamais été déclarée** depuis la 42.5 — message affiché en français à un gérant arabophone, absent du `.pot`, **règle 4 de `CLAUDE.md` enfreinte** parce que le code *paraissait* internationalisé. Taille relevée S → M. |
| 43.6 | Montant total sur Abandonnées (frais de livraison inclus) | M | ✅ **Livrée le 2026-07-28** (`b4799ee`) — 🚨 **la prémisse du plan était fausse.** A.20 Constat 4 annonçait qu'`estimate_lines()` « sait déjà chiffrer un produit + une quantité + **une wilaya + un type de livraison** […] seulement UN APPEL » : mesuré, elle ne prend ni wilaya ni type de livraison (il en faut deux, plus la somme), et surtout elle **exige un choix de variante** que `collectLead()` n'envoie jamais — sur un catalogue à variantes, la colonne aurait donc été un tiret sur presque toutes les lignes. **Arbitrage owner** : montant **approché « ≈ »** plutôt qu'une colonne vide → mode tolérant **opt-in** sur `estimate_lines()`, le chemin d'achat et le panier public restant stricts et exacts. **Trois replis en tiret**, dont un absent du plan et le plus vicieux : `price_for()` normalise EN SILENCE tout type de livraison inconnu en « home », donc un prospect capté tôt aurait affiché un tarif Domicile inventé. Cliquet dédié `test-montant-abandon.php`. |
| 43.7 | Sidebar : scrollbar verticale sur débordement | S | ✅ **Livrée le 2026-07-28** (`2545d33`) — `.aod-cd-side` était en `height:100vh` **sans** `overflow-y` : tout contenu plus haut que l'écran débordait en silence, sans moyen de l'atteindre (Constat 5). `overflow-y:auto` + barre fine teintée (`scrollbar-width`/`scrollbar-color` + repli `::-webkit-scrollbar`) — celle du système tranche sur un fond dérivé de l'accent. Prise avec la 43.8 (même sélecteur, correctif de trois lignes). |
| 43.8 | Sidebar : repliée par défaut, dépliée au survol/focus (D2 de A.20) | M | ✅ **Livrée le 2026-07-28** (`2545d33`) — rail de 64 px sur desktop, déplié au survol **et** au focus par **une seule déclaration** (D2 : un état visuel, deux déclencheurs). **La barre sort du flux** (`position:fixed` + `margin-inline-start` sur le contenu) : sinon la page glisserait horizontalement au moindre survol — et, moins évident, **les portes qui mesurent des positions deviendraient non reproductibles**. 🚨 **La décision de la story est invisible à l'écran** : les libellés s'effacent en `opacity`, **jamais** `display:none`/`visibility:hidden` — ces deux-là sortiraient les liens de l'ordre de tabulation, `:focus-within` ne se déclencherait plus jamais, et l'utilisateur clavier naviguerait dans une barre restée repliée. Mouvement réduit : bascule instantanée, **dépliage conservé**. Mobile et hamburger `.is-open` intacts. Cliquet `test-sidebar-rail.php` — 🚨 **il s'est fait piéger par son propre commentaire** (qui contient les mots `display:none` pour expliquer l'interdit) avant d'être corrigé pour lire le code et non la prose. |
| 43.9 | Clôture : i18n, cliquets, portes, docs | S | ✅ **Livrée le 2026-07-28** — `--strict` vert sur les deux plugins ; **1365 tests** ; 6 portes rejouées (`clavier`, `souris`, `noms`, `rtl`, `contraste`, `mouvement`). Remise en accord de cette table avec git : les statuts 43.1 → 43.8 étaient **différés à dessein** story après story (« déférée à 43.9 »), et l'étape **42.7 n'existait qu'en git**. Corrigés aussi : l'en-tête de Phase 43 qui promettait « `aod-cod-form` non touché » (faux depuis la 43.3), et la citation « D3 de A.20 » (A.20 n'a que D1 et D2 ; la règle du libellé littéral est au **Constat 3**). MAJ `docs/ARCHITECTURE.md` et `TEMPLATE.md`. Deux dettes neuves versées en §C.5 (D29, D30). |
| 44.1 | Bornes serveur de la soumission COD (quantité, articles, stock Woo) | M | ✅ **Livrée le 2026-07-29** (`aod-cod-form` v1.51.0 → **v1.52.0**) — issue de l'audit A→Z, pas d'une demande de fonctionnalité. Trois bornes posées dans le funnel UNIQUE `build_order_lines()` : plafond `AOD_COD_MAX_QTY_PER_LINE` (50, surchargeable comme ses deux jumelles), écrêtage à `AOD_COD_Cart::MAX_ITEMS` (**la constante existante**, pas une seconde), et garde de stock Woo alignée sur `WC_Cart::check_cart_item_stock`. 🚨 **Le plafond d'articles a été DÉPLACÉ de `handle_submit()` vers le funnel en cours de story** : `handle_submit()` termine le processus (`wp_send_json_*`) et reste inéprouvable — l'y laisser, c'était écrire une borne invérifiable le jour même. `AOD_COD_Cart::ajax_items()` corrigée dans le même geste, sinon la ligne en rupture s'évaporait du panneau au lieu d'être annoncée (helper partagé `orderable_stock()`). Garde **strict-only** : la colonne « Montant » de la 43.6 est intacte. Cliquet `test-bornes-cod.php` (25 assertions), **3 red-proofs** — et le red-proof a trouvé une faiblesse du TEST lui-même : la contre-épreuve de R4 restait verte garde désarmée, car `qty=9999` déclenchait le plafond et non le stock ; elle passait pour la bonne raison affichée et la mauvaise raison réelle. ✅ **Éprouvée en HTTP réel** sur le clone : `qty=100000` → refusée, stock intact à 3 ; `qty=10` sur stock 3 → refusée, stock intact ; `qty=2` → commande créée, stock 3 → 1. |
| 44.2 | `setup.sh` : l'activation d'Astra cesse d'écraser le thème du clone | S | ✅ **Livrée le 2026-07-29** (aucun plugin touché) — `wp theme activate astra` était inconditionnel dans un script documenté *idempotent*, donc explicitement fait pour être relancé. Trois cas désormais, et **le troisième PARLE** (un silence ferait « réparer » en forçant l'activation, ce qui rouvrirait le défaut). `get_template()` et non `get_stylesheet()` : un child d'Astra compte comme Astra. ⚠️ **Aucun cliquet PHP ne peut voir ce défaut** (script shell, hors du processus de test) — vérification manuelle consignée, avec un thème tiers jetable : Astra actif → inchangé ; `twentytwentyfive` → Astra activé ; **thème tiers → intact** ; child d'Astra → intact. Contraste relevé au passage : l'ancienne ligne, elle, faisait bien passer le thème tiers sous Astra. |
| 44.3 | Cliquet documentaire élargi (R8) + remise en accord de README et ARCHITECTURE | M | ✅ **Livrée le 2026-07-29** — corpus de `test-doc-derive.php` élargi à `README.md` et `docs/ARCHITECTURE.md` (témoins de R6 : 15 → **38** cliquets, 8 → **21** portes cités — preuve mesurée que le corpus a grandi). Nouvelle **R8** : toute version portée par un TITRE markdown ou la ligne `Versions analysées :` égale l'en-tête `Version:` du plugin. 🚨 **Périmètre issu d'une MESURE** : la règle naïve (« toute version sur une ligne citant un slug ») produirait des dizaines de faux positifs sur les mentions historiques et les « Versions cibles » — la restriction aux titres attrape exactement les 4 énoncés faux et zéro mention légitime. 🚨 **R8 a rougi sur SON PROPRE défaut à son premier run** : elle croisait chaque version avec chaque slug, donc la ligne `Versions analysées` — pourtant juste — produisait deux faux positifs ; une version appartient désormais au slug qui la précède. Red-proofée **dans les deux sens** (fausse version en titre → rouge nominatif ; en prose → verte). Les compteurs dupliqués (« 17 phases soldées », « 357 tests », « 15 portes ») sont **supprimés** au profit du renvoi, pas corrigés — ils re-vieilliraient (conclusion de D25, jamais appliquée hors `TEMPLATE.md`). |
| 45.1 | Le rail : l'icône centrée dans son cadre | S | ✅ **Livrée le 2026-07-29** (`e4f8c54`) — décalage **mesuré à +4 px** vers la fin de ligne (cadre de 40 px centré en 32, icône centrée en 36) : `.aod-cd-nav` retire 12 px de chaque côté du rail de 64, mais `.aod-cd-navlink` gardait le `padding-inline:14px` de la barre DÉPLIÉE. Correctif en `padding-inline` — le décalage MIROITE, un `padding-left` aurait corrigé le FR en aggravant l'AR. Après : **0 px sur les 12 liens, en FR (dir=ltr) ET en AR (dir=rtl)**. Ni `justify-content:center` (le libellé reste dans le flux en `opacity:0` — invariant R4 de `test-sidebar-rail.php` ET porteur du nom accessible du lien), ni animation de la bascule 10 ⇄ 14 px (une `transition` ne s'ajoute pas : il aurait fallu recopier la liste du sélecteur de base, copie qui divergerait). 🚨 Piège rencontré dans la SONDE : `login()` purge les cookies, donc `aod_lang=ar` posé AVANT lui mesurait deux fois le français — `dir=ltr` l'a dit. |
| 45.2 | Barre d'action groupée : hors du noir, et permanente | M | ✅ **Livrée le 2026-07-29** (`6c3a814`) — surface de la charte (`--cd-card`), rendue en permanence, actions `disabled` à sélection vide. La garantie de 2026 est CONSERVÉE en changeant de moyen : ce qui protégeait n'était pas le masquage mais l'impossibilité d'agir (D4). 🚨 **Et cette décision n'était gardée par RIEN** — le commentaire annonçait un cliquet `test-charte-surfaces.php` (« règle [hidden] neutralisé ») inexistant (0 occurrence de `bulkbar` dans `tests/`). D'où `test-bulkbar-permanente.php` (24 assertions, R0→R4), **éprouvé au rouge sur 4 injections distinctes**. Le bloc entier est repris, pas la couleur de fond : trois règles supposaient un fond sombre et donnaient blanc sur blanc. « Supprimer » d'Abandonnées, qui n'avait aucune peau propre, rejoint celui de Commandes. Au passage : `#b21f14` et deux ombres `rgba(217,45,32,…)` figées sur l'ANCIENNE valeur de `--cd-bad`, et l'anneau de focus de la case « écraser » qui quitte l'accent pour `--cd-focus`. Deux portes RETOURNÉES (`assignation`, `abandonnees`) : leur `:not([hidden])` serait devenu vrai par construction — le pire des verts. Mesuré : fond blanc, 0 px de débordement à 390 px, compteur 5,74:1, boutons 17,76:1 et 6,57:1. |
| 45.3 | Le détail d'une commande devient un bouton nommé | M | ✅ **Livrée le 2026-07-29** (`308c06d`) — le n° redevient du texte (`.aod-cd-onum`), « Détail » rejoint Appeler/WhatsApp sur **Commandes ET Suivi** (D2). 🚨 Le rang `.aod-cd-call-actions` devient **inconditionnel** : rendu `if ( $phone )`, il aurait rendu la modale inatteignable pour une commande sans téléphone (squelette d'abandon 39.4/39.5). **Zéro ligne de JS** : la classe `.aod-cd-order-detail` est conservée, seul l'élément qui la porte change. 🚨 Le cliquet d'icônes a attrapé `svg('receipt')` — un NOM DE FICHIER, pas une clé du catalogue fermé (la clé est `orders`) : sans lui, `_doing_it_wrong()` silencieux en production. Le CSS du numéro-bouton est SUPPRIMÉ (une peau qui habille un élément disparu est du CSS mort qui simule un composant vivant) et la règle de carte mobile retargetée sur `.aod-cd-onum`. Suivi était VIDE sur ce clone : fixtures `recette-seed-transverse.php` semées puis purgées pour prouver au lieu de supposer (leçon D29). |
| 45.4 | Clôture : i18n, cliquets, portes, docs | S | ✅ **Livrée le 2026-07-29** — `--strict` vert (**741** traduits, 3 chaînes de marque). **1402 tests** (1378 + 24). **8 portes rejouées** : `clavier`, `souris`, `mouvement`, `assignation`, `contraste` (les **6 accents**, accent client restauré après coup) ✅ ; `noms` et `rtl` ❌ **puis ✅ après correction**. 🚨 **Les deux rouges étaient de VRAIS défauts, et les deux disent la même chose sur la valeur d'une porte.** (a) `noms` : le `<select>` « Assigner à… » n'a **jamais** eu de nom accessible — la porte ne l'avait jamais vu parce que la barre était masquée au repos. Un défaut d'accessibilité ne naît pas d'un changement de peau, il devient VISIBLE (`aria-label` + AR écrit à la main). (b) `rtl` : la colonne d'actions du Suivi, en `white-space:nowrap`, faisait déborder la page de **35 px en FR et 41 px en AR** dès qu'elle a porté un troisième bouton — et rien ne l'absorbait, car en mode carte `.aod-cd-tablewrap` passe en `overflow:visible`. `abandonnees` : 66 ✓ / 1 ✗, rouge **PRÉ-EXISTANT mesuré** sur `97e8982` (65 ✓ / 1 ✗ déjà) → consigné en **D31**, pas avalé. MAJ `UX-GESTION-REFONTE.md` §2.4 et `TEMPLATE.md`. |
| 45.5 | Le rail replié tient dans l'écran | S | ✅ **Livrée le 2026-07-29** (`dac994b`, `1bcb188`) — retour owner : « une barre de défilement s'affiche à droite des icônes ». Mesuré AVANT : rail replié **1069 px en FR, 1348 px en AR** pour 12 icônes → débordait de 87 px (982 de haut) à 628 px (720×AR), donc sur TOUTE fenêtre d'ordinateur portable — et la barre qui en résultait **décentrait les icônes que la 45.1 venait de centrer**. Cause : `opacity:0` ne retire pas de hauteur ; les libellés jamais lus dictaient la colonne, et une boîte de texte arabe est plus haute (31 px contre 18). APRÈS : **0 px de débordement sur 8 couples** (4 hauteurs × 2 langues), **0 px de décentrage** sur les 12 icônes, hauteur naturelle **693 px identique en FR et en AR** (écart 279 → **0**). 🚨 **Le premier jet aurait été pire que le défaut** : densifier le rail replié sans voir qu'il se déplie AU SURVOL — les liens auraient filé sous le curseur. D'où le principe : rythme vertical IDENTIQUE dans les deux états (`--cd-navpad-y`), et replié on ne retire QUE l'invisible. 🚨 **`scrollbar-gutter:stable both-edges` essayé POUR préserver le centrage, puis rejeté sur mesure** : 20 px pris sur 64, icône décalée de **10 px** — deux fois et demie le mal corrigé (D7). 🚨 **Et le red-proof a trouvé un trou dans sa propre porte** : réinjecter cette gouttière n'a fait rougir personne — les barres de ce Chromium sont en SURIMPRESSION, l'assertion ne POUVAIT pas échouer (leçon `FS_METHOD`). Elle a migré vers **R7 de `test-sidebar-rail.php`**, qui garde aussi la règle anti-glissement que seule la source exprime. 6 injections, 6 rouges distincts. Glissement au dépliage borné et chiffré (D8) : 122 px FR / 101 px AR, 55 px sur les 4 entrées quotidiennes — il valait **261 px en AR** avant que l'interligne soit fixé. Nom accessible des 12 liens vérifié **dans l'arbre d'accessibilité de Chrome** (CDP), rail replié, FR et AR : 0 sans nom, 0 ignoré — `height:0` borne, il ne masque pas. **1409 tests** (1402 + 7), portes `clavier`/`noms`/`souris`/`mouvement`/`rtl`/`contraste` vertes. |
| 46.1 | L'anneau de focus ne dépend plus de l'accent | S | ✅ **Livrée le 2026-07-30** (`fe98276`, `e55f7f8`) — les 4 dérogations `outline:2px solid var(--cd-yellow|--cd-accent)` SUPPRIMÉES (pas réécrites : une dérogation qui redit la règle du socle est une occasion de divergence de plus). À spécificité ÉGALE (0,2,0) mais plus bas dans le fichier, elles gagnaient : avec l'accent PAR DÉFAUT du template, l'anneau tombait à **~1,15:1** quand SC 1.4.11 en demande 3:1 — le sélecteur de statut et le segmented control n'avaient donc PAS d'anneau visible. La règle était pourtant écrite EN ENTIER 1 100 lignes plus haut, et ré-appliquée à la main la veille (45.2). Cliquet `test-chrome-conformite.php` R0+R1 : R1 borne les propriétés qui DESSINENT l'indicateur, tolère `--cd-accent-on-dark` sur la sidebar seulement (readable_on l'y garantit ; un cliquet qui refuse le bon usage se fait désarmer), liste blanche VIDE. R0 ancre la couverture sur un INVARIANT (la dernière règle de la feuille, épinglée par le COUPLE sélecteur+déclaration — une sous-chaîne aurait été satisfaite par une occurrence 1 800 lignes trop tôt). Porte `contraste` étendue aux anneaux : **463 contrôles focalisés au clavier par accent**, 12/12 sections, 6 accents. 🔎 **Elle a payé à son premier run** : `.aod-cd-reset-input:focus` à **1,29:1** — l'anneau du champ de REMISE À ZÉRO DE LA BOUTIQUE, invisible au clavier, et invisible à R1 (couleur littérale, pas `var(--cd-accent*)`). 🚨 Deux pièges de sonde : `Tab` et non `el.focus()` (`:focus-visible` n'est pas garanti par un focus programmatique) ; et la première version mesurait la PREMIÈRE IMAGE d'une transition — Chrome sérialise alors `rgba(0,0,0,0) 0 0 0 0` ×2, qui composé sur son fond rend exactement le fond : 40 faux positifs « blanc sur blanc » sur des champs conformes. 4 injections, 4 rouges. |
| 46.2 | Les tableaux de données sont lisibles au pouce | M | ✅ **Livrée le 2026-07-30** (`d5a48e6`) — Stock : `--cards` manquait, et c'était LE seul mot (ses 7 `data-label` étaient déjà posés et traduits) ; mesuré, le tableau de 7 colonnes À ÉDITION INLINE défilait de **398 px** à 390. Accueil : 5 tableaux reçoivent `--cards` **et** leurs `data-label`, inexistants. Intitulés HISSÉS en variable (et capturés par `use` pour la fermeture partagée des 2 ventilations) : une seule source pour l'en-tête et le libellé de carte. 🚨 **Pourquoi aucune porte ne le voyait** : `.aod-cd-tablewrap` porte `overflow-x:auto`, donc le débordement reste INTÉRIEUR et la page ne déborde jamais — or `rtl-mobile.js` traque le débordement de PAGE. Elle mesure désormais CHAQUE tableau contre son conteneur : **24 tableaux × 3 passages** (FR desktop, FR 390, AR 390). Cliquet R2 bidirectionnel au grain du FICHIER (le grain fin ne se décide qu'au rendu, et c'est écrit), commentaires retirés par `token_get_all()` — mesuré nécessaire : mon propre commentaire faisait compter 6 occurrences pour 5 tableaux. Vérifié avec de VRAIES données (D29) : les tableaux Livraison/Confirmation ne se rendent pas sans colis ni confirmation tracée — semés, mesurés, purgés. ⚠️ `.aod-cd-carriers` exempté, nommé et chiffré → **D32** (lignes cliquables = changement d'interaction). 🔁 **Suite : D32 soldée le 2026-07-30, et elle a démenti ce report** — le débordement venait à 408/490 px du `min-width` de `.aod-cd-table`, et 138 des « 254 cellules » sont dans les `.form-table` imbriquées : l'interaction n'a finalement pas eu à changer. L'exemption a disparu de la porte. Trois pièges consignés : la colonne de cases N'A PAS de `data-label` et c'est légitime ; une dérogation d'échappement SÉCURITÉ évitée en échappant au point de sortie ; et le census a compté MA PROSE (citer le nom d'une annotation l'incrémente). |
| 46.3 | Le canal de retour de `/gestion` est annoncé | S | ✅ **Livrée le 2026-07-30** (`69f3acb`, `99f2c94`) — `toast()` porte le retour de TOUS les AJAX de `/gestion` et n'avait ni `role="status"` ni `aria-live` (SC 4.1.3). Le motif correct existait 7 fois ailleurs dans le même plugin : c'est le helper PARTAGÉ qui en était dépourvu, donc le seul dont le manque se propage. 🚨 **L'ORDRE est le sujet, pas l'attribut** : un lecteur d'écran n'annonce que les MUTATIONS d'une région DÉJÀ dans l'arbre, et `toast()` remplissait son div AVANT de l'insérer — ajouter l'attribut aurait été vert au grep et muet à l'oreille. Région UNIQUE et PERMANENTE posée au DOMContentLoaded. Trois conséquences traitées : `pointer-events:none` (une boîte `fixed` invisible en continu avalerait les clics), classe réécrite en entier (`bad` doit partir au succès suivant), texte vidé PUIS écrit (deux messages identiques ne muteraient pas). Cliquet R3 : ce qui est gardé n'est pas `role="status"` mais que `toast()` ne fabrique PLUS AUCUN nœud. Mesuré dans l'arbre d'accessibilité (CDP) : nœud `status` non ignoré, 1 SEUL nœud après 3 messages, `elementFromPoint` ne le désigne pas. Porte `noms` : 13/13 pages. 🔴 **Et R3 se faisait cautionner par sa propre documentation** — le commentaire de `toastRegion()` cite `role="status"` et `polite`, si bien que retirer les `setAttribute()` du CODE laissait tout VERT (corps 601→369 octets après nettoyage). Troisième objet du dépôt à tomber dans ce piège, et le seul démasqué par une mise au rouge. 3 injections, 3 rouges distincts. Plafond de la coque JS 636→696, justifié sur place. |
| 46.4 | Une seule famille de rayons + cliquet bidirectionnel | S/M | ✅ **Livrée le 2026-07-30** (`80d4650`) — `--cd-radius`/`--cd-radius-sm` valaient EXACTEMENT `--cd-r-md`/`--cd-r-sm` : deux noms VIVANTS pour une même valeur, 74 usages contre 16. Vérifié AVANT d'écrire : lus par `dashboard.css` seul, et `css_vars()` n'émet aucun rayon. Renommage **visuellement nul par construction**, comptes relevés avant/après (7+50=57, 9+24=33), 959 règles toujours parsées. `--cd-r-lg` et `--cd-yellow-d` SUPPRIMÉS — aucun candidat à 16 px dans la feuille, aucun lecteur pour l'alias : leur inventer un usage aurait fait passer un changement de design dans un renommage. 🚨 **R4 : le cliquet ne regardait que usage → déclaration depuis deux phases**, jamais l'inverse — alors que `test-icones.php` exige les DEUX sens et écrit pourquoi. Un token orphelin n'est pas un octet mort : c'est une échelle qui a l'air appliquée et ne l'est pas. 🔎 **Et le balayage a trouvé ce que l'inventaire manuel avait manqué** : 7 orphelins au lieu des 5 relevés en A.22, dont `--cd-accent-strong` qui l'est DEVENU dès l'alias retiré. D'où deux catégories dont AUCUNE n'est une liste en dur : les tokens que `css_vars()` INJECTE (interrogé, pas recopié — précédent de `test-charte-sidebar.php` appelant `side()`), et les 4 crans d'espacement objet de la 46.5. ÉGALITÉ de liste, pas seuil. 🔎 Consigné sans être corrigé : `--cd-accent-strong` est calculé à chaque page et consommé par ZÉRO règle. |
| 46.5 | Typo et espacement : les correspondances exactes passent aux crans | M | ✅ **Livrée le 2026-07-30** (`a1b7832`) — deux populations, une seule mécanique : **43** occurrences ÉGALES à un cran (11,5·13·14,5·17·20·26 px) et **110** sans cran. Seules les premières sont converties. 🔬 **PREUVE** : empreinte des styles CALCULÉS avant/après sur **12 sections × 7 003 éléments × 16 propriétés** — SHA256 **identique**, 0 différence. La conversion ne déplace pas un pixel, et ce n'est plus une affirmation. `--cd-sp-1` (10 sites), `--cd-sp-6` (6), `--cd-sp-10` (1) APPLIQUÉS. 🚨 **`--cd-sp-8` SUPPRIMÉ, ce qui CORRIGE la décision D2** : elle affirmait « `--cd-sp-6/8/10` correspondent à des littéraux présents », posé **sans compter** — vrai de 4/24/40 px, FAUX de 32 px, absent de toute propriété d'espacement. Le raisonnement tenait, sa prémisse était fausse pour un cran sur quatre ; D2 est corrigée avec la mesure. R4 tombe à ZÉRO orphelin — et c'est là qu'il devient dur. R5 (nouveau) : plafond DÉCROISSANT des littéraux (110 font-size, 143 padding), forme du cliquet de décomposition. On ne garde pas « l'échelle est appliquée » (invérifiable) mais « le compte ne remonte pas » — ce qui empêche la Phase 5 de se rejouer, elle qui avait DÉCLARÉ 6 crans puis laissé le nombre de tailles monter de 10 à 20. Comptes pris hors `:root` et hors commentaires (le piège, pour la 3ᵉ fois). |
| 46.5bis | Les 14 tailles sans cran : un arbitrage, pas un `sed` | M | ✅ **Livrée le 2026-07-30** — les 110 littéraux sont classés puis traités : **77 sites Δ±0,5 px** (11→11,5 · 12→11,5 · 12,5→13 · 13,5→13 · 14→14,5 · 15→14,5) et **5 arbitrages Δ1 px** (`card-val`/`form-title` 18→17, `brand`/`modal-title` 19→20, `onum` mobile 16→17). 🔬 Diff MESURÉ, pas supposé : sur 12 sections × 7 003 éléments, **3 193 éléments changent et UNIQUEMENT en `font-size`**, deltas **−0,5 (2 001) / +0,5 (1 155) / +1 (37)**, rien au-delà. Les `line-height` (718), `gap` (187) et marges (6) qui bougent sont tous **dérivés** — `1,2 × 12 = 14,4` devient `× 11,5 = 13,8`, les gaps de 7,25 px sont ceux des `<option>` natifs cachés (feuille du navigateur, en `em`), la marge est le `1em` par défaut d'un `<p>`. Résultat : les tailles de **texte** passent de **20 distinctes à 8**. 🚨 **29 littéraux restent, et c'est correct** — le tri NE SE MÉCANISE PAS. **24 sont des métriques de GLYPHE** : un caractère dessiné dans une boîte déterminée (la croix d'une modale 28px, un chevron qui pivote 22px, un glyphe centré dans 34 px, des initiales dans un rond de 42 px) — ranger ça sur des crans de texte casserait son rapport optique au cadre. **5 sont des micro-libellés** sous le plancher de l'échelle (étiquettes CAPITALES sur image : les porter à 11,5 = +15 % sur une pastille serrée). ⚠️ Le critère mécanique a été essayé et REJETÉ : « la règle fixe width/height ou line-height:1 » rendait des faux positifs (`.aod-cd-pill`, `.aod-cd-tracking-intro`, `.aod-cd-csel-btn` — du texte) ET des faux négatifs (les chevrons, sans boîte ni line-height) — plus un bug de regex de mon fait (`\bwidth` attrapait `max-width`). Il faut lire ce que l'élément CONTIENT. 🚨 **Et `.aod-cd-navgroup` est exclu pour une raison qui n'a rien de typographique** : la 45.5 a réglé la géométrie verticale du rail replié AU PIXEL (693 px identiques FR/AR), et le `font-size` de ces libellés entre dans ce calcul via `line-height:1.3` et le clipping `height:0`. Un « alignement d'échelle » ici aurait défait une phase entière, en silence, dans une langue qu'on ne lit pas forcément. Invariant re-mesuré après coup : **0 débordement, 0 décentrage, 12 liens, FR = AR**. R5 descend de 110 à **29 — son plancher irréductible** — et gagne un **recensement des tailles** : une valeur non recensée rougit MÊME si le total ne bouge pas (le contournement qu'un seuil seul autorise). 🔎 Ce recensement a rougi à son premier run, sur un bug de mon normaliseur : `rtrim('10','0')` rend `1`. Comparaison numérique désormais. 1443 tests ; portes `rtl`, `noms`, `souris`, `contraste` (6 accents) vertes — cette dernière compte, car un texte qui RÉTRÉCIT peut franchir le seuil « grand texte » et exiger 4,5:1 au lieu de 3:1. |
| 46.6 | Cibles tactiles mobiles + peau des jumeaux | M | ✅ **Livrée le 2026-07-30** (`79469fd`) — mesuré à 390 px AVANT : **tous** les contrôles sous 44 px, dont `.aod-cd-btn-sm` à **30-32 px sur 157 instances** (le rang Appeler/WhatsApp/Détail/Note, le geste le plus fréquent). APRÈS : **530 cibles mesurées sur les 2 passages mobiles, plus aucune sous 44**, 0 px de débordement en FR comme en AR. ⚠️ SC 2.5.8 (24×24) était DÉJÀ tenu : ce qui ne l'était pas, c'est la spec du projet (§5.3 et §2.3, « 3 hauteurs 32/40/44 » — aucune hauteur livrée n'y était). MOBILE UNIQUEMENT (D3) : la densité desktop est un ATOUT sur des listes de 30 lignes à la souris. `.aod-cd-page` prend `height` (sa hauteur est FIXÉE à 38, un `min-height` ne l'emporterait pas) ; `min-width` seulement sur le bouton sans libellé. Porte `rtl` : cibles jugées dans les 2 passages mobiles et PAS sur desktop — même code, deux règles, écrit comme tel. 🚨 **Le constat 8 est DÉMENTI par la mesure** : 23 des 25 règles nomment les deux jumeaux dans un corps UNIQUE — aucun corps dupliqué, la peau était déjà mutualisée. L'anneau fautif n'était donc pas écrit trois fois mais DEUX (une pour `statussel`, une pour la PAIRE) : la duplication réelle est entre `statussel` et la famille `assignsel`, deux composants DIFFÉRENTS. D4 close sans objet ; la moitié « jumeaux » disparaît parce qu'il n'y avait rien à corriger là. |
| 46.7 | Clôture : i18n, portes, docs | S | ✅ **Livrée le 2026-07-30** — `--strict` vert sur les DEUX plugins (dashboard **745** extraites / 741 traduites + 3 marque ; cod-form 416 + 3 marque), exit 0. **1442 tests** (1409 + 33). **8 portes rejouées** : `clavier`, `souris`, `noms`, `mouvement`, `contraste` (les 6 accents, accent client restauré), `rtl`, `assignation` ✅ ; `abandonnees` **66 ✓ / 1 ✗ — rouge PRÉ-EXISTANT inchangé**, mesuré identique avant la Phase 45 comme après la 46 : consigné en **D31**, pas avalé. Dette neuve **D32** versée en §C.5 (tableau des transporteurs, 490 px de débordement au pouce). MAJ `UX-GESTION-REFONTE.md` (§2.2 : les tokens supprimés, et surtout l'encadré qui dit pourquoi cette échelle n'avait jamais été appliquée), `ARCHITECTURE.md` §3 (`--cd-focus`, R4/R5, la région live), `TEMPLATE.md` §9. 🚨 **Ce que la clôture retient de la phase** : les 13 mises au rouge n'ont pas seulement prouvé que les cliquets rougissent — elles ont trouvé **pourquoi deux d'entre eux ne POUVAIENT pas rougir** (R3 lisait sa propre documentation ; la sonde d'anneaux mesurait la première image d'une transition). Un red-proof n'est pas une formalité de fin d'étape. |
| 47.1 | Le transporteur devient un choix par commande | M | ✅ **Livrée le 2026-08-01** (`15f4dec`) — méta `_aod_ship_carrier_choice`, **vide = repli sur le réglage global** : aucun clone existant ne change de comportement le jour de la mise à jour. Colonne « Transporteur » avant « Suivi », sélecteur de ligne + action groupée, **gérant ET confirmateur** (D2 de A.23) — divergence délibérée d'avec `$can_assign` (gérant strict), inscrite avec son *pourquoi* dans la table de référence de `test-permissions-sections.php`, faute de quoi elle se relirait comme un oubli. **Une seule résolution** : `AOD_Shipping::carrier_for()` porte les trois branches (méta → global → repli « un seul configuré ») et `auto_send()` n'en contient plus aucune — le repli a été DÉPLACÉ, pas recopié, et R4 du cliquet compte ses occurrences dans tout le plugin en exigeant exactement **1** (0 = régression pour les clones restés au défaut « EcoTrack », ≥2 = le dédoublement que D2 interdit). Un choix non configuré est **REFUSÉ, jamais remplacé** : le repli ne vaut que pour l'ABSENCE de choix — router un colis vers une société que personne n'a désignée engage un contrat commercial (même raisonnement que `build_order_lines()` sur un `product_id` inconnu, 44.1). 🚨 **Le red-proof a trouvé un défaut dans le cliquet lui-même, et c'est ce qu'il faut retenir de l'étape.** La mutation « la résolution revient dans `auto_send()` » a fait rougir 2 assertions sur 3 : *« `auto_send()` DEMANDE le transporteur »* est restée **VERTE** alors que l'appel avait disparu — elle se satisfaisait du **commentaire** juste au-dessus, qui cite `carrier_for()` pour expliquer la décision. Le cliquet prouvait la présence d'une PROSE, pas d'un appel. Corrigé puis **re-éprouvé** : la même mutation rougit désormais sur les trois. *La leçon était déjà écrite dans le dépôt* — `test-bulkbar-permanente.php` R3 la formule en toutes lettres après que `test-sidebar-rail.php` s'y soit fait prendre — mais n'avait jamais été propagée à ce patron de lecture de corps de méthode. **Sans red-proof, ce trou partait sous couvert d'un test vert.** ⚠️ **Deux bornes assumées, à ne pas lire comme des oublis** : le LOT refuse le retour au défaut (« Transporteur… » est une invite, et un lot vidé par mégarde annulerait en masse des décisions déjà prises, en silence) ; et la barre groupée reste invisible au confirmateur — non par garde manquante, mais parce que la colonne de cases appartient à `$can_delete` : sans sélection possible, un contrôle de lot serait un décor. Son geste **unitaire**, lui, fonctionne (vérifié porte `confirmateur`), et le handler de lot est gardé par la **cap**, pas par le rendu. 🔎 **Consigné sans être corrigé** : l'encart `render_metabox()` de **wp-admin** présélectionne toujours `reset( $configured )` au lieu de `carrier_for( $order )` — un gérant qui choisit X dans `/gestion` y verra le premier de la liste. Deux lignes, mais wp-admin n'est pas une surface de ce template et aucun AC ne le couvre : à trancher en 47.2, qui touche de toute façon aux chemins d'envoi. Trois cliquets ont exigé leur justification écrite (plafond `dashboard.php` 1055 → 1057 pour la clé i18n — la coque est propriétaire de l'objet i18n JS, précédent 43.5 ; census `phpcs:ignore` `EscapeOutput` 147 → 148, 4ᵉ occurrence du patron « markup déjà échappé par la méthode appelante »). **1496 tests** (+24), `--strict` vert sur les deux plugins (11 chaînes AR + 1), **30 contrôles au navigateur** (20 widgets sur 20 lignes, **0 px** de débordement à 1440, 390 et en RTL, en-tête AR `شركة التوصيل` vérifié à l'écran, 0 erreur console), portes `rtl` `assignation` `confirmateur` `suivi` `noms` `clavier` vertes. Clone remis dans son état exact après vérification, puis suite et porte `rtl` **rejouées après** la remise en état. |
| 47.2 | L'envoi quitte la confirmation et devient un geste nommé | L | ✅ **Livrée le 2026-08-01** (`9ef9197`) — hook `woocommerce_order_status_aod-confirmed` **retiré** ; `auto_send()` devient `AOD_Shipping::ship_order()` **publique**, rendant des `WP_Error` NOMMÉS au lieu de `return` muets (maintenant qu'un humain clique, chaque refus doit pouvoir se dire — et dans un lot, se dire ligne par ligne). Le camion devient un **bouton nommé** (leçon 45.3) : « Envoyer » / « Réessayer », et du TEXTE quand le colis existe — pas un bouton grisé, qui ferait chercher une permission absente là où c'est l'OBJET de l'action qui a disparu. 🚨 **Le lot est une boucle SÉQUENTIELLE côté navigateur, une commande par requête, et c'est un calcul — pas un goût.** `create_parcel()` plafonne à **10 s chez les SIX** transporteurs (vérifiés un par un : **aucun** ne surcharge `HTTP_TIMEOUT` — ⚠️ ne pas se laisser égarer par les `timeout=>8` de l'import de tarifs ni les `=>20` de la synchro de suivi), **20 s** à cache froid stop-desk : **3 commandes suffisent à toucher le mur des 30 s** d'un mutualisé, et à la coupure le lot aurait expédié une partie sans rendre compte de rien. Et **le mesurer ici mentirait** — `max_execution_time` vaut **0** sur cette pile (leçon `FS_METHOD`, TEMPLATE.md §3). Le plafond devient un plafond de **SÉLECTION** (`MAX_LOT_ENVOI = 50`, côté serveur, LU par le JS : écrit deux fois, l'un des deux mentirait), avec refus **dit** et jamais de troncature muette. Récapitulatif qui **nomme le transporteur de chaque commande** (D4 de A.23), jauge, compte rendu par commande, arrêt. Écran Livraison : la case « envoi automatique » pilotait un hook disparu → retirée ; `enabled` **préservé** en base plutôt que remis à 0 (le réécrire serait la migration silencieuse que la 47.1 s'est interdite). **Aucun second chemin d'envoi** : `test-verrou-envoi.php` reste vert **sans avoir été modifié**. 🚨 **LE RED-PROOF A TROUVÉ DEUX TROUS DANS LE CLIQUET LUI-MÊME, et c'est la deuxième étape consécutive.** (a) Hook rebranché par une **closure** liée à `$this` : R1 restait VERT — il ne regardait que les callbacks `array( $objet, 'methode' )`, or la closure est exactement la forme d'un rebranchement vite fait (corrigé par `getClosureThis()`). (b) Garde de concurrence désarmée en `if ( false && … )` : **rien n'a rougi** — l'assertion cherchait un motif de source, et le fragment était toujours là, précédé d'un `false &&` invisible au motif. *Une assertion satisfaite par un FRAGMENT ne prouve rien* — même famille que le trou de la 47.1 (satisfaite par un COMMENTAIRE). Remplacée par une preuve **fonctionnelle** : verrou posé à la main, refus, puis vérification qu'aucune méta ni note n'est écrite. 🔎 **Deux défauts trouvés AU NAVIGATEUR, qu'aucun test n'aurait vus** : un écouteur par bouton mourait avec la cellule re-rendue en `innerHTML` par `applyStatus()` — le bouton restait **cliquable et inerte**, le pire des états (passé en **délégation**, qui vaut pour tout re-rendu futur) ; et toute erreur répondait **400**, donc une erreur console par commande — à 50, la console devient illisible et les portes rougiraient sur un comportement **normal** (le statut suit désormais la nature du refus : 400 état · 409 concurrence · **200 + `success:false`** pour une panne transporteur — l'appel était valide, l'échec est MÉTIER : il s'affiche, il ne s'alarme pas). 🔎 **Et R5 de `test-charte-surfaces.php` a attrapé mieux que ce qu'il cherchait** : rouge sur un `padding:0 0 0 4px`, dont la correction a révélé que le raccourci codait le retrait **À GAUCHE** — la liste s'indentait du mauvais côté en arabe. Un cliquet d'ESPACEMENT a trouvé un défaut **RTL**. Passé en `padding-inline-start`. ⚠️ Les textes de recette qui « avaient peur » de ce statut (`a11y-souris.js`, `run.sh`, `README`, `recette-seed-souris.php`, `ARCHITECTURE.md` §1065) sont **annotés, pas falsifiés** : ils racontent un défaut réel à leur date. Seul `ARCHITECTURE.md` §318, qui décrivait le PRÉSENT (« → `auto_send()` → bascule Suivi automatique »), était devenu **faux** et est corrigé. 🔴 **`D27` n'a plus d'objet** — à retirer de §C.5 en 47.9, avec sa raison. **1517 tests**, `--strict` vert × 2 (18 chaînes AR), **26 contrôles au navigateur**, portes `rtl` `suivi` `souris` `assignation` `confirmateur` `noms` `clavier` **`public`** **`cod`** vertes — le parcours **acheteur** ne bouge pas d'une ligne. Clone remis dans son état exact, puis suite et portes **rejouées après**. |
| 47.3 | La note du confirmateur, et son voyage jusqu'au livreur | M | ✅ **Livrée le 2026-08-01** (`adb3308`) — la colonne « Note » disparaît de la ligne ; la note devient un `<textarea>` **nu** du volet d'édition, enregistré avec le reste par `aod_cd_order_save_info` sous `AOD_Carrier::META_DRIVER_NOTE` : **aucun handler AJAX neuf, aucune capability neuve** (`aod_cap_confirm_orders` couvrait déjà l'édition). `ajax_order_note()` — qui écrivait dans l'**historique WooCommerce**, que le livreur ne lit jamais — supprimé, avec `bindOrderNote`, `submitOrderNote`, `textPromptModal` (son unique appelant) et 3 clés i18n. **26 transporteurs sur 29** reçoivent la note **sans qu'un champ soit ajouté** : la case était déjà dans l'enveloppe et partait blanche. 🚨 **La constante vit dans `aod-cod-form`, pas dans le dashboard, et ce n'est pas un détail** : elle est SAISIE dans `/gestion` mais LUE par la charge utile transporteur, or la dépendance entre les deux plugins est à sens unique (règle 32.1, écrite en toutes lettres dans `class-aod-cod-icon.php`) — un `AOD_CD_*::META_NOTE` lu depuis `AOD_Carrier` serait la **première inversion** et casserait tout clone où seul `aod-cod-form` tourne. ⚠️ **Piège nommé, parce que le plan §B était ambigu** : il y avait DEUX écritures dans l'historique Woo, et **une seule devait partir**. Celle de `ajax_order_save_info()` (« Infos client / livraison… modifiées ») **reste** — c'est la traçabilité d'une ÉDITION, pas une consigne — ainsi que le bloc « Historique » de la modale. 🚨 **RED-PROOF 5/5 ROUGES DU PREMIER COUP — première fois en trois étapes, et ce n'est pas de la chance.** Les trous des 47.1 et 47.2 avaient la MÊME racine : une assertion satisfaite par une **prose** (un commentaire), puis par un **fragment** de source. Le cliquet de cette étape n'inspecte **aucune source** : R1 construit les **29 charges utiles** et y CHERCHE la note (présente ⇔ `supports_driver_note()`, **26 attendus**, ce qui *prouve* le chiffre du plan au lieu de le répéter), R4 lit `has_action()` — **closure-proof par construction**, contrairement à la réflexion que la 47.2 avait dû corriger —, R5 rend la cellule et lit son attribut sur 4 croisements + le cas « aucun transporteur ». *Une règle qui provoque l'état ne peut pas être satisfaite par la description de l'état.* 🔎 La mutation 2 a d'ailleurs attrapé **plus que sa cible** : déclarer le support chez **Yalidine** sans toucher son payload fait rougir `yalidine` **et `yalitec`**, qui en hérite — le mensonge D3 se propage par héritage, et c'est pourquoi R1 itère les 29 **instances** et non les 6 classes. 🔎 **UN DÉFAUT RÉEL TROUVÉ EN CONSTRUISANT, QU'AUCUN AC NE DÉCRIVAIT — et il aurait vidé l'étape de son sens.** `data-note-perdue` est calculé au rendu de la **ligne**, mais la note se saisit dans la **modale** et le transporteur se choisit dans la **ligne** : un gérant qui écrit une note puis clique « Envoyer » dans la foulée **n'aurait PAS été averti**, l'attribut restant à `0` jusqu'au prochain rechargement. Le défaut muet exact que l'étape ferme, **reconstitué par son propre correctif**. `ajax_order_save_info()` ET `ajax_order_carrier()` renvoient désormais la cellule « Suivi » fraîche, appliquée par `majCelluleSuivi()` (le helper que la 47.2 avait déjà écrit pour ça). Mesuré au navigateur : **0 → 1 à l'enregistrement, sans rechargement**, et retour à `0` à l'effacement. **Aucun test unitaire ne pouvait le voir** : les deux bouts sont corrects isolément, c'est leur CHAÎNAGE dans le navigateur qui manquait. 🚨 **Et `test-permissions-sections.php` a DEUX angles morts, trouvés en le mutant.** *Corrigé* : sa table de référence pouvait **roter en silence** — il criait « HANDLER INCONNU » quand le code portait un handler hors table, mais **rien ne regardait le sens inverse** ; vérifié, la suite est restée VERTE avec l'entrée périmée `order_note` décrivant un droit disparu. Règle d'orphelines ajoutée (5 lignes). *Consigné, non corrigé* : le parseur **saute les callbacks non-tableaux**, donc un handler enregistré par une **closure** lui est invisible — **nonce et capability compris**. Mesuré : la mutation 4 n'a fait rougir que `test-note-livreur.php` R4. Aucun handler du dépôt n'est une closure aujourd'hui, rien ne l'empêche → **à trancher en 47.9**. ⚠️ **`NOTE_MAX = 255` est en CARACTÈRES, et c'est une hypothèse assumée, pas une mesure** — aucune API transporteur ne documente sa limite ; 255 vient du seul plafond tiers déjà présent (E-com Delivery clippe `article`). Si une limite réelle est en **octets**, 255 caractères arabes font ~470 et seront refusés ; mais clipper en octets ferait **mentir le compteur du champ en arabe**, défaut silencieux dans une langue que le template supporte. Un seul nombre, trois lecteurs (`maxlength`, clip à l'enregistrement, `driver_note()`). ✅ **D-8 confirmée par la MESURE et non par la doctrine** : l'entrée `'note'` du catalogue d'icônes est réellement utilisée ailleurs (`class-aod-cd-section-stock.php:791`) — la retirer aurait cassé la section Stock. 📄 **Trois affirmations FAUSSES depuis la 47.2 corrigées, pas annotées** (elles décrivaient le PRÉSENT) : `ARCHITECTURE.md:236` et `:519` annonçaient encore que « Confirmée » **déclenche l'envoi auto** ; le compteur « **34 handlers** » (deux fois) en valait **36** — mesuré par `grep`, pas estimé — et en vaut **35** depuis cette étape ; plus un en-tête de section dans `class-aod-shipping.php`. **1532 tests**, `--strict` vert × 2 (3 chaînes AR), **18 contrôles au navigateur**, portes `rtl` `suivi` `confirmateur` `assignation` `noms` `clavier` **`public`** **`cod`** vertes — le parcours acheteur ne bouge pas d'une ligne. `test-verrou-envoi.php` vert **sans avoir été touché** : aucun second chemin d'envoi. Clone remis dans son état exact, puis suite et porte `rtl` **rejouées après**. 🚨 **Aucun envoi réel** : ce clone porte un compte **Yalidine véritablement configuré**, la fenêtre a été ouverte puis **annulée**. |
| 47.4 | La ligne Commandes et la modale qui ne se dérobe plus | S | ✅ **Livrée le 2026-08-01** (`6d841c8`) — la cellule « Client » passe à **deux rangs** (nom · téléphone · **Détail** ; Appeler · WhatsApp en dessous) et le **fond** de la fenêtre de détail ne la ferme plus. Étape de **chrome pur** : aucun handler AJAX, aucune capability, aucune requête, aucun schéma de données. 🚨 **QUATRE affirmations du plan démenties par la MESURE — corrigées avec leur chiffre, à l'endroit où elles étaient écrites, jamais réécrites.** **(1)** « trois hauteurs » (Constat 4) n'est vrai qu'au **bureau** : à 390 px la cellule faisait **210 px / cinq hauteurs** en FR et **160 / quatre** en AR, parce que la colonne de libellé du mode carte prend **40 %** (130 px sur 354) et qu'il ne restait **180 px** — où « Appeler » (91) + « WhatsApp » (103) ne tiennent pas. Pire : **nom (119) + téléphone (69) = 188 > 180**, donc *même ces deux-là seuls* ne tenaient pas — la demande n'était pas difficile, elle était **impossible** sans exempter la cellule de la colonne de libellé. Après : **98 / 137 / 137 px**, **0 px** de débordement. **(2)** « le retrait de `data-close` se fait à l'ouverture » (§B) est **INERTE** : mesuré au navigateur, l'attribut retiré **à chaud** puis le fond cliqué → la modale se ferme quand même, l'écouteur ayant été posé sur l'**élément**, par référence. Le garde est donc **au clic**, par **délégation** — ce qui règle du même coup la leçon 47.2 (un écouteur meurt avec son nœud). `data-close` **reste** dans le markup partagé, comme §B l'exigeait. **(3)** « la porte `clavier` verrait une modale sans issue » (Constat 5 **et** §B) est **faux** : `a11y-clavier.js` ne testait que `#aod-cd-team-modal`, et `grep` de `backdrop`/`modal-x`/`data-close` sur `tools/recette/*.js` rendait **zéro**. **Aucune porte ne couvrait la modale partagée** — une 47.4 qui aurait désarmé `Échap` serait partie **verte**. L'étape a écrit ce filet. **(4)** « `.aod-cd-cell-client`, propre à Commandes » (story D-7) : elle est **partagée** avec Commandes abandonnées (`class-aod-cd-section-abandoned.php:607`). D'où une classe **propre**, `--orderclient`, exactement comme la 43.3 avait tiré `--leadassign` hors de `.aod-cd-cell-assigned`. 🚨 **Le vrai chantier n'était pas le fond, c'était le PROPRIÉTAIRE.** `bindOrderDetail` et `bindTracking` posaient **chacun** leur `open`/`close` et leur `Échap` sur le **même** `#aod-cd-modal`, corps quasi identiques, et tous les modules tournent sur **toutes** les pages : deux fermetures, deux `Échap`, une seule modale. Écrire la politique du fond dans les deux, c'était la dérive que la Phase 46 a passé huit étapes à corriger. La coque gagne **`core.sharedModal()`** (politique du fond **en paramètre**) ; les deux modules **maigrissent** (1281 → 1272, 147 → 140). Plafond de `dashboard.js` **696 → 752**, justifié comme `toastRegion()` en 46.3. ⚠️ **La somme des trois fichiers MONTE (2105 → 2161) et ce n'est pas un raté** : ~44 lignes de code déplacé contre ~16 supprimées, le reste est de la **documentation** que les deux copies n'avaient pas. Même arbitrage qu'en 46.3 — tailler le commentaire pour rentrer sous un compteur paierait la lisibilité du garde-fou au prix de la connaissance qu'il protège. 🔎 **Piège nommé, et il ne se voit qu'au moment d'expédier** : `section-orders.js:76` lit `.aod-cd-client b` pour **nommer** chaque commande dans la fenêtre d'envoi de la 47.2. Sortir le nom du `<b>` aurait vidé ce récapitulatif **en silence**. C'est la mutation 4 du red-proof, et R5 le tient par **rendu réel**. 🚨 **RED-PROOF 5/5 rouges, chacune sur sa règle** (M5 en fait rougir **deux** : « Détail » remis dans le rang de contact rouvre aussi la garantie **45.3** — une commande sans téléphone perd sa fenêtre de détail). **Trois portes voient enfin ce qu'elles laissaient passer** : `clavier` (bloc 8 — fond désarmé, `Échap`, croix, focus piégé **et rendu**, et le garde qui **survit à un re-rendu du corps**), `suivi` (les **deux** politiques éprouvées **sur la même page**, seule où les deux consommateurs coexistent — la page étant **vide** au repos depuis la 47.2, deux témoins chiffrés la font rougir plutôt que passer), `rtl` (la cellule mesurée aux **3 cadrages**, **60 cellules**, témoin de couverture). **1566 tests**, `--strict` vert × 2, portes `rtl` `clavier` `noms` `souris` `assignation` `abandonnees` `suivi` `confirmateur` **`public`** **`cod`** vertes — le parcours acheteur ne bouge pas d'une ligne. 🚨 **Aucun envoi réel** (compte Yalidine véritablement configuré sur ce clone). |
| 47.5 | Les stats du confirmateur cessent de montrer la boutique | S | ✅ **Livrée le 2026-08-01** (`9c100cf`, `4acde2b`) — un confirmateur lit désormais **ses** confirmations et **son** délai moyen, plus ceux de toute la boutique. Étape **PHP pur, côté rendu** : aucun handler AJAX, aucune capability, aucune requête, aucun schéma de données, aucune ligne de JS ni de CSS ; `confirmation_data()` **intouchée** (D5). Le défaut était **atteignable** et pas théorique : `visible_tabs()` ouvre l'onglet à `aod_cap_stats_confirm` tandis que `read_filters()` force le filtre confirmateur à **0** pour tout non-gérant — la branche « globale » était donc la **seule** qu'il pouvait atteindre, alors que le bon geste était écrit **trois lignes plus bas** (le tableau « Par confirmateur » restreignait déjà à sa ligne) et n'avait jamais été propagé. Motif exact de la Phase 46. Correction : `$row_uid` calculé **une** fois, gouvernant les cartes, la période précédente **et** le tableau — qui ne peuvent plus diverger ; helper `conf_row()` pour que le motif de lecture de ligne, écrit **trois** fois, n'atteigne pas quatre. Critère = **`! manage_woocommerce`**, **pas** une capability : ne pas recopier la garde d'`assignment_scope()` (`aod_cap_confirm_orders`), qui protège des tuiles se vidant à tort — ici c'est l'inverse, un confirmateur qui n'a jamais confirmé doit lire **0**, ce qui est littéralement sa performance. 🚨 **La question laissée ouverte par §B est tranchée PAR LA MESURE : « Commandes reçues » reste GLOBALE.** 33/33 commandes portent `_aod_assigned_confirmer`, **1 seule** porte `_aod_confirmed_by` — mais ce n'est pas le volume qui décide, c'est le **temps** : `_aod_confirmed_by` est **figé** (contrat 42.3bis, éprouvé), `_aod_assigned_confirmer` est un **état courant**. Un « reçues par moi » serait un **numérateur figé sur un dénominateur mobile** : une commande confirmée par A puis réassignée à B donne à A *confirmées 1 / reçues 0*, soit une **division par zéro**, et à B *0 / 1* pour une commande qu'il n'a jamais vue. → L'écran le **DIT** : note conditionnelle non-gérant (`.aod-cd-note-scope-conf`), sans quoi « Confirmées : 3 (9 %) » se lit « 91 % de mes commandes m'ont échappé ». Chaîne neuve, **traduite en AR à la main**, `--strict` vert × 2. 🚨 **CINQUIÈME affirmation de la Phase 47 démentie — et la première par EXCÈS DE PRUDENCE.** Le cliquet réclamé « sur cette asymétrie » **existait déjà** depuis la 42.3bis (`test-assignation-accueil.php:196-210`) ; il a été éprouvé au rouge ici (M3) et **n'a pas été réécrit**. Ce qui manquait était ailleurs : **aucun cliquet ne regardait `tab_confirmation()`**, donc le rendu, donc l'endroit exact du défaut. D'où `tests/unit/test-stats-confirmateur.php` (R0→R4) qui **REND** l'onglet sous trois identités (gérant · A qui a confirmé · B qui n'a rien confirmé) — un cliquet qui aurait interrogé la **donnée** serait resté **vert sur le défaut**, la donnée étant correcte. Décor mesuré : reçues **8** · boutique **6** · ligne de A **3** · non attribuées **1** · B **0**, tous **distincts et non nuls** — sur des nombres égaux, la règle serait vraie par accident. 🚨 **RED-PROOF 4/4, chacune sur sa règle** (M1 en fait rougir **dix** assertions). 🔎 **Deux défauts du CLIQUET trouvés en éprouvant les mutations plutôt qu'en les supposant, et ce sont eux la leçon** : **(a)** l'assertion « le délai de A vaut 24,0 h » était **verte AVANT le correctif** — la chaîne existait dans la colonne « Délai moyen » du tableau, qui restreignait déjà correctement ; chercher un texte dans TOUT le document, c'est accepter qu'un autre composant réponde à sa place. Les règles de carte lisent désormais la **zone des cartes** seule. **(b)** R1 restait **verte sur M3** parce que la clé de `cached()` porte plage + produit + périmètre produits — **jamais l'identité ni l'assignation** : le gérant ayant déjà lu cette plage, l'appel sous confA rendait SON transient, donc aucune requête ne partait. R1 lit maintenant une plage élargie d'un jour (mêmes témoins, clé neuve). *Une règle qui lit un cache n'éprouve pas le code qui l'a rempli.* Et un troisième, sur le témoin lui-même : les confirmateurs de test ne portaient que `aod_cap_stats_confirm`, or c'est `aod_cap_confirm_orders` qui **arme** `assignment_scope()` — la mutation interdite par D5 aurait été **inerte** sur eux. 🚨 **La porte `confirmateur` aurait été CREUSE sans semis** : au repos B lit **0** et la boutique **1**, un « B voit 0 » passant aussi bien sur un rendu cassé que correct. Le seed lui attribue **3** confirmations et **5** à quelqu'un d'autre, marquées et purgées (double contrôle de la marque avant suppression, comme la purge des comptes) ; la porte lit l'écart **dans les deux sens** en déduisant le total de la boutique de la colonne « Part ». Mesuré en compte B : **reçues 41 · confirmées boutique 9 · confirmées B 3**. **36 → 45 contrôles**, les 36 existants inchangés. 🔴 **Aucun envoi transporteur** : que des métas, sur des commandes **sans article** (hook d'envoi auto débranché depuis la 47.2). **1611 tests** (1566 avant). Clone remis à l'identique (33 commandes, 0 fixture) et suite + porte rejouées **après**. |
| 47.6 | Produits : la description respire, les images sont bornées | S/M | ✅ **Livrée le 2026-08-01** (`74ac990`, `4533425`, `2623344`) — l'éditeur de description suit son contenu, et les images ont enfin une borne de poids. 🚨 **QUATRE affirmations du plan démenties par la MESURE — corrigées avec leur chiffre, à l'endroit où elles étaient écrites.** **(1)** Le plugin TinyMCE s'appelle **`wpautoresize`**, pas `autoresize` : `wp-includes/js/tinymce/plugins/` ne contient que le fork WordPress, et déclarer le nom officiel ferait chercher un fichier **inexistant**. Il porte de plus un **interrupteur**, `wp_autoresize_on`, sans lequel tout est conditionné à faux (`plugin.js:160,171`) — le plugin se charge et **ne fait rien**. Un cliquet statique cherchant « wpautoresize » dans le fichier serait donc resté **vert sur un éditeur inerte** : d'où une **PORTE** qui PROVOQUE la hauteur par `setContent` au lieu de la décrire. Avant : **132 px d'iframe** pour 216 comme pour 2 215 caractères. Après : **132 → 416 → 500 px**, FR **et** AR, plafond **atteint** (sans quoi « ≤ plafond » serait vrai d'un éditeur figé — mesuré, cette assertion passait AVANT le correctif). **(2)** 🚨 **Le Constat 8 sous-estimait son sujet, et la borne SEULE aurait été un défaut.** Sur les **six** sites d'appel de `AOD_CD_Upload::handle()`, **deux** — galerie (`:1580`) et photos d'options (`:1428`) — testaient `! is_wp_error()` **sans `else`** : une image refusée disparaissait **sans un mot**. Poser une borne au-dessus transformait « format refusé, en silence » en « photo trop lourde, en silence », précisément là où l'on dépose plusieurs photos d'un coup et où l'on ne recompte pas. Les deux **comptent** désormais leurs refus, remontés dans le `warning` **déjà existant** (patron `$dropped_offers`) ; `build_warnings()` est extrait pour que deux avertissements se **JOIGNENT** au lieu de s'écraser — la jonction est le point exact où une troisième cause se perdrait. **(3)** **« 5 Mo » n'est pas la limite : `min( 5 Mo, wp_max_upload_size() )` l'est.** Mesuré **au 2026-08-01, AVANT la 47.6bis** : `upload_max_filesize = 2M` sur cette pile — ⚠️ **ce chiffre est périmé, il vaut 8M depuis** (`docker/php-aod.ini`), et la limite effective est donc **5 Mo** ; le raisonnement, lui, reste vrai. PHP coupe **avant** WordPress — `error = UPLOAD_ERR_INI_SIZE`, `tmp_name` vide, **`size` = 0**. Un contrôle sur la seule taille serait **vert au test et INERTE en production** sur tout mutualisé sous 5 Mo : le goulot traite les **deux** cas. Et l'écran annonce la limite **effective** — promettre 5 Mo là où PHP en accepte 2 serait un mensonge d'écran. 🔎 **Effet de bord trouvé en chemin** : le champ vidéo d'une page de vente annonçait déjà `wp_max_upload_size()`, une note **juste** que la nouvelle borne rendait **fausse** au-dessus de 5 Mo — une seule source désormais, celle qui refuse. Sept zones de dépôt, **un seul corps** (`limit_note()`) : sept copies auraient donné sept chances de diverger. **(4)** ⚠️ **`class-aod-cd-dashboard.php` était EXACTEMENT à son plafond (1072/1072)**, et le second garde-fou interdit aussi de le descendre → **1077**, justifié comme la 47.4 (696 → 752). `maxUpload` relève du cas `maxLotEnvoi` de la 47.2 : une borne **lue à sa source** plutôt que recopiée en JS. Deux censures de `test-phpcs-ignore.php` ont rougi et sont justifiées **dans le fichier de test** (23.1) : `EscapeOutput` 148 → **154** (six `limit_note()`, patron des 141ᵉ-148ᵉ, seul contenu variable = un nombre formaté) et `NonceVerification` 17 → **18** (lecture de `$_FILES` dans le goulot — le nonce est vérifié plus tôt par les six appelants, et le seul effet possible est de **refuser**). 🚨 **RED-PROOF 7/7, chacune sur sa règle** (M1 en rougit quatre ; M7 rougit la porte en FR **et** en AR). 🔎 **Et une affirmation de mon PROPRE correctif a été démentie par le red-proof** : j'avais posé `autoresize_min_height: 132` en affirmant, mesure à l'appui, que sans lui l'éditeur s'ouvrait collé au plafond. Le retirer ne fait **RIEN** rougir — le produit témoin portait une description **arabe** de 2 124 caractères et `.aod-cd-lp-langs` est une grille `1fr 1fr` : *je mesurais le contenu de l'autre case.* Le défaut du plugin (`rows="6"`) donne exactement les 132 px d'avant ; la ligne est partie, et la porte garde le **résultat** plutôt que le réglage. Cliquet neuf : `tests/unit/test-upload-borne.php` (R0→R8) — il **APPELLE** `handle()`, il ne grep pas. ⚠️ Sa contre-épreuve a dû être **rétrécie honnêtement** : `_wp_handle_upload()` exige `is_uploaded_file()`, hors d'atteinte sous WP-CLI — et ce contrôle passe **avant** la vérification du type, si bien qu'une assertion « un .txt est refusé » était **verte pour la mauvaise raison**, elle aurait tenu la liste blanche entièrement supprimée. Elle porte désormais sur le **code d'erreur** (le nôtre ou celui de WordPress) et sur le **recensement** de la liste blanche. **1643 tests** (1611 avant), `--strict` × 2, portes `riche` `rtl` `noms` `clavier` `souris` `contraste` **`public`** **`cod`** vertes — le parcours acheteur ne bouge pas d'une ligne. 🔴 Aucun envoi transporteur. |
| 47.6bis | Les 5 Mo deviennent réellement atteignables | S | ✅ **Livrée le 2026-08-01** (`2164c58`) — **rouvre la 47.6 après sa clôture**, sur essai de l'owner : une photo de **2,8 Mo** refusée depuis `/gestion`. 🚨 **La 47.6 avait livré la moitié du travail** : elle a posé la borne applicative à 5 Mo, l'a annoncée honnêtement, puis a **constaté** que PHP plafonnait à 2 Mo et s'est contentée de le **dire**. *Annoncer honnêtement une limite qu'on aurait pu lever, c'est décrire le problème au lieu de le résoudre.* ⚠️ **Rectification factuelle** : `upload_max_filesize` **est** un réglage PHP — ce qui était faux, c'était d'en conclure que ce serait au client de le poser. **Mesuré : `Loaded Configuration File => (none)`** — AUCUN `php.ini` n'était chargé, les 2M/8M étaient les défauts **compilés** de PHP. Personne ne les avait choisis, et sur cette pile ils n'appartenaient qu'à nous. Livré : **`docker/php-aod.ini` versionné**, monté sur les **DEUX** conteneurs. 🚨 **Les deux, et ce n'est pas une précaution** : `tests/run.sh` s'exécute dans `wpcli`, les vrais envois arrivent dans `wordpress`. Ne le monter que sur le web laisserait le cliquet annoncer 2 Mo pour l'éternité ; ne le monter que sur la CLI le rendrait **vert sur un site qui refuse toujours**. C'est la leçon `FS_METHOD`/**D28** sous un autre visage. Du coup le cliquet cesse de **publier** la limite pour l'**exiger** — il imprimait « 2 Mo » pendant que l'écran promettait 5, et personne n'était réveillé. **Valeurs mesurées avant d'être écrites, et trois qu'il ne fallait PAS toucher** : `upload_max_filesize` **8M** (marge au-dessus de la borne, pour que ce soit NOTRE message qui refuse à 5 Mo et jamais un `size = 0` muet) · `post_max_size` **64M** — 🚨 la contrainte qu'on oublie : `wp_max_upload_size()` rend le **MIN** des deux, et la galerie poste plusieurs photos dans **un seul** POST · `memory_limit` **RIEN À FAIRE** (WordPress le monte déjà à 512M au boot côté web — sonde HTTP ; pic GD mesuré **162 / 179 / 209 Mo** pour 5,1 / 7,7 / 13,2 Mpx) · `max_file_uploads` **rien** (20 n'est jamais atteint : `post_max_size` borne à 8 fichiers) · `max_execution_time` **rien** (30 s côté web, redimensionnement le plus lourd **0,34 s**). **Production** : `setup.sh` écrit un **`.user.ini`** (PHP-FPM/CGI) — `wordpress/` n'étant pas versionné, il ne peut pas venir du dépôt. 🚨 **RIEN dans `.htaccess`, à dessein** : un `php_value` sans `mod_php` est une **erreur 500 sur tout le site**, et le bloc `# BEGIN WordPress` est régénéré — l'ajout y serait perdu en silence. Le repli mod_php passe par la **CHECKLIST**, qui exigeait 512 Mo de mémoire et **pas un mot** des envois. Sonde **6quinquies** dans `setup.sh` : **refuse de conclure** si PHP plafonne sous la promesse (patron 6quater — un setup « réussi » avec des envois morts se découvre chez le client). 🎯 **La preuve est enfin celle qui manquait** : la porte `riche` fait un **VRAI** envoi au navigateur — **2,97 Mo ACCEPTÉ**, attachement vérifié **EN BASE**, et **8,01 Mo REFUSÉ par NOTRE message**. Le cliquet unitaire l'**avouait** dans son en-tête : `_wp_handle_upload()` exige `is_uploaded_file()`, hors d'atteinte sous WP-CLI. Fixtures : de **vrais** JPEG générés par GD (des octets aléatoires seraient refusés sur le type, et la porte accuserait la borne), **taille lue sur disque** avant envoi. 🚨 **RED-PROOF 2/2** : `upload_max_filesize` remis à 2M → 2 règles du cliquet **et** 5 de la porte (dont l'envoi réel) ; borne applicative déplacée à 8 Mo → le cliquet **et** l'assertion qui exige que le refus vienne de NOUS. 🔎 **Deux défauts de la PORTE trouvés en l'écrivant** : un prédicat sur `request().postData()`, **toujours nul** sur un POST `multipart` (Playwright ne décode pas un corps binaire) — elle expirait sur un produit sain ; et une purge qui n'effaçait **qu'un** produit alors que `save_product()` enregistre AVANT de traiter l'image, donc la tentative refusée en crée un **second**. 🔎 **Et un piège d'exploitation, mesuré au red-proof** : modifier une valeur du `.ini` ne prend PAS avec `up -d` (config Compose inchangée = no-op, et PHP lit son `.ini` au démarrage) — il faut `docker compose restart`. **1645 tests** (1643 avant). Clone identique en sortie. |
| 47.7 | Les 13 dialogues du navigateur passent à la fenêtre maison | M | ✅ **Livrée le 2026-08-01** (`3d81d4d`) — **12 `window.confirm` + 1 `window.alert`, 7 fichiers, 0 restant.** Étape de **chrome pur** : aucun handler AJAX, aucune capability, aucune requête, aucun schéma. Ce qu'ils coûtaient : leurs boutons venaient du **NAVIGATEUR**, donc « OK / Cancel » en anglais sous une question en arabe — **le seul endroit de `/gestion` où le bilinguisme s'arrêtait** — et `window.confirm` **gèle** le fil d'exécution, requêtes en vol comprises. ⚠️ **DEUX NOMS DU PLAN ÉTAIENT FAUX** : `openModal()` n'existe pas (c'est **`openOverlayModal()`**) et `textPromptModal()` **n'existe plus** (supprimée en 47.3) — corrigés en §B là où ils étaient écrits. Livré : **une** primitive dans la coque, `confirmModal()`, bâtie **SUR** `openOverlayModal` (ni piège de focus ni `Échap` réécrits). `okOnly` absorbe l'unique `alert` — un **AVIS**, pas une question — plutôt qu'une seconde primitive pour une différence d'un bouton ; `detail` garde le mot de passe engendré **SÉLECTIONNABLE**, car `window.alert` le permettait et l'afficher dans un paragraphe ordinaire aurait été **une régression déguisée en amélioration de charte**. Titre **générique unique** : les 13 messages sont déjà des phrases complètes, leur donner chacun un titre afficherait deux fois le même texte pour 13 chaînes de plus. Les trois libellés (Confirmer / Confirmation / Annuler) **existaient déjà traduits** en arabe — vérifié à l'exécution. **Aucune règle CSS neuve** : `.aod-cd-modal-actions`, `-sm` et `.aod-cd-btn-danger` existaient. 🚨 **LE PIÈGE CENTRAL, nommé avant d'écrire une ligne et éprouvé au rouge** : `if ( ! confirmModal(…) ) { return; }` est **TOUJOURS faux** — une Promise est *truthy*. Un `sed` aurait produit **13 gardes mortes**, c'est-à-dire 13 suppressions sans confirmation, **sans qu'aucune erreur ne le dise**. Deux sites ne se transposent pas ligne à ligne : la question **conditionnelle** de `section-landing.js:300` et celle **imbriquée** de `section-orders.js:630`, suivie de ~45 lignes — toutes deux passent par une **promesse déjà résolue** quand il n'y a rien à demander, pour garder **UN SEUL** chemin de code. Le comportement 43.5 est intact : on ne demande confirmation d'un écrasement **que s'il y a réellement quelque chose à écraser**. Cliquet `test-dialogues-maison.php` : **R1** zéro natif · **R2** le **RECENSEMENT** — 🚨 *un zéro seul serait VERT sur treize confirmations supprimées*, c'est le mode d'échec que cette phase a déjà rencontré trois fois · **R3** une primitive, un propriétaire · **R4** les boutons en arabe. 🚨 **RED-PROOF 3/3, et la troisième est la démonstration de l'étape** : question retirée → **R2** ; natif réintroduit → **R1** ; **le faux ami `if ( ! confirmModal(…) )` → le CLIQUET RESTE VERT** (le code appelle bien la primitive 13 fois) et c'est **la PORTE qui l'attrape** — la requête de suppression part malgré « Annuler ». C'est précisément pourquoi les deux existent. Porte `clavier`, bloc 47.7 : la fenêtre s'ouvre, ses **deux** boutons viennent du site, le focus est piégé, **Annuler et Échap ne déclenchent AUCUNE requête** — éprouvé sur un geste **réversible**, jamais sur la remise à zéro. 🔎 **Deux assertions de cette porte étaient vertes PAR ACCIDENT** : le compteur attrapait aussi la modale **permanente** de la 47.4 (masquée mais présente), si bien que « la fenêtre s'ouvre » passait sans qu'aucune fenêtre ne s'ouvre. `:not(#aod-cd-modal)` cible l'overlay **jetable**. 🔎 **Et une trouvaille hors Constat** : `i18nCancel` était déclaré **DEUX FOIS** dans le même objet `window.AOD_CD`, la 47.3 l'ayant ajouté sans voir celui qui existait 65 lignes plus bas. Invisible (même valeur) — rangé. Plafonds relevés avec leur raison : `dashboard.js` **752 → 830**, `dashboard.php` **1077 → 1081**. **1656 tests** (1645 avant). ⚠️ La mutation M3 **émet une vraie requête de suppression** : le clone a été vérifié intact après (33 commandes, 0 corbeille), mais ce red-proof-là devrait viser une commande semée. |
| 47.8 | ABANDONNÉE — demande 6 de l'owner, retirée | — | ⛔ **Sans objet le 2026-08-01** — la demande est arrivée tronquée (« 6- Au niveau. ») ; interrogé, l'owner l'a retirée (« on l'oublie »). Le numéro **n'est pas réattribué** et 47.9 ne recule pas : renuméroter romprait le lien avec les en-têtes et les lignes déjà écrits, pour économiser un trou qui ne coûte rien. Un trou qui porte sa raison n'est pas une dette ; un trou muet, si (§C.2, précédent 17.4). |
| 47.9 | Clôture : i18n, cliquets, portes, docs | S | ✅ **Livrée le 2026-08-01** (`2c3791a`, `ed208ca`) — **la Phase 47 est SOLDÉE : 8 étapes livrées, 1 abandonnée sans objet (47.8).** 🚨 **La clôture a trouvé que le CLIQUET DOCUMENTAIRE était aveugle à la majorité de la ROADMAP.** Le motif de `test-doc-derive.php` R2 exigeait « `### N.M —` », or les phases **1 à 23 ET la Phase 47** écrivent « `### Étape N.M —` ». Élargi : **72 désaccords**, dont **quatre étapes de la 47 LIVRÉES avec un en-tête sans ✅** face à une ligne §C.3 à ✅ — *exactement* le désaccord que cette règle existe pour attraper, installé sous son nez pendant quatre étapes. Et **le témoin était complice** : « ≥ 10 en-têtes » passait en comptant ceux des phases antérieures — il attestait d'un balayage sans jamais vérifier CE QU'IL balayait. Il exige désormais de voir la phase **la plus récente de la table**. Les 68 désaccords historiques ne sont **pas** « corrigés » : convention d'époque, et leur ajouter ✅ serait un diff mécanique de 68 lignes de documentation pour zéro information — **plancher documenté**, patron R3 de `test-phpcs-ignore.php`, règle **PLEINE au-delà de la phase 23**. Les quatre de la 47 sont **corrigés, pas exemptés**. **⛔ `D27` close SANS OBJET**, barrée et non effacée : son chemin (`ajax_order_status` → `auto_send()`) n'existe plus depuis la 47.2. *Une dette qui disparaît sans trace laisse croire qu'elle n'a jamais existé, et la prochaine mesure de latence rouvre le même dossier.* Ce qui **reste vrai** est écrit : l'envoi demeure synchrone dans la requête de **celui qui clique** — la différence n'est pas technique, elle est de **consentement**. **Porte neuve `run.sh envoi`** (`envoi-geste.js`), celle que §B réclamait : sur une commande **jetable** confirmée depuis `/gestion`, **aucun colis** n'est créé — ni `_aod_ship_tracking`, ni `_aod_ship_error`, et le handler `aod_cd_ship_send` n'est **même pas appelé** ; le camion est là, **nommé** ; la fenêtre de LOT s'ouvre, nomme la commande, et on **ANNULE**. 🔴 **Deux défauts de la PORTE, trouvés en l'écrivant** : (a) elle cliquait le bouton **unitaire**, qui envoie **DIRECTEMENT** (la fenêtre appartient au lot, 47.2/D4) — une **vraie tentative d'envoi** est partie ; clone vérifié intact (33 commandes, **0** numéro de suivi), mais c'était un coup de chance ; (b) elle traçait les requêtes du **NAVIGATEUR** vers les domaines de livreurs, alors que l'appel part du **SERVEUR** : l'assertion aurait été vraie **pendant un envoi réel** — une affirmation invérifiable déguisée en preuve. La trace serveur (`_aod_ship_tracking` / `_aod_ship_error`), elle, conclut. ⚠️ **Ce que la porte DÉLÈGUE et le dit** : « un clic crée UN colis, une seule fois » reste prouvé par `test-verrou-envoi.php` (transporteur **bouchon injecté**) — trois façons de le refaire au navigateur ont été examinées et écartées **avec leur raison**, dont « ajouter un filtre pour le test », que `confirmateur.js` s'interdit déjà. **Les deux décisions en suspens, vérifiées avant d'être tranchées** : (1) la métabox **wp-admin** ignorait bien le transporteur choisi par commande — le `<select>` n'avait **aucun `selected`** et le champ stop-desk était bâti pour le **premier** configuré. Défaut de **PROPAGATION** laissé par la 47.1 : la résolution existait, elle n'était pas appelée d'ici. Corrigé par `carrier_for( $order )`, avec repli sur le premier si la résolution échoue. (2) L'« angle mort de fermeture » de `test-permissions-sections.php` **n'avait plus d'objet** : la limitation est documentée et son complément — la porte `confirmateur` — existe depuis la 22.2. Seule sa mention « **D13, ouvert** » était périmée ; corrigée. **1656 tests**, `--strict` × 2, **11 portes vertes** (dont la neuve), clone identique (33 commandes · 33 attachements · 2 produits). |
| 48.1 | « Commandes reçues » dit son périmètre | S | ✅ **Livrée le 2026-08-02** (`07340e9`) — libellé conditionné au lecteur (un SEUL site de décision, comme `$row_uid` gouverne déjà les trois cartes) + note de périmètre remontée sous les cartes : son texte n'a pas changé, sa **place** si — rendue en fin de page elle arrivait après la carte qui provoque le contresens, et hors du premier écran sur mobile. **Mesuré au rendu sous les deux identités** : gérant `>Commandes reçues<` ×1 / `(boutique)` ×0 / note absente ; confirmateur l'inverse exact — les deux libellés sont **exclusifs**. L'assertion D-3 de `test-stats-confirmateur.php` porte sur le **nombre** : restée verte **sans être touchée**, ce qui prouve que l'étape n'a pas débordé |
| 48.2 | La carte « Commandes assignées » | M | ✅ **Livrée le 2026-08-02** (`8d12c0c`) — 4ᵉ carte rendue au seul `$row_uid` non nul (**D-2** : elle suit le critère UNIQUE institué par la 47.5 ; un quatrième critère sur le même écran rouvrirait la divergence qu'elle a fermée — effet voulu et vérifié, un gérant qui filtre `?confirmateur=X` la voit aussi). **Compteur, jamais un ratio** (D-4). 🚨 **Le comptage est AVANT le `return` de la closure** : la branche `! $by` sort tôt, et compté après, une commande assignée mais pas encore confirmée — soit le cas le plus courant du chiffre affiché — n'aurait jamais été vue ; la carte censée corriger le défaut l'aurait réinstallé. 🚨 **Ventilation `uid => n` dans le payload EN CACHE**, comme `rows` : la clé n'intègre ni le rôle ni le lecteur, à dessein — y stocker un total « le mien » imposerait une clé par utilisateur, et le jour où on l'oublierait deux confirmateurs se serviraient la vue l'un de l'autre. Clé renommée **`confirmation2`** : la forme du payload change, une entrée encore en cache (TTL 5 min) aurait servi un **0 faux** pendant cinq minutes. `0`/méta absente = non assignée, jamais d'entrée `0`. ⚠️ **Défaut évité en chemin** : la tonalité `violet` n'existe pas — les tonalités sont un catalogue FERMÉ (`.t-*`), une tonalité inconnue ne lève **aucune erreur** et la carte serait sortie en neutre (le fantôme de la Phase 17). Corrigé en `indigo`. Mesuré : Bilal 12 · Amina 11 · Houssam 10 (somme = total, aucune fuite), gérant sans filtre **inchangé**, confirmateur sans assignation → **0** et non 33 |
| 48.3 | Clôture : i18n, cliquet, porte, docs | S | ✅ **Livrée le 2026-08-02** (`f6dbf4b`, `d762d47`) — **la Phase 48 est SOLDÉE : 3/3.** AR des 2 chaînes neuves depuis le glossaire du catalogue, `--strict` vert, rendu AR vérifié (4 cartes, zéro FR résiduel). **R5 de `test-stats-confirmateur.php`** (+17 assertions, 1686 → **1703**) : le décor **croise** assignation et confirmation — B assigné 4 fois sans rien confirmer, A confirmant 3 fois pour 1 assignation, C confirmant 2 fois sans assignation, et une commande confirmée par C **assignée à B** (la réassignation après confirmation, d'où vient la division par zéro que D-4 interdit) ; égaux, les deux nombres auraient rendu la règle incapable de dire ce que la carte lit. **6 mutations éprouvées au rouge**, chacune ne faisant rougir que sa cible. 🚨 **L'une n'a d'abord rien fait rougir — parce que l'injection n'avait pas eu lieu** (indentation), le piège nommé au point 5 de §C.2 : *on n'avait éprouvé que son propre `sed`*. Reprise avec vérification de l'injection. **Porte `run.sh confirmateur` : 44 → 50 assertions.** ⚠️ **Elle a rougi, et c'était juste** — elle comptait les cartes comme PROXY de « la carte gérant est absente » ; non desserrée mais **rebraquée** : elle nomme ce qu'elle cherche et mesure ce que la 48.2 a acheté (4 assignées ≠ 45 reçues). Deux défauts de la porte trouvés au passage : son auto-contrôle de semis a rougi (12 créées sur 8 — son travail), et son récapitulatif annonçait « 3 cartes » **en dur**, donc faux dès la première carte ajoutée — désormais dérivé de la mesure et porté hors du `try` (après une exception il dit « NON ATTEINT » au lieu de disparaître) |
| 48.4 | La carte en tête, et l'en-tête qui dit « vos » | S | ✅ **Livrée le 2026-08-02** (`d3025eb`) — **ajoutée à l'exécution, sur retour de l'owner : « rien n'est corrigé ».** Il avait raison : la carte de la 48.2 arrivait **en 4ᵉ position**, donc le premier nombre lu par un confirmateur restait celui de la boutique, sous un « Bonjour <prénom> » et un sous-titre « Vue d'ensemble de la **boutique** ». *Ajouter la bonne information ne corrige rien tant qu'elle arrive après celle qui trompe — l'ordre de lecture fait partie du message.* Carte en tête (`array_unshift`, lecteur restreint seul) + sous-titre « Vos commandes » au non-gérant. ⚠️ **Aucun calcul ne bouge** : D-4 est intact, la prudence sur l'arithmétique avait été appliquée à tort à la mise en page. Cliquet : la carte doit **précéder** « Commandes reçues » (sans quoi un futur `$cards[] = …` la renverrait en queue sans faire bouger une assertion) ; la porte lit les cartes **par libellé**, plus par index — 50 → **51** assertions. 🚨 **Incident de méthode consigné** : le `git checkout --` du red-proof a détruit ces deux modifications **non commitées** ; la leçon (« commiter AVANT d'éprouver au rouge ») était déjà écrite, et c'est le cliquet neuf qui a signalé la perte en rougissant |
| 48.5 | Une assignation invalide le cache | S | ✅ **Livrée le 2026-08-02** (`e61c399`, `453160b`) — **ajoutée à l'exécution, sur un second retour de l'owner : « je viens d'assigner 2 commandes, 12 + 2 = 14, et la carte affiche 12 ».** Le défaut n'était pas dans la carte de la 48.4, il était **sous** elle. **Mesuré avant de conclure** : 14 en base, **toutes dans la fenêtre de 30 jours** — la période n'était pas en cause ; reproduit en chauffant le cache puis en assignant (**base 15, écran 14**). Cause : `confirmation_data()` passe par `cached()` (TTL 5 min), invalidé par quatre hooks — création, statut, corbeille, suppression — dont **aucun ne couvre une écriture de MÉTA**, alors que le commentaire au-dessus d'eux promettait déjà « toute écriture de commande ». *Le code tenait quatre événements là où son commentaire annonçait une classe entière.* Profil du défaut : **intermittent, muet, auto-cicatrisant** en 5 min. Correctif : **un** hook, `woocommerce_update_order` — émis par `$order->save()` dans les **deux** magasins (HPOS et CPT hérité), signature vérifiée **dans la source de Woo**, pas supposée. ⚠️ **Posé là et non sur les cinq points d'écriture** de `_aod_assigned_confirmer` (manuel, lot, tourniquet, conversion de prospect, dé-assignation) : une règle répartie sur cinq sites ne survit pas au sixième — la dérive des Phases 44 et 46. Les quatre historiques restent (création et suppressions ne passent pas par `update`). ✅ Bénéfice non demandé : les **trois autres agrégats cachés** de l'Accueil se rafraîchissent aussi. Gardé par **R6** (+7 assertions, 1705 → **1712**), qui **chauffe** le cache, assigne par `apply_assignment()` puis relit — jamais un `has_action()`, qui décrirait le branchement au lieu de provoquer l'état. 🚨 **Deux enseignements du red-proof** : (1) le premier jet visait B, que `apply_assignment()` a **refusé à juste titre** (pas de `aod_cap_confirm_orders`, exprès) — sans le témoin d'écriture, les deux assertions suivantes auraient mesuré un **non-événement** ; (2) l'assertion jumelle « la dé-assignation redescend » était **verte au rouge**, le cache figé coïncidant avec l'attendu — doublée par l'**empreinte de cache**, qui ne peut pas coïncider. Vérifié sur le clone : 14 → assignation → **15 immédiatement** → retrait → 14 |
*Mettre à jour ce tableau à chaque étape livrée (✅ + date + commit).*

### 🔎 Revue de code de la Phase 47 — 2026-08-01 (`aa7a417`, `6d945eb`)

La phase soldée, la branche a été passée en **revue multi-agents** (4 finders, 27
candidats, 18 vérificateurs indépendants, **4 réfutés**, **10 retenus**). Ils sont
tous corrigés. Ce bloc existe parce que la leçon n'est pas dans les dix défauts,
elle est dans **ce qu'ils ont en commun** — et parce qu'aucune ligne d'étape ne
pouvait la porter : les défauts sont nés *entre* les étapes.

🚨 **Huit des dix sont une décision propagée à UN jumeau et pas à l'autre.** La case
« envoi automatique » retirée de `/gestion` mais laissée dans **wp-admin**, sous une
phrase promettant que « le bordereau part alors tout seul ». La cellule « Suivi »
renvoyée par les **deux** chemins unitaires et pas par le chemin **groupé**, si bien
que `data-note-perdue` restait périmé sur tout un lot et que la note du confirmateur
partait perdue **sans avertissement**. Les compteurs de refus d'image tissés dans la
galerie et les options (47.6) mais **pas** dans la photo principale, qui continuait
d'interrompre la soumission et de jeter en silence galerie et variantes — un produit
à moitié enregistré, titre et prix sauvés, le reste perdu. La résolution du
transporteur réduite à `carrier_for()` (47.1) **sauf** aux deux endroits du
formulaire public, qui pouvaient donc offrir le stop-desk pour un livreur qui n'en
fait pas. *C'est la famille de la Phase 44, à nouveau : le bon motif était déjà dans
le dépôt, il n'avait pas atteint son jumeau.*

🚨 **Le plus grave n'était pas une propagation manquée, mais une CONDITION PERDUE en
changeant de déclencheur.** Tant que l'envoi partait du hook
`woocommerce_order_status_aod-confirmed`, **le hook ÉTAIT la condition** : seule une
commande confirmée pouvait expédier, et ça n'avait pas à s'écrire. La 47.2 a déplacé
le déclencheur — et la condition est partie avec lui, sans que personne le remarque,
**pas même la porte neuve écrite pour cette étape**. Résultat : une commande COD
fraîche (`processing`) affichait un « Envoyer » actif, et une commande **annulée**
après un échec affichait « Réessayer » (`ship_status()` lisait `META_ERROR` **avant**
le statut). Un clic de trop créait un colis **réel, facturé, irréversible** — rien ne
revient en arrière une fois `_aod_ship_tracking` posé. 🔎 **Le red-proof l'a démontré
en direct** : garde retirée, `ship_order()` sur une commande non confirmée est allé
**jusqu'à l'appel réseau du transporteur** (`aod_carrier_http`). Clone vérifié
ensuite — **0 numéro de suivi**. *Une garantie portée par un mécanisme disparaît avec
lui : il faut la réécrire là où elle n'était pas écrite.*

Les deux autres viennent de la **47.7** et disent la même chose sous un autre angle :
`window.alert` **bloquait** le fil JS, la fenêtre maison non. Le `setTimeout(reload,
700)` laissé sur la même ligne d'exécution emportait la modale du **mot de passe
généré** — affiché une seule fois, jamais stocké en clair : un confirmateur qui ne
peut plus se connecter. Et `lancerLot()` reprenait `onCancel`/`onEscape` au squelette
sans en reprendre la responsabilité : ✕, fond et Échap restaient **inertes après la
fin du lot**, corps verrouillé en `overflow:hidden` — pendant qu'un lot arrêté à la
5ᵉ de 30 annonçait « 5 envoyée(s), 0 en échec », exact et parfaitement trompeur.
*Remplacer un dialogue bloquant par un dialogue qui ne l'est pas change le SENS de
tout ce qui le suit.*

🔴 **Et un correctif a fait rougir une porte — c'était juste.** `run.sh riche`
exigeait que la photo trop lourde fasse **échouer** la soumission : le contrat que le
correctif venait de supprimer. Elle n'a pas été « rendue verte » : elle envoie
désormais une **galerie valide dans la même soumission** et mesure en base — vignette
absente (le refus est réel), galerie enregistrée (le reste a survécu). Sans cette
galerie, l'assertion aurait été verte avec un code qui la jette encore.

Cliquet : `tests/unit/test-revue-47.php` (19 assertions, **9 mutations éprouvées au
rouge**). Aucune n'appelle un transporteur — ce clone a un compte Yalidine **réel** ;
l'ancrage inverse passe par le **verrou d'envoi**, seul moyen de prouver qu'on
franchit une garde sans exécuter ce qu'elle protège. **1686 tests**, `--strict` × 2,
portes vertes, clone identique (33 commandes · 33 attachements · 2 produits).

⚠️ **Les 4 réfutés méritent d'être connus** : la borne de 5 Mo appliquée aussi à la
vidéo est **voulue et annoncée** dans le même diff ; l'exemption `leadassign` de
`dashboard.css` existe pour une raison que le candidat avait inversée ; le bouton de
lot est protégé par la fenêtre elle-même ; et la garde de statut, réfutée une fois
comme « design délibéré de la 47.2 », était bien un défaut — **deux vérificateurs sur
le même point ont conclu l'inverse l'un de l'autre**. *Un verdict de réfutation n'est
pas plus final qu'un verdict de confirmation : c'est la mesure qui tranche.*

⚠️ **Ce repère annonçait un trou qui n'existait pas — corrigé le 2026-07-28.**
Il affirmait que « les étapes des Phases 33 à 42 ne sont pas listées ici (gap
pré-existant) ». **Mesuré en soldant D30 : elles y sont toutes** — 43 lignes de
33.1 à 42.7, dont 42 étaient déjà présentes sur `main` avant la Phase 43. La note
était donc fausse **à l'instant même où elle a été écrite**, et elle invitait à un
« backfill » sans objet.

🚨 **C'est le défaut que ce document traque, retourné contre lui** : un
avertissement en gras, dans la table qui « fait foi », décrivant un état que la
table dément trois lignes plus haut. Personne ne l'a vu parce que personne ne
recompte en lisant — et parce qu'aucune règle ne confrontait la table à quoi que
ce soit d'extérieur. C'est précisément ce que **R7** de `test-doc-derive.php`
(dette D30) fait désormais : toute étape présente dans un sujet de commit doit
avoir sa ligne ici. Les 81 étapes commitées de l'historique l'ont.

**Phases 1 à 6 : soldées (33/33 étapes).** La Phase 6 s'est close le 2026-07-14
avec la 6.5 (arbitrée « faite » par l'owner).

**Phase 7 (contenu produit bilingue FR/AR) : SOLDÉE le 2026-07-17 (7/7).**
Livrées le 2026-07-15 : **7.0** (extraction préalable du monolithe) et **7.1**
(armature `AOD_COD_Catalog_I18n`, dormante). Le 2026-07-16 : **7.2** (titre +
description — l'armature est **branchée**), **7.3** (arguments de vente), **7.4**
(variantes + 2 fuites `gettext` soldées) et **7.5** (catégories — term-meta
`_aod_cat_name_ar`, filtre unique `get_term`). Le 2026-07-17 : **7.6** (recette de
clôture rejouable — `bash tools/recette/run.sh bilingue`).

**Phase 8 (description produit riche — éditeur WYSIWYG) : SOLDÉE (4/4).**
**8.1 livrée** (v0.34.0, + correctifs de revue v0.34.1) : l'éditeur TinyMCE sur le
couple description — chargé « nu » par la coque, PAS par `wp_enqueue_editor()`
(inopérant : page autonome). **8.2 livrée le 2026-07-17** (v0.35.0) : bouton image
→ premier upload immédiat du plugin, markup minimal enrichi au rendu. **8.3 livrée
le 2026-07-18** (dashboard v0.36.0 · **cod-form v1.35.0** — premier bump cod-form de
la phase) : vidéo par **lien YouTube → façade** au rendu (0 iframe, 0 requête avant
le clic ; pattern 6.3 réutilisé) **et** par **fichier local → `<video>` natif**
(kses conserve `<video src>`, strippe `<source>` : d'où le `src` direct ; upload
immédiat jumeau de la 8.2). **8.4 livrée le 2026-07-18** (cod-form v1.35.1 ·
dashboard v0.36.1) : recette de clôture rejouable (`tools/recette/run.sh riche`,
incluse dans `public`) — description riche FR/AR/RTL à 390 px, façade 0-requête,
vidéo-fichier preload none, témoin plat inchangé, garde de commande ; **4 correctifs
de la revue `/code-review high` de la 8.3 repliés** (parité `ytId`/`youtube_id`,
regex `<p>` balancée, court-circuit resserré, gate aligné), 3 nettoyages différés en
dette. **La ROADMAP historique (Phases 1→8) est soldée.**

**Phase 9 (catégories sur l'accueil — sélection « à la une » + ordre) : SOLDÉE
(3/3), cadrée et close le 2026-07-18.** Née d'un constat owner : une catégorie créée
dans `/gestion` n'apparaissait pas sur l'accueil tant qu'aucun produit publié n'y
était classé (l'accueil déléguait à `[product_categories … hide_empty="1"
limit="6"]`), alors que la création ne rattachait aucun produit ni flag d'affichage.
Livré : **9.1** (dashboard v0.37.0) — réglage **« Afficher sur l'accueil » + ordre
par catégorie** dans `/gestion` (term-metas + helper pur `home_meta_ops()`, patron
« absent = intact » qui rend le piège 7.5-AC12 impossible) ; **9.2** (cod-form
v1.36.0) — `AOD_COD_Pages::home_categories_markup()` : accueil piloté par la sélection
(`orderby="include"`, vides comprises), **plafond de 6 retiré**, repli non-régressif
(filtrage des cochées **en PHP** car `get_terms( meta_key )` inopérant sur cette
version WP) ; **9.3** — porte rejouable `run.sh categories` (9/9).

**Phases 10 à 12 — SOLDÉES le 2026-07-18 (8/8 étapes, demande owner).** Trois chantiers
indépendants, chacun une **brique déjà à moitié présente** dans le socle, désormais
finie et **prouvée par une recette rejouable par clone** : **Phase 10** (API de
Conversion Meta / CAPI — `send_capi_purchase()` sur `aod_cod_order_created`, dédup
`event_id`, PII SHA-256, opt-in, fail-open ; bouton « Tester la connexion » ; porte
`run.sh capi`, **23 assertions**), **Phase 11** (Commandes abandonnées — nouvelle
section `/gestion` + capability `aod_cap_abandoned`, lecture des leads `pending`,
Appeler/WhatsApp ; porte `run.sh abandonnees`, **13 assertions**) et **Phase 12**
(Confirmée → expédiée « se déplace » vers Suivi — exclusion `meta_query` sur
`_aod_ship_tracking` dans `section_orders()` + `count_orders()` ; porte `run.sh suivi`,
**9 assertions**). Versions : `aod-cod-form` **v1.37.0**, `aod-client-dashboard` **v0.40.0**.
Verdicts A.10/A.11/A.12, plan Partie B, suivi C.3 ci-dessus.

**Phase 13 (conversions serveur TikTok + Snapchat) : SOLDÉE le 2026-07-19 (4/4
étapes, demande owner).** A généralisé l'envoi serveur des conversions (Phase 10, Meta)
au canal n°1 du COD algérien (**TikTok**, Events API) et à **Snapchat** (Conversions
API v3), en réutilisant l'infra (`event_id`, hachage PII, `to_reporting`, hook, jeton
secret) via un **socle mutualisé** (`capi_http_post`/`order_content_ids`/`normalize_phone`).
Le **piège** identifié au cadrage a été traité : le pixel **navigateur** TikTok/Snap
n'émettait pas d'id de déduplication (`event_id` / `client_dedup_id`) — ajouté des deux
côtés, sinon l'achat compte double. 13.1 socle + TikTok → 13.2 Snapchat → 13.3 boutons
« Tester » (Snap via `/events/validate`, TikTok via `test_event_code`, zéro pollution) →
13.4 clôture (porte `run.sh conversions`, dédup navigateur au filet, doc). Recette
rejouable : **`bash tools/recette/run.sh conversions`** (83 assertions, HTTP intercepté,
0 appel réel). Versions : `aod-cod-form` v1.40.0 · `aod-client-dashboard` v0.43.0.
Cf. verdict A.13, plan Partie B, suivi C.3.

> 🔎 **La leçon des étapes 9.1 → 9.3 :** trois fois, le vrai piège n'était **pas**
> dans le code visé mais dans l'écosystème autour. 9.1 : le patron `parent` (« absent
> = intact ») rendait le piège 7.5-AC12 impossible *par conception* — mieux vaut
> choisir la structure qui exclut le bug que le désamorcer site par site. 9.2 : le
> code (tri, `orderby="include"`) était juste ; le bug était que `get_terms(
> meta_key/meta_value )` **ne remonte pas** la term-meta sur cette version WP —
> trouvé **en recette**, pas en revue. 9.3 : le thème (Astra) affichait la catégorie
> d'un produit sous chaque produit → il fallait cadrer les assertions. **Cartographier
> l'écosystème — WP, WooCommerce, le thème — autant que son propre code.**

> 🔎 **La leçon des étapes 7.2 → 7.5, à relire avant la 7.6.** Trois fois sur quatre,
> **le risque annoncé n'était pas le risque réel** : la 7.2 prescrivait de filtrer les
> getters WooCommerce (ç'aurait gravé l'arabe dans les commandes) ; la 7.4 blindait
> `value=` pendant que deux `gettext` écrivaient déjà de l'arabe dans des clés ; la 7.5
> annonçait un « nom = clé » inexistant, alors que le vrai piège était l'objet mis en
> cache. **Cartographier le code AVANT d'écrire la story** — pas après.

> 🚨 **À lire avant 7.3 → 7.5.** La 7.2 a établi une règle qui vaut pour toute la
> phase : **on ne filtre QUE des hooks d'affichage, jamais un getter WooCommerce.**
> `$order->add_product()` appelle `$product->get_name()` **et persiste** le résultat
> dans la commande, depuis la requête acheteur — filtrer le getter graverait l'arabe
> dans les bordereaux transporteur, définitivement. Les rendus **bruts** se traitent
> par un appel explicite à `AOD_COD_Catalog_I18n::render()`, chez eux. Deux pièges
> vérifiés à réutiliser : `single_post_title` reçoit un **`WP_Post`** (pas un ID), et
> tout filtre de contenu doit passer **avant `wpautop`** (priorité 9).
>
> 🔴 **La leçon de la 7.4, et c'est la plus importante pour la 7.5 :
> `gettext` sur une CLÉ de données est un bug, pas une traduction.**
> La 7.4 cherchait le danger dans ce qu'elle allait écrire (`value=`) ; il était dans
> ce qui existait déjà. Deux `__()` innocents produisaient du **contenu-clé** :
> `__( 'Couleur' )` (branche de compat) devenait la **clé du meta de commande** — et
> `AOD_COD_Lang::filter_locale()` **s'applique à `admin-ajax`** (son propre commentaire
> le dit), donc tout acheteur arabophone enregistrait `اللون` jusque dans le bordereau
> transporteur ; et `color_palette()` faisait injecter par le JS un nom traduit dans le
> champ du **nom canonique**. Aucun des deux ne se voyait.
> → **Avant d'écrire la story 7.5 : chercher les `gettext` dont le résultat est
> comparé, stocké ou utilisé comme clé** (`grep "__( '" ` autour des noms de termes).
> Une chaîne affichée se traduit ; une chaîne qui sert d'identifiant se **fige**.
> Corollaire de portée : `is_admin()` **ne protège ni l'AJAX ni la REST**, et
> `/gestion` **n'est pas garanti en français** (cookie `aod_lang` partagé).
>
> 🚨 **Et la leçon de la 7.3, qui vaut pour toute LISTE** (elle a bien servi en 7.4 :
> le stockage **imbriqué** rend l'appariement structurel, donc le bug impossible —
> préférer cette forme dès que la donnée est un arbre) : **ne jamais dimensionner la
> sortie sur `max( count( $fr ), count( $ar ) )`.**
> Au rang où le FR manque, le repli **bidirectionnel** de `pick()` rend l'arabe — **y
> compris sur le site français**. La version FR est **canonique** : c'est elle qui fixe
> la structure ; seule une version FR **entièrement** vide laisse l'AR mener (produit
> livré 100 % arabe). Helper prêt à réutiliser : `AOD_COD_Catalog_I18n::pick_list()`.

## C.4 Méthode

Chaque étape suit le flux BMAD du dépôt : `bmad-create-story` (cadrage de
l'étape à partir de cette roadmap) → `bmad-dev-story` (implémentation) →
recette DoD. Une étape = une branche `feat/…` + un commit propre minimum.
Les règles non-négociables de `CLAUDE.md`/`TEMPLATE.md` priment sur tout.

---

## C.5 Dette ouverte (hors phases) — relevé du 2026-07-20, complété le 2026-07-21

Ces points ne relevaient d'aucune phase au moment de leur relevé et sont consignés
ici pour ne pas se redécouvrir par accident. Certains ont ensuite ouvert une phase
(D8 → Phase 19, **D9 → Phase 20**) : la ligne reste, avec le renvoi.

| # | Point | Où | Gravité | Pourquoi ça n'a pas été vu |
|---|---|---|---|---|
| ~~D1~~ | ✅ **SOLDÉ le 2026-07-20.** 3 `transition` implicites côté public, remplacées par des listes de propriétés nommées : `border-color`/`background-color` sur `.aod-cod__offer-check`, + `box-shadow` sur `.aod-cod__opt-card`, `background-color`/`color` sur `.aod-lang__btn`. **Le dépôt entier est à zéro.** | `aod-cod-form.css:422` et `:457` ; `class-aod-cod-lang.php:188` | — | La Phase 16 n'a jamais porté que sur `dashboard.css`, et sa porte ne visite que `/gestion`. Le reliquat était **hors de portée de la porte**, pas passé au travers |
| ~~D1b~~ | ✅ **SOLDÉ le 2026-07-20 (étape 18.1).** *Libellé d'origine : « la porte `mouvement` ne couvre pas le plugin public — elle ne visite que `/gestion/` et n'inspecte que `dashboard.css`, donc une régression sur `aod-cod-form.css` repasserait inaperçue ».* La porte couvre désormais **5 feuilles**, et un **cliquet statique** balaie tout `plugins-dev/` — y compris les feuilles qu'aucune liste ne nomme | `tools/recette/mouvement.js` | — | C'était la **cause racine** de D1, et elle avait survécu à sa correction : corriger un symptôme ne rend pas la porte plus large. C'est pourquoi 18.1 passait avant 18.2 et 18.3 |
| ~~D2~~ | ✅ **SOLDÉ le 2026-07-20.** Deux clés ajoutées au tableau i18n public (`error`, `net_error`) et branchées en `:413` / `:418`. AR écrit à la main en réutilisant le glossaire (`خطأ في الشبكة.`, `اتصالك`) ; `make-translations.sh --strict` passe (397 traduites, 3 chaînes de marque). **Vérifié servi au navigateur en FR et en AR** sous `aod_lang=ar` | `aod-cod-form.js:413` et `:418` | — | Aucun test ne déclenche d'échec réseau. ⚠️ **`:418` n'était pas un cas limite** : sur une vraie coupure, `xhr.responseJSON` est `undefined`, donc le littéral s'affichait **systématiquement**. 🚨 Au passage, un premier correctif référençait `i.error` — or `i` est déclaré **dans `showSuccess()`**, pas dans la portée du `.done()` : il aurait levé une `ReferenceError` et cassé l'affichage des erreurs. Corrigé en `AOD_COD.i18n && AOD_COD.i18n.error` |
| ~~D3~~ | ✅ **SOLDÉ le 2026-07-20.** Clé `i18nErr` ajoutée à `window.AOD_CD` (à côté de `i18nNetErr`, qui existait déjà) et branchée aux **19** endroits. AR : `خطأ.`, aligné sur `خطأ في الشبكة.` déjà en place | `dashboard.js` (19 occurrences), `class-aod-cd-dashboard.php:406` | — | Atteint quand le serveur répond en échec **sans** message. Longtemps invisible car la majorité des toasts affichent le message serveur, lui **bien** traduit par `__()`. Portée vérifiée **avant** l'édition (une seule IIFE ligne 2, `CD` ligne 5, les 19 sites entre 470 et 3402) — la leçon de D2, où un `i` hors portée aurait cassé le correctif |

> 🔍 **Comment lire ces deux lignes — le tri qui compte.** `dashboard.js` et
> `aod-cod-form.js` contiennent des dizaines de `|| 'Texte français'`, et **la
> plupart sont inoffensifs** : quand le littéral est précédé d'une clé
> (`CD.i18nCancel || 'Annuler'`, `i.success_title || 'Merci !'`), PHP fournit
> toujours la clé et le repli est **mort**. Seuls comptent les littéraux **sans
> aucune clé** — ceux de D2 et D3. Un audit qui grep `|| '` sans faire ce tri
> surestime le problème d'un facteur 10 ; un audit qui n'en signale qu'une
> occurrence le sous-estime tout autant. Les deux erreurs ont été commises sur
> ce relevé avant d'être corrigées en comptant les cas réellement atteignables.
| ~~D4~~ | ✅ **SOLDÉ le 2026-07-20 (étape 18.2).** 4 clés i18n mortes — fournies par PHP, jamais consommées en JS : `i18nCatDelete`, `i18nCatRename`, `i18nImpCurrent`, `i18nImpNew` | `class-aod-cd-dashboard.php` | Mineure | Traduites en AR pour rien ; à rebrancher ou à retirer |
| ~~D5~~ | ✅ **SOLDÉ le 2026-07-20 (étape 18.2).** `.gitignore` de `library/` ne couvrait que les `.zip` — un thème livré en `.tar.gz`, un dossier décompressé ou un PDF de licence **serait versionné**, contre la règle de `TEMPLATE.md` §2 | `.gitignore:12-13` | Mineure | Le cas ne s'est pas présenté : les thèmes reçus étaient des `.zip` |
| ~~D6~~ | ✅ **SOLDÉ le 2026-07-21 (Phase 23).** Cliquet `tests/unit/test-phpcs-ignore.php` : la 171ᵉ annotation ne passera pas en silence. 🚨 **Le libellé de cette dette était lui-même trompeur** — « 130 relues, 0 faille » ne comptait que la famille `EscapeOutput` ; le dépôt en portait **170**, et les **40** autres (CSRF, entrée non sanitisée, SQL) n'avaient jamais été auditées. Elles l'ont été : **0 suspecte**. Et le risque annoncé comme futur était **déjà réalisé** : une annotation **nue** échappait par construction à tout audit ciblé par nom de sniff | les deux plugins | — | Le fil de la Phase 17 : un contrôle humain sans cliquet ne protège que le passé |
| ~~D14~~ | ✅ **SOLDÉ le 2026-07-21 (étape 28.2).** Les 7 suppressions `WordPress.DB` larges (5 dans `class-aod-cd-section-abandoned.php` : 112, 113, 115, 131, 136 ; 2 dans `class-aod-cd-dashboard.php` : 3275, 3276) sont resserrées sur les sniffs **exacts** qui se déclenchent — `WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery` (136 : `DirectDatabaseQuery` seul, son `prepare()` est en 137) — **chacune avec sa raison** | `aod-client-dashboard` | — | 🚨 **Le blocage affiché n'était qu'une hypothèse non testée** : « phpcs absent de l'hôte ET du conteneur » — faux, le réseau sortant du conteneur marche (Phase 28.1). ✅ **Resserrage vérifié À L'OUTIL** : phpcs (annotations honorées) montre **zéro résidu** sur les 7 lignes — les sniffs étroits couvrent tout ce que `WordPress.DB` couvrait, rien ne ressort. Le gain : une suppression ne coupe plus **toute** la famille DB ; un `SlowDBQuery` ou un `RestrictedFunctions` futur sur ces lignes rougirait désormais. Census `WordPress.DB` inchangé (13, gardé par R2) ; plancher R3 sans-raison descendu 139 → 132 (7 raisons ajoutées) |
| ~~D15~~ | ✅ **SOLDÉ le 2026-07-21 (Phase 24).** Les deux helpers passent en `private static` — le contrat cesse d'être documentaire et devient **structurel** —, et le cliquet `tests/unit/test-nonce-contract.php` **énumère leurs appelants** en exigeant que chacun vérifie un nonce. Un 7ᵉ appel non gardé rougit en nommant fichier et ligne. Étendu au passage aux **59** méthodes de `plugins-dev/` lisant une superglobale : 33 noncent, 5 délèguent, 16 sont déclarées avec leur raison | les deux plugins | — | 🚨 La relecture indépendante a rattrapé le piège : `AOD_CD_Account::render_page` délègue depuis un `if` imbriqué et appelle `wp_insert_user()` — la déclarer « lecture d'affichage » aurait gravé une fausse déclaration de sécurité |
| 🟡 D16 | **La porte `confirmateur` a flanché une fois à la connexion** (`waitForNavigation: Timeout 30000ms`), au 15ᵉ rang d'un `run.sh tout`, **avant sa première assertion**. **Non reproductible** : verte seule (10 s) et verte au run complet suivant (34/0) | `tools/recette/lib.js` | Basse | 🚨 **Une porte instable apprend à ignorer la recette** — le mode d'échec le plus coûteux. **Traité le 2026-07-21 sans deviner** : les deux hypothèses pressenties ont été **écartées par la mesure**, et le correctif « évident » N'A PAS été appliqué. ❌ « `load` attend les sous-ressources » → mesuré `domcontentloaded` **220 ms** / `load` **256 ms** : **36 ms d'écart**, passer en `domcontentloaded` n'aurait rien réglé. ❌ « un cron sortant bloque » → `aod_ship_tracking_sync` en **2,2 s**, option `aod_cod_pixels` **absente** (l'envoi différé ne part nulle part), bloc `dns:` **présent** (aucun appel ne pend sur une résolution). ✅ **Ce qui a été fait** : l'échec PARLE désormais — user, message, URL, titre, capture d'écran — au lieu d'une exception Playwright nue. Ni réessai, ni timeout relevé : on ne masque rien, on rend la prochaine occurrence **diagnosticable**. Cause résiduelle présumée : contention transitoire de la pile |
| ~~D8~~ | ✅ **SOLDÉ le 2026-07-20 (étape 19.1).** Le plugin public n'honorait pas `prefers-reduced-motion` — 0 règle dans `aod-cod-form`, contre 5 dans `dashboard.css`. Un acheteur ayant activé « réduire les animations » subit quand même les transitions | `plugins-dev/aod-cod-form/assets/css/*.css` | Mineure (accessibilité) | Ouvert par l'étape **18.1**, qui l'a **mesuré** en cherchant à étendre son contrôle de mouvement réduit au public : il n'y avait rien à contrôler. C'est une **fonctionnalité manquante, pas un test manquant** — d'où l'exclusion assumée du périmètre de 18.1, que la porte **publie à chaque exécution** plutôt que d'être verte par omission |
| ~~D9~~ | ✅ **SOLDÉ le 2026-07-21 (étape 20.1).** **La typo de `/gestion` venait d'un CDN** — la coque charge Barlow depuis `fonts.googleapis.com` (2 `preconnect` + 1 feuille). Sans réseau — le cas nominal d'un clone neuf, `TEMPLATE.md` §3 — la charte retombe **en silence** sur la police système. S'y ajoutent une requête tierce portant l'IP du gérant à chaque page d'un back-office `noindex`, et 2 allers-retours avant le premier texte peint. **Traité en Phase 20** | `class-aod-cd-dashboard.php:385-387` | Moyenne | 🚨 **Le document qui l'interdit et le code qui l'enfreint sont dans le même plugin, à 300 lignes d'écart** : `assets/icons/README.md` justifie l'embarquement des 71 SVG par « sans npm, **sans CDN**, sans build ». L'audit de cohérence doc ⇄ code de juillet a comparé la doc **au code qu'elle décrit** (les icônes, conformes) — pas à ce que le **même fichier** fait par ailleurs pour une autre ressource. Une règle vérifiée sur son exemple canonique paraît tenue partout. Et le mode d'échec est muet : une police de repli laisse l'écran crédible, là où un emoji manquant se voit |
| ~~🟡 D10~~ | ~~**`AOD_COD_Data::is_arabic()` est vrai dès que `is_rtl()` l'est** — un thème RTL sur un site **francophone** ferait basculer les libellés géographiques (58 wilayas / 1541 communes) et le rendu de la landing en arabe~~ — **SOLDÉE le 2026-08-03** (`b755c81`, `1728 tests`) | `class-aod-cod-data.php:95` · `class-aod-cod-form.php:509` | Mineure (latente) | Aucun clone n'avait livré de thème RTL sur site FR : la condition n'a jamais été réunie. Le `\|\| is_rtl()` était un raccourci commode tant que RTL ⇔ arabe | **✅ SOLDÉE le 2026-08-03.** `is_arabic()` lit désormais la **langue**, jamais le **sens d'écriture** : `is_rtl()` répond « le rendu va de droite à gauche », pas « le visiteur lit l'arabe ». ⚠️ **Le retrait sec aurait été une régression, et c'est tout l'intérêt de ce correctif** : `AOD_COD_Lang::filter_locale()` se retire volontairement du wp-admin (l'admin reste dans la langue du SITE), si bien qu'un administrateur au **profil arabe** avait `get_locale() === 'fr_FR'` et ne devait son arabe qu'à `is_rtl()`. Le `\|\| is_rtl()` rendait donc **un vrai service, par effet de bord** — il est rendu explicitement, par `get_user_locale()`, derrière la **même frontière que `AOD_COD_Lang`** (`is_admin() && ! wp_doing_ajax()` : admin-ajax n'est pas l'admin, c'est le visiteur). 🚨 **Un JUMEAU trouvé en corrigeant** : `AOD_COD_Form::is_arabic()` portait sa propre copie de la règle et avait divergé — l'une lisait la langue, l'autre y ajoutait `is_rtl()`. **Deux réponses possibles à la même question sur la même page**, famille des Phases 44 et 47 ; il délègue désormais (un corps, trois appelants). Cliquet : `tests/unit/test-langue-vs-sens.php` (R1 comportement, R2 la langue commande, R3 un seul corps), **3 mutations éprouvées au rouge**. 🚨 **Il a FALLU fabriquer l'état** (`$wp_locale->text_direction`, restauré en `finally`) : le défaut étant latent et ce clone en LTR, **aucune porte de recette ne pouvait l'atteindre** — un défaut hors de portée de toute mesure ne se garde qu'en construisant sa condition. ⚠️ **Et le red-proof a montré que le cliquet ne gardait que la MOITIÉ du correctif** : supprimer la branche admin ne faisait rougir personne. Deux assertions structurelles ajoutées, avec la raison écrite (`WP_ADMIN` n'est pas définissable sous WP-CLI) — *ce qu'un correctif préserve se perd plus silencieusement que ce qu'il retire, parce que ça ressemble à un nettoyage*. |
| ~~D11~~ | ✅ **SOLDÉ le 2026-07-21 (étape 21.1).** `ajax_test_carrier` exigeait `manage_woocommerce` là où ses deux voisines de section demandent `aod_cap_shipping_prices`. **Un seul** vrai écart : les 3 autres divergences sont voulues et désormais **déclarées avec leur raison** dans la table du cliquet | `class-aod-cd-dashboard.php:2441` | — | 🚨 Invisible parce que **`manage_woocommerce` satisfait toutes les gardes** : le gérant ne rencontre jamais ce mur, et c'est en gérant qu'on développe **et qu'on recette**. Cause racine toujours ouverte : **D13** |
| ~~D12~~ | ✅ **ARBITRÉ le 2026-07-21 — c'est VOULU, l'arbitrage est clos.** *« Le gérant gère les mots de passe »* (owner). Un confirmateur n'a pas de section « Mon compte » (`visible_sections():209`) et `ajax_save_account` reste **resserré** (v0.18.6) : le gérant change le mot de passe depuis **Équipe**. Ce n'est **pas une dette**, c'est une décision de produit. | `class-aod-cd-dashboard.php:85-92`, `:3051` | — | 🚨 **Consigné parce qu'il a déjà été pris pour un bug** lors de l'analyse A→Z du 2026-07-21 : le handler, lu seul, paraît sur-gardé (il est borné à `$user->ID`). La garde n'a de sens qu'avec le routeur sous les yeux. La raison manquait — elle est désormais dans `ARCHITECTURE.md` §6. **Ne pas le « corriger » une troisième fois.** |
| ~~D13~~ | ✅ **SOLDÉ le 2026-07-21 (étape 22.2).** La porte `run.sh confirmateur` parcourt `/gestion` sous **deux identités confirmateur** aux capabilities complémentaires — **34 assertions**. Les 7 caps sont désormais exercées **dans les deux sens** (une cap ouvre chez l'un ce qu'elle ferme chez l'autre), les sections gérant sont éprouvées **par URL** et non seulement au menu, et les gardes AJAX sont mesurées sur un **vrai code HTTP** | `tools/recette/confirmateur.js` · `scripts/recette-seed-phase22.php` | — | 🚨 C'était la **cause racine de D11** : le cliquet de la Phase 21 est *statique* et ne verra jamais un 403 réel. Éprouvée au rouge sur la signature exacte du défaut de la Phase 21 (`test_carrier` → 403) |
| ✅ D17 | ~~**`/gestion` tombe en fatal si `aod-cod-form` est désactivé**~~ — **SOLDÉE le 2026-07-22 (32.1).** Fatale reproduite, corrigée en conditionnant la fonctionnalité à sa dépendance (`AOD_CD_Cod`), gardée par la porte `run.sh degrade` qui rend les 12 sections sans le plugin — **sans jamais le désactiver** (`wp --skip-plugins`) | `aod-client-dashboard` | — | — |
| ❌ D18 | ~~**`AOD_CD_Section_Abandoned` chargée mais jamais instanciée**~~ — **FAUX POSITIF, retirée le 2026-07-22 (32.1).** `__construct()` vide, aucun hook, aucun handler AJAX ; `render()` est appelée à la demande. Ne pas l'instancier au boot est CORRECT. L'audit avait généralisé depuis les sections qui enregistrent des hooks. 🚨 Une anomalie de FORME n'est pas une anomalie de FOND | `aod-client-dashboard` | — | — |
| ~~🟠 D19~~ | **5 `media_handle_upload()` sans liste blanche MIME** — photo produit (`section-products.php:1281`), image d'option (`:1412`), galerie (`:1534`), image de catégorie (`:1687`), images de landing (`section-landing.php:915`). Le bon motif existe à côté : `handle_desc_upload()` (`:1758-1794`) passe `mimes` explicitement | `aod-client-dashboard` | Moyenne | Pas une RCE (`.php`/`.svg` absents de `get_allowed_mime_types()` par défaut, aucun rôle du plugin n'a `unfiltered_upload`). Mais un champ « Photo du produit » accepte PDF/ZIP/MP3, et **si un clone active un plugin autorisant le SVG — fréquent sur un thème de design — les 5 champs deviennent une XSS stockée sans qu'une ligne de ce dépôt ait changé** | **✅ SOLDÉE en 32.2.** ⚠️ Re-scopée au passage : elle était rangée sous « surface NON AUTHENTIFIÉE » — c'est faux, les 5 appels exigent `edit_products`, hors de portée d'un confirmateur. La note ci-contre, elle, avait raison sur l'essentiel : **le risque n'est pas l'état actuel, c'est la filtrabilité de `get_allowed_mime_types()`**. |
| ~~🟠 D20~~ | **`aod_cod_lead` : `nopriv` sans limite de débit** — `class-aod-cod-leads.php:161` ne porte qu'un nonce, et le `token` est fourni par le client → une ligne neuve par token inventé, sans plafond | `aod-cod-form` | Moyenne | 🚨 **Le nonce n'est pas une barrière ici** : pour un visiteur déconnecté, `wp_create_nonce()` se calcule sur `user_id = 0` et une session vide — la valeur est **constante pour tout le site sur 12-24 h** et publiée dans le HTML de chaque page produit. Pas de compromission (le rendu est intégralement échappé), mais saturation disque et pollution de « Commandes abandonnées ». Correctif : le compteur `AOD_COD_IP::client()` de `handle_submit()` | **✅ SOLDÉE en 32.2** — plafond de 60 CRÉATIONS/h par IP (`AOD_COD_MAX_LEADS_PER_IP_HOUR`), forme du jeton bornée, et un jeton déjà connu n'est jamais plafonné. |
| ~~🟠 D21~~ | **« 5 couches anti-bot » en annonce 5, en tient 1 sur un clone neuf** — honeypot (contourné en ne remplissant pas), time-trap (`isset() && is_numeric()` : **omettre le champ suffit à la sauter**), plafond téléphone (anti-doublon, pas anti-bot), Turnstile (**inexistant tant que les clés ne sont pas saisies**). Seul le coupe-circuit IP tient réellement, depuis la 26.1 | `class-aod-cod-form.php:996-1060` · docs | Moyenne | Ce n'est pas un défaut de code : c'est la conséquence assumée de « pas d'internet sortant » + Turnstile non configuré. **Mais le nombre « 5 » surestime la protection**, et c'est le genre d'écart que ce dépôt corrige ailleurs. Renforcement à coût nul : rendre `elapsed` OBLIGATOIRE (le champ est posé par le JS ; sans JS il n'y a pas de soumission AJAX — la tolérance protège un cas qui n'existe pas) | **✅ SOLDÉE en 32.3** — l'absence d'un champ est désormais aussi disqualifiante que sa mauvaise valeur, garde unique partagée par les deux formulaires publics, refus journalisés, et les 9 fichiers qui annonçaient un compte de couches (dont deux qui en annonçaient **6**) disent maintenant ce qui exige une configuration. |
| ✅ D22 | ~~**WhatsApp : la détection d'erreur ne peut pas se déclencher**~~ — **SOLDÉE le 2026-07-22 avec la 31.3.** `blocking => false` rendait `is_wp_error()` faux dans tous les cas réels : le bloc « Notification WhatsApp non envoyée » était du **code mort**, donc le gérant croyait être notifié sans l'être. Y ajouter un journal aurait été écrire une ligne qui ne peut jamais s'exécuter — c'est pour ça que la dette devait tomber AVEC 31.3, pas après. Correctif : motif de la Phase 14 — `wp_schedule_single_event()` sort l'appel du chemin d'achat, et il redevient **bloquant** là où plus personne n'attend. 🚨 **Les deux progressent, ce n'est pas un arbitrage** : `blocking => false` n'évitait ni le DNS ni le `connect()`, donc l'acheteur payait déjà l'attente sur un clone sans DNS sortant (le cas NOMINAL de TEMPLATE.md §3) — il ne paie plus rien, et l'échec est enfin détectable. Gardée par R5 de `test-journalisation.php`, éprouvée au rouge en remettant le `blocking => false` exact | `aod-cod-form` | — | — |
| ~~🟡 D27~~ | ~~**L'envoi transporteur reste SYNCHRONE dans la requête du confirmateur**~~ — ⛔ **SANS OBJET depuis la Phase 47.2** (livrée le 2026-08-01, `9ef9197`). La dette décrivait le chemin `ajax_order_status` → `auto_send()` : **ce chemin n'existe plus.** Le hook `woocommerce_order_status_aod-confirmed` a été retiré, et l'envoi est devenu un **geste** déclenché au camion. La latence a donc quitté le chemin critique de la confirmation — non par une correction technique (Action Scheduler, file d'attente…), mais parce que **l'arbitrage produit a été rendu** : l'owner a décidé le 2026-08-01 que l'envoi sortirait de la confirmation. 🚨 **Elle est BARRÉE, pas effacée, et c'est délibéré.** Une dette qui disparaît sans trace laisse croire qu'elle n'a jamais existé — et la prochaine personne qui mesurera une latence d'envoi rouvrira le même dossier, sans savoir que le cadrage a été fait (`_bmad-output/planning-artifacts/cadrage-d27-envoi-synchrone-2026-07-28.md`, 5 options chiffrées) ni que la mesure de `AOD_Carrier::chrono()` existe depuis l'étape 0. ⚠️ **Ce que la 47.2 n'a PAS supprimé** : l'envoi reste synchrone **dans la requête de celui qui clique le camion**, et un lot est une boucle séquentielle plafonnée à 50 (`MAX_LOT_ENVOI`). La différence n'est pas technique, elle est de **consentement** : la personne qui attend a demandé l'envoi | `aod-cod-form` · `aod-client-dashboard` | — | **✅ CLOSE SANS OBJET en 47.9.** Ne pas la rouvrir sur la foi de son ancien énoncé : il décrit un code qui n'existe plus. Cf. §C.3 ligne 47.2 |
| ~~🟠 D23~~ | **« Actualiser tout » du Suivi est un auto-DoS** — `class-aod-cd-dashboard.php:2149` appelle `run_sync()` **en synchrone** dans la requête AJAX : 1200 commandes hydratées + jusqu'à 30 appels HTTP séquentiels × 30 s de timeout | `aod-client-dashboard` · `aod-ship-tracking` | Moyenne | La requête meurt sur le timeout PHP ou le reverse-proxy ; le gérant voit « Erreur réseau », **reclique**, et relance tout depuis le début. Le même travail en cron peut dépasser `max_execution_time` sans reprise ni trace : le suivi se fige **alors que le cron tourne** — pas le symptôme que `docs/CRON-SERVEUR.md` décrit. Timeout transporteur par défaut à 30 s (`class-aod-carrier.php:576`) : les API DZ répondent en < 2 s, 30 s ne rattrape rien | **✅ SOLDÉE en 32.4** — moteur par tranches sous budget de temps, reprise lue dans la donnée (`META_STATUS_AT` est le curseur), timeout 10 s, chaque passage journalisé (canal `tracking`), colis en panne annoncés au gérant. Cliquet `test-suivi-lots.php`. |
| ~~🟡 D24~~ | **`dashboard.js` : 16 `catch` au corps VIDE sur 27 initialisations**, et 11 non protégées du tout (`:3413-3441`) | `aod-client-dashboard` | Basse | Un binder cassé disparaît **en silence total** — ni `console.error`, ni toast : la fonctionnalité s'évapore de l'UI. C'est le motif exact que la Phase 17 a diagnostiqué en CSS et la Phase 27 en recette, jamais traité en JS. Et la ligne de partage entre protégés et nus ne suit **aucune logique** (`bindWhatsappTest` nu, `bindCapiTest` juste à côté protégé) : une exception dans `bindStatus` (2ᵉ appel) avorte les 25 suivantes. Correctif immédiat et sûr : `console.error( err )` dans les 16 | **✅ SOLDÉE en 32.5** (mesuré : 30 initialiseurs, 19 muets) — helper unique `init( name, fn )`, échec nommé en console, isolation complète, et census des 3 catch muets publics VOULUS (appels tiers du chemin d'achat) avec leur raison. Cliquet `test-init-binders.php`. |
| ~~🟡 D25~~ | **La doc a décroché sur ses compteurs, y compris ses phrases-garanties** — `CLAUDE.md`/`TEMPLATE.md` désignent §C.3 comme faisant foi ; elle s'arrêtait à 30.1 alors que 30.2/30.3 étaient commitées. Le fichier-dieu est annoncé à « 4229 l » dans 4 documents (réel : 2836). `ARCHITECTURE.md` se contredit sur sa propre version (`:5` v0.46.0, `:243` v0.48.0) et se date de 12 phases en arrière | `docs/`, `TEMPLATE.md` | Moyenne | §C.3 remise à jour le 2026-07-22. 🚨 **Le levier n'est pas de corriger les chiffres — ils re-vieilliront.** C'est de les SORTIR de la prose : le plafond de lignes vit très bien dans `test-decomposition.php:75`, où il est juste. Et de dégonfler l'auto-chargé — **43 % de `TEMPLATE.md`, lu avant chaque ligne de code, est un récit de phases recopié de la ROADMAP**, et c'est là que vivent les chiffres faux | **✅ SOLDÉE en 32.6** — cliquet `test-doc-derive.php` (le squelette recalculé à chaque run), en-tête d'`ARCHITECTURE.md` réparé (v0.48.0, daté du 2026-07-22, renvoie à §C.3 pour l'état des phases), les « 4229 l » de TEMPLATE.md requalifiés en historique avec renvoi au cliquet pour le compte courant. ✅ **Dégonflage fait le 2026-07-27** — le récit de phases (~650 lignes, §9) retiré de `TEMPLATE.md`, remplacé par un renvoi à §C.3 (état) et `ARCHITECTURE.md` (technique) + les 3 points de dette réellement ouverts (D27, D10, A.4/Maystro). `TEMPLATE.md` passe de 1175 à ~545 lignes. Les anchors du cliquet `test-doc-derive.php` (R1/R3/R5) conservés sous forme d'une ligne d'état compacte au lieu du récit complet. |
| 🟡 D26 | **Le seuil de livraison gratuite de ce clone est perdu** — `aod_cod_free_shipping` remis à `{enabled:0, threshold:0}` le 2026-07-22 par une sonde de recette écrite « inoffensive » (POST vide sur `aod_cd_save_shipping`, qui reconstruit la grille depuis le POST). La grille des wilayas a été reconstruite depuis l'historique des commandes (6 wilayas) ; le seuil, lui, n'est déductible d'aucune trace | ce clone (donnée, pas code) | Basse | 🚨 **Consigné comme incident, pas comme dette de code.** La leçon est encodée dans `test-numerotation-sequentielle.php` (R3 : le contrôle ne doit pas abîmer ce qu'il contrôle) et dans le commentaire d'AC4 de `confirmateur.js`. **Une sonde d'autorisation ne doit toucher que des handlers dont l'échec métier précède l'écriture ; sinon on lit l'écran** | | **⛔ CLOSE SANS OBJET POUR LE TEMPLATE le 2026-08-03.** Elle n'a jamais été une dette de code, et elle n'en devient pas une : **`{enabled:0, threshold:0}` est exactement l'état par défaut d'un clone neuf** (`AOD_COD_Data::free_shipping()`, `wp_parse_args`) — mesuré ce jour sur ce clone. Le seuil perdu était une **donnée de développement** sans portée métier sur le dépôt master ; sur un clone client, il se ressaisit dans `/gestion → Livraison` en dix secondes. La laisser ouverte ferait croire à un correctif en attente. ⚠️ **Ce qui reste, et qui est le vrai livrable** : la leçon est encodée en DEUX cliquets — `test-numerotation-sequentielle.php` R3 (le contrôle ne doit pas abîmer ce qu'il contrôle) et le commentaire d'AC4 de `confirmateur.js` — et elle a **déjà resservi trois fois depuis** (fixtures des portes `panier`, `degrade` et `landing`, toutes capturées/restaurées en `trap EXIT` en la citant). *Une dette dont le livrable est une leçon se solde quand la leçon a un cliquet, pas quand la donnée revient.* |
| ~~🔴 D28~~ | **La journalisation de la Phase 31.3 n'écrit RIEN sur une requête web** — mesuré le 2026-07-22 en écrivant la 32.2 : `AOD_COD_Log::error()` produit bien une ligne sous **WP-CLI**, et aucune depuis `admin-ajax.php`. Sondé au plus près : `should_handle()` rend `true`, les deux handlers sont présents (`LogHandlerFileV2` + `RemoteLogger`), le dossier `wc-logs/` est inscriptible et le fichier appartient à `www-data` — et pourtant `LogHandlerFileV2::handle()` rend **`false`** sous l'uid 33 (Apache), là où le même appel écrit sous l'uid 0 (CLI) | `aod-cod-form` + environnement | **Haute** | 🚨 **C'est la promesse centrale de la Phase 31.3 qui tombe.** Le socle a été bâti pour rendre 6 intégrations en fail-open *diagnosticables* ; sur le trafic réel — le seul qui compte — il ne laisse aucune trace. Pire : les phases suivantes s'y adossent (31.4 « doublon évité », 32.2 « plafond atteint ») en croyant la trace acquise. ⚠️ **Ne pas contourner en journalisant ailleurs** : le cliquet R4 de `test-journalisation.php` l'interdit, et à raison — les 3 couches de rédaction des secrets se court-circuitent d'un geste. Le travail est de trouver pourquoi `File::write()` échoue sous Apache (piste : `is_writable()` de `File`, ou `create()` via `WP_Filesystem`, sur un `wordpress/` **bind-monté** dont le parent appartient à l'uid 1000 de l'hôte — cf. TEMPLATE.md §3, qui décrit déjà ce genre de morsure sur les téléversements). 🔎 **Trouvé par accident**, en vérifiant qu'un refus de quota laissait bien une trace : *c'est en doutant de sa propre garde qu'on trouve celle d'à côté.* | **✅ SOLDÉE le 2026-07-22, dans la foulée.** Cause trouvée **hors de notre code** : `get_filesystem_method()` ne conclut « direct » que si `fileowner( wp-admin/includes/file.php )` **égale** le propriétaire d'un fichier créé par PHP. `wordpress/` étant **bind-monté**, le cœur appartient à l'hôte (uid 1000) et PHP tourne en `www-data` (uid 33) : l'égalité est structurellement impossible et **aucun `chmod` n'y peut rien** — c'était ma première hypothèse, mesurée fausse. WordPress retombait sur `ftpsockets`, `WP_Filesystem()` rendait `false`, `FilesystemUtil::get_wp_filesystem()` levait, et `LogHandlerFileV2::handle()` rendait `false` **sans un mot**. Correctif : `define('FS_METHOD','direct')` via `WORDPRESS_CONFIG_EXTRA` (`docker-compose.yml`) — évalué à chaque requête par le `wp-config.php` de l'image officielle, donc effectif **aussi sur les clones déjà installés**, sans y toucher. ⚠️ **Ne concerne que la pile de dev** : sur un hébergement normal, propriétaire des fichiers et utilisateur PHP coïncident. Porte `bash tools/recette/run.sh journal` (dans `tout`), **éprouvée au rouge** sur la signature exacte. 🚨 **La leçon dépasse la dette : un test unitaire ne pouvait PAS voir ce défaut** — `tests/run.sh` s'exécute dans le processus WP-CLI, précisément celui qui fonctionnait. *Éprouver une couche dans le contexte où on la développe, jamais dans celui où elle sert, c'est se garantir un vert permanent.* |
| ~~🟠 D29~~ | **Les portes de recette ne voient pas les défauts qui dépendent des DONNÉES** — relevé le 2026-07-28. `run.sh rtl` audite le débordement horizontal à 390 px **sur les 12 sections**, et elle était VERTE pendant que `/gestion/orders` débordait de 193 px : elle s'exécutait sur le clone TEL QUEL, et un clone de développement a **zéro commande** — sans lignes, ni tableau ni barre d'action ne sont rendus. Mesuré : **6 sections sur 12** (`home`, `orders`, `abandoned`, `products`, `stock`, `tracking`) rendaient un état vide, avec 0 tableau et 0 ligne. Même cause pour les 2 échecs permanents de `mouvement` (`aod-landing.css` jamais chargée, faute d'un produit avec page de vente) | `tools/recette/` | Moyenne | 🚨 La « règle du témoin » (§C.2) appliquée aux **DONNÉES** et non plus au code : la porte passe sans avoir regardé la chose qui casse, et rend le même signal qu'un audit exhaustif | **✅ SOLDÉE le 2026-07-28, sur les DEUX fronts.** (1) **Fixtures** — `scripts/recette-seed-transverse.php` (marqué, purgé au trap comme tous les autres) sème le minimum pour que les 6 sections creuses portent du contenu : 1 produit (options + stock géré + **page de vente**), 3 commandes dont une expédiée, 1 prospect complet. `run.sh` le monte avant les portes transverses (`contraste`, `noms`, `clavier`, `souris`, `rtl`, `mouvement`). Mesuré : **6 sections creuses → 0**, et `mouvement` passe de 2 échecs à **5/5 feuilles auditées**. (2) **La porte SAIT désormais ce qu'elle a regardé** — helper partagé `etatDonnees()` (`lib.js`), et `rtl-mobile.js` publie « Sections réellement GARNIES : N/12 » en **nommant** les creuses. Sans fixtures elle annonce donc **6/12** au lieu d'un « 0 échec » indistinguable d'un audit complet ; avec fixtures (`AOD_D29_SEEDED=1`), une section restée creuse **fait ROUGIR** la porte — red-proofée. 🚨 **Et le semis a immédiatement trouvé ce qu'il était censé trouver** : `a11y-contraste` est tombée sur les 6 accents, par timeout de 30 s sur un `uncheck()`. Cause : **six sites**, dans deux portes, garaient le curseur en `(5,5)`/`(0,0)` — coin inerte jusqu'à l'étape 43.8, qui en a fait la **zone de survol du rail**. Y poser la souris dépliait la barre à 248 px, laquelle recouvrait la première colonne des tableaux et interceptait le clic suivant. Défaut non pas du produit, mais d'une **hypothèse de la recette devenue fausse** — recopiée six fois, elle a failli six fois. D'où `garerSouris()`, helper unique gardant le curseur au centre de la barre du haut (un coin n'est jamais neutre : le rail passe à droite en RTL). |
| ~~🟡 D30~~ | **`test-doc-derive.php` ne confrontait jamais §C.3 au dépôt** — relevé le 2026-07-28. Le cliquet de la 32.6 recoupe les en-têtes de la Partie B avec la table, et la ligne d'état de `TEMPLATE.md` avec la table : deux documents **entre eux**. Il ne comparait **rien au code ni à git**. Conséquences mesurées : l'étape **42.7 a vécu six jours en git sans ligne en §C.3 ni section en Partie B**, cherchée par aucune règle ; et les statuts 43.1→43.8 sont restés « ⏳ » pendant que le code était commité — les deux documents étaient **d'accord entre eux, et faux tous les deux** | `tests/unit/test-doc-derive.php` | Moyenne | ⚠️ Le report était ici DÉLIBÉRÉ (chaque story écrit « MAJ §C.3 déférée à N.9 ») : ce n'est pas la dérive D25 qui recommence, c'est sa **limite structurelle** — le cliquet ne peut pas distinguer « pas encore fait » de « oublié » | **✅ SOLDÉE le 2026-07-28.** Nouvelle règle **R7** : toute étape `N.M` apparaissant dans un **sujet de commit** doit avoir une ligne en §C.3. Elle exige l'**EXISTENCE** de la ligne, jamais son statut — sans quoi elle contredirait la convention de report et serait rouge toute la phase, donc ignorée (leçon D16). 🚨 **Le conteneur de tests n'a pas `git`** (vérifié) et `.git/logs/HEAD` est un reflog, vide sur un clone frais : `tests/run.sh` produit donc la liste **côté hôte** (`tests/.git-sujets.txt`, ignoré par git), le test la consomme — chacun fait ce qu'il sait faire. Le parseur de §C.3 reconnaît désormais aussi les suffixes d'une lettre (`15.7b`), pas seulement `bis`. **Éprouvée au rouge TROIS fois** : ligne 42.7 retirée → R7 la nomme ; `#NO_GIT` → le cliquet DIT qu'il n'a rien vérifié au lieu de verdir ; fichier absent → échec avec la marche à suivre. Mesure de sortie : **81 étapes commitées, toutes couvertes**. Au passage, la note « les Phases 33 à 42 ne sont pas listées ici » s'est révélée **fausse dès son écriture** (43 lignes présentes, 42 déjà sur `main`) — corrigée. |
| ✅ D31 | ~~**La porte `abandonnees` porte un rouge PRÉ-EXISTANT**~~ — **SOLDÉE le 2026-07-30.** 🚨 **La dette accusait le produit ; le défaut était dans la PORTE — et sa contre-mesure était fausse sur ses deux termes.** Mesuré : la liste portait **28 lignes pour un `PER_PAGE` de 20**, et le témoin — daté du 15/06/2024, donc le plus ancien d'un tri `updated_at DESC` — était au **rang 28**, donc en **page 2**. Sonde dédiée qui discrimine les deux hypothèses : après le clic, l'URL est nue (`/gestion/abandoned/`), les deux champs de date sont **vides**, et le témoin est **présent en page 2**. « Réinitialiser » faisait exactement son travail. ⚠️ **La ligne « 15 prospects `pending` pour un `PER_PAGE` de 20, la pagination n'est pas en cause » était fausse deux fois** : (1) elle comptait les prospects du clone **au repos**, sans les **8 fixtures que la porte sème elle-même** à l'instant où l'assertion s'exécute (compte réel : 28) ; (2) elle ne comptait que les `pending`, alors que l'onglet par défaut est **`all`** et inclut les `converted`. *Compter la donnée hors du contexte où l'assertion tourne, c'est mesurer autre chose* — c'est le même geste que la Phase 30 avait consigné en découvrant qu'un test joué sous WP-CLI ne peut pas voir un défaut qui n'existe qu'en requête web. 🚨 **Et la leçon existait déjà dans le dépôt sans avoir été propagée** : `97e8982`, le MÊME JOUR, corrigeait ce défaut exact sur le cliquet `test-filtre-date.php` — même témoin de 2024, même pagination — en écrivant « un test qui ne survit pas aux données réelles de la zone qu'il garde ne garde plus rien ». Le correctif n'a jamais atteint les portes. 🔎 **Deux jumeaux trouvés en corrigeant le premier**, tous deux dans `commandes-suivi.js` : « Suivi : après Réinitialiser, le colis du 15/06 redevient visible » était **verte par accident de données** (le Suivi ne liste que les colis expédiés, et aucun jeu n'en avait produit vingt) ; « Commandes/Toutes : la non expédiée reste visible » était **ROUGE et couverte par aucune dette** — la porte `suivi` ne figurait pas parmi les 8 rejouées à la clôture de la Phase 46, donc son rouge n'a jamais été relevé. Correctif : `chercheDansPages()` dans `lib.js`, **un seul corps pour trois appelants**, qui parcourt les pages au lieu de supposer la première et compte des **lignes de tableau** (jamais `has( html, … )` : le champ de recherche réaffiche sa valeur). L'assertion de « Réinitialiser » est en outre **décomposée** — « le filtre est vidé » et « le témoin est dans la liste » sont deux affirmations, dont une seule dépend du volume : mesurées séparément, elles disent **laquelle** a lâché. Éprouvée au rouge dans les deux sens (témoin inexistant ⇒ ✗ « page 0/2 » ; clic neutralisé ⇒ ✗ ×2 **en nommant la valeur lue**). `abandonnees` **66 ✓ / 1 ✗ → 69 ✓ / 0 ✗** ; `suivi` **18 ✓ / 1 ✗ → 19 ✓ / 0 ✗** | `tools/recette/lib.js` · `abandonnees.js` · `commandes-suivi.js` | — | 🚨 **Ce qu'il faut retenir pour la prochaine dette** : celle-ci portait la bonne consigne (« un rouge de porte se diagnostique, il ne se répare pas à l'aveugle ») et une **mesure fausse** qui a orienté vers la mauvaise piste pendant deux phases. Une dette doit consigner **comment** le chiffre a été obtenu, pas seulement le chiffre — sans quoi elle se relit comme un fait établi |
| ✅ D32 | ~~**Le tableau des transporteurs est illisible au pouce**~~ — **SOLDÉE le 2026-07-30.** 🚨 **Les deux chiffres de cette dette étaient faux, et c'est la leçon la plus utile qu'elle laisse.** (1) Les « 760 px de large » n'étaient pas la largeur du contenu : c'était le `min-width:760px` que `.aod-cd-table` impose à TOUS les tableaux — un plancher taillé pour 7 colonnes, appliqué à un tableau qui en a **trois**. Il pesait **408 des 490 px**. (2) Sur les « 254 cellules sans `data-label` », **138 appartiennent aux 29 `.form-table` de WordPress imbriquées** dans les panneaux : elles n'ont jamais eu à porter de `data-label`, et la dette les comptait comme si le patron carte les concernait. **Conclusion inverse de celle que la dette annonçait** : le patron `--cards` n'était pas la solution, et l'**interaction n'a pas changé d'un iota** — la ligne reste le même `role="button"` avec son `tabindex`, le panneau reste la ligne suivante, `section-shipping.js` les apparie toujours par `nextElementSibling`. Le chantier « à cadrer » redevenait un correctif de mise en page. ⚠️ **Ce qui restait vrai, en revanche, c'est que l'empilement est OBLIGATOIRE, et il est contraint et non esthétique** : une ligne exige 38 (écusson) + 13 (espace) + 88 (« E-com Delivery », le nom le plus long) + 112 (pastille « Non connecté ») + 38 (chevron) = **289 px de contenu pour 270 disponibles**, padding non compris. Aucun réglage de padding ne fait tenir trois colonnes à 390 px. La pastille passe donc sous le nom et le chevron quitte le flux (`inset-inline-end`, miroir AR sans surcharge RTL). 🔎 **Le patron `--cards` a été écarté pour une raison mesurée, pas par goût** : ses règles visent `tr`/`td` en DESCENDANT — chacune des 138 cellules imbriquées serait devenue une carte bordée. Tout le bloc est donc scopé en `>`. **Défaut évité au passage, et il aurait été invisible en revue** : les 29 panneaux sont repliés par `panel.hidden = ! open` (JS), or une règle d'AUTEUR `display:block` l'emporte sur le `[hidden]{display:none}` de la feuille du navigateur — sans le `:not([hidden])`, les 29 panneaux d'identifiants s'affichaient tous dépliés, **au pouce uniquement**. ⚠️ **SECOND DÉFAUT, indépendant, trouvé en chemin et corrigé** : la rotation du chevron portait sur la **cellule** (41 × 68 px) ; pivotée de 90° elle devient 68 × 41 et dépassait de **13 px** le bord de la ligne — 13 px de défilement horizontal sur **GRAND écran**, dès qu'un panneau s'ouvrait, donc là où le correctif mobile ne s'applique même pas. Prouvé par neutralisation (`transform:none` ramenait 13 px à 4). La rotation porte désormais sur un glyphe (`.aod-cd-carrier-chev__g`) ; effet secondaire voulu : centrage vertical et rotation ne se disputent plus la même propriété `transform` au pouce. 🚨 **ET CE DÉFAUT DIT CE QUE LA PORTE NE SAVAIT PAS FAIRE — c'est le vrai produit de cette clôture.** `rtl-mobile.js` mesurait les tableaux tels que la page les sert, donc **panneaux FERMÉS** : un état sur deux, et le défaut du chevron ne vivait que dans l'autre. Elle en **déplie** désormais un à chaque passage, mesure le tableau ouvert et les cibles tactiles que l'ouverture RÉVÈLE (le bouton « Tester la connexion » fait 0 px de haut fermé, donc la sonde de 46.6 l'ignorait à juste titre), avec un **témoin** : 3 panneaux attendus, sans quoi un clic devenu inopérant rendrait le vert du tableau fermé. Et `TABLES_EXEMPTES` a disparu : **la porte ne porte plus aucune exception.** Vérifié : **0 px** de débordement à 390 / 700 / 1440 px, FR **et** AR, panneau ouvert **et** fermé (700 px est mesuré exprès : c'est entre les deux points de rupture, 680 pour les champs et 780 pour le tableau, là où la règle nouvelle croise l'ancienne). Ligne à **85 px** de haut au pouce. Desktop inchangé, assertion à l'appui (la ligne y reste sur UNE ligne, l'en-tête y reste visible). Porte : `bash tools/recette/run.sh rtl` — 27 tableaux mesurés, 530 cibles, 3 panneaux dépliés, 0 échec | `class-aod-cd-section-shipping.php` · `dashboard.css` (`.aod-cd-carriers`) · `tools/recette/rtl-mobile.js` | Basse | Soldée hors phase, en clôture de dette : aucun handler AJAX, aucune capability, aucune requête, aucun schéma de données — du chrome, comme les Phases 45 et 46 |
| ✅ D33 | ~~**La non-régression de la porte `landing` ne s'exécute plus sur un clone semé avec le catalogue de démonstration**~~ — **SOLDÉE le 2026-08-01** (option 1, la recommandée). Relevé le 2026-07-30 en soldant le rouge de sélection du témoin (même passage). Les 4 contrôles « un produit SANS page de vente s'affiche encore comme une fiche produit normale, avec son formulaire COD » exigent un produit publié sans landing ; `dev-seed-catalogue.php` en allume une sur **ses deux** produits, et un clone de développement n'en a pas d'autre. **Mesuré** : 2 produits publiés, 2 `_aod_landing_enabled=1`, `PLAIN_PID` vide ⇒ bloc retiré | `tools/recette/run.sh` (sélection) · `landing-produit.js` (bloc) · `scripts/dev-seed-catalogue.php` (cause) | Basse | ⚠️ **Ce n'est PAS un trou de production** : un vrai clone client a des produits ordinaires, le bloc s'y exécute. C'est un artefact de l'**outillage de dev**, et c'est pourquoi il est consigné plutôt que traité dans la foulée. 🚨 **Et le retrait est désormais ANNONCÉ** (`ⓘ Non-régression RETIRÉE … ce n'est pas un succès`) : le défaut coûteux n'était pas l'absence de couverture, c'était son invisibilité — la porte concluait « la page de vente respecte les règles » sans avoir joué ces 4 contrôles. Trois façons de le fermer, à arbitrer : (1) la porte sème son propre produit témoin jetable puis le purge — **précédent existant**, `run.sh cod` le fait déjà ; (2) le catalogue de démonstration laisse un de ses deux produits sans landing — affaiblit la démo, qui montre justement deux formes d'urgence ; (3) statu quo assumé, le retrait étant lisible. Recommandation : (1) — **c'est l'option retenue.** ✅ **Ce qui a été livré** : `scripts/recette-seed-landing.php` (create/cleanup, marque `_aod_seed_landing`, purge idempotente ne visant QUE la marque) ; le bloc `landing` de `run.sh` sème le témoin au lieu de le chercher — l'ancienne sélection `grep -vxF -f` sur le vivier a disparu — et `cleanup_landing` rejoint le trap `EXIT`, pour qu'une fatale en plein milieu ne laisse pas un produit jetable publié (leçon D26). Le témoin est délibérément **NU** (ni options, ni offres, ni landing) et la fixture **refuse de semer** si le drapeau `_aod_landing_enabled` se retrouvait posé — le défaut retourné à l'envers serait pire que l'original : 4 contrôles verts comparant une landing à une landing. Cliquet : `tests/unit/test-temoin-landing.php` (11 assertions, **7 mutations éprouvées au rouge**, chacune ne faisant rougir que l'assertion visée). 🚨 **ET LE CHIFFRE DE CETTE DETTE NE SE REPRODUISAIT PLUS AU MOMENT DE LA SOLDER** — mesuré avant de coder : **2 produits publiés, 1 seule landing**, donc un témoin parfaitement disponible et les 4 contrôles qui s'exécutaient. La dette n'était pas fausse pour autant : la cause est bien dans la source (`dev-seed-catalogue.php` lignes **611** et **917** allument les deux), mais le clone avait **dérivé** depuis — le sac avait perdu sa landing. La condition a donc été **ré-armée** (les deux produits repassés à `_aod_landing_enabled=1`, vivier vérifié vide) avant d'éprouver le correctif : les 4 contrôles se sont exécutés sur le témoin semé `108609`, et le clone est ressorti à 2 produits, le sac restauré à l'identique. *C'est la troisième dette en trois jours dont le chiffre a dû être ré-examiné (cf. `D31`, `D32`) — mais la leçon n'est pas la même : celles-là étaient FAUSSES, celle-ci était VRAIE et n'était plus reproductible. Une dette adossée à l'état d'un clone doit être ré-armée avant d'être crue, ni tenue pour fausse, ni tenue pour acquise.* ⚠️ **Ce que le cliquet ne prouve pas, et qui le prouve** : il garde le CÂBLAGE (la porte sème, purge, et ne cherche plus). Que le témoin semé s'affiche bien en fiche produit ordinaire avec son formulaire COD reste le travail de `bash tools/recette/run.sh landing`, dans un vrai navigateur — c'est écrit dans l'en-tête du fichier de test plutôt que laissé à deviner. |
| ✅ D34 | ~~**Le panneau du panier se déclare modale sans l'être**~~ — **SOLDÉE le 2026-08-02**, le jour même, en construisant le panneau de CHOIX : celui-ci est une SECONDE modale, donc il fallait une mécanique de boîte correcte — et une seule, partagée. 🚨 *Une dette d'accessibilité se solde mieux en ajoutant la surface qui la rendait intenable qu'en la corrigeant seule : deux modales imposent le corps unique qu'une seule tolérait de ne pas avoir.* Livré : le fond passe en `inert` **et** le cycle de tabulation est fermé à la main — mesuré, `inert` seul ne suffit pas (au-delà du dernier élément le focus part sur `BODY` puis dans la barre du navigateur, il ne revient pas au début) : le premier protège la PAGE, le second boucle la BOÎTE. ⚠️ Le bouton flottant n'est PAS épargné par l'inertie — l'avoir exclu le laissait atteignable au Tab depuis une modale (fuite mesurée au 6ᵉ Tab). Avant : sortie dès le 5ᵉ Tab, page ni inerte ni masquée. Après : **0 sortie sur 14 Tab dans les deux boîtes**, focus rendu à son origine, inertie retirée à la fermeture. Gardé par `run.sh panier` (2 assertions, éprouvées au rouge) — relevé le 2026-08-02, en auditant le panier COD. Il porte `role="dialog" aria-modal="true"`, mais **aucun piège de focus** : mesuré au navigateur, après le bouton « Commander » la tabulation sort dans la page derrière, qui n'est ni `inert` ni `aria-hidden` — alors que le défilement du corps EST bloqué (`overflow:hidden`). Un utilisateur au clavier ou au lecteur d'écran quitte donc une boîte annoncée comme modale et atterrit dans du contenu qu'il ne peut même pas faire défiler | `plugins-dev/aod-cod-form/assets/js/aod-cart.js` (open/close) | Basse | ⚠️ **Les portes d'accessibilité du dépôt ne pouvaient pas le voir** : `clavier` et `noms` auditent `/gestion`, pas le panier public. Le correctif est court (boucler le focus + `inert` sur le reste de la page), mais il touche l'ouverture/fermeture — à faire avec la porte `panier` sous la main, et à éprouver au rouge en retirant le piège |
| ~~D35~~ | ✅ **SOLDÉE le 2026-08-03.** L'écrêtage au-delà de `AOD_COD_Cart::MAX_ITEMS` (30 **lignes**, pas 30 unités) était **muet** : un panier de 31 lignes en montrait 30, la commande en portait 30, l'acheteur ne lisait rien. 🚨 **Le fond du défaut n'est pas la borne, c'est une justification qui a cessé d'être vraie sans que personne ne la relise** : 44.1 écrivait, pour écrêter plutôt que refuser, « aucune intention d'acheteur à trahir dans une forme que le formulaire ne produit jamais ». Exact d'un formulaire mono-produit ; le panier COD a rendu cette forme possible **trois phases plus tôt**. Livré : `add()` rend un verdict et refuse une ligne **neuve** au plafond — une **fusion** reste toujours permise, elle ne crée pas de ligne ; le plafond est **servi** au navigateur (`max_items`), jamais recopié ; le refus se dit sur les trois surfaces qui existaient déjà (le panneau de choix reste **ouvert**, la fiche passe son message en erreur, la carte de grille — qui n'a aucune surface — **ouvre le panier** avec la phrase, donc avec la ligne à retirer sous les yeux) ; et `ajax_items()` **annonce** ce qu'il a écrêté, avec sa **propre** phrase, pour les stores écrits avant cette version. La borne serveur **reste** : elle protège la requête, et ce chemin n'est plus atteint que par une charge forgée. Cliquet : 12 assertions dans `run.sh panier`, **3 red-proofs** — dont celui qui compte, un correctif qui **déborde** et refuse aussi les fusions. ⚠️ Le 2ᵉ red-proof a trouvé un défaut de la PORTE : `innerText()` sur un nœud absent lève, et la porte **s'arrêtait là** en taisant les assertions suivantes. *Un cliquet doit nommer ce qui casse, pas mourir dessus.* | `aod-cart.js` · `aod-cod-form.js` · `class-aod-cod-cart.php` · `class-aod-cod-form.php` (la justification périmée, corrigée sur place) | — | — |
| ~~D36~~ | ✅ **SOLDÉE le 2026-08-03.** Le store `localStorage` n'avait aucun écouteur `storage` : deux onglets ouverts sur la boutique divergeaient à l'écran. Corrigé par l'écouteur (il ne se déclenche que dans les onglets qui n'ont **pas** écrit — exactement ceux à rafraîchir), gardé par 3 assertions de `run.sh panier`, red-proof fait (badge figé à 1 pendant que le store en portait 2). 🚨 **LA DETTE SE TROMPAIT DANS LES DEUX SENS, et c'est la troisième fois en quatre jours** (cf. `D31`, `D32`). Elle affirmait que « le dernier qui écrit écrase l'autre » et qu'« un acheteur peut perdre un ajout » : **faux** — `read()` relit le store à chaque geste, donc rien ne se perd ni ne double, la fusion se fait correctement. Et elle concluait « sans perte d'argent » : **faux aussi, et dans le sens le plus coûteux** — le formulaire COD soumet `window.aodCart.items()`, c'est-à-dire le **store**, pas l'écran ; un onglet affichant « 1 article » envoyait une commande à deux lignes, et l'acheteur payait un article que sa page ne montrait nulle part. La dette décrivait donc un défaut de **donnée** qui n'existait pas, en déclarant inoffensif le défaut d'**affichage** qui, lui, touchait au paiement. *Une dette qui n'a pas été mesurée oriente vers la mauvaise piste — dans les deux sens.* | `aod-cart.js` (store) | — | — |
| ~~D37~~ | ✅ **SOLDÉE le 2026-08-03, le jour de son relevé.** **`wp plugin update` / `wp theme update` étaient IMPOSSIBLES sur tout clone**, alors que TEMPLATE.md §2 affirme depuis le 2026-07-28 que les archives de `library/` sont « des planchers, pas des versions figées » **parce que** ces commandes prennent le relais. `preflight.sh` ne réparait que `wp-content/uploads` — le symptôme qu'on avait vu (une image refusée). Or une mise à jour n'écrit pas là : elle crée `wp-content/upgrade-temp-backup/`, déballe dans `upgrade/`, puis remplace le dossier sous `plugins/`, tous en 755 propriété de l'utilisateur hôte donc fermés à Apache (uid 33). Un clone restait **à vie** sur la version du plancher, correctif de sécurité WooCommerce compris. 🚨 **Aucune porte ne pouvait le voir, et c'est le point à retenir** : les 1713 tests et les 22 portes éprouvent le **code**, jamais la **capacité à le mettre à jour**. Un dépôt entièrement vert peut être structurellement incapable de se mettre à jour. ⚠️ **Le second périmètre ne se devine pas** : réparer le dossier parent suffit à *créer* `woocommerce/`, pas à *remplacer* son contenu — mesuré, l'update passait « Retrait de l'ancienne version » puis échouait sur ~200 fichiers, parce que supprimer `patterns/banner.php` demande le droit sur `patterns/`. D'où `-R` sur `plugins/` et `themes/` seulement, et le dossier seul ailleurs. Le `-R` ne déborde pas sur le dépôt : `plugins/aod-*` sont côté hôte des points de montage **vides** (vérifié, `git status` identique avant/après). Éprouvé de bout en bout : WooCommerce 10.8.1 → **10.9.4**, Astra 4.13.4 → **4.13.8**, puis 1713 tests et 22 portes rejoués verts sur le nouveau socle. 🚨 *Une réparation ciblée sur le symptôme qu'on a vu ne couvre pas les autres chemins de la même cause* — trois symptômes sans rapport apparent (images refusées, journal muet en 32.2bis, mises à jour mortes) pour **un seul** bind-mount. | `scripts/preflight.sh` · `TEMPLATE.md` §2 et §3 | — | — |
| ~~D38~~ | ✅ **SOLDÉE le 2026-08-03, le jour de son relevé.** **Le paquet réellement mis en ligne avait 129 commits de retard.** `Deploiement/` datait du 25 juillet — ni les Phases 45→48, ni les trois correctifs du panier COD, dont celui où un second onglet faisait commander un article que la page n'affichait pas. Un `deploy/` du 13 juin traînait à côté, soit 150 Mo d'artefacts périmés, tous deux **fabriqués à la main**. 🚨 **Ce qui rend ce défaut structurel, et non une négligence** : ces dossiers sont **gitignorés**. `git status` propre, 1713 tests verts, 22 portes qui passent — et le fichier qu'on envoie au client a sept semaines. *Le dépôt ne peut pas dater ce qu'il ne suit pas.* Livré : `scripts/make-deploiement.sh`, générique (le domaine est un argument, jamais en dur), qui produit un **`MANIFESTE.md`** portant le commit d'origine et la commande d'une ligne qui dit combien de commits manquent au paquet. **Six gardes, toutes éprouvées au rouge** : domaine absent / donné en URL / donné avec un chemin (sans quoi la réécriture produirait `https://https://…` dans 100 000 lignes, en silence) ; arbre de travail sale (le manifeste désignerait un état qui n'a jamais existé) ; URL source subsistant dans le dump après réécriture ; et **comptes de recette présents en base** — celle-ci trouvée en s'apprêtant à bâtir pendant qu'une recette tournait : `run.sh` sème des comptes **confirmateur** dont le mot de passe transite par l'environnement, les livrer en production au milieu de fausses commandes ne se répare pas. ⚠️ `search-replace --export` et **jamais** `search-replace` : sans `--export`, la substitution écrit **dans** la base et casserait le clone de dev en silence. Et les deux plugins maison sont copiés depuis `plugins-dev/` — sous `wordpress/`, ce ne sont que des points de montage vides, les empaqueter donnerait un site sans `/gestion` ni formulaire COD, sans le dire. | `scripts/make-deploiement.sh` · `CHECKLIST-SITE-MASTER-ALGERIE.md` §13 · `ARCHITECTURE.md` §5 | — | — |
| D7 | **Traçabilité BMAD partielle** — **23 des 48 phases** n'ont aucun artefact dans `_bmad-output/implementation-artifacts/` : **6, 10, 11, 12, 15, 16, 17, 19, 20, 23, 26, 27, 28, 29, 30, 31, 32, 33, 38, 41, 45, 46, 48**. Les 25 autres en ont **95** au total, mais la couverture y est elle-même partielle (la Phase 5 n'a que `5-4`, la 14 que `14-1`) : **95 artefacts pour les 199 étapes de §C.3**. 🚨 **Le relevé d'origine n'en nommait que 7, et il n'a jamais été re-mesuré** — quatrième dette en quatre jours dont le chiffre était faux (cf. `D31`, `D32`, `D36`). Il datait du 2026-07-20 et décrivait donc, au mieux, un dépôt qui s'arrêtait à la Phase 30 ; les 16 phases livrées depuis n'ont pas été reversées dedans, si bien que la dette **rétrécissait à mesure que le trou grandissait**. La mesure, elle, se rejoue en une ligne — et c'est ça qui doit être consigné, pas son résultat : `for p in $(seq 1 48); do ls _bmad-output/implementation-artifacts/ \| grep -q "^${p}-" \|\| echo $p; done` (relevé du 2026-08-03) | `_bmad-output/` | Mineure | Ces phases ont été menées directement ; la ROADMAP §C.3 tient lieu de trace. ⚠️ Ce qui n'était PAS mineur, c'est le chiffre : une dette dont le compte ne se rejoue pas se relit comme un fait établi | | **✅ SOLDÉE le 2026-08-03 — par requalification, pas par remplissage.** ⛔ **Retro-écrire les 104 stories manquantes a été envisagé et REFUSÉ** : ce seraient des documents reconstitués après coup et présentés comme des archives. *Une trace fabriquée est pire qu'un trou déclaré — le trou, lui, ne ment pas.* Ce qui faisait dette n'était pas l'absence : c'était le **SILENCE** du dossier sur cette absence. `_bmad-output/` ressemble à une table des matières (des fichiers numérotés comme les étapes de §C.3, sans rien qui signale les trous), si bien qu'un lecteur cherchant la Phase 32 pouvait conclure qu'elle n'avait pas été faite — alors qu'elle est soldée, commitée et gardée. Livré : **`_bmad-output/README.md`**, qui dit ce que le dossier est, ce qu'il n'est pas, et renvoie §C.3 / `ARCHITECTURE.md` / `git log` comme seules autorités. 🚨 **Il ne porte AUCUN chiffre, seulement la commande qui le rejoue** — c'est le chiffre écrit en prose qui a rendu cette dette fausse pendant deux semaines (leçon `D25`). ⚠️ **Et aucune règle n'exige d'artefact, délibérément** : la convention accepte les étapes menées directement, une telle règle serait rouge en permanence, donc désarmée — et les six autres règles de `test-doc-derive.php` avec elle (leçon `D16`). |

> **Le fil commun de D1 et D6 est celui de la Phase 17** : un défaut qui ne casse
> rien ne se voit pas, il se lit. Une porte de recette ne prouve que ce qu'elle
> regarde — `mouvement` ne regarde pas le plugin public, et rien ne regarde les
> `phpcs:ignore`. Élargir une porte est toujours moins coûteux que de redécouvrir
> le trou par un bug client.
