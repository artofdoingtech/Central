// Contraste WCAG AA sur les 11 sections de /gestion.
//
// Calculé sur le RENDU, jamais sur les tokens CSS : le fond effectif d'un texte
// peut venir de n'importe quel ancêtre, et les alphas se composent. On remonte
// donc les ancêtres jusqu'au premier fond opaque et on empile.
//
// ⚠️ À rejouer sur CHAQUE clone : l'accent est choisi par le client dans le
// Personnalisateur (`aod_cd_accent`). Un accent sombre ou terne peut casser des
// contrastes que l'accent par défaut (jaune très clair) masquait. Voir run.sh,
// qui balaie une série d'accents hostiles.
const { launch, login, ok, BASE, SECTIONS, sectionRendue, garerSouris } = require( './lib.js' );

const AUDIT = function () {
	function parse( c ) {
		const m = c.match( /rgba?\(([^)]+)\)/ );
		if ( ! m ) { return null; }
		const p = m[ 1 ].split( /[,\s/]+/ ).filter( Boolean ).map( parseFloat );
		return { r: p[ 0 ], g: p[ 1 ], b: p[ 2 ], a: p.length > 3 ? p[ 3 ] : 1 };
	}
	function over( fg, bg ) { // composition alpha de fg sur un fond opaque
		const a = fg.a;
		return {
			r: fg.r * a + bg.r * ( 1 - a ),
			g: fg.g * a + bg.g * ( 1 - a ),
			b: fg.b * a + bg.b * ( 1 - a ),
			a: 1,
		};
	}
	function lum( c ) {
		const f = [ c.r, c.g, c.b ].map( function ( v ) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * f[ 0 ] + 0.7152 * f[ 1 ] + 0.0722 * f[ 2 ];
	}
	function ratio( a, b ) {
		const l1 = lum( a ), l2 = lum( b );
		return ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
	}
	function bgOf( el ) {
		const stack = [];
		let n = el;
		while ( n && n.nodeType === 1 ) {
			const c = parse( getComputedStyle( n ).backgroundColor );
			if ( c && c.a > 0 ) {
				stack.push( c );
				if ( c.a === 1 ) { break; }
			}
			n = n.parentElement;
		}
		let base = { r: 255, g: 255, b: 255, a: 1 }; // fond par défaut du navigateur
		for ( let i = stack.length - 1; i >= 0; i-- ) { base = over( stack[ i ], base ); }
		return base;
	}
	function sel( el ) {
		let s = el.tagName.toLowerCase();
		if ( el.id ) { return s + '#' + el.id; }
		const cls = ( el.getAttribute( 'class' ) || '' ).trim().split( /\s+/ ).filter( Boolean );
		if ( cls.length ) { s += '.' + cls.slice( 0, 2 ).join( '.' ); }
		return s;
	}

	const out = [];
	const seen = {};
	const root = document.querySelector( 'body.aod-cd' );
	// Sans ce garde-fou, un mauvais sélecteur racine inspecterait ZÉRO élément et
	// l'audit renverrait un vert parfaitement mensonger.
	if ( ! root ) {
		return [ { sel: 'RACINE body.aod-cd INTROUVABLE', text: '', color: '', bg: '', size: 0, weight: 0, ratio: 0, need: 0 } ];
	}

	root.querySelectorAll( '*' ).forEach( function ( el ) {
		// Ne garder que les éléments qui portent EUX-MÊMES du texte.
		let txt = '';
		for ( let i = 0; i < el.childNodes.length; i++ ) {
			if ( el.childNodes[ i ].nodeType === 3 ) { txt += el.childNodes[ i ].nodeValue; }
		}
		txt = txt.trim();
		if ( ! txt ) { return; }
		// Décor pur, retiré de l'arbre d'accessibilité : exempté par WCAG 1.4.3
		// (« texte purement décoratif »). Nos chevrons ▸ › → sont dans ce cas.
		if ( el.closest( '[aria-hidden="true"]' ) ) { return; }

		const cs = getComputedStyle( el );
		if ( cs.visibility === 'hidden' || cs.display === 'none' || parseFloat( cs.opacity ) === 0 ) { return; }
		const box = el.getBoundingClientRect();
		if ( ! box.width || ! box.height ) { return; }

		const fg = parse( cs.color );
		if ( ! fg ) { return; }
		const bg = bgOf( el );
		const eff = fg.a < 1 ? over( fg, bg ) : fg;
		const r = ratio( eff, bg );

		const size = parseFloat( cs.fontSize );
		const weight = parseInt( cs.fontWeight, 10 ) || 400;
		// AA : « grand texte » = ≥ 24px, ou ≥ 18.66px si gras (≥ 700).
		const large = size >= 24 || ( size >= 18.66 && weight >= 700 );
		const need = large ? 3 : 4.5;
		if ( r >= need ) { return; }

		const key = sel( el ) + '|' + cs.color + '|' + Math.round( r * 100 );
		if ( seen[ key ] ) { return; }
		seen[ key ] = 1;
		out.push( {
			sel: sel( el ),
			text: txt.slice( 0, 40 ),
			color: cs.color,
			bg: 'rgb(' + Math.round( bg.r ) + ',' + Math.round( bg.g ) + ',' + Math.round( bg.b ) + ')',
			size,
			weight,
			ratio: Math.round( r * 100 ) / 100,
			need,
		} );
	} );
	return out;
};

