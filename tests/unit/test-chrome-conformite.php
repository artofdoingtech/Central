<?php
/**
 * Le chrome de `/gestion` ne peut plus démentir ses propres règles (Phase 46).
 *
 * 🚨 LE MOTIF QUE CE FICHIER FERME, ET IL A TROIS PRÉCÉDENTS. La Phase 17 l'a
 * nommé sur les surfaces flottantes, la 45.2 sur la barre d'action groupée, et le
 * verdict A.22 le mesure une troisième fois : **une règle d'interface écrite en
 * prose, correcte, appliquée une ou deux fois — et rien qui empêche les autres
 * occurrences d'en diverger.** Les défauts qui en sortent ne cassent jamais un
 * écran : ils se lisent. Personne ne les voit.
 *
 * Le cas de R1 est le plus net du dépôt : la règle « l'anneau de focus ne dépend
 * pas de l'accent » était écrite EN ENTIER dans `dashboard.css` (~l.152), et
 * démentie quatre fois plus bas dans le même fichier. Elle a même été
 * ré-appliquée à la main la veille (45.2, case « écraser ») avec son
 * raisonnement recopié — sans que personne aille voir les quatre voisins.
 *
 * ⚠️ POURQUOI STATIQUE, alors que le dépôt préfère mesurer au navigateur. Les
 * deux sont nécessaires et ne prouvent pas la même chose :
 *   · ici on garde la RÈGLE (« aucun anneau bâti sur l'accent »), sur les 6
 *     accents possibles à la fois, sans en instancier aucun ;
 *   · la porte `contraste` garde le RÉSULTAT (l'anneau calculé contre son fond
 *     réel), mais un accent à la fois et seulement sur ce qu'elle sait focaliser.
 * Un cliquet statique qui remplacerait la porte serait une régression ; une porte
 * qui remplacerait le cliquet en serait une autre. Cf. la leçon `FS_METHOD` de
 * TEMPLATE.md §3 : éprouver une couche là où elle sert, pas là où on l'écrit.
 *
 * 🔎 CE N'EST PAS UNE PRÉCAUTION THÉORIQUE — la démonstration est arrivée le jour
 * même. À son premier run, la porte a trouvé un anneau que R1 ne POUVAIT pas voir :
 * `.aod-cd-reset-input:focus` posait `box-shadow:0 0 0 3px rgba(217,45,32,.18)`,
 * soit 1,29:1 composé sur le fond de la zone de danger — sur le champ de remise à
 * zéro de la boutique, le contrôle le plus destructif de `/gestion`. Invisible à
 * R1 parce que la couleur est un LITTÉRAL, pas `var(--cd-accent*)`.
 *
 * ⚠️ Et on ne « durcit » PAS R1 pour l'attraper : interdire tout littéral dans un
 * indicateur de focus ferait rougir `.aod-cd-side :focus-visible{outline-color:#fff}`,
 * qui est correct et voulu. R1 garde ce qu'il sait garder — la dépendance à une
 * couleur INCONNUE À L'ÉCRITURE (celle du client) ; la porte garde ce qui ne se
 * décide qu'au rendu — la valeur composée. Étendre l'un au domaine de l'autre les
 * abîme tous les deux.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * La feuille sans ses commentaires.
 *
 * Indispensable, et pour la raison exacte de `test-charte-surfaces.php` : les
 * commentaires de `dashboard.css` CITENT les déclarations supprimées (le bloc de
 * la 46.1 écrit `outline:2px solid var(--cd-yellow)` en prose pour expliquer ce
 * qui a été retiré). Un scan du fichier brut se ferait donc piéger par sa propre
 * documentation et rougirait sur du texte mort.
 *
 * ⚠️ Préfixe `aod_chrome_` : `tests/all.php` charge tous les fichiers par `glob()`
 * dans le même espace global — un helper au nom générique provoque un
 * `Cannot redeclare` (c'est déjà arrivé deux fois dans ce dossier).
 *
 * @param string $css Feuille complète.
 * @return string
 */
function aod_chrome_decom( $css ) {
	return (string) preg_replace( '#/\*.*?\*/#s', '', (string) $css );
}

/**
 * Découpe une feuille en règles `[ sélecteur, déclarations ]`.
 *
 * Le motif ne matche que les blocs LES PLUS INTERNES (`[^{}]` interdit de
 * traverser une accolade) : les règles d'un `@media` sont donc bien vues, et
 * l'en-tête `@media(...)` est sauté au lieu d'être pris pour un sélecteur. Les
 * étapes d'un `@keyframes` (`from`, `to`, `0%`) ressortent comme des règles —
 * inoffensif ici, aucune ne porte `:focus`.
 *
 * @param string $css Feuille sans commentaires.
 * @return array<int,array{0:string,1:string}> Liste de couples.
 */
