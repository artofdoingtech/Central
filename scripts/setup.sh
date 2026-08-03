#!/bin/bash
# ───────────────────────────────────────────────────────────────
# Installe et configure WordPress + WooCommerce pour le master DZ.
# S'exécute DANS le conteneur wpcli :
#   docker compose exec -e ... wpcli bash /scripts/setup.sh
# Idempotent : on peut le relancer sans tout casser.
# ───────────────────────────────────────────────────────────────
set -e
cd /var/www/html

echo "==> 1. Installation du coeur WordPress"
if ! wp core is-installed 2>/dev/null; then
  wp core install \
    --url="${WP_URL}" \
    --title="${WP_TITLE}" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email
else
  echo "    WordPress déjà installé, on continue."
fi

echo "==> 2. Réglages de base (Algérie)"
wp option update timezone_string "Africa/Algiers"
wp option update blogdescription "Boutique en ligne"
wp rewrite structure '/%postname%/' --hard
wp option update date_format 'd/m/Y'

echo "==> 2bis. Langues (FR par défaut, AR pour le switcher)"
# Sans ces packs, WordPress reste en en_US : nos plugins « paraissent » français
# (leurs msgid le sont) mais tout le cœur WP/WooCommerce s'affiche en anglais —
# ex. l'historique d'une commande : « Order status changed from … ».
# Non bloquant : un clone sans DNS sortant (cf. TEMPLATE.md §3) doit pouvoir
# terminer son installation.
for loc in fr_FR ar; do
  wp language core install "$loc" 2>/dev/null || echo "    (pack coeur $loc indisponible — DNS sortant ?)"
done
wp language plugin install woocommerce fr_FR ar 2>/dev/null || echo "    (traductions WooCommerce indisponibles)"
# Locale par défaut du site. Le switcher FR/AR (cookie aod_lang) la surcharge
# par visiteur : c'est bien le défaut qu'on fixe ici, pas un verrou.
wp site switch-language fr_FR 2>/dev/null || echo "    (bascule en fr_FR impossible)"

