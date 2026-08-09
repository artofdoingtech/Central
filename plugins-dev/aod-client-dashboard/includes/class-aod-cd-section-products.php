<?php
/**
 * Section « Produits » + « Catégories » : CRUD complet depuis /gestion.
 *
 * Le gérant gère son catalogue sans wp-admin : produits (simples, variantes
 * couleur, offres par lot, packs assortiment, galerie) et catégories
 * (product_cat : créer / renommer / supprimer). Extraite du monolithe
 * AOD_CD_Dashboard (étape 7.0) sur le patron des autres sections ; les helpers
 * partagés (page_head, empty_state, pill, render_search_bar, render_pagination)
 * restent dans AOD_CD_Dashboard, appelés en statique.
 *
 * Deux points d'entrée de rendu, car la classe porte deux sections du routeur :
 * render() (products) et render_categories() (categories). get_product_options()
 * et get_product_offers() sont publiques : le code d'édition de commande resté
 * dans le monolithe les consomme.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Products {

	/** @var AOD_CD_Section_Products|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_aod_cd_save_product', array( $this, 'ajax_save_product' ) );
		add_action( 'wp_ajax_aod_cd_delete_product', array( $this, 'ajax_delete_product' ) );
		add_action( 'wp_ajax_aod_cd_duplicate_product', array( $this, 'ajax_duplicate_product' ) );
		add_action( 'wp_ajax_aod_cd_save_category', array( $this, 'ajax_save_category' ) );
		add_action( 'wp_ajax_aod_cd_delete_category', array( $this, 'ajax_delete_category' ) );
		add_action( 'wp_ajax_aod_cd_desc_image', array( $this, 'ajax_desc_image' ) );
		add_action( 'wp_ajax_aod_cd_desc_video', array( $this, 'ajax_desc_video' ) );
	}

	/**
	 * Rend la section « Produits » : le formulaire de création/édition si
	 * ?action=new|edit, sinon la liste paginée.
	 */
	public function render() {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( self::is_form_action( $action ) ) {
			$pid = ( 'edit' === $action && isset( $_GET['product'] ) ) ? absint( $_GET['product'] ) : 0;
			$this->render_product_form( $pid );
			return;
		}
		$this->render_product_list();
	}

	/**
	 * L'action `?action=` affiche-t-elle le FORMULAIRE produit (vs la liste) ?
	 *
	 * Source unique : la coque (`AOD_CD_Dashboard::render_app`) s'en sert pour
	 * décider de charger l'éditeur riche. Sans ce partage, une future action de
	 * formulaire ferait apparaître les textareas `.aod-cd-rich` SANS que TinyMCE
	 * soit chargé — l'éditeur disparaîtrait en silence.
	 *
	 * @param string $action Valeur de `$_GET['action']` (déjà `sanitize_key`).
	 * @return bool
	 */
	public static function is_form_action( $action ) {
		return 'new' === $action || 'edit' === $action;
	}

	/** URL de base de la section produits. */
	protected function products_url( $args = array() ) {
		$url = home_url( '/' . AOD_CD_SLUG . '/products' );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/* ============================================================
	 * Catégories de produits : page de gestion (créer / renommer / supprimer)
	 * ========================================================== */

	/**
	 * Page « Catégories » : création, renommage et suppression des catégories
	 * de produits (taxonomie product_cat). La suppression ne touche pas aux
	 * produits — ils perdent simplement ce classement.
	 */
	public function render_categories() {
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Catégories', 'aod-client-dashboard' ),
			__( 'Classez vos produits. Supprimer une catégorie n’efface aucun produit.', 'aod-client-dashboard' )
		);
		?>
		<div class="aod-cd-cats" id="aod-cd-cats">

			<div class="aod-cd-card aod-cd-cat-create">
				<div class="aod-cd-card-body">
					<h3 class="aod-cd-cat-create__title"><?php esc_html_e( 'Nouvelle catégorie', 'aod-client-dashboard' ); ?></h3>
					<?php
					// 🚨 CE FORMULAIRE EST UNE LIGNE VIERGE DU TABLEAU D'EN DESSOUS — il
					// doit donc en parler la langue. Il portait les MÊMES six champs, dans
					// un ORDRE DIFFÉRENT (accueil et ordre avant « parente », alors que la
					// liste les met après) et sans le moindre intitulé : six contrôles nus
					// dont seul le fantôme du placeholder disait la fonction — et un
					// placeholder disparaît à la première frappe, c'est-à-dire au moment
					// exact où on aurait besoin de le relire.
					// Les intitulés reprennent MOT POUR MOT ceux de l'en-tête de colonnes,
					// dans le MÊME ordre : ce qu'on saisit ici tombe visuellement là où ça
					// atterrira en bas. Aucune chaîne nouvelle — ce sont les mêmes msgid,
					// donc les mêmes traductions arabes.
					// ⚠️ Les `aria-label` de ces quatre champs SONT RETIRÉS, à dessein : le
					// <label> qui les enveloppe donne désormais le nom accessible, et un
					// `aria-label` l'écraserait par un texte DIFFÉRENT de l'intitulé lu à
					// l'écran — c'est précisément ce que WCAG 2.5.3 interdit (« Label in
					// Name »), et ça casse la commande vocale : on dit ce qu'on voit.
					?>
					<form class="aod-cd-cat-new" id="aod-cd-cat-new">
						<label class="aod-cd-catf">
							<span class="aod-cd-catf__lab"><?php esc_html_e( 'Nom', 'aod-client-dashboard' ); ?></span>
							<input type="text" name="cat_name" class="aod-cd-cat-newname" placeholder="<?php esc_attr_e( 'Nom de la nouvelle catégorie', 'aod-client-dashboard' ); ?>" required>
						</label>
						<?php // Le pendant arabe — facultatif : sans lui, le visiteur AR lit le nom français. ?>
						<label class="aod-cd-catf">
							<span class="aod-cd-catf__lab"><?php esc_html_e( 'Nom en arabe', 'aod-client-dashboard' ); ?></span>
							<input type="text" name="cat_name_ar" class="aod-cd-cat-newname-ar" dir="rtl" lang="ar" placeholder="<?php esc_attr_e( 'Nom en arabe (facultatif)', 'aod-client-dashboard' ); ?>">
						</label>
						<label class="aod-cd-catf">
							<span class="aod-cd-catf__lab"><?php esc_html_e( 'Catégorie parente', 'aod-client-dashboard' ); ?></span>
							<select class="aod-cd-cat-newparent">
								<option value="0"><?php esc_html_e( '— Catégorie principale —', 'aod-client-dashboard' ); ?></option>
								<?php $this->render_parent_options( $terms, 0 ); ?>
							</select>
						</label>
						<label class="aod-cd-catf">
							<span class="aod-cd-catf__lab"><?php esc_html_e( 'Ordre', 'aod-client-dashboard' ); ?></span>
							<input type="number" class="aod-cd-cat-newhome-order" min="0" step="1" value="0">
						</label>
						<?php
						// La case et l'action se posent sur leur propre rang, au pied du
						// formulaire : « afficher sur l'accueil » est une DÉCISION sur la
						// catégorie, pas un sixième champ de saisie — et le bouton qui
						// conclut ne doit pas se retrouver coincé entre deux champs, comme
						// il l'était au pouce.
						?>
						<div class="aod-cd-cat-new__foot">
							<label class="aod-cd-cat-home-wrap">
								<input type="checkbox" class="aod-cd-cat-newhome">
								<span class="aod-cd-cat-home-txt"><?php esc_html_e( 'Afficher sur l’accueil', 'aod-client-dashboard' ); ?></span>
							</label>
							<button type="submit" class="aod-cd-btn aod-cd-btn-primary">+ <?php esc_html_e( 'Ajouter', 'aod-client-dashboard' ); ?></button>
						</div>
					</form>
					<?php
					// La limite d'envoi appartient à la PHRASE qui demande l'image, pas au
					// couloir entre deux blocs : elle y flottait, hors de toute carte, en
					// gris minuscule — elle se lisait comme un débris de gabarit. C'est une
					// contrainte, donc elle se pose contre l'instruction qu'elle contraint.
					?>
					<p class="aod-cd-note aod-cd-cat-create__help"><?php esc_html_e( 'Choisissez une catégorie parente pour en faire une sous-catégorie (par exemple une gamme rattachée à une marque). Cliquez sur la vignette d’une catégorie pour lui choisir une image, affichée sur la page d’accueil.', 'aod-client-dashboard' ); ?> <?php echo AOD_CD_Upload::limit_note(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le composant. ?></p>
				</div>
			</div>

			<div class="aod-cd-card aod-cd-cat-panel">
				<div class="aod-cd-card-body">
					<?php
					// 🚨 EN-TÊTE DE COLONNES — la seule chose qui manquait vraiment.
					// Neuf contrôles nus par ligne, et deux nombres côte à côte : « 1 » et
					// « 1 produit ». Rien ne disait que le premier est un RANG d'affichage
					// sur l'accueil et le second un COMPTE. Les `aria-label` des contrôles le
					// disaient déjà au lecteur d'écran ; l'œil, lui, n'avait rien.
					// ⚠️ `aria-hidden` à dessein : chaque contrôle porte DÉJÀ son nom
					// accessible (`aria-label`), donc annoncer l'en-tête le dirait deux fois.
					// C'est un repère visuel, pas une structure de tableau.
					// ⚠️ Hors de `.aod-cd-cat-list` : la liste est un contrat JS
					// (`list.appendChild`, `.aod-cd-cat-empty` enfant direct, cascade
					// `:nth-child`). Un en-tête dedans décalerait les trois.
					?>
					<div class="aod-cd-cat-head" aria-hidden="true">
						<span></span>
						<span><?php esc_html_e( 'Nom', 'aod-client-dashboard' ); ?></span>
						<span><?php esc_html_e( 'Nom en arabe', 'aod-client-dashboard' ); ?></span>
						<span><?php esc_html_e( 'Catégorie parente', 'aod-client-dashboard' ); ?></span>
						<span><?php esc_html_e( 'Accueil', 'aod-client-dashboard' ); ?></span>
						<span><?php esc_html_e( 'Ordre', 'aod-client-dashboard' ); ?></span>
						<span><?php esc_html_e( 'Produits', 'aod-client-dashboard' ); ?></span>
						<span></span>
						<span></span>
					</div>
					<div class="aod-cd-cat-list">
						<?php if ( empty( $terms ) ) : ?>
							<?php
							// La classe `aod-cd-cat-empty` est un contrat JS : dashboard.js la
							// supprime à la création d'une catégorie. Elle doit rester enfant
							// direct de la liste.
							echo AOD_CD_Dashboard::empty_state( 'categories', __( 'Aucune catégorie pour le moment.', 'aod-client-dashboard' ), __( 'Créez-en une ci-dessus pour commencer à classer vos produits.', 'aod-client-dashboard' ), 'aod-cd-cat-empty' ); // phpcs:ignore WordPress.Security.EscapeOutput
							?>
						<?php else :
					foreach ( $terms as $t ) {
						$img_id  = (int) get_term_meta( $t->term_id, 'thumbnail_id', true );
						$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
						// Le nom arabe DOIT être relu ici : sans lui, le champ AR s'afficherait
						// toujours vide et le premier « Enregistrer » effacerait la traduction
						// (ajax_save_category supprime la méta sur valeur vide).
						$name_ar = (string) get_term_meta( $t->term_id, '_aod_cat_name_ar', true );
						// Réglage accueil (9.1) : relu ici pour pré-remplir la case et l'ordre.
						// Sans cette relecture, la case s'afficherait décochée et le premier
						// « Enregistrer » retirerait le flag (home_meta_ops reçoit home=0).
						$home       = '1' === (string) get_term_meta( $t->term_id, '_aod_cat_home', true );
						$home_order = (int) get_term_meta( $t->term_id, '_aod_cat_home_order', true );
						$this->render_category_row( $t->term_id, $t->name, (int) $t->count, $img_url ? $img_url : '', (int) $t->parent, $terms, $name_ar, $home, $home_order );
					}
				endif; ?>
					</div>
				</div>
			</div>
			<template id="aod-cd-cat-row-tpl"><?php $this->render_category_row( 0, '', 0, '', 0, $terms, '', false, 0 ); ?></template>
		</div>
		<?php
	}

	/**
	 * Une ligne de catégorie dans la page de gestion.
	 *
	 * @param int    $id        Term ID.
	 * @param string $name      Nom de la catégorie.
	 * @param int    $count     Nombre de produits rattachés.
	 * @param string $img_url   URL de la miniature (vide si aucune).
	 * @param int    $parent     Term ID de la catégorie parente (0 = principale).
	 * @param array  $all_terms  Toutes les catégories (pour le menu « parente »).
	 * @param string $name_ar    Nom arabe (7.5).
	 * @param bool   $home       Catégorie mise en avant sur l'accueil (9.1).
	 * @param int    $home_order Ordre d'affichage sur l'accueil (9.1).
	 */
	protected function render_category_row( $id, $name, $count, $img_url = '', $parent = 0, $all_terms = array(), $name_ar = '', $home = false, $home_order = 0 ) {
		?>
		<div class="aod-cd-cat-row" data-id="<?php echo esc_attr( (string) $id ); ?>">
			<div class="aod-cd-cat-thumb<?php echo $img_url ? ' has-img' : ''; ?>">
				<img class="aod-cd-cat-thumb-img" src="<?php echo esc_url( $img_url ); ?>" alt=""<?php echo $img_url ? '' : ' hidden'; ?>>
				<span class="aod-cd-cat-thumb-ph" aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'camera', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<input type="file" class="aod-cd-cat-img" accept="image/*" hidden>
				<button type="button" class="aod-cd-cat-imgdel" aria-label="<?php esc_attr_e( 'Retirer l’image', 'aod-client-dashboard' ); ?>"<?php echo $img_url ? '' : ' hidden'; ?>>&times;</button>
			</div>
			<input type="text" class="aod-cd-cat-name" aria-label="<?php /* translators: %s: nom actuel de la catégorie. */ echo esc_attr( sprintf( __( 'Nom de la catégorie « %s »', 'aod-client-dashboard' ), $name ) ); ?>" value="<?php echo esc_attr( $name ); ?>">
			<?php
			// 🚨 Classe DISTINCTE, jamais `aod-cd-cat-name` : ce sélecteur est lu par 5
			// `querySelector` dans dashboard.js, qui rendent le PREMIER nœud trouvé. Partager
			// la classe ferait un jour écrire l'arabe dans le nom canonique (piège de la 7.4).
			?>
			<input type="text" class="aod-cd-cat-name-ar" dir="rtl" lang="ar" placeholder="<?php esc_attr_e( 'Nom en arabe', 'aod-client-dashboard' ); ?>" aria-label="<?php /* translators: %s: nom actuel de la catégorie. */ echo esc_attr( sprintf( __( 'Nom en arabe de la catégorie « %s »', 'aod-client-dashboard' ), $name ) ); ?>" value="<?php echo esc_attr( $name_ar ); ?>">
			<select class="aod-cd-cat-parent" aria-label="<?php esc_attr_e( 'Catégorie parente', 'aod-client-dashboard' ); ?>">
				<option value="0"><?php esc_html_e( '— Catégorie principale —', 'aod-client-dashboard' ); ?></option>
				<?php $this->render_parent_options( $all_terms, $parent, (int) $id ); ?>
			</select>
			<?php
			// Réglage accueil (9.1) : case « à la une » + ordre. Classes DISTINCTES
			// (jamais partagées) — un querySelector partagé finirait par lire la mauvaise
			// valeur (piège de la 7.5-D5). Le <label> porte le nom accessible de la case.
			?>
			<label class="aod-cd-cat-home-wrap">
				<input type="checkbox" class="aod-cd-cat-home"<?php checked( (bool) $home ); ?>>
				<span class="aod-cd-cat-home-txt"><?php esc_html_e( 'Afficher sur l’accueil', 'aod-client-dashboard' ); ?></span>
			</label>
			<input type="number" class="aod-cd-cat-home-order" min="0" step="1" value="<?php echo esc_attr( (string) (int) $home_order ); ?>" aria-label="<?php esc_attr_e( 'Ordre d’affichage sur l’accueil', 'aod-client-dashboard' ); ?>">
			<span class="aod-cd-cat-count aod-cd-badge"><?php
				/* translators: %d: nombre de produits */
				printf( esc_html( _n( '%d produit', '%d produits', $count, 'aod-client-dashboard' ) ), (int) $count );
			?></span>
			<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-cat-save"><?php esc_html_e( 'Enregistrer', 'aod-client-dashboard' ); ?></button>
			<button type="button" class="aod-cd-color-del aod-cd-cat-del" aria-label="<?php esc_attr_e( 'Supprimer la catégorie', 'aod-client-dashboard' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Affiche les <option> du menu « catégorie parente ».
	 *
	 * @param array $terms       Toutes les catégories (objets WP_Term).
	 * @param int   $selected    Term ID actuellement sélectionné.
	 * @param int   $exclude_id  Term ID à exclure (une catégorie ne peut pas être
	 *                           son propre parent ; 0 = aucune exclusion).
	 */
	protected function render_parent_options( $terms, $selected, $exclude_id = 0 ) {
		if ( empty( $terms ) || ! is_array( $terms ) ) {
			return;
		}
		foreach ( $terms as $opt ) {
			if ( (int) $opt->term_id === (int) $exclude_id ) {
				continue;
			}
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $opt->term_id,
				selected( (int) $selected, (int) $opt->term_id, false ),
				esc_html( $opt->name )
			);
		}
	}

	/**
	 * Liste des produits avec actions (ajouter / éditer / supprimer).
	 */
	protected function render_product_list() {
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Produits', 'aod-client-dashboard' ),
			__( 'Votre catalogue : prix, stock et mise en ligne.', 'aod-client-dashboard' ),
			'<a class="aod-cd-btn aod-cd-btn-primary" href="' . esc_url( $this->products_url( array( 'action' => 'new' ) ) ) . '">+ ' . esc_html__( 'Ajouter un produit', 'aod-client-dashboard' ) . '</a>'
		);

		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 20;
		$base_url = $this->products_url();

		// Recherche par nom de produit ou SKU.
		AOD_CD_Dashboard::render_search_bar( $base_url, $search, __( 'Rechercher un produit…', 'aod-client-dashboard' ) );

		$args = array(
			'limit'    => $per_page,
			'page'     => $paged,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'status'   => array( 'publish', 'draft', 'pending', 'private' ),
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$result   = wc_get_products( $args );
		$products = $result->products;

		if ( empty( $products ) ) {
			$msg = ( '' !== $search )
				? __( 'Aucun produit ne correspond à cette recherche.', 'aod-client-dashboard' )
				: __( 'Aucun produit pour le moment. Cliquez sur « Ajouter un produit » pour commencer.', 'aod-client-dashboard' );
			echo AOD_CD_Dashboard::empty_state( 'products', $msg ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards aod-cd-products"><thead><tr>';
		foreach ( array(
			__( 'Produit', 'aod-client-dashboard' ),
			__( 'Prix', 'aod-client-dashboard' ),
			__( 'Stock', 'aod-client-dashboard' ),
			__( 'Statut', 'aod-client-dashboard' ),
			__( 'Actions', 'aod-client-dashboard' ),
		) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $products as $p ) {
			$img      = $p->get_image( array( 40, 40 ) );
			$edit_url = $this->products_url( array( 'action' => 'edit', 'product' => $p->get_id() ) );
			echo '<tr data-product="' . esc_attr( $p->get_id() ) . '">';
			echo '<td class="aod-cd-cell-prod" data-label="' . esc_attr__( 'Produit', 'aod-client-dashboard' ) . '"><span class="aod-cd-prod">' . wp_kses_post( $img ) . '<a class="aod-cd-prod__name" href="' . esc_url( $edit_url ) . '">' . esc_html( $p->get_name() ) . '</a></span></td>';
			echo '<td data-label="' . esc_attr__( 'Prix', 'aod-client-dashboard' ) . '">' . $this->render_price_cell( $p ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML déjà échappé dans le helper.
			// Badge de stock : MÊME règle que la page Stock (une seule source de vérité).
			echo '<td data-label="' . esc_attr__( 'Stock', 'aod-client-dashboard' ) . '">' . AOD_CD_Section_Stock::instance()->stock_pill( $p ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le composant.
			$online = ( 'publish' === $p->get_status() );
			echo '<td data-label="' . esc_attr__( 'Statut', 'aod-client-dashboard' ) . '">' . AOD_CD_Dashboard::pill(
				$online ? __( 'En ligne', 'aod-client-dashboard' ) : __( 'Brouillon', 'aod-client-dashboard' ),
				$online ? 'ok' : 'neutral'
			) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le composant.
			echo '<td class="aod-cd-rowactions" data-label="' . esc_attr__( 'Actions', 'aod-client-dashboard' ) . '">';
			echo '<a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Éditer', 'aod-client-dashboard' ) . '</a> ';
			// Page de vente : seulement si le plugin public est là (c'est lui qui l'affiche).
			if ( AOD_CD_Section_Landing::available() ) {
				$lp_on = AOD_COD_Landing::is_enabled( $p->get_id() );
				echo '<a class="aod-cd-btn aod-cd-btn-sm' . ( $lp_on ? ' is-on' : '' ) . '" href="' . esc_url( AOD_CD_Section_Landing::url( $p->get_id() ) ) . '">'
					. esc_html__( 'Page de vente', 'aod-client-dashboard' )
					. ( $lp_on ? '<span class="aod-cd-lp-on" aria-hidden="true">●</span><span class="screen-reader-text"> ' . esc_html__( '(affichée)', 'aod-client-dashboard' ) . '</span>' : '' )
					. '</a> ';
			}
			// « Dupliquer » AVANT « Supprimer » : les deux agissent sur la ligne, mais
			// l'un est réversible et l'autre non — on ne met pas une action anodine
			// après l'action destructrice, à portée de clic de celle-ci.
			// 🔁 2026-08-09 — « Dupliquer » passe en FANTÔME. Les trois actions de la
			// ligne étaient rendues au même poids : trois boutons bordés identiques,
			// répétés sur chaque produit, dans une colonne qui prenait 367 px — 28 % du
			// tableau. Rien ne disait lequel est le geste courant. Or ils ne sont pas du
			// même rang : « Éditer » est ce qu'on fait dix fois par jour, « Dupliquer »
			// ce qu'on fait en créant une variante de temps en temps. Le fantôme dit
			// « disponible, pas attendu » — et rend son poids à « Éditer » sans avoir à
			// peindre quoi que ce soit en accent, ce qui aurait mis une tache verte sur
			// chaque ligne de la liste.
			echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-btn--ghost aod-cd-dup-product" data-product="' . esc_attr( $p->get_id() ) . '" data-name="' . esc_attr( $p->get_name() ) . '">' . esc_html__( 'Dupliquer', 'aod-client-dashboard' ) . '</button> ';
			// 🔁 2026-08-08 — « Supprimer » passe à l'ICÔNE SEULE. Mesuré : la colonne
			// « Actions » occupait **409 px** sur 1 316, soit presque autant que la
			// colonne du produit lui-même — quatre boutons écrits pour des gestes qu'on
			// ne fait qu'occasionnellement, en face d'une donnée qu'on lit à chaque ligne.
			// ⚠️ SEULEMENT celui-ci. La corbeille est le seul pictogramme de notre
			// catalogue qui dise sans ambiguïté ce que fait son bouton ; « Dupliquer »
			// n'a pas d'icône évidente (ni `stack`, ni `squares-four`, ni `images` ne
			// veulent dire « copier »), et une icône qu'il faut deviner coûte plus que
			// la largeur qu'elle économise. Il garde donc son libellé.
			// ⚠️ Le libellé change de porteur, il ne disparaît pas : `aria-label` pour
			// les lecteurs d'écran, `title` au survol — même chaîne, rien à retraduire.
			echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-btn-danger aod-cd-btn--ico aod-cd-del-product" data-product="' . esc_attr( $p->get_id() ) . '" data-name="' . esc_attr( $p->get_name() ) . '"'
				. ' aria-label="' . esc_attr__( 'Supprimer', 'aod-client-dashboard' ) . '"'
				. ' title="' . esc_attr__( 'Supprimer', 'aod-client-dashboard' ) . '">'
				. AOD_CD_Icon::svg( 'trash', array( 'size' => 15 ) ) . '</button>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		AOD_CD_Dashboard::render_pagination( $base_url, $paged, (int) $result->max_num_pages, array( 's' => $search ) );
	}

	/**
	 * Rend la cellule « Prix » de la liste produits sur plusieurs lignes :
	 * prix normal (barré si une promo est active), prix actuel mis en évidence,
	 * prix d'achat (`_aod_cost_price`) puis la marge (prix actuel − achat).
	 * Évite le `get_price_html()` natif qui injecte du texte « Original price
	 * was… » destiné aux lecteurs d'écran.
	 *
	 * @param WC_Product $p Produit.
	 * @return string HTML de la cellule (déjà échappé).
	 */
	protected function render_price_cell( $p ) {
		$cost    = (string) $p->get_meta( '_aod_cost_price' );
		$current = null; // Prix actuel sous forme numérique, pour la marge (produits simples).
		$out     = '<div class="aod-cd-price">';

		if ( $p->is_type( 'variable' ) ) {
			// Produit variable : on conserve la fourchette de prix native de WooCommerce.
			$html = $p->get_price_html();
			$out .= '<span class="aod-cd-price-now"><span class="aod-cd-price-tag">' . esc_html__( 'Prix', 'aod-client-dashboard' ) . '</span> ' . ( $html ? wp_kses_post( $html ) : '&mdash;' ) . '</span>';
		} else {
			$regular = $p->get_regular_price();
			$sale    = $p->get_sale_price();

			if ( $p->is_on_sale() && '' !== (string) $sale ) {
				$current = (float) $sale;
				$out .= '<span class="aod-cd-price-was"><span class="aod-cd-price-tag">' . esc_html__( 'Prix', 'aod-client-dashboard' ) . '</span> <del>' . wp_kses_post( wc_price( $regular ) ) . '</del></span>';
				$out .= '<span class="aod-cd-price-now"><span class="aod-cd-price-tag">' . esc_html__( 'Promo', 'aod-client-dashboard' ) . '</span> ' . wp_kses_post( wc_price( $sale ) ) . '</span>';
			} else {
				$price = ( '' !== (string) $regular ) ? $regular : $p->get_price();
				if ( '' !== (string) $price ) {
					$current = (float) $price;
				}
				// ⚠️ Le libellé « Prix » est ÉMIS MÊME SEUL depuis le 2026-08-08. Il ne
				// répète pas l'en-tête de colonne pour le plaisir : chaque ligne du bloc
				// est une grille libellé / valeur, et une ligne sans libellé fait tomber son
				// montant dans la COLONNE DES LIBELLÉS. Un produit sans promotion voyait
				// donc son prix décalé à gauche de tous les autres.
				$out .= '<span class="aod-cd-price-now"><span class="aod-cd-price-tag">' . esc_html__( 'Prix', 'aod-client-dashboard' ) . '</span> ' . ( '' !== (string) $price ? wp_kses_post( wc_price( $price ) ) : '&mdash;' ) . '</span>';
			}
		}

		if ( '' !== $cost ) {
			$out .= '<span class="aod-cd-price-cost"><span class="aod-cd-price-tag">' . esc_html__( 'Achat', 'aod-client-dashboard' ) . '</span> ' . wp_kses_post( wc_price( $cost ) ) . '</span>';

			// Marge = prix actuel − prix d'achat (uniquement pour les produits simples avec un prix connu).
			if ( null !== $current ) {
				$margin = $current - (float) $cost;
				$pct    = $current > 0 ? round( $margin / $current * 100 ) : 0;
				$state  = $margin < 0 ? ' aod-cd-price-margin--neg' : '';
				// ⚠️ LE POURCENTAGE EST DANS LA MÊME BOÎTE QUE LE MONTANT, et ce n'est
				// pas cosmétique : chaque ligne du bloc de prix est une grille à DEUX
				// colonnes (libellé / valeur). Cette ligne-ci en avait trois — libellé,
				// montant, pourcentage — et le troisième passait à la ligne suivante,
				// tout seul sous le tableau. Vu à la capture, pas à la lecture.
				$out   .= '<span class="aod-cd-price-margin' . $state . '"><span class="aod-cd-price-tag">' . esc_html__( 'Marge', 'aod-client-dashboard' ) . '</span> <span class="aod-cd-price-val">' . wp_kses_post( wc_price( $margin ) ) . ' <span class="aod-cd-price-pct">(' . esc_html( (string) $pct ) . '%)</span></span></span>';
			}
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Formulaire d'ajout / édition d'un produit.
	 *
	 * @param int $pid 0 pour un nouveau produit.
	 */
	protected function render_product_form( $pid ) {
		$product = $pid ? wc_get_product( $pid ) : null;
		if ( $pid && ! $product ) {
			echo AOD_CD_Dashboard::empty_state( 'question', __( 'Produit introuvable.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$name       = $product ? $product->get_name() : '';
		$desc       = $product ? $product->get_description() : '';
		// Pendants arabes (7.2) : métas PARALLÈLES — le champ cœur (post_title /
		// post_content) reste le FR canonique et n'est jamais touché. Absence de
		// méta `_ar` = repli FR automatique au rendu public.
		$name_ar    = $product ? (string) $product->get_meta( '_aod_title_ar' ) : '';
		$desc_ar    = $product ? (string) $product->get_meta( '_aod_desc_ar' ) : '';
		$reg        = $product ? $product->get_regular_price() : '';
		$sale       = $product ? $product->get_sale_price() : '';
		$sku        = $product ? $product->get_sku() : '';
		$manage     = $product ? $product->managing_stock() : false;
		$qty        = $product ? $product->get_stock_quantity() : '';
		$status     = $product ? $product->get_status() : 'publish';
		$cat_ids    = $product ? $product->get_category_ids() : array();
		$img_url    = ( $product && $product->get_image_id() ) ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '';
		$currency   = get_woocommerce_currency_symbol();

		// Sections d'options (Taille, Couleur, Pointure…) — modèle multi-sections.
		$options = $this->get_product_options( $product );

		$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );

		// Offres (prix par quantité) : N unités de ce produit à un prix de lot.
		// Fusionne les anciennes sections « Prix par quantité » et « Pack/Assortiment ».
		$offers = $this->get_product_offers( $product );

		// Lot 2 : poids, prix d'achat, arguments de vente, galerie.
		$weight      = $product ? (string) $product->get_weight() : '';
		$cost        = $product ? (string) $product->get_meta( '_aod_cost_price' ) : '';
		$points         = $product ? $product->get_meta( '_aod_selling_points' ) : array();
		$points_text    = is_array( $points ) ? implode( "\n", $points ) : '';
		$points_ar      = $product ? $product->get_meta( '_aod_selling_points_ar' ) : array();
		$points_ar_text = is_array( $points_ar ) ? implode( "\n", $points_ar ) : '';
		$gallery_ids = $product ? $product->get_gallery_image_ids() : array();

		// Lot 3 : seuil de stock bas, programmation de la promo.
		$low_stock = $product ? (string) $product->get_low_stock_amount() : '';
		$sale_from = ( $product && $product->get_date_on_sale_from() ) ? $product->get_date_on_sale_from()->date( 'Y-m-d' ) : '';
		$sale_to   = ( $product && $product->get_date_on_sale_to() ) ? $product->get_date_on_sale_to()->date( 'Y-m-d' ) : '';

		$title = $pid ? __( 'Modifier le produit', 'aod-client-dashboard' ) : __( 'Nouveau produit', 'aod-client-dashboard' );

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			$title,
			$pid ? $product->get_name() : __( 'Renseignez au moins un nom et un prix.', 'aod-client-dashboard' ),
			'<a class="aod-cd-btn" href="' . esc_url( $this->products_url() ) . '">&larr; ' . esc_html__( 'Retour à la liste', 'aod-client-dashboard' ) . '</a>'
		);
		?>
		<form class="aod-cd-form aod-cd-acc-form" id="aod-cd-product-form" enctype="multipart/form-data">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $pid ); ?>">

			<!-- Section : Informations générales -->
			<details class="aod-cd-acc" open>
				<summary class="aod-cd-acc-sum"><span class="aod-cd-acc-ic"><?php echo AOD_CD_Icon::svg( 'note-edit', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Informations générales', 'aod-client-dashboard' ); ?></summary>
				<div class="aod-cd-acc-body">
					<?php
					// Titre et description portent leur couple { fr, ar } : ce sont des
					// textes SAISIS par le gérant, donc invisibles à gettext/.po. Le FR
					// part dans le champ cœur, l'AR dans une méta parallèle.
					AOD_CD_Dashboard::render_pair(
						'',
						'name',
						'',
						__( 'Nom du produit', 'aod-client-dashboard' ) . ' *',
						array(
							'fr' => $name,
							'ar' => $name_ar,
						),
						false,
						array( 'required' => true )
					);
					AOD_CD_Dashboard::render_pair(
						'',
						'description',
						'',
						__( 'Description', 'aod-client-dashboard' ),
						array(
							'fr' => $desc,
							'ar' => $desc_ar,
						),
						true,
						// `rich` (8.1) : l'éditeur TinyMCE sur les deux cases —
						// LE SEUL site d'appel qui le passe. Le serveur ne change
						// pas : wp_kses_post() accepte déjà ce HTML (cf.
						// tests/unit/test-desc-kses.php, le contrat barre ⇄ kses).
						array( 'rows' => 6, 'rich' => true )
					);
					?>

					<?php
					// Les arguments portent eux aussi leur couple { fr, ar }. Les deux
					// zones sont côte à côte : c'est ce qui rend visible un décalage de
					// lignes (l'appariement se fait par rang).
					AOD_CD_Dashboard::render_pair(
						'',
						'selling_points',
						'',
						__( 'Arguments de vente (un par ligne)', 'aod-client-dashboard' ),
						array(
							'fr' => $points_text,
							'ar' => $points_ar_text,
						),
						true,
						array( 'rows' => 4 )
					);
					?>
					<span class="aod-cd-note" style="font-size:12px;margin-top:2px"><?php esc_html_e( 'Affichés en liste à puces sur la page produit. Une ligne sans traduction arabe s’affiche en français.', 'aod-client-dashboard' ); ?></span>

					<div class="aod-cd-row2">
						<div class="aod-cd-field">
							<span class="aod-cd-label"><?php esc_html_e( 'Catégories', 'aod-client-dashboard' ); ?></span>
							<div class="aod-cd-catdd" data-placeholder="<?php esc_attr_e( 'Sélectionner des catégories', 'aod-client-dashboard' ); ?>">
								<button type="button" class="aod-cd-catdd-btn" aria-haspopup="true" aria-expanded="false">
									<span class="aod-cd-catdd-text"><?php esc_html_e( 'Sélectionner des catégories', 'aod-client-dashboard' ); ?></span>
									<span class="aod-cd-catdd-caret" aria-hidden="true">▾</span>
								</button>
								<div class="aod-cd-catdd-panel" hidden>
									<?php if ( ! is_wp_error( $categories ) && $categories ) :
										foreach ( $categories as $cat ) :
											$checked = in_array( $cat->term_id, (array) $cat_ids, true ) ? ' checked' : ''; ?>
										<label class="aod-cd-catdd-opt">
											<input type="checkbox" name="category[]" value="<?php echo esc_attr( $cat->term_id ); ?>"<?php echo $checked; // phpcs:ignore WordPress.Security.EscapeOutput -- littéral ' checked' ou '' (ternaire ci-dessus), aucune donnée externe. ?>>
											<span><?php echo esc_html( $cat->name ); ?></span>
										</label>
									<?php endforeach;
									else : ?>
										<span class="aod-cd-catdd-empty"><?php esc_html_e( 'Aucune catégorie pour le moment.', 'aod-client-dashboard' ); ?></span>
									<?php endif; ?>
								</div>
							</div>
							<span class="aod-cd-note" style="font-size:12px;margin-top:2px"><?php esc_html_e( 'Cliquez pour ouvrir la liste et cochez une ou plusieurs catégories.', 'aod-client-dashboard' ); ?></span>
						</div>
						<div class="aod-cd-field">
							<label class="aod-cd-field" style="margin:0">
								<span class="aod-cd-label"><?php esc_html_e( 'Nouvelle catégorie (optionnel)', 'aod-client-dashboard' ); ?></span>
								<input type="text" name="new_category" placeholder="<?php esc_attr_e( 'Crée et assigne une catégorie', 'aod-client-dashboard' ); ?>">
							</label>
							<label class="aod-cd-field" style="margin:14px 0 0">
								<span class="aod-cd-label"><?php esc_html_e( 'Statut', 'aod-client-dashboard' ); ?></span>
								<select name="status">
									<option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( 'En ligne', 'aod-client-dashboard' ); ?></option>
									<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Brouillon', 'aod-client-dashboard' ); ?></option>
								</select>
							</label>
						</div>
					</div>
				</div>
			</details>

			<!-- Bloc 2/5 : Prix & offres -->
			<details class="aod-cd-acc" open>
				<summary class="aod-cd-acc-sum"><span class="aod-cd-acc-ic"><?php echo AOD_CD_Icon::svg( 'money', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Prix & offres', 'aod-client-dashboard' ); ?> <span class="aod-cd-acc-sub"><?php esc_html_e( 'prix, promotion, lots', 'aod-client-dashboard' ); ?></span></summary>
				<div class="aod-cd-acc-body">
					<div class="aod-cd-row2">
						<label class="aod-cd-field">
							<span class="aod-cd-label"><?php printf( esc_html__( 'Prix (%s)', 'aod-client-dashboard' ), esc_html( $currency ) ); ?> *</span>
							<input type="number" step="0.01" min="0" name="regular_price" required value="<?php echo esc_attr( $reg ); ?>">
							<span class="aod-cd-note" style="font-size:12px;margin-top:2px"><?php esc_html_e( 'Si vous ajoutez des variantes, ce prix sert de prix par défaut (utilisé quand une variante n’a pas son propre prix).', 'aod-client-dashboard' ); ?></span>
						</label>
						<label class="aod-cd-field">
							<span class="aod-cd-label"><?php esc_html_e( 'Prix promo (optionnel)', 'aod-client-dashboard' ); ?></span>
							<input type="number" step="0.01" min="0" name="sale_price" value="<?php echo esc_attr( $sale ); ?>">
						</label>
					</div>

					<div class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Période de promotion (optionnel)', 'aod-client-dashboard' ); ?></span>
						<div class="aod-cd-promo-range">
							<div class="aod-cd-promo-cell">
								<span class="aod-cd-promo-cap"><span aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'calendar', array( 'size' => 14 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Début', 'aod-client-dashboard' ); ?></span>
								<div class="aod-cd-datewrap">
									<input type="date" name="sale_from" class="aod-cd-date" aria-label="<?php esc_attr_e( 'Début de la promotion', 'aod-client-dashboard' ); ?>" value="<?php echo esc_attr( $sale_from ); ?>">
									<button type="button" class="aod-cd-date-clear" aria-label="<?php esc_attr_e( 'Effacer la date', 'aod-client-dashboard' ); ?>" hidden><?php echo AOD_CD_Icon::svg( 'close', array( 'size' => 14 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
								</div>
							</div>
							<span class="aod-cd-promo-arrow" aria-hidden="true">→</span>
							<div class="aod-cd-promo-cell">
								<span class="aod-cd-promo-cap"><span aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'calendar', array( 'size' => 14 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Fin', 'aod-client-dashboard' ); ?></span>
								<div class="aod-cd-datewrap">
									<input type="date" name="sale_to" class="aod-cd-date" aria-label="<?php esc_attr_e( 'Fin de la promotion', 'aod-client-dashboard' ); ?>" value="<?php echo esc_attr( $sale_to ); ?>">
									<button type="button" class="aod-cd-date-clear" aria-label="<?php esc_attr_e( 'Effacer la date', 'aod-client-dashboard' ); ?>" hidden><?php echo AOD_CD_Icon::svg( 'close', array( 'size' => 14 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
								</div>
							</div>
						</div>
						<span class="aod-cd-note" style="font-size:12px;margin-top:2px"><?php esc_html_e( 'La promo s’activera et s’arrêtera automatiquement à ces dates.', 'aod-client-dashboard' ); ?></span>
					</div>

					<label class="aod-cd-field" style="max-width:280px">
						<span class="aod-cd-label"><?php printf( esc_html__( 'Prix d’achat (%s, optionnel)', 'aod-client-dashboard' ), esc_html( $currency ) ); ?></span>
						<input type="number" step="0.01" min="0" name="cost_price" value="<?php echo esc_attr( $cost ); ?>">
						<span class="aod-cd-note" style="font-size:12px;margin-top:2px"><?php esc_html_e( 'Sert au calcul de la marge (non affiché au client).', 'aod-client-dashboard' ); ?></span>
					</label>

					<!-- Offres (prix par lot) : rattachées au bloc « Prix & offres » (spec §4.4) —
					     c'est une façon de fixer un prix, pas un sujet séparé. -->
					<div class="aod-cd-subsec">
						<h3 class="aod-cd-subsec__title"><span aria-hidden="true"><?php echo AOD_CD_Icon::svg( 'categories', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Offres', 'aod-client-dashboard' ); ?> <span class="aod-cd-acc-sub"><?php esc_html_e( 'packs « 2 produits », « 3 produits »… (optionnel)', 'aod-client-dashboard' ); ?></span></h3>
						<div class="aod-cd-offers" id="aod-cd-offers">
							<p class="aod-cd-note" style="margin-top:0"><?php esc_html_e( 'Proposez d’acheter plusieurs unités de ce produit à un prix de lot avantageux. Indiquez le NOMBRE d’unités et le PRIX TOTAL du lot (ex. : 2 → 2500, 3 → 3300). Ce total doit être inférieur au prix normal multiplié par le nombre d’unités, sinon l’offre n’apporte aucune réduction et sera ignorée. Sur la page produit, le client verra une carte « 1 produit » par défaut, puis une carte par offre ; au clic, il choisit une variante pour chaque unité.', 'aod-client-dashboard' ); ?></p>

							<div class="aod-cd-offer-head">
								<span><?php esc_html_e( 'Nombre d’unités', 'aod-client-dashboard' ); ?></span>
								<span><?php printf( esc_html__( 'Prix total du lot (%s)', 'aod-client-dashboard' ), esc_html( $currency ) ); ?></span>
								<span></span>
							</div>

							<?php
							// 🔒 LA BALISE OUVRANTE, LA BOUCLE ET LA FERMANTE TIENNENT SUR UNE
							// SEULE LIGNE, et ce n'est pas de la mise en forme : `dashboard.css`
							// masque l'en-tête de colonnes (« Nombre d'unités » / « Prix total du
							// lot ») tant qu'aucune offre n'existe, via `:empty`. Or `:empty`
							// compte les NŒUDS TEXTE — le seul retour à la ligne qu'il y avait ici
							// suffisait à rendre le <div> « non vide », et la règle CSS était morte
							// en silence : l'en-tête restait affiché au-dessus du néant.
							// ⚠️ Ne pas ré-indenter la ligne suivante : le défaut revient, sans que
							// rien ne le signale. Un `:has()` imbriqué aurait évité la contrainte
							// côté CSS, mais la spécification l'interdit.
							$oi = 0;
							?>
							<div class="aod-cd-offer-rows"><?php foreach ( $offers as $row ) { $this->render_offer_row( $oi, $row ); $oi++; } ?></div>

							<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-offer-add" data-next="<?php echo esc_attr( (string) $oi ); ?>">+ <?php esc_html_e( 'Ajouter une offre', 'aod-client-dashboard' ); ?></button>

							<template id="aod-cd-offer-tpl">
								<?php $this->render_offer_row( '__i__', array() ); ?>
							</template>
						</div>
					</div>
				</div>
			</details>

			<!-- Section : Images -->
			<details class="aod-cd-acc">
				<summary class="aod-cd-acc-sum"><span class="aod-cd-acc-ic"><?php echo AOD_CD_Icon::svg( 'images', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Photos', 'aod-client-dashboard' ); ?> <span class="aod-cd-acc-sub"><?php esc_html_e( 'photo principale & galerie', 'aod-client-dashboard' ); ?></span></summary>
				<div class="aod-cd-acc-body aod-cd-grid2">
					<div class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Photo principale', 'aod-client-dashboard' ); ?></span>
						<div class="aod-cd-imgbox">
							<img class="aod-cd-imgprev" src="<?php echo esc_url( $img_url ); ?>" alt="" <?php echo $img_url ? '' : 'style="display:none"'; ?>>
							<span class="aod-cd-imgempty" <?php echo $img_url ? 'style="display:none"' : ''; ?>><?php esc_html_e( 'Aucune image', 'aod-client-dashboard' ); ?></span>
						</div>
						<input type="file" name="image" accept="image/*" class="aod-cd-imgfile" aria-label="<?php esc_attr_e( 'Photo principale', 'aod-client-dashboard' ); ?>">
						<?php echo AOD_CD_Upload::limit_note(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le composant. ?>
					</div>

					<div class="aod-cd-field">
						<span class="aod-cd-label"><?php esc_html_e( 'Galerie (photos supplémentaires)', 'aod-client-dashboard' ); ?></span>
						<div class="aod-cd-gal" id="aod-cd-gallery">
							<?php if ( $gallery_ids ) :
								foreach ( $gallery_ids as $gid ) :
									$gurl = wp_get_attachment_image_url( $gid, 'thumbnail' );
									if ( ! $gurl ) { continue; } ?>
									<div class="aod-cd-gal-item" data-existing="<?php echo esc_attr( $gid ); ?>">
										<img src="<?php echo esc_url( $gurl ); ?>" alt="">
										<input type="checkbox" name="gallery_remove[]" value="<?php echo esc_attr( $gid ); ?>" class="aod-cd-gal-rmcheck" hidden>
										<button type="button" class="aod-cd-gal-rm" aria-label="<?php esc_attr_e( 'Retirer cette photo', 'aod-client-dashboard' ); ?>"><?php echo AOD_CD_Icon::svg( 'close', array( 'size' => 14 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
										<span class="aod-cd-gal-badge"><?php esc_html_e( 'À retirer', 'aod-client-dashboard' ); ?></span>
									</div>
								<?php endforeach;
							endif; ?>
							<button type="button" class="aod-cd-gal-add" id="aod-cd-gal-add">
								<span class="aod-cd-gal-add-ic" aria-hidden="true">＋</span>
								<span class="aod-cd-gal-add-txt"><?php esc_html_e( 'Ajouter', 'aod-client-dashboard' ); ?></span>
							</button>
						</div>
						<input type="file" name="gallery_images[]" accept="image/*" multiple class="aod-cd-galleryfile" hidden>
						<span class="aod-cd-note" style="font-size:12px;margin-top:2px"><?php esc_html_e( 'Cliquez sur « Ajouter » (ou glissez vos fichiers) pour choisir des photos. Cliquez sur ✕ pour en retirer une.', 'aod-client-dashboard' ); ?></span>
						<?php echo AOD_CD_Upload::limit_note(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le composant. ?>
					</div>
				</div>
			</details>

			<!-- Section : Stock & logistique -->
			<details class="aod-cd-acc">
				<summary class="aod-cd-acc-sum"><span class="aod-cd-acc-ic"><?php echo AOD_CD_Icon::svg( 'products', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Stock & logistique', 'aod-client-dashboard' ); ?></summary>
				<div class="aod-cd-acc-body">
					<div class="aod-cd-field">
						<label class="aod-cd-check">
							<input type="checkbox" name="manage_stock" value="1" <?php checked( $manage ); ?> class="aod-cd-stock-toggle">
							<?php esc_html_e( 'Gérer le stock', 'aod-client-dashboard' ); ?>
						</label>
						<?php
						// PAS de min="0" ici : un stock NÉGATIF est un état WooCommerce
						// parfaitement normal (survente — courant en COD, où la commande
						// part sans passer par un panier). Avec min="0", la valeur « -1 »
						// rendait le formulaire invalide ; le navigateur bloquait l'envoi
						// et, le champ étant dans un accordéon replié, il ne pouvait même
						// pas afficher l'erreur : le gérant cliquait « Enregistrer » et il
						// ne se passait RIEN. Un produit survendu devenait immodifiable.
						?>
						<input type="number" name="stock_quantity" class="aod-cd-stock-qty" value="<?php echo esc_attr( (string) $qty ); ?>" placeholder="<?php esc_attr_e( 'Quantité', 'aod-client-dashboard' ); ?>" <?php echo $manage ? '' : 'style="display:none"'; ?>>
						<input type="number" min="0" name="low_stock_amount" class="aod-cd-stock-qty" value="<?php echo esc_attr( $low_stock ); ?>" placeholder="<?php esc_attr_e( 'Alerte stock bas (optionnel)', 'aod-client-dashboard' ); ?>" <?php echo $manage ? '' : 'style="display:none"'; ?>>
					</div>

					<div class="aod-cd-row2">
						<label class="aod-cd-field">
							<span class="aod-cd-label"><?php esc_html_e( 'Référence / SKU (optionnel)', 'aod-client-dashboard' ); ?></span>
							<input type="text" name="sku" value="<?php echo esc_attr( $sku ); ?>">
						</label>
						<label class="aod-cd-field">
							<span class="aod-cd-label"><?php esc_html_e( 'Poids (kg, optionnel)', 'aod-client-dashboard' ); ?></span>
							<input type="number" step="0.001" min="0" name="weight" value="<?php echo esc_attr( $weight ); ?>" placeholder="<?php esc_attr_e( 'ex : 0.5', 'aod-client-dashboard' ); ?>">
						</label>
					</div>
				</div>
			</details>

			<!-- Section : Variantes -->
			<details class="aod-cd-acc">
				<summary class="aod-cd-acc-sum"><span class="aod-cd-acc-ic"><?php echo AOD_CD_Icon::svg( 'palette', array( 'size' => 18 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span> <?php esc_html_e( 'Options & variantes', 'aod-client-dashboard' ); ?> <span class="aod-cd-acc-sub"><?php esc_html_e( 'Taille, Couleur, Pointure… (optionnel)', 'aod-client-dashboard' ); ?></span></summary>
				<div class="aod-cd-acc-body">
					<div class="aod-cd-options" id="aod-cd-options">
						<p class="aod-cd-note" style="margin-top:0"><?php esc_html_e( 'Crée une section par caractéristique (Taille, Pointure, Couleur…). Pour chaque section, ajoute les valeurs que le client pourra choisir. Une section « avec photos » affiche des pastilles cliquables (couleurs, modèles…) ; chaque valeur peut ajouter un supplément de prix. Laisse vide pour un produit simple.', 'aod-client-dashboard' ); ?></p>

						<div class="aod-cd-size-presets">
							<span class="aod-cd-label" style="margin:0"><?php esc_html_e( 'Sections rapides :', 'aod-client-dashboard' ); ?></span>
							<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-opt-preset" data-label="<?php esc_attr_e( 'Sélectionner une taille', 'aod-client-dashboard' ); ?>" data-visual="0" data-values="S,M,L,XL,XXL"><?php esc_html_e( 'Tailles S–XXL', 'aod-client-dashboard' ); ?></button>
							<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-opt-preset" data-label="<?php esc_attr_e( 'Sélectionner une pointure', 'aod-client-dashboard' ); ?>" data-visual="0" data-values="35,36,37,38,39,40,41,42,43,44,45,46"><?php esc_html_e( 'Pointures 35–46', 'aod-client-dashboard' ); ?></button>
							<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-opt-preset" data-label="<?php esc_attr_e( 'Sélectionner une couleur', 'aod-client-dashboard' ); ?>" data-visual="1" data-values=""><?php esc_html_e( 'Couleur (avec photos)', 'aod-client-dashboard' ); ?></button>
						</div>

						<?php // 47.6 — l’aide vaut pour TOUTES les vignettes de valeurs : la répéter
						// sur chacune (40 px, une par couleur) serait du bruit, pas de l’aide. ?>
						<p><?php echo AOD_CD_Upload::limit_note(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le composant. ?></p>
						<div class="aod-cd-opt-sections">
							<?php
							$si = 0;
							foreach ( $options as $section ) {
								$this->render_option_section( $si, $section );
								$si++;
							}
							?>
						</div>

						<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-opt-add" data-next="<?php echo esc_attr( (string) $si ); ?>">+ <?php esc_html_e( 'Ajouter une section', 'aod-client-dashboard' ); ?></button>

						<template id="aod-cd-opt-section-tpl"><?php $this->render_option_section( '{SI}', array( 'values' => array( array() ) ) ); ?></template>
						<?php $this->render_color_palette_popover(); ?>
					</div>
				</div>
			</details>


			<!-- Barre d'enregistrement sticky (spec §4.4) : la fiche produit est longue,
			     un bouton « Enregistrer » tout en bas est hors de vue la plupart du temps. -->
			<div class="aod-cd-form-foot aod-cd-savebar">
				<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Enregistrer', 'aod-client-dashboard' ); ?></button>
				<a class="aod-cd-btn" href="<?php echo esc_url( $this->products_url() ); ?>"><?php esc_html_e( 'Annuler', 'aod-client-dashboard' ); ?></a>
				<span class="aod-cd-form-msg"></span>
			</div>
		</form>
		<?php
	}

	/**
	 * Normalise les sections d'options d'un produit pour l'édition.
	 *
	 * Lit la méta `_aod_options` ; à défaut, migre un ancien produit variable
	 * mono-axe (variations couleur) vers une section visuelle unique.
	 *
	 * @param WC_Product|null $product
	 * @return array Liste de [ 'label', 'visual', 'values' => [ ['name','price','image_id','img'], … ] ].
	 */
	public function get_product_options( $product ) {
		if ( ! $product ) {
			return array();
		}
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
					$values[] = array(
						'name'     => $name,
						'name_ar'  => isset( $val['name_ar'] ) ? (string) $val['name_ar'] : '', // 7.4
						'price'    => ( isset( $val['price'] ) && '' !== $val['price'] ) ? (string) $val['price'] : '',
						'image_id' => $img_id,
						'img'      => $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '',
						'hex'      => isset( $val['hex'] ) ? $this->sanitize_hex( (string) $val['hex'] ) : '',
						// 40.1 — relu pour la saisie (40.2). '' = non géré, int = géré.
						'stock'    => ( isset( $val['stock'] ) && '' !== $val['stock'] ) ? (int) $val['stock'] : '',
					);
				}
				if ( ! $values ) {
					continue;
				}
				$out[] = array(
					'label'    => isset( $sec['label'] ) ? (string) $sec['label'] : '',
					'label_ar' => isset( $sec['label_ar'] ) ? (string) $sec['label_ar'] : '', // 7.4
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
				// 🚨 Pas de __() : ce libellé sert de CLÉ (resolve_item_options() le
				// compare au meta de la ligne de commande, et /gestion peut être en
				// arabe — le cookie aod_lang est partagé avec le public). Traduit ici,
				// il faisait disparaître la variante à la première édition.
				$label = 'Couleur';
			}
			$values = array();
			foreach ( $product->get_children() as $cid ) {
				$v = wc_get_product( $cid );
				if ( ! $v ) {
					continue;
				}
				$atts = $v->get_attributes();
				$name = $atts ? (string) reset( $atts ) : '';
				if ( '' === $name ) {
					continue;
				}
				$img_id   = (int) $v->get_image_id();
				$values[] = array(
					'name'     => $name,
					'name_ar'  => '', // Attribut WC : aucune saisie possible → dictionnaire.
					'price'    => '',
					'image_id' => $img_id,
					'img'      => $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '',
				);
			}
			if ( $values ) {
				$out[] = array( 'label' => $label, 'label_ar' => '', 'visual' => true, 'values' => $values );
			}
		}
		return $out;
	}

	/**
	 * Une section d'options (Taille, Couleur…) dans l'éditeur de produit.
	 *
	 * @param int|string $si      Index de section (ou « {SI} » pour le gabarit JS).
	 * @param array      $section label, visual, values[].
	 */
	protected function render_option_section( $si, $section ) {
		$section  = wp_parse_args( $section, array( 'label' => '', 'label_ar' => '', 'visual' => false, 'display' => 'cards', 'values' => array() ) );
		$s        = esc_attr( (string) $si );
		$visual   = ! empty( $section['visual'] );
		$dropdown = ( 'dropdown' === $section['display'] );
		$values   = is_array( $section['values'] ) ? $section['values'] : array();
		?>
		<div class="aod-cd-opt-section" data-si="<?php echo $s; ?>">
			<div class="aod-cd-opt-head">
				<input type="text" name="opt_label[<?php echo $s; ?>]" class="aod-cd-opt-label" value="<?php echo esc_attr( $section['label'] ); ?>" placeholder="<?php esc_attr_e( 'ex : Sélectionner une couleur, une taille…', 'aod-client-dashboard' ); ?>">
				<?php // 7.4 — ⚠️ classe `-ar` SEULE : `.aod-cd-opt-label` est un sélecteur JS (presets/focus). ?>
				<input type="text" name="opt_label_ar[<?php echo $s; ?>]" class="aod-cd-opt-label-ar" dir="rtl" lang="ar" value="<?php echo esc_attr( $section['label_ar'] ); ?>" placeholder="<?php esc_attr_e( 'en arabe (facultatif)', 'aod-client-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Libellé de la section en arabe', 'aod-client-dashboard' ); ?>">
				<label class="aod-cd-opt-visual">
					<input type="checkbox" name="opt_visual[<?php echo $s; ?>]" value="1" class="aod-cd-opt-visual-cb" <?php checked( $visual ); ?>>
					<?php esc_html_e( 'Avec photos', 'aod-client-dashboard' ); ?>
				</label>
				<label class="aod-cd-opt-visual aod-cd-opt-dropdown" title="<?php esc_attr_e( 'Affiche cette section en menu déroulant sur le formulaire de commande (idéal quand il y a beaucoup de valeurs).', 'aod-client-dashboard' ); ?>">
					<input type="checkbox" name="opt_dropdown[<?php echo $s; ?>]" value="1" class="aod-cd-opt-dropdown-cb" <?php checked( $dropdown ); ?>>
					<?php esc_html_e( 'Liste déroulante', 'aod-client-dashboard' ); ?>
				</label>
				<button type="button" class="aod-cd-color-del aod-cd-opt-sec-del" aria-label="<?php esc_attr_e( 'Supprimer cette section', 'aod-client-dashboard' ); ?>">&times;</button>
			</div>
			<div class="aod-cd-opt-values<?php echo $visual ? ' is-visual' : ''; ?>">
				<?php
				$vi = 0;
				foreach ( $values as $val ) {
					$this->render_option_value( $si, $vi, $val );
					$vi++;
				}
				?>
			</div>
			<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-opt-val-add" data-next="<?php echo esc_attr( (string) $vi ); ?>">+ <?php esc_html_e( 'Ajouter une valeur', 'aod-client-dashboard' ); ?></button>
			<template class="aod-cd-opt-val-tpl"><?php $this->render_option_value( $si, '{VI}', array() ); ?></template>
		</div>
		<?php
	}

	/**
	 * Une valeur d'option (Rouge, L, 42…) avec photo et supplément de prix optionnels.
	 *
	 * @param int|string $si  Index de section.
	 * @param int|string $vi  Index de valeur (ou « {VI} » pour le gabarit JS).
	 * @param array      $val name, price, image_id, img.
	 */
	protected function render_option_value( $si, $vi, $val ) {
		$val = wp_parse_args( $val, array( 'name' => '', 'name_ar' => '', 'price' => '', 'image_id' => 0, 'img' => '', 'hex' => '', 'stock' => '' ) );
		$s   = esc_attr( (string) $si );
		$v   = esc_attr( (string) $vi );
		$hex = $this->sanitize_hex( (string) $val['hex'] );
		// 40.2 — stock par valeur : '' = non géré (illimité) → champ VIDE, jamais « 0 »
		// (0 = rupture, une valeur bien différente). Voir la garde serveur en 40.1.
		$stock_val = ( '' !== (string) $val['stock'] ) ? (string) (int) $val['stock'] : '';
		?>
		<div class="aod-cd-opt-value" data-vi="<?php echo $v; ?>">
			<label class="aod-cd-opt-imgbox">
				<img class="aod-cd-opt-imgprev" src="<?php echo esc_url( $val['img'] ); ?>" alt="" <?php echo $val['img'] ? '' : 'style="display:none"'; ?>>
				<span class="aod-cd-opt-imgempty" <?php echo $val['img'] ? 'style="display:none"' : ''; ?>><?php echo AOD_CD_Icon::svg( 'camera', array( 'size' => 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<input type="hidden" name="opt_value_imgid[<?php echo $s; ?>][<?php echo $v; ?>]" value="<?php echo esc_attr( (string) $val['image_id'] ); ?>">
				<input type="file" name="opt_img[<?php echo $s; ?>][<?php echo $v; ?>]" accept="image/*" class="aod-cd-opt-imgfile">
			</label>
			<button type="button" class="aod-cd-opt-swatch<?php echo $hex ? ' has-color' : ''; ?>" style="<?php echo $hex ? 'background-color:' . esc_attr( $hex ) : ''; ?>" aria-label="<?php esc_attr_e( 'Choisir une couleur', 'aod-client-dashboard' ); ?>" title="<?php esc_attr_e( 'Choisir une couleur prédéfinie', 'aod-client-dashboard' ); ?>"><?php echo AOD_CD_Icon::svg( 'palette', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<input type="hidden" name="opt_value_hex[<?php echo $s; ?>][<?php echo $v; ?>]" class="aod-cd-opt-hex" value="<?php echo esc_attr( $hex ); ?>">
			<input type="text" name="opt_value_name[<?php echo $s; ?>][<?php echo $v; ?>]" class="aod-cd-opt-name" value="<?php echo esc_attr( $val['name'] ); ?>" placeholder="<?php esc_attr_e( 'ex : Rouge, L, 42…', 'aod-client-dashboard' ); ?>">
			<?php // 7.4 — ⚠️ classe `-ar` SEULE : `.aod-cd-opt-name` est le sélecteur de la CLÉ canonique. ?>
			<input type="text" name="opt_value_name_ar[<?php echo $s; ?>][<?php echo $v; ?>]" class="aod-cd-opt-name-ar" dir="rtl" lang="ar" value="<?php echo esc_attr( $val['name_ar'] ); ?>" placeholder="<?php esc_attr_e( 'en arabe', 'aod-client-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Nom de la valeur en arabe', 'aod-client-dashboard' ); ?>">
			<span class="aod-cd-opt-plus" aria-hidden="true">+</span>
			<?php // 40.2 — prix (supplément) et stock partagent UNE cellule de grille : l'empreinte de la grille .aod-cd-opt-value ne change pas (robuste aux 4 déclinaisons responsive). ?>
			<div class="aod-cd-opt-nums">
				<input type="number" step="0.01" min="0" name="opt_value_price[<?php echo $s; ?>][<?php echo $v; ?>]" class="aod-cd-opt-price" value="<?php echo esc_attr( $val['price'] ); ?>" placeholder="<?php esc_attr_e( 'supplément', 'aod-client-dashboard' ); ?>">
				<?php // 40.2 — stock par valeur. `-ar` non concerné (nombre). Un seul axe à stock (D1bis) : le JS grise les autres axes ; le serveur re-normalise (40.1). ?>
				<input type="number" step="1" min="0" name="opt_value_stock[<?php echo $s; ?>][<?php echo $v; ?>]" class="aod-cd-opt-stock" value="<?php echo esc_attr( $stock_val ); ?>" placeholder="<?php esc_attr_e( 'stock', 'aod-client-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Stock de cette variante', 'aod-client-dashboard' ); ?>" title="<?php esc_attr_e( 'Stock de cette variante. Laisser vide = stock non géré (illimité). 0 = rupture.', 'aod-client-dashboard' ); ?>" data-guard="<?php esc_attr_e( 'Le stock se gère sur un seul axe. Videz les stocks de l’autre axe pour l’activer ici.', 'aod-client-dashboard' ); ?>">
			</div>
			<button type="button" class="aod-cd-color-del aod-cd-opt-val-del" aria-label="<?php esc_attr_e( 'Supprimer cette valeur', 'aod-client-dashboard' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Palette de couleurs prédéfinies (nom → code hex réel). Permet au marchand
	 * de piocher une couleur sans la saisir à la main ; la pastille affiche la
	 * vraie couleur côté client (formulaire de commande).
	 *
	 * @return array Liste de [ nom, hex ].
	 */
	protected function color_palette() {
		// 🚨 VOLONTAIREMENT SANS `__()`. Ces libellés ne sont pas du chrome : le clic sur
		// une pastille les ÉCRIT dans le nom de la valeur, qui est la CLÉ de matching
		// serveur (cf. AOD_COD_Form::match_option()). Traduits par gettext, ils
		// déposaient de l'arabe dans cette clé dès que /gestion était affiché en arabe —
		// le cookie `aod_lang` étant partagé avec le public. C'est le diagnostic de la
		// Phase 7 en miniature : gettext employé à produire du CONTENU.
		// → `fr` est canonique (la clé), `ar` est la traduction (pré-remplissage 7.4).
		return array(
			array( 'fr' => 'Noir', 'ar' => 'أسود', 'hex' => '#1a1a1a' ),
			array( 'fr' => 'Blanc', 'ar' => 'أبيض', 'hex' => '#ffffff' ),
			array( 'fr' => 'Gris', 'ar' => 'رمادي', 'hex' => '#9ca3af' ),
			array( 'fr' => 'Gris clair', 'ar' => 'رمادي فاتح', 'hex' => '#d1d5db' ),
			array( 'fr' => 'Gris foncé', 'ar' => 'رمادي غامق', 'hex' => '#4b5563' ),
			array( 'fr' => 'Beige', 'ar' => 'بيج', 'hex' => '#e8d9b5' ),
			array( 'fr' => 'Crème', 'ar' => 'كريمي', 'hex' => '#f7f1de' ),
			array( 'fr' => 'Marron', 'ar' => 'بنّي', 'hex' => '#7b4a2b' ),
			array( 'fr' => 'Bordeaux', 'ar' => 'خمري', 'hex' => '#5c1a2b' ),
			array( 'fr' => 'Rouge', 'ar' => 'أحمر', 'hex' => '#e02b2b' ),
			array( 'fr' => 'Rouge foncé', 'ar' => 'أحمر غامق', 'hex' => '#9b1c1c' ),
			array( 'fr' => 'Rose', 'ar' => 'وردي', 'hex' => '#f48fb1' ),
			array( 'fr' => 'Fuchsia', 'ar' => 'فوشيا', 'hex' => '#d6336c' ),
			array( 'fr' => 'Corail', 'ar' => 'مرجاني', 'hex' => '#ff6f61' ),
			array( 'fr' => 'Orange', 'ar' => 'برتقالي', 'hex' => '#f97316' ),
			array( 'fr' => 'Moutarde', 'ar' => 'خردلي', 'hex' => '#c99700' ),
			array( 'fr' => 'Jaune', 'ar' => 'أصفر', 'hex' => '#f5c518' ),
			array( 'fr' => 'Or', 'ar' => 'ذهبي', 'hex' => '#d4af37' ),
			array( 'fr' => 'Vert clair', 'ar' => 'أخضر فاتح', 'hex' => '#8bc34a' ),
			array( 'fr' => 'Vert', 'ar' => 'أخضر', 'hex' => '#2e9e4f' ),
			array( 'fr' => 'Vert foncé', 'ar' => 'أخضر غامق', 'hex' => '#1b5e20' ),
			array( 'fr' => 'Kaki', 'ar' => 'كاكي', 'hex' => '#6b6b3a' ),
			array( 'fr' => 'Turquoise', 'ar' => 'فيروزي', 'hex' => '#1abc9c' ),
			array( 'fr' => 'Cyan', 'ar' => 'سماوي', 'hex' => '#06b6d4' ),
			array( 'fr' => 'Bleu ciel', 'ar' => 'أزرق سماوي', 'hex' => '#60a5fa' ),
			array( 'fr' => 'Bleu', 'ar' => 'أزرق', 'hex' => '#2563eb' ),
			array( 'fr' => 'Bleu marine', 'ar' => 'كحلي', 'hex' => '#1e3a5f' ),
			array( 'fr' => 'Mauve', 'ar' => 'بنفسجي فاتح', 'hex' => '#b39ddb' ),
			array( 'fr' => 'Violet', 'ar' => 'بنفسجي', 'hex' => '#7c3aed' ),
			array( 'fr' => 'Argent', 'ar' => 'فضّي', 'hex' => '#c0c0c0' ),
		);
	}

	/**
	 * Valide un code couleur hexadécimal #RRGGBB. Renvoie '' si invalide.
	 *
	 * @param string $raw
	 * @return string
	 */
	protected function sanitize_hex( $raw ) {
		$raw = trim( (string) $raw );
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $raw ) ? strtolower( $raw ) : '';
	}

	/**
	 * Popover de la palette de couleurs prédéfinies, rendu une seule fois dans
	 * le formulaire produit. Ouvert via les pastilles 🎨 des valeurs (JS).
	 */
	protected function render_color_palette_popover() {
		?>
		<div class="aod-cd-palette" id="aod-cd-palette" hidden>
			<div class="aod-cd-palette-grid">
				<?php
				// Le gérant lit la palette dans SA langue ; mais le clic écrit le FR
				// canonique dans la clé, et l'AR dans le champ de traduction (7.4).
				$pal_ar = class_exists( 'AOD_COD_Data' ) && AOD_COD_Data::is_arabic();
				foreach ( $this->color_palette() as $c ) :
					$cname = $pal_ar && '' !== $c['ar'] ? $c['ar'] : $c['fr'];
					$chex  = $c['hex'];
					?>
					<button type="button" class="aod-cd-palette-sw" data-name="<?php echo esc_attr( $c['fr'] ); ?>" data-name-ar="<?php echo esc_attr( $c['ar'] ); ?>" data-hex="<?php echo esc_attr( $chex ); ?>" title="<?php echo esc_attr( $cname ); ?>">
						<span class="aod-cd-palette-dot" style="background-color:<?php echo esc_attr( $chex ); ?>"></span>
						<span class="aod-cd-palette-lbl"><?php echo esc_html( $cname ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Une ligne de palier de prix par quantité.
	 *
	 * @param int|string $i   Index de la ligne (ou « __i__ » pour le gabarit JS).
	 * @param array      $row min, price.
	 */
	/**
	 * Offres d'un produit (N unités à prix de lot), pour l'édition.
	 *
	 * Lit la méta `_aod_offers` ; à défaut, migre l'ancienne méta `_aod_qty_tiers`
	 * (prix par pièce → prix total du lot). La carte « 1 produit » est implicite et
	 * n'est jamais stockée ici.
	 *
	 * @param WC_Product|null $product
	 * @return array Liste de [ 'qty' => int, 'price' => float (total du lot) ], triée par qty.
	 */
	public function get_product_offers( $product ) {
		if ( ! $product ) {
			return array();
		}
		$offers = array();
		$raw    = $product->get_meta( '_aod_offers' );
		if ( is_array( $raw ) && $raw ) {
			foreach ( $raw as $o ) {
				$qty   = isset( $o['qty'] ) ? (int) $o['qty'] : 0;
				$price = isset( $o['price'] ) ? (float) $o['price'] : 0;
				if ( $qty >= 2 && $price > 0 ) {
					$offers[] = array( 'qty' => $qty, 'price' => $price );
				}
			}
			if ( $offers ) {
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
	 * Une ligne d'offre (nombre d'unités + prix total du lot).
	 *
	 * @param int|string $i   Index (ou « __i__ » pour le gabarit JS).
	 * @param array      $row qty, price (total du lot).
	 */
	protected function render_offer_row( $i, $row ) {
		$row = wp_parse_args( $row, array( 'qty' => '', 'price' => '' ) );
		$idx = esc_attr( (string) $i );
		?>
		<div class="aod-cd-offer-row" data-row="<?php echo $idx; ?>">
			<input type="number" min="2" step="1" name="offer_qty[<?php echo $idx; ?>]" value="<?php echo esc_attr( (string) $row['qty'] ); ?>" placeholder="<?php esc_attr_e( 'ex : 2', 'aod-client-dashboard' ); ?>">
			<input type="number" min="0" step="0.01" name="offer_price[<?php echo $idx; ?>]" value="<?php echo esc_attr( (string) $row['price'] ); ?>" placeholder="<?php esc_attr_e( 'ex : 2500 (pour 2)', 'aod-client-dashboard' ); ?>">
			<button type="button" class="aod-cd-color-del aod-cd-offer-del" aria-label="<?php esc_attr_e( 'Supprimer cette offre', 'aod-client-dashboard' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Une zone « un par ligne » → une liste propre.
	 *
	 * Les lignes vides sont écartées : elles produiraient des puces vides à l'écran.
	 * Factorisé depuis la 7.3 — le FR et l'AR des arguments de vente subissent
	 * exactement le même traitement, et deux copies dériveraient.
	 *
	 * @param mixed $raw Contenu brut du textarea (déjà `wp_unslash`).
	 * @return string[]
	 */
	protected static function split_lines( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $l ) {
			$l = sanitize_text_field( trim( $l ) );
			if ( '' !== $l ) {
				$out[] = $l;
			}
		}
		return $out;
	}

	/* ============================================================
	 * AJAX : enregistrer (créer / mettre à jour) un produit
	 * ========================================================== */

	public function ajax_save_product() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		// Défense en profondeur sur un produit EXISTANT : la capability globale
		// `edit_products` ne dit rien de CE produit-là. Même patron que Stock et
		// Page de vente (nonce → cap globale → cap par ID → périmètre) : latent
		// aujourd'hui (aucun confirmateur ne reçoit `edit_products`), mais c'est
		// l'invariant du reste du code, et il doit tenir sans dépendre de ça.
		if ( $pid ) {
			if ( ! current_user_can( 'edit_product', $pid ) ) {
				wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
			}
			$scope = AOD_CD_Roles::assigned_products();
			if ( $scope && ! in_array( $pid, $scope, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
			}
		}

		// Titre et description arrivent en COUPLE { fr, ar } depuis la 7.2.
		// Défensif : une page laissée ouverte avant le déploiement poste encore une
		// chaîne nue. La traiter comme « FR = la chaîne, AR = vide » plutôt que de
		// passer un tableau à sanitize_text_field() — qui viderait le nom et
		// renommerait le produit en chaîne vide.
		$name_pair = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '';
		$desc_pair = isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '';
		$name      = sanitize_text_field( is_array( $name_pair ) ? ( isset( $name_pair['fr'] ) ? $name_pair['fr'] : '' ) : $name_pair );
		$name_ar   = sanitize_text_field( is_array( $name_pair ) && isset( $name_pair['ar'] ) ? $name_pair['ar'] : '' );
		// La description AR est nettoyée comme la FR (wp_kses_post) : la passer à
		// sanitize_textarea_field() lui retirerait le HTML et l'arabe s'afficherait
		// plus pauvre que le français dans le même onglet.
		$desc      = wp_kses_post( is_array( $desc_pair ) ? ( isset( $desc_pair['fr'] ) ? $desc_pair['fr'] : '' ) : $desc_pair );
		$desc_ar   = wp_kses_post( is_array( $desc_pair ) && isset( $desc_pair['ar'] ) ? $desc_pair['ar'] : '' );

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Le nom du produit est obligatoire.', 'aod-client-dashboard' ) ), 400 );
		}

		// Sections d'options (Taille, Couleur…) postées. Au moins une section valide → variantes.
		$options = $this->collect_options();

		$product = $pid ? wc_get_product( $pid ) : new WC_Product_Simple();
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Produit introuvable.', 'aod-client-dashboard' ) ), 404 );
		}

		// Le cœur ne reçoit QUE le français : c'est la source canonique, celle qui
		// sert au matching serveur (lignes de commande, bordereaux transporteur).
		$product->set_name( $name );
		$product->set_description( $desc );

		// L'arabe vit à côté, en méta parallèle. Non vide → on écrit ; vide → on
		// SUPPRIME (jamais de méta vide en base : c'est ce qui garde vrai la règle
		// « absence de _ar = repli FR »).
		foreach ( array(
			'_aod_title_ar' => $name_ar,
			'_aod_desc_ar'  => $desc_ar,
		) as $meta_key => $meta_val ) {
			if ( '' !== trim( (string) $meta_val ) ) {
				$product->update_meta_data( $meta_key, $meta_val );
			} else {
				$product->delete_meta_data( $meta_key );
			}
		}

		$reg  = isset( $_POST['regular_price'] ) ? wc_format_decimal( wp_unslash( $_POST['regular_price'] ) ) : '';
		$sale = isset( $_POST['sale_price'] ) ? wc_format_decimal( wp_unslash( $_POST['sale_price'] ) ) : '';

		// Garde-fou : le prix promo ne peut pas dépasser le prix normal.
		if ( '' !== $sale && '' !== $reg && (float) $sale > (float) $reg ) {
			wp_send_json_error( array( 'message' => __( 'Le prix promo doit être inférieur ou égal au prix normal.', 'aod-client-dashboard' ) ), 400 );
		}

		// SKU (peut lever une exception si doublon).
		if ( isset( $_POST['sku'] ) ) {
			$sku = sanitize_text_field( wp_unslash( $_POST['sku'] ) );
			try {
				$product->set_sku( $sku );
			} catch ( Exception $e ) {
				wp_send_json_error( array( 'message' => __( 'Cette référence (SKU) est déjà utilisée.', 'aod-client-dashboard' ) ), 400 );
			}
		}

		$status = ( isset( $_POST['status'] ) && 'draft' === $_POST['status'] ) ? 'draft' : 'publish';
		$product->set_status( $status );

		// Catégories : sélection multiple + éventuelle nouvelle.
		$cat_ids = array();
		if ( ! empty( $_POST['category'] ) ) {
			foreach ( (array) $_POST['category'] as $c ) {
				$cat_ids[] = absint( $c );
			}
		}
		if ( ! empty( $_POST['new_category'] ) ) {
			$new = sanitize_text_field( wp_unslash( $_POST['new_category'] ) );
			$term = term_exists( $new, 'product_cat' );
			if ( ! $term ) {
				$term = wp_insert_term( $new, 'product_cat' );
			}
			if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
				$cat_ids[] = (int) $term['term_id'];
			}
		}
		// Toujours appliquer (permet aussi de tout retirer si rien n'est sélectionné).
		$product->set_category_ids( array_values( array_unique( array_filter( $cat_ids ) ) ) );

		// Poids (expédition) + prix d'achat (marge) + arguments de vente.
		if ( isset( $_POST['weight'] ) ) {
			$w = wc_format_decimal( wp_unslash( $_POST['weight'] ) );
			$product->set_weight( '' !== $w ? $w : '' );
		}
		if ( isset( $_POST['cost_price'] ) ) {
			$cost = wc_format_decimal( wp_unslash( $_POST['cost_price'] ) );
			if ( '' !== $cost ) {
				$product->update_meta_data( '_aod_cost_price', $cost );
			} else {
				$product->delete_meta_data( '_aod_cost_price' );
			}
		}
		if ( isset( $_POST['selling_points'] ) ) {
			// Couple { fr, ar } depuis la 7.3 — même lecture défensive que le titre :
			// une page ouverte avant le déploiement poste encore une chaîne nue.
			$sp_pair = wp_unslash( $_POST['selling_points'] );
			$points  = self::split_lines( is_array( $sp_pair ) ? ( isset( $sp_pair['fr'] ) ? $sp_pair['fr'] : '' ) : $sp_pair );
			// La liste arabe est PARALLÈLE : appariée par rang, jamais fusionnée avec
			// la française (qui reste la source canonique).
			$points_ar = self::split_lines( is_array( $sp_pair ) && isset( $sp_pair['ar'] ) ? $sp_pair['ar'] : '' );
			if ( $points_ar ) {
				$product->update_meta_data( '_aod_selling_points_ar', $points_ar );
			} else {
				$product->delete_meta_data( '_aod_selling_points_ar' );
			}
			if ( $points ) {
				$product->update_meta_data( '_aod_selling_points', $points );
			} else {
				$product->delete_meta_data( '_aod_selling_points' );
			}
		}

		// Programmation de la promo (dates de début / fin).
		$product->set_date_on_sale_from( ! empty( $_POST['sale_from'] ) ? sanitize_text_field( wp_unslash( $_POST['sale_from'] ) ) : '' );
		$product->set_date_on_sale_to( ! empty( $_POST['sale_to'] ) ? sanitize_text_field( wp_unslash( $_POST['sale_to'] ) ) : '' );

		// Produit toujours simple désormais : prix + stock classiques.
		$product->set_regular_price( $reg );
		$product->set_sale_price( '' !== $sale ? $sale : '' );
		$manage = ! empty( $_POST['manage_stock'] );
		$product->set_manage_stock( $manage );
		if ( $manage ) {
			$qty = isset( $_POST['stock_quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['stock_quantity'] ) ) : 0;
			$product->set_stock_quantity( $qty );
			$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			$low = isset( $_POST['low_stock_amount'] ) ? trim( (string) wp_unslash( $_POST['low_stock_amount'] ) ) : '';
			$product->set_low_stock_amount( '' !== $low ? wc_stock_amount( $low ) : '' );
		} else {
			$product->set_low_stock_amount( '' );
		}

		// Libellé d'axe mono-variante : obsolète (remplacé par les sections d'options).
		$product->delete_meta_data( '_aod_variant_label' );

		if ( ! $options ) {
			$product->delete_meta_data( '_aod_options' );
		}

		// Pack « produits différents » : éditeur retiré (fusionné dans les Offres).
		// On nettoie les métas legacy pour migrer proprement les anciens produits.
		$product->delete_meta_data( '_aod_is_pack' );
		$product->delete_meta_data( '_aod_pack_items' );

		// Offres (prix par quantité) : N unités de ce produit à prix de lot — compatibles
		// avec les sections d'options. Référence = PRIX NORMAL (jamais la promo) : une offre
		// est une remise sur le prix habituel, pas sur une promo temporaire. Sinon un produit
		// en promo ferait disparaître des offres pourtant avantageuses par rapport au prix normal.
		$base           = (float) $reg;
		$dropped_offers = 0;
		$offers         = $this->collect_offers( $base, $dropped_offers );
		if ( $offers ) {
			$product->update_meta_data( '_aod_offers', $offers );
		} else {
			$product->delete_meta_data( '_aod_offers' );
		}
		// Legacy migré vers `_aod_offers` : on retire l'ancienne méta paliers.
		$product->delete_meta_data( '_aod_qty_tiers' );

		$product->save();
		$new_id = $product->get_id();

		// Produit toujours simple désormais : on convertit d'anciens produits variables
		// (suppression des variations + attributs) avant de basculer le type.
		if ( ! $product->is_type( 'simple' ) ) {
			foreach ( $product->get_children() as $cid ) {
				$old = wc_get_product( $cid );
				if ( $old ) {
					$old->delete( true );
				}
			}
			$product->set_attributes( array() );
			$product->save();
			wp_set_object_terms( $new_id, 'simple', 'product_type' );
			$product = wc_get_product( $new_id );
		}

		// Refus d'image : COMPTÉS, jamais bloquants (47.6). Ils remontent tous dans
		// le même avertissement, plus bas.
		//
		// 🚨 DÉCLARÉS ICI, ET C'EST LE CORRECTIF. La photo principale, elle, sortait
		// en `wp_send_json_error()` — donc `wp_die()` — AVANT la galerie et avant
		// l'écriture de `_aod_options`. Tout le reste de la soumission était jeté en
		// silence : retraits et ajouts de galerie, renommage des variantes, photos
		// d'options. Le gérant lisait « échec de l'image », remettait une photo plus
		// légère, et ne saurait jamais que ses autres modifications n'ont pas été
		// enregistrées — alors que titre, prix et stock l'avaient été juste avant.
		// Un produit à moitié enregistré, sans un mot.
		//
		// ⚠️ La 47.6 avait fait exactement ce travail pour la galerie et les options
		// (compteurs par référence), et n'a pas propagé jusqu'ici — c'est le même
		// jumeau manquant que la Phase 44 a corrigé trois fois. Avant la 47.6 le
		// chemin ne s'atteignait qu'avec un mauvais type MIME ; la borne de taille en
		// fait l'ordinaire d'une photo prise au téléphone.
		$img_refusees = 0;
		$img_motif    = '';

		// Photo principale (upload optionnel) — sert d'image par défaut.
		if ( ! empty( $_FILES['image']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$att_id = AOD_CD_Upload::handle( 'image', $new_id );
			if ( is_wp_error( $att_id ) ) {
				++$img_refusees;
				if ( '' === $img_motif ) {
					$img_motif = $att_id->get_error_message();
				}
			} else {
				set_post_thumbnail( $new_id, $att_id );
			}
		}

		// Galerie : retrait des photos cochées + ajout des nouvelles.
		$this->save_gallery( $product, $new_id, $img_refusees, $img_motif );

		// Sections d'options : upload des photos de valeurs puis enregistrement de la méta.
		if ( $options ) {
			// MÊMES compteurs : galerie et photos d'options s'additionnent, le
			// gérant n'a pas à savoir laquelle des deux zones a refusé combien.
			$options = $this->upload_option_images( $options, $new_id, $img_refusees, $img_motif );
			$product->update_meta_data( '_aod_options', $this->normalize_options( $options ) );
			$product->save();
		}

		$data = array(
			'message'  => $pid ? __( 'Produit mis à jour.', 'aod-client-dashboard' ) : __( 'Produit créé.', 'aod-client-dashboard' ),
			'redirect' => $this->products_url(),
		);
		$avertissement = $this->build_warnings( $dropped_offers, $img_refusees, $img_motif );
		if ( '' !== $avertissement ) {
			$data['warning'] = $avertissement;
		}
		wp_send_json_success( $data );
	}

	/**
	 * Rassemble les sections d'options postées (sections et valeurs valides uniquement).
	 *
	 * Conserve les index postés (clés associatives) afin que l'upload des photos
	 * puisse retrouver le bon champ fichier opt_img[si][vi]. La normalisation en
	 * liste séquentielle est faite plus tard par normalize_options().
	 *
	 * @return array
	 */
	protected function collect_options() {
		if ( empty( $_POST['opt_label'] ) || ! is_array( $_POST['opt_label'] ) ) {
			return array();
		}
		$labels = wp_unslash( $_POST['opt_label'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$out    = array();
		foreach ( $labels as $si => $raw_label ) {
			$label    = sanitize_text_field( trim( (string) $raw_label ) );
			// 7.4 — la traduction est lue PAR CLÉ, à l'intérieur de la boucle FR : c'est
			// le FR qui pilote la structure. Jamais de boucle sur les tableaux `_ar` (une
			// section AR orpheline disparaît avec son FR, sans bruit).
			$label_ar = isset( $_POST['opt_label_ar'][ $si ] ) ? sanitize_text_field( trim( (string) wp_unslash( $_POST['opt_label_ar'][ $si ] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$visual   = ! empty( $_POST['opt_visual'][ $si ] );
			$dropdown = ! empty( $_POST['opt_dropdown'][ $si ] );
			$names  = ( isset( $_POST['opt_value_name'][ $si ] ) && is_array( $_POST['opt_value_name'][ $si ] ) )
				? wp_unslash( $_POST['opt_value_name'][ $si ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				: array();
			$values = array();
			foreach ( $names as $vi => $raw_name ) {
				$name = sanitize_text_field( trim( (string) $raw_name ) );
				if ( '' === $name ) {
					continue;
				}
				$price = isset( $_POST['opt_value_price'][ $si ][ $vi ] ) ? wc_format_decimal( wp_unslash( $_POST['opt_value_price'][ $si ][ $vi ] ) ) : '';
				if ( '' !== $price && (float) $price <= 0 ) {
					$price = '';
				}
				$imgid = ( $visual && isset( $_POST['opt_value_imgid'][ $si ][ $vi ] ) ) ? absint( $_POST['opt_value_imgid'][ $si ][ $vi ] ) : 0;
				$hex   = isset( $_POST['opt_value_hex'][ $si ][ $vi ] ) ? $this->sanitize_hex( (string) wp_unslash( $_POST['opt_value_hex'][ $si ][ $vi ] ) ) : '';
				// 7.4 — par clé, dans la boucle des noms FR (cf. commentaire plus haut).
				$name_ar       = isset( $_POST['opt_value_name_ar'][ $si ][ $vi ] ) ? sanitize_text_field( trim( (string) wp_unslash( $_POST['opt_value_name_ar'][ $si ][ $vi ] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				// 40.1 — stock par valeur : '' = NON géré (défaut, illimité), entier ≥ 0
				// = géré (0 = rupture). On distingue TOUJOURS '' de 0. Un champ vide ou
				// non numérique = non géré ; un négatif est borné à 0.
				$stock_in = isset( $_POST['opt_value_stock'][ $si ][ $vi ] ) ? sanitize_text_field( wp_unslash( $_POST['opt_value_stock'][ $si ][ $vi ] ) ) : '';
				$stock    = ( '' !== $stock_in && is_numeric( $stock_in ) ) ? (string) max( 0, (int) $stock_in ) : '';
				$values[ $vi ] = array(
					'name'     => $name,
					'name_ar'  => $name_ar,
					'price'    => $price,
					'image_id' => $imgid,
					'hex'      => $hex,
					'stock'    => $stock,
				);
			}
			if ( '' === $label || ! $values ) {
				continue;
			}
			$out[ $si ] = array(
				'label'    => $label,
				'label_ar' => $label_ar,
				'visual'  => $visual,
				'display' => $dropdown ? 'dropdown' : 'cards',
				'values'  => $values,
			);
		}
		return $out;
	}

	/**
	 * Assemble l'avertissement d'un enregistrement de produit.
	 *
	 * 🚨 `warning` EST UNE CHAÎNE UNIQUE, et deux avertissements peuvent survenir
	 * dans le même enregistrement : des offres ignorées ET des images refusées.
	 * Les affecter l'un après l'autre ferait taire le premier — on aurait déplacé
	 * le défaut muet d'un cran au lieu de le fermer. D'où l'assemblage ici, dans
	 * une méthode qu'un cliquet peut appeler : la jonction est le point exact où
	 * une future troisième cause se perdrait en silence.
	 *
	 * @param int    $dropped_offers Offres écartées par la règle de prix.
	 * @param int    $img_refusees   Images refusées (galerie + photos d'options).
	 * @param string $img_motif      Premier motif de refus, déjà traduit.
	 * @return string Vide si rien à signaler.
	 */
	protected function build_warnings( $dropped_offers, $img_refusees, $img_motif ) {
		$avertissements = array();
		if ( $dropped_offers > 0 ) {
			$avertissements[] = sprintf(
				/* translators: %d: nombre d'offres ignorées */
				_n(
					'%d offre a été ignorée : le prix total du lot doit être INFÉRIEUR au prix normal multiplié par le nombre d’unités (sinon l’offre n’apporte aucune réduction).',
					'%d offres ont été ignorées : le prix total du lot doit être INFÉRIEUR au prix normal multiplié par le nombre d’unités (sinon l’offre n’apporte aucune réduction).',
					$dropped_offers,
					'aod-client-dashboard'
				),
				$dropped_offers
			);
		}
		if ( $img_refusees > 0 ) {
			$avertissements[] = sprintf(
				/* translators: 1: nombre d'images refusées, 2: motif du refus (déjà traduit). */
				_n(
					'%1$d image n’a pas pu être ajoutée : %2$s',
					'%1$d images n’ont pas pu être ajoutées : %2$s',
					$img_refusees,
					'aod-client-dashboard'
				),
				$img_refusees,
				$img_motif
			);
		}
		return implode( ' ', $avertissements );
	}

	/**
	 * Upload des photos de valeurs (champ fichier opt_img[si][vi]) et fusion des
	 * identifiants d'attachement dans les sections. Conserve l'image existante si
	 * aucun nouveau fichier n'est fourni.
	 *
	 * 🚨 CE SITE ÉTAIT MUET (corrigé en 47.6). Il testait `! is_wp_error()` sans
	 * `else` : une photo refusée — mauvais format hier, trop lourde depuis la
	 * borne de la 47.6 — disparaissait SANS UN MOT. Le gérant enregistrait, la
	 * page confirmait « Produit mis à jour », et l'image n'était nulle part. Le
	 * refus n'est utile que s'il est DIT ; ajouter une borne sans rebrancher ce
	 * message aurait fabriqué un défaut muet de plus.
	 *
	 * @param array  $options  Sections issues de collect_options() (clés postées).
	 * @param int    $pid      Produit parent (pour rattacher les médias).
	 * @param int    $refusees Compteur CUMULATIF de refus (par référence).
	 * @param string $motif    Premier motif rencontré (par référence) — patron
	 *                         `AOD_CD_Section_Landing::upload_one()`.
	 * @return array
	 */
	protected function upload_option_images( $options, $pid, &$refusees = 0, &$motif = '' ) {
		if ( empty( $_FILES['opt_img']['name'] ) || ! is_array( $_FILES['opt_img']['name'] ) ) {
			return $options;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$files = $_FILES['opt_img'];
		foreach ( $options as $si => $section ) {
			if ( empty( $section['visual'] ) ) {
				continue;
			}
			foreach ( $section['values'] as $vi => $value ) {
				if ( empty( $files['name'][ $si ][ $vi ] ) ) {
					continue;
				}
				$_FILES['opt_img_single'] = array(
					'name'     => $files['name'][ $si ][ $vi ],
					'type'     => $files['type'][ $si ][ $vi ],
					'tmp_name' => $files['tmp_name'][ $si ][ $vi ],
					'error'    => $files['error'][ $si ][ $vi ],
					'size'     => $files['size'][ $si ][ $vi ],
				);
				$att = AOD_CD_Upload::handle( 'opt_img_single', $pid );
				if ( ! is_wp_error( $att ) ) {
					$options[ $si ]['values'][ $vi ]['image_id'] = (int) $att;
				} else {
					++$refusees;
					if ( '' === $motif ) {
						$motif = $att->get_error_message();
					}
				}
			}
		}
		unset( $_FILES['opt_img_single'] );
		return $options;
	}

	/**
	 * Réindexe les sections/valeurs en listes séquentielles et nettoie les champs
	 * pour le stockage en méta `_aod_options`.
	 *
	 * @param array $options
	 * @return array
	 */
	protected function normalize_options( $options ) {
		$out = array();
		foreach ( $options as $section ) {
			$values = array();
			foreach ( $section['values'] as $value ) {
				$values[] = array(
					'name'     => (string) $value['name'],
					// 7.4 — la traduction vit DANS la valeur : l'appariement est structurel,
					// donc impossible à désaligner (supprimer la valeur emporte sa traduction).
					'name_ar'  => isset( $value['name_ar'] ) ? (string) $value['name_ar'] : '',
					'price'    => ( isset( $value['price'] ) && '' !== $value['price'] ) ? (string) $value['price'] : '',
					'image_id' => isset( $value['image_id'] ) ? (int) $value['image_id'] : 0,
					'hex'      => isset( $value['hex'] ) ? $this->sanitize_hex( (string) $value['hex'] ) : '',
					// 40.1 — stock par valeur : '' = non géré (illimité), entier ≥ 0 = géré.
					// Ne JAMAIS confondre '' et 0. Le repli est structurel : une valeur sans
					// clé `stock` (produit d'avant la 40.1) devient '' — comportement inchangé.
					'stock'    => ( isset( $value['stock'] ) && '' !== $value['stock'] ) ? (string) max( 0, (int) $value['stock'] ) : '',
				);
			}
			if ( ! $values ) {
				continue;
			}
			$out[] = array(
				'label'    => (string) $section['label'],
				'label_ar' => isset( $section['label_ar'] ) ? (string) $section['label_ar'] : '',
				'visual'  => ! empty( $section['visual'] ),
				'display' => ( isset( $section['display'] ) && 'dropdown' === $section['display'] ) ? 'dropdown' : 'cards',
				'values'  => $values,
			);
		}

		// 40.1 — Règle MONO-AXE (D1bis), imposée ICI, côté serveur (source de vérité :
		// l'UI n'en est qu'un écho de confort). Un seul axe peut porter un stock, sinon
		// le décrément à la confirmation (40.5) serait ambigu. On retient la PREMIÈRE
		// section (ordre d'affichage) contenant une valeur à stock non vide ; toute
		// autre section voit ses stocks effacés.
		$stock_axis = -1;
		foreach ( $out as $si => $section ) {
			foreach ( $section['values'] as $value ) {
				if ( '' !== $value['stock'] ) {
					$stock_axis = $si;
					break 2;
				}
			}
		}
		if ( $stock_axis >= 0 ) {
			foreach ( $out as $si => $section ) {
				if ( $si === $stock_axis ) {
					continue;
				}
				foreach ( $out[ $si ]['values'] as $vi => $value ) {
					$out[ $si ]['values'][ $vi ]['stock'] = '';
				}
			}
		}

		return $out;
	}

	/**
	 * Rassemble les paliers de prix par quantité postés (lignes valides uniquement).
	 *
	 * Une ligne est retenue si : quantité ≥ 2 et prix > 0. Le prix unitaire doit
	 * être inférieur au prix de base (sinon le palier n'a pas d'intérêt et est ignoré).
	 * Le résultat est trié par quantité croissante.
	 *
	 * @param float $base_price Prix unitaire de référence (promo sinon normal).
	 * @return array Liste de [ 'min' => int, 'price' => float ].
	 */
	protected function collect_offers( $base_price, &$dropped = 0 ) {
		$dropped = 0; // Nb d'offres renseignées mais écartées faute de réduction réelle.
		if ( empty( $_POST['offer_qty'] ) || ! is_array( $_POST['offer_qty'] ) ) {
			return array();
		}
		$qtys   = wp_unslash( $_POST['offer_qty'] );   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$prices = isset( $_POST['offer_price'] ) ? wp_unslash( $_POST['offer_price'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$offers = array();
		$seen   = array();
		foreach ( $qtys as $i => $raw_qty ) {
			$qty   = absint( $raw_qty );
			// Le champ contient le PRIX TOTAL DU LOT ; on le stocke tel quel.
			$total = isset( $prices[ $i ] ) ? (float) wc_format_decimal( $prices[ $i ] ) : 0;
			if ( $qty < 2 || $total <= 0 ) {
				continue; // Ligne vide ou incomplète : on l'ignore sans alerter.
			}
			if ( $base_price > 0 && $total >= $base_price * $qty ) {
				$dropped++; // Lot pas plus avantageux que l'achat à l'unité : ignoré + alerte.
				continue;
			}
			if ( isset( $seen[ $qty ] ) ) {
				continue; // Doublon de quantité : on garde le premier.
			}
			$seen[ $qty ] = true;
			$offers[]     = array( 'qty' => $qty, 'price' => $total );
		}
		usort( $offers, function ( $a, $b ) {
			return $a['qty'] - $b['qty'];
		} );
		return $offers;
	}

	/**
	 * Met à jour la galerie d'images d'un produit : retire les images cochées,
	 * ajoute les nouveaux fichiers uploadés (champ multiple gallery_images[]).
	 *
	 * 🚨 CE SITE ÉTAIT MUET (corrigé en 47.6), et c'est ici que ça coûtait le
	 * plus cher : la galerie est le SEUL champ où l'on dépose plusieurs photos
	 * d'un coup, donc celui où l'on ne recompte pas ce qui est arrivé. Un
	 * `! is_wp_error()` sans `else` y faisait disparaître les refus en silence.
	 *
	 * @param WC_Product $product
	 * @param int        $new_id
	 * @param int        $refusees Compteur CUMULATIF de refus (par référence).
	 * @param string     $motif    Premier motif rencontré (par référence).
	 */
	protected function save_gallery( $product, $new_id, &$refusees = 0, &$motif = '' ) {
		$gallery = $product->get_gallery_image_ids();

		// Retraits demandés.
		if ( ! empty( $_POST['gallery_remove'] ) && is_array( $_POST['gallery_remove'] ) ) {
			$remove  = array_map( 'absint', wp_unslash( $_POST['gallery_remove'] ) );
			$gallery = array_values( array_diff( $gallery, $remove ) );
		}

		// Ajouts (upload multiple).
		if ( ! empty( $_FILES['gallery_images']['name'][0] ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$files = $_FILES['gallery_images'];
			$count = count( $files['name'] );
			for ( $i = 0; $i < $count; $i++ ) {
				if ( empty( $files['name'][ $i ] ) ) {
					continue;
				}
				$_FILES['gallery_single'] = array(
					'name'     => $files['name'][ $i ],
					'type'     => $files['type'][ $i ],
					'tmp_name' => $files['tmp_name'][ $i ],
					'error'    => $files['error'][ $i ],
					'size'     => $files['size'][ $i ],
				);
				$att = AOD_CD_Upload::handle( 'gallery_single', $new_id );
				if ( ! is_wp_error( $att ) ) {
					$gallery[] = (int) $att;
				} else {
					++$refusees;
					if ( '' === $motif ) {
						$motif = $att->get_error_message();
					}
				}
			}
			unset( $_FILES['gallery_single'] );
		}

		$product->set_gallery_image_ids( array_values( array_unique( array_filter( $gallery ) ) ) );
		$product->save();
	}

	/* ============================================================
	 * AJAX : supprimer (corbeille) un produit
	 * ========================================================== */

	public function ajax_delete_product() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'delete_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$pid     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product = $pid ? wc_get_product( $pid ) : false;
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Produit introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		// Défense en profondeur : droit sur CE produit, puis périmètre (cf. Stock).
		if ( ! current_user_can( 'delete_product', $pid ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$scope = AOD_CD_Roles::assigned_products();
		if ( $scope && ! in_array( $pid, $scope, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$product->delete( false ); // false = corbeille (réversible), pas suppression définitive.
		wp_send_json_success( array( 'message' => __( 'Produit déplacé dans la corbeille.', 'aod-client-dashboard' ) ) );
	}

	/**
	 * AJAX : duplique un produit (demande owner du 2026-08-04).
	 *
	 * Pourquoi : une boutique décline le même article (tailles, coloris, packs).
	 * Ressaisir offres, options, paliers de quantité et couple `{fr, ar}` à chaque
	 * variante est le geste le plus long de `/gestion` — et le plus propice à
	 * l'oubli d'un champ arabe, qui ne se voit qu'en passant le site en AR.
	 *
	 * 🚨 ON DÉLÈGUE À WOOCOMMERCE, on ne réécrit pas la copie. `product_duplicate()`
	 * recopie les VARIATIONS, toutes les métas (donc nos `_aod_*` : offres, options,
	 * points de vente, page de vente…) et les taxonomies. Une copie maison aurait
	 * couvert les champs auxquels on pense le jour où on l'écrit, et aurait
	 * silencieusement perdu les suivants — exactement le mode de dérive que les
	 * Phases 44 et 47 documentent (« un bon motif qui n'atteint pas son jumeau »).
	 * Le fichier n'est chargé qu'ici : il vit dans `includes/admin/` de Woo, que le
	 * front n'inclut pas.
	 *
	 * La copie naît en BROUILLON (comportement de Woo, volontairement conservé) :
	 * un doublon publié par accident, c'est deux fiches concurrentes en boutique.
	 *
	 * @return void
	 */
	public function ajax_duplicate_product() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$pid     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product = $pid ? wc_get_product( $pid ) : false;
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Produit introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		// Défense en profondeur, au même rang que la suppression juste au-dessus :
		// droit sur CE produit, puis périmètre produits du confirmateur.
		if ( ! current_user_can( 'edit_product', $pid ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$scope = AOD_CD_Roles::assigned_products();
		if ( $scope && ! in_array( $pid, $scope, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) ) {
			$file = defined( 'WC_ABSPATH' ) ? WC_ABSPATH . 'includes/admin/class-wc-admin-duplicate-product.php' : '';
			if ( $file && is_readable( $file ) ) {
				require_once $file;
			}
		}
		if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) ) {
			wp_send_json_error(
				array( 'message' => __( "La duplication n'est pas disponible sur cette installation.", 'aod-client-dashboard' ) ),
				500
			);
		}

		$copy = ( new WC_Admin_Duplicate_Product() )->product_duplicate( $product );

		if ( ! $copy || ! $copy->get_id() ) {
			wp_send_json_error( array( 'message' => __( 'La duplication a échoué.', 'aod-client-dashboard' ) ), 500 );
		}

		// TITRE FR — on le RÉÉCRIT, sans quoi la copie s'appelle « … (Copy) », en
		// anglais, sur une boutique française. Woo écrit ce suffixe EN DUR :
		//   $duplicate->set_name( sprintf( esc_html__( '%s (Copy)', 'woocommerce' ), … ) )
		// 🚨 Il n'existe AUCUN filtre pour l'infléchir. Une première version de ce
		// code posait un `add_filter( 'woocommerce_duplicate_product_suffix', … )` :
		// ce point d'extension n'existe pas, le filtre ne s'accrochait à rien, et le
		// résultat — « (Copy) » en français, « (copie) » en arabe — n'a été vu qu'en
		// comparant les deux fiches en base. *Un filtre posé sur un hook imaginaire
		// ne lève aucune erreur : il donne l'apparence d'un traitement.*
		// On repart du nom de l'ORIGINAL, pas de celui de la copie, qui porte déjà
		// le suffixe anglais.
		$copy->set_name( trim( $product->get_name() . ' ' . __( '(copie)', 'aod-client-dashboard' ) ) );
		$copy->save();

		// ⚠️ Le titre WordPress ne couvre QUE le français. Sans les lignes qui
		// suivent, l'original et sa copie portent le MÊME titre arabe :
		// indiscernables pour un gérant qui travaille en AR, et le défaut ne se voit
		// pas en français. Cf. TEMPLATE.md §5 (couple `{fr, ar}`).
		// ⚠️ LIMITE ASSUMÉE : `__()` rend la langue du GÉRANT, pas l'arabe. Un gérant
		// qui travaille en français écrit donc « (copie) » — un mot français — dans
		// un champ arabe. C'est borné et visible : la copie est un brouillon, et le
		// premier geste est de la renommer. `switch_to_locale( 'ar' )` le règlerait,
		// au prix d'un chargement de catalogue pour un suffixe ; à faire seulement si
		// un gérant publie des copies sans les renommer.
		$title_ar = get_post_meta( $pid, '_aod_title_ar', true );
		if ( '' !== trim( (string) $title_ar ) ) {
			update_post_meta( $copy->get_id(), '_aod_title_ar', trim( $title_ar . ' ' . __( '(copie)', 'aod-client-dashboard' ) ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Produit dupliqué. La copie est un brouillon : relis-la avant de la publier.', 'aod-client-dashboard' ),
				'edit_url' => $this->products_url( array( 'action' => 'edit', 'product' => $copy->get_id() ) ),
			)
		);
	}

	/* ============================================================
	 * AJAX : catégories de produits (créer / renommer / supprimer)
	 * ========================================================== */

	/**
	 * Décide quoi faire des métas « accueil » (9.1) selon la requête — logique PURE,
	 * extraite pour être prouvée sans nonce ni base (cf. test-category-home.php).
	 *
	 * Patron `parent` (« champ absent = méta laissée intacte »), PAS patron `name_ar`
	 * (« écriture inconditionnelle ») : les enregistrements d'image ne postent NI
	 * `home` NI `home_order`, donc ils ne touchent ni au flag ni à l'ordre. C'est ce
	 * qui rend le piège 7.5-AC12 (changer une image effaçait la valeur) impossible ici.
	 *
	 * `isset()` distingue « présent mais faux » (décochage → delete) de « absent »
	 * (image → leave). Confondre les deux serait le bug.
	 *
	 * @param array $req Sous-ensemble de la requête (clés `home`, `home_order` si présentes).
	 * @return array{home: string, order: ?int} `home` ∈ set|delete|leave ; `order` = entier ≥ 0 ou null.
	 */
	public static function home_meta_ops( array $req ) {
		$home = 'leave';
		if ( isset( $req['home'] ) ) {
			$home = ! empty( $req['home'] ) ? 'set' : 'delete';
		}
		$order = isset( $req['home_order'] ) ? max( 0, (int) $req['home_order'] ) : null;
		return array( 'home' => $home, 'order' => $order );
	}

	/**
	 * Crée (term_id absent ou 0) ou renomme (term_id fourni) une catégorie de
	 * produits. Renvoie l'identifiant, le nom et le compteur à jour.
	 */
	public function ajax_save_category() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Le nom de la catégorie est obligatoire.', 'aod-client-dashboard' ) ), 400 );
		}
		// Le pendant arabe (7.5). Facultatif : exiger l'AR interdirait d'enregistrer une
		// catégorie non traduite. Le nom FR ci-dessus reste le nom CANONIQUE du terme —
		// l'arabe ne le remplace jamais, il vit dans une term-meta parallèle.
		$name_ar = isset( $_POST['name_ar'] ) ? sanitize_text_field( wp_unslash( $_POST['name_ar'] ) ) : '';

		// Catégorie parente : pris en compte uniquement s'il est présent dans la
		// requête (les enregistrements d'image n'envoient pas ce champ → on ne
		// touche pas au parent existant). null = ne pas modifier le parent.
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : null;
		if ( null !== $parent && $term_id && $parent === $term_id ) {
			$parent = 0; // Une catégorie ne peut pas être son propre parent.
		}

		$args = array( 'name' => $name );
		if ( null !== $parent ) {
			$args['parent'] = $parent;
		}
		if ( $term_id ) {
			// WordPress empêche les boucles de hiérarchie (wp_check_term_hierarchy_for_loops).
			$res = wp_update_term( $term_id, 'product_cat', $args );
		} else {
			$res = wp_insert_term( $name, 'product_cat', $args );
		}
		if ( is_wp_error( $res ) ) {
			$msg = $res->get_error_message();
			if ( 'term_exists' === $res->get_error_code() ) {
				$msg = __( 'Cette catégorie existe déjà.', 'aod-client-dashboard' );
			}
			wp_send_json_error( array( 'message' => $msg ), 400 );
		}

		$id   = is_array( $res ) ? (int) $res['term_id'] : (int) $term_id;

		// Le nom arabe (7.5). Non vide → on écrit ; vide → on SUPPRIME la méta. C'est ce
		// qui rend « absence de _ar = repli FR » littéralement vrai : jamais de méta vide
		// en base, donc vider la case efface bien la traduction au lieu de stocker ''.
		if ( '' !== $name_ar ) {
			update_term_meta( $id, '_aod_cat_name_ar', $name_ar );
		} else {
			delete_term_meta( $id, '_aod_cat_name_ar' );
		}

		// Réglage « Afficher sur l'accueil » + ordre (9.1). On ne lit QUE les clés
		// présentes dans la requête : un enregistrement d'image ne les envoie pas, donc
		// il laisse le flag et l'ordre intacts (patron `parent`, cf. home_meta_ops()).
		$home_req = array();
		if ( isset( $_POST['home'] ) ) {
			$home_req['home'] = sanitize_text_field( wp_unslash( $_POST['home'] ) );
		}
		if ( isset( $_POST['home_order'] ) ) {
			$home_req['home_order'] = sanitize_text_field( wp_unslash( $_POST['home_order'] ) );
		}
		$home_ops = self::home_meta_ops( $home_req );
		if ( 'set' === $home_ops['home'] ) {
			update_term_meta( $id, '_aod_cat_home', '1' );
		} elseif ( 'delete' === $home_ops['home'] ) {
			delete_term_meta( $id, '_aod_cat_home' );
		}
		if ( null !== $home_ops['order'] ) {
			update_term_meta( $id, '_aod_cat_home_order', $home_ops['order'] );
		}

		// Image de la catégorie (term meta WooCommerce « thumbnail_id »), affichée
		// dans la section « Nos rayons » de l'accueil via [product_categories].
		$msg_img = '';
		if ( ! empty( $_POST['remove_image'] ) ) {
			$prev = (int) get_term_meta( $id, 'thumbnail_id', true );
			delete_term_meta( $id, 'thumbnail_id' );
			if ( $prev ) { wp_delete_attachment( $prev, true ); }
		} elseif ( ! empty( $_FILES['image']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$att_id = AOD_CD_Upload::handle( 'image', 0 );
			if ( is_wp_error( $att_id ) ) {
				$msg_img = ' ' . __( '(échec de l’image)', 'aod-client-dashboard' );
			} else {
				$prev = (int) get_term_meta( $id, 'thumbnail_id', true );
				update_term_meta( $id, 'thumbnail_id', $att_id );
				if ( $prev && $prev !== $att_id ) { wp_delete_attachment( $prev, true ); }
			}
		}

		$term    = get_term( $id, 'product_cat' );
		$img_id  = (int) get_term_meta( $id, 'thumbnail_id', true );
		$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
		wp_send_json_success( array(
			'message' => ( $term_id ? __( 'Catégorie mise à jour.', 'aod-client-dashboard' ) : __( 'Catégorie créée.', 'aod-client-dashboard' ) ) . $msg_img,
			'id'      => $id,
			// `$term->name` est ici le FR canonique : `get_term()` est filtré par
			// AOD_COD_Catalog_Filters, mais sa garde exige `! is_admin()` — or is_admin()
			// est VRAI sous admin-ajax. Le gérant récupère donc bien le français, même
			// s'il navigue en arabe.
			'name'       => $term && ! is_wp_error( $term ) ? $term->name : $name,
			'name_ar'    => $name_ar,
			'count'      => $term && ! is_wp_error( $term ) ? (int) $term->count : 0,
			'parent'     => $term && ! is_wp_error( $term ) ? (int) $term->parent : 0,
			'img'        => $img_url ? $img_url : '',
			// Réglage accueil (9.1), relu en base pour refléter l'état réel dans la ligne.
			'home'       => '1' === (string) get_term_meta( $id, '_aod_cat_home', true ) ? '1' : '',
			'home_order' => (int) get_term_meta( $id, '_aod_cat_home_order', true ),
		) );
	}

	/**
	 * Supprime une catégorie de produits. Les produits rattachés sont conservés.
	 */
	public function ajax_delete_category() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Catégorie introuvable.', 'aod-client-dashboard' ) ), 400 );
		}
		$res = wp_delete_term( $term_id, 'product_cat' );
		if ( is_wp_error( $res ) || ! $res ) {
			wp_send_json_error( array( 'message' => __( 'Suppression impossible.', 'aod-client-dashboard' ) ), 400 );
		}
		wp_send_json_success( array( 'message' => __( 'Catégorie supprimée.', 'aod-client-dashboard' ) ) );
	}

	/* ============================================================
	 * AJAX : téléverser UNE image pour l'insérer dans la description (8.2)
	 * ========================================================== */

	/**
	 * Cœur COMMUN des deux uploads immédiats de description (image 8.2, vidéo 8.3),
	 * qui ne diffèrent que par la whitelist de mimes et le payload de succès : garde
	 * nonce + capability + fichier présent, les trois `require` WP, puis
	 * `AOD_CD_Upload::handle()`, qui porte la liste blanche (la VRAIE barrière
	 * serveur ; l'`accept` HTML est contournable).
	 *
	 * ⚠️ Les deux listes vivaient ICI depuis la 8.2, en dur. La 32.2 les a déplacées
	 * dans `AOD_CD_Upload` pour que les SIX téléversements du dépôt partagent la
	 * même — sans quoi les cinq autres seraient repartis avec leur propre copie, et
	 * l'écart entre elles serait devenu invisible. Le piège `test_form` que ce
	 * docblock signalait est décrit à la source, là où il se rejoue.
	 *
	 * En cas de refus, envoie directement la réponse d'erreur JSON (et coupe via
	 * `wp_send_json_*`) ; sinon renvoie l'ID de l'attachement à l'appelant, qui bâtit
	 * son propre payload de succès.
	 *
	 * @param string $kind 'image' ou 'video'.
	 * @return int ID de l'attachement créé.
	 */
	private function handle_desc_upload( $kind ) {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		if ( empty( $_FILES['file']['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Aucun fichier reçu.', 'aod-client-dashboard' ) ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Parent = le produit en édition (0 en création : l'attachement existe alors
		// sans parent, comme l'image de catégorie). `product_id` = champ caché du form.
		$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		$att_id = AOD_CD_Upload::handle( 'file', $pid, $kind );
		if ( is_wp_error( $att_id ) ) {
			wp_send_json_error( array( 'message' => $att_id->get_error_message() ), 400 );
		}
		return (int) $att_id;
	}

	/**
	 * Premier upload IMMÉDIAT du plugin : une image insérée au fil du texte a
	 * besoin de son URL AVANT l'enregistrement du produit (les autres uploads du
	 * dashboard sont différés dans le `FormData` du save). Renvoie l'URL de la
	 * taille `large` (repli `full`) pour que le JS insère un `<img>` minimal.
	 */
	public function ajax_desc_image() {
		$att_id = $this->handle_desc_upload( 'image' );

		$src = wp_get_attachment_image_src( $att_id, 'large' );
		if ( ! $src ) {
			$src = wp_get_attachment_image_src( $att_id, 'full' );
		}
		if ( ! $src ) {
			wp_send_json_error( array( 'message' => __( 'Image téléversée mais illisible.', 'aod-client-dashboard' ) ), 500 );
		}

		wp_send_json_success( array(
			'id'     => (int) $att_id,
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		) );
	}

	/* ============================================================
	 * AJAX : téléverser UNE vidéo pour l'insérer dans la description (8.3)
	 * ========================================================== */

	/**
	 * Jumeau de `ajax_desc_image()` pour la vidéo par FICHIER : le gérant téléverse
	 * un MP4 depuis son PC, on renvoie l'URL de l'attachement, et le JS insère un
	 * `<video src>` natif (kses conserve `<video src>` ; il n'y a donc pas de façade
	 * ni de JS côté public pour ce cas — cf. AOD_COD_Desc_Video). La whitelist de
	 * mimes VIDÉO est la vraie barrière serveur.
	 *
	 * ⚠️ Vidéos lourdes : un fichier plus gros que `upload_max_filesize`/`post_max_size`
	 * de PHP échoue — `media_handle_upload()` renvoie alors un WP_Error dont le
	 * message remonte tel quel en toast (jamais masqué).
	 */
	public function ajax_desc_video() {
		// Mimes limités au web-lisible sans transcodage (la vraie barrière serveur).
		$att_id = $this->handle_desc_upload( 'video' );

		$url = wp_get_attachment_url( $att_id );
		if ( ! $url ) {
			wp_send_json_error( array( 'message' => __( 'Vidéo téléversée mais illisible.', 'aod-client-dashboard' ) ), 500 );
		}

		wp_send_json_success( array(
			'id'  => $att_id,
			'url' => $url,
		) );
	}
}
