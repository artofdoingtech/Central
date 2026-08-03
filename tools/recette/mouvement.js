// LE MOUVEMENT EXISTE-T-IL VRAIMENT ? (ROADMAP Phase 16 étape 16.3 ; Phase 18 étape 18.1)
//
// ⚠️ PÉRIMÈTRE — lire ceci avant d'ajouter une feuille de style au dépôt.
// Jusqu'à la Phase 18, cette porte n'inspectait QUE `dashboard.css`, et ne visitait
// QUE `/gestion`. Les 229 règles des trois feuilles publiques et les 6 du bloc
// inline du switcher FR/AR n'ont jamais été regardées — 21 % du CSS du dépôt. Les
// trois dernières `transition` implicites y ont survécu à la Phase 16 entière :
// elles n'avaient pas échappé au contrôle, elles n'avaient jamais été contrôlées.
// L'étape 18.1 étend l'audit aux 5 feuilles, et ajoute un CLIQUET STATIQUE (§5)
// parce qu'un audit CSSOM ne voit que les feuilles nommées en dur : une feuille
// nouvelle recréerait le trou à l'identique, en silence.
//
// Pourquoi cette porte existe. La Phase 16 a remplacé 22 `transition:.15s` nus
// par des listes de propriétés nommées. C'est un gain réel — `transition` sans
// propriété vaut `all`, donc anime aussi le layout — mais l'opération a un mode
// d'échec sournois : **oublier une propriété tue son animation, et RIEN ne le
// signale**. Les tests unitaires ne lisent pas le CSS ; les autres portes
// mesurent le contraste et les noms accessibles, pas le mouvement. L'interface
// devient sèche, et personne ne saura pourquoi.
//
// Le relevé qui a servi à écrire ces listes avait QUATRE angles morts, tous
// rencontrés pour de vrai :
//   1. l'état est posé par une classe ajoutée en JS  (`.aod-cd-toast` → `.show`) ;
//   2. l'état est porté par un ANCÊTRE  (`.aod-cd-todo-tile:hover .…__go`) ;
//   3. les propriétés appartiennent à un PSEUDO-ÉLÉMENT, pas à l'élément
//      (`.aod-cd-btn.is-loading::after` — ses width/height ne sont pas celles
//      du bouton, et un relevé naïf croit à tort qu'on anime du layout) ;
//   4. la règle d'état est PLUS LARGE que la règle de base
//      (`.aod-cd-form input:focus` couvre `.aod-cd-form input[type=text]`).
// Une lecture de feuille de style reproduirait ces angles morts. On déclenche
// donc l'état DANS LE NAVIGATEUR et on lit le style CALCULÉ.
//
// Cette porte a une deuxième raison d'être, découverte en écrivant la phase : un
// `@media(prefers-reduced-motion)` placé AVANT les règles qu'il annule, ou moins
// spécifique qu'elles, perd la cascade **sans rien signaler** — l'écran est
// identique pour qui n'a pas activé le réglage. C'est arrivé ici.

const fs = require( 'fs' );
const path = require( 'path' );
const { launch, login, ok, BASE, garerSouris } = require( './lib.js' );

// Contrôles pressables qui doivent accuser réception à l'appui.
// `hover` cible explicitement un exemplaire NON ACTIF : un onglet ou un segment
// déjà sélectionné ne réagit pas au survol, et c'est le comportement voulu. Viser
// « le premier » attrapait justement l'actif — le test accusait le CSS d'un défaut
// qui n'existait pas.
// 🚨 Chaque entrée DOIT matcher au moins un élément dans /gestion — un témoin le
// vérifie avant les boucles ci-dessous (bloc « 1bis »). Sans lui, un sélecteur
// devenu mort escamote EN SILENCE toutes ses assertions : les trois boucles font
// `if ( ! count ) continue;`. C'est ce qui est arrivé à `.aod-cd-tab`, reliquat de
// l'étape 5.4 (le segmented control `.aod-cd-seg__btn` l'a remplacé) : il ne
// matchait plus rien, et un quart du contrôle « appui + mouvement réduit »
// disparaissait à chaque run depuis la Phase 16, sans une ligne de rouge.
const PRESSABLES = [
	{ sel: '.aod-cd-btn', hover: '.aod-cd-btn:not(:disabled)' },
	{ sel: '.aod-cd-seg__btn', hover: '.aod-cd-seg__btn:not(.is-active)' },
	{ sel: '.aod-cd-lang__btn', hover: '.aod-cd-lang__btn:not(.is-active)' },
];

