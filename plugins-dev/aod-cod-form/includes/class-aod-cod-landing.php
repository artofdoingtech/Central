<?php
/**
 * Page de vente (landing) par produit — données + rendu public.
 *
 * Cette classe est PROPRIÉTAIRE du format : schéma, nettoyage, accesseurs, rendu.
 * `/gestion` (aod-client-dashboard) écrit la landing en APPELANT `sanitize()` — il
 * ne réimplémente rien. Deux copies du schéma dériveraient au premier bloc ajouté.
 *
 * Trois invariants (cf. docs/LANDING-PRODUIT.md §4) :
 *
 *  1. TOUT TEXTE EST UN COUPLE { fr, ar }. `gettext` ne traduit que le code : le
 *     contenu saisi par le gérant ne peut PAS passer par les .po. Une chaîne nue
 *     ici, et la landing reste en français quand le site passe en arabe — sans que
 *     rien ne le signale.
 *  2. TOUTE IMAGE EST UN ID D'ATTACHEMENT, jamais une URL : une URL en base ne
 *     survit pas au changement de domaine à la mise en ligne.
 *  3. AUCUN HTML LIBRE. Les champs sont du texte. On n'offre pas au gérant la
 *     possibilité de coller un <script> dans sa propre boutique.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Landing {

	/** Interrupteur : la fiche produit est-elle remplacée par une page de vente ? */
	const META_ENABLED = '_aod_landing_enabled';

	/** Structure complète de la landing (un seul get_meta au rendu). */
	const META_DATA = '_aod_landing';

	/** Version du schéma — permet de migrer plus tard sans deviner. */
	const SCHEMA_VERSION = 1;

	/** Aperçu gérant d'une landing non encore publiée. */
	const PREVIEW_PARAM = 'aod_landing_preview';

	/** Ancre du (premier) bloc formulaire : cible des boutons d'appel à l'action. */
	const FORM_ANCHOR = 'aod-commander';

	/** @var AOD_COD_Landing|null */
	private static $instance = null;

	/** @var int|null Produit dont la landing s'affiche (null = pas encore calculé). */
	protected $target = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Remplace le gabarit de la fiche produit. Priorité tardive : on passe APRÈS
		// les thèmes/plugins qui choisissent le leur, et on ne s'impose que si la
		// landing est réellement active (sinon on ne touche à rien du tout).
		add_filter( 'template_include', array( $this, 'maybe_take_over' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/* ───────────────────────────────────────────────────────────── Schéma ─── */

	/**
	 * Catalogue FERMÉ des blocs : le contrat entre `/gestion` (écriture) et le
	 * front (rendu). Ajouter un bloc = ajouter une entrée ici, et rien d'autre.
	 *
	 * Types de champ :
	 *   'text'     → couple { fr, ar }, une ligne.
	 *   'textarea' → couple { fr, ar }, multi-lignes.
	 *   'plain'    → texte court NON bilingue (emoji, prénom d'un avis…).
	 *   'image'    → ID d'attachement (int).
	 *   'images'   → liste d'IDs d'attachement (galerie).
	 *   'video'    → ID d'attachement d'un fichier vidéo (int).
	 *   'url'      → lien http/https NON bilingue (une URL ne se traduit pas).
	 *   'number'   → entier borné par 'min' / 'max' (une note, un seuil).
	 *   'select'   → une valeur parmi 'options' (liste fermée).
	 *   'repeat'   → liste d'items, chacun décrit par ses propres 'fields'.
	 *
	 * @return array
	 */
	public static function schema() {
		return array(
			'hero'   => array(
				'label'  => __( 'Héro', 'aod-cod-form' ),
				'fields' => array(
					'image' => array( 'type' => 'image', 'label' => __( 'Image de fond', 'aod-cod-form' ) ),
					'title' => array( 'type' => 'text', 'label' => __( 'Titre', 'aod-cod-form' ) ),
					'text'  => array( 'type' => 'textarea', 'label' => __( 'Accroche', 'aod-cod-form' ) ),
					'cta'   => array( 'type' => 'text', 'label' => __( 'Libellé du bouton', 'aod-cod-form' ) ),
				),
			),
			'points' => array(
				'label'  => __( 'Bénéfices', 'aod-cod-form' ),
				'fields' => array(
					'title' => array( 'type' => 'text', 'label' => __( 'Titre de la section', 'aod-cod-form' ) ),
					'items' => array(
						'type'   => 'repeat',
						'label'  => __( 'Bénéfices', 'aod-cod-form' ),
						'fields' => array(
							'icon'  => array( 'type' => 'plain', 'label' => __( 'Icône (emoji)', 'aod-cod-form' ) ),
							'title' => array( 'type' => 'text', 'label' => __( 'Titre', 'aod-cod-form' ) ),
							'text'  => array( 'type' => 'textarea', 'label' => __( 'Texte', 'aod-cod-form' ) ),
						),
					),
				),
			),
			'gallery' => array(
				'label'  => __( 'Galerie', 'aod-cod-form' ),
				'fields' => array(
					'title'  => array( 'type' => 'text', 'label' => __( 'Titre de la section', 'aod-cod-form' ) ),
					'images' => array( 'type' => 'images', 'label' => __( 'Images', 'aod-cod-form' ) ),
				),
			),
			'video'  => array(
				'label'  => __( 'Vidéo', 'aod-cod-form' ),
				'fields' => array(
					'title' => array( 'type' => 'text', 'label' => __( 'Titre de la section', 'aod-cod-form' ) ),
					'url'   => array( 'type' => 'url', 'label' => __( 'Lien YouTube', 'aod-cod-form' ) ),
					'file'  => array( 'type' => 'video', 'label' => __( 'Ou fichier vidéo (MP4)', 'aod-cod-form' ) ),
					'image' => array( 'type' => 'image', 'label' => __( 'Image de couverture', 'aod-cod-form' ) ),
				),
			),
			'text'   => array(
				'label'  => __( 'Texte', 'aod-cod-form' ),
				'fields' => array(
					'title' => array( 'type' => 'text', 'label' => __( 'Titre de la section', 'aod-cod-form' ) ),
					'text'  => array( 'type' => 'textarea', 'label' => __( 'Texte', 'aod-cod-form' ) ),
				),
			),
			'compare' => array(
				'label'  => __( 'Comparatif', 'aod-cod-form' ),
				'fields' => array(
					'title' => array( 'type' => 'text', 'label' => __( 'Titre de la section', 'aod-cod-form' ) ),
					'left'  => array( 'type' => 'text', 'label' => __( 'Titre de la colonne 1', 'aod-cod-form' ) ),
					'right' => array( 'type' => 'text', 'label' => __( 'Titre de la colonne 2', 'aod-cod-form' ) ),
					'items' => array(
						'type'   => 'repeat',
						'label'  => __( 'Lignes du comparatif', 'aod-cod-form' ),
						'fields' => array(
							'left'  => array( 'type' => 'text', 'label' => __( 'Colonne 1', 'aod-cod-form' ) ),
							'right' => array( 'type' => 'text', 'label' => __( 'Colonne 2', 'aod-cod-form' ) ),
						),
					),
				),
			),
			'reviews' => array(
				'label'  => __( 'Avis clients', 'aod-cod-form' ),
				'fields' => array(
					'title' => array( 'type' => 'text', 'label' => __( 'Titre de la section', 'aod-cod-form' ) ),
					'items' => array(
						'type'   => 'repeat',
						'label'  => __( 'Avis', 'aod-cod-form' ),
						'fields' => array(
							// Le prénom n'est PAS un couple { fr, ar } : un prénom ne se traduit pas.
							'name'   => array( 'type' => 'plain', 'label' => __( 'Prénom', 'aod-cod-form' ) ),
							'rating' => array( 'type' => 'number', 'label' => __( 'Note (sur 5)', 'aod-cod-form' ), 'min' => 0, 'max' => 5 ),
							'text'   => array( 'type' => 'textarea', 'label' => __( 'Avis', 'aod-cod-form' ) ),
							'photo'  => array( 'type' => 'image', 'label' => __( 'Photo (facultative)', 'aod-cod-form' ) ),
						),
					),
				),
			),
			'faq'    => array(
				'label'  => __( 'Questions fréquentes', 'aod-cod-form' ),
				'fields' => array(
					'title' => array( 'type' => 'text', 'label' => __( 'Titre de la section', 'aod-cod-form' ) ),
					'items' => array(
						'type'   => 'repeat',
						'label'  => __( 'Questions', 'aod-cod-form' ),
						'fields' => array(
							'q' => array( 'type' => 'text', 'label' => __( 'Question', 'aod-cod-form' ) ),
							'a' => array( 'type' => 'textarea', 'label' => __( 'Réponse', 'aod-cod-form' ) ),
						),
					),
				),
			),
			'urgency' => array(
				'label'  => __( 'Urgence', 'aod-cod-form' ),
				'fields' => array(
					'mode' => array(
						'type'    => 'select',
						'label'   => __( 'Ce qu’on affiche', 'aod-cod-form' ),
						'options' => array(
							'stock' => __( 'Le stock restant (lu dans votre fiche produit)', 'aod-cod-form' ),
							'sale'  => __( 'Le temps restant avant la fin de votre promotion', 'aod-cod-form' ),
						),
					),
					'text' => array( 'type' => 'text', 'label' => __( 'Message', 'aod-cod-form' ) ),
					'max'  => array( 'type' => 'number', 'label' => __( 'N’afficher que si le stock restant est inférieur ou égal à (0 = toujours)', 'aod-cod-form' ), 'min' => 0, 'max' => 9999 ),
				),
			),
			'form'   => array(
				'label'  => __( 'Formulaire de commande', 'aod-cod-form' ),
				'fields' => array(
					'title' => array( 'type' => 'text', 'label' => __( 'Titre de la section', 'aod-cod-form' ) ),
				),
			),
			'trust'  => array(
				'label'  => __( 'Réassurance', 'aod-cod-form' ),
				'fields' => array(
					'items' => array(
						'type'   => 'repeat',
						'label'  => __( 'Garanties', 'aod-cod-form' ),
						'fields' => array(
							'icon' => array( 'type' => 'plain', 'label' => __( 'Icône (emoji)', 'aod-cod-form' ) ),
							'text' => array( 'type' => 'text', 'label' => __( 'Texte', 'aod-cod-form' ) ),
						),
					),
				),
			),
		);
	}

	/**
	 * La structure de départ « page de vente type » : des blocs VIDES, dans l'ordre
	 * qui vend — accrocher, prouver, répondre aux objections, presser, faire
	 * commander, rassurer.
	 *
	 * Des blocs vides, pas un faux contenu : un modèle pré-rempli partirait dans tous
	 * les clones (règle n°1 du template) et le premier gérant pressé publierait le
	 * texte d'exemple tel quel. La structure guide ; les mots restent à lui.
	 *
	 * Elle vit ICI et pas dans `/gestion` pour la même raison que le schéma : c'est
	 * le format qui sait ce qu'est « une landing type », pas l'éditeur.
	 *
	 * @return string[] Types de blocs, dans l'ordre.
	 */
	public static function starter() {
		return array( 'hero', 'points', 'gallery', 'reviews', 'faq', 'urgency', 'form', 'trust' );
	}

	/**
	 * Structure vide (aucune landing).
	 *
	 * @return array
	 */
	public static function empty_data() {
		return array(
			'version'    => self::SCHEMA_VERSION,
			// Réglage de page : la barre « Commander » collante sur mobile. Le format
			// est figé dès la v1 pour ne pas avoir à migrer ; son RENDU arrive en 6.4.
			'sticky_cta' => array(
				'enabled' => false,
				'label'   => array( 'fr' => '', 'ar' => '' ),
			),
			'blocks'     => array(),
		);
	}

	/* ─────────────────────────────────────────────────────────── Nettoyage ─── */

	/**
	 * POST (ou méta d'origine douteuse) → structure propre.
	 *
	 * LISTE BLANCHE STRICTE : un champ hors schéma est JETÉ, jamais « conservé au
	 * cas où ». Un type de bloc inconnu est ignoré (un clone dont le plugin serait
	 * plus ancien affiche le reste au lieu de tomber en erreur fatale).
	 *
	 * @param mixed $raw Données brutes.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$out = self::empty_data();
		if ( ! is_array( $raw ) ) {
			return $out;
		}

		if ( isset( $raw['sticky_cta'] ) && is_array( $raw['sticky_cta'] ) ) {
			$sticky = $raw['sticky_cta'];
			$out['sticky_cta'] = array(
				'enabled' => ! empty( $sticky['enabled'] ),
				'label'   => self::sanitize_pair( isset( $sticky['label'] ) ? $sticky['label'] : null, false ),
			);
		}

		$schema = self::schema();
		$blocks = ( isset( $raw['blocks'] ) && is_array( $raw['blocks'] ) ) ? $raw['blocks'] : array();
		$seen   = array();
		$i      = 0;

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			if ( ! isset( $schema[ $type ] ) ) {
				continue; // Type hors catalogue : ignoré.
			}

			// Identifiant stable (réordonnancement, ancres). Unique, quoi qu'on reçoive.
			++$i;
			$id = isset( $block['id'] ) ? sanitize_key( $block['id'] ) : '';
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = 'b' . $i;
			}
			while ( isset( $seen[ $id ] ) ) {
				$id .= '-' . $i;
			}
			$seen[ $id ] = true;

			$clean = array( 'id' => $id, 'type' => $type );
			foreach ( $schema[ $type ]['fields'] as $key => $spec ) {
				$clean[ $key ] = self::sanitize_field( isset( $block[ $key ] ) ? $block[ $key ] : null, $spec );
			}
			$out['blocks'][] = $clean;
		}

		return $out;
	}

	/**
	 * Nettoie un champ selon son type déclaré au schéma.
	 *
	 * @param mixed $value Valeur brute.
	 * @param array $spec  Spécification du champ.
	 * @return mixed
	 */
	protected static function sanitize_field( $value, $spec ) {
		$type = isset( $spec['type'] ) ? $spec['type'] : 'text';

		switch ( $type ) {
			case 'image':
			case 'video':
				return max( 0, (int) $value );

			case 'images':
				// `max( 0, (int) )` et non `absint()` : absint(-5) rend 5, donc un ID
				// négatif désignerait l'attachement 5 — une AUTRE image de la
				// médiathèque, choisie par accident. Un ID invalide doit être écarté,
				// pas replié sur un voisin. Même règle que le champ `image` ci-dessus.
				$ids = array();
				if ( is_array( $value ) ) {
					foreach ( $value as $id ) {
						$id = max( 0, (int) $id );
						if ( $id ) {
							$ids[] = $id;
						}
					}
				}
				return array_values( array_unique( $ids ) );

			case 'url':
				// http/https UNIQUEMENT. Sans cette liste, un `javascript:` collé dans le
				// champ deviendrait un lien exécutable sur la boutique du gérant.
				return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );

			case 'number':
				$min = isset( $spec['min'] ) ? (int) $spec['min'] : 0;
				$max = isset( $spec['max'] ) ? (int) $spec['max'] : PHP_INT_MAX;
				return min( $max, max( $min, (int) $value ) );

			case 'select':
				// Liste FERMÉE : une valeur hors options retombe sur la première. Le rendu
				// n'a donc jamais à se demander ce qu'il doit faire d'un mode inconnu.
				$options = isset( $spec['options'] ) ? array_keys( $spec['options'] ) : array();
				$value   = sanitize_key( (string) $value );
				if ( in_array( $value, $options, true ) ) {
					return $value;
				}
				return $options ? $options[0] : '';

			case 'plain':
				return sanitize_text_field( (string) $value );

			case 'textarea':
				return self::sanitize_pair( $value, true );

			case 'repeat':
				$items  = array();
				$fields = isset( $spec['fields'] ) ? $spec['fields'] : array();
				if ( is_array( $value ) ) {
					foreach ( $value as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						$clean = array();
						foreach ( $fields as $key => $sub ) {
							$clean[ $key ] = self::sanitize_field( isset( $item[ $key ] ) ? $item[ $key ] : null, $sub );
						}
						$items[] = $clean;
					}
				}
				return $items;

			case 'text':
			default:
				return self::sanitize_pair( $value, false );
		}
	}

	/**
	 * Nettoie un couple bilingue { fr, ar }.
	 *
	 * @param mixed $value      Valeur brute.
	 * @param bool  $multiline  Autoriser les retours à la ligne.
	 * @return array { fr, ar }
	 */
	protected static function sanitize_pair( $value, $multiline = false ) {
		$pair = array( 'fr' => '', 'ar' => '' );
		if ( ! is_array( $value ) ) {
			return $pair;
		}
		foreach ( array( 'fr', 'ar' ) as $lang ) {
			if ( ! isset( $value[ $lang ] ) ) {
				continue;
			}
			$raw           = (string) $value[ $lang ];
			$pair[ $lang ] = $multiline ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		}
		return $pair;
	}

	/* ────────────────────────────────────────────────────────── Accesseurs ─── */

	/**
	 * Landing normalisée d'un produit (toujours une structure valide).
	 *
	 * On repasse par `sanitize()` à la LECTURE : la méta a pu être écrite par une
	 * version antérieure, ou à la main. Le rendu ne doit jamais avoir à se demander
	 * si une clé existe.
	 *
	 * @param int $product_id
	 * @return array
	 */
	public static function get( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return self::empty_data();
		}
		return self::sanitize( $product->get_meta( self::META_DATA ) );
	}

	/**
	 * L'interrupteur est-il sur ON pour ce produit ?
	 *
	 * @param int $product_id
	 * @return bool
	 */
	public static function is_enabled( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product && '1' === (string) $product->get_meta( self::META_ENABLED );
	}

	/**
	 * Le texte à AFFICHER pour un couple { fr, ar }.
	 *
	 * Repli DANS LES DEUX SENS : le gérant doit pouvoir livrer une landing 100 %
	 * arabe, 100 % française ou bilingue — sans jamais laisser un trou à l'écran.
	 * (`AOD_COD_Data::label()` ne replie que AR→FR : insuffisant ici.)
	 *
	 * Le repli est mutualisé dans `AOD_COD_Catalog_I18n::pick()` (socle 7.1) — une
	 * seule source de vérité pour tout le contenu marchand bilingue.
	 *
	 * @param mixed $field Couple { fr, ar }.
	 * @return string
	 */
	public static function text( $field ) {
		if ( ! is_array( $field ) ) {
			return '';
		}
		$fr = isset( $field['fr'] ) ? $field['fr'] : '';
		$ar = isset( $field['ar'] ) ? $field['ar'] : '';
		return AOD_COD_Catalog_I18n::pick( $fr, $ar, AOD_COD_Data::is_arabic() );
	}

	/* ─────────────────────────────────────────────────────────────── Rendu ─── */

	/**
	 * L'identifiant YouTube d'un lien — ou '' si ce n'en est pas un.
	 *
	 * On ne construit JAMAIS l'URL de l'iframe à partir du texte saisi : on en extrait
	 * l'identifiant, on le valide (11 caractères du jeu YouTube), et on rebâtit l'URL
	 * nous-mêmes. Un lien mal formé n'affiche donc pas de vidéo — il n'injecte rien.
	 *
	 * @param mixed $url Lien saisi par le gérant.
	 * @return string Identifiant à 11 caractères, ou ''.
	 */
	public static function youtube_id( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$id   = '';

		if ( 'youtu.be' === $host ) {
			$id = trim( $path, '/' );
		} elseif ( in_array( $host, array( 'youtube.com', 'm.youtube.com', 'youtube-nocookie.com' ), true ) ) {
			$query = array();
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			if ( ! empty( $query['v'] ) ) {
				$id = (string) $query['v'];
			} elseif ( preg_match( '~^/(?:embed|shorts|live|v)/([^/?#]+)~', $path, $m ) ) {
				// Délimiteur `~` et non `#` : un `#` dans la classe de caractères fermerait
				// le motif d'un cran trop tôt (et la regex ne rendrait plus jamais rien).
				$id = $m[1];
			}
		}

		return preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ? $id : '';
	}

	/**
	 * Cette landing a-t-elle besoin de JavaScript ?
	 *
	 * Trois usages, et trois seulement : la façade vidéo, le compte à rebours qui
	 * avance, et la barre collante qui s'efface quand le formulaire est à l'écran.
	 * Aucun des trois n'est indispensable — sans JS, la page reste complète (le lien
	 * YouTube en clair, le rebours figé sur sa dernière valeur, la barre toujours
	 * visible). C'est pour ça qu'on peut se permettre de ne PAS charger le script
	 * quand aucun des trois n'est là : une page de vente ordinaire reste 100 % HTML/CSS.
	 *
	 * @param array $data Landing normalisée.
	 * @param int   $product_id
	 * @return bool
	 */
	protected function needs_js( $data, $product_id ) {
		if ( ! empty( $data['sticky_cta']['enabled'] ) ) {
			return true;
		}
		foreach ( $data['blocks'] as $block ) {
			// Un fichier vidéo se lit avec la balise `<video>` native : aucun JS.
			if ( 'video' === $block['type'] && ! (int) $block['file'] && '' !== self::youtube_id( $block['url'] ) ) {
				return true;
			}
			if ( 'urgency' === $block['type'] && 'sale' === $block['mode'] && $this->sale_ends_at( $product_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fin de la promotion en cours — l'instant EXACT où WooCommerce retire le prix barré.
	 *
	 * C'est la seule source honnête pour un compte à rebours : il tombe à zéro quand la
	 * promotion tombe réellement. Pas de promo, pas de date de fin, ou date déjà passée
	 * → pas de rebours (et le bloc ne s'affiche pas du tout). Un rebours qui se réarme à
	 * chaque visite est un mensonge, et il se retourne contre le marchand.
	 *
	 * @param int $product_id
	 * @return int Timestamp UTC, ou 0.
	 */
	protected function sale_ends_at( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_on_sale() ) {
			return 0;
		}
		$end = $product->get_date_on_sale_to();
		if ( ! $end ) {
			return 0;
		}
		$ts = (int) $end->getTimestamp();
		return ( $ts > time() ) ? $ts : 0;
	}

	/**
	 * Le visiteur courant doit-il voir une landing ?
	 *
	 * Mémorisé : quatre crochets (`template_include`, `wp_enqueue_scripts`,
	 * `body_class`, le gabarit) posent la même question dans la même requête.
	 *
	 * @return int ID du produit, ou 0 si la fiche produit normale doit s'afficher.
	 */
	public function target_product() {
		if ( null !== $this->target ) {
			return $this->target;
		}
		$this->target = $this->compute_target();
		return $this->target;
	}

	/**
	 * @return int
	 */
	protected function compute_target() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return 0;
		}
		$product_id = (int) get_queried_object_id();
		if ( ! $product_id ) {
			return 0;
		}

		// Aperçu d'une landing non publiée : réservé à qui peut éditer les produits.
		$preview = isset( $_GET[ self::PREVIEW_PARAM ] ) && '1' === $_GET[ self::PREVIEW_PARAM ]; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! self::is_enabled( $product_id ) && ! ( $preview && current_user_can( 'edit_products' ) ) ) {
			return 0;
		}

		// Une landing vide n'est pas une landing : plutôt la fiche produit du thème
		// qu'une page blanche. Un produit sans bloc s'affiche donc comme avant.
		$data = self::get( $product_id );
		if ( empty( $data['blocks'] ) ) {
			return 0;
		}

		return $product_id;
	}

	/**
	 * Substitue notre gabarit à celui du thème — et à lui seul.
	 *
	 * On reprend le TEMPLATE (et non les hooks Woo comme le formulaire) parce que
	 * le thème imposerait sinon sa colonne, sa galerie et son titre : on se battrait
	 * contre lui en CSS, sur chaque clone. `get_header()`/`get_footer()` conservent
	 * l'en-tête et le pied — donc la nav à 3 entrées, le switcher FR/AR et le
	 * branding du Personnalisateur.
	 *
	 * @param string $template Gabarit choisi par WordPress.
	 * @return string
	 */
	public function maybe_take_over( $template ) {
		return $this->target_product() ? AOD_COD_PATH . 'templates/single-product-landing.php' : $template;
	}

	/**
	 * CSS de la landing — chargé UNIQUEMENT là où elle s'affiche (objectif < 3 s).
	 */
	public function assets() {
		$pid = $this->target_product();
		if ( ! $pid ) {
			return;
		}
		$path = AOD_COD_PATH . 'assets/css/aod-landing.css';
		wp_enqueue_style(
			'aod-landing',
			AOD_COD_URL . 'assets/css/aod-landing.css',
			array(),
			file_exists( $path ) ? (string) filemtime( $path ) : AOD_COD_VERSION
		);

		if ( ! $this->needs_js( self::get( $pid ), $pid ) ) {
			return;
		}
		$js = AOD_COD_PATH . 'assets/js/aod-landing.js';
		wp_enqueue_script(
			'aod-landing',
			AOD_COD_URL . 'assets/js/aod-landing.js',
			array(),
			file_exists( $js ) ? (string) filemtime( $js ) : AOD_COD_VERSION,
			true
		);
	}

	/**
	 * Classe de corps de page : permet au child theme d'un clone de neutraliser
	 * ses propres marges de page produit sans deviner.
	 *
	 * @param array $classes
	 * @return array
	 */
	public function body_class( $classes ) {
		$pid = $this->target_product();
		if ( ! $pid ) {
			return $classes;
		}
		$classes[] = 'aod-landing-active';

		// La barre collante est en `position: fixed` : sans marge au bas du CORPS de page,
		// elle recouvrirait la fin du contenu et le pied de page. La classe est ici (et
		// pas sur la landing) parce que c'est le `body` qu'il faut rallonger.
		$data = self::get( $pid );
		if ( ! empty( $data['sticky_cta']['enabled'] ) && $this->has_form( $data ) ) {
			$classes[] = 'aod-landing-sticky';
		}
		return $classes;
	}

	/**
	 * Cette landing contient-elle le formulaire de commande ?
	 *
	 * @param array $data Landing normalisée.
	 * @return bool
	 */
	protected function has_form( $data ) {
		foreach ( $data['blocks'] as $block ) {
			if ( 'form' === $block['type'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rend la landing d'un produit (appelé par le gabarit).
	 *
	 * @param int $product_id
	 */
	public function render( $product_id ) {
		$data = self::get( $product_id );
		if ( empty( $data['blocks'] ) ) {
			return;
		}

		// Le formulaire est ancré sur le PREMIER bloc `form` : c'est là que pointent
		// tous les appels à l'action.
		$anchor_used = false;

		echo '<div class="aod-landing">';
		foreach ( $data['blocks'] as $block ) {
			$method = 'render_' . $block['type'];
			if ( ! method_exists( $this, $method ) ) {
				continue; // Bloc du schéma pas encore rendu (étapes 6.3/6.4).
			}
			$is_form = ( 'form' === $block['type'] );
			$id      = ( $is_form && ! $anchor_used ) ? self::FORM_ANCHOR : 'aod-' . $block['id'];
			if ( $is_form ) {
				$anchor_used = true;
			}

			printf(
				'<section class="aod-landing__block aod-landing__block--%1$s" id="%2$s">',
				esc_attr( $block['type'] ),
				esc_attr( $id )
			);
			$this->$method( $block, $product_id );
			echo '</section>';
		}
		echo '</div>';

		$this->render_sticky( $data );
	}

	/**
	 * La barre « Commander » collée en bas de l'écran (mobile).
	 *
	 * Elle ne s'affiche que s'il y a un formulaire où l'envoyer : un bouton d'appel à
	 * l'action qui ne mène nulle part serait pire que pas de bouton du tout.
	 *
	 * Trois exigences tenues côté CSS/JS :
	 *  - elle NE MASQUE PAS le contenu (`body.aod-landing-sticky` reçoit une marge basse) ;
	 *  - elle NE PIÈGE PAS le focus : quand le formulaire est à l'écran, le JS l'efface en
	 *    `visibility: hidden` — le lien sort donc aussi de l'ordre de tabulation ;
	 *  - sans JavaScript elle reste simplement visible : dégradée, jamais cassée.
	 *
	 * @param array $data Landing normalisée.
	 */
	protected function render_sticky( $data ) {
		if ( empty( $data['sticky_cta']['enabled'] ) || ! $this->has_form( $data ) ) {
			return;
		}
		$label = self::text( $data['sticky_cta']['label'] );
		if ( '' === $label ) {
			$label = __( 'Commander maintenant', 'aod-cod-form' );
		}
		?>
		<div class="aod-landing__sticky" data-anchor="<?php echo esc_attr( self::FORM_ANCHOR ); ?>">
			<a class="button aod-landing__sticky-cta" href="#<?php echo esc_attr( self::FORM_ANCHOR ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Bloc « Héro » : image, titre, accroche, bouton vers le formulaire.
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_hero( $block, $product_id ) {
		$title = self::text( $block['title'] );
		$text  = self::text( $block['text'] );
		$cta   = self::text( $block['cta'] );
		$image = (int) $block['image'];

		// Le titre du produit sert de repli : un héro sans titre reste vendeur.
		// `get_name()` est un getter BRUT : il ne passe pas par `the_title`, donc le
		// repli resterait en français sur un site arabe. On applique la traduction
		// ici, explicitement (on ne filtre pas le getter : il est aussi appelé par
		// `add_product()`, qui grave le nom dans la commande).
		if ( '' === $title ) {
			$product = wc_get_product( $product_id );
			$title   = $product
				? AOD_COD_Catalog_I18n::render( $product->get_name(), get_post_meta( $product_id, AOD_COD_Catalog_Filters::META_TITLE, true ) )
				: '';
		}
		?>
		<?php if ( $image ) : ?>
			<div class="aod-landing__hero-media">
				<?php
				echo wp_get_attachment_image(
					$image,
					'large',
					false,
					array(
						'class'    => 'aod-landing__hero-img',
						'loading'  => 'eager',   // Image LCP : la différer la retarderait.
						'decoding' => 'async',
						'alt'      => esc_attr( $title ),
					)
				);
				?>
			</div>
		<?php endif; ?>
		<div class="aod-landing__hero-body">
			<?php if ( '' !== $title ) : ?>
				<h1 class="aod-landing__hero-title"><?php echo esc_html( $title ); ?></h1>
			<?php endif; ?>
			<?php if ( '' !== $text ) : ?>
				<p class="aod-landing__hero-text"><?php echo esc_html( $text ); ?></p>
			<?php endif; ?>
			<?php // `button` : on hérite du bouton du THÈME. Aucune couleur de marque ici. ?>
			<a class="button aod-landing__cta" href="#<?php echo esc_attr( self::FORM_ANCHOR ); ?>">
				<?php echo esc_html( '' !== $cta ? $cta : __( 'Commander maintenant', 'aod-cod-form' ) ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Bloc « Bénéfices ».
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_points( $block, $product_id ) {
		$title = self::text( $block['title'] );
		$items = is_array( $block['items'] ) ? $block['items'] : array();
		if ( ! $items ) {
			return;
		}
		?>
		<?php if ( '' !== $title ) : ?>
			<h2 class="aod-landing__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<ul class="aod-landing__points">
			<?php foreach ( $items as $item ) :
				$it_title = self::text( $item['title'] );
				$it_text  = self::text( $item['text'] );
				if ( '' === $it_title && '' === $it_text ) {
					continue;
				}
				?>
				<li class="aod-landing__point">
					<?php if ( '' !== $item['icon'] ) : ?>
						<span class="aod-landing__point-icon" aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span>
					<?php endif; ?>
					<div class="aod-landing__point-body">
						<?php if ( '' !== $it_title ) : ?>
							<h3 class="aod-landing__point-title"><?php echo esc_html( $it_title ); ?></h3>
						<?php endif; ?>
						<?php if ( '' !== $it_text ) : ?>
							<p class="aod-landing__point-text"><?php echo esc_html( $it_text ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Bloc « Galerie ».
	 *
	 * `wp_get_attachment_image()` pose `srcset`/`sizes` tout seul : le mobile télécharge
	 * la petite image, pas la grande. `lazy` sur toutes : une galerie est sous la ligne
	 * de flottaison, elle ne doit pas retarder l'affichage du héro.
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_gallery( $block, $product_id ) {
		$images = is_array( $block['images'] ) ? array_filter( array_map( 'intval', $block['images'] ) ) : array();
		if ( ! $images ) {
			return;
		}
		$title = self::text( $block['title'] );
		?>
		<?php if ( '' !== $title ) : ?>
			<h2 class="aod-landing__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<ul class="aod-landing__gallery">
			<?php foreach ( $images as $id ) : ?>
				<li class="aod-landing__gallery-item">
					<?php
					echo wp_get_attachment_image(
						$id,
						'large',
						false,
						array(
							'class'    => 'aod-landing__gallery-img',
							'loading'  => 'lazy',
							'decoding' => 'async',
						)
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Bloc « Vidéo » — jamais d'iframe posée d'emblée.
	 *
	 * Une iframe YouTube coûte ~1 Mo AVANT le moindre clic : l'objectif « < 3 s sur
	 * mobile » n'y survit pas, et le visiteur paie sa donnée pour une vidéo qu'il ne
	 * regardera peut-être pas. On affiche donc une COUVERTURE ; l'iframe n'est injectée
	 * qu'au clic (assets/js/aod-landing.js), sur `youtube-nocookie.com`.
	 *
	 * Un fichier vidéo téléversé, lui, n'a besoin d'aucun JS : `preload="none"` suffit —
	 * le navigateur ne télécharge rien tant qu'on n'appuie pas sur Lire.
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_video( $block, $product_id ) {
		$file  = (int) $block['file'];
		$yt    = self::youtube_id( $block['url'] );
		$cover = (int) $block['image'];

		// Ni fichier ni lien exploitable : on n'affiche pas une couverture qui ne mène
		// nulle part. (Un lien saisi de travers ne doit pas donner un lecteur mort.)
		if ( ! $file && '' === $yt ) {
			return;
		}

		$title = self::text( $block['title'] );
		if ( '' !== $title ) {
			echo '<h2 class="aod-landing__title">' . esc_html( $title ) . '</h2>';
		}

		if ( $file ) {
			$src   = wp_get_attachment_url( $file );
			$mime  = get_post_mime_type( $file );
			$post  = $cover ? wp_get_attachment_image_url( $cover, 'large' ) : '';
			if ( ! $src ) {
				return;
			}
			?>
			<video class="aod-landing__video" controls preload="none" playsinline
				<?php echo $post ? 'poster="' . esc_url( $post ) . '"' : ''; ?>>
				<source src="<?php echo esc_url( $src ); ?>" type="<?php echo esc_attr( $mime ? $mime : 'video/mp4' ); ?>">
			</video>
			<?php
			return;
		}
		?>
		<div class="aod-landing__video-wrap">
			<button type="button" class="aod-landing__video-facade"
				data-yt="<?php echo esc_attr( $yt ); ?>"
				data-title="<?php esc_attr_e( 'Vidéo', 'aod-cod-form' ); ?>">
				<?php
				if ( $cover ) {
					echo wp_get_attachment_image(
						$cover,
						'large',
						false,
						array(
							'class'    => 'aod-landing__video-cover',
							'loading'  => 'lazy',
							'decoding' => 'async',
							'alt'      => '',
						)
					);
				}
				?>
				<span class="aod-landing__video-play" aria-hidden="true">▶</span>
				<span class="screen-reader-text"><?php esc_html_e( 'Lire la vidéo', 'aod-cod-form' ); ?></span>
			</button>
		</div>
		<?php
		// Sans JavaScript, le bouton ne ferait rien : on donne le lien, en clair.
		?>
		<noscript>
			<p class="aod-landing__video-fallback">
				<a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . $yt ); ?>" target="_blank" rel="noopener nofollow">
					<?php esc_html_e( 'Voir la vidéo sur YouTube', 'aod-cod-form' ); ?>
				</a>
			</p>
		</noscript>
		<?php
	}

	/**
	 * Bloc « Texte » — un paragraphe libre, sans une once de HTML.
	 *
	 * `esc_html()` D'ABORD, `wpautop()` ensuite : on n'obtient des paragraphes qu'à
	 * partir des sauts de ligne, jamais à partir de balises collées par le gérant.
	 * L'inverse ouvrirait la porte à un `<script>` dans sa propre boutique.
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_text( $block, $product_id ) {
		$text = self::text( $block['text'] );
		if ( '' === $text ) {
			return;
		}
		$title = self::text( $block['title'] );
		if ( '' !== $title ) {
			echo '<h2 class="aod-landing__title">' . esc_html( $title ) . '</h2>';
		}
		echo '<div class="aod-landing__text">' . wpautop( esc_html( $text ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé avant wpautop().
	}

	/**
	 * Bloc « Comparatif » : deux colonnes, une ligne par point de comparaison.
	 *
	 * Un vrai `<table>` avec ses `<th scope="col">` : un lecteur d'écran annonce alors
	 * « Avec le produit : livraison en 48 h ». Deux `<div>` côte à côte ne diraient rien.
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_compare( $block, $product_id ) {
		$items = is_array( $block['items'] ) ? $block['items'] : array();
		$rows  = array();
		foreach ( $items as $item ) {
			$left  = self::text( $item['left'] );
			$right = self::text( $item['right'] );
			if ( '' === $left && '' === $right ) {
				continue;
			}
			$rows[] = array( $left, $right );
		}
		if ( ! $rows ) {
			return;
		}

		$title = self::text( $block['title'] );
		$left  = self::text( $block['left'] );
		$right = self::text( $block['right'] );
		if ( '' === $left ) {
			$left = __( 'Sans', 'aod-cod-form' );
		}
		if ( '' === $right ) {
			$right = __( 'Avec', 'aod-cod-form' );
		}
		?>
		<?php if ( '' !== $title ) : ?>
			<h2 class="aod-landing__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<table class="aod-landing__compare">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html( $left ); ?></th>
					<th scope="col" class="aod-landing__compare-win"><?php echo esc_html( $right ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row[0] ); ?></td>
						<td class="aod-landing__compare-win"><?php echo esc_html( $row[1] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Bloc « Avis clients ».
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_reviews( $block, $product_id ) {
		$items = is_array( $block['items'] ) ? $block['items'] : array();
		$title = self::text( $block['title'] );
		$shown = 0;
		ob_start();
		foreach ( $items as $item ) {
			$text = self::text( $item['text'] );
			$name = (string) $item['name'];
			if ( '' === $text && '' === $name ) {
				continue;
			}
			++$shown;
			$rating = (int) $item['rating'];
			$photo  = (int) $item['photo'];
			?>
			<li class="aod-landing__review">
				<div class="aod-landing__review-head">
					<?php
					if ( $photo ) {
						echo wp_get_attachment_image(
							$photo,
							'thumbnail',
							false,
							array(
								'class'    => 'aod-landing__review-photo',
								'loading'  => 'lazy',
								'decoding' => 'async',
								'alt'      => '',
							)
						);
					}
					?>
					<div>
						<?php if ( '' !== $name ) : ?>
							<p class="aod-landing__review-name"><?php echo esc_html( $name ); ?></p>
						<?php endif; ?>
						<?php if ( $rating > 0 ) : ?>
							<?php
							// Les étoiles sont un DESSIN : un lecteur d'écran qui épelle
							// « étoile étoile étoile » n'apprend rien. On lui donne la note.
							/* translators: 1 : note attribuée, 2 : note maximale (5). */
							$aria = sprintf( __( 'Note : %1$d sur %2$d', 'aod-cod-form' ), $rating, 5 );
							?>
							<p class="aod-landing__review-stars">
								<span aria-hidden="true"><?php echo esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) ); ?></span>
								<span class="screen-reader-text"><?php echo esc_html( $aria ); ?></span>
							</p>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( '' !== $text ) : ?>
					<p class="aod-landing__review-text"><?php echo esc_html( $text ); ?></p>
				<?php endif; ?>
			</li>
			<?php
		}
		$html = ob_get_clean();
		if ( ! $shown ) {
			return;
		}
		if ( '' !== $title ) {
			echo '<h2 class="aod-landing__title">' . esc_html( $title ) . '</h2>';
		}
		echo '<ul class="aod-landing__reviews">' . $html . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé ci-dessus, champ par champ.
	}

	/**
	 * Bloc « Questions fréquentes ».
	 *
	 * `<details>/<summary>` natifs : l'accordéon s'ouvre et se ferme sans une ligne de
	 * JavaScript, il est annoncé correctement par les lecteurs d'écran, il fonctionne au
	 * clavier, et le navigateur sait y chercher du texte. Un accordéon maison en JS
	 * n'apporterait rien — sinon des bugs.
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_faq( $block, $product_id ) {
		$items = is_array( $block['items'] ) ? $block['items'] : array();
		$rows  = array();
		foreach ( $items as $item ) {
			$q = self::text( $item['q'] );
			$a = self::text( $item['a'] );
			if ( '' === $q || '' === $a ) {
				continue; // Une question sans réponse n'est pas une FAQ.
			}
			$rows[] = array( $q, $a );
		}
		if ( ! $rows ) {
			return;
		}
		$title = self::text( $block['title'] );
		?>
		<?php if ( '' !== $title ) : ?>
			<h2 class="aod-landing__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<div class="aod-landing__faq">
			<?php foreach ( $rows as $row ) : ?>
				<details class="aod-landing__faq-item">
					<summary class="aod-landing__faq-q"><?php echo esc_html( $row[0] ); ?></summary>
					<?php // `esc_html()` PUIS `wpautop()` — cf. render_text(). ?>
					<div class="aod-landing__faq-a"><?php echo wpautop( esc_html( $row[1] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Bloc « Urgence » — sur des chiffres RÉELS, ou rien du tout.
	 *
	 * C'est le bloc où la tentation de mentir est la plus forte, et c'est justement là
	 * qu'il ne faut pas céder : le stock affiché est celui de la fiche produit, et le
	 * compte à rebours tombe à zéro à l'instant EXACT où WooCommerce retire la promotion.
	 * Ni faux compteur, ni rebours qui se réarme à chaque visite. Un client qui commande
	 * « les 2 dernières pièces » et attend trois semaines ne revient pas — et il le dit.
	 *
	 * Faute de chiffre réel (stock non géré, promotion sans date de fin), le bloc ne
	 * s'affiche PAS. Il n'invente rien pour remplir l'espace.
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_urgency( $block, $product_id ) {
		$msg = self::text( $block['text'] );

		if ( 'sale' === $block['mode'] ) {
			$end = $this->sale_ends_at( $product_id );
			if ( ! $end ) {
				return;
			}
			$this->render_countdown( $msg, $end );
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->managing_stock() ) {
			return; // Sans gestion de stock, il n'y a AUCUN chiffre à afficher.
		}
		$left = $product->get_stock_quantity();
		if ( null === $left || $left <= 0 ) {
			return; // Rupture : le formulaire le dit déjà, l'urgence n'a plus de sens.
		}
		$left = (int) $left;
		$max  = (int) $block['max'];
		if ( $max > 0 && $left > $max ) {
			return; // Au-dessus du seuil : ce n'est pas urgent, on n'en parle pas.
		}

		if ( '' === $msg ) {
			/* translators: %d : nombre d'exemplaires réellement en stock. */
			$msg = sprintf( _n( 'Plus que %d exemplaire en stock', 'Plus que %d exemplaires en stock', $left, 'aod-cod-form' ), $left );
		} else {
			// `str_replace`, jamais `sprintf` : la chaîne vient du gérant. Un « -50 % » tapé
			// dans le champ ferait tomber sprintf() en erreur fatale (PHP 8) — sa boutique
			// afficherait une page blanche. Un jeton remplacé ne peut rien faire de tel.
			$msg = str_replace( '%d', number_format_i18n( $left ), $msg );
		}
		?>
		<p class="aod-landing__urgency">
			<span class="aod-landing__urgency-icon" aria-hidden="true">🔥</span>
			<span><?php echo esc_html( $msg ); ?></span>
		</p>
		<?php
	}

	/**
	 * Le compte à rebours d'une promotion.
	 *
	 * Le serveur écrit déjà les bons chiffres : sans JavaScript, le visiteur voit un
	 * rebours figé — juste, mais immobile —, pas un trou. Le script ne fait que les
	 * faire avancer, et n'a donc AUCUNE chaîne à traduire : les unités (j, h, min, s)
	 * sont rendues ici, par `gettext`.
	 *
	 * @param string $msg Message du gérant (facultatif).
	 * @param int    $end Timestamp de fin.
	 */
	protected function render_countdown( $msg, $end ) {
		$left  = max( 0, $end - time() );
		$units = array(
			'd' => array( (int) floor( $left / DAY_IN_SECONDS ), _x( 'j', 'jours (compte à rebours)', 'aod-cod-form' ) ),
			'h' => array( (int) floor( ( $left % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ), _x( 'h', 'heures (compte à rebours)', 'aod-cod-form' ) ),
			'm' => array( (int) floor( ( $left % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ), _x( 'min', 'minutes (compte à rebours)', 'aod-cod-form' ) ),
			's' => array( (int) ( $left % MINUTE_IN_SECONDS ), _x( 's', 'secondes (compte à rebours)', 'aod-cod-form' ) ),
		);
		if ( '' === $msg ) {
			$msg = __( 'Fin de l’offre dans', 'aod-cod-form' );
		}
		?>
		<div class="aod-landing__urgency aod-landing__urgency--sale">
			<span class="aod-landing__urgency-icon" aria-hidden="true">⏳</span>
			<span class="aod-landing__urgency-msg"><?php echo esc_html( $msg ); ?></span>
			<?php // `dir="ltr"` : un rebours se lit « 2 j 04 h », dans cet ordre, y compris en arabe. ?>
			<span class="aod-landing__countdown" dir="ltr" data-end="<?php echo esc_attr( (string) $end ); ?>">
				<?php foreach ( $units as $key => $unit ) : ?>
					<span class="aod-landing__cd-unit">
						<b data-cd="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( str_pad( (string) $unit[0], 2, '0', STR_PAD_LEFT ) ); ?></b><i><?php echo esc_html( $unit[1] ); ?></i>
					</span>
				<?php endforeach; ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Bloc « Formulaire de commande ».
	 *
	 * On appelle le POINT D'ENTRÉE UNIQUE du formulaire COD — celui qu'utilisent
	 * déjà le hook produit, le shortcode, le bloc Gutenberg et le widget Elementor.
	 * Offres, variantes, packs, anti-bot 5 couches (dont 4 sur un clone neuf), prospects et pixels
	 * suivent gratuitement. Recopier le formulaire ici, ce serait se condamner à
	 * maintenir deux tunnels d'achat.
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_form( $block, $product_id ) {
		$title = self::text( $block['title'] );
		if ( '' !== $title ) {
			echo '<h2 class="aod-landing__title">' . esc_html( $title ) . '</h2>';
		}
		echo AOD_COD_Form::instance()->get_form_html( $product_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Bloc « Réassurance » (paiement à la livraison, 58 wilayas, retour…).
	 *
	 * @param array $block
	 * @param int   $product_id
	 */
	protected function render_trust( $block, $product_id ) {
		$items = is_array( $block['items'] ) ? $block['items'] : array();
		if ( ! $items ) {
			return;
		}
		?>
		<ul class="aod-landing__trust">
			<?php foreach ( $items as $item ) :
				$text = self::text( $item['text'] );
				if ( '' === $text ) {
					continue;
				}
				?>
				<li class="aod-landing__trust-item">
					<?php if ( '' !== $item['icon'] ) : ?>
						<span class="aod-landing__trust-icon" aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span>
					<?php endif; ?>
					<span><?php echo esc_html( $text ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
