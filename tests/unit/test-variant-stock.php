<?php
/**
 * Stock par variante (Phase 40.1) — le contrat de données `_aod_options.stock`.
 *
 * Le stock d'une variante vit dans la valeur d'option (`_aod_options`), pas dans un
 * tableau parallèle : même invariant structurel que la traduction 7.4. Ce cliquet
 * prouve les quatre garanties de la 40.1, dont AUCUNE ne se voit à l'œil nu :
 *
 *   • PARSE — `collect_options()` lit `opt_value_stock[si][vi]` : '' = non géré
 *     (défaut/illimité), entier ≥ 0 = géré (0 = rupture), négatif → 0, non-numérique → ''.
 *     🚨 Le piège : `''` (non géré) et `0` (rupture) ne doivent JAMAIS se confondre.
 *   • REPLI — une valeur sans saisie de stock normalise vers `stock=''` et laisse
 *     toutes ses autres clés intactes (aucune régression sur les produits existants).
 *   • MONO-AXE (D1bis) — imposée dans `normalize_options()`, côté serveur : deux axes
 *     à stock → seul le PREMIER garde son stock, l'autre est ramené à ''. Retirer
 *     cette passe fait échouer ce groupe (preuve au rouge).
 *   • LECTURE — les deux `get_product_options()` (public + éditeur) exposent `stock`.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

$form     = AOD_COD_Form::instance();
$products = AOD_CD_Section_Products::instance();

/* ---------------------------------------------------------------------------
 * PARSE — collect_options() lit opt_value_stock et distingue '' de 0.
 * ------------------------------------------------------------------------- */

AOD_Test::group( 'Stock variante 40.1 — PARSE : collect_options() lit opt_value_stock' );

$aod_vs_backup_post = $_POST;
try {
	$_POST = array(
		'opt_label'       => array( 0 => 'Couleur' ),
		'opt_value_name'  => array( 0 => array( 0 => 'Rouge', 1 => 'Bleu', 2 => 'Vert', 3 => 'Noir' ) ),
		'opt_value_stock' => array( 0 => array( 0 => '12', 1 => '', 2 => '-5', 3 => 'abc' ) ),
	);
	$parsed = AOD_Test::call( $products, 'collect_options', array() );
	$vals   = isset( $parsed[0]['values'] ) ? $parsed[0]['values'] : array();

	AOD_Test::eq( '12', isset( $vals[0]['stock'] ) ? $vals[0]['stock'] : null, 'Entier saisi : stock géré = "12"' );
	AOD_Test::eq( '', isset( $vals[1]['stock'] ) ? $vals[1]['stock'] : null, 'Champ vide : stock NON géré = "" (jamais confondu avec 0)' );
	AOD_Test::eq( '0', isset( $vals[2]['stock'] ) ? $vals[2]['stock'] : null, 'Négatif : borné à "0" (géré, en rupture)' );
	AOD_Test::eq( '', isset( $vals[3]['stock'] ) ? $vals[3]['stock'] : null, 'Non-numérique : ignoré → "" (non géré)' );
	// Le parse ne touche à rien d'autre : le nom canonique reste la clé.
	AOD_Test::eq( 'Rouge', isset( $vals[0]['name'] ) ? $vals[0]['name'] : null, 'Le parse du stock ne perturbe pas le nom (clé de commande)' );
} finally {
	$_POST = $aod_vs_backup_post;
}

/* ---------------------------------------------------------------------------
 * NORMALISE + REPLI — normalize_options() pose stock et n'altère rien d'autre.
 * ------------------------------------------------------------------------- */

AOD_Test::group( 'Stock variante 40.1 — NORMALISE : stock stocké, repli non-régressif' );

