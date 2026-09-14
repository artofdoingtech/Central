<?php
/**
 * Cœur : rendu du formulaire COD + traitement de la commande.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Form {

	/** @var AOD_COD_Form|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Le panier COD est-il activé sur cette boutique ? (Phase 35)
	 *
	 * Option `aod_cod_cart`, éditée par le gérant depuis /gestion → Livraison
	 * (carte réservée au gérant). Absente ou ≠ '1' = coupé : un clone qui ne
	 * configure rien garde le formulaire direct seul, comportement historique.
	 *
	 * @return bool
	 */
	public static function cart_enabled() {
		return '1' === get_option( 'aod_cod_cart', '' );
	}

	private function __construct() {
		// Assets front.
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );

		// Affichage auto sur la page produit, juste après le bouton d'ajout au panier.
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_auto' ), 35 );

		// Boutique 100 % COD : on retire le formulaire natif (dropdown variations + « Ajouter au panier »)
		// pour ne garder QUE le formulaire COD (évite la redondance du sélecteur de couleur).
		add_action( 'woocommerce_single_product_summary', array( $this, 'strip_native_add_to_cart' ), 1 );
		add_filter( 'astra_woo_single_product_structure', array( $this, 'remove_astra_add_to_cart' ) );

		// ⚠️ REVIREMENT (2026-09-13, même jour) : la description LONGUE a
		// brièvement vécu dans la colonne galerie, en volet dépliable (cf.
		// l'historique de `tests/unit/test-phpcs-ignore.php`, census
		// 192 → 193 → 192) — retour owner : elle doit être « à côté des avis »,
		// pas dans la galerie. L'onglet natif Woo (`woocommerce_default_
		// product_tabs()`) suffit tel quel ; rien à retirer ni à réimprimer
		// ailleurs.

		// Galerie produit : on injecte les photos des options (couleurs…) comme de vraies
		// diapos du carrousel WooCommerce, et on tague chaque diapo avec son ID de média
		// pour que le JS puisse sauter à la bonne photo quand une couleur est choisie.
		add_filter( 'woocommerce_product_get_gallery_image_ids', array( $this, 'inject_option_images_into_gallery' ), 10, 2 );
		add_filter( 'woocommerce_single_product_image_thumbnail_html', array( $this, 'tag_gallery_slide_attachment' ), 10, 2 );

		// Shortcode : [aod_cod_form product_id="123"]
		add_shortcode( 'aod_cod_form', array( $this, 'shortcode' ) );

		// AJAX (connecté + visiteur).
		add_action( 'wp_ajax_aod_cod_submit', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_aod_cod_submit', array( $this, 'handle_submit' ) );

		// AJAX : communes éligibles au stop-desk pour une wilaya (filtrage du formulaire).
		add_action( 'wp_ajax_aod_cod_desk_communes', array( $this, 'handle_desk_communes' ) );
		add_action( 'wp_ajax_nopriv_aod_cod_desk_communes', array( $this, 'handle_desk_communes' ) );
	}

	/**
	 * AJAX : renvoie les communes d'une wilaya qui ont réellement un point relais
	 * (stop-desk) chez le livreur d'envoi automatique, pour que le formulaire
	 * public ne propose « bureau » que là où c'est possible.
	 *
	 * Réponse : { gated:false } si le livreur ne filtre pas par commune (ou n'est
	 * pas configuré), sinon { gated:true, communes:[clés normalisées] }.
	 */
	public function handle_desk_communes() {
		check_ajax_referer( 'aod_cod_nonce', 'nonce' );

		$wilaya = isset( $_POST['wilaya'] ) ? absint( $_POST['wilaya'] ) : 0;
		if ( $wilaya < 1 || $wilaya > 58 ) {
			wp_send_json_success( array( 'gated' => false ) );
		}

		// 🚨 LA RÉSOLUTION PASSE PAR `carrier_for()` — LA SEULE DU PLUGIN (47.1).
		//
		// Cet endroit lisait le réglage global À LA MAIN (`carrier( auto_settings()
		// ['carrier'] )`), c'est-à-dire l'étape 2 de la résolution, sans son étape 3
		// (« un seul transporteur configuré, c'est forcément celui-là »). Les deux
		// pouvaient donc répondre des transporteurs DIFFÉRENTS : une boutique laissée
		// sur le défaut en dur `ecotrack`, jamais configuré, mais raccordée à Maystro,
		// voyait ici « inconnu → on n'impose rien » et offrait « Retrait au bureau » à
		// l'acheteur, quand le colis serait construit pour Maystro, qui n'a pas de
		// stop-desk. L'acheteur choisit un mode de livraison qui n'existe pas.
		//
		// ⚠️ R4 de `test-carrier-par-commande.php` ne pouvait pas le voir : elle compte
		// le littéral `1 === count( $configured )`, présent une seule fois. *Compter
		// l'unicité d'une IMPLÉMENTATION ne dit rien du nombre de ses CONCURRENTES.*
		if ( ! class_exists( 'AOD_Shipping' ) ) {
			wp_send_json_success( array( 'gated' => false ) );
		}
		$carrier = AOD_Shipping::instance()->carrier_for();

		// Rien de résolu (aucun configuré, ou défaut non configuré et plusieurs
		// candidats) → on n'impose rien : l'option reste visible.
		if ( is_wp_error( $carrier ) || ! $carrier instanceof AOD_Carrier ) {
			wp_send_json_success( array( 'gated' => false ) );
		}

		// Livreur résolu — donc configuré — mais qui ne gère PAS le stop-desk
		// (ex. E-com Delivery, Maystro) → l'option « bureau » est masquée.
		if ( ! $carrier->supports_stopdesk() ) {
			wp_send_json_success( array( 'desk' => false ) );
		}

		// Le bureau de retrait n'est plus choisi par l'acheteur : il est déduit de la
		// wilaya + commune à l'envoi (cf. AOD_Carrier::resolve_stopdesk_code()). On se
		// contente donc d'autoriser/restreindre l'option « bureau » par commune.
		$set = $carrier->desk_communes( $wilaya );
		if ( ! is_array( $set ) ) {
			// null = filtrage non applicable / référentiel indisponible : on n'impose rien.
			wp_send_json_success( array( 'gated' => false ) );
		}

		wp_send_json_success( array( 'gated' => true, 'communes' => array_values( $set ) ) );
	}

	/**
	 * Le livreur d'envoi automatique gère-t-il le stop-desk (point relais) ?
	 *
	 * Sert à masquer l'option « bureau » et à ramener une commande desk → domicile
	 * lorsque le livreur configuré ne fait que de la livraison à domicile. On ne
	 * restreint QUE si le livreur est réellement configuré et déclare ne pas gérer
	 * le stop-desk ; tout cas indéterminé reste permissif (option visible).
	 *
	 * @return bool
	 */
	protected function active_carrier_supports_desk() {
		if ( ! class_exists( 'AOD_Shipping' ) ) {
			return true;
		}
		// Même résolution que `handle_desk_communes()` — et c'est la seule du plugin
		// (47.1). Ces deux sites lisaient le réglage global à la main, donc sans le
		// repli « un seul configuré » : ils pouvaient offrir le stop-desk pour un
		// transporteur qui n'en fait pas.
		$carrier = AOD_Shipping::instance()->carrier_for();
		if ( $carrier instanceof AOD_Carrier && ! $carrier->supports_stopdesk() ) {
			return false;
		}
		return true;
	}

	/**
	 * Retire le formulaire d'ajout au panier natif de WooCommerce sur la page produit
	 * (dropdown variations pour les produits variables, bouton « Ajouter au panier » pour les simples).
	 * La commande passe exclusivement par le formulaire COD ci-dessous.
	 */
	public function strip_native_add_to_cart() {
		// Thèmes standard : le bouton/dropdown est rendu par ce hook à la priorité 30.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	}

	/**
	 * Thème Astra : il ne passe PAS par le hook standard mais rend sa propre
	 * structure (Astra_Woocommerce::single_product_content_structure) qui appelle
	 * directement woocommerce_template_single_add_to_cart() pour le composant « add_cart ».
	 * On retire ce composant pour ne garder que le formulaire COD.
	 *
	 * @param array $structure Liste ordonnée des composants de la fiche produit.
	 * @return array
	 */
	public function remove_astra_add_to_cart( $structure ) {
		if ( is_array( $structure ) ) {
			$structure = array_values( array_diff( $structure, array( 'add_cart' ) ) );
		}
		return $structure;
	}

	/**
	 * Enregistre/charge CSS + JS et passe les données à JS.
	 */
	public function assets() {
		// Version basée sur la date de modif du fichier : invalide le cache navigateur à chaque édition.
		$css_path = AOD_COD_PATH . 'assets/css/aod-cod-form.css';
		$js_path  = AOD_COD_PATH . 'assets/js/aod-cod-form.js';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : AOD_COD_VERSION;
		$js_ver   = file_exists( $js_path ) ? (string) filemtime( $js_path ) : AOD_COD_VERSION;

		wp_register_style( 'aod-cod-form', AOD_COD_URL . 'assets/css/aod-cod-form.css', array(), $css_ver );
		wp_register_script( 'aod-cod-form', AOD_COD_URL . 'assets/js/aod-cod-form.js', array( 'jquery' ), $js_ver, true );

		// Construit une table légère pour le JS. communes = [{v: nom latin, l: libellé affiché}].
		$wilayas = array();
		foreach ( AOD_COD_Data::places() as $w ) {
			$code     = (int) $w['code'];
			$communes = array();
			foreach ( $w['communes'] as $c ) {
				$ar         = isset( $c['name_ar'] ) ? $c['name_ar'] : '';
				$communes[] = array(
					'v' => $c['name'],
					'l' => AOD_COD_Data::label( $c['name'], $ar ),
				);
			}
			$wilayas[ $code ] = array(
				'name'     => AOD_COD_Data::label( $w['name'], isset( $w['name_ar'] ) ? $w['name_ar'] : '' ),
				'communes' => $communes,
				'home'     => AOD_COD_Data::price_for( $code, 'home' ),
				'desk'     => AOD_COD_Data::price_for( $code, 'desk' ),
				'free'     => AOD_COD_Data::is_free_wilaya( $code ) ? 1 : 0,
			);
		}

		wp_localize_script( 'aod-cod-form', 'AOD_COD', array(
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'aod_cod_nonce' ),
			// Cible de la redirection post-commande (Boutique) — cf. showSuccess()
			// dans aod-cod-form.js et AOD_COD_Pages::render_thankyou_banner().
			'shop_url'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
			'wilayas'   => $wilayas,
			'currency'  => get_woocommerce_currency_symbol(),
			'free_shipping' => array(
				'threshold' => AOD_COD_Data::free_shipping_threshold(),
			),
			'i18n'      => array(
				'choose_wilaya'  => __( 'Choisir une wilaya', 'aod-cod-form' ),
				'choose_commune' => __( 'Choisir une commune', 'aod-cod-form' ),
				'choose_color'   => __( 'Veuillez choisir une couleur.', 'aod-cod-form' ),
				'choose_option'  => __( 'Veuillez sélectionner toutes les options.', 'aod-cod-form' ),
				'sending'        => __( 'Envoi en cours…', 'aod-cod-form' ),
				'delivery'       => __( 'Livraison', 'aod-cod-form' ),
				'total'          => __( 'Total', 'aod-cod-form' ),
				'free'           => __( 'Offerte', 'aod-cod-form' ),
				/* translators: %s: montant restant à ajouter */
				'free_hint'      => __( 'Plus que %s pour la livraison gratuite !', 'aod-cod-form' ),
				'free_active'    => __( '🎉 Livraison gratuite débloquée !', 'aod-cod-form' ),
				'phone_invalid'  => __( 'Numéro de téléphone invalide (ex : 0550 12 34 56).', 'aod-cod-form' ),
				'required'       => __( 'Veuillez remplir tous les champs obligatoires.', 'aod-cod-form' ),
				'prev_image'     => __( 'Image précédente', 'aod-cod-form' ),
				'next_image'     => __( 'Image suivante', 'aod-cod-form' ),
				'article'        => __( 'Article', 'aod-cod-form' ),
				'add_item'       => __( 'Ajouter un article', 'aod-cod-form' ),
				'remove_item'    => __( 'Retirer cet article', 'aod-cod-form' ),
				'cart_empty'     => __( 'Veuillez ajouter au moins un article.', 'aod-cod-form' ),
				'success_title'  => __( 'Merci ! Votre commande est bien enregistrée.', 'aod-cod-form' ),
				'order_label'    => __( 'Commande N°', 'aod-cod-form' ),
				'success_sub'    => __( 'Nous vous appellerons bientôt pour confirmer la livraison.', 'aod-cod-form' ),
				// Replis d'erreur. Sans eux, un acheteur arabophone lisait du français :
				// sur une vraie coupure réseau, `xhr.responseJSON` est undefined, donc
				// le repli s'affiche TOUJOURS — ce n'était pas un cas limite.
				'error'          => __( 'Une erreur est survenue. Veuillez réessayer.', 'aod-cod-form' ),
				'net_error'      => __( 'Erreur réseau. Vérifiez votre connexion et réessayez.', 'aod-cod-form' ),
				'cart_added'     => __( 'Article ajouté au panier.', 'aod-cod-form' ),
			),
		) );
	}

	/**
	 * Rendu automatique sur la page produit simple.
	 */
	public function render_auto() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		echo $this->get_form_html( $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'product_id' => 0 ), $atts, 'aod_cod_form' );
		$pid  = (int) $atts['product_id'];
		if ( ! $pid && is_product() ) {
			$pid = get_the_ID();
		}
		if ( ! $pid ) {
			return '';
		}
		return $this->get_form_html( $pid );
	}

	/**
	 * Le produit a-t-il des variantes à choisir ? (36.1)
	 *
	 * Un ajout au panier depuis une grille ne choisit aucune option : le bouton
	 * de la carte doit alors MENER à la fiche plutôt qu'ajouter en devinant
	 * (précédent 35.2 — jamais de repli muet).
	 *
	 * @param WC_Product $product Produit.
	 * @return bool
	 */
	public function has_options( $product ) {
		return (bool) $this->get_product_options( $product );
	}

	/**
	 * Le bloc de choix de variantes d'un produit, prêt à peindre ailleurs que
	 * sur sa fiche (panneau de choix du panier, 2026-08-02).
	 *
	 * 🚨 ON SERT LE MARKUP DE LA FICHE, on n'en fabrique pas un second. Ce
	 * markup porte déjà des règles qu'il serait coûteux — et dangereux — de
	 * réécrire côté client : une valeur en RUPTURE y naît `disabled` +
	 * `aria-disabled` (40.4), les noms affichés sont localisés pendant que
	 * `value`/`data-name` gardent la clé canonique FR du matching serveur
	 * (7.4), et les vignettes/pastilles suivent le réglage du gérant. Un
	 * second rendu en JS aurait divergé du premier au premier ajustement, et
	 * la divergence se serait vue en rupture de stock — c'est-à-dire sur une
	 * commande impossible.
	 *
	 * Le préfixe isole les `id`/`name` : le panneau peut vivre sur une page qui
	 * rend DÉJÀ le formulaire du même produit (suggestions sous une fiche), et
	 * deux radios de même `name` ne feraient qu'un seul groupe.
	 *
	 * @param WC_Product $product
	 * @return string HTML, ou '' si le produit n'a pas d'options.
	 */
	public function options_html_for( $product ) {
		$options = $this->get_product_options( $product );
		if ( ! $options ) {
			return '';
		}
		return $this->option_sections_html( $product->get_id(), $options, $product->get_id() . '-sheet' );
	}

	/**
	 * Un VRAI choix reste-t-il à faire avant d'ajouter ce produit au panier
	 * depuis la grille ? (carte premium, 2026-09-10)
	 *
	 * Diffère de has_options() : un produit peut porter des sections d'options
	 * (has_options() === true) sans qu'aucune décision ne reste possible —
	 * chaque section n'a plus qu'UNE valeur disponible, les autres étant en
	 * rupture. Poser quand même la question ouvrirait un panneau pour une
	 * réponse déjà connue — l'exact inverse de « ne demander que les choix
	 * nécessaires ».
	 *
	 * Retourne aussi `true` si une section n'a AUCUNE valeur disponible : le
	 * choix reste nécessaire pour que l'acheteur VOIE la rupture (le panneau
	 * sert alors à le dire), plutôt que de refuser l'ajout sans un mot.
	 *
	 * @param WC_Product $product
	 * @return bool
	 */
	public function needs_variant_choice( $product ) {
		$sections = $this->get_product_options( $product );
		if ( ! $sections ) {
			return false;
		}
		foreach ( $sections as $sec ) {
			if ( 1 !== count( $this->available_values( $sec ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * La combinaison UNIQUE jouable d'un produit sans vrai choix
	 * (needs_variant_choice() === false) : la seule valeur disponible de
	 * chaque section, en clé canonique FR — prête à partir telle quelle vers
	 * `add()` côté client, sans détour par le panneau.
	 *
	 * @param WC_Product $product
	 * @return array section_index => nom canonique FR
	 */
	public function only_choice_for( $product ) {
		$picked = array();
		foreach ( $this->get_product_options( $product ) as $si => $sec ) {
			$avail = $this->available_values( $sec );
			if ( 1 === count( $avail ) ) {
				$picked[ $si ] = $avail[0]['name'];
			}
		}
		return $picked;
	}

	/**
	 * Valeurs NON en rupture d'une section (helper partagé par les deux
	 * méthodes ci-dessus et par option_sections_html()). Même critère de
	 * rupture que le rendu : stock GÉRÉ et ≤ 0 (jamais quand non géré = '').
	 *
	 * @param array $sec Une section de get_product_options().
	 * @return array Valeurs disponibles, dans leur ordre d'origine.
	 */
	protected function available_values( $sec ) {
		$out = array();
		foreach ( $sec['values'] as $val ) {
			$is_rupture = isset( $val['stock'] ) && '' !== $val['stock'] && (int) $val['stock'] <= 0;
			if ( ! $is_rupture ) {
				$out[] = $val;
			}
		}
		return $out;
	}

	/**
	 * Sections d'options d'un produit pour l'affichage (Taille, Couleur…).
	 *
	 * Lit la méta `_aod_options` ; à défaut, migre un ancien produit variable
	 * mono-axe (variations couleur) vers une section visuelle unique.
	 *
	 * @param WC_Product $product
	 * @return array Liste de [ 'label', 'visual', 'values' => [ ['name','price','img','img_full','srcset'], … ] ].
	 */
	protected function get_product_options( $product ) {
		$raw = $product->get_meta( '_aod_options' );
		$out = array();
		if ( is_array( $raw ) && $raw ) {
			foreach ( $raw as $sec ) {
				if ( ! is_array( $sec ) ) {
					continue;
				}
				$values = array();
				$src    = ( isset( $sec['values'] ) && is_array( $sec['values'] ) ) ? $sec['values'] : array();
				foreach ( $src as $val ) {
					$name = isset( $val['name'] ) ? (string) $val['name'] : '';
					if ( '' === $name ) {
						continue;
					}
					$img_id   = isset( $val['image_id'] ) ? (int) $val['image_id'] : 0;
					$hex      = ( isset( $val['hex'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $val['hex'] ) ) ? strtolower( (string) $val['hex'] ) : '';
					$single   = $img_id ? wp_get_attachment_image_src( $img_id, 'woocommerce_single' ) : false;
					$values[] = array(
						'name'     => $name,
						// Traduction saisie par le gérant (7.4). Absente sur tout produit
						// d'avant l'étape : le dictionnaire prend alors le relais.
						'name_ar'  => isset( $val['name_ar'] ) ? (string) $val['name_ar'] : '',
						'price'    => ( isset( $val['price'] ) && '' !== $val['price'] ) ? (float) $val['price'] : 0.0,
						'img_id'   => $img_id,
						'img'      => $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : '',
						'img_full' => $single ? $single[0] : '',
						'img_w'    => $single ? (int) $single[1] : 0,
						'img_h'    => $single ? (int) $single[2] : 0,
						'srcset'   => $img_id ? (string) wp_get_attachment_image_srcset( $img_id, 'woocommerce_single' ) : '',
						'hex'      => $hex,
						// 40.1 — stock par valeur, exposé pour la rupture publique (40.4).
						// '' = non géré (illimité), int ≥ 0 = géré (0 = rupture).
						'stock'    => ( isset( $val['stock'] ) && '' !== $val['stock'] ) ? (int) $val['stock'] : '',
					);
				}
				if ( ! $values ) {
					continue;
				}
				$out[] = array(
					'label'    => isset( $sec['label'] ) ? (string) $sec['label'] : '',
					'label_ar' => isset( $sec['label_ar'] ) ? (string) $sec['label_ar'] : '',
					'visual'  => ! empty( $sec['visual'] ),
					'display' => ( isset( $sec['display'] ) && 'dropdown' === $sec['display'] ) ? 'dropdown' : 'cards',
					'values'  => $values,
				);
			}
			if ( $out ) {
				return $out;
			}
		}

		// Compat : ancien produit variable mono-axe → une section visuelle unique.
		if ( $product->is_type( 'variable' ) ) {
			$label = (string) $product->get_meta( '_aod_variant_label' );
			if ( '' === $label ) {
				// 🚨 Pas de __() : ce libellé est une CLÉ de commande (cf. default_variant_label()).
				$label = $this->default_variant_label();
			}
			$values = array();
			foreach ( $product->get_children() as $cid ) {
				$v = wc_get_product( $cid );
				if ( ! $v || ! $v->is_purchasable() || ! $v->is_in_stock() ) {
					continue;
				}
				$atts = $v->get_attributes();
				$name = $atts ? (string) reset( $atts ) : '';
				if ( '' === $name ) {
					continue;
				}
				$img_id   = $v->get_image_id();
				$single   = $img_id ? wp_get_attachment_image_src( $img_id, 'woocommerce_single' ) : false;
				$values[] = array(
					'name'     => $name,
					'name_ar'  => '', // Attribut WC : aucune saisie possible → dictionnaire.
					'price'    => 0.0,
					'img_id'   => $img_id,
					'img'      => $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : '',
					'img_full' => $single ? $single[0] : '',
					'img_w'    => $single ? (int) $single[1] : 0,
					'img_h'    => $single ? (int) $single[2] : 0,
					'srcset'   => $img_id ? (string) wp_get_attachment_image_srcset( $img_id, 'woocommerce_single' ) : '',
					'hex'      => '',
					// 40.1 — variations Woo natives : jamais de stock de variante AOD ici.
					'stock'    => '',
				);
			}
			if ( $values ) {
				// Pas de traduction saisissable ici : ces valeurs viennent des attributs
				// WC, pas de /gestion. `label_ar`/`name_ar` restent vides → le dictionnaire
				// prend le relais, exactement comme avant la 7.4.
				$out[] = array( 'label' => $label, 'label_ar' => '', 'visual' => true, 'display' => 'cards', 'values' => $values );
			}
		}
		return $out;
	}

	/**
	 * IDs des médias attachés aux options (couleurs…) d'un produit, dans l'ordre
	 * d'affichage et sans doublon. Sert à intégrer ces photos à la galerie.
	 *
	 * @param WC_Product $product
	 * @return int[]
	 */
	protected function get_option_image_ids( $product ) {
		$ids = array();
		foreach ( $this->get_product_options( $product ) as $sec ) {
			foreach ( $sec['values'] as $val ) {
				$id = isset( $val['img_id'] ) ? (int) $val['img_id'] : 0;
				if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
		}
		return $ids;
	}

	/**
	 * Ajoute les photos des options (couleurs…) comme vraies diapos de la galerie
	 * WooCommerce du produit affiché. On les insère juste après la photo principale
	 * et avant les photos supplémentaires, en évitant tout doublon (image déjà
	 * principale ou déjà dans la galerie).
	 *
	 * @param int[]      $value   IDs de la galerie d'origine.
	 * @param WC_Product $product Produit concerné par le getter.
	 * @return int[]
	 */
	public function inject_option_images_into_gallery( $value, $product ) {
		// Uniquement la galerie du produit réellement consulté (pas les produits liés,
		// le panier, etc.) pour ne pas polluer les autres contextes.
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $value;
		}
		if ( ! ( $product instanceof WC_Product ) || $product->get_id() !== get_queried_object_id() ) {
			return $value;
		}

		$existing = is_array( $value ) ? array_map( 'intval', $value ) : array();
		$featured = (int) $product->get_image_id();
		$add      = array();
		foreach ( $this->get_option_image_ids( $product ) as $id ) {
			if ( $id === $featured || in_array( $id, $existing, true ) || in_array( $id, $add, true ) ) {
				continue; // Déjà présent comme diapo : le JS la retrouvera par son ID.
			}
			$add[] = $id;
		}
		return array_merge( $add, $existing );
	}

	/**
	 * Tague chaque diapo de la galerie avec l'ID du média qu'elle contient, pour
	 * que le JS puisse cibler la bonne photo (`[data-aod-attachment="ID"]`) au lieu
	 * de comparer des URL fragiles.
	 *
	 * @param string $html          HTML de la diapo.
	 * @param int    $attachment_id ID du média.
	 * @return string
	 */
	public function tag_gallery_slide_attachment( $html, $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( ! $attachment_id || false === strpos( $html, '<div' ) ) {
			return $html;
		}
		return preg_replace(
			'/<div /',
			'<div data-aod-attachment="' . esc_attr( $attachment_id ) . '" ',
			$html,
			1
		);
	}

	/**
	 * Le visiteur consulte-t-il le site en arabe ? (locale forcée par AOD_COD_Lang).
	 *
	 * ⚠️ UN SEUL CORPS, chez `AOD_COD_Data` (dette D10, 2026-08-03). Cette méthode
	 * portait sa propre copie de la règle — laquelle a divergé de l'autre pendant des
	 * mois sans que rien ne le dise : celle-ci lisait la langue, l'autre y ajoutait
	 * `|| is_rtl()`. Deux réponses possibles à la même question sur la même page, c'est
	 * la famille de défauts que les Phases 44 et 47 ont passé leur temps à refermer.
	 * On délègue plutôt que de recopier : le prochain correctif n'aura qu'un site à
	 * atteindre. Le wrapper reste, parce qu'il nomme la question du point de vue du
	 * formulaire et garde les appelants indépendants de la classe qui répond.
	 *
	 * @return bool
	 */
	protected function is_arabic() {
		return AOD_COD_Data::is_arabic();
	}

	/**
	 * Libellé par défaut de la section d'un ancien produit variable mono-axe.
	 *
	 * 🚨 CONSTANTE FRANÇAISE, VOLONTAIREMENT PAS DE `__()`. Ce libellé devient la CLÉ
	 * du meta de la ligne de commande (voir handle_submit()). Or la locale du visiteur
	 * est active pendant la création de la commande — `AOD_COD_Lang::filter_locale()`
	 * s'applique à `admin-ajax`. Un `__( 'Couleur' )` ici enregistrait donc « اللون »
	 * comme clé pour tout acheteur arabophone, jusque dans le bordereau transporteur.
	 * La traduction se fait à l'AFFICHAGE seulement, via localize_variant().
	 *
	 * @return string
	 */
	protected function default_variant_label() {
		return 'Couleur';
	}

	/**
	 * Retrouve la valeur d'option choisie dans une section, par son nom canonique.
	 *
	 * 🚨 La comparaison est stricte et porte sur le nom FRANÇAIS : c'est ce que le
	 * navigateur renvoie (attribut `value=`) et ce qui sera écrit sur la ligne de
	 * commande. Si un jour l'arabe atteignait `value=`, c'est ICI que ça casserait —
	 * silencieusement, et seulement pour les arabophones.
	 *
	 * @param array  $sec    Section (voir get_product_options()).
	 * @param string $chosen Nom canonique soumis.
	 * @return array|null La valeur correspondante, ou null.
	 */
	protected function match_option( $sec, $chosen ) {
		$values = ( isset( $sec['values'] ) && is_array( $sec['values'] ) ) ? $sec['values'] : array();
		foreach ( $values as $val ) {
			if ( isset( $val['name'] ) && $val['name'] === $chosen ) {
				return $val;
			}
		}
		return null;
	}

	/**
	 * Dictionnaire FR→AR des libellés/valeurs de variantes courants (clés en minuscules).
	 * Utilisé en REPLI, derrière la saisie du gérant (voir localize_variant()).
	 *
	 * @return array
	 */
	protected function variant_dictionary() {
		return array(
			// Phrases d'instruction par défaut (libellés des presets).
			'sélectionner une couleur'  => 'اختر اللون',
			'selectionner une couleur'  => 'اختر اللون',
			'selectioner une couleur'   => 'اختر اللون',
			'sélectionner une taille'   => 'اختر المقاس',
			'selectionner une taille'   => 'اختر المقاس',
			'selectioner une taille'    => 'اختر المقاس',
			'sélectionner une pointure' => 'اختر القياس',
			'selectionner une pointure' => 'اختر القياس',
			'selectioner une pointure'  => 'اختر القياس',
			// Libellés de section simples.
			'taille'    => 'المقاس',
			'tailles'   => 'المقاسات',
			'pointure'  => 'القياس',
			'pointures' => 'القياسات',
			'couleur'   => 'اللون',
			'couleurs'  => 'الألوان',
			'matière'   => 'الخامة',
			'matiere'   => 'الخامة',
			'modèle'    => 'الموديل',
			'modele'    => 'الموديل',
			// Couleurs.
			'noir'        => 'أسود',
			'blanc'       => 'أبيض',
			'gris'        => 'رمادي',
			'rouge'       => 'أحمر',
			'bleu'        => 'أزرق',
			'bleu ciel'   => 'أزرق سماوي',
			'bleu marine' => 'كحلي',
			'vert'        => 'أخضر',
			'jaune'       => 'أصفر',
			'orange'      => 'برتقالي',
			'rose'        => 'وردي',
			'violet'      => 'بنفسجي',
			'marron'      => 'بني',
			'beige'       => 'بيج',
			'doré'        => 'ذهبي',
			'dore'        => 'ذهبي',
			'or'          => 'ذهبي',
			'argent'      => 'فضي',
			'argenté'     => 'فضي',
			'bordeaux'    => 'عنابي',
			'turquoise'   => 'فيروزي',
			'kaki'        => 'كاكي',
			'fuchsia'     => 'فوشيا',
			'corail'      => 'مرجاني',
			'ciel'        => 'سماوي',
			'marine'      => 'كحلي',
			// Nuances (servent au repli mot-à-mot : « Gris clair » → « رمادي فاتح »).
			'clair'  => 'فاتح',
			'claire' => 'فاتح',
			'foncé'  => 'غامق',
			'fonce'  => 'غامق',
			'foncée' => 'غامق',
			'foncee' => 'غامق',
			'pâle'   => 'باهت',
			'pale'   => 'باهت',
			'vif'    => 'زاهي',
			'vive'   => 'زاهي',
		);
	}

	/**
	 * Traduit un libellé/valeur de variante pour l'AFFICHAGE en mode arabe.
	 *
	 * Priorité : saisie du gérant > dictionnaire intégré (correspondance exacte, puis
	 * mot-à-mot pour les valeurs composées comme « Gris clair ») > texte d'origine
	 * (français) si inconnu. Jamais de vide.
	 *
	 * Ne modifie JAMAIS la valeur soumise : le nom canonique reste le français, afin que
	 * le matching serveur (voir match_option()) et la commande enregistrée restent
	 * cohérents. Traduire une clé ne la traduit pas — ça la casse.
	 *
	 * @param string $fr Texte d'origine (français) — le nom CANONIQUE.
	 * @param string $ar Traduction saisie par le gérant (vide si absente).
	 * @return string
	 */
	protected function localize_variant( $fr, $ar = '' ) {
		if ( ! $this->is_arabic() ) {
			return (string) $fr;
		}
		// Le gérant a tranché : sa saisie prime sur le dictionnaire, toujours.
		$ar = trim( (string) $ar );
		if ( '' !== $ar ) {
			return $ar;
		}
		$fr   = (string) $fr;
		$dict = $this->variant_dictionary();
		$key  = strtolower( trim( $fr ) );
		if ( isset( $dict[ $key ] ) ) {
			return $dict[ $key ];
		}
		// Repli mot-à-mot pour les valeurs composées (« Gris clair » → « رمادي فاتح »).
		// L'adjectif suit le nom en arabe comme en français : on garde le même ordre.
		// On ne traduit que si TOUS les mots sont connus, sinon on garde le français.
		$tokens = preg_split( '/[\s\-\/]+/u', $key, -1, PREG_SPLIT_NO_EMPTY );
		if ( is_array( $tokens ) && count( $tokens ) > 1 ) {
			$parts = array();
			foreach ( $tokens as $t ) {
				if ( ! isset( $dict[ $t ] ) ) {
					$parts = array();
					break;
				}
				$parts[] = $dict[ $t ];
			}
			if ( $parts ) {
				return implode( ' ', $parts );
			}
		}
		return $fr;
	}

	/**
	 * Sections d'options du configurateur (Taille, Couleur…), en boutons style Shopify.
	 *
	 * Ces champs ne sont PAS soumis directement : le JS lit la sélection au clic sur
	 * « Ajouter », l'ajoute comme ligne au tableau du panier, puis réinitialise le
	 * configurateur. Les `name` servent uniquement à grouper les radios par section.
	 * Chaque valeur porte son `data-si` (section), `data-name`, `data-price` et ses
	 * `data-img*` (échange de la photo de galerie + récap du panier).
	 *
	 * @param int    $product_id Produit concerné.
	 * @param array  $options    Sections d'options (voir get_product_options()).
	 * @param string $prefix     Préfixe unique pour les `name`/`id` des radios (ex. offre+unité),
	 *                           afin de répéter ces sections plusieurs fois sur la même page.
	 * @return string
	 */
	protected function option_sections_html( $product_id, $options, $prefix = '' ) {
		$prefix = '' !== (string) $prefix ? (string) $prefix : (string) (int) $product_id;
		ob_start();
		foreach ( $options as $si => $sec ) :
			// 🚨 Seul le TEXTE AFFICHÉ est traduit. `data-label`/`data-name`/`value=`
			// gardent le nom canonique FR : c'est la clé de matching (match_option()).
			$label_disp = $this->localize_variant( $sec['label'], isset( $sec['label_ar'] ) ? $sec['label_ar'] : '' );
			$is_dropdown = ( isset( $sec['display'] ) && 'dropdown' === $sec['display'] );

			/*
			 * Une section qui porte des VIGNETTES (photo) ou des PASTILLES (hex) a
			 * besoin d'une maille de grille plus large qu'une section de pointures.
			 * Le drapeau `visual` du gérant ne suffit pas à le dire : une valeur
			 * peut porter un `hex` sans que la section soit cochée « visuelle »
			 * (c'est le cas des couleurs prédéfinies). On le CALCULE.
			 */
			$has_media = ! empty( $sec['visual'] );
			foreach ( $sec['values'] as $sec_val ) {
				if ( ! empty( $sec_val['img'] ) || ! empty( $sec_val['hex'] ) ) {
					$has_media = true;
					break;
				}
			}
			/*
			 * Une SEULE valeur jouable dans cette section : rien à décider, on la
			 * PRÉ-SÉLECTIONNE (2026-09-10) — sans ça, la fiche forçait un clic sur
			 * un choix qui n'en est pas un (`collectItems()` exige un radio coché
			 * ou une option choisie, faute de quoi l'ajout est refusé). Une section
			 * à choix réel, elle, reste vierge : on ne devine jamais À LA PLACE de
			 * l'acheteur, cf. needs_variant_choice() plus haut, même critère.
			 */
			$aod_lone_avail = $this->available_values( $sec );
			$aod_lone_name  = ( 1 === count( $aod_lone_avail ) ) ? $aod_lone_avail[0]['name'] : null;
			?>
			<div class="aod-cod__field aod-cod__optsec<?php echo $sec['visual'] ? ' is-visual' : ''; ?><?php echo $has_media ? ' has-media' : ''; ?><?php echo $is_dropdown ? ' is-dropdown' : ''; ?>" data-si="<?php echo esc_attr( $si ); ?>" data-label="<?php echo esc_attr( $sec['label'] ); ?>">
				<?php
				/*
				 * L'écho de sélection (« Pointure · 41 ») est rempli par le JS depuis
				 * le texte DÉJÀ affiché de la pastille choisie — jamais depuis
				 * `data-name`, qui porte le nom canonique FR (clé de matching serveur).
				 * Conséquence heureuse : aucune chaîne traduisible nouvelle, et l'écho
				 * est juste en arabe sans une ligne de `.po`.
				 */
				?>
				<label class="aod-cod__optlabel"><?php echo esc_html( $label_disp ); ?> <span>*</span><em class="aod-cod__optpick" aria-hidden="true"></em></label>
				<?php if ( $is_dropdown ) : ?>
					<select class="aod-cod__optselect" data-si="<?php echo esc_attr( $si ); ?>">
						<option value=""><?php esc_html_e( '— Choisir —', 'aod-cod-form' ); ?></option>
						<?php foreach ( $sec['values'] as $vi => $val ) :
							$opt_label = $this->localize_variant( $val['name'], isset( $val['name_ar'] ) ? $val['name_ar'] : '' );
							// 40.4 — rupture : stock GÉRÉ et ≤ 0 (jamais quand non géré = '').
							$is_rupture = isset( $val['stock'] ) && '' !== $val['stock'] && (int) $val['stock'] <= 0;
							if ( $is_rupture ) {
								$opt_label .= '  ' . __( '(Rupture de stock)', 'aod-cod-form' );
							} elseif ( $val['price'] > 0 ) {
								$opt_label .= '  (+' . wp_strip_all_tags( wc_price( $val['price'] ) ) . ')';
							}
							?>
							<option value="<?php echo esc_attr( $val['name'] ); ?>" data-si="<?php echo esc_attr( $si ); ?>" data-name="<?php echo esc_attr( $val['name'] ); ?>" data-price="<?php echo esc_attr( $val['price'] ); ?>" data-img="<?php echo esc_url( $val['img'] ); ?>" data-img-id="<?php echo esc_attr( $val['img_id'] ); ?>" data-img-full="<?php echo esc_url( $val['img_full'] ); ?>" data-img-w="<?php echo esc_attr( $val['img_w'] ); ?>" data-img-h="<?php echo esc_attr( $val['img_h'] ); ?>" data-srcset="<?php echo esc_attr( $val['srcset'] ); ?>"<?php disabled( $is_rupture ); selected( $aod_lone_name === $val['name'] ); ?>><?php echo esc_html( $opt_label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
				<div class="aod-cod__opts">
					<?php
					foreach ( $sec['values'] as $vi => $val ) :
						$oid = 'aod-cod-opt-' . $prefix . '-' . (int) $si . '-' . (int) $vi;
						$has_hex   = ! empty( $val['hex'] );
						$is_visual = $sec['visual'] || $has_hex;
						// Pastille couleur sans photo : rendu compact (point + nom).
						$is_color  = $has_hex && empty( $val['img'] );
						// 40.4 — rupture : stock GÉRÉ et ≤ 0 (jamais quand non géré = '').
						$is_rupture = isset( $val['stock'] ) && '' !== $val['stock'] && (int) $val['stock'] <= 0;
						$opt_class = 'aod-cod__opt';
						if ( $is_visual ) {
							$opt_class .= ' aod-cod__opt--visual';
						}
						if ( $is_color ) {
							$opt_class .= ' aod-cod__opt--color';
						}
						if ( $is_rupture ) {
							$opt_class .= ' aod-cod__opt--rupture';
						}
						// État visuel du choix auto (2026-09-10) : posé ICI, côté serveur,
						// pour que la pastille se lise déjà « choisie » au premier rendu —
						// sans lui, l'écran mentirait jusqu'à ce que le JS s'exécute.
						if ( $aod_lone_name === $val['name'] ) {
							$opt_class .= ' is-selected';
						}
						?>
						<label class="<?php echo esc_attr( $opt_class ); ?>" for="<?php echo esc_attr( $oid ); ?>">
							<input type="radio" id="<?php echo esc_attr( $oid ); ?>" name="aod-opt-<?php echo esc_attr( $prefix . '-' . (int) $si ); ?>" value="<?php echo esc_attr( $val['name'] ); ?>" data-si="<?php echo esc_attr( $si ); ?>" data-name="<?php echo esc_attr( $val['name'] ); ?>" data-price="<?php echo esc_attr( $val['price'] ); ?>" data-img="<?php echo esc_url( $val['img'] ); ?>" data-img-id="<?php echo esc_attr( $val['img_id'] ); ?>" data-img-full="<?php echo esc_url( $val['img_full'] ); ?>" data-img-w="<?php echo esc_attr( $val['img_w'] ); ?>" data-img-h="<?php echo esc_attr( $val['img_h'] ); ?>" data-srcset="<?php echo esc_attr( $val['srcset'] ); ?>"<?php disabled( $is_rupture ); checked( $aod_lone_name === $val['name'] ); echo $is_rupture ? ' aria-disabled="true"' : ''; ?>>
							<span class="aod-cod__opt-card">
								<?php if ( $is_visual && $val['img'] ) : ?>
									<span class="aod-cod__opt-thumb" style="background-image:url('<?php echo esc_url( $val['img'] ); ?>')"></span>
								<?php elseif ( $has_hex ) : ?>
									<span class="aod-cod__opt-thumb aod-cod__opt-thumb--color" style="background-color:<?php echo esc_attr( $val['hex'] ); ?>"></span>
								<?php endif; ?>
								<span class="aod-cod__opt-name"><?php echo esc_html( $this->localize_variant( $val['name'], isset( $val['name_ar'] ) ? $val['name_ar'] : '' ) ); ?></span>
								<?php if ( $is_rupture ) : ?>
									<span class="aod-cod__opt-rupture"><?php esc_html_e( 'Rupture de stock', 'aod-cod-form' ); ?></span>
								<?php elseif ( $val['price'] > 0 ) : ?>
									<span class="aod-cod__opt-plus">+<?php echo wp_strip_all_tags( wc_price( $val['price'] ) ); ?></span>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
			<?php
		endforeach;
		return ob_get_clean();
	}

	/**
	 * Offres d'un produit (N unités à prix de lot), pour le formulaire de commande.
	 *
	 * Lit la méta `_aod_offers` ; à défaut, migre l'ancienne méta `_aod_qty_tiers`
	 * (prix par pièce → prix total du lot). La carte « 1 produit » est implicite et
	 * n'est PAS incluse ici (elle est ajoutée au rendu avec le prix d'affichage).
	 *
	 * @param WC_Product $product
	 * @return array Liste de [ 'qty' => int, 'price' => float (total du lot) ], triée par qty.
	 */
	protected function get_product_offers( $product ) {
		$offers = array();
		if ( ! $product ) {
			return $offers;
		}
		$raw = $product->get_meta( '_aod_offers' );
		if ( is_array( $raw ) && $raw ) {
			foreach ( $raw as $o ) {
				$qty   = isset( $o['qty'] ) ? (int) $o['qty'] : 0;
				$price = isset( $o['price'] ) ? (float) $o['price'] : 0;
				if ( $qty >= 2 && $price > 0 ) {
					$offers[] = array( 'qty' => $qty, 'price' => $price );
				}
			}
			if ( $offers ) {
				usort( $offers, function ( $a, $b ) {
					return $a['qty'] - $b['qty'];
				} );
				return $offers;
			}
		}

		// Compat : ancien « prix par quantité » (prix par pièce) → offres (prix total du lot).
		$tiers = $product->get_meta( '_aod_qty_tiers' );
		if ( is_array( $tiers ) ) {
			foreach ( $tiers as $t ) {
				$min = isset( $t['min'] ) ? (int) $t['min'] : 0;
				$pp  = isset( $t['price'] ) ? (float) $t['price'] : 0;
				if ( $min >= 2 && $pp > 0 ) {
					$offers[] = array( 'qty' => $min, 'price' => $pp * $min );
				}
			}
		}
		usort( $offers, function ( $a, $b ) {
			return $a['qty'] - $b['qty'];
		} );
		return $offers;
	}

	/**
	 * Cartes d'offres : « 1 produit » (par défaut) + une carte par offre configurée.
	 *
	 * Chaque carte porte `data-qty` (nombre d'unités) et `data-price` (prix total du lot)
	 * pour que le JS calcule le sous-total. La première carte est sélectionnée par défaut.
	 * Si le produit a des variantes, le panneau de sélection (N blocs « Article k ») est
	 * imbriqué DANS la carte et se déplie à l'intérieur de celle-ci une fois sélectionnée.
	 *
	 * @param int   $product_id
	 * @param float $base_price  Prix d'affichage unitaire (carte « 1 produit »).
	 * @param array $offers      Offres (voir get_product_offers()).
	 * @param array $options     Sections d'options (vide = produit sans variantes).
	 * @return string
	 */
	/**
	 * Palette d'accents pour différencier visuellement les offres entre elles et
	 * les articles (unités) d'une même offre. Cyclée par index ; chaque couleur
	 * est exposée via la variable CSS `--aod-c` sur la carte d'offre / l'unité.
	 *
	 * @return string[] Codes hex, contrastés sur fond blanc.
	 */
	protected function accent_palette() {
		return array( '#2563eb', '#16a34a', '#f97316', '#7c3aed', '#db2777', '#0891b2', '#ca8a04', '#dc2626' );
	}

	protected function offer_cards_html( $product_id, $base_price, $offers, $options = array() ) {
		// Carte « 1 produit » implicite en tête, puis les offres.
		$cards       = array_merge( array( array( 'qty' => 1, 'price' => $base_price ) ), $offers );
		$name        = 'aod-offer-' . (int) $product_id;
		$has_options = ! empty( $options );
		$palette     = $this->accent_palette();
		$pcount      = count( $palette );

		/*
		 * 🚨 SOLO — le gérant n'a configuré AUCUNE offre : il ne reste que la carte
		 * « 1 produit », donc AUCUN choix à faire. Or on rendait quand même toute
		 * la mécanique du choix : un `role="radiogroup"` d'un seul bouton, une
		 * carte encadrée, un liseré de couleur pour « différencier » l'offre de
		 * ses voisines inexistantes, et une coche verte impossible à décocher.
		 *
		 * Le coût n'était pas que visuel. Mesuré au navigateur avant correctif,
		 * sur un écran de 390 px : ce décor plus celui du bloc « Article »
		 * mangeait 67 px de CHAQUE côté — 34 % de la largeur de l'écran pour
		 * encadrer une décision qui n'existe pas.
		 *
		 * ⚠️ On retire le DÉCOR, jamais les NŒUDS. `aod-cod-form.js` lit
		 * `data-qty`/`data-price` sur `.aod-cod__offer-card.is-selected` (l. 53-56)
		 * et itère `.aod-cod__unit` pour composer une sélection de variantes par
		 * article (l. 332). Supprimer ces éléments casserait le calcul du prix et
		 * la commande. Le contrat DOM est donc INCHANGÉ : seule la peau tombe.
		 */
		$solo = ( 1 === count( $cards ) );
		ob_start();
		?>
		<div class="aod-cod__offers<?php echo $solo ? ' aod-cod__offers--solo' : ''; ?>"<?php if ( ! $solo ) : ?> role="radiogroup" aria-label="<?php esc_attr_e( 'Choisissez votre offre', 'aod-cod-form' ); ?>"<?php endif; ?>>
			<?php foreach ( $cards as $oi => $card ) :
				$qty   = (int) $card['qty'];
				$total = (float) $card['price'];
				$old   = $base_price * $qty;             // Prix « plein » (achat à l'unité).
				$save  = $old - $total;                  // Économie du lot.
				$oid   = 'aod-cod-offer-' . (int) $product_id . '-' . (int) $oi;
				$accent = $palette[ $oi % $pcount ];
				?>
				<div class="aod-cod__offer-card<?php echo 0 === $oi ? ' is-selected' : ''; ?>" data-offer="<?php echo esc_attr( $oi ); ?>" data-qty="<?php echo esc_attr( $qty ); ?>" data-price="<?php echo esc_attr( $total ); ?>" style="--aod-c:<?php echo esc_attr( $accent ); ?>">
					<label class="aod-cod__offer-head" for="<?php echo esc_attr( $oid ); ?>">
						<input type="radio" id="<?php echo esc_attr( $oid ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $oi ); ?>" <?php checked( 0, $oi ); ?>>
						<span class="aod-cod__offer-body">
							<?php
							/*
							 * En SOLO, « 1 produit » nomme une alternative qui n'existe pas :
							 * on ne garde que le prix. Il ne fait pas doublon partout — une
							 * PAGE DE VENTE n'affiche aucun prix hors du formulaire (vérifié :
							 * `class-aod-cod-landing.php` n'appelle jamais `wc_price()`), donc
							 * le retirer priverait les landings de leur ancre de prix jusqu'au
							 * récapitulatif. C'est la raison pour laquelle cette ligne survit
							 * au désempilement.
							 */
							?>
							<?php if ( ! $solo ) : ?>
							<span class="aod-cod__offer-title">
								<?php
								/* translators: %d: nombre d'unités */
								echo esc_html( sprintf( _n( '%d produit', '%d produits', $qty, 'aod-cod-form' ), $qty ) );
								?>
							</span>
							<?php endif; ?>
							<?php if ( $qty > 1 && $save > 0 ) : ?>
								<span class="aod-cod__offer-badge"><?php echo esc_html( sprintf( __( 'Économisez %s', 'aod-cod-form' ), wp_strip_all_tags( wc_price( $save ) ) ) ); ?></span>
							<?php endif; ?>
							<span class="aod-cod__offer-price">
								<span class="aod-cod__offer-now"><?php echo wp_kses_post( wc_price( $total ) ); ?></span>
								<?php if ( $qty > 1 && $save > 0 ) : ?>
									<s class="aod-cod__offer-old"><?php echo wp_kses_post( wc_price( $old ) ); ?></s>
								<?php endif; ?>
							</span>
						</span>
						<?php // Une coche qu'on ne peut pas décocher n'informe de rien. ?>
						<?php if ( ! $solo ) : ?>
						<span class="aod-cod__offer-check" aria-hidden="true"></span>
						<?php endif; ?>
					</label>
					<?php if ( $has_options ) : ?>
						<div class="aod-cod__offer-panel" data-offer="<?php echo esc_attr( $oi ); ?>"<?php echo 0 === $oi ? '' : ' hidden'; ?>>
							<?php for ( $u = 0; $u < $qty; $u++ ) :
								$prefix  = (int) $product_id . '-' . (int) $oi . '-' . (int) $u;
								$uaccent = $palette[ $u % $pcount ];
								?>
								<?php // 1 seule unité => le bloc « Article » n'a même pas de titre (voir juste dessous) : c'est un cadre vide de sens. Il perd sa peau, pas son nœud (le JS l'itère). ?>
								<div class="aod-cod__unit<?php echo 1 === $qty ? ' aod-cod__unit--solo' : ''; ?>" data-unit="<?php echo esc_attr( $u ); ?>" style="--aod-c:<?php echo esc_attr( $uaccent ); ?>">
									<?php if ( $qty > 1 ) : ?>
										<div class="aod-cod__unit-head"><?php echo esc_html( sprintf( __( 'Article %d', 'aod-cod-form' ), $u + 1 ) ); ?></div>
									<?php endif; ?>
									<?php echo $this->option_sections_html( $product_id, $options, $prefix ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							<?php endfor; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Génère le HTML du formulaire.
	 *
	 * @param int $product_id
	 * @return string
	 */
	public function get_form_html( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() ) {
			return '';
		}

		wp_enqueue_style( 'aod-cod-form' );
		wp_enqueue_script( 'aod-cod-form' );

		// Challenge anti-robot Turnstile (chargé uniquement là où le formulaire s'affiche).
		if ( class_exists( 'AOD_COD_Turnstile' ) ) {
			AOD_COD_Turnstile::instance()->enqueue_script();
		}

		// Sections d'options (Taille, Couleur, Pointure…) — sélection style Shopify.
		$options     = $this->get_product_options( $product );
		$has_options = ! empty( $options );

		$price = (float) wc_get_price_to_display( $product );
		if ( $price <= 0 && $product->is_type( 'variable' ) ) {
			// Produit variable migré : le parent n'a pas de prix propre.
			foreach ( $product->get_children() as $cid ) {
				$cv = wc_get_product( $cid );
				if ( $cv && $cv->is_purchasable() ) {
					$price = (float) wc_get_price_to_display( $cv );
					break;
				}
			}
		}

		// Offres (prix par quantité) : N unités de ce produit à prix de lot. La carte
		// « 1 produit » (prix d'affichage) est ajoutée implicitement au rendu.
		$offers = $this->get_product_offers( $product );

		// Arguments de vente (liste à puces). Contenu SAISI par le gérant : il ne passe
		// pas par gettext, il porte sa version arabe dans une méta parallèle. Sans
		// traduction, `pick_list()` rend la liste FR telle quelle.
		$selling_points = AOD_COD_Catalog_I18n::pick_list(
			$product->get_meta( '_aod_selling_points' ),
			$product->get_meta( '_aod_selling_points_ar' ),
			AOD_COD_Catalog_I18n::active()
		);

		// Pack assortiment : liste des produits inclus (affichage informatif).
		$pack_items = array();
		if ( '1' === (string) $product->get_meta( '_aod_is_pack' ) ) {
			$raw = $product->get_meta( '_aod_pack_items' );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $it ) {
					$cp  = wc_get_product( isset( $it['id'] ) ? (int) $it['id'] : 0 );
					if ( $cp ) {
						// Getter BRUT (pas de `the_title` ici) → on traduit explicitement.
						// Sans risque : cette liste est de l'AFFICHAGE, elle n'est jamais
						// écrite dans la commande (seul `add_product()` crée des lignes).
						$cp_id        = $cp->get_id();
						$pack_items[] = array(
							'name' => AOD_COD_Catalog_I18n::render( $cp->get_name(), get_post_meta( $cp_id, AOD_COD_Catalog_Filters::META_TITLE, true ) ),
							'qty'  => isset( $it['qty'] ) ? max( 1, (int) $it['qty'] ) : 1,
						);
					}
				}
			}
		}

		ob_start();
		?>
		<div class="aod-cod" data-product="<?php echo esc_attr( $product_id ); ?>" data-price="<?php echo esc_attr( $price ); ?>">
			<h3 class="aod-cod__title"><?php esc_html_e( 'Commander maintenant — Paiement à la livraison', 'aod-cod-form' ); ?></h3>
			<?php if ( $selling_points ) : ?>
				<ul class="aod-cod__points">
					<?php foreach ( $selling_points as $pt ) : ?>
						<li><?php echo esc_html( $pt ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $pack_items ) : ?>
				<div class="aod-cod__pack">
					<span class="aod-cod__pack-label"><?php esc_html_e( 'Ce pack contient :', 'aod-cod-form' ); ?></span>
					<ul class="aod-cod__pack-list">
						<?php foreach ( $pack_items as $it ) : ?>
							<li><span class="aod-cod__pack-qty"><?php echo esc_html( $it['qty'] ); ?>×</span> <?php echo esc_html( $it['name'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<form class="aod-cod__form" novalidate>
				<?php
				// 🚨 REPÈRE D'ARRIVÉE depuis la grille (2026-08-02). Sur une carte de
				// produit À VARIANTES, « Ajouter au panier » ne peut pas ajouter : il
				// mène ici, parce qu'on ne devine pas une couleur. Mesuré, le visiteur
				// ne faisait pas le lien : il appuyait sur un bouton d'ajout, la page
				// sautait à 3 567 px, le compteur du panier ne bougeait pas et RIEN ne
				// disait pourquoi. Deux choses lui manquaient, et une seule est du
				// texte : un point d'atterrissage sur le CHOIX (l'ancre ci-dessous —
				// les cartes visent `#aod-options`, pas `#aod-commander`, qui tombe sur
				// le formulaire), et une phrase qui nomme les deux gestes qui restent.
				//
				// La phrase n'existe QUE si le panier est actif — elle cite un bouton
				// qui n'est rendu que dans ce cas — et que si le produit a des options,
				// seul cas où la grille renvoie ici. Elle naît masquée : un visiteur qui
				// arrive par la Boutique ou par un lien ne l'a jamais demandée. C'est le
				// JS qui la révèle sur reconnaissance de l'ancre, et qui l'efface dès
				// que le choix est complet — un message qui a fait son travail devient
				// du bruit.
				?>
				<div id="aod-options" tabindex="-1">
				<?php if ( self::cart_enabled() && $has_options ) : ?>
					<p class="aod-cod__pickhint" role="status" hidden>
						<?php
						printf(
							/* translators: %s : libellé exact du bouton d'ajout au panier. */
							esc_html__( 'Choisissez vos options, puis « %s ».', 'aod-cod-form' ),
							esc_html__( 'Ajouter au panier', 'aod-cod-form' )
						);
						?>
					</p>
				<?php endif; ?>
				<?php // Cartes d'offres : « 1 produit » par défaut + une carte par offre. Les
				// variantes (N blocs « Article k ») se déplient à l'intérieur de la carte choisie. ?>
				<?php echo $this->offer_cards_html( $product_id, $price, $offers, $has_options ? $options : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php if ( $has_options ) : ?>
					<p class="aod-cod__config-msg" role="alert"></p>
				<?php endif; ?>
				<?php // Anti-spam — champ « piège » (honeypot). Invisible et non focalisable
				// pour un humain ; un bot qui remplit tous les champs le remplit aussi et
				// la commande est rejetée côté serveur. Ne jamais retirer/afficher. ?>
				<div class="aod-cod__hp" aria-hidden="true">
					<label for="aod-cod-url"><?php esc_html_e( 'Ne pas remplir ce champ', 'aod-cod-form' ); ?></label>
					<input type="text" id="aod-cod-url" name="aod_url" tabindex="-1" autocomplete="off">
				</div>
				<div class="aod-cod__field aod-cod__float">
					<input type="text" id="aod-cod-name" name="name" autocomplete="name" placeholder=" ">
					<label for="aod-cod-name"><?php esc_html_e( 'Nom complet', 'aod-cod-form' ); ?></label>
				</div>
				<div class="aod-cod__field aod-cod__float">
					<input type="tel" id="aod-cod-phone" name="phone" inputmode="numeric" autocomplete="tel" placeholder=" " required>
					<label for="aod-cod-phone"><?php esc_html_e( 'Téléphone', 'aod-cod-form' ); ?> <span>*</span></label>
				</div>
				<div class="aod-cod__row">
					<div class="aod-cod__field aod-cod__float aod-cod__float--sel">
						<select id="aod-cod-wilaya" name="wilaya" required>
							<option value=""><?php esc_html_e( 'Choisir une wilaya', 'aod-cod-form' ); ?></option>
							<?php foreach ( AOD_COD_Data::places() as $w ) : ?>
								<option value="<?php echo esc_attr( $w['code'] ); ?>">
									<?php echo esc_html( sprintf( '%02d - %s', $w['code'], AOD_COD_Data::label( $w['name'], isset( $w['name_ar'] ) ? $w['name_ar'] : '' ) ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<label for="aod-cod-wilaya"><?php esc_html_e( 'Wilaya', 'aod-cod-form' ); ?> <span>*</span></label>
					</div>
					<div class="aod-cod__field aod-cod__float aod-cod__float--sel">
						<select id="aod-cod-commune" name="commune" disabled>
							<option value=""><?php esc_html_e( 'Choisir une commune', 'aod-cod-form' ); ?></option>
						</select>
						<label for="aod-cod-commune"><?php esc_html_e( 'Commune', 'aod-cod-form' ); ?></label>
					</div>
				</div>
				<div class="aod-cod__field aod-cod__delivery">
					<label><?php esc_html_e( 'Mode de livraison', 'aod-cod-form' ); ?></label>
					<div class="aod-cod__radios">
						<label class="aod-cod__radio">
							<input type="radio" name="delivery" value="home" checked>
							<span class="aod-cod__radio-card">
								<span class="aod-cod__radio-icon"><?php echo AOD_COD_Icon::svg( 'home', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- catalogue FERMÉ, aucune saisie ne l'atteint ; wp_kses_post() mangerait les attributs SVG. ?></span>
								<span class="aod-cod__radio-text">
									<span class="aod-cod__radio-title"><?php esc_html_e( 'À domicile', 'aod-cod-form' ); ?></span>
									<span class="aod-cod__radio-price" data-type="home"></span>
								</span>
							</span>
						</label>
						<label class="aod-cod__radio">
							<input type="radio" name="delivery" value="desk">
							<span class="aod-cod__radio-card">
								<span class="aod-cod__radio-icon"><?php echo AOD_COD_Icon::svg( 'desk', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- catalogue FERMÉ, aucune saisie ne l'atteint ; wp_kses_post() mangerait les attributs SVG. ?></span>
								<span class="aod-cod__radio-text">
									<span class="aod-cod__radio-title"><?php esc_html_e( 'Stop-desk (bureau)', 'aod-cod-form' ); ?></span>
									<span class="aod-cod__radio-price" data-type="desk"></span>
								</span>
							</span>
						</label>
					</div>
					<p class="aod-cod__free-hint" hidden></p>
				</div>
				<div class="aod-cod__field aod-cod__address aod-cod__float">
					<input type="text" id="aod-cod-address" name="address" autocomplete="street-address" placeholder=" ">
					<label for="aod-cod-address"><?php esc_html_e( 'Adresse', 'aod-cod-form' ); ?></label>
				</div>

				<div class="aod-cod__summary">
					<div><span><?php esc_html_e( 'Sous-total', 'aod-cod-form' ); ?></span> <strong class="aod-cod__subtotal"></strong></div>
					<div><span><?php esc_html_e( 'Livraison', 'aod-cod-form' ); ?></span> <strong class="aod-cod__shipping"></strong></div>
					<div class="aod-cod__grand"><span><?php esc_html_e( 'Total', 'aod-cod-form' ); ?></span> <strong class="aod-cod__total"></strong></div>
				</div>

				<?php // Challenge anti-robot Cloudflare Turnstile (invisible si « Managed »). ?>
				<?php echo class_exists( 'AOD_COD_Turnstile' ) ? AOD_COD_Turnstile::instance()->widget_html() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button type="submit" class="aod-cod__submit">
					<?php esc_html_e( 'Confirmer la commande', 'aod-cod-form' ); ?>
				</button>
				<?php
				// 🚨 PAS de `class="button"` ici — c'est CE mot qui a rendu le bouton
				// illisible. La classe du thème fait matcher les règles de boutons
				// d'Astra, dont le CSS dynamique est imprimé APRÈS nos feuilles : son
				// fond (bleu de marque) écrasait notre `background: transparent`,
				// pendant que notre `color` (vert), seule à porter `!important`,
				// tenait bon. Résultat mesuré : vert sur bleu, **1,05:1** — le point
				// d'entrée du panier était invisible. Le bouton porte désormais sa
				// propre peau, fond blanc EXPLICITE (jamais `transparent` : le
				// contraste dépendrait de ce qu'il y a derrière). Cf. la porte
				// `run.sh panier`, qui mesure ce contraste à chaque passage.
				?>
				<?php if ( self::cart_enabled() ) : ?>
					<button type="button" class="aod-cod__addcart">
						<?php esc_html_e( 'Ajouter au panier', 'aod-cod-form' ); ?>
					</button>
				<?php endif; ?>
				<p class="aod-cod__reassure">
					<?php echo AOD_COD_Icon::svg( 'lock', array( 'size' => 14, 'class' => 'aod-cod__reassure-ic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- catalogue FERMÉ, aucune saisie ne l'atteint ; wp_kses_post() mangerait les attributs SVG. ?>
					<?php esc_html_e( 'Paiement à la livraison — vous payez à la réception, aucun versement à l’avance.', 'aod-cod-form' ); ?>
				</p>
				<p class="aod-cod__msg" role="alert"></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Traite la soumission AJAX : valide, crée la commande WooCommerce en COD.
	 */
	public function handle_submit() {
		check_ajax_referer( 'aod_cod_nonce', 'nonce' );

		// --- Anti-spam (bots COD) : aucune friction pour un vrai client, mais on
		// bloque les soumissions automatisées AVANT de créer une commande. Cinq
		// garde-fous invisibles ; en cas de rejet on renvoie un message neutre pour
		// ne pas indiquer au bot lequel a réagi. ---

		// 1 & 2) Honeypot + time-trap, désormais dans AOD_COD_Antibot (étape 32.3).
		//    🚨 L'ABSENCE d'un champ y est aussi disqualifiante que sa mauvaise
		//    valeur : les deux couches se contournaient en NE REMPLISSANT PAS le
		//    piège et en OMETTANT « elapsed » — un robot n'avait qu'à éviter de se
		//    tromper, il doit maintenant reproduire le formulaire. Le raisonnement
		//    complet, et l'arbitrage sur le cache, sont dans class-aod-cod-antibot.php.
		//    On lit ici, après le nonce ci-dessus, et on TRANSMET : le helper ne
		//    touche pas aux superglobales (contrat structurel, leçon de la 24.1).
		$aod_ab = AOD_COD_Antibot::inspect(
			array_key_exists( 'aod_url', $_POST ) ? sanitize_text_field( wp_unslash( $_POST['aod_url'] ) ) : null,
			array_key_exists( 'elapsed', $_POST ) ? sanitize_text_field( wp_unslash( $_POST['elapsed'] ) ) : null
		);
		if ( '' !== $aod_ab ) {
			// 🔴 JOURNALISÉ, et c'est ce qui rend le durcissement défendable : un
			// acheteur bloqué à tort cesse d'être une vente perdue en silence.
			AOD_COD_Log::error( 'antibot', 'Commande refusée — ' . AOD_COD_Antibot::reason( $aod_ab ) . '.' );
			// Message NEUTRE : ne jamais dire au robot quelle couche a réagi.
			wp_send_json_error( array( 'message' => __( 'Une erreur est survenue. Veuillez réessayer.', 'aod-cod-form' ) ), 400 );
		}

		// 3) Coupe-circuit anti-flood par IP : un magasin COD ne reçoit jamais des
		//    dizaines de commandes/heure depuis UNE même IP. Une telle rafale = robot
		//    (typiquement une IP de datacenter qui change de numéro à chaque envoi,
		//    contournant la limite par téléphone). Seuil volontairement large
		//    (AOD_COD_MAX_PER_IP_HOUR) pour ne JAMAIS gêner le CGNAT mobile DZ, mais
		//    qui écrase un flood (~1/seconde). On compte chaque tentative valide.
		//    🔴 Source d'IP : AOD_COD_IP::client() et NON WC_Geolocation (étape 26.1).
		//    WooCommerce renvoie l'en-tête « X-Real-IP » BRUT, sans le valider — un
		//    client forge donc une IP neuve à chaque requête, et ce compteur ne
		//    comptait plus rien. Cf. le docblock de class-aod-cod-ip.php.
		$ip = AOD_COD_IP::client();
		if ( '' !== $ip ) {
			$ip_key   = 'aod_cod_ipf_' . md5( $ip );
			$ip_count = (int) get_transient( $ip_key );
			if ( $ip_count >= AOD_COD_MAX_PER_IP_HOUR ) {
				wp_send_json_error( array( 'message' => __( 'Trop de commandes depuis votre connexion. Réessayez plus tard ou contactez-nous.', 'aod-cod-form' ) ), 429 );
			}
			set_transient( $ip_key, $ip_count + 1, HOUR_IN_SECONDS );
		}

		// 4) Challenge anti-robot Cloudflare Turnstile (si configuré). Parade de fond
		//    contre la rotation d'IP : un bot qui POST en direct sur admin-ajax.php n'a
		//    jamais de jeton valide → rejeté, même derrière 300 proxies. Fail-open si
		//    Cloudflare est injoignable (voir AOD_COD_Turnstile::verify()).
		if ( class_exists( 'AOD_COD_Turnstile' ) ) {
			$ts = AOD_COD_Turnstile::instance();
			if ( $ts->is_enabled() ) {
				$ts_token = isset( $_POST['cf_turnstile'] ) ? sanitize_text_field( wp_unslash( $_POST['cf_turnstile'] ) ) : '';
				$ts_ip    = isset( $ip ) && '' !== $ip ? $ip : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
				if ( ! $ts->verify( $ts_token, $ts_ip ) ) {
					wp_send_json_error( array( 'message' => __( 'Vérification anti-robot échouée. Rechargez la page et réessayez.', 'aod-cod-form' ) ), 403 );
				}
			}
		}

		// 5) Limitation « 1 commande / 24 h par numéro de téléphone » : appliquée
		//    plus bas, une fois le numéro validé (transient « aod_cod_ph_<hash> »,
		//    posé après création de la commande). Complémentaire du coupe-circuit IP :
		//    le téléphone bloque le doublon honnête, l'IP bloque le flood automatisé.

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone_raw  = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$wilaya     = isset( $_POST['wilaya'] ) ? absint( $_POST['wilaya'] ) : 0;
		$commune    = isset( $_POST['commune'] ) ? sanitize_text_field( wp_unslash( $_POST['commune'] ) ) : '';
		$address    = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
		$delivery   = ( isset( $_POST['delivery'] ) && 'desk' === $_POST['delivery'] ) ? 'desk' : 'home';
		// Garde-fou : si le livreur d'envoi auto ne gère pas le stop-desk (ex. ZR
		// Express), toute commande « bureau » est ramenée à « domicile » — évite un
		// colis rejeté à l'envoi et un tarif stop-desk facturé sans relais.
		if ( 'desk' === $delivery && ! $this->active_carrier_supports_desk() ) {
			$delivery = 'home';
		}
		// Articles : chaque entrée = une combinaison de variantes + sa quantité,
		// ex. items[0][opt][0]=L, items[0][opt][1]=Rouge, items[0][qty]=2.
		// Depuis la 35.2, un item peut porter son propre items[i][product_id]
		// (panier COD multi-produits) ; à défaut il retombe sur la racine.
		$items_raw  = ( isset( $_POST['items'] ) && is_array( $_POST['items'] ) ) ? wp_unslash( $_POST['items'] ) : array();
		// Rétro-compat : ancien format mono-article (opt[]=… + qty).
		if ( ! $items_raw && ( isset( $_POST['opt'] ) || isset( $_POST['qty'] ) ) ) {
			$items_raw = array( array(
				'opt' => ( isset( $_POST['opt'] ) && is_array( $_POST['opt'] ) ) ? wp_unslash( $_POST['opt'] ) : array(),
				'qty' => isset( $_POST['qty'] ) ? absint( $_POST['qty'] ) : 1,
			) );
		}

		// Normalise le téléphone DZ : 9 ou 10 chiffres commençant par 0.
		$phone = preg_replace( '/\D+/', '', $phone_raw );

		// --- Validations serveur (ne jamais faire confiance au client) ---
		$errors = array();
		if ( ! preg_match( '/^0[5-7][0-9]{8}$/', $phone ) ) {
			$errors[] = __( 'Numéro de téléphone invalide.', 'aod-cod-form' );
		} elseif ( false !== get_transient( 'aod_cod_ph_' . md5( $phone ) ) ) {
			// Anti-spam : une seule commande par numéro toutes les 24 h. Le jeton est
			// posé après la création effective de la commande (voir plus bas).
			$errors[] = __( 'Une commande a déjà été enregistrée avec ce numéro. Réessayez dans 24 h ou contactez-nous.', 'aod-cod-form' );
		}
		if ( ! $wilaya || '' === AOD_COD_Data::wilaya_name( $wilaya ) ) {
			$errors[] = __( 'Wilaya invalide.', 'aod-cod-form' );
		} elseif ( '' !== $commune && ! AOD_COD_Data::commune_valid( $wilaya, $commune ) ) {
			// Commune facultative : on ne la rejette que si elle est renseignée mais invalide.
			$errors[] = __( 'Commune invalide.', 'aod-cod-form' );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() ) {
			$errors[] = __( 'Produit indisponible.', 'aod-cod-form' );
		}

		// Chaque article devient une ligne de commande WooCommerce distincte
		// (visible dans le tableau ET le détail de la commande), avec son propre
		// jeu de variantes (Taille=L, Couleur=Rouge…) et sa quantité. Le client peut
		// ainsi commander, par ex., 2× « L Rouge » + 1× « M Bleu » en une seule fois
		// — et, panier COD activé (35.2), mélanger plusieurs produits.
		$lines = array();
		if ( $product ) {
			$lines = $this->price_lines( $this->build_order_lines( $items_raw, $product, $errors ) );
			if ( ! $lines && ! $errors ) {
				$errors[] = __( 'Veuillez ajouter au moins un article.', 'aod-cod-form' );
			}
		}

		if ( $errors ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ), 400 );
		}

		// --- Création de la commande ---
		try {
			$order    = wc_create_order();
			$subtotal = 0;
			foreach ( $lines as $line ) {
				$line_total   = $line['line_total'];
				$line_product = ( $line['pid'] === $product->get_id() ) ? $product : wc_get_product( $line['pid'] );
				$item_id      = $order->add_product( $line_product, $line['qty'] );
				if ( $item_id ) {
					// 🔴 `false` = L'INSTANCE DÉJÀ EN MÉMOIRE, PAS UNE COPIE RELUE EN BASE.
					// `get_item( $id )` vaut `get_item( $id, true )` : WooCommerce rend un
					// objet NEUF, détaché de la commande. Le prix posé dessus s'écrit bien
					// dans la ligne — mais `calculate_totals()` (plus bas) additionne
					// `$order->get_items()`, c'est-à-dire les objets MIS EN CACHE par
					// `add_product()`, restés au prix catalogue. Le total encaissé ignorait
					// donc tout écart au tarif de base : prix de lot, supplément de variante.
					// Mesuré sur le bac à sable le 2026-08-15 : commande de 2 sacs à
					// 5 250 (prix de lot) totalisée à 2 × 5 400 — 300 د.ج de trop, réclamés
					// à la livraison ; et un casque à +500 de supplément encaissé 500 de
					// moins. Le montant COD, c'est ce total : l'écart part chez le client.
					$item = $order->get_item( $item_id, false );
					if ( $item ) {
						$item->set_subtotal( $line_total );
						$item->set_total( $line_total );
						// Choix de variantes visibles sur la commande (Taille : L, Couleur : Rouge…).
						foreach ( $line['option_meta'] as $olabel => $oval ) {
							$item->add_meta_data( $olabel, $oval, true );
						}
						$item->save();
					}
				}
				$subtotal += $line_total;
			}

			$address_parts = array(
				'first_name' => $name,
				'phone'      => $phone,
				'address_1'  => $address,
				'city'       => $commune,
				'state'      => 'DZ-' . str_pad( $wilaya, 2, '0', STR_PAD_LEFT ),
				'country'    => 'DZ',
			);
			$order->set_address( $address_parts, 'billing' );
			$order->set_address( $address_parts, 'shipping' );

			// Frais de livraison (seuil de gratuité appliqué côté serveur).
			$base_cost = AOD_COD_Data::price_for( $wilaya, $delivery );
			$ship_cost = AOD_COD_Data::effective_shipping( $wilaya, $delivery, $subtotal );
			$is_free   = ( $base_cost > 0 && $ship_cost <= 0 );

			$label = ( 'desk' === $delivery )
				? __( 'Stop-desk', 'aod-cod-form' )
				: __( 'Domicile', 'aod-cod-form' );

			if ( $is_free ) {
				// Ligne à 0 pour que le client voie « Livraison offerte » sur la commande.
				$item = new WC_Order_Item_Shipping();
				$item->set_method_title( sprintf( '%s — %s', __( 'Livraison offerte', 'aod-cod-form' ), AOD_COD_Data::wilaya_name( $wilaya ) ) . ' (' . $label . ')' );
				$item->set_method_id( 'aod_cod' );
				$item->set_total( 0 );
				$order->add_item( $item );
			} elseif ( $ship_cost > 0 ) {
				$item = new WC_Order_Item_Shipping();
				$item->set_method_title( sprintf( '%s — %s', __( 'Livraison', 'aod-cod-form' ), AOD_COD_Data::wilaya_name( $wilaya ) ) . ' (' . $label . ')' );
				$item->set_method_id( 'aod_cod' );
				$item->set_total( $ship_cost );
				$order->add_item( $item );
			}

			$order->set_payment_method( 'cod' );
			$order->set_payment_method_title( __( 'Paiement à la livraison', 'aod-cod-form' ) );

			// Métadonnées utiles pour la préparation/livraison.
			$order->update_meta_data( '_aod_wilaya_code', $wilaya );
			$order->update_meta_data( '_aod_wilaya_name', AOD_COD_Data::wilaya_name( $wilaya ) );
			$order->update_meta_data( '_aod_wilaya_name_ar', AOD_COD_Data::wilaya_name_ar( $wilaya ) );
			$order->update_meta_data( '_aod_commune', $commune );
			$order->update_meta_data( '_aod_commune_ar', AOD_COD_Data::commune_name_ar( $wilaya, $commune ) );
			$order->update_meta_data( '_aod_delivery_type', $delivery );
			// Le bureau stop-desk n'est plus saisi par l'acheteur : il est déduit de la
			// wilaya + commune au moment de l'envoi au transporteur.
			$order->update_meta_data( '_aod_source', 'aod-cod-form' );

			$order->calculate_totals();
			$order->update_status( 'processing', __( 'Commande COD via le formulaire AOD.', 'aod-cod-form' ) );

			// Anti-spam : verrou « 1 commande / 24 h » sur ce numéro (posé seulement
			// après une commande réellement créée — voir la vérification plus haut).
			set_transient( 'aod_cod_ph_' . md5( $phone ), 1, DAY_IN_SECONDS );

			// Le prospect (panier abandonné) devient une commande confirmée.
			if ( class_exists( 'AOD_COD_Leads' ) ) {
				$lead_token = isset( $_POST['lead_token'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_token'] ) ) : '';
				AOD_COD_Leads::mark_converted( $lead_token, $order->get_id() );
			}

			// Permet aux modules (notif WhatsApp, etc.) de réagir à la commande COD finalisée.
			do_action( 'aod_cod_order_created', $order );

			// Pas de redirection vers la page WooCommerce « commande reçue » (tunnel
			// checkout exclu de ce template) : le formulaire affiche un remerciement
			// en place via les libellés i18n (success_title / order_label / success_sub).
			//
			// Le hook woocommerce_thankyou ne se déclenchant donc jamais, on expose la
			// charge de conversion publicitaire (Meta/TikTok/Snapchat/Google) pour la
			// déclencher en JS juste après — sinon aucun achat n'est jamais mesuré.
			$pixels = class_exists( 'AOD_COD_Pixels' )
				? AOD_COD_Pixels::instance()->purchase_payload( $order )
				: null;

			wp_send_json_success( array(
				'order_id' => $order->get_id(),
				'message'  => __( 'Merci ! Votre commande est bien enregistrée.', 'aod-cod-form' ),
				'pixels'   => $pixels,
			) );
		} catch ( Exception $e ) {
			// 🚨 CE `catch` N'INSPECTAIT NI NE JOURNALISAIT `$e` (corrigé en 31.3).
			// C'est le point le plus coûteux de tout le dépôt : ici, une VENTE est
			// perdue. L'acheteur voyait « Erreur lors de la création de la
			// commande », fermait la page, et il ne restait **rien** — ni trace,
			// ni commande, ni prospect exploitable. Le gérant ne pouvait même pas
			// savoir que ça s'était produit, encore moins combien de fois.
			//
			// On ne change PAS ce que voit l'acheteur : le détail technique ne
			// remonte jamais au navigateur (il nommerait des tables et des
			// chemins). Il part au journal, où le dépannage le cherchera.
			AOD_COD_Log::error( 'commande', sprintf(
				'Création de commande échouée — %s (%s:%d)',
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			) );
			wp_send_json_error( array( 'message' => __( 'Erreur lors de la création de la commande.', 'aod-cod-form' ) ), 500 );
		}
	}

	/**
	 * Ce produit peut-il être commandé à CETTE quantité, stock compris ? (44.1)
	 *
	 * 🚨 POURQUOI UN HELPER PARTAGÉ, et pas deux tests copiés. Deux endroits doivent
	 * répondre à la même question : `build_order_lines()` (qui REFUSE la ligne) et
	 * `AOD_COD_Cart::ajax_items()` (qui la marque `available: false` dans le panneau).
	 * Si les deux divergeaient, un produit refusé à la commande resterait « ajoutable »
	 * au panier — ou, pire, sortirait du panneau SANS UN MOT : `ajax_items()` scinde
	 * `$alive`/`$dead` AVANT l'estimation, donc une ligne refusée plus loin ne serait
	 * ni dans les lignes vivantes ni dans les mortes. Le correctif de sécurité aurait
	 * créé un défaut d'honnêteté d'écran. Une seule réponse à une seule question —
	 * c'est le fil rouge de la Phase 44.
	 *
	 * Sémantique alignée sur le CŒUR (`WC_Cart::check_cart_item_stock`) plutôt
	 * qu'inventée ici : hors stock ⇒ non ; backorders autorisés ⇒ oui, quel que soit
	 * le stock ; sinon la quantité doit tenir. Stock non géré ⇒ `has_enough_stock()`
	 * rend vrai, donc jamais bloqué (c'est le cas nominal d'un clone neuf).
	 *
	 * ⚠️ TOCTOU ASSUMÉ : deux commandes concurrentes franchissent cette garde toutes
	 * les deux. Le décrément RÉEL reste différé à la confirmation (40.5, décision D3).
	 * Elle coupe l'abus grossier — elle ne garantit pas l'exactitude, et ne doit pas
	 * être vendue pour ce qu'elle n'est pas.
	 *
	 * @param mixed $product Produit (WC_Product attendu).
	 * @param int   $qty     Quantité demandée pour la ligne.
	 * @return bool
	 */
	public static function orderable_stock( $product, $qty ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		// `is_in_stock()` rend faux pour `outofstock` SEULEMENT : un produit
		// `onbackorder` le passe, et c'est voulu — la branche backorders juste en
		// dessous a le dernier mot.
		if ( ! $product->is_in_stock() ) {
			return false;
		}
		if ( $product->backorders_allowed() ) {
			return true;
		}
		return $product->has_enough_stock( max( 1, (int) $qty ) );
	}

	/**
	 * Résout les articles bruts du POST en lignes de commande validées (35.2).
	 *
	 * Multi-produits : chaque item peut porter son `product_id` ; à défaut il
	 * retombe sur le produit RACINE du formulaire (payload historique inchangé
	 * à l'octet). Si le panier COD est coupé (`cart_enabled()`), un item visant
	 * un AUTRE produit que la racine est REJETÉ — pas ignoré, pas re-routé : le
	 * choix du gérant est un contrat, et « toggle OFF = la surface serveur
	 * d'avant » vaut aussi pour un payload forgé. (Aucun enjeu de prix — tout
	 * est recalculé serveur — c'est la surface qu'on fige.)
	 *
	 * Un `product_id` inconnu, non publié ou non achetable est une ERREUR,
	 * jamais un repli silencieux sur la racine : re-tarifer un autre produit
	 * que celui demandé serait le pire des replis.
	 *
	 * 🚨 MODE TOLÉRANT (`$tolerant`, Phase 43.6) — JAMAIS sur le chemin d'achat.
	 * Un panier abandonné ne mémorise AUCUN choix de variante (`collectLead()`
	 * n'envoie ni `opt` ni équivalent) : exiger ce choix pour ESTIMER un montant
	 * rendrait la colonne vide sur tout produit à variantes. En mode tolérant,
	 * une section d'options sans choix est donc IGNORÉE — prix de base, aucun
	 * supplément — et `$approx` passe à `true` pour que l'appelant le DISE
	 * (préfixe « ≈ »). Un montant approché annoncé comme tel est utile ; un
	 * montant approché présenté comme exact serait un mensonge.
	 * Le défaut est `false` : `handle_submit()` continue d'exiger chaque choix.
	 *
	 * @param array      $items_raw Items bruts (déjà unslashed).
	 * @param WC_Product $root      Produit racine du formulaire (déjà validé).
	 * @param array      $errors    Collecteur d'erreurs (par référence).
	 * @param bool       $tolerant  Estimation : un choix de variante manquant n'est plus une erreur.
	 * @param bool       $approx    (par référence) passé à `true` si une variante a été ignorée.
	 * @return array[] Lignes { pid, qty, option_meta, supplement }.
	 */
	private function build_order_lines( array $items_raw, $root, array &$errors, $tolerant = false, &$approx = false ) {
		// 44.1 — PLAFOND DE CHARGE, avec la constante DÉJÀ EXISTANTE. L'endpoint de
		// LECTURE du panier borne à 30 depuis la 35.3 (`AOD_COD_Cart::ajax_items()`) ;
		// le chemin d'ÉCRITURE ne bornait rien. Un POST à ~330 entrées — plafond de
		// fait de `max_input_vars`, pas du code — produisait autant de
		// `wc_get_product()` + `add_product()` + `save()` dans une seule requête.
		//
		// 🚨 POSÉ ICI ET NON DANS `handle_submit()`, pour deux raisons. (1) C'est le
		// funnel UNIQUE : tous les appelants en héritent, y compris l'estimation.
		// (2) `handle_submit()` termine le processus (`wp_send_json_*`) et reste donc
		// **inéprouvable** par un cliquet — y poser une borne, c'était la rendre
		// invérifiable le jour même où on l'écrit.
		//
		// On ÉCRÊTE (et on ne refuse pas) : la borne existe pour protéger LA REQUÊTE.
		// On réutilise la constante du panier plutôt que d'en inventer une seconde —
		// deux plafonds jumeaux divergent, et c'est exactement le défaut de
		// propagation que cette phase corrige.
		//
		// 🚨 LA RAISON ÉCRITE ICI EN 44.1 A CESSÉ D'ÊTRE VRAIE (corrigée en D35, le
		// 2026-08-03). Elle disait : « aucune intention d'acheteur à trahir dans une
		// forme que le formulaire ne produit jamais ». C'était exact d'un formulaire
		// mono-produit ; le panier COD, lui, PEUT produire cette forme — et un
		// acheteur y perdait alors un article sans un mot. L'écrêtage reste, mais il
		// n'est plus muet : le navigateur refuse la 31ᵉ ligne (`aod-cart.js`, `add()`)
		// et `AOD_COD_Cart::ajax_items()` ANNONCE dans le panneau ce qu'il a écrêté,
		// pour les stores écrits avant cette version. Ce chemin-ci n'est donc plus
		// atteint que par une charge forgée.
		// La leçon, elle, est générale : une justification qui repose sur ce que le
		// produit NE FAIT PAS doit être relue le jour où il se met à le faire.
		if ( class_exists( 'AOD_COD_Cart' ) ) {
			$items_raw = array_slice( array_values( $items_raw ), 0, AOD_COD_Cart::MAX_ITEMS );
		}

		$lines    = array();
		$cart_on  = self::cart_enabled();
		$root_id  = $root->get_id();
		$prods    = array( $root_id => $root ); // cache pid => WC_Product.
		$sections = array();                    // cache pid => sections d'options.
		$line_no  = 0;

		foreach ( $items_raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$line_no++;
			$pid = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : $root_id;

			if ( $pid !== $root_id && ! $cart_on ) {
				$errors[] = __( 'Produit indisponible.', 'aod-cod-form' );
				continue;
			}
			if ( ! array_key_exists( $pid, $prods ) ) {
				$prods[ $pid ] = wc_get_product( $pid );
			}
			$p = $prods[ $pid ];
			if ( ! $p || ! $p->is_purchasable() ) {
				$errors[] = __( 'Produit indisponible.', 'aod-cod-form' );
				continue;
			}
			if ( ! isset( $sections[ $pid ] ) ) {
				$sections[ $pid ] = $this->get_product_options( $p );
			}

			$line_qty    = isset( $item['qty'] ) ? max( 1, absint( $item['qty'] ) ) : 1;

			// 44.1 — LES DEUX BORNES HAUTES, en mode STRICT uniquement.
			//
			// Pourquoi `! $tolerant` : le mode tolérant estime le montant d'un panier
			// ABANDONNÉ (43.6). Un produit tombé en rupture depuis n'annule pas ce que
			// le prospect voulait — appliquer ces gardes là viderait la colonne
			// « Montant » de `/gestion`, soit exactement le tiret contre lequel la 43.6
			// s'est battue. Le chemin d'ACHAT, lui, passe toujours ici en strict.
			if ( ! $tolerant && $line_qty > AOD_COD_MAX_QTY_PER_LINE ) {
				/* translators: 1: numéro de l'article, 2: quantité maximale autorisée. */
				$errors[] = sprintf( __( 'Article %1$d : quantité limitée à %2$d par article.', 'aod-cod-form' ), $line_no, AOD_COD_MAX_QTY_PER_LINE );
				continue;
			}
			// 🚨 LA GARDE QUI MANQUAIT. Plus haut, `is_purchasable()` est la SEULE
			// vérification de disponibilité — et elle ne regarde ni le stock ni les
			// backorders : son corps est `exists() && statut publish && '' !== prix`
			// (source Woo 10.8.1, `abstract-wc-product.php:1757`). Comme la commande
			// naît en `processing` et que Woo décrémente sur ce statut exact
			// (`wc-stock-functions.php:126`), une quantité arbitraire sur un produit à
			// stock géré poussait le stock DANS LE NÉGATIF. La garde jumelle existait
			// depuis la 40.4 pour les variantes maison (quelques lignes plus bas) ;
			// elle n'avait jamais été portée au stock Woo du produit.
			if ( ! $tolerant && ! self::orderable_stock( $p, $line_qty ) ) {
				/* translators: 1: numéro de l'article, 2: nom du produit. */
				$errors[] = sprintf( __( 'Article %1$d : « %2$s » n’est pas disponible dans cette quantité.', 'aod-cod-form' ), $line_no, get_the_title( $pid ) );
				continue;
			}

			$opt_sel     = ( isset( $item['opt'] ) && is_array( $item['opt'] ) ) ? $item['opt'] : array();
			$option_meta = array();   // label => valeur, visible sur la commande.
			$supplement  = 0.0;       // somme des suppléments des options de CETTE ligne.
			$line_ok     = true;
			// Photo de la VARIANTE choisie (panneau du panier). Résolue ICI parce que
			// c'est ici qu'on tient déjà `$match` : la retrouver ailleurs supposerait
			// de re-matcher `option_meta` (libellé => nom) contre les sections, donc
			// d'écrire une seconde fois la règle d'appariement — et de la laisser
			// diverger. On préfère la section VISUELLE, comme la fiche produit, qui ne
			// change sa grande photo que pour une section `is-visual` ; à défaut, la
			// première valeur porteuse d'une image (chemin liste déroulante, où la
			// fiche bascule aussi). Vide = repli sur la photo du produit.
			$img_visual = '';
			$img_any    = '';
			foreach ( $sections[ $pid ] as $si => $sec ) {
				$chosen = isset( $opt_sel[ $si ] ) ? sanitize_text_field( (string) $opt_sel[ $si ] ) : '';
				$match  = $this->match_option( $sec, $chosen );
				if ( null === $match ) {
					if ( $tolerant ) {
						// Estimation (43.6) : section ignorée, prix de base, et on
						// note que le résultat est APPROCHÉ. Jamais atteint depuis
						// `handle_submit()`, qui n'active pas ce mode.
						$approx = true;
						continue;
					}
					/* translators: 1: numéro de l'article, 2: nom de la section (Taille, Couleur…) */
					$errors[] = sprintf( __( 'Article %1$d : veuillez choisir « %2$s ».', 'aod-cod-form' ), $line_no, $sec['label'] );
					$line_ok  = false;
					continue;
				}
				// 40.4 — GARDE SERVEUR (le cœur de la story) : une variante en rupture
				// (stock GÉRÉ et ≤ 0) ne peut JAMAIS être commandée, quel que soit le
				// chemin (fiche, panier COD, suggestion) — tous passent par ce funnel. Le
				// grisage côté acheteur n'est qu'un confort ; c'est CETTE garde qui fait
				// foi. `''` = non géré (illimité) : jamais bloqué.
				if ( isset( $match['stock'] ) && '' !== $match['stock'] && (int) $match['stock'] <= 0 ) {
					/* translators: 1: numéro de l'article, 2: nom de la variante. */
					$errors[] = sprintf( __( 'Article %1$d : « %2$s » est en rupture de stock.', 'aod-cod-form' ), $line_no, $this->localize_variant( $match['name'], isset( $match['name_ar'] ) ? $match['name_ar'] : '' ) );
					$line_ok  = false;
					continue;
				}
				$option_meta[ $sec['label'] ] = $match['name'];
				$supplement                  += (float) $match['price'];

				$mimg = isset( $match['img'] ) ? (string) $match['img'] : '';
				if ( '' !== $mimg ) {
					if ( '' === $img_any ) {
						$img_any = $mimg;
					}
					if ( '' === $img_visual && ! empty( $sec['visual'] ) ) {
						$img_visual = $mimg;
					}
				}
			}
			if ( ! $line_ok ) {
				continue;
			}
			$lines[] = array(
				// Position de l'article DANS LA DEMANDE, reportée telle quelle
				// (2026-08-02). C'est ce qui permet à l'appelant de savoir QUEL
				// article n'a pas produit de ligne : sans elle, une ligne refusée
				// ici disparaissait sans laisser d'adresse — l'appelant ne pouvait
				// ni la nommer ni la rendre. Le panier COD y met l'index de son
				// store ; `handle_submit()` ne la lit pas et retombe sur le rang.
				'idx'         => isset( $item['idx'] ) ? (int) $item['idx'] : ( $line_no - 1 ),
				'pid'         => $pid,
				'qty'         => $line_qty,
				'option_meta' => $option_meta,
				'supplement'  => $supplement,
				// URL de vignette de la variante choisie ('' = aucune, l'appelant
				// retombe sur la photo du produit). Taille `woocommerce_thumbnail`,
				// posée par `get_product_options()` — la même que le panneau demande.
				'image'       => ( '' !== $img_visual ) ? $img_visual : $img_any,
			);
		}
		return $lines;
	}

	/**
	 * Pose `line_total` sur chaque ligne (35.2).
	 *
	 * Offres par GROUPE de produit, jamais inter-produits : le lot
	 * « 2 produits : X » d'un produit s'applique à la quantité cumulée de SES
	 * lignes ; les suppléments de variantes restent propres à chaque ligne.
	 * Un payload mono-produit passe par exactement la formule historique
	 * (lot ÷ quantité totale + supplément, × quantité de la ligne).
	 *
	 * @param array[] $lines Lignes issues de build_order_lines().
	 * @return array[] Les mêmes lignes, avec `line_total` (float).
	 */
	private function price_lines( array $lines ) {
		$group_qty = array();
		foreach ( $lines as $l ) {
			$group_qty[ $l['pid'] ] = ( isset( $group_qty[ $l['pid'] ] ) ? $group_qty[ $l['pid'] ] : 0 ) + $l['qty'];
		}
		$per_unit = array();
		foreach ( $group_qty as $pid => $qty ) {
			$per_unit[ $pid ] = $this->offer_lot_total( wc_get_product( $pid ), $qty ) / max( 1, $qty );
		}
		foreach ( $lines as $i => $l ) {
			$lines[ $i ]['line_total'] = ( $per_unit[ $l['pid'] ] + $l['supplement'] ) * $l['qty'];
		}
		return $lines;
	}

	/**
	 * Estimation serveur d'un panier (drawer public, 35.3) — le SEUL pont public
	 * vers build_order_lines()/price_lines() : le panneau affiche exactement ce
	 * que handle_submit() facturera (offres de lot comprises), sans dupliquer la
	 * formule. Lecture seule : rien n'est créé.
	 *
	 * Depuis la 43.6, elle sert AUSSI à estimer le montant d'un panier abandonné
	 * dans `/gestion` — d'où `$tolerant`, qui rend facultatif le choix de
	 * variante que ce contexte ne possède pas (cf. `build_order_lines()`). La clé
	 * `approx` du retour dit si le montant est APPROCHÉ ; l'appelant DOIT le
	 * signaler à l'écran. Le panier public, lui, appelle sans ce mode : ses
	 * lignes portent toujours leurs choix, et son total reste EXACT.
	 *
	 * @param array $items    [{ product_id, opt, qty }] — items déjà assainis.
	 * @param bool  $tolerant Estimation tolérante (43.6) : variante manquante ignorée.
	 * @return array { lines: array[], errors: string[], approx: bool }
	 */
	public function estimate_lines( array $items, $tolerant = false ) {
		$errors = array();
		$approx = false;
		$root   = null;
		foreach ( $items as $it ) {
			$pid = isset( $it['product_id'] ) ? absint( $it['product_id'] ) : 0;
			$p   = $pid ? wc_get_product( $pid ) : null;
			if ( $p && $p->is_purchasable() ) {
				$root = $p;
				break;
			}
		}
		if ( ! $root ) {
			return array( 'lines' => array(), 'errors' => array( __( 'Produit indisponible.', 'aod-cod-form' ) ), 'approx' => false );
		}
		$lines = $this->price_lines( $this->build_order_lines( $items, $root, $errors, (bool) $tolerant, $approx ) );
		return array(
			'lines'  => $lines,
			'errors' => $errors,
			'approx' => (bool) $approx,
		);
	}

	/**
	 * Prix TOTAL du lot pour une quantité donnée, selon les offres du produit.
	 *
	 * Renvoie le prix de l'offre dont le nombre d'unités correspond exactement à la
	 * quantité (« 2 produits : X »), s'il est réellement inférieur au plein tarif.
	 * Sinon — pas d'offre pour cette taille de lot, produit variable, ou qty < 2 —
	 * on facture le plein tarif (prix d'affichage × quantité).
	 *
	 * @param WC_Product $product
	 * @param int        $qty
	 * @return float Prix total à facturer pour le lot.
	 */
	protected function offer_lot_total( $product, $qty ) {
		$base = (float) wc_get_price_to_display( $product );
		$qty  = max( 1, (int) $qty );
		if ( ! $product || $product->is_type( 'variation' ) || $qty < 2 ) {
			return $base * $qty;
		}
		foreach ( $this->get_product_offers( $product ) as $o ) {
			$oqty   = (int) $o['qty'];
			$oprice = (float) $o['price'];
			if ( $oqty === $qty && $oprice > 0 && $oprice < $base * $qty ) {
				return $oprice;
			}
		}
		return $base * $qty;
	}
}