echo "==> 3. Thèmes (depuis /library/themes)"
for z in /library/themes/*.zip; do
  [ -e "$z" ] || continue
  echo "    -> $z"
  wp theme install "$z" --force || echo "    (échec $z, on continue)"
done

echo "==> 4. Plugins (depuis /library/plugins)"
for z in /library/plugins/*.zip; do
  [ -e "$z" ] || continue
  echo "    -> $z"
  wp plugin install "$z" --force || echo "    (échec $z, on continue)"
done

echo "==> 4bis. Socle obligatoire (repli depuis wordpress.org)"
# 🚨 SANS CETTE ÉTAPE, UN `git clone` NE DONNE PAS UNE APPLICATION QUI DÉMARRE.
# `library/` n'est pas versionné (poids + licences, cf. TEMPLATE.md §2) : sur un
# clone frais il est VIDE — mesuré le 2026-07-28, 0 fichier versionné dessous.
# Les deux boucles ci-dessus n'installent alors RIEN, et les deux plugins maison,
# qui déclarent `Requires Plugins: woocommerce`, se désactivent « proprement » :
# autrement dit /gestion et la boutique n'existent pas, sans qu'aucune erreur ne
# le dise. C'est l'échec silencieux type.
#
# On installe donc le SOCLE quand il manque. Ce n'est PAS un choix client (règle
# d'or n°1) : WooCommerce est une dépendance DURE du code de ce dépôt, et Astra
# est le thème neutre que le template documente comme défaut (TEMPLATE.md §4).
# ⚠️ Un thème déposé dans `library/themes/` reste PRIORITAIRE — on ne remplace
# jamais ce que l'owner a fourni, on comble seulement une absence.
if ! wp plugin is-installed woocommerce >/dev/null 2>&1; then
  echo "    WooCommerce absent -> installation depuis wordpress.org"
  wp plugin install woocommerce || echo "    (échec : réseau ? cf. TEMPLATE.md §3 — bloc dns du docker-compose)"
else
  echo "    WooCommerce déjà installé."
fi

if ls /library/themes/*.zip >/dev/null 2>&1; then
  echo "    Thème fourni dans library/ : aucun repli (le choix de l'owner prime)."
elif wp theme is-installed astra >/dev/null 2>&1; then
  echo "    Astra déjà installé."
else
  echo "    Aucun thème fourni et Astra absent -> installation depuis wordpress.org"
  wp theme install astra || echo "    (échec : réseau ?)"
fi

echo "==> 5. Activation WooCommerce + thème (Astra en REPLI seulement)"
wp plugin activate woocommerce 2>/dev/null || echo "    woocommerce: vérifier le slug"

# 🚨 CETTE LIGNE A ÉTÉ INCONDITIONNELLE JUSQU'AU 2026-07-29 (étape 44.2) : elle
# faisait `wp theme activate astra` à chaque passage. Or ce script est documenté
# IDEMPOTENT (README.md, TEMPLATE.md §8), ce qui invite explicitement à le
# relancer — et une relance sur un clone habillé renvoyait le site du client sous
# Astra, en imposant de rejouer `pages-publiques.sh` (les emplacements de menu
# changent d'un thème à l'autre : un menu non réassigné et la nav n'est plus
# garantie à 3 entrées, TEMPLATE.md §4).
#
# La règle était pourtant écrite DES DEUX CÔTÉS de cette ligne : à l'étape 4bis
# (« on ne remplace jamais ce que l'owner a fourni, on comble seulement une
# absence ») et au bloc 5bis juste en dessous (« NON destructif : si l'owner a
# déjà réglé une largeur, on n'y touche pas »). Elle n'avait simplement pas été
# portée de l'INSTALLATION à l'ACTIVATION.
#
# On teste le thème ACTIF (et non la présence d'un zip dans library/) : un thème
# déposé mais jamais activé doit encore laisser Astra prendre le relais, sinon un
# clone neuf finirait sans thème et l'étape 7 refuserait de conclure.
# `get_template()` et NON `get_stylesheet()` : un child theme d'Astra doit compter
# comme Astra, pas comme un thème tiers.
AOD_TPL="$(wp eval 'echo get_template();' 2>/dev/null || echo '')"
case "$AOD_TPL" in
  astra)
    echo "    thème actif : Astra (ou un enfant d'Astra) — rien à faire."
    ;;
  ''|twenty*)
    # Aucun thème, ou un thème par défaut de WordPress : c'est l'état d'une
    # installation NEUVE — le repli Astra est exactement ce qu'on veut ici.
    if wp theme activate astra 2>/dev/null; then
      echo "    Astra activé (aucun choix de thème sur ce clone)."
    else
      echo "    astra non activé (choix plus tard)"
    fi
    ;;
  *)
    # Le troisième cas DOIT parler : un silence laisserait croire que l'étape a
    # échoué, et quelqu'un « réparerait » en forçant l'activation — ce qui
    # réintroduirait exactement le défaut que 44.2 vient de fermer.
    echo "    thème actif : '$AOD_TPL' — CHOIX DE L'OWNER, on n'y touche pas."
    ;;
esac
# Les deux plugins maison sont bind-montés : ils sont TOUJOURS là après un clone.
# Les activer ici est ce qui fait la différence entre « les fichiers sont présents »
# et « l'application fonctionne ». APRÈS WooCommerce : tous deux la vérifient au
# chargement et se mettent en veille si elle manque.
wp plugin activate aod-cod-form aod-client-dashboard 2>/dev/null \
  || echo "    plugins aod-* : activation impossible (WooCommerce manque-t-il ?)"

echo "==> 5bis. Largeur de contenu Astra (défaut du template)"
# Sur grand écran (≥ ~2000 px), le défaut Astra de 1200 laisse d'énormes bandes
# vides sur les côtés (~680 px de chaque côté à 2560). On pose 1600 par DÉFAUT
# pour que chaque clone en hérite — le correctif CSS qui empêche le formulaire COD
# de flotter voyage déjà dans le plugin, mais cette LARGEUR est une option en base
# qui, elle, ne part pas avec `git clone`. Cf. CHECKLIST-THEME-CHILD.md C9.
# ⚠️ PAS un theme_mod : Astra lit cette largeur dans l'option `astra-settings`
# (un `set_theme_mod("site-content-width", …)` s'écrit dans le vide).
# Idempotent + NON destructif : si l'owner a déjà réglé une largeur (par clone,
# selon son thème/goût — règle d'or n°1), on n'y touche pas. Ne s'applique qu'à
# Astra (ou un enfant d'Astra) ; inerte sous un autre thème.
if [ "$(wp eval 'echo get_template();' 2>/dev/null)" = "astra" ]; then
  wp eval '
    $s = get_option("astra-settings");
    if ( ! is_array($s) ) { $s = array(); }
    if ( empty($s["site-content-width"]) ) {
      $s["site-content-width"] = 1600;
      update_option("astra-settings", $s);
      echo "    largeur posée à 1600.\n";
    } else {
      echo "    largeur déjà réglée (".$s["site-content-width"]."), on n\x27y touche pas.\n";
    }
  ' 2>/dev/null || echo "    (réglage largeur Astra ignoré)"
else
  echo "    thème actif non-Astra : réglage largeur ignoré (propre à Astra)."
fi

echo "==> 6. Config WooCommerce (DZD / Algérie)"
wp option update woocommerce_currency "DZD" || true
wp option update woocommerce_default_country "DZ" || true
wp option update woocommerce_currency_pos "right_space" || true
wp option update woocommerce_price_num_decimals "2" || true
wp option update woocommerce_weight_unit "kg" || true
wp option update woocommerce_dimension_unit "cm" || true
# Activer le paiement à la livraison (COD)
wp option update woocommerce_cod_settings '{"enabled":"yes","title":"Paiement à la livraison","description":"Payez en espèces à la réception."}' --format=json || true

# Mode « Bientôt disponible » : depuis WooCommerce 9.1, une installation neuve démarre
# avec ce mode ACTIVÉ. La page Boutique n'affiche alors aucun produit mais un écran
# « Notre boutique est en chantier » — et rien ne le signale. Un clone livré sans
# désactiver ça vend une boutique vide.
wp option update woocommerce_coming_soon "no" || true

# HPOS (tables de commandes dédiées). Les plugins aod-* le supposent actif :
# en stockage « legacy », wc_get_orders() ignore silencieusement `meta_query`,
# ce qui casse le filtrage des colis (page Suivi) et les compteurs de l'Accueil.
# Traductions des thèmes et plugins tiers. À FAIRE ICI, pas à l'étape 2bis : là-bas
# le thème n'est pas encore installé, donc `wp language theme install` n'aurait rien à
# traduire. Sans ce passage, le thème garde ses propres chaînes en anglais — Astra
# affiche par exemple « OUT OF STOCK » sur une carte produit, en plein site français.
echo "==> 6ter. Traductions des thèmes et plugins"
# `--all` sort en erreur dès qu'UN pack manque, alors même que les autres se sont
# installés. On ignore donc le code de sortie : ce qui compte, c'est ce qui a pu être
# installé. On RELANCE une fois — un simple aléa réseau suffit à faire échouer un
# téléchargement, et `--all` passe à la suite sans le dire.
for _ in 1 2; do
  wp language theme install  --all fr_FR ar 2>/dev/null || true
  wp language plugin install --all fr_FR ar 2>/dev/null || true
done

# Et on VÉRIFIE : un pack « uninstalled » est disponible mais absent. Sans ce contrôle,
# le thème garde ses chaînes en anglais (« OUT OF STOCK » en plein site français) et
# rien ne le signale — c'est exactement le genre d'échec silencieux qui se découvre
# chez le client.
theme_slug="$(wp theme list --status=active --field=name 2>/dev/null | head -1)"
if [ -n "$theme_slug" ]; then
  for loc in fr_FR ar; do
    st="$(wp language theme list "$theme_slug" --format=csv 2>/dev/null | grep "^$loc," | cut -d, -f2)"
    case "$st" in
      installed|active) ;;
      uninstalled) echo "    ⚠ pack $loc du thème « $theme_slug » DISPONIBLE mais NON installé — relancer : wp language theme install $theme_slug $loc" ;;
      "")          echo "    ⚠ le thème « $theme_slug » n'a AUCUNE traduction $loc — ses chaînes resteront en anglais (cf. CHECKLIST-THEME-CHILD.md C6)" ;;
    esac
  done
fi

echo "==> 6bis. HPOS (stockage des commandes)"
wp wc hpos sync 2>/dev/null || true          # migre les commandes déjà présentes
wp wc hpos enable 2>/dev/null || echo "    HPOS: activation impossible (vérifier la version de WooCommerce)"

echo "==> 6quater. Sonde d'écriture des téléversements"
# Ce script tourne sous l'uid 33 — EXACTEMENT celui d'Apache : si cette sonde ne
# peut pas écrire, aucun envoi d'image depuis /gestion ne le pourra. Le piège :
# wordpress/ est bind-monté depuis l'hôte, et un dossier créé côté hôte naît en
# 755, propriété de l'utilisateur hôte — illisible en écriture pour www-data.
# On échoue BRUYAMMENT : un setup « réussi » avec des téléversements morts est
# l'échec silencieux type, celui qui se découvre chez le client.
probe="wp-content/uploads/.aod-probe-$$"
if mkdir -p "$probe" 2>/dev/null && touch "$probe/w" 2>/dev/null; then
  rm -rf "$probe"
  echo "    ✓ le serveur web peut écrire dans wp-content/uploads."
else
  rm -rf "$probe" 2>/dev/null || true
  echo ""
  echo "    ✗ le serveur web NE PEUT PAS écrire dans wp-content/uploads :"
  echo "      chaque envoi d'image depuis /gestion échouera."
  echo "      Depuis l'HÔTE, dans le dossier du clone :"
  echo "          chmod -R a+rwX wordpress/wp-content/uploads"
  echo "      (ou relance : bash scripts/preflight.sh — il le fait aussi)"
  exit 1
fi

echo "==> 6quinquies. Limites de téléversement (Phase 47.6bis)"
# 🚨 POURQUOI CETTE SONDE. L'application promet 5 Mo par image
# (AOD_CD_Upload::MAX_BYTES). Si PHP plafonne plus bas, elle tient sa promesse à
# l'écran — elle ANNONCE la vraie limite — mais le gérant ne peut pas envoyer sa
# photo. C'est exactement ce qui a été constaté le 2026-08-01 : une photo de
# 2,8 Mo refusée, parce qu'AUCUN php.ini n'était chargé et que PHP retombait sur
# ses défauts compilés (2M/8M).
#
# On ÉCHOUE, on n'avertit pas : ce script ne tourne QUE dans le conteneur wpcli
# (`docker compose exec wpcli bash /scripts/setup.sh`, TEMPLATE.md §8), donc la
# configuration est ENTIÈREMENT la nôtre. Un écart n'est pas une contrainte
# d'hébergeur, c'est un montage manquant — et le remède tient en une commande.
max_app="$( wp eval 'echo (int) AOD_CD_Upload::MAX_BYTES;' 2>/dev/null || echo 0 )"
max_php="$( wp eval 'echo (int) wp_max_upload_size();' 2>/dev/null || echo 0 )"
if [ "$max_app" -gt 0 ] && [ "$max_php" -lt "$max_app" ]; then
  echo ""
  echo "    ✗ PHP plafonne les envois SOUS la limite annoncée par la boutique :"
  echo "      PHP = $(( max_php / 1048576 )) Mo · boutique = $(( max_app / 1048576 )) Mo"
  echo "      Le gérant ne pourra pas envoyer une photo de produit ordinaire."
  echo "      Depuis l'HÔTE, dans le dossier du clone :"
  echo "          docker compose up -d      # recrée les conteneurs avec docker/php-aod.ini"
  echo "      (le fichier est versionné : un clone neuf l'a déjà, il faut juste"
  echo "       que les conteneurs soient recréés pour prendre le montage)"
  exit 1
fi
echo "    ✓ PHP accepte $(( max_php / 1048576 )) Mo — au-dessus de la limite de la boutique ($(( max_app / 1048576 )) Mo)."

# HÉBERGEMENT RÉEL — `.user.ini` est le mécanisme de PHP-FPM / CGI, donc de la
# quasi-totalité des mutualisés (Hostinger, LiteSpeed…). Il est SANS EFFET sur
# cette pile de dev (Apache y tourne en mod_php, qui l'ignore) : on l'écrit
# quand même, parce que c'est le fichier qui PARTIRA chez le client — `wordpress/`
# n'étant pas versionné, il ne peut pas venir du dépôt.
#
# ⚠️ On n'écrit RIEN dans .htaccess. Un `php_value` sur un serveur sans mod_php
# provoque une ERREUR 500 — le site entier tombe — et le bloc `# BEGIN WordPress`
# est régénéré par WordPress, donc tout ajout y serait perdu en silence. Le repli
# mod_php est documenté dans CHECKLIST-SITE-MASTER-ALGERIE.md, à demander à
# l'hébergeur plutôt qu'à poser à l'aveugle.
if [ ! -f .user.ini ] || ! grep -q 'AOD 47.6bis' .user.ini 2>/dev/null; then
  {
    echo "; AOD 47.6bis — limites de téléversement pour PHP-FPM / CGI."
    echo "; Sans effet sous mod_php : voir CHECKLIST-SITE-MASTER-ALGERIE.md."
    echo "; La boutique refuse à 5 Mo avec son propre message ; ces valeurs"
    echo "; laissent PHP au-dessus, pour que ce soit ELLE qui explique le refus."
    echo "upload_max_filesize = 8M"
    echo "post_max_size = 64M"
  } > .user.ini
  echo "    ✓ .user.ini écrit (limites d'envoi pour un hébergement PHP-FPM/CGI)."
else
  echo "    ✓ .user.ini déjà en place."
fi

echo "==> 7. Contrôle de cohérence — ce clone est-il UTILISABLE ?"
# Même philosophie que la sonde d'écriture ci-dessus : on échoue BRUYAMMENT.
# Un setup qui se termine par « ✅ TERMINÉ » sur une installation sans WooCommerce
# ni thème est le pire des résultats — tout paraît normal, et le premier symptôme
# arrive chez le client.
manque=""
wp plugin is-active woocommerce          >/dev/null 2>&1 || manque="$manque woocommerce"
wp plugin is-active aod-cod-form         >/dev/null 2>&1 || manque="$manque aod-cod-form"
wp plugin is-active aod-client-dashboard >/dev/null 2>&1 || manque="$manque aod-client-dashboard"
[ -n "$( wp theme list --status=active --field=name 2>/dev/null )" ] || manque="$manque theme-actif"
if [ -n "$manque" ]; then
  echo ""
  echo "    ✗ CE CLONE N'EST PAS UTILISABLE — manque :$manque"
  echo "      · réseau absent ? le bloc dns du docker-compose.yml (TEMPLATE.md §3)"
  echo "      · ou dépose les .zip dans library/plugins/ et library/themes/, puis relance"
  echo "      Sans WooCommerce, les deux plugins aod-* se mettent en veille :"
  echo "      /gestion et la boutique n'existent tout simplement pas."
  exit 1
fi
echo "    ✓ WooCommerce + les 2 plugins aod-* actifs, un thème est activé."

echo "==> 7bis. État final"
echo "--- Thèmes ---";  wp theme list  --fields=name,status,version 2>/dev/null || true
echo "--- Plugins ---"; wp plugin list --fields=name,status,version 2>/dev/null || true

echo ""
echo "✅ TERMINÉ. Site : ${WP_URL}  | Admin : ${WP_URL}/wp-admin"
echo ""
echo "   Étape suivante OBLIGATOIRE (les 3 pages publiques + le menu) :"
echo "       docker compose exec wpcli bash /scripts/pages-publiques.sh"
echo "   Puis vérifier :  bash tools/recette/run.sh public"