$sections_sans_stock = array(
	array(
		'label'  => 'Taille',
		'visual' => false,
		'values' => array(
			array( 'name' => 'S', 'name_ar' => 'صغير', 'price' => '', 'image_id' => 0, 'hex' => '' ),
			array( 'name' => 'L', 'name_ar' => 'كبير', 'price' => '500', 'image_id' => 0, 'hex' => '' ),
		),
	),
);
$norm_sans = AOD_Test::call( $products, 'normalize_options', array( $sections_sans_stock ) );
AOD_Test::eq( '', $norm_sans[0]['values'][0]['stock'], 'Valeur sans stock → "" (non géré)' );
AOD_Test::eq( '', $norm_sans[0]['values'][1]['stock'], 'Autre valeur sans stock → "" (non géré)' );
// Repli : les clés préexistantes traversent sans dommage.
AOD_Test::eq( 'كبير', $norm_sans[0]['values'][1]['name_ar'], 'Repli : la traduction 7.4 est intacte' );
AOD_Test::eq( '500', $norm_sans[0]['values'][1]['price'], 'Repli : le supplément de prix est intact' );

$sections_avec_stock = array(
	array(
		'label'  => 'Couleur',
		'visual' => false,
		'values' => array(
			array( 'name' => 'Rouge', 'stock' => '7' ),
			array( 'name' => 'Bleu', 'stock' => '0' ),
			array( 'name' => 'Vert', 'stock' => '' ),
		),
	),
);
$norm_avec = AOD_Test::call( $products, 'normalize_options', array( $sections_avec_stock ) );
AOD_Test::eq( '7', $norm_avec[0]['values'][0]['stock'], 'Stock géré conservé ("7")' );
AOD_Test::eq( '0', $norm_avec[0]['values'][1]['stock'], 'Stock 0 conservé (rupture, pas "")' );
AOD_Test::eq( '', $norm_avec[0]['values'][2]['stock'], 'Stock vide conservé vide (non géré)' );

/* ---------------------------------------------------------------------------
 * MONO-AXE (D1bis) — un seul axe porte un stock. Preuve au rouge.
 * ------------------------------------------------------------------------- */

AOD_Test::group( 'Stock variante 40.1 — MONO-AXE : un seul axe à stock (D1bis, côté serveur)' );

$deux_axes = array(
	array(
		'label'  => 'Couleur',
		'values' => array(
			array( 'name' => 'Rouge', 'stock' => '5' ),
			array( 'name' => 'Bleu', 'stock' => '3' ),
		),
	),
	array(
		'label'  => 'Taille',
		'values' => array(
			array( 'name' => 'S', 'stock' => '9' ),
			array( 'name' => 'L', 'stock' => '4' ),
		),
	),
);
$norm2 = AOD_Test::call( $products, 'normalize_options', array( $deux_axes ) );
// La PREMIÈRE section (Couleur) garde son stock…
AOD_Test::eq( '5', $norm2[0]['values'][0]['stock'], 'Axe 1 (Couleur) : garde son stock' );
AOD_Test::eq( '3', $norm2[0]['values'][1]['stock'], 'Axe 1 (Couleur) : garde son stock (2ᵉ valeur)' );
// …et le SECOND axe est ramené à '' (sinon décrément ambigu).
AOD_Test::eq( '', $norm2[1]['values'][0]['stock'], '🔴 Axe 2 (Taille) : stock effacé — un seul axe à stock' );
AOD_Test::eq( '', $norm2[1]['values'][1]['stock'], '🔴 Axe 2 (Taille) : stock effacé (2ᵉ valeur)' );

// Contrôle : si SEUL le 2ᵉ axe porte un stock, c'est LUI l'axe à stock.
$stock_axe2 = array(
	array( 'label' => 'Couleur', 'values' => array( array( 'name' => 'Rouge', 'stock' => '' ) ) ),
	array( 'label' => 'Taille', 'values' => array( array( 'name' => 'L', 'stock' => '6' ) ) ),
);
$norm3 = AOD_Test::call( $products, 'normalize_options', array( $stock_axe2 ) );
AOD_Test::eq( '', $norm3[0]['values'][0]['stock'], 'Axe 1 sans stock reste sans stock' );
AOD_Test::eq( '6', $norm3[1]['values'][0]['stock'], 'Le PREMIER axe à porter un stock est l’axe à stock (ici le 2ᵉ)' );

