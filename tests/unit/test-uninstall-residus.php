<?php
/**
 * Cliquet sur la désinstallation — ce que les plugins CRÉENT doit être supprimé,
 * couvert par un motif, ou déclaré ici AVEC SA RAISON.
 *
 * CE QUE CE TEST REMPLACE. Les deux `uninstall.php` annoncent une « désinstallation
 * propre » et énumèrent les résidus dans des LISTES ÉCRITES À LA MAIN. Une liste en
 * dur dérive dès qu'un résidu naît ailleurs, et rien ne le signale : une option
 * oubliée n'émet aucune erreur, elle reste simplement en base. **Un défaut qui ne
 * casse rien ne se voit pas — il se lit.**
 *
 * 🚨 CE QUE L'AUDIT AVAIT RATÉ, ET POURQUOI. La première version de l'étape 25.1
 * s'intitulait « options orphelines » : elle a audité `wp_options`, y a trouvé deux
 * manques réels, et déclaré la désinstallation propre. **Le titre avait servi
 * d'œillère.** Le résidu le plus grave était ailleurs — les 8 capabilities posées
 * PAR UTILISATEUR (`WP_User::add_cap()`), que ni `remove_role()` ni une boucle sur
 * les rôles ne retire. Des droits qu'on croit révoqués et qui ne le sont pas :
 * exactement l'argument de la story, dans ce qu'elle n'avait pas regardé.
 * → Leçon gravée ici : **on cadre un audit par ce que le code CRÉE, jamais par la
 *   famille de stockage qu'on a en tête.** D'où les trois familles ci-dessous.
 *
 * LE CONTRAT, en cinq règles mécaniques :
 *
 *   R1 · Toute OPTION `aod*` découverte est supprimée par un `uninstall.php`
 *        (littéral ou motif `LIKE`), ou déclarée ici avec sa raison.
 *   R2 · Aucun `uninstall.php` ne supprime les options d'un AUTRE plugin — le
 *        dashboard n'est qu'un éditeur des réglages `aod_cod_*`, les effacer
 *        casserait le COD s'il reste actif.
 *   R3 · Toute CAPABILITY `aod_*` découverte est retirée par l'uninstall du
 *        dashboard, qui la pose.
 *   R4 · Tout PRÉFIXE DE TRANSIENT `aod*` découvert est couvert par un motif `LIKE`
 *        de l'uninstall de son plugin.
 *   R5 · TÉMOIN : le balayage publie ce qu'il a réellement vu. Un balayage vide
 *        doit ROUGIR, jamais verdir.
 *
 * ⚠️ POURQUOI R5 EXISTE. En 24.1, un cliquet balayait `dirname(dirname(__DIR__))`
 * — juste dans le dépôt, faux dans le conteneur où les tests tournent réellement.
 * Il a rendu « 0 fichier balayé » et **tout est passé au vert sur du vide**. Seul le
 * témoin l'a vu. Un contrôle qui ne publie pas son périmètre ne prouve rien.
 *
 * ⚠️ LA LIMITE, écrite ici et pas seulement dans la story. La PROPRIÉTÉ d'une
 * option n'a **aucun signal mécanique** dans ce dépôt : `aod_seq_order_counter` est
 * ÉCRITE par le dashboard sans lui appartenir (elle est au mu-plugin), et les
 * `aod_cod_*` sont écrites par le dashboard en appartenant au COD. « Qui écrit
 * possède » est donc faux dans les deux sens. La propriété reste une DÉCLARATION
 * HUMAINE MOTIVÉE, comme en 21.1 et 24.1.
 * → Ce cliquet détecte le **non-déclaré**, pas le **mal-déclaré**. Une option
 *   rangée dans la mauvaise case verdira avec l'autorité d'un test. Le seul
 *   rempart est la raison écrite, qu'un humain doit relire.
 *
 * ⚠️ Test STATIQUE : il lit les fichiers, il ne désinstalle rien. Il prouve qu'une
 * ligne de suppression existe, pas qu'elle s'exécute sans erreur.
 *
 * ⚠️ ANGLE MORT ASSUMÉ — les noms d'OPTIONS entièrement construits. `aod_carrier_*`
 * n'existe nulle part en littéral : `AOD_Carrier::option_key()` la fabrique par
 * concaténation. Ce cliquet ne la découvre donc pas, et ne peut pas garantir sa
 * couverture (elle l'est, par un motif `LIKE`, mais c'est le lecteur qui le sait,
 * pas le test). Un futur plugin qui nommerait ses options ainsi passerait sous le
 * radar. Les CAPS et les TRANSIENTS n'ont pas ce trou : leurs préfixes sont
 * capturés (cf. aod_test_uni_transients).
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Déclarations — tout ce qui N'EST PAS supprimé doit avoir sa raison ici
 * ========================================================================= */

