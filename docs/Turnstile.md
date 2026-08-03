# Turnstile — configuration du CAPTCHA anti-robot (formulaire COD)

> **À faire à chaque nouveau projet** une fois le site en ligne. Procédure ~5 min,
> **gratuite**. Concerne le plugin `aod-cod-form` **≥ 1.19.0** (livré dans le template).

## En bref

Le **Cloudflare Turnstile** est un CAPTCHA **invisible** dont le jeton est
**vérifié côté serveur** avant la création de la commande. C'est la parade de fond
contre le spam de fausses commandes COD : un bot qui poste en direct sur
`admin-ajax.php` n'a **jamais** de jeton valide → il est rejeté, **quelle que soit
son IP** (même derrière des centaines de proxies, là où les limites par IP échouent).

> ⚠️ **Le plugin est livré avec le Turnstile déjà intégré, mais INACTIF tant que
> les 2 clés ne sont pas saisies.** Sans clés, le formulaire fonctionne exactement
> comme avant. Il faut donc faire cette configuration pour l'activer.

C'est la couche la plus forte de l'anti-bot, en plus de : honeypot, time-trap
(**opportuniste** — il ne rejette pas un POST sans le champ), coupe-circuit IP
(10/h), limite 1 commande/24 h par téléphone (voir `RUNBOOK-ANTI-BOT-COD.md`).
C'est la **seule** qui résiste à une rotation de proxies.

---

## Étape 1 — Créer le widget Cloudflare (récupérer les 2 clés)

1. Va sur <https://dash.cloudflare.com> → menu **Turnstile** → **Add widget**.
   *(Un compte Cloudflare gratuit suffit ; le domaine n'a PAS besoin d'être géré
   par Cloudflare pour utiliser Turnstile.)*
2. Renseigne :
   - **Widget name** : le nom du site (ex. `monsite`).
   - **Hostname** : le domaine du site (ex. `monsite.com`). Ajoute aussi le
     `www.` et le domaine de préprod si tu testes dessus.
   - **Widget Mode** : **Managed** (recommandé — invisible dans la majorité des cas).
3. Valide → Cloudflare affiche deux clés. Copie-les :
   - **Site Key** (publique, commence par `0x4AAA…`) → ira dans le front.
   - **Secret Key** (privée) → ira côté serveur. **Ne la partage jamais publiquement.**

---

## Étape 2 — Saisir les clés dans WordPress

Deux méthodes, au choix (la méthode B a priorité si les deux sont utilisées).

### Méthode A — Page de réglages (simple)

1. WordPress → **Réglages → « COD Anti-spam »**.
2. Colle la **Site Key** et la **Secret Key** → **Enregistrer**.
3. La page affiche **✅ Actif** quand les deux clés sont présentes.

### Méthode B — Constantes dans `wp-config.php` (clés hors base, plus sûr)

Dans `public_html/wp-config.php`, **avant** la ligne `/* C'est tout, ne touchez pas… */` :

```php
define( 'AOD_COD_TURNSTILE_SITE_KEY',   '0x4AAA...votre_site_key...' );
define( 'AOD_COD_TURNSTILE_SECRET_KEY', '0x4AAA...votre_secret_key...' );
```

> Les constantes **priment** sur la page de réglages. Pratique pour garder les clés
> hors de la base (dumps, exports) et les versionner via ta config de déploiement.

---

## Étape 3 — Vérifier

1. **Purge le cache** (LiteSpeed / Cache Manager / plugin de cache) — sinon une page
   en cache pourrait servir l'ancien HTML sans le widget.
2. Ouvre une **page produit en navigation privée** → le widget Turnstile apparaît
   sous le formulaire (souvent juste une coche « Vérification… » qui se valide seule).
3. Passe une **commande test depuis un mobile** → elle doit fonctionner normalement.
4. *(Optionnel)* un POST direct doit être **rejeté** :
   ```bash
   curl -X POST 'https://monsite.com/wp-admin/admin-ajax.php' -d 'action=aod_cod_submit'
   ```
   → réponse d'erreur (pas de commande créée).

   > ⚠️ **Ce test ne prouve PAS que le Turnstile fonctionne.** Cette requête meurt
   > sur le **nonce** (`check_ajax_referer`), qui s'exécute bien **avant** toute
   > couche anti-bot — elle renvoie donc « erreur » même Turnstile désactivé. Il
   > vérifie que le point AJAX n'est pas ouvert à tous vents, rien de plus. Pour
   > éprouver le Turnstile lui-même, il faut un POST portant un nonce valide et
   > **pas** de `cf_turnstile` — ce qui suppose de lire le nonce dans le HTML de la
   > page produit.

