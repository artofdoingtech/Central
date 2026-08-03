#!/usr/bin/env bash
#
# Tests unitaires du template — à lancer depuis le dossier du clone.
#
#   bash tests/run.sh
#
# Ils s'exécutent DANS le conteneur, via WP-CLI : les classes testées sont les
# vraies, chargées par un vrai WordPress. Aucun téléchargement, aucun Composer :
# un clone tout neuf peut les lancer le jour de son installation.
#
set -euo pipefail

# 🪟 WINDOWS / GIT BASH — sans cette ligne, CE SCRIPT NE PEUT PAS S'EXÉCUTER.
# MSYS (Git Bash) réécrit tout argument qui ressemble à un chemin absolu POSIX en
# chemin Windows AVANT que le programme ne le voie : `/tests/all.php` devient
# `C:/Program Files/Git/tests/all.php`. Le `test -f` ci-dessous échoue donc
# toujours, et le script s'arrête sur « /tests n'est pas monté » — un diagnostic
# FAUX, qui envoie chercher un volume manquant dans docker-compose.yml alors que
# le montage est correct et que `wp eval-file` aurait parfaitement tourné.
# 🚨 Conséquence en aval : `tools/git-hooks/pre-push` appelle ce script, donc sur
# Windows AUCUN push ne passait — la porte refusait pour une raison inventée.
# Sans effet sur Linux/macOS : la variable y est simplement ignorée.
export MSYS_NO_PATHCONV=1

cd "$(dirname "${BASH_SOURCE[0]}")/.."

if ! docker compose ps --status running --services 2>/dev/null | grep -qx wpcli; then
  echo "✗ Le service wpcli n'est pas démarré." >&2
  echo "  docker compose up -d" >&2
  exit 1
fi

# Le dossier tests/ est monté dans wpcli (cf. docker-compose.yml). Si le montage
# manque, c'est que le conteneur date d'avant l'ajout du volume : on le dit, plutôt
# que de laisser WP-CLI échouer sur un « file not found » incompréhensible.
if ! docker compose exec -T wpcli test -f /tests/all.php 2>/dev/null; then
  echo "✗ /tests n'est pas monté dans le conteneur wpcli." >&2
  echo "  Applique le volume puis relance :  docker compose up -d wpcli" >&2
  exit 1
fi

# ─── Sujets de commit, pour le cliquet de dérive documentaire (dette D30) ────
#
# 🚨 POURQUOI ICI, ET PAS DANS LE TEST. `test-doc-derive.php` recoupait deux
# DOCUMENTS entre eux (en-têtes ⇄ §C.3, TEMPLATE.md ⇄ §C.3) — jamais un document
# au DÉPÔT. C'est ainsi que l'étape 42.7 a pu vivre uniquement en git : aucune
# règle ne la cherchait. Mais le conteneur de tests n'a pas `git` (vérifié), et
# `.git/logs/HEAD` est un reflog — vide sur un clone frais, donc inutilisable.
# L'hôte, lui, a git : il produit la liste, le test la consomme. Chacun fait ce
# qu'il sait faire.
#
# Fichier ignoré par git (cf. .gitignore) : c'est un artefact, pas une source.
if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then
	git log --format=%s > tests/.git-sujets.txt 2>/dev/null || printf '#NO_GIT\n' > tests/.git-sujets.txt
else
	printf '#NO_GIT\n' > tests/.git-sujets.txt
fi

docker compose exec -T wpcli wp eval-file /tests/all.php
