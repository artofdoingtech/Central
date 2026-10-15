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

	// Chaque régie ouvre sa PETITE FENÊTRE (2026-08-10).
	//
	// 🔒 `<dialog>` NATIF, ET PLACÉ DANS LE FORMULAIRE. C'est le point qui décide
	// de tout : les champs ne quittent jamais leur `<form>`, donc « Enregistrer
	// les pixels » continue de les envoyer. Une modale construite dans
	// `document.body` — comme celle de l'édition rapide — aurait sorti les champs
	// du formulaire, et `new FormData( form )` ne les aurait plus vus. Le réglage
	// se serait perdu en silence, exactement comme la soumission de WhatsApp la
	// veille.
	//
	// Le navigateur fournit le reste : fond, plan supérieur (donc aucun souci de
	// `overflow` ou de `transform` d'un ancêtre), piège de focus et Échap. Rien de
	// tout cela n'est réécrit ici.
	//
	// ⓘ Le bouton « Tester la connexion » vit DANS le dialogue, mais le dialogue
	// vit dans la carte : `btn.closest( '.aod-cd-pxcard' )` de bindCapiTest()
	// trouve donc toujours la bonne carte, et le verdict ne peut pas s'afficher
	// sur la régie du voisin.
	function bindPixelDialogs() {
		document.querySelectorAll( '.aod-cd-pxcard' ).forEach( function ( card ) {
			var head = card.querySelector( ':scope > .aod-cd-pxcard__head' );
			var dlg  = card.querySelector( ':scope > .aod-cd-pxdlg' );
			if ( ! head || ! dlg || ! dlg.showModal ) { return; }

			head.addEventListener( 'click', function () {
				dlg.showModal();
				// Le premier champ prend la main : on ouvre cette fenêtre POUR
				// saisir, pas pour lire.
				var premier = dlg.querySelector( 'input:not([type=checkbox]), select, textarea' );
				if ( premier ) { premier.focus(); }
			} );

			dlg.querySelectorAll( '.aod-cd-pxdlg-close' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () { dlg.close(); } );
			} );

			// Clic sur le FOND. Un `<dialog>` occupe toute la fenêtre : le fond est
			// le dialogue lui-même, hors de son panneau. On compare donc le point
			// cliqué à la boîte du panneau plutôt que de se fier à la cible, qui
			// serait le dialogue dans les deux cas.
			dlg.addEventListener( 'click', function ( e ) {
				var b = dlg.querySelector( '.aod-cd-pxcard__body' );
				if ( ! b ) { return; }
				var r = b.getBoundingClientRect();
				var dedans = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
				if ( ! dedans ) { dlg.close(); }
			} );
		} );
	}

	core.register( 'bindPixelDialogs', bindPixelDialogs );
	core.register( 'bindCapiTest', bindCapiTest );
	core.register( 'bindPixelCards', bindPixelCards );
}( window.aodCdCore ) );
