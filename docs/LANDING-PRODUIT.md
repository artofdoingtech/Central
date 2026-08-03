# LANDING-PRODUIT — spécification de la page de vente par produit

> **Statut au 2026-07-14 : Phase 6 livrée en entier (6.1 → 6.6)** (livrée en `aod-cod-form` **v1.28.0** /
> `aod-client-dashboard` **v0.27.0** ; le template est aujourd'hui en **v1.41.1** /
> **v0.46.0**) — le contrat de données (§4), le rendu (§3),
> l'**éditeur `/gestion`** (§6), le **catalogue de blocs au complet** (§5 : `hero`,
> `points`, `gallery`, `video`, `text`, `compare`, `reviews`, `faq`, `urgency`,
> `form`, `trust`) et la **barre « Commander » collante** existent dans le code.
> Une page de vente se construit de bout en bout, sans wp-admin, la **recette la
> couvre** (`tools/recette/landing-produit.js`), et l'éditeur sait **partir d'un
> modèle** (8 blocs vides, `AOD_COD_Landing::starter()`) ou **copier la landing d'un
> autre produit** (la copie arrive toujours éteinte). **La Phase 6 est close.**
> Le plan d'exécution vit dans [ROADMAP-AMELIORATIONS.md](ROADMAP-AMELIORATIONS.md)
> **Phase 6** — ce document dit *quoi* et *pourquoi*, la roadmap dit *dans quel ordre*.

---

## 1. Le besoin

Aujourd'hui, une fiche produit = le gabarit du thème (galerie + titre + prix +
description) avec le formulaire COD injecté dessous
(`woocommerce_single_product_summary`, prio 35). C'est une **fiche catalogue**.

Le e-commerce COD algérien vend en **page de vente** : une page longue qui
argumente (héro, bénéfices, preuve sociale, FAQ, urgence) et ramène sans cesse au
formulaire. Le template doit savoir produire ça — **sans plugin tiers, sans
wp-admin pour le gérant, sans casser une seule des règles d'or.**

---

## 2. Les trois décisions de fond (actées par l'owner le 2026-07-13)

### 2.1 La landing est la page produit — pas une 4ᵉ page

Elle s'active **par produit** (interrupteur dans `/gestion`) et remplace le rendu
de `/produit/<slug>`. **L'URL ne change pas.**

C'est ce qui fait qu'elle **ne viole pas la règle 3** (« côté public = exactement
3 pages ») : la règle porte sur les **pages WordPress** et sur la **navigation**.
Une fiche produit n'a jamais été l'une des 3 pages et n'a jamais figuré dans le
menu — la Boutique y mène par un clic. Habiller cette fiche autrement ne crée ni
page, ni entrée de nav.

Une page dédiée (`/offre/<slug>`) a été écartée : deux URLs pour un produit, deux
pages à indexer, deux liens à choisir dans la pub — pour zéro gain.

```
/                 → Accueil          ┐
/boutique         → Boutique          ├─ les 3 pages publiques, nav inchangée
/contact          → Contact          ┘
/produit/<slug>   → fiche produit  … ou LANDING si l'interrupteur est ON
                                       (dans les deux cas : formulaire COD dedans)
```

### 2.2 Blocs maison dans `/gestion` — pas Elementor

Le gérant empile des blocs d'un **catalogue fermé** (§5) depuis `/gestion`, comme
il édite déjà ses produits. Elementor a été écarté pour trois raisons, chacune
suffisante : il exige de rouvrir **wp-admin** au gérant (que `AOD_CD_Access` lui
interdit), il stocke le texte **en base sans notion de langue** (§2.3), et son
poids menace l'objectif « < 3 s sur mobile ».

Un catalogue fermé, ce n'est pas une limite subie : c'est ce qui garantit que
**toute** landing produite par **tout** clone reste responsive, RTL et rapide.

### 2.3 Chaque texte porte sa version FR **et** sa version AR

C'est le cœur de la spec, et le point où le template a aujourd'hui un trou.

