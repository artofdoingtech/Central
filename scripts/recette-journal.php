<?php
/**
 * Porte de recette « journal » — le journal écrit-il depuis une REQUÊTE WEB ?
 * (dette D28)
 *
 * Lancement : `bash tools/recette/run.sh journal`
 *
 * 🚨 CE QU'ELLE GARDE, ET POURQUOI ELLE EXISTE. La Phase 31.3 a bâti
 * `AOD_COD_Log` pour rendre diagnosticables six intégrations HTTP toutes en
 * fail-open. Elle a été éprouvée… sous WP-CLI. Mesuré le 2026-07-22, en écrivant
 * la 32.2 : **le même appel n'écrivait RIEN depuis une requête web.**
 *
 * La cause n'était pas dans notre code. `get_filesystem_method()` ne conclut
 * « direct » que si le propriétaire des fichiers du cœur ÉGALE celui d'un fichier
 * fraîchement créé par PHP :
 *
 *     wp-admin/includes/file.php  →  fileowner( __FILE__ ) === fileowner( $temp )
 *
 * Or `wordpress/` est **bind-monté** : le cœur appartient à l'utilisateur hôte
 * (uid 1000), PHP tourne en `www-data` (uid 33). L'égalité est structurellement
 * impossible — aucun `chmod` n'y peut rien. WordPress retombait sur `ftpsockets`,
 * `WP_Filesystem()` rendait `false`, et le handler de fichiers de WooCommerce
 * (`LogHandlerFileV2::handle()`) rendait `false` **sans un mot**. Sous WP-CLI, qui
 * tourne en root, le test passait : d'où une couche qui semblait fonctionner
 * partout où on la regardait, et nulle part où elle servait.
 *
 * ⚠️ POURQUOI CE CONTRÔLE NE POUVAIT PAS ÊTRE UN TEST UNITAIRE. `tests/run.sh`
 * s'exécute DANS le processus WP-CLI — celui-là même qui passait. Un test
 * unitaire aurait été vert du premier jour et le serait resté. **La distinction
 * CLI / web est le sujet même du défaut : l'éprouver exige une vraie requête
 * HTTP.** C'est pour cette raison, et pas par confort, que ce contrôle est une
 * porte de recette.
 *
 * 🔒 ELLE NE CRÉE AUCUNE DONNÉE. Le seul incident journalisé à la demande est le
 * plafond anti-flood des prospects (32.2). On pré-charge son compteur AU PLAFOND,
 * puis on envoie UNE requête : elle est refusée en 429 — donc **aucune ligne de
 * prospect n'est créée**. On mesure le journal avant/après, puis on remet le
 * compteur comme il était. Le nombre de prospects est vérifié identique en
 * sortie ; s'il ne l'est pas, la porte échoue au lieu de laisser du résidu.
 *
 * @package AOD_Recette
 */

defined( 'ABSPATH' ) || exit;

function aod_jr_score( $ajout = null ) {
	static $pass = 0;
	static $fail = 0;
	if ( true === $ajout )  { $pass++; }
	if ( false === $ajout ) { $fail++; }
	return array( 'pass' => $pass, 'fail' => $fail );
}

function aod_jr_ok( $cond, $msg, $detail = '' ) {
	$cond = (bool) $cond;
	aod_jr_score( $cond );
	echo $cond
		? "  \033[32m✓\033[0m {$msg}\n"
		: "  \033[31m✗\033[0m {$msg}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
	return $cond;
}

/** Nombre de lignes portant $motif dans les journaux du plugin. */
function aod_jr_compter( $motif ) {
	$n = 0;
	foreach ( (array) glob( WP_CONTENT_DIR . '/uploads/wc-logs/*aod-cod*.log' ) as $f ) {
		$n += substr_count( (string) file_get_contents( $f ), $motif );
	}
	return $n;
}

echo "\n\033[1m▶ Le journal écrit-il depuis une requête WEB ? (dette D28)\033[0m\n\n";

/* =========================================================================
 * 0 · ANCRAGES — sans eux, cette porte peut être verte sans rien prouver
 * ========================================================================= */

if ( ! class_exists( 'AOD_COD_Log' ) || ! class_exists( 'AOD_COD_Leads' ) ) {
	echo "\n\033[31m✗ journal : le plugin COD n’est pas chargé, rien à éprouver.\033[0m\n\n";
	exit( 1 );
}

// ANCRAGE 1 — le journal écrit bien depuis CE processus (WP-CLI). C'est l'état
// qui masquait le défaut : si même lui échouait, « la requête web n'écrit pas »
// ne dirait rien du web, seulement d'un journal cassé.
$aod_jr_temoin = 'SONDE-RECETTE-CLI-' . wp_generate_password( 8, false );
$aod_jr_av_cli = aod_jr_compter( $aod_jr_temoin );
AOD_COD_Log::error( 'recette', $aod_jr_temoin );
aod_jr_ok(
	aod_jr_compter( $aod_jr_temoin ) > $aod_jr_av_cli,
	'ANCRAGE : depuis WP-CLI, le journal écrit',
	'le journal est cassé pour TOUT LE MONDE — ce n’est plus le défaut D28, c’est plus grave'
);

// ANCRAGE 2 — la couche fichier de WordPress est utilisable sous l'uid du serveur
// web. C'est la condition exacte que le bind-mount cassait.
$aod_jr_fs = '(non testable ici)';
if ( class_exists( '\Automattic\WooCommerce\Internal\Utilities\FilesystemUtil' ) ) {
	try {
		$aod_jr_fs = get_class( \Automattic\WooCommerce\Internal\Utilities\FilesystemUtil::get_wp_filesystem() );
	} catch ( \Throwable $e ) {
		$aod_jr_fs = 'EXCEPTION';
	}
}
// ⚠️ Vue de CE processus (WP-CLI), pas du serveur web — c'est justement là que
// les deux divergeaient. Purement informatif : la preuve est plus bas.
echo "  \033[2mⓘ vu du processus CLI : FS_METHOD=" . ( defined( 'FS_METHOD' ) ? FS_METHOD : 'non défini' ) . " · " . $aod_jr_fs . "\033[0m\n";

