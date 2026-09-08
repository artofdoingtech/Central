<?php
/**
 * La peau du panier COD — le thème s'arrête à notre markup (Phase 37).
 *
 * Le défaut fondateur : notre propre markup portait `class="button"`. Cette
 * classe fait matcher les règles de boutons du thème (Astra les imprime en
 * `<style>` inline, donc APRÈS nos feuilles), et le fond de marque écrasait le
 * nôtre. Mesuré au navigateur sur le clone : « Ajouter au panier » ressortait
 * en vert sur bleu, **1,05:1** — le point d'entrée du panier sur la fiche
 * produit était invisible, sous une porte `panier` verte (elle vérifie la
 * présence et le comportement, jamais le contraste) et une porte `contraste`
 * qui n'audite que `/gestion`.
 *
 * Ce cliquet est STATIQUE : il lit les fichiers, sans navigateur. Il ne
 * remplace donc pas la mesure de contraste — c'est la porte `run.sh panier`
 * qui mesure le rendu. Il garde ce qu'un audit de rendu ne peut pas voir :
 * la RAISON du défaut, qui se recopie d'un copier-coller.
 *
 *   R1 · AUCUNE CLASSE DE THÈME sur un élément actionnable du panier. On
 *        cherche `class="… button …"` (et les classes `wp-element-button` /
 *        `wc-block-*`) dans les deux fichiers qui impriment du markup de
 *        panier. Un ajout fait rougir : un humain doit revenir ici.
 *   R2 · LES TOKENS NE DÉRIVENT PAS. `aod-cart.css` recopie les couleurs du
 *        formulaire (`--sh-*` → `--ac-*`) parce qu'il se charge sur toute la
 *        boutique là où la feuille du formulaire ne se charge que sur la fiche
 *        produit. La duplication est assumée ; sa DÉRIVE ne l'est pas.
 *   R3 · AUCUN FOND ACTIONNABLE `transparent`. Un fond transparent fait
 *        dépendre le contraste de ce qu'il y a derrière, donc du thème du
 *        clone : mesuré correct sur la carte blanche du formulaire, illisible
 *        ailleurs. Les boutons du panier déclarent un fond explicite.
 *   R4 · LES ÉTATS SONT DÉCLARÉS. Le thème porte un `button:focus{background}`
 *        en (0,1,1), qui bat une règle de composant en (0,1,0) : à l'ouverture
 *        du panneau, le bouton de fermeture ressortait au bleu du thème. Tout
 *        bouton du panier qui déclare un `:hover` doit déclarer `:focus` avec
 *        lui — sinon l'état focus retombe au thème.
 *   R5 · TÉMOIN. Les motifs cherchés doivent exister quelque part, sinon les
 *        quatre règles ci-dessus seraient vertes sur un fichier vide ou
 *        renommé. Un cliquet qu'on n'a jamais vu échouer ne prouve rien.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Panier 37 — la peau du panier' );

$aod_pk_root  = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : '';
$aod_pk_files = array(
	'cart_css'  => $aod_pk_root . 'assets/css/aod-cart.css',
	'form_css'  => $aod_pk_root . 'assets/css/aod-cod-form.css',
	'cart_php'  => $aod_pk_root . 'includes/class-aod-cod-cart.php',
	'form_php'  => $aod_pk_root . 'includes/class-aod-cod-form.php',
	'cart_js'   => $aod_pk_root . 'assets/js/aod-cart.js',
	'pages_php' => $aod_pk_root . 'includes/class-aod-cod-pages.php',
);

$aod_pk_src = array();
foreach ( $aod_pk_files as $aod_pk_key => $aod_pk_path ) {
	$aod_pk_src[ $aod_pk_key ] = is_readable( $aod_pk_path ) ? (string) file_get_contents( $aod_pk_path ) : '';
	AOD_Test::ok(
		'' !== $aod_pk_src[ $aod_pk_key ],
		sprintf( 'Fichier lisible : %s', basename( $aod_pk_path ) ),
		'sans le fichier, toutes les règles de ce cliquet seraient vertes sans rien lire'
	);
}

/* ------------------------------------------------------------------ R1 ---- */

AOD_Test::group( 'R1 — aucune classe de thème sur le markup du panier' );

