<?php
/**
 * Le calculateur de bénéfices — les formules, épinglées.
 *
 * CE QUE CE TEST GARDE, ET POURQUOI IL EXISTE. Un calculateur qui se trompe ne
 * plante pas : il rend un nombre plausible. Le gérant décide d'augmenter son
 * budget publicitaire sur une marge fausse, et rien — ni écran blanc, ni erreur
 * PHP, ni porte navigateur — ne l'aura signalé. C'est le profil de défaut le
 * plus coûteux de tout `/gestion`, et le seul contrôle possible est
 * arithmétique : des entrées connues, des sorties calculées à la main.
 *
 * 🚨 R2 EST LE CŒUR : LES TROIS PIÈGES DU MODÈLE COD.
 * Les trois assertions marquées 🔴 tiennent des décisions que n'importe quelle
 * réécriture « qui simplifie » ferait sauter, chacune en rendant un bénéfice
 * TROP FLATTEUR :
 *   1. On n'encaisse QUE les livrées. Compter le chiffre d'affaires sur les
 *      commandes REÇUES multiplierait le CA par 1,67 sur le jeu d'essai.
 *   2. La confirmation se paie sur TOUTES les commandes — on appelle aussi
 *      celles qui n'aboutiront pas. La compter sur les seules livrées
 *      effacerait 40 % de ce poste.
 *   3. Un retour a coûté sa livraison mais la marchandise revient en stock :
 *      il pèse en transport, pas en produit. L'y compter inventerait une perte.
 *
 * ⚠️ CE QUE CE TEST NE PEUT PAS FAIRE. Les mêmes formules existent en JavaScript
 * (`section-finance.js`), pour que l'aperçu soit instantané ; ce banc n'a pas de
 * moteur JS et ne peut donc pas les confronter. Ce n'est pas un angle mort
 * ignoré : c'est pourquoi l'enregistrement RECALCULE côté serveur et pourquoi le
 * JS compare sa réponse à la sienne, en l'affichant si elle diffère. R4 vérifie
 * ici que ce garde-fou n'a pas été retiré du JS.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * R0 — le témoin : la classe est là, et la méthode aussi
 * ========================================================================= */

AOD_Test::group( 'Finance — R0 : ancrage' );

AOD_Test::ok(
	class_exists( 'AOD_CD_Section_Finance' ),
	'La section Finance est chargée'
);
AOD_Test::ok(
	method_exists( 'AOD_CD_Section_Finance', 'compute' ),
	'`compute()` existe — sans elle, tout ce qui suit serait vert par vacuité'
);

if ( ! class_exists( 'AOD_CD_Section_Finance' ) ) {
	return;
}

/* =========================================================================
 * R1 — le contrat de saisie : déclaré une fois, complet
 * ========================================================================= */

AOD_Test::group( 'Finance — R1 : le contrat de saisie' );

$aod_fin_cles = AOD_CD_Section_Finance::cles_saisie();

// Les onze entrées de la demande owner. Une clé qui disparaîtrait du tableau
// `champs()` sortirait AUSSI du formulaire et de la sanitisation — le champ
// cesserait d'exister sans laisser de trace à l'écran.
$aod_fin_attendues = array(
	'prix_vente', 'prix_achat', 'f_livraison', 'f_confirm', 'f_emballage',
	'f_pub', 'f_autres',
	'n_commandes', 'n_livrees', 'n_annulees', 'n_retours',
);
$aod_fin_absentes = array_values( array_diff( $aod_fin_attendues, array_keys( $aod_fin_cles ) ) );
AOD_Test::ok(
	empty( $aod_fin_absentes ),
	sprintf( 'Les %d entrées du calculateur sont déclarées', count( $aod_fin_attendues ) ),
	'absentes : ' . implode( ', ', $aod_fin_absentes )
);

