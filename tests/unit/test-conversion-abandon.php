<?php
/**
 * Convertir un panier abandonné en commande (Phase 39) — le cliquet de la phase.
 *
 * 🚦 CE QU'IL GARDE, et pourquoi c'est complémentaire des cliquets existants :
 * `test-permissions-sections.php` énumère les `wp_ajax_aod_cd_*` et exige leur
 * capability — il couvre donc DÉJÀ l'AJAX `aod_cd_convert_lead`. Ce cliquet-ci
 * garde ce qu'il ne voit pas :
 *   R1  le BOUTON (rendu HTML) exige la même cap que l'AJAX — une symétrie qu'un
 *       simple masquage d'UI trahirait ;
 *   R2  l'anti-doublon est STRUCTUREL (verrou atomique add_option + relecture sur
 *       instance fraîche APRÈS le verrou + libération avant chaque sortie) ;
 *   R3  la création est un SQUELETTE neutre — statut `pending`, jamais un envoi,
 *       aucune duplication de champs (comportement mesuré, pas grep) ;
 *   R4  la section n'ouvre QU'UNE surface AJAX (les champs/statut restent
 *       propriété de section-orders : aod_cd_order_save_info / aod_cd_order_status).
 *
 * 🚨 ÉPROUVÉ AU ROUGE (leçon Phase 25/27 : un cliquet jamais vu échouer ne prouve
 * rien). Chaque règle rougit sur la mutation qui la vise :
 *   R1 — retirer `user_can('aod_cap_confirm_orders')` autour du bouton → rouge.
 *   R2 — remplacer `add_option` par `set_transient`, ou supprimer la relecture
 *        `get_var` d'après-verrou → rouge.
 *   R3 — `set_status('aod-confirmed')` (au lieu de `pending`) dans
 *        `build_skeleton_order`, ou un `add_product` du mauvais produit → rouge.
 *   R4 — un 2ᵉ `add_action('wp_ajax_…')` dans la section → rouge.
 * (Vérifié en mutant la source puis en restaurant ; R3/R4 rejoués à l'outil.)
 *
 * Tourne DANS WordPress (WP-CLI) : il lit la vraie source par Reflection ET
 * construit une vraie commande, qu'il PURGE. Aucune vraie API, aucun résidu.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/** Source exacte d'une méthode (bornée par Reflection — pas de faux voisinage). */
if ( ! function_exists( 'aod_conv_method_src' ) ) {
	function aod_conv_method_src( $class, $method ) {
		$r     = new ReflectionMethod( $class, $method );
		$lines = file( $r->getFileName() );
		return implode( '', array_slice( $lines, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1 ) );
	}
}

/**
 * Retire commentaires et docblocks d'une source PHP — sinon un cliquet se fait
 * berner par la PROSE. 🚨 Vu au rouge ici : le docblock de `ajax_convert_lead`
 * explique « add_option est atomique LÀ OÙ set_transient() écraserait » ; un grep
 * naïf de « set_transient » y voit un anti-pattern qui n'existe pas dans le code.
 * On ne scanne QUE le code exécuté.
 */
if ( ! function_exists( 'aod_conv_code_only' ) ) {
	function aod_conv_code_only( $src ) {
		$prefix = ( 0 === strpos( ltrim( $src ), '<?php' ) ) ? '' : '<?php ';
		$out    = '';
		foreach ( token_get_all( $prefix . $src ) as $t ) {
			if ( is_array( $t ) ) {
				if ( T_COMMENT === $t[0] || T_DOC_COMMENT === $t[0] ) {
					continue;
				}
				$out .= $t[1];
			} else {
				$out .= $t;
			}
		}
		return $out;
	}
}

/* ───────────────────────── R0 — le socle est chargé ───────────────────────── */
AOD_Test::group( 'Conversion abandon (Phase 39) — R0 : socle' );

$r0 = class_exists( 'AOD_CD_Section_Abandoned' )
	&& method_exists( 'AOD_CD_Section_Abandoned', 'ajax_convert_lead' )
	&& method_exists( 'AOD_CD_Section_Abandoned', 'build_skeleton_order' )
	&& method_exists( 'AOD_CD_Section_Abandoned', 'render_row' )
	&& class_exists( 'AOD_COD_Leads' )
	&& method_exists( 'AOD_COD_Leads', 'mark_converted' );
AOD_Test::ok( $r0, 'La section, ses méthodes de conversion et AOD_COD_Leads::mark_converted existent', 'sinon les règles suivantes verdiraient sur du vide' );

if ( ! $r0 ) {
	return; // Rien à prouver sans le socle.
}

// Sources CODE-SEUL (docblocks/commentaires retirés) : on garde contre le comportement, jamais contre la prose.
$render = aod_conv_code_only( aod_conv_method_src( 'AOD_CD_Section_Abandoned', 'render_row' ) );
$ajax   = aod_conv_code_only( aod_conv_method_src( 'AOD_CD_Section_Abandoned', 'ajax_convert_lead' ) );
$file   = aod_conv_code_only( file_get_contents( ( new ReflectionClass( 'AOD_CD_Section_Abandoned' ) )->getFileName() ) );

