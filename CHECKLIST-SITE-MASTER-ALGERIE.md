# Checklist — mettre un clone en ligne 🇩🇿

> **De quoi il s'agit :** la marche à suivre, du dépôt vide au site livré, pour un
> clone de ce template (WordPress + WooCommerce, **paiement à la livraison**, Algérie).
>
> Le « master », ce n'est plus un site à exporter/importer : **c'est ce dépôt Git.**
> Chaque client = un clone, avec sa stack Docker et son thème. Cf. [`TEMPLATE.md`](TEMPLATE.md).

---

## ⛔ Ce que ce template NE fait PAS — et ne doit jamais faire

Ces règles priment sur tout le reste. Une checklist qui les contredit est une checklist fausse.

| Interdit | Pourquoi | À la place |
|---|---|---|
| **Panier, tunnel de commande, compte client** | Règle fondatrice : l'acheteur DZ abandonne au checkout | Le **formulaire COD** (`aod-cod-form`) sur la page produit. Point. |
| **Paiement en ligne** (Chargily, CIB, Edahabia, Stripe…) | Hors périmètre : ce template est **100 % COD** | Le gérant confirme par téléphone, le transporteur encaisse |
| **Polylang / WPML** | Le bilingue FR⇄AR est **déjà** dans `aod-cod-form` (cookie `aod_lang` + RTL) | Ne rien installer. Traduire nos chaînes (cf. §8) |
| **Un plugin Yalidine tiers** | On a **notre** couche transporteurs : Yalidine, Yalitec, Noest, E-com, Maystro, EcoTrack + 23 marques | `/gestion` → Livraison → Transporteurs |
| **Plus de 3 pages publiques** | Règle : Accueil · Boutique · Contact, rien d'autre dans la nav | — |

---

## PHASE 0 — Hébergement & prérequis

- [ ] PHP **8.1+**, MySQL 5.7+ / MariaDB 10.4+
- [ ] Mémoire PHP **512 Mo** minimum (1 Go conseillé), `max_execution_time` ≥ 120 s
- [ ] **`upload_max_filesize` ≥ 8 Mo et `post_max_size` ≥ 64 Mo.** La boutique
      accepte les photos jusqu'à **5 Mo** et refuse au-delà **avec son propre
      message** ; si PHP plafonne plus bas, il refuse le premier, sans rien
      expliquer d'utile. `setup.sh` écrit un **`.user.ini`** à la racine, qui
      suffit sur PHP-FPM / CGI (Hostinger, LiteSpeed…). ⚠️ Sous **mod_php** il est
      ignoré : le demander alors à l'hébergeur. Ne PAS poser de `php_value` dans
      `.htaccess` à l'aveugle — sans mod_php, c'est une **erreur 500** sur tout
      le site. `post_max_size` compte autant que l'autre : la galerie envoie
      plusieurs photos dans un seul POST, et WordPress retient le **minimum** des
      deux.
- [ ] Domaine + **HTTPS** actif
- [ ] Accès **cron serveur** chez l'hébergeur → indispensable, cf. Phase 8
- [ ] Accès SSH ou gestionnaire de fichiers + phpMyAdmin

---

## PHASE 1 — Monter le clone (en local, Docker)

> 🚨 **Isolation.** Chaque clone a SA stack. Ne jamais installer dans le dossier
> d'un autre projet — l'erreur a déjà été faite. Cf. [`TEMPLATE.md`](TEMPLATE.md) §3.

```bash
git clone <repo> mon-client && cd mon-client
cp .env.example .env      # PROJECT_SLUG + COMPOSE_PROJECT_NAME UNIQUES, ports libres, mots de passe propres
bash scripts/preflight.sh # GARDE-FOU : refuse de continuer si mal isolé
```

- [ ] Déposer les zips : `library/plugins/*.zip` (WooCommerce…) et **`library/themes/*.zip`** (le thème du client)
- [ ] `docker compose up -d`
- [ ] `docker compose exec wpcli bash /scripts/setup.sh`

