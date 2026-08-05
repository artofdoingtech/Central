<?php
/**
 * La charte de `/gestion` ne peut pas se défaire en silence (Phase 17).
 *
 * Trois contrôles STATIQUES sur `dashboard.css`. Ils ne mesurent pas un rendu —
 * `test-contraste.php` et la porte `contraste` s'en chargent — ils vérifient que
 * la feuille reste GOUVERNÉE : pas de couleur de client en dur, pas de variable
 * inventée, pas de repli qui ment.
 *
 * Pourquoi statique et pas au navigateur : les trois défauts que cette phase a
 * corrigés étaient tous INVISIBLES à un audit de rendu. Une variable jamais
 * déclarée n'affiche pas d'erreur, elle sert son repli. Un survol de ligne masqué
 * par le zébrage ne se voit que si on pense à survoler une ligne PAIRE. Une
 * couleur de client en dur s'affiche parfaitement — elle est juste fausse chez le
 * client suivant. Aucune de ces trois choses ne casse un écran ; toutes trois se
 * lisent dans le fichier.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chroma d'un hex : écart entre le canal le plus fort et le plus faible.
 *
 * 0 = gris parfait. C'est le critère qui sépare une SURFACE NEUTRE (légitime en
 * dur : blanc, gris de séparation) d'une COULEUR (qui doit venir d'un token).
 * Un invariant, pas un seuil de goût.
 *
 * ⚠️ Préfixe `aod_test_surf_` : `tests/all.php` charge tous les fichiers par
 * `glob()` dans le même espace global — un helper au nom générique provoque un
 * `Cannot redeclare`, ce qui est déjà arrivé (cf. test-charte-sidebar.php).
 *
 * @param string $hex Couleur, avec ou sans `#`, en 3 ou 6 chiffres.
 * @return int 0..255
 */
function aod_test_surf_chroma( $hex ) {
	$hex = strtolower( ltrim( (string) $hex, '#' ) );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	return max( $r, $g, $b ) - min( $r, $g, $b );
}

/**
 * Normalise un hex en 6 chiffres minuscules.
 *
 * @param string $hex Couleur.
 * @return string
 */
function aod_test_surf_norm( $hex ) {
	$hex = strtolower( ltrim( (string) $hex, '#' ) );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return $hex;
}

/**
 * Corps de la feuille : sans le bloc `:root` et sans les commentaires.
 *
 * `:root` est le SEUL endroit où une couleur littérale est à sa place — c'est la
 * définition des tokens. Les commentaires doivent partir aussi : cette phase en a
 * écrit plusieurs qui CITENT les couleurs supprimées (« son ancien #f4f4f1… »).
 * Sans ce nettoyage, le test se ferait piéger par sa propre documentation.
 *
 * @param string $css Feuille complète.
 * @return string
 */
function aod_test_surf_body( $css ) {
	return preg_replace( '/:root\s*\{.*?\}/s', '', aod_test_surf_decom( $css ), 1 );
}

/**
 * La feuille sans ses commentaires, `:root` COMPRIS.
 *
 * 🚨 Séparée de `aod_test_surf_body()` pour une raison mesurée : le contrôle des
 * variables fantômes a besoin de `:root` (c'est là que tout est déclaré) mais
 * doit se passer des commentaires — or il lisait le fichier BRUT. Un commentaire
 * de `dashboard.css` écrit `--cd-accent:` en prose ; il comptait donc comme une
 * déclaration. Aujourd'hui c'est inoffensif — la variable existe pour de vrai —
 * mais le cliquet aurait tenu pour déclarée n'importe quelle variable citée dans
 * un commentaire, y compris une qui n'existe nulle part. C'est le mode d'échec
 * que le docblock ci-dessus nomme pour les couleurs, resté ouvert pour les
 * variables : le test se fait cautionner par sa propre documentation.
 *
 * @param string $css Feuille complète.
 * @return string
 */
function aod_test_surf_decom( $css ) {
	return (string) preg_replace( '#/\*.*?\*/#s', '', (string) $css );
}

$aod_surf_css   = (string) file_get_contents( AOD_CD_PATH . 'assets/css/dashboard.css' );
$aod_surf_body  = aod_test_surf_body( $aod_surf_css );
$aod_surf_decom = aod_test_surf_decom( $aod_surf_css );

AOD_Test::group( 'Charte — aucune couleur de CLIENT en dur dans la feuille' );

/*
 * La liste blanche ci-dessous est de la DETTE DÉCLARÉE, pas une dispense.
 *
 * Ces couleurs sont SÉMANTIQUES — elles disent « succès », « en attente »,
 * « échec », ou identifient une tonalité de carte. Aucune ne dérive de l'accent
 * du client, donc aucune ne devient fausse quand le client change de couleur :
 * c'est ce qui les distingue des jaunes supprimés en 17.3 (#fdf6d8, #fffdf0,
 * #fcfcf6), qui étaient, eux, l'accent PAR DÉFAUT du template figé dans le CSS.
 *
 * Elles restent néanmoins hors système : elles devraient être des tokens
 * sémantiques. Cette liste a vocation à RÉTRÉCIR phase après phase — son
 * intérêt est qu'on ne puisse plus en ajouter sans le décider.
 */
