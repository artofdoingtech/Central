<?php
/**
 * Les 3 pages publiques : Accueil · Boutique · Contact.
 *
 * POURQUOI LE CONTENU EST DANS LE CODE ET NON EN BASE
 * ---------------------------------------------------
 * Une page WordPress stocke son titre et son contenu en base. Or gettext — donc le
 * switcher FR/AR — ne traduit QUE le code. Une page d'accueil dont le texte serait
 * tapé dans l'éditeur resterait en français une fois le site passé en arabe : la
 * règle « tout le visible est bilingue » tomberait, silencieusement.
 *
 * D'où ce choix : les pages créées par scripts/pages-publiques.sh ne sont que des
 * coquilles contenant un shortcode ([aod_home], [aod_contact_form]). Tout le texte
 * visible vit ici, passe par __(), et suit le cookie aod_lang.
 *
 * Même raison pour les libellés du menu : ils viennent de la base. On les retraduit
 * donc à la volée, en reconnaissant les 3 pages par leur meta `_aod_page_key`
 * (posée par le script) — jamais par leur libellé, qu'un clone peut avoir changé.
 *
 * RIEN ICI N'EST SPÉCIFIQUE À UN CLIENT : ni couleur, ni nom, ni image. Le nom et le
 * slogan viennent du Personnalisateur, les produits de WooCommerce, l'habillage du
 * thème déposé par l'owner. C'est un SOCLE, pas une maquette.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Pages {

	/** Meta posée sur les 3 pages par scripts/pages-publiques.sh. */
	const META_KEY = '_aod_page_key';

	/** @var AOD_COD_Pages|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'aod_home', array( $this, 'render_home' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'wp_nav_menu_objects', array( $this, 'translate_menu_labels' ), 10 );
		// Bandeau « Merci, commande enregistrée » : la fiche produit redirige ici
		// après une commande COD réussie (cf. showSuccess() dans aod-cod-form.js).
		add_action( 'wp_footer', array( $this, 'render_thankyou_banner' ) );

		// Accueil et Contact rendent leur PROPRE titre (le nom de la boutique dans le
		// héro, « Nous contacter » sur la page Contact). Si le thème affiche en plus le
		// titre de la page, on obtient « Accueil » puis « Ma Boutique » — deux <h1> sur
		// la même page, ce qui est une faute d'accessibilité et de SEO.
		//
		// Astra expose un filtre pour ça. Un clone sur un AUTRE thème doit masquer le
		// titre de ces 2 pages dans les réglages du thème — c'est noté dans
		// CHECKLIST-THEME-CHILD.md.
		add_filter( 'astra_the_title_enabled', array( $this, 'hide_theme_title' ) );
	}

	/**
	 * @param bool $enabled Le thème doit-il afficher le titre de la page ?
	 * @return bool
	 */
	public function hide_theme_title( $enabled ) {
		if ( ! is_page() ) {
			return $enabled;
		}

		$key = get_post_meta( get_queried_object_id(), self::META_KEY, true );

		// La Boutique garde son titre : c'est une archive WooCommerce, son en-tête est
		// légitime. Seules les 2 pages à shortcode le rendent elles-mêmes.
		return in_array( $key, array( 'home', 'contact' ), true ) ? false : $enabled;
	}

	/**
	 * CSS des pages publiques. Chargé partout : le menu et le pied de page en ont
	 * besoin, pas seulement les 2 pages qui portent un shortcode.
	 *
	 * Le JS de surimpression du bouton « Commander » (demande owner, 2026-09-10),
	 * lui, ne part QUE si le panier COD est éteint — même logique que
	 * `AOD_COD_Cart::assets()` en miroir : panier ACTIF = `.aod-loop-cta` est
	 * retiré du DOM affiché (`display:none`, `aod-cart.css`) et ce script n'a
	 * plus rien à déplacer. Toggle ON = zéro octet de ce fichier-ci.
	 */
	public function assets() {
		$path = AOD_COD_PATH . 'assets/css/aod-cod-pages.css';
		$ver  = file_exists( $path ) ? (string) filemtime( $path ) : AOD_COD_VERSION;
		wp_enqueue_style( 'aod-cod-pages', AOD_COD_URL . 'assets/css/aod-cod-pages.css', array(), $ver );

		if ( AOD_COD_Form::cart_enabled() ) {
			return;
		}
		$js_path = AOD_COD_PATH . 'assets/js/aod-cod-pages.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : AOD_COD_VERSION;
		wp_enqueue_script( 'aod-cod-pages', AOD_COD_URL . 'assets/js/aod-cod-pages.js', array(), $js_ver, true );
	}

	/**
	 * Retraduit les libellés du menu pour les 3 pages du socle.
	 *
	 * Deux garde-fous pour ne pas écraser le travail d'un clone :
	 *  - on ne touche qu'aux pages portant la meta `_aod_page_key` ;
	 *  - et seulement si le libellé du menu est resté identique au titre de la page.
	 *    Un gérant qui saisit un libellé personnalisé dans Apparence → Menus le garde
	 *    (à lui de le traduire) ; c'est son choix, pas une erreur à corriger.
	 *
	 * @param array $items Entrées du menu.
	 * @return array
	 */
	public function translate_menu_labels( $items ) {
		if ( ! is_array( $items ) ) {
			return $items;
		}

		foreach ( $items as $item ) {
			if ( ! isset( $item->object, $item->object_id ) || 'page' !== $item->object ) {
				continue;
			}

			$page_id = (int) $item->object_id;
			$key     = get_post_meta( $page_id, self::META_KEY, true );
			if ( ! $key ) {
				continue;
			}

			// Libellé personnalisé par le gérant → on ne touche à rien.
			$page_title = get_the_title( $page_id );
			if ( isset( $item->title ) && $item->title !== $page_title ) {
				continue;
			}

			$label = $this->page_label( $key );
			if ( '' !== $label ) {
				$item->title = $label;
			}
		}

		return $items;
	}

	/**
	 * @param string $key home|shop|contact
	 * @return string Libellé traduit, ou '' si la clé est inconnue.
	 */
	protected function page_label( $key ) {
		switch ( $key ) {
			case 'home':
				return __( 'Accueil', 'aod-cod-form' );
			case 'shop':
				return __( 'Boutique', 'aod-cod-form' );
			case 'contact':
				return __( 'Contact', 'aod-cod-form' );
		}
		return '';
	}

	/**
	 * Coquille + script de lecture du bandeau de remerciement, imprimés
	 * UNIQUEMENT sur la Boutique.
	 *
	 * Le contenu (titre, n° de commande, sous-titre — déjà dans la langue de
	 * l'acheteur, cf. `AOD_COD.i18n` côté fiche produit) est déposé par
	 * `showSuccess()` (aod-cod-form.js) dans `sessionStorage` juste avant la
	 * redirection vers cette page, PUIS lu et effacé ici, au premier rendu.
	 * Aucun état serveur : un rechargement de la Boutique n'affiche rien.
	 *
	 * Imprimé en JS inline plutôt que via un fichier enregistré : ce bandeau
	 * n'a besoin d'aucune dépendance (pas de jQuery, pas de AOD_COD/AOD_CART,
	 * qui ne sont localisés que sur la fiche produit / que si le panier COD
	 * est actif) — le coupler à l'un des deux le ferait dépendre d'un toggle
	 * qui n'a rien à voir avec lui.
	 */
	public function render_thankyou_banner() {
		if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
			return;
		}
		?>
		<div id="aod-cod-thankyou" class="aod-cod-thankyou" role="status" aria-live="polite" hidden>
			<div class="aod-cod-thankyou__ic" aria-hidden="true">✓</div>
			<div class="aod-cod-thankyou__body">
				<p class="aod-cod-thankyou__title"></p>
				<p class="aod-cod-thankyou__order"></p>
				<p class="aod-cod-thankyou__sub"></p>
			</div>
			<button type="button" class="aod-cod-thankyou__close" aria-label="<?php esc_attr_e( 'Fermer', 'aod-cod-form' ); ?>">
				<?php echo AOD_COD_Icon::svg( 'close', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG d'un catalogue fermé, jamais d'une saisie (cf. AOD_COD_Icon). ?>
			</button>
		</div>
		<script>
		( function () {
			// Silence volontaire : sessionStorage.getItem/JSON.parse peuvent lever
			// (navigation privée, stockage plein, valeur corrompue). La commande est
			// déjà enregistrée — au pire le bandeau reste caché, jamais bloquant.
			try {
				var raw = window.sessionStorage.getItem( 'aod_cod_thankyou' );
				if ( ! raw ) { return; }
				window.sessionStorage.removeItem( 'aod_cod_thankyou' );
				var data = JSON.parse( raw );
				var el = document.getElementById( 'aod-cod-thankyou' );
				if ( ! el || ! data ) { return; }
				var setOrRemove = function ( sel, val ) {
					var node = el.querySelector( sel );
					if ( val ) { node.textContent = val; } else { node.parentNode.removeChild( node ); }
				};
				setOrRemove( '.aod-cod-thankyou__title', data.title || '' );
				setOrRemove( '.aod-cod-thankyou__order', data.order || '' );
				setOrRemove( '.aod-cod-thankyou__sub', data.sub || '' );

				// Le bandeau flotte SOUS l'en-tête du thème, jamais dessus : mesuré,
				// un en-tête mobile pleine largeur (logo + burger + icônes) se
				// retrouvait recouvert par le coin haut par défaut (16px/8px), quel
				// que soit le thème déposé dans library/themes/ (§4 du template).
				// `header`/`#masthead` couvre les thèmes classiques visés (Astra,
				// GeneratePress, Neve, Blocksy, Kadence) ; à défaut, repli sur 8px.
				var header = document.querySelector( '#masthead' ) || document.querySelector( 'header' );
				var top    = 8;
				if ( header ) {
					var r = header.getBoundingClientRect();
					if ( r.bottom > top ) { top = r.bottom + 8; }
				}
				el.style.top = top + 'px';

				el.hidden = false;
				var closeBtn = el.querySelector( '.aod-cod-thankyou__close' );
				if ( closeBtn ) {
					closeBtn.addEventListener( 'click', function () { el.hidden = true; } );
				}
			} catch ( e ) {}
		} )();
		</script>
		<?php
	}

	/**
	 * Trie une liste d'items catégorie `{ id, order, name }` pour l'accueil (9.2).
	 *
	 * Pur et statique : le tri se prouve sans base (cf. test-home-categories.php).
	 * Clé primaire = l'ordre saisi par le gérant ; tie-breaks = la clé `name` puis
	 * l'id — ce dernier rend le tri DÉTERMINISTE même à `name` égal (`usort` n'est
	 * stable qu'en PHP ≥ 8). Rend la liste des ids ordonnés, prête pour `orderby="include"`.
	 *
	 * ⚠️ L'appelant (`home_category_ids()`) passe le **slug** dans `name`, pas le nom
	 * affiché : le nom est traduit en AR (filtre 7.5), le trier inverserait les ex æquo
	 * entre FR et AR. Le slug est invariant par langue → ordre identique dans les deux.
	 *
	 * @param array $items [ [ 'id' => int, 'order' => int, 'name' => string ], … ].
	 * @return int[] Ids triés.
	 */
	public static function sort_home_category_ids( array $items ) {
		usort( $items, static function ( $a, $b ) {
			$ao = isset( $a['order'] ) ? (int) $a['order'] : 0;
			$bo = isset( $b['order'] ) ? (int) $b['order'] : 0;
			if ( $ao !== $bo ) {
				return $ao <=> $bo;
			}
			$cmp = strcasecmp( isset( $a['name'] ) ? (string) $a['name'] : '', isset( $b['name'] ) ? (string) $b['name'] : '' );
			if ( 0 !== $cmp ) {
				return $cmp;
			}
			return ( isset( $a['id'] ) ? (int) $a['id'] : 0 ) <=> ( isset( $b['id'] ) ? (int) $b['id'] : 0 );
		} );
		return array_map( static function ( $i ) {
			return (int) $i['id'];
		}, $items );
	}

	/**
	 * Les ids des catégories cochées « Afficher sur l'accueil » (9.1), dans l'ordre.
	 * Vide si aucune n'est cochée → l'accueil retombe sur le repli non-régressif.
	 *
	 * @return int[]
	 */
	public static function home_category_ids() {
		// Filtrage en PHP, PAS en `meta_query` : `get_terms( meta_key/meta_value )` ne
		// remonte pas la term-meta sur cette version WP (vérifié). `get_terms` amorce le
		// cache de term-meta (update_term_meta_cache), donc les `get_term_meta` ci-dessous
		// sont en mémoire — pas N requêtes. Le nombre de catégories est petit.
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$items = array();
		foreach ( $terms as $t ) {
			if ( '1' !== (string) get_term_meta( $t->term_id, '_aod_cat_home', true ) ) {
				continue;
			}
			$items[] = array(
				'id'    => (int) $t->term_id,
				'order' => (int) get_term_meta( $t->term_id, '_aod_cat_home_order', true ),
				// Départage sur le SLUG, pas sur `$t->name` : ce dernier a déjà traversé
				// le filtre `get_term` (7.5), donc il est ARABE site en AR — trier dessus
				// inverserait l'ordre des ex æquo entre FR et AR. Le slug est dérivé du FR,
				// latin, invariant par langue → ordre stable quelle que soit la locale.
				'name'  => (string) $t->slug,
			);
		}
		return self::sort_home_category_ids( $items );
	}

	/**
	 * Le HTML des catégories de l'accueil (9.2).
	 *
	 * Sélection « à la une » du gérant si elle existe (ordre respecté via
	 * `orderby="include"`, catégories vides comprises via `hide_empty="0"` — c'est
	 * le besoin : une catégorie neuve sans produit doit pouvoir apparaître), sinon
	 * **repli non-régressif** sur toutes les catégories non vides. Le **plafond de 6
	 * est retiré** dans les deux cas. Les noms restent traduits en AR par le filtre
	 * `get_term` (7.5), que `[product_categories]` appelle pour chaque terme — donc
	 * rien à ajouter côté i18n.
	 *
	 * @return string
	 */
	public static function home_categories_markup() {
		return do_shortcode( self::home_categories_shortcode( self::home_category_ids() ) );
	}

	/**
	 * Choisit le shortcode « Nos catégories » à partir d'une sélection.
	 *
	 * **Fonction pure** (aucune lecture de base), donc testable sans fixtures — même
	 * patron que `sort_home_category_ids()` ou `AOD_COD_Pixels::to_reporting()`.
	 *
	 * Deux modes, et c'est toute la logique de la Phase 9 :
	 *  · **sélection** — le gérant a coché des catégories : on rend EXACTEMENT
	 *    celles-là, dans son ordre (`orderby="include"`), **vides comprises**
	 *    (`hide_empty="0"` — une catégorie neuve sans produit doit pouvoir être mise
	 *    en avant). Sélection = remplacement, jamais addition ;
	 *  · **repli** — aucune sélection : comportement d'avant la Phase 9, toutes les
	 *    catégories non vides (`hide_empty="1"`), sans plafond.
	 *
	 * ⚠️ Pourquoi cette extraction : la bascule était vérifiée par la recette
	 * navigateur, qui exigeait pour cela un état GLOBAL (« aucune catégorie du site
	 * n'est cochée »). Or la graine de recette est volontairement **additive** — elle
	 * ne touche jamais aux objets du clone — donc structurellement incapable de
	 * produire cet état : sur une boutique où le gérant a coché ses propres
	 * catégories, la porte rougissait sans qu'il y ait le moindre défaut. Neutraliser
	 * les catégories réelles aurait été destructif. La bascule est donc prouvée ici,
	 * en unitaire, sans base et sans risque (cf. `tests/unit/test-home-categories.php`).
	 *
	 * @param int[] $ids Catégories sélectionnées, déjà ordonnées.
	 * @return string Shortcode à exécuter.
	 */
	public static function home_categories_shortcode( $ids ) {
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( ! empty( $ids ) ) {
			return sprintf(
				'[product_categories ids="%s" orderby="include" columns="3" hide_empty="0"]',
				esc_attr( implode( ',', $ids ) )
			);
		}
		return '[product_categories columns="3" hide_empty="1"]';
	}

	/**
	 * Shortcode [aod_home] — la page d'accueil.
	 *
	 * Vitrine : héro, catégories, produits, réassurance, appel à la Boutique. Aucune
	 * couleur ni image en dur : la page hérite entièrement du thème du clone.
	 *
	 * @return string
	 */
	public function render_home() {
		$shop_url = wc_get_page_permalink( 'shop' );
		if ( ! $shop_url ) {
			$shop_url = home_url( '/' );
		}

		ob_start();
		?>
		<div class="aod-home">

			<?php // ── Héro : nom + slogan du Personnalisateur. Jamais codés en dur. ── ?>
			<section class="aod-home__hero">
				<h1 class="aod-home__title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
				<?php $tagline = get_bloginfo( 'description' ); ?>
				<?php if ( $tagline ) : ?>
					<p class="aod-home__tagline"><?php echo esc_html( $tagline ); ?></p>
				<?php endif; ?>
				<p class="aod-home__cta">
					<a class="aod-home__btn" href="<?php echo esc_url( $shop_url ); ?>">
						<?php esc_html_e( 'Découvrir la boutique', 'aod-cod-form' ); ?>
					</a>
				</p>
			</section>

			<?php // ── Catégories (9.2). Sélection « à la une » du gérant si elle existe, sinon repli sur toutes les non vides ; plafond de 6 retiré. Masquées d'elles-mêmes si rien à afficher. ── ?>
			<?php $cats = self::home_categories_markup(); ?>
			<?php if ( trim( wp_strip_all_tags( $cats ) ) !== '' ) : ?>
				<section class="aod-home__section">
					<h2 class="aod-home__h2"><?php esc_html_e( 'Nos catégories', 'aod-cod-form' ); ?></h2>
					<?php echo $cats; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sortie WooCommerce. ?>
				</section>
			<?php endif; ?>

			<?php // ── Produits récents. ── ?>
			<section class="aod-home__section">
				<h2 class="aod-home__h2"><?php esc_html_e( 'Nos produits', 'aod-cod-form' ); ?></h2>
				<?php echo do_shortcode( '[products limit="8" columns="4" orderby="date" order="DESC"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p class="aod-home__cta">
					<a class="aod-home__btn" href="<?php echo esc_url( $shop_url ); ?>">
						<?php esc_html_e( 'Voir tous les produits', 'aod-cod-form' ); ?>
					</a>
				</p>
			</section>

			<?php // ── Réassurance : les 3 promesses qui font acheter en COD. ── ?>
			<section class="aod-home__reassure">
				<div class="aod-home__promise">
					<span class="aod-home__ico" aria-hidden="true"><?php echo AOD_COD_Icon::svg( 'money', array( 'size' => 30 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue FERMÉ (AOD_COD_Icon), jamais une saisie. ?></span>
					<h3><?php esc_html_e( 'Paiement à la livraison', 'aod-cod-form' ); ?></h3>
					<p><?php esc_html_e( 'Vous payez en espèces à la réception. Aucun versement à l’avance, aucune carte bancaire.', 'aod-cod-form' ); ?></p>
				</div>
				<div class="aod-home__promise">
					<span class="aod-home__ico" aria-hidden="true"><?php echo AOD_COD_Icon::svg( 'truck', array( 'size' => 30 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue FERMÉ (AOD_COD_Icon), jamais une saisie. ?></span>
					<h3><?php esc_html_e( 'Livraison dans les 58 wilayas', 'aod-cod-form' ); ?></h3>
					<p><?php esc_html_e( 'À domicile ou en point de retrait, partout en Algérie.', 'aod-cod-form' ); ?></p>
				</div>
				<div class="aod-home__promise">
					<span class="aod-home__ico" aria-hidden="true"><?php echo AOD_COD_Icon::svg( 'phone', array( 'size' => 30 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue FERMÉ (AOD_COD_Icon), jamais une saisie. ?></span>
					<h3><?php esc_html_e( 'Commande en 1 minute', 'aod-cod-form' ); ?></h3>
					<p><?php esc_html_e( 'Ni panier ni création de compte : votre nom, votre téléphone, et c’est commandé.', 'aod-cod-form' ); ?></p>
				</div>
			</section>

		</div>
		<?php
		return ob_get_clean();
	}
}