function aod_chrome_regles( $css ) {
	$out = array();
	if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/s', $css, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $set ) {
			$sel = trim( preg_replace( '/\s+/', ' ', $set[1] ) );
			if ( '' === $sel || '@' === substr( $sel, 0, 1 ) ) {
				continue;
			}
			$out[] = array( $sel, $set[2] );
		}
	}
	return $out;
}

/**
 * Les variables `--cd-*` lues par les propriétés qui DESSINENT un indicateur.
 *
 * Volontairement borné à `outline`, `outline-color` et `box-shadow` : ce sont les
 * seules propriétés qui peignent l'anneau. `border-color` en est exclu à dessein —
 * il accompagne légitimement un focus (`.aod-cd-csel-btn` pose une bordure d'encre
 * EN PLUS de son `box-shadow`), et l'inclure ferait rougir des règles correctes,
 * donc désarmer le cliquet.
 *
 * @param string $decls Corps d'une règle.
 * @return string[] Noms de variables, avec doublons.
 */
function aod_chrome_vars_indicateur( $decls ) {
	$vars = array();
	// Le `(?=;|$)` borne la valeur : sans lui, `outline:none;box-shadow:var(--x)`
	// serait lu comme UNE seule valeur d'`outline` et le box-shadow compterait
	// deux fois. Ici c'est sans conséquence (on cherche une présence), mais le
	// compte publié plus bas serait faux — et un compte faux est un compte qui
	// ne prouve rien.
	if ( preg_match_all( '/(?:^|;)\s*(outline(?:-color)?|box-shadow)\s*:([^;]*)(?=;|$)/i', $decls, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $set ) {
			if ( preg_match_all( '/var\(\s*(--cd-[a-z0-9-]+)/i', $set[2], $vm ) ) {
				foreach ( $vm[1] as $v ) {
					$vars[] = strtolower( $v );
				}
			}
		}
	}
	return $vars;
}

/**
 * Les anneaux de focus bâtis sur l'accent, dans une feuille donnée.
 *
 * Isolé en fonction pour que le TÉMOIN passe par le même code que le contrôle —
 * règle du témoin, point 4 (« passer par le code de production »). Un témoin qui
 * recopierait le matcher éprouverait sa copie.
 *
 * @param string $css Feuille (commentaires déjà retirés ou non).
 * @return array{pris:array<string,string>,regles:int,decls:int}
 */
function aod_chrome_anneaux_accent( $css ) {
	/*
	 * La famille ACCENT : toute variable dont la valeur est choisie par le client
	 * dans le Personnalisateur, donc dont le contraste est inconnu à l'écriture.
	 *
	 * `--cd-focus` n'en fait PAS partie, et c'est tout le sujet : il vaut
	 * `0 0 0 2px var(--cd-ink), 0 0 0 4px rgba(var(--cd-accent-rgb),.35)` —
	 * un liseré d'encre TOUJOURS contrasté, puis un halo d'accent qui n'est que
	 * décoratif. C'est la seule forme correcte, et c'est pour ça qu'elle est un
	 * token et pas une recette recopiée.
	 */
	$famille = array(
		'--cd-accent',
		'--cd-accent-strong',
		'--cd-accent-soft',
		'--cd-accent-wash',
		'--cd-accent-rgb',
		'--cd-yellow',
		'--cd-yellow-d',
	);

	/*
	 * 🚨 `--cd-accent-on-dark` est TOLÉRÉ, mais sur la sidebar seulement.
	 *
	 * Ce n'est pas une faveur : `readable_on()` lui garantit 4,6:1 sur nos
	 * surfaces sombres, il est donc parfaitement légitime en anneau LÀ. Sur une
	 * surface claire il ne garantit rien. L'interdire partout ferait rougir une
	 * valeur correcte — et un cliquet qui refuse le bon usage se fait désarmer,
	 * pas corriger (dette D16 : un test rouge en permanence finit ignoré).
	 */
	$sombre = 'aod-cd-side';

	/*
	 * Liste blanche VOLONTAIREMENT VIDE. Elle existe pour que la prochaine
	 * dérogation soit un choix ÉCRIT, avec sa raison, et non un oubli qui passe.
	 * Y ajouter une entrée sans justification, c'est rouvrir la Phase 46.
	 */
	$permis = array();

	$pris   = array();
	$regles = 0;
	$decls  = 0;

	foreach ( aod_chrome_regles( aod_chrome_decom( $css ) ) as $r ) {
		list( $sel, $body ) = $r;
		if ( false === stripos( $sel, ':focus' ) ) {
			continue;
		}
		++$regles;
		$vars = aod_chrome_vars_indicateur( $body );
		$decls += count( $vars );
		foreach ( $vars as $v ) {
			if ( in_array( $sel, $permis, true ) ) {
				continue;
			}
			if ( '--cd-accent-on-dark' === $v && false !== stripos( $sel, $sombre ) ) {
				continue;
			}
			if ( in_array( $v, $famille, true ) || '--cd-accent-on-dark' === $v ) {
				$pris[ $sel . ' → ' . $v ] = $v;
			}
		}
	}

	return array(
		'pris'   => $pris,
		'regles' => $regles,
		'decls'  => $decls,
	);
}

