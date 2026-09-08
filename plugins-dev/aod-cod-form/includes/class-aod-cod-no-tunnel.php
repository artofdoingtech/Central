<?php
/**
 * Règle non négociable : côté acheteur, il n'y a NI panier NI compte.
 *
 * La commande est créée directement par le formulaire COD (wc_create_order()) —
 * le plugin n'utilise à aucun moment WC()->cart. Le tunnel WooCommerce standard
 * (panier → commande → compte) est donc du code mort, mais WooCommerce continue
 * de l'exposer : boutons « Ajouter au panier » dans la grille, URLs /cart,
 * /checkout, /mon-compte, entrées de menu, icône panier du thème.
 *
 * Cette classe le débranche. Elle agit UNIQUEMENT côté public : l'admin garde
 * son WooCommerce intact (le gérant doit pouvoir éditer une commande).
 *
 * Deux niveaux, volontairement :
 *
 *   1. La BARRIÈRE — les URLs du tunnel renvoient vers la Boutique. Rien ne
 *      permet d'acheter en dehors du formulaire COD, même en tapant l'URL à la
 *      main. C'est ce niveau qui garantit la règle ; il ne dépend d'aucun thème.
 *
 *   2. Le NETTOYAGE VISUEL — retirer les boutons et l'icône panier. Il dépend en
 *      partie du thème du clone : on couvre le cas générique (le filtre Woo du
 *      bouton de grille, que tout thème correct traverse) et Astra (qui rend sa
 *      propre icône). Un clone sur un autre thème doit VÉRIFIER son en-tête —
 *      voir CHECKLIST-THEME-CHILD.md.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_No_Tunnel {

	/** @var AOD_COD_No_Tunnel|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// L'admin garde WooCommerce complet : le gérant édite les commandes, et
		// /gestion s'appuie dessus. On ne touche qu'au front.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// ── 1. La barrière : le panier ne peut plus être rempli ───────────
		//
		// LE POINT CLÉ. Un bouton « Ajouter au panier », ce n'est pas une classe CSS,
		// c'est un lien vers `?add-to-cart=123`. Masquer le bouton ne ferme rien ; il
		// faut fermer le lien ET l'action serveur qui le traite.
		//
		// a) L'action serveur. Sans elle, `?add-to-cart=123` — forgée à la main, ou
		//    laissée par un thème qu'on n'aura pas anticipé — ne fait plus rien.
		add_action( 'wp_loaded', array( $this, 'kill_add_to_cart_action' ), 5 );

		// b) L'URL. Tout bouton d'ajout au panier, D'OÙ QU'IL VIENNE, mène désormais à
		//    la page produit — c'est-à-dire au formulaire COD. Les thèmes qui rendent
		//    leur propre bouton (Astra et son « ast-on-card-button », qui ne passe par
		//    aucun hook WooCommerce) sont couverts eux aussi : ils appellent tous
		//    $product->add_to_cart_url(), donc ce filtre.
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'product_url' ), 99, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'cta_text' ), 99 );
		add_filter( 'woocommerce_product_add_to_cart_description', array( $this, 'cta_text' ), 99 );

		// b-bis) SA CLASSE (porté de Template-Full, 2026-09-08). Nous possédons déjà
		//    l'URL et le libellé de ce bouton : sans son nom de classe, `aod-cod-pages.css`
		//    ne peut pas le cibler sans écrire une règle sur le thème du clone.
		add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'loop_cta_args' ), 99, 2 );

		// c) Le JS. wc-add-to-cart intercepte le clic sur `.ajax_add_to_cart` et
		//    ajoute au panier en AJAX, sans jamais suivre le href. Déchargé, le clic
		//    suit le lien — donc va sur la page produit.
		//    wc-cart-fragments, lui, interroge le panier à CHAQUE page : une requête
		//    réseau par page pour rien, sur un trafic mobile.
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_cart_scripts' ), 99 );

		// d) Les URLs du tunnel.
		add_action( 'template_redirect', array( $this, 'block_tunnel_urls' ) );

		// ── 2. Le nettoyage visuel ────────────────────────────────────────
		// Entrées « Panier / Commande / Mon compte » dans les menus assignés.
		add_filter( 'wp_nav_menu_objects', array( $this, 'purge_menu_items' ), 99 );

		// Filet : si AUCUN menu n'est assigné, WordPress retombe sur wp_page_menu(),
		// qui liste toutes les pages publiées — y compris le tunnel, s'il n'a pas
		// encore été purgé sur ce clone.
		add_filter( 'wp_list_pages_excludes', array( $this, 'exclude_tunnel_pages' ) );

		// Astra rend sa propre icône panier dans l'en-tête (elle ne passe par aucun
		// hook WooCommerce). Même précédent que remove_astra_add_to_cart().
		add_filter( 'astra_woo_header_cart_icon', '__return_false' );
	}

	/**
	 * Débranche le traitement de `?add-to-cart=123` par WooCommerce.
	 *
	 * C'est la garantie de fond : même si un thème, un plugin ou une URL forgée
	 * réintroduit un lien d'ajout au panier, il ne se passe plus rien. Le panier de
	 * ce site ne peut pas être rempli.
	 *
	 * Sans danger pour la commande : le formulaire COD n'utilise jamais le panier,
	 * il crée la commande directement (wc_create_order()).
	 */
	public function kill_add_to_cart_action() {
		remove_action( 'wp_loaded', array( 'WC_Form_Handler', 'add_to_cart_action' ), 20 );
	}

	/**
	 * Tout « Ajouter au panier » mène à la page produit — donc au formulaire COD.
	 *
	 * @param string     $url     URL d'origine (`?add-to-cart=123`).
	 * @param WC_Product $product Produit concerné.
	 * @return string
	 */
	public function product_url( $url, $product = null ) {
		if ( $product instanceof WC_Product ) {
			return $product->get_permalink();
		}
		return $url;
	}

	/**
	 * Libellé du bouton. « Ajouter au panier » serait un mensonge : le clic mène au
	 * formulaire de commande.
	 *
	 * @return string
	 */
	public function cta_text() {
		return __( 'Commander', 'aod-cod-form' );
	}

	/**
	 * Ajoute NOTRE classe au bouton d'achat des grilles (porté de Template-Full).
	 *
	 * Ce bouton est rendu par `woocommerce_template_loop_add_to_cart()` sur la
	 * Boutique, l'Accueil et les produits liés. La classe s'AJOUTE, jamais ne
	 * remplace : Astra et les extensions passent par ce même filtre.
	 *
	 * @param array       $args    Arguments du bouton (dont `class`).
	 * @param \WC_Product $product Produit de la boucle.
	 * @return array
	 */
	public function loop_cta_args( $args, $product = null ) {
		unset( $product );
		if ( ! is_array( $args ) ) {
			return $args;
		}
		$classes       = isset( $args['class'] ) ? trim( (string) $args['class'] ) : '';
		$args['class'] = trim( $classes . ' aod-loop-cta' );
		return $args;
	}

	/**
	 * Décharge les scripts du panier.
	 */
	public function dequeue_cart_scripts() {
		wp_dequeue_script( 'wc-add-to-cart' );
		wp_dequeue_script( 'wc-cart-fragments' );
	}

	/**
	 * Les URLs du tunnel renvoient vers la Boutique.
	 *
	 * Sur un clone correctement installé, les pages Panier / Commande / Mon compte
	 * sont supprimées (cf. scripts/pages-publiques.sh) : ces URLs sont alors de
	 * vraies 404 et cette méthode ne sert à rien. Elle existe pour le clone où
	 * elles subsistent — une page laissée en ligne suffit à rouvrir le tunnel.
	 *
	 * Redirection permanente vers la Boutique plutôt que 404 : ces pages ne
	 * reviendront jamais dans ce template, et un moteur qui les a indexées doit
	 * atterrir sur quelque chose d'utile.
	 */
	public function block_tunnel_urls() {
		if ( ! function_exists( 'is_cart' ) ) {
			return;
		}
		if ( ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			return;
		}

		$shop = wc_get_page_permalink( 'shop' );
		if ( ! $shop ) {
			$shop = home_url( '/' );
		}

		wp_safe_redirect( $shop, 301 );
		exit;
	}

	/**
	 * Retire du menu toute entrée qui pointe vers le panier, la commande ou le compte.
	 *
	 * On compare les IDs de page WooCommerce, pas les libellés : un clone peut avoir
	 * renommé « Cart » en « Panier » ou en arabe.
	 *
	 * @param array $items Entrées du menu.
	 * @return array
	 */
	public function purge_menu_items( $items ) {
		$banned = $this->tunnel_page_ids();
		if ( empty( $banned ) || ! is_array( $items ) ) {
			return $items;
		}

		foreach ( $items as $key => $item ) {
			if ( isset( $item->object_id, $item->object )
				&& 'page' === $item->object
				&& in_array( (int) $item->object_id, $banned, true )
			) {
				unset( $items[ $key ] );
			}
		}

		return array_values( $items );
	}

	/**
	 * @param array $excludes IDs de pages exclues du repli wp_page_menu().
	 * @return array
	 */
	public function exclude_tunnel_pages( $excludes ) {
		return array_merge( (array) $excludes, $this->tunnel_page_ids() );
	}

	/**
	 * IDs des pages du tunnel WooCommerce encore présentes sur ce site.
	 *
	 * @return int[]
	 */
	protected function tunnel_page_ids() {
		$ids = array();

		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			$id = (int) wc_get_page_id( $page );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

}
