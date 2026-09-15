<?php
/**
 * La garde d'un handler AJAX suit la capability de sa section (Phase 21).
 *
 * Le défaut d'origine : `ajax_test_carrier` exigeait `manage_woocommerce` là où ses
 * deux voisines de la section « Livraison » demandent `aod_cap_shipping_prices`.
 * Un confirmateur « tarifs » pouvait donc IMPORTER des tarifs, mais pas TESTER la
 * connexion transporteur.
 *
 * Pourquoi personne ne l'a vu : `AOD_CD_Roles::user_can()` répond vrai à toute
 * `aod_cap_*` dès que l'utilisateur a `manage_woocommerce`. **Le gérant satisfait
 * donc TOUTES les gardes et ne rencontre jamais ce mur** — et c'est en gérant qu'on
 * développe et qu'on recette. Aucune porte de `tools/recette/` ne se connecte en
 * confirmateur (cf. ROADMAP §C.5, D13). Le défaut n'a pas échappé aux contrôles :
 * il n'a jamais été dans leur champ.
 *
 * 🚨 CE QUE CE TEST NE PROUVE PAS. Il est **statique** : il lit du code, il ne
 * verra jamais un 403 réel. Il empêche une garde de diverger en silence ; il ne
 * remplace pas un parcours navigateur en confirmateur.
 * ✅ **Et ce complément EXISTE** — `bash tools/recette/run.sh confirmateur`, la
 * seule porte du dépôt qui ne se connecte pas en gérant (Phase 22.2). Cette
 * ligne annonçait « D13, **ouvert** » : c'était vrai à l'écriture, faux depuis —
 * corrigé en 47.9. La limitation, elle, reste entière : un test statique ne
 * remplace pas un parcours, il le complète.
 *
 * @package AOD_Tests
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * LA TABLE DE RÉFÉRENCE — le contrat
 * =========================================================================
 *
 * Elle n'est PAS un relevé de l'existant : c'est ce que chaque handler DOIT
 * exiger. `test_carrier` y porte donc sa cap cible, pas celle du code au moment
 * où ces lignes sont écrites.
 *
 * ⚠️ `docs/ARCHITECTURE.md` §AJAX décrit cette carte en prose, et cette prose a
 * déjà été fausse (elle omettait que `import_fees` passe par
 * `aod_cap_shipping_prices` depuis la Phase 4). Cette table-ci est exécutable :
 * c'est elle qui fait foi.
 *
 * QUATRE FAMILLES — la troisième est le piège de la phase :
 *
 *   'gouvernee'    la section est dans `$section_caps` → même cap qu'elle.
 *   'manager'      la section est absente de `$section_caps`, donc réservée au
 *                  gérant (`visible_sections()` : `empty(...) → continue`).
 *                  Cap attendue : `manage_woocommerce`, ou la cap Woo native du
 *                  périmètre (`edit_products`, `delete_products`).
 *   'action-forte' action réservée au gérant À L'INTÉRIEUR d'une section
 *                  gouvernée. La cap est LÉGITIMEMENT plus forte que celle de la
 *                  section. Sans cette famille, le test rougirait sur du code
 *                  délibéré — puis on l'assouplirait pour le faire taire, et il
 *                  ne mordrait plus sur rien.
 *   'deleguee'     la garde vit dans une méthode partagée (cf. GARDES_PARTAGEES).
 *
 * Toute entrée 'action-forte' DOIT porter un 'pourquoi'. Une divergence sans
 * raison écrite est un échec de revue, pas un test vert.
 */
