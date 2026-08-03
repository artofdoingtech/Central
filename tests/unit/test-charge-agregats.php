<?php
/**
 * Les agrégats de l'Accueil ne rechargent plus toutes les commandes (Phase 31.6).
 *
 * 🚨 LE DÉFAUT. Quatre agrégats chargeaient toutes les commandes de la période
 * d'un coup (`limit => -1`) puis bouclaient sur `WC_Order::get_items()` — qui
 * n'est pas préchargé par `wc_get_orders()`, donc une requête par commande.
 *
 * MESURÉ sur ce clone à 10 000 commandes, avant / après :
 *   Revenus       94,0 Mo → 26,0 Mo
 *   Livraison     28,0 Mo →  0,0 Mo
 *   Confirmation  86,0 Mo →  4,0 Mo
 * Le temps ne bouge pas ; c'est le pic qui s'effondre — et c'est le pic qui
 * compte : dépasser `memory_limit` rend un écran BLANC, pas un écran lent.
 *
 * ⚠️ CE QUE CE FICHIER NE PROUVE PAS. Il ne mesure rien : un test unitaire
 * mono-processus sur 31 commandes réelles ne peut pas voir un défaut de charge.
 * La MESURE est la porte `bash tools/recette/run.sh charge`, qui sème son propre
 * volume. Ce cliquet garde le MOTIF : que personne ne remette un `limit => -1`
 * suivi d'une boucle `get_items()`, ce qui rendrait la porte rouge… six mois
 * plus tard, si quelqu'un pense à la lancer.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

AOD_Test::group( 'Charge 31.6 — R0 : le socle est chargé' );

$aod_chg_ok = class_exists( 'AOD_CD_Orders' ) && method_exists( 'AOD_CD_Orders', 'walk' );
AOD_Test::ok( $aod_chg_ok, 'AOD_CD_Orders::walk() est disponible', $aod_chg_ok ? '' : 'class-aod-cd-orders.php non chargée' );

if ( ! $aod_chg_ok ) {
	return;
}

$aod_chg_cd  = defined( 'AOD_CD_PATH' ) ? AOD_CD_PATH : '';
$aod_chg_lire = function ( $f ) use ( $aod_chg_cd ) {
	$p = $aod_chg_cd . 'includes/' . $f;
	return is_readable( $p ) ? (string) file_get_contents( $p ) : '';
};

/* =========================================================================
 * R1 — Les quatre agrégats passent par le parcours par lots
 * ========================================================================= */

AOD_Test::group( 'Charge 31.6 — R1 : les agrégats ne chargent plus tout d’un coup' );

$aod_chg_home  = $aod_chg_lire( 'class-aod-cd-section-home.php' );
$aod_chg_stock = $aod_chg_lire( 'class-aod-cd-section-stock.php' );

AOD_Test::ok( '' !== $aod_chg_home && '' !== $aod_chg_stock, 'TÉMOIN : les deux fichiers de section sont lisibles' );

// Trois agrégats datés dans Home + un dans Stock.
AOD_Test::ok(
	substr_count( $aod_chg_home, 'each_filtered_order(' ) >= 4,
	sprintf( 'Home : les 3 agrégats datés parcourent par lots (%d appels)', substr_count( $aod_chg_home, 'each_filtered_order(' ) )
);
AOD_Test::ok(
	false !== strpos( $aod_chg_stock, 'AOD_CD_Orders::walk(' ),
	'Stock : les ventes par produit parcourent par lots'
);

/* =========================================================================
 * R2 — ANTI-RETOUR : plus de `limit => -1` suivi d'une boucle sur les items
 *
 * 🚨 C'est la règle qui tient dans la durée. Le parcours par lots ne protège
 * que le code qui l'emprunte : un cinquième agrégat écrit demain referait
 * exactement le même geste, et rien ne le signalerait avant le premier client
 * à 10 000 commandes.
 * ========================================================================= */

AOD_Test::group( 'Charge 31.6 — R2 : aucun agrégat ne recharge toutes les commandes' );

$aod_chg_suspects = array();
foreach ( array( 'class-aod-cd-section-home.php', 'class-aod-cd-section-stock.php' ) as $f ) {
	$src   = $aod_chg_lire( $f );
	$lines = explode( "\n", $src );
	foreach ( $lines as $i => $l ) {
		if ( false === strpos( $l, "'limit'" ) || false === strpos( $l, '-1' ) ) {
			continue;
		}
		// Une requête d'IDENTIFIANTS est légitime : des entiers, pas des objets.
		// On regarde les 8 lignes suivantes — c'est là que `return => ids` se pose.
		$fenetre = implode( "\n", array_slice( $lines, $i, 9 ) );
		if ( false !== strpos( $fenetre, "'return'" ) && false !== strpos( $fenetre, "'ids'" ) ) {
			continue;
		}
		// Une requête sur les PRODUITS ou les utilisateurs n'est pas concernée :
		// une boutique a des dizaines de produits, pas des dizaines de milliers.
		if ( false !== strpos( $fenetre, "'product'" ) || false !== strpos( $fenetre, "get_users" ) ) {
			continue;
		}
		if ( false !== strpos( $fenetre, 'shop_order' ) || false !== strpos( $fenetre, 'wc-processing' ) ) {
			$aod_chg_suspects[] = $f . ':' . ( $i + 1 );
		}
	}
}

