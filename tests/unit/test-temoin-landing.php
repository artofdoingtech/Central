<?php
/**
 * Le témoin « sans page de vente » de la porte `landing` est SEMÉ, pas emprunté (dette D33).
 *
 * 🚨 LE DÉFAUT. `landing-produit.js` porte 4 contrôles de non-régression — « un
 * produit publié SANS page de vente s'affiche encore comme une fiche produit
 * normale, avec son formulaire COD ». `run.sh` leur cherchait un témoin dans le
 * clone : le premier produit publié absent de la liste des landings actives.
 * Ce vivier peut être VIDE. Il l'est sur un clone semé avec le catalogue de
 * démonstration, qui allume une page de vente sur ses DEUX produits
 * (`dev-seed-catalogue.php`, lignes 611 et 917). Les 4 contrôles se retiraient
 * alors, et la porte concluait « la page de vente respecte les règles » sans
 * avoir vérifié la moitié qui compte.
 *
 * 🚨 CE QUE CE FICHIER GARDE, ET POURQUOI IL LE GARDE ICI.
 * Le correctif est un CÂBLAGE : la porte sème son témoin au lieu de le chercher.
 * Un câblage ne se prouve pas en le rejouant depuis un test unitaire — il se
 * prouve en vérifiant qu'il est encore là. C'est exactement ce que fait R1 : si
 * quelqu'un rebranche l'ancienne recherche (`grep -vxF -f` sur le vivier), la
 * porte redeviendra silencieusement dépendante des données du clone, et **rien
 * d'autre dans le dépôt ne le dira** — la porte, elle, restera verte sur un clone
 * qui a la chance d'avoir un produit ordinaire.
 *
 * ⚠️ CE QUE CE FICHIER NE PROUVE PAS, ET QUI LE PROUVE.
 * Il ne rejoue pas la fixture et ne visite aucune page : que le témoin semé
 * s'affiche bien en fiche produit ordinaire avec son formulaire COD est le travail
 * de `bash tools/recette/run.sh landing`, qui le mesure dans un vrai navigateur.
 * Éprouvé le 2026-08-01 dans la condition EXACTE de D33 — les deux produits du
 * clone passés à `_aod_landing_enabled=1`, donc vivier vide : les 4 contrôles se
 * sont exécutés sur le témoin semé, et le clone est ressorti à 2 produits.
 * *Dire ce qu'on délègue vaut mieux que le laisser croire.*
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

$aod_tl_repo  = '/repo';
$aod_tl_run_p = $aod_tl_repo . '/tools/recette/run.sh';
$aod_tl_sed_p = $aod_tl_repo . '/scripts/recette-seed-landing.php';

AOD_Test::group( 'Témoin landing D33 — R0 : les deux fichiers sont lisibles' );

$aod_tl_run = is_readable( $aod_tl_run_p ) ? (string) file_get_contents( $aod_tl_run_p ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du dépôt, contexte de test.
$aod_tl_sed = is_readable( $aod_tl_sed_p ) ? (string) file_get_contents( $aod_tl_sed_p ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du dépôt, contexte de test.

AOD_Test::ok( '' !== $aod_tl_run, sprintf( 'TÉMOIN : run.sh lu (%d octets)', strlen( $aod_tl_run ) ) );
AOD_Test::ok( '' !== $aod_tl_sed, sprintf( 'TÉMOIN : la fixture est lue (%d octets)', strlen( $aod_tl_sed ) ) );

/* -------------------------------------------------------------------------
 * R1 — La porte SÈME son témoin, et ne l'emprunte plus au clone.
 *
 * On isole le bloc `landing` de run.sh plutôt que de chercher dans tout le
 * fichier : `grep -vxF -f` est un motif banal, et une occurrence légitime
 * ailleurs rendrait l'assertion verte pour la mauvaise raison — ou rouge.
 * ---------------------------------------------------------------------- */

AOD_Test::group( 'Témoin landing D33 — R1 : le témoin est semé, pas emprunté' );

