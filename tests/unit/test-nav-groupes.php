<?php
/**
 * Cohérence des domaines de navigation de `/gestion` (`AOD_CD_Dashboard::nav_groups()`).
 *
 * LE DÉFAUT D'ORIGINE. Le domaine « Livraison » ne contenait qu'une section, elle
 * aussi nommée « Livraison ». La barre latérale affiche un intitulé de groupe PUIS
 * ses liens : le gérant lisait donc « Livraison » sur deux lignes qui se touchent,
 * sans que la seconde apprenne quoi que ce soit de plus que la première.
 *
 * 🚨 CE QUI REND LE CAS INTÉRESSANT — le correctif existait déjà, à moitié. Le fil
 * d'ariane portait depuis longtemps un test `$group_label !== $page_label`, avec en
 * commentaire l'exemple EXACT du défaut (« Gestion › Livraison › Livraison »).
 * Autrement dit : le problème était connu, écrit, et traité — dans UN des deux
 * endroits où il se voit. Le menu, qui est celui que le gérant regarde en
 * permanence, n'a jamais reçu la contrepartie. Même famille que la Phase 46 (« une
 * règle écrite deux fois ne tient pas ») et que la Phase 47 (« une décision
 * propagée à un jumeau et pas à l'autre »).
 *
 * La cause est traitée à la racine : le domaine s'appelle « Expédition » et
 * accueille `tracking`. Ce fichier est le cliquet qui empêche la dérive de revenir —
 * parce qu'une rustine au rendu, elle, ne l'empêchait pas.
 *
 * 🚨 CE QUE CE TEST NE PROUVE PAS. Il lit une TABLE, pas un écran. Il ne verra ni un
 * rail trop haut, ni un intitulé illisible, ni un ordre de sections déroutant — ça,
 * c'est `bash tools/recette/run.sh` et l'œil. Il garantit une seule chose, mais il
 * la garantit toujours : aucun domaine ne peut redevenir le doublon ou le contenant
 * vide de sa propre section.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Navigation /gestion — cohérence des domaines' );

/*
 * Les trois méthodes sont `protected` : elles décrivent une structure interne, pas
 * une API. On les lit par réflexion plutôt que de les rendre publiques pour le
 * confort d'un test — ouvrir une méthode pour la tester, c'est changer le sujet.
 */
$aod_ng_dash = AOD_CD_Dashboard::instance();
$aod_ng_call = function ( $method ) use ( $aod_ng_dash ) {
	$m = new ReflectionMethod( 'AOD_CD_Dashboard', $method );
	$m->setAccessible( true );
	return $m->invoke( $aod_ng_dash );
};

/*
 * `hidden_sections()` ne rend son contenu qu'à un gérant (`manage_woocommerce`) —
 * or `wp eval-file` tourne sans utilisateur courant. Sans cette bascule, « landing »
 * serait vu comme un slug inconnu et le test rougirait sur du code parfaitement sain.
 * On restaure l'utilisateur d'origine juste après : les fichiers de `tests/unit/`
 * partagent un seul processus, et un test qui laisse un administrateur connecté
 * derrière lui fausse silencieusement ceux qui suivent.
 */
$aod_ng_user_avant = get_current_user_id();
$aod_ng_admins     = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( $aod_ng_admins ) {
	wp_set_current_user( (int) $aod_ng_admins[0] );
}

$aod_ng_groups   = $aod_ng_call( 'nav_groups' );
$aod_ng_sections = $aod_ng_call( 'sections' );
$aod_ng_hidden   = $aod_ng_call( 'hidden_sections' );

wp_set_current_user( $aod_ng_user_avant );

AOD_Test::ok(
	! empty( $aod_ng_hidden ),
	'TÉMOIN : la bascule gérant a bien rendu les sections masquées (« Page de vente »)',
	'sans elle les contrôles ci-dessous seraient verts pour la mauvaise raison — ils ne verraient qu’un sous-ensemble'
);

$aod_ng_routable = $aod_ng_sections + $aod_ng_hidden;

/* ---------------------------------------------------------------------------
 * R1 — LE DÉFAUT D'ORIGINE : un domaine ne porte jamais le nom d'un de ses enfants.
 * ------------------------------------------------------------------------- */
$aod_ng_doublons = array();
foreach ( $aod_ng_groups as $aod_ng_slug => $aod_ng_g ) {
	foreach ( $aod_ng_g[1] as $aod_ng_sec ) {
		if ( isset( $aod_ng_sections[ $aod_ng_sec ] ) && $aod_ng_sections[ $aod_ng_sec ][0] === $aod_ng_g[0] ) {
			$aod_ng_doublons[] = "{$aod_ng_slug} › {$aod_ng_sec} (« {$aod_ng_g[0]} »)";
		}
	}
}
AOD_Test::eq(
	array(),
	$aod_ng_doublons,
	'🔴 R1 — aucun intitulé de domaine n’est identique au libellé d’une de ses sections'
);

