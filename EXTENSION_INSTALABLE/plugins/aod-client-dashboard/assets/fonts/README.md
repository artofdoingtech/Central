# assets/fonts — Barlow embarqué

Police de l'espace gérant `/gestion`. Déclarée par les `@font-face` en tête de
`assets/css/dashboard.css`, jamais chargée autrement.

## Provenance

- **Fonte** : [Barlow](https://github.com/jpt/barlow) — Jeremy Tribby, version
  **v13** telle que servie par Google Fonts.
- **Licence** : **SIL Open Font License 1.1** (copie dans `LICENSE`).
  ⚠️ Pas MIT, contrairement aux icônes Phosphor : l'OFL **impose** de joindre la
  licence à toute redistribution des fichiers. Ne pas supprimer `LICENSE`.
- **Récupéré le** : 2026-07-21, depuis `fonts.gstatic.com` (les `woff2` que
  servait déjà le CDN — octet pour octet ce que les navigateurs recevaient avant).

## Pourquoi versionnés et pas chargés depuis un CDN

Même raison que `assets/icons/`, et elle vaut pour **toute ressource nécessaire au
rendu** — pas seulement les SVG : les conteneurs de ce template **n'ont pas
d'internet sortant fiable** (`TEMPLATE.md` §3). Un clone doit fonctionner le jour
de son installation.

Jusqu'à la Phase 20, `/gestion` chargeait Barlow depuis `fonts.googleapis.com`.
Trois conséquences, dans l'ordre où elles mordent :

1. **Sans réseau, la charte tombait en silence.** `font-family` retombe sur
   `-apple-system, …, sans-serif` : pas le même visage sur Windows, macOS et
   Android — le reproche exact fait aux emojis Unicode que la Phase 15 a retirés.
   🚨 En pire : un emoji manquant **se voit**, une police de repli **non**. L'écran
   reste lisible et crédible ; il n'est simplement plus le produit.
2. **Vie privée.** Chaque chargement émettait vers Google une requête portant l'IP
   du gérant et le `Referer` — depuis un back-office pourtant `noindex,nofollow`.
3. **Latence.** Deux `preconnect`, une résolution DNS et deux allers-retours
   (CSS puis `woff2`) avant le premier texte peint.

Cf. `docs/ARCHITECTURE.md` §7 dette 18 et ROADMAP §C.5 (D9) / Phase 20.

## Contenu

| Fichier | Graisse | Usage |
|---|---|---|
| `barlow-latin-400.woff2` | 400 | corps de texte |
| `barlow-latin-500.woff2` | 500 | libellés |
| `barlow-latin-600.woff2` | 600 | sous-titres, boutons secondaires |
| `barlow-latin-700.woff2` | 700 | titres, boutons |
| `barlow-latin-800.woff2` | 800 | titres forts (`--cd-fs-2xl`) |

**5 fichiers, ~113 Ko** — soit moins que le seul `woff2` d'une fonte variable.

## Deux choses à savoir avant d'y toucher

### 1. Barlow n'a **aucun glyphe arabe** — et c'est voulu

La moitié de `/gestion` est en AR. Elle est rendue par la **police système**, via
le repli de `dashboard.css` (`font-family:"Barlow",-apple-system,…`). C'était déjà
le cas quand la police venait du CDN ; l'embarquement n'y change rien.

→ **Ne pas chercher à « corriger » l'arabe qui ne serait pas en Barlow.** Ce n'est
pas un embarquement incomplet, c'est la fonte qui ne couvre pas l'écriture. Y
ajouter une fonte arabe est un **choix de design**, pas un correctif — et il
faudrait alors la choisir pour son appariement avec Barlow, pas par défaut.

Corollaire : les `letter-spacing` de `docs/UX-GESTION-REFONTE.md` §2.2 sont
calibrés sur le latin et n'ont pas d'effet visible en arabe.

### 2. Le poids se décide au **sous-ensemble**, pas à la graisse

Google sert Barlow en 3 sous-ensembles (`latin`, `latin-ext`, `vietnamese`) ×
5 graisses = **15 fichiers**. On n'en embarque que **5** : le français est
intégralement couvert par `latin` (`U+0000-00FF` pour les accentuées, plus
`U+0152-0153` pour « œ »). `latin-ext` et `vietnamese` ne serviraient jamais et
tripleraient le poids.

Les `unicode-range` déclarés dans `dashboard.css` sont **repris de l'amont**. Ils
rendent le chargement **paresseux** : une graisse n'est tirée que si un caractère
de sa plage est réellement peint. Mesuré au navigateur le 2026-07-21 : **4 des
5 fichiers**, la 500 n'étant demandée par aucun écran.

> ⚠️ **Ce gain est par graisse, pas par langue.** On pourrait croire qu'un écran
> arabe ne télécharge rien : c'est faux, il en tire autant — le **nom de la
> boutique** et les **chiffres** restent latins. Vérifié en `?aod_lang=ar` : mêmes
> 4 fichiers qu'en français. Une première version de ce README affirmait le
> contraire ; la mesure l'a démenti.

## Mettre à jour la fonte

1. Sur **l'hôte** (pas dans le conteneur — il n'a pas de réseau fiable) :
   ```bash
   curl -A "Mozilla/5.0 … Chrome/120 …" \
     "https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&display=swap"
   ```
   ⚠️ **Sans un `User-Agent` moderne, Google renvoie du `ttf`**, pas du `woff2`.
2. Y relever les URLs du bloc `/* latin */` **seulement**, les télécharger ici.
3. Vérifier que les `unicode-range` de `dashboard.css` correspondent toujours.
4. `bash tests/run.sh` — `test-ressources-locales.php` refuse tout hôte externe.

## Ce qu'il ne faut pas faire

- ❌ Rajouter un `<link>` vers un CDN de polices « juste pour tester ». Le cliquet
  de `test-ressources-locales.php` le refuse, et c'est exactement ce qu'il garde :
  la règle porte sur **l'hôte externe**, pas sur `fonts.googleapis.com` nommément —
  sinon le prochain CDN passerait.
- ❌ Supprimer `LICENSE`. L'OFL l'exige (cf. Provenance).
- ❌ Déclarer les `@font-face` ailleurs qu'en tête de `dashboard.css`. Un
  `@font-face` doit précéder son usage ; et la coque n'a qu'une feuille.
