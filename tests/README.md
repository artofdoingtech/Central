# Tests unitaires du template

```bash
bash tests/run.sh
```

Quelques secondes, aucun téléchargement. À rejouer **après toute retouche
des plugins**, et sur chaque clone avant mise en ligne.

## Pourquoi pas PHPUnit

Le template n'a ni Composer ni internet sortant garanti (cf. TEMPLATE.md §3). Un
clone doit pouvoir lancer ses tests **le jour de son installation**, sans rien
installer. Le harnais tient donc dans `bootstrap.php` et s'exécute dans WordPress
via `wp eval-file` : les classes testées sont les vraies, avec le vrai
`get_locale()`, le vrai `sanitize_text_field()`. Ce ne sont pas des mocks.

Sortie ≠ 0 dès qu'un test échoue — utilisable en hook ou en CI.

## 🚨 Avant d'écrire un test de couverture : la règle du témoin

Un test qui balaie un ensemble — « aucun emoji ne subsiste », « toutes les
sections ont une icône », « tout accent passe AA » — a un mode d'échec que
l'assertion elle-même ne peut pas voir : **passer au vert en n'ayant regardé
qu'une partie de l'ensemble, voire rien**. Il ne casse pas, il ne prévient pas,
et il rend exactement le même signal qu'un test qui a tout vu.

Six occurrences réelles sur les Phases 15 et 16 — dont une où le garde-fou
inspectait **10 éléments sur 875** en se déclarant vert. Le détail et les cinq
règles qui en découlent : `docs/ROADMAP-AMELIORATIONS.md` §C.2.

En résumé, exigible sur tout nouveau test de ce dossier :

1. **Remonter le compte** de ce qui a réellement été inspecté, et l'affirmer.
2. **Un invariant plutôt qu'un seuil** — un seuil choisi large laisse passer les
   régressions modestes.
3. **Nommer des témoins** : au moins un cas précis, cité pour ce qu'il prouve.
4. **Passer par le code de production**, jamais par une copie de sa formule.
5. **Forcer au rouge** avant de faire confiance — et vérifier que l'injection a
   bien eu lieu, sinon on n'a éprouvé que son propre `sed`.

## Ce qui est couvert, et pourquoi c'est **ça**

On ne teste pas pour faire du chiffre : on couvre les endroits où une régression
**ne se verrait pas**. Un bouton mal placé se voit ; un statut de colis mal mappé ne
se voit pas — il fausse les stats du gérant pendant deux mois.

