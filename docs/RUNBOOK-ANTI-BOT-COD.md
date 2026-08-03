# Runbook — Protéger un site COD existant contre les bots / floods de commandes

> **À qui ça sert ?** À tout site déjà en ligne (clone réalisé **avant** la
> v1.19.0 du plugin `aod-cod-form`, celle qui a introduit le Turnstile) qui
> reçoit des **commandes en masse**
> automatisées, ou qu'on veut simplement mettre à niveau par prévention.
>
> **Le problème.** Le formulaire COD crée la commande via un point AJAX public
> (`admin-ajax.php`, action `aod_cod_submit`). Un bot peut le frapper en direct,
> en sautant le JavaScript, avec un numéro de téléphone neuf à chaque envoi →
> des milliers de fausses commandes en `processing`. Cas réel observé : **~22 000
> fausses commandes en 2 h** depuis **une seule IP de datacenter**, ce qui peut en
> plus faire **planter `/gestion`** (mémoire PHP saturée par le volume de commandes).

Ce runbook couvre **5 volets** :

1. **Mettre à jour le code** du plugin (protection durable).
2. **Diagnostiquer** un site déjà attaqué.
3. **Nettoyer** les commandes spam.
4. **Bloquer l'attaque en cours** au niveau serveur (`.htaccess`).
5. **Activer le Turnstile — indispensable dès qu'il y a rotation d'IP**, le blocage
   serveur du volet 4 étant impuissant dans ce cas.
3. **Bloquer l'attaque en cours** au niveau serveur.

> ⚠️ **Toujours faire une sauvegarde (fichiers + base) avant de commencer.**
> Les commandes `wp` ci-dessous supposent l'accès SSH/WP-CLI ; sinon, utilise
> **phpMyAdmin** (SQL) et le **Gestionnaire de fichiers** de l'hébergeur (Hostinger,
> hPanel) — les deux fonctionnent même si le site affiche « erreur critique ».

---

## Ce que contient le correctif (plugin `aod-cod-form` ≥ 1.19.0)

