<?php
/**
 * Fixtures de la recette « description riche » (Phase 8, étape 8.4).
 *
 * Créer  :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase8.php create
 * Purger :  docker compose exec -T wpcli wp eval-file /scripts/recette-seed-phase8.php cleanup
 *
 * `create` écrit sur stdout, une par ligne, les clés que `tools/recette/run.sh`
 * transmet au script navigateur :  RICH=<id>  PLAIN=<id>  IMG=<id>  VID=<id>
 *
 * Tout ce qui est créé (produits ET pièces jointes) porte la méta
 * `_aod_seed_phase8` = 1 : `cleanup` ne supprime QUE ça (idempotent, rejouable
 * après un Ctrl-C). `create` commence par purger un éventuel reliquat.
 *
 * Deux VRAIES pièces jointes (pas des URLs bidon) : la recette vérifie l'affichage
 * réel — le `srcset` régénéré de l'image (8.2) et un `<video src>` en HTTP 200 (8.3).
 *
 * ⚠️ Les valeurs arabes sont DISCRIMINANTES : aucune n'est dans les `.po` ni dans
 * `variant_dictionary()`. Si elle s'affiche, elle vient de la saisie (ici, du seed).
 *
 * Vit dans `scripts/` (pas `tools/recette/`) : seul dossier de code monté dans
 * `wpcli` (docker-compose.yml). Garde-fou localhost, comme `dev-seed.php`.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'AOD_COD_Desc_Video' ) ) {
	WP_CLI::error( 'WooCommerce et/ou aod-cod-form (8.3) ne sont pas actifs.' );
}

const AOD_P8_MARK = '_aod_seed_phase8';

/** Purge tout ce qui porte la marque (produits + pièces jointes). Idempotent. */
function aod_p8_cleanup() {
	$n = 0;
	$ids = get_posts( array(
		'post_type'   => array( 'product', 'attachment' ),
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => AOD_P8_MARK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => '1',         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	) );
	foreach ( $ids as $id ) {
		if ( 'attachment' === get_post_type( $id ) ) {
			wp_delete_attachment( $id, true ); // true : supprime aussi le fichier sur le disque.
		} else {
			wp_delete_post( $id, true );
		}
		$n++;
	}
	return $n;
}

$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'cleanup' === $mode ) {
	$n = aod_p8_cleanup();
	WP_CLI::success( "Fixtures Phase 8 purgées : $n objet(s)." );
	return;
}
if ( 'create' !== $mode ) {
	WP_CLI::error( 'Usage : wp eval-file /scripts/recette-seed-phase8.php create|cleanup' );
}

aod_p8_cleanup(); // Reliquat d'une exécution interrompue : on repart propre.

require_once ABSPATH . 'wp-admin/includes/image.php'; // wp_generate_attachment_metadata

$up = wp_upload_dir();
if ( ! empty( $up['error'] ) ) {
	WP_CLI::error( 'Dossier uploads inutilisable : ' . $up['error'] );
}

/* -------------------------------------------------------------------------
 * 1. Pièce jointe IMAGE — 1200×800, générée par GD (aucun réseau).
 *    Les tailles intermédiaires (metadata) → srcset régénéré au rendu (8.2).
 * ---------------------------------------------------------------------- */
if ( ! function_exists( 'imagecreatetruecolor' ) ) {
	WP_CLI::error( 'GD indisponible : impossible de générer l\'image de la fixture.' );
}
$img_path = trailingslashit( $up['path'] ) . 'recette-phase8-' . wp_generate_password( 6, false ) . '.jpg';
$im       = imagecreatetruecolor( 1200, 800 );
imagefilledrectangle( $im, 0, 0, 1199, 799, imagecolorallocate( $im, 38, 92, 160 ) );
imagefilledrectangle( $im, 80, 80, 1119, 719, imagecolorallocate( $im, 240, 244, 250 ) );
imagejpeg( $im, $img_path, 82 );
imagedestroy( $im );

$img_id = wp_insert_attachment(
	array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Recette Phase 8 — image', 'post_status' => 'inherit' ),
	$img_path
);
if ( is_wp_error( $img_id ) || ! $img_id ) {
	WP_CLI::error( 'Insertion de l\'image impossible.' );
}
wp_update_attachment_metadata( $img_id, wp_generate_attachment_metadata( $img_id, $img_path ) );
update_post_meta( $img_id, AOD_P8_MARK, '1' );
$img_url = wp_get_attachment_url( $img_id );

/* -------------------------------------------------------------------------
 * 2. Pièce jointe VIDÉO — MP4 minimal mais VALIDE (boîtes ftyp + mdat).
 *    finfo → video/mp4 ; le navigateur sert le fichier en HTTP 200 (8.3).
 * ---------------------------------------------------------------------- */
