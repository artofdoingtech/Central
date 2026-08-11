// Noms accessibles : un champ sans intitulé est annoncé « zone de saisie » et rien
// d'autre — au lecteur d'écran, le formulaire devient une suite de cases anonymes.
// Idem pour un bouton sans texte (icône seule) : « bouton », point.
//
// Le `placeholder` NE COMPTE PAS comme intitulé : il disparaît à la première
// frappe, et beaucoup de lecteurs d'écran ne l'annoncent pas.
const { launch, login, ok, BASE, SECTIONS, OUTILS_FINANCE, sectionRendue } = require( './lib.js' );

// Les trois outils du pôle Finance sont balayés EN PLUS des sections : ils
// portent l'essentiel des champs de saisie de ce pôle (onze pour le seul
// calculateur), et un champ sans nom accessible y serait aussi muet qu'ailleurs.
const PAGES = [ ...SECTIONS, 'products?action=new', ...OUTILS_FINANCE ];

const AUDIT = function () {
	function nom( el ) {
		if ( el.getAttribute( 'aria-label' ) ) { return 'aria-label'; }
		const lb = el.getAttribute( 'aria-labelledby' );
		if ( lb && lb.split( /\s+/ ).some( function ( id ) { return document.getElementById( id ); } ) ) { return 'aria-labelledby'; }
		if ( el.id && document.querySelector( 'label[for="' + CSS.escape( el.id ) + '"]' ) ) { return 'label[for]'; }
		if ( el.closest( 'label' ) ) { return 'label englobant'; }
		if ( el.getAttribute( 'title' ) ) { return 'title'; }
		if ( el.getAttribute( 'placeholder' ) ) { return 'PLACEHOLDER SEUL'; }
		return null;
	}
	function sel( el ) {
		const c = ( el.getAttribute( 'class' ) || '' ).split( /\s+/ )[ 0 ];
		return el.tagName.toLowerCase() + ( c ? '.' + c : '' ) + ( el.name ? '[name=' + el.name + ']' : '' );
	}

	// 🚨 Garde-fou de RACINE (celui d'a11y-contraste.js). Les trois audits ci-dessous
	// sont bornés à `body.aod-cd`. Sans cette racine, ils parcourent ZÉRO élément et
	// l'audit conclut « aucun élément sans nom accessible » : un vert parfait,
	// obtenu en n'auditant rien.
	//
	// Ce qu'il attrape, vérifié : plugin `aod-client-dashboard` désactivé, page
	// /gestion absente ou dépubliée, écran « accès refusé », 404 servi à la place,
	// classe de coque renommée. Ce qu'il n'attrape PAS : une section inconnue —
	// le routeur de /gestion retombe alors sur une section valide, la coque est bien
	// rendue, et le vert est mérité.
	const out = { racine: !! document.querySelector( 'body.aod-cd' ), champs: [], boutons: [], images: [] };

	document.querySelectorAll( 'body.aod-cd input,body.aod-cd select,body.aod-cd textarea' ).forEach( function ( el ) {
		if ( el.type === 'hidden' ) { return; }
		if ( ! el.getClientRects().length ) { return; } // masqué (menu replié, select natif remplacé)
		const n = nom( el );
		if ( ! n || n === 'PLACEHOLDER SEUL' ) {
			out.champs.push( { sel: sel( el ), type: el.type || el.tagName, nom: n || 'AUCUN' } );
		}
	} );

	// On audite aussi les `[role="button"]` : les wrappers de barre TinyMCE
	// (<div role="button" aria-label="Gras">) sont de VRAIS boutons pour le lecteur
	// d'écran et doivent porter un nom — sans ça, la barre échappait entièrement à
	// la recette (le <button> interne est décoratif, cf. ci-dessous).
	document.querySelectorAll( 'body.aod-cd button,body.aod-cd a,body.aod-cd [role="button"]' ).forEach( function ( el ) {
		if ( ! el.getClientRects().length ) { return; }
		if ( el.closest( '[aria-hidden="true"]' ) ) { return; }
		// `role="presentation"` ne retire l'élément de l'arbre d'accessibilité QUE
		// s'il n'est pas focusable : la résolution de conflit ARIA impose au
		// navigateur d'IGNORER le rôle sur un élément focusable (qui réapparaît donc
		// SANS nom). On ne saute donc que le cas légitime — non focusable
		// (tabindex négatif) —, celui des <button role="presentation" tabindex="-1">
		// internes de TinyMCE. Un role=presentation posé par erreur sur un élément
		// resté focusable doit, lui, être signalé.
		if ( 'presentation' === el.getAttribute( 'role' ) && el.tabIndex < 0 ) { return; }
		// Texte visible, en ignorant ce qui est explicitement décoratif.
		const clone = el.cloneNode( true );
		clone.querySelectorAll( '[aria-hidden="true"]' ).forEach( function ( d ) { d.remove(); } );
		if ( ( clone.textContent || '' ).trim() ) { return; }
		if ( el.getAttribute( 'aria-label' ) || el.getAttribute( 'title' ) || el.getAttribute( 'aria-labelledby' ) ) { return; }
		out.boutons.push( { sel: sel( el ), html: el.innerHTML.trim().slice( 0, 40 ) } );
	} );

	document.querySelectorAll( 'body.aod-cd img' ).forEach( function ( el ) {
		if ( el.getAttribute( 'alt' ) === null ) { out.images.push( { src: ( el.getAttribute( 'src' ) || '' ).slice( -40 ) } ); }
	} );

	return out;
};

