<?php
/**
 * Livreur Maystro Delivery.
 *
 * API : https://backend.maystro-delivery.com/api/ — auth en-tête « Authorization: Token <clé> ».
 * Création : POST stores/orders/. Particularité : la commune est un IDENTIFIANT
 * NUMÉRIQUE propre à Maystro (pas le nom). On le résout via base/commune/ (mise en
 * cache) en faisant correspondre le nom de commune de la commande.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_Carrier_Maystro extends AOD_Carrier {

	const API_BASE = 'https://backend.maystro-delivery.com/api/';

	public function id() {
		return 'maystro';
	}

	public function label() {
		return 'Maystro Delivery';
	}

	public function brand_color() {
		return '#1e3a8a';
	}

	public function initials() {
		return 'MS';
	}

	public function supports_stopdesk() {
		return false;
	}

	/** 47.3 — `note_to_driver` dans `build_payload()`. */
	public function supports_driver_note() {
		return true;
	}

	protected function defaults() {
		return array(
			'token'   => '',
			'express' => 0,
		);
	}

	public function is_configured() {
		return '' !== $this->settings()['token'];
	}

	public function render_settings_fields() {
		$s = $this->settings();
		$p = $this->id();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'API Token (clé du magasin)', 'aod-cod-form' ); ?></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $p ); ?>[token]" value="<?php echo esc_attr( $s['token'] ); ?>" autocomplete="off">
				<p class="description"><?php esc_html_e( 'Espace Maystro → Paramètres → API (Store Key).', 'aod-cod-form' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'aod-cod-form' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[express]" value="1" <?php checked( 1, (int) $s['express'] ); ?>> <?php esc_html_e( 'Livraison express (tarif différent)', 'aod-cod-form' ); ?></label>
				</td>
			</tr>
		</table>
		<?php
	}

	public function sanitize_settings( $input ) {
		return array(
			'token'   => isset( $input['token'] ) ? sanitize_text_field( $input['token'] ) : '',
			'express' => empty( $input['express'] ) ? 0 : 1,
		);
	}

	/** En-têtes authentifiés Maystro. */
	protected function headers() {
		return array(
			'Authorization' => 'Token ' . $this->settings()['token'],
			'Accept'        => 'application/json',
		);
	}

	/** Normalise un nom de commune pour la comparaison (sans accents/espaces/casse). */
	protected function norm( $str ) {
		$str = remove_accents( (string) $str );
		$str = strtolower( $str );
		return preg_replace( '/[^a-z0-9]/', '', $str );
	}

	/**
	 * Résout l'ID numérique Maystro d'une commune à partir de son nom + wilaya.
	 *
	 * @param int    $wilaya_code Code wilaya (1-58).
	 * @param string $commune     Nom de commune.
	 * @return int|WP_Error
	 */
	protected function resolve_commune_id( $wilaya_code, $commune ) {
		$wilaya_code = absint( $wilaya_code );
		$key         = 'aod_maystro_communes_' . $wilaya_code;
		$list        = get_transient( $key );

		if ( ! is_array( $list ) ) {
			$res = $this->remote( 'GET', self::API_BASE . 'base/commune/?wilaya=' . $wilaya_code, array(
				'headers' => $this->headers(),
			) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			// La réponse peut être paginée (results) ou un tableau simple.
			$rows = array();
			if ( isset( $res['results'] ) && is_array( $res['results'] ) ) {
				$rows = $res['results'];
			} elseif ( is_array( $res ) ) {
				$rows = $res;
			}
			$list = array();
			foreach ( $rows as $row ) {
				if ( isset( $row['id'] ) && isset( $row['name'] ) ) {
					$list[] = array( 'id' => (int) $row['id'], 'name' => (string) $row['name'] );
				}
			}
			set_transient( $key, $list, WEEK_IN_SECONDS );
		}

		$target = $this->norm( $commune );
		foreach ( $list as $row ) {
			if ( $this->norm( $row['name'] ) === $target ) {
				return (int) $row['id'];
			}
		}
		return new WP_Error( 'aod_maystro_no_commune', sprintf(
			/* translators: %s: nom de la commune */
			__( 'Commune « %s » introuvable chez Maystro. Vérifiez l’orthographe.', 'aod-cod-form' ),
			$commune
		) );
	}

	/** Téléphone réduit aux chiffres (Maystro attend 9-10 chiffres). */
	protected function clean_phone( $phone ) {
		return preg_replace( '/\D+/', '', (string) $phone );
	}

	/** Sous-total produits (total commande hors livraison), arrondi à l'entier. */
	protected function product_price( $order ) {
		$subtotal = (float) $order->get_total() - (float) $order->get_shipping_total();
		return (int) round( max( 0, $subtotal ) );
	}

	public function build_payload( $order ) {
		$s           = $this->settings();
		$is_desk     = $this->is_stopdesk_order( $order );
		$wilaya_code = (int) $order->get_meta( '_aod_wilaya_code' );
		$commune     = $order->get_meta( '_aod_commune' ) ? $order->get_meta( '_aod_commune' ) : $order->get_billing_city();

		// Description produit unique (évite les rejets « description déjà utilisée »).
		$desc  = '#' . $order->get_order_number() . ' — ' . $this->product_list( $order );
		$qty   = 0;
		foreach ( $order->get_items() as $item ) {
			$qty += (int) $item->get_quantity();
		}

		return array(
			'wilaya'            => $wilaya_code,
			'commune'           => 0, // Remplacé dans create_parcel après résolution.
			'destination_text'  => $order->get_billing_address_1(),
			'customer_phone'    => $this->clean_phone( $order->get_billing_phone() ),
			'customer_name'     => $this->full_name( $order ),
			'product_price'     => $this->product_price( $order ),
			'delivery_type'     => $is_desk ? 1 : 0,
			'express'           => (bool) $s['express'],
			'note_to_driver'    => $this->driver_note( $order ), // 47.3 — partait vide depuis toujours.
			'products'          => array(
				array(
					'logistical_description' => $desc,
					'quantity'               => max( 1, $qty ),
				),
			),
			'source'            => 4,
			'external_order_id' => (string) $order->get_order_number(),
		);
	}

	public function create_parcel( $order ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_maystro_no_creds', __( 'Token API Maystro manquant.', 'aod-cod-form' ) );
		}
		$wilaya_code = (int) $order->get_meta( '_aod_wilaya_code' );
		if ( ! $wilaya_code ) {
			return new WP_Error( 'aod_maystro_no_wilaya', __( 'Wilaya de la commande manquante.', 'aod-cod-form' ) );
		}

		$commune_name = $order->get_meta( '_aod_commune' ) ? $order->get_meta( '_aod_commune' ) : $order->get_billing_city();
		$commune_id   = $this->resolve_commune_id( $wilaya_code, $commune_name );
		if ( is_wp_error( $commune_id ) ) {
			return $commune_id;
		}

		$payload            = $this->build_payload( $order );
		$payload['commune'] = (int) $commune_id;

		$res = $this->remote( 'POST', self::API_BASE . 'stores/orders/', array(
			'headers' => $this->headers(),
			'body'    => $payload,
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		// Le numéro de suivi peut être à la racine ou sous « data ».
		$data     = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : $res;
		$tracking = '';
		foreach ( array( 'display_id', 'tracking', 'id', 'order_id' ) as $field ) {
			if ( ! empty( $data[ $field ] ) ) {
				$tracking = (string) $data[ $field ];
				break;
			}
		}
		if ( '' === $tracking ) {
			$msg = ! empty( $res['message'] ) ? $res['message'] : __( 'Maystro n’a pas renvoyé de numéro de suivi.', 'aod-cod-form' );
			return new WP_Error( 'aod_maystro_rejected', $msg, $res );
		}

		return array(
			'tracking'  => $tracking,
			'label'     => '',
			'import_id' => '',
		);
	}

	/**
	 * Vérifie le token via un appel léger en lecture seule.
	 *
	 * GET base/commune/ (en-tête Authorization: Token …) renvoie 2xx si le token
	 * du magasin est accepté par Maystro ; un token invalide remonte une WP_Error.
	 *
	 * @return array|WP_Error
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return parent::test_connection();
		}
		$res = $this->remote( 'GET', self::API_BASE . 'base/commune/?wilaya=16', array(
			'headers' => $this->headers(),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'live' => true, 'message' => '' );
	}

	public function supports_tracking() {
		return true;
	}

	/**
	 * Suivi d'un colis Maystro.
	 *
	 * Maystro n'expose pas d'endpoint de suivi public stable : on récupère la fiche
	 * de la commande (via `display_id`, sinon par id) et on lit soit un historique
	 * de statuts, soit le libellé de statut courant. On privilégie les libellés
	 * texte (mappés par mots-clés) ; un statut purement numérique reste « inconnu »
	 * avec sa valeur brute, plutôt que d'inventer une correspondance.
	 *
	 * @param string $tracking
	 * @return array|WP_Error
	 */
	public function get_tracking( $tracking ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_maystro_no_creds', __( 'Token API Maystro manquant.', 'aod-cod-form' ) );
		}
		$tracking = trim( (string) $tracking );
		if ( '' === $tracking ) {
			return new WP_Error( 'aod_maystro_no_tracking', __( 'Numéro de suivi manquant.', 'aod-cod-form' ) );
		}

		// 1) Recherche par identifiant externe/affiché, puis repli sur l'accès direct.
		$res = $this->remote( 'GET', self::API_BASE . 'stores/orders/?display_id=' . rawurlencode( $tracking ), array( 'headers' => $this->headers() ) );
		$entry = array();
		if ( ! is_wp_error( $res ) ) {
			if ( isset( $res['results'] ) && is_array( $res['results'] ) && ! empty( $res['results'] ) ) {
				$entry = is_array( $res['results'][0] ) ? $res['results'][0] : array();
			} else {
				$entry = $this->extract_entry( $res, $tracking );
			}
		}
		if ( empty( $entry ) ) {
			$res2 = $this->remote( 'GET', self::API_BASE . 'stores/orders/' . rawurlencode( $tracking ) . '/', array( 'headers' => $this->headers() ) );
			if ( is_wp_error( $res2 ) ) {
				// Remonte la première erreur si les deux tentatives échouent.
				return is_wp_error( $res ) ? $res : $res2;
			}
			$entry = $this->extract_entry( $res2, $tracking );
		}

		$events = array();
		foreach ( array( 'status_logs', 'order_status_logs', 'status_history', 'history', 'tracking' ) as $k ) {
			if ( isset( $entry[ $k ] ) && is_array( $entry[ $k ] ) ) {
				$events = $entry[ $k ];
				break;
			}
		}

		$history = array();
		foreach ( $events as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			$label = $this->first_str( $e, array( 'status_display', 'status_name', 'status', 'label', 'event' ) );
			$date  = $this->first_str( $e, array( 'created_at', 'date', 'datetime', 'time' ) );
			$history[] = array(
				'ts'       => $date ? (int) strtotime( $date ) : 0,
				'date'     => $date,
				'status'   => $this->guess_status( $label ),
				'label'    => $label,
				'location' => '',
			);
		}

		// Repli : statut courant (texte de préférence).
		if ( empty( $history ) ) {
			$label = $this->first_str( $entry, array( 'status_display', 'status_name', 'wilaya_status', 'situation' ) );
			if ( '' === $label && isset( $entry['status'] ) && is_scalar( $entry['status'] ) ) {
				/* translators: %s : code de statut numérique renvoyé par Maystro */
				$label = sprintf( __( 'Statut %s', 'aod-cod-form' ), (string) $entry['status'] );
			}
			if ( '' !== $label ) {
				$history[] = array( 'ts' => 0, 'date' => '', 'status' => $this->guess_status( $label ), 'label' => $label, 'location' => '' );
			}
		}

		return $this->build_tracking( $history );
	}
}
