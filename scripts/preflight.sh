#!/usr/bin/env bash
# ───────────────────────────────────────────────────────────────
# preflight.sh — garde-fou d'ISOLATION. À lancer DEPUIS L'HÔTE,
# AVANT « docker compose up » :
#       bash scripts/preflight.sh
#
# Refuse de continuer (sortie ≠ 0) si le projet n'est pas correctement
# isolé — pour ne JAMAIS écraser ni piloter les conteneurs d'un autre
# projet (cause des installations qui atterrissaient sur le mauvais site).
# Vérifie : .env présent, PROJECT_SLUG / COMPOSE_PROJECT_NAME définis et
# non-défaut, aucun conteneur wp_*_<slug> appartenant à un autre dossier,
# ports WP_PORT/PMA_PORT libres. Répare au passage les droits de
# wp-content/uploads (bind-mount : un dossier créé côté hôte naît illisible
# en écriture pour Apache — et tout envoi d'image depuis /gestion échoue),
# ET ceux des dossiers qu'écrit l'updater WordPress (wp-content lui-même,
# plugins, themes, upgrade, upgrade-temp-backup, languages) — sans quoi
# `wp plugin update` échoue sur TOUT clone, alors que TEMPLATE.md §2 promet
# que c'est lui qui prend le relais des archives-planchers de `library/`.
# ───────────────────────────────────────────────────────────────
set -euo pipefail

cd "$(dirname -- "${BASH_SOURCE[0]}")/.." >/dev/null 2>&1
ROOT="$(pwd -P)"

grn(){ printf '\033[32m✓\033[0m %s\n' "$*"; }
ylw(){ printf '\033[33m⚠\033[0m %s\n' "$*"; }
fail(){ printf '\033[31m✗ %s\033[0m\n' "$*" >&2
        printf '\n\033[31mPréflight ÉCHOUÉ — corrige le .env puis relance. Aucun conteneur démarré.\033[0m\n' >&2
        exit 1; }

[ -f .env ] || fail ".env absent. Fais: cp .env.example .env  puis personnalise PROJECT_SLUG / ports."

# Charger le .env (fichier contrôlé par toi)
set -a; . ./.env; set +a

# 1) PROJECT_SLUG défini, non-défaut, format propre
[ -n "${PROJECT_SLUG:-}" ]        || fail "PROJECT_SLUG vide dans .env."
[ "${PROJECT_SLUG}" != "mon-projet" ] || fail "PROJECT_SLUG vaut encore le défaut « mon-projet ». Choisis un slug UNIQUE (ex. pantalon-femme)."
printf '%s' "$PROJECT_SLUG" | grep -qE '^[a-z0-9][a-z0-9-]*$' || fail "PROJECT_SLUG invalide « $PROJECT_SLUG » (attendu: [a-z0-9-])."
grn "PROJECT_SLUG = $PROJECT_SLUG"

# 2) COMPOSE_PROJECT_NAME défini, non-défaut
[ -n "${COMPOSE_PROJECT_NAME:-}" ]        || fail "COMPOSE_PROJECT_NAME vide dans .env."
[ "${COMPOSE_PROJECT_NAME}" != "mon-projet" ] || fail "COMPOSE_PROJECT_NAME vaut encore « mon-projet ». Mets une valeur unique."
grn "COMPOSE_PROJECT_NAME = $COMPOSE_PROJECT_NAME"