/* ---------------------------------------------------------------------------
 * LECTURE — les deux get_product_options() exposent `stock`.
 * ------------------------------------------------------------------------- */

AOD_Test::group( 'Stock variante 40.1 — LECTURE : public + éditeur exposent stock' );

$p = new WC_Product_Simple();
$p->set_name( 'Produit 40.1 avec stock de variante' );
$p->update_meta_data( '_aod_options', array(
	array(
		'label'  => 'Couleur',
		'visual' => false,
		'values' => array(
			array( 'name' => 'Rouge', 'stock' => '8' ),
			array( 'name' => 'Bleu', 'stock' => '0' ),
			array( 'name' => 'Vert', 'stock' => '' ),
		),
	),
) );
$pid = $p->save();

try {
	// Public (protected → réflexion).
	$pub = AOD_Test::call( $form, 'get_product_options', array( wc_get_product( $pid ) ) );
	AOD_Test::eq( 8, $pub[0]['values'][0]['stock'], 'Public : stock géré exposé en int (8)' );
	AOD_Test::eq( 0, $pub[0]['values'][1]['stock'], 'Public : stock 0 exposé (rupture)' );
	AOD_Test::eq( '', $pub[0]['values'][2]['stock'], 'Public : non géré exposé "" (illimité)' );

	// Éditeur (public).
	$edit = $products->get_product_options( wc_get_product( $pid ) );
	AOD_Test::eq( 8, $edit[0]['values'][0]['stock'], 'Éditeur : stock géré relu (8) pour la saisie' );
	AOD_Test::eq( 0, $edit[0]['values'][1]['stock'], 'Éditeur : stock 0 relu' );
	AOD_Test::eq( '', $edit[0]['values'][2]['stock'], 'Éditeur : non géré relu ""' );
} finally {
	wp_delete_post( $pid, true );
}

/* ---------------------------------------------------------------------------
 * SAISIE (40.2) — render_option_value() rend le champ stock, et jamais « 0 »
 * pour un stock NON géré.
 * ------------------------------------------------------------------------- */

AOD_Test::group( 'Stock variante 40.2 — SAISIE : render_option_value expose le champ stock' );

ob_start();
AOD_Test::call( $products, 'render_option_value', array( 0, 0, array( 'name' => 'Rouge', 'stock' => 8 ) ) );
$html_managed = (string) ob_get_clean();
AOD_Test::ok( false !== strpos( $html_managed, 'name="opt_value_stock[0][0]"' ), 'Le champ opt_value_stock[0][0] est rendu' );
AOD_Test::ok( false !== strpos( $html_managed, 'class="aod-cd-opt-stock"' ), 'Le champ porte la classe aod-cd-opt-stock' );
AOD_Test::ok( false !== strpos( $html_managed, 'aod-cd-opt-stock" value="8"' ), 'Stock géré : value="8"' );
AOD_Test::ok( false !== strpos( $html_managed, 'data-guard=' ), 'Le champ porte data-guard (message garde-fou mono-axe)' );

ob_start();
AOD_Test::call( $products, 'render_option_value', array( 0, 0, array( 'name' => 'Vert', 'stock' => '' ) ) );
$html_unmanaged = (string) ob_get_clean();
AOD_Test::ok( false !== strpos( $html_unmanaged, 'name="opt_value_stock[0][0]"' ), 'Non géré : le champ existe quand même (vide)' );
AOD_Test::ok( false === strpos( $html_unmanaged, 'aod-cd-opt-stock" value="0"' ), '🔴 Non géré : JAMAIS value="0" (0 = rupture, ≠ non géré)' );