function aod_test_perm_contrat() {
	return array(

		/* --- Section « Commandes » (gouvernée : aod_cap_confirm_orders) ------- */
		'order_status'     => array( 'caps' => array( 'aod_cap_confirm_orders' ), 'famille' => 'gouvernee' ),
		'order_detail'     => array( 'caps' => array( 'aod_cap_confirm_orders' ), 'famille' => 'gouvernee' ),
		// 47.3 — `order_note` a quitté cette table parce que le handler a quitté le
		// code : la note du confirmateur est devenue un champ de `order_save_info`
		// ci-dessous, sous la MÊME capability. Aucun droit n'a bougé.
		'order_save_info'  => array( 'caps' => array( 'aod_cap_confirm_orders' ), 'famille' => 'gouvernee' ),
		// 2026-08-04 — export CSV. Gouverné par la section : qui peut LIRE la liste
		// peut l'emporter. ⚠️ La garantie qui compte n'est pas ici mais dans le
		// corps : l'export passe par `scoped_orders()`, donc un confirmateur
		// n'exporte QUE ses commandes assignées. Sans ce passage, cette même
		// capability livrerait le fichier client de toute la boutique — nom,
		// téléphone et adresse de chaque acheteur — à qui n'en gouverne qu'une part.
		'export_orders'    => array( 'caps' => array( 'aod_cap_confirm_orders' ), 'famille' => 'gouvernee' ),
		'delete_orders'    => array(
			'caps'    => array( 'delete_shop_orders', 'manage_woocommerce' ),
			'famille' => 'action-forte',
			'pourquoi' => 'SUPPRIMER est plus fort que CONFIRMER : un confirmateur gouverne la section, pas la destruction. Durcissement assumé.',
		),
		'order_assign'     => array(
			'caps'    => array( 'manage_woocommerce' ),
			'famille' => 'action-forte',
			'pourquoi' => "ASSIGNER une commande à un confirmateur (Phase 42.4) est un pouvoir du GÉRANT, jamais de aod_cap_confirm_orders : un confirmateur porteur de cette capability ne doit pas pouvoir se réassigner (ou réassigner à un pair) des commandes — même piège que test_carrier (Phase 21/31.1, 'une permission cohérente n'est pas une permission bornée'). Le sélecteur est d'ailleurs absent du rendu pour un confirmateur (masqué ET 403 sur un POST forgé).",
		),
		'orders_bulk_assign' => array(
			'caps'    => array( 'manage_woocommerce' ),
			'famille' => 'action-forte',
			'pourquoi' => "Rattrapage groupé (Phase 42.5) : MÊME pouvoir gérant strict que order_assign, appliqué à un LOT. Le filtre « Non assignées » et le <select> « Assigner à… » sont d'ailleurs absents du rendu pour un confirmateur (manage_woocommerce requis pour les voir), donc jamais atteignables par l'UI — cette garde couvre un POST forgé.",
		),

		'order_carrier'    => array(
			'caps'    => array( 'aod_cap_confirm_orders' ),
			'famille' => 'gouvernee',
			'pourquoi' => "Phase 47.1 — CHOISIR le transporteur d'une commande s'aligne sur la cap de section, PAS sur order_assign juste au-dessus, et la divergence est délibérée : assigner, c'est RÉPARTIR du travail (pouvoir gérant strict — un confirmateur ne doit pas se distribuer le sien) ; choisir un transporteur, c'est FAIRE le travail. Le confirmateur déclenchera l'envoi (47.2) : lui refuser la destination reviendrait à lui faire déclencher un envoi dont il ne maîtrise pas la cible. Le périmètre reste borné par order_in_scope() — il ne route que SES commandes.",
		),
		'ship_send'        => array(
			'caps'    => array( 'aod_cap_confirm_orders' ),
			'famille' => 'gouvernee',
			'pourquoi' => "Phase 47.2 — EXPÉDIER une commande s'aligne sur la cap de section, comme order_carrier et pour la même raison : c'est FAIRE le travail, pas le répartir. 🚨 Et ce n'est pas un élargissement de droit : jusqu'à la 47.2, ce même envoi partait TOUT SEUL au passage à « Confirmée » — donc déjà à la portée de tout porteur d'aod_cap_confirm_orders, sans qu'il l'ait demandé ni vu venir. Le handler ne fait que rendre EXPLICITE et DÉCIDÉ un pouvoir que la cap conférait déjà implicitement. Périmètre borné par order_in_scope() : le confirmateur n'expédie que SES commandes. Une commande par requête (le lot est une boucle navigateur) : voir le docblock d'ajax_ship_send() pour le calcul de timeout qui l'impose.",
		),
		'orders_bulk_carrier' => array(
			'caps'    => array( 'aod_cap_confirm_orders' ),
			'famille' => 'gouvernee',
			'pourquoi' => "MÊME cap que order_carrier, appliquée à un LOT : un lot n'est pas un pouvoir de plus, c'est le même geste répété. order_in_scope() est réévalué COMMANDE PAR COMMANDE dans bulk_carrier() — un identifiant hors périmètre glissé dans le POST est ignoré même si les autres passent (la liste ne fait que CACHER ce qui est hors périmètre).",
		),

		/* --- Section « Commandes abandonnées » (gouvernée : aod_cap_abandoned) - */
		'convert_lead'     => array(
			'caps'    => array( 'aod_cap_confirm_orders' ),
			'famille' => 'action-forte',
			'pourquoi' => "CRÉER une commande à partir d'un abandon est un acte sur les COMMANDES, pas la lecture de la liste des abandons. La section est gouvernée par aod_cap_abandoned (voir / rappeler) ; transformer un prospect en commande WooCommerce exige aod_cap_confirm_orders — le même droit que confirmer ou éditer une commande (invariant Phase 39). Bouton MASQUÉ et AJAX à 403 pour qui n'a que aod_cap_abandoned. NE PAS aligner sur la cap de section : ce serait un TROU (un simple droit de consultation créerait des commandes).",
		),
		'delete_leads'     => array(
			'caps'    => array( 'manage_woocommerce' ),
			'famille' => 'action-forte',
			'pourquoi' => "SUPPRIMER des prospects (39.6) est destructif et réservé au GÉRANT, exactement comme delete_orders sur la page Commandes (leçon 31.1 : une permission cohérente n'est pas une permission bornée). La section est gouvernée par aod_cap_abandoned (voir / rappeler / convertir) ; purger des prospects exige manage_woocommerce. Cases, barre d'action et AJAX ABSENTS pour un confirmateur aod_cap_abandoned (masqués ET 403). Gérant only → aucun filtre assigned_products (il voit tout).",
		),
		'lead_assign'      => array(
			'caps'    => array( 'manage_woocommerce' ),
			'famille' => 'action-forte',
			'pourquoi' => "ASSIGNER un prospect à un confirmateur (Phase 43.3) est le JUMEAU EXACT de order_assign, sur un lead plutôt qu'une commande : même pouvoir gérant strict, même raison. Un confirmateur ne doit pas pouvoir se réassigner (ou réassigner à un pair) des prospects à rappeler — ce serait se distribuer soi-même le travail. NE PAS aligner sur aod_cap_abandoned (la cap de section) : elle ouvre la CONSULTATION de la liste, pas la répartition du travail. Sélecteur, colonne « Assigné à » et filtre par confirmateur sont d'ailleurs absents du rendu pour un confirmateur (manage_woocommerce requis) — cette garde couvre un POST forgé.",
		),

		/* --- Section « Livraison » (gouvernée : aod_cap_shipping_prices) ------ */
		'save_shipping'    => array( 'caps' => array( 'aod_cap_shipping_prices' ), 'famille' => 'gouvernee' ),
		'import_fees'      => array( 'caps' => array( 'aod_cap_shipping_prices' ), 'famille' => 'gouvernee' ),
		'test_carrier'     => array(
			'caps'    => array( 'manage_woocommerce' ),
			'famille' => 'action-forte',
			'pourquoi' => "TESTER n'est pas une lecture : le handler ÉCRIT d'abord les identifiants postés (save_settings(), pour « tester exactement ce que voit le gérant ») avant d'appeler l'API. C'est une primitive d'écriture sur les COMPTES transporteurs, là qu'import_fees ne fait que LIRE une grille via un transporteur déjà connecté. 🚨 Cette ligne portait `aod_cap_shipping_prices` depuis la Phase 21, qui avait desserré la garde au motif que « qui peut importer doit pouvoir tester » — prémisse fausse, et c'est ce desserrage qui a ouvert la lecture des clés API et la redirection de l'envoi auto (31.1). Aligner sur la section était le bon réflexe appliqué au mauvais handler. NE PAS le ré-aligner.",
		),

		'save_cart_mode'   => array(
			'caps'    => array( 'manage_woocommerce' ),
			'famille' => 'action-forte',
			'pourquoi' => "Le MODE D'ACHAT de la boutique (panier COD activé ou non, Phase 35) n'est pas un tarif : un confirmateur « tarifs » personnalise une grille de prix, il ne choisit pas si la boutique a un panier. Même séparation de portée que le bloc Transporteurs (31.1) — carte masquée au rendu ET garde serveur.",
		),

		/* --- Section « Suivi » (gouvernée : aod_cap_tracking) ----------------- */
		'tracking_refresh' => array( 'caps' => array( 'aod_cap_tracking' ), 'famille' => 'gouvernee' ),
		'tracking_detail'  => array( 'caps' => array( 'aod_cap_tracking' ), 'famille' => 'gouvernee' ),

		/* --- Section « Stock » (gouvernée : aod_cap_stock) -------------------- */
		'save_stock'       => array(
			'caps'    => array( 'edit_products' ),
			'famille' => 'action-forte',
			'pourquoi' => "aod_cap_stock ouvre l'écran en LECTURE SEULE : le confirmateur consulte, seul un porteur d'edit_products écrit. En-tête différencié à class-aod-cd-section-stock.php:420-425.",
		),

		/* --- Sections réservées au gérant ------------------------------------- */
		'save_product'     => array( 'caps' => array( 'edit_products' ), 'famille' => 'manager' ),
		// 2026-08-09 — édition RAPIDE depuis la liste (modale). MÊME capability que
		// `save_product`, et c'est exact : modifier un prix ou un nom, c'est écrire
		// un produit — le fait que la modale n'en montre que quatre champs ne change
		// rien au droit exigé.
		// 🚨 Ce n'est PAS un alias de `save_product` pour autant : celui-là est un
		// gestionnaire de formulaire COMPLET (sans `description` au POST il vide la
		// description, et il réécrit les catégories dans tous les cas). Y router
		// quatre champs aurait mutilé le produit en silence. Le handler séparé est
		// la seule façon de borner ce qu'une modale partielle peut abîmer.
		// Finance (2026-08-10). `manage_woocommerce` et rien de moins : le
		// calculateur ne lit aucune commande, mais son historique est une donnée
		// de PILOTAGE — prix d'achat, budget publicitaire, marge réelle. C'est
		// précisément ce qu'un confirmateur n'a pas à voir, et la section elle-même
		// est absente de `$section_caps`, donc gérant-only par défaut.
		'finance_save'     => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'finance_delete'   => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'finance_produits' => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'finance_depense_add' => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'finance_depense_del' => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'finance_entreprise' => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'quick_product'    => array( 'caps' => array( 'edit_products' ), 'famille' => 'manager' ),
		'delete_product'   => array( 'caps' => array( 'delete_products' ), 'famille' => 'manager' ),
		// 2026-08-04 — duplication. `edit_products` et NON `delete_products` : créer
		// une copie n'est pas destructif (elle naît en brouillon). Même cap que
		// `save_product`, ce qui est exact — dupliquer, c'est écrire un produit.
		'duplicate_product' => array( 'caps' => array( 'edit_products' ), 'famille' => 'manager' ),
		'save_category'    => array( 'caps' => array( 'edit_products' ), 'famille' => 'manager' ),
		'delete_category'  => array( 'caps' => array( 'edit_products' ), 'famille' => 'manager' ),
		'save_landing'     => array( 'caps' => array( 'edit_products' ), 'famille' => 'manager' ),
		'copy_landing'     => array( 'caps' => array( 'edit_products' ), 'famille' => 'manager' ),
		'save_pixels'      => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'test_capi'        => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'save_whatsapp'    => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'test_whatsapp'    => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'reset_shop'       => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'manager' ),
		'save_account'     => array(
			'caps'    => array( 'manage_woocommerce' ),
			'famille' => 'manager',
			'pourquoi' => "ARBITRÉ (2026-07-21) : « le gérant gère les mots de passe ». Le confirmateur n'a pas de section « Mon compte » et son mot de passe se change depuis Équipe. 🚨 La garde paraît inutile si l'on ne lit QUE le handler (borné à \$user->ID) : elle est la seconde moitié d'une décision prise dans le routeur. Déjà prise pour un oubli une fois. NE PAS la desserrer — cf. ARCHITECTURE.md §6, ROADMAP D12.",
		),
		'save_social_links' => array(
			'caps'    => array( 'manage_woocommerce' ),
			'famille' => 'manager',
			'pourquoi' => "MÊME ARBITRAGE que save_account (2026-09-15) : réseaux sociaux + contact du pied de page rendus par `AOD_CD_Section_Account`, même section « Mon compte » réservée au gérant, même capacité — un confirmateur n'a pas plus à changer le lien Facebook de la boutique qu'à changer le mot de passe du compte.",
		),

		/* --- Gardes déléguées (cf. GARDES_PARTAGEES) --------------------------- */
		'save_member'      => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'deleguee' ),
		'delete_member'    => array( 'caps' => array( 'manage_woocommerce' ), 'famille' => 'deleguee' ),
		'desc_image'       => array( 'caps' => array( 'edit_products' ), 'famille' => 'deleguee' ),
		'desc_video'       => array( 'caps' => array( 'edit_products' ), 'famille' => 'deleguee' ),
	);
}