// Les classes qui donnent prise au thème. `button` est celle qui a fait le
// dégât ; les deux autres sont les équivalents Gutenberg/Woo, même mécanique.
$aod_pk_theme_classes = array( 'button', 'wp-element-button', 'wc-block-components-button' );

/**
 * Retire les lignes de COMMENTAIRE avant de chercher du markup.
 *
 * Sans ça, ce cliquet rougit sur les commentaires qui EXPLIQUENT le défaut —
 * c'est arrivé au premier run, sur la ligne de `class-aod-cod-cart.php` qui
 * dit précisément de ne pas écrire cette classe. On ne va pas interdire de
 * documenter le piège dans le fichier où il se produit.
 */
$aod_pk_strip = function ( $code ) {
	$out = array();
	foreach ( explode( "\n", $code ) as $line ) {
		$trim = ltrim( $line );
		if ( 0 === strpos( $trim, '//' ) || 0 === strpos( $trim, '*' ) || 0 === strpos( $trim, '/*' ) || 0 === strpos( $trim, '#' ) ) {
			continue;
		}
		$out[] = $line;
	}
	return implode( "\n", $out );
};

// Markup de panier : la coque PHP, le bouton d'ajout du formulaire, et le
// markup produit par le JS (lignes du panneau et du bloc).
$aod_pk_markup = array(
	'class-aod-cod-cart.php' => $aod_pk_strip( $aod_pk_src['cart_php'] ),
	'aod-cart.js'            => $aod_pk_strip( $aod_pk_src['cart_js'] ),
);

foreach ( $aod_pk_markup as $aod_pk_name => $aod_pk_code ) {
	foreach ( $aod_pk_theme_classes as $aod_pk_cls ) {
		// `class="… <classe> …"` — on ne veut pas attraper `aod-cart__button`
		// ni un mot dans un commentaire : on exige la frontière de mot dans un
		// attribut class.
		$aod_pk_hit = (bool) preg_match(
			'/class\s*=\s*["\'][^"\']*\b' . preg_quote( $aod_pk_cls, '/' ) . '\b[^"\']*["\']/',
			$aod_pk_code
		);
		AOD_Test::ok(
			! $aod_pk_hit,
			sprintf( '%s n\'imprime aucun `class="… %s …"`', $aod_pk_name, $aod_pk_cls ),
			'une classe du thème sur notre bouton = le thème décide de son fond, et le contraste avec'
		);
	}
}

// Le bouton d'ajout du formulaire : c'est LUI qui était à 1,05:1.
AOD_Test::ok(
	(bool) preg_match( '/class="aod-cod__addcart"/', $aod_pk_src['form_php'] ),
	'`aod-cod__addcart` porte sa classe SEULE dans class-aod-cod-form.php',
	'c\'est le bouton mesuré à 1,05:1 ; toute classe de thème à côté rouvre le défaut'
);

/* ------------------------------------------------------------------ R2 ---- */

AOD_Test::group( 'R2 — les tokens du panier ne dérivent pas de ceux du formulaire' );

/**
 * Lit `--nom: valeur;` dans une feuille et rend un tableau nom => valeur.
 */
$aod_pk_vars = function ( $css, $prefix ) {
	$out = array();
	if ( preg_match_all( '/--' . preg_quote( $prefix, '/' ) . '-([a-z0-9-]+)\s*:\s*([^;]+);/i', $css, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $one ) {
			$out[ strtolower( $one[1] ) ] = strtolower( trim( $one[2] ) );
		}
	}
	return $out;
};

$aod_pk_sh = $aod_pk_vars( $aod_pk_src['form_css'], 'sh' );
$aod_pk_ac = $aod_pk_vars( $aod_pk_src['cart_css'], 'ac' );

AOD_Test::ok( count( $aod_pk_sh ) >= 8, sprintf( 'Le formulaire déclare ses tokens --sh-* (%d lus)', count( $aod_pk_sh ) ), 'témoin : zéro token lu rendrait la comparaison vide, donc verte' );
AOD_Test::ok( count( $aod_pk_ac ) >= 8, sprintf( 'Le panier déclare ses tokens --ac-* (%d lus)', count( $aod_pk_ac ) ), 'même témoin, côté panier' );

