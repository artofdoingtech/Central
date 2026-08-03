<?php
/**
 * Fixtures de la porte `panier` (clôture Phase 35) — create | rupture | cleanup.
 *
 * create : capture l'état RÉEL de l'option `aod_cod_cart` (leçon D26 : la
 * porte ne doit pas laisser le clone dans son état de test), puis sème
 * 3 produits publiés marqués `_aod_recette_p35` — Alpha et Beta SIMPLES,
 * Gamma À VARIANTES (3 couleurs, stock produit borné à 2, photo produit +
 * photo de variante). Sort `P35=1`, URL1/2/3 et les deux URL d'images.
 * 🚨 Gamma existe parce qu'une porte semée de produits simples ne dit rien de
 * la moitié du catalogue qu'un clone rencontre : le libellé des cartes,
 * l'image des lignes, l'article refusé par le funnel et la ligne refusée sur
 * sa quantité ne s'observent QUE sur un produit à variantes ou à stock borné.
 *
 * rupture : met la variante « Bleu » de Gamma à 0 — le geste du gérant qui
 * épuise une couleur PENDANT qu'un panier la contient. C'est ce scénario, et
 * lui seul, qui produisait un article fantôme (ni vivant, ni mort, invisible
 * et bloquant la commande).
 *
 * cleanup : purge les produits marqués, LEURS pièces jointes (fichiers
 * compris), les commandes témoins (nom complet exact « Recette Panier35 » —
 * purge par nom assumée et bornée au dev local, même précédent que la porte
 * `cod` ; le formulaire COD range TOUT le nom dans billing_first_name, une
 * requête sur last_name ne matcherait jamais), les DEUX verrous anti-spam
 * posés par la commande témoin (téléphone ET IP), et RESTAURE l'option
 * capturée (y compris « absente »).
 *
 * Usage : wp eval-file /scripts/recette-seed-panier.php <create|rupture|cleanup>
 *
 * @package AOD_Recette
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aod_p35_mode = isset( $args[0] ) ? $args[0] : 'create';

if ( 'create' === $aod_p35_mode ) {
	// L'état d'origine de l'option, sentinelle comprise : « absente » n'est pas « 0 ».
	if ( false === get_option( '_aod_recette_p35_orig', false ) ) {
		$aod_p35_orig = get_option( 'aod_cod_cart', '__ABSENTE__' );
		update_option( '_aod_recette_p35_orig', array( 'v' => $aod_p35_orig ), false );
	}

	/* --- Deux images DISTINCTES, générées par GD (aucun réseau) --------------
	 * Sans elles, « la ligne du panier montre la photo de la VARIANTE » ne peut
	 * pas s'asserter : une variante sans image retombe — légitimement — sur la
	 * photo du produit, et l'assertion serait verte en mesurant le repli. Deux
	 * aplats de couleurs franches suffisent : on compare des URL, pas des pixels.
	 * Précédent : recette-seed-phase8.php. Marquées, purgées au cleanup. */
	$aod_p35_att = function ( $label, $r, $g, $b ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}
		$up   = wp_upload_dir();
		$path = trailingslashit( $up['path'] ) . 'recette-p35-' . $label . '-' . wp_generate_password( 6, false ) . '.jpg';
		$im   = imagecreatetruecolor( 600, 600 );
		imagefilledrectangle( $im, 0, 0, 599, 599, imagecolorallocate( $im, $r, $g, $b ) );
		imagejpeg( $im, $path, 85 );
		imagedestroy( $im );
		$id = wp_insert_attachment(
			array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'RecetteP35 — ' . $label, 'post_status' => 'inherit' ),
			$path
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
		update_post_meta( $id, '_aod_recette_p35', '1' );
		return (int) $id;
	};
	$aod_p35_img_prod  = $aod_p35_att( 'produit', 20, 20, 20 );   // photo du PRODUIT
	$aod_p35_img_rouge = $aod_p35_att( 'rouge', 200, 30, 30 );    // photo de la VARIANTE Rouge

	// Gamma porte une section d'options — et c'est tout l'intérêt du 3ᵉ témoin.
	// La porte n'avait que des produits SIMPLES : elle ne pouvait donc rien dire
	// de la moitié du catalogue réel (les deux produits de démonstration ont une
	// couleur), ni voir que la surimpression de leurs cartes affichait un autre
	// libellé. Un témoin qui ne ressemble pas au catalogue rend vert sur la seule
	// moitié qu'on lui montre.
	$aod_p35_ids = array();
	foreach ( array(
		array( 'RecetteP35 Alpha', '1000', array() ),
		array( 'RecetteP35 Beta', '500', array() ),
		array( 'RecetteP35 Gamma', '700', array(
			array(
				'label'  => 'Couleur',
				// `visual` : c'est la section dont la fiche produit change la grande
				// photo — donc celle dont le panier doit reprendre l'image.
				'visual' => true,
				'values' => array(
					// Rouge PORTE une image ; Vert n'en a pas → le repli sur la photo
					// du produit est éprouvé par la même fixture, sur la même ligne.
					array( 'name' => 'Rouge', 'price' => 0, 'stock' => '5', 'image_id' => $aod_p35_img_rouge ),
					array( 'name' => 'Vert', 'price' => 0, 'stock' => '5' ),
					// Bleu naît EN STOCK : la porte l'ajoute au panier par le vrai
					// geste d'achat, puis le mode `rupture` le met à 0 — c'est le
					// scénario réel (le gérant épuise une couleur pendant que le
					// panier de l'acheteur vit), et le seul qui produisait un
					// article fantôme.
					array( 'name' => 'Bleu', 'price' => 0, 'stock' => '5' ),
				),
			),
		) ),
	) as $aod_p35_def ) {
		$aod_p35_p = new WC_Product_Simple();
		$aod_p35_p->set_name( $aod_p35_def[0] );
		$aod_p35_p->set_regular_price( $aod_p35_def[1] );
		$aod_p35_p->set_status( 'publish' );
		$aod_p35_p->save();
		$aod_p35_p->update_meta_data( '_aod_recette_p35', '1' );
		if ( $aod_p35_def[2] ) {
			$aod_p35_p->update_meta_data( '_aod_options', $aod_p35_def[2] );
		}
		$aod_p35_p->save();
		$aod_p35_ids[] = $aod_p35_p->get_id();
	}

	// Photo du PRODUIT sur Gamma : c'est elle que la ligne du panier montrait pour
	// TOUTE variante, et c'est donc elle qui doit disparaître de la ligne « Rouge »
	// — et rester sur la ligne « Vert », qui n'a pas d'image à elle.
	$aod_p35_g = wc_get_product( $aod_p35_ids[2] );
	if ( $aod_p35_img_prod ) {
		$aod_p35_g->set_image_id( $aod_p35_img_prod );
	}
	// Stock produit BORNÉ à 2 : c'est ce qui permet d'éprouver la ligne refusée
	// pour sa QUANTITÉ (un « + » de trop), distincte de l'indisponible.
	$aod_p35_g->set_manage_stock( true );
	$aod_p35_g->set_stock_quantity( 2 );
	$aod_p35_g->save();

	echo "P35=1\n";
	echo 'URL1=' . get_permalink( $aod_p35_ids[0] ) . "\n";
	echo 'URL2=' . get_permalink( $aod_p35_ids[1] ) . "\n";
	echo 'URL3=' . get_permalink( $aod_p35_ids[2] ) . "\n";
	// Taille `woocommerce_thumbnail` — exactement celle que sert le panneau du
	// panier : la porte compare des URL, pas des pixels.
	echo 'IMGPROD=' . ( $aod_p35_img_prod ? wp_get_attachment_image_url( $aod_p35_img_prod, 'woocommerce_thumbnail' ) : '' ) . "\n";
	echo 'IMGVAR=' . ( $aod_p35_img_rouge ? wp_get_attachment_image_url( $aod_p35_img_rouge, 'woocommerce_thumbnail' ) : '' ) . "\n";
	return;
}