AOD_Test::group( 'Chrome 46.1 — R0 : le parseur lit la feuille jusqu\'au bout' );

$aod_chrome_css = (string) file_get_contents( AOD_CD_PATH . 'assets/css/dashboard.css' );
AOD_Test::ok(
	strlen( $aod_chrome_css ) > 40000,
	sprintf( 'La feuille est lue (%d octets)', strlen( $aod_chrome_css ) ),
	'dashboard.css introuvable ou tronqué — tout ce qui suit serait vert par vacuité'
);

$aod_chrome_toutes = aod_chrome_regles( aod_chrome_decom( $aod_chrome_css ) );

/*
 * La DERNIÈRE règle réelle de la feuille, épinglée par un COUPLE
 * (sélecteur, déclaration) et non par son seul sélecteur.
 *
 * 🚨 Deux pièges rencontrés en écrivant cette assertion, tous deux instructifs :
 *
 * 1. La dernière règle est un GROUPE de cinq sélecteurs. Une égalité stricte sur
 *    `.aod-cd-catdd-panel:not([hidden])` échouait donc, alors que le parseur
 *    faisait parfaitement son travail. Bon sens de l'échec — un cliquet neuf qui
 *    rougit à tort se corrige ; celui qui verdit à tort ne se voit jamais.
 * 2. Mais une simple recherche de SOUS-CHAÎNE ne marche pas non plus : ce
 *    sélecteur apparaît DEUX fois dans la feuille (l.741 pour l'ouverture
 *    normale, l.2580 pour sa neutralisation sous `prefers-reduced-motion`). Elle
 *    aurait été satisfaite par la première — donc verte même si le parse
 *    s'arrêtait 1 800 lignes avant la fin. C'est exactement le faux vert que
 *    cette assertion est censée interdire.
 *
 * Le couple, lui, n'existe qu'une fois : seule la dernière règle associe ce
 * sélecteur à l'animation `aod-cd-fade`.
 */
$aod_chrome_fin = false;
foreach ( $aod_chrome_toutes as $aod_chrome_r ) {
	if ( false !== strpos( $aod_chrome_r[0], '.aod-cd-catdd-panel:not([hidden])' )
		&& false !== strpos( $aod_chrome_r[1], 'aod-cd-fade' ) ) {
		$aod_chrome_fin = true;
		break;
	}
}

/*
 * 🚨 UN INVARIANT, PAS UN SEUIL — leçon n°5 de la règle du témoin (§C.2).
 *
 * Le précédent exact : un contrôle d'intégrité de cette même feuille reposait sur
 * « > 600 règles », et une cassure en FIN de fichier n'en fait perdre que 6. Le
 * seuil restait franchi, le contrôle restait vert, et la queue du fichier n'était
 * plus inspectée par personne.
 *
 * On ancre donc sur la DERNIÈRE règle réelle de la feuille (le dernier bloc avant
 * l'unique `@keyframes` final). Si le parseur s'arrête n'importe où avant la fin —
 * accolade déséquilibrée, commentaire non fermé, motif cassé — ce sélecteur
 * disparaît et l'assertion tombe. Une cassure de 6 règles ne peut plus passer.
 */
AOD_Test::ok(
	$aod_chrome_fin,
	sprintf( 'Le parseur atteint la DERNIÈRE règle de la feuille (%d règles vues)', count( $aod_chrome_toutes ) ),
	'la dernière règle n\'a pas été vue — le parse s\'interrompt avant la fin, et tout contrôle en aval est partiel'
);

AOD_Test::group( 'Chrome 46.1 — R1 : aucun anneau de focus n\'est bâti sur l\'accent' );

$aod_chrome_r1 = aod_chrome_anneaux_accent( $aod_chrome_css );

/*
 * Le compte est une ASSERTION, pas un affichage (règle du témoin, point 1). Sans
 * lui, un motif qui ne reconnaîtrait plus `:focus` — ou un `aod_chrome_regles()`
 * cassé — rendrait `pris` vide et se déclarerait vert en n'ayant rien regardé.
 * C'est le sixième cas du tableau de §C.2 : le garde-fou vide.
 *
 * Plancher à 15 : mesuré à 22 règles de focus au moment de la 46.1. Il borne la
 * DISPARITION du corpus, pas sa croissance.
 */
