<?php
/**
 * Le seul point du dépôt qui téléverse un fichier (Phase 32.2).
 *
 * ⚠️ CE QUE CETTE CLASSE N'EST PAS. Elle ne ferme PAS une faille, et l'énoncé de
 * la story se trompait sur ce point — il rangeait ces cinq appels dans « la
 * surface d'entrée NON AUTHENTIFIÉE ». Vérifié : les cinq sont derrière
 * `check_ajax_referer( 'aod_cd' )` **et** `current_user_can( 'edit_products' )`,
 * et **aucun** `wp_ajax_nopriv_*` n'existe dans ce plugin. Un confirmateur ne les
 * atteint pas non plus : `edit_products` est une capability du cœur WordPress, et
 * `AOD_CD_Roles::CONFIRMER_CAPS` ne contient que des `aod_cap_*`. **Seuls le
 * gérant et l'administrateur y accèdent** — c'est-à-dire le propriétaire de la
 * boutique, sur sa propre boutique.
 *
 * Le dire franchement change ce qu'on attend de ce fichier. Sans liste blanche,
 * WordPress s'en remettait à `get_allowed_mime_types()`, qui autorise PDF, DOC,
 * XLS, PSD, ZIP, RAR, MP3… Le champ « photo du produit » acceptait donc une
 * archive : la boutique devenait un hébergeur de fichiers, et personne ne s'en
 * apercevait — l'image cassée dans la grille était le seul symptôme. Aujourd'hui,
 * c'est donc surtout de l'HYGIÈNE.
 *
 * 🔴 MAIS PAS SEULEMENT, ET C'EST LA RAISON QUI COMPTE VRAIMENT. `get_allowed_mime_types()`
 * n'est pas une constante : c'est une liste FILTRABLE. Le jour où un clone installe
 * un plugin qui autorise le SVG — banal sur un thème de design — les cinq champs
 * deviennent une **XSS stockée**, sans qu'une seule ligne de ce dépôt ait changé.
 * Une liste blanche EXPLICITE ne bouge pas quand l'environnement bouge : c'est
 * précisément ce qu'on lui demande.
 *
 * 🚨 LA VRAIE BARRIÈRE EST ICI, PAS DANS LE HTML. Les `accept="image/*"` des
 * champs sont un confort de sélecteur de fichiers : ils se contournent en
 * renommant, ou en postant directement. La liste passée à `media_handle_upload()`
 * est le seul contrôle serveur.
 *
 * 🚨 `test_form` — LE PIÈGE À NE PAS ROUVRIR. Le 4ᵉ argument de
 * `media_handle_upload()` vaut par défaut `array( 'test_form' => false )`, et un
 * tableau fourni le REMPLACE au lieu de le compléter. Passer `mimes` sans
 * re-poser `test_form => false` fait échouer **tous** les envois (« Invalid form
 * submission »). Les cinq appels d'origine ne passaient aucun `$overrides` : ils
 * vivaient tous sur ce défaut. C'est exactement la marche sur laquelle on trébuche
 * en ajoutant une liste blanche, et c'est pourquoi il n'y a plus qu'un seul appel
 * dans tout le dépôt — celui d'en dessous.
 *
 * @package AOD_Client_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_CD_Upload {

	/**
	 * Images acceptées.
	 *
	 * Le GIF est conservé : la liste blanche déjà livrée en 8.2 pour les images de
	 * description l'autorisait, et cette classe unifie les deux — retirer un format
	 * au passage serait un changement de comportement non demandé, glissé dans un
	 * correctif. L'AVIF est ajouté (WordPress le gère nativement) pour la même
	 * raison inverse : c'est un format d'image moderne qu'un gérant peut légitimement
	 * produire, et le refuser sans le dire serait tout aussi silencieux.
	 */
	const IMAGE_MIMES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
		'avif'         => 'image/avif',
	);

	/**
	 * Vidéos acceptées — limitées au web-lisible sans transcodage.
	 *
	 * ⚠️ CETTE LISTE EST NÉCESSAIRE, et l'oublier aurait CASSÉ une fonctionnalité
	 * livrée. Le bloc « Vidéo » d'une page de vente (Phase 6) porte un champ
	 * `file` de type `video` — « Ou fichier vidéo (MP4) » — qui passe par le MÊME
	 * `upload_one()` que les images de héro, de couverture et d'avis. Une liste
	 * blanche image-seule appliquée sans regarder aurait rendu ce champ inopérant,
	 * avec pour tout diagnostic un message de refus de format. C'est le risque
	 * propre à ce genre de correctif : **la restriction casse en silence ce qu'on
	 * n'a pas inventorié.**
	 */
	const VIDEO_MIMES = array(
		'mp4|m4v' => 'video/mp4',
		'webm'    => 'video/webm',
		'ogv|ogg' => 'video/ogg',
	);

	/**
	 * Poids maximal accepté — arbitrage owner (Phase 47.6, décision D6).
	 *
	 * Une VALEUR DE TEMPLATE : elle vaut pour tous les clones. Le plancher réel
	 * d'un clone donné, lui, dépend de son hébergeur — cf. `max_bytes()`.
	 */
	const MAX_BYTES = 5 * MB_IN_BYTES;

	/**
	 * Liste blanche correspondant à une nature de champ.
	 *
	 * @param string $kind 'image' (défaut) ou 'video'.
	 * @return array Liste `extension => type MIME`.
	 */
	public static function mimes( $kind = 'image' ) {
		return ( 'video' === $kind ) ? self::VIDEO_MIMES : self::IMAGE_MIMES;
	}

	/**
	 * La limite RÉELLEMENT applicable sur CE clone.
	 *
	 * 🚨 POURQUOI CE N'EST PAS `MAX_BYTES`. Mesuré sur la pile de dev :
	 * `upload_max_filesize` vaut **2M**. Annoncer « 5 Mo » à l'écran y serait un
	 * mensonge — et le mensonge est la panne la plus coûteuse de ce dépôt, parce
	 * qu'il ne se voit pas. C'est banal chez les hébergeurs mutualisés, et ça
	 * change d'un clone à l'autre : un template doit dire la vérité de SON clone,
	 * pas la valeur qu'il aurait aimé avoir.
	 *
	 * `wp_max_upload_size()` = min( `upload_max_filesize`, `post_max_size` ), et
	 * elle est filtrable (`upload_size_limit`).
	 *
	 * @return int Octets.
	 */
	public static function max_bytes() {
		$php = (int) wp_max_upload_size();
		return ( $php > 0 && $php < self::MAX_BYTES ) ? $php : self::MAX_BYTES;
	}

	/**
	 * Message de refus — un seul, pour les DEUX façons d'être trop lourd.
	 *
	 * Il NOMME la limite : « trop lourd » sans chiffre oblige le gérant à deviner
	 * combien retirer, donc à réessayer au hasard.
	 *
	 * @return WP_Error
	 */
	protected static function too_big() {
		return new WP_Error(
			'aod_cd_upload_too_big',
			sprintf(
				/* translators: %s : poids maximal accepté, déjà formaté (ex. « 2 Mo »). */
				__( 'Fichier trop lourd : %s au maximum.', 'aod-client-dashboard' ),
				size_format( self::max_bytes() )
			)
		);
	}

	/**
	 * L'aide à afficher SOUS une zone de dépôt — un seul corps pour les 7.
	 *
	 * 🚨 ANNONCER AVANT, PAS SEULEMENT REFUSER APRÈS. Un gérant qui découvre la
	 * limite dans un message d'erreur a déjà attendu le téléversement, et il ne
	 * sait toujours pas de combien réduire. Le chiffre est donc le MÊME que celui
	 * du refus, pris à la même source : aucun nombre n'est écrit deux fois dans
	 * le dépôt.
	 *
	 * Classe `.aod-cd-help` : le composant d'aide sous champ déjà en place
	 * (`dashboard.css`), jamais une classe neuve.
	 *
	 * @return string HTML sûr (déjà échappé).
	 */
	public static function limit_note() {
		return '<span class="aod-cd-help aod-cd-uplimit">' . esc_html(
			sprintf(
				/* translators: %s : poids maximal accepté, déjà formaté (ex. « 2 Mo »). */
				__( 'Poids maximal : %s par fichier.', 'aod-client-dashboard' ),
				size_format( self::max_bytes() )
			)
		) . '</span>';
	}

	/**
	 * Téléverse le fichier posté sous `$field`, restreint à `$kind`.
	 *
	 * Ne charge PAS les `require` de wp-admin : les appelants les font déjà, au
	 * plus près de leur propre garde. Les ajouter ici les rendrait redondants sans
	 * les rendre plus sûrs.
	 *
	 * @param string $field  Clé dans `$_FILES`.
	 * @param int    $parent Article parent de l'attachement (0 = aucun).
	 * @param string $kind   'image' ou 'video'.
	 * @return int|WP_Error ID d'attachement, ou l'erreur de WordPress.
	 */
	public static function handle( $field, $parent = 0, $kind = 'image' ) {
		$file = isset( $_FILES[ $field ] ) && is_array( $_FILES[ $field ] ) ? $_FILES[ $field ] : array(); // phpcs:ignore WordPress.Security.NonceVerification -- les six appelants vérifient le nonce avant d'arriver ici.

		// 🚨 DEUX FAÇONS D'ÊTRE TROP LOURD, ET LA SECONDE EST LA PLUS FRÉQUENTE.
		//
		// (1) Le fichier arrive entier et dépasse NOTRE borne : `size` le dit.
		// (2) PHP l'a déjà coupé parce qu'il dépassait `upload_max_filesize` —
		//     et dans ce cas `size` vaut **0**, `tmp_name` est vide. Un contrôle
		//     écrit sur la seule taille serait donc VERT au test et INERTE en
		//     production partout où l'hébergeur plafonne sous notre borne : c'est
		//     le cas de la pile de dev (2 Mo) et de beaucoup de mutualisés.
		//     Sans ce second cas, le gérant n'obtiendrait que le message générique
		//     de WordPress, qui ne dit pas quoi faire.
		$erreur = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_OK;
		if ( UPLOAD_ERR_INI_SIZE === $erreur || UPLOAD_ERR_FORM_SIZE === $erreur ) {
			return self::too_big();
		}
		// `size` est TOUJOURS scalaire ici : les deux appelants « multiples »
		// (galerie, photos d'options) reconstruisent un $_FILES scalaire avant
		// d'appeler — vérifié en 47.6, pas supposé.
		if ( isset( $file['size'] ) && ! is_array( $file['size'] ) && (int) $file['size'] > self::max_bytes() ) {
			return self::too_big();
		}

		return media_handle_upload(
			$field,
			(int) $parent,
			array(),
			array( 'test_form' => false, 'mimes' => self::mimes( $kind ) )
		);
	}
}
