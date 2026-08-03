<?php
/**
 * Recette « API de Conversion Meta (CAPI) » — clôture Phase 10 (étape 10.3).
 *
 * Recette SERVEUR (pas de navigateur) : la CAPI est un envoi serveur-à-serveur,
 * on la prouve de façon déterministe en INTERCEPTANT la requête HTTP sortante
 * (`pre_http_request`) — aucun appel réel à Meta n'est fait, aucun compte requis.
 *
 * Lancer :  docker compose exec -T wpcli wp eval-file /scripts/recette-capi.php
 * Sortie ≠ 0 (WP_CLI::error) si une assertion échoue → run.sh le détecte.
 *
 * Auto-suffisante : crée une commande jetable marquée, restaure l'option pixels
 * d'origine et supprime la commande à la fin (rejouable, sans effet de bord).
 *
 * Vit dans `scripts/` : seul dossier de code monté dans le conteneur wpcli.
 *
 * @package AOD
 */

defined( 'ABSPATH' ) || exit;

if ( false === strpos( home_url(), 'localhost' ) && false === strpos( home_url(), '127.0.0.1' ) ) {
	WP_CLI::error( 'Refus : ce script est réservé au dev local (home_url = ' . home_url() . ').' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'AOD_COD_Pixels' ) ) {
	WP_CLI::error( 'WooCommerce et/ou aod-cod-form ne sont pas actifs.' );
}

$pass = 0;
$fail = 0;
$ck = function ( $c, $m ) use ( &$pass, &$fail ) {
	if ( $c ) { $pass++; WP_CLI::log( "  \u{2713} $m" ); } else { $fail++; WP_CLI::log( "  \u{2717} $m" ); }
};

// Sauvegarde de l'option réelle du clone → restaurée à la fin, quoi qu'il arrive.
$saved_pixels = get_option( 'aod_cod_pixels' );

/* ── Filet de sécurité : restauration GARANTIE ──────────────────────────────
   Ce script écrit des JETONS FACTICES dans `aod_cod_pixels` (PX14, M_TOK…). Une
   fatale PHP, un OOM du conteneur wpcli (cas prévu par run.sh) ou un Ctrl-C entre
   ici et la fin laisserait le clone avec ces valeurs : il émettrait de vraies
   conversions vers des pixels inexistants, avec des jetons bidon, sans que rien ne
   le signale. `register_shutdown_function` s'exécute même après une erreur fatale —
   contrairement à un `try/finally`. Idempotent : le nettoyage nominal fait la même
   chose, ce filet ne sert que quand il n'a pas eu lieu. */
$GLOBALS['aod_capi_rec_orders'] = array();
$GLOBALS['aod_capi_rec_posts']  = array();
register_shutdown_function( function () use ( $saved_pixels ) {
	if ( false === $saved_pixels ) {
		delete_option( 'aod_cod_pixels' );
	} else {
		update_option( 'aod_cod_pixels', $saved_pixels );
	}
	foreach ( (array) $GLOBALS['aod_capi_rec_orders'] as $oid ) {
		$o = wc_get_order( (int) $oid );
		if ( $o ) {
			// Désarme d'abord la tâche de conversion de CETTE commande (ciblé).
			$args = array( (int) $oid );
			$ts   = wp_next_scheduled( AOD_COD_Pixels::DISPATCH_HOOK, $args );
			if ( $ts ) {
				wp_unschedule_event( $ts, AOD_COD_Pixels::DISPATCH_HOOK, $args );
			}
			$o->delete( true );
		}
	}
	foreach ( (array) $GLOBALS['aod_capi_rec_posts'] as $pid ) {
		if ( get_post( (int) $pid ) ) {
			wp_delete_post( (int) $pid, true );
		}
	}
} );

// Commande jetable avec des PII connues (pour prouver le hachage).
$order = wc_create_order();
$order->set_address( array(
	'first_name' => 'Amine',
	'last_name'  => 'Recette',
	'phone'      => '0551234567',
	'city'       => 'Bab Ezzouar',
	'state'      => 'DZ-16',
	'country'    => 'DZ',
), 'billing' );
$order->update_meta_data( '_aod_wilaya_name', 'Alger' );
$order->update_meta_data( '_aod_capi_recette', 1 );
$order->set_billing_email( 'Amine.Recette@Example.COM' );

// Ligne produit JETABLE, créée par la recette elle-même.
// Avant, on empruntait un produit publié du clone « s'il y en avait un » — la
// porte sortait donc ROUGE sur un clone neuf (état documenté juste après
// setup.sh, avant que le gérant n'ait créé son premier produit), alors que
// l'installation était saine. Une porte qui rougit sans défaut apprend à
// l'opérateur à ignorer une porte rouge.
// QUANTITÉ 3 à dessein : c'est ce qui prouve que les unités sont déclarées aux
// régies (TikTok contents[].quantity, Snapchat num_items) et non le nombre de
// lignes — le cas des offres par lot et des packs de ce template.
$rec_product = new WC_Product_Simple();
$rec_product->set_name( 'Recette CAPI (jetable)' );
$rec_product->set_regular_price( 1000 );
$rec_product->set_catalog_visibility( 'hidden' );
$rec_product->save();
$rec_product_id = $rec_product->get_id();
$GLOBALS['aod_capi_rec_posts'][] = $rec_product_id;
$order->add_product( $rec_product, 3 );

$order->set_total( 4000 );
$order->save();
$GLOBALS['aod_capi_rec_orders'][] = $order->get_id();

$capi = AOD_COD_Pixels::instance();

