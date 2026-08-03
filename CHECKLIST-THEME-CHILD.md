# Checklist — child theme d'un nouveau clone (correctifs à rejouer)

> **Quand l'utiliser.** Dès que tu **appliques / construis le child theme** d'un nouveau
> projet (cf. TEMPLATE.md §4–§5). Ces correctifs sont **génériques** mais vivent dans le
> thème (qui change à chaque client), donc ils ne peuvent pas être livrés « tout faits »
> par le template : **déroule cette liste sur le child theme du clone**.
>
> Le socle (plugins `aod-*`, COD, dashboard, i18n) est déjà corrigé dans le template
> (backport du 1er clone). Ici on ne traite **que** les pièges côté thème.

---

## C1. ⭐ Prix inversé — symbole DZD arabe `د.ج` (à faire sur TOUT clone en DZD)

- **Symptôme** : prix affiché `د.ج 4 900` au lieu de `4 900 د.ج` sur les cartes produit
  (Accueil/Boutique) et la fiche, **même en français**.
- **Cause** : la devise DZD a un symbole **arabe** (RTL fort). WooCommerce enveloppe le
  prix dans `<bdi>` sans `dir` → `dir="auto"` déduit la direction du 1er caractère fort
  (le `د`) → tout le bloc bascule en RTL, symbole à gauche du nombre.
- **Correctif** — dans `inc/woocommerce.php` (ou `functions.php`) du child theme :

```php
// Force l'ordre nombre → symbole (évite l'inversion bidi du symbole DZD arabe).
add_filter( 'wc_price', function ( $html ) {
    return str_replace( '<bdi>', '<bdi dir="ltr">', $html );
}, 10, 1 );
```

- **Filet CSS** — dans la feuille principale du thème :

```css
.woocommerce-Price-amount,
.woocommerce-Price-amount bdi { direction: ltr; unicode-bidi: isolate; }
```

- **Vérifier** : ouvrir la Boutique en FR **et** en AR → `4 900 د.ج` (nombre puis symbole)
  dans les deux cas. Penser au bump de version CSS + purge de cache.

---

## C2. Écran blanc en arabe (RTL) sur mobile

- **Cause** : éléments masqués via `left:-9999px`. En **RTL**, ce décalage horizontal crée
  un **débordement** de page → écran blanc / scroll cassé sur mobile.
- **Règle** : ne **jamais** masquer hors écran par `left:-9999px`. Masquer par le **haut**
  (`transform: translateY(-200%)`) ou via un *visually-hidden* propre :

```css
.skip-link  { transform: translateY(-200%); transition: transform .2s ease; }
.skip-link:focus { transform: translateY(0); }

.visually-hidden { position:absolute; width:1px; height:1px; overflow:hidden;
                   clip:rect(0 0 0 0); clip-path:inset(50%); white-space:nowrap; }
```

- **Vérifier** : passer le site en AR, recharger sur viewport mobile → pas d'écran blanc,
  pas de scroll horizontal.

---

## C3. Menu mobile (cliquabilité)

> ✅ **L'assignation du menu est désormais automatique.**
> `scripts/pages-publiques.sh` assigne « Menu principal » à **tous** les emplacements
> déclarés par le thème (sauf ceux de compte). Le piège historique — sans `mobile_menu`,
> Astra retombe sur `wp_page_menu()` et **réexpose Cart / Checkout / My-account** — est
> donc fermé par le template. **Relance quand même le script après avoir activé le thème
> du client** : les emplacements changent d'un thème à l'autre.

