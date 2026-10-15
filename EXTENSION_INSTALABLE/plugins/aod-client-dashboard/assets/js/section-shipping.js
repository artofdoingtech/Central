/* AOD Client Dashboard — section « Livraison » (extraction 33.3).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var escAttr = core.escAttr;
	var emptyState = core.emptyState;
	var netErr = core.netErr;
	var confirmModal = core.confirmModal;

	/* Livraison : checkbox « tout cocher » applique la gratuité à toutes les wilayas */
	function bindShippingFree() {
		var all = document.querySelector( '.aod-cd-free-all' );
		if ( ! all ) { return; }
		var ones = function () {
			return Array.prototype.slice.call( document.querySelectorAll( '.aod-cd-free-one' ) );
		};
		var syncMaster = function () {
			var list = ones();
			if ( ! list.length ) { return; }
			var checked = list.filter( function ( c ) { return c.checked; } ).length;
			all.checked = checked === list.length;
			all.indeterminate = checked > 0 && checked < list.length;
		};
		all.addEventListener( 'change', function () {
			ones().forEach( function ( c ) { c.checked = all.checked; } );
		} );
		document.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.classList.contains( 'aod-cd-free-one' ) ) { syncMaster(); }
		} );
		syncMaster();
	}

	// Tableau des transporteurs : ligne cliquable qui déplie le panneau d'identifiants.
	function bindCarrierRows() {
		var rows = Array.prototype.slice.call( document.querySelectorAll( '.aod-cd-carrier-row' ) );
		if ( ! rows.length ) { return; }

		var toggle = function ( row ) {
			var panel = row.nextElementSibling;
			if ( ! panel || ! panel.classList.contains( 'aod-cd-carrier-panel' ) ) { return; }
			var open = row.classList.toggle( 'is-open' );
			panel.hidden = ! open;
			row.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		};

		rows.forEach( function ( row ) {
			row.addEventListener( 'click', function () { toggle( row ); } );
			row.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) {
					e.preventDefault();
					toggle( row );
				}
			} );
		} );
	}

	// Bouton « Tester la connexion » dans le panneau d'un transporteur.
	function bindCarrierTest() {
		var btns = Array.prototype.slice.call( document.querySelectorAll( '.aod-cd-carrier-test-btn' ) );
		if ( ! btns.length ) { return; }

		btns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.stopPropagation(); // Ne pas replier le panneau.
				var id    = btn.getAttribute( 'data-carrier' );
				var panel = btn.closest( '.aod-cd-carrier-panel' );
				var msg   = panel ? panel.querySelector( '.aod-cd-carrier-test-msg' ) : null;

				btn.disabled = true;
				if ( msg ) {
					msg.textContent = CD.i18nTesting || '…';
					msg.className   = 'aod-cd-carrier-test-msg is-pending';
				}

				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_test_carrier' );
				body.append( 'nonce', CD.nonce );
				body.append( 'carrier', id );
				// Joint les identifiants saisis pour ce transporteur (name="id[champ]").
				if ( panel ) {
					panel.querySelectorAll( 'input, select' ).forEach( function ( f ) {
						if ( ! f.name ) { return; }
						if ( ( 'checkbox' === f.type || 'radio' === f.type ) && ! f.checked ) { return; }
						body.append( f.name, f.value );
					} );
				}

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						btn.disabled = false;
						var success = !! ( res && res.success );
						var live    = !! ( success && res.data && res.data.live );
						var m       = ( res && res.data && res.data.message ) || '';
						if ( msg ) {
							msg.textContent = m;
							msg.className   = 'aod-cd-carrier-test-msg ' + ( live ? 'is-ok' : ( success ? 'is-warn' : 'is-bad' ) );
						}
						toast( m || ( live ? 'OK' : netErr ), ! success );
						// Reflète « Connecté » sur la ligne si vérifié en direct — la
						// pastille aussi : sans ça, le test annonçait « connexion OK »
						// pendant que la ligne affichait encore « Non connecté ».
						if ( live ) {
							var row = panel ? panel.previousElementSibling : null;
							if ( row && row.classList.contains( 'aod-cd-carrier-row' ) ) {
								row.classList.add( 'is-on' );
								var pOn  = row.querySelector( '.aod-cd-carrier-on' );
								var pOff = row.querySelector( '.aod-cd-carrier-off' );
								if ( pOn )  { pOn.hidden  = false; }
								if ( pOff ) { pOff.hidden = true; }
							}
						}
					} )
					.catch( function () {
						btn.disabled = false;
						if ( msg ) {
							msg.textContent = netErr;
							msg.className   = 'aod-cd-carrier-test-msg is-bad';
						}
						toast( netErr, true );
					} );
			} );
		} );
	}

	// Champs de recherche : filtre les lignes des tableaux Tarifs / Transporteurs.
	function bindShippingSearch() {
		var inputs = Array.prototype.slice.call( document.querySelectorAll( '.aod-cd-search-input' ) );
		if ( ! inputs.length ) { return; }

		var norm = function ( s ) {
			return ( s || '' ).toString().toLowerCase()
				.normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
				.trim();
		};

		var filterPrices = function ( q ) {
			var table = document.querySelector( '.aod-cd-pricetable' );
			if ( ! table ) { return; }
			var rows = Array.prototype.slice.call( table.querySelectorAll( 'tbody > tr' ) );
			rows.forEach( function ( row ) {
				// 🔴 NE PAS CHERCHER DANS TOUTE LA CELLULE. Elle contient désormais le
				// marqueur « Non livrable », rendu sur chaque wilaya sans tarif : le
				// lire rendrait sa présence CHERCHABLE, donc taper « livr » ferait
				// ressortir les 58 lignes au lieu d'en filtrer aucune. On interroge le
				// code et le nom, qui sont les deux façons dont on désigne une wilaya
				// (« 16 » vaut « Alger »).
				// ⚠️ Le repli sur la cellule entière couvre un rendu servi depuis le
				// cache navigateur, antérieur aux deux <span> — même précaution que le
				// champ arabe des catégories.
				var cell = row.querySelector( '.aod-cd-cell-wilaya' ) || row.querySelector( 'td' );
				var code = row.querySelector( '.aod-cd-w-code' );
				var name = row.querySelector( '.aod-cd-w-name' );
				var txt  = ( code || name )
					? ( ( code ? code.textContent : '' ) + ' ' + ( name ? name.textContent : '' ) )
					: ( cell ? cell.textContent : '' );
				var hit  = ! q || norm( txt ).indexOf( q ) !== -1;
				row.hidden = ! hit;
			} );
		};

		var filterCarriers = function ( q ) {
			var rows = Array.prototype.slice.call( document.querySelectorAll( '.aod-cd-carrier-row' ) );
			rows.forEach( function ( row ) {
				var name  = row.querySelector( '.aod-cd-carrier-name' );
				var hit   = ! q || norm( name ? name.textContent : '' ).indexOf( q ) !== -1;
				var panel = row.nextElementSibling;
				row.hidden = ! hit;
				if ( panel && panel.classList.contains( 'aod-cd-carrier-panel' ) && ! row.classList.contains( 'is-open' ) ) {
					panel.hidden = true;
				}
			} );
		};

		inputs.forEach( function ( input ) {
			input.addEventListener( 'input', function () {
				var q = norm( input.value );
				if ( 'prices' === input.getAttribute( 'data-filter' ) ) {
					filterPrices( q );
				} else {
					filterCarriers( q );
				}
			} );
		} );
	}

	/* Import des tarifs transporteur : préview (marge à la volée) → appliquer / restaurer.
	   Miroir JS de AOD_CD_Section_Shipping::apply_margin() (ex-Dashboard, 30.4) — garder les deux formules alignées. */
	function bindImportFees() {
		var box = document.querySelector( '.aod-cd-import' );
		if ( ! box ) { return; }

		var fetchBtn   = box.querySelector( '.aod-cd-import-fetch' );
		var restoreBtn = box.querySelector( '.aod-cd-import-restore' );
		var carrierSel = box.querySelector( '.aod-cd-import-carrier' );
		var mtypeSel   = box.querySelector( '.aod-cd-import-mtype' );
		var mvalWrap   = box.querySelector( '.aod-cd-import-mval-wrap' );
		var mvalInput  = box.querySelector( '.aod-cd-import-mval' );
		var roundSel   = box.querySelector( '.aod-cd-import-round' );
		var msg        = box.querySelector( '.aod-cd-import-msg' );
		var result     = box.querySelector( '.aod-cd-import-result' );

		var lastRows = null; // Dernier jeu de tarifs API (préview courante).

		function marginParams() {
			return {
				type:  mtypeSel ? mtypeSel.value : 'none',
				value: mvalInput ? parseFloat( mvalInput.value ) || 0 : 0,
				round: roundSel ? parseInt( roundSel.value, 10 ) || 0 : 0
			};
		}

		// Miroir de AOD_CD_Dashboard::apply_margin() (PHP = source de vérité).
		function applyMargin( base, p ) {
			var v = parseFloat( base ) || 0;
			if ( v <= 0 ) { return 0; }
			if ( 'fixed' === p.type ) { v += p.value; }
			else if ( 'percent' === p.type ) { v += v * ( p.value / 100 ); }
			if ( v < 0 ) { v = 0; }
			if ( p.round > 0 ) { v = Math.round( v / p.round ) * p.round; }
			return Math.round( v );
		}

		function setMsg( text, cls ) {
			if ( ! msg ) { return; }
			msg.textContent = text || '';
			msg.className   = 'aod-cd-import-msg' + ( cls ? ' ' + cls : '' );
		}

		// Affiche/masque le champ « valeur » selon le type de marge.
		function syncMval() {
			if ( ! mvalWrap ) { return; }
			mvalWrap.hidden = ( ! mtypeSel || 'none' === mtypeSel.value );
		}
		if ( mtypeSel ) { mtypeSel.addEventListener( 'change', function () { syncMval(); renderPreview(); } ); }
		if ( mvalInput ) { mvalInput.addEventListener( 'input', renderPreview ); }
		if ( roundSel ) { roundSel.addEventListener( 'change', renderPreview ); }
		syncMval();

		function cell( cur, apiVal, p ) {
			var newVal = applyMargin( apiVal, p );
			var curTxt = ( cur === null || cur === undefined ) ? ( CD.i18nImpNone || '—' ) : String( cur );
			var diff   = ( apiVal > 0 ) && ( cur === null || cur === undefined || Math.round( cur ) !== newVal );
			var newTxt = apiVal > 0 ? String( newVal ) : ( CD.i18nImpNone || '—' );
			return '<span class="aod-cd-imp-cur">' + curTxt + '</span>'
				+ '<span class="aod-cd-imp-arrow" aria-hidden="true">→</span>'
				+ '<span class="aod-cd-imp-new' + ( diff ? ' is-diff' : '' ) + '">' + newTxt + '</span>';
		}

		function renderPreview() {
			if ( ! lastRows || ! result ) { return; }
			var p = marginParams();
			if ( ! lastRows.length ) {
				result.innerHTML = emptyState( 'empty-tray', CD.i18nImpEmpty || '—' );
				result.hidden = false;
				return;
			}

			var html = '<div class="aod-cd-scroll"><table class="aod-cd-table aod-cd-table--cards aod-cd-imp-table"><thead><tr>'
				+ '<th class="aod-cd-imp-checkcol"><label class="aod-cd-check"><input type="checkbox" class="aod-cd-imp-all" title="' + ( CD.i18nImpSelectAll || '' ) + '"></label></th>'
				+ '<th>' + ( CD.i18nImpWilaya || '' ) + '</th>'
				+ '<th>' + ( CD.i18nImpHome || '' ) + '</th>'
				+ '<th>' + ( CD.i18nImpDesk || '' ) + '</th>'
				+ '</tr></thead><tbody>';

			lastRows.forEach( function ( r ) {
				var newHome = applyMargin( r.api_home, p );
				var newDesk = applyMargin( r.api_desk, p );
				var changed = ( r.api_home > 0 && ( r.cur_home === null || Math.round( r.cur_home ) !== newHome ) )
					|| ( r.api_desk > 0 && ( r.cur_desk === null || Math.round( r.cur_desk ) !== newDesk ) );
				html += '<tr>'
					+ '<td class="aod-cd-imp-checkcol" data-label="">'
					+ '<label class="aod-cd-check"><input type="checkbox" class="aod-cd-imp-one" value="' + r.code + '"' + ( changed ? ' checked' : '' ) + '></label></td>'
					+ '<td class="aod-cd-cell-wilaya" data-label="' + ( CD.i18nImpWilaya || '' ) + '">' + escAttr( r.name ) + '</td>'
					+ '<td data-label="' + ( CD.i18nImpHome || '' ) + '">' + cell( r.cur_home, r.api_home, p ) + '</td>'
					+ '<td data-label="' + ( CD.i18nImpDesk || '' ) + '">' + cell( r.cur_desk, r.api_desk, p ) + '</td>'
					+ '</tr>';
			} );

			html += '</tbody></table></div>'
				+ '<div class="aod-cd-import-foot">'
				+ '<button type="button" class="aod-cd-btn aod-cd-btn-primary aod-cd-imp-apply">' + ( CD.i18nImpApply || 'Appliquer' ) + '</button>'
				+ '<button type="button" class="aod-cd-btn aod-cd-imp-cancel">' + ( CD.i18nImpCancel || 'Annuler' ) + '</button>'
				+ '</div>';

			result.innerHTML = html;
			result.hidden = false;

			// Tout cocher / décocher.
			var all = result.querySelector( '.aod-cd-imp-all' );
			var ones = function () { return Array.prototype.slice.call( result.querySelectorAll( '.aod-cd-imp-one' ) ); };
			var syncAll = function () {
				var list = ones(); if ( ! list.length ) { return; }
				var n = list.filter( function ( c ) { return c.checked; } ).length;
				all.checked = ( n === list.length );
				all.indeterminate = ( n > 0 && n < list.length );
			};
			if ( all ) {
				all.addEventListener( 'change', function () { ones().forEach( function ( c ) { c.checked = all.checked; } ); } );
				result.addEventListener( 'change', function ( e ) {
					if ( e.target && e.target.classList.contains( 'aod-cd-imp-one' ) ) { syncAll(); }
				} );
				syncAll();
			}

			var applyBtn = result.querySelector( '.aod-cd-imp-apply' );
			if ( applyBtn ) { applyBtn.addEventListener( 'click', doApply ); }
			var cancelBtn = result.querySelector( '.aod-cd-imp-cancel' );
			if ( cancelBtn ) { cancelBtn.addEventListener( 'click', function () { result.hidden = true; result.innerHTML = ''; lastRows = null; setMsg( '' ); } ); }
		}

		// 1) Récupère les tarifs API (préview, aucune écriture serveur).
		if ( fetchBtn ) {
			fetchBtn.addEventListener( 'click', function () {
				fetchBtn.disabled = true;
				setMsg( CD.i18nImpImporting || '…', 'is-pending' );
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_import_fees' );
				body.append( 'nonce', CD.nonce );
				body.append( 'op', 'preview' );
				body.append( 'carrier', carrierSel ? carrierSel.value : '' );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						fetchBtn.disabled = false;
						if ( res && res.success && res.data && res.data.rows ) {
							lastRows = res.data.rows;
							setMsg( '' );
							renderPreview();
							if ( restoreBtn && res.data.has_backup ) { restoreBtn.hidden = false; }
						} else {
							setMsg( ( res && res.data && res.data.message ) || netErr, 'is-bad' );
							toast( ( res && res.data && res.data.message ) || netErr, true );
						}
					} )
					.catch( function () { fetchBtn.disabled = false; setMsg( netErr, 'is-bad' ); toast( netErr, true ); } );
			} );
		}

		// 2) Applique les wilayas cochées (écriture serveur, marge recalculée côté PHP).
		function doApply() {
			var checked = Array.prototype.slice.call( result.querySelectorAll( '.aod-cd-imp-one:checked' ) );
			if ( ! checked.length ) { toast( CD.i18nImpNoCheck || '', true ); return; }
			confirmModal( {
				message: CD.i18nImpApplyConfirm || ''
			} ).then( function ( ok ) {
			if ( ! ok ) { return; }

			var applyBtn = result.querySelector( '.aod-cd-imp-apply' );
			if ( applyBtn ) { applyBtn.disabled = true; }
			setMsg( CD.i18nImpApplying || '…', 'is-pending' );

			var p = marginParams();
			var body = new URLSearchParams();
			body.append( 'action', 'aod_cd_import_fees' );
			body.append( 'nonce', CD.nonce );
			body.append( 'op', 'apply' );
			body.append( 'carrier', carrierSel ? carrierSel.value : '' );
			body.append( 'margin_type', p.type );
			body.append( 'margin_value', p.value );
			body.append( 'rounding', p.round );
			checked.forEach( function ( c ) { body.append( 'wilayas[]', c.value ); } );

			fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) {
						toast( ( res.data && res.data.message ) || 'OK', false );
						// Recharger pour refléter les nouveaux tarifs dans le tableau de saisie.
						if ( res.data && res.data.reload ) { setTimeout( function () { window.location.reload(); }, 700 ); }
					} else {
						if ( applyBtn ) { applyBtn.disabled = false; }
						setMsg( ( res && res.data && res.data.message ) || netErr, 'is-bad' );
						toast( ( res && res.data && res.data.message ) || netErr, true );
					}
				} )
				.catch( function () { if ( applyBtn ) { applyBtn.disabled = false; } setMsg( netErr, 'is-bad' ); toast( netErr, true ); } );
			} ); // fin de la continuation confirmModal (47.7)
		}

		// 3) Restaure le dernier backup.
		if ( restoreBtn ) {
			restoreBtn.addEventListener( 'click', function () {
				confirmModal( {
					message: CD.i18nImpRestoreConfirm || ''
				} ).then( function ( ok ) {
				if ( ! ok ) { return; }
				restoreBtn.disabled = true;
				setMsg( CD.i18nImpApplying || '…', 'is-pending' );
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_import_fees' );
				body.append( 'nonce', CD.nonce );
				body.append( 'op', 'restore' );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res && res.success ) {
							toast( ( res.data && res.data.message ) || 'OK', false );
							if ( res.data && res.data.reload ) { setTimeout( function () { window.location.reload(); }, 700 ); }
						} else {
							restoreBtn.disabled = false;
							setMsg( ( res && res.data && res.data.message ) || netErr, 'is-bad' );
							toast( ( res && res.data && res.data.message ) || netErr, true );
						}
					} )
					.catch( function () { restoreBtn.disabled = false; setMsg( netErr, 'is-bad' ); toast( netErr, true ); } );
				} ); // fin de la continuation confirmModal (47.7)
			} );
		}
	}

	/**
	 * Chaque bloc de la page Livraison ouvre sa FENÊTRE (2026-08-11).
	 *
	 * Les quatre blocs étaient des accordéons ouverts par défaut : la page faisait
	 * défiler 58 wilayas, six transporteurs et un panneau d'import avant qu'on
	 * n'ait choisi quoi que ce soit. Elle ne montre plus que quatre cartes, et le
	 * contenu s'ouvre au-dessus.
	 *
	 * 🔒 Le `<dialog>` est rendu PAR LE PHP, à l'intérieur du formulaire — ce
	 * binder ne déplace aucun nœud. C'est la condition pour que les champs restent
	 * vus par `new FormData( form )` ; la déplacer ici, dans `document.body`,
	 * perdrait la grille tarifaire en silence. Même raison, même patron que
	 * `bindPixelDialogs` (section-marketing.js), qui le documente en détail.
	 *
	 * Le navigateur fournit le fond, le plan supérieur, le piège de focus et
	 * Échap : rien de tout cela n'est réécrit ici.
	 */
	function bindShipDialogs() {
		document.querySelectorAll( '.aod-cd-shipcard' ).forEach( function ( card ) {
			var head = card.querySelector( ':scope > .aod-cd-shipcard__head' );
			var dlg  = card.querySelector( ':scope > .aod-cd-shipdlg' );
			if ( ! head || ! dlg || ! dlg.showModal ) { return; }

			head.addEventListener( 'click', function () {
				dlg.showModal();
				// La recherche d'abord quand il y en a une : sur 58 wilayas, on
				// ouvre cette fenêtre pour en trouver UNE, pas pour lire la liste.
				var premier = dlg.querySelector( '.aod-cd-search-input' )
					|| dlg.querySelector( 'input:not([type=checkbox]):not([type=hidden]), select, textarea' );
				if ( premier ) { premier.focus(); }
			} );

			dlg.querySelectorAll( '.aod-cd-shipdlg-close' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () { dlg.close(); } );
			} );

			// Clic sur le FOND. Un `<dialog>` occupe toute la fenêtre : le fond est
			// le dialogue lui-même, hors de son panneau. On compare donc le POINT
			// cliqué à la boîte du panneau — la cible serait le dialogue dans les
			// deux cas.
			// ⚠️ Ici le panneau EST le dialogue (il porte sa propre boîte, contrairement
			// aux cartes de régie dont le corps est un enfant). On mesure donc le
			// dialogue lui-même : un clic hors de sa boîte ferme.
			dlg.addEventListener( 'click', function ( e ) {
				var r = dlg.getBoundingClientRect();
				var dedans = e.clientX >= r.left && e.clientX <= r.right
					&& e.clientY >= r.top && e.clientY <= r.bottom;
				if ( ! dedans ) { dlg.close(); }
			} );
		} );
	}

	core.register( 'bindShipDialogs', bindShipDialogs );
	core.register( 'bindShippingFree', bindShippingFree );
	core.register( 'bindCarrierRows', bindCarrierRows );
	core.register( 'bindCarrierTest', bindCarrierTest );
	core.register( 'bindShippingSearch', bindShippingSearch );
	core.register( 'bindImportFees', bindImportFees );
}( window.aodCdCore ) );
