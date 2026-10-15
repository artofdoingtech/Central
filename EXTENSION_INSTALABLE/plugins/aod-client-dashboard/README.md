# AOD Client Dashboard

Plugin WordPress/WooCommerce **maison** (Art Of Doing) — espace de gestion
**front-end** pour le client de la boutique. Le gérant administre ses commandes,
produits, livraison, pixels et notifications **sans jamais accéder à `wp-admin`**.
Sans licence, dupliquable à l'infini.

## Fonctionnalités

- 🧾 **Commandes** : liste filtrée par statut (avec compteurs), recherche (n°/nom/téléphone), pagination, détail/édition en modale (**articles + total modifiables**), changement de statut en AJAX, suppression groupée, envoi transporteur depuis la cellule « Suivi »
- 📦 **Produits** : CRUD complet, **variantes couleur** (prix / stock / photo par couleur), offres/paliers de quantité, **packs assortiment** (stock des composants décrémenté), prix de revient, galerie, upload d'image, recherche + pagination
- 📊 **Stock** : vue de couverture par produit — stock actuel, seuil d'alerte, vendus sur la période (filtre période), vitesse d'écoulement et **jours de couverture estimés** (∞ si aucune vente), badges **Rupture / Stock bas / OK**, urgences triées en premier ; packs avec stock des composants et nombre assemblable ; produits « non suivi » à part ; confirmateur restreint à ses produits affectés ; **ajustement rapide** inline (quantité, seuil, note d'inventaire datée — AJAX `aod_cd_save_stock`, réservé à `edit_products` : un confirmateur « stock » seul lit sans modifier) et **encart d'alertes** (ruptures + stock bas) en tête
- 🚀 **Page de vente** (par produit) : éditeur de **11 blocs** — héro, bénéfices, galerie, vidéo, texte, comparatif, avis, FAQ, urgence, formulaire de commande, réassurance (+ la barre « Commander » collante sur mobile) — à empiler, réordonner et déplier, **chaque texte en deux cases `Français` / `العربية`** (le rendu replie sur l'autre langue si l'une manque). Un interrupteur remplace la fiche produit par la page de vente **sans changer l'URL** ; éteint, il sert de brouillon (les blocs sont conservés). Refuse d'être allumé sans le bloc « Formulaire de commande ». **Partir d'un modèle** (8 blocs vides, structure possédée par le format) ou **copier la page d'un autre produit** (la copie arrive éteinte, la source n'est jamais touchée). *Nécessite [AOD COD Form](../aod-cod-form/), qui possède le format et le rendu.*
- 🏷️ **Catégories** : CRUD avec hiérarchie (parent), image et compteur
- 🚚 **Livraison** : tarifs des 58 wilayas (Domicile / Stop-desk), livraison gratuite, transporteurs + envoi automatique — *réutilise le plugin [AOD COD Form](../aod-cod-form/)*
- 📍 **Suivi commande** : tableau des colis expédiés (états transporteurs normalisés, dernière MAJ), timeline d'historique en modale, « Actualiser tout »
- 🏠 **Accueil** : 3 onglets de statistiques gouvernés par capability — **Revenus** (gérant uniquement : CA, panier moyen, marge via prix de revient, encaissées / en attente / annulées, **top-produits** et **graphe d'évolution du CA**, le tout **filtrable par produit** — seules les lignes du produit comptent — **et par confirmateur** via `_aod_confirmed_by`), **Livraison** (cap `aod_cap_stats_delivery` : taux **livré / en transit / retourné / échec** sur les colis expédiés, ventilation **par transporteur et par wilaya** — noms FR/AR —, **délai moyen de livraison** depuis l'historique transporteur, **colis bloqués** sans événement depuis > 7 jours ; un confirmateur ne voit que les commandes de ses **produits affectés**) et **Confirmation** (cap `aod_cap_stats_confirm` : **taux de confirmation**, **délai moyen création → confirmation** via la traçabilité `_aod_confirmed_by/_at`, **ventilation par confirmateur** — un confirmateur ne voit que **sa propre ligne**, le gérant voit tout — et commandes confirmées **« non attribuées »** d'avant le suivi d'équipe) — sous une **barre de filtres commune** (produit, confirmateur, période Jour / Semaine / **plage libre**) dont l'état persiste en query-string ; agrégats en transients courts (5 min) invalidés à chaque écriture de commande, filtrage **côté SQL** compatible HPOS **et** CPT hérité
- 🎯 **Pixels & Tracking** : Meta, TikTok, Snapchat, Google Ads
- 💬 **WhatsApp** : notification de commande via CallMeBot + message test
- 👤 **Mon compte** : le gérant change lui-même son nom, son e-mail et son mot de passe + **zone de réinitialisation boutique** (phrase de confirmation exacte)
- 🛡️ **Rôle restreint** « Gestion Boutique » : capacités e-commerce uniquement, **aucune** capacité dangereuse (plugins, thèmes, réglages, utilisateurs, cœur)
- 🌐 **RTL / traduisible** — text domain `aod-client-dashboard`, traduction arabe quasi complète fournie

## Accès & sécurité

- Interface plein écran à l'URL **`/gestion`** (prend la main sur le thème).
- Le rôle `aod_shop_client` est **bloqué hors de `wp-admin`** (redirigé vers `/gestion`), barre d'admin masquée, page de connexion marquée au logo de la boutique.
- Sécurité par **capabilities**, pas par masquage d'UI : la liste `FORBIDDEN` retire explicitement toute capacité pouvant casser le site, et un *resync* la maintient après les mises à jour de WooCommerce.
- Tous les appels AJAX vérifient **nonce + capability**.
- Compatible **HPOS** (High-Performance Order Storage).

## Utilisation

### Créer le compte du client (administrateur)

`Utilisateurs → Accès Gérant` → renseigner identifiant + mot de passe (un mot de
passe fort est pré-rempli). Le compte est créé avec le rôle « Gestion Boutique » ;
le client se connecte normalement et arrive directement sur `/gestion`.

> Alternative manuelle : créer un utilisateur et lui attribuer le rôle **Gestion Boutique**.

### Côté client

Le gérant ouvre **`/gestion`** (il atterrit sur l'**Accueil**) et navigue dans
les 12 sections via le menu latéral (Accueil, Commandes, **Commandes
abandonnées**, Produits, Stock, Catégories, Livraison, Suivi commande,
Pixels & Tracking, WhatsApp, Équipe, Mon compte).

Une 13ᵉ section, **Page de vente**, n'est **pas** dans le menu : elle appartient à
un produit et s'ouvre depuis sa ligne dans **Produits** (`/gestion/landing?produit=…`).
Toutes les actions (statut, produit, réglages) sont instantanées (AJAX).

Depuis la section **Équipe**, le gérant crée des comptes **Confirmateur** à
permissions à la carte (cases à cocher = capabilities par utilisateur) : un
confirmateur ne voit et ne peut faire que ce qui lui est coché — tout handler
AJAX revérifie la capability côté serveur (403 sinon). Il peut aussi
**affecter des produits** à un confirmateur : celui-ci ne voit, n'ouvre et ne
confirme alors que les commandes contenant au moins un de ses produits
(filtrage SQL de la liste + refus 403 sur accès direct ; aucun produit coché
= tous les produits ; le gérant n'est jamais filtré).

## Dépendances

- **WooCommerce** : requis (le plugin se désactive proprement sinon).
- **AOD COD Form** : *fortement recommandé*. Il fournit le statut « Confirmée »,
  les tarifs par wilaya et les transporteurs. Sans lui, ces parties se masquent
  proprement et un avertissement s'affiche côté admin.

## Structure

```
aod-client-dashboard/
├── aod-client-dashboard.php          # bootstrap : rôle, /gestion, dépendances
├── uninstall.php                     # suppression du plugin : retire le rôle + la capability
├── includes/
│   ├── class-aod-cd-roles.php        # rôles (gérant + confirmateur), caps custom, resync
│   ├── class-aod-cd-access.php       # blocage wp-admin, redirections, page login
│   ├── class-aod-cd-dashboard.php    # coque /gestion, routeur, sections + AJAX
│   ├── class-aod-cd-section-home.php # section Accueil : onglets stats + filtres communs
│   ├── class-aod-cd-section-stock.php # section Stock : couverture, badges, packs
│   ├── class-aod-cd-section-team.php # section Équipe : CRUD des confirmateurs
│   ├── class-aod-cd-section-landing.php # section Page de vente : éditeur de blocs (hors menu)
│   ├── class-aod-cd-section-products.php # section Produits : CRUD, variantes, offres, packs
│   ├── class-aod-cd-section-abandoned.php # section Commandes abandonnées (Phase 11)
│   ├── class-aod-cd-customize.php    # accent du Personnalisateur → charte teintée (Phase 15)
│   ├── class-aod-cd-icon.php         # catalogue fermé d'icônes vectorielles (Phase 15)
│   ├── class-aod-cd-account.php      # page admin « Accès Gérant » (création de compte)
│   └── class-aod-cd-lang.php         # bilingue FR ⇄ AR (cookie aod_lang, RTL)
├── assets/
│   ├── css/dashboard.css             # styles (RTL-aware)
│   ├── js/dashboard.js               # AJAX (statuts, produits, réglages, modale)
│   └── icons/                        # SVG Phosphor embarqués (cf. assets/icons/README.md)
└── languages/
    ├── aod-client-dashboard.pot      # gabarit d'extraction
    ├── aod-client-dashboard-ar.po    # traduction arabe (source)
    └── aod-client-dashboard-ar.mo    # traduction arabe (compilée)
```

## Internationalisation

L'espace de gestion est **bilingue FR ⇄ AR**. Un bouton dans l'en-tête (`AOD_CD_Lang`,
`includes/class-aod-cd-lang.php`) bascule la langue ; le choix est mémorisé dans le
cookie `aod_lang` (1 an), **partagé avec le sélecteur de la partie publique** — le
gérant retrouve donc la même langue côté boutique et côté gestion. L'arabe passe
automatiquement en RTL (`dir="rtl"` via `is_rtl()`).

> À défaut de bouton, le locale WordPress est respecté (profil du gérant → Langue → العربية).

### Régénérer les traductions après modification des chaînes

**Lance ce script à chaque ajout/modification de texte** (PHP `__()/_e()…` ou clés
`window.AOD_CD` injectées vers le JS). Il extrait, fusionne (en préservant l'existant),
recompile le `.mo` et **liste ce qu'il reste à traduire** :

```bash
plugins-dev/aod-client-dashboard/bin/make-translations.sh
```

Puis édite `languages/aod-client-dashboard-ar.po` pour les chaînes signalées « à
traduire » et relance le script (la traduction arabe reste manuelle). Détection
auto de wp-cli (hôte ou conteneur Docker `wp_cli_$PROJECT_SLUG`, déduit du `.env`
du dépôt) ; `--strict` sort en erreur
s'il manque des traductions (utile en CI/hook).

> ⚠️ Oublier cette étape = des libellés qui restent en français quand on bascule
> en arabe. C'est la cause classique d'un dashboard « à moitié traduit ».

## Développement local

Le dossier est monté en direct dans le conteneur WordPress
(`./plugins-dev/aod-client-dashboard` → `/var/www/html/wp-content/plugins/aod-client-dashboard`),
donc **toute édition depuis le PC est live** (rafraîchir la page ; bumper la
version du plugin force le rechargement du CSS/JS via `?v=`).

## Roadmap (idées d'évolution)

Les grands chantiers sont planifiés **par phases et étapes** dans
**`docs/ROADMAP-AMELIORATIONS.md`** à la racine du dépôt (verdicts + plan
d'implémentation + suivi d'avancement). Ce plugin porte les **Phases 1 à 3, 5, 9,
11, 15 à 17, 20 et 21**, plus l'éditeur de la Phase 6 :

- **Phase 1** — rôles « Confirmateur » à permissions granulaires (**7 capabilities**
  à cocher — 6 à l'origine, `aod_cap_abandoned` ajoutée en Phase 11), section
  « Équipe », affectation de produits, traçabilité
  `_aod_confirmed_by/_at`

  > 🔒 **Périmètre effectif des 7 capabilities — Phase 21.** Une capability ouvre
  > une **section**, et chaque handler AJAX de cette section doit exiger **la même**.
  > `test_carrier` faisait exception (`manage_woocommerce`) : un confirmateur
  > « tarifs » pouvait importer des tarifs mais pas tester la connexion. Corrigé, et
  > **gardé par `tests/unit/test-permissions-sections.php`**, qui échoue si une
  > garde diverge — cap trop **forte** (mur invisible au gérant, qui satisfait
  > toutes les gardes) comme trop **faible** (trou de sécurité).
  >
  > Deux divergences restent **voulues** : `delete_orders` (supprimer > confirmer)
  > et `save_stock` (`aod_cap_stock` ouvre l'écran en **lecture seule**). Elles sont
  > déclarées **avec leur raison** dans la table du test.
  >
  > ⚠️ Un confirmateur n'a **pas** de section « Mon compte » : *le gérant gère les
  > mots de passe* (arbitré le 2026-07-21), et les change depuis « Équipe ».
- **Phase 2** — accueil 3 onglets de statistiques (Revenus / Confirmation /
  Livraison) avec filtres produit / confirmateur / période — **livrée**
  (étapes 2.1 à 2.5 : socle + les 3 onglets + i18n/recette FR-AR-RTL-mobile
  de phase)
- **Phase 3** — page Stock (couverture estimée, alertes, ajustement rapide) —
  **livrée** (étapes 3.1 à 3.3 : vue + ajustement/alertes + i18n/recette
  FR-AR-RTL-mobile de phase)

- [x] Statistiques enrichies : filtre par période, top-produits, graphe d'évolution du CA ✓
- [x] Catégories (hiérarchie + image) ✓
- [x] Suivi commande (lecture du tracking transporteurs) ✓
- [x] Édition de commande : ajout d'articles + total modifiable ✓
- [ ] Variantes au-delà de la couleur (taille, etc.) avec stock propre
- [ ] Badge « nouvelles commandes » en temps réel
- [ ] Section d'aide / onboarding du client

