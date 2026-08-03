/**
 * Page de vente — le seul JavaScript de la landing (étapes 6.3 / 6.4).
 *
 * Trois usages, et trois seulement : la façade vidéo, le compte à rebours qui avance,
 * et la barre collante qui s'efface quand le formulaire est à l'écran. Aucun des trois
 * n'est indispensable : sans JS, le lien YouTube reste donné en clair, le rebours reste
 * figé sur des chiffres justes, et la barre reste visible. C'est ce qui permet de ne PAS
 * charger ce fichier quand la landing n'a aucun des trois (cf. `needs_js()` côté PHP) —
 * une page de vente ordinaire est alors 100 % HTML/CSS.
 *
 * Aucune chaîne à traduire ici : les unités du rebours (j, h, min, s) sont rendues par
 * PHP, et le script ne fait que réécrire des CHIFFRES.
 *
 * @package AOD_COD_Form
 */
( function () {
	'use strict';

	/* ─────────────────────────────────────────────────────── Façade vidéo ─── */

	var VALID = /^[A-Za-z0-9_-]{11}$/;

	function play( facade ) {
		var id = facade.getAttribute( 'data-yt' ) || '';
		if ( ! VALID.test( id ) ) {
			return;
		}

		var frame = document.createElement( 'iframe' );
		frame.className = 'aod-landing__video-frame';
		// L'URL est RECONSTRUITE à partir du seul identifiant (déjà validé côté PHP :
		// 11 caractères du jeu YouTube). Rien de ce qu'a saisi le gérant n'est concaténé
		// tel quel dans un `src`.
		frame.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
		frame.title = facade.getAttribute( 'data-title' ) || 'YouTube';
		frame.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen';
		frame.setAttribute( 'allowfullscreen', '' );
		frame.setAttribute( 'loading', 'lazy' );

		var wrap = facade.parentNode;
		wrap.replaceChild( frame, facade );
		// Le clic a « ouvert » le lecteur : le focus doit l'y suivre, sinon il retombe
		// sur le corps de page et le visiteur au clavier perd le fil.
		frame.focus();
	}

	function bindVideo() {
		var facades = document.querySelectorAll( '.aod-landing__video-facade' );
		Array.prototype.forEach.call( facades, function ( facade ) {
			facade.addEventListener( 'click', function () {
				play( facade );
			} );
		} );
	}

	/* ────────────────────────────────────────────────── Compte à rebours ─── */

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	/**
	 * Fait avancer les rebours. Le serveur a déjà écrit les bons chiffres : on ne fait
	 * que les rafraîchir. À zéro, on efface la SECTION entière — une offre terminée ne
	 * doit pas laisser « 00 j 00 h » à l'écran, ce serait mentir par immobilité.
	 */
	function bindCountdowns() {
		var cds = document.querySelectorAll( '.aod-landing__countdown' );
		if ( ! cds.length ) {
			return;
		}

		function tick() {
			var alive = 0;
			Array.prototype.forEach.call( cds, function ( cd ) {
				var end  = parseInt( cd.getAttribute( 'data-end' ), 10 );
				var left = Math.max( 0, end - Math.floor( Date.now() / 1000 ) );

				if ( ! left ) {
					var section = cd.closest( '.aod-landing__block' );
					if ( section ) {
						section.hidden = true;
					}
					return;
				}
				alive++;

				var v = {
					d: Math.floor( left / 86400 ),
					h: Math.floor( ( left % 86400 ) / 3600 ),
					m: Math.floor( ( left % 3600 ) / 60 ),
					s: left % 60
				};
				Object.keys( v ).forEach( function ( k ) {
					var el = cd.querySelector( '[data-cd="' + k + '"]' );
					if ( el ) {
						el.textContent = pad( v[ k ] );
					}
				} );
			} );
			if ( ! alive ) {
				clearInterval( timer );
			}
		}

		var timer = setInterval( tick, 1000 );
		tick();
	}

	/* ──────────────────────────────────────────────────── Barre collante ─── */

	/**
	 * La barre « Commander » s'efface quand le formulaire est à l'écran : le visiteur y
	 * est déjà, un bouton qui l'y renvoie n'a plus de sens et mange de la place sur un
	 * petit écran.
	 *
	 * `visibility: hidden` (posé par la classe, en CSS) et non un simple décalage : le
	 * lien sort ainsi de l'ordre de tabulation. Une barre invisible qui garde le focus
	 * envoie l'utilisateur au clavier sur un bouton qu'il ne voit pas.
	 */
	function bindSticky() {
		var bar = document.querySelector( '.aod-landing__sticky' );
		if ( ! bar || ! window.IntersectionObserver ) {
			return; // Sans IntersectionObserver, la barre reste visible : dégradé, pas cassé.
		}
		var anchor = document.getElementById( bar.getAttribute( 'data-anchor' ) );
		if ( ! anchor ) {
			return;
		}

		new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( e ) {
				bar.classList.toggle( 'is-off', e.isIntersecting );
			} );
		}, { threshold: 0 } ).observe( anchor );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		bindVideo();
		bindCountdowns();
		bindSticky();
	} );
}() );
