<?php
/**
 * Temps relatif de l'interface de gestion.
 *
 * 🚨 POURQUOI UN FICHIER POUR UNE MÉTHODE. Ce helper a d'abord été posé dans
 * `class-aod-cd-dashboard.php`, à côté de `pill()`. R1 de `test-decomposition.php`
 * l'a refusé : le fichier passait de 1 087 à 1 155 lignes, et son plafond « ne se
 * remonte pas ». Le cliquet a raison, et sa règle dit exactement quoi faire —
 * « du code neuf va dans sa section, pas dans le fichier-dieu ». Sauf que celui-ci
 * n'appartient à aucune section : Prospects, Suivi et Accueil l'appellent tous
 * les trois. Le mettre dans l'un d'eux ferait dépendre deux sections d'une
 * troisième ; le laisser dans le monolithe le regonflerait. D'où sa propre classe.
 *
 * ⚠️ Si un deuxième utilitaire de date apparaît (formatage court, plage de
 * dates…), il vient ICI. C'est le sens du nom : « temps », pas « depuis ».
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Temps {

	/**
	 * « il y a … », dans la langue de l'INTERFACE (2026-08-08).
	 *
	 * 🔴 REMPLACE `human_time_diff()` AUX TROIS ENDROITS QUI L'UTILISAIENT, et
	 * ce n'est pas un goût de formulation : c'était un défaut de langue visible à
	 * l'œil nu. Mesuré sur ce clone — `get_locale()` rend **en_US**, et le seul
	 * paquet de langue installé est l'arabe. Or `human_time_diff()` est une
	 * fonction du CŒUR de WordPress : elle traduit dans le domaine `default`, pas
	 * dans le nôtre. Le tableau des prospects affichait donc « 2 hours » et
	 * « 4 days » au milieu d'une interface française, et Suivi comme Accueil
	 * rendaient « il y a 4 hours » — moitié français, moitié anglais.
	 *
	 * 🚨 POURQUOI PAS « INSTALLER fr_FR ». Parce que ça déplacerait le problème
	 * sur l'installateur du clone. Le template porte ses propres traductions
	 * (FR source + AR), et il tourne chez des gens dont le WordPress peut être en
	 * n'importe quelle langue. Une chaîne d'interface qui dépend du paquet de
	 * langue du cœur est une chaîne qu'on ne contrôle pas. *Tout ce que
	 * `/gestion` écrit doit venir du domaine `/gestion`.*
	 *
	 * ⚠️ Les 24 autres appels de date du plugin (`wp_date`, `date_i18n`) ne sont
	 * PAS concernés : leurs formats sont numériques (`d/m/Y`), donc sans mot à
	 * traduire. Vérifié avant d'écrire ceci, plutôt que supposé.
	 *
	 * ⚠️ Unités abrégées : la colonne « Quand » est étroite, et « min », « h »,
	 * « j », « sem. », « mois » sont invariables — seule l'année a besoin d'un
	 * pluriel, d'où un seul `_n()` au lieu de sept.
	 *
	 * @param int $ts  Horodatage passé (UNIX).
	 * @param int $now Référence ; 0 = maintenant.
	 * @return string Texte traduit, sans balise.
	 */
	public static function depuis( $ts, $now = 0 ) {
		$now   = $now ? (int) $now : time();
		$ecart = max( 0, $now - (int) $ts );

		if ( $ecart < MINUTE_IN_SECONDS ) {
			return __( 'à l’instant', 'aod-client-dashboard' );
		}
		if ( $ecart < HOUR_IN_SECONDS ) {
			$n = (int) round( $ecart / MINUTE_IN_SECONDS );
			/* translators: %s = nombre de minutes. */
			return sprintf( __( 'il y a %s min', 'aod-client-dashboard' ), number_format_i18n( $n ) );
		}
		if ( $ecart < DAY_IN_SECONDS ) {
			$n = (int) round( $ecart / HOUR_IN_SECONDS );
			/* translators: %s = nombre d'heures. */
			return sprintf( __( 'il y a %s h', 'aod-client-dashboard' ), number_format_i18n( $n ) );
		}
		if ( $ecart < WEEK_IN_SECONDS ) {
			$n = (int) round( $ecart / DAY_IN_SECONDS );
			/* translators: %s = nombre de jours. */
			return sprintf( __( 'il y a %s j', 'aod-client-dashboard' ), number_format_i18n( $n ) );
		}
		if ( $ecart < MONTH_IN_SECONDS ) {
			$n = (int) round( $ecart / WEEK_IN_SECONDS );
			/* translators: %s = nombre de semaines. */
			return sprintf( __( 'il y a %s sem.', 'aod-client-dashboard' ), number_format_i18n( $n ) );
		}
		if ( $ecart < YEAR_IN_SECONDS ) {
			$n = (int) round( $ecart / MONTH_IN_SECONDS );
			/* translators: %s = nombre de mois. */
			return sprintf( __( 'il y a %s mois', 'aod-client-dashboard' ), number_format_i18n( $n ) );
		}
		$n = (int) round( $ecart / YEAR_IN_SECONDS );
		/* translators: %s = nombre d'années. */
		return sprintf( _n( 'il y a %s an', 'il y a %s ans', $n, 'aod-client-dashboard' ), number_format_i18n( $n ) );
	}
}
