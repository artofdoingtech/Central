# UX — Refonte de l'espace de gestion `/gestion` (« Laste Premium »)

> **Statut : livrée** (Phase 5, étapes 5.1 → 5.8). Spécification UI/UX de la
> refonte de l'espace gérant `/gestion` (plugin `aod-client-dashboard`) — elle
> reste la **référence** du design system : toute nouvelle page de `/gestion` doit
> s'y conformer (tokens, composants, contraste AA).
> Objectif : un rendu **homme-machine élégant, cohérent et pro** pour le gérant,
> sans changer la logique métier ni enfreindre les règles non-négociables de
> `CLAUDE.md` / `TEMPLATE.md`.
>
> Chantier suivi comme **Phase 5** dans [`ROADMAP-AMELIORATIONS.md`](ROADMAP-AMELIORATIONS.md).
> État des lieux technique : [`ARCHITECTURE.md`](ARCHITECTURE.md).

---

## 0. Décisions cadrées (owner)

| Décision | Choix retenu |
|---|---|
| **Direction visuelle** | **Élever le thème « Laste »** : on garde l'ADN noir profond + jaune signature, on raffine l'exécution (hiérarchie, espacements, ombres, états, cohérence des composants). Évolution, pas révolution. |
| **Ampleur** | **Réorganisation du contenu de chaque page + harmonisation des composants + polish.** La structure applicative (11 sections, routeur, AJAX) ne change pas. |
| **Branding** | **Accent piloté par le Personnalisateur.** Le jaune `#FFE21E` codé en dur devient un token dérivé du branding → chaque clone a SA couleur. Défaut = jaune Laste (rien ne bouge tant que le client ne choisit pas). |

**Non-négociables rappelés** (priment sur toute proposition ci-dessous) :
générique (rien en dur), jamais de panier/compte, FR⇄AR + RTL sur tout le
visible, mobile-first, sécurité par capability serveur (le masquage d'UI n'est
qu'un confort), nouvelle section = nouvelle classe.

---

## 1. Diagnostic — pourquoi « ça n'est pas ajusté »

L'existant est déjà riche et fonctionnel, mais l'incohérence vient de
**l'accumulation par étapes** (Phases 1→4) sans langage de design unifié :

1. **Pas d'échelle typographique.** ~10 tailles de police en dur (`11.5`, `12.5`,
   `13`, `13.5`, `14`, `14.5`, `15`, `18`, `19`, `23`px) → hiérarchie floue.
2. **Pas d'échelle d'espacement.** Paddings au cas par cas (`11px 12px`,
   `14px 16px`, `20px 30px`, `26px 30px`…) → rythme vertical irrégulier.
3. **Composants dupliqués.** **Quatre** implémentations de menu déroulant custom
   (`statussel`, `csel`, `catdd`, `filterdd`) qui font presque la même chose ;
   **deux** systèmes d'onglets (`.aod-cd-tabs` pilules vs `.aod-cd-filterdd`).
4. **En-têtes de page incohérents.** Le titre vit dans la topbar, mais chaque
   section réintroduit son propre titre (`form-title`, `od-sub`, `tracking-head`…)
   avec des styles différents. Pas de patron « en-tête de page » commun
   (titre + sous-titre + action principale).
5. **Cartes hétérogènes.** Les cartes stats de l'Accueil (`minmax(140px)`) n'ont
   ni la même taille, ni le même style que les blocs des autres pages.
6. **États inégaux.** Les états vides, de chargement (AJAX), d'erreur et de focus
   ne sont pas traités partout de la même façon.
7. **Accent codé en dur.** `--cd-yellow:#FFE21E` fixé dans le CSS → non conforme à
   la règle « branding via Personnalisateur », et impossible à décliner par clone.
8. **Contraste.** Le jaune est parfois utilisé comme **texte** (badges, focus) sur
   fond clair → contraste insuffisant (WCAG). Le jaune doit rester un **fond**
   (avec encre sombre) ou un **filet d'accent**, jamais du texte sur blanc.

Le plan : poser un **socle de tokens + composants unifiés**, puis réorganiser
chaque page dessus.

---

## 2. Design system « Laste Premium »

### 2.1 Accent piloté par le branding (correctif de conformité)

**Mécanisme cible** (générique, indépendant du thème client) :

- Le plugin enregistre **un réglage Personnalisateur** `aod_cd_accent`
  (« Couleur d'accent — Gestion ») + amorçage automatique depuis les `theme_mod`
  courants du thème s'ils existent (`get_theme_mod('theme-color')` /
  `accent_color`…), **défaut = `#FFE21E`** (jaune Laste conservé).
