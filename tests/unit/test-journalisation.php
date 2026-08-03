<?php
/**
 * Les journaux ne doivent JAMAIS contenir de secret (Phase 31.3).
 *
 * 🚨 CE QUE CE CLIQUET GARDE, ET POURQUOI IL EXISTE. La 31.3 a ajouté des
 * journaux là où il n'y en avait aucun — 0 `error_log`, 0 `wc_get_logger` sur
 * 24 513 lignes, pour six intégrations HTTP toutes en fail-open. C'est un progrès
 * de diagnostic, mais c'est aussi le moment exact où un dépôt commence à fuiter
 * ses identifiants : l'audit de sécurité avait relevé « aucun secret journalisé »
 * comme un ACQUIS — acquis qui ne tenait qu'au silence général.
 *
 * L'échange serait perdant : on troquerait un défaut de diagnostic contre une
 * fuite d'identifiants. `wc-logs/` est lisible par tout plugin installé, part
 * dans les sauvegardes, et se retrouve parfois servi par le serveur web.
 *
 * 🚨 POURQUOI CE N'EST PAS TRIVIAL — le piège que ce fichier éprouve. Le réflexe
 * (masquer les clés d'un tableau qui « ressemble » à un secret) ne suffit pas
 * ici, parce que QUATRE de nos intégrations mettent leur secret DANS L'URL :
 * Noest (`?api_token=…&user_guid=…`), EcoTrack (`?api_token=…`), WhatsApp
 * (`?apikey=…`) et le test Meta (`?access_token=…`). Une URL est UNE CHAÎNE : un
 * masqueur par nom de clé n'y voit rien. Loguer « l'URL appelée » sur un échec —
 * le réflexe le plus naturel du monde — publierait le jeton en clair.
 *
 * D'où trois couches dans `AOD_COD_Log`, et d'où les trois groupes R1a/R1b/R1c
 * ci-dessous : chacun éprouve UNE couche ISOLÉMENT. Les tester ensemble ne
 * prouverait rien — une couche morte serait couverte par ses voisines, et on ne
 * l'apprendrait que le jour où le cas tombe précisément dans son angle.
 *
 * ⚠️ R2 EST AUSSI IMPORTANT QUE R1, et c'est contre-intuitif. Un masqueur qui
 * masque tout passe R1 haut la main et rend les journaux inutiles — on aurait
 * alors payé le risque sans acheter le diagnostic. R2 ancre donc chaque absence
 * par une présence : le jeton part, l'URL et le code d'erreur restent lisibles.
 * C'est la règle du témoin de la DoD §C.2.
 *
 * ⚠️ CE FICHIER N'ÉCRIT DANS AUCUNE OPTION DU SITE. Les secrets témoins sont
 * injectés par le filtre `aod_cod_log_secrets`, prévu pour ça. Un test qui
 * écrirait les réglages transporteurs pour se prouver lui-même serait un défaut,
 * pas un contrôle — cf. l'incident consigné en dette D26.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Journalisation 31.3 — R0 : le socle est chargé' );

$aod_log_ok = class_exists( 'AOD_COD_Log' );
AOD_Test::ok(
	$aod_log_ok,
	'AOD_COD_Log est chargée',
	$aod_log_ok ? '' : 'class-aod-cod-log.php n’est pas requis par le chargeur du plugin'
);

if ( ! $aod_log_ok ) {
	return;
}

/* =========================================================================
 * Bac à sable : des secrets TÉMOINS, injectés par filtre. Aucune écriture.
 * ========================================================================= */

// Assez longs pour dépasser SECRET_MIN_LEN, assez distinctifs pour qu'une
// correspondance accidentelle soit impossible.
const AOD_TEST_SECRET      = 'TEMOIN-SECRET-31-3-9f4c2ae1b7';
const AOD_TEST_SECRET_URL  = 'TEMOIN-URL-31-3-d4e8a10c5b';

$aod_log_injecte = function ( $secrets ) {
	$secrets[] = AOD_TEST_SECRET;
	$secrets[] = AOD_TEST_SECRET_URL;
	return $secrets;
};
add_filter( 'aod_cod_log_secrets', $aod_log_injecte );
AOD_COD_Log::flush_secrets();

