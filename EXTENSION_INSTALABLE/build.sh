#!/usr/bin/env bash
#
# build.sh — Génère les .zip installables de l'extension « AOD Gestion » à
# partir des sources de EXTENSION_INSTALABLE/plugins/.
#
# Produit 3 zips dans dist/, un par plugin WordPress à installer sur le site
# client (dans cet ordre : WooCommerce doit déjà être actif sur le site) :
#   1. aod-cod-form.zip
#   2. aod-client-dashboard.zip
#   3. aod-gestion-license.zip
#
# Usage :
#   bash build.sh
#
# ⚠️ Ne zippe JAMAIS outils-vendeur/ (l'outil qui émet les clés, réservé au
# fournisseur) — il n'a rien à faire chez le client.
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd -P)"
PLUGINS_DIR="$SCRIPT_DIR/plugins"
DIST_DIR="$SCRIPT_DIR/dist"

if [ -t 1 ]; then C_OK=$'\033[32m'; C_B=$'\033[1m'; C_X=$'\033[0m'; else C_OK=; C_B=; C_X=; fi

rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

for plugin_dir in "$PLUGINS_DIR"/*/; do
	slug="$(basename "$plugin_dir")"
	zip_path="$DIST_DIR/$slug.zip"

	echo "${C_B}→ $slug${C_X}"

	(
		cd "$PLUGINS_DIR"
		zip -rq "$zip_path" "$slug" \
			-x "*/.DS_Store" \
			-x "*/Thumbs.db" \
			-x "*/.git/*"
	)

	echo "  ${C_OK}✓ $zip_path${C_X}"
done

echo
echo "${C_B}Terminé.${C_X} 3 paquets dans $DIST_DIR/ — à installer chez le client dans"
echo "l'ordre : aod-cod-form → aod-client-dashboard → aod-gestion-license."
echo "Voir README.md pour la procédure complète."