// Correspondances qui DOIVENT rester identiques : ce sont les couleurs que
// l'acheteur voit côte à côte sur la fiche produit (bloc panier + formulaire).
$aod_pk_pairs = array(
	'ink'         => 'text',
	'ink-2'       => 'text-2',
	'muted'       => 'muted',
	'accent'      => 'accent',
	'accent-dark' => 'accent-dark',
	'danger'      => 'error',
	'surface-sub' => 'bg-sub',
	// Ajouté en 38.2 : le fond pâle des états choisis. Il existait côté panier
	// (`--ac-accent-wash`) sans jumeau côté formulaire, et les pastilles de
	// variantes le recopiaient en dur. Une couleur sans paire, c'est précisément
	// la dérive que cette règle garde.
	'accent-wash' => 'accent-wash',
);

foreach ( $aod_pk_pairs as $aod_pk_ac_key => $aod_pk_sh_key ) {
	if ( ! isset( $aod_pk_ac[ $aod_pk_ac_key ], $aod_pk_sh[ $aod_pk_sh_key ] ) ) {
		AOD_Test::ok( false, sprintf( 'Token appairé absent : --ac-%s / --sh-%s', $aod_pk_ac_key, $aod_pk_sh_key ), 'un token renommé d\'un côté seulement est exactement la dérive que cette règle garde' );
		continue;
	}
	AOD_Test::eq(
		$aod_pk_sh[ $aod_pk_sh_key ],
		$aod_pk_ac[ $aod_pk_ac_key ],
		sprintf( '--ac-%s == --sh-%s', $aod_pk_ac_key, $aod_pk_sh_key )
	);
}

/* ------------------------------------------------------------------ R3 ---- */

AOD_Test::group( 'R3 — aucun fond actionnable transparent' );

// Blocs de règles des boutons du panier : on isole chaque déclaration de
// `background`/`background-color` posée sur un sélecteur de bouton.
$aod_pk_btn_selectors = array( '.aod-cart__order', '.aod-cart__quickadd', '.aod-cod__addcart' );

foreach ( $aod_pk_btn_selectors as $aod_pk_sel ) {
	$aod_pk_sheet = ( '.aod-cod__addcart' === $aod_pk_sel ) ? $aod_pk_src['form_css'] : $aod_pk_src['cart_css'];
	// Le bloc de base : le sélecteur seul, suivi de { … }.
	$aod_pk_block = '';
	if ( preg_match( '/' . preg_quote( $aod_pk_sel, '/' ) . '\s*\{([^}]*)\}/', $aod_pk_sheet, $m ) ) {
		$aod_pk_block = $m[1];
	}
	AOD_Test::ok(
		'' !== $aod_pk_block,
		sprintf( 'Bloc de règles trouvé pour %s', $aod_pk_sel ),
		'témoin : sans bloc lu, la vérification de fond ci-dessous serait vide donc verte'
	);
	if ( '' === $aod_pk_block ) {
		continue;
	}
	$aod_pk_has_bg = (bool) preg_match( '/background(?:-color)?\s*:\s*([^;]+);/', $aod_pk_block, $bg );
	AOD_Test::ok(
		$aod_pk_has_bg,
		sprintf( '%s déclare un fond explicite', $aod_pk_sel ),
		'sans fond déclaré, le bouton prend celui du thème — c\'est l\'origine du 1,05:1'
	);
	if ( $aod_pk_has_bg ) {
		$aod_pk_val = strtolower( trim( $bg[1] ) );
		AOD_Test::ok(
			false === strpos( $aod_pk_val, 'transparent' ) && 'none' !== $aod_pk_val,
			sprintf( '%s : le fond n\'est ni `transparent` ni `none` (%s)', $aod_pk_sel, $aod_pk_val ),
			'un fond transparent fait dépendre le contraste de ce qu\'il y a derrière, donc du thème du clone'
		);
	}
}

/* ------------------------------------------------------------------ R4 ---- */

AOD_Test::group( 'R4 — tout :hover d\'un bouton du panier déclare aussi :focus' );

// Le thème pose `button:focus{background}` en (0,1,1). Une règle de composant
// en (0,1,0) perd cet état — et seulement cet état, ce qui le rend invisible
// tant qu'on ne met pas le focus sur le bouton.
$aod_pk_stateful = array( '.aod-cart__close', '.aod-cart__order', '.aod-cart__remove' );

