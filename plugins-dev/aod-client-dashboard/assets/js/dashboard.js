/* AOD Client Dashboard — interactions front-end */
( function () {
	'use strict';

	var CD = window.AOD_CD || {};
	var netErr = CD.i18nNetErr || 'Erreur réseau.';

	/* ---- Le canal de retour de /gestion, et son annonce (46.3) --------------
	 *
	 * 🚨 CE TOAST A ÉTÉ MUET POUR LES LECTEURS D'ÉCRAN PENDANT TOUTE SON
	 * EXISTENCE. Il créait un `<div>`, y mettait le texte, puis l'ajoutait au
	 * document : sans `role="status"` ni `aria-live`, rien n'était annoncé. Or
	 * c'est le canal de retour de TOUS les AJAX de l'espace gérant — succès comme
	 * erreurs (SC 4.1.3, niveau AA). Le motif correct existait pourtant 7 fois
	 * ailleurs dans le plugin (Livraison, Pixels, WhatsApp, Page de vente) : c'est
	 * le helper PARTAGÉ qui en était dépourvu, donc le seul dont le manque se
	 * propage partout.
	 *
	 * 🚨 ET L'ORDRE EST LE SUJET, PAS L'ATTRIBUT. Poser `role="status"` sur un
	 * nœud DÉJÀ REMPLI puis l'insérer n'est pas annoncé de façon fiable : la
	 * plupart des lecteurs d'écran n'annoncent que les MUTATIONS d'une région live
	 * déjà présente dans l'arbre. Un correctif qui ajouterait l'attribut sans
	 * changer l'ordre serait vert au grep et muet à l'oreille — d'où une région
	 * UNIQUE et PERMANENTE, créée au DOMContentLoaded, que `toast()` ne fait plus
	 * que remplir.
	 *
	 * Effet de bord voulu : deux messages successifs ne créent plus deux boîtes
	 * concurrentes qui se superposent au même endroit — le second remplace le
	 * premier, et son minuteur est réarmé.
	 *
	 * ⚠️ `polite` et non `assertive`, y compris pour les erreurs : le message
	 * accompagne une action que l'utilisateur vient lui-même de déclencher.
	 * `assertive` interromprait sa lecture en cours pour lui dire ce qu'il
	 * attendait.                                                              */
	var toastBox   = null;
	var toastTimer = null;

	function toastRegion() {
		if ( toastBox && toastBox.isConnected ) {
			return toastBox;
		}
		toastBox = document.createElement( 'div' );
		toastBox.className = 'aod-cd-toast';
		// `role="status"` implique déjà live=polite + atomic=true ; les deux autres
		// sont écrits explicitement parce que le support historique est inégal, et
		// parce que la règle se lit alors sans connaître la table des rôles ARIA.
		toastBox.setAttribute( 'role', 'status' );
		toastBox.setAttribute( 'aria-live', 'polite' );
		toastBox.setAttribute( 'aria-atomic', 'true' );
		document.body.appendChild( toastBox );
		return toastBox;
	}

	function toast( message, isBad ) {
		var t = toastRegion();
		// La classe est réécrite en entier : `bad` doit disparaître quand le
		// message suivant est un succès, sinon un toast vert hérite du rouge.
		t.className = 'aod-cd-toast' + ( isBad ? ' bad' : '' );
		// Vider puis remplir : deux messages IDENTIQUES à la suite ne produiraient
		// aucune mutation de texte, donc aucune annonce. Le vidage garantit le
		// changement — c'est le seul cas où l'utilisateur clavier perdrait
		// l'information alors que l'écran, lui, la montre.
		t.textContent = '';
		t.textContent = message;
		requestAnimationFrame( function () { t.classList.add( 'show' ); } );
		if ( toastTimer ) {
			clearTimeout( toastTimer );
		}
		toastTimer = setTimeout( function () {
			t.classList.remove( 'show' );
		}, 2600 );
	}

	/* Initialise un binder en l'ISOLANT : une exception dans l'un ne doit jamais
	 * emporter les autres — mais elle ne doit pas non plus disparaître (32.5,
	 * D24) : 19 `catch` au corps vide faisaient qu'un binder cassé ne laissait
	 * NI erreur console NI trace — juste un bouton qui ne répond plus, sans
	 * cause visible. C'est le pendant navigateur de la 31.3 : on ne change rien
	 * au comportement nominal, on rend l'échec visible.
	 * ⚠️ Console et non toast, à DESSEIN : c'est un défaut de développement, pas
	 * un message pour le gérant — et ce motif ne doit JAMAIS être recopié côté
	 * acheteur (le public ne doit voir ni erreur ni détail technique). */
	function init( name, fn ) {
		try {
			fn();
		} catch ( err ) {
			if ( window.console && console.error ) {
				console.error( '[aod-cd] init « ' + name + ' » a échoué :', err );
			}
		}
	}

	/* Échappe le texte injecté dans un innerHTML (contenu de nœud, entre balises). */
	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = String( s == null ? '' : s );
		return d.innerHTML;
	}

	/* Échappe une valeur destinée à un ATTRIBUT entre guillemets doubles (src, href,
	 * placeholder…). Distinct de `esc` : celui-ci échappe AUSSI les guillemets — un
	 * `esc()` basé sur textContent ne le fait pas et serait donc troué en attribut.
	 * Échappeur unique du fichier (remplace les ex-`htmlEsc`/`escapeHtml`/`esc` locaux). */
	function escAttr( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	/* État vide canonique — miroir JS de AOD_CD_Dashboard::empty_state(). */
	// `icon` est un NOM du catalogue PHP (Phase 15), plus un emoji. Le SVG vient
	// de CD.icons, rendu côté serveur : le JS ne fabrique pas de balisage d'icône
	// et ne peut donc pas en inventer une hors liste. Nom absent = pas d'icône,
	// jamais de texte de repli qui trahirait la substitution.
	function emptyState( icon, title, text ) {
		var svg = ( icon && CD.icons && CD.icons[ icon ] ) || '';
		var h = '<div class="aod-cd-empty">';
		if ( svg ) { h += '<span class="aod-cd-empty__ic" aria-hidden="true">' + svg + '</span>'; }
		h += '<p class="aod-cd-empty__title">' + esc( title ) + '</p>';
		if ( text ) { h += '<p class="aod-cd-empty__txt">' + esc( text ) + '</p>'; }
		return h + '</div>';
	}

	/* Squelette de chargement (N barres) — composant .aod-cd-skel. */
	function skeleton( lines ) {
		var h = '<div class="aod-cd-skelwrap">';
		for ( var i = 0; i < ( lines || 4 ); i++ ) {
			h += '<div class="aod-cd-skel"></div>';
		}
		return h + '</div>';
	}

	/* ==========================================================================
	 * Socle d'accessibilité au clavier (étape 5.8)
	 * ========================================================== */

	var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),'
		+ 'select:not([disabled]),textarea:not([disabled]),summary,[tabindex]:not([tabindex="-1"])';

	/* Éléments réellement focalisables DANS un conteneur (les masqués n'en sont pas). */
	function focusables( root ) {
		return Array.prototype.filter.call(
			root.querySelectorAll( FOCUSABLE ),
			function ( el ) {
				// getClientRects() vide ⇒ display:none, [hidden], ou dans un
				// <details> replié : ces éléments ne doivent pas capter le focus.
				return !! el.getClientRects().length;
			}
		);
	}

	/**
	 * Piège de focus d'une modale.
	 *
	 * Sans lui, une modale n'en est pas une : à la tabulation le focus part
	 * DERRIÈRE elle, dans la page masquée, et l'utilisateur au clavier tape à
	 * l'aveugle dans un formulaire qu'il ne voit plus. `aria-modal="true"` ne
	 * suffit pas — c'est une promesse faite aux lecteurs d'écran, pas un
	 * comportement du navigateur.
	 *
	 * Renvoie une fonction `release()` : elle décroche l'écoute ET rend le focus
	 * à l'élément qui a ouvert la modale.
	 *
	 * @param {Element} box     Boîte de dialogue.
	 * @param {Element} initial Élément à focaliser en premier (optionnel).
	 * @return {Function} release
	 */
	function trapFocus( box, initial ) {
		var prev = document.activeElement;

		function onKey( e ) {
			if ( 'Tab' !== e.key ) { return; }
			var f = focusables( box );
			if ( ! f.length ) { e.preventDefault(); return; }
			var first = f[ 0 ];
			var last  = f[ f.length - 1 ];
			// Le focus peut s'être échappé (clic sur le fond, focus() perdu) :
			// on le ramène dans la boîte plutôt que de le laisser derrière.
			if ( ! box.contains( document.activeElement ) ) {
				e.preventDefault();
				( e.shiftKey ? last : first ).focus();
				return;
			}
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
		document.addEventListener( 'keydown', onKey, true );

		var start = initial || focusables( box )[ 0 ] || box;
		if ( start === box && ! box.hasAttribute( 'tabindex' ) ) {
			box.setAttribute( 'tabindex', '-1' );
		}
		// Le contenu peut être injecté juste après l'ouverture : on laisse le DOM
		// se poser avant de viser le focus.
		setTimeout( function () {
			if ( document.contains( start ) ) { start.focus(); }
		}, 30 );

		return function release() {
			document.removeEventListener( 'keydown', onKey, true );
			if ( prev && document.contains( prev ) ) { prev.focus(); }
		};
	}

	/**
	 * Ouvre une modale overlay (backdrop + boîte + piège de focus + Échap + boutons
	 * `[data-cancel]`). Le CORPS (`opts.bodyHtml`, déjà échappé par l'appelant) et le
	 * câblage des boutons d'action sont laissés à l'appelant — cette fonction ne gère
	 * que le scaffold commun (auparavant recopié dans `pickVideo` et `textPromptModal`).
	 *
	 * @param {Object} opts { title, bodyHtml, initialFocus, onCancel, onEscape, onKey }
	 *   - onCancel : appelé au clic sur un `[data-cancel]` (défaut : ferme) ;
	 *   - onEscape : appelé sur Échap (défaut : ferme) ;
	 *   - onKey    : appelé sur les autres touches (ex. Ctrl+Entrée).
	 * @return {Object} { overlay, box, close }
	 */
	function openOverlayModal( opts ) {
		opts = opts || {};
		var overlay = document.createElement( 'div' );
		overlay.className = 'aod-cd-modal';
		overlay.innerHTML =
			'<div class="aod-cd-modal-backdrop" data-cancel></div>' +
			'<div class="aod-cd-modal-box aod-cd-modal-sm" role="dialog" aria-modal="true">' +
				'<header class="aod-cd-modal-head">' +
					'<h2 class="aod-cd-modal-title">' + escAttr( opts.title ) + '</h2>' +
					'<button type="button" class="aod-cd-modal-x" data-cancel aria-label="Fermer">&times;</button>' +
				'</header>' +
				'<div class="aod-cd-modal-body">' + ( opts.bodyHtml || '' ) + '</div>' +
			'</div>';
		document.body.appendChild( overlay );
		document.body.style.overflow = 'hidden';
		var box = overlay.querySelector( '.aod-cd-modal-box' );
		var release = trapFocus( box, opts.initialFocus ? overlay.querySelector( opts.initialFocus ) : null );

		function close() {
			release();
			if ( overlay.parentNode ) { document.body.removeChild( overlay ); }
			document.body.style.overflow = '';
			document.removeEventListener( 'keydown', onKey );
		}
		function onKey( e ) {
			if ( 'Escape' === e.key ) { ( opts.onEscape || close )(); }
			else if ( opts.onKey ) { opts.onKey( e ); }
		}
		document.addEventListener( 'keydown', onKey );
		overlay.querySelectorAll( '[data-cancel]' ).forEach( function ( el ) {
			el.addEventListener( 'click', opts.onCancel || close );
		} );

		return { overlay: overlay, box: box, close: close };
	}

	/**
	 * La question du tableau de bord — remplace `window.confirm` / `alert` (47.7).
	 *
	 * 🚨 POURQUOI ELLE EXISTE. Les 13 dialogues natifs du dépôt sortaient du site :
	 * leurs boutons « OK / Cancel » viennent du NAVIGATEUR, pas de la langue du
	 * gérant — un arabophone lisait une question en arabe sous deux boutons
	 * anglais. C'était le seul endroit de `/gestion` où le bilinguisme s'arrêtait.
	 * Et `window.confirm` GÈLE le fil d'exécution, requêtes en vol comprises.
	 *
	 * 🚨 LE PIÈGE, ET IL EST MORTEL. `window.confirm` est BLOQUANT et rend un
	 * booléen ; ceci rend une PROMESSE. Écrire
	 *     if ( ! confirmModal( … ) ) { return; }
	 * ne se déclenche JAMAIS — une Promise est toujours truthy. Le remplacement
	 * mécanique produirait 13 gardes qui laissent tout passer, c'est-à-dire 13
	 * suppressions sans confirmation, sans qu'aucune erreur ne le dise. Chaque
	 * appelant DOIT passer en continuation : `.then( function ( ok ) { … } )`.
	 *
	 * ⚠️ `okOnly` remplace l'unique `window.alert` (un mot de passe engendré) : un
	 * AVIS, pas une question. Une seconde primitive doublerait le scaffold pour
	 * une différence d'un bouton — la dérive que la Phase 46 a passé huit étapes à
	 * fermer. `detail` rend la valeur dans un `<code>` À PART, SÉLECTIONNABLE :
	 * `window.alert` permettait de la copier, une fenêtre qui l'interdirait serait
	 * une régression d'usage.
	 *
	 * Bâtie SUR `openOverlayModal` : ni le piège de focus, ni `Échap`, ni le fond
	 * ne sont réécrits ici.
	 *
	 * @param {Object} opts { title, message, detail, okLabel, danger, okOnly }
	 * @return {Promise<boolean>} true = confirmé · false = annulé / Échap / fond.
	 */
	function confirmModal( opts ) {
		opts = opts || {};
		return new Promise( function ( resolve ) {
			var corps = '<p class="aod-cd-modal-msg">' + esc( opts.message || '' ) + '</p>';
			if ( opts.detail ) {
				// `<code>` et pas `<p>` : la valeur doit rester lisible ET copiable.
				corps += '<p class="aod-cd-modal-msg"><code>' + esc( opts.detail ) + '</code></p>';
			}
			corps += '<div class="aod-cd-modal-actions">';
			if ( ! opts.okOnly ) {
				corps += '<button type="button" class="aod-cd-btn" data-cancel>' + escAttr( CD.i18nCancel || 'Annuler' ) + '</button>';
			}
			corps += '<button type="button" class="aod-cd-btn ' + ( opts.danger ? 'aod-cd-btn-danger' : 'aod-cd-btn-primary' ) + '" data-ok>'
				+ escAttr( opts.okLabel || CD.i18nConfirm || 'Confirmer' ) + '</button>';
			corps += '</div>';

			var repondu = false;
			var repondre = function ( valeur ) {
				if ( repondu ) { return; } // Échap PUIS clic : une seule réponse.
				repondu = true;
				resolve( valeur );
			};

			var modal = openOverlayModal( {
				// Titre GÉNÉRIQUE par défaut : les questions sont déjà des phrases
				// complètes (« Supprimer « X » ? »). Les répéter en en-tête ET dans
				// le corps afficherait deux fois le même texte — 13 chaînes de titre
				// pour zéro information.
				title: opts.title || CD.i18nConfirmTitle || '',
				bodyHtml: corps,
				initialFocus: '[data-ok]',
				// Annuler / Échap / fond mènent tous à la MÊME issue : false. Le
				// scaffold ferme, on ne fait qu'ajouter la réponse.
				onCancel: function () { modal.close(); repondre( false ); },
				onEscape: function () { modal.close(); repondre( false ); }
			} );
			modal.overlay.querySelector( '[data-ok]' ).addEventListener( 'click', function () {
				modal.close();
				repondre( true );
			} );
		} );
	}

	/**
	 * La modale PARTAGÉE `#aod-cd-modal` — un SEUL propriétaire, et c'est ici.
	 *
	 * ⚠️ À ne pas confondre avec `openOverlayModal()` juste au-dessus : celle-là
	 * CRÉE un overlay jetable (`[data-cancel]`) à chaque appel. Celle-ci pilote le
	 * markup unique imprimé par `AOD_CD_Dashboard` en pied de page (`[data-close]`),
	 * que deux sections se partagent — le détail d'une commande et la timeline de
	 * Suivi. Sur `/gestion/tracking`, les deux vivent dans la même page.
	 *
	 * 🚨 47.4 — Pourquoi un singleton et pas deux binders. `bindOrderDetail` et
	 * `bindTracking` posaient CHACUN leur fermeture et leur écouteur `Échap` sur
	 * le même élément, avec des corps quasi identiques, et tous deux tournent sur
	 * toutes les pages : deux fermetures, deux `Échap`, une seule modale. Y
	 * ajouter la politique du fond en double aurait été la dérive que la Phase 46
	 * a passé huit étapes à corriger — « une règle écrite deux fois ne tient pas ».
	 *
	 * 🚨 Et la politique du fond est interrogée AU CLIC, jamais portée par
	 * l'attribut. MESURÉ : retirer `data-close` du fond après le démarrage ne
	 * change RIEN — l'écouteur avait été posé sur l'élément, par référence ;
	 * l'attribut n'a servi qu'à le trouver une fois. D'où la délégation, qui règle
	 * du même coup la leçon 47.2 : un écouteur posé sur un nœud meurt avec le nœud
	 * dès qu'un `innerHTML` le remplace, et le bouton reste cliquable et inerte.
	 * `data-close` RESTE donc dans le markup (la timeline en dépend).
	 *
	 * @return {Object|null} { modal, titleEl, bodyEl, open( opts ), close } — `open`
	 *   accepte `{ backdropCloses: bool }` : le fond ferme (timeline, défaut) ou
	 *   ne ferme pas (détail d'une commande, où l'on SAISIT).
	 */
	var modalePartagee = null;
	function sharedModal() {
		if ( modalePartagee ) { return modalePartagee; }
		var modal = document.getElementById( 'aod-cd-modal' );
		if ( ! modal ) { return null; }
		var bodyEl  = modal.querySelector( '.aod-cd-modal-body' );
		var release = null;   // Piège de focus actif (cf. trapFocus).
		var fondFerme = true; // Politique de l'ouverture en cours.

		function open( opts ) {
			fondFerme = ! opts || false !== opts.backdropCloses;
			modal.hidden = false;
			document.body.style.overflow = 'hidden';
			release = trapFocus( modal.querySelector( '.aod-cd-modal-box' ) || modal );
		}
		function close() {
			modal.hidden = true;
			document.body.style.overflow = '';
			if ( bodyEl ) { bodyEl.innerHTML = ''; }
			if ( release ) { release(); release = null; }
		}

		modal.addEventListener( 'click', function ( e ) {
			var cible = e.target && e.target.closest ? e.target.closest( '[data-close]' ) : null;
			if ( ! cible ) { return; }
			if ( ! fondFerme && cible.classList.contains( 'aod-cd-modal-backdrop' ) ) { return; }
			close();
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! modal.hidden ) { close(); }
		} );

		modalePartagee = {
			modal: modal,
			titleEl: modal.querySelector( '.aod-cd-modal-title' ),
			bodyEl: bodyEl,
			open: open,
			close: close,
		};
		return modalePartagee;
	}

	/**
	 * Menu flottant (position:fixed) : le RECOLLER au bouton quand la page défile,
	 * au lieu de le refermer.
	 *
	 * Le réflexe « on referme dès que ça défile » a deux effets pervers, tous deux
	 * constatés en recette :
	 *
	 * 1. Cliquer un bouton partiellement hors écran le focalise ; le navigateur
	 *    défile alors pour l'amener en vue, et l'événement `scroll` arrive APRÈS le
	 *    `click` (les scroll sont émis en différé). Le menu s'ouvrait donc… puis se
	 *    refermait dans la foulée : au premier clic, il ne se passait RIEN.
	 * 2. Le menu défile lui-même (max-height + overflow) : en phase de capture, la
	 *    molette À L'INTÉRIEUR du menu déclenchait aussi la fermeture — les options
	 *    du bas (29 transporteurs) étaient inatteignables à la souris.
	 *
	 * On ne ferme donc que si le bouton a quitté l'écran : plus rien ne rattache le
	 * menu à quoi que ce soit.
	 *
	 * @param {Element}  btn    Bouton d'ancrage.
	 * @param {Element}  menu   Menu flottant.
	 * @param {Function} isOpen Le menu est-il ouvert ?
	 * @param {Function} place  Repositionne le menu sous le bouton.
	 * @param {Function} close  Ferme le menu.
	 */
	function bindFloatingMenu( btn, menu, isOpen, place, close ) {
		var pending = false;

		window.addEventListener( 'scroll', function ( e ) {
			if ( ! isOpen() ) { return; }
			// Défilement INTERNE au menu : il ne décolle pas de son bouton.
			if ( e.target === menu || ( e.target.nodeType === 1 && menu.contains( e.target ) ) ) { return; }
			if ( pending ) { return; }
			pending = true;
			requestAnimationFrame( function () {
				pending = false;
				if ( ! isOpen() ) { return; }
				var r = btn.getBoundingClientRect();
				if ( r.bottom < 0 || r.top > window.innerHeight ) { close(); return; }
				place();
			} );
		}, true );

		window.addEventListener( 'resize', function () { if ( isOpen() ) { close(); } } );
	}

	/**
	 * Clavier d'une liste d'options « à la combobox » (bouton + menu).
	 *
	 * Le focus NE QUITTE JAMAIS le bouton : l'option courante est désignée par
	 * `aria-activedescendant`. C'est la seule approche qui tienne ici, car le menu
	 * de `.aod-cd-csel` est attaché au <body> (pour échapper aux overflow parents) :
	 * il n'est donc pas voisin du bouton dans l'ordre de tabulation, et un focus
	 * déplacé dans le menu deviendrait injoignable au Tab. Les options gardent
	 * tabindex="-1" : cliquables à la souris, jamais des arrêts de tabulation.
	 *
	 * @param {Object} o {btn, menu, optSel, isOpen, setOpen, choose}
	 */
	function bindListboxKeys( o ) {
		var idBase = 'aod-cd-opt-' + Math.random().toString( 36 ).slice( 2, 8 );
		var cur = -1;

		// `aria-activedescendant` n'est pas permis sur un simple `button` : il faut
		// un rôle qui l'accepte. Le bouton reste un <button> (donc cliquable et
		// activable nativement), on ne change que ce qui est annoncé.
		o.btn.setAttribute( 'role', 'combobox' );
		if ( ! o.menu.id ) { o.menu.id = idBase + '-menu'; }
		o.btn.setAttribute( 'aria-controls', o.menu.id );

		function opts() { return Array.prototype.slice.call( o.menu.querySelectorAll( o.optSel ) ); }

		function mark( i ) {
			var list = opts();
			if ( ! list.length ) { return; }
			cur = Math.max( 0, Math.min( i, list.length - 1 ) );
			list.forEach( function ( el, k ) {
				// tabindex=-1 : les options restent cliquables mais ne sont JAMAIS des
				// arrêts de tabulation — le focus ne doit pas entrer dans le menu.
				el.tabIndex = -1;
				if ( ! el.id ) { el.id = idBase + '-' + k; }
				el.classList.toggle( 'is-kbd', k === cur );
				// NB : on ne touche pas à `aria-selected`. Dans une listbox il désigne
				// l'option CHOISIE (le statut courant de la commande), pas celle que
				// les flèches survolent — c'est le rôle d'aria-activedescendant.
			} );
			o.btn.setAttribute( 'aria-activedescendant', list[ cur ].id );
			list[ cur ].scrollIntoView( { block: 'nearest' } );
		}

		function clear() {
			o.btn.removeAttribute( 'aria-activedescendant' );
			opts().forEach( function ( el ) { el.classList.remove( 'is-kbd' ); } );
			cur = -1;
		}

		function openAt( i ) {
			if ( ! o.isOpen() ) { o.setOpen( true ); }
			var list = opts();
			var from = list.findIndex( function ( el ) { return el.classList.contains( 'is-active' ); } );
			mark( i === 'last' ? list.length - 1 : ( i === 'sel' ? Math.max( 0, from ) : i ) );
		}

		o.btn.addEventListener( 'keydown', function ( e ) {
			var open = o.isOpen();
			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				open ? mark( cur + 1 ) : openAt( 'sel' );
			} else if ( 'ArrowUp' === e.key ) {
				e.preventDefault();
				open ? mark( cur - 1 ) : openAt( 'last' );
			} else if ( 'Home' === e.key && open ) {
				e.preventDefault();
				mark( 0 );
			} else if ( 'End' === e.key && open ) {
				e.preventDefault();
				mark( opts().length - 1 );
			} else if ( ( 'Enter' === e.key || ' ' === e.key ) && open && cur >= 0 ) {
				// Le bouton étant un <button>, Entrée/Espace déclencherait aussi son
				// click (⇒ refermeture) : on coupe court et on valide l'option visée.
				e.preventDefault();
				var pick = opts()[ cur ];
				o.setOpen( false );
				clear();
				if ( pick ) { o.choose( pick ); }
			} else if ( 'Escape' === e.key && open ) {
				e.preventDefault();
				o.setOpen( false );
				clear();
			}
		} );

		// Le menu ne doit JAMAIS prendre le focus — comme un <select> natif.
		// Sans ce preventDefault, le mousedown sur une option focaliserait cette
		// option, ferait perdre le focus au bouton, donc déclencherait le `blur`
		// ci-dessous qui masque le menu… avant même que le `click` ne parte : la
		// sélection à la souris ne marcherait plus du tout.
		o.menu.addEventListener( 'mousedown', function ( e ) { e.preventDefault(); } );

		// Le focus quitte le widget (Tab) : un menu en position:fixed resté ouvert
		// flotterait au-dessus d'une page où plus rien ne le désigne.
		o.btn.addEventListener( 'blur', function () {
			if ( o.isOpen() ) { o.setOpen( false ); }
			clear();
		} );

		return { clear: clear };
	}

	/* Selects personnalisés : remplace le <select> natif (qui s'ouvrait hors
	   champ sur mobile) par un menu en position:fixed clampé au viewport.
	   Le <select> d'origine reste dans le DOM (caché) pour la soumission. */
	function bindSelects() {
		document.querySelectorAll( 'select[data-aod-select]' ).forEach( function ( sel ) {
			if ( sel.dataset.aodSelectBound ) { return; }
			sel.dataset.aodSelectBound = '1';

			var wrap = document.createElement( 'div' );
			wrap.className = 'aod-cd-csel';
			sel.parentNode.insertBefore( wrap, sel );
			wrap.appendChild( sel );

			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'aod-cd-csel-btn';
			btn.setAttribute( 'aria-haspopup', 'listbox' );
			btn.setAttribute( 'aria-expanded', 'false' );
			var txt = document.createElement( 'span' );
			txt.className = 'aod-cd-csel-text';
			var caret = document.createElement( 'span' );
			caret.className = 'aod-cd-csel-caret';
			caret.setAttribute( 'aria-hidden', 'true' );
			btn.appendChild( txt );
			btn.appendChild( caret );
			wrap.appendChild( btn );

			// Le menu est attaché au <body> : ainsi aucun overflow parent ne le rogne.
			var menu = document.createElement( 'div' );
			menu.className = 'aod-cd-csel-menu';
			menu.setAttribute( 'role', 'listbox' );
			menu.hidden = true;
			document.body.appendChild( menu );

			function syncText() {
				var o = sel.options[ sel.selectedIndex ];
				txt.textContent = o ? o.textContent.trim() : '';
			}
			function buildMenu() {
				menu.innerHTML = '';
				Array.prototype.forEach.call( sel.options, function ( o ) {
					var opt = document.createElement( 'button' );
					opt.type = 'button';
					opt.className = 'aod-cd-csel-opt' + ( o.selected ? ' is-active' : '' );
					opt.textContent = o.textContent.trim();
					opt.dataset.value = o.value;
					menu.appendChild( opt );
				} );
			}
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
					buildMenu();
					menu.hidden = false;
					place();
					wrap.classList.add( 'is-open' );
				} else {
					menu.hidden = true;
					wrap.classList.remove( 'is-open' );
				}
				btn.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
			}

			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				setOpen( menu.hidden );
			} );
			function choose( opt ) {
				sel.value = opt.dataset.value;
				sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				syncText();
				btn.focus();
			}

			menu.addEventListener( 'click', function ( e ) {
				var opt = e.target.closest( '.aod-cd-csel-opt' );
				if ( ! opt ) { return; }
				e.preventDefault();
				setOpen( false );
				choose( opt );
			} );

			// Clavier. Indispensable ici : le menu vit dans le <body> (pour échapper
			// aux overflow parents), il n'est donc PAS voisin du bouton dans l'ordre
			// de tabulation — au Tab on saute par-dessus. Le focus reste sur le
			// bouton et l'option courante est désignée par aria-activedescendant.
			bindListboxKeys( {
				btn: btn,
				menu: menu,
				optSel: '.aod-cd-csel-opt',
				isOpen: function () { return ! menu.hidden; },
				setOpen: setOpen,
				choose: choose,
			} );

			document.addEventListener( 'click', function ( e ) {
				if ( menu.hidden ) { return; }
				if ( e.target.closest( '.aod-cd-csel-menu' ) || btn.contains( e.target ) ) { return; }
				setOpen( false );
			} );
			bindFloatingMenu(
				btn,
				menu,
				function () { return ! menu.hidden; },
				place,
				function () { setOpen( false ); }
			);

			syncText();
		} );
	}

	/* Formulaires à accordéons (produit, livraison…).
	 *
	 * Garde-fou : un champ invalide REPLIÉ dans un accordéon bloque la soumission
	 * sans le moindre message — le navigateur refuse de focaliser un champ masqué,
	 * l'événement « submit » n'est jamais émis, et le clic sur « Enregistrer » ne
	 * fait donc visiblement RIEN. On ouvre le bloc fautif pour que l'erreur
	 * redevienne visible et corrigeable. (« invalid » ne remonte pas : il faut
	 * l'écouter en phase de capture.)
	 *
	 * Vaut pour TOUT formulaire à accordéons : la page Livraison cache 58 champs
	 * de tarif derrière un bloc repliable — même piège, mêmes conséquences.
	 */
	function bindAccordionForms() {
		document.querySelectorAll( 'form.aod-cd-acc-form' ).forEach( function ( form ) {
			form.addEventListener( 'invalid', function ( e ) {
				var acc = e.target.closest( 'details.aod-cd-acc' );
				if ( acc && ! acc.open ) {
					acc.open = true;
				}
			}, true );
		} );
	}

	/* Formulaires de réglages génériques (Livraison, Pixels, WhatsApp…) */
	function bindSettingsForms() {
		document.querySelectorAll( '.aod-cd-settings-form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var btn = form.querySelector( 'button[type="submit"]' );
				var msg = form.querySelector( '.aod-cd-form-msg' );
				if ( btn ) { btn.disabled = true; }
				if ( msg ) { msg.textContent = ''; }

				var data = new FormData( form );
				data.append( 'action', form.dataset.action );
				data.append( 'nonce', CD.nonce );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( btn ) { btn.disabled = false; }
						if ( res && res.success ) {
							toast( res.data.message || CD.i18nSaved || 'Enregistré', false );
							// 🔴 `reload` ÉTAIT ÉCOUTÉ PARTOUT SAUF ICI, et ça rendait un
							// bouton menteur. Les binders de section (Livraison, Équipe,
							// Suivi) respectent ce drapeau depuis toujours ; le générique
							// l'ignorait. Résultat : « Rentabilité par produit » renvoyait
							// `reload:true`, son bouton annonçait « Enregistrer et
							// recalculer », et seul l'enregistrement avait lieu — les
							// bénéfices affichés restaient ceux d'avant la saisie, sans
							// que rien ne le signale. Un handler qui change des nombres
							// rendus par le serveur les fait désormais reprendre.
							// 700 ms : le même délai que les trois autres binders, pour
							// que le toast soit lu avant que la page ne reparte.
							if ( res.data && res.data.reload ) {
								setTimeout( function () { window.location.reload(); }, 700 );
							}
						} else {
							toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
						}
					} )
					.catch( function () {
						if ( btn ) { btn.disabled = false; }
						toast( netErr, true );
					} );
			} );
		} );
	}

	// 47.3 — `textPromptModal()` a été SUPPRIMÉE : `bindOrderNote` en était l'unique
	// appelant, et la note du confirmateur est devenue un champ du formulaire
	// d'édition (elle a une destination — le livreur — qu'une invite n'avait pas).
	//
	// ⚠️ Ne pas la « garder pour 47.7 » : la 47.7 remplace 12 `window.confirm` et
	// 1 `window.alert` (Constat 10 de §A.23), pas un `prompt`. Sa primitive est
	// `openOverlayModal()`, juste au-dessus, qui reste.

	/* Menu déroulant de la barre latérale (mobile) */
	function bindNavToggle() {
		var btn  = document.querySelector( '.aod-cd-navtoggle' );
		var side = document.querySelector( '.aod-cd-side' );
		if ( ! btn || ! side ) { return; }
		btn.addEventListener( 'click', function () {
			var open = side.classList.toggle( 'is-open' );
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}

	/* ==========================================================================
	 * Registre des binders (décomposition 33.x)
	 * ========================================================== */

	// L'ORDRE CANONIQUE d'exécution — exactement celui d'avant la décomposition.
	// Les modules de section (fichiers section-*.js) déclarent leurs binders via
	// register() ; rien ne s'exécute à l'enregistrement, tout au DOMContentLoaded,
	// dans CET ordre — jamais dans l'ordre des balises <script>.
	var ORDER = [
		'bindNavToggle', 'bindStatus', 'bindQuickEdit', 'bindProducts', 'bindCategories',
		'bindCatDropdown', 'bindPromoRange', 'bindGallery', 'bindColorPalette',
		'bindCharts', 'bindCounters', 'bindSelects', 'bindDatePickers', 'bindAccordionForms', 'bindSettingsForms',
		'bindShippingFree', 'bindCarrierRows', 'bindCarrierTest',
		'bindShippingSearch', 'bindImportFees', 'bindWhatsappTest',
		'bindPixelDialogs', 'bindCapiTest', 'bindPixelCards', 'bindOrderDetail',
		'bindOrderBulkDelete', 'bindShipSend', 'bindResetShop', 'bindTracking', 'bindTeam',
		'bindStock', 'bindLanding', 'bindAbandonedConvert',
		'bindAbandonedBulkDelete', 'bindLeadAssign', 'bindFinance', 'bindDepenses'
	];
	var registry = {};
	function register( name, fn ) {
		registry[ name ] = fn;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		// 46.3 — la région live du toast est posée AVANT tout binder, donc avant
		// qu'un message puisse y entrer. C'est la condition de son annonce (cf. le
		// commentaire de `toastRegion()`) : une région créée au moment du premier
		// message rejouerait exactement le défaut qu'on corrige.
		init( 'toastRegion', toastRegion );

		// TOUS les binders passent par init() — isolation + échec NOMMÉ (32.5).
		// Et le registre applique la même politique : un nom listé sans
		// implémentation (module non chargé ?) ou un binder enregistré hors
		// liste est un échec nommé en console — jamais un silence.
		ORDER.forEach( function ( name ) {
			if ( ! registry[ name ] ) {
				console.error( '[aod-cd] binder « ' + name + ' » listé dans ORDER mais jamais enregistré (module manquant ?)' );
				return;
			}
			init( name, registry[ name ] );
		} );
		Object.keys( registry ).forEach( function ( name ) {
			if ( -1 === ORDER.indexOf( name ) ) {
				console.error( '[aod-cd] binder « ' + name + ' » enregistré hors de l’ordre canonique — l’ajouter à ORDER (dashboard.js)' );
				init( name, registry[ name ] );
			}
		} );
	} );

	// Ce que les modules de section consomment. Leurs corps restent VERBATIM :
	// chaque module pose des alias locaux (var toast = core.toast, …).
	window.aodCdCore = {
		CD: CD,
		netErr: netErr,
		register: register,
		init: init,
		toast: toast,
		esc: esc,
		escAttr: escAttr,
		emptyState: emptyState,
		skeleton: skeleton,
		trapFocus: trapFocus,
		openOverlayModal: openOverlayModal,
		// 47.7 — LA question du tableau de bord. ⚠️ Elle rend une PROMESSE :
		// `if ( ! confirmModal(…) )` ne se déclenche jamais (cf. son docblock).
		confirmModal: confirmModal,
		// ⚠️ `sharedModal` N'EST PAS un binder : ni dans ORDER, ni register()é.
		sharedModal: sharedModal,
		bindFloatingMenu: bindFloatingMenu,
		bindListboxKeys: bindListboxKeys
	};

	// Binders encore dans la coque (les sections extraites s'enregistrent
	// depuis leur module ; cette liste FOND à mesure de la décomposition).
	register( 'bindNavToggle', bindNavToggle );
	register( 'bindSelects', bindSelects );
	register( 'bindAccordionForms', bindAccordionForms );
	register( 'bindSettingsForms', bindSettingsForms );
}() );
