<?php
/**
 * Borne de poids des téléversements — `AOD_CD_Upload` (Phase 47.6, décision D6).
 *
 * 🚨 CE CLIQUET APPELLE `handle()`. Il ne grep pas le fichier. La leçon a coûté
 * deux étapes de cette phase : en 47.1 une assertion était satisfaite par un
 * COMMENTAIRE, en 47.2 par un FRAGMENT DE SOURCE — deux fois. Une règle qui
 * décrit l'état ne l'éprouve pas.
 *
 * 🚨 ET IL Y A UN PIÈGE PROPRE À CE SUJET, MESURÉ SUR CE CLONE.
 * `upload_max_filesize` vaut **2M** : la borne de 5 Mo y est INATTEIGNABLE par un
 * vrai téléversement HTTP. PHP coupe AVANT que WordPress ne voie quoi que ce
 * soit — `$_FILES[…]['error']` vaut `UPLOAD_ERR_INI_SIZE` (1), `tmp_name` est
 * vide et **`size` vaut 0**.
 *
 * Deux conséquences, et elles gouvernent tout ce fichier :
 *   1. Un contrôle écrit sur le seul `size` serait VERT ICI et INERTE en
 *      production sur tout hébergeur mutualisé sous 5 Mo. D'où R2.
 *   2. Le cliquet ne peut pas passer par un vrai POST : il fabrique `$_FILES`
 *      et appelle le goulot directement. C'est la seule façon d'atteindre la
 *      branche.
 *
 * ⚠️ `handle()` est `public static` → `AOD_Test::call_static`.
 * ⚠️ R3 crée un VRAI attachement sur la vraie base du clone : il est supprimé.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Borne de téléversement 47.6 — R0 : témoins et limite effective' );

$aod_ub_socle = class_exists( 'AOD_CD_Upload' ) && function_exists( 'wp_max_upload_size' );
AOD_Test::ok( $aod_ub_socle, 'Socle présent (AOD_CD_Upload + wp_max_upload_size)' );

if ( ! $aod_ub_socle ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$aod_ub_max_const = AOD_CD_Upload::MAX_BYTES;
$aod_ub_php       = (int) wp_max_upload_size();
$aod_ub_effective = (int) AOD_CD_Upload::max_bytes();

printf(
	"    ⓘ limites mesurées — constante %s · PHP (wp_max_upload_size) %s · EFFECTIVE %s\n",
	size_format( $aod_ub_max_const ),
	size_format( $aod_ub_php ),
	size_format( $aod_ub_effective )
);

AOD_Test::eq( 5 * MB_IN_BYTES, (int) $aod_ub_max_const, 'La constante vaut 5 Mo (arbitrage owner, D6)' );
AOD_Test::ok( $aod_ub_php > 0, 'PHP annonce une limite exploitable (' . size_format( $aod_ub_php ) . ')' );
AOD_Test::eq( min( $aod_ub_max_const, $aod_ub_php ), $aod_ub_effective, '🎯 La limite EFFECTIVE est le minimum des deux — jamais la constante seule' );

/**
 * 🎯 LA PROMESSE EST TENUE — règle DURE, ajoutée en 47.6bis.
 *
 * Jusque-là cette règle se contentait de PUBLIER la limite. Elle publiait « 2 Mo »
 * pendant que l'écran promettait 5, et personne n'était réveillé : l'owner l'a
 * découvert en essayant d'envoyer une photo de 2,8 Mo. Un chiffre qu'on imprime
 * sans le confronter à rien n'est pas un contrôle.
 *
 * ⚠️ POURQUOI DURE PLUTÔT QU'AVERTISSEMENT. `bash tests/run.sh` s'exécute dans le
 * conteneur `wpcli` (`wp eval-file /tests/all.php`) : la configuration PHP est
 * ENTIÈREMENT la nôtre, versionnée dans `docker/php-aod.ini`. Un écart n'est donc
 * jamais une contrainte d'hébergeur — c'est un montage manquant, et le remède
 * tient en une commande (`docker compose up -d`). C'est le même raisonnement que
 * la sonde 6quinquies de `setup.sh`, qui refuse de conclure.
 *
 * 🚨 ET ELLE MESURE LE BON CONTENEUR — enfin. `wpcli` porte désormais le MÊME
 * `zz-aod.ini` que le conteneur web : sans ce second montage, cette règle
 * verdirait sur une valeur qui ne s'applique à aucun téléversement réel. La
 * preuve de bout en bout reste au navigateur (`run.sh riche`), parce que
 * `_wp_handle_upload()` exige `is_uploaded_file()`, hors d'atteinte ici.
 */
