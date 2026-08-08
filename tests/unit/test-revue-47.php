<?php
/**
 * Ce que la revue de code de la Phase 47 a trouvé — et qui doit rester fermé.
 *
 * 🚨 LE FIL COMMUN DES DIX DÉFAUTS N'EST PAS DIX ERREURS, C'EST UN MODE DE DÉRIVE :
 * une décision propagée à UN jumeau et pas à l'autre. La case d'envoi automatique
 * retirée de `/gestion` mais laissée dans wp-admin ; la cellule « Suivi » renvoyée
 * par les deux chemins unitaires et pas par le chemin GROUPÉ ; les compteurs de
 * refus d'image tissés dans la galerie et les options, mais pas dans la photo
 * principale, qui continuait d'interrompre tout ; la résolution du transporteur
 * réduite à `carrier_for()` sauf aux deux endroits du formulaire public.
 *
 * 🚨 ET LE PLUS GRAVE N'ÉTAIT PAS UNE PROPAGATION MANQUÉE, MAIS UNE CONDITION
 * PERDUE EN CHANGEANT DE DÉCLENCHEUR. Tant que l'envoi partait du hook
 * `woocommerce_order_status_aod-confirmed`, le hook ÉTAIT la condition : seule une
 * commande confirmée pouvait expédier. La 47.2 a déplacé le déclencheur — et la
 * condition est partie avec lui, sans que personne l'écrive. Résultat : une
 * commande neuve, et même une commande ANNULÉE, se trouvaient à un clic d'un colis
 * réel, facturé, irréversible (rien ne revient en arrière une fois `META_TRACKING`
 * posé). *Une garantie portée par un mécanisme disparaît avec lui ; il faut la
 * réécrire là où elle n'était pas écrite.*
 *
 * ⚠️ AUCUNE ASSERTION DE CE FICHIER N'APPELLE UN TRANSPORTEUR. Un clone PEUT avoir
 * un compte livreur réel configuré — c'était le cas ici à l'écriture — et appeler
 * `ship_order()` sur une commande confirmée y créerait alors un vrai colis,
 * facturé, sans retour arrière. L'ancrage inverse de R1 pose donc le VERROU
 * d'envoi à la main : la commande est confirmée, elle franchit la garde de statut,
 * et se fait refuser plus loin, sans un octet de réseau. C'est le seul moyen de
 * prouver qu'on est passé au-delà d'une garde sans exécuter ce qu'elle protège.
 *
 * 🔁 LA PHRASE D'ORIGINE DISAIT « CE CLONE A UN COMPTE YALIDINE RÉEL CONFIGURÉ »,
 * AU PRÉSENT DE L'INDICATIF, et c'est devenu faux sans que personne ne l'écrive :
 * la configuration a été retirée depuis. Une assertion de ce fichier s'appuyait
 * dessus et s'est mise à rougir (cf. la note de l'ancrage inverse, plus bas).
 * 🚨 *Un commentaire qui décrit l'ÉTAT d'un clone se périme ; seul ce qui décrit
 * un INVARIANT du code reste vrai. Quand on a besoin de l'état, il faut le lire
 * au moment du test, pas l'avoir écrit une fois.*
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

$aod_r47_ok = class_exists( 'AOD_Shipping' ) && class_exists( 'AOD_Carrier' );
AOD_Test::ok( $aod_r47_ok, 'TÉMOIN : le module d’expédition est chargé' );
if ( ! $aod_r47_ok ) {
	return;
}
$aod_r47_ship = AOD_Shipping::instance();

/* =========================================================================
 * R1 — Seule une commande CONFIRMÉE peut partir chez le livreur.
 * ========================================================================= */

AOD_Test::group( 'Revue 47 — R1 : l’envoi exige une commande confirmée' );

$aod_r47_cmd = wc_create_order();
if ( ! $aod_r47_cmd instanceof WC_Order ) {
	AOD_Test::ok( false, 'Commande jetable créée', 'wc_create_order() a échoué' );
	return;
}
$aod_r47_cmd->update_meta_data( '_aod_delivery_type', 'home' );
$aod_r47_cmd->update_meta_data( '_aod_wilaya_code', 16 );
$aod_r47_cmd->set_status( 'processing' ); // Le statut d'une commande COD fraîche.
$aod_r47_cmd->save();
$aod_r47_id = (int) $aod_r47_cmd->get_id();