ob_start();
AOD_Test::call( $products, 'render_option_value', array( 0, 0, array( 'name' => 'Noir', 'stock' => 0 ) ) );
$html_rupture = (string) ob_get_clean();
AOD_Test::ok( false !== strpos( $html_rupture, 'aod-cd-opt-stock" value="0"' ), 'Rupture (stock 0) : value="0" bien rendu (≠ non géré vide)' );

// Repli d'un produit d'AVANT la 40.1 (méta sans clé `stock`) : lecture sans erreur, "".
$p_old = new WC_Product_Simple();
$p_old->set_name( 'Produit d’avant 40.1' );
$p_old->update_meta_data( '_aod_options', array(
	array( 'label' => 'Taille', 'visual' => false, 'values' => array( array( 'name' => 'M', 'price' => '' ) ) ),
) );
$old_id = $p_old->save();
try {
	$pub = AOD_Test::call( $form, 'get_product_options', array( wc_get_product( $old_id ) ) );
	AOD_Test::eq( '', $pub[0]['values'][0]['stock'], 'Produit d’avant 40.1 : clé stock absente → "" (repli, aucune erreur)' );
} finally {
	wp_delete_post( $old_id, true );
}

/* ---------------------------------------------------------------------------
 * STOCK (40.3) — somme + détail + pastille cohérente (pas de divergence).
 * ------------------------------------------------------------------------- */

AOD_Test::group( 'Stock variante 40.3 — STOCK : somme, items, pastille' );

$stocksec = AOD_CD_Section_Stock::instance();

$pv = new WC_Product_Simple();
$pv->set_name( 'Produit 40.3 à variantes' );
$pv->update_meta_data( '_aod_options', array(
	array( 'label' => 'Couleur', 'visual' => false, 'values' => array(
		array( 'name' => 'Rouge', 'stock' => '12' ),
		array( 'name' => 'Bleu', 'stock' => '0' ),   // rupture (dans les items)
		array( 'name' => 'Vert', 'stock' => '' ),    // non géré (illimité) → hors somme/items
	) ),
	array( 'label' => 'Taille', 'visual' => false, 'values' => array(
		array( 'name' => 'M', 'stock' => '' ),
		array( 'name' => 'L', 'stock' => '' ),
	) ),
) );
$pvid = $pv->save();

try {
	$sum = $stocksec->variant_stock_summary( wc_get_product( $pvid ) );
	AOD_Test::ok( true === $sum['managed'], 'Produit à variantes : managed=true' );
	AOD_Test::eq( 12, $sum['total'], 'Somme = 12 (Rouge 12 + Bleu 0 ; Vert non géré exclu)' );
	AOD_Test::eq( 2, count( $sum['items'] ), 'items = 2 valeurs gérées (Vert non géré exclu)' );
	AOD_Test::eq( 'Rouge', $sum['items'][0]['name'], 'Premier item = Rouge' );
	AOD_Test::eq( 12, $sum['items'][0]['stock'], 'Rouge → 12' );
	AOD_Test::eq( 0, $sum['items'][1]['stock'], 'Bleu → 0 (rupture, présent dans les items)' );

	// Pastille cohérente : badge + somme, JAMAIS « — » (sinon divergence Produits ⇄ Stock).
	$pill = $stocksec->stock_pill( wc_get_product( $pvid ) );
	AOD_Test::ok( false === strpos( $pill, '—' ), '🔴 stock_pill d’un produit à variantes ≠ tiret (pas de divergence)' );
	AOD_Test::ok( false !== strpos( $pill, '>12<' ) || false !== strpos( $pill, '12' ), 'stock_pill affiche la somme (12)' );
} finally {
	wp_delete_post( $pvid, true );
}

