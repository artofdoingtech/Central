<?php
/**
 * Montant estimé d'un panier abandonné (Phase 43.6).
 *
 * 🚨 CE QUE CE CLIQUET GARDE, ET POURQUOI IL EST DÉLICAT. Un montant faux ne
 * plante pas : il s'affiche. Le gérant décide de rappeler — ou non — un prospect
 * sur la foi de ce chiffre. Les deux modes de défaut sont donc silencieux :
 *   · un montant INVENTÉ là où l'information manque (le piège réel :
 *     `AOD_COD_Data::price_for()` normalise en silence tout type de livraison
 *     inconnu en « home », donc un prospect capté avant le choix de livraison
 *     afficherait un tarif Domicile que personne n'a choisi) ;
 *   · un montant APPROCHÉ présenté comme exact (le supplément de variante n'est
 *     pas mémorisé par un panier abandonné) — d'où le « ≈ », qui fait partie de
 *     la donnée, pas de la décoration.
 *
 * ⚠️ RÈGLE DU TÉMOIN : les attendus ci-dessous sont recalculés en passant par le
 * CODE DE PRODUCTION (`price_for()`), jamais par une formule recopiée dans le
 * test — une copie diverge en silence le jour où la vraie change.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Montant abandon 43.6 — socle' );

$aod_ma_socle = class_exists( 'AOD_CD_Section_Abandoned' )
	&& class_exists( 'AOD_COD_Form' )
	&& class_exists( 'AOD_COD_Data' )
	&& method_exists( 'AOD_CD_Section_Abandoned', 'lead_amount_html' );
AOD_Test::ok( $aod_ma_socle, 'Socle présent (Section_Abandoned::lead_amount_html + AOD_COD_Form + AOD_COD_Data)' );

if ( ! $aod_ma_socle ) {
	return;
}

/** Wilaya de test : on prend la PREMIÈRE réellement tarifée sur ce clone. */
$aod_ma_wilaya = 0;
for ( $aod_ma_i = 1; $aod_ma_i <= 58; $aod_ma_i++ ) {
	if ( AOD_COD_Data::price_for( $aod_ma_i, 'home' ) > 0 ) {
		$aod_ma_wilaya = $aod_ma_i;
		break;
	}
}
AOD_Test::ok( $aod_ma_wilaya > 0, sprintf( 'TÉMOIN : une wilaya tarifée existe sur ce clone (n° %d)', $aod_ma_wilaya ), 'sans tarif, toutes les assertions de somme seraient vraies par vacuité' );

if ( ! $aod_ma_wilaya ) {
	return;
}

$aod_ma_home = (float) AOD_COD_Data::price_for( $aod_ma_wilaya, 'home' );
$aod_ma_desk = (float) AOD_COD_Data::price_for( $aod_ma_wilaya, 'desk' );

/** Produit simple à prix fixe. */
$aod_ma_mk_simple = function ( $titre, $prix ) {
	$pid = wp_insert_post( array( 'post_title' => $titre, 'post_type' => 'product', 'post_status' => 'publish' ) );
	update_post_meta( $pid, '_price', $prix );
	update_post_meta( $pid, '_regular_price', $prix );
	wp_set_object_terms( $pid, 'simple', 'product_type' );
	return (int) $pid;
};

$aod_ma_pid_s = $aod_ma_mk_simple( 'AOD436 simple', 1000 );
$aod_ma_pid_v = $aod_ma_mk_simple( 'AOD436 variante', 1000 );
update_post_meta(
	$aod_ma_pid_v,
	'_aod_options',
	array(
		array(
			'label'  => 'Couleur',
			'values' => array(
				array( 'name' => 'Rouge', 'price' => 0, 'stock' => '' ),
				array( 'name' => 'Bleu', 'price' => 200, 'stock' => '' ),
			),
		),
	)
);

/** Fabrique un prospect (objet, comme un `SELECT *` sur la table leads). */
$aod_ma_lead = function ( $pid, $wilaya, $delivery, $qty ) {
	return (object) array(
		'product_id' => $pid,
		'wilaya'     => $wilaya,
		'delivery'   => $delivery,
		'qty'        => $qty,
	);
};