$aod_tl_bloc = '';
if ( preg_match( '/step "Page de vente \(landing\) par produit"(.*?)\nfi\n/s', $aod_tl_run, $aod_tl_m ) ) {
	$aod_tl_bloc = $aod_tl_m[1];
}
AOD_Test::ok(
	'' !== $aod_tl_bloc,
	sprintf( 'TÉMOIN : le bloc `landing` de run.sh est isolé (%d octets)', strlen( $aod_tl_bloc ) )
);

AOD_Test::ok(
	false !== strpos( $aod_tl_bloc, 'recette-seed-landing.php create' ),
	'🔴 Le bloc `landing` sème son témoin sans page de vente',
	'la fixture n’est plus appelée : le témoin redevient emprunté au clone, donc absent dès que TOUS les produits publiés ont une landing (catalogue de démonstration)'
);

AOD_Test::ok(
	false === strpos( $aod_tl_bloc, 'grep -vxF -f' ),
	'🔴 …et il ne cherche plus de témoin dans le vivier du clone',
	'l’ancienne sélection est de retour : elle rend AOD_PLAIN_PID vide sur un clone dont tous les produits ont une page de vente'
);

AOD_Test::ok(
	false !== strpos( $aod_tl_bloc, 'AOD_PLAIN_PID="$PLAIN_PID"' ),
	'🔴 …et le témoin semé est bien celui passé au scénario',
	'AOD_PLAIN_PID ne reçoit plus l’identifiant issu de la fixture'
);

/* -------------------------------------------------------------------------
 * R2 — La fixture est purgée même si la porte casse en plein milieu.
 *
 * Leçon D26 : un produit jetable laissé publié fausse la Boutique de tous les
 * scénarios suivants — et d'un clone qu'on croyait propre.
 * ---------------------------------------------------------------------- */

AOD_Test::group( 'Témoin landing D33 — R2 : la fixture ne survit pas à une interruption' );

AOD_Test::ok(
	preg_match( '/^on_exit\(\)\s*\{[^}]*cleanup_landing;/m', $aod_tl_run ) === 1,
	'🔴 `cleanup_landing` est appelé par le trap EXIT',
	'une fatale au milieu de la porte laisserait le produit témoin publié dans le clone'
);

AOD_Test::ok(
	preg_match( '/cleanup_landing\(\)\s*\{.*?recette-seed-landing\.php cleanup/s', $aod_tl_run ) === 1,
	'🔴 …et ce nettoyage appelle bien la purge de la fixture',
	'le nom existe mais ne purge rien'
);

/* -------------------------------------------------------------------------
 * R3 — Le témoin est NU : il ne doit jamais naître avec une page de vente.
 *
 * C'est le défaut D33 retourné à l'envers, et il serait bien pire : un témoin
 * porteur de landing rendrait les 4 contrôles verts en comparant une landing à
 * une landing. La fixture le vérifie à l'exécution (elle refuse de semer) ; ici
 * on garde le fait qu'elle ne le POSE jamais elle-même.
 * ---------------------------------------------------------------------- */

AOD_Test::group( 'Témoin landing D33 — R3 : la fixture ne pose aucune page de vente' );

AOD_Test::ok(
	preg_match( '/(?:update|add)_post_meta\s*\([^;]*_aod_landing_enabled/', $aod_tl_sed ) !== 1,
	'🔴 La fixture n’écrit jamais `_aod_landing_enabled`',
	'le témoin naîtrait AVEC une page de vente : les 4 contrôles compareraient une landing à une landing'
);

AOD_Test::ok(
	false !== strpos( $aod_tl_sed, 'WP_CLI::error( \'Le témoin est né AVEC une page de vente' ),
	'🔴 …et elle refuse de semer un témoin qui en aurait une',
	'le garde-fou d’exécution a disparu : un drapeau posé par défaut ailleurs passerait inaperçu'
);

AOD_Test::ok(
	preg_match( '/\'meta_key\'\s*=>\s*AOD_LANDING_SEED_MARK/', $aod_tl_sed ) === 1,
	'🔴 La purge ne supprime QUE ce qui porte la marque',
	'une purge qui viserait les produits par type ou par statut détruirait le catalogue du clone'
);
