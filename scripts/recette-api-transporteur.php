<?php
/**
 * Recette RÉSEAU d'un transporteur — confronte nos parseurs à la VRAIE API.
 *
 * Pourquoi ce script existe : nos lectures de tarifs et de suivi sont
 * volontairement « tolérantes » (plusieurs clés candidates). Une lecture tolérante
 * ne dit jamais qu'elle s'est trompée — elle renvoie simplement du vide, ou pire,
 * un statut « inconnu ». C'est exactement ce qui s'est produit avec E-com Delivery :
 * l'import de tarifs échouait en silence (schéma `wilayas[]`/`domicile` non reconnu)
 * et les colis annulés remontaient en « inconnu », donc comptés « en transit » dans
 * les statistiques. Deux mois sans que rien ne le signale.
 *
 * Ce script rejoue la confrontation à la demande. À lancer quand un transporteur
 * annonce une nouvelle version d'API, ou en recette d'un nouveau clone.
 *
 * LECTURE SEULE : aucun colis n'est créé, aucun tarif n'est écrit.
 *
 * Usage (identifiants déjà saisis dans /gestion → Livraison → Transporteurs) :
 *
 *   docker compose exec -T wpcli wp eval-file /scripts/recette-api-transporteur.php
 *   docker compose exec -T wpcli wp eval-file /scripts/recette-api-transporteur.php zrexpress
 *   docker compose exec -T wpcli wp eval-file /scripts/recette-api-transporteur.php zrexpress EC812HMB
 *
 * Arg 1 : id du transporteur (défaut : tous ceux qui sont configurés).
 * Arg 2 : un numéro de suivi RÉEL, pour éprouver aussi le parseur de suivi.
 *
 * @package AOD_COD_Form
 */

if ( ! class_exists( 'AOD_Shipping' ) ) {
	WP_CLI::error( 'Le plugin aod-cod-form n’est pas actif.' );
}

$only     = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';
$tracking = isset( $args[1] ) ? trim( (string) $args[1] ) : '';
$fails    = 0;

$ok = function ( $cond, $msg, $extra = '' ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	WP_CLI::log( ( $cond ? '  ✅ ' : '  ❌ ' ) . $msg . ( '' !== $extra ? ' — ' . $extra : '' ) );
	return (bool) $cond;
};

foreach ( AOD_Shipping::instance()->carriers() as $id => $carrier ) {
	if ( '' !== $only && $id !== $only ) {
		continue;
	}
	if ( ! $carrier->is_configured() ) {
		continue;
	}

	WP_CLI::log( "\n▸ " . $carrier->label() . " ({$id})" );

	// 1. Identifiants.
	$test = $carrier->test_connection();
	if ( is_wp_error( $test ) ) {
		$ok( false, 'Connexion', $test->get_error_message() );
		continue;
	}
	$ok( true, 'Connexion acceptée', isset( $test['message'] ) ? $test['message'] : '' );

	// 2. Tarifs — le piège n° 1 : un schéma non reconnu ne « plante » pas, il rend vide.
	if ( $carrier->supports_fees() ) {
		$fees = $carrier->get_fees( true );
		if ( is_wp_error( $fees ) ) {
			$ok( false, 'Import des tarifs', $fees->get_error_message() . ' (schéma de /tarifs changé ?)' );
		} else {
			$n    = count( $fees );
			$home = 0;
			$desk = 0;
			foreach ( $fees as $f ) {
				if ( ! empty( $f['home'] ) ) {
					$home++;
				}
				if ( ! empty( $f['desk'] ) ) {
					$desk++;
				}
			}
			$ok( $n > 0, "Tarifs récupérés : {$n} wilaya(s)" );
			// Une colonne entièrement à zéro = la clé de cette colonne n'existe plus
			// dans la réponse. L'écran d'import n'écrasera rien (il ignore les 0),
			// mais il affichera « 0 » partout : autant le dire ici, franchement.
			$ok( $home > 0, "Tarif DOMICILE lu sur {$home}/{$n} wilayas", 0 === $home ? 'la clé « domicile » n’est plus reconnue' : '' );
			$ok( $desk > 0, "Tarif STOP-DESK lu sur {$desk}/{$n} wilayas", 0 === $desk ? 'la clé « stopdesk » n’est plus reconnue' : '' );
		}
	}

	// 3. Suivi — le piège n° 2 : un statut « inconnu » est compté « en transit »
	//    par les statistiques. Il ne fait jamais de bruit, il fausse les chiffres.
	if ( '' !== $tracking && $carrier->supports_tracking() ) {
		$trk = $carrier->get_tracking( $tracking );
		if ( is_wp_error( $trk ) ) {
			$ok( false, 'Suivi du colis ' . $tracking, $trk->get_error_message() );
		} else {
			$st = isset( $trk['status'] ) ? $trk['status'] : AOD_Carrier::ST_UNKNOWN;
			$ok(
				AOD_Carrier::ST_UNKNOWN !== $st,
				'Suivi de ' . $tracking . ' → statut « ' . $st . ' »',
				AOD_Carrier::ST_UNKNOWN === $st
					? 'libellé « ' . ( isset( $trk['raw_label'] ) ? $trk['raw_label'] : '?' ) . ' » non reconnu → sera compté « en transit » dans les stats'
					: ( isset( $trk['raw_label'] ) ? 'libellé livreur : ' . $trk['raw_label'] : '' )
			);
			$hist    = isset( $trk['history'] ) ? $trk['history'] : array();
			$inconnu = 0;
			foreach ( $hist as $h ) {
				if ( empty( $h['status'] ) || AOD_Carrier::ST_UNKNOWN === $h['status'] ) {
					$inconnu++;
				}
			}
			$ok( count( $hist ) > 0, 'Historique lu : ' . count( $hist ) . ' événement(s)' );
			if ( $inconnu ) {
				WP_CLI::log( "     ⚠ {$inconnu} événement(s) d’historique au libellé non reconnu (timeline moins lisible, stats non impactées)." );
			}
		}
	}
}

WP_CLI::log( '' );
if ( $fails ) {
	WP_CLI::error( "RECETTE API : {$fails} échec(s)." );
}
WP_CLI::success( 'RECETTE API : tout passe.' );