// Capteur de requête sortante : rend une réponse simulée, ne touche jamais le réseau.
$GLOBALS['aod_capi_cap']      = null; // Meta.
$GLOBALS['aod_tiktok_cap']    = null; // TikTok envoi (13.1).
$GLOBALS['aod_snap_cap']      = null; // Snapchat envoi (13.2).
$GLOBALS['aod_tt_test_cap']   = null; // TikTok test connexion (13.3).
$GLOBALS['aod_snap_test_cap'] = null; // Snapchat test connexion (13.3).
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	// --- Tests de connexion (13.3), à distinguer des envois ci-dessous ---
	// Snapchat : endpoint de validation dedie /events/validate. AVANT la branche
	// d'envoi tr.snapchat.com (dont l'URL /events est un prefixe).
	if ( false !== strpos( $url, '/events/validate' ) ) {
		$GLOBALS['aod_snap_test_cap'] = array( 'url' => $url, 'args' => $args );
		$auth = isset( $args['headers']['Authorization'] ) ? $args['headers']['Authorization'] : '';
		if ( 'Bearer SC_BAD' === $auth ) {
			return array( 'response' => array( 'code' => 401 ), 'body' => wp_json_encode( array( 'error_message' => 'Unauthorized' ) ) );
		}
		// Cas PIEGE : /events/validate repond 200 des que la requete est BIEN FORMEE
		// et met son verdict dans le CORPS (ex. pixel n'appartenant pas au compte du
		// jeton). Se fier au seul code HTTP rendait vert un pixel casse.
		if ( 'Bearer SC_WRONGPIXEL' === $auth ) {
			return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'VALIDATION_ERROR', 'reason' => 'pixel does not belong to this account' ) ) );
		}
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'VALID' ) ) );
	}
	// TikTok : evenement de test = corps portant un test_event_code. AVANT la
	// branche d'envoi (meme hote). L'envoi normal n'a pas de test_event_code.
	if ( false !== strpos( $url, 'business-api.tiktok.com' ) ) {
		$tb = json_decode( isset( $args['body'] ) ? $args['body'] : '', true );
		if ( is_array( $tb ) && ! empty( $tb['test_event_code'] ) ) {
			$GLOBALS['aod_tt_test_cap'] = array( 'url' => $url, 'args' => $args );
			$tok = isset( $args['headers']['Access-Token'] ) ? $args['headers']['Access-Token'] : '';
			if ( 'TT_BAD' === $tok ) {
				return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'code' => 40105, 'message' => 'Access token is invalid' ) ) );
			}
			return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'code' => 0, 'message' => 'OK' ) ) );
		}
	}
	// TikTok Events API — ENVOI (POST /event/track/, sans test_event_code).
	if ( false !== strpos( $url, 'business-api.tiktok.com' ) ) {
		$GLOBALS['aod_tiktok_cap'] = array( 'url' => $url, 'args' => $args );
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'code' => 0, 'message' => 'OK' ) ) );
	}
	// Snapchat Conversions API v3 — ENVOI (POST /v3/<pixel>/events). AVANT le test
	// /events generique de Meta ci-dessous : l'URL Snapchat contient aussi « /events ».
	if ( false !== strpos( $url, 'tr.snapchat.com' ) ) {
		$GLOBALS['aod_snap_cap'] = array( 'url' => $url, 'args' => $args );
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'VALID' ) ) );
	}
	// GET du nœud pixel Meta (test de connexion) vs POST /events (envoi Purchase).
	if ( false !== strpos( $url, '/events' ) ) {
		$GLOBALS['aod_capi_cap'] = array( 'url' => $url, 'args' => $args );
		return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'events_received' => 1 ) ) );
	}
	// Test de connexion (GET) : réponse selon le jeton (voir plus bas).
	if ( false !== strpos( $url, 'access_token=BADTOKEN' ) ) {
		return array( 'response' => array( 'code' => 400 ), 'body' => wp_json_encode( array( 'error' => array( 'message' => 'Invalid OAuth access token.' ) ) ) );
	}
	return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'id' => '1234567890', 'name' => 'Ma Boutique' ) ) );
}, 10, 3 );

/* ── 1) Opt-in : sans jeton, aucun appel ── */
update_option( 'aod_cod_pixels', array( 'meta' => '1234567890', 'meta_capi_token' => '' ) );
$order->delete_meta_data( '_aod_capi_sent' ); $order->save();
$GLOBALS['aod_capi_cap'] = null;
$capi->send_capi_purchase( $order );
$ck( null === $GLOBALS['aod_capi_cap'], 'Opt-in : jeton vide -> ZERO requete reseau' );

update_option( 'aod_cod_pixels', array( 'meta' => '', 'meta_capi_token' => 'EAAsecret' ) );
$order->delete_meta_data( '_aod_capi_sent' ); $order->save();
$GLOBALS['aod_capi_cap'] = null;
$capi->send_capi_purchase( $order );
$ck( null === $GLOBALS['aod_capi_cap'], 'Opt-in : pixel vide -> ZERO requete reseau' );

/* ── 2) Envoi complet dédupliqué + PII hachées + devise reporting ── */
update_option( 'aod_cod_pixels', array(
	'meta'                 => '1234567890',
	'meta_capi_token'      => 'EAAsecret_TOKEN',
	'meta_test_event_code' => 'TEST42',
	'report_currency'      => 'USD',
	'report_rate'          => '0.0075',
) );
$order->delete_meta_data( '_aod_capi_sent' ); $order->save();
$GLOBALS['aod_capi_cap'] = null;
$capi->send_capi_purchase( $order );
$cap = $GLOBALS['aod_capi_cap'];
$ck( is_array( $cap ), 'Envoi : une requete part quand pixel + jeton sont configures' );
if ( is_array( $cap ) ) {
	$body = json_decode( $cap['args']['body'], true );
	$ev   = isset( $body['data'][0] ) ? $body['data'][0] : array();
	$ck( false !== strpos( $cap['url'], 'graph.facebook.com/' ) && false !== strpos( $cap['url'], '/1234567890/events' ), 'URL = graph.facebook.com/{pixel}/events' );
	$ck( isset( $cap['args']['timeout'] ) && $cap['args']['timeout'] <= 10, 'Timeout court (fail-open, modele Turnstile)' );
	$ck( isset( $body['access_token'] ) && 'EAAsecret_TOKEN' === $body['access_token'], 'access_token transmis' );
	$ck( isset( $body['test_event_code'] ) && 'TEST42' === $body['test_event_code'], 'test_event_code transmis' );
	$ck( isset( $ev['event_name'] ) && 'Purchase' === $ev['event_name'], 'event_name = Purchase' );
	$ck( isset( $ev['action_source'] ) && 'website' === $ev['action_source'], 'action_source = website' );
	$ck( isset( $ev['event_id'] ) && (string) $order->get_order_number() === $ev['event_id'], 'event_id = n. de commande (dedup pixel)' );
	$ph = isset( $ev['user_data']['ph'][0] ) ? $ev['user_data']['ph'][0] : '';
	$ck( 1 === preg_match( '/^[a-f0-9]{64}$/', $ph ), 'user_data.ph = SHA-256 (64 hex)' );
	$ck( hash( 'sha256', '213551234567' ) === $ph, 'Tel hache = SHA-256 du numero normalise (0->213)' );
	$ck( false === strpos( wp_json_encode( $ev['user_data'] ), '0551234567' ) && false === strpos( wp_json_encode( $ev['user_data'] ), '213551234567' ), 'Aucune PII en clair dans user_data' );
	$ck( isset( $ev['user_data']['fn'][0] ) && hash( 'sha256', 'amine' ) === $ev['user_data']['fn'][0], 'Prenom hache (minuscule)' );
	$ck( isset( $ev['custom_data']['currency'] ) && 'USD' === $ev['custom_data']['currency'], 'Devise reporting appliquee (USD)' );
	$ck( isset( $ev['custom_data']['value'] ) && abs( (float) $ev['custom_data']['value'] - 30.0 ) < 0.001, 'Valeur convertie via to_reporting (4000 DZD x 0.0075 = 30)' );
}
$ck( (bool) $order->get_meta( '_aod_capi_sent' ), 'Flag _aod_capi_sent pose (envoi unique)' );