AOD_Test::ok(
	$aod_chrome_r1['regles'] >= 15,
	sprintf(
		'TÉMOIN DE COUVERTURE : %d règles `:focus` inspectées, %d déclarations d\'indicateur lues',
		$aod_chrome_r1['regles'],
		$aod_chrome_r1['decls']
	),
	'moins de 15 règles de focus vues : le motif ne reconnaît plus le corpus, tout ce qui suit est vert par vacuité'
);

AOD_Test::ok(
	empty( $aod_chrome_r1['pris'] ),
	'Aucun `outline`/`box-shadow` de focus ne dépend de la couleur choisie par le client',
	"anneau(x) bâti(s) sur l'accent — invisible dès que le client le choisit pâle (le défaut par DÉFAUT du template) : "
		. implode( ' · ', array_keys( $aod_chrome_r1['pris'] ) )
);

/*
 * Mise au rouge du matcher (DoD §C.2 pt 5), sur les formes EXACTES retirées par
 * la 46.1 — et sur les formes correctes qui doivent passer. Sans ce témoin, un
 * matcher devenu aveugle serait indistinguable d'une feuille conforme.
 *
 * Quatre cas, chacun pour ce qu'il prouve :
 *   1. `--cd-yellow`  → pris  : l'alias historique de l'accent ne s'échappe pas.
 *   2. `--cd-accent`  → pris  : la forme directe.
 *   3. `--cd-focus`   → PASSE : la forme correcte n'est pas confondue avec le
 *                               défaut, même si sa VALEUR contient de l'accent.
 *   4. accent-on-dark → PASSE sur la sidebar : le cliquet ne refuse pas le bon
 *                               usage (sinon il se fait désarmer, pas corriger).
 */
$aod_chrome_fixture = '.aod-cd-statussel-btn:focus-visible{outline:2px solid var(--cd-yellow);outline-offset:1px}'
	. '.aod-cd-seg__btn:focus-visible{outline:2px solid var(--cd-accent);outline-offset:2px}'
	. '.aod-cd-bulk-ow input:focus-visible{outline:none;box-shadow:var(--cd-focus)}'
	. '.aod-cd-side .aod-cd-navlink:focus-visible{outline:2px solid var(--cd-accent-on-dark)}';

$aod_chrome_t = aod_chrome_anneaux_accent( $aod_chrome_fixture );
AOD_Test::eq(
	2,
	count( $aod_chrome_t['pris'] ),
	'TÉMOIN : le scan attrape les 2 formes retirées par la 46.1, et laisse passer `--cd-focus` ET `--cd-accent-on-dark` sur la sidebar'
);
AOD_Test::eq(
	4,
	$aod_chrome_t['regles'],
	'TÉMOIN : les 4 règles du corpus témoin ont bien été parcourues (sinon le 2 ci-dessus serait vrai par vacuité)'
);

/*
 * Le témoin de PORTÉE, dans l'autre sens. R1 ne gouverne QUE les indicateurs de
 * focus : l'accent en `outline` hors focus (un liseré décoratif) ou en `background`
 * est parfaitement légitime — c'est même tout l'intérêt d'un accent de marque.
 * Sans cette assertion, quelqu'un durcirait un jour R1 « pour être sûr », et le
 * cliquet se mettrait à refuser la charte qu'il est censé protéger.
 */
$aod_chrome_hors = aod_chrome_anneaux_accent( '.aod-cd-x{outline:2px solid var(--cd-accent)}.aod-cd-y:hover{box-shadow:0 0 0 2px var(--cd-yellow)}' );
AOD_Test::eq(
	0,
	count( $aod_chrome_hors['pris'] ),
	'TÉMOIN DE PORTÉE : l\'accent reste libre hors focus (liseré décoratif, survol) — R1 ne gouverne que l\'indicateur de focus'
);

/* =========================================================================
 * R2 — Le patron « carte mobile » n'est jamais posé à moitié
 *
 * 🚨 LE DÉFAUT MESURÉ (verdict A.22, constats 2 et 3), et il existait dans LES
 * DEUX SENS, ce qui est tout l'argument de ce contrôle :
 *
 *   · **Stock** portait ses 7 `data-label`, traduits, un par colonne — et PAS la
 *     classe `--cards`. Le travail était payé, l'interrupteur n'était pas mis :
 *     à 390 px, un tableau de 7 colonnes à édition inline défilait
 *     horizontalement. Un mot manquait.
 *   · **l'Accueil** avait 5 tableaux sans NI l'un NI l'autre.
 *
 * Ni l'un ni l'autre n'était visible d'une porte : `.aod-cd-tablewrap` porte
 * `overflow-x:auto`, donc le débordement reste INTÉRIEUR au conteneur et la page
 * ne déborde jamais. `rtl-mobile.js` traque le débordement de PAGE — un tableau
 * illisible au pouce lui était indistinguable d'un tableau parfait.
 *
 * ⚠️ CE QUE R2 NE PROUVE PAS, écrit ici pour que personne ne le surestime. Le
 * contrôle est au grain du FICHIER : un fichier qui porterait deux tableaux
 * `--cards` et des `data-label` sur un seul passerait. Le grain fin — « ce
 * tableau-ci défile-t-il à 390 px ? » — ne se décide qu'au rendu, et c'est la
 * porte `rtl` qui s'en charge depuis la 46.2. Les deux ensemble couvrent le
 * sujet ; R2 seul couvre le cas grossier, celui qui s'est réellement produit.
 * ========================================================================= */

