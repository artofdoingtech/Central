<?php
/**
 * Cliquet sur la charte de la page de connexion — le bouton suit la marque.
 *
 * CE QUE CE TEST REMPLACE. La page `wp-login.php` est la seule surface de
 * `/gestion` que le Personnalisateur ne repeint pas tout seul : elle a son propre
 * `login_enqueue_scripts`. Elle codait son accent EN DUR (`#111827`), si bien que
 * le bouton de connexion restait gris-ardoise quel que soit l'accent choisi par le
 * client — un gérant à marque verte (`#00A651`) voyait un bouton qui n'était pas la
 * sienne. C'est un manquement à la **Règle d'or n°1** (« branding via le
 * Personnalisateur, jamais codé en dur »), sur le tout premier écran qu'il voit.
 *
 * LE CONTRAT (étape 29.1) :
 *   R1 · La page login injecte la MÊME charte que /gestion — le bloc `:root{…}` de
 *        `AOD_CD_Customize::css_vars()`, donc `--cd-accent` dérivé du client.
 *   R2 · Le bouton primaire prend `var(--cd-accent)` en fond et `var(--cd-accent-ink)`
 *        en texte — l'encre lisible calculée par `best_ink`, jamais du blanc en dur
 *        (illisible sur un accent clair comme le jaune par défaut).
 *   R3 · Le fond du bouton n'est PAS une couleur hexadécimale en dur — c'est le
 *        retour en arrière qu'on interdit.
 *
 * ⚠️ Le contraste de `--cd-accent-ink` sur l'accent est déjà prouvé, pour TOUT
 * accent, par `test-contraste.php` (il éprouve `best_ink`). Ce cliquet-ci garde le
 * CÂBLAGE (le bouton lit bien la variable), pas le calcul de la couleur.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Login 29.1 — le bouton de connexion suit l’accent du client' );

if ( ! class_exists( 'AOD_CD_Access' ) ) {
	AOD_Test::ok( false, 'AOD_CD_Access est chargée', 'Le plugin dashboard doit être actif.' );
	return;
}

// Capture le CSS réellement injecté sur la page de connexion.
ob_start();
AOD_CD_Access::instance()->login_styles();
$aod_login_css = (string) ob_get_clean();

AOD_Test::ok(
	'' !== $aod_login_css,
	sprintf( 'Témoin : login_styles() a produit du CSS (%d octets)', strlen( $aod_login_css ) ),
	'Sans sortie, les règles ci-dessous verdiraient sur du vide.'
);

/* R1 — la charte du client est injectée (pas une couleur figée) */
AOD_Test::ok(
	false !== strpos( $aod_login_css, ':root{' ) && false !== strpos( $aod_login_css, '--cd-accent:' ),
	'R1 — la page login injecte le bloc :root{--cd-accent…} de css_vars()',
	'Sans lui, `var(--cd-accent)` plus bas n’a aucune valeur : le bouton retombe sur '
		. 'le défaut du navigateur. La login doit porter la MÊME charte que /gestion.'
);

/* R2 — le bouton lit l'accent ET son encre lisible */
AOD_Test::ok(
	false !== strpos( $aod_login_css, 'background:var(--cd-accent)' ),
	'R2 — le bouton primaire prend var(--cd-accent) en fond (couleur de marque)',
	'🎨 Règle d’or n°1 : le branding vient du Personnalisateur. Un fond en dur ferait '
		. 'un bouton qui n’est pas la marque du client.'
);
AOD_Test::ok(
	false !== strpos( $aod_login_css, 'color:var(--cd-accent-ink)' ),
	'R2 — le texte du bouton prend var(--cd-accent-ink) (encre lisible best_ink)',
	'🚨 Sans ça, le texte serait le blanc par défaut de WordPress : illisible sur un '
		. 'accent clair (le défaut est un jaune vif). --cd-accent-ink est calculé pour '
		. 'contraster sur N’IMPORTE quel accent (garanti par test-contraste.php).'
);

/* R3 — le fond du bouton n'est PAS une couleur en dur (garde anti-régression) */
if ( preg_match( '/\.button-primary\s*\{[^}]*\}/s', $aod_login_css, $m ) ) {
	$aod_login_btn = $m[0];
	AOD_Test::ok(
		! preg_match( '/background\s*:\s*#[0-9a-fA-F]{3,6}/', $aod_login_btn ),
		'R3 — le fond du bouton n’est pas une couleur hexadécimale en dur',
		'C’est le retour en arrière interdit : `background:#111827` figeait le bouton '
			. 'hors de la marque. Le fond doit rester `var(--cd-accent)`.'
	);
} else {
	AOD_Test::ok( false, 'R3 — la règle .button-primary est présente dans le CSS login', 'Introuvable : le bouton n’est plus stylé ?' );
}
