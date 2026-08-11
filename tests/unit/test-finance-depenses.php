<?php
/**
 * Le registre des dépenses — ses décisions, épinglées.
 *
 * CE QUE CE TEST GARDE. Un registre de dépenses n'échoue pas bruyamment : il
 * rend un total. Une ligne perdue, un poste inconnu rangé nulle part, une
 * couleur qui glisse d'un mois à l'autre — rien de tout cela ne casse un écran,
 * et tout fausse une décision. Ce banc épingle les trois choix qui rendent ce
 * registre digne du mot « comptable ».
 *
 * 🚨 R3 EST LE CŒUR : AU PLAFOND, ON REFUSE — ON NE JETTE PAS.
 * L'historique du calculateur garde des SIMULATIONS et perd la plus ancienne
 * sans dommage (`MAX_HISTORIQUE`, `array_slice`). Une dépense est une ÉCRITURE :
 * la même mécanique effacerait un fait, et le total du mois deviendrait faux en
 * paraissant complet. Une réécriture « qui aligne les deux plafonds » est
 * exactement le genre de simplification qui rouvrirait ce défaut, et rien à
 * l'écran ne le dirait.
 *
 * ⚠️ CE QUE CE TEST NE PEUT PAS FAIRE. Il n'exerce pas les handlers AJAX (ils
 * appellent `wp_send_json_*`, qui coupe le processus) : il lit leur SOURCE pour
 * les décisions structurelles, et éprouve de front l'arithmétique qui, elle, est
 * une fonction pure. La saisie de bout en bout relève de la recette navigateur.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * R0 — l'ancrage
 * ========================================================================= */

AOD_Test::group( 'Dépenses — R0 : ancrage' );

AOD_Test::ok(
	class_exists( 'AOD_CD_Section_Finance' )
		&& method_exists( 'AOD_CD_Section_Finance', 'depenses_cats' )
		&& method_exists( 'AOD_CD_Section_Finance', 'depenses_ventilation' ),
	'La section Finance expose `depenses_cats()` et `depenses_ventilation()`',
	'sans elles, tout ce qui suit serait vert par vacuité'
);

if ( ! method_exists( 'AOD_CD_Section_Finance', 'depenses_ventilation' ) ) {
	return;
}

/* =========================================================================
 * R1 — les postes : une liste fermée, et bornée par la rampe de couleurs
 * ========================================================================= */

AOD_Test::group( 'Dépenses — R1 : les postes' );

$aod_dep_cats = AOD_CD_Section_Finance::depenses_cats();

AOD_Test::ok(
	7 === count( $aod_dep_cats ),
	sprintf( 'Sept postes de dépense (trouvés : %d)', count( $aod_dep_cats ) )
);

// 🔴 LA RAMPE N'A QUE SEPT CRANS, ET C'EST UNE CONTRAINTE, PAS UN HASARD.
// `.aod-cd-fincost__seg` mélange l'accent vers sa teinte claire par
// `calc(100% - var(--part) * 14%)` : au 8ᵉ poste, `--part` vaut 7, le mélange
// tombe à 2 % d'accent et les deux dernières parts deviennent la même couleur.
// Rien ne casserait — la barre s'afficherait, avec deux postes indistinguables.
$aod_dep_css = AOD_CD_PATH . 'assets/css/dashboard.css';
$aod_dep_src = is_readable( $aod_dep_css ) ? (string) file_get_contents( $aod_dep_css ) : '';
AOD_Test::ok(
	false !== strpos( $aod_dep_src, 'calc(100% - var(--part) * 14%)' ),
	'🔴 La rampe des postes est bien celle à 14 % par cran (7 crans utiles)',
	'si elle change, le nombre de postes doit être revu avec elle'
);
AOD_Test::ok(
	count( $aod_dep_cats ) <= 7,
	sprintf( '🔴 Les postes tiennent dans les 7 crans de la rampe (%d)', count( $aod_dep_cats ) ),
	'un 8ᵉ poste rendrait deux parts de la barre de la même couleur, sans erreur'
);