// Repli : produit sans stock de variante → managed=false, comportement d'avant.
$pn = new WC_Product_Simple();
$pn->set_name( 'Produit 40.3 sans stock variante' );
$pn->update_meta_data( '_aod_options', array(
	array( 'label' => 'Taille', 'visual' => false, 'values' => array( array( 'name' => 'M', 'price' => '' ) ) ),
) );
$pnid = $pn->save();
try {
	$sum2 = $stocksec->variant_stock_summary( wc_get_product( $pnid ) );
	AOD_Test::ok( false === $sum2['managed'], 'Produit sans stock variante : managed=false (repli)' );
	AOD_Test::eq( 0, $sum2['total'], 'Repli : total 0' );
	// Sans gestion Woo ni variante → la pastille reste « — » (comportement inchangé).
	AOD_Test::ok( false !== strpos( $stocksec->stock_pill( wc_get_product( $pnid ) ), '—' ), 'Repli : pastille « — » (ni Woo ni variante)' );
} finally {
	wp_delete_post( $pnid, true );
}

/* ---------------------------------------------------------------------------
 * RUPTURE (40.4) — garde SERVEUR (le cœur) + rendu grisé côté acheteur.
 * ------------------------------------------------------------------------- */

AOD_Test::group( 'Stock variante 40.4 — RUPTURE : garde serveur + rendu' );

$rupt = new WC_Product_Simple();
$rupt->set_name( 'Produit 40.4 rupture' );
$rupt->set_regular_price( '1000' );
$rupt->set_status( 'publish' );
$rupt->update_meta_data( '_aod_options', array(
	array( 'label' => 'Couleur', 'visual' => false, 'values' => array(
		array( 'name' => 'Rouge', 'stock' => '0' ),   // rupture (stock géré ≤ 0)
		array( 'name' => 'Bleu', 'stock' => '5' ),     // disponible
		array( 'name' => 'Vert', 'stock' => '' ),      // non géré (illimité)
	) ),
) );
$rid  = $rupt->save();
$root = wc_get_product( $rid );

try {
	// 🚨 GARDE SERVEUR : choisir la variante en rupture ne crée AUCUNE ligne.
	$errA  = array();
	$argsA = array( array( array( 'product_id' => $rid, 'qty' => 1, 'opt' => array( 0 => 'Rouge' ) ) ), $root );
	$argsA[] = &$errA; // $errors est passé PAR RÉFÉRENCE.
	$linesA = AOD_Test::call( $form, 'build_order_lines', $argsA );
	AOD_Test::eq( 0, count( $linesA ), '🔴 Variante en rupture (Rouge/0) : AUCUNE ligne créée (garde serveur)' );
	AOD_Test::ok( ! empty( $errA ), 'Variante en rupture : une erreur est remontée à l’acheteur' );

	// Variante disponible : 1 ligne.
	$errB  = array();
	$argsB = array( array( array( 'product_id' => $rid, 'qty' => 1, 'opt' => array( 0 => 'Bleu' ) ) ), $root );
	$argsB[] = &$errB;
	$linesB = AOD_Test::call( $form, 'build_order_lines', $argsB );
	AOD_Test::eq( 1, count( $linesB ), 'Variante disponible (Bleu/5) : 1 ligne créée' );
	AOD_Test::ok( empty( $errB ), 'Variante disponible : aucune erreur' );

	// Variante NON gérée (illimitée) : jamais bloquée.
	$errC  = array();
	$argsC = array( array( array( 'product_id' => $rid, 'qty' => 1, 'opt' => array( 0 => 'Vert' ) ) ), $root );
	$argsC[] = &$errC;
	$linesC = AOD_Test::call( $form, 'build_order_lines', $argsC );
	AOD_Test::eq( 1, count( $linesC ), 'Variante non gérée (Vert/illimité) : 1 ligne créée (jamais bloquée)' );

	// Rendu : SEULE la variante en rupture porte la classe + le badge.
	$opts = AOD_Test::call( $form, 'get_product_options', array( $root ) );
	$html = AOD_Test::call( $form, 'option_sections_html', array( $rid, $opts ) );
	AOD_Test::eq( 1, substr_count( $html, 'aod-cod__opt--rupture' ), 'Rendu : une SEULE pastille en rupture (Rouge, pas Bleu/Vert)' );
	AOD_Test::ok( false !== strpos( $html, 'Rupture de stock' ), 'Rendu : badge « Rupture de stock » présent' );
	AOD_Test::ok( false !== strpos( $html, 'disabled' ), 'Rendu : la pastille en rupture est disabled' );
} finally {
	wp_delete_post( $rid, true );
}