if command -v docker >/dev/null 2>&1; then
  # 3) Un conteneur wp_*_<slug> appartient-il déjà à un AUTRE dossier de projet ?
  for svc in app db cli pma; do
    name="wp_${svc}_${PROJECT_SLUG}"
    line="$(docker ps -a --filter "name=^${name}$" \
            --format '{{.Label "com.docker.compose.project.working_dir"}}' 2>/dev/null || true)"
    [ -n "$line" ] || continue
    if [ "$line" != "$ROOT" ]; then
      fail "Conteneur « $name » existe déjà pour un AUTRE projet (dossier: $line). Choisis un PROJECT_SLUG différent."
    fi
  done
  grn "Aucun conteneur wp_*_${PROJECT_SLUG} en conflit."

  # 4) Ports déjà publiés par un conteneur d'un autre projet ?
  for p in "${WP_PORT:-}" "${PMA_PORT:-}"; do
    [ -n "$p" ] || continue
    used="$(docker ps --format '{{.Names}} {{.Ports}}' \
            | grep -E "(^|[^0-9]):${p}->" \
            | grep -vE "wp_[a-z]+_${PROJECT_SLUG}( |\$)" || true)"
    [ -z "$used" ] || fail "Port $p déjà publié par un autre conteneur:
    ${used}
  Change WP_PORT/PMA_PORT dans .env."
  done
  grn "Ports ${WP_PORT:-?}/${PMA_PORT:-?} libres côté Docker."
else
  ylw "docker introuvable sur l'hôte — vérif conteneurs/ports sautée."
fi

# 5) Écriture des téléversements. Le site tourne sous www-data (uid 33) mais
#    wordpress/ est bind-monté depuis l'hôte : tout dossier créé côté hôte naît
#    en 755, propriété de l'utilisateur hôte — et Apache ne peut plus RIEN créer
#    dedans. Symptôme : chaque envoi d'image depuis /gestion échoue (« le fichier
#    n'a pas pu être envoyé »), y compris des mois plus tard, quand WordPress
#    tente de créer le dossier du mois suivant.
#    On corrige ICI parce qu'on est côté hôte, donc propriétaire des fichiers
#    fautifs ; le `|| true` ignore ceux créés par le conteneur (uid 33), qu'on
#    n'a pas le droit de toucher — et qui sont déjà écrivables par Apache.
mkdir -p wordpress/wp-content/uploads
chmod -R a+rwX wordpress/wp-content/uploads 2>/dev/null || true
grn "wp-content/uploads : écrivable par le serveur web."

# 5ter) LE MÊME DÉFAUT TUAIT LES MISES À JOUR, et personne ne le voyait — parce
#    qu'il ne se manifeste pas sur le chemin qu'on emprunte tous les jours.
#    `uploads/` était réparé ci-dessus depuis le 2026-07-28 ; `wp-content/` lui-même,
#    `plugins/` et `themes/` restaient en 755 propriété de l'utilisateur HÔTE. Or une
#    mise à jour WordPress écrit AILLEURS que dans uploads : elle crée
#    `wp-content/upgrade-temp-backup/` (sauvegarde de l'ancienne version), déballe
#    dans `wp-content/upgrade/`, puis remplace le dossier dans `plugins/` ou `themes/`.
#    Résultat mesuré le 2026-08-03 : `wp plugin update woocommerce` téléchargeait
#    l'archive, puis échouait sur « Impossible de créer le répertoire
#    upgrade-temp-backup » — un message qui ne nomme NI le bind-mount, NI les droits,
#    NI le fait que la même commande ne marchera jamais sur aucun clone.
#    🚨 Ce qui rend le défaut coûteux, c'est ce qu'il démentait : TEMPLATE.md §2 promet
#    que les archives versionnées de `library/` sont « des planchers, pas des versions
#    figées : `wp plugin update` / `wp theme update` prennent le relais ». Ce relais
#    n'existait pas. Un clone restait donc à VIE sur la version du plancher — y compris
#    pour un correctif de sécurité WooCommerce — sans qu'aucune porte ne rougisse : les
#    tests et la recette éprouvent le code, jamais la capacité à le mettre à jour.
#    On ne répare QUE les dossiers que l'updater touche (pas un `a+rwX` sur tout
#    wp-content, qui embarquerait les données du site sans raison).
for _d in wordpress/wp-content wordpress/wp-content/upgrade \
          wordpress/wp-content/upgrade-temp-backup wordpress/wp-content/languages; do
	mkdir -p "$_d" 2>/dev/null || true
	chmod a+rwX "$_d" 2>/dev/null || true
