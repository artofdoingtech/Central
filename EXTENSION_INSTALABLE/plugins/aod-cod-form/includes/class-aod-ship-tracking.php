<?php
/**
 * Synchronisation du suivi des colis (évolution récupérée chez les livreurs).
 *
 * Interroge périodiquement l'API de suivi de chaque transporteur pour les
 * commandes expédiées et non encore « terminées » (livré / retourné / annulé),
 * puis met à jour les métadonnées de suivi de la commande. Le tableau de bord
 * gérant (page « Suivi commande ») affiche ces métadonnées et peut aussi
 * déclencher une synchronisation à la demande.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_Ship_Tracking {

	/** Hook de l'événement cron. */
	const CRON_HOOK = 'aod_ship_tracking_sync';

	/** Nom de la fréquence cron personnalisée. */
	const SCHEDULE = 'aod_two_hours';

	/**
	 * Commandes traitées par passage, pour un transporteur SANS suivi par lot
	 * (un appel réseau par colis) — garde-fou anti rate-limit.
	 */
	const BATCH = 30;

	/**
	 * Commandes candidates ramassées à chaque passage.
	 *
	 * Un transporteur qui sait interroger ses colis par lot (E-com : 40 colis en
	 * 2 appels) peut en absorber bien plus que 30 sans peser sur son API. Les
	 * transporteurs sans lot restent, eux, plafonnés à BATCH.
	 *
	 * Ce qui compte, c'est le temps de rattrapage : avec 30 colis / 2 h, une
	 * boutique qui a 500 colis en cours ne rafraîchit chacun que toutes les ~33 h.
	 * Avec 240, c'est toutes les ~4 h.
	 */
	const BATCH_BULK = 240;

	/**
	 * Budget de temps d'un passage cron, en secondes (32.4, dette D23).
	 *
	 * Sans budget, un passage pouvait dépasser `max_execution_time` — et mourir
	 * SANS trace ni reprise : le suivi se figeait alors que le cron tournait, un
	 * symptôme que `docs/CRON-SERVEUR.md` ne décrit pas. Avec le budget, le
	 * passage s'arrête proprement, journalise ce qu'il laisse, et la reprise est
	 * GRATUITE : `pending_orders()` sert les moins récemment synchronisées
	 * d'abord, donc le passage suivant reprend exactement où l'on s'est arrêté —
	 * l'horodatage `META_STATUS_AT` est le curseur, il vit dans la donnée.
	 */
	const CRON_BUDGET_S = 120;

	/**
	 * Budget de temps d'UNE tranche AJAX (« Actualiser tout »), en secondes.
	 *
	 * Assez court pour rester très en dessous des timeouts PHP (30 s) et
	 * reverse-proxy (60 s) même si un appel transporteur consomme son propre
	 * timeout de 10 s juste avant l'échéance : pire cas ≈ 20 s. Le navigateur
	 * enchaîne les tranches (`dashboard.js`) au lieu de tout demander d'un coup.
	 */
	const AJAX_BUDGET_S = 10;

	/** @var AOD_Ship_Tracking|null */
	protected static $instance = null;

	/** @var AOD_Shipping */
	protected $shipping;

	/**
	 * @param AOD_Shipping|null $shipping
	 * @return AOD_Ship_Tracking
	 */
	public static function instance( $shipping = null ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $shipping );
		}
		return self::$instance;
	}

	private function __construct( $shipping = null ) {
		$this->shipping = ( $shipping instanceof AOD_Shipping ) ? $shipping : AOD_Shipping::instance();

		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_sync' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );
	}

	/**
	 * Ajoute la fréquence « toutes les 2 heures » aux planifications cron.
	 *
	 * @param array $schedules
	 * @return array
	 */
	public function add_schedule( $schedules ) {
		if ( ! isset( $schedules[ self::SCHEDULE ] ) ) {
			$schedules[ self::SCHEDULE ] = array(
				'interval' => 2 * HOUR_IN_SECONDS,
				'display'  => __( 'Toutes les 2 heures (suivi des colis)', 'aod-cod-form' ),
			);
		}
		return $schedules;
	}

	/** Planifie l'événement cron s'il ne l'est pas déjà. */
	public function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Léger décalage pour ne pas lancer la 1re synchro pile au chargement.
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, self::SCHEDULE, self::CRON_HOOK );
		}
	}

	/** Déplanifie l'événement (appelé à la désactivation du plugin). */
	public static function unschedule() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/* ============================================================
	 * Synchronisation
	 * ========================================================== */

	/**
	 * Passage du cron : synchronise les commandes en cours de livraison.
	 *
	 * Simple porte d'entrée vers `run_sync_batch()` avec le budget cron : la
	 * signature (et son retour) restent ceux d'avant la 32.4.
	 *
	 * @return int Nombre de commandes réellement mises à jour.
	 */
	public function run_sync() {
		$r = $this->run_sync_batch( self::CRON_BUDGET_S );
		return (int) $r['updated'];
	}

	/**
	 * Synchronise UNE tranche de commandes, sous budget de temps (32.4, D23).
	 *
	 * Les commandes sont **regroupées par transporteur**, parce que c'est la seule
	 * maille où le suivi par lot a un sens : chaque API ne connaît que ses colis.
	 * Un transporteur qui sait interroger 40 colis en 2 appels traite donc jusqu'à
	 * BATCH_BULK commandes ; ceux qui n'ont qu'un appel par colis restent plafonnés
	 * à BATCH, exactement comme avant.
	 *
	 * 🚨 LA REPRISE NE SE CODE PAS, ELLE SE LIT DANS LA DONNÉE. Chaque commande
	 * traitée reçoit un `META_STATUS_AT` frais, et `pending_orders()` sert les
	 * moins récemment synchronisées d'abord : un passage interrompu au budget
	 * reprend au passage suivant exactement où il s'est arrêté, sans curseur à
	 * stocker ni à invalider. `$since` sert la boucle AJAX : les commandes déjà
	 * estampillées depuis le clic « Actualiser tout » sortent des candidates, donc
	 * chaque tranche avance au lieu de resynchroniser les mêmes.
	 *
	 * ⚠️ Une commande en ERREUR n'est PAS estampillée (on réessaiera) : elle reste
	 * donc candidate pour la tranche suivante. C'est voulu — et c'est pourquoi
	 * l'appelant AJAX s'arrête quand une tranche ne fait plus aucun progrès, au
	 * lieu de boucler sur les mêmes échecs.
	 *
	 * @param int        $budget     Budget de temps en secondes (≥ 1).
	 * @param int        $since      Timestamp : ignorer les commandes déjà synchronisées
	 *                               depuis cet instant (0 = pas de filtre, mode cron).
	 * @param array|null $candidates Réservé aux TESTS (transporteur bouchonné, même
	 *                               contrainte qu'en 31.4) : commandes à traiter au lieu
	 *                               de `pending_orders()`. En production : null.
	 * @return array { updated: int, errors: int, remaining: int, timed_out: bool, elapsed: float }
	 */
	public function run_sync_batch( $budget, $since = 0, $candidates = null ) {
		$t0       = microtime( true );
		$deadline = $t0 + max( 1, (int) $budget );

		if ( null === $candidates ) {
			$candidates = $this->pending_orders( self::BATCH_BULK );
		}
		// Fenêtre pleine = il existe sans doute d'autres candidates au-delà : on le
		// signale dans `remaining` pour que la boucle AJAX redemande une tranche au
		// lieu de conclure « terminé » sur une fenêtre qui déborde.
		$window_full = count( $candidates ) >= self::BATCH_BULK;

		if ( $since > 0 ) {
			$candidates = array_filter( $candidates, function ( $o ) use ( $since ) {
				return (int) $o->get_meta( AOD_Carrier::META_STATUS_AT ) < $since;
			} );
		}

		$by_carrier = array();
		foreach ( $candidates as $order ) {
			$cid = (string) $order->get_meta( AOD_Carrier::META_CARRIER );
			if ( '' === $cid ) {
				continue;
			}
			$by_carrier[ $cid ][] = $order;
		}

		$updated   = 0;
		$errors    = 0;
		$remaining = 0;
		$timed_out = false;
		$attempted = false; // Au moins un colis interrogé ? (garantie de progrès)

		foreach ( $by_carrier as $cid => $orders ) {
			$carrier = $this->shipping->carrier( $cid );
			if ( ! $carrier || ! $carrier->supports_tracking() ) {
				continue;
			}
			// Sans suivi par lot, on garde le plafond historique : les commandes
			// laissées de côté passeront au tour suivant (`pending_orders()` sert
			// les moins récemment rafraîchies d'abord — personne n'est oublié).
			if ( $carrier->bulk_tracking_limit() <= 1 && count( $orders ) > self::BATCH ) {
				$remaining += count( $orders ) - self::BATCH;
				$orders     = array_slice( $orders, 0, self::BATCH );
			}
			if ( $attempted && microtime( true ) >= $deadline ) {
				$remaining += count( $orders );
				$timed_out  = true;
				continue;
			}
			// ⚠️ `! $attempted` = garantie de progrès : même budget dépassé, la
			// PREMIÈRE fournée part quand même. Sans elle, un budget trop court ne
			// ferait strictement rien, tranche après tranche — un « Actualiser
			// tout » qui tourne sans jamais rien actualiser.
			$r = $this->sync_carrier( $carrier, $orders, $deadline, ! $attempted );

			$updated += $r['updated'];
			$errors  += $r['errors'];
			if ( $r['left'] > 0 ) {
				$remaining += $r['left'];
				$timed_out  = true;
			}
			$attempted = $attempted || ( $r['updated'] + $r['errors'] > 0 );
		}

		if ( $window_full ) {
			$remaining = max( $remaining, 1 );
		}

		$elapsed = round( microtime( true ) - $t0, 2 );

		// Journal (31.3) : c'est ce qui rend « le suivi ne bouge plus » diagnosticable.
		// Phrases non traduites, à dessein — convention du journal.
		$resume = sprintf(
			'synchronisation du suivi : %d mise(s) a jour, %d en erreur, %d restante(s), %ss',
			$updated,
			$errors,
			$remaining,
			$elapsed
		);
		if ( $timed_out ) {
			// Niveau error : rare (gros backlog uniquement), et c'est précisément
			// l'état qui, muet, se lisait « le cron est mort » (D23).
			AOD_COD_Log::error( 'tracking', 'Passage interrompu au budget de temps (' . max( 1, (int) $budget ) . 's) — reprise au prochain passage. ' . $resume );
		} else {
			AOD_COD_Log::debug( 'tracking', $resume );
		}

		return array(
			'updated'   => $updated,
			'errors'    => $errors,
			'remaining' => $remaining,
			'timed_out' => $timed_out,
			'elapsed'   => $elapsed,
		);
	}

	/**
	 * Synchronise les commandes d'UN transporteur, par lots de la taille qu'il accepte.
	 *
	 * @param AOD_Carrier $carrier
	 * @param WC_Order[]  $orders
	 * @param float       $deadline    Échéance absolue (microtime) au-delà de laquelle on
	 *                                 s'arrête ENTRE deux fournées (0.0 = sans limite).
	 *                                 Jamais en plein appel : un appel parti se termine.
	 * @param bool        $force_first Traiter la première fournée même échéance dépassée
	 *                                 (garantie de progrès, cf. run_sync_batch()).
	 * @return array { updated: int, errors: int, left: int }
	 */
	protected function sync_carrier( $carrier, $orders, $deadline = 0.0, $force_first = false ) {
		$size    = max( 1, (int) $carrier->bulk_tracking_limit() );
		$updated = 0;
		$errors  = 0;
		$left    = 0;
		$first   = true;

		foreach ( array_chunk( $orders, $size ) as $chunk ) {
			if ( $deadline > 0 && microtime( true ) >= $deadline && ! ( $first && $force_first ) ) {
				$left += count( $chunk );
				continue;
			}
			$first = false;

			// Un même colis peut (en théorie) être rattaché à plusieurs commandes :
			// on interroge le numéro UNE fois et on applique le résultat à chacune.
			$par_colis = array();
			foreach ( $chunk as $order ) {
				$t = trim( (string) $order->get_meta( AOD_Carrier::META_TRACKING ) );
				if ( '' !== $t ) {
					$par_colis[ $t ][] = $order;
				}
			}
			if ( empty( $par_colis ) ) {
				continue;
			}

			$res = $carrier->get_tracking_bulk( array_keys( $par_colis ) );
			foreach ( $par_colis as $tracking => $liste ) {
				$r = isset( $res[ $tracking ] ) ? $res[ $tracking ] : null;
				if ( ! is_array( $r ) ) {
					// WP_Error ou absent : on ne touche à rien, on réessaiera. Le
					// détail (URL, cause) est déjà journalisé par la couche carrier.
					$errors += count( $liste );
					continue;
				}
				foreach ( $liste as $order ) {
					$this->apply_tracking( $order, $carrier, $r );
					$updated++;
				}
			}
		}
		return array(
			'updated' => $updated,
			'errors'  => $errors,
			'left'    => $left,
		);
	}

	/**
	 * Synchronise le suivi d'une seule commande auprès de son transporteur.
	 *
	 * @param WC_Order|int $order
	 * @return array|WP_Error La structure de suivi (cf. AOD_Carrier::get_tracking) ou WP_Error.
	 */
	public function sync_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'aod_track_no_order', __( 'Commande introuvable.', 'aod-cod-form' ) );
		}

		$tracking = (string) $order->get_meta( AOD_Carrier::META_TRACKING );
		$cid      = (string) $order->get_meta( AOD_Carrier::META_CARRIER );
		if ( '' === $tracking || '' === $cid ) {
			return new WP_Error( 'aod_track_not_shipped', __( 'Commande sans colis expédié.', 'aod-cod-form' ) );
		}

		$carrier = $this->shipping->carrier( $cid );
		if ( ! $carrier ) {
			return new WP_Error( 'aod_track_no_carrier', __( 'Transporteur inconnu pour cette commande.', 'aod-cod-form' ) );
		}
		if ( ! $carrier->supports_tracking() ) {
			return new WP_Error( 'aod_track_unsupported', sprintf(
				/* translators: %s : nom du livreur */
				__( 'Le suivi détaillé n’est pas disponible pour %s.', 'aod-cod-form' ),
				$carrier->label()
			) );
		}

		$res = $carrier->get_tracking( $tracking );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$this->apply_tracking( $order, $carrier, $res );
		return $res;
	}

	/**
	 * Écrit le résultat d'un suivi sur la commande (métas + note au changement).
	 *
	 * Partagé par le rafraîchissement unitaire (`sync_order()`, bouton du tableau de
	 * bord) et par la synchronisation par lot (`sync_carrier()`, cron) : les deux
	 * chemins doivent laisser la commande dans le MÊME état, sans quoi un colis
	 * changerait de statut selon la manière dont il a été rafraîchi.
	 *
	 * @param WC_Order    $order
	 * @param AOD_Carrier $carrier
	 * @param array       $res Structure de suivi (cf. AOD_Carrier::get_tracking()).
	 * @return void
	 */
	protected function apply_tracking( $order, $carrier, $res ) {
		$prev    = (string) $order->get_meta( AOD_Carrier::META_STATUS );
		$new     = isset( $res['status'] ) ? (string) $res['status'] : AOD_Carrier::ST_UNKNOWN;
		$history = isset( $res['history'] ) && is_array( $res['history'] ) ? $res['history'] : array();

		$order->update_meta_data( AOD_Carrier::META_STATUS, $new );
		$order->update_meta_data( AOD_Carrier::META_STATUS_LABEL, isset( $res['raw_label'] ) ? (string) $res['raw_label'] : '' );
		$order->update_meta_data( AOD_Carrier::META_STATUS_HISTORY, wp_json_encode( $history ) );
		$order->update_meta_data( AOD_Carrier::META_STATUS_AT, time() );

		// Note de commande au changement de statut significatif (traçabilité gérant).
		if ( $new !== $prev && AOD_Carrier::ST_UNKNOWN !== $new ) {
			$meta = AOD_Carrier::tracking_status_meta( $new );
			$order->add_order_note( sprintf(
				/* translators: 1: nom du livreur, 2: libellé du statut de suivi */
				__( 'Suivi %1$s : %2$s', 'aod-cod-form' ),
				$carrier->label(),
				$meta['label']
			) );
		}

		$order->save();
	}

	/**
	 * Commandes expédiées dont le suivi n'est pas terminal, les moins récemment
	 * synchronisées d'abord (évite d'affamer les nouveaux colis quand il y en a
	 * plus que la taille d'un lot).
	 *
	 * @param int $limit
	 * @return WC_Order[]
	 */
	public function pending_orders( $limit ) {
		$limit    = max( 1, (int) $limit );
		$terminal = AOD_Carrier::tracking_terminal_statuses();

		// Fenêtre de candidats plus large que le lot, triée ensuite par ancienneté
		// de synchro pour un roulement équitable.
		$window = $limit * 5;

		$orders = wc_get_orders( array(
			'type'       => 'shop_order',
			'limit'      => $window,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => AOD_Carrier::META_TRACKING,
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => AOD_Carrier::META_STATUS,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => AOD_Carrier::META_STATUS,
						'value'   => $terminal,
						'compare' => 'NOT IN',
					),
				),
			),
		) );

		if ( empty( $orders ) ) {
			return array();
		}

		// Jamais synchronisé (0) d'abord, puis du plus ancien suivi au plus récent.
		usort( $orders, function ( $a, $b ) {
			$ta = (int) $a->get_meta( AOD_Carrier::META_STATUS_AT );
			$tb = (int) $b->get_meta( AOD_Carrier::META_STATUS_AT );
			return $ta <=> $tb;
		} );

		return array_slice( $orders, 0, $limit );
	}
}