/**
 * Le corps d'un fichier PHP, sans ses commentaires.
 *
 * 🚨 INDISPENSABLE, et mesuré : en écrivant la 46.2, le commentaire posé dans
 * `class-aod-cd-section-home.php` cite `.aod-cd-table--cards` en prose pour
 * expliquer le patron. Un comptage sur le fichier brut trouvait donc **6
 * occurrences pour 5 tableaux** — et, dans l'autre sens, un fichier qui aurait
 * PERDU sa classe mais gardé le commentaire qui en parle se serait déclaré
 * conforme. C'est exactement le piège nommé par `test-charte-surfaces.php` :
 * « le test se fait cautionner par sa propre documentation ».
 *
 * `token_get_all()` plutôt qu'une expression régulière : le tokenizer de PHP sait
 * ce qu'est un commentaire, y compris quand une chaîne en contient les
 * délimiteurs. Une regex sur `/* … *​/` couperait au premier `*​/` rencontré dans
 * une chaîne littérale.
 *
 * @param string $php Code source complet.
 * @return string Source sans T_COMMENT ni T_DOC_COMMENT.
 */
function aod_chrome_php_sans_commentaires( $php ) {
	$out = '';
	foreach ( token_get_all( $php ) as $tok ) {
		if ( is_array( $tok ) ) {
			if ( T_COMMENT === $tok[0] || T_DOC_COMMENT === $tok[0] ) {
				continue;
			}
			$out .= $tok[1];
			continue;
		}
		$out .= $tok;
	}
	return $out;
}

/**
 * Compte, pour un source PHP donné, les `data-label` et les tableaux `--cards`.
 *
 * Isolé pour que les témoins passent par le même code que le contrôle (règle du
 * témoin, point 4).
 *
 * @param string $php Code source.
 * @return array{labels:int,cards:int}
 */
function aod_chrome_patron_cartes( $php ) {
	$net = aod_chrome_php_sans_commentaires( $php );
	return array(
		'labels' => preg_match_all( '/data-label/', $net ),
		'cards'  => preg_match_all( '/aod-cd-table--cards/', $net ),
	);
}

AOD_Test::group( 'Chrome 46.2 — R2 : le patron « carte mobile » n\'est jamais posé à moitié' );

$aod_chrome_fichiers = glob( AOD_CD_PATH . 'includes/class-aod-cd-*.php' );
AOD_Test::ok(
	is_array( $aod_chrome_fichiers ) && count( $aod_chrome_fichiers ) >= 15,
	sprintf( 'TÉMOIN DE COUVERTURE : %d fichiers de section lus', is_array( $aod_chrome_fichiers ) ? count( $aod_chrome_fichiers ) : 0 ),
	'moins de 15 fichiers : le glob ne trouve plus les sections, tout ce qui suit est vert par vacuité'
);

$aod_chrome_boiteux  = array();
$aod_chrome_avec_tab = 0;
$aod_chrome_tot_c    = 0;
foreach ( (array) $aod_chrome_fichiers as $aod_chrome_f ) {
	$aod_chrome_p = aod_chrome_patron_cartes( (string) file_get_contents( $aod_chrome_f ) );
	if ( 0 === $aod_chrome_p['labels'] && 0 === $aod_chrome_p['cards'] ) {
		continue; // Aucun tableau de données ici — rien à prouver.
	}
	++$aod_chrome_avec_tab;
	$aod_chrome_tot_c += $aod_chrome_p['cards'];
	if ( 0 === $aod_chrome_p['labels'] || 0 === $aod_chrome_p['cards'] ) {
		$aod_chrome_boiteux[] = sprintf(
			'%s (%d data-label, %d --cards)',
			basename( $aod_chrome_f ),
			$aod_chrome_p['labels'],
			$aod_chrome_p['cards']
		);
	}
}

AOD_Test::ok(
	$aod_chrome_avec_tab >= 5,
	sprintf(
		'TÉMOIN : %d fichiers portent un tableau de données (%d tableaux `--cards` au total)',
		$aod_chrome_avec_tab,
		$aod_chrome_tot_c
	),
	'moins de 5 : le motif ne reconnaît plus les tableaux — l\'adapter, pas le supprimer'
);

