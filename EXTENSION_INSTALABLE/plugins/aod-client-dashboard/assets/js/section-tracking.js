/* AOD Client Dashboard — section « Suivi commande » (extraction 33.2).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var skeleton = core.skeleton;
	var netErr = core.netErr;

	/* Suivi commande : timeline (modale) + actualisation (ligne / global) */
	function bindTracking() {
		var wrap = document.querySelector( '.aod-cd-tracking' );
		if ( ! wrap ) { return; }

		// 47.4 — la modale `#aod-cd-modal` a UN SEUL propriétaire, dans la coque
		// (`core.sharedModal`). Ce module la partage avec le détail de commande :
		// sur CETTE page, les deux s'ouvrent dans le même élément. Ici le fond
		// FERME — comportement inchangé, et il est juste : la timeline n'est qu'un
		// afficheur, rien ne s'y perd.
		var partagee = core.sharedModal();
		var modal    = partagee ? partagee.modal : null;
		var titleEl  = partagee ? partagee.titleEl : null;
		var bodyEl   = partagee ? partagee.bodyEl : null;

		function openModal() {
			if ( partagee ) { partagee.open( { backdropCloses: true } ); }
		}
		function closeModal() {
			if ( partagee ) { partagee.close(); }
		}

		function post( params ) {
			var body = new URLSearchParams();
			body.append( 'nonce', CD.nonce );
			Object.keys( params ).forEach( function ( k ) { body.append( k, params[ k ] ); } );
			return fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } );
		}

		// Voir le suivi : timeline dans la modale.
		wrap.querySelectorAll( '.aod-cd-track-detail' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! modal ) { return; }
				if ( titleEl ) { titleEl.textContent = CD.i18nTrackLoading || '…'; }
				if ( bodyEl ) { bodyEl.innerHTML = skeleton( 5 ); }
				openModal();
				post( { action: 'aod_cd_tracking_detail', order_id: btn.dataset.order } )
					.then( function ( res ) {
						if ( res && res.success ) {
							if ( titleEl ) { titleEl.textContent = res.data.title || ''; }
							if ( bodyEl ) { bodyEl.innerHTML = res.data.html || ''; }
						} else {
							closeModal();
							toast( ( res && res.data && res.data.message ) || netErr, true );
						}
					} )
					.catch( function () { closeModal(); toast( netErr, true ); } );
			} );
		} );

		// Actualiser une commande : met à jour la pastille + la date, en place.
		wrap.querySelectorAll( '.aod-cd-track-refresh' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var row  = btn.closest( 'tr' );
				var prev = btn.textContent;
				btn.disabled = true; btn.textContent = '…';
				post( { action: 'aod_cd_tracking_refresh', order_id: btn.dataset.order } )
					.then( function ( res ) {
						btn.disabled = false; btn.textContent = prev;
						if ( res && res.success ) {
							if ( row && res.data.pill ) {
								var pc = row.querySelector( '.aod-cd-track-pillcell' );
								if ( pc ) { pc.innerHTML = res.data.pill; }
							}
							if ( row && res.data.at ) {
								var ac = row.querySelector( '.aod-cd-track-atcell' );
								if ( ac ) { ac.textContent = res.data.at; }
							}
							toast( res.data.message || CD.i18nSaved || 'OK', false );
						} else {
							toast( ( res && res.data && res.data.message ) || netErr, true );
						}
					} )
					.catch( function () { btn.disabled = false; btn.textContent = prev; toast( netErr, true ); } );
			} );
		} );

		// Actualiser tout : synchro PAR TRANCHES puis rechargement (32.4, D23).
		// Une seule requête synchrone mourait sur le timeout PHP/proxy dès que le
		// backlog grossissait ; le serveur ne traite plus que ~10 s par requête et
		// renvoie `partial` tant qu'il reste du travail ET que la tranche a progressé
		// — c'est LUI qui décide de conclure, jamais cette boucle.
		var allBtn = wrap.querySelector( '.aod-cd-track-refresh-all' );
		if ( allBtn ) {
			allBtn.addEventListener( 'click', function () {
				var prev  = allBtn.textContent;
				var state = { since: 0, done: 0, errs: 0, rounds: 0 };
				function done() {
					allBtn.disabled = false;
					allBtn.classList.remove( 'is-loading' );
					allBtn.textContent = prev;
				}
				function step() {
					// Garde-fou absolu : 60 tranches ≈ des milliers de colis. Si on
					// l'atteint, quelque chose ment — on s'arrête au lieu de tourner à vie.
					if ( ++state.rounds > 60 ) { done(); toast( netErr, true ); return; }
					post( { action: 'aod_cd_tracking_refresh', since: state.since, done: state.done, errs: state.errs } )
						.then( function ( res ) {
							if ( res && res.success && res.data.partial ) {
								state.since = res.data.since;
								state.done  = res.data.done;
								state.errs  = res.data.errs;
								// Progression chiffrée : un bouton figé sur « Actualisation… »
								// pendant une minute ressemble à un bouton mort.
								allBtn.textContent = ( CD.i18nTrackRefreshing || 'Actualisation…' ) + ' (' + state.done + ')';
								step();
								return;
							}
							if ( res && res.success ) {
								toast( res.data.message || '', false );
								if ( res.data.reload ) { setTimeout( function () { window.location.reload(); }, 700 ); return; }
							} else {
								toast( ( res && res.data && res.data.message ) || netErr, true );
							}
							done();
						} )
						.catch( function () { done(); toast( netErr, true ); } );
				}
				allBtn.disabled = true;
				allBtn.classList.add( 'is-loading' );
				allBtn.textContent = CD.i18nTrackRefreshing || 'Actualisation…';
				step();
			} );
		}
	}

	core.register( 'bindTracking', bindTracking );
}( window.aodCdCore ) );