// 🚨 PURGE ARMÉE TOUT DE SUITE, pas seulement à la fin. La purge finale de R2 ne
// s'exécute que si tout ce qui précède se déroule ; un red-proof a suffi à le
// démontrer — une assertion mutée, et la commande témoin est restée publiée dans
// le clone (constatée, puis retirée à la main). Leçon D26 : un témoin ne doit
// jamais survivre à l'interruption de son test.
register_shutdown_function( function () use ( $aod_r47_id ) {
	$reste = wc_get_order( $aod_r47_id );
	if ( $reste ) {
		$reste->delete( true );
	}
} );

AOD_Test::ok(
	'processing' === wc_get_order( $aod_r47_id )->get_status(),
	'TÉMOIN : la commande témoin est bien NON confirmée (statut « processing »)'
);

$aod_r47_refus = $aod_r47_ship->ship_order( wc_get_order( $aod_r47_id ) );
AOD_Test::ok(
	is_wp_error( $aod_r47_refus ) && 'aod_ship_not_confirmed' === $aod_r47_refus->get_error_code(),
	'🔴 Une commande NON confirmée est refusée à l’envoi',
	( is_wp_error( $aod_r47_refus ) ? 'code rendu : ' . $aod_r47_refus->get_error_code() : 'AUCUN refus — un colis réel serait parti pour une commande que personne n’a appelée' )
);

AOD_Test::ok(
	'' === (string) wc_get_order( $aod_r47_id )->get_meta( AOD_Carrier::META_TRACKING ),
	'🔴 …et rien n’a été expédié (aucun numéro de suivi)',
	'un numéro de suivi existe : le refus n’en était pas un'
);

// ANCRAGE INVERSE — sans lui, R1 serait verte sur un `ship_order()` qui refuserait
// TOUT. On confirme la commande et on pose le VERROU : elle doit désormais franchir
// la garde de statut et se faire arrêter PLUS LOIN, par le verrou. Le code de refus
// change → la garde de statut a bien été dépassée, sans qu'aucun colis ne parte.
$aod_r47_lock = new ReflectionMethod( 'AOD_Shipping', 'acquire_send_lock' );
$aod_r47_lock->setAccessible( true );
$aod_r47_unlock = new ReflectionMethod( 'AOD_Shipping', 'release_send_lock' );
$aod_r47_unlock->setAccessible( true );

$aod_r47_cmd2 = wc_get_order( $aod_r47_id );
$aod_r47_cmd2->set_status( 'aod-confirmed' );
$aod_r47_cmd2->save();

AOD_Test::ok(
	(bool) $aod_r47_lock->invoke( $aod_r47_ship, $aod_r47_id ),
	'TÉMOIN : le verrou d’envoi est posé (sinon l’ancrage déclencherait un VRAI envoi)'
);

/*
 * 🔴 CET ANCRAGE EXIGEAIT UN CODE UNIQUE, ET IL S'EST MIS À ROUGIR SANS QUE RIEN
 * NE CASSE (corrigé le 2026-08-07). Il attendait `aod_ship_in_progress` — le
 * verrou — et recevait `aod_ship_no_carrier` : ce clone n'a plus de transporteur
 * configuré, alors que le docblock de ce fichier affirme encore le contraire
 * (« Ce clone a un compte Yalidine RÉEL configuré »). L'affirmation était vraie le
 * jour où elle a été écrite ; la configuration a dérivé depuis, et l'assertion
 * s'est mise à mesurer l'état du clone au lieu de l'invariant.
 *
 * 🚨 CE QUE L'ANCRAGE DOIT PROUVER, C'EST QU'ON A FRANCHI LA GARDE DE STATUT —
 * rien d'autre. Or l'ordre des gardes de `ship_order()` est fixe et lisible :
 *   commande invalide → pas COD → déjà expédiée → **STATUT** → transporteur → verrou
 * Tout code postérieur au statut démontre donc exactement la même chose. Exiger
 * `aod_ship_in_progress` revenait à exiger, en plus, qu'un transporteur soit
 * configuré — une condition qui n'a rien à voir avec ce qu'on éprouve.
 * *Une assertion qui nomme UN résultat là où la propriété en autorise plusieurs
 * mesure le décor autour de l'invariant, pas l'invariant.*
 *
 * ⚠️ LA LISTE RESTE FERMÉE, et c'est ce qui garde le mordant : `aod_ship_bad_order`,
 * `aod_ship_not_cod`, `aod_ship_already_shipped` et surtout `aod_ship_not_confirmed`
 * en sont EXCLUS. Si la garde de statut cessait d'être franchie, le code rendu
 * serait l'un de ceux-là et l'ancrage rougirait — ce pour quoi il existe.
 * 🔒 Et le verrou reste posé même quand il ne sert pas à arrêter l'appel : sur un
 * clone CONFIGURÉ, c'est lui qui empêche un colis réel de partir. Le retirer sous
 * prétexte qu'un autre refus suffit ici ferait dépendre la sûreté du test de
 * l'absence de configuration.
 */