**`setup.sh` fait déjà tout ça — ne le refais pas à la main :** installation de
WordPress, fuseau **Africa/Algiers**, permaliens, packs de langue FR/AR, installation
des zips de `library/`, activation de WooCommerce, **DZD / Algérie / kg / cm**,
**passerelle COD activée**, **HPOS activé**.

- [ ] Activer les 2 plugins maison : `aod-cod-form` et `aod-client-dashboard`

---

## PHASE 2 — Le thème client & les 3 pages publiques

> C'est **le** travail du projet. Le thème est **choisi et déposé par l'owner** dans
> `library/themes/` — on ne choisit pas à sa place. Cf. [`TEMPLATE.md`](TEMPLATE.md) §4-5.

- [ ] Activer le thème client + créer un **child theme** (`wordpress/wp-content/themes/<theme>-child/`)

- [ ] **Monter le socle des 3 pages** — le script fait le travail, dans cet ordre
      (APRÈS l'activation du thème : l'assignation du menu dépend des emplacements
      que CE thème déclare) :

      docker compose exec wpcli bash /scripts/pages-publiques.sh

      Il crée Accueil / Boutique / Contact, met Accueil en page d'accueil, monte le menu
      et l'assigne partout, et met à la corbeille `Cart` / `Checkout` / `My account` /
      `Sample Page`. Il est idempotent : relançable sans rien casser.

- [ ] **Habiller** les 3 pages au style du thème client. Le contenu (héro, réassurance,
      formulaire de contact) est déjà là, rendu par les shortcodes `[aod_home]` et
      `[aod_contact_form]`.
      ⚠️ **N'écris pas de texte dans l'éditeur WordPress** : il serait stocké en base,
      donc **hors gettext**, donc **resté en français en arabe**. Tout texte va dans le
      code du plugin.

- [ ] Dérouler [`CHECKLIST-THEME-CHILD.md`](CHECKLIST-THEME-CHILD.md) — correctifs génériques
      à rejouer sur **chaque** clone (prix DZD inversé, écran blanc RTL mobile, double titre
      sur l'Accueil, thème sans traduction arabe, icône panier dans l'en-tête, tri boutique…)

- [ ] Renseigner l'**adresse de la boutique** (WooCommerce → Réglages) : elle s'affiche
      d'elle-même sur la page Contact.

- [ ] **Vérifier au navigateur, en visiteur non connecté** :

      bash tools/recette/run.sh public

      Nav = exactement 3 entrées (desktop **et** mobile), aucun lien qui remplit le panier,
      URLs du tunnel mortes, formulaire COD sur la fiche produit, RTL + libellés traduits en AR.

- [ ] Rendu **mobile** soigné (< 3 s) — la majorité du trafic DZ est mobile

---

## PHASE 3 — Branding

- [ ] **Personnalisateur** : logo + nom du site (jamais codé en dur — le dashboard et
      la page de connexion suivent automatiquement)
- [ ] **Personnalisateur → couleur d'accent de `/gestion`** (`aod_cd_accent`)
- [ ] 🔴 Une fois l'accent réglé : `bash tools/recette/run.sh contraste`
      → l'accent par défaut (jaune très clair) **masque** des fautes de contraste
      qu'une couleur de marque ordinaire révèle. Cf. [`tools/recette/README.md`](tools/recette/README.md)

---

## PHASE 4 — Produits & catégories

- [ ] Catégories (`/gestion` → Catégories)
- [ ] Produits (`/gestion` → Produits) : photos de qualité, prix, **prix de revient**
      (`_aod_cost_price`, sert au calcul de marge des stats), variantes, offres, packs
- [ ] Stock + seuils d'alerte (`/gestion` → Stock)

---

## PHASE 5 — Livraison & transporteurs

