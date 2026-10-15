/* AOD Client Dashboard — section « Stock » (extraction 33.2).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var netErr = core.netErr;

	/* Stock : ajustement rapide quantité / seuil / note (étape 3.2) */
	function bindStock() {
		var rows = document.querySelectorAll( 'tr[data-stock-row]' );
		if ( ! rows.length ) { return; }
		rows.forEach( function ( tr ) {
			var btn = tr.querySelector( '.aod-cd-stock-save' );
			if ( ! btn ) { return; } // Ligne en lecture seule.
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				var qty  = tr.querySelector( '.aod-cd-stock-qty' );
				var low  = tr.querySelector( '.aod-cd-stock-low' );
				var note = tr.querySelector( '.aod-cd-stock-note' );
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_save_stock' );
				body.append( 'nonce', CD.nonce );
				body.append( 'product_id', tr.dataset.product || '' );
				body.append( 'stock', qty ? qty.value : '' );
				body.append( 'low', low ? low.value : '' );
				body.append( 'note', note ? note.value : '' );
				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						btn.disabled = false;
						if ( ! ( res && res.success ) ) {
							toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
							return;
						}
						var d = res.data;
						if ( qty ) { qty.value = d.stock; }
						// Badge recalculé côté serveur (wrapper dédié : les
						// champs de la cellule survivent).
						var wrap = tr.querySelector( '.aod-cd-stock-badgewrap' );
						if ( wrap ) { wrap.innerHTML = d.badge_html; }
						// Couverture recalculée depuis la vitesse de la ligne
						// (le tri, lui, n'est rejoué qu'au rechargement).
						var cover = tr.querySelector( '.aod-cd-stock-cover' );
						var speed = parseFloat( tr.dataset.speed || '0' );
						if ( cover ) {
							var stock = parseInt( d.stock, 10 );
							cover.textContent = stock <= 0 ? '0.0' : ( speed > 0 ? ( stock / speed ).toFixed( 1 ) : '\u221E' );
						}
						// Sous-ligne note (vidée ou remplacée par la version datée).
						var line = tr.querySelector( '.aod-cd-stock-noteline' );
						if ( line ) {
							if ( d.note ) {
								line.textContent = '\uD83D\uDDD2 ' + d.note;
								line.hidden = false;
							} else {
								line.textContent = '';
								line.hidden = true;
							}
						}
						if ( note ) { note.value = ''; }
						toast( d.message || CD.i18nSaved || 'Enregistré', false );
					} )
					.catch( function () {
						btn.disabled = false;
						toast( netErr, true );
					} );
			} );
		} );
	}

	core.register( 'bindStock', bindStock );
}( window.aodCdCore ) );