$aod_r47_apres = $aod_r47_ship->ship_order( wc_get_order( $aod_r47_id ) );
$aod_r47_apres_gardes = array( 'aod_ship_in_progress', 'aod_ship_no_carrier', 'aod_ship_default_unconfigured' );
$aod_r47_apres_code   = is_wp_error( $aod_r47_apres ) ? $aod_r47_apres->get_error_code() : '';
AOD_Test::ok(
	in_array( $aod_r47_apres_code, $aod_r47_apres_gardes, true ),
	sprintf(
		'🔴 ANCRAGE INVERSE : une fois CONFIRMÉE, elle franchit la garde de statut (arrêtée plus loin par « %s »)',
		$aod_r47_apres_code
	),
	( '' === $aod_r47_apres_code ? 'AUCUNE erreur — un colis a pu partir' : 'code rendu : ' . $aod_r47_apres_code )
		. ' — attendu un refus POSTÉRIEUR au statut (' . implode( ', ', $aod_r47_apres_gardes ) . ')'
);

$aod_r47_unlock->invoke( $aod_r47_ship, $aod_r47_id );

/* =========================================================================
 * R2 — Une commande ANNULÉE ne propose plus « Réessayer ».
 * ========================================================================= */

AOD_Test::group( 'Revue 47 — R2 : l’annulation prime sur la trace d’un échec passé' );

$aod_r47_e = wc_get_order( $aod_r47_id );
$aod_r47_e->update_meta_data( AOD_Carrier::META_ERROR, 'Panne transporteur (témoin de test)' );
$aod_r47_e->set_status( 'aod-confirmed' );
$aod_r47_e->save();

AOD_Test::ok(
	'failed' === $aod_r47_ship->ship_status( wc_get_order( $aod_r47_id ) ),
	'TÉMOIN : confirmée + erreur d’envoi ⇒ état « failed » (sinon R2 mesurerait du vide)',
	'état lu : ' . $aod_r47_ship->ship_status( wc_get_order( $aod_r47_id ) )
);

$aod_r47_e = wc_get_order( $aod_r47_id );
$aod_r47_e->set_status( 'cancelled' );
$aod_r47_e->save();

AOD_Test::ok(
	'none' === $aod_r47_ship->ship_status( wc_get_order( $aod_r47_id ) ),
	'🔴 Annulée AVEC une erreur d’envoi ⇒ « none », donc aucun bouton « Réessayer »',
	'état lu : ' . $aod_r47_ship->ship_status( wc_get_order( $aod_r47_id ) )
		. ' — un clic créerait un colis réel pour une commande que la boutique vient d’annuler'
);

AOD_Test::ok(
	is_wp_error( $aod_r47_ship->ship_order( wc_get_order( $aod_r47_id ) ) ),
	'🔴 …et le serveur la refuse aussi (l’écran n’est pas la seule garde)',
	'un POST forgé sur `aod_cd_ship_send` ne passe pas par le rendu'
);

// ⚠️ `$order->delete( true )`, PAS `wp_delete_post()` : HPOS est actif, la commande
// ne vit pas dans `wp_posts`. Écrit ici parce que la première version de ce fichier
// s'est fait prendre — l'assertion de purge a rougi, ce qui est exactement son rôle.
$aod_r47_fin = wc_get_order( $aod_r47_id );
if ( $aod_r47_fin ) {
	$aod_r47_fin->delete( true );
}
AOD_Test::ok( ! wc_get_order( $aod_r47_id ), 'La commande jetable est supprimée' );

/* =========================================================================
 * R3 — Les propagations manquées, lues à la source.
 *
 * Ces quatre-là sont des CÂBLAGES : ils ne se rejouent pas depuis un test
 * unitaire (un rendu HTML, une réponse AJAX, un continuation JS), mais leur
 * absence est exactement ce qui a produit les défauts. On garde donc le fait
 * qu'ils sont branchés.
 * ========================================================================= */

AOD_Test::group( 'Revue 47 — R3 : les jumeaux ne divergent plus' );

