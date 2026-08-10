<?php
/**
 * Section « Finance » : le pôle des outils financiers de la boutique.
 *
 * 🧭 UNE PAGE D'ACCUEIL, PUIS DES OUTILS. La section rend un CHOIX de modules
 * (`render_hub()`) ou UN module (`?outil=<clé>`). Ce n'est pas de la coquetterie
 * d'URL : c'est ce qui permet d'ajouter un outil financier sans toucher ni à la
 * navigation, ni au routeur de `AOD_CD_Dashboard` — il suffit d'une entrée dans
 * `modules()` et d'une méthode de rendu. La demande owner parlait explicitement
 * d'« une architecture évolutive » ; la voici, et elle tient en un tableau.
 *
 * ⛔ CE QUI N'EST PAS ICI, ET POURQUOI. Le cahier des charges initial demandait
 * aussi un module « Équipe de confirmation » et un « Business Manager » complet
 * (produits, rôles, permissions, chiffre d'affaires). Ils ont été ÉCARTÉS après
 * arbitrage owner, parce qu'ils existent déjà :
 *   · CRUD confirmateurs + permissions à la carte → `AOD_CD_Section_Team` ;
 *   · statistiques par confirmateur, délai moyen, taux → onglet Confirmation de
 *     `AOD_CD_Section_Home`, avec ses filtres de dates ;
 *   · produits et prix → `AOD_CD_Section_Products` ;
 *   · rôles et capabilities → `AOD_CD_Roles`.
 * Les recopier ici aurait donné deux écrans pour gérer les mêmes personnes, dont
 * un seul ferait foi — le mode de dérive que ce dépôt passe son temps à fermer.
 * Restent à bâtir, parce qu'ils n'ont PAS de jumeau : les dépenses et la fiche
 * d'entreprise (fiscal, bancaire).
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Section_Finance {

	/** @var AOD_CD_Section_Finance|null */
	protected static $instance = null;

	/** Option portant l'historique des calculs enregistrés. */
	const OPTION_CALCULS = 'aod_cd_finance_calculs';

	/**
	 * Combien de calculs on garde.
	 *
	 * ⚠️ UN PLAFOND, PAS UNE PRÉFÉRENCE. L'historique vit dans une OPTION, donc
	 * dans une seule ligne chargée à chaque requête via `alloptions` si elle est
	 * autoloadée — d'où `update_option( …, false )` plus bas. Sans plafond, un
	 * gérant qui enregistre chaque simulation ferait grossir cette ligne sans fin.
	 */
	const MAX_HISTORIQUE = 20;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_aod_cd_finance_save', array( $this, 'ajax_finance_save' ) );
		add_action( 'wp_ajax_aod_cd_finance_delete', array( $this, 'ajax_finance_delete' ) );
	}

	/* =====================================================================
	 * Le catalogue des modules
	 * ================================================================== */

	/**
	 * Les outils du pôle Finance.
	 *
	 * `etat` vaut `pret` ou `avenir`. Un module « à venir » est rendu, mais SANS
	 * lien et sans affordance de clic : annoncer une architecture sans montrer ce
	 * qu'elle attend rendrait la page mensongère dans l'autre sens (« il n'y a
	 * qu'un outil, donc ce n'est pas un pôle »). Ce qu'on ne fait pas, en
	 * revanche, c'est offrir un bouton qui ne mène nulle part.
	 *
	 * @return array<string,array>
	 */
	public static function modules() {
		return array(
			'calculateur' => array(
				'ic'    => 'money',
				'titre' => __( 'Calculateur de bénéfices', 'aod-client-dashboard' ),
				'sous'  => __( 'Ce qu’il vous reste vraiment, une fois la publicité, les retours et la confirmation payés.', 'aod-client-dashboard' ),
				'etat'  => 'pret',
			),
			'depenses'    => array(
				'ic'    => 'orders',
				'titre' => __( 'Dépenses', 'aod-client-dashboard' ),
				'sous'  => __( 'Publicité, livraison, salaires, emballage, fournisseurs, abonnements — saisies une fois, retrouvées par période.', 'aod-client-dashboard' ),
				'etat'  => 'avenir',
			),
			'entreprise'  => array(
				'ic'    => 'storefront',
				'titre' => __( 'Entreprise', 'aod-client-dashboard' ),
				'sous'  => __( 'Coordonnées, informations fiscales et bancaires de la société.', 'aod-client-dashboard' ),
				'etat'  => 'avenir',
			),
		);
	}

	/* =====================================================================
	 * Le contrat de saisie du calculateur
	 * ================================================================== */

	/**
	 * Les champs du calculateur — DÉCLARÉS UNE FOIS, LUS TROIS FOIS.
	 *
	 * 🚨 CE TABLEAU EST LA SOURCE UNIQUE. Le formulaire s'en déduit, la
	 * sanitisation du POST s'en déduit, et le contrat de `compute()` s'y adosse.
	 * Écrits séparément, ces trois-là auraient divergé au premier champ ajouté :
	 * un champ affiché mais non lu se saisit sans effet, un champ lu mais non
	 * affiché reste à zéro pour toujours — et aucun des deux ne se signale.
	 *
	 * ⚠️ `portee` EST LA MOITIÉ DU SUJET, PAS UNE ÉTIQUETTE. « Frais de
	 * livraison » est un montant PAR COMMANDE ; « Frais de publicité » est un
	 * montant TOTAL sur la période. Mélanger les deux dans une même colonne de
	 * formulaire — ce que fait n'importe quel calculateur bâclé — donne un
	 * bénéfice faux d'un facteur égal au nombre de commandes, et l'écran n'a
	 * aucun moyen de le dire. Les groupes du formulaire portent cette distinction
	 * au premier coup d'œil.
	 *
	 * @return array<string,array> groupe => array( 'titre', 'aide', 'champs' )
	 */
	public static function champs() {
		return array(
			'unitaire' => array(
				'titre'  => __( 'Par commande', 'aod-client-dashboard' ),
				'aide'   => __( 'Ce que chaque commande vous coûte ou vous rapporte, une par une.', 'aod-client-dashboard' ),
				'ic'     => 'cart',
				'champs' => array(
					'prix_vente'   => array( 'l' => __( 'Prix de vente', 'aod-client-dashboard' ), 't' => 'money', 'h' => __( 'Le prix payé par le client à la livraison.', 'aod-client-dashboard' ) ),
					'prix_achat'   => array( 'l' => __( 'Prix d’achat (coût produit)', 'aod-client-dashboard' ), 't' => 'money', 'h' => __( 'Ce que le produit vous coûte chez le fournisseur.', 'aod-client-dashboard' ) ),
					'f_livraison'  => array( 'l' => __( 'Frais de livraison', 'aod-client-dashboard' ), 't' => 'money', 'h' => __( 'Ce que le transporteur vous facture par colis.', 'aod-client-dashboard' ) ),
					'f_confirm'    => array( 'l' => __( 'Frais de confirmation', 'aod-client-dashboard' ), 't' => 'money', 'h' => __( 'Ce que coûte l’appel — compté sur TOUTES les commandes, pas seulement celles qui aboutissent.', 'aod-client-dashboard' ) ),
					'f_emballage'  => array( 'l' => __( 'Frais d’emballage', 'aod-client-dashboard' ), 't' => 'money', 'h' => __( 'Carton, adhésif, étiquette — compté sur les colis réellement partis.', 'aod-client-dashboard' ) ),
				),
			),
			'periode'  => array(
				'titre'  => __( 'Sur la période', 'aod-client-dashboard' ),
				'aide'   => __( 'Des montants globaux, pas par commande : ils ne se multiplient pas.', 'aod-client-dashboard' ),
				'ic'     => 'calendar',
				'champs' => array(
					'f_pub'   => array( 'l' => __( 'Frais de publicité', 'aod-client-dashboard' ), 't' => 'money', 'h' => __( 'Le budget total dépensé en publicité sur la période.', 'aod-client-dashboard' ) ),
					'f_autres' => array( 'l' => __( 'Autres dépenses', 'aod-client-dashboard' ), 't' => 'money', 'h' => __( 'Abonnements, outils, salaires fixes… tout ce qui ne dépend pas du nombre de commandes.', 'aod-client-dashboard' ) ),
				),
			),
			'volumes'  => array(
				'titre'  => __( 'Vos commandes', 'aod-client-dashboard' ),
				'aide'   => __( 'Les quantités observées sur la même période.', 'aod-client-dashboard' ),
				'ic'     => 'orders',
				'champs' => array(
					'n_commandes' => array( 'l' => __( 'Commandes reçues', 'aod-client-dashboard' ), 't' => 'int', 'h' => __( 'Le total, avant tout tri.', 'aod-client-dashboard' ) ),
					'n_livrees'   => array( 'l' => __( 'Commandes livrées', 'aod-client-dashboard' ), 't' => 'int', 'h' => __( 'Celles qui ont été payées. Ce sont les seules qui font du chiffre d’affaires.', 'aod-client-dashboard' ) ),
					'n_annulees'  => array( 'l' => __( 'Commandes annulées', 'aod-client-dashboard' ), 't' => 'int', 'h' => __( 'Abandonnées avant l’expédition : aucun colis n’est parti.', 'aod-client-dashboard' ) ),
					'n_retours'   => array( 'l' => __( 'Retours', 'aod-client-dashboard' ), 't' => 'int', 'h' => __( 'Le colis est parti et vous est revenu : la livraison est due, la marchandise revient en stock.', 'aod-client-dashboard' ) ),
				),
			),
		);
	}

	/**
	 * Les noms des postes de coût, pour la légende dessinée par le JS.
	 *
	 * ⚠️ CÔTÉ PHP, ET C'EST LA RAISON D'ÊTRE DE CETTE MÉTHODE. La barre « Où part
	 * l'argent » est construite dans le navigateur : sans cette table, ses sept
	 * libellés auraient été écrits en dur dans le JS, donc hors de `gettext`, donc
	 * en français sur un `/gestion` en arabe — le défaut 42.5, « le code PARAÎT
	 * internationalisé ». Les clés sont celles que rend `compute()`.
	 *
	 * @return array<string,string>
	 */
	public static function postes_i18n() {
		return array(
			'cout_produit'   => __( 'Produit', 'aod-client-dashboard' ),
			'cout_pub'       => __( 'Publicité', 'aod-client-dashboard' ),
			'cout_livraison' => __( 'Livraison', 'aod-client-dashboard' ),
			'cout_confirm'   => __( 'Confirmation', 'aod-client-dashboard' ),
			'cout_emballage' => __( 'Emballage', 'aod-client-dashboard' ),
			'cout_retours'   => __( 'Retours', 'aod-client-dashboard' ),
			'cout_autres'    => __( 'Autres dépenses', 'aod-client-dashboard' ),
		);
	}

	/**
	 * Toutes les clés de saisie, à plat.
	 *
	 * @return array<string,string> clé => type (`money`|`int`)
	 */
	public static function cles_saisie() {
		$out = array();
		foreach ( self::champs() as $groupe ) {
			foreach ( $groupe['champs'] as $cle => $def ) {
				$out[ $cle ] = $def['t'];
			}
		}
		return $out;
	}

	/* =====================================================================
	 * LE CALCUL — source unique de vérité
	 * ================================================================== */

	/**
	 * Le bénéfice réel, et les treize indicateurs qui l'expliquent.
	 *
	 * 🚨 CETTE MÉTHODE EST L'AUTORITÉ. `section-finance.js` recalcule les mêmes
	 * formules pour l'aperçu en direct — sans quoi chaque frappe coûterait un
	 * aller-retour de 2 à 3 secondes sur un montage Windows (TEMPLATE.md §3), et
	 * l'outil serait inutilisable. Cette duplication est ASSUMÉE, et elle n'est
	 * pas laissée à la confiance : à l'enregistrement, le serveur RECALCULE tout
	 * depuis les seules entrées, et le JS compare son résultat au sien. Une
	 * divergence s'affiche à l'écran au lieu de dormir. *Une logique écrite deux
	 * fois dérive ; ce qu'on peut choisir, c'est qu'elle le dise.*
	 *
	 * ⚠️ LE MODÈLE, EN TROIS PHRASES — parce qu'un calculateur qui ne dit pas ce
	 * qu'il compte est un générateur de chiffres, pas un outil :
	 *   1. On n'encaisse QUE les commandes livrées. Le chiffre d'affaires et le
	 *      coût produit ne portent donc que sur elles.
	 *   2. Un RETOUR a coûté sa livraison (le colis est parti) mais la
	 *      marchandise revient en stock — il pèse en transport, pas en produit.
	 *   3. La CONFIRMATION se paie sur toutes les commandes reçues : on appelle
	 *      aussi celles qui n'aboutiront pas. C'est précisément ce que les
	 *      calculateurs naïfs oublient, et c'est ce qui mange la marge.
	 *
	 * ⚠️ Aucune division n'est faite sans son garde : à zéro commande, l'outil
	 * rend zéro plutôt qu'un `INF` ou un `NAN` qui traverserait tout l'affichage.
	 *
	 * @param array $e Entrées brutes (clés de `cles_saisie()`).
	 * @return array<string,float> Résultats.
	 */
	public static function compute( $e ) {
		$n = static function ( $v ) {
			$v = is_scalar( $v ) ? (float) $v : 0.0;
			return ( is_finite( $v ) && $v > 0 ) ? $v : 0.0;
		};

		$pv = $n( isset( $e['prix_vente'] ) ? $e['prix_vente'] : 0 );
		$pa = $n( isset( $e['prix_achat'] ) ? $e['prix_achat'] : 0 );
		$fl = $n( isset( $e['f_livraison'] ) ? $e['f_livraison'] : 0 );
		$fc = $n( isset( $e['f_confirm'] ) ? $e['f_confirm'] : 0 );
		$fe = $n( isset( $e['f_emballage'] ) ? $e['f_emballage'] : 0 );
		$fp = $n( isset( $e['f_pub'] ) ? $e['f_pub'] : 0 );
		$fa = $n( isset( $e['f_autres'] ) ? $e['f_autres'] : 0 );

		$cmd = floor( $n( isset( $e['n_commandes'] ) ? $e['n_commandes'] : 0 ) );
		$liv = floor( $n( isset( $e['n_livrees'] ) ? $e['n_livrees'] : 0 ) );
		$ann = floor( $n( isset( $e['n_annulees'] ) ? $e['n_annulees'] : 0 ) );
		$ret = floor( $n( isset( $e['n_retours'] ) ? $e['n_retours'] : 0 ) );

		$expediees = $liv + $ret;

		$ca            = $pv * $liv;
		$c_produit     = $pa * $liv;
		$c_livraison   = $fl * $liv;
		$c_retours     = $fl * $ret;
		$c_emballage   = $fe * $expediees;
		$c_confirm     = $fc * $cmd;
		$cout_total    = $c_produit + $c_livraison + $c_retours + $c_emballage + $c_confirm + $fp + $fa;

		$benefice_brut = $ca - $c_produit;
		$benefice_net  = $ca - $cout_total;

		$div = static function ( $a, $b ) {
			return $b > 0 ? $a / $b : 0.0;
		};

		return array(
			'ca'              => $ca,
			'cout_total'      => $cout_total,
			'cout_produit'    => $c_produit,
			'cout_pub'        => $fp,
			'cout_livraison'  => $c_livraison,
			'cout_retours'    => $c_retours,
			'cout_emballage'  => $c_emballage,
			'cout_confirm'    => $c_confirm,
			'cout_autres'     => $fa,
			'benefice_brut'   => $benefice_brut,
			'benefice_net'    => $benefice_net,
			'marge'           => $div( $benefice_net, $ca ) * 100,
			'cout_moyen'      => $div( $cout_total, $cmd ),
			'cpa'             => $div( $fp, $liv ),
			'roas'            => $div( $ca, $fp ),
			'taux_livraison'  => $div( $liv, $cmd ) * 100,
			'taux_annulation' => $div( $ann, $cmd ) * 100,
			'taux_retour'     => $div( $ret, $expediees ) * 100,
		);
	}

	/**
	 * Les entrées se contredisent-elles ?
	 *
	 * ⚠️ UN AVERTISSEMENT, JAMAIS UN REFUS. C'est un outil de simulation : on
	 * doit pouvoir taper des chiffres approximatifs, ou explorer un scénario
	 * avant d'avoir les vrais totaux. Bloquer la saisie ferait perdre le calcul
	 * en cours pour une incohérence que l'utilisateur est en train de corriger.
	 * Mais se taire serait pire : livrées + annulées + retours au-dessus du total
	 * reçu rend le taux de livraison faux sans que le nombre ait l'air anormal.
	 *
	 * @param array $e Entrées.
	 * @return string Message, ou chaîne vide.
	 */
	public static function coherence( $e ) {
		$i   = static function ( $v ) {
			return (int) max( 0, (float) ( is_scalar( $v ) ? $v : 0 ) );
		};
		$cmd = $i( isset( $e['n_commandes'] ) ? $e['n_commandes'] : 0 );
		$sum = $i( isset( $e['n_livrees'] ) ? $e['n_livrees'] : 0 )
			+ $i( isset( $e['n_annulees'] ) ? $e['n_annulees'] : 0 )
			+ $i( isset( $e['n_retours'] ) ? $e['n_retours'] : 0 );
		if ( $cmd > 0 && $sum > $cmd ) {
			return sprintf(
				/* translators: 1 : somme livrées + annulées + retours, 2 : commandes reçues */
				__( 'Livrées + annulées + retours font %1$d, au-dessus des %2$d commandes reçues. Les taux resteront calculés, mais ils ne voudront rien dire.', 'aod-client-dashboard' ),
				$sum,
				$cmd
			);
		}
		return '';
	}

	/* =====================================================================
	 * Rendu
	 * ================================================================== */

	public function render() {
		$outil   = isset( $_GET['outil'] ) ? sanitize_key( wp_unslash( $_GET['outil'] ) ) : '';
		$modules = self::modules();

		// Un outil inconnu, ou pas encore bâti, ramène au choix — jamais une page
		// blanche : une URL périmée dans un favori doit retomber sur quelque chose.
		if ( '' === $outil || ! isset( $modules[ $outil ] ) || 'pret' !== $modules[ $outil ]['etat'] ) {
			$this->render_hub( $modules );
			return;
		}
		if ( 'calculateur' === $outil ) {
			$this->render_calculateur();
		}
	}

	/** Le choix des outils. */
	protected function render_hub( $modules ) {
		$base = home_url( '/' . AOD_CD_SLUG . '/finance' );

		echo '<div class="aod-cd-fin">';
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput -- composant : libellés échappés à l'intérieur.
			__( 'Finance', 'aod-client-dashboard' ),
			__( 'Les outils qui répondent à une seule question : est-ce que cette boutique gagne de l’argent ?', 'aod-client-dashboard' )
		);

		echo '<div class="aod-cd-finmods">';
		foreach ( $modules as $cle => $m ) {
			$pret = ( 'pret' === $m['etat'] );
			$tag  = $pret ? 'a' : 'div';
			echo '<' . esc_attr( $tag ) . ' class="aod-cd-finmod' . ( $pret ? ' aod-cd-finmod--pret' : ' aod-cd-finmod--avenir' ) . '"'
				. ( $pret ? ' href="' . esc_url( add_query_arg( 'outil', $cle, $base ) ) . '"' : '' ) . '>';
			echo '<span class="aod-cd-finmod__ic" aria-hidden="true">' . AOD_CD_Icon::svg( $m['ic'], array( 'size' => 22 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
			echo '<span class="aod-cd-finmod__txt">';
			echo '<span class="aod-cd-finmod__t">' . esc_html( $m['titre'] ) . '</span>';
			echo '<span class="aod-cd-finmod__s">' . esc_html( $m['sous'] ) . '</span>';
			echo '</span>';
			echo $pret
				? '<span class="aod-cd-finmod__go" aria-hidden="true">' . AOD_CD_Icon::svg( 'trend-up', array( 'size' => 16 ) ) . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
				: '<span class="aod-cd-finmod__tag">' . esc_html__( 'À venir', 'aod-client-dashboard' ) . '</span>';
			echo '</' . esc_attr( $tag ) . '>';
		}
		echo '</div>';
		echo '</div>';
	}

	/** Le calculateur de bénéfices. */
	protected function render_calculateur() {
		$base   = home_url( '/' . AOD_CD_SLUG . '/finance' );
		$devise = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
		$hist   = $this->historique();

		echo '<div class="aod-cd-fin aod-cd-fincalc">';
		$retour = '<a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $base ) . '">'
			// `returned` est la flèche en U du catalogue — celle qui revient sur ses
			// pas. Elle est déjà MIROITÉE en RTL par `AOD_CD_Icon` : un retour
			// arrière pointe à droite en arabe, sans quoi il désignerait l'avant.
			. AOD_CD_Icon::svg( 'returned', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Finance', 'aod-client-dashboard' ) . '</a>';
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput -- composant : libellés échappés à l'intérieur.
			__( 'Calculateur de bénéfices', 'aod-client-dashboard' ),
			__( 'Renseignez vos chiffres : le résultat se met à jour à chaque frappe. Rien n’est enregistré tant que vous ne le demandez pas.', 'aod-client-dashboard' ),
			$retour
		);

		echo '<div class="aod-cd-fincalc__grid" data-devise="' . esc_attr( $devise ) . '">';

		/* ---- La saisie ---- */
		// ⚠️ PAS de `data-action` ICI, ET C'EST UN CLIQUET QUI L'A DIT.
		// `test-formulaires-branches.php` (écrit trois jours plus tôt, après que la
		// page WhatsApp eut cessé d'enregistrer en silence) exige que tout
		// `<form data-action>` porte `aod-cd-settings-form` — la classe que le binder
		// générique cherche. Ce formulaire-ci a son PROPRE binder, parce que sa
		// réponse porte les résultats recalculés par le serveur et doit être
		// confrontée à l'écran. Un `data-action` décoratif aurait donc annoncé un
		// branchement qui n'existe pas : exactement le mensonge que ce cliquet
		// existe pour attraper. L'action est nommée dans `section-finance.js`.
		echo '<form class="aod-cd-form aod-cd-fincalc__form" novalidate>';
		foreach ( self::champs() as $gcle => $g ) {
			echo '<fieldset class="aod-cd-finset aod-cd-finset--' . esc_attr( $gcle ) . '">';
			echo '<legend class="aod-cd-finset__lg">';
			echo '<span class="aod-cd-finset__ic" aria-hidden="true">' . AOD_CD_Icon::svg( $g['ic'], array( 'size' => 16 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
			echo '<span class="aod-cd-finset__t">' . esc_html( $g['titre'] ) . '</span>';
			echo '</legend>';
			echo '<p class="aod-cd-finset__s">' . esc_html( $g['aide'] ) . '</p>';
			echo '<div class="aod-cd-finset__body">';
			foreach ( $g['champs'] as $cle => $def ) {
				$money = ( 'money' === $def['t'] );
				echo '<label class="aod-cd-field aod-cd-finfield">';
				echo '<span class="aod-cd-label">' . esc_html( $def['l'] ) . '</span>';
				echo '<span class="aod-cd-finfield__wrap">';
				echo '<input type="number" class="aod-cd-code aod-cd-finfield__in" name="' . esc_attr( $cle ) . '"'
					. ' inputmode="' . ( $money ? 'decimal' : 'numeric' ) . '"'
					. ' step="' . ( $money ? '0.01' : '1' ) . '" min="0" value="" placeholder="0">';
				if ( $money && '' !== $devise ) {
					echo '<span class="aod-cd-finfield__unit" aria-hidden="true">' . esc_html( $devise ) . '</span>';
				}
				echo '</span>';
				echo '<span class="aod-cd-help">' . esc_html( $def['h'] ) . '</span>';
				echo '</label>';
			}
			echo '</div>';
			echo '</fieldset>';
		}

		echo '<p class="aod-cd-finwarn" hidden></p>';
		echo '<div class="aod-cd-fincalc__acts">';
		echo '<input type="text" class="aod-cd-fincalc__nom" name="titre" maxlength="60" placeholder="' . esc_attr__( 'Nommez ce calcul (facultatif)', 'aod-client-dashboard' ) . '" aria-label="' . esc_attr__( 'Nom du calcul', 'aod-client-dashboard' ) . '">';
		echo '<button type="submit" class="aod-cd-btn aod-cd-btn-primary aod-cd-fincalc__save">' . AOD_CD_Icon::svg( 'save', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Enregistrer ce calcul', 'aod-client-dashboard' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-fincalc__reset">' . esc_html__( 'Tout effacer', 'aod-client-dashboard' ) . '</button>';
		echo '<span class="aod-cd-form-msg"></span>';
		echo '</div>';
		echo '</form>';

		/* ---- Les résultats ---- */
		$this->render_resultats();
		echo '</div>'; // .grid

		$this->render_historique( $hist );
		echo '</div>';
	}

	/**
	 * Le panneau de résultats.
	 *
	 * ⚠️ CE BALISAGE N'EMPLOIE PAS `AOD_CD_Dashboard::kpis()`, ET C'EST DÉLIBÉRÉ.
	 * Le composant partagé rend des valeurs FIGÉES ; ici chaque nombre est réécrit
	 * à chaque frappe et doit donc porter son `data-res`. Ce sont les mêmes
	 * classes CSS — donc le même dessin, la même grammaire — mais un rendu
	 * différent. Étendre `kpis()` d'un identifiant par carte l'aurait alourdi pour
	 * un seul appelant, dans le fichier justement sous plafond de lignes.
	 */
	protected function render_resultats() {
		$tuiles = array(
			array( 'ca', __( 'Chiffre d’affaires', 'aod-client-dashboard' ), 'money', 'blue' ),
			array( 'benefice_net', __( 'Bénéfice net', 'aod-client-dashboard' ), 'trend-up', 'green' ),
			array( 'marge', __( 'Marge bénéficiaire', 'aod-client-dashboard' ), 'percent', 'indigo' ),
			array( 'roas', __( 'ROAS', 'aod-client-dashboard' ), 'conversion', 'amber' ),
		);

		echo '<div class="aod-cd-finres" aria-live="polite">';

		echo '<div class="aod-cd-kpis aod-cd-finres__kpis">';
		foreach ( $tuiles as $t ) {
			echo '<div class="aod-cd-kpi aod-cd-finkpi" data-tuile="' . esc_attr( $t[0] ) . '">';
			echo '<div class="aod-cd-kpi__top">';
			echo '<span class="aod-cd-kpi__ic t-' . esc_attr( $t[3] ) . '" aria-hidden="true">' . AOD_CD_Icon::svg( $t[2], array( 'size' => 18 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
			echo '<span class="aod-cd-kpi__lab">' . esc_html( $t[1] ) . '</span>';
			echo '</div>';
			echo '<div class="aod-cd-kpi__val" data-res="' . esc_attr( $t[0] ) . '">—</div>';
			echo '</div>';
		}
		echo '</div>';

		// Où part l'argent : une barre empilée vaut mieux qu'un tableau de sept
		// lignes qu'il faut additionner de tête pour voir laquelle domine.
		echo '<div class="aod-cd-chartcard aod-cd-fincost">';
		echo '<div class="aod-cd-chart-head"><h2 class="aod-cd-chart-title">' . esc_html__( 'Où part l’argent', 'aod-client-dashboard' ) . '</h2></div>';
		echo '<div class="aod-cd-fincost__bar" data-bar role="img" aria-label="' . esc_attr__( 'Répartition des coûts', 'aod-client-dashboard' ) . '"></div>';
		echo '<ul class="aod-cd-fincost__leg" data-leg></ul>';
		echo '</div>';

		// Les taux : trois jauges plutôt que trois nombres. Un taux se compare à
		// 100, et une barre le dit sans qu'on ait à y penser.
		echo '<div class="aod-cd-chartcard aod-cd-finrates">';
		echo '<div class="aod-cd-chart-head"><h2 class="aod-cd-chart-title">' . esc_html__( 'Vos taux', 'aod-client-dashboard' ) . '</h2></div>';
		foreach ( array(
			array( 'taux_livraison', __( 'Taux de livraison', 'aod-client-dashboard' ), 'ok' ),
			array( 'taux_annulation', __( 'Taux d’annulation', 'aod-client-dashboard' ), 'warn' ),
			array( 'taux_retour', __( 'Taux de retour', 'aod-client-dashboard' ), 'bad' ),
		) as $r ) {
			echo '<div class="aod-cd-finrate aod-cd-finrate--' . esc_attr( $r[2] ) . '">';
			echo '<span class="aod-cd-finrate__l">' . esc_html( $r[1] ) . '</span>';
			echo '<span class="aod-cd-finrate__v" data-res="' . esc_attr( $r[0] ) . '">—</span>';
			echo '<span class="aod-cd-finrate__track"><span class="aod-cd-finrate__fill" data-jauge="' . esc_attr( $r[0] ) . '"></span></span>';
			echo '</div>';
		}
		echo '</div>';

		// Le détail : tout ce que les quatre tuiles ne montrent pas.
		echo '<div class="aod-cd-chartcard aod-cd-findet">';
		echo '<div class="aod-cd-chart-head"><h2 class="aod-cd-chart-title">' . esc_html__( 'Le détail', 'aod-client-dashboard' ) . '</h2></div>';
		echo '<dl class="aod-cd-findet__l">';
		foreach ( array(
			array( 'cout_total', __( 'Coût total', 'aod-client-dashboard' ) ),
			array( 'benefice_brut', __( 'Bénéfice brut', 'aod-client-dashboard' ) ),
			array( 'cout_pub', __( 'Dépenses publicitaires', 'aod-client-dashboard' ) ),
			array( 'cout_livraison', __( 'Coût de livraison', 'aod-client-dashboard' ) ),
			array( 'cout_retours', __( 'Coût des retours', 'aod-client-dashboard' ) ),
			array( 'cout_moyen', __( 'Coût moyen par commande', 'aod-client-dashboard' ) ),
			array( 'cpa', __( 'Coût d’acquisition client', 'aod-client-dashboard' ) ),
		) as $d ) {
			echo '<div class="aod-cd-findet__row">';
			echo '<dt>' . esc_html( $d[1] ) . '</dt>';
			echo '<dd data-res="' . esc_attr( $d[0] ) . '">—</dd>';
			echo '</div>';
		}
		echo '</dl>';
		echo '</div>';

		echo '</div>';
	}

	/** L'historique des calculs enregistrés. */
	protected function render_historique( $hist ) {
		echo '<section class="aod-cd-finhist" data-hist>';
		echo '<h2 class="aod-cd-finhist__t">' . esc_html__( 'Calculs enregistrés', 'aod-client-dashboard' ) . '</h2>';
		if ( empty( $hist ) ) {
			echo AOD_CD_Dashboard::empty_state( 'note', __( 'Aucun calcul enregistré.', 'aod-client-dashboard' ), __( 'Enregistrez une simulation pour la retrouver ici et la recharger d’un clic.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- composant : SVG du catalogue fermé + libellés échappés.
			echo '</section>';
			return;
		}
		echo '<ul class="aod-cd-finhist__l">';
		foreach ( $hist as $c ) {
			echo '<li class="aod-cd-finhist__i" data-calc="' . esc_attr( wp_json_encode( $c['entrees'] ) ) . '" data-id="' . esc_attr( $c['id'] ) . '">';
			echo '<span class="aod-cd-finhist__nom">' . esc_html( $c['titre'] ) . '</span>';
			echo '<span class="aod-cd-finhist__net' . ( $c['net'] < 0 ? ' is-bad' : '' ) . '">' . wp_kses_post( self::prix( $c['net'] ) ) . '</span>';
			echo '<span class="aod-cd-finhist__at">' . esc_html( $c['at'] ) . '</span>';
			echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-finhist__load">' . esc_html__( 'Recharger', 'aod-client-dashboard' ) . '</button>';
			echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-btn--ico aod-cd-finhist__del" aria-label="' . esc_attr__( 'Supprimer ce calcul', 'aod-client-dashboard' ) . '">' . AOD_CD_Icon::svg( 'trash', array( 'size' => 15 ) ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
			echo '</li>';
		}
		echo '</ul>';
		echo '</section>';
	}

	/* =====================================================================
	 * Stockage
	 * ================================================================== */

	/**
	 * Les calculs enregistrés, du plus récent au plus ancien.
	 *
	 * @return array[]
	 */
	protected function historique() {
		$brut = get_option( self::OPTION_CALCULS, array() );
		if ( ! is_array( $brut ) ) {
			return array();
		}
		$out = array();
		foreach ( $brut as $c ) {
			if ( ! is_array( $c ) || empty( $c['id'] ) || ! isset( $c['entrees'] ) || ! is_array( $c['entrees'] ) ) {
				continue;
			}
			$out[] = array(
				'id'      => (string) $c['id'],
				'titre'   => isset( $c['titre'] ) && '' !== $c['titre'] ? (string) $c['titre'] : __( 'Sans nom', 'aod-client-dashboard' ),
				'at'      => isset( $c['at'] ) ? date_i18n( 'd/m/Y H:i', (int) $c['at'] ) : '',
				'net'     => isset( $c['net'] ) ? (float) $c['net'] : 0.0,
				'entrees' => array_map( 'floatval', $c['entrees'] ),
			);
		}
		return $out;
	}

	/** Un montant, dans la devise de la boutique. */
	protected static function prix( $v ) {
		return function_exists( 'wc_price' ) ? wc_price( (float) $v ) : esc_html( number_format_i18n( (float) $v, 2 ) );
	}

	/**
	 * Lit les entrées depuis le POST, en s'adossant à `cles_saisie()`.
	 *
	 * 🔒 Le nonce est vérifié par l'APPELANT, jamais ici : cette méthode ne fait
	 * que convertir, elle ne décide de rien. Elle reste `private` pour que ce
	 * contrat ne puisse pas être contourné depuis l'extérieur de la classe —
	 * même règle que `AOD_CD_Section_Marketing::capi_token_from_post()`.
	 *
	 * @return array<string,float>
	 */
	private static function entrees_du_post() {
		$out = array();
		foreach ( self::cles_saisie() as $cle => $type ) {
			$v = isset( $_POST[ $cle ] ) ? (float) wp_unslash( $_POST[ $cle ] ) : 0.0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié par l'appelant (contrat de la méthode, cf. docbloc).
			if ( ! is_finite( $v ) || $v < 0 ) {
				$v = 0.0;
			}
			$out[ $cle ] = ( 'int' === $type ) ? (float) floor( $v ) : round( $v, 2 );
		}
		return $out;
	}

	/**
	 * AJAX : enregistre un calcul.
	 *
	 * 🚨 LE SERVEUR RECALCULE, IL NE CROIT PAS L'ÉCRAN. Rien de ce que le
	 * navigateur a affiché n'est stocké : seules les ENTRÉES traversent le
	 * réseau, et `compute()` refait le travail. C'est ce qui rend la duplication
	 * de formules supportable — le JS peut se tromper sans polluer la base — et
	 * c'est aussi ce qui permet à la réponse de porter les résultats du serveur,
	 * que le JS compare aux siens pour signaler une divergence.
	 */
	public function ajax_finance_save() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$entrees = self::entrees_du_post();
		$res     = self::compute( $entrees );

		$titre = isset( $_POST['titre'] ) ? sanitize_text_field( wp_unslash( $_POST['titre'] ) ) : '';
		$titre = trim( mb_substr( $titre, 0, 60 ) );
		if ( '' === $titre ) {
			$titre = sprintf(
				/* translators: %s : date et heure du calcul */
				__( 'Calcul du %s', 'aod-client-dashboard' ),
				date_i18n( 'd/m/Y H:i' )
			);
		}

		$liste = get_option( self::OPTION_CALCULS, array() );
		$liste = is_array( $liste ) ? $liste : array();
		array_unshift( $liste, array(
			'id'      => uniqid( 'fc', true ),
			'titre'   => $titre,
			'at'      => time(),
			'net'     => $res['benefice_net'],
			'entrees' => $entrees,
		) );
		$liste = array_slice( $liste, 0, self::MAX_HISTORIQUE );
		// `false` en 3ᵉ argument : jamais autoloadée. Cette liste ne sert qu'à une
		// page ; la charger sur CHAQUE requête du site serait payer un historique
		// de simulations sur le temps de réponse de la boutique.
		update_option( self::OPTION_CALCULS, $liste, false );

		wp_send_json_success( array(
			'message'   => __( 'Calcul enregistré.', 'aod-client-dashboard' ),
			'resultats' => $res,
		) );
	}

	/** AJAX : supprime un calcul enregistré. */
	public function ajax_finance_delete() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}
		$id = isset( $_POST['calc'] ) ? sanitize_text_field( wp_unslash( $_POST['calc'] ) ) : '';
		if ( '' === $id ) {
			wp_send_json_error( array( 'message' => __( 'Calcul introuvable.', 'aod-client-dashboard' ) ) );
		}
		$liste = get_option( self::OPTION_CALCULS, array() );
		$liste = is_array( $liste ) ? $liste : array();
		$reste = array();
		foreach ( $liste as $c ) {
			if ( is_array( $c ) && isset( $c['id'] ) && (string) $c['id'] === $id ) {
				continue;
			}
			$reste[] = $c;
		}
		update_option( self::OPTION_CALCULS, $reste, false );
		wp_send_json_success( array( 'message' => __( 'Calcul supprimé.', 'aod-client-dashboard' ) ) );
	}
}
