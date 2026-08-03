# TEMPLATE.md — Guide pour Claude Code

> **À lire en premier.** Ce dépôt est un **template e-commerce réutilisable**
> (WordPress + WooCommerce, COD / paiement à la livraison, Algérie). Il est
> **cloné** pour chaque nouveau client. Ce fichier explique ce que tu dois savoir
> avant de toucher au code — surtout pour la mission la plus fréquente :
> **construire les 3 pages publiques avec le thème fourni par le client.**

---

## 0. Ta mission la plus probable sur un clone

L'owner **dépose le thème choisi dans `library/themes/`** du projet (voir §4). À
partir de ce thème, tu dois **construire / habiller les 3 pages publiques** de
façon **élégante, moderne et attirante**, en respectant strictement les règles
ci-dessous. Si aucun thème n'est fourni, demande-le avant de commencer.

Le socle technique (plugins maison, COD, dashboard gérant, i18n) **existe déjà et
ne change pas**. Ton travail côté public = **présentation + intégration du thème**,
pas réécriture de la logique métier.

---

## 1. Les règles d'or (ne jamais les enfreindre)

1. **C'est un template générique.** Tout choix est hérité par tous les futurs
   clones. → Rester **générique, minimal, reproductible**. Pas de valeurs client
   en dur (nom, couleurs, clés API) : tout se configure depuis l'interface
   (Personnalisateur WordPress + espace `/gestion`).
2. **Aucune fonctionnalité superflue.** N'ajoute que ce qui est demandé.
   « Je veux pas des choses inutiles ou de plus. »
3. **Côté public = exactement 3 pages :** **Accueil**, **Boutique**, **Contact**.
   La barre de navigation ne montre **que ces 3 entrées**.
4. **JAMAIS de panier ni de compte** côté acheteur. Pas d'icône Woo cart /
   checkout / my-account / wishlist. L'achat passe **uniquement** par le
   formulaire COD sur la page produit. *(Le **panier COD** de la Phase 35 —
   livrée, **éteint par défaut** — ne contredit pas cette règle : ce n'est PAS
   le panier Woo. `AOD_COD_No_Tunnel` reste entièrement armé, aucun compte ;
   c'est un bouton flottant + panneau en `localStorage` dont la seule sortie
   est le même formulaire COD — le gérant l'active depuis
   `/gestion → Livraison`. Cf. §5.)*
5. **Bilingue FR ⇄ AR + RTL.** Toute chaîne visible doit être traduisible
   (voir §7). L'arabe doit fonctionner sur la zone publique **et** le formulaire
   de contact.
6. **Mobile d'abord.** La majorité du trafic DZ est mobile : teste tout en
   responsive avant de considérer une page « finie ».

---

## 2. Ce que contient le template

### Deux plugins maison (Art Of Doing) — `plugins-dev/`