$vid_path = trailingslashit( $up['path'] ) . 'recette-phase8-' . wp_generate_password( 6, false ) . '.mp4';
$ftyp     = pack( 'N', 28 ) . 'ftyp' . 'isom' . pack( 'N', 0x200 ) . 'isom' . 'iso2' . 'mp41';
$mdat     = pack( 'N', 8 ) . 'mdat';
file_put_contents( $vid_path, $ftyp . $mdat ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

$vid_id = wp_insert_attachment(
	array( 'post_mime_type' => 'video/mp4', 'post_title' => 'Recette Phase 8 — vidéo', 'post_status' => 'inherit' ),
	$vid_path
);
if ( is_wp_error( $vid_id ) || ! $vid_id ) {
	WP_CLI::error( 'Insertion de la vidéo impossible.' );
}
update_post_meta( $vid_id, AOD_P8_MARK, '1' );
$vid_url = wp_get_attachment_url( $vid_id );

/* -------------------------------------------------------------------------
 * 3. Le markup EXACT que l'éditeur /gestion stocke (vérifié en source :
 *    dashboard.js pickImage / pickVideo). On le passe par wp_kses_post() —
 *    la vraie frontière du save produit — pour que la fixture reflète ce qui
 *    EXISTE en base, pas un idéal.
 *
 *    🩹 La vidéo YouTube est en MILIEU de paragraphe (`<p>Regardez : <a…></a></p>`)
 *    → exerce le correctif D7-#2 (regex `<p>` balancée) débusqué par la revue 8.3.
 * ---------------------------------------------------------------------- */
$img_tag = '<img class="wp-image-' . $img_id . '" src="' . esc_url( $img_url ) . '" alt="" width="1200" height="800" />';
$vid_tag = '<video class="aod-desc-video-file" src="' . esc_url( $vid_url ) . '" controls preload="none" playsinline></video>';
$yt      = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

$rich_fr = wp_kses_post(
	'<h2>Section Recette Riche</h2>'
	. '<p>Paragraphe riche de la recette de cloture.</p>'
	. '<h3>Sous-titre recette</h3>'
	. '<ul><li>Bienfait recette alpha</li><li>Bienfait recette beta</li></ul>'
	. '<ol><li>Etape recette une</li><li>Etape recette deux</li></ol>'
	. $img_tag
	. '<p>Regardez la demonstration : <a class="aod-desc-video" href="' . esc_url( $yt ) . '">' . esc_html( $yt ) . '</a></p>'
	. $vid_tag
);

$rich_ar = wp_kses_post(
	'<h2>قسم المعاينة الغني</h2>'
	. '<p>فقرة المعاينة الغنية للاختبار الختامي.</p>'
	. '<h3>عنوان فرعي للمعاينة</h3>'
	. '<ul><li>فائدة المعاينة ألفا</li><li>فائدة المعاينة بيتا</li></ul>'
	. '<ol><li>خطوة المعاينة الأولى</li><li>خطوة المعاينة الثانية</li></ol>'
	. $img_tag
	. '<p>شاهد العرض : <a class="aod-desc-video" href="' . esc_url( $yt ) . '">' . esc_html( $yt ) . '</a></p>'
	. $vid_tag
);

/* -------------------------------------------------------------------------
 * 4. Produit à description RICHE (couple { fr, ar } tous deux riches).
 * ---------------------------------------------------------------------- */
$p = new WC_Product_Simple();
$p->set_name( 'Recette Description Riche' );
$p->set_status( 'publish' );
$p->set_regular_price( '2700' );
$p->set_price( '2700' );
$p->set_description( $rich_fr );
$p->update_meta_data( '_aod_title_ar', 'منتج المعاينة الغني' );
$p->update_meta_data( '_aod_desc_ar', $rich_ar );
$p->update_meta_data( AOD_P8_MARK, '1' );
$rich_id = $p->save();

/* -------------------------------------------------------------------------
 * 5. Produit TÉMOIN, description PLATE (AR vide → repli FR intégral).
 * ---------------------------------------------------------------------- */
$w = new WC_Product_Simple();
$w->set_name( 'Recette Description Plate' );
$w->set_status( 'publish' );
$w->set_regular_price( '1500' );
$w->set_price( '1500' );
$w->set_description( 'Description plate sans aucune mise en forme.' );
$w->update_meta_data( AOD_P8_MARK, '1' );
$plain_id = $w->save();

if ( ! $rich_id || ! $plain_id ) {
	aod_p8_cleanup();
	WP_CLI::error( 'Création des produits impossible — fixtures purgées.' );
}

WP_CLI::log( 'RICH=' . $rich_id );
WP_CLI::log( 'PLAIN=' . $plain_id );
WP_CLI::log( 'IMG=' . $img_id );
WP_CLI::log( 'VID=' . $vid_id );