// 🚨 L'ANNEAU DE FOCUS — l'angle mort qui a laissé vivre les 4 anneaux de la 46.1.
//
// Cette porte tourne sur SIX accents hostiles, et n'a jamais regardé un anneau de
// focus : mesuré au verdict A.22, ce fichier contenait 0 occurrence de `focus` et
// 0 de `outline`. Elle aurait rougi au premier run si elle l'avait fait — quatre
// contrôles de /gestion dessinaient leur anneau avec `var(--cd-accent)`, donc à
// ~1,15:1 avec l'accent PAR DÉFAUT du template.
//
// C'est le pendant au rendu de R1 de `test-chrome-conformite.php`, et les deux sont
// nécessaires : le cliquet garde la RÈGLE sur tous les accents à la fois sans en
// instancier aucun ; cette porte garde le RÉSULTAT, un accent à la fois, sur ce
// qu'elle sait réellement focaliser. Aucun des deux ne remplace l'autre.
//
// ⚠️ POURQUOI AU CLAVIER (`Tab`) ET PAS `el.focus()`. Nos anneaux ne ciblent que
// `:focus-visible` — à dessein : la souris ne doit pas les déclencher. Or un
// `.focus()` programmatique ne satisfait PAS toujours l'heuristique
// `:focus-visible` du navigateur. Une sonde bâtie sur `.focus()` mesurerait donc
// des éléments SANS anneau et conclurait « aucun anneau insuffisant » — le vert
// vide, sixième cas du tableau de §C.2. Seul un vrai `Tab` est fiable.
//
// Le seuil est 3:1 : c'est du NON-TEXTE (SC 1.4.11), pas du texte.
const ANNEAUX = async ( page, nom, ajouterAnneau ) => {
	let vus = 0;

	// 🚨 ON NEUTRALISE LA DURÉE DES TRANSITIONS AVANT DE MESURER — et c'est un
	// défaut que la première version de cette sonde a réellement produit.
	//
	// Sans ça, elle tabulait puis lisait le style calculé IMMÉDIATEMENT. Or nos
	// champs animent `box-shadow` (~.15s) : à la première image, Chrome sérialise
	// `rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px` — deux
	// couches transparentes de taille nulle, la forme d'un `none` étiré vers la
	// géométrie de la cible. Composée sur son fond, une couche transparente rend
	// EXACTEMENT le fond : la sonde annonçait « rgb(255,255,255) sur
	// rgb(255,255,255) = 1:1 » sur 40 champs parfaitement conformes.
	//
	// Un faux positif est le bon sens de l'échec — mais 40 faux positifs auraient
	// fait désarmer la porte, pas corriger le CSS (D16). C'est le jumeau exact du
	// piège de la 17.5 : « on éloigne la souris après `check()`, sinon on mesure le
	// survol en croyant mesurer la sélection ». Ici : on mesure l'anneau ÉTABLI,
	// pas son premier vingtième de seconde.
	//
	// Mettre la durée à zéro plutôt qu'attendre 200 ms par tabulation : 40
	// tabulations × 12 sections × 6 accents, l'attente coûterait ~10 minutes de
	// recette pour la même valeur finale. Et on ne touche QUE la durée : la valeur
	// établie, seule chose qui nous intéresse, est inchangée. Le mouvement lui-même
	// reste gardé par la porte `mouvement`, qui est faite pour ça.
	await page.addStyleTag( {
		content: '*,*::before,*::after{transition-duration:0s !important;animation-duration:0s !important}',
	} );

	// 40 tabulations : au-delà on tourne dans la pagination d'un tableau sans
	// rencontrer de composant neuf. Le compte réel est publié, pas supposé.
	for ( let i = 0; i < 40; i++ ) {
		await page.keyboard.press( 'Tab' );
		const r = await page.evaluate( () => {
			const el = document.activeElement;
			if ( ! el || el === document.body || ! el.closest( 'body.aod-cd' ) ) { return null; }
			// On ne juge que ce qui prétend AVOIR un anneau. Un élément que le
			// navigateur n'a pas retenu comme `:focus-visible` (focus à la souris,
			// conteneur non interactif) n'a rien à prouver ici.
			if ( ! el.matches( ':focus-visible' ) ) { return null; }

			function parse( c ) {
				const m = String( c ).match( /rgba?\(([^)]+)\)/ );
				if ( ! m ) { return null; }
				const p = m[ 1 ].split( /[,\s/]+/ ).filter( Boolean ).map( parseFloat );
				return { r: p[ 0 ], g: p[ 1 ], b: p[ 2 ], a: p.length > 3 ? p[ 3 ] : 1 };
			}
			function over( fg, bg ) {
				const a = fg.a;
				return { r: fg.r * a + bg.r * ( 1 - a ), g: fg.g * a + bg.g * ( 1 - a ), b: fg.b * a + bg.b * ( 1 - a ), a: 1 };
			}
			function lum( c ) {
				const f = [ c.r, c.g, c.b ].map( function ( v ) {
					v /= 255;
					return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
				} );
				return 0.2126 * f[ 0 ] + 0.7152 * f[ 1 ] + 0.0722 * f[ 2 ];
			}
			function ratio( a, b ) {
				const l1 = lum( a ), l2 = lum( b );
				return ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
			}
			function bgOf( n ) {
				const stack = [];
				while ( n && n.nodeType === 1 ) {
					const c = parse( getComputedStyle( n ).backgroundColor );
					if ( c && c.a > 0 ) { stack.push( c ); if ( c.a === 1 ) { break; } }
					n = n.parentElement;
				}
				let base = { r: 255, g: 255, b: 255, a: 1 };
				for ( let i = stack.length - 1; i >= 0; i-- ) { base = over( stack[ i ], base ); }
				return base;
			}

			const cs = getComputedStyle( el );
			let ring = null;
			let via = '';
			const ow = parseFloat( cs.outlineWidth ) || 0;
			if ( ow > 0 && cs.outlineStyle !== 'none' ) {
				ring = parse( cs.outlineColor );
				via = 'outline';
			} else {
				// Repli sur le box-shadow : c'est la forme de `--cd-focus` (liseré
				// d'encre + halo). On prend la PREMIÈRE couleur de la liste — la
				// couche la plus proche du bord, donc celle qui fait l'anneau.
				const m = String( cs.boxShadow ).match( /rgba?\([^)]+\)/ );
				if ( m ) { ring = parse( m[ 0 ] ); via = 'box-shadow'; }
			}
			if ( ! ring ) {
				return { sansAnneau: true, sel: el.tagName.toLowerCase() + '.' + ( el.className || '' ).split( /\s+/ )[ 0 ] };
			}

			// 🚨 Le fond pertinent est celui DERRIÈRE l'anneau, donc celui de
			// l'ANCÊTRE — un outline se dessine hors de la boîte de bordure, avec
			// un offset. Mesurer contre le fond de l'élément lui-même donnerait un
			// ratio flatteur et faux (un bouton blanc sur une carte blanche a le
			// même fond, mais un bouton d'accent sur une carte blanche non).
			const bg  = bgOf( el.parentElement );
			const eff = ring.a < 1 ? over( ring, bg ) : ring;
			return {
				sel: el.tagName.toLowerCase() + ( el.className ? '.' + String( el.className ).split( /\s+/ ).slice( 0, 2 ).join( '.' ) : '' ),
				via,
				ring: 'rgb(' + Math.round( eff.r ) + ',' + Math.round( eff.g ) + ',' + Math.round( eff.b ) + ')',
				bg: 'rgb(' + Math.round( bg.r ) + ',' + Math.round( bg.g ) + ',' + Math.round( bg.b ) + ')',
				ratio: Math.round( ratio( eff, bg ) * 100 ) / 100,
			};
		} );
		if ( ! r ) { continue; }
		vus++;
		ajouterAnneau( nom, r );
	}
	return vus;
};

