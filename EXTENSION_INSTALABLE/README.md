# EXTENSION_INSTALABLE — AOD Gestion (`/gestion` en extension licenciée)

Ce dossier produit une extension WordPress **installable sur un site
existant** — pas un clone du template — pour lui ajouter l'espace de gestion
`/gestion` (commandes COD, produits, stock, livraison, transporteurs, suivi
colis, pixels, WhatsApp, équipe…), avec une **licence activable, valable un
an**.

C'est un produit différent du reste de ce dépôt : le template principal
(`plugins-dev/`, `TEMPLATE.md`) sert à **cloner un nouveau site** de A à Z et
reste gratuit/générique par construction (règle 1 de `CLAUDE.md`). Cette
extension sert à **greffer `/gestion` sur un site qui existe déjà**, avec son
propre thème et son propre contenu — la contrainte « exactement 3 pages
publiques » du template ne s'applique pas ici : on ne touche à aucune page,
aucun thème, aucun contenu public du site cible.

## Contenu du paquet

```
EXTENSION_INSTALABLE/
├── plugins/
│   ├── aod-cod-form/            copie du plugin du template (INCHANGÉE)
│   ├── aod-client-dashboard/    copie du plugin du template (2 lignes patchées, cf. plus bas)
│   └── aod-gestion-license/     NOUVEAU — active/vérifie la licence
├── outils-vendeur/              réservé au fournisseur, JAMAIS livré au client
│   ├── generer-licence.php      émet une clé de licence (1 an par défaut)
│   ├── verifier-licence.php     décode/contrôle une clé (support)
│   └── README.md
├── build.sh                     génère les 3 .zip installables dans dist/
└── dist/                        (généré, gitignoré) — les 3 .zip prêts à livrer
```

## Pourquoi 3 plugins et pas 1

`/gestion` (le plugin `aod-client-dashboard`) est **écrit contre** les
classes d'`aod-cod-form` (commandes, wilayas/communes, transporteurs) — 13 de
ses fichiers appellent `AOD_COD_*` directement. Les deux ne peuvent pas être
séparés sans réécrire des milliers de lignes déjà testées et traduites. La
solution la plus sûre — et la plus honnête vis-à-vis du code déjà éprouvé
dans ce dépôt — est de livrer les **plugins réels, inchangés**, installés et
activés normalement par WordPress (activation hooks, rôles, cron, HPOS : rien
de tout ça ne casse), plutôt que de les fusionner en un seul fichier ou de
réécrire `/gestion` en partant de zéro.

Le 3ᵉ plugin, `aod-gestion-license`, est neuf : c'est LUI qui porte toute la
logique de licence (activation, vérification, blocage). Il ne modifie rien
dans `aod-cod-form`, et seulement 2 endroits dans `aod-client-dashboard.php`
(marqués `PATCH AODG` dans le fichier) :

1. L'en-tête `Requires Plugins` (ajoute `aod-cod-form, aod-gestion-license` —
   WordPress refuse alors d'activer le dashboard si l'un des deux manque,
   avec un message clair dans l'UI d'activation).
2. Un garde-fou juste après la vérification WooCommerce : si la constante
   `AODG_LICENSE_ACTIVE` n'est pas définie (= le plugin de licence n'est pas
   actif), le dashboard refuse de démarrer. Ça empêche de contourner la
   licence en désactivant simplement `aod-gestion-license` : sans lui,
   `/gestion` ne fonctionne tout simplement pas, licence valide ou pas.

La validité de la clé elle-même (expirée / invalide / absente) est vérifiée
**séparément**, entièrement depuis `aod-gestion-license` (aucune autre
modification des deux autres plugins) :

- `template_redirect` (priorité 1, avant le routeur du dashboard) bloque le
  rendu de `/gestion` avec un écran « licence requise/expirée ».
- Les 44 actions AJAX du dashboard (préfixe commun `aod_cd_`, liste dans
  `AODG_Gate::AJAX_ACTIONS`) sont interceptées et refusées (HTTP 402) avant
  d'atteindre leur vrai handler.

Résultat : si tu resynchronises un jour `aod-client-dashboard/` depuis
`plugins-dev/` (nouvelle version du template), il suffit de reporter ces 2
petites modifications marquées `PATCH AODG` — tout le reste peut être
écrasé tel quel.

## Comment la licence fonctionne

Une clé (`AODG1.<payload>.<signature>`) est un jeton **signé HMAC-SHA256**,
généré hors ligne par `outils-vendeur/generer-licence.php`, contenant le nom
du client, un domaine (optionnel), la date d'émission et la date
d'expiration (+1 an par défaut). Le plugin `aod-gestion-license` la vérifie
**entièrement en local** (signature + expiration + domaine), sans aucun
appel réseau — cohérent avec le reste de ce projet, qui documente déjà
l'absence d'internet sortant fiable sur beaucoup d'hébergements DZ visés
(`TEMPLATE.md` §3).

Voir `outils-vendeur/README.md` pour émettre une clé et pour la limite
assumée de cette approche (licence « molle », pas un DRM).

## Installer chez un client

Prérequis sur le site cible : **WordPress + WooCommerce déjà actifs**.

```bash
bash build.sh        # génère dist/aod-cod-form.zip, aod-client-dashboard.zip, aod-gestion-license.zip
```

Puis, dans wp-admin du site client → **Extensions → Ajouter → Téléverser une
extension**, dans cet ordre :

1. `aod-cod-form.zip` → Activer.
   ⚠️ Active le formulaire de commande COD sur les fiches produit et
   débranche le tunnel panier/checkout/compte standard (comportement déjà
   documenté dans `TEMPLATE.md` §5 — « Ni panier ni compte : c'est verrouillé
   côté serveur »). Si le site doit **garder** son panier/checkout Woo
   normal et n'utiliser `/gestion` que pour éditer des commandes classiques,
   **n'active pas** `aod-cod-form` : le dashboard démarre quand même, en
   mode dégradé (pas de wilayas/communes, pas de transporteurs, pas de
   statut « Confirmée » — avertissement automatique dans wp-admin).
2. `aod-client-dashboard.zip` → Activer. Crée le rôle gérant, la règle de
   réécriture `/gestion`, et flush les permaliens automatiquement.
3. `aod-gestion-license.zip` → Activer.
4. **Réglages → Licence AOD Gestion** → coller la clé (émise avec
   `outils-vendeur/generer-licence.php`) → **Activer**.
5. Utilisateurs → donner l'accès gérant (rôle *Gestion Boutique*, créé par
   `aod-client-dashboard`) au(x) compte(s) du client.

Sans étape 4 (ou une fois la licence expirée), `/gestion` reste installé
mais bloqué : un écran dédié invite à activer/renouveler la licence, et les
actions du dashboard renvoient une erreur explicite plutôt qu'un
comportement silencieux ou cassé.

## Vérifier avant de livrer

```bash
bash build.sh
```

Puis sur un site de test (jamais le site du client final avant validation) :
installer les 3 zips dans l'ordre ci-dessus, vérifier que :

- sans licence : `/gestion` affiche l'écran de blocage, pas une erreur PHP ;
- avec une clé valide (générée avec un domaine `*` ou celui du site de
  test) : `/gestion` s'ouvre normalement, toutes les sections fonctionnent ;
- avec une clé expirée (génère-en une avec `0` jour dans le passé — ou
  attends, ou modifie temporairement l'horloge du serveur de test) :
  `/gestion` se rebloque tout seul, sans qu'il faille désactiver/réactiver
  quoi que ce soit.