foreach ( $aod_pk_stateful as $aod_pk_sel ) {
	$aod_pk_has_hover = (bool) preg_match( '/' . preg_quote( $aod_pk_sel, '/' ) . ':hover\b/', $aod_pk_src['cart_css'] );
	$aod_pk_has_focus = (bool) preg_match( '/' . preg_quote( $aod_pk_sel, '/' ) . ':focus\b(?!-visible)/', $aod_pk_src['cart_css'] );
	AOD_Test::ok(
		$aod_pk_has_hover,
		sprintf( '%s déclare un état :hover', $aod_pk_sel ),
		'témoin : sans :hover lu, la règle « :hover implique :focus » serait vide donc verte'
	);
	AOD_Test::ok(
		! $aod_pk_has_hover || $aod_pk_has_focus,
		sprintf( '%s déclare :focus à côté de :hover', $aod_pk_sel ),
		'`button:focus` du thème est en (0,1,1) : sans notre :focus, l\'état focus retombe au thème'
	);
}

/* ------------------------------------------------------------------ R5 ---- */

AOD_Test::group( 'R5 — témoins : les motifs cherchés existent' );

AOD_Test::ok(
	(bool) preg_match( '/class\s*=\s*["\'][^"\']*\bbutton\b/', $aod_pk_src['form_css'] . 'class="button"' ),
	'Le motif « classe de thème » sait reconnaître un cas positif',
	'sans ce témoin, une regex cassée rendrait R1 universellement verte'
);

AOD_Test::ok(
	isset( $aod_pk_ac['accent'] ) && '#008060' === $aod_pk_ac['accent'],
	'Le vert d\'action du panier est bien lu (#008060)',
	'ancre la lecture des tokens sur une valeur connue : une lecture vide ne peut pas passer pour un accord'
);

/* ------------------------------------------------------------------ R6 ---- */

AOD_Test::group( 'R6 — les icônes copiées ne dérivent pas de celles de /gestion' );

/*
 * Le panier public rend ses icônes par `AOD_COD_Icon`, jumeau de `AOD_CD_Icon`,
 * avec les MÊMES fichiers Phosphor copiés dans `aod-cod-form`.
 *
 * 🚨 Pourquoi une copie plutôt qu'un appel : la dépendance entre les deux
 * plugins ne va que dans UN sens (`aod-client-dashboard` → `aod-cod-form`).
 * Appeler la classe du dashboard depuis le public ferait tomber la boutique en
 * fatale dès qu'un gérant désactive `/gestion` — le mode dégradé fermé par la
 * Phase 32.1, repris à l'envers.
 *
 * La duplication est donc un choix. Sa DÉRIVE n'en serait pas un : deux jeux
 * d'icônes qui divergent, c'est `/gestion` et la boutique qui ne parlent plus
 * la même langue visuelle, sans que rien ne le signale.
 */
$aod_pk_dash_icons = defined( 'AOD_CD_PATH' )
	? AOD_CD_PATH . 'assets/icons/'
	: dirname( $aod_pk_root ) . '/aod-client-dashboard/assets/icons/';
$aod_pk_cod_icons  = $aod_pk_root . 'assets/icons/';

// Deux `glob()` plutôt qu'un motif `{a,b}` : `GLOB_BRACE` n'existe pas dans
// toutes les compilations de PHP (absent de l'image WP-CLI de ce dépôt, qui est
// sur musl) — la constante indéfinie faisait tomber TOUT le fichier de test en
// erreur fatale. Trouvé au premier run, pas à la relecture.
$aod_pk_copies = array();
foreach ( array( 'regular', 'fill' ) as $aod_pk_poids_dir ) {
	$aod_pk_found  = glob( $aod_pk_cod_icons . $aod_pk_poids_dir . '/*.svg' );
	$aod_pk_copies = array_merge( $aod_pk_copies, is_array( $aod_pk_found ) ? $aod_pk_found : array() );
}

