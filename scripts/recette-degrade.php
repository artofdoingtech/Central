<?php
/**
 * Porte de recette « degrade » — `/gestion` sans le plugin COD (Phase 32.1).
 *
 * Lancement : `bash tools/recette/run.sh degrade`
 *
 * 🚨 CE QU'ELLE GARDE. Les deux plugins maison ont des interrupteurs
 * d'activation INDÉPENDANTS dans wp-admin. Le chargeur du tableau de bord
 * déclare une « dépendance forte (NON BLOQUANTE) » : sans `aod-cod-form`, « ces
 * parties de l'espace de gestion se désactivent PROPREMENT ». C'était une
 * promesse que le code ne tenait pas — `/gestion` tombait en erreur fatale :
 *
 *     Fatal error: Uncaught Error: Class "AOD_Carrier" not found
 *     in .../class-aod-cd-section-home.php:550
 *
 * ⚠️ POURQUOI PERSONNE NE L'AVAIT VU. La ligne fautive vit dans une closure
 * passée à `cached()` : elle ne s'exécute qu'en **cache froid**. Un transient
 * encore chaud masque la fatale, qui n'apparaît qu'à la première ouverture
 * après invalidation. Un défaut intermittent qu'on n'attrape pas en cliquant au
 * hasard — il faut le provoquer.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * 🔒 CETTE PORTE NE DÉSACTIVE RIEN
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Elle est lancée par `run.sh` via `wp --skip-plugins=aod-cod-form`, qui fait
 * démarrer CE processus WP-CLI sans le plugin. L'installation n'est pas touchée :
 * le plugin reste actif pour le site et pour tous les autres processus.
 *
 * ⚠️ NE JAMAIS remplacer ça par `wp plugin deactivate`. Sur le clone d'un vrai
 * client, désactiver le formulaire COD, c'est couper la boutique — et si la
 * porte meurt entre la désactivation et la réactivation, elle la laisse coupée.
 * L'isolation par processus rend l'accident impossible, elle ne le rattrape pas.
 *
 * @package AOD_Recette
 */

defined( 'ABSPATH' ) || exit;

function aod_dg_score( $ajout = null ) {
	static $pass = 0;
	static $fail = 0;
	if ( true === $ajout )  { $pass++; }
	if ( false === $ajout ) { $fail++; }
	return array( 'pass' => $pass, 'fail' => $fail );
}

function aod_dg_ok( $cond, $msg, $detail = '' ) {
	$cond = (bool) $cond;
	aod_dg_score( $cond );
	echo $cond
		? "  \033[32m✓\033[0m {$msg}\n"
		: "  \033[31m✗\033[0m {$msg}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
	return $cond;
}

echo "\n\033[1m▶ /gestion sans le plugin COD — dégradation propre (32.1)\033[0m\n\n";

/* =========================================================================
 * 0 · ANCRAGE — on est bien dans l'état dégradé
 *
 * Sans ce contrôle, la porte tournerait avec le plugin PRÉSENT et rendrait un
 * vert parfait sans avoir rien éprouvé : le `--skip-plugins` oublié dans
 * `run.sh`, un nom de plugin changé, et le juge ne juge plus rien.
 * ========================================================================= */

$aod_dg_absent = ! class_exists( 'AOD_Carrier' );
aod_dg_ok(
	$aod_dg_absent,
	'ANCRAGE : le socle COD est bien ABSENT de ce processus',
	'AOD_Carrier est présente — la porte n’est pas lancée avec --skip-plugins=aod-cod-form, elle n’éprouve RIEN'
);
if ( ! $aod_dg_absent ) {
	echo "\n\033[31m✗ degrade : arrêt, l’état dégradé n’est pas en place.\033[0m\n\n";
	exit( 1 );
}

// …et l'ancrage inverse : le tableau de bord, lui, doit être chargé.
aod_dg_ok(
	class_exists( 'AOD_CD_Dashboard' ) && class_exists( 'AOD_CD_Cod' ),
	'ANCRAGE : le tableau de bord, lui, est bien chargé',
	'sans lui il n’y a rien à rendre, et « aucune fatale » serait vrai pour rien'
);
aod_dg_ok( ! AOD_CD_Cod::active(), 'AOD_CD_Cod::active() rapporte correctement l’absence' );