const ROOT = path.join( __dirname, '../..' );

// ── L'audit d'UNE feuille, dans le navigateur ────────────────────────────────
// 🚨 `page.evaluate()` ne transmet qu'UN SEUL argument : on passe un objet. Deux
// arguments partent en silence (le second est ignoré), et `needle` vaudrait
// `undefined` — la feuille serait « introuvable » sans que rien n'explique pourquoi.
const AUDIT = ( { kind, needle } ) => {
	const pick = ( s ) => ( 'href' === kind
		? ( s.href || '' ).includes( needle )
		: !! ( s.ownerNode && s.ownerNode.id === needle ) );
	const sh = [ ...document.styleSheets ].find( pick );
	if ( ! sh ) { return { missing: true, count: 0, inspected: 0, alls: [] }; }
	let rules;
	try { rules = sh.cssRules; } catch ( e ) { return { blocked: String( e ), count: 0, inspected: 0, alls: [] }; }
	let count = 0;
	let inspected = 0;
	const out = [];
	// 🚨 `r.cssRules && r.cssRules.LENGTH`, et l'oubli n'est pas véniel — voir le
	// commentaire long en §1 : sans `.length`, 10 règles sur 875 étaient inspectées.
	const walk = ( list ) => {
		for ( const r of list ) {
			count++;
			if ( r.style && r.selectorText ) {
				inspected++;
				if ( 'all' === r.style.transitionProperty ) { out.push( r.selectorText ); }
			}
			if ( r.cssRules && r.cssRules.length ) { walk( r.cssRules ); }
		}
	};
	walk( rules );
	return { count, inspected, alls: out };
};