// Les compteurs sont des ENTIERS. Un `n_livrees` typé `money` accepterait
// « 60,5 commandes livrées » et le taux de livraison deviendrait absurde.
$aod_fin_mal_typees = array();
foreach ( array( 'n_commandes', 'n_livrees', 'n_annulees', 'n_retours' ) as $aod_fin_c ) {
	if ( isset( $aod_fin_cles[ $aod_fin_c ] ) && 'int' !== $aod_fin_cles[ $aod_fin_c ] ) {
		$aod_fin_mal_typees[] = $aod_fin_c;
	}
}
AOD_Test::ok(
	empty( $aod_fin_mal_typees ),
	'Les quatre compteurs de commandes sont typés `int`',
	implode( ', ', $aod_fin_mal_typees )
);

/* =========================================================================
 * R2 — l'arithmétique, sur un jeu calculé à la main
 * ========================================================================= */

AOD_Test::group( 'Finance — R2 : le calcul sur un cas connu' );

/*
 * LE JEU D'ESSAI, ET SON CALCUL AU CRAYON.
 *
 *   Par commande : vente 3000 · achat 1200 · livraison 400 · confirmation 50
 *                  · emballage 100
 *   Sur la période : publicité 60 000 · autres 5 000
 *   Volumes : 100 reçues · 60 livrées · 25 annulées · 15 retours
 *
 *   expédiées      = 60 + 15                        =      75
 *   CA             = 3000 × 60                      = 180 000
 *   coût produit   = 1200 × 60                      =  72 000
 *   coût livraison =  400 × 60                      =  24 000
 *   coût retours   =  400 × 15                      =   6 000
 *   coût emballage =  100 × 75                      =   7 500
 *   coût confirm.  =   50 × 100                     =   5 000
 *   coût total     = 72000+24000+6000+7500+5000+60000+5000 = 179 500
 *   bénéfice brut  = 180000 − 72000                 = 108 000
 *   bénéfice net   = 180000 − 179500                =     500
 *   marge          = 500 / 180000 × 100             =   0,2777…
 *   coût moyen     = 179500 / 100                   =   1 795
 *   CPA            = 60000 / 60                     =   1 000
 *   ROAS           = 180000 / 60000                 =       3
 *   taux livraison = 60 / 100                       =      60 %
 *   taux annul.    = 25 / 100                       =      25 %
 *   taux retour    = 15 / 75                        =      20 %
 *
 * ⚠️ Ces nombres ne sont PAS repris d'une exécution du code : ils ont été posés
 * avant, à la main, puis confrontés. Recopier ce que la fonction rend
 * transformerait le test en photographie du bogue.
 */
$aod_fin_jeu = array(
	'prix_vente'  => 3000,
	'prix_achat'  => 1200,
	'f_livraison' => 400,
	'f_confirm'   => 50,
	'f_emballage' => 100,
	'f_pub'       => 60000,
	'f_autres'    => 5000,
	'n_commandes' => 100,
	'n_livrees'   => 60,
	'n_annulees'  => 25,
	'n_retours'   => 15,
);
$aod_fin_r = AOD_CD_Section_Finance::compute( $aod_fin_jeu );

$aod_fin_attendu = array(
	'ca'              => 180000.0,
	'cout_produit'    => 72000.0,
	'cout_livraison'  => 24000.0,
	'cout_retours'    => 6000.0,
	'cout_emballage'  => 7500.0,
	'cout_confirm'    => 5000.0,
	'cout_pub'        => 60000.0,
	'cout_autres'     => 5000.0,
	'cout_total'      => 179500.0,
	'benefice_brut'   => 108000.0,
	'benefice_net'    => 500.0,
	'cout_moyen'      => 1795.0,
	'cpa'             => 1000.0,
	'roas'            => 3.0,
	'taux_livraison'  => 60.0,
	'taux_annulation' => 25.0,
	'taux_retour'     => 20.0,
);
foreach ( $aod_fin_attendu as $aod_fin_k => $aod_fin_v ) {
	AOD_Test::ok(
		isset( $aod_fin_r[ $aod_fin_k ] ) && abs( $aod_fin_r[ $aod_fin_k ] - $aod_fin_v ) < 0.005,
		sprintf( '%s = %s', $aod_fin_k, number_format( $aod_fin_v, 2, ',', ' ' ) ),
		sprintf( 'obtenu %s', isset( $aod_fin_r[ $aod_fin_k ] ) ? (string) $aod_fin_r[ $aod_fin_k ] : '(absent)' )
	);
}
AOD_Test::ok(
	abs( $aod_fin_r['marge'] - 0.2777777 ) < 0.0005,
	'marge = 0,2778 % (500 sur 180 000)',
	'obtenu ' . $aod_fin_r['marge']
);

