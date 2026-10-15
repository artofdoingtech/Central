=== AOD Gestion — Licence ===
Contributors: artofdoing
Tags: licence, woocommerce, cod
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

Active et vérifie la licence annuelle de l'espace de gestion /gestion (AOD COD Form + AOD Client Dashboard) sur un site WordPress existant.

== Description ==

Ce plugin ne fait rien seul : il fournit et fait respecter la clé de licence
qui autorise l'utilisation de l'espace de gestion `/gestion`, fourni par les
plugins « AOD COD Form » et « AOD Client Dashboard » (installés à côté de
celui-ci, voir le dossier `plugins/` du paquet).

Sans une clé de licence valide, activée depuis
Réglages → Licence AOD Gestion, l'espace `/gestion` reste bloqué (message
d'écran + actions AJAX refusées). Sans ce plugin actif du tout,
AOD Client Dashboard refuse même de démarrer.

La vérification est **entièrement locale** (aucun appel réseau) : la clé est
un jeton signé, à durée d'un an, émis hors ligne par le fournisseur de
l'extension.

== Installation ==

1. Installez et activez WooCommerce.
2. Installez et activez « AOD COD Form ».
3. Installez et activez « AOD Client Dashboard ».
4. Installez et activez ce plugin.
5. Réglages → Licence AOD Gestion → collez votre clé → Activer.

== Changelog ==

= 1.0.0 =
* Première version : activation de clé, blocage de /gestion et de ses actions
  AJAX hors licence valide, rappels d'expiration dans wp-admin.