/* ── 3) Idempotence : 2e appel n'envoie rien ── */
$GLOBALS['aod_capi_cap'] = null;
$capi->send_capi_purchase( $order );
$ck( null === $GLOBALS['aod_capi_cap'], 'Idempotence : 2e appel -> ZERO requete' );

/* ── 4) Dedup : event_id du pixel navigateur == event_id CAPI ── */
$order->delete_meta_data( '_aod_pixels_fired' ); $order->save();
$payload = $capi->purchase_payload( $order );
$ck( is_array( $payload ) && (string) $order->get_order_number() === $payload['event_id'], 'event_id pixel navigateur == event_id CAPI' );

/* ── 5) Test de connexion : succes / erreur relayee / jeton jamais expose ── */
update_option( 'aod_cod_pixels', array( 'meta' => '1234567890', 'meta_capi_token' => 'GOODTOKEN' ) );
$r = $capi->test_capi_connection();
$ck( ! empty( $r['ok'] ) && false !== strpos( $r['message'], 'Ma Boutique' ), 'Test connexion : succes nomme le pixel' );
$ck( false === strpos( $r['message'], 'GOODTOKEN' ), 'Test connexion : le jeton n apparait PAS dans le message' );

update_option( 'aod_cod_pixels', array( 'meta' => '1234567890', 'meta_capi_token' => 'BADTOKEN' ) );
$r = $capi->test_capi_connection();
$ck( empty( $r['ok'] ) && false !== strpos( $r['message'], 'Invalid OAuth' ), 'Test connexion : erreur Meta relayee au gerant' );

update_option( 'aod_cod_pixels', array( 'meta' => '', 'meta_capi_token' => 'X' ) );
$r = $capi->test_capi_connection();
$ck( empty( $r['ok'] ), 'Test connexion : sans pixel -> invite, pas de succes' );

/* ══ TikTok Events API (Phase 13.1) ══ */

/* ── 6) Opt-in TikTok : sans jeton OU sans pixel, aucun appel ── */
update_option( 'aod_cod_pixels', array( 'tiktok' => 'CXXXX', 'tiktok_capi_token' => '' ) );
$order->delete_meta_data( '_aod_capi_tiktok_sent' ); $order->save();
$GLOBALS['aod_tiktok_cap'] = null;
$capi->send_tiktok_purchase( $order );
$ck( null === $GLOBALS['aod_tiktok_cap'], 'TikTok opt-in : jeton vide -> ZERO requete' );

update_option( 'aod_cod_pixels', array( 'tiktok' => '', 'tiktok_capi_token' => 'TT_secret' ) );
$order->delete_meta_data( '_aod_capi_tiktok_sent' ); $order->save();
$GLOBALS['aod_tiktok_cap'] = null;
$capi->send_tiktok_purchase( $order );
$ck( null === $GLOBALS['aod_tiktok_cap'], 'TikTok opt-in : pixel vide -> ZERO requete' );

