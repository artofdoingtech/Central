# assets/icons — SVG Phosphor embarqués

Icônes de l'espace gérant `/gestion`. Rendues par `AOD_CD_Icon`
(`includes/class-aod-cd-icon.php`), jamais lues directement.

## Provenance

- **Jeu** : [Phosphor Icons](https://phosphoricons.com/) — paquet
  `@phosphor-icons/core`, version **2.1.1**.
- **Licence** : MIT (copie dans `LICENSE`).
- **Récupéré le** : 2026-07-19.

Les fichiers sont **copiés tels quels depuis l'amont**, sans retouche. C'est
volontaire : on peut à tout moment vérifier qu'ils n'ont pas dérivé, et une mise à
jour est un simple remplacement. Les attributs de présentation (`class`, `width`,
`height`, `aria-hidden`) sont **reconstruits au rendu** par `AOD_CD_Icon::svg()`,
qui ne conserve du fichier que ce qu'il y a entre `<svg>` et `</svg>`.

## Pourquoi versionnés et pas installés

Les conteneurs de ce template **n'ont pas d'internet sortant fiable**
(`TEMPLATE.md` §3). Un clone doit fonctionner le jour de son installation, sans
npm, sans CDN, sans build. D'où : récupération **une fois sur l'hôte**, puis les
fichiers vivent dans le dépôt.

> 🚨 **Cette règle vaut pour toute ressource de rendu, pas seulement les SVG.**
> Elle est restée lettre morte à trois cents lignes d'ici : la coque de `/gestion`
> chargeait Barlow depuis `fonts.googleapis.com`
> (`includes/class-aod-cd-dashboard.php:385-387`). Voir `assets/fonts/README.md`,
> qui applique le même geste à la typographie, et la **Phase 20** de la ROADMAP.
> Une police manquante ne laisse **aucune trace visible** — contrairement à une
> icône manquante, qui laisse un trou et déclenche un `_doing_it_wrong()`.

## Contenu

| Dossier | Fichiers | Usage |
|---|---|---|
| `regular/` | 57 | Toutes les icônes du catalogue |
| `fill/` | 14 | Uniquement les entrées de la sidebar — **état actif** de la navigation : les 12 sections du menu, plus `storefront` (le lien « voir la boutique ») et `rocket-launch` (la Page de vente, routable mais masquée) |

L'état actif de la nav se joue sur la **graisse** (`regular` → `fill`) et non sur
la couleur : un signal qui tient quel que soit l'accent choisi par le client.

## Ajouter une icône

1. Récupérer le paquet sur **l'hôte** (pas dans le conteneur) :
   `npm pack @phosphor-icons/core` puis extraire.
2. Copier `assets/regular/<nom>.svg` ici, dans `regular/`.
   Pour une section de sidebar, copier aussi `assets/fill/<nom>-fill.svg` vers
   `fill/<nom>.svg` (noter le retrait du suffixe `-fill`).
3. Déclarer le **nom logique** dans `AOD_CD_Icon::catalog()`.
4. Si l'icône porte un sens de lecture horizontal, l'ajouter à
   `AOD_CD_Icon::directional()` — et **seulement** dans ce cas. Miroiter une
   courbe comme `trend-up` inverserait son sens.
5. `bash tests/run.sh` — `test-icones.php` refuse un catalogue incohérent.

## Ce qu'il ne faut pas faire

- ❌ Écrire une couleur dans un fichier SVG. Ils portent `fill="currentColor"` :
  c'est ce qui permet à l'accent du Personnalisateur de les piloter. Un `fill`
  ou un `stroke` en dur casserait le lien — le test le refuse.
- ❌ Rendre un SVG hors de `AOD_CD_Icon`. `wp_kses_post()` supprime les `<svg>`
  (cf. dette 14 de `docs/ARCHITECTURE.md`) ; le catalogue fermé est ce qui rend
  l'échappement inutile, parce que rien ne vient d'une saisie.