// 🚨 Les ÉTATS INTERACTIFS — l'angle mort qui a coûté deux défauts à la Phase 17.
//
// Cette porte n'auditait que l'état AU REPOS. Or deux surfaces qui portent du
// texte ne s'affichent JAMAIS au repos :
//
//   · le survol et la sélection d'une ligne de tableau — il faut une souris ;
//   · la frise de suivi d'un colis — elle vit dans un panneau qui s'ouvre au clic.
//
// Les deux ont hébergé de vrais défauts que la porte a déclarés verts pendant
// toute leur existence : un survol invisible une ligne sur deux (17.2), et un
// texte à 2,58:1 dans la frise (17.4). La porte ne mentait pas sur ce qu'elle
// voyait — elle n'avait simplement jamais eu l'occasion d'échouer.
//
// Chaque état est DÉCLENCHÉ, puis mesuré sur le style calculé. Et le passage
// publie ce qu'il a réellement touché : sur un clone sans commandes, il ne
// vérifie rien, et ça doit se lire dans la sortie plutôt que se fondre dans le
// vert (DoD §C.2 — la règle du témoin).
const ETATS = async ( page, all, ajouter ) => {
	const couverture = { lignes: 0, survols: 0, selections: 0, panneaux: 0, retraits: [] };

	// --- Survol et sélection d'une ligne de commande -----------------------
	await page.goto( `${ BASE }/gestion/orders`, { waitUntil: 'networkidle' } );
	const lignes = await page.$$( '.aod-cd-table:not(.aod-cd-carriers) tbody tr' );
	couverture.lignes = lignes.length;

	if ( ! lignes.length ) {
		couverture.retraits.push( 'survol/sélection : aucune commande sur ce clone' );
	} else {
		// On prend une ligne PAIRE quand elle existe : c'est celle que le zébrage
		// recouvrait, donc celle qui masquait le défaut. Se contenter de la
		// première ligne aurait laissé passer 17.2.
		for ( const idx of lignes.length > 1 ? [ 0, 1 ] : [ 0 ] ) {
			const tr = `.aod-cd-table:not(.aod-cd-carriers) tbody tr:nth-child(${ idx + 1 })`;

			await page.hover( tr );
			await page.waitForTimeout( 120 );
			ajouter( `orders (survol ligne ${ idx + 1 })`, await page.evaluate( AUDIT ) );
			couverture.survols++;

			const box = await page.$( `${ tr } .aod-cd-col-check input[type="checkbox"]` );
			if ( box ) {
				await box.check();
				// Éloigner la souris : `check()` la pose sur la ligne, et on
				// mesurerait le survol en croyant mesurer la sélection.
				await garerSouris( page );
				await page.waitForTimeout( 120 );
				ajouter( `orders (ligne ${ idx + 1 } sélectionnée)`, await page.evaluate( AUDIT ) );
				couverture.selections++;
				await box.uncheck();
			}
		}
		await garerSouris( page );
	}

	// --- Frise de suivi : un panneau qui n'existe qu'après un clic ----------
	await page.goto( `${ BASE }/gestion/tracking`, { waitUntil: 'networkidle' } );
	const detail = await page.$( '.aod-cd-track-detail' );
	if ( ! detail ) {
		couverture.retraits.push( 'frise de suivi : aucune commande expédiée sur ce clone' );
	} else {
		await detail.click();
		await page.waitForTimeout( 1500 );
		// Ne compter le panneau que s'il a VRAIMENT rendu quelque chose : un clic
		// qui n'ouvre rien produirait un audit vide, donc un vert sans objet.
		const ouvert = await page.evaluate( () => !! document.querySelector( '.aod-cd-tl-list, .aod-cd-tl-head' ) );
		if ( ouvert ) {
			ajouter( 'tracking (panneau de suivi ouvert)', await page.evaluate( AUDIT ) );
			couverture.panneaux++;
		} else {
			couverture.retraits.push( 'frise de suivi : le panneau ne s\'est pas ouvert' );
		}
	}

	return couverture;
};