/* ── 7) Envoi TikTok complet : URL, en-tete, event_id, PII E.164, devise ── */
update_option( 'aod_cod_pixels', array(
	'tiktok'            => 'CTESTPIXEL',
	'tiktok_capi_token' => 'TT_secret_TOKEN',
	'report_currency'   => 'USD',
	'report_rate'       => '0.0075',
) );
$order->delete_meta_data( '_aod_capi_tiktok_sent' ); $order->save();
$GLOBALS['aod_tiktok_cap'] = null;
$capi->send_tiktok_purchase( $order );
$tc = $GLOBALS['aod_tiktok_cap'];
$ck( is_array( $tc ), 'TikTok : une requete part quand pixel + jeton configures' );
if ( is_array( $tc ) ) {
	$tbody = json_decode( $tc['args']['body'], true );
	$tev   = isset( $tbody['data'][0] ) ? $tbody['data'][0] : array();
	$hdr   = isset( $tc['args']['headers']['Access-Token'] ) ? $tc['args']['headers']['Access-Token'] : '';
	$ck( false !== strpos( $tc['url'], 'business-api.tiktok.com' ) && false !== strpos( $tc['url'], '/event/track/' ), 'TikTok : URL = Events API /event/track/' );
	$ck( 'TT_secret_TOKEN' === $hdr, 'TikTok : jeton dans l en-tete Access-Token (pas le corps)' );
	$ck( isset( $tc['args']['timeout'] ) && $tc['args']['timeout'] <= 10, 'TikTok : timeout court (fail-open)' );
	$ck( isset( $tbody['event_source'] ) && 'web' === $tbody['event_source'] && 'CTESTPIXEL' === $tbody['event_source_id'], 'TikTok : event_source web + event_source_id = pixel' );
	$ck( isset( $tev['event'] ) && 'CompletePayment' === $tev['event'], 'TikTok : event = CompletePayment' );
	$ck( isset( $tev['event_id'] ) && (string) $order->get_order_number() === $tev['event_id'], 'TikTok : event_id = n. de commande (dedup navigateur)' );
	// ⚠️ Forme TikTok : chaque identifiant est une CHAÎNE, pas un tableau.
	// La version precedente assertait $tev['user']['phone'][0] — elle VERROUILLAIT
	// la forme Meta (tableau), c'est-a-dire le defaut qu'elle aurait du attraper.
	$tph = isset( $tev['user']['phone'] ) ? $tev['user']['phone'] : null;
	$ck( is_string( $tph ), 'TikTok : identifiants user = CHAINE (pas le tableau de Meta)' );
	$ck( hash( 'sha256', '+213551234567' ) === $tph, 'TikTok : tel hache = SHA-256 E.164 AVEC + (≠ Meta)' );
	$ck( false === strpos( wp_json_encode( $tev['user'] ), '0551234567' ) && false === strpos( wp_json_encode( $tev['user'] ), '213551234567' ), 'TikTok : aucune PII en clair' );
	$ck( isset( $tev['user']['first_name'] ) && hash( 'sha256', 'amine' ) === $tev['user']['first_name'], 'TikTok : prenom hache (minuscule)' );
	$ck( isset( $tev['user']['email'] ) && hash( 'sha256', 'amine.recette@example.com' ) === $tev['user']['email'], 'TikTok : e-mail hache present (identifiant au poids le plus fort)' );
	$ck( false === strpos( wp_json_encode( $tev['user'] ), 'example.com' ), 'TikTok : e-mail jamais en clair' );
	$ck( isset( $tev['properties']['currency'] ) && 'USD' === $tev['properties']['currency'] && abs( (float) $tev['properties']['value'] - 30.0 ) < 0.001, 'TikTok : devise/valeur via to_reporting (USD 30)' );
	$ck( ! empty( $tev['properties']['contents'] ), 'TikTok : properties.contents present' );
	$ck( isset( $tev['properties']['content_type'] ) && 'product' === $tev['properties']['content_type'], 'TikTok : content_type = product (requis avec contents)' );
	$tqty = isset( $tev['properties']['contents'][0]['quantity'] ) ? (int) $tev['properties']['contents'][0]['quantity'] : 0;
	$ck( 3 === $tqty, 'TikTok : contents[].quantity = 3 (la vraie quantite, pas 1 en dur)' );
}
$ck( (bool) $order->get_meta( '_aod_capi_tiktok_sent' ), 'TikTok : flag _aod_capi_tiktok_sent pose' );

/* ── 8) Idempotence TikTok ── */
$GLOBALS['aod_tiktok_cap'] = null;
$capi->send_tiktok_purchase( $order );
$ck( null === $GLOBALS['aod_tiktok_cap'], 'TikTok idempotence : 2e appel -> ZERO requete' );

/* ── 9) Dedup : purchase_payload expose event_id + tiktok pour le ttq navigateur ── */
$order->delete_meta_data( '_aod_pixels_fired' ); $order->save();
$pl = $capi->purchase_payload( $order );
$ck( is_array( $pl ) && true === $pl['tiktok'] && (string) $order->get_order_number() === $pl['event_id'], 'Dedup navigateur : payload expose tiktok=true + event_id = n. commande' );

/* ══ Snapchat Conversions API v3 (Phase 13.2) ══ */

/* ── 10) Opt-in Snapchat : sans jeton OU sans pixel, aucun appel ── */
update_option( 'aod_cod_pixels', array( 'snapchat' => 'SNAPX', 'snapchat_capi_token' => '' ) );
$order->delete_meta_data( '_aod_capi_snap_sent' ); $order->save();
$GLOBALS['aod_snap_cap'] = null;
$capi->send_snapchat_purchase( $order );
$ck( null === $GLOBALS['aod_snap_cap'], 'Snapchat opt-in : jeton vide -> ZERO requete' );

update_option( 'aod_cod_pixels', array( 'snapchat' => '', 'snapchat_capi_token' => 'SC_secret' ) );
$order->delete_meta_data( '_aod_capi_snap_sent' ); $order->save();
$GLOBALS['aod_snap_cap'] = null;
$capi->send_snapchat_purchase( $order );
$ck( null === $GLOBALS['aod_snap_cap'], 'Snapchat opt-in : pixel vide -> ZERO requete' );

/* ── 11) Envoi Snapchat complet : URL v3, en-tete Bearer, client_dedup_id, PII SANS +, devise ── */
update_option( 'aod_cod_pixels', array(
	'snapchat'            => 'SC_TEST_PIXEL',
	'snapchat_capi_token' => 'SC_secret_TOKEN',
	'report_currency'     => 'USD',
	'report_rate'         => '0.0075',
) );
$order->delete_meta_data( '_aod_capi_snap_sent' ); $order->save();
$GLOBALS['aod_snap_cap'] = null;
$capi->send_snapchat_purchase( $order );
$sc = $GLOBALS['aod_snap_cap'];
$ck( is_array( $sc ), 'Snapchat : une requete part quand pixel + jeton configures' );
if ( is_array( $sc ) ) {
	$sbody = json_decode( $sc['args']['body'], true );
	$sev   = isset( $sbody['data'][0] ) ? $sbody['data'][0] : array();
	$shdr  = isset( $sc['args']['headers']['Authorization'] ) ? $sc['args']['headers']['Authorization'] : '';
	$ck( false !== strpos( $sc['url'], 'tr.snapchat.com/v3/' ) && false !== strpos( $sc['url'], 'SC_TEST_PIXEL/events' ), 'Snapchat : URL = Conversions API v3 /v3/<pixel>/events' );
	$ck( 'Bearer SC_secret_TOKEN' === $shdr, 'Snapchat : jeton dans l en-tete Authorization Bearer' );
	$ck( false === strpos( $sc['url'], 'SC_secret_TOKEN' ), 'Snapchat : jeton ABSENT de l URL (jamais loggable)' );
	$ck( isset( $sc['args']['timeout'] ) && $sc['args']['timeout'] <= 10, 'Snapchat : timeout court (fail-open)' );
	$ck( isset( $sev['event_name'] ) && 'PURCHASE' === $sev['event_name'], 'Snapchat : event_name = PURCHASE' );
	$ck( isset( $sev['action_source'] ) && 'web' === $sev['action_source'], 'Snapchat : action_source = web' );
	$ck( isset( $sev['client_dedup_id'] ) && (string) $order->get_order_number() === $sev['client_dedup_id'], 'Snapchat : client_dedup_id = n. de commande (dedup navigateur)' );
	$sph = isset( $sev['user_data']['ph'][0] ) ? $sev['user_data']['ph'][0] : '';
	$ck( hash( 'sha256', '213551234567' ) === $sph, 'Snapchat : tel hache = SHA-256 SANS + (= Meta, ≠ TikTok)' );
	$ck( false === strpos( wp_json_encode( $sev['user_data'] ), '0551234567' ) && false === strpos( wp_json_encode( $sev['user_data'] ), '+213' ), 'Snapchat : aucune PII en clair (ni tel brut ni E.164)' );
	$ck( isset( $sev['user_data']['fn'][0] ) && hash( 'sha256', 'amine' ) === $sev['user_data']['fn'][0], 'Snapchat : prenom haché dans le champ fn (format Meta, pas first_name)' );
	// Snapchat garde la forme TABLEAU de Meta (c'est correct ici) — contrairement
	// a TikTok, dont les identifiants sont des chaines. D'ou l'index [0].
	$ck( isset( $sev['user_data']['em'][0] ) && hash( 'sha256', 'amine.recette@example.com' ) === $sev['user_data']['em'][0], 'Snapchat : em hache (minuscule) quand l e-mail est saisi' );
	$ck( false === strpos( wp_json_encode( $sev['user_data'] ), 'example.com' ), 'Snapchat : e-mail jamais en clair' );
	$ck( isset( $sev['custom_data']['currency'] ) && 'USD' === $sev['custom_data']['currency'] && abs( (float) $sev['custom_data']['value'] - 30.0 ) < 0.001, 'Snapchat : devise/valeur via to_reporting (USD 30)' );
	$ck( array_key_exists( 'content_ids', $sev['custom_data'] ), 'Snapchat : custom_data.content_ids present' );
	// num_items = somme des UNITES, pas le nombre de lignes (offre par lot / pack).
	$ck( isset( $sev['custom_data']['num_items'] ) && 3 === (int) $sev['custom_data']['num_items'], 'Snapchat : num_items = 3 unites (pas 1 ligne)' );
}
$ck( (bool) $order->get_meta( '_aod_capi_snap_sent' ), 'Snapchat : flag _aod_capi_snap_sent pose' );

