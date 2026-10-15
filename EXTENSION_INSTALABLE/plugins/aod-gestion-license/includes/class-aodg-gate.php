<?php
/**
 * Applique la licence : bloque /gestion et ses actions AJAX si elle n'est ni
 * valide ni en période de grâce.
 *
 * Centralisé ici plutôt que patché dans AOD Client Dashboard : cette
 * extension distribue une copie NON modifiée d'aod-client-dashboard (voir
 * EXTENSION_INSTALABLE/README.md « Pourquoi 3 plugins et pas 1 »). Le seul
 * point de couplage volontaire est la constante AODG_LICENSE_ACTIVE, testée
 * par aod-client-dashboard.php à son propre démarrage : sans elle (extension
 * de licence absente), le dashboard refuse carrément de s'initialiser — la
 * licence n'est pas contournable en désactivant juste ce plugin-ci.
 *
 * @package AOD_Gestion_License
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AODG_Gate {

	/**
	 * Actions AJAX enregistrées par AOD Client Dashboard (préfixe commun
	 * `aod_cd_`). Liste figée à la version 0.75.0 du plugin — si une future
	 * version en ajoute, complète cette liste (elles sont TOUTES préfixées
	 * `aod_cd_`, donc une recherche suffit) :
	 *
	 *   grep -rhoE "add_action\(\s*'wp_ajax_[a-z0-9_]+'" \
	 *     plugins/aod-client-dashboard --include='*.php' | sort -u
	 *
	 * @var string[]
	 */
	const AJAX_ACTIONS = array(
		'aod_cd_convert_lead',
		'aod_cd_copy_landing',
		'aod_cd_delete_category',
		'aod_cd_delete_leads',
		'aod_cd_delete_member',
		'aod_cd_delete_orders',
		'aod_cd_delete_product',
		'aod_cd_desc_image',
		'aod_cd_desc_video',
		'aod_cd_duplicate_product',
		'aod_cd_export_orders',
		'aod_cd_finance_delete',
		'aod_cd_finance_depense_add',
		'aod_cd_finance_depense_del',
		'aod_cd_finance_entreprise',
		'aod_cd_finance_produits',
		'aod_cd_finance_save',
		'aod_cd_import_fees',
		'aod_cd_lead_assign',
		'aod_cd_order_assign',
		'aod_cd_order_carrier',
		'aod_cd_order_detail',
		'aod_cd_order_save_info',
		'aod_cd_orders_bulk_assign',
		'aod_cd_orders_bulk_carrier',
		'aod_cd_order_status',
		'aod_cd_quick_product',
		'aod_cd_reset_shop',
		'aod_cd_save_account',
		'aod_cd_save_cart_mode',
		'aod_cd_save_category',
		'aod_cd_save_landing',
		'aod_cd_save_member',
		'aod_cd_save_pixels',
		'aod_cd_save_product',
		'aod_cd_save_shipping',
		'aod_cd_save_stock',
		'aod_cd_save_whatsapp',
		'aod_cd_ship_send',
		'aod_cd_test_capi',
		'aod_cd_test_carrier',
		'aod_cd_test_whatsapp',
		'aod_cd_tracking_detail',
		'aod_cd_tracking_refresh',
	);

	public static function init() {
		// Priorité 1 : ces callbacks doivent s'exécuter AVANT les vrais
		// handlers d'aod-client-dashboard, quel que soit l'ordre de
		// chargement des plugins (WordPress trie par priorité, pas par ordre
		// d'enregistrement quand les priorités diffèrent).
		add_action( 'template_redirect', array( __CLASS__, 'maybe_block_front' ), 1 );

		if ( AODG_License::is_valid() ) {
			return;
		}

		foreach ( self::AJAX_ACTIONS as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, 'block_ajax' ), 1 );
		}
	}

	/**
	 * Bloque une requête AJAX du dashboard si la licence n'est pas valide.
	 */
	public static function block_ajax() {
		wp_send_json_error(
			array( 'message' => self::locked_message() ),
			402
		);
	}

	/**
	 * Bloque le rendu de /gestion si la licence n'est pas valide. Ne fait
	 * rien pour toute autre page du site.
	 */
	public static function maybe_block_front() {
		if ( AODG_License::is_valid() ) {
			return;
		}

		// AOD_CD_SLUG n'existe que si aod-client-dashboard est actif ; sans
		// lui il n'y a de toute façon rien à protéger ici.
		if ( ! defined( 'AOD_CD_SLUG' ) || ! get_query_var( 'aod_cd' ) ) {
			return;
		}

		status_header( 402 );
		nocache_headers();

		$is_admin = current_user_can( 'manage_options' );
		$settings = $is_admin ? admin_url( 'admin.php?page=aod-gestion-license' ) : '';

		echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html__( 'Licence requise', 'aod-gestion-license' ) . '</title>';
		echo '<style>
			body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;
				display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;box-sizing:border-box}
			.box{max-width:480px;background:#1e293b;border-radius:12px;padding:32px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.4)}
			h1{font-size:20px;margin:0 0 12px}
			p{line-height:1.6;color:#94a3b8;margin:0 0 20px}
			a.btn{display:inline-block;background:#22c55e;color:#0f172a;font-weight:600;padding:10px 20px;border-radius:8px;text-decoration:none}
		</style></head><body><div class="box">';
		echo '<h1>' . esc_html__( 'Espace de gestion indisponible', 'aod-gestion-license' ) . '</h1>';
		echo '<p>' . esc_html( self::locked_message() ) . '</p>';
		if ( $settings ) {
			echo '<a class="btn" href="' . esc_url( $settings ) . '">' . esc_html__( 'Activer la licence', 'aod-gestion-license' ) . '</a>';
		}
		echo '</div></body></html>';
		exit;
	}

	/**
	 * @return string
	 */
	protected static function locked_message() {
		$status = AODG_License::status();

		if ( 'expired' === $status ) {
			return __( 'La licence de cette extension a expiré. Contactez votre fournisseur pour la renouveler.', 'aod-gestion-license' );
		}

		if ( 'invalid' === $status ) {
			return __( 'La clé de licence enregistrée n’est pas valide pour ce site.', 'aod-gestion-license' );
		}

		return __( 'Aucune licence active. Activez votre clé de licence pour utiliser l’espace de gestion.', 'aod-gestion-license' );
	}
}
