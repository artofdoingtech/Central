/* AOD Client Dashboard — section « Équipe » (extraction 33.2).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var trapFocus = core.trapFocus;
	var netErr = core.netErr;
	var confirmModal = core.confirmModal;

	/* Équipe : CRUD des confirmateurs */
	function bindTeam() {
		var form = document.getElementById( 'aod-cd-team-form' );
		if ( ! form ) { return; }

		function post( params ) {
			var body = new URLSearchParams();
			body.append( 'nonce', CD.nonce );
			Object.keys( params ).forEach( function ( k ) { body.append( k, params[ k ] ); } );
			return fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } );
		}
		function done( res ) {
			if ( res && res.success ) {
				// 🚨 LE RECHARGEMENT ATTEND QUE L'AVIS SOIT LU — et c'est la 47.7 qui a
				// créé le besoin. `window.alert` BLOQUAIT le fil JS : le `setTimeout`
				// n'était même pas armé tant que le gérant n'avait pas cliqué OK. La
				// fenêtre maison, elle, ne bloque rien — le rechargement partait donc
				// 700 ms plus tard et emportait la modale avec la page. Or ce mot de
				// passe est « affiché une seule fois, jamais stocké en clair » : le
				// perdre, c'est un confirmateur qui ne peut plus se connecter tant que
				// le gérant ne lui en regénère pas un.
				// *Remplacer un dialogue bloquant par un dialogue qui ne l'est pas
				// change le SENS de tout ce qui le suit sur la même ligne d'exécution.*
				var recharger = function () {
					if ( res.data.reload ) { setTimeout( function () { window.location.reload(); }, 700 ); }
				};
				if ( res.data.generated_password ) {
					// L'UNIQUE avis du dépôt (47.7) : un AVIS, pas une question — d'où
					// `okOnly`, qui évite une seconde primitive pour une différence
					// d'un bouton.
					// ⚠️ `detail` et non `message` : le mot de passe n'est montré
					// QU'UNE FOIS, il doit rester SÉLECTIONNABLE pour être transmis.
					// `window.alert` le permettait — l'afficher dans un paragraphe
					// ordinaire aurait été une régression d'usage déguisée en
					// amélioration de charte.
					confirmModal( {
						title: CD.i18nTeamPassNotice || 'Mot de passe généré :',
						message: CD.i18nTeamPassNotice || 'Mot de passe généré :',
						detail: res.data.generated_password,
						okOnly: true
					} ).then( recharger );
					toast( res.data.message || CD.i18nSaved || 'Enregistré', false );
				} else {
					toast( res.data.message || CD.i18nSaved || 'Enregistré', false );
					recharger();
				}
			} else {
				toast( ( res && res.data && res.data.message ) || netErr, true );
			}
		}

		var title  = document.getElementById( 'aod-cd-team-form-title' );
		// Le bouton d'envoi vit dans le PIED de la modale, hors du <form> (il lui est
		// rattaché par son attribut `form`) : le chercher dans le formulaire renverrait
		// null, et le mode « Ajouter / Enregistrer » ne changerait plus jamais.
		var submit = document.getElementById( 'aod-cd-team-submit' );
		var cancel = document.getElementById( 'aod-cd-team-cancel' );
		var login  = form.querySelector( '[name="login"]' );
		var limitCb  = form.querySelector( '[name="products_limited"]' );
		var prodsBox = document.getElementById( 'aod-cd-team-products' );
		var modal    = document.getElementById( 'aod-cd-team-modal' );
		var addBtn   = document.getElementById( 'aod-cd-team-add' );
		var opener   = null; // Élément à re-focaliser à la fermeture.
		var release  = null; // Piège de focus actif (cf. trapFocus).

		// Le sélecteur de produits n'apparaît que si « Limiter à certains produits » est coché.
		function syncProducts() {
			if ( prodsBox ) { prodsBox.hidden = ! ( limitCb && limitCb.checked ); }
		}
		if ( limitCb ) { limitCb.addEventListener( 'change', syncProducts ); }

		function setMode( edit ) {
			if ( title )  { title.textContent  = edit ? title.dataset.edit : title.dataset.create; }
			if ( submit ) { submit.textContent = edit ? submit.dataset.edit : submit.dataset.create; }
			if ( login )  { login.disabled = !! edit; login.required = ! edit; }
		}
		function resetForm() {
			form.reset();
			form.querySelector( '[name="member_id"]' ).value = '0';
			var msg = modal ? modal.querySelector( '.aod-cd-form-msg' ) : null;
			if ( msg ) { msg.textContent = ''; }
			setMode( false );
			syncProducts();
		}

		function openModal( from ) {
			if ( ! modal ) { return; }
			opener = from || null;
			modal.hidden = false;
			// Le premier champ actif : à la création c'est l'identifiant, en
			// modification il est verrouillé → on vise le nom affiché.
			var first = form.querySelector( 'input:not([type=hidden]):not([disabled])' );
			// trapFocus garde le focus DANS la modale : `aria-modal` ne fait que le
			// promettre aux lecteurs d'écran, il n'empêche pas le Tab de sortir.
			release = trapFocus( modal.querySelector( '.aod-cd-modal-box' ) || modal, first );
		}
		function closeModal() {
			if ( ! modal ) { return; }
			modal.hidden = true;
			resetForm();
			// trapFocus rend le focus à l'élément qui avait ouvert la modale ; on ne
			// re-focalise `opener` que s'il n'a pas pu s'en charger (modale déjà
			// fermée, déclencheur retiré du DOM après un enregistrement…).
			if ( release ) {
				release();
				release = null;
			} else if ( opener && document.contains( opener ) ) {
				opener.focus();
			}
		}
		if ( modal ) {
			modal.querySelectorAll( '[data-team-close]' ).forEach( function ( el ) {
				el.addEventListener( 'click', closeModal );
			} );
			document.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key && ! modal.hidden ) { closeModal(); }
			} );
		}
		if ( addBtn ) {
			addBtn.addEventListener( 'click', function () {
				resetForm();
				openModal( addBtn );
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( submit ) { submit.disabled = true; }
			var data = new FormData( form );
			data.append( 'action', 'aod_cd_save_member' );
			data.append( 'nonce', CD.nonce );
			fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) { if ( submit ) { submit.disabled = false; } done( res ); } )
				.catch( function () { if ( submit ) { submit.disabled = false; } toast( netErr, true ); } );
		} );
		if ( cancel ) { cancel.addEventListener( 'click', closeModal ); }

		// La liste n'est plus un tableau mais des cartes : l'ancre des données est
		// `[data-member]`, plus `tr`. (Un `closest('tr')` oublié ici renverrait null
		// et le bouton ne ferait plus rien, sans la moindre erreur visible.)
		var card = function ( btn ) { return btn.closest( '[data-member]' ); };

		document.querySelectorAll( '.aod-cd-team-edit' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var row = card( btn );
				if ( ! row ) { return; }
				resetForm();
				form.querySelector( '[name="member_id"]' ).value = row.dataset.id;
				if ( login ) { login.value = row.dataset.login; }
				form.querySelector( '[name="display_name"]' ).value = row.dataset.display;
				var caps = ( row.dataset.caps || '' ).split( ',' );
				form.querySelectorAll( '[name="caps[]"]' ).forEach( function ( cb ) {
					cb.checked = -1 !== caps.indexOf( cb.value );
				} );
				form.querySelector( '[name="products_limited"]' ).checked = '1' === row.dataset.limited;
				var prods = ( row.dataset.products || '' ).split( ',' );
				form.querySelectorAll( '[name="assigned_products[]"]' ).forEach( function ( cb ) {
					cb.checked = -1 !== prods.indexOf( cb.value );
				} );
				syncProducts();
				setMode( true );
				openModal( btn );
			} );
		} );

		document.querySelectorAll( '.aod-cd-team-toggle' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				post( { action: 'aod_cd_save_member', member_id: card( btn ).dataset.id, toggle_active: '1' } )
					.then( function ( res ) { btn.disabled = false; done( res ); } )
					.catch( function () { btn.disabled = false; toast( netErr, true ); } );
			} );
		} );

		document.querySelectorAll( '.aod-cd-team-delete' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				confirmModal( {
					message: CD.i18nTeamDelConfirm || 'Supprimer ce confirmateur ?',
					danger: true
				} ).then( function ( ok ) {
					if ( ! ok ) { return; }
					btn.disabled = true;
					post( { action: 'aod_cd_delete_member', member_id: card( btn ).dataset.id } )
						.then( function ( res ) { btn.disabled = false; done( res ); } )
						.catch( function () { btn.disabled = false; toast( netErr, true ); } );
				} );
			} );
		} );
	}

	core.register( 'bindTeam', bindTeam );
}( window.aodCdCore ) );