( async () => {
	const { browser, page } = await launch();
	await login( page );

	// 🚨 TÉMOIN D'ACCENT (défaut de recette corrigé le 2026-07-21).
	//
	// run.sh prétend rejouer cet audit sur SIX accents hostiles. Mais il ne posait
	// l'accent que par `wp theme mod set`, dont l'échec est muet (sortie avalée), et
	// CETTE porte ne lisait jamais l'accent. Si le `set` échouait (wpcli tombé), la
	// page gardait l'ancien accent : les six passages auditaient le MÊME, six verts
	// identiques sous six en-têtes différents. Le « piège n°1 » du README, désarmé.
	//
	// On lit donc l'accent EFFECTIF sur la page (`--cd-accent`, posé sur :root par
	// AOD_CD_Customize) et on le compare à celui que run.sh a demandé. Un écart =
	// le `set` n'a pas pris → rouge, au lieu d'un audit répété en silence.
	let temoinOk = true;
	const attendu = ( process.env.AOD_EXPECT_ACCENT || '' ).trim().toLowerCase();
	if ( attendu ) {
		await page.goto( `${ BASE }/gestion/`, { waitUntil: 'networkidle' } );
		const vu = ( await page.evaluate( () =>
			getComputedStyle( document.documentElement ).getPropertyValue( '--cd-accent' )
		) ).trim().toLowerCase();
		temoinOk = ( vu === attendu );
		ok(
			temoinOk,
			`Accent effectif sur la page = ${ attendu } (--cd-accent lu : ${ vu || 'vide' })`,
			temoinOk ? '' : 'run.sh a demandé cet accent mais la page en montre un autre — le ' +
				'`theme mod set` a-t-il échoué en silence (wpcli tombé) ? Les 6 passages ' +
				'auditeraient alors le même accent : la couverture « 6 accents » serait fausse.'
		);
	}

	let total = 0;
	const all = {};
	const ajouter = ( nom, hits ) => {
		if ( hits && hits.length ) {
			total += hits.length;
			all[ nom ] = ( all[ nom ] || [] ).concat( hits );
		}
	};

	// 🚨 Sections qui n'ont PAS été auditées parce qu'elles ne se routent plus
	// (31.5). Le routeur retombe en silence sur la première section visible : sans
	// ce relevé, la boucle auditerait `home` douze fois et rapporterait ses
	// résultats sous douze noms différents — un défaut de contraste serait attribué
	// à une page qui n'a jamais été ouverte. Le cas s'est DÉJÀ produit (cf. le
	// commentaire de SECTIONS dans lib.js : `/gestion/stats` rendait `home`).
	const horsRoute = [];

	// Anneaux de focus (46.1) : mesurés sur le RENDU, seuil non-texte 3:1.
	const anneauxFaibles = [];
	let anneauxVus = 0;
	let anneauxSans = 0;
	const ajouterAnneau = ( nom, r ) => {
		if ( r.sansAnneau ) {
			// Un contrôle focalisable QUI SE DÉCLARE `:focus-visible` et ne dessine
			// RIEN est un défaut au moins aussi grave qu'un anneau pâle : l'utilisateur
			// clavier ne sait pas où il est. On le compte à part pour ne pas le
			// confondre avec un anneau insuffisant — la cause et le correctif diffèrent.
			anneauxSans++;
			anneauxFaibles.push( `${ nom } · ${ r.sel } — AUCUN anneau dessiné` );
			return;
		}
		if ( r.ratio < 3 ) {
			anneauxFaibles.push( `${ nom } · ${ r.sel } — ${ r.ring } sur ${ r.bg } = ${ r.ratio }:1 (exigé 3, via ${ r.via })` );
		}
	};

	for ( const s of SECTIONS ) {
		await page.goto( `${ BASE }/gestion/${ s }`, { waitUntil: 'networkidle' } );
		const rendue = await sectionRendue( page );
		if ( rendue !== s ) {
			horsRoute.push( `${ s } → ${ rendue }` );
			continue;
		}
		ajouter( s, await page.evaluate( AUDIT ) );
		anneauxVus += await ANNEAUX( page, s, ajouterAnneau );
	}

	const couverture = await ETATS( page, all, ajouter );

	console.log( '=== CONTRASTE AA — texte sous le seuil ===' );
	const byKey = {};
	for ( const [ s, hits ] of Object.entries( all ) ) {
		for ( const h of hits ) {
			const k = `${ h.sel } | ${ h.color } sur ${ h.bg } | ${ h.ratio }:1 (exigé ${ h.need })`;
			( byKey[ k ] = byKey[ k ] || [] ).push( s );
		}
	}
	for ( const k of Object.keys( byKey ).sort() ) {
		console.log( `  ❌ ${ k }` );
		console.log( `        pages : ${ [ ...new Set( byKey[ k ] ) ].join( ', ' ) }` );
	}

	// Ce que le passage « états » a RÉELLEMENT touché. Sans ce relevé, un clone
	// sans commandes rendrait le même vert qu'un clone où les trois états ont été
	// mesurés — et c'est précisément ainsi que les défauts de 17.2 et 17.4 ont
	// survécu à toutes les exécutions précédentes.
	console.log(
		`  · états interactifs : ${ couverture.survols } survol(s), ` +
		`${ couverture.selections } sélection(s), ${ couverture.panneaux } panneau(x) ` +
		`— sur ${ couverture.lignes } ligne(s) de commande`
	);
	for ( const r of couverture.retraits ) {
		console.log( `  ⚠️  non couvert — ${ r }` );
	}

	// 🚨 TÉMOIN DE ROUTAGE (31.5). On publie le nombre de sections RÉELLEMENT
	// auditées, pas `SECTIONS.length` : annoncer 12 alors qu'on en a ouvert 3
	// serait exactement le mensonge que cette story corrige.
	const auditees = SECTIONS.length - horsRoute.length;
	for ( const hr of horsRoute ) {
		console.log( `  ❌ ROUTAGE : ${ hr } — section NON auditée (le routeur retombe en silence).` );
	}

	// 🚨 TÉMOIN DE COUVERTURE DES ANNEAUX (46.1), et il pèse sur le verdict.
	//
	// Sans lui, une sonde qui ne focaliserait plus rien — `:focus-visible` non
	// satisfait, sélecteur racine changé, `Tab` avalé par un piège de focus —
	// rendrait `anneauxFaibles` vide et le même vert qu'un audit complet. C'est
	// exactement le mode d'échec que ce fichier documente déjà deux fois (le témoin
	// d'accent et le témoin de routage) : un contrôle qui n'a rien regardé ne doit
	// jamais être indistinguable d'un contrôle qui a tout vu.
	//
	// Plancher à 40 : mesuré bien au-delà (12 sections × plusieurs contrôles
	// focalisables chacune). Il borne la DISPARITION du corpus, pas sa croissance.
	const anneauxOk = anneauxVus >= 40 && anneauxFaibles.length === 0;
	for ( const a of anneauxFaibles ) {
		console.log( `  ❌ ANNEAU : ${ a }` );
	}
	ok(
		anneauxOk,
		`anneaux de focus : ${ anneauxVus } contrôle(s) focalisé(s) au clavier, ` +
		`${ anneauxFaibles.length } sous le seuil non-texte 3:1 (dont ${ anneauxSans } sans anneau du tout)`,
		// `ok()` ajoute TOUJOURS ce texte : on le conditionne donc ici, comme le
		// fait déjà le témoin d'accent plus haut. Sans ça, la ligne VERTE se lit
		// comme un défaut — et une sortie qui crie au loup quand tout va bien finit
		// par ne plus être lue du tout.
		anneauxOk
			? ''
			: ( anneauxVus < 40
				? 'moins de 40 contrôles focalisés : la sonde ne parcourt plus le corpus (piège de focus ? `:focus-visible` non satisfait ?) — un audit vide n\'est pas un audit vert'
				: 'anneau(x) invisible(s) : l\'utilisateur clavier ne voit plus où il est' )
	);

	ok(
		total === 0 && horsRoute.length === 0,
		`${ total } occurrence(s) sous AA, ${ Object.keys( byKey ).length } cas distinct(s)` +
		`, ${ auditees }/${ SECTIONS.length } section(s) réellement auditée(s)` +
		` + ${ couverture.survols + couverture.selections + couverture.panneaux } état(s) interactif(s)`
	);

	await browser.close();
	// Le témoin d'accent pèse sur le verdict : un audit répété en silence ne doit
	// pas rendre le même vert qu'un audit réellement rejoué sur 6 accents.
	// Le témoin de ROUTAGE aussi : une section non ouverte n'est pas une section verte.
	process.exit( ( total || ! temoinOk || horsRoute.length || ! anneauxOk ) ? 1 : 0 );
} )();
