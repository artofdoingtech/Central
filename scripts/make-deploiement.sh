#!/usr/bin/env bash
# ───────────────────────────────────────────────────────────────
# make-deploiement.sh — bâtit le PAQUET DE MISE EN LIGNE d'un clone.
#
#   bash scripts/make-deploiement.sh <domaine> [dossier-de-sortie]
#   bash scripts/make-deploiement.sh boutique-du-client.com
#
# Produit, dans `Deploiement/` (gitignoré) :
#   · public_html.zip   — les fichiers du site (cœur WP + wp-content)
#   · database.sql.gz   — la base, URLs DÉJÀ réécrites vers <domaine>
#   · wp-config.php     — modèle à compléter (identifiants de l'hébergeur)
#   · MANIFESTE.md      — QUOI a été empaqueté, DEPUIS QUEL COMMIT, QUAND
#
# ───────────────────────────────────────────────────────────────
# 🚨 POURQUOI CE SCRIPT EXISTE — le défaut qu'il ferme (relevé le 2026-08-03)
#
# Ce paquet était fabriqué À LA MAIN. Conséquence mesurée : le dossier
# `Deploiement/` du clone de référence datait du 25 juillet, soit **129 commits
# de retard** — il ne portait ni les Phases 45→48, ni les trois correctifs du
# panier COD, dont celui où un second onglet faisait commander un article que la
# page n'affichait pas. Un `deploy/` plus ancien encore (13 juin) traînait à côté.
#
# Ce qui rend ce défaut PARTICULIER, et pourquoi aucune porte ne pouvait le voir :
# ces dossiers sont **gitignorés**. `git status` est propre, les 1713 tests sont
# verts, les 22 portes de recette passent — et le fichier qu'on met réellement en
# ligne a sept semaines. *Le dépôt ne peut pas signaler la péremption de ce qu'il
# ne suit pas.* D'où le MANIFESTE : le paquet porte désormais lui-même son commit
# d'origine, donc il est datable sans le croire sur parole.
# ───────────────────────────────────────────────────────────────
set -euo pipefail

cd "$(dirname -- "${BASH_SOURCE[0]}")/.." >/dev/null 2>&1
ROOT="$(pwd -P)"

