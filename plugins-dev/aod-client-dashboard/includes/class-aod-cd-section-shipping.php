<?php
/**
 * Section « Livraison » : la grille de tarifs par wilaya (Domicile / Stop-desk),
 * les transporteurs et l'import de leurs grilles par API, avec marge appliquée.
 *
 * 🧩 EXTRACTION (Phase 30.4). Sortie du monolithe `class-aod-cd-dashboard.php` à
 * comportement CONSTANT : mêmes actions AJAX (`aod_cd_save_shipping`,
 * `aod_cd_import_fees`, `aod_cd_test_carrier`) — `dashboard.js` inchangé —, même
 * rendu. Move verbatim ; seuls les helpers d'UI partagés (`page_head`,
 * `empty_state`, `pill`) deviennent `AOD_CD_Dashboard::…`. Les helpers de calcul
 * tarifaire `apply_margin()` / `import_apply()` (purs, `public static`) suivent la
 * section — leur logique EST la livraison. `tests/unit/test-tarifs-et-roles.php`
 * les appelle : sa référence a suivi la classe.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Shipping {

	/** @var AOD_CD_Section_Shipping|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_aod_cd_save_shipping', array( $this, 'ajax_save_shipping' ) );
		add_action( 'wp_ajax_aod_cd_import_fees', array( $this, 'ajax_import_fees' ) );
		add_action( 'wp_ajax_aod_cd_test_carrier', array( $this, 'ajax_test_carrier' ) );
		add_action( 'wp_ajax_aod_cd_save_cart_mode', array( $this, 'ajax_save_cart_mode' ) );
	}

	/**
	 * AJAX : active/coupe le panier COD (option `aod_cod_cart`, Phase 35).
	 *
	 * 🔒 RÉSERVÉ AU GÉRANT — même séparation de portée que le bloc Transporteurs
	 * (31.1) : la section est gouvernée par `aod_cap_shipping_prices`, mais le
	 * MODE D'ACHAT de la boutique n'est pas un tarif. Un handler séparé plutôt
	 * qu'un champ de plus dans save_shipping, précisément pour que la garde
	 * diffère. L'option appartient au plugin COD (son uninstall la purge) ;
	 * `'0'` explicite plutôt que delete_option : on distingue « coupé par le
	 * gérant » de « jamais configuré ».
	 */
	public function ajax_save_cart_mode() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		update_option( 'aod_cod_cart', empty( $_POST['cart_enabled'] ) ? '0' : '1' );
		wp_send_json_success( array( 'message' => __( 'Réglage du panier enregistré.', 'aod-client-dashboard' ) ) );
	}

	/**
	 * Le bloc « Transporteurs » est-il administrable par l'utilisateur courant ?
	 *
	 * 🔒 SÉPARATION DE PORTÉE (Phase 31.1). `aod_cap_shipping_prices` s'intitule
	 * « Personnalisation des tarifs de livraison » — le gérant qui coche cette case
	 * consent à déléguer une GRILLE DE PRIX. Il ne consent pas à déléguer les
	 * COMPTES transporteurs de sa boutique, et rien à l'écran ne le lui disait.
	 *
	 * Or ce bloc rend les identifiants API en clair (`render_settings_fields()`
	 * imprime `value="<token>"`), et son enregistrement écrit le DOMAINE appelé par
	 * l'envoi automatique. Un porteur de la seule cap « tarifs » pouvait donc lire
	 * les clés du compte transporteur du client, puis rediriger l'envoi auto vers
	 * un hôte de son choix — chaque commande confirmée y expédiant nom, téléphone,
	 * adresse et montant COD.
	 *
	 * La grille tarifaire et l'import restent gouvernés par `aod_cap_shipping_prices` :
	 * importer ne fait que LIRE via un transporteur déjà connecté, et c'est bien le
	 * travail qu'on délègue. Seule l'administration des comptes remonte au gérant.
	 *
	 * ⚠️ On teste `current_user_can()` en direct, PAS `AOD_CD_Roles::user_can()` :
	 * ce dernier répond vrai à toute `aod_cap_*` dès que l'utilisateur a
	 * `manage_woocommerce`, ce qui est le bon comportement pour une cap déléguée,
	 * mais ferait ici de la garde une tautologie.
	 *
	 * @return bool
	 */
	protected function can_manage_carriers() {
		return current_user_can( 'manage_woocommerce' );
	}

	public function render() {
		if ( ! class_exists( 'AOD_COD_Data' ) ) {
			echo AOD_CD_Dashboard::empty_state( 'shipping', __( 'Le module de livraison (AOD COD Form) n’est pas actif.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$prices   = AOD_COD_Data::prices();
		$free     = AOD_COD_Data::free_shipping();
		$symbol   = get_woocommerce_currency_symbol();
		$shipping = class_exists( 'AOD_Shipping' ) ? AOD_Shipping::instance() : null;
		$places   = AOD_COD_Data::places();
		$carriers = $shipping ? $shipping->carriers() : array();

		// Une wilaya sans aucun tarif n'est PAS livrable (le formulaire COD la
		// masque). L'information est vitale et illisible dans un tableau de 58
		// lignes : on la remonte dans le sous-titre du bloc.
		$served = 0;
		foreach ( $places as $w ) {
			$row = isset( $prices[ (int) $w['code'] ] ) ? $prices[ (int) $w['code'] ] : array();
			$has = ( isset( $row['home'] ) && '' !== (string) $row['home'] )
				|| ( isset( $row['desk'] ) && '' !== (string) $row['desk'] )
				|| ! empty( $row['free'] );
			if ( $has ) {
				$served++;
			}
		}

		$connected = 0;
		foreach ( $carriers as $c ) {
			if ( $c->is_configured() ) {
				$connected++;
			}
		}

		// Panneau d'import (étape 4.3) : affiché uniquement s'il existe un
		// transporteur configuré ET outillé pour l'import (supports_fees()).
		$importable = array();
		foreach ( $carriers as $cid => $c ) {
			if ( $c->is_configured() && $c->supports_fees() ) {
				$importable[ $cid ] = $c->label();
			}
		}

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Livraison', 'aod-client-dashboard' ),
			__( 'Vos tarifs par wilaya, vos transporteurs et l’import de leurs grilles.', 'aod-client-dashboard' )
		);
		?>
		<form class="aod-cd-form aod-cd-acc-form aod-cd-settings-form" data-action="aod_cd_save_shipping">

			<details class="aod-cd-acc" open>
				<summary class="aod-cd-acc-sum">
					<span class="aod-cd-acc-ic" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'money', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<?php esc_html_e( 'Tarifs par wilaya', 'aod-client-dashboard' ); ?>
					<span class="aod-cd-acc-sub">
						<?php
						// Libellé au pluriel invariable plutôt qu'un _n() : nos msgid sont
						// français, or pour une chaîne NON traduite WordPress applique la
						// règle de pluriel anglaise (0 → pluriel) — « 0 wilayas livrées »
						// au lieu de « 0 wilaya livrée ». Ici, aucun accord à faire.
						printf(
							/* translators: 1 : nombre de wilayas livrées, 2 : nombre total de wilayas. */
							esc_html__( 'Wilayas livrées : %1$s / %2$s', 'aod-client-dashboard' ),
							esc_html( number_format_i18n( $served ) ),
							esc_html( number_format_i18n( count( $places ) ) )
						);
						?>
					</span>
				</summary>
				<div class="aod-cd-acc-body">
					<div class="aod-cd-field">
						<label class="aod-cd-check">
							<input type="checkbox" name="fs_enabled" value="1" <?php checked( ! empty( $free['enabled'] ) ); ?>>
							<?php esc_html_e( 'Offrir la livraison à partir d’un certain montant de produits', 'aod-client-dashboard' ); ?>
						</label>
					</div>
					<label class="aod-cd-field aod-cd-field--sm">
						<span class="aod-cd-label"><?php printf( esc_html__( 'Seuil de livraison gratuite (%s)', 'aod-client-dashboard' ), esc_html( $symbol ) ); ?></span>
						<input type="number" min="0" step="any" name="fs_threshold" value="<?php echo ( '' !== (string) $free['threshold'] && (float) $free['threshold'] > 0 ) ? esc_attr( $free['threshold'] ) : ''; ?>">
					</label>

					<div class="aod-cd-subsec">
						<h3 class="aod-cd-subsec__title"><span aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'map', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Grille des tarifs', 'aod-client-dashboard' ); ?></h3>
						<p class="aod-cd-note"><?php esc_html_e( 'Laissez une wilaya vide pour la rendre non livrable. Domicile = livraison à l’adresse ; Stop-desk = retrait au bureau du livreur.', 'aod-client-dashboard' ); ?></p>
						<div class="aod-cd-search">
							<input type="search" class="aod-cd-search-input" data-filter="prices" placeholder="<?php esc_attr_e( 'Rechercher une wilaya…', 'aod-client-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Rechercher une wilaya…', 'aod-client-dashboard' ); ?>" autocomplete="off">
						</div>
						<div class="aod-cd-scroll">
							<table class="aod-cd-table aod-cd-pricetable aod-cd-table--cards">
								<thead><tr>
									<th><?php esc_html_e( 'Wilaya', 'aod-client-dashboard' ); ?></th>
									<th><?php printf( esc_html__( 'Domicile (%s)', 'aod-client-dashboard' ), esc_html( $symbol ) ); ?></th>
									<th><?php printf( esc_html__( 'Stop-desk (%s)', 'aod-client-dashboard' ), esc_html( $symbol ) ); ?></th>
									<?php
									// 🔴 CETTE CASE COCHE LES 58 WILAYAS, ET SON NOM ACCESSIBLE ÉTAIT
									// « Livraison gratuite » — exactement le libellé de la colonne.
									// Rien, ni à l'œil ni au lecteur d'écran, ne distinguait l'INTITULÉ
									// d'un geste qui offre la livraison à tout le pays d'un clic.
									// Le nom réutilise « Tout cocher / décocher », déjà employé pour le
									// même geste dans `class-aod-cd-dashboard.php` : même action, même
									// mot — et pas une chaîne de plus à traduire.
									?>
									<th class="aod-cd-free-col">
										<span class="aod-cd-free-head">
											<input type="checkbox" class="aod-cd-free-all" aria-label="<?php esc_attr_e( 'Tout cocher / décocher', 'aod-client-dashboard' ); ?>">
											<span><?php esc_html_e( 'Livraison gratuite', 'aod-client-dashboard' ); ?></span>
										</span>
									</th>
								</tr></thead>
								<tbody>
									<?php
									$lbl_home = sprintf( __( 'Domicile (%s)', 'aod-client-dashboard' ), $symbol );
									$lbl_desk = sprintf( __( 'Stop-desk (%s)', 'aod-client-dashboard' ), $symbol );
									$lbl_free = __( 'Livraison gratuite', 'aod-client-dashboard' );
									foreach ( $places as $w ) :
										$code = (int) $w['code'];
										// Le nom de la wilaya n'existe que dans la cellule VOISINE : au
										// clavier/lecteur d'écran, ces 174 champs seraient annoncés « zone de
										// saisie », sans jamais dire de quelle wilaya il s'agit. On nomme donc
										// chacun d'eux. (Le libellé de colonne seul ne suffit pas : il ne
										// distingue pas les 58 lignes.)
										$wname   = sprintf( '%02d - %s', $code, $w['name'] );
										$aria    = array(
											/* translators: 1: libellé de colonne (ex. « Domicile (DZD) »), 2: wilaya. */
											'home' => sprintf( __( '%1$s — %2$s', 'aod-client-dashboard' ), $lbl_home, $wname ),
											'desk' => sprintf( __( '%1$s — %2$s', 'aod-client-dashboard' ), $lbl_desk, $wname ),
											'free' => sprintf( __( '%1$s — %2$s', 'aod-client-dashboard' ), $lbl_free, $wname ),
										);
										?>
										<?php
										// 🚨 « VIDE » N'EST PAS « PAS ENCORE REMPLI » — C'EST UNE DÉCISION.
										// La note au-dessus du tableau le dit : « Laissez une wilaya vide
										// pour la rendre non livrable ». Une ligne blanche signifie donc
										// « nous ne vendons pas dans cette wilaya » — et c'est l'unique
										// état que l'œil ne sait pas lire, puisqu'il s'écrit par l'absence.
										// Sur un clone neuf, les 58 lignes sont dans cet état : la boutique
										// ne livre nulle part, et seule une ligne de 13 px le disait.
										// Le marqueur ci-dessous est rendu par CSS quand les DEUX prix sont
										// vides. Il est en `display:none` autrement — donc absent de l'arbre
										// d'accessibilité tant qu'il ne s'applique pas, jamais annoncé à tort.
										// ⚠️ Le `placeholder` n'est pas décoratif : c'est LUI qui rend l'état
										// détectable en CSS (`:placeholder-shown`), sans une ligne de script
										// et sans compteur qui se périmerait à la première frappe. Le tiret
										// est aussi ce qu'on écrit dans une grille tarifaire pour « pas de
										// prix » — il dit la même chose à l'œil et à la feuille de style.
										?>
										<tr>
											<?php
											// 🔢 LE NUMÉRO EST UN NOM, PAS UNE DÉCORATION. En Algérie une
											// wilaya s'appelle par son code autant que par son nom — « je suis
											// de 16 » veut dire Alger. Les deux méritent donc des rôles
											// typographiques distincts : le code en chiffres à chasse fixe,
											// pour qu'on puisse descendre 01 → 58 sans que les noms, de
											// longueurs très inégales, ne fassent tanguer la colonne.
											// ⚠️ Le séparateur reste dans le DOM : `textContent` est inchangé
											// pour qui le lirait entier, et la lecture à voix haute ne devient
											// pas « zéro un Adrar ».
											?>
											<td class="aod-cd-cell-wilaya" data-label="<?php esc_attr_e( 'Wilaya', 'aod-client-dashboard' ); ?>"><span class="aod-cd-w-code"><?php echo esc_html( sprintf( '%02d', $code ) ); ?></span> - <span class="aod-cd-w-name"><?php echo esc_html( $w['name'] ); ?></span><span class="aod-cd-ship-off"><?php esc_html_e( 'Non livrable', 'aod-client-dashboard' ); ?></span></td>
											<td data-label="<?php echo esc_attr( $lbl_home ); ?>"><input type="number" min="0" step="any" placeholder="—" name="home[<?php echo esc_attr( $code ); ?>]" aria-label="<?php echo esc_attr( $aria['home'] ); ?>" value="<?php echo isset( $prices[ $code ]['home'] ) ? esc_attr( $prices[ $code ]['home'] ) : ''; ?>"></td>
											<td data-label="<?php echo esc_attr( $lbl_desk ); ?>"><input type="number" min="0" step="any" placeholder="—" name="desk[<?php echo esc_attr( $code ); ?>]" aria-label="<?php echo esc_attr( $aria['desk'] ); ?>" value="<?php echo isset( $prices[ $code ]['desk'] ) ? esc_attr( $prices[ $code ]['desk'] ) : ''; ?>"></td>
											<td class="aod-cd-free-col" data-label="<?php echo esc_attr( $lbl_free ); ?>"><input type="checkbox" class="aod-cd-free-one" name="free[<?php echo esc_attr( $code ); ?>]" aria-label="<?php echo esc_attr( $aria['free'] ); ?>" value="1" <?php checked( ! empty( $prices[ $code ]['free'] ) ); ?>></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</details>

			<?php
			// 🔒 Bloc réservé au gérant (31.1) : il expose les identifiants API et
			// pilote la destination de l'envoi auto. Cf. can_manage_carriers().
			if ( $shipping && $this->can_manage_carriers() ) :
				$auto = $shipping->auto_settings();
				?>
				<details class="aod-cd-acc" open>
					<summary class="aod-cd-acc-sum">
						<span class="aod-cd-acc-ic" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'shipping', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<?php esc_html_e( 'Transporteurs', 'aod-client-dashboard' ); ?>
						<span class="aod-cd-acc-sub">
							<?php
							printf(
								/* translators: 1 : nombre de transporteurs connectés, 2 : nombre total. */
								esc_html__( 'Transporteurs connectés : %1$s / %2$s', 'aod-client-dashboard' ),
								esc_html( number_format_i18n( $connected ) ),
								esc_html( number_format_i18n( count( $carriers ) ) )
							);
							?>
						</span>
					</summary>
					<div class="aod-cd-acc-body">
						<?php
						// 🚨 47.2 — LA CASE « ENVOYER AUTOMATIQUEMENT » A ÉTÉ RETIRÉE D'ICI.
						// Elle pilotait un hook qui n'existe plus : la laisser aurait
						// donné un interrupteur qui ne commande rien — un écran qui
						// ment. L'envoi est désormais un geste, déclenché depuis
						// Commandes (bouton « Envoyer » de la colonne Suivi).
						//
						// ⚠️ L'option `aod_shipping_auto['enabled']` N'EST PAS SUPPRIMÉE
						// de la base et aucune migration n'est écrite : rien ne doit
						// changer en base chez un clone existant le jour de la mise à
						// jour (même règle qu'en 47.1 pour la méta de transporteur).
						// Elle devient simplement une clé que plus personne ne lit.
						//
						// Le `<select>` ci-dessous, lui, RESTE — et il est plus utile
						// qu'avant : il est la branche 2 de `AOD_Shipping::carrier_for()`
						// (le défaut d'une commande qui n'a pas de choix propre) ET la
						// source du support stop-desk du formulaire public. D'où son
						// nouveau nom : ce n'est plus « le livreur de l'envoi auto ».
						?>
						<label class="aod-cd-field aod-cd-field--md">
							<span class="aod-cd-label"><?php esc_html_e( 'Transporteur par défaut de la boutique', 'aod-client-dashboard' ); ?></span>
							<select name="auto[carrier]" data-aod-select>
								<?php foreach ( $carriers as $id => $c ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $auto['carrier'], $id ); ?>>
										<?php echo esc_html( $c->label() ); ?><?php echo $c->is_configured() ? '' : ' ' . esc_html__( '(non configuré)', 'aod-client-dashboard' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<div class="aod-cd-subsec">
							<h3 class="aod-cd-subsec__title"><span aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'unplugged', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Connexion aux transporteurs', 'aod-client-dashboard' ); ?></h3>
							<p class="aod-cd-note"><?php esc_html_e( 'Cliquez sur un transporteur pour saisir vos identifiants API et le connecter.', 'aod-client-dashboard' ); ?></p>
							<div class="aod-cd-search">
								<input type="search" class="aod-cd-search-input" data-filter="carriers" placeholder="<?php esc_attr_e( 'Rechercher un transporteur…', 'aod-client-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Rechercher un transporteur…', 'aod-client-dashboard' ); ?>" autocomplete="off">
							</div>
							<div class="aod-cd-scroll">
								<table class="aod-cd-table aod-cd-carriers">
									<thead><tr>
										<th><?php esc_html_e( 'Transporteur', 'aod-client-dashboard' ); ?></th>
										<th><?php esc_html_e( 'Statut', 'aod-client-dashboard' ); ?></th>
										<th aria-hidden="true"></th>
									</tr></thead>
									<tbody>
										<?php foreach ( $carriers as $id => $c ) :
											$ok = $c->is_configured(); ?>
											<tr class="aod-cd-carrier-row<?php echo $ok ? ' is-on' : ''; ?>" data-carrier="<?php echo esc_attr( $id ); ?>" tabindex="0" role="button" aria-expanded="false">
												<td class="aod-cd-carrier-name">
													<?php echo $c->icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML sûr (pastille). ?>
													<span><?php echo esc_html( $c->label() ); ?></span>
												</td>
												<td>
													<?php
													// Les deux pastilles sont rendues : après un test « connexion
													// vérifiée », le JS bascule l'affichage sans recharger la page
													// (sinon la ligne continuait d'afficher « Non connecté »).
													?>
													<span class="aod-cd-carrier-on"<?php echo $ok ? '' : ' hidden'; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Connecté', 'aod-client-dashboard' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
													<span class="aod-cd-carrier-off"<?php echo $ok ? ' hidden' : ''; ?>><?php echo AOD_CD_Dashboard::pill( __( 'Non connecté', 'aod-client-dashboard' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
												</td>
												<?php // Le `span` n'est pas décoratif : c'est LUI qui pivote (cf. `.aod-cd-carrier-chev__g`). Faire pivoter la cellule la faisait dépasser de 13 px. ?>
												<td class="aod-cd-carrier-chev" aria-hidden="true"><span class="aod-cd-carrier-chev__g">›</span></td>
											</tr>
											<tr class="aod-cd-carrier-panel" hidden>
												<td colspan="3">
													<div class="aod-cd-carrier-fields">
														<?php $c->render_settings_fields(); // Réutilise les champs du plugin COD (noms <id>[champ]). ?>
														<div class="aod-cd-carrier-test">
															<button type="button" class="aod-cd-btn aod-cd-carrier-test-btn" data-carrier="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Tester la connexion', 'aod-client-dashboard' ); ?></button>
															<span class="aod-cd-carrier-test-msg" aria-live="polite"></span>
														</div>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</details>
			<?php endif; ?>

			<?php
			if ( ! empty( $importable ) ) :
				$has_backup = is_array( get_option( AOD_COD_Data::OPTION_PRICES_BACKUP, null ) );
				?>
				<details class="aod-cd-acc">
					<summary class="aod-cd-acc-sum">
						<span class="aod-cd-acc-ic" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'download', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<?php esc_html_e( 'Importer les tarifs du transporteur', 'aod-client-dashboard' ); ?>
						<span class="aod-cd-acc-sub"><?php esc_html_e( 'récupère la grille officielle via l’API', 'aod-client-dashboard' ); ?></span>
					</summary>
					<div class="aod-cd-acc-body">
						<div class="aod-cd-import" data-import>
							<p class="aod-cd-note"><?php esc_html_e( 'Récupérez les tarifs directement depuis l’API de votre transporteur, prévisualisez-les (avec une marge éventuelle), puis choisissez les wilayas à appliquer. Rien n’est enregistré tant que vous n’avez pas cliqué sur « Appliquer ».', 'aod-client-dashboard' ); ?></p>
							<div class="aod-cd-import-controls">
								<label class="aod-cd-field">
									<span class="aod-cd-label"><?php esc_html_e( 'Transporteur', 'aod-client-dashboard' ); ?></span>
									<select class="aod-cd-import-carrier" data-aod-select>
										<?php foreach ( $importable as $cid => $clabel ) : ?>
											<option value="<?php echo esc_attr( $cid ); ?>"><?php echo esc_html( $clabel ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="aod-cd-field">
									<span class="aod-cd-label"><?php esc_html_e( 'Marge', 'aod-client-dashboard' ); ?></span>
									<select class="aod-cd-import-mtype" data-aod-select>
										<option value="none"><?php esc_html_e( 'Aucune', 'aod-client-dashboard' ); ?></option>
										<option value="fixed"><?php esc_html_e( 'Fixe (DZD)', 'aod-client-dashboard' ); ?></option>
										<option value="percent"><?php esc_html_e( 'Pourcentage (%)', 'aod-client-dashboard' ); ?></option>
									</select>
								</label>
								<label class="aod-cd-field aod-cd-import-mval-wrap" hidden>
									<span class="aod-cd-label"><?php esc_html_e( 'Valeur de la marge', 'aod-client-dashboard' ); ?></span>
									<input type="number" min="0" step="any" class="aod-cd-import-mval" value="0">
								</label>
								<label class="aod-cd-field">
									<span class="aod-cd-label"><?php esc_html_e( 'Arrondi', 'aod-client-dashboard' ); ?></span>
									<select class="aod-cd-import-round" data-aod-select>
										<option value="0"><?php esc_html_e( 'Aucun', 'aod-client-dashboard' ); ?></option>
										<option value="5">5 <?php echo esc_html( $symbol ); ?></option>
										<option value="10">10 <?php echo esc_html( $symbol ); ?></option>
										<option value="50">50 <?php echo esc_html( $symbol ); ?></option>
										<option value="100">100 <?php echo esc_html( $symbol ); ?></option>
									</select>
								</label>
							</div>
							<div class="aod-cd-import-actions">
								<button type="button" class="aod-cd-btn aod-cd-btn-primary aod-cd-import-fetch"><?php esc_html_e( 'Importer les tarifs du transporteur', 'aod-client-dashboard' ); ?></button>
								<button type="button" class="aod-cd-btn aod-cd-import-restore"<?php echo $has_backup ? '' : ' hidden'; ?>><?php esc_html_e( 'Restaurer les tarifs précédents', 'aod-client-dashboard' ); ?></button>
								<span class="aod-cd-import-msg" aria-live="polite"></span>
							</div>
							<div class="aod-cd-import-result" hidden></div>
						</div>
					</div>
				</details>
			<?php endif; ?>

			<div class="aod-cd-form-foot aod-cd-savebar">
				<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Enregistrer la livraison', 'aod-client-dashboard' ); ?></button>
				<span class="aod-cd-form-msg"></span>
			</div>
		</form>
		<?php
		// 🔒 RÉSERVÉ AU GÉRANT (précédent 31.1) : le mode d'achat de la boutique
		// n'est pas un tarif — un confirmateur « tarifs » ne choisit pas si la
		// boutique a un panier. Formulaire séparé du save_shipping : son AJAX
		// porte sa propre garde `manage_woocommerce` (le masquage au rendu n'est
		// qu'un confort d'UI, la moitié qui compte est côté serveur).
		if ( $this->can_manage_carriers() ) :
			$cart_on = class_exists( 'AOD_COD_Form' ) && AOD_COD_Form::cart_enabled();
			?>
			<form class="aod-cd-form aod-cd-acc-form aod-cd-settings-form" data-action="aod_cd_save_cart_mode">
				<details class="aod-cd-acc" open>
					<summary class="aod-cd-acc-sum">
						<span class="aod-cd-acc-ic" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'cart', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG du catalogue fermé AOD_CD_Icon (sûreté démontrée en 18.3, gardée par test-icones.php). ?></span>
						<?php esc_html_e( 'Panier', 'aod-client-dashboard' ); ?>
						<span class="aod-cd-acc-sub"><?php echo $cart_on ? esc_html__( 'activé', 'aod-client-dashboard' ) : esc_html__( 'désactivé', 'aod-client-dashboard' ); ?></span>
					</summary>
					<div class="aod-cd-acc-body">
						<div class="aod-cd-field">
							<label class="aod-cd-check">
								<input type="checkbox" name="cart_enabled" value="1" <?php checked( $cart_on ); ?>>
								<?php esc_html_e( 'Autoriser les clients à commander plusieurs produits en une seule commande (panier COD)', 'aod-client-dashboard' ); ?>
							</label>
						</div>
						<p class="aod-cd-note"><?php esc_html_e( 'Désactivé : chaque produit se commande directement depuis sa page (comportement par défaut). Dans les deux cas, le paiement reste à la livraison, sans compte client.', 'aod-client-dashboard' ); ?></p>
					</div>
				</details>
				<div class="aod-cd-form-foot aod-cd-savebar">
					<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Enregistrer le panier', 'aod-client-dashboard' ); ?></button>
					<span class="aod-cd-form-msg"></span>
				</div>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * AJAX : enregistre tarifs wilayas, livraison gratuite, envoi auto et transporteurs.
	 */
	public function ajax_save_shipping() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_shipping_prices' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_COD_Data' ) ) {
			wp_send_json_error( array( 'message' => __( 'Module de livraison indisponible.', 'aod-client-dashboard' ) ), 400 );
		}

		// Tarifs par wilaya (même schéma que le plugin COD).
		$home   = isset( $_POST['home'] ) ? (array) $_POST['home'] : array();
		$desk   = isset( $_POST['desk'] ) ? (array) $_POST['desk'] : array();
		$free   = isset( $_POST['free'] ) ? (array) $_POST['free'] : array();
		$prices = array();
		foreach ( AOD_COD_Data::places() as $w ) {
			$code = (int) $w['code'];
			$h    = isset( $home[ $code ] ) ? wc_format_decimal( wp_unslash( $home[ $code ] ) ) : '';
			$d    = isset( $desk[ $code ] ) ? wc_format_decimal( wp_unslash( $desk[ $code ] ) ) : '';
			$prices[ $code ] = array(
				'home' => ( '' === $h ) ? '' : (float) $h,
				'desk' => ( '' === $d ) ? '' : (float) $d,
				'free' => empty( $free[ $code ] ) ? 0 : 1,
			);
		}
		update_option( AOD_COD_Data::OPTION_PRICES, $prices );

		// Livraison gratuite.
		update_option( AOD_COD_Data::OPTION_FREE_SHIP, array(
			'enabled'   => isset( $_POST['fs_enabled'] ) ? 1 : 0,
			'threshold' => isset( $_POST['fs_threshold'] ) ? (float) wc_format_decimal( wp_unslash( $_POST['fs_threshold'] ) ) : 0,
		) );

		// Transporteurs + envoi auto (réutilise les classes du plugin COD).
		//
		// 🔒 RÉSERVÉ AU GÉRANT (31.1). Le masquage du bloc au rendu n'est qu'un
		// confort d'UI : sans cette garde, un POST forgé continuerait d'écrire les
		// identifiants transporteurs et la destination de l'envoi auto. C'est la
		// moitié qui compte — celle que l'attaquant ne voit pas.
		//
		// Un porteur de la seule cap « tarifs » n'est PAS refusé pour autant : ses
		// tarifs et sa livraison gratuite viennent d'être enregistrés ci-dessus. On
		// ignore silencieusement la part qui ne le concerne pas, plutôt que de lui
		// opposer un 403 sur un formulaire dont il a rempli sa moitié légitimement.
		if ( class_exists( 'AOD_Shipping' ) && current_user_can( 'manage_woocommerce' ) ) {
			$shipping = AOD_Shipping::instance();
			foreach ( $shipping->carriers() as $id => $carrier ) {
				$slice = isset( $_POST[ $id ] ) && is_array( $_POST[ $id ] ) ? wp_unslash( $_POST[ $id ] ) : array();
				$carrier->save_settings( $carrier->sanitize_settings( $slice ) );
			}
			// 🔁 47.2 — `enabled` n'est PLUS écrit : le formulaire ne le poste plus,
			// et plus personne ne le lit. On le PRÉSERVE néanmoins tel qu'il est en
			// base plutôt que de le remettre à 0 : réécrire la clé d'un clone
			// existant serait une migration silencieuse, exactement ce que la 47.1
			// s'est interdit. Elle s'éteindra d'elle-même, sans qu'on y touche.
			$auto  = isset( $_POST['auto'] ) && is_array( $_POST['auto'] ) ? wp_unslash( $_POST['auto'] ) : array();
			$avant = $shipping->auto_settings();
			update_option( AOD_Shipping::AUTO_OPTION, array(
				'enabled' => (int) $avant['enabled'],
				'carrier' => isset( $auto['carrier'] ) ? sanitize_key( $auto['carrier'] ) : 'ecotrack',
			) );
		}

		wp_send_json_success( array( 'message' => __( 'Réglages de livraison enregistrés.', 'aod-client-dashboard' ) ) );
	}

	/* ============================================================
	 * Import des tarifs depuis l'API transporteur (étape 4.3)
	 * ========================================================== */

	/**
	 * Applique une marge à un tarif API puis un arrondi. Helper PUR (testable).
	 *
	 * Un tarif de base ≤ 0 (mode non fourni par l'API) reste 0 : on n'invente
	 * jamais un prix fantôme. Un résultat négatif est ramené à 0.
	 *
	 * @param int|float $base     Tarif API (DZD).
	 * @param string    $type     'none' | 'fixed' | 'percent'.
	 * @param float     $amount   Marge (DZD si 'fixed', pourcentage si 'percent').
	 * @param int       $rounding Pas d'arrondi au plus proche (0 = aucun ; ex. 50).
	 * @return int Tarif final entier, jamais négatif.
	 */
	public static function apply_margin( $base, $type, $amount, $rounding ) {
		$val = (float) $base;
		if ( $val <= 0 ) {
			return 0;
		}
		$amount = (float) $amount;
		if ( 'fixed' === $type ) {
			$val += $amount;
		} elseif ( 'percent' === $type ) {
			$val += $val * ( $amount / 100 );
		}
		if ( $val < 0 ) {
			$val = 0;
		}
		$rounding = (int) $rounding;
		if ( $rounding > 0 ) {
			$val = round( $val / $rounding ) * $rounding;
		}
		return (int) round( $val );
	}

	/**
	 * Fusionne des tarifs importés dans les tarifs actuels. Helper PUR (testable).
	 *
	 * N'écrase QUE les wilayas cochées présentes dans $fees ; préserve le drapeau
	 * « gratuit » de chaque wilaya et toutes les wilayas non cochées ; pour une
	 * wilaya cochée, ne remplace un mode (home/desk) que si l'API en fournit une
	 * valeur > 0 (une wilaya desk-only ne doit pas remettre le domicile à 0).
	 *
	 * @param array  $current  Tarifs actuels [ code => ['home'=>,'desk'=>,'free'=>] ].
	 * @param array  $fees     Tarifs API normalisés [ code => ['home'=>int,'desk'=>int] ].
	 * @param int[]  $checked  Codes wilaya cochés.
	 * @param string $type     Type de marge.
	 * @param float  $amount   Montant de marge.
	 * @param int    $rounding Pas d'arrondi.
	 * @return array { 'prices' => array, 'applied' => int }.
	 */
	public static function import_apply( $current, $fees, $checked, $type, $amount, $rounding ) {
		$prices  = is_array( $current ) ? $current : array();
		$checked = array_map( 'intval', (array) $checked );
		$applied = 0;
		foreach ( $checked as $code ) {
			if ( ! isset( $fees[ $code ] ) || ! is_array( $fees[ $code ] ) ) {
				continue;
			}
			$api_home = isset( $fees[ $code ]['home'] ) ? (int) $fees[ $code ]['home'] : 0;
			$api_desk = isset( $fees[ $code ]['desk'] ) ? (int) $fees[ $code ]['desk'] : 0;
			if ( $api_home <= 0 && $api_desk <= 0 ) {
				continue;
			}
			$row = ( isset( $prices[ $code ] ) && is_array( $prices[ $code ] ) ) ? $prices[ $code ] : array();
			if ( $api_home > 0 ) {
				$row['home'] = self::apply_margin( $api_home, $type, $amount, $rounding );
			}
			if ( $api_desk > 0 ) {
				$row['desk'] = self::apply_margin( $api_desk, $type, $amount, $rounding );
			}
			if ( ! isset( $row['free'] ) ) {
				$row['free'] = 0;
			}
			$prices[ $code ] = $row;
			$applied++;
		}
		return array( 'prices' => $prices, 'applied' => $applied );
	}

	/**
	 * AJAX : import des tarifs transporteur (op = preview | apply | restore).
	 *
	 * - preview  : LECTURE SEULE — renvoie tarifs API + tarifs actuels (aucune
	 *              écriture d'option). La marge est appliquée à la volée côté JS.
	 * - apply    : écrit les wilayas cochées après avoir sauvegardé l'état courant
	 *              dans OPTION_PRICES_BACKUP ; la marge est recalculée côté serveur
	 *              (source de vérité), le JS n'en est qu'un miroir.
	 * - restore  : réécrit le dernier backup.
	 */
	public function ajax_import_fees() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! AOD_CD_Roles::user_can( 'aod_cap_shipping_prices' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_COD_Data' ) ) {
			wp_send_json_error( array( 'message' => __( 'Module de livraison indisponible.', 'aod-client-dashboard' ) ), 400 );
		}

		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : 'preview';

		// Restauration : indépendante d'un transporteur.
		if ( 'restore' === $op ) {
			$backup = get_option( AOD_COD_Data::OPTION_PRICES_BACKUP, null );
			if ( ! is_array( $backup ) ) {
				wp_send_json_error( array( 'message' => __( 'Aucune sauvegarde à restaurer.', 'aod-client-dashboard' ) ), 200 );
			}
			update_option( AOD_COD_Data::OPTION_PRICES, $backup );
			wp_send_json_success( array(
				'message' => __( 'Tarifs précédents restaurés.', 'aod-client-dashboard' ),
				'reload'  => true,
			) );
		}

		// preview / apply : nécessitent un transporteur outillé et configuré.
		if ( ! class_exists( 'AOD_Shipping' ) ) {
			wp_send_json_error( array( 'message' => __( 'Module transporteurs indisponible.', 'aod-client-dashboard' ) ), 400 );
		}
		$id       = isset( $_POST['carrier'] ) ? sanitize_key( wp_unslash( $_POST['carrier'] ) ) : '';
		$carriers = AOD_Shipping::instance()->carriers();
		if ( '' === $id || ! isset( $carriers[ $id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Transporteur inconnu.', 'aod-client-dashboard' ) ), 400 );
		}
		$carrier = $carriers[ $id ];
		if ( ! $carrier->supports_fees() ) {
			wp_send_json_error( array( 'message' => __( 'Ce transporteur ne propose pas l’import de tarifs.', 'aod-client-dashboard' ) ), 200 );
		}
		if ( ! $carrier->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Renseignez d’abord les identifiants de ce transporteur.', 'aod-client-dashboard' ) ), 200 );
		}

		$fees = $carrier->get_fees();
		if ( is_wp_error( $fees ) ) {
			wp_send_json_error( array( 'message' => $fees->get_error_message() ), 200 );
		}
		if ( ! is_array( $fees ) || empty( $fees ) ) {
			wp_send_json_error( array( 'message' => __( 'Aucun tarif reçu du transporteur.', 'aod-client-dashboard' ) ), 200 );
		}

		// Paramètres de marge (communs).
		$mtype = isset( $_POST['margin_type'] ) ? sanitize_key( wp_unslash( $_POST['margin_type'] ) ) : 'none';
		if ( ! in_array( $mtype, array( 'none', 'fixed', 'percent' ), true ) ) {
			$mtype = 'none';
		}
		$mval  = isset( $_POST['margin_value'] ) ? (float) wp_unslash( $_POST['margin_value'] ) : 0;
		$round = isset( $_POST['rounding'] ) ? (int) $_POST['rounding'] : 0;

		if ( 'apply' === $op ) {
			$checked = isset( $_POST['wilayas'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['wilayas'] ) ) : array();
			if ( empty( $checked ) ) {
				wp_send_json_error( array( 'message' => __( 'Cochez au moins une wilaya à importer.', 'aod-client-dashboard' ) ), 200 );
			}
			$current = AOD_COD_Data::prices();
			// Backup AVANT toute écriture (permet « Restaurer »).
			update_option( AOD_COD_Data::OPTION_PRICES_BACKUP, $current );
			$res = self::import_apply( $current, $fees, $checked, $mtype, $mval, $round );
			update_option( AOD_COD_Data::OPTION_PRICES, $res['prices'] );
			wp_send_json_success( array(
				/* translators: %d: nombre de wilayas mises à jour. */
				'message' => sprintf( _n( '%d wilaya mise à jour.', '%d wilayas mises à jour.', $res['applied'], 'aod-client-dashboard' ), $res['applied'] ),
				'applied' => $res['applied'],
				'reload'  => true,
			) );
		}

		// op = preview (défaut) : LECTURE SEULE.
		$current = AOD_COD_Data::prices();
		$rows    = array();
		foreach ( AOD_COD_Data::places() as $w ) {
			$code = (int) $w['code'];
			if ( ! isset( $fees[ $code ] ) ) {
				continue;
			}
			$rows[] = array(
				'code'     => $code,
				'name'     => sprintf( '%02d - %s', $code, $w['name'] ),
				'api_home' => isset( $fees[ $code ]['home'] ) ? (int) $fees[ $code ]['home'] : 0,
				'api_desk' => isset( $fees[ $code ]['desk'] ) ? (int) $fees[ $code ]['desk'] : 0,
				'cur_home' => ( isset( $current[ $code ]['home'] ) && '' !== $current[ $code ]['home'] ) ? (int) round( (float) $current[ $code ]['home'] ) : null,
				'cur_desk' => ( isset( $current[ $code ]['desk'] ) && '' !== $current[ $code ]['desk'] ) ? (int) round( (float) $current[ $code ]['desk'] ) : null,
			);
		}
		wp_send_json_success( array(
			'rows'       => $rows,
			'carrier'    => $carrier->label(),
			'has_backup' => is_array( get_option( AOD_COD_Data::OPTION_PRICES_BACKUP, null ) ),
		) );
	}

	/**
	 * AJAX : teste la connexion d'un transporteur (vérification « en direct »).
	 *
	 * Enregistre d'abord les identifiants saisis à l'écran (pour tester exactement
	 * ce que voit le gérant, sans imposer un « Enregistrer » préalable), puis
	 * appelle l'API du livreur via AOD_Carrier::test_connection().
	 */
	public function ajax_test_carrier() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		// 🔒 RÉSERVÉ AU GÉRANT (31.1) — divergence VOULUE avec ses deux voisines de
		// section, et RÉ-OUVERTURE ASSUMÉE de ce que la Phase 21 avait aligné.
		//
		// La Phase 21 avait desserré cette garde de `manage_woocommerce` vers
		// `aod_cap_shipping_prices` au motif que « qui peut IMPORTER doit pouvoir
		// TESTER ». La prémisse était fausse : importer ne fait que LIRE une grille
		// via un transporteur déjà connecté, tandis que tester ÉCRIT d'abord les
		// identifiants postés (`save_settings()` quelques lignes plus bas, pour
		// « tester exactement ce que voit le gérant »). Ce handler est donc une
		// primitive d'écriture sur les comptes transporteurs, pas une lecture.
		//
		// Aligner sur la section était le bon réflexe appliqué au mauvais handler.
		// Déclaré 'action-forte' dans tests/unit/test-permissions-sections.php.
		//
		// ⚠️ La cap est écrite EN TOUTES LETTRES ici plutôt que derrière
		// `can_manage_carriers()` : une garde de sécurité doit se lire au point
		// d'appel, et c'est la forme que `test-permissions-sections.php` sait
		// relire. Le helper ne sert qu'au rendu, où il n'y a rien à relire.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( ! class_exists( 'AOD_Shipping' ) ) {
			wp_send_json_error( array( 'message' => __( 'Module de livraison indisponible.', 'aod-client-dashboard' ) ), 400 );
		}

		$id       = isset( $_POST['carrier'] ) ? sanitize_key( wp_unslash( $_POST['carrier'] ) ) : '';
		$carriers = AOD_Shipping::instance()->carriers();
		if ( '' === $id || ! isset( $carriers[ $id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Transporteur inconnu.', 'aod-client-dashboard' ) ), 400 );
		}
		$carrier = $carriers[ $id ];

		// Enregistre les identifiants saisis avant de tester (teste l'écran actuel).
		if ( isset( $_POST[ $id ] ) && is_array( $_POST[ $id ] ) ) {
			$carrier->save_settings( $carrier->sanitize_settings( wp_unslash( $_POST[ $id ] ) ) );
		}
		if ( ! $carrier->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Renseignez les identifiants de ce transporteur avant de tester.', 'aod-client-dashboard' ) ), 200 );
		}

		$res = $carrier->test_connection();
		if ( is_wp_error( $res ) ) {
			/* translators: 1: nom du transporteur, 2: message d'erreur renvoyé par l'API. */
			$msg = sprintf( __( 'Échec de la connexion à %1$s : %2$s', 'aod-client-dashboard' ), $carrier->label(), $res->get_error_message() );
			wp_send_json_error( array( 'message' => $msg ), 200 );
		}

		if ( ! empty( $res['live'] ) ) {
			/* translators: %s: nom du transporteur. */
			$msg = sprintf( __( 'Connexion réussie à %s.', 'aod-client-dashboard' ), $carrier->label() );
			wp_send_json_success( array( 'live' => true, 'message' => $msg ) );
		}

		// Identifiants présents mais pas de vérification en direct (ex. Noest).
		/* translators: %s: nom du transporteur. */
		$msg = sprintf( __( '%s : identifiants enregistrés. La validité sera confirmée au premier envoi (ce transporteur ne propose pas de test en direct).', 'aod-client-dashboard' ), $carrier->label() );
		wp_send_json_success( array( 'live' => false, 'message' => $msg ) );
	}
}