/**
 * Gardes partagées — liste blanche NOMMÉE et volontairement courte.
 *
 * 4 handlers sur 27 n'ont aucune vérification dans leur propre corps : ils
 * délèguent. C'est du code CORRECT — factoriser une garde est une bonne pratique.
 *
 * 🚨 Sans cette liste, le test produirait 4 faux positifs sur 27 (15 % de bruit sur
 * du code sain). C'est le mode d'échec de la DoD initiale de l'étape 20.1 : sa
 * première rédaction interdisait « tout hôte externe » et aurait fait rougir en
 * permanence 6 appels légitimes. Un test qui crie à tort finit désarmé — et un test
 * désarmé est pire que pas de test, parce qu'il rassure.
 *
 * Y ajouter une entrée doit rester un geste conscient.
 *
 * @return string[] Noms de méthodes qui portent une garde pour le compte d'autrui.
 */
function aod_test_perm_gardes_partagees() {
	return array( 'guard', 'handle_desc_upload' );
}

/**
 * Corps source d'une méthode, lignes brutes.
 *
 * ⚠️ Préfixe `aod_test_perm_` : `tests/all.php` charge tous les fichiers par
 * `glob()` dans le même espace global — un helper au nom générique provoque un
 * `Cannot redeclare` (déjà arrivé, cf. en-tête de `test-charte-surfaces.php`).
 * Ne pas s'inspirer de `test-contraste.php`, dont les helpers ne sont pas préfixés.
 *
 * @param ReflectionMethod $m Méthode.
 * @return string
 */
