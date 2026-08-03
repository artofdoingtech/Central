<?php
/**
 * La barre d'action groupée est PERMANENTE, et ses actions sont désarmées à vide
 * (Phase 45.2).
 *
 * 🚨 POURQUOI CE FICHIER EXISTE, ET CE QU'IL CORRIGE D'ABORD.
 * Le commentaire qui vivait dans `dashboard.css` annonçait, en toutes lettres,
 * un cliquet `test-charte-surfaces.php` portant la « règle *[hidden] neutralisé* ».
 * Mesuré en instruisant la Phase 45 : **0 occurrence** de `hidden` dans ce
 * fichier, **0 occurrence** de `bulkbar` dans tout `tests/`. Ce cliquet n'a
 * jamais existé. La décision de 2026 — masquer la barre à vide — a donc vécu
 * deux ans sans le moindre témoin, et rien n'aurait signalé sa disparition.
 *
 * L'invariant a changé en 45.2, à la demande de l'owner : la barre est rendue en
 * permanence. Mais la GARANTIE, elle, n'a pas changé — on ne peut toujours pas
 * agir sur une sélection vide. Elle est simplement portée par `disabled` au lieu
 * du masquage. C'est cette garantie que ce fichier garde, et c'est pour cela
 * qu'il ne se contente pas de vérifier l'absence de `hidden` : une barre visible
 * dont les boutons seraient actifs à vide serait EXACTEMENT le défaut de 2026,
 * en plus voyant.
 *
 * Contrôles STATIQUES : ils lisent le rendu serveur et la feuille. Le
 * comportement dynamique (cocher arme, décocher désarme) est mesuré au
 * navigateur par les portes `assignation` et `abandonnees` — un cliquet
 * statique ne verra jamais un `disabled` retiré par du JS.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

// ⚠️ `AOD_CD_PATH` et pas un chemin relatif à `__DIR__` : la suite s'exécute
// DANS le conteneur, où les plugins sont bind-montés sous `wp-content/plugins/`
// et non sous `plugins-dev/`. Un chemin déduit du dossier de tests ne résout
// rien — et le cliquet l'a dit à voix haute (« 0 barre trouvée ») au lieu de
// verdir à vide, ce qui est précisément la règle du témoin.
$aod_bb_plugin = rtrim( AOD_CD_PATH, '/' );

$aod_bb_fichiers = array(
	'orders'    => $aod_bb_plugin . '/includes/class-aod-cd-section-orders.php',
	'abandoned' => $aod_bb_plugin . '/includes/class-aod-cd-section-abandoned.php',
);
$aod_bb_js       = array(
	'orders'    => $aod_bb_plugin . '/assets/js/section-orders.js',
	'abandoned' => $aod_bb_plugin . '/assets/js/section-abandoned.js',
);
$aod_bb_css_f    = $aod_bb_plugin . '/assets/css/dashboard.css';

AOD_Test::group( 'Barre groupée 45.2 — R0 : les sources sont lisibles' );

$aod_bb_src = array();
foreach ( $aod_bb_fichiers as $aod_bb_k => $aod_bb_p ) {
	$aod_bb_src[ $aod_bb_k ] = is_readable( $aod_bb_p ) ? (string) file_get_contents( $aod_bb_p ) : '';
	AOD_Test::ok( '' !== $aod_bb_src[ $aod_bb_k ], sprintf( 'Section « %s » lisible', $aod_bb_k ) );
}
$aod_bb_jssrc = array();
foreach ( $aod_bb_js as $aod_bb_k => $aod_bb_p ) {
	$aod_bb_jssrc[ $aod_bb_k ] = is_readable( $aod_bb_p ) ? (string) file_get_contents( $aod_bb_p ) : '';
	AOD_Test::ok( '' !== $aod_bb_jssrc[ $aod_bb_k ], sprintf( 'Module JS « %s » lisible', $aod_bb_k ) );
}
$aod_bb_css = is_readable( $aod_bb_css_f ) ? (string) file_get_contents( $aod_bb_css_f ) : '';
AOD_Test::ok( '' !== $aod_bb_css, 'dashboard.css lisible' );

/* =========================================================================
 * R1 — Les deux barres sont RENDUES, jamais masquées à la construction
 * ========================================================================= */

AOD_Test::group( 'Barre groupée 45.2 — R1 : rendue en permanence' );

