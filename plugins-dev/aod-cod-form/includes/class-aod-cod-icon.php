<?php
/**
 * Icônes vectorielles du PUBLIC — catalogue fermé (Phase 37.2).
 *
 * Jumeau minimal de `AOD_CD_Icon` (`/gestion`), mêmes SVG Phosphor, même
 * discipline : les fichiers sont copiés **verbatim de l'amont** et les
 * attributs de présentation sont **reconstruits ici**, jamais lus du fichier.
 *
 * 🚨 POURQUOI UN JUMEAU ET PAS UN APPEL À `AOD_CD_Icon`. La dépendance entre
 * les deux plugins ne va que dans UN sens : `aod-client-dashboard` a besoin de
 * `aod-cod-form`, jamais l'inverse (cf. `AOD_CD_Cod`, Phase 32.1). Appeler la
 * classe du dashboard depuis le public ferait tomber le panier en erreur
 * fatale dès qu'un gérant désactive `/gestion` — exactement le mode dégradé
 * que la 32.1 a fermé, repris à l'envers.
 *
 * La contrepartie est une DUPLICATION de 3 fichiers SVG entre les deux
 * plugins. Elle est assumée et **gardée** : `tests/unit/test-panier-peau.php`
 * (R6) compare les copies aux originales octet à octet et rougit si elles
 * divergent — comme pour les tokens `--ac-*` / `--sh-*`. La duplication est
 * un choix ; sa dérive silencieuse n'en serait pas un.
 *
 * Catalogue FERMÉ : un nom inconnu ne rend rien et crie en développement. Rien
 * de ce qui est rendu ici ne vient d'une saisie — c'est ce qui rend l'échappement
 * inutile (et impossible : `wp_kses_post()` mange les attributs SVG).
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Icon {

	/** Graisse par défaut — comme la nav de `/gestion` hors élément actif. */
	const DEFAULT_WEIGHT = 'regular';

	/** Taille par défaut, en px (celle de la nav de `/gestion`). */
	const DEFAULT_SIZE = 20;

	/** @var array<string,string> Cache du contenu des fichiers, par graisse/nom. */
	protected static $cache = array();

	/**
	 * Nom d'usage => nom de fichier Phosphor.
	 *
	 * On nomme par l'USAGE (`cart`, `close`) et non par le dessin : le jour où
	 * l'icône change, le nom d'usage reste juste.
	 */
	protected static function catalog() {
		return array(
			'cart'  => 'shopping-cart-simple',
			'close' => 'x',
			// Confirmation d'ajout au panier inline (2026-09-10) — même mapping
			// que `AOD_CD_Icon` côté /gestion, copie octet à octet (R6).
			'check' => 'check-circle',
			// Modes de livraison + réassurance du formulaire COD (Phase 38.3).
			// Ils remplacent 🏠 🏢 🔒 : un emoji est rendu par l'OS, donc trois
			// acheteurs sur trois systèmes ne voyaient pas le même formulaire.
			'home'    => 'house',
			'desk'    => 'storefront',
			'lock'    => 'lock',
			// Réassurance de l'Accueil + coordonnées de Contact (2026-09-15,
			// retour owner) : remplacent 💵 🚚 📞 📍 ✉️ — même raison que ci-dessus,
			// un emoji n'est pas le même visage sur deux systèmes. Chantier
			// « accent public » de docs/ICONES-ET-CHARTE-GESTION.md §6.3, ouvert.
			'money'   => 'money',
			'truck'   => 'truck',
			'phone'   => 'phone',
			'pin'     => 'map-pin',
			'email'   => 'envelope',
			// Pied de page — réseaux sociaux & contact (2026-09-15, retour owner) :
			// [aod_cod_social_links], AOD_COD_Footer. Un seul par réseau, jamais de
			// choix de couleur (rule 1 de aod-cod-pages.css) — le monogramme suffit
			// à identifier la marque, `currentColor` comme tout le reste de ce
			// catalogue.
			'facebook'  => 'facebook-logo',
			'instagram' => 'instagram-logo',
			'tiktok'    => 'tiktok-logo',
			'snapchat'  => 'snapchat-logo',
			'whatsapp'  => 'whatsapp-logo',
			// Lien « Voir sur la carte » du pied de page.
			'maps'      => 'map-trifold',
		);
	}

	/**
	 * Noms disponibles aussi en graisse `fill`.
	 *
	 * `home`/`desk` sont rendus en `regular` (l'état choisi se dit par la COULEUR,
	 * pas par le remplissage) : on ne liste donc ici que `cart`, seul à être rendu
	 * en `fill` (le bouton flottant). Lister un nom ici sans copier son fichier
	 * `fill` casserait R6 ; l'inverse (copier un `fill` jamais rendu) est du poids
	 * mort que le template proscrit.
	 */
	protected static function filled() {
		return array( 'cart' );
	}

	/**
	 * Rend une icône du catalogue.
	 *
	 * @param string $name Nom d'usage (`cart`, `close`).
	 * @param array  $args weight (`regular`|`fill`), size (px), class.
	 * @return string SVG prêt à imprimer, ou '' si le nom est inconnu.
	 */
	public static function svg( $name, $args = array() ) {
		$catalog = self::catalog();

		if ( ! isset( $catalog[ $name ] ) ) {
			// Une icône manquante ne casse rien : elle laisse un trou que l'œil
			// ne remarque pas. Elle doit donc CRIER en développement.
			_doing_it_wrong(
				__METHOD__,
				esc_html( sprintf( 'Icône inconnue : « %s ». Ajoutez-la au catalogue.', $name ) ),
				'1.46.0'
			);
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'weight' => self::DEFAULT_WEIGHT,
				'size'   => self::DEFAULT_SIZE,
				'class'  => '',
			)
		);

		$weight = ( 'fill' === $args['weight'] && in_array( $name, self::filled(), true ) ) ? 'fill' : 'regular';
		$size   = max( 8, (int) $args['size'] );
		$inner  = self::inner( $weight, $catalog[ $name ] );

		if ( '' === $inner ) {
			return '';
		}

		$classes = 'aod-i';
		if ( '' !== $args['class'] ) {
			$classes .= ' ' . $args['class'];
		}

		// `aria-hidden` : ces icônes sont DÉCORATIVES. Le bouton flottant et le
		// bouton de fermeture portent déjà un `aria-label` ; une icône annoncée
		// en plus serait du bruit au lecteur d'écran.
		return sprintf(
			'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $classes ),
			$size,
			$inner
		);
	}

	/**
	 * Contenu interne d'un fichier SVG (ce qu'il y a entre <svg> et </svg>).
	 *
	 * On ne garde QUE l'intérieur : les attributs de l'amont (dimensions,
	 * couleur) ne doivent pas fuir dans notre rendu, sinon une mise à jour du
	 * jeu d'icônes changerait la présentation sans qu'on l'ait décidé.
	 */
	protected static function inner( $weight, $file ) {
		$key = $weight . '/' . $file;

		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$path = AOD_COD_PATH . 'assets/icons/' . $key . '.svg';
		$raw  = is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichier local du plugin, catalogue fermé : ni URL ni saisie.

		$inner = '';
		if ( '' !== $raw && preg_match( '#<svg[^>]*>(.*)</svg>#is', $raw, $m ) ) {
			$inner = trim( $m[1] );
		}

		self::$cache[ $key ] = $inner;
		return $inner;
	}

	/**
	 * Icônes pré-rendues pour le JS (le panneau peint ses lignes côté client).
	 *
	 * Liste MINIMALE et fermée : le JS ne doit jamais fabriquer une icône hors
	 * catalogue. Même parti pris que `AOD_CD_Icon::js_map()`.
	 */
	public static function js_map() {
		return array(
			'close' => self::svg( 'close', array( 'size' => 16 ) ),
			'check' => self::svg( 'check', array( 'size' => 15 ) ),
		);
	}
}