/** Montant NUMÉRIQUE lu dans le HTML rendu (or tiret / approché). */
$aod_ma_lire = function ( $lead ) {
	$html  = AOD_CD_Section_Abandoned::lead_amount_html( $lead );
	$texte = trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	return array(
		'html'   => $html,
		'texte'  => $texte,
		'tiret'  => ( false !== strpos( $html, 'aod-cd-muted' ) ),
		'approx' => ( false !== strpos( $html, 'aod-cd-amount-approx' ) ),
		// Les chiffres seuls : robuste au format de `wc_price()` (séparateurs,
		// position du symbole, RTL) — on compare des NOMBRES, pas une chaîne.
		'nombre' => (float) preg_replace( '/[^0-9.]/', '', str_replace( ',', '', preg_replace( '/[^\d.,]/u', '', $texte ) ) ),
	);
};

try {
	/* =====================================================================
	 * R1 — Le montant EST produit × qty + livraison, à la wilaya et au type
	 * ===================================================================== */

	AOD_Test::group( 'Montant abandon 43.6 — R1 : la somme, wilaya et type exacts' );

	$aod_ma_r = $aod_ma_lire( $aod_ma_lead( $aod_ma_pid_s, $aod_ma_wilaya, 'home', 2 ) );
	AOD_Test::eq(
		2000.0 + $aod_ma_home,
		$aod_ma_r['nombre'],
		sprintf( '🔴 Domicile : 2 × 1000 + %s de livraison (lu : %s)', $aod_ma_home, $aod_ma_r['texte'] )
	);
	AOD_Test::ok( ! $aod_ma_r['approx'], 'Produit SANS variante : montant EXACT, aucun « ≈ »' );

	$aod_ma_r = $aod_ma_lire( $aod_ma_lead( $aod_ma_pid_s, $aod_ma_wilaya, 'desk', 1 ) );
	AOD_Test::eq(
		1000.0 + $aod_ma_desk,
		$aod_ma_r['nombre'],
		sprintf( '🔴 Stop-desk : le tarif DESK est utilisé, pas celui du domicile (desk=%s, home=%s)', $aod_ma_desk, $aod_ma_home )
	);

	// ANCRAGE : les deux tarifs doivent différer pour que R1 prouve quelque
	// chose. S'ils sont égaux sur ce clone, on le DIT plutôt que de laisser
	// croire que la distinction est éprouvée.
	AOD_Test::ok(
		$aod_ma_home !== $aod_ma_desk,
		sprintf( 'TÉMOIN : home (%s) et desk (%s) diffèrent — sinon l’assertion stop-desk ne distingue rien', $aod_ma_home, $aod_ma_desk ),
		'clone dont les deux tarifs sont identiques : l’assertion ci-dessus reste vraie sans rien prouver'
	);

	/* =====================================================================
	 * R2 — Le « ≈ » : approché quand la variante est inconnue, et JAMAIS sinon
	 * ===================================================================== */

	AOD_Test::group( 'Montant abandon 43.6 — R2 : le « ≈ » dit la vérité sur la précision' );

	$aod_ma_r = $aod_ma_lire( $aod_ma_lead( $aod_ma_pid_v, $aod_ma_wilaya, 'home', 1 ) );
	AOD_Test::ok( $aod_ma_r['approx'], '🔴 Produit À VARIANTES : le montant est marqué « ≈ » (le supplément est inconnu)' );
	AOD_Test::eq(
		1000.0 + $aod_ma_home,
		$aod_ma_r['nombre'],
		'…et vaut le PRIX DE BASE + livraison (jamais le supplément d’une variante choisie au hasard)'
	);
	AOD_Test::ok(
		! $aod_ma_r['tiret'],
		'🔴 …et surtout PAS un tiret : c’est tout l’objet de l’arbitrage (une colonne vide sur un catalogue à variantes ne servirait personne)'
	);

	/* =====================================================================
	 * R3 — Les trois replis : un tiret, JAMAIS un chiffre inventé
	 * ===================================================================== */

	AOD_Test::group( 'Montant abandon 43.6 — R3 : trois replis, aucun chiffre inventé' );

	AOD_Test::ok(
		$aod_ma_lire( $aod_ma_lead( $aod_ma_pid_s, 0, 'home', 1 ) )['tiret'],
		'Wilaya absente → tiret'
	);
	AOD_Test::ok(
		$aod_ma_lire( $aod_ma_lead( 999999999, $aod_ma_wilaya, 'home', 1 ) )['tiret'],
		'Produit supprimé → tiret (cas déjà connu de la 39.4)'
	);
	// 🚨 CELUI-CI N'ÉTAIT PAS AU PLAN, et c'est le plus vicieux :
	// `price_for()` normalise tout type inconnu en « home ». Sans la garde,
	// ce prospect afficherait un tarif Domicile que personne n'a choisi.
	foreach ( array( '', 'bidon', 'HOME', '0' ) as $aod_ma_del ) {
		AOD_Test::ok(
			$aod_ma_lire( $aod_ma_lead( $aod_ma_pid_s, $aod_ma_wilaya, $aod_ma_del, 1 ) )['tiret'],
			sprintf( '🔴 Type de livraison « %s » → tiret (price_for() l’aurait silencieusement pris pour « home »)', $aod_ma_del )
		);
	}
	// ANCRAGE INVERSE : les deux types VALIDES ne tombent jamais dans ce repli.
	foreach ( array( 'home', 'desk' ) as $aod_ma_del ) {
		AOD_Test::ok(
			! $aod_ma_lire( $aod_ma_lead( $aod_ma_pid_s, $aod_ma_wilaya, $aod_ma_del, 1 ) )['tiret'],
			sprintf( 'ANCRAGE INVERSE : « %s » est bien accepté (la garde ne ferme pas tout)', $aod_ma_del )
		);
	}

	/* =====================================================================
	 * R4 — Le mode tolérant est OPT-IN : le chemin d'achat n'a pas bougé
	 * ===================================================================== */

	AOD_Test::group( 'Montant abandon 43.6 — R4 : `estimate_lines()` par défaut reste STRICTE' );

	$aod_ma_form = AOD_COD_Form::instance();

	$aod_ma_strict = $aod_ma_form->estimate_lines( array( array( 'product_id' => $aod_ma_pid_v, 'qty' => 1 ) ) );
	AOD_Test::ok(
		! empty( $aod_ma_strict['errors'] ) && empty( $aod_ma_strict['lines'] ),
		'🔴 Sans le mode tolérant, un choix de variante manquant reste une ERREUR (le panier public et handle_submit() sont intacts)'
	);
	AOD_Test::ok(
		empty( $aod_ma_strict['approx'] ),
		'…et `approx` est faux : on n’a rien approché, on a refusé'
	);

	$aod_ma_tol = $aod_ma_form->estimate_lines( array( array( 'product_id' => $aod_ma_pid_v, 'qty' => 1 ) ), true );
	AOD_Test::ok(
		empty( $aod_ma_tol['errors'] ) && 1 === count( $aod_ma_tol['lines'] ),
		'Avec le mode tolérant : une ligne chiffrée, aucune erreur'
	);
	AOD_Test::ok( ! empty( $aod_ma_tol['approx'] ), '🔴 …et `approx` est VRAI — l’appelant ne peut pas ignorer que c’est une estimation' );

	// Un produit SANS variante n'est jamais marqué approché, même en tolérant :
	// sinon le drapeau perdrait tout son sens.
	$aod_ma_tol_s = $aod_ma_form->estimate_lines( array( array( 'product_id' => $aod_ma_pid_s, 'qty' => 1 ) ), true );
	AOD_Test::ok(
		empty( $aod_ma_tol_s['approx'] ),
		'🔴 ANCRAGE INVERSE : en mode tolérant, un produit SANS variante n’est PAS marqué approché'
	);
} finally {
	wp_delete_post( $aod_ma_pid_s, true );
	wp_delete_post( $aod_ma_pid_v, true );
}

AOD_Test::group( 'Montant abandon 43.6 — nettoyage' );
AOD_Test::ok( ! wc_get_product( $aod_ma_pid_s ) && ! wc_get_product( $aod_ma_pid_v ), 'Les 2 produits jetables ont bien disparu' );
