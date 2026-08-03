<?php
/**
 * Traduction FR→AR du TITRE et de la DESCRIPTION d'un produit — étape 7.2.
 *
 * `gettext`/`.po` ne traduit que le CODE. Le titre et la description sont saisis
 * par le gérant : ils n'y passent jamais. On les rend donc bilingues en filtrant
 * la sortie du cœur — le FR reste en base (`post_title`/`post_content`), l'arabe
 * vit dans une méta parallèle (`_aod_title_ar` / `_aod_desc_ar`, convention 7.1).
 *
 * Cette classe BRANCHE l'armature dormante `AOD_COD_Catalog_I18n` (7.1). Elle ne
 * réimplémente aucune garde : elle appelle `render()`, qui décide.
 *
 * ⚠️ ON NE FILTRE AUCUN GETTER WOOCOMMERCE — et surtout pas
 * `woocommerce_product_get_name`. Raison (chaîne vérifiée dans le cœur) :
 *
 *   AOD_COD_Form::submit()  ->  $order->add_product( $product, $qty )
 *     -> WC_Abstract_Order::add_product()
 *       -> WC_Order_Item_Product::set_product()
 *         -> $this->set_name( $product->get_name() )      // ← PERSISTÉ en base
 *           -> WC_Data::get_prop() -> apply_filters( 'woocommerce_product_get_name' )
 *
 * `add_product()` tourne dans `wp_ajax_nopriv_aod_cod_submit` — une requête
 * ACHETEUR, avec l'arabe potentiellement actif — et GRAVE le nom dans la commande.
 * Filtrer ce getter écrirait donc l'arabe dans `order_item_name` : les bordereaux
 * transporteur, les e-mails et WhatsApp le rejoueraient ensuite pour toujours, et
 * le matching serveur sur le nom FR canonique casserait. Les commandes déjà
 * créées seraient irrattrapables.
 *
 * → On ne filtre QUE des hooks d'AFFICHAGE (`the_title`, `the_content`,
 * `single_post_title`) : `add_product()` n'en traverse aucun. Les rares sites de
 * rendu BRUTS que nous possédons (héro de la page de vente, liste d'un pack)
 * appellent `AOD_COD_Catalog_I18n::render()` explicitement, chez eux.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Catalog_Filters {

	/** Méta portant le titre arabe (le FR canonique reste `post_title`). */
	const META_TITLE = '_aod_title_ar';

	/** Méta portant la description arabe (le FR canonique reste `post_content`). */
	const META_DESC = '_aod_desc_ar';

	/** Term-meta portant le nom arabe d'une catégorie (le FR reste `$term->name`). */
	const META_CAT_NAME = '_aod_cat_name_ar';

	/** @var AOD_COD_Catalog_Filters|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Callbacks `public static` : testables directement, sans Reflection.
		add_filter( 'the_title', array( __CLASS__, 'filter_title' ), 10, 2 );
		// Priorité 9 — NON NÉGOCIABLE : `wpautop` est en 10 (wp-includes/default-filters.php).
		// À 10, notre filtre s'exécuterait APRÈS lui : le FR serait paragraphé, puis
		// remplacé par de l'arabe BRUT, sans <p> ni <br>. En 9 on passe après
		// `do_blocks` (9, enregistré par le cœur donc avant nous) et avant `wpautop`,
		// donc l'arabe traverse exactement la même mise en forme que le français.
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 9 );
		add_filter( 'single_post_title', array( __CLASS__, 'filter_single_post_title' ), 10, 2 );

		// Les NOMS DE CATÉGORIES (7.5). UN SEUL hook, et c'est voulu — cf. filter_term().
		add_filter( 'get_term', array( __CLASS__, 'filter_term' ), 10, 2 );
	}

	/**
	 * Le nom d'une catégorie produit — étape 7.5.
	 *
	 * ⚠️ Ici on filtre l'OBJET, pas une chaîne : c'est la différence avec tout ce qui
	 * précède, et ce n'est pas un choix de style. WooCommerce échoue `$category->name`
	 * SANS aucun hook, aux deux sites qui comptent :
	 *
	 *   • l'accueil  : `echo esc_html( $category->name )`  (wc-template-functions.php:1253)
	 *   • le fil d'ariane : `$term->name` lu sur l'objet   (class-wc-breadcrumb.php:177…)
	 *
	 * Il n'y a donc rien à filtrer « après coup » : le nom doit DÉJÀ être en arabe dans
	 * l'objet quand Woo l'échoue.
	 *
	 * 🔎 `get_term` SUFFIT — ne pas ajouter `get_terms`, `single_term_title`,
	 * `woocommerce_product_categories` ni `term_name` : tous redondants ou inopérants.
	 * `WP_Term_Query` passe par `populate_terms()` (cache HIT compris,
	 * class-wp-term-query.php:795/826), qui appelle `get_term()` pour CHAQUE terme (:1131) ;
	 * et `get_term_by()` se termine par `return get_term( … )` (taxonomy.php:1159), donc
	 * l'objet interrogé d'une archive y passe aussi.
	 *
	 * 🚨 Ce n'est PAS une entorse à la règle « affichage seulement » de la 7.2 : cette
	 * règle protégeait les COMMANDES (`add_product()` persiste `$product->get_name()`).
	 * Aucun nom de catégorie n'entre dans une commande — la clé d'une catégorie est son
	 * `term_id`, jamais son nom.
	 *
	 * L'ORDRE des vérifications ci-dessous est une contrainte, pas du style :
	 * `populate_terms()` nous appelle pour chaque terme de chaque requête — des dizaines
	 * de fois par page, site en FR compris. La lecture de méta vient donc en DERNIER,
	 * après la garde (d'autant que `term_exists()`/`get_term_by()` posent
	 * `update_term_meta_cache => false` : chaque lecture partirait en requête SQL).
	 *
	 * @param mixed  $_term    Terme produit par le cœur (WP_Term, ou WP_Error/objet brut).
	 * @param string $taxonomy Taxonomie interrogée.
	 * @return mixed
	 */
	public static function filter_term( $_term, $taxonomy = '' ) {
		// 1. Garde de taxonomie — un test de chaîne, le moins cher.
		if ( 'product_cat' !== $taxonomy || ! $_term instanceof WP_Term ) {
			return $_term;
		}
		// 2. Garde de portée — aucune requête. Ferme sur FR, /gestion, wp-admin, admin-ajax.
		if ( ! AOD_COD_Catalog_I18n::active() ) {
			return $_term;
		}
		// 3. Seulement maintenant : la lecture de méta.
		$ar = get_term_meta( (int) $_term->term_id, self::META_CAT_NAME, true );
		if ( '' === trim( (string) $ar ) ) {
			// Invariant n°1 : pas de traduction → on ne touche à RIEN, pas même un clone.
			// (`pick()` trimmerait ses deux côtés : le nom ressortirait modifié.)
			return $_term;
		}
		// 4. CLONE obligatoire : `$_term` sort du cache d'objets (`wp_cache_get( $id,
		// 'terms' )`, avant le apply_filters de taxonomy.php:1021). Muter en place
		// empoisonnerait le cache pour toute la requête, hors de notre garde comprise.
		// WP_Term ne porte que des scalaires → un clone simple suffit.
		$term       = clone $_term;
		$term->name = AOD_COD_Catalog_I18n::render( $_term->name, $ar );
		return $term;
	}

	/**
	 * Le titre : grille de l'accueil, boucle Boutique, <h1> de la fiche, fil d'ariane.
	 *
	 * @param string $title Titre canonique produit par le cœur.
	 * @param int    $id    ID du post. `the_title` est aussi appelé sur les pages et
	 *                      sur les entrées de menu (avec l'ID de la page CIBLE) : la
	 *                      garde de type est ce qui les laisse tranquilles.
	 * @return string
	 */
	public static function filter_title( $title, $id = 0 ) {
		$id = (int) $id;
		if ( $id <= 0 || 'product' !== get_post_type( $id ) ) {
			return $title;
		}
		return self::swap( $title, get_post_meta( $id, self::META_TITLE, true ) );
	}

	/**
	 * La description (onglet « Description » de la fiche produit).
	 *
	 * ⚠️ `the_content` ne reçoit PAS d'ID (`apply_filters( 'the_content', $content )`)
	 * — on lit donc le post courant. Sans cette garde, on traduirait le contenu de
	 * n'importe quelle page (Accueil, Contact…), dont le texte vit dans le code et
	 * passe déjà par gettext.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function filter_content( $content ) {
		$post = get_post();
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return $content;
		}
		return self::swap( $content, get_post_meta( $post->ID, self::META_DESC, true ) );
	}

	/**
	 * La balise <title> du document.
	 *
	 * ⚠️ Ce filtre reçoit un **WP_Post**, pas un ID (wp-includes/general-template.php :
	 * `apply_filters( 'single_post_title', $_post->post_title, $_post )`). Réutiliser
	 * le callback de `the_title` ici passerait un objet à `get_post_meta()`, qui
	 * rendrait `''` EN SILENCE : le <title> ne serait jamais traduit, sans la moindre
	 * erreur PHP.
	 *
	 * @param string       $title
	 * @param WP_Post|null $post
	 * @return string
	 */
	public static function filter_single_post_title( $title, $post = null ) {
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return $title;
		}
		return self::swap( $title, get_post_meta( $post->ID, self::META_TITLE, true ) );
	}

	/**
	 * Rendre l'arabe — ou ne RIEN toucher.
	 *
	 * Le court-circuit « AR vide » n'est pas une optimisation, c'est ce qui tient
	 * l'invariant « un produit non traduit rend exactement comme avant » : quand le
	 * site est en arabe, la garde de `render()` est OUVERTE, et `pick()` renverrait
	 * alors un FR **trimmé** — donc modifié. Sur une description (qui arrive avec des
	 * sauts de ligne encadrants), ça se verrait. Ici, sans traduction, on rend
	 * l'argument d'origine à l'octet près.
	 *
	 * @param string $fr Texte canonique du cœur.
	 * @param mixed  $ar Méta arabe ('' si absente).
	 * @return string
	 */
	protected static function swap( $fr, $ar ) {
		if ( '' === trim( (string) $ar ) ) {
			return $fr;
		}
		return AOD_COD_Catalog_I18n::render( $fr, $ar );
	}
}
