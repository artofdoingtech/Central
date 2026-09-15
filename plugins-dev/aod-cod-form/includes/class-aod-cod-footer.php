<?php
/**
 * Pied de page complet : marque (logo/tagline/réseaux sociaux), navigation,
 * coordonnées, et bandeau de copyright — retour owner (2026-09-15/16).
 *
 * Premier jet (2026-09-15) : une simple barre en `wp_footer`, sous le pied
 * de page par défaut d'Astra. Rejeté par l'owner sur deux points : le STYLE
 * (« pas du tout ce que je veux ») et la POSITION (« le copyright doit être
 * tout en bas, le reste au-dessus, ça doit ressembler exactement au pied de
 * page de BELLISSIMA-BIO »). Ce fichier reprend le PATRON déjà éprouvé sur
 * ce clone client (`bellissima-bio-child/includes/site-footer.php`, même
 * méthodologie de template) — généralisé ici pour tout clone : aucune donnée
 * client en dur, tout vient du Personnalisateur, de WooCommerce ou de
 * `aod_cod_social_links` (réglé dans /gestion → Mon compte).
 *
 * Hook `astra_footer_before` — se déclenche juste avant `astra_footer()`
 * (le pied de page PAR DÉFAUT d'Astra, une simple ligne « Copyright ©
 * {année} {site} | Propulsé par Thème WordPress Astra »), donc CE bloc-ci
 * apparaît au-dessus de lui dans le DOM. Le défaut d'Astra est ensuite
 * masqué en CSS (`.site-below-footer-wrap`, aod-cod-pages.css) — remplacé
 * entièrement, jamais empilé avec.
 * ⚠️ Spécifique à Astra (thème par défaut du template, TEMPLATE.md §4) :
 * repli sur `wp_footer` si un AUTRE thème classique est actif — la position
 * n'est alors plus garantie « au-dessus du footer natif » (ce thème n'a pas
 * forcément d'équivalent à `astra_footer_before`), mais le contenu reste
 * visible plutôt que de disparaître.
 *
 * Site-wide (pas restreint aux 3 pages publiques) : le pied de page est du
 * chrome de site, visible sur la fiche produit et une éventuelle page de
 * vente au même titre qu'ailleurs.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Footer {

	/** @var AOD_COD_Footer|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Le pied de page natif d'Astra est masqué inconditionnellement en CSS
		// (`.site-below-footer-wrap`, aod-cod-pages.css — chargé sans condition
		// sur tout le public) : rien à enregistrer ici pour ça.
		$hook = ( 'astra' === get_template() ) ? 'astra_footer_before' : 'wp_footer';
		add_action( $hook, array( $this, 'render' ) );
	}

	/**
	 * Réseaux sociaux + contacts publics. Même forme que
	 * `AOD_CD_Section_Account::social_links()` côté dashboard — les deux lisent
	 * la MÊME option, indépendamment (aucun appel cross-plugin, cf. la garde de
	 * dépendance déjà en tête de `class-aod-cod-icon.php`).
	 *
	 * @return array{facebook:string,instagram:string,tiktok:string,snapchat:string,whatsapp:string,phone:string,maps:string}
	 */
	protected function social_links() {
		$saved = get_option( 'aod_cod_social_links', array() );
		return wp_parse_args( (array) $saved, array(
			'facebook'  => '',
			'instagram' => '',
			'tiktok'    => '',
			'snapchat'  => '',
			'whatsapp'  => '',
			'phone'     => '',
			'maps'      => '',
		) );
	}

	/**
	 * Le menu de l'emplacement `primary`, récupéré PAR SON ID plutôt que via
	 * `'theme_location' => 'primary'`.
	 *
	 * 🚨 Pourquoi ce détour, et pas la façon habituelle : `AOD_COD_Lang::add_to_menu()`
	 * ajoute le switcher FR/AR à TOUT `wp_nav_menu()` dont `$args->theme_location`
	 * vaut `primary`/`secondary_menu`/`mobile_menu`, sans exception de nombre
	 * d'appels. Un second appel avec `theme_location => primary` ici aurait
	 * donc fait apparaître un SECOND switcher FR/AR dans le pied de page —
	 * défaut déjà rencontré et corrigé sur un clone client (même patron
	 * reprographié ici). Passer `menu` (l'ID) sert le MÊME menu — assigné à
	 * `primary` par `pages-publiques.sh` — et garde la traduction des
	 * libellés (`AOD_COD_Pages::translate_menu_labels()` filtre
	 * `wp_nav_menu_objects` sans condition de location), sans déclencher ce
	 * hook précis.
	 *
	 * @return int 0 si aucun menu n'est assigné à `primary`.
	 */
	protected function primary_menu_id() {
		$locations = get_nav_menu_locations();
		return isset( $locations['primary'] ) ? (int) $locations['primary'] : 0;
	}

	public function render() {
		$s          = $this->social_links();
		$store      = class_exists( 'AOD_COD_Contact' ) ? AOD_COD_Contact::store_details() : array(
			'address' => '',
			'email'   => '',
		);
		$menu_id    = $this->primary_menu_id();
		$year       = gmdate( 'Y' );
		$has_social = ( '' !== $s['facebook'] || '' !== $s['instagram'] || '' !== $s['tiktok'] || '' !== $s['snapchat'] || '' !== $s['whatsapp'] );
		$has_contact = ( '' !== $s['phone'] || '' !== $store['address'] || '' !== $store['email'] || '' !== $s['maps'] );

		// Réseau => [ URL prête à l'emploi, nom lisible (aria-label — nom de
		// marque, jamais traduit, cf. TEMPLATE.md §7). ]
		$networks = array(
			'facebook'  => array( $s['facebook'], __( 'Facebook', 'aod-cod-form' ) ),
			'instagram' => array( $s['instagram'], __( 'Instagram', 'aod-cod-form' ) ),
			'tiktok'    => array( $s['tiktok'], __( 'TikTok', 'aod-cod-form' ) ),
			'snapchat'  => array( $s['snapchat'], __( 'Snapchat', 'aod-cod-form' ) ),
			// Numéro → lien wa.me : seuls les chiffres comptent.
			'whatsapp'  => array(
				'' !== $s['whatsapp'] ? 'https://wa.me/' . preg_replace( '/\D+/', '', $s['whatsapp'] ) : '',
				__( 'WhatsApp', 'aod-cod-form' ),
			),
		);
		?>
		<footer class="aod-footer">
			<div class="aod-footer__top">
				<div class="aod-footer__col aod-footer__brand">
					<?php if ( has_custom_logo() ) : ?>
						<?php the_custom_logo(); ?>
					<?php else : ?>
						<span class="aod-footer__sitename"><?php bloginfo( 'name' ); ?></span>
					<?php endif; ?>
					<?php $tagline = get_bloginfo( 'description' ); ?>
					<?php if ( $tagline ) : ?>
						<p class="aod-footer__tagline"><?php echo esc_html( $tagline ); ?></p>
					<?php endif; ?>
					<?php if ( $has_social ) : ?>
						<div class="aod-footer__social">
							<?php foreach ( $networks as $slug => $n ) :
								if ( '' === $n[0] ) {
									continue;
								}
								?>
								<a class="aod-footer__social-link" href="<?php echo esc_url( $n[0] ); ?>" target="_blank" rel="noopener noreferrer nofollow" aria-label="<?php echo esc_attr( $n[1] ); ?>">
									<?php echo AOD_COD_Icon::svg( $slug, array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue FERMÉ (AOD_COD_Icon), jamais une saisie. ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $menu_id ) : ?>
					<div class="aod-footer__col">
						<h3 class="aod-footer__heading"><?php esc_html_e( 'Navigation', 'aod-cod-form' ); ?></h3>
						<nav class="aod-footer__nav" aria-label="<?php esc_attr_e( 'Navigation du pied de page', 'aod-cod-form' ); ?>">
							<?php
							wp_nav_menu( array(
								'menu'        => $menu_id,
								'container'   => false,
								'menu_class'  => 'aod-footer__links',
								'fallback_cb' => false,
							) );
							?>
						</nav>
					</div>
				<?php endif; ?>

				<?php if ( $has_contact ) : ?>
					<div class="aod-footer__col">
						<h3 class="aod-footer__heading"><?php esc_html_e( 'Coordonnées', 'aod-cod-form' ); ?></h3>
						<ul class="aod-footer__contact">
							<?php if ( '' !== $s['phone'] ) : ?>
								<li>
									<span class="aod-footer__ico" aria-hidden="true"><?php echo AOD_COD_Icon::svg( 'phone', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue FERMÉ (AOD_COD_Icon), jamais une saisie. ?></span>
									<a href="tel:<?php echo esc_attr( $s['phone'] ); ?>"><?php echo esc_html( $s['phone'] ); ?></a>
								</li>
							<?php endif; ?>
							<?php if ( '' !== $store['address'] ) : ?>
								<li>
									<span class="aod-footer__ico" aria-hidden="true"><?php echo AOD_COD_Icon::svg( 'pin', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue FERMÉ (AOD_COD_Icon), jamais une saisie. ?></span>
									<span><?php echo esc_html( $store['address'] ); ?></span>
								</li>
							<?php endif; ?>
							<?php if ( '' !== $store['email'] ) : ?>
								<li>
									<span class="aod-footer__ico" aria-hidden="true"><?php echo AOD_COD_Icon::svg( 'email', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue FERMÉ (AOD_COD_Icon), jamais une saisie. ?></span>
									<a href="mailto:<?php echo esc_attr( $store['email'] ); ?>"><?php echo esc_html( $store['email'] ); ?></a>
								</li>
							<?php endif; ?>
							<?php if ( '' !== $s['maps'] ) : ?>
								<li>
									<span class="aod-footer__ico" aria-hidden="true"><?php echo AOD_COD_Icon::svg( 'maps', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue FERMÉ (AOD_COD_Icon), jamais une saisie. ?></span>
									<a href="<?php echo esc_url( $s['maps'] ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php esc_html_e( 'Voir sur la carte', 'aod-cod-form' ); ?></a>
								</li>
							<?php endif; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
			<div class="aod-footer__bottom">
				<p class="aod-footer__credit">
					<?php
					// Texte de copyright : chrome de PLUGIN comme le reste de ce fichier
					// (identique sur tous les clones, __()/.po) — SEUL « ART OF DOING »,
					// juste après, reste non traduit : chaîne de marque (TEMPLATE.md §7).
					printf(
						/* translators: %s = année en cours. */
						esc_html__( 'Copyright © %s — Développé par', 'aod-cod-form' ),
						esc_html( $year )
					);
					?>
					<a href="https://artofdoing.net/" target="_blank" rel="noopener noreferrer nofollow">ART OF DOING</a>
				</p>
			</div>
		</footer>
		<?php
	}
}
