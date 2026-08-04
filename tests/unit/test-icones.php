<?php
/**
 * Catalogue d'icônes de /gestion — `AOD_CD_Icon` (ROADMAP Phase 15, étape 15.2).
 *
 * Ce que ces tests protègent, et pourquoi ça ne se voit pas à l'œil :
 *
 * 1. Une icône manquante ne casse RIEN visiblement — elle laisse un trou dans la
 *    navigation, à côté d'un libellé qui, lui, s'affiche. Personne ne le remarque.
 * 2. Une couleur écrite en dur dans un fichier SVG rompt le lien avec l'accent du
 *    Personnalisateur. L'icône reste belle… et ne suit plus la charte du client.
 *    Ça ne se voit que sur un clone dont l'accent n'est pas celui de dev.
 * 3. Un `directional()` posé sur `trend-up` retournerait une courbe montante en
 *    courbe descendante — l'indicateur dirait l'inverse de sa valeur, en arabe
 *    seulement.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Icônes — catalogue fermé (AOD_CD_Icon)' );

$catalog = AOD_CD_Icon::catalog();
$filled  = AOD_CD_Icon::filled();

/* ------------------------------------------------------------------
 * 1. Tout nom du catalogue résout vers un fichier réellement présent.
 * ---------------------------------------------------------------- */
$manquants = array();
foreach ( $catalog as $name => $file ) {
	$path = AOD_CD_PATH . 'assets/icons/regular/' . $file . '.svg';
	if ( ! is_readable( $path ) ) {
		$manquants[] = $name . ' → regular/' . $file . '.svg';
	}
}
AOD_Test::ok(
	empty( $manquants ),
	'Chaque nom du catalogue a son fichier `regular` (' . count( $catalog ) . ' icônes)',
	implode( ', ', $manquants )
);

$manquants_fill = array();
foreach ( $filled as $name ) {
	if ( ! isset( $catalog[ $name ] ) ) {
		$manquants_fill[] = $name . ' (absent du catalogue)';
		continue;
	}
	$path = AOD_CD_PATH . 'assets/icons/fill/' . $catalog[ $name ] . '.svg';
	if ( ! is_readable( $path ) ) {
		$manquants_fill[] = $name . ' → fill/' . $catalog[ $name ] . '.svg';
	}
}
AOD_Test::ok(
	empty( $manquants_fill ),
	'Chaque icône déclarée `fill` a son fichier (' . count( $filled ) . ' icônes)',
	implode( ', ', $manquants_fill )
);

/* ------------------------------------------------------------------
 * 2. Aucune section de /gestion n'est orpheline.
 *
 * C'est LE test qui compte au quotidien : `sections()` est la source de vérité de
 * la navigation. Y ajouter une entrée sans ajouter son icône passerait la revue
 * (le libellé s'affiche) et laisserait un trou muet dans la sidebar.
 * ---------------------------------------------------------------- */
$dashboard = AOD_CD_Dashboard::instance();

// `hidden_sections()` est gardée par `manage_woocommerce`. En CLI il n'y a pas
// d'utilisateur courant : sans ça, la section masquée « Page de vente » sortirait
// de la liste et le test la déclarerait conforme sans jamais la regarder.
// On endosse un gérant le temps du test, puis on rend la main.
$_ancien_user = get_current_user_id();
$_gerant      = get_users(
	array(
		'role'    => 'administrator',
		'number'  => 1,
		'fields'  => 'ID',
		'orderby' => 'ID',
	)
);
if ( $_gerant ) {
	wp_set_current_user( (int) $_gerant[0] );
}
AOD_Test::ok(
	current_user_can( 'manage_woocommerce' ),
	'Contexte de test : un gérant est endossé (sinon la section masquée échapperait au test)'
);

