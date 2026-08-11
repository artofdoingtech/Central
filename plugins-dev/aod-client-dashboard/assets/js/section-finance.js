/**
 * Finance — le calculateur de bénéfices.
 *
 * Module de section : il s'enregistre auprès du noyau (`dashboard.js`) et ne
 * fait rien si la page n'est pas la sienne.
 */
( function ( core ) {
	'use strict';

	// LES MÊMES FORMULES QUE `AOD_CD_Section_Finance::compute()`, ET C'EST ASSUMÉ.
	// Un aller-retour serveur par frappe coûterait 2 à 3 secondes sur ce montage
	// (TEMPLATE.md §3) : l'outil serait mort. Ce qui n'est PAS laissé à la
	// confiance, c'est l'accord entre les deux — a l'enregistrement, le serveur
	// recalcule depuis les seules entrees et renvoie SES nombres ; `comparer()`
	// plus bas les confronte aux nôtres et le dit à l'écran si ça diverge.
	// Une logique écrite deux fois dérive ; ce qu'on choisit, c'est qu'elle le dise.
	function calculer( e ) {
		var n = function ( v ) {
			v = parseFloat( v );
			return ( isFinite( v ) && v > 0 ) ? v : 0;
		};
		var pv = n( e.prix_vente ), pa = n( e.prix_achat ), fl = n( e.f_livraison );
		var fc = n( e.f_confirm ), fe = n( e.f_emballage );
		var fp = n( e.f_pub ), fa = n( e.f_autres );
		var cmd = Math.floor( n( e.n_commandes ) ), liv = Math.floor( n( e.n_livrees ) );
		var ann = Math.floor( n( e.n_annulees ) ), ret = Math.floor( n( e.n_retours ) );

		var expediees = liv + ret;
		var ca = pv * liv;
		var cProduit = pa * liv;
		var cLivraison = fl * liv;
		var cRetours = fl * ret;
		var cEmballage = fe * expediees;
		var cConfirm = fc * cmd;
		var coutTotal = cProduit + cLivraison + cRetours + cEmballage + cConfirm + fp + fa;

		var div = function ( a, b ) { return b > 0 ? a / b : 0; };

		return {
			ca: ca,
			cout_total: coutTotal,
			cout_produit: cProduit,
			cout_pub: fp,
			cout_livraison: cLivraison,
			cout_retours: cRetours,
			cout_emballage: cEmballage,
			cout_confirm: cConfirm,
			cout_autres: fa,
			benefice_brut: ca - cProduit,
			benefice_net: ca - coutTotal,
			marge: div( ca - coutTotal, ca ) * 100,
			cout_moyen: div( coutTotal, cmd ),
			cpa: div( fp, liv ),
			roas: div( ca, fp ),
			taux_livraison: div( liv, cmd ) * 100,
			taux_annulation: div( ann, cmd ) * 100,
			taux_retour: div( ret, expediees ) * 100
		};
	}

	// Les sept postes de coût, dans l'ordre où ils sont empilés.
	var POSTES = [ 'cout_produit', 'cout_pub', 'cout_livraison', 'cout_confirm', 'cout_emballage', 'cout_retours', 'cout_autres' ];
	// Ce qui s'affiche en pourcentage, et ce qui s'affiche en multiple.
	var PCT = { marge: 1, taux_livraison: 1, taux_annulation: 1, taux_retour: 1 };
	var FOIS = { roas: 1 };

	var toast = core.toast;

	/**
	 * Dépenses : la suppression d'une ligne du registre.
	 *
	 * ⚠️ ON RECHARGE, ON NE RETIRE PAS LA LIGNE. Le total du mois, la pastille de
	 * variation et la barre « Où part l'argent » sont rendus par le SERVEUR :
	 * effacer la ligne à l'écran laisserait trois nombres qui la comptent encore.
	 * Un tableau juste sous un total faux se lit comme un total juste.
	 *
	 * L'ajout, lui, passe par le binder générique des formulaires de réglages —
	 * il n'y a rien à confronter, seulement à enregistrer.
	 */
	function bindDepenses() {
		var tbl = document.querySelector( '[data-depenses]' );
		if ( ! tbl ) { return; }

		tbl.addEventListener( 'click', function ( ev ) {
			var btn = ev.target.closest( '.aod-cd-findep__del' );
			if ( ! btn ) { return; }

			// ⚠️ `confirmModal` rend une PROMESSE : la suite DOIT être en
			// continuation. Un `if ( ! confirmModal(…) )` ne se déclencherait
			// jamais — donc une suppression sans confirmation.
			core.confirmModal( {
				message: btn.getAttribute( 'data-confirm' ) || '',
				danger: true
			} ).then( function ( ok ) {
				if ( ! ok ) { return; }
				var data = new FormData();
				data.append( 'action', 'aod_cd_finance_depense_del' );
				data.append( 'nonce', core.CD.nonce || '' );
				data.append( 'mois', tbl.getAttribute( 'data-mois' ) || '' );
				data.append( 'dep', btn.getAttribute( 'data-id' ) || '' );
				btn.disabled = true;

				fetch( core.CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( r ) {
						if ( r && r.success ) {
							if ( toast ) { toast( r.data.message || '' ); }
							setTimeout( function () { window.location.reload(); }, 700 );
							return;
						}
						btn.disabled = false;
						if ( toast ) { toast( ( r && r.data && r.data.message ) || core.netErr, true ); }
					} )
					.catch( function () {
						btn.disabled = false;
						if ( toast ) { toast( core.netErr, true ); }
					} );
			} );
		} );
	}

	function bindFinance() {
		var grille = document.querySelector( '.aod-cd-fincalc__grid' );
		if ( ! grille ) { return; }

		var form = grille.querySelector( '.aod-cd-fincalc__form' );
		var res = grille.querySelector( '.aod-cd-finres' );
		if ( ! form || ! res ) { return; }

		var devise = grille.getAttribute( 'data-devise' ) || '';
		var CD = core.CD;
		var champs = Array.prototype.slice.call( form.querySelectorAll( '.aod-cd-finfield__in' ) );
		var avert = form.querySelector( '.aod-cd-finwarn' );
		var msg = form.querySelector( '.aod-cd-form-msg' );

		function lire() {
			var e = {};
			champs.forEach( function ( i ) { e[ i.name ] = i.value; } );
			return e;
		}

		function montant( v ) {
			var s = Math.abs( v ) >= 1000
				? Math.round( v ).toLocaleString()
				: ( Math.round( v * 100 ) / 100 ).toLocaleString();
			return devise ? s + ' ' + devise : s;
		}

		function afficher( cle, v ) {
			if ( PCT[ cle ] ) { return ( Math.round( v * 10 ) / 10 ).toLocaleString() + ' %'; }
			if ( FOIS[ cle ] ) { return v > 0 ? '×' + ( Math.round( v * 100 ) / 100 ).toLocaleString() : '—'; }
			return montant( v );
		}

		// Un nombre qui change doit se VOIR changer : sans cela, on tape dans un
		// champ et rien ne bouge à l'écran de façon perceptible, alors que tout a
		// été recalculé. La classe est retirée à la fin de l'animation pour pouvoir
		// être reposée à la frappe suivante.
		function poser( el, texte ) {
			if ( el.textContent === texte ) { return; }
			el.textContent = texte;
			el.classList.remove( 'is-maj' );
			// Forcer un reflow : sans lui, retirer puis remettre la classe dans le
			// même cycle ne relance pas l'animation.
			void el.offsetWidth;
			el.classList.add( 'is-maj' );
		}

		function peindre( r ) {
			res.querySelectorAll( '[data-res]' ).forEach( function ( el ) {
				var cle = el.getAttribute( 'data-res' );
				poser( el, r.hasOwnProperty( cle ) ? afficher( cle, r[ cle ] ) : '—' );
			} );

			// La tuile « Bénéfice net » change de ton selon son signe : une perte
			// affichée dans la même couleur qu'un gain se lit comme un gain.
			var tuile = res.querySelector( '[data-tuile="benefice_net"]' );
			if ( tuile ) {
				tuile.classList.toggle( 'is-bad', r.benefice_net < 0 );
				tuile.classList.toggle( 'is-ok', r.benefice_net > 0 );
			}

			// Les jauges de taux.
			res.querySelectorAll( '[data-jauge]' ).forEach( function ( el ) {
				var v = r[ el.getAttribute( 'data-jauge' ) ] || 0;
				el.style.width = Math.max( 0, Math.min( 100, v ) ) + '%';
			} );

			peindreCouts( r );
		}

		function peindreCouts( r ) {
			var bar = res.querySelector( '[data-bar]' );
			var leg = res.querySelector( '[data-leg]' );
			if ( ! bar || ! leg ) { return; }

			var total = r.cout_total;
			if ( ! ( total > 0 ) ) {
				bar.innerHTML = '';
				leg.innerHTML = '';
				bar.classList.add( 'is-vide' );
				return;
			}
			bar.classList.remove( 'is-vide' );

			var segments = '';
			var lignes = '';
			POSTES.forEach( function ( cle, i ) {
				var v = r[ cle ] || 0;
				if ( v <= 0 ) { return; }
				var pct = ( v / total ) * 100;
				var nom = ( CD.finPostes && CD.finPostes[ cle ] ) || cle;
				segments += '<span class="aod-cd-fincost__seg" style="--part:' + i + ';width:' + pct.toFixed( 2 ) + '%" title="' +
					nom.replace( /"/g, '&quot;' ) + '"></span>';
				lignes += '<li class="aod-cd-fincost__li"><span class="aod-cd-fincost__dot" style="--part:' + i +
					'" aria-hidden="true"></span><span class="aod-cd-fincost__nom">' + nom +
					'</span><span class="aod-cd-fincost__val">' + montant( v ) + '</span><span class="aod-cd-fincost__pct">' +
					pct.toFixed( 1 ) + ' %</span></li>';
			} );
			bar.innerHTML = segments;
			leg.innerHTML = lignes;
		}

		function coherence( e ) {
			var i = function ( v ) { var x = parseFloat( v ); return isFinite( x ) && x > 0 ? Math.floor( x ) : 0; };
			var cmd = i( e.n_commandes );
			var somme = i( e.n_livrees ) + i( e.n_annulees ) + i( e.n_retours );
			if ( ! avert ) { return; }
			if ( cmd > 0 && somme > cmd ) {
				avert.textContent = ( CD.finIncoherent || '' )
					.replace( '%1$d', somme ).replace( '%2$d', cmd );
				avert.hidden = false;
			} else {
				avert.hidden = true;
			}
		}

		var maj = function () {
			var e = lire();
			peindre( calculer( e ) );
			coherence( e );
		};

		champs.forEach( function ( i ) {
			i.addEventListener( 'input', maj );
		} );
		maj();

		/* ---- Enregistrement ---------------------------------------------- */

		// 🚨 Ce formulaire N'EST PAS un `aod-cd-settings-form` : le binder générique
		// enverrait tout le formulaire et afficherait un simple toast. Ici la
		// réponse porte les résultats RECALCULÉS par le serveur, qu'on confronte
		// aux nôtres — c'est le garde-fou de la duplication de formules, et il n'a
		// de sens que dans ce handler-ci.
		form.addEventListener( 'submit', function ( ev ) {
			ev.preventDefault();
			var e = lire();
			var mien = calculer( e );
			var data = new FormData();
			data.append( 'action', 'aod_cd_finance_save' );
			data.append( 'nonce', CD.nonce || '' );
			data.append( 'titre', ( form.querySelector( '.aod-cd-fincalc__nom' ) || {} ).value || '' );
			Object.keys( e ).forEach( function ( k ) { data.append( k, e[ k ] ); } );

			var bouton = form.querySelector( '.aod-cd-fincalc__save' );
			if ( bouton ) { bouton.disabled = true; }

			fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					if ( bouton ) { bouton.disabled = false; }
					if ( ! r || ! r.success ) {
						if ( msg ) {
							msg.textContent = ( r && r.data && r.data.message ) || CD.i18nError || '';
							msg.classList.add( 'is-bad' );
						}
						return;
					}
					if ( msg ) {
						msg.classList.remove( 'is-bad' );
						msg.textContent = r.data.message || '';
					}
					comparer( mien, r.data.resultats );
					if ( toast ) { toast( r.data.message || '' ); }
					// L'historique est rendu côté serveur : on le recharge plutôt que
					// de le reconstruire ici en double.
					window.location.reload();
				} )
				.catch( function () {
					if ( bouton ) { bouton.disabled = false; }
					if ( msg ) {
						msg.textContent = CD.i18nError || '';
						msg.classList.add( 'is-bad' );
					}
				} );
		} );

		// LE GARDE-FOU. Si l'écran et le serveur ne disent pas la même chose, on
		// affiche le nombre du SERVEUR et on le signale. Une divergence silencieuse
		// serait le pire des deux mondes : l'utilisateur déciderait sur un chiffre
		// que la base ne connaît pas.
		function comparer( mien, sien ) {
			if ( ! sien ) { return; }
			var ecarts = [];
			Object.keys( sien ).forEach( function ( k ) {
				var a = Number( mien[ k ] ) || 0;
				var b = Number( sien[ k ] ) || 0;
				if ( Math.abs( a - b ) > 0.01 ) { ecarts.push( k ); }
			} );
			if ( ! ecarts.length ) { return; }
			peindre( sien );
			if ( msg ) {
				msg.textContent = ( CD.finDivergence || '' ).replace( '%s', ecarts.join( ', ' ) );
				msg.classList.add( 'is-bad' );
			}
		}

		/* ---- Tout effacer ------------------------------------------------- */

		var vider = form.querySelector( '.aod-cd-fincalc__reset' );
		if ( vider ) {
			vider.addEventListener( 'click', function () {
				champs.forEach( function ( i ) { i.value = ''; } );
				var nom = form.querySelector( '.aod-cd-fincalc__nom' );
				if ( nom ) { nom.value = ''; }
				if ( msg ) { msg.textContent = ''; msg.classList.remove( 'is-bad' ); }
				maj();
				if ( champs[ 0 ] ) { champs[ 0 ].focus(); }
			} );
		}

		/* ---- Historique --------------------------------------------------- */

		var hist = document.querySelector( '[data-hist]' );
		if ( ! hist ) { return; }

		hist.addEventListener( 'click', function ( ev ) {
			var ligne = ev.target.closest( '.aod-cd-finhist__i' );
			if ( ! ligne ) { return; }

			if ( ev.target.closest( '.aod-cd-finhist__load' ) ) {
				var e;
				try {
					e = JSON.parse( ligne.getAttribute( 'data-calc' ) || '{}' );
				} catch ( err ) {
					return;
				}
				champs.forEach( function ( i ) {
					i.value = e.hasOwnProperty( i.name ) ? e[ i.name ] : '';
				} );
				maj();
				grille.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				return;
			}

			if ( ev.target.closest( '.aod-cd-finhist__del' ) ) {
				var data = new FormData();
				data.append( 'action', 'aod_cd_finance_delete' );
				data.append( 'nonce', CD.nonce || '' );
				data.append( 'calc', ligne.getAttribute( 'data-id' ) || '' );
				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( r ) {
						if ( r && r.success ) {
							ligne.remove();
							if ( toast ) { toast( r.data.message || '' ); }
						}
					} );
			}
		} );
	}

	// DEUX binders, pas un appel imbriqué. Le premier jet appelait `bindDepenses()`
	// depuis `bindFinance()` : un cliquet (32.5 — R2) l'a refusé, et il avait
	// raison. `init()` isole et NOMME chaque binder qui échoue ; appelé nu depuis
	// un autre, celui-ci aurait fait passer sa propre panne pour celle du
	// calculateur — l'écran fautif désigné à la console n'aurait pas été le bon.
	core.register( 'bindFinance', bindFinance );
	core.register( 'bindDepenses', bindDepenses );
}( window.aodCdCore ) );