| Plugin                                                      | Rôle                                                                                | Notes                                                                                                                                                                                                                                                             |
| ----------------------------------------------------------- | ----------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [`aod-cod-form`](plugins-dev/aod-cod-form/)                 | Formulaire de commande COD sur la page produit (sans panier ni compte)              | 58 wilayas / 1541 communes, tarifs livraison Domicile/Stop-desk, transporteurs **Yalidine / Yalitec / Noest / E-com Delivery (ex-ZR) / Maystro / EcoTrack (+23 white-labels)** avec envoi auto + suivi colis (cron 2 h), anti-bot **5 couches, dont 4 actives sur un clone neuf** (honeypot, time-trap — depuis la 32.3, **omettre** le champ est aussi disqualifiant que mal le remplir —, rate-limit IP, 1 commande / 24 h par téléphone, puis **Turnstile** — ⚠️ **la 5ᵉ n'existe pas tant qu'on n'a pas saisi les clés** ; cf. `docs/RUNBOOK-ANTI-BOT-COD.md`), pixels (Meta/TikTok/Snap/Google), WhatsApp (CallMeBot), suivi prospects, FR/AR + RTL. **Panier COD togglable** (Phase 35 — option `aod_cod_cart`, **OFF par défaut** : bouton flottant + panneau `localStorage`, une commande à N lignes par le MÊME formulaire COD — jamais le panier Woo). Contient aussi le **switcher de langue FR/AR** public. |
| [`aod-client-dashboard`](plugins-dev/aod-client-dashboard/) | Espace gérant front-end `/gestion` (le client gère sa boutique **sans `wp-admin`**) | 12 sections : **Accueil** (3 onglets de stats), Commandes (édition articles + total, boutons **Appeler / WhatsApp** par ligne et en modale — Phase 34), **Commandes abandonnées** (relance des prospects non validés), Produits (CRUD + variantes couleur, offres, packs), **Stock**, Catégories, Livraison (+ import des tarifs par API), **Suivi commande** (états transporteurs normalisés), **Pixels & Tracking**, WhatsApp, **Équipe** (CRUD confirmateurs + permissions à la carte), Mon compte (+ reset boutique sécurisé) — plus l'éditeur **Page de vente** (ouvert depuis un produit). Rôles « Gestion Boutique » et « Confirmateur » (capabilities par utilisateur), HPOS, FR/AR + RTL complet.                                                                                                                     |

Ces plugins sont **bind-montés** dans le conteneur → toute édition dans
`plugins-dev/` est immédiatement live.

### Stack de dev (Docker) — `docker-compose.yml`

WordPress + MariaDB + phpMyAdmin + WP-CLI. Voir [`README-LOCAL.md`](README-LOCAL.md).

### Ce qui n'est PAS versionné

- `library/` : zips des **thèmes & plugins tiers** (poids + licences). `scripts/setup.sh`
  installe automatiquement tous les `.zip` qui s'y trouvent.
  ✅ **DEUX EXCEPTIONS, versionnées à dessein (2026-07-28)** —
  `library/plugins/woocommerce.*.zip` et `library/themes/astra.*.zip` : le
  **socle**. Sans lui, un `git clone` ne donne pas une application qui démarre
  (les deux plugins maison déclarent `Requires Plugins: woocommerce` et se
  mettent en veille : `/gestion` et la boutique n'existent pas). ~25 Mo, licences
  GPL, redistribution autorisée. `setup.sh` sait aussi les tirer de
  wordpress.org — ces archives rendent l'installation possible **hors ligne**.
  ⚠️ Ce sont des **planchers**, pas des versions figées : `wp plugin update` /
  `wp theme update` prennent le relais. Le **thème client**, lui, reste hors
  dépôt.
  🚨 **Ce relais a été FAUX sur tout clone jusqu'au 2026-08-03**, et la phrase
  ci-dessus l'affirmait quand même. Une mise à jour n'écrit pas dans `uploads/`
  (le seul dossier que `preflight.sh` réparait) : elle crée
  `wp-content/upgrade-temp-backup/`, déballe dans `upgrade/`, puis remplace le
  dossier sous `plugins/` — tous fermés à Apache par le bind-mount. `wp plugin
  update woocommerce` téléchargeait puis échouait, et un clone restait **à vie**
  sur la version du plancher, correctif de sécurité compris. Corrigé dans
  `preflight.sh` ; **il faut donc l'avoir lancé** pour que la phrase soit vraie.
  *Aucune porte ne pouvait voir ce défaut : les tests et la recette éprouvent le
  code, jamais la capacité à le mettre à jour.*
- `.env` : secrets (mots de passe BDD/admin) + ports. **À créer depuis `.env.example`.**
- Le **thème du client** : il arrive au moment du projet, pas dans le template de base.

---

## 3. Environnement de dev local (Docker)

> 🚨 **ISOLATION — à vérifier AVANT toute installation.** Chaque clone doit avoir
> sa **propre** stack. Les conteneurs sont nommés `wp_<service>_${PROJECT_SLUG}`
> (slug défini dans `.env`). Avant de lancer/installer quoi que ce soit :
> 1. `pwd` → tu es bien dans CE clone, pas dans le template ni un autre projet.
> 2. `docker ps -a --format 'table {{.Names}}\t{{.Label "com.docker.compose.project"}}'`
>    → repère les stacks existantes ; **ne touche jamais** aux conteneurs d'un autre projet.
> 3. `.env` : `PROJECT_SLUG` + `COMPOSE_PROJECT_NAME` **uniques**, ports libres
>    (`WP_PORT`/`PMA_PORT`) — sinon collision / installation dans le mauvais projet.
> 4. Lancer **`bash scripts/preflight.sh`** AVANT `docker compose up` : il refuse
>    de continuer (sortie ≠ 0) si le slug est resté au défaut, ou si un conteneur /
>    port est déjà pris par un autre projet. C'est le garde-fou automatique.
> 5. **Toujours** piloter via `docker compose exec <service>` **depuis le dossier
>    du clone** — JAMAIS `docker exec wp_cli_c1 …` (nom global = risque de viser un
>    autre projet). C'est l'erreur qui a déjà fait installer des plugins sur le
>    mauvais site.

Services (voir `docker-compose.yml`), à piloter depuis le dossier du projet :

- `wpcli` — WP-CLI : `docker compose exec wpcli wp <cmd>`
- `wordpress` — Apache (le site), `db` — MariaDB, `phpmyadmin` — phpMyAdmin

Accès (ports dans `.env`) : Boutique `http://localhost:${WP_PORT}` · Admin
`/wp-admin` · phpMyAdmin `http://localhost:${PMA_PORT}`.

**À savoir :**

- **HPOS actif** → le meta des commandes n'est **pas** dans `postmeta`. Ne pas
  utiliser `wp post meta get` pour une commande. Utiliser :
  `docker compose exec wpcli wp eval '$o=wc_get_order($id); echo $o->get_meta("_cle");'`
  Lister : `docker compose exec wpcli wp wc shop_order list --user=1 --fields=id,status`.
- L'hôte **n'a pas de PHP** : faire les `php -l` / lint **dans le conteneur**.
- 📦 **Limites de téléversement — `docker/php-aod.ini` (Phase 47.6bis).** L'image
  officielle ne charge **aucun** `php.ini` : `upload_max_filesize` valait donc le
  défaut compilé de PHP, **2M**, et une photo de produit ordinaire (2,8 Mo) était
  refusée pendant que la boutique promettait 5 Mo. Le fichier est **versionné** et
  monté sur **les deux** conteneurs (`wordpress` **et** `wpcli` — la suite de tests
  tourne dans le second, les vrais envois arrivent dans le premier ; n'en équiper
  qu'un rend l'autre menteur).
  ⚠️ **Un clone déjà installé doit recréer ses conteneurs** : `docker compose up -d`
  depuis le dossier du clone. Et si tu **modifies une valeur** du fichier, `up -d`
  ne suffit pas (config Compose inchangée = no-op, et PHP lit son `.ini` au
  démarrage) → `docker compose restart wordpress wpcli`.
  `setup.sh` **refuse de conclure** si PHP plafonne sous la limite annoncée par la
  boutique, et écrit un `.user.ini` pour l'hébergement réel.
- **Si un envoi d'image depuis `/gestion` échoue** (« le fichier n'a pas pu être
  envoyé ») : `wordpress/` est bind-monté, et un dossier créé **côté hôte** naît
  en 755 propriété de l'utilisateur hôte — Apache (uid 33) ne peut plus rien
  créer dedans. `preflight.sh` répare (`chmod -R a+rwX` sur `uploads/`) et
  `setup.sh` le **sonde** sous l'uid d'Apache : il refuse de terminer si les
  téléversements sont morts.
  ⚠️ **Ce même chmod ne couvrait QUE `uploads/`, et ça tuait les mises à jour** —
  une TROISIÈME morsure du bind-mount, trouvée le 2026-08-03 : `wp plugin update`
  écrit dans `wp-content/upgrade-temp-backup/`, `upgrade/` puis `plugins/`, pas
  dans `uploads/`. Réparé dans `preflight.sh` (détail et périmètres en §2, note
  sur les « planchers »). 🚨 *Une réparation ciblée sur le symptôme qu'on a vu ne
  couvre pas les autres chemins de la même cause* — ici, trois symptômes sans
  rapport apparent (images refusées, journal muet, mises à jour impossibles) pour
  un seul bind-mount.
- 🔴 **Le même bind-mount a une SECONDE morsure, et celle-là ne se répare pas au
  `chmod`.** `get_filesystem_method()` ne conclut « direct » que si le
  propriétaire des fichiers du cœur **égale** celui d'un fichier créé par PHP.
  Ici le cœur appartient à l'utilisateur hôte (uid 1000) et PHP tourne en
  `www-data` (uid 33) : l'égalité est **structurellement impossible**. WordPress
  retombe alors sur `ftpsockets`, `WP_Filesystem()` rend `false`, et **tout ce
  qui passe par cette couche échoue en silence** — c'est ce qui rendait la
  journalisation (`AOD_COD_Log`, Phase 31.3) muette sur **toute requête web**
  alors qu'elle écrivait parfaitement sous WP-CLI. D'où
  `define('FS_METHOD','direct')` dans `WORDPRESS_CONFIG_EXTRA`
  (`docker-compose.yml`) : le `wp-config.php` de l'image officielle l'évalue à
  chaque requête, donc **les clones déjà installés sont couverts sans y toucher**.
  ⚠️ Ne concerne QUE cette pile de dev : sur un hébergement normal, propriétaire
  des fichiers et utilisateur PHP coïncident. Gardé par
  `bash tools/recette/run.sh journal`.
  🚨 **La leçon, elle, vaut partout : un test unitaire ne pouvait PAS voir ce
  défaut**, parce que `tests/run.sh` s'exécute dans le processus WP-CLI —
  précisément celui qui fonctionnait. *Éprouver une couche dans le contexte où on
  la développe plutôt que dans celui où elle sert, c'est se garantir un vert
  permanent.*
- **Pas d'internet sortant fiable** par défaut. Un bloc `dns: [8.8.8.8, 1.1.1.1]`
  dans `docker-compose.yml` débloque les appels API externes (Ecotrack…). Si un
  clone neuf échoue sur des appels externes, vérifier ce bloc en premier.
  → Conséquence : avec ce bloc DNS, `setup.sh` (§2bis) installe les **packs de
  langue** du cœur WP + WooCommerce (`fr_FR`, `ar`) et met le site en `fr_FR`.
  Sans réseau, ces packs manquent : WordPress reste en `en_US` et tout le cœur
  WP/Woo s'affiche en anglais (ex. l'historique d'une commande) — le rattraper à
  la main via `wp language core install fr_FR`.
  En revanche, la **traduction AR de nos propres chaînes** (`aod-*`) reste
  **manuelle** dans tous les cas : elle vit dans nos `languages/*-ar.po` (cf. §7).

---

## 4. Le thème du projet — fourni dans `library/themes/`

**Méthode :** l'owner **choisit lui-même** le thème et le **dépose dans le dossier
du projet**, sous `library/themes/`. Tu construis les 3 pages à partir de CE
thème-là. (Plus de bibliothèque externe à parcourir : un seul thème, local au clone.)

> Si aucun thème n'est présent dans `library/themes/`, **demande-le à l'owner**
> avant de commencer — ne choisis pas à sa place.

### Où le déposer

- **`library/themes/*.zip`** — dossier project-local, **gitignoré** (licences/poids),
  monté dans le conteneur à `/library` et **installé automatiquement par
  `setup.sh`** (`wp theme install`).
- Plugins tiers nécessaires au thème (Elementor, Hello…) : **`library/plugins/*.zip`**
  (même mécanisme d'installation auto).

### ⛔ Le thème doit être CLASSIQUE, pas un thème de blocs (FSE)

**C'est une contrainte dure, pas une préférence.** Un thème de blocs (Twenty
Twenty-Four/Five et la plupart des thèmes FSE) n'appelle pas `register_nav_menus()` :
il ne déclare **aucun emplacement de menu**, la navigation se construit à la main
dans l'éditeur de site.

Sur ce template, ça casse deux choses :

| | Conséquence |
|---|---|
| `pages-publiques.sh` ne peut plus assigner le menu | La nav n'est plus garantie à 3 entrées — et sur mobile, WordPress retombe sur `wp_page_menu()`, qui réexpose Panier / Commande / Mon compte. |
| Le switcher FR/AR s'injecte dans `wp_nav_menu()` | Il **disparaît**. Le site n'est plus bilingue. La règle 5 tombe. |

Vérifié : avec Twenty Twenty-Five, `wp menu location list` renvoie **zéro** emplacement.
`pages-publiques.sh` refuse désormais de continuer dans ce cas — il ne fera pas semblant.

**Thèmes classiques avec traductions FR + AR** (vérifié sur wordpress.org) :
**Astra** (celui du template), **GeneratePress**, **Neve**, **Blocksy**, **Kadence**.
⚠️ *Hello Elementor* et *Storefront* n'ont **pas** de traduction arabe.

> **Le thème du template lui-même est volontairement neutre** (Astra par défaut, sans
> aucune personnalisation). Le template n'impose aucun style : il fournit le socle
> fonctionnel. Le look arrive avec le thème que l'owner dépose par clone.

### Deux cas selon l'artefact fourni

1. **Vrai thème WordPress** (`.zip` Astra / GeneratePress / child theme… — **classique**)
   → `setup.sh` l'installe ; tu l'actives, puis tu lances `pages-publiques.sh` et tu
   habilles les 3 pages.
2. **Elementor Template Kit** (`.zip` Envato : `manifest.json` + `templates/*.json`
   + `screenshots/*.jpg`) → ce n'est **pas** un thème : il s'importe via Elementor
   (Outils → *Importer un Kit*), sur un thème de base léger (**Hello Elementor**
   ou **Astra**) + **Elementor** déposé dans `library/plugins/`. N'importe **que**
   header, footer, home→Accueil, contact→Contact — **jamais** tout le démo
   multi-pages (règle 3).

### Comprendre le thème avant de coder

Si c'est un kit Elementor, inspecte-le sans tout importer :

```bash
THEME_ZIP="library/themes/<le-zip>.zip"
unzip -p "$THEME_ZIP" manifest.json | head -60        # pages incluses + styles
unzip -o "$THEME_ZIP" 'screenshots/*' -d /tmp/kit      # extraire les aperçus
#   puis ouvre-les avec l'outil Read (il lit les images) :
#   Read /tmp/kit/screenshots/home.jpg  et  global-kit-styles.png
```

Regarde `home.jpg` (mise en page) et `global-kit-styles.png` (couleurs/typo) pour
t'imprégner du style, puis **reproduis-le fidèlement** sur les 3 pages.

### Contraintes d'intégration

- Adapter le thème en **3 pages** seulement (Accueil / Boutique / Contact),
  **mobile-first**, **RTL-compatible** (éviter les mises en page trop dépendantes
  du sens LTR).
- **Pas** de tunnel panier/compte ; la **Boutique = page Woo Shop** habillée au
  style du thème (pas une page Elementor déconnectée de WooCommerce).
- **Branding** (logo + nom) via le Personnalisateur, jamais codé en dur.

Une fois le kit retenu → l'importer dans `library/themes/` du clone (ou via
Elementor → Outils → *Importer un Kit*), puis appliquer le cahier des charges §5.

