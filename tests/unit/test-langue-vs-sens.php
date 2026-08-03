<?php
/**
 * « Le site est-il en arabe ? » — la langue, jamais le sens d'écriture (dette D10).
 *
 * `AOD_COD_Data::is_arabic()` commande beaucoup de monde : les 58 wilayas et les
 * 1541 communes du formulaire COD, les noms de catégories, les variantes, et tout
 * le contenu marchand de la page de vente (`AOD_COD_Landing::t()`). Elle s'écrivait
 * `( 0 === strpos( get_locale(), 'ar' ) ) || is_rtl()`.
 *
 * Le raccourci tenait tant que RTL ⇔ arabe. Mais `is_rtl()` ne répond pas à la
 * question posée : il dit « le rendu va de droite à gauche », pas « le visiteur lit
 * l'arabe ». TEMPLATE.md §4 n'interdit aucun thème RTL à un clone francophone — le
 * jour où l'owner en dépose un, tout le contenu bascule en arabe sur un site en
 * français, sans qu'aucune option ne l'ait demandé.
 *
 * 🚨 CE QUE CE FICHIER GARDE, ET POURQUOI IL EN FAUT UN. Le défaut était LATENT :
 * aucun clone n'avait encore réuni la condition, donc aucune porte de recette ne
 * pouvait le voir — elles mesurent ce que le clone affiche, et ce clone-ci est en
 * LTR. Un défaut qu'aucune mesure ne peut atteindre dans l'état courant ne se garde
 * qu'en FABRIQUANT l'état, ce que fait R1 ci-dessous.
 *
 * ⚠️ ET IL GARDE AUSSI L'INVERSE, qui est le piège de ce correctif. Retirer
 * `|| is_rtl()` sèchement aurait cassé un cas réel : dans le wp-admin, la locale du
 * SITE et celle du PROFIL sont deux choses différentes, et `AOD_COD_Lang` se retire
 * volontairement de l'admin. Un administrateur au profil arabe ne devait donc son
 * arabe qu'à `is_rtl()`. R2 exige qu'il le garde — pour la bonne raison, cette fois.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exécute `$fn` avec le rendu forcé en RTL, puis restaure — quoi qu'il arrive.
 *
 * On agit sur `$wp_locale->text_direction`, la source unique que `is_rtl()` lit.
 * Le `finally` n'est pas décoratif : un test qui laisserait le globals en RTL
 * ferait passer les ~60 fichiers chargés APRÈS lui dans un contexte faussé, et
 * l'échec apparaîtrait n'importe où sauf ici.
 *
 * @param callable $fn Test à exécuter en rendu RTL.
 * @return mixed
 */
function aod_test_in_rtl( $fn ) {
	global $wp_locale;
	$avant = $wp_locale->text_direction;
	$wp_locale->text_direction = 'rtl';
	try {
		return $fn();
	} finally {
		$wp_locale->text_direction = $avant;
	}
}

AOD_Test::group( 'D10 — R1 : un thème RTL ne rend pas un site francophone arabe' );

// Ancrage. Sans lui, R1 pourrait être vert parce que le harnais est cassé.
AOD_Test::ok( ! is_rtl(), 'Ancrage : le clone est en LTR au repos', 'sinon les deux contrôles suivants ne prouvent rien' );
AOD_Test::ok( ! AOD_COD_Data::is_arabic(), 'Ancrage : au repos (site fr_FR), is_arabic() est faux' );

aod_test_in_rtl(
	function () {
		AOD_Test::ok( is_rtl(), 'Le rendu est bien basculé en RTL (le harnais fait ce qu\'il annonce)' );
		AOD_Test::ok(
			! AOD_COD_Data::is_arabic(),
			'Thème RTL sur site FRANÇAIS : is_arabic() reste FAUX',
			'c\'est la dette D10 : `|| is_rtl()` faisait basculer wilayas, communes et page de vente en arabe sur un site en français'
		);
		// Le contenu marchand suit la même garde : on le vérifie de bout en bout,
		// pas seulement sur le prédicat — c'est lui que le visiteur lit.
		AOD_Test::eq(
			'Chaise',
			AOD_COD_Data::label( 'Chaise', 'كرسي' ),
			'Et le libellé rendu reste le français, arabe disponible ou non'
		);
	}
);

AOD_Test::group( 'D10 — R2 : la langue, elle, commande toujours' );

