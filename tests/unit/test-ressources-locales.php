<?php
/**
 * Aucune ressource de RENDU ne vient d'un hôte externe (Phase 20).
 *
 * Le défaut d'origine : la coque de `/gestion` chargeait Barlow depuis
 * `fonts.googleapis.com`, dans le plugin même dont `assets/icons/README.md`
 * justifie l'embarquement des 71 SVG par « sans npm, sans CDN, sans build ».
 *
 * Pourquoi un cliquet et pas une relecture : le mode d'échec est MUET. Sans
 * réseau — le cas nominal d'un clone neuf (TEMPLATE.md §3) — la requête échoue,
 * `font-family` sert son repli et l'écran reste parfaitement crédible. Il n'est
 * simplement plus le produit. Personne ne signale ce qui a l'air normal, et
 * aucune porte de recette ne tourne sans réseau. C'est la leçon de D1 et D6 :
 * une règle sans cliquet finit par ne plus être tenue.
 *
 * ⚠️ La règle porte sur **l'hôte externe**, jamais sur un domaine nommé.
 * Interdire `fonts.googleapis.com` laisserait passer le prochain CDN.
 *
 * PÉRIMÈTRE — ce que le navigateur va chercher TOUT SEUL pour rendre la page :
 *   1. `url()` et `@import` dans toutes les feuilles des deux plugins ;
 *   2. `<link>` et `<script src>` dans la coque de `/gestion` et sa page de
 *      connexion.
 *
 * HORS PÉRIMÈTRE — et c'est nommé ici plutôt que passé sous silence (règle du
 * témoin, `tools/recette/README.md`). Ces appels sortants sont **légitimes** :
 *   - **pixels marketing** (`facebook.com`, `googletagmanager.com`) : opt-in,
 *     éteints par défaut, et c'est leur raison d'être que d'atteindre la régie ;
 *   - **façade vidéo** (`youtube.com`, `youtube-nocookie.com`) : elle existe
 *     précisément pour NE RIEN charger avant un clic (Phase 8.3) ;
 *   - **liens** (`wa.me`, `dash.cloudflare.com`) : une ancre que l'utilisateur
 *     clique n'est pas une ressource de rendu.
 * Les confondre rendrait ce test ininterprétable — il rougirait en permanence
 * sur du code correct, et on finirait par le désarmer.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Feuilles et coques inspectées.
 *
 * ⚠️ Préfixe `aod_test_res_` : `tests/all.php` charge tous les fichiers par
 * `glob()` dans le même espace global (cf. test-charte-surfaces.php).
 *
 * @return array{css:string[],shell:string[]}
 */
function aod_test_res_fichiers() {
	$cod = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : dirname( AOD_CD_PATH ) . '/aod-cod-form/';
	$css = array_merge(
		glob( AOD_CD_PATH . 'assets/css/*.css' ) ? glob( AOD_CD_PATH . 'assets/css/*.css' ) : array(),
		glob( $cod . 'assets/css/*.css' ) ? glob( $cod . 'assets/css/*.css' ) : array()
	);
	return array(
		'css'   => $css,
		'shell' => array(
			AOD_CD_PATH . 'includes/class-aod-cd-dashboard.php',
			AOD_CD_PATH . 'includes/class-aod-cd-access.php',
		),
	);
}

/**
 * Relève les hôtes externes d'une chaîne CSS (`url()` et `@import`).
 *
 * On ignore `data:` (inline, pas un appel réseau) et tout ce qui est relatif.
 *
 * @param string $css Contenu de la feuille.
 * @return string[] Hôtes trouvés.
 */
function aod_test_res_css_hotes( $css ) {
	$out = array();
	if ( preg_match_all( '#(?:url\(|@import\s+)[\'"\s]*(https?:)?//([a-z0-9.\-]+)#i', $css, $m ) ) {
		foreach ( $m[2] as $h ) {
			$out[] = strtolower( $h );
		}
	}
	return $out;
}

/**
 * Relève les hôtes externes des `<link href>` / `<script src>` d'une coque PHP.
 *
 * @param string $php Contenu du fichier.
 * @return string[] Hôtes trouvés.
 */
