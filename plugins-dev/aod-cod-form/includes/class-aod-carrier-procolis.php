<?php
/**
 * Livreur E-com Delivery (ecom-dz.com) — API v2.
 *
 * API : https://ecom-dz.com/api_v2/ — auth par en-têtes X-API-Key (« KEY », clé
 * fixe) + X-API-Token (« Token », régénérable). Référentiels en lecture :
 * /wilayas, /communes, /stopdesks, /tarifs. Création : POST /colis (tableau de
 * 1..100 colis) → HTTP 201 ; vérifier resultats[i].ok / .tracking / .erreur.
 *
 * Domicile : champ `commune` (validé par nom + wilaya, casse/accents ignorés).
 * Stop-desk : `stopdesk=1` + `code_stopdesk` du bureau choisi (cf. /stopdesks).
 * Étiquette (bordereau) : GET /colis/{tracking}/bordereau (endpoint authentifié,
 * pas de lien direct public).
 *
 * Ce compte partage le backend de l'ancienne marque « ZR Express / Procolis » :
 * mêmes identifiants. L'id interne reste « zrexpress » (compat des réglages
 * déjà enregistrés + envoi auto).
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_Carrier_Procolis extends AOD_Carrier {

	const API_BASE = 'https://ecom-dz.com/api_v2/';

	/** @var array Config d'instance : id, label, brand, initials. */
	protected $config;

	public function __construct( $config = array() ) {
		$this->config = wp_parse_args( $config, array(
			'id'       => 'zrexpress',
			'label'    => 'E-com Delivery',
			'brand'    => '#ef4444',
			'initials' => 'EC',
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

	/** E-com Delivery gère le stop-desk via le code_stopdesk d'un bureau de la wilaya. */
	public function supports_stopdesk() {
		return true;
	}

	protected function defaults() {
		return array(
			'token' => '',
			'key'   => '',
		);
	}

	public function is_configured() {
		$s = $this->settings();
		return '' !== $s['token'] && '' !== $s['key'];
	}

	public function render_settings_fields() {
		$s = $this->settings();
		$p = $this->id();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'KEY', 'aod-cod-form' ); ?></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $p ); ?>[key]" value="<?php echo esc_attr( $s['key'] ); ?>" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Token', 'aod-cod-form' ); ?></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $p ); ?>[token]" value="<?php echo esc_attr( $s['token'] ); ?>" autocomplete="off">
				<p class="description"><?php esc_html_e( 'ecom-dz.com → Identifiants API (KEY = clé fixe, Token = régénérable).', 'aod-cod-form' ); ?></p></td>
			</tr>
		</table>
		<?php
	}

	public function sanitize_settings( $input ) {
		return array(
			'token' => isset( $input['token'] ) ? sanitize_text_field( $input['token'] ) : '',
			'key'   => isset( $input['key'] ) ? sanitize_text_field( $input['key'] ) : '',
		);
	}

	/** En-têtes d'authentification E-com Delivery (API v2). */
	protected function headers() {
		$s = $this->settings();
		return array(
			'X-API-Key'    => $s['key'],
			'X-API-Token'  => $s['token'],
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
	}

	/**
	 * Vérifie KEY + Token via GET /test (renvoie l'identité du compte fournisseur).
	 *
	 * @return array|WP_Error
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return parent::test_connection();
		}
		$res = $this->remote( 'GET', self::API_BASE . 'test', array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( ! isset( $res['id_fournisseur'] ) ) {
			return new WP_Error( 'aod_ecomdz_denied', __( 'Identifiants refusés (KEY / Token).', 'aod-cod-form' ) );
		}
		$nom = isset( $res['nom_fournisseur'] ) ? (string) $res['nom_fournisseur'] : '';
		return array( 'live' => true, 'message' => $nom );
	}

	public function supports_fees() {
		return true;
	}

	/**
	 * Tarifs de livraison E-com Delivery (frais Domicile/Stop-desk) par wilaya.
	 *
	 * Un seul appel `GET /tarifs` (référentiel exposé par l'API v2) renvoie tous
	 * les tarifs → réduits par `normalize_wilaya_fees()`. Lecture seule (n'écrit
	 * jamais `aod_cod_delivery_prices`) ; cache transient 12 h.
	 *
	 * Schéma réel, confirmé en recette réseau sur un compte de production
	 * (API v2.110, 2026-07-13) :
	 *
	 *     { "id_fournisseur": 8736, "service": 0, "emballage": 0,
	 *       "wilayas": [ { "wilaya": 1, "domicile": 1100, "stopdesk": 750, "annuler": 0 }, … ] }
	 *
	 * La liste est sous `wilayas` et le tarif domicile sous `domicile` — deux noms
	 * qui ne figuraient dans AUCUNE de nos clés candidates : l'import échouait donc
	 * intégralement (« Aucun tarif n'a pu être récupéré »). Les clés historiques
	 * sont conservées en repli, mais les vraies passent en tête.
	 *
	 * Une wilaya non desservie est renvoyée à `0/0` (ex. 50 et 54) : elle est
	 * écartée par `normalize_wilaya_fees()`, et l'import laisse donc le prix déjà
	 * saisi par le gérant intact.
	 *
	 * ⚠️ `service` et `emballage` sont des frais GLOBAUX, facturés en plus du tarif
	 * par wilaya. Ils ne sont volontairement pas ajoutés ici : `get_fees()` renvoie
	 * le tarif de livraison du transporteur, et c'est la marge de l'écran d'import
	 * qui sert à les répercuter (choix du gérant, pas du code).
	 *
	 * @param bool $force Ignorer le cache et réinterroger l'API.
	 * @return array|WP_Error [ (int) wilaya_id => [ 'home'=>int, 'desk'=>int ] ].
	 */
	public function get_fees( $force = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_ecomdz_no_creds', __( 'Identifiants E-com Delivery manquants (KEY / Token).', 'aod-cod-form' ) );
		}

		$cache_key = 'aod_' . $this->id() . '_fees';
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$res = $this->remote( 'GET', self::API_BASE . 'tarifs', array(
			'headers' => $this->headers(),
			'timeout' => 8,
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$rows = $res;
		foreach ( array( 'wilayas', 'tarifs', 'data', 'livraison' ) as $k ) {
			if ( isset( $res[ $k ] ) && is_array( $res[ $k ] ) ) {
				$rows = $res[ $k ];
				break;
			}
		}
		$fees = $this->normalize_wilaya_fees(
			$rows,
			array( 'wilaya', 'id_wilaya', 'IDWilaya', 'wilaya_id' ),
			array( 'domicile', 'tarif', 'Tarif', 'tarif_domicile', 'livraison', 'prix' ),
			array( 'stopdesk', 'tarif_stopdesk', 'tarif_stop_desk', 'stop_desk', 'desk' )
		);
		if ( empty( $fees ) ) {
			return new WP_Error( 'aod_ecomdz_fees_empty', __( 'Aucun tarif n’a pu être récupéré chez E-com Delivery.', 'aod-cod-form' ) );
		}

		set_transient( $cache_key, $fees, 12 * HOUR_IN_SECONDS );
		return $fees;
	}

	/**
	 * Bureaux stop-desk bruts d'une wilaya (cache 24 h).
	 *
	 * @param int $wilaya_code 1-58.
	 * @return array Liste [ [ 'code' =>, 'commune' =>, 'name' => ], … ].
	 */
	protected function desks( $wilaya_code ) {
		$wilaya_code = absint( $wilaya_code );
		if ( $wilaya_code < 1 || ! $this->is_configured() ) {
			return array();
		}
		// v2 dans la clé : le format du cache a évolué (id/name → code/commune/name) ;
		// éviter de lire un ancien transient au format incompatible après mise à jour.
		$key    = 'aod_ecomdz_desks2_' . $wilaya_code;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = $this->remote( 'GET', self::API_BASE . 'stopdesks?id_wilaya=' . $wilaya_code, array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $res ) || ! is_array( $res ) ) {
			return array();
		}
		$list = array();
		foreach ( $res as $row ) {
			if ( ! is_array( $row ) || empty( $row['code_stopdesk'] ) ) {
				continue;
			}
			$commune = isset( $row['commune'] ) ? trim( (string) $row['commune'] ) : '';
			$name    = isset( $row['nom_bureau'] ) ? (string) $row['nom_bureau'] : (string) $row['code_stopdesk'];
			if ( '' !== $commune && false === stripos( $name, $commune ) ) {
				$name .= ' — ' . $commune;
			}
			$list[] = array( 'code' => (string) $row['code_stopdesk'], 'commune' => $commune, 'name' => $name );
		}
		if ( ! empty( $list ) ) {
			set_transient( $key, $list, DAY_IN_SECONDS );
		}
		return $list;
	}

	/**
	 * Bureaux stop-desk d'une wilaya, format générique [ ['id'=>, 'name'=>], … ]
	 * (utilisé par le dashboard gérant pour un éventuel choix manuel).
	 *
	 * @param int $wilaya_code 1-58.
	 * @return array
	 */
	public function get_centers( $wilaya_code ) {
		$list = array();
		foreach ( $this->desks( $wilaya_code ) as $d ) {
			$list[] = array( 'id' => $d['code'], 'name' => $d['name'] );
		}
		return $list;
	}

	/**
	 * Déduit le bureau de retrait d'une commande « bureau » à partir de sa commune
	 * (l'acheteur ne choisit plus de bureau dans le formulaire). On cherche le bureau
	 * dont la commune correspond à celle de la commande ; repli : premier bureau de
	 * la wilaya.
	 *
	 * @param WC_Order $order
	 * @return string Code du bureau, ou '' si la wilaya n'a aucun bureau.
	 */
	public function resolve_stopdesk_code( $order ) {
		$wilaya  = (int) $order->get_meta( '_aod_wilaya_code' );
		$desks   = $this->desks( $wilaya );
		if ( empty( $desks ) ) {
			return '';
		}
		$commune = $order->get_meta( '_aod_commune' ) ? $order->get_meta( '_aod_commune' ) : $order->get_billing_city();
		$target  = $this->norm( $commune );
		if ( '' !== $target ) {
			foreach ( $desks as $d ) {
				if ( '' !== $d['commune'] && $this->norm( $d['commune'] ) === $target ) {
					return $d['code'];
				}
			}
		}
		return $desks[0]['code']; // Aucun bureau dans la commune : premier bureau de la wilaya.
	}

	/** Normalise un nom de commune pour la comparaison (sans accents/espaces/casse). */
	protected function norm( $str ) {
		$str = remove_accents( (string) $str );
		$str = strtolower( $str );
		return preg_replace( '/[^a-z0-9]/', '', $str );
	}

	/**
	 * Résout le nom de commune saisi vers l'orthographe officielle E-com (domicile).
	 *
	 * L'API valide la commune par nom + wilaya ; on aligne donc le nom envoyé sur
	 * le référentiel /communes (correspondance normalisée puis Levenshtein) pour
	 * éviter l'erreur « commune introuvable ». Cache 12 h par wilaya.
	 *
	 * @param int    $wilaya_code
	 * @param string $commune
	 * @return string Nom de commune à envoyer.
	 */
	protected function resolve_commune( $wilaya_code, $commune ) {
		$wilaya_code = absint( $wilaya_code );
		$commune     = trim( (string) $commune );
		$key         = 'aod_ecomdz_communes_' . $wilaya_code;
		$list        = get_transient( $key );

		if ( ! is_array( $list ) ) {
			$res  = $this->remote( 'GET', self::API_BASE . 'communes?id_wilaya=' . $wilaya_code, array( 'headers' => $this->headers() ) );
			$list = array();
			if ( ! is_wp_error( $res ) && is_array( $res ) ) {
				foreach ( $res as $row ) {
					if ( is_array( $row ) && ! empty( $row['commune'] ) ) {
						$list[] = (string) $row['commune'];
					}
				}
			}
			if ( ! empty( $list ) ) {
				set_transient( $key, $list, 12 * HOUR_IN_SECONDS );
			}
		}
		if ( empty( $list ) ) {
			return $commune; // Pas de référentiel : on envoie tel quel (l'API tranchera).
		}

		$target = $this->norm( $commune );
		foreach ( $list as $name ) {
			if ( $this->norm( $name ) === $target ) {
				return $name;
			}
		}
		// Repli : commune la plus proche (petites fautes de frappe).
		$best = null;
		$bd   = PHP_INT_MAX;
		foreach ( $list as $name ) {
			$d = levenshtein( $target, $this->norm( $name ) );
			if ( $d < $bd ) {
				$bd   = $d;
				$best = $name;
			}
		}
		return ( null !== $best && $bd <= 2 ) ? $best : $commune;
	}

	/** Tronque une chaîne à la longueur max imposée par l'API. */
	protected function clip( $str, $max ) {
		$str = trim( (string) $str );
		return function_exists( 'mb_substr' ) ? mb_substr( $str, 0, $max ) : substr( $str, 0, $max );
	}

	/** Quantité totale (somme des quantités d'articles). */
	protected function total_qty( $order ) {
		$q = 0;
		foreach ( $order->get_items() as $item ) {
			$q += (int) $item->get_quantity();
		}
		return max( 1, $q );
	}

	public function build_payload( $order ) {
		$is_desk     = $this->is_stopdesk_order( $order );
		$wilaya_code = (int) $order->get_meta( '_aod_wilaya_code' );
		$commune_raw = $order->get_meta( '_aod_commune' ) ? $order->get_meta( '_aod_commune' ) : $order->get_billing_city();

		$colis = array(
			'nom_complet' => $this->clip( $this->full_name( $order ), 20 ),
			'mobile_1'    => $this->clip( $order->get_billing_phone(), 25 ),
			'id_wilaya'   => $wilaya_code,
			'article'     => $this->clip( $this->product_list( $order ), 255 ),
			'quantite'    => $this->total_qty( $order ),
			'total'       => $this->order_total( $order ),
			'adresse'     => $this->clip( $order->get_billing_address_1() ? $order->get_billing_address_1() : $commune_raw, 30 ),
			'id_externe'  => $this->clip( $order->get_order_number(), 20 ),
			'stopdesk'    => $is_desk ? 1 : 0,
		);
		if ( $is_desk ) {
			// Bureau déjà fixé (choix manuel dashboard) ou déduit de la commune.
			$code = (string) $order->get_meta( self::META_STOPDESK );
			if ( '' === $code ) {
				$code = $this->resolve_stopdesk_code( $order );
			}
			$colis['code_stopdesk'] = $code;
		} else {
			$colis['commune'] = $this->resolve_commune( $wilaya_code, $commune_raw );
		}
		return $colis;
	}

	public function create_parcel( $order ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_ecomdz_no_creds', __( 'Identifiants E-com Delivery manquants (KEY / Token).', 'aod-cod-form' ) );
		}
		if ( ! (int) $order->get_meta( '_aod_wilaya_code' ) ) {
			return new WP_Error( 'aod_ecomdz_no_wilaya', __( 'Wilaya de la commande manquante.', 'aod-cod-form' ) );
		}

		$payload = $this->build_payload( $order );
		// Stop-desk : le bureau est déduit de la wilaya/commune ; on n'échoue que si
		// la wilaya n'expose réellement aucun bureau.
		if ( $this->is_stopdesk_order( $order ) && empty( $payload['code_stopdesk'] ) ) {
			return new WP_Error( 'aod_ecomdz_no_desk', __( 'Aucun bureau stop-desk disponible pour cette wilaya. Choisissez la livraison à domicile.', 'aod-cod-form' ) );
		}

		$res = $this->remote( 'POST', self::API_BASE . 'colis', array(
			'headers' => $this->headers(),
			'body'    => wp_json_encode( array( $payload ) ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		// HTTP 201 même si la ligne échoue : se fier à resultats[0].ok / .erreur.
		$row = isset( $res['resultats'][0] ) && is_array( $res['resultats'][0] ) ? $res['resultats'][0] : array();
		if ( empty( $row['ok'] ) || empty( $row['tracking'] ) ) {
			$msg = ! empty( $row['erreur'] ) ? $row['erreur'] : __( 'E-com Delivery a refusé le colis.', 'aod-cod-form' );
			return new WP_Error( 'aod_ecomdz_rejected', $msg, $row );
		}

		return array(
			'tracking'  => (string) $row['tracking'],
			'label'     => '', // Bordereau via endpoint authentifié (pas de lien direct).
			'import_id' => isset( $row['id_colis'] ) ? (string) $row['id_colis'] : '',
		);
	}

	public function supports_tracking() {
		return true;
	}

	/**
	 * Table officielle `id_situation` → statut normalisé (API v2, section « Situations »).
	 *
	 * C'est CETTE table qui fait foi, pas le dernier événement de l'historique.
	 * Recette réseau du 2026-07-13 sur un compte réel (1 253 colis) : l'historique
	 * d'un colis ANNULÉ se termine par « Au Bureau », celui d'un colis « Ne Réponde
	 * pas » par « Appel Tel » — le dernier événement est un fait LOGISTIQUE, pas la
	 * situation commerciale du colis. En déduisant le statut de l'historique, on
	 * classait donc en « inconnu » (→ compté « En transit » par le tableau de bord)
	 * les annulés, les injoignables et les reportés : ~18 % des colis du compte.
	 *
	 * Choix assumé : on reprend la classification d'E-com telle quelle, pour que le
	 * gérant lise la MÊME chose dans `/gestion` et dans le back-office du livreur.
	 * D'où « Annuler x3 » → annulé (et non « retourné », alors même que le colis
	 * revient physiquement — le retour reste visible dans la timeline).
	 *
	 * @param int $id Identifiant de situation (1..28).
	 * @return string Clé ST_* normalisée, ou '' si l'id est inconnu.
	 */
	protected static function status_from_situation_id( $id ) {
		$map = array(
			1  => self::ST_CREATED,           // EnCours (état par défaut à la création)
			2  => self::ST_FAILED,            // Ne Réponde pas #1
			3  => self::ST_FAILED,            // Ne Réponde pas #2
			4  => self::ST_FAILED,            // Ne Réponde pas #3
			5  => self::ST_CANCELLED,         // Annuler
			6  => self::ST_CANCELLED,         // Annuler x3
			7  => self::ST_DELIVERED,         // Livrée (terminal de succès)
			8  => self::ST_FAILED,            // En attente du client
			9  => self::ST_FAILED,            // Reporté
			10 => self::ST_FAILED,            // Reporté Commune Erronée
			11 => self::ST_FAILED,            // Reporté Wilaya Erronée
			12 => self::ST_IN_TRANSIT,        // BIZ (colis d'échange généré)
			13 => self::ST_IN_TRANSIT,        // Appel Tel (contact, pas un état de livraison)
			14 => self::ST_DELIVERED,         // Encaisser (post-livraison)
			15 => self::ST_DELIVERED,         // Recouvert (post-livraison)
			16 => self::ST_IN_TRANSIT,        // SMS Envoyé
			17 => self::ST_CREATED,           // Attend Confirmation
			18 => self::ST_CANCELLED,         // Commande Annuler
			19 => self::ST_CREATED,           // Commande Confirmée
			20 => self::ST_FAILED,            // Commande Reporté
			21 => self::ST_FAILED,            // Commande a Relancé
			22 => self::ST_IN_TRANSIT,        // WhatsApp OK
			23 => self::ST_RETURNED,          // Retour de Dispatche
			24 => self::ST_RETURNED,          // Retour Navette
			25 => self::ST_IN_TRANSIT,        // Au Bureau
			26 => self::ST_OUT_FOR_DELIVERY,  // Sortir en livraison
			27 => self::ST_OUT_FOR_DELIVERY,  // Dispatcher
			28 => self::ST_IN_TRANSIT,        // Suivi par E-com
		);
		$id = (int) $id;
		return isset( $map[ $id ] ) ? $map[ $id ] : '';
	}

	/**
	 * Statut normalisé d'un LIBELLÉ E-com (situation ou état logistique).
	 *
	 * Sert (a) de repli quand l'API ne renvoie pas d'`id_situation` (comptes restés
	 * sur une version antérieure à la v2.101) et (b) à colorer chaque ligne de la
	 * timeline. Volontairement local à cette classe : `guess_status()` est partagé
	 * avec les autres transporteurs, que cette recette n'a pas pu vérifier.
	 *
	 * @param string $label
	 * @return string Clé ST_*.
	 */
	protected function status_from_label( $label ) {
		$map = array(
			'encours'                => self::ST_CREATED,
			'enpreparation'          => self::ST_CREATED,
			'attendconfirmation'     => self::ST_CREATED,
			'commandeconfirmee'      => self::ST_CREATED,
			'nerepondepas1'          => self::ST_FAILED,
			'nerepondepas2'          => self::ST_FAILED,
			'nerepondepas3'          => self::ST_FAILED,
			'enattenteduclient'      => self::ST_FAILED,
			'commandearelance'       => self::ST_FAILED,
			'aubureau'               => self::ST_IN_TRANSIT,
			'appeltel'               => self::ST_IN_TRANSIT,
			'smsenvoye'              => self::ST_IN_TRANSIT,
			'whatsappok'             => self::ST_IN_TRANSIT,
			'suiviparecom'           => self::ST_IN_TRANSIT,
			'biz'                    => self::ST_IN_TRANSIT,
			'encaisser'              => self::ST_DELIVERED,
			'recouvert'              => self::ST_DELIVERED,
			'recuperer'              => self::ST_DELIVERED,
			'retourfournisseur'      => self::ST_RETURNED,
			'retourstock'            => self::ST_RETURNED,
			'supprimee'              => self::ST_CANCELLED,
		);
		$n = $this->norm( $label );
		if ( isset( $map[ $n ] ) ) {
			return $map[ $n ];
		}
		return $this->guess_status( $label ); // « Livrée », « Annuler », « Reporté »… déjà couverts.
	}

	/**
	 * Suivi d'un colis E-com Delivery.
	 *
	 * `GET /colis/{tracking}` renvoie la fiche du colis + son `historique`. Le statut
	 * courant est pris sur le colis lui-même (`id_situation`, cf.
	 * `status_from_situation_id()`), JAMAIS sur le dernier événement d'historique —
	 * qui décrit un mouvement logistique et non la situation du colis.
	 * L'historique ne sert qu'à la timeline (et au calcul du délai de livraison).
	 *
	 * @param string $tracking
	 * @return array|WP_Error
	 */
	public function get_tracking( $tracking ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_ecomdz_no_creds', __( 'Identifiants E-com Delivery manquants (KEY / Token).', 'aod-cod-form' ) );
		}
		$tracking = trim( (string) $tracking );
		if ( '' === $tracking ) {
			return new WP_Error( 'aod_ecomdz_no_tracking', __( 'Numéro de suivi manquant.', 'aod-cod-form' ) );
		}
		$res = $this->remote( 'GET', self::API_BASE . 'colis/' . rawurlencode( $tracking ), array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$entry  = $this->extract_entry( $res, $tracking );
		$events = array();
		foreach ( array( 'activites', 'activités', 'historique', 'history', 'suivi', 'evenements' ) as $k ) {
			if ( isset( $entry[ $k ] ) && is_array( $entry[ $k ] ) ) {
				$events = $entry[ $k ];
				break;
			}
		}

		return $this->tracking_from_parts(
			$events,
			isset( $entry['id_situation'] ) ? $entry['id_situation'] : 0,
			$this->first_str( $entry, array( 'situation', 'statut', 'status', 'etat', 'last_status' ) )
		);
	}

	/**
	 * Assemble la structure de suivi normalisée à partir des deux sources d'E-com :
	 * l'historique (la chronologie) et la situation du colis (le statut courant).
	 *
	 * Factorisé parce que l'appel unitaire (`GET /colis/{tracking}`) et l'appel par
	 * lot (`POST /colis/statuts` + `POST /colis/historique`) doivent produire
	 * EXACTEMENT le même résultat — sans quoi un colis changerait de statut selon
	 * qu'il a été rafraîchi par le cron ou à la main.
	 *
	 * @param array  $events         Événements bruts de l'historique.
	 * @param int    $id_situation   Situation du colis (entier stable, fait foi).
	 * @param string $situation_label Libellé de la situation (repli + affichage).
	 * @return array Cf. AOD_Carrier::get_tracking().
	 */
	protected function tracking_from_parts( $events, $id_situation, $situation_label ) {
		$history = array();
		foreach ( (array) $events as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			$label = $this->first_str( $e, array( 'situation', 'statut', 'status', 'event', 'commentaire', 'libelle' ) );
			$date  = $this->first_str( $e, array( 'date', 'date_situation', 'created_at', 'datetime', 'time' ) );
			$loc   = $this->first_str( $e, array( 'commune', 'wilaya', 'centre', 'bureau' ) );
			$history[] = array(
				'ts'       => $date ? (int) strtotime( $date ) : 0,
				'date'     => $date,
				'status'   => $this->status_from_label( $label ),
				'label'    => $label,
				'location' => $loc,
			);
		}

		$current = trim( (string) $situation_label );
		if ( empty( $history ) && '' !== $current ) {
			$history[] = array( 'ts' => 0, 'date' => '', 'status' => $this->status_from_label( $current ), 'label' => $current, 'location' => '' );
		}

		$out = $this->build_tracking( $history );

		// Le statut courant fait foi au niveau du COLIS, pas de son dernier
		// événement (cf. status_from_situation_id : un colis annulé finit sur
		// « Au Bureau »). `id_situation` d'abord — un entier stable ; à défaut le
		// libellé ; sinon on garde ce que l'historique a déduit.
		$by_id = self::status_from_situation_id( $id_situation );
		if ( '' !== $by_id ) {
			$out['status']    = $by_id;
			$out['raw_label'] = '' !== $current ? $current : $out['raw_label'];
		} elseif ( '' !== $current ) {
			$guess = $this->status_from_label( $current );
			if ( self::ST_UNKNOWN !== $guess ) {
				$out['status']    = $guess;
				$out['raw_label'] = $current;
			}
		}

		return $out;
	}

	/**
	 * E-com sait renvoyer l'historique de 40 colis en un appel (`POST /colis/historique`).
	 *
	 * C'est la borne LA PLUS BASSE des deux endpoints utilisés par `get_tracking_bulk()`
	 * (`/colis/statuts` accepte 100, `/colis/historique` seulement 40) : on s'aligne
	 * sur la plus stricte pour n'envoyer qu'une taille de lot.
	 *
	 * @return int
	 */
	public function bulk_tracking_limit() {
		return 40;
	}

	/**
	 * Suivi de plusieurs colis en 2 appels au lieu de N.
	 *
	 * E-com n'expose pas le statut ET l'historique dans le même endpoint de masse :
	 *   - `POST /colis/statuts`    → situation + `id_situation` (le statut qui fait foi) ;
	 *   - `POST /colis/historique` → la chronologie (nécessaire au délai de livraison
	 *                                et à la détection des colis bloqués).
	 * On appelle donc les deux, et on refuse d'écrire si l'un des deux échoue : mieux
	 * vaut réessayer au prochain passage que d'enregistrer un statut sans historique
	 * (ce qui écraserait la chronologie déjà connue de la commande).
	 *
	 * 40 colis = 2 appels réseau au lieu de 40.
	 *
	 * @param string[] $trackings
	 * @return array [ tracking => array|WP_Error ]
	 */
	public function get_tracking_bulk( $trackings ) {
		$list = array();
		foreach ( (array) $trackings as $t ) {
			$t = trim( (string) $t );
			if ( '' !== $t ) {
				$list[ $t ] = true;
			}
		}
		$list = array_keys( $list );
		$out  = array();
		if ( empty( $list ) ) {
			return $out;
		}
		if ( ! $this->is_configured() ) {
			$err = new WP_Error( 'aod_ecomdz_no_creds', __( 'Identifiants E-com Delivery manquants (KEY / Token).', 'aod-cod-form' ) );
			foreach ( $list as $t ) {
				$out[ $t ] = $err;
			}
			return $out;
		}

		foreach ( array_chunk( $list, $this->bulk_tracking_limit() ) as $chunk ) {
			$body = wp_json_encode( array( 'trackings' => array_values( $chunk ) ) );

			$st = $this->remote( 'POST', self::API_BASE . 'colis/statuts', array(
				'headers' => $this->headers(),
				'body'    => $body,
				'timeout' => 20,
			) );
			$hi = $this->remote( 'POST', self::API_BASE . 'colis/historique', array(
				'headers' => $this->headers(),
				'body'    => $body,
				'timeout' => 20,
			) );

			// Un lot en échec ne fait pas tomber les autres : on marque CE lot en erreur.
			$fail = is_wp_error( $st ) ? $st : ( is_wp_error( $hi ) ? $hi : null );
			if ( $fail ) {
				foreach ( $chunk as $t ) {
					$out[ $t ] = $fail;
				}
				continue;
			}

			$statuts = array();
			if ( isset( $st['trackings'] ) && is_array( $st['trackings'] ) ) {
				foreach ( $st['trackings'] as $row ) {
					if ( is_array( $row ) && ! empty( $row['tracking'] ) ) {
						$statuts[ (string) $row['tracking'] ] = $row;
					}
				}
			}
			$histos = array();
			if ( isset( $hi['resultats'] ) && is_array( $hi['resultats'] ) ) {
				foreach ( $hi['resultats'] as $row ) {
					if ( is_array( $row ) && ! empty( $row['tracking'] ) ) {
						$histos[ (string) $row['tracking'] ] = $row;
					}
				}
			}

			foreach ( $chunk as $t ) {
				$s = isset( $statuts[ $t ] ) ? $statuts[ $t ] : null;
				if ( ! is_array( $s ) || empty( $s['trouve'] ) ) {
					$out[ $t ] = new WP_Error(
						'aod_ecomdz_not_found',
						/* translators: %s : numéro de suivi. */
						sprintf( __( 'Colis %s introuvable chez E-com Delivery.', 'aod-cod-form' ), $t )
					);
					continue;
				}
				$h = ( isset( $histos[ $t ]['historique'] ) && is_array( $histos[ $t ]['historique'] ) )
					? $histos[ $t ]['historique']
					: array();
				$out[ $t ] = $this->tracking_from_parts(
					$h,
					isset( $s['id_situation'] ) ? $s['id_situation'] : 0,
					isset( $s['situation'] ) ? (string) $s['situation'] : ''
				);
			}
		}

		return $out;
	}
}