// Le nombre de règles qu'un CSS doit exposer = ses `{` hors commentaires.
const bracesOf = ( css ) => ( css.replace( /\/\*[\s\S]*?\*\//g, '' ).match( /\{/g ) || [] ).length;

// `expected` d'une feuille servie par fichier.
const expectedFromFile = ( rel ) => bracesOf( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ) );

// `expected` d'un bloc <style id="…"> écrit dans un PHP.
// 🚨 ORDRE IMPÉRATIF : extraire le bloc D'ABORD, décommenter ENSUITE, compter APRÈS.
// Compter les `{` sur le fichier PHP entier compterait les accolades de PHP.
const expectedFromInline = ( rel, id ) => {
	const php = fs.readFileSync( path.join( ROOT, rel ), 'utf8' );
	const m = php.match( new RegExp( '<style id="' + id + '">([\\s\\S]*?)</style>' ) );
	if ( ! m ) { return null; }
	return bracesOf( m[ 1 ] );
};

( async () => {
	const { browser, page } = await launch();
	await login( page );
	// Contexte ANONYME distinct pour tout le public : réutiliser la page admin
	// injecterait la barre d'administration et ses feuilles, et mesurerait un écran
	// que l'acheteur ne voit jamais. Ne PAS basculer tout le scénario en anonyme :
	// le bloc /gestion a besoin de la session.
	const anon = await launch( { state: false } );
	let fails = 0;
	const t = ( c, m, x ) => { if ( ! ok( c, m, x ) ) { fails++; } };

	// `aod-landing.css` n'est PAS réservée à la page de vente : elle est aussi
	// enqueue sur une fiche produit à description vidéo (class-aod-cod-desc-video.php).
	// run.sh fournit l'ID d'une page de vente publiée quand il en existe une.
	const LANDING_URL = process.env.AOD_LANDING_PID ? `/?p=${ process.env.AOD_LANDING_PID }` : '/';

	/* --- 1. Le cliquet CSSOM : plus aucune transition implicite, sur LES 5 FEUILLES --- */
	console.log( '\n--- Aucune `transition-property: all` (5 feuilles) ---' );
	await page.goto( `${ BASE }/gestion/`, { waitUntil: 'networkidle' } );

	// On interroge la FEUILLE DE STYLE ici (et non le calculé) : le but est
	// justement de trouver les règles écrites en raccourci, y compris celles dont
	// aucun élément n'est présent sur cette page.
	//
	// 🚨 L'INVARIANT D'INTÉGRITÉ D'ABORD, et il n'est pas de la paranoïa de principe.
	// Une erreur de syntaxe CSS ne lève RIEN : le navigateur avale la règle fautive
	// et continue. L'assertion « aucune règle n'anime all » passerait alors au VERT
	// en n'ayant regardé qu'un fragment. C'est arrivé pendant l'écriture de cette
	// porte : une accolade mangée par un remplacement automatique a fait tomber la
	// feuille de 730 règles à 41, et le contrôle s'est déclaré satisfait.
	//
	// Le premier jet gardait un seuil (« > 600 règles »). Il ne valait rien :
	// éprouvé en cassant une accolade en FIN de fichier, la feuille tombait de 875 à
	// 869 — six règles perdues, très au-dessus du seuil, porte verte. Un seuil
	// n'attrape qu'une cassure précoce. D'où l'invariant EXACT : hors commentaires,
	// le nombre d'accolades ouvrantes du fichier est exactement le nombre de règles
	// que le navigateur doit exposer. Toute divergence = une règle avalée.
	//
	// `minInspected` est un plancher MESURÉ par feuille (étape 18.1 T0), jamais
	// recopié : le `700` de dashboard.css appliqué à aod-cod-pages.css (26 règles)
	// rougirait d'office.
	const SHEETS = [
		{ label: 'dashboard.css', kind: 'href', needle: 'dashboard.css', url: '/gestion/', ctx: 'admin',
			expected: expectedFromFile( 'plugins-dev/aod-client-dashboard/assets/css/dashboard.css' ), minInspected: 700 },
		{ label: 'aod-cod-pages.css', kind: 'href', needle: 'aod-cod-pages.css', url: '/', ctx: 'anon',
			expected: expectedFromFile( 'plugins-dev/aod-cod-form/assets/css/aod-cod-pages.css' ), minInspected: 22 },
		{ label: 'aod-lang-css (inline)', kind: 'id', needle: 'aod-lang-css', url: '/', ctx: 'anon',
			expected: expectedFromInline( 'plugins-dev/aod-cod-form/includes/class-aod-cod-lang.php', 'aod-lang-css' ), minInspected: 5 },
		{ label: 'aod-cod-form.css', kind: 'href', needle: 'aod-cod-form.css', url: '/contact/', ctx: 'anon',
			expected: expectedFromFile( 'plugins-dev/aod-cod-form/assets/css/aod-cod-form.css' ), minInspected: 120 },
		{ label: 'aod-landing.css', kind: 'href', needle: 'aod-landing.css', url: LANDING_URL, ctx: 'anon',
			expected: expectedFromFile( 'plugins-dev/aod-cod-form/assets/css/aod-landing.css' ), minInspected: 60 },
	];

	let audited = 0;
	for ( const s of SHEETS ) {
		// Une feuille par contexte : la session admin injecte la barre d'admin et ses
		// propres feuilles ; on ne mesure pas le public dans une page d'administrateur.
		const pg = 'admin' === s.ctx ? page : anon.page;
		await pg.goto( `${ BASE }${ s.url }`, { waitUntil: 'networkidle' } );
		const r = await pg.evaluate( AUDIT, { kind: s.kind, needle: s.needle } );

		// Feuille absente = ÉCHEC NOMMÉ, jamais un skip silencieux, et surtout jamais
		// un `exit 3` : ce code est GLOBAL au process — il emporterait l'audit des
		// autres feuilles, dont celui de /gestion qui fonctionne. Rougir ici laisse
		// les autres se prononcer.
		if ( r.missing || r.blocked ) {
			t( false, `${ s.label } : feuille INTROUVABLE sur ${ s.url }`, r.blocked || 'aucune entrée dans document.styleSheets' );
			continue;
		}
		audited++;

		t(
			r.count === s.expected,
			`${ s.label } : parsée en ENTIER (${ r.count } règles = les ${ s.expected } du fichier) — ${ s.url }`,
			r.count === s.expected ? '' : `${ s.expected - r.count } règle(s) avalée(s) : erreur de syntaxe CSS`
		);
		// Le témoin, sans lequel l'assertion suivante peut être vide sans le dire.
		t(
			r.inspected >= s.minInspected,
			`${ s.label } : règles réellement INSPECTÉES (${ r.inspected } sur ${ r.count })`,
			r.inspected >= s.minInspected ? '' : `seulement ${ r.inspected } inspectées (plancher ${ s.minInspected }) : le parcours du CSSOM saute des règles`
		);
		t(
			0 === r.alls.length,
			`${ s.label } : aucune règle n'anime \`all\` (${ r.inspected } inspectées)`,
			r.alls.slice( 0, 6 ).join( ' · ' )
		);
	}

	// Un oubli de câblage rendrait le rapport simplement plus court, sans rougir.
	t( audited === SHEETS.length, `Les ${ SHEETS.length } feuilles attendues ont été auditées (${ audited })`, audited === SHEETS.length ? '' : `${ SHEETS.length - audited } feuille(s) non auditée(s)` );

	/* --- 1bis. TÉMOIN : chaque cible d'appui existe dans /gestion --- */
	// Les boucles 2/3/4 font `if ( ! count ) continue;` : une cible qui ne matche
	// rien y disparaît sans un mot. Ce témoin transforme ce silence en rouge — un
	// sélecteur renommé lors d'un refactor CSS rougit ici, au lieu d'emporter tout
	// le contrôle « appui + mouvement réduit » sans bruit.
	console.log( '\n--- Les cibles d\'appui existent bien (sinon les boucles s\'escamotent) ---' );
	for ( const { sel } of PRESSABLES ) {
		const n = await page.locator( sel ).count();
		t( n > 0, `${ sel } : présent dans /gestion (${ n })`, n > 0 ? '' : 'sélecteur mort — les assertions d\'appui de cette cible seraient sautées en silence' );
	}

	/* --- 2. Le survol produit vraiment un changement --- */
	console.log( '\n--- Le survol change bien ce que la transition annonce ---' );
	for ( const { hover } of PRESSABLES ) {
		const el = page.locator( hover ).first();
		if ( ! ( await el.count() ) ) { continue; }
		const read = () => el.evaluate( ( e ) => getComputedStyle( e ).backgroundColor + '|' + getComputedStyle( e ).color );
		const before = await read();
		await el.hover();
		await page.waitForTimeout( 220 );
		const after = await read();
		t( before !== after, `${ hover } : le survol change fond ou couleur`, before === after ? `inchangé (${ before })` : '' );
		await garerSouris( page );
	}

	/* --- 3. L'appui accuse réception (souris maintenue, style CALCULÉ) --- */
	console.log( '\n--- L\'appui accuse réception ---' );
	for ( const { sel } of PRESSABLES ) {
		const el = page.locator( sel ).first();
		if ( ! ( await el.count() ) ) { continue; }
		const box = await el.boundingBox();
		if ( ! box ) { continue; }
		await page.mouse.move( box.x + box.width / 2, box.y + box.height / 2 );
		await page.mouse.down();
		await page.waitForTimeout( 160 );
		const pressed = await el.evaluate( ( e ) => getComputedStyle( e ).transform );
		await page.mouse.up();
		await page.waitForTimeout( 200 );
		t( pressed !== 'none', `${ sel } : creux d'appui présent`, pressed === 'none' ? 'aucun transform pendant l\'appui' : '' );
	}

	/* --- 4. `prefers-reduced-motion` est RÉELLEMENT respecté --- */
	// C'est le contrôle que la Phase 16 a le plus besoin d'avoir : la première
	// version de sa neutralisation était placée trop haut dans le fichier et
	// moins spécifique que les règles visées. Elle ne s'appliquait pas.
	console.log( '\n--- Mouvement réduit : le déplacement part, la réponse reste ---' );
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	await page.reload( { waitUntil: 'networkidle' } );

	for ( const { sel } of PRESSABLES ) {
		const el = page.locator( sel ).first();
		if ( ! ( await el.count() ) ) { continue; }
		const box = await el.boundingBox();
		if ( ! box ) { continue; }
		await page.mouse.move( box.x + box.width / 2, box.y + box.height / 2 );
		await page.mouse.down();
		await page.waitForTimeout( 160 );
		const tr = await el.evaluate( ( e ) => getComputedStyle( e ).transform );
		const bg = await el.evaluate( ( e ) => getComputedStyle( e ).backgroundColor );
		await page.mouse.up();
		t( tr === 'none', `${ sel } : plus de creux sous mouvement réduit`, tr === 'none' ? '' : `transform = ${ tr } — surcharge perdue dans la cascade` );
		// 🚨 `!! bg` SEUL EST UNE TAUTOLOGIE (corrigé en 31.5) :
		// `getComputedStyle().backgroundColor` ne renvoie JAMAIS de chaîne vide —
		// un fond absent vaut `rgba(0, 0, 0, 0)`, qui est vrai. L'assertion
		// passait donc quoi qu'il arrive, y compris si l'acquittement non moteur
		// avait entièrement disparu — c'est-à-dire dans le seul cas qu'elle
		// existe pour attraper. Le jumeau public (plus bas) était, lui, durci
		// depuis l'origine ; le durcissement n'avait simplement pas été recopié.
		t(
			!! bg && 'rgba(0, 0, 0, 0)' !== bg,
			`${ sel } : la réponse non-motrice subsiste (fond ${ bg })`,
			'rgba(0, 0, 0, 0)' === bg ? 'fond transparent : retirer le mouvement a retiré TOUT le retour d\'appui' : ''
		);
	}
	await page.emulateMedia( { reducedMotion: 'no-preference' } );

	/* --- 4bis. Les états PUBLICS se déclenchent vraiment --- */
	// Les 3 sélecteurs corrigés en 18.1 illustrent chacun un angle mort de l'en-tête.
	// On lit les propriétés RÉELLEMENT animées, pas un couple générique : le `:checked`
	// de `.aod-cod__opt-card` change surtout la `box-shadow`, qu'un contrôle
	// fond+couleur manquerait.
	console.log( '\n--- Les états publics changent bien ce que la transition annonce ---' );
	const ap = anon.page;

	if ( ! process.env.AOD_COD_PID ) {
		t( false, 'Produit de recette absent', 'AOD_COD_PID non fourni : run.sh doit semer le produit COD avant ce scénario' );
	} else {
		await ap.goto( `${ BASE }/?p=${ process.env.AOD_COD_PID }`, { waitUntil: 'networkidle' } );

		// (a) `.aod-cod__offer-check` — état porté par un ANCÊTRE (`.is-selected` est
		// sur la CARTE, pas sur la pastille). La 1re carte naît sélectionnée : cliquer
		// celle-là ne changerait rien. Il en faut une AUTRE — d'où l'offre semée.
		const cards = ap.locator( '.aod-cod__offer-card' );
		const nCards = await cards.count();
		t( nCards >= 2, `Fiche produit : ${ nCards } cartes d'offre (2 requises pour observer un changement)`, nCards >= 2 ? '' : 'le seed ne pose pas `_aod_offers` : aucune AUTRE carte à cliquer' );
		if ( nCards >= 2 ) {
			const check = cards.nth( 1 ).locator( '.aod-cod__offer-check' );
			const readCheck = () => check.evaluate( ( e ) => {
				const s = getComputedStyle( e );
				return s.borderColor + '|' + s.backgroundColor;
			} );
			const before = await readCheck();
			await cards.nth( 1 ).locator( 'label' ).first().click();
			await ap.waitForTimeout( 220 );
			await garerSouris( ap );   // sinon on mesure le survol, pas la sélection (leçon 17.5)
			const after = await readCheck();
			t( before !== after, '.aod-cod__offer-check : la sélection change bordure ou fond', before === after ? `inchangé (${ before })` : '' );
		}

		// (b) `.aod-cod__opt-card` — frère `+` : l'état vient de `input:checked`, et le
		// survol se fait sur le PARENT `.aod-cod__opt`, jamais sur la carte.
		//
		// 🚨 Les options sont DUPLIQUÉES dans chaque carte d'offre, et seul le panneau
		// de la carte SÉLECTIONNÉE est affiché (`.aod-cod__offer-panel[hidden]`). Un
		// `.aod-cod__opt` global attrape donc une option d'une carte refermée, sur
		// laquelle `click()` expire au bout de 30 s — constaté. On borne la recherche
		// à la carte sélectionnée, qui est la seule dont les options sont visibles.
		const opt = ap.locator( '.aod-cod__offer-card.is-selected .aod-cod__opt' ).first();
		if ( await opt.count() ) {
			const card = opt.locator( '.aod-cod__opt-card' );
			const readCard = () => card.evaluate( ( e ) => {
				const s = getComputedStyle( e );
				return s.borderColor + '|' + s.boxShadow + '|' + s.backgroundColor;
			} );
			const before = await readCard();
			await opt.click();
			await ap.waitForTimeout( 220 );
			await garerSouris( ap );
			const after = await readCard();
			t( before !== after, '.aod-cod__opt-card : `input:checked +` change bordure, ombre ou fond', before === after ? `inchangé (${ before })` : '' );
		} else {
			t( false, '.aod-cod__opt : option de recette absente', 'le seed doit poser `_aod_options`' );
		}

		// (c) `.aod-lang__btn` — état posé CÔTÉ SERVEUR selon la locale : `.is-active`
		// ne bascule pas au clic. On survole donc un exemplaire NON actif.
		// ⚠️ Ne pas confondre avec `.aod-cd-lang__btn` (dashboard) des PRESSABLES.
		const lang = ap.locator( '.aod-lang__btn:not(.is-active)' ).first();
		if ( await lang.count() ) {
			const readLang = () => lang.evaluate( ( e ) => {
				const s = getComputedStyle( e );
				return s.backgroundColor + '|' + s.color;
			} );
			const before = await readLang();
			await lang.hover();
			await ap.waitForTimeout( 220 );
			const after = await readLang();
			t( before !== after, '.aod-lang__btn : le survol change fond ou couleur', before === after ? `inchangé (${ before })` : '' );
			await garerSouris( ap );
		} else {
			// Défaut de mise en place du clone, pas un cas sans objet : le switcher
			// n'est injecté que si un menu est assigné (TEMPLATE.md §5).
			t( false, '.aod-lang__btn : switcher ABSENT du DOM', 'aucun menu assigné à primary/secondary_menu/mobile_menu — lancer pages-publiques.sh' );
		}
	}

	/* --- 5. LE CLIQUET STATIQUE : « n'importe où dans plugins-dev/ » --- */
	// L'audit CSSOM ci-dessus ne voit que les feuilles NOMMÉES EN DUR. Une feuille
	// nouvelle, ou un `<style>` ajouté dans un PHP, n'apparaîtrait dans aucune liste
	// et la porte resterait verte — le trou exact que l'étape 18.1 vient combler.
	// Ce balayage-là ne demande pas de navigateur et ne connaît pas de liste : il lit
	// tout `plugins-dev/`. C'est lui qui honore littéralement la DoD de la phase.
	console.log( '\n--- Cliquet statique : aucune `transition` nue dans plugins-dev/ ---' );
	const walkDir = ( dir, acc = [] ) => {
		for ( const e of fs.readdirSync( dir, { withFileTypes: true } ) ) {
			const p = path.join( dir, e.name );
			if ( e.isDirectory() ) { walkDir( p, acc ); } else { acc.push( p ); }
		}
		return acc;
	};
	const NUE = /transition\s*:\s*[.0-9]/;
	const files = walkDir( path.join( ROOT, 'plugins-dev' ) );
	const offenders = [];
	let cssScanned = 0;
	let inlineScanned = 0;

	for ( const f of files ) {
		const raw = fs.readFileSync( f, 'utf8' );
		const rel = path.relative( ROOT, f );
		if ( f.endsWith( '.css' ) ) {
			cssScanned++;
			// Décommenter AVANT de chercher : deux commentaires du dépôt CITENT
			// `transition:.12s` pour expliquer la règle. Les compter ferait rougir la
			// porte sur sa propre documentation — le miroir exact du piège de 17.5,
			// où une variable citée en commentaire se faisait cautionner par elle-même.
			const css = raw.replace( /\/\*[\s\S]*?\*\//g, '' );
			css.split( '\n' ).forEach( ( l, i ) => { if ( NUE.test( l ) ) { offenders.push( `${ rel }:${ i + 1 }` ); } } );
		} else if ( f.endsWith( '.php' ) ) {
			const blocks = raw.match( /<style[^>]*>[\s\S]*?<\/style>/g ) || [];
			for ( const b of blocks ) {
				inlineScanned++;
				const css = b.replace( /\/\*[\s\S]*?\*\//g, '' );
				if ( NUE.test( css ) ) { offenders.push( `${ rel } (bloc <style>)` ); }
			}
		}
	}

	// Les comptes sont eux-mêmes des assertions (DoD §C.2, règle 1) : sans elles, un
	// balayage qui ne trouverait plus aucun fichier se déclarerait vert.
	t( cssScanned >= 4, `Feuilles CSS balayées : ${ cssScanned }`, cssScanned >= 4 ? '' : `seulement ${ cssScanned } — le balayage ne trouve plus les fichiers` );
	t( inlineScanned >= 3, `Blocs <style> balayés : ${ inlineScanned }`, inlineScanned >= 3 ? '' : `seulement ${ inlineScanned } — les 3 blocs connus (aod-lang-css, aod-cd-accent, écran d'accès) ne sont plus vus` );
	t( 0 === offenders.length, `Aucune \`transition\` nue dans plugins-dev/ (${ cssScanned } CSS + ${ inlineScanned } blocs inline)`, offenders.slice( 0, 6 ).join( ' · ' ) );

	/* --- 6. Mouvement réduit CÔTÉ PUBLIC (Phase 19, étape 19.1) --- */
	// Le bloc 4 ne couvrait que /gestion : l'acheteur, lui, subissait le mouvement
	// quel que soit son réglage système. Ce bloc-ci vérifie les deux sens — que le
	// mouvement PART, et que ce qui n'est pas du mouvement RESTE.
	console.log( '\n--- Mouvement réduit côté public : le trajet part, la place et la réponse restent ---' );
	if ( process.env.AOD_COD_PID ) {
		// 🚨 LE PIÈGE DE CE BLOC, ET IL A ÉTÉ COMMIS AVANT D'ÊTRE VU.
		// `transform: none` est aussi la valeur PAR DÉFAUT. Asserter « none sous
		// mouvement réduit » est donc satisfait de deux façons : la surcharge marche,
		// OU l'appui ne s'est jamais enregistré. Une première version de ce contrôle
		// restait VERTE alors que la surcharge avait perdu la cascade — elle prouvait
		// seulement que `:active` ne s'appliquait pas.
		// D'où la mesure en DEUX temps : on établit d'abord que l'appui produit
		// vraiment un mouvement SANS le réglage. Sans cette base, l'assertion
		// suivante ne vaut rien.
		const pressTransform = async () => {
			const el = ap.locator( '.aod-cod__submit' ).first();
			if ( ! ( await el.count() ) ) { return null; }
			// 🚨 Obligatoire, et c'est ce qui manquait. `boundingBox()` rend des
			// coordonnées relatives au VIEWPORT : le bouton de commande est bas dans
			// une fiche produit, donc hors écran au chargement. La souris tombait à
			// côté, `:active` ne s'appliquait jamais, et le contrôle « transform ===
			// none sous mouvement réduit » passait au vert en n'ayant rien pressé.
			await el.scrollIntoViewIfNeeded();
			await ap.waitForTimeout( 80 );
			const box = await el.boundingBox();
			if ( ! box ) { return null; }
			await ap.mouse.move( box.x + box.width / 2, box.y + box.height / 2 );
			await ap.mouse.down();
			await ap.waitForTimeout( 160 );
			const st = await el.evaluate( ( e ) => {
				const s = getComputedStyle( e );
				return { tr: s.transform, bg: s.backgroundColor };
			} );
			await ap.mouse.up();
			await ap.waitForTimeout( 120 );
			return st;
		};

		// (a0) La BASE : sans réglage, l'appui déplace bien le bouton.
		await ap.emulateMedia( { reducedMotion: 'no-preference' } );
		await ap.goto( `${ BASE }/?p=${ process.env.AOD_COD_PID }`, { waitUntil: 'networkidle' } );
		const base = await pressTransform();
		t(
			base && 'none' !== base.tr,
			`.aod-cod__submit : l'appui produit bien un mouvement SANS le réglage (${ base ? base.tr : 'bouton absent' })`,
			base && 'none' === base.tr ? 'aucun creux d\'appui : l\'assertion suivante serait vraie pour rien' : ''
		);

		// (a1) …et il disparaît AVEC. Cette assertion ne vaut que parce que (a0) passe.
		await ap.emulateMedia( { reducedMotion: 'reduce' } );
		await ap.reload( { waitUntil: 'networkidle' } );
		const red = await pressTransform();
		if ( red ) {
			t( 'none' === red.tr, '.aod-cod__submit : plus de creux d\'appui sous mouvement réduit', 'none' === red.tr ? '' : `transform = ${ red.tr } — surcharge perdue dans la cascade` );
			// L'acquittement NON MOTEUR reste : sans lui, l'acheteur ne saurait plus
			// que son appui a été reçu. Retirer le mouvement ne doit pas retirer le retour.
			t( !! red.bg && 'rgba(0, 0, 0, 0)' !== red.bg, `.aod-cod__submit : la réponse non-motrice subsiste (fond ${ red.bg })` );
		}

		// (b) Le label flottant change de place sans faire le trajet.
		const lbl = ap.locator( '.aod-cod__float > label' ).first();
		if ( await lbl.count() ) {
			const prop = await lbl.evaluate( ( e ) => getComputedStyle( e ).transitionProperty );
			t(
				! /transform|top|all/.test( prop ),
				`.aod-cod__float > label : plus de trajet animé (transition-property = ${ prop })`,
				/transform|top|all/.test( prop ) ? 'le label glisse encore' : ''
			);
		}

		// (c) 🚨 LE TÉMOIN DU PIÈGE, et c'est le contrôle le plus important de ce bloc.
		// `.aod-gallery-nav:active` vaut `translateY(-50%) scale(.94)` : le premier
		// terme CENTRE, le second est l'appui. Un `transform:none` bien intentionné
		// décentrerait la flèche de la moitié de sa hauteur — un correctif
		// d'accessibilité qui casse la mise en page. On vérifie donc que la règle de
		// mouvement réduit conserve un transform, au lieu de l'annuler.
		const mixte = await ap.evaluate( () => {
			const out = { found: false, value: '' };
			for ( const sh of document.styleSheets ) {
				if ( ! ( sh.href || '' ).includes( 'aod-cod-form.css' ) ) { continue; }
				let rules;
				try { rules = sh.cssRules; } catch ( e ) { return out; }
				for ( const r of rules ) {
					if ( ! r.media || ! String( r.conditionText || r.media.mediaText ).includes( 'reduced-motion' ) ) { continue; }
					for ( const inner of r.cssRules ) {
						if ( inner.selectorText && inner.selectorText.includes( 'aod-gallery-nav' ) ) {
							out.found = true;
							out.value = inner.style.transform;
						}
					}
				}
			}
			return out;
		} );
		t( mixte.found, 'La règle de mouvement réduit de `.aod-gallery-nav:active` existe', mixte.found ? '' : 'introuvable dans le bloc @media' );
		t(
			mixte.found && 'none' !== mixte.value && '' !== mixte.value,
			`.aod-gallery-nav:active : le CENTRAGE est conservé sous mouvement réduit (${ mixte.value || 'vide' })`,
			'none' === mixte.value ? '🚨 `transform:none` — la flèche est décentrée de la moitié de sa hauteur' : ''
		);

		await ap.emulateMedia( { reducedMotion: 'no-preference' } );
	}

	console.log( `\n${ fails ? '❌' : '✅' } MOUVEMENT : ${ fails } échec(s)` );
	await anon.browser.close();
	await browser.close();
	process.exit( fails ? 1 : 0 );
} )();
