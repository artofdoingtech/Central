<?php
/**
 * Deux garanties du dashboard :
 *   • `apply_margin()` — le prix de livraison affiché à l'acheteur. Une erreur ici
 *     se paie en argent réel, sur chaque commande.
 *   • `build_caps()` / `CONFIRMER_CAPS` — le périmètre de sécurité. Une capacité
 *     dangereuse qui repasse dans la liste et le gérant peut casser le site.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Tarifs — apply_margin() : on n\'invente jamais un prix' );

AOD_Test::eq( 0, AOD_CD_Section_Shipping::apply_margin( 0, 'fixed', 100, 0 ), 'Un tarif de base à 0 (mode non fourni par l\'API) reste 0, même avec une marge' );
AOD_Test::eq( 0, AOD_CD_Section_Shipping::apply_margin( -50, 'percent', 10, 0 ), 'Un tarif de base négatif reste 0' );
AOD_Test::eq( 0, AOD_CD_Section_Shipping::apply_margin( 400, 'fixed', -1000, 0 ), 'Une marge qui rendrait le tarif négatif le ramène à 0, jamais en dessous' );

AOD_Test::group( 'Tarifs — apply_margin() : les trois modes' );

AOD_Test::eq( 400, AOD_CD_Section_Shipping::apply_margin( 400, 'none', 100, 0 ), 'Mode « aucune marge » : le tarif API passe tel quel' );
AOD_Test::eq( 500, AOD_CD_Section_Shipping::apply_margin( 400, 'fixed', 100, 0 ), 'Marge fixe : +100 DZD sur 400 → 500' );
AOD_Test::eq( 440, AOD_CD_Section_Shipping::apply_margin( 400, 'percent', 10, 0 ), 'Marge en pourcentage : +10 % sur 400 → 440' );
AOD_Test::eq( 400, AOD_CD_Section_Shipping::apply_margin( 400, 'type_inconnu', 100, 0 ), 'Un type de marge inconnu ne modifie pas le tarif (pas de surprise)' );

AOD_Test::group( 'Tarifs — apply_margin() : arrondi commercial' );

AOD_Test::eq( 450, AOD_CD_Section_Shipping::apply_margin( 440, 'none', 0, 50 ), 'Arrondi au pas de 50 : 440 → 450' );
AOD_Test::eq( 400, AOD_CD_Section_Shipping::apply_margin( 420, 'none', 0, 50 ), 'Arrondi au pas de 50 : 420 → 400 (au plus proche, pas au supérieur)' );
AOD_Test::eq( 440, AOD_CD_Section_Shipping::apply_margin( 440, 'none', 0, 0 ), 'Pas d\'arrondi demandé : la valeur exacte est conservée' );
AOD_Test::eq( 450, AOD_CD_Section_Shipping::apply_margin( 400, 'percent', 10, 50 ), 'Marge PUIS arrondi : 400 +10 % = 440, arrondi au pas de 50 → 450' );

// Le résultat doit TOUJOURS être un entier : un prix à virgule finirait dans le
// total de la commande et dans le bordereau du transporteur.
$val = AOD_CD_Section_Shipping::apply_margin( 333, 'percent', 7.5, 0 );
AOD_Test::ok( is_int( $val ), 'Le tarif final est un entier (jamais un flottant qui traînerait jusqu\'au bordereau)', 'obtenu : ' . var_export( $val, true ) );

AOD_Test::group( 'Sécurité — build_caps() : aucune capacité dangereuse ne survit' );

$caps = AOD_CD_Roles::build_caps();

foreach ( AOD_CD_Roles::FORBIDDEN as $bad ) {
	AOD_Test::ok( empty( $caps[ $bad ] ), "Le gérant n'a PAS la capacité `{$bad}`" );
}

AOD_Test::ok( ! empty( $caps['manage_woocommerce'] ), 'Le gérant garde `manage_woocommerce` (sinon il ne peut plus rien gérer)' );
AOD_Test::ok( ! empty( $caps['edit_products'] ), 'Le gérant garde `edit_products`' );
AOD_Test::ok( ! empty( $caps['upload_files'] ), 'Le gérant garde `upload_files` (images produits)' );
AOD_Test::ok( ! empty( $caps['read'] ), 'Le gérant garde `read`' );

// Les trois qui font la différence entre « gérant » et « administrateur ».
AOD_Test::ok( empty( $caps['manage_options'] ), 'Le gérant ne peut pas atteindre les réglages du site' );
AOD_Test::ok( empty( $caps['edit_users'] ), 'Le gérant ne peut pas changer le mot de passe de l\'admin' );
AOD_Test::ok( empty( $caps['activate_plugins'] ), 'Le gérant ne peut pas activer d\'extension' );

AOD_Test::group( 'Sécurité — confirmateur : whitelist stricte' );

$confirmer = AOD_CD_Roles::CONFIRMER_CAPS;
// 7 depuis la Phase 11 : les 6 d'origine + aod_cap_abandoned (commandes abandonnées).
AOD_Test::eq( 7, count( $confirmer ), 'Le confirmateur a exactement 7 capacités à la carte' );
AOD_Test::ok( in_array( 'aod_cap_abandoned', $confirmer, true ), 'La capacité « commandes abandonnées » (Phase 11) est bien présente' );

foreach ( $confirmer as $cap ) {
	AOD_Test::ok( 0 === strpos( $cap, 'aod_cap_' ), "`{$cap}` est bien une capacité custom `aod_cap_*` (jamais une capacité WordPress native)" );
}

// La whitelist ne doit contenir AUCUNE capacité WordPress/Woo : c'est ce qui
// garantit qu'un confirmateur ne peut pas hériter d'un droit d'administration.
$dangereuses = array_intersect( $confirmer, AOD_CD_Roles::FORBIDDEN );
AOD_Test::eq( array(), $dangereuses, 'Aucune capacité interdite ne figure dans la whitelist du confirmateur' );
AOD_Test::ok( ! in_array( 'manage_woocommerce', $confirmer, true ), 'Un confirmateur n\'a jamais `manage_woocommerce`' );
