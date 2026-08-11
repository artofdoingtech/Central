/**
 * Recette « parcours confirmateur » — clôture Phase 22 (étape 22.2), dette D13.
 *
 * LA SEULE PORTE DU DÉPÔT QUI NE SE CONNECTE PAS EN GÉRANT.
 *
 * `AOD_CD_Roles::user_can()` répond vrai à toute `aod_cap_*` dès que l'utilisateur
 * possède `manage_woocommerce`. Le gérant satisfait donc TOUTES les gardes — et les
 * 10 portes qui se connectent le font en gérant. La couche d'autorisation entière
 * (7 caps, sections masquées, écrans en lecture seule, gardes AJAX) n'était donc
 * exercée nulle part dans les conditions où elle compte.
 *
 * C'est la cause racine de la Phase 21 : `ajax_test_carrier` exigeait
 * `manage_woocommerce` là où sa section demande `aod_cap_shipping_prices`. Un mur
 * devant un confirmateur « tarifs », invisible au gérant. Le défaut n'avait pas
 * échappé aux contrôles : il n'était jamais entré dans leur champ.
 *
 * ⚠️ MISE À JOUR 31.1 — le correctif de la Phase 21 était le bon réflexe appliqué
 * au mauvais handler, et cette porte en portait le témoin à l'envers. `test_carrier`
 * est repassé au gérant : il ÉCRIT les identifiants transporteurs avant d'appeler
 * l'API, là où `import_fees` ne fait que lire. Voir AC4. La leçon de la Phase 21
 * reste vraie — une garde ne doit pas diverger en silence — mais elle ne dit rien
 * de la PORTÉE : trois gardes parfaitement cohérentes peuvent être collectivement
 * trop larges. C'est ce trou-là qui a laissé passer la faille de la 31.1, et c'est
 * `tests/unit/test-cap-portee.php` qui le garde désormais.
 *
 * Fixtures semées par scripts/recette-seed-phase22.php (via run.sh) : DEUX
 * confirmateurs aux jeux de capabilities COMPLÉMENTAIRES (4 + 3 = les 7).
 *
 * Aucun des deux n'est « tout coché », et c'est le cœur du dispositif. Un compte
 * complet ne prouverait que les droits accordés, jamais que les droits refusés
 * bloquent — la direction dangereuse, celle des TROUS. Et un seul compte partiel
 * n'éprouverait ses caps manquantes que dans un sens : une capability devenue
 * inopérante (renommée, jamais lue) rendrait le même vert. Le compte B accorde
 * exactement ce que A refuse : chaque cap est donc vue ouvrir ET fermer.
 *
 * Lancé par run.sh : `run confirmateur.js`. Sortie ≠ 0 = échec.
 * 🚨 Cette porte ne se RETIRE jamais (pas d'`exit 3`) : la couche d'autorisation
 * n'est pas optionnelle par clone. Un retrait la rendrait muette là où elle doit
 * parler.
 */
const { launch, login, whoAmI, scanPhp, sectionRendue } = require( './lib.js' );

const USER   = process.env.AOD_P22_LOGIN || '';
const PASS   = process.env.AOD_P22_PASS || '';
const USER_B = process.env.AOD_P22_LOGIN2 || '';
const PASS_B = process.env.AOD_P22_PASS2 || '';

// Le contrat, écrit ici et vérifié là-bas. Doit rester synchronisé avec
// `$section_caps` (class-aod-cd-dashboard.php) et le seed.
//
// Compte A : confirm_orders · shipping_prices · stock · tracking
// Compte B : le complément — abandoned · stats_confirm · stats_delivery
//            (`home` s'ouvre sur l'une OU l'autre des deux caps `stats_*`)
const OUVERTES   = [ 'orders', 'stock', 'shipping', 'tracking' ];
const FERMEES    = [ 'home', 'abandoned', 'products', 'categories', 'marketing', 'whatsapp', 'team', 'account' ];
const OUVERTES_B = [ 'home', 'abandoned' ];

let pass = 0, fail = 0;
const ok = ( c, m ) => { if ( c ) { pass++; console.log( '  \x1b[32m✓\x1b[0m ' + m ); } else { fail++; console.log( '  \x1b[31m✗\x1b[0m ' + m ); } };