$aod_bb_barres = 0;
foreach ( $aod_bb_src as $aod_bb_k => $aod_bb_code ) {
	if ( preg_match( '/<div class="aod-cd-bulkbar[^"]*" id="([a-z-]+)"([^>]*)>/', $aod_bb_code, $aod_bb_m ) ) {
		++$aod_bb_barres;
		AOD_Test::ok(
			false === strpos( $aod_bb_m[2], 'hidden' ),
			sprintf( '🔴 « %s » : la barre #%s est rendue SANS attribut `hidden`', $aod_bb_k, $aod_bb_m[1] ),
			'un `hidden` au rendu ramènerait le saut de mise en page que 45.2 supprime'
		);
		AOD_Test::ok(
			false !== strpos( $aod_bb_m[0], 'is-vide' ),
			sprintf( '« %s » : la barre part à l’état `is-vide` (aucune ligne n’est cochée au chargement)', $aod_bb_k )
		);
	}
}
AOD_Test::ok(
	2 === $aod_bb_barres,
	sprintf( 'TÉMOIN : %d barre(s) trouvée(s) — attendu 2 (Commandes + Abandonnées)', $aod_bb_barres ),
	'un motif qui ne trouve rien rendrait toutes les assertions de R1 vraies par vacuité'
);

/* =========================================================================
 * R2 — Toute ACTION de la barre est marquée, et désarmée au rendu
 *
 * C'est la garantie de 2026, déplacée : ce n'est plus « on ne voit pas le
 * bouton », c'est « on ne peut pas l'actionner ».
 * ========================================================================= */

AOD_Test::group( 'Barre groupée 45.2 — R2 : aucune action activable à vide' );

$aod_bb_actions = 0;
$aod_bb_nues    = array();
foreach ( $aod_bb_src as $aod_bb_k => $aod_bb_code ) {
	// Les boutons d'action des barres : leur classe les nomme tous.
	if ( preg_match_all( '/<button[^>]*class="[^"]*aod-cd-(?:order|lead)-bulk-(?:del|assign)[^"]*"[^>]*>/', $aod_bb_code, $aod_bb_b ) ) {
		foreach ( $aod_bb_b[0] as $aod_bb_btn ) {
			++$aod_bb_actions;
			$aod_bb_marque = ( false !== strpos( $aod_bb_btn, 'aod-cd-bulk-act' ) );
			$aod_bb_off    = (bool) preg_match( '/\sdisabled(?=[\s\'">])/', $aod_bb_btn );
			if ( ! $aod_bb_marque || ! $aod_bb_off ) {
				$aod_bb_nues[] = $aod_bb_k . ' : ' . ( $aod_bb_marque ? '' : 'sans .aod-cd-bulk-act ' ) . ( $aod_bb_off ? '' : 'sans disabled' );
			}
		}
	}
}
AOD_Test::ok(
	$aod_bb_actions >= 3,
	sprintf( 'TÉMOIN : %d bouton(s) d’action inspecté(s) — attendu ≥ 3 (Assigner + Supprimer + Supprimer)', $aod_bb_actions )
);
AOD_Test::ok(
	empty( $aod_bb_nues ),
	'🔴 Chaque action porte `.aod-cd-bulk-act` ET `disabled` au rendu',
	implode( ' · ', $aod_bb_nues ) . ' — une action rendue active alors que rien n’est sélectionné, c’est le « Supprimer (0) » de 2026'
);

/* =========================================================================
 * R3 — Le JS ARME les actions ; il ne masque plus la barre
 * ========================================================================= */

AOD_Test::group( 'Barre groupée 45.2 — R3 : le JS arme au lieu de masquer' );

foreach ( $aod_bb_jssrc as $aod_bb_k => $aod_bb_code ) {
	// On ne regarde que le CODE : un commentaire qui cite `bar.hidden` pour
	// expliquer l'historique ne doit pas faire rougir le cliquet. (Le piège
	// exact qui a mordu `test-sidebar-rail.php`, cf. son en-tête.)
	$aod_bb_nu = preg_replace( '#/\*.*?\*/#s', '', $aod_bb_code );
	$aod_bb_nu = preg_replace( '#^\s*//.*$#m', '', (string) $aod_bb_nu );

	AOD_Test::ok(
		false === strpos( (string) $aod_bb_nu, 'bar.hidden' ),
		sprintf( '🔴 « %s » : plus aucune écriture de `bar.hidden` dans le code', $aod_bb_k ),
		'la barre redeviendrait intermittente, et les portes qui mesurent son état diraient l’inverse du produit'
	);
	AOD_Test::ok(
		false !== strpos( (string) $aod_bb_nu, '.aod-cd-bulk-act' ),
		sprintf( '« %s » : le JS pilote bien les actions par `.aod-cd-bulk-act`', $aod_bb_k )
	);
	AOD_Test::ok(
		(bool) preg_match( '/disabled\s*=\s*vide/', (string) $aod_bb_nu ),
		sprintf( '🔴 « %s » : l’état des actions SUIT la sélection (`disabled = vide`)', $aod_bb_k ),
		'sans cette ligne, les boutons resteraient désactivés pour toujours — et R2 passerait quand même'
	);
}

