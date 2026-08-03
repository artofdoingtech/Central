<?php
/**
 * Socle bilingue du catalogue (`AOD_COD_Catalog_I18n`) — étape 7.1.
 *
 * L'armature qui décide « traduire ou non » le contenu marchand. Trois choses à
 * prouver, car une erreur ici ferait fuiter de l'arabe dans l'admin / les e-mails /
 * l'éditeur `/gestion`, ou laisserait un trou à l'écran :
 *   • `pick()` : le bon texte, avec repli DANS LES DEUX SENS ;
 *   • `should_translate()` : la garde (AR active, hors admin, contexte public) ;
 *   • `render()` : la façade — FR canonique intact quand la garde est fermée.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Catalogue i18n — pick() : repli bidirectionnel' );

AOD_Test::eq( 'كرسي', AOD_COD_Catalog_I18n::pick( 'Chaise', 'كرسي', true ), 'AR + les deux : l\'arabe est choisi' );
AOD_Test::eq( 'Chaise', AOD_COD_Catalog_I18n::pick( 'Chaise', '', true ), 'AR sans arabe : on retombe sur le français (jamais de vide)' );
AOD_Test::eq( 'Chaise', AOD_COD_Catalog_I18n::pick( 'Chaise', 'كرسي', false ), 'FR + les deux : le français est choisi' );
AOD_Test::eq( 'كرسي', AOD_COD_Catalog_I18n::pick( '', 'كرسي', false ), 'FR sans français : on retombe sur l\'arabe' );
AOD_Test::eq( '', AOD_COD_Catalog_I18n::pick( '', '', true ), 'Les deux vides : chaîne vide, pas d\'erreur' );
AOD_Test::eq( 'Chaise', AOD_COD_Catalog_I18n::pick( '  Chaise  ', '', true ), 'Les espaces autour sont retirés (trim)' );

AOD_Test::group( 'Catalogue i18n — pick_list() : deux listes appariées par rang' );

// Le cas qui doit rester SACRÉ : pas de traduction → la liste ne bouge pas.
$fr3 = array( 'Livraison rapide', 'Garantie 1 an', 'Qualité premium' );
AOD_Test::eq( $fr3, AOD_COD_Catalog_I18n::pick_list( $fr3, array(), true ), 'Aucune liste arabe : la liste FR est rendue telle quelle, même en AR' );
AOD_Test::eq( $fr3, AOD_COD_Catalog_I18n::pick_list( $fr3, '', true ), 'Méta arabe absente (non-tableau) : idem, aucune erreur' );
AOD_Test::eq( array(), AOD_COD_Catalog_I18n::pick_list( '', '', true ), 'Deux non-tableaux : liste vide, pas d\'erreur' );

$ar3 = array( 'توصيل سريع', 'ضمان سنة', 'جودة عالية' );
AOD_Test::eq( $ar3, AOD_COD_Catalog_I18n::pick_list( $fr3, $ar3, true ), 'Les deux listes, site AR : tout en arabe' );
AOD_Test::eq( $fr3, AOD_COD_Catalog_I18n::pick_list( $fr3, $ar3, false ), 'Les deux listes, site FR : tout en français' );

// Désalignement : c'est là que ça se joue.
AOD_Test::eq(
	array( 'توصيل سريع', 'Garantie 1 an', 'Qualité premium' ),
	AOD_COD_Catalog_I18n::pick_list( $fr3, array( 'توصيل سريع' ), true ),
	'Liste AR plus COURTE : les lignes sans arabe replient sur le français de même rang'
);
// 🚨 Le FR canonique fixe le nombre de puces — la règle qui évite le pire des cas.
AOD_Test::eq(
	$ar3,
	AOD_COD_Catalog_I18n::pick_list( $fr3, array_merge( $ar3, array( 'هدية مجانية' ) ), true ),
	'Liste AR plus LONGUE, site AR : la ligne en trop est IGNORÉE (le FR fixe la structure)'
);
AOD_Test::eq(
	$fr3,
	AOD_COD_Catalog_I18n::pick_list( $fr3, array_merge( $ar3, array( 'هدية مجانية' ) ), false ),
	'Liste AR plus LONGUE, site FR : AUCUNE puce arabe ne surgit sur le site français'
);
// Seule exception au « FR canonique » : un produit livré 100 % arabe.
AOD_Test::eq(
	$ar3,
	AOD_COD_Catalog_I18n::pick_list( array(), $ar3, false ),
	'Aucune ligne FR (produit 100 % arabe) : l\'AR mène, même sur le site FR (repli bidirectionnel)'
);
AOD_Test::eq(
	array( 'Garantie 1 an' ),
	AOD_COD_Catalog_I18n::pick_list( array( '', 'Garantie 1 an' ), array( '', '' ), true ),
	'Ligne vide des DEUX côtés : omise (jamais de puce vide)'
);

AOD_Test::group( 'Catalogue i18n — should_translate() : la garde (table de vérité)' );

// On ne traduit QUE si : langue arabe ET hors admin ET contexte public catalogue.
AOD_Test::eq( true,  AOD_COD_Catalog_I18n::should_translate( true,  false, true ),  'AR + public + hors admin → on traduit' );
AOD_Test::eq( false, AOD_COD_Catalog_I18n::should_translate( false, false, true ),  'Site FR → on ne traduit pas' );
AOD_Test::eq( false, AOD_COD_Catalog_I18n::should_translate( true,  true,  true ),  'En admin (wp-admin, e-mails) → jamais de swap, même en AR' );
AOD_Test::eq( false, AOD_COD_Catalog_I18n::should_translate( true,  false, false ), 'Hors contexte public (éditeur /gestion) → jamais de swap' );
AOD_Test::eq( false, AOD_COD_Catalog_I18n::should_translate( false, true,  false ), 'FR + admin + hors contexte → non' );
AOD_Test::eq( false, AOD_COD_Catalog_I18n::should_translate( true,  true,  false ), 'AR mais admin ET hors contexte → non' );

AOD_Test::group( 'Catalogue i18n — render() : façade gardée, FR canonique intact' );

// En contexte de test (wp eval-file) : is_admin()=false et get_query_var('aod_cd')
// est vide → la garde est ouverte dès que la langue passe en arabe.
AOD_Test::eq( 'Chaise', AOD_COD_Catalog_I18n::render( 'Chaise', 'كرسي' ), 'Site FR : render rend le FR canonique inchangé' );
AOD_Test::eq( 'كرسي', AOD_Test::in_arabic( static function () {
	return AOD_COD_Catalog_I18n::render( 'Chaise', 'كرسي' );
} ), 'Site AR (contexte public) : render rend l\'arabe' );
AOD_Test::eq( 'Chaise', AOD_Test::in_arabic( static function () {
	return AOD_COD_Catalog_I18n::render( 'Chaise', '' );
} ), 'Site AR sans traduction : repli sur le FR (pas de vide)' );