AOD_Test::ok(
	empty( $aod_chg_suspects ),
	'Aucune requête de commandes en `limit => -1` qui rende des OBJETS',
	empty( $aod_chg_suspects ) ? '' : implode( ' · ', $aod_chg_suspects ) . ' — utiliser AOD_CD_Orders::walk()'
);

/* =========================================================================
 * R3 — `include` est bannie : elle est ignorée en silence
 *
 * 🚨 MESURÉ pendant l'écriture de la story : `wc_get_orders( array( 'include'
 * => $cinq_ids ) )` a rendu les 31 commandes du clone. Aucune erreur — juste
 * TOUTES les commandes au lieu de cinq, et le CA de l'écran Revenus qui passe
 * de 113 000 à 174 500 DZD. Un chiffre faux, sur lequel le gérant décide ses
 * achats. `post__in` et `id` sont honorées ; `include` ne l'est pas.
 * ========================================================================= */

AOD_Test::group( 'Charge 31.6 — R3 : la clé `include` ne revient pas' );

$aod_chg_orders_src = $aod_chg_lire( 'class-aod-cd-orders.php' );

AOD_Test::ok(
	false === strpos( $aod_chg_orders_src, "'include'" ),
	'Le parcours n’utilise pas `include` (clé ignorée en silence → rend TOUT)'
);
AOD_Test::ok(
	false !== strpos( $aod_chg_orders_src, "'post__in'" ),
	'…il utilise `post__in`, vérifiée honorée sur ce clone'
);
AOD_Test::ok(
	false !== strpos( $aod_chg_orders_src, 'isset( $attendus[' ),
	'…ET trie par appartenance : correct même si la clé cessait d’être honorée'
);

/* =========================================================================
 * R4 — Le cache est versionné PAR BAC DE DATE
 *
 * Fonctionnel, pas textuel : on éprouve le comportement.
 * ========================================================================= */

AOD_Test::group( 'Charge 31.6 — R4 : une commande récente n’invalide pas le passé' );

if ( ! method_exists( 'AOD_CD_Section_Home', 'cache_version_for' ) ) {
	AOD_Test::ok( false, 'AOD_CD_Section_Home::cache_version_for() existe' );
	return;
}

$aod_chg_bacs_avant = get_option( 'aod_cd_home_cache_buckets', null );

$aod_chg_vieux_1  = AOD_CD_Section_Home::cache_version_for( '2019-01-01', '2019-01-31' );
$aod_chg_recent_1 = AOD_CD_Section_Home::cache_version_for( gmdate( 'Y-m-01' ), gmdate( 'Y-m-d' ) );

// On simule une écriture sur le mois COURANT, sans créer de commande : on pousse
// directement le compteur du bac, ce que fait `bump_cache_version()`.
$aod_chg_bacs = (array) get_option( 'aod_cd_home_cache_buckets', array() );
$aod_chg_mois = gmdate( 'Y-m' );
$aod_chg_bacs[ $aod_chg_mois ] = (int) ( isset( $aod_chg_bacs[ $aod_chg_mois ] ) ? $aod_chg_bacs[ $aod_chg_mois ] : 0 ) + 1;
update_option( 'aod_cd_home_cache_buckets', $aod_chg_bacs, true );

$aod_chg_vieux_2  = AOD_CD_Section_Home::cache_version_for( '2019-01-01', '2019-01-31' );
$aod_chg_recent_2 = AOD_CD_Section_Home::cache_version_for( gmdate( 'Y-m-01' ), gmdate( 'Y-m-d' ) );

AOD_Test::ok(
	$aod_chg_recent_2 !== $aod_chg_recent_1,
	'ANCRAGE : la période EN COURS est bien invalidée',
	'sans cet ancrage, « le passé ne bouge pas » serait vrai pour rien — par exemple si la fonction rendait une constante'
);
AOD_Test::ok(
	$aod_chg_vieux_2 === $aod_chg_vieux_1,
	'🔴 Un mois CLOS n’est PAS invalidé (ses chiffres ne peuvent plus changer)',
	'le compteur global invalidait TOUT : à une commande toutes les 3 min, un TTL de 5 min n’était jamais atteint — le cache protégeait la boutique qui n’en a pas besoin et lâchait sur celle qui en a besoin'
);

// Restauration : ce test ne laisse pas le compteur avancé.
if ( null === $aod_chg_bacs_avant ) {
	delete_option( 'aod_cd_home_cache_buckets' );
} else {
	update_option( 'aod_cd_home_cache_buckets', $aod_chg_bacs_avant, true );
}
AOD_Test::ok(
	get_option( 'aod_cd_home_cache_buckets', null ) === $aod_chg_bacs_avant,
	'R5 : l’option des bacs est rendue telle qu’elle était'
);