- [ ] `/gestion` → Livraison → **Transporteurs** : saisir les identifiants API du livreur
- [ ] **Importer les tarifs depuis l'API** du transporteur (préview → marge → wilayas cochées → appliquer).
      Une sauvegarde est faite avant écriture (« Restaurer les tarifs précédents »).
- [ ] Sinon : saisir manuellement les tarifs Domicile / Stop-desk des 58 wilayas
- [ ] Seuil de **livraison gratuite** si besoin
- [ ] 🔴 **Vérifier les identifiants contre la vraie API** :
      ```
      docker compose exec -T wpcli wp eval-file /scripts/recette-api-transporteur.php <id> [tracking]
      ```
      Un parseur tolérant qui se trompe **ne fait aucun bruit** : il rend du vide, ou un
      statut « inconnu » qui se fond dans « en transit ». C'est ainsi que l'import E-com
      est resté cassé des mois. Ne saute pas cette étape.

---

## PHASE 6 — Notifications, pixels, anti-bot

- [ ] `/gestion` → **WhatsApp** (CallMeBot) : notification au marchand à chaque commande + message test
- [ ] `/gestion` → **Pixels** : Meta / TikTok / Snapchat / Google Ads
- [ ] **Turnstile** (anti-robot) → [`docs/Turnstile.md`](docs/Turnstile.md)
- [ ] En cas de flood de fausses commandes → [`docs/RUNBOOK-ANTI-BOT-COD.md`](docs/RUNBOOK-ANTI-BOT-COD.md)

---

## PHASE 7 — Comptes & équipe

- [ ] Créer le **compte gérant** (Utilisateurs → Accès Gérant) — il n'a **pas** accès à `wp-admin`
- [ ] `/gestion` → **Équipe** : créer les confirmateurs, cocher leurs permissions,
      et si besoin **restreindre leur périmètre à certains produits**
- [ ] Vérifier qu'un confirmateur ne voit **que** ce qu'il doit voir

---

## PHASE 8 — 🔴 Cron serveur (sans ça, le suivi des colis ne marche pas)

WordPress n'a pas de vrai planificateur : WP-Cron est déclenché **par les visites**.
Sans visiteur, un colis livré à 9 h reste affiché « en transit », et les stats de
livraison sont fausses.

- [ ] `define( 'DISABLE_WP_CRON', true );` dans `wp-config.php`
- [ ] Tâche cron chez l'hébergeur, **toutes les 15 min**, sur `wp-cron.php`
- [ ] **Vérifier** : dans `/gestion` → Suivi commande, la colonne « mis à jour il y a X »
      doit **rajeunir toute seule**, sans que personne ne visite le site

→ Marche à suivre détaillée (Hostinger, cPanel, SSH) : [`docs/CRON-SERVEUR.md`](docs/CRON-SERVEUR.md)

---

## PHASE 9 — Bilingue FR / AR

Le switcher FR⇄AR est **déjà là** (`aod-cod-form`, cookie `aod_lang`, RTL via `is_rtl()`).
**Ne pas installer Polylang.**

- [ ] Le switcher s'affiche (il exige qu'un menu soit assigné à `primary`/`mobile_menu` → faire la nav **avant**)
- [ ] Passer le site en **AR** : `<html lang="ar">`, direction **RTL** correcte, rien qui déborde
- [ ] Toute chaîne ajoutée au code est traduite :
      `plugins-dev/<plugin>/bin/make-translations.sh` puis traduire l'AR dans `languages/*-ar.po`
- [ ] Pages légales : Mentions, CGV, Politique de retour

---

## PHASE 10 — Sécurité & sauvegarde

- [ ] `.htaccess` durci → [`docs/DURCISSEMENT-SECURITE.md`](docs/DURCISSEMENT-SECURITE.md)
- [ ] HTTPS forcé partout
- [ ] Mot de passe fort pour l'admin (jamais « admin »), 2FA si possible
- [ ] Limitation des tentatives de connexion
- [ ] **Sauvegardes automatiques** (UpdraftPlus ou l'outil de l'hébergeur)