- À l'ouverture de `/gestion`, `render_app()` **injecte les tokens en `:root`
  inline** (avant le `<link>` CSS), calculés depuis l'accent choisi :
  - `--cd-accent` : la couleur du client.
  - `--cd-accent-strong` : version survol (assombrie ~8 %).
  - `--cd-accent-ink` : **encre sur accent**, noir ou blanc **choisi
    automatiquement par luminance** (garantit le contraste quel que soit
    l'accent — un accent foncé prend du texte blanc).
  - `--cd-accent-soft` : teinte 12 % pour fonds/halo (`color-mix`).
- Le CSS n'utilise plus jamais `#FFE21E` en dur : uniquement `var(--cd-accent*)`.

> Résultat : par défaut le dashboard reste **noir + jaune** (aucun changement
> visible), mais un clone « bleu marine » ou « vert » obtient un espace de
> gestion cohérent avec sa marque, sans toucher au code. ✅ Règle n°1.

### 2.2 Tokens — tels que **spécifiés** en Phase 5

> 🚨 **Ne recopie pas les valeurs ci-dessous : la source de vérité est
> `plugins-dev/aod-client-dashboard/assets/css/dashboard.css:3-84`.** Ce bloc est la
> spécification d'origine ; le code livré s'en écarte volontairement sur trois points,
> et suivre le bloc produit du CSS **silencieusement mort** :
>
> - **Familles renommées** — `--fs-*` → `--cd-fs-*`, `--sp-*` → `--cd-sp-*`,
>   `--r-*` → `--cd-r-*`. Écrire `var(--sp-4)` ne déclenche aucune erreur : la
>   déclaration est simplement ignorée, et le repli passe inaperçu. C'est le mode
>   d'échec nommé par la Phase 17.
> - **Tokens jamais implémentés** — `--cd-ink-3`, `--cd-surface` (le code a
>   `--cd-card`), `--cd-surface-2`, les échelles `--fw-*` / `--lh-*`, l'élévation
>   `--e-1/2/3` (remplacée par un `--cd-shadow` unique), `--t-fast` / `--t-base`.
> - **Tokens SUPPRIMÉS par la Phase 46** parce qu'ils étaient déclarés sans être
>   lus — `--cd-radius` / `--cd-radius-sm` (doublons exacts de `--cd-r-md` /
>   `--cd-r-sm` : 74 usages d'un côté, 16 de l'autre, **deux noms vivants pour une
>   même valeur**), `--cd-r-lg` (aucun rayon de 16 px dans la feuille),
>   `--cd-yellow-d` (alias de `--cd-accent-strong`, zéro lecteur) et `--cd-sp-8`
>   (32 px n'apparaît dans aucune propriété d'espacement).
> - **Valeurs assombries pour AA** — `--cd-muted`, `--cd-ok`, `--cd-warn`,
>   `--cd-bad`, `--cd-info`, `--cd-accent-ink` et `--cd-focus` ont tous été
>   durcis en 5.8 par rapport à cette spec. Le code déclare en outre
>   `--cd-accent-rgb`, `--cd-accent-wash`, `--cd-side-txt` et les alias `--cd-yellow*`.

```css
:root{
  /* Neutres (encre / surfaces) */
  --cd-ink:#181818; --cd-ink-2:#2a2a2a; --cd-ink-3:#4a4a4a;
  --cd-muted:#6b6b6b; --cd-soft:#9a9a9a;
  --cd-bg:#f4f5f4; --cd-surface:#ffffff; --cd-surface-2:#fafafa;
  --cd-line:#e6e6e6; --cd-line-2:#f0f0f0;

  /* Accent (injecté depuis le branding — §2.1) */
  --cd-accent:#FFE21E; --cd-accent-strong:#e6c800;
  --cd-accent-ink:#181818; --cd-accent-soft:#fff6c2;

  /* Sémantiques (statuts) */
  --cd-ok:#15803d; --cd-ok-soft:#e1f7e8;
  --cd-warn:#b45309; --cd-warn-soft:#fff2cc;
  --cd-bad:#c81e1e; --cd-bad-soft:#fde7e5;
  --cd-info:#1d4ed8; --cd-info-soft:#e6f0ff;

  /* Typo — échelle unique */
  --fs-xs:11px; --fs-sm:12.5px; --fs-base:14px; --fs-md:16px;
  --fs-lg:18px; --fs-xl:22px; --fs-2xl:28px;
  --fw-reg:400; --fw-med:500; --fw-semi:600; --fw-bold:700; --fw-black:800;
  --lh-tight:1.2; --lh-base:1.5;

  /* Espacement — base 4 */
  --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px;
  --sp-5:20px; --sp-6:24px; --sp-8:32px; --sp-10:40px;

  /* Rayons / élévation */
  --r-sm:8px; --r-md:12px; --r-lg:16px; --r-pill:999px;
  --e-1:0 1px 2px rgba(24,24,24,.06);           /* repos    */
  --e-2:0 8px 24px rgba(24,24,24,.10);          /* survol   */
  --e-3:0 16px 40px rgba(24,24,24,.18);         /* modale   */
  --cd-focus:0 0 0 3px var(--cd-accent-soft);   /* focus    */
  --t-fast:.12s; --t-base:.18s;
}
```

> 🔄 **Trois tokens ajoutés par la Phase 15** — non listés ci-dessus parce que ce
> bloc est la cible de la Phase 5, laissée telle qu'elle a été spécifiée :
>
> | Token | Valeur | Pourquoi il ne peut pas être écrit en dur |
> |---|---|---|
> | `--cd-side` | **dérivé** de l'accent | Le fond de la sidebar porte la couleur du client. L'écrire ici ferait hériter cette couleur à **tous les futurs clones** (règle template n°1). Cf. [`ICONES-ET-CHARTE-GESTION.md`](ICONES-ET-CHARTE-GESTION.md) §4. |
> | `--cd-accent-on-dark` | **dérivé** de l'accent | Sert **deux** fonds sombres différents ; calibré sur le plus clair des deux. |
> | `--cd-ease-out` | `cubic-bezier(.23,1,.32,1)` | Les easings natifs sont mous. ⚠️ **`ease-in` est proscrit** dans `/gestion` : il retarde le début du mouvement, l'instant précis que l'œil regarde — à durée égale, l'interface *paraît* plus lente. |

> 🚨 **ET LE POINT LE PLUS IMPORTANT DE CE CHAPITRE, ajouté par la Phase 46 : cette
> échelle a été DÉCLARÉE puis jamais appliquée, et personne ne l'a vu pendant huit
> phases.** Le diagnostic d'ouverture (§1, point 1) nommait comme problème n°1
> « ~10 tailles de police en dur ». Mesuré le 2026-07-30 : **20 tailles distinctes**
> et **13 %** seulement des `font-size` passant par un cran ; l'espacement était à
> **5 %**. Le problème n°1 avait **doublé** après la phase censée le régler.
>
> Ce que la 46.5 en a tiré, et qui vaut plus que la correction : **une échelle sans
> cliquet décore un fichier, elle ne le gouverne pas.** D'où **R5** de
> `test-charte-surfaces.php` — un plafond du nombre de valeurs littérales, qui ne
> peut que DESCENDRE. On ne garde pas « l'échelle est appliquée » (invérifiable : une
> valeur hors échelle peut être parfaitement légitime, et §2.2 en liste), mais le
> seul invariant qui empêche la rechute : **le compte ne remonte pas.**
>
> Et **R4** garde l'autre moitié : tout token déclaré doit avoir un lecteur, ou
> disparaître. C'est ce qui a fait tomber les cinq tokens ci-dessus — dont deux que
> l'inventaire manuel du verdict A.22 n'avait pas vus.