done

#    ⚠️ `plugins/` et `themes/` exigent le -R, et ça ne se devine pas : rendre le
#    dossier PARENT écrivable suffit à créer `woocommerce/`, pas à REMPLACER ce qu'il
#    contient. Mesuré le 2026-08-03 : avec le seul parent réparé, l'update passait
#    l'étape « Retrait de l'ancienne version » puis échouait sur ~200 fichiers
#    (« certains fichiers n'ont pas pu être copiés »), parce que supprimer
#    `patterns/banner.php` demande le droit d'écrire sur `patterns/`, pas sur
#    `woocommerce/`. WordPress a restauré depuis upgrade-temp-backup et le site est
#    resté debout — c'est précisément à ça qu'il sert, et c'est pour ça qu'il est
#    créé ci-dessus AVANT d'élargir ici.
#    Le -R ne déborde PAS sur le dépôt : `plugins/aod-cod-form` et
#    `plugins/aod-client-dashboard` sont, côté hôte, des points de montage **vides**
#    (le contenu vit dans `plugins-dev/`, monté par Docker par-dessus). Le -R ne voit
#    donc que les extensions tierces et les deux dossiers creux — vérifié, pas supposé.
for _d in wordpress/wp-content/plugins wordpress/wp-content/themes; do
	mkdir -p "$_d" 2>/dev/null || true
	chmod -R a+rwX "$_d" 2>/dev/null || true
done
unset _d
grn "wp-content : mises à jour de plugins/thèmes possibles (upgrade-temp-backup + remplacement)."

# 5bis) Arborescence `library/` — là où l'owner dépose thèmes et plugins tiers.
#    Rien n'y est versionné (poids + licences, TEMPLATE.md §2), donc un `git clone`
#    frais n'a même pas les DOSSIERS : on ne sait pas où déposer les .zip. Pire, si
#    `docker compose up` passe en premier, Docker crée `library/` en **root** et
#    l'utilisateur ne peut plus rien y mettre — c'est arrivé sur le clone de
#    référence (constaté le 2026-07-28).
#    ⚠️ Non bloquant : depuis la même date, `setup.sh` installe le socle
#    (WooCommerce + Astra) depuis wordpress.org quand `library/` est vide. Ces
#    dossiers servent à FOURNIR un thème client, plus à faire démarrer le clone.
for d in library/themes library/plugins; do
  if [ -d "$d" ]; then continue; fi
  if mkdir -p "$d" 2>/dev/null; then
    grn "Créé : $d (dépose ici les .zip du client).
  ⚠️ Si les conteneurs tournaient DÉJÀ, recrée-les pour qu'ils voient ce dossier :
      docker compose up -d --force-recreate wpcli wordpress
  (Docker résout le bind-mount au démarrage : un dossier créé après coup reste
   invisible du conteneur — constaté le 2026-07-28.)"
  else
    ylw "$d absent et non créable — library/ appartient sans doute à root (Docker l'a créé avant toi).
  Depuis l'hôte, dans le dossier du clone :
      sudo chown -R \"\$USER\":\"\$USER\" library
  Sans ça, tu ne pourras pas y déposer le thème du client."
  fi
done

# 6) Activer le hook pre-push versionné (joue les tests unitaires avant tout push).
#    core.hooksPath n'est pas versionné : on le (re)pointe ici, à chaque clone.
if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1 \
   && [ -f tools/git-hooks/pre-push ]; then
  chmod +x tools/git-hooks/pre-push 2>/dev/null || true
  if git config core.hooksPath tools/git-hooks 2>/dev/null; then
    grn "Hook pre-push activé (tests avant push ; contourner: git push --no-verify)."
  else
    ylw "Hook pre-push non activé (git config a échoué) — actif au besoin: git config core.hooksPath tools/git-hooks"
  fi
fi

printf '\n'
grn "Préflight OK — isolation validée. Tu peux lancer : docker compose up -d"
