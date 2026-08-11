<?php
/**
 * Emblèmes des transporteurs — catalogue FERMÉ.
 *
 * POURQUOI CE FICHIER EXISTE. Le tableau « Connexion aux transporteurs » aligne
 * 29 lignes. Jusqu'ici chacune portait le même carré plat de 38 px avec deux ou
 * trois lettres dedans : à l'œil, 29 fois la même chose, et on ne retrouvait le
 * sien qu'en lisant. Un emblème se reconnaît avant d'être lu — c'est tout
 * l'intérêt d'un logo dans une liste.
 *
 * 🚨 CE NE SONT PAS LES LOGOS OFFICIELS DES TRANSPORTEURS, ET C'EST VOULU.
 * Les vraies marques (Yalidine, Maystro, UPS…) sont des œuvres déposées : un
 * template redistribué à chaque clone ne peut pas les embarquer, et rien ici ne
 * permet d'aller les chercher (pas d'internet sortant, cf. TEMPLATE.md §3). Ce
 * sont donc des emblèmes MAISON, dessinés ici, qui ne cherchent pas à imiter :
 * ils s'appuient sur la seule identité qu'on possède légitimement — la couleur
 * de marque, déjà déclarée dans `AOD_Shipping::ecotrack_whitelabels()`, et le
 * SENS DU NOM. « Negmar » est l'étoile (nedjma), « Allo Livraison » est l'appel,
 * « 48HR » est l'heure, « Rocket » la fusée, « Conexlog (UPS) » l'écusson.
 *
 * ⚠️ QUAND LE NOM NE DIT RIEN, IL N'Y A PAS D'EMBLÈME — la pastille garde son
 * monogramme. C'est une règle, pas un reste à faire : inventer un pictogramme
 * pour « TSL Express » ou « RB Livraison » reviendrait à leur attribuer une
 * identité visuelle qu'ils n'ont pas, et le gérant qui connaît la vraie la
 * trouverait fausse. Un monogramme n'affirme rien. Sur les 29 transporteurs,
 * 14 portent un emblème et 15 un monogramme, et le mélange n'est pas une demi-
 * mesure : c'est à quoi ressemble n'importe quelle liste de transporteurs réelle.
 *
 * 🔒 CATALOGUE FERMÉ, comme `AOD_CD_Icon::catalog()` et pour la même raison : un
 * nom d'emblème inconnu ne casse rien de visible — la pastille retombe sur le
 * monogramme et personne ne remarque que le dessin a disparu. C'est
 * `tests/unit/test-carrier-emblemes.php` qui le voit.
 *
 * ⚠️ NE PAS RÉUTILISER LES ICÔNES DE `/gestion` ICI. `AOD_CD_Icon` appartient à
 * l'autre plugin (dépendance interdite dans ce sens), et surtout ce sont des
 * icônes d'INTERFACE : coiffer un transporteur du glyphe qui sert déjà de
 * pastille à la section « Livraison » ferait dire au même dessin deux choses
 * différentes sur le même écran.
 *
 * Dessin : boîte 24 × 24, `currentColor` UNIQUEMENT (l'encre est calculée par
 * `AOD_Carrier::brand_ink()` d'après la couleur de marque — une couleur en dur
 * ici rendrait l'emblème illisible sur l'ambre des white-labels EcoTrack).
 * Les emblèmes ne sont JAMAIS miroités en RTL : une marque ne se retourne pas.
 *
 * @package AOD_COD_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AOD_Carrier_Emblem {

	/**
	 * Le catalogue : nom → contenu SVG (boîte 24 × 24).
	 *
	 * @return array<string,string>
	 */
	public static function catalog() {
		return array(

			// « Allo Livraison » — l'appel. Trois ondes montantes sur un point.
			'call' => '<circle cx="12" cy="17.6" r="2.1"/>'
				. '<path d="M7.9 13.7a5.8 5.8 0 0 1 8.2 0" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/>'
				. '<path d="M4.6 10.4a10.5 10.5 0 0 1 14.8 0" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/>',

			// « Rex » — rex, le roi. La couronne : trois pointes et son bandeau.
			// ⚠️ Le premier tracé était une couronne à cinq sommets, tirée d'un bord
			// à l'autre de la boîte. Agrandie, elle s'écrasait — 21,9 de large pour
			// 14,7 de haut — et se lisait comme une barre. Trois pointes tiennent
			// mieux la hauteur, et le bandeau reste détaché du corps.
			'crown' => '<path d="M4.8 16.4 3.5 8.6 8 11.6 12 5.8l4 5.8 4.5-3 -1.3 7.8Z"/>'
				. '<path d="M4.5 17.9h15a1 1 0 0 1 0 2h-15a1 1 0 0 1 0-2Z"/>',

			// « Conexlog (UPS) » — l'écusson, la forme même de cette marque-là.
			'shield' => '<path d="M12 2.6 20 5.5v5.6c0 4.6-3.2 8.2-8 9.7-4.8-1.5-8-5.1-8-9.7V5.5Z"/>',

			// « Coyote Express » — l'animal. L'empreinte.
			// Doigts élargis après contrôle à la loupe : à rx 1,9 ils se lisaient
			// comme quatre points posés sur une tache, pas comme une patte.
			'paw' => '<ellipse cx="6.4" cy="10" rx="2.15" ry="2.7"/>'
				. '<ellipse cx="10.5" cy="7.4" rx="2.2" ry="3"/>'
				. '<ellipse cx="14.8" cy="7.6" rx="2.2" ry="2.95"/>'
				. '<ellipse cx="18.3" cy="10.4" rx="2.1" ry="2.6"/>'
				. '<path d="M12.3 12.6c3 0 5.6 2.4 5.6 4.8 0 1.8-1.5 2.9-3.2 2.9-1.1 0-1.7-.4-2.4-.4s-1.3.4-2.4.4c-1.7 0-3.2-1.1-3.2-2.9 0-2.4 2.6-4.8 5.6-4.8Z"/>',

			// « Distazero » — la distance zéro. La cible.
			'target' => '<circle cx="12" cy="12" r="8.2" fill="none" stroke="currentColor" stroke-width="2"/>'
				. '<circle cx="12" cy="12" r="4.3" fill="none" stroke="currentColor" stroke-width="2"/>'
				. '<circle cx="12" cy="12" r="1.7"/>',

			// « 48HR Livraison » — le délai annoncé. L'horloge.
			'clock' => '<circle cx="12" cy="12" r="8.4" fill="none" stroke="currentColor" stroke-width="2"/>'
				. '<path d="M12 6.9v5.4l3.6 2.1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',

			// « Fret Direct » — le fret. Le porteur, cabine détachée de la caisse.
			'truck' => '<path d="M3.4 16.4V7.3a.9.9 0 0 1 .9-.9h7.4v10h-.9M15.7 16.4h-4V10.2h3.1a1 1 0 0 1 .78.37l2.6 3.2a1 1 0 0 1 .22.63v2h-1.4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>'
				. '<circle cx="8.3" cy="17.4" r="1.95" fill="none" stroke="currentColor" stroke-width="1.9"/>'
				. '<circle cx="17.1" cy="17.4" r="1.95" fill="none" stroke="currentColor" stroke-width="1.9"/>',

			// « MonoHub » — le hub. Un centre, quatre antennes.
			// Le centre domine les antennes : à rayons égaux, l'ensemble se lisait
			// comme un flocon plutôt que comme un moyeu et ses satellites.
			'hub' => '<circle cx="12" cy="12" r="3.4"/>'
				. '<circle cx="12" cy="4.2" r="1.95"/><circle cx="19.8" cy="12" r="1.95"/>'
				. '<circle cx="12" cy="19.8" r="1.95"/><circle cx="4.2" cy="12" r="1.95"/>'
				. '<path d="M12 6.3v2.2M12 15.5v2.2M6.3 12h2.2M15.5 12h2.2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',

			// « Negmar Express » — nedjma, l'étoile.
			'star' => '<path d="M12 2.9 14.86 8.7l6.4.93-4.63 4.51 1.09 6.37L12 17.5l-5.72 3.01 1.09-6.37L2.74 9.63l6.4-.93Z"/>',

			// « Packers » — l'emballage. Le colis, arêtes visibles.
			'box' => '<path d="M12 2.6 21.2 7.3v9.4L12 21.4 2.8 16.7V7.3Z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>'
				. '<path d="M2.8 7.3 12 12l9.2-4.7M12 12v9.4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>'
				. '<path d="m7.4 4.95 9.2 4.7" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>',

			// « Rocket Delivery » — la fusée. Le hublot est un TROU (`evenodd`) :
			// un disque plein de la couleur de marque n'aurait pas suivi le dégradé.
			'rocket' => '<path fill-rule="evenodd" d="M12 2.2c3.2 2.5 5 6.1 5 9.9l-1.5 3.5H8.5L7 12.1c0-3.8 1.8-7.4 5-9.9Zm0 5a2.15 2.15 0 1 0 0 4.3 2.15 2.15 0 0 0 0-4.3Z"/>'
				. '<path d="M7.2 13.5 4.8 16a2 2 0 0 0-.6 1.4v2.2l3.3-1.7ZM16.8 13.5 19.2 16a2 2 0 0 1 .6 1.4v2.2l-3.3-1.7Z"/>'
				. '<path d="M12 17.1c1 1 1.6 2.1 1.6 3.3 0 .7-.7 1.2-1.6 1.2s-1.6-.5-1.6-1.2c0-1.2.6-2.3 1.6-3.3Z"/>',

			// « Speed Delivery » — la vitesse. Le sillage et la pointe.
			'speed' => '<path d="M3.6 8.4h9.3M2.4 12h13.5M5.1 15.6h6.9" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>'
				. '<path d="m16.4 6.8 5 5.2-5 5.2" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',

			// « Worldexpress » — le monde. Le globe méridien.
			'globe' => '<circle cx="12" cy="12" r="8.6" fill="none" stroke="currentColor" stroke-width="1.9"/>'
				. '<path d="M3.9 9.2h16.2M3.9 14.8h16.2" fill="none" stroke="currentColor" stroke-width="1.9"/>'
				. '<ellipse cx="12" cy="12" rx="4" ry="8.6" fill="none" stroke="currentColor" stroke-width="1.9"/>',

			// « E-com Delivery » — le commerce en ligne. Le sac, pas le caddie :
			// le caddie est déjà l'icône des commandes abandonnées dans /gestion.
			'bag' => '<path d="M5.3 7.7h13.4l1.2 12a1 1 0 0 1-1 1.1H5.1a1 1 0 0 1-1-1.1Z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>'
				. '<path d="M8.6 10.3V7.3a3.4 3.4 0 0 1 6.8 0v3" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>',

			// « EcoTrack » — le suivi. La trace pointillée et son point d'arrivée.
			'route' => '<path fill-rule="evenodd" d="M16.6 2.6a5.3 5.3 0 0 1 5.3 5.3c0 3.8-5.3 9-5.3 9s-5.3-5.2-5.3-9a5.3 5.3 0 0 1 5.3-5.3Zm0 3.3a2.05 2.05 0 1 0 0 4.1 2.05 2.05 0 0 0 0-4.1Z"/>'
				. '<path d="M3 19.9c4.3 0 3.3-5.3 7.6-5.3" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-dasharray="0.1 3.7"/>',
		);
	}

	/**
	 * Rend l'emblème d'un nom, ou '' si le nom n'est pas au catalogue.
	 *
	 * Le '' n'est pas un échec : c'est la valeur que renvoie tout transporteur
	 * dont le nom ne porte pas d'image (cf. l'en-tête de ce fichier). L'appelant
	 * — `AOD_Carrier::icon_html()` — retombe alors sur le monogramme.
	 *
	 * @param string $name Nom au catalogue.
	 * @return string Markup SVG sûr (construit ici, jamais reçu de l'extérieur), ou ''.
	 */
	public static function svg( $name ) {
		$cat = self::catalog();
		if ( '' === (string) $name || ! isset( $cat[ $name ] ) ) {
			return '';
		}
		// `aria-hidden` sur la pastille entière est posé par `icon_html()` : le nom
		// du transporteur est le texte juste à côté, le répéter serait du bruit.
		return '<svg viewBox="0 0 24 24" fill="currentColor" focusable="false">' . $cat[ $name ] . '</svg>';
	}
}
