<?php
/**
 * Section « Accueil » : onglets de statistiques + barre de filtres commune.
 *
 * Socle de la Phase 2 (étape 2.1) : trois onglets gouvernés par capability —
 * Revenus (gérant uniquement), Confirmation (aod_cap_stats_confirm) et
 * Livraison (aod_cap_stats_delivery) — au-dessus desquels vit une barre de
 * filtres commune (produit, confirmateur, période à plage libre) dont l'état
 * persiste en query-string. L'onglet Revenus reprend l'ancienne section
 * « Statistiques ». Depuis l'étape 2.2, les filtres produit (lignes du
 * produit) et confirmateur (commandes portant `_aod_confirmed_by`)
 * s'appliquent réellement aux chiffres, via des jointures SQL armées le temps
 * de la requête (bimode HPOS / CPT hérité, patron de la liste Commandes).
 * L'étape 2.3 livre l'onglet Livraison (statuts de colis normalisés
 * `_aod_ship_status*` : taux livré/retourné/échec/en transit, ventilations
 * transporteur/wilaya, délai moyen, colis bloqués) et étend ces jointures :
 * restriction « expédiées » et périmètre produits du confirmateur
 * (`assigned_products`), appliqués côté SQL. L'étape 2.4 livre l'onglet
 * Confirmation (traçabilité `_aod_confirmed_by/_at` : taux, délai création →
 * confirmation, ventilation par confirmateur — chacun ne voit que sa ligne —
 * et « non attribuées » d'avant la Phase 1).
 *
 * Les agrégats sont servis par des transients courts (5 min) dont la clé
 * intègre une version de cache incrémentée à chaque écriture de commande —
 * invalidation sans balayage, réutilisable telle quelle par 2.2/2.3/2.4.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Home {

	/** Option incrémentée à chaque écriture de commande (invalide les agrégats). */
	const CACHE_VER_OPTION = 'aod_cd_home_cache_ver';


	/** Compteurs d'invalidation PAR MOIS : 'YYYY-MM' => entier (31.6). */
	const CACHE_BUCKETS_OPTION = 'aod_cd_home_cache_buckets';

	/** Nombre de mois conservés dans l'option des bacs (10 ans). */
	const CACHE_BUCKETS_MAX = 120;

	/** Durée de vie des agrégats en cache. */
	const CACHE_TTL = 300; // 5 min.


	/** Ancienneté (jours) sans événement au-delà de laquelle un colis est « bloqué ». */
	const BLOCKED_DAYS = 7;

	/** @var AOD_CD_Section_Home|null */
	protected static $instance = null;

	/** @var int Produit dont on restreint les commandes (0 = pas de filtre). */
	protected $filter_product_id = 0;

	/** @var int Confirmateur (`_aod_confirmed_by`) filtré (0 = pas de filtre). */
	protected $filter_confirmer_id = 0;

	/** @var bool Restreindre aux commandes expédiées (`_aod_ship_tracking` non vide). */
	protected $filter_shipped_only = false;

	/** @var int[] Périmètre produits de l'utilisateur (vide = pas de restriction). */
	protected $filter_scope_ids = array();

	/** @var int Confirmateur ASSIGNÉ (`_aod_assigned_confirmer`) filtré (0 = pas de filtre, Phase 42.3bis). */
	protected $filter_assigned_id = 0;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Toute écriture de commande invalide les agrégats — les anciennes clés
		// expirent d'elles-mêmes.
		//
		// 🚨 48.5 — CE COMMENTAIRE PROMETTAIT « toute écriture » ; les quatre hooks
		// en dessous n'en couvraient que quatre ÉVÉNEMENTS. Une écriture de MÉTA
		// n'en fait partie d'aucun : assigner une commande à un confirmateur
		// n'invalidait donc rien, et sa carte « Commandes assignées » servait un
		// agrégat périmé jusqu'à 5 min (mesuré : 15 en base, 14 à l'écran). Le
		// gérant assigne, le confirmateur regarde, et son propre chiffre lui donne
		// tort — sans que rien ne le signale.
		//
		// `woocommerce_update_order` part de `$order->save()` : il voit les métas,
		// et il est émis par les DEUX magasins (HPOS `OrdersTableDataStore` et CPT
		// hérité), avec la même signature `$order_id` que les quatre autres. C'est
		// pourquoi l'invalidation est posée ICI et non sur les cinq points
		// d'écriture de `_aod_assigned_confirmer` (manuel, lot, tourniquet,
		// conversion de prospect, dé-assignation) : une règle répartie sur cinq
		// sites ne survit pas au sixième — c'est la dérive des Phases 44 et 46.
		//
		// Les quatre suivants RESTENT : la création (`new_order`) et les
		// suppressions ne passent pas par `update`, et un bump en trop ne coûte
		// qu'un recalcul, quand un bump manquant coûte un chiffre faux.
		add_action( 'woocommerce_update_order', array( __CLASS__, 'bump_cache_version' ) );
		add_action( 'woocommerce_new_order', array( __CLASS__, 'bump_cache_version' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'bump_cache_version' ) );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'bump_cache_version' ) );
		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'bump_cache_version' ) );
	}

	/**
	 * Invalide le cache des agrégats en changeant la version (jamais de purge :
	 * la clé des transients intègre la version, les orphelins expirent en 5 min).
	 */
	public static function bump_cache_version( $order_id = 0 ) {
		$bac = self::order_bucket( $order_id );

		if ( '' === $bac ) {
			// Date inconnue : on ne sait pas quelle période est touchée, donc on
			// invalide TOUT. Repli volontairement conservateur — mieux vaut un
			// cache froid qu'un chiffre périmé affiché comme courant.
			update_option( self::CACHE_VER_OPTION, (int) get_option( self::CACHE_VER_OPTION, 0 ) + 1, true );
			return;
		}

		$bacs         = (array) get_option( self::CACHE_BUCKETS_OPTION, array() );
		$bacs[ $bac ] = (int) ( isset( $bacs[ $bac ] ) ? $bacs[ $bac ] : 0 ) + 1;

		// Bornage : sans lui l'option grossirait d'un mois par mois, indéfiniment.
		if ( count( $bacs ) > self::CACHE_BUCKETS_MAX ) {
			ksort( $bacs );
			$bacs = array_slice( $bacs, -self::CACHE_BUCKETS_MAX, null, true );
		}
		update_option( self::CACHE_BUCKETS_OPTION, $bacs, true );
	}

	/**
	 * Mois (`YYYY-MM`) d'une commande, ou '' si on ne peut pas le déterminer.
	 *
	 * @param int|WC_Order $order_id
	 * @return string
	 */
	protected static function order_bucket( $order_id ) {
		if ( ! $order_id ) {
			return '';
		}
		$order = $order_id instanceof WC_Order ? $order_id : wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return '';
		}
		$date = $order->get_date_created();
		return $date ? wp_date( 'Y-m', $date->getTimestamp() ) : '';
	}

	/**
	 * Version de cache couvrant la plage [$from, $to].
	 *
	 * 🚨 CE QUI ÉTAIT FAUX (31.6). Le compteur d'invalidation était GLOBAL : la
	 * moindre écriture de commande — création, changement de statut, corbeille —
	 * invalidait TOUS les agrégats, toutes périodes confondues. Sur une boutique
	 * qui reçoit une commande toutes les 3 minutes, un TTL de 5 minutes n'était
	 * donc JAMAIS atteint : le cache ne servait plus jamais.
	 *
	 * **Le cache protégeait la boutique qui n'en a pas besoin et lâchait sur celle
	 * qui en a besoin.** Plus la boutique marche, moins elle est protégée — c'est
	 * l'inverse exact de ce qu'on veut, et c'est indétectable sur un clone de
	 * démonstration où rien ne se vend.
	 *
	 * Désormais l'invalidation est par MOIS : une commande d'aujourd'hui ne touche
	 * que les périodes qui incluent le mois courant. Les agrégats d'un mois clos ne
	 * peuvent plus changer — les invalider n'a jamais eu de sens.
	 *
	 * Le compteur global subsiste comme repli (date de commande inconnue) et entre
	 * dans toutes les versions : il reste donc capable de tout invalider.
	 *
	 * @param string $from Date `Y-m-d`.
	 * @param string $to   Date `Y-m-d`.
	 * @return string Empreinte à intégrer à la clé de transient.
	 */
	public static function cache_version_for( $from, $to ) {
		$parts = array( 'g' . (int) get_option( self::CACHE_VER_OPTION, 0 ) );
		$bacs  = (array) get_option( self::CACHE_BUCKETS_OPTION, array() );

		$debut = strtotime( substr( (string) $from, 0, 7 ) . '-01' );
		$fin   = strtotime( substr( (string) $to, 0, 7 ) . '-01' );

		if ( ! $debut || ! $fin || $fin < $debut ) {
			// Plage illisible : on retombe sur « tous les bacs », donc sur le
			// comportement d'avant. Jamais sur « aucun bac », qui servirait un
			// cache que plus rien n'invalide.
			foreach ( $bacs as $k => $v ) {
				$parts[] = $k . ':' . (int) $v;
			}
			return md5( implode( '|', $parts ) );
		}

		$curseur = $debut;
		while ( $curseur <= $fin ) {
			$k       = gmdate( 'Y-m', $curseur );
			$parts[] = $k . ':' . (int) ( isset( $bacs[ $k ] ) ? $bacs[ $k ] : 0 );
			$curseur = strtotime( '+1 month', $curseur );
		}

		return md5( implode( '|', $parts ) );
	}

	/**
	 * Sert $callback depuis un transient court, clé = arguments + version.
	 *
	 * @param array    $key_parts Identité de la donnée (onglet, bornes, filtres…).
	 * @param callable $callback  Calcul (exécuté seulement si cache froid).
	 * @return mixed
	 */
	protected function cached( $key_parts, $callback, $from = '', $to = '' ) {
		// Avec une plage : version PAR BAC DE DATE (31.6) — une commande d'aujourd'hui
		// n'invalide pas les agrégats d'un mois clos. Sans plage (ex. « à faire »,
		// qui n'a pas de période) : compteur global, comme avant.
		$ver = ( '' !== $from && '' !== $to )
			? self::cache_version_for( $from, $to )
			: 'g' . (int) get_option( self::CACHE_VER_OPTION, 0 );
		$key = 'aod_cd_home_' . md5( wp_json_encode( $key_parts ) . '|v' . $ver );
		$hit = get_transient( $key );
		if ( false !== $hit ) {
			return $hit;
		}
		$val = call_user_func( $callback );
		set_transient( $key, $val, self::CACHE_TTL );
		return $val;
	}

	/* ============================================================
	 * Requêtes filtrées (produit / confirmateur) — bimode HPOS / CPT
	 * ========================================================== */

	/**
	 * wc_get_orders restreint au produit et/ou au confirmateur demandés.
	 *
	 * Même patron que scoped_orders() de la liste Commandes (étape 1.5) : les
	 * jointures sont injectées le temps de la requête seulement, côté SQL, donc
	 * les comptages paginés restent exacts et les DEUX modes de stockage sont
	 * couverts (un clone peut tourner en HPOS ou en CPT hérité). On ne réutilise
	 * pas scoped_orders() lui-même : il est paramétré par le périmètre produits
	 * de l'utilisateur courant, ici le filtre est un choix d'écran du gérant.
	 *
	 * @param array $args         Arguments wc_get_orders.
	 * @param int   $product_id   Produit (parent ou variation) — 0 = sans filtre.
	 * @param int   $confirmer_id Confirmateur `_aod_confirmed_by` — 0 = sans filtre.
	 * @param bool  $shipped_only Ne garder que les commandes expédiées (colis créé).
	 * @param int[] $scope_ids    Périmètre produits imposé (confirmateur restreint).
	 * @return mixed Résultat de wc_get_orders.
	 */
	/**
	 * Parcourt les commandes filtrées PAR LOTS, sans jamais toutes les tenir.
	 *
	 * 🚨 CE QUI ÉTAIT FAUX (31.6). Les agrégats appelaient `filtered_orders()` en
	 * `limit => -1` puis bouclaient sur `get_items()`. Deux coûts s'additionnaient :
	 * toutes les commandes de la période hydratées EN MÉMOIRE EN MÊME TEMPS, et
	 * une requête d'items PAR commande (`WC_Order::get_items()` n'est pas préchargé
	 * par `wc_get_orders()`).
	 *
	 * MESURÉ sur ce clone avant correctif, à 3 000 commandes :
	 * **+100,5 Mo de pic** pour le seul onglet Revenus. Extrapolé à 10 000, on
	 * dépasse le `memory_limit` de 256 Mo courant chez les hébergeurs mutualisés —
	 * et un dépassement de mémoire ne rend pas un écran LENT, il rend un écran
	 * BLANC. Le gérant dont la boutique marche le mieux est celui qui perd son
	 * tableau de bord.
	 *
	 * ⚠️ POURQUOI DES LOTS ET PAS `wc_order_product_lookup`. L'énoncé proposait des
	 * agrégats SQL sur cette table. Elle appartient à **WooCommerce Analytics** :
	 * elle peut être désactivée, vide ou en retard de reconstruction sur un clone.
	 * En tirer le CA aurait échangé un écran blanc — visible, diagnosticable —
	 * contre des **chiffres faux en silence**, ce qui est bien pire pour un gérant
	 * qui pilote ses achats dessus. Les lots ne changent RIEN au calcul : mêmes
	 * commandes, même arithmétique, même résultat au centime. Seul le pic tombe.
	 *
	 * On récupère d'abord les IDs (des entiers : 10 000 tiennent dans ~1 Mo), puis
	 * on hydrate par paquets. Les filtres sont appliqués à l'étape des IDs, donc
	 * l'hydratation n'a pas à les rejouer.
	 *
	 * @param array    $args     Arguments wc_get_orders (le `limit` est ignoré).
	 * @param callable $cb       Reçoit chaque WC_Order, une par une.
	 * @param int      $product_id
	 * @param int      $confirmer_id
	 * @param bool     $shipped_only
	 * @param int[]    $scope_ids
	 * @param int      $assigned_id  Confirmateur ASSIGNÉ (Phase 42.3bis) — ajouté en FIN
	 *                               de signature pour ne rien décaler sur les appels existants.
	 * @return int Nombre de commandes réellement parcourues.
	 */
	protected function each_filtered_order( $args, $cb, $product_id = 0, $confirmer_id = 0, $shipped_only = false, $scope_ids = array(), $assigned_id = 0 ) {
		$ids = $this->filtered_orders(
			array_merge( $args, array( 'limit' => -1, 'return' => 'ids' ) ),
			$product_id,
			$confirmer_id,
			$shipped_only,
			$scope_ids,
			$assigned_id
		);
		$ids = is_array( $ids ) ? $ids : array();
		if ( ! $ids ) {
			return 0;
		}

		// 🚨 `post__in` ET NON `include`. Mesuré ici : `include` n'est pas une clé
		// supportée par `wc_get_orders()` — elle est **IGNORÉE EN SILENCE**, et la
		// requête rend alors TOUTES les commandes. La première version de ce code
		// l'utilisait : 5 identifiants demandés → 31 commandes rendues, et le CA de
		// l'écran Revenus passait de 113 000 à 174 500 DZD. Aucune erreur, aucun
		// avertissement — juste un chiffre faux sur lequel le gérant décide ses
		// achats. `post__in` (et `id`) sont honorées, vérifié sur ce clone HPOS.
		//
		// ⚠️ ET ON NE LUI FAIT PAS CONFIANCE POUR AUTANT. Le tri par appartenance
		// ci-dessous rend la boucle correcte même si la clé cessait d'être honorée
		// un jour : au pire on perd le bénéfice de l'hydratation groupée, jamais
		// l'exactitude. C'est précisément le garde-fou qui manquait à `include`.
		return AOD_CD_Orders::walk( $ids, $cb );
	}

	protected function filtered_orders( $args, $product_id = 0, $confirmer_id = 0, $shipped_only = false, $scope_ids = array(), $assigned_id = 0 ) {
		$this->filter_product_id   = absint( $product_id );
		$this->filter_confirmer_id = absint( $confirmer_id );
		$this->filter_shipped_only = (bool) $shipped_only;
		$this->filter_scope_ids    = array_values( array_filter( array_map( 'absint', (array) $scope_ids ) ) );
		$this->filter_assigned_id  = absint( $assigned_id );
		if ( ! $this->filter_product_id && ! $this->filter_confirmer_id && ! $this->filter_shipped_only && ! $this->filter_scope_ids && ! $this->filter_assigned_id ) {
			return wc_get_orders( $args );
		}
		add_filter( 'woocommerce_orders_table_query_clauses', array( $this, 'filter_home_clauses' ) ); // HPOS.
		add_filter( 'posts_clauses', array( $this, 'filter_home_clauses_cpt' ) );                      // CPT hérité.
		$res = wc_get_orders( $args );
		remove_filter( 'woocommerce_orders_table_query_clauses', array( $this, 'filter_home_clauses' ) );
		remove_filter( 'posts_clauses', array( $this, 'filter_home_clauses_cpt' ) );
		$this->filter_product_id   = 0;
		$this->filter_confirmer_id = 0;
		$this->filter_shipped_only = false;
		$this->filter_scope_ids    = array();
		$this->filter_assigned_id  = 0;
		return $res;
	}

	/**
	 * Clauses JOIN des filtres actifs, pour une colonne « ID de commande » donnée.
	 *
	 * Produit : sous-requête DISTINCT sur wp_wc_order_product_lookup (les deux
	 * colonnes product_id/variation_id — sélectionner un produit variable couvre
	 * toutes ses variations, une commande mixte ne sort qu'une fois). Confirmateur :
	 * jointure sur la table de métas (`meta_value` comparée en chaîne). Expédiées :
	 * jointure méta `_aod_ship_tracking` non vide (l'onglet Livraison raisonne en
	 * colis, pas en statut de commande). Périmètre : même sous-requête DISTINCT que
	 * le produit mais sur une liste d'IDs (produits affectés du confirmateur).
	 *
	 * @param string $orders_id_col Colonne ID de la requête hôte.
	 * @param string $meta_table    Table de métas (wc_orders_meta ou postmeta).
	 * @param string $meta_fk       Colonne de liaison de la table de métas.
	 * @return string Clauses JOIN à concaténer.
	 */
	protected function home_join_sql( $orders_id_col, $meta_table, $meta_fk ) {
		$join = '';
		if ( $this->filter_product_id ) {
			// 🚨 Sur les LIGNES DE COMMANDE, jamais sur `wc_order_product_lookup`.
			// Cette table est l'analytics de WooCommerce, remplie EN DIFFÉRÉ par
			// l'Action Scheduler : une commande qui vient d'arriver n'y est pas
			// encore, et si l'analytics est désactivé ou le planificateur en carafe
			// (fréquent en mutualisé) elle n'y sera jamais. Le CA du jour et les
			// stats d'un confirmateur restreint sous-comptaient donc en silence.
			// Bug déjà corrigé côté liste Commandes en 5.5 (`0898855`) — il avait
			// survécu ici. Helper partagé : une seule réponse à la même question.
			$join .= AOD_CD_Section_Orders::product_orders_join_sql(
				$orders_id_col,
				array( $this->filter_product_id ),
				'aod_hp'
			);
		}
		if ( $this->filter_confirmer_id ) {
			$cid   = absint( $this->filter_confirmer_id );
			$join .= " JOIN {$meta_table} aod_hc ON aod_hc.{$meta_fk} = {$orders_id_col}"
				. " AND aod_hc.meta_key = '_aod_confirmed_by' AND aod_hc.meta_value = '{$cid}'";
		}
		if ( $this->filter_shipped_only ) {
			$join .= " JOIN {$meta_table} aod_hs ON aod_hs.{$meta_fk} = {$orders_id_col}"
				. " AND aod_hs.meta_key = '_aod_ship_tracking' AND aod_hs.meta_value <> ''";
		}
		if ( $this->filter_scope_ids ) {
			// Même helper : le périmètre d'un confirmateur doit désigner EXACTEMENT
			// les mêmes commandes ici que dans la liste Commandes. Deux jointures
			// différentes = deux vérités, et c'est le confirmateur qui trinque.
			$join .= AOD_CD_Section_Orders::product_orders_join_sql(
				$orders_id_col,
				$this->filter_scope_ids,
				'aod_hsc'
			);
		}
		if ( $this->filter_assigned_id ) {
			// Jumelle de la jointure confirmateur ci-dessus (`_aod_confirmed_by`) :
			// `_aod_assigned_confirmer` est aussi une méta de COMMANDE à valeur
			// unique (Phase 42.3bis) — jointure simple, pas la sous-requête DISTINCT
			// du produit/périmètre (qui, eux, portent sur les LIGNES d'article).
			$aid   = absint( $this->filter_assigned_id );
			$join .= " JOIN {$meta_table} aod_ha ON aod_ha.{$meta_fk} = {$orders_id_col}"
				. " AND aod_ha.meta_key = '_aod_assigned_confirmer' AND aod_ha.meta_value = '{$aid}'";
		}
		return $join;
	}

	/**
	 * Injecte les jointures de filtre dans OrdersTableQuery (HPOS).
	 *
	 * @param array $clauses Clauses SQL de la requête.
	 * @return array
	 */
	public function filter_home_clauses( $clauses ) {
		global $wpdb;
		$clauses['join'] .= $this->home_join_sql( "{$wpdb->prefix}wc_orders.id", "{$wpdb->prefix}wc_orders_meta", 'order_id' );
		return $clauses;
	}

	/**
	 * Injecte les jointures de filtre dans la WP_Query du stockage CPT hérité.
	 *
	 * @param array $clauses Clauses SQL de la requête.
	 * @return array
	 */
	public function filter_home_clauses_cpt( $clauses ) {
		global $wpdb;
		$clauses['join'] .= $this->home_join_sql( "{$wpdb->posts}.ID", $wpdb->postmeta, 'post_id' );
		return $clauses;
	}

	/* ============================================================
	 * Onglets & filtres (état en query-string)
	 * ========================================================== */

	/** @var int[]|null Cache requête du périmètre produits de l'utilisateur courant. */
	protected $user_scope = null;

	/**
	 * Produits affectés à l'utilisateur courant (vide = pas de restriction —
	 * gérant, ou confirmateur sans affectation). Mémoïsé pour la requête.
	 *
	 * @return int[]
	 */
	protected function user_scope() {
		if ( null === $this->user_scope ) {
			$this->user_scope = AOD_CD_Roles::assigned_products();
		}
		return $this->user_scope;
	}

	/** @var int|null Cache requête : 0 = pas de filtre, sinon ID utilisateur (Phase 42.3bis). */
	protected $assignment_scope = null;

	/**
	 * ID du confirmateur courant si les agrégats doivent être restreints à SES
	 * commandes assignées (`_aod_assigned_confirmer`) — `0` sinon. Jumelle de
	 * `AOD_CD_Section_Orders::assignment_scope()` (Phase 42.3) : même garde par
	 * CAPABILITY (`aod_cap_confirm_orders`), pas seulement le rôle — un
	 * confirmateur « tracking/stock seul » n'a jamais d'assignation (42.1) et ne
	 * doit jamais voir ses tuiles Suivi/Stock se vider à tort.
	 *
	 * 🚨 Ne s'applique QUE là où l'agrégat est déjà personnel à l'utilisateur
	 * courant (`render_todo()` tuile « en attente », `delivery_data()`) —
	 * JAMAIS à `confirmation_data()`, qui ramène volontairement les lignes de
	 * TOUS les confirmateurs et restreint au RENDU (cf. son docblock).
	 *
	 * @return int
	 */
	protected function assignment_scope() {
		if ( null === $this->assignment_scope ) {
			$this->assignment_scope = ( ! current_user_can( 'manage_woocommerce' ) && AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) )
				? get_current_user_id()
				: 0;
		}
		return $this->assignment_scope;
	}

	/**
	 * Onglets ouverts à l'utilisateur courant — chaque onglet revérifie sa
	 * propre capability (la cap de la section « home » n'ouvre que la page).
	 *
	 * @return array<string,string> slug => libellé i18n.
	 */
	protected function visible_tabs() {
		$tabs = array();
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$tabs['revenus'] = __( 'Revenus', 'aod-client-dashboard' );
		}
		if ( AOD_CD_Roles::user_can( 'aod_cap_stats_confirm' ) ) {
			$tabs['confirmation'] = __( 'Confirmation', 'aod-client-dashboard' );
		}
		if ( AOD_CD_Roles::user_can( 'aod_cap_stats_delivery' ) ) {
			$tabs['livraison'] = __( 'Livraison', 'aod-client-dashboard' );
		}
		return $tabs;
	}

	/**
	 * Filtres communs lus depuis la query-string (assainis).
	 *
	 * Produit/confirmateur sont portés par l'URL mais n'affectent pas encore
	 * les chiffres (étape 2.2) ; le confirmateur n'a de sens que pour le gérant.
	 *
	 * @return array{onglet:string,produit:int,confirmateur:int,periode:string,date_from:string,date_to:string}
	 */
	protected function read_filters() {
		// Validation de date déléguée au helper partagé (Phase 43.1) —
		// AOD_CD_Dashboard::parse_date_param() est le reflet EXACT de
		// l'ancienne closure locale : comportement inchangé à l'octet.
		$periode = isset( $_GET['periode'] ) ? sanitize_key( wp_unslash( $_GET['periode'] ) ) : '';
		$produit = isset( $_GET['produit'] ) ? absint( $_GET['produit'] ) : 0;
		$scope   = $this->user_scope();
		if ( $produit && $scope && ! in_array( $produit, $scope, true ) ) {
			$produit = 0; // Produit hors périmètre affecté : ignoré (le périmètre s'applique de toute façon).
		}
		return array(
			'onglet'       => isset( $_GET['onglet'] ) ? sanitize_key( wp_unslash( $_GET['onglet'] ) ) : '',
			'produit'      => $produit,
			'confirmateur' => ( current_user_can( 'manage_woocommerce' ) && isset( $_GET['confirmateur'] ) ) ? absint( $_GET['confirmateur'] ) : 0,
			'periode'      => in_array( $periode, array( 'jour', 'semaine' ), true ) ? $periode : '',
			'date_from'    => AOD_CD_Dashboard::parse_date_param( 'date_from' ),
			'date_to'      => AOD_CD_Dashboard::parse_date_param( 'date_to' ),
		);
	}

	/**
	 * Résout la période effective : plage libre > raccourci > défaut 30 jours.
	 *
	 * @param array $filters Sortie de read_filters().
	 * @return array{from:string,to:string,key:string} Bornes Y-m-d (fuseau du
	 *         site, incluses) + clé d'état ('libre'|'jour'|'semaine'|'30').
	 */
	protected function resolve_range( $filters ) {
		$today = wp_date( 'Y-m-d' );

		if ( $filters['date_from'] || $filters['date_to'] ) {
			// Synthèse des bornes manquantes : PROPRE à Home (une plage de
			// stats a toujours besoin des deux bouts) — pas déléguée au
			// helper partagé, qui ne comble jamais une borne absente
			// (cf. AOD_CD_Dashboard::resolve_date_range()).
			$to   = $filters['date_to'] ? $filters['date_to'] : $today;
			$from = $filters['date_from'] ? $filters['date_from'] : $to;
			// Seul le tri « si inversées » est réellement partagé (Phase 43.1).
			$bounds = AOD_CD_Dashboard::resolve_date_range( $from, $to );
			return array( 'from' => $bounds['from'], 'to' => $bounds['to'], 'key' => 'libre' );
		}
		if ( 'jour' === $filters['periode'] ) {
			return array( 'from' => $today, 'to' => $today, 'key' => 'jour' );
		}
		if ( 'semaine' === $filters['periode'] ) {
			return array( 'from' => wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS ), 'to' => $today, 'key' => 'semaine' );
		}
		// Défaut : 30 derniers jours (parité avec l'ancienne section Statistiques).
		return array( 'from' => wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ), 'to' => $today, 'key' => '30' );
	}

	/**
	 * Libellé humain de la période active (pour le sous-titre de l'en-tête).
	 *
	 * @param array $range Sortie de resolve_range().
	 * @return string
	 */
	protected function range_label( $range ) {
		if ( 'jour' === $range['key'] ) {
			return __( 'aujourd’hui', 'aod-client-dashboard' );
		}
		if ( 'semaine' === $range['key'] ) {
			return __( '7 derniers jours', 'aod-client-dashboard' );
		}
		if ( '30' === $range['key'] ) {
			return __( '30 derniers jours', 'aod-client-dashboard' );
		}
		return sprintf(
			/* translators: 1 : date de début, 2 : date de fin */
			__( 'du %1$s au %2$s', 'aod-client-dashboard' ),
			date_i18n( get_option( 'date_format' ), strtotime( $range['from'] ) ),
			date_i18n( get_option( 'date_format' ), strtotime( $range['to'] ) )
		);
	}

	/**
	 * Période immédiatement antérieure, de MÊME durée (pour les deltas).
	 *
	 * @param array $range Sortie de resolve_range().
	 * @return array ['from' => 'Y-m-d', 'to' => 'Y-m-d', 'key' => 'prev']
	 */
	protected function previous_range( $range ) {
		$from = strtotime( $range['from'] . ' 00:00:00' );
		$to   = strtotime( $range['to'] . ' 00:00:00' );
		$span = max( 1, (int) round( ( $to - $from ) / DAY_IN_SECONDS ) + 1 );
		$p_to = $from - DAY_IN_SECONDS;
		return array(
			'from' => wp_date( 'Y-m-d', $p_to - ( $span - 1 ) * DAY_IN_SECONDS ),
			'to'   => wp_date( 'Y-m-d', $p_to ),
			'key'  => 'prev',
		);
	}

	/**
	 * Bandeau « À traiter » : 4 tuiles-raccourcis vers ce qui demande une action.
	 *
	 * Chaque tuile est gouvernée par la capability de sa section cible — on ne
	 * rend jamais un lien vers une page que l'utilisateur ne peut pas ouvrir.
	 * Les compteurs respectent le périmètre produits (comme les pages ciblées).
	 */
	protected function render_todo() {
		$tiles    = array();
		$scope    = $this->user_scope();
		$assigned = $this->assignment_scope();

		$can_orders   = AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) || current_user_can( 'manage_woocommerce' );
		// 🔒 Le DROIT **et** la DÉPENDANCE (32.1). Sans `aod-cod-form`, il n'y a pas
		// de transporteur : la tuile « Échecs de livraison » n'est pas construite,
		// donc `AOD_Carrier::ST_FAILED` n'est jamais lu. C'était la fatale — la
		// constante était résolue en ARGUMENT, avant la garde de la méthode
		// appelée. Cf. AOD_CD_Cod.
		$can_tracking = AOD_CD_Cod::can_tracking();
		$can_stock    = AOD_CD_Roles::user_can( 'aod_cap_stock' ) || current_user_can( 'manage_woocommerce' );

		$counts = $this->cached(
			array( 'todo', $can_orders ? 1 : 0, $can_tracking ? 1 : 0, $can_stock ? 1 : 0, implode( ',', $scope ), $assigned ),
			function () use ( $can_orders, $can_tracking, $can_stock, $scope, $assigned ) {
				return array(
					'pending'  => $can_orders ? $this->count_status( 'pending', '', 0, 0, $scope, $assigned ) : 0,
					'failed'   => $can_tracking ? $this->count_tracking_status( AOD_Carrier::ST_FAILED ) : 0,
					'blocked'  => $can_tracking ? $this->count_blocked_parcels() : 0,
					'lowstock' => $can_stock ? AOD_CD_Section_Stock::instance()->low_stock_count() : 0,
				);
			}
		);

		/*
		 * 🔁 LES TEINTES DES TUILES SONT PASSÉES AUX TOKENS SÉMANTIQUES (2026-08-05).
		 * Elles étaient écrites en dur — `#d97706`, `#dc2626`, `#b45309` — et
		 * doublaient `--cd-warn` / `--cd-bad`, que les KPI voisins emploient pour
		 * EXACTEMENT le même sens. `test-charte-surfaces.php` avait consigné cette
		 * divergence (« deux palettes divergentes qu'une phase ultérieure doit
		 * fusionner ») ; c'est ici qu'elle se ferme, côté tuiles.
		 * 🚨 Et ce n'était pas que de la cohérence : les pastilles étant passées en
		 * APLAT, la teinte porte désormais le glyphe. Mesuré, blanc sur la couleur —
		 * `#d97706` ne donnait que 3,19:1, à 0,19 point du seuil WCAG 1.4.11 pour un
		 * graphique. Sur `--cd-warn` (#8a4708) : 7,03. Les tokens sémantiques ont été
		 * assombris précisément pour être lisibles ; les littéraux, jamais.
		 */
		if ( $can_orders ) {
			$tiles[] = array(
				'n'     => (int) $counts['pending'],
				'label' => __( 'Commandes en attente', 'aod-client-dashboard' ),
				'icon'  => 'pending',
				'tone'  => 'var(--cd-warn)',
				'soft'  => 'var(--cd-warn-soft)',
				'url'   => add_query_arg( 'statut', 'pending', home_url( '/' . AOD_CD_SLUG . '/orders' ) ),
			);
		}
		if ( $can_tracking ) {
			$tiles[] = array(
				'n'     => (int) $counts['failed'],
				'label' => __( 'Échecs de livraison', 'aod-client-dashboard' ),
				'icon'  => 'warning',
				'tone'  => 'var(--cd-bad)',
				'soft'  => 'var(--cd-bad-soft)',
				'url'   => add_query_arg( 'etat', AOD_Carrier::ST_FAILED, home_url( '/' . AOD_CD_SLUG . '/tracking' ) ),
			);
			$tiles[] = array(
				'n'     => (int) $counts['blocked'],
				'label' => __( 'Colis bloqués', 'aod-client-dashboard' ),
				'icon'  => 'blocked',
				'tone'  => 'var(--cd-bad)',
				'soft'  => 'var(--cd-bad-soft)',
				'url'   => add_query_arg( 'etat', 'bloques', home_url( '/' . AOD_CD_SLUG . '/tracking' ) ),
			);
		}
		if ( $can_stock ) {
			$tiles[] = array(
				// low_stock_count() compte les ruptures ET les stocks bas. La tuile
				// s'appelait « Produits en stock bas » : le gérant cliquait, tombait
				// sur « Stock bas : 0 · Ruptures : 3 » et croyait à une incohérence.
				// « À réapprovisionner » couvre honnêtement les deux ; la page Stock
				// donne le détail.
				'n'     => (int) $counts['lowstock'],
				'label' => __( 'Produits à réapprovisionner', 'aod-client-dashboard' ),
				'icon'  => 'products',
				'tone'  => 'var(--cd-warn)',
				'soft'  => 'var(--cd-warn-soft)',
				'url'   => home_url( '/' . AOD_CD_SLUG . '/stock' ),
			);
		}

		if ( ! $tiles ) {
			return; // Aucune section accessible : pas de grille vide.
		}

		// %4$s reçoit un SVG du catalogue fermé (Phase 15), pas du texte : il est
		// injecté tel quel, comme partout ailleurs où AOD_CD_Icon rend.
		echo '<div class="aod-cd-todo">';
		foreach ( $tiles as $t ) {
			// `is-zero` : une tuile à 0 n'appelle aucune action, mais elle criait aussi
			// fort que celle qui en appelle une — quatre pastilles colorées côte à côte,
			// dont trois ne veulent rien dire. Elle passe donc en gris neutre : un « 3 »
			// en rouge se détache alors immédiatement, et une rangée entièrement calme
			// SE LIT comme « rien à traiter », sans qu'on ait à l'écrire.
			// ⚠️ La tuile reste PRÉSENTE, cliquable et dans l'ordre de tabulation : c'est
			// aussi un raccourci de navigation. On la calme, on ne la retire pas.
			printf(
				'<a class="aod-cd-todo-tile%7$s" href="%1$s" style="--tone:%2$s;--tone-soft:%3$s">'
					. '<span class="aod-cd-todo-tile__ic" aria-hidden="true">%4$s</span>'
					. '<span class="aod-cd-todo-tile__txt"><span class="aod-cd-todo-tile__n">%5$s</span>'
					. '<span class="aod-cd-todo-tile__l">%6$s</span></span>'
					. '<span class="aod-cd-todo-tile__go" aria-hidden="true">›</span></a>',
				esc_url( $t['url'] ),
				// 🔁 LA TUILE PORTE DÉSORMAIS SA VRAIE COULEUR, MÊME À ZÉRO (2026-08-05).
				// La veille, un zéro recevait ici `var(--cd-soft)` : quatre carrés GRIS en
				// haut de l'écran le plus regardé. La demande owner (« des couleurs
				// attirantes ») et l'intention d'origine (« le zéro se tait ») ne sont pas
				// contradictoires — elles se règlent par l'INTENSITÉ, pas par le gris.
				// Une tuile à zéro garde sa teinte en aplat PÂLE, une tuile qui appelle une
				// action la porte en aplat PLEIN. L'écart entre les deux est plus net
				// qu'avant, où les deux états étaient pâles.
				//
				// 🚨 CE QUI CHANGE DE PLACE, ET POURQUOI. La neutralisation vivait ici — et
				// non en CSS — parce que `--tone` est un style INLINE, que nulle règle de la
				// feuille ne peut surcharger. Le raisonnement reste vrai ; ce qui a changé,
				// c'est qu'on n'a plus besoin de surcharger la VARIABLE. La feuille agit
				// maintenant sur la DÉCLARATION de la pastille
				// (`.is-zero .aod-cd-todo-tile__ic`), qui n'est pas inline : la classe
				// `is-zero` posée plus bas suffit, sans `!important`.
				esc_attr( $t['tone'] ),
				esc_attr( $t['soft'] ),
				AOD_CD_Icon::svg( $t['icon'], array( 'size' => 22 ) ),
				esc_html( number_format_i18n( $t['n'] ) ),
				esc_html( $t['label'] ),
				0 === (int) $t['n'] ? ' is-zero' : ''
			);
		}
		echo '</div>';
	}

	/**
	 * Nombre de colis à un état de suivi donné (toutes périodes, périmètre inclus).
	 *
	 * @param string $status Clé AOD_Carrier::ST_*.
	 * @return int
	 */
	protected function count_tracking_status( $status ) {
		if ( ! class_exists( 'AOD_Carrier' ) ) {
			return 0;
		}
		$res = $this->filtered_orders(
			array(
				'type'       => 'shop_order',
				'limit'      => 1,
				'paginate'   => true,
				'return'     => 'ids',
				'status'     => 'any',
				'meta_query' => array(
					array( 'key' => AOD_Carrier::META_STATUS, 'value' => $status ),
				),
			),
			0, 0, false, $this->user_scope()
		);
		return isset( $res->total ) ? (int) $res->total : 0;
	}

	/**
	 * Nombre de colis « bloqués » : état non terminal, aucun événement depuis
	 * BLOCKED_DAYS jours. Toutes périodes (le bandeau n'est pas borné au filtre).
	 *
	 * @return int
	 */
	protected function count_blocked_parcels() {
		if ( ! class_exists( 'AOD_Carrier' ) ) {
			return 0;
		}
		$res = $this->filtered_orders(
			array(
				'type'       => 'shop_order',
				'limit'      => 1,
				'paginate'   => true,
				'return'     => 'ids',
				'status'     => 'any',
				'meta_query' => array(
					array(
						'key'     => AOD_Carrier::META_TRACKING,
						'value'   => '',
						'compare' => '!=',
					),
					array(
						'key'     => AOD_Carrier::META_STATUS,
						'value'   => AOD_Carrier::tracking_terminal_statuses(),
						'compare' => 'NOT IN',
					),
					array(
						'key'     => AOD_Carrier::META_STATUS_AT,
						'value'   => time() - ( self::BLOCKED_DAYS * DAY_IN_SECONDS ),
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			),
			0, 0, false, $this->user_scope()
		);
		return isset( $res->total ) ? (int) $res->total : 0;
	}

	/**
	 * Query-string persistante des filtres courants (reportée par les onglets).
	 *
	 * @param array $filters Sortie de read_filters().
	 * @param array $range   Sortie de resolve_range().
	 * @return array<string,string|int>
	 */
	protected function filters_qs( $filters, $range ) {
		$qs = array();
		if ( $filters['produit'] ) {
			$qs['produit'] = $filters['produit'];
		}
		if ( $filters['confirmateur'] ) {
			$qs['confirmateur'] = $filters['confirmateur'];
		}
		if ( 'libre' === $range['key'] ) {
			$qs['date_from'] = $range['from'];
			$qs['date_to']   = $range['to'];
		} elseif ( 'jour' === $range['key'] || 'semaine' === $range['key'] ) {
			$qs['periode'] = $range['key'];
		}
		return $qs;
	}

	/** URL de l'accueil, avec query-string optionnelle. */
	protected function home_url_args( $args = array() ) {
		$url = home_url( '/' . AOD_CD_SLUG . '/home' );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/* ============================================================
	 * Rendu
	 * ========================================================== */

	/**
	 * Alerte « la protection anti-robot ne protège plus » (étape 26.1).
	 *
	 * 🔴 POURQUOI CET ÉCRAN EXISTE. Le challenge Turnstile laissait passer les
	 * commandes dès que Cloudflare était injoignable — politique assumée, pour ne
	 * pas bloquer un vrai client pendant un incident. Mais **rien ne le disait** :
	 * la page de réglages continuait d'afficher « ✅ Actif », et le gérant n'avait
	 * aucun moyen d'apprendre que sa protection était ouverte. Sur un clone sans
	 * réseau sortant — le cas nominal de TEMPLATE.md §3 — cet état était permanent.
	 *
	 * Le vrai défaut n'était pas la politique, c'était son SILENCE. D'où cette
	 * alerte, posée là où le gérant travaille (`/gestion`) et non dans `wp-admin`,
	 * qu'il ne visite jamais.
	 */
	protected function render_antibot_alert() {
		if ( ! class_exists( 'AOD_COD_Turnstile' ) ) {
			return; // Plugin COD absent : rien à dire.
		}
		$sante = AOD_COD_Turnstile::health();
		if ( empty( $sante['degraded'] ) ) {
			return; // Service sain : pas de bruit.
		}

		if ( ! empty( $sante['strict'] ) ) {
			$ton   = 'bad';
			$titre = __( 'Protection anti-robot : vérification impossible', 'aod-client-dashboard' );
			$corps = __( 'Le service de vérification est injoignable de façon répétée. Les commandes non vérifiées sont désormais refusées, pour éviter les fausses commandes. Vérifiez la connexion du serveur et vos clés dans Réglages → COD Anti-spam.', 'aod-client-dashboard' );
		} else {
			$ton   = 'warn';
			$titre = __( 'Protection anti-robot momentanément inactive', 'aod-client-dashboard' );
			$corps = __( 'Le service de vérification ne répond pas. Les commandes continuent d’être acceptées pour ne pas vous faire perdre de ventes, mais elles ne sont plus filtrées : surveillez les fausses commandes. Si cela persiste, vérifiez la connexion du serveur.', 'aod-client-dashboard' );
		}

		echo '<div class="aod-cd-alerts">';
		echo '<div class="aod-cd-alert aod-cd-alert--' . esc_attr( $ton ) . '">';
		echo '<span class="aod-cd-alert__ic" aria-hidden="true">' . AOD_CD_Icon::svg( 'warning', array( 'size' => 20 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé AOD_CD_Icon.
		echo '<div class="aod-cd-alert__txt">';
		echo '<p class="aod-cd-alert__title">' . esc_html( $titre ) . '</p>';
		echo '<p class="aod-cd-alert__body">' . esc_html( $corps ) . '</p>';
		echo '</div></div></div>';
	}

	public function render() {
		$tabs = $this->visible_tabs();
		if ( ! $tabs ) {
			// Ne devrait pas arriver (la section est gouvernée par capability),
			// mais on affiche un état vide propre plutôt qu'une page cassée.
			echo AOD_CD_Dashboard::empty_state( 'stock', __( 'Aucune statistique ne vous est accessible pour le moment.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$filters = $this->read_filters();
		$range   = $this->resolve_range( $filters );
		$active  = ( $filters['onglet'] && isset( $tabs[ $filters['onglet'] ] ) ) ? $filters['onglet'] : array_key_first( $tabs );

		$user  = wp_get_current_user();
		$first = trim( $user->first_name ) ? trim( $user->first_name ) : $user->display_name;

		// 🚨 « Vue d'ensemble DE LA BOUTIQUE » SOUS LE PRÉNOM D'UN CONFIRMATEUR (48.4).
		// Le sous-titre annonçait la boutique à quelqu'un dont TOUT le reste de
		// l'espace — sa liste Commandes, son Suivi, ses Abandonnées — est restreint à
		// ses commandes assignées. Juxtaposé à « Bonjour <prénom> », il fait lire
		// « voici TES chiffres » là où le premier nombre est celui du magasin. C'est
		// la moitié du contresens que la Phase 48 corrige : la carte disait son
		// périmètre, l'en-tête continuait de dire le contraire deux lignes plus haut.
		$sous_titre = current_user_can( 'manage_woocommerce' )
			/* translators: 1 : date du jour, 2 : période affichée (ex. « 30 derniers jours ») */
			? __( 'Vue d’ensemble de la boutique — %1$s · %2$s.', 'aod-client-dashboard' )
			/* translators: 1 : date du jour, 2 : période affichée (ex. « 30 derniers jours ») */
			: __( 'Vos commandes — %1$s · %2$s.', 'aod-client-dashboard' );

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			/* translators: %s : prénom du gérant */
			// 🚫 Le « 👋 » a été retiré (2026-08-04). La Phase 15 a sorti les emojis de
			// `/gestion` pour une raison mesurée — la police emoji du système donne un
			// rendu DIFFÉRENT sur chaque machine — et l'a remplacée par le catalogue
			// fermé `AOD_CD_Icon`. Celui-ci avait survécu au balayage, seul de son
			// espèce, dans le titre le plus visible de l'application.
			sprintf( __( 'Bonjour %s', 'aod-client-dashboard' ), $first ),
			sprintf(
				$sous_titre,
				date_i18n( get_option( 'date_format' ) ),
				$this->range_label( $range )
			)
		);

		$this->render_antibot_alert();
		$this->render_todo();
		$this->render_tabs( $tabs, $active, $filters, $range );
		$this->render_filters( $active, $filters, $range );

		if ( 'revenus' === $active ) {
			$this->tab_revenue( $range, $filters );
		} elseif ( 'livraison' === $active ) {
			$this->tab_delivery( $range, $filters );
		} elseif ( 'confirmation' === $active ) {
			$this->tab_confirmation( $range, $filters );
		}
	}

	/**
	 * Barre de filtres commune : formulaire GET natif (fonctionne sans JS,
	 * inputs date natifs OK mobile/RTL) + raccourcis de période en liens.
	 *
	 * @param string $active  Onglet actif (reporté en champ caché).
	 * @param array  $filters Sortie de read_filters().
	 * @param array  $range   Sortie de resolve_range().
	 */
	protected function render_filters( $active, $filters, $range ) {
		$is_manager = current_user_can( 'manage_woocommerce' );

		// Raccourcis : conservent produit/confirmateur, remplacent la période.
		$keep = array( 'onglet' => $active );
		if ( $filters['produit'] ) {
			$keep['produit'] = $filters['produit'];
		}
		if ( $filters['confirmateur'] ) {
			$keep['confirmateur'] = $filters['confirmateur'];
		}
		$shortcuts = array(
			'jour'    => __( 'Jour', 'aod-client-dashboard' ),
			'semaine' => __( 'Semaine', 'aod-client-dashboard' ),
		);

		// Un confirmateur à périmètre restreint ne choisit que parmi ses produits.
		$prod_args = array(
			'limit'   => -1,
			'status'  => 'publish',
			'orderby' => 'title',
			'order'   => 'ASC',
		);
		$scope = $this->user_scope();
		if ( $scope ) {
			$prod_args['include'] = $scope;
		}
		$products = wc_get_products( $prod_args );
		?>
		<?php
		/*
		 * 🚨 `data-aod-select` N'EST PAS DÉCORATIF (2026-08-05, retour owner : « quand
		 * je clique sur Produit, c'est moche »). Sans cet attribut, le navigateur ouvre
		 * la liste d'options du SYSTÈME D'EXPLOITATION — que le CSS ne peut pas
		 * atteindre, ni pour la teinte, ni pour l'espacement, ni pour l'ouverture.
		 * Aucune retouche de feuille ne pouvait donc répondre à la demande.
		 *
		 * L'attribut branche le select personnalisé de `dashboard.js` (`bindSelects()`) :
		 * le `<select>` natif reste dans le DOM, caché, et continue de porter la
		 * soumission du formulaire — c'est de l'AMÉLIORATION PROGRESSIVE, le filtre
		 * fonctionne encore si le JS ne se charge pas.
		 *
		 * ⚠️ Le composant existait et servait déjà dans la section Livraison ; ces deux
		 * champs étaient simplement passés à côté. Une amélioration qui n'est pas
		 * DEMANDÉE par un composant reste invisible à qui écrit le composant suivant.
		 */
		?>
		<form class="aod-cd-form aod-cd-filters" method="get" action="<?php echo esc_url( $this->home_url_args() ); ?>">
			<input type="hidden" name="onglet" value="<?php echo esc_attr( $active ); ?>">
			<div class="aod-cd-field">
				<label class="aod-cd-label" for="aod-cd-f-product"><?php esc_html_e( 'Produit', 'aod-client-dashboard' ); ?></label>
				<select id="aod-cd-f-product" name="produit" data-aod-select>
					<option value=""><?php esc_html_e( 'Tous les produits', 'aod-client-dashboard' ); ?></option>
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p->get_id() ); ?>" <?php selected( $filters['produit'], $p->get_id() ); ?>><?php echo esc_html( $p->get_name() ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php if ( $is_manager ) :
				$members = get_users( array(
					'role'    => AOD_CD_ROLE_CONFIRMER,
					'orderby' => 'display_name',
					'order'   => 'ASC',
				) );
				?>
				<div class="aod-cd-field">
					<label class="aod-cd-label" for="aod-cd-f-confirmer"><?php esc_html_e( 'Confirmateur', 'aod-client-dashboard' ); ?></label>
					<select id="aod-cd-f-confirmer" name="confirmateur" data-aod-select>
						<option value=""><?php esc_html_e( 'Tous les confirmateurs', 'aod-client-dashboard' ); ?></option>
						<?php foreach ( $members as $m ) : ?>
							<option value="<?php echo esc_attr( $m->ID ); ?>" <?php selected( $filters['confirmateur'], $m->ID ); ?>><?php echo esc_html( $m->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>
			<div class="aod-cd-field">
				<label class="aod-cd-label" for="aod-cd-f-from"><?php esc_html_e( 'Du', 'aod-client-dashboard' ); ?></label>
				<input type="date" id="aod-cd-f-from" name="date_from" value="<?php echo esc_attr( $range['from'] ); ?>">
			</div>
			<div class="aod-cd-field">
				<label class="aod-cd-label" for="aod-cd-f-to"><?php esc_html_e( 'Au', 'aod-client-dashboard' ); ?></label>
				<input type="date" id="aod-cd-f-to" name="date_to" value="<?php echo esc_attr( $range['to'] ); ?>">
			</div>
			<div class="aod-cd-field aod-cd-filters-actions">
				<span class="aod-cd-label" aria-hidden="true">&nbsp;</span>
				<div class="aod-cd-filters-btns">
					<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Filtrer', 'aod-client-dashboard' ); ?></button>
					<?php foreach ( $shortcuts as $slug => $label ) :
						$cls = 'aod-cd-btn aod-cd-btn-sm' . ( $slug === $range['key'] ? ' is-active' : '' );
						?>
						<a class="<?php echo esc_attr( $cls ); ?>" href="<?php echo esc_url( $this->home_url_args( array_merge( $keep, array( 'periode' => $slug ) ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Liens d'onglet — chacun reporte les filtres courants (persistance).
	 *
	 * @param array  $tabs    Sortie de visible_tabs().
	 * @param string $active  Onglet actif.
	 * @param array  $filters Sortie de read_filters().
	 * @param array  $range   Sortie de resolve_range().
	 */
	protected function render_tabs( $tabs, $active, $filters, $range ) {
		$qs = $this->filters_qs( $filters, $range );
		// Segmented control canonique (étape 5.4) — remplace .aod-cd-tabs. La
		// navigation reste en GET (paramètre `onglet`), sans JavaScript.
		echo '<div class="aod-cd-seg" role="group" aria-label="' . esc_attr__( 'Onglets de statistiques', 'aod-client-dashboard' ) . '">';
		/*
		 * 🎨 CHAQUE ONGLET PORTE UNE ICÔNE (2026-08-06, retour owner : « ce sont des
		 * écritures simples »). Le constat est juste : trois libellés nus posés dans
		 * une piste se lisent comme du TEXTE, pas comme un choix — le seul signal
		 * était l'encre de l'onglet actif, donc rien du tout sur les deux autres.
		 *
		 * 🔒 L'icône est associée au SLUG, JAMAIS au libellé. Le libellé est traduit :
		 * une table indexée dessus ne rendrait plus rien en arabe, et personne ne le
		 * verrait sur un clone français.
		 *
		 * Les trois redisent ce que la page montre déjà juste en dessous — `money`
		 * est l'icône du KPI « Chiffre d'affaires », `check` celle des commandes
		 * confirmées, `shipping` le camion de la livraison. Aucune icône nouvelle :
		 * le catalogue est fermé, et un onglet qui emprunterait un glyphe inédit
		 * dirait autre chose que la donnée qu'il ouvre.
		 * ⚠️ Un slug hors table ne casse rien : `''` est rendu, l'onglet reste un
		 * libellé nu. C'est le comportement voulu si un 4ᵉ onglet apparaît sans
		 * qu'on ait tranché son icône — il ne doit pas hériter de celle du voisin.
		 */
		$icones = array(
			'revenus'      => 'money',
			'confirmation' => 'check',
			'livraison'    => 'shipping',
		);
		foreach ( $tabs as $slug => $label ) {
			$cls = 'aod-cd-seg__btn' . ( $slug === $active ? ' is-active' : '' );
			$url = $this->home_url_args( array_merge( $qs, array( 'onglet' => $slug ) ) );
			printf(
				'<a class="%1$s" href="%2$s"%3$s>%4$s<span>%5$s</span></a>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- %4$s : SVG d'un catalogue FERMÉ, nom d'icône littéral du dépôt ; wp_kses_post() mangerait ses attributs. Le libellé (%5$s) passe par esc_html().
				esc_attr( $cls ),
				esc_url( $url ),
				$slug === $active ? ' aria-current="true"' : '',
				isset( $icones[ $slug ] ) ? AOD_CD_Icon::svg( $icones[ $slug ], array( 'size' => 16 ) ) : '',
				esc_html( $label )
			);
		}
		echo '</div>';
	}

	/* ============================================================
	 * Onglet Revenus (ancienne section « Statistiques », gérant uniquement)
	 * ========================================================== */

	/**
	 * Agrégats de l'onglet Revenus sur la plage donnée (cache 5 min).
	 *
	 * Onglet réservé au gérant : jamais filtré par périmètre produits
	 * (assigned_products() est vide pour lui) — pas de scoped_orders() ici.
	 * Filtres d'écran (étape 2.2) : produit → seules les LIGNES de ce produit
	 * comptent (CA hors livraison et hors autres produits des mêmes commandes) ;
	 * confirmateur → commandes portant `_aod_confirmed_by` (la nature des
	 * montants ne change pas). Les deux se combinent en ET avec la plage.
	 *
	 * @param array $range   Sortie de resolve_range().
	 * @param array $filters Sortie de read_filters().
	 * @return array Données prêtes à afficher (valeurs brutes, mise en forme au rendu).
	 */
	protected function revenue_data( $range, $filters ) {
		$fpid = absint( $filters['produit'] );
		$fcid = absint( $filters['confirmateur'] );
		return $this->cached(
			array( 'revenus', $range['from'], $range['to'], $fpid, $fcid ),
			function () use ( $range, $fpid, $fcid ) {
				$date_arg = $range['from'] . '...' . $range['to'];

				// Les commandes encaissées de la période servent à la fois au CA, au
				// panier moyen et au top-produits — PARCOURUES PAR LOTS (31.6) :
				// toutes les tenir en mémoire coûtait +100,5 Mo à 3 000 commandes,
				// soit l'écran blanc passé 10 000. Cf. `each_filtered_order()`.

				// Bacs du graphe préremplis à 0 (les creux restent visibles).
				$monthly = false;
				$bins    = $this->range_bins( $range['from'], $range['to'], $monthly );

				$revenue   = 0.0;
				$prod_rev  = 0.0;   // CA produits (hors livraison) — base de la marge.
				$cost_tot  = 0.0;   // Coût d'achat cumulé des lignes dont le coût est connu.
				$miss_cost = false; // Au moins une ligne sans prix d'achat renseigné.
				$top       = array(); // pid|hash => [ name, qty, rev, cost, pid, cknown ].

				// `nb_paid` valait `count( $paid )` : le compte revient désormais du
				// parcours lui-même (même nombre, jamais toute la liste en mémoire).
				$nb_paid = $this->each_filtered_order( array(
					'status'       => array( 'wc-processing', 'wc-aod-confirmed', 'wc-completed' ),
					'date_created' => $date_arg,
				), function ( $o ) use ( $fpid, $monthly, &$bins, &$revenue, &$prod_rev, &$cost_tot, &$miss_cost, &$top ) {
					// Sans filtre produit : montant = total de la commande (livraison
					// incluse — comportement historique). Avec filtre : somme des
					// lignes du produit, cumulée pendant la boucle d'items.
					$amount = $fpid ? 0.0 : (float) $o->get_total();

					foreach ( $o->get_items() as $item ) {
						if ( $fpid && (int) $item->get_product_id() !== $fpid && (int) $item->get_variation_id() !== $fpid ) {
							continue; // Ligne d'un autre produit d'une commande mixte.
						}
						$pid = $item->get_product_id();
						$key = $pid ? (string) $pid : 'x' . md5( $item->get_name() );
						if ( ! isset( $top[ $key ] ) ) {
							$top[ $key ] = array(
								'name'   => $item->get_name(),
								'qty'    => 0,
								'rev'    => 0.0,
								'cost'   => 0.0,
								'cknown' => true,
								'pid'    => $pid,
							);
						}
						$qty       = (int) $item->get_quantity();
						$line_rev  = (float) $item->get_total();
						$prod_rev += $line_rev;
						if ( $fpid ) {
							$amount += $line_rev;
						}
						$top[ $key ]['qty'] += $qty;
						$top[ $key ]['rev'] += $line_rev;

						// Coût d'achat : meta produit (ou produit parent pour une variation).
						$cost = $this->item_cost_price( $item );
						if ( null === $cost ) {
							$miss_cost             = true;
							$top[ $key ]['cknown'] = false;
						} else {
							$cost_tot            += $cost * $qty;
							$top[ $key ]['cost'] += $cost * $qty;
						}
					}

					$revenue += $amount;
					$dc = $o->get_date_created();
					if ( $dc ) {
						$bkey = wp_date( $monthly ? 'Y-m' : 'Y-m-d', $dc->getTimestamp() );
						if ( isset( $bins[ $bkey ] ) ) {
							$bins[ $bkey ]['val'] += $amount;
						}
					}
				}, $fpid, $fcid );

				usort( $top, function ( $a, $b ) {
					if ( $a['rev'] === $b['rev'] ) {
						return $b['qty'] - $a['qty'];
					}
					return ( $a['rev'] < $b['rev'] ) ? 1 : -1;
				} );

				return array(
					'revenue'    => $revenue,
					'nb_paid'    => $nb_paid,
					'prod_rev'   => $prod_rev,
					'cost_tot'   => $cost_tot,
					'miss_cost'  => $miss_cost,
					'top'        => array_slice( $top, 0, 10 ),
					'bins'       => $bins,
					// « En attente » = état courant (toutes périodes), filtre produit
					// seulement (une commande non confirmée n'a pas de confirmateur) ;
					// « Annulées » = période + produit + confirmateur.
					'nb_pending' => $this->count_status( 'pending', '', $fpid, 0 ),
					'nb_cancel'  => $this->count_status( 'cancelled', $date_arg, $fpid, $fcid ),
					'nb_prod'    => (int) wp_count_posts( 'product' )->publish,
				);
			},
			$range['from'],
			$range['to']
		);
	}

	/**
	 * Delta d'un KPI vs la période précédente (composant `.aod-cd-kpi__delta`).
	 *
	 * La COULEUR est sémantique, pas directionnelle : `--up` est vert, `--down`
	 * rouge. Une hausse des annulations ou du délai de livraison doit donc
	 * s'afficher en ROUGE → c'est le rôle de $higher_is_better. La FLÈCHE, elle,
	 * indique le sens réel de la variation (▲ hausse / ▼ baisse).
	 *
	 * @param float $now              Valeur de la période courante.
	 * @param float $prev             Valeur de la période précédente.
	 * @param bool  $higher_is_better Vrai si « plus, c'est mieux ».
	 * @param bool  $points           Vrai si la métrique est déjà un pourcentage
	 *                                (la variation s'exprime alors en points).
	 * @return string HTML sûr, ou '' si le delta n'a pas de sens.
	 */
	protected function kpi_delta( $now, $prev, $higher_is_better = true, $points = false ) {
		$now  = (float) $now;
		$prev = (float) $prev;

		if ( $points ) {
			$diff = $now - $prev;
			/* translators: %s : variation en points de pourcentage */
			$txt = sprintf( __( '%s pts', 'aod-client-dashboard' ), ( $diff >= 0 ? '+' : '−' ) . number_format_i18n( abs( $diff ), 1 ) );
		} else {
			// Base nulle : une variation en pourcentage n'a pas de sens (+∞ %).
			if ( $prev <= 0 ) {
				return '';
			}
			$diff = ( ( $now - $prev ) / $prev ) * 100;
			$txt  = ( $diff >= 0 ? '+' : '−' ) . number_format_i18n( abs( $diff ), 1 ) . ' %';
		}

		if ( abs( $diff ) < 0.05 ) {
			$mod   = 'flat';
			$arrow = '—';
		} else {
			$rising = ( $diff > 0 );
			$good   = ( $rising === (bool) $higher_is_better );
			$mod    = $good ? 'up' : 'down';
			$arrow  = $rising ? '▲' : '▼';
		}

		return '<span class="aod-cd-kpi__delta aod-cd-kpi__delta--' . esc_attr( $mod ) . '">'
			. '<span aria-hidden="true">' . esc_html( $arrow ) . '</span> ' . esc_html( $txt )
			. ' <span class="aod-cd-kpi__vs">' . esc_html__( 'vs période préc.', 'aod-client-dashboard' ) . '</span></span>';
	}

	/**
	 * Rangée de KPI unifiés — délègue au composant partagé (étape 5.5).
	 *
	 * Le balisage vit désormais dans AOD_CD_Dashboard::kpis() : Stock l'emploie
	 * aussi, et une seule définition évite que les deux pages divergent.
	 *
	 * @param array[] $cards Chaque carte : [ label, valeur HTML, icône, tonalité,
	 *                       delta HTML (facultatif) ].
	 */
	protected function render_kpis( $cards ) {
		echo AOD_CD_Dashboard::kpis( $cards ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le composant.
	}

	/**
	 * Onglet Revenus : cartes KPI, graphe du CA, top-produits.
	 *
	 * Contenu migré de l'ancienne section « Statistiques » — la période vient
	 * du filtre commun ; depuis 2.2, produit et confirmateur s'appliquent aussi.
	 *
	 * @param array $range   Sortie de resolve_range().
	 * @param array $filters Sortie de read_filters().
	 */
	protected function tab_revenue( $range, $filters ) {
		$d = $this->revenue_data( $range, $filters );

		$this->filter_context_line( $filters );

		$avg         = $d['nb_paid'] > 0 ? $d['revenue'] / $d['nb_paid'] : 0.0;
		$margin      = $d['prod_rev'] - $d['cost_tot'];
		$margin_rate = $d['prod_rev'] > 0 ? ( $margin / $d['prod_rev'] ) * 100 : 0.0;

		// Période précédente (même durée) — mise en cache par la clé from/to de
		// revenue_data(), donc aucune requête supplémentaire au rechargement.
		$p           = $this->revenue_data( $this->previous_range( $range ), $filters );
		$p_avg       = $p['nb_paid'] > 0 ? $p['revenue'] / $p['nb_paid'] : 0.0;
		$p_margin    = $p['prod_rev'] - $p['cost_tot'];
		$p_mrate     = $p['prod_rev'] > 0 ? ( $p_margin / $p['prod_rev'] ) * 100 : 0.0;

		// Chaque carte : libellé, valeur (HTML autorisé), icône, tonalité, delta.
		// Pas de delta sur « En attente » ni « Produits en ligne » : ce sont des
		// instantanés hors période, une comparaison n'aurait aucun sens.
		$cards = array(
			// 6ᵉ élément = modificateur. Le chiffre d'affaires est LA raison pour
			// laquelle le gérant ouvre cet écran ; il occupait pourtant la même
			// surface que « Produits en ligne ». Huit cartes de poids identique, c'est
			// huit cartes sans hiérarchie — l'œil n'a nulle part où se poser.
			// 🎨 La distinction se fait par l'ESPACE (deux colonnes), jamais par une
			// teinte : l'accent est choisi par le client dans le Personnalisateur, et
			// une carte colorée à la main sortirait du garde-fou de contraste que
			// `run.sh contraste` éprouve sur six accents hostiles.
			// 🎨 TONALITÉ `accent` = « cette pastille ne signifie RIEN ». Ces trois-là
			// portaient du bleu, de l'indigo et du teal : trois teintes fixes, choisies
			// pour varier, posées à côté d'un rouge (annulée) et d'un ambre (en attente)
			// qui, eux, portent un état. Quand le décoratif emprunte le vocabulaire du
			// sémantique, le sémantique cesse d'être lisible — le gérant ne peut plus
			// savoir quelle couleur mérite son attention.
			// Elles retombent donc sur `--cd-accent-soft`, la teinte du client : la page
			// gagne en cohérence de marque ET rend leur signal au rouge et à l'ambre.
			array( __( 'Chiffre d’affaires', 'aod-client-dashboard' ), wp_kses_post( wc_price( $d['revenue'] ) ), 'money', 'accent', $this->kpi_delta( $d['revenue'], $p['revenue'] ), 'primary' ),
			array( __( 'Commandes encaissées', 'aod-client-dashboard' ), (string) $d['nb_paid'], 'orders', 'accent', $this->kpi_delta( $d['nb_paid'], $p['nb_paid'] ) ),
			array( __( 'Panier moyen', 'aod-client-dashboard' ), wp_kses_post( wc_price( $avg ) ), 'cart', 'accent', $this->kpi_delta( $avg, $p_avg ) ),
			array( __( 'Marge estimée', 'aod-client-dashboard' ), wp_kses_post( wc_price( $margin ) ), 'trend-up', 'green', $this->kpi_delta( $margin, $p_margin ) ),
			array( __( 'Taux de marge', 'aod-client-dashboard' ), esc_html( number_format_i18n( $margin_rate, 1 ) . ' %' ), 'percent', 'green', $this->kpi_delta( $margin_rate, $p_mrate, true, true ) ),
			array( __( 'En attente (en cours)', 'aod-client-dashboard' ), (string) $d['nb_pending'], 'pending', 'amber' ),
			array( __( 'Annulées', 'aod-client-dashboard' ), (string) $d['nb_cancel'], 'cancel', 'red', $this->kpi_delta( $d['nb_cancel'], $p['nb_cancel'], false ) ),
			array( __( 'Produits en ligne', 'aod-client-dashboard' ), (string) $d['nb_prod'], 'products', 'accent' ),
		);

		$this->render_kpis( $cards );

		/*
		 * Les deux graphes de la période, côte à côte : la courbe dit QUAND le
		 * chiffre est arrivé, l'anneau dit D'OÙ il vient.
		 * ⚠️ L'enveloppe n'est pas décorative : c'est elle qui porte la grille, donc
		 * l'empilement sur mobile. Sans elle il faudrait donner une largeur à chaque
		 * carte, et le jour où un troisième graphe arrive, la règle serait à réécrire
		 * à deux endroits.
		 */
		echo '<div class="aod-cd-charts">';
		$this->render_stats_chart( $d['bins'] );
		$this->render_stats_donut( $d['top'], isset( $d['prod_rev'] ) ? (float) $d['prod_rev'] : 0.0 );
		echo '</div>';

		// Top-produits de la période (par CA généré).
		echo '<h2 class="aod-cd-form-subtitle aod-cd-blocktitle">' . esc_html__( 'Top produits', 'aod-client-dashboard' ) . '</h2>';
		if ( empty( $d['top'] ) ) {
			echo AOD_CD_Dashboard::empty_state( 'chart', __( 'Aucune vente sur cette période.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			/* 46.2 — les intitulés sont HISSÉS dans une variable parce qu'ils servent
			   maintenant DEUX fois : l'en-tête de colonne (desktop) et le
			   `data-label` de chaque cellule (carte mobile, `.aod-cd-table--cards`
			   rappelle l'en-tête via `content:attr(data-label)`). Les recopier
			   serait la garantie qu'ils divergent — et une carte mobile dont le
			   libellé ne correspond plus à sa colonne est pire qu'absente. */
			$cols_top = array(
				__( 'Produit', 'aod-client-dashboard' ),
				__( 'Quantité vendue', 'aod-client-dashboard' ),
				__( 'CA généré', 'aod-client-dashboard' ),
				__( 'Marge', 'aod-client-dashboard' ),
			);
			echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards"><thead><tr>';
			foreach ( $cols_top as $h ) {
				echo '<th>' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $d['top'] as $row ) {
				$name = $row['name'];
				if ( $row['pid'] && current_user_can( 'edit_product', $row['pid'] ) ) {
					$edit = add_query_arg( array( 'action' => 'edit', 'product' => $row['pid'] ), home_url( '/' . AOD_CD_SLUG . '/products' ) );
					$name = '<a href="' . esc_url( $edit ) . '">' . esc_html( $row['name'] ) . '</a>';
				} else {
					$name = esc_html( $name );
				}
				// Marge produit : « — » si aucun prix d'achat n'est renseigné pour ce produit.
				if ( $row['cknown'] && $row['cost'] > 0 ) {
					$pm          = $row['rev'] - $row['cost'];
					$margin_cell = wp_kses_post( wc_price( $pm ) );
				} else {
					$margin_cell = '<span class="aod-cd-muted">—</span>';
				}
				echo '<tr><td data-label="' . esc_attr( $cols_top[0] ) . '">' . $name . '</td>'
					. '<td data-label="' . esc_attr( $cols_top[1] ) . '">' . (int) $row['qty'] . '</td>'
					. '<td data-label="' . esc_attr( $cols_top[2] ) . '">' . wp_kses_post( wc_price( $row['rev'] ) ) . '</td>'
					. '<td data-label="' . esc_attr( $cols_top[3] ) . '">' . $margin_cell . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}

		// ⚠️ L'AVERTISSEMENT RESTE VISIBLE, la MÉTHODOLOGIE se replie. Le tri n'est
		// pas cosmétique : cette note-ci dit qu'un nombre AFFICHÉ À L'ÉCRAN est faux
		// — partiel, faute de prix d'achat. La replier reviendrait à publier une
		// marge sous-évaluée en cachant ce qui l'explique, et le gérant prendrait ses
		// décisions dessus. Une réserve sur une donnée voyage avec la donnée.
		if ( $d['miss_cost'] ) {
			echo '<p class="aod-cd-note aod-cd-note--warn">'
				. AOD_CD_Icon::svg( 'warning', array( 'size' => 15 ) ) // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
				. ' ' . esc_html__( 'Certains produits vendus n’ont pas de prix d’achat renseigné — la marge affichée est donc une estimation partielle.', 'aod-client-dashboard' )
				. '</p>';
		}

		// Le reste explique COMMENT les chiffres sont calculés. C'est de la référence :
		// on la lit une fois, à la prise en main, puis plus jamais — alors qu'elle
		// occupait en permanence le bas de chaque écran de statistiques, en trois
		// paragraphes de même taille et de même couleur. Repliée, l'information n'est
		// pas perdue : elle est à un clic, et la page cesse d'être un mur.
		// `<details>` natif : ni script, ni état à mémoriser, ouvrable au clavier, et
		// le contenu reste dans le document — donc lisible par une synthèse vocale et
		// trouvable par la recherche du navigateur, contrairement à une infobulle.
		echo '<details class="aod-cd-note-fold">';
		echo '<summary>' . esc_html__( 'Comment ces chiffres sont calculés', 'aod-client-dashboard' ) . '</summary>';
		echo '<p class="aod-cd-note">' . esc_html__( 'CA = total des commandes en cours, confirmées et terminées sur la période sélectionnée. « En attente » et « Produits en ligne » reflètent l’état actuel, toutes périodes confondues.', 'aod-client-dashboard' ) . '</p>';
		if ( $filters['produit'] ) {
			echo '<p class="aod-cd-note">' . esc_html__( 'Filtre produit actif : CA, marge et graphe ne comptent que les lignes de ce produit (hors livraison et hors autres produits des mêmes commandes) ; « En attente » et « Annulées » ne comptent que les commandes le contenant.', 'aod-client-dashboard' ) . '</p>';
		}
		echo '<p class="aod-cd-note">' . esc_html__( 'Marge = CA produits (hors livraison) − prix d’achat. Renseignez le « Prix d’achat » d’un produit pour qu’il compte dans la marge ; les produits sans prix d’achat sont ignorés (un « — » s’affiche).', 'aod-client-dashboard' ) . '</p>';
		echo '</details>';
	}

	/**
	 * Compte les commandes d'un statut (COUNT SQL via paginate, HPOS-safe).
	 *
	 * Appelée depuis l'onglet Revenus (réservé au gérant → périmètre vide) et
	 * depuis le bandeau « À traiter » (qui, lui, PASSE le périmètre produits :
	 * ses compteurs doivent correspondre à ce que la page ciblée affichera).
	 *
	 * @param string $slug         Statut sans préfixe wc-.
	 * @param string $date         Filtre date_created optionnel.
	 * @param int    $product_id   Filtre produit optionnel (0 = sans).
	 * @param int    $confirmer_id Filtre confirmateur optionnel (0 = sans).
	 * @param int[]  $scope_ids    Périmètre produits (vide = aucune restriction).
	 * @param int    $assigned_id  Confirmateur ASSIGNÉ (Phase 42.3bis, 0 = sans).
	 * @return int
	 */
	protected function count_status( $slug, $date = '', $product_id = 0, $confirmer_id = 0, $scope_ids = array(), $assigned_id = 0 ) {
		$args = array(
			'type'     => 'shop_order',
			'limit'    => 1,
			'paginate' => true,
			'return'   => 'ids',
			'status'   => array( 'wc-' . $slug ),
		);
		if ( '' !== $date ) {
			$args['date_created'] = $date;
		}
		$res = $this->filtered_orders( $args, $product_id, $confirmer_id, false, $scope_ids, $assigned_id );
		return isset( $res->total ) ? (int) $res->total : 0;
	}

	/**
	 * Prix d'achat unitaire d'une ligne de commande.
	 *
	 * Lit le meta `_aod_cost_price` du produit ; pour une variation sans coût
	 * propre, retombe sur le coût du produit parent. Retourne null quand aucun
	 * prix d'achat n'est renseigné (la ligne est alors exclue du calcul de marge).
	 *
	 * 🔓 PASSÉE `public` LE 2026-08-10, PARCE QU'ELLE A EU UN SECOND LECTEUR.
	 * `AOD_CD_Section_Finance` calcule la rentabilité par produit et a besoin
	 * EXACTEMENT de cette règle — meta du produit, repli sur le parent pour une
	 * variation, `null` quand rien n'est renseigné. La recopier là-bas aurait
	 * donné deux définitions du prix d'achat : le jour où l'une gagnerait le
	 * repli sur le parent et pas l'autre, l'Accueil et Finance afficheraient deux
	 * marges différentes pour le même produit, toutes deux plausibles.
	 * ⚠️ Elle ne lit RIEN d'autre qu'un meta produit et ne mute rien : l'ouvrir
	 * n'expose aucun privilège. Ce n'est pas le cas de tous les helpers de cette
	 * classe — cf. le contrat de `test-nonce-contract.php` sur ceux qui lisent le
	 * POST, qui doivent, eux, rester privés.
	 *
	 * @param WC_Order_Item_Product $item Ligne de commande.
	 * @return float|null Coût unitaire, ou null si inconnu.
	 */
	public function item_cost_price( $item ) {
		$product = $item->get_product();
		if ( ! $product ) {
			return null;
		}
		$cost = $product->get_meta( '_aod_cost_price' );
		if ( ( '' === $cost || null === $cost ) && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$cost = $parent->get_meta( '_aod_cost_price' );
			}
		}
		if ( '' === $cost || null === $cost ) {
			return null;
		}
		return (float) $cost;
	}

	/**
	 * Rappel du périmètre quand un filtre produit/confirmateur est actif —
	 * partagé par les onglets (un ID supprimé s'affiche « #<ID> » sans casser).
	 *
	 * @param array $filters Sortie de read_filters().
	 */
	protected function filter_context_line( $filters ) {
		$ctx = array();
		if ( $filters['produit'] ) {
			$pname = get_the_title( $filters['produit'] );
			/* translators: %s : nom du produit filtré. */
			$ctx[] = sprintf( __( 'produit : %s', 'aod-client-dashboard' ), $pname ? $pname : '#' . $filters['produit'] );
		}
		if ( $filters['confirmateur'] ) {
			$user = get_userdata( $filters['confirmateur'] );
			/* translators: %s : nom du confirmateur filtré. */
			$ctx[] = sprintf( __( 'confirmateur : %s', 'aod-client-dashboard' ), $user ? $user->display_name : '#' . $filters['confirmateur'] );
		}
		if ( $ctx ) {
			/* translators: %s : liste des filtres actifs (produit, confirmateur). */
			echo '<p class="aod-cd-note aod-cd-filter-ctx">' . AOD_CD_Icon::svg( 'search', array( 'size' => 15 ) ) . ' ' . esc_html( sprintf( __( 'Chiffres filtrés — %s', 'aod-client-dashboard' ), implode( ' · ', $ctx ) ) ) . '</p>';
		}
	}

	/* ============================================================
	 * Onglet Livraison (étape 2.3 — statuts de colis normalisés)
	 * ========================================================== */

	/**
	 * Groupe KPI d'un statut de colis normalisé (AOD_Carrier::ST_*).
	 *
	 * Cinq groupes dont la somme = expédiées : livré / retourné / échec /
	 * annulé sont repris tels quels ; tout le reste (créé, en transit, sorti
	 * en livraison, inconnu, méta absente) = « en transit ».
	 *
	 * @param string $status Clé ST_* (ou chaîne vide si jamais synchronisé).
	 * @return string delivered|returned|failed|cancelled|in_transit.
	 */
	protected function delivery_group( $status ) {
		if ( in_array( $status, array( AOD_Carrier::ST_DELIVERED, AOD_Carrier::ST_RETURNED, AOD_Carrier::ST_FAILED, AOD_Carrier::ST_CANCELLED ), true ) ) {
			return $status;
		}
		return AOD_Carrier::ST_IN_TRANSIT;
	}

	/**
	 * Agrégats de l'onglet Livraison sur la plage donnée (cache 5 min).
	 *
	 * Périmètre = commandes EXPÉDIÉES (méta `_aod_ship_tracking` non vide)
	 * créées dans la période, quel que soit leur statut Woo (une commande
	 * annulée côté boutique mais expédiée reste un colis à suivre). Filtres
	 * d'écran produit/confirmateur (jointures 2.2) + périmètre produits du
	 * confirmateur connecté — le périmètre fait partie de la clé de cache
	 * (deux utilisateurs à périmètres différents ne partagent jamais une
	 * entrée). Les libellés (transporteur, wilaya, statut) sont résolus au
	 * rendu, pas ici : le cache resterait sinon figé dans une langue.
	 *
	 * @param array $range   Sortie de resolve_range().
	 * @param array $filters Sortie de read_filters().
	 * @return array Données brutes prêtes à afficher.
	 */
	protected function delivery_data( $range, $filters ) {
		$fpid     = absint( $filters['produit'] );
		$fcid     = absint( $filters['confirmateur'] );
		$scope    = $this->user_scope();
		$assigned = $this->assignment_scope();
		return $this->cached(
			array( 'livraison', $range['from'], $range['to'], $fpid, $fcid, $scope, $assigned ),
			function () use ( $range, $fpid, $fcid, $scope, $assigned ) {

				$terminal = AOD_Carrier::tracking_terminal_statuses();
				$groups   = array(
					AOD_Carrier::ST_DELIVERED  => 0,
					AOD_Carrier::ST_IN_TRANSIT => 0,
					AOD_Carrier::ST_RETURNED   => 0,
					AOD_Carrier::ST_FAILED     => 0,
					AOD_Carrier::ST_CANCELLED  => 0,
				);
				$carriers  = array(); // cid => total + compteur par groupe.
				$wilayas   = array(); // clé => code/état brut + total + par groupe.
				$delay_sum = 0;
				$delay_n   = 0;
				$blocked   = array();
				$now       = time();

				// Parcours PAR LOTS (31.6) — mesuré à 10 000 commandes : 28 Mo de pic
				// quand toutes les expéditions tenaient en mémoire simultanément.
				$total_expediees = $this->each_filtered_order( array(
					'type'         => 'shop_order',
					'status'       => array_keys( wc_get_order_statuses() ),
					'date_created' => $range['from'] . '...' . $range['to'],
				), function ( $o ) use ( $terminal, $now, &$groups, &$carriers, &$wilayas, &$delay_sum, &$delay_n, &$blocked ) {
					$st  = (string) $o->get_meta( AOD_Carrier::META_STATUS );
					$grp = $this->delivery_group( $st );
					$groups[ $grp ]++;

					// Ventilation par transporteur (libellé résolu au rendu).
					$cid = (string) $o->get_meta( AOD_Carrier::META_CARRIER );
					if ( ! isset( $carriers[ $cid ] ) ) {
						$carriers[ $cid ] = array_fill_keys( array_keys( $groups ), 0 ) + array( 'total' => 0 );
					}
					$carriers[ $cid ]['total']++;
					$carriers[ $cid ][ $grp ]++;

					// Ventilation par wilaya : code du formulaire COD, repli
					// billing_state brut (commande créée autrement).
					$wcode = (int) $o->get_meta( '_aod_wilaya_code' );
					$state = (string) $o->get_billing_state();
					$wkey  = $wcode ? 'w' . $wcode : ( '' !== $state ? 's' . $state : '' );
					if ( ! isset( $wilayas[ $wkey ] ) ) {
						$wilayas[ $wkey ] = array_fill_keys( array_keys( $groups ), 0 ) + array(
							'total' => 0,
							'code'  => $wcode,
							'state' => $state,
						);
					}
					$wilayas[ $wkey ]['total']++;
					$wilayas[ $wkey ][ $grp ]++;

					// Historique JSON : délai (livrées) / dernier événement (bloquées).
					$hist = json_decode( (string) $o->get_meta( AOD_Carrier::META_STATUS_HISTORY ), true );
					$hist = is_array( $hist ) ? $hist : array();

					if ( AOD_Carrier::ST_DELIVERED === $grp ) {
						// Délai = événement « livré » − plus ancien événement ; les
						// ts à 0 (transporteur sans date) rendent le colis non mesurable.
						$first = 0;
						$deliv = 0;
						foreach ( $hist as $ev ) {
							$ts = isset( $ev['ts'] ) ? (int) $ev['ts'] : 0;
							if ( $ts <= 0 ) {
								continue;
							}
							if ( ! $first || $ts < $first ) {
								$first = $ts;
							}
							if ( isset( $ev['status'] ) && AOD_Carrier::ST_DELIVERED === $ev['status'] && $ts > $deliv ) {
								$deliv = $ts;
							}
						}
						if ( $first && $deliv && $deliv >= $first ) {
							$delay_sum += $deliv - $first;
							$delay_n++;
						}
					} elseif ( ! in_array( $st, $terminal, true ) ) {
						// Colis non terminal : bloqué si aucun événement récent.
						$last = 0;
						foreach ( $hist as $ev ) {
							$ts = isset( $ev['ts'] ) ? (int) $ev['ts'] : 0;
							if ( $ts > $last ) {
								$last = $ts;
							}
						}
						if ( ! $last ) {
							$last = (int) $o->get_meta( AOD_Carrier::META_STATUS_AT );
						}
						if ( ! $last ) {
							$dc   = $o->get_date_created();
							$last = $dc ? $dc->getTimestamp() : $now;
						}
						if ( $now - $last > self::BLOCKED_DAYS * DAY_IN_SECONDS ) {
							$blocked[] = array(
								'id'      => $o->get_id(),
								'number'  => $o->get_order_number(),
								'carrier' => $cid,
								'status'  => $st,
								'last'    => $last,
							);
						}
					}
				}, $fpid, $fcid, true, $scope, $assigned );

				// Volumes décroissants (wilayas : le rendu tronque au top 10) ;
				// bloquées : les plus anciennes d'abord.
				$by_total = function ( $a, $b ) {
					return $b['total'] <=> $a['total'];
				};
				uasort( $carriers, $by_total );
				uasort( $wilayas, $by_total );
				usort( $blocked, function ( $a, $b ) {
					return $a['last'] <=> $b['last'];
				} );

				return array(
					'total'     => $total_expediees,
					'groups'    => $groups,
					'carriers'  => $carriers,
					'wilayas'   => $wilayas,
					'delay_avg' => $delay_n ? $delay_sum / $delay_n : 0,
					'delay_n'   => $delay_n,
					'blocked'   => $blocked,
				);
			},
			$range['from'],
			$range['to']
		);
	}

	/**
	 * Libellé d'un transporteur depuis son ID de config (repli : ID brut).
	 *
	 * @param string $cid ID du transporteur (méta `_aod_ship_carrier`).
	 * @return string
	 */
	protected function carrier_label( $cid ) {
		if ( '' === $cid ) {
			return '—';
		}
		if ( class_exists( 'AOD_Shipping' ) ) {
			$carrier = AOD_Shipping::instance()->carrier( $cid );
			if ( $carrier ) {
				return $carrier->label();
			}
		}
		return $cid;
	}

	/**
	 * Nom d'une wilaya d'après une ligne de ventilation (FR ou AR selon la
	 * langue courante), repli sur l'état de facturation brut puis « — ».
	 *
	 * @param array $row Ligne de delivery_data()['wilayas'].
	 * @return string
	 */
	protected function wilaya_label( $row ) {
		if ( $row['code'] && class_exists( 'AOD_COD_Data' ) ) {
			$name = AOD_COD_Data::label( AOD_COD_Data::wilaya_name( $row['code'] ), AOD_COD_Data::wilaya_name_ar( $row['code'] ) );
			if ( '' !== $name ) {
				return $name;
			}
		}
		if ( $row['code'] ) {
			return '#' . $row['code'];
		}
		return '' !== $row['state'] ? $row['state'] : '—';
	}

	/**
	 * Pastille d'un statut de colis normalisé (composant unifié — étape 5.4).
	 *
	 * Délègue à AOD_CD_Section_Tracking::tracking_pill() (extraite du Dashboard en
	 * 30.5) : une seule pastille sémantique pour l'Accueil, la page Suivi et la
	 * timeline (plus de recopie locale).
	 *
	 * @param string $status Clé ST_* (vide → inconnu).
	 * @return string HTML sûr.
	 */
	protected function delivery_pill( $status ) {
		return AOD_CD_Section_Tracking::tracking_pill( $status );
	}

	/**
	 * Ligne « N (taux %) » d'une carte ou cellule de ventilation.
	 *
	 * @param int $n     Compteur du groupe.
	 * @param int $total Base (expédiées du périmètre).
	 * @return string
	 */
	protected function delivery_rate( $n, $total ) {
		if ( $total <= 0 ) {
			return '0';
		}
		/* translators: 1 : nombre de colis, 2 : pourcentage des expédiées. */
		return sprintf( __( '%1$s (%2$s %%)', 'aod-client-dashboard' ), number_format_i18n( $n ), number_format_i18n( $n / $total * 100, 1 ) );
	}

	/**
	 * Onglet Livraison : cartes KPI, ventilations transporteur/wilaya, bloquées.
	 *
	 * @param array $range   Sortie de resolve_range().
	 * @param array $filters Sortie de read_filters().
	 */
	protected function tab_delivery( $range, $filters ) {
		if ( ! class_exists( 'AOD_Carrier' ) ) {
			echo AOD_CD_Dashboard::empty_state( 'shipping', __( 'Le module de livraison n’est pas actif.', 'aod-client-dashboard' ), __( 'Activez le plugin « AOD COD Form » pour suivre l’évolution des colis.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$d = $this->delivery_data( $range, $filters );

		$this->filter_context_line( $filters );

		if ( ! $d['total'] ) {
			echo AOD_CD_Dashboard::empty_state( 'products', __( 'Aucun colis expédié sur cette période (avec ces filtres).', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$g = $d['groups'];
		if ( $d['delay_n'] ) {
			/* translators: %s : nombre de jours (1 décimale). */
			$delay_val = sprintf( __( '%s jours', 'aod-client-dashboard' ), number_format_i18n( $d['delay_avg'] / DAY_IN_SECONDS, 1 ) );
		} else {
			$delay_val = '—';
		}

		// Période précédente (même durée) pour les deltas.
		$p    = $this->delivery_data( $this->previous_range( $range ), $filters );
		$pg   = $p['groups'];
		$rate = function ( $n, $tot ) {
			return $tot > 0 ? ( $n / $tot ) * 100 : 0.0;
		};

		// Chaque carte : libellé, valeur, icône, tonalité, delta.
		// Les taux (livrés / retournés / échec) se comparent en POINTS. « En
		// transit », « Colis annulés » et « Bloqués » sont des états instantanés :
		// pas de delta. Retours, échecs et délai sont « moins c'est mieux » → une
		// hausse doit s'afficher en rouge (cf. kpi_delta()).
		$cards = array(
			array( __( 'Colis expédiés', 'aod-client-dashboard' ), number_format_i18n( $d['total'] ), 'shipping', 'blue', $this->kpi_delta( $d['total'], $p['total'] ) ),
			array( __( 'Livrés', 'aod-client-dashboard' ), $this->delivery_rate( $g[ AOD_Carrier::ST_DELIVERED ], $d['total'] ), 'check', 'green', $this->kpi_delta( $rate( $g[ AOD_Carrier::ST_DELIVERED ], $d['total'] ), $rate( $pg[ AOD_Carrier::ST_DELIVERED ], $p['total'] ), true, true ) ),
			array( __( 'En transit', 'aod-client-dashboard' ), $this->delivery_rate( $g[ AOD_Carrier::ST_IN_TRANSIT ], $d['total'] ), 'transit', 'indigo' ),
			array( __( 'Retournés', 'aod-client-dashboard' ), $this->delivery_rate( $g[ AOD_Carrier::ST_RETURNED ], $d['total'] ), 'returned', 'red', $this->kpi_delta( $rate( $g[ AOD_Carrier::ST_RETURNED ], $d['total'] ), $rate( $pg[ AOD_Carrier::ST_RETURNED ], $p['total'] ), false, true ) ),
			array( __( 'Échec de livraison', 'aod-client-dashboard' ), $this->delivery_rate( $g[ AOD_Carrier::ST_FAILED ], $d['total'] ), 'warning', 'amber', $this->kpi_delta( $rate( $g[ AOD_Carrier::ST_FAILED ], $d['total'] ), $rate( $pg[ AOD_Carrier::ST_FAILED ], $p['total'] ), false, true ) ),
			array( __( 'Colis annulés', 'aod-client-dashboard' ), number_format_i18n( $g[ AOD_Carrier::ST_CANCELLED ] ), 'cancel', 'slate' ),
			array( __( 'Délai moyen de livraison', 'aod-client-dashboard' ), $delay_val, 'delay', 'teal', ( $d['delay_n'] && $p['delay_n'] ) ? $this->kpi_delta( $d['delay_avg'], $p['delay_avg'], false ) : '' ),
			/* translators: %d : nombre de jours sans événement. */
			array( sprintf( __( 'Bloqués (> %d jours)', 'aod-client-dashboard' ), self::BLOCKED_DAYS ), number_format_i18n( count( $d['blocked'] ) ), 'blocked', 'amber' ),
		);

		$this->render_kpis( $cards );

		// Ventilation par transporteur.
		$cols = array(
			__( 'Expédiés', 'aod-client-dashboard' ),
			__( 'Livrés', 'aod-client-dashboard' ),
			__( 'Taux livré', 'aod-client-dashboard' ),
			__( 'En transit', 'aod-client-dashboard' ),
			__( 'Retournés', 'aod-client-dashboard' ),
			__( 'Échec', 'aod-client-dashboard' ),
		);
		/* 46.2 — `$cols` est CAPTURÉ par la fermeture (`use`) pour que les
		   `data-label` des cartes mobiles viennent des mêmes intitulés que les
		   en-têtes. Les deux ventilations (transporteur, wilaya) partagent cette
		   fermeture : l'écrire une fois, c'est aussi garantir qu'elles ne
		   divergeront pas l'une de l'autre. */
		$row_cells = function ( $row ) use ( $cols ) {
			$rate = $row['total'] > 0 ? number_format_i18n( $row[ AOD_Carrier::ST_DELIVERED ] / $row['total'] * 100, 1 ) . ' %' : '—';
			/* Les valeurs restent BRUTES ici et sont échappées au POINT DE SORTIE.
			   C'est ce qui évite une dérogation d'échappement de plus :
			   `test-phpcs-ignore.php` tient un census de cette famille de sécurité
			   et exige qu'une hausse soit justifiée. Un compteur de sécurité qu'on
			   gonfle pour du chrome perd son sens d'alerte — mieux vaut ne pas avoir
			   besoin de l'exception que de la justifier.
			   ATTENTION : ne PAS écrire le nom littéral de cette annotation dans un
			   commentaire. Ce census lit le source BRUT, donc il compterait la
			   mention comme une annotation réelle (constaté ici même : +1 sur deux
			   compteurs distincts). Même piège que celui documenté dans
			   `test-charte-surfaces.php`, pris par l'autre bout. */
			$vals = array(
				(string) (int) $row['total'],
				(string) (int) $row[ AOD_Carrier::ST_DELIVERED ],
				$rate,
				(string) (int) $row[ AOD_Carrier::ST_IN_TRANSIT ],
				(string) (int) $row[ AOD_Carrier::ST_RETURNED ],
				(string) (int) $row[ AOD_Carrier::ST_FAILED ],
			);
			foreach ( $vals as $i => $v ) {
				echo '<td data-label="' . esc_attr( isset( $cols[ $i ] ) ? $cols[ $i ] : '' ) . '">' . esc_html( $v ) . '</td>';
			}
		};

		echo '<h2 class="aod-cd-form-subtitle aod-cd-blocktitle">' . esc_html__( 'Par transporteur', 'aod-client-dashboard' ) . '</h2>';
		echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards"><thead><tr><th>' . esc_html__( 'Transporteur', 'aod-client-dashboard' ) . '</th>';
		foreach ( $cols as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $d['carriers'] as $cid => $row ) {
			echo '<tr><td data-label="' . esc_attr__( 'Transporteur', 'aod-client-dashboard' ) . '">' . esc_html( $this->carrier_label( (string) $cid ) ) . '</td>';
			$row_cells( $row );
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		// Ventilation par wilaya : top 10, le reste agrégé.
		echo '<h2 class="aod-cd-form-subtitle aod-cd-blocktitle">' . esc_html__( 'Par wilaya', 'aod-client-dashboard' ) . '</h2>';
		echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards"><thead><tr><th>' . esc_html__( 'Wilaya', 'aod-client-dashboard' ) . '</th>';
		foreach ( $cols as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		$rest = null;
		$i    = 0;
		foreach ( $d['wilayas'] as $row ) {
			$i++;
			if ( $i <= 10 ) {
				echo '<tr><td data-label="' . esc_attr__( 'Wilaya', 'aod-client-dashboard' ) . '">' . esc_html( $this->wilaya_label( $row ) ) . '</td>';
				$row_cells( $row );
				echo '</tr>';
				continue;
			}
			if ( null === $rest ) {
				$rest = array_fill_keys( array_keys( $d['groups'] ), 0 ) + array( 'total' => 0, 'n' => 0 );
			}
			foreach ( array_keys( $d['groups'] ) as $k ) {
				$rest[ $k ] += $row[ $k ];
			}
			$rest['total'] += $row['total'];
			$rest['n']++;
		}
		if ( $rest ) {
			/* translators: %d : nombre de wilayas agrégées hors top 10. */
			echo '<tr><td data-label="' . esc_attr__( 'Wilaya', 'aod-client-dashboard' ) . '">' . esc_html( sprintf( __( 'Autres wilayas (%d)', 'aod-client-dashboard' ), $rest['n'] ) ) . '</td>';
			$row_cells( $rest );
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		// Colis bloqués (non terminaux sans événement récent), plus anciens d'abord.
		if ( $d['blocked'] ) {
			/* translators: %d : nombre de jours sans événement. */
			echo '<h2 class="aod-cd-form-subtitle aod-cd-blocktitle">' . esc_html( sprintf( __( 'Colis bloqués (sans événement depuis plus de %d jours)', 'aod-client-dashboard' ), self::BLOCKED_DAYS ) ) . '</h2>';
			$cols_bl = array(
				__( 'N°', 'aod-client-dashboard' ),
				__( 'Transporteur', 'aod-client-dashboard' ),
				__( 'État du colis', 'aod-client-dashboard' ),
				__( 'Dernier événement', 'aod-client-dashboard' ),
			);
			echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards"><thead><tr>';
			foreach ( $cols_bl as $h ) {
				echo '<th>' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( array_slice( $d['blocked'], 0, 20 ) as $b ) {
				/* translators: %s : durée écoulée (ex. « 3 heures »). */
				$ago = AOD_CD_Temps::depuis( $b['last'] );
				echo '<tr><td data-label="' . esc_attr( $cols_bl[0] ) . '"><strong>#' . esc_html( $b['number'] ) . '</strong></td>'
					. '<td data-label="' . esc_attr( $cols_bl[1] ) . '">' . esc_html( $this->carrier_label( (string) $b['carrier'] ) ) . '</td>'
					. '<td data-label="' . esc_attr( $cols_bl[2] ) . '">' . $this->delivery_pill( $b['status'] ) . '</td>' // phpcs:ignore WordPress.Security.EscapeOutput -- markup échappé dans la méthode.
					. '<td data-label="' . esc_attr( $cols_bl[3] ) . '">' . esc_html( $ago ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
			if ( count( $d['blocked'] ) > 20 ) {
				/* translators: %d : nombre total de colis bloqués. */
				echo '<p class="aod-cd-note">' . esc_html( sprintf( __( 'Seuls les 20 colis les plus anciens sont listés (%d bloqués au total). Le détail complet est dans « Suivi commande ».', 'aod-client-dashboard' ), count( $d['blocked'] ) ) ) . '</p>';
			}
		}

		echo '<p class="aod-cd-note">' . esc_html__( 'Base des taux : commandes expédiées (colis créé chez un transporteur) dont la date de création tombe dans la période, quel que soit leur statut de commande. Un colis sans suivi synchronisé compte « En transit ».', 'aod-client-dashboard' ) . '</p>';
		if ( $d['delay_n'] ) {
			/* translators: %s : nombre de livraisons mesurables. */
			echo '<p class="aod-cd-note">' . esc_html( sprintf( __( 'Délai moyen calculé sur %s livraison(s) dont l’historique transporteur est horodaté (du premier événement du colis à sa livraison).', 'aod-client-dashboard' ), number_format_i18n( $d['delay_n'] ) ) ) . '</p>';
		} else {
			echo '<p class="aod-cd-note">' . esc_html__( 'Délai moyen indisponible : aucune livraison de la période n’a d’historique transporteur horodaté.', 'aod-client-dashboard' ) . '</p>';
		}
		if ( $filters['produit'] ) {
			echo '<p class="aod-cd-note">' . esc_html__( 'Filtre produit actif : les taux portent sur les commandes contenant ce produit (un colis est livré ou non en entier — pas de découpage par ligne).', 'aod-client-dashboard' ) . '</p>';
		}
	}

	/* ============================================================
	 * Onglet Confirmation (étape 2.4 — traçabilité _aod_confirmed_*)
	 * ========================================================== */

	/**
	 * Agrégats de l'onglet Confirmation sur la plage donnée (cache 5 min).
	 *
	 * Périmètre = commandes créées dans la période, tous statuts. « Tracée » =
	 * `_aod_confirmed_by` > 0 (posée avec `_aod_confirmed_at` au premier
	 * passage vers Confirmée, étape 1.1) ; « non attribuée » = statut actuel
	 * Confirmée/Terminée SANS traçabilité (commande d'avant la Phase 1).
	 * Le filtre d'écran « confirmateur » n'est PAS appliqué ici : les données
	 * contiennent toutes les lignes, la vue (rôle, filtre) se décide au rendu —
	 * la clé de cache n'intègre donc que plage/produit/périmètre produits.
	 *
	 * @param array $range   Sortie de resolve_range().
	 * @param array $filters Sortie de read_filters().
	 * @return array Données brutes prêtes à afficher.
	 */
	protected function confirmation_data( $range, $filters ) {
		$fpid  = absint( $filters['produit'] );
		$scope = $this->user_scope();
		return $this->cached(
			// 🚨 « confirmation2 » ET NON « confirmation » (48.2). La forme du
			// payload change (clé `assigned` nouvelle) : sous l'ancien nom, une
			// entrée encore en cache — TTL 5 min — serait servie SANS cette clé, et
			// la carte lirait 0 pour un confirmateur qui a bien des commandes. Un
			// `?? array()` défensif suffirait à ne pas casser, mais il afficherait
			// un chiffre FAUX pendant cinq minutes. Changer le nom de clé invalide
			// l'ancien cache STRUCTURELLEMENT, sans dépendre d'un bump d'option.
			array( 'confirmation2', $range['from'], $range['to'], $fpid, $scope ),
			function () use ( $range, $fpid, $scope ) {
				$rows       = array(); // uid => [ n, delay_sum, delay_n ].
				$assigned   = array(); // uid => n commandes ASSIGNÉES (48.2).
				$unassigned = 0;

				// Parcours PAR LOTS (31.6) — mesuré à 10 000 commandes : 86 Mo de
				// pic quand tout tenait en mémoire.
				$total_commandes = $this->each_filtered_order( array(
					'type'         => 'shop_order',
					'status'       => array_keys( wc_get_order_statuses() ),
					'date_created' => $range['from'] . '...' . $range['to'],
				), function ( $o ) use ( &$rows, &$assigned, &$unassigned ) {
					// 🚨 AVANT TOUT `return` DE CETTE CLOSURE, et ce n'est pas un
					// détail de placement (48.2). La branche `! $by` ci-dessous sort
					// tôt : une commande assignée mais PAS ENCORE confirmée — c'est
					// exactement celles qu'un confirmateur a en attente, donc le cas
					// le plus courant du chiffre qu'on affiche — n'aurait jamais été
					// comptée. La carte aurait lu 0 pour quelqu'un qui a douze
					// commandes sur les bras : le défaut de §A.24 réinstallé dans la
					// carte censée le corriger.
					//
					// ⚠️ `0` / méta absente = NON assignée (invariant Phase 42) : on
					// ne crée pas d'entrée `0`, qui se relirait ensuite comme
					// « assignées à personne ». Le gérant a déjà « Non attribuées »,
					// qui compte un TOUT AUTRE concept (une commande confirmée sans
					// `_aod_confirmed_by`) — les confondre ferait dire à l'écran
					// l'inverse du vrai.
					$aid = (int) $o->get_meta( '_aod_assigned_confirmer' );
					if ( $aid > 0 ) {
						$assigned[ $aid ] = ( isset( $assigned[ $aid ] ) ? $assigned[ $aid ] : 0 ) + 1;
					}

					$by = (int) $o->get_meta( '_aod_confirmed_by' );
					if ( ! $by ) {
						// Sans traçabilité : confirmée quand même si son statut
						// actuel est en aval de la confirmation (avant Phase 1).
						if ( in_array( $o->get_status(), array( 'aod-confirmed', 'completed' ), true ) ) {
							$unassigned++;
						}
						return; // closure : `continue` n’existe pas ici (31.6).
					}
					if ( ! isset( $rows[ $by ] ) ) {
						$rows[ $by ] = array( 'n' => 0, 'delay_sum' => 0, 'delay_n' => 0 );
					}
					$rows[ $by ]['n']++;

					// Délai création → confirmation. `_aod_confirmed_at` est en
					// heure LOCALE du site (current_time 'mysql') : conversion en
					// epoch UTC via get_gmt_from_date, jamais strtotime seul.
					$at = (string) $o->get_meta( '_aod_confirmed_at' );
					$dc = $o->get_date_created();
					if ( '' === $at || ! $dc ) {
						return; // closure : `continue` n’existe pas ici (31.6).
					}
					$ts = get_gmt_from_date( $at, 'U' );
					if ( ! $ts ) {
						return; // closure : `continue` n’existe pas ici (31.6).
					}
					$delta = (int) $ts - $dc->getTimestamp();
					if ( $delta >= 0 ) { // Négatif = donnée aberrante, exclue.
						$rows[ $by ]['delay_sum'] += $delta;
						$rows[ $by ]['delay_n']++;
					}
				}, $fpid, 0, false, $scope );

				uasort( $rows, function ( $a, $b ) {
					return $b['n'] <=> $a['n'];
				} );

				$traced    = array_sum( wp_list_pluck( $rows, 'n' ) );
				$delay_sum = array_sum( wp_list_pluck( $rows, 'delay_sum' ) );
				$delay_n   = array_sum( wp_list_pluck( $rows, 'delay_n' ) );

				return array(
					'total'      => $total_commandes,
					'rows'       => $rows,
					// Ventilation par confirmateur ASSIGNÉ (48.2) — un agrégat
					// complet, INDÉPENDANT DU LECTEUR, comme `rows`. La clé de
					// cache n'intègre ni le rôle ni l'utilisateur courant (à
					// dessein) : y stocker un total « le mien » exigerait une clé
					// par utilisateur, et le jour où on l'oublierait deux
					// confirmateurs se serviraient la vue l'un de l'autre. La
					// visibilité se décide AU RENDU, jamais dans la donnée.
					'assigned'   => $assigned,
					'unassigned' => $unassigned,
					'confirmed'  => $traced + $unassigned,
					'delay_sum'  => $delay_sum,
					'delay_n'    => $delay_n,
				);
			},
			$range['from'],
			$range['to']
		);
	}

	/**
	 * Formate un délai moyen : heures sous 48 h, jours au-delà, « — » sans base.
	 *
	 * @param int $sum Somme des délais (secondes).
	 * @param int $n   Nombre de délais mesurés.
	 * @return string
	 */
	protected function delay_fmt( $sum, $n ) {
		if ( $n <= 0 ) {
			return '—';
		}
		$avg = $sum / $n;
		if ( $avg < 2 * DAY_IN_SECONDS ) {
			/* translators: %s : nombre d'heures (1 décimale). */
			return sprintf( __( '%s h', 'aod-client-dashboard' ), number_format_i18n( $avg / HOUR_IN_SECONDS, 1 ) );
		}
		/* translators: %s : nombre de jours (1 décimale). */
		return sprintf( __( '%s jours', 'aod-client-dashboard' ), number_format_i18n( $avg / DAY_IN_SECONDS, 1 ) );
	}

	/**
	 * Ligne d'un confirmateur dans les agrégats Confirmation — jamais absente.
	 *
	 * Le motif `isset( $d['rows'][ $uid ] ) ? … : ligne vide` était écrit TROIS
	 * fois dans `tab_confirmation()` (cartes, période précédente, tableau) ; la
	 * 47.5 en aurait ajouté un quatrième. Un seul corps : trois copies ne
	 * peuvent plus diverger sur ce qu'est « aucune confirmation » (0, pas de
	 * notice PHP, pas de `null` qui casserait une division).
	 *
	 * @param array $data Sortie de confirmation_data().
	 * @param int   $uid  Confirmateur — 0 rend la ligne vide.
	 * @return array{n:int,delay_sum:int,delay_n:int}
	 */
	protected static function conf_row( $data, $uid ) {
		$uid = absint( $uid );
		return ( $uid && isset( $data['rows'][ $uid ] ) )
			? $data['rows'][ $uid ]
			: array( 'n' => 0, 'delay_sum' => 0, 'delay_n' => 0 );
	}

	/**
	 * Onglet Confirmation : cartes KPI + ventilation par confirmateur.
	 *
	 * Visibilité décidée ici (les données en cache sont complètes) : le gérant
	 * voit toutes les lignes + « Non attribuées » ; un confirmateur ne voit que
	 * SA ligne (jamais celles des autres). Le filtre d'écran « confirmateur »
	 * (gérant) recalcule les cartes Confirmées/Délai depuis la ligne de X — le
	 * taux reste rapporté aux Reçues globales (un taux « 100 % » mécanique
	 * serait trompeur).
	 *
	 * 🚨 CE QUI ÉTAIT FAUX (47.5). Cette restriction n'existait QUE pour le
	 * tableau. Les trois cartes, elles, retombaient dans la branche « globale »
	 * dès qu'aucun filtre d'écran n'était posé — or `read_filters()` force ce
	 * filtre à 0 pour un non-gérant, et `visible_tabs()` ouvre pourtant l'onglet
	 * à quiconque porte `aod_cap_stats_confirm`. Un confirmateur lisait donc
	 * TOUJOURS la performance de toute la boutique, à trois lignes d'un code qui
	 * faisait déjà le bon geste juste en dessous. Depuis, `$row_uid` est calculé
	 * UNE fois et gouverne les cartes, la période précédente ET le tableau.
	 *
	 * ⚠️ Le critère est `! manage_woocommerce`, PAS une capability. Ne pas
	 * recopier la garde d'`assignment_scope()` (`aod_cap_confirm_orders`) : elle
	 * protège des tuiles qui se videraient à tort pour un confirmateur
	 * « suivi/stock seul ». Ici c'est l'inverse — un confirmateur qui n'a jamais
	 * confirmé doit lire **0**, ce qui est littéralement sa performance.
	 *
	 * @param array $range   Sortie de resolve_range().
	 * @param array $filters Sortie de read_filters().
	 */
	protected function tab_confirmation( $range, $filters ) {
		$d = $this->confirmation_data( $range, $filters );

		$this->filter_context_line( $filters );

		if ( ! $d['total'] ) {
			echo AOD_CD_Dashboard::empty_state( 'list', __( 'Aucune commande reçue sur cette période (avec ces filtres).', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$is_manager = current_user_can( 'manage_woocommerce' );
		$fcid       = absint( $filters['confirmateur'] ); // Déjà 0 pour un non-gérant.

		// DE QUI lit-on la ligne ? Le confirmateur choisi à l'écran (gérant), ou
		// l'utilisateur courant s'il n'est pas gérant, ou 0 = toute la boutique.
		// Calculé UNE fois : les cartes, la période précédente et le tableau
		// doivent parler du même périmètre, sans quoi le delta comparerait deux
		// choses différentes et « Part » se lirait de travers.
		$row_uid = $fcid ? $fcid : ( $is_manager ? 0 : get_current_user_id() );

		// Chiffres des cartes : globaux, ou ceux de la ligne retenue.
		if ( $row_uid ) {
			$row       = self::conf_row( $d, $row_uid );
			$conf_n    = $row['n'];
			$delay_sum = $row['delay_sum'];
			$delay_n   = $row['delay_n'];
		} else {
			$conf_n    = $d['confirmed'];
			$delay_sum = $d['delay_sum'];
			$delay_n   = $d['delay_n'];
		}

		// Période précédente (même durée), lue avec LE MÊME $row_uid que les
		// chiffres courants — sinon on comparerait des choses différentes.
		$p = $this->confirmation_data( $this->previous_range( $range ), $filters );
		if ( $row_uid ) {
			$p_row  = self::conf_row( $p, $row_uid );
			$p_conf = $p_row['n'];
			$p_dsum = $p_row['delay_sum'];
			$p_dn   = $p_row['delay_n'];
		} else {
			$p_conf = $p['confirmed'];
			$p_dsum = $p['delay_sum'];
			$p_dn   = $p['delay_n'];
		}
		$rate_now  = $d['total'] > 0 ? ( $conf_n / $d['total'] ) * 100 : 0.0;
		$rate_prev = $p['total'] > 0 ? ( $p_conf / $p['total'] ) * 100 : 0.0;

		// 🚨 LA CARTE DIT SON PÉRIMÈTRE AU SEUL LECTEUR QUI PEUT S'Y TROMPER (48.1).
		// « Commandes reçues » est le SEUL chiffre de tout le /gestion d'un
		// confirmateur qui parle de la BOUTIQUE : sa liste Commandes, son Suivi,
		// ses Abandonnées et les onglets Revenus/Livraison sont tous restreints à
		// son assignation, côté SQL. Posé au milieu de ça, « 33 » ne se lit pas
		// comme un total de boutique — il se lit comme une contradiction avec les
		// 12 commandes qu'on lui a confiées (constat owner du 2026-08-02, §A.24).
		//
		// ⚠️ Le gérant garde EXACTEMENT sa chaîne : c'est la non-régression n°1 de
		// la phase, et c'est aussi ce qui empêche la précision de devenir du bruit
		// là où le périmètre boutique est le cas nominal.
		//
		// ⚠️ UN SEUL SITE DE DÉCISION. Deux libellés, oui — mais choisis ici, en un
		// point, comme `$row_uid` gouverne déjà les trois cartes. Les disperser
		// serait la dérive que la Phase 46 a passé huit étapes à refermer.
		$lbl_recues = $is_manager
			? __( 'Commandes reçues', 'aod-client-dashboard' )
			: __( 'Commandes reçues (boutique)', 'aod-client-dashboard' );

		// Chaque carte : libellé, valeur, icône, tonalité, delta. Le délai de
		// confirmation est « moins c'est mieux ». « Non attribuées » est un
		// indicateur de qualité de donnée, pas une performance : pas de delta.
		$cards = array(
			array( $lbl_recues, number_format_i18n( $d['total'] ), 'inbox', 'blue', $this->kpi_delta( $d['total'], $p['total'] ) ),
			array( __( 'Confirmées', 'aod-client-dashboard' ), $this->delivery_rate( $conf_n, $d['total'] ), 'check', 'green', $this->kpi_delta( $rate_now, $rate_prev, true, true ) ),
			array( __( 'Délai moyen de confirmation', 'aod-client-dashboard' ), $this->delay_fmt( $delay_sum, $delay_n ), 'delay', 'teal', ( $delay_n && $p_dn ) ? $this->kpi_delta( $delay_sum / $delay_n, $p_dsum / $p_dn, false ) : '' ),
		);
		if ( $is_manager ) {
			$cards[] = array( __( 'Confirmées non attribuées', 'aod-client-dashboard' ), number_format_i18n( $d['unassigned'] ), 'question', 'slate' );
		}

		// 🚨 LA CARTE QUI RÉPOND À LA QUESTION RÉELLEMENT POSÉE (48.2). « J'ai reçu
		// 12 commandes, l'écran en affiche 33 » : les deux chiffres étaient exacts,
		// et aucun des deux ne disait « voilà les vôtres ». Celui-ci le dit.
		//
		// ⚠️ ELLE SUIT `$row_uid`, PAS `! $is_manager` — décision D-2, et c'est le
		// cœur de l'étape. `$row_uid` est le critère UNIQUE que la 47.5 a institué
		// pour que cartes, période précédente et tableau ne puissent plus parler de
		// périmètres différents. Lui adjoindre un quatrième critère sur le MÊME
		// écran rouvrirait précisément la divergence qu'elle a fermée. Effet voulu :
		// un gérant qui filtre `?confirmateur=X` lit lui aussi ce qui est assigné à
		// X — il en a le même besoin, et le libellé reste vrai dans les deux cas
		// (une seconde chaîne « assignées à MOI » aurait été une chaîne de plus à
		// traduire et une occasion de plus de diverger).
		//
		// ⚠️ UN COMPTEUR, JAMAIS UN RATIO. Aucun pourcentage n'est bâti dessus, et
		// c'est précisément ce qui le rend sûr : `_aod_assigned_confirmer` est un
		// état PRÉSENT (il bouge à chaque réassignation) tandis que
		// `_aod_confirmed_by` est FIGÉ. Les mettre en fraction l'un de l'autre
		// donnerait « confirmées 1 / assignées 0 » — division par zéro — à tout
		// confirmateur dont une commande a été réassignée après coup. C'est la
		// raison pour laquelle « Reçues » et « Part » gardent, eux, le dénominateur
		// boutique (D-4, §A.24 Constat 3).
		//
		// Pas de delta : comparer un état présent à celui d'une période close ne
		// mesure pas une performance, seulement l'historique des réassignations.
		if ( $row_uid ) {
			$assigned_n = isset( $d['assigned'][ $row_uid ] ) ? (int) $d['assigned'][ $row_uid ] : 0;
			// ⚠️ `indigo`, PAS une teinte neuve. Les tonalités sont un catalogue
			// FERMÉ (`.t-*` de dashboard.css) : une tonalité non déclarée ne lève
			// aucune erreur — le repli prend le relais et la carte sort en neutre,
			// sans que rien ne le dise. C'est le « fantôme » de la Phase 17, et
			// `test-charte-surfaces.php` le refuse. `user` est de même un nom du
			// catalogue fermé `AOD_CD_Icon` (un nom inconnu ne rend RIEN).
			$carte_assignees = array( __( 'Commandes assignées', 'aod-client-dashboard' ), number_format_i18n( $assigned_n ), 'user', 'indigo' );

			// 🚨 EN TÊTE, PAS EN QUEUE (48.4) — ET C'EST LE CŒUR DE LA CORRECTION.
			// Livrée en 4ᵉ position, cette carte était exacte et INUTILE : le premier
			// nombre qu'un confirmateur lisait restait celui de la boutique, sous un
			// « Bonjour <prénom> ». Ajouter la bonne information ne corrige rien tant
			// qu'elle arrive après celle qui trompe — *l'ordre de lecture fait partie
			// du message*. Retour de l'owner, le 2026-08-02 : « rien n'est corrigé ».
			// Il avait raison, et rien dans le raisonnement de D-4 (garder le
			// dénominateur boutique pour le TAUX) n'imposait de la mettre en dernier.
			//
			// ⚠️ Aucun calcul ne bouge : « Reçues », « Confirmées », « Part » et le
			// taux gardent exactement leurs valeurs et leurs dénominateurs. C'est un
			// ordre d'affichage, et il ne s'applique qu'au lecteur restreint.
			array_unshift( $cards, $carte_assignees );
		}

		$this->render_kpis( $cards );

		// 🚨 SANS CE MOT, LE CHIFFRE JUSTE SE LIT DE TRAVERS. Le confirmateur voit
		// SES confirmations — mais le pourcentage les rapporte aux commandes reçues
		// par la BOUTIQUE, pas aux siennes. « Confirmées : 3 (9 %) » se lirait
		// sinon « 91 % de mes commandes m'ont échappé ».
		// Et ce dénominateur est global à dessein : « reçues par moi » serait un
		// numérateur figé (`_aod_confirmed_by` ne bouge jamais) sur un dénominateur
		// mobile (`_aod_assigned_confirmer` bouge à chaque réassignation) — une
		// commande confirmée puis réassignée donnerait « confirmées 1 / reçues 0 »,
		// donc une division par zéro.
		//
		// ⚠️ ELLE EST ICI, ET PLUS SOUS LE TABLEAU (48.1). Le texte n'a pas changé —
		// sa PLACE, si. Rendue en fin de page, elle arrivait plusieurs écrans après
		// la carte qui provoque le malentendu, et hors du premier écran sur mobile,
		// c'est-à-dire sur le poste de travail réel d'un confirmateur. Une note
		// exacte qu'on lit après s'être trompé n'a rien évité.
		if ( ! $is_manager ) {
			echo '<p class="aod-cd-note aod-cd-note-scope-conf">' . esc_html__( 'Vos chiffres : « Confirmées » et « Délai moyen » ne comptent que VOS confirmations. Le pourcentage, lui, les rapporte aux commandes reçues par la boutique sur la période — pas seulement aux vôtres.', 'aod-client-dashboard' ) . '</p>';
		}

		// Lignes visibles : tout le monde (gérant sans filtre), ou la seule ligne
		// retenue — $row_uid porte déjà les deux cas (filtre d'écran, ou
		// confirmateur connecté) : le tableau ne peut plus diverger des cartes.
		$rows = $row_uid ? array( $row_uid => self::conf_row( $d, $row_uid ) ) : $d['rows'];

		echo '<h2 class="aod-cd-form-subtitle aod-cd-blocktitle">' . esc_html__( 'Par confirmateur', 'aod-client-dashboard' ) . '</h2>';
		$cols_cf = array(
			__( 'Confirmateur', 'aod-client-dashboard' ),
			__( 'Confirmées', 'aod-client-dashboard' ),
			__( 'Part', 'aod-client-dashboard' ),
			__( 'Délai moyen', 'aod-client-dashboard' ),
		);
		echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards"><thead><tr>';
		foreach ( $cols_cf as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $uid => $row ) {
			$user = get_userdata( $uid );
			$name = $user ? $user->display_name : '#' . $uid; // Compte supprimé.
			$part = $d['confirmed'] > 0 ? number_format_i18n( $row['n'] / $d['confirmed'] * 100, 1 ) . ' %' : '—';
			echo '<tr><td data-label="' . esc_attr( $cols_cf[0] ) . '">' . esc_html( $name ) . '</td>'
				. '<td data-label="' . esc_attr( $cols_cf[1] ) . '">' . (int) $row['n'] . '</td>'
				. '<td data-label="' . esc_attr( $cols_cf[2] ) . '">' . esc_html( $part ) . '</td>'
				. '<td data-label="' . esc_attr( $cols_cf[3] ) . '">' . esc_html( $this->delay_fmt( $row['delay_sum'], $row['delay_n'] ) ) . '</td></tr>';
		}
		if ( $is_manager && ! $row_uid && $d['unassigned'] > 0 ) {
			$part = $d['confirmed'] > 0 ? number_format_i18n( $d['unassigned'] / $d['confirmed'] * 100, 1 ) . ' %' : '—';
			echo '<tr><td data-label="' . esc_attr( $cols_cf[0] ) . '">' . esc_html__( 'Non attribuées', 'aod-client-dashboard' ) . '</td>'
				. '<td data-label="' . esc_attr( $cols_cf[1] ) . '">' . (int) $d['unassigned'] . '</td>'
				. '<td data-label="' . esc_attr( $cols_cf[2] ) . '">' . esc_html( $part ) . '</td>'
				. '<td data-label="' . esc_attr( $cols_cf[3] ) . '">—</td></tr>';
		}
		echo '</tbody></table></div>';

		echo '<p class="aod-cd-note">' . esc_html__( 'Reçues = commandes créées sur la période, tous statuts. Confirmées = commandes tracées par un confirmateur + « non attribuées » (statut Confirmée/Terminée sans traçabilité, antérieures au suivi d’équipe).', 'aod-client-dashboard' ) . '</p>';

		if ( $delay_n ) {
			/* translators: %s : nombre de confirmations tracées mesurables. */
			echo '<p class="aod-cd-note">' . esc_html( sprintf( __( 'Délai moyen calculé sur %s confirmation(s) tracée(s) (de la création de la commande à sa première confirmation).', 'aod-client-dashboard' ), number_format_i18n( $delay_n ) ) ) . '</p>';
		}
		if ( $filters['produit'] ) {
			echo '<p class="aod-cd-note">' . esc_html__( 'Filtre produit actif : les chiffres portent sur les commandes contenant ce produit.', 'aod-client-dashboard' ) . '</p>';
		}
	}

	/**
	 * Construit les bacs (vides) du graphe pour une plage libre.
	 *
	 * Jours si la plage tient sur ≤ 45 jours (étiquette moyenne : ~8 visibles),
	 * mois au-delà. Chaque bac : val, label court, libellé complet, show.
	 * Timestamps calés à midi : évite les glissements de jour aux changements
	 * d'heure (le PHP de WordPress tourne en UTC, les libellés via wp_date).
	 *
	 * @param string $from    Borne basse Y-m-d (incluse).
	 * @param string $to      Borne haute Y-m-d (incluse).
	 * @param bool   $monthly Sortie : bacs mensuels (true) ou journaliers.
	 * @return array<string,array>
	 */
	protected function range_bins( $from, $to, &$monthly ) {
		$bins    = array();
		$from_ts = strtotime( $from . ' 12:00:00' );
		$to_ts   = strtotime( $to . ' 12:00:00' );
		$span    = (int) round( ( $to_ts - $from_ts ) / DAY_IN_SECONDS ) + 1;
		$monthly = $span > 45;

		if ( ! $monthly ) {
			$every = max( 1, (int) ceil( $span / 8 ) ); // ~8 étiquettes X maximum.
			for ( $i = 0; $i < $span; $i++ ) {
				$ts = $from_ts + $i * DAY_IN_SECONDS;
				$bins[ wp_date( 'Y-m-d', $ts ) ] = array(
					'val'   => 0.0,
					'label' => wp_date( 'd/m', $ts ),
					'full'  => wp_date( 'd/m/Y', $ts ),
					'show'  => ( 0 === $i % $every ),
				);
			}
			return $bins;
		}

		$cur    = strtotime( wp_date( 'Y-m', $from_ts ) . '-01 12:00:00' );
		$end    = strtotime( wp_date( 'Y-m', $to_ts ) . '-01 12:00:00' );
		$months = array();
		while ( $cur <= $end ) {
			$months[] = $cur;
			$cur      = strtotime( '+1 month', $cur );
		}
		$every = max( 1, (int) ceil( count( $months ) / 12 ) ); // ~12 étiquettes max.
		foreach ( $months as $i => $ts ) {
			$bins[ wp_date( 'Y-m', $ts ) ] = array(
				'val'   => 0.0,
				'label' => wp_date( 'M', $ts ),
				'full'  => wp_date( 'F Y', $ts ),
				'show'  => ( 0 === $i % $every ),
			);
		}
		return $bins;
	}

	/**
	 * Affiche un graphe à courbe (SVG lissé, sans librairie externe) du CA par bac.
	 *
	 * Le tracé est calculé côté serveur en unités viewBox ; un léger script
	 * (bindCharts) ajoute une infobulle interactive au survol. Aucune dépendance.
	 * ⚠️ Markup contractuel avec assets/js/dashboard.js (bindCharts) : classes
	 * aod-cd-linechart / aod-cd-guide / aod-cd-tip / aod-cd-dot + data-points.
	 *
	 * @param array $bins Bacs renvoyés par range_bins() et alimentés en CA.
	 */
	protected function render_stats_chart( $bins ) {
		if ( empty( $bins ) ) {
			return;
		}

		$total = 0.0;
		$max   = 0.0;
		foreach ( $bins as $b ) {
			$total += (float) $b['val'];
			if ( (float) $b['val'] > $max ) {
				$max = (float) $b['val'];
			}
		}

		echo '<div class="aod-cd-chartcard">';
		echo '<div class="aod-cd-chart-head">';
		echo '<h2 class="aod-cd-chart-title">' . esc_html__( 'Évolution du chiffre d’affaires', 'aod-client-dashboard' ) . '</h2>';
		echo '<span class="aod-cd-chart-total">' . esc_html__( 'Total', 'aod-client-dashboard' ) . ' <b>' . wp_kses_post( wc_price( $total ) ) . '</b></span>';
		echo '</div>';

		if ( $max <= 0 ) {
			echo AOD_CD_Dashboard::empty_state( 'chart', __( 'Aucune vente sur cette période.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			return;
		}

		// Géométrie du tracé (unités viewBox ; le SVG est mis à l'échelle en CSS).
		$vw = 920;
		$vh = 215;
		$pl = 50;  // marge gauche (étiquettes Y)
		$pr = 16;
		$pt = 16;
		$pb = 28;  // marge basse (étiquettes X)
		$pw = $vw - $pl - $pr;
		$ph = $vh - $pt - $pb;
		$base_y = $pt + $ph;
		$n = count( $bins );

		// Points (x,y) + données pour l'infobulle JS.
		$pts  = array();
		$data = array();
		$i    = 0;
		foreach ( $bins as $b ) {
			$x = ( $n <= 1 ) ? ( $pl + $pw / 2 ) : ( $pl + $i * $pw / ( $n - 1 ) );
			$y = $base_y - ( (float) $b['val'] / $max ) * $ph;
			$pts[] = array( $x, $y );
			$data[] = array(
				'x'      => round( $x, 1 ),
				'y'      => round( $y, 1 ),
				'label'  => $b['full'],
				'amount' => wp_strip_all_tags( wc_price( (float) $b['val'] ) ),
			);
			$i++;
		}

		// Courbe lissée : spline Catmull-Rom convertie en Béziers cubiques.
		$r    = function ( $v ) { return round( $v, 1 ); };
		$line = 'M' . $r( $pts[0][0] ) . ' ' . $r( $pts[0][1] );
		for ( $k = 1; $k < $n; $k++ ) {
			$p0  = $pts[ max( 0, $k - 2 ) ];
			$p1  = $pts[ $k - 1 ];
			$p2  = $pts[ $k ];
			$p3  = $pts[ min( $n - 1, $k + 1 ) ];
			$c1x = $p1[0] + ( $p2[0] - $p0[0] ) / 6;
			$c1y = $p1[1] + ( $p2[1] - $p0[1] ) / 6;
			$c2x = $p2[0] - ( $p3[0] - $p1[0] ) / 6;
			$c2y = $p2[1] - ( $p3[1] - $p1[1] ) / 6;
			$line .= ' C' . $r( $c1x ) . ' ' . $r( $c1y ) . ' ' . $r( $c2x ) . ' ' . $r( $c2y ) . ' ' . $r( $p2[0] ) . ' ' . $r( $p2[1] );
		}
		$area = $line . ' L' . $r( $pts[ $n - 1 ][0] ) . ' ' . $r( $base_y ) . ' L' . $r( $pts[0][0] ) . ' ' . $r( $base_y ) . ' Z';

		echo '<div class="aod-cd-linechart" data-vw="' . (int) $vw . '" data-vh="' . (int) $vh . '" data-points="' . esc_attr( wp_json_encode( $data ) ) . '">';
		echo '<svg viewBox="0 0 ' . (int) $vw . ' ' . (int) $vh . '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="' . esc_attr__( 'Chiffre d’affaires sur la période', 'aod-client-dashboard' ) . '">';
		echo '<defs><linearGradient id="aodAreaGrad" x1="0" y1="0" x2="0" y2="1">'
			. '<stop offset="0%" stop-color="var(--cd-accent)" stop-opacity="0.22"/>'
			. '<stop offset="100%" stop-color="var(--cd-accent)" stop-opacity="0"/>'
			. '</linearGradient></defs>';

		// Grille horizontale + étiquettes de l'axe Y.
		for ( $g = 0; $g <= 4; $g++ ) {
			$frac = $g / 4;
			$gy   = $base_y - $frac * $ph;
			$gv   = $max * $frac;
			echo '<line class="aod-cd-grid-line" x1="' . (int) $pl . '" y1="' . esc_attr( $r( $gy ) ) . '" x2="' . (int) ( $vw - $pr ) . '" y2="' . esc_attr( $r( $gy ) ) . '"/>';
			echo '<text class="aod-cd-axis-lbl" x="' . (int) ( $pl - 10 ) . '" y="' . esc_attr( $r( $gy + 3.5 ) ) . '" text-anchor="end">' . esc_html( $this->stats_compact_amount( $gv ) ) . '</text>';
		}

		// Aire dégradée + courbe.
		echo '<path class="aod-cd-area" d="' . esc_attr( $area ) . '"/>';
		// `pathLength="1"` NORMALISE la longueur du tracé à 1 : le pointillé qui le
		// fait se dessiner (`stroke-dasharray:1`, cf. dashboard.css) tient alors en
		// CSS pur, sans mesurer la courbe au JavaScript. C'est ce qui permet à
		// l'animation d'exister même si le script ne se charge pas — et de
		// disparaître proprement en mouvement réduit, où la feuille la neutralise.
		echo '<path class="aod-cd-line" pathLength="1" d="' . esc_attr( $line ) . '"/>';

		// Repère vertical (déplacé par le JS au survol).
		echo '<line class="aod-cd-guide" x1="' . (int) $pl . '" y1="' . (int) $pt . '" x2="' . (int) $pl . '" y2="' . esc_attr( $r( $base_y ) ) . '"/>';

		// Étiquettes X + points.
		$i = 0;
		foreach ( $bins as $b ) {
			$x = $pts[ $i ][0];
			$y = $pts[ $i ][1];
			if ( ! empty( $b['show'] ) ) {
				echo '<text class="aod-cd-x-lbl" x="' . esc_attr( $r( $x ) ) . '" y="' . (int) ( $vh - 13 ) . '" text-anchor="middle">' . esc_html( $b['label'] ) . '</text>';
			}
			echo '<circle class="aod-cd-dot" cx="' . esc_attr( $r( $x ) ) . '" cy="' . esc_attr( $r( $y ) ) . '" r="2.6"/>';
			$i++;
		}

		echo '</svg>';
		echo '<div class="aod-cd-tip" aria-hidden="true"></div>';
		echo '</div>'; // .aod-cd-linechart
		echo '</div>'; // .aod-cd-chartcard
	}

	/** Nombre de produits nommés dans le camembert ; le reste est agrégé. */
	const DONUT_PARTS = 5;

	/**
	 * Affiche la RÉPARTITION du chiffre d'affaires par produit (anneau SVG).
	 *
	 * Le compagnon de la courbe : celle-ci dit QUAND le chiffre est arrivé, celui-ci
	 * dit D'OÙ il vient. Aucune requête nouvelle — les deux se servent du même
	 * agrégat de période, déjà calculé et déjà mis en cache.
	 *
	 * 🚨 LE DÉNOMINATEUR EST `prod_rev`, ET SÛREMENT PAS `revenue`. Les deux existent
	 * dans le même tableau et ne mesurent pas la même chose : `revenue` est le total
	 * des commandes (frais de livraison compris, et restreint au produit filtré le
	 * cas échéant), tandis que `prod_rev` est la somme des LIGNES de produit — la
	 * seule grandeur dont les parts de ce graphe sont des morceaux. Rapporter des
	 * lignes à un total de commandes donnerait des pourcentages qui ne font pas 100,
	 * sans que rien ne le signale : un camembert faux s'affiche parfaitement.
	 *
	 * 🔴 ET C'EST LA MÊME RAISON QUI A FAIT ÉCARTER LE DÉCOUPAGE « PAR ÉTAT DE
	 * COMMANDE », qui semblait pourtant le candidat naturel (encaissées / en attente
	 * / annulées, trois nombres déjà à l'écran). Mesuré dans `stats_data()` :
	 * `nb_pending` est un INSTANTANÉ TOUTES PÉRIODES (`count_status( 'pending', ''
	 * … )`, argument de date volontairement vide), quand `nb_paid` et `nb_cancel`
	 * sont bornés à la période. Les trois parts n'auraient donc pas partagé de
	 * dénominateur. Le fichier le savait déjà — c'est pour ça qu'aucun delta n'est
	 * affiché sur cette carte — mais rien n'empêchait de l'oublier une part plus
	 * loin. *Trois nombres côte à côte sur un écran n'ont pas, pour autant, le droit
	 * d'être additionnés.*
	 *
	 * ⚠️ La part « Autres » n'est pas cosmétique : `$top` est tronqué à 10 produits
	 * en amont, et on n'en nomme que DONUT_PARTS. Sans elle, l'anneau serait
	 * incomplet tout en ayant l'air entier.
	 *
	 * @param array $top      Produits triés par CA décroissant (cf. stats_data()).
	 * @param float $prod_rev CA des lignes de produit sur la période.
	 */
	protected function render_stats_donut( $top, $prod_rev ) {
		$prod_rev = (float) $prod_rev;

		echo '<div class="aod-cd-chartcard aod-cd-donutcard">';
		echo '<div class="aod-cd-chart-head">';
		echo '<h2 class="aod-cd-chart-title">' . esc_html__( 'Répartition du chiffre d’affaires', 'aod-client-dashboard' ) . '</h2>';
		echo '</div>';

		if ( $prod_rev <= 0 || empty( $top ) ) {
			echo AOD_CD_Dashboard::empty_state( 'chart', __( 'Aucune vente sur cette période.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- empty_state() rend un SVG du catalogue FERMÉ AOD_CD_Icon et échappe son libellé ; l'échapper ici retirerait le balisage.
			echo '</div>';
			return;
		}

		// Parts nommées, puis le reliquat. `array_slice` est sûr sur un tableau plus
		// court que la découpe : un clone à deux produits rend deux parts.
		$parts = array();
		$somme = 0.0;
		foreach ( array_slice( $top, 0, self::DONUT_PARTS ) as $row ) {
			$rev = (float) $row['rev'];
			if ( $rev <= 0 ) {
				continue;
			}
			$parts[] = array( 'name' => (string) $row['name'], 'rev' => $rev );
			$somme  += $rev;
		}
		$reste = $prod_rev - $somme;
		if ( $reste > 0.005 ) {
			$parts[] = array( 'name' => __( 'Autres produits', 'aod-client-dashboard' ), 'rev' => $reste );
		}
		if ( empty( $parts ) ) {
			echo AOD_CD_Dashboard::empty_state( 'chart', __( 'Aucune vente sur cette période.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- même dérogation que ci-dessus.
			echo '</div>';
			return;
		}

		/*
		 * Géométrie : un seul cercle par part, tracé au `stroke-dasharray`. C'est la
		 * technique la plus courte qui reste NETTE à toute taille (pas de `path`
		 * calculé en trigonométrie, donc pas d'arrondi qui laisse un cheveu blanc
		 * entre deux parts), et elle se prête au responsive sans JavaScript.
		 * Le SVG est en `viewBox` : toutes les longueurs ci-dessous sont des unités
		 * de dessin, jamais des pixels — la carte peut faire 260 ou 420 px de large.
		 */
		$rayon = 45.0;
		$circ  = 2 * M_PI * $rayon;
		$vide  = 1.4; // Coupure entre deux parts, en unités de contour.

		$svg    = '';
		$lignes = '';
		$depart = 0.0;
		$i      = 0;
		foreach ( $parts as $p ) {
			$pct = ( $p['rev'] / $prod_rev ) * 100;
			$len = max( 0.0, ( $circ * $pct / 100 ) - $vide );

			$svg .= '<circle class="aod-cd-donut__arc" cx="60" cy="60" r="' . esc_attr( (string) $rayon ) . '"'
				. ' style="--part:' . (int) min( $i, self::DONUT_PARTS ) . '"'
				. ' stroke-dasharray="' . esc_attr( number_format( $len, 2, '.', '' ) . ' ' . number_format( $circ - $len, 2, '.', '' ) ) . '"'
				. ' stroke-dashoffset="' . esc_attr( number_format( -$depart, 2, '.', '' ) ) . '"/>';

			$lignes .= '<li class="aod-cd-donut__item">'
				. '<span class="aod-cd-donut__dot" style="--part:' . (int) min( $i, self::DONUT_PARTS ) . '" aria-hidden="true"></span>'
				. '<span class="aod-cd-donut__name">' . esc_html( $p['name'] ) . '</span>'
				. '<span class="aod-cd-donut__pct">' . esc_html( number_format_i18n( $pct, 1 ) . ' %' ) . '</span>'
				. '</li>';

			$depart += $circ * $pct / 100;
			$i++;
		}

		/*
		 * 🔒 `role="img"` + un nom : sans lui, un lecteur d'écran annonce « graphique »
		 * et s'arrête. La LISTE en dessous porte la donnée complète (nom, part), donc
		 * l'anneau n'a pas à la répéter — il annonce ce qu'il représente, et le détail
		 * se lit dans le texte, qui est du vrai texte et non une image de texte.
		 */
		echo '<div class="aod-cd-donut">';
		echo '<svg class="aod-cd-donut__svg" viewBox="0 0 120 120" role="img" aria-label="'
			. esc_attr__( 'Répartition du chiffre d’affaires par produit', 'aod-client-dashboard' ) . '">';
		echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG assemblé ci-dessus : chaque attribut variable passe par esc_attr(), les nombres par number_format() ; l'échapper ici rendrait le balisage en texte.
		echo '</svg>';
		echo '<div class="aod-cd-donut__mid"><span class="aod-cd-donut__midlab">' . esc_html__( 'Total', 'aod-client-dashboard' ) . '</span>'
			. '<b class="aod-cd-donut__midval">' . wp_kses_post( wc_price( $prod_rev ) ) . '</b></div>';
		echo '</div>';

		echo '<ul class="aod-cd-donut__legend">';
		echo $lignes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- même assemblage : `name` passe par esc_html(), la part par esc_html( number_format_i18n() ).
		echo '</ul>';

		echo '</div>'; // .aod-cd-chartcard
	}

	/**
	 * Formate un montant de façon compacte pour les étiquettes d'axe (12,5k, 1,2M).
	 *
	 * @param float $n Montant.
	 * @return string
	 */
	protected function stats_compact_amount( $n ) {
		$n = (float) $n;
		if ( $n >= 1000000 ) {
			return number_format_i18n( $n / 1000000, ( $n < 10000000 ) ? 1 : 0 ) . 'M';
		}
		if ( $n >= 1000 ) {
			return number_format_i18n( $n / 1000, ( $n < 10000 ) ? 1 : 0 ) . 'k';
		}
		return number_format_i18n( $n );
	}
}