/* ── 12) Idempotence Snapchat ── */
$GLOBALS['aod_snap_cap'] = null;
$capi->send_snapchat_purchase( $order );
$ck( null === $GLOBALS['aod_snap_cap'], 'Snapchat idempotence : 2e appel -> ZERO requete' );

/* ── 12bis) Champ vide -> AUCUN hash de chaine vide (garantie array_filter) ──
   L'e-mail est facultatif dans le formulaire COD. Un SHA-256 de chaine vide est
   une valeur CONSTANTE : l'envoyer ferait « correspondre » entre eux tous les
   acheteurs qui n'ont pas saisi d'e-mail. Commande dediee, sans e-mail. */
$order_noemail = wc_create_order();
$order_noemail->set_address( array(
	'first_name' => 'Sans',
	'last_name'  => 'Email',
	'phone'      => '0551234567',
	'country'    => 'DZ',
), 'billing' );
$order_noemail->save();
$GLOBALS['aod_capi_rec_orders'][] = $order_noemail->get_id();
$GLOBALS['aod_snap_cap'] = null;
$capi->send_snapchat_purchase( $order_noemail );
$sc0 = $GLOBALS['aod_snap_cap'];
if ( is_array( $sc0 ) ) {
	$sev0 = json_decode( $sc0['args']['body'], true );
	$sev0 = isset( $sev0['data'][0] ) ? $sev0['data'][0] : array();
	$ck( ! isset( $sev0['user_data']['em'] ), 'Snapchat : em ABSENT quand l e-mail est vide (jamais le hash du vide)' );
	$ck( false === strpos( wp_json_encode( $sev0['user_data'] ), hash( 'sha256', '' ) ), 'Snapchat : le SHA-256 de la chaine vide n apparait nulle part' );
}
$GLOBALS['aod_tiktok_cap'] = null;
$capi->send_tiktok_purchase( $order_noemail );
$tc0 = $GLOBALS['aod_tiktok_cap'];
if ( is_array( $tc0 ) ) {
	$tev0 = json_decode( $tc0['args']['body'], true );
	$tev0 = isset( $tev0['data'][0] ) ? $tev0['data'][0] : array();
	$ck( ! isset( $tev0['user']['email'] ), 'TikTok : email ABSENT quand l e-mail est vide' );
	$ck( false === strpos( wp_json_encode( $tev0['user'] ), hash( 'sha256', '' ) ), 'TikTok : le SHA-256 de la chaine vide n apparait nulle part' );
}
$order_noemail->delete( true );

/* ── 13) Dedup : purchase_payload expose event_id + snapchat pour le snaptr navigateur ── */
$order->delete_meta_data( '_aod_pixels_fired' ); $order->save();
$spl = $capi->purchase_payload( $order );
$ck( is_array( $spl ) && true === $spl['snapchat'] && (string) $order->get_order_number() === $spl['event_id'], 'Dedup navigateur : payload expose snapchat=true + event_id = n. commande' );

/* ── 14) Meta + TikTok + Snapchat configures -> les TROIS envois partent (metas distinctes) ── */
update_option( 'aod_cod_pixels', array(
	'meta' => '1234567890', 'meta_capi_token' => 'EAA_x',
	'tiktok' => 'CTESTPIXEL', 'tiktok_capi_token' => 'TT_x',
	'snapchat' => 'SC_TEST_PIXEL', 'snapchat_capi_token' => 'SC_x',
) );
$order->delete_meta_data( '_aod_capi_sent' ); $order->delete_meta_data( '_aod_capi_tiktok_sent' ); $order->delete_meta_data( '_aod_capi_snap_sent' ); $order->save();
$GLOBALS['aod_capi_cap'] = null; $GLOBALS['aod_tiktok_cap'] = null; $GLOBALS['aod_snap_cap'] = null;
$capi->send_capi_purchase( $order );
$capi->send_tiktok_purchase( $order );
$capi->send_snapchat_purchase( $order );
$ck( is_array( $GLOBALS['aod_capi_cap'] ) && is_array( $GLOBALS['aod_tiktok_cap'] ) && is_array( $GLOBALS['aod_snap_cap'] ), 'Meta + TikTok + Snapchat : les TROIS envois partent (gardes de meta distinctes)' );