/** Slugs des sections listées dans la barre latérale, lus sur le href (pas le libellé : traduit). */
const slugsDuMenu = ( page ) => page.evaluate( () =>
	[ ...document.querySelectorAll( '.aod-cd-navlink' ) ]
		.map( ( a ) => ( a.getAttribute( 'href' ) || '' ).replace( /\/+$/, '' ).split( '/' ).pop() )
		.filter( Boolean )
);

// `sectionRendue()` vit désormais dans lib.js (31.5) : trois autres portes en
// avaient besoin, et une copie locale aurait divergé de la version partagée.

/**
 * Joue une action AJAX du dashboard et rend le CODE HTTP.
 *
 * On mesure la FRONTIÈRE D'AUTORISATION (403 ou non), jamais l'issue métier : une
 * action autorisée mais fonctionnellement en erreur (400) prouve exactement ce
 * qu'on veut — la garde a été franchie.
 */
const statutAjax = ( page, action, champs = {} ) => page.evaluate( async ( [ act, extra ] ) => {
	const body = new URLSearchParams( { action: act, nonce: window.AOD_CD.nonce, ...extra } );
	const r = await fetch( window.AOD_CD.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: body.toString(),
	} );
	return r.status;
}, [ action, champs ] );

( async () => {
	if ( ! USER || ! PASS || ! USER_B || ! PASS_B ) {
		console.error( "❌ AOD_P22_LOGIN / AOD_P22_PASS / *2 absents — cette porte est lancée par run.sh, qui sème les 2 comptes témoins." );
		process.exit( 2 );
	}

	const { browser, ctx, page, BASE } = await launch();
	// Récapitulatif de l'onglet Confirmation — porté HORS du `try` : le témoin de
	// fin de porte s'imprime dans tous les cas, y compris après une exception.
	// `null` y est une information (« pas atteint »), jamais un vert par défaut.
	let recapConf = null;

	try {
		// ── AC1 · Témoin d'identité ────────────────────────────────────────────
		// `login()` sort déjà en 2 si l'identité ne correspond pas (brique 22.1).
		// On le redit ici parce que c'est LA prémisse de toute la porte : si elle
		// tournait en gérant, chaque assertion ci-dessous deviendrait un mensonge
		// vert. `whoAmI` prend le CONTEXTE, pas la page.
		await login( page, { user: USER, pass: PASS } );
		const qui = await whoAmI( ctx );
		ok( qui === USER, `Connecté en CONFIRMATEUR « ${ USER } » (et non en gérant) — lu : ${ qui }` );

		await page.goto( `${ BASE }/gestion/`, { waitUntil: 'networkidle' } );
		const html = await page.content();
		ok( scanPhp( html ).length === 0, 'Aucune erreur PHP sur /gestion en confirmateur' );

		// ── AC2 · La barre latérale montre exactement les sections ouvertes ────
		const menu = await slugsDuMenu( page );
		console.log( `  ⓘ sections au menu : [${ menu.join( ', ' ) }]` );

		for ( const s of OUVERTES ) {
			ok( menu.includes( s ), `Section OUVERTE présente au menu : ${ s }` );
		}
		for ( const s of FERMEES ) {
			ok( ! menu.includes( s ), `Section FERMÉE absente du menu : ${ s }` );
		}
		ok( menu.length === OUVERTES.length, `Le menu ne montre QUE les ${ OUVERTES.length } sections ouvertes (vu : ${ menu.length })` );

		// 🚨 `landing` n'est volontairement PAS testée ici. Elle n'est pas dans
		// `sections()` : la boucle du menu ne l'affiche POUR PERSONNE, gérant
		// compris. Une assertion « absente du menu » serait verte quel que soit le
		// rôle — elle n'attesterait rien tout en gonflant le témoin. Elle se teste
		// par URL ci-dessous, où la question a un sens.

		// ── AC3 · Une section gérant n'est pas atteignable par URL ─────────────
		// Le code REPLIE sur la première section visible ; il ne rend pas un 403 et
		// ne change pas l'URL. Asserter sur `location.pathname` serait donc
		// toujours vert : on asserte sur la section RÉELLEMENT RENDUE.
		for ( const s of [ 'products', 'team', 'account', 'landing', 'marketing' ] ) {
			await page.goto( `${ BASE }/gestion/${ s }`, { waitUntil: 'networkidle' } );
			const rendue = await sectionRendue( page );
			ok( rendue !== s, `URL /gestion/${ s } ne rend PAS cette section (repli sur « ${ rendue } »)` );
		}

		// Contre-épreuve : une section ouverte, elle, s'ouvre bien par URL. Sans
		// elle, un repli systématique (bug rendant TOUTE URL inopérante) passerait
		// pour un contrôle d'accès qui fonctionne.
		await page.goto( `${ BASE }/gestion/shipping`, { waitUntil: 'networkidle' } );
		ok( ( await sectionRendue( page ) ) === 'shipping', 'Contre-épreuve : une section OUVERTE s’ouvre bien par URL' );

		// ── AC4 · PORTÉE d'une cap : « tarifs » ne donne PAS les transporteurs ──
		//
		// 🚨 CETTE ASSERTION A ÉTÉ RETOURNÉE EN 31.1, à dessein. Elle affirmait
		// l'inverse — « test_carrier suit bien aod_cap_shipping_prices » — comme
		// témoin de régression de la Phase 21, qui avait aligné cette garde sur sa
		// section. La prémisse de la Phase 21 était fausse : « qui peut IMPORTER
		// doit pouvoir TESTER » confond une lecture et une écriture. Importer LIT
		// une grille via un transporteur déjà connecté ; tester ÉCRIT d'abord les
		// identifiants postés. Ce desserrage a ouvert au porteur de la seule cap
		// « tarifs » la lecture des clés API du client et la redirection de l'envoi
		// automatique vers un hôte de son choix.
		//
		// `test_carrier` est donc désormais réservé au gérant, et A — qui PORTE la
		// cap « tarifs » — doit être refusé comme les autres.
		//
		// 🚨 `carrier` VIDE, et AUCUNE clé de réglages. Si une garde cédait, la
		// requête ne doit RIEN écrire : passé le contrôle « Transporteur inconnu »,
		// le handler écrit les réglages transporteur puis appelle l'API du livreur
		// POUR DE VRAI — ce qui consommerait le quota de production et écraserait la
		// configuration du gérant à chaque `run.sh tout`.
		const carrier = await statutAjax( page, 'aod_cd_test_carrier', { carrier: '' } );
		ok( carrier === 403, `PORTÉE : test_carrier REFUSÉ (403) MALGRÉ aod_cap_shipping_prices — réservé au gérant (31.1) — HTTP ${ carrier }` );

		// Contre-épreuve de PORTÉE, EN LECTURE SEULE. La cap n'est pas inopérante
		// pour autant : ce que « tarifs » est censé donner — la grille — doit
		// rester là. Sans ce témoin, on ne distinguerait pas « portée resserrée »
		// de « cap cassée », et un correctif qui aurait tout fermé passerait pour
		// bon.
		//
		// 🚨 NE PAS sonder `aod_cd_save_shipping` pour le prouver. Une première
		// rédaction de cette porte l'a fait avec une charge VIDE, en la croyant
		// inoffensive : le handler reconstruit la grille des 58 wilayas à partir du
		// POST, donc un POST vide écrit 58 tarifs VIDES et EFFACE la grille du
		// gérant — à chaque `run.sh tout`. C'est le piège que le commentaire d'AC4
		// signalait déjà pour `test_carrier`, reproduit à l'identique deux lignes
		// plus bas. Une sonde d'autorisation ne doit toucher que des handlers dont
		// l'échec métier précède l'écriture ; sinon on lit l'écran.
		await page.goto( `${ BASE }/gestion/shipping`, { waitUntil: 'networkidle' } );
		const ecranShip = await page.content();
		ok( ecranShip.includes( 'name="home[' ), 'Contre-épreuve : la GRILLE TARIFAIRE reste rendue au porteur de la cap' );
		ok( ! ecranShip.includes( 'aod-cd-carrier-test-btn' ), 'PORTÉE : le bloc « Transporteurs » n’est PAS rendu au confirmateur (31.1)' );

		// ── AC4bis · Finance : la fiche société ne s'ouvre pas par son URL ─────
		//
		// 🚨 L'ÉCRAN « Entreprise » ÉCRIT SUR LUI-MÊME : « cette page n'est visible
		// que du gérant, un confirmateur ne peut pas l'ouvrir par son adresse ».
		// Une phrase pareille, affichée à côté d'un RIB, ne doit pas reposer sur
		// une relecture de code : c'est une promesse faite à l'utilisateur, elle
		// s'éprouve. Le routeur ne rend ni 404 ni 403 sur une section non
		// autorisée — il retombe EN SILENCE sur la première section visible
		// (`maybe_render()`), donc le signal est la section RENDUE, jamais le code
		// HTTP. Même piège que la redirection wp-admin vingt lignes plus bas.
		await page.goto( `${ BASE }/gestion/finance?outil=entreprise`, { waitUntil: 'networkidle' } );
		const ecranEnt = await page.content();
		ok( 'finance' !== await sectionRendue( page ), 'PORTÉE : /gestion/finance ne s’ouvre PAS au confirmateur (le routeur le renvoie ailleurs)' );
		ok( ! ecranEnt.includes( 'name="ent_rib"' ), '🔒 Le champ RIB de la fiche société n’est nulle part dans la page servie au confirmateur' );
		ok( ! ecranEnt.includes( 'aod-cd-finent__form' ), 'PORTÉE : le formulaire « Entreprise » n’est pas rendu du tout' );

		// ── AC5 · Garde AJAX, direction « TROU » : un droit refusé bloque ───────
		//
		// Charge utile inoffensive : si une garde cédait, la requête ne doit RIEN
		// écrire. `save_stock` sans données, `save_pixels` sans réglages.
		//
		// ℹ️ Ces deux sondes portent sur `edit_products` et `manage_woocommerce`,
		// pas sur une `aod_cap_*` refusée : AUCUN handler AJAX du dépôt n'est gardé
		// par les 3 caps refusées au témoin (`aod_cap_abandoned` n'a pas de handler,
		// les `aod_cap_stats_*` ne gardent que du rendu). C'est un constat, pas un
		// oubli — la direction « cap refusée » est éprouvée par AC2 et AC3. Ajouter
		// un handler pour combler ce vide serait faire plier le produit pour un test.
		const stock = await statutAjax( page, 'aod_cd_save_stock' );
		ok( stock === 403, `TROU : save_stock REFUSÉ (403) malgré aod_cap_stock — l’écran Stock est en lecture seule — HTTP ${ stock }` );

		const pixels = await statutAjax( page, 'aod_cd_save_pixels' );
		ok( pixels === 403, `TROU : save_pixels REFUSÉ (403) — section réservée au gérant — HTTP ${ pixels }` );

		// ── Le confirmateur reste hors de wp-admin ─────────────────────────────
		//
		// 🚨 NE PAS asserter sur le code HTTP. `AOD_CD_Access::block_wp_admin()`
		// REDIRIGE vers /gestion (il ne refuse pas) — et une redirection suivie rend
		// 200. Une assertion `status >= 400` est donc rouge sur un produit
		// parfaitement sain : c'est exactement l'erreur commise en écrivant cette
		// porte. Le signal est l'URL D'ARRIVÉE.
		await page.goto( `${ BASE }/wp-admin/`, { waitUntil: 'domcontentloaded' } );
		const arrivee = new URL( page.url() ).pathname.replace( /\/+$/, '' );
		ok( arrivee.endsWith( '/gestion' ), `wp-admin renvoie le confirmateur vers /gestion (arrivée : ${ arrivee || '/' })` );

		// ── LE MIROIR · compte B, jeu de caps complémentaire ───────────────────
		//
		// 🚨 Sans ce second compte, les 3 caps absentes de A ne seraient éprouvées
		// que dans UN sens : « pas la cap → pas la section ». Une capability devenue
		// inopérante — renommée, jamais lue — rendrait exactement le même vert. B
		// accorde précisément ce que A refuse : ce qui était fermé doit s'ouvrir, et
		// ce qui était ouvert doit se fermer. Les 7 caps sont alors exercées DANS
		// LES DEUX SENS.
		console.log( `\n  ── miroir : compte B (${ USER_B }) ──` );
		await login( page, { user: USER_B, pass: PASS_B } );
		ok( ( await whoAmI( ctx ) ) === USER_B, `Bascule de compte effective vers « ${ USER_B } »` );

		await page.goto( `${ BASE }/gestion/`, { waitUntil: 'networkidle' } );
		const menuB = await slugsDuMenu( page );
		console.log( `  ⓘ sections au menu (B) : [${ menuB.join( ', ' ) }]` );

		for ( const s of OUVERTES_B ) {
			ok( menuB.includes( s ), `Miroir — section OUVERTE chez B, fermée chez A : ${ s }` );
		}
		for ( const s of OUVERTES ) {
			ok( ! menuB.includes( s ), `Miroir — section OUVERTE chez A, fermée chez B : ${ s }` );
		}
		ok( menuB.length === OUVERTES_B.length, `Le menu de B ne montre QUE ses ${ OUVERTES_B.length } sections (vu : ${ menuB.length })` );

		// B n'a pas `aod_cap_shipping_prices`. Depuis 31.1, A non plus n'y a pas
		// droit : les deux sont refusés. C'EST LE CONSTAT, et il est plus fort que
		// l'ancien miroir — la cap ne fait AUCUNE différence sur ce handler, ce qui
		// est exactement la définition d'une portée correctement resserrée.
		const carrierB = await statutAjax( page, 'aod_cd_test_carrier', { carrier: '' } );
		ok( carrierB === 403, `Miroir — test_carrier REFUSÉ (403) sans la cap non plus : la cap n’ouvre RIEN ici — HTTP ${ carrierB }` );

		// Le miroir de la contre-épreuve est déjà couvert plus haut, en lecture
		// seule : `shipping` figure dans OUVERTES (A) et pas dans OUVERTES_B, donc
		// la boucle « section OUVERTE chez A, fermée chez B » prouve que c'est bien
		// LA CAP qui ouvre la grille. Pas de sonde d'écriture ici : cf. AC4.

		// ── 47.5 · L'onglet Confirmation montre SES chiffres, pas la boutique ──
		//
		// 🚨 C'EST LA SEULE PORTE DU DÉPÔT OÙ UN VRAI CONFIRMATEUR OUVRE CET
		// ÉCRAN. Le défaut de la 47.5 était invisible partout ailleurs : le gérant
		// voit les chiffres globaux À BON DROIT, et le cliquet unitaire rend du
		// HTML sans navigateur. Ici, B est connecté, devant sa vraie page.
		//
		// ⚠️ Et sans les fixtures du seed, cette porte serait CREUSE — au repos, B
		// n'a rien confirmé (0) et la boutique en compte 1 : « B voit 0 » serait
		// vert sur un rendu cassé comme sur un rendu correct. Le seed lui attribue
		// donc AOD_P22_CONFB confirmations, et en attribue d'autres à A.
		console.log( '\n  ── 47.5 : onglet Confirmation, en confirmateur ──' );
		await page.goto( `${ BASE }/gestion/home?onglet=confirmation`, { waitUntil: 'networkidle' } );

		const ecranConf = await page.evaluate( () => {
			const nb = ( t ) => {
				const m = String( t || '' ).replace( /[^\d]/g, '' );
				return m === '' ? null : parseInt( m, 10 );
			};
			const pct = ( t ) => {
				const m = String( t || '' ).match( /([\d]+[,.][\d]+|[\d]+)\s*%/ );
				return m ? parseFloat( m[ 1 ].replace( ',', '.' ) ) : null;
			};
			const cartes = [ ...document.querySelectorAll( '.aod-cd-kpi' ) ].map( ( k ) => ( {
				lab: ( k.querySelector( '.aod-cd-kpi__lab' ) || {} ).textContent || '',
				val: ( k.querySelector( '.aod-cd-kpi__val' ) || {} ).textContent || '',
			} ) );
			const lignes = [ ...document.querySelectorAll( '.aod-cd-table tbody tr' ) ].map( ( tr ) =>
				[ ...tr.querySelectorAll( 'td' ) ].map( ( td ) => td.textContent.trim() )
			);
			return {
				cartes,
				lignes,
				// 🚨 PAR LIBELLÉ, PLUS PAR INDEX (48.4). `cartes[0]` valait « Commandes
				// reçues » — vrai jusqu'au jour où l'ordre change pour une raison de
				// LISIBILITÉ, sans qu'aucun calcul ne bouge. La porte aurait alors
				// comparé les mauvais nombres sans rien signaler. Un index est une
				// hypothèse sur la mise en page ; un libellé est le contrat.
				recues: ( cartes.find( ( c ) => /re.ues/i.test( c.lab ) ) || {} ).val
					? nb( cartes.find( ( c ) => /re.ues/i.test( c.lab ) ).val ) : null,
				confirmees: ( cartes.find( ( c ) => /^\s*Confirm/i.test( c.lab ) ) || {} ).val
					? nb( cartes.find( ( c ) => /^\s*Confirm/i.test( c.lab ) ).val.split( '(' )[ 0 ] ) : null,
				partLigne: lignes[ 0 ] ? pct( lignes[ 0 ][ 2 ] ) : null,
				nLigne: lignes[ 0 ] ? nb( lignes[ 0 ][ 1 ] ) : null,
				note: !! document.querySelector( '.aod-cd-note-scope-conf' ),
			};
		} );

		const attenduB = parseInt( process.env.AOD_P22_CONFB || '0', 10 );
		ok( attenduB > 0, `Le seed a bien attribué ${ attenduB } confirmation(s) à B (sinon la porte serait creuse)` );
		// 🔴 CETTE ASSERTION COMPTAIT LES CARTES — et la Phase 48 en a ajouté une.
		// Elle n'a PAS été « rendue verte » : le compte n'était qu'un PROXY pour
		// « la carte réservée au gérant est absente », et un proxy se périme dès
		// que l'écran gagne une carte pour une tout autre raison. Elle nomme
		// désormais ce qu'elle cherche — et mesure, en plus, ce que la 48.2 a
		// acheté : le chiffre que le confirmateur venait chercher.
		// (Même geste qu'en revue 47 sur `run.sh riche` : une porte qui rougit
		// parce que le contrat a changé se REBRAQUE, elle ne se desserre pas.)
		const labels = ecranConf.cartes.map( ( c ) => c.lab.trim() );
		ok(
			ecranConf.cartes.length >= 3,
			`L’onglet Confirmation est rendu (cartes : ${ ecranConf.cartes.length } — ${ labels.join( ' · ' ) })`
		);
		ok(
			! labels.some( ( l ) => /non attribu/i.test( l ) ),
			'🔴 La carte « Confirmées non attribuées », réservée au gérant, reste ABSENTE'
		);
		// 48.2 — le chiffre qui manquait : ce qui lui est ASSIGNÉ, distinct de ce
		// qu'il a confirmé et du total de la boutique.
		const iAsg = labels.findIndex( ( l ) => /assign/i.test( l ) );
		ok( iAsg !== -1, `48.2 : la carte « Commandes assignées » est rendue (${ labels.join( ' · ' ) })` );
		const nAsg = iAsg !== -1 ? parseInt( ( ecranConf.cartes[ iAsg ].val || '' ).replace( /[^\d]/g, '' ), 10 ) : null;
		const attenduAsgB = parseInt( process.env.AOD_P22_ASGB || '-1', 10 );
		ok(
			attenduAsgB >= 0,
			`Le seed annonce ${ attenduAsgB } commande(s) assignée(s) à B (sinon l’assertion suivante serait creuse)`
		);
		ok(
			nAsg === attenduAsgB,
			`🎯 48.2 : la carte porte SES ${ attenduAsgB } commande(s) assignée(s) (lu : ${ nAsg })`
		);
		ok(
			iAsg === 0,
			`🎯 48.4 : « Commandes assignées » est la PREMIÈRE carte que lit un confirmateur (position ${ iAsg + 1 }) — livrée en dernier, elle était exacte et inutile`
		);
		ok(
			ecranConf.recues !== nAsg,
			`🔴 Et ce n’est PAS le total de la boutique (reçues ${ ecranConf.recues } ≠ assignées ${ nAsg }) — le défaut que la Phase 48 corrige`
		);
		ok( ecranConf.confirmees === attenduB, `🎯 « Confirmées » affiche SES ${ attenduB } confirmations (lu : ${ ecranConf.confirmees })` );
		ok( ecranConf.lignes.length === 1, `Le tableau « Par confirmateur » ne montre QUE sa ligne (lignes : ${ ecranConf.lignes.length })` );
		ok( ecranConf.nLigne === ecranConf.confirmees, `La carte et le tableau s’accordent (${ ecranConf.confirmees } / ${ ecranConf.nLigne })` );
		ok( ecranConf.note, 'La note « le pourcentage se rapporte à la boutique » est affichée (47.5)' );

		// L'ÉCART DANS L'AUTRE SENS. « Part » = sa ligne ÷ confirmées de la
		// boutique : elle trahit donc le total global sans qu'on ait à le
		// connaître. S'il valait sa propre ligne, la part serait de 100 %.
		const boutique = ( ecranConf.partLigne && ecranConf.nLigne )
			? Math.round( ecranConf.nLigne / ( ecranConf.partLigne / 100 ) )
			: null;
		ok( ecranConf.partLigne !== null && ecranConf.partLigne < 100, `« Part » reste rapportée au total de la BOUTIQUE, pas à sa ligne (${ ecranConf.partLigne } %)` );
		ok( boutique !== null && boutique > ecranConf.confirmees, `🔴 La boutique compte ${ boutique } confirmation(s) — B en lit ${ ecranConf.confirmees } : il ne lit PAS le chiffre de la boutique` );
		ok( ecranConf.recues !== null && ecranConf.recues > boutique, `« Commandes reçues » reste le total de la boutique (${ ecranConf.recues }), au-dessus des confirmées` );
		console.log( `  ⓘ 47.5 — reçues (boutique) ${ ecranConf.recues } · confirmées (boutique) ${ boutique } · confirmées (B) ${ ecranConf.confirmees }` );
		recapConf = { cartes: ecranConf.cartes.length, lignes: ecranConf.lignes.length, asg: nAsg, recues: ecranConf.recues };
	} catch ( e ) {
		fail++;
		console.log( '  \x1b[31m✗\x1b[0m Exception : ' + ( e && e.message ? e.message : e ) );
	} finally {
		await browser.close();
	}

	// ── Témoin (DoD §C.2) : publier CE QUI A ÉTÉ INSPECTÉ, pas seulement le verdict.
	console.log(
		`\n  ⓘ périmètre inspecté — 2 identités CONFIRMATEUR (${ USER }, ${ USER_B })`
		+ ` · les 7 capabilities exercées DANS LES DEUX SENS (A : ${ OUVERTES.length } sections ouvertes / B : ${ OUVERTES_B.length })`
		+ ` · ${ FERMEES.length } sections refusées au menu · 5 URLs gérant éprouvées · 4 sondes AJAX (aucune écrivante)`
		// Le compte de cartes était écrit EN DUR (« 3 cartes ») et disait donc faux
		// dès que l'écran en a gagné une (48.2). Un récapitulatif qui n'est pas
		// dérivé de la mesure vieillit en silence — et c'est lui qu'on lit pour
		// savoir ce que la porte a regardé.
		+ ( recapConf
			? ` · onglet Confirmation lu EN CONFIRMATEUR (${ recapConf.cartes } cartes, ${ recapConf.lignes } ligne, assignées ${ recapConf.asg } ≠ reçues ${ recapConf.recues } — 47.5/48.2)`
			: ' · onglet Confirmation NON ATTEINT (exception avant la mesure)' )
	);
	console.log( `\n  ${ fail === 0 ? '\x1b[32m✅' : '\x1b[31m❌' } confirmateur : ${ pass } passé(s), ${ fail } échec(s)\x1b[0m` );
	process.exit( fail === 0 ? 0 : 1 );
} )();