// Le 2ᵉ élément de `sections()` est le NOM D'ICÔNE. On éprouve ce qui est
// réellement passé à `svg()`, pas le slug — les deux coïncident aujourd'hui, rien
// ne garantit que ce sera toujours vrai.
$routables  = AOD_Test::call( $dashboard, 'routable_sections' );
$orphelines = array();
foreach ( $routables as $slug => $meta ) {
	$icone = isset( $meta[1] ) ? $meta[1] : '';
	if ( '' === $icone || ! isset( $catalog[ $icone ] ) ) {
		$orphelines[] = $slug . ( '' === $icone ? ' (aucune)' : ' → ' . $icone );
	}
}
AOD_Test::ok(
	empty( $orphelines ),
	'Chaque section routable a une icône au catalogue (' . count( $routables ) . ' sections, masquées comprises)',
	'sans icône : ' . implode( ', ', $orphelines )
);

// Aucun emoji résiduel : c'est le point de départ de la phase, et une section
// ajoutée à l'ancienne manière repasserait inaperçue.
$emojis = array();
foreach ( $routables as $slug => $meta ) {
	if ( isset( $meta[1] ) && preg_match( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', $meta[1] ) ) {
		$emojis[] = $slug;
	}
}
AOD_Test::ok(
	empty( $emojis ),
	'Aucune section ne porte encore un emoji (le rendu dépendait de l’OS)',
	implode( ', ', $emojis )
);

// Les sections de menu doivent TOUTES exister en `fill` : c'est ce qui porte
// l'état actif de la navigation. Une section qui n'aurait que `regular`
// perdrait son signal d'état sans que rien ne le signale.
$icones_sections = array();
foreach ( $routables as $meta ) {
	if ( isset( $meta[1] ) ) {
		$icones_sections[] = $meta[1];
	}
}
$sans_fill = array_values( array_diff( $icones_sections, $filled ) );
AOD_Test::ok(
	empty( $sans_fill ),
	'Chaque section est disponible en graisse `fill` (état actif)',
	'sans fill : ' . implode( ', ', $sans_fill )
);

/* ------------------------------------------------------------------
 * 2bis. Les icônes des cartes KPI.
 *
 * Elles ne passent PAS par `sections()` : chaque carte est un tableau construit
 * à la volée dans une méthode, derrière des requêtes SQL et des capabilities. On
 * ne peut pas les instancier ici sans monter un jeu de données complet.
 *
 * D'où un balayage des SOURCES. L'ancrage est la TONALITÉ, qui est un ensemble
 * fermé (blue|indigo|teal|green|amber|red|slate) et suit toujours l'icône dans
 * `kpis()` : `array( libellé, valeur, ICÔNE, TONALITÉ, … )`. Une faute de frappe
 * sur un nom d'icône ne casse rien de visible — `svg()` rend '' et la pastille
 * reste vide. C'est exactement le genre de trou que personne ne remarque.
 * ---------------------------------------------------------------- */
$sources = array(
	AOD_CD_PATH . 'includes/class-aod-cd-section-home.php',
	AOD_CD_PATH . 'includes/class-aod-cd-section-stock.php',
);
$noms_kpi = array();
foreach ( $sources as $src ) {
	$code = file_get_contents( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- fichier local.
	// `[^,]{0,60}?` : la tonalité n'est pas toujours un littéral. Stock l'écrit en
	// ternaire — `'rupture', $rupture > 0 ? 'red' : 'green'`. Sans cette tolérance
	// le motif sautait ces deux cartes, et le test se serait déclaré satisfait
	// après avoir regardé 17 icônes sur 18.
	// ⚠️ `accent` ajoutée le 2026-08-04, et l'oubli s'est vu IMMÉDIATEMENT : quatre
	// cartes de l'Accueil ont quitté `blue`/`indigo`/`teal` pour cette tonalité —
	// celle qui dit « cette pastille ne code aucun état » et prend la teinte du
	// client — et le balayage est aussitôt tombé à 17 noms. C'est le comportement
	// voulu : cette liste est un RECENSEMENT des tonalités, au même titre que celui
	// des tailles de texte (46.5bis). Une tonalité qu'on introduit sans l'inscrire
	// ici rend le motif aveugle aux cartes qui la portent — et un balayage aveugle
	// se déclare satisfait en ayant regardé moins que ce qu'il annonce.
	if ( preg_match_all( "/,\s*'([a-z0-9-]+)'\s*,[^,]{0,60}?'(?:accent|blue|indigo|teal|green|amber|red|slate)'/", $code, $m ) ) {
		foreach ( $m[1] as $n ) {
			$noms_kpi[ $n ] = basename( $src );
		}
	}
}
AOD_Test::ok(
	count( $noms_kpi ) >= 18,
	'Le balayage des sources trouve bien les icônes de KPI (' . count( $noms_kpi ) . ' noms distincts)',
	'trop peu : le motif de détection a dû être cassé par un refactor'
);
// Témoin du cas à tonalité TERNAIRE. Sans lui, un motif qui ne saurait plus lire
// les cartes de Stock passerait quand même — il resterait 17 noms trouvés
// ailleurs, au-dessus d'un seuil mal choisi.
AOD_Test::ok(
	isset( $noms_kpi['rupture'] ),
	'Le balayage lit aussi les cartes dont la tonalité est un ternaire (Stock)',
	'« rupture » non détectée : le motif ne couvre plus `$x > 0 ? \'red\' : \'green\'`'
);

$kpi_inconnues = array();
foreach ( $noms_kpi as $n => $src ) {
	if ( ! isset( $catalog[ $n ] ) ) {
		$kpi_inconnues[] = $n . ' (' . $src . ')';
	}
}
AOD_Test::ok(
	empty( $kpi_inconnues ),
	'Chaque icône de carte KPI existe au catalogue',
	'inconnues : ' . implode( ', ', $kpi_inconnues )
);

/* ------------------------------------------------------------------
 * 2ter. Tous les autres points d'appel du catalogue.
 *
 * Même raison que ci-dessus : `svg()` rend '' sur un nom inconnu. Un état vide
 * sans icône, une tuile d'alerte sans glyphe — rien ne casse, rien ne se voit,
 * et la revue passe. On balaie donc les sources pour TOUTES les formes d'appel.
 * ---------------------------------------------------------------- */
$appels  = array();
$motifs  = array(
	// AOD_CD_Icon::svg( 'nom' …) et js_map( array( 'nom', … ) )
	// `(?://[^\n]*\n\s*)?` : plusieurs appels portent un `// phpcs:ignore` JUSTE
	// après la parenthèse ouvrante, l'icône n'arrivant qu'à la ligne suivante.
	// Sans cette tolérance le motif sautait 4 états vides (abandoned, check,
	// team, unplugged) — et se serait tu.
	"/AOD_CD_Icon::svg\(\s*(?:\/\/[^\n]*\n\s*)?'([a-z0-9-]+)'/",
	"/empty_state\(\s*(?:\/\/[^\n]*\n\s*)?'([a-z0-9-]+)'/",
	// Tableaux de configuration : 'icon' => 'nom', 'ic' => 'nom'
	"/'ic(?:on)?'\s*=>\s*'([a-z0-9-]+)'/",
);
foreach ( glob( AOD_CD_PATH . 'includes/*.php' ) as $src ) {
	if ( false !== strpos( $src, 'class-aod-cd-icon.php' ) ) {
		continue; // Le catalogue lui-même : ce sont ses définitions, pas des appels.
	}
	$code = file_get_contents( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- fichier local.
	foreach ( $motifs as $motif ) {
		if ( preg_match_all( $motif, $code, $m ) ) {
			foreach ( $m[1] as $n ) {
				$appels[ $n ] = basename( $src );
			}
		}
	}
}
AOD_Test::ok(
	count( $appels ) >= 23,
	'Le balayage trouve les appels au catalogue hors KPI (' . count( $appels ) . ' noms distincts)',
	'trop peu : un motif de détection a dû être cassé'
);

$appels_inconnus = array();
foreach ( $appels as $n => $src ) {
	if ( ! isset( $catalog[ $n ] ) ) {
		$appels_inconnus[] = $n . ' (' . $src . ')';
	}
}
AOD_Test::ok(
	empty( $appels_inconnus ),
	'Chaque icône appelée dans les sources existe au catalogue',
	'inconnues : ' . implode( ', ', $appels_inconnus )
);

/* ------------------------------------------------------------------
 * 2quater. Aucun fichier orphelin.
 *
 * Contrôle INVERSE : un SVG embarqué mais absent du catalogue est du poids mort
 * que personne ne remarquera jamais — le dossier ne fait que grossir au fil des
 * étapes. Le sens catalogue → fichier est déjà couvert plus haut.
 * ---------------------------------------------------------------- */
$fichiers_utiles = array_flip( $catalog );
$orphelins       = array();
foreach ( glob( AOD_CD_PATH . 'assets/icons/regular/*.svg' ) as $f ) {
	$base = basename( $f, '.svg' );
	if ( ! isset( $fichiers_utiles[ $base ] ) ) {
		$orphelins[] = $base;
	}
}
AOD_Test::ok(
	empty( $orphelins ),
	'Aucun SVG embarqué sans entrée au catalogue (' . count( $catalog ) . ' entrées)',
	'à retirer ou à déclarer : ' . implode( ', ', $orphelins )
);

/* ------------------------------------------------------------------
 * 2quinquies. Le cliquet de la phase : plus aucun emoji de CHROME.
 *
 * C'est ce test qui empêche la Phase 15 de se défaire doucement. Sans lui, la
 * prochaine section ajoutée reprendra l'ancienne habitude, et personne ne le
 * verra — un emoji de plus dans une interface qui en contenait soixante ne
 * choque pas.
 *
 * La règle est simple et tient toute seule : un emoji est admis UNIQUEMENT s'il
 * est dans un commentaire (marqueurs 🚨 ⚠️ du code) ou dans une chaîne
 * TRADUISIBLE. Les trois emojis délibérément conservés sont tous dans ce second
 * cas — « Bonjour %s 👋 » (du ton, pas une icône), le « ✅ » du message WhatsApp
 * de test et le « ✓ » d'un message de statut : ce sont des CONTENUS, pas du
 * chrome. Tout emoji ailleurs est du balisage, donc une régression.
 * ---------------------------------------------------------------- */
$emoji_re   = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u';

/*
 * 🚨 Le neutraliseur de chaînes traduisibles a été AVEUGLE pendant toute la
 * Phase 15 — et c'est lui qui a laissé passer le seul emoji de chrome restant.
 *
 * Il s'écrivait `…\s*\(.*` : à partir de la première fonction de traduction, il
 * effaçait la ligne JUSQU'AU BOUT. Or une ligne d'écho met très souvent la
 * traduction dans un ATTRIBUT, donc AVANT le contenu :
 *
 *   echo '<button title="' . esc_attr__( 'Actualiser', … ) . '">🔄</button>';
 *                            └── à partir d'ici, tout était effacé ──────┘
 *
 * Le `🔄` tombait dans la zone effacée. Le cliquet le déclarait vert, et l'a
 * fait à chaque exécution des Phases 15 et 16. C'est exactement la « règle du
 * témoin » (DoD §C.2) prise en défaut par un garde-fou : il ne mentait pas sur
 * ce qu'il voyait, il mentait sur ce qu'il avait REGARDÉ.
 *
 * On ne retire donc plus « la fin de la ligne » mais l'APPEL, et rien que lui —
 * via un motif à parenthèses équilibrées (`(?1)` = récursion sur le groupe 1).
 * Ce qui suit la parenthèse fermante redevient inspectable.
 *
 * Limite connue et assumée : une parenthèse déséquilibrée À L'INTÉRIEUR d'un
 * littéral (`__( 'Voir ) ici' )`) ferme le motif trop tôt et laisse un résidu
 * inspecté. Ça ne peut produire qu'un FAUX POSITIF — un cliquet qui crie à tort,
 * jamais un cliquet qui se tait. C'est le bon sens de l'erreur.
 */
$trad_re = '/\b(?:__|_e|_n|_x|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*(\((?:[^()]++|(?1))*\))/u';

$fautifs    = array();
$n_fichiers = 0;
$n_lignes   = 0;
/*
 * 🚨 CE CLIQUET REGARDE MOINS LARGE QUE SON TITRE — et je ne l'élargis QU'À CE
 * QUE J'AI NETTOYÉ (38.3). Il s'annonce « plus aucun emoji de CHROME » et n'a
 * jamais regardé que `aod-client-dashboard`. Le plugin PUBLIC — celui que
 * l'ACHETEUR voit — en portait plusieurs. J'en ai retiré trois du FORMULAIRE de
 * commande (🏠 🏢 sur le mode de livraison, 🔒 sur la réassurance), remplacés par
 * `AOD_COD_Icon`. Ce balayage garde CE fichier contre leur retour.
 *
 * ⚠️ PÉRIMÈTRE ASSUMÉ, PAS UN OUBLI. Le reste du plugin public porte encore des
 * emojis de chrome hors du tunnel de commande — l'Accueil (💵 🚚 📞 de la
 * réassurance) et le Contact (📍 ✉️). Même défaut, mais les corriger, c'est de
 * nouvelles icônes au catalogue et deux autres pages : un chantier à part, pas
 * « la page produit ». L'étendre ici obligerait soit à tout corriger d'un bloc
 * (débordement de périmètre), soit à déclarer une liste d'exceptions qui
 * ressemblerait à un permis de laisser du chrome en emoji. Consigné en mémoire
 * (`suites-audit-a-traiter.md`) plutôt que masqué. On garde ce qu'on a nettoyé ;
 * on n'affiche pas vert sur ce qu'on n'a pas regardé.
 */
$aod_public_chrome = glob( AOD_COD_PATH . 'includes/class-aod-cod-form.php' ) ?: array();
$a_examiner        = array_merge(
	glob( AOD_CD_PATH . 'includes/*.php' ),
	glob( AOD_CD_PATH . 'assets/js/*.js' ),
	$aod_public_chrome
);
// Témoin : le fichier public visé DOIT avoir été résolu. Sans lui, un chemin qui
// ne résout pas (`?:` → tableau vide) rendrait le balayage silencieusement plus
// étroit — vert parce qu'il n'a rien regardé, exactement le défaut condamné en 27.
AOD_Test::ok(
	1 === count( $aod_public_chrome ) && is_readable( $aod_public_chrome[0] ),
	'Le fichier public gardé (class-aod-cod-form.php) est bien résolu et lisible',
	'sans lui, la garde anti-emoji du formulaire ne regarderait aucun fichier'
);
foreach ( $a_examiner as $src ) {
	++$n_fichiers;
	foreach ( file( $src ) as $i => $ligne ) {
		$nu = ltrim( $ligne );
		// Commentaires : marqueurs de lecture, jamais rendus.
		if ( '' === $nu || 0 === strpos( $nu, '*' ) || 0 === strpos( $nu, '//' ) || 0 === strpos( $nu, '/*' ) || 0 === strpos( $nu, '#' ) ) {
			continue;
		}
		++$n_lignes;
		// Chaînes traduisibles : du contenu, pas du chrome. On retire l'APPEL,
		// pas la fin de la ligne (cf. le bloc ci-dessus).
		$reste = preg_replace( $trad_re, '', $ligne );
		// Commentaire en fin de ligne.
		$reste = preg_replace( '#//.*$#u', '', (string) $reste );
		if ( preg_match( $emoji_re, (string) $reste, $m ) ) {
			$fautifs[] = basename( $src ) . ':' . ( $i + 1 ) . ' (' . $m[0] . ')';
		}
	}
}
AOD_Test::ok(
	empty( $fautifs ),
	sprintf(
		'Aucun emoji de chrome ne subsiste dans les sources (cliquet Phase 15) — %d fichiers, %d lignes inspectées',
		$n_fichiers,
		$n_lignes
	),
	implode( ' · ', array_slice( $fautifs, 0, 8 ) )
);

/*
 * Le cliquet doit prouver qu'il REGARDE ce qui suit un appel de traduction —
 * c'est précisément ce qu'il ne faisait pas. On lui soumet la forme exacte qui
 * lui a échappé (traduction dans l'attribut, emoji dans le contenu) : s'il la
 * laisse passer, il est retombé dans son ancien défaut et l'assertion ci-dessus
 * ne vaut plus rien, quel que soit son vert.
 */
$temoin = 'echo \'<button title="\' . esc_attr__( \'Actualiser\', \'d\' ) . \'">🔄</button>\';';
$t_apres = preg_replace( '#//.*$#u', '', (string) preg_replace( $trad_re, '', $temoin ) );
AOD_Test::ok(
	1 === preg_match( $emoji_re, (string) $t_apres ),
	'Le cliquet voit un emoji placé APRÈS un appel de traduction (témoin de non-régression)',
	'le neutraliseur efface encore la fin de la ligne'
);
AOD_Test::ok(
	0 === preg_match( $emoji_re, (string) preg_replace( $trad_re, '', "sprintf( __( 'Bonjour %s 👋', 'd' ), \$x )," ) ),
	'Le cliquet ignore un emoji placé DANS un appel de traduction (contenu, pas chrome)',
	'le neutraliseur ne retire plus les chaînes traduisibles'
);

/* ------------------------------------------------------------------
 * 3. Aucune couleur en dur dans les fichiers SVG.
 *
 * Les fichiers Phosphor portent `fill="currentColor"`. Un `fill="#xxx"` ou un
 * `stroke="#xxx"` glissé lors d'une mise à jour couperait le lien avec l'accent
 * du Personnalisateur — sans rien casser de visible sur le clone de dev, dont
 * l'accent est celui par défaut.
 * ---------------------------------------------------------------- */
$colores = array();
foreach ( glob( AOD_CD_PATH . 'assets/icons/*/*.svg' ) as $file ) {
	$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- fichier local.
	if ( preg_match( '/(fill|stroke)\s*=\s*"(?!currentColor|none")[^"]+"/i', $raw ) ) {
		$colores[] = basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
AOD_Test::ok(
	empty( $colores ),
	'Aucun SVG ne code une couleur en dur (tous en `currentColor`)',
	implode( ', ', $colores )
);

/* ------------------------------------------------------------------
 * 4. Le drapeau « directionnelle » ne porte que sur des noms existants,
 *    et JAMAIS sur une icône dont le miroir inverserait le sens.
 * ---------------------------------------------------------------- */
$dir      = AOD_CD_Icon::directional();
$inconnus = array_values( array_diff( $dir, array_keys( $catalog ) ) );
AOD_Test::ok(
	empty( $inconnus ),
	'`directional()` ne référence que des icônes du catalogue',
	implode( ', ', $inconnus )
);

// Liste noire explicite : miroiter ces icônes changerait leur SENS, pas seulement
// leur orientation. `trend-up` est le cas d'école — une courbe montante miroitée
// descend, et l'indicateur « Marge estimée » dirait alors l'inverse de sa valeur.
$jamais_miroir = array( 'trend-up', 'check', 'cancel', 'warning', 'blocked', 'percent' );
$fautives      = array_values( array_intersect( $dir, $jamais_miroir ) );
AOD_Test::ok(
	empty( $fautives ),
	'Aucune icône à sens inversable n’est marquée directionnelle',
	'à retirer : ' . implode( ', ', $fautives )
);

/* ------------------------------------------------------------------
 * 5. Le rendu.
 * ---------------------------------------------------------------- */
$html = AOD_CD_Icon::svg( 'home' );
AOD_Test::ok(
	false !== strpos( $html, '<svg' ) && false !== strpos( $html, '</svg>' ),
	'`svg()` rend bien un élément SVG complet'
);
AOD_Test::ok(
	false !== strpos( $html, 'fill="currentColor"' ),
	'Le SVG rendu porte `fill="currentColor"` (l’accent le pilote)'
);
AOD_Test::ok(
	false !== strpos( $html, 'aria-hidden="true"' ) && false !== strpos( $html, 'focusable="false"' ),
	'Le SVG rendu est décoratif (aria-hidden + focusable="false")'
);
AOD_Test::ok(
	false === strpos( $html, '<script' ) && false === strpos( $html, 'xmlns' ),
	'Le SVG rendu ne conserve rien de l’en-tête du fichier source'
);

// Un nom inconnu ne rend RIEN. `_doing_it_wrong()` déclenche une notice PHP en
// contexte de test : on la neutralise pour n'éprouver que la valeur de retour.
add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
AOD_Test::eq( '', AOD_CD_Icon::svg( 'nom-qui-nexiste-pas' ), 'Un nom hors catalogue rend une chaîne vide' );
remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );

// La graisse `fill` n'est servie que si elle existe pour ce nom ; sinon repli
// silencieux sur `regular` plutôt qu'un trou.
$kpi_fill = AOD_CD_Icon::svg( 'money', array( 'weight' => 'fill' ) );
AOD_Test::ok(
	'' !== $kpi_fill,
	'Une icône sans variante `fill` retombe sur `regular` au lieu de disparaître'
);

// Le marqueur RTL n'est posé QUE sur les directionnelles — c'est lui que lit la
// règle CSS `html[dir="rtl"] .aod-cd-i[data-dir]`.
AOD_Test::ok(
	false !== strpos( AOD_CD_Icon::svg( 'shipping' ), 'data-dir="1"' ),
	'Une icône directionnelle porte `data-dir` (miroir RTL)'
);
AOD_Test::ok(
	false === strpos( AOD_CD_Icon::svg( 'trend-up' ), 'data-dir' ),
	'`trend-up` NE porte PAS `data-dir` (miroitée, elle dirait l’inverse)'
);

/* ------------------------------------------------------------------------ *
 * LES FICHIERS EMBARQUÉS SONT INERTES  (ROADMAP Phase 18, étape 18.3)
 *
 * Pourquoi ce contrôle porte sur les FICHIERS et pas sur le rendu. La revue des
 * 33 `phpcs:ignore EscapeOutput` ajoutés par les Phases 15 et 17 a conclu à
 * 0 faille, et toute cette conclusion tient sur UNE prémisse : `inner()` recopie
 * VERBATIM ce qu'il y a entre <svg> et </svg> du fichier, sans le filtrer. Ce qui
 * est sûr tant que les fichiers le sont — et rien ne le vérifiait.
 *
 * Le test voisin (« pas de <script> dans le rendu ») n'y suffit pas : il n'inspecte
 * qu'UNE icône. Un fichier hostile ajouté au catalogue passerait à côté de lui,
 * puis serait injecté sans échappement dans /gestion par 130 sorties annotées.
 *
 * Ce n'est PAS le cliquet sur les `phpcs:ignore` eux-mêmes — celui-là reste hors
 * périmètre (cf. §C.5, D6). C'est le témoin de la prémisse sur laquelle repose
 * le verdict « 0 faille » de la revue.
 * ------------------------------------------------------------------------ */
$icon_dir   = AOD_CD_PATH . 'assets/icons/';
$svg_files  = array_merge(
	glob( $icon_dir . 'regular/*.svg' ) ?: array(),
	glob( $icon_dir . 'fill/*.svg' ) ?: array()
);
$hostiles   = array();
foreach ( $svg_files as $f ) {
	$raw = file_get_contents( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- fichier local du dépôt, lu par un test.
	if ( preg_match( '#<script|\son[a-z]+\s*=|javascript:|<foreignObject|<use[^>]+href#i', $raw ) ) {
		$hostiles[] = basename( dirname( $f ) ) . '/' . basename( $f );
	}
}
// Le compte est lui-même une assertion : un `glob` qui ne trouve plus rien
// rendrait le contrôle suivant vert sans avoir rien regardé (DoD §C.2, règle 1).
AOD_Test::ok(
	count( $svg_files ) >= 60,
	sprintf( 'Les SVG embarqués ont bien été LUS (%d fichiers)', count( $svg_files ) )
);
AOD_Test::ok(
	empty( $hostiles ),
	sprintf( 'Aucun SVG embarqué ne porte de script, de gestionnaire d’événement ni de <use href> (%d inspectés)', count( $svg_files ) ),
	implode( ' · ', array_slice( $hostiles, 0, 5 ) )
);

// Le test ne doit rien laisser derrière lui : les fichiers suivants tournent
// dans le même processus.
wp_set_current_user( $_ancien_user );