**Règle typographique** : titres en Barlow 700/800 avec `letter-spacing:-.2px` ;
sur-titres/labels en `--fs-xs` MAJUSCULES `letter-spacing:.6px` couleur
`--cd-muted`. Une seule police (Barlow) — déjà en place.

> ⚠️ **« Déjà en place » voulait dire « déclarée », pas « disponible ».** Jusqu'à
> la **Phase 20**, Barlow venait de `fonts.googleapis.com` : sur un clone sans
> réseau — le cas nominal, `TEMPLATE.md` §3 — toute cette règle typographique
> tombait en silence sur la police système, `letter-spacing` compris. Les `woff2`
> sont désormais embarqués (`assets/fonts/`).
>
> 📌 **Et une part de la règle ne s'applique pas à l'arabe** : Barlow n'a aucun
> glyphe arabe. En AR, `/gestion` est rendu par la police système — c'était déjà
> vrai avant l'embarquement et ça le reste. Les valeurs de `letter-spacing`
> ci-dessus sont calibrées sur le latin ; ne pas conclure à un défaut de rendu en
> les voyant sans effet sur un écran arabe.

### 2.3 Composants unifiés (inventaire → cible)

| Composant | Aujourd'hui | Cible |
|---|---|---|
| **Bouton** | `btn`, `btn-primary`, `btn-sm`, `btn-danger` | + `btn-ghost`, `btn-icon` ; **3 hauteurs** (32/40/44) ; accent = `var(--cd-accent)` ; états loading (spinner) unifiés |
| **Select / dropdown** | 4 implémentations (`statussel`, `csel`, `catdd`, `filterdd`) | **1 seul** `aod-cd-select` (bouton + menu `position:fixed`, `<select>` natif caché pour submit + a11y). Variantes : simple, multi, avec pastille ⚠️ *voir la note ci-dessous* |
| **Onglets / filtres** | pilules `.aod-cd-tabs` **+** dropdown `.filterdd` | **1 « segmented control »** pour ≤ 5 choix ; dropdown seulement si > 5 ou mobile étroit |
| **Carte** | `card`, `form`, `chartcard`, `soon` divergents | **1 `.aod-cd-card`** + variantes (`--pad`, `--flush`) ; ombre `--e-1`, survol `--e-2` |

