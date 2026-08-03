# assets/icons — SVG Phosphor embarqués (côté PUBLIC)

Icônes du **panier COD public**. Rendues par `AOD_COD_Icon`
(`includes/class-aod-cod-icon.php`), jamais lues directement.

## Provenance

- **Jeu** : [Phosphor Icons](https://phosphoricons.com/) — paquet
  `@phosphor-icons/core`, version **2.1.1**.
- **Licence** : MIT (copie dans `LICENSE`).
- **Copiés depuis** : `plugins-dev/aod-client-dashboard/assets/icons/`
  (récupérés sur l'amont le 2026-07-19), le 2026-07-23. `house`, `storefront` et
  `lock` ajoutés le 2026-07-23 (38.3), même provenance.

Les fichiers sont **copiés tels quels**, sans retouche. Les attributs de
présentation (`class`, `width`, `height`, `aria-hidden`) sont **reconstruits au
rendu** par `AOD_COD_Icon::svg()`, qui ne conserve du fichier que ce qu'il y a
entre `<svg>` et `</svg>`.

## 🚨 Pourquoi ces fichiers existent DEUX fois dans le dépôt

Ils sont identiques à ceux de `aod-client-dashboard`. C'est une duplication
assumée, et voici pourquoi elle n'est pas une négligence :

**La dépendance entre les deux plugins ne va que dans un sens.**
`aod-client-dashboard` a besoin de `aod-cod-form` ; jamais l'inverse. Le
formulaire COD, le panier et les 3 pages publiques doivent fonctionner quand
`/gestion` est désactivé — c'est le mode dégradé que la Phase 32.1 a fermé après
une **erreur fatale** en production. Appeler `AOD_CD_Icon` depuis le panier
rouvrirait ce défaut, à l'envers : un clic dans wp-admin et la boutique tombe.

**La duplication est gardée, donc bruyante.** `tests/unit/test-panier-peau.php`
(R6) compare ces fichiers à ceux du dashboard **octet à octet** et rougit s'ils
divergent. Même parti pris que les tokens `--ac-*` / `--sh-*` de `aod-cart.css` :
on ne peut pas toujours éviter de recopier, on peut toujours refuser que la copie
dérive en silence.

## Ajouter une icône

1. La copier depuis `aod-client-dashboard/assets/icons/<graisse>/<nom>.svg`
   (si elle n'y est pas, la récupérer d'abord sur l'amont **depuis l'hôte** — les
   conteneurs n'ont pas d'internet sortant fiable, cf. `TEMPLATE.md` §3).
2. L'ajouter au catalogue de `AOD_COD_Icon::catalog()`, sous un nom d'**usage**
   (`cart`, `close`) et non de dessin : le jour où l'icône change, le nom reste juste.
3. Si elle doit exister en graisse `fill`, l'ajouter aussi à `filled()`.
4. Relancer `bash tests/run.sh` : R6 vérifie la conformité de la copie.

## Catalogue actuel

| Nom d'usage | Fichier Phosphor | Graisses | Où |
|---|---|---|---|
| `cart` | `shopping-cart-simple` | `regular`, `fill` | Bouton flottant du panier (`fill`, 24 px) |
| `close` | `x` | `regular` | Fermeture du panneau (20 px), retrait d'une ligne (16 px, via `js_map()`) |
| `home` | `house` | `regular` | Mode de livraison « À domicile » du formulaire COD (20 px) |
| `desk` | `storefront` | `regular` | Mode de livraison « Stop-desk » du formulaire COD (20 px) |
| `lock` | `lock` | `regular` | Ligne de réassurance « paiement à la livraison » (14 px) |
