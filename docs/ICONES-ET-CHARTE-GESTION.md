# ICONES-ET-CHARTE-GESTION.md — Icônes vectorielles & charte teintée de `/gestion`

> **Spécification.** Statut : **✅ PHASE SOLDÉE — 10 étapes sur 10** (2026-07-20).
> Livré : catalogue `AOD_CD_Icon`, sidebar, KPI, collatéral — **l'espace gérant ne
> contient plus aucun emoji de chrome** — et la **charte teintée** (§4) : le fond
> de la sidebar est désormais dérivé de l'accent du Personnalisateur, et le
> **nettoyage CSS** (§10), et l'**accent vert appliqué** (§9 — `#00A651`, choisi
> sur mesure : les verts foncés de club sont indiscernables du vert sémantique
> `--cd-ok`).
> Périmètre : **l'espace gérant `/gestion` uniquement**. La partie publique
> (Accueil / Boutique / Contact / page produit / page de vente) **ne change pas**
> — décision explicite de l'owner. Cf. §8 pour ce que cela implique.
>
> À lire avant toute retouche des icônes ou des couleurs du tableau de bord.
> Compléte [`UX-GESTION-REFONTE.md`](UX-GESTION-REFONTE.md) (design system Phase 5),
> qui reste la référence des composants ; ce document ne traite que **les icônes**
> et **la dérivation de la charte**.

---

## 1. Le problème — pourquoi on ne garde pas les emojis

> 📌 *Rédigé avant la phase, conservé au présent de l'époque : c'est le constat de
> départ. **Ce n'est plus l'état du code** — les emojis de chrome ont tous été
> remplacés (§5, §10).*

Les 60+ icônes de `/gestion` étaient des **emojis Unicode** (🏠 🧾 📦 💰 …)
rendus par la **police emoji du système d'exploitation**.

Conséquence directe : le tableau de bord n'a pas le même visage selon la machine du
gérant. Segoe UI Emoji (Windows) rend des pictogrammes très cartoon ; Apple Color
Emoji (macOS/iOS) rend du 3D brillant ; Noto Color Emoji (Android/Linux) est
différent encore. **Aucun contrôle** sur le trait, la couleur, le poids optique,
l'alignement vertical.

Trois symptômes déjà présents dans le code, qui sont tous des conséquences de ce
choix :

| Symptôme | Emplacement | Explication |
|---|---|---|
| `filter:grayscale(0)` sur l'icône active | `dashboard.css:124` | Correctif **orphelin** : rien ne pose jamais `grayscale(1)`. Vestige d'une tentative de dompter la couleur des emojis. |
| Pastilles KPI teintées + emoji multicolore dedans | `dashboard.css:1347`, `:1447-1453` | 7 tonalités de fond (`t-blue`, `t-teal`…) alors que l'emoji porte **sa propre couleur**, qui ne s'accorde avec aucune. |
| Emojis et caractères nus mélangés | `class-aod-cd-section-home.php:1348-1351` | `✖ ↩ ⚠ 🛣` sont des caractères Unicode **monochromes fins**, alignés à côté d'emojis colorés. L'incohérence est maximale sur l'onglet Livraison. |

**Décision : remplacer par du SVG monochrome embarqué.**

---

## 2. Les trois décisions actées

| Décision | Valeur | Prise le |
|---|---|---|
| Jeu d'icônes | **Phosphor Icons** | 2026-07-19 |
| Fond de la sidebar | **noir-vert dérivé de l'accent** (§4) | ✅ 2026-07-20 |
| Périmètre | **`/gestion` uniquement** — le public reste inchangé | 2026-07-19 |

### Pourquoi Phosphor

- **6 graisses** (`thin`, `light`, `regular`, `bold`, `fill`, `duotone`) sur la
  même géométrie. C'est le levier décisif : l'état actif de la navigation peut se
  jouer sur un **changement de graisse** (`regular` → `fill`) plutôt que sur une
  couleur. Un signal qui tient **quel que soit l'accent choisi par le client** —
  contrairement à la pastille de fond actuelle, dont le rendu dépend entièrement
  de la couleur retenue.
- Licence **MIT**.
- Logo **WhatsApp natif** (`WhatsappLogo`) — Lucide a retiré toutes les icônes de
  marque, ce qui aurait obligé à sourcer celle-là ailleurs pour la section WhatsApp.
- Géométrie régulière, ni enfantine ni froide.

### Contrainte d'approvisionnement

`TEMPLATE.md` §3 rappelle qu'il n'y a **pas d'internet sortant fiable depuis les
conteneurs**. Vérifié le 2026-07-19 : **l'hôte, lui, a du réseau**
(`registry.npmjs.org` → HTTP 200, `node`/`npm` présents).

→ Les SVG sont récupérés **une fois, sur l'hôte** (`@phosphor-icons/core`), puis
**versionnés dans le plugin**. Aucune dépendance à l'exécution, aucun CDN, aucun
appel réseau au rendu. Un clone neuf sans réseau fonctionne.

