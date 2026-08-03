<?php
/**
 * Panier COD public (Phase 35.3) — bouton flottant + panneau, sans tunnel Woo.
 *
 * N'existe QUE si le gérant a activé l'option (`AOD_COD_Form::cart_enabled()`,
 * carte « Panier » de /gestion → Livraison, 35.1) : toggle OFF = aucun asset,
 * aucun markup, endpoint refusé — pas un octet de différence avec avant.
 *
 * L'état du panier vit dans le NAVIGATEUR (`localStorage.aod_cart`) : aucun
 * état serveur, pas de session, pas de cookie — la règle 4 (jamais de panier
 * Woo ni de compte) tient, `AOD_COD_No_Tunnel` reste entièrement armé. Le
 * store ne garde que des { product_id, opt (clés FR canoniques — leçon 7.4),
 * qty } : les noms et les prix affichés sont (re)demandés au serveur à chaque
 * ouverture du panneau, dans la langue courante — un titre figé à l'ajout
 * resterait en français après bascule AR, et un prix stocké mentirait après
 * une promo. Le prix qui fait foi reste celui de `handle_submit()` (35.2).
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Cart {

	/** Plafond d'articles acceptés par l'endpoint d'estimation. */
	const MAX_ITEMS = 30;

	/** @var AOD_COD_Cart|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Priorité 20 : `AOD_COD_Form::assets()` (10) ENREGISTRE la feuille du
		// formulaire, dont le panneau de choix a besoin (cf. `assets()`). Une
		// dépendance vers un handle pas encore enregistré est silencieusement
		// abandonnée par WordPress — le panneau sortirait nu, sans une erreur.
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_shell' ) );
		// Estimation lecture seule (visiteur compris) : le drawer peint depuis ici.
		add_action( 'wp_ajax_aod_cod_cart_items', array( $this, 'ajax_items' ) );
		add_action( 'wp_ajax_nopriv_aod_cod_cart_items', array( $this, 'ajax_items' ) );
		// Choix des variantes SUR LA GRILLE (2026-08-02) — lecture seule elle aussi.
		add_action( 'wp_ajax_aod_cod_cart_options', array( $this, 'ajax_options' ) );
		add_action( 'wp_ajax_nopriv_aod_cod_cart_options', array( $this, 'ajax_options' ) );
		// Grilles produits (36.1) : un nœud de DONNÉES par carte — le bouton est
		// posé par le JS, le markup des cartes appartenant au thème.
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'loop_card_data' ), 20 );
		// « Complétez votre commande » sous le formulaire de la fiche classique (36.3).
		add_action( 'woocommerce_after_single_product', array( $this, 'render_suggestions' ) );
	}

	/**
	 * Assets du panier — chargés sur TOUT le public (le bouton flottant vit
	 * partout), seulement si le toggle est actif. Même pattern conditionnel
	 * que `needs_js()` de la landing : OFF = zéro requête.
	 */
	public function assets() {
		if ( ! AOD_COD_Form::cart_enabled() ) {
			return;
		}
		$css = AOD_COD_PATH . 'assets/css/aod-cart.css';
		$js  = AOD_COD_PATH . 'assets/js/aod-cart.js';
		// 🚨 DÉPENDANCE À LA FEUILLE DU FORMULAIRE, et c'est un choix, pas un
		// réflexe. Le panneau de choix (2026-08-02) sert le markup de la fiche
		// (`.aod-cod__optsec` et ses pastilles) sur des pages qui, elles, ne
		// chargeaient que `aod-cart.css` : mesuré, les variantes y sortaient en
		// boutons radio nus. Les deux issues étaient de RECOPIER ces règles ici —
		// donc d'en tenir une seconde version, qui aurait divergé de la fiche au
		// premier ajustement, exactement ce que R2 de `test-panier-peau.php`
		// interdit — ou de charger la feuille qui les porte déjà. On charge.
		// Elle est entièrement scopée `.aod-cod*` (vérifié : aucun sélecteur
		// global), déjà ENREGISTRÉE par `AOD_COD_Form::assets()`, et ne part que
		// si le gérant a allumé le panier. Toggle OFF = toujours zéro octet.
		wp_enqueue_style( 'aod-cod-form' );
		wp_enqueue_style( 'aod-cart', AOD_COD_URL . 'assets/css/aod-cart.css', array( 'aod-cod-form' ), file_exists( $css ) ? (string) filemtime( $css ) : AOD_COD_VERSION );
		wp_enqueue_script( 'aod-cart', AOD_COD_URL . 'assets/js/aod-cart.js', array(), file_exists( $js ) ? (string) filemtime( $js ) : AOD_COD_VERSION, true );
		wp_localize_script( 'aod-cart', 'AOD_CART', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'aod_cod_nonce' ),
			// L'ancre du formulaire COD sur la fiche produit (posée par la landing
			// ET par le formulaire classique — « Commander » y mène).
			'anchor'   => 'aod-commander',
			// Icônes PRÉ-RENDUES (37.2) : le panneau peint ses lignes côté client,
			// et le JS ne doit jamais fabriquer une icône hors catalogue — même
			// parti pris que `window.AOD_CD.icons` dans /gestion.
			'icons'    => AOD_COD_Icon::js_map(),
			// Le plafond de lignes, SERVI au navigateur (D35, 2026-08-03) : le JS
			// doit refuser le 31ᵉ ajout, et il ne doit pas connaître le nombre par
			// cœur. Deux plafonds jumeaux divergent — c'est la même raison qui a
			// fait réutiliser cette constante dans `build_order_lines()` (44.1).
			'max_items' => self::MAX_ITEMS,
			'i18n'     => array(
				'cart'        => __( 'Votre panier', 'aod-cod-form' ),
				'open'        => __( 'Ouvrir le panier', 'aod-cod-form' ),
				'close'       => __( 'Fermer le panier', 'aod-cod-form' ),
				'empty'       => __( 'Votre panier est vide.', 'aod-cod-form' ),
				'remove'      => __( 'Retirer cet article', 'aod-cod-form' ),
				'more'        => __( 'Augmenter la quantité', 'aod-cod-form' ),
				'less'        => __( 'Diminuer la quantité', 'aod-cod-form' ),
				'total'       => __( 'Total estimé', 'aod-cod-form' ),
				'shipping'    => __( 'Livraison calculée à la commande, selon la wilaya.', 'aod-cod-form' ),
				'order'       => __( 'Commander', 'aod-cod-form' ),
				'unavailable' => __( 'Produit indisponible.', 'aod-cod-form' ),
				'added'       => __( 'Article ajouté au panier.', 'aod-cod-form' ),
				'net_error'   => __( 'Erreur réseau. Vérifiez votre connexion et réessayez.', 'aod-cod-form' ),
				'add'         => __( 'Ajouter au panier', 'aod-cod-form' ),
				'added_short' => __( 'Ajouté ✓', 'aod-cod-form' ),
				// Panneau de choix ouvert depuis une carte de grille (2026-08-02).
				'pick'         => __( 'Choisir les options', 'aod-cod-form' ),
				'pick_missing' => __( 'Veuillez sélectionner toutes les options.', 'aod-cod-form' ),
				// Plafond de lignes (D35, 2026-08-03). DEUX phrases, parce que ce
				// sont deux moments : `full` répond à un geste qu'on vient de
				// refuser (« retirez-en un »), `over` constate un panier déjà
				// au-delà — un store écrit avant cette version, ou à la main. Les
				// confondre dirait « retirez-en un » à qui n'a rien demandé.
				// Le nombre est substitué ICI : le JS n'écrit jamais « 30 ».
				/* translators: %d = nombre maximum de lignes distinctes dans le panier. */
				'full'         => sprintf( __( 'Votre panier est limité à %d articles différents. Retirez-en un pour en ajouter un autre.', 'aod-cod-form' ), self::MAX_ITEMS ),
				/* translators: %d = nombre maximum de lignes distinctes dans le panier. */
				'over'         => sprintf( __( 'Seuls les %d premiers articles sont pris en compte : les suivants ne seront pas commandés.', 'aod-cod-form' ), self::MAX_ITEMS ),
				// (Il y avait ici un `choose` = « Choisir les options », affiché sur
				// les cartes des produits à variantes. Le libellé est UNIQUE depuis
				// le 2026-08-02 — la raison est écrite dans `aod-cart.js`, au point
				// qui le lisait. Clé retirée plutôt que laissée morte : une chaîne
				// traduite que plus rien n'affiche se fait ré-employer par erreur,
				// et sa version AR la ferait passer pour vivante.)
			),
		) );
	}

	/**
	 * Où mène la carte d'un produit qu'on ne peut pas ajouter sur place.
	 *
	 * 🚨 UN SEUL CORPS pour les DEUX imprimeurs de cartes (la boucle Woo et les
	 * suggestions) : ils écrivaient la même URL chacun de son côté, et cette
	 * fonction change désormais d'ancre selon le produit — deux copies auraient
	 * divergé au premier ajustement, et l'une des deux grilles aurait continué
	 * d'atterrir sur le formulaire.
	 *
	 * Produit À VARIANTES → `#aod-options`, le bloc de choix : c'est là que le
	 * geste se termine, et la fiche y révèle la phrase qui le dit. Produit sans
	 * options → `#aod-commander`, le formulaire (l'ancre historique) ; ce cas ne
	 * navigue pas aujourd'hui — l'ajout se fait sur la carte — mais l'URL reste
	 * juste si quelqu'un la suit.
	 *
	 * @param WC_Product $product
	 * @return string
	 */
	protected function card_url( $product ) {
		$ancre = AOD_COD_Form::instance()->has_options( $product ) ? 'aod-options' : 'aod-commander';
		return get_permalink( $product->get_id() ) . '#' . $ancre;
	}

	/**
	 * Nœud de données imprimé dans chaque carte de la boucle Woo (36.1).
	 *
	 * PHP ne pose PAS le bouton : le markup des cartes appartient au thème, et
	 * un <button> imprimé dans le <a> de la carte serait du HTML invalide. Le
	 * JS panier lit ce nœud, enveloppe l'image de la carte et pose la
	 * surimpression « Ajouter au panier » (produit à variantes : lien vers la
	 * fiche — on n'ajoute pas des options en devinant).
	 */
	public function loop_card_data() {
		global $product;
		if ( ! AOD_COD_Form::cart_enabled() || ! $product instanceof WC_Product ) {
			return;
		}
		if ( 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
			return;
		}
		printf(
			'<span class="aod-cart__carddata" hidden data-pid="%d" data-hasopts="%s" data-url="%s"></span>',
			(int) $product->get_id(),
			AOD_COD_Form::instance()->has_options( $product ) ? '1' : '0',
			esc_url( $this->card_url( $product ) )
		);
	}

	/**
	 * « Complétez votre commande » — grille courte sous le formulaire (36.3).
	 *
	 * Fiche produit CLASSIQUE uniquement : une page de vente (landing) est une
	 * composition du gérant, on ne lui injecte pas une grille générique. Rendu
	 * serveur au chargement — titres via les filtres 7.2, la langue courante
	 * est servie — chaque carte portant le même nœud de données que les
	 * grilles : le bouton d'ajout est posé par le même JS.
	 */
	public function render_suggestions() {
		if ( ! AOD_COD_Form::cart_enabled() || ! is_product() ) {
			return;
		}
		$current = get_the_ID();
		if ( class_exists( 'AOD_COD_Landing' ) && AOD_COD_Landing::is_enabled( $current ) ) {
			return;
		}
		$found = wc_get_products( array(
			'status'  => 'publish',
			'limit'   => 8,
			'exclude' => array( (int) $current ),
			'orderby' => 'date',
			'order'   => 'DESC',
		) );

		$products = array();
		foreach ( $found as $p ) {
			if ( $p->is_purchasable() ) {
				$products[] = $p;
			}
			if ( count( $products ) >= 4 ) {
				break;
			}
		}
		if ( ! $products ) {
			return;
		}
		$form = AOD_COD_Form::instance();
		?>
		<section class="aod-cart__suggest" aria-label="<?php esc_attr_e( 'Complétez votre commande', 'aod-cod-form' ); ?>">
			<h2 class="aod-cart__suggest-title"><?php esc_html_e( 'Complétez votre commande', 'aod-cod-form' ); ?></h2>
			<div class="aod-cart__suggrid">
				<?php foreach ( $products as $p ) : ?>
					<div class="aod-cart__sugcard">
						<a class="aod-cart__suglink" href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>">
							<?php echo $p->get_image( 'woocommerce_thumbnail', array( 'class' => 'aod-cart__sugimg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup d'image généré par le cœur Woo (wp_get_attachment_image). ?>
							<span class="aod-cart__sugtitle"><?php echo esc_html( get_the_title( $p->get_id() ) ); ?></span>
							<span class="aod-cart__sugprice"><?php echo wp_kses_post( $p->get_price_html() ); ?></span>
						</a>
						<span class="aod-cart__carddata" hidden data-pid="<?php echo (int) $p->get_id(); ?>" data-hasopts="<?php echo $form->has_options( $p ) ? '1' : '0'; ?>" data-url="<?php echo esc_url( $this->card_url( $p ) ); ?>"></span>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Coque du panier : bouton flottant + panneau (cachés/inertes tant que le
	 * JS ne les peuple pas). Imprimée au wp_footer public uniquement.
	 */
	public function render_shell() {
		if ( is_admin() || ! AOD_COD_Form::cart_enabled() ) {
			return;
		}
		?>
		<?php
		// Icône du CATALOGUE (37.2), plus un chemin SVG recopié à la main : même
		// jeu Phosphor que la sidebar de `/gestion`, mêmes attributs reconstruits
		// au rendu. Graisse `fill` — le bouton flottant est une action pleine,
		// posée sur un disque coloré ; c'est la graisse que `/gestion` réserve à
		// l'élément de nav ACTIF, et elle tient mieux en petit sur un fond plein.
		?>
		<button type="button" class="aod-cart__fab" id="aod-cart-fab" hidden aria-label="<?php esc_attr_e( 'Ouvrir le panier', 'aod-cod-form' ); ?>">
			<?php echo AOD_COD_Icon::svg( 'cart', array( 'weight' => 'fill', 'size' => 24 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG d'un catalogue fermé, jamais d'une saisie (cf. AOD_COD_Icon). ?>
			<span class="aod-cart__badge" hidden>0</span>
		</button>
		<div class="aod-cart__overlay" id="aod-cart-overlay" hidden></div>
		<?php
		// Panneau de CHOIX (2026-08-02) — ouvert depuis une carte de grille pour
		// un produit à variantes. Il est vide au rendu : son contenu est demandé
		// au serveur à l'ouverture (`ajax_options`), comme les lignes du panier
		// le sont à chaque peinture, et pour la même raison — les noms, les prix
		// et l'état de rupture doivent être ceux de MAINTENANT, dans la langue
		// courante.
		?>
		<aside class="aod-cart__sheet" id="aod-cart-sheet" role="dialog" aria-modal="true" aria-labelledby="aod-cart-sheet-title" hidden>
			<div class="aod-cart__head">
				<h2 class="aod-cart__title" id="aod-cart-sheet-title"><?php esc_html_e( 'Choisir les options', 'aod-cod-form' ); ?></h2>
				<button type="button" class="aod-cart__close" aria-label="<?php esc_attr_e( 'Fermer', 'aod-cod-form' ); ?>"><?php echo AOD_COD_Icon::svg( 'close', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG d'un catalogue fermé, jamais d'une saisie (cf. AOD_COD_Icon). ?></button>
			</div>
			<p class="aod-cart__sheet-name"></p>
			<div class="aod-cart__sheet-body"></div>
			<p class="aod-cart__sheet-msg" role="alert"></p>
			<div class="aod-cart__foot">
				<?php // `disabled` de naissance : il ne s'arme qu'une fois les options peintes (même raison que « Commander »). ?>
				<button type="button" class="aod-cart__sheet-add" disabled><?php esc_html_e( 'Ajouter au panier', 'aod-cod-form' ); ?></button>
			</div>
		</aside>
		<aside class="aod-cart__drawer" id="aod-cart-drawer" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Votre panier', 'aod-cod-form' ); ?>" hidden>
			<div class="aod-cart__head">
				<h2 class="aod-cart__title"><?php esc_html_e( 'Votre panier', 'aod-cod-form' ); ?></h2>
				<button type="button" class="aod-cart__close" aria-label="<?php esc_attr_e( 'Fermer le panier', 'aod-cod-form' ); ?>"><?php echo AOD_COD_Icon::svg( 'close', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG d'un catalogue fermé, jamais d'une saisie (cf. AOD_COD_Icon). ?></button>
			</div>
			<?php
			// Plafond atteint (D35, 2026-08-03) : le refus d'un ajout se dit ICI.
			// Le geste part d'une carte de grille, qui n'a aucune surface de
			// message ; on ouvre alors le panier — l'acheteur voit du même coup
			// les lignes qui expliquent le refus, et celle qu'il peut retirer.
			?>
			<p class="aod-cart__cap" role="status" hidden></p>
			<div class="aod-cart__list" aria-live="polite"></div>
			<div class="aod-cart__foot">
				<div class="aod-cart__totalrow"><span><?php esc_html_e( 'Total estimé', 'aod-cod-form' ); ?></span> <strong class="aod-cart__total"></strong></div>
				<p class="aod-cart__note"><?php esc_html_e( 'Livraison calculée à la commande, selon la wilaya.', 'aod-cod-form' ); ?></p>
				<?php
				// `disabled` de naissance : il ne s'arme qu'une fois le panneau peint
				// (paint() pose firstUrl). Sans ça, un clic entre l'ouverture et la
				// réponse AJAX tombe dans le vide — course vue par la porte `panier`.
				//
				// 🚨 PAS de `class="button"` : la classe du thème faisait matcher les
				// règles de boutons d'Astra, dont le CSS dynamique est imprimé APRÈS
				// nos feuilles — le bouton sortait au bleu du thème au lieu du vert
				// d'achat, et le bouton de fermeture en rectangle bleu de 75×56 px.
				// Le panier porte sa propre peau, cf. l'en-tête de `aod-cart.css`.
				?>
				<button type="button" class="aod-cart__order" disabled><?php esc_html_e( 'Commander', 'aod-cod-form' ); ?></button>
			</div>
		</aside>
		<?php
	}

	/**
	 * AJAX lecture seule : le bloc de choix de variantes d'UN produit, pour le
	 * panneau qui s'ouvre depuis une carte de grille (2026-08-02).
	 *
	 * 🚨 Pourquoi le SERVEUR et pas le JS : le markup rendu porte l'état de
	 * RUPTURE (`disabled` + `aria-disabled`, 40.4), les noms localisés, et les
	 * clés canoniques FR du matching serveur. Le refaire côté client, c'était en
	 * tenir une seconde copie — et la voir diverger le jour d'une rupture de
	 * stock, c'est-à-dire sur une commande impossible. Cf.
	 * `AOD_COD_Form::options_html_for()`.
	 *
	 * Aucune écriture, aucun état : mêmes gardes que `ajax_items()` — nonce,
	 * toggle, produit publié et achetable.
	 */
	public function ajax_options() {
		check_ajax_referer( 'aod_cod_nonce', 'nonce' );
		if ( ! AOD_COD_Form::cart_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Produit indisponible.', 'aod-cod-form' ) ), 403 );
		}
		$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$p   = $pid ? wc_get_product( $pid ) : null;
		if ( ! $p || 'publish' !== $p->get_status() || ! $p->is_purchasable() ) {
			wp_send_json_error( array( 'message' => __( 'Produit indisponible.', 'aod-cod-form' ) ), 404 );
		}
		$html = AOD_COD_Form::instance()->options_html_for( $p );
		if ( '' === $html ) {
			// Aucun choix à faire : le panneau n'a pas lieu d'être, et le JS doit
			// le savoir plutôt que d'ouvrir une boîte vide.
			wp_send_json_error( array( 'message' => __( 'Produit indisponible.', 'aod-cod-form' ) ), 404 );
		}
		wp_send_json_success( array(
			'title'      => get_the_title( $pid ),
			'price_html' => $p->get_price_html(),
			'options'    => $html,
		) );
	}

	/**
	 * AJAX lecture seule : estime le panier envoyé et renvoie les lignes
	 * peignables (titres dans la langue courante, prix serveur).
	 *
	 * Aucune écriture ; toggle coupé = refus (la surface OFF est celle
	 * d'avant, cf. 35.2) ; plafond d'items ; produits non publiés/achetables
	 * signalés `unavailable` et exclus du total — jamais un repli silencieux.
	 *
	 * 🚨 CONTRAT DE RÉCONCILIATION (2026-08-02) — le cœur de cette méthode.
	 * **Tout article envoyé ressort en ligne, sans exception.** Il ressortait
	 * autrefois une ligne par article que le tri jugeait vivant PUIS que
	 * `build_order_lines()` acceptait — deux juges, et l'écart entre eux
	 * tombait dans le vide : variante en rupture, variante renommée par le
	 * gérant, quantité au-dessus du plafond. L'article disparaissait du panneau
	 * SANS UN MOT, en restant dans le store : badge « 1 » sur un panneau
	 * « vide », aucun bouton pour le retirer, et la commande refusée en bloc
	 * plus tard par `handle_submit()` au nom d'un article absent de l'écran.
	 * Une vente perdue sans issue possible.
	 * Le tri ne PRÉTEND donc plus décider seul : il écarte ce qu'il sait
	 * (produit disparu, quantité au-dessus du stock), puis **compare la demande
	 * au rendu** et range en indisponible tout article que le funnel a refusé
	 * pour une raison qu'il est le seul à connaître. Ajouter ici un test jumeau
	 * par cause aurait recréé la divergence qu'on ferme : `build_order_lines()`
	 * reste la seule autorité, on lit son verdict au lieu de le redevINER.
	 *
	 * Chaque ligne porte `idx`, sa **position dans le store du navigateur** :
	 * c'est l'identité d'une ligne, et non le `product_id` — deux variantes du
	 * même produit sont deux lignes, et les cibler par produit faisait agir
	 * « + » sur la mauvaise.
	 */
	public function ajax_items() {
		check_ajax_referer( 'aod_cod_nonce', 'nonce' );
		if ( ! AOD_COD_Form::cart_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Produit indisponible.', 'aod-cod-form' ) ), 403 );
		}

		$raw = ( isset( $_POST['items'] ) && is_array( $_POST['items'] ) ) ? wp_unslash( $_POST['items'] ) : array();
		// 🚨 L'écrêtage est ANNONCÉ (D35, 2026-08-03). Il était muet : un store de
		// 31 lignes en montrait 30, la commande en portait 30, et l'acheteur ne
		// lisait rien. Le navigateur refuse désormais le 31ᵉ ajout — mais un store
		// écrit avant cette version, ou par une autre voie, existe encore ; c'est
		// le seul endroit qui voit le nombre DEMANDÉ avant de le réduire.
		$demande  = count( $raw );
		$raw      = array_slice( array_values( $raw ), 0, self::MAX_ITEMS );
		$ecretees = max( 0, $demande - count( $raw ) );

		$items = array();
		foreach ( $raw as $i => $it ) {
			if ( ! is_array( $it ) || empty( $it['product_id'] ) ) {
				continue;
			}
			$items[] = array(
				// Position dans le store, reportée jusqu'à la ligne peinte.
				'idx'        => (int) $i,
				'product_id' => absint( $it['product_id'] ),
				'qty'        => isset( $it['qty'] ) ? max( 1, absint( $it['qty'] ) ) : 1,
				'opt'        => ( isset( $it['opt'] ) && is_array( $it['opt'] ) ) ? array_map( 'sanitize_text_field', $it['opt'] ) : array(),
			);
		}
		if ( ! $items ) {
			wp_send_json_success( array( 'lines' => array(), 'total_html' => wc_price( 0 ), 'total' => 0.0, 'truncated' => $ecretees ) );
		}

		// --- 1. Tri de ce que CE code sait juger -----------------------------
		// Produit disparu / dépublié / non achetable, ou quantité au-dessus du
		// stock. `orderable_stock()` est le MÊME helper que le funnel de commande
		// (44.1) : une seule réponse à la même question.
		// 🚨 On distingue « produit indisponible » de « pas à CETTE quantité » :
		// la seconde se répare en cliquant « − », et une ligne qu'on ne peut plus
		// décrémenter est un cul-de-sac. Un « + » de trop figeait la ligne — plus
		// de quantité, plus de total, plus de boutons — et il fallait la
		// supprimer pour repartir de la fiche.
		$refus = array(); // idx => 'gone' | 'qty'
		$alive = array();
		foreach ( $items as $it ) {
			$p = wc_get_product( $it['product_id'] );
			if ( ! $p || 'publish' !== $p->get_status() || ! $p->is_purchasable() ) {
				$refus[ $it['idx'] ] = 'gone';
				continue;
			}
			if ( ! AOD_COD_Form::orderable_stock( $p, $it['qty'] ) ) {
				$refus[ $it['idx'] ] = AOD_COD_Form::orderable_stock( $p, 1 ) ? 'qty' : 'gone';
				continue;
			}
			$alive[] = $it;
		}

		// --- 2. Estimation, puis RÉCONCILIATION -------------------------------
		$rendues = array(); // idx => ligne estimée
		if ( $alive ) {
			$est = AOD_COD_Form::instance()->estimate_lines( $alive );
			foreach ( $est['lines'] as $l ) {
				$rendues[ (int) $l['idx'] ] = $l;
			}
		}
		// --- 3. Peinture, DANS L'ORDRE DU PANIER ------------------------------
		//
		// 🚨 C'EST ICI QUE SE JOUE LA GARANTIE, et nulle part ailleurs : on
		// parcourt les articles DEMANDÉS, pas les lignes rendues. Tout article
		// sans ligne estimée retombe donc en indisponible — y compris quand le
		// funnel l'a refusé pour une raison que le tri ci-dessus ne connaît pas
		// (variante en rupture, variante renommée, quantité au-delà du plafond).
		// Parcourir `$rendues` ferait réapparaître le fantôme le jour même.
		//
		// ⚠️ Une boucle de « réconciliation » explicite a été écrite ici, puis
		// RETIRÉE : mise au rouge, elle n'a rien fait rougir — elle ne faisait que
		// pré-remplir un cas que le repli ci-dessous traite déjà. Du code qui ne
		// peut pas manquer ne prouve rien, et se relit comme le porteur de la
		// garantie : le prochain qui simplifierait la peinture le croirait couvert.
		$lines = array();
		$total = 0.0;
		foreach ( $items as $it ) {
			$idx = $it['idx'];

			if ( isset( $rendues[ $idx ] ) && ! isset( $refus[ $idx ] ) ) {
				$l = $rendues[ $idx ];
				$p = wc_get_product( $l['pid'] );
				if ( ! $p ) {
					continue;
				}
				// Photo de la VARIANTE choisie si elle en a une (2026-08-02), sinon
				// celle du produit. Une ligne « Casque · Bordeaux » illustrée du
				// casque noir décrit un autre article que celui qu'on commande — et
				// deux lignes du même produit y étaient indistinguables, sur la seule
				// image que l'acheteur voit avant de valider.
				// La résolution vit dans `build_order_lines()`, là où l'appariement de
				// la variante est déjà fait : la refaire ici supposerait de re-matcher
				// `option_meta` contre les sections, donc d'écrire une seconde fois la
				// règle d'appariement — et de la laisser diverger.
				$img = ( isset( $l['image'] ) && '' !== $l['image'] ) ? $l['image'] : '';
				if ( '' === $img ) {
					$img = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) : '';
				}
				$total  += (float) $l['line_total'];
				$lines[] = array(
					'idx'          => $idx,
					'product_id'   => $l['pid'],
					// get_the_title passe par le filtre `the_title` : titre AR servi
					// automatiquement quand le cookie aod_lang le demande (7.2).
					'title'        => get_the_title( $l['pid'] ),
					'url'          => get_permalink( $l['pid'] ),
					'img'          => $img,
					'qty'          => (int) $l['qty'],
					'opts'         => $l['option_meta'],
					'total_html'   => wc_price( $l['line_total'] ),
					'available'    => true,
					'qty_editable' => true,
					'message'      => '',
				);
				continue;
			}

			$cause = isset( $refus[ $idx ] ) ? $refus[ $idx ] : 'gone';
			$lines[] = array(
				'idx'          => $idx,
				'product_id'   => $it['product_id'],
				'title'        => get_the_title( $it['product_id'] ),
				'url'          => '',
				'img'          => '',
				// La quantité DEMANDÉE reste affichée quand elle est la cause : la
				// masquer priverait l'acheteur du seul geste qui répare.
				'qty'          => ( 'qty' === $cause ) ? (int) $it['qty'] : 0,
				'opts'         => array(),
				'total_html'   => '',
				'available'    => false,
				'qty_editable' => ( 'qty' === $cause ),
				'message'      => ( 'qty' === $cause )
					? __( 'Quantité non disponible en stock.', 'aod-cod-form' )
					: __( 'Produit indisponible.', 'aod-cod-form' ),
			);
		}

		wp_send_json_success( array(
			'lines'      => $lines,
			'total_html' => wc_price( $total ),
			// Lignes tombées sous le plafond (D35) : le panneau le DIT, dans les
			// deux vues, plutôt que de laisser un article disparaître en silence.
			'truncated'  => $ecretees,
			// Nombre BRUT (36.2) : le récapitulatif du formulaire suit le panier
			// (sous-total, seuil de gratuité) — un montant formaté ne se calcule pas.
			'total'      => (float) $total,
		) );
	}
}