function aod_test_perm_corps( ReflectionMethod $m ) {
	$lignes = file( $m->getFileName() );
	if ( ! $lignes ) {
		return '';
	}
	return implode( '', array_slice( $lignes, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1 ) );
}

/**
 * Capabilities de la PREMIÈRE instruction de garde d'un corps.
 *
 * 🚨 Lire « le premier `current_user_can` » ne suffit pas. `ajax_delete_orders`
 * s'écrit :
 *
 *     if ( ! current_user_can( 'delete_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
 *
 * Un parseur qui retient le premier appel perd le `|| manage_woocommerce` et
 * conclut « TROU » sur une garde PLUS STRICTE que ce qu'il croit lire. On retient
 * donc l'instruction entière, avec TOUTES ses caps.
 *
 * Les gardes SUIVANTES sont ignorées : ce sont des défenses en profondeur
 * (revérification par commande, par produit, par périmètre), pas la garde de
 * section.
 *
 * Deux syntaxes reconnues : `AOD_CD_Roles::user_can( 'x' )` et
 * `current_user_can( 'x' )` — la regex `user_can\(` couvre les deux.
 *
 * @param string $corps Corps de la méthode.
 * @return string[]|null Caps trouvées, ou null si aucune instruction de garde.
 */
function aod_test_perm_caps_de_garde( $corps ) {
	$lignes = explode( "\n", $corps );
	$n      = count( $lignes );

	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! preg_match( '/^\s*if\s*\(/', $lignes[ $i ] ) ) {
			continue;
		}

		// Accumuler la condition jusqu'à l'accolade ouvrante (conditions multi-lignes).
		$cond = '';
		for ( $j = $i; $j < min( $i + 5, $n ); $j++ ) {
			$cond .= $lignes[ $j ] . ' ';
			if ( false !== strpos( $lignes[ $j ], '{' ) ) {
				break;
			}
		}
		if ( false === strpos( $cond, 'user_can(' ) ) {
			continue; // Garde non liée aux capabilities (session, objet introuvable…).
		}

		// Ce `if` est-il bien une garde ? Son corps doit refuser en 403.
		$suite = implode( ' ', array_slice( $lignes, $j, 6 ) );
		if ( false === strpos( $suite, '403' ) ) {
			continue;
		}

		preg_match_all( "/user_can\(\s*'([a-z_]+)'/", $cond, $m );
		if ( ! empty( $m[1] ) ) {
			$caps = array_values( array_unique( $m[1] ) );
			sort( $caps );
			return $caps;
		}
	}
	return null;
}