> 🚨 **La règle ci-dessus n'a longtemps été appliquée qu'aux icônes** (relevé le
> 2026-07-21). La coque de `/gestion` chargeait **Barlow depuis
> `fonts.googleapis.com`** — même page, même contrainte, conclusion inverse. La
> contrainte d'approvisionnement ne porte pas sur « les SVG » : elle porte sur
> **toute ressource nécessaire au rendu**. Polices comprises.
>
> Ce qui la rend traître ici, c'est le **silence de l'échec**. Une icône absente
> laisse un trou ; `AOD_CD_Icon` va jusqu'à déclencher un `_doing_it_wrong()` pour
> qu'un nom inconnu crie. Une police absente ne laisse rien : le navigateur sert la
> police système, l'écran reste lisible et crédible — il n'est simplement plus le
> produit. C'est le reproche exact fait aux emojis (« pas le même visage sur
> Windows, macOS et Android »), reconduit par la police de repli.
>
> → Corrigé par la **Phase 20** (étape 20.1) : `woff2` embarqués dans
> `assets/fonts/` sur le modèle de `assets/icons/`, plus un **cliquet statique**
> refusant tout hôte externe dans `plugins-dev/` — parce que la leçon de D1 et D6
> est qu'une règle sans porte finit par ne plus être tenue. Cf. `ARCHITECTURE.md`
> §7 dette 18.

---

## 3. Le contrat technique — `AOD_CD_Icon`

### 3.1 Pourquoi un catalogue fermé et pas un `<span>` libre

La dette n°14 de [`ARCHITECTURE.md`](ARCHITECTURE.md) documente le piège exact :
**`wp_kses_post()` supprime les `<svg>`**. Les points d'injection actuels utilisent
`esc_html()` — parfait pour un emoji, **destructeur** pour un SVG.

On ne peut donc pas simplement « échapper autrement » : il faut que le HTML ne
vienne **jamais** de la base ni d'une saisie. D'où un **catalogue fermé en PHP**,
sur le modèle déjà éprouvé de `AOD_COD_Landing::schema()` :

```
plugins-dev/aod-client-dashboard/includes/class-aod-cd-icon.php
plugins-dev/aod-client-dashboard/assets/icons/<nom>.svg     (fichiers Phosphor)
```

**Règle :** `AOD_CD_Icon::svg( $nom )` ne rend que des noms présents dans le
catalogue. Un nom inconnu ne rend **rien** et déclenche un `_doing_it_wrong()` en
`WP_DEBUG` — jamais un HTML de repli silencieux. Aucune surface XSS : rien de ce
qui est rendu ne provient de l'utilisateur.

### 3.2 Le rendu

```
<svg class="aod-cd-i" width="20" height="20" viewBox="0 0 256 256"
     fill="currentColor" aria-hidden="true" focusable="false"> … </svg>
```

Points non négociables :

- **`fill="currentColor"`** — l'icône prend la couleur de son contexte. C'est ce
  qui permet à l'accent du Personnalisateur de piloter les icônes sans qu'aucune
  couleur ne soit écrite dans le catalogue.
- **`aria-hidden="true"` + `focusable="false"`** — ces icônes sont **décoratives** :
  chaque lien de nav et chaque KPI porte déjà son libellé texte. Une icône
  annoncée en plus serait du bruit au lecteur d'écran. (Le `focusable="false"` est
  nécessaire : sans lui, IE/Edge legacy mettaient les `<svg>` dans l'ordre de
  tabulation. Peu coûteux, on le garde.)
- **Pas de `<title>`, pas de `<desc>`** — cf. ci-dessus.
- **`viewBox` conservé, pas de `width`/`height` en dur dans le fichier** : la
  taille est pilotée par CSS.

### 3.3 Graisses

| Contexte | Graisse Phosphor | Raison |
|---|---|---|
| Nav — item inactif | `regular` | Poids de lecture normal |
| Nav — item **actif** | `fill` | Le signal d'état ne dépend plus de la couleur |
| KPI | `regular` | Uniformité |
| États vides | `regular` | ⚠️ La spec prévoyait `light`. Abandonné : `.aod-cd-empty__ic` porte déjà `opacity:.5`, et embarquer une 3ᵉ graisse pour un effet que l'opacité produit déjà ne se justifiait pas. |
| Boutons d'action | `regular` | — |

