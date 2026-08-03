<?php
/**
 * Ce qu'une capability DONNE, pas ce qu'elle ouvre (Phase 31.1).
 *
 * Le dépôt savait déjà vérifier deux choses sur ses capabilities :
 *   - `test-permissions-sections.php` (Phase 21) : les gardes d'une section sont
 *     COHÉRENTES entre elles ;
 *   - `tools/recette/confirmateur.js` (Phase 22) : une cap OUVRE et FERME bien son
 *     écran, mesuré sur un vrai 403.
 *
 * 🚨 Ni l'un ni l'autre ne mesure la PORTÉE — ce que l'écran ouvert contient une
 * fois ouvert. C'est le trou par lequel est passée la faille de la 31.1 : la case
 * « Personnalisation des tarifs de livraison » rendait aussi le bloc
 * « Transporteurs », donc les identifiants API du client EN CLAIR
 * (`render_settings_fields()` imprime `value="<token>"`), et son enregistrement
 * écrivait le DOMAINE appelé par l'envoi automatique. Les trois gardes de la
 * section étaient parfaitement cohérentes ; elles étaient collectivement trop
 * larges. Un cliquet de cohérence ne pouvait pas le voir.
 *
 * Ce test-ci endosse un VRAI confirmateur, rend la section, et lit ce qui sort.
 *
 * 🔴 IL EST ANCRÉ. Une assertion d'absence (« le jeton n'apparaît pas ») est vraie
 * pour la mauvaise raison dès que le rendu est vide, que la classe a changé de nom
 * ou que la section a cessé de se rendre. Chaque absence est donc adossée à deux
 * présences : le même jeton EST vu par le gérant (le témoin prouve que le test
 * sait le trouver quand il est là), et la grille tarifaire EST vue par le
 * confirmateur (le témoin prouve qu'on a resserré la portée sans casser la
 * fonctionnalité qu'on voulait déléguer).
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Contexte : un confirmateur « tarifs » réel, et un jeton témoin
 * ========================================================================= */

AOD_Test::group( 'Portée des caps 31.1 — préparation du contexte' );

$aod_cp_ok_socle = class_exists( 'AOD_CD_Section_Shipping' )
	&& class_exists( 'AOD_Shipping' )
	&& class_exists( 'AOD_CD_Roles' );

AOD_Test::ok( $aod_cp_ok_socle, 'Socle présent (AOD_CD_Section_Shipping + AOD_Shipping + AOD_CD_Roles)' );

if ( ! $aod_cp_ok_socle ) {
	// Sortir en silence rendrait le fichier vert sans rien éprouver — le défaut
	// que la Phase 27 a corrigé sur quatre portes de recette. On l'a dit, on sort.
	return;
}

$aod_cp_user_avant = get_current_user_id();

// Le jeton témoin. Chaîne improbable : s'il apparaît dans le HTML, il n'a pu y
// arriver que par le champ qu'on surveille.
$aod_cp_token = 'TEMOIN-PORTEE-31-1-a7f3c9';

// On travaille sur le transporteur EcoTrack générique (domaine libre) : c'est
// exactement celui qui portait le SSRF. Ses réglages RÉELS sont sauvegardés et
// restaurés en fin de fichier — ce clone peut porter une vraie configuration
// transporteur, et un test n'a pas le droit de la perdre.
$aod_cp_carriers = AOD_Shipping::instance()->carriers();
$aod_cp_cid      = isset( $aod_cp_carriers['ecotrack'] ) ? 'ecotrack' : (string) array_key_first( $aod_cp_carriers );
$aod_cp_carrier  = $aod_cp_carriers[ $aod_cp_cid ];
$aod_cp_opt      = $aod_cp_carrier->option_key();
$aod_cp_reglages = get_option( $aod_cp_opt, null );

update_option(
	$aod_cp_opt,
	array_merge(
		(array) $aod_cp_carrier->settings(),
		array( 'domain' => 'temoin.ecotrack.dz', 'api_token' => $aod_cp_token )
	)
);

// Un confirmateur porteur de la SEULE cap « tarifs ». Créé pour le test, détruit
// à la fin.
$aod_cp_uid = wp_insert_user(
	array(
		'user_login' => 'aod_test_portee_31_1',
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => 'aod-test-portee-31-1@example.invalid',
		'role'       => AOD_CD_ROLE_CONFIRMER,
	)
);