**Le mécanisme de traduction actuel (`gettext` + cookie `aod_lang`) ne traduit que
le code.** C'est pour ça que le contenu des 3 pages vit dans des shortcodes et non
dans l'éditeur (TEMPLATE.md §5). Mais une landing, c'est **du contenu marchand**,
par définition tapé par le gérant : `gettext` ne peut rien pour elle.

État des lieux — **à jour au 2026-07-20** :

| Contenu marchand | En arabe |
|---|---|
| Titre / description du produit | ✅ **traduits** depuis la **7.2** — métas `_aod_title_ar` / `_aod_desc_ar` |
| `_aod_selling_points` (arguments de vente) | ✅ **traduits** depuis la **7.3** — `_aod_selling_points_ar` |
| Libellés/valeurs de variantes (`_aod_options`) | ✅ **traduits** depuis la **7.4** — saisie explicite `opt_label_ar[]` / `opt_value_name_ar[][]` dans `/gestion`. Le dictionnaire codé en dur ne sert plus que de **repli** |
| Noms de catégories | ✅ **traduits** depuis la **7.5** — term-meta `_aod_cat_name_ar`, rendue par un unique filtre `get_term` |

> ⚠️ **Ce tableau annonçait exactement l'inverse, sous l'étiquette « vérifié dans le
> code ».** Il décrivait l'état d'avant la **Phase 7**, soldée le 2026-07-17 — dont
> ce document a lui-même inspiré le contrat `{ fr, ar }`. Une affirmation périmée
> qui se présente comme vérifiée est la plus difficile à mettre en doute : c'est
> celle qu'on ne rouvre pas. La saisie explicite dont le paragraphe suivant disait
> qu'elle « n'existe nulle part » existe depuis la 7.4.

→ **Contrat de la landing : tout champ texte est un couple `{ fr, ar }`.** Le
visiteur voit la version de sa langue ; si elle est vide, il voit l'autre (jamais
de trou). Le gérant peut donc livrer une landing 100 % arabe, 100 % française ou
bilingue — sans jamais être bloqué.

Le helper existe déjà et est `public static` : **`AOD_COD_Data::label( $fr, $ar )`**
(`class-aod-cod-data.php:106`), aujourd'hui alimenté seulement par le JSON géo. La
landing est son premier vrai usage produit.

> **Les deux mécanismes coexistent, et les confondre casse le bilinguisme :**
> le **chrome** de la landing (« Commander maintenant », « Questions fréquentes »,
> « Voir plus ») est de l'**UI** → `__()` + `.po` (§7 de TEMPLATE.md).
> Le **contenu** (titre du héro, texte d'un avis, réponse d'une FAQ) est de la
> **donnée** → couple `{ fr, ar }` en base.

---

## 3. Rendu public — où le code s'insère

Nouvelle classe **`AOD_COD_Landing`** dans `aod-cod-form` (le plugin public).

```
template_include  ─→ is_product() && landing activée ?
                       ├─ non  → on ne touche à rien (fiche produit du thème, inchangée)
                       └─ oui  → templates/single-product-landing.php
                                   get_header()          ← en-tête du thème : nav 3 entrées + switcher FR/AR
                                   <blocs de la landing> ← dont ≥ 1 bloc « Formulaire COD »
                                   get_footer()          ← pied du thème
```

**Pourquoi `template_include` et pas les hooks Woo.** Habiller la fiche par hooks
(comme le formulaire aujourd'hui) laisse le thème imposer sa colonne, sa galerie et
son titre : on se battrait contre Astra à coups de CSS, sur chaque clone. En
reprenant le template, on garde l'en-tête et le pied du thème (donc la nav, le
switcher, le branding du Personnalisateur) et on maîtrise **tout** le corps.

C'est aussi ce qui explique la contrainte « thème **classique**, pas FSE » de
TEMPLATE.md §4 : `get_header()`/`get_footer()` supposent un thème classique. La
landing ne rajoute pas de contrainte — elle s'appuie sur celle qui existe déjà.