$aod_jr_leads_av = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . AOD_COD_Leads::table() );

/* =========================================================================
 * 1 · Armer le plafond, puis provoquer UN refus par une VRAIE requête HTTP
 * ========================================================================= */

echo "\n  \033[1mune requête HTTP réelle, servie par le serveur web\033[0m\n";

$aod_jr_motif = 'Plafond de création de prospects';
$aod_jr_avant = aod_jr_compter( $aod_jr_motif );

$aod_jr_max = defined( 'AOD_COD_MAX_LEADS_PER_IP_HOUR' ) ? (int) AOD_COD_MAX_LEADS_PER_IP_HOUR : 60;

/*
 * ⚠️ L'IP vue par le serveur web est celle du CONTENEUR appelant, que ce
 * processus ne connaît pas. On arme donc les candidats plausibles ; si aucun ne
 * correspond, la requête passera (200) au lieu d'être refusée (429), et l'ancrage
 * ci-dessous le dira — plutôt que de conclure « pas de ligne au journal » sur une
 * requête qui n'a simplement jamais atteint le code qui journalise.
 */
$aod_jr_ips = array( '127.0.0.1', '::1' );
for ( $o = 16; $o <= 31; $o++ ) {
	// Plage par défaut des réseaux Docker (172.16.0.0/12) : la passerelle et les
	// premières adresses couvrent le cas nominal d'un `docker compose`.
	for ( $h = 1; $h <= 6; $h++ ) {
		$aod_jr_ips[] = '172.' . $o . '.0.' . $h;
	}
}
foreach ( $aod_jr_ips as $ip ) {
	set_transient( 'aod_cod_leadq_' . md5( $ip ), $aod_jr_max, HOUR_IN_SECONDS );
}

$aod_jr_url = 'http://wordpress/wp-admin/admin-ajax.php';
$aod_jr_rep = wp_remote_post( $aod_jr_url, array(
	'timeout' => 15,
	'body'    => array(
		'action'     => 'aod_cod_lead',
		'nonce'      => wp_create_nonce( 'aod_cod_nonce' ),
		'token'      => 'ljournald28' . wp_generate_password( 6, false ),
		'phone'      => '0555000111',
		'name'       => 'SondeJournalD28',
		'product_id' => 0,
	),
) );

$aod_jr_code = is_wp_error( $aod_jr_rep ) ? 0 : (int) wp_remote_retrieve_response_code( $aod_jr_rep );

aod_jr_ok(
	429 === $aod_jr_code,
	sprintf( 'ANCRAGE : la requête HTTP a bien atteint le code qui journalise (HTTP %d)', $aod_jr_code ),
	is_wp_error( $aod_jr_rep )
		? 'requête impossible : ' . $aod_jr_rep->get_error_message()
		: 'attendu 429 — sans refus, aucune ligne n’est censée être écrite et la suite ne prouverait RIEN'
);

/* =========================================================================
 * 2 · LE contrôle : la ligne est-elle réellement sur le disque ?
 * ========================================================================= */

clearstatcache();
$aod_jr_apres = aod_jr_compter( $aod_jr_motif );

aod_jr_ok(
	$aod_jr_apres > $aod_jr_avant,
	sprintf( '🔴 La requête WEB a laissé une trace au journal (%d → %d)', $aod_jr_avant, $aod_jr_apres ),
	'c’est la signature EXACTE de D28 : le code appelle AOD_COD_Log, et rien n’arrive sur le disque. '
		. 'Vérifier FS_METHOD (docker-compose.yml) — sans lui, le bind-mount empêche WordPress de conclure « direct ».'
);

/* =========================================================================
 * 3 · Remise en état — la porte ne laisse rien derrière elle
 * ========================================================================= */

foreach ( $aod_jr_ips as $ip ) {
	delete_transient( 'aod_cod_leadq_' . md5( $ip ) );
}

// Ceinture : si l'IP n'a pas été devinée, la requête a créé une ligne. On la
// retire, et on VÉRIFIE le compte plutôt que de l'espérer.
$GLOBALS['wpdb']->delete( AOD_COD_Leads::table(), array( 'name' => 'SondeJournalD28' ) );
$aod_jr_leads_ap = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . AOD_COD_Leads::table() );

aod_jr_ok(
	$aod_jr_leads_av === $aod_jr_leads_ap,
	sprintf( 'La table des prospects est rendue intacte (%d)', $aod_jr_leads_ap ),
	sprintf( 'avant %d, après %d — du résidu de recette est resté', $aod_jr_leads_av, $aod_jr_leads_ap )
);

/* =========================================================================
 * Bilan
 * ========================================================================= */

$aod_jr_s = aod_jr_score();
echo "\n";
if ( $aod_jr_s['fail'] ) {
	echo "\033[31m✗ journal : {$aod_jr_s['pass']} passé(s), {$aod_jr_s['fail']} échec(s)\033[0m\n\n";
	exit( 1 );
}
if ( ! $aod_jr_s['pass'] ) {
	echo "\033[31m✗ journal : AUCUN contrôle n'a été exécuté\033[0m\n\n";
	exit( 1 );
}
echo "\033[32m✅ journal : {$aod_jr_s['pass']} passé(s), 0 échec\033[0m\n\n";
