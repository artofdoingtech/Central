<?php
/**
 * Section « Page de vente » : éditeur de blocs d'une landing produit (étape 6.2).
 *
 * Écran dédié, atteint depuis la liste des produits (`/gestion/landing?produit=123`)
 * et volontairement ABSENT de la barre latérale : une page de vente appartient à un
 * produit, on n'y entre pas « dans le vide ».
 *
 * CETTE CLASSE N'EST PAS PROPRIÉTAIRE DU FORMAT. Le schéma, le nettoyage et le rendu
 * vivent dans `AOD_COD_Landing` (plugin aod-cod-form). Ici on ne fait que DESSINER le
 * formulaire à partir de `AOD_COD_Landing::schema()` et RENVOYER le POST à
 * `AOD_COD_Landing::sanitize()`. Deux copies du schéma dériveraient au premier bloc
 * ajouté — et c'est le rendu public qui trinquerait, en silence.
 *
 * Chaque champ texte est un COUPLE { fr, ar } : deux cases côte à côte. `gettext` ne
 * traduit que le code ; le contenu saisi par le gérant ne passera jamais par les .po.
 * Cf. docs/LANDING-PRODUIT.md §2.3.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Landing {

	/** @var AOD_CD_Section_Landing|null */
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_aod_cd_save_landing', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_aod_cd_copy_landing', array( $this, 'ajax_copy' ) );
	}

	/**
	 * Le plugin public est-il là ? Sans lui, pas de schéma, pas de rendu : l'éditeur
	 * n'a plus rien à éditer. On le dit, on ne tombe pas en erreur fatale.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( 'AOD_COD_Landing' );
	}

	/** URL de l'éditeur pour un produit. */
	public static function url( $product_id ) {
		return add_query_arg(
			array( 'produit' => (int) $product_id ),
			home_url( '/' . AOD_CD_SLUG . '/landing' )
		);
	}

	/* ============================================================
	 * Rendu de l'écran
	 * ========================================================== */

	public function render() {
		$pid     = isset( $_GET['produit'] ) ? absint( $_GET['produit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- lecture seule.
		$product = $pid ? wc_get_product( $pid ) : null;

		if ( ! self::available() ) {
			echo AOD_CD_Dashboard::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
				'unplugged',
				__( 'Le plugin « AOD COD Form » est requis.', 'aod-client-dashboard' ),
				__( 'C’est lui qui affiche la page de vente sur la boutique. Sans lui, il n’y a rien à éditer.', 'aod-client-dashboard' )
			);
			return;
		}

		if ( ! $product ) {
			echo AOD_CD_Dashboard::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
				'question',
				__( 'Produit introuvable.', 'aod-client-dashboard' ),
				__( 'Une page de vente appartient à un produit : ouvrez-la depuis la liste des produits.', 'aod-client-dashboard' )
			);
			return;
		}

		// Périmètre produits d'un confirmateur : même garde que l'ajustement de stock.
		// (La section elle-même est gérant-only ; ceci reste une ceinture de sécurité.)
		$scope = AOD_CD_Roles::assigned_products();
		if ( $scope && ! in_array( $pid, $scope, true ) ) {
			echo AOD_CD_Dashboard::empty_state( 'locked', __( 'Accès refusé.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$data    = AOD_COD_Landing::get( $pid );
		$enabled = AOD_COD_Landing::is_enabled( $pid );
		$schema  = AOD_COD_Landing::schema();

		// Aperçu : l'URL du produit + le paramètre d'aperçu. Elle sert MÊME quand
		// l'interrupteur est éteint — c'est justement là qu'on veut voir avant d'ouvrir.
		$preview = add_query_arg(
			array( AOD_COD_Landing::PREVIEW_PARAM => '1' ),
			get_permalink( $pid )
		);

		$actions = '<a class="aod-cd-btn" href="' . esc_url( $preview ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Aperçu', 'aod-client-dashboard' ) . '</a> '
			. '<a class="aod-cd-btn" href="' . esc_url( home_url( '/' . AOD_CD_SLUG . '/products' ) ) . '">'
			. esc_html__( 'Retour aux produits', 'aod-client-dashboard' ) . '</a>';

		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput
			__( 'Page de vente', 'aod-client-dashboard' ),
			$product->get_name(),
			$actions
		);
		?>
		<form class="aod-cd-form aod-cd-lp" id="aod-cd-landing-form" enctype="multipart/form-data">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $pid ); ?>">

			<div class="aod-cd-lp-onoff">
				<label class="aod-cd-check">
					<input type="checkbox" name="enabled" value="1" <?php checked( $enabled ); ?>>
					<span><?php esc_html_e( 'Afficher la page de vente à la place de la fiche produit', 'aod-client-dashboard' ); ?></span>
				</label>
				<p class="aod-cd-note">
					<?php esc_html_e( 'L’adresse du produit ne change pas. Éteint, le produit s’affiche comme avant — votre travail est conservé. Vous pouvez donc préparer la page tranquillement, puis l’allumer.', 'aod-client-dashboard' ); ?>
				</p>
			</div>

			<?php
			// La barre « Commander » collante : un RÉGLAGE DE PAGE, pas un bloc — elle n'a
			// pas de place dans le flux, elle flotte au-dessus. Elle vit donc ici, hors de
			// la liste des blocs (et n'est jamais renumérotée : pas de `data-n`).
			$sticky = $data['sticky_cta'];
			?>
			<div class="aod-cd-lp-onoff">
				<label class="aod-cd-check">
					<input type="checkbox" name="sticky[enabled]" value="1" <?php checked( ! empty( $sticky['enabled'] ) ); ?>>
					<span><?php esc_html_e( 'Afficher un bouton « Commander » collé en bas de l’écran (mobile)', 'aod-client-dashboard' ); ?></span>
				</label>
				<p class="aod-cd-note">
					<?php esc_html_e( 'Il ramène au formulaire, et s’efface tout seul quand le visiteur y arrive. Il n’apparaît que si la page contient le bloc « Formulaire de commande ».', 'aod-client-dashboard' ); ?>
				</p>
				<?php $this->render_pair( 'sticky', '[label]', '', __( 'Texte du bouton', 'aod-client-dashboard' ), $sticky['label'] ); ?>
			</div>

			<div class="aod-cd-lp-blocks" id="aod-cd-lp-blocks">
				<?php
				foreach ( $data['blocks'] as $i => $block ) {
					$this->render_block( $i, $block['type'], $block, $schema );
				}
				?>
			</div>

			<?php // État vide : ne pas laisser le gérant devant une page nue sans savoir quoi faire. ?>
			<div class="aod-cd-lp-empty" id="aod-cd-lp-empty"<?php echo $data['blocks'] ? ' hidden' : ''; ?>>
				<?php
				echo AOD_CD_Dashboard::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput
					'blocks',
					__( 'Aucun bloc pour le moment.', 'aod-client-dashboard' ),
					__( 'Ajoutez le « Héro », puis le « Formulaire de commande » : c’est le minimum d’une page qui vend.', 'aod-client-dashboard' )
				);
				?>
				<p class="aod-cd-lp-starter">
					<?php
					// La liste des blocs vient du FORMAT (AOD_COD_Landing::starter()), pas
					// d'ici : l'éditeur ne sait pas ce qu'est « une landing type », il clone
					// juste les gabarits. Côté JS, un bloc du modèle sans gabarit est ignoré
					// (clone plus ancien) au lieu de casser le bouton.
					?>
					<button type="button" class="aod-cd-btn" id="aod-cd-lp-starterbtn"
						data-blocks="<?php echo esc_attr( implode( ',', AOD_COD_Landing::starter() ) ); ?>">
						<?php echo AOD_CD_Icon::svg( 'template', array( 'size' => 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Partir d’un modèle', 'aod-client-dashboard' ); ?>
					</button>
				</p>
				<p class="aod-cd-note">
					<?php esc_html_e( 'Le modèle pose la structure d’une page qui vend — héro, bénéfices, galerie, avis, questions, urgence, formulaire, garanties — avec des blocs vides : les textes restent à écrire, en français et en arabe.', 'aod-client-dashboard' ); ?>
				</p>
			</div>

			<?php
			// Copier la page de vente d'un autre produit. La liste ne propose que des
			// produits qui ont RÉELLEMENT des blocs (brouillon compris) — copier du vide
			// n'a pas de sens, et un menu vide n'a pas à s'afficher.
			$sources = $this->copy_sources( $pid, $scope );
			if ( $sources ) :
				?>
				<div class="aod-cd-lp-copy">
					<label class="aod-cd-label" for="aod-cd-lp-copysrc"><?php esc_html_e( 'Ou copier la page de vente d’un autre produit', 'aod-client-dashboard' ); ?></label>
					<div class="aod-cd-lp-copyrow">
						<select id="aod-cd-lp-copysrc">
							<?php foreach ( $sources as $sid => $title ) : ?>
								<option value="<?php echo esc_attr( (string) $sid ); ?>"><?php echo esc_html( $title ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="aod-cd-btn" id="aod-cd-lp-copybtn"><?php esc_html_e( 'Copier', 'aod-client-dashboard' ); ?></button>
					</div>
					<p class="aod-cd-note">
						<?php esc_html_e( 'La copie remplace les blocs actuels et arrive ÉTEINTE : ses textes parlent de l’autre produit — adaptez-les, puis allumez la page.', 'aod-client-dashboard' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="aod-cd-lp-add">
				<div class="aod-cd-field">
					<label class="aod-cd-label" for="aod-cd-lp-type"><?php esc_html_e( 'Ajouter un bloc', 'aod-client-dashboard' ); ?></label>
					<select id="aod-cd-lp-type">
						<?php foreach ( $schema as $type => $spec ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $spec['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="button" class="aod-cd-btn" id="aod-cd-lp-addbtn">+ <?php esc_html_e( 'Ajouter', 'aod-client-dashboard' ); ?></button>
			</div>

			<div class="aod-cd-savebar aod-cd-form-foot">
				<button type="submit" class="aod-cd-btn aod-cd-btn-primary"><?php esc_html_e( 'Enregistrer', 'aod-client-dashboard' ); ?></button>
				<span class="aod-cd-form-msg" role="status"></span>
			</div>
		</form>

		<?php
		// Un gabarit par type de bloc : le JS clone, puis renumérote. Les index portent
		// le jeton `__i__` / `__j__`, jamais un vrai numéro — un gabarit n'a pas de rang.
		foreach ( $schema as $type => $spec ) :
			?>
			<template id="aod-cd-lp-tpl-<?php echo esc_attr( $type ); ?>">
				<?php $this->render_block( '__i__', $type, array(), $schema ); ?>
			</template>
			<?php
		endforeach;
	}

	/**
	 * Un bloc de l'éditeur.
	 *
	 * @param int|string $i      Index du bloc (ou le jeton `__i__` dans un gabarit).
	 * @param string     $type   Type de bloc (clé du schéma).
	 * @param array      $block  Valeurs actuelles (vide pour un bloc neuf).
	 * @param array      $schema Schéma complet.
	 */
	protected function render_block( $i, $type, $block, $schema ) {
		if ( ! isset( $schema[ $type ] ) ) {
			return;
		}
		$spec = $schema[ $type ];
		$id   = isset( $block['id'] ) ? (string) $block['id'] : '';
		?>
		<div class="aod-cd-lp-block" data-type="<?php echo esc_attr( $type ); ?>">
			<div class="aod-cd-lp-head">
				<button type="button" class="aod-cd-lp-toggle" aria-expanded="true">
					<span class="aod-cd-lp-chev" aria-hidden="true">▾</span>
					<span class="aod-cd-lp-name"><?php echo esc_html( $spec['label'] ); ?></span>
				</button>
				<div class="aod-cd-lp-tools">
					<button type="button" class="aod-cd-lp-mv" data-dir="up" aria-label="<?php esc_attr_e( 'Monter ce bloc', 'aod-client-dashboard' ); ?>">↑</button>
					<button type="button" class="aod-cd-lp-mv" data-dir="down" aria-label="<?php esc_attr_e( 'Descendre ce bloc', 'aod-client-dashboard' ); ?>">↓</button>
					<button type="button" class="aod-cd-lp-del" aria-label="<?php esc_attr_e( 'Supprimer ce bloc', 'aod-client-dashboard' ); ?>">&times;</button>
				</div>
			</div>
			<div class="aod-cd-lp-body">
				<input type="hidden" data-n="[type]" name="blocks[<?php echo esc_attr( (string) $i ); ?>][type]" value="<?php echo esc_attr( $type ); ?>">
				<input type="hidden" data-n="[id]" name="blocks[<?php echo esc_attr( (string) $i ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
				<?php
				if ( 'form' === $type ) {
					// Le seul bloc dont le contenu n'appartient pas au gérant : le formulaire
					// COD est rendu par le plugin public, avec ses offres, ses variantes et
					// ses 5 couches anti-bot (dont 4 sur un clone neuf). On n'en édite que le titre.
					echo '<p class="aod-cd-note">' . esc_html__( 'Le formulaire de commande (wilaya, commune, livraison, offres) est repris tel quel de votre fiche produit. Vous n’en réglez ici que le titre.', 'aod-client-dashboard' ) . '</p>';
				}
				if ( 'urgency' === $type ) {
					// Le bloc s'appuie sur des chiffres RÉELS : il peut donc ne rien afficher.
					// Mieux vaut le dire ici que laisser le gérant chercher pourquoi sa page
					// « ne montre pas l'urgence » — et surtout, mieux vaut ça que mentir.
					echo '<p class="aod-cd-note">' . esc_html__( 'Les chiffres sont ceux de votre boutique : le stock est lu dans votre fiche produit, et le compte à rebours s’arrête à la fin de votre promotion. Si le stock n’est pas géré, ou si la promotion n’a pas de date de fin, le bloc ne s’affiche pas — plutôt rien qu’un chiffre inventé.', 'aod-client-dashboard' ) . '</p>';
					echo '<p class="aod-cd-note">' . esc_html__( 'Dans le message, écrivez %d à l’endroit où doit apparaître le nombre restant — par exemple : « Plus que %d pièces en stock ! ». Laissez le message vide pour utiliser le texte par défaut.', 'aod-client-dashboard' ) . '</p>';
				}
				if ( 'video' === $type ) {
					// Deux façons de mettre une vidéo, et une règle claire entre les deux :
					// sans ça, le gérant remplit les deux champs et ne comprend pas le résultat.
					echo '<p class="aod-cd-note">' . esc_html__( 'Collez un lien YouTube, ou envoyez un fichier vidéo — pas les deux : si les deux sont remplis, c’est le fichier qui est joué. La vidéo ne se charge qu’au clic du visiteur : c’est l’image de couverture qui s’affiche avant.', 'aod-client-dashboard' ) . '</p>';
				}
				foreach ( $spec['fields'] as $key => $field ) {
					$this->render_field( $i, $key, $field, isset( $block[ $key ] ) ? $block[ $key ] : null );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Un champ de premier niveau (directement sous le bloc).
	 *
	 * @param int|string $i     Index du bloc.
	 * @param string     $key   Clé du champ.
	 * @param array      $field Spécification.
	 * @param mixed      $value Valeur actuelle.
	 */
	protected function render_field( $i, $key, $field, $value ) {
		$this->render_control( $i, '[' . $key . ']', '[' . $key . ']', $key, $field, $value );
	}

	/**
	 * Un champ, selon son type déclaré au schéma — À N'IMPORTE QUELLE PROFONDEUR.
	 *
	 * Trois chemins, et il faut les trois distincts (les confondre a déjà coûté un bug) :
	 *
	 *  - `$name` : le chemin RÉEL posté (`[items][2][photo]`) ;
	 *  - `$dn`   : le même, avec le jeton `__j__` à la place du rang — le JS le remplace
	 *              à chaque renumérotation, c'est lui qui survit aux déplacements ;
	 *  - `$fk`   : le nom PLAT du fichier joint (`items__%J%__photo`). `$_FILES` ne sait
	 *              pas rendre un nom imbriqué à `media_handle_upload()` : d'où un nom à
	 *              plat, et un second jeton (`%J%`) que le JS remplace lui aussi.
	 *
	 * @param int|string $i     Index du bloc.
	 * @param string     $name  Chemin réel, sous `blocks[i]`.
	 * @param string     $dn    Chemin gabarit (jeton `__j__`).
	 * @param string     $fk    Clé de fichier à plat (jeton `%J%`).
	 * @param array      $field Spécification.
	 * @param mixed      $value Valeur actuelle.
	 */
	protected function render_control( $i, $name, $dn, $fk, $field, $value ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';
		$nb   = 'blocks[' . $i . ']' . $name;

		switch ( $type ) {
			case 'image':
				$this->render_image( $nb, $dn, $i, $fk, $field, (int) $value );
				break;

			case 'images':
				$this->render_images( $nb, $dn, $i, $fk, $field, is_array( $value ) ? $value : array() );
				break;

			case 'video':
				$this->render_video( $nb, $dn, $i, $fk, $field, (int) $value );
				break;

			case 'url':
				?>
				<div class="aod-cd-field aod-cd-lp-plain">
					<label class="aod-cd-label"><?php echo esc_html( $field['label'] ); ?></label>
					<input type="url" inputmode="url" dir="ltr" placeholder="https://www.youtube.com/watch?v=…" data-n="<?php echo esc_attr( $dn ); ?>" name="<?php echo esc_attr( $nb ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
				</div>
				<?php
				break;

			case 'number':
				$min = isset( $field['min'] ) ? (int) $field['min'] : 0;
				$max = isset( $field['max'] ) ? (int) $field['max'] : 9999;
				?>
				<div class="aod-cd-field aod-cd-lp-plain aod-cd-lp-num">
					<label class="aod-cd-label"><?php echo esc_html( $field['label'] ); ?></label>
					<input type="number" inputmode="numeric" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="1" data-n="<?php echo esc_attr( $dn ); ?>" name="<?php echo esc_attr( $nb ); ?>" value="<?php echo esc_attr( (string) (int) $value ); ?>">
				</div>
				<?php
				break;

			case 'select':
				$options = isset( $field['options'] ) ? $field['options'] : array();
				$current = (string) $value;
				?>
				<div class="aod-cd-field aod-cd-lp-plain">
					<label class="aod-cd-label"><?php echo esc_html( $field['label'] ); ?></label>
					<select data-n="<?php echo esc_attr( $dn ); ?>" name="<?php echo esc_attr( $nb ); ?>">
						<?php foreach ( $options as $ok => $ol ) : ?>
							<option value="<?php echo esc_attr( $ok ); ?>" <?php selected( $current, $ok ); ?>><?php echo esc_html( $ol ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php
				break;

			case 'plain':
				?>
				<div class="aod-cd-field aod-cd-lp-plain">
					<label class="aod-cd-label"><?php echo esc_html( $field['label'] ); ?></label>
					<input type="text" data-n="<?php echo esc_attr( $dn ); ?>" name="<?php echo esc_attr( $nb ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
				</div>
				<?php
				break;

			case 'repeat':
				$this->render_repeat( $i, trim( $name, '[]' ), $field, is_array( $value ) ? $value : array() );
				break;

			case 'text':
			case 'textarea':
			default:
				$this->render_pair( 'blocks[' . $i . ']', $name, $dn, $field['label'], $value, 'textarea' === $type );
				break;
		}
	}

	/**
	 * Un couple bilingue : deux cases, `Français` et `العربية`.
	 *
	 * Les deux sont facultatives et le rendu public replie sur l'autre langue quand
	 * l'une manque — mais une case arabe laissée vide, c'est un visiteur arabophone
	 * qui lira du français. Les voir côte à côte rend le trou visible.
	 *
	 * Deux chemins, et non un seul : le `name` porte le rang RÉEL de l'item
	 * (`[items][2][title]`), tandis que `data-n` porte le jeton `__j__` que le JS
	 * remplace à chaque renumérotation. Les confondre, c'est poster `__j__` comme
	 * index quand le JS ne passe pas.
	 *
	 * @param string $prefix    Préfixe du `name` (`blocks[2]`, ou `sticky` pour la barre).
	 * @param string $name_base Chemin réel du champ (ex. `[title]`, `[items][2][title]`).
	 * @param string $data_base Chemin gabarit (ex. `[items][__j__][title]`). Vide = champ
	 *                          hors liste de blocs (la barre collante) : rien à renuméroter.
	 * @param string $label     Libellé.
	 * @param mixed  $value     Couple { fr, ar } actuel.
	 * @param bool   $multiline Champ multi-lignes.
	 */
	protected function render_pair( $prefix, $name_base, $data_base, $label, $value, $multiline = false ) {
		// Le rendu du couple est mutualisé (l'éditeur produit s'en sert aussi depuis
		// la 7.2) : une seule source pour le RTL, les aria-label et le balisage.
		AOD_CD_Dashboard::render_pair( $prefix, $name_base, $data_base, $label, $value, $multiline );
	}

	/**
	 * Champ image : vignette + choix de fichier + retrait.
	 *
	 * On stocke un ID d'attachement, jamais une URL : une URL en base ne survit pas au
	 * changement de domaine, le jour de la mise en ligne (cf. LANDING-PRODUIT.md §4).
	 *
	 * @param string     $nb    `name` du champ caché (ID).
	 * @param string     $dn    Chemin gabarit (`data-n`).
	 * @param int|string $i     Index du bloc (préfixe du nom de fichier).
	 * @param string     $fk    Clé de fichier à plat.
	 * @param array      $field Spécification.
	 * @param int        $att   ID d'attachement actuel.
	 */
	protected function render_image( $nb, $dn, $i, $fk, $field, $att ) {
		$url = $att ? wp_get_attachment_image_url( $att, 'medium' ) : '';
		?>
		<div class="aod-cd-field aod-cd-lp-img">
			<span class="aod-cd-label"><?php echo esc_html( $field['label'] ); ?></span>
			<div class="aod-cd-imgbox">
				<?php if ( $url ) : ?>
					<img class="aod-cd-imgprev" src="<?php echo esc_url( $url ); ?>" alt="">
				<?php else : ?>
					<span class="aod-cd-imgempty"><?php esc_html_e( 'Aucune image', 'aod-client-dashboard' ); ?></span>
				<?php endif; ?>
			</div>
			<?php // L'ID part avec le POST ; le fichier (s'il y en a un) l'écrasera côté serveur. ?>
			<input type="hidden" data-n="<?php echo esc_attr( $dn ); ?>" name="<?php echo esc_attr( $nb ); ?>" value="<?php echo esc_attr( (string) $att ); ?>">
			<input type="file" accept="image/*" data-file="<?php echo esc_attr( $fk ); ?>" name="img_<?php echo esc_attr( (string) $i ); ?>__<?php echo esc_attr( $fk ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
			<?php echo AOD_CD_Upload::limit_note(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le composant. ?>
			<?php if ( $att ) : ?>
				<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-lp-imgdel"><?php esc_html_e( 'Retirer l’image', 'aod-client-dashboard' ); ?></button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Champ galerie : les images déjà en place (retirables une à une) + un envoi multiple.
	 *
	 * Chaque image en place porte son propre champ caché `[images][]` : l'ordre du DOM
	 * est l'ordre enregistré, et retirer une vignette, c'est retirer sa ligne du POST.
	 * Aucune liste parallèle à tenir à jour — donc aucune à désynchroniser.
	 *
	 * @param string     $nb    `name` du champ caché, sans le `[]` final.
	 * @param string     $dn0   Chemin gabarit, sans le `[]` final.
	 * @param int|string $i     Index du bloc.
	 * @param string     $fk    Clé de fichier à plat.
	 * @param array      $field Spécification.
	 * @param array      $ids   IDs d'attachement actuels.
	 */
	protected function render_images( $nb, $dn0, $i, $fk, $field, $ids ) {
		$name = $nb . '[]';
		$dn   = $dn0 . '[]';
		?>
		<div class="aod-cd-field aod-cd-lp-gal">
			<span class="aod-cd-label"><?php echo esc_html( $field['label'] ); ?></span>
			<div class="aod-cd-lp-galgrid">
				<?php
				foreach ( $ids as $id ) :
					$id  = (int) $id;
					$url = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
					if ( ! $url ) {
						continue; // Image supprimée de la médiathèque : on ne montre pas un cadre vide.
					}
					?>
					<div class="aod-cd-lp-galitem">
						<img src="<?php echo esc_url( $url ); ?>" alt="">
						<input type="hidden" data-n="<?php echo esc_attr( $dn ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $id ); ?>">
						<button type="button" class="aod-cd-lp-galdel" aria-label="<?php esc_attr_e( 'Retirer cette image', 'aod-client-dashboard' ); ?>">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<input type="file" accept="image/*" multiple data-file="<?php echo esc_attr( $fk ); ?>" name="img_<?php echo esc_attr( (string) $i ); ?>__<?php echo esc_attr( $fk ); ?>[]" aria-label="<?php esc_attr_e( 'Ajouter des images à la galerie', 'aod-client-dashboard' ); ?>">
			<?php echo AOD_CD_Upload::limit_note(); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans le composant. ?>
			<p class="aod-cd-note"><?php esc_html_e( 'Les images choisies seront ajoutées à l’enregistrement. Pour changer votre choix, sélectionnez à nouveau vos fichiers.', 'aod-client-dashboard' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Champ fichier vidéo : nom du fichier en place + envoi + retrait.
	 *
	 * On annonce la taille maximale acceptée par le serveur, parce que c'est LA raison
	 * pour laquelle un envoi de vidéo échoue — et qu'un « ça ne marche pas » sans
	 * chiffre ne s'explique pas tout seul.
	 *
	 * @param string     $nb    `name` du champ caché (ID).
	 * @param string     $dn    Chemin gabarit (`data-n`).
	 * @param int|string $i     Index du bloc.
	 * @param string     $fk    Clé de fichier à plat.
	 * @param array      $field Spécification.
	 * @param int        $att   ID d'attachement actuel.
	 */
	protected function render_video( $nb, $dn, $i, $fk, $field, $att ) {
		$name = $att ? get_the_title( $att ) : '';
		?>
		<div class="aod-cd-field aod-cd-lp-vid">
			<span class="aod-cd-label"><?php echo esc_html( $field['label'] ); ?></span>
			<div class="aod-cd-lp-vidbox">
				<?php if ( $name ) : ?>
					<span class="aod-cd-lp-vidname"><?php echo AOD_CD_Icon::svg( 'video', array( 'size' => 15 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $name ); ?></span>
				<?php else : ?>
					<span class="aod-cd-imgempty"><?php esc_html_e( 'Aucun fichier', 'aod-client-dashboard' ); ?></span>
				<?php endif; ?>
			</div>
			<input type="hidden" data-n="<?php echo esc_attr( $dn ); ?>" name="<?php echo esc_attr( $nb ); ?>" value="<?php echo esc_attr( (string) $att ); ?>">
			<input type="file" accept="video/*" data-file="<?php echo esc_attr( $fk ); ?>" name="img_<?php echo esc_attr( (string) $i ); ?>__<?php echo esc_attr( $fk ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
			<p class="aod-cd-note">
				<?php
				// 🚨 `AOD_CD_Upload::max_bytes()`, PLUS `wp_max_upload_size()` (47.6).
				// Cette note annonçait la limite de l'HÉBERGEUR. Depuis que le goulot
				// porte sa propre borne (5 Mo, D6), les deux peuvent diverger : sur un
				// hébergeur généreux elle aurait promis 20 Mo pour un fichier refusé à
				// 5 — une note juste devenue fausse par un changement fait ailleurs.
				// Une seule source, celle qui refuse.
				/* translators: %s : taille maximale d'envoi acceptée (ex. « 5 Mo »). */
				echo esc_html( sprintf( __( 'Taille maximale acceptée : %s. Au-delà, préférez YouTube.', 'aod-client-dashboard' ), size_format( AOD_CD_Upload::max_bytes() ) ) );
				?>
			</p>
			<?php if ( $att ) : ?>
				<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-lp-viddel"><?php esc_html_e( 'Retirer la vidéo', 'aod-client-dashboard' ); ?></button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Champ répétable (bénéfices, garanties…) : une liste d'items + « Ajouter ».
	 *
	 * @param int|string $i     Index du bloc.
	 * @param string     $key   Clé du champ.
	 * @param array      $field Spécification (contient ses propres `fields`).
	 * @param array      $items Items actuels.
	 */
	protected function render_repeat( $i, $key, $field, $items ) {
		$sub = isset( $field['fields'] ) ? $field['fields'] : array();
		?>
		<div class="aod-cd-lp-repeat" data-key="<?php echo esc_attr( $key ); ?>">
			<span class="aod-cd-label"><?php echo esc_html( $field['label'] ); ?></span>
			<div class="aod-cd-lp-items">
				<?php foreach ( $items as $j => $item ) {
					$this->render_item( $i, $key, $j, $sub, $item );
				} ?>
			</div>
			<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-lp-additem">+ <?php esc_html_e( 'Ajouter', 'aod-client-dashboard' ); ?></button>
			<template class="aod-cd-lp-itemtpl"><?php $this->render_item( $i, $key, '__j__', $sub, array() ); ?></template>
		</div>
		<?php
	}

	/**
	 * Un item d'un champ répétable.
	 *
	 * @param int|string $i     Index du bloc.
	 * @param string     $key   Clé du champ répétable.
	 * @param int|string $j     Index de l'item (ou le jeton `__j__`).
	 * @param array      $sub   Champs de l'item.
	 * @param array      $item  Valeurs actuelles.
	 */
	protected function render_item( $i, $key, $j, $sub, $item ) {
		?>
		<div class="aod-cd-lp-item">
			<div class="aod-cd-lp-item-fields">
				<?php
				foreach ( $sub as $sk => $sf ) {
					// Trois chemins, cf. `render_control()` : le `name` porte le rang RÉEL de
					// l'item, `data-n` et la clé de fichier gardent leurs jetons (`__j__`,
					// `%J%`), que le JS remplace à chaque renumérotation.
					$this->render_control(
						$i,
						'[' . $key . '][' . $j . '][' . $sk . ']',
						'[' . $key . '][__j__][' . $sk . ']',
						$key . '__%J%__' . $sk,
						$sf,
						isset( $item[ $sk ] ) ? $item[ $sk ] : null
					);
				}
				?>
			</div>
			<button type="button" class="aod-cd-lp-delitem" aria-label="<?php esc_attr_e( 'Retirer cet élément', 'aod-client-dashboard' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Les produits dont on peut copier la page de vente.
	 *
	 * `meta_key` seul (sans valeur) = « le produit a la méta » : brouillons compris,
	 * c'est voulu — une landing éteinte mais travaillée est justement celle qu'on
	 * veut réutiliser. On filtre ensuite sur « a des blocs » : une méta vidée par un
	 * enregistrement sans bloc ne doit pas apparaître dans la liste.
	 *
	 * @param int   $pid   Produit courant (exclu de la liste).
	 * @param int[] $scope Périmètre produits de l'appelant (vide = tous).
	 * @return array id => titre.
	 */
	protected function copy_sources( $pid, $scope ) {
		$ids = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'any',
				'numberposts' => 200,
				'fields'      => 'ids',
				'meta_key'    => AOD_COD_Landing::META_DATA, // phpcs:ignore WordPress.DB.SlowDBQuery -- borné, écran gérant.
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$out = array();
		foreach ( $ids as $sid ) {
			$sid = (int) $sid;
			if ( $sid === (int) $pid ) {
				continue;
			}
			if ( $scope && ! in_array( $sid, $scope, true ) ) {
				continue;
			}
			$data = AOD_COD_Landing::get( $sid );
			if ( empty( $data['blocks'] ) ) {
				continue;
			}
			$out[ $sid ] = get_the_title( $sid );
		}
		return $out;
	}

	/* ============================================================
	 * AJAX : enregistrer la page de vente
	 * ========================================================== */

	/**
	 * Enregistre la landing d'un produit — en un seul appel, toute la structure.
	 *
	 * Le POST n'est JAMAIS écrit tel quel : il traverse `AOD_COD_Landing::sanitize()`,
	 * liste blanche stricte (type de bloc hors catalogue = jeté, champ hors schéma =
	 * jeté, aucun HTML libre).
	 */
	public function ajax_save() {
		check_ajax_referer( 'aod_cd', 'nonce' );

		if ( ! self::available() ) {
			wp_send_json_error( array( 'message' => __( 'Le plugin « AOD COD Form » est requis.', 'aod-client-dashboard' ) ), 400 );
		}
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$pid     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product = $pid ? wc_get_product( $pid ) : null;
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Produit introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		if ( ! current_user_can( 'edit_product', $pid ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$scope = AOD_CD_Roles::assigned_products();
		if ( $scope && ! in_array( $pid, $scope, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		// `array_values` : l'ordre des blocs est celui du POST, donc celui de l'écran.
		// phpcs:ignore WordPress.Security.NonceVerification -- vérifié ci-dessus.
		$blocks  = ( isset( $_POST['blocks'] ) && is_array( $_POST['blocks'] ) ) ? array_values( wp_unslash( $_POST['blocks'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- assaini par AOD_COD_Landing::sanitize().
		$upload  = $this->attach_uploads( $blocks, $pid );
		$blocks  = $upload['blocks'];
		$enabled = ! empty( $_POST['enabled'] );

		// phpcs:ignore WordPress.Security.NonceVerification -- vérifié ci-dessus.
		$sticky = ( isset( $_POST['sticky'] ) && is_array( $_POST['sticky'] ) ) ? wp_unslash( $_POST['sticky'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- assaini par AOD_COD_Landing::sanitize().

		$data = AOD_COD_Landing::sanitize(
			array(
				'sticky_cta' => $sticky,
				'blocks'     => $blocks,
			)
		);

		// Une page de vente ALLUMÉE sans formulaire, c'est une boutique où l'on ne peut
		// pas acheter. On refuse — l'interrupteur éteint reste le brouillon de travail.
		if ( $enabled ) {
			if ( empty( $data['blocks'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Ajoutez au moins un bloc avant d’afficher la page de vente.', 'aod-client-dashboard' ) ), 400 );
			}
			if ( ! $this->has_form_block( $data['blocks'] ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Ajoutez le bloc « Formulaire de commande » : sans lui, le visiteur ne peut pas commander. (Vous pouvez aussi enregistrer en laissant la page de vente éteinte.)', 'aod-client-dashboard' ),
					),
					400
				);
			}
		}

		$product->update_meta_data( AOD_COD_Landing::META_DATA, $data );
		if ( $enabled ) {
			$product->update_meta_data( AOD_COD_Landing::META_ENABLED, '1' );
		} else {
			$product->delete_meta_data( AOD_COD_Landing::META_ENABLED );
		}
		$product->save();

		// Un téléversement qui échoue (dossier `uploads` non inscriptible, fichier trop
		// lourd, format refusé…) NE DOIT PAS passer pour un succès : le reste du contenu
		// est enregistré, mais on dit que le fichier, lui, ne l'est pas. Un « Enregistré »
		// suivi d'une image absente est le pire des deux mondes.
		if ( $upload['error'] ) {
			wp_send_json_success(
				array(
					'message' => __( 'Enregistré', 'aod-client-dashboard' ),
					/* translators: %s : message d'erreur renvoyé par WordPress. */
					'warning' => sprintf( __( 'Le texte est enregistré, mais le fichier n’a pas pu être envoyé : %s', 'aod-client-dashboard' ), $upload['error'] ),
				)
			);
		}

		wp_send_json_success( array( 'message' => __( 'Enregistré', 'aod-client-dashboard' ) ) );
	}

	/**
	 * Copie la page de vente d'un produit vers un autre.
	 *
	 * La copie REMPLACE les blocs du produit cible et arrive ÉTEINTE : ses textes
	 * parlent de l'autre produit — les publier tels quels tromperait le visiteur.
	 * L'interrupteur retombe donc, même s'il était allumé : le gérant adapte, puis
	 * rallume. C'est le seul comportement honnête, et l'écran l'annonce avant le clic.
	 *
	 * Les images sont partagées, pas dupliquées : les deux landings pointent les
	 * mêmes IDs d'attachement — retirer une vignette d'une landing ne supprime
	 * jamais le fichier de la médiathèque.
	 */
	public function ajax_copy() {
		check_ajax_referer( 'aod_cd', 'nonce' );

		if ( ! self::available() ) {
			wp_send_json_error( array( 'message' => __( 'Le plugin « AOD COD Form » est requis.', 'aod-client-dashboard' ) ), 400 );
		}
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$src = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;

		$product = $pid ? wc_get_product( $pid ) : null;
		$source  = $src ? wc_get_product( $src ) : null;
		if ( ! $product || ! $source || $pid === $src ) {
			wp_send_json_error( array( 'message' => __( 'Produit introuvable.', 'aod-client-dashboard' ) ), 404 );
		}
		if ( ! current_user_can( 'edit_product', $pid ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		// Le périmètre vaut pour les DEUX bouts : copier DEPUIS un produit hors
		// périmètre serait lire un contenu qu'on n'a pas à voir.
		$scope = AOD_CD_Roles::assigned_products();
		if ( $scope && ( ! in_array( $pid, $scope, true ) || ! in_array( $src, $scope, true ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		// `get()` repasse par `sanitize()` : même une méta source écrite à la main
		// arrive propre — la cible ne reçoit jamais autre chose que le format.
		$data = AOD_COD_Landing::get( $src );
		if ( empty( $data['blocks'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Ce produit n’a pas de page de vente à copier.', 'aod-client-dashboard' ) ), 400 );
		}

		$product->update_meta_data( AOD_COD_Landing::META_DATA, $data );
		$product->delete_meta_data( AOD_COD_Landing::META_ENABLED ); // La copie arrive éteinte.
		$product->save();

		wp_send_json_success(
			array(
				'message' => __( 'Page copiée — elle est éteinte : adaptez les textes, puis allumez-la.', 'aod-client-dashboard' ),
			)
		);
	}

	/**
	 * Y a-t-il un bloc « formulaire de commande » dans cette landing ?
	 *
	 * @param array $blocks Blocs assainis.
	 * @return bool
	 */
	protected function has_form_block( $blocks ) {
		foreach ( $blocks as $block ) {
			if ( isset( $block['type'] ) && 'form' === $block['type'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Téléverse les fichiers postés (images, galerie, vidéo) et pose leur ID dans les blocs.
	 *
	 * Les fichiers arrivent sous un nom PLAT (`img_<bloc>__<champ>`) et non imbriqué :
	 * `$_FILES` réorganise les noms en tableau (`name[0][image]`…) d'une façon que
	 * `media_handle_upload()` ne sait pas lire. Un nom plat par champ image évite tout
	 * ce démêlage — c'est aussi ce que fait déjà la galerie produit.
	 *
	 * @param array $blocks Blocs bruts du POST.
	 * @param int   $pid    Produit (parent de l'attachement).
	 * @return array { blocks: array, error: string } — `error` vide si tout est passé.
	 */
	protected function attach_uploads( $blocks, $pid ) {
		if ( empty( $_FILES ) ) {
			return array( 'blocks' => $blocks, 'error' => '' );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$schema = AOD_COD_Landing::schema();
		$error  = '';

		foreach ( $blocks as $i => $block ) {
			$type = ( is_array( $block ) && isset( $block['type'] ) ) ? sanitize_key( $block['type'] ) : '';
			if ( ! isset( $schema[ $type ] ) ) {
				continue;
			}
			foreach ( $schema[ $type ]['fields'] as $key => $spec ) {
				$field = 'img_' . $i . '__' . $key;

				// Un seul fichier (image de héro, de couverture, vidéo).
				if ( 'image' === $spec['type'] || 'video' === $spec['type'] ) {
					$att = $this->upload_one( $field, $pid, $error, $spec['type'] );
					if ( $att ) {
						$blocks[ $i ][ $key ] = $att;
					}
					continue;
				}

				// Galerie : envoi multiple. `media_handle_upload()` ne sait lire qu'une
				// entrée SIMPLE de `$_FILES` ; un champ `multiple` en produit une où chaque
				// clé est un tableau. On lui présente donc les fichiers un par un, sous une
				// entrée temporaire — même ruse que la galerie produit (`save_gallery`).
				if ( 'images' === $spec['type'] ) {
					if ( empty( $_FILES[ $field ]['name'][0] ) ) {
						continue;
					}
					$files = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passé à media_handle_upload().
					$count = count( $files['name'] );
					$ids   = ( isset( $blocks[ $i ][ $key ] ) && is_array( $blocks[ $i ][ $key ] ) ) ? $blocks[ $i ][ $key ] : array();

					for ( $k = 0; $k < $count; $k++ ) {
						if ( empty( $files['name'][ $k ] ) ) {
							continue;
						}
						$_FILES['aod_lp_one'] = array(
							'name'     => $files['name'][ $k ],
							'type'     => $files['type'][ $k ],
							'tmp_name' => $files['tmp_name'][ $k ],
							'error'    => $files['error'][ $k ],
							'size'     => $files['size'][ $k ],
						);
						$att = $this->upload_one( 'aod_lp_one', $pid, $error );
						if ( $att ) {
							$ids[] = $att;
						}
					}
					unset( $_FILES['aod_lp_one'] );

					$blocks[ $i ][ $key ] = $ids;
					continue;
				}

				// Fichier DANS un item (la photo d'un avis) : le nom reste plat, mais il
				// porte en plus le rang de l'item — `img_<bloc>__<champ>__<rang>__<sous-champ>`.
				if ( 'repeat' === $spec['type'] ) {
					$items = ( isset( $blocks[ $i ][ $key ] ) && is_array( $blocks[ $i ][ $key ] ) ) ? $blocks[ $i ][ $key ] : array();
					foreach ( $items as $j => $item ) {
						foreach ( $spec['fields'] as $sk => $ssp ) {
							if ( 'image' !== $ssp['type'] && 'video' !== $ssp['type'] ) {
								continue;
							}
							$att = $this->upload_one( 'img_' . $i . '__' . $key . '__' . $j . '__' . $sk, $pid, $error, $ssp['type'] );
							if ( $att ) {
								$blocks[ $i ][ $key ][ $j ][ $sk ] = $att;
							}
						}
					}
				}
			}
		}

		return array( 'blocks' => $blocks, 'error' => $error );
	}

	/**
	 * Téléverse UN fichier posté sous `$field`, s'il y en a un.
	 *
	 * @param string $field Clé dans `$_FILES`.
	 * @param int    $pid   Produit (parent de l'attachement).
	 * @param string $error Premier message d'erreur rencontré (par référence). On garde le
	 *                      PREMIER : c'est presque toujours la même cause pour tous les
	 *                      fichiers (dossier non inscriptible, quota, format refusé).
	 * @param string $kind  Nature du champ : 'image' (défaut) ou 'video'.
	 *
	 * ⚠️ `$kind` N'EST PAS DÉCORATIF. Cette méthode sert les images de héro, de
	 * couverture, de galerie et d'avis — MAIS AUSSI le champ `file` du bloc
	 * « Vidéo » (« Ou fichier vidéo (MP4) »), qui emprunte exactement le même
	 * chemin. La liste blanche posée en 32.2 doit donc suivre le type déclaré au
	 * schéma : une liste image-seule appliquée ici casserait une fonctionnalité
	 * livrée en Phase 6, sans autre symptôme qu'un « format refusé ».
	 *
	 * @return int ID d'attachement, ou 0 (pas de fichier, ou échec).
	 */
	protected function upload_one( $field, $pid, &$error, $kind = 'image' ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return 0;
		}
		$att = AOD_CD_Upload::handle( $field, $pid, $kind );
		if ( is_wp_error( $att ) ) {
			if ( '' === $error ) {
				$error = $att->get_error_message();
			}
			return 0;
		}
		return (int) $att;
	}
}