| Fichier | Ce qu'il protège |
|---|---|
| `unit/test-landing.php` | Les trois invariants de la **page de vente** (`docs/LANDING-PRODUIT.md` §2) : aucun HTML libre ne survit à la saisie du gérant, une image est un **ID d'attachement** (jamais une URL), un lien n'est jamais réinjecté brut (l'ID YouTube est extrait puis revalidé). Plus le **couple `{ fr, ar }`** et son repli dans les deux sens — le mécanisme qui rend le contenu marchand bilingue, là où `gettext` ne peut rien. |
| `unit/test-contraste.php` | La lisibilité de `/gestion` pour **n'importe quel accent** choisi par le client. On balaie **4 096** couleurs du cube RGB (compte *remonté par le test*, pas écrit ici : il l'a longtemps annoncé 4 913, le chiffre d'un pas qu'il ne fait pas) et on vérifie que le pire contraste texte/bouton reste **AA (≥ 4,5:1)**. La recette navigateur teste 5 accents ; ici on en teste des milliers — c'est une garantie mathématique, pas un échantillon. |
| `unit/test-transporteurs.php` | Le **mapping des statuts colis** (FR / AR / EN, avec et sans accents) et la règle d'ordre : « Livré au centre relais » est **livré**, pas « en transit ». Un statut inconnu rend `unknown` — **jamais** une supposition. C'est exactement le bug qui a faussé les stats de livraison pendant deux mois. Plus la normalisation des tarifs : une wilaya sans stop-desk garde `desk = 0`, on n'invente pas de prix. |
| `unit/test-tarifs-et-roles.php` | Le **prix de livraison** (`apply_margin`) — une erreur ici se paie en argent réel, sur chaque commande — et le **périmètre de sécurité** : aucune capacité de la liste `FORBIDDEN` ne peut revenir au gérant, la whitelist du confirmateur ne contient que des `aod_cap_*`. |
| `unit/test-charte-sidebar.php` | La sidebar est **dérivée** de l'accent du client (Phase 15) : elle reste toujours assez sombre (plafond de luminance), et tout ce qui est posé dessus reste lisible **pour tout accent**. Sans ce test, un accent de marque foncé se ferait délaver en silence. |
| `unit/test-charte-surfaces.php` | La charte de `/gestion` ne peut plus se défaire **en silence** (Phase 17) : aucune couleur de client en dur, aucune variable CSS **jamais déclarée**, aucun repli mort, aucune animation `@keyframes` fantôme. Trois contrôles statiques — les défauts visés n'affichent **aucune** erreur : le repli prend le relais et le contrat de contraste tombe sans bruit. |
| `unit/test-icones.php` | Le **catalogue fermé** d'icônes (Phase 15) : aucun fichier manquant, aucune section sans icône, aucune couleur en dur, et la liste noire du miroir RTL (miroiter une courbe la ferait **descendre**). Porte aussi le **cliquet anti-emoji** : plus aucun emoji de chrome ne peut revenir dans `/gestion`. |
| `unit/test-catalog-i18n.php` | Le socle **bilingue du catalogue** (Phase 7) : titre et description portent leur couple `{ fr, ar }`, avec repli **bidirectionnel** — une version manquante retombe sur l'autre plutôt que d'afficher du vide. |
| `unit/test-category-i18n.php` | Les **noms de catégories** en arabe (7.5), rendus par un seul filtre `get_term` — l'objet venant du cache, le filtre doit rendre un **clone** sous peine de graver l'arabe partout. |
| `unit/test-variants-i18n.php` | Les **variantes** bilingues (7.4) et le piège central de la phase : le nom **canonique** reste FR, car il sert de **clé** de commande. Traduire cette clé casserait la commande jusque dans le bordereau transporteur. |
| `unit/test-catalog-filters.php` | Les filtres d'affichage du catalogue : ce qui est swappé en AR, et surtout ce qui ne doit **jamais** l'être (les getters WooCommerce persistent leur résultat dans la commande). |
| `unit/test-home-categories.php` | Le tri des catégories « à la une » (9.2) et la **bascule sélection ⇄ repli** : aucune sélection → toutes les catégories non vides, comme avant la Phase 9. Le départage des ex æquo se fait sur le **slug** — le nom est traduit, l'ordre s'inverserait entre FR et AR. |
| `unit/test-category-home.php` | Le réglage « Afficher sur l'accueil » côté données (9.1) : le patron « absent = intact » — enregistrer une image ne doit ni décocher ni réordonner une catégorie. |
| `unit/test-desc-kses.php` | La **description riche** (Phase 8) : ce que la liste blanche HTML laisse passer, et ce qu'elle coupe. |
| `unit/test-desc-video.php` | La **vidéo** dans la description (8.3) : l'ID YouTube est re-dérivé et revalidé, jamais réinjecté brut ; la façade ne laisse pas de paragraphe orphelin. |
| `unit/test-pixels.php` | La **devise de reporting** des pixels (`to_reporting()`). Meta refuse le DZD : sans conversion, la **valeur** des achats n'est jamais mesurée et le gérant pilote ses pubs au ROAS **sur du vide**. Le helper ne doit rien changer tant que rien n'est configuré, et convertir proprement dès qu'une devise et un taux sont saisis. Une erreur ici fausse les chiffres remontés aux régies, en silence. |

## Ce qui n'est PAS ici

Le **parcours réel** (une commande qui part, le rendu, le RTL, le clavier) n'est pas
du ressort d'un test unitaire : il est couvert par la **recette navigateur**
(`tools/recette/`), et notamment `bash tools/recette/run.sh cod` qui passe une vraie
commande COD et va la relire en base.

## Ajouter un test

Un fichier `unit/test-*.php` est chargé automatiquement. Les assertions :

```php
AOD_Test::group( 'Ce que couvre ce bloc' );
AOD_Test::ok( $condition, 'Ce que le test prouve, en une phrase' );
AOD_Test::eq( $attendu, $obtenu, 'Ce que le test prouve' );

// Méthodes protected/private (on ne les rend pas publiques juste pour les tests) :
AOD_Test::call_static( 'AOD_CD_Customize', 'best_ink', array( $lum ) );
AOD_Test::call( $carrier, 'guess_status', array( 'Livré' ) );

// Basculer le site en arabe le temps d'une assertion :
AOD_Test::in_arabic( static fn() => AOD_COD_Landing::text( $champ ) );
```

Le libellé d'un test dit **ce qu'il prouve**, pas ce qu'il appelle : c'est lui qu'on
lira le jour où il passera au rouge.
