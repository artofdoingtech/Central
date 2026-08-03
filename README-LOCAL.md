# Environnement local — stack Docker du template 🇩🇿

Stack WordPress + WooCommerce sous Docker, prête à travailler.
Chaque clone a **sa propre stack** (voir TEMPLATE.md §3 — isolation).

## 🔗 Accès

Ports définis dans `.env` (`WP_PORT` / `PMA_PORT`, défauts 8090/8091) :

| Service | URL | Identifiants |
|---|---|---|
| **Boutique** | http://localhost:${WP_PORT} | — |
| **Admin WordPress** | http://localhost:${WP_PORT}/wp-admin | `.env` → `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` |
| **phpMyAdmin** | http://localhost:${PMA_PORT} | `.env` → `MYSQL_USER` / `MYSQL_PASSWORD` — serveur : `db` |

> ⚠️ Mots de passe de **dev local** (dans `.env`). À changer avant toute mise en production.

## 📦 Ce que la stack installe

- **WordPress** (php8.2-apache) + **WooCommerce** (zip déposé dans `library/plugins/`)
  — devise **DZD**, pays **Algérie**, **COD activé** (via `scripts/setup.sh`)
- **Thème** : celui déposé par l'owner dans `library/themes/` (installé auto par
  `setup.sh`) ; Astra sert de base par défaut
- **Plugins maison** `aod-cod-form` + `aod-client-dashboard` : **bind-montés**
  depuis `plugins-dev/` → toute édition sur le PC est immédiatement live
- **mu-plugin** : numérotation séquentielle des commandes
  (`wordpress/wp-content/mu-plugins/`)

## 🗂️ Structure

```
.
├── docker-compose.yml   # db (MariaDB) + wordpress + wpcli + phpmyadmin
├── .env                 # secrets & ports — NON versionné (partir de .env.example)
├── wordpress/           # le site — ÉDITABLE depuis ton PC (volume monté)
├── plugins-dev/         # les 2 plugins maison (bind-montés, live)
├── library/             # zips des thèmes/plugins tiers (installés par setup.sh)
├── scripts/preflight.sh # garde-fou d'isolation — AVANT docker compose up
└── scripts/setup.sh     # ré-installe/configure tout (idempotent)
```

## ⚙️ Commandes utiles

> **Toujours depuis le dossier du clone** (jamais `docker exec <nom-global>` —
> risque de piloter la stack d'un autre projet).

```bash
# Vérifier l'isolation puis démarrer / arrêter
bash scripts/preflight.sh
docker compose up -d
docker compose stop
docker compose down              # stop + supprime conteneurs (garde les volumes/données)

# Logs
docker compose logs -f wordpress

# WP-CLI (ligne de commande WordPress)
docker compose exec wpcli wp plugin list
docker compose exec wpcli wp theme list
docker compose exec wpcli wp option get blogname

# Commandes HPOS (le meta des commandes n'est PAS dans postmeta)
docker compose exec wpcli wp wc shop_order list --user=1 --fields=id,status
docker compose exec wpcli wp eval '$o=wc_get_order(123); echo $o->get_meta("_aod_wilaya_name");'

# Lint PHP (l'hôte n'a pas de PHP — toujours dans le conteneur)
docker compose exec wordpress php -l wp-content/plugins/aod-cod-form/aod-cod-form.php
```

## 🌐 Réseau sortant (API transporteurs)

Le resolver DNS interne de Docker ne résout pas toujours les domaines externes :
le bloc `dns: [8.8.8.8, 1.1.1.1]` de `docker-compose.yml` (services `wordpress`
et `wpcli`) est **indispensable** pour les appels API Yalidine/Noest/EcoTrack…
Si un clone neuf échoue sur des appels externes, vérifier ce bloc en premier.

## 🌍 Pack de langue arabe (RTL public)

Sans le pack core `ar`, `is_rtl()` reste faux et la zone **publique** s'affiche
en LTR même en arabe (le dashboard `/gestion`, lui, force son RTL tout seul).
`wp language core install ar` échoue souvent dans le conteneur (upgrader) —
installer à la main depuis l'hôte :

```bash
curl -sL -o /tmp/wp-ar.zip https://downloads.wordpress.org/translation/core/<version-WP>/ar.zip
unzip -o /tmp/wp-ar.zip -d wordpress/wp-content/languages/
```

## 🔑 Droits d'écriture (à faire une fois)

Les fichiers sont créés par Apache (uid 33). Pour les éditer depuis ton PC :

```bash
sudo setfacl -R    -m u:1000:rwX wordpress
sudo setfacl -R -d -m u:1000:rwX wordpress
```

**Et dans l'autre sens** : un dossier créé depuis ton PC naît en 755, propriété de
ton utilisateur — Apache ne peut plus rien créer dedans, et **chaque envoi d'image
depuis `/gestion` échoue** (« le fichier n'a pas pu être envoyé »). `preflight.sh`
répare (`chmod -R a+rwX` sur `wp-content/uploads`), et `setup.sh` sonde l'écriture
sous l'uid d'Apache : il refuse de terminer tant que les téléversements sont morts.
À la main si besoin :

```bash
chmod -R a+rwX wordpress/wp-content/uploads
```

## ⏭️ Étapes suivantes

1. Activer **WooCommerce** + les 2 plugins **aod-*** (si pas déjà fait par `setup.sh`)
2. Appliquer le **thème client** (logo + nom via le Personnalisateur)
3. Construire les **3 pages** Accueil / Boutique / Contact (cf. TEMPLATE.md §5)
4. Créer le compte gérant : **Utilisateurs → Accès Gérant**
5. Régler livraison / pixels / WhatsApp dans **`/gestion`**