/**
 * Résidus volontairement NON supprimés, avec le motif de la décision.
 *
 * 🚨 N'AJOUTEZ JAMAIS une entrée ici pour faire taire un rouge sans avoir établi
 * que la conservation est un CHOIX. Une déclaration est une affirmation de
 * sécurité gravée dans le dépôt : mal posée, elle est pire que l'absence de test.
 *
 * ⚠️ Préfixe `aod_test_uni_` : `tests/all.php` charge tous les fichiers par
 * `glob()` dans le même espace global.
 *
 * @return array<string,string> nom du résidu => raison
 */
function aod_test_uni_declarations() {
	return array(
		'aod_cod_leads_db_version' =>
			'CONDITIONNELLE À DESSEIN. Supprimée seulement si $aod_cod_drop_leads = true. '
			. 'En mode prudent la table des prospects est conservée (donnée commerciale) ; '
			. 'effacer le marqueur de version en gardant la table ferait croire à un schéma '
			. 'neuf sur des données existantes, à la réinstallation.',
		'aod_seq_order_counter'    =>
			'PAS À NOUS. Appartient au mu-plugin wordpress/wp-content/mu-plugins/'
			. 'aod-sequential-order-numbers.php (const AOD_SEQ_OPTION). Le dashboard ne fait '
			. 'que la remettre à zéro lors d’une réinitialisation de boutique. La supprimer '
			. 'détruirait la numérotation d’un plugin qui SURVIT aux nôtres. '
			. '🚨 C’est le contre-exemple qui interdit la règle « qui écrit possède ».',
	);
}

/**
 * Options de tiers que les plugins LISENT sans les posséder — hors périmètre.
 * Les supprimer serait un défaut plus grave que celui que ce cliquet corrige.
 *
 * @return string[]
 */
function aod_test_uni_tierces() {
	return array( 'admin_email', 'date_format' );
}

/* =========================================================================
 * Balayage — récursif, jamais une liste de fichiers en dur (leçon 18.1)
 * ========================================================================= */

/**
 * Racines réelles des deux plugins. On passe par les constantes définies par les
 * plugins eux-mêmes : les tests tournent DANS le conteneur, où `__DIR__` ne
 * désigne pas le dépôt de l'hôte (leçon 24.1).
 *
 * @return array<string,string> slug => chemin
 */
function aod_test_uni_racines() {
	$cod = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : dirname( AOD_CD_PATH ) . '/aod-cod-form/';
	return array(
		'aod-client-dashboard' => AOD_CD_PATH,
		'aod-cod-form'         => $cod,
	);
}

/**
 * Tous les fichiers PHP d'une racine, hors `uninstall.php` (qui est le SUJET du
 * contrôle, pas une source de création).
 *
 * @param string $racine Chemin.
 * @return string[]
 */
function aod_test_uni_fichiers( $racine ) {
	if ( ! is_dir( $racine ) ) {
		return array();
	}
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $racine, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) && 'uninstall.php' !== $f->getFilename() ) {
			$out[] = $f->getPathname();
		}
	}
	sort( $out );
	return $out;
}

/**
 * Options `aod*` créées par un plugin. Deux sources, parce qu'une seule ne suffit
 * pas : les littéraux passés à *_option(), ET les constantes de classe qui portent
 * un nom d'option (le dépôt fait surtout ça).
 *
 * @param string[] $fichiers Chemins.
 * @return string[]
 */
