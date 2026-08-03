<?php
/**
 * Réglage « Afficher sur l'accueil » + ordre par catégorie — étape 9.1.
 *
 * On prouve la DÉCISION de persistance, pas l'écriture en base. `ajax_save_category()`
 * délègue à `AOD_CD_Section_Products::home_meta_ops( $req )` (méthode pure, sans nonce,
 * sans WP), qui répond quoi faire des deux term-metas selon la requête :
 *
 *   { home: 'set'|'delete'|'leave', order: int|null }
 *
 * 🔴 Le cas CENTRAL est « home absent → leave » : il distingue un enregistrement d'IMAGE
 * (qui ne poste pas `home` → on ne touche à rien) d'un DÉCOCHAGE (`home = '0'` → delete).
 * C'est ce qui rend le piège de la 7.5 (changer une image effaçait la valeur)
 * structurellement impossible ici — le patron `parent` (« absent = intact »), pas le
 * patron `name_ar` (« écriture inconditionnelle »).
 *
 * Tests DISCRIMINANTS (leçon 7.3/7.4) : sans le `isset()` de D2, le cas « absent » rendrait
 * 'delete' au lieu de 'leave' — chaque assertion tomberait sur un code faux.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Accueil 9.1 — le flag « à la une » : set / delete / leave' );

AOD_Test::eq(
	'set',
	AOD_CD_Section_Products::home_meta_ops( array( 'home' => '1' ) )['home'],
	'`home = "1"` (case cochée) → on ÉCRIT la méta'
);

AOD_Test::eq(
	'delete',
	AOD_CD_Section_Products::home_meta_ops( array( 'home' => '0' ) )['home'],
	'`home = "0"` (case décochée, présente) → on SUPPRIME la méta (jamais de "0" stocké)'
);

AOD_Test::eq(
	'delete',
	AOD_CD_Section_Products::home_meta_ops( array( 'home' => '' ) )['home'],
	'`home = ""` (présent mais vide) → on SUPPRIME la méta'
);

AOD_Test::eq(
	'leave',
	AOD_CD_Section_Products::home_meta_ops( array() )['home'],
	'🔴 `home` ABSENT (enregistrement d\'image) → on NE TOUCHE À RIEN',
	'Le garde-fou de l\'étape. Sans le isset() de D2, ce cas rendrait "delete" — changer une image effacerait le flag (le bug 7.5-AC12 rejoué).'
);

AOD_Test::group( 'Accueil 9.1 — l\'ordre : entier borné, ou leave si absent' );

AOD_Test::eq(
	3,
	AOD_CD_Section_Products::home_meta_ops( array( 'home_order' => '3' ) )['order'],
	'`home_order = "3"` → 3 (entier)'
);

AOD_Test::eq(
	0,
	AOD_CD_Section_Products::home_meta_ops( array( 'home_order' => '-5' ) )['order'],
	'`home_order = "-5"` → 0 (borné à ≥ 0, jamais de négatif)'
);

AOD_Test::eq(
	0,
	AOD_CD_Section_Products::home_meta_ops( array( 'home_order' => '' ) )['order'],
	'`home_order = ""` (présent) → 0'
);

AOD_Test::ok(
	null === AOD_CD_Section_Products::home_meta_ops( array() )['order'],
	'`home_order` ABSENT → null (= laisser la méta d\'ordre intacte)',
	'Comme le flag : un enregistrement d\'image ne poste pas l\'ordre, il ne doit pas le réécrire.'
);

AOD_Test::group( 'Accueil 9.1 — les deux champs sont indépendants' );

AOD_Test::ok(
	'set' === AOD_CD_Section_Products::home_meta_ops( array( 'home' => '1' ) )['home']
	&& null === AOD_CD_Section_Products::home_meta_ops( array( 'home' => '1' ) )['order'],
	'`home` seul (sans `home_order`) → flag écrit, ordre laissé intact',
	'Le flag et l\'ordre ne se conditionnent pas l\'un l\'autre.'
);

AOD_Test::ok(
	'leave' === AOD_CD_Section_Products::home_meta_ops( array( 'home_order' => '2' ) )['home']
	&& 2 === AOD_CD_Section_Products::home_meta_ops( array( 'home_order' => '2' ) )['order'],
	'`home_order` seul (sans `home`) → ordre écrit, flag laissé intact'
);
