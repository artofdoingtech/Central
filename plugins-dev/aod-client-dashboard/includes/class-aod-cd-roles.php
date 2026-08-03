<?php
/**
 * Rôle « Gestion Boutique » (client) : capacités restreintes.
 *
 * La sécurité vient des capabilities, pas du masquage de l'UI : ce rôle ne
 * possède AUCUNE capacité pouvant casser le site (plugins, thèmes, réglages,
 * utilisateurs, mise à jour du cœur). Il peut uniquement gérer commandes,
 * produits et catégories, plus la capacité custom d'ouvrir le dashboard.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Roles {

	/** @var AOD_CD_Roles|null */
	protected static $instance = null;

	/**
	 * Capacités EXPLICITEMENT interdites au gérant client, même si shop_manager
	 * les possède. C'est la liste qui garantit qu'il ne peut pas casser le site.
	 *
	 * @var string[]
	 */
	const FORBIDDEN = array(
		// Utilisateurs (sinon il pourrait changer le mot de passe admin).
		'edit_users', 'create_users', 'delete_users', 'remove_users', 'add_users',
		'list_users', 'promote_users', 'edit_user',
		// Extensions / thèmes / éditeur de fichiers.
		'install_plugins', 'activate_plugins', 'update_plugins', 'delete_plugins', 'edit_plugins',
		'install_themes', 'switch_themes', 'update_themes', 'delete_themes', 'edit_themes', 'edit_theme_options',
		'edit_files',
		// Réglages du site & cœur.
		'manage_options', 'update_core', 'export', 'import',
		'customize', 'edit_dashboard',
	);

	/**
	 * Capabilities custom du confirmateur — une par case à cocher de sa fiche
	 * (section Équipe). Attribuées PAR UTILISATEUR via WP_User::add_cap(),
	 * jamais au rôle : le rôle reste vide, les droits sont à la carte.
	 *
	 * @var string[]
	 */
	const CONFIRMER_CAPS = array(
		'aod_cap_confirm_orders',  // Confirmation de commandes.
		'aod_cap_tracking',        // Suivi de commande.
		'aod_cap_stock',           // Gestion de stock.
		'aod_cap_shipping_prices', // Personnalisation des tarifs de livraison.
		'aod_cap_stats_confirm',   // Statistiques de confirmation.
		'aod_cap_stats_delivery',  // Statistiques de livraison.
		'aod_cap_abandoned',       // Commandes abandonnées (relance des prospects).
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Réaligne les capacités si le rôle existe mais a dérivé (ex. mise à jour WC).
		add_action( 'init', array( $this, 'maybe_sync_role' ), 20 );
	}

	/**
	 * Construit la table des capacités du rôle client.
	 *
	 * Base : les capacités de shop_manager (compatibilité native WooCommerce
	 * pour commandes/produits), MOINS la liste FORBIDDEN, PLUS la capacité du
	 * dashboard et le strict nécessaire en lecture/upload.
	 *
	 * @return array<string,bool>
	 */
	public static function build_caps() {
		$caps = array();

		$sm = get_role( 'shop_manager' );
		if ( $sm && ! empty( $sm->capabilities ) ) {
			$caps = $sm->capabilities;
		} else {
			// Repli si shop_manager absent : liste explicite minimale e-commerce.
			$caps = array_fill_keys( array(
				'read', 'upload_files',
				'manage_woocommerce', 'view_woocommerce_reports',
				'edit_products', 'edit_others_products', 'publish_products', 'read_private_products',
				'delete_products', 'delete_others_products', 'edit_published_products', 'delete_published_products',
				'manage_product_terms', 'edit_product_terms', 'delete_product_terms', 'assign_product_terms',
				'edit_shop_orders', 'edit_others_shop_orders', 'publish_shop_orders', 'read_private_shop_orders',
				'delete_shop_orders', 'edit_published_shop_orders', 'read_shop_order', 'edit_shop_order',
			), true );
		}

		// Retire toute capacité dangereuse.
		foreach ( self::FORBIDDEN as $bad ) {
			unset( $caps[ $bad ] );
		}

		// Garde-fous indispensables.
		$caps['read']         = true;
		$caps['upload_files'] = true;        // photos produits
		$caps[ AOD_CD_CAP ]   = true;        // ouvre /gestion

		return $caps;
	}

	/**
	 * Crée (ou recrée) les rôles. Appelé à l'activation.
	 */
	public static function create_role() {
		$caps = self::build_caps();
		// remove_role puis add_role = recréation propre et idempotente.
		remove_role( AOD_CD_ROLE );
		add_role( AOD_CD_ROLE, __( 'Gestion Boutique', 'aod-client-dashboard' ), $caps );
		self::create_confirmer_role();
	}

	/**
	 * Crée (ou recrée) le rôle confirmateur : lecture + accès au dashboard,
	 * rien d'autre. Ses droits métier viennent des CONFIRMER_CAPS posées par
	 * utilisateur — jamais manage_woocommerce, jamais delete_*.
	 */
	public static function create_confirmer_role() {
		remove_role( AOD_CD_ROLE_CONFIRMER );
		add_role(
			AOD_CD_ROLE_CONFIRMER,
			__( 'Confirmateur', 'aod-client-dashboard' ),
			array(
				'read'      => true,
				AOD_CD_CAP  => true,
			)
		);
	}

	/**
	 * L'utilisateur possède-t-il cette capability confirmateur ?
	 *
	 * Le gérant (manage_woocommerce) est réputé les avoir toutes ; pour un
	 * confirmateur, la cap est lue sur l'utilisateur (add_cap par user).
	 *
	 * @param string       $cap  Une capability de CONFIRMER_CAPS.
	 * @param WP_User|null $user Défaut : utilisateur courant.
	 * @return bool
	 */
	public static function user_can( $cap, $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		if ( in_array( $cap, self::CONFIRMER_CAPS, true ) && user_can( $user, 'manage_woocommerce' ) ) {
			return true;
		}
		return user_can( $user, $cap );
	}

	/**
	 * Produits affectés à un confirmateur (périmètre de commandes).
	 *
	 * Renvoie les IDs de produits auxquels l'utilisateur est restreint, ou un
	 * tableau vide = AUCUNE restriction. Le gérant (manage_woocommerce) n'est
	 * jamais filtré. La restriction n'existe que si la case « Affectation de
	 * produits » est cochée (meta _aod_products_limited) ET qu'au moins un
	 * produit est sélectionné (meta _aod_assigned_products) — vide = tous.
	 *
	 * @param WP_User|null $user Défaut : utilisateur courant.
	 * @return int[]
	 */
	public static function assigned_products( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		if ( ! $user || ! $user->exists() || user_can( $user, 'manage_woocommerce' ) ) {
			return array();
		}
		if ( ! get_user_meta( $user->ID, '_aod_products_limited', true ) ) {
			return array();
		}
		$ids = get_user_meta( $user->ID, '_aod_assigned_products', true );
		return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	}

	/**
	 * Vivier de confirmateurs éligibles à une assignation automatique
	 * (Phase 42 — tourniquet par charge).
	 *
	 * Un confirmateur est éligible si (a) il est ACTIF (`has_cap( AOD_CD_CAP )` —
	 * la désactivation réversible de l'Équipe retire cette cap sans supprimer le
	 * compte), (b) il porte `aod_cap_confirm_orders`, ET (c) son périmètre produit
	 * (`assigned_products()`) est VIDE (aucune restriction) OU intersecte
	 * `$product_ids` (décision D1 — ROADMAP §A.19 : ne jamais choisir un
	 * confirmateur qui ne pourrait de toute façon pas voir la commande une fois
	 * assignée, le cumul périmètre produit + assignation le masquerait à tous).
	 *
	 * `$product_ids` vide (appel générique, sans commande précise) : seuls les
	 * confirmateurs à périmètre VIDE sont éligibles — un confirmateur restreint
	 * ne peut être jugé éligible qu'au regard de produits précis.
	 *
	 * Le gérant n'entre jamais dans ce vivier : ce n'est pas un confirmateur
	 * (rôle `AOD_CD_ROLE_CONFIRMER`), il n'a pas besoin qu'on lui assigne quoi
	 * que ce soit — il voit tout par construction ailleurs dans le dashboard.
	 *
	 * @param int[] $product_ids   IDs produits (parents et/ou variations) de la
	 *                             commande à assigner. Vide = aucune commande précise.
	 * @param bool  $ignore_scope  Phase 42.4 — `true` saute le filtre périmètre
	 *                             produit (c) : réservé au sélecteur MANUEL du
	 *                             gérant, qui doit pouvoir choisir n'importe quel
	 *                             confirmateur actif, y compris restreint à
	 *                             d'autres produits (choix délibéré, pas une
	 *                             proposition automatique). Le tourniquet (42.1)
	 *                             n'utilise JAMAIS ce paramètre : il doit rester
	 *                             borné au périmètre produit.
	 * @return WP_User[] Triés par ID croissant (déterminisme du choix à charge égale).
	 */
	public static function eligible_confirmers( $product_ids = array(), $ignore_scope = false ) {
		$product_ids = array_values( array_filter( array_map( 'absint', (array) $product_ids ) ) );

		$members = get_users( array( 'role' => AOD_CD_ROLE_CONFIRMER ) );
		$out     = array();

		foreach ( $members as $member ) {
			if ( ! $member->has_cap( AOD_CD_CAP ) ) {
				continue; // Inactif (Équipe).
			}
			if ( ! self::user_can( 'aod_cap_confirm_orders', $member ) ) {
				continue;
			}
			$scope = self::assigned_products( $member );
			if ( ! $ignore_scope && $scope && ! array_intersect( $scope, $product_ids ) ) {
				continue; // Périmètre non vide et ne couvrant aucun produit de la commande.
			}
			$out[] = $member;
		}

		usort( $out, function ( $a, $b ) {
			return $a->ID <=> $b->ID;
		} );

		return $out;
	}

	/**
	 * Confiance zéro dans un `confirmer_id` posté : ne renvoie l'utilisateur QUE
	 * s'il est un VRAI confirmateur actif porteur de `aod_cap_confirm_orders` —
	 * jamais un compte arbitraire (administrateur, utilisateur sans rapport…).
	 *
	 * Extrait de `AOD_CD_Section_Orders::valid_confirmer()` (Phase 42.4) pour
	 * être partagé avec l'assignation des paniers abandonnés (Phase 43.3) —
	 * une seule définition de « confirmateur valide », jamais deux
	 * implémentations qui pourraient diverger. `Section_Orders::valid_confirmer()`
	 * délègue désormais ici ; ses appelants (`apply_assignment()`) sont
	 * inchangés.
	 *
	 * @param int $confirmer_id
	 * @return WP_User|false
	 */
	public static function valid_confirmer( $confirmer_id ) {
		$target = get_user_by( 'id', absint( $confirmer_id ) );
		$valid  = $target instanceof WP_User
			&& in_array( AOD_CD_ROLE_CONFIRMER, (array) $target->roles, true )
			&& $target->has_cap( AOD_CD_CAP )
			&& self::user_can( 'aod_cap_confirm_orders', $target );
		return $valid ? $target : false;
	}

	/**
	 * Étiquette d'un confirmateur assigné, à partir de son seul ID — tiret
	 * sourd si non assigné (`0`) ou si le compte a été supprimé depuis.
	 *
	 * Extrait de `AOD_CD_Section_Orders::assigned_confirmer_label()`
	 * (Phase 42.7) pour être partagé avec la colonne « Assigné à » des
	 * paniers abandonnés (Phase 43.3), qui lit une COLONNE de table
	 * (`assigned_confirmer`) plutôt qu'une meta de commande — seule
	 * l'extraction de l'ID diffère entre les deux appelants, le rendu est
	 * identique.
	 *
	 * 🚨 Ne vérifie AUCUNE capability : c'est à l'appelant de garder
	 * l'affichage gérant uniquement — un confirmateur ne doit jamais voir le
	 * nom d'un assigné, y compris le sien. `$assigned_by` hérite de cette
	 * règle : pas de seconde garde ici, elle divergerait de la première.
	 *
	 * `$assigned_by` (Phase 43.4) — ORIGINE de l'assignation, jamais l'identité
	 * de son auteur (A.20, Constat 3) :
	 *   · `0` / absent  → aucun fragment. C'est le cas du tourniquet
	 *                     automatique, ET le comportement d'avant la 43.4 :
	 *                     un appel à un seul argument rend exactement la même
	 *                     chaîne qu'avant.
	 *   · un gérant     → fragment littéral « Admin ».
	 *   · un confirmateur (cas conversion d'un abandon) → aucun fragment : le
	 *                     nom de l'assigné suffit, seule l'assignation manuelle
	 *                     du gérant a besoin d'être distinguée du tourniquet.
	 *   · compte supprimé OU rétrogradé → aucun fragment. Dégradation ASSUMÉE :
	 *                     le second cas est atteignable depuis la section
	 *                     Équipe, et la commande se relit alors « automatique ».
	 *                     L'éviter supposerait de figer le rôle au moment de
	 *                     l'assignation — donc une seconde source de vérité.
	 *
	 * ⚠️ Une signature sans assignation est une incohérence, pas un état : si
	 * `$id` est nul, on rend le tiret sourd QUEL QUE SOIT `$assigned_by`.
	 *
	 * @param int $id          ID du confirmateur assigné, `0` = non assigné.
	 * @param int $assigned_by ID de l'utilisateur qui a posé l'assignation,
	 *                         `0` = automatique/inconnue.
	 * @return string HTML échappé.
	 */
	public static function confirmer_label_html( $id, $assigned_by = 0 ) {
		$id = (int) $id;
		if ( ! $id ) {
			return '<span class="aod-cd-muted">—</span>';
		}
		$user = get_user_by( 'id', $id );
		if ( ! $user ) {
			return '<span class="aod-cd-muted">—</span>';
		}

		$html = '<span class="aod-cd-assigned-name">' . esc_html( $user->display_name ) . '</span>';

		// ⚠️ `user_can()` NON qualifié = la fonction GLOBALE de WordPress
		// (`user_can( $user, $cap )`), pas `AOD_CD_Roles::user_can( $cap, $user )`
		// qui vit dans cette même classe avec des arguments INVERSÉS. Une méthode
		// ne se résout jamais sans `self::`/`$this->` — mais l'homonymie est un
		// piège de relecture, d'où cette note. Elle rend `false` sur un ID
		// inexistant : le compte supprimé est couvert sans garde en plus.
		$assigned_by = (int) $assigned_by;
		if ( $assigned_by && user_can( $assigned_by, 'manage_woocommerce' ) ) {
			$html .= '<span class="aod-cd-assigned-by">' . esc_html__( 'Admin', 'aod-client-dashboard' ) . '</span>';
		}

		return $html;
	}

	/**
	 * Si le rôle existe, s'assure qu'il n'a pas gagné de capacité interdite
	 * (par ex. après une mise à jour de WooCommerce qui recopie shop_manager).
	 */
	public function maybe_sync_role() {
		$role = get_role( AOD_CD_ROLE );

		// (Re)construit le rôle s'il est absent ou si la version du plugin a
		// changé. Indispensable : create_role() ne tourne qu'à l'activation, qui
		// ne se rejoue pas sur un clone bind-monté ni lors d'une simple mise à
		// jour. Sans ça, le gérant peut se retrouver sans les capacités produits/
		// commandes → « Accès refusé » sur l'ajout de catégorie ou de produit.
		if ( ! $role || get_option( 'aod_cd_roles_version' ) !== AOD_CD_VERSION ) {
			self::create_role();
			update_option( 'aod_cd_roles_version', AOD_CD_VERSION );
			$role = get_role( AOD_CD_ROLE );
		}
		if ( $role ) {
			foreach ( self::FORBIDDEN as $bad ) {
				if ( isset( $role->capabilities[ $bad ] ) ) {
					$role->remove_cap( $bad );
				}
			}
			if ( empty( $role->capabilities[ AOD_CD_CAP ] ) ) {
				$role->add_cap( AOD_CD_CAP );
			}
		}

		// Rôle confirmateur : recréé s'il manque, et ramené à { read, dashboard }
		// s'il a dérivé — il ne doit JAMAIS porter de cap métier (elles sont
		// posées par utilisateur), encore moins manage_woocommerce/delete_*.
		$conf = get_role( AOD_CD_ROLE_CONFIRMER );
		if ( ! $conf ) {
			self::create_confirmer_role();
			$conf = get_role( AOD_CD_ROLE_CONFIRMER );
		}
		if ( $conf ) {
			foreach ( array_keys( $conf->capabilities ) as $cap ) {
				if ( 'read' !== $cap && AOD_CD_CAP !== $cap ) {
					$conf->remove_cap( $cap );
				}
			}
			if ( empty( $conf->capabilities[ AOD_CD_CAP ] ) ) {
				$conf->add_cap( AOD_CD_CAP );
			}
		}
	}
}
