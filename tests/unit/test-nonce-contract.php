<?php
/**
 * « Le nonce est vérifié par l'appelant » — mais personne ne vérifiait l'appelant.
 * (Phase 24.1, dette D15.)
 *
 * LE DÉFAUT D'ORIGINE. `capi_token_from_post()` existe en deux exemplaires et lit
 * `$_POST` SANS le recevoir en argument. Sa sûreté tenait à une phrase de docblock
 * — « le nonce est vérifié par l'appelant ». C'était vrai, et ça l'est resté par
 * chance : rien n'empêchait un 7ᵉ site d'appel non noncé de créer une CSRF en
 * silence. Un contrat documentaire ne s'exécute pas.
 *
 * CE QUE CE TEST FAIT. Il énumère DYNAMIQUEMENT toute méthode de `plugins-dev/`
 * qui lit `$_POST`/`$_GET`/`$_REQUEST`, et exige de chacune l'une des trois :
 *
 *   1. elle vérifie un nonce elle-même ;
 *   2. elle DÉLÈGUE à une méthode de sa classe qui en vérifie un (résolu) ;
 *   3. elle est DÉCLARÉE ci-dessous, avec une raison écrite.
 *
 * Rien d'autre ne passe. Une méthode nouvelle qui lit une superglobale sans nonce
 * et sans déclaration fait rougir la suite, en nommant fichier et ligne.
 *
 * 🚨 CE QUE CE TEST NE PROUVE PAS — trois limites, à lire avant de s'y fier.
 *
 * (a) Il est STATIQUE. Il lit du code ; il n'enverra jamais une requête forgée.
 *     Il garde un contrat, il ne démontre pas l'absence de CSRF.
 *
 * (b) Il est INDIFFÉRENT À L'ORDRE. Il prouve « un nonce est vérifié dans ce
 *     chemin d'appel », PAS « vérifié avant la première lecture ». Choix assumé :
 *     3 des 5 délégations du dépôt lisent avant d'appeler leur garde
 *     (`ajax_save_member` :347/:348, `ajax_delete_member` :458/:459,
 *     `Account::render_page` :58/:59). Exiger l'ordre ferait rougir 3 méthodes
 *     saines — la lecture n'y est qu'un `absint`/`isset` rangé dans une variable,
 *     et la garde coupe avant toute mutation. Un contrôle qui crie au loup sur du
 *     code sain se fait assouplir dans la semaine, puis ne mord plus sur rien.
 *
 * (c) Sa résolution de délégation est INTRA-CLASSE et bornée en profondeur. Un
 *     nonce vérifié dans une AUTRE classe ne sera pas vu — la méthode devra être
 *     déclarée, avec sa raison.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * LA TABLE DÉCLARATIVE — familles A et B
 * =========================================================================
 *
 * Ce sont les lectures de superglobale qui NE PEUVENT PAS être noncées, ou pour
 * lesquelles le nonce n'a pas de sens. Chaque entrée porte un 'pourquoi' : une
 * exemption sans raison écrite est un échec de revue, pas un test vert.
 *
 * ⚠️ NE JAMAIS y ajouter une méthode qui MUTE quelque chose pour la faire taire.
 * Le cas d'école est `AOD_CD_Account::render_page` : elle lit `$_POST`, ne nonce
 * pas elle-même, et son texte a tout d'une « lecture d'affichage » — mais elle
 * délègue à `handle_create()`, qui appelle `wp_insert_user()`. Elle DOIT passer
 * par la résolution (famille C implicite). Si elle apparaît un jour ici, c'est le
 * résolveur qui est cassé — pas la table qui manque d'une ligne.
 */