function aod_test_uni_options( $fichiers ) {
	$out = array();
	foreach ( $fichiers as $f ) {
		$src = (string) file_get_contents( $f );

		// a) Littéraux : get_option( 'aod_x' ), update_option( "aod_x", … )…
		if ( preg_match_all( '/\b(?:get|update|add|delete)_option\(\s*[\'"](aod[a-z_0-9]*)[\'"]/i', $src, $m ) ) {
			$out = array_merge( $out, $m[1] );
		}
		// b) Constantes de classe dont la valeur ressemble à un nom d'option.
		//    On ne retient que celles dont le nom de constante dit « option ».
		if ( preg_match_all( '/const\s+([A-Z_0-9]*OPTION[A-Z_0-9]*)\s*=\s*[\'"](aod[a-z_0-9]*)[\'"]/', $src, $m ) ) {
			$out = array_merge( $out, $m[2] );
		}
	}
	$out = array_values( array_unique( $out ) );
	sort( $out );
	return $out;
}

/**
 * Capabilities `aod_*` posées par le code (rôle ou utilisateur).
 *
 * @param string[] $fichiers Chemins.
 * @return string[]
 */
function aod_test_uni_caps( $fichiers ) {
	$out = array();
	foreach ( $fichiers as $f ) {
		if ( preg_match_all( '/[\'"](aod_cap_[a-z_0-9]+|aod_use_dashboard)[\'"]/', (string) file_get_contents( $f ), $m ) ) {
			$out = array_merge( $out, $m[1] );
		}
	}
	$out = array_values( array_unique( $out ) );
	sort( $out );
	return $out;
}

/**
 * Préfixes de transients `aod*` — la clé est concaténée à un hash, donc on capture
 * le littéral qui précède le point de concaténation.
 *
 * @param string[] $fichiers Chemins.
 * @return string[]
 */
function aod_test_uni_transients( $fichiers ) {
	$out = array();
	foreach ( $fichiers as $f ) {
		$src = (string) file_get_contents( $f );
		// set_transient( $key, … ) où $key = 'aod_x_' . md5(…) — on prend l'affectation.
		if ( preg_match_all( '/=\s*[\'"](aod[a-z_0-9]*_)[\'"]\s*\.\s*md5/i', $src, $m ) ) {
			$out = array_merge( $out, $m[1] );
		}
		// set_transient( 'aod_x_' . … ) en direct.
		if ( preg_match_all( '/\bset_transient\(\s*[\'"](aod[a-z_0-9]*_)[\'"]\s*\./i', $src, $m ) ) {
			$out = array_merge( $out, $m[1] );
		}
	}
	$out = array_values( array_unique( $out ) );
	sort( $out );
	return $out;
}

/**
 * Un nom est-il couvert par le contenu d'un `uninstall.php` — littéralement, ou
 * par un motif `LIKE 'prefixe%'` ?
 *
 * ⚠️ La couverture par motif est INDISPENSABLE : `aod_carrier_yalidine` n'existe
 * nulle part en littéral, elle est construite par AOD_Carrier::option_key(). Un
 * cliquet qui exigerait le littéral crierait au loup sur du code sain — et se
 * ferait desserrer dans la semaine.
 *
 * @param string $nom      Nom du résidu (option, cap) ou préfixe de transient.
 * @param string $uninst   Contenu de l'uninstall.php.
 * @param string $prefixe  Préfixe à ajouter aux motifs (ex. '_transient_').
 * @return bool
 */
