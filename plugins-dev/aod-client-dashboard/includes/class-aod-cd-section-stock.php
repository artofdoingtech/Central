<?php
/**
 * Section « Stock » : vue de couverture du stock (étape 3.1, Phase 3).
 *
 * Tableau en lecture seule, une ligne par produit publié : stock actuel,
 * seuil d'alerte, vendus sur la période (filtre période commun — mêmes
 * paramètres d'URL que l'Accueil), vitesse d'écoulement (ventes/jour) et
 * jours de couverture estimés (stock ÷ vitesse), badge Rupture / Stock bas /
 * OK. Tri par couverture croissante (les urgences d'abord), « ∞ » quand
 * aucune vente, produits sans gestion de stock listés « non suivi » en fin.
 * Les packs affichent le stock de leurs composants et le nombre de packs
 * assemblables. Un confirmateur restreint (`assigned_products`) ne voit que
 * ses produits affectés. L'ajustement rapide (quantité/seuil) et l'encart
 * d'alertes sont l'étape 3.2.
 *
 * Les ventes par produit sont servies par un transient court (5 min) dont la
 * clé intègre la version de cache de l'Accueil (invalidée à chaque écriture
 * de commande — les hooks vivent dans AOD_CD_Section_Home, chargée avant).
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Stock {

	/** Durée de vie du cache des ventes par produit. */
	const CACHE_TTL = 300; // 5 min.

	/** @var AOD_CD_Section_Stock|null */
	protected static $instance = null;

	/** @var int[]|null Cache requête du périmètre produits de l'utilisateur. */
	protected $user_scope = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Ajustement rapide (étape 3.2) : quantité + seuil + note d'inventaire.
		add_action( 'wp_ajax_aod_cd_save_stock', array( $this, 'ajax_save_stock' ) );
	}

	/**
	 * AJAX : enregistre quantité / seuil / note d'inventaire d'un produit.
	 *
	 * Garde stricte (le masquage UI n'est qu'un confort) : nonce + capability
	 * générale ET par produit + périmètre produits de l'appelant. Un produit
	 * sans gestion de stock est refusé (l'activer = fiche produit, hors
	 * périmètre de l'ajustement rapide).
	 */
	public function ajax_save_stock() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$p   = $pid ? wc_get_product( $pid ) : null;
		if ( ! $p ) {
			wp_send_json_error( array( 'message' => __( 'Produit introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		if ( ! current_user_can( 'edit_product', $pid ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$scope = AOD_CD_Roles::assigned_products();
		if ( $scope && ! in_array( $pid, $scope, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		// 40.3 — stock géré à la variante : l'ajustement rapide produit n'a pas de sens
		// (le total est une SOMME calculée). Le masquage UI n'est qu'un confort ; c'est
		// cette garde serveur qui fait foi (leçon 21/31.1).
		if ( $this->variant_stock_summary( $p )['managed'] ) {
			wp_send_json_error( array( 'message' => __( 'Stock géré par variante : modifiez-le dans Produits.', 'aod-client-dashboard' ) ), 400 );
		}
		if ( ! $p->managing_stock() ) {
			wp_send_json_error( array( 'message' => __( 'Ce produit ne gère pas de stock.', 'aod-client-dashboard' ) ), 400 );
		}

		$stock   = isset( $_POST['stock'] ) ? max( 0, intval( $_POST['stock'] ) ) : 0;
		$low_raw = isset( $_POST['low'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['low'] ) ) ) : '';
		$note    = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		$note    = mb_substr( $note, 0, 200 );

		$p->set_stock_quantity( $stock );
		// Seuil vide = retour au seuil global WooCommerce.
		$p->set_low_stock_amount( '' === $low_raw ? '' : max( 0, intval( $low_raw ) ) );
		if ( '' === $note ) {
			$p->delete_meta_data( '_aod_stock_note' );
		} else {
			$p->update_meta_data( '_aod_stock_note', wp_date( 'd/m/Y' ) . ' — ' . $note );
		}
		$p->save();

		$low_eff = $this->low_threshold( $p );
		$badge   = $stock <= 0 ? 'rupture' : ( $stock <= $low_eff ? 'bas' : 'ok' );

		wp_send_json_success( array(
			'stock'      => $stock,
			'low'        => $low_eff,
			'badge_html' => $this->badge_html( $badge ),
			'note'       => (string) $p->get_meta( '_aod_stock_note' ),
			'message'    => __( 'Stock mis à jour.', 'aod-client-dashboard' ),
		) );
	}

	/**
	 * Produits affectés à l'utilisateur courant (vide = pas de restriction).
	 *
	 * @return int[]
	 */
	protected function user_scope() {
		if ( null === $this->user_scope ) {
			$this->user_scope = AOD_CD_Roles::assigned_products();
		}
		return $this->user_scope;
	}

	/**
	 * Sert $callback depuis un transient court, clé = arguments + version de
	 * cache de l'Accueil (bumpée à chaque écriture de commande — hooks dans
	 * AOD_CD_Section_Home, pas de duplication ici).
	 *
	 * @param array    $key_parts Identité de la donnée.
	 * @param callable $callback  Calcul (exécuté seulement si cache froid).
	 * @return mixed
	 */
	protected function cached( $key_parts, $callback ) {
		$ver = (int) get_option( AOD_CD_Section_Home::CACHE_VER_OPTION, 0 );
		$key = 'aod_cd_stock_' . md5( wp_json_encode( $key_parts ) . '|v' . $ver );
		$hit = get_transient( $key );
		if ( false !== $hit ) {
			return $hit;
		}
		$val = call_user_func( $callback );
		set_transient( $key, $val, self::CACHE_TTL );
		return $val;
	}

	/* ============================================================
	 * Période (mêmes paramètres d'URL que l'Accueil)
	 * ========================================================== */

	/**
	 * Lit la période depuis la query-string (assainie). Copie réduite du
	 * read_filters() de l'Accueil (protected là-bas) : période seulement —
	 * pas de filtre produit/confirmateur sur cette page.
	 *
	 * @return array{periode:string,date_from:string,date_to:string}
	 */
	protected function read_period() {
		$date = function ( $key ) {
			$v = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
			if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
				return '';
			}
			return $v;
		};
		$periode = isset( $_GET['periode'] ) ? sanitize_key( wp_unslash( $_GET['periode'] ) ) : '';
		return array(
			'periode'   => in_array( $periode, array( 'jour', 'semaine' ), true ) ? $periode : '',
			'date_from' => $date( 'date_from' ),
			'date_to'   => $date( 'date_to' ),
		);
	}

	/**
	 * Résout la période effective : plage libre > raccourci > défaut 30 jours.
	 *
	 * @param array $filters Sortie de read_period().
	 * @return array{from:string,to:string,key:string,days:int} Bornes Y-m-d
	 *         incluses + clé d'état + nombre de jours couverts.
	 */
	protected function resolve_range( $filters ) {
		$today = wp_date( 'Y-m-d' );

		if ( $filters['date_from'] || $filters['date_to'] ) {
			$to   = $filters['date_to'] ? $filters['date_to'] : $today;
			$from = $filters['date_from'] ? $filters['date_from'] : $to;
			if ( $from > $to ) {
				list( $from, $to ) = array( $to, $from );
			}
			$range = array( 'from' => $from, 'to' => $to, 'key' => 'libre' );
		} elseif ( 'jour' === $filters['periode'] ) {
			$range = array( 'from' => $today, 'to' => $today, 'key' => 'jour' );
		} elseif ( 'semaine' === $filters['periode'] ) {
			$range = array( 'from' => wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS ), 'to' => $today, 'key' => 'semaine' );
		} else {
			$range = array( 'from' => wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ), 'to' => $today, 'key' => '30' );
		}

		// Nombre de jours inclus (base de la vitesse) — timestamps à midi pour
		// éviter les glissements aux changements d'heure (patron range_bins).
		$range['days'] = (int) round( ( strtotime( $range['to'] . ' 12:00:00' ) - strtotime( $range['from'] . ' 12:00:00' ) ) / DAY_IN_SECONDS ) + 1;
		return $range;
	}

	/** URL de la section stock, avec query-string optionnelle. */
	protected function stock_url_args( $args = array() ) {
		$url = home_url( '/' . AOD_CD_SLUG . '/stock' );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/* ============================================================
	 * Données
	 * ========================================================== */

	/**
	 * Quantités vendues par produit (parent) sur la plage — cache 5 min.
	 *
	 * Ventes = lignes des commandes encaissées (en cours / confirmées /
	 * terminées) créées dans la période ; une vente de variation crédite son
	 * produit PARENT (le stock est géré au produit — variantes maison sans
	 * stock propre). Aucune jointure custom : wc_get_orders sur statuts +
	 * dates est nativement bimode HPOS/CPT.
	 *
	 * @param array $range Sortie de resolve_range().
	 * @return array<int,int> product_id (parent) => quantité vendue.
	 */
	protected function sales_by_product( $range ) {
		$scope = $this->user_scope();
		return $this->cached(
			array( 'ventes', $range['from'], $range['to'], $scope ),
			function () use ( $range ) {
				// Parcours PAR LOTS (31.6) — 4ᵉ agrégat à charger toutes les commandes
				// de la période d'un coup avant de boucler sur `get_items()`. Cf.
				// `AOD_CD_Orders::walk()` pour les mesures et le raisonnement.
				$ids = wc_get_orders( array(
					'type'         => 'shop_order',
					'limit'        => -1,
					'return'       => 'ids',
					'status'       => array( 'wc-processing', 'wc-aod-confirmed', 'wc-completed' ),
					'date_created' => $range['from'] . '...' . $range['to'],
				) );

				$sales = array();
				AOD_CD_Orders::walk( $ids, function ( $o ) use ( &$sales ) {
					foreach ( $o->get_items() as $item ) {
						$pid = (int) $item->get_product_id(); // Parent pour une variation.
						if ( ! $pid ) {
							continue;
						}
						$sales[ $pid ] = ( isset( $sales[ $pid ] ) ? $sales[ $pid ] : 0 ) + (int) $item->get_quantity();
					}
				} );
				return $sales;
			}
		);
	}

	/**
	 * Seuil d'alerte d'un produit : seuil propre, repli sur l'option globale.
	 *
	 * @param WC_Product $p
	 * @return int
	 */
	protected function low_threshold( $p ) {
		$low = $p->get_low_stock_amount();
		if ( '' === $low || null === $low ) {
			$low = get_option( 'woocommerce_notify_low_stock_amount', 2 );
		}
		return max( 0, (int) $low );
	}

	/**
	 * Nombre de produits en rupture OU en stock bas (tuile « À traiter »).
	 *
	 * Requête DÉDIÉE, volontairement séparée de stock_rows() : cette dernière
	 * exige une plage de dates et calcule les ventes de chaque produit
	 * (wc_get_orders limit=-1) — beaucoup trop cher pour un simple compteur.
	 * Ici on ne lit que le stock et le seuil.
	 *
	 * Les produits SANS gestion de stock (managing_stock() faux) sont exclus :
	 * ils n'ont ni stock ni seuil, donc aucune alerte possible — c'est la même
	 * règle que le badge de stock_rows() (badge vide).
	 *
	 * @return int
	 */
	public function low_stock_count() {
		$args = array(
			'limit'   => -1,
			'status'  => 'publish',
			'return'  => 'objects',
		);
		$scope = $this->user_scope();
		if ( $scope ) {
			$args['include'] = $scope;
		}

		$n = 0;
		foreach ( wc_get_products( $args ) as $p ) {
			if ( ! $p->managing_stock() ) {
				continue;
			}
			$stock = (int) $p->get_stock_quantity();
			if ( $stock <= 0 || $stock <= $this->low_threshold( $p ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Résumé du stock par variante d'un produit (40.3).
	 *
	 * Lit `_aod_options` et retient les valeurs dont le `stock` est renseigné (un
	 * seul axe, garanti par la normalisation mono-axe de 40.1). Le stock produit
	 * affiché par la page Stock en est la SOMME — CALCULÉE, jamais écrite dans le
	 * `_stock` WooCommerce (pas de duplication d'état, cf. architecture §7.6).
	 * Une valeur laissée vide reste « non gérée » (illimitée) et n'entre ni dans la
	 * somme ni dans le détail.
	 *
	 * @param WC_Product|null $product
	 * @return array{managed:bool,total:int,items:array<int,array{name:string,stock:int}>}
	 */
	public function variant_stock_summary( $product ) {
		$out = array( 'managed' => false, 'total' => 0, 'items' => array() );
		if ( ! $product ) {
			return $out;
		}
		$opts = $product->get_meta( '_aod_options' );
		if ( ! is_array( $opts ) ) {
			return $out;
		}
		foreach ( $opts as $sec ) {
			if ( ! is_array( $sec ) || empty( $sec['values'] ) || ! is_array( $sec['values'] ) ) {
				continue;
			}
			foreach ( $sec['values'] as $val ) {
				if ( ! is_array( $val ) || ! isset( $val['stock'] ) || '' === (string) $val['stock'] ) {
					continue; // Variante non gérée : illimitée, hors somme.
				}
				$qty            = max( 0, (int) $val['stock'] );
				$out['items'][] = array( 'name' => (string) ( isset( $val['name'] ) ? $val['name'] : '' ), 'stock' => $qty );
				$out['total']  += $qty;
				$out['managed'] = true;
			}
		}
		return $out;
	}

	/**
	 * Construit et trie les lignes du tableau.
	 *
	 * Chaque ligne : produit, suivi ou non, stock, seuil, vendus, vitesse,
	 * couverture (jours ; 0 = rupture, INF = aucune vente, null = non suivi),
	 * badge, détail pack éventuel. Tri : couverture croissante (urgences
	 * d'abord), « ∞ » ensuite, non suivis en fin — à volet égal, ordre
	 * alphabétique.
	 *
	 * @param array $range Sortie de resolve_range().
	 * @return array[]
	 */
	protected function stock_rows( $range ) {
		$sales = $this->sales_by_product( $range );
		$args  = array(
			'limit'   => -1,
			'status'  => 'publish',
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		$scope = $this->user_scope();
		if ( $scope ) {
			$args['include'] = $scope;
		}
		$products = wc_get_products( $args );

		$rows = array();
		foreach ( $products as $p ) {
			$pid     = $p->get_id();
			$managed = $p->managing_stock();
			$stock   = $managed ? (int) $p->get_stock_quantity() : null;
			$low     = $this->low_threshold( $p );
			$sold    = isset( $sales[ $pid ] ) ? (int) $sales[ $pid ] : 0;
			$speed   = $range['days'] > 0 ? $sold / $range['days'] : 0.0;

			// 40.3 — stock par variante : la SOMME calculée fait foi et pilote badge /
			// couverture / KPI (via $stock), même si le produit ne gère pas le _stock Woo.
			// Le _stock Woo n'est jamais écrit (pas de duplication d'état, §7.6).
			$vsum = $this->variant_stock_summary( $p );
			if ( $vsum['managed'] ) {
				$managed = true;
				$stock   = $vsum['total'];
			}

			if ( ! $managed ) {
				$cover = null;   // Non suivi : ni couverture ni badge.
				$badge = '';
			} elseif ( $stock <= 0 ) {
				$cover = 0.0;    // Rupture : en tête de tri.
				$badge = 'rupture';
			} elseif ( $speed <= 0 ) {
				$cover = INF;    // Aucune vente : « ∞ », après les valeurs finies.
				$badge = ( $stock <= $low ) ? 'bas' : 'ok';
			} else {
				$cover = $stock / $speed;
				$badge = ( $stock <= $low ) ? 'bas' : 'ok';
			}

			// Détail pack : stock des composants + packs assemblables.
			$pack = null;
			if ( '1' === (string) $p->get_meta( '_aod_is_pack' ) ) {
				$components = $p->get_meta( '_aod_pack_items' );
				if ( is_array( $components ) && $components ) {
					$pack = array( 'parts' => array(), 'buildable' => null );
					$mins = array();
					$all_managed = true;
					foreach ( $components as $comp ) {
						$cid = isset( $comp['id'] ) ? (int) $comp['id'] : 0;
						$cq  = isset( $comp['qty'] ) ? max( 1, (int) $comp['qty'] ) : 1;
						$cp  = $cid ? wc_get_product( $cid ) : null;
						if ( ! $cp ) {
							continue;
						}
						$cstock = $cp->managing_stock() ? (int) $cp->get_stock_quantity() : null;
						$pack['parts'][] = array( 'name' => $cp->get_name(), 'qty' => $cq, 'stock' => $cstock );
						if ( null === $cstock ) {
							$all_managed = false; // Composant non suivi : exclu du min.
						} else {
							$mins[] = (int) floor( max( 0, $cstock ) / $cq );
						}
					}
					if ( $mins && $all_managed ) {
						$pack['buildable'] = min( $mins );
					}
				}
			}

			$rows[] = array(
				'pid'   => $pid,
				'name'  => $p->get_name(),
				// 🔁 2026-08-09 — la vignette. Stock et Produits listent LE MÊME
				// catalogue : l'un montrait la photo, l'autre pas. Le gérant qui passe
				// de l'un à l'autre reconnaissait ses articles sur un écran et devait
				// les lire sur le suivant.
				// 🔒 AUCUNE REQUÊTE DE PLUS : `$p` est déjà chargé ici (c'est lui qui
				// donne `get_name()` juste au-dessus). Charger l'image dans `render_row()`
				// à partir du seul `pid` aurait, lui, coûté un `wc_get_product()` par
				// ligne — c'est pourquoi elle est prise ICI et pas là-bas.
				'img'   => $p->get_image( array( 40, 40 ) ),
				'stock' => $stock,
				'low'   => $low,
				'low_own' => (string) $p->get_low_stock_amount(), // '' = seuil global.
				'sold'  => $sold,
				'speed' => $speed,
				'cover' => $cover,
				'badge' => $badge,
				'pack'  => $pack,
				// 40.3 — détail par variante (sous-ligne + lecture seule) quand le stock
				// est géré à la variante.
				'variant_managed' => $vsum['managed'],
				'variants'        => $vsum['managed'] ? $vsum['items'] : null,
				'note'  => (string) $p->get_meta( '_aod_stock_note' ),
				// Prix d'achat : sert le KPI « valeur du stock ». '' = non renseigné,
				// le produit est alors exclu du total (même convention que la marge
				// de l'Accueil — on ne mélange pas prix d'achat et prix de vente).
				'cost'  => (string) $p->get_meta( '_aod_cost_price' ),
			);
		}

		usort( $rows, function ( $a, $b ) {
			// Non suivis (cover null) en fin, puis couverture croissante (INF
			// après les finies), puis alphabétique.
			$ga = null === $a['cover'] ? 1 : 0;
			$gb = null === $b['cover'] ? 1 : 0;
			if ( $ga !== $gb ) {
				return $ga <=> $gb;
			}
			if ( 0 === $ga && $a['cover'] !== $b['cover'] ) {
				return $a['cover'] <=> $b['cover'];
			}
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return $rows;
	}

	/* ============================================================
	 * Rendu
	 * ========================================================== */

	public function render() {
		$filters = $this->read_period();
		$range   = $this->resolve_range( $filters );

		$readonly = ! current_user_can( 'edit_products' );
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Stock', 'aod-client-dashboard' ),
			$readonly
				? __( 'Couverture et alertes de stock (lecture seule).', 'aod-client-dashboard' )
				: __( 'Surveillez la couverture, ajustez les quantités et les seuils d’alerte.', 'aod-client-dashboard' )
		);

		$this->render_period_form( $range );

		$rows = $this->stock_rows( $range );
		if ( ! $rows ) {
			echo AOD_CD_Dashboard::empty_state( 'products', __( 'Aucun produit publié à suivre pour le moment.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$this->render_kpis( $rows );
		$this->render_alerts( $rows );

		/* 🚨 46.2 — `--cards` MANQUAIT, et c'est le seul mot qui manquait.
		   Les 7 `data-label` de `render_row()` étaient posés, traduits, un par
		   colonne — c'est-à-dire tout ce que consomme le patron carte. Sans la
		   classe, ils ne servaient à RIEN : à 390 px, un tableau de 7 colonnes À
		   ÉDITION INLINE défilait de 398 px (mesuré) dans `.aod-cd-tablewrap`,
		   qui porte `overflow-x:auto`. Et c'est ce même `overflow-x` qui achetait
		   le silence de la porte `rtl`, laquelle traque le débordement de PAGE :
		   un tableau illisible au pouce lui était indistinguable d'un tableau
		   parfait. Le travail était payé, l'interrupteur n'était pas mis. */
		echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards"><thead><tr>';
		foreach ( array(
			__( 'Produit', 'aod-client-dashboard' ),
			__( 'Stock actuel', 'aod-client-dashboard' ),
			__( 'Seuil d’alerte', 'aod-client-dashboard' ),
			__( 'Vendus sur la période', 'aod-client-dashboard' ),
			__( 'Vitesse (ventes/jour)', 'aod-client-dashboard' ),
			__( 'Couverture (jours)', 'aod-client-dashboard' ),
			__( 'État', 'aod-client-dashboard' ),
		) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$this->render_row( $r );
		}
		echo '</tbody></table></div>';

		// 🔁 2026-08-09 — LES TROIS NOTES DEVIENNENT UN BLOC DE RÉFÉRENCE.
		//
		// Elles étaient trois `<p>` frères posés à nu sous le tableau, donc rendus sur
		// TOUTE la largeur — 1 314 px mesurés. C'est deux à trois fois la longueur de
		// ligne à laquelle un œil retrouve le début de la suivante : on lit la première
		// ligne, on perd la seconde. Le texte était correct, sa mise en page le rendait
		// pénible.
		// L'enveloppe donne trois choses que trois `<p>` nus ne peuvent pas avoir :
		// une LARGEUR DE LECTURE bornée, une surface qui les sort du flux des données,
		// et un repère (l'icône) qui dit « ceci explique, ceci ne se fait pas ».
		//
		// 🔒 AUCUNE CHAÎNE NOUVELLE, et c'est un choix, pas un oubli. Un intitulé
		// (« Comment lire ce tableau ») aurait été plus clair — mais toute chaîne
		// visible impose le cycle `make-translations.sh` + traduction arabe manuelle
		// (règle 4 du dépôt). L'icône et la mise en forme portent le même sens sans
		// rien ajouter à traduire. Si un intitulé est voulu un jour, il vient AVEC son
		// arabe, pas après.
		// ⚠️ `aria-hidden` sur l'icône : elle ne dit rien que le texte ne dise, et un
		// lecteur d'écran n'a pas à annoncer une décoration avant trois paragraphes.
		echo '<div class="aod-cd-legend">';
		echo '<span class="aod-cd-legend__ic" aria-hidden="true">' . AOD_CD_Icon::svg( 'question', array( 'size' => 18 ) ) . '</span>';
		echo '<div class="aod-cd-legend__body">';
		echo '<p class="aod-cd-note">' . esc_html__( 'Vendus = quantités des commandes en cours, confirmées et terminées créées sur la période. Couverture = stock ÷ vitesse ; « ∞ » = aucune vente sur la période. « Stock bas » dès que le stock atteint le seuil d’alerte (seuil du produit, sinon seuil global WooCommerce).', 'aod-client-dashboard' ) . '</p>';
		echo '<p class="aod-cd-note">' . esc_html__( 'Les produits sans gestion de stock WooCommerce sont listés « non suivi » (activez « Gérer le stock ? » sur la fiche produit pour les suivre).', 'aod-client-dashboard' ) . '</p>';
		if ( current_user_can( 'edit_products' ) ) {
			echo '<p class="aod-cd-note">' . esc_html__( 'Après un ajustement, le classement du tableau se met à jour au rechargement de la page.', 'aod-client-dashboard' ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * KPI de tête : ruptures, stock bas, valeur immobilisée, produits suivis
	 * (composant partagé `.aod-cd-kpi` — étape 5.5).
	 *
	 * La valeur du stock se calcule au PRIX D'ACHAT (capital immobilisé), jamais
	 * au prix de vente. Les produits sans prix d'achat renseigné sont exclus du
	 * total et signalés — même convention que la marge de l'Accueil, pour qu'un
	 * chiffre partiel ne se fasse jamais passer pour un chiffre complet.
	 *
	 * @param array[] $rows Lignes de stock_rows().
	 */
	protected function render_kpis( $rows ) {
		$rupture = 0;
		$bas     = 0;
		$suivis  = 0;
		$valeur  = 0.0;
		$sans_pa = 0;

		foreach ( $rows as $r ) {
			if ( 'rupture' === $r['badge'] ) {
				$rupture++;
			} elseif ( 'bas' === $r['badge'] ) {
				$bas++;
			}
			if ( null === $r['stock'] ) {
				continue; // Produit sans gestion de stock : hors comptage.
			}
			$suivis++;
			if ( '' === $r['cost'] ) {
				$sans_pa++;
			} else {
				$valeur += max( 0, (int) $r['stock'] ) * (float) $r['cost'];
			}
		}

		echo AOD_CD_Dashboard::kpis( array( // phpcs:ignore WordPress.Security.EscapeOutput
			array( __( 'Ruptures', 'aod-client-dashboard' ), (string) $rupture, 'rupture', $rupture > 0 ? 'red' : 'green' ),
			array( __( 'Stock bas', 'aod-client-dashboard' ), (string) $bas, 'warning', $bas > 0 ? 'amber' : 'green' ),
			array( __( 'Valeur du stock', 'aod-client-dashboard' ), wc_price( $valeur ), 'money', 'teal' ),
			array( __( 'Produits suivis', 'aod-client-dashboard' ), (string) $suivis, 'products', 'slate' ),
		) );

		if ( $sans_pa > 0 ) {
			echo '<p class="aod-cd-note">' . esc_html( sprintf(
				/* translators: %d : nombre de produits sans prix d'achat renseigné. */
				_n(
					'Valeur du stock calculée au prix d’achat : %d produit sans prix d’achat renseigné est exclu du total.',
					'Valeur du stock calculée au prix d’achat : %d produits sans prix d’achat renseigné sont exclus du total.',
					$sans_pa,
					'aod-client-dashboard'
				),
				$sans_pa
			) ) . '</p>';
		}
	}

	/**
	 * Alertes : ruptures + stock bas, en cartes sémantiques (rouge / ambre), avec
	 * les produits concernés (mêmes lignes que le tableau, périmètre compris).
	 * Masquées quand tout va bien — une page sans alerte ne doit pas en inventer.
	 *
	 * @param array[] $rows Lignes de stock_rows().
	 */
	protected function render_alerts( $rows ) {
		$out = array( 'rupture' => array(), 'bas' => array() );
		foreach ( $rows as $r ) {
			if ( isset( $out[ $r['badge'] ] ) ) {
				$out[ $r['badge'] ][] = $r['name'];
			}
		}
		if ( ! $out['rupture'] && ! $out['bas'] ) {
			return;
		}

		$list = function ( $names ) {
			$shown = array_slice( $names, 0, 10 );
			$txt   = implode( ' · ', $shown );
			if ( count( $names ) > 10 ) {
				/* translators: %d : nombre de produits non listés dans l'encart d'alertes. */
				$txt .= ' ' . sprintf( __( '… et %d autres', 'aod-client-dashboard' ), count( $names ) - 10 );
			}
			return $txt;
		};

		echo '<div class="aod-cd-alerts">';
		if ( $out['rupture'] ) {
			$this->render_alert(
				'bad',
				'rupture',
				/* translators: %d : nombre de produits en rupture de stock. */
				sprintf( _n( '%d produit en rupture', '%d produits en rupture', count( $out['rupture'] ), 'aod-client-dashboard' ), count( $out['rupture'] ) ),
				$list( $out['rupture'] )
			);
		}
		if ( $out['bas'] ) {
			$this->render_alert(
				'warn',
				'warning',
				/* translators: %d : nombre de produits sous leur seuil d'alerte. */
				sprintf( _n( '%d produit en stock bas', '%d produits en stock bas', count( $out['bas'] ), 'aod-client-dashboard' ), count( $out['bas'] ) ),
				$list( $out['bas'] )
			);
		}
		echo '</div>';
	}

	/**
	 * Une carte d'alerte sémantique (composant `.aod-cd-alert` — étape 5.5).
	 *
	 * @param string $tone  warn|bad.
	 * @param string $icon  Emoji décoratif.
	 * @param string $title Titre (déjà traduit).
	 * @param string $body  Liste des produits concernés.
	 */
	protected function render_alert( $tone, $icon, $title, $body ) {
		echo '<div class="aod-cd-alert aod-cd-alert--' . esc_attr( $tone ) . '">';
		// $icon est un nom du catalogue AOD_CD_Icon (Phase 15), plus un emoji.
		echo '<span class="aod-cd-alert__ic" aria-hidden="true">' . AOD_CD_Icon::svg( $icon, array( 'size' => 20 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo '<div class="aod-cd-alert__txt">';
		echo '<p class="aod-cd-alert__title">' . esc_html( $title ) . '</p>';
		echo '<p class="aod-cd-alert__body">' . esc_html( $body ) . '</p>';
		echo '</div></div>';
	}

	/**
	 * Pastille d'état de stock d'un produit — point d'entrée public.
	 *
	 * La liste des produits l'emploie pour afficher le MÊME badge que la page
	 * Stock (spec §4.6). Une seule règle de décision : sans ça, les deux pages
	 * finiraient par dire « Stock bas » et « OK » du même produit.
	 *
	 * @param WC_Product $p Produit.
	 * @return string HTML sûr ('—' si le produit ne gère pas son stock).
	 */
	public function stock_pill( $p ) {
		// 40.3 — stock géré à la variante : la MÊME somme + badge que la page Stock,
		// sinon la liste Produits dirait « — » là où Stock dit « rupture » (divergence).
		$vsum = $this->variant_stock_summary( $p );
		if ( $vsum['managed'] ) {
			$stock = $vsum['total'];
			$badge = $stock <= 0 ? 'rupture' : ( $stock <= $this->low_threshold( $p ) ? 'bas' : 'ok' );
			return $this->badge_html( $badge ) . ' <span class="aod-cd-stock-qty">' . esc_html( (string) $stock ) . '</span>';
		}
		if ( ! $p->managing_stock() ) {
			return '<span class="aod-cd-muted">—</span>';
		}
		$stock = (int) $p->get_stock_quantity();
		$badge = $stock <= 0 ? 'rupture' : ( $stock <= $this->low_threshold( $p ) ? 'bas' : 'ok' );
		return $this->badge_html( $badge ) . ' <span class="aod-cd-stock-qty">' . esc_html( (string) $stock ) . '</span>';
	}

	/**
	 * Pastille d'état de stock (partagée entre le rendu et la réponse AJAX).
	 *
	 * Emploie la pastille canonique du design system : les couleurs viennent des
	 * tokens, plus d'hexadécimal en dur (sinon l'accent du Personnalisateur ne
	 * suit pas et les trois familles de pastilles divergent).
	 *
	 * @param string $badge rupture|bas|ok (autre : tiret muet).
	 * @return string HTML sûr.
	 */
	protected function badge_html( $badge ) {
		$badges = array(
			'rupture' => array( __( 'Rupture', 'aod-client-dashboard' ), 'bad' ),
			'bas'     => array( __( 'Stock bas', 'aod-client-dashboard' ), 'warn' ),
			'ok'      => array( __( 'OK', 'aod-client-dashboard' ), 'ok' ),
		);
		if ( ! isset( $badges[ $badge ] ) ) {
			return '<span class="aod-cd-muted">—</span>';
		}
		return AOD_CD_Dashboard::pill( $badges[ $badge ][0], $badges[ $badge ][1] );
	}

	/**
	 * Formulaire GET de période (copie réduite de la barre de l'Accueil :
	 * inputs date natifs, raccourcis Jour/Semaine — sans JS).
	 *
	 * @param array $range Sortie de resolve_range().
	 */
	protected function render_period_form( $range ) {
		$shortcuts = array(
			'jour'    => __( 'Jour', 'aod-client-dashboard' ),
			'semaine' => __( 'Semaine', 'aod-client-dashboard' ),
		);
		?>
		<form class="aod-cd-form aod-cd-filters" method="get" action="<?php echo esc_url( $this->stock_url_args() ); ?>">
			<div class="aod-cd-field">
				<label class="aod-cd-label" for="aod-cd-s-from"><?php esc_html_e( 'Du', 'aod-client-dashboard' ); ?></label>
				<input type="date" id="aod-cd-s-from" name="date_from" value="<?php echo esc_attr( $range['from'] ); ?>">
			</div>
			<div class="aod-cd-field">
				<label class="aod-cd-label" for="aod-cd-s-to"><?php esc_html_e( 'Au', 'aod-client-dashboard' ); ?></label>
				<input type="date" id="aod-cd-s-to" name="date_to" value="<?php echo esc_attr( $range['to'] ); ?>">
			</div>
			<div class="aod-cd-field aod-cd-filters-actions">
				<span class="aod-cd-label" aria-hidden="true">&nbsp;</span>
				<div class="aod-cd-filters-btns">
					<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Filtrer', 'aod-client-dashboard' ); ?></button>
					<?php foreach ( $shortcuts as $slug => $label ) :
						$cls = 'aod-cd-btn aod-cd-btn-sm' . ( $slug === $range['key'] ? ' is-active' : '' );
						?>
						<a class="<?php echo esc_attr( $cls ); ?>" href="<?php echo esc_url( $this->stock_url_args( array( 'periode' => $slug ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Une ligne du tableau de stock.
	 *
	 * @param array $r Ligne de stock_rows().
	 */
	protected function render_row( $r ) {
		// Nom (lien édition si permis) + détail pack en sous-ligne discrète.
		$name = esc_html( $r['name'] );
		if ( current_user_can( 'edit_product', $r['pid'] ) ) {
			$edit = add_query_arg( array( 'action' => 'edit', 'product' => $r['pid'] ), home_url( '/' . AOD_CD_SLUG . '/products' ) );
			// 🔴 CE LIEN ÉTAIT NU (corrigé le 2026-08-09), et c'est le MÊME défaut que
			// celui trouvé sur `/gestion/products/` le 2026-08-08 : un `<a href>` sans
			// classe n'est visé par aucune règle de `dashboard.css`, donc il hérite du
			// style du NAVIGATEUR — bleu, souligné. Mesuré ici : le nom du produit était
			// le seul élément bleu de tout l'écran, sur une page entièrement verte.
			// 🚨 Le correctif existait déjà, avec sa classe et sa raison, appliqué à un
			// seul des deux tableaux du catalogue. C'est le mode de dérive que la
			// Phase 46 documente en entier : *une règle écrite une fois ne se propage
			// pas toute seule à son jumeau.*
			$name = '<a class="aod-cd-prod__name" href="' . esc_url( $edit ) . '">' . $name . '</a>';
		}
		if ( $r['pack'] ) {
			$parts = array();
			foreach ( $r['pack']['parts'] as $part ) {
				$pstock  = null === $part['stock'] ? __( 'non suivi', 'aod-client-dashboard' ) : number_format_i18n( $part['stock'] );
				/* translators: 1 : nom du composant, 2 : quantité par pack, 3 : stock du composant. */
				$parts[] = sprintf( __( '%1$s ×%2$d → %3$s', 'aod-client-dashboard' ), $part['name'], $part['qty'], $pstock );
			}
			/* translators: %s : liste des composants du pack avec leur stock. */
			$detail = sprintf( __( 'Composants : %s', 'aod-client-dashboard' ), implode( ' · ', $parts ) );
			if ( null !== $r['pack']['buildable'] ) {
				/* translators: %s : nombre de packs assemblables avec le stock des composants. */
				$detail .= ' — ' . sprintf( __( '%s pack(s) assemblable(s)', 'aod-client-dashboard' ), number_format_i18n( $r['pack']['buildable'] ) );
			}
			$name .= '<br><span class="aod-cd-muted">' . esc_html( $detail ) . '</span>';
		}

		// 40.3 — détail par variante en sous-ligne (même patron que le détail pack).
		if ( ! empty( $r['variant_managed'] ) && ! empty( $r['variants'] ) ) {
			$vparts = array();
			foreach ( $r['variants'] as $vv ) {
				/* translators: 1 : nom de la variante, 2 : stock de la variante. */
				$vparts[] = sprintf( __( '%1$s → %2$s', 'aod-client-dashboard' ), $vv['name'], number_format_i18n( $vv['stock'] ) );
			}
			/* translators: %s : liste des variantes avec leur stock. */
			$vdetail = sprintf( __( 'Variantes : %s', 'aod-client-dashboard' ), implode( ' · ', $vparts ) );
			$name   .= '<br><span class="aod-cd-muted">' . esc_html( $vdetail ) . '</span>';
		}

		// Note d'inventaire (3.2) : sous-ligne discrète, span toujours présent
		// sur une ligne éditable (le JS la met à jour après enregistrement).
		// 40.3 — un produit géré à la variante est en LECTURE SEULE ici : la saisie du
		// stock se fait dans Produits (par variante). On affiche la somme, pas un champ.
		$editable = null !== $r['stock'] && empty( $r['variant_managed'] ) && current_user_can( 'edit_products' ) && current_user_can( 'edit_product', $r['pid'] );
		if ( '' !== $r['note'] || $editable ) {
			$name .= '<br><span class="aod-cd-muted aod-cd-stock-noteline"' . ( '' === $r['note'] ? ' hidden' : '' ) . '>' . AOD_CD_Icon::svg( 'note', array( 'size' => 14 ) ) . ' ' . esc_html( $r['note'] ) . '</span>';
		}

		if ( $editable ) {
			// Un tableau de N produits : le nom du produit est dans une AUTRE cellule.
			// Sans aria-label, ces champs sont annoncés « zone de saisie » et rien de
			// plus — impossible de savoir quel stock on est en train de modifier.
			/* translators: %s: nom du produit. */
			$aria_qty  = esc_attr( sprintf( __( 'Stock — %s', 'aod-client-dashboard' ), $r['name'] ) );
			/* translators: %s: nom du produit. */
			$aria_low  = esc_attr( sprintf( __( 'Seuil d’alerte stock bas — %s', 'aod-client-dashboard' ), $r['name'] ) );

			$stock_cell = '<input type="number" min="0" step="1" class="aod-cd-stock-qty" aria-label="' . $aria_qty . '" value="' . esc_attr( $r['stock'] ) . '" style="width:6em">';
			// Seuil : champ vide = seuil global (affiché en placeholder).
			$low_cell = '<input type="number" min="0" step="1" class="aod-cd-stock-low" aria-label="' . $aria_low . '" value="' . esc_attr( $r['low_own'] ) . '" placeholder="' . esc_attr( $r['low'] ) . '" style="width:6em">';
		} elseif ( null === $r['stock'] ) {
			$stock_cell = '<span class="aod-cd-muted">' . esc_html__( 'non suivi', 'aod-client-dashboard' ) . '</span>';
			$low_cell   = esc_html( number_format_i18n( $r['low'] ) );
		} else {
			$stock_cell = esc_html( number_format_i18n( $r['stock'] ) );
			// 40.3 — indice : ce total est la somme des variantes (édition dans Produits).
			if ( ! empty( $r['variant_managed'] ) ) {
				$stock_cell .= ' <span class="aod-cd-muted aod-cd-stock-variant-hint">' . esc_html__( 'géré par variante', 'aod-client-dashboard' ) . '</span>';
			}
			$low_cell   = esc_html( number_format_i18n( $r['low'] ) );
		}

		if ( null === $r['cover'] ) {
			$cover_cell = '<span class="aod-cd-muted">—</span>';
		} elseif ( is_infinite( $r['cover'] ) ) {
			$cover_cell = '∞';
		} else {
			$cover_cell = esc_html( number_format_i18n( $r['cover'], 1 ) );
		}

		// Le badge vit dans un wrapper dédié : le JS ne remplace que lui (les
		// champs note/💾 partagent la cellule et doivent survivre à la MAJ).
		$badge_cell = '<span class="aod-cd-stock-badgewrap">' . $this->badge_html( $r['badge'] ) . '</span>';
		if ( $editable ) {
			/* translators: %s: nom du produit. */
			$aria_note   = esc_attr( sprintf( __( 'Note d’inventaire — %s', 'aod-client-dashboard' ), $r['name'] ) );
			$badge_cell .= '<br><input type="text" maxlength="200" class="aod-cd-stock-note" aria-label="' . $aria_note . '" placeholder="' . esc_attr__( 'Note d’inventaire (facultative)', 'aod-client-dashboard' ) . '" value="" style="width:11em;margin-top:4px"> '
				. '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-stock-save" title="' . esc_attr__( 'Enregistrer', 'aod-client-dashboard' ) . '">' . AOD_CD_Icon::svg( 'save', array( 'size' => 15 ) ) . '</button>';
		}

		echo '<tr data-stock-row data-product="' . esc_attr( $r['pid'] ) . '" data-speed="' . esc_attr( $r['speed'] ) . '">';
		// La cellule reprend `.aod-cd-prod` — le composant vignette + nom de la page
		// Produits — pour que les deux tableaux du catalogue se ressemblent. Le détail
		// (variantes, composants de pack, note) reste SOUS le nom, dans la colonne.
		echo '<td class="aod-cd-cell-prod" data-label="' . esc_attr__( 'Produit', 'aod-client-dashboard' ) . '"><span class="aod-cd-prod">'
			. ( isset( $r['img'] ) ? wp_kses_post( $r['img'] ) : '' )
			. '<span class="aod-cd-prod__txt">' . $name . '</span></span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- markup échappé ci-dessus.
		// 🔑 `aod-cd-cell-stockn` — c'est LE nombre de cette page. Il était rendu au
		// corps de texte, à côté d'un « géré par variante » gris, dans une colonne
		// parmi sept. Une classe est nécessaire : le seul autre point d'accroche
		// aurait été `[data-label="Stock actuel"]`, un sélecteur sur du TEXTE TRADUIT,
		// donc mort dès que le gérant passe en arabe.
		echo '<td class="aod-cd-cell-stockn" data-label="' . esc_attr__( 'Stock actuel', 'aod-client-dashboard' ) . '">' . $stock_cell . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td data-label="' . esc_attr__( 'Seuil d’alerte', 'aod-client-dashboard' ) . '">' . $low_cell . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td data-label="' . esc_attr__( 'Vendus sur la période', 'aod-client-dashboard' ) . '">' . esc_html( number_format_i18n( $r['sold'] ) ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Vitesse (ventes/jour)', 'aod-client-dashboard' ) . '">' . esc_html( number_format_i18n( $r['speed'], 1 ) ) . '</td>';
		echo '<td class="aod-cd-stock-cover" data-label="' . esc_attr__( 'Couverture (jours)', 'aod-client-dashboard' ) . '">' . $cover_cell . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td class="aod-cd-stock-badge" data-label="' . esc_attr__( 'État', 'aod-client-dashboard' ) . '">' . $badge_cell . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</tr>';
	}
}
