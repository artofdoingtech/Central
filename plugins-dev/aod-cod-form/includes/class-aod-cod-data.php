<?php
/**
 * Données géographiques (wilayas/communes) et tarifs de livraison.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Data {

	/** Clé d'option pour les tarifs de livraison. */
	const OPTION_PRICES = 'aod_cod_delivery_prices';

	/**
	 * Clé d'option pour la sauvegarde des tarifs avant un import (étape 4.3).
	 * Snapshot pris juste avant d'écraser OPTION_PRICES → permet « Restaurer ».
	 */
	const OPTION_PRICES_BACKUP = 'aod_cod_delivery_prices_backup';

	/** Clé d'option pour le seuil de livraison gratuite. */
	const OPTION_FREE_SHIP = 'aod_cod_free_shipping';

	/** @var array|null Cache des wilayas+communes. */
	protected static $places = null;

	/**
	 * Charge le JSON des 58 wilayas + communes.
	 *
	 * @return array Liste : [ ['code'=>1,'name'=>'Adrar','communes'=>[...]], ... ]
	 */
	public static function places() {
		if ( null !== self::$places ) {
			return self::$places;
		}
		$file = AOD_COD_PATH . 'includes/data/dz-places.json';
		$raw  = is_readable( $file ) ? file_get_contents( $file ) : '';
		$data = json_decode( $raw, true );
		self::$places = is_array( $data ) ? $data : array();
		return self::$places;
	}

	/**
	 * Communes d'une wilaya donnée.
	 *
	 * @param int $code Code wilaya (1-58).
	 * @return array[] Liste d'objets [ 'name' => 'Adrar', 'name_ar' => 'أدرار' ].
	 */
	public static function communes( $code ) {
		foreach ( self::places() as $w ) {
			if ( (int) $w['code'] === (int) $code ) {
				return $w['communes'];
			}
		}
		return array();
	}

	/**
	 * Nom latin (FR) d'une wilaya.
	 *
	 * @param int $code
	 * @return string
	 */
	public static function wilaya_name( $code ) {
		foreach ( self::places() as $w ) {
			if ( (int) $w['code'] === (int) $code ) {
				return $w['name'];
			}
		}
		return '';
	}

	/**
	 * Nom arabe d'une wilaya.
	 *
	 * @param int $code
	 * @return string
	 */
	public static function wilaya_name_ar( $code ) {
		foreach ( self::places() as $w ) {
			if ( (int) $w['code'] === (int) $code ) {
				return isset( $w['name_ar'] ) ? $w['name_ar'] : '';
			}
		}
		return '';
	}

	/**
	 * Le site doit-il afficher les noms en arabe ?
	 *
	 * 🚨 ON LIT LA LANGUE, JAMAIS LE SENS D'ÉCRITURE (dette D10, soldée le 2026-08-03).
	 * Cette méthode s'écrivait `… || is_rtl()`. Le raccourci tenait tant que RTL ⇔ arabe,
	 * mais `is_rtl()` répond « le rendu est de droite à gauche », pas « le visiteur lit
	 * l'arabe » : un thème RTL déposé sur un clone **francophone** (TEMPLATE.md §4 n'en
	 * interdit aucun) basculait d'un coup les 58 wilayas, les 1541 communes et tout le
	 * contenu marchand de la page de vente en arabe, sur un site en français — sans qu'aucune
	 * option ne l'ait demandé, et sans que rien ne le signale.
	 *
	 * ⚠️ Le `|| is_rtl()` rendait pourtant UN vrai service, et il est conservé — autrement,
	 * en le retirant sèchement : dans le wp-admin RÉEL, `AOD_COD_Lang::filter_locale()` se
	 * retire volontairement (l'admin reste dans la langue du site), si bien qu'un
	 * administrateur au **profil arabe** avait `get_locale() === 'fr_FR'` et ne devait son
	 * arabe qu'à `is_rtl()`. On le lui rend explicitement, par la langue de son profil,
	 * au lieu de le lui laisser par effet de bord.
	 *
	 * La condition est calquée sur celle de `AOD_COD_Lang::filter_locale()` — même
	 * frontière, même raison : admin-ajax n'est pas l'admin, c'est le visiteur.
	 *
	 * @return bool
	 */
	public static function is_arabic() {
		$locale = ( is_admin() && ! wp_doing_ajax() ) ? get_user_locale() : get_locale();
		return 0 === strpos( (string) $locale, 'ar' );
	}

	/**
	 * Branche les filtres de langue purement chrome de cette classe. Séparé
	 * des méthodes statiques ci-dessus (appelables sans effet de bord) — à
	 * appeler UNE fois au chargement du plugin, cf. `AOD_COD_Lang::init()`
	 * dans aod-cod-form.php pour le même patron.
	 */
	public static function init() {
		add_filter( 'woocommerce_currency_symbol', array( __CLASS__, 'currency_symbol' ), 10, 2 );
	}

	/**
	 * Symbole du dinar algérien : « DA » en français, « د.ج » en arabe (retour
	 * owner, 2026-09-14).
	 *
	 * 🚨 WooCommerce ne connaît qu'UN symbole par devise, câblé en dur dans son
	 * cœur (`includes/wc-core-functions.php`) : pour `DZD`, c'est littéralement
	 * « د.ج » — en arabe — QUELLE QUE SOIT la langue affichée. Un visiteur
	 * francophone lisait donc ses prix suivis de caractères arabes. On ne
	 * touche qu'au cas AR→FR : le symbole arabe natif de Woo reste inchangé
	 * quand `is_arabic()` est vrai (rien à corriger côté arabe), et toute
	 * AUTRE devise que DZD traverse ce filtre sans y être touchée (ce
	 * template ne configure que DZD, cf. `scripts/setup.sh`, mais un clone qui
	 * en changerait ne doit pas hériter d'un « DA » qui n'a plus de sens).
	 *
	 * @param string $symbol   Symbole natif de Woo pour `$currency`.
	 * @param string $currency Code devise (ex. 'DZD').
	 * @return string
	 */
	public static function currency_symbol( $symbol, $currency ) {
		if ( 'DZD' !== $currency || self::is_arabic() ) {
			return $symbol;
		}
		return 'DA';
	}

	/**
	 * Choisit le libellé à afficher (arabe si site arabe, sinon latin).
	 *
	 * @param string $name    Nom latin.
	 * @param string $name_ar Nom arabe.
	 * @return string
	 */
	public static function label( $name, $name_ar ) {
		return ( self::is_arabic() && '' !== (string) $name_ar ) ? $name_ar : $name;
	}

	/**
	 * Récupère le nom arabe d'une commune (chaîne vide si inconnue).
	 *
	 * @param int    $code    Code wilaya.
	 * @param string $commune Nom latin de la commune.
	 * @return string
	 */
	public static function commune_name_ar( $code, $commune ) {
		foreach ( self::communes( $code ) as $c ) {
			if ( isset( $c['name'] ) && $c['name'] === $commune ) {
				return isset( $c['name_ar'] ) ? $c['name_ar'] : '';
			}
		}
		return '';
	}

	/**
	 * Vérifie qu'une commune (nom latin) appartient bien à la wilaya (anti-triche).
	 *
	 * @param int    $code    Code wilaya.
	 * @param string $commune Nom latin de commune.
	 * @return bool
	 */
	public static function commune_valid( $code, $commune ) {
		foreach ( self::communes( $code ) as $c ) {
			if ( isset( $c['name'] ) && $c['name'] === $commune ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Table des tarifs de livraison.
	 *
	 * @return array [ wilaya_code => [ 'home'=>int, 'desk'=>int ] ]
	 */
	public static function prices() {
		$saved = get_option( self::OPTION_PRICES, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Tarif pour une wilaya + un mode de livraison.
	 *
	 * @param int    $code Code wilaya.
	 * @param string $type 'home' (domicile) ou 'desk' (stop-desk).
	 * @return float
	 */
	public static function price_for( $code, $type ) {
		$prices = self::prices();
		$type   = ( 'desk' === $type ) ? 'desk' : 'home';
		if ( isset( $prices[ $code ][ $type ] ) && '' !== $prices[ $code ][ $type ] ) {
			return (float) $prices[ $code ][ $type ];
		}
		return 0.0;
	}

	/**
	 * Réglages de la livraison gratuite (avec valeurs par défaut).
	 *
	 * @return array { 'enabled' => 0|1, 'threshold' => float }
	 */
	public static function free_shipping() {
		$saved = get_option( self::OPTION_FREE_SHIP, array() );
		return wp_parse_args( (array) $saved, array(
			'enabled'   => 0,
			'threshold' => 0,
		) );
	}

	/**
	 * Seuil de livraison gratuite effectif (0 = désactivé).
	 *
	 * @return float Montant de sous-total à atteindre, ou 0 si la gratuité est off.
	 */
	public static function free_shipping_threshold() {
		$s = self::free_shipping();
		return ! empty( $s['enabled'] ) ? (float) $s['threshold'] : 0.0;
	}

	/**
	 * La wilaya est-elle en livraison gratuite permanente ?
	 *
	 * Indépendant du seuil de gratuité : si cette case est cochée pour la wilaya,
	 * la livraison y est toujours offerte, quel que soit le sous-total.
	 *
	 * @param int $code Code wilaya.
	 * @return bool
	 */
	public static function is_free_wilaya( $code ) {
		$prices = self::prices();
		return ! empty( $prices[ $code ]['free'] );
	}

	/**
	 * Frais de livraison effectifs, seuil de gratuité appliqué.
	 *
	 * @param int    $code     Code wilaya.
	 * @param string $type     'home' ou 'desk'.
	 * @param float  $subtotal Sous-total produits de la commande.
	 * @return float 0 si la wilaya est offerte ou si le seuil de gratuité est atteint, sinon le tarif de base.
	 */
	public static function effective_shipping( $code, $type, $subtotal ) {
		if ( self::is_free_wilaya( $code ) ) {
			return 0.0;
		}
		$threshold = self::free_shipping_threshold();
		if ( $threshold > 0 && (float) $subtotal >= $threshold ) {
			return 0.0;
		}
		return self::price_for( $code, $type );
	}
}
