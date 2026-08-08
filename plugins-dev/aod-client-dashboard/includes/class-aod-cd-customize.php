<?php
/**
 * Personnalisateur : couleur d'accent de l'espace de gestion /gestion.
 *
 * Règle template : rien de spécifique à un client en dur. L'accent (vert par
 * défaut) est un réglage du Personnalisateur (`aod_cd_accent`). Toutes les
 * variantes utiles (survol, encre lisible, teinte douce) sont dérivées ici en
 * PHP puis injectées en ligne dans `:root` par render_app(). Le CSS ne contient
 * plus de couleur d'accent en dur — il ne lit que ces variables.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Customize {

	/**
	 * Accent par défaut du template.
	 *
	 * 🔁 `#FFE21E` → `#116048` → **`#0E5C66`** LE 2026-08-08. Trois demandes owner
	 * du même jour : « des couleurs qui rendent les yeux plus à l'aise », puis « des
	 * couleurs vert et bleu ciel clair », puis — capture d'une plateforme de
	 * livraison à l'appui — « ce bleu-là me plaît beaucoup, mets-le au fond ».
	 * ⚠️ Le fond ne prend de l'accent que sa TEINTE (cf. PAGE_SAT) : demander un
	 * fond turquoise, c'est donc demander un ACCENT turquoise. Les deux ne peuvent
	 * pas diverger sans casser la promesse « le fond porte la couleur du client ».
	 *
	 * 🚨 CE N'EST PAS UN CHANGEMENT DE PASTILLE, C'EST UN CHANGEMENT DE TOUT
	 * L'ÉCRAN, et c'est ce qui rendait la demande difficile à situer. Depuis que
	 * l'échelle de surfaces est DÉRIVÉE de l'accent (2026-08-04 → 08-06), l'accent
	 * ne colore plus seulement les boutons : il colore la carte, le creux, le
	 * champ de saisie, le survol de ligne, le fond de page et la sidebar. Mesuré
	 * sur le jaune, pour l'accent par défaut :
	 *
	 *   --cd-card  #fffbe2   --cd-bg    #fff9ce   --cd-field #fffdef
	 *   --cd-accent-wash #fff9d2 (chroma 45)      --cd-side  #2b2812 (kaki)
	 *
	 * Autrement dit : la totalité des surfaces de `/gestion` était du jaune très
	 * clair, et le texte s'y posait à 17,0:1. C'est la combinaison la plus
	 * fatigante possible — le jaune-vert est le maximum de sensibilité de l'œil,
	 * étalé ici sur la plus grande surface de l'écran, sous un texte quasi noir.
	 * Aucune porte ne pouvait le dire : `contraste` était verte, justement parce
	 * que le défaut est un EXCÈS de contraste sur une teinte éblouissante, pas un
	 * manque. La règle mesure un plancher ; il n'existe pas de plafond.
	 *
	 * `#0E5C66` (pétrole profond, teinte 187°) est mesuré, pas choisi à l'œil :
	 *   · luminance 0,087 — l'accent n'est pas la zone la plus lumineuse de l'écran,
	 *     donc les aplats pleins (bouton principal, onglet actif) n'éblouissent
	 *     pas ; `best_ink()` choisit le blanc, à **7,66:1** ;
	 *   · sa variante de survol `#397982` monte à 0,167, donc SOUS le seuil de
	 *     bascule (~0,179) : l'encre ne change pas au survol (cf. plus bas) ;
	 *   · texte fort sur carte : 14,4:1 au lieu de 17,0:1, sur un blanc cassé
	 *     froid (`#e0eae7`) au lieu d'un blanc jaune ;
	 *   · sidebar `#17231f` au lieu de `#2b2812` — le « noir profond » du thème
	 *     redevient noir au lieu de virer kaki ;
	 *   · et c'est sa TEINTE qui donne le fond demandé : `#cbebe2` → `#b1cce2`,
	 *     vert d'eau vers bleu ciel clair (cf. `PAGE_SAT` et `PAGE2_HUE`).
	 *
	 * 🚨 POURQUOI CETTE PROFONDEUR-LÀ ET PAS UN VERT PLUS FRANC. `css_vars()`
	 * fabrique la variante de survol en ÉCLAIRCISSANT de 18 % tout accent dont la
	 * luminance est ≤ 0,20, puis choisit son encre séparément (`$strong_ink`).
	 * Un accent moyen traverse donc le seuil de bascule de `best_ink()` (~0,179)
	 * en passant au survol : mesuré, `#14714E` et `#0E7357` — deux verts nettement
	 * plus vifs — font passer le libellé du bouton principal du BLANC au NOIR au
	 * simple survol. C'est correct au sens d'AA, c'est même la raison d'être de
	 * `$strong_ink`, mais ça clignote. Le pétrole connaît le même piège : mesuré le
	 * 2026-08-08, `#106B78`, `#0B6473` et `#14707D` — trois turquoises à peine plus
	 * clairs — font tous BASCULER l'encre au survol. `#0E5C66` monte de 0,087 à
	 * 0,167 : il reste sous le seuil des deux côtés, l'encre ne bouge pas.
	 * ⚠️ Ne pas « rafraîchir » cet accent en l'éclaircissant sans revérifier ce
	 * point : la mesure qui compte n'est pas la luminance de l'accent, c'est celle
	 * de sa variante de survol. Vert comme turquoise, la famille « lisible » vit
	 * presque entièrement autour du seuil : c'est là qu'il faut mesurer, pas ailleurs.
	 *
	 * ⚠️ LE COÛT DE TEINTE, DIT UNE FOIS ET ASSUMÉ. À 187°, cet accent est à **6°**
	 * de `--cd-info` (193°), qui porte le statut « terminée » d'une commande et deux
	 * pastilles de KPI. C'est exactement l'objection qui avait fait écarter le
	 * pétrole `#245A62` le matin même — la marque et un statut disent la même
	 * couleur. L'owner a tranché pour cette teinte-là, capture à l'appui : la
	 * décision est la sienne, la mesure reste écrite ici pour qui la relira.
	 * Atténuation réelle : `--cd-info` est un aplat SATURÉ sur une pastille de
	 * 20 px, l'accent un pétrole PROFOND sur des boutons — ils ne se croisent pas
	 * dans le même registre. La confusion resterait possible entre un bouton
	 * principal et une pastille « terminée » vues séparément.
	 *
	 * ⚠️ LE COÛT ASSUMÉ DE CE CHOIX, écrit ici pour que personne n'ait à le
	 * redécouvrir : `--cd-ok` (#146c2e) est à **138°**, soit **24°** de cet accent.
	 * La marque et le vert « tout va bien » appartiennent donc à la même famille,
	 * et une pastille de statut cesse d'être la seule chose verte de l'écran. Un
	 * accent bleu-vert avait été retenu la veille précisément pour éviter ça ; la
	 * demande de couleurs vertes a tranché autrement, et c'est un arbitrage
	 * produit, pas un oubli. Deux choses limitent la casse, mesurées :
	 *   · l'écart de 24° est EXACTEMENT celui que l'ancien défaut jaune (53°) avait
	 *     avec `--cd-warn` (29°) pendant toute la vie du template, sans que rien ne
	 *     s'en plaigne — la proximité de teinte n'est pas, à elle seule, une faute ;
	 *   · les registres restent distincts : `--cd-ok` ne paraît qu'en TEXTE sur une
	 *     pastille pâle, l'accent qu'en APLAT sous du blanc.
	 * ⚠️ Si l'écart devait être repris, c'est `--cd-ok` qu'il ne faut PAS bouger :
	 * il est calibré AA sur ses fonds doux, et il est lu par le plancher de
	 * `WASH_MIN_LUM`.
	 *
	 * ⚠️ CE N'EST QU'UN DÉFAUT, et il reste un défaut : le réglage
	 * « aod_cd_accent » du Personnalisateur le remplace, comme avant. Un clone qui
	 * a choisi sa couleur ne voit rien changer. Revenir en arrière = cette seule
	 * constante + les replis de `:root` régénérés (que le contrôle « le repli du
	 * CSS dit la vérité » de `test-charte-surfaces.php` exige de toute façon).
	 */
	const DEFAULT_ACCENT = '#116048';

	/**
	 * Base sombre neutre de la coque (`--cd-ink`) : pastille de langue active,
	 * pagination active. Elle NE dépend pas de l'accent, elle sert de socle.
	 */
	const NEUTRAL_DARK = '#181818';

	/**
	 * Part de neutre dans la sidebar dérivée (ROADMAP Phase 15, étape 15.5).
	 *
	 * 0.85 = 15 % d'accent, 85 % de `NEUTRAL_DARK`. Calibré par balayage du cube
	 * RGB (cf. docs/ICONES-ET-CHARTE-GESTION.md §4.3) : c'est le meilleur compromis
	 * entre une teinte réellement perceptible et la marge de contraste du texte de
	 * navigation. Plus bas, le fond s'éclaircit et le texte se rapproche du seuil ;
	 * plus haut, la teinte de marque disparaît et la dérivation ne sert plus à rien.
	 */
	const SIDE_MIX = 0.85;

	/**
	 * Luminance maximale de la sidebar dérivée.
	 *
	 * 🚨 Sans ce plafond, la dérivation est uniforme dans sa FORMULE mais pas dans
	 * son RÉSULTAT : un mélange à taux fixe transmet la clarté de l'accent autant
	 * que sa teinte. Mesuré — la sidebar sort à 1,0× la luminance du neutre pour un
	 * accent presque noir, 2,4× pour un vert moyen… et **4,0×** pour le jaune par
	 * défaut du template, qui vire au kaki nettement plus clair que le noir voulu.
	 *
	 * Le plafond rend la règle uniforme dans son résultat : « la sidebar est
	 * toujours à peu près aussi sombre, teintée de VOTRE couleur ». Il ne se
	 * déclenche que sur les accents très clairs — tous les autres ressortent
	 * intacts, y compris le vert de référence de la documentation (0.0216).
	 */
	const SIDE_MAX_LUM = 0.022;

	/**
	 * Part de blanc dans les SURFACES TEINTÉES qui portent du texte (Phase 17).
	 *
	 * Survol de ligne, ligne sélectionnée, ligne de transporteur dépliée, note du
	 * gérant : des fonds qui doivent se teinter de l'accent du client sans jamais
	 * mettre en péril ce qui est écrit dessus.
	 *
	 * 🚨 L'ENCRE CONTRAIGNANTE N'EST PAS `--cd-ink` (confortable partout). Deux
	 * autres vivent dans ces cellules et décident, chacune à son tour :
	 *   · `--cd-muted` — prix barré, coût, variantes, numéro de suivi. C'est lui
	 *     qui a fixé le plancher de la Phase 17, et la raison d'être de ce token
	 *     face à `--cd-accent-soft`, calibré pour porter `--cd-ink` et rien
	 *     d'autre. Réutiliser `soft` ici — ce que faisait la note du gérant —
	 *     ouvrait une brèche AA silencieuse, invisible sur l'accent jaune.
	 *   · `--cd-ok` (#146c2e) — la variation d'un KPI. 🔴 Trouvé le 2026-08-06 par
	 *     le balayage du cube : à `WASH_MIN_LUM = 0,66`, l'accent BLEU PUR tombait
	 *     à **4,40:1** sous cette encre-là. Le gris n'était plus le pire cas, et
	 *     rien ne le disait — le plancher avait été déduit de lui seul.
	 * 🚨 *Un plancher déduit d'une encre reste juste tant qu'aucune encre plus
	 * sombre n'arrive sur la même surface. Ici elle y était déjà.*
	 *
	 * ⚠️ Il y a désormais un plancher de luminance, contrairement à ce que disait
	 * cette note : à 0.94 il ne se serait jamais déclenché, mais l'échelle est
	 * descendue à 0.80 et il mord sur tous les accents sombres.
	 */
	/*
	 * ═══════════════════════════════════════════════════════════════════════════
	 * 🎨 L'ÉCHELLE DE SURFACES (2026-08-06) — demande owner : « je ne veux pas voir
	 * de blanc dedans ».
	 * ═══════════════════════════════════════════════════════════════════════════
	 *
	 * 🚨 CE N'EST PAS UNE RETOUCHE DE COULEURS, ET IL FAUT SAVOIR POURQUOI AVANT DE
	 * TOUCHER UN SEUL DE CES NOMBRES. Jusqu'ici les cinq surfaces vivaient dans
	 * l'intervalle ]0,92 ; 1,0[ — huit centièmes — et ce plafond n'était pas un
	 * choix de goût : `--cd-muted` valait #666666, et une surface qui porte ce gris
	 * ne peut pas descendre plus bas sans passer sous AA. **Autrement dit, le blanc
	 * n'était pas décoratif : il était la conséquence arithmétique d'un gris de
	 * texte.** Le retirer sans toucher au gris est impossible ; on ne peut que
	 * choisir lequel des deux bouge.
	 *
	 * Le gris bouge donc, et l'intervalle s'ouvre à ]0,52 ; 1,0[. Les cinq surfaces
	 * s'y répartissent en une ÉCHELLE ORDONNÉE, du plus profond au plus clair :
	 *
	 *   creux (BG_MIX) < survol (WASH_MIX) < carte (CARD_MIX) < champ (FIELD_MIX)
 *
 * 🔁 LE FOND DE PAGE A QUITTÉ CETTE ÉCHELLE. D'abord le 2026-08-07 (il ne
 * pouvait plus en être le cran le plus profond, `PAGE_MIX` étant passé au-dessus
 * de `BG_MIX`), puis pour de bon le 2026-08-08 : il n'est même plus construit par
 * la même méthode (cf. `PAGE_SAT`). Mesuré aujourd'hui, il est même PLUS CLAIR
 * que le creux à son extrémité haute (0,776 contre 0,704) et plus SOMBRE à son
 * extrémité basse (0,580) — un dégradé n'a pas UNE place dans un classement.
 * ⚠️ Ce n'est pas une régression : un creux se lit contre LA CARTE qui le
 * contient, jamais contre le fond de l'application, dont aucune règle ne le
 * rapproche. L'ordre qui compte pour lui est `creux < carte`, et il tient.
	 *
	 * Elle porte la structure visuelle que la demande réclame : on lit la
	 * profondeur d'un élément à sa teinte, pas seulement à son ombre. Un champ de
	 * saisie reste le point le plus clair de l'écran — c'est ce qui le désigne
	 * comme l'endroit où l'on écrit — mais il n'est plus blanc pour autant.
	 *
	 * ⚠️ CHAQUE CRAN A SON PLANCHER DE LUMINANCE, et c'est ce qui rend l'échelle
	 * sûre pour un accent SOMBRE. Un mélange à taux fixe transmet la clarté de
	 * l'accent autant que sa teinte : à ces taux-là, l'accent presque-noir sort
	 * sous AA sur les cinq crans. Les planchers les relèvent — ordonnés eux aussi,
	 * sans quoi un accent sombre écraserait les cinq surfaces sur une seule valeur
	 * et l'écran redeviendrait plat, en gris cette fois.
	 */

	const WASH_MIX = 0.80;

	/** Plancher de luminance du survol (cf. l'échelle de surfaces). */
	const WASH_MIN_LUM = 0.73;

	/**
	 * Aplat d'accent posé DANS une carte (`--cd-accent-soft`) — hors échelle.
	 *
	 * 🔁 0.86 → 0.74 le 2026-08-06 : à 0.86 il tombait à un cheveu de `CARD_MIX`
	 * (0.87), soit deux tokens vivants pour une même valeur — le mode d'échec nommé
	 * en 46.4. Il descend donc avec le reste et redevient ce qu'il est : un aplat,
	 * pas une nuance de la carte. Cf. soft() pour pourquoi il n'a pas de plancher.
	 */
	const SOFT_MIX = 0.74;

	/**
	 * Mélange du FOND DE PAGE (`--cd-bg`) vers le blanc — demande owner du
	 * 2026-08-04 : « une couleur pour le fond ».
	 *
	 * 🚨 POURQUOI DÉRIVÉ ET NON ÉCRIT EN DUR. La demande était « turquoise ». Un
	 * `#ECF6F6` posé dans `dashboard.css` aurait été juste pour CE client et faux
	 * pour le suivant — c'est exactement ce que `test-charte-surfaces.php`
	 * interdit en séparant la surface neutre (grise, légitime en dur) de la
	 * couleur, qui doit venir d'un token. Ici le fond prend la teinte de l'accent
	 * choisi dans le Personnalisateur : turquoise chez qui choisit le turquoise,
	 * gris-bleu chez qui choisit du bleu nuit. La demande est satisfaite SANS
	 * qu'un clone hérite du goût d'un autre.
	 *
	 * ⚠️ CE N'EST PLUS LE FOND DE PAGE depuis le 2026-08-05 (cf. `PAGE_SAT`) : c'est
	 * la surface CREUSÉE à l'intérieur des cartes — zébrures de tableau, en-têtes,
	 * encarts, puces de code, pastilles neutres. Le nom est resté ; les 34 règles
	 * qui le lisent sont toutes de ce second rôle.
	 *
	 * Deuxième cran de l'échelle : plus clair que la page, plus sombre que la
	 * carte qui le contient. C'est cet ordre — et non sa valeur absolue — qui fait
	 * qu'un creux se lit comme un creux.
	 */
	const BG_MIX = 0.78;

	/** Plancher de luminance du creux (cf. l'échelle de surfaces). */
	const BG_MIN_LUM = 0.70;

	/**
	 * Mélange de la SURFACE DES CARTES (`--cd-card`) vers le blanc — demande owner
	 * du 2026-08-04 : « toutes les petites sections sont blanches ».
	 *
	 * 🔁 LE COMMENTAIRE QUI VIVAIT ICI EST DEVENU FAUX LE 2026-08-06, et il vaut
	 * d'être rappelé plutôt qu'effacé : il expliquait que la carte ne pouvait pas
	 * être « bien colorée » parce que la marge de manœuvre était ]0,92 ; 1,0[, soit
	 * huit centièmes. C'était exact — À GRIS DE TEXTE CONSTANT. Le raisonnement
	 * prenait `--cd-muted` (#666666) pour une donnée du problème alors que c'en
	 * était un paramètre. Une fois le gris descendu (cf. l'échelle de surfaces),
	 * l'intervalle passe à ]0,52 ; 1,0[ et la conclusion s'inverse.
	 * 🚨 *Une contrainte qu'on démontre à partir d'une valeur qu'on n'a pas pensé à
	 * remettre en cause est un plafond de verre : elle est vraie, elle est
	 * mesurée, et elle est déplaçable.*
	 *
	 * Quatrième cran de l'échelle. La carte reste plus claire que le survol qui s'y
	 * pose et plus sombre que les champs qu'elle contient : ce sont ces deux
	 * inégalités qui la font lire comme une feuille, pas sa valeur.
	 */
	const CARD_MIX = 0.87;

	/** Plancher de luminance de la carte (cf. l'échelle de surfaces). */
	const CARD_MIN_LUM = 0.77;

	/**
	 * Mélange des CHAMPS DE SAISIE (`--cd-field`) vers le blanc — cinquième et
	 * dernier cran de l'échelle (2026-08-06).
	 *
	 * 🚨 CE TOKEN NAÎT DE LA DEMANDE « pas de blanc ». Les champs étaient le
	 * dernier endroit où le blanc PUR subsistait, et c'était assumé : depuis le
	 * 2026-08-04, « le blanc cesse d'être le défaut et devient un RÔLE — celui du
	 * champ de saisie ». Le rôle reste ; sa couleur change. Un champ doit être le
	 * point le plus CLAIR de l'écran — c'est ce qui désigne l'endroit où l'on
	 * écrit — mais « le plus clair » est une position dans l'échelle, pas une
	 * couleur absolue. Le blanc n'en était qu'une lecture littérale.
	 *
	 * ⚠️ Il reste 30 `#fff` dans la feuille, et ils sont JUSTES : ce sont des
	 * ENCRES posées sur un aplat sombre ou saturé (glyphe blanc sur `--cd-bad`,
	 * libellé de l'onglet actif sur `--cd-ink`). Les teinter casserait le contraste
	 * que la Phase 46 a mesuré. « Pas de blanc » porte sur les SURFACES.
	 */
	const FIELD_MIX = 0.93;

	/** Plancher de luminance des champs (cf. l'échelle de surfaces). */
	const FIELD_MIN_LUM = 0.84;

	/**
	 * SATURATION HSL du PREMIER TON du fond d'application.
	 *
	 * 🔁 `PAGE_SAT` ET `PAGE_LUM` REMPLACENT `PAGE_MIX` ET `PAGE_DESAT` LE
	 * 2026-08-08 (demande owner : « des couleurs vert et bleu ciel clair, un très
	 * bon visuel au niveau du background »). Ce n'est pas un réglage de plus, c'est
	 * un changement de MÉTHODE — et il a fallu le faire pour que la demande soit
	 * tenable ailleurs que sur un seul accent.
	 *
	 * 🚨 CE QUI NE POUVAIT PAS MARCHER. Les deux tons étaient l'accent
	 * DÉSATURÉ puis mélangé vers le blanc. Or une désaturation est RELATIVE : elle
	 * divise le chroma de l'accent, donc le fond héritait de la clarté ET de la
	 * vivacité du réglage client. Mesuré, à `PAGE_DESAT` abaissé juste assez pour
	 * rendre le vert demandé visible :
	 *
	 *   accent vert  #116048 → chroma du fond 16 → 22   (invisible)
	 *   accent jaune #FFE21E → chroma du fond 45 → 68   (criard, et vire au saumon)
	 *   accent rouge #ff0000 → chroma du fond 51 → 78   (criard)
	 *
	 * Autrement dit AUCUNE valeur de `PAGE_DESAT` ne pouvait satisfaire les deux :
	 * la rendre visible sur un accent sombre la rendait hurlante sur un accent
	 * clair. C'est exactement le fond « trop moche » arbitré le 2026-08-06,
	 * reproduit par l'autre bout du même paramètre — et sept livraisons de dosage
	 * de blanc n'y pouvaient rien, parce que le défaut n'était pas dans le dosage.
	 *
	 * 🚨 LA CORRECTION. Le fond ne prend plus de l'accent que sa TEINTE ; sa
	 * saturation et sa clarté sont les SIENNES, écrites ici. Mesuré sur les huit
	 * accents témoins : le chroma du premier ton vaut **32 pour tous**, seule la
	 * teinte change. Le fond a donc la même qualité chez tout le monde, ce qu'un
	 * mélange à taux fixe ne peut structurellement pas donner.
	 * ⚠️ La règle n°1 du template est tenue à l'identique : c'est la teinte du
	 * client qui gouverne, aucune couleur n'est écrite ici — seulement la manière
	 * de la porter.
	 * ⚠️ Un accent GRIS (chroma 0) ne se voit toujours pas inventer de couleur :
	 * `ton()` le laisse neutre. Gardé par R5bis de `test-fond-accent.php`.
	 */
	const PAGE_SAT = 0.45;

	/**
	 * CLARTÉ HSL du premier ton (cf. `PAGE_SAT` pour la méthode).
	 *
	 * Bornée des DEUX côtés, et c'est ce qui l'empêche d'être un réglage de goût :
	 *   · EN HAUT par R2bis de `test-fond-accent.php` — le fond doit rester plus
	 *     sombre que les cartes, sinon l'écran redevient plat. Mesuré : à **0,88**
	 *     le fond passe DEVANT la carte sur l'accent vert (0,8071 contre 0,8047) ;
	 *   · EN BAS par `PAGE_MIN_LUM` — du texte secondaire se pose dessus.
	 * ⚠️ La marge du haut est de trois centièmes de luminance. Monter cette valeur
	 * « pour éclaircir un peu » fait rougir R2bis, et c'est voulu.
	 */
	const PAGE_LUM = 0.86;

	/**
	 * Luminance MINIMALE des deux tons du fond d'application (cf. `PAGE_LUM`).
	 *
	 * Même forme que SIDE_MAX_LUM, en miroir : là-bas un plafond empêchait un accent
	 * clair d'éclaircir la sidebar, ici un plancher empêche un accent sombre
	 * d'assombrir la page. La règle devient uniforme dans son RÉSULTAT — « le fond
	 * est toujours à peu près aussi foncé, teinté de VOTRE couleur » — et non
	 * seulement dans sa formule.
	 *
	 * 🔒 LA VALEUR N'EST PAS CHOISIE, ELLE EST DÉDUITE. C'est la luminance à
	 * laquelle `--cd-muted` garde **4,7:1** sur ce fond : (L + 0,05) / (L_muted +
	 * 0,05) = 4,7. Avec le gris descendu à #4f4f4f (luminance 0,078173) :
	 * L = 4,7 × 0,128173 − 0,05 = **0,5524**. On vise 4,7 et non 4,5 pour garder
	 * deux dixièmes de marge — le seuil AA n'est pas un objectif, c'est une limite,
	 * et un fond calé pile dessus n'en laisse aucune au correctif suivant.
	 *
	 * 🚨 CE NOMBRE EST SOLIDAIRE DE `--cd-muted` (dashboard.css). Changer l'un sans
	 * l'autre casse la déduction en silence : le plancher continuerait d'énoncer
	 * une garantie qu'il ne produit plus. C'est pour ça que `test-fond-accent.php`
	 * mesure le RAPPORT obtenu, et pas la conformité au nombre écrit ici.
	 *
	 * ⚠️ NE PAS BAISSER CE PLANCHER POUR OBTENIR UN FOND PLUS FONCÉ. Ce sont
	 * `PAGE_LUM` et `PAGE2_LUM` qui donnent la clarté ; ce nombre-ci ne fait
	 * qu'énoncer ce que la lisibilité autorise.
	 */
	const PAGE_MIN_LUM = 0.5524;

	/**
	 * SATURATION HSL du SECOND TON — la même que le premier, à dessein : c'est la
	 * TEINTE qui fait le balayage (cf. `PAGE2_HUE`), pas un écart de vivacité. Deux
	 * saturations différentes se liraient comme un défaut d'impression.
	 */
	const PAGE2_SAT = 0.45;

	/**
	 * CLARTÉ HSL du SECOND TON. Sept centièmes sous le premier : c'est cet écart
	 * qui fait lire un DÉGRADÉ plutôt qu'un aplat, et R5 de `test-fond-accent.php`
	 * exige que le second soit le plus profond des deux.
	 *
	 * ⚠️ Il passe par le MÊME plancher que le premier (`PAGE_MIN_LUM`), et celui-ci
	 * le rattrape réellement — ce n'est pas un garde-fou décoratif : sur le rouge,
	 * le bleu, le bordeaux et le presque-noir, `--cd-muted` y ressort à **4,70:1**,
	 * soit exactement la valeur pour laquelle le plancher a été calculé. Du texte se
	 * pose sur TOUTE la longueur du balayage : une lisibilité garantie à un seul
	 * bout d'un dégradé n'est pas une garantie.
	 */
	const PAGE2_LUM = 0.79;

	/**
	 * Rotation de teinte, en degrés, entre les deux tons du fond.
	 *
	 * 🚨 CE N'EST PAS UNE SECONDE COULEUR CHOISIE, et ça ne peut pas l'être : la
	 * règle n°1 du template interdit d'écrire la couleur d'un client dans le code.
	 * Un `#c7e9ff` en dur aurait été joli chez celui qui a demandé le dégradé et
	 * faux chez tous les autres. Le second ton est le VOISIN de l'accent : même
	 * famille, décalé d'un cran sur la roue, vers le froid (cf. COOL_HUE).
	 *
	 * 🔁 38° → 45° → **26°** le 2026-08-08. Les deux premiers chiffres avaient été
	 * calibrés du temps où les tons étaient l'accent MÉLANGÉ vers le blanc : il
	 * fallait alors beaucoup de rotation pour que la différence survive au mélange,
	 * et le commentaire d'origine disait « en dessous de ~25° les deux tons se
	 * confondent ». Cet argument est MORT avec la méthode : depuis que les tons sont
	 * construits à leur propre clarté (cf. PAGE_SAT), ils diffèrent déjà de sept
	 * centièmes de luminance — la rotation n'a plus à porter seule la distinction.
	 * ⚠️ *Un seuil resté vrai peut avoir perdu sa raison d'être.* Celui-ci gardait
	 * une contrainte qui n'existait plus, et coûtait la teinte du second ton.
	 *
	 * Mesuré sur les six accents témoins, à 45° le second ton QUITTE la famille de
	 * l'accent : le pétrole 186° partait à 231° (parme), le vert 163° à 207°. À 26°
	 * il reste voisin — 186° → 213°, un bleu ciel — et l'écart de canal vaut encore
	 * 33 (R5 en exige 8). C'est la demande owner du jour : « ce bleu-là au fond »,
	 * pas « ce bleu-là puis autre chose ».
	 */
	const PAGE2_HUE = 45.0;

	/**
	 * Teinte de référence vers laquelle le second ton s'incline (cf. PAGE2_HUE).
	 *
	 * 🚨 LE SENS DE ROTATION N'EST PAS ARBITRAIRE, et le laisser au hasard rendait
	 * le résultat laid une fois sur deux — mesuré sur les six accents témoins :
	 * turquoise + 38° donne un bleu, − 38° un vert ; jaune − 38° donne un orangé,
	 * + 38° un vert acide. Un signe fixe (« toujours +38 ») aurait donc soigné une
	 * moitié des clones et abîmé l'autre.
	 * On tourne vers le côté FROID de la roue, par l'arc le plus court : le second
	 * ton est l'extrémité profonde du balayage, et une couleur froide y lit comme
	 * de l'ombre plutôt que comme une seconde tache de peinture. Résultat sur les
	 * témoins : jaune → orangé, turquoise → bleu, bleu → indigo, rouge → rosé,
	 * vert → sarcelle, bordeaux → prune.
	 *
	 * ⚠️ Un accent GRIS (chroma 0) n'a pas de teinte à faire tourner : le dégradé
	 * dégénère alors en un balayage clair → moins clair de la même couleur. C'est
	 * le comportement voulu — inventer une teinte à un gris reviendrait à choisir
	 * une couleur à la place du client.
	 */
	const COOL_HUE = 240.0;

	/** Amorçage. */
	public static function init() {
		add_action( 'customize_register', array( __CLASS__, 'register' ) );
	}

	/**
	 * Déclare la section + le contrôle couleur dans le Personnalisateur.
	 *
	 * @param WP_Customize_Manager $wp_customize Gestionnaire du Personnalisateur.
	 */
	public static function register( $wp_customize ) {
		$wp_customize->add_section(
			'aod_cd_branding',
			array(
				'title'       => __( 'Espace de gestion', 'aod-client-dashboard' ),
				'description' => __( 'Couleur d’accent de l’espace gérant (/gestion). Le logo et le nom viennent de « Identité du site ».', 'aod-client-dashboard' ),
				'priority'    => 45,
			)
		);

		$wp_customize->add_setting(
			'aod_cd_accent',
			array(
				'default'           => self::DEFAULT_ACCENT,
				'sanitize_callback' => 'sanitize_hex_color',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'aod_cd_accent',
				array(
					'label'       => __( 'Couleur d’accent', 'aod-client-dashboard' ),
					'description' => __( 'Boutons, éléments actifs, points forts de l’interface de gestion.', 'aod-client-dashboard' ),
					'section'     => 'aod_cd_branding',
				)
			)
		);
	}

	/** Valeur d'accent courante, toujours un hex valide. */
	public static function accent() {
		$c = sanitize_hex_color( (string) get_theme_mod( 'aod_cd_accent', self::DEFAULT_ACCENT ) );
		return $c ? $c : self::DEFAULT_ACCENT;
	}

	/**
	 * Fond de la sidebar, DÉRIVÉ de l'accent (étape 15.5).
	 *
	 * 🚨 Pourquoi dériver au lieu d'écrire la couleur. Le fond noir-vert voulu par
	 * un client donné ne peut PAS être écrit dans le CSS : règle template n°1 —
	 * rien de spécifique à un client en dur. Chaque futur clone hériterait des
	 * couleurs de ce client-là. En dérivant, un seul réglage (l'accent) suffit et
	 * chaque clone obtient une sidebar teintée de SA couleur.
	 *
	 * Le mélange part de la base neutre existante, et non du noir pur : vers le
	 * noir, un accent saturé donne un fond quasi noir (#001108 mesuré) où la teinte
	 * ne se lit plus — on obtenait le coût de la dérivation sans son bénéfice.
	 *
	 * @param string|null $accent Accent à dériver. Par défaut celui du site — le
	 *                            paramètre existe pour que les tests puissent
	 *                            balayer le cube RGB à travers CE code plutôt
	 *                            qu'en recopiant la formule (une copie ne verrait
	 *                            ni le plafond, ni ce qu'on ajoutera demain).
	 * @return string Hex.
	 */
	public static function side( $accent = null ) {
		$accent = ( null === $accent ) ? self::accent() : $accent;
		$side   = self::mix( self::rgb( $accent ), self::rgb( self::NEUTRAL_DARK ), self::SIDE_MIX );

		// Rassombrissement par paliers jusqu'au plafond (cf. SIDE_MAX_LUM). Même
		// forme que readable_on() : assombrir vers le noir fait décroître la
		// luminance de façon monotone, donc le premier palier qui passe est aussi
		// celui qui préserve le mieux la teinte. Le compteur borne la boucle ; le
		// noir finit toujours par passer.
		for ( $i = 0; $i < 40 && self::luminance( $side ) > self::SIDE_MAX_LUM; $i++ ) {
			$side = self::mix( $side, 0, 0.03 );
		}

		return self::to_hex( $side );
	}

	/**
	 * Surface teintée portant du texte, DÉRIVÉE de l'accent (Phase 17).
	 *
	 * Même raison d'être que side() : le survol d'une ligne de tableau était écrit
	 * `#fdf6d8` et la ligne sélectionnée `#fffdf0` — deux jaunes hérités de l'accent
	 * PAR DÉFAUT du template. Un client au bordeaux gardait des lignes jaunes.
	 * C'était la dernière couleur de client réellement en dur (règle template n°1).
	 *
	 * Dérivé en PHP et non en `color-mix()` CSS, délibérément : la garantie « AA
	 * pour tout accent » est produite par un test qui balaie le cube RGB en
	 * appelant CE code. Une valeur calculée par le navigateur obligerait le test à
	 * recopier la formule — exactement ce que side() interdit en toutes lettres.
	 *
	 * @param string|null $accent Accent à dériver. Par défaut celui du site — le
	 *                            paramètre existe pour que les tests passent par le
	 *                            vrai code plutôt que par une copie de la formule.
	 * @return string Hex.
	 */
	public static function wash( $accent = null ) {
		return self::surface( $accent, self::WASH_MIX, self::WASH_MIN_LUM );
	}

	/**
	 * Un cran de l'échelle de surfaces : l'accent mélangé vers le blanc, puis
	 * relevé par paliers jusqu'à son plancher de luminance.
	 *
	 * 🚨 LE RELÈVEMENT SE FAIT PAR PALIERS, comme `side()` le fait vers le noir, et
	 * pour la même raison : éclaircir fait croître la luminance de façon monotone,
	 * donc le premier palier qui passe est aussi celui qui préserve le mieux la
	 * teinte. Résoudre l'équation d'un coup rendrait un gris — la couleur du client
	 * se perdrait dans l'arithmétique. Le compteur borne la boucle ; le blanc finit
	 * toujours par passer.
	 *
	 * ⚠️ Un accent CLAIR ne touche jamais son plancher : il ressort au taux nu. Le
	 * garde-fou ne rattrape que les accents sombres — pendant exact de
	 * SIDE_MAX_LUM, qui ne rattrape que les clairs.
	 *
	 * @param string|null $accent  Accent à dériver (défaut : celui du site).
	 * @param float       $mix     Part de blanc dans le mélange.
	 * @param float       $min_lum Luminance minimale acceptée.
	 * @return string Hex.
	 */
	protected static function surface( $accent, $mix, $min_lum ) {
		$accent = ( null === $accent ) ? self::accent() : $accent;
		$out    = self::mix( self::rgb( $accent ), 255, $mix );

		for ( $i = 0; $i < 40 && self::luminance( $out ) < $min_lum; $i++ ) {
			$out = self::mix( $out, 255, 0.03 );
		}

		return self::to_hex( $out );
	}

	/** Surface CREUSÉE dans les cartes — 2ᵉ cran (cf. l'échelle de surfaces). */
	public static function bg( $accent = null ) {
		return self::surface( $accent, self::BG_MIX, self::BG_MIN_LUM );
	}

	/** Surface des CARTES — 4ᵉ cran (cf. l'échelle de surfaces). */
	public static function card( $accent = null ) {
		return self::surface( $accent, self::CARD_MIX, self::CARD_MIN_LUM );
	}

	/** Surface des CHAMPS de saisie — 5ᵉ et dernier cran, le point le plus clair. */
	public static function field( $accent = null ) {
		return self::surface( $accent, self::FIELD_MIX, self::FIELD_MIN_LUM );
	}

	/**
	 * Aplat d'accent posé DANS une carte (préréglage, interrupteur armé, pastille).
	 *
	 * 🚨 HORS DE L'ÉCHELLE, ET SANS PLANCHER — c'est ce qui le distingue de
	 * `wash()`. Son contrat est de porter `--cd-ink`, jamais `--cd-muted` : lui
	 * donner un plancher le rendrait sûr sous du texte secondaire et les deux
	 * tokens deviendraient des doublons. `test-contraste.php` garde la frontière en
	 * éprouvant qu'il ÉCHOUE sous `--cd-muted`.
	 *
	 * 🔁 Exposé le 2026-08-06 parce que le test le RECOMPOSAIT (`mix(noir, 0.86)`).
	 * Le taux ayant bougé, il mesurait une couleur morte et rougissait pour une
	 * raison inventée — le troisième cas du même patron en une journée. Un token
	 * dérivé doit avoir un accesseur, sinon quelqu'un finira par recopier sa formule.
	 *
	 * @param string|null $accent Accent à dériver (défaut : celui du site).
	 * @return string Hex.
	 */
	public static function soft( $accent = null ) {
		$accent = ( null === $accent ) ? self::accent() : $accent;
		return self::to_hex( self::mix( self::rgb( $accent ), 255, self::SOFT_MIX ) );
	}

	/**
	 * PREMIER TON du fond de l'application (`--cd-page`). Cf. `ton()`.
	 *
	 * @param string|null $accent Accent à dériver. Par défaut celui du site — le
	 *                            paramètre existe pour que les tests passent par le
	 *                            vrai code plutôt que par une copie de la formule.
	 * @return string Hex.
	 */
	public static function page( $accent = null ) {
		return self::ton( ( null === $accent ) ? self::accent() : $accent, self::PAGE_SAT, self::PAGE_LUM, 0.0 );
	}

	/**
	 * SECOND TON du fond (`--cd-page-2`) — l'autre extrémité du dégradé, teinte
	 * inclinée vers le froid (cf. `PAGE2_HUE`).
	 *
	 * ⚠️ Ce n'est PAS un cran de plus dans l'échelle de surfaces : il ne s'empile
	 * avec rien, il ne porte aucune carte, il vit au même rang que `page()` et sur
	 * la même règle. La monotonie des cinq crans (R2 de `test-fond-accent.php`) ne
	 * le concerne donc pas — sa lisibilité, si.
	 *
	 * @param string|null $accent Accent à dériver (défaut : celui du site).
	 * @return string Hex.
	 */
	public static function page2( $accent = null ) {
		return self::ton( ( null === $accent ) ? self::accent() : $accent, self::PAGE2_SAT, self::PAGE2_LUM, self::PAGE2_HUE );
	}

	/**
	 * UN TON du fond d'application : la TEINTE de l'accent — éventuellement
	 * inclinée vers le froid — portée à la saturation et à la clarté du FOND, puis
	 * relevée par paliers jusqu'au plancher de lisibilité.
	 *
	 * 🚨 IL NE PASSE PAS PAR `surface()`, ET C'EST LA DIFFÉRENCE DE FOND (cf.
	 * `PAGE_SAT`). Les cinq crans de l'échelle sont l'accent MÉLANGÉ vers le blanc :
	 * ils héritent donc de sa clarté et de sa vivacité, ce qui est juste pour une
	 * carte ou un champ — des surfaces qui doivent rester DISCRÈTES quel que soit
	 * l'accent. Les deux tons du fond, eux, sont le sujet visuel de l'écran : ils
	 * doivent avoir la même présence chez tout le monde. Un mélange à taux fixe ne
	 * peut pas le donner, une clarté écrite si.
	 *
	 * 🚨 LE PLANCHER RESTE, ET IL SERT. On pourrait croire qu'une clarté HSL
	 * fixée garantit la lisibilité : elle ne la garantit pas, parce que la luminance
	 * RELATIVE d'une couleur dépend de sa teinte à clarté HSL égale — un bleu à
	 * L=0,79 est bien plus sombre qu'un jaune à L=0,79. Mesuré : le plancher se
	 * déclenche sur le second ton du rouge, du bleu, du bordeaux et du presque-noir.
	 *
	 * ⚠️ Le relèvement se fait PAR PALIERS vers le blanc, comme `side()` le fait
	 * vers le noir : éclaircir fait croître la luminance de façon monotone, donc le
	 * premier palier qui passe est aussi celui qui préserve le mieux la teinte.
	 * Résoudre l'équation d'un coup donnerait un gris. Le compteur borne la boucle ;
	 * le blanc finit toujours par passer.
	 *
	 * ⚠️ LA ROTATION PASSE PAR `cooler()` ET NON PAR UNE COPIE DE SA RÈGLE. Le sens
	 * de rotation (le plus court chemin vers `COOL_HUE`) est une décision qui doit
	 * vivre à UN endroit : la réécrire ici en ferait deux, qui divergeraient au
	 * premier ajustement.
	 *
	 * @param string $accent Accent source.
	 * @param float  $sat    Saturation HSL du ton.
	 * @param float  $lum    Clarté HSL du ton.
	 * @param float  $rot    Rotation vers le froid, en degrés (0 = aucune).
	 * @return string Hex.
	 */
	protected static function ton( $accent, $sat, $lum, $rot ) {
		$rgb = self::rgb( $accent );
		$hsl = self::to_hsl( $rgb );

		if ( $hsl[1] <= 0.0 ) {
			// Accent gris : pas de teinte à inventer — même refus que `cooler()`. Le ton
			// reste neutre, à la clarté du fond, et le dégradé devient un balayage de
			// clarté pure. C'est le cas témoin de R5bis.
			$out = array( $lum * 255.0, $lum * 255.0, $lum * 255.0 );
		} else {
			$teinte = ( $rot > 0.0 ) ? self::to_hsl( self::cooler( $rgb, $rot ) ) : $hsl;
			$out    = self::from_hsl( array( $teinte[0], $sat, $lum ) );
		}

		for ( $i = 0; $i < 40 && self::luminance( $out ) < self::PAGE_MIN_LUM; $i++ ) {
			$out = self::mix( $out, 255, 0.03 );
		}

		return self::to_hex( $out );
	}

	/**
	 * Bloc `:root{…}` à injecter en ligne : accent + variantes dérivées.
	 * Toutes les valeurs sortent de sanitize_hex_color / de notre propre
	 * formatage hexadécimal → sûres à imprimer sans échappement supplémentaire.
	 *
	 * @return string CSS.
	 */
	public static function css_vars() {
		$hex = self::accent();
		$rgb = self::rgb( $hex );
		$lum = self::luminance( $rgb );

		// Encre lisible sur l'accent : on retient, entre noir et blanc, celle qui
		// contraste le mieux (ratio WCAG) — robuste sur les tons moyens (verts…)
		// qu'un simple seuil de luminance classerait à tort.
		$ink = self::best_ink( $lum );

		// Variante « survol / renforcée » : on assombrit un accent clair, on
		// éclaircit un accent déjà foncé — pour garder un écart visible.
		$strong = ( $lum > 0.20 )
			? self::to_hex( self::mix( $rgb, 0, 0.12 ) )
			: self::to_hex( self::mix( $rgb, 255, 0.18 ) );

		// Encre lisible SUR `$strong` — ajoutée le 2026-08-05, et c'est ce qui rendait
		// `--cd-accent-strong` inutilisable.
		//
		// 🚨 `test-charte-surfaces.php` consignait le problème sans le nommer : « le
		// survol des surfaces d'accent ne se sert pas de la couleur prévue pour lui »,
		// `--cd-accent-strong` calculé à chaque page et lu par ZÉRO règle. La raison
		// n'était pas l'oubli mais un PIÈGE : réutiliser `--cd-accent-ink`, calculée
		// pour l'accent de BASE, casse AA sur la variante.
		//
		// MESURÉ, et le résultat est pire que prévu — 4 accents sur 8 tombent sous AA
		// si on réutilise l'encre de base (ratio sur `$strong`, seuil 4,5) :
		//
		//   turquoise #0d9488 → 4,48 ❌ → 4,69 ✅   (l'accent choisi par CE client)
		//   rouge pur #ff0000 → 4,17 ❌ → 5,04 ✅
		//   vert  #2f6b3a     → 4,25 ❌ → 4,95 ✅
		//   bleu-gris #4a5a8a → 4,36 ❌ → 4,81 ✅
		//   jaune, bleu, bordeaux, presque-noir : inchangés, déjà larges.
		//
		// La cause est mécanique : `best_ink()` bascule du noir au blanc vers une
		// luminance de ~0,179, et `$strong` DÉPLACE la luminance — de 12 % vers le noir
		// pour un accent clair, de 18 % vers le blanc pour un accent sombre. La
		// variante peut donc franchir le seuil que la base n'avait pas franchi, et
		// l'encre de la base devient le mauvais choix. Le défaut ne se voyait qu'AU
		// SURVOL : aucune capture d'écran au repos ne pouvait le montrer.
		//
		// D'où une encre propre, choisie par la MÊME fonction sur la luminance de
		// `$strong` : la variante est libre de basculer d'encre quand la base ne le
		// fait pas. C'est ce qui rend le token enfin consommable (cf. `dashboard.css`,
		// `.aod-cd-btn-primary:hover`).
		$strong_ink = self::best_ink( self::luminance( self::rgb( $strong ) ) );

		// Teinte douce (fonds discrets, anneaux de focus légers). Calibrée pour
		// porter `--cd-ink` : ne PAS l'utiliser sous du `--cd-muted` — cf. wash().
		// 🔁 0.86 → 0.74 le 2026-08-06. Ce n'est pas un ajustement d'humeur : à 0.86 la
		// teinte douce tombait à un cheveu de `--cd-card` (0.87), soit deux tokens
		// vivants pour une même valeur — le mode d'échec nommé en 46.4. Elle descend
		// donc AVEC l'échelle, et redevient ce qu'elle est : un aplat d'accent posé
		// DANS une carte (préréglage, interrupteur armé, pastille d'une tuile à zéro),
		// pas une nuance de la carte elle-même.
		// 🔒 Elle reste HORS de l'échelle et sans plancher, à dessein : cf. soft().
		$soft = self::soft( $hex );

		// Surface teintée portant du texte, y compris `--cd-muted`. Cf. wash().
		$wash = self::wash( $hex );

		// Les cinq crans de l'échelle de surfaces, du plus profond au plus clair.
		// ⚠️ Tous passent par `surface()` : le mélange NU serait faux pour un accent
		// sombre (sous AA), et une copie de la formule ici ne verrait pas le plancher.
		$page  = self::page( $hex );
		$page2 = self::page2( $hex );
		$bg    = self::bg( $hex );
		$card  = self::card( $hex );
		$field = self::field( $hex );

		// Fond de la sidebar, teinté de l'accent (étape 15.5). Cf. side().
		$side = self::side();

		// Accent posé EN TEXTE sur nos surfaces sombres. C'est le pendant de `$ink` —
		// et le piège inverse : l'accent par défaut est un jaune vif, donc lisible, ce
		// qui masque le problème. Un accent moyen y tombe sous AA (un bleu de marque
		// n'y donne que 4,2:1) et un accent foncé y serait carrément invisible. On
		// l'éclaircit vers le blanc juste assez pour atteindre AA : la teinte reste
		// reconnaissable, et un accent déjà clair n'est pas touché.
		//
		// 🚨 Ces surfaces sont maintenant DEUX, et pas la même selon l'endroit : la
		// sidebar est dérivée de l'accent, tandis que la pastille de langue active et
		// la pagination active restent sur le neutre. Une seule variable les sert
		// toutes : on la calibre donc sur la PLUS CLAIRE des deux, celle où l'accent
		// contraste le moins — passer là garantit de passer sur l'autre.
		//
		// Et non, la sidebar n'est pas toujours la plus claire : mesuré sur le cube
		// RGB, 72 accents sur 4 096 (1,8 %, tous très sombres) donnent une sidebar
		// dérivée PLUS sombre que le neutre. Choisir « la sidebar » en dur aurait
		// sous-calibré ces cas-là sans que rien ne le signale.
		$neutral  = self::rgb( self::NEUTRAL_DARK );
		$side_rgb = self::rgb( $side );
		$lightest = ( self::luminance( $side_rgb ) >= self::luminance( $neutral ) ) ? $side_rgb : $neutral;
		$on_dark  = self::readable_on( $rgb, $lightest );

		return ':root{'
			. '--cd-accent:' . $hex . ';'
			. '--cd-accent-rgb:' . implode( ',', array_map( 'intval', $rgb ) ) . ';'
			. '--cd-accent-strong:' . $strong . ';'
			. '--cd-accent-strong-ink:' . $strong_ink . ';'
			. '--cd-accent-ink:' . $ink . ';'
			. '--cd-accent-soft:' . $soft . ';'
			. '--cd-accent-wash:' . $wash . ';'
			. '--cd-bg:' . $bg . ';'
			. '--cd-page:' . $page . ';'
			. '--cd-page-2:' . $page2 . ';'
			. '--cd-card:' . $card . ';'
			. '--cd-field:' . $field . ';'
			. '--cd-accent-on-dark:' . $on_dark . ';'
			. '--cd-side:' . $side . ';'
			. '}';
	}

	/* ----------------------------------------------------------------- *
	 * Petites fonctions couleur (sans dépendance)                        *
	 * ----------------------------------------------------------------- */

	/** #rrggbb (ou #rgb) → array(r,g,b) entiers 0..255. */
	protected static function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/** array(r,g,b) → #rrggbb, valeurs bornées. */
	protected static function to_hex( $rgb ) {
		return sprintf(
			'#%02x%02x%02x',
			max( 0, min( 255, (int) round( $rgb[0] ) ) ),
			max( 0, min( 255, (int) round( $rgb[1] ) ) ),
			max( 0, min( 255, (int) round( $rgb[2] ) ) )
		);
	}

	/**
	 * Encre (noir vs blanc) qui contraste le mieux avec une luminance donnée.
	 * Ratio WCAG = (Lclair+0.05)/(Lfoncé+0.05) ; on garde l'encre au plus fort ratio.
	 *
	 * L'encre foncée est le NOIR PUR, et non notre `--cd-ink` (#181818) : le client
	 * choisit son accent librement, la garantie doit donc tenir pour TOUTE couleur
	 * du cube. Avec #181818, le meilleur des deux encres tombe à 4,21:1 dans le pire
	 * cas (#1878f0, un bleu de marque banal ; le rouge pur plafonne à 4,44:1) — sous
	 * le seuil AA de 4,5. Avec le noir pur, le plancher mathématique du « meilleur des
	 * deux » remonte à 4,58:1 : plus aucun accent ne peut produire un bouton illisible.
	 * (Cette encre ne sert QUE de couleur de texte posée sur un fond d'accent.)
	 *
	 * @param float $lum Luminance relative de l'accent (0..1).
	 * @return string Hex de l'encre.
	 */
	protected static function best_ink( $lum ) {
		$c_wht = ( 1.0 + 0.05 ) / ( $lum + 0.05 );
		$c_blk = ( $lum + 0.05 ) / 0.05;
		return ( $c_blk >= $c_wht ) ? '#000000' : '#ffffff';
	}

	/**
	 * Éclaircit une couleur vers le blanc jusqu'à ce qu'elle atteigne AA sur un
	 * fond SOMBRE donné. Le fond étant sombre, éclaircir augmente le contraste de
	 * façon monotone : le premier palier qui passe est aussi le plus fidèle à la
	 * teinte d'origine. Une couleur déjà lisible ressort inchangée.
	 *
	 * @param array $rgb Couleur à ajuster.
	 * @param array $bg  Fond sombre sur lequel elle sera posée.
	 * @return string Hex.
	 */
	protected static function readable_on( $rgb, $bg ) {
		$l_bg   = self::luminance( $bg );
		$target = 4.6; // Petite marge au-dessus du seuil AA (4.5).

		for ( $i = 0; $i <= 20; $i++ ) {
			$try   = self::mix( $rgb, 255, $i * 0.05 );
			$l     = self::luminance( array_map( 'round', $try ) );
			$ratio = ( max( $l, $l_bg ) + 0.05 ) / ( min( $l, $l_bg ) + 0.05 );
			if ( $ratio >= $target ) {
				return self::to_hex( $try );
			}
		}
		return '#ffffff'; // Inatteignable en pratique (le blanc passe toujours).
	}

	/**
	 * Incline une couleur vers le côté FROID de la roue, à saturation et clarté
	 * constantes (cf. COOL_HUE / PAGE2_HUE).
	 *
	 * On prend l'arc le PLUS COURT vers la référence froide, et on tourne de
	 * `$deg` dans ce sens — quitte à la dépasser. Le dépassement est voulu : sans
	 * lui, un accent déjà bleu s'arrêterait sur place et le « dégradé en deux
	 * couleurs » n'aurait, chez lui seul, qu'une couleur. Un accent PILE sur la
	 * référence (arc nul) part vers le violet, par convention — il faut bien
	 * trancher, et le résultat vaut mieux qu'un fond plat.
	 *
	 * @param array $rgb Couleur de départ, array(r,g,b).
	 * @param float $deg Amplitude de la rotation, en degrés.
	 * @return array Composantes non arrondies.
	 */
	protected static function cooler( $rgb, $deg ) {
		$hsl = self::to_hsl( $rgb );

		// Un gris n'a pas de teinte : la faire tourner reviendrait à lui en
		// inventer une, donc à choisir une couleur à la place du client.
		if ( $hsl[1] <= 0.0 ) {
			return $rgb;
		}

		$vers   = fmod( self::COOL_HUE - $hsl[0] + 360.0, 360.0 );
		$sens   = ( $vers > 180.0 ) ? -1.0 : 1.0;
		$hsl[0] = fmod( $hsl[0] + $sens * $deg + 360.0, 360.0 );

		return self::from_hsl( $hsl );
	}

	/**
	 * array(r,g,b) 0..255 → array(teinte 0..360, saturation 0..1, clarté 0..1).
	 *
	 * HSL et non HSV : `ton()` a besoin de lire une TEINTE et d'écrire une
	 * saturation et une clarté séparément — c'est exactement le repère HSL, et
	 * c'est ce qui rend le fond indépendant de la vivacité de l'accent.
	 *
	 * @param array $rgb Couleur.
	 * @return array
	 */
	protected static function to_hsl( $rgb ) {
		$r = $rgb[0] / 255;
		$g = $rgb[1] / 255;
		$b = $rgb[2] / 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$lum = ( $max + $min ) / 2;
		$d   = $max - $min;

		if ( $d <= 0.0 ) {
			return array( 0.0, 0.0, $lum );
		}

		$sat = ( $lum > 0.5 ) ? $d / ( 2.0 - $max - $min ) : $d / ( $max + $min );

		if ( $max === $r ) {
			$hue = fmod( ( $g - $b ) / $d, 6.0 );
		} elseif ( $max === $g ) {
			$hue = ( $b - $r ) / $d + 2.0;
		} else {
			$hue = ( $r - $g ) / $d + 4.0;
		}

		return array( fmod( $hue * 60.0 + 360.0, 360.0 ), $sat, $lum );
	}

	/**
	 * Inverse de to_hsl() : array(h,s,l) → array(r,g,b) 0..255 non arrondis.
	 *
	 * @param array $hsl Teinte 0..360, saturation 0..1, clarté 0..1.
	 * @return array
	 */
	protected static function from_hsl( $hsl ) {
		list( $hue, $sat, $lum ) = $hsl;

		if ( $sat <= 0.0 ) {
			return array( $lum * 255, $lum * 255, $lum * 255 );
		}

		$c = ( 1.0 - abs( 2.0 * $lum - 1.0 ) ) * $sat;
		$x = $c * ( 1.0 - abs( fmod( $hue / 60.0, 2.0 ) - 1.0 ) );
		$m = $lum - $c / 2.0;

		if ( $hue < 60.0 ) {
			$out = array( $c, $x, 0.0 );
		} elseif ( $hue < 120.0 ) {
			$out = array( $x, $c, 0.0 );
		} elseif ( $hue < 180.0 ) {
			$out = array( 0.0, $c, $x );
		} elseif ( $hue < 240.0 ) {
			$out = array( 0.0, $x, $c );
		} elseif ( $hue < 300.0 ) {
			$out = array( $x, 0.0, $c );
		} else {
			$out = array( $c, 0.0, $x );
		}

		return array( ( $out[0] + $m ) * 255, ( $out[1] + $m ) * 255, ( $out[2] + $m ) * 255 );
	}

	/** Luminance relative sRGB (0..1), pour choisir l'encre par contraste. */
	protected static function luminance( $rgb ) {
		$lin = array();
		foreach ( $rgb as $v ) {
			$v     = $v / 255;
			$lin[] = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
	}

	/**
	 * Mélange linéaire de $rgb vers une cible, à hauteur de $amt (0..1).
	 *
	 * La cible accepte un SCALAIRE achromatique (0 = noir, 255 = blanc — la forme
	 * historique, utilisée par `$strong`, `$soft` et `readable_on()`) ou un
	 * array(r,g,b) : la sidebar dérivée mélange vers `NEUTRAL_DARK`, qui se trouve
	 * être gris mais n'a aucune raison de le rester si la coque évolue.
	 *
	 * @param array     $rgb    Couleur de départ.
	 * @param array|int $target Cible du mélange.
	 * @param float     $amt    Part de la cible (0 = inchangé, 1 = cible pure).
	 * @return array Composantes non arrondies.
	 */
	protected static function mix( $rgb, $target, $amt ) {
		$t   = is_array( $target ) ? $target : array( $target, $target, $target );
		$out = array();
		foreach ( array( 0, 1, 2 ) as $i ) {
			$out[ $i ] = $rgb[ $i ] + ( $t[ $i ] - $rgb[ $i ] ) * $amt;
		}
		return $out;
	}
}
