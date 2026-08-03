<?php
/**
 * Téléversements sous liste blanche, et prospects plafonnés (Phase 32.2).
 *
 * ⚠️ D'ABORD, CE QUE LA STORY DISAIT DE TRAVERS. Elle réunissait ces deux sujets
 * sous « la surface d'entrée NON AUTHENTIFIÉE ». Vérifié : **les cinq
 * téléversements ne le sont pas.** Ils exigent `check_ajax_referer( 'aod_cd' )`
 * ET `current_user_can( 'edit_products' )`, aucun `wp_ajax_nopriv_*` n'existe dans
 * le tableau de bord, et `edit_products` — capability du cœur WordPress —
 * n'appartient à aucune des 7 `aod_cap_*` qu'un confirmateur peut recevoir. Seuls
 * le gérant et l'administrateur y accèdent : le propriétaire, sur sa boutique.
 *
 * Le dire change ce qu'on attend de la liste blanche : c'est de l'**hygiène**, pas
 * une barrière. Sans elle, le champ « photo du produit » acceptait tout ce que
 * `get_allowed_mime_types()` autorise — PDF, DOC, ZIP, RAR, PSD, MP3 : la boutique
 * devenait un hébergeur de fichiers, sans que rien ne le signale.
 *
 * 🔴 LA VRAIE PORTE NON AUTHENTIFIÉE, ELLE, EST UNIQUE : `aod_cod_lead`. Mesuré
 * sur ce clone AVANT correctif — **15 requêtes avec un jeton inventé à chaque
 * fois → 15 lignes créées, 15 réponses HTTP 200**, une seule IP, un seul nonce
 * (pour un visiteur déconnecté, WordPress calcule le même sur tout le site et il
 * vaut 24 h). APRÈS — 70 tentatives, **60 acceptées, 10 refusées en 429**.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Surface 32.2 — R0 : le socle est chargé' );

$aod_se_ok = class_exists( 'AOD_CD_Upload' ) && method_exists( 'AOD_CD_Upload', 'handle' );
AOD_Test::ok( $aod_se_ok, 'AOD_CD_Upload::handle() est disponible', $aod_se_ok ? '' : 'class-aod-cd-upload.php non chargée' );

$aod_se_leads = class_exists( 'AOD_COD_Leads' ) && method_exists( 'AOD_COD_Leads', 'instance' );
AOD_Test::ok( $aod_se_leads, 'AOD_COD_Leads est disponible' );

if ( ! $aod_se_ok ) {
	return;
}

/* =========================================================================
 * R1 — UN SEUL téléversement dans tout le dépôt
 *
 * 🚨 C'est la règle qui tient dans la durée, et la seule qui attrape le 7ᵉ appel
 * écrit demain. Une liste blanche posée « sur les cinq appels connus » ne protège
 * que les cinq : le suivant repartirait sans, et rien ne le dirait. En ramenant
 * `media_handle_upload()` à un point unique, l'oubli devient impossible plutôt
 * qu'improbable.
 * ========================================================================= */

AOD_Test::group( 'Surface 32.2 — R1 : un seul point de téléversement' );

/**
 * Compte les APPELS réels d'une fonction, en ignorant commentaires et docblocks.
 *
 * ⚠️ Nécessaire, pas cosmétique : le dépôt cite `media_handle_upload()` dans six
 * commentaires qui expliquent précisément les pièges de cette API. Un `grep` nu
 * les compterait comme des appels et rendrait la règle absurde — le genre de
 * cliquet qu'on finit par désactiver.
 */
function aod_se_compter_appels( $fichier, $fonction ) {
	$src   = (string) file_get_contents( $fichier );
	$n     = 0;
	$bloc  = false;
	foreach ( explode( "\n", $src ) as $ligne ) {
		$t = ltrim( $ligne );
		if ( $bloc ) {
			if ( false !== strpos( $t, '*/' ) ) {
				$bloc = false;
			}
			continue;
		}
		if ( 0 === strpos( $t, '/*' ) ) {
			$bloc = ( false === strpos( $t, '*/' ) );
			continue;
		}
		if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '#' ) ) {
			continue;
		}
		// Commentaire de fin de ligne : on ne garde que le code qui le précède.
		$pos = strpos( $t, '//' );
		if ( false !== $pos ) {
			$t = substr( $t, 0, $pos );
		}
		$n += substr_count( $t, $fonction . '(' );
	}
	return $n;
}