function aod_test_res_shell_hotes( $php ) {
	$out = array();
	if ( preg_match_all( '#<(?:link|script)\b[^>]*\b(?:href|src)\s*=\s*[\'"](?:https?:)?//([a-z0-9.\-]+)#i', $php, $m ) ) {
		foreach ( $m[1] as $h ) {
			$out[] = strtolower( $h );
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * 1. Les feuilles de style ne sortent pas du serveur
 * ---------------------------------------------------------------------- */

AOD_Test::group( 'Ressources locales — aucune feuille ne charge depuis un hôte externe' );

$aod_res_f   = aod_test_res_fichiers();
$aod_res_lus = 0;

foreach ( $aod_res_f['css'] as $aod_res_p ) {
	$aod_res_c = (string) file_get_contents( $aod_res_p ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du dépôt, contexte de test.
	++$aod_res_lus;
	$aod_res_h = aod_test_res_css_hotes( $aod_res_c );
	AOD_Test::eq(
		'',
		implode( ', ', $aod_res_h ),
		sprintf( '%s ne référence aucun hôte externe', basename( $aod_res_p ) )
	);
}

// TÉMOIN — un `glob()` vide se déclarerait vert (mode d'échec relevé en 18.3).
AOD_Test::ok(
	$aod_res_lus >= 4,
	sprintf( 'Le scan a bien lu les feuilles du dépôt (%d lues, 4 attendues au minimum)', $aod_res_lus )
);

// TÉMOIN — le scan attrape-t-il vraiment une violation ? On lui en donne une.
AOD_Test::eq(
	'fonts.googleapis.com',
	implode( ',', aod_test_res_css_hotes( '@import url("https://fonts.googleapis.com/css2?family=Barlow");' ) ),
	'Témoin : le scan repère un @import vers un CDN'
);
AOD_Test::eq(
	'cdn.example.net',
	implode( ',', aod_test_res_css_hotes( "src:url(//cdn.example.net/x.woff2) format('woff2');" ) ),
	'Témoin : le scan repère une url() protocol-relative (le cas qu\'un filtre sur "https" raterait)'
);
AOD_Test::eq(
	'',
	implode( ',', aod_test_res_css_hotes( 'src:url("../fonts/barlow-latin-400.woff2");background:url(data:image/svg+xml;base64,AA==)' ) ),
	'Témoin : une url() relative et un data: ne sont pas comptés comme externes'
);

/* -------------------------------------------------------------------------
 * 2. La coque de /gestion ne tire ni feuille ni script d'ailleurs
 * ---------------------------------------------------------------------- */

AOD_Test::group( 'Ressources locales — la coque de /gestion ne tire rien d\'un CDN' );

foreach ( $aod_res_f['shell'] as $aod_res_p ) {
	AOD_Test::ok( file_exists( $aod_res_p ), sprintf( '%s est présent', basename( $aod_res_p ) ) );
	if ( ! file_exists( $aod_res_p ) ) {
		continue;
	}
	$aod_res_c = (string) file_get_contents( $aod_res_p ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du dépôt, contexte de test.
	AOD_Test::eq(
		'',
		implode( ', ', aod_test_res_shell_hotes( $aod_res_c ) ),
		sprintf( '%s : aucun <link>/<script src> vers un hôte externe', basename( $aod_res_p ) )
	);
}

// TÉMOIN — le scan de coque attrape le défaut historique, dans sa forme exacte.
AOD_Test::eq(
	'fonts.googleapis.com',
	implode( ',', aod_test_res_shell_hotes( '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400&display=swap">' ) ),
	'Témoin : le scan repère le <link> qui a motivé la Phase 20'
);
AOD_Test::eq(
	'',
	implode( ',', aod_test_res_shell_hotes( '<link rel="stylesheet" href="<?php echo esc_url( AOD_CD_URL . \'assets/css/dashboard.css\' ); ?>">' ) ),
	'Témoin : la feuille locale de la coque n\'est pas prise pour un CDN'
);

/* -------------------------------------------------------------------------
 * 3. Les @font-face déclarés pointent des fichiers qui EXISTENT
 * ---------------------------------------------------------------------- */

AOD_Test::group( 'Ressources locales — chaque @font-face pointe un fichier réel' );

/*
 * C'est le contrôle qui compte le plus, et il n'est pas redondant avec les deux
 * précédents : un `@font-face` dont le `src` pointe un fichier ABSENT ne produit
 * aucune erreur. Le navigateur passe au repli — exactement le silence que la
 * Phase 20 corrige. Retirer le CDN sans embarquer les fichiers laisserait donc
 * les deux premiers groupes VERTS et la charte cassée.
 */

$aod_res_css = (string) file_get_contents( AOD_CD_PATH . 'assets/css/dashboard.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du dépôt, contexte de test.
$aod_res_n   = preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $aod_res_css, $aod_res_ff );

AOD_Test::eq( 5, (int) $aod_res_n, 'Les 5 graisses de Barlow sont déclarées (400/500/600/700/800)' );

$aod_res_poids = array();
foreach ( (array) $aod_res_ff[1] as $aod_res_bloc ) {
	preg_match( '/font-weight\s*:\s*(\d+)/i', $aod_res_bloc, $aod_res_w );
	preg_match( '/url\(\s*[\'"]?([^\'")]+)/i', $aod_res_bloc, $aod_res_u );
	$aod_res_poids[] = isset( $aod_res_w[1] ) ? (int) $aod_res_w[1] : 0;

	$aod_res_rel = isset( $aod_res_u[1] ) ? $aod_res_u[1] : '';
	$aod_res_abs = realpath( AOD_CD_PATH . 'assets/css/' . $aod_res_rel );
	AOD_Test::ok(
		$aod_res_abs && is_readable( $aod_res_abs ),
		sprintf( 'Le fichier de la graisse %d existe (%s)', end( $aod_res_poids ), $aod_res_rel )
	);

	/*
	 * Et c'est bien une FONTE, pas une page d'erreur enregistrée par curl —
	 * mode d'échec réel quand un CDN répond 403 à un User-Agent inconnu.
	 * `wOF2` est le nombre magique du format.
	 */
	if ( $aod_res_abs && is_readable( $aod_res_abs ) ) {
		$aod_res_fh    = fopen( $aod_res_abs, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fichier local du dépôt, contexte de test.
		$aod_res_magic = $aod_res_fh ? fread( $aod_res_fh, 4 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- idem.
		if ( $aod_res_fh ) {
			fclose( $aod_res_fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- idem.
		}
		AOD_Test::eq( 'wOF2', $aod_res_magic, sprintf( 'La graisse %d est un vrai woff2, pas une page d\'erreur', end( $aod_res_poids ) ) );
	}
}

sort( $aod_res_poids );
AOD_Test::eq(
	'400,500,600,700,800',
	implode( ',', $aod_res_poids ),
	'Les graisses déclarées sont exactement celles que la charte utilise'
);

/*
 * L'OFL impose de joindre la licence à toute redistribution des fichiers.
 * Phosphor est MIT et pardonne l'oubli ; l'OFL non.
 */
AOD_Test::ok(
	is_readable( AOD_CD_PATH . 'assets/fonts/LICENSE' ),
	'La licence OFL-1.1 accompagne les fontes (obligation de l\'OFL, pas du MIT)'
);

/*
 * Le repli système reste déclaré APRÈS Barlow : c'est lui qui rend l'arabe,
 * que Barlow ne couvre pas. Un jour où quelqu'un « nettoierait » la pile en
 * ne laissant que "Barlow", `/gestion` en AR tomberait sur la police par
 * défaut du navigateur — sans que rien ne le signale.
 */
AOD_Test::ok(
	(bool) preg_match( '/font-family\s*:\s*"Barlow"\s*,\s*[^;]*sans-serif/i', $aod_res_css ),
	'Barlow est suivi d\'une pile de repli système (elle rend l\'arabe, non couvert par Barlow)'
);
