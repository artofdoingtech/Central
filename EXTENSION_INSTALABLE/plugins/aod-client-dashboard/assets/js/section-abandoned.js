/* AOD Client Dashboard — section « Commandes abandonnées » (conversion, 39.1).
   IIFE à ALIAS LOCAUX : consomme les helpers de la coque (dashboard.js) sous
   leur nom d'origine. Premier binder de cette section (elle était en lecture
   pure jusqu'ici). */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var netErr = core.netErr;
	var confirmModal = core.confirmModal;
	// 43.3 — sélecteur d'assignation de prospect (mêmes helpers de coque que le
	// widget jumeau des commandes, cf. section-orders.js).
	var bindFloatingMenu = core.bindFloatingMenu;
	var bindListboxKeys = core.bindListboxKeys;
	var placeFloatingMenu = core.placeFloatingMenu;

	/* Bouton « Commander » : convertit un panier abandonné en commande (AJAX).
	   39.1 s'arrête à : la commande existe, la ligne disparaît. L'ouverture de la
	   modale d'édition sur order_id est l'étape 39.2. */
	function bindAbandonedConvert() {
		document.querySelectorAll( '.aod-cd-convert' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var id = btn.dataset.leadId;
				if ( ! id ) { return; }

				var msg = CD.i18nConvertConfirm || 'Créer la commande pour ce prospect ?';
				confirmModal( { message: msg } ).then( function ( ok ) {
				if ( ! ok ) { return; }

				btn.disabled = true;
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_convert_lead' );
				body.append( 'nonce', CD.nonce );
				body.append( 'lead_id', id );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res && res.success ) {
							// Le prospect est converti : ce n'est plus un abandon → retirer la ligne.
							var row = btn.closest( 'tr' );
							if ( row ) { row.remove(); }
							toast( ( res.data && res.data.message ) || CD.i18nSaved || 'OK', false );
							// 39.2 : ouvrir la commande créée dans la modale d'édition
							// existante (exposée par section-orders.js). Lu au call-time
							// (le module orders est chargé avant) et gardé.
							if ( res.data && res.data.order_id && core.openOrderModal ) {
								core.openOrderModal( res.data.order_id );
							}
						} else {
							btn.disabled = false;
							toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
						}
					} )
					.catch( function () { btn.disabled = false; toast( netErr, true ); } );
				} ); // fin de la continuation confirmModal (47.7)
			} );
		} );
	}

	core.register( 'bindAbandonedConvert', bindAbandonedConvert );

	/* Suppression groupée de prospects (39.6). Jumeau de bindOrderBulkDelete
	   (section-orders.js), mais sur des classes PROPRES (aod-cd-leadcheck…) pour ne
	   pas entrer en conflit avec le binder Commandes qui tourne aussi sur cette page
	   (il ne trouve alors aucune de ses cases → no-op). GÉRANT uniquement : la barre
	   et les cases ne sont rendues côté serveur que si manage_woocommerce, et le
	   handler aod_cd_delete_leads répète la garde. */
	function bindAbandonedBulkDelete() {
		var master = document.querySelector( '.aod-cd-leadcheck-all' );
		var bar    = document.getElementById( 'aod-cd-lead-bulk' );
		var table  = document.querySelector( '.aod-cd-table' );
		if ( ! table ) { return; }

		function boxes() {
			return Array.prototype.slice.call( table.querySelectorAll( '.aod-cd-leadcheck' ) );
		}
		function checked() {
			return boxes().filter( function ( c ) { return c.checked; } );
		}

		function sync() {
			var all = boxes();
			var sel = checked();
			if ( bar ) {
				// 45.2 — même mécanique que Commandes : la barre reste rendue, ce
				// sont les actions qui s'arment. Cf. `section-orders.js`.
				var vide = sel.length === 0;
				bar.classList.toggle( 'is-vide', vide );
				bar.querySelectorAll( '.aod-cd-bulk-act' ).forEach( function ( b ) {
					b.disabled = vide;
				} );
				var n = bar.querySelector( '.aod-cd-bulkbar-n' );
				if ( n ) { n.textContent = String( sel.length ); }
				var bn = bar.querySelector( '.aod-cd-bulkbar-btnn' );
				if ( bn ) { bn.textContent = '(' + sel.length + ')'; }
			}
			if ( master ) {
				master.checked = all.length > 0 && sel.length === all.length;
				master.indeterminate = sel.length > 0 && sel.length < all.length;
			}
			boxes().forEach( function ( c ) {
				var row = c.closest( 'tr' );
				if ( row ) { row.classList.toggle( 'is-selected', c.checked ); }
			} );
		}

		if ( master ) {
			master.addEventListener( 'change', function () {
				boxes().forEach( function ( c ) { c.checked = master.checked; } );
				sync();
			} );
		}

		table.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.classList.contains( 'aod-cd-leadcheck' ) ) { sync(); }
		} );

		var delBtn = bar ? bar.querySelector( '.aod-cd-lead-bulk-del' ) : null;
		if ( delBtn ) {
			delBtn.addEventListener( 'click', function () {
				var sel = checked();
				if ( ! sel.length ) { return; }
				var ids = sel.map( function ( c ) { return c.value; } );
				var msg = ( CD.i18nLeadBulkDelConfirm || 'Supprimer %d prospect(s) ?' ).replace( '%d', ids.length );
				confirmModal( { message: msg, danger: true } ).then( function ( ok ) {
				if ( ! ok ) { return; }

				delBtn.disabled = true;
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_delete_leads' );
				body.append( 'nonce', CD.nonce );
				ids.forEach( function ( id ) { body.append( 'lead_ids[]', id ); } );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						delBtn.disabled = false;
						if ( res && res.success ) {
							( res.data.deleted || [] ).forEach( function ( id ) {
								var cb = table.querySelector( '.aod-cd-leadcheck[value="' + id + '"]' );
								var row = cb ? cb.closest( 'tr' ) : null;
								if ( row ) { row.remove(); }
							} );
							if ( master ) { master.checked = false; master.indeterminate = false; }
							sync();
							toast( ( res.data && res.data.message ) || CD.i18nDeleted || 'Supprimé', false );
							if ( ! table.querySelectorAll( '.aod-cd-leadcheck' ).length ) {
								setTimeout( function () { window.location.reload(); }, 900 );
							}
						} else {
							toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
						}
					} )
					.catch( function () { delBtn.disabled = false; toast( netErr, true ); } );
				} ); // fin de la continuation confirmModal (47.7)
			} );
		}

		sync();
	}

	core.register( 'bindAbandonedBulkDelete', bindAbandonedBulkDelete );

	/* Sélecteur d'assignation d'un PROSPECT (43.3).

	   Jumeau de wireAssignWidget/applyAssign (section-orders.js, 42.4) sous des
	   classes et un point AJAX PROPRES.

	   ATTENTION — les classes NE PEUVENT PAS être partagées : section-orders.js câble
	   `.aod-cd-assignsel` par un querySelectorAll GLOBAL et poste
	   `aod_cd_order_assign` avec un `order_id` — or TOUS les modules section-*.js
	   sont chargés sur TOUTES les pages de /gestion (dashboard_scripts()). Un
	   prospect portant les classes « commande » partirait au mauvais handler,
	   avec un identifiant qui ne désigne aucune commande. La peau CSS, elle, est
	   mutualisée (sélecteurs joints dans dashboard.css).

	   GÉRANT uniquement : le widget n'est rendu côté serveur que si
	   manage_woocommerce, et ajax_lead_assign() répète la garde. */
	function bindLeadAssign() {
		document.querySelectorAll( '.aod-cd-leadassignsel' ).forEach( wireLeadAssignWidget );
	}

	function wireLeadAssignWidget( widget ) {
		var btn  = widget.querySelector( '.aod-cd-leadassignsel-btn' );
		var menu = widget.querySelector( '.aod-cd-leadassignsel-menu' );
		if ( ! btn || ! menu ) { return; }
		var txt = btn.querySelector( '.aod-cd-leadassignsel-text' );

		function place() { placeFloatingMenu( btn, menu ); }

		function setOpen( on ) {
			if ( on ) {
				menu.hidden = false;
				place();
				widget.classList.add( 'is-open' );
			} else {
				menu.hidden = true;
				widget.classList.remove( 'is-open' );
			}
			btn.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
		}

		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			setOpen( menu.hidden );
		} );

		function choose( opt ) {
			if ( opt.dataset.value !== widget.dataset.value ) {
				applyLeadAssign( widget, txt, opt );
			}
		}

		menu.addEventListener( 'click', function ( e ) {
			var opt = e.target.closest( '.aod-cd-leadassignsel-opt' );
			if ( ! opt ) { return; }
			e.preventDefault();
			setOpen( false );
			choose( opt );
		} );

		bindListboxKeys( {
			btn: btn,
			menu: menu,
			optSel: '.aod-cd-leadassignsel-opt',
			isOpen: function () { return ! menu.hidden; },
			setOpen: setOpen,
			choose: choose,
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! menu.hidden && ! e.target.closest( '.aod-cd-leadassignsel' ) ) { setOpen( false ); }
		} );
		bindFloatingMenu(
			btn,
			menu,
			function () { return ! menu.hidden; },
			place,
			function () { setOpen( false ); }
		);
	}

	/* Applique un changement d'assignation de prospect (AJAX). */
	function applyLeadAssign( widget, txt, opt ) {
		var confirmerId = opt.dataset.value;
		var newLabel    = opt.querySelector( '.aod-cd-leadassignsel-text' ).textContent;

		widget.classList.add( 'is-saving' );

		var body = new URLSearchParams();
		body.append( 'action', 'aod_cd_lead_assign' );
		body.append( 'nonce', CD.nonce );
		body.append( 'lead_id', widget.dataset.lead );
		body.append( 'confirmer_id', confirmerId );

		fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				widget.classList.remove( 'is-saving' );
				if ( res && res.success ) {
					widget.dataset.value = confirmerId;
					txt.textContent      = newLabel;
					widget.querySelectorAll( '.aod-cd-leadassignsel-opt' ).forEach( function ( o ) {
						var on = ( o.dataset.value === confirmerId );
						o.classList.toggle( 'is-active', on );
						o.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					} );
					// Colonne « Assigné à » de la même ligne : elle affiche le nom
					// rendu côté serveur, elle doit suivre le sélecteur sans
					// rechargement (sinon le gérant lit deux valeurs contradictoires).
					// On RECONSTRUIT le <span> avec sa classe (tiret sourd vs nom),
					// jamais un simple textContent sur la cellule : ça effacerait le
					// balisage que le rendu serveur pose, et la ligne perdrait son
					// style au premier changement. textContent sur le span créé =
					// aucune injection possible.
					var row  = widget.closest( 'tr' );
					var cell = row ? row.querySelector( '.aod-cd-cell-leadassigned' ) : null;
					if ( cell ) {
						var span = document.createElement( 'span' );
						if ( confirmerId === '0' ) {
							span.className   = 'aod-cd-muted';
							span.textContent = '—';
						} else {
							span.className   = 'aod-cd-assigned-name';
							span.textContent = ( res.data && res.data.label ) ? res.data.label : newLabel;
						}
						cell.innerHTML = '';
						cell.appendChild( span );
					}
					widget.classList.add( 'is-ok' );
					setTimeout( function () { widget.classList.remove( 'is-ok' ); }, 1500 );
					toast( ( res.data && res.data.message ) || 'OK', false );
				} else {
					toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
				}
			} )
			.catch( function () {
				widget.classList.remove( 'is-saving' );
				toast( netErr, true );
			} );
	}

	core.register( 'bindLeadAssign', bindLeadAssign );
}( window.aodCdCore ) );