/*
 * ⚠️ ON BALAIE NOS DEUX PLUGINS, PAS LE DOSSIER `plugins/`. La première version de
 * cette règle remontait d'un cran de trop (`dirname( dirname( AOD_CD_PATH ) )`) et
 * balayait TOUT `wp-content/plugins/` — WooCommerce compris. Elle a échoué en
 * citant trois fichiers du cœur de WooCommerce, sur du code qui n'est pas le
 * nôtre et que nous ne corrigerons jamais. **C'est le TÉMOIN qui l'a montré :
 * 4 725 fichiers là où le dépôt en compte quelques dizaines.** Un cliquet qui
 * déborde de son périmètre finit désactivé — publier ce qu'il a réellement lu est
 * ce qui permet de s'en apercevoir.
 */
$aod_se_php = array();
foreach ( array( defined( 'AOD_CD_PATH' ) ? AOD_CD_PATH : '', defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : '' ) as $racine ) {
	if ( '' === $racine || ! is_dir( $racine ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $racine ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
			$aod_se_php[] = $f->getPathname();
		}
	}
}

AOD_Test::ok(
	count( $aod_se_php ) > 40 && count( $aod_se_php ) < 400,
	sprintf( 'TÉMOIN : %d fichiers PHP balayés dans les DEUX plugins maison', count( $aod_se_php ) ),
	'compte hors plage : soit la règle ne balaie presque rien, soit elle déborde sur des plugins tiers'
);

$aod_se_appels = array();
foreach ( $aod_se_php as $f ) {
	foreach ( array( 'media_handle_upload', 'media_handle_sideload', 'wp_handle_upload', 'media_sideload_image' ) as $fn ) {
		$n = aod_se_compter_appels( $f, $fn );
		if ( $n > 0 ) {
			$aod_se_appels[] = basename( $f ) . ' → ' . $fn . ' ×' . $n;
		}
	}
}

AOD_Test::ok(
	array( 'class-aod-cd-upload.php → media_handle_upload ×1' ) === $aod_se_appels,
	'🔒 Exactement UN téléversement, et il est dans AOD_CD_Upload',
	empty( $aod_se_appels ) ? 'aucun appel trouvé — le compteur ne compte plus rien' : implode( ' · ', $aod_se_appels ) . ' — passer par AOD_CD_Upload::handle()'
);

/* =========================================================================
 * R2 — Le piège `test_form`, et deux listes qui restent distinctes
 * ========================================================================= */

AOD_Test::group( 'Surface 32.2 — R2 : la liste blanche est réellement posée' );

$aod_se_src = (string) file_get_contents( AOD_CD_PATH . 'includes/class-aod-cd-upload.php' );

AOD_Test::ok(
	false !== strpos( $aod_se_src, "'test_form' => false" ),
	'🚨 `test_form => false` est re-posé avec la liste blanche',
	'un $overrides custom REMPLACE le défaut : sans lui, TOUS les envois échouent (« Invalid form submission »)'
);

$aod_se_img = AOD_CD_Upload::mimes( 'image' );
$aod_se_vid = AOD_CD_Upload::mimes( 'video' );

AOD_Test::ok( ! empty( $aod_se_img ) && ! empty( $aod_se_vid ), 'ANCRAGE : les deux listes sont non vides' );
AOD_Test::ok(
	$aod_se_img !== $aod_se_vid,
	'ANCRAGE : les deux listes sont DISTINCTES',
	'si `mimes()` rendait toujours la même, « le champ vidéo accepte un MP4 » serait vrai pour rien'
);

$aod_se_fuite = array_intersect( array_values( $aod_se_img ), array_values( $aod_se_vid ) );
AOD_Test::ok(
	empty( $aod_se_fuite ),
	'Aucun type ne figure dans les DEUX listes',
	implode( ', ', $aod_se_fuite )
);

