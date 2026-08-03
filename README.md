# Template WordPress E-commerce COD — Algérie 🇩🇿

Template **réutilisable** pour lancer rapidement des boutiques en ligne **COD**
(paiement à la livraison) en Algérie sous WordPress + WooCommerce.

> **Philosophie :** un seul socle générique pour tous les clients. Pour chaque
> nouveau projet, on **clone ce dépôt** et on **applique le thème** correspondant
> aux exigences du client — les plugins maison restent inchangés. Aucune donnée
> client n'est codée en dur : tout se configure depuis l'interface.

---

## ✨ Ce que contient le template

### 🔌 Deux plugins maison (Art Of Doing)

#### [`aod-cod-form`](plugins-dev/aod-cod-form/) — Formulaire COD (v1.52.1)
- Commande directe sur la page produit (sans panier ni compte)
- **Page de vente (landing) par produit** : un interrupteur dans `/gestion` remplace
  le rendu de `/produit/<slug>` par une landing à blocs (héro, bénéfices, avis, FAQ,
  urgence, barre collante… + le formulaire COD) — **même URL, pas de 4ᵉ page**, et
  chaque texte porte son couple `{ fr, ar }`
- **58 wilayas / 1541 communes**, tarifs de livraison par wilaya (Domicile / Stop-desk)
- Livraison gratuite à partir d'un montant
- Transporteurs **Yalidine / Yalitec / Noest / E-com Delivery (ex-ZR) / Maystro /
  EcoTrack + 23 marques white-label** : envoi auto/manuel, suivi colis (cron 2 h),
  étiquettes PDF selon transporteur
- **Anti-bot 5 couches, dont 4 sur un clone neuf** : honeypot, time-trap, rate-limit IP, limite téléphone
  24 h, **Cloudflare Turnstile** — le nonce CSRF les précède sans en faire partie