**Le bloc « Formulaire COD » appelle `AOD_COD_Form::get_form_html( $product_id )`** —
le point d'entrée unique déjà utilisé par le hook, le shortcode `[aod_cod_form]`, le
bloc Gutenberg et le widget Elementor. Conséquence directe : offres, variantes
visuelles, packs, anti-bot 5 couches (dont 4 sur un clone neuf), capture de prospects et pixels
**fonctionnent dans la landing sans une ligne de plus**. Aucun second formulaire à
maintenir — c'est non négociable.

`AOD_COD_No_Tunnel` reste actif (on est toujours sur `is_product()`) : ni panier, ni
compte, quoi qu'affiche la landing.

**Aperçu avant publication.** Tant que l'interrupteur est OFF, la landing est
visible via `?aod_landing_preview=1` **pour un utilisateur ayant `edit_products`
uniquement** — jamais pour un visiteur.

---

## 4. Contrat de données

### 4.1 Les deux métas produit

| Méta | Type | Contenu |
|---|---|---|
| `_aod_landing_enabled` | `'1'` \| absente | L'interrupteur. Absente = fiche produit normale. |
| `_aod_landing` | array sérialisé | **Toute** la landing : réglages de page + liste ordonnée de blocs. |

Un seul `get_meta()` au rendu, un seul `update_meta_data()` à l'enregistrement.

### 4.2 Schéma (`version: 1`)

```php
array(
  'version'    => 1,
  'sticky_cta' => array(                       // barre collante mobile
      'enabled' => true,
      'label'   => array( 'fr' => 'Commander', 'ar' => 'اطلب الآن' ),
  ),
  'blocks' => array(
      array(
          'id'    => 'b1',                     // identifiant stable (réordonnancement)
          'type'  => 'hero',                   // ∈ catalogue fermé (§5)
          'image' => 128,                      // ID d'attachement, jamais une URL
          'title' => array( 'fr' => '…', 'ar' => '…' ),   // ← le couple bilingue
          'text'  => array( 'fr' => '…', 'ar' => '…' ),
          'cta'   => array( 'fr' => '…', 'ar' => '…' ),
      ),
      array( 'id' => 'b2', 'type' => 'form' ), // le formulaire COD
      // …
  ),
)
```

**Les types de champ** (le vocabulaire que l'éditeur sait dessiner — un bloc qui ne
se sert que de ceux-là ne demande **aucune ligne** dans `/gestion`) :