/* ---- Les trois pièges du modèle, isolés ------------------------------- */

// 🔴 1. Le CA ne compte QUE les livrées. Si quelqu'un le calculait sur les
// commandes reçues, il vaudrait 300 000 au lieu de 180 000.
AOD_Test::ok(
	abs( $aod_fin_r['ca'] - ( 3000 * 60 ) ) < 0.005 && abs( $aod_fin_r['ca'] - ( 3000 * 100 ) ) > 1,
	'🔴 Le chiffre d’affaires ne compte QUE les commandes livrées',
	'obtenu ' . $aod_fin_r['ca'] . ' — attendu 180000, et surtout PAS 300000'
);

// 🔴 2. La confirmation se paie sur toutes les commandes reçues, pas sur les
// livrées : 50 × 100 et non 50 × 60.
AOD_Test::ok(
	abs( $aod_fin_r['cout_confirm'] - ( 50 * 100 ) ) < 0.005,
	'🔴 La confirmation est comptée sur TOUTES les commandes reçues',
	'obtenu ' . $aod_fin_r['cout_confirm'] . ' — attendu 5000, et PAS 3000'
);

// 🔴 3. Un retour ne consomme pas de produit — la marchandise revient. Le coût
// produit reste sur les 60 livrées, pas sur les 75 expédiées.
AOD_Test::ok(
	abs( $aod_fin_r['cout_produit'] - ( 1200 * 60 ) ) < 0.005,
	'🔴 Un retour ne consomme pas de produit (la marchandise revient en stock)',
	'obtenu ' . $aod_fin_r['cout_produit'] . ' — attendu 72000, et PAS 90000'
);

// Et le taux de retour se mesure sur les colis PARTIS, pas sur les commandes
// reçues : 15/75 = 20 %, pas 15/100 = 15 %.
AOD_Test::ok(
	abs( $aod_fin_r['taux_retour'] - 20.0 ) < 0.005,
	'Le taux de retour se mesure sur les colis expédiés (15 sur 75)',
	'obtenu ' . $aod_fin_r['taux_retour'] . ' — attendu 20, et PAS 15'
);

/* =========================================================================
 * R3 — les bords : zéro, vide, négatif, absurde
 * ========================================================================= */

AOD_Test::group( 'Finance — R3 : aucune division ne peut exploser' );

// Un formulaire VIERGE est l'état de la page au premier affichage. S'il rendait
// un `NAN` ou un `INF`, il traverserait tout le panneau de résultats.
$aod_fin_vide = AOD_CD_Section_Finance::compute( array() );
$aod_fin_sales = array();
foreach ( $aod_fin_vide as $aod_fin_k => $aod_fin_v ) {
	if ( ! is_finite( $aod_fin_v ) || abs( $aod_fin_v ) > 0.0001 ) {
		$aod_fin_sales[] = $aod_fin_k . '=' . $aod_fin_v;
	}
}
AOD_Test::ok(
	empty( $aod_fin_sales ),
	'Un formulaire vierge rend dix-huit zéros finis (ni NAN, ni INF)',
	implode( ', ', $aod_fin_sales )
);