( async () => {
	const { browser, page } = await launch();
	await login( page );
	let fails = 0;
	// 46.3 — compte des pages où la région live a été réellement sondée.
	let regionsVues = 0;
	const vus = new Set();

	for ( const s of PAGES ) {
		await page.goto( `${ BASE }/gestion/${ s }`, { waitUntil: 'networkidle' } );
		// Déplier accordéons et panneaux : un champ replié reste un champ à nommer.
		await page.evaluate( () => {
			document.querySelectorAll( 'details' ).forEach( ( d ) => { d.open = true; } );
			document.querySelectorAll( '.aod-cd-catdd-panel,.aod-cd-import' ).forEach( ( p ) => { p.hidden = false; } );
		} );
		await page.waitForTimeout( 250 );
		const r = await page.evaluate( AUDIT );

		if ( ! r.racine ) {
			fails++;
			console.log( `  ❌ [${ s }] RACINE body.aod-cd INTROUVABLE — rien n’a été audité sur cette page.` );
			continue;
		}

		// 🚨 Même garde que la racine, pour l'autre façon de ne rien auditer
		// (31.5) : un slug qui ne se route plus rend la PREMIÈRE section visible,
		// sans 404 ni erreur. On auditerait alors `home` douze fois en croyant
		// balayer douze pages. Cf. `sectionRendue()` dans lib.js.
		// ⚠️ `PAGES` n'est PAS une liste de slugs nus : il contient
		// `products?action=new`. On compare donc la SECTION, pas l'URL — sinon le
		// contrôle de routage échoue sur une page qui se route parfaitement.
		const attendue = s.split( '?' )[ 0 ];
		const rendue = await sectionRendue( page );
		if ( rendue !== attendue ) {
			fails++;
			console.log( `  ❌ [${ s }] ROUTAGE : la page rendue est « ${ rendue } » (attendu « ${ attendue } ») — cette section ne se route plus, elle N’A PAS été auditée.` );
			continue;
		}

		for ( const c of r.champs ) {
			const k = `champ|${ c.sel }|${ c.nom }`;
			if ( vus.has( k ) ) { continue; }
			vus.add( k );
			fails++;
			console.log( `  ❌ [${ s }] champ sans intitulé : ${ c.sel } (${ c.type }) — ${ c.nom }` );
		}
		for ( const b of r.boutons ) {
			const k = `btn|${ b.sel }`;
			if ( vus.has( k ) ) { continue; }
			vus.add( k );
			fails++;
			console.log( `  ❌ [${ s }] bouton/lien sans nom : ${ b.sel } — « ${ b.html } »` );
		}
		for ( const i of r.images ) {
			const k = `img|${ i.src }`;
			if ( vus.has( k ) ) { continue; }
			vus.add( k );
			fails++;
			console.log( `  ❌ [${ s }] image sans attribut alt : …${ i.src }` );
		}

		/* 46.3 — la région live du toast PRÉEXISTE sur chaque page.
		 *
		 * Elle porte le retour de tous les AJAX de /gestion. Sa présence AU
		 * CHARGEMENT — et non à la première réponse — est la condition de son
		 * annonce : un lecteur d'écran n'annonce que les mutations d'une région
		 * déjà dans l'arbre. On vérifie donc l'état de repos, la seule chose
		 * qu'une porte de balayage peut observer sans déclencher d'action.
		 * ⚠️ `pointer-events` est contrôlé ici AUSSI, et ce n'est pas du zèle :
		 * la boîte est `position:fixed` en bas de l'écran et invisible au repos.
		 * Sans cette propriété, elle avalerait les clics de la zone qu'elle
		 * recouvre, sur toutes les pages — un défaut qu'aucun audit de NOM ne
		 * verrait, mais que la même sonde peut mesurer au même endroit. */
		const tr = await page.evaluate( () => {
			const el = document.querySelector( '.aod-cd-toast' );
			if ( ! el ) { return { present: false }; }
			return {
				present: true,
				role: el.getAttribute( 'role' ),
				live: el.getAttribute( 'aria-live' ),
				pe: getComputedStyle( el ).pointerEvents,
			};
		} );
		regionsVues++;
		if ( ! tr.present || 'status' !== tr.role || 'polite' !== tr.live || 'none' !== tr.pe ) {
			const k = 'toast|' + s;
			if ( ! vus.has( k ) ) {
				vus.add( k );
				fails++;
				console.log(
					`  ❌ [${ s }] région live du toast : ${ ! tr.present
						? 'ABSENTE au chargement — créée au premier message, elle n\'annonce rien'
						: `role=${ tr.role }, aria-live=${ tr.live }, pointer-events=${ tr.pe } (attendu status/polite/none)` }`
				);
			}
		}
	}

	/* Témoin de couverture (règle du témoin, pt 1) : sans lui, un sélecteur devenu
	 * faux rendrait `regionsVues` à 0 et le contrôle ci-dessus muet — donc vert. */
	console.log( `Régions live du toast vérifiées : ${ regionsVues } / ${ PAGES.length }` );
	if ( regionsVues < PAGES.length ) {
		fails++;
		console.log( `  ❌ seulement ${ regionsVues } page(s) sur ${ PAGES.length } ont été sondées pour la région live` );
	}

	ok( fails === 0, `${ fails } élément(s) sans nom accessible` );
	await browser.close();
	process.exit( fails ? 1 : 0 );
} )();