/**
 * Nom de la garde partagée appelée par un corps, s'il y en a une.
 *
 * @param string $corps Corps de la méthode.
 * @return string|null
 */
function aod_test_perm_delegation( $corps ) {
	foreach ( aod_test_perm_gardes_partagees() as $g ) {
		if ( preg_match( '/\$this->' . preg_quote( $g, '/' ) . '\s*\(/', $corps ) ) {
			return $g;
		}
	}
	return null;
}

/* =========================================================================
 * 1. Chaque handler exige la capability de sa section
 * ====================================================================== */

AOD_Test::group( 'Permissions — la garde de chaque handler AJAX suit sa section' );

$aod_perm_contrat  = aod_test_perm_contrat();
$aod_perm_vus      = 0;
$aod_perm_sans_non = 0;
$aod_perm_connus   = array(); // Actions RÉELLEMENT enregistrées (cf. entrées orphelines, 47.3).

foreach ( $GLOBALS['wp_filter'] as $aod_perm_hook => $aod_perm_obj ) {
	if ( 0 !== strpos( $aod_perm_hook, 'wp_ajax_aod_cd_' ) ) {
		continue;
	}
	$aod_perm_action = substr( $aod_perm_hook, strlen( 'wp_ajax_aod_cd_' ) );

	foreach ( $aod_perm_obj->callbacks as $aod_perm_cbs ) {
		foreach ( $aod_perm_cbs as $aod_perm_cb ) {
			$aod_perm_f = $aod_perm_cb['function'];
			if ( ! is_array( $aod_perm_f ) ) {
				continue;
			}
			++$aod_perm_vus;
			$aod_perm_connus[] = $aod_perm_action;

			$aod_perm_cls = is_object( $aod_perm_f[0] ) ? get_class( $aod_perm_f[0] ) : $aod_perm_f[0];
			$aod_perm_m   = new ReflectionMethod( $aod_perm_cls, $aod_perm_f[1] );
			$aod_perm_bod = aod_test_perm_corps( $aod_perm_m );

			// Le nonce est-il vérifié ? (dans le corps, ou dans la garde déléguée)
			$aod_perm_deleg = aod_test_perm_delegation( $aod_perm_bod );
			$aod_perm_full  = $aod_perm_bod;
			if ( $aod_perm_deleg ) {
				$aod_perm_gm    = new ReflectionMethod( $aod_perm_cls, $aod_perm_deleg );
				$aod_perm_full .= aod_test_perm_corps( $aod_perm_gm );
			}
			if ( false === strpos( $aod_perm_full, "check_ajax_referer( 'aod_cd'" ) ) {
				++$aod_perm_sans_non;
			}

			// AC 7 — un handler hors contrat échoue. C'est le cliquet pour le 28ᵉ.
			if ( ! isset( $aod_perm_contrat[ $aod_perm_action ] ) ) {
				AOD_Test::ok(
					false,
					sprintf(
						'HANDLER INCONNU « %s » : absent de la table de référence. Déclare sa cap attendue et sa famille dans aod_test_perm_contrat().',
						$aod_perm_action
					)
				);
				continue;
			}

			$aod_perm_att = $aod_perm_contrat[ $aod_perm_action ]['caps'];
			sort( $aod_perm_att );

			// Garde effective : celle du corps, ou celle de la garde déléguée (D2).
			$aod_perm_eff = aod_test_perm_caps_de_garde( $aod_perm_bod );
			if ( null === $aod_perm_eff && $aod_perm_deleg ) {
				$aod_perm_gm  = new ReflectionMethod( $aod_perm_cls, $aod_perm_deleg );
				$aod_perm_eff = aod_test_perm_caps_de_garde( aod_test_perm_corps( $aod_perm_gm ) );
			}

			if ( null === $aod_perm_eff ) {
				AOD_Test::ok(
					false,
					sprintf( 'TROU — « %s » n\'a AUCUNE garde de capability (ni chez lui, ni déléguée)', $aod_perm_action )
				);
				continue;
			}

			// D3 — nommer le SENS de l'écart. « Écart » seul ne dit pas à un
			// mainteneur s'il tient une gêne d'ergonomie ou une faille.
			$aod_perm_sens = '';
			if ( $aod_perm_eff !== $aod_perm_att ) {
				$aod_perm_fort_eff = in_array( 'manage_woocommerce', $aod_perm_eff, true );
				$aod_perm_fort_att = in_array( 'manage_woocommerce', $aod_perm_att, true );
				if ( $aod_perm_fort_eff && ! $aod_perm_fort_att ) {
					$aod_perm_sens = 'MUR (cap trop FORTE : le confirmateur légitime est bloqué)';
				} elseif ( $aod_perm_fort_att && ! $aod_perm_fort_eff ) {
					$aod_perm_sens = '🚨 TROU (cap trop FAIBLE : écran atteint par qui ne devrait pas)';
				} else {
					$aod_perm_sens = 'ÉCART';
				}
			}

			AOD_Test::eq(
				implode( '+', $aod_perm_att ),
				implode( '+', $aod_perm_eff ),
				sprintf(
					'%s exige la cap de sa section (%s)%s',
					$aod_perm_action,
					$aod_perm_contrat[ $aod_perm_action ]['famille'],
					$aod_perm_sens ? ' — ' . $aod_perm_sens : ''
				)
			);
		}
	}
}