foreach ( array( 'image/jpeg', 'image/png', 'image/webp' ) as $m ) {
	AOD_Test::ok( in_array( $m, array_values( $aod_se_img ), true ), sprintf( 'Les images acceptent %s', $m ) );
}
AOD_Test::ok( in_array( 'video/mp4', array_values( $aod_se_vid ), true ), 'Les vidéos acceptent video/mp4' );

// Ce que la liste doit REFUSER — c'est la moitié utile de la règle.
foreach ( array( 'application/zip', 'application/pdf', 'image/svg+xml' ) as $m ) {
	AOD_Test::ok(
		! in_array( $m, array_values( $aod_se_img ), true ) && ! in_array( $m, array_values( $aod_se_vid ), true ),
		sprintf( '🔒 %s n’est accepté nulle part', $m )
	);
}

/* =========================================================================
 * R3 — La liste suit le CHAMP, pas le fichier
 *
 * 🚨 LE PIÈGE DE CETTE STORY, ET IL A FAILLI SE REFERMER. `upload_one()` sert les
 * images de héro, de couverture, de galerie et d'avis — MAIS AUSSI le champ
 * `file` du bloc « Vidéo » d'une page de vente (« Ou fichier vidéo (MP4) »), qui
 * emprunte exactement le même chemin. Une liste image-seule appliquée sans
 * inventorier les appelants aurait CASSÉ une fonctionnalité livrée en Phase 6,
 * avec pour tout symptôme un « format refusé ». **Une restriction casse en
 * silence ce qu'on n'a pas énuméré.**
 * ========================================================================= */

AOD_Test::group( 'Surface 32.2 — R3 : le champ vidéo de la page de vente survit' );

if ( class_exists( 'AOD_COD_Landing' ) && method_exists( 'AOD_COD_Landing', 'schema' ) ) {
	$aod_se_schema = AOD_COD_Landing::schema();
	$aod_se_champs_video = array();
	foreach ( $aod_se_schema as $bloc => $def ) {
		foreach ( (array) $def['fields'] as $cle => $spec ) {
			if ( isset( $spec['type'] ) && 'video' === $spec['type'] ) {
				$aod_se_champs_video[] = $bloc . '.' . $cle;
			}
		}
	}
	AOD_Test::ok(
		! empty( $aod_se_champs_video ),
		sprintf( 'ANCRAGE : le schéma porte bien un champ vidéo (%s)', implode( ', ', $aod_se_champs_video ) ),
		'plus aucun champ `video` au schéma — si c’est voulu, retirer aussi VIDEO_MIMES ; sinon, ce test ne prouvait rien'
	);

	$aod_se_land = (string) file_get_contents( AOD_CD_PATH . 'includes/class-aod-cd-section-landing.php' );
	AOD_Test::ok(
		false !== strpos( $aod_se_land, 'upload_one( $field, $pid, $error, $spec[\'type\'] )' ),
		'Le téléversement d’un champ simple reçoit le TYPE du schéma',
		'sans lui, tous les champs partiraient en liste image — le MP4 du bloc Vidéo serait refusé'
	);
	AOD_Test::ok(
		false !== strpos( $aod_se_land, '$ssp[\'type\'] )' ),
		'…et les sous-champs d’un bloc répété aussi'
	);
} else {
	AOD_Test::ok( true, 'R3 NON ÉPROUVÉE (AOD_COD_Landing absente : plugin COD désactivé) — cf. porte `degrade`' );
}

/* =========================================================================
 * R4 — Le plafond des prospects compte les CRÉATIONS, jamais les requêtes
 *
 * 🚨 TOUT LE RAISONNEMENT DE LA GARDE EST LÀ. Le JS republie le prospect à chaque
 * frappe (débounce 900 ms) : un seul visiteur légitime émet des DIZAINES de
 * requêtes pour UN jeton, sans jamais créer plus d'une ligne. Compter les requêtes
 * aurait jeté dehors précisément les acheteurs qui hésitent et se reprennent —
 * c'est-à-dire ceux que la fonctionnalité existe pour rattraper.
 * ========================================================================= */