// Relevé AVANT toute manipulation : on vérifiera en R5 qu'on n'a rien écrit.
$aod_log_options_avant = array(
	'aod_cod_whatsapp'  => get_option( 'aod_cod_whatsapp', null ),
	'aod_cod_pixels'    => get_option( 'aod_cod_pixels', null ),
	'aod_cod_turnstile' => get_option( 'aod_cod_turnstile', null ),
);

$aod_log_masque = AOD_COD_Log::MASK;

/* =========================================================================
 * R1a — COUCHE 1 : masquage par NOM DE CLÉ, récursif
 *
 * Couvre les corps de requête et les en-têtes HTTP. Les 8 noms éprouvés ici sont
 * ceux RÉELLEMENT utilisés par les 29 transporteurs et les 3 régies, relevés un
 * par un dans le code — pas une liste plausible.
 * ========================================================================= */

AOD_Test::group( 'Journalisation 31.3 — R1a 🔒 couche 1 : masquage par nom de clé' );

$aod_log_cles = array(
	'api_token'           => 'Yalidine, Yalitec, Noest, EcoTrack (+23 white-labels)',
	'api_id'              => 'Yalidine, Yalitec',
	'user_guid'           => 'Noest (moitié du couple d’authentification)',
	'token'               => 'Maystro, Procolis',
	'key'                 => 'Procolis (nom court : correspondance exacte)',
	'secret'              => 'Turnstile',
	'apikey'              => 'WhatsApp / CallMeBot',
	'meta_capi_token'     => 'CAPI Meta',
	'tiktok_capi_token'   => 'Events API TikTok',
	'snapchat_capi_token' => 'CAPI Snapchat',
	'Authorization'       => 'en-tête Bearer (EcoTrack, Snapchat)',
	'Access-Token'        => 'en-tête TikTok',
);

foreach ( $aod_log_cles as $aod_cle => $aod_qui ) {
	$aod_sortie = AOD_COD_Log::redact( array( $aod_cle => AOD_TEST_SECRET ) );
	AOD_Test::ok(
		isset( $aod_sortie[ $aod_cle ] ) && $aod_log_masque === $aod_sortie[ $aod_cle ],
		sprintf( '« %s » est masqué (%s)', $aod_cle, $aod_qui ),
		isset( $aod_sortie[ $aod_cle ] ) ? 'valeur rendue : ' . $aod_sortie[ $aod_cle ] : 'clé absente de la sortie'
	);
}

// Récursivité : les corps de requête imbriquent volontiers l'auth sous plusieurs
// niveaux (`{ data: [ { … } ], access_token: … }` chez Meta).
$aod_log_imbrique = AOD_COD_Log::redact( array(
	'data' => array( array( 'event' => 'Purchase', 'creds' => array( 'api_token' => AOD_TEST_SECRET ) ) ),
) );
AOD_Test::ok(
	false === strpos( wp_json_encode( $aod_log_imbrique ), AOD_TEST_SECRET ),
	'Le masquage descend dans les tableaux imbriqués (3 niveaux)'
);

/* =========================================================================
 * R1b — COUCHE 2 : masquage par NOM DE PARAMÈTRE D'URL
 *
 * 🚨 C'est LA couche que le réflexe « masquer les clés de tableau » n'a pas.
 * Elle couvre aussi un secret jamais enregistré — saisi dans le formulaire puis
 * testé — qu'aucune lecture d'option ne pourrait connaître : elle regarde le NOM
 * du paramètre, pas la valeur. On l'éprouve donc avec une valeur INCONNUE du
 * collecteur, sinon la couche 3 la masquerait et ce groupe serait un faux vert.
 * ========================================================================= */

AOD_Test::group( 'Journalisation 31.3 — R1b 🔒 couche 2 : masquage dans les URL' );

$aod_log_inconnu = 'JAMAIS-ENREGISTRE-31-3-c0ffee42';