/* --- Témoins (DoD §C.2 : un scan vide se déclarerait vert) ---------------- */

AOD_Test::ok(
	$aod_perm_vus >= 27,
	sprintf( 'Handlers AJAX réellement inspectés : %d (27 attendus au minimum)', $aod_perm_vus )
);

AOD_Test::eq( 0, $aod_perm_sans_non, 'Tous les handlers vérifient le nonce `aod_cd` (chez eux ou via leur garde partagée)' );

// 🚨 47.3 — LA TABLE DE RÉFÉRENCE POUVAIT ROTER EN SILENCE, ET C'EST CETTE ÉTAPE
// QUI L'A MONTRÉ. Le parseur crie « HANDLER INCONNU » quand le CODE porte un
// handler absent de la table — mais rien ne regardait le sens inverse : une entrée
// dont le handler a disparu restait là, verte, à décrire un droit qui n'existe plus.
// Vérifié à la suppression de `order_note` : la suite est restée verte avec l'entrée
// périmée en place. Une table de référence qu'on ne confronte que dans un sens finit
// par décrire un autre logiciel que celui qui tourne.
$aod_perm_orphelines = array_diff( array_keys( $aod_perm_contrat ), $aod_perm_connus );
AOD_Test::eq(
	'',
	implode( ', ', $aod_perm_orphelines ),
	'Toute entrée de la table correspond à un handler RÉELLEMENT enregistré (aucune entrée périmée)'
);

