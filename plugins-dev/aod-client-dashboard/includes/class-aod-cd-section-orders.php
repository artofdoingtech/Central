<?php
/**
 * Section « Commandes » : liste filtrable par statut avec compteurs, recherche,
 * détail en modale (édition infos client / articles / total / note au livreur),
 * suppression groupée — et le PÉRIMÈTRE PRODUITS d'un confirmateur (étape 1.5),
 * la logique de toutes les listes de commandes, désormais chez elle.
 *
 * 🧩 EXTRACTION (Phase 30.6 — la dernière : le pattern AOD_CD_Section_* est
 * fini, le Dashboard n'est plus qu'une coque + routeur + composants partagés).
 * Sortie du monolithe `class-aod-cd-dashboard.php` à comportement CONSTANT :
 * mêmes actions AJAX (`aod_cd_order_status`, `aod_cd_order_detail`,
 * `aod_cd_order_note`, `aod_cd_order_save_info`, `aod_cd_delete_orders`) —
 * `dashboard.js` inchangé —, même rendu.
 * 🔁 Cette liste décrit la 30.6 à SA date et reste vraie comme telle :
 * `aod_cd_order_note` a été supprimée depuis (47.3). Ne pas la relire comme
 * l'inventaire courant des handlers de la classe — c'est le constructeur qui fait foi. Move verbatim ; seuls les helpers d'UI
 * partagés (`page_head`, `empty_state`, `render_search_bar`,
 * `render_pagination`) deviennent `AOD_CD_Dashboard::…`. Les helpers de
 * périmètre (`scoped_orders()`, `order_in_scope()`,
 * `product_orders_join_sql()`) suivent la section, comme la 30.5 l'annonçait ;
 * leurs appelants externes (`AOD_CD_Section_Tracking`, `AOD_CD_Section_Home`)
 * ont suivi la classe. `reduce_pack_components_stock()` suit aussi : hook de
 * CYCLE DE VIE (`woocommerce_order_status_*`), armé au boot — il agit quel que
 * soit l'écran (ou le cron) d'où vient le changement de statut, pas seulement
 * depuis cette page.
 *
 * ⚠️ Ne pas confondre avec `AOD_CD_Orders` (parcours de commandes par LOTS sans
 * les tenir en mémoire, Phase 31.6), chargée à côté et sans rapport — même
 * piège de nommage que `AOD_CD_Account` ⇄ `AOD_CD_Section_Account` (30.3).
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Orders {

	/**
	 * Slug de l'onglet de repli « Autres » (statuts hors cycle de vie COD).
	 *
	 * Ce n'est PAS un statut WooCommerce : `status_keys_for()` le détend en la
	 * liste des statuts résiduels. Le mot est volontairement hors du jeu des
	 * slugs de statut (`sanitize_key()` d'un statut Woo ne produit jamais
	 * « autres ») pour qu'aucune collision ne soit possible.
	 */
	const TAB_OTHER = 'autres';

	/** @var AOD_CD_Section_Orders|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Actions AJAX de la section (mêmes noms qu'avant la 30.6 : dashboard.js inchangé).
		add_action( 'wp_ajax_aod_cd_order_status', array( $this, 'ajax_order_status' ) );
		add_action( 'wp_ajax_aod_cd_export_orders', array( $this, 'ajax_export_orders' ) );
		add_action( 'wp_ajax_aod_cd_order_detail', array( $this, 'ajax_order_detail' ) );
		// 47.3 — `aod_cd_order_note` a disparu (cf. le bloc qui remplace le handler,
		// plus bas). La note du confirmateur s'enregistre avec le formulaire.
		add_action( 'wp_ajax_aod_cd_order_save_info', array( $this, 'ajax_order_save_info' ) );
		add_action( 'wp_ajax_aod_cd_delete_orders', array( $this, 'ajax_delete_orders' ) );
		add_action( 'wp_ajax_aod_cd_order_assign', array( $this, 'ajax_order_assign' ) );
		add_action( 'wp_ajax_aod_cd_orders_bulk_assign', array( $this, 'ajax_orders_bulk_assign' ) );
		add_action( 'wp_ajax_aod_cd_order_carrier', array( $this, 'ajax_order_carrier' ) );
		add_action( 'wp_ajax_aod_cd_orders_bulk_carrier', array( $this, 'ajax_orders_bulk_carrier' ) );
		add_action( 'wp_ajax_aod_cd_ship_send', array( $this, 'ajax_ship_send' ) );

		// Pack assortiment : décrémente le stock des composants quand la commande est prise.
		add_action( 'woocommerce_order_status_processing', array( $this, 'reduce_pack_components_stock' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'reduce_pack_components_stock' ) );

		// Stock par variante (40.5) : même geste que le pack, autre donnée, autres statuts.
		// Décrément à la CONFIRMATION (D3 — jamais à la création : un abandon/bot ne vide
		// pas le stock). `completed` en second, défensif et idempotent (drapeau) : couvre
		// un chemin qui atteindrait « Terminée » sans passer par « Confirmée ».
		add_action( 'woocommerce_order_status_aod-confirmed', array( $this, 'reduce_variant_stock' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'reduce_variant_stock' ) );
		// Ré-incrément à l'ANNULATION / au REMBOURSEMENT (D4), idempotent.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'restore_variant_stock' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'restore_variant_stock' ) );

		// Assignation des commandes aux confirmateurs (Phase 42.1) : tourniquet par
		// charge, UNIQUEMENT pour les commandes du formulaire public. La conversion
		// d'un abandon (Phase 39, AOD_CD_Section_Abandoned::build_skeleton_order())
		// ne déclenche PAS ce hook — elle assigne directement au confirmateur qui
		// convertit (42.2), jamais au tourniquet : il a déjà pris possession du
		// prospect en le rappelant.
		add_action( 'aod_cod_order_created', array( $this, 'assign_least_loaded' ) );
	}

	/* ============================================================
	 * Rendu de la page
	 * ========================================================== */

	public function render() {
		$current = isset( $_GET['statut'] ) ? sanitize_key( wp_unslash( $_GET['statut'] ) ) : 'all';
		// Filtre « Non assignées » (Phase 42.5) — GÉRANT uniquement : un
		// confirmateur ne voit de toute façon que ses propres commandes
		// assignées depuis 42.3, ce filtre n'a de sens que pour qui voit tout.
		$can_assign     = current_user_can( 'manage_woocommerce' );
		$non_assignees  = $can_assign && isset( $_GET['non_assignees'] ) && '1' === $_GET['non_assignees'];

		// Onglets de filtrage par statut (avec compteurs) — le cycle de vie COD,
		// dans l'ordre où une commande le parcourt.
		//
		// ⚠️ « En cours » (wc-processing) a manqué à cette liste jusqu'au
		// 2026-08-15, et c'est le statut d'une commande COD FRAÎCHEMENT PASSÉE :
		// sur un clone actif, 19 commandes sur 21 étaient invisibles depuis les
		// onglets — joignables uniquement en forgeant `?statut=processing`, qui
		// fonctionnait déjà parfaitement. Le tableau les affichait bien sous
		// « Toutes », mais la somme des onglets nommés valait 2 sur 21 : un
		// gérant qui clique onglet par onglet en concluait que sa boutique était
		// vide. Ne pas retirer cette entrée.
		$tabs = array(
			'all'           => __( 'Toutes', 'aod-client-dashboard' ),
			'pending'       => __( 'En attente', 'aod-client-dashboard' ),
			'processing'    => __( 'En cours', 'aod-client-dashboard' ),
			'aod-confirmed' => __( 'Confirmées', 'aod-client-dashboard' ),
			'completed'     => __( 'Terminées', 'aod-client-dashboard' ),
			'cancelled'     => __( 'Annulées', 'aod-client-dashboard' ),
		);

		// Les statuts hors cycle de vie (En pause, Remboursée, Échouée,
		// Brouillon…) tiennent dans UN onglet de repli, affiché seulement s'il
		// compte quelque chose. Sans lui, une commande remboursée resterait
		// hors de portée des onglets : le défaut ci-dessus se reproduirait à
		// l'identique, en plus discret. Un seul compteur de plus, pas un par
		// statut résiduel — ces requêtes portent un meta_query (exclusion des
		// expédiées), elles ne sont pas gratuites.
		if ( $this->residual_status_keys() ) {
			$tabs[ self::TAB_OTHER ] = __( 'Autres', 'aod-client-dashboard' );
		}

		// Compteurs (calculés une seule fois, réutilisés par le bouton et le menu).
		$counts = array();
		foreach ( $tabs as $slug => $label ) {
			$counts[ $slug ] = (int) $this->count_orders( $slug );
		}

		// Onglet de repli vide = onglet muet : on le retire plutôt que d'afficher
		// « Autres 0 » sur la quasi-totalité des clones.
		if ( isset( $counts[ self::TAB_OTHER ] ) && 0 === $counts[ self::TAB_OTHER ] && self::TAB_OTHER !== $current ) {
			unset( $tabs[ self::TAB_OTHER ], $counts[ self::TAB_OTHER ] );
		}

		$query_status = $this->status_keys_for( $current );

		// Compteur du filtre « Non assignées » — sur le statut courant, pour
		// rester cohérent avec les onglets déjà affichés (même patron que
		// $counts ci-dessus).
		$unassigned_count = $can_assign ? $this->count_unassigned( $query_status ) : 0;

		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 20;
		$base_url = home_url( '/' . AOD_CD_SLUG . '/orders' );

		// Filtre par date (Phase 43.1) — pas gérant-only : lecture pure sur des
		// commandes déjà visibles par l'utilisateur courant (cf. story, AC 5).
		$date_from = AOD_CD_Dashboard::parse_date_param( 'date_from' );
		$date_to   = AOD_CD_Dashboard::parse_date_param( 'date_to' );
		$range     = AOD_CD_Dashboard::resolve_date_range( $date_from, $date_to );

		// Filtre par confirmateur (43.2) — GÉRANT UNIQUEMENT : voir la liste des
		// confirmateurs par nom est l'information que assigned_confirmer_label()/
		// assign_select_html() protègent déjà (« un confirmateur ne doit jamais
		// voir le nom d'un assigné »). Confiance zéro : un ID hors du vivier
		// éligible est traité comme absent, jamais une erreur.
		$confirmers       = $can_assign ? AOD_CD_Roles::eligible_confirmers( array(), true ) : array();
		$confirmer_filter = 0;
		if ( $can_assign && isset( $_GET['confirmateur'] ) ) {
			$posted_confirmer = absint( $_GET['confirmateur'] );
			if ( in_array( $posted_confirmer, wp_list_pluck( $confirmers, 'ID' ), true ) ) {
				$confirmer_filter = $posted_confirmer;
			}
		}

		// Export CSV : un LIEN, pas un bouton AJAX — le navigateur doit recevoir un
		// téléchargement, ce qu'un `fetch()` ne déclenche pas sans passer par un blob.
		// Le nonce `aod_cd` (celui de toute la section) voyage donc dans l'URL sous la
		// clé `nonce` : la vérification côté serveur la lit dans `$_REQUEST`, donc en
		// GET aussi. ⚠️ Ne PAS nommer ici la fonction de vérification : le cliquet D15
		// (`test-nonce-contract.php`) cherche son nom dans le corps de la méthode SANS
		// dépouiller les commentaires — la citer suffirait à faire passer `render()`
		// pour noncée, et à éteindre la garde qui surveille ses lectures de `$_GET`.
		// Le filtre de statut courant est repris : on exporte CE QU'ON REGARDE, sinon
		// le gérant qui a filtré « Confirmées » reçoit tout et croit le filtre ignoré.
		$export_url = add_query_arg(
			array(
				'action' => 'aod_cd_export_orders',
				'statut' => $current,
				'nonce'  => wp_create_nonce( 'aod_cd' ),
			),
			admin_url( 'admin-ajax.php' )
		);
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Commandes', 'aod-client-dashboard' ),
			__( 'Suivez, confirmez et expédiez les commandes COD.', 'aod-client-dashboard' ),
			'<a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $export_url ) . '">'
				. esc_html__( 'Exporter en CSV', 'aod-client-dashboard' ) . '</a>'
		);

		// Filtre de statut : segmented control (remplace l'ancien menu déroulant).
		// Navigation en GET, paramètre `statut` inchangé ; la recherche est
		// conservée, la pagination est réinitialisée.
		echo '<div class="aod-cd-toolbar">';
		echo '<div class="aod-cd-seg" role="group" aria-label="' . esc_attr__( 'Filtrer par statut', 'aod-client-dashboard' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			$keep = array( 'statut' => $slug );
			if ( '' !== $search ) {
				$keep['s'] = $search;
			}
			if ( '' !== $range['from'] ) {
				$keep['date_from'] = $range['from'];
			}
			if ( '' !== $range['to'] ) {
				$keep['date_to'] = $range['to'];
			}
			if ( $confirmer_filter ) {
				$keep['confirmateur'] = $confirmer_filter;
			}
			printf(
				'<a class="aod-cd-seg__btn%1$s" href="%2$s"%3$s>%4$s<span class="aod-cd-seg__count">%5$d</span></a>',
				$slug === $current ? ' is-active' : '',
				esc_url( add_query_arg( $keep, $base_url ) ),
				$slug === $current ? ' aria-current="true"' : '',
				esc_html( $label ),
				(int) $counts[ $slug ]
			);
		}
		echo '</div>';

		// Recherche (n° de commande, nom ou téléphone du client).
		AOD_CD_Dashboard::render_search_bar( $base_url, $search, __( 'Rechercher : n°, nom, téléphone…', 'aod-client-dashboard' ), array( 'statut' => $current, 'date_from' => $range['from'], 'date_to' => $range['to'], 'confirmateur' => $confirmer_filter ? $confirmer_filter : '' ) );
		echo '</div>';

		// Filtre par date (43.1) + confirmateur (43.2) — formulaire GET distinct
		// de la recherche, même patron que Suivi (render_tracking_filters()) :
		// champs cachés pour préserver les autres filtres déjà actifs à la
		// soumission.
		echo '<form class="aod-cd-form aod-cd-filters" method="get" action="' . esc_url( $base_url ) . '">';
		echo '<input type="hidden" name="statut" value="' . esc_attr( $current ) . '">';
		if ( '' !== $search ) {
			echo '<input type="hidden" name="s" value="' . esc_attr( $search ) . '">';
		}
		if ( $non_assignees ) {
			echo '<input type="hidden" name="non_assignees" value="1">';
		}
		AOD_CD_Dashboard::render_date_fields( $range['from'], $range['to'] );
		if ( $can_assign ) {
			echo '<div class="aod-cd-field"><label for="aod-cd-f-confirmer">' . esc_html__( 'Confirmateur', 'aod-client-dashboard' ) . '</label>';
			// `data-aod-select` AJOUTÉ LE 2026-08-06. Ce filtre ouvrait la liste du
			// SYSTÈME pendant que le MÊME filtre, sur l'Accueil, ouvrait le composant
			// maison — la correction du 2026-08-05 s'était arrêtée à la page qu'on
			// regardait. Or le composant existe pour une raison mesurée, écrite dans
			// `bindSelects()` : le `<select>` natif s'ouvre hors champ sur mobile,
			// c'est-à-dire sur la majorité du trafic. Même barre, même geste, même
			// widget — et les cinq filtres des autres sections suivent.
			echo '<select id="aod-cd-f-confirmer" name="confirmateur" data-aod-select>';
			echo '<option value="">' . esc_html__( 'Tous les confirmateurs', 'aod-client-dashboard' ) . '</option>';
			foreach ( $confirmers as $confirmer ) {
				echo '<option value="' . esc_attr( $confirmer->ID ) . '" ' . selected( $confirmer_filter, $confirmer->ID, false ) . '>' . esc_html( $confirmer->display_name ) . '</option>';
			}
			echo '</select></div>';
		}
		echo '<div class="aod-cd-field aod-cd-filters-actions">';
		echo '<button type="submit" class="aod-cd-btn aod-cd-btn-primary">' . esc_html__( 'Filtrer', 'aod-client-dashboard' ) . '</button>';
		if ( '' !== $range['from'] || '' !== $range['to'] || $confirmer_filter ) {
			echo '<a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $base_url ) . '">' . esc_html__( 'Réinitialiser', 'aod-client-dashboard' ) . '</a>';
		}
		echo '</div>';
		echo '</form>';

		// Filtre « Non assignées » (42.5) — GÉRANT uniquement, pour rattraper
		// l'historique (D4 : une commande non assignée est invisible aux
		// confirmateurs tant qu'elle n'est pas assignée).
		if ( $can_assign ) {
			$toggle_args = array( 'statut' => $current );
			if ( '' !== $search ) {
				$toggle_args['s'] = $search;
			}
			if ( '' !== $range['from'] ) {
				$toggle_args['date_from'] = $range['from'];
			}
			if ( '' !== $range['to'] ) {
				$toggle_args['date_to'] = $range['to'];
			}
			if ( $confirmer_filter ) {
				$toggle_args['confirmateur'] = $confirmer_filter;
			}
			if ( $non_assignees ) {
				echo '<div class="aod-cd-toolbar">';
				printf(
					'<a class="aod-cd-seg__btn aod-cd-unassigned-toggle is-active" href="%1$s" aria-current="true">%2$s<span class="aod-cd-seg__count">%3$d</span></a>',
					esc_url( add_query_arg( $toggle_args, $base_url ) ),
					esc_html__( 'Non assignées', 'aod-client-dashboard' ),
					(int) $unassigned_count
				);
				echo '</div>';
			} else {
				$toggle_args['non_assignees'] = 1;
				echo '<div class="aod-cd-toolbar">';
				printf(
					'<a class="aod-cd-seg__btn aod-cd-unassigned-toggle" href="%1$s">%2$s<span class="aod-cd-seg__count">%3$d</span></a>',
					esc_url( add_query_arg( $toggle_args, $base_url ) ),
					esc_html__( 'Non assignées', 'aod-client-dashboard' ),
					(int) $unassigned_count
				);
				echo '</div>';
			}
		}

		$args = array(
			'type'     => 'shop_order', // Exclut les remboursements (shop_order_refund) du listing.
			'limit'    => $per_page,
			'paged'    => $paged,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'status'   => $query_status,
		);
		// Étape 12.1 : une commande expédiée quitte « Commandes » pour « Suivi ».
		$exclude_shipped = $this->unshipped_meta_query();
		if ( $exclude_shipped ) {
			$args['meta_query'] = $exclude_shipped; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		// Filtre « Non assignées » (42.5) — fusionné en AND, jamais un
		// écrasement (même patron que le cumul d'assignation en lecture, 42.3).
		if ( $non_assignees ) {
			$unassigned_clause = array( 'key' => '_aod_assigned_confirmer', 'compare' => 'NOT EXISTS' );
			$args['meta_query'] = ! empty( $args['meta_query'] ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtre gérant only (rattrapage 42.5), fusionné AND avec un éventuel filtre déjà présent.
				? array( 'relation' => 'AND', $args['meta_query'], $unassigned_clause )
				: array( $unassigned_clause );
		}
		// Filtre par confirmateur (43.2) — même patron de fusion AND. Combinable
		// avec « Non assignées » sans garde spéciale : les deux ensemble rendent
		// un résultat vide, honnêtement (cf. story, AC 5).
		if ( $confirmer_filter ) {
			$confirmer_clause   = array( 'key' => '_aod_assigned_confirmer', 'value' => $confirmer_filter );
			$args['meta_query'] = ! empty( $args['meta_query'] ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtre gérant only, fusionné AND avec un éventuel filtre déjà présent.
				? array( 'relation' => 'AND', $args['meta_query'], $confirmer_clause )
				: array( $confirmer_clause );
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		// Filtre par date (43.1) — seulement si au moins une borne est posée
		// (jamais une chaîne vide dans $args, cf. story AC 2).
		$date_created = AOD_CD_Dashboard::date_created_query_arg( $range['from'], $range['to'] );
		if ( null !== $date_created ) {
			$args['date_created'] = $date_created;
		}
		$result = $this->scoped_orders( $args );
		$orders = $result->orders;

		if ( empty( $orders ) ) {
			echo AOD_CD_Dashboard::empty_state( 'orders', __( 'Aucune commande dans cette catégorie.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$status_options = $this->status_labels(); // Libellés de statut forcés en français.

		// Suppression groupée : disponible seulement si l'utilisateur peut supprimer
		// des commandes (le gérant possède delete_shop_orders).
		$can_delete = current_user_can( 'delete_shop_orders' ) || current_user_can( 'manage_woocommerce' );

		// Choix du transporteur (47.1) : GÉRANT **ET** CONFIRMATEUR — et cette
		// divergence avec `$can_assign` est délibérée, pas un oubli.
		// `$can_assign` porte un pouvoir gérant STRICT (42.4/43.x) : assigner,
		// c'est RÉPARTIR du travail, et un confirmateur ne doit pas se
		// distribuer le sien. Choisir un transporteur, c'est FAIRE le travail —
		// et le confirmateur déclenchera l'envoi (47.2) : il ne peut pas
		// déclencher un envoi dont il ne choisit pas la destination.
		// ⚠️ Ne PAS réutiliser `$can_assign` ici : la variable distincte EST la
		// décision.
		$can_carrier = AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' );

		if ( $can_delete || $can_assign ) {
			// 45.2 — la barre est rendue EN PERMANENCE (plus d'attribut `hidden`).
			// Ce qui protège d'une action à vide n'est pas l'invisibilité du bouton
			// mais l'impossibilité d'agir : les actions portent `disabled` au rendu,
			// `sync()` les arme dès qu'une ligne est cochée. `is-vide` est l'état de
			// départ — aucune ligne n'est cochée au chargement d'une page.
			echo '<div class="aod-cd-bulkbar is-vide" id="aod-cd-order-bulk">';
			echo '<span class="aod-cd-bulkbar-count"><strong class="aod-cd-bulkbar-n">0</strong> ' . esc_html__( 'sélectionnée(s)', 'aod-client-dashboard' ) . '</span>';
			if ( $can_assign ) {
				// Rattrapage groupé (42.5) : <select> NATIF — cette barre est un
				// bandeau pleine largeur, HORS du tableau défilant, donc aucun
				// risque d'ouverture hors champ (contrairement à
				// status_select_html()/assign_select_html(), nécessaires
				// uniquement à cause de l'overflow d'une cellule de tableau).
				// 🚨 45.2 — `aria-label` : ce <select> n'a JAMAIS eu de nom
				// accessible. La porte `noms` ne l'avait jamais vu parce que la
				// barre était masquée au repos — elle l'a trouvé à la seconde où
				// la barre est devenue permanente. Un défaut d'accessibilité ne
				// naît pas d'un changement de peau : il devient VISIBLE.
				// Sa première option (« Assigner à… ») n'est pas un nom : un
				// lecteur d'écran annonce le libellé du contrôle, pas sa valeur.
				// 🔁 2026-08-08 — LES GESTES SONT REGROUPÉS (demande owner : « sépare-moi
				// la moitié »). La barre alignait jusqu'ici huit contrôles d'affilée, à
				// écart constant : rien ne disait que le sélecteur, la case « Écraser » et
				// le bouton « Assigner » forment UN geste, ni que « Transporteur »,
				// « Router » et « Envoyer » en forment un autre. À écart égal, l'œil lit
				// huit objets indépendants — la proximité est ce qui fait le groupe, et il
				// n'y en avait aucune.
				// ⚠️ Le JS atteint chaque contrôle par `bar.querySelector( '.classe' )`
				// (vérifié, onze occurrences) : ce sont des recherches par DESCENDANCE,
				// donc envelopper ne casse aucune liaison. Si un jour l'une d'elles passe
				// à `children` ou à `nextElementSibling`, elle cassera ici — d'où cette note.
				echo '<span class="aod-cd-bulkgroup">';
				echo '<select class="aod-cd-order-bulk-assign-select" data-aod-select aria-label="' . esc_attr__( 'Assigner la sélection à un confirmateur', 'aod-client-dashboard' ) . '">';
				echo '<option value="">' . esc_html__( 'Assigner à…', 'aod-client-dashboard' ) . '</option>';
				foreach ( AOD_CD_Roles::eligible_confirmers( array(), true ) as $confirmer ) {
					echo '<option value="' . esc_attr( $confirmer->ID ) . '">' . esc_html( $confirmer->display_name ) . '</option>';
				}
				echo '</select>';
				// Écrasement explicite (43.5, décision D1 de A.20). Case NATIVE,
				// DÉCOCHÉE à chaque rendu, jamais mémorisée : l'outil comble des
				// absences par défaut, et ne réassigne en masse que si le gérant
				// le demande, à ce moment-là, et le voit. Un défaut caché
				// réintroduirait exactement le risque que la 42.5 avait écarté.
				// `<label for>` explicite plutôt qu'un widget maison : la barre est
				// un bandeau pleine largeur (aucun problème de clipping), et un
				// contrôle natif est clavier + lecteur d'écran par construction.
				echo '<span class="aod-cd-bulk-ow">';
				echo '<input type="checkbox" class="aod-cd-order-bulk-overwrite" id="aod-cd-order-bulk-ow">';
				echo '<label for="aod-cd-order-bulk-ow">' . esc_html__( 'Écraser les assignations existantes', 'aod-client-dashboard' ) . '</label>';
				echo '</span>';
				// 🔁 2026-08-08 (soir) — le bouton qui CONCLUT le groupe prend l'accent.
				// Chaque panneau de la barre se lit désormais « je choisis, puis je
				// valide » : sans bouton principal, les trois contrôles d'un groupe
				// avaient exactement le même poids et rien ne disait lequel déclenche.
				// Les deux finisseurs (celui-ci et « Envoyer au transporteur ») vivent
				// dans des panneaux DIFFÉRENTS : ils ne se disputent pas le regard.
				echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-btn-primary aod-cd-order-bulk-assign aod-cd-bulk-act" disabled>'
					. esc_html__( 'Assigner', 'aod-client-dashboard' )
					. '</button>';
				echo '</span>';
			}
			// Routage groupé (47.1). `<select>` NATIF, comme celui de
			// l'assignation juste au-dessus, et pour la même raison : ce bandeau
			// est pleine largeur, HORS du tableau défilant — le clipping qui
			// impose un menu maison dans une cellule n'existe pas ici.
			// 🚨 `aria-label` dès l'écriture : la leçon de la 45.2 est que le
			// <select> voisin a vécu sans nom accessible parce que la barre était
			// masquée au repos, donc invisible pour la porte `noms`. Elle est
			// permanente désormais — un contrôle nu serait vu, mais autant ne pas
			// le poser. Sa première option est une INVITE, jamais un nom.
			// ⚠️ Le contrôle vit dans une barre rendue seulement si
			// `$can_delete || $can_assign` : un confirmateur n'a pas de cases à
			// cocher (colonne réservée à `$can_delete`), donc pas de sélection
			// possible. Le geste unitaire, lui, LUI est ouvert — c'est le
			// périmètre voulu, et le handler reste gardé par la cap, pas par le
			// rendu.
			// Groupe TRANSPORT — il enveloppe les deux gestes de livraison : choisir le
			// transporteur (47.1) puis déclencher l'envoi (47.2). Ils étaient rendus par
			// deux `if` consécutifs, ce qui était juste au sens des permissions mais les
			// laissait visuellement aussi éloignés l'un de l'autre que du reste.
			if ( $can_carrier ) {
				echo '<span class="aod-cd-bulkgroup">';
			}
			if ( $can_carrier && class_exists( 'AOD_Shipping' ) ) {
				$bulk_carriers = AOD_Shipping::instance()->configured();
				if ( ! empty( $bulk_carriers ) ) {
					echo '<select class="aod-cd-order-bulk-carrier-select" data-aod-select aria-label="' . esc_attr__( 'Router la sélection vers un transporteur', 'aod-client-dashboard' ) . '">';
					echo '<option value="">' . esc_html__( 'Transporteur…', 'aod-client-dashboard' ) . '</option>';
					foreach ( $bulk_carriers as $bulk_cid => $bulk_carrier ) {
						echo '<option value="' . esc_attr( $bulk_cid ) . '">' . esc_html( $bulk_carrier->label() ) . '</option>';
					}
					echo '</select>';
					echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-order-bulk-carrier aod-cd-bulk-act" disabled>'
						. esc_html__( 'Router', 'aod-client-dashboard' )
						. '</button>';
				}
			}
			// Envoi groupé (47.2). Un seul bouton, pas de `<select>` : le
			// transporteur de chaque commande est DÉJÀ décidé (47.1) — le
			// redemander ici écraserait des choix ligne à ligne sans le dire.
			// La fenêtre de confirmation, elle, NOMME le transporteur de chacune.
			if ( $can_carrier ) {
				// Finisseur du panneau TRANSPORT (cf. la note sur « Assigner »).
				echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-btn-primary aod-cd-order-bulk-ship aod-cd-bulk-act" disabled>'
					. '<span class="aod-cd-bulk-ico" aria-hidden="true">' . AOD_CD_Icon::svg( 'shipping', array( 'size' => 16 ) ) . '</span> '
					. esc_html__( 'Envoyer au transporteur', 'aod-client-dashboard' )
					. '</button>';
			}
			if ( $can_carrier ) {
				echo '</span>';
			}
			if ( $can_delete ) {
				echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-order-bulk-del aod-cd-bulk-act" disabled>'
					. '<span class="aod-cd-bulk-ico" aria-hidden="true">' . AOD_CD_Icon::svg( 'trash', array( 'size' => 16 ) ) . '</span> '
					. esc_html__( 'Supprimer', 'aod-client-dashboard' )
					. ' <span class="aod-cd-bulkbar-btnn">(0)</span></button>';
			}
			echo '</div>';
		}

		echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards"><thead><tr>';
		if ( $can_delete ) {
			echo '<th class="aod-cd-col-check"><label class="aod-cd-checkwrap"><input type="checkbox" class="aod-cd-ordercheck-all" title="' . esc_attr__( 'Tout sélectionner', 'aod-client-dashboard' ) . '"></label></th>';
		}
		// Tableau priorisé (étape 5.4) : 7 colonnes au lieu de 13. Les colonnes
		// secondaires (date, commune, articles, prix produit, prix livraison) sont
		// toutes lisibles dans la modale de détail — aucune donnée n'est perdue.
		// « Assigné à » (42.7) rejoint ce tableau seulement pour le gérant : un
		// confirmateur n'a ni le sélecteur ni le nom de l'assigné dans la modale
		// (cf. render_order_detail) — la colonne suit la même règle.
		// ⚠️ Chaque en-tête est un COUPLE libellé / classe depuis le 2026-08-08. La
		// classe n'est pas décorative : la colonne des montants s'aligne sur sa fin
		// (cf. `.aod-cd-cell-total` dans dashboard.css), et un en-tête resté à gauche
		// au-dessus de nombres calés à droite serait pire que l'alignement d'origine.
		// Les autres colonnes gardent une classe vide plutôt qu'une liste à deux
		// formes : un `foreach` qui doit deviner s'il tient une chaîne ou un couple
		// est un piège pour la colonne qu'on ajoutera ensuite.
		$headers = array(
			array( __( 'N°', 'aod-client-dashboard' ), '' ),
			array( __( 'Client', 'aod-client-dashboard' ), '' ),
			array( __( 'Wilaya', 'aod-client-dashboard' ), '' ),
			array( __( 'Total', 'aod-client-dashboard' ), 'aod-cd-th-total' ),
			array( __( 'Statut', 'aod-client-dashboard' ), '' ),
		);
		// « Transporteur » (47.1) AVANT « Suivi » : on choisit la destination,
		// puis on regarde où en est le colis. L'inverse mettrait la décision
		// après son résultat.
		if ( $can_carrier ) {
			$headers[] = array( __( 'Transporteur', 'aod-client-dashboard' ), '' );
		}
		$headers[] = array( __( 'Suivi', 'aod-client-dashboard' ), '' );
		if ( $can_assign ) {
			$headers[] = array( __( 'Assigné à', 'aod-client-dashboard' ), '' );
		}
		// 47.3 — la colonne « Note » a disparu d'ici. Elle portait un bouton par
		// ligne qui ouvrait une invite et écrivait dans l'HISTORIQUE WooCommerce —
		// une liste append-only mêlée aux notes système, dont RIEN ne part chez le
		// transporteur. La note du confirmateur a désormais une destination : elle
		// vit dans le volet d'édition de la modale et voyage avec le colis.
		foreach ( $headers as $h ) {
			echo '<th' . ( '' !== $h[1] ? ' class="' . esc_attr( $h[1] ) . '"' : '' ) . '>' . esc_html( $h[0] ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $orders as $order ) {
			// Filet de sécurité : ne jamais tenter de rendre autre chose qu'une
			// vraie commande. Un remboursement (WC_Order_Refund) qui se glisserait
			// dans le résultat n'a pas get_billing_state() et provoquerait une
			// erreur fatale qui couperait tout le tableau (et la pagination).
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$this->render_order_row( $order, $status_options, $can_delete, $can_assign, $can_carrier );
		}
		echo '</tbody></table></div>';

		AOD_CD_Dashboard::render_pagination( $base_url, $paged, (int) $result->max_num_pages, array( 'statut' => $current, 's' => $search, 'date_from' => $range['from'], 'date_to' => $range['to'], 'confirmateur' => $confirmer_filter ? $confirmer_filter : '' ) );
	}

	/**
	 * Une ligne de commande.
	 *
	 * @param WC_Order $order
	 * @param array    $status_options
	 */
	protected function render_order_row( $order, $status_options, $can_delete = false, $can_assign = false, $can_carrier = false ) {
		$wilaya_code = (int) $order->get_meta( '_aod_wilaya_code' );
		$wilaya      = $wilaya_code && class_exists( 'AOD_COD_Data' )
			? AOD_COD_Data::wilaya_name( $wilaya_code )
			: $order->get_billing_state();
		$phone  = $order->get_billing_phone();
		$status = 'wc-' . $order->get_status();

		echo '<tr>';
		if ( $can_delete ) {
			echo '<td class="aod-cd-col-check"><label class="aod-cd-checkwrap"><input type="checkbox" class="aod-cd-ordercheck" value="' . esc_attr( $order->get_id() ) . '" title="' . esc_attr__( 'Sélectionner cette commande', 'aod-client-dashboard' ) . '"></label></td>';
		}
		// 45.3 — le n° redevient du TEXTE. C'était un `<button>` souligné en accent :
		// une affordance cachée (rien ne dit qu'un numéro s'ouvre), un nom
		// accessible réduit à « #4 » (qui n'annonce aucune action) et une cible de
		// deux caractères. L'action porte désormais son nom, dans le rang d'actions
		// de la ligne — un identifiant se lit, il ne se clique pas.
		echo '<td class="aod-cd-cell-num" data-label="' . esc_attr__( 'N°', 'aod-client-dashboard' ) . '"><span class="aod-cd-onum">#' . esc_html( $order->get_order_number() ) . '</span></td>';

		// Client : nom + téléphone + actions Appeler / WhatsApp (la date, la commune,
		// les articles et le détail des prix vivent désormais dans la modale de détail).
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		// 47.4 — DEUX classes sur la cellule, et ce n'est pas un doublon :
		//   · `.aod-cd-cell-client`      — le marqueur sémantique, PARTAGÉ avec
		//     Commandes abandonnées (`class-aod-cd-section-abandoned.php`) ;
		//   · `.aod-cd-cell-orderclient` — la classe PROPRE qui porte la mise en
		//     page à deux rangs, donc l'exemption de la colonne de libellé en mode
		//     carte (`dashboard.css`, recensement des exemptions).
		// Styler le marqueur partagé aurait déplacé la cellule des prospects au
		// passage, alors que rien ne le demande : même arbitrage qu'en 43.3 pour
		// `.aod-cd-cell-assigned` → `.aod-cd-cell-leadassign`.
		echo '<td class="aod-cd-cell-client aod-cd-cell-orderclient" data-label="' . esc_attr__( 'Client', 'aod-client-dashboard' ) . '">';
		echo '<span class="aod-cd-client">';
		// 🚨 RANG 1 — INCONDITIONNEL, et c'est la reprise de la garantie 45.3 par un
		// autre moyen. Le rang d'actions avait dû devenir inconditionnel parce qu'il
		// portait « Détail » et vivait sous `if ( $phone )` : la fenêtre de détail
		// devenait INATTEIGNABLE pour une commande sans téléphone — et il en existe
		// (squelette créé depuis un abandon, commande vidée de son numéro à
		// l'édition). « Détail » vit désormais ici, dans un rang que rien ne
		// conditionne : la garantie tient, et le rang d'actions peut redevenir ce
		// qu'il était — les boutons DU TÉLÉPHONE, sous condition de téléphone.
		echo '<span class="aod-cd-client__row">';
		// `title` : l'ellipse CSS tronque à l'affichage (le nom est le seul élément
		// compressible du rang) — le nom entier doit rester atteignable.
		echo '<b title="' . esc_attr( '' !== $name ? $name : '—' ) . '">' . esc_html( '' !== $name ? $name : '—' ) . '</b>';
		// La classe `.aod-cd-order-detail` est CONSERVÉE : c'est elle que cible
		// `bindOrderDetail` (`section-orders.js`). Changer l'élément qui la porte ne
		// touche donc pas une ligne de JS.
		echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-order-detail" data-order="' . esc_attr( $order->get_id() ) . '">' . AOD_CD_Icon::svg( 'orders', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Détail', 'aod-client-dashboard' ) . '</button>';
		// La classe `.aod-cd-order-detail` est CONSERVÉE : c'est elle que cible
		// `bindOrderDetail` (`section-orders.js`, `querySelectorAll`). Changer
		// l'élément qui la porte ne touche donc pas une ligne de JS — et si le JS
		// devait changer, c'est que le contrat aurait bougé sans qu'on le veuille.
		// 🔁 2026-08-08 — APPELER / WHATSAPP REJOIGNENT CE RANG, EN ICÔNES SEULES.
		// Ils vivaient dans un second rang, en toutes lettres. Deux mesures l'ont
		// fait tomber, et aucune n'est une question de goût :
		//   · LARGEUR — le tableau réclamait 1 148 px incompressibles, donc une barre
		//     de défilement horizontale dès qu'on descend sous ~1 250 px de fenêtre.
		//     « Appeler » + « WhatsApp » écrits pesaient à eux seuls ~190 px de ce
		//     minimum, sur une colonne qui en réclamait déjà pour le nom et le numéro.
		//   · HAUTEUR — le second rang portait la ligne à 94 px. Trois commandes
		//     remplissaient l'écran.
		// ⚠️ LE LIBELLÉ N'EST PAS PERDU, IL CHANGE DE PORTEUR : `aria-label` le donne
		// aux lecteurs d'écran, `title` le rend au survol. La porte `noms` vérifie
		// qu'aucun contrôle n'est sans nom accessible — c'est elle qui garde cette
		// promesse, pas ma vigilance. Les chaînes sont les MÊMES : rien à retraduire.
		// ⚠️ Un bouton sans libellé doit être élargi au pouce : ils rejoignent la
		// liste `min-width:44px` de dashboard.css, dont le commentaire annonçait
		// jusqu'ici `.aod-cd-page` comme « le seul bouton sans libellé ».
		echo '</span>';
		// RANG 2 — le NUMÉRO et les deux gestes qu'on en fait. Rendu seulement s'il
		// y a un téléphone : un rang vide n'ajouterait qu'une gouttière.
		if ( $phone ) {
			$wa = AOD_CD_Section_Abandoned::wa_number( $phone );
			echo '<span class="aod-cd-call-actions">';
			echo '<a class="aod-cd-client__tel" href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a>';
			echo '<a class="aod-cd-btn aod-cd-btn-sm aod-cd-btn--ghost aod-cd-btn--ico" href="tel:' . esc_attr( $phone ) . '"'
				. ' aria-label="' . esc_attr__( 'Appeler', 'aod-client-dashboard' ) . '"'
				. ' title="' . esc_attr__( 'Appeler', 'aod-client-dashboard' ) . '">'
				. AOD_CD_Icon::svg( 'phone', array( 'size' => 15 ) ) . '</a>';
			echo '<a class="aod-cd-btn aod-cd-btn-sm aod-cd-btn--ghost aod-cd-btn--ico" href="' . esc_url( 'https://wa.me/' . $wa ) . '" target="_blank" rel="noopener"'
				. ' aria-label="' . esc_attr__( 'WhatsApp', 'aod-client-dashboard' ) . '"'
				. ' title="' . esc_attr__( 'WhatsApp', 'aod-client-dashboard' ) . '">'
				. AOD_CD_Icon::svg( 'whatsapp', array( 'size' => 15 ) ) . '</a>';
			echo '</span>';
		}
		echo '</span></td>';

		echo '<td data-label="' . esc_attr__( 'Wilaya', 'aod-client-dashboard' ) . '">' . esc_html( $wilaya ? $wilaya : '—' ) . '</td>';
		echo '<td class="aod-cd-cell-total" data-label="' . esc_attr__( 'Total', 'aod-client-dashboard' ) . '">' . wp_kses_post( $order->get_formatted_order_total() ) . '</td>';

		echo '<td class="aod-cd-cell-status" data-label="' . esc_attr__( 'Statut', 'aod-client-dashboard' ) . '">';
		echo $this->status_select_html( $order->get_id(), $status, $status_options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup échappé dans la méthode.
		echo '</td>';

		// 47.1 — `data-label` OBLIGATOIRE : en dessous du point de bascule, le
		// tableau passe en cartes et cet attribut EST l'intitulé de la ligne
		// (46.2). Aucun cliquet générique ne verrait son absence — R2 de
		// `test-chrome-conformite.php` travaille au grain du FICHIER, qui porte
		// déjà `--cards` et des `data-label` ailleurs. D'où R5 de
		// `test-carrier-par-commande.php`, écrit pour cette cellule-là.
		if ( $can_carrier ) {
			echo '<td class="aod-cd-cell-carrier" data-label="' . esc_attr__( 'Transporteur', 'aod-client-dashboard' ) . '">' . $this->carrier_select_html( $order ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- markup échappé dans carrier_select_html().
		}

		echo '<td class="aod-cd-cell-ship" data-label="' . esc_attr__( 'Suivi', 'aod-client-dashboard' ) . '">' . $this->ship_cell_html( $order ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $can_assign ) {
			echo '<td class="aod-cd-cell-assigned" data-label="' . esc_attr__( 'Assigné à', 'aod-client-dashboard' ) . '">' . self::assigned_confirmer_label( $order ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- markup échappé dans assigned_confirmer_label().
		}

		echo '</tr>';
	}

	/**
	 * Sélecteur de statut de commande sous forme de menu personnalisé.
	 *
	 * Remplace le <select> natif : la liste d'options du select natif s'ouvrait
	 * hors champ (rognée par l'overflow du tableau / mal placée selon le
	 * navigateur). Le menu ci-dessous est positionné en JS (position:fixed) et
	 * stylé aux couleurs de la boutique, avec une pastille de couleur par statut.
	 *
	 * @param int    $order_id
	 * @param string $current  Statut courant préfixé wc- (ex. wc-aod-confirmed).
	 * @param array  $options  [ 'wc-pending' => 'En attente', ... ]
	 * @return string Markup échappé.
	 */
	protected function status_select_html( $order_id, $current, $options ) {
		$current_label = isset( $options[ $current ] ) ? $options[ $current ] : $current;
		$cur_slug      = preg_replace( '/^wc-/', '', $current );

		$caret = '<svg class="aod-cd-statussel-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
		$check = '<svg class="aod-cd-statussel-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';

		$html  = '<div class="aod-cd-statussel" data-order="' . esc_attr( $order_id ) . '" data-value="' . esc_attr( $current ) . '">';
		$html .= '<button type="button" class="aod-cd-statussel-btn" data-status="' . esc_attr( $cur_slug ) . '" aria-haspopup="listbox" aria-expanded="false">';
		$html .= '<span class="aod-cd-statussel-dot"></span>';
		$html .= '<span class="aod-cd-statussel-text">' . esc_html( $current_label ) . '</span>';
		$html .= $caret . '</button>';
		$html .= '<div class="aod-cd-statussel-menu" role="listbox" hidden>';
		foreach ( $options as $key => $label ) {
			$slug = preg_replace( '/^wc-/', '', $key );
			$sel  = ( $key === $current );
			$html .= '<button type="button" class="aod-cd-statussel-opt' . ( $sel ? ' is-active' : '' ) . '" role="option"'
				. ' data-value="' . esc_attr( $key ) . '" data-status="' . esc_attr( $slug ) . '"'
				. ' aria-selected="' . ( $sel ? 'true' : 'false' ) . '">';
			$html .= '<span class="aod-cd-statussel-dot"></span>';
			$html .= '<span class="aod-cd-statussel-text">' . esc_html( $label ) . '</span>';
			$html .= $check . '</button>';
		}
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Sélecteur du transporteur d'une commande (Phase 47.1).
	 *
	 * TROISIÈME occurrence du patron « menu personnalisé » après `statussel`
	 * (5.2) et `assignsel` (42.4) — et la règle de trois posée dans le docblock
	 * d'`assign_select_html()` s'applique donc… à la PEAU, qui est mutualisée en
	 * CSS par sélecteurs joints, PAS au comportement. Les classes restent
	 * distinctes (`aod-cd-carriersel*`) parce que tous les `section-*.js` sont
	 * chargés sur TOUTES les pages de `/gestion` et câblent par
	 * `querySelectorAll` GLOBAL : une classe recyclée enverrait ce widget au
	 * mauvais point AJAX (piège documenté en 43.3, `section-abandoned.js:160`).
	 *
	 * 🚨 Un `<select>` NATIF est exclu ici : sa liste s'ouvre hors champ dans une
	 * cellule de tableau (cf. `status_select_html()` ci-dessus). Dans la BARRE
	 * d'action groupée, en revanche, le natif est correct — bandeau pleine
	 * largeur, hors du tableau défilant.
	 *
	 * L'ÉCRAN NE MENT PAS : le bouton affiche le transporteur EFFECTIF, c'est-à-dire
	 * celui chez qui le colis partira — choisi sur la commande, ou hérité du
	 * réglage de la boutique. L'option « Par défaut » nomme d'ailleurs sa cible :
	 * c'est ce qui permet de REVENIR au réglage global (décision D-1 : méta vide =
	 * « comme la boutique », il faut donc pouvoir y retourner).
	 *
	 * @param WC_Order $order
	 * @return string Markup échappé.
	 */
	protected function carrier_select_html( $order ) {
		if ( ! class_exists( 'AOD_Shipping' ) ) {
			return '<span class="aod-cd-muted">—</span>';
		}
		$ship = AOD_Shipping::instance();

		// Déjà expédiée : le colis EXISTE chez un transporteur. Le changer ici ne
		// le déplacerait pas — ce serait un écran qui ment. On montre qui a
		// expédié (`META_CARRIER`, écrite par `store_result()`), en texte.
		if ( '' !== (string) $order->get_meta( '_aod_ship_tracking' ) ) {
			$sent = $ship->carrier( (string) $order->get_meta( '_aod_ship_carrier' ) );
			return '<span class="aod-cd-carrier-sent">' . esc_html( $sent ? $sent->label() : '—' ) . '</span>';
		}

		$configured = $ship->configured();
		if ( empty( $configured ) ) {
			// Rien à choisir : aucun transporteur n'est connecté. Le dire à
			// l'écran plutôt que d'afficher un menu vide.
			return '<span class="aod-cd-muted">' . esc_html__( 'Aucun transporteur', 'aod-client-dashboard' ) . '</span>';
		}

		$choice    = (string) $order->get_meta( AOD_Shipping::META_CARRIER_CHOICE );
		$effective = $ship->carrier_for( $order );
		$eff_label = is_wp_error( $effective ) ? __( 'Non configuré', 'aod-client-dashboard' ) : $effective->label();

		// Le repli global NOMMÉ : « Par défaut (Yalidine) ». Sans le nom, le gérant
		// devrait aller lire un autre écran pour savoir ce que « défaut » désigne
		// aujourd'hui. Résolu SANS la commande — c'est bien la valeur de la
		// boutique qu'on nomme, pas celle que cette commande porte.
		$global    = $ship->carrier_for();
		$gl_label  = is_wp_error( $global ) ? __( 'Non configuré', 'aod-client-dashboard' ) : $global->label();

		$options = array(
			'' => sprintf(
				/* translators: %s : nom du transporteur réglé pour toute la boutique. */
				__( 'Par défaut (%s)', 'aod-client-dashboard' ),
				$gl_label
			),
		);
		foreach ( $configured as $id => $carrier ) {
			$options[ (string) $id ] = $carrier->label();
		}

		$caret = '<svg class="aod-cd-carriersel-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
		$check = '<svg class="aod-cd-carriersel-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';

		$html  = '<div class="aod-cd-carriersel' . ( '' === $choice ? ' is-inherited' : '' ) . '" data-order="' . esc_attr( $order->get_id() ) . '" data-value="' . esc_attr( $choice ) . '">';
		$html .= '<button type="button" class="aod-cd-carriersel-btn" aria-haspopup="listbox" aria-expanded="false">';
		$html .= AOD_CD_Icon::svg( 'shipping', array( 'size' => 15 ) );
		$html .= '<span class="aod-cd-carriersel-text">' . esc_html( $eff_label ) . '</span>';
		$html .= $caret . '</button>';
		$html .= '<div class="aod-cd-carriersel-menu" role="listbox" hidden>';
		foreach ( $options as $id => $label ) {
			$sel   = ( (string) $id === $choice );
			$html .= '<button type="button" class="aod-cd-carriersel-opt' . ( $sel ? ' is-active' : '' ) . '" role="option"'
				. ' data-value="' . esc_attr( $id ) . '"'
				. ' aria-selected="' . ( $sel ? 'true' : 'false' ) . '">';
			$html .= '<span class="aod-cd-carriersel-text">' . esc_html( $label ) . '</span>';
			$html .= $check . '</button>';
		}
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Nom du confirmateur assigné à une commande (colonne « Assigné à », 42.7),
	 * ou tiret sourd si non assignée / confirmateur supprimé depuis.
	 *
	 * `public static` — jumeau de `AOD_CD_Section_Abandoned::wa_number()`
	 * (Phase 34) : seule cette section possède la donnée d'assignation, réutilisée
	 * telle quelle par `AOD_CD_Section_Tracking` (qui hérite de `scoped_orders()`
	 * depuis la 30.6, mais pas des méthodes de rendu de cette classe).
	 *
	 * 🚨 Ne vérifie AUCUNE capability : c'est à l'appelant de garder l'affichage
	 * gérant uniquement (cf. `assign_select_html()` ci-dessous, même règle) — un
	 * confirmateur ne doit jamais voir le nom d'un assigné, y compris le sien.
	 *
	 * Depuis la 43.4, transmet aussi `_aod_assigned_by` : le helper y ajoute le
	 * fragment « Admin » quand l'assignation vient du gérant, rien quand elle
	 * vient du tourniquet. La section `Suivi` hérite du fragment sans une ligne
	 * de plus (elle appelle cette méthode, pas le helper).
	 *
	 * @param WC_Order $order
	 * @return string HTML échappé.
	 */
	public static function assigned_confirmer_label( $order ) {
		return AOD_CD_Roles::confirmer_label_html(
			(int) $order->get_meta( '_aod_assigned_confirmer' ),
			(int) $order->get_meta( '_aod_assigned_by' )
		);
	}

	/**
	 * Sélecteur d'assignation d'une commande à un confirmateur (Phase 42.4).
	 *
	 * Patron EXACT de `status_select_html()` ci-dessus (menu personnalisé
	 * positionné en JS) sous des classes CSS distinctes (`aod-cd-assignsel*`) —
	 * volontairement DUPLIQUÉ plutôt que factorisé avec le sélecteur de statut :
	 * ce dernier est un rouage déjà éprouvé (statut → envoi transporteur), le
	 * refactoriser pour partager un widget générique ferait courir un risque de
	 * régression sans bénéfice proportionné pour cette story (règle de trois :
	 * à factoriser seulement si un TROISIÈME widget de ce type apparaît).
	 *
	 * Gérant UNIQUEMENT (appelant : `render_order_detail()`) — un confirmateur
	 * ne voit ni ce sélecteur ni le nom de l'assigné.
	 *
	 * @param int      $order_id
	 * @param int      $current_id  ID du confirmateur assigné, `0` = non assignée.
	 * @param WP_User[] $confirmers Vivier (typiquement `eligible_confirmers( array(), true )`).
	 * @return string Markup échappé.
	 */
	protected function assign_select_html( $order_id, $current_id, $confirmers ) {
		$current_id = (int) $current_id;

		$options = array( 0 => __( 'Non assignée', 'aod-client-dashboard' ) );
		foreach ( $confirmers as $confirmer ) {
			$options[ (int) $confirmer->ID ] = $confirmer->display_name;
		}
		$current_label = isset( $options[ $current_id ] ) ? $options[ $current_id ] : $options[0];

		$caret = '<svg class="aod-cd-assignsel-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
		$check = '<svg class="aod-cd-assignsel-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';

		$html  = '<div class="aod-cd-assignsel" data-order="' . esc_attr( $order_id ) . '" data-value="' . esc_attr( $current_id ) . '">';
		$html .= '<button type="button" class="aod-cd-assignsel-btn" aria-haspopup="listbox" aria-expanded="false">';
		$html .= AOD_CD_Icon::svg( 'user', array( 'size' => 15 ) );
		$html .= '<span class="aod-cd-assignsel-text">' . esc_html( $current_label ) . '</span>';
		$html .= $caret . '</button>';
		$html .= '<div class="aod-cd-assignsel-menu" role="listbox" hidden>';
		foreach ( $options as $uid => $label ) {
			$sel = ( $uid === $current_id );
			$html .= '<button type="button" class="aod-cd-assignsel-opt' . ( $sel ? ' is-active' : '' ) . '" role="option"'
				. ' data-value="' . esc_attr( $uid ) . '"'
				. ' aria-selected="' . ( $sel ? 'true' : 'false' ) . '">';
			$html .= '<span class="aod-cd-assignsel-text">' . esc_html( $label ) . '</span>';
			$html .= $check . '</button>';
		}
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Variantes choisies d'une ligne de commande (ex. ['XXL', 'Gris']).
	 *
	 * @param WC_Order_Item $item
	 * @return string[] Valeurs des variantes (sans les libellés de section).
	 */
	protected function item_variants( $item ) {
		$out = array();
		if ( ! is_callable( array( $item, 'get_formatted_meta_data' ) ) ) {
			return $out;
		}
		foreach ( $item->get_formatted_meta_data() as $m ) {
			$val = wp_strip_all_tags( $m->display_value );
			if ( '' !== trim( $val ) ) {
				$out[] = $val;
			}
		}
		return $out;
	}

	/**
	 * Colonne « Articles » : produits commandés + variantes + quantité.
	 *
	 * @param WC_Order $order
	 * @return string HTML sûr.
	 */
	protected function order_articles_html( $order ) {
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return '<span class="aod-cd-muted">—</span>';
		}
		$lines = array();
		foreach ( $items as $item ) {
			$txt      = '<strong>' . esc_html( $item->get_name() ) . '</strong>';
			$variants = $this->item_variants( $item );
			if ( $variants ) {
				$txt .= ' <span class="aod-cd-variants">(' . esc_html( implode( ', ', $variants ) ) . ')</span>';
			}
			$txt    .= ' <span class="aod-cd-qty">×' . esc_html( $item->get_quantity() ) . '</span>';
			$lines[] = $txt;
		}
		return '<div class="aod-cd-articles-list">' . implode( '<br>', $lines ) . '</div>';
	}

	/**
	 * Cellule « Suivi » : icône camion (gris = à envoyer, vert = envoyé, rouge = échec).
	 *
	 * Réutilise l'icône du module d'expédition (AOD_Shipping) si disponible.
	 *
	 * @param WC_Order $order
	 * @return string HTML sûr.
	 */
	protected function ship_cell_html( $order ) {
		$tracking = $order->get_meta( '_aod_ship_tracking' );

		if ( class_exists( 'AOD_Shipping' ) ) {
			$ship   = AOD_Shipping::instance();
			$status = $ship->ship_status( $order );
			if ( 'none' === $status ) {
				return '<span class="aod-cd-muted">—</span>';
			}
			$icon = $ship->status_badge_html( $order, 22, false );

			// 🚨 47.2 — LE CAMION DEVIENT UN BOUTON NOMMÉ. Il a longtemps été une
			// icône muette, et c'était cohérent : rien ne se passait au clic,
			// l'envoi partait tout seul à la confirmation. Maintenant que le geste
			// existe, une icône sans nom serait une affordance cachée — la leçon
			// 45.3, mot pour mot (« un identifiant se lit, il ne se clique pas » :
			// ici, un état se lit, une action se nomme).
			//
			// ⚠️ TROIS ÉTATS, TROIS RENDUS, et le troisième n'est PAS un bouton
			// `disabled`. Pour une commande déjà expédiée, rien ne manque comme
			// DROIT — c'est l'objet même de l'action qui a disparu : le colis
			// existe. Un bouton grisé ferait chercher une permission absente.
			// Même arbitrage qu'en 47.1 pour le sélecteur de transporteur.
			if ( 'sent' === $status ) {
				return '<span class="aod-cd-ship">' . $icon
					. ( $tracking ? ' <code class="aod-cd-trackcode">' . esc_html( $tracking ) . '</code>' : '' )
					. '</span>';
			}

			// La cellule est rendue pour tout le monde ; le BOUTON, lui, suppose la
			// capability. Le rendu ne remplace pas la garde serveur (`ajax_ship_send`
			// la refait) : il évite d'offrir un geste qui serait refusé.
			if ( ! AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
				return '<span class="aod-cd-ship">' . $icon . '</span>';
			}

			// 🚨 Le bouton ne s'offre qu'à une commande CONFIRMÉE — même raison que la
			// garde de `AOD_Shipping::ship_order()`, dont ce rendu est le miroir : tant
			// que l'envoi partait du hook de statut, « confirmée » allait de soi ; le
			// geste de la 47.2 l'a rendu offrable partout, y compris sur une commande
			// neuve que personne n'a appelée, et sur une commande annulée (elle portait
			// `_aod_ship_error`, donc « Réessayer »). Le serveur refuse de toute façon —
			// ceci évite d'offrir un geste irréversible qui serait refusé.
			if ( ! class_exists( 'AOD_Shipping' ) || AOD_Shipping::STATUS !== $order->get_status() ) {
				return '<span class="aod-cd-ship">' . $icon . '</span>';
			}

			$echec = ( 'failed' === $status );
			$titre = $echec ? (string) $order->get_meta( '_aod_ship_error' ) : '';

			// 🚨 47.3 — `data-note-perdue` : le SEUL endroit qui connaît les deux faits.
			//
			// L'écran doit prévenir quand la note du confirmateur ne partira pas
			// (décision D3 de §A.23). Deux choses sont nécessaires : la commande a-t-elle
			// une note, et le transporteur RÉSOLU porte-t-il le champ.
			//
			// ⚠️ NE PAS refaire ce calcul en JS avec une carte des transporteurs. Il
			// faudrait y rejouer la résolution méta → global → repli « un seul
			// configuré » que la 47.1 a réduite à UNE fonction — `carrier_for()`, dont
			// R4 de `test-carrier-par-commande.php` exige exactement 1 occurrence dans
			// tout le plugin. Le serveur calcule, le DOM porte, le JS lit : c'est déjà
			// la doctrine de `ligneEnvoi()` (`eligible: !! btn`).
			//
			// ⚠️ Un `WP_Error` (aucun transporteur configuré, ou choix non configuré →
			// refus 47.1) vaut « 0 » : on ne crie pas au loup sur un envoi qui sera de
			// toute façon refusé, avec un message plus précis que le nôtre.
			$note_perdue = false;
			if ( class_exists( 'AOD_Carrier' ) && '' !== trim( (string) $order->get_meta( AOD_Carrier::META_DRIVER_NOTE ) ) ) {
				$cible = $ship->carrier_for( $order );
				$note_perdue = ( $cible instanceof AOD_Carrier ) && ! $cible->supports_driver_note();
			}

			return '<span class="aod-cd-ship">'
				. '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-ship-send' . ( $echec ? ' aod-cd-ship-send--retry' : '' ) . '"'
				. ' data-order="' . esc_attr( $order->get_id() ) . '"'
				. ' data-note-perdue="' . ( $note_perdue ? '1' : '0' ) . '"'
				. ( '' !== $titre ? ' title="' . esc_attr( $titre ) . '"' : '' )
				. '>' . $icon . ' '
				. esc_html( $echec ? __( 'Réessayer', 'aod-client-dashboard' ) : __( 'Envoyer', 'aod-client-dashboard' ) )
				. '</button></span>';
		}

		// Repli si le module d'expédition n'est pas actif.
		return $tracking
			? '<span class="aod-cd-track">' . AOD_CD_Icon::svg( 'check', array( 'size' => 14 ) ) . ' <code>' . esc_html( $tracking ) . '</code></span>'
			: '<span class="aod-cd-muted">—</span>';
	}

	/**
	 * Libellés des statuts de commande, forcés en français.
	 *
	 * WooCommerce renvoie ses libellés natifs en anglais lorsque le pack de
	 * traduction n'est pas chargé (même sous locale fr_FR). On conserve l'ordre
	 * et les statuts réels de WooCommerce (y compris les statuts personnalisés)
	 * et on remplace uniquement les libellés connus par leur version française.
	 *
	 * @return array [ 'wc-pending' => 'En attente', ... ]
	 */
	protected function status_labels() {
		$fr = array(
			'wc-pending'        => __( 'En attente', 'aod-client-dashboard' ),
			'wc-processing'     => __( 'En cours', 'aod-client-dashboard' ),
			'wc-aod-confirmed'  => __( 'Confirmée', 'aod-client-dashboard' ),
			'wc-on-hold'        => __( 'En pause', 'aod-client-dashboard' ),
			'wc-completed'      => __( 'Terminée', 'aod-client-dashboard' ),
			'wc-cancelled'      => __( 'Annulée', 'aod-client-dashboard' ),
			'wc-refunded'       => __( 'Remboursée', 'aod-client-dashboard' ),
			'wc-failed'         => __( 'Échouée', 'aod-client-dashboard' ),
			'wc-checkout-draft' => __( 'Brouillon', 'aod-client-dashboard' ),
		);

		$labels = array();
		foreach ( wc_get_order_statuses() as $key => $label ) {
			$labels[ $key ] = isset( $fr[ $key ] ) ? $fr[ $key ] : $label;
		}

		return $labels;
	}

	/**
	 * Statuts utilisés par l'onglet « Toutes » et les compteurs.
	 *
	 * On part de wc_get_order_statuses(), mais on GARANTIT la présence du statut
	 * personnalisé « Confirmée » (wc-aod-confirmed). Ce statut est fourni par le
	 * plugin AOD COD Form ; si sa registration ne s'exécute pas (plugin désactivé,
	 * ordre de chargement, copie en double sur le serveur…), il manquerait à la
	 * liste et TOUTES les commandes confirmées disparaîtraient de « Toutes » et
	 * des compteurs — alors qu'elles existent toujours en base. Ce filet empêche
	 * cette disparition silencieuse, indépendamment de l'état du plugin COD.
	 *
	 * @return string[] Clés de statut préfixées wc- (ex. wc-pending, wc-aod-confirmed…).
	 */
	protected function all_order_status_keys() {
		$keys = array_keys( wc_get_order_statuses() );
		if ( ! in_array( 'wc-aod-confirmed', $keys, true ) ) {
			$keys[] = 'wc-aod-confirmed';
		}
		return $keys;
	}

	/**
	 * Statuts couverts par un onglet nommé du cycle de vie COD.
	 *
	 * Source unique : `render()` construit ses onglets dessus, et
	 * `residual_status_keys()` s'en sert pour définir son complément. Écrire
	 * cette liste à deux endroits laisserait un statut tomber entre les deux
	 * sans que rien ne le signale — exactement le défaut que l'onglet de repli
	 * est censé rendre impossible.
	 *
	 * @return string[] Clés préfixées wc-.
	 */
	protected function lifecycle_status_keys() {
		return array(
			'wc-pending',
			'wc-processing',
			'wc-aod-confirmed',
			'wc-completed',
			'wc-cancelled',
		);
	}

	/**
	 * Statuts réellement enregistrés qui n'ont PAS d'onglet nommé.
	 *
	 * Typiquement : En pause, Remboursée, Échouée, Brouillon — plus tout statut
	 * ajouté par une extension tierce. Ils alimentent l'onglet « Autres », qui
	 * garantit qu'aucune commande n'est hors de portée des onglets.
	 *
	 * @return string[] Clés préfixées wc- (tableau vide si tout est couvert).
	 */
	protected function residual_status_keys() {
		return array_values(
			array_diff( $this->all_order_status_keys(), $this->lifecycle_status_keys() )
		);
	}

	/**
	 * Traduit un slug d'onglet en liste de statuts pour wc_get_orders().
	 *
	 * Source unique pour le listing ET les compteurs : les deux divergeaient
	 * dès qu'un slug cessait d'être un simple `wc-<slug>` (cas de « Autres »).
	 *
	 * @param string $slug Slug d'onglet (`all`, `autres`, ou un statut sans préfixe).
	 * @return string[] Clés de statut préfixées wc-.
	 */
	protected function status_keys_for( $slug ) {
		if ( 'all' === $slug ) {
			return $this->all_order_status_keys();
		}
		if ( self::TAB_OTHER === $slug ) {
			$keys = $this->residual_status_keys();
			// Jamais un tableau vide : wc_get_orders() interprète `status => []`
			// comme « tous les statuts » et l'onglet de repli afficherait alors
			// la boutique entière. Un statut impossible rend zéro ligne, ce qui
			// est la bonne réponse quand il n'y a pas de résiduel.
			return $keys ? $keys : array( 'wc-aod-none' );
		}
		return array( 'wc-' . $slug );
	}

	/**
	 * Filtre meta_query excluant les commandes DÉJÀ EXPÉDIÉES (colis créé chez le
	 * transporteur → méta _aod_ship_tracking non vide) de la liste « Commandes »
	 * (étape 12.1).
	 *
	 * Une commande expédiée « se déplace » vers « Suivi commande » : les deux pages
	 * partagent le MÊME critère (_aod_ship_tracking), donc rien ne disparaît — ce qui
	 * quitte « Commandes » réapparaît dans « Suivi ». Une commande en ERREUR d'envoi
	 * (_aod_ship_error SANS tracking) reste ici : elle est à retraiter, elle n'est pas
	 * dans « Suivi ». N'affecte pas les clauses de périmètre de scoped_orders()
	 * (celles-ci filtrent par jointure SQL, indépendamment du meta_query).
	 *
	 * Renvoie un tableau vide si le module COD (donc AOD_Carrier) est absent : sans
	 * lui aucune commande n'est expédiée, il n'y a rien à exclure — et référencer la
	 * constante planterait.
	 *
	 * @return array meta_query partielle (à fusionner dans les args wc_get_orders).
	 */
	protected function unshipped_meta_query() {
		if ( ! class_exists( 'AOD_Carrier' ) ) {
			return array();
		}
		return array(
			'relation' => 'OR',
			array(
				'key'     => AOD_Carrier::META_TRACKING,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => AOD_Carrier::META_TRACKING,
				'value'   => '',
				'compare' => '=',
			),
		);
	}

	/**
	 * Compte les commandes d'un statut (ou toutes).
	 *
	 * @param string $slug 'all' ou statut sans préfixe wc-.
	 * @param string $date Filtre date_created optionnel (ex. '>=2026-01-01').
	 *                     Format HPOS-safe accepté par wc_get_orders.
	 * @return int
	 */
	protected function count_orders( $slug, $date = '' ) {
		// Comptage côté base (COUNT SQL) via la pagination de wc_get_orders :
		// limit=1 + paginate=true renvoie ->total sans charger toutes les lignes.
		// HPOS-safe, contrairement à un wc_get_orders( limit => -1 ) qui chargeait
		// tous les IDs de chaque statut à chaque affichage de la page Commandes.
		$status = $this->status_keys_for( $slug );

		$args = array(
			'type'     => 'shop_order', // Cohérent avec le listing : pas de remboursements dans les compteurs.
			'limit'    => 1,
			'paginate' => true,
			'return'   => 'ids',
			'status'   => $status,
		);
		if ( '' !== $date ) {
			$args['date_created'] = $date;
		}
		// Étape 12.1 : compteurs alignés sur le listing (les expédiées sont exclues).
		$exclude_shipped = $this->unshipped_meta_query();
		if ( $exclude_shipped ) {
			$args['meta_query'] = $exclude_shipped; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$res = $this->scoped_orders( $args );

		return isset( $res->total ) ? (int) $res->total : 0;
	}

	/**
	 * Compte les commandes (du/des statut(s) donné(s)) qui n'ont AUCUNE
	 * `_aod_assigned_confirmer` — mesure du filtre « Non assignées » (42.5).
	 * Même patron que `count_orders()` (COUNT SQL via pagination).
	 *
	 * @param string[] $status Statuts préfixés `wc-` (ex. `all_order_status_keys()`).
	 * @return int
	 */
	protected function count_unassigned( $status ) {
		$unassigned_clause = array( 'key' => '_aod_assigned_confirmer', 'compare' => 'NOT EXISTS' );
		$exclude_shipped   = $this->unshipped_meta_query();

		$args = array(
			'type'       => 'shop_order',
			'limit'      => 1,
			'paginate'   => true,
			'return'     => 'ids',
			'status'     => $status,
			'meta_query' => $exclude_shipped // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- gérant only (compteur du filtre « Non assignées », 42.5), clé indexable.
				? array( 'relation' => 'AND', $exclude_shipped, $unassigned_clause )
				: array( $unassigned_clause ),
		);

		$res = $this->scoped_orders( $args );

		return isset( $res->total ) ? (int) $res->total : 0;
	}

	/* ============================================================
	 * Assignation des commandes aux confirmateurs (Phase 42)
	 * ========================================================== */

	/**
	 * Nombre de commandes ACTIVES (ni `completed`, ni `cancelled`, ni `refunded`)
	 * actuellement assignées à cet utilisateur — sert de mesure de charge au
	 * tourniquet (42.1). Compte brut, INDÉPENDANT du périmètre produit de qui
	 * pose la question (patron `count_orders()` ci-dessus : total via
	 * pagination, jamais charger toutes les commandes en mémoire — leçon
	 * Phase 31.6).
	 *
	 * @param int $user_id
	 * @return int
	 */
	public function count_active_assigned( $user_id ) {
		$terminal = array( 'wc-completed', 'wc-cancelled', 'wc-refunded' );
		$status   = array_values( array_diff( $this->all_order_status_keys(), $terminal ) );

		$res = wc_get_orders( array(
			'type'       => 'shop_order',
			'status'     => $status,
			'meta_key'   => '_aod_assigned_confirmer', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- pas d'API wc_get_orders dédiée à « compte par assigné », clé indexable, requête paginée à limit=1 (total seul, jamais la liste).
			'meta_value' => (int) $user_id,             // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- même requête, valeur bornée à un ID entier.
			'limit'      => 1,
			'paginate'   => true,
			'return'     => 'ids',
		) );

		return isset( $res->total ) ? (int) $res->total : 0;
	}

	/**
	 * Assigne une commande née du FORMULAIRE PUBLIC au confirmateur éligible le
	 * moins chargé (décision D2 — ROADMAP §A.19). Hookée `aod_cod_order_created`
	 * (constructeur) : ne couvre PAS la conversion d'un abandon (42.2, hook
	 * distinct, règle distincte).
	 *
	 * Vivier vide (aucun confirmateur éligible) : ne pose AUCUNE méta — la
	 * commande reste non assignée, visible du seul gérant, comme avant la
	 * Phase 42.
	 *
	 * Concurrence ASSUMÉE, sans verrou : deux commandes créées dans la même
	 * fraction de seconde peuvent lire la même charge et atterrir toutes deux
	 * chez le même confirmateur (léger déséquilibre transitoire, auto-corrigé à
	 * la commande suivante). Différent de la Phase 31.2 (numérotation), où une
	 * collision produisait une donnée CORROMPUE : ici, jamais de donnée fausse
	 * ni de commande perdue — un verrou serait disproportionné au risque réel.
	 *
	 * Aucun appel HTTP : uniquement des requêtes DB locales sur le chemin
	 * d'achat, sans commune mesure avec les CAPI externes sorties de ce chemin
	 * en Phase 14.
	 *
	 * @param WC_Order $order
	 */
	public function assign_least_loaded( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$product_ids = array();
		foreach ( $order->get_items() as $item ) {
			$product_ids[] = (int) $item->get_product_id();
			$variation_id  = (int) $item->get_variation_id();
			if ( $variation_id ) {
				$product_ids[] = $variation_id;
			}
		}

		$eligible = AOD_CD_Roles::eligible_confirmers( $product_ids );
		if ( ! $eligible ) {
			return;
		}

		$chosen     = null;
		$best_count = null;
		foreach ( $eligible as $user ) {
			$count = $this->count_active_assigned( $user->ID );
			if ( null === $best_count || $count < $best_count ) {
				$best_count = $count;
				$chosen     = $user;
			}
		}

		if ( ! $chosen ) {
			return;
		}

		$order->update_meta_data( '_aod_assigned_confirmer', (int) $chosen->ID );
		// 🚨 AUCUNE `_aod_assigned_by` ici, et c'est la RÈGLE, pas un oubli
		// (43.4) : l'absence de signature EST ce qui distingue une assignation
		// automatique d'un choix du gérant. Ne pas « corriger » ce silence.
		// Ne pas non plus s'en remettre au fait que `get_current_user_id()`
		// vaudrait 0 sur le chemin d'achat public : le jour où cette méthode
		// serait appelée depuis /gestion (rattrapage, réquilibrage…), poser la
		// signature ferait passer le tourniquet pour le gérant.
		$order->save();
	}

	/* ============================================================
	 * Périmètre d'assignation d'un confirmateur (Phase 42.3)
	 * ========================================================== */

	/** @var int|null Cache requête : 0 = pas de filtre, sinon ID utilisateur. */
	protected $assignment_scope = null;

	/**
	 * ID du confirmateur courant si le périmètre de lecture doit être restreint
	 * à SES commandes assignées (`_aod_assigned_confirmer`) — `0` sinon (aucun
	 * filtre : gérant, ou confirmateur sans `aod_cap_confirm_orders`).
	 *
	 * Gouverné par la CAPABILITY, pas seulement le rôle : un confirmateur peut
	 * porter `aod_cap_tracking`/`aod_cap_stock` SANS `aod_cap_confirm_orders`
	 * (7 capabilities à la carte, Phase 1) — il n'a jamais d'assignation
	 * (`AOD_CD_Roles::eligible_confirmers()`, 42.1, ne pioche que parmi les
	 * porteurs de cette cap) ; le filtrer quand même viderait Suivi à tort pour
	 * un rôle qui n'a rien à voir avec la confirmation (ROADMAP §Phase 42, 42.3).
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

	/* ============================================================
	 * Périmètre produits d'un confirmateur (étape 1.5)
	 * ========================================================== */

	/** @var int[]|null Cache requête du périmètre produits de l'utilisateur courant. */
	protected $product_scope = null;

	/**
	 * Produits affectés à l'utilisateur courant. Vide = pas de restriction
	 * (gérant, ou confirmateur sans affectation).
	 *
	 * @return int[]
	 */
	protected function product_scope() {
		if ( null === $this->product_scope ) {
			$this->product_scope = AOD_CD_Roles::assigned_products();
		}
		return $this->product_scope;
	}

	/**
	 * La commande contient-elle au moins un produit du périmètre ?
	 * Garde des handlers AJAX : un ID hors périmètre est refusé même si le
	 * confirmateur le connaît (le filtre SQL ne cache que la liste).
	 *
	 * `public` depuis la 30.5 : la section Suivi (AOD_CD_Section_Tracking) porte
	 * la même garde. Le périmètre produits reste défini une seule fois — c'est
	 * la logique de toutes les listes de commandes ; il a quitté le Dashboard
	 * AVEC cette section (30.6), comme la 30.5 l'annonçait.
	 *
	 * @param WC_Order $order
	 * @return bool
	 */
	public function order_in_scope( $order ) {
		// Cumul (Phase 42.3) : une commande assignée à un AUTRE confirmateur est
		// refusée, même dans le périmètre produit de l'appelant — l'échec le plus
		// tôt gagne, avant même de consulter le périmètre produit.
		$assigned = $this->assignment_scope();
		if ( $assigned && (int) $order->get_meta( '_aod_assigned_confirmer' ) !== $assigned ) {
			return false;
		}

		$scope = $this->product_scope();
		if ( ! $scope ) {
			return true;
		}
		foreach ( $order->get_items() as $item ) {
			if ( in_array( (int) $item->get_product_id(), $scope, true )
				|| in_array( (int) $item->get_variation_id(), $scope, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * wc_get_orders restreint au périmètre produits de l'utilisateur courant.
	 *
	 * Les filtres ne sont armés que le temps de la requête : la jointure sur
	 * wp_wc_order_product_lookup filtre côté SQL, donc la pagination et le
	 * total (compteurs) restent exacts — pas de tri en PHP après coup. Les DEUX
	 * modes de stockage sont couverts (un clone peut être en HPOS ou en CPT
	 * hérité) : sans ça, le mode non couvert laisserait la liste non filtrée.
	 *
	 * `public` depuis la 30.5 : même raison que `order_in_scope()` ci-dessus.
	 *
	 * @param array $args Arguments wc_get_orders.
	 * @return mixed Résultat de wc_get_orders.
	 */
	/**
	 * Colonnes de l'export CSV — une seule source, lue par l'en-tête ET par les
	 * lignes. Deux listes séparées dériveraient : c'est le défaut de propagation
	 * que les Phases 44 et 47 documentent, et il donnerait ici un fichier dont les
	 * en-têtes ne décrivent plus les colonnes — indétectable à l'œil sur 500 lignes.
	 *
	 * @return array<string,callable> Libellé traduit => extracteur( WC_Order ): string
	 */
	public static function export_columns() {
		return array(
			__( 'N° commande', 'aod-client-dashboard' )  => function ( $o ) { return $o->get_order_number(); },
			__( 'Date', 'aod-client-dashboard' )         => function ( $o ) {
				$d = $o->get_date_created();
				return $d ? $d->date_i18n( 'Y-m-d H:i' ) : '';
			},
			__( 'Statut', 'aod-client-dashboard' )       => function ( $o ) { return wc_get_order_status_name( $o->get_status() ); },
			__( 'Client', 'aod-client-dashboard' )       => function ( $o ) { return trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ); },
			__( 'Téléphone', 'aod-client-dashboard' )    => function ( $o ) { return $o->get_billing_phone(); },
			__( 'Wilaya', 'aod-client-dashboard' )       => function ( $o ) { return (string) $o->get_meta( '_aod_wilaya_name' ); },
			__( 'Commune', 'aod-client-dashboard' )      => function ( $o ) { return (string) $o->get_meta( '_aod_commune' ); },
			__( 'Adresse', 'aod-client-dashboard' )      => function ( $o ) { return $o->get_billing_address_1(); },
			__( 'Livraison', 'aod-client-dashboard' )    => function ( $o ) { return (string) $o->get_meta( '_aod_delivery_type' ); },
			__( 'Articles', 'aod-client-dashboard' )     => function ( $o ) {
				$out = array();
				foreach ( $o->get_items() as $item ) {
					$out[] = $item->get_name() . ' x' . $item->get_quantity();
				}
				return implode( ' | ', $out );
			},
			__( 'Total', 'aod-client-dashboard' )        => function ( $o ) { return $o->get_total(); },
			__( 'Transporteur', 'aod-client-dashboard' ) => function ( $o ) { return (string) $o->get_meta( '_aod_ship_carrier' ); },
			__( 'Suivi', 'aod-client-dashboard' )        => function ( $o ) { return (string) $o->get_meta( '_aod_ship_tracking' ); },
			__( 'Assigné à', 'aod-client-dashboard' )    => function ( $o ) {
				$uid = (int) $o->get_meta( '_aod_assigned_confirmer' );
				$u   = $uid ? get_userdata( $uid ) : false;
				return $u ? $u->display_name : '';
			},
		);
	}

	/**
	 * Neutralise une valeur avant écriture dans le CSV.
	 *
	 * 🚨 DEUX PIÈGES, et le second coûte de l'argent.
	 *
	 * 1. INJECTION DE FORMULE. Un tableur exécute toute cellule commençant par
	 *    `=`, `+`, `-` ou `@`. Le nom et l'adresse viennent de l'ACHETEUR : un
	 *    champ `=HYPERLINK(...)` s'exécute à l'ouverture, chez le gérant, sur sa
	 *    machine. On préfixe d'une apostrophe, qui neutralise sans altérer la
	 *    lecture. (CSV injection / CWE-1236.)
	 * 2. TÉLÉPHONES MUTILÉS. Un numéro algérien commence par 0 ; Excel le lit
	 *    comme un nombre et écrit `551234567`. En COD, le téléphone EST le moyen
	 *    de livrer la commande — un export inexploitable pour rappeler un client
	 *    ne vaut rien. Le préfixe apostrophe force le texte et règle les deux.
	 *
	 * @param string $v Valeur brute.
	 * @return string
	 */
	public static function csv_safe( $v ) {
		$v = (string) $v;
		if ( '' === $v ) {
			return $v;
		}
		if ( in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) || preg_match( '/^0\d/', $v ) ) {
			return "'" . $v;
		}
		return $v;
	}

	/**
	 * Export CSV des commandes (demande owner du 2026-08-04).
	 *
	 * Pourquoi : le gérant rapproche l'argent encaissé par le transporteur des
	 * commandes livrées, et son comptable réclame un fichier. Aujourd'hui ça se
	 * fait écran par écran.
	 *
	 * 🔒 PÉRIMÈTRE. L'export passe par `scoped_orders()`, comme la liste : un
	 * confirmateur n'exporte que SES commandes. Écrire ici une requête directe
	 * aurait donné à quiconque ouvre la section un fichier contenant la boutique
	 * entière — nom, téléphone et adresse de chaque acheteur. Le périmètre n'est
	 * pas un confort d'affichage, c'est la garantie elle-même.
	 *
	 * ⚠️ C'est un TÉLÉCHARGEMENT, donc un lien (GET) et non un `fetch()`. Le nonce
	 * reste malgré tout le `aod_cd` de toute la section, vérifié par
	 * `check_ajax_referer()` — qui lit `$_REQUEST`, donc fonctionne en GET. Un
	 * nonce dédié aurait marché aussi, mais aurait fait de ce handler le SEUL à
	 * ne pas suivre la règle commune, et `test-permissions-sections.php` la garde
	 * précisément pour qu'aucun ne s'en écarte sans qu'on le voie.
	 *
	 * @return void
	 */
	public function ajax_export_orders() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'aod-client-dashboard' ), '', array( 'response' => 403 ) );
		}

		$slug = isset( $_GET['statut'] ) ? sanitize_key( wp_unslash( $_GET['statut'] ) ) : 'all';
		// MÊME résolution que le listing (`status_keys_for()`) : l'export exporte
		// ce qu'on regarde. Recopier ici `'wc-' . $slug` ferait de l'onglet
		// « Autres » un export de la boutique entière — le gérant croirait son
		// filtre respecté.
		$status = $this->status_keys_for( $slug );

		$cols = self::export_columns();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=commandes-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// BOM UTF-8 : sans lui, Excel lit le fichier en ANSI et rend illisibles les
		// accents ET tout l'arabe — sur une boutique bilingue, ce n'est pas un détail.
		fwrite( $out, "\xEF\xBB\xBF" );
		// Séparateur « ; » : c'est celui qu'attend Excel en locale FR/AR. Avec « , »
		// toutes les colonnes atterrissent dans la première, et le gérant conclut
		// que l'export est cassé.
		fputcsv( $out, array_keys( $cols ), ';' );

		// Pagination : on ne charge JAMAIS toute la boutique d'un coup. Une
		// `limit => -1` marche le jour de la livraison et meurt au millième client
		// — c'est exactement l'auto-DoS relevé en D23 sur le rafraîchissement du suivi.
		$page = 1;
		do {
			$res = $this->scoped_orders(
				array(
					'type'     => 'shop_order',
					'status'   => $status,
					'limit'    => 200,
					'page'     => $page,
					'paginate' => true,
					'orderby'  => 'date',
					'order'    => 'DESC',
				)
			);
			$orders = isset( $res->orders ) ? $res->orders : array();
			foreach ( $orders as $order ) {
				$row = array();
				foreach ( $cols as $extract ) {
					$row[] = self::csv_safe( $extract( $order ) );
				}
				fputcsv( $out, $row, ';' );
			}
			++$page;
		} while ( ! empty( $orders ) && $page <= ( isset( $res->max_num_pages ) ? (int) $res->max_num_pages : 1 ) );

		fclose( $out );
		exit;
	}

	public function scoped_orders( $args ) {
		// Cumul (Phase 42.3) : injecté AVANT le test de périmètre produit
		// ci-dessous — sinon un confirmateur au périmètre produit VIDE mais
		// assigné à des commandes précises verrait quand même TOUT (le retour
		// anticipé « pas de périmètre produit → non filtré » court-circuiterait
		// silencieusement ce filtre s'il était ajouté après).
		$assigned = $this->assignment_scope();
		if ( $assigned ) {
			$clause = array(
				'key'     => '_aod_assigned_confirmer',
				'value'   => $assigned,
				'compare' => '=',
			);
			$args['meta_query'] = ( ! empty( $args['meta_query'] ) ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- fusion AND avec un éventuel filtre déjà présent (ex. non-expédiées), jamais un OR qui élargirait ; clé indexable, gouverné par capability.
				? array( 'relation' => 'AND', $args['meta_query'], $clause )
				: array( $clause );
		}

		if ( ! $this->product_scope() ) {
			return wc_get_orders( $args );
		}
		add_filter( 'woocommerce_orders_table_query_clauses', array( $this, 'filter_scope_clauses' ) ); // HPOS.
		add_filter( 'posts_clauses', array( $this, 'filter_scope_clauses_cpt' ) );                      // CPT hérité.
		$res = wc_get_orders( $args );
		remove_filter( 'woocommerce_orders_table_query_clauses', array( $this, 'filter_scope_clauses' ) );
		remove_filter( 'posts_clauses', array( $this, 'filter_scope_clauses_cpt' ) );
		return $res;
	}

	/**
	 * Jointure SQL de périmètre : sous-requête DISTINCT (une commande contenant
	 * plusieurs produits affectés ne sort qu'une fois — pas de doublons ni de
	 * total faussé). Les deux clés `_product_id` (parent) et `_variation_id` sont
	 * testées : affecter un produit variable couvre toutes ses variations.
	 *
	 * On joint sur les LIGNES DE COMMANDE (`woocommerce_order_items` +
	 * `order_itemmeta`), pas sur `wc_order_product_lookup` : cette dernière est la
	 * table d'analytics de WooCommerce, remplie **en différé** par l'Action
	 * Scheduler. Une commande qui vient d'arriver n'y est pas encore — le
	 * confirmateur ne voyait donc PAS les commandes fraîches, celles-là mêmes
	 * qu'il doit rappeler. Et si l'analytics est désactivé ou le planificateur en
	 * carafe (fréquent en mutualisé), il ne voit plus jamais rien de nouveau.
	 * Les lignes de commande, elles, sont écrites dans la transaction de création :
	 * toujours à jour, dans les deux modes de stockage (HPOS et CPT hérité).
	 *
	 * @param string $orders_id_col Colonne « ID de commande » de la requête hôte.
	 * @return string Clause JOIN à concaténer.
	 */
	protected function scope_join_sql( $orders_id_col ) {
		return self::product_orders_join_sql( $orders_id_col, $this->product_scope(), 'aod_scope' );
	}

	/**
	 * Jointure « commandes contenant l'un de ces produits », sur les LIGNES DE
	 * COMMANDE. Helper **partagé** : c'est la seule façon de garantir que la
	 * liste Commandes, les compteurs, l'Accueil et le Stock répondent tous la
	 * même chose à la même question.
	 *
	 * 🚨 Ne JAMAIS réécrire cette jointure sur `wc_order_product_lookup`. Ce point
	 * a déjà coûté un bug en production (corrigé en 5.5, `0898855` — « le
	 * confirmateur ne voyait pas les commandes fraîches ») puis **réapparu** dans
	 * `AOD_CD_Section_Home`, qui était resté sur la table d'analytics : les
	 * statistiques d'un confirmateur restreint et le filtre produit du gérant
	 * sous-comptaient les commandes récentes. Le raisonnement était écrit noir sur
	 * blanc ici et contredit à trois fichiers de distance.
	 *
	 * @param string $orders_id_col Colonne « ID de commande » de la requête hôte.
	 * @param int[]  $product_ids   Produits du périmètre (parents et/ou variations).
	 * @param string $alias         Préfixe d'alias, unique par jointure d'une même requête.
	 * @return string Clause JOIN à concaténer ('' si aucun produit).
	 */
	public static function product_orders_join_sql( $orders_id_col, $product_ids, $alias ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', (array) $product_ids ) ) );
		if ( ! $ids ) {
			return '';
		}
		// meta_value est une colonne texte : on compare à des chaînes quotées.
		$in = "'" . implode( "','", $ids ) . "'";
		$a  = preg_replace( '/[^a-z0-9_]/', '', (string) $alias );

		// Sous-requête DISTINCT : une commande contenant plusieurs produits du
		// périmètre ne sort qu'une fois (pas de doublon ni de total faussé).
		// Les deux clés sont testées : affecter un produit variable couvre toutes
		// ses variations.
		return " JOIN ( SELECT DISTINCT {$a}_oi.order_id"
			. " FROM {$wpdb->prefix}woocommerce_order_items {$a}_oi"
			. " JOIN {$wpdb->prefix}woocommerce_order_itemmeta {$a}_oim"
			. " ON {$a}_oim.order_item_id = {$a}_oi.order_item_id"
			. " WHERE {$a}_oi.order_item_type = 'line_item'"
			. " AND {$a}_oim.meta_key IN ('_product_id','_variation_id')"
			. " AND {$a}_oim.meta_value IN ({$in}) ) {$a}"
			. " ON {$a}.order_id = {$orders_id_col}";
	}

	/**
	 * Injecte la jointure de périmètre dans OrdersTableQuery (HPOS).
	 *
	 * @param array $clauses Clauses SQL de la requête.
	 * @return array
	 */
	public function filter_scope_clauses( $clauses ) {
		global $wpdb;
		if ( $this->product_scope() ) {
			$clauses['join'] .= $this->scope_join_sql( "{$wpdb->prefix}wc_orders.id" );
		}
		return $clauses;
	}

	/**
	 * Injecte la jointure de périmètre dans la WP_Query du stockage CPT hérité.
	 *
	 * @param array $clauses Clauses SQL de la requête.
	 * @return array
	 */
	public function filter_scope_clauses_cpt( $clauses ) {
		global $wpdb;
		if ( $this->product_scope() ) {
			$clauses['join'] .= $this->scope_join_sql( "{$wpdb->posts}.ID" );
		}
		return $clauses;
	}

	/* ============================================================
	 * AJAX : changement de statut de commande
	 * ========================================================== */

	public function ajax_order_status() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$status   = preg_replace( '/^wc-/', '', $status );

		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Commande introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		if ( ! $this->order_in_scope( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! array_key_exists( 'wc-' . $status, wc_get_order_statuses() ) ) {
			wp_send_json_error( array( 'message' => __( 'Statut invalide.', 'aod-client-dashboard' ) ), 400 );
		}

		// Traçabilité : lue AVANT le changement de statut — une commande déjà
		// confirmée par le passé garde son confirmateur d'origine (pas d'écrasement).
		$already_traced = '' !== (string) $order->get_meta( '_aod_confirmed_by' );

		$order->update_status( $status, __( 'Statut modifié depuis l’espace de gestion.', 'aod-client-dashboard' ) );

		// 🔁 47.2 — ON RECHARGE ENCORE, MAIS PLUS POUR LA MÊME RAISON. Ce
		// rechargement existait parce que le hook d'envoi écrivait le suivi dans
		// notre dos, sur une AUTRE instance de la commande. Le hook n'existe plus.
		// Il reste néanmoins nécessaire : `update_status()` écrit en base et notre
		// instance porte désormais un statut périmé.
		$order    = wc_get_order( $order_id );
		$tracking = $order ? $order->get_meta( '_aod_ship_tracking' ) : '';

		// Première confirmation : on enregistre qui a confirmé et quand (métas HPOS,
		// posées sur l'instance rechargée).
		if ( 'aod-confirmed' === $status && ! $already_traced && $order instanceof WC_Order ) {
			$order->update_meta_data( '_aod_confirmed_by', get_current_user_id() );
			$order->update_meta_data( '_aod_confirmed_at', current_time( 'mysql' ) );
			$order->save();
			$order->add_order_note(
				sprintf(
					/* translators: %s : nom de l'utilisateur ayant confirmé la commande. */
					__( 'Confirmée par %s.', 'aod-client-dashboard' ),
					wp_get_current_user()->display_name
				),
				0,
				true
			);
		}

		// 🔁 47.2 — `ship_cell` RESTE, `ship_status`/`ship_message` PARTENT.
		//
		// Les deux derniers disaient si le colis venait de partir à la
		// confirmation. Plus rien ne part à la confirmation : les garder aurait
		// été un écran qui commente un mécanisme supprimé — et leur branche
		// `disabled` renvoyait « Envoi automatique désactivé », un réglage qui
		// n'existe plus (47.2, D-2).
		//
		// ⚠️ `ship_cell`, LUI, EST INDISPENSABLE, et pour une raison NEUVE : la
		// cellule porte désormais un BOUTON dont l'existence dépend du statut.
		// Passer une commande à « Annulée » fait tomber son état à `none` — sans
		// ce re-rendu, le bouton « Envoyer » resterait affiché sur une commande
		// qui n'a plus à partir.
		$resp = array(
			'message'   => __( 'Statut mis à jour.', 'aod-client-dashboard' ),
			'tracking'  => $tracking,
			'ship_cell' => $order instanceof WC_Order ? $this->ship_cell_html( $order ) : '',
		);

		wp_send_json_success( $resp );
	}

	/**
	 * Assigne/réassigne/dé-assigne une commande à un confirmateur (Phase 42.4).
	 *
	 * GÉRANT UNIQUEMENT (`manage_woocommerce`, famille action-forte) — jamais
	 * `aod_cap_confirm_orders` : un confirmateur porteur de cette capability ne
	 * doit pas pouvoir se réassigner (ou réassigner à un pair) une commande —
	 * même piège que la Phase 21/31.1 (« une permission cohérente n'est pas une
	 * permission bornée »). Réassignable à TOUT statut : c'est un routage de
	 * visibilité, jamais un verrou sur l'historique déjà écrit
	 * (`_aod_confirmed_by`/`_aod_confirmed_at` restent intacts).
	 *
	 * `confirmer_id = 0` SUPPRIME la méta (jamais une valeur `'0'` — invariant
	 * 42.1 : absence = non assignée).
	 */
	public function ajax_order_assign() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$order_id     = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$confirmer_id = isset( $_POST['confirmer_id'] ) ? absint( $_POST['confirmer_id'] ) : 0;

		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Commande introuvable.', 'aod-client-dashboard' ) ), 404 );
		}

		$result = $this->apply_assignment( $order, $confirmer_id );
		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ), 400 );
		}

		wp_send_json_success( array(
			'message' => __( 'Assignation mise à jour.', 'aod-client-dashboard' ),
			'label'   => $result['label'],
		) );
	}

	/**
	 * Confiance zéro dans un `confirmer_id` posté : ne renvoie l'utilisateur QUE
	 * s'il est un VRAI confirmateur actif porteur de `aod_cap_confirm_orders` —
	 * jamais un compte arbitraire (administrateur, utilisateur sans rapport…).
	 * Extraite pour être partagée par `apply_assignment()` (42.4) et le
	 * pré-contrôle du lot `ajax_orders_bulk_assign()` (42.5) — une seule
	 * définition de « confirmateur valide ».
	 *
	 * Délègue à `AOD_CD_Roles::valid_confirmer()` (Phase 43.3 — mutualisé
	 * avec l'assignation des paniers abandonnés) ; méthode conservée ici
	 * (plutôt que ses appelants réécrits) pour ne rien changer aux sites
	 * d'appel existants (`apply_assignment()`, testé par réflexion).
	 *
	 * @param int $confirmer_id
	 * @return WP_User|false
	 */
	protected function valid_confirmer( $confirmer_id ) {
		return AOD_CD_Roles::valid_confirmer( $confirmer_id );
	}

	/**
	 * Écrit (ou supprime) `_aod_assigned_confirmer` sur une commande, avec
	 * validation « confiance zéro » du confirmateur ciblé (Phase 42.4).
	 *
	 * Extraite de `ajax_order_assign()` pour rester appelable par réflexion
	 * dans les tests — le handler AJAX se termine par `wp_send_json_*()`
	 * (`wp_die()`), donc jamais invoqué en direct dans le cliquet (même patron
	 * que `AOD_CD_Section_Abandoned::maybe_assign_converter()`, 42.2).
	 *
	 * `$confirmer_id = 0` SUPPRIME la méta (jamais une valeur `'0'` — invariant
	 * 42.1 : absence = non assignée).
	 *
	 * TROIS branches, et la Phase 43.4 en touche DEUX (la 3ᵉ, le refus, doit
	 * rester rigoureusement muette) :
	 *   · suppression → efface AUSSI `_aod_assigned_by` (voir plus bas) ;
	 *   · refus       → n'écrit RIEN, ni l'une ni l'autre ;
	 *   · écriture    → pose `_aod_assigned_by` = l'utilisateur courant.
	 *
	 * 🚨 Pourquoi la suppression doit effacer la signature (43.4). Cette méthode
	 * a un second appelant : `unassign_confirmer()` (42.7), qui libère les
	 * commandes d'un confirmateur supprimé. Si `_aod_assigned_by` survivait à la
	 * dé-assignation, la commande repartirait au tourniquet, serait réassignée
	 * PAR LE SYSTÈME, et afficherait pourtant « Admin » — une signature orpheline
	 * qui ment sur l'origine de l'assignation qu'elle prétend décrire.
	 *
	 * @param WC_Order $order
	 * @param int      $confirmer_id
	 * @return array{ok:bool,message:string,label:string} `message` n'est peuplé
	 *                que si `ok` est faux.
	 */
	protected function apply_assignment( $order, $confirmer_id ) {
		$confirmer_id = absint( $confirmer_id );

		if ( ! $confirmer_id ) {
			$order->delete_meta_data( '_aod_assigned_confirmer' );
			$order->delete_meta_data( '_aod_assigned_by' );
			$order->save();
			return array( 'ok' => true, 'message' => '', 'label' => __( 'Non assignée', 'aod-client-dashboard' ) );
		}

		$target = $this->valid_confirmer( $confirmer_id );
		if ( ! $target ) {
			return array( 'ok' => false, 'message' => __( 'Confirmateur invalide.', 'aod-client-dashboard' ), 'label' => '' );
		}

		$order->update_meta_data( '_aod_assigned_confirmer', $confirmer_id );
		// Signature de l'assignant (43.4) : ce chemin est manuel par construction
		// (gérant only, `ajax_order_assign()`), donc `get_current_user_id()` est
		// toujours un humain — jamais 0. La méta n'est posée QUE sur ce chemin :
		// le tourniquet, lui, ne signe pas (cf. `assign_least_loaded()`).
		$order->update_meta_data( '_aod_assigned_by', get_current_user_id() );
		$order->save();
		return array( 'ok' => true, 'message' => '', 'label' => $target->display_name );
	}

	/**
	 * Libère toutes les commandes assignées à un confirmateur (retour à
	 * « Non assignée ») — à appeler AVANT `wp_delete_user()` (42.7,
	 * `AOD_CD_Section_Team::ajax_delete_member()`).
	 *
	 * 🚨 Sans cet appel, supprimer un confirmateur laisse `_aod_assigned_confirmer`
	 * pointer vers un compte qui n'existe plus : `wp_delete_user()` ne réattribue
	 * que `post_author` (contenu WordPress standard), jamais cette méta métier.
	 * La commande reste alors marquée « assignée » — `count_unassigned()` teste
	 * une absence de méta, pas la validité de sa valeur, donc le filtre
	 * « Non assignées » ne la voit JAMAIS — pendant qu'aucun confirmateur ne
	 * peut plus la voir non plus (assignée à un ID fantôme). Silencieusement
	 * perdue pour tout le monde sauf le gérant, qui la croit prise en charge.
	 *
	 * Réutilise `apply_assignment( $order, 0 )` : MÊME code que la
	 * dé-assignation manuelle (42.4), jamais une seconde écriture de la même
	 * règle. Paginé par lots de 50 (leçon 31.6 : jamais `limit => -1` sans
	 * borne sur un compte qui peut porter des milliers de commandes au fil du
	 * temps).
	 *
	 * @param int $user_id
	 * @return int Nombre de commandes libérées.
	 */
	public function unassign_confirmer( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$freed = 0;
		$paged = 1;
		do {
			$result = wc_get_orders( array(
				'type'       => 'shop_order',
				'status'     => 'any',
				'limit'      => 50,
				'paged'      => $paged,
				'paginate'   => true,
				'meta_key'   => '_aod_assigned_confirmer', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- même clé indexable que count_active_assigned() ; borné aux commandes de CE confirmateur, jamais tout le catalogue.
				'meta_value' => $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- même requête, valeur bornée à un ID entier (patron count_active_assigned()).
			) );
			foreach ( $result->orders as $order ) {
				if ( $order instanceof WC_Order ) {
					$this->apply_assignment( $order, 0 );
					++$freed;
				}
			}
			++$paged;
		} while ( $paged <= (int) $result->max_num_pages );

		return $freed;
	}

	/**
	 * Rattrapage : assigne en LOT une sélection de commandes à un confirmateur
	 * (Phase 42.5). GÉRANT UNIQUEMENT (`manage_woocommerce`, action-forte —
	 * jamais `aod_cap_confirm_orders`, même raison que `ajax_order_assign()`).
	 *
	 * Règle D4-rattrapage : une commande DÉJÀ assignée est SAUTÉE **par défaut**
	 * (jamais écrasée silencieusement) — cet outil comble des absences.
	 *
	 * Phase 43.5 (décision D1 de A.20, tranchée avec l'owner) : il peut aussi
	 * ÉCRASER, mais uniquement sur demande EXPLICITE — case à cocher visible et
	 * décochée par défaut dans l'UI, jamais un second mode silencieux. Sans
	 * `overwrite`, le comportement est rigoureusement celui de la 42.5.
	 *
	 * 🚨 `overwrite` est un booléen de DESTRUCTION : il se lit en liste blanche
	 * (`'1' ===`), jamais par `! empty()` ni `absint()` — `'0'` et `'false'` sont
	 * des chaînes VRAIES pour `! empty()`, et une garde qui ne regarde pas la
	 * valeur ne la refuse pas (leçon 32.3, anti-bot).
	 *
	 * `confirmer_id` est validé UNE fois, avant tout traitement : un
	 * confirmateur invalide refuse l'AJAX entier (400), jamais un lot
	 * partiellement appliqué.
	 */
	public function ajax_orders_bulk_assign() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$confirmer_id = isset( $_POST['confirmer_id'] ) ? absint( $_POST['confirmer_id'] ) : 0;
		$target       = $confirmer_id ? $this->valid_confirmer( $confirmer_id ) : false;
		if ( ! $target ) {
			wp_send_json_error( array( 'message' => __( 'Confirmateur invalide.', 'aod-client-dashboard' ) ), 400 );
		}

		$raw       = isset( $_POST['order_ids'] ) ? (array) wp_unslash( $_POST['order_ids'] ) : array();
		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		if ( ! $order_ids ) {
			wp_send_json_error( array( 'message' => __( 'Aucune commande sélectionnée.', 'aod-client-dashboard' ) ), 400 );
		}

		$overwrite = isset( $_POST['overwrite'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['overwrite'] ) );

		$result = $this->bulk_assign( $order_ids, $confirmer_id, $overwrite );

		/* translators: %1$d: nombre assignées, %2$s: nom du confirmateur. */
		$message = sprintf( __( '%1$d commande(s) assignée(s) à %2$s.', 'aod-client-dashboard' ), count( $result['assigned'] ), $target->display_name );
		if ( $result['reassigned'] ) {
			// Nommé À PART, jamais fondu dans le total : le gérant doit voir ce
			// qu'il a RÉASSIGNÉ (geste destructif) distinctement de ce qu'il a
			// simplement comblé — même exigence de clarté que « ignorée(s) ».
			$message .= ' ' . sprintf(
				/* translators: %d: nombre de commandes réassignées (elles étaient déjà assignées à quelqu'un d'autre). */
				__( '%d déjà assignée(s) ont été RÉASSIGNÉE(S).', 'aod-client-dashboard' ),
				count( $result['reassigned'] )
			);
		}

		wp_send_json_success( array(
			'assigned'     => $result['assigned'],
			'assigned_n'   => count( $result['assigned'] ),
			'reassigned'   => $result['reassigned'],
			'reassigned_n' => count( $result['reassigned'] ),
			'skipped'      => $result['skipped'],
			'missing'      => $result['missing'],
			'label'        => $target->display_name,
			'message'      => $message,
		) );
	}

	/**
	 * Cœur du rattrapage groupé (42.5), extrait de `ajax_orders_bulk_assign()`
	 * pour rester appelable par réflexion dans les tests (même patron que
	 * `apply_assignment()`, 42.4 — le handler AJAX se termine par
	 * `wp_send_json_*()`). Le `$confirmer_id` est supposé DÉJÀ validé par
	 * l'appelant (`valid_confirmer()`) — cette méthode ne porte que la règle de
	 * lot, et depuis la 43.5 elle en porte DEUX, selon `$overwrite` :
	 *
	 * | Commande…                    | `$overwrite = false` (défaut) | `= true`     |
	 * |------------------------------|-------------------------------|--------------|
	 * | non assignée                 | `assigned`                    | `assigned`   |
	 * | assignée à QUELQU'UN D'AUTRE | `skipped` (D4-rattrapage)     | `reassigned` |
	 * | assignée à LA CIBLE          | `skipped`                     | `skipped`    |
	 * | introuvable                  | `missing`                     | `missing`    |
	 *
	 * 🚨 La 3ᵉ ligne est le piège de la 43.5, et elle vaut dans les DEUX modes.
	 * Réassigner X à X ne change rien à l'assignation — mais réécrirait
	 * `_aod_assigned_by` (43.4), faisant passer une assignation POSÉE PAR LE
	 * TOURNIQUET pour un choix délibéré du gérant : la colonne afficherait
	 * « Admin » sur une décision que personne n'a prise. On saute donc.
	 *
	 * `reassigned` est TOUJOURS un tableau vide quand `$overwrite` est faux :
	 * c'est la forme même de la non-régression 42.5.
	 *
	 * @param int[] $order_ids
	 * @param int   $confirmer_id
	 * @param bool  $overwrite    Écraser une assignation existante (43.5, D1 de A.20).
	 * @return array{assigned:int[],reassigned:int[],skipped:int,missing:int}
	 */
	protected function bulk_assign( $order_ids, $confirmer_id, $overwrite = false ) {
		$assigned     = array();
		$reassigned   = array();
		$skipped      = 0;
		$missing      = 0;
		$confirmer_id = absint( $confirmer_id );

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				++$missing;
				continue;
			}

			$deja = '' !== (string) $order->get_meta( '_aod_assigned_confirmer' );
			if ( $deja ) {
				// Déjà la cible : rien à changer, dans les DEUX modes (voir le
				// docblock — sinon on signerait une assignation automatique).
				if ( (int) $order->get_meta( '_aod_assigned_confirmer' ) === $confirmer_id ) {
					++$skipped;
					continue;
				}
				if ( ! $overwrite ) {
					++$skipped; // D4-rattrapage : jamais écrasée sans demande explicite.
					continue;
				}
			}

			$order->update_meta_data( '_aod_assigned_confirmer', $confirmer_id );
			// Signature de l'assignant (43.4). Posée ICI et pas héritée
			// d'`apply_assignment()` : ce chemin n'y délègue pas (règle de lot
			// distincte, cf. 42.5) — donc la signature aussi se pose à la main.
			// Une commande sautée ne reçoit rien : elle garde la signature de
			// l'assignation qu'elle porte déjà.
			$order->update_meta_data( '_aod_assigned_by', get_current_user_id() );
			$order->save();

			if ( $deja ) {
				$reassigned[] = (int) $order_id;
			} else {
				$assigned[] = (int) $order_id;
			}
		}

		return array(
			'assigned'   => $assigned,
			'reassigned' => $reassigned,
			'skipped'    => $skipped,
			'missing'    => $missing,
		);
	}

	/* ============================================================
	 * Transporteur par commande (Phase 47.1)
	 * ========================================================== */

	/**
	 * Le transporteur posté est-il choisissable ? — confiance zéro (47.1).
	 *
	 * Jumeau de `valid_confirmer()` : une seule définition de « valide », et elle
	 * est plus stricte que « existe ». `configured()` et non `carriers()` :
	 * proposer les 29 du catalogue laisserait choisir une société dont personne
	 * n'a saisi les identifiants — le colis ne partirait jamais, et l'écran
	 * l'aurait pourtant accepté.
	 *
	 * `''` = retour au réglage de la boutique (décision D-1) : c'est une valeur
	 * LÉGITIME, pas une absence de choix à rejeter.
	 *
	 * @param string $carrier_id
	 * @return bool
	 */
	protected function valid_carrier_choice( $carrier_id ) {
		$carrier_id = (string) $carrier_id;
		if ( '' === $carrier_id ) {
			return true;
		}
		if ( ! class_exists( 'AOD_Shipping' ) ) {
			return false;
		}
		return array_key_exists( $carrier_id, AOD_Shipping::instance()->configured() );
	}

	/**
	 * Écrit (ou retire) le transporteur choisi sur une commande (47.1).
	 *
	 * Extraite du handler AJAX pour rester appelable par réflexion dans les tests
	 * — le handler se termine par `wp_send_json_*()` (`wp_die()`). Même patron
	 * qu'`apply_assignment()` (42.4).
	 *
	 * 🚨 UNE COMMANDE DÉJÀ EXPÉDIÉE EST REFUSÉE. Le colis existe chez un
	 * transporteur : réécrire le choix ne le déplacerait pas, il rendrait
	 * seulement l'écran faux. La garde est SERVEUR et pas seulement au rendu —
	 * l'écran d'un confirmateur peut dater d'avant l'envoi.
	 *
	 * `$carrier_id = ''` SUPPRIME la méta (jamais une chaîne vide stockée —
	 * même invariant que 42.1 : absence = « comme la boutique »).
	 *
	 * @param WC_Order $order
	 * @param string   $carrier_id
	 * @return array{ok:bool,message:string,label:string,value:string}
	 */
	protected function apply_carrier( $order, $carrier_id ) {
		$carrier_id = (string) $carrier_id;

		if ( '' !== (string) $order->get_meta( '_aod_ship_tracking' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Commande déjà expédiée : son transporteur ne peut plus changer.', 'aod-client-dashboard' ),
				'label'   => '',
				'value'   => '',
			);
		}

		if ( ! $this->valid_carrier_choice( $carrier_id ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Transporteur inconnu ou non configuré (Livraison → Transporteurs).', 'aod-client-dashboard' ),
				'label'   => '',
				'value'   => '',
			);
		}

		if ( '' === $carrier_id ) {
			$order->delete_meta_data( AOD_Shipping::META_CARRIER_CHOICE );
		} else {
			$order->update_meta_data( AOD_Shipping::META_CARRIER_CHOICE, $carrier_id );
		}
		$order->save();

		// Le libellé rendu est celui du transporteur EFFECTIF : après un retour
		// au défaut, l'écran doit afficher ce que la boutique fera, pas un vide.
		$effective = AOD_Shipping::instance()->carrier_for( wc_get_order( $order->get_id() ) );

		return array(
			'ok'      => true,
			'message' => __( 'Transporteur mis à jour.', 'aod-client-dashboard' ),
			'label'   => is_wp_error( $effective ) ? __( 'Non configuré', 'aod-client-dashboard' ) : $effective->label(),
			'value'   => $carrier_id,
		);
	}

	/**
	 * Choisit le transporteur d'UNE commande (47.1).
	 *
	 * Cap `aod_cap_confirm_orders` — GÉRANT **ET** CONFIRMATEUR, et cela s'écarte
	 * volontairement d'`ajax_order_assign()` (gérant strict). Assigner, c'est
	 * répartir du travail ; choisir un transporteur, c'est faire le travail. Le
	 * confirmateur déclenchera l'envoi (47.2) : lui refuser la destination
	 * reviendrait à lui faire déclencher un envoi qu'il ne maîtrise pas.
	 * Le périmètre reste borné par `order_in_scope()` — il ne peut router que
	 * SES commandes.
	 */
	public function ajax_order_carrier() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_Shipping' ) ) {
			wp_send_json_error( array( 'message' => __( 'Module de livraison indisponible.', 'aod-client-dashboard' ) ), 400 );
		}

		$order_id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$carrier_id = isset( $_POST['carrier_id'] ) ? sanitize_key( wp_unslash( $_POST['carrier_id'] ) ) : '';

		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Commande introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		if ( ! $this->order_in_scope( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$result = $this->apply_carrier( $order, $carrier_id );
		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ), 400 );
		}

		wp_send_json_success( array(
			'message' => $result['message'],
			'label'   => $result['label'],
			'value'   => $result['value'],
			// 🚨 47.3 — la cellule « Suivi » est renvoyée parce que `data-note-perdue`
			// DÉPEND du transporteur qu'on vient de changer. Sans ça, le bouton
			// « Envoyer » garderait l'avertissement de l'ancien transporteur : le
			// confirmateur serait prévenu à tort, ou — bien pire — pas prévenu du tout.
			'ship_cell' => $this->ship_cell_html( wc_get_order( $order_id ) ),
		) );
	}

	/**
	 * Applique un transporteur à une SÉLECTION de commandes (47.1).
	 *
	 * Même cap et même périmètre que le geste unitaire — un lot n'est pas un
	 * pouvoir de plus, c'est le même geste répété. Rend compte PAR COMMANDE
	 * (appliquée / ignorée / introuvable) sur le patron de `bulk_assign()` :
	 * un total muet laisserait croire que tout est passé.
	 */
	public function ajax_orders_bulk_carrier() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_Shipping' ) ) {
			wp_send_json_error( array( 'message' => __( 'Module de livraison indisponible.', 'aod-client-dashboard' ) ), 400 );
		}

		$carrier_id = isset( $_POST['carrier_id'] ) ? sanitize_key( wp_unslash( $_POST['carrier_id'] ) ) : '';
		// Le lot n'accepte PAS le retour au défaut : « Transporteur… » est un
		// libellé d'invite, pas un choix. Un lot vidé par mégarde reviendrait à
		// annuler en masse des décisions déjà prises, sans que rien ne le montre.
		if ( '' === $carrier_id || ! $this->valid_carrier_choice( $carrier_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Transporteur inconnu ou non configuré (Livraison → Transporteurs).', 'aod-client-dashboard' ) ), 400 );
		}

		$raw       = isset( $_POST['order_ids'] ) ? (array) wp_unslash( $_POST['order_ids'] ) : array();
		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		if ( ! $order_ids ) {
			wp_send_json_error( array( 'message' => __( 'Aucune commande sélectionnée.', 'aod-client-dashboard' ) ), 400 );
		}

		$result  = $this->bulk_carrier( $order_ids, $carrier_id );
		$carrier = AOD_Shipping::instance()->carrier( $carrier_id );

		/* translators: %1$d : nombre de commandes routées, %2$s : nom du transporteur. */
		$message = sprintf( __( '%1$d commande(s) routée(s) vers %2$s.', 'aod-client-dashboard' ), count( $result['applied'] ), $carrier ? $carrier->label() : $carrier_id );

		// 🚨 LA CELLULE « SUIVI » REPART AVEC, COMME SUR LES DEUX CHEMINS UNITAIRES.
		//
		// `data-note-perdue` dépend du transporteur RÉSOLU (cf. `ship_cell_html()`) :
		// router un lot change ce transporteur, donc invalide le drapeau de chaque
		// ligne touchée. `ajax_order_carrier()` et `ajax_order_save_info()` renvoient
		// la cellule pour cette raison exacte ; le chemin GROUPÉ, lui, ne renvoyait
		// que le widget de transporteur, et le drapeau restait celui d'avant.
		//
		// Conséquence mesurée par la revue : router 5 commandes portant une note
		// livreur d'EcoTrack vers Yalidine laissait `data-note-perdue="0"` — l'écran
		// n'avertissait plus, les 5 colis partaient et la note du confirmateur était
		// perdue en silence. Le cas miroir criait au loup sur une note qui, elle,
		// serait bien transmise. *Un même fait rendu à trois endroits doit repartir
		// des trois, ou il n'en est vrai qu'à deux.*
		$cells = array();
		foreach ( $result['applied'] as $applied_id ) {
			$applied_order = wc_get_order( $applied_id );
			if ( $applied_order instanceof WC_Order ) {
				$cells[ (string) $applied_id ] = $this->ship_cell_html( $applied_order );
			}
		}

		wp_send_json_success( array(
			'applied'   => $result['applied'],
			'applied_n' => count( $result['applied'] ),
			'skipped'   => $result['skipped'],
			'missing'   => $result['missing'],
			'label'     => $carrier ? $carrier->label() : $carrier_id,
			'cells'     => $cells,
			'message'   => $message,
		) );
	}

	/**
	 * Cœur du routage groupé (47.1), extrait pour rester appelable par réflexion
	 * dans les tests (même patron que `bulk_assign()`, 42.5).
	 *
	 * | Commande…                          | Résultat  |
	 * |------------------------------------|-----------|
	 * | dans le périmètre, non expédiée     | `applied` |
	 * | hors périmètre OU déjà expédiée      | `skipped` |
	 * | introuvable                         | `missing` |
	 *
	 * 🚨 `order_in_scope()` est réévalué ICI, commande par commande, et pas une
	 * fois pour le lot : un identifiant hors périmètre glissé dans le POST doit
	 * être ignoré même si les autres passent. C'est la garde d'`ajax_order_status()`
	 * appliquée au lot — la liste ne fait que CACHER ce qui est hors périmètre.
	 *
	 * @param int[]  $order_ids
	 * @param string $carrier_id Supposé DÉJÀ validé par l'appelant.
	 * @return array{applied:int[],skipped:int,missing:int}
	 */
	protected function bulk_carrier( $order_ids, $carrier_id ) {
		$applied = array();
		$skipped = 0;
		$missing = 0;

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order ) {
				++$missing;
				continue;
			}
			if ( ! $this->order_in_scope( $order ) ) {
				++$skipped;
				continue;
			}
			$res = $this->apply_carrier( $order, $carrier_id );
			if ( $res['ok'] ) {
				$applied[] = (int) $order_id;
			} else {
				++$skipped; // Déjà expédiée : le colis existe, on ne la touche pas.
			}
		}

		return array(
			'applied' => $applied,
			'skipped' => $skipped,
			'missing' => $missing,
		);
	}

	/* ============================================================
	 * L'envoi au transporteur, devenu un geste (Phase 47.2)
	 * ========================================================== */

	/**
	 * Nombre maximal de commandes qu'un lot d'envoi accepte de démarrer.
	 *
	 * 🚨 CE N'EST PAS UN PLAFOND DE REQUÊTE — il n'y en a plus besoin. Le handler
	 * traite UNE commande, le navigateur boucle. Ce plafond-ci empêche un
	 * ACCIDENT : un « tout cocher » malheureux, ou un POST forgé à 500 lignes qui
	 * occuperait le poste du confirmateur pendant des heures.
	 *
	 * ⚠️ POURQUOI 50 ET PAS « ASSEZ GRAND ». La page affiche 20 lignes
	 * (`$per_page`), donc « tout cocher » vaut 20. 50 couvre largement toute
	 * sélection réelle — y compris à travers deux pages — et refuse une sélection
	 * manifestement involontaire. Le refus est DIT à l'écran : une troncature
	 * muette ferait croire que le lot entier est parti (§C.2).
	 *
	 * Une seule définition, côté serveur, exposée au JS via `window.AOD_CD` : si
	 * l'écran et le handler refusaient à deux seuils différents, l'un des deux
	 * mentirait.
	 */
	const MAX_LOT_ENVOI = 50;

	/**
	 * Expédie UNE commande au transporteur — le geste de la 47.2.
	 *
	 * 🚨 UNE SEULE COMMANDE PAR REQUÊTE, ET C'EST LA DÉCISION STRUCTURANTE.
	 * `create_parcel()` a un plafond de 10 s chez les six transporteurs (aucun ne
	 * surcharge `AOD_Carrier::HTTP_TIMEOUT`), et une commande stop-desk à cache
	 * froid ajoute un second appel : ≈ 20 s. Un lot traité ICI, c'est N × 10 à
	 * 20 s dans une seule requête — **trois commandes suffisent à toucher le mur
	 * des 30 s** d'un mutualisé, et à la coupure il aurait expédié une partie du
	 * lot sans rendre compte de rien.
	 *
	 * ⚠️ Et le mesurer sur la pile de dev donnerait une réponse faussement
	 * rassurante : `max_execution_time` y vaut 0. C'est la leçon `FS_METHOD` de
	 * TEMPLATE.md §3 — éprouver une couche dans le contexte où on la développe
	 * plutôt que dans celui où elle sert, c'est se garantir un vert permanent.
	 *
	 * → Le lot est une boucle SÉQUENTIELLE côté navigateur (`section-orders.js`),
	 * avec progression et compte rendu par commande. La question du plafond PHP
	 * disparaît au lieu d'être arbitrée à l'aveugle.
	 *
	 * ⚠️ NE CRÉE AUCUN SECOND CHEMIN D'ENVOI : délègue à `AOD_Shipping::ship_order()`,
	 * qui passe par le goulot `send_parcel()` (verrou 31.4 + relecture après
	 * verrou). `test-verrou-envoi.php` R3 exige un SEUL appel à `create_parcel()`
	 * dans le plugin.
	 */
	public function ajax_ship_send() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_Shipping' ) ) {
			wp_send_json_error( array( 'message' => __( 'Module de livraison indisponible.', 'aod-client-dashboard' ) ), 400 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Commande introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		if ( ! $this->order_in_scope( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$result = AOD_Shipping::instance()->ship_order( $order );

		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();

			// 🚨 LE STATUT HTTP SUIT LA NATURE DU REFUS, ET CE N'EST PAS DU
			// PURISME. Une panne transporteur n'est PAS une requête invalide :
			// répondre 4xx la fait journaliser par le navigateur comme une erreur
			// réseau. Mesuré au navigateur — un lot de 2 commandes chez un
			// transporteur injoignable produisait 2 erreurs console ; à 50, la
			// console devient illisible et les portes de recette (qui comptent les
			// erreurs console) rougissent sur un comportement NORMAL du produit.
			//
			//   · état de la commande / paramètre     → 400, c'est bien l'appel qui est fautif ;
			//   · concurrence (verrou, doublon évité) → 409, comme `ajax_send()` le fait déjà ;
			//   · panne transporteur                  → 200 + `success:false`.
			//     L'appel était valide, la réponse est un ÉCHEC MÉTIER : elle
			//     s'affiche, elle ne s'alarme pas.
			$statut = 200;
			if ( in_array( $code, array( 'aod_ship_bad_order', 'aod_ship_not_cod', 'aod_ship_already_shipped' ), true ) ) {
				$statut = 400;
			} elseif ( in_array( $code, array( 'aod_ship_in_progress', 'aod_ship_already_sent' ), true ) ) {
				$statut = 409;
			}

			// Le CODE remonte tel quel : c'est lui qui permet au lot de distinguer
			// « déjà expédiée » (bénin, on passe) d'une vraie panne transporteur
			// (à signaler). Un message seul obligerait le JS à lire du français.
			wp_send_json_error( array(
				'code'      => $code,
				'message'   => $result->get_error_message(),
				'ship_cell' => $this->ship_cell_html( wc_get_order( $order_id ) ),
			), $statut );
		}

		wp_send_json_success( array(
			'message'   => sprintf(
				/* translators: %s : numéro de suivi du colis. */
				__( 'Colis créé ✓ Suivi : %s', 'aod-client-dashboard' ),
				isset( $result['tracking'] ) ? $result['tracking'] : ''
			),
			'tracking'  => isset( $result['tracking'] ) ? $result['tracking'] : '',
			'ship_cell' => $this->ship_cell_html( wc_get_order( $order_id ) ),
		) );
	}

	/**
	 * Supprime définitivement une sélection de commandes (suppression groupée).
	 *
	 * Reçoit une liste d'IDs ; vérifie la capacité de suppression sur chacune,
	 * puis force la suppression via l'API WooCommerce (HPOS-safe).
	 */
	public function ajax_delete_orders() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'delete_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$raw = isset( $_POST['order_ids'] ) ? (array) wp_unslash( $_POST['order_ids'] ) : array();
		$ids = array_filter( array_map( 'absint', $raw ) );
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Aucune commande sélectionnée.', 'aod-client-dashboard' ) ), 400 );
		}

		$deleted = array();
		$failed  = 0;
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			// Garde-fou par commande : on ne supprime que ce que l'utilisateur a le droit de supprimer.
			if ( ! $order || ! current_user_can( 'delete_shop_order', $id ) && ! current_user_can( 'manage_woocommerce' ) ) {
				$failed++;
				continue;
			}
			// …et seulement dans son périmètre produits. Les 4 handlers de commande
			// posent déjà cette garde ; la suppression groupée y échappait, alors
			// que c'est l'action la moins réversible de la section.
			if ( ! $this->order_in_scope( $order ) ) {
				$failed++;
				continue;
			}
			if ( $order->delete( true ) ) {
				$deleted[] = $id;
			} else {
				$failed++;
			}
		}

		if ( empty( $deleted ) ) {
			wp_send_json_error( array( 'message' => __( 'Aucune commande n’a pu être supprimée.', 'aod-client-dashboard' ) ), 400 );
		}

		$message = sprintf(
			/* translators: %d: nombre de commandes supprimées. */
			_n( '%d commande supprimée.', '%d commandes supprimées.', count( $deleted ), 'aod-client-dashboard' ),
			count( $deleted )
		);
		if ( $failed > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: nombre de commandes non supprimées. */
				_n( '%d commande n’a pas pu être supprimée.', '%d commandes n’ont pas pu être supprimées.', $failed, 'aod-client-dashboard' ),
				$failed
			);
		}

		wp_send_json_success( array(
			'message' => $message,
			'deleted' => $deleted,
		) );
	}

	/*
	 * 47.3 — `ajax_order_note()` A ÉTÉ SUPPRIMÉE, ET SON ABSENCE EST LA
	 * FONCTIONNALITÉ.
	 *
	 * Elle écrivait dans l'historique WooCommerce (`add_order_note()`), c'est-à-dire
	 * dans une liste append-only mêlée aux notes système — que le LIVREUR ne lit
	 * jamais. La note du confirmateur a maintenant une destination : elle est un
	 * champ du formulaire d'édition, enregistrée par `ajax_order_save_info()` sous
	 * `AOD_Carrier::META_DRIVER_NOTE`, et transmise dans la charge utile de
	 * 26 transporteurs sur 29.
	 *
	 * ⚠️ NE PAS LA REMETTRE « pour garder l'historique » : l'historique Woo n'a pas
	 * bougé. Il est toujours écrit par `ajax_order_save_info()` (traçabilité des
	 * ÉDITIONS) et toujours affiché par `render_order_detail()`. Ce qui a disparu,
	 * c'est le détournement de ce journal en boîte à consignes.
	 *
	 * `test-note-livreur.php` R4 lit `has_action()` — pas la source — et rougira,
	 * y compris si le handler revient sous forme de closure.
	 */

	/* ============================================================
	 * AJAX : détail d'une commande (modale)
	 * ========================================================== */

	public function ajax_order_detail() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Commande introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		if ( ! $this->order_in_scope( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		ob_start();
		$this->render_order_detail( $order );
		$html = ob_get_clean();

		wp_send_json_success( array(
			'title' => sprintf( __( 'Commande #%s', 'aod-client-dashboard' ), $order->get_order_number() ),
			'html'  => $html,
		) );
	}

	/* ============================================================
	 * AJAX : modification des infos client / livraison d'une commande
	 * ========================================================== */

	public function ajax_order_save_info() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Commande introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		if ( ! $this->order_in_scope( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$addr     = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
		$wilaya   = isset( $_POST['wilaya'] ) ? absint( $_POST['wilaya'] ) : 0;
		$commune  = isset( $_POST['commune'] ) ? sanitize_text_field( wp_unslash( $_POST['commune'] ) ) : '';
		$delivery = isset( $_POST['delivery_type'] ) ? sanitize_key( wp_unslash( $_POST['delivery_type'] ) ) : '';

		if ( '' === trim( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Le téléphone est obligatoire.', 'aod-client-dashboard' ) ), 400 );
		}
		if ( ! in_array( $delivery, array( 'home', 'desk' ), true ) ) {
			$delivery = $order->get_meta( '_aod_delivery_type' );
		}

		$has_data = class_exists( 'AOD_COD_Data' );
		if ( $has_data && $wilaya ) {
			if ( '' === AOD_COD_Data::wilaya_name( $wilaya ) ) {
				wp_send_json_error( array( 'message' => __( 'Wilaya invalide.', 'aod-client-dashboard' ) ), 400 );
			}
			if ( '' !== $commune && ! AOD_COD_Data::commune_valid( $wilaya, $commune ) ) {
				wp_send_json_error( array( 'message' => __( 'Commune invalide pour cette wilaya.', 'aod-client-dashboard' ) ), 400 );
			}
		}

		// Adresse de facturation + livraison (mêmes clés que la création de commande COD).
		$parts = array(
			'first_name' => $name,
			'last_name'  => '',
			'phone'      => $phone,
			'address_1'  => $addr,
			'country'    => 'DZ',
		);
		if ( '' !== $commune ) {
			$parts['city'] = $commune;
		}
		if ( $wilaya && $has_data ) {
			$parts['state'] = 'DZ-' . str_pad( (string) $wilaya, 2, '0', STR_PAD_LEFT );
		}
		$order->set_address( $parts, 'billing' );
		$order->set_address( $parts, 'shipping' );

		// Méta spécifiques à la livraison COD.
		if ( $wilaya && $has_data ) {
			$order->update_meta_data( '_aod_wilaya_code', $wilaya );
			$order->update_meta_data( '_aod_wilaya_name', AOD_COD_Data::wilaya_name( $wilaya ) );
			$order->update_meta_data( '_aod_wilaya_name_ar', AOD_COD_Data::wilaya_name_ar( $wilaya ) );
		}
		if ( '' !== $commune ) {
			$order->update_meta_data( '_aod_commune', $commune );
			if ( $wilaya && $has_data ) {
				$order->update_meta_data( '_aod_commune_ar', AOD_COD_Data::commune_name_ar( $wilaya, $commune ) );
			}
		}
		if ( $delivery ) {
			$order->update_meta_data( '_aod_delivery_type', $delivery );
		}

		// 47.3 — La note pour le livreur.
		//
		// `sanitize_textarea_field()` et JAMAIS `wp_kses_post()` : cette chaîne ne
		// s'affiche pas dans une page, elle part dans la charge utile envoyée à une
		// API tierce. Y laisser passer du HTML n'a aucun sens et pourrait finir
		// recopié tel quel sur un bordereau.
		//
		// 🚨 LE CLIP EST ICI, À LA SOURCE — pas seulement à l'envoi. Une note trop
		// longue échouerait CÔTÉ TRANSPORTEUR, donc après le verrou d'envoi (31.4) et
		// au milieu d'un lot : une partie des colis partis, l'autre non. Le champ
		// annonce la même borne (`maxlength`), et `AOD_Carrier::driver_note()` la
		// réapplique pour une chaîne arrivée par un autre chemin (WP-CLI, import).
		// Trois clips, UN SEUL nombre : `AOD_Carrier::NOTE_MAX`.
		if ( isset( $_POST['note'] ) && class_exists( 'AOD_Carrier' ) ) {
			$note = sanitize_textarea_field( wp_unslash( $_POST['note'] ) );
			$note = trim( $note );
			if ( '' !== $note && function_exists( 'mb_substr' ) ) {
				$note = mb_substr( $note, 0, AOD_Carrier::NOTE_MAX, 'UTF-8' );
			} elseif ( '' !== $note ) {
				$note = substr( $note, 0, AOD_Carrier::NOTE_MAX );
			}
			$order->update_meta_data( AOD_Carrier::META_DRIVER_NOTE, $note );
		}

		// Articles : variantes, quantités, retrait de lignes ET ajout de nouveaux
		// articles (recalcul des totaux). Chaque ligne postée porte, soit un « id »
		// (ligne existante à modifier/retirer), soit un « new_product » (produit du
		// catalogue à ajouter à la commande).
		$items_changed = false;
		$items_json    = isset( $_POST['items_json'] ) ? wp_unslash( $_POST['items_json'] ) : '';
		$items_in      = $items_json ? json_decode( $items_json, true ) : null;
		if ( is_array( $items_in ) && $items_in ) {
			// Plan de modification + ajouts + quantités cumulées par produit (paliers de prix).
			$plan      = array(); // iid => modification d'une ligne existante.
			$additions = array(); // nouveaux articles à créer.
			$group_qty = array(); // quantité cumulée par produit (existants + ajouts).
			foreach ( $items_in as $row ) {
				// Nouvel article : le gérant ajoute un produit du catalogue.
				$new_pid = isset( $row['new_product'] ) ? absint( $row['new_product'] ) : 0;
				if ( $new_pid ) {
					$product = wc_get_product( $new_pid );
					// On n'ajoute que des produits simples achetables (pas de parent variable).
					if ( ! $product instanceof WC_Product || ! $product->exists() || $product->is_type( 'variable' ) ) {
						continue;
					}
					$qty     = isset( $row['qty'] ) ? max( 1, absint( $row['qty'] ) ) : 1;
					$opts_in = ( isset( $row['opts'] ) && is_array( $row['opts'] ) ) ? $row['opts'] : array();
					list( $valid_opts, $supplement ) = $this->resolve_item_options( $product, $opts_in, null );
					$group_qty[ $new_pid ] = ( isset( $group_qty[ $new_pid ] ) ? $group_qty[ $new_pid ] : 0 ) + $qty;
					$additions[]           = array(
						'product'    => $product,
						'qty'        => $qty,
						'opts'       => $valid_opts,
						'supplement' => $supplement,
					);
					continue;
				}

				$iid  = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
				$item = $iid ? $order->get_item( $iid ) : null;
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				if ( ! empty( $row['remove'] ) ) {
					$plan[ $iid ] = array( 'remove' => true );
					continue;
				}
				$qty     = isset( $row['qty'] ) ? max( 1, absint( $row['qty'] ) ) : (int) $item->get_quantity();
				$product = $item->get_product();
				$opts_in = ( isset( $row['opts'] ) && is_array( $row['opts'] ) ) ? $row['opts'] : array();
				list( $valid_opts, $supplement ) = $this->resolve_item_options( $product, $opts_in, $item );
				if ( $product ) {
					$pid               = $product->get_id();
					$group_qty[ $pid ] = ( isset( $group_qty[ $pid ] ) ? $group_qty[ $pid ] : 0 ) + $qty;
				}
				$plan[ $iid ] = array(
					'remove'     => false,
					'qty'        => $qty,
					'opts'       => $valid_opts,
					'product'    => $product,
					'supplement' => $supplement,
				);
			}

			// Refus si la commande se retrouve vide (aucune ligne conservée ni ajoutée).
			$kept = count( $additions );
			foreach ( $plan as $p ) {
				if ( empty( $p['remove'] ) ) {
					$kept++;
				}
			}
			if ( ( $plan || $additions ) && 0 === $kept ) {
				wp_send_json_error( array( 'message' => __( 'Une commande doit contenir au moins un article.', 'aod-client-dashboard' ) ), 400 );
			}

			// 1) Modifications / retraits des lignes existantes.
			foreach ( $plan as $iid => $p ) {
				$item = $order->get_item( $iid );
				if ( ! $item ) {
					continue;
				}
				if ( ! empty( $p['remove'] ) ) {
					$order->remove_item( $iid );
					$items_changed = true;
					continue;
				}
				$product = $p['product'];
				if ( $product ) {
					$qty_total = isset( $group_qty[ $product->get_id() ] ) ? $group_qty[ $product->get_id() ] : $p['qty'];
					$unit      = $this->tier_unit_price( $product, $qty_total ) + $p['supplement'];
				} else {
					// Produit supprimé du catalogue : on garde le prix unitaire actuel.
					$prev_qty = max( 1, (int) $item->get_quantity() );
					$unit     = ( (float) $item->get_subtotal() / $prev_qty ) + $p['supplement'];
				}
				$line_total = $unit * $p['qty'];

				$item->set_quantity( $p['qty'] );
				$item->set_subtotal( $line_total );
				$item->set_total( $line_total );
				foreach ( $p['opts'] as $slabel => $val ) {
					$item->update_meta_data( $slabel, $val );
				}
				$item->save();
				$items_changed = true;
			}

			// 2) Ajout des nouveaux articles.
			foreach ( $additions as $a ) {
				$product = $a['product'];
				$item_id = $order->add_product( $product, $a['qty'] );
				if ( ! $item_id ) {
					continue;
				}
				$item = $order->get_item( $item_id );
				if ( ! $item ) {
					continue;
				}
				$qty_total  = isset( $group_qty[ $product->get_id() ] ) ? $group_qty[ $product->get_id() ] : $a['qty'];
				$unit       = $this->tier_unit_price( $product, $qty_total ) + $a['supplement'];
				$line_total = $unit * $a['qty'];

				$item->set_subtotal( $line_total );
				$item->set_total( $line_total );
				foreach ( $a['opts'] as $slabel => $val ) {
					$item->add_meta_data( $slabel, $val, true );
				}
				$item->save();
				$items_changed = true;
			}
		}

		$order->save();

		if ( $items_changed ) {
			$order->calculate_totals();
		}

		// Total manuel (livraison incluse) : le gérant force le montant à encaisser
		// (remise accordée au téléphone, arrondi négocié…). Appliqué APRÈS le recalcul
		// automatique pour avoir le dernier mot. Le drapeau « total_override » n'est posé
		// par le JS que si le champ a réellement été modifié à la main.
		$total_changed = false;
		if ( ! empty( $_POST['total_override'] ) && isset( $_POST['order_total'] ) ) {
			$manual = (float) wc_format_decimal( wp_unslash( $_POST['order_total'] ) );
			if ( $manual >= 0 && abs( $manual - (float) $order->get_total() ) > 0.001 ) {
				$order->set_total( $manual );
				$order->update_meta_data( '_aod_manual_total', $manual );
				$order->save();
				$total_changed = true;
			}
		}

		// Note d'historique récapitulant ce qui a changé.
		if ( $items_changed && $total_changed ) {
			$note = __( 'Infos client / livraison, articles et total modifiés depuis l’espace de gestion.', 'aod-client-dashboard' );
		} elseif ( $items_changed ) {
			$note = __( 'Infos client / livraison et articles modifiés depuis l’espace de gestion.', 'aod-client-dashboard' );
		} elseif ( $total_changed ) {
			$note = __( 'Infos client / livraison et total forcé manuellement depuis l’espace de gestion.', 'aod-client-dashboard' );
		} else {
			$note = __( 'Infos client / livraison modifiées depuis l’espace de gestion.', 'aod-client-dashboard' );
		}
		$order->add_order_note( $note, 0, true );

		$order = wc_get_order( $order_id );
		ob_start();
		$this->render_order_detail( $order );
		$html = ob_get_clean();

		wp_send_json_success( array(
			'message' => __( 'Commande mise à jour.', 'aod-client-dashboard' ),
			'title'   => sprintf( __( 'Commande #%s', 'aod-client-dashboard' ), $order->get_order_number() ),
			'html'    => $html,
			// 🚨 47.3 — même raison qu'en `ajax_order_carrier()` : la note vient
			// peut-être de changer, et `data-note-perdue` en dépend. Sans ce renvoi,
			// un gérant qui écrit une note puis clique « Envoyer » dans la foulée ne
			// serait PAS averti qu'elle ne partira pas — le défaut muet exact que
			// cette étape ferme.
			'ship_cell' => $this->ship_cell_html( wc_get_order( $order_id ) ),
		) );
	}

	/**
	 * Résout les variantes choisies d'une ligne et le supplément de prix associé.
	 *
	 * Pour une ligne existante ($item non nul), une variante non postée retombe sur la
	 * valeur déjà enregistrée. Pour un nouvel article ($item nul), seules les valeurs
	 * postées sont prises en compte.
	 *
	 * @param WC_Product|false          $product Produit de la ligne.
	 * @param array                     $opts_in Variantes postées (label => valeur).
	 * @param WC_Order_Item_Product|null $item    Ligne existante, ou null pour un ajout.
	 * @return array{0:array<string,string>,1:float} [ variantes valides, supplément total ].
	 */
	protected function resolve_item_options( $product, $opts_in, $item = null ) {
		$valid_opts = array();
		$supplement = 0.0;
		if ( ! $product ) {
			return array( $valid_opts, $supplement );
		}
		foreach ( AOD_CD_Section_Products::instance()->get_product_options( $product ) as $sec ) {
			$slabel = (string) $sec['label'];
			if ( isset( $opts_in[ $slabel ] ) ) {
				$chosen = sanitize_text_field( (string) $opts_in[ $slabel ] );
			} else {
				$chosen = $item ? (string) $item->get_meta( $slabel ) : '';
			}
			$match = null;
			foreach ( $sec['values'] as $val ) {
				if ( $val['name'] === $chosen ) {
					$match = $val;
					break;
				}
			}
			if ( $match ) {
				$valid_opts[ $slabel ] = $match['name'];
				if ( '' !== $match['price'] ) {
					$supplement += (float) $match['price'];
				}
			} elseif ( '' !== $chosen ) {
				$valid_opts[ $slabel ] = $chosen; // valeur héritée inconnue : conservée sans supplément.
			}
		}
		return array( $valid_opts, $supplement );
	}

	/**
	 * Catalogue des produits ajoutables à une commande (produits simples publiés),
	 * avec prix d'affichage et variantes disponibles. Sert au sélecteur « Ajouter un
	 * article » de la modale : le JS reconstruit la ligne à partir de ces données.
	 *
	 * @param WC_Order $order Commande courante (pour la devise, non stockée dans le JSON).
	 * @return array<int,array{name:string,price:float,options:array}>
	 */
	protected function orderable_catalog( $order ) {
		$products = wc_get_products( array(
			'status'  => 'publish',
			'type'    => 'simple',
			'limit'   => -1,
			'orderby' => 'title',
			'order'   => 'ASC',
		) );
		$catalog = array();
		foreach ( $products as $p ) {
			if ( ! $p instanceof WC_Product ) {
				continue;
			}
			$secs = array();
			foreach ( AOD_CD_Section_Products::instance()->get_product_options( $p ) as $sec ) {
				$values = array();
				foreach ( $sec['values'] as $v ) {
					$values[] = array(
						'name'  => $v['name'],
						'price' => ( '' !== $v['price'] ) ? (float) $v['price'] : 0,
					);
				}
				if ( $values ) {
					$secs[] = array(
						'label'  => (string) $sec['label'],
						'values' => $values,
					);
				}
			}
			$catalog[ (int) $p->get_id() ] = array(
				'name'    => $p->get_name(),
				'price'   => (float) wc_get_price_to_display( $p ),
				'options' => $secs,
			);
		}
		return $catalog;
	}

	/**
	 * Prix unitaire d'un produit pour une quantité donnée, offres de lot appliquées.
	 *
	 * Réplique la logique du formulaire COD : on retient le prix par unité de la plus
	 * grande offre dont le nombre d'unités est atteint, s'il est inférieur au prix de base.
	 *
	 * @param WC_Product $product
	 * @param int        $qty
	 * @return float
	 */
	protected function tier_unit_price( $product, $qty ) {
		$base = (float) wc_get_price_to_display( $product );
		if ( ! $product || $product->is_type( 'variation' ) ) {
			return $base;
		}
		$offers = AOD_CD_Section_Products::instance()->get_product_offers( $product );
		if ( ! $offers ) {
			return $base;
		}
		$unit     = $base;
		$best_qty = 1;
		foreach ( $offers as $o ) {
			$oqty = (int) $o['qty'];
			$u    = $oqty > 0 ? (float) $o['price'] / $oqty : 0; // price = prix total du lot.
			if ( $oqty >= 2 && $u > 0 && $u < $base && $qty >= $oqty && $oqty >= $best_qty ) {
				$best_qty = $oqty;
				$unit     = $u;
			}
		}
		return $unit;
	}

	/**
	 * Formulaire d'édition des infos client / livraison (affiché dans la modale détail).
	 *
	 * @param WC_Order $order
	 */
	protected function render_order_edit_form( $order ) {
		$wilaya_code = (int) $order->get_meta( '_aod_wilaya_code' );
		$commune     = $order->get_meta( '_aod_commune' );
		$delivery    = $order->get_meta( '_aod_delivery_type' );
		$has_data    = class_exists( 'AOD_COD_Data' );

		$full_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		echo '<form class="aod-cd-form aod-cd-od-edit" data-order="' . esc_attr( $order->get_id() ) . '" hidden>';

		// 🔁 2026-08-09 — L'ÉDITION REPREND LES DEUX CARTES DE LA VUE LECTURE.
		//
		// Retour owner, trois fois de suite : « ça reste simple ». Il avait raison, et
		// la cause n'était pas la peau des champs — c'était l'ABSENCE DE STRUCTURE.
		// Le volet en lecture montre deux encarts nommés (Client / Livraison) ; passer
		// en édition les faisait disparaître au profit d'une pile plate de sept champs
		// où rien ne disait plus ce qui relevait de l'acheteur et ce qui relevait du
		// colis. Deux vues du MÊME objet qui ne se ressemblaient pas.
		// Les champs sont donc regroupés dans `.aod-cd-od-box`, la classe exacte des
		// encarts de lecture — même grille, même creux, mêmes intitulés.
		//
		// 🔒 AUCUNE CHAÎNE NOUVELLE : « Client » et « Livraison » sont déjà traduits,
		// c'est la vue lecture qui les pose (cf. `render_order_detail()`). Aucun
		// passage par `make-translations.sh` n'est dû ici.
		//
		// ⚠️ L'ORDRE DES CHAMPS CHANGE, et c'est le coût assumé : « Téléphone » et
		// « Type de livraison » partageaient une rangée alors qu'ils appartiennent à
		// deux sujets différents. Un gérant habitué au tabulateur retrouvera les mêmes
		// champs, pas dans le même ordre. Le gain — deux blocs qu'on lit d'un coup au
		// lieu de sept lignes à déchiffrer — vaut ce dérangement une fois.
		//
		// ⚠️ Le JS n'est PAS touché : ses deux accès à ce formulaire sont
		// `form.querySelector( '.aod-cd-od-wilaya' )` (l. 1116-1117 de
		// `section-orders.js`) et une boucle sur `[name=…]` (l. 1241) — deux
		// recherches par DESCENDANCE, insensibles à l'enveloppe. Vérifié avant
		// d'écrire, pas après.
		echo '<div class="aod-cd-od-grid">';

		echo '<div class="aod-cd-od-box aod-cd-od-boxform"><h4>' . esc_html__( 'Client', 'aod-client-dashboard' ) . '</h4>';
		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . AOD_CD_Icon::svg( 'user', array( 'size' => 15 ) ) . esc_html__( 'Nom du client', 'aod-client-dashboard' ) . '</span>';
		echo '<input type="text" name="name" value="' . esc_attr( $full_name ) . '"></label>';
		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . AOD_CD_Icon::svg( 'phone', array( 'size' => 15 ) ) . esc_html__( 'Téléphone', 'aod-client-dashboard' ) . ' *</span>';
		echo '<input type="text" name="phone" required value="' . esc_attr( $order->get_billing_phone() ) . '"></label>';
		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . AOD_CD_Icon::svg( 'home', array( 'size' => 15 ) ) . esc_html__( 'Adresse', 'aod-client-dashboard' ) . '</span>';
		echo '<input type="text" name="address" value="' . esc_attr( $order->get_billing_address_1() ) . '"></label>';
		echo '</div>';

		echo '<div class="aod-cd-od-box aod-cd-od-boxform"><h4>' . esc_html__( 'Livraison', 'aod-client-dashboard' ) . '</h4>';
		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . AOD_CD_Icon::svg( 'shipping', array( 'size' => 15 ) ) . esc_html__( 'Type de livraison', 'aod-client-dashboard' ) . '</span>';
		echo '<select name="delivery_type">';
		echo '<option value="home" ' . selected( $delivery, 'home', false ) . '>' . esc_html__( 'À domicile', 'aod-client-dashboard' ) . '</option>';
		echo '<option value="desk" ' . selected( $delivery, 'desk', false ) . '>' . esc_html__( 'Stop-desk (bureau)', 'aod-client-dashboard' ) . '</option>';
		echo '</select></label>';

		// Wilaya.
		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . AOD_CD_Icon::svg( 'tracking', array( 'size' => 15 ) ) . esc_html__( 'Wilaya', 'aod-client-dashboard' ) . '</span>';
		echo '<select name="wilaya" class="aod-cd-od-wilaya">';
		echo '<option value="">' . esc_html__( '—', 'aod-client-dashboard' ) . '</option>';
		if ( $has_data ) {
			foreach ( AOD_COD_Data::places() as $w ) {
				$label = AOD_COD_Data::label( $w['name'], isset( $w['name_ar'] ) ? $w['name_ar'] : '' );
				echo '<option value="' . esc_attr( $w['code'] ) . '" ' . selected( $wilaya_code, (int) $w['code'], false ) . '>'
					. esc_html( sprintf( '%02d - %s', $w['code'], $label ) ) . '</option>';
			}
		}
		echo '</select></label>';

		// Commune (peuplée pour la wilaya courante, recalculée en JS au changement).
		// `map` et non `tracking` : les deux existent au catalogue, et la wilaya porte
		// déjà l'épingle (`tracking` = map-pin). Deux épingles identiques l'une sous
		// l'autre n'auraient rien distingué — la carte pour le territoire, l'épingle
		// pour le point.
		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . AOD_CD_Icon::svg( 'map', array( 'size' => 15 ) ) . esc_html__( 'Commune', 'aod-client-dashboard' ) . '</span>';
		echo '<select name="commune" class="aod-cd-od-commune">';
		echo '<option value="">' . esc_html__( '—', 'aod-client-dashboard' ) . '</option>';
		if ( $has_data && $wilaya_code ) {
			foreach ( AOD_COD_Data::communes( $wilaya_code ) as $c ) {
				$label = AOD_COD_Data::label( $c['name'], isset( $c['name_ar'] ) ? $c['name_ar'] : '' );
				echo '<option value="' . esc_attr( $c['name'] ) . '" ' . selected( $commune, $c['name'], false ) . '>'
					. esc_html( $label ) . '</option>';
			}
		} elseif ( '' !== $commune ) {
			echo '<option value="' . esc_attr( $commune ) . '" selected>' . esc_html( $commune ) . '</option>';
		}
		echo '</select></label>';

		echo '</div>'; // /box Livraison
		echo '</div>'; // /grid

		// 47.3 — LA NOTE DU CONFIRMATEUR, et elle est ici pour une raison.
		//
		// Elle a longtemps vécu dans une colonne du tableau, derrière un bouton qui
		// ouvrait une invite et écrivait dans l'historique WooCommerce. Ce n'était pas
		// une note : c'était un journal, et rien de ce journal ne partait chez le
		// livreur. Elle rejoint donc le formulaire d'édition — même enregistrement,
		// même capability, aucun handler AJAX de plus.
		//
		// ⚠️ `<textarea>` NU, jamais l'éditeur riche : cette chaîne part dans une API
		// tierce, pas dans une page. Du HTML y serait au mieux ignoré, au pire recopié
		// tel quel sur un bordereau. Même raison pour `sanitize_textarea_field()` à
		// l'enregistrement plutôt que `wp_kses_post()`.
		//
		// La borne vient de `AOD_Carrier::NOTE_MAX` — un seul nombre pour le champ, le
		// clip serveur et le clip d'envoi. L'écrire en dur ici en ferait un second,
		// qui mentirait le jour où l'autre changerait.
		$note_max = class_exists( 'AOD_Carrier' ) ? (int) AOD_Carrier::NOTE_MAX : 255;
		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . esc_html__( 'Note pour le livreur', 'aod-client-dashboard' ) . '</span>';
		echo '<textarea name="note" rows="3" maxlength="' . esc_attr( $note_max ) . '">'
			. esc_textarea( (string) $order->get_meta( class_exists( 'AOD_Carrier' ) ? AOD_Carrier::META_DRIVER_NOTE : '_aod_cd_confirmer_note' ) )
			. '</textarea></label>';
		echo '<p class="aod-cd-note">'
			. esc_html( sprintf(
				/* translators: %d : nombre maximal de caractères. */
				__( 'Consigne transmise au livreur avec le colis (%d caractères maximum). Certains transporteurs ne la reçoivent pas : l’écran d’envoi vous le dira.', 'aod-client-dashboard' ),
				$note_max
			) )
			. '</p>';

		// Articles : variantes + quantité, avec possibilité de retirer une ligne.
		//
		// 🚨 39.5 : ce bloc N'EST PLUS conditionné à `if ( $items )`. Une commande
		// abandonnée convertie SANS produit (39.4) part à zéro article ; l'ancien
		// `if ( $items )` cachait alors « Ajouter un article », les variantes ET le
		// champ Total — le confirmateur ne pouvait plus REMPLIR la commande qu'il
		// venait de créer. Le conteneur `.aod-cd-od-items` est rendu même vide (le JS
		// y ajoute les lignes), l'ajout est conditionné au CATALOGUE (pas aux items),
		// le Total est toujours là. Sur une commande AVEC articles, le rendu est
		// identique à avant (la boucle itère, comme auparavant).
		$items = $order->get_items();
		echo '<h4 class="aod-cd-od-sub">' . esc_html__( 'Articles', 'aod-client-dashboard' ) . '</h4>';
		if ( ! $items ) {
			echo '<p class="aod-cd-note">' . esc_html__( 'Aucun article pour l’instant. Ajoutez ci-dessous les produits dictés par le client au téléphone.', 'aod-client-dashboard' ) . '</p>';
		}
		echo '<div class="aod-cd-od-items">';
		foreach ( $items as $item_id => $item ) {
			$product = $item->get_product();
			$options = $product ? AOD_CD_Section_Products::instance()->get_product_options( $product ) : array();

			echo '<div class="aod-cd-od-item" data-item="' . esc_attr( $item_id ) . '">';
			echo '<div class="aod-cd-od-item-head">';
			echo '<strong>' . esc_html( $item->get_name() ) . '</strong>';
			echo '<label class="aod-cd-check aod-cd-od-item-rm"><input type="checkbox" class="aod-cd-od-itemremove"> ' . esc_html__( 'Retirer', 'aod-client-dashboard' ) . '</label>';
			echo '</div>';

			echo '<div class="aod-cd-od-item-fields">';
			foreach ( $options as $sec ) {
				$slabel  = (string) $sec['label'];
				$current = (string) $item->get_meta( $slabel );
				$found   = false;
				echo '<label class="aod-cd-field"><span class="aod-cd-label">' . esc_html( $slabel ) . '</span>';
				echo '<select class="aod-cd-od-itemopt" data-label="' . esc_attr( $slabel ) . '">';
				foreach ( $sec['values'] as $val ) {
					$price  = ( '' !== $val['price'] ) ? (float) $val['price'] : 0;
					$suffix = $price > 0 ? ' (+' . wp_strip_all_tags( wc_price( $price, array( 'currency' => $order->get_currency() ) ) ) . ')' : '';
					$sel    = selected( $current, $val['name'], false );
					if ( '' !== $sel ) {
						$found = true;
					}
					echo '<option value="' . esc_attr( $val['name'] ) . '" ' . $sel . '>' . esc_html( $val['name'] . $suffix ) . '</option>';
				}
				if ( ! $found && '' !== $current ) {
					echo '<option value="' . esc_attr( $current ) . '" selected>' . esc_html( $current ) . '</option>';
				}
				echo '</select></label>';
			}
			echo '<label class="aod-cd-field aod-cd-od-item-qty"><span class="aod-cd-label">' . esc_html__( 'Quantité', 'aod-client-dashboard' ) . '</span>';
			echo '<input type="number" min="1" step="1" class="aod-cd-od-itemqty" value="' . esc_attr( $item->get_quantity() ) . '"></label>';
			echo '</div>'; // /item-fields
			echo '</div>'; // /item
		}
		echo '</div>'; // /items

		// Ajout d'un article : sélecteur de produit + bouton (nouvelle ligne créée en
		// JS). Conditionné au CATALOGUE, jamais aux items (39.5) : une commande vide
		// doit pouvoir être remplie.
		$catalog = $this->orderable_catalog( $order );
		if ( $catalog ) {
			echo '<div class="aod-cd-od-additem">';
			echo '<label class="aod-cd-field"><span class="aod-cd-label">' . esc_html__( 'Ajouter un article', 'aod-client-dashboard' ) . '</span>';
			echo '<select class="aod-cd-od-addpick">';
			echo '<option value="">' . esc_html__( '— Choisir un produit —', 'aod-client-dashboard' ) . '</option>';
			foreach ( $catalog as $pid => $c ) {
				$plabel = $c['name'];
				if ( $c['price'] > 0 ) {
					$plabel .= ' — ' . wp_strip_all_tags( wc_price( $c['price'], array( 'currency' => $order->get_currency() ) ) );
				}
				echo '<option value="' . esc_attr( $pid ) . '">' . esc_html( $plabel ) . '</option>';
			}
			echo '</select></label>';
			echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-od-additem-btn">＋ ' . esc_html__( 'Ajouter', 'aod-client-dashboard' ) . '</button>';
			echo '</div>';
			echo '<script type="application/json" class="aod-cd-od-catalog">' . wp_json_encode( $catalog ) . '</script>';
		}

		echo '<p class="aod-cd-note aod-cd-od-items-note">' . esc_html__( 'Le total est recalculé automatiquement (prix de base + suppléments des variantes).', 'aod-client-dashboard' ) . '</p>';

		// Total à payer (livraison incluse) — modifiable à la main pour forcer le
		// montant. Toujours rendu (39.5) : sur une commande vide, get_total() = 0 et
		// le confirmateur le fixe à la valeur dictée au téléphone.
		echo '<h4 class="aod-cd-od-sub">' . esc_html__( 'Total', 'aod-client-dashboard' ) . '</h4>';
		echo '<label class="aod-cd-field aod-cd-od-total-field"><span class="aod-cd-label">' . esc_html__( 'Total à payer (livraison incluse)', 'aod-client-dashboard' ) . '</span>';
		echo '<input type="number" min="0" step="0.01" class="aod-cd-od-total" value="' . esc_attr( wc_format_decimal( $order->get_total(), 2 ) ) . '"></label>';
		echo '<input type="hidden" name="total_override" class="aod-cd-od-total-override" value="0">';
		echo '<p class="aod-cd-note">' . esc_html__( 'Laissez ce montant tel quel pour un calcul automatique. Modifiez-le pour forcer le total à encaisser (ex. remise accordée au téléphone).', 'aod-client-dashboard' ) . '</p>';

		// Carte des communes par wilaya pour le cascade JS (uniquement les valeurs nécessaires).
		if ( $has_data ) {
			$map = array();
			foreach ( AOD_COD_Data::places() as $w ) {
				$list = array();
				foreach ( $w['communes'] as $c ) {
					$list[] = array(
						'v' => $c['name'],
						'l' => AOD_COD_Data::label( $c['name'], isset( $c['name_ar'] ) ? $c['name_ar'] : '' ),
					);
				}
				$map[ (int) $w['code'] ] = $list;
			}
			echo '<script type="application/json" class="aod-cd-od-wilmap">' . wp_json_encode( $map ) . '</script>';
		}

		echo '<div class="aod-cd-form-foot">';
		echo '<button type="submit" class="aod-cd-btn aod-cd-btn-primary">' . esc_html__( 'Enregistrer', 'aod-client-dashboard' ) . '</button>';
		echo '<button type="button" class="aod-cd-btn aod-cd-od-edit-cancel">' . esc_html__( 'Annuler', 'aod-client-dashboard' ) . '</button>';
		echo '<span class="aod-cd-form-msg"></span>';
		echo '</div>';

		echo '</form>';
	}

	/**
	 * Contenu HTML du détail d'une commande (produits, adresse, livraison, historique).
	 *
	 * @param WC_Order $order
	 */
	protected function render_order_detail( $order ) {
		$wilaya_code = (int) $order->get_meta( '_aod_wilaya_code' );
		$wilaya      = $order->get_meta( '_aod_wilaya_name' );
		if ( ! $wilaya && $wilaya_code && class_exists( 'AOD_COD_Data' ) ) {
			$wilaya = AOD_COD_Data::wilaya_name( $wilaya_code );
		}
		$commune  = $order->get_meta( '_aod_commune' );
		$delivery = $order->get_meta( '_aod_delivery_type' );
		$tracking = $order->get_meta( '_aod_ship_tracking' );
		$phone    = $order->get_billing_phone();
		$name     = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$addr     = $order->get_billing_address_1();
		// En-tête : sélecteur de statut (39.2 — MÊME widget que la liste, statut
		// choisissable dans le flux de conversion, câblé par initOrderEdit côté JS)
		// + date + bouton de modification.
		echo '<div class="aod-cd-od-head">';
		echo '<span class="aod-cd-od-meta">';
		echo $this->status_select_html( $order->get_id(), 'wc-' . $order->get_status(), $this->status_labels() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup échappé dans status_select_html() (idem render_order_row:236).
		// Sélecteur d'assignation (42.4) : GÉRANT UNIQUEMENT — un confirmateur ne
		// voit ni le sélecteur ni le nom de l'assigné (c'est toujours lui, ou
		// personne d'atteignable pour lui de toute façon).
		if ( current_user_can( 'manage_woocommerce' ) ) {
			echo $this->assign_select_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup échappé dans assign_select_html().
				$order->get_id(),
				(int) $order->get_meta( '_aod_assigned_confirmer' ),
				AOD_CD_Roles::eligible_confirmers( array(), true )
			);
		}
		echo '<span class="aod-cd-od-date">' . esc_html( wc_format_datetime( $order->get_date_created(), 'd/m/Y H:i' ) ) . '</span>';
		echo '</span>';
		echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-od-edit-toggle">' . AOD_CD_Icon::svg( 'edit', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Modifier', 'aod-client-dashboard' ) . '</button>';
		echo '</div>';

		// Bloc client / livraison (vue en lecture).
		echo '<div class="aod-cd-od-readview">';
		echo '<div class="aod-cd-od-grid">';

		echo '<div class="aod-cd-od-box"><h4>' . esc_html__( 'Client', 'aod-client-dashboard' ) . '</h4><ul class="aod-cd-od-list">';
		echo '<li>' . AOD_CD_Icon::svg( 'user', array( 'size' => 15 ) ) . ' ' . esc_html( $name ? $name : '—' ) . '</li>';
		echo '<li>' . AOD_CD_Icon::svg( 'phone', array( 'size' => 15 ) ) . ' ' . ( $phone ? '<a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a>' : '—' ) . '</li>';
		if ( $phone ) {
			$wa = AOD_CD_Section_Abandoned::wa_number( $phone );
			echo '<li><span class="aod-cd-call-actions">';
			echo '<a class="aod-cd-btn aod-cd-btn-sm" href="tel:' . esc_attr( $phone ) . '">' . AOD_CD_Icon::svg( 'phone', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Appeler', 'aod-client-dashboard' ) . '</a> ';
			echo '<a class="aod-cd-btn aod-cd-btn-sm aod-cd-btn--ghost" href="' . esc_url( 'https://wa.me/' . $wa ) . '" target="_blank" rel="noopener">' . AOD_CD_Icon::svg( 'whatsapp', array( 'size' => 15 ) ) . ' ' . esc_html__( 'WhatsApp', 'aod-client-dashboard' ) . '</a>';
			echo '</span></li>';
		}
		echo '</ul></div>';

		echo '<div class="aod-cd-od-box"><h4>' . esc_html__( 'Livraison', 'aod-client-dashboard' ) . '</h4><ul class="aod-cd-od-list">';
		echo '<li>' . AOD_CD_Icon::svg( 'tracking', array( 'size' => 15 ) ) . ' ' . esc_html( trim( ( $commune ? $commune : '' ) . ( $wilaya ? ( $commune ? ', ' : '' ) . $wilaya : '' ) ) ?: '—' ) . '</li>';
		if ( $addr ) {
			echo '<li>' . AOD_CD_Icon::svg( 'home', array( 'size' => 15 ) ) . ' ' . esc_html( $addr ) . '</li>';
		}
		if ( $delivery ) {
			echo '<li>' . AOD_CD_Icon::svg( 'shipping', array( 'size' => 15 ) ) . ' ' . esc_html( 'desk' === $delivery ? __( 'Stop-desk (bureau)', 'aod-client-dashboard' ) : __( 'À domicile', 'aod-client-dashboard' ) ) . '</li>';
		}
		if ( class_exists( 'AOD_Shipping' ) ) {
			$ship  = AOD_Shipping::instance();
			$badge = $ship->status_badge_html( $order, 22, true );
			if ( '' !== $badge ) {
				echo '<li class="aod-cd-ship">' . $badge // phpcs:ignore WordPress.Security.EscapeOutput
					. ( $tracking ? ' <code>' . esc_html( $tracking ) . '</code>' : '' ) . '</li>';
			}
		} elseif ( $tracking ) {
			echo '<li class="aod-cd-track">' . AOD_CD_Icon::svg( 'check', array( 'size' => 14 ) ) . ' ' . esc_html__( 'Suivi', 'aod-client-dashboard' ) . ' : <code>' . esc_html( $tracking ) . '</code></li>';
		}
		echo '</ul></div>';

		echo '</div>'; // /grid

		// Produits (vue lecture).
		echo '<h4 class="aod-cd-od-sub">' . esc_html__( 'Produits', 'aod-client-dashboard' ) . '</h4>';
		// `aod-cd-od-prods` : ce tableau-ci porte deux colonnes de NOMBRES (Qté,
		// Total) qui doivent s'aligner par la droite, comme celles de la liste des
		// commandes. Sans classe, la seule prise CSS était « toute `.aod-cd-table`
		// dans un corps de modale » — ce qui aurait aussi repeint le récapitulatif
		// d'envoi transporteur, dont les colonnes sont du texte.
		echo '<table class="aod-cd-table aod-cd-od-prods" style="min-width:0"><thead><tr>';
		echo '<th>' . esc_html__( 'Article', 'aod-client-dashboard' ) . '</th><th>' . esc_html__( 'Qté', 'aod-client-dashboard' ) . '</th><th>' . esc_html__( 'Total', 'aod-client-dashboard' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $order->get_items() as $item ) {
			echo '<tr>';
			echo '<td>' . esc_html( $item->get_name() );
			$variants = $this->item_variants( $item );
			if ( $variants ) {
				echo '<br><span class="aod-cd-variants">' . esc_html( implode( ' · ', $variants ) ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( $item->get_quantity() ) . '</td>';
			echo '<td>' . wp_kses_post( wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) ) . '</td>';
			echo '</tr>';
		}
		/*
		 * Décomposition du montant. Ces lignes portent les colonnes « Prix
		 * produit » et « Prix livraison » retirées du tableau (étape 5.4) : elles
		 * sont donc INCONDITIONNELLES — une livraison gratuite doit s'afficher à 0,
		 * pas disparaître.
		 *
		 * 🔴 « PRIX PRODUIT » NE SE DÉDUIT PAS DU TOTAL (corrigé le 2026-08-15).
		 * Il valait `get_total() − livraison`, c'est-à-dire un RESTE. Tant que le
		 * total d'une commande égale la somme de ses lignes plus la livraison, le
		 * reste tombe juste ; dès qu'il s'en écarte, la soustraction ABSORBE
		 * l'écart en silence et le fait passer pour un prix produit.
		 * Constaté sur deux commandes du même clone, dans les deux sens :
		 *   · #22 — lignes 10 500, livraison 300, total 11 100 → « prix produit »
		 *     affichait 10 800. Le prix de lot porté par la ligne était perdu.
		 *   · #23 — lignes 9 400 (dont 500 de supplément de variante), livraison
		 *     650, total 9 550 → « prix produit » affichait 8 900. Le supplément
		 *     avait disparu du récapitulatif.
		 * Dans les deux cas le tableau se contredisait LUI-MÊME, à trois lignes
		 * d'écart, sans que rien ne le signale.
		 *
		 * On additionne donc les lignes — la seule valeur qui dise ce qui a été
		 * vendu — et on NOMME l'écart s'il en reste un, au lieu de le noyer. Un
		 * écart visible est un problème qu'on peut traiter ; un écart absorbé est
		 * un chiffre faux qu'on recopie.
		 */
		$currency      = $order->get_currency();
		$shipping_cost = (float) $order->get_shipping_total();
		$products_cost = 0.0;
		foreach ( $order->get_items() as $item ) {
			$products_cost += (float) $item->get_total();
		}
		$fees = 0.0;
		foreach ( $order->get_items( 'fee' ) as $fee ) {
			$fees += (float) $fee->get_total();
		}
		$tax         = (float) $order->get_total_tax();
		$order_total = (float) $order->get_total();
		// Tolérance d'un centime : une somme de lignes arrondies ne retombe pas
		// toujours au centime sur le total, et signaler ça comme une anomalie
		// apprendrait au gérant à ignorer le signal.
		$gap = $order_total - ( $products_cost + $shipping_cost + $fees + $tax );
		if ( abs( $gap ) < 0.01 ) {
			$gap = 0.0;
		}

		echo '</tbody><tfoot>';
		echo '<tr><td colspan="2">' . esc_html__( 'Prix produit', 'aod-client-dashboard' ) . '</td><td>' . wp_kses_post( wc_price( $products_cost, array( 'currency' => $currency ) ) ) . '</td></tr>';
		echo '<tr><td colspan="2">' . esc_html__( 'Prix livraison', 'aod-client-dashboard' ) . '</td><td>' . wp_kses_post( wc_price( $shipping_cost, array( 'currency' => $currency ) ) ) . '</td></tr>';
		// Frais et taxes : émis SEULEMENT s'ils existent. Sur une boutique COD
		// algérienne ils valent zéro, et deux lignes à 0 dans chaque commande
		// noieraient les deux qui parlent.
		if ( abs( $fees ) >= 0.01 ) {
			echo '<tr><td colspan="2">' . esc_html__( 'Frais', 'aod-client-dashboard' ) . '</td><td>' . wp_kses_post( wc_price( $fees, array( 'currency' => $currency ) ) ) . '</td></tr>';
		}
		if ( abs( $tax ) >= 0.01 ) {
			echo '<tr><td colspan="2">' . esc_html__( 'Taxes', 'aod-client-dashboard' ) . '</td><td>' . wp_kses_post( wc_price( $tax, array( 'currency' => $currency ) ) ) . '</td></tr>';
		}
		if ( 0.0 !== $gap ) {
			echo '<tr class="aod-cd-od-gap"><td colspan="2">' . esc_html__( 'Écart à vérifier', 'aod-client-dashboard' )
				. '<br><span class="aod-cd-od-gaphint">' . esc_html__( 'Le total enregistré ne correspond pas à la somme des lignes ci-dessus.', 'aod-client-dashboard' ) . '</span></td>'
				. '<td>' . wp_kses_post( wc_price( $gap, array( 'currency' => $currency ) ) ) . '</td></tr>';
		}
		echo '<tr><td colspan="2"><strong>' . esc_html__( 'Total', 'aod-client-dashboard' ) . '</strong></td><td><strong>' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong></td></tr>';
		echo '</tfoot></table>';

		echo '</div>'; // /readview

		// Formulaire d'édition (client / livraison + articles), masqué par défaut, basculé en JS.
		$this->render_order_edit_form( $order );

		// Notes & historique de la commande.
		$notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
		if ( $notes ) {
			echo '<h4 class="aod-cd-od-sub">' . esc_html__( 'Notes & historique', 'aod-client-dashboard' ) . '</h4>';
			echo '<ul class="aod-cd-od-notes">';
			foreach ( $notes as $note ) {
				$is_system = ( 'system' === $note->added_by || '' === (string) $note->added_by );
				$author    = $is_system ? __( 'Système', 'aod-client-dashboard' ) : $note->added_by;
				echo '<li class="' . ( $is_system ? 'is-system' : 'is-user' ) . '">';
				echo '<span class="aod-cd-od-notedate">' . esc_html( date_i18n( 'd/m H:i', strtotime( $note->date_created ) ) ) . ' · ' . esc_html( $author ) . '</span> ';
				echo wp_kses_post( wpautop( wptexturize( $note->content ) ) );
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<h4 class="aod-cd-od-sub">' . esc_html__( 'Notes & historique', 'aod-client-dashboard' ) . '</h4>';
			echo '<p class="aod-cd-muted">' . esc_html__( 'Aucune note pour le moment.', 'aod-client-dashboard' ) . '</p>';
		}
	}

	/**
	 * Réduit le stock des produits composant un pack présent dans une commande.
	 *
	 * WooCommerce ne réduit que le stock du produit-pack lui-même ; ce hook répercute
	 * la baisse sur chaque produit inclus (quantité du pack × quantité commandée).
	 * Un drapeau sur la commande empêche tout double décompte.
	 *
	 * @param int $order_id
	 */
	public function reduce_pack_components_stock( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' === $order->get_meta( '_aod_pack_stock_done' ) ) {
			return;
		}
		$touched = false;
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || '1' !== (string) $product->get_meta( '_aod_is_pack' ) ) {
				continue;
			}
			$components = $product->get_meta( '_aod_pack_items' );
			if ( ! is_array( $components ) || ! $components ) {
				continue;
			}
			$order_qty = (int) $item->get_quantity();
			foreach ( $components as $comp ) {
				$cid = isset( $comp['id'] ) ? (int) $comp['id'] : 0;
				$cq  = isset( $comp['qty'] ) ? (int) $comp['qty'] : 0;
				$cp  = $cid ? wc_get_product( $cid ) : null;
				if ( ! $cp || ! $cp->managing_stock() || $cq < 1 ) {
					continue;
				}
				wc_update_product_stock( $cp, $cq * max( 1, $order_qty ), 'decrease' );
				$touched = true;
			}
		}
		if ( $touched ) {
			$order->update_meta_data( '_aod_pack_stock_done', 'yes' );
			$order->save();
		}
	}

	/**
	 * Décrémente le stock des variantes choisies quand la commande est CONFIRMÉE (40.5).
	 *
	 * Mirroir de `reduce_pack_components_stock()` : même structure de garde, même drapeau
	 * d'idempotence posé SUR LA COMMANDE, même `save()` par entité touchée. La donnée
	 * mutée est le champ `stock` d'une valeur dans la méta produit `_aod_options` (40.1),
	 * pas le stock Woo natif. Décrément à la confirmation (D3), jamais à la création.
	 *
	 * Le drapeau `_aod_variant_stock_reduced` = 'yes' garantit un décrément unique, même
	 * si le statut `aod-confirmed`/`completed` est atteint deux fois.
	 *
	 * @param int $order_id
	 */
	public function reduce_variant_stock( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' === $order->get_meta( '_aod_variant_stock_reduced' ) ) {
			return;
		}
		if ( $this->apply_variant_stock_delta( $order, -1 ) ) {
			$order->update_meta_data( '_aod_variant_stock_reduced', 'yes' );
			$order->save();
		}
	}

	/**
	 * Ré-incrémente le stock des variantes quand la commande est ANNULÉE / REMBOURSÉE (40.5).
	 *
	 * N'agit QUE si le décrément avait eu lieu (`_aod_variant_stock_reduced === 'yes'`) :
	 * une commande jamais confirmée n'a rien à restituer. Le drapeau repasse ensuite à ''
	 * — la réduction est réputée annulée : un double `cancelled`/`refunded` ne restitue
	 * qu'une fois (garde d'entrée), et une reconfirmation redécrémente proprement.
	 *
	 * @param int $order_id
	 */
	public function restore_variant_stock( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' !== $order->get_meta( '_aod_variant_stock_reduced' ) ) {
			return;
		}
		$this->apply_variant_stock_delta( $order, 1 );
		// Toujours remettre le drapeau à '' : la réduction portée par cette commande est
		// levée. Même si rien n'a pu être restitué (produit supprimé entre-temps), l'état
		// « réduit » ne tient plus — sinon une reconfirmation ne redécrémenterait jamais.
		$order->update_meta_data( '_aod_variant_stock_reduced', '' );
		$order->save();
	}

	/**
	 * Applique une variation de stock (± quantité) aux variantes gérées d'une commande.
	 *
	 * Mutualise le mapping item-meta → valeur `_aod_options` entre le décrément (D3) et
	 * le ré-incrément (D4) : un SEUL endroit lit la ligne, retrouve l'axe par son libellé
	 * (clé item-meta = `label` FR canonique, écrite par `build_order_lines()`), et la
	 * valeur retenue par son `name`. Seule l'opération sur `stock` diffère, portée par le
	 * signe. Le décrément est borné à 0 ; le ré-incrément n'a pas de borne haute.
	 *
	 * Ne touche QUE les valeurs à stock géré (`'' !== stock`). Une valeur non gérée
	 * (illimitée) ou un produit d'avant la 40.1 (clé `stock` absente) est ignoré sans
	 * effet. Lecture/écriture sur la méta produit BRUTE (jamais la somme calculée de la
	 * vue Stock : pas de duplication d'état — leçon Phase 35).
	 *
	 * @param WC_Order $order Commande à parcourir.
	 * @param int      $sign  -1 pour décrémenter (borné à 0), +1 pour ré-incrémenter.
	 * @return bool True si au moins une variante gérée a été mutée (≥ 1 `save()` produit).
	 */
	private function apply_variant_stock_delta( $order, $sign ) {
		$touched = false;
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$opts = $product->get_meta( '_aod_options' );
			if ( ! is_array( $opts ) || ! $opts ) {
				continue;
			}
			$qty        = max( 1, (int) $item->get_quantity() );
			$prod_dirty = false;
			foreach ( $opts as $si => $sec ) {
				if ( ! isset( $sec['label'], $sec['values'] ) || ! is_array( $sec['values'] ) ) {
					continue;
				}
				$chosen = (string) $item->get_meta( (string) $sec['label'] );
				if ( '' === $chosen ) {
					continue;
				}
				foreach ( $sec['values'] as $vi => $val ) {
					if ( ! isset( $val['name'] ) || $val['name'] !== $chosen ) {
						continue;
					}
					// Valeur retrouvée : n'agir que si son stock est GÉRÉ ('' = illimité).
					if ( ! isset( $val['stock'] ) || '' === (string) $val['stock'] ) {
						break;
					}
					$current = (int) $val['stock'];
					$next    = ( $sign < 0 ) ? max( 0, $current - $qty ) : $current + $qty;
					$opts[ $si ]['values'][ $vi ]['stock'] = (string) $next;
					$prod_dirty = true;
					break;
				}
			}
			if ( $prod_dirty ) {
				$product->update_meta_data( '_aod_options', $opts );
				$product->save();
				$touched = true;
			}
		}
		return $touched;
	}
}