---

## Comment ça marche (pour comprendre / débuguer)

- **Front** : l'API Turnstile est chargée là où un formulaire protégé s'affiche —
  la **page produit** (widget inséré avant « Confirmer la commande ») **et la page
  Contact** (`class-aod-cod-contact.php`). Les deux envoient un jeton, les deux le
  font vérifier côté serveur.
- **Serveur** : à la soumission, le jeton `cf-turnstile-response` est envoyé
  (`cf_turnstile`) et vérifié auprès de Cloudflare en **étape 4** du traitement
  (après honeypot, time-trap et coupe-circuit IP), **avant** de créer la commande.
- **Jeton à usage unique** : après un échec, le JS régénère automatiquement le jeton
  (`resetTurnstile()`) pour permettre une nouvelle tentative propre.
- **Politique de tolérance aux pannes** :
  - jeton **absent ou invalide** → commande **rejetée** (403) — c'est le cas des bots ;
  - Cloudflare **injoignable, en erreur (code ≠ 200) ou réponse illisible** →
    commande **acceptée** (*fail-open*) pour ne **jamais** bloquer un vrai client à
    cause d'un incident tiers.
>
> ⚠️ **Corollaire à connaître : une Secret Key invalide ou révoquée désactive la
> protection EN SILENCE.** Le *fail-open* ne couvre pas que la panne réseau : le
> code laisse passer sur `is_wp_error()`, sur **tout code HTTP ≠ 200** et sur toute
> réponse non décodable (`class-aod-cod-turnstile.php:146-151`). Une 5xx Cloudflare,
> un 403 sur clé révoquée ou une page d'erreur renvoyée par un proxy produisent donc
> le même résultat qu'un site sans Turnstile — sans aucun signal. La seule preuve
> d'activité réelle est le **widget visible côté front**.

---

## Dépannage

| Symptôme | Cause probable / solution |
|---|---|
| Le widget n'apparaît pas | Clés non saisies (Réglages → COD Anti-spam doit être **✅ Actif**), ou cache non purgé, ou tu n'es sur **ni** une page produit portant le formulaire COD **ni** la page Contact. |
| « Vérification anti-robot échouée » pour un vrai client | Le **hostname** du widget Cloudflare ne correspond pas au domaine réel (ajoute `www.`/préprod), ou une extension navigateur bloque `challenges.cloudflare.com`. ⚠️ Ça casse **aussi la page Contact**, pas seulement la commande — vérifie les deux. |
| Rien ne change / bots toujours là | Vérifie que la page de réglages affiche **✅ Actif**, puis que le **widget est visible** sur une page produit ET sur Contact, en navigation privée. ⚠️ **N'utilise pas le `curl` ci-dessus pour ça** : il meurt sur le nonce avant d'atteindre le Turnstile (cf. l'encadré de l'étape 3), donc il « réussit » même avec une Secret Key fausse. Une clé révoquée passe en *fail-open*, sans signal. |
| Widget visible mais lourd/challenge cliquable | Normal si trafic suspect ; le mode **Managed** durcit automatiquement. Pour de la préprod, un widget de test Cloudflare existe. |

---

## Checklist « nouveau projet »

- [ ] Widget Cloudflare Turnstile créé (Managed) avec le bon hostname
- [ ] Site Key + Secret Key saisies (Réglages → COD Anti-spam = **✅ Actif**) **ou** constantes dans `wp-config.php`
- [ ] Cache purgé
- [ ] Commande test mobile OK (widget invisible)
- [ ] Widget visible sur une page produit **et** sur Contact, en navigation privée
      *(seule preuve qui dépende réellement des clés — un `curl` ne teste pas le Turnstile)*
- [ ] *(Idéal)* domaine derrière Cloudflare avec **Bot Fight Mode** activé

---

*Voir aussi :* `RUNBOOK-ANTI-BOT-COD.md` (les 5 couches anti-spam, dont 4 sur un clone neuf, + nettoyage d'un
site déjà attaqué) · `DURCISSEMENT-SECURITE.md` (durcissement `.htaccess` général).