// Toute entrée 'action-forte' doit porter sa justification (AC 8).
$aod_perm_injust = array();
foreach ( $aod_perm_contrat as $aod_perm_a => $aod_perm_e ) {
	if ( 'action-forte' === $aod_perm_e['famille'] && empty( $aod_perm_e['pourquoi'] ) ) {
		$aod_perm_injust[] = $aod_perm_a;
	}
}
AOD_Test::eq(
	'',
	implode( ', ', $aod_perm_injust ),
	'Toute divergence VOULUE porte sa raison écrite (une divergence sans raison est un échec de revue)'
);

/* --- Témoins du parseur lui-même ----------------------------------------- */

AOD_Test::eq(
	'aod_cap_shipping_prices',
	implode( '+', (array) aod_test_perm_caps_de_garde(
		"function x() {\n\tcheck_ajax_referer( 'aod_cd', 'nonce' );\n\tif ( ! AOD_CD_Roles::user_can( 'aod_cap_shipping_prices' ) ) {\n\t\twp_send_json_error( array(), 403 );\n\t}\n}"
	) ),
	'Témoin : le parseur lit une garde `AOD_CD_Roles::user_can()`'
);

AOD_Test::eq(
	'delete_shop_orders+manage_woocommerce',
	implode( '+', (array) aod_test_perm_caps_de_garde(
		"function x() {\n\tif ( ! current_user_can( 'delete_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {\n\t\twp_send_json_error( array(), 403 );\n\t}\n}"
	) ),
	'Témoin : une condition composée rend ses DEUX caps (sinon `delete_orders` passerait pour un trou)'
);

