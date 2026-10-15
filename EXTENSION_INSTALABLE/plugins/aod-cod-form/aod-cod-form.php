<?php
/**
 * Plugin Name:       AOD COD Form
 * Plugin URI:        https://artofdoing.net
 * Description:       Formulaire de commande COD (paiement à la livraison) pour l'Algérie : 58 wilayas + 1541 communes, frais de livraison par wilaya, commande directe sur la page produit. RTL/FR/AR.
 * Version:           1.52.1
 * Author:            Art Of Doing
 * Author URI:        https://artofdoing.net
 * Text Domain:       aod-cod-form
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * License:           GPL-2.0-or-later
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Accès direct interdit.
}

define( 'AOD_COD_VERSION', '1.52.1' );
define( 'AOD_COD_FILE', __FILE__ );

// Anti-flood : nombre max de commandes acceptées par IP et par heure. Seuil large
// (un magasin COD réel n'approche jamais ce volume depuis une seule IP) pour
// écraser un robot mono-IP sans gêner le CGNAT mobile algérien.
if ( ! defined( 'AOD_COD_MAX_PER_IP_HOUR' ) ) {
	define( 'AOD_COD_MAX_PER_IP_HOUR', 10 );
}

// Anti-flood : nombre max de prospects CRÉÉS par IP et par heure (32.2). Bien plus
// haut que le plafond des commandes, et c'est voulu : un prospect naît dès qu'un
// visiteur saisit son téléphone, donc il y en a beaucoup plus que de commandes, et
// en refuser un à tort coûte une vente. On compte les créations, jamais les
// requêtes — le formulaire republie le même prospect à chaque frappe.
if ( ! defined( 'AOD_COD_MAX_LEADS_PER_IP_HOUR' ) ) {
	define( 'AOD_COD_MAX_LEADS_PER_IP_HOUR', 60 );
}

// Anti-abus : quantité maximale acceptée pour UNE ligne de commande (44.1).
// `max( 1, absint( $qty ) )` fermait le bas, jamais le haut — un `items[0][qty]`
// à 100 000 partait en commande `processing`, donc en décrément de stock Woo :
// le produit finissait à stock négatif et « en rupture » pour les vrais
// acheteurs, sans qu'un centime ait été payé (c'est du COD). Seuil large : un
// achat COD réel dépasse rarement quelques unités, et un clone qui vend des lots
// peut le relever d'un `define()` avant chargement, comme ses deux jumelles.
// ⚠️ Il REFUSE, il n'écrête pas : ramener 100 000 à 50 en silence ferait passer
// une commande que l'acheteur n'a pas voulue, et le gérant la traiterait comme
// réelle.
if ( ! defined( 'AOD_COD_MAX_QTY_PER_LINE' ) ) {
	define( 'AOD_COD_MAX_QTY_PER_LINE', 50 );
}
define( 'AOD_COD_PATH', plugin_dir_path( __FILE__ ) );
define( 'AOD_COD_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bloque l'activation si WooCommerce n'est pas présent.
 */
register_activation_hook( __FILE__, function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'AOD COD Form nécessite WooCommerce actif.', 'aod-cod-form' ),
			esc_html__( 'Dépendance manquante', 'aod-cod-form' ),
			array( 'back_link' => true )
		);
	}

	// Crée la table des paniers abandonnés (prospects).
	require_once AOD_COD_PATH . 'includes/class-aod-cod-leads.php';
	AOD_COD_Leads::maybe_migrate();
} );

/**
 * Nettoyage à la désactivation : retire le cron de synchronisation du suivi et
 * les envois de conversion encore en file (Phase 14.1) — sinon WordPress
 * garderait des tâches pointant vers un hook que plus rien n'écoute.
 */
register_deactivation_hook( __FILE__, function () {
	require_once AOD_COD_PATH . 'includes/class-aod-ship-tracking.php';
	AOD_Ship_Tracking::unschedule();
	require_once AOD_COD_PATH . 'includes/class-aod-cod-pixels.php';
	// wp_unschedule_hook() et NON wp_clear_scheduled_hook() : nos tâches portent
	// l'ID de commande en argument, et clear() ne purge que les tâches SANS
	// arguments — il en aurait laissé une par commande en attente.
	wp_unschedule_hook( AOD_COD_Pixels::DISPATCH_HOOK );
	// Même raison pour la file des notifications WhatsApp (31.3, dette D22) :
	// elle porte elle aussi l'ID de commande en argument.
	require_once AOD_COD_PATH . 'includes/class-aod-cod-whatsapp.php';
	wp_unschedule_hook( AOD_COD_WhatsApp::DISPATCH_HOOK );
} );