- Reste à ta charge : le menu off-canvas/centré doit être **cliquable**. Vérifier `z-index`
  et `pointer-events` (les liens doivent passer au-dessus d'un backdrop `backdrop-filter`).
- **Vérifier** : `bash tools/recette/run.sh public` — et à l'œil, sur mobile.

---

## C4. Libellés de menu en arabe

> ✅ **Traité par le template pour les 3 pages du socle.**
> `AOD_COD_Pages::translate_menu_labels()` retraduit Accueil / Boutique / Contact via
> gettext, en reconnaissant les pages à leur meta `_aod_page_key` (jamais à leur libellé).
> Un libellé personnalisé dans Apparence → Menus est respecté et **n'est pas** écrasé —
> mais il devient alors ta responsabilité de le traduire.

- Reste à ta charge : toute entrée de menu **ajoutée par le thème** (ex. un lien du pied
  de page propre au thème).

---

## C5. Titre de page affiché deux fois (Accueil / Contact)

- **Symptôme** : la page d'accueil montre « Accueil » en gros, puis le nom de la boutique
  juste en dessous. Deux `<h1>` sur la même page — faute d'accessibilité **et** de SEO.
- **Cause** : Accueil et Contact rendent leur **propre** titre (shortcodes `[aod_home]` /
  `[aod_contact_form]`), et le thème rajoute par-dessus le titre de la page WordPress.
- **Astra** : déjà traité par le template (filtre `astra_the_title_enabled`).
- **Autre thème** : masquer le titre sur ces 2 pages, dans les réglages du thème (souvent
  un réglage par page) ou via le filtre équivalent du thème.
- **Vérifier** : un seul `<h1>` sur l'Accueil et sur Contact.

---

## C6. Chaînes du thème restées en anglais (en FR ou en AR)

- **Symptôme** : des chaînes du **thème** restent en anglais — `OUT OF STOCK` sur une carte
  produit, en plein site français ou arabe.
- **Cause** : le pack de langue du thème n'est pas installé. `setup.sh` s'en charge
  (`wp language theme install --all fr_FR ar`), mais **le téléchargement peut échouer
  silencieusement** — un aléa réseau suffit, et `--all` continue sans le dire.
- **Vérifier que le pack est bien là** (et pas seulement « disponible ») :

```bash
docker compose exec wpcli wp language theme list <slug-du-theme> --format=csv | grep -E '^(ar|fr_FR),'
#   ar,installed   ✅
#   ar,uninstalled ❌  → disponible mais PAS installé : relancer l'installation
#   (absent)       ⚠️  → le thème n'a pas de traduction arabe du tout
```

```bash
docker compose exec wpcli wp language theme install <slug> fr_FR ar
```

- **Si le thème n'a réellement pas de pack arabe** — traduire les quelques chaînes visibles
  dans le `.po` du **child theme**, ou les surcharger par un filtre `gettext` ciblé sur son
  text-domain.
- **Vérifier** : passer le site en AR et parcourir Accueil / Boutique / fiche produit — il
  ne doit rester **aucun mot anglais**.

> Pour mémoire : **Astra a bien un pack arabe** (`غير متوفر في المخزون`). Si tu vois
> « OUT OF STOCK », c'est que l'installation du pack a échoué — pas qu'il n'existe pas.

---

## C7. Icône panier dans l'en-tête du thème

- Beaucoup de thèmes WooCommerce affichent une **icône panier** dans l'en-tête, sans passer
  par aucun hook WooCommerce (ils rendent leur propre markup).
- **Astra** : déjà traité par le template (filtre `astra_woo_header_cart_icon`).
- **Autre thème** : la désactiver dans les réglages du thème, ou par son filtre. Le panier
  est **déjà impossible à remplir** (`AOD_COD_No_Tunnel` débranche l'action serveur), donc
  ce n'est pas une faille — mais une icône panier sur une boutique sans panier est une
  promesse mensongère faite à l'acheteur.
- **Vérifier** : aucune icône panier, en desktop **et** en mobile.

---

## C8. Dropdown de tri boutique (responsive)

- Le `<select>` natif de tri WooCommerce (`woocommerce_catalog_ordering`) déborde / est mal
  dimensionné sur mobile → l'habiller (menu custom ou CSS contrôlant largeur/placement).
- **Vérifier** : sur mobile, le tri ne déborde pas et reste utilisable.

---

## C9. Largeur du contenu (marges latérales sur grand écran)

- Le thème plafonne la largeur du contenu (Astra : **1200 px** par défaut). Sur un
  écran large, ça laisse de grandes bandes vides sur les côtés — mesuré à 1920 px :
  **340 px de vide de chaque côté**, encore 100 px à 1440. Pire sur un écran
  **2560 px** : le contenu de 1400 laisse **~580 px de vide de chaque côté**, ce
  qui donne l'impression d'une colonne étroite « perdue » au milieu de l'écran.
  La fiche produit, avec sa galerie et son formulaire côte à côte, s'y sent à l'étroit.
- ✅ **Posé AUTOMATIQUEMENT sur les clones neufs** : `setup.sh` (étape **5bis**)
  écrit **1600 px** par défaut dès que le thème actif est **Astra** (ou un enfant
  d'Astra) — le correctif CSS voyage déjà dans le plugin, mais la largeur est une
  **option en base** qui, elle, ne part pas avec `git clone` ; on la bake donc dans
  le setup pour que chaque clone en hérite. Idempotent et **non destructif** : si
  une largeur est déjà réglée, il n'y touche pas.
- **La retoucher / la reposer à la main** dans le Personnalisateur → *Mise en page →
  Contenu / Conteneur* (Astra : « Largeur du contenu »), ou en ligne de commande.
  1600 réduit sensiblement les bandes latérales sur grand écran sans étaler le texte
  (1400 restait trop centré sur un moniteur large).
  ⚠️ **En ligne de commande, ce n'est PAS un `theme_mod`.** Astra stocke ses
  réglages dans l'option dédiée **`astra-settings`**, pas dans les theme mods —
  `set_theme_mod("site-content-width", …)` s'écrit dans le vide (vérifié : sans
  effet, le conteneur reste à 1200). La bonne manip :
  ```bash
  docker compose exec wpcli wp eval '
    $s = get_option("astra-settings"); if(!is_array($s)) $s = array();
    $s["site-content-width"] = 1600; update_option("astra-settings", $s);'
  ```
  Astra met son CSS dynamique **en cache** : si le changement n'apparaît pas,
  vider le cache (Personnalisateur *Publier* le régénère, ou purger
  `uploads/astra/`).
- **Overridable par clone** : 1600 est un **défaut générique** (comme DZD/COD posés
  par `setup.sh`), pas une valeur figée. L'owner peut la changer selon le thème
  déposé et son goût — `setup.sh` respecte alors son choix. Sous un thème **non-Astra**,
  l'étape 5bis est inerte : régler la largeur dans le Personnalisateur de ce thème.
  Elle touche les 3 pages, pas seulement la fiche.
- 🚨 **Élargir le conteneur SEUL ne suffit pas pour la fiche produit — et peut même
  l'empirer.** Le formulaire COD (`.aod-cod`) est plafonné à 560 px et **centré**
  (`margin: auto`, voulu pour l'empilement 601-921 px). Quand la colonne « résumé »
  s'étire (conteneur large), ce centrage fait **flotter** la carte au milieu de sa
  colonne : elle ne s'aligne plus sous le titre/prix (alignés au début) et « pend »
  dans le vide. Le correctif vit **dans le template** (générique, tous clones) :
  `aod-cod-form.css` ancre le formulaire au **début** de la colonne et le laisse
  remplir jusqu'à **640 px** dès **≥ 922 px** (2 colonnes Astra), en logique
  `margin-inline` → **correct en RTL** sans règle miroir. Rien à refaire par clone
  ici : seul le réglage de largeur ci-dessus est à poser.
- **Vérifier** : à 1440, 1920 **et 2560 px**, le contenu respire sans s'étaler et,
  sur la fiche produit, **le formulaire reste aligné sous le titre** (ne flotte pas),
  en **FR et AR** ; en mobile, rien ne change (la largeur est déjà à 100 %).

---

## C. Ancres et en-tête collant — à vérifier si le thème du clone en a un

Le template pose deux ancres sur la fiche produit : **`#aod-commander`** (le
formulaire — la page de vente y mène depuis la Phase 6) et **`#aod-options`** (le
bloc de choix des variantes — la grille y mène depuis le 2026-08-02, avec une
phrase de repère).

Un thème à **en-tête collant** (`position: sticky/fixed`) recouvre le haut de
l'écran : la cible d'une ancre atterrit **derrière** lui, et sur `#aod-options`
c'est précisément la phrase d'explication qui disparaît — au moment où elle sert.

Le plugin pose déjà `scroll-margin-block-start` sur l'ancre, mais la hauteur d'un
en-tête ne se devine pas depuis le plugin : elle appartient au thème du clone.
Dans le child theme :

```css
/* Hauteur RÉELLE de l'en-tête collant du thème, mesurée — pas copiée d'ici. */
#aod-options, #aod-commander { scroll-margin-block-start: 96px; }
```

> ⚠️ **Ne pas essayer de le régler en JavaScript.** Ça a été fait, mesuré, et
> retiré : un défilement calculé au chargement n'a jamais pris la main, à 120 ms
> comme à 700 ms — le saut d'ancre du navigateur arrive après et gagne. Deux
> acteurs pour un même défilement, c'est un de trop.

**Vérifier :** thème activé, panier COD allumé, depuis la Boutique appuyer sur
« Ajouter au panier » d'un produit **à variantes** → la phrase « Choisissez vos
options… » doit être **entièrement visible**, pas sous l'en-tête. Gardé côté
plugin par `bash tools/recette/run.sh panier` (assertion « ENTIÈREMENT dans
l'écran »), qui mesure avec le thème **du clone**.

## Rappels transverses (TEMPLATE.md)

- **3 pages** seulement : Accueil / Boutique / Contact. Aucune autre entrée de nav.
- **Jamais** panier / compte / wishlist côté acheteur. Achat = formulaire COD uniquement.
- **Branding** (logo + nom) via le **Personnalisateur**, jamais en dur.
- **Mobile-first** + **RTL** : tester en FR et AR avant de dire « fini ».
- Après toute chaîne UI ajoutée au thème : traduire l'AR dans le `.po` du thème.

---

### Checklist express
- [ ] C1 prix DZD : filtre `wc_price` + CSS, vérifié FR & AR
- [ ] C2 RTL mobile : aucun `left:-9999px`, pas d'écran blanc en AR
- [ ] C3 menu mobile : `pages-publiques.sh` relancé après activation du thème, liens cliquables
- [ ] C4 menu AR : entrées ajoutées par le thème traduites elles aussi
- [ ] C5 un seul `<h1>` sur l'Accueil et sur Contact
- [ ] C6 aucune chaîne du thème restée en anglais, en arabe
- [ ] C7 aucune icône panier dans l'en-tête (desktop **et** mobile)
- [ ] C8 tri boutique : responsive, ne déborde pas
- [ ] C9 largeur du contenu réglée (marges latérales OK à 1440/1920/2560), formulaire aligné sous le titre (ne flotte pas), FR & AR
- [ ] `bash tools/recette/run.sh public` → tout vert
- [ ] Rappels transverses respectés
