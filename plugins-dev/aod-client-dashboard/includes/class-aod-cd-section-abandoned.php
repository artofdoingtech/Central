<?php
/**
 * Section « Commandes abandonnées » (Phase 11) : les prospects du formulaire COD
 * qui ont saisi leur téléphone sans jamais cliquer « Commander ».
 *
 * La donnée existe déjà ENTIÈREMENT dans la table {prefix}aod_cod_leads du plugin
 * aod-cod-form (capture au blur du téléphone, mark_converted() par token) : un
 * panier abandonné = un lead resté `status='pending'`. Cette section ne fait que
 * PRÉSENTER ces prospects dans /gestion (le gérant n'a pas accès à wp-admin) pour
 * qu'il — ou un confirmateur habilité — les rappelle (Appeler / WhatsApp).
 *
 * Aucune capture, aucune migration : lecture seule d'une table qui porte des PII
 * (téléphone) → gouvernée par la capability aod_cap_abandoned (routeur) + le
 * périmètre produits du confirmateur, jamais de nopriv.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Abandoned {

	const PER_PAGE = 20;

	/** Seuil au-delà duquel un prospect est « abandonné » (sinon « en cours »). */
	const ABANDON_AFTER = 1800; // 30 min. Même seuil que la page wp-admin des leads.

	/** @var AOD_CD_Section_Abandoned|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Conversion d'un panier abandonné en commande (Phase 39.1). La section est
		// instanciée au boot (aod-client-dashboard.php) UNIQUEMENT pour enregistrer
		// ce hook, sur le patron des autres sections ; le RENDU, lui, reste paresseux
		// (AOD_CD_Dashboard::…::instance()->render()). Instancier ne touche pas à
		// AOD_COD_Leads : la boutique ne tombe pas si aod-cod-form est désactivé
		// (le handler le vérifie, précédent 32.1).
		add_action( 'wp_ajax_aod_cd_convert_lead', array( $this, 'ajax_convert_lead' ) );
		// Suppression groupée de prospects (39.6) — GÉRANT uniquement (destructif).
		add_action( 'wp_ajax_aod_cd_delete_leads', array( $this, 'ajax_delete_leads' ) );
		// Assignation d'un prospect à un confirmateur (43.3) — GÉRANT uniquement,
		// même famille « action-forte » que aod_cd_order_assign (Phase 42.4).
		add_action( 'wp_ajax_aod_cd_lead_assign', array( $this, 'ajax_lead_assign' ) );
	}

	/**
	 * Téléphone DZ local → international pour wa.me (0X… → 213X…). Réplique la
	 * logique de AOD_COD_Leads::wa_number() (protected, non appelable d'ici).
	 *
	 * Public : c'est le SEUL exemplaire du dashboard (leçon ARCHITECTURE.md §7.6
	 * — un helper se partage, il ne se recopie pas), consommé par cette section
	 * ET par AOD_CD_Section_Orders (rang Appeler / WhatsApp, Phase 34). La coque
	 * est au plafond R1 de test-decomposition.php : il ne peut pas y monter.
	 *
	 * @param string $phone
	 * @return string
	 */
	public static function wa_number( $phone ) {
		$p = preg_replace( '/\D+/', '', (string) $phone );
		if ( 0 === strpos( $p, '0' ) ) {
			$p = '213' . substr( $p, 1 );
		}
		return $p;
	}

	/**
	 * Construit la clause WHERE commune (status pending + périmètre + recherche
	 * + plage de dates), déjà préparée. Renvoie une chaîne à concaténer après
	 * « WHERE 1=1 ».
	 *
	 * ⚠️ N'affecte PAS `nb_conv` (« récupérés en commande ») dans `render()` —
	 * ce compteur reste volontairement global (signal indépendant de la vue
	 * courante), comportement existant préservé (cf. story 43.1).
	 *
	 * @param int[]  $scope            Périmètre produits (vide = pas de restriction).
	 * @param string $search           Recherche nom/téléphone.
	 * @param string $date_from        `Y-m-d` ou `''` (43.1) — borne sur `updated_at`.
	 * @param string $date_to          `Y-m-d` ou `''` (43.1).
	 * @param int    $confirmer_filter ID d'un confirmateur DÉJÀ validé, `0` = pas
	 *                                 de filtre (43.3).
	 * @return string SQL préparé (commence par « AND … »).
	 */
	protected function where_sql( $scope, $search, $date_from = '', $date_to = '', $confirmer_filter = 0 ) {
		global $wpdb;
		$sql = " AND status = 'pending' ";
		if ( $scope ) {
			$ph   = implode( ',', array_fill( 0, count( $scope ), '%d' ) );
			$sql .= $wpdb->prepare( " AND product_id IN ($ph) ", $scope ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$sql .= $wpdb->prepare( ' AND ( name LIKE %s OR phone LIKE %s ) ', $like, $like );
		}
		// Le lead porte une HEURE (`updated_at`), pas seulement un jour : la
		// borne haute couvre la journée entière (23:59:59), sinon un lead mis
		// à jour à 14h le jour même de `$date_to` serait exclu à tort.
		if ( '' !== $date_from ) {
			$sql .= $wpdb->prepare( ' AND updated_at >= %s ', $date_from . ' 00:00:00' );
		}
		if ( '' !== $date_to ) {
			$sql .= $wpdb->prepare( ' AND updated_at <= %s ', $date_to . ' 23:59:59' );
		}
		// Filtre confirmateur (43.3) — reprise du patron 43.2 sur Commandes/Suivi,
		// possible seulement depuis que la colonne `assigned_confirmer` existe.
		// L'appelant a DÉJÀ validé l'ID (confiance zéro) et ne passe `0` que pour
		// « pas de filtre » — jamais pour « chercher les non assignés » (ce serait
		// un besoin distinct, non demandé).
		if ( $confirmer_filter > 0 ) {
			$sql .= $wpdb->prepare( ' AND assigned_confirmer = %d ', $confirmer_filter );
		}
		return $sql;
	}

	/**
	 * Sélecteur d'assignation d'un PROSPECT à un confirmateur (Phase 43.3).
	 *
	 * Jumeau de `AOD_CD_Section_Orders::assign_select_html()` (42.4) sous des
	 * classes CSS et un point AJAX PROPRES (`aod-cd-leadassignsel*`,
	 * `aod_cd_lead_assign`).
	 *
	 * 🚨 Les noms de classes NE DOIVENT PAS être partagés avec le widget des
	 * commandes : `section-orders.js` câble `.aod-cd-assignsel` par un
	 * `querySelectorAll` GLOBAL, et tous les modules `section-*.js` sont chargés
	 * sur TOUTES les pages de /gestion (`AOD_CD_Dashboard::dashboard_scripts()`).
	 * Un prospect portant les classes « commande » serait donc câblé au handler
	 * `aod_cd_order_assign` avec un `order_id` qui ne désigne aucune commande.
	 * La PEAU, elle, est mutualisée côté CSS (sélecteurs joints, pas recopiés).
	 *
	 * Gérant UNIQUEMENT (appelant : `render_row()`) — un confirmateur ne voit ni
	 * ce sélecteur ni le nom de l'assigné.
	 *
	 * @param int       $lead_id
	 * @param int       $current_id ID du confirmateur assigné, `0` = non assigné.
	 * @param WP_User[] $confirmers Vivier (`eligible_confirmers( array(), true )`).
	 * @return string Markup échappé.
	 */
	/**
	 * Montant estimé d'un panier abandonné : produit × quantité + frais de
	 * livraison de SA wilaya et de SON type de livraison (Phase 43.6).
	 *
	 * 🚨 ZÉRO NOUVELLE FORMULE (invariant de tête de Phase 43). Les deux moitiés
	 * du calcul sont celles de `handle_submit()`, appelées telles quelles :
	 * `AOD_COD_Form::estimate_lines()` pour les articles (offres de lot
	 * comprises) et `AOD_COD_Data::effective_shipping()` pour la livraison
	 * (wilaya offerte et seuil de gratuité compris). Le montant affiché est
	 * donc EXACTEMENT ce que la commande coûterait si le prospect était
	 * converti — ce qui est la seule définition utile de « juste » ici.
	 *
	 * ⚠️ Le plan annonçait un seul appel : `estimate_lines()` ne prend NI wilaya
	 * NI type de livraison (mesuré) — elle ne chiffre que les articles. Il en
	 * faut donc deux, plus la somme, comme `handle_submit()` la fait.
	 *
	 * TROIS REPLIS, tous rendus par un tiret sourd — jamais un 0 DA qui se
	 * ferait passer pour un vrai chiffre :
	 *   · produit supprimé/non achetable (cas déjà connu de la 39.4) ;
	 *   · wilaya absente (prospect capté avant le choix de livraison) ;
	 *   · type de livraison absent ou inconnu. 🚨 Ce dernier n'est PAS au plan,
	 *     et il est indispensable : `AOD_COD_Data::price_for()` normalise en
	 *     silence tout type inconnu en `'home'` — sans cette garde, un prospect
	 *     sans choix de livraison afficherait un tarif Domicile INVENTÉ.
	 *
	 * MONTANT APPROCHÉ (« ≈ ») : un prospect ne mémorise aucun choix de variante
	 * (`collectLead()` n'envoie pas `opt`), donc le supplément de variante est
	 * inconnu. Plutôt qu'un tiret sur tout produit à variantes — c'est-à-dire
	 * une colonne vide sur la majorité d'un catalogue —, on chiffre au prix de
	 * base (`estimate_lines( …, $tolerant = true )`) et on ANNONCE
	 * l'approximation. Arbitrage tranché avec l'owner le 2026-07-28.
	 *
	 * @param object $lead Ligne de la table leads.
	 * @return string HTML échappé.
	 */
	public static function lead_amount_html( $lead ) {
		$tiret = '<span class="aod-cd-muted">—</span>';

		if ( ! class_exists( 'AOD_COD_Form' ) || ! class_exists( 'AOD_COD_Data' ) ) {
			return $tiret; // Socle COD absent (mode dégradé, 32.1) : on n'invente rien.
		}

		$pid = isset( $lead->product_id ) ? (int) $lead->product_id : 0;
		$wil = isset( $lead->wilaya ) ? (int) $lead->wilaya : 0;
		$del = isset( $lead->delivery ) ? (string) $lead->delivery : '';
		if ( ! $pid || ! $wil || ( 'home' !== $del && 'desk' !== $del ) ) {
			return $tiret;
		}

		$qty = isset( $lead->qty ) ? max( 1, (int) $lead->qty ) : 1;
		$est = AOD_COD_Form::instance()->estimate_lines(
			array( array( 'product_id' => $pid, 'qty' => $qty ) ),
			true
		);
		if ( empty( $est['lines'] ) ) {
			return $tiret; // Produit indisponible : le prospect n'est plus chiffrable.
		}

		$sous_total = 0.0;
		foreach ( $est['lines'] as $ligne ) {
			$sous_total += (float) $ligne['line_total'];
		}
		$total = $sous_total + (float) AOD_COD_Data::effective_shipping( $wil, $del, $sous_total );

		$html = wc_price( $total );
		if ( ! empty( $est['approx'] ) ) {
			$html = '<abbr class="aod-cd-amount-approx" title="'
				. esc_attr__( 'Montant approché : le choix de variante n’est pas mémorisé par un panier abandonné, le supplément éventuel n’est donc pas compté.', 'aod-client-dashboard' )
				. '">' . esc_html__( '≈', 'aod-client-dashboard' ) . '</abbr> ' . $html;
		}
		return $html;
	}

	protected function lead_assign_select_html( $lead_id, $current_id, $confirmers ) {
		$current_id = (int) $current_id;

		$options = array( 0 => __( 'Non assignée', 'aod-client-dashboard' ) );
		foreach ( $confirmers as $confirmer ) {
			$options[ (int) $confirmer->ID ] = $confirmer->display_name;
		}
		$current_label = isset( $options[ $current_id ] ) ? $options[ $current_id ] : $options[0];

		$caret = '<svg class="aod-cd-leadassignsel-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';
		$check = '<svg class="aod-cd-leadassignsel-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';

		$html  = '<div class="aod-cd-leadassignsel" data-lead="' . esc_attr( $lead_id ) . '" data-value="' . esc_attr( $current_id ) . '">';
		$html .= '<button type="button" class="aod-cd-leadassignsel-btn" aria-haspopup="listbox" aria-expanded="false">';
		$html .= AOD_CD_Icon::svg( 'user', array( 'size' => 14 ) );
		$html .= '<span class="aod-cd-leadassignsel-text">' . esc_html( $current_label ) . '</span>';
		$html .= $caret . '</button>';
		$html .= '<div class="aod-cd-leadassignsel-menu" role="listbox" hidden>';
		foreach ( $options as $uid => $label ) {
			$sel   = ( $uid === $current_id );
			$html .= '<button type="button" class="aod-cd-leadassignsel-opt' . ( $sel ? ' is-active' : '' ) . '" role="option"'
				. ' data-value="' . esc_attr( $uid ) . '"'
				. ' aria-selected="' . ( $sel ? 'true' : 'false' ) . '">';
			$html .= '<span class="aod-cd-leadassignsel-text">' . esc_html( $label ) . '</span>';
			$html .= $check . '</button>';
		}
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Écrit (ou remet à zéro) la colonne `assigned_confirmer` d'un prospect,
	 * avec validation « confiance zéro » du confirmateur ciblé (Phase 43.3).
	 *
	 * Jumeau de `AOD_CD_Section_Orders::apply_assignment()` — extraite du
	 * handler AJAX pour rester appelable par réflexion dans les tests (le
	 * handler se termine par `wp_send_json_*()` → `wp_die()`).
	 *
	 * ⚠️ Contrairement aux FILTRES (43.1/43.2), où un ID hors vivier est
	 * silencieusement ignoré, ici il est REFUSÉ : c'est une écriture, pas un
	 * affichage — même règle que `apply_assignment()`.
	 *
	 * `$confirmer_id = 0` remet la colonne à `0` (non assigné). La table n'a pas
	 * de meta à supprimer : `0` EST l'absence (jamais `NULL`, cf. schéma).
	 *
	 * @param int $lead_id
	 * @param int $confirmer_id
	 * @return array{ok:bool,message:string,label:string} `message` n'est peuplé
	 *                que si `ok` est faux.
	 */
	protected function apply_lead_assignment( $lead_id, $confirmer_id ) {
		global $wpdb;

		$lead_id      = absint( $lead_id );
		$confirmer_id = absint( $confirmer_id );

		if ( ! class_exists( 'AOD_COD_Leads' ) || ! method_exists( 'AOD_COD_Leads', 'table' ) ) {
			return array( 'ok' => false, 'message' => __( 'Le module de commande COD n’est pas actif.', 'aod-client-dashboard' ), 'label' => '' );
		}
		$table = AOD_COD_Leads::table();

		$exists = $lead_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", $lead_id ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $table = AOD_COD_Leads::table() ($wpdb->prefix, nom contrôlé) ; le prepare() borne l'id. Table custom aod_cod_leads : pas d'API WP, cache non pertinent.
		if ( ! $exists ) {
			return array( 'ok' => false, 'message' => __( 'Prospect introuvable.', 'aod-client-dashboard' ), 'label' => '' );
		}

		$label = __( 'Non assignée', 'aod-client-dashboard' );
		if ( $confirmer_id ) {
			$target = AOD_CD_Roles::valid_confirmer( $confirmer_id );
			if ( ! $target ) {
				return array( 'ok' => false, 'message' => __( 'Confirmateur invalide.', 'aod-client-dashboard' ), 'label' => '' );
			}
			$label = $target->display_name;
		}

		// `assigned_by` (43.4) : l'ID du gérant qui pose l'assignation — ce
		// handler est gérant-only, donc c'en est toujours un. Remis à `0` en même
		// temps que `assigned_confirmer` sur une dé-assignation : ici `0` EST
		// l'absence (colonne `NOT NULL DEFAULT 0`, cf. schéma), là où la commande
		// SUPPRIME sa méta. Deux stockages, deux façons d'exprimer « rien ».
		// Cette colonne n'est jamais affichée : elle sert au report de
		// `build_skeleton_order()` à la conversion.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table custom aod_cod_leads : pas d'API WP, cache non pertinent. $wpdb->update() prépare valeurs et clause WHERE.
			$table,
			array(
				'assigned_confirmer' => $confirmer_id,
				'assigned_by'        => $confirmer_id ? get_current_user_id() : 0,
			),
			array( 'id' => $lead_id )
		);

		return array( 'ok' => true, 'message' => '', 'label' => $label );
	}

	/**
	 * Assigne un prospect à un confirmateur (Phase 43.3).
	 *
	 * GÉRANT UNIQUEMENT (`manage_woocommerce`) — même famille « action-forte »
	 * que `AOD_CD_Section_Orders::ajax_order_assign()` (42.4) : un confirmateur
	 * ne doit pas pouvoir se réassigner (ou réassigner à un pair) un prospect.
	 * Réassignable à tout moment, sans restriction : c'est un routage de
	 * visibilité, jamais un verrou (même règle que les commandes).
	 */
	public function ajax_lead_assign() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$lead_id      = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		$confirmer_id = isset( $_POST['confirmer_id'] ) ? absint( $_POST['confirmer_id'] ) : 0;

		$result = $this->apply_lead_assignment( $lead_id, $confirmer_id );
		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ), 400 );
		}

		wp_send_json_success( array(
			'message' => __( 'Assignation mise à jour.', 'aod-client-dashboard' ),
			'label'   => $result['label'],
		) );
	}

	public function render() {
		global $wpdb;

		if ( ! class_exists( 'AOD_COD_Leads' ) || ! method_exists( 'AOD_COD_Leads', 'table' ) ) {
			echo AOD_CD_Dashboard::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
				'abandoned',
				__( 'Le module de commande COD n’est pas actif.', 'aod-client-dashboard' ),
				__( 'Activez le plugin « AOD COD Form » pour suivre les paniers abandonnés.', 'aod-client-dashboard' )
			);
			return;
		}
		$table = AOD_COD_Leads::table();

		$base_url = home_url( '/' . AOD_CD_SLUG . '/abandoned' );
		$current  = isset( $_GET['filtre'] ) ? sanitize_key( wp_unslash( $_GET['filtre'] ) ) : 'all';
		if ( ! in_array( $current, array( 'all', 'abandoned', 'recent' ), true ) ) {
			$current = 'all';
		}
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		// Filtre par date (43.1) — sur updated_at (colonne « Quand »), pas
		// gérant-only (lecture pure, même règle que Commandes/Suivi).
		$date_from = AOD_CD_Dashboard::parse_date_param( 'date_from' );
		$date_to   = AOD_CD_Dashboard::parse_date_param( 'date_to' );
		$range     = AOD_CD_Dashboard::resolve_date_range( $date_from, $date_to );

		// Assignation (43.3) — GÉRANT UNIQUEMENT, comme sur Commandes : le
		// sélecteur, la colonne « Assigné à » ET le filtre par confirmateur
		// exposent tous le NOM d'un confirmateur, information qu'un confirmateur
		// ne doit jamais voir (y compris la sienne). Confiance zéro sur l'ID
		// posté : hors vivier → traité comme absent (c'est un filtre GET, pas une
		// action ; l'écriture, elle, REFUSE — cf. apply_lead_assignment()).
		$can_assign       = current_user_can( 'manage_woocommerce' );
		$confirmers       = $can_assign ? AOD_CD_Roles::eligible_confirmers( array(), true ) : array();
		$confirmer_filter = 0;
		if ( $can_assign && isset( $_GET['confirmateur'] ) ) {
			$posted_confirmer = absint( $_GET['confirmateur'] );
			if ( in_array( $posted_confirmer, wp_list_pluck( $confirmers, 'ID' ), true ) ) {
				$confirmer_filter = $posted_confirmer;
			}
		}

		// Périmètre produits du confirmateur (vide = pas de restriction ; le gérant
		// n'est jamais filtré). Filtrage CÔTÉ SQL — jamais un simple masquage.
		$scope = AOD_CD_Roles::assigned_products();
		$where = $this->where_sql( $scope, $search, $range['from'], $range['to'], $confirmer_filter );

		// Coupure « abandonné » : tout en heure du site (updated_at posé par
		// upsert() via current_time('mysql')). On soustrait dans SQL pour rester
		// cohérent avec le comptage.
		$now_mysql = current_time( 'mysql' );
		$cut_expr  = $wpdb->prepare( 'DATE_SUB(%s, INTERVAL %d SECOND)', $now_mysql, self::ABANDON_AFTER );

		// Compteurs des 3 onglets (bornés au même périmètre/recherche).
		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE 1=1 $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = $wpdb->prefix (nom contrôlé, non paramétrable) ; $where/$tab_sql/$cut_expr déjà préparés. Table custom : pas d’API WP, cache non pertinent.
		$nb_aband  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE 1=1 $where AND updated_at <= $cut_expr" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = $wpdb->prefix (nom contrôlé, non paramétrable) ; $where/$tab_sql/$cut_expr déjà préparés. Table custom : pas d’API WP, cache non pertinent.
		$nb_recent = $total - $nb_aband;
		$nb_conv   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'converted'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = $wpdb->prefix (nom contrôlé, non paramétrable) ; $where/$tab_sql/$cut_expr déjà préparés. Table custom : pas d’API WP, cache non pertinent.

		$tabs = array(
			'all'       => array( __( 'Tous', 'aod-client-dashboard' ), $total ),
			'abandoned' => array( __( 'Abandonnés', 'aod-client-dashboard' ), $nb_aband ),
			'recent'    => array( __( 'En cours', 'aod-client-dashboard' ), $nb_recent ),
		);

		// Clause de l'onglet courant.
		$tab_sql = '';
		if ( 'abandoned' === $current ) {
			$tab_sql = " AND updated_at <= $cut_expr ";
		} elseif ( 'recent' === $current ) {
			$tab_sql = " AND updated_at > $cut_expr ";
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE 1=1 $where $tab_sql" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $table = $wpdb->prefix (nom contrôlé, non paramétrable) ; $where/$tab_sql/$cut_expr déjà préparés. Table custom : pas d’API WP, cache non pertinent.
		$pages = max( 1, (int) ceil( $count / self::PER_PAGE ) );
		$paged = min( $paged, $pages );
		$offset = ( $paged - 1 ) * self::PER_PAGE;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table custom aod_cod_leads (pas d’API WP, cache non pertinent). Le prepare() et son SQL sont sur la ligne suivante.
			"SELECT * FROM $table WHERE 1=1 $where $tab_sql ORDER BY updated_at DESC LIMIT %d OFFSET %d",
			self::PER_PAGE,
			$offset
		) );

		// En-tête : sous-titre qui dit combien ont fini par commander (signal de
		// récupération), sans lister les convertis (ils ne sont plus « abandonnés »).
		$sub = ( $nb_conv > 0 )
			? sprintf(
				/* translators: %d = nombre de prospects devenus commandes. */
				_n( 'Un visiteur a rempli le formulaire sans valider. %d a déjà été récupéré en commande.', 'Un visiteur a rempli le formulaire sans valider. %d ont déjà été récupérés en commande.', $nb_conv, 'aod-client-dashboard' ),
				$nb_conv
			)
			: __( 'Un visiteur a rempli le formulaire de commande sans cliquer « Commander ». Rappelez-le tant qu’il est chaud.', 'aod-client-dashboard' );

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Commandes abandonnées', 'aod-client-dashboard' ),
			$sub
		);

		// Barre d'onglets (segmented control) + recherche, comme la page Commandes.
		echo '<div class="aod-cd-toolbar">';
		echo '<div class="aod-cd-seg" role="group" aria-label="' . esc_attr__( 'Filtrer', 'aod-client-dashboard' ) . '">';
		foreach ( $tabs as $slug => $tab ) {
			$keep = array( 'filtre' => $slug );
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
				esc_html( $tab[0] ),
				(int) $tab[1]
			);
		}
		echo '</div>';
		AOD_CD_Dashboard::render_search_bar( $base_url, $search, __( 'Rechercher : nom, téléphone…', 'aod-client-dashboard' ), array( 'filtre' => $current, 'date_from' => $range['from'], 'date_to' => $range['to'], 'confirmateur' => $confirmer_filter ? $confirmer_filter : '' ) );
		echo '</div>';

		// Filtre par date (43.1) + confirmateur (43.3) — formulaire GET distinct,
		// même patron que Commandes : champs cachés pour préserver l'onglet et la
		// recherche.
		echo '<form class="aod-cd-form aod-cd-filters" method="get" action="' . esc_url( $base_url ) . '">';
		echo '<input type="hidden" name="filtre" value="' . esc_attr( $current ) . '">';
		if ( '' !== $search ) {
			echo '<input type="hidden" name="s" value="' . esc_attr( $search ) . '">';
		}
		AOD_CD_Dashboard::render_date_fields( $range['from'], $range['to'] );
		if ( $can_assign ) {
			echo '<div class="aod-cd-field"><label for="aod-cd-f-leadconfirmer">' . esc_html__( 'Confirmateur', 'aod-client-dashboard' ) . '</label>';
			echo '<select id="aod-cd-f-leadconfirmer" name="confirmateur">';
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

		if ( empty( $rows ) ) {
			echo AOD_CD_Dashboard::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
				'check',
				'' !== $search ? __( 'Aucun résultat pour cette recherche.', 'aod-client-dashboard' ) : __( 'Aucun panier abandonné pour le moment.', 'aod-client-dashboard' )
			);
			return;
		}

		$now_ts = strtotime( $now_mysql ); // même base naïve que updated_at.

		// Suppression groupée (39.6) : GÉRANT uniquement — supprimer des prospects est
		// destructif, réservé au gérant comme delete_orders (31.1). Un confirmateur
		// aod_cap_abandoned consulte/rappelle mais ne purge pas. Classes CSS
		// comportementales PROPRES (leadcheck…) pour ne pas entrer en conflit avec le
		// binder de la page Commandes (qui tourne aussi mais ne trouve pas ses cases).
		//
		// DÉRIVÉE de $can_assign (43.3), pas recalculée : c'est rigoureusement la
		// même porte (`manage_woocommerce`), gardée sous un nom distinct parce que
		// c'est un AUTRE pouvoir (purger ≠ assigner). Une seule lecture de
		// capability par render().
		$can_delete = $can_assign;

		if ( $can_delete ) {
			// 45.2 — même barre, même règle que Commandes : rendue en permanence,
			// action désactivée tant que rien n'est coché (cf. le commentaire de
			// `.aod-cd-bulkbar` dans dashboard.css). Les deux sections partagent
			// désormais le composant ET sa peau — elles ne diffèrent que par leur
			// contenu.
			echo '<div class="aod-cd-bulkbar is-vide" id="aod-cd-lead-bulk">';
			echo '<span class="aod-cd-bulkbar-count"><strong class="aod-cd-bulkbar-n">0</strong> ' . esc_html__( 'sélectionné(s)', 'aod-client-dashboard' ) . '</span>';
			echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-lead-bulk-del aod-cd-bulk-act" disabled>'
				. '<span class="aod-cd-bulk-ico" aria-hidden="true">' . AOD_CD_Icon::svg( 'trash', array( 'size' => 16 ) ) . '</span> '
				. esc_html__( 'Supprimer', 'aod-client-dashboard' )
				. ' <span class="aod-cd-bulkbar-btnn">(0)</span></button>';
			echo '</div>';
		}

		echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards"><thead><tr>';
		if ( $can_delete ) {
			echo '<th class="aod-cd-col-check"><input type="checkbox" class="aod-cd-leadcheck-all" title="' . esc_attr__( 'Tout sélectionner', 'aod-client-dashboard' ) . '"></th>';
		}
		$heads = array(
			__( 'Quand', 'aod-client-dashboard' ),
			__( 'Client', 'aod-client-dashboard' ),
			__( 'Produit', 'aod-client-dashboard' ),
			__( 'Wilaya', 'aod-client-dashboard' ),
			__( 'Livraison', 'aod-client-dashboard' ),
			__( 'Qté', 'aod-client-dashboard' ),
			// Montant (43.6) — visible de TOUS ceux qui voient la page : c'est une
			// information de vente, pas un pouvoir d'assignation. Un confirmateur
			// qui rappelle un prospect a besoin de savoir de quelle somme il parle.
			__( 'Montant', 'aod-client-dashboard' ),
		);
		if ( $can_assign ) {
			// Colonne « Assigné à » (43.3) — GÉRANT uniquement, même règle et même
			// libellé que la colonne jumelle de Commandes (42.7).
			$heads[] = __( 'Assigné à', 'aod-client-dashboard' );
		}
		foreach ( $heads as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $lead ) {
			$this->render_row( $lead, $now_ts, $can_delete, $can_assign, $confirmers );
		}
		echo '</tbody></table></div>';

		AOD_CD_Dashboard::render_pagination( $base_url, $paged, $pages, array( 'filtre' => $current, 's' => $search, 'date_from' => $range['from'], 'date_to' => $range['to'], 'confirmateur' => $confirmer_filter ? $confirmer_filter : '' ) );
	}

	/**
	 * Une ligne du tableau (markup carte : chaque cellule porte data-label pour
	 * le rendu mobile empilé, comme la page Commandes).
	 *
	 * @param object    $lead       Ligne de la table leads.
	 * @param int       $now_ts     « Maintenant » (base naïve, cohérente avec updated_at).
	 * @param bool      $can_delete Rendre la case de sélection groupée (gérant, 39.6).
	 * @param bool      $can_assign Rendre la colonne « Assigné à » + le sélecteur
	 *                              d'assignation (gérant, 43.3).
	 * @param WP_User[] $confirmers Vivier pour ce sélecteur (vide si non gérant).
	 */
	protected function render_row( $lead, $now_ts, $can_delete = false, $can_assign = false, $confirmers = array() ) {
		$ts        = strtotime( $lead->updated_at );
		$abandoned = ( $now_ts - $ts ) >= self::ABANDON_AFTER;
		$phone     = (string) $lead->phone;
		$wa        = self::wa_number( $phone );

		$product = $lead->product_id ? wc_get_product( (int) $lead->product_id ) : null;
		$pname   = $product ? $product->get_name() : '—';

		$wilaya_name = ( $lead->wilaya && class_exists( 'AOD_COD_Data' ) ) ? AOD_COD_Data::wilaya_name( (int) $lead->wilaya ) : '';

		echo '<tr>';

		if ( $can_delete ) {
			echo '<td class="aod-cd-col-check"><input type="checkbox" class="aod-cd-leadcheck" value="' . esc_attr( (int) $lead->id ) . '" title="' . esc_attr__( 'Sélectionner ce prospect', 'aod-client-dashboard' ) . '"></td>';
		}

		// Quand + badge d'état.
		echo '<td data-label="' . esc_attr__( 'Quand', 'aod-client-dashboard' ) . '">';
		echo '<span class="aod-cd-client"><b>' . esc_html( human_time_diff( $ts, $now_ts ) ) . '</b>';
		echo $abandoned // phpcs:ignore WordPress.Security.EscapeOutput
			? AOD_CD_Dashboard::pill( __( 'Abandonné', 'aod-client-dashboard' ), 'bad' )
			: AOD_CD_Dashboard::pill( __( 'En cours', 'aod-client-dashboard' ), 'warn' );
		echo '</span></td>';

		// Client : nom + téléphone + actions Appeler / WhatsApp.
		echo '<td class="aod-cd-cell-client" data-label="' . esc_attr__( 'Client', 'aod-client-dashboard' ) . '">';
		echo '<span class="aod-cd-client"><b>' . esc_html( '' !== trim( (string) $lead->name ) ? $lead->name : '—' ) . '</b>';
		if ( '' !== $phone ) {
			echo '<a class="aod-cd-client__tel" href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a>';
			echo '<span class="aod-cd-call-actions">';
			echo '<a class="aod-cd-btn aod-cd-btn-sm" href="tel:' . esc_attr( $phone ) . '">' . AOD_CD_Icon::svg( 'phone', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Appeler', 'aod-client-dashboard' ) . '</a> ';
			echo '<a class="aod-cd-btn aod-cd-btn-sm aod-cd-btn--ghost" href="' . esc_url( 'https://wa.me/' . $wa ) . '" target="_blank" rel="noopener">' . AOD_CD_Icon::svg( 'whatsapp', array( 'size' => 15 ) ) . ' ' . esc_html__( 'WhatsApp', 'aod-client-dashboard' ) . '</a>';
			// « Commander » : convertit ce prospect en vraie commande (Phase 39.1).
			// MASQUÉ (pas seulement désactivé) dès qu'il y a un téléphone ET que
			// l'utilisateur peut agir sur les commandes. Le produit n'est PAS requis
			// (39.4) : on appelle le client, il fournit tout — le confirmateur compose
			// les articles dans la modale. La garde serveur de ajax_convert_lead()
			// répète l'exigence (jamais un simple masquage).
			if ( AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
				echo ' <button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-convert" data-lead-id="' . esc_attr( (int) $lead->id ) . '">' . AOD_CD_Icon::svg( 'cart', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Commander', 'aod-client-dashboard' ) . '</button>';
			}
			echo '</span>';
		}
		echo '</span></td>';

		echo '<td data-label="' . esc_attr__( 'Produit', 'aod-client-dashboard' ) . '">' . esc_html( $pname ) . '</td>';

		echo '<td data-label="' . esc_attr__( 'Wilaya', 'aod-client-dashboard' ) . '">';
		echo esc_html( $wilaya_name ? sprintf( '%02d - %s', (int) $lead->wilaya, $wilaya_name ) : '—' );
		if ( '' !== trim( (string) $lead->commune ) ) {
			echo '<br><span class="aod-cd-muted">' . esc_html( $lead->commune ) . '</span>';
		}
		echo '</td>';

		echo '<td data-label="' . esc_attr__( 'Livraison', 'aod-client-dashboard' ) . '">'
			. ( 'desk' === $lead->delivery ? esc_html__( 'Stop-desk', 'aod-client-dashboard' ) : esc_html__( 'Domicile', 'aod-client-dashboard' ) )
			. '</td>';

		echo '<td data-label="' . esc_attr__( 'Qté', 'aod-client-dashboard' ) . '">' . esc_html( (int) $lead->qty ) . '</td>';
		echo '<td class="aod-cd-cell-amount" data-label="' . esc_attr__( 'Montant', 'aod-client-dashboard' ) . '">' . self::lead_amount_html( $lead ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- markup échappé dans lead_amount_html() (wc_price() + esc_attr sur l'infobulle).

		// « Assigné à » + sélecteur d'assignation (43.3) — GÉRANT uniquement.
		// Le nom (rendu serveur) ET le sélecteur cohabitent, comme sur Commandes :
		// le nom se lit d'un coup d'œil en balayant la colonne, le sélecteur agit.
		// Le JS tient les deux à jour ensemble après une assignation (classe
		// `aod-cd-cell-leadassigned` — c'est son point d'accroche).
		if ( $can_assign ) {
			$assigned = isset( $lead->assigned_confirmer ) ? (int) $lead->assigned_confirmer : 0;
			// Deux classes, deux rôles : `aod-cd-cell-assigned` est le marqueur
			// sémantique partagé avec Commandes/Suivi ; `aod-cd-cell-leadassign`
			// est PROPRE à cette page — seule sa cellule porte À LA FOIS un nom
			// et un sélecteur, donc seule elle a besoin d'empiler en mobile.
			// Styler le marqueur partagé aurait déplacé la colonne des deux
			// autres pages au passage.
			echo '<td class="aod-cd-cell-assigned aod-cd-cell-leadassign" data-label="' . esc_attr__( 'Assigné à', 'aod-client-dashboard' ) . '">';
			echo '<span class="aod-cd-cell-leadassigned">' . AOD_CD_Roles::confirmer_label_html( $assigned ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- markup échappé dans confirmer_label_html().
			echo $this->lead_assign_select_html( (int) $lead->id, $assigned, $confirmers ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup échappé dans lead_assign_select_html().
			echo '</td>';
		}

		echo '</tr>';
	}

	/* ---------------------------------------------------------------------- */
	/* Conversion d'un panier abandonné en commande (Phase 39.1)              */
	/* ---------------------------------------------------------------------- */

	/**
	 * Transforme un prospect `pending` en vraie commande WooCommerce, à la demande
	 * d'un confirmateur qui vient de le rappeler.
	 *
	 * 🚦 On ne CRÉE PAS un 3ᵉ constructeur de commande : on pose un SQUELETTE minimal
	 * (produit + coordonnées déjà saisies), statut neutre `pending` — JAMAIS
	 * `aod-confirmed`, donc AUCUN envoi transporteur ici. La complétion (adresse,
	 * variante, total) et le choix du statut sont l'étape 39.2, via le chemin
	 * d'édition existant (aod_cd_order_save_info / aod_cd_order_status).
	 *
	 * Garde : aod_cap_confirm_orders (agir sur les commandes) EN PLUS de la
	 * capability de section aod_cap_abandoned qui gouverne le routeur — divergence
	 * VOULUE, déclarée dans tests/unit/test-permissions-sections.php.
	 */
	public function ajax_convert_lead() {
		check_ajax_referer( 'aod_cd', 'nonce' );

		if ( ! AOD_CD_Roles::user_can( 'aod_cap_confirm_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_COD_Leads' ) || ! method_exists( 'AOD_COD_Leads', 'table' ) || ! function_exists( 'wc_create_order' ) ) {
			wp_send_json_error( array( 'message' => __( 'Le module de commande COD n’est pas actif.', 'aod-client-dashboard' ) ), 400 );
		}

		global $wpdb;
		$table   = AOD_COD_Leads::table();
		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		if ( ! $lead_id ) {
			wp_send_json_error( array( 'message' => __( 'Prospect introuvable.', 'aod-client-dashboard' ) ), 404 );
		}

		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $lead_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $table = AOD_COD_Leads::table() ($wpdb->prefix, nom contrôlé) ; le prepare() borne l'id. Table custom aod_cod_leads : pas d'API WP, cache non pertinent.
		if ( ! $lead ) {
			wp_send_json_error( array( 'message' => __( 'Prospect introuvable.', 'aod-client-dashboard' ) ), 404 );
		}

		// 39.4 : le TÉLÉPHONE est le seul prérequis d'une conversion — sans lui, pas de
		// commande COD possible (aucun moyen de rappeler). Le produit, lui, est
		// facultatif (le client fournit tout au téléphone). Défensif : le bouton n'est
		// rendu que si le lead a un téléphone ; cette garde couvre l'appel forgé.
		if ( '' === trim( (string) $lead->phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Ce prospect n’a pas de numéro de téléphone : impossible de créer une commande.', 'aod-client-dashboard' ) ), 422 );
		}

		// Périmètre produits du confirmateur : filtrage CÔTÉ SERVEUR (jamais un simple
		// masquage d'UI). Vide = gérant, aucune restriction.
		$scope = AOD_CD_Roles::assigned_products();
		if ( $scope && ! in_array( (int) $lead->product_id, $scope, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		// Déjà converti ? Court-circuit lisible avant même le verrou.
		if ( 'pending' !== (string) $lead->status ) {
			wp_send_json_error( array(
				'message'  => __( 'Ce prospect a déjà été converti en commande.', 'aod-client-dashboard' ),
				'order_id' => (int) $lead->order_id,
			), 409 );
		}

		// Anti-doublon (leçon 31.4). add_option() est ATOMIQUE — il rend false si le
		// verrou existe déjà — là où set_transient() écraserait sans condition (un
		// verrou qui ne verrouille pas). Même primitive que AOD_Shipping::acquire_send_lock().
		$lock = 'aod_cd_convert_lock_' . (int) $lead->id;
		if ( ! add_option( $lock, (string) time(), '', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Conversion déjà en cours pour ce prospect.', 'aod-client-dashboard' ) ), 409 );
		}

		// À partir d'ici, TOUTE sortie doit delete_option($lock) AVANT wp_send_json_* :
		// ce dernier appelle wp_die() → exit, qui NE déclenche PAS un bloc finally.
		// Un verrou sérialise, il n'annule pas → relecture sur instance FRAÎCHE.
		$fresh = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $lead_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $table = AOD_COD_Leads::table() ($wpdb->prefix, nom contrôlé) ; le prepare() borne l'id. Relecture d'anti-doublon (31.4) sur la table custom : pas d'API WP, cache non pertinent.
		if ( 'pending' !== (string) $fresh ) {
			delete_option( $lock );
			wp_send_json_error( array( 'message' => __( 'Ce prospect a déjà été converti en commande.', 'aod-client-dashboard' ) ), 409 );
		}

		// 39.4 : produit FACULTATIF. Présent → il fait la 1ʳᵉ ligne d'article ; absent
		// ou supprimé (`wc_get_product` rend false) → squelette SANS ligne, le
		// confirmateur compose au téléphone dans la modale (39.2). On ne refuse plus
		// « produit n'existe plus » : ce cas est désormais légitime.
		$product = (int) $lead->product_id ? wc_get_product( (int) $lead->product_id ) : null;
		if ( ! $product instanceof WC_Product ) {
			$product = null;
		}

		try {
			$order = $this->build_skeleton_order( $lead, $product );
			$this->maybe_assign_converter( $order );
			AOD_COD_Leads::mark_converted( $lead->token, $order->get_id() );
		} catch ( Exception $e ) {
			delete_option( $lock );
			wp_send_json_error( array( 'message' => __( 'La commande n’a pas pu être créée.', 'aod-client-dashboard' ) ), 500 );
		}

		delete_option( $lock );
		wp_send_json_success( array(
			'order_id' => $order->get_id(),
			// order_id est renvoyé pour l'étape 39.2 (ouverture de la modale d'édition).
			'message'  => __( 'Commande créée. Complétez-la, puis validez le statut.', 'aod-client-dashboard' ),
		) );
	}

	/**
	 * Assignation DIRECTE de la commande convertie au confirmateur qui la
	 * convertit — jamais au tourniquet (Phase 42.1, `AOD_CD_Section_Orders::
	 * assign_least_loaded()`) : il a déjà pris possession du prospect en le
	 * rappelant, un tourniquet la lui reprendrait arbitrairement (ROADMAP §A.19).
	 *
	 * Le gérant (ou tout compte hors du rôle confirmateur) n'est PAS un
	 * confirmateur : aucune méta n'est posée — la commande reste non assignée,
	 * assignable manuellement ensuite (42.4). Testé par `user_can()` serait
	 * insuffisant : le gérant satisfait TOUJOURS `aod_cap_confirm_orders`
	 * (`AOD_CD_Roles::user_can()`), donc seule l'appartenance au RÔLE distingue
	 * un vrai confirmateur.
	 *
	 * Méthode dédiée (et non inline dans `ajax_convert_lead()`) pour rester
	 * appelable par réflexion dans les tests, comme `build_skeleton_order()` —
	 * `ajax_convert_lead()` se termine par `wp_send_json_*()` (`wp_die()`), donc
	 * jamais invoqué en direct dans le cliquet.
	 *
	 * 🚨 CHANGEMENT DE CONTRAT EN 43.3 : cette méthode COMBLE une absence, elle
	 * n'écrase plus. Depuis que le gérant peut pré-assigner un PROSPECT
	 * (colonne `assigned_confirmer`, reportée sur la commande par
	 * `build_skeleton_order()`), écraser inconditionnellement effacerait ce
	 * choix délibéré au profit du premier confirmateur qui décroche le
	 * téléphone — qui n'est pas forcément celui qu'on avait désigné. Le seul
	 * appelant est `ajax_convert_lead()`, qui ne comptait pas sur l'écrasement.
	 *
	 * @param WC_Order $order Commande déjà persistante (issue de `build_skeleton_order()`).
	 */
	protected function maybe_assign_converter( $order ) {
		$converter = wp_get_current_user();
		if ( ! in_array( AOD_CD_ROLE_CONFIRMER, (array) $converter->roles, true ) ) {
			return;
		}
		if ( (int) $order->get_meta( '_aod_assigned_confirmer' ) ) {
			return; // Pré-assignation du gérant (43.3) : elle prime, on n'y touche pas.
		}
		$order->update_meta_data( '_aod_assigned_confirmer', (int) $converter->ID );
		// Signature (43.4) posée SUR CETTE BRANCHE UNIQUEMENT : celle qui écrit
		// réellement. La sortie anticipée ci-dessus laisse intactes l'assignation
		// ET sa signature — sinon la signature du gérant (reportée depuis le
		// prospect) serait remplacée par l'ID du confirmateur qui passe, ce qui
		// INVERSERAIT l'information au lieu de la compléter.
		$order->update_meta_data( '_aod_assigned_by', (int) $converter->ID );
		$order->save();
	}

	/**
	 * Supprime définitivement une sélection de prospects (suppression groupée, 39.6).
	 *
	 * GÉRANT uniquement (`manage_woocommerce`) : supprimer est destructif et borné,
	 * comme `ajax_delete_orders` de la page Commandes (leçon 31.1). Un confirmateur
	 * `aod_cap_abandoned` consulte/rappelle/convertit mais ne purge pas les prospects.
	 * Gérant only → aucun filtre `assigned_products()` (le gérant voit tout ; un
	 * confirmateur n'atteint jamais ce handler).
	 *
	 * Ne touche PAS aux commandes : on supprime des LIGNES de la table leads (par id).
	 * Un lead converti porte un order_id, mais supprimer le lead ne supprime pas la
	 * commande — et un lead converti n'est de toute façon plus listé (status pending).
	 */
	public function ajax_delete_leads() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_COD_Leads' ) || ! method_exists( 'AOD_COD_Leads', 'table' ) ) {
			wp_send_json_error( array( 'message' => __( 'Le module de commande COD n’est pas actif.', 'aod-client-dashboard' ) ), 400 );
		}

		$raw = isset( $_POST['lead_ids'] ) ? (array) wp_unslash( $_POST['lead_ids'] ) : array();
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Aucun prospect sélectionné.', 'aod-client-dashboard' ) ), 400 );
		}

		global $wpdb;
		$table        = AOD_COD_Leads::table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// DELETE préparé : $table = $wpdb->prefix (nom contrôlé), les IDs sont bornés
		// par les placeholders %d. Même patron préparé que where_sql().
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id IN ($placeholders)", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $table = AOD_COD_Leads::table() ($wpdb->prefix, nom contrôlé) ; le prepare() borne les IDs (placeholders %d). Table custom aod_cod_leads : pas d'API WP, cache non pertinent.

		if ( $deleted < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Aucun prospect n’a pu être supprimé.', 'aod-client-dashboard' ) ), 400 );
		}

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: nombre de prospects supprimés. */
				_n( '%d prospect supprimé.', '%d prospects supprimés.', $deleted, 'aod-client-dashboard' ),
				$deleted
			),
			'deleted' => $ids,
		) );
	}

	/**
	 * Squelette de commande à partir d'un prospect : produit racine + coordonnées
	 * DÉJÀ saisies, verbatim. Aucune dérivation (pas de frais de livraison, de
	 * gratuité au seuil, de ligne shipping, de pixels : c'est le rôle du formulaire
	 * public / de la modale d'édition 39.2). Statut `pending` — neutre, ne déclenche
	 * aucun hook d'envoi (le seul est woocommerce_order_status_aod-confirmed).
	 *
	 * @param object          $lead    Ligne de la table leads.
	 * @param WC_Product|null $product Produit racine, ou null si supprimé/absent (39.4) :
	 *                                 la commande part alors SANS ligne d'article.
	 * @return WC_Order
	 */
	protected function build_skeleton_order( $lead, $product ) {
		$order = wc_create_order();
		if ( $product instanceof WC_Product ) {
			$order->add_product( $product, max( 1, (int) $lead->qty ) );
		}

		$address = array(
			'first_name' => (string) $lead->name,
			'phone'      => (string) $lead->phone,
			'country'    => 'DZ',
		);
		if ( (int) $lead->wilaya > 0 ) {
			$address['state'] = 'DZ-' . str_pad( (string) (int) $lead->wilaya, 2, '0', STR_PAD_LEFT );
		}
		if ( '' !== trim( (string) $lead->commune ) ) {
			$address['city'] = (string) $lead->commune;
		}
		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );

		$order->set_payment_method( 'cod' );
		$order->set_payment_method_title( __( 'Paiement à la livraison', 'aod-client-dashboard' ) );

		// Origine : abandon converti depuis /gestion — DISTINCT de 'aod-cod-form'
		// (témoin d'origine, base d'une future stat « commandes récupérées »).
		$order->update_meta_data( '_aod_source', 'aod-abandoned' );
		if ( (int) $lead->wilaya > 0 ) {
			$order->update_meta_data( '_aod_wilaya_code', (int) $lead->wilaya );
			if ( class_exists( 'AOD_COD_Data' ) ) {
				$order->update_meta_data( '_aod_wilaya_name', AOD_COD_Data::wilaya_name( (int) $lead->wilaya ) );
			}
		}
		if ( '' !== trim( (string) $lead->commune ) ) {
			$order->update_meta_data( '_aod_commune', (string) $lead->commune );
		}
		// Type de livraison : rend la commande reconnue « COD » par le retour d'envoi
		// de l'étape 39.2 (class-aod-cd-section-orders.php, garde sur _aod_delivery_type).
		if ( '' !== (string) $lead->delivery ) {
			$order->update_meta_data( '_aod_delivery_type', (string) $lead->delivery );
		}
		// Assignation pré-posée par le gérant sur le PROSPECT (43.3) : elle suit
		// la commande. Deux stockages différents pour le même concept — une
		// COLONNE côté leads (table custom sans meta), une META côté commande —
		// et c'est ici, à la conversion, qu'est l'unique pont entre les deux.
		// `maybe_assign_converter()` respectera ensuite cette valeur au lieu de
		// l'écraser.
		if ( isset( $lead->assigned_confirmer ) && (int) $lead->assigned_confirmer ) {
			$order->update_meta_data( '_aod_assigned_confirmer', (int) $lead->assigned_confirmer );
			// Origine de cette assignation (43.4) : le gérant qui l'a posée sur le
			// PROSPECT. Sans ce report, la commande n'aurait aucune signature et
			// se lirait « assignée automatiquement » — faux, et silencieusement.
			//
			// 🚨 Garde IMBRIQUÉE, jamais la même que celle d'`assigned_confirmer`.
			// Un prospect assigné sous la 43.3 (avant que la colonne n'existe)
			// porte `assigned_confirmer > 0` ET `assigned_by = 0` : sous une garde
			// commune, on poserait `_aod_assigned_by = 0`, or l'invariant est
			// « absence, jamais zéro » (comme `_aod_assigned_confirmer`, 42.1).
			// Ces prospects-là ne sont pas backfillés : leur commande se lira
			// « automatique ». Dégradation bornée dans le temps, assumée — on ne
			// devine pas un auteur.
			//
			// Le `isset()` n'est pas décoratif : `$lead` vient d'un `SELECT *` sur
			// un clone où `maybe_migrate()` peut n'avoir pas encore rejoué.
			if ( isset( $lead->assigned_by ) && (int) $lead->assigned_by ) {
				$order->update_meta_data( '_aod_assigned_by', (int) $lead->assigned_by );
			}
		}

		$order->calculate_totals();
		$order->set_status( 'pending' ); // JAMAIS aod-confirmed : aucun envoi transporteur ici.
		$order->add_order_note(
			$product instanceof WC_Product
				? __( 'Commande créée depuis un panier abandonné — à compléter, puis à confirmer.', 'aod-client-dashboard' )
				: __( 'Commande créée depuis un panier abandonné (sans produit) — articles à composer au téléphone, puis à confirmer.', 'aod-client-dashboard' ),
			0,
			false
		);
		$order->save();

		return $order;
	}
}