AOD_Test::group( 'Surface 32.2 — R4 : le plafond par IP, éprouvé' );

if ( ! $aod_se_leads ) {
	AOD_Test::ok( false, 'AOD_COD_Leads chargée' );
	return;
}

$aod_se_ip  = '198.51.100.42';
$aod_se_old = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
$_SERVER['REMOTE_ADDR'] = $aod_se_ip;
$aod_se_cle = 'aod_cod_leadq_' . md5( $aod_se_ip );
delete_transient( $aod_se_cle );

$aod_se_q = new ReflectionMethod( 'AOD_COD_Leads', 'consume_quota' );
$aod_se_q->setAccessible( true );

AOD_Test::ok(
	true === $aod_se_q->invoke( null ),
	'ANCRAGE : compteur à zéro, la création passe',
	'sans cet ancrage, « le plafond bloque » serait vrai même si la garde refusait TOUT'
);

$aod_se_max = defined( 'AOD_COD_MAX_LEADS_PER_IP_HOUR' ) ? (int) AOD_COD_MAX_LEADS_PER_IP_HOUR : 60;
set_transient( $aod_se_cle, $aod_se_max, HOUR_IN_SECONDS );

AOD_Test::ok(
	false === $aod_se_q->invoke( null ),
	sprintf( '🔒 Au plafond (%d/h), la création est refusée', $aod_se_max )
);

// Le seuil doit rester LARGE : perdre un prospect, c'est perdre une vente, et le
// CGNAT mobile algérien fait partager une IP à beaucoup d'acheteurs.
AOD_Test::ok(
	$aod_se_max >= 30,
	sprintf( 'Le seuil reste large (%d/h ≥ 30)', $aod_se_max ),
	'un plafond serré se retourne contre le gérant : il coupe de vrais prospects derrière une IP partagée'
);

delete_transient( $aod_se_cle );
if ( null === $aod_se_old ) {
	unset( $_SERVER['REMOTE_ADDR'] );
} else {
	$_SERVER['REMOTE_ADDR'] = $aod_se_old;
}
AOD_Test::ok( false === get_transient( $aod_se_cle ), 'R5 : le test ne laisse aucun compteur derrière lui' );

/* =========================================================================
 * R6 — Ce que la garde ne doit PAS faire
 * ========================================================================= */

AOD_Test::group( 'Surface 32.2 — R6 : ni seconde source d’IP, ni mise à jour plafonnée' );

$aod_se_lsrc = (string) file_get_contents( AOD_COD_PATH . 'includes/class-aod-cod-leads.php' );

AOD_Test::ok(
	false !== strpos( $aod_se_lsrc, 'AOD_COD_IP::client()' ),
	'L’IP vient de la source unique posée en 26.1'
);
AOD_Test::ok(
	false === strpos( $aod_se_lsrc, 'WC_Geolocation' ) && false === strpos( $aod_se_lsrc, 'HTTP_X_' ),
	'🔒 Aucune SECONDE source d’IP n’est réintroduite',
	'WC_Geolocation rend `X-Real-IP` BRUT : le client forge alors un compteur neuf à chaque requête et le plafond ne compte plus rien (défaut soldé en 26.1)'
);
AOD_Test::ok(
	false !== strpos( $aod_se_lsrc, '! self::exists( $token ) && ! self::consume_quota()' ),
	'🔴 Un prospect DÉJÀ CONNU n’est jamais plafonné',
	'sans le court-circuit sur `exists()`, un acheteur en cours de saisie perdrait sa fiche dès l’IP saturée — la garde se retournerait contre la fonctionnalité'
);

// Résidu de désinstallation : le motif générique `_transient_aod_%` couvre déjà
// `aod_cod_leadq_*`. On le vérifie plutôt que de le supposer (leçon de la 25.1).
$aod_se_uninst = (string) file_get_contents( AOD_COD_PATH . 'uninstall.php' );
AOD_Test::ok(
	false !== strpos( $aod_se_uninst, "'_transient_aod_'" ),
	'Les compteurs de quota partent à la désinstallation (motif `_transient_aod_%`)'
);