grn(){ printf '\033[32m✓\033[0m %s\n' "$*"; }
ylw(){ printf '\033[33m⚠\033[0m %s\n' "$*"; }
die(){ printf '\033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

DOMAIN="${1:-}"
OUTDIR="${2:-Deploiement}"

[ -n "$DOMAIN" ] || die "Usage : bash scripts/make-deploiement.sh <domaine> [dossier]
       ex.  bash scripts/make-deploiement.sh boutique-du-client.com
       (le domaine n'est JAMAIS en dur : ce dépôt est un template, cf. TEMPLATE.md §1)"

# Un domaine, pas une URL : on préfixe https:// nous-mêmes pour que la réécriture
# porte sur une chaîne prévisible. Coller « https:// » ici produirait
# « https://https://… » dans 100 000 lignes de dump, en silence.
case "$DOMAIN" in
	http://*|https://*) die "Donne le DOMAINE seul (sans http:// ni https://) : ${DOMAIN#*//}" ;;
	*/*)                die "Donne le DOMAINE seul (sans chemin) : ${DOMAIN%%/*}" ;;
esac

command -v docker >/dev/null 2>&1 || die "docker introuvable."
docker compose ps --status running --services 2>/dev/null | grep -qx wpcli \
	|| die "Le service wpcli n'est pas démarré.  docker compose up -d"

# ── 1) L'estampille, AVANT tout le reste ────────────────────────────────────
# Un paquet non datable est exactement le défaut qu'on ferme : on refuse donc de
# bâtir depuis un arbre sale, sinon le commit inscrit au manifeste désignerait un
# état qui n'a jamais existé.
COMMIT="$(git rev-parse --short HEAD 2>/dev/null || echo '(hors dépôt git)')"
COMMIT_DATE="$(git log -1 --format=%cd --date=short 2>/dev/null || echo '?')"
BUILD_DATE="$(date -u +'%Y-%m-%d %H:%M UTC')"
DIRTY=""
if ! git diff --quiet HEAD 2>/dev/null || [ -n "$(git status --porcelain 2>/dev/null | grep -v '^??' || true)" ]; then
	DIRTY="oui"
	if [ "${AOD_ALLOW_DIRTY:-0}" != "1" ]; then
		die "L'arbre de travail porte des modifications non commitées.
       Le manifeste inscrirait le commit ${COMMIT}, qui ne décrit PAS ce qui serait empaqueté.
       Commite d'abord — ou, en connaissance de cause :  AOD_ALLOW_DIRTY=1 $0 $DOMAIN"
	fi
	ylw "Arbre SALE empaqueté (AOD_ALLOW_DIRTY=1) — le manifeste le dira."
fi

# ── 1bis) Aucune fixture de recette dans la base qu'on s'apprête à empaqueter ──
# 🚨 Ce n'est pas de l'hygiène, c'est de la sécurité. `tools/recette/run.sh` sème
# des comptes CONFIRMATEUR (`aod_recette_conf*`) dont le mot de passe est engendré
# par le seed, plus des commandes et des produits témoins. Deux façons d'en
# hériter, aucune bruyante :
#   · bâtir le paquet PENDANT qu'une recette tourne (elles durent ~25 min) ;
#   · bâtir après une recette INTERROMPUE — son `trap` de purge n'a pas tourné.
# Dans les deux cas on livrerait en production des comptes à privilèges dont le
# mot de passe a transité par l'environnement, au milieu de fausses commandes.
# On refuse : c'est réparable en une commande, l'inverse ne l'est pas.
FIXTURES="$(docker compose exec -T wpcli wp user list --format=csv --fields=user_login 2>/dev/null \
	| grep -c '^aod_recette' || true)"
if [ "${FIXTURES:-0}" -gt 0 ] 2>/dev/null; then
	die "La base contient $FIXTURES compte(s) témoin(s) de recette (aod_recette_*).
       Soit une recette tourne en ce moment (attends la fin), soit l'une a été
       interrompue sans purger. Empaqueter maintenant livrerait des comptes
       CONFIRMATEUR et de fausses commandes en production.
       Purge :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase22.php cleanup"
fi

SRC_URL="$(docker compose exec -T wpcli wp option get siteurl 2>/dev/null | tr -d '\r\n')"
[ -n "$SRC_URL" ] || die "Impossible de lire l'URL actuelle du site (wpcli injoignable ?)."
DST_URL="https://${DOMAIN}"

printf '\n\033[1mPaquet de mise en ligne\033[0m\n'
printf '  source      : %s\n' "$SRC_URL"
printf '  destination : %s\n' "$DST_URL"
printf '  commit      : %s (%s)%s\n\n' "$COMMIT" "$COMMIT_DATE" "${DIRTY:+ — ARBRE SALE}"

mkdir -p "$OUTDIR"

# ── 2) La base, réécrite SANS toucher à la base locale ───────────────────────
# 🚨 `wp search-replace` sans `--export` écrit DANS la base. Le faire ici
# casserait le clone de développement (toutes ses URLs pointeraient chez le
# client) et le casserait EN SILENCE — on ne s'en apercevrait qu'au prochain
# `run.sh`. `--export` fait la substitution à la volée, vers un fichier, et ne
# modifie rien. C'est la seule forme acceptable dans ce script.
printf '  · base de données (réécriture des URLs à la volée)…\n'
docker compose exec -T wpcli wp search-replace "$SRC_URL" "$DST_URL" \
	--all-tables-with-prefix --precise --export=/tmp/aod-deploy.sql --quiet >/dev/null 2>&1 \
	|| die "L'export de la base a échoué."
docker compose cp wpcli:/tmp/aod-deploy.sql "$OUTDIR/database.sql" >/dev/null 2>&1 \
	|| die "Impossible de récupérer le dump depuis le conteneur."
docker compose exec -T wpcli rm -f /tmp/aod-deploy.sql >/dev/null 2>&1 || true

# Filet : si l'ancienne URL survit dans le dump, la réécriture n'a pas fait son
# travail (préfixes de tables inattendus, sérialisation exotique). Mieux vaut
# refuser que livrer une base qui renverra le visiteur vers localhost.
if grep -qF "$SRC_URL" "$OUTDIR/database.sql" 2>/dev/null; then
	die "L'URL source ($SRC_URL) subsiste dans le dump — réécriture incomplète, paquet NON livré."
fi
gzip -9 -kf "$OUTDIR/database.sql"
grn "database.sql + database.sql.gz ($(du -h "$OUTDIR/database.sql.gz" | cut -f1)) — 0 occurrence de l'URL source."

# ── 3) Les fichiers ─────────────────────────────────────────────────────────
# On empaquette depuis `wordpress/`, où vit le site réellement servi. Les deux
# plugins maison y sont montés par Docker : on les copie donc depuis
# `plugins-dev/` (la source), sinon le zip contiendrait deux dossiers VIDES —
# c'est-à-dire un site sans /gestion ni formulaire COD, et sans le dire.
printf '  · fichiers du site…\n'
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

tar -C wordpress -cf - \
	--exclude='./wp-content/upgrade' \
	--exclude='./wp-content/upgrade-temp-backup' \
	--exclude='./wp-content/uploads/aod-logs' \
	--exclude='./wp-content/debug.log' \
	--exclude='./wp-config.php' \
	. 2>/dev/null | tar -C "$STAGE" -xf -

for p in aod-cod-form aod-client-dashboard; do
	rm -rf "$STAGE/wp-content/plugins/$p"
	cp -a "plugins-dev/$p" "$STAGE/wp-content/plugins/$p"
	# Les sources de traduction ne servent pas en production ; les .mo, si.
	rm -rf "$STAGE/wp-content/plugins/$p/bin" "$STAGE/wp-content/plugins/$p/node_modules"
	[ -s "$STAGE/wp-content/plugins/$p/languages/$p-ar.mo" ] \
		|| die "$p : le .mo arabe est absent ou vide — le site ne serait pas bilingue.
       Lance : bash plugins-dev/$p/bin/make-translations.sh"
done
grn "les 2 plugins maison empaquetés depuis plugins-dev/ (.mo arabe présent)"

# `.user.ini` — la limite d'envoi annoncée par la boutique (Phase 47.6bis). Sans
# lui, l'hébergeur applique SA valeur et /gestion promet 5 Mo qu'il refuse.
cat > "$STAGE/.user.ini" <<'INI'
; Limites d'envoi — doivent rester ≥ à ce que /gestion annonce (cf. TEMPLATE.md §3).
upload_max_filesize = 8M
post_max_size = 16M
memory_limit = 256M
max_execution_time = 120
INI

( cd "$STAGE" && zip -q -r -9 "$ROOT/$OUTDIR/public_html.zip" . -x '*.DS_Store' )
grn "public_html.zip ($(du -h "$OUTDIR/public_html.zip" | cut -f1))"

# ── 4) wp-config.php modèle ─────────────────────────────────────────────────
# Modèle À COMPLÉTER : aucun identifiant réel n'est écrit ici. Les sels sont
# laissés en clair à remplacer — les copier depuis le clone de dev donnerait au
# site en ligne les mêmes secrets qu'une machine de développement.
cat > "$OUTDIR/wp-config.php" <<CONFIG
<?php
/**
 * wp-config.php — MODÈLE à compléter chez l'hébergeur.
 * Engendré par scripts/make-deploiement.sh depuis le commit $COMMIT ($BUILD_DATE).
 */

// ── À REMPLIR : identifiants de la base créée dans le panneau de l'hébergeur ──
define( 'DB_NAME',     'A_REMPLIR' );
define( 'DB_USER',     'A_REMPLIR' );
define( 'DB_PASSWORD', 'A_REMPLIR' );
define( 'DB_HOST',     'localhost' );
define( 'DB_CHARSET',  'utf8mb4' );
define( 'DB_COLLATE',  '' );

// ── À REMPLIR : https://api.wordpress.org/secret-key/1.1/salt/ ────────────────
define( 'AUTH_KEY',         'A_REMPLIR' );
define( 'SECURE_AUTH_KEY',  'A_REMPLIR' );
define( 'LOGGED_IN_KEY',    'A_REMPLIR' );
define( 'NONCE_KEY',        'A_REMPLIR' );
define( 'AUTH_SALT',        'A_REMPLIR' );
define( 'SECURE_AUTH_SALT', 'A_REMPLIR' );
define( 'LOGGED_IN_SALT',   'A_REMPLIR' );
define( 'NONCE_SALT',       'A_REMPLIR' );

\$table_prefix = 'wp_';

define( 'WP_DEBUG', false );
define( 'DISALLOW_FILE_EDIT', true );

// Le cron de WordPress ne se déclenche que sur une visite : sans cron SERVEUR,
// le suivi des colis reste figé (cf. docs/CRON-SERVEUR.md — à faire À CHAQUE
// mise en ligne, ce n'est pas facultatif).
define( 'DISABLE_WP_CRON', true );

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
CONFIG
grn "wp-config.php (modèle — aucun secret réel)"

# ── 5) Le manifeste : ce qui rend le paquet datable ─────────────────────────
cat > "$OUTDIR/MANIFESTE.md" <<MANIFEST
# Manifeste du paquet — \`$DOMAIN\`

> Engendré par \`scripts/make-deploiement.sh\`. **Ne pas éditer à la main** :
> régénère plutôt, c'est une commande.

| | |
|---|---|
| **Domaine cible** | \`$DST_URL\` |
| **Bâti le** | $BUILD_DATE |
| **Depuis le commit** | \`$COMMIT\` (du $COMMIT_DATE) |
| **Arbre de travail** | ${DIRTY:+⚠️ SALE — des modifications non commitées ont été empaquetées}${DIRTY:-propre} |
| **WordPress** | $(docker compose exec -T wpcli wp core version 2>/dev/null | tr -d '\r\n') |
| **WooCommerce** | $(docker compose exec -T wpcli wp plugin get woocommerce --field=version 2>/dev/null | tr -d '\r\n') |
| **Thème actif** | $(docker compose exec -T wpcli wp theme list --status=active --field=name 2>/dev/null | tr -d '\r\n') $(docker compose exec -T wpcli wp theme list --status=active --field=version 2>/dev/null | tr -d '\r\n') |
| **aod-cod-form** | $(grep -m1 -oP 'Version:\s*\K[0-9.]+' plugins-dev/aod-cod-form/aod-cod-form.php) |
| **aod-client-dashboard** | $(grep -m1 -oP 'Version:\s*\K[0-9.]+' plugins-dev/aod-client-dashboard/aod-client-dashboard.php) |

## Est-il encore à jour ?

\`\`\`bash
git log --oneline $COMMIT..HEAD | wc -l    # 0 = le paquet est au niveau du dépôt
\`\`\`

Tout autre chiffre = autant de commits que ce paquet **ne porte pas**. Régénère :

\`\`\`bash
bash scripts/make-deploiement.sh $DOMAIN
\`\`\`

## Contenu

| Fichier | Rôle |
|---|---|
| \`public_html.zip\` | Les fichiers du site. À décompresser dans \`public_html\`. Contient un \`.user.ini\` qui tient la limite d'envoi annoncée par \`/gestion\`. |
| \`database.sql.gz\` | La base, **URLs déjà réécrites** vers \`$DST_URL\`. À importer via phpMyAdmin. |
| \`database.sql\` | La même, non compressée. |
| \`wp-config.php\` | **Modèle** : base et sels à remplir chez l'hébergeur. Aucun secret réel n'y figure. |

## Après la mise en ligne — non facultatif

1. **Cron serveur** toutes les 15 min → \`docs/CRON-SERVEUR.md\`. Sans lui, le
   suivi des colis reste figé et les stats de livraison sont fausses.
2. Vérifier l'envoi d'une image depuis \`/gestion\` (droits d'écriture sur \`uploads/\`).
3. Passer le site en arabe et contrôler le RTL sur mobile.
MANIFEST
grn "MANIFESTE.md — le paquet porte son commit d'origine"

printf '\n\033[32m✅ Paquet prêt dans %s/\033[0m  (commit %s)\n' "$OUTDIR" "$COMMIT"
printf '   Relire le manifeste avant d’envoyer : %s/MANIFESTE.md\n\n' "$OUTDIR"
