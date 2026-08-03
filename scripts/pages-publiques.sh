#!/bin/bash
# ───────────────────────────────────────────────────────────────────────────────
# Socle des 3 pages publiques : Accueil · Boutique · Contact.
#
#   docker compose exec wpcli bash /scripts/pages-publiques.sh
#
# Idempotent : relançable sans rien casser. À RELANCER APRÈS AVOIR ACTIVÉ LE THÈME
# DU CLIENT — l'assignation du menu dépend des emplacements déclarés par le thème,
# qui changent d'un thème à l'autre.
#
# Ce que fait ce script :
#   1. crée/retrouve les 3 pages et les marque (_aod_page_key) pour que le plugin
#      sache les reconnaître (traduction du menu, cf. AOD_COD_Pages) ;
#   2. met Accueil en page d'accueil du site ;
#   3. construit « Menu principal » = Accueil / Boutique / Contact et l'assigne à
#      TOUS les emplacements de menu du thème (primary, mobile_menu…) ;
#   4. met à la corbeille le tunnel WooCommerce (Panier / Commande / Mon compte)
#      et la « Sample Page » de WordPress.
#
# Le CONTENU des pages n'est volontairement PAS écrit en base : ce sont des
# shortcodes ([aod_home], [aod_contact_form]). Le texte vit donc dans le code du
# plugin, passe par gettext, et suit le switcher FR/AR. Une page dont le contenu
# serait tapé en base resterait en français en arabe.
# ───────────────────────────────────────────────────────────────────────────────
set -u

echo "=== Socle des 3 pages publiques ==="

# ─── 1. Les 3 pages ────────────────────────────────────────────────────────────
# Retrouve une page par sa clé (meta _aod_page_key), sinon par son slug, sinon la
# crée. La recherche par clé d'abord permet à un clone de RENOMMER ses pages sans
# que le script en recrée des doublons au prochain passage.
page_id() {
  local key="$1" slug="$2" title="$3" content="$4"
  local id

  id=$(wp post list --post_type=page --post_status=publish,draft,pending \
        --meta_key=_aod_page_key --meta_value="$key" --field=ID --posts_per_page=1 2>/dev/null | head -1)

  if [ -z "$id" ]; then
    id=$(wp post list --post_type=page --post_status=publish,draft,pending \
          --name="$slug" --field=ID --posts_per_page=1 2>/dev/null | head -1)
  fi

  if [ -z "$id" ]; then
    id=$(wp post create --post_type=page --post_status=publish \
          --post_title="$title" --post_name="$slug" --post_content="$content" --porcelain)
    echo "    + page « $title » créée (#$id)" >&2
  else
    echo "    = page « $title » déjà là (#$id)" >&2
  fi

  wp post meta update "$id" _aod_page_key "$key" >/dev/null
  echo "$id"
}

echo "--- Pages ---"

# Accueil : coquille vide + shortcode. Tout le texte est dans le plugin (traduisible).
HOME_ID=$(page_id "home" "accueil" "Accueil" "[aod_home]")

# Boutique : c'est la page Boutique de WooCommerce (créée par WooCommerce à
# l'installation). On ne la recrée surtout pas — on adopte celle de Woo, sinon la
# grille produits ne s'afficherait pas. On la renomme juste et on la marque.
SHOP_ID=$(wp option get woocommerce_shop_page_id 2>/dev/null || echo 0)
if [ "${SHOP_ID:-0}" -gt 0 ]; then
  wp post update "$SHOP_ID" --post_title="Boutique" --post_name="boutique" >/dev/null
  wp post meta update "$SHOP_ID" _aod_page_key shop >/dev/null
  echo "    = page Boutique = page Woo Shop (#$SHOP_ID), renommée"
else
  echo "    ⚠ page Boutique WooCommerce introuvable — WooCommerce est-il activé ?"
fi

CONTACT_ID=$(page_id "contact" "contact" "Contact" "[aod_contact_form]")

# ─── 2. Page d'accueil du site ─────────────────────────────────────────────────
wp option update show_on_front page >/dev/null
wp option update page_on_front "$HOME_ID" >/dev/null
# Pas de blog dans ce template : aucune page d'articles.
wp option update page_for_posts 0 >/dev/null
echo "--- Accueil du site → page #$HOME_ID ---"

# ─── 3. Le menu ────────────────────────────────────────────────────────────────
echo "--- Menu ---"
MENU="Menu principal"
if ! wp menu list --fields=name --format=csv 2>/dev/null | tr -d '"' | grep -qx "$MENU"; then
  wp menu create "$MENU" >/dev/null
  echo "    + menu « $MENU » créé"
fi

# On repart d'un menu propre : sinon un 2e passage empile les doublons.
for item in $(wp menu item list "$MENU" --fields=db_id --format=csv 2>/dev/null | tail -n +2); do
  wp menu item delete "$item" >/dev/null 2>&1
