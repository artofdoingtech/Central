<?php
/**
 * Paniers abandonnés : capture des formulaires COD incomplets (prospects à rappeler).
 *
 * Le plugin crée des commandes en direct (pas de panier WooCommerce classique).
 * L'« abandon » ici = un visiteur qui a saisi son téléphone (et éventuellement
 * nom/wilaya/commune) mais n'a PAS confirmé la commande. On enregistre ce
 * prospect pour que le commerçant puisse le rappeler.
 *
 * Cycle de vie d'une ligne :
 *   - 'pending'   : formulaire commencé, commande pas (encore) confirmée.
 *   - 'converted' : la commande a été confirmée (le prospect a acheté).
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Leads {

	/** @var AOD_COD_Leads|null */
	protected static $instance = null;

	/** Version du schéma SQL (à incrémenter pour forcer une migration). */
	const DB_VERSION = '3';

	/** Clé d'option stockant la version installée. */
	const DB_OPTION = 'aod_cod_leads_db_version';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Migration légère (garde par option : 1 lecture, pas de dbDelta inutile).
		self::maybe_migrate();

		// Capture côté front (visiteur + connecté).
		add_action( 'wp_ajax_aod_cod_lead', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_nopriv_aod_cod_lead', array( $this, 'ajax_save' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_init', array( $this, 'maybe_action' ) );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Base de données                                                       */
	/* --------------------------------------------------------------------- */

	/**
	 * Nom complet de la table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aod_cod_leads';
	}

	/**
	 * Crée/met à jour la table si nécessaire.
	 *
	 * `assigned_confirmer` (Phase 43.3) : `0` = non assignée, jamais `NULL` —
	 * même convention que `_aod_assigned_confirmer` sur les commandes. Colonne
	 * de TABLE (pas une meta : `aod_cod_leads` n'a pas de mécanisme de meta),
	 * lue/écrite en SQL direct par `AOD_CD_Section_Abandoned`
	 * (`aod-client-dashboard`) — ce plugin ne fait que porter le schéma.
	 *
	 * `assigned_by` (Phase 43.4) : ID du GÉRANT qui a posé l'assignation ci-dessus
	 * (`0` = inconnue). Elle n'est JAMAIS affichée sur la page « Commandes
	 * abandonnées » — toute assignation de prospect y est manuelle et gérante par
	 * construction (`ajax_lead_assign()` exige `manage_woocommerce`, et aucun
	 * tourniquet ne touche les prospects), donc un libellé « Admin » y serait
	 * constant, donc muet. Elle existe pour UNE raison : survivre à la conversion.
	 * `build_skeleton_order()` la reporte sur `_aod_assigned_by` de la commande
	 * créée, sans quoi une commande née d'un prospect pré-assigné par le gérant se
	 * lirait « assignée automatiquement » — faux, et silencieusement.
	 * ⚠️ Pas d'index : jamais filtrée ni triée dessus.
	 * ⚠️ Pas de backfill : les prospects assignés sous la 43.3 gardent `0`.
	 */
	public static function maybe_migrate() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token VARCHAR(40) NOT NULL DEFAULT '',
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name VARCHAR(190) NOT NULL DEFAULT '',
			phone VARCHAR(20) NOT NULL DEFAULT '',
			wilaya SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			commune VARCHAR(190) NOT NULL DEFAULT '',
			delivery VARCHAR(10) NOT NULL DEFAULT '',
			qty INT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			assigned_confirmer BIGINT UNSIGNED NOT NULL DEFAULT 0,
			assigned_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY token (token),
			KEY phone (phone),
			KEY assigned_confirmer (assigned_confirmer)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_OPTION, self::DB_VERSION );
	}

	/**
	 * Ce jeton a-t-il déjà une ligne ?
	 *
	 * Sert à distinguer une CRÉATION d'une simple mise à jour, avant même de
	 * toucher au plafond. La colonne `token` est indexée (`KEY token`), donc c'est
	 * une lecture ponctuelle — le prix d'une requête de plus par envoi, assumé pour
	 * ne pas mêler le comptage et l'écriture dans la même méthode.
	 *
	 * @param string $token
	 * @return bool
	 */
	protected static function exists( $token ) {
		global $wpdb;
		$table = self::table();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE token = %s LIMIT 1", $token ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table custom aod_cod_leads (pas d’API WP). Le prepare() est sur cette ligne.
	}

	/**
	 * Insère ou met à jour un prospect (clé = token de session du formulaire).
	 *
	 * @param array $data
	 * @return string 'inserted' | 'updated' | 'converted' (intouché) | 'failed'.
	 */
	protected static function upsert( array $data ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM $table WHERE token = %s", $data['token'] ) );

		if ( $row ) {
			// Ne pas rétrograder un prospect déjà converti.
			if ( 'converted' === $row->status ) {
				return 'converted';
			}
			$data['updated_at'] = $now;
			return ( false === $wpdb->update( $table, $data, array( 'id' => (int) $row->id ) ) ) ? 'failed' : 'updated';
		}

		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		// ⚠️ CE RETOUR N'EXISTAIT PAS, et l'appelant répondait « succès » quoi qu'il
		// arrive. Mesuré avant correctif : un jeton de 300 caractères fait échouer
		// l'INSERT (MySQL en mode strict refuse de tronquer un VARCHAR(40)), et
		// l'AJAX répondait quand même `{"success":true}`. Le prospect était perdu et
		// rien ne le disait — ni au visiteur, ni au gérant, ni à un journal.
		return ( false === $wpdb->insert( $table, $data ) ) ? 'failed' : 'inserted';
	}

	/**
	 * Marque un prospect comme converti (commande confirmée).
	 *
	 * @param string $token
	 * @param int    $order_id
	 */
	public static function mark_converted( $token, $order_id ) {
		$token = (string) $token;
		if ( '' === $token ) {
			return;
		}
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'status'     => 'converted',
				'order_id'   => (int) $order_id,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'token' => $token )
		);
	}

	/* --------------------------------------------------------------------- */
	/* AJAX (capture front)                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * Enregistre un formulaire incomplet (appelé en direct par le JS, fire-and-forget).
	 *
	 * 🚨 C'EST LA SEULE PORTE NON AUTHENTIFIÉE DU DÉPÔT QUI ÉCRIT EN BASE, et elle
	 * n'avait aucun plafond. Le nonce ne protège rien ici : pour un visiteur
	 * déconnecté, WordPress calcule le même sur tout le site et il reste valide 24 h
	 * — on le lit une fois sur n'importe quelle page produit.
	 *
	 * MESURÉ avant correctif, sur ce clone : **15 requêtes avec un jeton inventé à
	 * chaque fois → 15 lignes créées, 15 réponses HTTP 200**, depuis une seule IP,
	 * avec un seul nonce. Rien ne bornait la suite.
	 *
	 * ⚠️ ET LE VRAI DÉGÂT N'EST PAS LA TAILLE DE LA TABLE. Ces lignes s'affichent
	 * dans `/gestion → Commandes abandonnées` comme autant de prospects à rappeler.
	 * Noyer cette liste, c'est faire perdre au gérant les VRAIS prospects — la
	 * fonctionnalité se retourne contre lui, sans jamais tomber en panne.
	 */
	public function ajax_save() {
		check_ajax_referer( 'aod_cod_nonce', 'nonce' );

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['phone'] ) ) : '';

		// Rien d'exploitable sans token ni téléphone : on ignore.
		if ( '' === $token || strlen( $phone ) < 8 ) {
			wp_send_json_error();
		}

		// Forme du jeton. Volontairement LARGE plutôt que calquée sur le générateur
		// du JS (`'l' + base36`) : un contrôle trop serré ferait disparaître la
		// capture des prospects — en silence — le jour où ce générateur change.
		// Ce qu'on borne vraiment, c'est la longueur et l'alphabet.
		if ( ! preg_match( '/^[A-Za-z0-9_-]{8,40}$/', $token ) ) {
			wp_send_json_error( array( 'message' => 'token' ), 400 );
		}

		// Plafond par IP — SUR LES CRÉATIONS, PAS SUR LES REQUÊTES.
		//
		// 🚨 C'est tout le raisonnement de cette garde. Le JS republie le prospect à
		// chaque frappe (débounce 900 ms) : un seul visiteur légitime émet des
		// DIZAINES de requêtes pour UN jeton, sans jamais créer plus d'une ligne.
		// Compter les requêtes aurait donc jeté dehors précisément les acheteurs qui
		// hésitent et se reprennent. La ressource à protéger, ce sont les LIGNES —
		// c'est donc leur création qu'on compte.
		//
		// Un prospect déjà connu passe toujours, plafond atteint ou non : une fois
		// l'IP saturée, un acheteur en cours de saisie ne doit pas perdre la sienne.
		if ( ! self::exists( $token ) && ! self::consume_quota() ) {
			wp_send_json_error( array( 'message' => 'quota' ), 429 );
		}

		$outcome = self::upsert( array(
			'token'      => $token,
			'product_id' => isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0,
			'name'       => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'phone'      => $phone,
			'wilaya'     => isset( $_POST['wilaya'] ) ? absint( $_POST['wilaya'] ) : 0,
			'commune'    => isset( $_POST['commune'] ) ? sanitize_text_field( wp_unslash( $_POST['commune'] ) ) : '',
			'delivery'   => ( isset( $_POST['delivery'] ) && 'desk' === $_POST['delivery'] ) ? 'desk' : 'home',
			'qty'        => isset( $_POST['qty'] ) ? max( 1, absint( $_POST['qty'] ) ) : 1,
			'status'     => 'pending',
		) );

		if ( 'failed' === $outcome ) {
			// Honnêteté élémentaire : ne pas répondre « succès » quand rien n'a été
			// écrit. Le JS est en fire-and-forget et n'en fera rien, mais le journal,
			// lui, garde la trace — c'est la leçon de la 31.3.
			AOD_COD_Log::error( 'leads', 'Écriture du prospect impossible (jeton ' . $token . ').' );
			wp_send_json_error( array( 'message' => 'db' ), 500 );
		}

		wp_send_json_success();
	}

	/**
	 * Consomme une unité du quota horaire de CRÉATION de prospects pour cette IP.
	 *
	 * ⚠️ SEUIL VOLONTAIREMENT LARGE. Perdre un prospect, c'est perdre une vente :
	 * cette garde doit écraser un flood, jamais gêner une boutique qui marche. En
	 * Algérie, le CGNAT mobile fait partager une même IP à beaucoup d'acheteurs — un
	 * plafond serré s'y retournerait contre le gérant. À 60/h, un flood à une
	 * requête par seconde est coupé en une minute, alors qu'une boutique qui
	 * démarrerait 60 formulaires par heure depuis une SEULE IP n'existe pas.
	 *
	 * 🚨 Le refus est JOURNALISÉ, et c'est non négociable : une limite muette est
	 * exactement le défaut soldé par la Phase 26 sur Turnstile. Si un jour de vraies
	 * captures disparaissent, le journal doit permettre de le voir — et de
	 * distinguer « une IP qui inonde » de « toutes les IP confondues par un proxy ».
	 *
	 * ⚠️ ET CETTE PHRASE A FAILLI ÊTRE FAUSSE. Mesuré en écrivant cette étape :
	 * `AOD_COD_Log::error()` écrivait bien sous WP-CLI et **rien du tout depuis une
	 * requête web** — donc jamais là où ça compte. Cause trouvée hors de notre code
	 * (le bind-mount empêchait WordPress de conclure « direct », cf. `FS_METHOD` dans
	 * `docker-compose.yml`), corrigée, et surtout **gardée** : la porte
	 * `bash tools/recette/run.sh journal` provoque un refus par une VRAIE requête
	 * HTTP et exige que la ligne arrive sur le disque. C'est ce contrôle-ci qui rend
	 * la phrase ci-dessus vérifiable au lieu d'être une intention.
	 *
	 * ⚠️ Derrière Cloudflare ou un load-balancer sans `AOD_COD_TRUSTED_PROXY`,
	 * `REMOTE_ADDR` est l'IP DU PROXY : tous les visiteurs partagent alors ce
	 * compteur. Même mise en garde qu'en 26.1, même remède — déclarer la constante.
	 *
	 * @return bool false si le plafond est atteint.
	 */
	protected static function consume_quota() {
		// Source d'IP UNIQUE du plugin (26.1) : `REMOTE_ADDR`, jamais un en-tête
		// forgeable. Ne pas en réintroduire une seconde ici — c'est précisément ce
		// qui avait rendu le coupe-circuit des commandes décoratif.
		$ip = AOD_COD_IP::client();
		if ( '' === $ip ) {
			// Pas d'IP exploitable : on laisse passer plutôt que de bloquer un
			// acheteur pour une raison qu'on ne sait pas nommer. Le cas ne se produit
			// pas sur un serveur web normal.
			return true;
		}

		$max = defined( 'AOD_COD_MAX_LEADS_PER_IP_HOUR' ) ? (int) AOD_COD_MAX_LEADS_PER_IP_HOUR : 60;
		$key = 'aod_cod_leadq_' . md5( $ip );
		$n   = (int) get_transient( $key );

		if ( $n >= $max ) {
			AOD_COD_Log::error( 'leads', sprintf(
				'Plafond de création de prospects atteint (%d/h) — captures refusées pour cette IP. Si des prospects réels manquent, vérifier AOD_COD_TRUSTED_PROXY : derrière un proxy, toutes les IP se confondent.',
				$max
			), array( 'ip' => $ip ) );
			return false;
		}

		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	/* --------------------------------------------------------------------- */
	/* Admin                                                                 */
	/* --------------------------------------------------------------------- */

	public function menu() {
		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() . " WHERE status = 'pending'" );
		$title = __( 'Paniers abandonnés', 'aod-cod-form' );
		$label = $title;
		if ( $count > 0 ) {
			$label .= ' <span class="awaiting-mod">' . $count . '</span>';
		}

		add_submenu_page(
			'woocommerce',
			$title,
			$label,
			'manage_woocommerce',
			'aod-cod-leads',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Traite les actions (suppression d'un prospect, purge des convertis).
	 */
	public function maybe_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Suppression d'une ligne.
		if ( isset( $_GET['aod_lead_delete'] ) ) {
			$id = absint( $_GET['aod_lead_delete'] );
			check_admin_referer( 'aod_lead_delete_' . $id );
			global $wpdb;
			$wpdb->delete( self::table(), array( 'id' => $id ) );
			wp_safe_redirect( add_query_arg( array( 'page' => 'aod-cod-leads', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Purge des prospects convertis.
		if ( isset( $_GET['aod_lead_purge'] ) ) {
			check_admin_referer( 'aod_lead_purge' );
			global $wpdb;
			$wpdb->query( "DELETE FROM " . self::table() . " WHERE status = 'converted'" );
			wp_safe_redirect( add_query_arg( array( 'page' => 'aod-cod-leads', 'purged' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/**
	 * Convertit un téléphone DZ local en numéro international pour wa.me.
	 *
	 * @param string $phone
	 * @return string
	 */
	protected static function wa_number( $phone ) {
		$p = preg_replace( '/\D+/', '', (string) $phone );
		if ( 0 === strpos( $p, '0' ) ) {
			$p = '213' . substr( $p, 1 );
		}
		return $p;
	}

	public function render_page() {
		global $wpdb;
		$table = self::table();

		$pending   = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'pending' ORDER BY updated_at DESC LIMIT 500" );
		$nb_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending'" );
		$nb_conv    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'converted'" );

		// Seuil au-delà duquel un prospect est considéré « abandonné » (sinon « en cours »).
		$abandon_after = 30 * MINUTE_IN_SECONDS;
		$now_ts        = current_time( 'timestamp' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Paniers abandonnés (prospects à rappeler)', 'aod-cod-form' ); ?></h1>

			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Prospect supprimé.', 'aod-cod-form' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['purged'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Prospects convertis purgés.', 'aod-cod-form' ); ?></p></div>
			<?php endif; ?>

			<p>
				<?php
				printf(
					/* translators: 1: nombre de prospects en attente, 2: nombre convertis */
					esc_html__( '%1$d prospect(s) non confirmé(s) — %2$d converti(s) en commande.', 'aod-cod-form' ),
					$nb_pending,
					$nb_conv
				);
				?>
			</p>
			<p class="description"><?php esc_html_e( 'Un prospect apparaît dès qu’un visiteur saisit son téléphone sans valider. Il disparaît automatiquement de cette liste s’il confirme sa commande.', 'aod-cod-form' ); ?></p>

			<?php if ( empty( $pending ) ) : ?>
				<p><strong><?php esc_html_e( 'Aucun panier abandonné pour le moment. 🎉', 'aod-cod-form' ); ?></strong></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Quand', 'aod-cod-form' ); ?></th>
							<th><?php esc_html_e( 'Nom', 'aod-cod-form' ); ?></th>
							<th><?php esc_html_e( 'Téléphone', 'aod-cod-form' ); ?></th>
							<th><?php esc_html_e( 'Produit', 'aod-cod-form' ); ?></th>
							<th><?php esc_html_e( 'Wilaya / Commune', 'aod-cod-form' ); ?></th>
							<th><?php esc_html_e( 'Livraison', 'aod-cod-form' ); ?></th>
							<th><?php esc_html_e( 'Qté', 'aod-cod-form' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'aod-cod-form' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pending as $lead ) : ?>
							<?php
							$ts        = strtotime( $lead->updated_at );
							$age       = $now_ts - $ts;
							$abandoned = ( $age >= $abandon_after );
							$phone     = $lead->phone;
							$wa        = self::wa_number( $phone );

							$product = $lead->product_id ? wc_get_product( $lead->product_id ) : null;
							$pname   = $product ? $product->get_name() : '—';
							$plink   = ( $product && $lead->product_id ) ? get_edit_post_link( $lead->product_id ) : '';

							$wilaya_name = $lead->wilaya ? AOD_COD_Data::wilaya_name( $lead->wilaya ) : '';
							$del_url     = wp_nonce_url(
								add_query_arg( array( 'page' => 'aod-cod-leads', 'aod_lead_delete' => $lead->id ), admin_url( 'admin.php' ) ),
								'aod_lead_delete_' . $lead->id
							);
							?>
							<tr>
								<td>
									<?php echo esc_html( human_time_diff( $ts, $now_ts ) ); ?>
									<?php if ( $abandoned ) : ?>
										<br><span style="color:#b91c1c;font-weight:600;font-size:.85em">⚠ <?php esc_html_e( 'abandonné', 'aod-cod-form' ); ?></span>
									<?php else : ?>
										<br><span style="color:#b45309;font-weight:600;font-size:.85em">⏳ <?php esc_html_e( 'en cours', 'aod-cod-form' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $lead->name ? $lead->name : '—' ); ?></td>
								<td>
									<?php if ( $phone ) : ?>
										<strong><?php echo esc_html( $phone ); ?></strong><br>
										<a href="<?php echo esc_attr( 'tel:' . $phone ); ?>" class="button button-small">📞 <?php esc_html_e( 'Appeler', 'aod-cod-form' ); ?></a>
										<a href="<?php echo esc_url( 'https://wa.me/' . $wa ); ?>" target="_blank" rel="noopener" class="button button-small">💬 WhatsApp</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $plink ) : ?>
										<a href="<?php echo esc_url( $plink ); ?>"><?php echo esc_html( $pname ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $pname ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( $wilaya_name ? sprintf( '%02d - %s', $lead->wilaya, $wilaya_name ) : '—' ); ?>
									<?php if ( $lead->commune ) : ?><br><span class="description"><?php echo esc_html( $lead->commune ); ?></span><?php endif; ?>
								</td>
								<td><?php echo 'desk' === $lead->delivery ? esc_html__( 'Stop-desk', 'aod-cod-form' ) : esc_html__( 'Domicile', 'aod-cod-form' ); ?></td>
								<td><?php echo esc_html( $lead->qty ); ?></td>
								<td>
									<a href="<?php echo esc_url( $del_url ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Supprimer ce prospect ?', 'aod-cod-form' ) ); ?>');"><?php esc_html_e( 'Supprimer', 'aod-cod-form' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( $nb_conv > 0 ) : ?>
				<p style="margin-top:16px">
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'aod-cod-leads', 'aod_lead_purge' => 1 ), admin_url( 'admin.php' ) ), 'aod_lead_purge' ) ); ?>"
					   class="button"
					   onclick="return confirm('<?php echo esc_js( __( 'Supprimer définitivement tous les prospects déjà convertis ?', 'aod-cod-form' ) ); ?>');">
						<?php
						printf(
							/* translators: %d: nombre de prospects convertis */
							esc_html__( 'Purger les %d prospect(s) converti(s)', 'aod-cod-form' ),
							$nb_conv
						);
						?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
