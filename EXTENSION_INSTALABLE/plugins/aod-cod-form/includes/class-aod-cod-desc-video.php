<?php
/**
 * Vidéo dans la description produit (Phase 8, étape 8.3).
 *
 * Deux sources, deux mécanismes OPPOSÉS — parce que `wp_kses_post()` les traite en
 * opposés :
 *
 *   • YouTube — kses SUPPRIME les <iframe>. On ne peut donc pas stocker de lecteur.
 *     Le gérant colle un lien ; l'éditeur insère un LIEN MARQUÉ
 *     `<a class="aod-desc-video" href="…">` (kses le conserve). C'est CETTE classe,
 *     au RENDU, que `render_facades()` reconnaît et convertit en FAÇADE cliquable :
 *     0 iframe et 0 requête YouTube tant que le visiteur n'a pas cliqué — l'iframe
 *     `youtube-nocookie.com` n'est injectée qu'au clic, par `aod-landing.js`
 *     (exactement le pattern de la page de vente, 6.3, dont on réutilise le JS, le
 *     CSS et les classes `aod-landing__video-*`).
 *
 *   • Fichier local — kses CONSERVE `<video src>`. Le gérant téléverse un MP4 (même
 *     upload immédiat que la photo, 8.2) ; l'éditeur insère un `<video src>` natif,
 *     lu par le navigateur SANS iframe et SANS JS. Aucune conversion au rendu : le
 *     `<video controls preload="none">` se rend seul. La seule chose qu'il lui faut
 *     est une largeur bornée (sinon il déborde à 390 px) → la règle CSS
 *     `.aod-desc-video-file` d'`aod-landing.css`, chargée au même endroit que la
 *     façade.
 *
 * Frontière serveur : INCHANGÉE. On n'autorise pas l'iframe (kses reste tel quel) ;
 * la sortie de `render_facades()` est du markup de confiance construit ICI, où l'ID
 * est re-dérivé du `href` et re-validé (11 caractères YouTube) par
 * `AOD_COD_Landing::youtube_id()` — jamais concaténé depuis le texte saisi.
 *
 * @package AOD_COD_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AOD_COD_Desc_Video
 */
class AOD_COD_Desc_Video {

	/**
	 * @var AOD_COD_Desc_Video|null
	 */
	private static $instance = null;

	/**
	 * @return AOD_COD_Desc_Video
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Priorité 20 — APRÈS le swap AR (9), `wpautop` (10) et
		// `wp_filter_content_tags` (12) : on voit le contenu FINAL, dans la bonne
		// langue, et il ne reste qu'à remplacer le lien marqué par la façade.
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/* ─────────────────────────────────────────────────────── Rendu façade ─── */