/* =========================================================================
 * 1 · Les 12 sections rendent, aucune ne meurt
 * ========================================================================= */

echo "\n  \033[1mrendu des 12 sections en gérant\033[0m\n";

// Gérant : c'est le pire cas. Ses capabilities ouvrent TOUS les blocs, y compris
// ceux qui dépendent du socle COD — un confirmateur restreint n'atteindrait
// même pas le code fautif.
$aod_dg_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( $aod_dg_admin ) {
	wp_set_current_user( (int) $aod_dg_admin[0] );
}
aod_dg_ok( current_user_can( 'manage_woocommerce' ), 'ANCRAGE : on rend bien en gérant (pire cas de couverture)' );

// 🚨 CACHE FROID OBLIGATOIRE. La fatale d'origine vivait dans une closure
// `cached()` : avec un transient chaud, la porte passerait au vert sans jamais
// exécuter la ligne fautive. C'est exactement ce qui l'a rendue invisible.
if ( method_exists( 'AOD_CD_Section_Home', 'bump_cache_version' ) ) {
	AOD_CD_Section_Home::bump_cache_version();
}
wp_cache_flush();

$aod_dg_sections = array(
	'home', 'orders', 'abandoned', 'products', 'stock', 'categories',
	'shipping', 'tracking', 'marketing', 'whatsapp', 'team', 'account',
);

$aod_dg_render = new ReflectionMethod( 'AOD_CD_Dashboard', 'render_app' );
$aod_dg_render->setAccessible( true );
$aod_dg_app    = AOD_CD_Dashboard::instance();

foreach ( $aod_dg_sections as $s ) {
	ob_start();
	$err = '';
	try {
		$aod_dg_render->invoke( $aod_dg_app, $s );
	} catch ( \Throwable $e ) {
		$err = get_class( $e ) . ' : ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
	}
	$html = ob_get_clean();

	// Une section qui rend 200 octets n'a pas rendu : elle a avorté en silence.
	// L'absence de fatale ne suffit donc pas — on exige aussi du contenu.
	aod_dg_ok(
		'' === $err && strlen( $html ) > 2000,
		sprintf( '/%s rend sans fatale (%d o)', $s, strlen( $html ) ),
		'' !== $err ? $err : 'rendu trop court : la section a probablement avorté'
	);
}

/* =========================================================================
 * 2 · Les helpers publics survivent hors de tout contexte gardé
 *
 * `tracking_tone()` et `tracking_pill()` sont `public static` : elles étaient
 * sûres uniquement parce que TOUS leurs appelants portaient une garde. C'est une
 * propriété qui se périme au premier appel écrit ailleurs.
 * ========================================================================= */

echo "\n  \033[1mhelpers publics appelés à nu\033[0m\n";

$aod_dg_nu = '';
try {
	// Sur AOD_CD_Section_Tracking depuis la 30.5 (elles ont suivi leur section).
	// Cette porte les appelait encore sur AOD_CD_Dashboard : référence morte
	// restée invisible parce que la DoD des étapes 30.x jouait la porte `rtl`,
	// pas `degrade` — trouvée au premier rejeu (35.1, le 2026-07-23).
	AOD_CD_Section_Tracking::tracking_tone( 'peu importe' );
	AOD_CD_Section_Tracking::tracking_pill( '' );
} catch ( \Throwable $e ) {
	$aod_dg_nu = $e->getMessage();
}
aod_dg_ok(
	'' === $aod_dg_nu,
	'tracking_tone() / tracking_pill() ne meurent pas hors contexte gardé',
	$aod_dg_nu
);

/* =========================================================================
 * Bilan
 * ========================================================================= */

$aod_dg_s = aod_dg_score();
echo "\n";
if ( $aod_dg_s['fail'] ) {
	echo "\033[31m✗ degrade : {$aod_dg_s['pass']} passé(s), {$aod_dg_s['fail']} échec(s)\033[0m\n\n";
	exit( 1 );
}
if ( ! $aod_dg_s['pass'] ) {
	echo "\033[31m✗ degrade : AUCUN contrôle n'a été exécuté\033[0m\n\n";
	exit( 1 );
}
echo "\033[32m✅ degrade : {$aod_dg_s['pass']} passé(s), 0 échec\033[0m\n\n";
