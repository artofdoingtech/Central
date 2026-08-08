/* AOD Client Dashboard — le CALENDRIER de /gestion (2026-08-06).
 *
 * 🚨 POURQUOI IL FALLAIT L'ÉCRIRE, ET POURQUOI UNE FEUILLE DE STYLE N'Y POUVAIT
 * RIEN. Constat owner : « quand je clique sur un calendrier, il est blanc ».
 * Exact — et ce blanc-là n'est pas dans `dashboard.css`. Le sélecteur de dates
 * d'un `<input type="date">` est peint par le NAVIGATEUR, hors du document : ni
 * une règle CSS, ni un token de la charte, ni `--cd-card` ne l'atteignent. Il
 * reste blanc sur un écran entièrement teinté à l'accent du client, il ignore
 * `prefers-reduced-motion` comme le RTL, et il ne peut porter aucune animation.
 * C'est le seul morceau de `/gestion` qui échappait à la charte, et la seule
 * façon de l'y ramener est de ne plus l'ouvrir.
 *
 * 🔒 CE QUI NE CHANGE PAS, ET C'EST LE POINT DUR. L'`<input type="date">` RESTE
 * la source de vérité : même nom, même valeur `Y-m-d`, même soumission, même
 * `min`/`max`, même saisie au clavier. Ce module ne fait qu'ouvrir sa propre
 * surface à la place de celle du navigateur et y réécrire `input.value`. Aucun
 * champ caché, aucun format d'affichage maison — le serveur
 * (`parse_date_param()`, `read_filters()`) ne voit strictement rien de nouveau,
 * et une page sans JS garde le calendrier natif plutôt que rien du tout.
 *
 * 🌍 ZÉRO CHAÎNE NOUVELLE, à dessein. Noms de mois, jours de la semaine, date du
 * jour et premier jour de semaine viennent d'`Intl`, dans la langue de la PAGE
 * (`<html lang>`), donc en arabe dès que le cookie `aod_lang` bascule — sans
 * passer par les `.po`, qu'il aurait fallu retraduire à la main pour douze
 * libellés que le système connaît déjà. Le nom accessible du bouton est repris
 * du `<label>` du champ : lui est déjà traduit.
 */
