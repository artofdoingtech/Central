// Les 12 sections × { FR desktop, FR mobile 390, AR mobile 390 }.
// On traque : erreurs PHP servies, erreurs console, débordement horizontal, et
// bascule RTL effective en arabe. Une capture est prise pour chaque échec.
const path = require( 'path' );
const fs = require( 'fs' );
const { launch, login, ok, BASE, SHOTS, SECTIONS, sectionRendue, etatDonnees, echecReseauHorsOrigine } = require( './lib.js' );

// Le domaine du cookie doit suivre l'URL réelle du clone (pas toujours localhost).
const HOST = new URL( BASE ).hostname;

( async () => {
	let fails = 0;
	// Dette D29 : une section sans données n'a presque rien à auditer (ni
	// tableau, ni ligne, ni barre d'action). On COMPTE ce qu'on a réellement pu
	// inspecter, au lieu d'annoncer 12 sections balayées.
	const creuses = new Set();
	// Compte des tableaux réellement mesurés (46.2). Publié et ASSERTÉ plus bas :
	// une sonde qui ne trouverait plus un seul tableau rendrait le même vert qu'un
	// balayage complet — règle du témoin, point 1.
	let tablesTotal = 0;
	// 46.6 — cibles tactiles réellement mesurées, sur les DEUX passages mobiles.
	let ciblesTotal = 0;
	// 46.6bis — les cases/radios sont comptées A PART : leur regle juge les DEUX
	// dimensions, celle des boutons seulement la hauteur (cf. la sonde).
	let casesTotal = 0;
	// D32 — panneaux de transporteur réellement DÉPLIÉS par la porte. Un tableau
	// n'est mesuré dans son état ouvert que si quelque chose s'est ouvert : sans ce
	// compteur, un clic devenu inopérant rendrait le vert du tableau fermé.
	let panneauxOuverts = 0;
	// 47.4 — cellules « Client » de Commandes réellement mesurées (3 passages).
	let cellulesClient = 0;
	const SEME = '1' === process.env.AOD_D29_SEEDED;
	const t = ( c, m, x ) => { if ( ! ok( c, m, x ) ) { fails++; } };

	/* ── TÉMOIN DE SENS du filtre de console (2026-08-07) ──────────────────────
	 * Cette porte ne juge plus les pannes de transport venues d'un autre domaine
	 * (cf. `echecReseauHorsOrigine()` dans lib.js). Une exemption qui ne se vérifie
	 * pas est un trou : on éprouve donc les DEUX branches ici, à chaque passage.
	 *
	 * 🚨 LA SECONDE ASSERTION EST LA PLUS IMPORTANTE. La première dit « le filtre
	 * écarte bien ce qu'il doit écarter » ; la seconde dit « il n'écarte pas le
	 * reste ». Sans elle, un filtre devenu trop large — un `startsWith` mal écrit,
	 * une origine qui change — rendrait la porte AVEUGLE à nos propres 404 et
	 * verte en permanence. C'est le mode d'échec le plus coûteux d'un garde-fou :
	 * il ne tombe pas en panne, il devient complaisant.
	 * 🔎 Écrit après avoir échoué à produire la contre-épreuve au navigateur (la
	 * requête témoin ne partait pas) : *quand une preuve de bout en bout se dérobe,
	 * on rend la règle éprouvable au lieu de se contenter de la moitié qu'on sait
	 * montrer.* */
	t(
		echecReseauHorsOrigine( 'https://secure.gravatar.com/avatar/x?s=26', 'Failed to load resource: net::ERR_NAME_NOT_RESOLVED' ),
		'TÉMOIN : une panne de transport HORS origine est écartée (gravatar de la barre d’admin)',
		'le filtre ne reconnaît plus le cas pour lequel il existe'
	);
	t(
		! echecReseauHorsOrigine( `${ BASE }/wp-content/plugins/aod-client-dashboard/assets/absent.css`, 'Failed to load resource: the server responded with a status of 404' )
			&& ! echecReseauHorsOrigine( '', 'Uncaught TypeError: x is not a function' )
			&& ! echecReseauHorsOrigine( 'https://exemple.test/x.js', 'Uncaught ReferenceError: y is not defined' ),
		'🔴 TÉMOIN INVERSE : un 404 À NOUS, une exception sans URL et une erreur JS tierce restent JUGÉS',
		'le filtre s’est élargi : la porte ne verrait plus nos propres ressources manquantes'
	);
	fs.mkdirSync( SHOTS, { recursive: true } );

	for ( const mode of [
		{ nom: 'FR desktop 1440', w: 1440, h: 900, ar: false },
		{ nom: 'FR mobile 390', w: 390, h: 844, ar: false },
		{ nom: 'AR mobile 390 (RTL)', w: 390, h: 844, ar: true },
	] ) {
		console.log( `\n=== ${ mode.nom } ===` );
		const { browser, ctx, page, console_, scanPhp } = await launch( { width: mode.w, height: mode.h } );
		await login( page );
		if ( mode.ar ) {
			await ctx.addCookies( [ { name: 'aod_lang', value: 'ar', domain: HOST, path: '/' } ] );
		}

		for ( const s of SECTIONS ) {
			console_.length = 0;
			await page.goto( `${ BASE }/gestion/${ s }`, { waitUntil: 'networkidle' } );
			const php = scanPhp( await page.content() );

			// 🚨 On vérifie qu'on regarde bien LA section demandée (31.5). Sans
			// ça, un slug qui ne se route plus fait retomber le routeur sur la
			// première section visible : la boucle auditerait 12 fois `home` en
			// imprimant 12 ✅. Cf. `sectionRendue()` dans lib.js.
			const rendue = await sectionRendue( page );
			const donnees = await etatDonnees( page );
			if ( donnees.creuse ) { creuses.add( s ); }

			const r = await page.evaluate( () => {
				const de = document.documentElement;
				const lim = de.clientWidth;
				let worst = null;
				// 🚨 Garde-fou de RACINE (celui d'a11y-contraste.js). La recherche de
				// débordement ci-dessous est bornée à `body.aod-cd` : sans cette
				// racine, elle inspecte ZÉRO élément, ne trouve donc aucun
				// débordement, et la page rend un VERT PARFAIT sans rien avoir
				// audité. Attrape (vérifié) : plugin désactivé, page /gestion absente,
				// « accès refusé », 404, coque renommée. N'attrape pas une section
				// inconnue — le routeur retombe sur une section valide.
				const racine = !! document.querySelector( 'body.aod-cd' );
				document.querySelectorAll( 'body.aod-cd *' ).forEach( ( el ) => {
					const b = el.getBoundingClientRect();
					const over = Math.round( b.right - lim );
					if ( over > 2 && ( ! worst || over > worst.over ) ) {
						const cls = ( el.getAttribute( 'class' ) || '' ).split( /\s+/ )[ 0 ];
						worst = { over, sel: el.tagName.toLowerCase() + ( cls ? '.' + cls : '' ) };
					}
				} );
				// 🚨 46.2 — LE DÉBORDEMENT QUI NE DÉBORDE PAS DE LA PAGE.
				//
				// Tout ce qui précède mesure le débordement de la PAGE. Or
				// `.aod-cd-tablewrap` porte `overflow-x:auto` : un tableau trop
				// large défile À L'INTÉRIEUR de son conteneur et la page reste
				// impeccable. C'est ce qui a rendu cette porte aveugle aux constats
				// 2 et 3 d'A.22 — Stock (7 colonnes à édition inline) et les 5
				// tableaux de l'Accueil défilaient horizontalement à 390 px, et la
				// porte les déclarait verts. Un tableau illisible au pouce lui était
				// indistinguable d'un tableau parfait.
				//
				// On mesure donc CHAQUE tableau contre son propre conteneur.
				const tables = [];
				document.querySelectorAll( 'body.aod-cd table.aod-cd-table' ).forEach( ( t ) => {
					const w = t.closest( '.aod-cd-tablewrap' ) || t.parentElement;
					if ( ! w ) { return; }
					const cls = ( t.getAttribute( 'class' ) || '' )
						.split( /\s+/ ).filter( ( c ) => c && c !== 'aod-cd-table' ).join( '.' );
					tables.push( {
						cls: cls || '(nu)',
						cards: t.classList.contains( 'aod-cd-table--cards' ),
						scroll: w.scrollWidth - w.clientWidth,
						rows: t.querySelectorAll( 'tbody tr' ).length,
					} );
				} );

				// 46.6 — CIBLES TACTILES. Mesurées ici et pas dans la porte
				// `souris` : celle-ci est la seule qui tourne déjà à 390 px, et en
				// AR en plus du FR. Un contrôle trop petit au pouce est un défaut
				// de VIEWPORT, il doit se mesurer là où le viewport est mobile.
				// Les hauteurs mesurées AVANT correctif : `.aod-cd-btn-sm` 30 px
				// (157 instances), seg 32, statut 33, assignation 34, pagination 38.
				const petits = [];
				document.querySelectorAll(
					'body.aod-cd a.aod-cd-btn, body.aod-cd button.aod-cd-btn, body.aod-cd .aod-cd-seg__btn,'
					+ ' body.aod-cd .aod-cd-page, body.aod-cd .aod-cd-statussel-btn,'
					+ ' body.aod-cd .aod-cd-assignsel-btn, body.aod-cd .aod-cd-leadassignsel-btn'
				).forEach( ( el ) => {
					const b = el.getBoundingClientRect();
					// Un contrôle de hauteur nulle est masqué (accordéon replié,
					// menu fermé) : il n'est pas une cible, on ne le juge pas.
					if ( ! b.height ) { return; }
					/*
					 * 🔴 ON COMPARE SUR LE MÊME QUANTUM QU'ON RAPPORTE (2026-08-07).
					 * Cette sonde comparait `b.height < 44` — un flottant brut — mais
					 * publiait `Math.round( b.height )`. Elle a fini par rendre, mot
					 * pour mot : « aod-cd-btn.aod-cd-btn-sm@44px × 1 — sous 44 px ».
					 * Un contrôle refusé pour être sous 44 px, avec 44 px affiché à
					 * côté : le lecteur ne peut ni le croire ni agir dessus.
					 *
					 * 🚨 CE N'EST PAS UN RELÂCHEMENT, C'EST UNE UNITÉ. La spec (UX
					 * §5.3) est écrite en pixels entiers, et la charte pose 44 px
					 * comme un cran de son échelle de hauteurs — mesuré au
					 * sous-pixel, TOUS les contrôles de /gestion rendent exactement
					 * 44.00000 px. Le système est donc calé PILE sur le seuil, sans
					 * la moindre marge : la plus petite perturbation de layout
					 * (LayoutUnit de Chromium au 1/64ᵉ, apparition d'une barre de
					 * défilement) fait passer UN contrôle à 43,99 et rougir la porte
					 * pour un écart que personne ne peut voir ni corriger.
					 * *Un seuil qui rougit sur un cas juste finit désactivé* (D16) —
					 * et celui-ci rougissait de façon intermittente, ce qui est pire :
					 * on apprend à le rejouer jusqu'à ce qu'il passe.
					 *
					 * ⚠️ LE MORDANT EST INTACT. Les hauteurs d'AVANT le correctif 46.6
					 * — 30, 32, 33, 34, 38 px — restent toutes très loin sous 44 une
					 * fois arrondies. Ce qui disparaît, c'est uniquement le bruit de
					 * mesure d'un système posé sur la limite, jamais un vrai manque.
					 */
					const h = Math.round( b.height );
					if ( h < 44 ) {
						const cls = ( el.getAttribute( 'class' ) || '' )
							.split( /\s+/ ).filter( ( c ) => c.startsWith( 'aod-cd' ) ).slice( 0, 2 ).join( '.' );
						petits.push( { cls, h } );
					}
				} );

				/* 🔴 46.6bis — LES CASES À COCHER ÉTAIENT HORS PÉRIMÈTRE (2026-08-09).
				 * La liste ci-dessus énumère des BOUTONS. Aucune case, aucun bouton
				 * radio. La porte publiait donc « 350 cibles mesurées, 0 échec »
				 * pendant que **66 cases sur 87** tenaient sous 44 px à 390 px —
				 * dont les 58 de la colonne « Livraison gratuite » (18×18) et celles
				 * qui arment la SUPPRESSION GROUPÉE de Commandes et Abandonnées.
				 * 🚨 La porte ne mentait pas : ses 350 mesures étaient exactes. C'est
				 * son PÉRIMÈTRE qui laissait un trou, et un grand nombre affiché à
				 * côté d'un trou rassure plus qu'il n'informe. *Avant de croire une
				 * porte verte, lire ce qu'elle énumère.*
				 *
				 * ⚠️ SONDE SÉPARÉE, à dessein. Celle des boutons est calibrée au
				 * sous-pixel (cf. le pavé ci-dessus : tout /gestion rend 44.00000) et
				 * ne juge que la HAUTEUR — légitime pour un bouton, qui est toujours
				 * large. Une case est petite dans les DEUX sens : il faut mesurer les
				 * deux, et mélanger les deux règles dans une seule boucle aurait
				 * abîmé une calibration durement acquise.
				 *
				 * ⚠️ ON MESURE LA CIBLE, PAS LE CHAMP. Une case de 16 px dans un
				 * `<label>` de 44 est parfaitement atteignable — cliquer l'étiquette
				 * bascule le champ, nativement. Juger le champ nu ferait rougir des
				 * cas corrects, et un cliquet qui rougit à tort finit désactivé (D16).
				 * On remonte donc au `<label>` englobant, puis au `label[for]`, puis
				 * au champ seulement s'il n'a ni l'un ni l'autre. */
				const cases = [];
				document.querySelectorAll( 'body.aod-cd input[type=checkbox], body.aod-cd input[type=radio]' ).forEach( ( el ) => {
					let cible = el.closest( 'label' );
					if ( ! cible && el.id ) {
						cible = document.querySelector( 'label[for="' + el.id + '"]' );
					}
					if ( ! cible ) { cible = el; }
					const b = cible.getBoundingClientRect();
					if ( ! b.height || ! b.width ) { return; }
					const w = Math.round( b.width );
					const h = Math.round( b.height );
					if ( w < 44 || h < 44 ) {
						const cls = ( el.getAttribute( 'class' ) || '' )
							.split( /\s+/ ).filter( ( c ) => c.startsWith( 'aod-cd' ) ).slice( 0, 2 ).join( '.' );
						cases.push( { cls: cls || '(sans classe)', w, h } );
					}
				} );

				return {
					racine,
					dir: de.getAttribute( 'dir' ) || getComputedStyle( document.body ).direction,
					lang: de.getAttribute( 'lang' ),
					scrollW: de.scrollWidth,
					clientW: de.clientWidth,
					worst,
					tables,
					petits,
					cases,
					casesTotal: document.querySelectorAll( 'body.aod-cd input[type=checkbox], body.aod-cd input[type=radio]' ).length,
					cibles: document.querySelectorAll(
						'body.aod-cd a.aod-cd-btn, body.aod-cd button.aod-cd-btn, body.aod-cd .aod-cd-seg__btn,'
						+ ' body.aod-cd .aod-cd-page, body.aod-cd .aod-cd-statussel-btn,'
						+ ' body.aod-cd .aod-cd-assignsel-btn, body.aod-cd .aod-cd-leadassignsel-btn'
					).length,
				};
			} );

			const overflow = r.scrollW - r.clientW;

			/* D32 — L'EXCEPTION EST PARTIE, ET AVEC ELLE LE DERNIER TROU.
			 *
			 * Cette porte a porté, de la 46.2 à la clôture de D32, une exception
			 * nommée : `.aod-cd-carriers` (Livraison) défilait de 490 px dans un
			 * conteneur de 272. La dette est soldée, donc l'exception n'a plus de
			 * raison d'être — et une exception qui survit à sa raison est
			 * exactement ce par quoi une porte meurt. Il n'y en a plus aucune.
			 *
			 * 🚨 Ce que ce filtre ne pouvait PAS voir, et qui est réparé juste en
			 * dessous : il mesure les tableaux tels que la page les sert, donc
			 * PANNEAUX FERMÉS. Or les 29 lignes de transporteurs déplient un
			 * panneau, et le défaut suivant ne vivait que dans cet état-là —
			 * mesuré sur GRAND écran, où le correctif mobile ne s'applique même
			 * pas : la rotation du chevron portait sur la CELLULE (41 × 68 px), qui
			 * pivotée devient 68 × 41 et dépasse de 13 px le bord de la ligne.
			 * Une porte qui n'ouvre jamais rien ne voit que l'état initial. */
			const tables = ( r.tables || [] ).slice();
			if ( 'shipping' === s && await page.$( 'tr.aod-cd-carrier-row' ) ) {
				await page.click( 'tr.aod-cd-carrier-row' );
				// Laisse la transition du chevron finir : c'est son image FINALE
				// qui doit tenir dans la ligne, pas une image intermédiaire.
				await page.waitForTimeout( 300 );
				const d = await page.evaluate( () => {
					const t = document.querySelector( '.aod-cd-carriers' );
					const w = t.closest( '.aod-cd-scroll' ) || t.parentElement;
					// Les contrôles que l'ouverture RÉVÈLE : fermés, ils font 0 px de
					// haut et la sonde des cibles tactiles les ignore à juste titre.
					const reveles = [];
					document.querySelectorAll( '.aod-cd-carrier-panel:not([hidden]) .aod-cd-btn' ).forEach( ( el ) => {
						const b = el.getBoundingClientRect();
						// Même quantum que la sonde principale (cf. sa note) : comparer
						// un flottant brut ici et un arrondi là-bas ferait diverger
						// deux mesures censées appliquer LA MÊME règle — et le jumeau
						// oublié est le mode d'échec que ce dépôt rencontre le plus.
						if ( b.height && Math.round( b.height ) < 44 ) { reveles.push( { cls: 'aod-cd-carrier-test-btn', h: Math.round( b.height ) } ); }
					} );
					return {
						scroll: w.scrollWidth - w.clientWidth,
						rows: t.querySelectorAll( 'tbody tr' ).length,
						ouverts: t.querySelectorAll( 'tr.aod-cd-carrier-panel:not([hidden])' ).length,
						reveles,
					};
				} );
				// 🚨 TÉMOIN : si le clic n'ouvrait plus rien, la mesure ci-dessous
				// rendrait le même vert que le tableau fermé — un vert vide.
				panneauxOuverts += d.ouverts;
				tables.push( { cls: 'aod-cd-carriers (panneau DÉPLIÉ)', cards: false, scroll: d.scroll, rows: d.rows } );
				if ( mode.w <= 780 ) { r.petits = ( r.petits || [] ).concat( d.reveles ); }
			}

			/* 47.4 — la cellule « Client » de Commandes : DEUX rangs, et rien qui
			 * déborde. Ce que ça mesure naît de la composition de trois règles CSS
			 * et de la métrique d'une police — aucun cliquet ne peut le voir.
			 *
			 * 🚨 Et ce n'est pas au bureau que ça se joue. Le §A.23 annonçait « trois
			 * hauteurs » : vrai à 1440 (105 px), FAUX au pouce, où les trois boutons
			 * ne tenaient pas côte à côte (91 + 103 = 194 pour 180 px disponibles) et
			 * s'empilaient — 210 px et CINQ hauteurs en FR, 160 et quatre en AR.
			 * Après : 98 / 137 / 137 px, en deux rangs partout.
			 *
			 * L'écart des centres verticaux est la seule façon honnête de dire « sur
			 * la même ligne » : un `flex-wrap` qui cède ne change ni les classes ni
			 * la source, seulement la géométrie. */
			if ( 'orders' === s ) {
				const cc = await page.evaluate( () => {
					const cy = ( el ) => { const b = el.getBoundingClientRect(); return b.top + b.height / 2; };
					const cells = [ ...document.querySelectorAll( '.aod-cd-cell-orderclient' ) ];
					let ecart1 = 0, ecart2 = 0, rang2Sous = true, deborde = 0, hauteur = 0;
					cells.forEach( ( td ) => {
						const nom = td.querySelector( '.aod-cd-client b' );
						const det = td.querySelector( '.aod-cd-order-detail' );
						const btns = [ ...td.querySelectorAll( '.aod-cd-call-actions .aod-cd-btn' ) ];
						if ( nom && det ) { ecart1 = Math.max( ecart1, Math.abs( cy( nom ) - cy( det ) ) ); }
						if ( btns.length >= 2 ) { ecart2 = Math.max( ecart2, Math.abs( cy( btns[ 0 ] ) - cy( btns[ 1 ] ) ) ); }
						if ( btns.length && det && cy( btns[ 0 ] ) <= cy( det ) ) { rang2Sous = false; }
						const tr = td.closest( 'tr' );
						if ( tr ) { deborde = Math.max( deborde, td.getBoundingClientRect().right - tr.getBoundingClientRect().right ); }
						hauteur = Math.max( hauteur, td.getBoundingClientRect().height );
					} );
					return {
						n: cells.length,
						ecart1: Math.round( ecart1 ),
						ecart2: Math.round( ecart2 ),
						rang2Sous,
						deborde: Math.round( deborde ),
						hauteur: Math.round( hauteur ),
					};
				} );
				cellulesClient += cc.n;
				if ( cc.n ) {
					t( cc.ecart1 < 12, `47.4 : nom et « Détail » sur la MÊME ligne (${ cc.n } cellules, écart max ${ cc.ecart1 } px)`,
						'le rang 1 a cédé : « Détail » est repassé à la ligne' );
					t( cc.ecart2 < 12 && cc.rang2Sous, `47.4 : Appeler et WhatsApp groupés, SOUS le rang 1 (écart ${ cc.ecart2 } px)`,
						'le rang 2 s’est disloqué ou est remonté au-dessus du rang 1' );
					t( cc.deborde <= 0, `47.4 : la cellule ne déborde pas de sa ligne (${ cc.deborde } px, hauteur max ${ cc.hauteur } px)`,
						'elle dépasse du <tr> : la carte est rognée au pouce' );
				}
			}

			const tablesLarges = tables.filter( ( t ) => t.scroll > 2 );
			tablesTotal += tables.length;

			/* 46.6 — les cibles tactiles ne sont jugées QU'EN MOBILE (D3).
			 * Sur desktop, une hauteur de 32 px est un choix de densité assumé :
			 * l'exiger à 44 px gonflerait chaque tableau pour résoudre un problème
			 * qui n'existe qu'au pouce. Le même passage sert donc les deux modes
			 * sans appliquer la même règle — c'est voulu, et c'est écrit. */
			const jugeCibles = mode.w <= 780;
			const petits = jugeCibles ? ( r.petits || [] ) : [];
			// 46.6bis — même règle mobile-seulement que les boutons : sur desktop une
			// case de 16 px visée à la souris est un choix de densité, pas un défaut.
			const cases = jugeCibles ? ( r.cases || [] ) : [];
			if ( jugeCibles ) { ciblesTotal += r.cibles || 0; casesTotal += r.casesTotal || 0; }

			const okPage = r.racine && rendue === s && php.length === 0 && console_.length === 0 && overflow <= 2
				&& tablesLarges.length === 0
				&& petits.length === 0
				&& cases.length === 0
				&& ( ! mode.ar || r.dir === 'rtl' );

			if ( okPage ) {
				console.log( `  ✅ /${ s }` );
				continue;
			}
			fails++;
			console.log( `  ❌ /${ s }` );
			if ( ! r.racine )      { console.log( '        RACINE   : body.aod-cd INTROUVABLE — page /gestion absente, renommée ou refusée. Rien n’a été audité.' ); }
			if ( rendue !== s )    { console.log( `        ROUTAGE  : /${ s } a rendu « ${ rendue } » — la section ne se route plus (le routeur retombe en silence). Cette page N’A PAS été auditée.` ); }
			if ( php.length )      { console.log( `        PHP      : ${ php.join( ' | ' ) }` ); }
			if ( console_.length ) { console.log( `        console  : ${ console_.join( ' | ' ) }` ); }
			if ( overflow > 2 )    { console.log( `        overflow : ${ overflow }px — ${ r.worst ? r.worst.sel + ' dépasse de ' + r.worst.over + 'px' : '?' }` ); }
			if ( petits.length ) {
				const vus = {};
				for ( const p of petits ) { vus[ `${ p.cls }@${ p.h }px` ] = ( vus[ `${ p.cls }@${ p.h }px` ] || 0 ) + 1; }
				for ( const [ k, n ] of Object.entries( vus ) ) {
					console.log( `        cible    : ${ k } × ${ n } — sous 44 px au pouce (spec UX §5.3)` );
				}
			}
			if ( cases.length ) {
				const vues = {};
				for ( const c of cases ) { vues[ `${ c.cls }@${ c.w }×${ c.h }` ] = ( vues[ `${ c.cls }@${ c.w }×${ c.h }` ] || 0 ) + 1; }
				for ( const [ k, n ] of Object.entries( vues ) ) {
					console.log( `        case     : ${ k } × ${ n } — cible sous 44 px au pouce. Envelopper la case d'un <label class="aod-cd-checkwrap"> plutot que de grossir la case.` );
				}
			}
			for ( const t of tablesLarges ) {
				console.log(
					`        tableau  : .${ t.cls } défile de ${ t.scroll }px DANS son conteneur ` +
					`(${ t.rows } ligne(s), mode carte : ${ t.cards ? 'oui' : 'NON' }) — ` +
					'illisible au pouce, et invisible au débordement de page'
				);
			}
			if ( mode.ar && r.dir !== 'rtl' ) { console.log( `        dir      : ${ r.dir } (attendu rtl), lang=${ r.lang }` ); }
			const shot = path.join( SHOTS, `${ mode.ar ? 'ar' : 'fr' }-${ mode.w }-${ s.replace( /\W+/g, '-' ) }.png` );
			await page.screenshot( { path: shot, fullPage: true } );
			console.log( `        capture  : ${ shot }` );
		}
		await browser.close();
	}

	/* --- Dette D29 : dire ce qui a RÉELLEMENT été audité --------------------
	 *
	 * Sans fixtures (clone client, ou `run.sh rtl` lancé seul), une section
	 * creuse est légitime : on la NOMME au lieu de la compter comme auditée.
	 * Avec fixtures (`AOD_D29_SEEDED=1`, posé par run.sh), elle ne l'est plus —
	 * le seed est fait pour les remplir : une section restée creuse signifie
	 * que la fixture n'a pas pris, et la porte doit ROUGIR plutôt que d'auditer
	 * du vide en silence. */
	const garnies = SECTIONS.length - creuses.size;
	console.log( `\nSections réellement GARNIES : ${ garnies } / ${ SECTIONS.length }` );
	if ( creuses.size ) {
		console.log( `  creuses (rien à auditer) : ${ [ ...creuses ].join( ', ' ) }` );
	}
	if ( SEME ) {
		const detail = creuses.size
			? `${ creuses.size } creuse(s) : ${ [ ...creuses ].join( ', ' ) } — le seed transverse n'a pas pris`
			: '';
		if ( ! ok( creuses.size === 0, `🔴 D29 : fixtures en place → les ${ SECTIONS.length } sections portent toutes du contenu`, detail ) ) {
			fails++;
		}
	} else if ( creuses.size ) {
		console.log( '  ⓘ sans fixtures : ces sections n’ont PAS été auditées (dette D29) — `run.sh tout` les sème.' );
	}

	/* 46.2 — témoin de couverture des tableaux.
	 *
	 * Le corpus est balayé 3 fois (FR desktop, FR 390, AR 390) : un plancher de 10
	 * mesures est atteint dès que quelques sections portent une ligne. Sans cette
	 * assertion, un sélecteur devenu faux (`table.aod-cd-table` renommé) ferait
	 * rendre `tablesLarges` vide partout — donc un vert parfait en n'ayant mesuré
	 * aucun tableau. C'est le sixième cas du tableau de §C.2, le garde-fou vide. */
	console.log( `Tableaux mesurés (3 passages) : ${ tablesTotal }` );
	if ( ! ok(
		tablesTotal >= 10,
		`🔴 46.2 : ${ tablesTotal } tableau(x) réellement mesuré(s) contre leur conteneur`,
		tablesTotal >= 10 ? '' : 'moins de 10 : le sélecteur ne trouve plus les tableaux — un audit vide n\'est pas un audit vert'
	) ) {
		fails++;
	}

	/* 46.6 — témoin de couverture des cibles tactiles. Sans lui, un sélecteur
	 * devenu faux rendrait `petits` vide sur les deux passages mobiles : vert
	 * éclatant, zéro cible regardée. Plancher à 100 : mesuré à plus de 400 sur les
	 * deux passages (157 boutons de rang à eux seuls). */
	console.log( `Cibles tactiles mesurées (2 passages mobiles) : ${ ciblesTotal }` );
	if ( ! ok(
		ciblesTotal >= 100,
		`🔴 46.6 : ${ ciblesTotal } cible(s) tactile(s) réellement mesurée(s) à 390 px`,
		ciblesTotal >= 100 ? '' : 'moins de 100 : le sélecteur ne trouve plus les contrôles — un audit vide n\'est pas un audit vert'
	) ) {
		fails++;
	}

	/* 46.6bis — témoin de couverture des CASES, et il vaut son pesant : c'est
	 * exactement le trou que cette étape ferme. La sonde des boutons publiait
	 * « 350 cibles, 0 échec » alors que 66 cases sur 87 tenaient sous 44 px — elle
	 * ne les énumérait simplement pas. Sans le compteur ci-dessous, un sélecteur
	 * devenu faux rendrait `cases` vide sur les deux passages mobiles et
	 * reproduirait le MÊME vert, à la même place.
	 * Plancher à 60 : mesuré à 174 sur les deux passages (87 par passage, dont
	 * les 58 de la colonne « Livraison gratuite »). */
	console.log( `Cases à cocher mesurées (2 passages mobiles) : ${ casesTotal }` );
	if ( ! ok(
		casesTotal >= 60,
		`🔴 46.6bis : ${ casesTotal } case(s)/radio(s) réellement mesurée(s) à 390 px`,
		casesTotal >= 60 ? '' : 'moins de 60 : le sélecteur ne trouve plus les cases — c’est le trou même que cette sonde ferme'
	) ) {
		fails++;
	}

	/* D32 — témoin du seul état que cette porte ne servait PAS d'elle-même.
	 * Trois passages × un panneau déplié = 3 attendus. Sans cette assertion, un
	 * `tr.aod-cd-carrier-row` renommé, un JS qui n'écoute plus, ou une section
	 * Livraison réservée au gérant feraient sauter le clic en silence : la porte
	 * mesurerait le tableau FERMÉ trois fois de plus et rendrait le même vert. */
	console.log( `Panneaux de transporteur dépliés par la porte : ${ panneauxOuverts }` );
	if ( ! ok(
		panneauxOuverts >= 3,
		`🔴 D32 : ${ panneauxOuverts } panneau(x) réellement déplié(s), donc mesuré(s) OUVERTS`,
		panneauxOuverts >= 3 ? '' : 'moins de 3 (un par passage) : le clic n’ouvre plus rien — l’état où vivait le défaut du chevron n’est plus audité'
	) ) {
		fails++;
	}

	/* 47.4 — témoin de couverture de la cellule « Client ». Une boutique sans
	 * commande, un sélecteur devenu faux, ou une classe renommée rendraient les
	 * trois assertions ci-dessus muettes et la porte verte. 3 passages × ≥ 1 ligne. */
	console.log( `Cellules « Client » mesurées (3 passages) : ${ cellulesClient }` );
	if ( ! ok(
		cellulesClient >= 3,
		`🔴 47.4 : ${ cellulesClient } cellule(s) « Client » réellement mesurée(s)`,
		cellulesClient >= 3 ? '' : 'moins de 3 (une par passage) : la mise en page à deux rangs n’est plus auditée'
	) ) {
		fails++;
	}

	ok( fails === 0, `RTL / MOBILE : ${ fails } échec(s)` );
	process.exit( fails ? 1 : 0 );
} )();