// Des valeurs hostiles : négatifs, texte, tableau. Aucune ne doit produire un
// résultat non fini — le calculateur est nourri par un `<input>`, donc par tout
// ce qu'un navigateur ou un POST forgé peut y mettre.
$aod_fin_hostile = AOD_CD_Section_Finance::compute( array(
	'prix_vente'  => -5000,
	'prix_achat'  => 'abc',
	'f_livraison' => array( 1, 2 ),
	'f_pub'       => 0,
	'n_commandes' => -10,
	'n_livrees'   => 'douze',
	'n_retours'   => null,
) );
$aod_fin_sales2 = array();
foreach ( $aod_fin_hostile as $aod_fin_k => $aod_fin_v ) {
	if ( ! is_finite( $aod_fin_v ) ) {
		$aod_fin_sales2[] = $aod_fin_k;
	}
}
AOD_Test::ok(
	empty( $aod_fin_sales2 ),
	'Des entrées hostiles (négatifs, texte, tableau, null) rendent des nombres finis',
	implode( ', ', $aod_fin_sales2 )
);

// ROAS sans publicité : la division serait par zéro. On rend 0, et l'écran
// affiche « — » plutôt qu'un infini.
$aod_fin_sanspub = AOD_CD_Section_Finance::compute( array(
	'prix_vente' => 1000,
	'n_livrees'  => 10,
	'n_commandes' => 10,
) );
AOD_Test::ok(
	0.0 === $aod_fin_sanspub['roas'] && 0.0 === $aod_fin_sanspub['cpa'],
	'Sans budget publicitaire, ROAS et coût d’acquisition valent 0 (pas INF)',
	'roas=' . $aod_fin_sanspub['roas'] . ' cpa=' . $aod_fin_sanspub['cpa']
);

// L'avertissement de cohérence se déclenche, et SEULEMENT quand il doit.
AOD_Test::ok(
	'' !== AOD_CD_Section_Finance::coherence( array( 'n_commandes' => 10, 'n_livrees' => 8, 'n_annulees' => 5, 'n_retours' => 0 ) ),
	'🔴 Livrées + annulées au-dessus des reçues déclenche l’avertissement'
);
AOD_Test::ok(
	'' === AOD_CD_Section_Finance::coherence( $aod_fin_jeu ),
	'ANCRAGE INVERSE : un jeu cohérent ne déclenche RIEN (sinon l’avertissement serait un décor)'
);

/* =========================================================================
 * R4 — le garde-fou de la duplication JS n'a pas été retiré
 * ========================================================================= */

AOD_Test::group( 'Finance — R4 : l’écran et le serveur se confrontent' );

$aod_fin_js = AOD_CD_PATH . 'assets/js/section-finance.js';
$aod_fin_src = is_readable( $aod_fin_js ) ? (string) file_get_contents( $aod_fin_js ) : '';

AOD_Test::ok(
	'' !== $aod_fin_src,
	'Témoin : `section-finance.js` est lisible (' . strlen( $aod_fin_src ) . ' octets)'
);

// Les formules sont écrites deux fois — c'est assumé, pour que l'aperçu soit
// instantané. Ce qui n'est PAS négociable, c'est le mécanisme qui rend une
// divergence VISIBLE. S'il disparaît, la duplication redevient un pari.
AOD_Test::ok(
	false !== strpos( $aod_fin_src, 'function comparer' ) && false !== strpos( $aod_fin_src, 'peindre( sien )' ),
	'🔴 Le JS compare son résultat à celui du serveur et affiche CELUI DU SERVEUR en cas d’écart',
	'`comparer()` ou son repli sur les nombres du serveur a disparu — la duplication de formules n’est plus gardée'
);

// Et le serveur ne croit jamais l'écran : il recalcule depuis les seules
// entrées. Si `ajax_finance_save()` se mettait à stocker des résultats postés,
// une page trafiquée écrirait n'importe quoi dans l'historique.
$aod_fin_php = AOD_CD_PATH . 'includes/class-aod-cd-section-finance.php';
$aod_fin_psrc = is_readable( $aod_fin_php ) ? (string) file_get_contents( $aod_fin_php ) : '';
AOD_Test::ok(
	false !== strpos( $aod_fin_psrc, '$res     = self::compute( $entrees );' ),
	'🔴 L’enregistrement RECALCULE côté serveur au lieu de croire ce que la page envoie'
);