/* ─────────────── R1 — bouton ET AJAX exigent aod_cap_confirm_orders ────────── */
AOD_Test::group( 'Conversion abandon — R1 : symétrie de permission (bouton + AJAX)' );

$btn_pos = strpos( $render, 'aod-cd-convert' );
$cap_pos = strpos( $render, "user_can( 'aod_cap_confirm_orders' )" );
AOD_Test::ok(
	false !== $btn_pos && false !== $cap_pos && $cap_pos < $btn_pos,
	'render_row : le bouton « Commander » est ceint de user_can( aod_cap_confirm_orders )',
	'la garde doit précéder le rendu du bouton (masquage vrai, pas décoratif)'
);
AOD_Test::ok(
	false !== strpos( $ajax, "user_can( 'aod_cap_confirm_orders' )" ),
	'ajax_convert_lead : la même capability est exigée côté serveur',
	'une URL forgée ne doit pas contourner le bouton masqué'
);

/* ── R1bis (39.4) — le TÉLÉPHONE seul suffit : le bouton n'exige plus de produit ── */
AOD_Test::group( 'Conversion abandon — R1bis : le téléphone seul suffit (39.4)' );

$gate = ( false !== $btn_pos ) ? substr( $render, max( 0, $btn_pos - 200 ), 200 ) : '';
AOD_Test::ok(
	false !== strpos( $gate, "user_can( 'aod_cap_confirm_orders' )" ) && false === strpos( $gate, 'product_id' ),
	'La garde du bouton « Commander » est la capability SEULE — plus de condition product_id',
	'un prospect sans produit doit voir le bouton (le client fournit tout au téléphone)'
);
AOD_Test::ok(
	false === strpos( $ajax, 'existe plus' ),
	'ajax_convert_lead ne refuse plus « produit n’existe plus » (cas devenu légitime)'
);
AOD_Test::ok(
	false !== strpos( $ajax, '422' ),
	'ajax_convert_lead garde le TÉLÉPHONE (422 si absent) — le seul prérequis d’une conversion'
);

/* ───────────────── R2 — anti-doublon structurel (verrou + relecture) ───────── */
AOD_Test::group( 'Conversion abandon — R2 : anti-doublon (31.4)' );

$addopt = strpos( $ajax, 'add_option(' );
$reread = strpos( $ajax, 'get_var(' );          // la relecture d'après-verrou (SELECT status).
$build  = strpos( $ajax, 'build_skeleton_order(' );

AOD_Test::ok( false !== $addopt, 'Verrou par add_option() — primitive ATOMIQUE (rend false si déjà posé)' );
AOD_Test::ok( false === strpos( $ajax, 'set_transient(' ), 'Jamais set_transient() comme verrou (il écraserait sans condition — un verrou qui ne verrouille pas)' );
AOD_Test::ok(
	false !== $addopt && false !== $reread && false !== $build && $addopt < $reread && $reread < $build,
	'Relecture sur instance FRAÎCHE entre le verrou et la création (verrou sérialise, n’annule pas)',
	'ordre attendu : add_option → get_var(status) → build_skeleton_order'
);
AOD_Test::ok(
	substr_count( $ajax, 'delete_option(' ) >= 3,
	'Le verrou est libéré (delete_option) avant les sorties wp_send_json (wp_die() ne déclenche pas de finally)',
	'compté : ' . substr_count( $ajax, 'delete_option(' )
);

/* ────────────── R3 — squelette neutre, aucune duplication (mesuré) ─────────── */
AOD_Test::group( 'Conversion abandon — R3 : squelette pending, zéro envoi (comportement)' );

$product = new WC_Product_Simple();
$product->set_name( 'ZZT39 Produit témoin conversion' );
$product->set_regular_price( '1500' );
$product->set_status( 'publish' );
$product->save();

$lead = (object) array(
	'id'         => 0,
	'token'      => 'ZZT39_unit',
	'product_id' => $product->get_id(),
	'name'       => 'Témoin Conversion',
	'phone'      => '0551239876',
	'wilaya'     => 16,
	'commune'    => 'Bab Ezzouar',
	'delivery'   => 'home',
	'qty'        => 3,
	'status'     => 'pending',
	'order_id'   => 0,
);

$section = AOD_CD_Section_Abandoned::instance();
$order   = AOD_Test::call( $section, 'build_skeleton_order', array( $lead, $product ) );

