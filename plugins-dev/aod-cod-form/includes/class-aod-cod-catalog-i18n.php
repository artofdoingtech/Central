<?php
/**
 * Socle bilingue du CATALOGUE (produits + catégories) — étape 7.1.
 *
 * `gettext`/`.po` ne traduit que le CODE ; le contenu saisi par le gérant (titre
 * produit, description, arguments, noms de catégories) n'y passe jamais. On le
 * rendra bilingue en FILTRANT la sortie du cœur WooCommerce (the_title,
 * the_content, getters WC…) — mais ces filtres sont GLOBAUX. Sans garde, ils
 * traduiraient aussi le titre dans l'e-mail de commande, dans le bordereau
 * transporteur, et dans l'éditeur `/gestion` où le gérant doit voir/éditer le FR
 * canonique.
 *
 * Cette classe est l'ARMATURE de décision, posée une seule fois :
 *   - `pick()`             : choisit le texte d'un couple FR/AR (repli bidirectionnel) — PURE ;
 *   - `should_translate()` : la garde (langue AR, hors admin, contexte public) — PURE ;
 *   - `active()`/`is_front_catalog()` : les wrappers qui récoltent les vrais signaux WP ;
 *   - `render()`           : le point d'entrée que les filtres de 7.2+ appelleront.
 *
 * ⚠️ ÉTAPE 7.1 = INFRASTRUCTURE DORMANTE. Cette classe n'attache AUCUN filtre :
 * tant que 7.2 ne l'appelle pas, le rendu du site est strictement identique.
 *
 * Convention de stockage (actée ici, câblée en 7.2+) : le pendant arabe d'un champ
 * catalogue vit dans une méta PARALLÈLE suffixée `_ar` (produit : `_aod_title_ar`,
 * `_aod_desc_ar`… ; catégorie : term-meta de même forme). Le champ cœur WP
 * (post_title, post_content, nom de terme) reste le FR canonique et n'est JAMAIS
 * modifié. Absence de `_ar` = repli FR automatique.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Catalog_I18n {

	/**
	 * Choisit le texte à afficher d'un couple { FR, AR }, avec repli BIDIRECTIONNEL.
	 *
	 * Le contenu marchand peut être saisi dans une seule langue (un gérant peut
	 * livrer un produit 100 % arabe) : on ne laisse jamais un trou à l'écran.
	 * (`AOD_COD_Data::label()` ne replie que AR→FR — insuffisant ici.)
	 *
	 * Fonction PURE : la langue est un argument, pas un appel WP → testable seule.
	 *
	 * @param mixed $fr     Texte français (source canonique).
	 * @param mixed $ar     Texte arabe.
	 * @param bool  $arabic Le site doit-il afficher l'arabe ?
	 * @return string
	 */
	public static function pick( $fr, $ar, $arabic ) {
		$fr = trim( (string) $fr );
		$ar = trim( (string) $ar );
		if ( $arabic ) {
			return '' !== $ar ? $ar : $fr;
		}
		return '' !== $fr ? $fr : $ar;
	}

	/**
	 * Même chose, sur une LISTE (arguments de vente…) : deux listes appariées par INDEX.
	 *
	 * Les deux versions sont saisies dans deux zones « un par ligne » : rien ne garantit
	 * qu'elles aient le même nombre de lignes. D'où les règles, toutes voulues :
	 *
	 *  • `$ar` vide  → on rend `$fr` TEL QUEL. Un produit non traduit doit s'afficher
	 *    exactement comme avant — on ne recalcule rien, on ne trimme rien.
	 *  • ligne AR manquante → repli sur la ligne FR de même rang.
	 *  • 🚨 **La liste FR est CANONIQUE : c'est elle qui fixe le nombre de puces.** Une
	 *    traduction ne décide pas d'ajouter un argument. Sans cette règle, un gérant
	 *    ayant saisi 5 lignes arabes pour 3 lignes françaises verrait **2 puces arabes
	 *    surgir sur le site FRANÇAIS** (le repli bidirectionnel de `pick()` remplirait
	 *    les rangs 4 et 5, où le FR est absent). Les lignes AR au-delà du rang FR sont
	 *    donc ignorées.
	 *  • seule exception : une liste FR **entièrement vide** (produit livré 100 % arabe)
	 *    laisse l'AR mener — c'est le repli bidirectionnel, au niveau de la LISTE.
	 *  • ligne vide des deux côtés → omise (jamais de puce vide).
	 *
	 * ⚠️ Ne PAS passer par `AOD_COD_Catalog_Filters::swap()` : il rend `$fr` dès que l'AR
	 * est vide, ce qui conviendrait à un titre (toujours rempli) mais casserait le repli
	 * bidirectionnel ici, où c'est parfois le FR qui manque.
	 *
	 * L'appariement par rang est ASSUMÉ : réordonner une seule des deux listes les
	 * désaligne. Les deux zones sont côte à côte dans `/gestion`, ce qui rend le
	 * décalage visible. On n'invente pas d'appariement « intelligent ».
	 *
	 * Fonction PURE : la langue est un argument.
	 *
	 * @param mixed $fr     Liste française (source canonique).
	 * @param mixed $ar     Liste arabe ('' / absente = pas de traduction).
	 * @param bool  $arabic Le site doit-il afficher l'arabe ?
	 * @return string[]
	 */
	public static function pick_list( $fr, $ar, $arabic ) {
		$fr = is_array( $fr ) ? array_values( $fr ) : array();
		$ar = is_array( $ar ) ? array_values( $ar ) : array();
		if ( ! $ar ) {
			return $fr; // Aucune traduction : la liste FR, à l'identique.
		}
		$out = array();
		// Le FR canonique fixe la structure ; sans aucune ligne FR, l'AR mène.
		$n = $fr ? count( $fr ) : count( $ar );
		for ( $i = 0; $i < $n; $i++ ) {
			$line = self::pick(
				isset( $fr[ $i ] ) ? $fr[ $i ] : '',
				isset( $ar[ $i ] ) ? $ar[ $i ] : '',
				$arabic
			);
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * La garde : faut-il traduire, compte tenu du contexte ?
	 *
	 * Fonction PURE : les trois signaux sont des arguments → table de vérité
	 * testable sans pouvoir flipper `WP_ADMIN` / `is_admin()`.
	 *
	 * @param bool $arabic   Langue arabe active.
	 * @param bool $is_admin Requête back-office (wp-admin, e-mails de commande).
	 * @param bool $front    Contexte public catalogue (pas l'éditeur `/gestion`).
	 * @return bool
	 */
	public static function should_translate( $arabic, $is_admin, $front ) {
		return (bool) $arabic && ! $is_admin && (bool) $front;
	}

	/**
	 * Sommes-nous dans un contexte PUBLIC catalogue (et pas l'éditeur gérant) ?
	 *
	 * Piège : `/gestion` est du FRONT-END (pas wp-admin), donc `is_admin()` ne
	 * l'exclut pas — or le gérant y édite le FR canonique. Le dashboard pose la
	 * query var `aod_cd=1` sur toute requête `/gestion`
	 * (AOD_CD_Dashboard::add_rewrite) : c'est un signal WordPress découplé, on
	 * n'appelle aucune méthode du dashboard.
	 *
	 * @return bool
	 */
	public static function is_front_catalog() {
		return ! (bool) get_query_var( 'aod_cd' );
	}

	/**
	 * La garde, câblée aux vrais signaux : langue AR, hors admin, contexte public.
	 *
	 * @return bool
	 */
	public static function active() {
		return self::should_translate( AOD_COD_Data::is_arabic(), is_admin(), self::is_front_catalog() );
	}

	/**
	 * Le point d'entrée des filtres de 7.2+ : rend le FR ou l'AR selon la garde.
	 *
	 * Quand la garde est FERMÉE (admin, e-mail, `/gestion`, site FR), retourne `$fr`
	 * INCHANGÉ — c'est le FR canonique tel que le cœur WP l'a produit, octet pour
	 * octet, aucun effet de bord. Quand elle est ouverte, `pick()` avec repli.
	 *
	 * @param mixed  $fr Texte canonique (sortie du cœur : the_title, get_name…).
	 * @param string $ar Pendant arabe (méta `_ar`), '' si absent.
	 * @return mixed
	 */
	public static function render( $fr, $ar ) {
		return self::active() ? self::pick( $fr, $ar, true ) : $fr;
	}
}