$aod_surf_permis = array(

	// Tonalités des pastilles d'icône (.t-blue/-indigo/-teal/-green/-amber/-red/
	// -slate sur .aod-cd-card-ico, .aod-cd-kpi__ic, .aod-cd-pxcard__ic). Couples
	// fond + glyphe. ⚠️ Les mêmes noms de tonalité portent des valeurs
	// DIFFÉRENTES selon le composant (#ecebff vs #e7e9fd, #d9f6f3 vs #d9f2f6) :
	// deux palettes divergentes qu'une phase ultérieure doit fusionner.
	'e6f0ff', '1d4ed8', 'ecebff', '4338ca', 'd9f6f3', '0e7490', 'e1f7e8',
	'fff2cc', 'fde7e5', 'eef1f5', '475569', 'e7e9fd', 'd9f2f6',

	// Pastilles de statut de commande (--dot) : une couleur par statut
	// WooCommerce. Le sens est porté par la couleur, pas par la marque.
	'f59e0b', '3b82f6', '16a34a', '9ca3af', '15803d', 'ef4444', 'a855f7', 'dc2626',

	// Bordures des pastilles et cartes d'alerte sémantiques. Les fonds ont leur
	// token (--cd-ok/warn/bad/info-soft), les bordures n'en ont jamais eu.
	'f2d9a8', 'bfe6c9', 'b3e0e8', 'f2c4c0', 'f3c9c5',

	// Encres d'état (messages « Tester la connexion », alertes) : redites de
	// --cd-ok/--cd-warn/--cd-bad, à rabattre dessus un jour.
	'b45309', 'b91c1c', 'd97706',

	// Danger / suppression : rouges d'action destructive.
	'c0392b', 'b21f14', 'fdecec', 'fff7f6',

	// Déconnexion dans la sidebar sombre : rouges calibrés SUR CE FOND-LÀ, donc
	// non substituables par --cd-bad (qui est calibré sur fond clair).
	'f3a0a0', 'ff7b7b',

	// Gris ardoise de la frise de suivi.
	'64748b',

	// Couleurs de MARQUE des régies de « Pixels & Tracking » (Phase 41,
	// .aod-cd-pxcard--meta/-tiktok/-snapchat/-google). À la DIFFÉRENCE de tout ce
	// qui précède, ce ne sont ni des tonalités sémantiques ni de la dette : ce sont
	// les couleurs UNIVERSELLES de marques tierces (Meta est toujours bleu, Snapchat
	// toujours jaune), identiques sur tous les clones et SANS lien avec l'accent du
	// client — donc légitimement en dur (cf. A.18 / ICONES §6.4). Elles n'ont pas
	// vocation à rétrécir. Chaque marque va par 2 : la couleur pleine + sa teinte
	// d'en-tête. L'ENCRE posée dessus (best_ink) est achromatique, donc non listée ;
	// sa justesse AA est gardée à part par test-contraste.php. TikTok #000000 et sa
	// teinte #ededed sont neutres → jamais signalées ici.
	'1877f2', 'e9f1fe', // Meta — bleu + en-tête.
	'fffc00', 'fffde0', // Snapchat — jaune + en-tête.
	'ea4335', 'fdeceb', // Google — rouge + en-tête.
);

$aod_surf_vus     = array();
$aod_surf_fautifs = array();
if ( preg_match_all( '/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', $aod_surf_body, $aod_surf_m ) ) {
	foreach ( $aod_surf_m[0] as $aod_surf_h ) {
		$aod_surf_n            = aod_test_surf_norm( $aod_surf_h );
		$aod_surf_vus[]        = $aod_surf_n;
		if ( aod_test_surf_chroma( $aod_surf_n ) <= 2 ) {
			continue; // Neutre : blanc, gris. Pas une couleur de marque.
		}
		if ( in_array( $aod_surf_n, $aod_surf_permis, true ) ) {
			continue;
		}
		$aod_surf_fautifs[ $aod_surf_n ] = true;
	}
}
$aod_surf_fautifs = array_keys( $aod_surf_fautifs );

AOD_Test::ok(
	empty( $aod_surf_fautifs ),
	sprintf(
		'Aucune couleur chromatique hors token ni hors liste déclarée — %d littéraux inspectés, %d distincts, %d tolérés par la liste',
		count( $aod_surf_vus ),
		count( array_unique( $aod_surf_vus ) ),
		count( $aod_surf_permis )
	),
	'couleur(s) non déclarée(s) : #' . implode( ' · #', array_slice( $aod_surf_fautifs, 0, 8 ) )
);

/*
 * Mise au rouge du matcher, sans toucher au moindre fichier (DoD §C.2 pt 5).
 * On lui soumet la forme EXACTE qui a été retirée en 17.3 : si le jaune de
 * l'accent par défaut repassait, il doit le dire.
 */
$aod_surf_fixture = '.x{background:#fdf6d8}.y{background:#fffdf0}';
$aod_surf_pris    = 0;
if ( preg_match_all( '/#[0-9a-fA-F]{6}\b/', aod_test_surf_body( $aod_surf_fixture ), $aod_surf_fm ) ) {
	foreach ( $aod_surf_fm[0] as $aod_surf_fh ) {
		$aod_surf_fn = aod_test_surf_norm( $aod_surf_fh );
		if ( aod_test_surf_chroma( $aod_surf_fn ) > 2 && ! in_array( $aod_surf_fn, $aod_surf_permis, true ) ) {
			++$aod_surf_pris;
		}
	}
}
AOD_Test::eq( 2, $aod_surf_pris, 'Le scan attrape bien les jaunes de l\'accent par défaut s\'ils revenaient (témoin)' );

