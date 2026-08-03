# AOD COD Form

Plugin WordPress/WooCommerce **maison** (Art Of Doing) — formulaire de commande
**COD (paiement à la livraison)** pour l'Algérie. Sans licence, dupliquable à l'infini.
Compatible **HPOS**. WooCommerce requis.

## Fonctionnalités

- 🛒 **Commande directe sur la page produit** (sans panier ni compte)
- 💵 **COD** — paiement à la livraison, statut custom « Confirmée » (`wc-aod-confirmed`)
- 🗺️ **58 wilayas + 1541 communes** (noms FR + AR, cascade wilaya → commune)
- 🚚 **Frais de livraison par wilaya** : Domicile / Stop-desk + **livraison gratuite**
  à partir d'un montant (page admin `WooCommerce → Livraison COD`)
- 📥 **Import des tarifs depuis l'API transporteur** : couche `get_fees()` normalisée
  (Yalidine/Yalitec, E-com Delivery, EcoTrack + white-labels) → prévisualisation
  actuel vs API dans `/gestion` avec **marge** (fixe/%/arrondi) et
  **backup/restauration** avant écriture (Noest/Maystro : pas d'API tarifs)
- 📦 **Transporteurs** : Yalidine, Yalitec, Noest, E-com Delivery (ex-ZR Express),
  Maystro, EcoTrack + **23 marques white-label** — envoi auto/manuel à la
  confirmation, étiquettes PDF (selon transporteur), **suivi colis** par cron
  (toutes les 2 h, statuts normalisés dans les métas `_aod_ship_status*`)
- 🛡️ **Anti-bot 5 couches — dont 4 actives sur un clone neuf** : honeypot,
  time-trap *(depuis la 32.3, **omettre** le champ est aussi disqualifiant que
  mal le remplir)*, rate-limit IP (10/h), limite 1 commande/24 h par téléphone —
  puis **Cloudflare Turnstile**, la 5ᵉ, qui **n'existe pas tant qu'on n'a pas
  saisi les clés**. Précédées du **nonce CSRF**, qui n'est pas une couche
  anti-bot : la requête meurt dessus avant d'atteindre les cinq. Cf.
  [`docs/RUNBOOK-ANTI-BOT-COD.md`](../../docs/RUNBOOK-ANTI-BOT-COD.md)
- 🧮 **Total en direct** + prix **recalculé côté serveur** (anti-triche),
  offres/lots (`_aod_offers`), variantes visuelles, packs
- 🎯 **Pixels** Meta / TikTok / Snapchat / Google Ads (PageView + conversion,
  anti double comptage)
- 💬 **Notification WhatsApp** au marchand à chaque commande (CallMeBot)
- 📇 **Prospects / paniers abandonnés** : table custom `{prefix}aod_cod_leads` +
  page admin (appel / WhatsApp)
- 🌐 **Switcher de langue FR ⇄ AR public** (cookie `aod_lang`, RTL auto) —
  shortcode `[aod_lang_switcher]`
- 🧱 **Bloc Gutenberg** `aod/cod-form` + **widget Elementor**

## Utilisation

- **Automatique** : s'affiche sur chaque page produit (hook `woocommerce_single_product_summary`, priorité 35).
- **Shortcode** : `[aod_cod_form product_id="123"]` (ou `[aod_cod_form]` sur une page produit).
- **Tarifs livraison** : `WooCommerce → Livraison COD` (ou l'onglet Livraison de `/gestion`).
- **Transporteurs** : réglages par transporteur (options `aod_carrier_{id}`), test de
  connexion intégré ; envoi auto activable (option `aod_shipping_auto`).
- **Turnstile** : clés via réglages ou constantes `AOD_COD_TURNSTILE_SITE_KEY` /
  `AOD_COD_TURNSTILE_SECRET_KEY` — voir `docs/Turnstile.md` à la racine du dépôt.

## Structure

```
aod-cod-form/
├── aod-cod-form.php                       # bootstrap (hooks, dépendance Woo, HPOS)
├── uninstall.php                          # efface les réglages (garde la table prospects par défaut)
├── includes/
│   ├── class-aod-cod-form.php             # rendu formulaire + AJAX commande + offres/variantes
│   ├── class-aod-cod-data.php             # wilayas/communes + tarifs + livraison gratuite
│   ├── class-aod-cod-admin.php            # page admin tarifs de livraison
│   ├── class-aod-shipping.php             # orchestrateur transporteurs + statut « Confirmée »
│   ├── class-aod-carrier.php              # classe abstraite transporteur (métas, statuts, HTTP, get_fees)
│   ├── class-aod-carrier-yalidine.php     # Yalidine (+ Yalitec par héritage)
│   ├── class-aod-carrier-yalitec.php
│   ├── class-aod-carrier-noest.php        # Noest Express
│   ├── class-aod-carrier-procolis.php     # E-com Delivery (ex-ZR Express)
│   ├── class-aod-carrier-maystro.php      # Maystro Delivery
│   ├── class-aod-carrier-ecotrack.php     # EcoTrack + 23 white-labels
│   ├── class-aod-ship-tracking.php        # cron de suivi colis (2 h, lots de 30)
│   ├── class-aod-cod-leads.php            # prospects / paniers abandonnés
│   ├── class-aod-cod-pixels.php           # pixels publicitaires
│   ├── class-aod-cod-whatsapp.php         # notification WhatsApp (CallMeBot)
│   ├── class-aod-cod-turnstile.php        # anti-robot Cloudflare Turnstile
│   ├── class-aod-cod-lang.php             # switcher FR/AR public + RTL
│   ├── class-aod-cod-block.php            # bloc Gutenberg (rendu serveur)
│   ├── class-aod-cod-elementor-widget.php # widget Elementor
│   ├── class-aod-cod-landing.php          # page de vente par produit (schéma + sanitize + rendu, 11 blocs)
│   └── data/dz-places.json                # 58 wilayas / 1541 communes (FR + AR)
├── templates/
│   └── single-product-landing.php         # gabarit de la page de vente (header/blocs/footer)
├── assets/
│   ├── css/aod-cod-form.css               # styles (RTL-aware)
│   ├── css/aod-landing.css                # styles de la page de vente (chargés si active)
│   ├── js/aod-cod-form.js                 # cascade + total live + AJAX + anti-bot
│   └── js/aod-landing.js                  # façade vidéo · rebours · barre collante (chargé SEULEMENT si l'un des trois sert)
└── languages/                             # .pot / -ar.po / -ar.mo
```

## Développement local

Le dossier est monté en direct dans le conteneur WordPress
(`./plugins-dev/aod-cod-form` → `/var/www/html/wp-content/plugins/aod-cod-form`),
donc **toute édition depuis le PC est live** (rafraîchir la page).

## i18n

Text domain `aod-cod-form`. **Traduction arabe complète** (les chaînes de
**marque** restent volontairement en FR), complétée par les `name_ar`
du JSON géo et le dictionnaire FR→AR des variantes. Après toute nouvelle
chaîne : régénérer le `.pot`, fusionner le `.po` arabe et recompiler le `.mo`
(workflow : `bin/make-translations.sh` — celui de CE plugin, `--strict` disponible).

## Évolutions envisagées

Voir **`docs/ROADMAP-AMELIORATIONS.md`** à la racine du dépôt (plan par phases
et étapes). En résumé pour ce plugin :

- [x] Compléter la traduction arabe du front ✓ (2026-07-07)
- [x] **Phase 4** — Import des tarifs de livraison depuis l'API transporteur ✓ (2026-07-11) :
      `get_fees()` normalisé + Yalidine (4.1), E-com Delivery + EcoTrack (4.2),
      UI de prévisualisation avec marge + backup/restauration (4.3),
      i18n + recette FR/AR/RTL/mobile (4.4)
- [ ] Stock par variante (le modèle `_aod_options` n'a pas de stock propre —
      hors roadmap actuelle, chantier séparé)