/* ══ Tests de connexion serveur — boutons « Tester » (Phase 13.3) ══ */

/* ── 15) TikTok test : opt-in strict → invite + ZERO requete (zero pollution) ── */
update_option( 'aod_cod_pixels', array( 'tiktok' => '', 'tiktok_capi_token' => 'TT', 'tiktok_test_event_code' => 'TEST42' ) );
$GLOBALS['aod_tt_test_cap'] = null;
$r = $capi->test_tiktok_connection();
$ck( empty( $r['ok'] ) && null === $GLOBALS['aod_tt_test_cap'], 'TikTok test : sans pixel -> invite, ZERO requete' );

update_option( 'aod_cod_pixels', array( 'tiktok' => 'CPX', 'tiktok_capi_token' => '', 'tiktok_test_event_code' => 'TEST42' ) );
$GLOBALS['aod_tt_test_cap'] = null;
$r = $capi->test_tiktok_connection();
$ck( empty( $r['ok'] ) && null === $GLOBALS['aod_tt_test_cap'], 'TikTok test : sans jeton -> invite, ZERO requete' );

update_option( 'aod_cod_pixels', array( 'tiktok' => 'CPX', 'tiktok_capi_token' => 'TT_GOOD', 'tiktok_test_event_code' => '' ) );
$GLOBALS['aod_tt_test_cap'] = null;
$r = $capi->test_tiktok_connection();
$ck( empty( $r['ok'] ) && null === $GLOBALS['aod_tt_test_cap'], 'TikTok test : sans test_event_code -> invite, ZERO requete (zero pollution prod)' );

/* ── 16) TikTok test : couple valide -> succes (code 0), jeton jamais expose ── */
update_option( 'aod_cod_pixels', array( 'tiktok' => 'CPX', 'tiktok_capi_token' => 'TT_GOOD', 'tiktok_test_event_code' => 'TEST42' ) );
$GLOBALS['aod_tt_test_cap'] = null;
$r  = $capi->test_tiktok_connection();
$tt = $GLOBALS['aod_tt_test_cap'];
$ck( ! empty( $r['ok'] ), 'TikTok test : couple valide -> succes' );
$ck( is_array( $tt ) && false !== strpos( $tt['url'], '/event/track/' ), 'TikTok test : requete vers Events API /event/track/' );
if ( is_array( $tt ) ) {
	$ttb = json_decode( $tt['args']['body'], true );
	$ck( isset( $ttb['test_event_code'] ) && 'TEST42' === $ttb['test_event_code'], 'TikTok test : test_event_code transmis (route vers onglet Test)' );
	$ck( isset( $ttb['data'][0]['event'] ) && 'ViewContent' === $ttb['data'][0]['event'], 'TikTok test : evenement NEUTRE ViewContent (pas un achat)' );
	$ck( false === strpos( wp_json_encode( $ttb ), '0551234567' ), 'TikTok test : aucune PII dans l evenement de test' );
	// TikTok exige au moins un identifiant dans `user` : un evenement sans aucun
	// se fait refuser tout court, et un jeton VALIDE ressortirait « invalide ».
	$ck( ! empty( $ttb['data'][0]['user'] ), 'TikTok test : l evenement porte un objet user (sinon refus systematique)' );
}
$ck( false === strpos( $r['message'], 'TT_GOOD' ), 'TikTok test : le jeton n apparait PAS dans le verdict' );

/* ── 17) TikTok test : jeton invalide -> refus relaye, jeton non expose ── */
update_option( 'aod_cod_pixels', array( 'tiktok' => 'CPX', 'tiktok_capi_token' => 'TT_BAD', 'tiktok_test_event_code' => 'TEST42' ) );
$r = $capi->test_tiktok_connection();
$ck( empty( $r['ok'] ) && false !== strpos( $r['message'], 'TikTok' ), 'TikTok test : jeton invalide -> refus relaye' );
$ck( false === strpos( $r['message'], 'TT_BAD' ), 'TikTok test : jeton invalide jamais expose' );

/* ── 18) Snapchat test : opt-in -> invite + ZERO requete ── */
update_option( 'aod_cod_pixels', array( 'snapchat' => '', 'snapchat_capi_token' => 'SC' ) );
$GLOBALS['aod_snap_test_cap'] = null;
$r = $capi->test_snapchat_connection();
$ck( empty( $r['ok'] ) && null === $GLOBALS['aod_snap_test_cap'], 'Snapchat test : sans pixel -> invite, ZERO requete' );

/* ── 19) Snapchat test : valide -> /events/validate, en-tete Bearer, jeton hors URL/verdict ── */
update_option( 'aod_cod_pixels', array( 'snapchat' => 'SPX', 'snapchat_capi_token' => 'SC_GOOD' ) );
$GLOBALS['aod_snap_test_cap'] = null;
$r  = $capi->test_snapchat_connection();
$st = $GLOBALS['aod_snap_test_cap'];
$ck( ! empty( $r['ok'] ), 'Snapchat test : couple valide -> succes' );
$ck( is_array( $st ) && false !== strpos( $st['url'], '/events/validate' ), 'Snapchat test : endpoint de validation dedie (zero pollution)' );
if ( is_array( $st ) ) {
	$sh = isset( $st['args']['headers']['Authorization'] ) ? $st['args']['headers']['Authorization'] : '';
	$ck( 'Bearer SC_GOOD' === $sh, 'Snapchat test : jeton en en-tete Authorization Bearer' );
	$ck( false === strpos( $st['url'], 'SC_GOOD' ), 'Snapchat test : jeton ABSENT de l URL' );
}
$ck( false === strpos( $r['message'], 'SC_GOOD' ), 'Snapchat test : le jeton n apparait PAS dans le verdict' );

/* ── 19bis) Snapchat test : HTTP 200 mais verdict d'ECHEC dans le corps ──
   Le piege que ce bouton existe pour attraper : /events/validate repond 200 des
   que la requete est bien formee. Un pixel qui n'appartient pas au compte du
   jeton ressortait « Connexion reussie » — le gerant arretait d'investiguer
   pendant qu'aucun achat n'atteignait Snapchat (l'envoi est fail-open). */
