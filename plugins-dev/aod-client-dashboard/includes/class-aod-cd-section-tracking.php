<?php
/**
 * Section « Suivi commande » : évolution des colis chez le livreur — liste
 * filtrable des colis expédiés, pastille d'état normalisée, timeline en modale,
 * synchronisation à la demande (par tranches, 32.4).
 *
 * 🧩 EXTRACTION (Phase 30.5). Sortie du monolithe `class-aod-cd-dashboard.php` à
 * comportement CONSTANT : mêmes actions AJAX (`aod_cd_tracking_refresh`,
 * `aod_cd_tracking_detail`) — `dashboard.js` inchangé —, même rendu. Move
 * verbatim ; seuls les helpers d'UI partagés (`page_head`, `empty_state`,
 * `render_pagination`) deviennent `AOD_CD_Dashboard::…`, et le périmètre
 * produits (`scoped_orders()` / `order_in_scope()`) vit chez la section
 * Commandes (`AOD_CD_Section_Orders`, depuis la 30.6) — c'est la logique de
 * TOUTES les listes de commandes, pas celle du suivi. Les statiques
 * `tracking_tone()` / `tracking_pill()` suivent la section : leur unique
 * appelant externe (`AOD_CD_Section_Home`) a suivi la classe.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Tracking {

	/** @var AOD_CD_Section_Tracking|null */
	protected static $instance = null;

	/**
	 * Colis « bloqué » : état non terminal et aucun événement de suivi depuis ce
	 * nombre de jours. Même seuil que AOD_CD_Section_Home::BLOCKED_DAYS.
	 */
	const TRACK_BLOCKED_DAYS = 7;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_aod_cd_tracking_refresh', array( $this, 'ajax_tracking_refresh' ) );
		add_action( 'wp_ajax_aod_cd_tracking_detail', array( $this, 'ajax_tracking_detail' ) );
	}

	/**
	 * Page « Suivi commande » : liste des colis expédiés avec leur état actuel,
	 * récupéré depuis le compte du transporteur (synchro cron + à la demande).
	 */
	public function render() {
		if ( ! class_exists( 'AOD_Shipping' ) || ! class_exists( 'AOD_Carrier' ) ) {
			echo AOD_CD_Dashboard::empty_state( 'shipping', __( 'Le module de livraison n’est pas actif.', 'aod-client-dashboard' ), __( 'Activez le plugin « AOD COD Form » pour suivre l’évolution des colis.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$base_url = home_url( '/' . AOD_CD_SLUG . '/tracking' );
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		// « Assigné à » (42.7) + filtre confirmateur (43.2) : même règle
		// gérant-only que la page Commandes (cf. AOD_CD_Section_Orders::
		// render_order_detail — un confirmateur ne voit jamais le nom d'un
		// assigné). Remonté ICI (43.2) — était calculé après le early-return
		// « aucun colis » et après render_tracking_filters(), donc inutilisable
		// pour gater le <select> confirmateur ou construire le meta_query.
		$can_assign = current_user_can( 'manage_woocommerce' );

		// Filtres (GET, whitelistés — une valeur inconnue est simplement ignorée).
		// Seuls les transporteurs configurés sont proposés : la liste complète
		// compte des dizaines de white-labels EcoTrack, inutilisables ici.
		$carriers = AOD_Shipping::instance()->configured();
		$f_car    = isset( $_GET['transporteur'] ) ? sanitize_key( wp_unslash( $_GET['transporteur'] ) ) : '';
		if ( '' !== $f_car && ! isset( $carriers[ $f_car ] ) ) {
			$f_car = '';
		}
		$states = $this->tracking_state_choices();
		$f_st   = isset( $_GET['etat'] ) ? sanitize_key( wp_unslash( $_GET['etat'] ) ) : '';
		if ( '' !== $f_st && ! isset( $states[ $f_st ] ) ) {
			$f_st = '';
		}

		// Filtre par date (43.1) — sur date_created, la SEULE date rendue sur
		// cette page (colonne « Date ») ; `_aod_ship_status_at` est la
		// dernière mise à jour de SUIVI, pas la date d'envoi (cf. story).
		$date_from = AOD_CD_Dashboard::parse_date_param( 'date_from' );
		$date_to   = AOD_CD_Dashboard::parse_date_param( 'date_to' );
		$range     = AOD_CD_Dashboard::resolve_date_range( $date_from, $date_to );

		// Filtre par confirmateur (43.2) — GÉRANT UNIQUEMENT, même patron que
		// Commandes : confiance zéro dans l'ID posté, vivier
		// eligible_confirmers( array(), true ).
		$confirmers       = $can_assign ? AOD_CD_Roles::eligible_confirmers( array(), true ) : array();
		$confirmer_filter = 0;
		if ( $can_assign && isset( $_GET['confirmateur'] ) ) {
			$posted_confirmer = absint( $_GET['confirmateur'] );
			if ( in_array( $posted_confirmer, wp_list_pluck( $confirmers, 'ID' ), true ) ) {
				$confirmer_filter = $posted_confirmer;
			}
		}

		$filtered = ( '' !== $f_car || '' !== $f_st || '' !== $range['from'] || '' !== $range['to'] || $confirmer_filter );

		$meta_query = $this->tracking_meta_query( $f_car, $f_st, $confirmer_filter );

		// scoped_orders() : un confirmateur ne voit que les colis de ses produits
		// affectés (parité avec la page Commandes).
		$track_args = array(
			'type'       => 'shop_order',
			'limit'      => 20,
			'paged'      => $paged,
			'paginate'   => true,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_query' => $meta_query,
		);
		// Filtre par date (43.1) — seulement si au moins une borne est posée.
		$date_created = AOD_CD_Dashboard::date_created_query_arg( $range['from'], $range['to'] );
		if ( null !== $date_created ) {
			$track_args['date_created'] = $date_created;
		}
		$result = AOD_CD_Section_Orders::instance()->scoped_orders( $track_args );
		$orders = $result->orders;

		echo '<div class="aod-cd-tracking">';

		// L'en-tête DOIT rester à l'intérieur de .aod-cd-tracking : bindTracking()
		// cherche « Actualiser tout » dans ce conteneur (dashboard.js).
		$refresh = '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-track-refresh-all">' . AOD_CD_Icon::svg( 'refresh', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Actualiser tout', 'aod-client-dashboard' ) . '</button>';
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Suivi commande', 'aod-client-dashboard' ),
			__( 'Évolution des colis expédiés, récupérée automatiquement depuis le compte du transporteur.', 'aod-client-dashboard' ),
			$refresh
		);

		// ⚠️ PAS DE BANDEAU SUR UNE BOUTIQUE QUI N'A JAMAIS EXPÉDIÉ. Trois zéros
		// posés au-dessus de « Aucun colis expédié pour le moment » disent la même
		// chose que l'état vide, en trois fois — et c'est précisément l'écran d'un
		// clone neuf, donc la première impression de la page. Un filtre qui ne rend
		// rien, lui, GARDE son bandeau : les compteurs sont alors la sortie de
		// secours (« 2 bloqués » reste cliquable quand la liste est vide).
		if ( ! empty( $orders ) || $filtered ) {
			$this->render_tracking_pulse( $base_url, $f_car, $f_st, $range, $confirmer_filter );
		}

		// La barre de filtres est TOUJOURS rendue — sinon un filtre sans résultat
		// masquerait le moyen de l'annuler.
		$this->render_tracking_filters( $base_url, $carriers, $states, $f_car, $f_st, $range, $confirmers, $confirmer_filter );

		if ( empty( $orders ) ) {
			if ( $filtered ) {
				echo AOD_CD_Dashboard::empty_state( 'search', __( 'Aucun colis ne correspond à ces filtres.', 'aod-client-dashboard' ), __( 'Modifiez le transporteur ou l’état, ou réinitialisez les filtres.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<p class="aod-cd-note"><a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $base_url ) . '">' . esc_html__( 'Réinitialiser les filtres', 'aod-client-dashboard' ) . '</a></p>';
			} else {
				echo AOD_CD_Dashboard::empty_state( 'products', __( 'Aucun colis expédié pour le moment.', 'aod-client-dashboard' ), __( 'Le suivi apparaîtra ici dès qu’une commande sera envoyée à un transporteur.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div>';
			return;
		}

		echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards aod-cd-track-table"><thead><tr>';
		$headers = array(
			__( 'N°', 'aod-client-dashboard' ),
			__( 'Date', 'aod-client-dashboard' ),
			__( 'Client', 'aod-client-dashboard' ),
			__( 'Transporteur', 'aod-client-dashboard' ),
			__( 'N° de suivi', 'aod-client-dashboard' ),
			__( 'État du colis', 'aod-client-dashboard' ),
			__( 'Dernière MAJ', 'aod-client-dashboard' ),
		);
		if ( $can_assign ) {
			$headers[] = __( 'Assigné à', 'aod-client-dashboard' );
		}
		$headers[] = __( 'Détail', 'aod-client-dashboard' );
		foreach ( $headers as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$this->render_tracking_row( $order, $can_assign );
		}
		echo '</tbody></table></div>';

		AOD_CD_Dashboard::render_pagination( $base_url, $paged, (int) $result->max_num_pages, array_filter( array(
			'transporteur' => $f_car,
			'etat'         => $f_st,
			'date_from'    => $range['from'],
			'date_to'      => $range['to'],
			'confirmateur' => $confirmer_filter,
		) ) );
		echo '</div>';
	}

	/**
	 * Le `meta_query` de la page Suivi — UN SEUL CORPS, deux appelants.
	 *
	 * 🚨 IL EST DEVENU UNE MÉTHODE LE JOUR OÙ IL A EU UN SECOND LECTEUR. Tant que
	 * `render()` était seul à le construire, l'écrire sur place ne coûtait rien.
	 * Le bandeau de compteurs pose la MÊME question à la base une fois par état :
	 * recopié, il aurait suffi qu'un critère change ici pour que le nombre affiché
	 * en tête cesse de décrire la liste qu'il surplombe — et rien ne l'aurait dit,
	 * puisque les deux chiffres seraient restés plausibles.
	 *
	 * C'est exactement la garde que porte déjà `is_tracking_blocked()` quelques
	 * lignes plus bas : elle ne peut pas partager ce code (elle interroge un objet,
	 * pas SQL) et doit donc rester son jumeau à la main. Là où le partage EST
	 * possible, on ne s'en prive pas.
	 *
	 * @param string $f_car            Transporteur filtré, `''` = tous.
	 * @param string $f_st             État filtré, `''` = tous, `bloques` = immobiles.
	 * @param int    $confirmer_filter ID du confirmateur filtré, `0` = tous.
	 * @return array `meta_query` WooCommerce.
	 */
	protected function tracking_meta_query( $f_car, $f_st, $confirmer_filter ) {
		// Un colis « bloqué » = état non terminal + aucun événement depuis N jours.
		$meta_query = array(
			array(
				'key'     => AOD_Carrier::META_TRACKING,
				'value'   => '',
				'compare' => '!=',
			),
		);
		if ( '' !== $f_car ) {
			$meta_query[] = array(
				'key'   => AOD_Carrier::META_CARRIER,
				'value' => $f_car,
			);
		}
		if ( 'bloques' === $f_st ) {
			$meta_query[] = array(
				'key'     => AOD_Carrier::META_STATUS,
				'value'   => AOD_Carrier::tracking_terminal_statuses(),
				'compare' => 'NOT IN',
			);
			$meta_query[] = array(
				'key'     => AOD_Carrier::META_STATUS_AT,
				'value'   => time() - ( self::TRACK_BLOCKED_DAYS * DAY_IN_SECONDS ),
				'compare' => '<',
				'type'    => 'NUMERIC',
			);
		} elseif ( '' !== $f_st ) {
			$meta_query[] = array(
				'key'   => AOD_Carrier::META_STATUS,
				'value' => $f_st,
			);
		}
		if ( $confirmer_filter ) {
			$meta_query[] = array(
				'key'   => '_aod_assigned_confirmer',
				'value' => $confirmer_filter,
			);
		}
		return $meta_query;
	}

	/**
	 * Combien de colis dans cet état — DANS LE PÉRIMÈTRE ET LES FILTRES COURANTS.
	 *
	 * ⚠️ Le transporteur, la plage de dates et le confirmateur sont CONSERVÉS ;
	 * seul l'état varie. Un bandeau qui compterait toute la boutique pendant que
	 * la liste montre un seul transporteur afficherait deux vérités sur le même
	 * écran — le défaut que la Phase 48 a mis deux passes à fermer sur l'Accueil.
	 *
	 * @param string $f_st             État compté (`''` = tous, `bloques` = immobiles).
	 * @param string $f_car            Transporteur filtré.
	 * @param array  $range            Plage de dates active.
	 * @param int    $confirmer_filter Confirmateur filtré.
	 * @return int
	 */
	protected function tracking_count( $f_st, $f_car, $range, $confirmer_filter ) {
		$args = array(
			'type'       => 'shop_order',
			'limit'      => 1,
			'paginate'   => true,
			'return'     => 'ids',
			'meta_query' => $this->tracking_meta_query( $f_car, $f_st, $confirmer_filter ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- corps unique partagé avec la liste ; clés indexées, gouverné par scoped_orders().
		);
		$date_created = AOD_CD_Dashboard::date_created_query_arg( $range['from'], $range['to'] );
		if ( null !== $date_created ) {
			$args['date_created'] = $date_created;
		}
		$res = AOD_CD_Section_Orders::instance()->scoped_orders( $args );
		return isset( $res->total ) ? (int) $res->total : 0;
	}

	/**
	 * Choix du filtre « État du colis » : les 8 états normalisés + « bloqués ».
	 *
	 * @return array<string,string> clé => libellé traduit.
	 */
	protected function tracking_state_choices() {
		$out = array();
		foreach ( array(
			AOD_Carrier::ST_CREATED,
			AOD_Carrier::ST_IN_TRANSIT,
			AOD_Carrier::ST_OUT_FOR_DELIVERY,
			AOD_Carrier::ST_DELIVERED,
			AOD_Carrier::ST_FAILED,
			AOD_Carrier::ST_RETURNED,
			AOD_Carrier::ST_CANCELLED,
			AOD_Carrier::ST_UNKNOWN,
		) as $st ) {
			$meta        = AOD_Carrier::tracking_status_meta( $st );
			$out[ $st ] = $meta['label'];
		}
		/* translators: %d : nombre de jours sans nouvel événement de suivi */
		$out['bloques'] = sprintf( __( 'Bloqués (plus de %d jours)', 'aod-client-dashboard' ), self::TRACK_BLOCKED_DAYS );
		return $out;
	}

	/**
	 * ⏱️ LE POULS DES COLIS — trois chiffres au-dessus de la liste.
	 *
	 * 🚨 CE QUE ÇA CORRIGE. Le marqueur « Bloqué » posé en 43.x désigne un colis
	 * immobile DANS SA LIGNE : encore faut-il tomber dessus. La liste est paginée
	 * par 20 et triée par date — un colis parti il y a trois semaines est en page
	 * 2 ou 3, c'est-à-dire nulle part. Le seul fait de cette page sur lequel on
	 * peut encore agir était donc lisible uniquement par celui qui le cherchait
	 * déjà. C'est le MÊME défaut que le marqueur croyait fermer, d'un cran plus
	 * haut : *rendre une information visible dans la ligne ne la rend pas visible
	 * sur la page.*
	 *
	 * 🔗 Chaque tuile est un LIEN vers le filtre qui isole exactement ce qu'elle
	 * compte, et le compte passe par `tracking_meta_query()` — le corps que la
	 * liste emploie. Le nombre et la liste qu'il ouvre ne peuvent pas diverger :
	 * ils sont la même requête, à la pagination près.
	 *
	 * ⚠️ TROIS TUILES, PAS NEUF. Le menu déroulant liste déjà les huit états ; les
	 * recopier ici en aurait fait un second menu, plus gros et moins complet. On
	 * garde les trois questions qu'un gérant se pose devant des colis partis :
	 * combien sont en route, combien n'avancent plus, combien sont arrivés.
	 *
	 * ⚠️ « Bloqués » reste rendu à ZÉRO, en ton neutre. Une tuile qui disparaît
	 * quand tout va bien laisse le doute (« a-t-elle disparu, ou n'ai-je pas
	 * regardé ? ») ; « 0 » est une réponse, l'absence n'en est pas une.
	 *
	 * @param string $base_url         URL de la section.
	 * @param string $f_car            Transporteur filtré.
	 * @param string $f_st             État filtré (surligne la tuile active).
	 * @param array  $range            Plage de dates active.
	 * @param int    $confirmer_filter Confirmateur filtré.
	 */
	protected function render_tracking_pulse( $base_url, $f_car, $f_st, $range, $confirmer_filter ) {
		// Les filtres AUTRES que l'état sont reconduits dans chaque lien : cliquer
		// « Bloqués » depuis « Yalidine » doit donner les bloqués de Yalidine, pas
		// ceux de la boutique entière.
		$porte = array_filter( array(
			'transporteur' => $f_car,
			'date_from'    => $range['from'],
			'date_to'      => $range['to'],
			'confirmateur' => $confirmer_filter,
		) );
		$lien  = function ( $etat ) use ( $base_url, $porte ) {
			$q = $porte;
			if ( '' !== $etat ) {
				$q['etat'] = $etat;
			}
			return $q ? add_query_arg( $q, $base_url ) : $base_url;
		};

		$n_tous    = $this->tracking_count( '', $f_car, $range, $confirmer_filter );
		$n_bloques = $this->tracking_count( 'bloques', $f_car, $range, $confirmer_filter );
		$n_livres  = $this->tracking_count( AOD_Carrier::ST_DELIVERED, $f_car, $range, $confirmer_filter );

		echo AOD_CD_Dashboard::kpis( // phpcs:ignore WordPress.Security.EscapeOutput -- composant : libellés échappés, valeurs entières, URLs esc_url().
			array(
				array(
					__( 'Colis suivis', 'aod-client-dashboard' ),
					(string) $n_tous,
					'shipping',
					'accent',
					'',
					'' === $f_st ? 'actif' : '',
					$lien( '' ),
				),
				array(
					__( 'Bloqués', 'aod-client-dashboard' ),
					(string) $n_bloques,
					'warning',
					$n_bloques ? 'amber' : 'slate',
					'',
					'bloques' === $f_st ? 'actif' : '',
					$lien( 'bloques' ),
				),
				array(
					__( 'Livrés', 'aod-client-dashboard' ),
					(string) $n_livres,
					'check',
					'green',
					'',
					AOD_Carrier::ST_DELIVERED === $f_st ? 'actif' : '',
					$lien( AOD_Carrier::ST_DELIVERED ),
				),
			)
		);
	}

	/**
	 * Barre de filtres de la page « Suivi commande » (GET natif, aucun JS).
	 *
	 * @param string               $base_url URL de la section.
	 * @param array                $carriers Transporteurs disponibles.
	 * @param array<string,string> $states   Choix d'états.
	 * @param string               $f_car    Transporteur actif.
	 * @param string               $f_st     État actif.
	 * @param array{from:string,to:string} $range Plage de dates active (43.1).
	 * @param WP_User[] $confirmers       Vivier gérant (43.2) — vide si le
	 *                  visiteur courant n'est pas gérant, auquel cas le
	 *                  `<select>` n'est pas rendu du tout.
	 * @param int       $confirmer_filter ID du confirmateur actif, `0` = aucun.
	 */
	protected function render_tracking_filters( $base_url, $carriers, $states, $f_car, $f_st, $range = array( 'from' => '', 'to' => '' ), $confirmers = array(), $confirmer_filter = 0 ) {
		echo '<form class="aod-cd-form aod-cd-filters aod-cd-track-filters" method="get" action="' . esc_url( $base_url ) . '">';

		// Le filtre transporteur n'a de sens que si la boutique en a configuré au
		// moins un ; sinon on n'affiche qu'un menu vide.
		if ( $carriers ) {
			echo '<div class="aod-cd-field"><label for="aod-cd-f-carrier">' . esc_html__( 'Transporteur', 'aod-client-dashboard' ) . '</label>';
			// `data-aod-select` sur les TROIS filtres de cette barre (2026-08-06) :
			// même composant que l'Accueil et Commandes. Le natif s'ouvre hors champ
			// sur mobile — cf. la note de `AOD_CD_Section_Orders::render_filters()`.
			echo '<select id="aod-cd-f-carrier" name="transporteur" data-aod-select>';
			echo '<option value="">' . esc_html__( 'Tous les transporteurs', 'aod-client-dashboard' ) . '</option>';
			foreach ( $carriers as $cid => $carrier ) {
				echo '<option value="' . esc_attr( $cid ) . '" ' . selected( $f_car, $cid, false ) . '>' . esc_html( $carrier->label() ) . '</option>';
			}
			echo '</select></div>';
		}

		echo '<div class="aod-cd-field"><label for="aod-cd-f-state">' . esc_html__( 'État du colis', 'aod-client-dashboard' ) . '</label>';
		echo '<select id="aod-cd-f-state" name="etat" data-aod-select>';
		echo '<option value="">' . esc_html__( 'Tous les états', 'aod-client-dashboard' ) . '</option>';
		foreach ( $states as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $f_st, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';

		// Filtre par date (43.1) — même formulaire, pas de champ caché
		// nécessaire (transporteur/état sont déjà des champs de CE formulaire).
		AOD_CD_Dashboard::render_date_fields( $range['from'], $range['to'] );

		// Filtre par confirmateur (43.2) — GÉRANT UNIQUEMENT : $confirmers est
		// déjà vide pour tout autre visiteur (cf. render()), donc ce bloc ne
		// s'affiche naturellement que pour le gérant, sans revérifier la
		// capability ici.
		if ( $confirmers ) {
			echo '<div class="aod-cd-field"><label for="aod-cd-f-confirmer-track">' . esc_html__( 'Confirmateur', 'aod-client-dashboard' ) . '</label>';
			echo '<select id="aod-cd-f-confirmer-track" name="confirmateur" data-aod-select>';
			echo '<option value="">' . esc_html__( 'Tous les confirmateurs', 'aod-client-dashboard' ) . '</option>';
			foreach ( $confirmers as $confirmer ) {
				echo '<option value="' . esc_attr( $confirmer->ID ) . '" ' . selected( $confirmer_filter, $confirmer->ID, false ) . '>' . esc_html( $confirmer->display_name ) . '</option>';
			}
			echo '</select></div>';
		}

		echo '<div class="aod-cd-field aod-cd-filters-actions">';
		echo '<button type="submit" class="aod-cd-btn aod-cd-btn-primary">' . esc_html__( 'Filtrer', 'aod-client-dashboard' ) . '</button>';
		if ( '' !== $f_car || '' !== $f_st || '' !== $range['from'] || '' !== $range['to'] || $confirmer_filter ) {
			echo '<a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $base_url ) . '">' . esc_html__( 'Réinitialiser', 'aod-client-dashboard' ) . '</a>';
		}
		echo '</div>';

		echo '</form>';
	}

	/**
	 * Une ligne de la page « Suivi commande ».
	 *
	 * @param WC_Order $order
	 */
	protected function render_tracking_row( $order, $can_assign = false ) {
		$cid      = (string) $order->get_meta( AOD_Carrier::META_CARRIER );
		$tracking = (string) $order->get_meta( AOD_Carrier::META_TRACKING );
		$carrier  = ( '' !== $cid ) ? AOD_Shipping::instance()->carrier( $cid ) : null;
		$cname    = $carrier ? $carrier->label() : ( '' !== $cid ? $cid : '—' );
		$client   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		echo '<tr data-order="' . esc_attr( $order->get_id() ) . '">';
		// 45.3 — même traitement que la liste des Commandes : le n° redevient du
		// texte, l'ouverture de la fenêtre de détail passe par un bouton nommé,
		// dans la colonne d'actions qui existe déjà plus bas. Le motif était
		// identique à l'octet près dans les deux sections : corriger l'une sans
		// l'autre aurait laissé deux grammaires de tableau dans la même appli.
		echo '<td class="aod-cd-cell-num" data-label="' . esc_attr__( 'N°', 'aod-client-dashboard' ) . '"><span class="aod-cd-onum">#' . esc_html( $order->get_order_number() ) . '</span></td>';
		echo '<td data-label="' . esc_attr__( 'Date', 'aod-client-dashboard' ) . '">' . esc_html( wc_format_datetime( $order->get_date_created(), 'd/m/Y H:i' ) ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Client', 'aod-client-dashboard' ) . '">' . esc_html( $client ? $client : '—' ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Transporteur', 'aod-client-dashboard' ) . '">' . esc_html( $cname ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'N° de suivi', 'aod-client-dashboard' ) . '">' . ( '' !== $tracking ? '<code class="aod-cd-trackcode">' . esc_html( $tracking ) . '</code>' : '—' ) . '</td>';
		// 🚨 « BLOQUÉ » EXISTAIT DÉJÀ DANS LE PRODUIT, ET NULLE PART À L'ÉCRAN.
		// Le filtre « Bloqués (plus de N jours) » sait isoler ces colis depuis
		// toujours ; la LISTE, elle, ne les distinguait pas — il fallait donc
		// soupçonner le problème pour le chercher, alors que c'est le seul fait de
		// cette page sur lequel on peut encore agir. Un colis immobile depuis trois
		// semaines, c'est un client qu'on perd et un remboursement qui vient.
		// ⚠️ Le marqueur est dérivé de LA MÊME RÈGLE que le filtre (état non
		// terminal + horodatage antérieur au seuil) — c'est la leçon de la grille
		// des wilayas, où un critère visuel divergent aurait contredit le compteur
		// posé juste au-dessus. Un état dérivé d'une autre règle que le chiffre
		// qu'il accompagne n'ajoute pas une information : il ouvre une contradiction.
		$bloque = $this->is_tracking_blocked( $order );
		echo '<td class="aod-cd-track-pillcell' . ( $bloque ? ' is-bloque' : '' ) . '" data-label="' . esc_attr__( 'État du colis', 'aod-client-dashboard' ) . '">' . $this->tracking_pill_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $bloque ) {
			echo '<span class="aod-cd-track-stuck">' . esc_html__( 'Bloqué', 'aod-client-dashboard' ) . '</span>';
		}
		echo '</td>';
		echo '<td class="aod-cd-track-atcell" data-label="' . esc_attr__( 'Dernière MAJ', 'aod-client-dashboard' ) . '">' . esc_html( $this->tracking_updated_at( $order ) ) . '</td>';
		if ( $can_assign ) {
			echo '<td class="aod-cd-cell-assigned" data-label="' . esc_attr__( 'Assigné à', 'aod-client-dashboard' ) . '">' . AOD_CD_Section_Orders::assigned_confirmer_label( $order ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- markup échappé dans assigned_confirmer_label().
		}
		// 45.3 — le libellé de colonne passe de « Détail » à « Actions » : elle
		// porte désormais trois gestes, dont un bouton qui s'appelle « Détail ».
		// Garder l'ancien nom ferait dire à l'en-tête de carte mobile « Détail »
		// au-dessus d'un bouton « Détail ».
		echo '<td class="aod-cd-track-actions" data-label="' . esc_attr__( 'Actions', 'aod-client-dashboard' ) . '">';
		echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-track-detail" data-order="' . esc_attr( $order->get_id() ) . '">' . esc_html__( 'Voir le suivi', 'aod-client-dashboard' ) . '</button> ';
		// Ouvre la MÊME fenêtre que la liste des Commandes (classe conservée pour
		// `bindOrderDetail`) — c'est le geste que le n° souligné rendait invisible.
		echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-order-detail" data-order="' . esc_attr( $order->get_id() ) . '">' . AOD_CD_Icon::svg( 'orders', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Détail', 'aod-client-dashboard' ) . '</button> ';
		// Même glyphe et même taille que « Actualiser tout » (l'en-tête de render()) :
		// les deux boutons font le même geste à deux portées différentes. Le nom
		// accessible reste porté par `title` — le SVG est décoratif (`aria-hidden`).
		echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-track-refresh" data-order="' . esc_attr( $order->get_id() ) . '" title="' . esc_attr__( 'Actualiser cette commande', 'aod-client-dashboard' ) . '">' . AOD_CD_Icon::svg( 'refresh', array( 'size' => 15 ) ) . '</button>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Ce colis est-il BLOQUÉ ? — le pendant PHP du filtre « bloques ».
	 *
	 * 🚨 CE CORPS DOIT RESTER LE JUMEAU EXACT du `meta_query` de `render()`
	 * (état NOT IN terminaux + `META_STATUS_AT` < seuil). Les deux ne peuvent pas
	 * partager le même code — l'un interroge SQL, l'autre un objet déjà chargé —
	 * mais ils partagent la CONSTANTE et la liste des états terminaux, qui sont
	 * les deux seuls endroits où la règle peut changer. Si l'une bouge, les deux
	 * suivent ; c'est ce qui empêche la ligne de dire l'inverse du filtre.
	 *
	 * ⚠️ UN HORODATAGE ABSENT (0) COMPTE COMME BLOQUÉ, et c'est un choix, pas un
	 * oubli : `0 < seuil` est vrai en SQL, donc le filtre range déjà ces colis
	 * parmi les bloqués. On pourrait trouver plus juste de les en exclure — un
	 * colis parti il y a deux minutes et jamais synchronisé n'est pas immobile
	 * depuis sept jours — mais diverger du filtre pour avoir raison tout seul
	 * afficherait deux vérités sur le même écran. La ligne rend alors
	 * « Jamais » en dernière MAJ à côté de « Bloqué », ce qui décrit exactement
	 * la situation : la synchronisation n'a jamais tourné.
	 *
	 * @param WC_Order $order Commande.
	 * @return bool
	 */
	protected function is_tracking_blocked( $order ) {
		$status = (string) $order->get_meta( AOD_Carrier::META_STATUS );
		if ( in_array( $status, AOD_Carrier::tracking_terminal_statuses(), true ) ) {
			return false;
		}
		$at = (int) $order->get_meta( AOD_Carrier::META_STATUS_AT );
		return $at < ( time() - ( self::TRACK_BLOCKED_DAYS * DAY_IN_SECONDS ) );
	}

	/**
	 * Ton sémantique d'un état de suivi transporteur (composant `.aod-cd-pill`).
	 *
	 * Source unique de vérité : réutilisée par la page Suivi, la timeline et
	 * l'onglet Livraison de l'Accueil. Les libellés, eux, restent ceux de
	 * AOD_Carrier::tracking_status_meta() (on ne duplique pas la table).
	 *
	 * @param string $status Clé normalisée (cf. AOD_Carrier::ST_*).
	 * @return string ok|warn|bad|info|neutral
	 */
	public static function tracking_tone( $status ) {
		// 🔒 Sûre PAR CONSTRUCTION, pas par contexte (32.1). Cette méthode est
		// `public static` et ne portait aucune garde : elle n'était sûre que parce
		// que tous ses appelants actuels en ont une. Un appel écrit demain depuis
		// un chemin non gardé rouvrirait la fatale — et « tous les appelants sont
		// gardés » est une propriété qui se périme sans prévenir.
		if ( ! AOD_CD_Cod::active() ) {
			return 'neutral';
		}
		$map = array(
			AOD_Carrier::ST_DELIVERED        => 'ok',
			AOD_Carrier::ST_CREATED          => 'info',
			AOD_Carrier::ST_IN_TRANSIT       => 'info',
			AOD_Carrier::ST_OUT_FOR_DELIVERY => 'warn',
			AOD_Carrier::ST_FAILED           => 'bad',
			AOD_Carrier::ST_RETURNED         => 'bad',
			AOD_Carrier::ST_CANCELLED        => 'neutral',
			AOD_Carrier::ST_UNKNOWN          => 'neutral',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : 'neutral';
	}

	/**
	 * Pastille sémantique unifiée pour un état de suivi.
	 *
	 * @param string $status Clé normalisée (cf. AOD_Carrier::ST_*).
	 * @return string HTML sûr.
	 */
	public static function tracking_pill( $status ) {
		// 🔒 Même raison que `tracking_tone()` ci-dessus. Sans le socle COD il n'y a
		// pas d'état de colis à afficher : on ne rend rien plutôt que de mourir.
		if ( ! AOD_CD_Cod::active() ) {
			return '';
		}
		if ( '' === $status ) {
			$status = AOD_Carrier::ST_UNKNOWN;
		}
		$meta = AOD_Carrier::tracking_status_meta( $status );
		return '<span class="aod-cd-pill aod-cd-pill--' . esc_attr( self::tracking_tone( $status ) ) . '">' . esc_html( $meta['label'] ) . '</span>';
	}

	/**
	 * Pastille de l'état de suivi normalisé d'une commande.
	 *
	 * @param WC_Order $order
	 * @return string HTML sûr.
	 */
	protected function tracking_pill_html( $order ) {
		return self::tracking_pill( (string) $order->get_meta( AOD_Carrier::META_STATUS ) );
	}

	/**
	 * Date de dernière synchronisation du suivi, en langage humain.
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	protected function tracking_updated_at( $order ) {
		$at = (int) $order->get_meta( AOD_Carrier::META_STATUS_AT );
		if ( $at <= 0 ) {
			return __( 'Jamais', 'aod-client-dashboard' );
		}
		/* translators: %s : durée écoulée (ex. « 3 heures ») */
		return AOD_CD_Temps::depuis( $at );
	}

	/**
	 * Timeline de suivi (historique des événements stockés) pour la modale.
	 *
	 * @param WC_Order $order
	 * @return string HTML sûr.
	 */
	protected function render_tracking_timeline( $order ) {
		$cid     = (string) $order->get_meta( AOD_Carrier::META_CARRIER );
		$carrier = ( '' !== $cid ) ? AOD_Shipping::instance()->carrier( $cid ) : null;
		$cname   = $carrier ? $carrier->label() : $cid;
		$track   = (string) $order->get_meta( AOD_Carrier::META_TRACKING );
		$status  = (string) $order->get_meta( AOD_Carrier::META_STATUS );
		if ( '' === $status ) {
			$status = AOD_Carrier::ST_UNKNOWN;
		}
		$raw     = $order->get_meta( AOD_Carrier::META_STATUS_HISTORY );
		$events  = json_decode( (string) $raw, true );
		$events  = is_array( $events ) ? $events : array();

		$out  = '<div class="aod-cd-tl">';
		$out .= '<div class="aod-cd-tl-head">';
		$out .= '<div>' . esc_html__( 'Transporteur', 'aod-client-dashboard' ) . ' : <strong>' . esc_html( $cname ? $cname : '—' ) . '</strong></div>';
		if ( '' !== $track ) {
			$out .= '<div>' . esc_html__( 'N° de suivi', 'aod-client-dashboard' ) . ' : <code>' . esc_html( $track ) . '</code></div>';
		}
		$out .= '<div>' . esc_html__( 'État actuel', 'aod-client-dashboard' ) . ' : ' . self::tracking_pill( $status ) . '</div>';
		$out .= '<div class="aod-cd-tl-updated">' . esc_html( $this->tracking_updated_at( $order ) ) . '</div>';
		$out .= '</div>';

		if ( empty( $events ) ) {
			$msg = ( $carrier && ! $carrier->supports_tracking() )
				? __( 'Ce transporteur ne fournit pas de suivi détaillé. Consultez son espace pour le détail.', 'aod-client-dashboard' )
				: __( 'Aucun événement de suivi pour l’instant. Réessayez après la prochaine synchronisation.', 'aod-client-dashboard' );
			$out .= '<p class="aod-cd-muted">' . esc_html( $msg ) . '</p></div>';
			return $out;
		}

		$out .= '<ul class="aod-cd-tl-list">';
		foreach ( $events as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			$ekey   = isset( $e['status'] ) ? (string) $e['status'] : AOD_Carrier::ST_UNKNOWN;
			$emeta  = AOD_Carrier::tracking_status_meta( $ekey );
			$label  = isset( $e['label'] ) && '' !== $e['label'] ? (string) $e['label'] : $emeta['label'];
			$date   = isset( $e['date'] ) ? (string) $e['date'] : '';
			$loc    = isset( $e['location'] ) ? (string) $e['location'] : '';

			$out .= '<li class="aod-cd-tl-item">';
			$out .= '<span class="aod-cd-tl-dot" style="--dot:' . esc_attr( $emeta['color'] ) . '"></span>';
			$out .= '<div class="aod-cd-tl-body">';
			$out .= '<div class="aod-cd-tl-label">' . esc_html( $label ) . '</div>';
			$meta_line = array();
			if ( '' !== $date ) {
				$meta_line[] = esc_html( $date );
			}
			if ( '' !== $loc ) {
				$meta_line[] = esc_html( $loc );
			}
			if ( $meta_line ) {
				$out .= '<div class="aod-cd-tl-meta">' . implode( ' · ', $meta_line ) . '</div>';
			}
			$out .= '</div></li>';
		}
		$out .= '</ul></div>';
		return $out;
	}

	/* ============================================================
	 * AJAX : suivi commande (synchronisation + timeline)
	 * ========================================================== */

	/**
	 * Actualise le suivi : une commande précise (order_id fourni) ou tout un lot.
	 */
	public function ajax_tracking_refresh() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_tracking' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_Ship_Tracking' ) ) {
			wp_send_json_error( array( 'message' => __( 'Le module de suivi n’est pas actif.', 'aod-client-dashboard' ) ) );
		}

		$svc      = AOD_Ship_Tracking::instance();
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( $order_id ) {
			// Garde de périmètre : un confirmateur ne peut actualiser que les
			// commandes de ses produits affectés (parité avec ajax_order_detail).
			$target = wc_get_order( $order_id );
			if ( ! $target instanceof WC_Order || ! AOD_CD_Section_Orders::instance()->order_in_scope( $target ) ) {
				wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
			}
			$res = $svc->sync_order( $order_id );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			$order = wc_get_order( $order_id );
			wp_send_json_success( array(
				'message' => __( 'Suivi mis à jour.', 'aod-client-dashboard' ),
				'pill'    => $order instanceof WC_Order ? $this->tracking_pill_html( $order ) : '',
				'at'      => $order instanceof WC_Order ? $this->tracking_updated_at( $order ) : '',
			) );
		}

		// « Actualiser tout » PAR TRANCHES (32.4, D23). L'ancien appel synchrone à
		// run_sync() — jusqu'à 1200 commandes et 30 HTTP séquentiels dans UNE
		// requête — mourait sur le timeout PHP/proxy ; le gérant lisait « Erreur
		// réseau » et recliquait, relançant tout depuis zéro. Chaque requête ne
		// traite plus que ce qu'elle tient en ~10 s, puis `dashboard.js` enchaîne.
		// `since` (l'instant du clic) sert de curseur : le déjà-synchronisé sort
		// des candidates, chaque tranche avance.
		$since = isset( $_POST['since'] ) ? absint( $_POST['since'] ) : 0;
		$done  = isset( $_POST['done'] ) ? absint( $_POST['done'] ) : 0;
		$errs  = isset( $_POST['errs'] ) ? absint( $_POST['errs'] ) : 0;
		if ( ! $since ) {
			$since = time();
		}

		$r     = $svc->run_sync_batch( AOD_Ship_Tracking::AJAX_BUDGET_S, $since );
		$done += (int) $r['updated'];
		$errs += (int) $r['errors'];

		// Tant qu'une tranche PROGRESSE et qu'il reste du travail, on continue.
		// Une tranche sans aucun progrès (tout ce qui reste est en erreur) doit
		// conclure — sinon la boucle rejouerait les mêmes échecs à l'infini.
		if ( $r['remaining'] > 0 && $r['updated'] > 0 ) {
			wp_send_json_success( array(
				'partial'   => true,
				'since'     => $since,
				'done'      => $done,
				'errs'      => $errs,
				'remaining' => (int) $r['remaining'],
			) );
		}

		/* translators: %d : nombre de commandes synchronisées */
		$message = sprintf( _n( '%d commande synchronisée.', '%d commandes synchronisées.', $done, 'aod-client-dashboard' ), $done );
		if ( $errs > 0 ) {
			/* translators: %d : nombre de colis dont le transporteur n'a pas répondu */
			$message .= ' ' . sprintf( _n( '%d colis n’a pas répondu — nouvel essai au prochain passage automatique.', '%d colis n’ont pas répondu — nouvel essai au prochain passage automatique.', $errs, 'aod-client-dashboard' ), $errs );
		}
		wp_send_json_success( array(
			'message' => $message,
			'reload'  => true,
		) );
	}

	/**
	 * Renvoie la timeline de suivi d'une commande (HTML) pour la modale.
	 */
	public function ajax_tracking_detail() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_tracking' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_Carrier' ) ) {
			wp_send_json_error( array( 'message' => __( 'Le module de livraison n’est pas actif.', 'aod-client-dashboard' ) ) );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Commande introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		// Garde de périmètre : ne pas divulguer le suivi d'une commande hors du
		// périmètre produits du confirmateur (parité avec ajax_order_detail).
		if ( ! AOD_CD_Section_Orders::instance()->order_in_scope( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		ob_start();
		echo $this->render_tracking_timeline( $order ); // phpcs:ignore WordPress.Security.EscapeOutput
		$html = ob_get_clean();

		wp_send_json_success( array(
			'title' => sprintf( __( 'Suivi — commande #%s', 'aod-client-dashboard' ), $order->get_order_number() ),
			'html'  => $html,
		) );
	}
}
