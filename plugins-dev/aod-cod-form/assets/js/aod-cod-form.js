/* AOD COD Form — front logic */
( function ( $ ) {
	'use strict';

	function fmt( n ) {
		n = Math.round( ( parseFloat( n ) || 0 ) * 100 ) / 100;
		return n.toLocaleString( 'fr-DZ' ) + ' ' + AOD_COD.currency;
	}

	// Normalise un nom de commune comme le serveur (accents, casse, y≈i, ponctuation),
	// pour comparer la commune choisie aux communes qui ont réellement un bureau.
	function normCommune( s ) {
		return ( s == null ? '' : String( s ) )
			.normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
			.toLowerCase()
			.replace( /y/g, 'i' )
			.replace( /[^a-z0-9]+/g, '' );
	}

	$( function () {
		$( '.aod-cod' ).each( function () {
			var $box      = $( this );
			var $form     = $box.find( '.aod-cod__form' );
			var price     = parseFloat( $box.data( 'price' ) ) || 0;
			var productId = $box.data( 'product' );

			// Modèle « cartes d'offres » : le client choisit une offre (1 produit, 2 produits…)
			// puis, si le produit a des variantes, une variante pour chaque unité de l'offre.
			var $offers = $box.find( '.aod-cod__offers' );
			var $cards  = $offers.find( '.aod-cod__offer-card' );
			// Les panneaux de variantes sont imbriqués DANS chaque carte (accordéon).
			var $panels = $offers.find( '.aod-cod__offer-panel' );
			var hasOptions = $panels.length > 0;

			var $wilaya  = $form.find( 'select[name="wilaya"]' );
			var $commune = $form.find( 'select[name="commune"]' );
			var $msg     = $box.find( '.aod-cod__msg' );
			var $cmsg    = $box.find( '.aod-cod__config-msg' );
			var $hint    = $box.find( '.aod-cod__free-hint' );

			// Filtrage du mode « bureau » (stop-desk) selon la commune.
			var $deskRadio = $form.find( 'input[name="delivery"][value="desk"]' );
			var $homeRadio = $form.find( 'input[name="delivery"][value="home"]' );
			var $deskLabel = $deskRadio.closest( '.aod-cod__radio' );
			var deskCache  = {}; // wilaya -> { supported, gated, set:[clés] }

			// Carte d'offre actuellement sélectionnée (index 0 = « 1 produit » par défaut).
			function $selectedCard() {
				var $c = $cards.filter( '.is-selected' ).first();
				return $c.length ? $c : $cards.first();
			}
			function selectedQty() {
				return Math.max( 1, parseInt( $selectedCard().attr( 'data-qty' ), 10 ) || 1 );
			}
			function selectedLotPrice() {
				return parseFloat( $selectedCard().attr( 'data-price' ) ) || price;
			}
			function $selectedPanel() {
				return $selectedCard().find( '.aod-cod__offer-panel' );
			}

			function totalQty() {
				return selectedQty();
			}

			// Somme des suppléments des variantes choisies dans le panneau actif.
			function panelSupplements() {
				if ( ! hasOptions ) { return 0; }
				var supp = 0;
				$selectedPanel().find( '.aod-cod__optsec input[type="radio"]:checked' ).each( function () {
					supp += parseFloat( $( this ).attr( 'data-price' ) ) || 0;
				} );
				$selectedPanel().find( '.aod-cod__optsec select.aod-cod__optselect' ).each( function () {
					supp += parseFloat( $( this ).find( 'option:selected' ).attr( 'data-price' ) ) || 0;
				} );
				return supp;
			}

			// Sous-total produits : prix de lot de l'offre + suppléments des variantes.
			function productsSubtotal() {
				return selectedLotPrice() + panelSupplements();
			}

			// Place la galerie WooCommerce sur la photo de la variante choisie.
			function swapGallery( $radio ) {
				var imgId = parseInt( $radio.attr( 'data-img-id' ), 10 ) || 0;
				if ( ! imgId ) { return; }
				var $gallery = $( '.woocommerce-product-gallery' );
				if ( ! $gallery.length ) { return; }
				var $slides = $gallery.find( '.woocommerce-product-gallery__image' ).not( '.clone' );
				var index = -1;
				$slides.each( function ( i ) {
					if ( parseInt( $( this ).attr( 'data-aod-attachment' ), 10 ) === imgId ) {
						index = i;
						return false;
					}
				} );
				if ( index < 0 ) { return; }
				goToSlide( $gallery, index );
			}

			// Seuil de livraison gratuite (0 = désactivé).
			function freeThreshold() {
				return ( AOD_COD.free_shipping && parseFloat( AOD_COD.free_shipping.threshold ) ) || 0;
			}

			// Tarif de base d'un mode de livraison pour la wilaya sélectionnée.
			function baseShip( type ) {
				var code = parseInt( $wilaya.val(), 10 );
				var w    = AOD_COD.wilayas[ code ];
				if ( ! w ) { return 0; }
				return ( type === 'desk' ) ? ( parseFloat( w.desk ) || 0 ) : ( parseFloat( w.home ) || 0 );
			}

			// La wilaya sélectionnée est-elle en livraison gratuite permanente ?
			function wilayaIsFree() {
				var code = parseInt( $wilaya.val(), 10 );
				var w    = AOD_COD.wilayas[ code ];
				return !! ( w && w.free );
			}

			// Affiche/masque l'option « bureau » selon que la commune choisie a un
			// point relais. Tant qu'on ne connaît pas la wilaya (cache absent) ou que
			// le livreur ne filtre pas, l'option reste visible.
			function applyDeskGate() {
				var code  = parseInt( $wilaya.val(), 10 );
				var entry = deskCache[ code ];
				var allowed = true;
				if ( entry && false === entry.supported ) {
					// Le livreur actif ne gère pas le stop-desk : pas d'option « bureau ».
					allowed = false;
				} else if ( entry && entry.gated ) {
					var val = $commune.val();
					if ( val ) {
						allowed = entry.set.indexOf( normCommune( val ) ) !== -1;
					}
				}
				if ( allowed ) {
					$deskLabel.show();
				} else {
					$deskLabel.hide();
					if ( $deskRadio.is( ':checked' ) ) {
						$homeRadio.prop( 'checked', true );
					}
				}
				render();
			}

			// Récupère (et met en cache) les communes éligibles au stop-desk d'une
			// wilaya auprès du livreur d'envoi automatique, puis applique le filtre.
			function loadDeskSet( code ) {
				if ( ! code ) { applyDeskGate(); return; }
				if ( deskCache[ code ] ) { applyDeskGate(); return; }
				$.post( AOD_COD.ajax_url, {
					action: 'aod_cod_desk_communes',
					nonce: AOD_COD.nonce,
					wilaya: code
				} ).done( function ( res ) {
					var d = ( res && res.data ) || {};
					// desk:false = livreur sans stop-desk → option « bureau » masquée.
					deskCache[ code ] = {
						supported: ( false !== d.desk ),
						gated: !! d.gated,
						set: d.communes || []
					};
					applyDeskGate();
				} ).fail( function () {
					deskCache[ code ] = { supported: true, gated: false, set: [] };
					applyDeskGate();
				} );
			}

			// Recalcule cartes de livraison + récapitulatif + incitation au seuil.
			function render() {
				var subtotal = productsSubtotal();
				// Panier non vide (36.2) : le récapitulatif suit le PANIER — c'est
				// lui qui sera commandé. Le total vient de l'estimation serveur
				// (formule exacte de handle_submit), jamais recalculé ici ; tant
				// qu'elle n'est pas arrivée, on garde le produit de la page.
				//
				// 🚨 « garder le produit de la page », justement, EST le défaut
				// (2026-09-09) : `count()` sait tout de suite (localStorage, lecture
				// synchrone) que le panier n'est pas vide, mais `hasTotal()` attend
				// l'aller-retour réseau de `fetchEstimate()`. Entre les deux —
				// quelques dizaines de ms en local, sensiblement plus sur un réseau
				// mobile DZ — le Sous-total/Total affichait le prix d'UN SEUL produit
				// (celui de cette page) alors que le panier peut en contenir
				// plusieurs. Mesuré : 4 articles à 5 000 DA affichés « 1 000 DA » le
				// temps que la vraie estimation arrive. Un chiffre qu'on SAIT faux ne
				// doit jamais s'afficher, même une fraction de seconde — `render()`
				// est déjà rebranché sur `aod:cart-total` (plus bas) donc l'écran se
				// corrige de lui-même dès l'estimation reçue ; ici on se contente de
				// ne rien afficher de trompeur en attendant.
				var cartPending = !! ( window.aodCart && window.aodCart.count() && ! window.aodCart.hasTotal() );
				if ( window.aodCart && window.aodCart.count() && window.aodCart.hasTotal() ) {
					subtotal = window.aodCart.total();
				}
				var th       = freeThreshold();
				var free     = wilayaIsFree() || ( th > 0 && subtotal >= th );

				$box.find( '.aod-cod__radio-price' ).each( function () {
					var $p = $( this );
					var v  = baseShip( $p.data( 'type' ) );
					if ( v > 0 && free ) {
						$p.html( '<s>' + fmt( v ) + '</s> ' + AOD_COD.i18n.free );
					} else {
						$p.text( v > 0 ? fmt( v ) : '' );
					}
				} );

				if ( cartPending ) {
					$box.find( '.aod-cod__subtotal, .aod-cod__shipping, .aod-cod__total' ).text( '…' );
					$hint.attr( 'hidden', 'hidden' ).text( '' );
					return;
				}

				var type = $form.find( 'input[name="delivery"]:checked' ).val();
				var base = baseShip( type );
				var ship = free ? 0 : base;
				$box.find( '.aod-cod__subtotal' ).text( fmt( subtotal ) );
				$box.find( '.aod-cod__shipping' ).text( free && base > 0 ? AOD_COD.i18n.free : ( ship > 0 ? fmt( ship ) : '—' ) );
				$box.find( '.aod-cod__total' ).text( fmt( subtotal + ship ) );

				if ( th > 0 && free ) {
					$hint.text( AOD_COD.i18n.free_active ).removeAttr( 'hidden' );
				} else if ( th > 0 && subtotal > 0 ) {
					$hint.text( AOD_COD.i18n.free_hint.replace( '%s', fmt( th - subtotal ) ) ).removeAttr( 'hidden' );
				} else {
					$hint.attr( 'hidden', 'hidden' ).text( '' );
				}
			}

			// Sélection d'une carte d'offre : on affiche son panneau de variantes, on masque
			// les autres, et on recalcule. La photo de galerie suit la 1re variante visuelle.
			function selectCard( $card ) {
				if ( ! $card.length ) { return; }
				$cards.removeClass( 'is-selected' );
				$card.addClass( 'is-selected' );
				$card.children( '.aod-cod__offer-head' ).find( 'input[type="radio"]' ).prop( 'checked', true );
				if ( hasOptions ) {
					$panels.attr( 'hidden', 'hidden' );
					$selectedPanel().removeAttr( 'hidden' );
				}
				if ( $cmsg ) { $cmsg.text( '' ); }
				render();
			}

			$offers.on( 'change', '.aod-cod__offer-card input[type="radio"]', function () {
				selectCard( $( this ).closest( '.aod-cod__offer-card' ) );
			} );

			// Écho de la valeur choisie, à côté du libellé de la section (« Pointure · 41 »).
			//
			// 🚨 On lit le texte DÉJÀ AFFICHÉ de la pastille (`.aod-cod__opt-name`),
			// jamais son `data-name` : ce dernier porte le nom canonique FRANÇAIS, qui
			// est la clé de matching serveur (`match_option()`). L'afficher rendrait
			// « Noir » à un acheteur arabophone qui vient de cliquer « أسود ».
			// Effet de bord voulu : zéro chaîne traduisible ajoutée.
			function echoPick( $sec ) {
				var $out = $sec.find( '.aod-cod__optpick' ).first();
				if ( ! $out.length ) { return; }
				var txt = '';
				if ( $sec.hasClass( 'is-dropdown' ) ) {
					var $o = $sec.find( 'select.aod-cod__optselect option:selected' );
					txt = $o.val() ? $.trim( $o.text() ) : '';
				} else {
					txt = $.trim( $sec.find( 'input[type="radio"]:checked' )
						.closest( '.aod-cod__opt' ).find( '.aod-cod__opt-name' ).text() );
				}
				$out.text( txt );
			}

			// Choisir une valeur d'option : marque la carte/option et change la grande photo.
			if ( hasOptions ) {
				$offers.on( 'change', '.aod-cod__optsec input[type="radio"]', function () {
					var $sec = $( this ).closest( '.aod-cod__optsec' );
					$sec.find( '.aod-cod__opt' ).removeClass( 'is-selected' );
					$( this ).closest( '.aod-cod__opt' ).addClass( 'is-selected' );
					echoPick( $sec );
					if ( $sec.hasClass( 'is-visual' ) ) {
						swapGallery( $( this ) );
					}
					render();
				} );

				// Variante choisie via une liste déroulante : photo de galerie + recalcul.
				$offers.on( 'change', '.aod-cod__optsec select.aod-cod__optselect', function () {
					echoPick( $( this ).closest( '.aod-cod__optsec' ) );
					swapGallery( $( this ).find( 'option:selected' ) );
					render();
				} );

				// État initial : une section peut arriver déjà cochée (retour arrière du
				// navigateur, remplissage automatique). Sans ce passage, l'écho resterait
				// vide alors qu'une valeur EST sélectionnée — un affichage qui ment.
				$offers.find( '.aod-cod__optsec' ).each( function () {
					echoPick( $( this ) );
				} );
			}

			// Cascade wilaya -> communes
			$wilaya.on( 'change', function () {
				var code = parseInt( $( this ).val(), 10 );
				var data = AOD_COD.wilayas[ code ];
				$commune.empty().append(
					$( '<option>' ).val( '' ).text( AOD_COD.i18n.choose_commune )
				);
				if ( data && data.communes ) {
					$.each( data.communes, function ( i, c ) {
						$commune.append( $( '<option>' ).val( c.v ).text( c.l ) );
					} );
					$commune.prop( 'disabled', false );
				} else {
					$commune.prop( 'disabled', true );
				}
				applyDeskGate(); // rend immédiatement (option « bureau » visible le temps du chargement)
				loadDeskSet( code );
			} );

			$form.on( 'change', 'select[name="commune"]', applyDeskGate );
			$form.on( 'change input', 'input[name="delivery"]', render );

			// État initial : carte par défaut + panneau visible cohérents.
			selectCard( $selectedCard() );

			// Anti-spam (time-trap) : instant où le formulaire devient interactif.
			// Le temps de remplissage écoulé est envoyé à la soumission ; le serveur
			// rejette les envois trop rapides (bots). Mesuré côté client → insensible
			// au cache de page (un horodatage serveur figé dans le HTML mis en cache
			// fausserait le calcul).
			var formReadyAt = Date.now();

			/* ---- Capture « panier abandonné » (prospect à rappeler) ---- */
			var leadToken   = 'l' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 8 );
			var leadStarted = false;
			var leadTimer   = null;

			function collectLead() {
				return {
					action: 'aod_cod_lead',
					nonce: AOD_COD.nonce,
					token: leadToken,
					product_id: productId,
					name: $form.find( 'input[name="name"]' ).val(),
					phone: $form.find( 'input[name="phone"]' ).val(),
					wilaya: $wilaya.val(),
					commune: $commune.val(),
					delivery: $form.find( 'input[name="delivery"]:checked' ).val(),
					qty: totalQty() || 1
				};
			}

			function pushLead() {
				var phone = ( $form.find( 'input[name="phone"]' ).val() || '' ).replace( /\D+/g, '' );
				if ( phone.length < 8 ) { return; }
				leadStarted = true;
				$.post( AOD_COD.ajax_url, collectLead() );
			}

			function scheduleLead() {
				if ( leadTimer ) { clearTimeout( leadTimer ); }
				leadTimer = setTimeout( pushLead, 900 );
			}

			$form.find( 'input[name="phone"]' ).on( 'blur', pushLead );
			$form.on( 'input change', 'input[name="name"], input[name="phone"], select[name="wilaya"], select[name="commune"], input[name="delivery"]', function () {
				if ( leadStarted ) {
					scheduleLead();
				} else {
					var phone = ( $form.find( 'input[name="phone"]' ).val() || '' ).replace( /\D+/g, '' );
					if ( phone.length >= 9 ) { scheduleLead(); }
				}
			} );

			// Lit la sélection de variantes du panneau actif : une entrée par unité.
			// Renvoie { items: [...], missing: bool }.
			function collectItems() {
				if ( ! hasOptions ) {
					return { items: [ { qty: totalQty(), opt: {} } ], missing: false };
				}
				var items   = [];
				var missing = false;
				$selectedPanel().find( '.aod-cod__unit' ).each( function () {
					var opt = {};
					$( this ).find( '.aod-cod__optsec' ).each( function () {
						var si       = $( this ).data( 'si' );
						var $select  = $( this ).find( 'select.aod-cod__optselect' );
						var val;
						if ( $select.length ) {
							val = $select.val();
						} else {
							var $sel = $( this ).find( 'input[type="radio"]:checked' );
							val = $sel.length ? $sel.val() : '';
						}
						if ( val ) {
							opt[ si ] = val;
						} else {
							missing = true;
						}
					} );
					items.push( { qty: 1, opt: opt } );
				} );
				return { items: items, missing: missing };
			}

			// Panier COD (35.3) — présent seulement si le gérant l'a activé :
			// le bouton n'est rendu que si cart_enabled(), et aod-cart.js n'est
			// enqueué que dans ce cas. « Ajouter » réutilise collectItems() :
			// les variantes déjà composées partent telles quelles au store.
			var $addCart = $form.find( '.aod-cod__addcart' );
			if ( $addCart.length && window.aodCart ) {
				$addCart.on( 'click', function () {
					var c = collectItems();
					if ( c.missing ) {
						if ( $cmsg ) { $cmsg.text( AOD_COD.i18n.choose_option ); }
						return showError( AOD_COD.i18n.choose_option );
					}
					if ( ! window.aodCart.add( productId, c.items ) ) {
						// Plafond de lignes atteint (D35) : le panier REFUSE, et le dit ici
						// même. La phrase vient du panier, pas d'une seconde chaîne — deux
						// formulations du même refus divergeraient à la première retouche.
						$msg.removeClass( 'is-success' ).addClass( 'is-error' )
							.text( ( window.AOD_CART && window.AOD_CART.i18n.full ) || '' );
						return;
					}
					$msg.removeClass( 'is-error' ).addClass( 'is-success' ).text( AOD_COD.i18n.cart_added || '' );
				} );
			}

			// --- Repère d'arrivée depuis la grille (2026-08-02) ------------------
			// Chemin de REPLI depuis la grille : la carte d'un produit à variantes
			// ouvre normalement le panneau de choix SUR PLACE, et n'amène ici que
			// s'il n'a pas pu s'ouvrir. Le visiteur, lui, a appuyé sur « Ajouter »
			// et voit une page qui saute sans que le compteur bouge — mesuré, rien
			// ne reliait les deux moments. On révèle alors la phrase qui nomme les
			// deux gestes restants.
			//
			// ⚠️ Sur reconnaissance de l'ancre UNIQUEMENT : un visiteur venu de la
			// Boutique ou d'un lien n'a rien demandé, il ne doit rien voir de plus.
			// Et la phrase s'efface dès que le choix est complet — `collectItems()`
			// répond déjà à cette question exacte (`missing`), on ne la repose pas
			// autrement.
			//
			// 🚨 AUCUN DÉFILEMENT JS ICI, et c'est une décision mesurée : le calage
			// est fait par `scroll-margin-block-start` sur `#aod-options` (CSS). Une
			// version précédente recalculait la position en déduisant un bandeau
			// collant ; elle n'a JAMAIS pris la main — le saut d'ancre du navigateur
			// arrive après, et gagne. Deux acteurs pour un même défilement, c'est un
			// de trop : on laisse celui dont c'est le métier.
			var $pickHint = $box.find( '.aod-cod__pickhint' );
			if ( $pickHint.length && '#aod-options' === window.location.hash ) {
				$pickHint.removeAttr( 'hidden' );
			}
			function majPickHint() {
				if ( ! $pickHint.length || $pickHint.attr( 'hidden' ) ) { return; }
				if ( ! collectItems().missing ) {
					$pickHint.attr( 'hidden', 'hidden' );
				}
			}
			$offers.on( 'change', 'input[type="radio"], select', majPickHint );

			// 36.2 : le bloc de gestion (aod-cart.js) remplace l'ancien bandeau
			// « votre panier (N) » au-dessus du formulaire. Le récapitulatif suit
			// le panier — re-rendu à chaque mutation et à l'arrivée de chaque
			// estimation serveur.
			document.addEventListener( 'aod:cart-change', render );
			document.addEventListener( 'aod:cart-total', render );

			// Soumission
			$form.on( 'submit', function ( e ) {
				e.preventDefault();
				$msg.removeClass( 'is-error is-success' ).text( '' );
				if ( $cmsg ) { $cmsg.text( '' ); }

				var name    = $form.find( 'input[name="name"]' ).val().trim();
				var phone   = $form.find( 'input[name="phone"]' ).val().replace( /\D+/g, '' );
				var wilaya  = $wilaya.val();
				var commune = $commune.val();

				// Panier non vide ⇒ la commande porte LES ARTICLES DU PANIER
				// (chacun avec son product_id — serveur 35.2). La sélection de la
				// page n'y entre que par le bouton « Ajouter au panier » : exiger
				// ses options ici bloquerait un visiteur venu du panneau commander.
				var usedCart  = !! ( window.aodCart && window.aodCart.count() );
				var itemsData;
				if ( usedCart ) {
					itemsData = window.aodCart.items();
				} else {
					var collected = collectItems();
					if ( collected.missing ) {
						if ( $cmsg ) { $cmsg.text( AOD_COD.i18n.choose_option ); }
						return showError( AOD_COD.i18n.choose_option );
					}
					itemsData = collected.items;
				}

				if ( ! wilaya ) {
					return showError( AOD_COD.i18n.required );
				}
				if ( ! /^0[5-7][0-9]{8}$/.test( phone ) ) {
					return showError( AOD_COD.i18n.phone_invalid );
				}

				var $btn = $form.find( '.aod-cod__submit' );
				$btn.prop( 'disabled', true );
				var original = $btn.text();
				$btn.text( AOD_COD.i18n.sending );

				var postData = {
					action: 'aod_cod_submit',
					nonce: AOD_COD.nonce,
					product_id: productId,
					name: name,
					phone: phone,
					wilaya: wilaya,
					commune: commune,
					address: $form.find( 'input[name="address"]' ).val(),
					delivery: $form.find( 'input[name="delivery"]:checked' ).val(),
					lead_token: leadToken,
					elapsed: Date.now() - formReadyAt,
					aod_url: $form.find( 'input[name="aod_url"]' ).val(),
					// Challenge anti-robot Cloudflare Turnstile (vide si non configuré).
					cf_turnstile: $form.find( '[name="cf-turnstile-response"]' ).val() || ''
				};
				$.each( itemsData, function ( li, it ) {
					postData[ 'items[' + li + '][qty]' ] = it.qty;
					if ( it.product_id ) {
						postData[ 'items[' + li + '][product_id]' ] = it.product_id;
					}
					$.each( it.opt || {}, function ( si, v ) {
						postData[ 'items[' + li + '][opt][' + si + ']' ] = v;
					} );
				} );

				// Jeton Turnstile à usage unique : on le régénère avant chaque nouvelle
				// tentative, sinon un 2e envoi réutiliserait un jeton déjà consommé.
				function resetTurnstile() {
					if ( window.turnstile ) {
						try { window.turnstile.reset(); } catch ( e ) {}
					}
				}

				$.post( AOD_COD.ajax_url, postData ).done( function ( res ) {
					if ( res && res.success ) {
						// Le panier est consommé par la commande : on le vide, sinon
						// la prochaine visite recommanderait les mêmes articles.
						if ( usedCart && window.aodCart ) {
							window.aodCart.clear();
						}
						showSuccess( res.data || {} );
					} else {
						showError( ( res && res.data && res.data.message ) || ( AOD_COD.i18n && AOD_COD.i18n.error ) || 'Erreur.' );
						$btn.prop( 'disabled', false ).text( original );
						resetTurnstile();
					}
				} ).fail( function ( xhr ) {
					var m = ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) || ( AOD_COD.i18n && AOD_COD.i18n.net_error ) || 'Erreur réseau.';
					showError( m );
					$btn.prop( 'disabled', false ).text( original );
					resetTurnstile();
				} );
			} );

			function showError( m ) {
				$msg.addClass( 'is-error' ).text( m );
			}

			// Remplace le formulaire par un panneau de remerciement (pas de redirection
			// vers la page WooCommerce « commande reçue » : ce template n'expose aucun
			// tunnel panier/checkout).
			function showSuccess( data ) {
				// Conversion publicitaire : le tunnel WooCommerce « commande reçue »
				// n'étant pas utilisé, c'est ici (et non sur woocommerce_thankyou) que
				// l'achat est mesuré. Une seule fois : la commande est marquée côté serveur.
				fireConversion( data.pixels );

				var i     = AOD_COD.i18n || {};
				var title = data.message || i.success_title || 'Merci !';
				var order = data.order_id ? ( ( i.order_label || 'Commande N°' ) + ' ' + data.order_id ) : '';
				var sub   = i.success_sub || '';
				var $done = $(
					'<div class="aod-cod__done" role="status" aria-live="polite">' +
						'<div class="aod-cod__done-ic" aria-hidden="true">✓</div>' +
						'<h3 class="aod-cod__done-title"></h3>' +
						'<p class="aod-cod__done-order"></p>' +
						'<p class="aod-cod__done-sub"></p>' +
					'</div>'
				);
				$done.find( '.aod-cod__done-title' ).text( title );
				if ( order ) { $done.find( '.aod-cod__done-order' ).text( order ); } else { $done.find( '.aod-cod__done-order' ).remove(); }
				if ( sub ) { $done.find( '.aod-cod__done-sub' ).text( sub ); } else { $done.find( '.aod-cod__done-sub' ).remove(); }
				$form.replaceWith( $done );
				if ( $done[0] && $done[0].scrollIntoView ) {
					$done[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
				}
			}

			// Émet l'événement d'achat vers chaque régie configurée (Meta « Purchase »,
			// TikTok « CompletePayment », Snapchat « PURCHASE », Google Ads « conversion »).
			// Les bases de pixel sont déjà chargées dans le <head> de la page produit,
			// donc fbq/ttq/snaptr/gtag existent ici. La charge utile vient du serveur
			// (valeur, devise, produits) ; reste null si aucun pixel n'est configuré.
			function fireConversion( px ) {
				if ( ! px ) { return; }
				var ids = px.content_ids || [];
				try {
					if ( px.meta && window.fbq ) {
						// 4e argument { eventID } : permet à Meta de dédupliquer cet
						// achat avec sa copie « serveur » (Conversions API) qui porte
						// le même event_id (= n° de commande).
						fbq( 'track', 'Purchase', {
							value: px.value,
							currency: px.currency,
							content_ids: ids,
							content_type: 'product'
						}, px.event_id ? { eventID: px.event_id } : undefined );
					}
					if ( px.tiktok && window.ttq ) {
						// 3e argument { event_id } : déduplication avec la copie
						// serveur (Events API, Phase 13.1) qui porte le même event_id
						// (= n° de commande). Sans lui, TikTok compte l'achat 2 fois.
						ttq.track( 'CompletePayment', {
							value: px.value,
							currency: px.currency,
							content_type: 'product',
							content_id: ids.length ? ids[0] : ''
						}, px.event_id ? { event_id: px.event_id } : undefined );
					}
					if ( px.snapchat && window.snaptr ) {
						// client_dedup_id : déduplication avec la copie serveur
						// (Conversions API v3, Phase 13.2) qui porte le même id
						// (= n° de commande). Snapchat n'a pas de 3e argument
						// d'options : la clé va dans l'objet de propriétés.
						var snapProps = {
							price: px.value,
							currency: px.currency,
							transaction_id: px.transaction_id
						};
						if ( px.event_id ) { snapProps.client_dedup_id = px.event_id; }
						snaptr( 'track', 'PURCHASE', snapProps );
					}
					if ( px.google_send_to && window.gtag ) {
						gtag( 'event', 'conversion', {
							send_to: px.google_send_to,
							value: px.value,
							currency: px.currency,
							transaction_id: px.transaction_id
						} );
					}
				} catch ( e ) {}
			}

			render();
		} );

		// Flèches ‹ › superposées sur la grande photo pour parcourir toutes les
		// images du produit. La galerie est unique sur la page : on initialise une
		// seule fois, hors de la boucle des formulaires.
		initGalleryNav();
	} );

	// Pilote la galerie WooCommerce (flexslider) : direction ('prev'/'next') ou
	// index de diapo (nombre). On privilégie l'API flexslider ; à défaut on simule
	// un clic sur la navigation/les miniatures natives.
	function goToSlide( $gallery, target ) {
		if ( $.fn.flexslider && $gallery.find( '.flex-viewport' ).length ) {
			try { $gallery.flexslider( target ); return; } catch ( e ) {}
		}
		if ( typeof target === 'number' ) {
			var $thumb = $gallery.find( '.flex-control-nav li' ).eq( target ).find( 'a, img' ).first();
			if ( $thumb.length ) { $thumb.trigger( 'click' ); }
			return;
		}
		var $link = $gallery.find( '.flex-direction-nav .flex-' + target + ' a' );
		if ( $link.length ) { $link.trigger( 'click' ); }
	}

	// Boutons précédent/suivant sur la galerie WooCommerce (flexslider).
	function initGalleryNav() {
		var $gallery = $( '.woocommerce-product-gallery' );
		if ( ! $gallery.length ) { return; }
		// Déjà initialisé (script chargé deux fois) → on ne duplique pas.
		if ( $gallery.find( '> .aod-gallery-nav' ).length ) { return; }
		// Compter les vraies diapos (hors clones que flexslider ajoute après init).
		var slideCount = $gallery.find( '.woocommerce-product-gallery__image' ).not( '.clone' ).length;
		if ( slideCount < 2 ) { return; } // une seule image : pas de navigation.

		var labels = ( window.AOD_COD && AOD_COD.i18n ) || {};
		var prevLabel = labels.prev_image || 'Image précédente';
		var nextLabel = labels.next_image || 'Image suivante';

		var $prev = $( '<button type="button" class="aod-gallery-nav aod-gallery-nav--prev" aria-label="' + prevLabel + '">‹</button>' );
		var $next = $( '<button type="button" class="aod-gallery-nav aod-gallery-nav--next" aria-label="' + nextLabel + '">›</button>' );

		$prev.on( 'click', function ( e ) { e.preventDefault(); goToSlide( $gallery, 'prev' ); } );
		$next.on( 'click', function ( e ) { e.preventDefault(); goToSlide( $gallery, 'next' ); } );

		$gallery.append( $prev, $next );
	}
} )( jQuery );