/* --- rupture : le gérant épuise « Bleu » pendant que le panier vit ---------- */

if ( 'rupture' === $aod_p35_mode ) {
	$aod_p35_g = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'any',
		'posts_per_page' => 20,
		'meta_key'       => '_aod_recette_p35',
		'meta_value'     => '1',
		'fields'         => 'ids',
	) );
	foreach ( $aod_p35_g as $aod_p35_gid ) {
		$aod_p35_p = wc_get_product( $aod_p35_gid );
		$aod_p35_o = $aod_p35_p ? $aod_p35_p->get_meta( '_aod_options' ) : array();
		if ( ! is_array( $aod_p35_o ) || ! $aod_p35_o ) {
			continue;
		}
		$aod_p35_hit = false;
		foreach ( $aod_p35_o as $aod_p35_si => $aod_p35_sec ) {
			foreach ( $aod_p35_sec['values'] as $aod_p35_vi => $aod_p35_val ) {
				if ( 'Bleu' === $aod_p35_val['name'] ) {
					$aod_p35_o[ $aod_p35_si ]['values'][ $aod_p35_vi ]['stock'] = '0';
					$aod_p35_hit = true;
				}
			}
		}
		if ( $aod_p35_hit ) {
			$aod_p35_p->update_meta_data( '_aod_options', $aod_p35_o );
			$aod_p35_p->save();
			echo "P35RUPTURE=1\n";
			return;
		}
	}
	echo "P35RUPTURE=0\n";
	return;
}