function aod_test_nonce_exemptions() {
	return array(

		/* --- A · Contexte public : aucun nonce possible par construction ------ */
		'AOD_COD_Lang::current_choice'      => array(
			'famille'  => 'public',
			'pourquoi' => 'Switcher FR/AR : lit $_GET["aod_lang"] pour un VISITEUR NON CONNECTÉ. Un nonce est impossible sans session — l’exiger casserait le bilinguisme (règle non négociable nº 4).',
		),
		'AOD_COD_Lang::maybe_set_cookie'    => array(
			'famille'  => 'public',
			'pourquoi' => 'Idem, et elle ÉCRIT : setcookie() + redirection. Effet borné au choix de langue du visiteur dans son propre navigateur — aucun état serveur, aucun privilège. Le pire d’une requête forgée est d’afficher le site dans l’autre langue.',
		),
		'AOD_CD_Lang::current_choice'       => array(
			'famille'  => 'public',
			'pourquoi' => 'Jumeau côté /gestion : le cookie aod_lang est partagé public ⇄ gestion, la lecture doit rester possible avant authentification.',
		),
		'AOD_CD_Lang::maybe_set_cookie'     => array(
			'famille'  => 'public',
			'pourquoi' => 'Idem AOD_COD_Lang::maybe_set_cookie — choix de langue, pas d’état serveur.',
		),

		/* --- B · Lecture d'affichage derrière une porte déjà franchie --------- */
		'AOD_CD_Dashboard::render_app'      => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Routeur d’écran : lit la section/les onglets demandés. L’accès à /gestion est déjà gardé en amont (AOD_CD_Access) ; ne mute rien.',
		),
		'AOD_CD_Section_Orders::render'     => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Filtres/pagination de la liste des commandes (ex-AOD_CD_Dashboard::section_orders, extraite en 30.6). Lecture seule, écran déjà authentifié.',
		),
		'AOD_CD_Section_Tracking::render'   => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Filtres de la liste de suivi (ex-AOD_CD_Dashboard::section_tracking, extraite en 30.5). Lecture seule, écran déjà authentifié.',
		),
		'AOD_CD_Section_Finance::produits_mois' => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Mois affiché par « Rentabilité par produit » ($_GET["mois"]). Lecture seule, écran déjà authentifié ; la valeur est validée contre /^\d{4}-(0[1-9]|1[0-2])$/ et retombe sur le mois courant — un POST forgé ne peut au pire que changer la période REGARDÉE.',
		),
		'AOD_CD_Section_Finance::render'    => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Choix de l’outil financier ouvert ($_GET["outil"]). Lecture seule, écran déjà authentifié ; une clé inconnue retombe sur la page de choix plutôt que d’écrire quoi que ce soit.',
		),
		'AOD_CD_Section_Abandoned::render'  => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Filtres des commandes abandonnées. Lecture seule, écran déjà authentifié.',
		),
		'AOD_CD_Section_Home::read_filters' => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Période/onglet des statistiques d’accueil. Lecture seule.',
		),
		'AOD_CD_Dashboard::parse_date_param' => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Helper partagé (Phase 43.1) : lit une borne de date depuis $_GET pour les filtres de Commandes/Suivi/Abandonnées/Home. Reflet exact de l’ancienne closure locale de Home (déjà exemptée ci-dessus) — devenue une méthode nommée en la partageant. Lecture seule, écran déjà authentifié.',
		),
		'AOD_CD_Section_Landing::render'    => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Identifie le produit dont on édite la page de vente. Lecture seule ; l’enregistrement passe par un handler AJAX noncé.',
		),
		'AOD_CD_Section_Products::render'   => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Route liste ⇄ fiche produit. Lecture seule.',
		),
		'AOD_CD_Section_Products::render_product_list' => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Recherche/pagination/catégorie de la liste produits. Lecture seule.',
		),
		'AOD_CD_Section_Stock::read_period' => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Période affichée dans l’écran Stock. Lecture seule.',
		),
		'AOD_COD_Landing::compute_target'   => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Résout le produit ciblé pour le rendu de la page de vente, côté public. Lecture seule ; aucune écriture.',
		),
		'AOD_COD_Leads::render_page'        => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Filtres de la liste des prospects (écran wp-admin, capability vérifiée à l’enregistrement du menu). Lecture seule.',
		),
		'AOD_Shipping::bulk_notice'         => array(
			'famille'  => 'affichage',
			'pourquoi' => 'Lit $_GET APRÈS une redirection admin pour afficher « N colis créés ». Les compteurs passent par absint() et ne servent qu’au texte du bandeau.',
		),
	);
}

/* =========================================================================
 * FAMILLE C — les helpers dont le contrat SE VÉRIFIE au lieu de se déclarer
 * =========================================================================
 *
 * Ces méthodes lisent `$_POST` sans le recevoir en argument. On ne leur accorde
 * pas une exemption sur parole : le test ÉNUMÈRE leurs appelants dans la classe
 * et exige que CHACUN vérifie un nonce. Un 7ᵉ appel depuis une méthode non noncée
 * fait rougir, en nommant fichier et ligne.
 *
 * C'est la fermeture de D15 : le contrat « le nonce est vérifié par l'appelant »
 * devient exécutable.
 */
