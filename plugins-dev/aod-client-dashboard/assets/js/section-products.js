/* AOD Client Dashboard — section « Produits & Catégories » (extraction 33.4).
   IIFE à ALIAS LOCAUX : les corps de fonctions restent VERBATIM — ils
   consomment les helpers de la coque (dashboard.js) sous leur nom d'origine. */
( function ( core ) {
	'use strict';

	var CD = core.CD;
	var toast = core.toast;
	var escAttr = core.escAttr;
	var openOverlayModal = core.openOverlayModal;
	var init = core.init;
	var netErr = core.netErr;
	var confirmModal = core.confirmModal;

	/**
	 * Le fichier est-il trop lourd ? — CONFORT, jamais la barrière (47.6, D6).
	 *
	 * 🚨 CE CONTRÔLE NE PROTÈGE RIEN, et c'est écrit ici pour que personne ne s'y
	 * fie : il se contourne en postant directement, comme les `accept="image/*"`
	 * dont `AOD_CD_Upload` dit déjà qu'ils sont « un confort de sélecteur de
	 * fichiers ». La VRAIE barrière est `AOD_CD_Upload::handle()`, côté serveur.
	 * Ce qu'il apporte : éviter d'attendre le téléversement d'un fichier qu'on
	 * sait déjà refusé — sur une connexion mobile algérienne, ce n'est pas rien.
	 *
	 * La limite vient de `CD.maxUpload`, lue à la SOURCE (`max_bytes()`). Aucun
	 * nombre n'est écrit deux fois : le message affiché ici et le refus du serveur
	 * ne peuvent pas diverger.
	 *
	 * @param {File} f Fichier choisi.
	 * @return {boolean} Vrai si le fichier dépasse la limite.
	 */
	function tropLourd( f ) {
		var max = parseInt( CD.maxUpload, 10 );
		return !! f && max > 0 && f.size > max;
	}

	/* Produits : aperçu image, toggle stock, enregistrement, suppression */
	function bindProducts() {
		// Aperçu de la photo choisie.
		var file = document.querySelector( '.aod-cd-imgfile' );
		if ( file ) {
			file.addEventListener( 'change', function () {
				var prev  = document.querySelector( '.aod-cd-imgprev' );
				var empty = document.querySelector( '.aod-cd-imgempty' );
				if ( file.files && file.files[0] ) {
					var url = URL.createObjectURL( file.files[0] );
					if ( prev )  { prev.src = url; prev.style.display = ''; }
					if ( empty ) { empty.style.display = 'none'; }
				}
			} );
		}

		// Affiche/masque le champ quantité selon « Gérer le stock ».
		var toggle = document.querySelector( '.aod-cd-stock-toggle' );
		if ( toggle ) {
			toggle.addEventListener( 'change', function () {
				var qty = document.querySelector( '.aod-cd-stock-qty' );
				if ( qty ) { qty.style.display = toggle.checked ? '' : 'none'; }
			} );
		}

		// Éditeur riche de la description (8.1) — TinyMCE, chargé par la coque
		// UNIQUEMENT sur le formulaire produit. Garde `window.tinymce` : sans lui
		// (JS bloqué, vieux cache), les textareas nus restent le repli complet.
		// Un init PAR SENS : la case FR édite en LTR, la AR en RTL, quel que soit
		// le sens de la page. `language` est OBLIGATOIRE : sans lui TinyMCE reste
		// sur son défaut "en" et ignore les traductions addI18n imprimées par la
		// coque (packs cœur).
		if ( window.tinymce && document.querySelector( '.aod-cd-rich' ) ) {
			// Les libellés de formats viennent des traductions (.po) : « = » et « ; »
			// sont STRUCTURELS dans block_formats. On les neutralise pour qu'une
			// retouche de traduction ne puisse pas casser le menu déroulant.
			var fmt = function ( s ) { return String( s || '' ).replace( /[=;]/g, ' ' ).trim(); };

			// Bouton image de la barre (8.2) : upload IMMÉDIAT via l'endpoint
			// `aod_cd_desc_image`, puis insertion d'un <img> minimal AU POINT
			// D'INSERTION de l'éditeur QUI A CLIQUÉ (`ed` de la closure — jamais
			// `tinymce.activeEditor` : le focus peut avoir changé de case pendant
			// l'upload, l'image partirait dans la mauvaise langue). Le srcset/lazy
			// sont ajoutés AU RENDU public par `wp_filter_content_tags` depuis la
			// classe `wp-image-{id}` — on ne les stocke pas (kses les strippe).
			var pickImage = function ( ed ) {
				var input = document.getElementById( 'aod-cd-descimg-input' );
				if ( ! input ) {
					input = document.createElement( 'input' );
					input.type = 'file';
					input.accept = 'image/*'; // Confort : la vraie barrière est la whitelist serveur.
					input.id = 'aod-cd-descimg-input';
					input.style.display = 'none';
					document.body.appendChild( input );
				}
				input.onchange = function () {
					if ( ! input.files || ! input.files[0] ) { return; }
					// Confort (47.6) : on n'envoie pas ce qui sera refusé de toute façon.
					if ( tropLourd( input.files[0] ) ) { input.value = ''; toast( CD.i18nTooBig, true ); return; }
					var f = document.getElementById( 'aod-cd-product-form' );
					var pidEl = f ? f.querySelector( '[name="product_id"]' ) : null;
					var fd = new FormData();
					fd.append( 'action', 'aod_cd_desc_image' );
					fd.append( 'nonce', CD.nonce );
					fd.append( 'product_id', pidEl ? pidEl.value : '0' );
					fd.append( 'file', input.files[0] );
					input.value = ''; // Sinon re-choisir le MÊME fichier ne redéclenche pas `change`.
					fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							if ( res && res.success && res.data && res.data.url ) {
								ed.insertContent(
									'<img class="wp-image-' + res.data.id + '" src="' + res.data.url +
									'" alt="" width="' + res.data.width + '" height="' + res.data.height + '" />'
								);
							} else {
								toast( ( res && res.data && res.data.message ) || CD.i18nNetErr, true );
							}
						} )
						.catch( function () { toast( CD.i18nNetErr, true ); } );
				};
				input.click();
			};

			// Bouton vidéo de la barre (8.3) : une modale offre DEUX entrées —
			// un lien YouTube OU un fichier local. YouTube → on insère un LIEN
			// marqué (`aod-desc-video`) que le rendu public convertit en façade
			// (0 iframe, 0 requête avant le clic). Fichier → upload immédiat via
			// `aod_cd_desc_video`, puis un `<video src>` natif (kses le conserve ;
			// aucune façade ni JS côté public). Toujours dans le `ed` de la closure.
			// Reflet EXACT de AOD_COD_Landing::youtube_id() (serveur, source de vérité) :
			// même whitelist d'hôtes (après retrait de `www.`), même exigence de 11
			// caractères STRICTS. Un ancien regex approximatif divergeait du serveur
			// (revue 8.3) — il rejetait `youtube-nocookie.com` pourtant valide, et
			// acceptait un hôte sosie (`myyoutube.com`) ou un v= trop long en le
			// tronquant, produisant un lien inséré que le rendu laissait inerte.
			var ytId = function ( url ) {
				var u;
				try {
					u = new URL( String( url == null ? '' : url ).trim() );
				} catch ( e ) {
					return ''; // Pas d'URL absolue (scheme manquant) → le serveur refuse aussi.
				}
				var host = u.hostname.toLowerCase().replace( /^www\./, '' );
				var id = '';
				if ( 'youtu.be' === host ) {
					id = u.pathname.replace( /^\/+|\/+$/g, '' );
				} else if ( 'youtube.com' === host || 'm.youtube.com' === host || 'youtube-nocookie.com' === host ) {
					var v = u.searchParams.get( 'v' );
					if ( v ) {
						id = v;
					} else {
						var pm = u.pathname.match( /^\/(?:embed|shorts|live|v)\/([^/?#]+)/ );
						if ( pm ) { id = pm[ 1 ]; }
					}
				}
				return /^[A-Za-z0-9_-]{11}$/.test( id ) ? id : '';
			};
			var pickVideo = function ( ed ) {
				var body =
					'<label class="aod-cd-field"><span>' + escAttr( CD.i18nVideoUrl ) + '</span>' +
						'<input type="url" class="aod-cd-textinput" data-url placeholder="https://youtu.be/…" inputmode="url">' +
					'</label>' +
					'<label class="aod-cd-field"><span>' + escAttr( CD.i18nVideoFile ) + '</span>' +
						'<input type="file" accept="video/*" data-file>' +
					'</label>' +
					'<p class="aod-cd-help">' + escAttr( CD.i18nVideoHint ) + '</p>' +
					'<div class="aod-cd-modal-actions">' +
						'<button type="button" class="aod-cd-btn" data-cancel>' + escAttr( CD.i18nCancel || 'Annuler' ) + '</button>' +
						'<button type="button" class="aod-cd-btn aod-cd-btn-primary" data-ok>' + escAttr( CD.i18nInsert || 'Insérer' ) + '</button>' +
					'</div>';
				var modal   = openOverlayModal( { title: CD.i18nVideoTitle, bodyHtml: body, initialFocus: '[data-url]' } );
				var overlay = modal.overlay;
				var close   = modal.close;
				var urlEl   = overlay.querySelector( '[data-url]' );
				var fileEl  = overlay.querySelector( '[data-file]' );
				var okBtn   = overlay.querySelector( '[data-ok]' );

				okBtn.addEventListener( 'click', function () {
					// 1) Un fichier prime : upload immédiat → <video> natif.
					if ( fileEl.files && fileEl.files[0] ) {
						// Confort (47.6) : même garde que l'insertion d'image.
						if ( tropLourd( fileEl.files[0] ) ) { toast( CD.i18nTooBig, true ); return; }
						var f = document.getElementById( 'aod-cd-product-form' );
						var pidEl = f ? f.querySelector( '[name="product_id"]' ) : null;
						var fd = new FormData();
						fd.append( 'action', 'aod_cd_desc_video' );
						fd.append( 'nonce', CD.nonce );
						fd.append( 'product_id', pidEl ? pidEl.value : '0' );
						fd.append( 'file', fileEl.files[0] );
						okBtn.disabled = true;
						fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
							.then( function ( r ) { return r.json(); } )
							.then( function ( res ) {
								if ( res && res.success && res.data && res.data.url ) {
									ed.insertContent(
										'<video class="aod-desc-video-file" src="' + escAttr( res.data.url ) +
										'" controls preload="none" playsinline></video>'
									);
									close();
								} else {
									okBtn.disabled = false;
									toast( ( res && res.data && res.data.message ) || CD.i18nNetErr, true );
								}
							} )
							.catch( function () { okBtn.disabled = false; toast( CD.i18nNetErr, true ); } );
						return;
					}
					// 2) Sinon un lien YouTube : validation, puis lien marqué.
					var url = ( urlEl.value || '' ).trim();
					if ( ! url ) { close(); return; }
					if ( '' === ytId( url ) ) { toast( CD.i18nVideoBad, true ); return; }
					ed.insertContent( '<a class="aod-desc-video" href="' + escAttr( url ) + '">' + escAttr( url ) + '</a>' );
					close();
				} );
			};

			[ 'ltr', 'rtl' ].forEach( function ( dir ) {
				window.tinymce.init( {
					selector: '.aod-cd-rich[data-rich-dir="' + dir + '"]',
					language: CD.mceLocale || undefined,
					directionality: dir, // Option CŒUR de TinyMCE 4 : le plugin `directionality` (boutons ltr/rtl) est inutile ici.
					// 🚨 `wpautoresize`, PAS `autoresize` (47.6). Mesuré dans le
					// conteneur : `wp-includes/js/tinymce/plugins/` ne contient QUE le
					// fork WordPress — déclarer `autoresize` ferait chercher un
					// `plugins/autoresize/plugin.min.js` inexistant.
					plugins: 'lists link paste wpautoresize',
					// L'INTERRUPTEUR, et il n'est pas décoratif : tout le plugin est
					// conditionné à ce drapeau (`plugin.js:160,171`). Sans lui il se
					// charge et ne fait RIEN — un test statique qui se contenterait de
					// voir « wpautoresize » plus haut serait vert sur un éditeur inerte.
					wp_autoresize_on: true,
					// PAS DE `autoresize_min_height` — et c'est un choix mesuré, pas un
					// oubli. Le défaut du plugin est `getElement().offsetHeight`
					// (`plugin.js:141`), c'est-à-dire la hauteur du <textarea>, donc
					// `rows="6"` : vérifié à 132 px sur une description courte, la
					// hauteur EXACTE d'avant la 47.6. Le coder en dur ici en ferait une
					// seconde source de vérité, qui divergerait en silence le jour où
					// quelqu'un touche `rows` — le défaut de propagation que ce dépôt
					// passe son temps à fermer.
					// ⚠️ Une première rédaction posait `autoresize_min_height: 132` en
					// affirmant que sans lui l'éditeur s'ouvrait collé au plafond
					// (500 px mesurés). C'ÉTAIT UNE MAUVAISE LECTURE DE LA MESURE : le
					// produit témoin avait une description ARABE de 2 124 caractères, et
					// `.aod-cd-lp-langs` est une grille `1fr 1fr` — la case FR hérite
					// donc de la hauteur de l'AR. On mesurait le contenu de l'autre
					// case, pas l'absence de plancher.
					// Le PLAFOND, lui, est nécessaire : sans lui on remplacerait un défaut par un autre :
					// une description longue pousserait « Enregistrer » hors de l'écran.
					// Les deux cases sont CÔTE À CÔTE : la hauteur du bloc est le max
					// des deux, jamais leur somme.
					autoresize_max_height: 500,
					toolbar: 'formatselect bold italic | bullist numlist | alignleft aligncenter alignright | link aodimage aodvideo | undo redo',
					block_formats: fmt( CD.i18nFmtP ) + '=p;' + fmt( CD.i18nFmtH2 ) + '=h2;' + fmt( CD.i18nFmtH3 ) + '=h3',
					// Les boutons image (8.2) et vidéo (8.3). `setup` reçoit l'éditeur
					// `ed` de CETTE case → l'insertion vise la bonne langue.
					setup: function ( ed ) {
						ed.addButton( 'aodimage', {
							icon: 'image',
							tooltip: CD.i18nImgBtn,
							onclick: function () { pickImage( ed ); }
						} );
						ed.addButton( 'aodvideo', {
							icon: 'media', // glyphe présent dans le skin (mce-i-media) ; `embed` ne l'est pas.
							tooltip: CD.i18nVideoBtn,
							onclick: function () { pickVideo( ed ); }
						} );
					},
					// La barre ne propose NI couleur NI police : on restreint les
					// styles inline au seul `text-align` (ce que produisent nos
					// boutons d'alignement). Sans ça, un collage depuis Word ou un
					// site ferait entrer color/font-family/font-size — que
					// wp_kses_post conserve — et contournerait la barre.
					valid_styles: { '*': 'text-align' },
					// Sans ça, TinyMCE STRIPPE le <video> (fichier local) à la
					// sérialisation : il n'est pas dans son valid_elements par défaut.
					extended_valid_elements: 'video[class|src|controls|preload|playsinline|width|height|poster]',
					// URLs ABSOLUES en base (comme l'éditeur WP lui-même) : par défaut
					// TinyMCE réécrit une URL insérée en relatif (« ../../wp-content/… »),
					// qui ne résout plus si la fiche est servie à une autre profondeur.
					// La src d'un <video>/<img> et le href d'un lien doivent rester absolus.
					convert_urls: false,
					menubar: false,
					statusbar: false,
					branding: false,
					// L'iframe TinyMCE remplace le <textarea> et hérite d'un titre
					// GÉNÉRIQUE identique pour les deux cases → FR et AR indiscernables
					// au lecteur d'écran. On recopie l'aria-label distinctif du
					// textarea (« … — français » / « … — arabe ») sur l'iframe voisine
					// (même wrapper .aod-cd-lp-lang). init_instance_callback garantit
					// que l'iframe existe. Le réglage `aria_label` ne pilote pas le
					// titre dans le TinyMCE embarqué par WP — vérifié.
					init_instance_callback: function ( ed ) {
						var el   = ed.getElement();
						var lbl  = el && el.getAttribute( 'aria-label' );
						var wrap = el && el.closest( '.aod-cd-lp-lang' );
						var ifr  = wrap && wrap.querySelector( 'iframe' );
						if ( lbl && ifr ) { ifr.setAttribute( 'title', lbl ); }
					}
				} );
			} );
		}

		// Enregistrement du produit (formulaire multipart via FormData).
		// IMPORTANT : on attache le handler de soumission AVANT les bindings
		// optionnels (options / paliers / packs). Si l'un d'eux levait une
		// exception, le « submit » resterait quand même branché : sans lui, le
		// navigateur ferait une soumission native du <form> et toutes les
		// saisies (description, stock, prix…) seraient perdues au rechargement.
		var form = document.getElementById( 'aod-cd-product-form' );
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var btn = form.querySelector( 'button[type="submit"]' );
				var msg = form.querySelector( '.aod-cd-form-msg' );
				btn.disabled = true;
				if ( msg ) { msg.textContent = ''; }

				// 🔴 AVANT FormData : TinyMCE ne resynchronise les <textarea>
				// que sur demande. Sans ce triggerSave, la description postée
				// serait celle d'AVANT la saisie — perte silencieuse, aucune
				// erreur à l'écran.
				if ( window.tinymce ) { window.tinymce.triggerSave(); }

				var data = new FormData( form );
				data.append( 'action', 'aod_cd_save_product' );
				data.append( 'nonce', CD.nonce );

				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res && res.success ) {
							// Un avertissement (ex. palier ignoré) reste affiché plus longtemps avant la redirection.
							if ( res.data && res.data.warning ) {
								toast( res.data.warning, true );
								setTimeout( function () { window.location.href = res.data.redirect || CD.base; }, 4000 );
							} else {
								toast( res.data.message || 'OK', false );
								setTimeout( function () { window.location.href = res.data.redirect || CD.base; }, 700 );
							}
						} else {
							btn.disabled = false;
							var m = ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.';
							if ( msg ) { msg.textContent = m; msg.classList.add( 'is-bad' ); }
							toast( m, true );
						}
					} )
					.catch( function () {
						btn.disabled = false;
						toast( netErr, true );
					} );
			} );
		}

		// Bindings optionnels : isolés pour qu'une erreur dans l'un (données
		// inattendues, élément absent…) n'empêche jamais les autres ni la
		// soumission ci-dessus de fonctionner — mais plus en silence (32.5).
		init( 'bindOptions', bindOptions );
		init( 'bindOfferRows', bindOfferRows );

		// Suppression (corbeille) avec confirmation.
		document.querySelectorAll( '.aod-cd-del-product' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var name = b.dataset.name || '';
				var delMsg = ( CD.i18nProductDelConfirm || 'Supprimer « %s » ? (déplacé dans la corbeille)' ).replace( '%s', name );
				confirmModal( { message: delMsg, danger: true } ).then( function ( ok ) {
				if ( ! ok ) { return; }
				b.disabled = true;
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_delete_product' );
				body.append( 'nonce', CD.nonce );
				body.append( 'product_id', b.dataset.product );
				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res && res.success ) {
							var row = b.closest( 'tr' );
							if ( row ) { row.style.opacity = '0.4'; row.remove(); }
							toast( res.data.message || CD.i18nDeleted || 'Supprimé', false );
						} else {
							b.disabled = false;
							toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
						}
					} )
					.catch( function () { b.disabled = false; toast( netErr, true ); } );
				} ); // fin de la continuation confirmModal (47.7)
			} );
		} );

		/* Duplication : même patron que la suppression ci-dessus, SANS confirmation
		   — l'action est réversible (la copie naît en brouillon) et une modale sur
		   un geste anodin apprend à cliquer « oui » sans lire, ce qui affaiblit
		   celle de la suppression. On mène directement à la copie : dupliquer sans
		   aller la renommer n'a aucun intérêt, et deux fiches au même nom traînent. */
		document.querySelectorAll( '.aod-cd-dup-product' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				b.disabled = true;
				var body = new URLSearchParams();
				body.append( 'action', 'aod_cd_duplicate_product' );
				body.append( 'nonce', CD.nonce );
				body.append( 'product_id', b.dataset.product );
				fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res && res.success ) {
							// Repli sur une clé EXISTANTE : `dashboard.js` est servi par la
							// coque, qui est à son plafond de lignes (cf. test-decomposition
							// R1). Le serveur envoie toujours son message ici — ce repli ne
							// se déclenche pas, il ne justifie pas une chaîne de plus.
							toast( res.data.message || CD.i18nSaved, false );
							if ( res.data.edit_url ) { window.location.href = res.data.edit_url; }
						} else {
							b.disabled = false;
							toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
						}
					} )
					.catch( function () { b.disabled = false; toast( netErr, true ); } );
			} );
		} );
	}

	/* Sections d'options (Taille, Couleur, Pointure…) : sections + valeurs ajoutables */
	function bindOptions() {
		var wrap = document.getElementById( 'aod-cd-options' );
		if ( ! wrap ) { return; }
		var sectionsBox = wrap.querySelector( '.aod-cd-opt-sections' );
		var addSecBtn   = wrap.querySelector( '.aod-cd-opt-add' );
		var secTpl      = document.getElementById( 'aod-cd-opt-section-tpl' );
		if ( ! sectionsBox || ! addSecBtn || ! secTpl ) { return; }

		function makeSection() {
			var si   = parseInt( addSecBtn.dataset.next, 10 ) || 0;
			addSecBtn.dataset.next = String( si + 1 );
			var html = secTpl.innerHTML.replace( /\{SI\}/g, String( si ) );
			var tmp  = document.createElement( 'div' );
			tmp.innerHTML = html.trim();
			var node = tmp.firstElementChild;
			sectionsBox.appendChild( node );
			refreshStockGuard();
			return node;
		}

		function addValue( section, value ) {
			var valuesBox = section.querySelector( '.aod-cd-opt-values' );
			var addBtn    = section.querySelector( '.aod-cd-opt-val-add' );
			var tpl       = section.querySelector( '.aod-cd-opt-val-tpl' );
			if ( ! valuesBox || ! addBtn || ! tpl ) { return null; }
			var vi   = parseInt( addBtn.dataset.next, 10 ) || 0;
			addBtn.dataset.next = String( vi + 1 );
			var html = tpl.innerHTML.replace( /\{VI\}/g, String( vi ) );
			var tmp  = document.createElement( 'div' );
			tmp.innerHTML = html.trim();
			var node = tmp.firstElementChild;
			valuesBox.appendChild( node );
			if ( value != null ) {
				var nameInput = node.querySelector( '.aod-cd-opt-name' );
				if ( nameInput ) { nameInput.value = value; }
			}
			refreshStockGuard();
			return node;
		}

		function setVisual( section, on ) {
			var cb        = section.querySelector( '.aod-cd-opt-visual-cb' );
			var valuesBox = section.querySelector( '.aod-cd-opt-values' );
			if ( cb ) { cb.checked = !! on; }
			if ( valuesBox ) { valuesBox.classList.toggle( 'is-visual', !! on ); }
		}

		// 40.2 — Garde-fou mono-axe : ÉCHO de la règle serveur (normalize_options).
		// Dès qu'un axe porte un stock, les champs stock des AUTRES axes sont grisés.
		// « L'axe à stock » = le PREMIER (ordre DOM) ayant une valeur non vide — même
		// règle que le serveur (40.1). Confort pur : le serveur re-normalise à
		// l'enregistrement, donc contourner ce grisage ne corrompt jamais la donnée.
		function refreshStockGuard() {
			var sections = Array.prototype.slice.call( sectionsBox.querySelectorAll( '.aod-cd-opt-section' ) );
			var axis = -1;
			sections.forEach( function ( sec, i ) {
				if ( axis !== -1 ) { return; }
				var filled = Array.prototype.slice.call( sec.querySelectorAll( '.aod-cd-opt-stock' ) ).some( function ( inp ) {
					return String( inp.value ).trim() !== '';
				} );
				if ( filled ) { axis = i; }
			} );
			sections.forEach( function ( sec, i ) {
				var off = ( axis !== -1 && i !== axis );
				sec.querySelectorAll( '.aod-cd-opt-stock' ).forEach( function ( inp ) {
					inp.disabled = off;
					if ( off ) {
						if ( ! inp.dataset.baseTitle ) { inp.dataset.baseTitle = inp.title; }
						if ( inp.dataset.guard ) { inp.title = inp.dataset.guard; }
					} else if ( inp.dataset.baseTitle ) {
						inp.title = inp.dataset.baseTitle;
					}
				} );
			} );
		}

		// + Ajouter une section (vide, avec une valeur vierge).
		addSecBtn.addEventListener( 'click', function () {
			var section = makeSection();
			var label   = section.querySelector( '.aod-cd-opt-label' );
			if ( label ) { label.focus(); }
		} );

		// Sections rapides (presets) : crée une section pré-remplie.
		wrap.querySelectorAll( '.aod-cd-opt-preset' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var section = makeSection();
				var label   = section.querySelector( '.aod-cd-opt-label' );
				if ( label ) { label.value = btn.dataset.label || ''; }
				var visual = btn.dataset.visual === '1';
				setVisual( section, visual );
				var values = ( btn.dataset.values || '' ).split( ',' ).map( function ( s ) {
					return s.trim();
				} ).filter( Boolean );
				// La section neuve possède déjà une valeur vierge : on la garnit puis on complète.
				if ( values.length ) {
					var firstEmpty = section.querySelector( '.aod-cd-opt-name' );
					if ( firstEmpty ) { firstEmpty.value = values.shift(); }
					values.forEach( function ( v ) { addValue( section, v ); } );
				}
				if ( label ) { label.focus(); }
			} );
		} );

		// Délégation : ajout de valeur, suppressions de valeur/section.
		wrap.addEventListener( 'click', function ( e ) {
			var addVal = e.target.closest( '.aod-cd-opt-val-add' );
			if ( addVal ) {
				var sec  = addVal.closest( '.aod-cd-opt-section' );
				var node = sec ? addValue( sec, null ) : null;
				if ( node ) {
					var n = node.querySelector( '.aod-cd-opt-name' );
					if ( n ) { n.focus(); }
				}
				return;
			}
			var valDel = e.target.closest( '.aod-cd-opt-val-del' );
			if ( valDel ) {
				var row = valDel.closest( '.aod-cd-opt-value' );
				if ( row ) { row.remove(); }
				refreshStockGuard();
				return;
			}
			var secDel = e.target.closest( '.aod-cd-opt-sec-del' );
			if ( secDel ) {
				var s = secDel.closest( '.aod-cd-opt-section' );
				if ( s ) { s.remove(); }
				refreshStockGuard();
			}
		} );

		// 40.2 — ré-évalue le garde-fou mono-axe à chaque saisie dans un champ stock.
		wrap.addEventListener( 'input', function ( e ) {
			if ( e.target.closest( '.aod-cd-opt-stock' ) ) { refreshStockGuard(); }
		} );

		refreshStockGuard(); // état initial (produit déjà doté d'un stock de variante).

		// Délégation : toggle « avec photos » + aperçu des photos de valeurs.
		wrap.addEventListener( 'change', function ( e ) {
			var cb = e.target.closest( '.aod-cd-opt-visual-cb' );
			if ( cb ) {
				var sec       = cb.closest( '.aod-cd-opt-section' );
				var valuesBox = sec ? sec.querySelector( '.aod-cd-opt-values' ) : null;
				if ( valuesBox ) { valuesBox.classList.toggle( 'is-visual', cb.checked ); }
				return;
			}
			var file = e.target.closest( '.aod-cd-opt-imgfile' );
			if ( file && file.files && file.files[0] ) {
				var box   = file.closest( '.aod-cd-opt-imgbox' );
				var prev  = box ? box.querySelector( '.aod-cd-opt-imgprev' ) : null;
				var empty = box ? box.querySelector( '.aod-cd-opt-imgempty' ) : null;
				var url   = URL.createObjectURL( file.files[0] );
				if ( prev )  { prev.src = url; prev.style.display = ''; }
				if ( empty ) { empty.style.display = 'none'; }
			}
		} );
	}

	/* Offres (prix par quantité) : lignes ajoutables + aperçu de l'économie par offre */
	function bindOfferRows() {
		var wrap = document.getElementById( 'aod-cd-offers' );
		if ( ! wrap ) { return; }
		var rows   = wrap.querySelector( '.aod-cd-offer-rows' );
		var tpl    = document.getElementById( 'aod-cd-offer-tpl' );
		var addBtn = wrap.querySelector( '.aod-cd-offer-add' );

		if ( addBtn && tpl && rows ) {
			addBtn.addEventListener( 'click', function () {
				var next = parseInt( addBtn.dataset.next, 10 ) || 0;
				var html = tpl.innerHTML.replace( /__i__/g, String( next ) );
				var tmp  = document.createElement( 'div' );
				tmp.innerHTML = html.trim();
				var node = tmp.firstChild;
				rows.appendChild( node );
				addBtn.dataset.next = String( next + 1 );
				var first = node.querySelector( 'input' );
				if ( first ) { first.focus(); }
			} );
		}

		wrap.addEventListener( 'click', function ( e ) {
			var del = e.target.closest( '.aod-cd-offer-del' );
			if ( del ) {
				var row = del.closest( '.aod-cd-offer-row' );
				if ( row ) { row.remove(); updateOffers(); }
			}
		} );

		wrap.addEventListener( 'change', updateOffers );
		wrap.addEventListener( 'input', updateOffers );

		// Aperçu par offre : économie = prix normal × nb d'unités − prix du lot.
		function updateOffers() {
			if ( ! rows ) { return; }
			var base = parseFloat( ( document.querySelector( 'input[name="regular_price"]' ) || {} ).value ) || 0;
			rows.querySelectorAll( '.aod-cd-offer-row' ).forEach( function ( row ) {
				var qtyEl   = row.querySelector( 'input[name^="offer_qty"]' );
				var priceEl = row.querySelector( 'input[name^="offer_price"]' );
				var hint    = row.querySelector( '.aod-cd-offer-eco' );
				if ( ! hint ) {
					hint = document.createElement( 'span' );
					hint.className = 'aod-cd-offer-eco';
					row.appendChild( hint );
				}
				var qty   = qtyEl ? ( parseInt( qtyEl.value, 10 ) || 0 ) : 0;
				var total = priceEl ? ( parseFloat( priceEl.value ) || 0 ) : 0;
				if ( base > 0 && qty >= 2 && total > 0 && total < base * qty ) {
					var eco = Math.round( ( base * qty - total ) * 100 ) / 100;
					hint.textContent = ( CD.i18nEco || 'Économie : %s' ).replace( '%s', eco.toLocaleString( 'fr-DZ' ) );
					hint.classList.remove( 'is-bad' );
				} else if ( qty >= 2 && total > 0 ) {
					hint.textContent = CD.i18nNoDiscount || 'Aucune réduction (sera ignorée)';
					hint.classList.add( 'is-bad' );
				} else {
					hint.textContent = '';
					hint.classList.remove( 'is-bad' );
				}
			} );
		}

		updateOffers();
	}

	/* Catégories : création, renommage et suppression (page « Catégories ») */
	function bindCategories() {
		var wrap = document.getElementById( 'aod-cd-cats' );
		if ( ! wrap ) { return; }
		var list    = wrap.querySelector( '.aod-cd-cat-list' );
		var newForm = document.getElementById( 'aod-cd-cat-new' );
		var tpl     = document.getElementById( 'aod-cd-cat-row-tpl' );

		/**
		 * Le nom arabe saisi dans une ligne de catégorie.
		 *
		 * Tout envoi qui reposte `name` DOIT reposter `name_ar` : le serveur supprime la
		 * méta quand la valeur est vide, donc un envoi partiel (retrait/ajout d'image)
		 * effacerait la traduction sans rien dire. Le `|| ''` couvre le <template> servi
		 * depuis le cache navigateur, antérieur au champ arabe.
		 */
		function catNameAr( row ) {
			var el = row.querySelector( '.aod-cd-cat-name-ar' );
			return el ? ( el.value || '' ).trim() : '';
		}

		/**
		 * Le réglage « à la une » d'une LIGNE (case + ordre) — 9.1.
		 *
		 * À l'INVERSE de `catNameAr` : ces champs ne sont envoyés QUE par la création et
		 * l'enregistrement, jamais par les chemins image. Côté serveur, une clé absente
		 * laisse la méta intacte (patron `parent`, cf. `home_meta_ops`) — donc changer une
		 * image ne peut ni décocher ni réordonner. `|| ''`/`|| 0` couvrent un <template>
		 * servi depuis le cache navigateur, antérieur à ces champs.
		 */
		function catHome( row ) {
			var el = row.querySelector( '.aod-cd-cat-home' );
			return el && el.checked ? '1' : '0';
		}
		function catHomeOrder( row ) {
			var el = row.querySelector( '.aod-cd-cat-home-order' );
			return el ? String( parseInt( el.value, 10 ) || 0 ) : '0';
		}

		function post( action, params ) {
			var body = new URLSearchParams();
			body.append( 'action', action );
			body.append( 'nonce', CD.nonce );
			Object.keys( params ).forEach( function ( k ) { body.append( k, params[ k ] ); } );
			return fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } );
		}

		// Variante multipart (upload de l'image de catégorie).
		function postForm( fd ) {
			fd.append( 'nonce', CD.nonce );
			return fetch( CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
				.then( function ( r ) { return r.json(); } );
		}

		// Reflète l'état de l'image (URL ou vide) sur une ligne de catégorie.
		function applyImg( row, url ) {
			var thumb = row.querySelector( '.aod-cd-cat-thumb' );
			var img   = row.querySelector( '.aod-cd-cat-thumb-img' );
			var del   = row.querySelector( '.aod-cd-cat-imgdel' );
			if ( ! thumb || ! img ) { return; }
			if ( url ) {
				img.src = url; img.hidden = false; thumb.classList.add( 'has-img' );
				if ( del ) { del.hidden = false; }
			} else {
				img.removeAttribute( 'src' ); img.hidden = true; thumb.classList.remove( 'has-img' );
				if ( del ) { del.hidden = true; }
			}
		}

		// Création.
		if ( newForm && list && tpl ) {
			newForm.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var input = newForm.querySelector( '.aod-cd-cat-newname' );
				var inputAr = newForm.querySelector( '.aod-cd-cat-newname-ar' );
				var psel  = newForm.querySelector( '.aod-cd-cat-newparent' );
				var homeChk   = newForm.querySelector( '.aod-cd-cat-newhome' );
				var homeOrder = newForm.querySelector( '.aod-cd-cat-newhome-order' );
				var name  = ( input.value || '' ).trim();
				if ( ! name ) { return; }
				var nameAr = inputAr ? ( inputAr.value || '' ).trim() : '';
				var parent = psel ? ( psel.value || '0' ) : '0';
				var home   = homeChk && homeChk.checked ? '1' : '0';
				var homeOrderVal = homeOrder ? String( parseInt( homeOrder.value, 10 ) || 0 ) : '0';
				var btn = newForm.querySelector( 'button[type="submit"]' );
				if ( btn ) { btn.disabled = true; }
				post( 'aod_cd_save_category', { name: name, name_ar: nameAr, parent: parent, home: home, home_order: homeOrderVal } ).then( function ( res ) {
					if ( btn ) { btn.disabled = false; }
					if ( res && res.success ) {
						var empty = list.querySelector( '.aod-cd-cat-empty' );
						if ( empty ) { empty.remove(); }
						var node = tpl.content.firstElementChild.cloneNode( true );
						node.dataset.id = String( res.data.id );
						node.querySelector( '.aod-cd-cat-name' ).value = res.data.name;
						// Défensif : un <template> servi depuis le cache navigateur peut
						// dater d'avant le champ arabe.
						var nodeAr = node.querySelector( '.aod-cd-cat-name-ar' );
						if ( nodeAr ) { nodeAr.value = res.data.name_ar || ''; }
						node.querySelector( '.aod-cd-cat-count' ).textContent = CD.i18nCatZero || '0';
						var nodeParent = node.querySelector( '.aod-cd-cat-parent' );
						if ( nodeParent ) { nodeParent.value = String( res.data.parent || 0 ); }
						// Réglage accueil (9.1) reflété dans la ligne créée (défensif : cache).
						var nodeHome = node.querySelector( '.aod-cd-cat-home' );
						if ( nodeHome ) { nodeHome.checked = '1' === res.data.home; }
						var nodeHomeOrder = node.querySelector( '.aod-cd-cat-home-order' );
						if ( nodeHomeOrder ) { nodeHomeOrder.value = String( res.data.home_order || 0 ); }
						list.appendChild( node );
						input.value = '';
						if ( inputAr ) { inputAr.value = ''; }
						if ( psel ) { psel.value = '0'; }
						if ( homeChk ) { homeChk.checked = false; }
						if ( homeOrder ) { homeOrder.value = '0'; }
						input.focus();
						toast( res.data.message || 'OK', false );
					} else {
						toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
					}
				} ).catch( function () {
					if ( btn ) { btn.disabled = false; }
					toast( netErr, true );
				} );
			} );
		}

		// Renommage / suppression (délégation sur la liste).
		if ( list ) {
			list.addEventListener( 'click', function ( e ) {
				var imgDel  = e.target.closest( '.aod-cd-cat-imgdel' );
				var thumb   = e.target.closest( '.aod-cd-cat-thumb' );
				// Retrait de l'image de la catégorie.
				if ( imgDel ) {
					var rowi = imgDel.closest( '.aod-cd-cat-row' );
					if ( ! rowi.dataset.id || rowi.dataset.id === '0' ) { return; }
					imgDel.disabled = true;
					post( 'aod_cd_save_category', {
						term_id: rowi.dataset.id,
						name: ( rowi.querySelector( '.aod-cd-cat-name' ).value || '' ).trim(),
						// Ce chemin REPOSTE le nom : sans le pendant arabe, le serveur
						// recevrait name_ar='' et EFFACERAIT la traduction. Retirer une
						// image ne doit pas détraduire la catégorie.
						name_ar: catNameAr( rowi ),
						remove_image: 1
					} ).then( function ( res ) {
						imgDel.disabled = false;
						if ( res && res.success ) { applyImg( rowi, '' ); toast( res.data.message || 'OK', false ); }
						else { toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true ); }
					} ).catch( function () { imgDel.disabled = false; toast( netErr, true ); } );
					return;
				}
				// Clic sur la vignette → ouvre le sélecteur de fichier.
				if ( thumb ) {
					var fi = thumb.querySelector( '.aod-cd-cat-img' );
					if ( fi ) { fi.click(); }
					return;
				}
				var saveBtn = e.target.closest( '.aod-cd-cat-save' );
				var delBtn  = e.target.closest( '.aod-cd-cat-del' );
				if ( saveBtn ) {
					var row   = saveBtn.closest( '.aod-cd-cat-row' );
					var name  = ( row.querySelector( '.aod-cd-cat-name' ).value || '' ).trim();
					if ( ! name ) { return; }
					var rowPsel = row.querySelector( '.aod-cd-cat-parent' );
					var rowParent = rowPsel ? ( rowPsel.value || '0' ) : '0';
					saveBtn.disabled = true;
					post( 'aod_cd_save_category', { term_id: row.dataset.id, name: name, name_ar: catNameAr( row ), parent: rowParent, home: catHome( row ), home_order: catHomeOrder( row ) } ).then( function ( res ) {
						saveBtn.disabled = false;
						if ( res && res.success ) {
							row.querySelector( '.aod-cd-cat-name' ).value = res.data.name;
							var rowAr = row.querySelector( '.aod-cd-cat-name-ar' );
							if ( rowAr ) { rowAr.value = res.data.name_ar || ''; }
							if ( rowPsel ) { rowPsel.value = String( res.data.parent || 0 ); }
							// Réglage accueil (9.1) resynchronisé sur l'état réel en base.
							var rowHome = row.querySelector( '.aod-cd-cat-home' );
							if ( rowHome ) { rowHome.checked = '1' === res.data.home; }
							var rowHomeOrder = row.querySelector( '.aod-cd-cat-home-order' );
							if ( rowHomeOrder ) { rowHomeOrder.value = String( res.data.home_order || 0 ); }
							toast( res.data.message || 'OK', false );
						} else {
							toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
						}
					} ).catch( function () {
						saveBtn.disabled = false;
						toast( netErr, true );
					} );
					return;
				}
				if ( delBtn ) {
					var r = delBtn.closest( '.aod-cd-cat-row' );
					confirmModal( {
						message: CD.i18nCatDelConfirm || 'Supprimer ?',
						danger: true
					} ).then( function ( ok ) {
						if ( ! ok ) { return; }
						delBtn.disabled = true;
						post( 'aod_cd_delete_category', { term_id: r.dataset.id } ).then( function ( res ) {
							if ( res && res.success ) {
								r.remove();
								toast( res.data.message || 'OK', false );
							} else {
								delBtn.disabled = false;
								toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true );
							}
						} ).catch( function () {
							delBtn.disabled = false;
							toast( netErr, true );
						} );
					} );
				}
			} );

			// Upload de l'image au choix du fichier (l'événement change remonte).
			list.addEventListener( 'change', function ( e ) {
				var fi = e.target.closest( '.aod-cd-cat-img' );
				if ( ! fi || ! fi.files || ! fi.files[ 0 ] ) { return; }
				var row = fi.closest( '.aod-cd-cat-row' );
				if ( ! row.dataset.id || row.dataset.id === '0' ) { return; }
				var thumb = row.querySelector( '.aod-cd-cat-thumb' );
				var fd = new FormData();
				fd.append( 'action', 'aod_cd_save_category' );
				fd.append( 'term_id', row.dataset.id );
				fd.append( 'name', ( row.querySelector( '.aod-cd-cat-name' ).value || '' ).trim() );
				// Même raison qu'au retrait d'image : ce chemin reposte le nom, il doit
				// reposter sa traduction, sinon envoyer une image l'efface.
				fd.append( 'name_ar', catNameAr( row ) );
				fd.append( 'image', fi.files[ 0 ] );
				if ( thumb ) { thumb.classList.add( 'is-loading' ); }
				postForm( fd ).then( function ( res ) {
					if ( thumb ) { thumb.classList.remove( 'is-loading' ); }
					fi.value = '';
					if ( res && res.success ) { applyImg( row, res.data.img || '' ); toast( res.data.message || 'OK', false ); }
					else { toast( ( res && res.data && res.data.message ) || CD.i18nErr || 'Erreur.', true ); }
				} ).catch( function () {
					if ( thumb ) { thumb.classList.remove( 'is-loading' ); }
					fi.value = '';
					toast( netErr, true );
				} );
			} );
		}
	}

	/* Palette de couleurs prédéfinies pour les valeurs d'options (pastille `palette`) */
	function bindColorPalette() {
		var palette = document.getElementById( 'aod-cd-palette' );
		if ( ! palette ) { return; }
		var current = null; // pastille déclencheuse en cours.

		function closePalette() {
			palette.hidden = true;
			current = null;
		}

		function openPalette( swatch ) {
			current = swatch;
			palette.hidden = false;
			var r = swatch.getBoundingClientRect();
			var w = palette.offsetWidth || 280;
			var left = Math.max( 8, Math.min( r.left, window.innerWidth - w - 8 ) );
			var top  = r.bottom + 6;
			// Si pas la place en dessous, on remonte au-dessus de la pastille.
			if ( top + palette.offsetHeight > window.innerHeight - 8 ) {
				top = Math.max( 8, r.top - palette.offsetHeight - 6 );
			}
			palette.style.left = left + 'px';
			palette.style.top  = top + 'px';
		}

		// Ouvre la palette depuis une pastille de valeur.
		document.addEventListener( 'click', function ( e ) {
			var sw = e.target.closest( '.aod-cd-opt-swatch' );
			if ( sw ) {
				e.preventDefault();
				if ( current === sw && ! palette.hidden ) { closePalette(); }
				else { openPalette( sw ); }
				return;
			}
			// Choix d'une couleur dans la palette.
			var pick = e.target.closest( '.aod-cd-palette-sw' );
			if ( pick && current ) {
				e.preventDefault();
				var value = current.closest( '.aod-cd-opt-value' );
				var hex   = pick.dataset.hex || '';
				var name  = pick.dataset.name || '';
				if ( value ) {
					// `name` est le FR canonique — la CLÉ de matching serveur : jamais
					// traduit (7.4). La palette pré-remplit aussi la case arabe.
					var nameInput = value.querySelector( '.aod-cd-opt-name' );
					var arInput   = value.querySelector( '.aod-cd-opt-name-ar' );
					var hexInput  = value.querySelector( '.aod-cd-opt-hex' );
					var nameAr    = pick.dataset.nameAr || '';
					if ( nameInput && ! nameInput.value.trim() ) { nameInput.value = name; }
					if ( arInput && ! arInput.value.trim() ) { arInput.value = nameAr; }
					if ( hexInput ) { hexInput.value = hex; }
				}
				current.style.backgroundColor = hex;
				current.classList.add( 'has-color' );
				closePalette();
				return;
			}
			// Clic en dehors : on ferme.
			if ( ! palette.hidden && ! e.target.closest( '#aod-cd-palette' ) ) {
				closePalette();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! palette.hidden ) { closePalette(); }
		} );
	}

	/* Catégories du produit : liste déroulante sous forme de bouton (multi-sélection) */
	function bindCatDropdown() {
		var dropdowns = document.querySelectorAll( '.aod-cd-catdd' );
		if ( ! dropdowns.length ) { return; }

		dropdowns.forEach( function ( dd ) {
			var btn   = dd.querySelector( '.aod-cd-catdd-btn' );
			var panel = dd.querySelector( '.aod-cd-catdd-panel' );
			var text  = dd.querySelector( '.aod-cd-catdd-text' );
			if ( ! btn || ! panel || ! text ) { return; }
			var placeholder = dd.dataset.placeholder || ( CD && CD.i18nCatPlaceholder ) || '';

			function updateLabel() {
				var names = [];
				panel.querySelectorAll( 'input[type="checkbox"]:checked' ).forEach( function ( cb ) {
					var lbl = cb.closest( '.aod-cd-catdd-opt' );
					names.push( lbl ? lbl.textContent.trim() : cb.value );
				} );
				if ( names.length ) {
					text.textContent = names.join( ', ' );
					text.classList.remove( 'is-placeholder' );
				} else {
					text.textContent = placeholder;
					text.classList.add( 'is-placeholder' );
				}
			}

			function setOpen( on ) {
				panel.hidden = ! on;
				btn.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
			}

			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				setOpen( panel.hidden );
			} );

			panel.addEventListener( 'change', updateLabel );

			// Clic en dehors : on referme.
			document.addEventListener( 'click', function ( e ) {
				if ( ! panel.hidden && ! e.target.closest( '.aod-cd-catdd' ) ) { setOpen( false ); }
			} );
			document.addEventListener( 'keydown', function ( e ) {
				// Échap doit RENDRE le focus au bouton : sinon il reste sur une case
				// à cocher qu'on vient de masquer, et le Tab suivant repart du début
				// du document — l'utilisateur au clavier est éjecté du formulaire.
				if ( 'Escape' === e.key && ! panel.hidden ) {
					var inside = dd.contains( document.activeElement );
					setOpen( false );
					if ( inside ) { btn.focus(); }
				}
			} );

			// Ce panneau est une liste de cases à cocher : contrairement aux
			// listbox, le Tab doit y ENTRER (il est voisin du bouton dans le DOM).
			// En revanche il doit se refermer dès que le focus en sort vraiment —
			// sinon il reste ouvert par-dessus la suite du formulaire.
			dd.addEventListener( 'focusout', function ( e ) {
				if ( panel.hidden ) { return; }
				if ( e.relatedTarget && dd.contains( e.relatedTarget ) ) { return; }
				setOpen( false );
			} );

			updateLabel();
		} );
	}

	/* Période de promotion : bouton effacer par date + garde-fou fin ≥ début */
	function bindPromoRange() {
		document.querySelectorAll( '.aod-cd-promo-range' ).forEach( function ( range ) {
			var from = range.querySelector( 'input[name="sale_from"]' );
			var to   = range.querySelector( 'input[name="sale_to"]' );

			// La date de fin ne peut pas précéder la date de début.
			function syncMin() {
				if ( ! from || ! to ) { return; }
				to.min = from.value || '';
				if ( from.value && to.value && to.value < from.value ) { to.value = from.value; }
			}

			range.querySelectorAll( '.aod-cd-datewrap' ).forEach( function ( wrap ) {
				var input = wrap.querySelector( 'input[type="date"]' );
				var clear = wrap.querySelector( '.aod-cd-date-clear' );
				if ( ! input || ! clear ) { return; }

				function refresh() { clear.hidden = ! input.value; }

				input.addEventListener( 'change', function () { refresh(); syncMin(); } );
				input.addEventListener( 'input', refresh );
				clear.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					input.value = '';
					refresh();
					syncMin();
					input.focus();
				} );
				refresh();
			} );

			syncMin();
		} );
	}

	/* Galerie produit : tuile d'ajout, aperçu en direct, glisser-déposer, retrait */
	function bindGallery() {
		var grid  = document.getElementById( 'aod-cd-gallery' );
		var input = document.querySelector( '.aod-cd-galleryfile' );
		if ( ! grid || ! input ) { return; }
		var addBtn = document.getElementById( 'aod-cd-gal-add' );
		var hasDT  = ( typeof DataTransfer === 'function' );
		var store  = hasDT ? new DataTransfer() : null; // accumulateur des fichiers ajoutés.

		// (Re)génère les vignettes d'aperçu des fichiers nouvellement choisis.
		function renderNew() {
			grid.querySelectorAll( '.aod-cd-gal-new' ).forEach( function ( n ) {
				if ( n.dataset.url ) { URL.revokeObjectURL( n.dataset.url ); }
				n.remove();
			} );
			Array.prototype.forEach.call( input.files, function ( f, i ) {
				var url  = URL.createObjectURL( f );
				var tile = document.createElement( 'div' );
				tile.className   = 'aod-cd-gal-item aod-cd-gal-new';
				tile.dataset.idx = String( i );
				tile.dataset.url = url;

				var img = document.createElement( 'img' );
				img.src = url; img.alt = '';

				var tag = document.createElement( 'span' );
				tag.className = 'aod-cd-gal-tag';
				tag.textContent = CD.i18nGalNew || 'Nouveau';

				var rm = document.createElement( 'button' );
				rm.type = 'button';
				rm.className = 'aod-cd-gal-rmnew';
				rm.setAttribute( 'aria-label', CD.i18nGalRemove || 'Retirer' );
				// innerHTML et non textContent : le SVG vient du catalogue PHP (CD.icons),
				// pas d'une saisie. textContent l'afficherait comme du texte littéral.
				rm.innerHTML = ( CD.icons && CD.icons.close ) || '';

				tile.appendChild( img );
				tile.appendChild( tag );
				tile.appendChild( rm );
				grid.insertBefore( tile, addBtn );
			} );
		}

		// Ajoute des fichiers à la sélection (cumulatif si DataTransfer dispo).
		function addFiles( files ) {
			if ( ! files || ! files.length ) { return; }
			if ( store ) {
				Array.prototype.forEach.call( files, function ( f ) {
					if ( f.type && f.type.indexOf( 'image/' ) === 0 ) { store.items.add( f ); }
				} );
				input.files = store.files;
			}
			renderNew();
		}

		if ( addBtn ) {
			addBtn.addEventListener( 'click', function () { input.click(); } );

			// Glisser-déposer sur la tuile d'ajout.
			[ 'dragover', 'dragenter' ].forEach( function ( ev ) {
				addBtn.addEventListener( ev, function ( e ) { e.preventDefault(); addBtn.classList.add( 'is-drop' ); } );
			} );
			[ 'dragleave', 'dragend', 'drop' ].forEach( function ( ev ) {
				addBtn.addEventListener( ev, function () { addBtn.classList.remove( 'is-drop' ); } );
			} );
			addBtn.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				if ( e.dataTransfer ) { addFiles( e.dataTransfer.files ); }
			} );
		}

		input.addEventListener( 'change', function () {
			// Sans DataTransfer : pas de cumul, on affiche simplement la sélection courante.
			if ( store ) { addFiles( input.files ); } else { renderNew(); }
		} );

		// Délégation : retrait d'une photo (existante = marquage ; nouvelle = suppression).
		grid.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '.aod-cd-gal-rm' );
			if ( rm ) {
				var item = rm.closest( '.aod-cd-gal-item' );
				if ( ! item ) { return; }
				var check = item.querySelector( '.aod-cd-gal-rmcheck' );
				var on    = item.classList.toggle( 'is-removing' );
				if ( check ) { check.checked = on; }
				return;
			}
			var rmNew = e.target.closest( '.aod-cd-gal-rmnew' );
			if ( rmNew ) {
				var tile = rmNew.closest( '.aod-cd-gal-new' );
				if ( ! tile ) { return; }
				if ( store ) {
					var drop = parseInt( tile.dataset.idx, 10 );
					var keep = new DataTransfer();
					Array.prototype.forEach.call( store.files, function ( f, i ) { if ( i !== drop ) { keep.items.add( f ); } } );
					store = keep;
					input.files = store.files;
					renderNew();
				} else {
					if ( tile.dataset.url ) { URL.revokeObjectURL( tile.dataset.url ); }
					tile.remove();
				}
			}
		} );
	}

	core.register( 'bindProducts', bindProducts );
	core.register( 'bindCategories', bindCategories );
	core.register( 'bindCatDropdown', bindCatDropdown );
	core.register( 'bindPromoRange', bindPromoRange );
	core.register( 'bindGallery', bindGallery );
	core.register( 'bindColorPalette', bindColorPalette );
}( window.aodCdCore ) );