// Et il ne doit pas crier sur une surface neutre, sinon la liste blanche
// gonflerait jusqu'à ne plus rien vouloir dire.
$aod_surf_neutre = 0;
foreach ( array( '#ffffff', '#fafafa', '#f4f4f4', '#fff' ) as $aod_surf_nh ) {
	if ( aod_test_surf_chroma( $aod_surf_nh ) > 2 ) {
		++$aod_surf_neutre;
	}
}
AOD_Test::eq( 0, $aod_surf_neutre, 'Une surface neutre (blanc, gris) n\'est pas traitée comme une couleur de marque' );

AOD_Test::group( 'Charte — toute variable utilisée est une variable déclarée' );

/*
 * 🚨 Le contrôle né de l'étape 17.4. `--cd-ink-3`, `--cd-bg-2` et `--cd-green`
 * étaient appelées sans avoir jamais été déclarées : ce qui s'affichait était
 * leur repli, et `--cd-ink-3` rendait #98a2b3, soit 2,58:1 — très en dessous
 * d'AA, en corps 12.
 *
 * C'est le mode d'échec le plus sournois du CSS : une variable inexistante ne
 * produit AUCUNE erreur, ni console, ni rendu cassé. Le repli prend le relais et
 * le contrat de contraste tombe sans un bruit. Seule une lecture du fichier peut
 * le voir.
 */
$aod_surf_decl = array();
if ( preg_match_all( '/(--cd-[a-z0-9-]+)\s*:/', $aod_surf_decom, $aod_surf_dm ) ) {
	$aod_surf_decl = array_unique( $aod_surf_dm[1] );
}
$aod_surf_used   = array();
$aod_surf_orphes = array();
if ( preg_match_all( '/var\(\s*(--cd-[a-z0-9-]+)/', $aod_surf_decom, $aod_surf_um ) ) {
	foreach ( $aod_surf_um[1] as $aod_surf_v ) {
		$aod_surf_used[] = $aod_surf_v;
		if ( ! in_array( $aod_surf_v, $aod_surf_decl, true ) ) {
			$aod_surf_orphes[ $aod_surf_v ] = true;
		}
	}
}
AOD_Test::ok(
	empty( $aod_surf_orphes ),
	sprintf(
		'Aucune variable fantôme — %d déclarations, %d usages, %d variables distinctes utilisées',
		count( $aod_surf_decl ),
		count( $aod_surf_used ),
		count( array_unique( $aod_surf_used ) )
	),
	'jamais déclarée(s) : ' . implode( ' · ', array_keys( $aod_surf_orphes ) )
);

/*
 * ═════════════════════════════════════════════════════════════════════════
 * R4 (46.4) — ET LE SENS INVERSE : toute variable DÉCLARÉE est utilisée.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * 🚨 CE CLIQUET N'A REGARDÉ QUE DANS UN SENS PENDANT DEUX PHASES. Il vérifiait
 * usage → déclaration (les fantômes de 17.4, à raison) et JAMAIS
 * déclaration → usage. Mesuré au verdict A.22 : cinq tokens vivaient déclarés
 * sans qu'aucun `var()` ne les lise — `--cd-sp-1`, `--cd-sp-6`, `--cd-sp-8`,
 * `--cd-sp-10` et `--cd-r-lg`.
 *
 * Un token orphelin n'est pas qu'un octet mort. C'est une échelle qui a l'air
 * appliquée et ne l'est pas : le suivant qui a besoin de 24 px lit `--cd-sp-6`
 * dans `:root`, en déduit que la maison range l'espacement par tokens, et écrit
 * pourtant `24px` en dur parce que tout son voisinage le fait. L'échelle décorait
 * le fichier au lieu de le gouverner — et c'est très exactement ce qui est arrivé
 * à la Phase 5, dont le problème n°1 (10 tailles de police en dur) était monté à
 * 20 après la phase censée le régler.
 *
 * ⚠️ POURQUOI CE SENS-LÀ MANQUAIT, alors que le dépôt applique déjà la règle
 * ailleurs — et l'écrit : `test-icones.php` exige les DEUX sens, « catalogue →
 * fichier ET fichier → catalogue (aucun SVG orphelin, sinon le dossier grossit en
 * silence à chaque étape) ». Le raisonnement était bon, il n'avait simplement
 * jamais été porté au CSS. Un garde-fou hérite des angles morts de la phase qui
 * l'a écrit ; c'est en le relisant depuis un autre objet qu'on les voit.
 *
 * 🔒 L'assertion est une ÉGALITÉ de liste, pas un seuil. Un seuil (« au plus 4
 * orphelins ») se contournerait par accident : on en retirerait un et on en
 * ajouterait un autre sans que rien ne bouge. L'égalité impose que TOUTE
 * évolution — dans un sens comme dans l'autre — passe par une mise à jour
 * explicite de cette liste, donc par une décision.
 */
$aod_surf_orphelins = array();
foreach ( $aod_surf_decl as $aod_surf_d ) {
	if ( ! in_array( $aod_surf_d, $aod_surf_used, true ) ) {
		$aod_surf_orphelins[] = $aod_surf_d;
	}
}
sort( $aod_surf_orphelins );