function aod_test_uni_couvert( $nom, $uninst, $prefixe = '' ) {
	if ( '' === $prefixe && false !== strpos( $uninst, "'" . $nom . "'" ) ) {
		return true;
	}
	$attendu = $prefixe . $nom;
	foreach ( aod_test_uni_motifs( $uninst ) as $motif ) {
		if ( '' !== $motif && 0 === strpos( $attendu, $motif ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Motifs de SUPPRESSION par préfixe d'un `uninstall.php` — et eux seuls.
 *
 * 🚨 DEUX FORMES, et n'en lire qu'une rend le cliquet FAUX. Le dashboard écrit son
 * motif en clair (`LIKE '_transient_aod_cd_%'`), le plugin COD le construit —
 * `$wpdb->esc_like( '_transient_aod_' ) . '%'` — et le passe en placeholder `%s`,
 * via une variable assignée quelques lignes plus haut. La première version de ce
 * test ne lisait que la forme littérale : elle a fait rougir 5 préfixes de
 * transients PARFAITEMENT couverts. C'est le mode d'échec annoncé en garde dans
 * l'en-tête, survenu dès la première exécution — un contrôle qui crie au loup sur
 * du code sain se fait desserrer dans la semaine, et ne mord plus jamais.
 *
 * 🚨🚨 ET SURTOUT — POURQUOI ON FILTRE SUR `DELETE FROM …options`. La deuxième
 * version, elle, moissonnait TOUT `LIKE` du fichier sans regarder à quoi il sert.
 * Or l'étape 25.1 a introduit dans le dashboard un `SELECT … FROM usermeta WHERE
 * meta_value LIKE '%' . esc_like( 'aod_' ) . '%'` — pour énumérer les porteurs
 * d'une capability. Le motif `aod_` en est ressorti comme une « couverture », et
 * comme il préfixe TOUTE option du dépôt, la règle R1 est devenue
 * **universellement verte**.
 * → Autrement dit : **le correctif de production de cette story a désarmé en
 *   silence le cliquet de cette story.** Détecté uniquement parce que la preuve au
 *   rouge (AC10) a refusé de rougir. Un cliquet qu'on n'a jamais vu échouer ne
 *   prouve rien — c'est la leçon, et elle a failli coûter la story entière.
 *
 * On ne retient donc que les motifs apparaissant dans une instruction qui
 * SUPPRIME dans la table des options. Un `LIKE` de lecture n'est pas une preuve
 * de nettoyage.
 *
 * @param string $uninst Contenu du fichier.
 * @return string[]
 */
function aod_test_uni_motifs( $uninst ) {
	$out = array();

	// Découpe en instructions ; on ne garde que les DELETE sur la table options.
	foreach ( explode( ';', $uninst ) as $stmt ) {
		if ( ! preg_match( '/DELETE\s+FROM/i', $stmt ) || ! preg_match( '/\boptions\b/i', $stmt ) ) {
			continue;
		}

		// Forme A — motif écrit en clair : LIKE '_transient_aod_cd_%'
		if ( preg_match_all( '/LIKE\s+[\'"]([^\'"]*)%[\'"]/i', $stmt, $m ) ) {
			$out = array_merge( $out, $m[1] );
		}

		// Forme B — motif passé par variable : LIKE %s … , $like_tr
		//   L'assignation vit hors de l'instruction : on la retrouve dans le fichier.
		if ( preg_match_all( '/\$(\w+)/', $stmt, $vars ) ) {
			foreach ( array_unique( $vars[1] ) as $var ) {
				$re = '/\$' . preg_quote( $var, '/' ) . '\s*=\s*[^;]*esc_like\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*\.\s*[\'"]%[\'"]/';
				if ( preg_match( $re, $uninst, $mv ) ) {
					$out[] = $mv[1];
				}
			}
		}

		// Forme C — motif construit dans l'instruction elle-même.
		if ( preg_match_all( '/esc_like\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*\.\s*[\'"]%[\'"]/i', $stmt, $m ) ) {
			$out = array_merge( $out, $m[1] );
		}
	}

	return array_values( array_unique( $out ) );
}

/* =========================================================================
 * Exécution
 * ========================================================================= */

$aod_uni_racines = aod_test_uni_racines();
$aod_uni_scan    = array();
$aod_uni_nb_f    = 0;

foreach ( $aod_uni_racines as $aod_uni_slug => $aod_uni_path ) {
	$aod_uni_files = aod_test_uni_fichiers( $aod_uni_path );
	$aod_uni_nb_f += count( $aod_uni_files );

	$aod_uni_scan[ $aod_uni_slug ] = array(
		'uninstall'  => is_readable( $aod_uni_path . 'uninstall.php' )
			? (string) file_get_contents( $aod_uni_path . 'uninstall.php' )
			: '',
		'options'    => aod_test_uni_options( $aod_uni_files ),
		'caps'       => aod_test_uni_caps( $aod_uni_files ),
		'transients' => aod_test_uni_transients( $aod_uni_files ),
	);
}

$aod_uni_tous_uninst = '';
foreach ( $aod_uni_scan as $aod_uni_d ) {
	$aod_uni_tous_uninst .= "\n" . $aod_uni_d['uninstall'];
}

/* ------------------------------------------------------------------ R5 : témoin */

AOD_Test::group( 'Désinstallation 25.1 — R5 : le témoin (ce que le balayage a VU)' );

AOD_Test::ok(
	$aod_uni_nb_f > 0,
	sprintf( 'Balayage non vide : %d fichiers PHP dans %d plugins', $aod_uni_nb_f, count( $aod_uni_racines ) ),
	'0 fichier balayé = toutes les règles ci-dessous verdissent sur du VIDE. '
		. 'C’est exactement le mode d’échec de la 24.1 : les tests tournent dans le conteneur, '
		. 'où __DIR__ ne désigne pas le dépôt. Vérifier AOD_CD_PATH / AOD_COD_PATH.'
);

foreach ( $aod_uni_scan as $aod_uni_slug => $aod_uni_d ) {
	AOD_Test::ok(
		'' !== $aod_uni_d['uninstall'],
		sprintf( '%s : uninstall.php lu (%d octets)', $aod_uni_slug, strlen( $aod_uni_d['uninstall'] ) ),
		'Sans le fichier à contrôler, le contrôle n’a pas de sujet.'
	);
	AOD_Test::ok(
		count( $aod_uni_d['options'] ) > 0,
		sprintf(
			'%s : %d option(s), %d capability(ies), %d préfixe(s) de transient trouvés',
			$aod_uni_slug,
			count( $aod_uni_d['options'] ),
			count( $aod_uni_d['caps'] ),
			count( $aod_uni_d['transients'] )
		),
		'Aucune option trouvée dans un plugin qui en pose : le motif de détection a cassé.'
	);
}

/* ------------------------------------------------- R1 : options supprimées ou déclarées */

AOD_Test::group( 'Désinstallation 25.1 — R1 : toute option est supprimée, couverte ou déclarée' );

$aod_uni_decl   = aod_test_uni_declarations();
$aod_uni_tierce = aod_test_uni_tierces();
$aod_uni_vues   = array();

foreach ( $aod_uni_scan as $aod_uni_slug => $aod_uni_d ) {
	foreach ( $aod_uni_d['options'] as $aod_uni_opt ) {
		if ( in_array( $aod_uni_opt, $aod_uni_tierce, true ) || isset( $aod_uni_vues[ $aod_uni_opt ] ) ) {
			continue;
		}
		$aod_uni_vues[ $aod_uni_opt ] = true;

		$aod_uni_sup = aod_test_uni_couvert( $aod_uni_opt, $aod_uni_tous_uninst );
		$aod_uni_dit = isset( $aod_uni_decl[ $aod_uni_opt ] );

		AOD_Test::ok(
			$aod_uni_sup || $aod_uni_dit,
			sprintf(
				'%s → %s',
				$aod_uni_opt,
				$aod_uni_sup ? 'supprimée à la désinstallation' : 'conservée À DESSEIN (déclarée)'
			),
			sprintf(
				'L’option « %s » n’est supprimée par aucun uninstall.php et n’est pas déclarée. '
					. 'Soit on l’ajoute à la liste du plugin qui la possède, soit on écrit POURQUOI '
					. 'elle survit dans aod_test_uni_declarations(). Une option orpheline reste en '
					. 'base indéfiniment, sans que rien ne le signale.',
				$aod_uni_opt
			)
		);
	}
}

// Une option supprimée ET déclarée serait une contradiction : la déclaration
// affirme une conservation que le code contredit.
foreach ( $aod_uni_decl as $aod_uni_nom => $aod_uni_pourquoi ) {
	AOD_Test::ok(
		'' !== trim( $aod_uni_pourquoi ),
		sprintf( 'Déclaration « %s » : la raison est écrite', $aod_uni_nom ),
		'Une déclaration sans raison est un silence déguisé en décision.'
	);
}

/* ---------------------------------------- R2 : pas de suppression cross-plugin */

AOD_Test::group( 'Désinstallation 25.1 — R2 : aucun plugin ne supprime les options d’un autre' );

AOD_Test::ok(
	false === strpos( $aod_uni_scan['aod-client-dashboard']['uninstall'], "'aod_cod_" ),
	'Le dashboard ne supprime aucune option « aod_cod_* »',
	'Le dashboard n’est qu’un ÉDITEUR front-end des réglages Pixels/WhatsApp : ils '
		. 'appartiennent au plugin COD. Les effacer casserait sa configuration alors '
		. 'qu’il reste actif. La décision est écrite en tête de son uninstall.php.'
);

/* -------------------------------------------- R3 : capabilities par utilisateur */

AOD_Test::group( 'Désinstallation 25.1 — R3 : toute capability est retirée (le défaut de la v1)' );

$aod_uni_cd_uninst = $aod_uni_scan['aod-client-dashboard']['uninstall'];
$aod_uni_caps_all  = array_values( array_unique( array_merge(
	$aod_uni_scan['aod-client-dashboard']['caps'],
	$aod_uni_scan['aod-cod-form']['caps']
) ) );
sort( $aod_uni_caps_all );

AOD_Test::ok(
	count( $aod_uni_caps_all ) >= 8,
	sprintf( 'Témoin : %d capabilities « aod_* » découvertes dans le code', count( $aod_uni_caps_all ) ),
	'Moins de 8 : le motif de détection des capabilities a cassé, et les assertions '
		. 'ci-dessous ne portent plus sur rien.'
);

foreach ( $aod_uni_caps_all as $aod_uni_cap ) {
	AOD_Test::ok(
		false !== strpos( $aod_uni_cd_uninst, "'" . $aod_uni_cap . "'" ),
		sprintf( '%s → retirée à la désinstallation', $aod_uni_cap ),
		sprintf(
			'🔴 La capability « %s » n’est retirée par aucun uninstall.php. Les 7 caps du '
				. 'confirmateur sont posées PAR UTILISATEUR (WP_User::add_cap) : ni remove_role() '
				. 'ni une boucle sur les rôles ne les enlève. Elles survivraient dans le usermeta '
				. '{prefix}capabilities de chaque compte — un droit qu’on croit révoqué et qui ne '
				. 'l’est pas. C’est le défaut exact que l’étape 25.1 a fermé.',
			$aod_uni_cap
		)
	);
}

AOD_Test::ok(
	false !== strpos( $aod_uni_cd_uninst, 'remove_cap' )
		&& false !== strpos( $aod_uni_cd_uninst, 'usermeta' ),
	'Le nettoyage vise les UTILISATEURS, pas seulement les rôles',
	'Nommer les capabilities ne suffit pas : il faut les retirer des comptes qui les '
		. 'portent. Sans requête sur le usermeta, la boucle sur $roles->role_objects ne '
		. 'trouve rien — les caps « à la carte » ne sont jamais posées sur un rôle.'
);

/* ------------------------------------------------------ R4 : préfixes de transients */

AOD_Test::group( 'Désinstallation 25.1 — R4 : les caches sont couverts par un motif' );

/*
 * 🚨 CHAQUE PLUGIN EST JUGÉ SUR SON PROPRE uninstall.php — pas sur l'union.
 *
 * Contrairement aux OPTIONS (R1), dont la propriété est croisée à dessein — le
 * dashboard ÉDITE les réglages `aod_cod_*` que le COD POSSÈDE, et R2 lui interdit
 * de les supprimer —, un plugin possède ses propres caches. Les juger sur l'union
 * rendait la règle aveugle : le motif large du COD (`_transient_aod_%`) recouvre
 * les transients du dashboard, et masquait donc la dérive `aod_cd_stock_` que
 * cette étape corrige. Vérifié : la preuve au rouge refusait de rougir tant que
 * R4 lisait l'union.
 * → Une couverture qui ne tient que si l'AUTRE plugin est supprimé aussi n'est pas
 *   une couverture. Supprimer le dashboard seul laissait ses caches en base.
 */
foreach ( $aod_uni_scan as $aod_uni_slug => $aod_uni_d ) {
	foreach ( $aod_uni_d['transients'] as $aod_uni_tr ) {
		AOD_Test::ok(
			aod_test_uni_couvert( $aod_uni_tr, $aod_uni_d['uninstall'], '_transient_' ),
			sprintf( '%s : transients « %s* » couverts par un motif LIKE', $aod_uni_slug, $aod_uni_tr ),
			sprintf(
				'Aucun motif LIKE ne couvre « _transient_%s ». C’est la dérive qui a fait manquer '
					. 'les caches de la section Stock : le motif nommait UNE section (aod_cd_home_), '
					. 'la deuxième a copié le mécanisme avec son propre préfixe. Un motif qui nomme '
					. 'une section dérive dès la suivante.',
				$aod_uni_tr
			)
		);
	}
}