---

## 5. Les 3 pages publiques — cahier des charges

> 🏗️ **Le SOCLE existe déjà. Tu n'as plus à le bâtir — tu l'HABILLES.**
>
> ```bash
> docker compose exec wpcli bash /scripts/pages-publiques.sh
> ```
>
> Ce script (idempotent) crée les 3 pages, met Accueil en page d'accueil, monte le
> menu et l'assigne à tous les emplacements du thème, et met à la corbeille
> Panier / Commande / Mon compte / Sample Page.
> **À RELANCER APRÈS AVOIR ACTIVÉ LE THÈME DU CLIENT** : les emplacements de menu
> changent d'un thème à l'autre.

| Page         | Ce que le template fournit déjà                                                                                     | Ce qu'il te reste à faire                                                        |
| ------------ | ------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| **Accueil**  | Shortcode `[aod_home]` : héro (nom + slogan du Personnalisateur), catégories, produits, réassurance COD, CTA Boutique | **L'habiller au thème du client** : visuels, mise en page, style.                |
| **Boutique** | La page Boutique WooCommerce, renommée. Grille produits ; au clic → fiche produit + formulaire COD                    | Habiller la grille au style du thème.                                            |
| **Contact**  | Shortcode `[aod_contact_form]` : coordonnées (réglages Woo) + formulaire bilingue FR/AR, anti-bot                    | Habiller. Renseigner l'adresse de la boutique dans les réglages WooCommerce.     |

> 🗂️ **Catégories sur l'accueil — le gérant choisit (Phase 9, soldée).** Dans
> `/gestion → Catégories`, chaque catégorie a une case **« Afficher sur l'accueil »**
> + un **ordre**. La section « Nos catégories » de `[aod_home]` affiche alors
> **exactement** les catégories cochées, dans cet ordre (**vides comprises** — une
> catégorie neuve sans produit peut être mise en avant), sans plafond. Si **aucune**
> n'est cochée (clone neuf), repli automatique sur **toutes les catégories non
> vides**. Le rendu passe par `AOD_COD_Pages::home_categories_markup()` (cf.
> [`docs/ROADMAP-AMELIORATIONS.md`](docs/ROADMAP-AMELIORATIONS.md) §A.9 / Phase 9).
> *(Recette rejouable par clone : `bash tools/recette/run.sh categories`, incluse
> dans `public`. Elle sème ses propres catégories témoins et les purge en sortie.)*

### ⚠️ Pourquoi le contenu est dans des SHORTCODES et pas dans l'éditeur

Une page WordPress stocke son texte **en base**. Or `gettext` — donc le switcher
FR/AR — ne traduit **que le code**. Une page d'accueil dont le texte serait tapé
dans l'éditeur **resterait en français une fois le site passé en arabe**, sans
que rien ne le signale.

→ Les pages ne sont que des **coquilles** portant un shortcode. Tout le texte vit
dans `class-aod-cod-pages.php` / `class-aod-cod-contact.php`, passe par `__()`, et
suit le cookie `aod_lang`. **Si tu ajoutes du contenu, ajoute-le dans le code, pas
dans l'éditeur** — sinon tu casses le bilinguisme.

Même raison pour les libellés du menu : ils viennent de la base, donc
`AOD_COD_Pages::translate_menu_labels()` les retraduit à la volée, en reconnaissant
les 3 pages à leur meta `_aod_page_key` (jamais à leur libellé).

### ✅ La page produit peut devenir une « page de vente » (landing) — Phase 6

> **Statut : livrée** (étapes 6.1 → 6.6). Cahier des charges complet :
> [`docs/LANDING-PRODUIT.md`](docs/LANDING-PRODUIT.md).

Un interrupteur **par produit** dans `/gestion` (section *Page de vente*) remplace le
rendu de `/produit/<slug>` par une **page de vente** (héro, bénéfices, avis, FAQ,
réassurance… et le formulaire COD dedans), construite à partir d'un **catalogue
fermé de blocs** édités depuis `/gestion` — sans wp-admin, sans Elementor.
Code : `AOD_COD_Landing` (`plugins-dev/aod-cod-form/includes/class-aod-cod-landing.php`)
pour le schéma et le rendu, `AOD_CD_Section_Landing` (`aod-client-dashboard`) pour l'éditeur.

**Ce n'est pas une 4ᵉ page, et ça ne touche pas à la règle 3.** La règle porte sur
les **pages WordPress** et sur la **navigation**. Une fiche produit n'a jamais été
l'une des 3 pages et n'a jamais figuré dans le menu : la Boutique y mène par un
clic. **L'URL ne change pas**, aucune page n'est créée, la nav reste à 3 entrées.
Si tu te retrouves à créer une page WordPress par produit, tu as pris le mauvais
chemin — relis la spec §2.1.