$aod_cp_user_cree = ! is_wp_error( $aod_cp_uid );
AOD_Test::ok( $aod_cp_user_cree, 'Un confirmateur de test est créé', $aod_cp_user_cree ? '' : $aod_cp_uid->get_error_message() );

if ( ! $aod_cp_user_cree ) {
	if ( null === $aod_cp_reglages ) {
		delete_option( $aod_cp_opt );
	} else {
		update_option( $aod_cp_opt, $aod_cp_reglages );
	}
	return;
}

$aod_cp_user = new WP_User( (int) $aod_cp_uid );
$aod_cp_user->add_cap( 'aod_cap_shipping_prices' );

wp_set_current_user( (int) $aod_cp_uid );

// Témoin d'identité : sans lui, tout le fichier pourrait tourner en gérant et
// verdir en prouvant l'inverse de ce qu'il annonce (la leçon de la Phase 22).
AOD_Test::ok(
	AOD_CD_Roles::user_can( 'aod_cap_shipping_prices' ),
	'Témoin : le confirmateur porte bien la cap « tarifs »'
);
AOD_Test::ok(
	! current_user_can( 'manage_woocommerce' ),
	'🚨 Témoin d’identité : il n’est PAS gérant (sinon toute garde serait satisfaite et le test vert pour rien)'
);

/* =========================================================================
 * R1 — Le confirmateur « tarifs » ne voit AUCUN secret transporteur
 * ========================================================================= */

AOD_Test::group( 'Portée des caps 31.1 — R1 : la cap « tarifs » ne donne pas les comptes transporteurs' );

ob_start();
AOD_CD_Section_Shipping::instance()->render();
$aod_cp_html_conf = (string) ob_get_clean();

// Ancrage n°1 : le rendu a bien eu lieu. Sans ça, les absences ci-dessous
// seraient vraies sur une page blanche.
AOD_Test::ok(
	strlen( $aod_cp_html_conf ) > 500,
	'Ancrage : la section Livraison rend bien quelque chose pour ce confirmateur',
	'longueur : ' . strlen( $aod_cp_html_conf )
);

// Ancrage n°2 : ce qu'on a VOULU déléguer est toujours là. Un correctif qui
// fermerait toute la section passerait R1 sans ce témoin — en cassant la
// fonctionnalité même que la cap existe pour offrir.
AOD_Test::ok(
	false !== strpos( $aod_cp_html_conf, 'name="home[' ),
	'Ancrage : la GRILLE TARIFAIRE reste rendue (la cap fait toujours son travail)'
);

AOD_Test::ok(
	false === strpos( $aod_cp_html_conf, $aod_cp_token ),
	'🔒 Le jeton API du transporteur n’apparaît PAS dans le rendu du confirmateur',
	false === strpos( $aod_cp_html_conf, $aod_cp_token ) ? '' : 'jeton trouvé dans le HTML'
);

AOD_Test::ok(
	false === strpos( $aod_cp_html_conf, 'name="auto[carrier]"' ),
	'🔒 Le choix du livreur de l’envoi AUTO n’est pas rendu au confirmateur'
);

AOD_Test::ok(
	false === strpos( $aod_cp_html_conf, 'aod-cd-carrier-test-btn' ),
	'🔒 Le bouton « Tester la connexion » n’est pas rendu au confirmateur'
);

/* =========================================================================
 * R2 — Le témoin inverse : le gérant, lui, DOIT voir ce jeton
 * =========================================================================
 *
 * C'est la moitié qui rend R1 démonstratif. Sans elle, R1 resterait vert si
 * `render_settings_fields()` cessait d'imprimer le jeton pour n'importe quelle
 * raison — refactor, panne, champ renommé — et le cliquet cesserait de mordre
 * sans que personne ne l'apprenne. « Un cliquet qu'on n'a jamais vu échouer ne
 * prouve rien » : ici on le lui fait dire dans les deux sens.
 */

AOD_Test::group( 'Portée des caps 31.1 — R2 : témoin inverse, le gérant voit ce que le confirmateur ne voit pas' );