**Deux graisses embarquées, pas trois** : `regular` (toutes les icônes) et `fill`
(les 14 sections, pour l'état actif de la nav).

### 3.4 RTL — les icônes directionnelles

Le CSS a déjà ce pattern à 2 endroits (`dashboard.css:1283`, `:1337`) :
`transform: scaleX(-1)`.

**Ne jamais retourner toute la famille.** Le catalogue marque les icônes
directionnelles par un drapeau ; seules celles-là sont miroitées en RTL.

Icônes concernées, **et la liste est volontairement courte** — deux entrées :
`shipping` (le camion roule vers la droite) et `returned` (flèche de retour).

🚨 **`trend-up` ne doit JAMAIS y figurer.** C'est une courbe qui monte de gauche à
droite ; miroitée, elle **descend**, et l'indicateur « Marge estimée » dirait
l'inverse de sa valeur — en arabe seulement, donc invisible depuis un poste
français. `inbox` (flèche verticale), `check`, `cancel`, `warning`, `blocked`,
`map-pin`, `package` et `tag` sont symétriques ou verticaux : rien à faire. Le test
porte une **liste noire explicite** qui refuse l'ajout des cas inversables.

---

## 4. La charte teintée — sidebar dérivée de l'accent

### 4.1 Le problème mesuré

L'accent de `/gestion` est déjà pilotable (`aod_cd_accent`, défaut `#FFE21E`).
Passer à un vert de marque semblait donc gratuit. **C'est faux**, et la mesure le
prouve.

`AOD_CD_Customize::readable_on()` (`class-aod-cd-customize.php:174`) garantit que
l'accent reste lisible **en texte sur la sidebar sombre**. Pour y arriver, il
**éclaircit la couleur** jusqu'à atteindre 4,6:1. Mesuré le 2026-07-19 :

| Accent candidat | Brut sur `#181818` | Ce que `readable_on()` en fait |
|---|---|---|
| `#FFE21E` (jaune actuel) | 13.65:1 ✅ | **inchangé** |
| `#006633` vert foncé | **2.49:1** ❌ | → `#4d9470` *(vert sauge délavé)* |
| `#007A3D` vert moyen | **3.26:1** ❌ | → `#339564` |
| `#009640` vert vif | 4.60:1 ⚠️ | → `#0d9b4a` |
| `#00A651` vert clair | 5.56:1 ✅ | **inchangé** |

**Le mécanisme n'a jamais été éprouvé** parce que le jaune par défaut passe très
au-dessus du seuil. Avec un vert de club foncé, il se déclencherait et **diluerait
la couleur de marque** à l'endroit précis où elle est la plus visible. Personne ne
serait prévenu : le garde-fou fait exactement son travail, en silence.

### 4.2 La solution — teinter le fond plutôt que délaver l'accent

Plutôt que de contraindre le client à choisir un accent clair, on **teinte la
surface sombre** avec sa propre couleur. L'accent redevient lisible dessus sans
être modifié, et le tableau de bord prend l'identité de la marque **dans sa masse**
plutôt que par un élément qui crie.

```
--cd-side  =  accent  mélangé à 85 % vers  #181818
```

C'est-à-dire : on garde la base neutre existante et on la **teinte légèrement**,
au lieu de partir du noir pur (qui donnait un vert sursaturé quasi noir, `#001108`,
où la teinte ne se lit pas).

### 4.3 La preuve — balayage du cube RGB

Comme pour `test-contraste.php`, la propriété doit tenir pour **toute** couleur que
le client peut choisir, pas pour un échantillon. Balayage de 4 096 accents
(pas de 17 sur chaque canal), sidebar dérivée pour chacun :

| Mélange | Sidebar (vert `#00A651`) | Pire texte `#cfcfcf` | Pire blanc |
|---|---|---|---|
| 0.80 | `#133423` | 6.06:1 ✅ | 9.44:1 ✅ |
| **0.85** | **`#142d21`** | **7.19:1** ✅ | **11.20:1** ✅ |
| 0.88 | `#15291f` | 7.99:1 ✅ | 12.45:1 ✅ |
| 0.92 | `#16231d` | 9.21:1 ✅ | 14.35:1 ✅ |

**Valeur retenue : `0.85`.** Le meilleur compromis entre teinte perceptible et
marge de contraste.

Pour le vert MOB, cela produit `#142d21` : exactement le noir-vert visé, **sans
qu'il soit écrit nulle part**.

### 4.3bis Le plafond de luminance — corrigé à l'implémentation

> ⚠️ **Ce chapitre annonçait une formule à taux fixe. Elle était insuffisante, et
> seule la capture d'écran l'a montré : les tests étaient verts.**

Un mélange à taux fixe est uniforme dans sa **formule**, pas dans son **résultat** :
il transmet la *clarté* de l'accent autant que sa teinte. Mesuré sur la luminance
de la sidebar rapportée à celle du neutre :

| Accent | Sidebar | Éclaircissement |
|---|---|---|
| `#111827` presque noir | `#17181a` | ×1,0 |
| `#b42318` rouge | `#2f1a18` | ×1,5 |
| `#1878f0` bleu | `#182638` | ×2,1 |
| `#00A651` vert | `#142d21` | ×2,4 |
| **`#FFE21E` jaune (défaut du template)** | **`#3b3619`** | **×4,0** ❌ |

Le jaune par défaut virait au **kaki olive**, franchement plus clair que le noir
voulu — un changement d'aspect imposé à tous les clones qui n'ont pas touché à
leur accent. Le contraste tenait, les 4 096 assertions passaient : rien ne le
signalait.

→ D'où `SIDE_MAX_LUM = 0.022` : après le mélange, la sidebar est **rassombrie par
paliers** jusqu'à passer sous le plafond (même forme monotone que `readable_on()`).
La règle devient uniforme dans son **résultat** — *« la sidebar est toujours à peu
près aussi sombre, teintée de VOTRE couleur »*.

Le plafond ne se déclenche que sur les accents très clairs : le jaune passe à
`#2b2812` (un noir chaud), **tous les autres ressortent intacts**, vert de
référence compris. Effet de bord : le pire contraste du texte de nav remonte de
**7,19:1 à 9,31:1**.

### 4.4 Pourquoi c'est la seule forme acceptable pour un template

`CLAUDE.md` règle n°1 : *« Rien de spécifique à un seul client en dur (nom,
couleurs, clés API) — tout se configure depuis l'interface. »*

Écrire `--cd-side:#142d21` dans `dashboard.css` **violerait cette règle** : chaque
futur clone hériterait des couleurs d'un club de football de Béjaïa. De même,
changer `DEFAULT_ACCENT` (`class-aod-cd-customize.php:21`) est **interdit**.

La dérivation résout le conflit : **un seul réglage** (l'accent), et chaque clone
obtient une sidebar teintée de **sa** couleur. Le vert MOB est une **valeur saisie
dans le Personnalisateur de ce clone**, pas une constante du template.

### 4.5 Le couplage que ça crée — à ne pas rater

`readable_on()` est aujourd'hui appelé contre un `#181818` **écrit en dur**
(cf. commentaire `class-aod-cd-customize.php:97`). Si la sidebar devient teintée,
`--cd-accent-on-dark` est calibré contre un fond **qui n'existe plus**.

→ `readable_on( $rgb, $bg )` doit recevoir la **sidebar dérivée**, pas `#181818`.
C'est le seul point de couplage, mais il est silencieux si on l'oublie : rien ne
casse visiblement, le contraste se dégrade juste un peu. D'où le test §7.1.

> ⚠️ **Correction — le couplage porte sur DEUX surfaces, pas une.**
> Ce chapitre disait « la sidebar dérivée ». C'est incomplet :
> `--cd-accent-on-dark` est **une seule variable** servant **trois** points, sur
> **deux** fonds différents (`dashboard.css:151, 242, 1000`) —
> `.aod-cd-view:hover` est sur la sidebar **dérivée**, tandis que la pastille de
> langue active et la pagination active restent sur le **neutre**.
>
> `css_vars()` calibre donc sur la **plus claire des deux** : passer là garantit de
> passer sur l'autre. Et non, ce n'est pas toujours la sidebar — mesuré sur le
> cube, **72 accents sur 4 096** (1,8 %, tous très sombres) donnent une sidebar
> dérivée *plus sombre* que le neutre. Écrire « la sidebar » en dur aurait
> sous-calibré ces cas-là, sans que rien ne le signale. Un test nomme ce compte
> pour que la raison du choix survive au code.

---

## 5. Le mapping des icônes

### 5.1 Zone 1 — Sidebar (14 icônes)

Source : `class-aod-cd-dashboard.php`, tableau `sections()` + le lien `storefront`.
*(Repérer par nom de symbole, pas par numéro de ligne : les ancres chiffrées se périment à chaque édition du fichier.)*
Point d'injection unique : `:482`.

| Actuel | Section | Phosphor | Note |
|---|---|---|---|
| 🏠 | Accueil | `House` | |
| 🧾 | Commandes | `Receipt` | |
| 🛒 | Commandes abandonnées | `ShoppingCartSimple` | |
| 📦 | Produits | `Package` | |
| 📊 | Stock | **`Stack`** | ⚠️ changement de sens — voir §5.4 |
| 🏷️ | Catégories | `Tag` | |
| 🚚 | Livraison | `Truck` | directionnelle (RTL) |
| 📍 | Suivi commande | `MapPin` | |
| 🎯 | Pixels & Tracking | **`Crosshair`** | ⚠️ changement de sens — voir §5.4 |
| 💬 | WhatsApp | `WhatsappLogo` | icône de marque |
| 👥 | Équipe | `Users` | |
| 👤 | Mon compte | `UserCircle` | |
| 🌐 | Voir la boutique | `Storefront` | |
| 🚀 | Page de vente *(section masquée)* | `RocketLaunch` | Absente du menu mais **routable** — elle a donc besoin d'une icône comme les autres. Ajoutée en 15.3. |

### 5.2 Zone 2 — Statistiques / KPI (24 cartes, 18 icônes distinctes)

> ⚠️ **Ce chapitre annonçait 18 cartes : il y en a 24.** Trois cartes manquaient à
> l'inventaire initial — « En attente (en cours) » (⏳), et les deux « Délai moyen »
> (⏱, un par onglet). Corrigé en 15.4, d'où trois entrées de catalogue non prévues :
> `pending` (`Hourglass`), `delay` (`Timer`) et `rupture` (`WarningOctagon`).
>
> `rupture` est **volontairement distincte de `cancel`** : les KPI de Stock affichent
> « Ruptures » à côté d'indicateurs de commandes annulées, et le même glyphe pour les
> deux les rendrait confondables. De même, `cart` existe en doublon de glyphe avec
> `abandoned` mais sous un nom propre : « Panier moyen » n'a rien à voir avec les
> commandes abandonnées, et emprunter le nom de la section aurait fait mentir le code.

Rendues par `AOD_CD_Dashboard::kpis()` (`:990`), champ `$c[2]`.

**Accueil → onglet Ventes** — `class-aod-cd-section-home.php:925-932`

| Actuel | KPI | Phosphor | Tonalité (inchangée) |
|---|---|---|---|
| 💰 | Chiffre d'affaires | `Money` | `blue` |
| 🧾 | Commandes encaissées | `Receipt` | `indigo` |
| 🛒 | Panier moyen | `ShoppingCartSimple` | `teal` |
| 📈 | Marge estimée | `TrendUp` | `green` |
| 💹 | Taux de marge | `Percent` | `green` |
| ✖ | Annulées | `XCircle` | `red` |
| 📦 | Produits en ligne | `Package` | `slate` |

**Accueil → onglet Livraison** — `:1346-1354`

| Actuel | KPI | Phosphor | Tonalité |
|---|---|---|---|
| 🚚 | Colis expédiés | `Truck` | `blue` · directionnelle |
| ✅ | Livrés | `CheckCircle` | `green` |
| 🛣 | En transit | `PathArrow` *(à vérifier)* | `indigo` |
| ↩ | Retournés | `ArrowUUpLeft` | `red` · directionnelle |
| ⚠ | Échec de livraison | `Warning` | `amber` |
| ✖ | Colis annulés | `XCircle` | `slate` |
| 🚧 | Bloqués | `Prohibit` | `amber` |

**Accueil → onglet Confirmation** — `:1629-1634`

| Actuel | KPI | Phosphor | Tonalité |
|---|---|---|---|
| 📥 | Commandes reçues | `TrayArrowDown` | `blue` · directionnelle |
| ✅ | Confirmées | `CheckCircle` | `green` |
| ❓ | Confirmées non attribuées | `Question` | `slate` |

**Stock** — `class-aod-cd-section-stock.php:500-503`

| Actuel | KPI | Phosphor | Tonalité |
|---|---|---|---|
| 🛑 | Ruptures | `XCircle` | `red` / `green` |
| ⚠️ | Stock bas | `Warning` | `amber` / `green` |
| 💰 | Valeur du stock | `Money` | `teal` |
| 📦 | Produits suivis | `Package` | `slate` |

### 5.3 Zone 3 — Collatéral

> ⚠️ **Ce chapitre annonçait « ~25 icônes » : le compte réel était d'environ 60
> occurrences sur 6 fichiers.** D'où un découpage en deux étapes.

**15.7a — les composants partagés** (un point de rendu, beaucoup d'écrans) :
`empty_state()` (12 appelants), tuiles d'alerte de l'Accueil (4), cartes de régie
(8), et le jumeau JS `emptyState()`.

**15.7b — le diffus** (~45 remplacements) : accordéons de la fiche produit et de
Livraison, panneaux WhatsApp / Mon compte, boutons d'action (corbeille, actualiser,
modifier, enregistrer, note), détail de commande, éditeur de page de vente,
alertes de Stock, et les glyphes générés en JS.

#### Les régies avaient des substituts, pas des icônes

`f` (Meta), `♪` (TikTok), `👻` (Snapchat), `G` (Google) et `$` (Conversions)
n'étaient pas des choix graphiques : c'était ce qu'on avait sous la main. Phosphor
fournit les quatre vrais logos sous licence MIT. `$` devient
`currency-circle-dollar` — un symbole dollar dans une boutique en dinars n'avait
rien à faire là.

#### Ce qui reste, volontairement

Trois emojis subsistent dans les sources, **tous à l'intérieur de chaînes
traduisibles** — c'est-à-dire du **contenu**, pas du chrome :

| Chaîne | Pourquoi on n'y touche pas |
|---|---|
| `__( 'Bonjour %s 👋' )` | Du **ton** dans un titre, pas une icône. Un vecteur y serait incongru. |
| `__( '✅ Test : la notification WhatsApp…' )` | Le **corps d'un message envoyé** au gérant sur WhatsApp. Un emoji y est à sa place ; un SVG n'y survivrait pas. |
| `__( '…Cliquez sur ✕ pour en retirer une.' )` | De la **prose** qui nomme un glyphe. Le bouton rend maintenant l'icône `close`, dont la forme est bien un ✕ : le texte reste exact. |

C'est aussi la règle que fait respecter le **cliquet** (§7.2) : un emoji est admis
dans un commentaire ou une chaîne traduisible, **nulle part ailleurs**.

### 5.4 Deux corrections de sens, indépendantes du jeu retenu

| Section | Avant | Après | Pourquoi |
|---|---|---|---|
| Stock | 📊 graphique | `Stack` | Un graphique évoque des **statistiques**. La section Stock compte des unités empilées — et il existe déjà une section Stats. |
| Pixels & Tracking | 🎯 cible pleine | `Crosshair` | La cible pleine lit « jeu de fléchettes ». Le réticule lit « ciblage publicitaire ». |

---

## 6. Ce qui NE change PAS — et pourquoi

### 6.1 Les tonalités sémantiques des KPI restent

Demande initiale : *« ces icônes aussi doivent avoir les mêmes couleurs »* — c'est-à-dire
tout peindre à la couleur de marque.

**Refusé sur les KPI**, et ce n'est pas un arbitrage esthétique :

`t-red` signale les **Annulées**, `t-amber` les **Échecs de livraison**, `t-green`
les **Livrés**. Peindre ces 18 pastilles en vert de club ferait lire « Retournés »
et « Échec de livraison » comme de bonnes nouvelles. **C'est une perte
d'information, pas un choix de style.** Les 7 tonalités (`dashboard.css:1447-1453`)
sont conservées telles quelles.

### 6.2 Les icônes de nav ne sont pas colorées non plus

| Contexte | Rejeté | Retenu | Pourquoi |
|---|---|---|---|
| Nav inactive | icône verte sur fond sombre | `currentColor` (gris du texte) | Un vert à 20px sur fond sombre est peu lisible, et met tous les items au même niveau sonore. |
| Nav **active** | icône verte sur pastille verte | `currentColor` (encre de l'accent) + graisse `fill` | Vert sur vert = **invisible**. |

**Le principe :** les icônes sont monochromes et **héritent de leur contexte**. La
couleur de marque apparaît à des endroits choisis — le fond de la sidebar, la
pastille active, le logo — pas peinte sur tout.

Un maillot du MOB n'est pas vert intégralement : c'est du vert et du noir dans des
proportions choisies, et c'est ce **rapport** qui fait l'identité. Tout peindre en
vert ne rendrait pas l'interface plus MOB — ça la rendrait moins dessinée.

### 6.3 La partie publique reste intacte

Décision de l'owner. `aod-cod-pages.css`, `aod-landing.css`, `aod-cod-form.css` et
tous les emojis de la couche acheteur (héro, réassurance, blocs de landing) ne sont
pas touchés.

⚠️ **Conséquence à assumer, écrite ici pour qu'elle ne surprenne personne plus
tard :** après ce chantier, `/gestion` portera la charte du client et **la boutique
que voient les acheteurs n'aura pas bougé d'un pixel**. Le formulaire COD restera
sur son vert Shopify `#008060` écrit en dur (`aod-cod-form.css:8`), qui n'aura
aucun rapport avec l'accent choisi.

Ce n'est pas un oubli, c'est un périmètre. Le chantier « accent public » reste
ouvert et non planifié — cf. [`ROADMAP-AMELIORATIONS.md`](ROADMAP-AMELIORATIONS.md).

### 6.4 L'exception assumée : les cartes de régie portent leur couleur de marque (Phase 41)

§6.2 réserve explicitement la couleur de marque à « des **endroits choisis** ». Une
carte de régie de `Pixels & Tracking` en est un — et c'en est même l'exemple le plus
net : là, **la couleur EST l'information**. Le sens de la carte « Meta — Facebook &
Instagram », c'est *Meta* ; celui de la carte Snapchat, *Snapchat*. Les peindre à leur
couleur de marque (Meta bleu, TikTok noir, Snapchat jaune, Google rouge) ne contredit
pas §6.1/6.2 — ça applique leur principe là où il vaut.

La distinction avec ce qui NE change pas :

| Surface | Couleur | Pourquoi |
|---|---|---|
| KPI (§6.1) | tonalité **sémantique** (rouge = Annulées) | La couleur porte un JUGEMENT ; une marque l'effacerait. |
| Nav (§6.2) | monochrome (`currentColor`) | Vert sur vert = invisible ; tous les items au même niveau. |
| **Carte de régie (§6.4)** | **couleur de marque** | La couleur EST l'identité de la plateforme — c'est ce que l'œil cherche. |

Trois garde-fous, écrits pour qu'on ne les rate pas :

- **Ce n'est pas l'accent client.** Les couleurs de régies tierces sont **universelles**
  (Meta est toujours bleu), identiques sur tous les clones, sans aucun lien avec le
  Personnalisateur (`#00A651` ici). En dur = légitime, comme les tons `t-*` — un catalogue
  fermé de marques, pas une valeur de client (Règle d'or n°1 sauve).
- **AA par construction.** L'encre posée sur une couleur de marque vient de `best_ink()`
  (§4) — la même fonction qui garantit ≥ 4,58:1 sur tout accent : noir sur le jaune
  Snapchat, blanc sur le noir TikTok. Prouvé par extension de `test-contraste.php`. Les
  champs du formulaire restent sur **fond blanc**, jamais sur la marque.
- **Marque ≠ statut.** Le vert « Actif » (`is-on`) reste sémantique : marque et on/off
  **coexistent** (même arbitrage que §6.1), la marque sur l'icône + un liseré, le statut
  sur la bordure/pastille.

Cadré en Phase 41 (cf. [`ROADMAP-AMELIORATIONS.md`](ROADMAP-AMELIORATIONS.md) A.18).

---

## 7. Tests à ajouter

Sans ces deux tests, la §4 est une intention, pas une garantie.

### 7.1 `tests/unit/test-charte-sidebar.php` — **livré** (20 assertions)

Sur le modèle de `test-contraste.php` — **prouver, pas échantillonner**. Balayage
de 4 096 accents (pas de 17), sidebar dérivée **en appelant `side()`**, jamais en
recopiant sa formule (cf. l'encadré ci-dessous) :

1. `mix()` généralisé accepte encore une cible **scalaire** — sa forme historique
   pilote `--cd-accent-strong`, `--cd-accent-soft` et `readable_on()`.
2. La sidebar est réellement **teintée** (écart chromatique ≥ 8 sur un accent
   saturé) — et reste **parfaitement neutre** sur un accent gris.
3. Le **plafond** tient : aucun accent n'éclaircit la sidebar au-delà de 2,6× le
   neutre, le jaune par défaut ne revient pas au kaki, et le vert de référence
   `#00A651` ressort **intact** à `#142d21`.
4. `ratio( sidebar, #cfcfcf ) >= 4.5` — le texte de nav tient. Mesuré : pire cas
   **9,31:1** (accent `#11aaaa`).
5. `ratio( sidebar, blanc ) >= 3` — l'anneau de focus reste visible. Pire **14,51:1**.
6. Le couplage §4.5, **bout en bout à travers `css_vars()`** : `--cd-accent-on-dark`
   tient AA sur la sidebar dérivée **et** sur le neutre, pour cinq accents dont
   chacun a une raison d'être là (le défaut, le vert de club, le bleu pire-cas de
   `best_ink`, et les deux extrêmes noir/blanc).
7. Le témoin des **72 accents sur 4 096** dont la sidebar est plus sombre que le
   neutre — la raison d'être du `max()` de `css_vars()`.
8. L'accent du site est **restauré** après le balayage.

> ⚠️ **Le piège évité de justesse — troisième récidive.** La première version du
> helper recopiait le mélange au lieu d'appeler `side()`. Elle aurait balayé
> 4 096 accents **sans jamais voir le plafond de luminance**, en se déclarant
> verte : le test aurait mesuré une formule qui n'est pas le code livré. C'est
> pour cela que `side()` accepte un accent en paramètre — le test passe par le
> code de production, pas à côté. Même leçon qu'en §7.2 : *un test de couverture
> qui se satisfait après avoir regardé moins que tout ne prouve rien.*

### 7.2 `tests/unit/test-icones.php` — **livré** (21 assertions)

1. Tout nom du catalogue résout vers un fichier SVG existant, dans les deux sens :
   catalogue → fichier **et** fichier → catalogue (aucun SVG orphelin, sinon le
   dossier grossit en silence à chaque étape).
2. Toute section **routable** — masquées comprises — a une icône. Le test endosse
   un gérant, sans quoi `hidden_sections()` (gardée par `manage_woocommerce`)
   sortirait de la liste et la « Page de vente » serait déclarée conforme sans
   avoir été regardée.
3. Aucun SVG ne code de couleur en dur — sinon l'accent ne pilote plus rien.
4. Le drapeau « directionnelle » ne porte que sur des noms existants, **et jamais
   sur une icône à sens inversable** (liste noire explicite : `trend-up`, `check`,
   `cancel`, `warning`, `blocked`, `percent`).
5. **Balayage des sources** : tout nom appelé quelque part existe au catalogue.
   Les KPI et les états vides ne passent pas par `sections()` — ils sont construits
   à la volée derrière des requêtes SQL — donc seul un balayage du code les couvre.
6. **Le cliquet** : aucun emoji hors commentaire et hors chaîne traduisible.
   C'est lui qui empêche la phase de se défaire doucement — un emoji de plus dans
   une interface qui en contenait soixante ne choque personne.

> ⚠️ **Deux fois pendant la phase, un test de couverture s'est déclaré satisfait
> après avoir regardé moins que tout.** D'abord le motif des KPI, aveugle aux
> tonalités écrites en ternaire (17 noms sur 18) ; puis celui des états vides,
> arrêté par un `// phpcs:ignore` placé après la parenthèse ouvrante (19 sur 23).
> Les deux fois, c'est un **seuil chiffré** qui a donné l'alerte — pas l'assertion
> elle-même, qui était verte. D'où les témoins nommés (`rupture` pour le ternaire)
> ajoutés depuis : un seuil se contourne par accident, un témoin non.
>
> Le cliquet, lui, a été **forcé à rougir** avant d'être retenu (un emoji injecté
> dans une section : `class-aod-cd-section-team.php:19 (🎯)`), puis le fichier
> restauré. Cf. la dette 15 de `ARCHITECTURE.md` : un garde-fou qui n'a jamais
> échoué ne prouve rien.

### 7.3 Recette navigateur

`tools/recette/a11y-contraste.js` couvre déjà le contraste **calculé sur le rendu**
et tourne sur 5 accents hostiles (`run.sh:31`). Il couvrira la sidebar teintée
gratuitement.

⚠️ **Mais sa liste de pages était fausse.** `tools/recette/lib.js` contenait une
liste `SECTIONS` **périmée** : `'stats'`, slug supprimé en Phase 2 (renommé `home`),
et ni `home` ni `abandoned`.

Vérifié dans le routeur (`class-aod-cd-dashboard.php:327-329`) : un slug inconnu ne
lève pas d'erreur, `maybe_render()` retombe sur la **première section autorisée**.
Impact réel, plus nuancé que ce qu'on pouvait croire :

| | Effet |
|---|---|
| `abandoned` | **Jamais visitée** — vrai trou de couverture. |
| `home` | **Visitée quand même**, par le repli de `'stats'` (l'Accueil est la première section d'un gérant). Les 18 KPI étaient donc bien balayés — mais rapportés sous un nom de page **qui n'existe pas**. |

Autrement dit : un balayage qui se trompe de page ne le signale pas, il compte
quand même. Corrigé en **étape 15.0** (12 sections de menu ; `landing` exclue car
elle exige `?produit=<id>` et a sa propre porte).

---

## 8. Plan d'étapes

| # | Étape | Livrable | Dépend de |
|---|---|---|---|
| **0** | Corriger `SECTIONS` dans `tools/recette/lib.js` | La recette voit enfin l'Accueil | — |
| **1** | Récupérer Phosphor sur l'hôte, versionner les SVG utiles | `assets/icons/*.svg` (`regular` + `fill`) | réseau hôte |
| **2** | `AOD_CD_Icon` — catalogue fermé, rendu, drapeau RTL | `class-aod-cd-icon.php` + `test-icones.php` | 1 |
| **3** | Zone 1 — sidebar (14 icônes) | `sections()` + `:482` + CSS | 2 |
| **4** | Zone 2 — KPI (24 cartes) | `kpis()` + 4 fichiers de sections | 2 |
| **5** | Sidebar dérivée + `readable_on` recalibré | `class-aod-cd-customize.php` + `test-charte-sidebar.php` | ✅ 2026-07-20 |
| **6** | Nettoyage : `filter:grayscale(0)` orphelin, `transition:.16s` | `dashboard.css:124`, `:119` | 3 |
| **7** | Zone 3 — collatéral (~25 icônes) | états vides, accordéons, boutons | 2 |
| **8** | Saisir le vert MOB dans le Personnalisateur + `run.sh contraste` | — | 5 |

**Étapes 3 et 4 sont indépendantes de 5** : les icônes héritent de `currentColor`,
elles fonctionnent avec n'importe quelle charte. On peut livrer les icônes sans
attendre que la couleur soit arrêtée.

---

## 9. L'accent retenu — `#00A651` (étape 15.8, livrée)

> Le hex officiel du club n'ayant jamais été communiqué, l'owner a délégué le
> choix (« je te laisse trancher, je veux quelque chose comme vert harmonisé »,
> 2026-07-20). Ce chapitre documente **sur quoi** la décision s'appuie, pour
> qu'elle puisse être rejouée ou contestée sur des chiffres.

### 9.1 Le piège de « un vert » dans CE tableau de bord

`/gestion` a **déjà un vert**, et il porte un sens : `--cd-ok:#146c2e` — succès,
commande confirmée, livraison réussie. Prendre un vert de marque proche de
celui-là fait de la couleur de marque et de « ça a marché » **la même tache verte**.

Mesuré, contraste de chaque candidat contre `--cd-ok` :

| Candidat | Teinte | Contraste vs `--cd-ok` | Verdict |
|---|---|---|---|
| `#006633` | 150° | **1,09:1** | indiscernable ❌ |
| `#046A38` | 151° | **1,03:1** | indiscernable ❌ |
| `#00693E` | 155° | **1,04:1** | indiscernable ❌ |
| `#008040` | 150° | 1,29:1 | trop proche |
| `#0F9D58` | 151° | 1,86:1 | acceptable |
| **`#00A651`** | 149° | **2,05:1** | **retenu** ✅ |

Les verts **foncés** de club — le réflexe naturel — sont précisément les pires :
ils partagent la teinte *et* la clarté du vert sémantique.

### 9.2 Pourquoi `#00A651`

1. **Meilleure séparation du vert sémantique** du lot (2,05:1). Le système qui en
   sort est lisible : **marque = vert vif, statut = vert sourd**.
2. **`--cd-accent-on-dark` reste `#00a651`, pur.** C'est le seul candidat que
   `readable_on()` ne retouche dans **aucun** rôle — tous les verts foncés testés
   ressortent éclaircis (`#006633` → `#599c7a`). La couleur de marque est donc
   rendue telle quelle partout où elle apparaît.
3. **`best_ink()` bascule sur le noir** : les CTA portent une encre noire sur vert
   vif, la combinaison la plus lisible du lot.
4. Sidebar dérivée `#142d21` — le noir-vert visé depuis le début.

### 9.3 ⚠️ Où cette couleur vit — et où elle ne vit PAS

`#00A651` est une **valeur en base de CE clone** (`theme_mod aod_cd_accent`),
saisie dans le Personnalisateur. Elle n'apparaît **dans aucun fichier versionné**.

`DEFAULT_ACCENT` (`class-aod-cd-customize.php`) **reste `#FFE21E`** — la toucher
ferait hériter le vert à tous les futurs clones, ce qu'interdit la règle n°1. Un
clone neuf démarre toujours en jaune ; le gérant choisit sa couleur, et toute la
charte suit.

Vérifié après application : porte `contraste` à **0 occurrence sous AA**.

---

## 10. Revue technique au passage — **livrée** (étape 15.6)

Points relevés dans le CSS de la sidebar pendant l'analyse, non liés aux
icônes mais dans la zone touchée :

| Avant | Après | Pourquoi |
|---|---|---|
| `.aod-cd-navlink { transition:.16s }` (`dashboard.css:119`) | `transition: background-color 160ms ease, color 160ms ease` | `transition` sans propriété = `all` : anime aussi `padding`, `border-radius`, `transform`… déclenche layout + paint au lieu de rester sur le compositeur. |
| `.aod-cd-navlink.is-active .aod-cd-ico { filter:grayscale(0) }` (`:124`) | *supprimé* | Correctif orphelin — rien ne pose jamais `grayscale(1)`. Sans objet une fois les emojis remplacés. |
| Aucun état `:active` sur `.aod-cd-navlink` | `transform: scale(0.98)` sur `:active` | Un lien de nav cliqué doit accuser réception. 13 items, cliqués des dizaines de fois par jour. |

Le stagger d'ouverture du menu mobile (`:185-193`, `aodNavIn .26s` avec délais de
40 ms) est **déjà correct** : durée sous 300 ms, délais dans la fourchette 30-80 ms,
et neutralisé sous `prefers-reduced-motion` (`:197`). Rien à changer.

### Ce qui a été livré en plus du tableau

- **Quatre** `transition` implicites corrigés dans la zone sidebar, pas un seul :
  `.aod-cd-navlink`, `.aod-cd-view/.aod-cd-logout`, `.aod-cd-navtoggle span` et
  `.aod-cd-navlink::after`. Chaque liste de propriétés est **exactement** ce qui
  change d'état pour ce sélecteur — vérifié état par état, pas recopié.
- Un token **`--cd-ease-out`** (`cubic-bezier(.23,1,.32,1)`). Les easings natifs
  sont mous ; une courbe qui démarre franchement fait *paraître* l'interaction
  plus rapide à durée égale, parce que c'est le début du mouvement que l'œil
  regarde. Corollaire à ne jamais oublier : **`ease-in` est proscrit** ici, il
  retarde précisément cet instant.
- Le `:active` est **neutralisé sous `prefers-reduced-motion`** — mais
  l'acquittement ne disparaît pas : le changement de fond reste, et lui n'est pas
  du déplacement. Vérifié au navigateur dans les deux modes.

### ⚠️ Ce qui N'A PAS été traité — 22 sites restants → **Phase 16**

Le tableau ci-dessus annonçait **un** `transition` implicite. Le compte réel est
de **26** ; l'étape en a corrigé 4 (la zone sidebar, son périmètre).

> 📌 Le chiffre « 24 » a circulé un temps : il venait d'un `grep` **par ligne**,
> qui manque les règles dont la déclaration est répartie autrement. Un relevé
> **par règle** en donne 26. Le détail compte peu ; la leçon, si : compter à la
> ligne ce qui se définit à la règle donne un résultat plausible et faux.

Ils n'ont **pas** été traités par oubli mais par choix. Corriger un
`transition:.15s` demande de relever tous les déclencheurs du sélecteur — et ils
ne sont pas là où on les cherche : classe ajoutée en **JS** (`.aod-cd-toast` →
`.show`), état porté par un **ancêtre** (`.aod-cd-todo-tile:hover .…__go`),
propriétés appartenant à un **pseudo-élément** et non à l'élément
(`.aod-cd-btn.is-loading::after`). Regarder `:hover` seul conclut « transition
morte » sur le toast — et la retirer **tue son apparition**.

Aucun garde-fou ne verrait cette régression : les tests unitaires ne lisent pas le
CSS, les portes de recette mesurent le contraste et les noms accessibles, pas le
mouvement.

→ Planifié comme **Phase 16** (relever → convertir par lots → porte de recette qui
voit le mouvement) dans [`ROADMAP-AMELIORATIONS.md`](ROADMAP-AMELIORATIONS.md).