> ⚠️ **Select / dropdown : livré autrement que spécifié.** La classe
> `aod-cd-select` **n'existe pas** dans `dashboard.css`, et les 4 noms d'origine
> (`.aod-cd-statussel*`, `.aod-cd-csel*`, `.aod-cd-catdd*`, `.aod-cd-filterdd`)
> subsistent. Ce qui a été mutualisé : le **comportement** (clavier + ARIA) en 5.2b,
> puis la **peau** (surface flottante) en Phase 17. Le **positionnement reste séparé
> à dessein** — deux menus `fixed` placés par le JS, un panneau `absolute` ancré à
> son parent — et ce n'est pas un reliquat. Cf. `TEMPLATE.md` §9 (Phase 17).
> De même ligne « Bouton » : le code livre `.aod-cd-btn--ghost`, mais **aucun**
> `btn-icon`.
| **En-tête de page** | titre topbar + titres locaux dispersés | **NOUVEAU `.aod-cd-pagehead`** : titre + sous-titre + zone d'actions ; présent sur **toutes** les pages |
| **Carte KPI** | `cards`/`card-val`/`card-lab` (petites, Accueil) | **1 `.aod-cd-kpi`** taille unique + **delta vs période précédente** (▲/▼ %) + icône teintée |
| **Tableau** | `table` + `table--cards` (mobile) | Généralisé partout ; en-têtes, alternance, survol, tri, pagination homogènes |
| **Badge / pastille de statut** | dots `statussel` **+** pills `track-pill` | **1 système de pastilles** sémantiques (couleur + libellé), réutilisé commandes/suivi/stock |
| **Champ de formulaire** | `field`/`label`/`input` OK mais hints/erreurs ad hoc | `field` standard : label + input + **hint** + **message d'erreur** + états invalides |
| **État vide** | `.aod-cd-empty` (texte gris) | **Composant** : icône + titre + explication + CTA optionnel |
| **Chargement** | rien (ou texte « Chargement… ») | **Skeletons** pour contenus AJAX (détail commande, suivi, import) |
| **Toast / Modale** | OK | Conservés, alignés sur les tokens (rayons, ombres, accent) |

### 2.4 Deux composants arbitrés APRÈS la Phase 5 (Phase 45, 2026-07-29)

Ce document est la référence du design system : ce qui suit s'y ajoute, et prime
sur l'inventaire de §2.3 pour ces deux objets. Le détail et les mesures vivent
dans [`ROADMAP-AMELIORATIONS.md`](ROADMAP-AMELIORATIONS.md) §A.21.

**a) Barre d'action groupée (`.aod-cd-bulkbar`) — surface de la charte, et permanente.**
Elle était un bandeau noir (`--cd-ink`) *masqué* tant que rien n'était
sélectionné. Deux défauts : la page saute de 52 px à la première sélection, et
rien n'annonce à l'avance ce qu'on pourra faire d'une sélection. Elle devient une
**carte claire ordinaire** (`--cd-card` / `--cd-line` / `--cd-radius`), **toujours
rendue**, dont les actions sont `disabled` + `aria-disabled` tant que la sélection
est vide.

