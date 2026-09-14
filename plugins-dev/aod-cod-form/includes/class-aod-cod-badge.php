<?php
/**
 * Étiquette de carte produit (Nouveau / Promo / Pack / Meilleure vente).
 *
 * Choix MANUEL par produit, fait dans /gestion → Produits (plugin
 * `aod-client-dashboard`, class-aod-cd-section-products.php) — une seule
 * méta, une seule source, même patron que `_aod_landing_enabled` (cf.
 * class-aod-cod-landing.php) : on écrit la méta, ou on la supprime, jamais de
 * valeur vide en base.
 *
 * Catalogue FERMÉ de types, dont les libellés sont du CHROME (identiques
 * chez tous les clients, cf. TEMPLATE.md §5) : ils passent par __()/.po,
 * jamais par un couple { fr, ar } — contrairement au contenu marchand saisi
 * librement (titre, arguments de vente…). Cf. docs/LANDING-PRODUIT.md §2.3.
 *
 * 🔁 « Promo » a réintégré ce catalogue le 2026-09-14 (retour owner), APRÈS
 * que le badge « Promo ! » natif de Woo/Astra a été supprimé partout
 * (`__return_empty_string` sur `woocommerce_sale_flash`, PLUS un
 * `display:none` CSS — cf. aod-cod-pages.css — pour le second chemin de
 * rendu du thème qui ignore ce filtre). Le risque de doublon qui l'avait fait
 * retirer une première fois a disparu avec sa cause : il n'existe plus de
 * badge automatique en face pour dupliquer ou pour mentir. « Promo » ici est
 * un choix éditorial du gérant comme les trois autres, jamais lié au prix
 * réel — le prix barré/réduit (`.price ins`/`.price del`) reste, lui, géré
 * par Woo, indépendamment de cette méta.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Badge {

	/** Meta produit — une valeur du catalogue ci-dessous, ou absente. */
	const META = '_aod_badge';

	/** @var AOD_COD_Badge|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'render_loop' ), 5 );
		// Coupe le badge « Promo ! » natif partout où Woo le rend (boucle ET
		// fiche produit — même filtre pour les deux).
		//
		// 🔴 Priorité 20, PAS 10 — corrigé en test (2026-09-14) : Astra accroche
		// SON PROPRE filtre sur `woocommerce_sale_flash`, à la priorité 10 lui
		// aussi (`Astra_Woocommerce::sale_flash()`), et il IGNORE totalement le
		// `$markup` reçu — il recalcule sa propre pastille depuis `$product`,
		// quoi qu'on lui passe. À priorité égale, `plugins_loaded` (nous)
		// s'enregistre avant `after_setup_theme` (le thème) : notre '' partait
		// donc EN PREMIER dans la chaîne, et Astra l'écrasait juste après —
		// mesuré : `apply_filters()` rendait encore le badge natif malgré notre
		// retour vide. Une priorité postérieure nous fait passer APRÈS Astra :
		// c'est alors NOTRE '' qui a le dernier mot, quel que soit ce que le
		// thème vient de calculer.
		add_filter( 'woocommerce_sale_flash', '__return_empty_string', 20 );
	}

	/**
	 * Catalogue fermé { slug => libellé }. Calculé à CHAQUE appel, jamais mis
	 * en cache statique : __() doit lire la langue courante (cookie
	 * `aod_lang`), qui peut changer d'une requête à l'autre.
	 *
	 * @return array<string,string>
	 */
	public static function types() {
		return array(
			'new'        => __( 'Nouveau', 'aod-cod-form' ),
			'promo'      => __( 'Promo', 'aod-cod-form' ),
			'pack'       => __( 'Pack', 'aod-cod-form' ),
			'bestseller' => __( 'Meilleure vente', 'aod-cod-form' ),
		);
	}

	/**
	 * @param int $product_id
	 * @return string Le slug choisi pour ce produit, ou '' si aucun/invalide.
	 */
	public static function get( $product_id ) {
		$slug  = (string) get_post_meta( (int) $product_id, self::META, true );
		$types = self::types();
		return isset( $types[ $slug ] ) ? $slug : '';
	}

	/**
	 * Markup de la pastille pour UN produit, ou '' s'il n'en porte pas.
	 *
	 * Appelé depuis la boucle Woo standard (`render_loop()` ci-dessous —
	 * Boutique, Accueil) ET depuis la grille « Complétez votre commande »
	 * construite à la main (`AOD_COD_Cart::render_suggestions()`), seul autre
	 * endroit public à afficher des cartes produit.
	 *
	 * @param int $product_id
	 * @return string
	 */
	public static function markup( $product_id ) {
		$slug = self::get( $product_id );
		if ( '' === $slug ) {
			return '';
		}
		$types = self::types();
		return '<span class="aod-badge aod-badge--' . esc_attr( $slug ) . '">' . esc_html( $types[ $slug ] ) . '</span>';
	}

	/** Boucle Woo standard (Boutique, Accueil, produits liés natifs). */
	public function render_loop() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		echo self::markup( $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput -- déjà esc_html()/esc_attr() dans markup().
	}
}