AOD_Test::ok(
	$aod_ub_php >= $aod_ub_max_const,
	'🎯 PHP accepte AU MOINS ce que la boutique promet — ' . size_format( $aod_ub_php ) . ' pour ' . size_format( $aod_ub_max_const )
		. ' (sinon le gérant lit une limite qu’il ne peut pas atteindre)',
	'Depuis l’hôte : docker compose up -d (recrée les conteneurs avec docker/php-aod.ini)'
);
AOD_Test::eq(
	(int) $aod_ub_max_const,
	$aod_ub_effective,
	'🔴 …donc la limite EFFECTIVE vaut la borne applicative, et non un plafond PHP subi'
);

/**
 * Fabrique un `$_FILES` scalaire, exactement dans la forme que les SIX sites
 * d'appel passent au goulot (les deux sites « multiples » reconstruisent
 * eux-mêmes un tableau scalaire avant d'appeler — vérifié 47.6).
 */
$aod_ub_poser = function ( $champ, $taille, $erreur = UPLOAD_ERR_OK, $tmp = '', $nom = 'temoin.jpg', $type = 'image/jpeg' ) {
	$_FILES[ $champ ] = array(
		'name'     => $nom,
		'type'     => $type,
		'tmp_name' => $tmp,
		'error'    => $erreur,
		'size'     => $taille,
	);
};

/** Compte les attachements — un refus ne doit EN CRÉER AUCUN. */
$aod_ub_nb_att = function () {
	$q = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	return count( $q );
};

$aod_ub_att_avant = $aod_ub_nb_att();
AOD_Test::ok( $aod_ub_att_avant >= 0, 'Attachements du clone avant épreuve : ' . $aod_ub_att_avant );

$aod_ub_nes = array();