function aod_test_nonce_helpers() {
	return array(
		'AOD_COD_Pixels::capi_token_from_post',
		'AOD_CD_Section_Marketing::capi_token_from_post',
		// Même contrat que les deux ci-dessus : elle CONVERTIT le POST en nombres
		// et ne décide de rien. `private static`, donc le contrat ne peut pas être
		// contourné depuis l'extérieur — et c'est ce test qui vérifie que son
		// unique appelant, `ajax_finance_save()`, vérifie bien le nonce.
		'AOD_CD_Section_Finance::entrees_du_post',
		'AOD_CD_Section_Products::collect_options',
		'AOD_CD_Section_Products::collect_offers',
		'AOD_CD_Section_Products::save_gallery',
	);
}

/* =========================================================================
 * Analyse statique — parseur par accolades
 * ========================================================================= */

/**
 * Découpe un fichier PHP en méthodes : nom => [ corps, ligne de déclaration ].
 * On travaille sur le TEXTE (pas de tokenizer) : le dépôt n'a ni Composer ni
 * réseau, et `token_get_all` suffirait mais compliquerait la lecture du test.
 */
function aod_test_nonce_methodes( $chemin ) {
	$lignes = explode( "\n", (string) file_get_contents( $chemin ) );
	$out    = array();
	$cur    = null;
	$corps  = array();
	$debut  = 0;
	$prof   = 0;

	foreach ( $lignes as $i => $l ) {
		if ( null === $cur && preg_match( '/^\s*(?:(?:public|protected|private|static|final|abstract)\s+)*function\s+(\w+)\s*\(/', $l, $m ) ) {
			$cur   = $m[1];
			$debut = $i + 1;
			$corps = array();
			$prof  = 0;
		}
		if ( null === $cur ) {
			continue;
		}
		$corps[] = $l;
		$prof   += substr_count( $l, '{' ) - substr_count( $l, '}' );
		$joint   = implode( "\n", $corps );
		if ( $prof <= 0 && false !== strpos( $joint, '{' ) ) {
			$out[ $cur ] = array( $joint, $debut );
			$cur         = null;
		}
	}
	return $out;
}

/** Nom de classe déclaré dans un fichier (le premier). */
function aod_test_nonce_classe( $chemin ) {
	if ( preg_match( '/^\s*(?:final\s+|abstract\s+)*class\s+(\w+)/m', (string) file_get_contents( $chemin ), $m ) ) {
		return $m[1];
	}
	return '';
}

/** Le corps vérifie-t-il un nonce lui-même ? */
function aod_test_nonce_verifie( $corps ) {
	return (bool) preg_match( '/\b(check_ajax_referer|check_admin_referer|wp_verify_nonce)\s*\(/', $corps );
}

/**
 * Le nonce est-il vérifié dans le chemin d'appel — soit ici, soit via une méthode
 * de la MÊME classe appelée depuis ce corps (profondeur bornée, anti-cycle).
 *
 * ⚠️ La détection d'appel ne regarde PAS l'imbrication : un `$this->guard()` posé
 * dans un `if` compte autant qu'un appel en première instruction. C'est
 * délibéré — `AOD_CD_Account::render_page` délègue précisément ainsi, et un
 * résolveur qui ne verrait que les appels de premier niveau la ferait rougir à
 * tort, puis on la déclarerait « lecture d’affichage »… alors qu’elle crée un
 * utilisateur. Cf. l’avertissement de la table déclarative.
 */
function aod_test_nonce_garde( $methodes, $nom, $vus = array(), $prof = 0 ) {
	if ( $prof > 3 || in_array( $nom, $vus, true ) || ! isset( $methodes[ $nom ] ) ) {
		return false;
	}
	$vus[]  = $nom;
	$corps  = $methodes[ $nom ][0];
	if ( aod_test_nonce_verifie( $corps ) ) {
		return true;
	}
	preg_match_all( '/(?:\$this->|self::|static::)(\w+)\s*\(/', $corps, $m );
	foreach ( array_unique( $m[1] ) as $appelee ) {
		if ( $appelee !== $nom && aod_test_nonce_garde( $methodes, $appelee, $vus, $prof + 1 ) ) {
			return true;
		}
	}
	return false;
}

/* =========================================================================
 * Le test
 * ========================================================================= */

AOD_Test::group( 'Contrat de nonce sur les lectures de superglobale (D15)' );

/*
 * Racine = les plugins TELS QUE MONTÉS DANS LE CONTENEUR (`AOD_CD_PATH` /
 * `AOD_COD_PATH`), jamais un chemin relatif au fichier de test : la suite tourne
 * via `wp eval-file` dans WordPress, où `__DIR__` ne pointe pas sur le dépôt.
 * Même résolution que `tests/unit/test-phpcs-ignore.php`.
 * Balayage RÉCURSIF — une liste de fichiers laisserait hors champ celui créé
 * demain (leçon de la 18.1).
 */