AOD_Test::in_arabic(
	function () {
		AOD_Test::ok( AOD_COD_Data::is_arabic(), 'Site en arabe, thème LTR : is_arabic() est VRAI' );
		AOD_Test::eq( 'كرسي', AOD_COD_Data::label( 'Chaise', 'كرسي' ), 'Le libellé arabe est rendu' );
	}
);

// Le cas qui interdit de « simplement retirer le || is_rtl() » : les deux à la fois.
AOD_Test::in_arabic(
	function () {
		aod_test_in_rtl(
			function () {
				AOD_Test::ok( AOD_COD_Data::is_arabic(), 'Site en arabe ET thème RTL (le cas normal) : toujours VRAI' );
			}
		);
	}
);

AOD_Test::group( 'D10 — R3 : un seul corps pour la règle' );

// 🚨 R1 et R2 mesurent le COMPORTEMENT ; R3 garde la STRUCTURE. Les deux sont
// nécessaires : `AOD_COD_Form::is_arabic()` portait sa propre copie de la règle et
// avait divergé — elle lisait la langue quand l'autre y ajoutait `is_rtl()`. Deux
// réponses possibles à la même question sur la même page. Le comportement seul ne
// l'aurait pas vu, puisque chacune était juste... de son côté.
$src_data = file_get_contents( WP_PLUGIN_DIR . '/aod-cod-form/includes/class-aod-cod-data.php' );
$src_form = file_get_contents( WP_PLUGIN_DIR . '/aod-cod-form/includes/class-aod-cod-form.php' );

AOD_Test::ok( is_string( $src_data ) && '' !== $src_data, 'Ancrage : la source de AOD_COD_Data est lisible' );
AOD_Test::ok( is_string( $src_form ) && '' !== $src_form, 'Ancrage : la source de AOD_COD_Form est lisible' );

// On cherche un appel à is_rtl() dans le CORPS de la méthode, pas dans son commentaire :
// le correctif explique justement pourquoi `is_rtl()` a été retiré, et une règle qui
// rougirait sur sa propre explication serait désarmée le jour même (leçon D16).
$corps = '';
if ( preg_match( '/function\s+is_arabic\s*\([^)]*\)\s*\{(.*?)\n\t\}/s', $src_data, $m ) ) {
	$corps = $m[1];
}
AOD_Test::ok( '' !== $corps, 'Ancrage : le corps de AOD_COD_Data::is_arabic() a été isolé', 'si la regex ne trouve plus rien, la règle suivante est vide de sens' );
AOD_Test::ok(
	false === strpos( $corps, 'is_rtl' ),
	'AOD_COD_Data::is_arabic() n\'appelle pas is_rtl()',
	'la question posée est « quelle langue », pas « quel sens d\'écriture »'
);

AOD_Test::ok(
	1 === preg_match( '/function\s+is_arabic[^{]*\{\s*return\s+AOD_COD_Data::is_arabic\(\s*\);\s*\}/', $src_form ),
	'AOD_COD_Form::is_arabic() DÉLÈGUE au lieu de recopier la règle',
	'sinon les deux corps redivergent, et rien ne le signalera'
);

// 🚨 CE QUI SUIT EST STRUCTUREL FAUTE DE POUVOIR ÊTRE COMPORTEMENTAL, et il vaut
// mieux le dire que le laisser croire. Le cas « administrateur au profil arabe sur
// un site français » est justement celui que le correctif devait PRÉSERVER — le
// retirer serait une régression silencieuse, pas une simplification. Or il ne se
// joue que si `is_admin()` est vrai, c'est-à-dire si la constante `WP_ADMIN` est
// définie ; ces tests tournent sous WP-CLI, où elle ne l'est pas et ne peut pas
// l'être après coup. Le red-proof l'a montré : supprimer la branche admin ne fait
// rougir AUCUN des contrôles de comportement ci-dessus. On garde donc ce qu'on
// peut atteindre — la branche elle-même — plutôt que rien.
AOD_Test::ok(
	false !== strpos( $corps, 'get_user_locale' ) && false !== strpos( $corps, 'is_admin' ),
	'La branche admin (profil arabe, site français) est toujours là',
	'c\'est le service que rendait `|| is_rtl()` ; le retirer serait une régression, pas un nettoyage'
);
AOD_Test::ok(
	false !== strpos( $corps, 'wp_doing_ajax' ),
	'…et elle EXCLUT admin-ajax, comme AOD_COD_Lang::filter_locale()',
	'sans quoi une commande COD passée en arabe verrait ses libellés repasser en français : admin-ajax n\'est pas l\'admin, c\'est le visiteur'
);
