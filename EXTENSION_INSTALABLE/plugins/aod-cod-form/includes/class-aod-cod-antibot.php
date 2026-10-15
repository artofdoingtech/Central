<?php
/**
 * Les deux couches anti-bot « de formulaire », écrites une seule fois (Phase 32.3).
 *
 * 🚨 CE QUI ÉTAIT FAUX, ET CE N'ÉTAIT PAS D'ABORD UN DÉFAUT DE SÉCURITÉ. Le dépôt
 * annonçait « anti-bot 5 couches » (README, TEMPLATE.md, docs de la page de vente
 * — et « **6** couches » à deux endroits, ce qui aurait dû alerter tout seul). Sur
 * un clone neuf, il en tenait deux et demie :
 *
 *   1. Honeypot — se contournait en **ne remplissant pas** le champ piège.
 *   2. Time-trap — se contournait en **omettant** le champ `elapsed` : la garde
 *      s'écrivait `isset() && is_numeric() && < 3000`, donc un payload qui ne
 *      portait pas le champ traversait les trois conditions sans en heurter une.
 *   3. Turnstile — **n'existe pas** tant qu'aucune clé n'est saisie (TEMPLATE.md §3).
 *   4. Plafond par IP — réel (réparé en 26.1).
 *   5. Verrou 24 h par téléphone — réel.
 *
 * **Une protection qu'on croit avoir est plus dangereuse qu'une protection qu'on
 * sait absente : elle empêche d'en chercher une autre.** C'est pour ça que cette
 * étape corrige le code ET le discours ; corriger le seul code aurait laissé
 * intacte la moitié dangereuse du problème.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CE QUE CHANGE LA 32.3 : **l'ABSENCE d'un champ devient aussi disqualifiante que
 * sa mauvaise valeur.** Un robot qui POSTe directement sur `admin-ajax.php` fait
 * son payload à la main : il ne devine ni le nom du champ piège, ni qu'il faut
 * annoncer une durée de remplissage plausible. Il devait, jusqu'ici, seulement
 * éviter de se tromper ; il doit désormais **reproduire** le formulaire.
 *
 * ⚠️ ET C'EST UN ARBITRAGE, PAS UN GAIN GRATUIT. Le commentaire d'origine
 * justifiait la tolérance : « une page servie depuis un cache avec l'ancien JS ne
 * doit JAMAIS bloquer un vrai client ». L'inquiétude est légitime — elle est juste
 * mal réglée. Ce qui la borne :
 *
 *   • Les deux champs partent du MÊME bloc JS, dans la MÊME requête : soit le
 *     payload vient de notre formulaire (les deux présents), soit il n'en vient
 *     pas (aucun des deux). Il n'y a pas d'entre-deux réaliste.
 *   • jQuery sérialise une valeur `undefined` en chaîne VIDE : un HTML périmé
 *     (sans le champ piège) envoie donc `aod_url=` — présent et vide, donc
 *     accepté. Seul un JS périmé déclenche le refus.
 *   • Le refus est **récupérable** : recharger la page suffit, et le message reste
 *     neutre.
 *   • Surtout, il est **JOURNALISÉ**. Un blocage légitime devient visible au lieu
 *     d'être une vente perdue en silence. C'est la leçon de la Phase 26 : un
 *     durcissement muet est indéfendable, un durcissement tracé se règle.
 *
 * 🔒 POURQUOI LA VALEUR EST PASSÉE EN ARGUMENT ET NON LUE ICI. Cette classe ne
 * touche pas à `$_POST`. Les deux appelants noncent puis lisent eux-mêmes, et
 * transmettent — le contrat reste STRUCTUREL au lieu d'être documentaire (leçon
 * de la Phase 24, qui a fait passer `capi_token_from_post()` en `private static`
 * pour exactement cette raison).
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_COD_Antibot {

	/**
	 * Durée de remplissage minimale, en millisecondes.
	 *
	 * Un humain ne saisit pas nom + téléphone + wilaya + commune + adresse en
	 * moins de trois secondes. Seuil volontairement bas : il vise le robot qui
	 * poste instantanément, pas l'acheteur rapide.
	 */
	const MIN_FILL_MS = 3000;

	/**
	 * Inspecte les deux champs anti-bot du formulaire.
	 *
	 * @param string|null $honeypot Valeur brute du champ piège, ou null s'il est ABSENT du payload.
	 * @param string|null $elapsed  Durée de remplissage annoncée, ou null si ABSENTE.
	 * @return string '' si tout va bien, sinon un code de refus :
	 *                'honeypot' | 'champs_absents' | 'duree_invalide' | 'trop_rapide'.
	 */
	public static function inspect( $honeypot, $elapsed ) {
		// Le piège d'abord : c'est le seul cas où le robot s'est trahi en FAISANT
		// quelque chose, et non en omettant quelque chose.
		if ( null !== $honeypot && '' !== trim( (string) $honeypot ) ) {
			return 'honeypot';
		}

		// 🚨 LE CŒUR DE L'ÉTAPE. Un payload fabriqué à la main ne porte ni l'un ni
		// l'autre : l'absence vaut désormais aveu. Les deux sont exigés ensemble —
		// n'en exiger qu'un laisserait la moitié du contournement ouverte.
		if ( null === $honeypot || null === $elapsed ) {
			return 'champs_absents';
		}

		// Présent mais illisible : « abc » traversait l'ancienne garde, parce que
		// `is_numeric()` y était une CONDITION D'APPLICATION et non une exigence.
		if ( ! is_numeric( $elapsed ) ) {
			return 'duree_invalide';
		}

		if ( (int) $elapsed < self::MIN_FILL_MS ) {
			return 'trop_rapide';
		}

		return '';
	}

	/**
	 * Traduit un code de refus en phrase de journal.
	 *
	 * ⚠️ Non traduite, à dessein : ce n'est pas de l'UI. Un journal doit lire pareil
	 * quelle que soit la langue du gérant qui l'ouvre (convention posée en 31.3).
	 * Le message rendu à l'acheteur, lui, reste NEUTRE et identique pour tous les
	 * motifs — indiquer au robot quelle couche a réagi lui donnerait la carte.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function reason( $code ) {
		$table = array(
			'honeypot'       => 'champ piège rempli (robot qui remplit tout)',
			'champs_absents' => 'payload sans les champs du formulaire (POST direct, ou JS périmé côté acheteur)',
			'duree_invalide' => 'durée de remplissage illisible',
			'trop_rapide'    => 'formulaire rempli en moins de ' . ( self::MIN_FILL_MS / 1000 ) . ' s',
		);
		return isset( $table[ $code ] ) ? $table[ $code ] : $code;
	}
}
