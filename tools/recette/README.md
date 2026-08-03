# Recette navigateur du clone

Des scripts qui pilotent un vrai navigateur — sur l'espace gérant `/gestion` ET
sur le côté public — et **échouent avec un code de sortie ≠ 0** si quelque chose
casse. Ils ne sont pas décoratifs : c'est cette recette qui a trouvé les cinq bugs
produit corrigés à l'étape 5.8 (deux d'entre eux étaient des bugs **souris**,
invisibles à la lecture du code), et le bug de regex qui rendait muets les liens
YouTube `/shorts/` à l'étape 6.3.

```bash
bash tools/recette/run.sh              # tout
bash tools/recette/run.sh contraste    # une seule porte, parmi :
#   contraste | noms | clavier | souris | rtl        → /gestion : a11y, RTL, mobile
#   mouvement                                        → transitions (gestion + public), appui, mouvement réduit
#   public | landing | bilingue | riche | categories → côté acheteur
#   cod                                              → une VRAIE commande de bout en bout
#   conversions (alias : capi) | abandonnees | suivi → conversions serveur, prospects, colis
#   confirmateur                                     → /gestion vu par un CONFIRMATEUR
```

Prérequis : la stack Docker du clone tourne, et Chromium est présent
(`npx playwright install chromium`, ou `AOD_CHROMIUM=/chemin/vers/chrome`).
L'URL et le compte admin sont lus dans le `.env` du clone — rien n'est codé en dur.

### Sous quelle identité une porte tourne-t-elle ? (Phase 22.1)

`login( page )` utilise le compte du `.env`. Pour un autre compte :
`login( page, { user, pass } )` — et la bascule fonctionne **dans le même
contexte** (les cookies sont purgés à chaque appel).

`whoAmI( ctx )` rend le `user_login` réellement connecté, ou `null`. Chaque
`login()` **publie** l'identité obtenue (`ⓘ identité : …`) et **refuse de
continuer** (`exit 2`) si ce n'est pas celle demandée.

> 🚨 **Pourquoi c'est une porte à part entière, et pas un confort.** Toute la
> recette se connectait en gérant — or `manage_woocommerce` satisfait **toutes**
> les gardes de `/gestion`. Une porte destinée à un confirmateur pouvait donc
> verdir en ayant testé le gérant. C'est ce qui a rendu le défaut de la Phase 21
> invisible : il n'a pas échappé aux contrôles, il n'a jamais été dans leur champ.
> La règle du témoin ci-dessous vaut aussi pour **qui regarde**, pas seulement pour
> ce qui est regardé.

> 📌 **L'identité se lit dans le cookie `wordpress_logged_in_*`, et c'est mesuré.**
> Ne pas repartir sur les voies « évidentes » : `/wp-json/wp/v2/users/me` rend
> **401** (l'auth par cookie exige un `X-WP-Nonce` que `/gestion` n'expose pas), le
> DOM ne montre le login que par accident (un `display_name` distinct le
> masquerait), et `window.AOD_CD` ne porte aucune clé d'identité — l'y ajouter
> reviendrait à modifier le **plugin** pour satisfaire un **test**.
> Le cookie est `HttpOnly` : d'où `ctx.cookies()`, et une signature en `ctx`.

## 🚨 Avant d'écrire un test de couverture : la règle du témoin

Un test qui balaie un ensemble — « aucun emoji ne subsiste », « toutes les
sections ont une icône », « tout accent passe AA » — a un mode d'échec que
l'assertion elle-même ne peut pas voir : **passer au vert en n'ayant regardé
qu'une partie de l'ensemble, voire rien**. Il ne casse pas, il ne prévient pas,
et il rend exactement le même signal qu'un test qui a tout vu.

Six occurrences réelles sur les Phases 15 et 16 — dont une où le garde-fou
inspectait **10 éléments sur 875** en se déclarant vert. Le détail et les cinq
règles qui en découlent : `docs/ROADMAP-AMELIORATIONS.md` §C.2. `mouvement.js`
porte les trois garde-fous nés de ces erreurs — le lire est le plus court chemin
pour comprendre ce qu'on attend ici.