AOD_Test::ok(
	empty( $aod_chrome_boiteux ),
	'`data-label` et `--cards` vont toujours ensemble — aucun patron mobile posé à moitié',
	'patron incomplet : des libellés sans mode carte (le défaut de Stock) ou un mode carte sans libellés (celui de l\'Accueil) : '
		. implode( ' · ', $aod_chrome_boiteux )
);

/*
 * Les trois témoins, chacun pour ce qu'il prouve — et le troisième est celui qui
 * compte vraiment (DoD §C.2 pt 5, et le précédent de `aod_test_surf_decom`).
 */
$aod_chrome_t_stock = aod_chrome_patron_cartes( '<?php echo \'<td data-label="x">v</td>\';' );
AOD_Test::ok(
	$aod_chrome_t_stock['labels'] > 0 && 0 === $aod_chrome_t_stock['cards'],
	'TÉMOIN : le défaut de Stock est reconnaissable (des `data-label`, aucun `--cards`)'
);

$aod_chrome_t_home = aod_chrome_patron_cartes( '<?php echo \'<table class="aod-cd-table aod-cd-table--cards">\';' );
AOD_Test::ok(
	0 === $aod_chrome_t_home['labels'] && $aod_chrome_t_home['cards'] > 0,
	'TÉMOIN : le défaut de l\'Accueil est reconnaissable (un `--cards`, aucun `data-label`)'
);

$aod_chrome_t_doc = aod_chrome_patron_cartes(
	'<?php /* le patron aod-cd-table--cards rappelle l\'en-tête via data-label */ echo \'<table class="aod-cd-table">\';'
);
AOD_Test::eq(
	0,
	$aod_chrome_t_doc['labels'] + $aod_chrome_t_doc['cards'],
	'TÉMOIN : un fichier qui ne fait que PARLER du patron dans un commentaire ne compte pas comme le portant'
);

/* =========================================================================
 * R3 — Le canal de retour partagé est une région live, et elle PRÉEXISTE
 *
 * 🚨 CE QUE R3 GARDE, ET POURQUOI CE N'EST PAS « LA PRÉSENCE DE role=status ».
 * Le défaut corrigé en 46.3 n'était pas seulement un attribut manquant : c'était
 * l'ORDRE. `toast()` créait un `<div>`, y mettait le texte, PUIS l'insérait dans
 * le document. Ajouter `role="status"` à ce nœud-là n'aurait rien changé — la
 * plupart des lecteurs d'écran n'annoncent que les mutations d'une région live
 * DÉJÀ présente dans l'arbre. Un cliquet qui se contenterait de chercher
 * `role="status"` serait donc VERT sur le défaut exact qu'on vient de corriger.
 *
 * D'où la forme de R3 : la région est créée au boot, et `toast()` — le helper
 * partagé par les 11 modules de section — ne fabrique PLUS de nœud du tout. C'est
 * cette absence qui est vérifiable statiquement, et c'est elle qui porte la
 * garantie.
 * ========================================================================= */

/**
 * Corps d'une fonction JS, SANS ses commentaires, par comptage d'accolades.
 *
 * Deux raisons de ne pas se contenter d'un `substr` :
 *
 * 1. Jusqu'à la prochaine accolade fermante, on s'arrêterait au premier bloc
 *    interne (`if`, `setTimeout`, fonction anonyme) — donc avant précisément ce
 *    qu'on veut inspecter. D'où le comptage.
 * 2. 🚨 ET SURTOUT : LES COMMENTAIRES DOIVENT PARTIR. Cette fonction a d'abord
 *    rendu le corps BRUT, et le red-proof l'a démasquée. Le commentaire de
 *    `toastRegion()` explique la règle en citant `role="status"` et `polite` —
 *    si bien que retirer les `setAttribute()` du CODE laissait les assertions
 *    VERTES : elles lisaient la documentation de la règle au lieu de son
 *    application. C'est le troisième objet du dépôt à tomber dans ce piège après
 *    les couleurs et les variables de `test-charte-surfaces.php` (« le test se
 *    fait cautionner par sa propre documentation »), et le seul démasqué par une
 *    mise au rouge plutôt que par une relecture.
 *
 * Les chaînes sont traversées sans être interprétées (une accolade ou un `//`
 * dans un littéral ne compte pas), et leur contenu est CONSERVÉ : c'est là que
 * vivent les valeurs qu'on cherche (`'status'`, `'polite'`).
 *
 * @param string $js  Source complet.
 * @param string $sig Début de signature, ex. 'function toast('.
 * @return string Corps entre accolades, commentaires retirés ; '' si absente.
 */