$aod_log_urls = array(
	'Noest (bordereau, jeton + guid)' => 'https://app.noest-dz.com/api/public/get/order?api_token=' . $aod_log_inconnu . '&user_guid=' . $aod_log_inconnu,
	'EcoTrack'                        => 'https://speed.ecotrack.dz/api/v1/get/fees?api_token=' . $aod_log_inconnu,
	'WhatsApp / CallMeBot'            => 'https://api.callmebot.com/whatsapp.php?phone=213550000000&text=bonjour&apikey=' . $aod_log_inconnu,
	'Test CAPI Meta'                  => 'https://graph.facebook.com/v19.0/123/?fields=id,name&access_token=' . $aod_log_inconnu,
);

foreach ( $aod_log_urls as $aod_quoi => $aod_url ) {
	$aod_r = AOD_COD_Log::redact_string( $aod_url );
	AOD_Test::ok(
		false === strpos( $aod_r, $aod_log_inconnu ),
		sprintf( '%s : le jeton ne survit pas dans l’URL', $aod_quoi ),
		'rendu : ' . $aod_r
	);
}

// ANCRAGE (règle du témoin) — une absence ne prouve rien sans une présence à
// côté. Si `redact_string()` renvoyait la chaîne vide, tous les tests ci-dessus
// passeraient au vert en masquant absolument tout.
$aod_log_ancre = AOD_COD_Log::redact_string(
	'https://api.callmebot.com/whatsapp.php?phone=213550000000&apikey=' . $aod_log_inconnu
);
AOD_Test::ok(
	false !== strpos( $aod_log_ancre, 'api.callmebot.com' ),
	'ANCRAGE : l’hôte appelé reste lisible (sinon le journal ne diagnostique rien)',
	'rendu : ' . $aod_log_ancre
);
AOD_Test::ok(
	false !== strpos( $aod_log_ancre, '213550000000' ),
	'ANCRAGE : les paramètres non secrets restent lisibles'
);

/* =========================================================================
 * R1c — COUCHE 3 : masquage par VALEUR CONNUE
 *
 * Le dernier filet : le secret apparaît là où personne ne l'attendait — renvoyé
 * en écho par l'API dans son corps de réponse, recopié dans le message d'une
 * exception. Ni le nom de clé ni le nom de paramètre ne peuvent aider là.
 * ========================================================================= */

AOD_Test::group( 'Journalisation 31.3 — R1c 🔒 couche 3 : masquage par valeur connue' );

$aod_log_echo = 'Réponse API : {"error":"invalid credentials supplied: ' . AOD_TEST_SECRET . '"}';
AOD_Test::ok(
	false === strpos( AOD_COD_Log::redact_string( $aod_log_echo ), AOD_TEST_SECRET ),
	'Un secret renvoyé en ÉCHO dans un corps de réponse est masqué',
	'rendu : ' . AOD_COD_Log::redact_string( $aod_log_echo )
);

// Forme ENCODÉE : `add_query_arg()` encode la valeur, donc le jeton présent dans
// l'URL n'est PAS littéralement celui de l'option dès qu'il porte un caractère
// spécial. Un collecteur qui n'enregistrerait que la forme brute raterait
// exactement les jetons les plus complexes.
$aod_log_encode = 'https://x.test/?z=' . rawurlencode( AOD_TEST_SECRET_URL );
AOD_Test::ok(
	false === strpos( AOD_COD_Log::redact_string( $aod_log_encode ), rawurlencode( AOD_TEST_SECRET_URL ) ),
	'La forme URL-encodée d’un secret est masquée elle aussi'
);

// Le message lui-même, pas seulement le contexte : `write()` rédige la ligne
// complète. Sans ça, un `sprintf( '… %s', $url )` passerait à côté des couches.
$aod_log_ligne = AOD_COD_Log::redact_string( 'Échec : ' . AOD_TEST_SECRET . ' refusé' );
AOD_Test::ok(
	false === strpos( $aod_log_ligne, AOD_TEST_SECRET ),
	'Le MESSAGE est rédigé, pas seulement le contexte structuré'
);

/* =========================================================================
 * R2 — Ne pas masquer ce qui doit rester lisible
 *
 * ⚠️ Aussi important que R1. Un masqueur qui masque tout passe R1 parfaitement
 * et rend les journaux inutiles : on aurait payé le risque sans acheter le
 * diagnostic. Les valeurs ci-dessous sont publiques par construction — les
 * masquer ne protégerait rien et casserait le dépannage.
 * ========================================================================= */