---

## PHASE 11 — Performance & SEO

- [ ] Cache (LiteSpeed Cache / WP Super Cache)
- [ ] Compression des images (ShortPixel / Smush)
- [ ] SEO (Rank Math ou Yoast)
- [ ] PageSpeed : viser **< 3 s sur mobile**

---

## PHASE 12 — Recette avant livraison

**Côté public** (cf. [`TEMPLATE.md`](TEMPLATE.md) §10) :

- [ ] Nav = **exactement** Accueil / Boutique / Contact (desktop **et** mobile)
- [ ] **Aucun** panier / compte / wishlist visible nulle part
- [ ] Page produit = formulaire COD ; une **commande test** arrive bien dans `/gestion`
- [ ] FR **et** AR OK, switcher visible, RTL correct
- [ ] Mobile soigné et rapide

**Côté gérant :**

- [ ] `bash tools/recette/run.sh` — accessibilité AA, clavier, souris, RTL + mobile 390 px
- [ ] `wp eval-file /scripts/recette-api-transporteur.php <id>` — tarifs + suivi contre la vraie API
- [ ] Le cron serveur tourne (Phase 8) — **vérifié, pas supposé**

---

## PHASE 13 — Mise en production

Le paquet se **bâtit**, il ne se fabrique plus à la main :

```bash
bash scripts/make-deploiement.sh <domaine-du-client.com>
```

Il produit `Deploiement/` (gitignoré) : `public_html.zip`, `database.sql.gz` avec les
**URLs déjà réécrites**, un `wp-config.php` **modèle** (base et sels à remplir chez
l'hébergeur — aucun secret du clone de dev n'y passe) et un **`MANIFESTE.md`**.

> 🚨 **Lis le manifeste avant d'envoyer quoi que ce soit.** `Deploiement/` étant
> gitignoré, `git status` reste propre même si le paquet a sept semaines — c'est
> arrivé au clone de référence, avec **129 commits de retard**. Le manifeste porte le
> commit d'origine ; `git log --oneline <commit>..HEAD | wc -l` doit rendre **0**.
> Sinon, régénère : c'est une commande.

- [ ] Bâtir le paquet et **vérifier le manifeste** (0 commit de retard)
- [ ] Transférer fichiers + base chez l'hébergeur
- [ ] Adapter `wp-config.php` (identifiants BDD, clés de sel, `DISABLE_WP_CRON`)
- [ ] Mettre à jour les URL du site
- [ ] **Vider la boutique de ses données de test** : `/gestion` → Mon compte → réinitialisation
      (protégée par une phrase de confirmation)
- [ ] Rejouer la Phase 12 **sur le site en ligne**
- [ ] Livrer 🚀

---

## Récapitulatif des composants

| Besoin | Solution | Où |
|---|---|---|
| CMS + boutique | WordPress + WooCommerce (HPOS) | `scripts/setup.sh` |
| Commande sans panier | **`aod-cod-form`** (maison) | `plugins-dev/` |
| Espace gérant sans `wp-admin` | **`aod-client-dashboard`** (maison) → `/gestion` | `plugins-dev/` |
| Paiement | **COD uniquement** (natif Woo, activé par `setup.sh`) | — |
| Transporteurs + suivi | Couche maison : Yalidine, Yalitec, Noest, E-com, Maystro, EcoTrack + 23 marques | `/gestion` → Livraison |
| Bilingue FR/AR + RTL | **`aod-cod-form`** (cookie `aod_lang`) — **pas** Polylang | — |
| Thème | Fourni par l'owner dans `library/themes/` + child theme | [`CHECKLIST-THEME-CHILD.md`](CHECKLIST-THEME-CHILD.md) |
| Duplication | **`git clone` de ce dépôt** — pas d'export/import | [`TEMPLATE.md`](TEMPLATE.md) §8 |
