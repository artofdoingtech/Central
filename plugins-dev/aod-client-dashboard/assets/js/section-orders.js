/* AOD Client Dashboard — section « Commandes » (extraction 33.6).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var skeleton = core.skeleton;
	var bindFloatingMenu = core.bindFloatingMenu;
	var bindListboxKeys = core.bindListboxKeys;
	var netErr = core.netErr;
	var confirmModal = core.confirmModal;
	var openOverlayModal = core.openOverlayModal;

	/* ==========================================================================
	 * L'envoi au transporteur, devenu un geste (Phase 47.2)
	 * ========================================================== */

	/* Remplace le contenu de la cellule « Suivi » d'une ligne, en CONSERVANT son
	   `data-label` — l'attribut porte l'intitulé de la carte mobile (46.2), et
	   `innerHTML` ne l'efface pas mais un remplacement de <td> l'aurait fait.
	   Même geste que `applyStatus()`, écrit une fois plutôt que deux. */
	function majCelluleSuivi( orderId, html ) {
		if ( 'string' !== typeof html ) { return; }
		var btn = document.querySelector( '.aod-cd-ordercheck[value="' + orderId + '"]' );
		var row = btn ? btn.closest( 'tr' ) : null;
		if ( ! row ) {
			var b = document.querySelector( '.aod-cd-ship-send[data-order="' + orderId + '"]' );
			row = b ? b.closest( 'tr' ) : null;
		}
		var cell = row ? row.querySelector( '.aod-cd-cell-ship' ) : null;
		if ( ! cell ) { return; }
		var label = cell.getAttribute( 'data-label' );
		cell.innerHTML = html;
		if ( label !== null ) { cell.setAttribute( 'data-label', label ); }
		cell.classList.add( 'is-ship-updated' );
		setTimeout( function () { cell.classList.remove( 'is-ship-updated' ); }, 1500 );
	}

	/* Envoie UNE commande. Résout TOUJOURS (jamais de rejet) : dans un lot, une
	   commande en échec ne doit pas interrompre les suivantes — c'est tout
	   l'intérêt d'un compte rendu ligne à ligne. */
	function envoyerUne( orderId ) {
		var body = new URLSearchParams();
		body.append( 'action', 'aod_cd_ship_send' );
		body.append( 'nonce', CD.nonce );
		body.append( 'order_id', orderId );

		return fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				var d = ( res && res.data ) || {};
				majCelluleSuivi( orderId, d.ship_cell );
				return {
					ok:      !! ( res && res.success ),
					code:    d.code || '',
					message: d.message || '',
				};
			} )
			.catch( function () {
				return { ok: false, code: 'network', message: netErr };
			} );
	}

	/* Ce que le DOM sait d'une ligne, pour le récapitulatif d'envoi.
	   La présence du bouton `.aod-cd-ship-send` EST le critère d'éligibilité :
	   c'est exactement la vérité que le serveur applique (état `pending`/`failed`,
	   capability présente). Se fier au DOM plutôt qu'à une seconde règle écrite en
	   JS évite deux sources qui divergent — la dette que les Phases 44 à 47 ont
	   passé leur temps à refermer. */
	function ligneDepuisRang( row, id ) {
		if ( ! row ) { return null; }
		var btn  = row.querySelector( '.aod-cd-ship-send' );
		var num  = row.querySelector( '.aod-cd-onum' );
		var nom  = row.querySelector( '.aod-cd-client b' );
		var tr   = row.querySelector( '.aod-cd-carriersel-btn .aod-cd-carriersel-text' );
		var envo = row.querySelector( '.aod-cd-trackcode' );
		return {
			id:          id,
			eligible:    !! btn,
			numero:      num ? num.textContent.trim() : '#' + id,
			client:      nom ? nom.textContent.trim() : '',
			transporteur: tr ? tr.textContent.trim() : '',
			dejaEnvoyee: !! envo,
			// 47.3 — calculé par le SERVEUR (`ship_cell_html()`), qui seul connaît le
			// transporteur RÉSOLU de cette commande et sait s'il porte un champ note.
			notePerdue:  !! ( btn && '1' === btn.dataset.notePerdue ),
		};
	}

	/* 47.3 — enveloppe pour le LOT, qui part d'une case à cocher.
	   Le geste unitaire, lui, n'en a pas : un confirmateur n'a aucune case (la
	   colonne appartient à `$can_delete`). D'où la séparation. */
	function ligneEnvoi( checkbox ) {
		return ligneDepuisRang( checkbox.closest( 'tr' ), checkbox.value );
	}


	/* Câble UN widget de statut (.aod-cd-statussel) : ouverture, clavier, RTL,
	   application AJAX. Extrait de bindStatus (étape 39.2) pour être réutilisable
	   sur un widget INJECTÉ par AJAX dans la modale de détail (initOrderEdit) —
	   bindStatus ne balaie que le DOM présent au chargement. Comportement de la
	   liste inchangé. */
	function wireStatusWidget( widget ) {
		var btn  = widget.querySelector( '.aod-cd-statussel-btn' );
		var menu = widget.querySelector( '.aod-cd-statussel-menu' );
		if ( ! btn || ! menu ) { return; }
		var txt = btn.querySelector( '.aod-cd-statussel-text' );

		// Menu en position:fixed : on le place sous le bouton (ou au-dessus
		// s'il dépasse en bas), ce qui évite tout rognage par l'overflow du
		// tableau et le replace toujours au bon endroit.
		function place() {
			var r  = btn.getBoundingClientRect();
			var mh = menu.offsetHeight;
			var mw = menu.offsetWidth;
			var vw = window.innerWidth;
			var vh = window.innerHeight;
			var left = Math.max( 8, Math.min( r.left, vw - mw - 8 ) );
			var top  = r.bottom + 6;
			if ( top + mh > vh - 8 && r.top - 6 - mh > 8 ) { top = r.top - 6 - mh; }
			menu.style.left     = left + 'px';
			menu.style.top      = top + 'px';
			menu.style.minWidth = r.width + 'px';
		}

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
				applyStatus( widget, btn, txt, opt );
			}
		}

		menu.addEventListener( 'click', function ( e ) {
			var opt = e.target.closest( '.aod-cd-statussel-opt' );
			if ( ! opt ) { return; }
			e.preventDefault();
			setOpen( false );
			choose( opt );
		} );

		// Clavier : flèches + Entrée/Échap, focus maintenu sur le bouton.
		bindListboxKeys( {
			btn: btn,
			menu: menu,
			optSel: '.aod-cd-statussel-opt',
			isOpen: function () { return ! menu.hidden; },
			setOpen: setOpen,
			choose: choose,
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! menu.hidden && ! e.target.closest( '.aod-cd-statussel' ) ) { setOpen( false ); }
		} );
		// Le menu est en position:fixed : on le RECOLLE au bouton quand la page
		// défile (le refermer cassait le premier clic — cf. bindFloatingMenu).
		bindFloatingMenu(
			btn,
			menu,
			function () { return ! menu.hidden; },
			place,
			function () { setOpen( false ); }
		);
	}

	/* Changement de statut de commande (AJAX) — widgets de la LISTE. */
	function bindStatus() {
		document.querySelectorAll( '.aod-cd-statussel' ).forEach( wireStatusWidget );
		document.querySelectorAll( '.aod-cd-assignsel' ).forEach( wireAssignWidget );
		document.querySelectorAll( '.aod-cd-carriersel' ).forEach( wireCarrierWidget );
	}

	/* Câble UN widget d'assignation (.aod-cd-assignsel) — DUPLIQUÉ de
	   wireStatusWidget() sous un nom distinct (Phase 42.4) : le sélecteur de
	   statut est un rouage déjà éprouvé (statut → envoi transporteur), le
	   refactoriser en widget partagé ferait courir un risque de régression
	   sans bénéfice proportionné. Factoriser seulement si un TROISIÈME widget
	   de ce type apparaissait un jour (règle de trois). */
	function wireAssignWidget( widget ) {
		var btn  = widget.querySelector( '.aod-cd-assignsel-btn' );
		var menu = widget.querySelector( '.aod-cd-assignsel-menu' );
		if ( ! btn || ! menu ) { return; }
		var txt = btn.querySelector( '.aod-cd-assignsel-text' );

		function place() {
			var r  = btn.getBoundingClientRect();
			var mh = menu.offsetHeight;
			var mw = menu.offsetWidth;
			var vw = window.innerWidth;
			var vh = window.innerHeight;
			var left = Math.max( 8, Math.min( r.left, vw - mw - 8 ) );
			var top  = r.bottom + 6;
			if ( top + mh > vh - 8 && r.top - 6 - mh > 8 ) { top = r.top - 6 - mh; }
			menu.style.left     = left + 'px';
			menu.style.top      = top + 'px';
			menu.style.minWidth = r.width + 'px';
		}

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
				applyAssign( widget, btn, txt, opt );
			}
		}

		menu.addEventListener( 'click', function ( e ) {
			var opt = e.target.closest( '.aod-cd-assignsel-opt' );
			if ( ! opt ) { return; }
			e.preventDefault();
			setOpen( false );
			choose( opt );
		} );

		bindListboxKeys( {
			btn: btn,
			menu: menu,
			optSel: '.aod-cd-assignsel-opt',
			isOpen: function () { return ! menu.hidden; },
			setOpen: setOpen,
			choose: choose,
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! menu.hidden && ! e.target.closest( '.aod-cd-assignsel' ) ) { setOpen( false ); }
		} );
		bindFloatingMenu(
			btn,
			menu,
			function () { return ! menu.hidden; },
			place,
			function () { setOpen( false ); }
		);
	}

	/* Applique un changement d'assignation (AJAX) et met à jour l'affichage. */
	function applyAssign( widget, btn, txt, opt ) {
		var confirmerId = opt.dataset.value;
		var newLabel    = opt.querySelector( '.aod-cd-assignsel-text' ).textContent;

		widget.classList.add( 'is-saving' );

		var body = new URLSearchParams();
		body.append( 'action', 'aod_cd_order_assign' );
		body.append( 'nonce', CD.nonce );
		body.append( 'order_id', widget.dataset.order );
		body.append( 'confirmer_id', confirmerId );

		fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				widget.classList.remove( 'is-saving' );
				if ( res && res.success ) {
					widget.dataset.value = confirmerId;
					txt.textContent      = newLabel;
					widget.querySelectorAll( '.aod-cd-assignsel-opt' ).forEach( function ( o ) {
						var on = ( o.dataset.value === confirmerId );
						o.classList.toggle( 'is-active', on );
						o.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					} );
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

	/* Câble UN widget de transporteur (.aod-cd-carriersel) — Phase 47.1.
	   TROISIÈME jumeau de wireStatusWidget(), et la règle de trois annoncée en
	   42.4 ne se déclenche PAS ici : ce qui se mutualise sans risque, c'est la
	   PEAU (faite, sélecteurs joints en CSS), pas le comportement. Un widget
	   générique fondrait trois points AJAX distincts en un seul chemin — or tous
	   les section-*.js sont chargés sur TOUTES les pages de /gestion et câblent
	   par querySelectorAll GLOBAL : c'est très exactement le piège que 43.3 a
	   documenté (`leadassignsel`) et qui envoyait un prospect au handler des
	   commandes. Trois classes, trois binders, trois handlers — étanches. */
	function wireCarrierWidget( widget ) {
		var btn  = widget.querySelector( '.aod-cd-carriersel-btn' );
		var menu = widget.querySelector( '.aod-cd-carriersel-menu' );
		if ( ! btn || ! menu ) { return; }
		var txt = btn.querySelector( '.aod-cd-carriersel-text' );

		function place() {
			var r  = btn.getBoundingClientRect();
			var mh = menu.offsetHeight;
			var mw = menu.offsetWidth;
			var vw = window.innerWidth;
			var vh = window.innerHeight;
			var left = Math.max( 8, Math.min( r.left, vw - mw - 8 ) );
			var top  = r.bottom + 6;
			if ( top + mh > vh - 8 && r.top - 6 - mh > 8 ) { top = r.top - 6 - mh; }
			menu.style.left     = left + 'px';
			menu.style.top      = top + 'px';
			menu.style.minWidth = r.width + 'px';
		}

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
				applyCarrier( widget, btn, txt, opt );
			}
		}

		menu.addEventListener( 'click', function ( e ) {
			var opt = e.target.closest( '.aod-cd-carriersel-opt' );
			if ( ! opt ) { return; }
			e.preventDefault();
			setOpen( false );
			choose( opt );
		} );

		bindListboxKeys( {
			btn: btn,
			menu: menu,
			optSel: '.aod-cd-carriersel-opt',
			isOpen: function () { return ! menu.hidden; },
			setOpen: setOpen,
			choose: choose,
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! menu.hidden && ! e.target.closest( '.aod-cd-carriersel' ) ) { setOpen( false ); }
		} );
		bindFloatingMenu(
			btn,
			menu,
			function () { return ! menu.hidden; },
			place,
			function () { setOpen( false ); }
		);
	}

	/* Applique un changement de transporteur (AJAX) et met à jour l'affichage.
	   Le libellé affiché vient de la RÉPONSE, jamais de l'option cliquée : sur un
	   retour au défaut, l'option dit « Par défaut (X) » alors que le bouton doit
	   afficher X — c'est le serveur qui sait quel transporteur agira. */
	function applyCarrier( widget, btn, txt, opt ) {
		var carrierId = opt.dataset.value;

		widget.classList.add( 'is-saving' );

		var body = new URLSearchParams();
		body.append( 'action', 'aod_cd_order_carrier' );
		body.append( 'nonce', CD.nonce );
		body.append( 'order_id', widget.dataset.order );
		body.append( 'carrier_id', carrierId );

		fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				widget.classList.remove( 'is-saving' );
				if ( res && res.success ) {
					widget.dataset.value = carrierId;
					widget.classList.toggle( 'is-inherited', carrierId === '' );
					if ( res.data && res.data.label ) { txt.textContent = res.data.label; }
					// 47.3 — `data-note-perdue` dépend du transporteur : la cellule
					// « Suivi » se remet à jour ici, sinon l'avertissement resterait
					// celui du transporteur précédent.
					if ( res.data ) { majCelluleSuivi( widget.dataset.order, res.data.ship_cell ); }
					widget.querySelectorAll( '.aod-cd-carriersel-opt' ).forEach( function ( o ) {
						var on = ( o.dataset.value === carrierId );
						o.classList.toggle( 'is-active', on );
						o.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					} );
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

	/* Applique un changement de statut (AJAX) et met à jour l'affichage. */
	function applyStatus( widget, btn, txt, opt ) {
		var status   = opt.dataset.value;
		var newLabel = opt.querySelector( '.aod-cd-statussel-text' ).textContent;
		var newSlug  = opt.dataset.status;

		widget.classList.add( 'is-saving' );

		var body = new URLSearchParams();
		body.append( 'action', 'aod_cd_order_status' );
		body.append( 'nonce', CD.nonce );
		body.append( 'order_id', widget.dataset.order );
		body.append( 'status', status );

		fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				widget.classList.remove( 'is-saving' );
				if ( res && res.success ) {
					widget.dataset.value = status;
					txt.textContent      = newLabel;
					btn.dataset.status   = newSlug;
					widget.querySelectorAll( '.aod-cd-statussel-opt' ).forEach( function ( o ) {
						var on = ( o.dataset.value === status );
						o.classList.toggle( 'is-active', on );
						o.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					} );
					widget.classList.add( 'is-ok' );
					setTimeout( function () { widget.classList.remove( 'is-ok' ); }, 1500 );
					// Met à jour l'icône camion de la colonne « Suivi » en direct
					// (gris → vert dès que l'envoi auto a réussi), sans recharger.
					if ( typeof res.data.ship_cell === 'string' ) {
						var row  = widget.closest( 'tr' );
						var cell = row && row.querySelector( '.aod-cd-cell-ship' );
						if ( cell ) {
							// Conserve l'attribut data-label (vue cartes mobile).
							var label = cell.getAttribute( 'data-label' );
							cell.innerHTML = res.data.ship_cell;
							if ( label !== null ) { cell.setAttribute( 'data-label', label ); }
							cell.classList.add( 'is-ship-updated' );
							setTimeout( function () { cell.classList.remove( 'is-ship-updated' ); }, 1500 );
						}
					}
					// 🔁 47.2 — LA BRANCHE « résultat de l'envoi » A ÉTÉ RETIRÉE.
					// Elle commentait un envoi déclenché par le changement de
					// statut ; ce déclenchement n'existe plus. La garder aurait
					// été du code mort qui décrit un mécanisme supprimé — et sa
					// branche `disabled` annonçait « Envoi automatique
					// désactivé », un réglage qui n'existe plus non plus.
					var msg = res.data.message || 'OK';
					if ( res.data.tracking ) {
						msg += ' — ' + ( CD.i18nShipped || 'Colis créé' ) + ' : ' + res.data.tracking;
					}
					toast( msg, false );
				} else {
					toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
				}
			} )
			.catch( function () {
				widget.classList.remove( 'is-saving' );
				toast( netErr, true );
			} );
	}

	/* Commandes : sélection multiple + suppression groupée */
	function bindOrderBulkDelete() {
		var master = document.querySelector( '.aod-cd-ordercheck-all' );
		var bar    = document.getElementById( 'aod-cd-order-bulk' );
		var table  = document.querySelector( '.aod-cd-table' );
		if ( ! table ) { return; }

		function boxes() {
			return Array.prototype.slice.call( table.querySelectorAll( '.aod-cd-ordercheck' ) );
		}
		function checked() {
			return boxes().filter( function ( c ) { return c.checked; } );
		}

		// Met à jour la barre d'action (visible + compteur) et l'état du « tout cocher ».
		function sync() {
			var all = boxes();
			var sel = checked();
			if ( bar ) {
				// 45.2 — la barre ne se masque plus, elle s'ARME. Ce qui protégeait
				// d'une action à vide n'était pas l'invisibilité du bouton mais
				// l'impossibilité d'agir : c'est désormais `disabled` qui la porte.
				var vide = sel.length === 0;
				bar.classList.toggle( 'is-vide', vide );
				bar.querySelectorAll( '.aod-cd-bulk-act' ).forEach( function ( b ) {
					b.disabled = vide;
				} );
				// La case « écraser » (43.5) ne survit JAMAIS à une sélection : un
				// mode destructif mémorisé serait précisément le « défaut caché »
				// que la décision D1 proscrit. On la réarme dès que la sélection se
				// vide — le déclencheur est inchangé, seule la façon de le nommer
				// a bougé (`bar.hidden` était devenu faux par construction).
				if ( vide ) {
					var ow = bar.querySelector( '.aod-cd-order-bulk-overwrite' );
					if ( ow ) { ow.checked = false; }
				}
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

		// Délégation : cocher/décocher une ligne.
		table.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.classList.contains( 'aod-cd-ordercheck' ) ) { sync(); }
		} );

		// Suppression groupée.
		var delBtn = bar ? bar.querySelector( '.aod-cd-order-bulk-del' ) : null;
		if ( delBtn ) {
			delBtn.addEventListener( 'click', function () {
				var sel = checked();
				if ( ! sel.length ) { return; }
				var ids = sel.map( function ( c ) { return c.value; } );
				var msg = ( CD.i18nOrderBulkDelConfirm || 'Supprimer %d commande(s) ?' ).replace( '%d', ids.length );
				confirmModal( { message: msg, danger: true } ).then( function ( ok ) {
				if ( ! ok ) { return; }

				delBtn.disabled = true;
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_delete_orders' );
				body.append( 'nonce', CD.nonce );
				ids.forEach( function ( id ) { body.append( 'order_ids[]', id ); } );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						delBtn.disabled = false;
						if ( res && res.success ) {
							( res.data.deleted || [] ).forEach( function ( id ) {
								var cb = table.querySelector( '.aod-cd-ordercheck[value="' + id + '"]' );
								var row = cb ? cb.closest( 'tr' ) : null;
								if ( row ) { row.remove(); }
							} );
							if ( master ) { master.checked = false; master.indeterminate = false; }
							sync();
							toast( res.data.message || CD.i18nDeleted || 'Supprimé', false );
							// Plus aucune ligne ? On recharge pour afficher l'état vide / la pagination.
							if ( ! table.querySelectorAll( '.aod-cd-ordercheck' ).length ) {
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

		// Rattrapage groupé (42.5) : « Assigner à… ».
		var assignSelect = bar ? bar.querySelector( '.aod-cd-order-bulk-assign-select' ) : null;
		var assignBtn    = bar ? bar.querySelector( '.aod-cd-order-bulk-assign' ) : null;
		if ( assignBtn && assignSelect ) {
			assignBtn.addEventListener( 'click', function () {
				var sel = checked();
				if ( ! sel.length ) { return; }
				var confirmerId = assignSelect.value;
				if ( ! confirmerId ) { return; }
				var ids = sel.map( function ( c ) { return c.value; } );

				// Écrasement explicite (43.5, D1). La case est décochée par défaut
				// et réarmée à chaque sélection vidée (cf. sync()).
				var owBox     = bar ? bar.querySelector( '.aod-cd-order-bulk-overwrite' ) : null;
				var overwrite = !! ( owBox && owBox.checked );

				// Combien de commandes du lot sont DÉJÀ assignées ? Le DOM le dit :
				// la cellule « Assigné à » n'est rendue que pour le gérant — donc
				// toujours présente ici — et porte un nom (`.aod-cd-assigned-name`)
				// ou le tiret sourd.
				var dejaN = overwrite ? sel.filter( function ( c ) {
					var r = c.closest( 'tr' );
					return !! ( r && r.querySelector( '.aod-cd-cell-assigned .aod-cd-assigned-name' ) );
				} ).length : 0;

				// 🚨 CONFIRMATION SEULEMENT S'IL Y A QUELQUE CHOSE À ÉCRASER (43.5) :
				// cocher la case sur un lot entièrement libre est un geste inoffensif,
				// et ajouter une friction inutile pousserait à cliquer « oui » sans
				// lire quand elle compte vraiment. Le comportement est INCHANGÉ par
				// la 47.7 — seule la fenêtre change.
				// ⚠️ UN SEUL chemin de code : promesse déjà résolue quand il n'y a
				// rien à demander, plutôt qu'un `if` qui dupliquerait les ~45 lignes
				// de la suite. Celle-ci n'est PAS déplacée — elle reste où elle
				// était, à l'indentation près : le diff se relit donc ligne à ligne,
				// ce qu'une extraction en fonction nommée aurait justement empêché
				// ici (le corps capture `sel`, `ids`, `confirmerId`, `overwrite`,
				// `assignBtn`, `master`, `table` et `sync` — sept liaisons à
				// repasser en paramètres, pour zéro gain de lisibilité).
				var demande = dejaN
					? confirmModal( {
							message: ( CD.i18nBulkAssignOverwriteConfirm || '%d commande(s) déjà assignée(s) seront réassignées. Continuer ?' ).replace( '%d', dejaN ),
						danger: true
					} )
					: Promise.resolve( true );

				demande.then( function ( ok ) {
				if ( ! ok ) { return; }

				assignBtn.disabled = true;
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_orders_bulk_assign' );
				body.append( 'nonce', CD.nonce );
				body.append( 'confirmer_id', confirmerId );
				if ( overwrite ) { body.append( 'overwrite', '1' ); }
				ids.forEach( function ( id ) { body.append( 'order_ids[]', id ); } );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						assignBtn.disabled = false;
						if ( res && res.success ) {
							// 🚨 Piège levé en 43.5 : ne retirer une ligne que si
							// elle n'a PLUS SA PLACE. Dans la vue « Non assignées »,
							// une commande qu'on vient d'assigner sort du filtre —
							// la retirer est juste (comportement 42.5). Dans la
							// liste COMPLÈTE — la seule où l'écrasement sert — elle
							// y reste : la faire disparaître donnerait au gérant
							// l'impression de l'avoir perdue. On recharge alors,
							// plutôt que de rafraîchir la cellule « Assigné à », que
							// ce JS ne met pas à jour (constat établi en 43.4).
							var vueNonAssignees = !! document.querySelector( '.aod-cd-unassigned-toggle.is-active' );
							var touchees = ( res.data.assigned || [] ).concat( res.data.reassigned || [] );

							if ( vueNonAssignees ) {
								touchees.forEach( function ( id ) {
									var cb = table.querySelector( '.aod-cd-ordercheck[value="' + id + '"]' );
									var row = cb ? cb.closest( 'tr' ) : null;
									if ( row ) { row.remove(); }
								} );
								if ( master ) { master.checked = false; master.indeterminate = false; }
								sync();
							}

							var msg = res.data.message || 'OK';
							if ( res.data.skipped ) {
								msg += ' ' + ( CD.i18nBulkAssignSkipped || '(%d déjà assignée(s), ignorée(s))' ).replace( '%d', res.data.skipped );
							}
							toast( msg, false );

							if ( ! vueNonAssignees ) {
								// Liste complète : les lignes restent, mais leur
								// colonne « Assigné à » est périmée → rechargement.
								setTimeout( function () { window.location.reload(); }, 900 );
							} else if ( ! table.querySelectorAll( '.aod-cd-ordercheck' ).length ) {
								setTimeout( function () { window.location.reload(); }, 900 );
							}
						} else {
							toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
						}
					} )
					.catch( function () { assignBtn.disabled = false; toast( netErr, true ); } );
				} ); // fin de la continuation confirmModal (47.7)
			} );
		}

		// Routage groupé (47.1) : « Transporteur… » + « Router ».
		var carrierSelect = bar ? bar.querySelector( '.aod-cd-order-bulk-carrier-select' ) : null;
		var carrierBtn    = bar ? bar.querySelector( '.aod-cd-order-bulk-carrier' ) : null;
		if ( carrierBtn && carrierSelect ) {
			carrierBtn.addEventListener( 'click', function () {
				var sel = checked();
				if ( ! sel.length ) { return; }
				var carrierId = carrierSelect.value;
				if ( ! carrierId ) { return; }
				var ids = sel.map( function ( c ) { return c.value; } );

				carrierBtn.disabled = true;
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_orders_bulk_carrier' );
				body.append( 'nonce', CD.nonce );
				body.append( 'carrier_id', carrierId );
				ids.forEach( function ( id ) { body.append( 'order_ids[]', id ); } );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						carrierBtn.disabled = false;
						if ( res && res.success ) {
							// Les lignes RESTENT (aucun filtre ne porte sur le
							// transporteur : rien ne sort de la vue). On met donc
							// à jour la cellule sur place plutôt que de recharger
							// — le gérant garde sa sélection et son défilement.
							( res.data.applied || [] ).forEach( function ( id ) {
								var cb  = table.querySelector( '.aod-cd-ordercheck[value="' + id + '"]' );
								var row = cb ? cb.closest( 'tr' ) : null;
								var w   = row ? row.querySelector( '.aod-cd-carriersel' ) : null;
								if ( ! w ) { return; }
								w.dataset.value = carrierId;
								w.classList.remove( 'is-inherited' );
								var t = w.querySelector( '.aod-cd-carriersel-btn .aod-cd-carriersel-text' );
								if ( t && res.data.label ) { t.textContent = res.data.label; }
								w.querySelectorAll( '.aod-cd-carriersel-opt' ).forEach( function ( o ) {
									var on = ( o.dataset.value === carrierId );
									o.classList.toggle( 'is-active', on );
									o.setAttribute( 'aria-selected', on ? 'true' : 'false' );
								} );
								// La cellule « Suivi » repart du serveur, comme sur les
								// chemins unitaires : elle porte `data-note-perdue`, que
								// le changement de transporteur vient d'invalider. Sans
								// ça, l'avertissement « la note ne partira pas » se tait
								// (ou crie à tort) sur toutes les lignes du lot.
								majCelluleSuivi( id, ( res.data.cells || {} )[ id ] );
							} );

							var msg = res.data.message || 'OK';
							if ( res.data.skipped ) {
								msg += ' ' + ( CD.i18nBulkCarrierSkipped || '(%d ignorée(s) : déjà expédiée(s) ou hors périmètre)' ).replace( '%d', res.data.skipped );
							}
							toast( msg, false );
						} else {
							toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
						}
					} )
					.catch( function () { carrierBtn.disabled = false; toast( netErr, true ); } );
			} );
		}

		// Envoi groupé (47.2) : récapitulatif NOMMÉ, puis boucle séquentielle.
		var shipBtn = bar ? bar.querySelector( '.aod-cd-order-bulk-ship' ) : null;
		if ( shipBtn ) {
			shipBtn.addEventListener( 'click', function () {
				var sel = checked();
				if ( ! sel.length ) { return; }

				// Plafond de SÉLECTION (pas de requête) — le même nombre que le
				// serveur, lu depuis lui. Refus DIT, jamais une troncature muette.
				var max = parseInt( CD.maxLotEnvoi, 10 ) || 50;
				if ( sel.length > max ) {
					toast(
						( CD.i18nShipTooMany || 'Sélection trop grande : %1$d commandes pour un maximum de %2$d.' )
							.replace( '%1$d', String( sel.length ) ).replace( '%2$d', String( max ) ),
						true
					);
					return;
				}

				var lignes  = sel.map( ligneEnvoi ).filter( Boolean );
				var partent = lignes.filter( function ( l ) { return l.eligible; } );
				var ecartes = lignes.filter( function ( l ) { return ! l.eligible; } );

				if ( ! partent.length ) {
					toast( CD.i18nShipNothing || 'Aucune de ces commandes n’est à envoyer.', true );
					return;
				}

				fenetreEnvoi( partent, ecartes.length, function () {
					shipBtn.disabled = true;
					lancerLot( partent, bar, shipBtn );
				} );
			} );
		}

		sync();
	}

	// Fenêtre de confirmation d'envoi — extraite du bouton groupé en 47.3 pour que
	// le geste UNITAIRE puisse s'en servir quand une note serait perdue.
	//
	// Le récapitulatif NOMME le transporteur de chaque commande : c'est tout l'objet
	// de la fenêtre (D4 de A.23). Un « envoyer 7 commandes ? » sans destination ne
	// serait pas une confirmation, juste une friction.
	//
	// @param {Array}    lignes   Commandes qui partent.
	// @param {number}   ecartes  Nombre de commandes écartées (0 pour le geste unitaire).
	// @param {Function} onGo     Appelée après fermeture, si l'utilisateur confirme.
	function fenetreEnvoi( lignes, ecartes, onGo ) {
		var html = '<p><strong>' +
			( CD.i18nShipConfirmIntro || '%d commande(s) seront envoyées.' ).replace( '%d', String( lignes.length ) ) +
			'</strong></p><ul class="aod-cd-ship-lot-liste">';
		lignes.forEach( function ( l ) {
			var t = document.createElement( 'li' );
			t.textContent = l.numero + ' · ' + l.client + ' → ' + ( l.transporteur || ( CD.i18nShipNoCarrier || 'Par défaut' ) );
			html += t.outerHTML;
		} );
		html += '</ul>';

		// 🚨 47.3 — L'AVERTISSEMENT D3, ET IL EST LA RAISON D'ÊTRE DE CETTE FENÊTRE
		// POUR UNE SEULE COMMANDE. Yalidine, Yalitec et E-com Delivery n'ont AUCUN
		// champ de note dans leur charge utile. Se taire ferait croire au confirmateur
		// qu'il a transmis une consigne que le livreur ne verra jamais — la définition
		// même du défaut muet que ce dépôt chasse depuis la Phase 26.
		//
		// ⚠️ On ne DÉCIDE rien ici : `notePerdue` est calculé par le serveur, seul à
		// connaître le transporteur résolu de chaque commande (cf. `ship_cell_html()`).
		var perdues = lignes.filter( function ( l ) { return l.notePerdue; } );
		if ( perdues.length ) {
			var w = document.createElement( 'p' );
			w.className = 'aod-cd-ship-note-alerte';
			w.textContent = ( CD.i18nShipNoteLost || '%d commande(s) portent une note que ce transporteur ne recevra pas.' )
				.replace( '%d', String( perdues.length ) );
			html += w.outerHTML;
			var ul = '<ul class="aod-cd-ship-lot-liste">';
			perdues.forEach( function ( l ) {
				var t = document.createElement( 'li' );
				t.textContent = l.numero + ' → ' + ( l.transporteur || ( CD.i18nShipNoCarrier || 'Par défaut' ) );
				ul += t.outerHTML;
			} );
			html += ul + '</ul>';
		}

		if ( ecartes ) {
			var e = document.createElement( 'p' );
			e.className = 'aod-cd-muted';
			e.textContent = ( CD.i18nShipExcluded || '%d commande(s) écartée(s) : déjà expédiée(s) ou rien à envoyer.' )
				.replace( '%d', String( ecartes ) );
			html += e.outerHTML;
		}
		html += '<div class="aod-cd-modal-actions">' +
			'<button type="button" class="aod-cd-btn" data-cancel></button>' +
			'<button type="button" class="aod-cd-btn aod-cd-btn-primary" data-go></button>' +
			'</div>';

		var confirmation = openOverlayModal( {
			title:    CD.i18nShipConfirmTitle || 'Envoi au transporteur',
			bodyHtml: html,
			initialFocus: '[data-go]',
		} );
		// textContent et non innerHTML : ces libellés viennent de la base
		// de traduction, on ne les injecte jamais comme du markup.
		confirmation.overlay.querySelector( '[data-cancel]:not(.aod-cd-modal-backdrop):not(.aod-cd-modal-x)' ).textContent = CD.i18nShipCancel || 'Annuler';
		confirmation.overlay.querySelector( '[data-go]' ).textContent = CD.i18nShipGo || 'Envoyer';
		confirmation.overlay.querySelector( '[data-go]' ).addEventListener( 'click', function () {
			confirmation.close();
			onGo();
		} );
	}

	// Bouton « Envoyer » d'une ligne (47.2) — le geste unitaire.
	//
	// 🚨 DÉLÉGATION, ET PAS UN ÉCOUTEUR PAR BOUTON. La première version câblait
	// chaque `.aod-cd-ship-send` au chargement — et le bouton ne répondait plus
	// après un changement de statut. Mesuré au navigateur : `applyStatus()`
	// remplace la cellule « Suivi » par `innerHTML` (pour que l'icône suive
	// l'état), ce qui DÉTRUIT le bouton et son écouteur avec lui. Le bouton
	// restait à l'écran, cliquable, et ne faisait plus rien — le pire des états.
	//
	// C'est le même piège que la 39.2 avait rencontré sur le sélecteur de statut,
	// et résolu autrement (extraire `wireStatusWidget()` pour le rappeler sur le
	// widget injecté). Ici la délégation est plus sûre : elle vaut pour TOUT
	// re-rendu, y compris ceux qu'on n'a pas encore écrits.
	function bindShipSend() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest ? e.target.closest( '.aod-cd-ship-send' ) : null;
			if ( ! btn || btn.disabled ) { return; }
			var id = btn.dataset.order;

			function partir() {
				btn.disabled = true;
				// ⚠️ `innerHTML`, PAS `textContent` : le bouton contient une icône SVG
				// en ligne (le camion, dont la COULEUR porte l'état — gris à envoyer,
				// rouge en échec). `textContent` ne lisait que le libellé, et l'écrire
				// SUPPRIMAIT le nœud <svg>. Sur le seul chemin où le bouton survit —
				// une panne réseau, donc pas de re-rendu de la cellule —, il revenait
				// en texte nu, sans camion ni code couleur, précisément sur la ligne
				// qui vient d'échouer. *Sauvegarder un contenu riche dans une variable
				// texte, c'est décider de le perdre.*
				var avant = btn.innerHTML;
				btn.textContent = CD.i18nShipSending || 'Envoi…';
				envoyerUne( id ).then( function ( r ) {
					// La cellule vient d'être re-rendue : le bouton courant peut avoir
					// disparu du DOM (état « expédiée »). On ne le remet en état que
					// s'il y est encore.
					if ( document.body.contains( btn ) ) {
						btn.disabled = false;
						btn.innerHTML = avant;
					}
					toast( r.message || ( r.ok ? 'OK' : CD.i18nErr || 'Erreur.' ), ! r.ok );
				} );
			}

			// 🚨 47.3 — LE GESTE UNITAIRE PASSE PAR LA FENÊTRE, ET SEULEMENT QUAND UNE
			// NOTE SERAIT PERDUE. Sans cela, l'avertissement D3 ne vivrait que dans le
			// lot — or un confirmateur n'a AUCUNE case à cocher (la colonne appartient
			// à `$can_delete`, borne assumée de la 47.1) : il n'aurait jamais appris que
			// sa consigne n'est pas partie. La friction n'apparaît que dans le cas qui
			// la justifie ; partout ailleurs le bouton envoie directement, comme en 47.2.
			if ( '1' === btn.dataset.notePerdue ) {
				var ligne = ligneDepuisRang( btn.closest( 'tr' ), id );
				if ( ligne ) {
					fenetreEnvoi( [ ligne ], 0, partir );
					return;
				}
			}
			partir();
		} );
	}

	// Envoi GROUPÉ (47.2) : fenêtre de confirmation puis boucle SÉQUENTIELLE.
	//
	// ⚠️ Séquentielle, jamais Promise.all() : N requêtes simultanées ne vont pas
	// plus vite (le verrou 31.4 les sérialise de toute façon) mais occupent N
	// workers PHP — sur un mutualisé DZ, c'est l'auto-DoS de la 32.4.
	//
	// ⚠️ COMMENTAIRE EN `//` ET NON EN `/* */`, et ce n'est pas un caprice de
	// style : `test-icones.php` ne saute que les lignes qui COMMENCENT par un
	// marqueur de commentaire. Dans un bloc `/* … */` à la mode de ce fichier,
	// les lignes de continuation commencent par du texte — un ⚠️ y est donc lu
	// comme du chrome, et le cliquet rougit. Il a rougi.
	function lancerLot( lignes, bar, btnLot ) {
		var faits = 0;
		var ok    = 0;
		var ko    = 0;
		var stop  = false;
		var fini  = false;

		var corps =
			'<p class="aod-cd-ship-lot-etat" role="status" aria-live="polite">' +
				( CD.i18nShipProgress || 'Envoi %1$d / %2$d…' ).replace( '%1$d', '0' ).replace( '%2$d', String( lignes.length ) ) +
			'</p>' +
			'<div class="aod-cd-ship-lot-jauge"><span style="width:0%"></span></div>' +
			'<ul class="aod-cd-ship-lot-liste"></ul>' +
			'<div class="aod-cd-modal-actions">' +
				'<button type="button" class="aod-cd-btn" data-arret>' + ( CD.i18nShipStop || 'Arrêter' ) + '</button>' +
			'</div>';

		var modal = openOverlayModal( {
			title:    CD.i18nShipConfirmTitle || 'Envoi au transporteur',
			bodyHtml: corps,
			// Pas de fermeture au clic extérieur ni à Échap tant que ça tourne :
			// un lot interrompu à mi-course sans compte rendu, c'est précisément
			// ce qu'on cherche à ne plus faire.
			//
			// 🚨 MAIS UNE FOIS FINI, ILS DOIVENT FERMER. Ces deux rappels écrasaient
			// ceux du squelette (qui, eux, fermaient) et ne les rendaient jamais :
			// la croix ✕, le fond et Échap restaient inertes APRÈS la fin du lot,
			// alors que `document.body.style.overflow` valait toujours `'hidden'`.
			// Un gérant qui ne repère pas le bouton « Fermer » se retrouve devant un
			// tableau de bord figé, sous une fenêtre qui refuse tous les gestes de
			// fermeture qu'il connaît. *Reprendre un rappel au squelette, c'est en
			// reprendre la RESPONSABILITÉ — ici, celle de fermer.*
			onCancel: function () { if ( fini ) { modal.close(); return; } stop = true; },
			onEscape: function () { if ( fini ) { modal.close(); return; } stop = true; },
		} );

		var etat  = modal.overlay.querySelector( '.aod-cd-ship-lot-etat' );
		var jauge = modal.overlay.querySelector( '.aod-cd-ship-lot-jauge span' );
		var liste = modal.overlay.querySelector( '.aod-cd-ship-lot-liste' );
		var arret = modal.overlay.querySelector( '[data-arret]' );
		arret.addEventListener( 'click', function () {
			stop = true;
			arret.disabled = true;
		} );

		function journal( ligne, r ) {
			var li = document.createElement( 'li' );
			li.className = r.ok ? 'is-ok' : 'is-ko';
			li.textContent = ligne.numero + ' — ' + ( r.message || ( r.ok ? 'OK' : '' ) );
			liste.appendChild( li );
			liste.scrollTop = liste.scrollHeight;
		}

		function suivante() {
			if ( stop || faits >= lignes.length ) { return terminer(); }
			var ligne = lignes[ faits ];
			return envoyerUne( ligne.id ).then( function ( r ) {
				faits++;
				if ( r.ok ) { ok++; } else { ko++; }
				journal( ligne, r );
				etat.textContent = ( CD.i18nShipProgress || 'Envoi %1$d / %2$d…' )
					.replace( '%1$d', String( faits ) ).replace( '%2$d', String( lignes.length ) );
				jauge.style.width = Math.round( ( faits / lignes.length ) * 100 ) + '%';
				return suivante();
			} );
		}

		function terminer() {
			fini = true;
			arret.textContent = CD.i18nShipClose || 'Fermer';
			arret.disabled = false;
			arret.onclick = function () { modal.close(); };
			var msg = ( CD.i18nShipDone || '%1$d envoyée(s), %2$d en échec.' )
				.replace( '%1$d', String( ok ) ).replace( '%2$d', String( ko ) );
			// 🚨 CE QUI N'A PAS ÉTÉ TENTÉ SE DIT. Un lot arrêté à la 5ᵉ de 30 annonçait
			// « 5 envoyée(s), 0 en échec » — vrai, et parfaitement trompeur : le gérant
			// en conclut que tout est parti. *Un compte rendu qui ne compte que ce
			// qu'il a fait laisse croire qu'il a tout fait.*
			var restantes = lignes.length - faits;
			if ( restantes > 0 ) {
				msg += ' ' + ( CD.i18nShipAbandoned || '%d commande(s) n’ont pas été tentées (lot arrêté).' )
					.replace( '%d', String( restantes ) );
			}
			etat.textContent = msg;
			if ( btnLot ) { btnLot.disabled = false; }
			toast( msg, ko > 0 );
		}

		suivante();
	}

	/* Modale de détail de commande */
	function bindOrderDetail() {
		// 47.4 — la modale `#aod-cd-modal` a UN SEUL propriétaire, dans la coque
		// (`core.sharedModal`, cf. son commentaire). Ce module ne fait plus que la
		// consommer : ouverture, fermeture, `Échap` et piège de focus y vivent.
		// 🚨 Le fond NE FERME PAS cette modale-là : on y SAISIT (édition des
		// articles, du client, de la note du confirmateur), et un clic à côté
		// jetait le travail en cours sans confirmation ni retour en arrière.
		var partagee = core.sharedModal();
		if ( ! partagee ) { return; }
		var titleEl = partagee.titleEl;
		var bodyEl  = partagee.bodyEl;

		function open() {
			partagee.open( { backdropCloses: false } );
		}
		function close() {
			partagee.close();
		}

		// Ouvre la modale sur une commande par son ID (fetch → inject → init).
		// Exposé sur le bus partagé window.aodCdCore pour que la conversion d'un
		// panier abandonné (section-abandoned.js, étape 39.2) ouvre la commande
		// créée sans dupliquer cette mécanique. section-orders est le propriétaire
		// de la modale de commande — c'est donc lui qui l'expose.
		function openOrderModal( orderId ) {
			titleEl.textContent = CD.i18nTrackLoading || '…';
			bodyEl.innerHTML = skeleton( 6 );
			open();

			var body = new URLSearchParams();
			body.append( 'action', 'aod_cd_order_detail' );
			body.append( 'nonce', CD.nonce );
			body.append( 'order_id', orderId );

			fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) {
						titleEl.textContent = res.data.title || '';
						bodyEl.innerHTML = res.data.html || '';
						initOrderEdit( bodyEl, titleEl );
					} else {
						close();
						toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
					}
				} )
				.catch( function () { close(); toast( netErr, true ); } );
		}
		core.openOrderModal = openOrderModal;

		document.querySelectorAll( '.aod-cd-order-detail' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () { openOrderModal( btn.dataset.order ); } );
		} );
	}

	/* Édition des infos client / livraison dans la modale détail */
	function initOrderEdit( root, titleEl ) {
		// Statut choisissable dans la modale (39.2) : le widget est injecté par
		// AJAX, donc bindStatus (DOMContentLoaded) ne l'a pas vu — on le câble ici,
		// avant tout retour anticipé (il est indépendant du formulaire d'édition).
		var statusWidget = root.querySelector( '.aod-cd-statussel' );
		if ( statusWidget ) { wireStatusWidget( statusWidget ); }

		// Assignation (42.4) : même raison — injecté par AJAX, GÉRANT uniquement
		// (absent du DOM pour un confirmateur).
		var assignWidget = root.querySelector( '.aod-cd-assignsel' );
		if ( assignWidget ) { wireAssignWidget( assignWidget ); }

		var form     = root.querySelector( '.aod-cd-od-edit' );
		var toggle   = root.querySelector( '.aod-cd-od-edit-toggle' );
		var readview = root.querySelector( '.aod-cd-od-readview' );
		if ( ! form || ! toggle || ! readview ) { return; }

		var cancel = form.querySelector( '.aod-cd-od-edit-cancel' );
		var wilSel = form.querySelector( '.aod-cd-od-wilaya' );
		var comSel = form.querySelector( '.aod-cd-od-commune' );
		var mapEl  = form.querySelector( '.aod-cd-od-wilmap' );
		var msg    = form.querySelector( '.aod-cd-form-msg' );
		var map    = {};
		if ( mapEl ) { try { map = JSON.parse( mapEl.textContent || '{}' ); } catch ( e ) { map = {}; } }

		function showForm( on ) {
			form.hidden = ! on;
			readview.hidden = on;
			toggle.hidden = on;
		}

		toggle.addEventListener( 'click', function () { showForm( true ); } );
		if ( cancel ) { cancel.addEventListener( 'click', function () { showForm( false ); } ); }

		if ( wilSel && comSel ) {
			wilSel.addEventListener( 'change', function () {
				var list = map[ parseInt( wilSel.value, 10 ) ] || [];
				comSel.innerHTML = '<option value="">—</option>';
				list.forEach( function ( c ) {
					var o = document.createElement( 'option' );
					o.value = c.v;
					o.textContent = c.l;
					comSel.appendChild( o );
				} );
			} );
		}

		// Total à payer : dès que le gérant y touche, on cesse le calcul auto (drapeau).
		var totalInput    = form.querySelector( '.aod-cd-od-total' );
		var totalOverride = form.querySelector( '.aod-cd-od-total-override' );
		if ( totalInput && totalOverride ) {
			totalInput.addEventListener( 'input', function () { totalOverride.value = '1'; } );
		}

		// Ajout d'un article : construit une nouvelle ligne à partir du catalogue.
		var catEl   = form.querySelector( '.aod-cd-od-catalog' );
		var catalog = {};
		if ( catEl ) { try { catalog = JSON.parse( catEl.textContent || '{}' ); } catch ( e ) { catalog = {}; } }
		var addPick  = form.querySelector( '.aod-cd-od-addpick' );
		var addBtn   = form.querySelector( '.aod-cd-od-additem-btn' );
		var itemsBox = form.querySelector( '.aod-cd-od-items' );

		function buildNewItemRow( pid, prod ) {
			var row = document.createElement( 'div' );
			row.className = 'aod-cd-od-item is-new';
			row.dataset.newProduct = pid;

			var head = document.createElement( 'div' );
			head.className = 'aod-cd-od-item-head';
			var strong = document.createElement( 'strong' );
			strong.textContent = prod.name;
			var del = document.createElement( 'button' );
			del.type = 'button';
			del.className = 'aod-cd-od-itemdel';
			del.textContent = '× ' + ( CD.i18nItemRemove || 'Retirer' );
			del.addEventListener( 'click', function () { row.parentNode.removeChild( row ); } );
			head.appendChild( strong );
			head.appendChild( del );
			row.appendChild( head );

			var fields = document.createElement( 'div' );
			fields.className = 'aod-cd-od-item-fields';

			( prod.options || [] ).forEach( function ( sec ) {
				var lab = document.createElement( 'label' );
				lab.className = 'aod-cd-field';
				var span = document.createElement( 'span' );
				span.className = 'aod-cd-label';
				span.textContent = sec.label;
				var sel = document.createElement( 'select' );
				sel.className = 'aod-cd-od-itemopt';
				sel.dataset.label = sec.label;
				( sec.values || [] ).forEach( function ( v ) {
					var o = document.createElement( 'option' );
					o.value = v.name;
					o.textContent = ( v.price > 0 ) ? ( v.name + ' (+' + v.price + ')' ) : v.name;
					sel.appendChild( o );
				} );
				lab.appendChild( span );
				lab.appendChild( sel );
				fields.appendChild( lab );
			} );

			var qlab = document.createElement( 'label' );
			qlab.className = 'aod-cd-field aod-cd-od-item-qty';
			var qspan = document.createElement( 'span' );
			qspan.className = 'aod-cd-label';
			qspan.textContent = CD.i18nItemQty || 'Quantité';
			var qty = document.createElement( 'input' );
			qty.type = 'number';
			qty.min = '1';
			qty.step = '1';
			qty.className = 'aod-cd-od-itemqty';
			qty.value = '1';
			qlab.appendChild( qspan );
			qlab.appendChild( qty );
			fields.appendChild( qlab );

			row.appendChild( fields );
			return row;
		}

		if ( addBtn && addPick && itemsBox ) {
			addBtn.addEventListener( 'click', function () {
				var pid = addPick.value;
				if ( ! pid || ! catalog[ pid ] ) { return; }
				itemsBox.appendChild( buildNewItemRow( pid, catalog[ pid ] ) );
				addPick.value = '';
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = form.querySelector( 'button[type="submit"]' );
			if ( btn ) { btn.disabled = true; }
			if ( msg ) { msg.textContent = ''; }

			var body = new URLSearchParams();
			body.append( 'action', 'aod_cd_order_save_info' );
			body.append( 'nonce', CD.nonce );
			body.append( 'order_id', form.dataset.order );
			// 47.3 — « note » rejoint la collecte : la note au livreur s'enregistre
			// avec le reste du formulaire, sans requête ni handler propre.
			[ 'name', 'phone', 'address', 'wilaya', 'commune', 'delivery_type', 'note' ].forEach( function ( n ) {
				var el = form.querySelector( '[name="' + n + '"]' );
				body.append( n, el ? el.value : '' );
			} );

			// Articles : lignes existantes (variantes + quantité + retrait) et nouveaux
			// articles ajoutés par le gérant (data-new-product).
			var items = [];
			form.querySelectorAll( '.aod-cd-od-item' ).forEach( function ( row ) {
				var opts = {};
				row.querySelectorAll( '.aod-cd-od-itemopt' ).forEach( function ( sel ) {
					opts[ sel.dataset.label ] = sel.value;
				} );
				var qtyEl = row.querySelector( '.aod-cd-od-itemqty' );
				var qty   = qtyEl ? qtyEl.value : '1';
				if ( row.dataset.newProduct ) {
					items.push( { new_product: row.dataset.newProduct, qty: qty, opts: opts } );
				} else {
					var rmEl = row.querySelector( '.aod-cd-od-itemremove' );
					items.push( {
						id:     row.dataset.item,
						qty:    qty,
						remove: rmEl && rmEl.checked ? 1 : 0,
						opts:   opts
					} );
				}
			} );
			if ( items.length ) {
				body.append( 'items_json', JSON.stringify( items ) );
			}

			// Total forcé manuellement (uniquement si le champ a été modifié).
			if ( totalInput && totalOverride && '1' === totalOverride.value ) {
				body.append( 'total_override', '1' );
				body.append( 'order_total', totalInput.value );
			}

			fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( btn ) { btn.disabled = false; }
					if ( res && res.success ) {
						if ( titleEl && res.data.title ) { titleEl.textContent = res.data.title; }
						root.innerHTML = res.data.html || '';
						initOrderEdit( root, titleEl );
						// 47.3 — la note vient peut-être de changer : la cellule
						// « Suivi » de la ligne se remet à jour pour que
						// `data-note-perdue` reste vrai sans recharger la page.
						majCelluleSuivi( form.dataset.order, res.data.ship_cell );
						toast( res.data.message || 'OK', false );
					} else {
						var m = ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.';
						if ( msg ) { msg.textContent = m; }
						toast( m, true );
					}
				} )
				.catch( function () {
					if ( btn ) { btn.disabled = false; }
					toast( netErr, true );
				} );
		} );
	}

	// 47.3 — `bindOrderNote` / `submitOrderNote` ont été SUPPRIMÉS.
	//
	// Ils ouvraient une invite depuis un bouton de la colonne « Note » et postaient
	// vers `aod_cd_order_note`, qui écrivait dans l'HISTORIQUE WooCommerce. Le
	// livreur n'a jamais rien lu de cet historique. La note est désormais un champ
	// du formulaire d'édition : elle part avec `aod_cd_order_save_info`, sans
	// binder ni requête propre.
	//
	// ⚠️ `textPromptModal` est partie avec eux (coque `dashboard.js`) : c'était son
	// unique appelant. La 47.7 remplacera 12 `window.confirm` + 1 `window.alert`
	// par `openOverlayModal` — pas par un prompt.

	core.register( 'bindStatus', bindStatus );
	core.register( 'bindOrderDetail', bindOrderDetail );
	core.register( 'bindOrderBulkDelete', bindOrderBulkDelete );
	core.register( 'bindShipSend', bindShipSend );
	/* Note (étape 5.4) : bindFilterDropdown() a été supprimé — le filtre de statut
	   de la page Commandes est désormais un segmented control de liens GET
	   (.aod-cd-seg), sans JavaScript. */
}( window.aodCdCore ) );
