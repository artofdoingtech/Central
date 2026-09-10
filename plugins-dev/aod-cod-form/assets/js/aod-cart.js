/**
 * Panier COD public (Phases 35.3 + 36) — store localStorage, bouton flottant,
 * panneau, surimpression d'ajout sur les cartes produit, bloc de gestion sur
 * la page du formulaire.
 *
 * Vanilla, sans jQuery (pattern aod-landing.js). Chargé UNIQUEMENT si le
 * gérant a activé le panier (enqueue conditionnel côté PHP).
 *
 * Le store ne garde que { product_id, opt (clés FR canoniques), qty } : les
 * noms et prix affichés sont (re)demandés au serveur à CHAQUE peinture —
 * c'est ce qui rend l'affichage bilingue (titres via les filtres 7.2) et les
 * prix honnêtes (estimation = la formule exacte du serveur).
 *
 * 36.2 : le panneau (drawer) et le bloc de la page formulaire partagent LA
 * MÊME peinture (fetchEstimate + paintInto) — deux implémentations
 * divergeraient à la première retouche.
 */
( function () {
	'use strict';

	if ( ! window.AOD_CART ) {
		return;
	}
	var CFG = window.AOD_CART;
	var KEY = 'aod_cart';

	/* ---------------------------------------------------------------- store */

	function read() {
		try {
			var v = JSON.parse( window.localStorage.getItem( KEY ) || '[]' );
			return Array.isArray( v ) ? v : [];
		} catch ( e ) {
			return [];
		}
	}

	function write( items ) {
		try {
			window.localStorage.setItem( KEY, JSON.stringify( items ) );
		} catch ( e ) { /* stockage plein/privé : le panier redevient volatil. */ }
		refreshBadge();
		document.dispatchEvent( new CustomEvent( 'aod:cart-change' ) );
	}

	function count() {
		return read().reduce( function ( n, it ) { return n + ( parseInt( it.qty, 10 ) || 1 ); }, 0 );
	}

	// Deux entrées fusionnent si même produit ET mêmes options (clé canonique).
	function sig( it ) {
		return String( it.product_id ) + '|' + JSON.stringify( it.opt || {} );
	}

	// Renvoie `true` si TOUT a été rangé, `false` si au moins une ligne NEUVE a
	// buté sur le plafond.
	//
	// 🚨 Le refus est le correctif de la dette D35 (2026-08-03), et il porte sur
	// les LIGNES, pas sur les unités. Le serveur écrête à `MAX_ITEMS` — dans
	// l'estimation ET dans le funnel de commande (44.1) — et ici rien ne bornait :
	// un panier de 31 lignes en montrait 30, la commande en portait 30, sans un
	// mot. L'acheteur recevait moins que ce qu'il avait demandé.
	// La justification écrite en 44.1 pour écrêter au lieu de refuser — « aucune
	// intention d'acheteur à trahir dans une forme que le formulaire ne produit
	// jamais » — était vraie tant que le formulaire était mono-produit. Le panier
	// l'a rendue fausse sans que personne ne relise la phrase.
	//
	// ⚠️ Une FUSION reste toujours permise, plafond atteint ou non : ajouter une
	// unité à une ligne existante ne crée pas de ligne. Refuser là serait un
	// second défaut — l'acheteur ne comprendrait pas qu'on lui refuse un article
	// qui est déjà dans son panier.
	function add( productId, items ) {
		var cart  = read();
		var max   = parseInt( CFG.max_items, 10 ) || 0;
		var refus = false;
		items.forEach( function ( raw ) {
			var it = { product_id: parseInt( productId, 10 ), qty: parseInt( raw.qty, 10 ) || 1, opt: raw.opt || {} };
			var hit = null;
			cart.forEach( function ( c ) {
				if ( sig( c ) === sig( it ) ) { hit = c; }
			} );
			if ( hit ) {
				hit.qty = ( parseInt( hit.qty, 10 ) || 1 ) + it.qty;
			} else if ( max && cart.length >= max ) {
				refus = true;
			} else {
				cart.push( it );
			}
		} );
		// On écrit quand même : d'un lot de deux lignes, celle qui tenait est
		// rangée. Un refus total sur un lot partiellement acceptable perdrait un
		// article que rien n'empêchait de prendre.
		write( cart );
		return ! refus;
	}

	/* ------------------------------------------------------------ éléments */

	var fab     = document.getElementById( 'aod-cart-fab' );
	var overlay = document.getElementById( 'aod-cart-overlay' );
	var drawer  = document.getElementById( 'aod-cart-drawer' );
	if ( ! fab || ! drawer || ! overlay ) {
		return; // Coque absente (page d'admin, thème exotique) : store seul.
	}
	var sheet    = document.getElementById( 'aod-cart-sheet' );
	var list     = drawer.querySelector( '.aod-cart__list' );
	var totalEl  = drawer.querySelector( '.aod-cart__total' );
	var orderBtn = drawer.querySelector( '.aod-cart__order' );
	var closeBtn = drawer.querySelector( '.aod-cart__close' );
	var firstUrl = '';

	// Dernier total BRUT servi par l'estimation (36.2) : le récapitulatif du
	// formulaire le consomme via window.aodCart.total(). -1 = jamais estimé.
	var lastTotal = -1;

	function refreshBadge() {
		var n     = count();
		var badge = fab.querySelector( '.aod-cart__badge' );
		badge.textContent = String( n );
		badge.hidden      = n === 0;
		// Le bouton flottant n'apparaît qu'avec un panier non vide : une boutique
		// au panier vide garde son écran d'avant.
		fab.hidden = n === 0;
	}

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = String( s == null ? '' : s );
		return d.innerHTML;
	}

	/* -------------------------------------------------- estimation partagée */

	// UNE requête d'estimation, quel que soit le nombre de vues à peindre.
	// cb( null, data ) sur succès ; cb( message ) sur échec.
	function fetchEstimate( cb ) {
		var cart = read();
		var body = new URLSearchParams();
		body.set( 'action', 'aod_cod_cart_items' );
		body.set( 'nonce', CFG.nonce );
		cart.forEach( function ( it, i ) {
			body.set( 'items[' + i + '][product_id]', it.product_id );
			body.set( 'items[' + i + '][qty]', it.qty );
			Object.keys( it.opt || {} ).forEach( function ( si ) {
				body.set( 'items[' + i + '][opt][' + si + ']', it.opt[ si ] );
			} );
		} );
		window.fetch( CFG.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					cb( ( res && res.data && res.data.message ) || CFG.i18n.net_error );
					return;
				}
				lastTotal = ( 'undefined' !== typeof res.data.total ) ? ( parseFloat( res.data.total ) || 0 ) : -1;
				document.dispatchEvent( new CustomEvent( 'aod:cart-total' ) );
				cb( null, res.data );
			} )
			.catch( function () {
				cb( CFG.i18n.net_error );
			} );
	}

	// Peint des lignes estimées dans une vue (drawer OU bloc formulaire).
	// Renvoie l'URL de la 1ʳᵉ ligne disponible (cible de « Commander »).
	function paintInto( listEl, totEl, data ) {
		var first = '';
		var html  = '';
		( data.lines || [] ).forEach( function ( l ) {
			if ( l.available && ! first ) {
				first = l.url;
			}
			var opts = Object.keys( l.opts || {} ).map( function ( k ) {
				return esc( k ) + ' : ' + esc( l.opts[ k ] );
			} ).join( ' · ' );
			// DEUX colonnes (vignette | corps), pas cinq : la grille à 5 colonnes
			// écrasait le nom jusqu'à « Bas… » dès 390 px. Le retrait vit dans la
			// rangée haute — il reste donc actif sur une ligne indisponible, qui
			// n'a ni quantité ni total.
			// 🚨 IDENTITÉ = `data-idx`, la position dans le store — jamais le
			// produit. Deux variantes du même produit sont deux lignes : cibler
			// par `product_id` faisait agir « + », « − » et « retirer » sur la
			// PREMIÈRE ligne du produit, donc sur une couleur que l'acheteur
			// n'avait pas désignée. Il payait alors un article qu'il n'avait pas
			// choisi — et depuis que chaque ligne porte la photo de sa variante,
			// l'erreur se voit à l'écran.
			html += '<div class="aod-cart__line' + ( l.available ? '' : ' is-dead' ) + '" data-idx="' + esc( l.idx ) + '">';
			html += l.img ? '<img class="aod-cart__img" src="' + esc( l.img ) + '" alt="">' : '<span class="aod-cart__img aod-cart__img--none"></span>';
			html += '<div class="aod-cart__body">';
			html += '<div class="aod-cart__top">';
			html += '<span class="aod-cart__name">' + esc( l.title ) + '</span>';
			// Icône du catalogue fermé, pré-rendue par PHP (37.2) : le JS ne
			// fabrique jamais une icône. Repli sur le glyphe si la carte d'icônes
			// manque — un bouton sans étiquette visible serait pire qu'un « × ».
			html += '<button type="button" class="aod-cart__remove" data-idx="' + esc( l.idx ) + '" aria-label="' + esc( CFG.i18n.remove ) + '">'
				+ ( ( CFG.icons && CFG.icons.close ) ? CFG.icons.close : '&times;' )
				+ '</button>';
			html += '</div>';
			if ( opts ) {
				html += '<span class="aod-cart__opts">' + opts + '</span>';
			}
			if ( ! l.available ) {
				// Le serveur DIT laquelle des deux causes s'applique : « produit
				// indisponible » et « pas à cette quantité » ne se réparent pas du
				// même geste, et les confondre envoyait l'acheteur supprimer une
				// ligne qu'un clic sur « − » aurait sauvée.
				html += '<span class="aod-cart__dead">' + esc( l.message || CFG.i18n.unavailable ) + '</span>';
			}
			// Une ligne refusée POUR SA QUANTITÉ garde ses commandes : sans elles,
			// un « + » de trop était irréversible (plus de −, plus de +, total à
			// zéro) et il fallait supprimer la ligne pour repartir de la fiche.
			if ( l.available || l.qty_editable ) {
				html += '<div class="aod-cart__bottom">'
					+ '<div class="aod-cart__qty">'
					+ '<button type="button" class="aod-cart__less" data-idx="' + esc( l.idx ) + '" aria-label="' + esc( CFG.i18n.less ) + '">−</button>'
					+ '<span class="aod-cart__qtyval">' + esc( l.qty ) + '</span>'
					+ '<button type="button" class="aod-cart__more" data-idx="' + esc( l.idx ) + '" aria-label="' + esc( CFG.i18n.more ) + '">+</button>'
					+ '</div>'
					+ '<span class="aod-cart__linetotal">' + ( l.total_html || '' ) + '</span>'
					+ '</div>';
			}
			html += '</div>';
			html += '</div>';
		} );
		// Écrêtage annoncé (D35) : le serveur est le seul à voir le nombre DEMANDÉ
		// avant de le réduire. Peint ICI, donc dans les DEUX vues — le tiroir et le
		// bloc de la page formulaire ont le même corps, et un article ne doit pas
		// disparaître en silence de celle où l'acheteur regarde.
		var note = data.truncated ? '<p class="aod-cart__cap">' + esc( CFG.i18n.over ) + '</p>' : '';
		listEl.innerHTML = note + ( html || ( '<p class="aod-cart__empty">' + esc( CFG.i18n.empty ) + '</p>' ) );
		totEl.innerHTML  = data.total_html || '';
		return first;
	}

	/* ------------------------------------------------------- vue : drawer */

	// Message de plafond du tiroir (D35) : mis par le geste REFUSÉ, effacé par
	// toute peinture qui suit. Il ne se confond pas avec l'écrêtage annoncé par
	// le serveur, peint dans la liste par `paintInto()` : ce sont deux moments
	// distincts (un refus qu'on vient d'opposer / un panier déjà au-delà).
	var capEl = drawer.querySelector( '.aod-cart__cap' );

	function noteCap( txt ) {
		if ( ! capEl ) { return; }
		capEl.textContent = txt || '';
		capEl.hidden      = ! txt;
	}

	function renderDrawer() {
		var cart = read();
		noteCap( '' );
		if ( ! cart.length ) {
			list.innerHTML = '<p class="aod-cart__empty">' + esc( CFG.i18n.empty ) + '</p>';
			totalEl.innerHTML = '';
			orderBtn.disabled = true;
			firstUrl = '';
			return;
		}
		list.innerHTML = '<p class="aod-cart__empty">…</p>';
		fetchEstimate( function ( err, data ) {
			if ( err ) {
				list.innerHTML = '<p class="aod-cart__empty">' + esc( err ) + '</p>';
				return;
			}
			firstUrl          = paintInto( list, totalEl, data );
			orderBtn.disabled = ! firstUrl;
		} );
	}

	/* --------------------------------- vue : bloc de la page formulaire (36.2) */

	// Le bloc remplace le bandeau « votre panier (N) » : les MÊMES lignes que le
	// panneau (quantités, retrait), peintes au-dessus du formulaire COD.
	var box = null;

	function ensureBox() {
		if ( box ) {
			return box;
		}
		var host = document.querySelector( '.aod-cod' );
		if ( ! host || ! host.parentNode ) {
			return null;
		}
		box = document.createElement( 'section' );
		box.className = 'aod-cart__box';
		box.hidden    = true;
		box.innerHTML = '<h2 class="aod-cart__box-title">' + esc( CFG.i18n.cart ) + '</h2>'
			+ '<div class="aod-cart__list"></div>'
			+ '<div class="aod-cart__totalrow"><span>' + esc( CFG.i18n.total ) + '</span> <strong class="aod-cart__total"></strong></div>'
			+ '<p class="aod-cart__note">' + esc( CFG.i18n.shipping ) + '</p>';
		host.parentNode.insertBefore( box, host );
		bindLineActions( box.querySelector( '.aod-cart__list' ) );
		return box;
	}

	function renderBox() {
		var b = ensureBox();
		if ( ! b ) {
			return;
		}
		if ( ! read().length ) {
			// Panier vidé : le formulaire retrouve son mode mono-produit d'avant.
			// La liste est VIDÉE, pas seulement masquée — un DOM périmé sous un
			// [hidden] reste interrogeable et ment à qui le compte.
			b.hidden = true;
			b.querySelector( '.aod-cart__list' ).innerHTML = '';
			// La fiche du produit-hôte (image, prix, titre) redevient légitime :
			// « Commander » n'ayant pas encore de page dédiée (Phase 77 absente
			// ici), on atterrit sur la fiche du 1ᵉʳ article du panier — sans cette
			// classe, son image/prix restait affiché même une fois le panier vidé
			// depuis ce même bloc, sans rapport avec le formulaire juste dessous.
			document.body.classList.remove( 'aod-cart-checkout' );
			return;
		}
		b.hidden = false;
		// 🚨 « Commander » n'ouvre pas une page dédiée : il atterrit sur la fiche
		// du 1ᵉʳ article du panier, qui affiche SA propre image/titre/prix — alors
		// que le formulaire, juste dessous, peut soumettre PLUSIEURS produits
		// différents (ceux de `.aod-cart__box`, juste au-dessus). L'acheteur lisait
		// deux histoires contradictoires sur la même page. Cette classe masque la
		// fiche du produit-hôte (aod-cart.css) tant que le panier — donc CE bloc —
		// est visible : ne reste que « Votre panier » (les vraies lignes) + le
		// formulaire. Posée ici et nulle part ailleurs : c'est CE bloc, pas un état
		// de panier générique, qui décide si la fiche est redondante sur CETTE page.
		document.body.classList.add( 'aod-cart-checkout' );
		fetchEstimate( function ( err, data ) {
			if ( err ) {
				b.querySelector( '.aod-cart__list' ).innerHTML = '<p class="aod-cart__empty">' + esc( err ) + '</p>';
				return;
			}
			paintInto( b.querySelector( '.aod-cart__list' ), b.querySelector( '.aod-cart__total' ), data );
		} );
	}

	/* ------------------------------------------------- actions sur les lignes */

	// Mutation d'UNE ligne, désignée par sa position dans le store — la même que
	// le serveur a reportée sur la ligne peinte (`idx`).
	//
	// 🚨 Ça visait le `product_id`, et la boucle s'arrêtait à la première
	// correspondance : sur un produit à variantes, « + » sur la ligne Bordeaux
	// incrémentait la ligne Noir, et « retirer » supprimait Noir. L'acheteur
	// commandait et payait une couleur qu'il n'avait pas choisie. Le commentaire
	// d'alors décrivait le fait comme un détail d'affichage — c'était une
	// mutation du panier.
	//
	// L'index est valable le temps d'une peinture : toute écriture repeint les
	// vues (`aod:cart-change`), et une ligne tombée à zéro est retirée ci-dessous
	// — les index suivants glissent, et la peinture qui suit les rétablit.
	function mutate( idx, fn ) {
		var cart = read();
		var i    = parseInt( idx, 10 );
		if ( isNaN( i ) || ! cart[ i ] ) {
			return; // Ligne déjà partie (double clic, autre vue) : ne rien toucher.
		}
		fn( cart[ i ], i, cart );
		write( cart.filter( function ( it ) { return ( parseInt( it.qty, 10 ) || 0 ) > 0; } ) );
	}

	// Délégation partagée : le drawer ET le bloc formulaire branchent le même
	// gestionnaire — mêmes classes, mêmes gestes.
	function bindLineActions( el ) {
		el.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest ? e.target.closest( 'button' ) : null;
			if ( ! btn ) {
				return;
			}
			var idx = btn.getAttribute( 'data-idx' );
			if ( btn.classList.contains( 'aod-cart__more' ) ) {
				mutate( idx, function ( it ) { it.qty = ( parseInt( it.qty, 10 ) || 1 ) + 1; } );
			} else if ( btn.classList.contains( 'aod-cart__less' ) ) {
				mutate( idx, function ( it ) { it.qty = ( parseInt( it.qty, 10 ) || 1 ) - 1; } );
			} else if ( btn.classList.contains( 'aod-cart__remove' ) ) {
				mutate( idx, function ( it, i, cart ) { cart.splice( i, 1 ); } );
			}
		} );
	}

	bindLineActions( list );

	// Re-peint les vues VISIBLES (le drawer fermé n'a rien à re-peindre — il
	// se peint à l'ouverture). Branché sur l'événement de changement : ajout
	// depuis une grille, mutation d'une ligne, vidage — une seule voie.
	function renderAll() {
		if ( ! drawer.hidden ) {
			renderDrawer();
		}
		renderBox();
	}
	document.addEventListener( 'aod:cart-change', renderAll );

	// Un AUTRE onglet a touché au panier (D36, 2026-08-03). L'événement `storage`
	// ne se déclenche que dans les onglets qui n'ont pas écrit — c'est exactement
	// ceux qu'il faut rafraîchir.
	//
	// 🚨 Ce n'était pas cosmétique, et je l'avais d'abord classé ainsi. Le store
	// est relu à CHAQUE geste (`read()`), donc rien ne se perd et rien ne double :
	// c'est l'ÉCRAN qui restait périmé. Or le formulaire COD soumet
	// `window.aodCart.items()` — le store, pas l'écran. Un onglet affichant
	// « 1 article » pouvait donc envoyer une commande à deux lignes, et l'acheteur
	// payait un article que sa page ne montrait nulle part.
	// `e.key === null` : un `localStorage.clear()` d'un autre onglet.
	window.addEventListener( 'storage', function ( e ) {
		if ( e.key !== KEY && null !== e.key ) { return; }
		refreshBadge();
		renderAll();
	} );

	/* ------------------------------------------------------ ouvrir/fermer */

	// UNE seule mécanique de boîte modale, pour le tiroir ET le panneau de choix.
	//
	// 🚨 Elle piège le focus, et c'est une correction (dette D34, 2026-08-02) :
	// les deux se déclarent `aria-modal="true"`, mais la tabulation SORTAIT dans
	// la page derrière — mesuré, après le dernier bouton — alors que le
	// défilement du corps, lui, était bloqué. On annonçait une modale à un
	// lecteur d'écran en le laissant errer dans un contenu qu'il ne pouvait même
	// pas faire défiler.
	// Le reste de la page passe en `inert` : une seule ligne remplace le piège
	// écrit à la main, ET retire le contenu de l'arbre d'accessibilité. Repli
	// manuel (boucle sur Tab) là où `inert` n'existe pas.
	var lastFocus = null;
	var boiteOuverte = null;

	function focusables( el ) {
		return Array.prototype.filter.call(
			el.querySelectorAll( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])' ),
			function ( n ) { return n.offsetParent !== null || n === document.activeElement; }
		);
	}

	function inerterFond( actif ) {
		Array.prototype.forEach.call( document.body.children, function ( n ) {
			// ⚠️ Le bouton flottant N'EST PAS exclu : il vit hors de la boîte, donc
			// il doit devenir inerte avec le reste. L'avoir épargné le laissait
			// atteignable au Tab depuis une modale — mesuré, fuite au 6ᵉ Tab.
			if ( n === overlay || n === drawer || n === sheet ) { return; }
			if ( actif ) {
				if ( 'inert' in HTMLElement.prototype ) { n.inert = true; }
				n.setAttribute( 'data-aod-inert', '1' );
			} else if ( n.getAttribute( 'data-aod-inert' ) ) {
				if ( 'inert' in HTMLElement.prototype ) { n.inert = false; }
				n.removeAttribute( 'data-aod-inert' );
			}
		} );
	}

	function ouvrirBoite( el, apres ) {
		lastFocus      = document.activeElement;
		boiteOuverte   = el;
		overlay.hidden = false;
		el.hidden      = false;
		document.body.classList.add( 'aod-cart-open' );
		inerterFond( true );
		if ( apres ) { apres(); }
		var f = focusables( el );
		( f[ 0 ] || el ).focus();
	}

	function fermerBoite() {
		if ( ! boiteOuverte ) { return; }
		overlay.hidden       = true;
		boiteOuverte.hidden  = true;
		boiteOuverte         = null;
		document.body.classList.remove( 'aod-cart-open' );
		inerterFond( false );
		if ( lastFocus && lastFocus.focus ) { lastFocus.focus(); }
	}

	// `note` : phrase à afficher en tête du tiroir (plafond atteint). Posée APRÈS
	// `renderDrawer()`, qui remet le bandeau à zéro à chaque peinture.
	// ⚠️ Jamais branché tel quel sur un `addEventListener` : l'événement arriverait
	// en `note` et le tiroir s'ouvrirait sur un objet.
	function open( note ) {
		ouvrirBoite( drawer, function () {
			renderDrawer();
			noteCap( note );
		} );
	}
	function close() {
		fermerBoite();
	}

	fab.addEventListener( 'click', function () { open(); } );
	overlay.addEventListener( 'click', fermerBoite );
	closeBtn.addEventListener( 'click', fermerBoite );
	document.addEventListener( 'keydown', function ( e ) {
		if ( ! boiteOuverte ) { return; }
		if ( 'Escape' === e.key ) {
			fermerBoite();
			return;
		}
		// Le cycle est fermé À LA MAIN, `inert` ou pas. Mesuré avec `inert` seul :
		// au-delà du dernier élément, le focus part sur `BODY` puis dans la barre
		// du navigateur — il ne revient pas au début. `inert` protège la PAGE ;
		// il ne boucle pas la boîte. Les deux sont nécessaires.
		if ( 'Tab' === e.key ) {
			var f = focusables( boiteOuverte );
			if ( ! f.length ) { return; }
			var premier = f[ 0 ];
			var dernier = f[ f.length - 1 ];
			if ( e.shiftKey && document.activeElement === premier ) {
				e.preventDefault();
				dernier.focus();
			} else if ( ! e.shiftKey && document.activeElement === dernier ) {
				e.preventDefault();
				premier.focus();
			}
		}
	} );

	orderBtn.addEventListener( 'click', function () {
		if ( firstUrl ) {
			window.location.href = firstUrl + '#' + CFG.anchor;
		}
	} );

	/* -------------------------- surimpression sur les cartes produit (36.1) */

	// PHP n'imprime qu'un nœud de données par carte (Boutique, Accueil,
	// produits liés, suggestions) : le markup des cartes appartient au thème,
	// c'est ici qu'on enveloppe l'image et qu'on pose le bouton. Un <button>
	// dans le <a> de la carte serait invalide : role="button" + preventDefault.
	function initQuickAdd() {
		var nodes = document.querySelectorAll( '.aod-cart__carddata' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			var card = node.closest ? node.closest( 'li.product, .aod-cart__sugcard' ) : null;
			if ( ! card || card.querySelector( '.aod-cart__quickgrp' ) ) {
				return;
			}
			var img = card.querySelector( 'img' );
			if ( ! img || ! img.parentNode ) {
				return; // Carte sans image : pas de surimpression possible.
			}
			// Le produit porte-t-il des options à choisir ? Ça ne change PAS le
			// libellé (cf. plus bas) — seulement ce que fait le clic.
			var hasOpts = '1' === node.getAttribute( 'data-hasopts' );

			// Groupe empilé (2026-09-08, demande owner) : « Ajouter au panier »
			// PUIS « Commander », posés l'un sous l'autre, même peau.
			var grp = document.createElement( 'span' );
			grp.className = 'aod-cart__quickgrp';

			// 🚨 SUR MOBILE, LE GROUPE NE FLOTTE PLUS SUR L'IMAGE (2026-09-08, demande
			// owner, porté de Template-Full) : `(hover: none)` — même signal que la
			// feuille de style pour distinguer tactile et souris. Le groupe rejoint la
			// carte APRÈS `.astra-shop-summary-wrap` (donc après le prix) ; la
			// surimpression `.aod-cart__qwrap` n'est créée QUE pour la souris.
			var mobile = !! ( window.matchMedia && window.matchMedia( '(hover: none)' ).matches );
			if ( mobile ) {
				card.appendChild( grp );
			} else {
				var wrap = document.createElement( 'span' );
				wrap.className = 'aod-cart__qwrap';
				img.parentNode.insertBefore( wrap, img );
				wrap.appendChild( img );
				wrap.appendChild( grp );
			}

			var btn = document.createElement( 'span' );
			btn.className = 'aod-cart__quickadd';
			btn.setAttribute( 'role', 'button' );
			btn.setAttribute( 'tabindex', '0' );
			// 🚨 LIBELLÉ UNIQUE, décidé le 2026-08-02 (arbitrage owner). Il a
			// longtemps dépendu de `hasOpts` : « Choisir les options » sur un
			// produit à variantes, « Ajouter au panier » sinon. Sur un catalogue
			// réel, c'est le PREMIER cas qui domine — les deux produits de
			// démonstration ont une couleur, donc TOUTES les cartes de la
			// Boutique et de l'Accueil affichaient « Choisir les options », et
			// le point d'entrée du panier ne se lisait plus comme une action
			// d'achat. Une grille où aucune carte ne dit « Ajouter au panier »
			// n'annonce pas qu'il y a un panier.
			//
			// Ce qui NE change pas : on n'ajoute jamais une couleur en la
			// devinant. Le clic d'un produit à variantes ouvre le panneau de
			// choix (voir `act()`), et se replie sur la fiche si le panneau ne
			// peut pas s'ouvrir. Remettre `hasOpts` ici rouvrirait le défaut.
			// Gardé par `bash tools/recette/run.sh panier` (témoin à variantes).
			btn.textContent = CFG.i18n.add;
			grp.appendChild( btn );

			function act( e ) {
				e.preventDefault();
				e.stopPropagation();
				if ( hasOpts ) {
					// Variantes à choisir : le panneau les demande SUR PLACE. Le repli
					// — la fiche, ancrée sur le bloc de choix — reste le chemin quand le
					// panneau ne peut pas s'ouvrir (serveur qui refuse, réseau coupé,
					// coque absente sur un thème exotique) : le geste aboutit toujours
					// quelque part, jamais dans le vide.
					ouvrirChoix( node.getAttribute( 'data-pid' ), function () {
						window.location.href = node.getAttribute( 'data-url' );
					} );
					return;
				}
				if ( ! add( node.getAttribute( 'data-pid' ), [ { qty: 1, opt: {} } ] ) ) {
					// Plafond atteint (D35). La carte n'a aucune surface de message :
					// on OUVRE le panier avec la phrase. L'acheteur y lit la raison
					// ET voit la ligne qu'il peut retirer — un « Ajouté ✓ » menteur,
					// ou un refus silencieux, l'auraient laissé sans recours.
					open( CFG.i18n.full );
					return;
				}
				btn.classList.add( 'is-added' );
				btn.textContent = CFG.i18n.added_short;
				window.setTimeout( function () {
					btn.classList.remove( 'is-added' );
					btn.textContent = CFG.i18n.add;
				}, 1600 );
			}
			btn.addEventListener( 'click', act );
			btn.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) {
					act( e );
				}
			} );

			/*
			 * « Commander » (2026-09-08, demande owner) : posé SOUS « Ajouter au
			 * panier », même peau, visible dans le même geste. Même ajout que
			 * « Ajouter » (`add()`), sur un produit à variantes le panneau de choix
			 * s'ouvre d'abord (on ne devine pas la couleur) — cf. `sheetGoOrder`.
			 * ⛔ CE CLONE N'A PAS ENCORE LA PAGE `/commander` (Phase 77, absente
			 * ici) : le repli est le même que celui déjà écrit pour le bouton
			 * « Commander » du tiroir un peu plus haut — ouvrir le panier, où
			 * l'acheteur voit sa ligne et le propre bouton du tiroir.
			 */
			var order = document.createElement( 'span' );
			order.className = 'aod-cart__quickorder';
			order.setAttribute( 'role', 'button' );
			order.setAttribute( 'tabindex', '0' );
			order.textContent = CFG.i18n.order;
			grp.appendChild( order );

			function actOrder( e ) {
				e.preventDefault();
				e.stopPropagation();
				if ( hasOpts ) {
					ouvrirChoix( node.getAttribute( 'data-pid' ), function () {
						window.location.href = node.getAttribute( 'data-url' );
					}, true );
					return;
				}
				if ( ! add( node.getAttribute( 'data-pid' ), [ { qty: 1, opt: {} } ] ) ) {
					open( CFG.i18n.full );
					return;
				}
				open();
			}
			order.addEventListener( 'click', actOrder );
			order.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) {
					actOrder( e );
				}
			} );
		} );
	}

	/* ------------------------- panneau de CHOIX sur la grille (2026-08-02) */

	// Ce que ce panneau répare : sur une carte de produit À VARIANTES, le bouton
	// disait « Ajouter au panier » et menait à la fiche — on ne devine pas une
	// couleur. Mesuré, le visiteur ne faisait pas le lien : il appuyait sur un
	// bouton d'ajout, la page sautait, le compteur ne bougeait pas. Le bouton
	// promettait ce qu'il ne faisait pas.
	// Ici, le geste ABOUTIT : on choisit dans le panneau, ça s'ajoute, la page
	// ne bouge pas. Le libellé unique devient vrai pour tous les produits.
	//
	// 🚨 Le contenu du panneau est SERVI PAR LE SERVEUR (`aod_cod_cart_options`),
	// pas fabriqué ici : c'est le markup de la fiche, avec l'état de rupture
	// (`disabled`) et les noms localisés. Un second rendu en JS aurait divergé
	// le jour d'une rupture — donc sur une commande impossible.
	var sheetBody = sheet ? sheet.querySelector( '.aod-cart__sheet-body' ) : null;
	var sheetName = sheet ? sheet.querySelector( '.aod-cart__sheet-name' ) : null;
	var sheetMsg  = sheet ? sheet.querySelector( '.aod-cart__sheet-msg' ) : null;
	var sheetAdd  = sheet ? sheet.querySelector( '.aod-cart__sheet-add' ) : null;
	var sheetPid  = 0;
	// « Commander » depuis une carte à variantes (2026-09-08) : posé par
	// `ouvrirChoix( pid, replier, true )`, lu une fois par le clic d'ajout du
	// panneau.
	var sheetGoOrder = false;

	function ouvrirChoix( pid, replier, goOrder ) {
		if ( ! sheet ) { replier(); return; }
		sheetGoOrder      = !! goOrder;
		sheetPid          = parseInt( pid, 10 ) || 0;
		sheetMsg.textContent = '';
		sheetBody.innerHTML  = '';
		sheetName.textContent = '';
		sheetAdd.disabled     = true;

		var body = new URLSearchParams();
		body.set( 'action', 'aod_cod_cart_options' );
		body.set( 'nonce', CFG.nonce );
		body.set( 'product_id', sheetPid );
		window.fetch( CFG.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res || ! res.success || ! res.data || ! res.data.options ) {
					// Le serveur refuse (produit parti, options disparues, toggle
					// coupé) : on REPLIE sur la fiche plutôt que d'ouvrir une boîte
					// vide — le visiteur garde un chemin.
					replier();
					return;
				}
				sheetName.innerHTML = esc( res.data.title ) + ' <span class="aod-cart__sheet-price">' + ( res.data.price_html || '' ) + '</span>';
				sheetBody.innerHTML = res.data.options;
				sheetAdd.disabled   = false;
				ouvrirBoite( sheet );
			} )
			.catch( replier );
	}

	if ( sheet ) {
		sheet.querySelector( '.aod-cart__close' ).addEventListener( 'click', fermerBoite );

		// L'état visuel « choisi » est porté par une CLASSE sur la fiche (c'est son
		// JS qui la pose). Le panneau sert le même markup : il doit la poser aussi,
		// sinon la pastille cochée ne se distingue pas des autres.
		sheetBody.addEventListener( 'change', function ( e ) {
			var input = e.target;
			if ( ! input || 'radio' !== input.type ) { return; }
			var sec = input.closest( '.aod-cod__optsec' );
			if ( ! sec ) { return; }
			Array.prototype.forEach.call( sec.querySelectorAll( '.aod-cod__opt' ), function ( o ) { o.classList.remove( 'is-selected' ); } );
			var opt = input.closest( '.aod-cod__opt' );
			if ( opt ) { opt.classList.add( 'is-selected' ); }
			sheetMsg.textContent = '';
		} );

		sheetAdd.addEventListener( 'click', function () {
			var opt     = {};
			var manque  = false;
			Array.prototype.forEach.call( sheetBody.querySelectorAll( '.aod-cod__optsec' ), function ( sec ) {
				var si  = sec.getAttribute( 'data-si' );
				var sel = sec.querySelector( 'select.aod-cod__optselect' );
				var val = sel ? sel.value : ( ( sec.querySelector( 'input[type="radio"]:checked' ) || {} ).value || '' );
				if ( val ) { opt[ si ] = val; } else { manque = true; }
			} );
			if ( manque ) {
				// Même exigence que le formulaire : on n'ajoute jamais une option
				// devinée, y compris depuis ce panneau.
				sheetMsg.textContent = CFG.i18n.pick_missing;
				return;
			}
			if ( ! add( sheetPid, [ { qty: 1, opt: opt } ] ) ) {
				// Plafond atteint : on reste OUVERT. Fermer sur un refus rendrait le
				// geste indistinguable d'un ajout réussi — c'est précisément le
				// défaut que le panneau est venu réparer.
				sheetMsg.textContent = CFG.i18n.full;
				return;
			}
			fermerBoite();
			if ( sheetGoOrder ) {
				// Entré par « Commander » (2026-09-08) : l'ajout a réussi, on ouvre
				// directement le panier — pas de page `/commander` sur ce clone.
				open();
			}
		} );
	}

	/* ---------------------------------------------------------------- API */

	window.aodCart = {
		add: function ( productId, items ) {
			add( productId, items );
		},
		items: function () {
			return read();
		},
		count: count,
		clear: function () {
			write( [] );
		},
		open: function () {
			open();
		},
		// 36.2 : total BRUT de la dernière estimation serveur (-1 = jamais
		// estimé) — le récapitulatif du formulaire le consomme.
		total: function () {
			return lastTotal;
		},
		hasTotal: function () {
			return lastTotal >= 0;
		},
	};

	refreshBadge();
	initQuickAdd();
	renderBox();
}() );