- **Pixels** navigateur (Meta, TikTok, Snapchat, Google Ads) + **conversions
  serveur** (Meta CAPI / TikTok / Snapchat, dédupliquées avec le pixel, envoyées
  en file différée hors du chemin d'achat) et **notification WhatsApp** (CallMeBot)
- Suivi des **prospects** (paniers abandonnés) + **switcher de langue FR/AR** public
- Bloc Gutenberg + widget Elementor · FR / AR + RTL

#### [`aod-client-dashboard`](plugins-dev/aod-client-dashboard/) — Espace gérant `/gestion` (v0.75.0)
- Interface front-end pour que le client gère sa boutique **sans accéder à `wp-admin`**
- **12 sections** : **Accueil** (3 onglets de stats : revenus, confirmation,
  livraison) · Commandes (filtres, édition, statut AJAX) · **Commandes abandonnées**
  (relance des prospects du formulaire COD non validés) · Produits (CRUD +
  variantes couleur, offres, packs) · **Stock** (couverture, ajustement inline) ·
  Catégories (hiérarchie + image) · Livraison (+ import des tarifs depuis l'API
  transporteur) · **Suivi commande** (états transporteurs normalisés, timeline) ·
  **Pixels & Tracking** · WhatsApp · **Équipe** (confirmateurs + permissions à la
  carte) · Mon compte
  — plus l'éditeur **Page de vente**, ouvert depuis la fiche d'un produit
  *(il n'existe pas de section « Statistiques » autonome : les stats vivent dans
  les 3 onglets de l'Accueil, qui a absorbé l'ancienne section)*
- **Design system** (Phase 5) : tokens, accent piloté par le Personnalisateur avec
  **contraste AA calculé**, composants unifiés, accessibilité clavier
- **Rôles restreints** « Gestion Boutique » et « Confirmateur » (capabilities
  sûres uniquement, permissions par utilisateur, resync auto)
- Reset boutique sécurisé (phrase de confirmation) · Compatible **HPOS**
- FR / AR + RTL, traduction arabe quasi complète

#### mu-plugin [`aod-sequential-order-numbers`](wordpress/wp-content/mu-plugins/)
Numérotation séquentielle des commandes à partir de 1 (HPOS-compatible).

### 🐳 Stack de développement (Docker)
WordPress + MariaDB + phpMyAdmin + WP-CLI, prêts à l'emploi via `docker-compose.yml`.

---

## 🚀 Démarrage rapide

```bash
# 1. Cloner le dépôt
git clone git@github.com:artsmart-pix/Template-Full.git mon-client
cd mon-client

# 2. Créer le fichier d'environnement (mots de passe, ports, titre du site)
cp .env.example .env   # puis éditer .env
#    ⚠️  Le .env n'est PAS versionné : choisis des mots de passe propres au projet.
#    ⚠️  PROJECT_SLUG + COMPOSE_PROJECT_NAME + ports doivent être UNIQUES par projet
#        (sinon ce clone piloterait les conteneurs d'un autre → installations mélangées).

# 3. Déposer les thèmes/plugins tiers dans library/ (voir note ci-dessous)
#    library/plugins/*.zip   (woocommerce, polylang, chargily-epay…)
#    library/themes/*.zip    (astra, kadence, neve…)

# 4. Vérifier l'isolation PUIS lancer la stack
bash scripts/preflight.sh      # garde-fou : bloque si slug/ports déjà pris par un autre projet
docker compose up -d

# 5. Installer & configurer WordPress + WooCommerce (Algérie / DZD / COD)
docker compose exec wpcli bash /scripts/setup.sh
```

Accès par défaut (ports définis dans `.env`) :
- Boutique : `http://localhost:8090`
- Admin : `http://localhost:8090/wp-admin`
- phpMyAdmin : `http://localhost:8091`

> ℹ️ Les **plugins tiers et thèmes ne sont pas versionnés** (poids + licences).
> `scripts/setup.sh` installe automatiquement tous les `.zip` présents dans
> `library/`. Voir [`README-LOCAL.md`](README-LOCAL.md) pour les détails de l'environnement local.

---

## 🧩 Mettre en place un nouveau client

1. **Cloner** ce dépôt → éditer `.env` (titre du site, mots de passe, ports).
2. `docker compose up -d` puis `setup.sh`.
3. Activer **WooCommerce** + les 2 plugins `aod-*`.
4. **Appliquer le thème du client** et définir son **logo** (Personnalisateur)
   et le **nom du site** → le branding du dashboard/login suit automatiquement.
5. Créer le compte du gérant : **Utilisateurs → Accès Gérant**.
6. Côté client, tout se règle dans `/gestion` : WhatsApp, pixels, tarifs de
   livraison, transporteurs.
7. _(Optionnel)_ Locale arabe par utilisateur pour le gérant.

---

## 🗂️ Structure du dépôt

```
.
├── docker-compose.yml                # db (MariaDB) + wordpress + wpcli + phpmyadmin
├── .env                              # secrets & ports — NON versionné
├── scripts/
│   ├── preflight.sh                  # garde-fou d'isolation (à lancer AVANT docker compose up)
│   └── setup.sh                      # installe/configure WP + WooCommerce (idempotent)
├── library/                          # zips thèmes/plugins tiers — NON versionnés
├── plugins-dev/
│   ├── aod-cod-form/                 # plugin formulaire COD + transporteurs
│   └── aod-client-dashboard/         # plugin espace gérant /gestion
├── wordpress/wp-content/
│   ├── mu-plugins/                   # numérotation séquentielle des commandes (versionné)
│   └── themes/*-child/               # child themes clients (seuls dossiers versionnés de wordpress/)
├── tests/                            # tests unitaires — `bash tests/run.sh` (il affiche son compte)
│   └── unit/                         # la logique qu'une régression casserait SANS que ça se voie
├── tools/
│   ├── recette/                      # recette navigateur — `bash tools/recette/run.sh` (portes : voir SCENARIOS)
│   └── git-hooks/                    # pre-push : refuse de pousser si les tests échouent
├── docs/                             # architecture, roadmap, sécurité, runbooks
├── TEMPLATE.md                       # guide complet du template (règles d'or)
├── README-LOCAL.md                   # notes d'environnement local
└── CHECKLIST-SITE-MASTER-ALGERIE.md  # checklist de mise en place
```

---

## 📚 Documentation

| Document | Contenu |
|---|---|
| [`TEMPLATE.md`](TEMPLATE.md) | **Le guide central** : règles d'or, isolation Docker, thème client, 3 pages, i18n. |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | État des lieux technique complet des 2 plugins (classes, données, sécurité). |
| [`docs/ROADMAP-AMELIORATIONS.md`](docs/ROADMAP-AMELIORATIONS.md) | **Plan d'implémentation par phases/étapes** (verdicts + DoD + suivi). ⚠️ **L'état des phases se lit dans la table §C.3 de ce document, qui fait foi — et nulle part ailleurs.** Cette cellule a annoncé « les 17 phases sont soldées (80 étapes) » jusqu'au 2026-07-29, alors que le projet en comptait 43 : un compteur recopié dans la porte d'entrée du dépôt, qui a vieilli de 26 phases sans que rien ne le signale (étape 44.3). Un compteur qu'on ne sait pas tenir se supprime, il ne se corrige pas. |
| [`docs/LANDING-PRODUIT.md`](docs/LANDING-PRODUIT.md) | **Page de vente (landing) par produit** : pourquoi elle remplace le rendu de la fiche produit, catalogue de blocs, couple `{ fr, ar }` du contenu marchand. |
| [`docs/UX-GESTION-REFONTE.md`](docs/UX-GESTION-REFONTE.md) | **Design system de `/gestion`** (tokens, composants, contraste AA) — référence pour toute nouvelle page. |
| [`docs/CRON-SERVEUR.md`](docs/CRON-SERVEUR.md) | 🔴 Cron serveur à poser à chaque mise en ligne — sans lui, le suivi des colis reste figé. |
| [`tools/recette/README.md`](tools/recette/README.md) | Recette navigateur (a11y, contraste, RTL/mobile, 3 pages publiques, commande COD de bout en bout). |
| [`docs/RUNBOOK-ANTI-BOT-COD.md`](docs/RUNBOOK-ANTI-BOT-COD.md) | Réponse aux floods de fausses commandes COD. |
| [`docs/Turnstile.md`](docs/Turnstile.md) | Configuration Cloudflare Turnstile. |
| [`docs/DURCISSEMENT-SECURITE.md`](docs/DURCISSEMENT-SECURITE.md) | `.htaccess` durci de référence pour la production. |
| [`CHECKLIST-THEME-CHILD.md`](CHECKLIST-THEME-CHILD.md) | Correctifs à rejouer sur le child theme de chaque clone. |
| [`CHECKLIST-SITE-MASTER-ALGERIE.md`](CHECKLIST-SITE-MASTER-ALGERIE.md) | Checklist de mise en place complète (hébergement → SEO). |
| [`README-LOCAL.md`](README-LOCAL.md) | Environnement Docker local (accès, commandes). |

---

## 🔒 Sécurité & bonnes pratiques

- Le `.env` (mots de passe BDD + admin) **n'est jamais versionné** (`.gitignore`).
- Génère des mots de passe **propres à chaque projet** ; ceux du dev local ne
  doivent pas servir en production.
- Désinstallation propre : chaque plugin fournit un `uninstall.php` (le dashboard
  retire son rôle ; le COD efface ses réglages et **conserve la table des
  prospects** par défaut).

---

## 📄 Licence

Plugins maison sous **GPL-2.0-or-later**. Les thèmes et plugins tiers déposés
dans `library/` restent soumis à leurs licences respectives.

—  Développé par [Art Of Doing](https://artofdoing.net)
