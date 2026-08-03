// Recette CLAVIER : on pilote au clavier uniquement (aucun clic) et on regarde où
// va le focus. Un widget « accessible » sur le papier peut très bien être
// inatteignable en pratique — le menu du sélecteur de transporteur, par exemple,
// est attaché au <body>, donc hors du flux de tabulation de son bouton.
const { launch, login, ok, BASE, garerSouris } = require( './lib.js' );

const active = ( page ) => page.evaluate( () => {
	const a = document.activeElement;
	if ( ! a ) { return 'null'; }
	const cls = ( a.getAttribute( 'class' ) || '' ).split( /\s+/ )[ 0 ];
	return a.tagName.toLowerCase() + ( cls ? '.' + cls : '' ) + ( a.id ? '#' + a.id : '' );
} );

( async () => {
	const { browser, ctx, page } = await launch();
	await login( page );
	let fails = 0;
	const t = ( c, m, x ) => { if ( ! ok( c, m, x ) ) { fails++; } };

	/* ---------- 1. statussel : sélecteur de statut (Commandes) ---------- */
	console.log( '\n--- statussel : sélecteur de statut (Commandes) ---' );
	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	if ( await page.locator( '.aod-cd-statussel-btn' ).count() ) {
		await page.locator( '.aod-cd-statussel-btn' ).first().focus();
		await page.keyboard.press( 'Enter' );
		await page.waitForTimeout( 150 );
		t( await page.locator( '.aod-cd-statussel-menu' ).first().isVisible(), 'Entrée ouvre le menu' );

		// Modèle « combobox » : le focus RESTE sur le bouton, l'option visée est
		// désignée par aria-activedescendant (seule approche tenable quand le menu
		// ne vit pas sous le bouton).
		await page.keyboard.press( 'ArrowDown' );
		await page.waitForTimeout( 100 );
		const kbd = await page.evaluate( () => {
			const b = document.querySelector( '.aod-cd-statussel-btn' );
			const ad = b.getAttribute( 'aria-activedescendant' );
			const el = ad ? document.getElementById( ad ) : null;
			return { ad, marked: !! ( el && el.classList.contains( 'is-kbd' ) ), role: b.getAttribute( 'role' ) };
		} );
		t( !! kbd.ad && kbd.marked, 'Flèche bas désigne une option (aria-activedescendant + surlignage)', JSON.stringify( kbd ) );
		t( kbd.role === 'combobox', 'Le bouton porte role=combobox (requis pour aria-activedescendant)', kbd.role );

		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 100 );
		const a1 = await active( page );
		t( ! ( await page.locator( '.aod-cd-statussel-menu' ).first().isVisible() ), 'Échap referme' );
		t( a1.includes( 'statussel-btn' ), 'Échap rend le focus au bouton', a1 );

		// Tab sortant : le menu doit se refermer, pas rester ouvert en l'air.
		await page.keyboard.press( 'Enter' );
		await page.waitForTimeout( 120 );
		for ( let i = 0; i < 5; i++ ) { await page.keyboard.press( 'Tab' ); }
		await page.waitForTimeout( 150 );
		const stray = await page.evaluate( () =>
			[ ...document.querySelectorAll( '.aod-cd-statussel-menu' ) ].filter( ( m ) => ! m.hidden ).length );
		t( stray === 0, 'Le menu se referme quand le focus le quitte (Tab)', `${ stray } menu(s) resté(s) ouvert(s)` );
		await page.keyboard.press( 'Escape' );
	} else {
		// 🚨 Absence MOTIVÉE. Ce bloc se contentait de « (aucune commande —
		// sélecteur absent) » et passait son chemin : six assertions disparaissaient
		// en silence et la porte finissait verte. Or le sélecteur est rendu par
		// LIGNE de commande : son absence n'est légitime que si la liste est vide.
		// S'il y a des commandes mais plus de sélecteur, c'est le widget qui est
		// cassé — exactement ce que cette recette existe pour attraper.
		const lignes = await page.locator( '.aod-cd-order-detail' ).count();
		if ( lignes > 0 ) {
			t( false, 'Le sélecteur de statut est présent sur les lignes de commande',
				`${ lignes } commande(s) listée(s), 0 .aod-cd-statussel-btn` );
		} else {
			console.log( '  ⏭️  Aucune commande sur ce clone : sélecteur de statut sans objet (rien vérifié ici).' );
		}
	}

	/* ---------- 2. csel : menu attaché au <body> (Livraison) ---------- */
	console.log( '\n--- csel : sélecteur custom (menu attaché au <body>) ---' );
	await page.goto( `${ BASE }/gestion/shipping`, { waitUntil: 'networkidle' } );
	await page.waitForTimeout( 400 );
	// Ces sélecteurs vivent dans l'accordéon « Transporteurs » : il faut l'ouvrir.
	await page.evaluate( () => document.querySelectorAll( 'details.aod-cd-acc' ).forEach( ( d ) => { d.open = true; } ) );
	await page.waitForTimeout( 200 );
	const csel = page.locator( '.aod-cd-csel-btn:visible' ).first();
	if ( await csel.count() ) {
		await csel.focus();
		await page.keyboard.press( 'ArrowDown' ); // ouvre ET désigne l'option courante
		await page.waitForTimeout( 200 );
		const st = await page.evaluate( () => {
			const b = document.querySelector( '.aod-cd-csel-btn[aria-expanded="true"]' );
			if ( ! b ) { return { open: false }; }
			const menu = document.getElementById( b.getAttribute( 'aria-controls' ) );
			const ad = b.getAttribute( 'aria-activedescendant' );
			const el = ad ? document.getElementById( ad ) : null;
			return {
				open: !! ( menu && ! menu.hidden ),
				inBody: !! ( menu && menu.parentElement === document.body ),
				marked: !! ( el && el.classList.contains( 'is-kbd' ) ),
				tabbable: menu ? [ ...menu.querySelectorAll( '.aod-cd-csel-opt' ) ].filter( ( o ) => o.tabIndex !== -1 ).length : -1,
			};
		} );
		t( st.open, 'Flèche bas ouvre le menu' );
		t( st.marked, 'Flèche bas désigne une option (surlignage clavier visible)' );
		t( st.inBody, '(rappel) le menu vit bien dans le <body> — d’où le modèle activedescendant' );
		t( st.tabbable === 0, 'Aucune option n’est un arrêt de tabulation', `${ st.tabbable } option(s) tabulable(s)` );

		await page.keyboard.press( 'Tab' );
		await page.waitForTimeout( 150 );
		const stray = await page.evaluate( () =>
			[ ...document.querySelectorAll( '.aod-cd-csel-menu' ) ].filter( ( m ) => ! m.hidden ).length );
		t( stray === 0, 'Tab referme le menu (pas de menu flottant orphelin)', `${ stray } resté(s) ouvert(s)` );
	} else {
		console.log( '  ❌ aucun csel VISIBLE sur /gestion/shipping' );
		fails++;
	}

	/* ---------- 3. catdd : sélecteur de catégories (formulaire produit) ---------- */
	console.log( '\n--- catdd : sélecteur de catégories (formulaire produit) ---' );
	await page.goto( `${ BASE }/gestion/products?action=new`, { waitUntil: 'networkidle' } );
	await page.evaluate( () => document.querySelectorAll( 'details.aod-cd-acc' ).forEach( ( d ) => { d.open = true; } ) );
	await page.waitForTimeout( 250 );
	if ( await page.locator( '.aod-cd-catdd-btn' ).count() ) {
		await page.locator( '.aod-cd-catdd-btn' ).first().focus();
		await page.keyboard.press( 'Enter' );
		await page.waitForTimeout( 150 );
		t( await page.locator( '.aod-cd-catdd-panel' ).first().isVisible(), 'Entrée ouvre le panneau' );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 100 );
		const a = await active( page );
		t( ! ( await page.locator( '.aod-cd-catdd-panel' ).first().isVisible() ), 'Échap referme le panneau' );
		t( a.includes( 'catdd-btn' ), 'Échap rend le focus au bouton', a );
	} else {
		// 🚨 Ici l'absence n'est JAMAIS légitime. Contrairement au sélecteur de
		// statut, `.aod-cd-catdd-btn` est rendu INCONDITIONNELLEMENT par le
		// formulaire produit (class-aod-cd-section-products.php) : une boutique sans
		// aucune catégorie affiche quand même le bouton, avec « Aucune catégorie
		// pour le moment » dans le panneau. S'il manque, le formulaire est cassé.
		t( false, 'Le sélecteur de catégories est présent sur le formulaire produit',
			'aucun .aod-cd-catdd-btn — il est pourtant rendu sans condition' );
	}

	/* ---------- 4. Modale : piège de focus ---------- */
	// `aria-modal="true"` ne piège PAS le focus : c'est une promesse faite aux
	// lecteurs d'écran, pas un comportement du navigateur. Sans piège réel, on
	// tabule derrière la modale, dans une page qu'on ne voit plus.
	console.log( '\n--- Modale Équipe : piège de focus ---' );
	await page.goto( `${ BASE }/gestion/team`, { waitUntil: 'networkidle' } );
	await page.locator( '#aod-cd-team-add' ).click();
	await page.waitForTimeout( 250 );
	let a = await active( page );
	t( await page.locator( '#aod-cd-team-modal' ).isVisible(), 'La modale s’ouvre' );
	t( ! a.includes( 'team-add' ), 'Le focus entre dans la modale à l’ouverture', `focus = ${ a }` );

	let escaped = null;
	for ( let i = 0; i < 40; i++ ) {
		await page.keyboard.press( 'Tab' );
		const inside = await page.evaluate( () => {
			const m = document.getElementById( 'aod-cd-team-modal' );
			return !! ( m && m.contains( document.activeElement ) );
		} );
		if ( ! inside ) { escaped = await active( page ); break; }
	}
	t( escaped === null, 'Le focus reste piégé dans la modale (40 Tab)', escaped ? `échappé vers ${ escaped }` : '' );

	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 200 );
	const closed = ! ( await page.locator( '#aod-cd-team-modal' ).isVisible() );
	a = await active( page );
	t( closed, 'Échap referme la modale' );
	t( a.includes( 'team-add' ), 'Le focus revient sur le bouton déclencheur', `focus = ${ a }` );

	/* ---------- 5. Barre latérale : le rail se déplie au CLAVIER (43.8) ----------
	 *
	 * 🚨 LE DÉFAUT QUE CETTE SECTION EXISTE POUR ATTRAPER. Un rail qui se déplie
	 * au survol est trivial à écrire ; le rendre utilisable au clavier ne l'est
	 * pas. Si les libellés étaient masqués par `display:none` ou
	 * `visibility:hidden` — le réflexe naturel —, les liens sortiraient de
	 * l'ordre de tabulation, `:focus-within` ne se déclencherait JAMAIS, et
	 * l'utilisateur clavier tabulerait à travers une barre restée repliée : le
	 * focus visuel et l'état visible divergeraient, exactement le point
	 * d'attention porté par les Phases 5.8/17.
	 *
	 * Le cliquet `test-sidebar-rail.php` interdit ces deux propriétés dans la
	 * feuille ; ici on vérifie le COMPORTEMENT RÉEL, au TAB, dans un navigateur.
	 */
	console.log( '\n--- Barre latérale : rail replié/déplié au clavier ---' );

	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	await garerSouris( page ); // curseur hors du rail : aucun survol (helper partagé, cf. lib.js).
	await page.waitForTimeout( 300 );

	const railW = () => page.evaluate( () => {
		const s = document.querySelector( '.aod-cd-side' );
		return s ? Math.round( s.getBoundingClientRect().width ) : -1 ;
	} );
	const mainX = () => page.evaluate( () => {
		const m = document.querySelector( '.aod-cd-main' );
		return m ? Math.round( m.getBoundingClientRect().left ) : -1;
	} );

	const wReplie   = await railW();
	const xAvant    = await mainX();
	t( wReplie > 0 && wReplie < 120, `Desktop : la barre est REPLIÉE par défaut (${ wReplie } px)`, `largeur lue : ${ wReplie }` );

	// Le lien doit être ATTEIGNABLE au clavier alors que la barre est repliée.
	await page.locator( '.aod-cd-navlink' ).first().focus();
	await page.waitForTimeout( 300 );
	const aNav = await active( page );
	const wFocus = await railW();
	t( aNav.includes( 'navlink' ), 'Un lien de nav reçoit le focus alors que la barre est repliée', `focus = ${ aNav }` );
	t( wFocus > wReplie + 100, `🔴 Le FOCUS clavier déplie la barre (${ wReplie } → ${ wFocus } px)`, 'si elle reste repliée, `:focus-within` ne se déclenche pas — libellés probablement en display:none' );

	// Le libellé doit être VISIBLE une fois déplié (sinon on tabule à l'aveugle).
	const lblOp = await page.evaluate( () => {
		const l = document.querySelector( '.aod-cd-navlink span' );
		return l ? getComputedStyle( l ).opacity : null;
	} );
	t( '1' === lblOp, '…et le libellé du lien focalisé est LISIBLE', `opacity = ${ lblOp }` );

	// 🔴 Le contenu ne doit PAS avoir bougé : la barre se superpose, elle ne pousse pas.
	t( ( await mainX() ) === xAvant, `🔴 Le contenu ne se décale pas quand la barre se déplie (x = ${ xAvant } px)`, 'une page qui glisse horizontalement au survol/focus est une régression de lecture' );

	// Perte de focus → la barre se replie.
	await page.evaluate( () => document.activeElement.blur() );
	await garerSouris( page );
	await page.waitForTimeout( 350 );
	t( ( await railW() ) === wReplie, 'La barre se replie quand le focus la quitte' );

	// 43.7 — hauteur d'écran réduite : le contenu de la barre reste ATTEIGNABLE.
	await page.setViewportSize( { width: 1440, height: 360 } );
	await page.waitForTimeout( 300 );
	const scroll = await page.evaluate( () => {
		const s = document.querySelector( '.aod-cd-side' );
		return { deborde: s.scrollHeight > s.clientHeight, ovY: getComputedStyle( s ).overflowY };
	} );
	t( scroll.deborde && 'auto' === scroll.ovY, `🔴 43.7 : écran court → la barre DÉFILE au lieu de déborder en silence (overflow-y: ${ scroll.ovY })` );
	await page.setViewportSize( { width: 1440, height: 900 } );

	/* ---------- 6. Le rail replié TIENT dans l'écran (45.5) ----------
	 *
	 * 🚨 POURQUOI CETTE SECTION VIT ICI, ET POURQUOI ELLE NE POUVAIT PAS ÊTRE UN
	 * TEST UNITAIRE. Ce qu'elle mesure naît de la COMPOSITION de six règles CSS et
	 * de la métrique d'une police : relire la feuille ne peut pas le voir. La 45.1
	 * l'avait appris à ses dépens dans l'autre sens — son centrage a été prouvé par
	 * une sonde jetable, et RIEN ne le gardait ensuite (`test-sidebar-rail.php` ne
	 * porte pas une ligne sur le sujet). Les deux mesures — tient-il, et reste-t-il
	 * centré — sont donc réunies ici, au même endroit que le reste du rail.
	 *
	 * 🚨 LE DÉFAUT QU'ELLE ATTRAPE. Replié, le rail n'affiche que des icônes, mais
	 * sa hauteur était encore dictée par les libellés invisibles : `opacity:0`
	 * masque à l'œil et ne retire RIEN à la mise en page. Mesuré avant correctif :
	 * 1069 px en FR, **1348 px en AR** pour 12 icônes — un rail plus haut de 279 px
	 * dans une langue dont il n'affiche pas un caractère. Il débordait donc de
	 * toute fenêtre d'ordinateur portable, et la barre de défilement qui en
	 * résultait DÉCENTRAIT les icônes que la 45.1 venait de centrer.
	 */
	console.log( '\n--- 45.5 : le rail replié tient dans l’écran (FR et AR) ---' );

	const mesureRail = () => page.evaluate( () => {
		const side  = document.querySelector( '.aod-cd-side' );
		const rs    = side.getBoundingClientRect();
		const liens = [ ...document.querySelectorAll( '.aod-cd-navlink' ) ];
		const centre = rs.left + rs.width / 2;
		return {
			dir: document.documentElement.getAttribute( 'dir' ) || 'ltr',
			largeur: Math.round( rs.width ),
			contenu: side.scrollHeight,
			visible: side.clientHeight,
			// Largeur PRISE par la barre de défilement dans la mise en page.
			gouttiere: side.offsetWidth - side.clientWidth,
			nbLiens: liens.length,
			decalMax: liens.reduce( ( m, l ) => {
				const i = l.querySelector( 'svg' ).getBoundingClientRect();
				return Math.max( m, Math.abs( i.left + i.width / 2 - centre ) );
			}, 0 ),
		};
	} );

	const allerRail = async ( lang, w, h ) => {
		await ctx.addCookies( [ { name: 'aod_lang', value: lang, url: BASE } ] );
		await page.setViewportSize( { width: w, height: h } );
		await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'domcontentloaded' } );
		await garerSouris( page ); // 🚨 sans ça, le curseur DÉPLIE le rail et la mesure est fausse (D29).
		await page.waitForTimeout( 320 );
		return mesureRail();
	};

	// A. Il tient — sur toutes les hauteurs d'écran d'ordinateur portable, dans les
	//    deux langues. Le témoin publie combien de couples ont VRAIMENT été mesurés.
	const HAUTEURS = [ 900, 768, 720 ];
	let couples = 0;
	for ( const lang of [ 'fr', 'ar' ] ) {
		for ( const hv of HAUTEURS ) {
			const m = await allerRail( lang, 1440, hv );
			couples++;
			t(
				m.contenu <= m.visible,
				`🔴 Rail replié ${ lang.toUpperCase() } (dir=${ m.dir }) sur 1440×${ hv } : tient SANS défilement (${ m.contenu } px pour ${ m.visible } px)`,
				`déborde de ${ m.contenu - m.visible } px — un libellé invisible a dû reprendre du poids en hauteur`
			);
			t(
				m.decalMax <= 1 && m.largeur < 120,
				`…et les ${ m.nbLiens } icônes restent centrées dans le rail (décalage max ${ Math.round( m.decalMax * 100 ) / 100 } px)`,
				`rail ${ m.largeur } px, décalage ${ m.decalMax } px — la 45.1 est défaite`
			);
		}
	}
	t( 6 === couples, `TÉMOIN : ${ couples } couples (langue × hauteur d’écran) réellement mesurés` );

	// B. L'INVARIANT, et non un seuil : la hauteur du rail replié ne dépend PLUS de
	//    la langue. On la lit sur une fenêtre volontairement trop courte (600 px),
	//    seul cas où `scrollHeight` rend la hauteur NATURELLE — dès qu'elle tient,
	//    `.aod-cd-nav{flex:1}` l'étire à la fenêtre et la mesure ne dirait plus rien.
	const courtFr = await allerRail( 'fr', 1440, 600 );
	const courtAr = await allerRail( 'ar', 1440, 600 );
	const ecart   = Math.abs( courtFr.contenu - courtAr.contenu );
	t(
		ecart <= 2,
		`🔴 La hauteur du rail replié ne dépend plus de la LANGUE : ${ courtFr.contenu } px en FR, ${ courtAr.contenu } px en AR (écart ${ ecart } px ; il était de 279 px avant la 45.5)`,
		'un texte que le rail n’affiche pas s’est remis à peser en hauteur — c’est TOUT le sujet de la 45.5'
	);

	// C. Écran trop court : le défilement de la 43.7 est intact, et il ne coûte
	//    AUCUNE largeur — sans quoi il décentrerait les icônes (D7 de A.21).
	const court43 = await page.evaluate( () => {
		const s = document.querySelector( '.aod-cd-side' );
		return { deborde: s.scrollHeight > s.clientHeight, ovY: getComputedStyle( s ).overflowY };
	} );
	t( court43.deborde && 'auto' === court43.ovY, `🔴 43.7 INTACTE : à 600 px de haut le rail déborde et DÉFILE toujours (overflow-y: ${ court43.ovY })` );
	t(
		courtFr.decalMax <= 1 && courtAr.decalMax <= 1,
		`🔴 …et les icônes restent centrées ALORS MÊME que le rail défile (FR ${ Math.round( courtFr.decalMax * 100 ) / 100 } px, AR ${ Math.round( courtAr.decalMax * 100 ) / 100 } px)`,
		'c’est le cas que la 45.1 seule ne couvrait pas : elle ne tenait que sur les fenêtres assez hautes pour ne pas défiler'
	);

	/* 🚨 CE QUE CETTE PORTE NE PEUT PAS PROUVER, ET OÙ C'EST PROUVÉ À LA PLACE.
	 * « Repliée, la barre de défilement ne prend aucune largeur » (D7) est
	 * INVÉRIFIABLE ici : le Chromium de la recette rend des barres en
	 * SURIMPRESSION — `offsetWidth − clientWidth` y valait déjà 0 AVANT le
	 * correctif, et y vaudrait encore 0 si on retirait `scrollbar-width:none`.
	 * Une assertion qui ne peut pas échouer sur la plateforme où elle tourne ne
	 * prouve rien (c'est la leçon `FS_METHOD` de TEMPLATE.md §3 : éprouver une
	 * couche dans le contexte où elle marche, c'est se garantir un vert
	 * permanent). Découvert en éprouvant CETTE porte au rouge : réinjecter
	 * `scrollbar-gutter:stable both-edges` — le remède rejeté en D7 — n'a
	 * déclenché AUCUN échec. La règle est donc gardée à la source, par R7 de
	 * `tests/unit/test-sidebar-rail.php`, qui lui peut rougir.
	 */

	// D. Le dépliage fait glisser les liens (D8) — la promesse est CHIFFRÉE, donc
	//    elle se garde. Ce qui la borne : le rythme vertical est le même dans les
	//    deux états, et le glissement ne vient que des 6 intitulés de groupe. S'il
	//    explose, c'est qu'une hauteur s'est remise à dépendre de l'état (ou de
	//    l'écriture : il valait 261 px en AR avant la normalisation de l'interligne).
	const glissement = async ( lang ) => {
		await allerRail( lang, 1440, 900 );
		const avant = await page.evaluate( () => [ ...document.querySelectorAll( '.aod-cd-navlink' ) ].map( ( l ) => Math.round( l.getBoundingClientRect().top ) ) );
		const rtl = 'ar' === lang;
		await page.mouse.move( rtl ? 1420 : 20, 450 ); // dans le rail : il se déplie.
		await page.waitForTimeout( 420 );
		const apres = await page.evaluate( () => [ ...document.querySelectorAll( '.aod-cd-navlink' ) ].map( ( l ) => Math.round( l.getBoundingClientRect().top ) ) );
		await garerSouris( page );
		return { max: Math.max( ...avant.map( ( v, i ) => Math.abs( apres[ i ] - v ) ) ), tete: Math.max( ...avant.slice( 0, 4 ).map( ( v, i ) => Math.abs( apres[ i ] - v ) ) ), n: avant.length };
	};
	const gFr = await glissement( 'fr' );
	const gAr = await glissement( 'ar' );
	t(
		gFr.max <= 130 && gAr.max <= 130,
		`Le dépliage fait glisser les liens de ${ gFr.max } px au plus en FR et ${ gAr.max } px en AR (borne assumée en D8 : 130 px, sur ${ gFr.n } liens)`,
		'au-delà, une hauteur dépend de nouveau de l’état déplié — le curseur ne trouverait plus l’entrée qu’il vise'
	);
	t(
		gAr.max <= gFr.max + 25,
		`🔴 …et l’arabe ne glisse pas plus que le français (${ gAr.max } px contre ${ gFr.max } px) — il glissait de 261 px contre 102 avant que l’interligne soit fixé`,
		'une écriture ne doit pas décider de la géométrie du rail'
	);
	t(
		gFr.tete <= 60 && gAr.tete <= 60,
		`…et les 4 entrées du travail quotidien bougent d’au plus ${ Math.max( gFr.tete, gAr.tete ) } px (le glissement est ordonné par la profondeur — D8)`
	);

	await ctx.addCookies( [ { name: 'aod_lang', value: 'fr', url: BASE } ] );
	await page.setViewportSize( { width: 1440, height: 900 } );

	/* ---------- 8. La modale PARTAGÉE `#aod-cd-modal` (47.4) ----------
	 *
	 * 🚨 POURQUOI CE BLOC N'EXISTAIT PAS, ET CE QUE ÇA COÛTAIT. Le bloc 4
	 * ci-dessus teste `#aod-cd-team-modal` — la modale de la section Équipe, qui a
	 * son propre markup et ses propres `[data-team-close]`. La modale PARTAGÉE, qui
	 * porte le détail d'une commande ET la timeline de Suivi, n'était couverte par
	 * AUCUNE porte du dépôt sur ses chemins de fermeture : `grep` de `backdrop`,
	 * `modal-x` ou `data-close` sur `tools/recette/*.js` ne rendait rien.
	 * Le plan de la 47.4 a d'ailleurs invoqué ce filet — « Échap et la croix
	 * restent : la porte clavier verrait une modale sans issue » — alors qu'il
	 * n'existait pas. Une 47.4 qui aurait désarmé `Échap` par mégarde en même temps
	 * que le fond serait partie VERTE. C'est la 47.4 qui l'écrit.
	 *
	 * 🚨 ET CE QU'IL MESURE NE PEUT PAS ÊTRE UN CLIQUET. La politique du fond est
	 * décidée AU CLIC, par délégation : la relecture d'une source ne dit pas si un
	 * clic ferme. Mesuré pendant l'étude : retirer `data-close` du fond APRÈS le
	 * démarrage ne change rien — l'écouteur avait été posé sur l'élément, par
	 * référence. Seul un vrai clic tranche.
	 */
	console.log( '\n--- 47.4 : la modale partagée (fond désarmé, Échap et croix intacts) ---' );

	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	await page.waitForTimeout( 300 );

	const nbDetail = await page.locator( '.aod-cd-order-detail' ).count();
	// TÉMOIN : sans ligne, tout ce bloc serait vert sans avoir rien ouvert.
	t( nbDetail > 0, `TÉMOIN : ${ nbDetail } bouton(s) « Détail » sur /gestion/orders`,
		'aucune commande listée — le bloc mesurerait le vide' );

	if ( nbDetail > 0 ) {
		const modaleOuverte = () => page.evaluate( () => {
			const m = document.getElementById( 'aod-cd-modal' );
			return !! ( m && ! m.hidden );
		} );
		const ouvrirDetail = async () => {
			await page.locator( '.aod-cd-order-detail' ).first().click();
			await page.waitForSelector( '#aod-cd-modal:not([hidden])', { timeout: 8000 } );
			await page.waitForTimeout( 600 ); // le corps arrive en AJAX
		};
		const clicFond = () => page.locator( '#aod-cd-modal .aod-cd-modal-backdrop' ).click( { position: { x: 5, y: 5 } } );

		// A. Le fond NE FERME PLUS — c'est la demande 5 de l'owner.
		await ouvrirDetail();
		t( await modaleOuverte(), 'La modale de détail s’ouvre' );
		await clicFond();
		await page.waitForTimeout( 300 );
		t( await modaleOuverte(),
			'🔴 Un clic sur le FOND ne ferme plus la modale de détail (on y saisit)',
			'un clic à côté jette la saisie en cours, sans confirmation ni retour en arrière' );

		// B. …et elle garde ses DEUX issues. Une modale qu'on ne peut plus fermer
		//    serait un piège bien pire que le défaut corrigé.
		let sorti = null;
		for ( let i = 0; i < 40; i++ ) {
			await page.keyboard.press( 'Tab' );
			const dedans = await page.evaluate( () => {
				const m = document.getElementById( 'aod-cd-modal' );
				return !! ( m && m.contains( document.activeElement ) );
			} );
			if ( ! dedans ) { sorti = await active( page ); break; }
		}
		t( sorti === null, 'Le focus reste piégé dans la modale (40 Tab)', sorti ? `échappé vers ${ sorti }` : '' );

		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 300 );
		t( ! ( await modaleOuverte() ), '🔴 Échap ferme toujours' );
		// ⚠️ On interroge la LISTE des classes, pas `active()` : ce helper ne rend
		// que la PREMIÈRE (`aod-cd-btn`, portée par tous les boutons du tableau de
		// bord) et l'assertion aurait échoué sur un comportement pourtant correct.
		const apresEchap = await page.evaluate( () => {
			const a = document.activeElement;
			return {
				declencheur: !! ( a && a.classList && a.classList.contains( 'aod-cd-order-detail' ) ),
				quoi: a ? a.tagName.toLowerCase() + '.' + ( a.getAttribute( 'class' ) || '' ) : 'null',
			};
		} );
		t( apresEchap.declencheur, 'Le focus revient sur le bouton déclencheur', `focus = ${ apresEchap.quoi }` );

		await ouvrirDetail();
		await page.locator( '#aod-cd-modal .aod-cd-modal-x' ).click();
		await page.waitForTimeout( 300 );
		t( ! ( await modaleOuverte() ), '🔴 La croix ferme toujours' );

		// C. Le corps est vidé à la fermeture — sinon la modale suivante affiche
		//    un instant le contenu de la précédente.
		const reste = await page.evaluate( () => {
			const b = document.querySelector( '#aod-cd-modal .aod-cd-modal-body' );
			return b ? b.innerHTML.length : -1;
		} );
		t( 0 === reste, 'Le corps de la modale est vidé à la fermeture', `${ reste } octets restants` );

		// D. Le garde survit à un RE-RENDU du corps. C'est la leçon 47.2 : un
		//    écouteur posé sur un nœud meurt avec le nœud. Ici la délégation vit
		//    sur la modale, pas sur ses enfants — on le prouve en remplaçant le
		//    corps, puis en recliquant le fond.
		await ouvrirDetail();
		await page.evaluate( () => {
			const b = document.querySelector( '#aod-cd-modal .aod-cd-modal-body' );
			if ( b ) { b.innerHTML = '<p>corps remplacé</p>'; }
		} );
		await clicFond();
		await page.waitForTimeout( 300 );
		t( await modaleOuverte(),
			'🔴 …et le fond reste désarmé APRÈS un re-rendu du corps (délégation, pas écouteur par élément)',
			'un écouteur meurt avec son nœud : la modale se refermerait de nouveau au clic à côté' );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 200 );
	}

	/* ── 47.7 · La question maison : Annuler ne déclenche RIEN ──────────────────
	 *
	 * 🚨 C'EST LA SEULE RÈGLE QUI COMPTE, et le seul défaut que le remplacement
	 * des 13 dialogues natifs peut introduire. Il est SILENCIEUX : une garde qui
	 * laisse passer ne se voit qu'au moment où l'on perd une donnée. Le piège
	 * exact est nommé dans `confirmModal()` — `if ( ! confirmModal(…) )` est
	 * toujours faux, une Promise étant truthy ; un remplacement mécanique aurait
	 * produit 13 gardes mortes.
	 *
	 * ⚠️ Éprouvé sur un geste RÉVERSIBLE — la suppression groupée de commandes,
	 * qu'on ANNULE — jamais sur la remise à zéro de la boutique. Et la preuve
	 * n'est pas « la ligne est toujours là » : c'est qu'AUCUNE REQUÊTE n'est
	 * partie. Une ligne encore présente pourrait n'être qu'un rendu non
	 * rafraîchi ; une requête absente ne s'interprète pas.
	 */
	console.log( '\n--- 47.7 : la question maison (Annuler n\'agit pas) ---' );

	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	await page.waitForTimeout( 300 );

	const nbCases = await page.locator( '.aod-cd-ordercheck' ).count();
	// TÉMOIN : sans commande cochable, tout ce bloc serait vert sans rien ouvrir.
	t( nbCases > 0, `Témoin : ${ nbCases } commande(s) cochable(s) — sinon ce bloc serait vrai par vacuité` );

	if ( nbCases > 0 ) {
		// On trace les appels AJAX de suppression. Aucun ne doit partir.
		const suppressions = [];
		page.on( 'request', ( r ) => {
			const d = r.postData();
			if ( d && d.includes( 'aod_cd_delete_orders' ) ) { suppressions.push( r.url() ); }
		} );

		// ⚠️ On coche par `click()` sur l'élément, pas par `locator.check()` : la
		// case est recouverte par son libellé étendu (cible tactile de 44 px,
		// 46.6) et Playwright refuse alors le clic « réel ». Ce n'est pas la case
		// qu'on éprouve ici — c'est la fenêtre.
		await page.evaluate( () => {
			const cb = document.querySelector( '.aod-cd-ordercheck' );
			if ( cb && ! cb.checked ) { cb.click(); }
		} );
		await page.waitForTimeout( 300 );
		await page.locator( '.aod-cd-order-bulk-del' ).click();
		await page.waitForTimeout( 500 );

		// 🚨 `:not(#aod-cd-modal)` — et ce n'est pas de la coquetterie de sélecteur.
		// La modale PARTAGÉE de la 47.4 est un markup PERMANENT imprimé en pied de
		// page : masqué, mais présent dans le DOM, avec sa propre
		// `.aod-cd-modal-box`. Sans cette exclusion, « la fenêtre est ouverte » ET
		// « la fenêtre est fermée » étaient TOUTES DEUX vraies par accident —
		// mesuré : la première passait sans qu'aucune fenêtre ne s'ouvre, la
		// seconde échouait alors qu'Annuler faisait son travail. La question de la
		// 47.7, elle, crée un overlay JETABLE, sans id.
		const jetable = '.aod-cd-modal:not(#aod-cd-modal) .aod-cd-modal-box';
		t( await page.locator( jetable ).count() > 0, '🎯 La question s’ouvre dans une fenêtre du SITE (plus un dialogue du navigateur)' );

		// Les boutons viennent du site : ils portent donc du texte traduit.
		const libelles = await page.evaluate( () => {
			const box = [ ...document.querySelectorAll( '.aod-cd-modal:not(#aod-cd-modal) .aod-cd-modal-box' ) ].pop();
			if ( ! box ) { return null; }
			const ok = box.querySelector( '[data-ok]' );
			const an = box.querySelector( '.aod-cd-modal-actions [data-cancel]' );
			return {
				ok: ok ? ok.textContent.trim() : '',
				annuler: an ? an.textContent.trim() : '',
				titre: ( box.querySelector( '.aod-cd-modal-title' ) || {} ).textContent || '',
			};
		} );
		t( !! libelles && '' !== libelles.ok && '' !== libelles.annuler,
			`Les DEUX boutons viennent du site : « ${ libelles && libelles.ok } » / « ${ libelles && libelles.annuler } »`,
			'le dialogue natif empruntait « OK / Cancel » au navigateur' );

		// Le focus est piégé dans la fenêtre (garantie trapFocus, 47.4).
		const dedans = await page.evaluate( () => {
			const box = [ ...document.querySelectorAll( '.aod-cd-modal:not(#aod-cd-modal) .aod-cd-modal-box' ) ].pop();
			return !! ( box && box.contains( document.activeElement ) );
		} );
		t( dedans, 'Le focus est DANS la fenêtre à l’ouverture (sinon la tabulation part derrière)' );

		// 🎯 ANNULER — et rien ne doit partir.
		await page.locator( '.aod-cd-modal:not(#aod-cd-modal) .aod-cd-modal-actions [data-cancel]' ).last().click();
		await page.waitForTimeout( 700 );
		t( await page.locator( jetable ).count() === 0, 'Annuler referme la fenêtre' );
		t( suppressions.length === 0, `🔴 ANNULER N’A DÉCLENCHÉ AUCUNE REQUÊTE (${ suppressions.length } vue(s)) — c’est LE contrôle de la 47.7` );

		// Échap mène à la même issue.
		await page.locator( '.aod-cd-order-bulk-del' ).click();
		await page.waitForTimeout( 500 );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 700 );
		t( await page.locator( jetable ).count() === 0, 'Échap referme la fenêtre' );
		t( suppressions.length === 0, `🔴 Échap non plus n’a rien déclenché (${ suppressions.length } requête(s))` );
	}

	console.log( `\n=== CLAVIER : ${ fails } échec(s) ===` );
	await browser.close();
	process.exit( fails ? 1 : 0 );
} )();
