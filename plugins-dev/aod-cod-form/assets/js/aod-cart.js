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
			// `data-pid` + `role="button"` : SEULE la vue « bloc de la page
			// formulaire » (`.aod-cart__box`, cf. `bindPreview()`) écoute ce
			// rôle — le tiroir flottant (`.aod-cart__drawer`) n'a pas de galerie
			// à côté de lui pour y afficher quoi que ce soit, il garde son
			// ancien comportement (rien au clic sur l'image).
			html += l.img
				? '<img class="aod-cart__img" data-pid="' + esc( l.product_id ) + '" role="button" tabindex="0" src="' + esc( l.img ) + '" alt="' + esc( CFG.i18n.preview_alt ) + '">'
				: '<span class="aod-cart__img aod-cart__img--none" data-pid="' + esc( l.product_id ) + '" role="button" tabindex="0"></span>';
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
		// Colonne côte à côte sur grand écran (2026-09-10, demande owner) : le
		// panier et le formulaire partagent un conteneur commun — CSS Grid a
		// besoin des deux comme ENFANTS DIRECTS du MÊME parent pour les mettre
		// côte à côte et les réordonner (`order`, cf. aod-cart.css) ; deux
		// voisins insérés l'un après l'autre dans `.summary.entry-summary` ne le
		// permettent pas proprement. `host` (le formulaire) change donc de
		// PARENT — sa place dans la page ne bouge pas, seul son parent immédiat
		// le fait. Vérifié : aucun sélecteur CSS de ce plugin ne dépend du
		// parent direct de `.aod-cod` (seuls `.summary.entry-summary > …`
		// ciblent les éléments propres à WooCommerce, pas le formulaire).
		var wrap = document.createElement( 'div' );
		wrap.className = 'aod-cart__checkout';
		host.parentNode.insertBefore( wrap, host );
		wrap.appendChild( box );
		wrap.appendChild( host );
		bindLineActions( box.querySelector( '.aod-cart__list' ) );
		bindPreview( box.querySelector( '.aod-cart__list' ) );
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
			document.body.classList.remove( 'aod-cart-checkout' );
			return;
		}
		b.hidden = false;
		// Posée dès que CE bloc (le panier sous le formulaire) est visible —
		// c'est encore ce qui décide si le sélecteur de variantes doit s'effacer
		// (aod-cart.css) : lui seul, depuis le 2026-09-13, la fiche du produit
		// (galerie/titre/prix/description) reste TOUJOURS affichée, panier vide
		// ou non, produit dans le panier ou non — cf. le commentaire du CSS.
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
	/* --------------------------------------- aperçu image + description (2026-09-13) */
	//
	// Clic sur la vignette d'une ligne du BLOC panier (`.aod-cart__box`, celui
	// posé à côté du formulaire — PAS le tiroir flottant, qui n'a aucune
	// galerie à côté de lui pour y afficher quoi que ce soit) : bascule la
	// galerie de la fiche courante sur la photo et la description de CET
	// article, SANS navigation. Le titre/prix affichés au-dessus (ceux de la
	// vraie page où l'acheteur se trouve) ne bougent jamais : seule la galerie
	// change de contenu — les toucher aurait fait mentir le fil d'ariane ou le
	// H1 sur la page réellement affichée.
	//
	// 🚨 Le DOM natif de la galerie n'est JAMAIS réécrit : un panneau
	// `.aod-cart__preview` est ajouté À CÔTÉ, cf. `ensurePreview()`, et une
	// classe sur `.woocommerce-product-gallery` bascule laquelle des deux est
	// visible (aod-cart.css). Le zoom au survol du cœur Woo est lié aux nœuds
	// D'ORIGINE au chargement de la page : le réécrire via `innerHTML` aurait
	// créé des nœuds neufs, sans ces gestionnaires — le zoom serait resté
	// cassé même après être revenu à la fiche du produit-hôte.
	var galleryEl    = document.querySelector( '.woocommerce-product-gallery' );
	// 🚨 La classe d'état se pose sur `div.product` (l'ancêtre), PAS sur la
	// galerie elle-même : trouvé au navigateur — le ruban « Promo ! »
	// (`.onsale`/`.ast-onsale-card`) est un enfant de `div.product`, PAS de la
	// galerie (déjà noté plus haut dans ce fichier, cf. aod-cart.css), et
	// restait donc affiché PAR-DESSUS l'aperçu. Scoper l'état sur `div.product`
	// permet de masquer les deux depuis un seul et même interrupteur.
	var productEl    = galleryEl ? ( galleryEl.closest( '.product' ) || galleryEl.parentNode ) : null;
	var previewEl    = null;
	var previewCache = {}; // product_id -> réponse déjà reçue (pas de double aller-retour)

	function ensurePreview() {
		if ( previewEl || ! galleryEl ) {
			return previewEl;
		}
		previewEl = document.createElement( 'div' );
		previewEl.className = 'aod-cart__preview';
		// Visibilité pilotée UNIQUEMENT par `.is-previewing` sur `productEl`
		// (aod-cart.css) — jamais par l'attribut `hidden` : deux mécanismes
		// pour un même état auraient pu diverger (c'est arrivé, cf. la version
		// éprouvée au navigateur qui restait invisible malgré la classe posée).
		previewEl.innerHTML = '<button type="button" class="aod-cart__preview-close">' + esc( CFG.i18n.preview_close ) + '</button>'
			+ '<img class="aod-cart__preview-img" alt="">'
			+ '<p class="aod-cart__preview-title"></p>'
			+ '<div class="aod-cart__preview-desc"></div>';
		galleryEl.appendChild( previewEl );
		previewEl.querySelector( '.aod-cart__preview-close' ).addEventListener( 'click', function () {
			productEl.classList.remove( 'is-previewing' );
		} );
		return previewEl;
	}

	function paintPreview( data ) {
		var img = previewEl.querySelector( '.aod-cart__preview-img' );
		if ( data.image && data.image.src ) {
			img.src    = data.image.src;
			img.alt    = data.image.alt || data.title || '';
			img.hidden = false;
		} else {
			img.hidden = true;
			img.removeAttribute( 'src' );
		}
		previewEl.querySelector( '.aod-cart__preview-title' ).textContent = data.title || '';
		// HTML de CONFIANCE (même filtre serveur que la fiche native, cf.
		// ajax_preview()) — même statut que `l.total_html` plus haut dans ce
		// fichier, jamais une saisie du visiteur.
		previewEl.querySelector( '.aod-cart__preview-desc' ).innerHTML = data.description || '';
	}

	function showPreview( pid ) {
		var p = ensurePreview();
		if ( ! p || ! pid ) {
			return;
		}
		productEl.classList.add( 'is-previewing' );
		if ( previewCache[ pid ] ) {
			paintPreview( previewCache[ pid ] );
			return;
		}
		p.querySelector( '.aod-cart__preview-title' ).textContent = '…';
		p.querySelector( '.aod-cart__preview-desc' ).innerHTML = '';
		p.querySelector( '.aod-cart__preview-img' ).hidden = true;
		var body = new URLSearchParams();
		body.set( 'action', 'aod_cod_cart_preview' );
		body.set( 'nonce', CFG.nonce );
		body.set( 'product_id', pid );
		window.fetch( CFG.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					return;
				}
				previewCache[ pid ] = res.data;
				paintPreview( res.data );
			} )
			.catch( function () { /* échec réseau : la galerie reste sur ce qu'elle affichait déjà. */ } );
	}

	// Délégué sur la liste du BLOC uniquement — jamais le tiroir, cf. plus haut.
	function bindPreview( el ) {
		if ( ! galleryEl ) {
			return; // Pas de galerie sur cette page (thème exotique) : rien à faire.
		}
		function targetOf( e ) {
			return e.target.closest ? e.target.closest( '.aod-cart__img' ) : null;
		}
		el.addEventListener( 'click', function ( e ) {
			var img = targetOf( e );
			if ( img ) {
				showPreview( parseInt( img.getAttribute( 'data-pid' ), 10 ) || 0 );
			}
		} );
		el.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' !== e.key && ' ' !== e.key ) {
				return;
			}
			var img = targetOf( e );
			if ( img ) {
				e.preventDefault();
				showPreview( parseInt( img.getAttribute( 'data-pid' ), 10 ) || 0 );
			}
		} );
	}

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
	// Le tiroir flottant peut s'ouvrir SUR une fiche produit (galerie présente
	// à côté de lui, sous l'overlay) tout comme sur une page sans galerie
	// (Boutique, Accueil) — `bindPreview()` se désarme de lui-même dans le
	// second cas (`galleryEl` absent), cf. sa propre garde.
	bindPreview( list );

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
			if ( n === overlay || n === drawer ) { return; }
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
			// Un VRAI choix reste-t-il à faire (2026-09-10) ? Calculé au RENDU
			// (`needs_variant_choice()`), pas ici : un produit à options dont
			// chaque section n'a plus qu'UNE valeur jouable n'a rien à demander —
			// `autoOpt` porte alors cette combinaison, prête pour `add()`.
			var needsChoice = hasOpts && '1' === node.getAttribute( 'data-needschoice' );
			var autoOpt     = {};
			if ( hasOpts && ! needsChoice ) {
				try {
					autoOpt = JSON.parse( node.getAttribute( 'data-autoopt' ) || '{}' );
				} catch ( e ) {
					autoOpt = {};
				}
			}

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
				if ( needsChoice ) {
					// Vrai choix à faire : le panneau le demande SUR PLACE, dans la
					// carte. Le repli — la fiche, ancrée sur le bloc de choix — reste
					// le chemin quand le panneau ne peut pas s'ouvrir (serveur qui
					// refuse, réseau coupé, coque absente sur un thème exotique) : le
					// geste aboutit toujours quelque part, jamais dans le vide.
					abrirInline( card, btn, node.getAttribute( 'data-pid' ), node.getAttribute( 'data-url' ), false );
					return;
				}
				if ( ! add( node.getAttribute( 'data-pid' ), [ { qty: 1, opt: autoOpt } ] ) ) {
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
			 * « Ajouter » (`add()`), sur un produit à vrai choix le panneau
			 * inline s'ouvre d'abord (on ne devine pas la couleur) — cf.
			 * `abrirInline( …, goOrder )`.
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
				if ( needsChoice ) {
					abrirInline( card, btn, node.getAttribute( 'data-pid' ), node.getAttribute( 'data-url' ), true );
					return;
				}
				if ( ! add( node.getAttribute( 'data-pid' ), [ { qty: 1, opt: autoOpt } ] ) ) {
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

	/* --------------------- panneau de variantes INLINE dans la carte (2026-09-10) */
	//
	// Refonte demandée par l'owner : le choix de variantes n'est plus une
	// fenêtre flottante (tiroir bas puis boîte centrée, Phases 35-37) — il
	// s'ouvre DANS la carte de la grille, sous les infos produit, comme une
	// extension naturelle de la carte plutôt qu'un composant à part.
	//
	// 🚨 Le contenu est toujours SERVI PAR LE SERVEUR (`aod_cod_cart_options`),
	// jamais fabriqué ici : c'est le markup de la fiche, avec l'état de rupture
	// (`disabled`), les noms localisés, et — depuis needs_variant_choice() — une
	// section à valeur unique déjà pré-cochée. Un second rendu en JS aurait
	// divergé le jour d'une rupture — donc sur une commande impossible.
	//
	// Ce N'EST PAS une modale : pas de piège de focus, pas de fond rendu inerte
	// (`ouvrirBoite`/`inerterFond`, réservés au tiroir DU panier, ne servent pas
	// ici). C'est un disclosure ordinaire — le reste de la page reste utilisable
	// pendant que le panneau est ouvert. Un seul panneau ouvert à la fois sur
	// toute la grille : en ouvrir un referme l'éventuel précédent, pour que le
	// remous (ouvrir une carte agrandit sa RANGÉE dans la grille — comportement
	// CSS Grid normal d'Astra, inévitable dès qu'on choisit d'intégrer le
	// panneau DANS la carte plutôt que par-dessus) reste localisé à un endroit.
	var openInline = null; // { card, panel, trigger, pid, goOrder } | null

	function fermerInline( sansRendreLeFocus ) {
		if ( ! openInline ) { return; }
		var prev = openInline;
		openInline = null;
		prev.panel.classList.remove( 'is-open' );
		prev.trigger.setAttribute( 'aria-expanded', 'false' );
		if ( ! sansRendreLeFocus && prev.trigger.focus ) { prev.trigger.focus(); }
		// La transition retire le nœud une fois finie (durée alignée sur le CSS,
		// cf. `--aod-cart-inline-ms` dans aod-cart.css) : le laisser en vie masqué
		// garderait des radios/labels dans l'ordre de tabulation.
		window.setTimeout( function () {
			if ( prev.panel && prev.panel.parentNode ) { prev.panel.parentNode.removeChild( prev.panel ); }
		}, 320 );
	}

	function abrirInline( card, trigger, pid, fallbackUrl, goOrder ) {
		if ( openInline && openInline.card === card ) {
			// Même carte déjà ouverte : « Commander » qui rejoint « Ajouter »
			// (ou l'inverse) — on garde juste l'intention, sans relancer la
			// requête ni faire clignoter le panneau.
			openInline.goOrder = !! goOrder;
			return;
		}
		fermerInline( true );

		var panel = document.createElement( 'div' );
		panel.className = 'aod-cart__inline';
		panel.innerHTML = '<div class="aod-cart__inline-clip"><div class="aod-cart__inline-body">'
			+ '<p class="aod-cart__inline-load">…</p>'
			+ '</div></div>';
		card.appendChild( panel );
		trigger.setAttribute( 'aria-expanded', 'true' );
		openInline = { card: card, panel: panel, trigger: trigger, pid: pid, goOrder: !! goOrder };
		// Ouvre au cadre suivant : une classe posée le même tick que la création
		// du nœud ne transitionne pas (rien à animer DEPUIS, l'état de départ
		// n'a pas encore été peint).
		window.requestAnimationFrame( function () {
			window.requestAnimationFrame( function () {
				if ( openInline && openInline.panel === panel ) { panel.classList.add( 'is-open' ); }
			} );
		} );

		var body = new URLSearchParams();
		body.set( 'action', 'aod_cod_cart_options' );
		body.set( 'nonce', CFG.nonce );
		body.set( 'product_id', pid );
		window.fetch( CFG.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! openInline || openInline.panel !== panel ) { return; } // fermé entre-temps
				if ( ! res || ! res.success || ! res.data || ! res.data.options ) {
					// Le serveur refuse (produit parti, options disparues, toggle
					// coupé) : on REPLIE sur la fiche plutôt que de garder une
					// carte ouverte sur une boîte vide.
					fermerInline( true );
					window.location.href = fallbackUrl;
					return;
				}
				peindreInline( panel, res.data.options );
				var f = focusables( panel );
				( f[ 0 ] || panel ).focus();
			} )
			.catch( function () {
				fermerInline( true );
				window.location.href = fallbackUrl;
			} );
	}

	function peindreInline( panel, optionsHtml ) {
		var body = panel.querySelector( '.aod-cart__inline-body' );
		body.innerHTML = '<div class="aod-cart__inline-opts">' + optionsHtml + '</div>'
			+ '<div class="aod-cart__inline-foot">'
			+ '<span class="aod-cart__qty">'
			+ '<button type="button" class="aod-cart__less" aria-label="' + esc( CFG.i18n.less ) + '">−</button>'
			+ '<span class="aod-cart__qtyval">1</span>'
			+ '<button type="button" class="aod-cart__more" aria-label="' + esc( CFG.i18n.more ) + '">+</button>'
			+ '</span>'
			+ '<button type="button" class="aod-cart__inline-add"><span class="aod-cart__inline-add-label">' + esc( CFG.i18n.add ) + '</span></button>'
			+ '</div>'
			+ '<p class="aod-cart__inline-msg" role="alert"></p>';

		// État visuel initial des pastilles : le PHP pré-coche déjà la valeur
		// unique d'une section sans vrai choix (needs_variant_choice()), et une
		// section À CHOIX RÉEL peut cohabiter avec elle dans le même produit —
		// sans ce passage, la pastille auto-choisie resterait visuellement nue
		// jusqu'à un `change` que l'acheteur n'a aucune raison de déclencher.
		Array.prototype.forEach.call( body.querySelectorAll( '.aod-cod__optsec' ), function ( sec ) {
			var checked = sec.querySelector( 'input[type="radio"]:checked' );
			if ( ! checked ) { return; }
			var opt = checked.closest( '.aod-cod__opt' );
			if ( opt ) { opt.classList.add( 'is-selected' ); }
		} );

		var msgEl = body.querySelector( '.aod-cart__inline-msg' );
		body.addEventListener( 'change', function ( e ) {
			var input = e.target;
			if ( ! input || 'radio' !== input.type ) { return; }
			var sec = input.closest( '.aod-cod__optsec' );
			if ( ! sec ) { return; }
			Array.prototype.forEach.call( sec.querySelectorAll( '.aod-cod__opt' ), function ( o ) { o.classList.remove( 'is-selected' ); } );
			var opt = input.closest( '.aod-cod__opt' );
			if ( opt ) { opt.classList.add( 'is-selected' ); }
			msgEl.textContent = '';
		} );

		var qty    = 1;
		var qtyVal = body.querySelector( '.aod-cart__qtyval' );
		body.querySelector( '.aod-cart__more' ).addEventListener( 'click', function () {
			qty = Math.min( 99, qty + 1 );
			qtyVal.textContent = String( qty );
		} );
		body.querySelector( '.aod-cart__less' ).addEventListener( 'click', function () {
			qty = Math.max( 1, qty - 1 );
			qtyVal.textContent = String( qty );
		} );

		var addBtn = body.querySelector( '.aod-cart__inline-add' );
		addBtn.addEventListener( 'click', function () {
			if ( addBtn.classList.contains( 'is-loading' ) || addBtn.classList.contains( 'is-added' ) ) { return; }
			var opt    = {};
			var manque = false;
			Array.prototype.forEach.call( body.querySelectorAll( '.aod-cod__optsec' ), function ( sec ) {
				var si  = sec.getAttribute( 'data-si' );
				var sel = sec.querySelector( 'select.aod-cod__optselect' );
				var val = sel ? sel.value : ( ( sec.querySelector( 'input[type="radio"]:checked' ) || {} ).value || '' );
				if ( val ) { opt[ si ] = val; } else { manque = true; }
			} );
			if ( manque ) {
				// Même exigence que le formulaire : on n'ajoute jamais une option
				// devinée, y compris depuis ce panneau.
				msgEl.textContent = CFG.i18n.pick_missing;
				return;
			}
			var pid = openInline ? openInline.pid : 0;
			// État « chargement » (demande owner) : bref et volontaire — l'ajout
			// lui-même est instantané (localStorage), ce court passage évite un
			// double-clic et donne au geste le temps de se lire.
			addBtn.classList.add( 'is-loading' );
			window.setTimeout( function () {
				addBtn.classList.remove( 'is-loading' );
				if ( ! add( pid, [ { qty: qty, opt: opt } ] ) ) {
					// Plafond atteint : on reste OUVERT. Fermer sur un refus rendrait
					// le geste indistinguable d'un ajout réussi — c'est précisément
					// le défaut que ce panneau (et son ancêtre le tiroir bas) est
					// venu réparer.
					msgEl.textContent = CFG.i18n.full;
					return;
				}
				addBtn.classList.add( 'is-added' );
				var goOrder = !! ( openInline && openInline.goOrder );
				var ok = document.createElement( 'p' );
				ok.className = 'aod-cart__inline-ok';
				ok.setAttribute( 'role', 'status' );
				ok.innerHTML = ( ( CFG.icons && CFG.icons.check ) ? CFG.icons.check : '' ) + '<span>' + esc( CFG.i18n.added ) + '</span>';
				body.appendChild( ok );
				window.setTimeout( function () {
					// Le focus revient au bouton déclencheur — SAUF si le tiroir du
					// panier va s'ouvrir dans la foulée (`goOrder`) : il pose lui-même
					// le focus sur son premier élément, cf. `ouvrirBoite()`. Sans cette
					// distinction, un clavier qui vient d'ajouter perdrait le focus au
					// milieu de la page (le bouton disparaît avec le panneau).
					fermerInline( goOrder );
					if ( goOrder ) { open(); }
				}, 1200 );
			}, 320 );
		} );
	}

	// Escape ferme le panneau ouvert et rend le focus à son déclencheur — le
	// même geste que le tiroir/l'ancien panneau, même si ce n'en est plus un.
	document.addEventListener( 'keydown', function ( e ) {
		if ( openInline && 'Escape' === e.key ) {
			fermerInline();
		}
	} );

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