/* --- cleanup ------------------------------------------------------------- */

// 1) Commandes témoins (avant les produits : leurs lignes les référencent).
// ⚠️ handle_submit() pose le nom ENTIER dans billing_first_name (last_name
// vide) : filtrer par last_name ne trouverait jamais rien. Même parcours que
// la purge de la porte `cod` (commande-cod.js) : nom complet reconstitué.
foreach ( wc_get_orders( array( 'limit' => -1, 'status' => 'any' ) ) as $aod_p35_o ) {
	if ( 'Recette Panier35' === trim( $aod_p35_o->get_billing_first_name() . ' ' . $aod_p35_o->get_billing_last_name() ) ) {
		$aod_p35_o->delete( true );
	}
}

// 2) Produits marqués.
$aod_p35_posts = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => 'any',
	'posts_per_page' => 20,
	'meta_key'       => '_aod_recette_p35',
	'meta_value'     => '1',
	'fields'         => 'ids',
) );
foreach ( $aod_p35_posts as $aod_p35_id ) {
	wp_delete_post( $aod_p35_id, true );
}

// 2bis) Pièces jointes de la porte (photo produit + photo variante) — `true`
// supprime AUSSI les fichiers et les tailles dérivées : une porte qui laisse
// grossir `uploads/` à chaque passage n'est pas rejouable, elle est cumulative.
$aod_p35_atts = get_posts( array(
	'post_type'      => 'attachment',
	'post_status'    => 'any',
	'posts_per_page' => 20,
	'meta_key'       => '_aod_recette_p35',
	'meta_value'     => '1',
	'fields'         => 'ids',
) );
foreach ( $aod_p35_atts as $aod_p35_att_id ) {
	wp_delete_attachment( $aod_p35_att_id, true );
}

// 3) Verrous anti-spam posés par la commande témoin (préfixes bornés — jamais
// les caches transporteurs).
// 🚨 LES DEUX, pas seulement le téléphone. La porte passe une VRAIE commande :
// elle incrémente aussi le coupe-circuit par IP (`aod_cod_ipf_`, 10/h), et au
// 11ᵉ passage dans l'heure la commande était refusée — la porte rougissait sur
// « La commande passe », un message qui accuse le produit. `panier-cod.js`
// purge désormais les deux AVANT de soumettre (helper partagé
// `PURGE_VERROUS_COD`) ; ce filet-ci, lui, court même quand la porte meurt en
// chemin. Un filet qui ne rattrape qu'un verrou sur deux laisse le clone dans
// l'état exact qui fera rougir la prochaine exécution.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%aod_cod_ph_%' OR option_name LIKE '_transient_timeout%aod_cod_ph_%' OR option_name LIKE '_transient%aod_cod_ipf_%' OR option_name LIKE '_transient_timeout%aod_cod_ipf_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purge de recette, motifs littéraux bornés.

// 4) Restauration de l'option — « absente » redevient ABSENTE, pas '0'.
$aod_p35_saved = get_option( '_aod_recette_p35_orig', false );
if ( is_array( $aod_p35_saved ) ) {
	if ( '__ABSENTE__' === $aod_p35_saved['v'] ) {
		delete_option( 'aod_cod_cart' );
	} else {
		update_option( 'aod_cod_cart', $aod_p35_saved['v'] );
	}
	delete_option( '_aod_recette_p35_orig' );
}

echo "P35CLEAN=1\n";