( function ( core ) {
	'use strict';

	var CD = core.CD;

	// UN SEUL panneau pour toute la page (deux champs de date par écran, jamais
	// deux calendriers ouverts). Même raison que `sharedModal()` dans la coque :
	// un panneau par champ, ce sont N écouteurs `Échap` concurrents.
	var panneau = null;
	var champ   = null;   // Champ piloté par l'ouverture en cours.
	var vue     = null;   // 1er jour du mois affiché.
	var relache = null;   // Sortie du piège de focus (cf. core.trapFocus).

	/**
	 * La langue du TABLEAU DE BORD — et surtout pas celle de `<html lang>`.
	 *
	 * 🚨 MESURÉ SUR CE CLONE, et le piège vaut d'être écrit : `<html lang>` valait
	 * `en-US` pendant que tout `/gestion` s'affichait en français. Ce n'est pas une
	 * anomalie locale mais le cas NOMINAL d'un clone sans réseau sortant
	 * (TEMPLATE.md §3) : les packs de langue du cœur ne s'installent pas, WordPress
	 * reste en `en_US` — tandis que nos chaînes, elles, sont écrites en français
	 * dans le code et s'affichent donc en français, `gettext` ou pas. Un calendrier
	 * calé sur `lang` aurait annoncé « July 2026 · Sun Mon Tue » au milieu d'un
	 * écran français, et rien n'aurait pu le signaler : les dates, elles, restent
	 * justes.
	 *
	 * On lit donc le cookie `aod_lang` — celui du switcher FR/AR partagé avec le
	 * public, c'est-à-dire la source de vérité de ce que le gérant LIT. Deux
	 * valeurs possibles et deux seulement (nos `.po` n'ont que l'arabe, le français
	 * étant la langue des `msgid`), d'où le repli sur `fr` plutôt que sur la locale
	 * du site.
	 *
	 * @return {string} 'fr' ou 'ar'.
	 */
	function langue() {
		var m = /(?:^|;\s*)aod_lang=(ar|fr)\b/.exec( document.cookie || '' );
		if ( m ) { return m[ 1 ]; }
		// Cookie absent (première visite) : le sens d'écriture de la page tranche.
		return 'rtl' === document.documentElement.getAttribute( 'dir' ) ? 'ar' : 'fr';
	}

	/**
	 * Formatage d'une date dans la langue du tableau de bord.
	 *
	 * ⚠️ `calendar` et `numberingSystem` sont IMPOSÉS, ils ne sont pas décoratifs.
	 * En arabe, ICU rend volontiers les chiffres en indo-arabes (٠١٢) et, selon la
	 * région résolue, des dates HÉGIRIENNES : le panneau afficherait alors un mois
	 * qui ne correspond plus à la valeur grégorienne du champ — un calendrier qui
	 * ment sur la donnée qu'il édite. Le reste de `/gestion` (montants, numéros de
	 * commande) est en chiffres latins : le calendrier s'y range.
	 */
	function fmt( d, opts ) {
		var o = { calendar: 'gregory', numberingSystem: 'latn' };
		for ( var k in opts ) {
			if ( Object.prototype.hasOwnProperty.call( opts, k ) ) { o[ k ] = opts[ k ]; }
		}
		try {
			return new Intl.DateTimeFormat( langue(), o ).format( d );
		} catch ( e ) {
			// Locale ou options refusées : mieux vaut un nombre nu qu'une case vide.
			return String( d.getDate() );
		}
	}

	/* Premier jour de la semaine, 0 = dimanche. */
	function premierJour() {
		// Le repli n'est PAS « lundi » : lundi pour le latin, samedi pour l'arabe.
		// Une semaine qui commence le mauvais jour décale toute la grille — et le
		// décalage est silencieux, les dates restant justes une à une.
		var defaut = 'ar' === langue().slice( 0, 2 ).toLowerCase() ? 6 : 1;
		try {
			var loc  = new Intl.Locale( langue() );
			var info = loc.weekInfo || ( loc.getWeekInfo && loc.getWeekInfo() );
			return ( info && info.firstDay ) ? info.firstDay % 7 : defaut;
		} catch ( e ) {
			// `Intl.Locale` ou `weekInfo` absents (navigateur ancien), ou balise de
			// langue invalide. Un catch au corps VIDE serait ici un vrai défaut
			// (politique 32.5) : ce repli est une décision, pas une erreur avalée.
			return defaut;
		}
	}

	/**
	 * `Y-m-d` → Date LOCALE.
	 *
	 * ⚠️ `new Date('2026-08-06')` est interprété en UTC : à l'ouest de Greenwich
	 * il rend le 5 août. Un champ de date parle en date CIVILE, jamais en instant
	 * — d'où le découpage à la main. Le contrôle sur le mois rejette au passage
	 * les dates impossibles (2024-02-30), exactement comme `checkdate()` côté
	 * serveur.
	 */
	function parse( v ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( v || '' );
		if ( ! m ) { return null; }
		var d = new Date( +m[ 1 ], +m[ 2 ] - 1, +m[ 3 ] );
		return ( d.getMonth() === +m[ 2 ] - 1 && d.getDate() === +m[ 3 ] ) ? d : null;
	}

	function iso( d ) {
		var mm = String( d.getMonth() + 1 );
		var jj = String( d.getDate() );
		return d.getFullYear() + '-' + ( mm.length < 2 ? '0' + mm : mm ) + '-' + ( jj.length < 2 ? '0' + jj : jj );
	}

	/* Hors des bornes du champ ? `min`/`max` sont posés par le formulaire produit
	   (la fin d'une promotion ne peut pas précéder son début) : les ignorer ici
	   rendrait cliquable ce que le champ refuse ensuite en silence. */
	function horsBornes( d ) {
		var v = iso( d );
		if ( champ.min && v < champ.min ) { return true; }
		if ( champ.max && v > champ.max ) { return true; }
		return false;
	}

	/* ---- Construction du panneau (une fois pour la page) ------------------- */

	function construire() {
		if ( panneau ) { return panneau; }
		panneau = document.createElement( 'div' );
		panneau.className = 'aod-cd-cal';
		panneau.hidden    = true;
		panneau.setAttribute( 'role', 'dialog' );
		// Les chevrons sont DESSINÉS en CSS (deux bordures pivotées), comme celui
		// du select maison : le catalogue d'icônes n'en contient pas, et le JS ne
		// fabrique pas de balisage d'icône (règle posée par `emptyState()`).
		panneau.innerHTML =
			'<div class="aod-cd-cal__head">' +
				'<button type="button" class="aod-cd-cal__nav" data-pas="-1"><span class="aod-cd-cal__chev" aria-hidden="true"></span></button>' +
				'<span class="aod-cd-cal__title"></span>' +
				'<button type="button" class="aod-cd-cal__nav aod-cd-cal__nav--next" data-pas="1"><span class="aod-cd-cal__chev" aria-hidden="true"></span></button>' +
			'</div>' +
			'<div class="aod-cd-cal__dow" aria-hidden="true"></div>' +
			'<div class="aod-cd-cal__grid"></div>' +
			'<div class="aod-cd-cal__foot"><button type="button" class="aod-cd-cal__today"></button></div>';
		document.body.appendChild( panneau );

		panneau.querySelectorAll( '.aod-cd-cal__nav' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var pas = parseInt( b.dataset.pas, 10 );
				vue = new Date( vue.getFullYear(), vue.getMonth() + pas, 1 );
				rendre( pas > 0 ? 'next' : 'prev' );
			} );
		} );
		panneau.querySelector( '.aod-cd-cal__today' ).addEventListener( 'click', function () {
			var t = new Date();
			// 🔴 LE RACCOURCI DOIT PASSER PAR LA MÊME PORTE QUE LES CASES. Sans ce
			// contrôle il posait la date du jour SANS regarder `min`/`max` : sur la
			// fin d'une promotion qui commence demain, un clic écrivait une valeur
			// que le champ refuse — invisible, parce que le garde-fou « fin ≥ début »
			// la rattrapait au changement suivant. Une règle contournée par un seul
			// de ses appelants n'est plus une règle.
			if ( horsBornes( t ) ) { return; }
			vue = new Date( t.getFullYear(), t.getMonth(), 1 );
			rendre( 'next' );
			choisir( t );
		} );
		panneau.querySelector( '.aod-cd-cal__grid' ).addEventListener( 'click', function ( e ) {
			var b = e.target.closest ? e.target.closest( '.aod-cd-cal__day' ) : null;
			if ( ! b || b.disabled ) { return; }
			choisir( parse( b.dataset.jour ) );
		} );
		panneau.addEventListener( 'keydown', clavier );
		return panneau;
	}

	/* ---- Rendu ------------------------------------------------------------- */

	/* En-tête des jours : les noms viennent d'`Intl`, à partir d'une semaine de
	   référence dont on connaît le dimanche (7 janvier 2024). Aucune liste de
	   sept libellés à traduire, aucune à laisser dériver. */
	function joursSemaine() {
		var out = [];
		var d0  = premierJour();
		for ( var i = 0; i < 7; i++ ) {
			// Le point de l'abréviation française (« lun. ») saute : sept points
			// alignés sous un titre sont du bruit, et l'en-tête est déjà
			// `aria-hidden` — l'information de jour vit dans le nom de chaque case.
			// `narrow` aurait donné « L M M J V S D », illisible deux fois.
			out.push( fmt( new Date( 2024, 0, 7 + ( ( d0 + i ) % 7 ) ), { weekday: 'short' } ).replace( /\.$/, '' ) );
		}
		return out;
	}

	function rendre( sens ) {
		var titre  = panneau.querySelector( '.aod-cd-cal__title' );
		var dow    = panneau.querySelector( '.aod-cd-cal__dow' );
		var grille = panneau.querySelector( '.aod-cd-cal__grid' );
		var moisAn = { month: 'long', year: 'numeric' };
		var an     = vue.getFullYear();
		var mo     = vue.getMonth();

		titre.textContent = fmt( vue, moisAn );
		// Le nom accessible des flèches EST le mois où elles mènent : « juillet
		// 2026 » dit à la fois l'action et sa destination, dans la langue de la
		// page, sans une seule chaîne à traduire.
		panneau.querySelector( '.aod-cd-cal__nav[data-pas="-1"]' )
			.setAttribute( 'aria-label', fmt( new Date( an, mo - 1, 1 ), moisAn ) );
		panneau.querySelector( '.aod-cd-cal__nav[data-pas="1"]' )
			.setAttribute( 'aria-label', fmt( new Date( an, mo + 1, 1 ), moisAn ) );
		panneau.setAttribute( 'aria-label', titre.textContent );

		dow.innerHTML = '';
		joursSemaine().forEach( function ( n ) {
			var s = document.createElement( 'span' );
			s.textContent = n;
			dow.appendChild( s );
		} );

		var choisi = parse( champ.value );
		var auj    = new Date();
		var debut  = ( new Date( an, mo, 1 ).getDay() - premierJour() + 7 ) % 7;
		var nb     = new Date( an, mo + 1, 0 ).getDate();

		grille.innerHTML = '';
		for ( var i = 0; i < debut; i++ ) {
			grille.appendChild( document.createElement( 'span' ) );
		}
		for ( var j = 1; j <= nb; j++ ) {
			var d = new Date( an, mo, j );
			var b = document.createElement( 'button' );
			b.type        = 'button';
			b.className   = 'aod-cd-cal__day';
			b.textContent = String( j );
			b.dataset.jour = iso( d );
			// Le nom accessible est la date ENTIÈRE : « 6 août 2026 ». Un lecteur
			// d'écran qui annoncerait « 6 » seul obligerait à retenir le mois lu
			// en en-tête plusieurs secondes plus tôt.
			b.setAttribute( 'aria-label', fmt( d, { day: 'numeric', month: 'long', year: 'numeric' } ) );
			if ( horsBornes( d ) ) { b.disabled = true; }
			if ( choisi && iso( choisi ) === iso( d ) ) {
				b.classList.add( 'is-sel' );
				b.setAttribute( 'aria-pressed', 'true' );
			}
			if ( iso( auj ) === iso( d ) ) {
				b.classList.add( 'is-today' );
				b.setAttribute( 'aria-current', 'date' );
			}
			grille.appendChild( b );
		}

		// La grille rejoue son entrée à chaque mois, dans le SENS du geste : la
		// classe est retirée puis reposée après un reflow, sinon le navigateur ne
		// redémarre pas une animation dont le nom n'a pas changé.
		grille.classList.remove( 'is-next', 'is-prev' );
		if ( sens ) {
			void grille.offsetWidth;
			grille.classList.add( 'is-' + sens );
		}

		var chip = panneau.querySelector( '.aod-cd-cal__today' );
		chip.textContent = fmt( auj, { day: 'numeric', month: 'long', year: 'numeric' } );
		// …et le refus se VOIT, au lieu d'être un clic sans effet.
		chip.disabled = horsBornes( auj );
	}

	/* ---- Ouverture / fermeture / choix ------------------------------------- */

	/* Place le panneau sous le champ, sans jamais sortir de l'écran — et
	   au-DESSUS s'il n'y a pas la place dessous. Même mécanique que le menu du
	   select maison : `position:fixed` pour échapper aux `overflow` parents. */
	function placer() {
		if ( ! champ ) { return; }
		var r  = champ.getBoundingClientRect();
		var ph = panneau.offsetHeight;
		var pw = panneau.offsetWidth;
		var vh = window.innerHeight;
		var vw = window.innerWidth;
		var haut = r.bottom + 6;
		if ( haut + ph > vh - 8 && r.top - 6 - ph > 8 ) { haut = r.top - 6 - ph; }
		panneau.style.top  = Math.max( 8, Math.min( haut, vh - ph - 8 ) ) + 'px';
		panneau.style.left = Math.max( 8, Math.min( r.left, vw - pw - 8 ) ) + 'px';
	}

	function ouvert( input ) {
		return ! ! panneau && ! panneau.hidden && champ === input;
	}

	function ouvrir( input ) {
		construire();
		if ( champ && champ !== input ) { fermer(); }
		champ = input;
		var v = parse( input.value ) || new Date();
		vue   = new Date( v.getFullYear(), v.getMonth(), 1 );
		panneau.hidden = false;
		rendre( null );
		placer();
		marquer( input, true );
		// Piège de focus : le panneau vit dans le <body>, il n'est donc PAS voisin
		// du champ dans l'ordre de tabulation — sans piège, la première tabulation
		// partirait derrière un panneau resté ouvert. `trapFocus` rend aussi le
		// focus au champ à la fermeture, ce qu'on veut dans tous les cas.
		// Le jour visé à l'ouverture est celui qui est CHOISI, à défaut aujourd'hui :
		// atterrir sur le 1er du mois obligerait à traverser la grille à chaque fois.
		relache = core.trapFocus(
			panneau,
			panneau.querySelector( '.aod-cd-cal__day.is-sel' )
				|| panneau.querySelector( '.aod-cd-cal__day.is-today' )
				|| panneau.querySelector( '.aod-cd-cal__day:not(:disabled)' )
		);
	}

	function fermer() {
		if ( ! panneau || panneau.hidden ) { return; }
		panneau.hidden = true;
		if ( champ ) { marquer( champ, false ); }
		if ( relache ) { relache(); relache = null; }
		champ = null;
	}

	function marquer( input, on ) {
		var btn = input.parentNode ? input.parentNode.querySelector( '.aod-cd-calbtn' ) : null;
		if ( btn ) { btn.setAttribute( 'aria-expanded', on ? 'true' : 'false' ); }
		if ( input.parentNode ) { input.parentNode.classList.toggle( 'is-open', !! on ); }
	}

	function choisir( d ) {
		if ( ! d || ! champ ) { return; }
		var input = champ;
		input.value = iso( d );
		// LES DEUX événements, et ce n'est pas une ceinture-bretelles : `input`
		// rafraîchit le bouton d'effacement du formulaire produit, `change` déclenche
		// le garde-fou « fin ≥ début ». Une valeur posée par le script n'en émet
		// aucun toute seule — le champ changerait sans que rien ne le sache.
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		fermer();
	}

	/* Clavier du panneau (APG « date picker dialog ») : les flèches parcourent les
	   jours, Page haut/bas les mois, Échap referme sans choisir. */
	function clavier( e ) {
		if ( 'Escape' === e.key ) { e.preventDefault(); fermer(); return; }
		var pas = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[ e.key ];
		var mois = { PageUp: -1, PageDown: 1 }[ e.key ];
		if ( mois ) {
			e.preventDefault();
			vue = new Date( vue.getFullYear(), vue.getMonth() + mois, 1 );
			rendre( mois > 0 ? 'next' : 'prev' );
			var p = panneau.querySelector( '.aod-cd-cal__day:not(:disabled)' );
			if ( p ) { p.focus(); }
			return;
		}
		if ( ! pas ) { return; }
		var jour = e.target.closest ? e.target.closest( '.aod-cd-cal__day' ) : null;
		if ( ! jour ) { return; }
		e.preventDefault();
		// En arabe la grille est miroitée : la flèche « gauche » doit avancer dans
		// le temps, pas reculer — sinon le déplacement contredit ce qu'on voit.
		if ( 'rtl' === document.documentElement.getAttribute( 'dir' ) && Math.abs( pas ) === 1 ) { pas = -pas; }
		var d = parse( jour.dataset.jour );
		d.setDate( d.getDate() + pas );
		if ( d.getFullYear() !== vue.getFullYear() || d.getMonth() !== vue.getMonth() ) {
			vue = new Date( d.getFullYear(), d.getMonth(), 1 );
			rendre( pas > 0 ? 'next' : 'prev' );
		}
		var suivant = panneau.querySelector( '.aod-cd-cal__day[data-jour="' + iso( d ) + '"]' );
		if ( suivant && ! suivant.disabled ) { suivant.focus(); }
	}

	/* ---- Le binder --------------------------------------------------------- */

	function bindDatePickers() {
		document.querySelectorAll( 'input[type="date"]' ).forEach( function ( input ) {
			if ( input.dataset.aodCal ) { return; }
			input.dataset.aodCal = '1';

			// Enveloppe de positionnement. Le formulaire produit en a déjà une
			// (`.aod-cd-datewrap`, qui porte le bouton d'effacement) : on la
			// réutilise plutôt que d'en emboîter une seconde.
			var wrap = input.parentNode;
			if ( ! wrap || ! wrap.classList.contains( 'aod-cd-datewrap' ) ) {
				wrap = document.createElement( 'div' );
				wrap.className = 'aod-cd-datewrap';
				input.parentNode.insertBefore( wrap, input );
				wrap.appendChild( input );
			}
			wrap.classList.add( 'has-cal' );

			var btn = document.createElement( 'button' );
			btn.type      = 'button';
			btn.className = 'aod-cd-calbtn';
			btn.setAttribute( 'aria-haspopup', 'dialog' );
			btn.setAttribute( 'aria-expanded', 'false' );
			// Nom accessible : celui du CHAMP (« Du », « Fin de la promotion »…),
			// déjà traduit. Aucune chaîne nouvelle, et le nom dit de quelle date il
			// s'agit — ce qu'un « Ouvrir le calendrier » générique ne ferait pas
			// sur un écran qui en porte deux.
			var lab = input.id ? document.querySelector( 'label[for="' + input.id + '"]' ) : null;
			btn.setAttribute( 'aria-label', ( lab ? lab.textContent : input.getAttribute( 'aria-label' ) || '' ).trim() );
			// SVG rendu par le catalogue fermé (AOD_CD_Icon), pas fabriqué ici.
			btn.innerHTML = ( CD.icons && CD.icons.calendar ) || '';
			wrap.appendChild( btn );

			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( ouvert( input ) ) { fermer(); } else { ouvrir( input ); }
			} );
			// Flèche bas dans le champ : la convention APG pour dérouler un widget
			// depuis sa saisie. La frappe directe (JJ/MM/AAAA) reste intacte —
			// c'est même le chemin le plus rapide, et il ne doit pas être volé par
			// une ouverture au clic.
			input.addEventListener( 'keydown', function ( e ) {
				if ( 'ArrowDown' === e.key && ( e.altKey || ! ouvert( input ) ) ) {
					e.preventDefault();
					ouvrir( input );
				}
			} );

			// Le panneau ne se referme pas au défilement : il se RECOLLE au champ
			// (et ne part que si le champ quitte l'écran). C'est la primitive
			// partagée de la coque, avec ses deux pièges déjà réglés — un clic sur
			// un champ à moitié hors écran provoque un `scroll` APRÈS le `click`.
			core.bindFloatingMenu(
				input,
				construire(),
				function () { return ouvert( input ); },
				placer,
				fermer
			);
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! panneau || panneau.hidden ) { return; }
			if ( ! e.target || ! e.target.closest ) { return; }
			if ( e.target.closest( '.aod-cd-cal' ) || e.target.closest( '.aod-cd-calbtn' ) ) { return; }
			fermer();
		} );
	}

	core.register( 'bindDatePickers', bindDatePickers );
}( window.aodCdCore ) );