AOD_Test::ok(
	count( $aod_pk_copies ) >= 3,
	sprintf( 'Le panier embarque ses icônes (%d fichiers lus)', count( $aod_pk_copies ) ),
	'témoin : zéro fichier lu rendrait la comparaison vide, donc verte — le cas exact que R6 doit refuser'
);

foreach ( $aod_pk_copies as $aod_pk_copie ) {
	$aod_pk_rel  = basename( dirname( $aod_pk_copie ) ) . '/' . basename( $aod_pk_copie );
	$aod_pk_orig = $aod_pk_dash_icons . $aod_pk_rel;

	if ( ! is_readable( $aod_pk_orig ) ) {
		AOD_Test::ok(
			false,
			sprintf( 'L\'originale de %s existe dans /gestion', $aod_pk_rel ),
			'une icône publique sans originale : soit elle a été inventée, soit /gestion a perdu la sienne — dans les deux cas un humain doit trancher'
		);
		continue;
	}

	AOD_Test::eq(
		md5_file( $aod_pk_orig ),
		md5_file( $aod_pk_copie ),
		sprintf( '%s est identique à celle de /gestion (octet à octet)', $aod_pk_rel )
	);
}

// Le catalogue est FERMÉ : chaque nom d'usage doit avoir son fichier.
if ( class_exists( 'AOD_COD_Icon' ) ) {
	foreach ( array( 'cart' => 'fill', 'close' => 'regular' ) as $aod_pk_nom => $aod_pk_poids ) {
		$aod_pk_out = AOD_COD_Icon::svg( $aod_pk_nom, array( 'weight' => $aod_pk_poids ) );
		AOD_Test::ok(
			'' !== $aod_pk_out && false !== strpos( $aod_pk_out, '<path' ),
			sprintf( 'AOD_COD_Icon::svg( \'%s\' ) rend un tracé', $aod_pk_nom ),
			'une icône vide ne casse rien et ne se voit pas : le bouton garde sa taille et perd son sens'
		);
		AOD_Test::ok(
			false !== strpos( $aod_pk_out, 'aria-hidden="true"' ),
			sprintf( '%s est décorative (aria-hidden)', $aod_pk_nom ),
			'les boutons portent déjà un aria-label ; une icône annoncée en plus est du bruit au lecteur d\'écran'
		);
	}

	// ⚠️ Le comportement HORS catalogue (rend '' + `_doing_it_wrong()`) n'est pas
	// éprouvé ici : `_doing_it_wrong()` lève sous `WP_DEBUG`, et un test qui
	// attraperait l'exception éprouverait le harnais plus que la garde. Limite
	// assumée, écrite plutôt qu'une assertion toujours verte.
}

/* ------------------------------------------------------------------ R7 ---- */

AOD_Test::group( 'R7 — s\'habiller soi-même OU déléguer au thème, jamais les deux' );

/*
 * LA RÈGLE, arbitrée en 37.3 : un composant public qui déclare son propre fond
 * ne doit PAS porter en plus `class="button"`. Les deux ensemble, c'est le
 * défaut fondateur de la Phase 37 — le thème gagne, la déclaration devient un
 * mensonge, et le contraste dépend d'une valeur qu'on ne contrôle pas.
 *
 * ⚠️ CE N'EST PAS « jamais de classe de thème ». Certains composants DÉLÈGUENT
 * leur apparence au thème **à dessein** — les CTA des pages de vente sont du
 * mobilier de page, ils doivent avoir l'air natifs chez chaque client, et leur
 * CSS ne déclare volontairement aucun fond (la raison est écrite dans
 * `aod-cod-pages.css`). Leur retirer la classe les laisserait NUS. Ils sont
 * donc déclarés ici, avec leur raison : une délégation sans raison écrite est
 * un échec de revue, pas une tolérance.
 *
 * ⚠️ `aod-home__btn` EST SORTI DE CETTE LISTE le 2026-09-08, demande owner
 * explicite (vert plein + arrondi, quel que soit le thème du clone) — la
 * délégation au thème n'est plus la règle pour LUI. La classe `button` a été
 * retirée de son markup dans `class-aod-cod-pages.php`, et `aod-cod-pages.css`
 * lui déclare désormais un fond (en jetons, cf. son en-tête). Il rejoint donc
 * `$aod_pk_autonomes` ci-dessous, comme n'importe quel composant qui s'habille
 * lui-même. Les deux CTA de la page de vente, eux, n'ont pas reçu cette
 * demande : ils restent délégués.
 */