**Et le bilinguisme ?** C'est LE point dur, et il nuance le paragraphe ci-dessus.
« Le contenu vit dans le code » vaut pour les **3 pages**, dont le texte est le
même chez tous les clones. Une landing, elle, est du **contenu marchand** : elle ne
peut pas vivre dans le code, et `gettext` ne pourra jamais la traduire.

→ D'où la règle propre à ce contenu : **chaque champ texte porte son couple
`{ fr, ar }`** (deux cases dans `/gestion`, repli automatique sur l'autre langue si
l'une est vide). Le helper existe déjà : `AOD_COD_Data::label( $fr, $ar )`.

Les deux mécanismes coexistent — les confondre casse le site :

| Nature | Exemple | Mécanisme |
|---|---|---|
| **Chrome** (UI, identique chez tous les clones) | « Commander maintenant », « Questions fréquentes » | `__()` + `.po` (§7) |
| **Contenu** (saisi par le gérant, propre au client) | le titre du héro, le texte d'un avis | couple `{ fr, ar }` en base |

✅ **C'est livré (Phase 7, soldée le 2026-07-17).** Titre et description du
produit, arguments de vente, variantes et noms de catégories portent chacun leur
couple `{ fr, ar }`, saisi dans `/gestion` et rendu selon le cookie `aod_lang`,
avec **repli bidirectionnel** (une version manquante retombe sur l'autre plutôt
que d'afficher du vide). Recette rejouable : `bash tools/recette/run.sh bilingue`.
Cf. [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) §6.

> ⚠️ Ce paragraphe a longtemps affirmé **l'inverse** — « tout le contenu marchand
> reste en français quand le site passe en arabe » — en renvoyant, pour preuve, au
> document qui disait déjà le contraire (`ARCHITECTURE.md` §6 : « Depuis la 7.5,
> tout le contenu marchand est bilingue »). Un avertissement en gras, dans le
> fichier lu **avant tout code**, sur une fonctionnalité livrée depuis trois
> jours : de quoi la faire ré-implémenter, ou faire reculer devant une story qui
> en dépend. C'est le pire format possible pour une information périmée.

### Ni panier ni compte : c'est verrouillé côté serveur

`AOD_COD_No_Tunnel` ne se contente pas de **masquer** le panier — il le rend
**impossible à remplir**, quel que soit le thème :

- l'action serveur `?add-to-cart=123` est **débranchée** (une URL forgée ne fait
  plus rien) ;
- tout bouton « Ajouter au panier », **d'où qu'il vienne**, pointe désormais vers
  la page produit — donc vers le formulaire COD ;
- les scripts `wc-add-to-cart` et `wc-cart-fragments` sont déchargés ;
- les URLs `/cart`, `/checkout`, `/my-account` renvoient vers la Boutique ;
- les entrées de menu correspondantes sont purgées.

Un thème qui rendrait son **propre** bouton panier (Astra le fait) est couvert : il
appelle forcément `$product->add_to_cart_url()`, donc le filtre.

> 🛒 **Panier COD (Phases 35-36, livrées) — l'exception qui ne désarme rien.** Si le
> gérant l'active (`/gestion → Livraison → carte Panier`, gérant uniquement), la
> fiche produit gagne « Ajouter au panier », le site un bouton flottant +
> panneau, les cartes des grilles (Boutique, Accueil, produits liés) une
> surimpression d'ajout sur l'image (ajout sans changer de page), et la page du
> formulaire un bloc de gestion du panier + 4 suggestions « Complétez votre
> commande » — mais **tout ce qui précède reste armé** : aucun état serveur (panier
> en `localStorage`), ni session ni cookie, tunnel Woo toujours mort, et la
> commande part par le MÊME `aod_cod_submit` (anti-bot et prix serveur
> inchangés, une commande à N lignes). Toggle OFF — le défaut de tout clone —
> : **pas un octet de différence**. Porte : `bash tools/recette/run.sh panier`.

### Vérifier — ne pas croire, mesurer

```bash
bash tests/run.sh                  # logique pure (prix, contraste AA, statuts colis, schéma landing)
bash tools/recette/run.sh public   # les 3 pages + une VRAIE commande COD, en visiteur
```

Contrôle dans un vrai navigateur, **en visiteur non connecté** : nav = exactement 3
entrées (desktop **et** mobile), aucun lien qui remplit le panier, URLs du tunnel
mortes, formulaire COD présent sur la fiche produit, RTL + libellés traduits en AR.

**Navigation :** le switcher FR/AR du plugin `aod-cod-form` ne s'affiche **que** si
un menu est assigné à `primary`/`secondary_menu`/`mobile_menu` → faire la nav
**avant** de traduire.

**Intégration du thème client :**

- Préfère un **child theme** (`wordpress/wp-content/themes/<theme>-child/`) pour
  que les personnalisations survivent aux mises à jour du thème parent.
- Logo + nom du site via le **Personnalisateur** → le branding du dashboard/login
  suit automatiquement. Ne code pas le logo en dur.
- Vérifie le **RTL** du thème (passe le site en AR et contrôle le rendu).
- Objectif qualité : élégant, moderne, rapide (< 3 s mobile), accessible.
- 📋 **Déroule [`CHECKLIST-THEME-CHILD.md`](CHECKLIST-THEME-CHILD.md)** : correctifs
  génériques à rejouer sur le child theme de chaque clone (prix DZD inversé, écran
  blanc RTL mobile, double titre sur l'Accueil, thème sans traduction arabe, icône
  panier dans l'en-tête, tri boutique…).

---

## 6. Le flux de commande (à respecter dans toute page produit)

Acheteur → page produit → **formulaire COD** (`aod-cod-form`) :
Nom, **Téléphone (obligatoire)**, Wilaya + Commune (listes déroulantes),
Adresse, e-mail **facultatif**. → commande créée sans panier ni compte → le
gérant la traite dans `/gestion` → envoi transporteur (Yalidine/Noest/Ecotrack).

Ne réintroduis jamais le tunnel WooCommerce standard (panier → checkout → compte).
*(Le panier COD togglable de la Phase 35 n'est pas ce tunnel : sa seule sortie
est le formulaire COD ci-dessus, qui crée UNE commande à N lignes.)*

---

## 7. i18n — workflow FR ⇄ AR (obligatoire après toute chaîne UI)

Le toggle FR/AR pose un cookie `aod_lang` partagé entre le public (`aod-cod-form`)
et `/gestion` (`aod-client-dashboard`) ; l'AR bascule en RTL via `is_rtl()`.

- **Toute** chaîne visible doit passer par `__()/_e()` (PHP) ou l'objet i18n JS
  (`window.AOD_CD` / équivalent) — jamais de texte FR en dur.
- Après avoir ajouté/modifié une chaîne, lancer le `make-translations.sh` **du plugin
  concerné** (extract → merge → compile `.mo` + liste les non-traduites), puis traduire
  les chaînes signalées dans `languages/*-ar.po`, et relancer. `--strict` sort en
  erreur s'il en reste (hook/CI).
  - `/gestion` → [`plugins-dev/aod-client-dashboard/bin/make-translations.sh`](plugins-dev/aod-client-dashboard/bin/make-translations.sh)
  - public (formulaire COD, 3 pages) → [`plugins-dev/aod-cod-form/bin/make-translations.sh`](plugins-dev/aod-cod-form/bin/make-translations.sh)
- La traduction AR est **manuelle** (pas d'internet). **Glossaire de référence** :
  `woo-jude-form-premium.1.0.7/woo-jude-form-premium/languages/woo-jude-form-ar-complete.po`
  (source AR la plus riche) + données géo DZ (`include/places/DZ.php`,
  `include/states/DZ.php`) si besoin.
- Les chaînes de **marque** (nom du plugin, auteur, URL) restent volontairement
  non traduites.

---

## 8. Mettre en route un nouveau clone

```bash
git clone <repo> mon-client && cd mon-client
cp .env.example .env            # éditer : PROJECT_SLUG + COMPOSE_PROJECT_NAME UNIQUES,
                                #          ports libres (WP_PORT/PMA_PORT), mots de passe propres
bash scripts/preflight.sh       # GARDE-FOU : refuse de continuer si mal isolé (slug défaut,
                                #             conteneurs/ports déjà pris par un autre projet)
# FACULTATIF : déposer le thème du client dans library/themes/*.zip
#              (et les plugins tiers qu'il exige dans library/plugins/*.zip)
docker compose up -d
docker compose exec wpcli bash /scripts/setup.sh   # idempotent : installe/configure WP+Woo (Algérie/DZD/COD)
```

> 🔌 **`setup.sh` rend le clone FONCTIONNEL à lui seul (depuis le 2026-07-28).**
> `library/` n'étant pas versionné (poids + licences, §2), un clone frais l'a
> **vide** : les boucles d'installation n'y trouvaient rien, et les deux plugins
> maison — qui déclarent `Requires Plugins: woocommerce` — se mettaient en veille.
> Autrement dit `/gestion` et la boutique n'existaient pas, **sans qu'aucune
> erreur ne le dise**. Désormais l'étape **4bis** installe le socle manquant
> (**WooCommerce**, et **Astra** si aucun thème n'est fourni) depuis
> wordpress.org, l'étape **5** active les deux plugins `aod-*`, et l'étape **7**
> **refuse de conclure** si l'un des trois manque ou si aucun thème n'est actif.
> ⚠️ Un thème déposé dans `library/themes/` reste **prioritaire** : le repli
> comble une absence, il ne remplace jamais le choix de l'owner.
>
> 📦 **Et depuis le 2026-07-28, l'installation marche HORS LIGNE** : les archives
> du socle (`woocommerce.*.zip`, `astra.*.zip`) sont **versionnées** (§2). Le
> repli réseau ne sert plus que si on les retire, ou pour une version plus
> récente que le plancher versionné.

Puis : **activer le thème client** s'il y en a un (logo + nom via Personnalisateur)
→ puis, **dans cet ordre** :

```bash
# Le socle des 3 pages publiques. APRÈS l'activation du thème : l'assignation du
# menu dépend des emplacements que CE thème déclare.
docker compose exec wpcli bash /scripts/pages-publiques.sh

# Vérifier — en visiteur, dans un vrai navigateur.
bash tools/recette/run.sh public
```

Enfin : créer le compte gérant (Utilisateurs → Accès Gérant) → le client règle
WhatsApp/pixels/livraison dans `/gestion`.

> 🎨 **Une fois l'accent du client réglé** dans le Personnalisateur, relance
> **`bash tools/recette/run.sh contraste`**. L'accent pilote toutes les couleurs
> de `/gestion` : une couleur de marque ordinaire peut faire passer des textes
> sous le seuil de contraste AA que l'accent par défaut (un jaune très clair)
> masquait. C'est une porte de recette **par clone**, pas un test de dev. Cf.
> [`tools/recette/README.md`](tools/recette/README.md).

---

## 9. Références utiles
- [`README.md`](README.md) — présentation générale du template.
- [`README-LOCAL.md`](README-LOCAL.md) — détails environnement Docker local.
- [`tests/README.md`](tests/README.md) — **tests unitaires** (`bash tests/run.sh`) :
  la logique qu'une régression casserait *sans que ça se voie* — calcul du tarif de
  livraison, contraste AA sur tout accent, mapping des statuts colis, schéma de la
  page de vente, périmètre des rôles. Sans Composer ni internet : jouables dès
  l'installation d'un clone.
- [`tools/recette/README.md`](tools/recette/README.md) — **recette navigateur**
  (contraste AA, noms accessibles, clavier, souris, **mouvement** — transitions, appui,
  `prefers-reduced-motion` —, RTL + mobile, 3 pages publiques,
  **une vraie commande COD de bout en bout** : `run.sh cod`, et `/gestion` parcouru
  **en confirmateur** — la seule porte qui ne se connecte pas en gérant :
  `run.sh confirmateur`) :
  `bash tools/recette/run.sh`. À rejouer après toute retouche de `/gestion`, et
  sur chaque clone une fois l'accent réglé.
- [`docs/CRON-SERVEUR.md`](docs/CRON-SERVEUR.md) — 🔴 **à faire à chaque mise en
  ligne** : cron serveur (Hostinger & autres). Sans lui, WordPress ne se réveille
  que sur une visite → le **suivi des colis reste figé** et les stats de livraison
  sont fausses.
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — **état des lieux technique complet**
  des deux plugins (classes, modèle de données, sécurité, dette connue) — à lire
  avant tout chantier sur les plugins.
- [`docs/ROADMAP-AMELIORATIONS.md`](docs/ROADMAP-AMELIORATIONS.md) — verdicts de
  faisabilité (partie A) + plan d'implémentation par phases/étapes (partie B) +
  pilotage (partie C). **Les Phases 1 à 48 sont soldées** — Phase 32 SOLDÉE (6/6 +
  32.2bis) comprise.
  ✅ **La Phase 48 est SOLDÉE le 2026-08-02 (5/5)** (verdict §A.24, plan
  §B/Phase 48, suivi §C.3) — **chrome pur pour ses quatre premières étapes** :
  aucun handler AJAX, aucune capability, aucun schéma, aucune requête nouvelle.
  ⚠️ **La 48.5, elle, n'en est pas** : elle ajoute un hook d'invalidation de
  cache (`woocommerce_update_order`) — ne pas lire « chrome pur » comme couvrant
  toute la phase. Née d'un constat owner : un
  confirmateur à qui **12** commandes étaient assignées lisait « Commandes
  reçues : **33** ». Le chiffre était **exact et voulu** (D-3 de la 47.5) — mais
  la raison écrite alors couvrait le **ratio**, pas le **compteur**, et c'était le
  seul nombre de tout son `/gestion` à parler de la boutique. Livré : le libellé
  nomme son périmètre pour le seul lecteur qui peut s'y tromper (le gérant garde
  exactement sa chaîne), la note de périmètre remonte **sous les cartes**, et une
  carte **« Commandes assignées »** donne enfin son chiffre, **en tête de
  page** — gouvernée par le **même `$row_uid`** que les trois autres.
  🔁 **La 48.4 a rouvert la phase après sa clôture, sur retour de l'owner :
  « rien n'est corrigé ».** Il avait raison — la carte était livrée en 4ᵉ
  position, donc le premier nombre lu restait celui de la boutique, sous un
  sous-titre « Vue d'ensemble de la **boutique** ». *Ajouter la bonne
  information ne corrige rien tant qu'elle arrive après celle qui trompe.* 🚨 *La cohérence d'un écran ne se juge
  pas carte par carte, mais contre le reste du produit.*
  🔁 **Puis la 48.5 l'a rouverte une seconde fois, sur un second retour :
  « j'assigne 2 commandes, 12 + 2 = 14, et la carte affiche 12 ».** Cette
  fois le défaut n'était pas dans la carte mais **sous** elle : une assignation
  n'écrit qu'une **méta**, et les quatre hooks qui invalidaient le cache des
  agrégats ne couvraient que création, statut et suppressions — pendant que le
  commentaire au-dessus d'eux promettait déjà « toute écriture de commande ».
  🚨 *Un commentaire qui annonce une classe entière au-dessus d'une liste de
  quatre cas est une promesse que personne ne vérifie.* Corrigé par **un** hook
  (`woocommerce_update_order`, émis par `$order->save()` dans les deux magasins)
  plutôt que par un bump sur les **cinq** points d'écriture de l'assignation —
  une règle répartie sur cinq sites ne survit pas au sixième.
  ⚠️ **Le profil de ce défaut mérite d'être connu** : il était *intermittent,
  muet et auto-cicatrisant* (5 min de TTL). Un écran vérifié une minute trop tard
  le déclare corrigé. Détail et pièges :
  `ARCHITECTURE.md` §3 (« Qui voit quoi dans l'onglet Confirmation »).
  ✅ **La Phase 47 est SOLDÉE le 2026-08-01** (verdict §A.23,
  plan §B/Phase 47, suivi §C.3) : **8 étapes livrées + 47.6bis, 1 abandonnée**.
  Dix demandes de l'owner qui convergeaient vers un même point — *le confirmateur
  ne pilote pas son propre geste*. Elle a **débranché l'envoi transporteur de la
  confirmation** (47.2) pour en faire un acte déclenché au camion, avec fenêtre de
  récapitulatif ; ce faisant elle **solde `D27`** en supprimant son objet (cf. la
  ligne barrée plus bas). ⚠️ Contrairement aux Phases 45 et 46, ce **n'était pas du
  chrome** : hook de statut Woo, charge utile transporteur, deux métas de commande
  et un handler AJAX nouveaux. Son critère de non-régression était ailleurs — **le
  parcours acheteur n'a pas bougé d'une ligne**, et aucune capability n'a été
  créée. ⛔ L'étape **47.8 est ABANDONNÉE sans objet** (6ᵉ demande arrivée
  tronquée, retirée par l'owner) : le numéro n'est pas réattribué — ne pas lire ce
  trou comme un oubli. 🔎 **47.6bis** a rouvert la 47.6 après sa clôture, à
  dessein : celle-ci annonçait honnêtement une limite d'envoi de 2 Mo au lieu de
  la lever. *Annoncer honnêtement une limite qu'on aurait pu lever, c'est décrire
  le problème au lieu de le résoudre.*
  🔎 **REVUE DE CODE POST-PHASE (2026-08-01) — dix défauts, tous corrigés**
  (`aa7a417`, détail en ROADMAP §C.3, bloc « Revue de code de la Phase 47 »).
  🚨 **Le plus grave : la 47.2 a perdu une CONDITION en changeant de déclencheur.**
  Tant que l'envoi partait du hook `woocommerce_order_status_aod-confirmed`, le hook
  **était** la condition — seule une commande confirmée pouvait expédier. Le
  déclencheur déplacé, la condition est partie avec lui : une commande COD fraîche,
  et même une commande **annulée**, se retrouvaient à un clic d'un colis **réel,
  facturé, irréversible**. Restituée côté serveur (`ship_order()`) **et** côté rendu.
  *Une garantie portée par un mécanisme disparaît avec lui.*
  Les huit autres sont **une décision propagée à un jumeau et pas à l'autre** — case
  d'envoi auto retirée de `/gestion` mais gardée dans wp-admin, cellule « Suivi »
  renvoyée par les chemins unitaires mais pas par le lot, compteurs de refus d'image
  tissés partout sauf sur la photo principale (qui jetait alors le reste de la
  saisie), `carrier_for()` unique sauf dans le formulaire public. Même famille que la
  Phase 44. Cliquet : `tests/unit/test-revue-47.php`.
  ⚠️ **Un correctif a fait rougir une porte, et c'était juste** : `run.sh riche`
  gardait le contrat que le correctif venait de changer. Elle n'a pas été rendue
  verte — elle mesure désormais **ce que le correctif a acheté**.

  🚨 **La leçon transverse de la phase, et elle vaut pour toute étape future** :
  **neuf** affirmations de son propre plan ont été démenties par la mesure, et
  plusieurs **assertions écrites pour la garder étaient vertes par accident** —
  satisfaites par un commentaire, un fragment de source, un cache, une modale
  masquée mais présente dans le DOM. *Écrire un contrôle ne suffit pas : il faut
  l'éprouver au rouge, et vérifier qu'il rougit pour LA raison qu'on croit.*
  La **Phase 44** (soldée le 2026-07-29, issue d'un audit A→Z)
  a fermé trois défauts de PROPAGATION — un bon motif déjà présent dans le dépôt
  qui n'avait pas atteint son jumeau : bornes de quantité/stock sur la soumission
  COD, `setup.sh` qui n'écrase plus le thème du clone, cliquet documentaire étendu
  à `README.md` et `ARCHITECTURE.md`. Le détail phase par phase (ce qui a été livré, les défauts
  trouvés en cours de route, les leçons 🚨 à connaître avant de retoucher une zone)
  **ne vit plus ici** — il vivait en double avec `docs/ARCHITECTURE.md` (le
  technique, par classe) et `ROADMAP-AMELIORATIONS.md` §C.3 (l'historique, par
  phase), ce qui les faisait dériver l'un de l'autre (cf. dette D25, soldée en 32.6
  par un cliquet qui recalcule le squelette documentaire).
  La table §C.3 de la ROADMAP fait foi sur l'état des phases ;
  `ARCHITECTURE.md` fait foi sur le fonctionnement du code. Avant de toucher une
  zone précise (assignation, panier
  COD, stock par variante, landing, pixels…), chercher son numéro de phase dans
  §C.3 : chaque ligne renvoie aux gotchas propres à cette zone.

  ✅ **Phase 45 SOLDÉE (5/5) le 2026-07-29** — chrome de `/gestion` (verdict
  A.21) : l'icône du rail replié était décentrée de 4 px (mesuré, et le décalage
  MIROITE en arabe) ; la barre d'action groupée quitte le fond noir pour une
  surface de la charte et devient **permanente**, ses actions `disabled` à
  sélection vide (la garantie de 2026 conservée en changeant de moyen — et
  gardée, enfin, par `test-bulkbar-permanente.php` : le cliquet que l'ancien
  commentaire annonçait n'avait jamais été écrit) ; le n° de commande redevient
  du texte et l'ouverture du détail passe par un bouton **« Détail »** dans le
  rang Appeler/WhatsApp, sur Commandes **et** Suivi. Phase de **chrome pur** :
  aucun handler AJAX, aucune capability, aucune requête, aucun schéma de données.
  🔁 **La 45.5 a rouvert la phase après sa clôture, à dessein** : la 45.1 avait
  centré l'icône **horizontalement** sans regarder la **colonne** qui la
  contient. Mesuré, rail replié : **1069 px en FR, 1348 px en AR** pour
  12 icônes — il débordait donc de toute fenêtre d'ordinateur portable, et ce
  débordement **défaisait le centrage** là où la barre de défilement prend de
  la largeur. Cause : `opacity:0` ne retire aucune hauteur, si bien que des
  libellés que personne ne lit dictaient la hauteur du rail — dans une langue
  dont rien ne s'affiche. Après : **0 px de débordement**, **0 px de
  décentrage**, et une hauteur naturelle de **693 px identique en FR et en AR**
  (cf. §A.21, Constat 1bis). 🚨 La règle générale, elle, vaut partout : *dans
  un rail d'icônes, aucun texte invisible ne doit peser en hauteur* — et on la
  fait respecter en **bornant** la hauteur, jamais avec `display:none`, qui
  retirerait les liens de l'ordre de tabulation et leur nom accessible.
  ⚠️ **La Phase 44 est postérieure à la 45 et à la 46 dans l'histoire du dépôt,
  antérieure dans la numérotation** : elle a vécu sur sa propre branche
  (`phase-44-bornes-idempotence-cliquet`, PR #3) pendant que 45, 46 et D32 se
  livraient ailleurs, et n'a été fusionnée que le **2026-07-30**. Le trou entre
  43 et 45 était voulu, il est désormais comblé — mais si une ligne de la 45 ou
  de la 46 semble ignorer un correctif de la 44, c'est cette chronologie qu'il
  faut lire, pas un oubli.

  ✅ **Phase 46 SOLDÉE (8/8) le 2026-07-30** — conformité du chrome de `/gestion`
  (verdict A.22). Phase de **chrome pur** : aucun handler AJAX, aucune capability,
  aucune requête, aucun schéma de données.
  **Ce qu'elle corrige n'est pas huit défauts, c'est un mode de dérive.** Dans six
  des huit constats, la bonne décision était **déjà écrite dans le fichier, avec sa
  raison, appliquée une ou deux fois** — et rien n'empêchait les autres occurrences
  d'en diverger. Le cas le plus net : la règle « l'anneau de focus ne dépend pas de
  l'accent » était écrite en entier dans `dashboard.css` et **démentie quatre fois
  plus bas dans le même fichier**, alors qu'elle venait d'être ré-appliquée à la
  main la veille (45.2). Avec l'accent par défaut du template, ces anneaux étaient
  à **1,15:1**.
  Livré : les 4 anneaux repassés sur `--cd-focus` (46.1) · Stock + les 5 tableaux
  de l'Accueil lisibles au pouce (46.2) · le toast devient une région live
  permanente (46.3) · une seule famille de rayons (46.4) · les 43 correspondances
  typo exactes passées aux crans, **prouvé pixel-identique** par empreinte des
  styles calculés sur 7 003 éléments (46.5) · les cibles tactiles à **44 px sur
  mobile seulement** (46.6, 530 cibles mesurées).
  🚨 **La leçon de la phase, et elle est générale : une règle écrite deux fois ne
  tient pas ; seul un cliquet la tient.** Cinq nouveaux garde-fous
  (`test-chrome-conformite.php` R1→R3, plus R4/R5 dans `test-charte-surfaces.php`),
  et **trois portes qui regardent enfin ce qu'elles laissaient passer** :
  `contraste` mesure les anneaux de focus (463 contrôles × 6 accents ; elle en
  contenait **zéro** mention avant), `rtl` mesure chaque tableau contre son
  conteneur **et** les cibles tactiles à 390 px, `noms` vérifie la région live.
  🔎 **Les portes ont payé immédiatement** : l'anneau du champ de **remise à zéro
  de la boutique** était à 1,29:1 — le contrôle le plus destructif de `/gestion`,
  invisible au clavier — et R1 ne pouvait pas le voir (couleur littérale).
  ⚠️ **Trois affirmations de mon propre plan ont été démenties par la mesure, et
  sont corrigées dans la ROADMAP avec leur chiffre plutôt que réécrites** : `32 px`
  n'existe nulle part (donc `--cd-sp-8` supprimé au lieu d'être appliqué, contre
  D2) ; les « jumeaux » `assignsel`/`leadassignsel` étaient **déjà mutualisés**
  (23 règles sur 25 ont un corps unique, donc D4 close sans objet) ; et l'anneau
  fautif n'était pas écrit trois fois mais deux.
  ✅ **46.5bis livrée** — les 110 littéraux typographiques restants, classés puis
  traités : **les tailles de TEXTE passent de 20 distinctes à 8.** Diff mesuré, pas
  supposé : 3 193 éléments changent et **uniquement en `font-size`**, deltas
  −0,5 / +0,5 / +1 px, rien au-delà.
  🚨 **29 littéraux restent, et c'est correct** — ce sont **24 métriques de glyphe**
  (un caractère dessiné dans une boîte déterminée : la croix d'une modale, un
  chevron qui pivote, des initiales dans un rond de 42 px — les ranger sur des crans
  de TEXTE casserait leur rapport optique au cadre) et **5 micro-libellés** sous le
  plancher de l'échelle. Le tri **ne se mécanise pas** : un critère automatique a été
  essayé et rejeté (faux positifs sur du texte, faux négatifs sur les chevrons). Il
  faut lire ce que l'élément contient — c'est pourquoi cette étape était un arbitrage
  et non un `sed`, et pourquoi R5 porte désormais un **recensement** : une taille non
  recensée rougit même si le total ne bouge pas.
  ⚠️ **Un cas mérite d'être connu avant de « finir l'alignement »** :
  `.aod-cd-navgroup` (les intitulés de groupe du rail) est exclu pour une raison qui
  n'a rien de typographique — la 45.5 a réglé la géométrie verticale du rail replié
  AU PIXEL (693 px identiques FR/AR) et le `font-size` de ces libellés entre dans ce
  calcul. Y toucher défait une phase entière, en silence, dans une langue qu'on ne
  lit pas forcément.

  ✅ **`D32` SOLDÉE le 2026-07-30** — le tableau des **transporteurs** au pouce.
  🚨 **La mesure a démenti la dette que j'avais moi-même écrite**, sur ses deux
  chiffres, et c'est la leçon à retenir avant d'ouvrir un chantier sur sa foi :
  les « 760 px de large » n'étaient **pas** la largeur du contenu, c'était le
  `min-width:760px` que `.aod-cd-table` impose à TOUS les tableaux — un plancher
  taillé pour 7 colonnes, appliqué à un tableau qui en a 3 (**408 des 490 px**) ;
  et sur les « 254 cellules », **138 appartiennent aux 29 `.form-table` de
  WordPress imbriquées** dans les panneaux, qui n'ont jamais eu à porter de
  `data-label`. Conclusion inverse de la dette : le patron carte n'était pas
  nécessaire, et **l'interaction n'a pas changé d'un iota** — la ligne reste le
  même `role="button"`, le panneau reste la ligne suivante.
  Ce qui l'était : l'**empilement**, et il est contraint, pas esthétique — une
  ligne exige 289 px de contenu (écusson 38 + espace 13 + « E-com Delivery » 88 +
  pastille 112 + chevron 38) pour **270 disponibles**, padding non compris.
  Après : **0 px** de débordement en FR comme en AR, à 390, 700 et 1440 px,
  panneau ouvert **et** fermé, cibles à 85 px de haut.
  ⚠️ **Un second défaut, indépendant, trouvé en chemin et corrigé** : la rotation
  du chevron portait sur la **cellule** (41 × 68 px) — pivotée, elle devient
  68 × 41 et dépassait de **13 px**, soit un défilement horizontal sur **grand
  écran** dès qu'un panneau s'ouvrait. La rotation porte désormais sur le glyphe.
  🚨 **Et ce défaut dit ce que la porte ne savait pas faire** : elle mesurait les
  tableaux tels que la page les sert, donc **panneaux fermés** — un état sur deux.
  Elle en **déplie** un désormais à chaque passage (témoin : 3 attendus), et
  l'exception `TABLES_EXEMPTES` a disparu : **il n'y en a plus aucune**.

  ✅ **`D31` SOLDÉE le 2026-07-30 — et la dette accusait le produit alors que le
  défaut était dans la PORTE.** Le rouge de `abandonnees` (« après Réinitialiser,
  le prospect du 15/06 redevient visible ») venait de la **pagination** : 28 lignes
  pour un `PER_PAGE` de 20, témoin daté de 2024 donc **dernier** d'un tri
  `updated_at DESC`, donc en **page 2**. « Réinitialiser » faisait son travail —
  prouvé : URL nue, champs de date vides, témoin présent en page 2.
  ⚠️ **La contre-mesure de la dette (« 15 prospects pour un PER_PAGE de 20, la
  pagination n'est pas en cause ») était fausse deux fois** : elle comptait le clone
  **au repos**, sans les 8 fixtures que la porte sème elle-même, et seulement les
  `pending` alors que l'onglet par défaut est `all`. *Compter la donnée hors du
  contexte où l'assertion tourne, c'est mesurer autre chose.*
  🚨 **Et la leçon était déjà écrite dans le dépôt, sans avoir été propagée** :
  `97e8982` corrigeait ce défaut EXACT sur `test-filtre-date.php` le même jour. Deux
  jumeaux trouvés en corrigeant le premier, tous deux dans `commandes-suivi.js` —
  l'un **vert par accident de données**, l'autre **rouge et couvert par aucune
  dette** (la porte `suivi` ne figurait pas parmi les 8 rejouées en 46.7). Correctif :
  `chercheDansPages()` dans `lib.js`, **un seul corps pour trois appelants**.
  🔎 **Ce qu'il faut en retenir avant d'ouvrir un chantier sur la foi d'une dette** :
  une dette doit consigner **comment** le chiffre a été obtenu, pas seulement le
  chiffre — sinon elle se relit comme un fait établi et oriente vers la mauvaise
  piste. C'est la deuxième fois en deux jours (cf. `D32`).

  **Dette de CODE** (détail et dettes non-code en ROADMAP §C.5, qui **fait foi**) —
  au 2026-08-03 il n'en reste **qu'une**, `A.4`, et elle attend un compte client
  réel, pas du code. `D16` (porte `confirmateur` flanchée une fois, non
  reproductible) reste ouverte côté **outillage** ; `D26` et `D7` ont été closes le
  2026-08-03 :
  - ~~`D33`~~ — ✅ **SOLDÉE le 2026-08-01.** Les 4 contrôles de non-régression de la
    porte `landing` (« un produit publié **sans** page de vente s'affiche encore en
    fiche produit normale, avec son formulaire COD ») exigeaient un témoin que
    `run.sh` **empruntait au clone** — et le vivier est vide dès que tous les
    produits publiés ont une landing, ce que fait le catalogue de démonstration sur
    ses **deux** produits. La porte sème désormais son propre témoin jetable
    (`scripts/recette-seed-landing.php`), comme `run.sh cod` sème le produit qu'il
    achète, et pour la même raison : *une porte qui emprunte sa donnée au clone
    dépend de ce que le gérant a saisi en dernier.* Le témoin est délibérément **nu**
    — ni options, ni offres, ni landing — et la fixture **refuse de semer** un témoin
    qui en aurait une (le défaut retourné à l'envers serait pire : comparer une
    landing à une landing, en vert). Gardé par `tests/unit/test-temoin-landing.php`.
    ⚠️ **Le chiffre de la dette ne se reproduisait plus au moment de la solder** —
    mesuré : 2 produits publiés, **1 seule** landing, donc un témoin disponible. La
    cause est bien dans la source (`dev-seed-catalogue.php` lignes 611 et 917
    allument les deux), mais le clone avait **dérivé** depuis. Éprouvé en
    reproduisant la condition exacte : les deux produits repassés à
    `_aod_landing_enabled=1`, vivier vide, les 4 contrôles se sont exécutés sur le
    témoin semé. 🚨 *Une dette dont le chiffre dépend de l'état d'un clone doit être
    ré-armée avant d'être crue — ni tenue pour fausse, ni tenue pour acquise.*
  - ~~`D27`~~ — ⛔ **CLOSE SANS OBJET le 2026-08-01 (Phase 47.9).** Elle disait
    « l'envoi transporteur reste **synchrone** à la confirmation d'une commande ».
    Ce chemin **n'existe plus** : la 47.2 a retiré le hook
    `woocommerce_order_status_aod-confirmed`, et l'envoi est devenu un **geste**
    déclenché au camion. Elle n'a donc pas été corrigée — elle a **cessé d'avoir
    un objet**, parce que l'arbitrage produit a été rendu (demande 3 de l'owner).
    ⚠️ **Ce qui reste vrai** : l'envoi est toujours synchrone dans la requête de
    **celui qui clique**, et un lot est une boucle séquentielle plafonnée à 50.
    La différence n'est pas technique, elle est de **consentement**. Détail et
    cadrage chiffré : §C.5 (ligne barrée) et §C.3 ligne 47.2.
  - ~~`D10`~~ — ✅ **SOLDÉE le 2026-08-03.** `AOD_COD_Data::is_arabic()` confondait
    « thème RTL » et « site en arabe » : un thème RTL déposé sur un clone
    **francophone** — que rien n'interdit (§4) — faisait basculer les 58 wilayas,
    les 1541 communes et toute la page de vente en arabe, sur un site en français.
    Elle lit désormais la **langue**, jamais le sens d'écriture.
    ⚠️ **Deux choses à savoir avant d'y toucher.** (1) Le `|| is_rtl()` rendait
    **un vrai service par effet de bord** : dans le wp-admin, `AOD_COD_Lang` se
    retire volontairement, donc un administrateur au **profil arabe** ne devait son
    arabe qu'à lui. Il est rendu explicitement (`get_user_locale()`), derrière la
    **même frontière que `AOD_COD_Lang`** — `is_admin() && ! wp_doing_ajax()` :
    admin-ajax n'est pas l'admin, c'est le visiteur. (2) `AOD_COD_Form::is_arabic()`
    **délègue** — ne lui redonne pas de corps propre, les deux avaient déjà divergé
    en silence. Cliquet : `tests/unit/test-langue-vs-sens.php`.
  - `A.4` — **la seule dette de code encore ouverte** : tarifs API Maystro jamais
    confrontés au réseau réel (Noest n'a pas d'API publique, assumé) ; à vérifier si
    un client en fait un usage significatif.
- [`docs/LANDING-PRODUIT.md`](docs/LANDING-PRODUIT.md) — ✅ **spécification** de la
  **page de vente (landing) par produit** (ROADMAP Phase 6, **livrée**) :
  pourquoi elle remplace le rendu de la fiche produit au lieu de créer une page,
  contrat de données, catalogue de blocs, et le couple `{ fr, ar }` qui rend le
  **contenu marchand** bilingue — ce que `gettext` ne saura jamais faire. À lire
  avant toute retouche de la page de vente.
- [`docs/UX-GESTION-REFONTE.md`](docs/UX-GESTION-REFONTE.md) — **spécification UI/UX**
  de la refonte de l'espace gérant `/gestion` (« Laste Premium ») : design system
  (tokens + accent piloté par le Personnalisateur), composants unifiés, cahier des
  charges **page par page**, plan d'étapes. À lire avant la Phase 5.
- [`docs/ICONES-ET-CHARTE-GESTION.md`](docs/ICONES-ET-CHARTE-GESTION.md) — 🎨
  **spécification** des **icônes vectorielles** (Phosphor, catalogue fermé
  `AOD_CD_Icon`, `currentColor`, RTL) et de la **charte teintée** de `/gestion`
  (ROADMAP Phase 15) : pourquoi les emojis partent, et pourquoi la sidebar sombre
  est **dérivée** de l'accent au lieu d'être écrite en dur — sans quoi un accent de
  marque foncé se fait **délaver en silence** par le garde-fou de contraste. À lire
  avant toute retouche des icônes ou des couleurs du tableau de bord.
- [`docs/RUNBOOK-ANTI-BOT-COD.md`](docs/RUNBOOK-ANTI-BOT-COD.md) ·
  [`docs/Turnstile.md`](docs/Turnstile.md) ·
  [`docs/DURCISSEMENT-SECURITE.md`](docs/DURCISSEMENT-SECURITE.md) — sécurité :
  anti-flood COD, Turnstile, `.htaccess` durci.
- [`CHECKLIST-SITE-MASTER-ALGERIE.md`](CHECKLIST-SITE-MASTER-ALGERIE.md) — checklist de mise en place complète (hébergement → SEO → sécurité).
- `woo-jude-form-premium.1.0.7/` — **template de référence** (formulaire COD/RTL/AR
  premium). Non utilisé tel quel ; on y **pioche** : traductions AR, patterns RTL,
  données géo DZ. ⚠️ Pas de formulaire de contact prêt à l'emploi dedans — on adapte.

## 10. Checklist avant de dire « c'est fini » (côté public)

- [ ] Topbar = **exactement** Accueil / Boutique / Contact (desktop **et** mobile)
- [ ] **Aucun** panier **Woo** / compte / wishlist visible nulle part *(le
      panier **COD** de la Phase 35 n'apparaît que si le gérant l'a activé —
      et son « Commander » mène au formulaire COD, jamais à un checkout)*
- [ ] Page produit = formulaire COD (pas de « Ajouter au panier »)
- [ ] Si une **page de vente** est activée sur un produit (Phase 6) : elle contient
      le formulaire COD, l'URL du produit est inchangée, la nav est toujours à
      3 entrées, et **les textes saisis ont bien leur version arabe** (sinon le
      visiteur AR lit du français). Un produit **sans** landing s'affiche
      exactement comme avant.
- [ ] FR **et** AR OK, switcher visible, `<html lang="ar">` + RTL correct en AR
- [ ] Rendu **mobile** soigné, rapide (< 3 s)
- [ ] Logo / nom du site pris depuis le Personnalisateur (rien codé en dur)
- [ ] Rien de spécifique à un seul client ajouté au template
