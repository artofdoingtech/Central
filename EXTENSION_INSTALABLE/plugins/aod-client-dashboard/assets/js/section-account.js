/* AOD Client Dashboard — section « Mon compte » (extraction 33.2).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var netErr = core.netErr;
	var confirmModal = core.confirmModal;

	/* Zone de danger : réinitialisation complète de la boutique */
	function bindResetShop() {
		var form = document.getElementById( 'aod-cd-reset-form' );
		if ( ! form ) { return; }
		var input  = form.querySelector( '.aod-cd-reset-input' );
		var btn    = form.querySelector( 'button[type="submit"]' );
		var msg    = form.querySelector( '.aod-cd-form-msg' );
		var phrase = form.dataset.phrase || '';
		if ( ! input || ! btn ) { return; }

		// Le bouton ne s'active que si la phrase est recopiée à l'identique.
		function sync() {
			btn.disabled = ( input.value.trim() !== phrase );
		}
		input.addEventListener( 'input', sync );
		sync();

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( input.value.trim() !== phrase ) { return; }
			// Double garde-fou : une question AVANT la purge irréversible. Depuis la
			// 47.7 c'est la fenêtre maison — le dialogue natif empruntait ses boutons
			// au navigateur, donc « OK / Cancel » en anglais devant une phrase de
			// confirmation en arabe, sur le geste le plus destructif de /gestion.
			confirmModal( {
				message: CD.i18nResetConfirm || 'Tout supprimer ?',
				danger: true
			} ).then( function ( ok ) {
			if ( ! ok ) { return; }

			btn.disabled = true;
			input.disabled = true;
			if ( msg ) { msg.textContent = CD.i18nResetting || 'Réinitialisation…'; msg.classList.remove( 'is-bad' ); }

			var body = new URLSearchParams();
			body.append( 'action', 'aod_cd_reset_shop' );
			body.append( 'nonce', CD.nonce );
			body.append( 'phrase', input.value.trim() );

			fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) {
						if ( msg ) { msg.textContent = res.data.message || CD.i18nResetDone || 'OK'; }
						toast( res.data.message || CD.i18nResetDone || 'OK', false );
						// Recharge pour repartir d'une boutique vide (stats, compteurs…).
						setTimeout( function () { window.location.href = CD.base + 'account'; }, 1800 );
					} else {
						input.disabled = false;
						sync();
						var m = ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.';
						if ( msg ) { msg.textContent = m; msg.classList.add( 'is-bad' ); }
						toast( m, true );
					}
				} )
				.catch( function () {
					input.disabled = false;
					sync();
					if ( msg ) { msg.textContent = netErr; msg.classList.add( 'is-bad' ); }
					toast( netErr, true );
				} );
			} ); // fin de la continuation confirmModal (47.7)
		} );
	}

	core.register( 'bindResetShop', bindResetShop );
}( window.aodCdCore ) );
