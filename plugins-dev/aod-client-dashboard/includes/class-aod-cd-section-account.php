<?php
/**
 * Section « Mon compte » (self-service du gérant) : profil, mot de passe, et la
 * zone dangereuse de réinitialisation de boutique (suppression de tous les
 * produits et commandes après recopie d'une phrase de confirmation).
 *
 * 🧩 EXTRACTION (Phase 30.3). Sortie du monolithe `class-aod-cd-dashboard.php` à
 * comportement CONSTANT : mêmes actions AJAX (`aod_cd_save_account`,
 * `aod_cd_reset_shop`) — `dashboard.js` inchangé —, même rendu. Move verbatim ;
 * seul `self::page_head` (helper d'UI partagé) devient `AOD_CD_Dashboard::…`.
 * La constante `RESET_PHRASE` suit la section (elle n'était utilisée qu'ici).
 *
 * ⚠️ À NE PAS CONFONDRE avec `AOD_CD_Account` : cette dernière crée les COMPTES
 * gérant côté admin (Utilisateurs → Accès Gérant, `wp_insert_user`). La présente
 * classe est le « Mon compte » que le gérant édite lui-même dans /gestion.
 *
 * ⚠️ `ajax_save_account` garde `manage_woocommerce` (arbitrage D12 : le gérant
 * gère les mots de passe ; un confirmateur n'a pas de section « Mon compte »).
 * `tests/unit/test-permissions-sections.php` déclare cette divergence avec sa
 * raison — elle ne doit pas être « alignée ».
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Account {

	/** @var AOD_CD_Section_Account|null */
	protected static $instance = null;

	/**
	 * Phrase exacte que le gérant doit recopier pour confirmer la
	 * réinitialisation de la boutique. Volontairement NON traduite : la
	 * comparaison côté serveur doit porter sur la même chaîne que celle
	 * affichée, quelle que soit la langue active du dashboard.
	 */
	const RESET_PHRASE = 'SUPPRIMER DEFINITIVEMENT TOUS LES PRODUITS ET TOUTES LES COMMANDES';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_aod_cd_save_account', array( $this, 'ajax_save_account' ) );
		add_action( 'wp_ajax_aod_cd_reset_shop', array( $this, 'ajax_reset_shop' ) );
	}

	public function render() {
		$user = wp_get_current_user();

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Mon compte', 'aod-client-dashboard' ),
			__( 'Votre profil et votre mot de passe.', 'aod-client-dashboard' )
		);
		?>
		<form class="aod-cd-form aod-cd-settings-form" data-action="aod_cd_save_account">

			<div class="aod-cd-panel">
				<div class="aod-cd-panel__head">
					<span class="aod-cd-panel__ic" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'user', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-panel__name"><?php esc_html_e( 'Profil', 'aod-client-dashboard' ); ?></span>
				</div>
				<div class="aod-cd-panel__body">
					<div class="aod-cd-row2">
						<label class="aod-cd-field">
							<span class="aod-cd-label"><?php esc_html_e( 'Identifiant de connexion', 'aod-client-dashboard' ); ?></span>
							<input type="text" value="<?php echo esc_attr( $user->user_login ); ?>" disabled>
							<span class="aod-cd-help"><?php esc_html_e( 'L’identifiant ne peut pas être modifié.', 'aod-client-dashboard' ); ?></span>
						</label>
						<label class="aod-cd-field">
							<span class="aod-cd-label"><?php esc_html_e( 'Nom affiché', 'aod-client-dashboard' ); ?></span>
							<input type="text" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>">
						</label>
					</div>
					<label class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Adresse e-mail', 'aod-client-dashboard' ); ?></span>
						<input type="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>">
					</label>
				</div>
			</div>

			<div class="aod-cd-panel">
				<div class="aod-cd-panel__head">
					<span class="aod-cd-panel__ic" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'locked', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="aod-cd-panel__name"><?php esc_html_e( 'Sécurité', 'aod-client-dashboard' ); ?></span>
				</div>
				<div class="aod-cd-panel__body">
					<p class="aod-cd-help"><?php esc_html_e( 'Laissez ces deux champs vides pour conserver le mot de passe actuel.', 'aod-client-dashboard' ); ?></p>
					<div class="aod-cd-row2">
						<label class="aod-cd-field">
							<span class="aod-cd-label"><?php esc_html_e( 'Nouveau mot de passe', 'aod-client-dashboard' ); ?></span>
							<input type="password" name="new_password" autocomplete="new-password">
						</label>
						<label class="aod-cd-field">
							<span class="aod-cd-label"><?php esc_html_e( 'Confirmer', 'aod-client-dashboard' ); ?></span>
							<input type="password" name="confirm_password" autocomplete="new-password">
						</label>
					</div>
				</div>
			</div>

			<div class="aod-cd-form-foot aod-cd-savebar">
				<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Enregistrer', 'aod-client-dashboard' ); ?></button>
				<span class="aod-cd-form-msg"></span>
			</div>
		</form>
		<?php
		// Zone de danger : réinitialisation complète de la boutique. Réservée aux
		// profils pouvant gérer WooCommerce (gérant + admin) ; les autres ne la voient pas.
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$this->render_reset_zone();
		}
	}

	/**
	 * Bloc « Zone de danger » : supprime tous les produits (avec leurs photos) et
	 * toutes les commandes, puis remet les compteurs (dont le n° de commande) à zéro.
	 *
	 * La validation impose de recopier une phrase longue et exacte : aucun clic
	 * accidentel ne peut déclencher la purge.
	 */
	protected function render_reset_zone() {
		?>
		<div class="aod-cd-danger" id="aod-cd-reset">
			<h2 class="aod-cd-danger-title"><?php echo AOD_CD_Icon::svg( 'warning', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Zone de danger', 'aod-client-dashboard' ); ?></h2>
			<p class="aod-cd-danger-lead"><?php esc_html_e( 'Réinitialiser la boutique supprime définitivement TOUS les produits (avec leurs photos) et TOUTES les commandes, puis remet le compteur des numéros de commande à #1. Les pages, réglages et votre compte ne sont pas touchés. Cette action est irréversible.', 'aod-client-dashboard' ); ?></p>

			<form id="aod-cd-reset-form" data-phrase="<?php echo esc_attr( self::RESET_PHRASE ); ?>">
				<label class="aod-cd-field">
					<span class="aod-cd-label"><?php esc_html_e( 'Pour confirmer, recopiez exactement la phrase ci-dessous :', 'aod-client-dashboard' ); ?></span>
					<code class="aod-cd-danger-phrase"><?php echo esc_html( self::RESET_PHRASE ); ?></code>
					<input type="text" class="aod-cd-reset-input" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="<?php echo esc_attr( self::RESET_PHRASE ); ?>">
				</label>
				<div class="aod-cd-form-foot">
					<button type="submit" class="aod-cd-btn aod-cd-btn-danger" disabled><?php esc_html_e( 'Réinitialiser la boutique', 'aod-client-dashboard' ); ?></button>
					<span class="aod-cd-form-msg"></span>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX : met à jour le compte de l'utilisateur connecté (nom, e-mail, mot de passe).
	 * Réservé au gérant (manage_woocommerce) — le compte d'un confirmateur se
	 * gère depuis la section Équipe.
	 */
	public function ajax_save_account() {
		check_ajax_referer( 'aod_cd', 'nonce' );

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			wp_send_json_error( array( 'message' => __( 'Session expirée, reconnectez-vous.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$display = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$pass    = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';
		$confirm = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';

		$data = array( 'ID' => $user->ID );

		if ( '' !== $display ) {
			$data['display_name'] = $display;
		}

		if ( '' !== $email && $email !== $user->user_email ) {
			if ( ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Adresse e-mail invalide.', 'aod-client-dashboard' ) ), 400 );
			}
			$owner = email_exists( $email );
			if ( $owner && (int) $owner !== (int) $user->ID ) {
				wp_send_json_error( array( 'message' => __( 'Cette adresse e-mail est déjà utilisée par un autre compte.', 'aod-client-dashboard' ) ), 400 );
			}
			$data['user_email'] = $email;
		}

		if ( '' !== $pass || '' !== $confirm ) {
			if ( $pass !== $confirm ) {
				wp_send_json_error( array( 'message' => __( 'Les deux mots de passe ne correspondent pas.', 'aod-client-dashboard' ) ), 400 );
			}
			if ( strlen( $pass ) < 8 ) {
				wp_send_json_error( array( 'message' => __( 'Le mot de passe doit faire au moins 8 caractères.', 'aod-client-dashboard' ) ), 400 );
			}
			$data['user_pass'] = $pass;
		}

		$res = wp_update_user( $data );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}

		// Changer le mot de passe invalide la session courante : on la rétablit
		// pour que le gérant reste connecté au lieu d'être éjecté du dashboard.
		if ( isset( $data['user_pass'] ) ) {
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true );
		}

		wp_send_json_success( array( 'message' => __( 'Compte mis à jour.', 'aod-client-dashboard' ) ) );
	}

	/* ============================================================
	 * AJAX : réinitialisation complète de la boutique
	 * ========================================================== */

	/**
	 * Supprime tous les produits (et leurs images), toutes les commandes, puis
	 * remet les compteurs à zéro. Tout passe par les API WordPress/WooCommerce
	 * (HPOS-safe, indépendant du préfixe de table). Exige la phrase de confirmation.
	 */
	public function ajax_reset_shop() {
		check_ajax_referer( 'aod_cd', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$phrase = isset( $_POST['phrase'] ) ? trim( (string) wp_unslash( $_POST['phrase'] ) ) : '';
		if ( ! hash_equals( self::RESET_PHRASE, $phrase ) ) {
			wp_send_json_error( array( 'message' => __( 'La phrase de confirmation ne correspond pas. Réinitialisation annulée.', 'aod-client-dashboard' ) ), 400 );
		}

		// Évite une coupure PHP au milieu de la purge sur un gros catalogue.
		if ( function_exists( 'wc_set_time_limit' ) ) {
			wc_set_time_limit( 0 );
		}

		$deleted_orders   = $this->reset_delete_orders();
		$result           = $this->reset_delete_products();
		$deleted_products = $result['products'];
		$deleted_images   = $result['images'];
		$deleted_leads    = $this->reset_delete_cod_leads();

		// Compteur séquentiel des numéros de commande (mu-plugin) -> repart à #1.
		update_option( 'aod_seq_order_counter', 0, false );

		// Purge les caches/transients WooCommerce pour que les compteurs et les
		// listes (produits, rapports) reflètent immédiatement la boutique vide.
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'product', true );
		}

		$message = sprintf(
			/* translators: 1: nb produits, 2: nb images, 3: nb commandes. */
			__( 'Boutique réinitialisée : %1$d produit(s), %2$d image(s) et %3$d commande(s) supprimés. La prochaine commande portera le n° #1.', 'aod-client-dashboard' ),
			$deleted_products,
			$deleted_images,
			$deleted_orders
		);
		if ( $deleted_leads > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: nb de prospects (leads) supprimés. */
				__( '%d prospect(s) du formulaire également supprimés.', 'aod-client-dashboard' ),
				$deleted_leads
			);
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Supprime définitivement toutes les commandes (y compris remboursements),
	 * par lots, via l'API WooCommerce (compatible HPOS et stockage hérité).
	 *
	 * @return int Nombre de commandes supprimées.
	 */
	protected function reset_delete_orders() {
		$deleted = 0;
		// wc_get_order_types() renvoie une LISTE de noms de types (valeurs), pas
		// des clés : on l'utilise telle quelle (array_keys donnerait [0]).
		$types = wc_get_order_types( 'order-count' );
		if ( empty( $types ) ) {
			$types = array( 'shop_order' );
		}

		// Boucle par lots : on relit à chaque tour car les IDs disparaissent.
		do {
			$ids = wc_get_orders( array(
				'limit'  => 50,
				'type'   => $types,
				'status' => 'any',
				'return' => 'ids',
			) );
			foreach ( $ids as $id ) {
				$order = wc_get_order( $id );
				if ( $order && $order->delete( true ) ) {
					$deleted++;
				}
			}
		} while ( ! empty( $ids ) );

		return $deleted;
	}

	/**
	 * Supprime définitivement tous les produits et variations, AINSI QUE leurs
	 * images (image principale + galerie + images de variations), par lots.
	 *
	 * @return array{products:int,images:int}
	 */
	protected function reset_delete_products() {
		$deleted_products = 0;
		$deleted_images   = 0;

		do {
			$products = wc_get_products( array(
				'limit'  => 50,
				'status' => array( 'publish', 'pending', 'draft', 'private', 'trash' ),
				'return' => 'objects',
			) );

			foreach ( $products as $product ) {
				// Rassemble les images à supprimer : principale + galerie. Pour un
				// produit variable, on ajoute aussi les images propres des variations.
				$image_ids = array();
				$thumb     = (int) $product->get_image_id();
				if ( $thumb ) {
					$image_ids[] = $thumb;
				}
				foreach ( (array) $product->get_gallery_image_ids() as $gid ) {
					$image_ids[] = (int) $gid;
				}
				if ( $product->is_type( 'variable' ) ) {
					foreach ( $product->get_children() as $child_id ) {
						$variation = wc_get_product( $child_id );
						if ( $variation && $variation->get_image_id() ) {
							$image_ids[] = (int) $variation->get_image_id();
						}
					}
				}

				if ( $product->delete( true ) ) {
					$deleted_products++;
				}

				foreach ( array_unique( array_filter( $image_ids ) ) as $att_id ) {
					if ( wp_delete_attachment( $att_id, true ) ) {
						$deleted_images++;
					}
				}
			}
		} while ( ! empty( $products ) );

		return array(
			'products' => $deleted_products,
			'images'   => $deleted_images,
		);
	}

	/**
	 * Vide la table des prospects du formulaire COD si elle existe.
	 *
	 * @return int Nombre de lignes supprimées (0 si la table n'existe pas).
	 */
	protected function reset_delete_cod_leads() {
		global $wpdb;
		$table  = $wpdb->prefix . 'aod_cod_leads';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return 0;
		}
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- `{$table}` = $wpdb->prefix (non paramétrable) ; table custom (pas d’API WP, cache non pertinent).
		$wpdb->query( "TRUNCATE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- `{$table}` = $wpdb->prefix (non paramétrable) ; table custom (pas d’API WP, cache non pertinent).
		return $count;
	}
}