/*
 * 🚨 PREMIÈRE CATÉGORIE LÉGITIME, ET ELLE N'EST PAS UNE LISTE EN DUR : les tokens
 * que `css_vars()` INJECTE.
 *
 * Le bloc `:root` de `dashboard.css` se décrit lui-même comme « un REPLI si
 * l'injection est absente ». Tout token produit par `css_vars()` doit donc y
 * figurer — le dépôt garde même l'oubli symétrique (« ajouter un token dans
 * css_vars() sans lui donner de repli »). Qu'une RÈGLE le consomme est une
 * question séparée.
 *
 * On interroge donc `css_vars()` lui-même au lieu de recopier ses noms : c'est le
 * point 4 de la règle du témoin (« passer par le code de production »), et le
 * précédent est explicite — `test-charte-sidebar.php` appelle `side()` plutôt que
 * de réécrire son mélange, parce que la copie avait failli balayer 4 096 accents
 * sans jamais voir le plafond de luminance.
 *
 * 🔎 Ce que ce contrôle a mis au jour au passage, et qui n'est PAS corrigé ici :
 * `--cd-accent-strong` (la variante de survol de l'accent) est calculée à chaque
 * chargement de page et consommée par ZÉRO règle. Son unique lecteur était
 * `--cd-yellow-d`, l'alias mort supprimé en 46.4. Ce n'est pas un défaut de
 * conformité — le token fait partie du contrat d'accent — mais un manque de
 * design : le survol des surfaces d'accent ne se sert pas de la couleur prévue
 * pour lui. Consigné, pas avalé.
 */
$aod_surf_injectes = array();
if ( preg_match_all( '/(--cd-[a-z0-9-]+)\s*:/', AOD_CD_Customize::css_vars(), $aod_surf_im ) ) {
	$aod_surf_injectes = array_unique( $aod_surf_im[1] );
}
AOD_Test::ok(
	count( $aod_surf_injectes ) >= 5,
	sprintf( 'TÉMOIN : %d tokens réellement injectés par css_vars() (interrogé, pas recopié)', count( $aod_surf_injectes ) ),
	'css_vars() n\'a rien rendu de reconnaissable : sans cette liste, les tokens du contrat d\'accent passeraient tous pour orphelins'
);
$aod_surf_orphelins = array_values( array_diff( $aod_surf_orphelins, $aod_surf_injectes ) );
sort( $aod_surf_orphelins );

/*
 * SECONDE CATÉGORIE : plus aucune. ✅ La 46.5 a VIDÉ cette liste, comme la 46.4
 * l'en avait chargée — c'était sa raison d'être.
 *
 * Ce qu'elle est devenue, cran par cran, parce que le chemin compte plus que le
 * résultat ici : `--cd-sp-1` (10 sites), `--cd-sp-6` (6) et `--cd-sp-10` (1) ont
 * été APPLIQUÉS sur des littéraux exacts — Δ = 0 px, aucun changement visuel.
 * `--cd-sp-8:32px` a été SUPPRIMÉ : 32 px n'apparaît dans aucune propriété
 * d'espacement de la feuille, contrairement à ce que supposait la décision D2.
 * `--cd-r-lg` et `--cd-yellow-d` avaient été supprimés en 46.4, pour la même
 * raison (aucun candidat, ou aucun lecteur).
 *
 * 🔒 LA LISTE VIDE EST LE VRAI CLIQUET, et il est plus dur que celui qu'elle
 * remplace : désormais tout token ajouté à `:root` sans consommateur — ou tout
 * token dont on retire le dernier usage — rougit immédiatement. C'est ce qui
 * empêche la Phase 5 de se rejouer : son échelle avait été déclarée puis jamais
 * appliquée, et rien ne l'a signalé pendant huit phases.
 */
$aod_surf_attendus = array();

AOD_Test::eq(
	implode( ' · ', $aod_surf_attendus ),
	implode( ' · ', $aod_surf_orphelins ),
	sprintf(
		'🔴 R4 — AUCUN token déclaré n\'est laissé sans lecteur (%d déclarations balayées, %d injectées par css_vars())',
		count( $aod_surf_decl ),
		count( $aod_surf_injectes )
	)
);

/* =========================================================================
 * R5 (46.5) — les valeurs littérales ne peuvent que DESCENDRE
 *
 * 🚨 CE QUE CE PLAFOND EMPÊCHE, et il est né d'un échec mesuré. Le diagnostic
 * d'ouverture de la Phase 5 nommait comme problème n°1 : « ~10 tailles de police
 * en dur → hiérarchie floue ». Elle a répondu en DÉCLARANT une échelle de 6
 * crans… puis en ne l'appliquant pas. Huit phases plus tard, le compte de tailles
 * distinctes était monté à 20 : le problème n°1 avait DOUBLÉ après la phase censée
 * le régler, sans que rien ne le signale.
 *
 * On ne garde donc pas « l'échelle est appliquée » (invérifiable : une valeur hors
 * échelle peut être légitime), mais l'invariant qui compte vraiment : **le nombre
 * de littéraux ne remonte pas**. C'est la forme du cliquet de décomposition
 * (`test-decomposition.php`), pour la même raison — un plafond qui suit la
 * réalité vaut mieux qu'une interdiction qu'on contourne.
 *
 * ⚠️ Ce plafond est un CLIQUET, pas un objectif. 111 littéraux subsistent, sur 14
 * tailles sans cran correspondant : les rapprocher est un changement VISUEL sur
 * ~110 sites, donc un arbitrage de design qui demande un œil, pas un `sed`. Il
 * est cadré en 46.5bis. Ce qui est acquis ici, et qui ne peut plus se perdre :
 * les 43 correspondances EXACTES (Δ = 0 px) sont passées aux crans.
 * ========================================================================= */