$aod_nc_cod      = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : dirname( AOD_CD_PATH ) . '/aod-cod-form/';
$aod_nc_fichiers = array();
foreach ( array( AOD_CD_PATH, $aod_nc_cod ) as $aod_nc_racine ) {
	if ( ! is_dir( $aod_nc_racine ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $aod_nc_racine, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
			$aod_nc_fichiers[] = $f->getPathname();
		}
	}
}
sort( $aod_nc_fichiers );

/* --- Témoin de balayage (DoD §C.2) : un glob vide doit ROUGIR, pas verdir. --- */
AOD_Test::ok(
	count( $aod_nc_fichiers ) >= 30,
	sprintf( 'Témoin — %d fichiers PHP balayés dans plugins-dev/ (seuil 30)', count( $aod_nc_fichiers ) )
);

$aod_nc_sup       = '/\$_(POST|GET|REQUEST)\b/';
$aod_nc_exempt    = aod_test_nonce_exemptions();
$aod_nc_helpers   = aod_test_nonce_helpers();
$aod_nc_lecteurs  = 0;
$aod_nc_noncees   = 0;
$aod_nc_resolues  = 0;
$aod_nc_declarees = 0;
$aod_nc_orphelins = array();
$aod_nc_vues      = array();
/** Index global : "Classe::methode" => [ fichier, methodes de la classe ]. */
$aod_nc_index     = array();

foreach ( $aod_nc_fichiers as $chemin ) {
	$classe = aod_test_nonce_classe( $chemin );
	if ( '' === $classe ) {
		continue;
	}
	$methodes = aod_test_nonce_methodes( $chemin );
	foreach ( $methodes as $nom => $info ) {
		$aod_nc_index[ $classe . '::' . $nom ] = array( $chemin, $methodes );
	}
	foreach ( $methodes as $nom => $info ) {
		list( $corps, $ligne ) = $info;
		if ( ! preg_match( $aod_nc_sup, $corps ) ) {
			continue;
		}
		++$aod_nc_lecteurs;
		$cle = $classe . '::' . $nom;

		if ( aod_test_nonce_verifie( $corps ) ) {
			++$aod_nc_noncees;
			continue;
		}
		if ( aod_test_nonce_garde( $methodes, $nom ) ) {
			++$aod_nc_resolues;
			continue;
		}
		if ( in_array( $cle, $aod_nc_helpers, true ) ) {
			continue; // Famille C : vérifiée plus bas, par ses appelants.
		}
		if ( isset( $aod_nc_exempt[ $cle ] ) ) {
			++$aod_nc_declarees;
			$aod_nc_vues[] = $cle;
			continue;
		}
		$aod_nc_orphelins[] = sprintf( '%s (%s:%d)', $cle, basename( $chemin ), $ligne );
	}
}

AOD_Test::ok(
	$aod_nc_lecteurs >= 40,
	sprintf( 'Témoin — %d méthodes lisant une superglobale (dont %d noncées, %d résolues par délégation, %d déclarées)', $aod_nc_lecteurs, $aod_nc_noncees, $aod_nc_resolues, $aod_nc_declarees )
);

/* --- R1 · Aucune lecture orpheline -------------------------------------- */
AOD_Test::ok(
	empty( $aod_nc_orphelins ),
	empty( $aod_nc_orphelins )
		? 'R1 — aucune lecture de superglobale sans nonce, sans délégation et sans raison déclarée'
		: sprintf(
			"R1 — 🔒 %d lecture(s) de superglobale NON GARDÉE(S) et NON DÉCLARÉE(S) : %s\n"
			. "      → soit la méthode vérifie un nonce (ou délègue à une méthode de sa classe qui le fait),\n"
			. "        soit elle rejoint la table de tests/unit/test-nonce-contract.php AVEC SA RAISON.\n"
			. '      ⚠️ Ne l’y ajoute PAS si elle mute quoi que ce soit — corrige la garde.',
			count( $aod_nc_orphelins ),
			implode( ', ', $aod_nc_orphelins )
		)
);

