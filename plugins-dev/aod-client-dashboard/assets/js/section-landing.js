/* AOD Client Dashboard — section « Page de vente » (extraction 33.5).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var esc = core.esc;
	var netErr = core.netErr;
	var confirmModal = core.confirmModal;

	/* ==========================================================================
	 * Page de vente : éditeur de blocs (étape 6.2)
	 * ========================================================== */

	/**
	 * Éditeur de la landing produit : ajouter / réordonner / déplier / supprimer des
	 * blocs, puis tout enregistrer en un seul POST multipart.
	 *
	 * Le point délicat est la RENUMÉROTATION. Les champs s'appellent
	 * `blocks[2][title][fr]` : l'index est le rang du bloc à l'écran. Dès qu'on
	 * ajoute, supprime ou déplace un bloc, ces index mentent. On les réécrit donc
	 * depuis le DOM (`reindex`), qui est la seule source de vérité de l'ordre — et
	 * une dernière fois juste avant l'envoi, pour qu'aucune manipulation ne puisse
	 * passer entre les mailles.
	 *
	 * Chaque champ porte son chemin dans `data-n` (`[title][fr]`), l'index du bloc
	 * étant ajouté devant. Les items d'une liste portent le jeton `__j__` à la place
	 * de leur rang : c'est ce qui distingue, dans un même bloc, les champs de l'item
	 * de ceux du bloc lui-même.
	 */
	function bindLanding() {
		var form = document.getElementById( 'aod-cd-landing-form' );
		if ( ! form ) { return; }

		var list  = document.getElementById( 'aod-cd-lp-blocks' );
		var empty = document.getElementById( 'aod-cd-lp-empty' );
		var addBt = document.getElementById( 'aod-cd-lp-addbtn' );
		var type  = document.getElementById( 'aod-cd-lp-type' );

		function blocks() {
			return Array.prototype.slice.call( list.querySelectorAll( ':scope > .aod-cd-lp-block' ) );
		}

		/* Le fichier voyage sous un nom PLAT (`img_<bloc>__<champ>`) : $_FILES ne sait pas
		 * rendre un nom imbriqué à media_handle_upload(). Le `[]` final n'est là que pour
		 * les envois multiples (galerie). Dans un item, le nom porte en plus son rang —
		 * d'où le jeton `%J%` que l'on remplace ici.
		 */
		function fileName( el, i, j ) {
			var key = el.dataset.file.replace( '%J%', j );
			return 'img_' + i + '__' + key + ( el.multiple ? '[]' : '' );
		}

		/* Réécrit tous les `name` depuis l'ordre réel du DOM. */
		function reindex() {
			blocks().forEach( function ( b, i ) {
				b.querySelectorAll( '[data-n]' ).forEach( function ( el ) {
					var n = el.dataset.n;
					// Champ d'item : renuméroté dans la boucle ci-dessous, pas ici.
					if ( n.indexOf( '__j__' ) > -1 ) { return; }
					el.name = 'blocks[' + i + ']' + n;
				} );
				b.querySelectorAll( '[data-file]' ).forEach( function ( el ) {
					if ( el.closest( '.aod-cd-lp-item' ) ) { return; } // Idem : plus bas.
					el.name = fileName( el, i, 0 );
				} );

				// Une liste à la fois : deux listes dans un même bloc auraient sinon des
				// rangs qui se suivent au lieu de repartir de zéro chacune.
				b.querySelectorAll( '.aod-cd-lp-repeat' ).forEach( function ( rep ) {
					rep.querySelectorAll( '.aod-cd-lp-item' ).forEach( function ( it, j ) {
						it.querySelectorAll( '[data-n]' ).forEach( function ( el ) {
							el.name = 'blocks[' + i + ']' + el.dataset.n.replace( '__j__', j );
						} );
						it.querySelectorAll( '[data-file]' ).forEach( function ( el ) {
							el.name = fileName( el, i, j );
						} );
					} );
				} );
			} );
			if ( empty ) { empty.hidden = blocks().length > 0; }
		}

		/* Galerie : retirer une image déjà en place. */
		function bindGalDel( btn ) {
			btn.addEventListener( 'click', function () {
				var item = btn.closest( '.aod-cd-lp-galitem' );
				if ( item ) { item.remove(); }
			} );
		}

		/* Galerie : vignettes des fichiers qui viennent d'être choisis.
		 *
		 * Elles ne portent PAS de champ caché : ces images n'ont pas encore d'ID — c'est
		 * le serveur qui le leur donnera. Re-choisir des fichiers remplace la sélection
		 * (c'est le comportement d'un champ `multiple`), donc on redessine à chaque fois.
		 */
		function previewGallery( input, gal ) {
			var grid = gal.querySelector( '.aod-cd-lp-galgrid' );
			if ( ! grid ) { return; }

			grid.querySelectorAll( '.aod-cd-lp-galnew' ).forEach( function ( n ) {
				if ( n.dataset.url ) { URL.revokeObjectURL( n.dataset.url ); }
				n.remove();
			} );

			Array.prototype.forEach.call( input.files, function ( f ) {
				var url  = URL.createObjectURL( f );
				var item = document.createElement( 'div' );
				item.className   = 'aod-cd-lp-galitem aod-cd-lp-galnew';
				item.dataset.url = url;

				var img = document.createElement( 'img' );
				img.src = url;
				img.alt = '';

				var tag = document.createElement( 'span' );
				tag.className   = 'aod-cd-lp-galtag';
				tag.textContent = CD.i18nGalNew || 'Nouveau';

				item.appendChild( img );
				item.appendChild( tag );
				grid.appendChild( item );
			} );
		}

		/* Un bloc : repli, déplacement, suppression, listes répétables, fichiers. */
		function bindBlock( b ) {
			var toggle = b.querySelector( '.aod-cd-lp-toggle' );
			var body   = b.querySelector( '.aod-cd-lp-body' );
			if ( toggle && body ) {
				toggle.addEventListener( 'click', function () {
					var open = toggle.getAttribute( 'aria-expanded' ) !== 'true';
					toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
					body.hidden = ! open;
					var chev = toggle.querySelector( '.aod-cd-lp-chev' );
					if ( chev ) { chev.textContent = open ? '▾' : '▸'; }
				} );
			}

			b.querySelectorAll( '.aod-cd-lp-mv' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var sib = ( 'up' === btn.dataset.dir ) ? b.previousElementSibling : b.nextElementSibling;
					if ( ! sib || ! sib.classList.contains( 'aod-cd-lp-block' ) ) { return; }
					if ( 'up' === btn.dataset.dir ) {
						list.insertBefore( b, sib );
					} else {
						list.insertBefore( sib, b );
					}
					reindex();
					btn.focus(); // Le focus suit le bloc — sinon il retombe au début de la page.
				} );
			} );

			var del = b.querySelector( '.aod-cd-lp-del' );
			if ( del ) {
				del.addEventListener( 'click', function () {
					confirmModal( {
						message: CD.i18nLpDelBlock || 'Supprimer ce bloc ?',
						danger: true
					} ).then( function ( ok ) {
						if ( ! ok ) { return; }
						b.remove();
						reindex();
					} );
				} );
			}

			bindFiles( b );
			b.querySelectorAll( '.aod-cd-lp-repeat' ).forEach( bindRepeat );
		}

		/* Les champs fichier d'une portion de DOM : bloc entier, ou item fraîchement
		 * ajouté (un avis a sa photo — sans ce second appel, son aperçu resterait mort).
		 */
		function bindFiles( root ) {
			// Retirer un fichier : on remet l'ID à 0 (le champ fichier reste disponible).
			// `querySelectorAll` et non `querySelector` : le bloc vidéo a DEUX fichiers
			// (la couverture et la vidéo) — n'en câbler qu'un rendrait l'autre inerte.
			root.querySelectorAll( '.aod-cd-lp-imgdel, .aod-cd-lp-viddel' ).forEach( function ( del ) {
				var isVid = del.classList.contains( 'aod-cd-lp-viddel' );
				del.addEventListener( 'click', function () {
					var wrap = del.closest( isVid ? '.aod-cd-lp-vid' : '.aod-cd-lp-img' );
					var hid  = wrap && wrap.querySelector( 'input[type=hidden]' );
					var box  = wrap && wrap.querySelector( isVid ? '.aod-cd-lp-vidbox' : '.aod-cd-imgbox' );
					var lbl  = isVid ? ( CD.i18nLpNoVid || 'Aucun fichier' ) : ( CD.i18nLpNoImg || 'Aucune image' );
					if ( hid ) { hid.value = '0'; }
					if ( box ) { box.innerHTML = '<span class="aod-cd-imgempty">' + esc( lbl ) + '</span>'; }
					del.remove();
				} );
			} );

			// Retirer une image de la galerie : la vignette emporte son champ caché, donc
			// sa ligne du POST. Rien d'autre à tenir à jour.
			root.querySelectorAll( '.aod-cd-lp-galdel' ).forEach( bindGalDel );

			// Aperçu immédiat du fichier choisi (le gérant ne devrait pas avoir à
			// enregistrer pour savoir ce qu'il vient de sélectionner).
			root.querySelectorAll( '[data-file]' ).forEach( function ( input ) {
				input.addEventListener( 'change', function () {
					// Galerie : autant de vignettes que de fichiers choisis.
					var gal = input.closest( '.aod-cd-lp-gal' );
					if ( gal ) { previewGallery( input, gal ); return; }

					var file = input.files && input.files[0];
					if ( ! file ) { return; }

					// Vidéo : on n'affiche pas la vidéo, juste son nom — la lire ici
					// coûterait le poids du fichier pour rien.
					var vid = input.closest( '.aod-cd-lp-vid' );
					if ( vid ) {
						var vbox = vid.querySelector( '.aod-cd-lp-vidbox' );
						if ( vbox ) {
							vbox.innerHTML = '<span class="aod-cd-lp-vidname">' + ( ( CD.icons && CD.icons.video ) || '' ) + ' ' + esc( file.name ) + '</span>';
						}
						return;
					}

					var box = input.closest( '.aod-cd-lp-img' );
					box = box && box.querySelector( '.aod-cd-imgbox' );
					if ( ! box ) { return; }
					var img = document.createElement( 'img' );
					img.className = 'aod-cd-imgprev';
					img.alt = '';
					img.src = URL.createObjectURL( file );
					box.innerHTML = '';
					box.appendChild( img );
				} );
			} );
		}

		/* Une liste répétable : ajouter / retirer un item. */
		function bindRepeat( rep ) {
			var items = rep.querySelector( '.aod-cd-lp-items' );
			var tpl   = rep.querySelector( '.aod-cd-lp-itemtpl' );
			var add   = rep.querySelector( '.aod-cd-lp-additem' );

			function bindItem( it ) {
				var x = it.querySelector( '.aod-cd-lp-delitem' );
				if ( x ) {
					x.addEventListener( 'click', function () { it.remove(); reindex(); } );
				}
			}
			items.querySelectorAll( '.aod-cd-lp-item' ).forEach( bindItem );

			if ( add && tpl ) {
				add.addEventListener( 'click', function () {
					var node = tpl.content.firstElementChild.cloneNode( true );
					items.appendChild( node );
					bindItem( node );
					bindFiles( node ); // Un avis neuf a sa photo : son aperçu doit vivre aussi.
					reindex();
					var first = node.querySelector( 'input,textarea' );
					if ( first ) { first.focus(); }
				} );
			}
		}

		blocks().forEach( bindBlock );

		if ( addBt && type ) {
			addBt.addEventListener( 'click', function () {
				var tpl = document.getElementById( 'aod-cd-lp-tpl-' + type.value );
				if ( ! tpl ) { return; }
				var node = tpl.content.firstElementChild.cloneNode( true );
				list.appendChild( node );
				bindBlock( node );
				reindex();
				node.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				var focusable = node.querySelector( '.aod-cd-lp-toggle' );
				if ( focusable ) { focusable.focus(); }
			} );
		}

		/* « Partir d'un modèle » : la structure type, en blocs VIDES. La liste des
		 * types vient du format (data-blocks, rempli depuis AOD_COD_Landing::starter()) ;
		 * ici on ne fait que cloner les gabarits. Un type sans gabarit est sauté —
		 * un modèle plus récent que ce clone ne doit pas casser le bouton.
		 */
		var starter = document.getElementById( 'aod-cd-lp-starterbtn' );
		if ( starter ) {
			starter.addEventListener( 'click', function () {
				( starter.dataset.blocks || '' ).split( ',' ).forEach( function ( t ) {
					var tpl = document.getElementById( 'aod-cd-lp-tpl-' + t );
					if ( ! tpl ) { return; }
					var node = tpl.content.firstElementChild.cloneNode( true );
					list.appendChild( node );
					bindBlock( node );
				} );
				reindex();
				var first = list.querySelector( '.aod-cd-lp-block .aod-cd-lp-toggle' );
				if ( first ) { first.focus(); }
			} );
		}

		/* Copier la page de vente d'un autre produit. Destructif (elle remplace les
		 * blocs à l'écran) : on prévient si du travail existe. Le serveur éteint la
		 * copie quoi qu'il arrive — ses textes parlent de l'autre produit.
		 */
		var copyBt  = document.getElementById( 'aod-cd-lp-copybtn' );
		var copySrc = document.getElementById( 'aod-cd-lp-copysrc' );
		if ( copyBt && copySrc ) {
			copyBt.addEventListener( 'click', function () {
				// 🚨 QUESTION CONDITIONNELLE : rien à l'écran, rien à écraser, donc
				// aucune fenêtre. On garde UN SEUL chemin de code — une promesse déjà
				// résolue plutôt qu'un `if` qui dupliquerait la suite. Deux branches
				// divergeraient au premier correctif, et c'est la moins lue qui
				// dériverait.
				var demande = blocks().length
					? confirmModal( {
						message: CD.i18nLpCopyConfirm || 'Remplacer les blocs actuels ?',
						danger: true
					} )
					: Promise.resolve( true );

				demande.then( function ( ok ) {
				if ( ! ok ) { return; }
				copyBt.disabled = true;

				var data = new FormData();
				data.append( 'action', 'aod_cd_copy_landing' );
				data.append( 'nonce', CD.nonce );
				data.append( 'product_id', form.querySelector( '[name="product_id"]' ).value );
				data.append( 'source_id', copySrc.value );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res && res.success ) {
							toast( ( res.data && res.data.message ) || CD.i18nSaved || 'OK' );
							// On recharge : l'écran doit montrer ce qui est EN BASE.
							setTimeout( function () { window.location.reload(); }, 900 );
							return;
						}
						copyBt.disabled = false;
						toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
					} )
					.catch( function () {
						copyBt.disabled = false;
						toast( netErr, true );
					} );
				} ); // fin de la continuation confirmModal (47.7)
			} );
		}

		reindex();

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			reindex(); // Dernier mot au DOM : l'ordre à l'écran est l'ordre enregistré.

			var btn = form.querySelector( 'button[type="submit"]' );
			var msg = form.querySelector( '.aod-cd-form-msg' );
			btn.disabled = true;
			if ( msg ) { msg.textContent = ''; msg.classList.remove( 'is-bad' ); }

			var data = new FormData( form );
			data.append( 'action', 'aod_cd_save_landing' );
			data.append( 'nonce', CD.nonce );

			fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) {
						// Une image refusée par WordPress n'est PAS un succès muet : le texte
						// est bien enregistré, l'image non — on le dit, et on laisse le temps
						// de le lire avant de recharger.
						var warn = res.data && res.data.warning;
						toast( warn || ( res.data && res.data.message ) || CD.i18nSaved || 'Enregistré', !! warn );
						if ( msg && warn ) { msg.textContent = warn; msg.classList.add( 'is-bad' ); }
						// On recharge : les images téléversées ont reçu leur ID, les blocs
						// leur identifiant. L'écran doit montrer ce qui est EN BASE, pas ce
						// qu'on croit y avoir mis.
						setTimeout( function () { window.location.reload(); }, warn ? 4000 : 700 );
						return;
					}
					btn.disabled = false;
					var m = ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.';
					if ( msg ) { msg.textContent = m; msg.classList.add( 'is-bad' ); }
					toast( m, true );
				} )
				.catch( function () {
					btn.disabled = false;
					toast( netErr, true );
				} );
		} );
	}

	core.register( 'bindLanding', bindLanding );
}( window.aodCdCore ) );