$aod_pk_delegue = array(
	'aod-landing__cta'        => 'CTA d\'une page de vente : composition du gérant, apparence héritée du thème du clone.',
	'aod-landing__sticky-cta' => 'Idem, barre « Commander » mobile de la page de vente.',
);

// Composants qui s'habillent EUX-MÊMES : ils déclarent un fond, donc la classe
// du thème leur est interdite.
$aod_pk_autonomes = array(
	'aod-cod__submit'  => $aod_pk_src['form_php'],
	'aod-cod__addcart' => $aod_pk_src['form_php'],
	'aod-cart__order'  => $aod_pk_src['cart_php'],
	'aod-home__btn'    => $aod_pk_src['pages_php'],
);

$aod_pk_contact = is_readable( $aod_pk_root . 'includes/class-aod-cod-contact.php' )
	? (string) file_get_contents( $aod_pk_root . 'includes/class-aod-cod-contact.php' )
	: '';
AOD_Test::ok(
	'' !== $aod_pk_contact,
	'Le formulaire de contact est lisible',
	'témoin : il porte le second `aod-cod__submit` du dépôt — sans lui, R7 n\'en vérifierait qu\'un'
);
$aod_pk_autonomes_src = $aod_pk_autonomes;
$aod_pk_autonomes_src['aod-cod__submit (contact)'] = $aod_pk_contact;

foreach ( $aod_pk_autonomes_src as $aod_pk_comp => $aod_pk_code ) {
	$aod_pk_cls = preg_replace( '/\s*\(.+\)$/', '', $aod_pk_comp );
	// Le composant apparaît-il bien dans ce fichier ? (témoin : sinon l'absence
	// de classe de thème serait vraie par vacuité)
	$aod_pk_present = (bool) preg_match( '/class="[^"]*\b' . preg_quote( $aod_pk_cls, '/' ) . '\b/', $aod_pk_code );
	AOD_Test::ok(
		$aod_pk_present,
		sprintf( 'ANCRAGE : %s est bien rendu par ce fichier', $aod_pk_comp ),
		'une assertion d\'absence doit s\'adosser à une présence mesurée dans le même état'
	);
	if ( ! $aod_pk_present ) {
		continue;
	}
	// …et sans `button` dans le MÊME attribut class.
	$aod_pk_avec_theme = (bool) preg_match(
		'/class="[^"]*\b' . preg_quote( $aod_pk_cls, '/' ) . '\b[^"]*\bbutton\b[^"]*"|class="[^"]*\bbutton\b[^"]*\b' . preg_quote( $aod_pk_cls, '/' ) . '\b[^"]*"/',
		$aod_pk_code
	);
	AOD_Test::ok(
		! $aod_pk_avec_theme,
		sprintf( '%s ne porte PAS la classe `button` du thème', $aod_pk_comp ),
		'il déclare son propre fond : porter en plus la classe du thème, c\'est laisser le thème gagner et le contraste devenir imprévisible'
	);
}

// Les délégués, eux, DOIVENT continuer de la porter — sinon ils sont nus.
foreach ( $aod_pk_delegue as $aod_pk_cls => $aod_pk_raison ) {
	$aod_pk_trouve = false;
	foreach ( array( 'pages', 'landing' ) as $aod_pk_f ) {
		$aod_pk_p = $aod_pk_root . 'includes/class-aod-cod-' . $aod_pk_f . '.php';
		if ( is_readable( $aod_pk_p ) && preg_match( '/class="[^"]*\b' . preg_quote( $aod_pk_cls, '/' ) . '\b[^"]*\bbutton\b|class="[^"]*\bbutton\b[^"]*\b' . preg_quote( $aod_pk_cls, '/' ) . '\b/', (string) file_get_contents( $aod_pk_p ) ) ) {
			$aod_pk_trouve = true;
			break;
		}
	}
	AOD_Test::ok(
		$aod_pk_trouve,
		sprintf( '%s délègue toujours au thème (raison déclarée)', $aod_pk_cls ),
		'le lui retirer le laisserait sans aucun habillage : ' . $aod_pk_raison
	);
}
