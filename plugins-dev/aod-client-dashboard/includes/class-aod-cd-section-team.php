<?php
/**
 * Section « Équipe » : CRUD des confirmateurs depuis /gestion.
 *
 * Le gérant crée/édite/désactive ses confirmateurs et coche leurs permissions
 * (une case = une capability de AOD_CD_Roles::CONFIRMER_CAPS, posée PAR
 * utilisateur) — sans jamais passer par wp-admin. Section et handlers réservés
 * au gérant (manage_woocommerce) ; les handlers ne peuvent cibler que des
 * comptes portant le rôle confirmateur (jamais un gérant/admin).
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Team {

	/** @var AOD_CD_Section_Team|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_aod_cd_save_member', array( $this, 'ajax_save_member' ) );
		add_action( 'wp_ajax_aod_cd_delete_member', array( $this, 'ajax_delete_member' ) );
		// Trace du dernier accès d'un confirmateur (affichée dans la liste).
		add_action( 'wp_login', array( $this, 'track_last_login' ), 10, 2 );
	}

	/**
	 * Libellés des cases à cocher (une par capability confirmateur).
	 *
	 * @return array<string,string> cap => libellé i18n.
	 */
	public static function cap_labels() {
		return array(
			'aod_cap_confirm_orders'  => __( 'Confirmation de commandes', 'aod-client-dashboard' ),
			'aod_cap_tracking'        => __( 'Suivi de commande', 'aod-client-dashboard' ),
			'aod_cap_stock'           => __( 'Gestion de stock', 'aod-client-dashboard' ),
			'aod_cap_shipping_prices' => __( 'Personnalisation des tarifs de livraison', 'aod-client-dashboard' ),
			'aod_cap_stats_confirm'   => __( 'Statistiques de confirmation', 'aod-client-dashboard' ),
			'aod_cap_stats_delivery'  => __( 'Statistiques de livraison', 'aod-client-dashboard' ),
			'aod_cap_abandoned'       => __( 'Commandes abandonnées', 'aod-client-dashboard' ),
		);
	}

	/**
	 * Libellés COURTS des capabilities, pour les pastilles d'une carte membre.
	 *
	 * Les libellés longs (cap_labels()) tiennent dans une grille de cases à cocher,
	 * pas sur une carte : « Personnalisation des tarifs de livraison » en pastille
	 * dévorait la carte entière. Le libellé long reste en infobulle.
	 *
	 * @return array<string,string> cap => libellé court i18n.
	 */
	public static function cap_short_labels() {
		return array(
			'aod_cap_confirm_orders'  => __( 'Commandes', 'aod-client-dashboard' ),
			'aod_cap_tracking'        => __( 'Suivi', 'aod-client-dashboard' ),
			'aod_cap_stock'           => __( 'Stock', 'aod-client-dashboard' ),
			'aod_cap_shipping_prices' => __( 'Tarifs', 'aod-client-dashboard' ),
			'aod_cap_stats_confirm'   => __( 'Stats confirmation', 'aod-client-dashboard' ),
			'aod_cap_stats_delivery'  => __( 'Stats livraison', 'aod-client-dashboard' ),
			'aod_cap_abandoned'       => __( 'Abandonnées', 'aod-client-dashboard' ),
		);
	}

	/**
	 * Initiales d'un nom affiché, pour l'avatar d'une carte membre.
	 *
	 * @param string $name
	 * @return string 1 à 2 caractères, en majuscules.
	 */
	protected function initials( $name ) {
		$parts = preg_split( '/\s+/', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $parts ) {
			return '?';
		}
		$first = function_exists( 'mb_substr' ) ? mb_substr( $parts[0], 0, 1 ) : substr( $parts[0], 0, 1 );
		$last  = '';
		if ( count( $parts ) > 1 ) {
			$tail = end( $parts );
			$last = function_exists( 'mb_substr' ) ? mb_substr( $tail, 0, 1 ) : substr( $tail, 0, 1 );
		}
		$ini = $first . $last;
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $ini ) : strtoupper( $ini );
	}

	/**
	 * Mémorise la dernière connexion d'un confirmateur.
	 *
	 * @param string  $login
	 * @param WP_User $user
	 */
	public function track_last_login( $login, $user ) {
		if ( $user instanceof WP_User && in_array( AOD_CD_ROLE_CONFIRMER, (array) $user->roles, true ) ) {
			update_user_meta( $user->ID, 'aod_cd_last_login', current_time( 'mysql' ) );
		}
	}

	/* ============================================================
	 * Rendu de la section
	 * ========================================================== */

	public function render() {
		$members = get_users( array(
			'role'    => AOD_CD_ROLE_CONFIRMER,
			'orderby' => 'display_name',
			'order'   => 'ASC',
		) );
		$labels = self::cap_labels();
		$shorts = self::cap_short_labels();

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Équipe', 'aod-client-dashboard' ),
			__( 'Vos confirmateurs, leurs permissions et leur périmètre produits.', 'aod-client-dashboard' ),
			'<button type="button" class="aod-cd-btn aod-cd-btn-primary" id="aod-cd-team-add">+ ' . esc_html__( 'Ajouter un confirmateur', 'aod-client-dashboard' ) . '</button>'
		);

		if ( ! $members ) {
			echo AOD_CD_Dashboard::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
				'team',
				__( 'Aucun confirmateur pour le moment.', 'aod-client-dashboard' ),
				__( 'Créez un compte pour déléguer la confirmation des commandes, sans donner accès à toute la boutique.', 'aod-client-dashboard' )
			);
		} else {
			echo '<div class="aod-cd-members">';
			foreach ( $members as $m ) {
				$caps = array();
				foreach ( AOD_CD_Roles::CONFIRMER_CAPS as $cap ) {
					if ( ! empty( $m->caps[ $cap ] ) ) {
						$caps[] = $cap;
					}
				}
				$limited  = (bool) get_user_meta( $m->ID, '_aod_products_limited', true );
				$assigned = array_values( array_filter( array_map( 'absint', (array) get_user_meta( $m->ID, '_aod_assigned_products', true ) ) ) );
				$active   = $m->has_cap( AOD_CD_CAP );
				$last     = get_user_meta( $m->ID, 'aod_cd_last_login', true );
				$last_txt = $last
					? date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $last ) )
					: __( 'jamais connecté', 'aod-client-dashboard' );
				?>
				<article class="aod-cd-member<?php echo $active ? '' : ' is-off'; ?>"
					data-member
					data-id="<?php echo esc_attr( $m->ID ); ?>"
					data-login="<?php echo esc_attr( $m->user_login ); ?>"
					data-display="<?php echo esc_attr( $m->display_name ); ?>"
					data-caps="<?php echo esc_attr( implode( ',', $caps ) ); ?>"
					data-limited="<?php echo $limited ? '1' : '0'; ?>"
					data-products="<?php echo esc_attr( implode( ',', $assigned ) ); ?>">

					<div class="aod-cd-member__top">
						<span class="aod-cd-member__av" aria-hidden="true"><?php echo esc_html( $this->initials( $m->display_name ) ); ?></span>
						<span class="aod-cd-member__id">
							<b class="aod-cd-member__name"><?php echo esc_html( $m->display_name ); ?></b>
							<span class="aod-cd-member__login"><?php echo esc_html( $m->user_login ); ?></span>
						</span>
						<?php
						echo $active // phpcs:ignore WordPress.Security.EscapeOutput
							? AOD_CD_Dashboard::pill( __( 'Actif', 'aod-client-dashboard' ), 'ok' )
							: AOD_CD_Dashboard::pill( __( 'Désactivé', 'aod-client-dashboard' ), 'neutral' );
						?>
					</div>

					<div class="aod-cd-member__caps">
						<?php
						if ( ! $caps ) {
							// Un confirmateur sans aucune permission ne peut RIEN faire : ce
							// n'est pas un détail cosmétique, on l'affiche en alerte.
							echo AOD_CD_Dashboard::pill( __( 'Aucune permission', 'aod-client-dashboard' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput
						} else {
							foreach ( $caps as $cap ) {
								$short = isset( $shorts[ $cap ] ) ? $shorts[ $cap ] : $cap;
								$full  = isset( $labels[ $cap ] ) ? $labels[ $cap ] : $cap;
								echo '<span class="aod-cd-pill aod-cd-pill--info" title="' . esc_attr( $full ) . '">' . esc_html( $short ) . '</span>';
							}
						}
						if ( $limited && $assigned ) {
							$names = array_filter( array_map( 'get_the_title', $assigned ) );
							echo '<span class="aod-cd-pill aod-cd-pill--neutral" title="' . esc_attr( implode( ' · ', $names ) ) . '">'
								/* translators: %d : nombre de produits affectés au confirmateur. */
								. esc_html( sprintf( __( 'Produits : %d', 'aod-client-dashboard' ), count( $assigned ) ) )
								. '</span>';
						} elseif ( $limited ) {
							// Case cochée mais aucun produit retenu : le périmètre ne restreint
							// rien du tout (cf. ajax_save_member) — le dire plutôt que le taire.
							echo AOD_CD_Dashboard::pill( __( 'Périmètre vide : tous les produits', 'aod-client-dashboard' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput
						}
						?>
					</div>

					<div class="aod-cd-member__foot">
						<span class="aod-cd-member__last">
							<?php
							/* translators: %s : date de dernière connexion, ou « jamais connecté ». */
							printf( esc_html__( 'Dernier accès : %s', 'aod-client-dashboard' ), esc_html( $last_txt ) );
							?>
						</span>
						<span class="aod-cd-member__act">
							<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-team-edit"><?php esc_html_e( 'Modifier', 'aod-client-dashboard' ); ?></button>
							<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-team-toggle">
								<?php $active ? esc_html_e( 'Désactiver', 'aod-client-dashboard' ) : esc_html_e( 'Activer', 'aod-client-dashboard' ); ?>
							</button>
							<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-btn-danger aod-cd-team-delete"><?php esc_html_e( 'Supprimer', 'aod-client-dashboard' ); ?></button>
						</span>
					</div>
				</article>
				<?php
			}
			echo '</div>';
		}

		// Périmètre produits : liste proposée dans la modale d'édition.
		$products = wc_get_products( array(
			'limit'   => -1,
			'status'  => 'publish',
			'orderby' => 'title',
			'order'   => 'ASC',
		) );
		?>

		<div class="aod-cd-modal" id="aod-cd-team-modal" hidden>
			<div class="aod-cd-modal-backdrop" data-team-close></div>
			<div class="aod-cd-modal-box" role="dialog" aria-modal="true" aria-labelledby="aod-cd-team-form-title">
				<header class="aod-cd-modal-head">
					<h2 class="aod-cd-modal-title" id="aod-cd-team-form-title"
						data-create="<?php esc_attr_e( 'Ajouter un confirmateur', 'aod-client-dashboard' ); ?>"
						data-edit="<?php esc_attr_e( 'Modifier le confirmateur', 'aod-client-dashboard' ); ?>"><?php esc_html_e( 'Ajouter un confirmateur', 'aod-client-dashboard' ); ?></h2>
					<button type="button" class="aod-cd-modal-x" data-team-close aria-label="<?php esc_attr_e( 'Fermer', 'aod-client-dashboard' ); ?>">&times;</button>
				</header>
				<div class="aod-cd-modal-body">
					<form class="aod-cd-form" id="aod-cd-team-form">
						<input type="hidden" name="member_id" value="0">

						<div class="aod-cd-row2">
							<label class="aod-cd-field">
								<span class="aod-cd-label"><?php esc_html_e( 'Identifiant de connexion', 'aod-client-dashboard' ); ?></span>
								<input type="text" name="login" autocomplete="off" autocapitalize="off" spellcheck="false" required>
								<span class="aod-cd-help"><?php esc_html_e( 'L’identifiant ne peut pas être modifié.', 'aod-client-dashboard' ); ?></span>
							</label>
							<label class="aod-cd-field">
								<span class="aod-cd-label"><?php esc_html_e( 'Nom affiché', 'aod-client-dashboard' ); ?></span>
								<input type="text" name="display_name" required>
							</label>
						</div>

						<label class="aod-cd-field">
							<span class="aod-cd-label"><?php esc_html_e( 'Mot de passe', 'aod-client-dashboard' ); ?></span>
							<input type="text" name="password" autocomplete="new-password" spellcheck="false">
							<span class="aod-cd-help"><?php esc_html_e( '10 caractères minimum. Laissez vide à la création pour en générer un automatiquement — il ne sera affiché qu’une seule fois, notez-le. Laissez vide en modification pour conserver le mot de passe actuel.', 'aod-client-dashboard' ); ?></span>
						</label>

						<h3 class="aod-cd-form-subtitle"><?php esc_html_e( 'Permissions', 'aod-client-dashboard' ); ?></h3>
						<p class="aod-cd-help"><?php esc_html_e( 'Un confirmateur ne voit que ce que vous cochez ici.', 'aod-client-dashboard' ); ?></p>
						<div class="aod-cd-toggles aod-cd-team-caps">
							<?php foreach ( $labels as $cap => $label ) : ?>
								<label class="aod-cd-toggle">
									<input type="checkbox" name="caps[]" value="<?php echo esc_attr( $cap ); ?>">
									<span><?php echo esc_html( $label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>

						<h3 class="aod-cd-form-subtitle"><?php esc_html_e( 'Périmètre produits', 'aod-client-dashboard' ); ?></h3>
						<div class="aod-cd-toggles">
							<label class="aod-cd-toggle">
								<input type="checkbox" name="products_limited" value="1">
								<span><?php esc_html_e( 'Limiter à certains produits', 'aod-client-dashboard' ); ?></span>
							</label>
						</div>

						<div class="aod-cd-team-products" id="aod-cd-team-products" hidden>
							<p class="aod-cd-help"><?php esc_html_e( 'Cochez les produits auxquels ce confirmateur est limité. Aucun produit coché = tous les produits.', 'aod-client-dashboard' ); ?></p>
							<?php if ( ! $products ) : ?>
								<p class="aod-cd-note"><?php esc_html_e( 'Aucun produit publié pour le moment.', 'aod-client-dashboard' ); ?></p>
							<?php else : ?>
								<div class="aod-cd-team-products-list">
									<?php foreach ( $products as $p ) : ?>
										<label class="aod-cd-toggle">
											<input type="checkbox" name="assigned_products[]" value="<?php echo esc_attr( $p->get_id() ); ?>">
											<span><?php echo esc_html( $p->get_name() ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>

					</form>
				</div>

				<?php
				// Le pied vit HORS de la zone de défilement : les boutons restent
				// toujours visibles, sans bricolage de `sticky` (6 permissions + la
				// liste des produits dépassent vite la hauteur d'un mobile).
				// `form="…"` rattache le bouton au formulaire resté dans le corps.
				?>
				<footer class="aod-cd-modal-foot">
					<span class="aod-cd-form-msg"></span>
					<button type="button" class="aod-cd-btn" id="aod-cd-team-cancel"><?php esc_html_e( 'Annuler', 'aod-client-dashboard' ); ?></button>
					<button type="submit" form="aod-cd-team-form" class="aod-cd-btn aod-cd-btn-primary" id="aod-cd-team-submit"
						data-create="<?php esc_attr_e( 'Ajouter', 'aod-client-dashboard' ); ?>"
						data-edit="<?php esc_attr_e( 'Enregistrer', 'aod-client-dashboard' ); ?>"><?php esc_html_e( 'Ajouter', 'aod-client-dashboard' ); ?></button>
				</footer>
			</div>
		</div>
		<?php
	}

	/* ============================================================
	 * AJAX
	 * ========================================================== */

	/**
	 * Garde commune : gérant uniquement ; si une cible est visée, elle doit
	 * exister ET porter le rôle confirmateur (jamais un gérant/admin).
	 *
	 * @param int $member_id 0 = pas de cible (création).
	 * @return WP_User|null La cible validée, ou null en création.
	 */
	protected function guard( $member_id ) {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! $member_id ) {
			return null;
		}
		$target = get_user_by( 'id', $member_id );
		if ( ! $target || ! in_array( AOD_CD_ROLE_CONFIRMER, (array) $target->roles, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Compte introuvable ou protégé.', 'aod-client-dashboard' ) ), 403 );
		}
		return $target;
	}

	/**
	 * AJAX : crée/édite un confirmateur, coche ses permissions, ou bascule
	 * son statut actif (toggle_active).
	 */
	public function ajax_save_member() {
		$member_id = isset( $_POST['member_id'] ) ? absint( $_POST['member_id'] ) : 0;
		$target    = $this->guard( $member_id );

		// Bascule Actif/Désactivé : surcharge utilisateur de la capability
		// d'accès (prime sur le grant du rôle) — réversible.
		if ( $target && ! empty( $_POST['toggle_active'] ) ) {
			if ( $target->has_cap( AOD_CD_CAP ) ) {
				$target->add_cap( AOD_CD_CAP, false );
				$msg = __( 'Compte désactivé : l’accès à l’espace de gestion est refusé.', 'aod-client-dashboard' );
			} else {
				$target->remove_cap( AOD_CD_CAP );
				$msg = __( 'Compte réactivé.', 'aod-client-dashboard' );
			}
			wp_send_json_success( array( 'message' => $msg, 'reload' => true ) );
		}

		$display     = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$pass        = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$caps_in     = isset( $_POST['caps'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['caps'] ) ) : array();
		$limited     = ! empty( $_POST['products_limited'] );
		$assigned_in = isset( $_POST['assigned_products'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['assigned_products'] ) ) : array();

		if ( '' !== $pass && strlen( $pass ) < 10 ) {
			wp_send_json_error( array( 'message' => __( 'Mot de passe trop court : 10 caractères minimum.', 'aod-client-dashboard' ) ), 400 );
		}

		$generated = '';

		if ( ! $target ) {
			// Création.
			$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ), true ) : '';
			if ( '' === $login || ! validate_username( $login ) ) {
				wp_send_json_error( array( 'message' => __( 'Identifiant invalide.', 'aod-client-dashboard' ) ), 400 );
			}
			if ( username_exists( $login ) ) {
				wp_send_json_error( array( 'message' => __( 'Cet identifiant existe déjà.', 'aod-client-dashboard' ) ), 400 );
			}
			if ( '' === $pass ) {
				$pass      = wp_generate_password( 14, true, false );
				$generated = $pass;
			}
			$uid = wp_insert_user( array(
				'user_login'   => $login,
				'user_pass'    => $pass,
				'display_name' => '' !== $display ? $display : $login,
				'role'         => AOD_CD_ROLE_CONFIRMER,
			) );
			if ( is_wp_error( $uid ) ) {
				wp_send_json_error( array( 'message' => $uid->get_error_message() ), 400 );
			}
			$target = new WP_User( $uid );
		} else {
			// Édition : nom affiché + mot de passe optionnel (l'identifiant est immuable).
			$data = array( 'ID' => $target->ID );
			if ( '' !== $display ) {
				$data['display_name'] = $display;
			}
			if ( '' !== $pass ) {
				$data['user_pass'] = $pass;
			}
			$res = wp_update_user( $data );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
			}
		}

		// Permissions à la carte : whitelist stricte, add/remove PAR utilisateur.
		foreach ( AOD_CD_Roles::CONFIRMER_CAPS as $cap ) {
			if ( in_array( $cap, $caps_in, true ) ) {
				$target->add_cap( $cap );
			} else {
				$target->remove_cap( $cap );
			}
		}
		if ( $limited ) {
			update_user_meta( $target->ID, '_aod_products_limited', '1' );
		} else {
			delete_user_meta( $target->ID, '_aod_products_limited' );
		}

		// Périmètre produits : IDs validés (type product), posé seulement si la
		// case est cochée ET qu'au moins un produit est sélectionné — vide = tous.
		$assigned = array();
		if ( $limited ) {
			foreach ( array_unique( array_filter( $assigned_in ) ) as $pid ) {
				if ( 'product' === get_post_type( $pid ) ) {
					$assigned[] = $pid;
				}
			}
		}
		if ( $assigned ) {
			update_user_meta( $target->ID, '_aod_assigned_products', $assigned );
		} else {
			delete_user_meta( $target->ID, '_aod_assigned_products' );
		}

		$data = array(
			'message' => $member_id ? __( 'Confirmateur mis à jour.', 'aod-client-dashboard' ) : __( 'Confirmateur créé.', 'aod-client-dashboard' ),
			'reload'  => true,
		);
		if ( '' !== $generated ) {
			// Affiché une seule fois — jamais stocké ni journalisé en clair.
			$data['generated_password'] = $generated;
		}
		wp_send_json_success( $data );
	}

	/**
	 * AJAX : supprime un confirmateur (contenu réattribué au gérant courant).
	 *
	 * 🚨 42.7 — `wp_delete_user()` ne réattribue que `post_author` (contenu
	 * WordPress standard) : il ignore totalement `_aod_assigned_confirmer`,
	 * notre méta métier. Sans le passage ci-dessous, les commandes de ce
	 * confirmateur restent marquées assignées à un ID qui n'existe plus —
	 * invisibles du filtre « Non assignées » (qui teste une ABSENCE de méta,
	 * pas sa validité) et de tout confirmateur, silencieusement perdues pour
	 * tout le monde sauf le gérant, qui les croit prises en charge.
	 */
	public function ajax_delete_member() {
		$member_id = isset( $_POST['member_id'] ) ? absint( $_POST['member_id'] ) : 0;
		$target    = $this->guard( $member_id );
		if ( ! $target ) {
			wp_send_json_error( array( 'message' => __( 'Compte introuvable ou protégé.', 'aod-client-dashboard' ) ), 400 );
		}
		if ( class_exists( 'AOD_CD_Section_Orders' ) ) {
			AOD_CD_Section_Orders::instance()->unassign_confirmer( $target->ID );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		if ( ! wp_delete_user( $target->ID, get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'La suppression a échoué.', 'aod-client-dashboard' ) ), 500 );
		}
		wp_send_json_success( array(
			'message' => __( 'Confirmateur supprimé.', 'aod-client-dashboard' ),
			'reload'  => true,
		) );
	}
}