> 🚨 **La règle générale à en retenir, parce qu'elle vaut au-delà de ce
> composant :** ce qui protège l'utilisateur d'une action destructive, c'est
> **l'impossibilité d'agir**, jamais l'invisibilité du bouton. Masquer est un
> moyen commode qui coûte de la prévisibilité ; désactiver dit la même chose et
> montre l'avenir. Une future barre d'outils de `/gestion` se conçoit ainsi.
>
> Et le corollaire, tiré du même dossier : la décision inverse (masquer) avait
> été prise en 2026 pour une bonne raison, écrite dans le CSS — mais le cliquet
> que ce commentaire annonçait n'a **jamais été écrit**. Un invariant d'interface
> qui n'est pas mesuré ne survit pas à la prochaine demande. Il en va d'un
> arbitrage de design comme d'un test : **s'il n'a pas de témoin, il n'a pas eu
> lieu.**

**b) Le rang d'actions de ligne (`.aod-cd-call-actions`) porte TOUTES les actions
de la ligne.** Il était né en 34.1 pour « Appeler » et « WhatsApp ». Il accueille
désormais aussi **« Détail »**, et le numéro de commande redevient du **texte**
— un identifiant se lit, il ne se clique pas.

> 🚨 **Deux pièges portés par ce rang, à connaître avant d'y ajouter quoi que ce
> soit.** Il n'est rendu que si la ligne porte un **téléphone** : toute action
> qui n'a rien à voir avec le téléphone doit d'abord rendre ce rang
> inconditionnel, sans quoi elle disparaît silencieusement pour une partie des
> lignes. Et une action déplacée vers ce rang doit **conserver la classe** que
> son binder JS cible (`querySelectorAll`) : c'est ce qui permet de changer
> l'élément porteur sans toucher au JS. Si le JS doit changer, c'est que le
> contrat a bougé — s'arrêter et relire.

