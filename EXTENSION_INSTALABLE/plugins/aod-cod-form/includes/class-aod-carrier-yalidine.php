<?php
/**
 * Livreur Yalidine.
 *
 * API : https://api.yalidine.app/v1/ — en-têtes X-API-ID / X-API-TOKEN, JSON.
 * Création : POST /parcels/ (tableau) → réponse indexée par order_id
 * (success, tracking, import_id, label PDF). Wilaya/commune par NOM.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_Carrier_Yalidine extends AOD_Carrier {

	const API_BASE = 'https://api.yalidine.app/v1/';

	public function id() {
		return 'yalidine';
	}

	public function label() {
		return 'Yalidine';
	}

	public function brand_color() {
		return '#e63329';
	}

	public function initials() {
		return 'YA';
	}

	public function supports_stopdesk() {
		return true;
	}

	/** Base API (surchargeable pour les clones type Yalitec). */
	protected function api_base() {
		return self::API_BASE;
	}

	protected function defaults() {
		return array(
			'api_id'           => '',
			'api_token'        => '',
			'from_wilaya_id'   => '',
			'from_wilaya_name' => '',
			'weight'           => 1,
			'length'           => 20,
			'width'            => 20,
			'height'           => 20,
			'freeshipping'     => 1,
			'do_insurance'     => 0,
		);
	}

	public function is_configured() {
		$s = $this->settings();
		return '' !== $s['api_id'] && '' !== $s['api_token'];
	}

	public function render_settings_fields() {
		$s = $this->settings();
		$p = $this->id();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'API ID', 'aod-cod-form' ); ?></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $p ); ?>[api_id]" value="<?php echo esc_attr( $s['api_id'] ); ?>" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'API Token', 'aod-cod-form' ); ?></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $p ); ?>[api_token]" value="<?php echo esc_attr( $s['api_token'] ); ?>" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Wilaya d’expédition', 'aod-cod-form' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $p ); ?>[from_wilaya_id]">
						<option value=""><?php esc_html_e( '— Choisir —', 'aod-cod-form' ); ?></option>
						<?php foreach ( AOD_COD_Data::places() as $w ) : ?>
							<option value="<?php echo esc_attr( $w['code'] ); ?>" <?php selected( (int) $s['from_wilaya_id'], (int) $w['code'] ); ?>><?php echo esc_html( sprintf( '%02d - %s', $w['code'], $w['name'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Obligatoire pour Yalidine.', 'aod-cod-form' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Poids / dimensions', 'aod-cod-form' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Poids (kg)', 'aod-cod-form' ); ?> <input type="number" min="1" step="1" name="<?php echo esc_attr( $p ); ?>[weight]" value="<?php echo esc_attr( $s['weight'] ); ?>" style="width:80px"></label>
					<label>L <input type="number" min="0" name="<?php echo esc_attr( $p ); ?>[length]" value="<?php echo esc_attr( $s['length'] ); ?>" style="width:70px"></label>
					<label>l <input type="number" min="0" name="<?php echo esc_attr( $p ); ?>[width]" value="<?php echo esc_attr( $s['width'] ); ?>" style="width:70px"></label>
					<label>H <input type="number" min="0" name="<?php echo esc_attr( $p ); ?>[height]" value="<?php echo esc_attr( $s['height'] ); ?>" style="width:70px"></label>
					<span class="description">(cm)</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'aod-cod-form' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[freeshipping]" value="1" <?php checked( 1, (int) $s['freeshipping'] ); ?>> <?php esc_html_e( 'Livraison « gratuite » côté Yalidine (le client paie pile le total)', 'aod-cod-form' ); ?></label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[do_insurance]" value="1" <?php checked( 1, (int) $s['do_insurance'] ); ?>> <?php esc_html_e( 'Assurer le colis', 'aod-cod-form' ); ?></label>
				</td>
			</tr>
		</table>
		<?php
	}

	public function sanitize_settings( $input ) {
		$wid = isset( $input['from_wilaya_id'] ) ? absint( $input['from_wilaya_id'] ) : 0;
		return array(
			'api_id'           => isset( $input['api_id'] ) ? sanitize_text_field( $input['api_id'] ) : '',
			'api_token'        => isset( $input['api_token'] ) ? sanitize_text_field( $input['api_token'] ) : '',
			'from_wilaya_id'   => $wid ? $wid : '',
			'from_wilaya_name' => $wid ? AOD_COD_Data::wilaya_name( $wid ) : '',
			'weight'           => isset( $input['weight'] ) ? max( 1, absint( $input['weight'] ) ) : 1,
			'length'           => isset( $input['length'] ) ? max( 0, absint( $input['length'] ) ) : 0,
			'width'            => isset( $input['width'] ) ? max( 0, absint( $input['width'] ) ) : 0,
			'height'           => isset( $input['height'] ) ? max( 0, absint( $input['height'] ) ) : 0,
			'freeshipping'     => empty( $input['freeshipping'] ) ? 0 : 1,
			'do_insurance'     => empty( $input['do_insurance'] ) ? 0 : 1,
		);
	}

	/**
	 * Requête authentifiée Yalidine (JSON).
	 *
	 * @param string   $method   GET/POST…
	 * @param string   $endpoint Chemin relatif à l'API base (ex. 'fees/?…').
	 * @param mixed    $body     Corps JSON (ou null).
	 * @param int|null $timeout  Timeout HTTP en secondes (repli 30 s de remote() si null).
	 * @return array|WP_Error
	 */
	protected function request( $method, $endpoint, $body = null, $timeout = null ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_yalidine_no_creds', __( 'Clés API Yalidine manquantes.', 'aod-cod-form' ) );
		}
		$s    = $this->settings();
		$args = array(
			'headers' => array(
				'X-API-ID'     => $s['api_id'],
				'X-API-TOKEN'  => $s['api_token'],
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);
		if ( null !== $timeout ) {
			$args['timeout'] = (int) $timeout;
		}
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		return $this->remote( $method, $this->api_base() . $endpoint, $args );
	}

	public function supports_fees() {
		return true;
	}

	/**
	 * Tarifs de livraison Yalidine (frais Domicile/Stop-desk) par wilaya.
	 *
	 * Endpoint `fees` : `GET fees/?from_wilaya_id={départ}&to_wilaya_id={W}` — une
	 * requête par wilaya de destination (1..58), réutilisant les clés API et la
	 * wilaya de départ déjà réglées. La réponse expose les frais **par commune**
	 * (`per_commune[*].express_home` / `express_desk`) : on réduit à une valeur
	 * représentative par wilaya (cf. `fees_from_response()`).
	 *
	 * Lecture seule (n'écrit jamais `aod_cod_delivery_prices`) ; résultat mis en
	 * cache 12 h (transient) sauf `$force`. Échec du **premier** appel (clés
	 * refusées, API injoignable) → WP_Error immédiat ; échec isolé d'une wilaya →
	 * wilaya ignorée ; aucune wilaya lue → WP_Error.
	 *
	 * @param bool $force Ignorer le cache et réinterroger l'API.
	 * @return array|WP_Error [ (int) wilaya_id => [ 'home'=>int, 'desk'=>int ] ].
	 */
	public function get_fees( $force = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_yalidine_no_creds', __( 'Clés API Yalidine manquantes.', 'aod-cod-form' ) );
		}
		$s    = $this->settings();
		$from = (int) $s['from_wilaya_id'];
		if ( $from <= 0 ) {
			return new WP_Error( 'aod_yalidine_no_from', __( 'Wilaya d’expédition non définie (Réglages Yalidine).', 'aod-cod-form' ) );
		}

		$cache_key = 'aod_' . $this->id() . '_fees_' . $from;
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$fees = array();
		for ( $w = 1; $w <= 58; $w++ ) {
			$res = $this->request( 'GET', 'fees/?from_wilaya_id=' . $from . '&to_wilaya_id=' . $w, null, 8 );
			if ( is_wp_error( $res ) ) {
				// Le premier échec (clés/réseau) touche tout : on abandonne proprement.
				if ( empty( $fees ) ) {
					return $res;
				}
				// Échec isolé plus loin : on saute cette wilaya.
				continue;
			}
			$row = $this->fees_from_response( $res );
			if ( null !== $row ) {
				$fees[ $w ] = $row;
			}
		}

		if ( empty( $fees ) ) {
			return new WP_Error( 'aod_yalidine_fees_empty', __( 'Aucun tarif n’a pu être récupéré chez Yalidine.', 'aod-cod-form' ) );
		}

		set_transient( $cache_key, $fees, 12 * HOUR_IN_SECONDS );
		return $fees;
	}

	/**
	 * Réduit une réponse `fees` (frais par commune) à un tarif représentatif par
	 * wilaya : le **mode** (valeur entière non nulle la plus fréquente) de
	 * `express_home` / `express_desk`, repli sur la première valeur non nulle.
	 * `desk` = 0 si aucune commune de la wilaya n'a de bureau. Défensif : lit
	 * `per_commune`, à défaut un tableau plat `data`/liste.
	 *
	 * Isolé (sans réseau) pour être testable via `wp eval` sur un JSON simulé.
	 *
	 * @param array $res Réponse décodée de l'endpoint fees.
	 * @return array|null [ 'home'=>int, 'desk'=>int ] ou null si rien d'exploitable.
	 */
	protected function fees_from_response( $res ) {
		if ( ! is_array( $res ) ) {
			return null;
		}
		if ( isset( $res['per_commune'] ) && is_array( $res['per_commune'] ) ) {
			$rows = $res['per_commune'];
		} elseif ( isset( $res['data'] ) && is_array( $res['data'] ) ) {
			$rows = $res['data'];
		} else {
			$rows = $res;
		}

		$home = array();
		$desk = array();
		foreach ( (array) $rows as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$h = isset( $c['express_home'] ) ? (int) $c['express_home'] : 0;
			$d = isset( $c['express_desk'] ) ? (int) $c['express_desk'] : 0;
			if ( $h > 0 ) {
				$home[] = $h;
			}
			if ( $d > 0 ) {
				$desk[] = $d;
			}
		}

		if ( empty( $home ) && empty( $desk ) ) {
			return null;
		}
		return array(
			'home' => $this->mode_int( $home ),
			'desk' => $this->mode_int( $desk ),
		);
	}

	/**
	 * Valeur entière la plus fréquente d'une liste (repli : première valeur ; 0 si vide).
	 *
	 * @param int[] $values
	 * @return int
	 */
	protected function mode_int( $values ) {
		$values = array_values( array_filter( array_map( 'intval', (array) $values ), function ( $v ) {
			return $v > 0;
		} ) );
		if ( empty( $values ) ) {
			return 0;
		}
		$counts = array_count_values( $values );
		arsort( $counts );
		$best = key( $counts );
		return (int) $best;
	}

	public function get_centers( $wilaya_code ) {
		$wilaya_code = absint( $wilaya_code );
		$key         = 'aod_yalidine_centers_' . $wilaya_code;
		$cached      = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = $this->request( 'GET', 'centers/?wilaya_id=' . $wilaya_code );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$list = array();
		$rows = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
		foreach ( $rows as $c ) {
			$list[] = array(
				'id'   => isset( $c['center_id'] ) ? (int) $c['center_id'] : 0,
				'name' => isset( $c['name'] ) ? $c['name'] : ( isset( $c['commune_name'] ) ? $c['commune_name'] : '' ),
			);
		}
		set_transient( $key, $list, DAY_IN_SECONDS );
		return $list;
	}

	public function build_payload( $order ) {
		$s = $this->settings();
		list( $firstname, $familyname ) = $this->split_name( $this->full_name( $order ) );
		$is_desk = $this->is_stopdesk_order( $order );
		$total   = $this->order_total( $order );

		$payload = array(
			'order_id'         => (string) $order->get_order_number(),
			'from_wilaya_name' => $s['from_wilaya_name'],
			'firstname'        => $firstname,
			'familyname'       => $familyname,
			'contact_phone'    => $order->get_billing_phone(),
			'address'          => $order->get_billing_address_1(),
			'to_commune_name'  => $order->get_meta( '_aod_commune' ) ? $order->get_meta( '_aod_commune' ) : $order->get_billing_city(),
			'to_wilaya_name'   => $order->get_meta( '_aod_wilaya_name' ),
			'product_list'     => $this->product_list( $order ),
			'price'            => $total,
			'do_insurance'     => (bool) $s['do_insurance'],
			'declared_value'   => $total,
			'length'           => (int) $s['length'],
			'width'            => (int) $s['width'],
			'height'           => (int) $s['height'],
			'weight'           => (int) $s['weight'],
			'freeshipping'     => (bool) $s['freeshipping'],
			'is_stopdesk'      => $is_desk,
			'has_exchange'     => false,
		);
		if ( $is_desk ) {
			$payload['stopdesk_id'] = (int) $order->get_meta( self::META_STOPDESK );
		}
		return $payload;
	}

	public function create_parcel( $order ) {
		$s = $this->settings();
		if ( '' === $s['from_wilaya_name'] ) {
			return new WP_Error( 'aod_yalidine_no_from', __( 'Wilaya d’expédition non définie (Réglages Yalidine).', 'aod-cod-form' ) );
		}
		if ( $this->is_stopdesk_order( $order ) && ! (int) $order->get_meta( self::META_STOPDESK ) ) {
			return new WP_Error( 'aod_yalidine_no_desk', __( 'Stop-desk : choisissez un centre avant l’envoi.', 'aod-cod-form' ) );
		}

		$payload = $this->build_payload( $order );
		$res     = $this->request( 'POST', 'parcels/', array( $payload ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$entry = isset( $res[ $payload['order_id'] ] ) ? $res[ $payload['order_id'] ] : reset( $res );
		if ( ! is_array( $entry ) || empty( $entry['success'] ) ) {
			$msg = is_array( $entry ) && ! empty( $entry['message'] ) ? $entry['message'] : __( 'Yalidine a refusé le colis.', 'aod-cod-form' );
			return new WP_Error( 'aod_yalidine_rejected', $msg, $entry );
		}
		return array(
			'tracking'  => isset( $entry['tracking'] ) ? $entry['tracking'] : '',
			'label'     => isset( $entry['label'] ) ? $entry['label'] : '',
			'import_id' => isset( $entry['import_id'] ) ? $entry['import_id'] : '',
		);
	}

	/**
	 * Vérifie les clés API via un appel léger en lecture seule.
	 *
	 * GET /wilayas/ exige une authentification valide (X-API-ID / X-API-TOKEN) :
	 * une réponse 2xx confirme que les clés sont acceptées. Hérité tel quel par
	 * Yalitec (seule la base API change).
	 *
	 * @return array|WP_Error
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return parent::test_connection();
		}
		$res = $this->request( 'GET', 'wilayas/?page=1' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'live' => true, 'message' => '' );
	}

	public function supports_tracking() {
		return true;
	}

	/**
	 * Historique de suivi Yalidine.
	 *
	 * GET /histories/?tracking=<tracking> renvoie { has_more, data: [ {…} ] } où
	 * chaque événement porte `date_status`, `status` (libellé FR), et la
	 * localisation (`center_name`/`commune_name`/`wilaya_name`).
	 *
	 * @param string $tracking
	 * @return array|WP_Error
	 */
	public function get_tracking( $tracking ) {
		$tracking = trim( (string) $tracking );
		if ( '' === $tracking ) {
			return new WP_Error( 'aod_yalidine_no_tracking', __( 'Numéro de suivi manquant.', 'aod-cod-form' ) );
		}
		$res = $this->request( 'GET', 'histories/?tracking=' . rawurlencode( $tracking ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$rows    = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
		$history = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$label = $this->first_str( $r, array( 'status' ) );
			$date  = $this->first_str( $r, array( 'date_status', 'date' ) );
			$loc   = $this->first_str( $r, array( 'center_name', 'commune_name', 'wilaya_name' ) );
			$history[] = array(
				'ts'       => $date ? (int) strtotime( $date ) : 0,
				'date'     => $date,
				'status'   => $this->guess_status( $label ),
				'label'    => $label,
				'location' => $loc,
			);
		}
		return $this->build_tracking( $history );
	}
}
