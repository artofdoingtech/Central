<?php
/**
 * Classe de base des transporteurs (livreurs).
 *
 * Chaque livreur (Yalidine, Noest, Ecotrack…) hérite de cette classe et
 * implémente son authentification, ses champs de réglages et la création
 * de colis. L'orchestrateur AOD_Shipping gère l'UI commune.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AOD_Carrier {

	/* Métadonnées de commande partagées par tous les livreurs. */
	const META_CARRIER   = '_aod_ship_carrier';
	const META_TRACKING  = '_aod_ship_tracking';
	const META_LABEL     = '_aod_ship_label';
	const META_IMPORT_ID = '_aod_ship_import_id';
	const META_STOPDESK  = '_aod_ship_stopdesk';
	const META_ERROR     = '_aod_ship_error';

	/**
	 * Note du confirmateur pour le LIVREUR (Phase 47.3).
	 *
	 * 🚨 POURQUOI CETTE CONSTANTE VIT ICI ET PAS DANS `aod-client-dashboard`.
	 * Elle est SAISIE dans `/gestion` — d'où son préfixe `_aod_cd_` — mais elle est
	 * LUE ici, par la charge utile envoyée au transporteur. Or la dépendance entre
	 * les deux plugins va de `aod-client-dashboard` VERS `aod-cod-form`, jamais
	 * l'inverse (règle écrite en toutes lettres dans `class-aod-cod-icon.php`,
	 * Phase 32.1) : `aod-cod-form` ne référence aujourd'hui AUCUNE classe
	 * `AOD_CD_*`. Un `AOD_CD_Section_Orders::META_NOTE` lu depuis ici serait la
	 * première inversion — et casserait tout clone où seul `aod-cod-form` tourne.
	 * Le préfixe dit qui l'écrit ; la classe dit qui la lit.
	 */
	const META_DRIVER_NOTE = '_aod_cd_confirmer_note';

	/**
	 * Longueur maximale de cette note, en CARACTÈRES (pas en octets).
	 *
	 * 🚨 CE NOMBRE EST UNE HYPOTHÈSE ASSUMÉE, PAS UNE MESURE. Aucun transporteur ne
	 * documente la limite de son champ `remarque` / `note_to_driver` ; 255 est repris
	 * du seul plafond tiers déjà présent dans le dépôt (E-com Delivery clippe son
	 * champ `article` à 255, `class-aod-carrier-procolis.php`).
	 *
	 * ⚠️ Et le choix « caractères » a un coût qu'il faut connaître : si la limite
	 * réelle d'une API est en OCTETS, 255 caractères arabes font ~470 octets et
	 * seront refusés. Le choix inverse (clipper à 255 octets) ferait mentir le
	 * compteur du champ en arabe — un défaut SILENCIEUX, dans une langue que ce
	 * template supporte, donc pire. À revoir si un transporteur rejette une note.
	 *
	 * Un seul nombre, deux clips : ici (`driver_note()`) et à l'enregistrement dans
	 * `/gestion`, qui LIT cette constante. Ce n'est pas la dette « deux sources » —
	 * clipper une chaîne déjà clippée ne fait rien, et le NOMBRE reste unique.
	 */
	const NOTE_MAX = 255;

	/* Suivi du colis (évolution récupérée chez le livreur). */
	const META_STATUS         = '_aod_ship_status';          // Clé de statut normalisée (cf. ST_*).
	const META_STATUS_LABEL   = '_aod_ship_status_label';    // Libellé brut du dernier statut renvoyé par le livreur.
	const META_STATUS_HISTORY = '_aod_ship_status_history';  // Historique JSON des événements de suivi.
	const META_STATUS_AT      = '_aod_ship_status_at';        // Timestamp (UNIX) de la dernière synchronisation.

	/**
	 * Timeout HTTP par défaut de tout appel transporteur, en secondes (32.4, D23).
	 *
	 * Les API DZ répondent en < 2 s : un appel qui dépasse 10 s est déjà une panne,
	 * et l'ancien défaut de 30 s ne rattrapait aucun appel — il ne faisait
	 * qu'allonger l'attente pendant la panne, jusqu'à transformer une
	 * synchronisation de suivi en timeout PHP. Un livreur au besoin particulier
	 * passe son propre 'timeout' dans $args (Yalidine et Procolis le font déjà).
	 */
	const HTTP_TIMEOUT = 10;

	/* ---- Chronomètre réseau (instrumentation du cadrage D27) --------------------
	 *
	 * 🚨 POURQUOI CES TROIS COMPTEURS EXISTENT. `remote()` ne journalisait QUE les
	 * échecs, et aucune durée : personne — ni l'owner, ni la ROADMAP — ne savait
	 * combien de temps un appel transporteur prend réellement. D27 a donc vécu des
	 * mois en annonçant « jusqu'à 30 s » alors que le plafond était retombé à 10 s
	 * en Phase 32.4. *Un chiffre qu'on ne mesure pas finit toujours par mentir.*
	 *
	 * STATIQUES à dessein : une opération peut enchaîner plusieurs appels sur
	 * plusieurs instances (résolution du bureau stop-desk PUIS création du colis).
	 * C'est le CUMUL qui intéresse — c'est lui que le confirmateur attend.
	 *
	 * On ne journalise PAS ici : la synchro de suivi enchaîne jusqu'à 240 appels
	 * par passage, et une ligne par requête noierait le signal. L'appelant décide
	 * quand rendre compte (précédent 32.4 : un passage journalisé, pas un colis).
	 */

	/** @var int Durée du DERNIER appel réseau, en millisecondes. */
	protected static $derniere_duree_ms = 0;

	/** @var int Cumul des durées réseau depuis le dernier `chrono_reset()`, en ms. */
	protected static $cumul_duree_ms = 0;

	/** @var int Nombre d'appels réseau depuis le dernier `chrono_reset()`. */
	protected static $cumul_appels = 0;

	/** Remet le chronomètre à zéro — à appeler AVANT l'opération qu'on veut mesurer. */
	public static function chrono_reset() {
		self::$cumul_duree_ms = 0;
		self::$cumul_appels   = 0;
	}

	/**
	 * Ce que le réseau a coûté depuis le dernier `chrono_reset()`.
	 *
	 * @return array{ms:int,appels:int}
	 */
	public static function chrono() {
		return array( 'ms' => (int) self::$cumul_duree_ms, 'appels' => (int) self::$cumul_appels );
	}

	/* ---- Vocabulaire de statut de suivi normalisé (commun à tous les livreurs) ---- */
	const ST_UNKNOWN          = 'unknown';           // Indéterminé (pas encore de suivi ou statut non reconnu).
	const ST_CREATED          = 'created';           // Colis créé / enregistré, pas encore pris en charge.
	const ST_IN_TRANSIT       = 'in_transit';        // Ramassé / au centre / en transit.
	const ST_OUT_FOR_DELIVERY = 'out_for_delivery';  // Sorti en livraison (le livreur est en route).
	const ST_DELIVERED        = 'delivered';         // Livré au client (terminal).
	const ST_RETURNED         = 'returned';          // Retourné à l'expéditeur (terminal).
	const ST_FAILED           = 'failed';            // Tentative échouée / incident (transitoire).
	const ST_CANCELLED        = 'cancelled';         // Annulé (terminal).

	/* ---- À implémenter par chaque livreur ---- */

	/** Identifiant machine (ex. 'yalidine'). */
	abstract public function id();

	/** Nom affiché (ex. 'Yalidine'). */
	abstract public function label();

	/** Valeurs de réglages par défaut. */
	abstract protected function defaults();

	/** L'API est-elle configurée (clés présentes) ? */
	abstract public function is_configured();

	/** Affiche les lignes de réglages (dans le <form> commun, noms préfixés par l'id). */
	abstract public function render_settings_fields();

	/** Nettoie/valide les réglages soumis et renvoie le tableau à sauvegarder. */
	abstract public function sanitize_settings( $input );

	/** Construit la charge utile du colis (utile pour debug/preview). */
	abstract public function build_payload( $order );

	/**
	 * Crée le colis chez le livreur.
	 *
	 * @param WC_Order $order
	 * @return array|WP_Error [ 'tracking'=>, 'label'=>, 'import_id'=> ] ou WP_Error.
	 */
	abstract public function create_parcel( $order );

	/* ---- Comportements par défaut (surchargeables) ---- */

	/** Le livreur gère-t-il le stop-desk (point relais) ? */
	public function supports_stopdesk() {
		return false;
	}

	/**
	 * Plafond du montant COD (à encaisser) accepté par le livreur, en DZD.
	 *
	 * La plupart des transporteurs algériens refusent un colis dont le montant à
	 * encaisser dépasse un certain seuil (souvent 150 000 DZD). 0 = aucune limite
	 * connue (pas de contrôle).
	 *
	 * @return int
	 */
	public function cod_limit() {
		return 0;
	}

	/**
	 * Couleur de marque (pastille/icône). Surchargée par chaque livreur.
	 *
	 * @return string Code couleur hexadécimal (#RRGGBB).
	 */
	public function brand_color() {
		return '#64748b';
	}

	/**
	 * Encre lisible (noir ou blanc) POSÉE SUR la couleur de marque.
	 *
	 * Le monogramme ne peut pas être blanc en dur : chaque transporteur déclare SA
	 * couleur — et les 22 white-labels EcoTrack en déclarent 22 autres, hors de
	 * notre contrôle. Sur l'ambre (#f59e0b) le blanc ne donne que 2,15:1, très
	 * loin du seuil AA. On retient donc, entre noir et blanc, l'encre au plus fort
	 * ratio WCAG : le meilleur des deux vaut toujours ≥ 4,58:1, quelle que soit la
	 * couleur — un futur white-label ne peut pas rendre sa pastille illisible.
	 *
	 * @return string '#000000' ou '#ffffff'.
	 */
	public function brand_ink() {
		$hex = ltrim( (string) $this->brand_color(), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return '#ffffff'; // Couleur illisible : on garde l'ancien défaut.
		}

		$lin = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v     = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$lin[] = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		$lum = 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];

		// Ratio WCAG = (Lclair + 0.05) / (Lfoncé + 0.05).
		$on_white = 1.05 / ( $lum + 0.05 );
		$on_black = ( $lum + 0.05 ) / 0.05;

		return ( $on_black >= $on_white ) ? '#000000' : '#ffffff';
	}

	/**
	 * Initiales affichées dans la pastille (1 à 3 lettres).
	 *
	 * Par défaut : déduites du libellé (ex. « Speed Delivery » → « SD »).
	 *
	 * @return string
	 */
	public function initials() {
		$words = preg_split( '/[\s\-]+/', (string) $this->label(), -1, PREG_SPLIT_NO_EMPTY );
		if ( empty( $words ) ) {
			return '?';
		}
		if ( 1 === count( $words ) ) {
			return strtoupper( substr( $words[0], 0, 2 ) );
		}
		return strtoupper( substr( $words[0], 0, 1 ) . substr( $words[1], 0, 1 ) );
	}

	/**
	 * Teinte d'appui du dégradé de la pastille.
	 *
	 * 🚨 ELLE S'ÉLOIGNE TOUJOURS DE L'ENCRE, ET C'EST TOUT L'INTÉRÊT. `brand_ink()`
	 * choisit le noir ou le blanc en mesurant le contraste SUR LA COULEUR PLEINE.
	 * Un dégradé introduit une seconde couleur sous le même monogramme : l'assombrir
	 * systématiquement ferait tomber l'encre NOIRE — celle retenue sur les marques
	 * claires, l'ambre EcoTrack en tête — sous le seuil qu'on venait de garantir,
	 * et personne ne l'aurait vu puisque le calcul, lui, resterait juste.
	 * On décale donc dans le sens opposé à l'encre : assombri quand l'encre est
	 * blanche, éclairci quand elle est noire. Le contraste ne peut que monter.
	 * Gardé par `tests/unit/test-carrier-emblemes.php` R3.
	 *
	 * @return string Code couleur hexadécimal (#RRGGBB).
	 */
	public function brand_shade() {
		$hex = ltrim( (string) $this->brand_color(), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return $this->brand_color(); // Couleur illisible : pas de dégradé, pas de risque.
		}
		$vers_le_noir = ( '#ffffff' === $this->brand_ink() );
		$out          = '#';
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v    = hexdec( substr( $hex, $i, 2 ) );
			$v    = $vers_le_noir ? (int) round( $v * 0.66 ) : (int) round( $v + ( 255 - $v ) * 0.34 );
			$out .= str_pad( dechex( max( 0, min( 255, $v ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	/**
	 * Nom de l'emblème du transporteur au catalogue `AOD_Carrier_Emblem`.
	 *
	 * '' = pas d'emblème, la pastille garde son monogramme. C'est le défaut, et
	 * c'est un défaut SÛR : un transporteur ajouté demain sans emblème s'affiche
	 * correctement, il ne porte simplement pas de dessin. Lire l'en-tête de
	 * `class-aod-carrier-emblem.php` avant d'en inventer un.
	 *
	 * @return string
	 */
	public function emblem() {
		return '';
	}

	/**
	 * Pastille-icône HTML du livreur : emblème, ou monogramme à défaut.
	 *
	 * ⚠️ `aria-hidden` sur la pastille ENTIÈRE, monogramme compris. Le nom du
	 * transporteur est le texte immédiatement à côté : jusqu'ici un lecteur
	 * d'écran annonçait « Y A Yalidine », les initiales n'apportant rien qui ne
	 * suive dans la même cellule.
	 *
	 * @return string HTML sûr (déjà échappé).
	 */
	public function icon_html() {
		$emb = AOD_Carrier_Emblem::svg( $this->emblem() );

		return '<span class="aod-carrier-ic" aria-hidden="true" style="--ic:' . esc_attr( $this->brand_color() )
			. ';--ic-2:' . esc_attr( $this->brand_shade() )
			. ';--ic-ink:' . esc_attr( $this->brand_ink() ) . '">'
			// Le SVG vient du catalogue fermé de CE dépôt, jamais d'une saisie.
			. ( '' !== $emb ? $emb : esc_html( $this->initials() ) )
			. '</span>';
	}

	/**
	 * Liste des centres/points relais d'une wilaya.
	 *
	 * @param int $wilaya_code 1-58.
	 * @return array|WP_Error Liste [ ['id'=>, 'name'=>], ... ].
	 */
	public function get_centers( $wilaya_code ) {
		return array();
	}

	/**
	 * Communes d'une wilaya qui disposent réellement d'un point relais (stop-desk),
	 * sous forme de clés normalisées (cf. normalisation propre à chaque livreur).
	 *
	 * Sert au formulaire public à masquer l'option « bureau » là où elle n'existe
	 * pas. Par défaut, le livreur ne filtre PAS par commune (stop-desk géré au
	 * niveau wilaya/station, ou non géré) : on renvoie null = « ne pas filtrer ».
	 *
	 * @param int $wilaya_code 1-58.
	 * @return string[]|null Clés normalisées des communes avec bureau, ou null si
	 *                       le filtrage par commune ne s'applique pas / indéterminé.
	 */
	public function desk_communes( $wilaya_code ) {
		return null;
	}

	/**
	 * Détermine le bureau stop-desk d'une commande SANS demander à l'acheteur.
	 *
	 * Le formulaire public ne propose plus de liste de bureaux : pour une commande
	 * « bureau », le point de retrait est déduit de la wilaya + commune de la
	 * commande. Implémentation par défaut : le premier bureau de la wilaya. Les
	 * livreurs qui exposent la commune de chaque bureau (E-com Delivery…) surchargent
	 * pour choisir le bureau de la commune de l'acheteur (repli : premier bureau).
	 *
	 * @param WC_Order $order
	 * @return string Code du bureau, ou '' si la wilaya n'a aucun bureau.
	 */
	public function resolve_stopdesk_code( $order ) {
		$centers = $this->get_centers( (int) $order->get_meta( '_aod_wilaya_code' ) );
		if ( is_array( $centers ) && ! empty( $centers ) && isset( $centers[0]['id'] ) ) {
			return (string) $centers[0]['id'];
		}
		return '';
	}

	/**
	 * Teste la connexion à l'API du transporteur (vérification « en direct »).
	 *
	 * Effectue un appel léger en lecture seule pour confirmer que les identifiants
	 * saisis sont réellement acceptés par le livreur. Implémentation par défaut :
	 * pas d'endpoint de vérification dédié → on ne confirme que la présence des
	 * identifiants (live = false). Les livreurs qui exposent un point d'accès léger
	 * (Procolis /token, Yalidine /wilayas, EcoTrack get/communes, Maystro
	 * base/commune) surchargent cette méthode pour un vrai test « en direct ».
	 *
	 * @return array|WP_Error array( 'live' => bool, 'message' => string ) :
	 *                        live = true si vérifié auprès de l'API, false si seuls
	 *                        les identifiants sont présents (pas de test en direct) ;
	 *                        WP_Error si non configuré ou si l'API a refusé.
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aod_carrier_not_configured', __( 'Identifiants du transporteur manquants.', 'aod-cod-form' ) );
		}
		return array( 'live' => false, 'message' => '' );
	}

	/* ---- Suivi du colis (évolution) ---- */

	/**
	 * Le livreur expose-t-il une API de suivi (lecture de l'évolution du colis) ?
	 *
	 * Par défaut non : la page « Suivi commande » retombe alors sur le statut local.
	 * Les livreurs qui exposent un endpoint de suivi surchargent cette méthode et
	 * `get_tracking()`.
	 *
	 * @return bool
	 */
	public function supports_tracking() {
		return false;
	}

	/**
	 * Récupère l'évolution d'un colis chez le livreur.
	 *
	 * Chaque livreur mappe la réponse de SON API vers le vocabulaire normalisé
	 * (ST_*) pour que l'interface affiche la même chronologie quel que soit le
	 * transporteur. Implémentation par défaut : suivi non disponible.
	 *
	 * @param string $tracking Numéro de suivi (colis id).
	 * @return array|WP_Error {
	 *     @type string $status    Clé de statut normalisée (cf. ST_*).
	 *     @type string $raw_label Libellé brut du dernier statut renvoyé par le livreur.
	 *     @type array  $history   Événements, du plus récent au plus ancien :
	 *                             [ [ 'ts'=>int, 'date'=>string, 'status'=>string,
	 *                                 'label'=>string, 'location'=>string ], … ].
	 * }
	 */
	public function get_tracking( $tracking ) {
		return new WP_Error( 'aod_tracking_unsupported', sprintf(
			/* translators: %s : nom du livreur */
			__( 'Le suivi détaillé n’est pas disponible pour %s.', 'aod-cod-form' ),
			$this->label()
		) );
	}

	/**
	 * Nombre de colis que ce livreur sait interroger en UN SEUL appel réseau.
	 *
	 * 1 (défaut) = pas de suivi par lot : un appel par colis. Un livreur qui expose
	 * un endpoint « statuts en masse » renvoie ici la borne de son API — c'est ce
	 * qui permet à la synchronisation de traiter beaucoup plus de colis par passage
	 * sans multiplier les appels (cf. `AOD_Ship_Tracking::sync_carrier()`).
	 *
	 * @return int ≥ 1.
	 */
	public function bulk_tracking_limit() {
		return 1;
	}

	/**
	 * Suivi de PLUSIEURS colis d'un coup.
	 *
	 * Implémentation par défaut : une boucle sur `get_tracking()` — un appel par
	 * colis. Les livreurs qui savent faire mieux (E-com Delivery) la surchargent.
	 * Le contrat ne change pas : l'appelant reçoit, pour chaque numéro demandé, soit
	 * la structure de `get_tracking()`, soit un WP_Error — et une entrée en erreur
	 * n'empêche jamais les autres d'aboutir.
	 *
	 * @param string[] $trackings Numéros de suivi.
	 * @return array [ (string) tracking => array|WP_Error ].
	 */
	public function get_tracking_bulk( $trackings ) {
		$out = array();
		foreach ( (array) $trackings as $t ) {
			$t = trim( (string) $t );
			if ( '' === $t || isset( $out[ $t ] ) ) {
				continue;
			}
			$out[ $t ] = $this->get_tracking( $t );
		}
		return $out;
	}

	/* ---- Tarifs de livraison (import depuis l'API du livreur) ---- */

	/**
	 * Le livreur expose-t-il une API de tarification (frais Domicile/Stop-desk) ?
	 *
	 * Par défaut non : les tarifs restent saisis à la main dans « Livraison COD ».
	 * Les livreurs dont l'API renvoie les frais (Yalidine, ZR/Procolis, EcoTrack)
	 * surchargent cette méthode et `get_fees()`. Sert à n'afficher le bouton
	 * d'import (étape 4.3) que pour les transporteurs concernés.
	 *
	 * @return bool
	 */
	public function supports_fees() {
		return false;
	}

	/**
	 * Récupère les tarifs de livraison chez le livreur, normalisés par wilaya.
	 *
	 * L'import est une **commodité** : la saisie manuelle reste le repli, et cette
	 * méthode est **en lecture seule** (elle n'écrit jamais l'option
	 * `aod_cod_delivery_prices`). L'application des tarifs (prévisualisation +
	 * marge + validation) est faite par l'UI d'import (étape 4.3).
	 *
	 * @param bool $force Ignorer un éventuel cache et réinterroger l'API.
	 * @return array|WP_Error Tableau normalisé
	 *                        `[ (int) wilaya_id => [ 'home' => (int), 'desk' => (int) ] ]`
	 *                        (frais en DZD entiers ; `desk` = 0 si la wilaya n'a pas
	 *                        de stop-desk), ou WP_Error si non supporté / API en échec.
	 */
	public function get_fees( $force = false ) {
		return new WP_Error( 'aod_fees_unsupported', sprintf(
			/* translators: %s : nom du livreur */
			__( 'L’import automatique des tarifs n’est pas disponible pour %s.', 'aod-cod-form' ),
			$this->label()
		) );
	}

	/**
	 * Réduit une **liste de lignes par wilaya** (une entrée = une wilaya) au format
	 * normalisé de `get_fees()`. Sert aux transporteurs dont l'API renvoie tous les
	 * tarifs en un seul appel (E-com Delivery `/tarifs`, EcoTrack `get/fees`) — par
	 * opposition au modèle « par commune » de Yalidine.
	 *
	 * Pour chaque ligne : l'id wilaya est lu via la 1ʳᵉ clé candidate présente (borné
	 * `1..58`), `home`/`desk` idem ; la ligne est ignorée si la wilaya est hors bornes
	 * ou si `home` **et** `desk` sont ≤ 0 ; les valeurs négatives sont ramenées à 0.
	 *
	 * Isolé (sans réseau) pour être testable via `wp eval` sur un JSON simulé.
	 *
	 * @param array    $rows      Liste de lignes (tableaux associatifs).
	 * @param string[] $id_keys   Clés candidates pour le code wilaya.
	 * @param string[] $home_keys Clés candidates pour le tarif Domicile.
	 * @param string[] $desk_keys Clés candidates pour le tarif Stop-desk.
	 * @return array [ (int) wilaya_id => [ 'home'=>int, 'desk'=>int ] ].
	 */
	protected function normalize_wilaya_fees( $rows, $id_keys, $home_keys, $desk_keys ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$wid = (int) $this->first_str( $row, $id_keys );
			if ( $wid < 1 || $wid > 58 ) {
				continue;
			}
			$home = (int) $this->first_str( $row, $home_keys );
			$desk = (int) $this->first_str( $row, $desk_keys );
			if ( $home <= 0 && $desk <= 0 ) {
				continue;
			}
			$out[ $wid ] = array(
				'home' => max( 0, $home ),
				'desk' => max( 0, $desk ),
			);
		}
		return $out;
	}

	/**
	 * Statuts de suivi « terminaux » (plus besoin de resynchroniser).
	 *
	 * @return string[]
	 */
	public static function tracking_terminal_statuses() {
		return array( self::ST_DELIVERED, self::ST_RETURNED, self::ST_CANCELLED );
	}

	/**
	 * Libellé (traduit) + couleur associés à un statut de suivi normalisé.
	 *
	 * Exposé en statique pour que le tableau de bord gérant affiche exactement les
	 * mêmes libellés/couleurs sans dupliquer la table.
	 *
	 * @param string $status Clé normalisée (cf. ST_*).
	 * @return array [ 'label' => string, 'color' => '#RRGGBB' ].
	 */
	public static function tracking_status_meta( $status ) {
		$map = array(
			self::ST_CREATED          => array( 'label' => __( 'Colis créé', 'aod-cod-form' ),            'color' => '#64748b' ),
			self::ST_IN_TRANSIT       => array( 'label' => __( 'En transit', 'aod-cod-form' ),            'color' => '#2563eb' ),
			self::ST_OUT_FOR_DELIVERY => array( 'label' => __( 'En cours de livraison', 'aod-cod-form' ), 'color' => '#d97706' ),
			self::ST_DELIVERED        => array( 'label' => __( 'Livré', 'aod-cod-form' ),                 'color' => '#16a34a' ),
			self::ST_RETURNED         => array( 'label' => __( 'Retourné', 'aod-cod-form' ),              'color' => '#dc2626' ),
			self::ST_FAILED           => array( 'label' => __( 'Tentative échouée', 'aod-cod-form' ),     'color' => '#ea580c' ),
			self::ST_CANCELLED        => array( 'label' => __( 'Annulé', 'aod-cod-form' ),                'color' => '#6b7280' ),
			self::ST_UNKNOWN          => array( 'label' => __( 'Statut inconnu', 'aod-cod-form' ),        'color' => '#9ca3af' ),
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : $map[ self::ST_UNKNOWN ];
	}

	/**
	 * Devine la clé de statut normalisée à partir d'un libellé texte du livreur.
	 *
	 * Beaucoup d'API renvoient un libellé (FR/AR/EN) plutôt qu'un code stable :
	 * ce repli par mots-clés couvre les formulations courantes des transporteurs DZ.
	 *
	 * @param string $text Libellé brut du statut/événement.
	 * @return string Clé normalisée (ST_*).
	 */
	protected function guess_status( $text ) {
		$t = strtolower( remove_accents( (string) $text ) );
		if ( '' === trim( $t ) ) {
			return self::ST_UNKNOWN;
		}
		// Ordre important : les statuts terminaux/spécifiques passent avant « transit ».
		$rules = array(
			self::ST_DELIVERED        => array( 'livre', 'delivered', 'remis', 'recu par', 'تم التسليم', 'سلمت', 'تسليم' ),
			self::ST_RETURNED         => array( 'retour', 'returned', 'renvoye', 'مرتجع', 'راجع', 'ارجاع' ),
			self::ST_CANCELLED        => array( 'annule', 'cancel', 'supprime', 'ملغى', 'الغاء', 'ملغاة' ),
			self::ST_FAILED           => array( 'echec', 'tentative', 'failed', 'reporte', 'injoignable', 'absent', 'refuse', 'probleme', 'فشل', 'تعذر', 'مؤجل' ),
			self::ST_OUT_FOR_DELIVERY => array( 'sortie', 'sorti', 'en livraison', 'out for delivery', 'en cours de livraison', 'dispatch', 'en route', 'خرج', 'قيد التوصيل', 'للتوزيع' ),
			self::ST_IN_TRANSIT       => array( 'transit', 'centre', 'hub', 'ramass', 'collect', 'expedi', 'pickup', 'picked', 'reception', 'recu', 'entrepot', 'traitement', 'في الطريق', 'مركز', 'استلم المركز', 'قيد المعالجة' ),
			self::ST_CREATED          => array( 'cree', 'creation', 'created', 'pret', 'enregistr', 'nouveau', 'pending', 'en attente', 'valide', 'أنشئ', 'جديد', 'قيد الانتظار' ),
		);
		foreach ( $rules as $key => $needles ) {
			foreach ( $needles as $n ) {
				if ( false !== strpos( $t, $n ) ) {
					return $key;
				}
			}
		}
		return self::ST_UNKNOWN;
	}

	/**
	 * Construit la structure de suivi normalisée à partir d'une liste d'événements.
	 *
	 * Trie du plus récent au plus ancien (par timestamp si disponible), déduit le
	 * statut courant du dernier événement, et renvoie le contrat attendu par
	 * `get_tracking()`.
	 *
	 * @param array $history      Événements (cf. get_tracking()).
	 * @param bool  $newest_first Ordre d'entrée si aucun timestamp n'est exploitable
	 *                            (true = déjà du plus récent au plus ancien).
	 * @return array
	 */
	protected function build_tracking( $history, $newest_first = true ) {
		$history = array_values( array_filter( (array) $history, 'is_array' ) );

		$have_ts = false;
		foreach ( $history as $h ) {
			if ( ! empty( $h['ts'] ) ) {
				$have_ts = true;
				break;
			}
		}
		if ( $have_ts ) {
			usort( $history, function ( $a, $b ) {
				$ta = isset( $a['ts'] ) ? (int) $a['ts'] : 0;
				$tb = isset( $b['ts'] ) ? (int) $b['ts'] : 0;
				return $tb <=> $ta;
			} );
		} elseif ( ! $newest_first ) {
			$history = array_reverse( $history );
		}

		$status    = self::ST_UNKNOWN;
		$raw_label = '';
		if ( ! empty( $history ) ) {
			$status    = isset( $history[0]['status'] ) ? $history[0]['status'] : self::ST_UNKNOWN;
			$raw_label = isset( $history[0]['label'] ) ? (string) $history[0]['label'] : '';
		}

		return array(
			'status'    => $status,
			'raw_label' => $raw_label,
			'history'   => array_values( $history ),
		);
	}

	/**
	 * Extrait l'entrée d'un colis dans une réponse d'API indexée par tracking
	 * (ou repli sur `data[tracking]`, `data[0]`, ou le premier élément).
	 *
	 * @param mixed  $res      Réponse décodée.
	 * @param string $tracking Numéro de suivi recherché.
	 * @return array
	 */
	protected function extract_entry( $res, $tracking ) {
		if ( ! is_array( $res ) ) {
			return array();
		}
		if ( isset( $res[ $tracking ] ) && is_array( $res[ $tracking ] ) ) {
			return $res[ $tracking ];
		}
		if ( isset( $res['data'] ) && is_array( $res['data'] ) ) {
			if ( isset( $res['data'][ $tracking ] ) && is_array( $res['data'][ $tracking ] ) ) {
				return $res['data'][ $tracking ];
			}
			$first = reset( $res['data'] );
			if ( is_array( $first ) ) {
				return $first;
			}
		}
		$first = reset( $res );
		return is_array( $first ) ? $first : $res;
	}

	/**
	 * Première valeur scalaire non vide parmi une liste de clés candidates.
	 *
	 * @param array    $arr
	 * @param string[] $keys
	 * @return string
	 */
	protected function first_str( $arr, $keys ) {
		if ( ! is_array( $arr ) ) {
			return '';
		}
		foreach ( (array) $keys as $k ) {
			if ( isset( $arr[ $k ] ) && is_scalar( $arr[ $k ] ) && '' !== (string) $arr[ $k ] ) {
				return (string) $arr[ $k ];
			}
		}
		return '';
	}

	/* ---- Helpers communs ---- */

	/** Clé d'option WordPress des réglages de ce livreur. */
	public function option_key() {
		return 'aod_carrier_' . $this->id();
	}

	/** Réglages enregistrés fusionnés avec les valeurs par défaut. */
	public function settings() {
		$saved = get_option( $this->option_key(), array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $this->defaults() );
	}

	/** Enregistre les réglages. */
	public function save_settings( $data ) {
		update_option( $this->option_key(), $data );
	}

	/**
	 * Requête HTTP générique avec décodage JSON et gestion d'erreurs.
	 *
	 * @param string $method GET/POST…
	 * @param string $url    URL complète.
	 * @param array  $args   Arguments wp_remote_request (headers, body…).
	 * @return array|WP_Error
	 */
	protected function remote( $method, $url, $args = array() ) {
		$args = wp_parse_args( $args, array( 'method' => $method, 'timeout' => self::HTTP_TIMEOUT ) );

		// Instrumentation (cadrage D27, 2026-07-28). On MESURE le temps passé en
		// réseau, faute de quoi personne ne peut arbitrer entre « indolore » et
		// « pénible » — l'énoncé de D27 annonçait « jusqu'à 30 s » alors que le
		// plafond est à 10 s depuis la 32.4 : un chiffre non mesuré finit toujours
		// par mentir. Le compteur est mémorisé pour que l'appelant puisse rendre
		// compte du CUMUL d'une opération (cf. `AOD_Shipping::auto_send()`), sans
		// journaliser chaque requête : la synchro de suivi en enchaîne jusqu'à 240
		// par passage, et une ligne par appel noierait le signal (précédent 32.4,
		// qui journalise UN passage, pas chaque colis).
		$t0 = microtime( true );

		$resp = wp_remote_request( $url, $args );

		self::$derniere_duree_ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		self::$cumul_duree_ms   += self::$derniere_duree_ms;
		++self::$cumul_appels;

		if ( is_wp_error( $resp ) ) {
			// 🔒 `$url` porte le jeton en query arg chez Noest et EcoTrack, et
			// `$args['headers']` porte le Bearer / X-API-TOKEN chez tous les autres.
			// C'est `AOD_COD_Log` qui rend les deux publiables — ne jamais les
			// passer à autre chose. Cf. class-aod-cod-log.php, couches 1 et 2.
			AOD_COD_Log::error( 'carrier', sprintf(
				'%s : appel réseau impossible (%s %s) après %d ms — %s',
				$this->label(),
				$args['method'],
				$url,
				self::$derniere_duree_ms,
				$resp->get_error_message()
			) );
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			if ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$msg = $data['message'];
			} elseif ( is_array( $data ) && isset( $data['error']['message'] ) && '' !== $data['error']['message'] ) {
				// Format { error: { code, message, details } } (ex. E-com Delivery API v2).
				$msg = (string) $data['error']['message'];
			} else {
				$msg = sprintf( __( 'Erreur API %1$s (HTTP %2$d).', 'aod-cod-form' ), $this->label(), $code );
			}
			// Détaille les erreurs de validation (format Laravel { errors: { champ: [..] } })
			// au lieu du résumé tronqué « … (and 1 more error) ».
			if ( is_array( $data ) && ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
				$details = array();
				foreach ( $data['errors'] as $field_errors ) {
					foreach ( (array) $field_errors as $fe ) {
						$details[] = (string) $fe;
					}
				}
				$details = array_values( array_unique( array_filter( $details ) ) );
				if ( ! empty( $details ) ) {
					$msg = implode( ' ', $details );
				}
			}
			// 🔒 ORDRE IMPORTANT : on rédige AVANT de tronquer, jamais l'inverse.
			// Tronquer d'abord couperait un jeton en deux au caractère 500 — et
			// une moitié de jeton n'est plus reconnue par le masquage PAR VALEUR
			// (couche 3), donc elle partirait en clair dans le journal.
			$extrait = AOD_COD_Log::redact_string( (string) $raw );
			if ( strlen( $extrait ) > 500 ) {
				$extrait = substr( $extrait, 0, 500 ) . '…';
			}
			AOD_COD_Log::error( 'carrier', sprintf(
				'%s : HTTP %d sur %s %s — %s',
				$this->label(),
				$code,
				$args['method'],
				$url,
				$msg
			), array( 'reponse' => $extrait ) );

			return new WP_Error( 'aod_carrier_http', $msg, array( 'http' => $code, 'data' => $data, 'raw' => $raw ) );
		}
		return is_array( $data ) ? $data : array( '_raw' => $raw );
	}

	/** Découpe un nom complet en [prénom, nom]. */
	protected function split_name( $full ) {
		$full = trim( preg_replace( '/\s+/', ' ', (string) $full ) );
		if ( '' === $full ) {
			return array( '-', '-' );
		}
		$parts = explode( ' ', $full );
		if ( 1 === count( $parts ) ) {
			return array( $parts[0], $parts[0] );
		}
		$first = array_shift( $parts );
		return array( $first, implode( ' ', $parts ) );
	}

	/** Nom complet du client de la commande. */
	protected function full_name( $order ) {
		return trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
	}

	/**
	 * Description des produits envoyée au livreur : « Nom (Couleur: Rouge, Taille: L) x2, Autre x1 ».
	 *
	 * Les variantes du formulaire COD ne sont pas des variations WooCommerce natives :
	 * la commande porte le produit parent + le choix stocké en meta de ligne
	 * (Couleur, Taille…). On annexe donc ces meta visibles, sinon le livreur ne
	 * reçoit que le nom du parent sans la variante choisie.
	 */
	protected function product_list( $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$label = $item->get_name();
			$meta  = array();
			foreach ( $item->get_formatted_meta_data( '_', true ) as $m ) {
				$key = wp_strip_all_tags( $m->display_key );
				$val = wp_strip_all_tags( $m->display_value );
				if ( '' !== trim( $key ) && '' !== trim( $val ) ) {
					$meta[] = $key . ': ' . trim( $val );
				}
			}
			if ( $meta ) {
				$label .= ' (' . implode( ', ', $meta ) . ')';
			}
			$items[] = $label . ' x' . $item->get_quantity();
		}
		return implode( ', ', $items );
	}

	/** Total de la commande arrondi à l'entier (montant COD à encaisser). */
	protected function order_total( $order ) {
		return (int) round( (float) $order->get_total() );
	}

	/** La commande est-elle en stop-desk ? */
	protected function is_stopdesk_order( $order ) {
		return 'desk' === $order->get_meta( '_aod_delivery_type' );
	}

	/**
	 * Ce transporteur porte-t-il un champ de note au livreur dans sa charge utile ?
	 *
	 * `false` par défaut : un transporteur qui n'a rien surchargé n'a nulle part où
	 * mettre la note, et c'est le cas honnête. Surchargée `true` par les SEULES
	 * implémentations dont `build_payload()` transmet réellement `driver_note()` —
	 * la biconditionnelle est gardée par `tests/unit/test-note-livreur.php` R1, qui
	 * construit les 29 charges utiles et y cherche la note.
	 *
	 * ⚠️ Répondre `true` sans placer la note dans le payload est un MENSONGE VISIBLE
	 * à l'écran : `/gestion` s'appuie là-dessus pour prévenir le confirmateur que sa
	 * consigne ne partira pas (décision D3 de §A.23). R1 rougit dans ce cas.
	 *
	 * @return bool
	 */
	public function supports_driver_note() {
		return false;
	}

	/**
	 * La note du confirmateur, prête à partir : lue, nettoyée, bornée.
	 *
	 * Même patron que `full_name()` / `product_list()` / `order_total()` ci-dessus —
	 * un helper de la classe de base que les `build_payload()` appellent, pour que
	 * la lecture de la méta et le clip n'existent qu'à UN endroit.
	 *
	 * 🚨 Le clip se fait AVANT l'envoi et jamais après : une note trop longue
	 * échouerait côté transporteur, donc APRÈS le verrou d'envoi (31.4) et au milieu
	 * d'un lot — la moitié des colis partis, l'autre non, et rien qui l'explique.
	 *
	 * `mb_substr` avec repli : mbstring est présent partout où WordPress tourne, mais
	 * un `substr` brut couperait un caractère arabe en deux et produirait un UTF-8
	 * invalide que l'API refuserait sans dire pourquoi.
	 *
	 * ⚠️ On ne transforme RIEN d'autre — pas d'aplatissement des retours à la ligne,
	 * pas de reformatage. `sanitize_textarea_field()` a déjà fait le ménage à
	 * l'enregistrement ; en rajouter ici, c'est modifier en silence ce que le
	 * confirmateur a relu à l'écran avant d'envoyer.
	 *
	 * @param WC_Order $order
	 * @return string Chaîne d'au plus self::NOTE_MAX caractères ('' si aucune note).
	 */
	protected function driver_note( $order ) {
		$note = trim( (string) $order->get_meta( self::META_DRIVER_NOTE ) );
		if ( '' === $note ) {
			return '';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $note, 0, self::NOTE_MAX, 'UTF-8' );
		}
		return (string) substr( $note, 0, self::NOTE_MAX );
	}

	/**
	 * Garde-fou : vérifie que le montant COD ne dépasse pas le plafond du livreur,
	 * AVANT tout appel réseau, pour renvoyer un message clair et actionnable plutôt
	 * que l'erreur brute de l'API (« La valeur de montant ne peut être supérieure… »).
	 *
	 * @param WC_Order $order
	 * @return WP_Error|null WP_Error si le plafond est dépassé, sinon null.
	 */
	protected function check_cod_limit( $order ) {
		$limit = (int) $this->cod_limit();
		if ( $limit <= 0 ) {
			return null;
		}
		$amount = $this->order_total( $order );
		if ( $amount <= $limit ) {
			return null;
		}
		return new WP_Error( 'aod_cod_over_limit', sprintf(
			/* translators: 1: montant de la commande, 2: plafond du livreur, 3: nom du livreur */
			__( 'Montant trop élevé : %1$s DZD dépasse le plafond COD de %2$s DZD accepté par %3$s. Réduisez la commande, choisissez un autre livreur, ou envoyez le colis manuellement.', 'aod-cod-form' ),
			number_format_i18n( $amount ),
			number_format_i18n( $limit ),
			$this->label()
		) );
	}
}