| Type | En base | Dans `/gestion` |
|---|---|---|
| `text` · `textarea` | couple `{ fr, ar }` | deux cases, `Français` et `العربية` |
| `plain` | texte court, **non** bilingue | une case (emoji, prénom d'un avis) |
| `image` | ID d'attachement | vignette + choix de fichier |
| `images` | liste d'IDs | grille de vignettes retirables + envoi multiple |
| `video` | ID d'attachement | nom du fichier + envoi (taille max annoncée) |
| `url` | lien `http`/`https` | une case (une URL ne se traduit pas) |
| `number` | entier borné (`min`/`max` au schéma) | une case courte (une note, un seuil) |
| `select` | une clé parmi `options` — **liste fermée** | un menu déroulant |
| `repeat` | liste d'items | items ajoutables / retirables |

> **Un fichier peut vivre DANS un item** (la photo d'un avis). Son nom d'envoi porte
> alors le rang de l'item — `img_<bloc>__<champ>__<rang>__<sous-champ>` —, rang qui
> change dès qu'on déplace le bloc ou qu'on retire un avis. C'est le JS qui le réécrit
> depuis le DOM, à chaque modification **et une dernière fois avant l'envoi**. S'il le
> ratait, la photo atterrirait sur le mauvais avis ; c'est le premier endroit à
> regarder si un fichier se retrouve « à côté ».

**Règles du schéma, à tenir sans exception :**

1. **Tout texte est un couple `{ fr, ar }`.** Jamais une chaîne nue.
2. **Toute image est un ID d'attachement** (int), jamais une URL — sinon la
   migration de domaine à la mise en ligne casse toutes les images.
3. **Aucun HTML libre.** Les champs sont du texte (`sanitize_text_field`, ou
   `sanitize_textarea_field` pour les paragraphes). Un gérant ne colle pas de
   `<script>` dans sa landing, et on ne lui offre pas la possibilité de le faire.
4. **Catalogue fermé** : un `type` inconnu à la lecture est **ignoré au rendu**
   (pas d'erreur fatale sur un clone dont le plugin serait plus ancien).
5. `version` existe pour pouvoir migrer plus tard sans deviner.

### 4.3 Où vit le schéma — la décision anti-dérive

La feature est à cheval sur les deux plugins : le **dashboard écrit**, le
**cod-form lit et rend**. Deux copies du schéma = dérive garantie au premier ajout
de bloc.

→ **Le schéma, le nettoyage et les accesseurs vivent dans `AOD_COD_Landing`**
(plugin `aod-cod-form`, propriétaire de la donnée) :

```php
AOD_COD_Landing::schema()                  // catalogue des blocs + champs
AOD_COD_Landing::sanitize( array $raw )    // POST → structure propre (liste blanche)
AOD_COD_Landing::get( int $product_id )    // structure normalisée, prête au rendu
AOD_COD_Landing::text( array $field )      // { fr, ar } → la bonne langue + repli
```

Le dashboard **appelle** `sanitize()` — il ne réimplémente rien. Le précédent
existe : `/gestion` dépend déjà de `AOD_COD_Data` et `AOD_Shipping` du même plugin.

**La sécurité est côté écriture** (liste blanche stricte : un champ hors schéma est
jeté, jamais « conservé au cas où ») **et côté rendu** (`esc_html` / `esc_attr` /
`esc_url` systématiques). Les deux, pas l'une ou l'autre.

---

## 5. Catalogue des blocs

Socle (indispensables) :

| Bloc | Champs | Notes |
|---|---|---|
| **`hero`** | image, titre `{fr,ar}`, accroche `{fr,ar}`, libellé CTA `{fr,ar}` | Le CTA est une **ancre** vers le bloc formulaire — jamais un lien sortant. Contraste du texte sur l'image à vérifier (AA). |
| **`points`** | liste de { emoji, titre `{fr,ar}`, texte `{fr,ar}` } | Les bénéfices. |
| **`form`** | titre `{fr,ar}` (facultatif) | `AOD_COD_Form::get_form_html()`, précédé du titre s'il est saisi. **Au moins un par landing** — une landing sans formulaire ne vend rien : l'éditeur le refuse. |
| **`trust`** | liste de { emoji, texte `{fr,ar}` } | Réassurance COD : paiement à la livraison, 58 wilayas, retour. |

Contenu — ✅ **codé (étape 6.3)** :

| Bloc | Champs | Notes |
|---|---|---|
| **`gallery`** | titre `{fr,ar}` + liste d'IDs d'images | Grille responsive, `loading="lazy"`, `srcset` (posés par `wp_get_attachment_image()`). |
| **`video`** | titre `{fr,ar}`, URL YouTube **ou** ID d'attachement vidéo, image de couverture | **Façade obligatoire** — voir l'encadré ci-dessous. |
| **`text`** | titre `{fr,ar}`, texte `{fr,ar}` | Paragraphe libre. **`esc_html()` PUIS `wpautop()`** : les paragraphes naissent des sauts de ligne, jamais d'une balise collée par le gérant. |
| **`compare`** | titre `{fr,ar}`, titre des 2 colonnes `{fr,ar}`, lignes `{ gauche, droite }` `{fr,ar}` | Un vrai `<table>` avec `<th scope="col">` : un lecteur d'écran annonce « Chez nous : paiement à la livraison ». Deux `<div>` côte à côte ne diraient rien. |

> **La vidéo ne se charge jamais toute seule.** Une `<iframe>` YouTube posée d'emblée
> coûte ~1 Mo et une poignée de requêtes tierces **avant le moindre clic** : sur une
> connexion mobile algérienne, l'objectif « < 3 s » n'y survit pas, et le visiteur
> paie sa donnée pour une vidéo qu'il ne regardera peut-être pas.
>
> - **Lien YouTube** → on affiche la **couverture** (un `<button>`), et l'iframe n'est
>   injectée **qu'au clic**, sur `youtube-nocookie.com`. Le lien saisi n'est **jamais**
>   concaténé dans un `src` : on en **extrait** l'identifiant (11 caractères du jeu
>   YouTube), on le **valide**, et on **rebâtit** l'URL. Un lien de travers n'affiche
>   pas de lecteur mort — il n'injecte rien du tout. Sans JavaScript, un `<noscript>`
>   donne le lien en clair.
> - **Fichier téléversé** → `<video preload="none" poster>` : le navigateur ne
>   télécharge rien tant qu'on n'appuie pas sur Lire, et **aucun JS n'est chargé**.
> - Les deux remplis → **le fichier gagne** (rien à aller chercher chez un tiers).
>   L'éditeur le dit au gérant, au lieu de le lui laisser découvrir.
>
> Conséquence : le **seul** script de la landing n'est mis en file d'attente que si un
> bloc vidéo pointe réellement vers YouTube. Une page de vente sans vidéo reste
>100 % HTML/CSS.

Conversion — ✅ **codé (étape 6.4)** :

| Bloc | Champs | Notes |
|---|---|---|
| **`reviews`** | titre `{fr,ar}` + liste de { prénom, note 0-5, texte `{fr,ar}`, photo } | Le prénom n'est **pas** un couple : un prénom ne se traduit pas. Les étoiles sont un **dessin** (`aria-hidden`) : le lecteur d'écran, lui, entend « Note : 4 sur 5 » — « étoile étoile étoile » ne lui apprendrait rien. |
| **`faq`** | titre `{fr,ar}` + liste de { question `{fr,ar}`, réponse `{fr,ar}` } | `<details>/<summary>` **natifs** : l'accordéon s'ouvre sans une ligne de JS, il est annoncé correctement, il marche au clavier, et le navigateur sait y chercher du texte. Une question sans réponse n'est pas affichée. |
| **`urgency`** | mode (`stock` \| `sale`), message `{fr,ar}`, seuil | ⚠️ **Chiffres réels, ou rien.** Voir l'encadré ci-dessous. |

> **L'urgence dit la vérité, ou elle se tait.** C'est le bloc où la tentation de mentir
> est la plus forte, et c'est justement là qu'il ne faut pas céder : un client qui
> commande « les 2 dernières pièces » et attend trois semaines ne revient pas — et il
> le dit autour de lui.
>
> - **Mode `stock`** → le nombre vient de `$product->get_stock_quantity()`. Stock non
>   géré, rupture, ou stock **au-dessus du seuil** que le gérant a fixé : le bloc ne
>   s'affiche **pas**. Il n'invente rien pour remplir l'espace.
> - **Mode `sale`** → le rebours vise `$product->get_date_on_sale_to()` : l'instant
>   **exact** où WooCommerce retire le prix barré. Pas de promo, pas de date de fin, ou
>   date passée → pas de rebours. Il ne se réarme jamais : il n'a pas de mémoire à
>   réarmer, il lit une date en base. À zéro, la section **disparaît** — laisser
>   « 00 j 00 h » figé à l'écran serait mentir par immobilité.
> - Le message du gérant accepte le jeton **`%d`** (le nombre restant). Il est injecté
>   par `str_replace`, **jamais** par `sprintf` : un « -50 % » tapé dans le champ ferait
>   tomber `sprintf()` en erreur fatale (PHP 8) — la boutique afficherait une page
>   blanche. Vérifié : `-50 % ! %s %1$s %d restants` s'affiche tel quel, `%d` remplacé.
> - Le serveur écrit **déjà les bons chiffres** du rebours : sans JavaScript, le
>   visiteur voit un compteur juste mais immobile — jamais un trou.

Réglage de page (hors blocs) — ✅ **codé (étape 6.4)** : **`sticky_cta`**, la barre
« Commander » collée en bas de l'écran **sur mobile** (sur desktop, le bouton du héro et
celui du formulaire suffisent : elle est retirée). Elle ne s'affiche que si la page
contient un bloc `form` — un appel à l'action qui ne mène nulle part serait pire que
pas de bouton du tout. Trois exigences, tenues et vérifiées :

- elle **ne masque pas le contenu** : `body.aod-landing-sticky` reçoit une marge basse
  (c'est le `body` qu'on rallonge, pas la landing — le pied de page est **dehors**) ;
- elle **ne piège pas le focus** : quand le formulaire arrive à l'écran, elle passe en
  `visibility: hidden` — le lien sort donc aussi de l'ordre de tabulation. Un simple
  décalage l'aurait laissé focalisable, et l'utilisateur au clavier serait tombé sur un
  bouton qu'il ne voit pas ;
- **sans JavaScript**, elle reste simplement visible : dégradée, jamais cassée.

---

## 6. Éditeur dans `/gestion` — ✅ codé (étape 6.2)

**Écran dédié**, pas un onglet de la modale produit : un constructeur de blocs n'a
pas sa place dans une modale.

- **Accès** : Produits → bouton « Page de vente » sur la ligne du produit (avec une
  puce quand elle est affichée) → `/gestion/landing?produit=123`.
- **Classe dédiée `AOD_CD_Section_Landing`** (`class-aod-cd-section-landing.php`) —
  la DoD l'impose : on n'empile plus rien dans le monolithe. Section **masquée de la
  sidebar** (`hidden_sections()`, jamais dans `sections()` — c'est ce qui la garde
  hors du menu) mais **atteignable par URL** (`routable_sections()`), rattachée au
  domaine « Catalogue » pour le fil d'ariane.
- **Droits : gérant uniquement.** Section réservée à `manage_woocommerce` (comme
  Produits) ; l'AJAX revérifie `edit_products` **+ `edit_product` sur l'ID** + le
  périmètre produits de l'appelant. **Aucune nouvelle capability.**
- **Écran** : interrupteur ON/OFF · blocs à ajouter / monter / descendre / déplier /
  supprimer · pour chaque champ texte, **deux cases : `Français` et `العربية`** ·
  bouton « Aperçu » (URL produit + `?aod_landing_preview=1`, utile **surtout quand
  l'interrupteur est éteint**).
- **Enregistrement** : AJAX `aod_cd_save_landing` — `check_ajax_referer( 'aod_cd' )`
  + capabilities (403 sinon) → `AOD_COD_Landing::sanitize()`. Un seul enregistrement
  pour toute la landing, puis **rechargement de l'écran** : ce qui s'affiche est ce
  qui est en base, pas ce qu'on croit y avoir mis.
- **Images** : `media_handle_upload()` en **multipart** (`FormData` — le gérant n'a
  pas `wp.media`). Le fichier voyage sous un nom **plat** `img_<bloc>__<champ>` :
  `$_FILES` réorganise les noms imbriqués d'une façon que `media_handle_upload()` ne
  sait pas relire.
- **La renumérotation est le point délicat.** Les champs s'appellent
  `blocks[2][title][fr]` : l'index EST le rang à l'écran. Chaque champ porte donc son
  chemin dans `data-n`, et le JS réécrit tous les `name` depuis le DOM après chaque
  ajout / suppression / déplacement — **et une dernière fois juste avant l'envoi**.
- **Refus d'ALLUMER une landing sans bloc `form`** (ni sans aucun bloc) : une page de
  vente où l'on ne peut pas commander n'en est pas une. Écart assumé avec la spec
  d'origine : le refus ne porte que sur l'état **allumé** — sinon le gérant ne
  pourrait pas quitter son écran avant d'avoir fini. **L'interrupteur éteint est le
  brouillon** : les blocs sont conservés, la fiche produit s'affiche comme avant.
- **Un téléversement qui échoue ne passe pas pour un succès** : le texte est
  enregistré, et l'écran le dit — « l'image n'a pas pu être envoyée : … ». Un
  « Enregistré » suivi d'une image absente est le pire des deux mondes.

> ⚠️ **Piège d'environnement, découvert en 6.2.** Dans la stack Docker,
> `wp-content/uploads/<année>` appartient à l'utilisateur de l'hôte en 755 : Apache
> (`www-data`, uid 33) **ne peut pas y créer le dossier du mois**, et *tout*
> téléversement depuis `/gestion` échoue — image produit, galerie, vignette de
> catégorie, pas seulement la landing. Si un clone neuf refuse les images, regarder
> les droits de `uploads/` **avant** de suspecter le code.

---

## 7. Ce qu'on ne casse pas (non-régression)

- **Produit sans landing** = **exactement** le rendu d'aujourd'hui. L'interrupteur
  OFF ⇒ `template_include` ne fait rien. C'est le premier test de recette.
- Formulaire COD, offres, variantes, packs, anti-bot, Turnstile, prospects,
  WhatsApp, pixels : **inchangés**, puisque la landing appelle `get_form_html()`.
- Ni panier ni compte (`AOD_COD_No_Tunnel` s'applique toujours).
- Nav = 3 entrées, switcher FR/AR présent (en-tête du thème, via `get_header()`).
- Boutique, Accueil, Contact : **pas touchées**.

---

## 8. Qualité — les portes à passer

| Porte | Exigence |
|---|---|
| **Mobile** | Mobile-first. Rendu et clic testés à 390 px. |
| **Perf** | **< 3 s** sur mobile. Images `lazy` + `srcset` ; vidéo en façade ; **un** CSS et **un** JS, chargés **uniquement** si la landing est active ; zéro librairie tierce. |
| **RTL** | Propriétés logiques (`margin-inline`, `padding-inline`, `inset-inline`) — pas de `left/right` en dur. Testé en AR. |
| **a11y** | Contraste AA (attention au texte du héro **sur l'image**), FAQ en `<details>`, CTA = vrais boutons, sticky CTA qui ne piège pas le focus. |
| **i18n** | Chrome via `__()` + `make-translations.sh` des **deux** plugins (`--strict` vert). Contenu via les couples `{fr,ar}`. |
| **Recette** | `tools/recette/` : la landing entre dans le scénario `public` (nav 3 entrées, aucun panier, formulaire présent, FR **et** AR, 390 px). |
| **Générique** | Aucun contenu client dans le code. Les blocs livrés sont **vides** — le gérant les remplit. |

---

## 9. Questions tranchées / laissées ouvertes

**Tranchées** (owner, 2026-07-13) : landing = la page produit (§2.1) · blocs maison
(§2.2) · couples `{fr,ar}` avec repli (§2.3) · catalogue = les 11 blocs du §5 +
CTA collant.

**Les trois questions ci-dessous sont TRANCHÉES et livrées.** *(Conservées pour
la trace : elles expliquent pourquoi le découpage a pris cette forme. Elles ont
figuré ici comme « ouvertes, ne pas les traiter par défaut » bien après avoir été
livrées — alors même que l'en-tête de ce fichier, 390 lignes plus haut, décrit
déjà le modèle de départ et la copie.)*

1. **Modèle de départ** — ✅ livré en **6.5** : `AOD_COD_Landing::starter()`,
   8 blocs **vides**, possédé par le format.
2. **Copier une landing** d'un produit vers un autre — ✅ livré en **6.5** : la
   copie arrive **éteinte**, source intacte, images partagées, confirmation avant
   écrasement.
3. **Les autres contenus marchands** (titre produit, description,
   `_aod_selling_points`, catégories) — ✅ livrés par la **Phase 7** (soldée le
   2026-07-17). C'était bien un chantier **séparé**, comme prévu ici : la landing
   a prouvé le mécanisme `{ fr, ar }`, la Phase 7 l'a généralisé au catalogue.

---

## 10. Documents liés

- [ROADMAP-AMELIORATIONS.md](ROADMAP-AMELIORATIONS.md) — **Phase 6** : le découpage en étapes.
- [ARCHITECTURE.md](ARCHITECTURE.md) — l'état des lieux du code (à mettre à jour **à la livraison**, pas avant).
- [../TEMPLATE.md](../TEMPLATE.md) — §5 (les 3 pages, et pourquoi la landing n'en est pas une 4ᵉ) et §7 (i18n).
- [UX-GESTION-REFONTE.md](UX-GESTION-REFONTE.md) — le design system de `/gestion` que l'éditeur doit réutiliser.