/* ---------------------------------------------------------------------------
 * R2 — Aucune section orpheline. Une section absente de `nav_groups()` n’a PAS de
 * ligne dans le menu : le rendu boucle sur les domaines, pas sur les sections. Elle
 * resterait joignable par URL — donc vivante, testée, traduite… et invisible.
 * ------------------------------------------------------------------------- */
$aod_ng_groupees = array();
foreach ( $aod_ng_groups as $aod_ng_g ) {
	$aod_ng_groupees = array_merge( $aod_ng_groupees, $aod_ng_g[1] );
}
$aod_ng_orphelines = array_values( array_diff( array_keys( $aod_ng_sections ), $aod_ng_groupees ) );
AOD_Test::eq(
	array(),
	$aod_ng_orphelines,
	'🔴 R2 — toute section du menu appartient à un domaine (sinon : invisible, sans erreur)'
);

/* ---------------------------------------------------------------------------
 * R3 — Aucun slug dans deux domaines : il s’afficherait DEUX fois dans le rail, et
 * `section_group_label()` — qui rend le PREMIER domaine trouvé — donnerait un fil
 * d’ariane qui contredit la ligne de menu sur laquelle on vient de cliquer.
 * ------------------------------------------------------------------------- */
$aod_ng_dupes = array_values( array_unique( array_diff_assoc( $aod_ng_groupees, array_unique( $aod_ng_groupees ) ) ) );
AOD_Test::eq(
	array(),
	$aod_ng_dupes,
	'🔴 R3 — aucune section n’est rattachée à deux domaines'
);

/* ---------------------------------------------------------------------------
 * R4 — Aucun slug fantôme. Un domaine qui référence une section supprimée ou mal
 * orthographiée ne casse rien : la boucle du rendu le saute en silence. C’est
 * précisément pour ça qu’il faut le dire ici.
 * ------------------------------------------------------------------------- */
$aod_ng_fantomes = array_values( array_diff( $aod_ng_groupees, array_keys( $aod_ng_routable ) ) );
AOD_Test::eq(
	array(),
	$aod_ng_fantomes,
	'🔴 R4 — tout slug listé dans un domaine existe vraiment (menu ou section masquée)'
);

/* ---------------------------------------------------------------------------
 * R5 — Aucun domaine vide.
 * ------------------------------------------------------------------------- */
$aod_ng_vides = array();
foreach ( $aod_ng_groups as $aod_ng_slug => $aod_ng_g ) {
	if ( empty( $aod_ng_g[1] ) || '' === trim( (string) $aod_ng_g[0] ) ) {
		$aod_ng_vides[] = $aod_ng_slug;
	}
}
AOD_Test::eq(
	array(),
	$aod_ng_vides,
	'🔴 R5 — tout domaine a un intitulé non vide et au moins une section'
);

/* ---------------------------------------------------------------------------
 * R6 — L’ARBITRAGE DE LA PHASE, écrit pour qu’on ne le défasse pas par distraction.
 *
 * `tracking` (« Suivi commande ») lit les états rendus par le TRANSPORTEUR, via le
 * cron de 2 h. C’est la suite de l’expédition, pas de la vente — et depuis la
 * Phase 47, l’envoi est un geste déclenché au camion, ce qui achève de le détacher
 * de la confirmation. Le remettre dans « Ventes » ramènerait les deux défauts d’un
 * coup : « Expédition » redeviendrait un singleton, et « Ventes » remélangerait le
 * tunnel de vente avec le suivi logistique.
 *
 * ⚠️ Si un arbitrage produit ULTÉRIEUR décide l’inverse, ce contrôle doit être
 * réécrit avec sa nouvelle raison — pas supprimé pour retrouver du vert.
 * ------------------------------------------------------------------------- */
AOD_Test::ok(
	isset( $aod_ng_groups['livraison'] ) && in_array( 'tracking', $aod_ng_groups['livraison'][1], true ),
	'🔴 R6 — « Suivi commande » vit dans le domaine de l’expédition, pas dans « Ventes »',
	'il rend des états transporteur : le rattacher à la vente laisse « Expédition » seule avec sa section homonyme — le défaut d’origine'
);

AOD_Test::ok(
	isset( $aod_ng_groups['ventes'] ) && ! in_array( 'tracking', $aod_ng_groups['ventes'][1], true ),
	'🔴 R6bis — …et il n’est pas resté dans « Ventes » en double du déplacement'
);