$aod_cp_gerants = get_users( array( 'role__in' => array( 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );

if ( $aod_cp_gerants ) {
	wp_set_current_user( (int) $aod_cp_gerants[0] );

	AOD_Test::ok( current_user_can( 'manage_woocommerce' ), 'Témoin : un gérant est endossé' );

	ob_start();
	AOD_CD_Section_Shipping::instance()->render();
	$aod_cp_html_ger = (string) ob_get_clean();

	AOD_Test::ok(
		false !== strpos( $aod_cp_html_ger, $aod_cp_token ),
		'🚨 Le gérant, LUI, voit le jeton — la recherche de R1 sait donc le trouver quand il est là',
		false !== strpos( $aod_cp_html_ger, $aod_cp_token ) ? '' : 'jeton introuvable même en gérant : R1 est devenu vide de sens'
	);

	AOD_Test::ok(
		false !== strpos( $aod_cp_html_ger, 'name="auto[carrier]"' ),
		'Le gérant voit le réglage d’envoi automatique'
	);
} else {
	AOD_Test::ok( false, 'Aucun administrateur trouvé : le témoin inverse de R2 n’a pas pu être joué' );
}

/* =========================================================================
 * R3 — `sanitize_domain()` : le domaine transporteur n'est pas un champ libre
 * =========================================================================
 *
 * Helper PUR. Il ne remplace pas la garde de capability (un domaine public
 * contrôlé par l'attaquant reste possible) : il ferme le volet RÉSEAU INTERNE,
 * qui ne dépend d'aucun droit.
 */

AOD_Test::group( 'Portée des caps 31.1 — R3 : le domaine EcoTrack refuse les cibles internes' );

if ( class_exists( 'AOD_Carrier_Ecotrack' ) ) {
	$aod_cp_eco = $aod_cp_carriers[ $aod_cp_cid ];

	$aod_cp_refus = array(
		'169.254.169.254'          => 'métadonnées cloud (littéral IPv4)',
		'127.0.0.1'                => 'loopback',
		'10.0.0.5'                 => 'réseau privé',
		'::1'                      => 'loopback IPv6',
		'localhost'                => 'nom sans point',
		'db'                       => 'nom de service Docker interne',
		'evil.tld/../x'            => 'chemin dans le champ',
		'evil.tld:8080'            => 'port explicite',
		'ecotrack.dz@evil.tld'     => 'identifiants dans l’autorité (la cible réelle est evil.tld)',
		''                         => 'chaîne vide',
	);

	foreach ( $aod_cp_refus as $aod_cp_in => $aod_cp_pourquoi ) {
		AOD_Test::eq(
			'',
			AOD_Test::call( $aod_cp_eco, 'sanitize_domain', array( $aod_cp_in ) ),
			sprintf( 'Refusé — %s : « %s »', $aod_cp_pourquoi, $aod_cp_in )
		);
	}

	// Témoin : la validation ne refuse pas TOUT. Un helper qui renverrait '' en
	// toutes circonstances passerait les 10 assertions ci-dessus.
	$aod_cp_acceptes = array(
		'monorg.ecotrack.dz'        => 'monorg.ecotrack.dz',
		'https://monorg.ecotrack.dz/' => 'monorg.ecotrack.dz',
		'APP.CONEXLOG-DZ.COM'       => 'app.conexlog-dz.com',
	);
	foreach ( $aod_cp_acceptes as $aod_cp_in => $aod_cp_attendu ) {
		AOD_Test::eq(
			$aod_cp_attendu,
			AOD_Test::call( $aod_cp_eco, 'sanitize_domain', array( $aod_cp_in ) ),
			sprintf( '🚨 Témoin d’acceptation — un domaine légitime passe : « %s »', $aod_cp_in )
		);
	}
} else {
	AOD_Test::ok( false, 'AOD_Carrier_Ecotrack introuvable : R3 n’a pas pu être joué' );
}

/* =========================================================================
 * Nettoyage — le fichier ne laisse rien derrière lui
 * ========================================================================= */

wp_set_current_user( $aod_cp_user_avant );

if ( null === $aod_cp_reglages ) {
	delete_option( $aod_cp_opt );
} else {
	update_option( $aod_cp_opt, $aod_cp_reglages );
}

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( (int) $aod_cp_uid );

AOD_Test::group( 'Portée des caps 31.1 — nettoyage' );
AOD_Test::ok( ! get_user_by( 'login', 'aod_test_portee_31_1' ), 'Le confirmateur de test est supprimé' );
AOD_Test::eq(
	$aod_cp_reglages,
	null === $aod_cp_reglages ? get_option( $aod_cp_opt, null ) : get_option( $aod_cp_opt ),
	'🚨 Les réglages RÉELS du transporteur sont restaurés à l’identique'
);