/* ---------------------------------------------------------------------------
 * DÉCRÉMENT (40.5) — le stock baisse à la CONFIRMATION, remonte à l'ANNULATION,
 * une seule fois de chaque, jamais une variante non gérée.
 * ------------------------------------------------------------------------- */

AOD_Test::group( 'Stock variante 40.5 — DÉCRÉMENT : confirmation, borne, non géré, idempotence' );

$orders = AOD_CD_Section_Orders::instance();

/**
 * Fabrique un produit à une variante « Couleur » et lit/écrit son stock brut.
 * Renvoie l'ID produit ; le stock d'une valeur se relit avec aod_vs_read_stock().
 */
$aod_vs_make_product = function ( $name, $values ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->update_meta_data( '_aod_options', array(
		array( 'label' => 'Couleur', 'visual' => false, 'values' => $values ),
	) );
	return $p->save();
};
$aod_vs_read_stock = function ( $pid, $value_name ) {
	$opts = wc_get_product( $pid )->get_meta( '_aod_options' );
	foreach ( $opts[0]['values'] as $v ) {
		if ( $v['name'] === $value_name ) {
			return isset( $v['stock'] ) ? (string) $v['stock'] : null;
		}
	}
	return null;
};
/** Commande à une ligne : produit $pid, variante « Couleur=$choice », quantité $qty. */
$aod_vs_make_order = function ( $pid, $choice, $qty ) {
	$order = new WC_Order();
	$item  = new WC_Order_Item_Product();
	$item->set_product( wc_get_product( $pid ) );
	$item->set_quantity( $qty );
	$item->add_meta_data( 'Couleur', $choice, true ); // clé = label FR, valeur = name FR canonique.
	$order->add_item( $item );
	$order->save();
	return $order->get_id();
};
/**
 * Supprime VRAIMENT une commande — jamais `wp_delete_post()` sur ce site en
 * HPOS (cf. TEMPLATE.md §3) : il ne détruit que le post-placeholder
 * cosmétique, jamais la ligne réelle de `wp_wc_orders` (patron
 * `test-assignation-commandes.php`, 42.7).
 */
$aod_vs_del_order = function ( $oid ) {
	$o = wc_get_order( $oid );
	if ( $o instanceof WC_Order ) {
		$o->delete( true );
	}
};

