/* AOD Client Dashboard — section « Pixels & Tracking » (extraction 33.2).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var netErr = core.netErr;

	/* Boutons « Tester la connexion » CAPI (Meta / TikTok / Snapchat, Phase 13.3).
	   Chaque bouton porte data-platform ; son verdict s'affiche dans le
	   .aod-cd-capi-test-msg de SA carte (plus de sélecteur global). */
	function bindCapiTest() {
		var btns = document.querySelectorAll( '.aod-cd-capi-test' );
		if ( ! btns.length ) { return; }
		btns.forEach( function ( btn ) {
			var platform = btn.getAttribute( 'data-platform' ) || 'meta';
			var card     = btn.closest( '.aod-cd-pxcard' );
			var msg      = ( card || document ).querySelector( '.aod-cd-capi-test-msg' );
			var say = function ( text, cls ) {
				if ( ! msg ) { return; }
				msg.textContent = text;
				msg.className   = 'aod-cd-capi-test-msg' + ( cls ? ' ' + cls : '' );
			};
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				say( CD.i18nTesting || '…', 'is-pending' );
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_test_capi' );
				body.append( 'nonce', CD.nonce );
				body.append( 'platform', platform );
				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						btn.disabled = false;
						var success = !! ( res && res.success );
						var m       = ( res && res.data && res.data.message ) || '';
						say( m, success ? 'is-ok' : 'is-bad' );
						toast( m || ( success ? 'OK' : ( CD.i18nErr || 'Erreur.' ) ), ! success );
					} )
					.catch( function () {
						btn.disabled = false;
						say( netErr, 'is-bad' );
						toast( netErr, true );
					} );
			} );
		} );
	}

	/* Pixels : l'indicateur actif/inactif d'une carte suit la saisie, sans recharger. */
	function bindPixelCards() {
		document.querySelectorAll( '.aod-cd-pxcard' ).forEach( function ( card ) {
			var id  = card.querySelector( '.aod-cd-px-id' );
			var on  = card.querySelector( '.aod-cd-px-on' );
			var off = card.querySelector( '.aod-cd-px-off' );
			if ( ! id || ! on || ! off ) { return; }
			id.addEventListener( 'input', function () {
				var active = '' !== id.value.trim();
				card.classList.toggle( 'is-on', active );
				on.hidden  = ! active;
				off.hidden = active;
			} );
		} );
	}

	core.register( 'bindCapiTest', bindCapiTest );
	core.register( 'bindPixelCards', bindPixelCards );
}( window.aodCdCore ) );