| Garde-fou | Rôle | Pourquoi il tient |
|---|---|---|
| **Nonce** *(couche 0 — pas un anti-bot)* | `check_ajax_referer( 'aod_cod_nonce' )`, avant tout le reste | Un POST **nu** sur `admin-ajax.php` meurt ici. ⚠️ **Ne protège pas** contre un bot qui lit d'abord le nonce dans le HTML de la page produit — c'est le cas standard. C'est pourquoi il n'est pas compté parmi les 5 couches |
| **Honeypot** *(durci en 32.3)* | Champ piège caché `aod_url` | Un bot qui remplit tout le remplit aussi → rejeté. **Et depuis la 32.3, l'ABSENCE du champ est refusée elle aussi** : ne pas le remplir ne suffit plus, il faut l'envoyer |
| **Time-trap** *(durci en 32.3)* | Le JS envoie `elapsed` ; rejet si **absent**, non numérique, ou < 3 s | Arrête le bot qui rejoue le formulaire trop vite **et celui qui poste un payload fabriqué à la main**. Chaque refus est journalisé (canal `antibot`) |
| **Coupe-circuit IP** *(corrigé en 26.1)* | Max `AOD_COD_MAX_PER_IP_HOUR` (10) commandes/heure/IP | Écrase un flood mono-IP sans gêner le CGNAT mobile DZ. ⚠️ **Ne tenait pas avant la 26.1** — voir l'encadré ci-dessous |
| **Limite téléphone** | 1 commande / 24 h par numéro | Bloque les doublons honnêtes |
| **Turnstile** *(≥ 1.19.0)* | CAPTCHA invisible Cloudflare, jeton **vérifié serveur** | Un bot en direct n'a **jamais** de jeton valide → rejeté **même derrière des centaines de proxies**. ⚠️ **À CONDITION** d'être configuré (il ne l'est pas sur un clone neuf) **et** que Cloudflare soit joignable — voir l'encadré ci-dessous |

> ℹ️ **Ces couches protègent DEUX points AJAX publics, pas un.** La commande COD
> **et** le formulaire de contact (`class-aod-cod-contact.php:195-233`) portent les
> mêmes couches. Activer le Turnstile couvre les deux d'un coup — mais attention au
> diagnostic : un flood visant le **contact** ne crée **aucune commande**, donc il
> n'apparaîtra dans **aucune** des requêtes du Volet 2, qui ne lisent que
> `wp_wc_orders`.

> 🚨 **Le coupe-circuit IP comptait par EN-TÊTE, pas par visiteur (avant la 26.1).**
>
> Il indexait son compteur sur `WC_Geolocation::get_ip_address()`, qui renvoie
> l'en-tête `X-Real-IP` **brut** (`class-wc-geolocation.php:83-84`) — sans même le
> `rest_is_ip_address()` que ses deux branches suivantes appliquent. Un en-tête est
> écrit par le client : `X-Real-IP: <aléatoire>` à chaque requête donnait une clé de
> compteur neuve à chaque fois, et le plafond de 10/heure n'était **jamais** atteint.
>
> Ce n'est pas un bug de WooCommerce : `get_ip_address()` sert à deviner un pays pour
> pré-remplir un formulaire, où un visiteur qui se ment ne nuit qu'à lui. **Le défaut
> était de réutiliser une source de CONFORT comme source de SÉCURITÉ.**
>
> Depuis la 26.1, la source unique est `AOD_COD_IP::client()` : `REMOTE_ADDR`, la
> seule valeur que le client ne peut pas forger (elle vient de la couche TCP).
>
> 🔧 **Si le site est derrière un proxy** (Cloudflare, load-balancer, reverse-proxy),
> `REMOTE_ADDR` est l'IP **du proxy** : tous les visiteurs partagent alors un seul
> compteur et se font bloquer à la 10ᵉ commande du site. Déclare-le dans
> `wp-config.php` :
>
> ```php
> define( 'AOD_COD_TRUSTED_PROXY', true );
> ```
>
> Les en-têtes `X-Forwarded-For` puis `X-Real-IP` sont alors lus — **et validés**.
> Une valeur non-IP retombe sur `REMOTE_ADDR`, jamais sur une clé de compteur neuve.

> 🚨 **Turnstile laissait passer en silence quand Cloudflare était injoignable
> (avant la 26.1).**
>
> `verify()` retournait « valide » sur erreur réseau — politique assumée pour ne pas
> bloquer un vrai client pendant un incident, mais **sans borne, sans trace, sans
> fin**. Trois conséquences qu'aucun écran ne révélait :
>
> 1. Le contournement coûtait **un caractère** : `cf_turnstile=x` franchit le test
>    « jeton vide », déclenche l'appel HTTP, le voit échouer, et passe.
> 2. Sur un clone **sans réseau sortant** — le cas que TEMPLATE.md §3 pose comme
>    nominal — l'échec est permanent : la couche était ouverte **en permanence**,
>    pas seulement pendant une panne.
> 3. La page de réglages affichait « ✅ Actif » pendant ce temps.
>
> **Le défaut n'était pas la politique, c'était son silence.** Depuis la 26.1 le
> fail-open est **borné** (`AOD_COD_TURNSTILE_MAX_FAILOPEN`, 20 échecs consécutifs
> par défaut ; au-delà, les commandes non vérifiées sont refusées) et **visible** :
> `/gestion → Accueil` affiche une alerte dès le **premier** échec.
>
> ⚠️ Rappel : sur un clone neuf **aucune clé n'est configurée**, donc la couche
> Turnstile n'existe pas. Les « 5 couches » n'en font que 4 tant qu'on ne l'a pas
> renseignée dans *Réglages → COD Anti-spam*.

> ✅ **CE TROU EST FERMÉ DEPUIS LA 32.3 — et l'encadré reste, parce que la façon
> dont il s'était ouvert vaut mieux que son correctif.**
>
> Les deux premières couches se contournaient **en ne faisant rien** : le honeypot
> en ne remplissant pas le champ piège, le time-trap en **omettant** `elapsed`. La
> garde s'écrivait :
>
> ```php
> // TOLÉRANT si le champ est absent : une page servie depuis un cache avec
> // l'ancien JS (sans « elapsed ») ne doit JAMAIS bloquer un vrai client.
> if ( isset( $_POST['elapsed'] ) && is_numeric( $_POST['elapsed'] ) && (int) $_POST['elapsed'] < 3000 ) {
> ```
>
> 🚨 **`is_numeric()` y était une condition d'APPLICATION, pas une exigence.** Un
> payload sans le champ traversait les trois conditions **sans en heurter une
> seule** — la garde ne le refusait pas, elle ne le regardait pas.
>
> L'inquiétude qui avait produit cette tolérance était légitime (ne pas bloquer un
> acheteur servi depuis un cache) ; elle était juste **mal réglée**. Ce qui la
> borne désormais : les deux champs partent du même bloc JS dans la même requête
> (pas d'entre-deux réaliste) ; jQuery sérialise `undefined` en chaîne **vide**,
> donc un HTML périmé envoie `aod_url=` et **passe** ; le refus est **récupérable**
> (recharger suffit) ; et surtout il est **journalisé** — un acheteur bloqué à tort
> cesse d'être une vente perdue en silence.
>
> **La leçon générale : un durcissement muet est indéfendable, un durcissement
> tracé se règle.** C'est la même que celle de la Phase 26 sur le fail-open de
> Turnstile.
>
> ⚠️ **Le Turnstile reste néanmoins la seule couche qui casse la rotation d'IP.**
> Un bot déterminé peut reproduire le formulaire ; aucune des quatre autres
> couches ne l'arrête alors. Dès qu'on subit un vrai bot, Turnstile n'est pas
> optionnel.

> Les 5 couches sont complémentaires, mais **pas équivalentes** : le time-trap
> gêne le bot « bête » qui rejoue le formulaire trop vite ; le coupe-circuit IP
> arrête un flood mono-IP ; le téléphone gère les doublons humains ; le
> **Turnstile** est le seul à casser la parade la plus dure — la **rotation d'IP**
> (proxies datacenter) que les limites par IP ne peuvent pas suivre. Le Turnstile
> est **livré dans le code mais inactif tant que les 2 clés ne sont pas saisies**
> (voir Volet 5) : le formulaire fonctionne exactement comme avant tant qu'on ne
> l'active pas.

---

## Volet 1 — Mettre à jour le code du plugin

L'objectif : amener le dossier `wp-content/plugins/aod-cod-form/` à **la version
du template**, quelle qu'elle soit au moment où tu lis ceci.

> ⚠️ **Aucun numéro de version en dur dans ce volet, à dessein.** Il a longtemps
> dit « amener à la v1.19.0 » ; le template en est aujourd'hui **bien** au-delà, et
> suivre la consigne à la lettre aurait **rétrogradé** le plugin — en emportant au
> passage le correctif du time-trap décrit plus haut. La source de vérité est
> l'en-tête du fichier, pas ce document :
>
> ```bash
> grep '^ \* Version:' plugins-dev/aod-cod-form/aod-cod-form.php
> ```

### Option A — Remplacer le dossier complet (recommandé, le plus sûr)

1. Récupère la version à jour du plugin depuis le template :
   `Template-Full/plugins-dev/aod-cod-form/` (relève l'en-tête `Version:` — c'est
   le numéro que tu devras retrouver sur le site après coup).
2. Zippe ce dossier → `aod-cod-form.zip` (la racine du zip doit être le dossier
   `aod-cod-form/`).
3. Sur le serveur (Gestionnaire de fichiers) → `public_html/wp-content/plugins/` :
   - **Supprime** l'ancien dossier `aod-cod-form`.
   - **Upload** `aod-cod-form.zip` → clic droit → **Extraire** → supprime le zip.
4. **Purge le cache** (Cache Manager + LiteSpeed → *Purge All*). Une page en cache
   sert l'ancien JavaScript : le widget Turnstile ne s'affiche pas, et le jeton
   manque à l'envoi — le formulaire refuserait alors de vrais clients.
   *(Ce n'est plus le time-trap qui pose ce risque : il tolère précisément le champ
   absent pour cette raison — cf. l'encadré plus haut. C'est le Turnstile, lui
   bien obligatoire une fois les clés saisies, qui l'impose.)*

> Le branding, les pixels, la livraison, etc. sont stockés en base — remplacer le
> dossier du plugin **ne perd aucun réglage**.

### Option B — Appliquer seulement les fichiers modifiés

Si tu préfères un patch minimal, remplace uniquement ces fichiers par ceux du
template (⚠️ `class-aod-cod-turnstile.php` est **apparu** en v1.19.0 — sur un
clone antérieur il est à **ajouter**, pas seulement à remplacer) :

```
aod-cod-form.php
includes/class-aod-cod-form.php
includes/class-aod-cod-turnstile.php   ← absent des clones < 1.19.0
includes/class-aod-cod-contact.php     ← le formulaire de CONTACT vérifie aussi
                                          le jeton Turnstile (côté serveur)
assets/js/aod-cod-form.js
assets/css/aod-cod-form.css
languages/aod-cod-form-ar.po
languages/aod-cod-form-ar.mo
```

> 🚨 `class-aod-cod-contact.php` manquait à cette liste. Le Turnstile protège
> **deux** formulaires, pas un : la commande COD **et** la page Contact. Un patch
> qui l'oubliait rendait le widget sur Contact **sans le vérifier côté serveur** —
> un état incohérent, et une fausse impression de couverture.

### Vérifier après mise à jour

- Plugins (ou code) : la version affichée correspond à celle relevée dans le template.
- Passe une **commande test** sur le site → elle doit fonctionner normalement.
- Renvoie **tout de suite** une 2ᵉ commande avec **le même numéro** → message
  « *Une commande a déjà été enregistrée avec ce numéro…* » = ✅ protection active.

---

## Volet 2 — Diagnostiquer un site déjà attaqué

But : repérer **la signature du bot** (son IP, son user-agent) propre à CE site,
car elle change d'une attaque à l'autre.

Dans **phpMyAdmin** → onglet **SQL** (adapte le préfixe `wp_` si besoin) :

```sql
-- A) Volume de commandes par statut (un pic de « wc-processing » = suspect)
SELECT status, COUNT(*) n FROM wp_wc_orders GROUP BY status ORDER BY n DESC;

-- B) Top des IP : une IP qui concentre des milliers de commandes = le bot
SELECT ip_address, COUNT(*) n
FROM wp_wc_orders
GROUP BY ip_address ORDER BY n DESC LIMIT 15;

-- C) Top des user-agents : un UA figé sur des milliers de commandes = le bot
--    (les vrais clients DZ ont des UA mobiles variés : Instagram, FBAV, iPhone…)
SELECT user_agent, COUNT(*) n
FROM wp_wc_orders
GROUP BY user_agent ORDER BY n DESC LIMIT 15;
```

Note l'**IP** dominante (souvent un datacenter) et/ou l'**user-agent** figé : ce
sont tes deux critères de nettoyage. Vérifie qu'aucune **vraie** commande
(confirmée/complétée) ne les utilise :

```sql
-- Combien de commandes NON-spam porteraient ces critères ? (doit être 0)
SELECT COUNT(*) FROM wp_wc_orders
WHERE status IN ('wc-aod-confirmed','wc-completed','wc-processing')   -- tes vrais statuts
  AND (ip_address = 'IP_DU_BOT' OR user_agent LIKE '%UA_DU_BOT%')
  AND id IN ( /* tes vraies commandes connues, ou laisse vide */ );
```

> Règle de prudence : les commandes du bot sont **toujours en `processing`/`pending`
> et jamais confirmées**. Si une commande confirmée porte le critère, **affine** le
> critère avant de supprimer.

---

## Volet 3 — Nettoyer les commandes spam (WooCommerce HPOS)

> **HPOS** (stockage haute performance) : les commandes ne sont **pas** dans
> `wp_posts` mais dans `wp_wc_orders` + tables liées. Le script ci-dessous nettoie
> toutes les tables concernées, dans une transaction (tout ou rien).
>
> **Remplace** `IP_DU_BOT` et `UA_DU_BOT` par les valeurs trouvées au Volet 2.
> Garde **au moins un** des deux critères ; supprime la ligne `OR …` si tu n'en
> utilises qu'un.

```sql
-- ⚠️ SAUVEGARDE LA BASE AVANT. phpMyAdmin → onglet SQL → coller → Exécuter.
START TRANSACTION;

CREATE TEMPORARY TABLE _bot_ids AS
  SELECT id FROM wp_wc_orders
  WHERE status IN ('wc-processing','wc-pending')          -- jamais les confirmées
    AND ( ip_address = 'IP_DU_BOT'
          OR user_agent LIKE '%UA_DU_BOT%' );

DELETE FROM wp_woocommerce_order_itemmeta
  WHERE order_item_id IN (
    SELECT order_item_id FROM wp_woocommerce_order_items
    WHERE order_id IN (SELECT id FROM _bot_ids) );
DELETE FROM wp_woocommerce_order_items     WHERE order_id IN (SELECT id FROM _bot_ids);
DELETE FROM wp_wc_orders_meta              WHERE order_id IN (SELECT id FROM _bot_ids);
DELETE FROM wp_wc_order_addresses          WHERE order_id IN (SELECT id FROM _bot_ids);
DELETE FROM wp_wc_order_operational_data   WHERE order_id IN (SELECT id FROM _bot_ids);
DELETE FROM wp_wc_order_product_lookup     WHERE order_id IN (SELECT id FROM _bot_ids);
DELETE FROM wp_wc_order_tax_lookup         WHERE order_id IN (SELECT id FROM _bot_ids);
DELETE FROM wp_wc_order_coupon_lookup      WHERE order_id IN (SELECT id FROM _bot_ids);
DELETE FROM wp_wc_order_stats              WHERE order_id IN (SELECT id FROM _bot_ids);
DELETE FROM wp_wc_orders                   WHERE id       IN (SELECT id FROM _bot_ids);

DROP TEMPORARY TABLE _bot_ids;
COMMIT;

-- Contrôle (doit retomber au nombre de tes vraies commandes)
SELECT COUNT(*) AS commandes_restantes FROM wp_wc_orders;
```

### Alternative WP-CLI (si tu as SSH)

```bash
# Lister ce qui serait supprimé (dry-run), par IP du bot :
wp eval '
$ids = wc_get_orders(["limit"=>-1,"status"=>["processing","pending"],"return"=>"ids"]);
$n=0; foreach($ids as $id){ $o=wc_get_order($id);
  if($o && $o->get_customer_ip_address()==="IP_DU_BOT"){ $n++; } }
echo "$n commandes ciblées\n";'

# Suppression définitive :
wp eval '
$ids = wc_get_orders(["limit"=>-1,"status"=>["processing","pending"],"return"=>"ids"]);
foreach($ids as $id){ $o=wc_get_order($id);
  if($o && $o->get_customer_ip_address()==="IP_DU_BOT"){ $o->delete(true); } }
echo "fait\n";'
```

---

## Volet 4 — Bloquer l'attaque en cours (niveau serveur)

Tant que le code n'est pas mis à jour **ou** si le flood est massif, bloque l'IP
immédiatement : zéro PHP exécuté, ça stoppe net.

Colle **tout en haut** de `public_html/.htaccess` (avant `# BEGIN WordPress`),
en remplaçant l'IP :

```apache
# === Blocage bot spam COD ===
<RequireAll>
  Require all granted
  Require not ip IP_DU_BOT
</RequireAll>
# === fin blocage ===
```

> Plusieurs IP ? Ajoute autant de lignes `Require not ip …`. Derrière Cloudflare,
> bloque plutôt l'IP dans Cloudflare (Security → WAF) car `.htaccess` ne verra que
> les IP du proxy.

---

## Ordre d'intervention conseillé

1. **Sauvegarde** (fichiers + base).
2. **Bloque l'IP** en `.htaccess` (stoppe le flot tout de suite).
3. **Nettoie** les commandes spam (Volet 3) → `/gestion` redevient utilisable.
4. **Mets à jour le plugin** à la version du template (Volet 1) → protection durable.
5. **Purge le cache**, puis teste une commande réelle.

---

## Volet 5 — Activer le Turnstile (parade définitive multi-IP)

Le coupe-circuit IP ralentit un bot mono-IP, mais un attaquant qui **tourne sur
des centaines de proxies** passe sous le seuil par IP. La parade **définitive** est
le **Cloudflare Turnstile** : un CAPTCHA invisible dont le jeton est **vérifié côté
serveur** avant création de la commande. Un bot qui POST en direct sur
`admin-ajax.php` n'a **jamais** de jeton valide → rejeté, quelle que soit son IP.

Depuis la **v1.19.0**, le Turnstile est **livré dans le code** ; il reste juste à
créer un widget Cloudflare (gratuit) et coller 2 clés. **Tant que les clés sont
absentes, il est inactif** (le formulaire marche comme avant) — donc sûr à laisser
en place sur tous les clones.

> 📄 **Procédure détaillée pas-à-pas (nouveau projet) : voir `Turnstile.md`.**
> Le résumé ci-dessous suffit pour une activation rapide.

1. **Créer le widget** — <https://dash.cloudflare.com> → **Turnstile** → *Add widget*.
   - Domaine : celui du site · Type : **Managed** (invisible dans la majorité des cas).
   - Copie la **Site Key** (publique) et la **Secret Key** (privée).
2. **Saisir les clés** — 2 options :
   - **Simple** : WordPress → **Réglages → « COD Anti-spam »** → colle les 2 clés →
     Enregistrer. La page affiche **✅ Actif** quand les deux sont présentes.
   - **Plus sûr (hors base)** : dans `wp-config.php`, avant « *C'est tout…* » :
     ```php
     define( 'AOD_COD_TURNSTILE_SITE_KEY',   '0x4AAA...' );
     define( 'AOD_COD_TURNSTILE_SECRET_KEY', '0x4AAA...' );
     ```
     Les constantes ont **priorité** sur la page de réglages.
3. **Purge le cache** puis **teste une commande** depuis un mobile → doit passer
   normalement (le widget est invisible). Un POST direct sans jeton doit être
   **rejeté** (403).

> **Fail-open / fail-closed** : jeton absent ou invalide → commande **rejetée**
> (c'est le cas des bots) ; mais si Cloudflare est **injoignable** (incident réseau)
> → la commande **passe** quand même, pour ne jamais bloquer un vrai client.
> Idéalement, mettre aussi le domaine derrière Cloudflare (**Bot Fight Mode**).

---

## Checklist rapide

- [ ] Sauvegarde faite (fichiers + base)
- [ ] IP du bot identifiée (Volet 2) et bloquée en `.htaccess`
- [ ] Commandes spam nettoyées, `/gestion` accessible
- [ ] Plugin `aod-cod-form` à la version du template, cache purgé
- [ ] Commande test OK + 2ᵉ commande même numéro = refusée
- [ ] (Si récidive multi-IP) **Turnstile activé** : widget Cloudflare créé + 2 clés saisies (Réglages → COD Anti-spam = ✅ Actif)