AOD_Test::ok(
	isset( $aod_dep_cats['publicite'], $aod_dep_cats['autres'] ),
	'Les postes « publicite » et « autres » existent — le premier dialogue avec la Rentabilité, le second est le refuge des inconnus'
);

/* =========================================================================
 * R2 — la ventilation : arithmétique calculée à la main
 * ========================================================================= */

AOD_Test::group( 'Dépenses — R2 : la ventilation' );

$aod_dep_lignes = array(
	array( 'id' => 'a', 'cat' => 'salaires', 'lib' => '', 'mnt' => 45000.0, 'date' => '2026-08-01' ),
	array( 'id' => 'b', 'cat' => 'publicite', 'lib' => '', 'mnt' => 20000.0, 'date' => '2026-08-03' ),
	array( 'id' => 'c', 'cat' => 'publicite', 'lib' => '', 'mnt' => 5000.5, 'date' => '2026-08-09' ),
	array( 'id' => 'd', 'cat' => 'emballage', 'lib' => '', 'mnt' => 1200.0, 'date' => '2026-08-11' ),
);
$aod_dep_v = AOD_CD_Section_Finance::depenses_ventilation( $aod_dep_lignes );

// 45000 + 20000 + 5000,5 + 1200 = 71200,5
AOD_Test::ok(
	abs( $aod_dep_v['total'] - 71200.5 ) < 0.001,
	sprintf( 'Total du mois = 71 200,50 (obtenu : %s)', $aod_dep_v['total'] )
);
AOD_Test::ok(
	abs( $aod_dep_v['par_cat']['publicite'] - 25000.5 ) < 0.001,
	sprintf( 'Deux lignes d’un même poste s’additionnent : publicité = 25 000,50 (obtenu : %s)', $aod_dep_v['par_cat']['publicite'] )
);
AOD_Test::ok(
	abs( array_sum( $aod_dep_v['par_cat'] ) - $aod_dep_v['total'] ) < 0.001,
	'🔴 La somme des postes ÉGALE le total — aucune ligne ne se perd en route'
);

// 🔴 TOUS LES POSTES SONT LÀ, MÊME À ZÉRO. L'indice d'un poste dans ce tableau
// EST sa couleur (`--part`). Ne rendre que les postes utilisés ferait glisser
// les couleurs d'un mois à l'autre : « la part bleue » ne désignerait plus la
// même dépense en juillet et en août, et la comparaison serait muette.
AOD_Test::ok(
	array_keys( $aod_dep_v['par_cat'] ) === array_keys( $aod_dep_cats ),
	'🔴 Les 7 postes sont présents, dans l’ordre de `depenses_cats()` — la couleur d’un poste ne bouge pas d’un mois à l’autre'
);
AOD_Test::ok(
	0.0 === $aod_dep_v['par_cat']['abonnements'],
	'Un poste sans dépense vaut zéro, il ne disparaît pas'
);

// ANCRAGE INVERSE : sans lignes, tout est à zéro et rien n'est indéfini.
$aod_dep_vide = AOD_CD_Section_Finance::depenses_ventilation( array() );
AOD_Test::ok(
	0.0 === $aod_dep_vide['total'] && 7 === count( $aod_dep_vide['par_cat'] ),
	'ANCRAGE INVERSE : un mois vide rend un total nul et ses 7 postes — jamais un tableau vide'
);

/* =========================================================================
 * R3 — le plafond REFUSE, il ne tronque pas
 * ========================================================================= */

AOD_Test::group( 'Dépenses — R3 : le plafond' );

$aod_dep_php = AOD_CD_PATH . 'includes/class-aod-cd-section-finance.php';
$aod_dep_fin = is_readable( $aod_dep_php ) ? (string) file_get_contents( $aod_dep_php ) : '';

AOD_Test::ok(
	'' !== $aod_dep_fin,
	'La source de la section Finance est lisible'
);