function aod_chrome_corps_js( $js, $sig ) {
	$p = strpos( $js, $sig );
	if ( false === $p ) {
		return '';
	}
	$open = strpos( $js, '{', $p );
	if ( false === $open ) {
		return '';
	}
	$len  = strlen( $js );
	$prof = 0;
	$dans = '';
	$out  = '';
	for ( $i = $open; $i < $len; $i++ ) {
		$c = $js[ $i ];

		if ( '' !== $dans ) {
			$out .= $c;
			// Échappement : le caractère suivant est littéral, quel qu'il soit.
			if ( '\\' === $c && $i + 1 < $len ) {
				$out .= $js[ $i + 1 ];
				++$i;
				continue;
			}
			if ( $c === $dans ) {
				$dans = '';
			}
			continue;
		}

		if ( '\'' === $c || '"' === $c ) {
			$dans  = $c;
			$out  .= $c;
			continue;
		}

		// Commentaire de fin de ligne — sauté, et NON recopié.
		if ( '/' === $c && $i + 1 < $len && '/' === $js[ $i + 1 ] ) {
			$nl = strpos( $js, "\n", $i );
			if ( false === $nl ) {
				break;
			}
			$i    = $nl;
			$out .= "\n";
			continue;
		}

		// Commentaire de bloc — sauté, et NON recopié. Il peut contenir des
		// accolades (le nôtre en contient) : les compter ferait sortir du corps
		// trop tôt ou jamais.
		if ( '/' === $c && $i + 1 < $len && '*' === $js[ $i + 1 ] ) {
			$fin = strpos( $js, '*/', $i + 2 );
			if ( false === $fin ) {
				break;
			}
			$i    = $fin + 1;
			$out .= ' ';
			continue;
		}

		if ( '{' === $c ) {
			++$prof;
			if ( $prof > 1 ) {
				$out .= $c;
			}
			continue;
		}
		if ( '}' === $c ) {
			--$prof;
			if ( 0 === $prof ) {
				return $out;
			}
			$out .= $c;
			continue;
		}

		$out .= $c;
	}
	return '';
}

AOD_Test::group( 'Chrome 46.3 — R3 : le canal de retour est une région live, et elle préexiste' );

$aod_chrome_js = (string) file_get_contents( AOD_CD_PATH . 'assets/js/dashboard.js' );
AOD_Test::ok(
	strlen( $aod_chrome_js ) > 10000,
	sprintf( 'La coque JS est lue (%d octets)', strlen( $aod_chrome_js ) ),
	'dashboard.js introuvable — tout ce qui suit serait vert par vacuité'
);

$aod_chrome_region = aod_chrome_corps_js( $aod_chrome_js, 'function toastRegion(' );
$aod_chrome_toast  = aod_chrome_corps_js( $aod_chrome_js, 'function toast(' );

AOD_Test::ok(
	'' !== $aod_chrome_region && '' !== $aod_chrome_toast,
	sprintf(
		'TÉMOIN : les deux corps de fonction sont extraits (%d et %d octets)',
		strlen( $aod_chrome_region ),
		strlen( $aod_chrome_toast )
	),
	'extraction vide : le compteur d\'accolades ne reconnaît plus la signature — tout ce qui suit serait vert sans rien avoir lu'
);

foreach ( array( 'role', 'aria-live', 'aria-atomic' ) as $aod_chrome_attr ) {
	AOD_Test::ok(
		false !== strpos( $aod_chrome_region, $aod_chrome_attr ),
		sprintf( 'La région du toast déclare `%s`', $aod_chrome_attr ),
		'sans cet attribut, le retour des 34 handlers AJAX de /gestion reste muet (SC 4.1.3)'
	);
}

AOD_Test::ok(
	false !== strpos( $aod_chrome_region, 'status' ) && false !== strpos( $aod_chrome_region, 'polite' ),
	'La région est `role="status"` + `aria-live="polite"`',
	'`assertive` interromprait la lecture en cours pour annoncer ce que l\'utilisateur vient de déclencher lui-même'
);

/*
 * 🔴 L'ASSERTION CENTRALE. `toast()` n'a plus le droit de fabriquer son nœud :
 * c'est ce qui garantit que le texte entre dans une région DÉJÀ dans l'arbre.
 * Sans elle, un futur « petit correctif » qui recréerait la boîte à chaque
 * message rendrait le toast muet à nouveau, sans qu'aucun attribut ne manque.
 */
$aod_chrome_fabrique = array();
foreach ( array( 'createElement', 'appendChild', 'insertBefore' ) as $aod_chrome_dom ) {
	if ( false !== strpos( $aod_chrome_toast, $aod_chrome_dom ) ) {
		$aod_chrome_fabrique[] = $aod_chrome_dom;
	}
}
AOD_Test::ok(
	empty( $aod_chrome_fabrique ),
	'🔴 `toast()` ne fabrique AUCUN nœud — il écrit dans une région qui existe déjà',
	'`toast()` appelle ' . implode( '/', $aod_chrome_fabrique ) . ' : le texte est posé sur un nœud AVANT son insertion, '
		. 'donc la région live n\'annonce rien. C\'est le défaut de 46.3, reconduit — et invisible à un contrôle qui ne cherche que `role="status"`.'
);

