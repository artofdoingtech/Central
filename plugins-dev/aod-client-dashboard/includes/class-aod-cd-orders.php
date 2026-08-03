<?php
/**
 * Parcours de commandes par lots, sans jamais toutes les tenir (Phase 31.6).
 *
 * 🚨 CE QUI ÉTAIT FAUX. Quatre agrégats du tableau de bord chargeaient toutes
 * les commandes de la période en une fois (`limit => -1`) puis bouclaient sur
 * `WC_Order::get_items()`. Deux coûts s'additionnaient : toutes les commandes
 * hydratées EN MÉMOIRE EN MÊME TEMPS, et une requête d'items PAR commande
 * (`get_items()` n'est pas préchargé par `wc_get_orders()`).
 *
 * MESURÉ sur ce clone, à 10 000 commandes, avant / après :
 *
 *   Revenus       94,0 Mo → 26,0 Mo     (temps 4,79 s → 5,01 s)
 *   Livraison     28,0 Mo →  0,0 Mo     (temps 0,82 s → 0,86 s)
 *   Confirmation  86,0 Mo →  4,0 Mo     (temps 2,61 s → 2,59 s)
 *
 * Le temps ne bouge pas ; c'est le pic qui s'effondre. Et c'est le pic qui
 * compte : un dépassement de `memory_limit` ne rend pas un écran LENT, il rend
 * un écran BLANC. Sur un hébergement mutualisé à 128 Mo — le cas courant en
 * Algérie — l'ancien Accueil ne passait pas. **Le gérant dont la boutique marche
 * le mieux était celui qui perdait son tableau de bord.**
 *
 * ⚠️ POURQUOI DES LOTS ET PAS `wc_order_product_lookup`. L'énoncé de la story
 * proposait des agrégats SQL sur cette table. Elle appartient à **WooCommerce
 * Analytics** : elle peut être désactivée, vide, ou en retard de reconstruction
 * sur un clone. En tirer le chiffre d'affaires aurait échangé un écran blanc —
 * visible, diagnosticable — contre des **chiffres faux en silence**, sur
 * lesquels le gérant décide ses achats. Les lots ne changent RIEN au calcul :
 * mêmes commandes, même arithmétique, même résultat au centime. Vérifié par
 * comparaison octet à octet de la sortie des quatre agrégats, avant / après.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Orders {

	/**
	 * Taille d'un lot d'hydratation.
	 *
	 * Compromis mesuré : hydrater une commande à la fois divise encore le pic
	 * mais multiplie les allers-retours SQL (1,37 s → 1,68 s à 3 000 commandes).
	 * Par lots de 200, on garde la requête groupée ET le pic borné.
	 */
	const BATCH = 200;

	/**
	 * Parcourt les commandes désignées, par lots, en appelant `$cb` sur chacune.
	 *
	 * @param int[]    $ids Identifiants de commande (déjà filtrés par l'appelant).
	 * @param callable $cb  Reçoit chaque WC_Order, une par une.
	 * @return int Nombre de commandes réellement parcourues.
	 */
	public static function walk( $ids, $cb ) {
		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}

		$vus = 0;
		foreach ( array_chunk( $ids, self::BATCH ) as $lot ) {
			$attendus = array_flip( $lot );

			// 🚨 `post__in` ET NON `include`. Mesuré : **`include` n'est pas une clé
			// supportée par `wc_get_orders()` — elle est IGNORÉE EN SILENCE**, et la
			// requête rend alors TOUTES les commandes. La première version de ce
			// code l'utilisait : 5 identifiants demandés → 31 commandes rendues, et
			// le CA de l'écran Revenus passait de 113 000 à 174 500 DZD. Aucune
			// erreur, aucun avertissement — juste un chiffre faux.
			$lot_orders = wc_get_orders( array(
				'type'     => 'shop_order',
				'limit'    => -1,
				'post__in' => $lot,
				'status'   => 'any',
			) );

			foreach ( $lot_orders as $o ) {
				// ⚠️ ON NE FAIT PAS CONFIANCE À LA CLÉ POUR AUTANT. Ce tri par
				// appartenance rend la boucle correcte même si `post__in` cessait
				// d'être honorée : au pire on perd le bénéfice de l'hydratation
				// groupée, jamais l'exactitude. C'est le garde-fou qui manquait à
				// `include`, et qui aurait transformé un chiffre faux en simple
				// lenteur.
				if ( ! $o instanceof WC_Order || ! isset( $attendus[ $o->get_id() ] ) ) {
					continue;
				}
				call_user_func( $cb, $o );
				$vus++;
				self::forget( $o->get_id() );
			}
			unset( $lot_orders, $attendus );
		}
		return $vus;
	}

	/**
	 * Retire une commande du cache d'objets, une fois qu'on en a fini avec elle.
	 *
	 * ⚠️ ON PASSE PAR L'API DE WOOCOMMERCE, PAS PAR `wp_cache_delete()` SEUL. Le
	 * cache d'objets de commande vit dans un groupe PRÉFIXÉ et versionné
	 * (`OrdersTableDataStore::get_cache_group()`) : supprimer la clé du groupe
	 * « orders » en dur ne l'atteint pas. Mesuré — avec la seule version en dur,
	 * le pic ne bougeait quasiment pas quand on divisait la taille des lots par
	 * 8, ce qui était le signe que rien n'était réellement libéré.
	 *
	 * Si ces clés changeaient un jour, le seul effet serait le retour du pic
	 * d'avant — jamais un résultat faux : ce n'est qu'un cache.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public static function forget( $order_id ) {
		$order_id = (int) $order_id;

		if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Caches\OrderCache' ) ) {
			$cache = wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class );
			if ( is_object( $cache ) && method_exists( $cache, 'remove' ) ) {
				$cache->remove( $order_id );
			}
		}

		// Les LIGNES de commande, elles, restent dans le groupe `orders` classique.
		wp_cache_delete( 'order-items-' . $order_id, 'orders' );
		foreach ( array( 'line_item', 'fee', 'shipping', 'tax', 'coupon' ) as $type ) {
			wp_cache_delete( 'order-items-' . $order_id . '-' . $type, 'orders' );
		}
	}
}