// Le handler d'ajout, isolé — on ne veut pas juger sur le fichier entier
// (`array_slice` existe légitimement dans l'historique du calculateur).
$aod_dep_i0 = strpos( $aod_dep_fin, 'public function ajax_depense_add()' );
$aod_dep_i1 = strpos( $aod_dep_fin, 'public function ajax_depense_del()' );
$aod_dep_add = ( false !== $aod_dep_i0 && false !== $aod_dep_i1 && $aod_dep_i1 > $aod_dep_i0 )
	? substr( $aod_dep_fin, $aod_dep_i0, $aod_dep_i1 - $aod_dep_i0 )
	: '';

AOD_Test::ok(
	'' !== $aod_dep_add,
	'ANCRAGE : le corps de `ajax_depense_add()` a bien été isolé (sinon les deux contrôles suivants seraient vrais par vacuité)'
);
AOD_Test::ok(
	false !== strpos( $aod_dep_add, 'count( $mo ) >= self::MAX_DEPENSES_MOIS' )
		&& false !== strpos( $aod_dep_add, 'wp_send_json_error' ),
	'🔴 Au plafond, l’ajout est REFUSÉ (erreur renvoyée), pas absorbé en silence'
);
AOD_Test::ok(
	false === strpos( $aod_dep_add, 'array_slice' )
		&& false === strpos( $aod_dep_add, 'array_shift' ),
	'🔴 Aucune troncature dans l’ajout — une écriture comptable ne se jette pas pour faire de la place',
	'c’est ce qui distingue ce registre de l’historique des simulations, qui perd sa plus ancienne ligne sans dommage'
);

// L'option n'est jamais autoloadée : elle ne sert qu'à un écran, la charger sur
// chaque requête ferait payer la comptabilité au temps de réponse de la boutique.
AOD_Test::ok(
	false !== strpos( $aod_dep_add, 'update_option( self::OPTION_DEPENSES, $all, false )' ),
	'Le registre est écrit sans autoload (3ᵉ argument `false`)'
);

// Et il est effacé à la désinstallation — R1 de `test-uninstall.php` l'exige,
// on le redit ici pour que le lien option ↔ nettoyage se lise au même endroit.
$aod_dep_uni = AOD_CD_PATH . 'uninstall.php';
AOD_Test::ok(
	is_readable( $aod_dep_uni )
		&& false !== strpos( (string) file_get_contents( $aod_dep_uni ), "delete_option( 'aod_cd_finance_depenses' )" ),
	'Le registre est supprimé par `uninstall.php`'
);

/* =========================================================================
 * R4 — le mois de rangement vient de la DATE, pas du filtre
 * ========================================================================= */

AOD_Test::group( 'Dépenses — R4 : le rangement' );

// 🔴 Ranger une dépense du 1ᵉʳ septembre dans août parce qu'on consultait août
// fausserait DEUX mois d'un coup, sans laisser de trace. Le champ `mois` du
// formulaire ne borne que le sélecteur de date à l'écran.
AOD_Test::ok(
	false !== strpos( $aod_dep_add, '$mois = substr( $date, 0, 7 );' ),
	'🔴 Le mois de rangement est DÉDUIT de la date saisie, jamais lu depuis le filtre'
);
AOD_Test::ok(
	false !== strpos( $aod_dep_add, '$mnt <= 0' ),
	'Un montant nul ou négatif est refusé — une ligne à zéro ne serait qu’un bruit dans le registre'
);
AOD_Test::ok(
	false !== strpos( $aod_dep_add, 'if ( ! isset( $cats[ $cat ] ) )' ),
	'Un poste hors de la liste fermée est refusé à l’écriture'
);

// …et à la LECTURE, une valeur qu'on n'a pas écrite (option modifiée par
// ailleurs) retombe sur « autres » plutôt que de disparaître du tableau : ce
// serait de l'argent dépensé qui cesse d'être compté.
AOD_Test::ok(
	false !== strpos( $aod_dep_fin, "isset( \$cat[ \$c ] ) ? \$c : 'autres'" ),
	'🔴 À la lecture, un poste inconnu est rangé dans « autres » — jamais écarté du total'
);