AOD_Test::group( 'Charte — les valeurs littérales ne peuvent que descendre (46.5)' );

$aod_surf_corps = aod_test_surf_body( $aod_surf_css );

// Plafonds constatés le 2026-07-30, APRÈS la conversion des correspondances
// exactes. Ils DESCENDENT à chaque lot converti ; ils ne remontent jamais.
//   font-size : 153 → 110 (46.5 : les 43 exactes, 11.5/13/14.5/17/20/26 px).
//              110 →  29 (46.5bis : 77 sites Δ±0,5 px + 5 arbitrages Δ1 px).
//   padding   : 146 → 143 (24 px seul, 40px 16px, 4px seul).
//
// 🔒 LE PLAFOND TYPO EST DÉSORMAIS À SON PLANCHER, et c'est ce qui donne tout son
// mordant au cliquet : les 29 littéraux restants sont TOUS documentés dans
// `dashboard.css` (bloc de l'échelle) — **24 métriques de glyphe** (un caractère
// dessiné dans une boîte déterminée : la croix d'une modale, un chevron qui pivote,
// des initiales dans un rond de 42 px) et **5 micro-libellés** sous le plancher de
// l'échelle, dont `.aod-cd-navgroup` que la 45.5 a mesuré au pixel dans la
// géométrie du rail. Aucun n'est de la typographie.
// Conséquence : toute hausse est maintenant, par construction, une régression —
// il n'existe plus de « littéral légitime » non recensé à ajouter.
// ⚠️ Ne PAS descendre ce plafond en dessous de 29 sans avoir retiré des sites du
// recensement : on rendrait le cliquet rouge en permanence, donc ignoré (D16).
// ⚠️ Ces comptes sont ceux de `aod_test_surf_body()` — donc HORS `:root` et hors
// commentaires. Ils diffèrent volontairement d'un `grep` sur le fichier brut : la
// déclaration d'un cran dans `:root` n'est pas un littéral en dur, et un
// commentaire qui cite « font-size:13px » pour expliquer une conversion ne doit
// pas la faire compter (le piège déjà rencontré trois fois dans cette phase).
$aod_surf_plafond_fs  = 29;
$aod_surf_plafond_pad = 143;

$aod_surf_n_fs  = preg_match_all( '/font-size\s*:\s*[0-9.]+px/', $aod_surf_corps );
$aod_surf_n_pad = preg_match_all( '/padding\s*:\s*[0-9]/', $aod_surf_corps );

AOD_Test::ok(
	$aod_surf_n_fs <= $aod_surf_plafond_fs,
	sprintf( 'R5 — %d `font-size` littéraux (plafond %d, ne doit pas remonter)', $aod_surf_n_fs, $aod_surf_plafond_fs ),
	'Une taille de police en dur de plus. Utilise un cran `--cd-fs-*` — ou, si aucun ne convient, '
		. 'écris la raison en commentaire ET descends ce plafond après avoir converti ailleurs.'
);
AOD_Test::ok(
	$aod_surf_n_pad <= $aod_surf_plafond_pad,
	sprintf( 'R5 — %d `padding` littéraux (plafond %d, ne doit pas remonter)', $aod_surf_n_pad, $aod_surf_plafond_pad ),
	'Un espacement en dur de plus. Même règle : un cran `--cd-sp-*`, ou une raison écrite.'
);

/*
 * Le plafond doit SUIVRE la réalité — même garde que `test-decomposition-js.php`.
 * Sans elle, une grosse conversion laisserait un plafond très au-dessus du compte,
 * et le cliquet cesserait de mordre sans que personne ne s'en aperçoive.
 */
AOD_Test::ok(
	$aod_surf_n_fs >= $aod_surf_plafond_fs - 5 && $aod_surf_n_pad >= $aod_surf_plafond_pad - 15,
	sprintf( 'R5 — les plafonds collent à la réalité (%d/%d et %d/%d)', $aod_surf_n_fs, $aod_surf_plafond_fs, $aod_surf_n_pad, $aod_surf_plafond_pad ),
	'La feuille a bien maigri : DESCENDS ces plafonds, sinon le cliquet ne mord plus.'
);

/*
 * 🔴 Le témoin qui rend le plafond typo VÉRIFIABLE et pas seulement chiffré : les
 * 29 littéraux restants doivent tous porter une taille du recensement documenté.
 * Une valeur HORS de cette liste signifie qu'un littéral non recensé est apparu —
 * même si le compte total n'a pas bougé (on en aurait retiré un et ajouté un autre,
 * exactement le contournement qu'un seuil seul autorise).
 */