AOD_Test::ok(
	(bool) preg_match( '/init\(\s*[\'"]toastRegion[\'"]/', $aod_chrome_js ),
	'La région est posée au boot (`init( \'toastRegion\' … )`), avant tout binder',
	'créée au premier message, elle rejouerait le défaut : région et texte apparaîtraient dans la même tâche'
);

/*
 * Témoins du matcher, sur les DEUX formes — l'ancienne (fautive) et la nouvelle.
 * Sans eux, un extracteur cassé rendrait `$aod_chrome_fabrique` vide, donc vert.
 */
$aod_chrome_t_vieux = aod_chrome_corps_js(
	"function toast( m, b ) {\n\tvar t = document.createElement( 'div' );\n\tt.textContent = m;\n\tdocument.body.appendChild( t );\n\tif ( b ) { t.className = 'bad'; }\n}",
	'function toast('
);
AOD_Test::ok(
	false !== strpos( $aod_chrome_t_vieux, 'createElement' ) && false !== strpos( $aod_chrome_t_vieux, 'appendChild' ),
	'TÉMOIN : l\'ANCIENNE forme de `toast()` est bien reconnue comme fautive (et le corps est lu au-delà de son premier bloc `if`)'
);

$aod_chrome_t_neuf = aod_chrome_corps_js(
	"function toast( m, b ) {\n\tvar t = toastRegion();\n\tt.textContent = '';\n\tt.textContent = m;\n\tif ( b ) { t.className = 'bad'; }\n}",
	'function toast('
);
AOD_Test::ok(
	'' !== $aod_chrome_t_neuf && false === strpos( $aod_chrome_t_neuf, 'createElement' ),
	'TÉMOIN : la forme conforme passe — le contrôle n\'interdit pas d\'écrire dans la région, seulement d\'en fabriquer une'
);

/*
 * 🔴 LE TÉMOIN QUI A MANQUÉ, ajouté après que la mise au rouge l'ait démasqué.
 *
 * Première version de ce cliquet : `aod_chrome_corps_js()` rendait le corps BRUT,
 * commentaires compris. Or le commentaire de `toastRegion()` CITE `role="status"`
 * et `polite` pour expliquer la règle. Conséquence mesurée en injectant le
 * défaut : les `setAttribute()` supprimés du code, les assertions restaient
 * VERTES — elles lisaient la documentation au lieu de l'application.
 *
 * Ce témoin fixe la correction : un corps où la règle n'existe QUE dans un
 * commentaire ne compte pas comme la portant. C'est le même invariant que
 * `test-charte-surfaces.php` pose pour les variables et les couleurs ; il
 * manquait ici, sur un troisième objet.
 */
$aod_chrome_t_prose = aod_chrome_corps_js(
	"function toastRegion() {\n"
		. "\t/* On pose role=\"status\" + aria-live=\"polite\" parce que sinon rien n'est annoncé. */\n"
		. "\t// idem : aria-atomic=\"true\" est écrit explicitement.\n"
		. "\tvar b = document.createElement( 'div' );\n"
		. "\treturn b;\n}",
	'function toastRegion('
);
AOD_Test::ok(
	'' !== $aod_chrome_t_prose
		&& false === strpos( $aod_chrome_t_prose, 'status' )
		&& false === strpos( $aod_chrome_t_prose, 'polite' )
		&& false === strpos( $aod_chrome_t_prose, 'aria-atomic' ),
	'🔴 TÉMOIN : une règle présente SEULEMENT en commentaire ne compte pas — les deux formes de commentaire sont retirées',
	'le corps extrait contient encore du commentaire : le cliquet se ferait cautionner par sa propre documentation'
);

/*
 * Et le pendant : le contenu des CHAÎNES doit survivre au nettoyage, sinon on
 * retirerait aussi ce qu'on cherche et tout deviendrait « non conforme ».
 */
$aod_chrome_t_chaine = aod_chrome_corps_js(
	"function toastRegion() {\n\tb.setAttribute( 'role', 'status' ); // pose le rôle\n\treturn b;\n}",
	'function toastRegion('
);
AOD_Test::ok(
	false !== strpos( $aod_chrome_t_chaine, 'status' ) && false === strpos( $aod_chrome_t_chaine, 'pose le rôle' ),
	'TÉMOIN : le nettoyage retire le commentaire de fin de ligne et CONSERVE le littéral de la ligne de code'
);
