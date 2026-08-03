<?php
/**
 * Le plugin « AOD COD Form » est-il là ? (Phase 32.1)
 *
 * 🚨 CE QUI ÉTAIT FAUX. Les deux plugins maison ont des interrupteurs
 * d'activation INDÉPENDANTS dans wp-admin. Le chargeur du tableau de bord
 * déclare pourtant, noir sur blanc, une « dépendance forte (NON BLOQUANTE) » :
 * sans `aod-cod-form`, « ces parties de l'espace de gestion se désactivent
 * PROPREMENT ». C'était une promesse que le code ne tenait pas : `/gestion`
 * tombait en **erreur fatale**.
 *
 * Reproduit avant correctif :
 *
 *     Fatal error: Uncaught Error: Class "AOD_Carrier" not found
 *     in .../includes/class-aod-cd-section-home.php:550
 *
 * Un clic dans wp-admin — celui d'un gérant qui débranche le formulaire COD le
 * temps d'un essai — et l'espace de gestion est mort. Pas dégradé : mort, avec
 * un écran blanc et aucune indication de la cause.
 *
 * 🚨 LE PIÈGE, ET IL EST SUBTIL. La garde EXISTAIT. `count_tracking_status()`
 * commence bien par `if ( ! class_exists( 'AOD_Carrier' ) ) { return 0; }`.
 * Mais l'appel s'écrivait :
 *
 *     $can_tracking ? $this->count_tracking_status( AOD_Carrier::ST_FAILED ) : 0
 *
 * et **PHP résout la constante pour construire l'appel, donc AVANT d'entrer
 * dans la méthode**. La garde ne pouvait jamais s'exécuter. Une garde placée
 * derrière ce qu'elle protège n'est pas une garde ; elle est pire, parce qu'elle
 * donne à la relecture toutes les apparences de la sûreté.
 *
 * D'OÙ LE PARTI PRIS DE CETTE CLASSE : on ne garde pas chaque constante, une par
 * une — c'est la course sans fin, et il suffit d'en oublier une. **On conditionne
 * la FONCTIONNALITÉ à sa dépendance, une seule fois**, en amont : sans le plugin
 * COD, il n'y a pas de transporteur, donc pas de suivi de colis, donc la tuile
 * n'est pas construite et aucune constante n'est lue. C'est aussi la lecture
 * juste du métier, pas seulement une parade technique.
 *
 * ⚠️ Ce n'est PAS une dépendance bloquante, et il ne faut pas la transformer en
 * telle. Produits, catégories, équipe, mon compte n'ont aucun besoin du plugin
 * COD : les rendre indisponibles « par sécurité » retirerait au gérant des
 * écrans qui marchent très bien. Le contrat reste celui déjà écrit dans le
 * chargeur — dégradation propre, pas refus de démarrer.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Cod {

	/**
	 * Le socle COD (transporteurs, suivi, tarifs par wilaya) est-il disponible ?
	 *
	 * On teste `AOD_Carrier` et non la constante `AOD_COD_VERSION` : c'est cette
	 * classe-là que le tableau de bord manipule, et c'est son absence qui provoque
	 * la fatale. Tester autre chose reviendrait à vérifier une chose et à en
	 * utiliser une autre.
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'AOD_Carrier' );
	}

	/**
	 * Le suivi des colis est-il utilisable par l'utilisateur courant ?
	 *
	 * Réunit les deux conditions qui vont toujours ensemble : le droit ET la
	 * dépendance. Les séparer, c'est rouvrir le défaut — quelqu'un finira par
	 * tester le droit seul.
	 *
	 * @return bool
	 */
	public static function can_tracking() {
		if ( ! self::active() ) {
			return false;
		}
		return AOD_CD_Roles::user_can( 'aod_cap_tracking' ) || current_user_can( 'manage_woocommerce' );
	}
}