	/**
	 * Le hook `the_content` — mais uniquement sur une fiche PRODUIT.
	 *
	 * `the_content` ne reçoit pas d'ID : on lit le post courant, exactement comme le
	 * swap i18n (class-aod-cod-catalog-filters.php). Sans cette garde, on convertirait
	 * des liens sur n'importe quelle page (Accueil, Contact…).
	 *
	 * @param string $content
	 * @return string
	 */
	public static function filter_content( $content ) {
		// Même garde que maybe_enqueue() (plus bas) : on ne convertit un lien marqué en
		// façade QUE là où ses assets (aod-landing.css/.js) sont chargés — la fiche
		// produit rendue par WooCommerce. Rendue ailleurs (une description sortie via un
		// shortcode sur une autre page), la façade n'aurait ni style ni JS : un ▶ mort.
		// Le lien marqué y reste alors un <a> cliquable inoffensif — dégradation
		// gracieuse, pas bouton mort. (Divergence de portée signalée par la revue 8.3.)
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return $content;
		}
		// Une landing REMPLACE le rendu de la fiche et gère ses propres vidéos (6.3) :
		// maybe_enqueue s'y abstient → filter_content aussi, sinon façade sans assets.
		if ( AOD_COD_Landing::instance()->target_product() ) {
			return $content;
		}
		return self::render_facades( $content );
	}

	/**
	 * Convertit chaque lien marqué YouTube en façade. Pur et testable.
	 *
	 * Absorption du `<p>` que `wpautop` a posé autour du lien : elle est BALANCÉE.
	 * Le `<p>` ouvrant est capturé (groupe 1) et le `</p>` fermant n'est retiré QUE si
	 * ce `<p>` l'a été (conditionnel `(?(1)…)`). Ainsi :
	 *   • lien seul sur sa ligne (`<p><a…></a></p>`) → paragraphe entier absorbé ;
	 *   • lien en MILIEU de paragraphe (`<p>Texte <a…></a></p>`) → le `<p>` ne peut
	 *     être absorbé (du texte précède) → le `</p>` est LAISSÉ : HTML balancé.
	 * (Sans le conditionnel, le `</p>` seul était mangé → paragraphe orphelin qui
	 * aspirait la suite — bug signalé par la revue 8.3.)
	 *
	 * @param string $html
	 * @return string
	 */
	public static function render_facades( $html ) {
		$html = (string) $html;
		// Court-circuit : rien à convertir sans lien marqué. `aod-desc-video` est un
		// sous-ensemble de `aod-desc-video-file` (la vidéo native, qui n'a pas de
		// façade) → on retire d'abord ce marqueur-là avant de tester, sinon une
		// description à vidéo-fichier SEULE lancerait la regex d'ancre pour rien.
		if ( false === strpos( str_replace( 'aod-desc-video-file', '', $html ), 'aod-desc-video' ) ) {
			return $html;
		}
		return preg_replace_callback(
			'~(<p>\s*)?<a\b[^>]*\bclass\s*=\s*(["\'])(?:[^"\']*\s)?aod-desc-video(?:\s[^"\']*)?\2[^>]*>.*?</a>(?(1)\s*</p>)~is',
			array( __CLASS__, 'replace_anchor' ),
			$html
		);
	}

	/**
	 * Callback : un lien marqué → façade si le href est une vraie vidéo YouTube,
	 * sinon on rend le lien INTACT (lien cliquable inoffensif, jamais de lecteur mort).
	 *
	 * @param array $m
	 * @return string
	 */
	protected static function replace_anchor( $m ) {
		$anchor = $m[0];
		if ( ! preg_match( '~\bhref\s*=\s*(["\'])(.*?)\1~is', $anchor, $h ) ) {
			return $anchor;
		}
		// Le href a pu être entité-encodé par kses/wpautop (`&` → `&#038;`). On décode
		// avant d'en extraire l'ID, sinon un lien avec `&list=…` ne serait pas reconnu.
		$id = AOD_COD_Landing::youtube_id( html_entity_decode( $h[2], ENT_QUOTES ) );
		if ( '' === $id ) {
			return $anchor;
		}
		return self::facade_html( $id );
	}

	/**
	 * Le markup de la façade — réutilise les classes `aod-landing__video-*` pour que
	 * `aod-landing.css` (style) ET `aod-landing.js` (`bindVideo`→`play`) fonctionnent
	 * tels quels. Sans couverture (le fond teinté + le disque ▶ suffisent).
	 *
	 * @param string $id Identifiant YouTube déjà validé (11 caractères).
	 * @return string
	 */
	protected static function facade_html( $id ) {
		$watch = 'https://www.youtube.com/watch?v=' . $id;
		return '<span class="aod-landing__video-wrap">'
			. '<button type="button" class="aod-landing__video-facade" data-yt="' . esc_attr( $id ) . '" data-title="' . esc_attr__( 'Vidéo', 'aod-cod-form' ) . '">'
			. '<span class="aod-landing__video-play" aria-hidden="true">&#9654;</span>'
			. '<span class="screen-reader-text">' . esc_html__( 'Lire la vidéo', 'aod-cod-form' ) . '</span>'
			. '</button></span>'
			. '<noscript><a href="' . esc_url( $watch ) . '" target="_blank" rel="noopener nofollow">' . esc_html__( 'Voir la vidéo sur YouTube', 'aod-cod-form' ) . '</a></noscript>';
	}

	/* ──────────────────────────────────────────────── Détection (enqueue) ─── */

	/**
	 * Reste-t-il un lien marqué YouTube CONVERTIBLE ? (pilote l'enqueue du JS.)
	 *
	 * @param string $html
	 * @return bool
	 */
	public static function has_video( $html ) {
		// Même resserrage que render_facades : `aod-desc-video-file` (vidéo native) ne
		// compte pas comme lien YouTube convertible → on l'ôte avant de tester.
		if ( false === strpos( str_replace( 'aod-desc-video-file', '', (string) $html ), 'aod-desc-video' ) ) {
			return false;
		}
		if ( ! preg_match_all( '~<a\b[^>]*\bclass\s*=\s*(["\'])(?:[^"\']*\s)?aod-desc-video(?:\s[^"\']*)?\1[^>]*>~is', $html, $mm ) ) {
			return false;
		}
		foreach ( $mm[0] as $tag ) {
			if ( preg_match( '~\bhref\s*=\s*(["\'])(.*?)\1~is', $tag, $h )
				&& '' !== AOD_COD_Landing::youtube_id( html_entity_decode( $h[2], ENT_QUOTES ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Y a-t-il une vidéo-FICHIER `<video class="aod-desc-video-file">` ? (pilote
	 * l'enqueue de la CSS seule — le fichier natif n'a pas besoin de JS.)
	 *
	 * @param string $html
	 * @return bool
	 */
	public static function has_video_file( $html ) {
		return (bool) preg_match(
			'~<video\b[^>]*\bclass\s*=\s*(["\'])(?:[^"\']*\s)?aod-desc-video-file(?:\s[^"\']*)?\1~is',
			(string) $html
		);
	}

	/* ────────────────────────────────────────────────────────── Enqueue ─── */

	/**
	 * Charge les assets `aod-landing` — UNIQUEMENT sur une fiche produit qui n'est PAS
	 * une landing (celle-ci charge déjà les siens) et dont la description EFFECTIVE
	 * (celle qui sera réellement affichée, FR ou AR) contient une vidéo :
	 *
	 *   • lien YouTube  → CSS (façade) + JS (injection de l'iframe au clic) ;
	 *   • <video> fichier → CSS seule (largeur bornée ; lecture native, aucun JS).
	 *
	 * Un produit SANS vidéo ne charge rien : la fiche reste identique à l'avant-étape.
	 */
	public function maybe_enqueue() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		// Une landing gère déjà ses assets ; ne pas doubler ni interférer.
		if ( AOD_COD_Landing::instance()->target_product() ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// La description telle qu'elle s'affichera : AR si le site est en arabe ET la
		// méta AR est renseignée, sinon le FR. On ne sniffe QUE celle-là, pour ne rien
		// charger sur un produit dont la vidéo est dans l'autre langue, invisible ici.
		$content = (string) $post->post_content;
		if ( AOD_COD_Catalog_I18n::active() ) {
			$ar = get_post_meta( $post->ID, AOD_COD_Catalog_Filters::META_DESC, true );
			if ( '' !== trim( (string) $ar ) ) {
				$content = (string) $ar;
			}
		}

		$has_yt   = self::has_video( $content );
		$has_file = self::has_video_file( $content );
		if ( ! $has_yt && ! $has_file ) {
			return;
		}

		$css = AOD_COD_PATH . 'assets/css/aod-landing.css';
		wp_enqueue_style(
			'aod-landing',
			AOD_COD_URL . 'assets/css/aod-landing.css',
			array(),
			file_exists( $css ) ? (string) filemtime( $css ) : AOD_COD_VERSION
		);

		if ( $has_yt ) {
			$js = AOD_COD_PATH . 'assets/js/aod-landing.js';
			wp_enqueue_script(
				'aod-landing',
				AOD_COD_URL . 'assets/js/aod-landing.js',
				array(),
				file_exists( $js ) ? (string) filemtime( $js ) : AOD_COD_VERSION,
				true
			);
		}
	}
}