En résumé, exigible sur tout nouveau test de recette :

1. **Remonter le compte** de ce qui a réellement été inspecté, et l'affirmer.
2. **Un invariant plutôt qu'un seuil** — un seuil choisi large laisse passer les
   régressions modestes.
3. **Nommer des témoins** : au moins un cas précis, cité pour ce qu'il prouve.
4. **Passer par le code de production**, jamais par une copie de sa formule.
5. **Forcer au rouge** avant de faire confiance — et vérifier que l'injection a
   bien eu lieu, sinon on n'a éprouvé que son propre `sed`.

## À quoi sert chaque script

| Script | Ce qu'il vérifie |
| --- | --- |
| `a11y-contraste.js` | Contraste WCAG **AA** (4,5:1 ; 3:1 sur grand texte), calculé sur le **rendu** — fond effectif remonté d'ancêtre en ancêtre, alphas composés. |
| `mouvement.js` | Que le **mouvement existe vraiment**. Aucune autre porte ne le regarde : une transition tuée par erreur ne casse aucune assertion, elle rend juste l'interface sèche. Pour chacune des **5 feuilles** (voir ci-dessous) : parsée **en entier** (`{` du fichier == règles exposées — un seuil ne suffit pas, une cassure en fin de fichier ne perd que 6 règles), un compte d'inspection **mesuré par feuille** (le plancher de `dashboard.css` appliqué à une petite feuille rougirait d'office), et aucune règle n'animant `all`. Plus : l'appui produit un vrai creux (souris **maintenue**, style **calculé**), les états **publics** sont déclenchés au navigateur, et `prefers-reduced-motion` est **réellement** respecté dans `/gestion` — une surcharge trop haute dans la cascade ne s'applique pas, et l'écran est identique pour qui n'a pas activé le réglage. |
| `a11y-noms.js` | Tout champ / bouton-icône / image a un nom accessible. Un `placeholder` **ne compte pas** : il disparaît à la première frappe. |
| `a11y-clavier.js` | Dropdowns pilotables au clavier (modèle *combobox* : le focus reste sur le bouton, l'option visée est désignée par `aria-activedescendant`) et **piège de focus** des modales. |
| `a11y-souris.js` | Non-régression **souris** des menus flottants — voir l'avertissement ci-dessous. |
| `rtl-mobile.js` | Les 12 sections × { FR desktop, FR mobile 390 px, AR mobile 390 px } : erreurs PHP, erreurs console, débordement horizontal, bascule RTL. Capture d'écran à chaque échec. |
| `public-3-pages.js` | Les règles non négociables du côté public, en **visiteur non connecté** : nav à exactement 3 entrées (desktop **et** mobile), aucun lien panier/compte/add-to-cart, URLs du tunnel mortes, formulaire COD sur la fiche produit, AR + RTL. |
| `landing-produit.js` | La **page de vente** par produit (Phase 6), en visiteur : en-tête/pied du thème conservés (nav 3 entrées, switcher), formulaire COD + ancre `#aod-commander`, toujours aucun tunnel, chrome AR sans reste de français, 390 px sans débordement, barre collante qui rallonge le `body` et vise le formulaire, et un produit **sans** landing qui ne charge ni son CSS ni son JS. `run.sh` découvre le produit via WP-CLI (`_aod_landing_enabled`) ; **aucune landing sur le clone → le scénario se retire** (sortie 0) : elle est optionnelle par produit. |
| `categories-accueil.js` | La **sélection des catégories sur l'accueil** (Phase 9, `run.sh categories`), en visiteur : les catégories cochées « Afficher sur l'accueil » dans `/gestion` s'affichent dans l'**ordre** saisi (l'ordre bat l'alphabet), **vides comprises** ; une non cochée est absente (sélection = remplacement) ; le nom **arabe** s'affiche en AR ; et **sans sélection**, l'accueil retombe sur toutes les catégories non vides (repli non-régressif). Fixtures semées par `scripts/recette-seed-phase9.php` (dont un témoin non vide, pour être clone-agnostique). ⚠️ Les assertions sont **cadrées sur la section « Nos catégories »** : le thème (Astra) affiche la catégorie d'un produit sous chaque produit de « Nos produits ». |
| `catalogue-bilingue.js` | Le **contenu marchand bilingue** (Phase 7, `run.sh bilingue`) : les 10 surfaces acheteur dans les deux langues, zéro français résiduel côté AR, repli intégral d'un produit non traduit, description rendue **avec ses `<p>`** (preuve que le filtre passe en priorité 9, avant `wpautop`), 390 px. Fixtures `scripts/recette-seed-bilingue.php`, aux valeurs arabes **volontairement absentes du dictionnaire et des `.po`** : si elles s'affichent, elles viennent bien de la saisie du gérant. Un seed en échec fait **échouer** la recette (pas de retrait silencieux). |
| `description-riche.js` | La **description produit riche** (Phase 8, `run.sh riche`) : titres et listes rendus, `<img>` avec `srcset` **régénéré au rendu** (jamais stocké), façade YouTube (0 iframe et 0 requête avant le clic, iframe `nocookie` après), `<video preload="none">` (0 octet avant lecture) servie en HTTP 200, FR **et** AR/RTL à 390 px, et un témoin en texte plat qui doit rester inchangé. Fixtures `scripts/recette-seed-phase8.php` — image GD et MP4 **réels**, pas des URLs bidon. |
| `abandonnees.js` | Les **commandes abandonnées** dans `/gestion` (Phase 11, `run.sh abandonnees`, gérant connecté) : le prospect abandonné et celui en cours apparaissent, le **converti jamais** (il n'est plus abandonné), badges, boutons Appeler (`tel:`) et WhatsApp (`wa.me`, 0→213), onglets, recherche par nom **et** par téléphone, AR + RTL à 390 px. Fixtures `scripts/recette-seed-phase11.php` (tokens `ZZP11`). |
| `commandes-suivi.js` | La **partition Commandes / Suivi** (Phase 12, `run.sh suivi`) : une commande expédiée a **quitté** « Commandes » (onglets Confirmées **et** Toutes) et apparaît dans « Suivi » avec son n° de colis ; la non expédiée reste dans « Commandes » et est absente de « Suivi ». Fixtures `scripts/recette-seed-phase12.php`, **envoi auto désactivé le temps du seed** → aucun appel transporteur réel. |
| `../../scripts/recette-capi.php` | Les **conversions serveur** Meta / TikTok / Snapchat (Phases 13 et 14, `run.sh conversions`, alias `capi`). Seule porte **sans navigateur** : un envoi serveur-à-serveur se prouve en **interceptant la requête sortante** (`pre_http_request`) — **aucun appel réel** aux régies, aucun compte requis. Couvre l'opt-in, les formats propres à chaque plateforme, les PII hachées, la **déduplication** avec le pixel navigateur, les boutons « Tester la connexion », et (14.1) le fait que le chemin acheteur ne déclenche **aucun** appel HTTP et que l'envoi différé garde ses cookies d'appariement. Auto-suffisante : crée et supprime ses fixtures, restaure l'option pixels. |
| `confirmateur.js` | **`/gestion` vu par un CONFIRMATEUR** (Phase 22, `run.sh confirmateur`) — **la seule porte du dépôt qui ne se connecte pas en gérant**. `AOD_CD_Roles::user_can()` répond vrai à toute `aod_cap_*` dès qu'on a `manage_woocommerce` : le gérant satisfait **toutes** les gardes, et la couche d'autorisation entière n'était donc exercée **nulle part**. C'est ce qui a rendu le défaut de la Phase 21 invisible pendant des mois. Sème **deux** confirmateurs aux capabilities **complémentaires** (4 + 3 = les 7) : sections ouvertes / fermées au menu, sections gérant **inatteignables par URL** (le code *replie*, il ne refuse pas — asserter sur `location.pathname` serait toujours vert), gardes AJAX sur un **vrai code HTTP** dans les deux sens, et `wp-admin` qui **redirige** vers `/gestion`. Fixtures `scripts/recette-seed-phase22.php` — purge **par méta**, jamais par rôle : un clone en service porte de vrais confirmateurs créés par le gérant. ⚠️ La sonde `test_carrier` envoie `carrier=` **vide** : nommer un transporteur ferait **écrire** les réglages et **appeler l'API du livreur pour de vrai**. |
| `commande-cod.js` | **Le tunnel de vente, pour de vrai** (`run.sh cod`). Passe une commande COD en visiteur anonyme sur un **produit jetable semé** (avec une option obligatoire), puis va la **relire en base** (HPOS) : statut `processing`, téléphone saisi, total calculé côté serveur, moyen de paiement `cod`, méta `_aod_source`, et l'**option choisie gravée sur la ligne de commande**. Éprouve les couches anti-bot dans leur ordre réel — honeypot, time-trap (< 3 s), téléphone hors format DZ, verrou « 1 commande / 24 h par numéro » — en vérifiant à chaque fois le **motif** du refus, jamais seulement qu'un refus a eu lieu. La commande créée est supprimée en fin de porte. C'est le seul scénario qui prouve qu'une boutique **encaisse** ; tous les autres prouvent qu'elle *s'affiche*. |

> 🎬 **Ce que `mouvement` couvre depuis l'étape 18.1 — et ce qu'il ne couvre pas.**
>
> Jusqu'à la Phase 18, cette porte ne lisait qu'une seule feuille (`dashboard.css`)
> sur une seule zone (`/gestion`). Les **229 règles** des trois feuilles publiques et
> les **6** du `<style>` inline du switcher FR/AR — 21 % du CSS du dépôt — n'étaient
> **jamais inspectées**. Les trois dernières `transition` implicites y ont survécu à
> la Phase 16 entière : elles n'avaient pas échappé au contrôle, elles n'avaient
> jamais été contrôlées.
>
> | Feuille | Page visitée | Contexte |
> |---|---|---|
> | `dashboard.css` | `/gestion/` | session gérant |
> | `aod-cod-pages.css` | `/` | **visiteur anonyme** |
> | `aod-lang-css` (inline) | `/` | visiteur anonyme — repéré par `ownerNode.id`, **pas** par `href` (il n'en a pas) |
> | `aod-cod-form.css` | `/contact/` | visiteur anonyme |
> | `aod-landing.css` | page de vente si le clone en a une | visiteur anonyme |
>
> Une feuille **absente rougit en la nommant** : jamais un retrait. Le code de sortie
> `3` est global au process — l'utiliser ici emporterait l'audit de `/gestion`, qui
> lui fonctionne.
>
> S'y ajoute un **cliquet statique**, sans navigateur, qui balaie **tout**
> `plugins-dev/` (fichiers `.css` **et** blocs `<style>` des `.php`). Il existe parce
> qu'un audit CSSOM ne voit que les feuilles nommées en dur : une feuille nouvelle
> recréerait le trou de 18.1 à l'identique, en silence.
>
> **Mouvement réduit, des deux côtés** (étape 19.1). La porte vérifie que
> `prefers-reduced-motion` est honoré dans `/gestion` **et** côté acheteur. Le
> contrôle public se fait en **deux temps** : on établit d'abord que l'appui produit
> un mouvement *sans* le réglage, *puis* qu'il disparaît avec. Sans cette base,
> l'assertion serait vraie pour rien — `transform:none` est aussi la valeur par
> défaut, et une première version restait verte alors que la surcharge avait perdu
> la cascade. Elle vérifie aussi que les `transform` de **positionnement** (centrage,
> variante RTL) n'ont **pas** été neutralisés au passage.


### Pourquoi les portes sèment leurs données plutôt que d'emprunter celles du clone

Trois portes ont été reprises le 2026-07-19 parce qu'elles **empruntaient** l'état de
la boutique. C'est la leçon la plus utile de ce dossier :

- **`cod`** prenait « le dernier produit publié ». Sur un produit à **options
  obligatoires**, le formulaire refuse la soumission **côté JavaScript**, avant le
  moindre appel réseau — et les assertions honeypot / time-trap / verrou 24 h, qui se
  contentaient de constater *qu'un refus avait eu lieu*, restaient **vertes sans avoir
  jamais atteint le serveur**. Sept assertions de relecture en base disparaissaient en
  outre en silence. Elle sème désormais son produit, sélectionne les options, et
  vérifie le **motif** de chaque refus.
- **`souris`** changeait le statut d'une **vraie commande** du gérant. Si le statut
  tiré était « Confirmée », `auto_send()` faisait partir un **colis réel** chez le
  transporteur — qu'aucun retour arrière ne rappelle. Elle sème désormais une commande
  jetable sans ligne produit et se borne à des statuts inertes.
  🔁 **Depuis la Phase 47.2, ce danger-là n'existe plus** : confirmer une commande
  n'envoie plus rien (le hook a été retiré, l'envoi est un clic sur « Envoyer »).
  La commande jetable reste néanmoins de mise — un changement de statut écrit du
  stock et de la traçabilité. *Ce paragraphe raconte un défaut réel à sa date : il
  est conservé, pas réécrit.*
- **`categories`** exigeait un état **global** (« aucune catégorie cochée ») que sa
  graine, volontairement additive, ne peut pas produire. La bascule sélection ⇄ repli
  est maintenant prouvée en **unitaire** (`tests/unit/test-home-categories.php`), sans
  base et sans toucher aux réglages du gérant.

**Règle qui en découle : une assertion d'ABSENCE doit toujours être adossée à une
présence, ou vérifier le motif.** Sinon elle passe au vert quand la page est vide,
quand le site est mort, ou quand le refus vient d'ailleurs.

## Le piège n° 1 : l'accent

**`run.sh` rejoue l'audit de contraste sur six accents**, pas un seul. C'est
essentiel, et voici pourquoi.

L'accent de `/gestion` est choisi par le client dans le Personnalisateur
(`aod_cd_accent`). L'accent **par défaut** est un jaune très clair, donc très
contrasté : il **masque** des fautes de contraste qu'un accent ordinaire — un
bleu de marque, un bordeaux — révèle immédiatement. Un audit vert sur l'accent
par défaut ne prouve donc rien du tout sur les clones réels.

C'est exactement comme ça qu'on a trouvé, entre autres, que l'encre posée *sur*
l'accent tombait à 4,21:1 (sous AA) pour un bleu banal comme `#1878f0`, et que
trois éléments utilisaient l'accent **en texte** sur fond sombre — invisibles dès
que le client choisit un accent foncé.

→ **Sur un nouveau clone, une fois l'accent du client réglé, relance au minimum
`bash tools/recette/run.sh contraste`.**

> 🚨 **Ce piège s'est presque refermé sur la recette elle-même (corrigé le
> 2026-07-21).** `run.sh` posait chaque accent par `wp theme mod set`, dont la
> sortie est avalée, et `a11y-contraste.js` **ne lisait jamais l'accent** : si le
> `set` échouait en silence (service `wpcli` tombé, OOM), les six passages
> auditaient le **même** accent — six verts identiques sous six en-têtes
> différents. La porte prétendait balayer six accents et pouvait n'en éprouver
> qu'un. Désormais `run.sh` passe l'accent attendu à la porte, qui lit
> `--cd-accent` **sur la page** et rougit s'il ne correspond pas.
>
> ⚠️ **Et un chemin destructif** : l'accent d'origine était capturé **avant** de
> relancer `wpcli` ; sur un service tombé, la capture rendait une chaîne vide,
> indistinguable d'un clone sans accent — et `restore_accent()` faisait alors un
> `theme mod remove`, **effaçant la couleur de marque du gérant**. La capture
> distingue maintenant « pas d'accent » de « lecture impossible » (sentinelle), et
> ne restaure que si elle a réellement lu quelque chose.

## Le piège n° 2 : rendre accessible peut casser la souris

Deux bugs réels, tous deux invisibles à un audit clavier :

1. Si un menu se ferme sur le `blur` de son bouton, le `mousedown` sur une option
   déplace le focus **avant** que le `click` ne parte → la sélection à la souris
   ne fonctionne plus du tout. (Un `<select>` natif, lui, ne prend jamais le
   focus au `mousedown` : d'où le `preventDefault` dans `dashboard.js`.)
2. Si un menu se ferme au `scroll`, donner simplement le focus à un bouton
   partiellement visible fait défiler la page — et l'évènement `scroll` arrive
   **après** le `click` → le premier clic ne fait rien.

`a11y-souris.js` existe pour attraper ces régressions. Il fait un vrai
changement de statut en AJAX puis **restaure la valeur d'origine** : la recette
ne laisse aucune trace en base.

## Le piège n° 3 : la recette COD écrit dans la base

`commande-cod.js` crée de **vraies** commandes — c'est tout l'intérêt. Deux
conséquences, gérées automatiquement **en local seulement** (`localhost`/`127.0.0.1`,
même garde-fou que `scripts/dev-seed.php`) :

- chaque tentative qui franchit le honeypot et le time-trap **consomme du quota IP**
  (10/h). Le script lève donc les deux verrous anti-flood avant de commencer
  (`aod_cod_ipf_*`, `aod_cod_ph_*`) — sinon la recette ne serait jouable que trois
  fois par heure. Purge **ciblée** : c'était un `transient delete --all`, qui
  emportait aussi les caches de tarifs et de communes des transporteurs, refaits
  ensuite par des appels API externes ;
- la commande créée est supprimée **en fin de porte**, repérée par son téléphone
  (unique à chaque exécution). Les portes `bilingue` et `riche` ne purgeaient
  qu'au *début* : leur commande de garde survivait donc jusqu'au run suivant, au
  statut `processing` — donc **comptée dans le CA affiché au gérant**. Une recette
  ne doit pas fausser les chiffres qu'elle observe. Un filet par nom de client
  reste joué à l'amorce, pour ramasser ce qu'une exécution interrompue aurait laissé.

Sur une cible **non locale**, le script ne purge rien et le dit.

### Codes de sortie : « retiré » n'est pas « passé »

| Code | Sens |
|---|---|
| `0` | passé |
| `2` | **argument invalide** — `run.sh` n'a reconnu aucun scénario, rien n'a été joué |
| `3` | **RETIRÉ** — le scénario n'avait pas d'objet sur ce clone, il n'a **rien vérifié** |
| autre | échec |

Deux portes se retirent légitimement : `landing` quand aucun produit n'a de page de
vente (elle est optionnelle par produit), et `cod` quand **Turnstile** est actif (pas
de jeton en test). Elles sortaient à `0`, donc **comptaient comme des succès** : le
bilan annonçait « tout passe » alors qu'une porte n'avait rien joué. `run.sh` les
compte désormais à part et les nomme en fin d'exécution.

Le code `2` répare la même faute d'un cran en amont. Le dispatch de `run.sh` est une
**suite de `if` indépendants**, pas un `case` : un argument que personne ne reconnaît
ne déclenchait aucune porte, n'affichait aucun avertissement, et sortait à `0`.
Autrement dit `run.sh contrast` — sans le « e » — rendait exactement le même vert que
`run.sh contraste`, en n'ayant **rien vérifié du tout**. La garde refuse désormais tout
scénario hors liste et affiche les noms valides.

## Ce que la recette ne couvre pas

Le **rendu visuel du thème client** : `public-3-pages.js` et `landing-produit.js`
vérifient les règles (nav, tunnel, RTL, débordement), pas l'esthétique. Le look du
thème déposé par l'owner se juge à l'œil, par clone — et le **contenu marchand**
d'une landing (ses textes `{ fr, ar }`) appartient au gérant : la recette contrôle
le chrome, pas ses phrases.

L'**envoi réel au transporteur** (création de colis) n'est pas joué non plus : la
recette API (`scripts/recette-api-transporteur.php`) reste en lecture seule, pour ne
pas faire naître de vrais colis chez Yalidine ou Noest.

La **logique pure** (calcul de marge, contraste AA, mapping des statuts colis,
schéma de la page de vente) n'est pas du ressort du navigateur : elle est couverte
par les tests unitaires — `bash tests/run.sh`.
