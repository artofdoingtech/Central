/**
 * Formulaire de contact — envoi AJAX.
 *
 * Sans jQuery : ce fichier ne sert que la page Contact, inutile d'y traîner une
 * dépendance de 90 ko sur une connexion mobile algérienne.
 */
( function () {
	'use strict';

	var form = document.querySelector( '.aod-contact__form' );
	if ( ! form || typeof AOD_CT === 'undefined' ) {
		return;
	}

	var msg    = form.querySelector( '.aod-cod__msg' );
	var submit = form.querySelector( '.aod-cod__submit' );
	var label  = submit ? submit.textContent : '';

	// Time-trap : on mesure la durée de remplissage côté client, donc insensible au
	// cache de page (un HTML mis en cache figerait un horodatage côté serveur).
	var openedAt = Date.now();

	function say( text, isError ) {
		if ( ! msg ) {
			return;
		}
		msg.textContent = text;
		msg.classList.toggle( 'is-error', !! isError );
		msg.classList.toggle( 'is-ok', ! isError );
	}

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();

		if ( submit ) {
			submit.disabled    = true;
			submit.textContent = AOD_CT.i18n.sending;
		}
		say( '', false );

		var data = new FormData();
		data.append( 'action', 'aod_contact_submit' );
		data.append( 'nonce', AOD_CT.nonce );
		data.append( 'elapsed', String( Date.now() - openedAt ) );

		[ 'name', 'email', 'phone', 'message', 'aod_url' ].forEach( function ( field ) {
			var el = form.querySelector( '[name="' + field + '"]' );
			data.append( field, el ? el.value : '' );
		} );

		// Turnstile injecte son jeton dans un input caché du formulaire.
		var ts = form.querySelector( '[name="cf-turnstile-response"]' );
		if ( ts ) {
			data.append( 'cf_turnstile', ts.value );
		}

		fetch( AOD_CT.ajax_url, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				if ( res && res.success ) {
					form.reset();
					// Turnstile est à usage unique : sans reset, un 2e envoi rejouerait un
					// jeton déjà consommé et serait rejeté.
					if ( window.turnstile ) {
						window.turnstile.reset();
					}
					openedAt = Date.now();
					say( res.data.message, false );
				} else {
					say( ( res && res.data && res.data.message ) || AOD_CT.i18n.error, true );
				}
			} )
			.catch( function () {
				say( AOD_CT.i18n.error, true );
			} )
			.finally( function () {
				if ( submit ) {
					submit.disabled    = false;
					submit.textContent = label;
				}
			} );
	} );
}() );