/* =========================================================================
 * R4 — La peau ne suppose plus un fond sombre
 *
 * Trois règles n'existaient que parce que la barre était noire. Sur une surface
 * claire, elles donnent blanc sur blanc — un défaut qu'aucune assertion
 * fonctionnelle ne peut voir.
 * ========================================================================= */

AOD_Test::group( 'Barre groupée 45.2 — R4 : la peau suit la surface claire' );

// Bloc de la règle `.aod-cd-bulkbar{…}` (la déclaration, pas ses variantes).
$aod_bb_bloc = '';
if ( preg_match( '/\n\.aod-cd-bulkbar\{([^}]*)\}/', $aod_bb_css, $aod_bb_m2 ) ) {
	$aod_bb_bloc = $aod_bb_m2[1];
}
AOD_Test::ok( '' !== $aod_bb_bloc, 'TÉMOIN : la règle `.aod-cd-bulkbar{…}` est isolée dans la feuille' );

// ⚠️ On vise la déclaration `background`, PAS la présence de `--cd-ink` dans le
// bloc : cette variable y est parfaitement légitime comme ENCRE (`color`), et
// une première version de ce cliquet a rougi pour ça. Un contrôle trop large ne
// protège pas mieux — il apprend à ignorer le rouge.
$aod_bb_fond = '';
if ( '' !== $aod_bb_bloc && preg_match( '/(?:^|;)\s*background(?:-color)?\s*:\s*([^;]+)/', $aod_bb_bloc, $aod_bb_m4 ) ) {
	$aod_bb_fond = trim( $aod_bb_m4[1] );
}
AOD_Test::ok( '' !== $aod_bb_fond, 'TÉMOIN : la déclaration de fond de la barre est lue (' . $aod_bb_fond . ')' );
AOD_Test::ok(
	'' !== $aod_bb_fond && false === strpos( $aod_bb_fond, '--cd-ink' ),
	'🔴 Le FOND de la barre n’est plus `--cd-ink`',
	'fond mesuré : ' . $aod_bb_fond . ' — le bandeau noir est revenu, et avec lui les trois règles qui en dépendaient'
);
AOD_Test::ok(
	'' !== $aod_bb_bloc && false !== strpos( $aod_bb_bloc, 'var(--cd-card)' ),
	'La barre est une surface de la charte (`--cd-card`), pas une couleur à elle'
);

// Aucune encre blanche en dur sur les contrôles de la barre : sur fond clair,
// c'est invisible. On cible les règles, pas les commentaires.
$aod_bb_css_nu = (string) preg_replace( '#/\*.*?\*/#s', '', $aod_bb_css );
$aod_bb_blancs = array();
foreach ( array( '.aod-cd-bulk-ow', '.aod-cd-order-bulk-assign', '.aod-cd-order-bulk-assign-select' ) as $aod_bb_sel ) {
	if ( preg_match( '/\n' . preg_quote( $aod_bb_sel, '/' ) . '\{([^}]*)\}/', $aod_bb_css_nu, $aod_bb_m3 ) ) {
		if ( preg_match( '/(?:^|[:;\s])(?:color|background|background-color|border(?:-color)?)\s*:[^;]*#fff\b/i', $aod_bb_m3[1] ) ) {
			$aod_bb_blancs[] = $aod_bb_sel;
		}
	}
}
AOD_Test::ok(
	empty( $aod_bb_blancs ),
	'🔴 Aucun contrôle de la barre ne pose de blanc en dur (il serait invisible sur la surface claire)',
	implode( ', ', $aod_bb_blancs ) . ' — c’est le « blanc sur blanc » que le changement de fond rend possible'
);

// L'anneau de focus de la case « écraser » ne dépend plus de l'accent : la règle
// générale de la feuille l'interdit depuis toujours, et sur fond clair un accent
// pâle disparaît pour de bon.
AOD_Test::ok(
	false === strpos( $aod_bb_css_nu, '.aod-cd-bulk-ow input[type="checkbox"]:focus-visible{outline:2px solid var(--cd-yellow)' ),
	'🔴 Le focus de la case « écraser » ne se peint plus avec l’accent',
	'un accent pâle rendait l’anneau invisible — exactement ce que la règle générale du fichier interdit'
);
