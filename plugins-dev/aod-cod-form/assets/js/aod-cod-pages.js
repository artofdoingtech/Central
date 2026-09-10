/**
 * Grilles produits (Boutique / Accueil / produits liés) — panier COD ÉTEINT
 * (comportement par défaut, cf. `AOD_COD_Form::cart_enabled()`).
 *
 * Demande owner (2026-09-10) : le bouton « Commander » ne doit pas s'afficher
 * sous le prix en desktop, mais SUR la carte produit, en survolant — comme le
 * fait déjà le panier COD activé (`aod-cart.js`, `initQuickAdd()`). Ce fichier
 * porte la MÊME idée, réduite à un seul bouton (pas de groupe « Ajouter au
 * panier » + « Commander ») puisque le panier est ici éteint.
 *
 * Enqueue conditionnel côté PHP : n'existe QUE si le panier COD est éteint —
 * quand il est actif, `.aod-loop-cta` est retiré du DOM affiché
 * (`display:none`, aod-cart.css) et ce script n'a plus rien à faire dessus.
 *
 * 🚨 ON NE PEUT PAS EMBOÎTER `<a class="aod-loop-cta">` DANS
 * `<a class="woocommerce-LoopProduct-link">` (le lien de l'image) : deux <a>
 * imbriqués sont invalides, et le lien extérieur se referme tôt selon les
 * navigateurs. `aod-cart.js` évite le problème en posant un `<span
 * role="button">`, pas un lien — nous, si : `.aod-loop-cta` reste un VRAI
 * `<a>` (même destination que l'image de toute façon, cf.
 * `AOD_COD_No_Tunnel::product_url()`). La parade : le bouton devient un
 * FRÈRE du lien-image, tous deux enfants du même conteneur — jamais un
 * enfant l'un de l'autre.
 */
( function () {
	'use strict';

	// Le survol n'existe qu'à la souris (mobile d'abord — la surimpression est
	// la variante desktop, même signal que `aod-cart.js`). Sur tactile, le
	// bouton reste où WooCommerce/Astra l'a posé : un bloc sous le prix,
	// toujours visible (aod-cod-pages.css, règle de base).
	if ( ! window.matchMedia || ! window.matchMedia( '(hover: hover)' ).matches ) {
		return;
	}

	var cards = document.querySelectorAll( 'li.product' );
	Array.prototype.forEach.call( cards, function ( card ) {
		if ( card.querySelector( '.aod-loop-qwrap' ) ) {
			return; // Déjà fait (garde-fou, au cas où ce script tournerait deux fois).
		}
		var btn = card.querySelector( '.astra-shop-summary-wrap > a.aod-loop-cta' );
		var thumb = card.querySelector( '.astra-shop-thumbnail-wrap' );
		var link = thumb ? thumb.querySelector( ':scope > a' ) : null;
		if ( ! btn || ! thumb || ! link ) {
			return; // Carte sans le markup attendu : on la laisse telle quelle.
		}
		var wrap = document.createElement( 'span' );
		wrap.className = 'aod-loop-qwrap';
		thumb.insertBefore( wrap, link );
		wrap.appendChild( link ); // Le lien-image d'abord…
		wrap.appendChild( btn ); // … puis notre bouton, FRÈRE du lien, jamais dedans.
	} );
}() );