AOD_Test::group( 'Journalisation 31.3 — R2 : le diagnostic reste possible' );

$aod_log_publics = array(
	'domain'               => 'speed.ecotrack.dz',        // Imposé en dur pour 23 white-labels.
	'site'                 => '0x4AAAAAAA_cle_de_SITE',   // Clé Turnstile PUBLIQUE (data-sitekey).
	'meta_test_event_code' => 'TEST12345',                // Documenté « non secret » dans le code.
	'from_wilaya_id'       => '16',
	'http'                 => '502',
	'tracking'             => 'YAL-123456789',
);

foreach ( $aod_log_publics as $aod_cle => $aod_val ) {
	$aod_sortie = AOD_COD_Log::redact( array( $aod_cle => $aod_val ) );
	AOD_Test::ok(
		isset( $aod_sortie[ $aod_cle ] ) && $aod_val === $aod_sortie[ $aod_cle ],
		sprintf( '« %s » reste lisible (%s)', $aod_cle, $aod_val ),
		isset( $aod_sortie[ $aod_cle ] ) ? 'rendu : ' . $aod_sortie[ $aod_cle ] : 'clé perdue'
	);
}

/* =========================================================================
 * R3 — Les six sorties externes journalisent VRAIMENT
 *
 * Contrôle structurel : une classe de journalisation que personne n'appelle est
 * exactement le genre de livrable qui rend vert sans rien changer. On vérifie
 * donc les points d'accroche, dans le vrai code.
 * ========================================================================= */

AOD_Test::group( 'Journalisation 31.3 — R3 : les points d’accroche existent' );

$aod_log_cod = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : dirname( AOD_CD_PATH ) . '/aod-cod-form/';

$aod_log_accroches = array(
	'class-aod-carrier.php'      => 'les 29 transporteurs (goulot `remote()`)',
	'class-aod-cod-pixels.php'   => 'les 3 régies (goulot `capi_http_post()`)',
	'class-aod-cod-turnstile.php' => 'le fail-open Turnstile',
	'class-aod-cod-whatsapp.php' => 'la notification WhatsApp',
	'class-aod-cod-form.php'     => 'le `catch` de la création de commande',
);

foreach ( $aod_log_accroches as $aod_fichier => $aod_quoi ) {
	$aod_chemin  = $aod_log_cod . 'includes/' . $aod_fichier;
	$aod_contenu = is_readable( $aod_chemin ) ? (string) file_get_contents( $aod_chemin ) : '';
	AOD_Test::ok(
		false !== strpos( $aod_contenu, 'AOD_COD_Log::' ),
		sprintf( '%s journalise — %s', $aod_fichier, $aod_quoi ),
		'' === $aod_contenu ? 'fichier illisible : ' . $aod_chemin : 'aucun appel à AOD_COD_Log'
	);
}

/* =========================================================================
 * R4 — ANTI-CONTOURNEMENT : rien ne journalise en dehors d'AOD_COD_Log
 *
 * 🚨 LA RÈGLE QUI TIENDRA DANS LE TEMPS. R1 prouve que le rédacteur fonctionne ;
 * il ne prouve rien sur le code qui ne passe pas par lui. Un futur
 * `wc_get_logger()->error( $url )` écrit en direct — le geste le plus naturel du
 * monde — court-circuiterait les trois couches d'un coup, et R1 resterait vert.
 * C'est cette règle-là, pas R1, qui empêche la fuite de revenir.
 * ========================================================================= */

AOD_Test::group( 'Journalisation 31.3 — R4 : aucun journal ne contourne le rédacteur' );

$aod_log_racines = array( AOD_CD_PATH, $aod_log_cod );
$aod_log_fautifs = array();
$aod_log_scannes = 0;