done

wp menu item add-post "$MENU" "$HOME_ID" --title="Accueil" >/dev/null
[ "${SHOP_ID:-0}" -gt 0 ] && wp menu item add-post "$MENU" "$SHOP_ID" --title="Boutique" >/dev/null
wp menu item add-post "$MENU" "$CONTACT_ID" --title="Contact" >/dev/null
echo "    = 3 entrées : Accueil · Boutique · Contact"

# Assignation aux emplacements du thème actif.
#
# 🪤 Le piège Astra : sans `mobile_menu`, le menu off-canvas mobile retombe sur
# wp_page_menu() et RÉEXPOSE Panier / Commande / Mon compte — la règle 4 tombe, et
# uniquement sur mobile, c'est-à-dire là où est le trafic. Comme les noms des
# emplacements changent d'un thème à l'autre, on assigne à TOUS ceux que le thème
# déclare, plutôt que de parier sur une liste de noms connus.
#
# Une seule exception : les emplacements de COMPTE (loggedin_account_menu chez
# Astra). Ils appartiennent au tunnel qu'on supprime ; y accrocher la nav n'aurait
# aucun sens sur une boutique sans comptes.
# `wp menu location list` n'accepte PAS --fields (contrairement aux autres commandes
# `list` de WP-CLI). On prend donc la 1re colonne du CSV.
LOCATIONS=$(wp menu location list --format=csv 2>/dev/null | tail -n +2 | cut -d, -f1 | tr -d '"')
if [ -z "$LOCATIONS" ]; then
  THEME=$(wp theme list --status=active --field=name 2>/dev/null | head -1)
  cat >&2 <<EOF

  ╔══════════════════════════════════════════════════════════════════════════╗
  ║  ⛔ THÈME INCOMPATIBLE : « $THEME » ne déclare AUCUN emplacement de menu.
  ╚══════════════════════════════════════════════════════════════════════════╝

  C'est la signature d'un THÈME DE BLOCS (Twenty Twenty-Four/Five, et la plupart
  des thèmes FSE) : ils n'utilisent pas register_nav_menus(), la navigation se
  construit à la main dans l'éditeur de site.

  Conséquences, sur CE template :
    · le menu ne peut pas être assigné → la nav n'est pas garantie à 3 entrées ;
    · le switcher FR/AR de aod-cod-form s'injecte dans wp_nav_menu() → il
      DISPARAÎT purement et simplement. Le site n'est plus bilingue.

  → Il faut un THÈME CLASSIQUE (Astra, GeneratePress, Neve, Blocksy, Kadence…).
    Cf. TEMPLATE.md §4.

EOF
  exit 1
else
  for loc in $LOCATIONS; do
    case "$loc" in
      *account*|*compte*)
        echo "    · « $loc » ignoré (emplacement de compte — pas de compte ici)"
        continue
        ;;
    esac
    wp menu location assign "$MENU" "$loc" >/dev/null 2>&1 \
      && echo "    → assigné à « $loc »"
  done
fi

# ─── 4. Purge du tunnel WooCommerce ────────────────────────────────────────────
# Corbeille, pas suppression définitive : les options woocommerce_*_page_id
# continuent de pointer dessus, ce qui permet à AOD_COD_No_Tunnel de reconnaître
# ces pages et de les purger des menus. Et un clone peut toujours les restaurer.
echo "--- Purge du tunnel (règle : ni panier ni compte) ---"
for opt in woocommerce_cart_page_id woocommerce_checkout_page_id woocommerce_myaccount_page_id; do
  id=$(wp option get "$opt" 2>/dev/null || echo 0)
  if [ "${id:-0}" -gt 0 ] && [ "$(wp post get "$id" --field=post_status 2>/dev/null)" != "trash" ]; then
    title=$(wp post get "$id" --field=post_title 2>/dev/null)
    wp post delete "$id" >/dev/null 2>&1 && echo "    ✗ « $title » (#$id) → corbeille"
  fi
done

# « Sample Page » : page de démo de WordPress, publiée par défaut.
SAMPLE=$(wp post list --post_type=page --name=sample-page --field=ID --posts_per_page=1 2>/dev/null | head -1)
if [ -n "$SAMPLE" ]; then
  wp post delete "$SAMPLE" >/dev/null 2>&1 && echo "    ✗ « Sample Page » (#$SAMPLE) → corbeille"
fi

# ─── Bilan ─────────────────────────────────────────────────────────────────────
echo
echo "=== Pages publiques ==="
wp post list --post_type=page --post_status=publish --fields=ID,post_title,post_name
echo
echo "=== Emplacements de menu ==="
wp menu location list 2>/dev/null
echo
echo "→ Vérifier ensuite en navigateur : la nav doit montrer EXACTEMENT"
echo "  Accueil · Boutique · Contact, en desktop ET en mobile."