AOD_Test::ok( $order instanceof WC_Order, 'build_skeleton_order rend une WC_Order' );
if ( $order instanceof WC_Order ) {
	AOD_Test::eq( 'pending', $order->get_status(), 'Statut = pending (JAMAIS aod-confirmed → aucun hook d’envoi)' );
	AOD_Test::eq( 'aod-abandoned', $order->get_meta( '_aod_source' ), 'Meta _aod_source = aod-abandoned (témoin d’origine)' );
	AOD_Test::ok( '' === (string) $order->get_meta( '_aod_ship_tracking' ), 'Aucun _aod_ship_tracking (la commande n’est pas expédiée)' );
	AOD_Test::ok( '' === (string) $order->get_meta( '_aod_ship_error' ), 'Aucun _aod_ship_error (aucun envoi tenté)' );

	$item_pid = 0;
	$qty      = 0;
	foreach ( $order->get_items() as $item ) {
		$item_pid = (int) $item->get_product_id();
		$qty      = (int) $item->get_quantity();
		break;
	}
	AOD_Test::eq( (int) $product->get_id(), $item_pid, 'La ligne de commande porte le produit du prospect (aucune dérivation)' );
	AOD_Test::eq( 3, $qty, 'La quantité du prospect est reprise (max(1, qty))' );

	$order->delete( true ); // Purge — aucun résidu.
}

/* 39.4 — produit ABSENT : squelette SANS ligne d'article (le confirmateur compose au téléphone). */
$order_np = AOD_Test::call( $section, 'build_skeleton_order', array( $lead, null ) );
AOD_Test::ok( $order_np instanceof WC_Order, 'build_skeleton_order accepte un produit NULL (39.4)' );
if ( $order_np instanceof WC_Order ) {
	AOD_Test::eq( 'pending', $order_np->get_status(), 'Sans produit : statut = pending' );
	AOD_Test::eq( 'aod-abandoned', $order_np->get_meta( '_aod_source' ), 'Sans produit : _aod_source = aod-abandoned' );
	AOD_Test::eq( 0, count( $order_np->get_items() ), 'Sans produit : ZÉRO ligne d’article (composée au téléphone dans la modale)' );
	AOD_Test::ok( '' === (string) $order_np->get_meta( '_aod_ship_tracking' ), 'Sans produit : aucun tracking' );
	$order_np->delete( true );
}

/* mark_converted sort le lead de `pending` (invariant « un converti n’est plus un abandon »). */
global $wpdb;
$ltable = AOD_COD_Leads::table();
$wpdb->insert( $ltable, array( 'token' => 'ZZT39_mc', 'product_id' => $product->get_id(), 'name' => 'Témoin MC', 'phone' => '0551230000', 'wilaya' => 16, 'commune' => 'Alger', 'delivery' => 'home', 'qty' => 1, 'status' => 'pending', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
AOD_COD_Leads::mark_converted( 'ZZT39_mc', 424242 );
$mc_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $ltable WHERE token = %s", 'ZZT39_mc' ) ); // phpcs:ignore WordPress.DB
$mc_oid    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM $ltable WHERE token = %s", 'ZZT39_mc' ) ); // phpcs:ignore WordPress.DB
AOD_Test::eq( 'converted', $mc_status, 'mark_converted fait passer le lead à « converted » (il quitte la liste pending)' );
AOD_Test::eq( 424242, $mc_oid, 'mark_converted enregistre l’order_id sur le lead' );
$wpdb->query( $wpdb->prepare( "DELETE FROM $ltable WHERE token = %s", 'ZZT39_mc' ) ); // phpcs:ignore WordPress.DB

$product->delete( true ); // Purge du produit témoin.

/* ───────────── R4 — surfaces AJAX de la section : convert + delete, PAS de champs/statut ── */
AOD_Test::group( 'Conversion abandon — R4 : les surfaces AJAX de la section sont bornées' );

// 39.6 : la section portait DEUX handlers — convert_lead (39.1) et delete_leads
// (suppression groupée gérant-only). 43.3 en ajoute un TROISIÈME, lead_assign
// (assignation d'un prospect à un confirmateur, gérant-only).
//
// 🔺 HAUSSE ASSUMÉE 2 → 3. Ce que le compte borné garde n'a pas changé : AUCUN
// des trois n'écrit de champs ni de statut de COMMANDE — ces chemins restent la
// propriété de section-orders (aod_cd_order_save_info / aod_cd_order_status).
// lead_assign n'écrit qu'une colonne de la table leads (`assigned_confirmer`),
// jamais une commande. Le compte reste borné pour empêcher un 4ᵉ chemin en
// douce.
$ajax_hooks = substr_count( $file, "add_action( 'wp_ajax_" );
AOD_Test::eq( 3, $ajax_hooks, 'La section enregistre EXACTEMENT trois handlers AJAX (convert_lead + delete_leads + lead_assign)' );
AOD_Test::ok(
	false !== strpos( $file, "add_action( 'wp_ajax_aod_cd_convert_lead'" ),
	'…dont aod_cd_convert_lead (39.1)',
	'la conversion vit ici'
);
AOD_Test::ok(
	false !== strpos( $file, "add_action( 'wp_ajax_aod_cd_delete_leads'" ),
	'…et aod_cd_delete_leads (39.6, suppression groupée gérant-only)',
	'la suppression de prospects vit ici ; aucune écriture de champs/statut de commande'
);
AOD_Test::ok(
	false !== strpos( $file, "add_action( 'wp_ajax_aod_cd_lead_assign'" ),
	'…et aod_cd_lead_assign (43.3, assignation d’un prospect, gérant-only)',
	'l’assignation d’un prospect vit ici ; elle n’écrit qu’une colonne de la table leads'
);