try {
	/* =====================================================================
	 * R1 — un fichier trop lourd est refusé, et rien n'est créé.
	 * ===================================================================== */

	AOD_Test::group( 'Borne de téléversement 47.6 — R1 : au-dessus de la limite, c’est non' );

	$aod_ub_poser( 'aod_ub_gros', $aod_ub_effective + 1 );
	$aod_ub_r1 = AOD_Test::call_static( 'AOD_CD_Upload', 'handle', array( 'aod_ub_gros', 0, 'image' ) );

	AOD_Test::ok( is_wp_error( $aod_ub_r1 ), '🎯 Un fichier d’un octet au-dessus de la limite rend un WP_Error' );
	AOD_Test::eq( $aod_ub_att_avant, $aod_ub_nb_att(), '🔴 AUCUN attachement créé — media_handle_upload() n’a pas été atteint' );

	// Le `tmp_name` est VIDE : si le contrôle de taille n'était pas passé avant,
	// `media_handle_upload()` échouerait de toute façon — mais sur un tout autre
	// message. On exige donc le NÔTRE, sinon la règle serait vraie par accident.
	$aod_ub_msg1 = is_wp_error( $aod_ub_r1 ) ? $aod_ub_r1->get_error_message() : '';
	AOD_Test::ok(
		is_wp_error( $aod_ub_r1 ) && 'aod_cd_upload_too_big' === $aod_ub_r1->get_error_code(),
		'🔴 …et c’est BIEN notre refus (code aod_cd_upload_too_big), pas l’échec générique de WordPress — code lu : '
			. ( is_wp_error( $aod_ub_r1 ) ? $aod_ub_r1->get_error_code() : 'aucun' )
	);

	/* =====================================================================
	 * R2 — le cas RÉEL de ce clone : PHP a déjà coupé, `size` vaut 0.
	 * ===================================================================== */

	AOD_Test::group( 'Borne de téléversement 47.6 — R2 : quand PHP a coupé avant nous' );

	foreach ( array( UPLOAD_ERR_INI_SIZE => 'upload_max_filesize (PHP)', UPLOAD_ERR_FORM_SIZE => 'MAX_FILE_SIZE (formulaire)' ) as $aod_ub_code => $aod_ub_quoi ) {
		$aod_ub_poser( 'aod_ub_coupe', 0, $aod_ub_code );
		$aod_ub_r2 = AOD_Test::call_static( 'AOD_CD_Upload', 'handle', array( 'aod_ub_coupe', 0, 'image' ) );
		AOD_Test::ok(
			is_wp_error( $aod_ub_r2 ) && 'aod_cd_upload_too_big' === $aod_ub_r2->get_error_code(),
			'🎯 ' . $aod_ub_quoi . ' : `size` vaut 0 et pourtant le refus est le NÔTRE — un contrôle sur la seule taille serait inerte ici'
		);
	}

	AOD_Test::eq( $aod_ub_att_avant, $aod_ub_nb_att(), 'Toujours aucun attachement créé' );

	/* =====================================================================
	 * R3 — CONTRE-ÉPREUVE : sans elle, « tout refuser » serait vert.
	 * ===================================================================== */

	AOD_Test::group( 'Borne de téléversement 47.6 — R3 : la contre-épreuve, sans « tout refuser »' );

	/**
	 * 🚨 CE QUE CETTE RÈGLE PEUT PROUVER, ET CE QU'ELLE NE PEUT PAS — mesuré.
	 *
	 * On ne peut PAS mener un téléversement de bout en bout sous WP-CLI :
	 * `_wp_handle_upload()` exige `is_uploaded_file()`, vrai uniquement dans une
	 * vraie requête HTTP `multipart`. Sa première rédaction croyait contourner
	 * ça avec `pre_move_uploaded_file` — mais ce filtre agit APRÈS le contrôle.
	 *
	 * ⚠️ Et surtout : ce contrôle passe AVANT la vérification du type. Une
	 * assertion « un .txt est refusé » était donc VERTE POUR LA MAUVAISE RAISON
	 * — elle aurait tenu avec la liste blanche entièrement supprimée. C'est
	 * exactement le faux vert que cette phase a déjà rencontré trois fois.
	 *
	 * La contre-épreuve HONNÊTE porte donc sur ce dont CETTE story répond : un
	 * fichier sous la limite n'est pas refusé PAR NOTRE BORNE. Le code d'erreur
	 * discrimine sans ambiguïté — le nôtre, ou celui de WordPress.
	 * La liste blanche, elle, reste gardée là où elle est éprouvable : le
	 * contenu passé à `media_handle_upload()`.
	 */
	$aod_ub_gif = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	$aod_ub_tmp = wp_tempnam( 'aod-ub-temoin.gif' );
	file_put_contents( $aod_ub_tmp, $aod_ub_gif ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	$aod_ub_poser( 'aod_ub_ok', filesize( $aod_ub_tmp ), UPLOAD_ERR_OK, $aod_ub_tmp, 'aod-ub-temoin.gif', 'image/gif' );
	$aod_ub_r3   = AOD_Test::call_static( 'AOD_CD_Upload', 'handle', array( 'aod_ub_ok', 0, 'image' ) );
	$aod_ub_code = is_wp_error( $aod_ub_r3 ) ? $aod_ub_r3->get_error_code() : '(aucune erreur)';

	AOD_Test::ok(
		! is_wp_error( $aod_ub_r3 ) || 'aod_cd_upload_too_big' !== $aod_ub_code,
		'🔴 CONTRE-ÉPREUVE : un fichier SOUS la limite N’EST PAS refusé par notre borne — sinon « tout refuser » passerait pour une borne (issue : ' . $aod_ub_code . ')'
	);
	AOD_Test::ok(
		$aod_ub_att_avant === $aod_ub_nb_att(),
		'Témoin d’honnêteté : sous WP-CLI le téléversement ne peut pas aboutir (is_uploaded_file) — aucun attachement, et la règle ne prétend pas le contraire'
	);
	if ( ! is_wp_error( $aod_ub_r3 ) ) {
		$aod_ub_nes[] = (int) $aod_ub_r3;
	}
	if ( file_exists( $aod_ub_tmp ) ) {
		wp_delete_file( $aod_ub_tmp );
	}

	// La liste blanche MIME (32.2) n'a pas été affaiblie au passage — éprouvée
	// sur son CONTENU, seul angle atteignable ici, et recensée pour qu'un retrait
	// silencieux rougisse.
	$aod_ub_img = AOD_CD_Upload::mimes( 'image' );
	$aod_ub_vid = AOD_CD_Upload::mimes( 'video' );
	AOD_Test::eq( 5, count( $aod_ub_img ), 'Liste blanche IMAGE : 5 entrées (recensement — un ajout ou un retrait rougit)' );
	AOD_Test::eq( 3, count( $aod_ub_vid ), 'Liste blanche VIDÉO : 3 entrées (le bloc « Vidéo » d’une page de vente en dépend)' );
	AOD_Test::ok(
		! in_array( 'text/plain', $aod_ub_img, true ) && ! in_array( 'image/svg+xml', $aod_ub_img, true ),
		'🔴 Ni text/plain ni SVG dans la liste blanche — c’est la raison d’être de la 32.2 (XSS stockée si un plugin autorise le SVG)'
	);

	/* =====================================================================
	 * R4 — `min( 5 Mo, PHP )` éprouvé DANS LES DEUX SENS.
	 * ===================================================================== */

	AOD_Test::group( 'Borne de téléversement 47.6 — R4 : la limite suit l’hébergeur, dans les deux sens' );

	// Sens 1 : l'hébergeur est PLUS GÉNÉREUX que nous → c'est notre 5 Mo qui borne.
	$aod_ub_haut = function () { return 50 * MB_IN_BYTES; };
	add_filter( 'upload_size_limit', $aod_ub_haut, 99 );
	AOD_Test::eq( (int) $aod_ub_max_const, (int) AOD_CD_Upload::max_bytes(), '🎯 Hébergeur à 50 Mo → la limite reste NOTRE 5 Mo' );
	remove_filter( 'upload_size_limit', $aod_ub_haut, 99 );

	// Sens 2 : l'hébergeur est PLUS STRICT → c'est LUI qui borne (le cas de ce clone).
	$aod_ub_bas = function () { return 1 * MB_IN_BYTES; };
	add_filter( 'upload_size_limit', $aod_ub_bas, 99 );
	AOD_Test::eq( 1 * MB_IN_BYTES, (int) AOD_CD_Upload::max_bytes(), '🎯 Hébergeur à 1 Mo → la limite descend à 1 Mo, pas 5' );

	// …et le refus suit vraiment cette limite-là, pas la constante.
	$aod_ub_poser( 'aod_ub_2mo', 2 * MB_IN_BYTES );
	$aod_ub_r4 = AOD_Test::call_static( 'AOD_CD_Upload', 'handle', array( 'aod_ub_2mo', 0, 'image' ) );
	AOD_Test::ok(
		is_wp_error( $aod_ub_r4 ) && 'aod_cd_upload_too_big' === $aod_ub_r4->get_error_code(),
		'🔴 Un fichier de 2 Mo — SOUS notre constante — est refusé quand l’hébergeur plafonne à 1 Mo'
	);
	remove_filter( 'upload_size_limit', $aod_ub_bas, 99 );

	/* =====================================================================
	 * R5 — le message dit la limite, et il est traduisible.
	 * ===================================================================== */

	AOD_Test::group( 'Borne de téléversement 47.6 — R5 : le message sert à quelque chose' );

	AOD_Test::ok(
		false !== strpos( $aod_ub_msg1, size_format( $aod_ub_effective ) ),
		'🎯 Le message NOMME la limite effective (' . size_format( $aod_ub_effective ) . ') — message : « ' . $aod_ub_msg1 . ' »'
	);
	/* =====================================================================
	 * R6/R7/R8 — CE QUI ÉTAIT MUET DOIT PARLER.
	 *
	 * 🚨 Sans ces trois règles, la borne des R1-R5 SERAIT LE DÉFAUT. Deux des
	 * six sites d'appel testaient `! is_wp_error()` sans `else` : une image
	 * refusée disparaissait sans un mot. Poser une borne au-dessus de ça, c'est
	 * transformer « format refusé, en silence » en « photo trop lourde, en
	 * silence » — sur la galerie, c'est-à-dire là où on en dépose plusieurs et
	 * où l'on ne recompte pas.
	 * ===================================================================== */

	AOD_Test::group( 'Borne de téléversement 47.6 — R6 : la GALERIE dit ses refus' );

	$aod_ub_sec  = AOD_CD_Section_Products::instance();
	$aod_ub_prod = new WC_Product_Simple();
	$aod_ub_prod->set_name( 'T47.6 produit jetable' );
	$aod_ub_prod->set_status( 'publish' );
	$aod_ub_pid = $aod_ub_prod->save();

	// Deux fichiers postés dans la galerie, tous deux trop lourds : c'est la
	// forme MULTIPLE que `save_gallery()` réindexe elle-même.
	$aod_ub_trop                 = AOD_CD_Upload::max_bytes() + 1;
	$_FILES['gallery_images']    = array(
		'name'     => array( 'a.jpg', 'b.jpg' ),
		'type'     => array( 'image/jpeg', 'image/jpeg' ),
		'tmp_name' => array( '', '' ),
		'error'    => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ),
		'size'     => array( $aod_ub_trop, $aod_ub_trop ),
	);
	$aod_ub_n     = 0;
	$aod_ub_motif = '';
	AOD_Test::call( $aod_ub_sec, 'save_gallery', array( $aod_ub_prod, $aod_ub_pid, &$aod_ub_n, &$aod_ub_motif ) );
	unset( $_FILES['gallery_images'], $_FILES['gallery_single'] );

	AOD_Test::eq( 2, $aod_ub_n, '🎯 Les 2 images refusées sont COMPTÉES (elles disparaissaient en silence avant la 47.6)' );
	AOD_Test::ok( false !== strpos( $aod_ub_motif, size_format( AOD_CD_Upload::max_bytes() ) ), '🔴 …et le motif remonté nomme la limite : « ' . $aod_ub_motif . ' »' );
	AOD_Test::eq( 0, count( wc_get_product( $aod_ub_pid )->get_gallery_image_ids() ), 'La galerie reste vide — un refus n’ajoute rien' );

	AOD_Test::group( 'Borne de téléversement 47.6 — R7 : les PHOTOS D’OPTIONS aussi' );

	$_FILES['opt_img'] = array(
		'name'     => array( 0 => array( 0 => 'v.jpg' ) ),
		'type'     => array( 0 => array( 0 => 'image/jpeg' ) ),
		'tmp_name' => array( 0 => array( 0 => '' ) ),
		'error'    => array( 0 => array( 0 => UPLOAD_ERR_OK ) ),
		'size'     => array( 0 => array( 0 => $aod_ub_trop ) ),
	);
	$aod_ub_opts = array( 0 => array( 'visual' => 1, 'values' => array( 0 => array( 'label' => 'Rouge' ) ) ) );
	$aod_ub_n2   = 0;
	$aod_ub_mot2 = '';
	AOD_Test::call( $aod_ub_sec, 'upload_option_images', array( $aod_ub_opts, $aod_ub_pid, &$aod_ub_n2, &$aod_ub_mot2 ) );
	unset( $_FILES['opt_img'], $_FILES['opt_img_single'] );

	AOD_Test::eq( 1, $aod_ub_n2, '🎯 La photo d’option refusée est COMPTÉE (second site muet fermé)' );
	AOD_Test::ok( '' !== $aod_ub_mot2, '…et son motif est remonté : « ' . $aod_ub_mot2 . ' »' );

	AOD_Test::group( 'Borne de téléversement 47.6 — R8 : deux avertissements ne s’écrasent pas' );

	$aod_ub_w_img = AOD_Test::call( $aod_ub_sec, 'build_warnings', array( 0, 2, 'MOTIF-TEMOIN' ) );
	$aod_ub_w_off = AOD_Test::call( $aod_ub_sec, 'build_warnings', array( 3, 0, '' ) );
	$aod_ub_w_2   = AOD_Test::call( $aod_ub_sec, 'build_warnings', array( 3, 2, 'MOTIF-TEMOIN' ) );

	AOD_Test::ok( '' !== $aod_ub_w_img && '' !== $aod_ub_w_off, 'Témoin : chacun des deux avertissements existe seul' );
	AOD_Test::eq( '', AOD_Test::call( $aod_ub_sec, 'build_warnings', array( 0, 0, '' ) ), 'Rien à signaler → chaîne vide (aucun avertissement fantôme)' );
	AOD_Test::ok(
		false !== strpos( $aod_ub_w_2, 'MOTIF-TEMOIN' ) && false !== strpos( $aod_ub_w_2, '3' ),
		'🎯 Offres ignorées ET images refusées : les DEUX messages parviennent au gérant, aucun n’écrase l’autre'
	);
	AOD_Test::ok( strlen( $aod_ub_w_2 ) > strlen( $aod_ub_w_img ) && strlen( $aod_ub_w_2 ) > strlen( $aod_ub_w_off ), '🔴 Le message combiné est plus long que chacun pris seul (jonction réelle, pas remplacement)' );

	$aod_ub_prod2 = wc_get_product( $aod_ub_pid );
	if ( $aod_ub_prod2 ) {
		$aod_ub_prod2->delete( true );
	}
	AOD_Test::ok( ! wc_get_product( $aod_ub_pid ), 'Le produit jetable est supprimé' );

	// ⚠️ `in_arabic()` filtre la locale ; le `.mo` du plugin doit être rechargé
	// pour que `__()` change de langue — sinon le message resterait en français
	// et l'assertion accuserait le code au lieu du harnais.
	$aod_ub_msg_ar = AOD_Test::in_arabic( function () use ( $aod_ub_poser ) {
		unload_textdomain( 'aod-client-dashboard' );
		load_textdomain( 'aod-client-dashboard', WP_PLUGIN_DIR . '/aod-client-dashboard/languages/aod-client-dashboard-ar.mo' );
		$aod_ub_poser( 'aod_ub_ar', AOD_CD_Upload::max_bytes() + 1 );
		$e = AOD_CD_Upload::handle( 'aod_ub_ar', 0, 'image' );
		return is_wp_error( $e ) ? $e->get_error_message() : '';
	} );
	unload_textdomain( 'aod-client-dashboard' ); // Le harnais retrouve sa langue.
	AOD_Test::ok(
		(bool) preg_match( '/\p{Arabic}/u', $aod_ub_msg_ar ),
		'🔴 Le message de refus existe en ARABE — la chaîne passe par __(), ce n’est pas du FR en dur : « ' . $aod_ub_msg_ar . ' »'
	);
} finally {
	foreach ( $aod_ub_nes as $aod_ub_id ) {
		wp_delete_attachment( $aod_ub_id, true );
	}
	foreach ( array( 'aod_ub_gros', 'aod_ub_coupe', 'aod_ub_ok', 'aod_ub_txt', 'aod_ub_2mo', 'aod_ub_ar' ) as $aod_ub_c ) {
		unset( $_FILES[ $aod_ub_c ] );
	}
}

AOD_Test::group( 'Borne de téléversement 47.6 — nettoyage' );

AOD_Test::eq( $aod_ub_att_avant, $aod_ub_nb_att(), 'Le clone retrouve son nombre d’attachements (' . $aod_ub_att_avant . ')' );
