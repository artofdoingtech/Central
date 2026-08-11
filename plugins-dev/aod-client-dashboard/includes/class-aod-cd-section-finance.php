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
		add_action( 'wp_ajax_aod_cd_finance_produits', array( $this, 'ajax_finance_produits' ) );
		add_action( 'wp_ajax_aod_cd_finance_depense_add', array( $this, 'ajax_depense_add' ) );
		add_action( 'wp_ajax_aod_cd_finance_depense_del', array( $this, 'ajax_depense_del' ) );
		add_action( 'wp_ajax_aod_cd_finance_entreprise', array( $this, 'ajax_entreprise' ) );
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
			'produits'    => array(
				'ic'    => 'products',
				'titre' => __( 'Rentabilité par produit', 'aod-client-dashboard' ),
				'sous'  => __( 'Quels produits vous rapportent vraiment, une fois leur coût, leur livraison et leur publicité déduits.', 'aod-client-dashboard' ),
				'etat'  => 'pret',
			),
			'depenses'    => array(
				'ic'    => 'note-edit',
				'titre' => __( 'Dépenses', 'aod-client-dashboard' ),
				'sous'  => __( 'Publicité, livraison, salaires, emballage, fournisseurs, abonnements — saisies une fois, retrouvées par mois.', 'aod-client-dashboard' ),
				'etat'  => 'pret',
			),
			'entreprise'  => array(
				'ic'    => 'storefront',
				'titre' => __( 'Entreprise', 'aod-client-dashboard' ),
				'sous'  => __( 'Coordonnées, informations fiscales et bancaires de la société.', 'aod-client-dashboard' ),
				'etat'  => 'pret',
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
		} elseif ( 'produits' === $outil ) {
			$this->render_produits();
		} elseif ( 'depenses' === $outil ) {
			$this->render_depenses();
		} elseif ( 'entreprise' === $outil ) {
			$this->render_entreprise();
		}
	}

	/* =====================================================================
	 * Rentabilité par produit
	 * ================================================================== */

	/** Option : les deux taux globaux (confirmation, emballage). */
	const OPTION_TAUX = 'aod_cd_finance_taux';

	/** Option : budget publicitaire par mois et par produit. */
	const OPTION_PUB = 'aod_cd_finance_pub';

	/** Plafond de commandes lues sur un mois — cf. la note de `produits_data()`. */
	const MAX_COMMANDES = 800;

	/**
	 * Le mois affiché, au format `AAAA-MM`.
	 *
	 * ⚠️ UN MOIS, PAS UNE PLAGE LIBRE, ET C'EST LA DONNÉE QUI L'IMPOSE. Le budget
	 * publicitaire est saisi par le gérant *pour une période* ; le laisser choisir
	 * « du 12 au 27 » obligerait à répartir un budget mensuel au prorata des
	 * jours — une hypothèse de plus, invisible, dans un écran dont tout l'intérêt
	 * est de ne rien supposer. Un mois est aussi l'unité dans laquelle la
	 * publicité s'achète réellement.
	 *
	 * ⚠️ ELLE NE S'APPELLE PLUS `produits_mois()`. Les Dépenses lisent le même
	 * paramètre d'URL, avec la même validation et le même repli sur le mois en
	 * cours : deux corps auraient divergé au premier format accepté d'un côté et
	 * refusé de l'autre — et un lien passant d'un écran à l'autre aurait changé de
	 * mois en silence. Une méthode nommée d'après un seul de ses lecteurs invite
	 * le suivant à s'en écrire une.
	 *
	 * @return string
	 */
	protected function mois_affiche() {
		$m = isset( $_GET['mois'] ) ? sanitize_text_field( wp_unslash( $_GET['mois'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $m ) ) {
			$m = gmdate( 'Y-m', (int) current_time( 'timestamp' ) );
		}
		return $m;
	}

	/** Les deux taux globaux, en nombres. */
	protected function taux() {
		$t = get_option( self::OPTION_TAUX, array() );
		$t = is_array( $t ) ? $t : array();
		return array(
			'confirm'   => isset( $t['confirm'] ) ? (float) $t['confirm'] : 0.0,
			'emballage' => isset( $t['emballage'] ) ? (float) $t['emballage'] : 0.0,
		);
	}

	/** Budgets publicitaires saisis pour ce mois : `pid => montant`. */
	protected function pub_mois( $mois ) {
		$all = get_option( self::OPTION_PUB, array() );
		$all = is_array( $all ) && isset( $all[ $mois ] ) && is_array( $all[ $mois ] ) ? $all[ $mois ] : array();
		$out = array();
		foreach ( $all as $pid => $v ) {
			$out[ (int) $pid ] = (float) $v;
		}
		return $out;
	}

	/**
	 * Les chiffres du mois, produit par produit.
	 *
	 * 🚨 « ENCAISSÉ », PAS « CHIFFRE D'AFFAIRES » — ET LA DIFFÉRENCE EST VOULUE.
	 * L'Accueil compte le CA sur les commandes en cours, confirmées ET terminées :
	 * c'est le bon chiffre pour savoir ce que la boutique produit. Ici on parle de
	 * BÉNÉFICE, donc d'argent réellement rentré : en paiement à la livraison, une
	 * commande non livrée n'a rien rapporté et a déjà coûté. Les deux écrans
	 * donneront donc des nombres différents pour le même mois, et c'est correct —
	 * d'où deux NOMS différents. *Deux quantités distinctes qui portent le même
	 * libellé se lisent comme une contradiction ; correctement nommées, elles se
	 * lisent comme deux faits.*
	 *
	 * ⚠️ Est « encaissée » une commande TERMINÉE, ou dont le colis est marqué
	 * livré par le transporteur. Les deux, parce que toutes les boutiques
	 * n'expédient pas via un transporteur intégré : ne garder que le suivi
	 * viderait l'écran chez celles qui livrent elles-mêmes, ne garder que le
	 * statut Woo l'aurait vidé chez celles qui ne repassent jamais les commandes
	 * en « terminée ».
	 *
	 * ⚠️ LA LIVRAISON EST RÉPARTIE AU PRORATA DU CA DE CHAQUE LIGNE. Sur une
	 * commande à un seul produit — le cas courant en COD — cette règle est exacte.
	 * Sur une commande à plusieurs lignes, c'est un choix : il est écrit à l'écran
	 * plutôt que caché ici.
	 *
	 * @param string $mois `AAAA-MM`.
	 * @return array{lignes:array,total:array,tronque:bool,sans_cout:bool}
	 */
	protected function produits_data( $mois ) {
		$debut = $mois . '-01 00:00:00';
		$fin   = gmdate( 'Y-m-t 23:59:59', (int) strtotime( $debut ) );
		$args  = array(
			'type'         => 'shop_order',
			'limit'        => self::MAX_COMMANDES + 1,
			'return'       => 'objects',
			'date_created' => strtotime( $debut ) . '...' . strtotime( $fin ),
		);

		$commandes = array();
		$sec       = AOD_CD_Section_Orders::instance();

		$termines = $sec->scoped_orders( array_merge( $args, array( 'status' => array( 'completed' ) ) ) );
		foreach ( (array) $termines as $o ) {
			if ( $o instanceof WC_Order ) {
				$commandes[ $o->get_id() ] = $o;
			}
		}
		$livres = $sec->scoped_orders( array_merge( $args, array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- clé de suivi indexée, une requête par affichage d'écran.
				array(
					'key'   => AOD_Carrier::META_STATUS,
					'value' => AOD_Carrier::ST_DELIVERED,
				),
			),
		) ) );
		foreach ( (array) $livres as $o ) {
			if ( $o instanceof WC_Order ) {
				$commandes[ $o->get_id() ] = $o;
			}
		}

		// 🔎 Le plafond est ANNONCÉ, jamais silencieux : une troncature muette
		// ferait lire « voici votre mois » sur un mois incomplet.
		$tronque = count( $commandes ) > self::MAX_COMMANDES;
		if ( $tronque ) {
			$commandes = array_slice( $commandes, 0, self::MAX_COMMANDES, true );
		}

		$taux      = $this->taux();
		$home      = AOD_CD_Section_Home::instance();
		$lignes    = array();
		$sans_cout = false;
		$n_cmd     = count( $commandes );

		foreach ( $commandes as $o ) {
			$items    = $o->get_items();
			$ca_cmd   = 0.0;
			$par_item = array();
			foreach ( $items as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$rev              = (float) $item->get_total();
				$par_item[]       = array( 'item' => $item, 'rev' => $rev );
				$ca_cmd          += $rev;
			}
			if ( ! $par_item ) {
				continue;
			}
			$port = (float) $o->get_shipping_total();

			foreach ( $par_item as $l ) {
				$item = $l['item'];
				$pid  = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
				$pid  = (int) $pid;
				if ( ! isset( $lignes[ $pid ] ) ) {
					$lignes[ $pid ] = array(
						'nom'       => $item->get_name(),
						'qte'       => 0,
						'ca'        => 0.0,
						'cout'      => 0.0,
						'cout_su'   => true,
						'livraison' => 0.0,
						'annexes'   => 0.0,
						'cmd'       => 0,
					);
				}
				$qte                          = (int) $item->get_quantity();
				$lignes[ $pid ]['qte']       += $qte;
				$lignes[ $pid ]['ca']        += $l['rev'];
				$lignes[ $pid ]['cmd']       += 1;
				$lignes[ $pid ]['livraison'] += ( $ca_cmd > 0 ) ? $port * ( $l['rev'] / $ca_cmd ) : 0.0;
				$lignes[ $pid ]['annexes']   += $taux['confirm'] + ( $taux['emballage'] * $qte );

				$cu = $home->item_cost_price( $item );
				if ( null === $cu ) {
					$lignes[ $pid ]['cout_su'] = false;
					$sans_cout                 = true;
				} else {
					$lignes[ $pid ]['cout'] += $cu * $qte;
				}
			}
		}

		$pub   = $this->pub_mois( $mois );
		$total = array( 'ca' => 0.0, 'cout' => 0.0, 'livraison' => 0.0, 'annexes' => 0.0, 'pub' => 0.0, 'net' => 0.0, 'net_su' => true );

		foreach ( $lignes as $pid => &$l ) {
			$l['pid'] = $pid;
			$l['pub'] = isset( $pub[ $pid ] ) ? $pub[ $pid ] : null;
			// Le bénéfice n'existe que si TOUT ce qu'il retranche est connu. Un
			// prix d'achat ou un budget publicitaire absent rend « — » : c'est le
			// motif que la colonne « Marge » de l'Accueil emploie déjà, et il vaut
			// mieux qu'un nombre calculé sur un coût qu'on a pris pour zéro.
			$l['net'] = ( $l['cout_su'] && null !== $l['pub'] )
				? $l['ca'] - $l['cout'] - $l['livraison'] - $l['annexes'] - $l['pub']
				: null;
			$l['marge'] = ( null !== $l['net'] && $l['ca'] > 0 ) ? ( $l['net'] / $l['ca'] ) * 100 : null;

			$total['ca']        += $l['ca'];
			$total['livraison'] += $l['livraison'];
			$total['annexes']   += $l['annexes'];
			if ( $l['cout_su'] ) {
				$total['cout'] += $l['cout'];
			}
			if ( null !== $l['pub'] ) {
				$total['pub'] += $l['pub'];
			}
			if ( null === $l['net'] ) {
				$total['net_su'] = false;
			} else {
				$total['net'] += $l['net'];
			}
		}
		unset( $l );

		uasort( $lignes, static function ( $a, $b ) {
			return ( $b['ca'] < $a['ca'] ) ? -1 : ( ( $b['ca'] > $a['ca'] ) ? 1 : 0 );
		} );

		return array(
			'lignes'    => $lignes,
			'total'     => $total,
			'tronque'   => $tronque,
			'sans_cout' => $sans_cout,
			'n_cmd'     => $n_cmd,
		);
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

	/** L'écran « Rentabilité par produit ». */
	protected function render_produits() {
		$base = home_url( '/' . AOD_CD_SLUG . '/finance' );
		$mois = $this->mois_affiche();
		$d    = $this->produits_data( $mois );
		$taux = $this->taux();

		echo '<div class="aod-cd-fin aod-cd-finprod">';
		$retour = '<a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $base ) . '">'
			. AOD_CD_Icon::svg( 'returned', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Finance', 'aod-client-dashboard' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput -- composant : libellés échappés à l'intérieur.
			__( 'Rentabilité par produit', 'aod-client-dashboard' ),
			__( 'Calculé sur les commandes réellement encaissées du mois. Renseignez le budget publicitaire de chaque produit pour obtenir son bénéfice.', 'aod-client-dashboard' ),
			$retour
		);

		// Le mois : un formulaire GET natif, comme les autres barres de filtres.
		echo '<form class="aod-cd-form aod-cd-filters" method="get" action="' . esc_url( $base ) . '">';
		echo '<input type="hidden" name="outil" value="produits">';
		echo '<div class="aod-cd-field"><label for="aod-cd-f-mois">' . esc_html__( 'Mois', 'aod-client-dashboard' ) . '</label>';
		echo '<input type="month" id="aod-cd-f-mois" name="mois" value="' . esc_attr( $mois ) . '"></div>';
		echo '<div class="aod-cd-field aod-cd-filters-actions"><button type="submit" class="aod-cd-btn aod-cd-btn-primary">' . esc_html__( 'Afficher', 'aod-client-dashboard' ) . '</button></div>';
		echo '</form>';

		if ( $d['tronque'] ) {
			echo '<p class="aod-cd-finwarn">' . esc_html( sprintf(
				/* translators: %d : nombre de commandes lues */
				__( 'Ce mois dépasse %d commandes encaissées : seules les premières sont comptées. Les chiffres ci-dessous sont donc incomplets.', 'aod-client-dashboard' ),
				self::MAX_COMMANDES
			) ) . '</p>';
		}

		if ( empty( $d['lignes'] ) ) {
			echo AOD_CD_Dashboard::empty_state( 'chart', __( 'Aucune commande encaissée sur ce mois.', 'aod-client-dashboard' ), __( 'Une commande compte ici lorsqu’elle est terminée, ou que son colis est marqué livré par le transporteur.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- composant.
			echo '</div>';
			return;
		}

		// UN SEUL FORMULAIRE pour tout l'écran : les deux taux et les budgets
		// publicitaires de chaque ligne. Il emploie le binder GÉNÉRIQUE
		// (`aod-cd-settings-form`), donc aucun JavaScript propre à cette page —
		// et aucune formule dupliquée, puisque le bénéfice est recalculé côté
		// serveur au rechargement.
		echo '<form class="aod-cd-form aod-cd-settings-form aod-cd-finprod__form" data-action="aod_cd_finance_produits">';
		echo '<input type="hidden" name="mois" value="' . esc_attr( $mois ) . '">';

		echo '<fieldset class="aod-cd-finset"><legend class="aod-cd-finset__lg">';
		echo '<span class="aod-cd-finset__ic" aria-hidden="true">' . AOD_CD_Icon::svg( 'cart', array( 'size' => 16 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo '<span class="aod-cd-finset__t">' . esc_html__( 'Vos coûts par commande', 'aod-client-dashboard' ) . '</span></legend>';
		echo '<p class="aod-cd-finset__s">' . esc_html__( 'Les mêmes pour tous les produits. Ils sont retranchés du bénéfice de chaque ligne, au nombre de commandes et de pièces.', 'aod-client-dashboard' ) . '</p>';
		echo '<div class="aod-cd-finset__body">';
		foreach ( array(
			array( 'confirm', __( 'Frais de confirmation (par commande)', 'aod-client-dashboard' ), $taux['confirm'] ),
			array( 'emballage', __( 'Frais d’emballage (par pièce)', 'aod-client-dashboard' ), $taux['emballage'] ),
		) as $t ) {
			echo '<label class="aod-cd-field aod-cd-finfield"><span class="aod-cd-label">' . esc_html( $t[1] ) . '</span>';
			echo '<span class="aod-cd-finfield__wrap"><input type="number" step="0.01" min="0" class="aod-cd-code aod-cd-finfield__in" name="taux_' . esc_attr( $t[0] ) . '" value="' . esc_attr( $t[2] > 0 ? (string) $t[2] : '' ) . '" placeholder="0"></span></label>';
		}
		echo '</div></fieldset>';

		echo '<div class="aod-cd-tablewrap"><table class="aod-cd-table aod-cd-table--cards aod-cd-finprod__t"><thead><tr>';
		$cols = array(
			__( 'Produit', 'aod-client-dashboard' ),
			__( 'Quantité', 'aod-client-dashboard' ),
			__( 'Encaissé', 'aod-client-dashboard' ),
			__( 'Coût produit', 'aod-client-dashboard' ),
			__( 'Livraison', 'aod-client-dashboard' ),
			__( 'Frais par commande', 'aod-client-dashboard' ),
			__( 'Publicité', 'aod-client-dashboard' ),
			__( 'Bénéfice', 'aod-client-dashboard' ),
			__( 'Marge', 'aod-client-dashboard' ),
		);
		foreach ( $cols as $c ) {
			echo '<th>' . esc_html( $c ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $d['lignes'] as $pid => $l ) {
			$net_cls = ( null === $l['net'] ) ? '' : ( $l['net'] < 0 ? ' is-bad' : ' is-ok' );
			echo '<tr>';
			echo '<td data-label="' . esc_attr( $cols[0] ) . '">' . esc_html( $l['nom'] ) . '</td>';
			echo '<td data-label="' . esc_attr( $cols[1] ) . '">' . (int) $l['qte'] . '</td>';
			echo '<td data-label="' . esc_attr( $cols[2] ) . '">' . wp_kses_post( self::prix( $l['ca'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr( $cols[3] ) . '">' . ( $l['cout_su'] ? wp_kses_post( self::prix( $l['cout'] ) ) : '<span class="aod-cd-finprod__vide" title="' . esc_attr__( 'Prix d’achat non renseigné pour ce produit.', 'aod-client-dashboard' ) . '">—</span>' ) . '</td>';
			echo '<td data-label="' . esc_attr( $cols[4] ) . '">' . wp_kses_post( self::prix( $l['livraison'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr( $cols[5] ) . '">' . wp_kses_post( self::prix( $l['annexes'] ) ) . '</td>';
			echo '<td class="aod-cd-finprod__pub" data-label="' . esc_attr( $cols[6] ) . '">';
			echo '<input type="number" step="0.01" min="0" class="aod-cd-code" name="pub[' . esc_attr( (string) $pid ) . ']" value="' . esc_attr( null === $l['pub'] ? '' : (string) $l['pub'] ) . '" placeholder="0" aria-label="' . esc_attr( sprintf( /* translators: %s : nom du produit */ __( 'Budget publicitaire — %s', 'aod-client-dashboard' ), $l['nom'] ) ) . '">';
			echo '</td>';
			echo '<td class="aod-cd-finprod__net' . esc_attr( $net_cls ) . '" data-label="' . esc_attr( $cols[7] ) . '">' . ( null === $l['net'] ? '<span class="aod-cd-finprod__vide">—</span>' : wp_kses_post( self::prix( $l['net'] ) ) ) . '</td>';
			echo '<td data-label="' . esc_attr( $cols[8] ) . '">' . ( null === $l['marge'] ? '<span class="aod-cd-finprod__vide">—</span>' : esc_html( number_format_i18n( $l['marge'], 1 ) . ' %' ) ) . '</td>';
			echo '</tr>';
		}

		$t = $d['total'];
		echo '<tr class="aod-cd-fintot">';
		// Même correctif que la ligne de total des Dépenses, et pour la même
		// raison : en carte, `data-label` intitule la ligne — « PRODUIT — Total »
		// annonçait un produit qui n'en est pas un. Propagé ici le même jour :
		// c'est le mode de dérive que ce dépôt passe son temps à fermer.
		echo '<td><strong>' . esc_html__( 'Total', 'aod-client-dashboard' ) . '</strong></td>';
		echo '<td data-label="' . esc_attr( $cols[1] ) . '">—</td>';
		echo '<td data-label="' . esc_attr( $cols[2] ) . '">' . wp_kses_post( self::prix( $t['ca'] ) ) . '</td>';
		echo '<td data-label="' . esc_attr( $cols[3] ) . '">' . wp_kses_post( self::prix( $t['cout'] ) ) . '</td>';
		echo '<td data-label="' . esc_attr( $cols[4] ) . '">' . wp_kses_post( self::prix( $t['livraison'] ) ) . '</td>';
		echo '<td data-label="' . esc_attr( $cols[5] ) . '">' . wp_kses_post( self::prix( $t['annexes'] ) ) . '</td>';
		echo '<td data-label="' . esc_attr( $cols[6] ) . '">' . wp_kses_post( self::prix( $t['pub'] ) ) . '</td>';
		echo '<td class="aod-cd-finprod__net' . ( $t['net_su'] && $t['net'] < 0 ? ' is-bad' : ( $t['net_su'] ? ' is-ok' : '' ) ) . '" data-label="' . esc_attr( $cols[7] ) . '">' . ( $t['net_su'] ? wp_kses_post( self::prix( $t['net'] ) ) : '<span class="aod-cd-finprod__vide">—</span>' ) . '</td>';
		echo '<td data-label="' . esc_attr( $cols[8] ) . '">' . ( $t['net_su'] && $t['ca'] > 0 ? esc_html( number_format_i18n( ( $t['net'] / $t['ca'] ) * 100, 1 ) . ' %' ) : '<span class="aod-cd-finprod__vide">—</span>' ) . '</td>';
		echo '</tr>';
		echo '</tbody></table></div>';

		echo '<div class="aod-cd-form-foot aod-cd-savebar">';
		echo '<button type="submit" class="aod-cd-btn aod-cd-btn-primary">' . esc_html__( 'Enregistrer et recalculer', 'aod-client-dashboard' ) . '</button>';
		echo '<span class="aod-cd-form-msg"></span>';
		echo '</div>';
		echo '</form>';

		// 🚨 CE QUE L'ÉCRAN COMPTE, ÉCRIT SUR L'ÉCRAN. Un tableau de rentabilité
		// sans sa définition invite à comparer des nombres qui ne mesurent pas la
		// même chose — et « Encaissé » DIFFÈRE volontairement du « Chiffre
		// d'affaires » de l'Accueil. Le taire produirait une contradiction
		// apparente entre deux pages du même tableau de bord.
		echo '<div class="aod-cd-finprod__notes">';
		echo '<p class="aod-cd-note">' . esc_html( sprintf(
			/* translators: %d : nombre de commandes prises en compte */
			__( '« Encaissé » = commandes terminées, ou dont le colis est marqué livré (%d ce mois-ci). Ce n’est pas le « Chiffre d’affaires » de l’Accueil, qui compte aussi les commandes en cours : ici on ne parle que d’argent réellement rentré.', 'aod-client-dashboard' ),
			(int) $d['n_cmd']
		) ) . '</p>';
		echo '<p class="aod-cd-note">' . esc_html__( 'Bénéfice = encaissé − coût produit − livraison − frais par commande − publicité. Les frais de livraison réels de chaque commande sont répartis entre ses produits au prorata de leur montant.', 'aod-client-dashboard' ) . '</p>';
		echo '<p class="aod-cd-note">' . esc_html__( 'Un « — » signifie qu’il manque une donnée : le prix d’achat du produit, ou son budget publicitaire du mois. Le bénéfice n’est jamais estimé à partir d’un coût inconnu.', 'aod-client-dashboard' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * AJAX : enregistre les deux taux et les budgets publicitaires du mois.
	 *
	 * ⚠️ Le budget est rangé PAR MOIS. Écraser une seule table `pid => montant`
	 * ferait qu'un mois consulté après coup afficherait le budget du mois en
	 * cours — un bénéfice passé qui change tout seul quand on achète de la
	 * publicité aujourd'hui.
	 */
	public function ajax_finance_produits() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$mois = isset( $_POST['mois'] ) ? sanitize_text_field( wp_unslash( $_POST['mois'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $mois ) ) {
			wp_send_json_error( array( 'message' => __( 'Mois invalide.', 'aod-client-dashboard' ) ) );
		}

		$nombre = static function ( $v ) {
			$v = (float) $v;
			return ( is_finite( $v ) && $v > 0 ) ? round( $v, 2 ) : 0.0;
		};

		update_option( self::OPTION_TAUX, array(
			'confirm'   => $nombre( isset( $_POST['taux_confirm'] ) ? wp_unslash( $_POST['taux_confirm'] ) : 0 ),
			'emballage' => $nombre( isset( $_POST['taux_emballage'] ) ? wp_unslash( $_POST['taux_emballage'] ) : 0 ),
		), false );

		$all = get_option( self::OPTION_PUB, array() );
		$all = is_array( $all ) ? $all : array();
		$mo  = array();
		if ( isset( $_POST['pub'] ) && is_array( $_POST['pub'] ) ) {
			foreach ( wp_unslash( $_POST['pub'] ) as $pid => $v ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- clé castée en entier, valeur en flottant borné, juste dessous.
				$pid = (int) $pid;
				// Une case laissée VIDE n'est pas zéro : c'est « je ne sais pas »,
				// et le bénéfice doit rester « — ». Les confondre afficherait un
				// bénéfice flatteur pour tout produit dont on n'a pas saisi la pub.
				if ( $pid <= 0 || '' === trim( (string) $v ) ) {
					continue;
				}
				$mo[ $pid ] = $nombre( $v );
			}
		}
		$all[ $mois ] = $mo;
		update_option( self::OPTION_PUB, $all, false );

		wp_send_json_success( array( 'message' => __( 'Enregistré. Les bénéfices sont recalculés.', 'aod-client-dashboard' ), 'reload' => true ) );
	}

	/* =====================================================================
	 * Dépenses
	 * ================================================================== */

	/** Option : le registre des dépenses, rangé par mois. */
	const OPTION_DEPENSES = 'aod_cd_finance_depenses';

	/**
	 * Combien de lignes un mois accepte.
	 *
	 * ⚠️ UN REFUS, PAS UNE TRONCATURE — et la différence avec `MAX_HISTORIQUE`
	 * est de nature, pas de réglage. L'historique du calculateur garde des
	 * SIMULATIONS : en perdre la plus ancienne ne coûte rien. Une dépense est une
	 * ÉCRITURE : la jeter pour faire de la place effacerait un fait, et le total
	 * du mois deviendrait faux sans que rien ne le dise — le registre paraîtrait
	 * complet en étant amputé. Au plafond, l'ajout est REFUSÉ et l'écran le dit.
	 */
	const MAX_DEPENSES_MOIS = 200;

	/**
	 * Les postes de dépense.
	 *
	 * ⚠️ LES MÊMES MOTS QUE `postes_i18n()`, VOLONTAIREMENT. « Publicité »,
	 * « Livraison », « Emballage » désignent ici ce qu'ils désignent dans le
	 * calculateur : un gérant qui passe d'un écran à l'autre ne doit pas avoir à
	 * traduire son propre vocabulaire. Un synonyme (« Frais de port », « Colis »)
	 * se lirait comme une autre catégorie.
	 *
	 * 🚨 « PUBLICITÉ » EXISTE AUSSI DANS « RENTABILITÉ PAR PRODUIT », ET LES DEUX
	 * NE SE RECOPIENT PAS. Là-bas le budget est saisi PAR PRODUIT, pour calculer
	 * le bénéfice de chaque ligne ; ici c'est la dépense de la boutique, tous
	 * produits confondus — y compris ce qui n'est attribuable à aucun (une
	 * campagne de marque). Les synchroniser ferait qu'une saisie ici changerait un
	 * bénéfice là-bas sans qu'on l'ait demandé. Ce qu'on fait à la place : le
	 * total des budgets par produit est ÉCRIT sous le tableau, et l'écart est
	 * nommé quand il y en a un. *Deux nombres qui mesurent deux choses se
	 * réconcilient par une phrase, jamais par une recopie.*
	 *
	 * @return array<string,string>
	 */
	public static function depenses_cats() {
		return array(
			'publicite'    => __( 'Publicité', 'aod-client-dashboard' ),
			'livraison'    => __( 'Livraison', 'aod-client-dashboard' ),
			'salaires'     => __( 'Salaires', 'aod-client-dashboard' ),
			'emballage'    => __( 'Emballage', 'aod-client-dashboard' ),
			'fournisseurs' => __( 'Fournisseurs', 'aod-client-dashboard' ),
			'abonnements'  => __( 'Abonnements', 'aod-client-dashboard' ),
			'autres'       => __( 'Autres dépenses', 'aod-client-dashboard' ),
		);
	}

	/**
	 * Les dépenses d'un mois, de la plus récente à la plus ancienne.
	 *
	 * Chaque ligne est normalisée à la lecture : une option est modifiable par
	 * n'importe quel code tiers, et l'écran ne doit pas se briser sur une valeur
	 * qu'il n'a pas écrite.
	 *
	 * @param string $mois `AAAA-MM`.
	 * @return array[]
	 */
	protected function depenses_mois( $mois ) {
		$all = get_option( self::OPTION_DEPENSES, array() );
		$all = is_array( $all ) && isset( $all[ $mois ] ) && is_array( $all[ $mois ] ) ? $all[ $mois ] : array();
		$cat = self::depenses_cats();
		$out = array();
		foreach ( $all as $d ) {
			if ( ! is_array( $d ) || empty( $d['id'] ) ) {
				continue;
			}
			$c     = isset( $d['cat'] ) ? (string) $d['cat'] : '';
			$out[] = array(
				'id'   => (string) $d['id'],
				'cat'  => isset( $cat[ $c ] ) ? $c : 'autres',
				'lib'  => isset( $d['lib'] ) ? (string) $d['lib'] : '',
				'mnt'  => isset( $d['mnt'] ) ? (float) $d['mnt'] : 0.0,
				'date' => isset( $d['date'] ) ? (string) $d['date'] : $mois . '-01',
			);
		}
		usort( $out, static function ( $a, $b ) {
			return strcmp( $b['date'], $a['date'] );
		} );
		return $out;
	}

	/**
	 * Le total d'un mois, et la ventilation par poste.
	 *
	 * L'ordre des postes suit `depenses_cats()`, PAS le montant : la barre et le
	 * tableau se lisent l'un contre l'autre, et une couleur qui changerait de
	 * poste d'un mois à l'autre rendrait la comparaison impossible.
	 *
	 * ⚠️ TOUS LES POSTES SONT PRÉSENTS, MÊME À ZÉRO, et ce n'est pas de la
	 * complaisance : l'indice de chaque poste dans ce tableau EST sa couleur dans
	 * la barre et dans le registre (`--part`). Ne rendre que les postes utilisés
	 * ferait glisser les couleurs d'un mois à l'autre — « la part bleue » ne
	 * désignerait plus la même dépense en juillet et en août.
	 *
	 * `public static` au même titre que `compute()` : c'est l'arithmétique de
	 * l'écran, elle s'éprouve de front plutôt que par une porte dérobée.
	 *
	 * @param array $lignes Sortie de `depenses_mois()`.
	 * @return array{total:float,par_cat:array<string,float>}
	 */
	public static function depenses_ventilation( $lignes ) {
		$par_cat = array();
		foreach ( array_keys( self::depenses_cats() ) as $cle ) {
			$par_cat[ $cle ] = 0.0;
		}
		$total = 0.0;
		foreach ( $lignes as $l ) {
			$par_cat[ $l['cat'] ] += $l['mnt'];
			$total                += $l['mnt'];
		}
		return array( 'total' => $total, 'par_cat' => $par_cat );
	}

	/** L'écran « Dépenses ». */
	protected function render_depenses() {
		$base   = home_url( '/' . AOD_CD_SLUG . '/finance' );
		$mois   = $this->mois_affiche();
		$lignes = $this->depenses_mois( $mois );
		$vent   = self::depenses_ventilation( $lignes );
		$cats   = self::depenses_cats();

		$prec       = gmdate( 'Y-m', (int) strtotime( $mois . '-01 -1 month' ) );
		$total_prec = self::depenses_ventilation( $this->depenses_mois( $prec ) );

		echo '<div class="aod-cd-fin aod-cd-findep">';
		$retour = '<a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $base ) . '">'
			. AOD_CD_Icon::svg( 'returned', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Finance', 'aod-client-dashboard' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput -- composant : libellés échappés à l'intérieur.
			__( 'Dépenses', 'aod-client-dashboard' ),
			__( 'Tout ce que la boutique paie, mois par mois. Saisi une fois, retrouvé ensuite.', 'aod-client-dashboard' ),
			$retour
		);

		echo '<form class="aod-cd-form aod-cd-filters" method="get" action="' . esc_url( $base ) . '">';
		echo '<input type="hidden" name="outil" value="depenses">';
		echo '<div class="aod-cd-field"><label for="aod-cd-d-mois">' . esc_html__( 'Mois', 'aod-client-dashboard' ) . '</label>';
		echo '<input type="month" id="aod-cd-d-mois" name="mois" value="' . esc_attr( $mois ) . '"></div>';
		echo '<div class="aod-cd-field aod-cd-filters-actions"><button type="submit" class="aod-cd-btn aod-cd-btn-primary">' . esc_html__( 'Afficher', 'aod-client-dashboard' ) . '</button></div>';
		echo '</form>';

		$this->render_depenses_kpis( $vent, $total_prec['total'], $cats, $prec );

		// 🚨 UNE COMPOSITION, PAS UNE PILE. Empilés sur toute la largeur, la
		// ventilation et le formulaire donnaient deux dalles de 1300 px : dans la
		// première, le nom d'un poste et son montant se retrouvaient à un mètre
		// l'un de l'autre — MESURÉ à la capture, et c'est ce qui rendait la
		// légende illisible ; dans la seconde, quatre champs et un bouton
		// flottaient dans une bande vide. Côte à côte, chacun retrouve sa largeur
		// utile. La bande retombe en une colonne sous 1081 px, et le formulaire y
		// passe seul si le mois est vide (la carte ne se rend pas) — d'où le
		// `:has()` en CSS plutôt qu'un test de plus ici.
		echo '<div class="aod-cd-findep__grid">';
		$this->render_depenses_bar( $vent, $cats );
		$this->render_depenses_form( $mois, $cats, count( $lignes ) );
		echo '</div>';

		$this->render_depenses_table( $mois, $lignes, $vent, $cats );
		$this->render_depenses_notes( $mois, $vent );

		echo '</div>';
	}

	/**
	 * Les trois chiffres de tête.
	 *
	 * ⚠️ LA HAUSSE EST EN ROUGE — c'est un choix, et il se discute. Dépenser plus
	 * n'est pas mauvais en soi : une boutique qui vend deux fois plus paie deux
	 * fois plus de livraison. Mais sur un écran qui ne parle QUE de dépenses, la
	 * variation est précisément ce qu'on vient regarder, et le reste du tableau de
	 * bord emploie déjà cette convention pour les annulations et les délais. La
	 * flèche porte le sens réel de la variation sans dépendre de la couleur.
	 *
	 * ⚠️ LA TROISIÈME TUILE DONNE LE TOTAL PRÉCÉDENT EN TOUTES LETTRES, et ce
	 * n'est pas un doublon de la pastille de variation : celle-ci dit « +18 % »,
	 * ce qui ne se juge pas sans savoir de quoi. Un pourcentage sans sa base
	 * paraît grand ou petit selon l'humeur du lecteur.
	 *
	 * @param array  $vent Ventilation du mois.
	 * @param float  $prec Total du mois précédent.
	 * @param array  $cats Libellés des postes.
	 * @param string $mois `AAAA-MM` du mois précédent, pour l'annoncer.
	 */
	protected function render_depenses_kpis( $vent, $prec, $cats, $mois ) {
		$tete = '';
		$max  = 0.0;
		foreach ( $vent['par_cat'] as $cle => $montant ) {
			if ( $montant > $max ) {
				$max  = $montant;
				$tete = $cle;
			}
		}
		$part = ( $vent['total'] > 0 && '' !== $tete ) ? ( $max / $vent['total'] ) * 100 : 0.0;
		// 🔴 LE MONTANT GARDE SON BALISAGE, ET C'EST TOUT LE CORRECTIF. Le symbole
		// de la devise algérienne (د.ج) est de sens DROITE-À-GAUCHE : posé au
		// milieu d'une chaîne, il emporte avec lui les caractères neutres qui le
		// suivent, et la ligne s'affichait « 103 000,00 د.ج 36,1 · % » — le
		// séparateur rejeté entre le nombre et son signe pourcent. Rien n'était
		// faux, tout était illisible.
		// 🚨 La cause n'était pas l'absence d'isolation : c'était `wp_strip_all_tags`
		// qui la RETIRAIT. `wc_price()` enveloppe déjà son montant dans un `<bdi>`,
		// et c'est pour ça que les grandes valeurs des tuiles, elles, s'affichaient
		// bien. *Aplatir du HTML pour « juste garder le texte » enlève aussi ce que
		// ce HTML portait de sens.* Le pourcentage, lui, gagne son propre `<bdi>` :
		// il n'en avait aucun.
		// ⚠️ Le défaut existe en FRANÇAIS aussi — c'est la devise qui est RTL, pas
		// la langue de l'écran. Une vérification faite seulement en arabe l'aurait
		// manqué à l'envers.
		$sous = ( '' === $tete )
			? ''
			: '<span class="aod-cd-kpi__vs">' . wp_kses_post( self::prix( $max ) ) . ' · <bdi>'
				. esc_html( number_format_i18n( $part, 1 ) . ' %' ) . '</bdi></span>';

		echo AOD_CD_Dashboard::kpis( array( // phpcs:ignore WordPress.Security.EscapeOutput -- composant : libellés échappés à l'intérieur.
			array(
				__( 'Total des dépenses', 'aod-client-dashboard' ),
				self::prix( $vent['total'] ),
				'money',
				'blue',
				AOD_CD_Section_Home::instance()->kpi_delta( $vent['total'], $prec, false ),
				// `primary` : c'est LE nombre de la page. Le modificateur porte le
				// dégradé d'accent de l'en-tête et réduit la valeur des cartes
				// voisines (`:has()`), donc il crée la hiérarchie au lieu de la
				// décrire. Trois cartes de même poids ne disaient pas laquelle lire.
				'primary',
			),
			array(
				__( 'Poste principal', 'aod-client-dashboard' ),
				'' === $tete ? '<span class="aod-cd-findep__vide">—</span>' : esc_html( $cats[ $tete ] ),
				'chart',
				'indigo',
				$sous,
			),
			array(
				__( 'Mois précédent', 'aod-client-dashboard' ),
				self::prix( $prec ),
				'calendar',
				'slate',
				// 🔴 `m/Y` ET NON `F Y`, ET C'EST LA MESURE QUI L'A DIT. Le nom du
				// mois vient des traductions du CŒUR de WordPress, pas des nôtres :
				// sur ce clone — et sur tout clone installé sans réseau, cf.
				// TEMPLATE.md §3 — seul `ar` est présent, le site reste en `en_US`,
				// et la tuile affichait « July 2026 » au milieu d'un écran français.
				// Nos `.po` n'y peuvent rien : `date_i18n()` ne les consulte pas.
				// Un format numérique ne dépend d'aucun pack et se lit dans les deux
				// langues. *Une chaîne traduite par quelqu'un d'autre que nous n'est
				// pas une chaîne traduite.*
				'<span class="aod-cd-kpi__vs">' . esc_html( date_i18n( 'm/Y', (int) strtotime( $mois . '-01' ) ) ) . '</span>',
			),
		) );
	}

	/**
	 * Le formulaire de saisie.
	 *
	 * Il emploie le binder GÉNÉRIQUE (`aod-cd-settings-form`) : sa réponse ne
	 * porte rien à confronter, seulement un rechargement — les totaux, la barre et
	 * le tableau sont rendus par le serveur, et les repeindre ici en aurait fait
	 * une seconde définition de la ventilation.
	 *
	 * @param string $mois `AAAA-MM`.
	 * @param array  $cats Libellés des postes.
	 * @param int    $n    Nombre de lignes déjà saisies ce mois.
	 */
	protected function render_depenses_form( $mois, $cats, $n ) {
		$devise = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
		$plein  = ( $n >= self::MAX_DEPENSES_MOIS );

		// Le jour proposé : aujourd'hui s'il tombe dans le mois consulté, son
		// premier jour sinon. Proposer « aujourd'hui » sur un mois passé ferait
		// saisir une dépense hors de la période qu'on est en train de lire.
		$today  = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) );
		$defaut = ( 0 === strpos( $today, $mois ) ) ? $today : $mois . '-01';

		echo '<form class="aod-cd-form aod-cd-settings-form aod-cd-findep__form" data-action="aod_cd_finance_depense_add">';
		echo '<input type="hidden" name="mois" value="' . esc_attr( $mois ) . '">';
		echo '<fieldset class="aod-cd-finset"><legend class="aod-cd-finset__lg">';
		echo '<span class="aod-cd-finset__ic" aria-hidden="true">' . AOD_CD_Icon::svg( 'note-edit', array( 'size' => 16 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo '<span class="aod-cd-finset__t">' . esc_html__( 'Ajouter une dépense', 'aod-client-dashboard' ) . '</span></legend>';
		echo '<p class="aod-cd-finset__s">' . esc_html__( 'Elle sera rangée dans le mois de sa date, pas dans celui que vous consultez.', 'aod-client-dashboard' ) . '</p>';
		echo '<div class="aod-cd-finset__body">';

		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . esc_html__( 'Date', 'aod-client-dashboard' ) . '</span>';
		echo '<input type="date" name="date" value="' . esc_attr( $defaut ) . '" min="' . esc_attr( $mois . '-01' ) . '" max="' . esc_attr( gmdate( 'Y-m-t', (int) strtotime( $mois . '-01' ) ) ) . '" required></label>';

		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . esc_html__( 'Poste', 'aod-client-dashboard' ) . '</span><select name="cat">';
		foreach ( $cats as $cle => $lab ) {
			echo '<option value="' . esc_attr( $cle ) . '">' . esc_html( $lab ) . '</option>';
		}
		echo '</select></label>';

		echo '<label class="aod-cd-field"><span class="aod-cd-label">' . esc_html__( 'Libellé', 'aod-client-dashboard' ) . '</span>';
		echo '<input type="text" name="lib" maxlength="120" placeholder="' . esc_attr__( 'Campagne Meta, salaire d’Amine, cartons…', 'aod-client-dashboard' ) . '"></label>';

		echo '<label class="aod-cd-field aod-cd-finfield"><span class="aod-cd-label">' . esc_html__( 'Montant', 'aod-client-dashboard' ) . '</span>';
		echo '<span class="aod-cd-finfield__wrap"><input type="number" step="0.01" min="0" class="aod-cd-code aod-cd-finfield__in" name="mnt" inputmode="decimal" placeholder="0" required>';
		if ( '' !== $devise ) {
			echo '<span class="aod-cd-finfield__unit" aria-hidden="true">' . esc_html( $devise ) . '</span>';
		}
		echo '</span></label>';

		echo '</div></fieldset>';

		if ( $plein ) {
			echo '<p class="aod-cd-finwarn">' . esc_html( sprintf(
				/* translators: %d : nombre maximal de lignes par mois */
				__( 'Ce mois compte déjà %d lignes, le maximum. Supprimez-en une avant d’en ajouter une autre — aucune ligne n’est effacée automatiquement.', 'aod-client-dashboard' ),
				self::MAX_DEPENSES_MOIS
			) ) . '</p>';
		}

		echo '<div class="aod-cd-form-foot aod-cd-savebar">';
		echo '<button type="submit" class="aod-cd-btn aod-cd-btn-primary"' . ( $plein ? ' disabled' : '' ) . '>'
			. AOD_CD_Icon::svg( 'save', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Ajouter la dépense', 'aod-client-dashboard' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo '<span class="aod-cd-form-msg"></span>';
		echo '</div>';
		echo '</form>';
	}

	/**
	 * « Où part l'argent » — la même barre que le calculateur, remplie par le PHP.
	 *
	 * ⚠️ MÊME COMPOSANT, AUTRE SOURCE. Le calculateur bâtit cette barre dans le
	 * navigateur, à chaque frappe, depuis une simulation ; ici elle est rendue par
	 * le serveur depuis des écritures. Ce sont les mêmes classes — donc le même
	 * dessin et la même rampe de couleurs, celle qui a été corrigée pour rester
	 * lisible sur sept parts — et deux remplissages. Les fondre en un seul aurait
	 * demandé de faire voyager des dépenses jusqu'au JS pour rien.
	 *
	 * @param array $vent Ventilation du mois.
	 * @param array $cats Libellés des postes.
	 */
	protected function render_depenses_bar( $vent, $cats ) {
		if ( $vent['total'] <= 0 ) {
			return;
		}
		// 🚨 LA COULEUR SUIT LE POSTE, L'ORDRE SUIT LE MONTANT — et les deux
		// décisions sont indépendantes. L'indice `--part` reste celui du poste
		// dans `depenses_cats()`, donc « le vert foncé » désigne la publicité en
		// juillet comme en août : c'est ce qui rend deux mois comparables. Le
		// RANG, lui, se lit du plus gros au plus petit — c'est la question qu'on
		// pose à cet écran, et un ordre alphabétique déguisé en classement
		// obligerait à comparer sept nombres de tête.
		$rangs = array_flip( array_keys( $cats ) );
		$parts = array();
		foreach ( $vent['par_cat'] as $cle => $montant ) {
			if ( $montant > 0 ) {
				$parts[] = array( 'cle' => $cle, 'mnt' => $montant );
			}
		}
		usort( $parts, static function ( $a, $b ) {
			return ( $b['mnt'] < $a['mnt'] ) ? -1 : ( ( $b['mnt'] > $a['mnt'] ) ? 1 : 0 );
		} );

		$segments = '';
		$legende  = '';
		$rang     = 0;
		foreach ( $parts as $p ) {
			$part = (int) $rangs[ $p['cle'] ];
			$pct  = ( $p['mnt'] / $vent['total'] ) * 100;
			// `--rang` sert au DÉCALAGE de l'animation, `--part` à la couleur :
			// les segments se construisent du plus gros au plus petit, mais
			// gardent la teinte de leur poste.
			$segments .= '<span class="aod-cd-fincost__seg" style="--part:' . $part . ';--rang:' . (int) $rang . ';width:' . esc_attr( number_format( $pct, 2, '.', '' ) ) . '%" title="' . esc_attr( $cats[ $p['cle'] ] ) . '"></span>';
			$legende  .= '<li class="aod-cd-fincost__li" style="--rang:' . (int) $rang . '"><span class="aod-cd-fincost__dot" style="--part:' . $part . '" aria-hidden="true"></span>'
				. '<span class="aod-cd-fincost__nom">' . esc_html( $cats[ $p['cle'] ] ) . '</span>'
				. '<span class="aod-cd-fincost__val">' . wp_kses_post( self::prix( $p['mnt'] ) ) . '</span>'
				. '<span class="aod-cd-fincost__pct">' . esc_html( number_format_i18n( $pct, 1 ) . ' %' ) . '</span></li>';
			$rang++;
		}

		echo '<div class="aod-cd-chartcard aod-cd-fincost aod-cd-findep__card">';
		echo '<div class="aod-cd-chart-head"><h2 class="aod-cd-chart-title">' . esc_html__( 'Où part l’argent', 'aod-client-dashboard' ) . '</h2></div>';
		echo '<div class="aod-cd-fincost__bar" role="img" aria-label="' . esc_attr__( 'Répartition des dépenses par poste', 'aod-client-dashboard' ) . '">';
		echo $segments; // phpcs:ignore WordPress.Security.EscapeOutput -- assemblé ci-dessus : chaque valeur variable passe par esc_attr() ou number_format().
		echo '</div>';
		echo '<ul class="aod-cd-fincost__leg">';
		echo $legende; // phpcs:ignore WordPress.Security.EscapeOutput -- même assemblage : libellés par esc_html(), montants par wp_kses_post( wc_price() ).
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Le registre du mois.
	 *
	 * Chaque ligne porte la PASTILLE de couleur de son poste, la même que dans la
	 * barre : sans elle, la barre et le tableau seraient deux lectures sans point
	 * commun, et retrouver « la grosse part bleue » demanderait de compter.
	 *
	 * @param string $mois   `AAAA-MM`.
	 * @param array  $lignes Dépenses du mois.
	 * @param array  $vent   Ventilation.
	 * @param array  $cats   Libellés des postes.
	 */
	protected function render_depenses_table( $mois, $lignes, $vent, $cats ) {
		if ( empty( $lignes ) ) {
			echo AOD_CD_Dashboard::empty_state( 'note', __( 'Aucune dépense saisie sur ce mois.', 'aod-client-dashboard' ), __( 'Ajoutez-en une ci-dessus : elle apparaîtra ici, avec sa part du total.', 'aod-client-dashboard' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- composant.
			return;
		}

		$rangs = array_flip( array_keys( $cats ) );
		$cols  = array(
			__( 'Date', 'aod-client-dashboard' ),
			__( 'Poste', 'aod-client-dashboard' ),
			__( 'Libellé', 'aod-client-dashboard' ),
			__( 'Montant', 'aod-client-dashboard' ),
			__( 'Action', 'aod-client-dashboard' ),
		);

		echo '<div class="aod-cd-tablewrap" data-depenses data-mois="' . esc_attr( $mois ) . '">';
		echo '<table class="aod-cd-table aod-cd-table--cards aod-cd-findep__t"><thead><tr>';
		foreach ( $cols as $c ) {
			echo '<th>' . esc_html( $c ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $lignes as $l ) {
			$lab = $cats[ $l['cat'] ];
			echo '<tr>';
			echo '<td data-label="' . esc_attr( $cols[0] ) . '">' . esc_html( date_i18n( 'd/m/Y', (int) strtotime( $l['date'] ) ) ) . '</td>';
			echo '<td data-label="' . esc_attr( $cols[1] ) . '"><span class="aod-cd-findep__poste">'
				. '<span class="aod-cd-fincost__dot" style="--part:' . (int) $rangs[ $l['cat'] ] . '" aria-hidden="true"></span>'
				. esc_html( $lab ) . '</span></td>';
			echo '<td data-label="' . esc_attr( $cols[2] ) . '">' . ( '' === $l['lib'] ? '<span class="aod-cd-findep__vide">—</span>' : esc_html( $l['lib'] ) ) . '</td>';
			echo '<td class="aod-cd-findep__mnt" data-label="' . esc_attr( $cols[3] ) . '">' . wp_kses_post( self::prix( $l['mnt'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr( $cols[4] ) . '">';
			echo '<button type="button" class="aod-cd-btn aod-cd-btn-sm aod-cd-btn--ico aod-cd-findep__del" data-id="' . esc_attr( $l['id'] ) . '"'
				. ' data-confirm="' . esc_attr( sprintf(
					/* translators: 1 : poste de dépense, 2 : date */
					__( 'Supprimer cette dépense (%1$s, %2$s) ? Elle ne sera plus comptée dans le total du mois.', 'aod-client-dashboard' ),
					$lab,
					date_i18n( 'd/m/Y', (int) strtotime( $l['date'] ) )
				) ) . '"'
				. ' aria-label="' . esc_attr__( 'Supprimer cette dépense', 'aod-client-dashboard' ) . '">'
				. AOD_CD_Icon::svg( 'trash', array( 'size' => 15 ) ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
			echo '</td></tr>';
		}

		echo '<tr class="aod-cd-fintot">';
		// 🔴 PAS de `data-label` sur CETTE cellule. Sous 780 px le tableau devient
		// des cartes et `data-label` est rendu comme intitulé de la ligne : la
		// carte du total annonçait « DATE — Total », c'est-à-dire un libellé qui
		// dément sa propre valeur. Sans lui, elle lit « Total », en tête de carte.
		// (Les « — » des colonnes suivantes RESTENT étiquetés : ils disent « cette
		// colonne-là ne se totalise pas », ce qui est une information.)
		echo '<td><strong>' . esc_html__( 'Total', 'aod-client-dashboard' ) . '</strong></td>';
		echo '<td data-label="' . esc_attr( $cols[1] ) . '">—</td>';
		echo '<td data-label="' . esc_attr( $cols[2] ) . '">' . esc_html( sprintf(
			/* translators: %d : nombre de dépenses */
			_n( '%d dépense', '%d dépenses', count( $lignes ), 'aod-client-dashboard' ),
			count( $lignes )
		) ) . '</td>';
		echo '<td class="aod-cd-findep__mnt" data-label="' . esc_attr( $cols[3] ) . '">' . wp_kses_post( self::prix( $vent['total'] ) ) . '</td>';
		echo '<td data-label="' . esc_attr( $cols[4] ) . '">—</td>';
		echo '</tr>';
		echo '</tbody></table></div>';
	}

	/**
	 * Ce que l'écran compte, et ce qu'il ne compte pas.
	 *
	 * 🚨 L'ÉCART AVEC « RENTABILITÉ PAR PRODUIT » EST NOMMÉ, JAMAIS RÉSOLU EN
	 * SILENCE. Les deux écrans connaissent un montant de publicité pour le même
	 * mois : ici la dépense de la boutique, là-bas la somme des budgets attribués
	 * produit par produit. Ils peuvent légitimement différer — une campagne de
	 * marque n'est attribuable à aucun produit. Ce qui serait fautif, c'est de
	 * laisser un gérant découvrir deux chiffres différents sans explication : la
	 * phrase ci-dessous les rapproche et dit lequel sert à quoi.
	 *
	 * @param string $mois `AAAA-MM`.
	 * @param array  $vent Ventilation du mois.
	 */
	protected function render_depenses_notes( $mois, $vent ) {
		$par_produit = array_sum( $this->pub_mois( $mois ) );
		$ici         = isset( $vent['par_cat']['publicite'] ) ? $vent['par_cat']['publicite'] : 0.0;

		echo '<div class="aod-cd-findep__notes">';
		echo '<p class="aod-cd-note">' . esc_html__( 'Ce registre est le vôtre : il totalise exactement ce que vous saisissez. Aucune dépense n’y entre automatiquement depuis les commandes.', 'aod-client-dashboard' ) . '</p>';

		if ( $par_produit > 0 || $ici > 0 ) {
			$phrase = sprintf(
				/* translators: 1 : montant saisi ici, 2 : total des budgets par produit */
				__( 'Publicité : %1$s ici, contre %2$s répartis produit par produit dans « Rentabilité par produit ». Les deux saisies sont indépendantes — celle-ci mesure ce que la boutique a payé, l’autre sert à calculer le bénéfice de chaque produit.', 'aod-client-dashboard' ),
				wp_strip_all_tags( self::prix( $ici ) ),
				wp_strip_all_tags( self::prix( $par_produit ) )
			);
			echo '<p class="aod-cd-note">' . esc_html( $phrase ) . '</p>';
		}

		echo '<p class="aod-cd-note">' . esc_html( sprintf(
			/* translators: %d : nombre maximal de lignes par mois */
			__( 'Un mois accepte %d lignes. Au-delà, l’ajout est refusé : rien n’est effacé pour faire de la place.', 'aod-client-dashboard' ),
			self::MAX_DEPENSES_MOIS
		) ) . '</p>';
		echo '</div>';
	}

	/**
	 * AJAX : ajoute une dépense.
	 *
	 * ⚠️ LE MOIS DE RANGEMENT VIENT DE LA DATE, PAS DU FILTRE. Le champ `mois`
	 * du formulaire ne sert qu'à borner le sélecteur de date à l'écran ; ranger
	 * une dépense du 1ᵉʳ septembre dans août parce qu'on consultait août aurait
	 * faussé les deux mois d'un coup, sans trace.
	 */
	public function ajax_depense_add() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Date invalide.', 'aod-client-dashboard' ) ) );
		}
		$mois = substr( $date, 0, 7 );

		$cats = self::depenses_cats();
		$cat  = isset( $_POST['cat'] ) ? sanitize_key( wp_unslash( $_POST['cat'] ) ) : '';
		if ( ! isset( $cats[ $cat ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Poste de dépense inconnu.', 'aod-client-dashboard' ) ) );
		}

		$mnt = isset( $_POST['mnt'] ) ? (float) wp_unslash( $_POST['mnt'] ) : 0.0;
		if ( ! is_finite( $mnt ) || $mnt <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Le montant doit être supérieur à zéro.', 'aod-client-dashboard' ) ) );
		}

		$lib = isset( $_POST['lib'] ) ? sanitize_text_field( wp_unslash( $_POST['lib'] ) ) : '';
		$lib = trim( mb_substr( $lib, 0, 120 ) );

		$all = get_option( self::OPTION_DEPENSES, array() );
		$all = is_array( $all ) ? $all : array();
		$mo  = isset( $all[ $mois ] ) && is_array( $all[ $mois ] ) ? $all[ $mois ] : array();

		if ( count( $mo ) >= self::MAX_DEPENSES_MOIS ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %d : nombre maximal de lignes par mois */
					__( 'Ce mois compte déjà %d lignes, le maximum. Supprimez-en une avant d’en ajouter une autre.', 'aod-client-dashboard' ),
					self::MAX_DEPENSES_MOIS
				),
			) );
		}

		$mo[] = array(
			'id'   => uniqid( 'dp', true ),
			'cat'  => $cat,
			'lib'  => $lib,
			'mnt'  => round( $mnt, 2 ),
			'date' => $date,
		);
		$all[ $mois ] = $mo;
		// `false` : jamais autoloadée. Ce registre ne sert qu'à cet écran ; le
		// charger sur chaque requête de la boutique serait payer la comptabilité
		// sur le temps de réponse des pages produit.
		update_option( self::OPTION_DEPENSES, $all, false );

		wp_send_json_success( array(
			'message' => __( 'Dépense ajoutée.', 'aod-client-dashboard' ),
			'reload'  => true,
		) );
	}

	/** AJAX : supprime une dépense. */
	public function ajax_depense_del() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$mois = isset( $_POST['mois'] ) ? sanitize_text_field( wp_unslash( $_POST['mois'] ) ) : '';
		$id   = isset( $_POST['dep'] ) ? sanitize_text_field( wp_unslash( $_POST['dep'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $mois ) || '' === $id ) {
			wp_send_json_error( array( 'message' => __( 'Dépense introuvable.', 'aod-client-dashboard' ) ) );
		}

		$all = get_option( self::OPTION_DEPENSES, array() );
		$all = is_array( $all ) ? $all : array();
		if ( ! isset( $all[ $mois ] ) || ! is_array( $all[ $mois ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Dépense introuvable.', 'aod-client-dashboard' ) ) );
		}

		$reste = array();
		$vue   = false;
		foreach ( $all[ $mois ] as $d ) {
			if ( is_array( $d ) && isset( $d['id'] ) && (string) $d['id'] === $id ) {
				$vue = true;
				continue;
			}
			$reste[] = $d;
		}
		if ( ! $vue ) {
			wp_send_json_error( array( 'message' => __( 'Dépense introuvable.', 'aod-client-dashboard' ) ) );
		}

		$all[ $mois ] = $reste;
		update_option( self::OPTION_DEPENSES, $all, false );

		wp_send_json_success( array( 'message' => __( 'Dépense supprimée.', 'aod-client-dashboard' ) ) );
	}

	/* =====================================================================
	 * Entreprise
	 * ================================================================== */

	/** Option : la fiche société (identité, fiscal, bancaire). */
	const OPTION_ENTREPRISE = 'aod_cd_entreprise';

	/**
	 * L'ADRESSE N'EST PAS À NOUS — elle appartient à WooCommerce.
	 *
	 * 🚨 CE TABLEAU EST UN PONT, PAS UN STOCKAGE. Ces quatre valeurs vivent déjà
	 * dans les options `woocommerce_store_*`, et la page Contact PUBLIQUE les
	 * affiche (`AOD_COD_Contact::store_details()`). Les recopier dans notre fiche
	 * aurait donné deux adresses pour une société : celle qu'on saisit ici et
	 * celle que voit l'acheteur, divergeant dès la première correction faite du
	 * mauvais côté. Cet écran les ÉDITE donc là où elles vivent.
	 *
	 * ⚠️ Et il comble un vrai trou : jusqu'ici l'adresse du site public ne se
	 * modifiait QUE depuis `wp-admin` (TEMPLATE.md §5, « Renseigner l'adresse de
	 * la boutique dans les réglages WooCommerce ») — sur un tableau de bord dont
	 * la raison d'être est que le client gère sa boutique SANS wp-admin.
	 *
	 * ⛔ Ces options ne sont PAS supprimées par notre `uninstall.php` : elles
	 * appartiennent à WooCommerce, qui reste actif après nous. Même règle que les
	 * réglages `aod_cod_*`, éditée ici mais possédée ailleurs.
	 *
	 * @return array<string,array{opt:string,l:string,ph:string}>
	 */
	public static function entreprise_adresse() {
		return array(
			'adresse'   => array(
				'opt' => 'woocommerce_store_address',
				'l'   => __( 'Adresse', 'aod-client-dashboard' ),
				'ph'  => __( 'Numéro et rue', 'aod-client-dashboard' ),
			),
			'adresse_2' => array(
				'opt' => 'woocommerce_store_address_2',
				'l'   => __( 'Complément d’adresse', 'aod-client-dashboard' ),
				'ph'  => __( 'Bâtiment, étage, local…', 'aod-client-dashboard' ),
			),
			'ville'     => array(
				'opt' => 'woocommerce_store_city',
				'l'   => __( 'Ville', 'aod-client-dashboard' ),
				'ph'  => '',
			),
			'code'      => array(
				'opt' => 'woocommerce_store_postcode',
				'l'   => __( 'Code postal', 'aod-client-dashboard' ),
				'ph'  => '',
			),
		);
	}

	/**
	 * La fiche société — déclarée une fois, lue trois fois.
	 *
	 * Même contrat que `champs()` pour le calculateur : le formulaire s'en déduit,
	 * la sanitisation du POST s'en déduit, l'affichage aussi. Écrits séparément,
	 * un champ affiché mais non lu se saisirait sans effet et rien ne le dirait.
	 *
	 * ⚠️ AUCUN LIBELLÉ N'EST PROPRE À UN PAYS DANS LE CODE. « RC », « NIF »,
	 * « NIS » sont les intitulés algériens, et ce template vise l'Algérie
	 * (TEMPLATE.md) — mais ce sont des ÉTIQUETTES traduisibles, pas des règles :
	 * aucune validation de format, aucun calcul ne s'y adosse. Un clone servant un
	 * autre pays remplace les libellés dans son `.po` et le reste fonctionne.
	 * Écrire un contrôle de format « 15 chiffres » aurait figé la fiscalité d'un
	 * pays dans un template générique.
	 *
	 * @return array<string,array>
	 */
	public static function entreprise_champs() {
		return array(
			'identite' => array(
				'titre'  => __( 'Identité', 'aod-client-dashboard' ),
				'aide'   => __( 'Le nom sous lequel la société est enregistrée — pas nécessairement celui de la boutique.', 'aod-client-dashboard' ),
				'ic'     => 'storefront',
				'champs' => array(
					'raison_sociale' => array( 'l' => __( 'Raison sociale', 'aod-client-dashboard' ), 'ph' => __( 'Nom légal de la société', 'aod-client-dashboard' ) ),
					'forme'          => array( 'l' => __( 'Forme juridique', 'aod-client-dashboard' ), 'ph' => __( 'EURL, SARL, SPA, auto-entrepreneur…', 'aod-client-dashboard' ) ),
					'gerant'         => array( 'l' => __( 'Représentant légal', 'aod-client-dashboard' ), 'ph' => __( 'Prénom et nom du gérant', 'aod-client-dashboard' ) ),
					'capital'        => array( 'l' => __( 'Capital social', 'aod-client-dashboard' ), 'ph' => '' ),
				),
			),
			'fiscal'   => array(
				'titre'  => __( 'Informations fiscales', 'aod-client-dashboard' ),
				'aide'   => __( 'Les numéros que réclament un contrat transporteur, une facture ou un dossier administratif.', 'aod-client-dashboard' ),
				'ic'     => 'note',
				'champs' => array(
					'rc'      => array( 'l' => __( 'Registre de commerce (RC)', 'aod-client-dashboard' ), 'ph' => '' ),
					'nif'     => array( 'l' => __( 'Identification fiscale (NIF)', 'aod-client-dashboard' ), 'ph' => '' ),
					'nis'     => array( 'l' => __( 'Identification statistique (NIS)', 'aod-client-dashboard' ), 'ph' => '' ),
					'article' => array( 'l' => __( 'Article d’imposition', 'aod-client-dashboard' ), 'ph' => '' ),
				),
			),
			'banque'   => array(
				'titre'  => __( 'Coordonnées bancaires', 'aod-client-dashboard' ),
				'aide'   => __( 'Le compte sur lequel la boutique encaisse. Visible du gérant seul.', 'aod-client-dashboard' ),
				'ic'     => 'money',
				'champs' => array(
					'banque' => array( 'l' => __( 'Banque', 'aod-client-dashboard' ), 'ph' => '' ),
					'agence' => array( 'l' => __( 'Agence', 'aod-client-dashboard' ), 'ph' => '' ),
					'rib'    => array( 'l' => __( 'RIB / numéro de compte', 'aod-client-dashboard' ), 'ph' => '' ),
				),
			),
		);
	}

	/** La fiche enregistrée, complétée des clés manquantes. */
	protected function entreprise_valeurs() {
		$brut = get_option( self::OPTION_ENTREPRISE, array() );
		$brut = is_array( $brut ) ? $brut : array();
		$out  = array();
		foreach ( self::entreprise_champs() as $groupe ) {
			foreach ( array_keys( $groupe['champs'] ) as $cle ) {
				$out[ $cle ] = isset( $brut[ $cle ] ) ? (string) $brut[ $cle ] : '';
			}
		}
		return $out;
	}

	/** L'écran « Entreprise ». */
	protected function render_entreprise() {
		$base    = home_url( '/' . AOD_CD_SLUG . '/finance' );
		$valeurs = $this->entreprise_valeurs();

		echo '<div class="aod-cd-fin aod-cd-finent">';
		$retour = '<a class="aod-cd-btn aod-cd-btn-sm" href="' . esc_url( $base ) . '">'
			. AOD_CD_Icon::svg( 'returned', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Finance', 'aod-client-dashboard' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo AOD_CD_Dashboard::page_head( // phpcs:ignore WordPress.Security.EscapeOutput -- composant : libellés échappés à l'intérieur.
			__( 'Entreprise', 'aod-client-dashboard' ),
			__( 'La fiche de votre société : ce qu’on vous demande de recopier, rangé une fois pour toutes.', 'aod-client-dashboard' ),
			$retour
		);

		// Les quatre blocs sont des ENFANTS DIRECTS du formulaire : celui-ci est
		// déjà une grille à deux colonnes qui retombe à une sous 680 px. Une
		// grille de plus n'aurait rien ajouté qu'un point de rupture à tenir.
		echo '<form class="aod-cd-form aod-cd-settings-form aod-cd-finent__form" data-action="aod_cd_finance_entreprise">';

		$this->render_entreprise_bloc(
			'adresse',
			__( 'Adresse de la boutique', 'aod-client-dashboard' ),
			__( 'C’est l’adresse affichée sur votre page Contact publique.', 'aod-client-dashboard' ),
			'map',
			self::entreprise_adresse(),
			null
		);

		foreach ( self::entreprise_champs() as $gcle => $g ) {
			$this->render_entreprise_bloc( $gcle, $g['titre'], $g['aide'], $g['ic'], $g['champs'], $valeurs );
		}

		echo '<div class="aod-cd-form-foot aod-cd-savebar">';
		echo '<button type="submit" class="aod-cd-btn aod-cd-btn-primary">'
			. AOD_CD_Icon::svg( 'save', array( 'size' => 15 ) ) . ' ' . esc_html__( 'Enregistrer la fiche', 'aod-client-dashboard' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo '<span class="aod-cd-form-msg"></span>';
		echo '</div>';
		echo '</form>';

		// 🚨 CE QUE CET ÉCRAN FAIT, ET CE QU'IL NE FAIT PAS. Une fiche d'entreprise
		// laisse croire qu'elle s'imprime quelque part. Une seule de ces valeurs
		// sort d'ici — l'adresse, sur la page Contact. Le taire ferait attendre des
		// factures qui n'existent pas ; l'écrire vaut mieux qu'une promesse.
		echo '<div class="aod-cd-finent__notes">';
		echo '<p class="aod-cd-note">' . esc_html__( 'Seule l’adresse quitte cette page : elle s’affiche sur votre page Contact. Le reste est une fiche de référence — ce que vous recopiez sur un contrat transporteur, une facture ou un dossier. Rien n’est envoyé à l’extérieur.', 'aod-client-dashboard' ) . '</p>';
		echo '<p class="aod-cd-note">' . esc_html__( 'Cette page n’est visible que du gérant. Un confirmateur ne la voit pas dans le menu et ne peut pas l’ouvrir par son adresse.', 'aod-client-dashboard' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Un bloc de la fiche.
	 *
	 * Un seul corps pour les quatre, alors que le premier lit ses valeurs dans
	 * WooCommerce et les trois autres dans notre option : la DIFFÉRENCE tient dans
	 * `$valeurs` (nul = va chercher `opt` côté Woo). Quatre rendus jumeaux auraient
	 * divergé au premier ajustement de balisage — le mode de dérive que la Phase 46
	 * a passé huit étapes à fermer.
	 *
	 * @param string     $cle     Clé du groupe (classe CSS).
	 * @param string     $titre   Titre du bloc.
	 * @param string     $aide    Phrase d'explication.
	 * @param string     $ic      Icône du catalogue fermé.
	 * @param array      $champs  Champs du bloc.
	 * @param array|null $valeurs Valeurs, ou null pour lire les options WooCommerce.
	 */
	protected function render_entreprise_bloc( $cle, $titre, $aide, $ic, $champs, $valeurs ) {
		echo '<fieldset class="aod-cd-finset aod-cd-finent__bloc aod-cd-finent__bloc--' . esc_attr( $cle ) . '">';
		echo '<legend class="aod-cd-finset__lg">';
		echo '<span class="aod-cd-finset__ic" aria-hidden="true">' . AOD_CD_Icon::svg( $ic, array( 'size' => 16 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- catalogue fermé.
		echo '<span class="aod-cd-finset__t">' . esc_html( $titre ) . '</span></legend>';
		echo '<p class="aod-cd-finset__s">' . esc_html( $aide ) . '</p>';
		echo '<div class="aod-cd-finset__body">';
		foreach ( $champs as $k => $def ) {
			$val = ( null === $valeurs )
				? (string) get_option( $def['opt'], '' )
				: ( isset( $valeurs[ $k ] ) ? $valeurs[ $k ] : '' );
			echo '<label class="aod-cd-field"><span class="aod-cd-label">' . esc_html( $def['l'] ) . '</span>';
			echo '<input type="text" name="' . esc_attr( ( null === $valeurs ? 'wc_' : 'ent_' ) . $k ) . '" maxlength="120"'
				. ' value="' . esc_attr( $val ) . '"'
				. ( '' === $def['ph'] ? '' : ' placeholder="' . esc_attr( $def['ph'] ) . '"' ) . '></label>';
		}
		echo '</div></fieldset>';
	}

	/**
	 * AJAX : enregistre la fiche société.
	 *
	 * ⚠️ DEUX DESTINATIONS, UN SEUL FORMULAIRE. Les champs préfixés `wc_` partent
	 * dans les options WooCommerce (l'adresse, que la page Contact affiche) ; les
	 * `ent_` dans la nôtre. Le préfixe est ce qui empêche une clé de fiche
	 * d'écraser une option de WooCommerce si les deux venaient à porter le même
	 * nom — et il rend le tri lisible au lieu de le confier à une liste tenue à
	 * jour à la main.
	 */
	public function ajax_entreprise() {
		check_ajax_referer( 'aod_cd', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'aod-client-dashboard' ) ), 403 );
		}

		$lire = static function ( $nom ) {
			$v = isset( $_POST[ $nom ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nom ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce vérifié en tête de ce handler.
			return trim( mb_substr( $v, 0, 120 ) );
		};

		foreach ( self::entreprise_adresse() as $cle => $def ) {
			update_option( $def['opt'], $lire( 'wc_' . $cle ) );
		}

		$fiche = array();
		foreach ( self::entreprise_champs() as $groupe ) {
			foreach ( array_keys( $groupe['champs'] ) as $cle ) {
				$fiche[ $cle ] = $lire( 'ent_' . $cle );
			}
		}
		// `false` : jamais autoloadée. Une fiche consultée quelques fois par an n'a
		// rien à faire dans la mémoire de chaque page produit.
		update_option( self::OPTION_ENTREPRISE, $fiche, false );

		wp_send_json_success( array( 'message' => __( 'Fiche enregistrée.', 'aod-client-dashboard' ) ) );
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
