#!/usr/bin/env bash
# Visuels du catalogue de démonstration — DÉVELOPPEMENT LOCAL UNIQUEMENT.
#
#   bash scripts/dev-assets-demo.sh
#
# Télécharge dans `library/demo/produits/` les photos utilisées par
# `scripts/dev-seed-catalogue.php` (2 produits complets : casque audio, sac à dos).
#
# POURQUOI CE SCRIPT EXISTE, ET POURQUOI IL TOURNE SUR L'HÔTE :
#  · `library/` n'est PAS versionné (poids + licences, TEMPLATE.md §2) — on ne
#    committe donc pas 20 JPEG dans le template. Le script, lui, l'est : n'importe
#    quel clone peut rejouer le catalogue de démo à l'identique.
#  · Le conteneur n'a pas d'internet sortant fiable (TEMPLATE.md §3). L'hôte, si.
#    Le seed lit ensuite les fichiers via le montant `./library:/library:ro`.
#
# LICENCE DES VISUELS : StockSnap.io — Creative Commons CC0 1.0 (domaine public,
# usage commercial autorisé, aucune attribution requise). Vérifié via l'API
# Openverse (`license: cc0`, `source: stocksnap`) le 2026-07-29.
#
# Idempotent : un fichier déjà présent et non vide n'est pas retéléchargé.

set -uo pipefail

DEST="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/library/demo/produits"
UA="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120 Safari/537.36"
CDN="https://cdn.stocksnap.io/img-thumbs/960w"

# <nom-de-fichier>:<identifiant StockSnap>
# Les noms sont le CONTRAT avec dev-seed-catalogue.php : le seed cherche
# `<nom>.jpg`, jamais un identifiant. Renommer ici casse le seed — pas l'inverse.
ASSETS=(
  # Produit 1 — casque audio sans fil « AURA ANC 40 »
  "casque-hero:G2V8G1H1AQ"
  "casque-noir:EXCBJA3FFQ"
  "casque-blanc:UXKE7VWPCY"
  "casque-bordeaux:16FDCF0DFC"
  "casque-detail-1:2D9XMVWNKU"
  "casque-detail-2:1Y69ONYCCZ"
  "casque-detail-3:WM2HLLDW0K"
  "casque-detail-4:6DB784AEFE"
  "casque-lifestyle-1:NOLZXJLYKF"
  "casque-lifestyle-2:3YOVALG5DX"
  # Produit 2 — sac à dos urbain « NOMAD 25L »
  "sac-hero:BWPRK5DEF0"
  "sac-noir:FDMAZ9AACE"
  "sac-olive:THRCTPZSJP"
  "sac-moutarde:LE23HHZVIF"
  "sac-interieur:4MT4167LN3"
  "sac-voyage:40ZTQ6FVOF"
  "sac-flatlay:0JXUC43X55"
  "sac-duo:FPZ4QSV64F"
  "sac-lifestyle:KEUH8PXYKI"
  "sac-contenu:ML3FHWZ5R8"
)

mkdir -p "$DEST" || exit 1
echo "==> Visuels de démonstration → $DEST"

ok=0; skip=0; fail=0
for entry in "${ASSETS[@]}"; do
  name="${entry%%:*}"
  id="${entry##*:}"
  out="$DEST/$name.jpg"

  # Déjà là et plausible (> 5 ko) : on ne retélécharge pas.
  if [ -s "$out" ] && [ "$(stat -c%s "$out")" -gt 5120 ]; then
    skip=$((skip + 1))
    continue
  fi

  code=$(curl -sS -m 30 -A "$UA" -e "https://stocksnap.io/" \
              -o "$out" -w '%{http_code}' "$CDN/$id.jpg" 2>/dev/null)

  # Le CDN répond 200 + HTML sur certaines erreurs : on vérifie que c'est un JPEG.
  if [ "$code" = "200" ] && head -c 2 "$out" | grep -q $'\xff\xd8'; then
    ok=$((ok + 1))
  else
    rm -f "$out"
    echo "    ✗ $name ($id) — HTTP $code"
    fail=$((fail + 1))
  fi
done

echo "==> $ok téléchargé(s), $skip déjà présent(s), $fail échec(s)."
[ "$fail" -eq 0 ] || {
  echo "    ⚠️  Des visuels manquent : le seed créera les produits SANS ces images."
  exit 1
}
echo "    Suite : docker compose exec -T wpcli wp eval-file /scripts/dev-seed-catalogue.php create"
