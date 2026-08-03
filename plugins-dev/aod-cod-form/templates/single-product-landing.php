<?php
/**
 * Gabarit d'une page de vente (landing) produit.
 *
 * Substitué au gabarit du thème par `AOD_COD_Landing::maybe_take_over()`, et
 * UNIQUEMENT quand la landing du produit est active (cf. `target_product()`).
 *
 * `get_header()` / `get_footer()` sont ici l'essentiel : ils conservent l'en-tête
 * et le pied du thème — donc la navigation à 3 entrées, le switcher FR/AR et le
 * branding du Personnalisateur. On ne maîtrise que le CORPS de la page.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header( 'shop' );

$aod_landing    = AOD_COD_Landing::instance();
$aod_product_id = $aod_landing->target_product();

if ( $aod_product_id ) {
	$aod_landing->render( $aod_product_id );
}

get_footer( 'shop' );