/* --- R2 · La table ne pourrit pas : pas d'entrée qui ne couvre plus rien -- */
$aod_nc_mortes = array_diff( array_keys( $aod_nc_exempt ), $aod_nc_vues );
AOD_Test::ok(
	empty( $aod_nc_mortes ),
	empty( $aod_nc_mortes )
		? sprintf( 'R2 — les %d exemptions déclarées couvrent toutes une méthode réelle', count( $aod_nc_exempt ) )
		: sprintf( 'R2 — %d exemption(s) ne couvrent plus rien (méthode disparue ou désormais noncée) : %s — à retirer', count( $aod_nc_mortes ), implode( ', ', $aod_nc_mortes ) )
);

/* --- R3 · Toute exemption porte une raison écrite ------------------------ */
$aod_nc_sans_raison = array();
foreach ( $aod_nc_exempt as $cle => $e ) {
	if ( empty( $e['pourquoi'] ) || strlen( $e['pourquoi'] ) < 40 ) {
		$aod_nc_sans_raison[] = $cle;
	}
}
AOD_Test::ok(
	empty( $aod_nc_sans_raison ),
	empty( $aod_nc_sans_raison )
		? 'R3 — chaque exemption porte une raison écrite (≥ 40 caractères)'
		: sprintf( 'R3 — exemption(s) sans raison exploitable : %s', implode( ', ', $aod_nc_sans_raison ) )
);

/* --- R4 · FAMILLE C : les appelants du helper sont TOUS noncés ----------- */
/*        C'est la fermeture de D15 : le contrat devient exécutable.        */
foreach ( $aod_nc_helpers as $cle ) {
	list( $classe, $nom ) = explode( '::', $cle );

	if ( ! isset( $aod_nc_index[ $cle ] ) ) {
		AOD_Test::ok( false, sprintf( 'R4 — helper déclaré introuvable : %s (renommé ou supprimé ? mettre la liste à jour)', $cle ) );
		continue;
	}
	list( $chemin, $methodes ) = $aod_nc_index[ $cle ];

	$appelants   = array();
	$non_noncees = array();
	foreach ( $methodes as $m_nom => $m_info ) {
		if ( $m_nom === $nom ) {
			continue;
		}
		if ( ! preg_match( '/(?:\$this->|self::|static::)' . preg_quote( $nom, '/' ) . '\s*\(/', $m_info[0] ) ) {
			continue;
		}
		$appelants[] = $m_nom;
		if ( ! aod_test_nonce_garde( $methodes, $m_nom ) ) {
			$non_noncees[] = sprintf( '%s() (%s:%d)', $m_nom, basename( $chemin ), $m_info[1] );
		}
	}

	AOD_Test::ok(
		! empty( $appelants ),
		sprintf( 'R4 — %s : %d appelant(s) trouvé(s) dans la classe%s', $cle, count( $appelants ), $appelants ? ' [' . implode( ', ', $appelants ) . ']' : ' — AUCUN : helper mort, ou détection cassée' )
	);
	AOD_Test::ok(
		empty( $non_noncees ),
		empty( $non_noncees )
			? sprintf( 'R4 — %s : tous ses appelants vérifient un nonce', $cle )
			: sprintf( 'R4 — 🔒 %s est appelé sans nonce depuis : %s — CSRF potentielle, corriger la garde de l’appelant', $cle, implode( ', ', $non_noncees ) )
	);
}

/* --- R5 · Le durcissement de visibilité est GARDÉ ------------------------ */
/*        Sans ceci, repasser un helper en `public` est une régression       */
/*        silencieuse et VERTE — le défaut même que cette phase corrige.     */
foreach ( array( 'AOD_COD_Pixels', 'AOD_CD_Section_Marketing' ) as $aod_nc_cls ) {
	if ( ! class_exists( $aod_nc_cls ) ) {
		AOD_Test::ok( false, sprintf( 'R5 — classe %s introuvable (plugin inactif ?)', $aod_nc_cls ) );
		continue;
	}
	try {
		$aod_nc_ref = new ReflectionMethod( $aod_nc_cls, 'capi_token_from_post' );
		AOD_Test::ok(
			$aod_nc_ref->isPrivate(),
			sprintf(
				'R5 — %s::capi_token_from_post() est private (%s)',
				$aod_nc_cls,
				$aod_nc_ref->isPrivate() ? 'contrat structurel' : '🔒 VISIBILITÉ ROUVERTE : la méthode lit $_POST sans le recevoir ; hors de sa classe, plus rien ne garantit le nonce'
			)
		);
	} catch ( ReflectionException $e ) {
		AOD_Test::ok( false, sprintf( 'R5 — %s::capi_token_from_post() introuvable (renommée ?)', $aod_nc_cls ) );
	}
}