/*
 * ⚠️ Comparaison NUMÉRIQUE, et pas de normalisation de chaîne. La première version
 * faisait `rtrim( rtrim( $v, '0' ), '.' )` pour ramener « 14.0 » à « 14 » — et
 * transformait « 10 » en « 1 », parce que `rtrim` ne sait pas qu'un zéro final
 * n'est décimal que s'il suit un point. Le cliquet a rougi sur `10px` dès son
 * premier run : bon sens de l'erreur, mais la leçon est de ne pas bricoler un
 * nombre à coups de chaînes.
 */
$aod_surf_recensees = array( 10.0, 10.5, 11.0, 12.0, 12.5, 14.0, 15.0, 16.0, 18.0, 19.0, 21.0, 22.0, 28.0 );
$aod_surf_intruses  = array();
if ( preg_match_all( '/font-size\s*:\s*([0-9.]+)px/', $aod_surf_corps, $aod_surf_fm3 ) ) {
	foreach ( array_unique( $aod_surf_fm3[1] ) as $aod_surf_v ) {
		if ( ! in_array( (float) $aod_surf_v, $aod_surf_recensees, true ) ) {
			$aod_surf_intruses[] = $aod_surf_v . 'px';
		}
	}
}
AOD_Test::ok(
	empty( $aod_surf_intruses ),
	sprintf( 'R5 — les %d littéraux restants portent tous une taille RECENSÉE (%d tailles documentées)', $aod_surf_n_fs, count( $aod_surf_recensees ) ),
	'taille(s) non recensée(s) : ' . implode( ' · ', $aod_surf_intruses )
		. ' — soit c\'est une métrique de glyphe ou un micro-libellé, et il faut l\'INSCRIRE ici avec sa raison dans dashboard.css ; soit c\'est du texte, et il faut un cran `--cd-fs-*`.'
);

/*
 * Témoin du compteur (règle du témoin pt 1 et 3). Un motif qui ne reconnaîtrait
 * plus les déclarations rendrait 0 — donc un vert éclatant sous les plafonds, en
 * n'ayant rien compté. On ancre sur un corpus jouet, avec un cas nommé : une
 * valeur passée en token NE DOIT PAS être comptée.
 */
$aod_surf_jfs = preg_match_all( '/font-size\s*:\s*[0-9.]+px/', '.a{font-size:13px}.b{font-size:var(--cd-fs-sm)}.c{font-size:12.5px}' );
AOD_Test::eq(
	2,
	$aod_surf_jfs,
	'TÉMOIN : le compteur voit les littéraux (13px, 12.5px) et IGNORE ce qui est passé au cran (`var(--cd-fs-sm)`)'
);

/*
 * Témoin du matcher (DoD §C.2 pt 5). Sans lui, un scan qui ne reconnaîtrait plus
 * les déclarations rendrait `$aod_surf_orphelins` vide — et l'égalité ci-dessus
 * échouerait, ce qui est le bon sens de l'erreur. Mais un scan qui ne
 * reconnaîtrait plus les USAGES rendrait TOUT orphelin, et personne ne saurait
 * dire si le défaut est dans le CSS ou dans le test. On ancre donc les deux
 * comptes.
 */
AOD_Test::ok(
	count( $aod_surf_decl ) >= 30 && count( array_unique( $aod_surf_used ) ) >= 25,
	sprintf(
		'TÉMOIN : %d tokens déclarés, %d distincts réellement lus par un var()',
		count( $aod_surf_decl ),
		count( array_unique( $aod_surf_used ) )
	),
	'les comptes se sont effondrés : le scan ne reconnaît plus la forme des déclarations ou des usages — l\'adapter, pas le contourner'
);

/*
 * Et le témoin du SENS : sur un corpus jouet, un token déclaré et jamais lu doit
 * être vu comme orphelin, et un token lu doit passer. C'est cette assertion qui
 * garantit que R4 mesure bien la direction qui manquait.
 */
$aod_surf_jouet  = ':root{--cd-jamais-lu:#fff;--cd-bien-lu:#000}.x{color:var(--cd-bien-lu)}';
$aod_surf_jd     = array();
$aod_surf_ju     = array();
if ( preg_match_all( '/(--cd-[a-z0-9-]+)\s*:/', $aod_surf_jouet, $aod_surf_jm ) ) {
	$aod_surf_jd = array_unique( $aod_surf_jm[1] );
}
if ( preg_match_all( '/var\(\s*(--cd-[a-z0-9-]+)/', $aod_surf_jouet, $aod_surf_jum ) ) {
	$aod_surf_ju = $aod_surf_jum[1];
}
$aod_surf_jorph = array_values( array_diff( $aod_surf_jd, $aod_surf_ju ) );
AOD_Test::eq(
	'--cd-jamais-lu',
	implode( ',', $aod_surf_jorph ),
	'TÉMOIN DE SENS : un token déclaré et jamais lu est vu orphelin ; un token lu ne l\'est pas'
);

/*
 * Le témoin de `decom()`. Une variable qui n'est déclarée QUE dans un commentaire
 * n'existe pas : le navigateur servira le repli, exactement comme pour les trois
 * fantômes de 17.4. Le cliquet doit donc la voir orpheline.
 *
 * Sans cette assertion, quelqu'un rebrancherait un jour le scan sur le fichier
 * brut — c'est plus court, et rien ne rougirait.
 */