update_option( 'aod_cod_pixels', array( 'snapchat' => 'SPX', 'snapchat_capi_token' => 'SC_WRONGPIXEL' ) );
$r = $capi->test_snapchat_connection();
$ck( empty( $r['ok'] ), 'Snapchat test : 200 + verdict d echec dans le corps -> ECHEC (pas un faux vert)' );
$ck( false !== strpos( $r['message'], 'pixel does not belong' ), 'Snapchat test : le motif de refus de Snapchat est relaye' );
$ck( false === strpos( $r['message'], 'SC_WRONGPIXEL' ), 'Snapchat test : jeton jamais expose meme en echec de validation' );

/* ── 20) Snapchat test : jeton invalide (401) -> echec, jeton non expose ── */
update_option( 'aod_cod_pixels', array( 'snapchat' => 'SPX', 'snapchat_capi_token' => 'SC_BAD' ) );
$r = $capi->test_snapchat_connection();
$ck( empty( $r['ok'] ), 'Snapchat test : jeton invalide (401) -> echec' );
$ck( false === strpos( $r['message'], 'SC_BAD' ), 'Snapchat test : jeton invalide jamais expose' );

/* ══════════════════════════════════════════════════════════════════════════
   Phase 14.1 — les conversions serveur SORTENT du chemin d'achat.
   Ce qui doit etre prouve : l'acheteur n'attend plus AUCUN appel reseau, ET
   l'envoi differe porte EXACTEMENT les memes identifiants qu'avant — cookies
   d'appariement compris, alors qu'un cron n'a aucun cookie.
   ══════════════════════════════════════════════════════════════════════════ */

update_option( 'aod_cod_pixels', array(
	'meta'                => 'PX14',
	'meta_capi_token'     => 'M_TOK',
	'tiktok'              => 'CPX14',
	'tiktok_capi_token'   => 'T_TOK',
	'snapchat'            => 'SPX14',
	'snapchat_capi_token' => 'S_TOK',
) );

$o14 = wc_create_order();
$o14->set_address( array(
	'first_name' => 'Amine',
	'last_name'  => 'Differe',
	'phone'      => '0551234567',
	'country'    => 'DZ',
), 'billing' );
$o14->set_total( 4000 );
$o14->save();
$GLOBALS['aod_capi_rec_orders'][] = $o14->get_id();

// Cookies d'appariement, tels qu'ils existent DANS LA REQUETE ACHETEUR.
$_COOKIE['_fbp']  = 'fb.1.1700000000.111';
$_COOKIE['_ttp']  = 'ttp-abc123';
$_COOKIE['_scid'] = 'scid-xyz789';

/* ── 21) Chemin acheteur : mise en file, ZERO appel HTTP ── */
$GLOBALS['aod_capi_cap'] = null; $GLOBALS['aod_tiktok_cap'] = null; $GLOBALS['aod_snap_cap'] = null;
do_action( 'aod_cod_order_created', $o14 );
$ck( null === $GLOBALS['aod_capi_cap'] && null === $GLOBALS['aod_tiktok_cap'] && null === $GLOBALS['aod_snap_cap'],
	'14.1 : le chemin acheteur ne declenche AUCUN appel HTTP de conversion' );
$ck( (bool) wp_next_scheduled( AOD_COD_Pixels::DISPATCH_HOOK, array( $o14->get_id() ) ),
	'14.1 : une tache d envoi est planifiee pour la commande' );

/* ── 22) « Mis en file » n est PAS « envoye » (cron mort -> rejouable) ── */
$o14r = wc_get_order( $o14->get_id() );
$ck( ! $o14r->get_meta( '_aod_capi_sent' ) && ! $o14r->get_meta( '_aod_capi_tiktok_sent' ) && ! $o14r->get_meta( '_aod_capi_snap_sent' ),
	'14.1 : aucune garde d envoi posee a la mise en file (un cron mort reste rattrapable)' );

/* ── 23) Les cookies d appariement sont captures en meta ── */
$saved = $o14r->get_meta( '_aod_conv_cookies' );
$ck( is_array( $saved ) && 'fb.1.1700000000.111' === ( isset( $saved['_fbp'] ) ? $saved['_fbp'] : '' ),
	'14.1 : cookies d appariement captures dans la commande (_fbp)' );
$ck( is_array( $saved ) && isset( $saved['_ttp'] ) && isset( $saved['_scid'] ),
	'14.1 : cookies TikTok (_ttp) et Snapchat (_scid) captures aussi' );
$ck( is_array( $saved ) && ! array_key_exists( '_fbc', $saved ),
	'14.1 : un cookie absent n est pas stocke a vide' );

/* ── 24) CONTEXTE CRON : plus aucun cookie -> le payload doit rester complet ──
   C'est LE test de la phase. Sans la capture, on aurait troque de la latence
   contre de la qualite d appariement, en silence (socle fail-open). */
unset( $_COOKIE['_fbp'], $_COOKIE['_ttp'], $_COOKIE['_scid'] );
$GLOBALS['aod_capi_cap'] = null; $GLOBALS['aod_tiktok_cap'] = null; $GLOBALS['aod_snap_cap'] = null;
$capi->dispatch_server_conversions( $o14->get_id() );

$ck( is_array( $GLOBALS['aod_capi_cap'] ) && is_array( $GLOBALS['aod_tiktok_cap'] ) && is_array( $GLOBALS['aod_snap_cap'] ),
	'14.1 : l envoi differe part bien pour les TROIS regies' );
