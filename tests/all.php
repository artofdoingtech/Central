<?php
/**
 * Point d'entrée des tests unitaires — chargé par `wp eval-file` (donc DANS
 * WordPress, avec les plugins actifs et les vraies fonctions du cœur).
 *
 * Lancer : `bash tests/run.sh` depuis le dossier du clone.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/bootstrap.php';

// Les tests portent sur les classes des deux plugins maison : sans elles, il n'y a
// rien à prouver — mieux vaut le dire que de rendre un « 0 test, tout va bien ».
$required = array(
	'AOD_COD_Landing'  => 'aod-cod-form',
	'AOD_COD_Pixels'   => 'aod-cod-form',
	'AOD_COD_Catalog_I18n' => 'aod-cod-form',
	'AOD_COD_Catalog_Filters' => 'aod-cod-form',
	'AOD_COD_Desc_Video' => 'aod-cod-form',
	'AOD_Carrier'      => 'aod-cod-form',
	'AOD_CD_Customize' => 'aod-client-dashboard',
	'AOD_CD_Icon'      => 'aod-client-dashboard',
	'AOD_CD_Roles'     => 'aod-client-dashboard',
	'AOD_CD_Dashboard' => 'aod-client-dashboard',
	'AOD_CD_Section_Products' => 'aod-client-dashboard',
);
foreach ( $required as $class => $plugin ) {
	if ( ! class_exists( $class ) ) {
		echo "\n\033[31m✗ Classe {$class} introuvable — le plugin `{$plugin}` est-il actif ?\033[0m\n";
		echo "  docker compose exec wpcli wp plugin activate {$plugin}\n\n";
		exit( 2 );
	}
}

echo "\n\033[1mTests unitaires — Template-Full\033[0m\n";
echo str_repeat( '─', 60 ) . "\n";

foreach ( glob( __DIR__ . '/unit/test-*.php' ) as $file ) {
	require $file;
}

AOD_Test::summary();
