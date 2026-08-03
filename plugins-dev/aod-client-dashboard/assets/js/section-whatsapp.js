/* AOD Client Dashboard — section « WhatsApp » (extraction 33.1, pilote).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var netErr = core.netErr;
	var toast = core.toast;

	/* Bouton « message test » WhatsApp */
	function bindWhatsappTest() {
		var btn = document.querySelector( '.aod-cd-wa-test' );
		if ( ! btn ) { return; }
		var msg = document.querySelector( '.aod-cd-wa-test-msg' );

		// Le résultat s'affiche À CÔTÉ du bouton, comme pour le test transporteur :
		// le toast disparaît au bout de quelques secondes, or le message dit souvent
		// ce qu'il reste à corriger (clé absente, numéro faux…).
		var say = function ( text, cls ) {
			if ( ! msg ) { return; }
			msg.textContent = text;
			msg.className   = 'aod-cd-wa-test-msg' + ( cls ? ' ' + cls : '' );
		};

		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			say( CD.i18nTesting || '…', 'is-pending' );
			var body = new URLSearchParams();
			body.append( 'action', 'aod_cd_test_whatsapp' );
			body.append( 'nonce', CD.nonce );
			fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					btn.disabled = false;
					var success = !! ( res && res.success );
					var m       = ( res && res.data && res.data.message ) || '';
					say( m, success ? 'is-ok' : 'is-bad' );
					toast( m || ( success ? ( CD.i18nSent || 'Envoyé' ) : ( CD.i18nErr || 'Erreur.' ) ), ! success );
				} )
				.catch( function () {
					btn.disabled = false;
					say( netErr, 'is-bad' );
					toast( netErr, true );
				} );
		} );
	}

	core.register( 'bindWhatsappTest', bindWhatsappTest );
}( window.aodCdCore ) );