if ( is_array( $GLOBALS['aod_capi_cap'] ) ) {
	$m14 = json_decode( $GLOBALS['aod_capi_cap']['args']['body'], true );
	$m14 = isset( $m14['data'][0] ) ? $m14['data'][0] : array();
	$ck( isset( $m14['user_data']['fbp'] ) && 'fb.1.1700000000.111' === $m14['user_data']['fbp'],
		'14.1 : Meta — _fbp present dans l envoi differe (sans aucun cookie vivant)' );
	$ck( isset( $m14['event_id'] ) && (string) $o14->get_order_number() === $m14['event_id'],
		'14.1 : Meta — event_id de dedup intact malgre le differe' );
}
if ( is_array( $GLOBALS['aod_tiktok_cap'] ) ) {
	$t14 = json_decode( $GLOBALS['aod_tiktok_cap']['args']['body'], true );
	$t14 = isset( $t14['data'][0] ) ? $t14['data'][0] : array();
	$ck( isset( $t14['user']['ttp'] ) && 'ttp-abc123' === $t14['user']['ttp'],
		'14.1 : TikTok — _ttp present dans l envoi differe' );
	$ck( isset( $t14['event_id'] ) && (string) $o14->get_order_number() === $t14['event_id'],
		'14.1 : TikTok — event_id de dedup intact malgre le differe' );
}
if ( is_array( $GLOBALS['aod_snap_cap'] ) ) {
	$s14 = json_decode( $GLOBALS['aod_snap_cap']['args']['body'], true );
	$s14 = isset( $s14['data'][0] ) ? $s14['data'][0] : array();
	$ck( isset( $s14['user_data']['sc_cookie1'] ) && 'scid-xyz789' === $s14['user_data']['sc_cookie1'],
		'14.1 : Snapchat — _scid present dans l envoi differe' );
	$ck( isset( $s14['client_dedup_id'] ) && (string) $o14->get_order_number() === $s14['client_dedup_id'],
		'14.1 : Snapchat — client_dedup_id intact malgre le differe' );
}

/* ── 25) Idempotence du differe : un cron rejoue n envoie pas deux fois ── */
$GLOBALS['aod_capi_cap'] = null; $GLOBALS['aod_tiktok_cap'] = null; $GLOBALS['aod_snap_cap'] = null;
$capi->dispatch_server_conversions( $o14->get_id() );
$ck( null === $GLOBALS['aod_capi_cap'] && null === $GLOBALS['aod_tiktok_cap'] && null === $GLOBALS['aod_snap_cap'],
	'14.1 : dispatch rejoue -> ZERO requete (gardes d envoi unique)' );

/* ── 26) Opt-in : aucune regie armee -> AUCUNE tache planifiee ── */
update_option( 'aod_cod_pixels', array( 'meta' => 'PX14', 'meta_capi_token' => '' ) );
$o14b = wc_create_order();
$o14b->set_address( array( 'phone' => '0551234567', 'country' => 'DZ' ), 'billing' );
$o14b->save();
$GLOBALS['aod_capi_rec_orders'][] = $o14b->get_id();
do_action( 'aod_cod_order_created', $o14b );
$ck( false === wp_next_scheduled( AOD_COD_Pixels::DISPATCH_HOOK, array( $o14b->get_id() ) ),
	'14.1 : sans jeton, AUCUNE tache cron fantome n est planifiee' );

/* Nettoyage de la section 14.1 — CIBLÉ.
   ⚠️ Surtout PAS wp_unschedule_hook( DISPATCH_HOOK ) : il desarme TOUTES les taches
   de conversion du clone, y compris celles de vraies commandes en attente d'envoi
   differe (Phase 14.1). On perdrait des conversions, en silence. On ne desarme que
   ce que CETTE recette a planifie. */
foreach ( array( $o14->get_id(), $o14b->get_id() ) as $rec_oid ) {
	$args = array( $rec_oid );
	$ts   = wp_next_scheduled( AOD_COD_Pixels::DISPATCH_HOOK, $args );
	if ( $ts ) {
		wp_unschedule_event( $ts, AOD_COD_Pixels::DISPATCH_HOOK, $args );
	}
}
$o14->delete( true );
$o14b->delete( true );

/* ══ Deduplication NAVIGATEUR (Phase 13.4) : le JS pose l'id de dedup des 3 regies ══ */
/* On lit la SOURCE JS (bind-montee dans wpcli) : un refactor qui retire l'id de dedup
   d'un cote navigateur ferait compter l'achat DEUX fois — ce filet le casse. */
$js = @file_get_contents( WP_PLUGIN_DIR . '/aod-cod-form/assets/js/aod-cod-form.js' );
$ck( is_string( $js ) && '' !== $js, 'Navigateur : source JS de conversion lisible' );
$js = (string) $js;
$ck( 1 === preg_match( '/fbq\([^;]*eventID/', $js ), 'Navigateur : fbq pose { eventID } (dedup Meta)' );
$ck( 1 === preg_match( '/ttq\.track\([^;]*event_id/', $js ), 'Navigateur : ttq.track pose { event_id } (dedup TikTok)' );
$ck( 1 === preg_match( '/\.client_dedup_id\s*=\s*px\.event_id/', $js ) && false !== strpos( $js, 'snaptr(' ), 'Navigateur : snaptr pose client_dedup_id = event_id (dedup Snapchat)' );

/* Le filet ci-dessus ne lisait QUE le JS du formulaire. Or purchase_pixels()
   (rendu PHP sur woocommerce_thankyou) emet les MEMES evenements par un autre
   chemin — et n'avait recu ni event_id TikTok ni client_dedup_id Snapchat quand
   les envois serveur 13.1/13.2 sont arrives : tout achat passant par la page de
   remerciement comptait DEUX fois. On lit donc aussi la source PHP. */
$php = @file_get_contents( WP_PLUGIN_DIR . '/aod-cod-form/includes/class-aod-cod-pixels.php' );
$ck( is_string( $php ) && '' !== $php, 'Thank-you : source PHP de conversion lisible' );
$php = (string) $php;
$ck( 1 === preg_match( "/ttq\.track\('CompletePayment'[^\n]*event_id/", $php ), 'Thank-you : ttq.track PHP pose event_id (dedup TikTok)' );
$ck( 1 === preg_match( "/snaptr\('track','PURCHASE'[^\n]*client_dedup_id/", $php ), 'Thank-you : snaptr PHP pose client_dedup_id (dedup Snapchat)' );

/* ── Nettoyage : restaure l'option d'origine + supprime la commande jetable ── */
if ( false === $saved_pixels ) { delete_option( 'aod_cod_pixels' ); } else { update_option( 'aod_cod_pixels', $saved_pixels ); }
$order->delete( true );
// Produit jetable cree par la recette : purge (rien ne doit rester au catalogue).
if ( ! empty( $rec_product_id ) ) {
	wp_delete_post( (int) $rec_product_id, true );
}

WP_CLI::log( "\n=== CAPI : $pass \u{2713} / $fail \u{2717} ===" );
if ( $fail > 0 ) {
	WP_CLI::error( "$fail assertion(s) CAPI en echec." );
}
WP_CLI::success( 'Recette CAPI : toutes les assertions passent.' );