$aod_surf_piege = "/* on avait --cd-jamais-la:#fff ici */ .x{color:var(--cd-jamais-la)}";
$aod_surf_pdecl = array();
if ( preg_match_all( '/(--cd-[a-z0-9-]+)\s*:/', aod_test_surf_decom( $aod_surf_piege ), $aod_surf_pd ) ) {
	$aod_surf_pdecl = $aod_surf_pd[1];
}
AOD_Test::ok(
	! in_array( '--cd-jamais-la', $aod_surf_pdecl, true ),
	'Témoin : une variable déclarée seulement dans un commentaire ne compte pas comme déclarée',
	'le scan lit les commentaires — une variable fantôme peut se faire cautionner par sa propre doc'
);

/*
 * Un repli sur une variable qui EXISTE est du code mort qui ment : il donne à
 * lire une valeur qui ne sera jamais appliquée. Les trois derniers ont été
 * purgés en 17.4 (`var(--cd-bad,#dc2626)`, `var(--cd-ink,#1a1a1a)`,
 * `var(--cd-muted,#888)`).
 */
$aod_surf_morts = array();
if ( preg_match_all( '/var\(\s*(--cd-[a-z0-9-]+)\s*,([^)]*)\)/', $aod_surf_decom, $aod_surf_fm2, PREG_SET_ORDER ) ) {
	foreach ( $aod_surf_fm2 as $aod_surf_set ) {
		if ( in_array( $aod_surf_set[1], $aod_surf_decl, true ) ) {
			$aod_surf_morts[ $aod_surf_set[0] ] = true;
		}
	}
}
AOD_Test::ok(
	empty( $aod_surf_morts ),
	'Aucun repli mort : un var() ne propose pas de valeur de secours pour une variable qui existe',
	'repli(s) mort(s) : ' . implode( ' · ', array_slice( array_keys( $aod_surf_morts ), 0, 6 ) )
);

AOD_Test::group( 'Charte — toute animation jouée est une animation déclarée' );

/*
 * 🚨 Le même défaut que les variables fantômes, sur un autre objet — et il avait
 * survécu au contrôle de 17.4, qui ne regardait que les `--cd-*`.
 *
 * `.aod-cd-statussel-menu` et `.aod-cd-csel-menu` demandaient `aod-cd-dd-in`, un
 * `@keyframes` JAMAIS déclaré. Vérifié au navigateur avant correction :
 * `getComputedStyle().animationName` rendait bien `aod-cd-dd-in`, et
 * `getAnimations()` rendait `[]` — le nom était là, rien ne tournait. Les deux
 * menus se sont ouverts d'un coup pendant toute leur existence.
 *
 * Une animation inexistante ne casse rien, n'écrit rien en console, et laisse le
 * `animation-name` en place : c'est le seul angle où on peut la voir.
 */
$aod_surf_kf = array();
if ( preg_match_all( '/@keyframes\s+([a-zA-Z][\w-]*)/', $aod_surf_decom, $aod_surf_km ) ) {
	$aod_surf_kf = array_unique( $aod_surf_km[1] );
}

