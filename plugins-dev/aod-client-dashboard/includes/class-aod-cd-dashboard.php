<?php
/**
 * Dashboard front-end /gestion : coque d'application autonome + routeur.
 *
 * Prend totalement la main sur le rendu de la page /gestion (n'utilise pas le
 * template du thème) pour offrir une interface marquée et plein écran. Chaque
 * section est rendue par une méthode dédiée ; les actions passent par AJAX.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Dashboard {

	/** @var AOD_CD_Dashboard|null */
	protected static $instance = null;

	/** Mémoïsation de sections() : slug => [ label, icône ]. */
	protected $sections = null;

	/**
	 * Sections du menu : slug => [ label traduit, icône ].
	 *
	 * Construit à la demande, jamais à la construction de la classe : celle-ci
	 * tourne sur `plugins_loaded`, or WordPress 6.7 interdit de traduire avant
	 * `init` (« _load_textdomain_just_in_time was called incorrectly »). Le
	 * premier appel a lieu au rendu, donc bien après `init`.
	 *
	 * « home » en tête : la section par défaut de maybe_render() est la première
	 * section visible — l'Accueil pour le gérant et pour tout confirmateur doté
	 * d'une cap statistiques (étape 2.1).
	 *
	 * @return array<string,array>
	 */
	protected function sections() {
		if ( null === $this->sections ) {
			// Le 2ᵉ élément est un NOM D'ICÔNE du catalogue `AOD_CD_Icon`, plus un
			// emoji (Phase 15) : la police emoji du système donnait un rendu
			// différent sur chaque machine. Il se trouve qu'il vaut le slug — c'est
			// voulu mais pas garanti, d'où une colonne explicite : une section
			// pourrait un jour vouloir une icône qui ne porte pas son nom.
			// `tests/unit/test-icones.php` refuse une section sans icône.
			$this->sections = array(
				'home'       => array( __( 'Accueil', 'aod-client-dashboard' ), 'home' ),
				'orders'     => array( __( 'Commandes', 'aod-client-dashboard' ), 'orders' ),
				'abandoned'  => array( __( 'Commandes abandonnées', 'aod-client-dashboard' ), 'abandoned' ),
				'products'   => array( __( 'Produits', 'aod-client-dashboard' ), 'products' ),
				'stock'      => array( __( 'Stock', 'aod-client-dashboard' ), 'stock' ),
				'categories' => array( __( 'Catégories', 'aod-client-dashboard' ), 'categories' ),
				'shipping'   => array( __( 'Livraison', 'aod-client-dashboard' ), 'shipping' ),
				'tracking'   => array( __( 'Suivi commande', 'aod-client-dashboard' ), 'tracking' ),
				'marketing'  => array( __( 'Pixels & Tracking', 'aod-client-dashboard' ), 'marketing' ),
				'whatsapp'   => array( __( 'WhatsApp', 'aod-client-dashboard' ), 'whatsapp' ),
				'team'       => array( __( 'Équipe', 'aod-client-dashboard' ), 'team' ),
				'account'    => array( __( 'Mon compte', 'aod-client-dashboard' ), 'account' ),
			);
		}
		return $this->sections;
	}

	/**
	 * Capabilities confirmateur ouvrant chaque section (logique « ou »).
	 * Toute section absente de cette carte est réservée au gérant
	 * (manage_woocommerce). Voir AOD_CD_Roles::CONFIRMER_CAPS.
	 *
	 * @var array<string,string[]>
	 */
	protected $section_caps = array(
		'home'      => array( 'aod_cap_stats_confirm', 'aod_cap_stats_delivery' ),
		'orders'    => array( 'aod_cap_confirm_orders' ),
		'abandoned' => array( 'aod_cap_abandoned' ),
		'stock'    => array( 'aod_cap_stock' ),
		'shipping' => array( 'aod_cap_shipping_prices' ),
		'tracking' => array( 'aod_cap_tracking' ),
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );

		// Plus AUCUN hook AJAX ici : chaque section enregistre les siens, à
		// l'instanciation au boot (aod-client-dashboard.php). Décomposition
		// achevée en 30.6 — cette classe n'est plus qu'une coque (rendu du
		// document + routeur) et des composants d'UI partagés.
		// Produits & catégories : AOD_CD_Section_Products (étape 7.0).
		// Livraison : AOD_CD_Section_Shipping (30.4).
		// Suivi commande : AOD_CD_Section_Tracking (30.5).
		// Pixels & CAPI : AOD_CD_Section_Marketing (30.2).
		// WhatsApp : AOD_CD_Section_WhatsApp (30.1).
		// Mon compte + reset boutique : AOD_CD_Section_Account (30.3).
		// Commandes + cycle de vie du pack assortiment : AOD_CD_Section_Orders (30.6).
	}

	/**
	 * Enregistre la règle de réécriture /gestion (et /gestion/<section>).
	 */
	public static function add_rewrite() {
		add_rewrite_rule(
			'^' . AOD_CD_SLUG . '(?:/([^/]+))?/?$',
			'index.php?aod_cd=1&aod_cd_section=$matches[1]',
			'top'
		);
	}

	public function query_vars( $vars ) {
		$vars[] = 'aod_cd';
		$vars[] = 'aod_cd_section';
		return $vars;
	}

	/* ============================================================
	 * Rendu de la page
	 * ========================================================== */

	/**
	 * Sections visibles pour l'utilisateur courant.
	 *
	 * Le gérant (manage_woocommerce) voit tout ; un confirmateur ne voit que
	 * les sections ouvertes par ses capabilities ($section_caps, logique
	 * « ou »). Le masquage n'est qu'un confort d'UI : chaque handler AJAX
	 * revérifie la capability côté serveur.
	 *
	 * @return array<string,array> Sous-ensemble de $this->sections.
	 */
	protected function visible_sections() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return $this->sections();
		}
		$out = array();
		foreach ( $this->sections() as $slug => $meta ) {
			if ( empty( $this->section_caps[ $slug ] ) ) {
				continue; // Réservée au gérant.
			}
			foreach ( $this->section_caps[ $slug ] as $cap ) {
				if ( AOD_CD_Roles::user_can( $cap ) ) {
					$out[ $slug ] = $meta;
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Sections atteignables par URL mais JAMAIS listées dans la barre latérale.
	 *
	 * « Page de vente » appartient à un produit : on y entre depuis la liste des
	 * produits, avec un `?produit=` — une entrée de menu mènerait à un écran sans
	 * sujet. Elle reste dans `nav_groups()` (domaine « Catalogue ») pour que le fil
	 * d'ariane la situe ; la boucle du menu la saute puisqu'elle n'est pas visible.
	 *
	 * Réservées au gérant, comme la section Produits dont elles dépendent.
	 *
	 * @return array<string,array>
	 */
	protected function hidden_sections() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return array();
		}
		return array(
			'landing' => array( __( 'Page de vente', 'aod-client-dashboard' ), 'landing' ),
		);
	}

	/**
	 * Sections que l'utilisateur a le droit d'ouvrir : celles du menu + les masquées.
	 *
	 * @return array<string,array>
	 */
	protected function routable_sections() {
		return $this->visible_sections() + $this->hidden_sections();
	}

	/**
	 * Regroupement de la navigation par domaine métier (coque — étape 5.3).
	 * Chaque groupe : slug => array( libellé, array de slugs de section ordonnés ).
	 * Ordre fixe ; un groupe sans section visible (capabilities) est masqué. ⚠️ Un
	 * domaine ne porte JAMAIS le nom d'un enfant — `tests/unit/test-nav-groupes.php`.
	 *
	 * @return array
	 */
	protected function nav_groups() {
		return array(
			'pilotage'  => array( __( 'Pilotage', 'aod-client-dashboard' ),       array( 'home' ) ),
			'ventes'    => array( __( 'Ventes', 'aod-client-dashboard' ),         array( 'orders', 'abandoned' ) ),
			// « landing » n'est PAS dans sections() : elle ne s'affichera donc jamais dans
			// le menu (la boucle filtre sur les sections visibles). Sa présence ici sert
			// au seul fil d'ariane — « Gestion › Catalogue › Page de vente ».
			'catalogue' => array( __( 'Catalogue', 'aod-client-dashboard' ),      array( 'products', 'stock', 'categories', 'landing' ) ),
			'livraison' => array( __( 'Expédition', 'aod-client-dashboard' ),     array( 'shipping', 'tracking' ) ),
			'marketing' => array( __( 'Marketing', 'aod-client-dashboard' ),      array( 'marketing', 'whatsapp' ) ),
			'admin'     => array( __( 'Administration', 'aod-client-dashboard' ), array( 'team', 'account' ) ),
		);
	}

	/**
	 * Libellé du domaine (groupe) auquel appartient une section — pour le fil
	 * d'ariane. Renvoie '' si la section n'est rattachée à aucun groupe.
	 *
	 * @param string $section Slug de section.
	 * @return string
	 */
	protected function section_group_label( $section ) {
		foreach ( $this->nav_groups() as $group ) {
			if ( in_array( $section, $group[1], true ) ) {
				return $group[0];
			}
		}
		return '';
	}

	/**
	 * Intercepte /gestion : vérifie l'accès puis rend l'application.
	 */
	public function maybe_render() {
		if ( ! get_query_var( 'aod_cd' ) ) {
			return;
		}

		// Non connecté → page de connexion, retour sur /gestion ensuite.
		if ( ! is_user_logged_in() ) {
			$back = home_url( '/' . AOD_CD_SLUG . '/' );
			wp_safe_redirect( wp_login_url( $back ) );
			exit;
		}

		// Connecté mais sans la capacité → refus.
		if ( ! current_user_can( AOD_CD_CAP ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__( "Vous n'avez pas accès à l'espace de gestion.", 'aod-client-dashboard' ),
				esc_html__( 'Accès refusé', 'aod-client-dashboard' ),
				array( 'response' => 403 )
			);
		}

		// WordPress a résolu la requête AVANT nous. Sur
		// /gestion/products?action=edit&product=123, `product` est la variable de
		// requête de WooCommerce : WP cherche un produit dont le slug est « 123 »,
		// ne trouve rien et arme un 404. La fiche produit s'affichait donc bien…
		// avec un statut HTTP 404. Les caches et les proxys refusent de servir une
		// telle page, et le diagnostic devient impossible. /gestion est toujours
		// une page valide : on rétablit le statut avant d'imprimer quoi que ce soit.
		global $wp_query;
		$wp_query->is_404 = false;
		status_header( 200 );
		nocache_headers();

		// Section demandée si elle est autorisée, sinon la première autorisée
		// (l'état vide de render_app() prend le relais si aucune ne l'est).
		// « Autorisée » inclut les sections masquées du menu (Page de vente) : elles
		// s'ouvrent par URL depuis l'écran qui y mène.
		$routable = $this->routable_sections();
		$visible  = $this->visible_sections();
		$section  = sanitize_key( get_query_var( 'aod_cd_section' ) );
		if ( ! $section || ! isset( $routable[ $section ] ) ) {
			$section = $visible ? array_key_first( $visible ) : 'orders';
		}

		$this->render_app( $section );
		exit;
	}

	/**
	 * Document HTML complet de l'application.
	 *
	 * @param string $section
	 */
	protected function render_app( $section ) {
		$user      = wp_get_current_user();
		$visible   = $this->visible_sections();   // Ce qui s'affiche dans le menu.
		$routable  = $this->routable_sections();  // Ce qu'on a le droit d'ouvrir (menu + masquées).
		$base       = home_url( '/' . AOD_CD_SLUG . '/' );
		// RTL si la locale est arabe OU is_rtl(). Le test sur la locale évite de
		// dépendre du pack de langue *core* de WordPress (ar.mo, qui fixe la
		// direction du texte) : sur un clone sans réseau il n'est pas installé,
		// is_rtl() resterait faux et l'arabe s'afficherait en LTR. Même pattern
		// que la partie publique (AOD_COD_Data).
		$is_rtl     = ( 0 === strpos( get_locale(), 'ar' ) ) || ( function_exists( 'is_rtl' ) && is_rtl() );
		// Éditeur riche de la description (8.1) : TinyMCE (embarqué dans WP) n'est
		// chargé QUE sur le formulaire produit — ~365 Ko que la liste et les 10
		// autres sections n'ont pas à payer. `action` se lit dans $_GET : le
		// rewrite de /gestion ne capture que la section, pas l'action (même
		// lecture que AOD_CD_Section_Products::render()).
		$action      = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'affichage.
		$rich_editor = ( 'products' === $section && AOD_CD_Section_Products::is_form_action( $action ) );
		$mce_locale  = '';
		if ( $rich_editor ) {
			// ⚠️ _WP_Editors n'est PAS autochargée : sans ce require, fatal.
			require_once ABSPATH . WPINC . '/class-wp-editor.php';
			// La locale de l'éditeur suit le switcher FR/AR, PAS le profil
			// utilisateur. `_WP_Editors::get_mce_locale()` lit `get_user_locale()`
			// → la langue du profil wp-admin primerait sur le cookie `aod_lang`, et
			// un compte réglé en anglais verrait la barre en anglais au milieu d'une
			// page arabe. On dérive de `get_locale()` (déjà la source de $is_rtl
			// ci-dessus), en code ISO 639-1 comme le cœur.
			$mce_locale = strtolower( substr( get_locale(), 0, 2 ) );
		}
		nocache_headers();
		?>
<!DOCTYPE html>
<html <?php echo $is_rtl ? 'dir="rtl"' : ''; ?> lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( get_bloginfo( 'name' ) . ' — ' . __( 'Gestion', 'aod-client-dashboard' ) ); ?></title>
	<?php
	// Barlow est EMBARQUÉ (Phase 20) : les @font-face sont en tête de dashboard.css
	// et pointent assets/fonts/. Ne pas réintroduire de <link> vers un CDN de
	// polices — sans réseau (le cas nominal d'un clone neuf, TEMPLATE.md §3) la
	// charte retombait en silence sur la police système. tests/unit/
	// test-ressources-locales.php refuse désormais tout hôte externe.
	?>
	<link rel="stylesheet" href="<?php echo esc_url( AOD_CD_URL . 'assets/css/dashboard.css?v=' . $this->asset_ver( 'assets/css/dashboard.css' ) ); ?>">
	<?php // Accent piloté par le Personnalisateur : surcharge les repli du CSS. ?>
	<style id="aod-cd-accent"><?php echo AOD_CD_Customize::css_vars(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- valeurs hex issues de sanitize_hex_color / formatage interne ?></style>
	<script>
		window.AOD_CD = {
			ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			nonce:   <?php echo wp_json_encode( wp_create_nonce( 'aod_cd' ) ); ?>,
			base:    <?php echo wp_json_encode( $base ); ?>,
			i18nCatDelConfirm: <?php echo wp_json_encode( __( 'Supprimer cette catégorie ? Les produits concernés perdront ce classement.', 'aod-client-dashboard' ) ); ?>,
			i18nCatZero:    <?php echo wp_json_encode( __( '0 produit', 'aod-client-dashboard' ) ); ?>,
			i18nNetErr:     <?php echo wp_json_encode( __( 'Erreur réseau.', 'aod-client-dashboard' ) ); ?>,
			// Repli générique des toasts d'échec. Le chemin normal affiche le message
			// du serveur (traduit par __()) ; ce repli ne sert que si l'échec arrive
			// SANS message — 19 endroits qui, sans cette clé, affichaient « Erreur. »
			// en français même en site arabe.
			i18nErr:        <?php echo wp_json_encode( __( 'Erreur.', 'aod-client-dashboard' ) ); ?>,
			i18nDeleted:    <?php echo wp_json_encode( __( 'Supprimé', 'aod-client-dashboard' ) ); ?>,
			i18nSaved:      <?php echo wp_json_encode( __( 'Enregistré', 'aod-client-dashboard' ) ); ?>,
			i18nSent:       <?php echo wp_json_encode( __( 'Envoyé', 'aod-client-dashboard' ) ); ?>,
			i18nTesting:    <?php echo wp_json_encode( __( 'Test en cours…', 'aod-client-dashboard' ) ); ?>,
			i18nProductDelConfirm: <?php echo wp_json_encode( __( 'Supprimer « %s » ? (déplacé dans la corbeille)', 'aod-client-dashboard' ) ); ?>,
			i18nEco:        <?php echo wp_json_encode( __( 'Économie : %s', 'aod-client-dashboard' ) ); ?>,
			i18nNoDiscount: <?php echo wp_json_encode( __( 'Aucune réduction (sera ignorée)', 'aod-client-dashboard' ) ); ?>,
			<?php // 47.3 — TROIS clés sont parties avec l'invite de note (`textPromptModal`) : `i18nNoteTitle`, `i18nNotePrompt` et `i18nSave`. Les deux premières nommaient l'invite ; la troisième n'a JAMAIS servi qu'à son bouton « OK » — vérifié, plus aucun appelant (ne pas la confondre avec `i18nSaved`, le toast de confirmation, qui a 7 appelants et reste). La note du confirmateur est un champ du formulaire d'édition, rendu côté PHP : ses libellés passent par `__()` à l'impression, pas par l'objet i18n JS. `i18nCancel` reste — `section-products.js` l'utilise. ?>
			i18nCancel:     <?php echo wp_json_encode( __( 'Annuler', 'aod-client-dashboard' ) ); ?>,
			i18nShipped:    <?php echo wp_json_encode( __( 'Colis créé', 'aod-client-dashboard' ) ); ?>,
			i18nCatPlaceholder: <?php echo wp_json_encode( __( 'Sélectionner des catégories', 'aod-client-dashboard' ) ); ?>,
			i18nGalNew:     <?php echo wp_json_encode( __( 'Nouveau', 'aod-client-dashboard' ) ); ?>,
			i18nGalRemove:  <?php echo wp_json_encode( __( 'Retirer cette photo', 'aod-client-dashboard' ) ); ?>,
			i18nResetConfirm: <?php echo wp_json_encode( __( 'Dernière confirmation : supprimer définitivement TOUS les produits et TOUTES les commandes ? Cette action est irréversible.', 'aod-client-dashboard' ) ); ?>,
			i18nResetting:  <?php echo wp_json_encode( __( 'Réinitialisation en cours… ne fermez pas cette page.', 'aod-client-dashboard' ) ); ?>,
			i18nResetDone:  <?php echo wp_json_encode( __( 'Boutique réinitialisée.', 'aod-client-dashboard' ) ); ?>,
			i18nOrderBulkDelConfirm: <?php echo wp_json_encode( __( 'Supprimer définitivement %d commande(s) sélectionnée(s) ? Cette action est irréversible.', 'aod-client-dashboard' ) ); ?>,
			i18nLeadBulkDelConfirm: <?php echo wp_json_encode( __( 'Supprimer définitivement %d prospect(s) sélectionné(s) ? Cette action est irréversible.', 'aod-client-dashboard' ) ); ?>,
			<?php // 🚨 43.5 : `i18nBulkAssignSkipped` était CONSOMMÉE par section-orders.js sans avoir jamais été DÉCLARÉE — son repli FR en dur était donc le seul texte affiché, y compris en arabe, et absent du .pot. Le code PARAISSAIT internationalisé (défaut 42.5). ?>
			i18nBulkAssignSkipped: <?php echo wp_json_encode( __( '(%d déjà assignée(s), ignorée(s))', 'aod-client-dashboard' ) ); ?>,
			i18nBulkAssignOverwriteConfirm: <?php echo wp_json_encode( __( '%d commande(s) sélectionnée(s) sont DÉJÀ assignées à un confirmateur. Les lui retirer pour les réassigner ?', 'aod-client-dashboard' ) ); ?>,
			<?php // 47.1 — DÉCLARÉE en même temps que sa consommation, précisément à cause du défaut ci-dessus : un repli FR en dur s'affiche aussi en arabe, et n'apparaît dans aucun .pot. ?>
			i18nBulkCarrierSkipped: <?php echo wp_json_encode( __( '(%d ignorée(s) : déjà expédiée(s) ou hors périmètre)', 'aod-client-dashboard' ) ); ?>,
			<?php // 47.2 — l'envoi au transporteur devenu un geste : libellés de la fenêtre de confirmation et du lot. Même règle que ci-dessus, appliquée d'emblée aux 12 chaînes. `maxLotEnvoi` n'est PAS une chaîne : c'est le plafond de sélection, lu depuis le serveur pour que l'écran et le handler refusent au MÊME seuil — écrit deux fois, l'un des deux mentirait. ?>
			maxLotEnvoi:    <?php echo (int) AOD_CD_Section_Orders::MAX_LOT_ENVOI; ?>,
			i18nShipSending:      <?php echo wp_json_encode( __( 'Envoi…', 'aod-client-dashboard' ) ); ?>,
			i18nShipConfirmTitle: <?php echo wp_json_encode( __( 'Envoi au transporteur', 'aod-client-dashboard' ) ); ?>,
			i18nShipConfirmIntro: <?php echo wp_json_encode( __( '%d commande(s) vont être envoyées au transporteur.', 'aod-client-dashboard' ) ); ?>,
			i18nShipExcluded:     <?php echo wp_json_encode( __( '%d commande(s) écartée(s) : déjà expédiée(s) ou rien à envoyer.', 'aod-client-dashboard' ) ); ?>,
			i18nShipNoCarrier:    <?php echo wp_json_encode( __( 'Transporteur par défaut', 'aod-client-dashboard' ) ); ?>,
			i18nShipGo:           <?php echo wp_json_encode( __( 'Envoyer', 'aod-client-dashboard' ) ); ?>,
			i18nShipCancel:       <?php echo wp_json_encode( __( 'Annuler', 'aod-client-dashboard' ) ); ?>,
			i18nShipProgress:     <?php echo wp_json_encode( __( 'Envoi %1$d / %2$d…', 'aod-client-dashboard' ) ); ?>,
			i18nShipDone:         <?php echo wp_json_encode( __( '%1$d envoyée(s), %2$d en échec.', 'aod-client-dashboard' ) ); ?>,
			i18nShipStop:         <?php echo wp_json_encode( __( 'Arrêter', 'aod-client-dashboard' ) ); ?>,
			<?php /* translators: %d : nombre de commandes du lot qui n'ont pas été tentées après un arrêt. */ ?>
			i18nShipAbandoned:    <?php echo wp_json_encode( __( '%d commande(s) n’ont pas été tentées (lot arrêté).', 'aod-client-dashboard' ) ); ?>,
			i18nShipClose:        <?php echo wp_json_encode( __( 'Fermer', 'aod-client-dashboard' ) ); ?>,
			i18nShipTooMany:      <?php echo wp_json_encode( __( 'Sélection trop grande : %1$d commandes pour un maximum de %2$d. Réduisez la sélection.', 'aod-client-dashboard' ) ); ?>,
			i18nShipNothing:      <?php echo wp_json_encode( __( 'Aucune de ces commandes n’est à envoyer.', 'aod-client-dashboard' ) ); ?>,
			<?php // 47.3 — l'avertissement D3 : ces transporteurs (Yalidine, Yalitec, E-com Delivery) n'ont AUCUN champ de note dans leur charge utile. Se taire ferait croire au confirmateur qu'il a transmis une consigne que le livreur ne verra jamais. ?>
			i18nShipNoteLost:     <?php echo wp_json_encode( __( '%d commande(s) portent une note pour le livreur que le transporteur choisi ne recevra PAS.', 'aod-client-dashboard' ) ); ?>,
			i18nItemRemove: <?php echo wp_json_encode( __( 'Retirer', 'aod-client-dashboard' ) ); ?>,
			i18nItemQty:    <?php echo wp_json_encode( __( 'Quantité', 'aod-client-dashboard' ) ); ?>,
			i18nTrackRefreshing: <?php echo wp_json_encode( __( 'Actualisation…', 'aod-client-dashboard' ) ); ?>,
			i18nTrackLoading: <?php echo wp_json_encode( __( 'Chargement du suivi…', 'aod-client-dashboard' ) ); ?>,
			i18nTeamDelConfirm: <?php echo wp_json_encode( __( 'Supprimer ce confirmateur ? Cette action est irréversible.', 'aod-client-dashboard' ) ); ?>,
			i18nConvertConfirm: <?php echo wp_json_encode( __( 'Créer la commande pour ce prospect ? Vous la compléterez ensuite.', 'aod-client-dashboard' ) ); ?>,
			i18nTeamPassNotice: <?php echo wp_json_encode( __( 'Mot de passe généré — notez-le, il ne sera plus affiché :', 'aod-client-dashboard' ) ); ?>,
			i18nImpImporting: <?php echo wp_json_encode( __( 'Import en cours…', 'aod-client-dashboard' ) ); ?>,
			i18nImpWilaya:    <?php echo wp_json_encode( __( 'Wilaya', 'aod-client-dashboard' ) ); ?>,
			i18nImpHome:      <?php echo wp_json_encode( __( 'Domicile', 'aod-client-dashboard' ) ); ?>,
			i18nImpDesk:      <?php echo wp_json_encode( __( 'Stop-desk', 'aod-client-dashboard' ) ); ?>,
			i18nImpApply:     <?php echo wp_json_encode( __( 'Appliquer les wilayas cochées', 'aod-client-dashboard' ) ); ?>,
			i18nImpCancel:    <?php echo wp_json_encode( __( 'Annuler', 'aod-client-dashboard' ) ); ?>,
			i18nImpSelectAll: <?php echo wp_json_encode( __( 'Tout cocher / décocher', 'aod-client-dashboard' ) ); ?>,
			i18nImpNone:      <?php echo wp_json_encode( __( 'Non défini', 'aod-client-dashboard' ) ); ?>,
			i18nImpEmpty:     <?php echo wp_json_encode( __( 'Aucun tarif reçu.', 'aod-client-dashboard' ) ); ?>,
			i18nImpApplyConfirm: <?php echo wp_json_encode( __( 'Appliquer les tarifs importés aux wilayas cochées ? Les tarifs actuels seront sauvegardés (restaurables).', 'aod-client-dashboard' ) ); ?>,
			i18nImpRestoreConfirm: <?php echo wp_json_encode( __( 'Restaurer les tarifs précédents ? L’import le plus récent sera annulé.', 'aod-client-dashboard' ) ); ?>,
			i18nImpApplying:  <?php echo wp_json_encode( __( 'Application…', 'aod-client-dashboard' ) ); ?>,
			i18nImpNoCheck:   <?php echo wp_json_encode( __( 'Cochez au moins une wilaya.', 'aod-client-dashboard' ) ); ?>,
			i18nLpDelBlock:   <?php echo wp_json_encode( __( 'Supprimer ce bloc ? Son contenu sera perdu.', 'aod-client-dashboard' ) ); ?>,
			i18nLpNoImg:      <?php echo wp_json_encode( __( 'Aucune image', 'aod-client-dashboard' ) ); ?>,
			i18nLpNoVid:      <?php echo wp_json_encode( __( 'Aucun fichier', 'aod-client-dashboard' ) ); ?>,
			i18nFmtP:         <?php echo wp_json_encode( __( 'Paragraphe', 'aod-client-dashboard' ) ); ?>,
			i18nFmtH2:        <?php echo wp_json_encode( __( 'Titre', 'aod-client-dashboard' ) ); ?>,
			i18nFmtH3:        <?php echo wp_json_encode( __( 'Sous-titre', 'aod-client-dashboard' ) ); ?>,
			i18nImgBtn:       <?php echo wp_json_encode( __( 'Insérer une image', 'aod-client-dashboard' ) ); ?>,
			i18nVideoBtn:     <?php echo wp_json_encode( __( 'Insérer une vidéo', 'aod-client-dashboard' ) ); ?>,
			i18nVideoTitle:   <?php echo wp_json_encode( __( 'Ajouter une vidéo', 'aod-client-dashboard' ) ); ?>,
			i18nVideoUrl:     <?php echo wp_json_encode( __( 'Coller un lien YouTube', 'aod-client-dashboard' ) ); ?>,
			i18nVideoFile:    <?php echo wp_json_encode( __( 'Ou choisir un fichier vidéo depuis mon PC', 'aod-client-dashboard' ) ); ?>,
			i18nVideoHint:    <?php echo wp_json_encode( __( 'Le lien YouTube reste léger (rien n’est chargé avant le clic). Un fichier est hébergé sur votre site.', 'aod-client-dashboard' ) ); ?>,
			i18nVideoBad:     <?php echo wp_json_encode( __( 'Lien YouTube non reconnu.', 'aod-client-dashboard' ) ); ?>,
			<?php // 47.7 — `i18nCancel` était déclaré ICI **et** 65 lignes plus haut, dans le MÊME objet : un doublon créé par la 47.3, qui a écrit « i18nCancel reste » sans voir celui qui existait déjà. Sans effet visible (même valeur, la seconde écrasait la première), mais c'est le motif de la Phase 46 en miniature. Une seule déclaration désormais, celle du haut. ?>
			i18nInsert:       <?php echo wp_json_encode( __( 'Insérer', 'aod-client-dashboard' ) ); ?>,
			<?php // 47.7 — le libellé du bouton d'action des questions maison. Les 13 dialogues natifs empruntaient leurs boutons au NAVIGATEUR : c'était le seul endroit de /gestion où la langue du gérant n'était pas suivie. ?>
			i18nConfirm:      <?php echo wp_json_encode( __( 'Confirmer', 'aod-client-dashboard' ) ); ?>,
			<?php // 47.7 — titre GÉNÉRIQUE des questions. Les 13 messages sont déjà des phrases complètes (« Supprimer « X » ? ») : leur donner à chacun un titre propre afficherait deux fois le même texte, pour 13 chaînes de plus à traduire. ?>
			i18nConfirmTitle: <?php echo wp_json_encode( __( 'Confirmation', 'aod-client-dashboard' ) ); ?>,
			mceLocale:        <?php echo wp_json_encode( $mce_locale ); ?>,
			// 47.6 — la borne de poids, prise à SA source. Le JS n'est qu'un confort
			// (il évite un aller-retour) : la vraie barrière reste AOD_CD_Upload, qui
			// refuse même un POST forgé. Aucun nombre n'est écrit deux fois.
			maxUpload:        <?php echo (int) AOD_CD_Upload::max_bytes(); ?>,
			i18nTooBig:       <?php echo wp_json_encode( sprintf( /* translators: %s : poids maximal accepté, déjà formaté. */ __( 'Fichier trop lourd : %s au maximum.', 'aod-client-dashboard' ), size_format( AOD_CD_Upload::max_bytes() ) ) ); ?>,
			i18nLpCopyConfirm: <?php echo wp_json_encode( __( 'Remplacer les blocs actuels par ceux de l’autre produit ? Le travail en cours sur cette page sera perdu.', 'aod-client-dashboard' ) ); ?>,
			// Icônes dont le JS a besoin pour construire du balisage côté client.
			// On expose le SVG DÉJÀ RENDU par le catalogue plutôt qu'un nom : le JS
			// n'a ainsi aucun moyen de fabriquer une icône hors liste, et la source
			// de vérité reste unique (AOD_CD_Icon). Liste volontairement minimale.
			icons: <?php echo wp_json_encode( AOD_CD_Icon::js_map( array( 'empty-tray', 'close', 'video', 'calendar' ) ) ); // 'calendar' : le déclencheur du calendrier maison (widget-datepicker.js) remplace l'indicateur natif, qui ouvrait la fenêtre blanche du navigateur. Ajouté SUR CETTE LIGNE : `dashboard.php` est à son plafond (test-decomposition.php R1). ?>
		};
	</script>
</head>
<body class="aod-cd">
	<aside class="aod-cd-side">
		<div class="aod-cd-brand"><?php echo AOD_CD_Access::brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- logo échappé + SVG du catalogue fermé. Écrit sur CETTE ligne : `dashboard.php` est à son plafond (test-decomposition.php R1). ?>
			<span class="aod-cd-brand-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<button type="button" class="aod-cd-navtoggle" aria-expanded="false" aria-controls="aod-cd-nav" aria-label="<?php esc_attr_e( 'Menu', 'aod-client-dashboard' ); ?>">
				<span></span><span></span><span></span>
			</button>
		</div>
		<div class="aod-cd-side-head">
			<a class="aod-cd-view" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
				<?php echo AOD_CD_Icon::svg( 'storefront', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- catalogue fermé. ?>
				<?php esc_html_e( 'Voir la boutique', 'aod-client-dashboard' ); ?>
			</a>
		</div>
		<nav class="aod-cd-nav" id="aod-cd-nav">
			<?php foreach ( $this->nav_groups() as $group ) :
				// Sections de ce groupe réellement visibles (capabilities), dans l'ordre défini.
				$group_slugs = array_values( array_filter( $group[1], function ( $s ) use ( $visible ) {
					return isset( $visible[ $s ] );
				} ) );
				if ( empty( $group_slugs ) ) {
					continue; // Groupe sans section visible → masqué.
				}
				?>
				<div class="aod-cd-navgroup"><?php echo esc_html( $group[0] ); ?></div>
				<?php foreach ( $group_slugs as $slug ) :
					$meta   = $visible[ $slug ];
					$active = ( $slug === $section ) ? ' is-active' : '';
					?>
					<a class="aod-cd-navlink<?php echo esc_attr( $active ); ?>" href="<?php echo esc_url( $base . $slug ); ?>">
						<?php
						// L'état actif se joue sur la GRAISSE (`regular` → `fill`) et pas
						// seulement sur la couleur : un signal qui tient quel que soit
						// l'accent choisi par le client dans le Personnalisateur.
						// Sortie de AOD_CD_Icon : catalogue fermé, aucune saisie — cf. la
						// classe pour pourquoi `wp_kses_post()` ne convient pas ici.
						echo AOD_CD_Icon::svg( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG d'un catalogue fermé, jamais d'une saisie.
							$meta[1],
							array( 'weight' => ( $slug === $section ) ? 'fill' : 'regular' )
						);
						?>
						<span><?php echo esc_html( $meta[0] ); ?></span>
					</a>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</nav>
		<div class="aod-cd-side-foot">
			<a class="aod-cd-logout" href="<?php echo esc_url( wp_logout_url( $base ) ); ?>"><?php esc_html_e( 'Déconnexion', 'aod-client-dashboard' ); ?></a>
		</div>
	</aside>

	<main class="aod-cd-main">
		<header class="aod-cd-top">
			<?php
			$page_label  = isset( $routable[ $section ] ) ? $routable[ $section ][0] : __( 'Gestion', 'aod-client-dashboard' );
			$group_label = $this->section_group_label( $section );
			?>
			<?php
			// La barre du haut ne porte QUE le fil d'ariane. Chaque page affiche son
			// titre via `page_head()` : tant que le <h1> vivait ici, 8 pages sur 11
			// affichaient leur nom DEUX fois, l'un sous l'autre. Le fil d'ariane
			// reprend donc le nom de la page comme dernier maillon — sans quoi il ne
			// nommait que le domaine (« Gestion › Ventes »), et « Commandes » et
			// « Commandes abandonnées » deviendraient indiscernables.
			?>
			<div class="aod-cd-topttl">
				<nav class="aod-cd-crumb" aria-label="<?php esc_attr_e( 'Fil d’ariane', 'aod-client-dashboard' ); ?>">
					<span class="aod-cd-crumb__root"><?php esc_html_e( 'Gestion', 'aod-client-dashboard' ); ?></span>
					<?php
					// Le domaine n'est affiché que s'il APPORTE quelque chose. ⚠️ Ce test fut
					// longtemps la seule réponse au doublon « Livraison › Livraison » — posé
					// ici, jamais dans le menu. La cause vit dans `nav_groups()` : garde-fou.
					if ( $group_label && $group_label !== $page_label ) :
						?>
						<span class="aod-cd-crumb__sep" aria-hidden="true">›</span>
						<span class="aod-cd-crumb__dom"><?php echo esc_html( $group_label ); ?></span>
					<?php endif; ?>
					<span class="aod-cd-crumb__sep" aria-hidden="true">›</span>
					<span class="aod-cd-crumb__page" aria-current="page"><?php echo esc_html( $page_label ); ?></span>
				</nav>
			</div>
			<div class="aod-cd-top-right">
				<?php if ( class_exists( 'AOD_CD_Lang' ) ) { echo AOD_CD_Lang::switcher(); // phpcs:ignore WordPress.Security.EscapeOutput
				} ?>
				<?php
				// Confirmateur restreint : rappel discret de son périmètre produits
				// (jamais affiché pour le gérant — assigned_products() renvoie vide).
				$scope = AOD_CD_Roles::assigned_products();
				if ( $scope ) :
					$scope_names = array_filter( array_map( 'get_the_title', $scope ) );
					?>
					<span class="aod-cd-badge" title="<?php echo esc_attr( implode( ' · ', $scope_names ) ); ?>">
						<?php
						/* translators: %d : nombre de produits affectés au confirmateur. */
						echo esc_html( sprintf( __( 'Produits : %d', 'aod-client-dashboard' ), count( $scope ) ) );
						?>
					</span>
				<?php endif; ?>
				<div class="aod-cd-user"><?php echo esc_html( $user->display_name ); ?></div>
			</div>
		</header>
		<div class="aod-cd-content" data-section="<?php echo esc_attr( $section ); ?>"><?php // `data-section` : la coque nomme la page pour la feuille et les modules. Sans lui, une mise en scène propre à une section (le bandeau de l'Accueil, sa cascade) s'appliquerait aux onze autres, ou exigerait une classe posée à la main dans chaque render(). Écrit sur CETTE ligne : `dashboard.php` est à son plafond de lignes (test-decomposition.php R1), et du chrome ne justifie pas de le relever. ?>
			<?php if ( isset( $routable[ $section ] ) ) : ?>
				<?php $this->render_section( $section ); ?>
			<?php else : ?>
				<div class="aod-cd-card">
					<p><?php esc_html_e( 'Aucune section ne vous est attribuée pour le moment. Contactez le gérant de la boutique.', 'aod-client-dashboard' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</main>

	<div class="aod-cd-modal" id="aod-cd-modal" hidden>
		<div class="aod-cd-modal-backdrop" data-close></div>
		<div class="aod-cd-modal-box" role="dialog" aria-modal="true" aria-labelledby="aod-cd-modal-title">
			<header class="aod-cd-modal-head">
				<h2 class="aod-cd-modal-title" id="aod-cd-modal-title"></h2>
				<button type="button" class="aod-cd-modal-x" data-close aria-label="<?php esc_attr_e( 'Fermer', 'aod-client-dashboard' ); ?>">&times;</button>
			</header>
			<div class="aod-cd-modal-body"></div>
		</div>
	</div>

	<?php if ( $rich_editor ) : ?>
		<?php
		// TinyMCE « nu » : cette page n'appelle ni wp_head() ni wp_footer(), donc
		// wp_enqueue_editor() n'imprimerait jamais rien — on charge comme pour
		// dashboard.js, par balises directes. TinyMCE déduit baseURL/suffix du
		// src et charge seul plugins et skin. La traduction (packs cœur, FR/AR)
		// est imprimée inline : son addI18n + markDone évitent tout fetch de
		// langs/<code>.js (fichier qui n'existe pas). L'init vit dans
		// dashboard.js, qui doit donc venir APRÈS.
		?>
	<script src="<?php echo esc_url( includes_url( 'js/tinymce/tinymce.min.js' ) ); ?>"></script>
	<?php // Locale explicite : la clé addI18n imprimée ET le `language:` de l'init suivent le même `get_locale()` (le switcher), jamais le profil. ?>
	<script><?php echo _WP_Editors::wp_mce_translation( $mce_locale ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS généré par le cœur. ?></script>
	<?php endif; ?>
	<?php
	// Décomposition 33.x : la coque JS (dashboard.js — helpers partagés,
	// registre, ORDRE CANONIQUE d'exécution) se charge en PREMIER ; chaque
	// module section-*.js s'enregistre auprès d'elle. Scripts synchrones en
	// fin de <body> : tout est enregistré avant DOMContentLoaded, et l'ordre
	// d'EXÉCUTION vit dans ORDER (coque), pas dans l'ordre des balises.
	// TinyMCE (ci-dessus) doit rester imprimé AVANT le module products, dont
	// l'init de l'éditeur riche vit dans bindProducts.
	foreach ( $this->dashboard_scripts() as $rel ) :
		?>
	<script src="<?php echo esc_url( AOD_CD_URL . $rel . '?v=' . $this->asset_ver( $rel ) ); ?>"></script>
	<?php endforeach; ?>
</body>
</html>
		<?php
	}

	/**
	 * Aiguille vers la section demandée.
	 *
	 * @param string $section
	 */
	protected function render_section( $section ) {
		switch ( $section ) {
			case 'home':
				AOD_CD_Section_Home::instance()->render();
				break;
			case 'orders':
				AOD_CD_Section_Orders::instance()->render();
				break;
			case 'abandoned':
				AOD_CD_Section_Abandoned::instance()->render();
				break;
			case 'products':
				AOD_CD_Section_Products::instance()->render();
				break;
			case 'stock':
				AOD_CD_Section_Stock::instance()->render();
				break;
			case 'landing':
				AOD_CD_Section_Landing::instance()->render();
				break;
			case 'categories':
				AOD_CD_Section_Products::instance()->render_categories();
				break;
			case 'shipping':
				AOD_CD_Section_Shipping::instance()->render();
				break;
			case 'tracking':
				AOD_CD_Section_Tracking::instance()->render();
				break;
			case 'marketing':
				AOD_CD_Section_Marketing::instance()->render();
				break;
			case 'whatsapp':
				AOD_CD_Section_WhatsApp::instance()->render();
				break;
			case 'team':
				AOD_CD_Section_Team::instance()->render();
				break;
			case 'account':
				AOD_CD_Section_Account::instance()->render();
				break;
			default:
				$this->section_soon( $section );
		}
	}

	/* ============================================================
	 * Recherche + pagination (réutilisables)
	 * ========================================================== */

	/**
	 * Barre de recherche GET. Conserve les filtres passés en arguments cachés.
	 *
	 * @param string $base_url    URL de destination du formulaire.
	 * @param string $value       Valeur actuelle de la recherche.
	 * @param string $placeholder Texte indicatif du champ.
	 * @param array  $hidden      Couples nom => valeur à conserver (champs cachés).
	 */
	public static function render_search_bar( $base_url, $value, $placeholder, $hidden = array() ) {
		echo '<form class="aod-cd-search" method="get" action="' . esc_url( $base_url ) . '">';
		foreach ( $hidden as $k => $v ) {
			if ( '' === (string) $v ) {
				continue;
			}
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		// Le placeholder n'est PAS un intitulé : il disparaît dès la première frappe — d'où la LOUPE (2026-08-08), qui reste. 🔒 Enveloppe + icône tiennent sur CETTE ligne à dessein : R1 de `test-decomposition.php` plafonne ce fichier à 1087 lignes et il y est EXACTEMENT ; une ligne de plus et le cliquet rougit. Le pourquoi du design est dans `dashboard.css`, à `.aod-cd-searchfield`.
		echo '<span class="aod-cd-searchfield">' . AOD_CD_Icon::svg( 'search', array( 'size' => 16 ) ) . '<input type="search" name="s" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" aria-label="' . esc_attr( $placeholder ) . '"></span>';
		echo '<button type="submit" class="aod-cd-btn aod-cd-btn-sm aod-cd-btn-primary">' . esc_html__( 'Rechercher', 'aod-client-dashboard' ) . '</button>';
		if ( '' !== $value ) {
			$clear = $hidden ? add_query_arg( array_filter( $hidden, function ( $v ) { return '' !== (string) $v; } ), $base_url ) : $base_url;
			echo ' <a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $clear ) . '">' . esc_html__( 'Réinitialiser', 'aod-client-dashboard' ) . '</a>';
		}
		echo '</form>';
	}

	/**
	 * Pagination numérotée (fenêtre glissante ±2). Conserve les arguments fournis.
	 *
	 * @param string $base_url  URL de base.
	 * @param int    $paged     Page courante (>= 1).
	 * @param int    $max_pages Nombre total de pages.
	 * @param array  $args      Arguments de requête à conserver (ex. statut, s).
	 */
	public static function render_pagination( $base_url, $paged, $max_pages, $args = array() ) {
		if ( $max_pages <= 1 ) {
			return;
		}
		$keep = array_filter( $args, function ( $v ) { return '' !== (string) $v && 'all' !== $v; } );
		$link = function ( $page ) use ( $base_url, $keep ) {
			return esc_url( add_query_arg( array_merge( $keep, array( 'paged' => $page ) ), $base_url ) );
		};

		echo '<nav class="aod-cd-pagination" aria-label="' . esc_attr__( 'Pagination', 'aod-client-dashboard' ) . '">';

		if ( $paged > 1 ) {
			echo '<a class="aod-cd-page" href="' . $link( $paged - 1 ) . '" rel="prev">&larr;</a>';
		}

		// Fenêtre glissante : 1 … (p-2..p+2) … N.
		$window = 2;
		$start  = max( 1, $paged - $window );
		$end    = min( $max_pages, $paged + $window );

		if ( $start > 1 ) {
			echo '<a class="aod-cd-page" href="' . $link( 1 ) . '">1</a>';
			if ( $start > 2 ) {
				echo '<span class="aod-cd-page-gap">…</span>';
			}
		}
		for ( $i = $start; $i <= $end; $i++ ) {
			$cls = ( $i === $paged ) ? ' is-active' : '';
			echo '<a class="aod-cd-page' . esc_attr( $cls ) . '" href="' . $link( $i ) . '">' . (int) $i . '</a>';
		}
		if ( $end < $max_pages ) {
			if ( $end < $max_pages - 1 ) {
				echo '<span class="aod-cd-page-gap">…</span>';
			}
			echo '<a class="aod-cd-page" href="' . $link( $max_pages ) . '">' . (int) $max_pages . '</a>';
		}

		if ( $paged < $max_pages ) {
			echo '<a class="aod-cd-page" href="' . $link( $paged + 1 ) . '" rel="next">&rarr;</a>';
		}
		echo '</nav>';
	}

	/* ============================================================
	 * Filtre par date (réutilisable, Phase 43.1)
	 *
	 * Extrait du patron déjà éprouvé sur l'Accueil
	 * (AOD_CD_Section_Home::read_filters()/resolve_range()) — 3ᵉ occurrence
	 * dans la même story (Commandes, Suivi, Abandonnées) : règle de trois du
	 * dépôt, on factorise au lieu de dupliquer une closure de validation de
	 * date une troisième fois.
	 *
	 * ⚠️ Volontairement PLUS SIMPLE que le patron de Home : pas de raccourcis
	 * jour/semaine, pas de défaut « 30 derniers jours ». Ici, l'absence de
	 * borne doit rendre TOUT (comportement actuel des 3 pages), jamais une
	 * fenêtre implicite qui surprendrait un gérant qui n'a jamais touché le
	 * filtre. `AOD_CD_Section_Home` garde ses raccourcis et son défaut,
	 * posés PAR-DESSUS le résultat de ces deux helpers.
	 * ========================================================== */

	/**
	 * Lit et valide un paramètre de date depuis `$_GET` (format `Y-m-d`
	 * strict, calendaire réel).
	 *
	 * Reflet exact de la closure `$date` de
	 * `AOD_CD_Section_Home::read_filters()` — ne pas diverger des deux
	 * implémentations, sinon Home et les 3 nouvelles pages accepteraient des
	 * formats différents pour le même champ `<input type="date">`.
	 *
	 * @param string $key Nom du paramètre GET (ex. `date_from`).
	 * @return string `Y-m-d` si valide, chaîne vide sinon (absent, mal
	 *                formé, ou date calendaire impossible — ex. 2024-02-30).
	 */
	public static function parse_date_param( $key ) {
		$v = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $v;
	}

	/**
	 * Remet dans l'ordre chronologique deux bornes de date déjà validées par
	 * `parse_date_param()`, sans en synthétiser aucune.
	 *
	 * ⚠️ **Ne comble PAS une borne manquante** — contrairement à
	 * `AOD_CD_Section_Home::resolve_range()`, qui complète une borne absente
	 * avec `$today` : ce choix est une décision métier propre aux stats de
	 * l'Accueil (une plage a toujours besoin des deux bouts pour ses calculs),
	 * pas une règle générale de filtre de date. Sur Commandes/Suivi/Abandonnées,
	 * une seule borne renseignée reste **ouverte** de l'autre côté (« depuis
	 * le… » / « jusqu'au… ») — le sens le plus attendu de deux champs
	 * `<input type="date">` indépendants, et celui que `wc_get_orders()` sait
	 * exprimer nativement avec `>=`/`<=` seuls (cf. Dev Notes de la story
	 * 43.1). Home, elle, appelle ce helper **après** avoir déjà comblé ses
	 * propres bornes avec `$today` — seul le tri « si inversées » est
	 * réellement partagé entre les deux usages.
	 *
	 * @param string $date_from `Y-m-d` ou `''`.
	 * @param string $date_to   `Y-m-d` ou `''`.
	 * @return array{from:string,to:string} Les deux bornes reçues, permutées
	 *         seulement si les DEUX sont renseignées et dans le mauvais ordre.
	 */
	public static function resolve_date_range( $date_from, $date_to ) {
		if ( '' !== $date_from && '' !== $date_to && $date_from > $date_to ) {
			list( $date_from, $date_to ) = array( $date_to, $date_from );
		}
		return array( 'from' => $date_from, 'to' => $date_to );
	}

	/**
	 * Construit la valeur du paramètre `date_created` de `wc_get_orders()` à
	 * partir d'une plage résolue par `resolve_date_range()` — Commandes ET
	 * Suivi en ont chacune besoin (2ᵉ occurrence de la même conversion dans
	 * cette story) : un seul endroit choisit l'opérateur, pour ne jamais
	 * risquer qu'une des deux pages se trompe de syntaxe.
	 *
	 * Syntaxe vérifiée empiriquement sur ce clone (HPOS actif, cf. Dev Notes
	 * de la story 43.1) : plage inclusive `"{from}...{to}"` quand les deux
	 * bornes sont posées, opérateur seul (`>=`/`<=`) quand une seule l'est.
	 *
	 * @param string $from `Y-m-d` ou `''`.
	 * @param string $to   `Y-m-d` ou `''`.
	 * @return string|null Valeur prête pour `$args['date_created']`, ou
	 *                      `null` si aucune borne n'est posée — dans ce cas,
	 *                      **ne pas ajouter la clé du tout** à `$args` (une
	 *                      chaîne vide n'est pas garantie neutre).
	 */
	public static function date_created_query_arg( $from, $to ) {
		if ( '' !== $from && '' !== $to ) {
			return $from . '...' . $to;
		}
		if ( '' !== $from ) {
			return '>=' . $from;
		}
		if ( '' !== $to ) {
			return '<=' . $to;
		}
		return null;
	}

	/**
	 * Rend les deux champs `<input type="date">` communs aux barres de
	 * filtres de Commandes/Suivi/Abandonnées (3ᵉ occurrence du même balisage
	 * dans cette story) — n'imprime PAS le `<form>` englobant : chaque
	 * section garde son propre formulaire (champs cachés différents selon
	 * les filtres déjà en place sur la page), seuls les deux champs sont
	 * partagés.
	 *
	 * @param string $date_from `Y-m-d` ou `''`.
	 * @param string $date_to   `Y-m-d` ou `''`.
	 */
	public static function render_date_fields( $date_from, $date_to ) {
		echo '<div class="aod-cd-field"><label for="aod-cd-f-datefrom">' . esc_html__( 'Du', 'aod-client-dashboard' ) . '</label>';
		echo '<input type="date" id="aod-cd-f-datefrom" name="date_from" value="' . esc_attr( $date_from ) . '"></div>';
		echo '<div class="aod-cd-field"><label for="aod-cd-f-dateto">' . esc_html__( 'Au', 'aod-client-dashboard' ) . '</label>';
		echo '<input type="date" id="aod-cd-f-dateto" name="date_to" value="' . esc_attr( $date_to ) . '"></div>';
	}

	/**
	 * Scripts de /gestion, dans l'ordre de CHARGEMENT (coque d'abord).
	 *
	 * Décomposition 33.x : la coque expose `window.aodCdCore`, chaque module
	 * section-*.js s'y enregistre. TOUT fichier de `assets/js/` doit figurer
	 * ici — un module créé mais non listé serait une section morte en silence ;
	 * `tests/unit/test-decomposition-js.php` (R4) recoupe cette liste avec le
	 * contenu réel du dossier.
	 *
	 * @return string[] Chemins relatifs au dossier du plugin.
	 */
	protected function dashboard_scripts() {
		return array(
			'assets/js/dashboard.js',
			'assets/js/section-home.js',
			'assets/js/section-orders.js',
			'assets/js/section-products.js',
			'assets/js/section-landing.js',
			'assets/js/section-tracking.js',
			'assets/js/section-shipping.js',
			'assets/js/section-marketing.js',
			'assets/js/section-whatsapp.js',
			'assets/js/section-stock.js',
			'assets/js/section-team.js',
			'assets/js/section-account.js',
			'assets/js/section-abandoned.js',
			// Les modules qui ne sont PAS des sections (2026-08-06) : ils appartiennent
			// à un COMPOSANT partagé, donc à aucune page. Cf. leur en-tête.
			'assets/js/widget-datepicker.js',
			'assets/js/widget-counters.js',
		);
	}

	/**
	 * Version d'un asset pour le cache-busting : date de modif du fichier,
	 * avec repli sur la version du plugin si le fichier est introuvable.
	 *
	 * @param string $rel Chemin relatif au dossier du plugin (ex. 'assets/js/dashboard.js').
	 * @return string
	 */
	protected function asset_ver( $rel ) {
		$path = AOD_CD_PATH . $rel;
		$mt   = is_file( $path ) ? filemtime( $path ) : 0;
		return $mt ? (string) $mt : AOD_CD_VERSION;
	}

	/**
	 * En-tête de page canonique (composant unifié — Phase 5, §3.3 de la spec UX).
	 *
	 * Le patron répété qui donne l'impression « ajusté » sur toutes les pages :
	 * titre + sous-titre + zone d'actions.
	 *
	 * @param string $title   Titre de la page (déjà traduit).
	 * @param string $sub     Sous-titre explicatif (facultatif, déjà traduit).
	 * @param string $actions HTML des actions principales (facultatif, déjà sûr).
	 * @return string HTML sûr.
	 */
	public static function page_head( $title, $sub = '', $actions = '' ) {
		$out  = '<div class="aod-cd-pagehead">';
		$out .= '<div class="aod-cd-pagehead__txt">';
		$out .= '<h2 class="aod-cd-pagehead__title">' . esc_html( $title ) . '</h2>';
		if ( '' !== $sub ) {
			$out .= '<p class="aod-cd-pagehead__sub">' . esc_html( $sub ) . '</p>';
		}
		$out .= '</div>';
		if ( '' !== $actions ) {
			$out .= '<div class="aod-cd-pagehead__actions">' . $actions . '</div>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Rangée de KPI canonique (composant unifié — Phase 5, §2.3 de la spec UX).
	 *
	 * Vivait dans la section Accueil ; remontée ici en étape 5.5 pour que Stock
	 * l'emploie sans dupliquer le balisage — une seule définition, un seul rendu.
	 *
	 * 🔗 `$c[6]` rend la carte cliquable (`<a>`) sans forker le composant ; sans
	 * lui le rendu est celui d'hier à l'octet près. Le pourquoi est chez son
	 * appelant, `AOD_CD_Section_Tracking::render_tracking_pulse()`.
	 * ⚠️ Aucun modificateur de RANGÉE n'est exposé : la géométrie se lit dans le
	 * DOM (`:has()`, cf. `.aod-cd-kpis` dans `dashboard.css`), elle ne se déclare
	 * pas — un appelant qui doit s'en souvenir finit par oublier.
	 *
	 * @param array[] $cards Chaque carte : [ label, valeur HTML, icône, tonalité,
	 *                       delta HTML (facultatif, déjà sûr), modificateur
	 *                       (facultatif), URL (facultative — rend un `<a>`) ].
	 * @return string HTML sûr.
	 */
	public static function kpis( $cards ) {
		$out = '<div class="aod-cd-kpis">';
		foreach ( $cards as $c ) {
			$href  = isset( $c[6] ) && '' !== $c[6] ? (string) $c[6] : '';
			$balis = $href ? 'a' : 'div';
			$out  .= '<' . $balis . ' class="aod-cd-kpi' . ( empty( $c[5] ) ? '' : ' aod-cd-kpi--' . sanitize_html_class( $c[5] ) ) . ( $href ? ' aod-cd-kpi--link' : '' ) . '"' // $c[5] : modificateur optionnel (ex. « primary »), jamais du balisage.
				. ( $href ? ' href="' . esc_url( $href ) . '"' : '' ) . '>';
			$out .= '<div class="aod-cd-kpi__top">';
			// $c[2] est un NOM D'ICÔNE du catalogue, plus un emoji (Phase 15). Le
			// composant appelle lui-même AOD_CD_Icon : les appelants restent
			// déclaratifs et ne peuvent pas injecter de balisage.
			// La tonalité (t-red, t-green…) reste sur la PASTILLE et n'est pas
			// touchée : elle porte du SENS (rouge = annulé, ambre = échec), pas de
			// la décoration. Une icône monochrome héritant de `color` la respecte.
			$out .= '<span class="aod-cd-kpi__ic t-' . esc_attr( $c[3] ) . '" aria-hidden="true">'
				. AOD_CD_Icon::svg( $c[2], array( 'size' => 18 ) )
				. '</span>';
			$out .= '<span class="aod-cd-kpi__lab">' . esc_html( $c[0] ) . '</span>';
			$out .= '</div>';
			$out .= '<div class="aod-cd-kpi__val">' . wp_kses_post( $c[1] ) . '</div>';
			if ( isset( $c[4] ) && '' !== $c[4] ) {
				$out .= $c[4];
			}
			$out .= '</' . $balis . '>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Pastille d'état canonique (composant unifié — Phase 5, §2.3).
	 *
	 * Une seule famille de pastilles pour tout le dashboard : statut de commande,
	 * état de colis, état de stock. Les tonalités viennent des tokens, jamais
	 * d'un hexadécimal en dur — sinon l'accent du Personnalisateur ne suit pas.
	 *
	 * @param string $label Texte (déjà traduit).
	 * @param string $tone  ok|warn|bad|info|neutral.
	 * @return string HTML sûr.
	 */
	public static function pill( $label, $tone = 'neutral' ) {
		$tones = array( 'ok', 'warn', 'bad', 'info', 'neutral' );
		$tone  = in_array( $tone, $tones, true ) ? $tone : 'neutral';
		return '<span class="aod-cd-pill aod-cd-pill--' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * État vide canonique (composant unifié — Phase 5).
	 *
	 * Rend le composant `.aod-cd-empty` : icône décorative + titre + explication.
	 * Le composant porte déjà son fond et sa bordure — ne PAS l'envelopper dans un
	 * `.aod-cd-card` (double carte).
	 *
	 * @param string $icon  Nom d'icône du catalogue AOD_CD_Icon (facultatif).
	 * @param string $title Message principal (obligatoire, déjà traduit).
	 * @param string $text  Explication secondaire (facultatif, déjà traduit).
	 * @param string $class Classes additionnelles sur la racine (ex. contrat JS).
	 * @return string HTML sûr.
	 */
	public static function empty_state( $icon, $title, $text = '', $class = '' ) {
		$cls = 'aod-cd-empty' . ( '' !== $class ? ' ' . $class : '' );
		$out = '<div class="' . esc_attr( $cls ) . '">';
		if ( '' !== $icon ) {
			// Graisse `regular` : la spec prévoyait `light` pour alléger un état vide
			// à 32 px, mais `.aod-cd-empty__ic` porte déjà `opacity:.5`. Embarquer une
			// 3ᵉ graisse pour un effet que l'opacité produit déjà ne se justifiait pas.
			$out .= '<span class="aod-cd-empty__ic" aria-hidden="true">'
				. AOD_CD_Icon::svg( $icon, array( 'size' => 32 ) )
				. '</span>';
		}
		$out .= '<p class="aod-cd-empty__title">' . esc_html( $title ) . '</p>';
		if ( '' !== $text ) {
			$out .= '<p class="aod-cd-empty__txt">' . esc_html( $text ) . '</p>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Couple de champs FR/AR pour un contenu saisi par le gérant.
	 *
	 * `gettext` ne traduit que le CODE : le contenu marchand (titre d'un produit,
	 * texte d'un bloc de page de vente…) doit porter son propre couple { fr, ar }
	 * en base. Ce helper rend les DEUX cases côte à côte, l'arabe en RTL.
	 *
	 * Helper PARTAGÉ (Page de vente, éditeur produit…) : il vit ici, avec
	 * `page_head`/`kpis`/`pill`/`empty_state`, plutôt que dans une section — sinon
	 * chaque section rendrait sa propre variante du même couple.
	 *
	 * @param string $prefix    Préfixe du name (ex. 'blocks[2]', 'sticky', '' hors liste).
	 * @param string $name_base Chemin réel (ex. '[title]', 'name').
	 * @param string $data_base Chemin gabarit pour la renumérotation JS ('' si hors liste).
	 * @param string $label     Libellé (déjà traduit).
	 * @param array  $value     Couple array( 'fr' => …, 'ar' => … ).
	 * @param bool   $multiline true → <textarea>, false → <input type="text">.
	 * @param array  $args      Options : 'required' (bool, FR seulement), 'rows' (int, défaut 3),
	 *                          'rich' (bool, multiline seulement — pose les crochets de
	 *                          l'éditeur riche TinyMCE et pré-formate le contenu, cf. 8.1).
	 * @return void Émet le HTML.
	 */
	public static function render_pair( $prefix, $name_base, $data_base, $label, $value, $multiline = false, $args = array() ) {
		$fr = ( is_array( $value ) && isset( $value['fr'] ) ) ? (string) $value['fr'] : '';
		$ar = ( is_array( $value ) && isset( $value['ar'] ) ) ? (string) $value['ar'] : '';
		$nb = $prefix . $name_base;
		/* translators: %s : libellé du champ (ex. « Titre »). */
		$a_fr = sprintf( __( '%s — français', 'aod-client-dashboard' ), $label );
		/* translators: %s : libellé du champ (ex. « Titre »). */
		$a_ar = sprintf( __( '%s — arabe', 'aod-client-dashboard' ), $label );
		$d_fr = ( '' !== $data_base ) ? ' data-n="' . esc_attr( $data_base . '[fr]' ) . '"' : '';
		$d_ar = ( '' !== $data_base ) ? ' data-n="' . esc_attr( $data_base . '[ar]' ) . '"' : '';
		// `required` ne vaut que pour le FR : c'est la source canonique. Exiger l'AR
		// interdirait d'enregistrer un produit non encore traduit.
		$req  = ! empty( $args['required'] ) ? ' required' : '';
		// `rows` s'applique aux DEUX cases : une case AR plus petite que la FR
		// donnerait un couple visuellement bancal.
		$rows = isset( $args['rows'] ) ? max( 1, (int) $args['rows'] ) : 3;
		// `rich` (8.1) : pose les crochets de l'éditeur TinyMCE (classe DISTINCTE
		// de tout sélecteur existant + sens d'édition par case). N'a de sens que
		// sur des textareas. Le `dir="ltr"` explicite du bloc FR est nécessaire :
		// la page /gestion en arabe est RTL GLOBALE (<html dir="rtl">) et ce bloc
		// n'avait pas de sens propre — la case FR hériterait du RTL, éditeur ou
		// pas (c'est aussi le repli sans JS).
		$rich    = ! empty( $args['rich'] ) && $multiline;
		// Un contenu saisi AVANT l'éditeur riche est du texte brut : ses sauts de
		// ligne seraient réduits à des espaces par le parseur HTML de TinyMCE, et
		// le gérant qui rouvre puis réenregistre un ancien produit APLATIRAIT sa
		// description, en silence. On applique `wpautop` — exactement ce que
		// `the_content` fait au rendu public (prio 10) — pour que l'éditeur reçoive
		// la même structure de paragraphes. `wpautop` est idempotent sur du HTML
		// déjà riche (il ne ré-emballe pas les blocs), donc sans effet sur un
		// contenu déjà saisi avec l'éditeur.
		if ( $rich ) {
			$fr = wpautop( $fr );
			$ar = wpautop( $ar );
		}
		$rich_fr = $rich ? ' class="aod-cd-rich" data-rich-dir="ltr"' : '';
		$rich_ar = $rich ? ' class="aod-cd-rich" data-rich-dir="rtl"' : '';
		?>
		<div class="aod-cd-field aod-cd-lp-pair">
			<span class="aod-cd-label"><?php echo esc_html( $label ); ?></span>
			<div class="aod-cd-lp-langs">
				<div class="aod-cd-lp-lang"<?php echo $rich ? ' dir="ltr"' : ''; ?>>
					<span class="aod-cd-lp-tag" aria-hidden="true"><?php esc_html_e( 'Français', 'aod-client-dashboard' ); ?></span>
					<?php if ( $multiline ) : ?>
						<textarea rows="<?php echo esc_attr( $rows ); ?>"<?php echo $rich_fr . $d_fr . $req; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé ci-dessus. ?> name="<?php echo esc_attr( $nb . '[fr]' ); ?>" aria-label="<?php echo esc_attr( $a_fr ); ?>"><?php echo esc_textarea( $fr ); ?></textarea>
					<?php else : ?>
						<input type="text"<?php echo $d_fr . $req; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé ci-dessus. ?> name="<?php echo esc_attr( $nb . '[fr]' ); ?>" value="<?php echo esc_attr( $fr ); ?>" aria-label="<?php echo esc_attr( $a_fr ); ?>">
					<?php endif; ?>
				</div>
				<div class="aod-cd-lp-lang" dir="rtl">
					<span class="aod-cd-lp-tag" aria-hidden="true"><?php esc_html_e( 'العربية', 'aod-client-dashboard' ); ?></span>
					<?php if ( $multiline ) : ?>
						<textarea rows="<?php echo esc_attr( $rows ); ?>" lang="ar"<?php echo $rich_ar . $d_ar; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé ci-dessus. ?> name="<?php echo esc_attr( $nb . '[ar]' ); ?>" aria-label="<?php echo esc_attr( $a_ar ); ?>"><?php echo esc_textarea( $ar ); ?></textarea>
					<?php else : ?>
						<input type="text" lang="ar"<?php echo $d_ar; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé ci-dessus. ?> name="<?php echo esc_attr( $nb . '[ar]' ); ?>" value="<?php echo esc_attr( $ar ); ?>" aria-label="<?php echo esc_attr( $a_ar ); ?>">
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/* ============================================================
	 * Placeholder
	 * ========================================================== */

	protected function section_soon( $slug ) {
		echo '<div class="aod-cd-soon"><p>' . AOD_CD_Icon::svg( 'blocked', array( 'size' => 18 ) ) . ' ' . esc_html__( 'Cette section arrive bientôt.', 'aod-client-dashboard' ) . '</p></div>';
	}
}