// 1) Décrément à la confirmation : Rouge 12, qty 3 → 9 ; drapeau posé.
$pid1 = $aod_vs_make_product( 'P40.5 décrément', array(
	array( 'name' => 'Rouge', 'stock' => '12' ),
	array( 'name' => 'Bleu', 'stock' => '2' ),
	array( 'name' => 'Vert', 'stock' => '' ),
) );
$oid1 = $aod_vs_make_order( $pid1, 'Rouge', 3 );
try {
	$orders->reduce_variant_stock( $oid1 );
	AOD_Test::eq( '9', $aod_vs_read_stock( $pid1, 'Rouge' ), 'Confirmation : Rouge 12 − qty 3 = 9' );
	AOD_Test::eq( 'yes', (string) wc_get_order( $oid1 )->get_meta( '_aod_variant_stock_reduced' ), 'Drapeau _aod_variant_stock_reduced posé' );
	AOD_Test::eq( '2', $aod_vs_read_stock( $pid1, 'Bleu' ), 'Bleu (non choisi) : inchangé (2)' );
	AOD_Test::eq( '', $aod_vs_read_stock( $pid1, 'Vert' ), 'Vert (non choisi) : inchangé ("")' );

	// 2) Idempotence décrément : rappeler → inchangé (drapeau garde).
	$orders->reduce_variant_stock( $oid1 );
	AOD_Test::eq( '9', $aod_vs_read_stock( $pid1, 'Rouge' ), 'Idempotence : 2ᵉ appel ne redécrémente pas (reste 9)' );

	// 5) Ré-incrément à l'annulation : Rouge 9 + qty 3 = 12 ; drapeau levé.
	$orders->restore_variant_stock( $oid1 );
	AOD_Test::eq( '12', $aod_vs_read_stock( $pid1, 'Rouge' ), 'Annulation : Rouge 9 + qty 3 = 12 (restitué)' );
	AOD_Test::eq( '', (string) wc_get_order( $oid1 )->get_meta( '_aod_variant_stock_reduced' ), 'Drapeau remis à "" après restitution' );

	// 5bis) Idempotence ré-incrément : rappeler → inchangé (drapeau levé).
	$orders->restore_variant_stock( $oid1 );
	AOD_Test::eq( '12', $aod_vs_read_stock( $pid1, 'Rouge' ), 'Idempotence : 2ᵉ annulation ne re-restitue pas (reste 12)' );
} finally {
	$aod_vs_del_order( $oid1 );
	wp_delete_post( $pid1, true );
}

// 3) Borne à 0 : Bleu 2, qty 5 → max(0, −3) = 0 (jamais négatif).
$pid2 = $aod_vs_make_product( 'P40.5 borne', array(
	array( 'name' => 'Rouge', 'stock' => '12' ),
	array( 'name' => 'Bleu', 'stock' => '2' ),
	array( 'name' => 'Vert', 'stock' => '' ),
) );
$oid2 = $aod_vs_make_order( $pid2, 'Bleu', 5 );
try {
	$orders->reduce_variant_stock( $oid2 );
	AOD_Test::eq( '0', $aod_vs_read_stock( $pid2, 'Bleu' ), '🔴 Borne : Bleu 2 − qty 5 = 0 (jamais négatif)' );
} finally {
	$aod_vs_del_order( $oid2 );
	wp_delete_post( $pid2, true );
}

// 4) Variante NON gérée : Vert '', qty 2 → reste '' (jamais mutée, jamais "0").
$pid3 = $aod_vs_make_product( 'P40.5 non géré', array(
	array( 'name' => 'Rouge', 'stock' => '12' ),
	array( 'name' => 'Vert', 'stock' => '' ),
) );
$oid3 = $aod_vs_make_order( $pid3, 'Vert', 2 );
try {
	$orders->reduce_variant_stock( $oid3 );
	AOD_Test::eq( '', $aod_vs_read_stock( $pid3, 'Vert' ), '🔴 Non géré : Vert "" reste "" (jamais muté en "0")' );
	// Rien de géré touché → pas de drapeau (rien à restituer).
	AOD_Test::eq( '', (string) wc_get_order( $oid3 )->get_meta( '_aod_variant_stock_reduced' ), 'Aucune variante gérée touchée → pas de drapeau' );
} finally {
	$aod_vs_del_order( $oid3 );
	wp_delete_post( $pid3, true );
}

// 6) Ré-incrément SANS confirmation préalable : commande sans drapeau → inchangée.
$pid4 = $aod_vs_make_product( 'P40.5 annulation sans confirmation', array(
	array( 'name' => 'Rouge', 'stock' => '7' ),
) );
$oid4 = $aod_vs_make_order( $pid4, 'Rouge', 3 );
try {
	$orders->restore_variant_stock( $oid4 ); // jamais confirmée → drapeau absent.
	AOD_Test::eq( '7', $aod_vs_read_stock( $pid4, 'Rouge' ), 'Annulation sans confirmation : Rouge inchangé (7, pas 10)' );
} finally {
	$aod_vs_del_order( $oid4 );
	wp_delete_post( $pid4, true );
}