AOD_Test::eq(
	'aod_cap_stock',
	implode( '+', (array) aod_test_perm_caps_de_garde(
		"function x() {\n\tif ( ! \$user || ! \$user->exists() ) {\n\t\twp_send_json_error( array(), 403 );\n\t}\n\tif ( ! AOD_CD_Roles::user_can( 'aod_cap_stock' ) ) {\n\t\twp_send_json_error( array(), 403 );\n\t}\n\tif ( ! current_user_can( 'edit_product', \$pid ) ) {\n\t\twp_send_json_error( array(), 403 );\n\t}\n}"
	) ),
	'Témoin : une garde non-capability est sautée, et la revérification par objet ignorée'
);

AOD_Test::ok(
	null === aod_test_perm_caps_de_garde( "function x() {\n\tcheck_ajax_referer( 'aod_cd', 'nonce' );\n\treturn 1;\n}" ),
	'Témoin : un corps sans garde rend null (et non un tableau vide qui passerait pour une garde)'
);

/* =========================================================================
 * 2. Contrôles structurels — gratuits sur le même parcours
 * ====================================================================== */

AOD_Test::group( 'Permissions — invariants de structure' );

// Aucun point AJAX de /gestion n'est ouvert aux visiteurs. Il n'y en a jamais eu :
// l'assertion est là pour qu'il n'y en ait jamais.
$aod_perm_nopriv = array();
foreach ( array_keys( $GLOBALS['wp_filter'] ) as $aod_perm_h ) {
	if ( 0 === strpos( $aod_perm_h, 'wp_ajax_nopriv_aod_cd_' ) ) {
		$aod_perm_nopriv[] = $aod_perm_h;
	}
}
AOD_Test::eq( '', implode( ', ', $aod_perm_nopriv ), 'Aucun `wp_ajax_nopriv_aod_cd_*` : /gestion n\'expose rien aux visiteurs' );

$aod_perm_dash = new ReflectionClass( 'AOD_CD_Dashboard' );
$aod_perm_pcap = $aod_perm_dash->getProperty( 'section_caps' );
$aod_perm_pcap->setAccessible( true );
$aod_perm_caps = $aod_perm_pcap->getValue( AOD_CD_Dashboard::instance() );

$aod_perm_msec = $aod_perm_dash->getMethod( 'sections' );
$aod_perm_msec->setAccessible( true );
$aod_perm_secs = array_keys( $aod_perm_msec->invoke( AOD_CD_Dashboard::instance() ) );

$aod_perm_orph = array_diff( array_keys( $aod_perm_caps ), $aod_perm_secs );
AOD_Test::eq( '', implode( ', ', $aod_perm_orph ), 'Toute clé de $section_caps est une section réelle (une clé orpheline ne gouvernerait rien)' );

$aod_perm_hors = array();
foreach ( $aod_perm_caps as $aod_perm_s => $aod_perm_l ) {
	foreach ( (array) $aod_perm_l as $aod_perm_c ) {
		if ( ! in_array( $aod_perm_c, AOD_CD_Roles::CONFIRMER_CAPS, true ) ) {
			$aod_perm_hors[] = $aod_perm_s . '/' . $aod_perm_c;
		}
	}
}
AOD_Test::eq( '', implode( ', ', $aod_perm_hors ), 'Toute cap de $section_caps appartient à CONFIRMER_CAPS (sinon aucune case ne la donne dans Équipe)' );
