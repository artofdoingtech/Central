<?php
/**
 * Livreur Ecotrack (plateforme multi-transporteurs).
 *
 * API : https://{domaine}/api/v1/ — auth par api_token (corps + en-tête Bearer).
 * Le domaine dépend du transporteur (ex. monorg.ecotrack.dz). Création :
 * POST create/order, puis valid/order. Wilaya par CODE (1-58), commune par NOM.
 *
 * Cette classe est aussi utilisée pour tous les livreurs « white-label » EcoTrack
 * (Rex, Golivri, DHD, Speed, Rocket…). Ils partagent exactement la même API ;
 * seule l'URL (domaine) et la marque changent. On les instancie avec une config
 * (id/label/domaine fixe/couleur) au lieu de créer une classe par livreur.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_Carrier_Ecotrack extends AOD_Carrier {

	/** @var array Config d'instance : id, label, domain (fixe), brand, initials. */
	protected $config;

	/**
	 * @param array $config {
	 *     @type string $id       Identifiant machine (défaut 'ecotrack').
	 *     @type string $label    Nom affiché (défaut 'EcoTrack').
	 *     @type string $domain   Domaine fixe ; si défini, le champ domaine est masqué.
	 *     @type string $brand    Couleur de marque (#RRGGBB).
	 *     @type string $initials Initiales de la pastille.
	 *     @type string $emblem   Emblème au catalogue `AOD_Carrier_Emblem`, '' = monogramme.
	 * }
	 */
	public function __construct( $config = array() ) {
		$this->config = wp_parse_args( $config, array(
			'id'       => 'ecotrack',
			'label'    => 'EcoTrack',
			'domain'   => '',
			'brand'    => '#16a34a',
			'initials' => '',
			// Le défaut est l'emblème d'EcoTrack LUI-MÊME, parce que la seule
			// instance qui se construit sans config est l'EcoTrack générique.
			// Les 23 white-labels déclarent tous le leur — y compris '' quand
			// leur nom ne porte pas d'image. Un white-label ajouté sans clé
			// `emblem` arborerait donc la trace d'EcoTrack : c'est ce que
			// `test-carrier-emblemes.php` R2 refuse.
			'emblem'   => 'route',
		) );
	}

	public function id() {
		return $this->config['id'];
	}

	public function label() {
		return $this->config['label'];
	}

	public function brand_color() {
		return $this->config['brand'];
	}

	public function initials() {
		return '' !== $this->config['initials'] ? $this->config['initials'] : parent::initials();
	}

	public function emblem() {
		return $this->config['emblem'];
	}

	public function supports_stopdesk() {
		return true;
	}

	/** 47.3 — `remarque` dans `build_payload()`. Vaut aussi pour les 23 white-labels. */
	public function supports_driver_note() {
		return true;
	}

	/** Domaine fixe imposé par le white-label (vide pour l'EcoTrack générique). */
	protected function fixed_domain() {
		return $this->config['domain'];
	}

	protected function defaults() {
		return array(
			'domain'        => $this->fixed_domain(),
			'api_token'     => '',
			'weight'        => 1,
			'fragile'       => 0,
			'auto_validate' => 1,
			// Plafond COD courant des transporteurs EcoTrack/CourierDZ (DZD).
			// 0 = pas de limite. Ajustable selon le contrat du client.
			'cod_max'       => 150000,
		);
	}

	/** Plafond COD configuré pour cette instance EcoTrack (0 = aucun). */
	public function cod_limit() {
		return (int) $this->settings()['cod_max'];
	}

	/** Domaine effectif : le domaine fixe s'il existe, sinon celui saisi. */
	protected function effective_domain() {
		$fixed = $this->fixed_domain();
		if ( '' !== $fixed ) {
			return $fixed;
		}
		return (string) $this->settings()['domain'];
	}

	public function is_configured() {
		return '' !== $this->settings()['api_token'] && '' !== $this->effective_domain();
	}

	/** Base API construite à partir du domaine effectif. */
	protected function api_base() {
		$domain = preg_replace( '#^https?://#', '', trim( $this->effective_domain() ) );
		$domain = rtrim( $domain, '/' );
		return 'https://' . $domain . '/api/v1/';
	}

	public function render_settings_fields() {
		$s     = $this->settings();
		$p     = $this->id();
		$fixed = $this->fixed_domain();
		?>
		<table class="form-table" role="presentation">
			<?php if ( '' === $fixed ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Domaine EcoTrack', 'aod-cod-form' ); ?></th>
					<td><input type="text" class="regular-text" name="<?php echo esc_attr( $p ); ?>[domain]" value="<?php echo esc_attr( $s['domain'] ); ?>" placeholder="monorg.ecotrack.dz" autocomplete="off">
					<p class="description"><?php esc_html_e( 'Le sous-domaine fourni par votre transporteur EcoTrack (sans https://).', 'aod-cod-form' ); ?></p></td>
				</tr>
			<?php else : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Plateforme', 'aod-cod-form' ); ?></th>
					<td><code><?php echo esc_html( $fixed ); ?></code>
					<p class="description"><?php esc_html_e( 'Connecté via EcoTrack. Il vous suffit de coller votre token API.', 'aod-cod-form' ); ?></p></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'API Token', 'aod-cod-form' ); ?></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $p ); ?>[api_token]" value="<?php echo esc_attr( $s['api_token'] ); ?>" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Poids par défaut (kg)', 'aod-cod-form' ); ?></th>
				<td><input type="number" min="1" step="1" name="<?php echo esc_attr( $p ); ?>[weight]" value="<?php echo esc_attr( $s['weight'] ); ?>" style="width:80px"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Plafond COD (DZD)', 'aod-cod-form' ); ?></th>
				<td><input type="number" min="0" step="1000" name="<?php echo esc_attr( $p ); ?>[cod_max]" value="<?php echo esc_attr( $s['cod_max'] ); ?>" style="width:120px">
				<p class="description"><?php esc_html_e( 'Montant maximum à encaisser accepté par le transporteur. Au-delà, l’envoi est bloqué avec un message clair (0 = aucune limite).', 'aod-cod-form' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'aod-cod-form' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[fragile]" value="1" <?php checked( 1, (int) $s['fragile'] ); ?>> <?php esc_html_e( 'Colis fragile par défaut', 'aod-cod-form' ); ?></label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[auto_validate]" value="1" <?php checked( 1, (int) $s['auto_validate'] ); ?>> <?php esc_html_e( 'Valider automatiquement le colis après création', 'aod-cod-form' ); ?></label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Nom d'hôte public bien formé, ou chaîne vide. JAMAIS une cible interne.
	 *
	 * 🔒 Le domaine saisi ici devient l'hôte appelé par `api_base()`, donc la
	 * destination des bordereaux — nom, téléphone, adresse et montant COD de chaque
	 * commande confirmée. Sans contrainte, ce champ est deux choses à la fois : un
	 * canal d'exfiltration, et un SSRF depuis le serveur du client vers son propre
	 * réseau (`169.254.169.254`, `127.0.0.1`, `10.0.0.0/8`…).
	 *
	 * On refuse donc tout ce qui n'est pas un nom de domaine : littéraux IP (v4
	 * comme v6), hôtes sans point (`localhost`), et toute forme portant un port,
	 * un chemin, des identifiants ou une requête — autant de moyens de faire dire
	 * à une URL autre chose que ce qu'elle a l'air de dire.
	 *
	 * ⚠️ Ce n'est PAS une garantie que l'hôte est légitime : un attaquant peut
	 * pointer un domaine public qu'il contrôle. La barrière qui compte contre ça
	 * est la capability (`can_manage_carriers()`, 31.1) — celle-ci borne les
	 * dégâts et ferme le volet réseau interne.
	 *
	 * @param string $raw Saisie brute.
	 * @return string Nom d'hôte en minuscules, ou '' si refusé.
	 */
	protected function sanitize_domain( $raw ) {
		$domain = strtolower( trim( (string) $raw ) );
		$domain = preg_replace( '#^https?://#', '', $domain );
		$domain = rtrim( $domain, '/' );

		// Tout ce qui suit le nom d'hôte est écarté AVANT validation : un `@` ou un
		// `:` accepté ici change la cible réelle de l'URL construite.
		if ( '' === $domain || preg_match( '#[/@:?\#\s]#', $domain ) ) {
			return '';
		}
		// Un littéral IP n'est jamais un domaine transporteur — et c'est la forme
		// qu'emprunte un SSRF vers le réseau interne.
		if ( filter_var( $domain, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		// Nom d'hôte : labels alphanumériques (tiret interne), au moins un point,
		// TLD alphabétique. Écarte `localhost` et les noms de service internes.
		if ( ! preg_match( '#^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$#', $domain ) ) {
			return '';
		}
		return $domain;
	}

	public function sanitize_settings( $input ) {
		$fixed = $this->fixed_domain();
		if ( '' !== $fixed ) {
			$domain = $fixed;
		} else {
			$domain = $this->sanitize_domain( isset( $input['domain'] ) ? $input['domain'] : '' );
		}
		return array(
			'domain'        => $domain,
			'api_token'     => isset( $input['api_token'] ) ? sanitize_text_field( $input['api_token'] ) : '',
			'weight'        => isset( $input['weight'] ) ? max( 1, absint( $input['weight'] ) ) : 1,
			'fragile'       => empty( $input['fragile'] ) ? 0 : 1,
			'auto_validate' => empty( $input['auto_validate'] ) ? 0 : 1,
			'cod_max'       => isset( $input['cod_max'] ) ? absint( $input['cod_max'] ) : 150000,
		);
	}

	/**
	 * Liste des communes EcoTrack d'une wilaya (cache 12 h).
	 *
	 * EcoTrack impose l'orthographe EXACTE de la commune (ex. « Tichy », pas
	 * « Tichi ») et indique par commune si un point relais (stop-desk) existe.
	 * On met le résultat en cache par domaine + wilaya pour éviter un appel
	 * réseau à chaque envoi.
	 *
	 * @param int $wilaya_code 1-58.
	 * @return array Liste [ [ 'nom' => string, 'has_stop_desk' => bool ], … ].
	 */
	protected function communes( $wilaya_code ) {
		$wilaya_code = (int) $wilaya_code;
		if ( $wilaya_code < 1 || ! $this->is_configured() ) {
			return array();
		}
		$key    = 'aod_eco_communes_' . md5( $this->effective_domain() ) . '_' . $wilaya_code;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$s   = $this->settings();
		$url = add_query_arg(
			array( 'api_token' => $s['api_token'], 'wilaya_id' => $wilaya_code ),
			$this->api_base() . 'get/communes'
		);
		$res = $this->remote( 'GET', $url, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $s['api_token'], 'Accept' => 'application/json' ),
		) );
		if ( is_wp_error( $res ) || ! is_array( $res ) ) {
			return array();
		}

		// La réponse est une liste d'objets ; on ne garde que la wilaya demandée.
		$list = array();
		foreach ( $res as $row ) {
			if ( is_array( $row ) && isset( $row['nom'] ) && (int) $row['wilaya_id'] === $wilaya_code ) {
				$list[] = array(
					'nom'           => (string) $row['nom'],
					'has_stop_desk' => ! empty( $row['has_stop_desk'] ),
				);
			}
		}
		if ( ! empty( $list ) ) {
			set_transient( $key, $list, 12 * HOUR_IN_SECONDS );
		}
		return $list;
	}

	/**
	 * Normalise un nom de commune pour une comparaison tolérante
	 * (accents, casse, apostrophes/tirets, et « y » ≈ « i »).
	 *
	 * @param string $name
	 * @return string
	 */
	protected function normalize_commune( $name ) {
		$name = remove_accents( (string) $name );
		$name = strtolower( $name );
		$name = str_replace( 'y', 'i', $name );           // Tichy ≈ Tichi.
		$name = preg_replace( '/[^a-z0-9]+/', '', $name ); // Retire espaces, apostrophes, tirets.
		return (string) $name;
	}

	/**
	 * Résout le nom de commune saisi vers l'orthographe canonique EcoTrack.
	 *
	 * Évite l'erreur « Commune mal écrite » en alignant le nom envoyé sur la
	 * base EcoTrack (correspondance normalisée puis distance de Levenshtein).
	 *
	 * @param int    $wilaya_code
	 * @param string $commune
	 * @return array [ 'nom' => string, 'has_stop_desk' => bool, 'matched' => bool ].
	 */
	protected function resolve_commune( $wilaya_code, $commune ) {
		$commune = trim( (string) $commune );
		$list    = $this->communes( $wilaya_code );
		if ( empty( $list ) ) {
			// Pas de référentiel disponible : on laisse EcoTrack valider tel quel.
			return array( 'nom' => $commune, 'has_stop_desk' => false, 'matched' => false );
		}

		$target = $this->normalize_commune( $commune );

		// 1) Correspondance normalisée exacte (gère accents/casse/ponctuation/y≈i).
		foreach ( $list as $c ) {
			if ( $this->normalize_commune( $c['nom'] ) === $target ) {
				return array( 'nom' => $c['nom'], 'has_stop_desk' => $c['has_stop_desk'], 'matched' => true );
			}
		}

		// 2) Repli : commune la plus proche (petites fautes de frappe).
		$best = null;
		$best_d = PHP_INT_MAX;
		foreach ( $list as $c ) {
			$d = levenshtein( $target, $this->normalize_commune( $c['nom'] ) );
			if ( $d < $best_d ) {
				$best_d = $d;
				$best   = $c;
			}
		}
		if ( $best && $best_d <= 2 ) {
			return array( 'nom' => $best['nom'], 'has_stop_desk' => $best['has_stop_desk'], 'matched' => true );
		}

		return array( 'nom' => $commune, 'has_stop_desk' => false, 'matched' => false );
	}

	/**
	 * Communes (clés normalisées) de la wilaya disposant d'un point relais EcoTrack.
	 *
	 * Permet au formulaire public de n'afficher « bureau » que là où EcoTrack a
	 * réellement un stop-desk. On renvoie null si le référentiel est indisponible
	 * (livreur non configuré ou API muette) afin de NE PAS masquer l'option à tort
	 * sur une panne réseau — la validation finale reste faite à la confirmation.
	 *
	 * @param int $wilaya_code 1-58.
	 * @return string[]|null Clés normalisées des communes avec bureau, ou null.
	 */
	public function desk_communes( $wilaya_code ) {
		$list = $this->communes( $wilaya_code );
		if ( empty( $list ) ) {
			return null; // Pas de référentiel : on ne filtre pas (fallback sûr).
		}
		$keys = array();
		foreach ( $list as $c ) {
			if ( ! empty( $c['has_stop_desk'] ) ) {
				$keys[] = $this->normalize_commune( $c['nom'] );
			}
		}
		return array_values( array_unique( $keys ) );
	}

	public function build_payload( $order ) {
		$s       = $this->settings();
		$is_desk = $this->is_stopdesk_order( $order );

		$raw_commune = $order->get_meta( '_aod_commune' ) ? $order->get_meta( '_aod_commune' ) : $order->get_billing_city();
		$match       = $this->resolve_commune( (int) $order->get_meta( '_aod_wilaya_code' ), $raw_commune );

		// EcoTrack pilote le stop-desk par la COMMUNE (champ has_stop_desk), pas
		// par un code de station : on ne transmet donc aucun « station_code ».
		$payload = array(
			'api_token'    => $s['api_token'],
			'reference'    => (string) $order->get_order_number(),
			'nom_client'   => $this->full_name( $order ),
			'telephone'    => $order->get_billing_phone(),
			'telephone_2'  => '',
			'adresse'      => $order->get_billing_address_1() ? $order->get_billing_address_1() : $raw_commune,
			'code_wilaya'  => (int) $order->get_meta( '_aod_wilaya_code' ),
			'commune'      => $match['nom'], // Orthographe canonique EcoTrack.
			'montant'      => $this->order_total( $order ),
			// 47.3 — ce champ partait VIDE depuis toujours. Il couvre EcoTrack et ses
			// 23 white-labels, soit 24 des 29 transporteurs du registre.
			'remarque'     => $this->driver_note( $order ),
			'produit'      => $this->product_list( $order ),
			'type'         => 1, // 1 = livraison. Les instances EcoTrack récentes (API v1.1.0) exigent « type ».
			'type_id'      => 1, // Conservé pour compatibilité avec les instances plus anciennes.
			'poids'        => (int) $s['weight'],
			'stop_desk'    => $is_desk ? 1 : 0,
			'stock'        => 0,
			'quantite'     => '1',
			'fragile'      => (int) $s['fragile'],
		);
		return $payload;
	}

	public function create_parcel( $order ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_ecotrack_no_creds', __( 'Domaine ou token EcoTrack manquant.', 'aod-cod-form' ) );
		}
		$wilaya = (int) $order->get_meta( '_aod_wilaya_code' );
		if ( ! $wilaya ) {
			return new WP_Error( 'aod_ecotrack_no_wilaya', __( 'Wilaya de la commande manquante.', 'aod-cod-form' ) );
		}

		// Garde-fou montant : message clair avant l'appel API si le COD dépasse le plafond.
		$over = $this->check_cod_limit( $order );
		if ( is_wp_error( $over ) ) {
			return $over;
		}

		// Stop-desk demandé mais la commune n'a pas de point relais EcoTrack :
		// message clair plutôt qu'un refus générique « commune mal écrite ».
		if ( $this->is_stopdesk_order( $order ) ) {
			$raw_commune = $order->get_meta( '_aod_commune' ) ? $order->get_meta( '_aod_commune' ) : $order->get_billing_city();
			$match       = $this->resolve_commune( $wilaya, $raw_commune );
			if ( $match['matched'] && ! $match['has_stop_desk'] ) {
				return new WP_Error( 'aod_ecotrack_no_desk', sprintf(
					/* translators: %s : nom de la commune */
					__( 'La commune « %s » ne dispose pas de point relais (stop-desk) EcoTrack. Choisissez la livraison à domicile.', 'aod-cod-form' ),
					$match['nom']
				) );
			}
		}

		$s       = $this->settings();
		$headers = array( 'Authorization' => 'Bearer ' . $s['api_token'], 'Accept' => 'application/json' );

		$payload = $this->build_payload( $order );
		$res     = $this->remote( 'POST', $this->api_base() . 'create/order', array( 'body' => $payload, 'headers' => $headers ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( empty( $res['success'] ) || empty( $res['tracking'] ) ) {
			$msg = ! empty( $res['message'] ) ? $res['message'] : __( 'EcoTrack a refusé le colis.', 'aod-cod-form' );
			return new WP_Error( 'aod_ecotrack_rejected', $msg, $res );
		}
		$tracking = $res['tracking'];

		if ( ! empty( $s['auto_validate'] ) ) {
			$this->remote( 'POST', $this->api_base() . 'valid/order', array(
				'body'    => array( 'api_token' => $s['api_token'], 'tracking' => $tracking ),
				'headers' => $headers,
			) );
		}

		$label = add_query_arg(
			array( 'api_token' => $s['api_token'], 'tracking' => $tracking ),
			$this->api_base() . 'get/order/label'
		);

		return array(
			'tracking'  => $tracking,
			'label'     => $label,
			'import_id' => '',
		);
	}

	/**
	 * Vérifie domaine + token via un appel léger en lecture seule.
	 *
	 * GET get/communes (Bearer token) renvoie 2xx si le token est accepté par
	 * l'instance EcoTrack ; un domaine ou token invalide remonte une WP_Error.
	 *
	 * @return array|WP_Error
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return parent::test_connection();
		}
		$s   = $this->settings();
		$url = add_query_arg(
			array( 'api_token' => $s['api_token'], 'wilaya_id' => 16 ),
			$this->api_base() . 'get/communes'
		);
		$res = $this->remote( 'GET', $url, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $s['api_token'], 'Accept' => 'application/json' ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'live' => true, 'message' => '' );
	}

	public function supports_fees() {
		return true;
	}

	/**
	 * Tarifs de livraison EcoTrack (frais Domicile/Stop-desk) par wilaya.
	 *
	 * Un seul appel `GET get/fees` (Bearer + api_token, patron des autres GET
	 * EcoTrack) renvoie tous les tarifs → réduits par `normalize_wilaya_fees()`.
	 * Lecture seule (n'écrit jamais `aod_cod_delivery_prices`) ; cache transient
	 * 12 h **par domaine** (les white-labels partagent la classe mais ont chacun
	 * leur domaine/token). Extraction tolérante (liste directe ou sous
	 * `livraison`/`fees`/`data`/`tarifs`, clés candidates).
	 *
	 * @param bool $force Ignorer le cache et réinterroger l'API.
	 * @return array|WP_Error [ (int) wilaya_id => [ 'home'=>int, 'desk'=>int ] ].
	 */
	public function get_fees( $force = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_ecotrack_no_creds', __( 'Domaine ou token EcoTrack manquant.', 'aod-cod-form' ) );
		}

		$cache_key = 'aod_eco_fees_' . md5( $this->effective_domain() );
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$s   = $this->settings();
		$url = add_query_arg( array( 'api_token' => $s['api_token'] ), $this->api_base() . 'get/fees' );
		$res = $this->remote( 'GET', $url, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $s['api_token'], 'Accept' => 'application/json' ),
			'timeout' => 8,
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$rows = $res;
		foreach ( array( 'livraison', 'fees', 'data', 'tarifs' ) as $k ) {
			if ( isset( $res[ $k ] ) && is_array( $res[ $k ] ) ) {
				$rows = $res[ $k ];
				break;
			}
		}
		$fees = $this->normalize_wilaya_fees(
			$rows,
			array( 'wilaya_id', 'code_wilaya', 'id_wilaya', 'wilaya' ),
			array( 'tarif', 'tarif_livraison', 'livraison', 'home', 'prix' ),
			array( 'tarif_stopdesk', 'stopdesk', 'stop_desk', 'desk' )
		);
		if ( empty( $fees ) ) {
			return new WP_Error( 'aod_ecotrack_fees_empty', __( 'Aucun tarif n’a pu être récupéré chez EcoTrack.', 'aod-cod-form' ) );
		}

		set_transient( $cache_key, $fees, 12 * HOUR_IN_SECONDS );
		return $fees;
	}

	public function supports_tracking() {
		return true;
	}

	/**
	 * Suivi d'un colis EcoTrack.
	 *
	 * POST get/trackings/info { api_token, trackings: [tracking] } (Bearer) renvoie,
	 * indexé par tracking, un objet contenant un tableau `activity` (événements avec
	 * date + libellé). La forme exacte varie selon la version de l'instance : on lit
	 * donc plusieurs clés candidates et on retombe sur le statut direct au besoin.
	 *
	 * @param string $tracking
	 * @return array|WP_Error
	 */
	public function get_tracking( $tracking ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_ecotrack_no_creds', __( 'Domaine ou token EcoTrack manquant.', 'aod-cod-form' ) );
		}
		$tracking = trim( (string) $tracking );
		if ( '' === $tracking ) {
			return new WP_Error( 'aod_ecotrack_no_tracking', __( 'Numéro de suivi manquant.', 'aod-cod-form' ) );
		}
		$s   = $this->settings();
		// EcoTrack expose ce point d'accès en GET (le token + les trackings passent
		// en paramètres d'URL ; `trackings[]` accepte un ou plusieurs suivis).
		$url = add_query_arg(
			array( 'api_token' => $s['api_token'], 'trackings' => array( $tracking ) ),
			$this->api_base() . 'get/trackings/info'
		);
		$res = $this->remote( 'GET', $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $s['api_token'],
				'Accept'        => 'application/json',
			),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		// Réponse EcoTrack : { <tracking>: { status: "Prêt à expédier", activity: [
		//   { date, time, status: "<code machine>", station }, … ], deliveryAttempts: [] } }
		$entry  = $this->extract_entry( $res, $tracking );
		$events = ( isset( $entry['activity'] ) && is_array( $entry['activity'] ) ) ? $entry['activity'] : array();

		$history = array();
		foreach ( $events as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			$code = $this->first_str( $e, array( 'status', 'event', 'statut' ) );
			$date = trim( $this->first_str( $e, array( 'date' ) ) . ' ' . $this->first_str( $e, array( 'time' ) ) );
			$loc  = $this->first_str( $e, array( 'station', 'commune', 'wilaya', 'center' ) );
			$key  = $this->map_ecotrack_code( $code );
			if ( self::ST_UNKNOWN === $key ) {
				$key = $this->guess_status( $code );
			}
			$history[] = array(
				'ts'       => $date ? (int) strtotime( $date ) : 0,
				'date'     => $date,
				'status'   => $key,
				// Code reconnu → libellé vide (l'UI affiche le statut normalisé traduit) ;
				// sinon on humanise le code brut pour ne rien perdre.
				'label'    => ( self::ST_UNKNOWN === $key ) ? ucfirst( str_replace( '_', ' ', $code ) ) : '',
				'location' => $loc,
			);
		}

		// Repli : statut courant lisible si aucune activité détaillée n'est renvoyée.
		if ( empty( $history ) ) {
			$label = $this->first_str( $entry, array( 'status', 'statut', 'last_status', 'situation' ) );
			if ( '' !== $label ) {
				$history[] = array( 'ts' => 0, 'date' => '', 'status' => $this->guess_status( $label ), 'label' => $label, 'location' => '' );
			}
		}

		$out = $this->build_tracking( $history );
		// EcoTrack fournit un libellé courant lisible (FR) : on l'expose tel quel.
		$top = $this->first_str( $entry, array( 'status', 'statut' ) );
		if ( '' !== $top ) {
			$out['raw_label'] = $top;
		}
		return $out;
	}

	/**
	 * Mappe un code d'activité EcoTrack (ex. « order_information_received_by_carrier »,
	 * « out_for_delivery », « delivered »…) vers le vocabulaire de statut normalisé.
	 *
	 * @param string $code
	 * @return string Clé normalisée (ST_*) ou ST_UNKNOWN si non reconnu.
	 */
	protected function map_ecotrack_code( $code ) {
		$c = strtolower( trim( (string) $code ) );
		if ( '' === $c ) {
			return self::ST_UNKNOWN;
		}
		// Les codes du flux `activity` sont en anglais (« order_information_received_by_carrier »),
		// mais certaines déclinaisons EcoTrack en émettent aussi en français : Allo
		// Livraison renvoie `prete_a_expedier` sur `get/orders` (recette réseau du
		// 2026-07-13). D'où les codes FR ci-dessous — sans eux, `prete_a_expedier`
		// (PRÊT à expédier, donc pas encore parti) retombait sur `guess_status()` et
		// matchait « expédi » → « en transit ». Sans effet sur les statistiques (les
		// deux sont non terminaux), mais la timeline affichait un colis en route
		// alors qu'il attendait encore au dépôt.
		// L'ordre compte : les états terminaux passent avant les états de flux.
		$rules = array(
			self::ST_RETURNED         => array( 'return', 'renvoy', 'retourn' ),
			self::ST_CANCELLED        => array( 'cancel', 'canceled', 'cancelled', 'annul' ),
			self::ST_FAILED           => array( 'fail', 'attempt', 'postpon', 'unreachable', 'refus', 'absent', 'reschedul', 'echec', 'report' ),
			self::ST_DELIVERED        => array( 'delivered', 'livree', 'completed' ),
			self::ST_OUT_FOR_DELIVERY => array( 'out_for_delivery', 'dispatch', 'with_delivery', 'in_delivery', 'on_the_way', 'en_livraison', 'sortie_livraison' ),
			self::ST_CREATED          => array( 'received_by_carrier', 'information_received', 'created', 'ready', 'pending', 'registered', 'new_order', 'preparation', 'prete_a_expedier', 'pret_a_expedier' ),
			self::ST_IN_TRANSIT       => array( 'transit', 'hub', 'warehouse', 'transfer', 'received_at', 'picked', 'collected', 'arrived', 'in_progress', 'processing', 'expedi', 'ramass' ),
		);
		foreach ( $rules as $key => $needles ) {
			foreach ( $needles as $n ) {
				if ( false !== strpos( $c, $n ) ) {
					return $key;
				}
			}
		}
		return self::ST_UNKNOWN;
	}
}