**c) Le rail replié a sa PROPRE géométrie verticale (45.5).** Replié, la barre
latérale ne montre que des icônes — mais elle héritait du rythme vertical de la
barre *dépliée*, texte compris. Mesuré : **1069 px de haut en français, 1348 px
en arabe**, pour 12 icônes ; elle déborde donc sur toute fenêtre d'ordinateur
portable, et la barre de défilement qui en résulte **décentre les icônes** là où
elle prend de la largeur. Le rail replié règle désormais sa hauteur sur ce qu'il
montre (l'icône) et sur la fenêtre (`clamp()`), les intitulés de groupe devenant
un filet de 1 px.

> 🚨 **La règle à retenir : dans un rail d'icônes, aucun texte invisible ne doit
> peser en hauteur.** `opacity:0` masque à l'œil et ne retire **rien** à la mise
> en page — la boîte reste, avec sa ligne de texte, ses interlignes et ses
> retours à la ligne. Et comme la hauteur d'une ligne dépend de l'écriture, un
> rail qui n'affiche aucun caractère finit par être **279 px plus haut en arabe
> qu'en français**. La parade n'est jamais `display:none` (elle retirerait les
> liens de l'ordre de tabulation et leur nom accessible — invariant de la 43.8) :
> c'est de **borner explicitement la hauteur** des boîtes concernées. Borner
> n'est pas masquer.

---

## 3. Coque & navigation

### 3.1 Barre latérale — regroupement logique

Les **12 entrées** restent (aucune section supprimée), mais **groupées** par
domaine avec de petits intitulés de groupe → l'œil trouve plus vite, la liste
« à plat » disparaît. Purement visuel (mêmes liens, mêmes URLs).

> ℹ️ Le schéma ci-dessous date d'avant deux évolutions livrées : il **omet
> « Commandes abandonnées »** (Phase 11, rattachée au groupe *Ventes*) — d'où
> « 11 » à l'origine — et il dessine des **emojis**, tous supprimés en Phase 15 au
> profit du catalogue vectoriel `AOD_CD_Icon`. Les noms logiques réels (`home`,
> `orders`, `abandoned`, …) sont dans
> [`ICONES-ET-CHARTE-GESTION.md`](ICONES-ET-CHARTE-GESTION.md) §5.1.

```
┌──────────────────────┐
│ ▓ {NOM BOUTIQUE}     │  ← branding (logo/nom du Personnalisateur)
├──────────────────────┤
│ PILOTAGE             │
│  🏠 Accueil          │
│ VENTES               │
│  🧾 Commandes        │
│  📍 Suivi commande   │
│ CATALOGUE            │
│  📦 Produits         │
│  🏷️ Catégories       │
│  📊 Stock            │
│ LIVRAISON            │
│  🚚 Tarifs & transp. │
│ MARKETING            │
│  🎯 Pixels           │
│  💬 WhatsApp         │
│ ADMINISTRATION       │
│  👥 Équipe           │
│  👤 Mon compte       │
├──────────────────────┤
│ 🌐 Voir la boutique  │
│ ⎋ Déconnexion        │
└──────────────────────┘
```

- Les groupes vides (confirmateur restreint) ne s'affichent pas → l'en-tête de
  groupe n'apparaît que s'il contient au moins une entrée visible.
- État actif : fond `--cd-accent` + encre `--cd-accent-ink` (contraste garanti).
- Mobile : hamburger off-canvas conservé (déjà bon), groupes repris.

### 3.2 Barre du haut (topbar)

- Gauche : **fil d'ariane léger** `Gestion / {Section}` (remplace le simple H1)
  pour l'orientation.
- Droite : switcher **FR/العربية**, badge périmètre (confirmateur), pastille
  utilisateur (initiales + nom). Alignement et hauteur constants.

### 3.3 Patron « en-tête de page » (`.aod-cd-pagehead`) — le grand unificateur

Chaque page ouvre avec **le même bloc** :

```
┌───────────────────────────────────────────────────────────┐
│  Commandes                                   [+ Action ▸]  │   ← titre + action principale (droite)
│  Suivez et confirmez les commandes COD.                    │   ← sous-titre (1 ligne, gris)
├───────────────────────────────────────────────────────────┤
│  [ Toutes 128 ][ En attente 42 ][ Confirmées ][ … ]  🔎…  │   ← barre d'outils (filtres + recherche)
└───────────────────────────────────────────────────────────┘
```

C'est ce patron répété qui crée l'impression « ajusté / harmonisé » sur
l'ensemble.

---

## 4. Page par page

> Pour chaque page : **problème actuel** → **réorganisation cible** → composants.
> Aucune donnée ni action n'est retirée : on **regroupe, ordonne et unifie**.

### 4.1 🏠 Accueil (tableau de bord)
- **Actuel** : filtres → onglets (Revenus/Livraison/Confirmation) → cartes stats
  (petites) → graphe SVG. Le premier écran ne dit pas « quoi faire maintenant ».
- **Cible** :
  1. En-tête « Bonjour {prénom} » + date + période active.
  2. **Bandeau « À traiter »** (actionnable) : 3–4 tuiles-raccourcis —
     *commandes en attente*, *colis bloqués*, *stock bas*, *à confirmer* —
     chaque tuile mène à la liste filtrée. Priorité au pilotage quotidien.
  3. **Segmented control** Revenus / Confirmation / Livraison (accent unifié).
  4. **Rangée de KPI** `.aod-cd-kpi` homogènes **avec delta** vs période
     précédente (▲ +12 % / ▼ −4 %).
  5. Graphe SVG conservé (déjà élégant), aligné sur les tokens (grille, tooltip).
  6. Barre de filtres compacte et **sticky** (produit / confirmateur / dates).
- Les 3 onglets partagent **exactement** la même grille KPI + le même graphe.

### 4.2 🧾 Commandes
- **Actuel** : dropdown de filtre + recherche + tableau **13 colonnes** (dense),
  bulk-bar, modale de détail.
- **Cible** :
  1. En-tête + **segmented control** de statut (avec compteurs) au lieu du
     dropdown (dropdown gardé en repli mobile > 5 états).
  2. **Priorisation des colonnes** : N° · Client (nom+tél empilés) · Wilaya ·
     Total · Statut · Suivi · Note. Les colonnes secondaires (commune, prix
     produit/livraison, date) passent en **détail** de la carte mobile et en
     info-bulle/ligne repliable desktop → tableau respirable.
  3. Pastille de statut sémantique unifiée + sélecteur inline (composant select
     unique).
  4. Modale de détail : typographie et sections alignées sur les tokens ;
     **skeleton** au chargement.
  5. Bulk-bar : conservée, restylée (accent + danger cohérents).

### 4.3 📍 Suivi commande
- **Actuel** : intro + tableau de suivi + timeline en modale.
- **Cible** : en-tête commun ; **pastilles d'état normalisées = même composant**
  que Commandes ; timeline modale peaufinée (points colorés, dates, transporteur) ;
  bouton « Actualiser » avec état loading unifié ; filtre par transporteur/état.

### 4.4 📦 Produits
- **Actuel** : liste + formulaire en accordéon (déjà bon), mais titres/sections
  hétérogènes ; barre d'enregistrement en bas de page (peut être loin).
- **Cible** :
  1. Liste : cartes/tableau homogènes (vignette · nom · prix · **badge stock** ·
     statut · actions).
  2. Formulaire : accordéons regroupés en **5 blocs clairs** — *Informations
     générales* · *Prix & offres* · *Photos* · *Stock & logistique* ·
     *Options & variantes*.
     > **Arbitrage (étape 5.5).** Le 5ᵉ bloc était initialement écrit
     > « Stock & catégories ». Les **catégories restent dans « Informations
     > générales »** : ranger un produit, c'est dire *ce qu'il est*, pas
     > comment on l'expédie. Le bloc logistique regroupe stock, seuil d'alerte,
     > poids et prix d'achat.
  3. **Barre d'enregistrement sticky** en bas (Enregistrer / Annuler + message),
     toujours visible.
  4. Uniformiser galerie, palette couleurs, offres (aperçu d'économie) sur les
     mêmes composants de champ.

### 4.5 🏷️ Catégories
- **Actuel** : formulaire de création + liste de lignes (vignette, nom, parent,
  compteur, actions).
- **Cible** : en-tête commun ; **carte de création** distincte en tête ; lignes
  = composant `row` homogène ; vignette cliquable soignée ; états vides illustrés.

### 4.6 📊 Stock
- **Actuel** : encart d'alertes + tableau avec édition inline (déjà solide).
- **Cible** : en-tête + **KPI stock** en tête (ruptures / stock bas / valeur) via
  `.aod-cd-kpi` ; alertes en cartes sémantiques (rouge/ambre) ; badges de stock =
  **même système de pastilles** ; inputs inline stylés ; lecture seule claire
  pour le confirmateur `aod_cap_stock`.

### 4.7 🚚 Livraison (Tarifs & transporteurs)
- **Actuel** : la page empile tarifs wilayas + transporteurs + import → longue et
  peu lisible.
- **Cible** : **3 blocs à en-têtes explicites**, éventuellement en sous-onglets :
  1. *Tarifs par wilaya* (recherche + table + case « gratuit » + tout cocher).
  2. *Transporteurs* (cartes-accordéon, badge configuré/inactif, bouton tester).
  3. *Import des tarifs* (préview/marge/backup — déjà bien, aligné sur les tokens).
  - La flèche RTL de l'import (correctif existant) est conservée.

### 4.8 🎯 Pixels & Tracking
- **Actuel** : formulaire à plat de champs d'IDs.
- **Cible** : **une carte par plateforme** (Meta · TikTok · Snapchat · Google)
  avec icône/repère couleur, champ d'ID + aide, et **indicateur actif/inactif**.
  Lecture immédiate de ce qui est branché.

### 4.9 💬 WhatsApp
- **Actuel** : formulaire + bouton test.
- **Cible** : carte unique « Notifications WhatsApp » : numéro + clé CallMeBot +
  **bouton Tester** avec retour d'état unifié (ok/erreur) + aide contextuelle.

### 4.10 👥 Équipe
- **Actuel** : CRUD confirmateurs + permissions à cocher + affectation produits.
- **Cible** : en-tête + **cartes membres** (avatar-initiales, nom, dernière
  connexion, badges de permissions actives) ; modale d'édition avec les
  **6 capabilities en grille de toggles** clairs + périmètre produits ;
  génération de mot de passe mise en valeur (rappel « notez-le »).

### 4.11 👤 Mon compte
- **Actuel** : formulaire réglages en grille + zone de danger (reset).
- **Cible** : sections « Profil » / « Sécurité » ; grille de champs homogène ;
  **zone de danger** conservée (déjà excellente : phrase à recopier), restylée
  sur les tokens sémantiques `bad`.

---

## 5. États, micro-interactions, accessibilité, mobile/RTL

### 5.1 États systématiques (chaque composant)
Repos · **survol** (élévation `--e-2`) · **focus-visible** (`--cd-focus`) ·
actif · **chargement** (spinner/skeleton) · **vide** (composant dédié) ·
**erreur** (message + bordure `bad`). Transitions `--t-fast`/`--t-base`,
`prefers-reduced-motion` respecté (déjà amorcé).

### 5.2 Accessibilité (WCAG AA visé)
- **Contraste** : accent = fond + encre auto (§2.1) ; **jamais** de texte accent
  sur blanc. Vérifier tous les gris de texte ≥ 4.5:1.
- **Clavier** : les selects/dropdowns unifiés = navigation ↑↓, Entrée, Échap,
  `role="listbox"/"option"`, `aria-expanded`. **Focus-trap** dans la modale.
- `aria-live` pour les toasts ; libellés `aria-label` sur les boutons-icônes.

### 5.3 Mobile-first & RTL
- Cibles tactiles ≥ 44 px ; tableaux → cartes (`table--cards`, déjà en place) ;
  modales en **feuille plein écran** avec `safe-area-inset`.
- **RTL par flux logique** : conserver `margin-inline`, `inset-inline`,
  `text-align:start` — aucune règle `[dir=rtl]` sauf glyphes bidi-neutres
  (ex. flèche import). Tester chaque page en AR à 390 px.

---

## 6. Plan de mise en œuvre — Phase 5 (étapes BMAD)

Découpage aligné sur la méthode du dépôt (une étape = une story + un commit) et
la DoD commune (C.2). **Le socle d'abord** (5.1) : sans lui, chaque page
re-diverge.

| Étape | Intitulé | Contenu | Taille |
|---|---|---|---|
| **5.1** | Socle design tokens + accent branding | Tokens `:root`, accent depuis Personnalisateur (§2.1), échelles typo/espacement/rayons/ombres. Aucun changement de logique. | M |
| **5.2** | Composants unifiés | Bouton, **select unique** (fusion des 4), segmented control, carte, KPI, pastille de statut, **en-tête de page**, état vide, skeleton, champ. | M/L |
| **5.3** | Coque & navigation | Sidebar groupée, topbar (fil d'ariane), application de `.aod-cd-pagehead` partout. | M |
| **5.4** | Accueil + Commandes + Suivi | Réorg des 3 pages « pilotage quotidien » sur les nouveaux composants. | L |
| **5.5** | Produits + Catégories + Stock | Réorg catalogue (barre save sticky, blocs, badges stock). | L |
| **5.6** | Livraison + Pixels + WhatsApp | Blocs Livraison, cartes plateformes, carte WhatsApp. | M |
| **5.7** | Équipe + Mon compte | Cartes membres, grille de toggles, sections compte. | M |
| **5.8** | Recette globale FR/AR/RTL/mobile + a11y | Audit clavier/contraste, i18n `--strict`, recette 390 px + AR sur les 11 pages. | M |

**DoD spécifique refonte** (en plus de C.2) :
- [ ] Aucune couleur d'accent en dur (que `var(--cd-accent*)`).
- [ ] Aucune régression fonctionnelle : mêmes sections, mêmes actions, mêmes
      handlers AJAX (nonce + capability inchangés).
- [ ] Un seul composant select (les 4 anciens supprimés).
- [ ] `.aod-cd-pagehead` présent sur les 11 pages.
- [ ] Contraste AA vérifié ; navigation clavier des dropdowns et focus-trap modale.
- [ ] Testé AR (RTL) + mobile 390 px page par page.

---

## 7bis. Maquette de référence (langage visuel verrouillé)

Une maquette HTML interactive **fait foi** pour le rendu cible « Laste Premium ».
Elle couvre les **4 archétypes d'écran** du dashboard — ce qui verrouille le
langage visuel avant d'écrire le code de production :

| Archétype | Page maquette | Ce qu'elle prouve |
|---|---|---|
| Tableau de bord | **Accueil** | en-tête de page, bandeau « À traiter », KPI + delta, graphe SVG, filtres |
| Liste / données | **Commandes** | segmented control de statut, tableau priorisé (8 col.), pastilles unifiées, sélection groupée |
| Formulaire | **Produits** | liste ⇄ formulaire, accordéons 5 blocs, offres, variantes, galerie, **barre d'enregistrement sticky** |
| Config multi-blocs | **Livraison** | sous-onglets (tarifs / transporteurs / import), cartes transporteur, préview d'import + **flèche RTL corrigée** |

La maquette démontre aussi, en direct : **FR ⇄ AR + RTL** (miroir complet) et
**accent piloté par le branding** (l'encre sur accent est recalculée par
luminance). Elle utilise une police système en repli de Barlow (le CSP des
artefacts bloque les webfonts) — le produit réel chargera Barlow.

> Décisions verrouillées par l'owner (« je te laisse trancher, je veux que tout
> soit parfait ») : direction Laste Premium, réorg + harmonisation, accent
> branding. La maquette sert de **contrat visuel** pour les étapes 5.1 → 5.7.

## 7. Ce qui NE change pas (garde-fous)
- Les 11 sections, le routeur `/gestion/<section>`, les 27 handlers AJAX, les
  rôles/capabilities, HPOS, le cookie de langue partagé, le parcours acheteur COD.
- La logique métier (stats, import tarifs, envoi transporteur, packs, offres).
- On ne réintroduit **jamais** panier/compte, et rien de spécifique à un client.