/**
 * Démarre le plugin une fois tous les plugins chargés
 * (garantit que WooCommerce est disponible).
 */
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'AOD COD Form est inactif : WooCommerce est requis.', 'aod-cod-form' )
				. '</p></div>';
		} );
		return;
	}

	// Sélecteur de langue visiteur : branché AVANT le chargement des traductions
	// pour que le filtre de locale s'applique à toutes les chaînes.
	require_once AOD_COD_PATH . 'includes/class-aod-cod-lang.php';
	AOD_COD_Lang::init();

	// Sur `init`, pas ici : depuis WordPress 6.7, charger un textdomain avant
	// `init` déclenche « _load_textdomain_just_in_time was called incorrectly ».
	add_action( 'init', function () {
		load_plugin_textdomain( 'aod-cod-form', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}, 0 );

	// Journal des sorties externes (31.3). EN PREMIER : le carrier, les pixels,
	// Turnstile et le formulaire l'appellent tous — il doit exister avant eux.
	require_once AOD_COD_PATH . 'includes/class-aod-cod-log.php';
	AOD_COD_Log::boot();
	require_once AOD_COD_PATH . 'includes/class-aod-cod-data.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-catalog-i18n.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-catalog-filters.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-ip.php';
	// Honeypot + time-trap, partagés par la commande ET le contact (32.3).
	require_once AOD_COD_PATH . 'includes/class-aod-cod-antibot.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-turnstile.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-form.php';
	// Icônes du public (37.2) — utilitaire statique pur, aucun hook : chargé
	// inconditionnellement comme les autres, il ne rend rien tant que personne
	// ne l'appelle (le panier est son seul appelant, sous sa garde de toggle).
	require_once AOD_COD_PATH . 'includes/class-aod-cod-icon.php';
	// Panier COD public (35.3) — APRÈS le formulaire, dont il lit cart_enabled().
	require_once AOD_COD_PATH . 'includes/class-aod-cod-cart.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-admin.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-whatsapp.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-leads.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-block.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-pixels.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-no-tunnel.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-pages.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-contact.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-landing.php';
	require_once AOD_COD_PATH . 'includes/class-aod-cod-desc-video.php';
	require_once AOD_COD_PATH . 'includes/class-aod-shipping.php';

	// Challenge anti-robot Turnstile : réglages en admin + vérification en front.
	AOD_COD_Turnstile::instance();
	AOD_COD_Form::instance();
	AOD_COD_Cart::instance(); // panier COD togglable (35.3) — inerte si l'option est coupée.
	// Notif WhatsApp : doit écouter aussi en front (commande créée via AJAX).
	AOD_COD_WhatsApp::instance();
	// Paniers abandonnés : capture AJAX en front + migration de la table.
	AOD_COD_Leads::instance();
	// Bloc Gutenberg (dynamique) + widget Elementor : enregistrement front & éditeur.
	AOD_COD_Block::instance();
	// Pixels publicitaires (Meta/TikTok/Snapchat/Google) : <head> + conversion en front.
	AOD_COD_Pixels::instance();
	// Ni panier ni compte côté acheteur : débranche le tunnel WooCommerce en front.
	AOD_COD_No_Tunnel::instance();
	// Titre/description d'un produit en arabe : filtres d'AFFICHAGE gardés (jamais
	// les getters WC — ils graveraient l'arabe dans les commandes). Sans traduction
	// saisie, ne touche à rien.
	AOD_COD_Catalog_Filters::instance();
	// Les 3 pages publiques : contenu de l'Accueil + libellés du menu (traduits).
	AOD_COD_Pages::instance();
	// Page Contact : coordonnées + formulaire bilingue.
	AOD_COD_Contact::instance();
	// Page de vente (landing) par produit : remplace le gabarit de la fiche produit
	// quand l'interrupteur est ON. Sinon, ne touche à rien.
	AOD_COD_Landing::instance();
	// Vidéo dans la description produit (8.3) : lien YouTube → façade au rendu, et
	// enqueue conditionnel des assets landing (façade JS/CSS, ou CSS seule pour un
	// <video> fichier). Sans vidéo, ne charge et ne touche à rien.
	AOD_COD_Desc_Video::instance();
	// Expédition : instanciée partout (statut « Confirmée » + envoi auto valides hors admin).
	AOD_Shipping::instance();
	// Suivi des colis : synchro cron de l'évolution des commandes chez les livreurs.
	require_once AOD_COD_PATH . 'includes/class-aod-ship-tracking.php';
	AOD_Ship_Tracking::instance( AOD_Shipping::instance() );
	if ( is_admin() ) {
		AOD_COD_Admin::instance();
	}
} );

/**
 * Déclare la compatibilité avec le stockage des commandes haute performance (HPOS).
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