$aod_surf_anim   = array();
$aod_surf_absent = array();
if ( preg_match_all( '/animation(?:-name)?\s*:\s*([^;}]+)/', $aod_surf_decom, $aod_surf_am ) ) {
	foreach ( $aod_surf_am[1] as $aod_surf_decl_a ) {
		// Le raccourci mélange nom, durée, courbe et itérations dans n'importe
		// quel ordre. On ne retient que les jetons qui PEUVENT être un nom : pas
		// un temps, pas un nombre, pas un mot-clé de la propriété, pas un var().
		foreach ( preg_split( '/[\s,]+/', trim( $aod_surf_decl_a ) ) as $aod_surf_tok2 ) {
			if ( '' === $aod_surf_tok2 || preg_match( '/^[\d.]|^var\(|\(/', $aod_surf_tok2 ) ) {
				continue;
			}
			if ( in_array( $aod_surf_tok2, array( 'none', 'infinite', 'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'alternate', 'normal', 'reverse', 'both', 'forwards', 'backwards', 'running', 'paused', 'step-start', 'step-end' ), true ) ) {
				continue;
			}
			$aod_surf_anim[] = $aod_surf_tok2;
			if ( ! in_array( $aod_surf_tok2, $aod_surf_kf, true ) ) {
				$aod_surf_absent[ $aod_surf_tok2 ] = true;
			}
		}
	}
}
AOD_Test::ok(
	empty( $aod_surf_absent ),
	sprintf(
		'Aucune animation fantôme — %d @keyframes déclarés, %d appels, %d noms distincts appelés',
		count( $aod_surf_kf ),
		count( $aod_surf_anim ),
		count( array_unique( $aod_surf_anim ) )
	),
	'jamais déclaré(s) : ' . implode( ' · ', array_keys( $aod_surf_absent ) )
);

/*
 * Mise au rouge du matcher (DoD §C.2 pt 5), sur la forme EXACTE qui a été
 * corrigée en 17.6. Sans ce témoin, un matcher qui n'extrairait plus aucun nom
 * serait vert — et ce serait le sixième cas du tableau de la règle du témoin :
 * le garde-fou vide.
 */
$aod_surf_fixt_a = '.x{animation:aod-cd-dd-in .14s ease}.y{animation:aod-cd-pop .18s ease}';
$aod_surf_pris_a = 0;
if ( preg_match_all( '/animation(?:-name)?\s*:\s*([^;}]+)/', $aod_surf_fixt_a, $aod_surf_fam ) ) {
	foreach ( $aod_surf_fam[1] as $aod_surf_d ) {
		foreach ( preg_split( '/[\s,]+/', trim( $aod_surf_d ) ) as $aod_surf_t ) {
			if ( '' === $aod_surf_t || preg_match( '/^[\d.]|^var\(|\(/', $aod_surf_t ) || 'ease' === $aod_surf_t ) {
				continue;
			}
			if ( ! in_array( $aod_surf_t, $aod_surf_kf, true ) ) {
				++$aod_surf_pris_a;
			}
		}
	}
}
AOD_Test::eq( 1, $aod_surf_pris_a, 'Témoin : le scan repère `aod-cd-dd-in` (fantôme) et laisse passer `aod-cd-pop` (déclaré)' );

AOD_Test::group( 'Charte — le repli du CSS dit la vérité sur ce que le PHP produit' );

/*
 * `:root` dans dashboard.css n'est qu'un FILET : en fonctionnement, css_vars()
 * injecte ses valeurs en ligne et les écrase toutes. D'où la dérive constatée en
 * 17.3 — le repli annonçait `--cd-accent-soft:#fff8cc` quand le système produit
 * `#fffbe0` pour l'accent par défaut. Personne ne pouvait le voir : la valeur
 * fausse n'est jamais celle qui s'affiche.
 *
 * Ce contrôle compare les deux sources pour DEFAULT_ACCENT. Il vaut aussi comme
 * garde-fou contre l'oubli symétrique : ajouter un token dans css_vars() sans lui
 * donner de repli.
 */
$aod_surf_avant = get_theme_mod( 'aod_cd_accent', null );
set_theme_mod( 'aod_cd_accent', AOD_CD_Customize::DEFAULT_ACCENT );
$aod_surf_vars = AOD_CD_Customize::css_vars();

preg_match( '/:root\s*\{(.*?)\}/s', $aod_surf_decom, $aod_surf_rootm );
$aod_surf_root = isset( $aod_surf_rootm[1] ) ? $aod_surf_rootm[1] : '';

/*
 * 🔁 `--cd-bg` et `--cd-card` ONT ÉTÉ AJOUTÉS ICI LE 2026-08-04, et l'oubli qu'ils
 * réparent est celui que ce contrôle existe pour attraper. Le fond de page est
 * devenu dérivé la veille (BG_MIX) sans que son repli bouge : `dashboard.css`
 * annonçait encore `#F1F1F1` quand le système produit `#fffded` pour l'accent par
 * défaut. Invisible — la valeur fausse n'est jamais celle qui s'affiche —, et
 * exactement la dérive décrite au-dessus, sur un token que la liste ne balayait
 * pas. Une liste en dur hérite des angles morts du jour où on l'a écrite : tout
 * token de SURFACE ajouté à css_vars() doit venir ici.
 *
 * 🔴 ET LA PREUVE EST TOMBÉE LE LENDEMAIN, sur un token que la liste ne couvrait
 * toujours pas : `--cd-accent-strong` annonçait `#e6cb00` en repli quand le
 * système produit `#e0c71a` pour l'accent par défaut. La dérive avait pu durer
 * parce que ce token n'avait AUCUN lecteur — personne ne pouvait voir la couleur
 * fausse, faute de règle qui l'affiche. Maintenant que le survol du bouton
 * principal le consomme, il entre ici avec son encre.
 * ⚠️ La leçon vaut au-delà du cas : un repli faux sur un token INUTILISÉ est
 * invisible deux fois — ni à l'écran, ni au raisonnement. C'est le premier usage
 * qui le révèle, et il est alors trop tard pour savoir depuis quand.
 */
foreach ( array( '--cd-accent-soft', '--cd-accent-wash', '--cd-bg', '--cd-card', '--cd-accent-strong', '--cd-accent-strong-ink' ) as $aod_surf_tok ) {
	$aod_surf_php = preg_match( '/' . preg_quote( $aod_surf_tok, '/' ) . ':\s*(#[0-9a-fA-F]{6})/', $aod_surf_vars, $aod_surf_pm )
		? strtolower( $aod_surf_pm[1] ) : '';
	$aod_surf_cssv = preg_match( '/' . preg_quote( $aod_surf_tok, '/' ) . '\s*:\s*(#[0-9a-fA-F]{3,6})/', $aod_surf_root, $aod_surf_cm )
		? aod_test_surf_norm( $aod_surf_cm[1] ) : '';
	AOD_Test::eq(
		$aod_surf_php,
		'' === $aod_surf_cssv ? '' : '#' . $aod_surf_cssv,
		sprintf( 'Le repli CSS de %s correspond à ce que css_vars() produit pour l\'accent par défaut', $aod_surf_tok )
	);
}

if ( null === $aod_surf_avant ) {
	remove_theme_mod( 'aod_cd_accent' );
} else {
	set_theme_mod( 'aod_cd_accent', $aod_surf_avant );
}
AOD_Test::eq(
	(string) $aod_surf_avant,
	(string) get_theme_mod( 'aod_cd_accent', null ),
	'L\'accent du clone est rendu intact après le contrôle'
);