foreach ( $aod_log_racines as $aod_racine ) {
	if ( ! is_dir( $aod_racine ) ) {
		continue;
	}
	$aod_it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $aod_racine, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $aod_it as $aod_f ) {
		if ( ! $aod_f->isFile() || 'php' !== strtolower( $aod_f->getExtension() ) ) {
			continue;
		}
		$aod_chemin = $aod_f->getPathname();
		// Le rédacteur lui-même est le SEUL endroit autorisé à tenir le logger.
		if ( false !== strpos( $aod_chemin, 'class-aod-cod-log.php' ) ) {
			continue;
		}
		$aod_log_scannes++;
		$aod_src = (string) file_get_contents( $aod_chemin );
		foreach ( array( 'wc_get_logger', 'error_log(', 'new WC_Logger' ) as $aod_motif ) {
			if ( false !== strpos( $aod_src, $aod_motif ) ) {
				$aod_log_fautifs[] = basename( $aod_chemin ) . ' → ' . $aod_motif;
			}
		}
	}
}

// TÉMOIN (DoD §C.2) — un balayage vide rendrait « 0 contournement » : vert, et
// vide de sens. On publie le périmètre réellement inspecté.
AOD_Test::ok(
	$aod_log_scannes >= 30,
	sprintf( 'Témoin : %d fichiers PHP réellement balayés', $aod_log_scannes )
);

AOD_Test::ok(
	empty( $aod_log_fautifs ),
	'Aucun journal écrit en direct : tout passe par AOD_COD_Log (donc par la rédaction)',
	empty( $aod_log_fautifs ) ? '' : implode( ' · ', $aod_log_fautifs )
);

// Le niveau `info` est proscrit : un journal qui grossit sans raison est un
// journal que l'hébergeur finit par désactiver — et on revient au silence.
$aod_log_src_log = (string) file_get_contents( $aod_log_cod . 'includes/class-aod-cod-log.php' );
AOD_Test::ok(
	false === strpos( $aod_log_src_log, "'info'" ),
	'Aucun niveau « info » : seuls `error` (échec) et `debug` (détail) existent'
);

/* =========================================================================
 * R5 — Le correctif D22 tient : plus d'envoi WhatsApp dans le chemin d'achat
 *
 * `blocking => false` rendait la détection d'erreur IMPOSSIBLE (réponse factice
 * immédiate → `is_wp_error()` toujours faux), tout en faisant quand même payer à
 * l'acheteur la résolution DNS. Journaliser dans cette branche aurait été écrire
 * une ligne qui ne peut jamais s'exécuter.
 * ========================================================================= */

AOD_Test::group( 'Journalisation 31.3 — R5 : WhatsApp sort du chemin d’achat (D22)' );

$aod_log_wa = (string) file_get_contents( $aod_log_cod . 'includes/class-aod-cod-whatsapp.php' );

AOD_Test::ok(
	false === strpos( $aod_log_wa, "'blocking' => false" ),
	'L’envoi n’est plus « non bloquant » — donc l’échec redevient détectable'
);
AOD_Test::ok(
	false !== strpos( $aod_log_wa, 'wp_schedule_single_event' ),
	'L’envoi est mis en file (motif de la Phase 14), hors de la requête de l’acheteur'
);
AOD_Test::ok(
	has_action( AOD_COD_WhatsApp::DISPATCH_HOOK ) !== false,
	'Le hook de la file est réellement écouté (une file sans consommateur perd les messages)'
);

/* =========================================================================
 * R6 — Ce test n'a rien écrit dans les réglages du site
 *
 * Motif repris de `test-numerotation-sequentielle.php` (R3), écrit après
 * l'incident D26 : un test qui manipule les données de production pour se
 * prouver lui-même est un défaut, pas un contrôle.
 * ========================================================================= */

AOD_Test::group( 'Journalisation 31.3 — R6 : aucune écriture dans les réglages' );

foreach ( $aod_log_options_avant as $aod_opt => $aod_avant ) {
	AOD_Test::ok(
		get_option( $aod_opt, null ) === $aod_avant,
		sprintf( 'L’option « %s » est intacte', $aod_opt )
	);
}

// On rend l'environnement tel qu'on l'a trouvé : le filtre témoin ne doit pas
// survivre à ce fichier, sinon les tests suivants tourneraient avec des secrets
// fantômes dans le collecteur.
remove_filter( 'aod_cod_log_secrets', $aod_log_injecte );
AOD_COD_Log::flush_secrets();