$aod_r47_lire = function ( $chemin ) {
	return is_readable( $chemin ) ? (string) file_get_contents( $chemin ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du dépôt, contexte de test.
};

$aod_r47_cod  = defined( 'AOD_COD_PATH' ) ? AOD_COD_PATH : dirname( AOD_CD_PATH ) . '/aod-cod-form/';
$aod_r47_shp  = $aod_r47_lire( $aod_r47_cod . 'includes/class-aod-shipping.php' );
$aod_r47_form = $aod_r47_lire( $aod_r47_cod . 'includes/class-aod-cod-form.php' );
$aod_r47_ord  = $aod_r47_lire( AOD_CD_PATH . 'includes/class-aod-cd-section-orders.php' );
$aod_r47_ojs  = $aod_r47_lire( AOD_CD_PATH . 'assets/js/section-orders.js' );
$aod_r47_tjs  = $aod_r47_lire( AOD_CD_PATH . 'assets/js/section-team.js' );
$aod_r47_pro  = $aod_r47_lire( AOD_CD_PATH . 'includes/class-aod-cd-section-products.php' );

AOD_Test::ok(
	'' !== $aod_r47_shp && '' !== $aod_r47_form && '' !== $aod_r47_ord
		&& '' !== $aod_r47_ojs && '' !== $aod_r47_tjs && '' !== $aod_r47_pro,
	'TÉMOIN : les six fichiers concernés sont lus'
);

// (a) wp-admin n'expose plus une case qui ne commande rien.
AOD_Test::ok(
	false === strpos( $aod_r47_shp, 'auto[enabled]' ),
	'🔴 wp-admin n’affiche plus la case « envoi automatique » (elle ne commandait plus rien)',
	'l’écran promettrait « le bordereau part alors tout seul » sur un déclencheur supprimé en 47.2'
);

// (b) Le chemin GROUPÉ renvoie les cellules « Suivi », comme les deux unitaires.
AOD_Test::ok(
	preg_match( '/function ajax_orders_bulk_carrier.*?\'cells\'\s*=>/s', $aod_r47_ord ) === 1,
	'🔴 Le routage EN LOT renvoie les cellules « Suivi » rafraîchies',
	'`data-note-perdue` resterait périmé sur toutes les lignes du lot : la note du confirmateur partirait perdue, sans avertissement'
);
AOD_Test::ok(
	false !== strpos( $aod_r47_ojs, 'res.data.cells' ),
	'🔴 …et le JS du lot les applique',
	'le serveur les envoie, personne ne les pose'
);

// (c) La photo principale COMPTE son refus au lieu d'interrompre la soumission.
AOD_Test::ok(
	preg_match( '/AOD_CD_Upload::handle\( \'image\', \$new_id \);\s*\n\s*if \( is_wp_error\( \$att_id \) \) \{\s*\n\s*\+\+\$img_refusees;/', $aod_r47_pro ) === 1,
	'🔴 Un refus sur la photo PRINCIPALE est compté, pas bloquant',
	'un `wp_send_json_error()` ici jetait en silence la galerie et les variantes saisies dans la même soumission'
);

// (d) Le formulaire public ne résout plus le transporteur à la main.
AOD_Test::ok(
	preg_match( '/\$shipping->carrier\( \$auto\[\'carrier\'\] \)|auto_settings\(\)\[\'carrier\'\] \)/', $aod_r47_form ) !== 1,
	'🔴 Le formulaire public ne refait plus la résolution du transporteur',
	'il lisait l’étape 2 sans l’étape 3 : il pouvait offrir le stop-desk pour un livreur qui n’en fait pas'
);
AOD_Test::ok(
	substr_count( $aod_r47_form, 'carrier_for()' ) >= 2,
	'🔴 …il passe par `carrier_for()`, la seule résolution du plugin (47.1)',
	'occurrences trouvées : ' . substr_count( $aod_r47_form, 'carrier_for()' )
);

// (e) Le rechargement attend que le mot de passe généré ait été lu.
AOD_Test::ok(
	preg_match( '/okOnly: true\s*\n\s*\}\s*\)\.then\( recharger \)/', $aod_r47_tjs ) === 1,
	'🔴 Le rechargement de la page ATTEND la fermeture de l’avis « mot de passe généré »',
	'la fenêtre maison ne bloque pas le fil JS : le rechargement l’emporterait, et le mot de passe est affiché UNE SEULE FOIS'
);

// (f) Plus un seul message qui parle d'un envoi automatique supprimé.
AOD_Test::ok(
	false === strpos( $aod_r47_shp, 'Envoi automatique annulé' ),
	'🔴 Aucun message ne parle encore d’un « envoi automatique » (supprimé en 47.2)',
	'un confirmateur qui clique « Envoyer » lirait qu’un envoi AUTOMATIQUE a été annulé'
);
