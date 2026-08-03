/**
 * Recette « Confirmée → expédiée se déplace vers Suivi » — clôture Phase 12 (12.2).
 *
 * Prouve, connecté en gérant, que dès qu'un colis est créé chez le transporteur
 * (`_aod_ship_tracking` non vide), la commande QUITTE la liste « Commandes » (et
 * ses compteurs) pour n'apparaître plus que dans « Suivi commande » — le
 * « déplacement » demandé — tandis qu'une commande Confirmée NON expédiée reste
 * dans « Commandes ». Rien ne se perd (partition).
 *
 * Fixtures semées par scripts/recette-seed-phase12.php (via run.sh) :
 *   ExpedieRecette (Confirmée + tracking → expédiée) ; AConfirmerRecette
 *   (Confirmée, sans tracking → reste dans Commandes).
 *
 * Lancé par run.sh : `run commandes-suivi.js`. Sortie ≠ 0 = échec.
 */
const { launch, login, scanPhp, chercheDansPages } = require( './lib.js' );

let pass = 0, fail = 0;
const ok = ( c, m ) => { if ( c ) { pass++; console.log( '  \x1b[32m✓\x1b[0m ' + m ); } else { fail++; console.log( '  \x1b[31m✗\x1b[0m ' + m ); } };
const has = ( html, s ) => html.indexOf( s ) !== -1;

( async () => {
	const { browser, page, BASE } = await launch();
	try {
		await login( page );

		// ── Onglet « Confirmées » de Commandes ──
		await page.goto( `${ BASE }/gestion/orders?statut=aod-confirmed`, { waitUntil: 'networkidle' } );
		let html = await page.content();
		ok( scanPhp( html ).length === 0, 'Aucune erreur PHP sur la page Commandes' );
		ok( has( html, 'AConfirmerRecette' ), 'Commandes/Confirmées : la commande NON expédiée est présente' );
		ok( ! has( html, 'ExpedieRecette' ), 'Commandes/Confirmées : la commande EXPÉDIÉE a QUITTÉ la liste (12.1)' );

		// ── Onglet « Toutes » : la commande expédiée n'y est nulle part ──
		await page.goto( `${ BASE }/gestion/orders?statut=all`, { waitUntil: 'networkidle' } );
		html = await page.content();
		// ⚠️ Cette assertion-ci ne porte QUE sur la 1ʳᵉ page, son libellé le dit, et
		// c'est assumé : depuis que la boutique porte du volume, elle est vraie pour
		// deux raisons à la fois (l'exclusion réelle, et l'ancienneté de la fixture).
		// Ce qu'elle prétendait prouver est donc porté plus bas, PAR RECHERCHE —
		// « exclue partout, pas seulement hors 1ʳᵉ page ». On la garde comme témoin
		// de surface, pas comme preuve.
		ok( ! has( html, 'ExpedieRecette' ), 'Commandes/Toutes : la commande expédiée est absente de la 1ʳᵉ page' );

		// 🚨 TROISIÈME INSTANCE DU DÉFAUT DE D31, trouvée le 2026-07-30 en soldant
		// les deux premières — et celle-ci n'était couverte par AUCUNE dette : la
		// porte `suivi` ne figurait pas parmi les 8 rejouées à la clôture de la
		// Phase 46, donc son rouge n'a jamais été relevé. Même cause exactement :
		// la fixture est datée du 15/06/2024, « Commandes » trie du plus récent au
		// plus ancien et pagine à 20 — les 30 commandes du jeu de démonstration la
		// poussent hors de la première page. Le produit n'a rien fait de mal.
		const nonExpediee = await chercheDansPages( page, `${ BASE }/gestion/orders?statut=all`, 'AConfirmerRecette' );
		ok(
			nonExpediee.trouve,
			`Commandes/Toutes : la non expédiée reste visible dans la liste (page ${ nonExpediee.page }/${ nonExpediee.pages })`
		);

		// 🚨 ABSENCE ANCRÉE PAR UNE RECHERCHE (31.5). Le contrôle ci-dessus
		// n'inspecte que la PAGE COURANTE d'une liste paginée : il ne peut pas
		// dire « exclue partout », seulement « pas ici ». Sur un clone jeune les
		// commandes de recette sont en tête de liste, donc l'absence paraît
		// prouvée ; sur un clone qui a vendu, elles seraient hors première page et
		// le contrôle rendrait le MÊME vert sans rien établir.
		//
		// La recherche, elle, porte sur toute la table. Et elle est ancrée : on
		// vérifie d'abord qu'elle TROUVE la commande non expédiée — sans quoi
		// « la recherche ne rend rien » suffirait à valider l'exclusion.
		// Même précaution que ci-dessous : on compte des LIGNES DE RÉSULTAT, pas des
		// occurrences dans le HTML — le terme cherché est réaffiché dans le champ,
		// donc `has( html, … )` serait vrai même sur zéro résultat. Un ancrage faux
		// est pire qu'un ancrage absent : il donne l'illusion d'avoir vérifié.
		await page.goto( `${ BASE }/gestion/orders?statut=all&s=AConfirmerRecette`, { waitUntil: 'networkidle' } );
		const lignesAnc = await page.locator( '.aod-cd-table tbody tr' ).count();
		ok( lignesAnc > 0, `ANCRAGE : la recherche trouve bien la commande NON expédiée (${ lignesAnc } ligne(s))` );

		// ⚠️ ON N'INSPECTE PAS LE HTML BRUT ICI. Le terme cherché est RÉAFFICHÉ dans
		// le champ de recherche (`value="ExpedieRecette"`) : chercher la chaîne dans
		// toute la page la trouverait toujours, et l'assertion échouerait alors que
		// la requête, elle, ne rend rien — vérifié à la source, `wc_get_orders`
		// avec `s=Expedie` renvoie 0 commande. On lit donc le RÉSULTAT (l'état vide),
		// pas la page.
		await page.goto( `${ BASE }/gestion/orders?statut=all&s=ExpedieRecette`, { waitUntil: 'networkidle' } );
		const vide = await page.locator( '.aod-cd-empty' ).count();
		const lignes = await page.locator( '.aod-cd-table tbody tr' ).count();
		ok(
			vide > 0 && lignes === 0,
			`Commandes/Toutes : la commande expédiée est introuvable PAR RECHERCHE — exclue partout, pas seulement hors 1ʳᵉ page (état vide : ${ vide }, lignes : ${ lignes })`
		);

		// ── Suivi commande : la commande expédiée y est, avec son colis ──
		await page.goto( `${ BASE }/gestion/tracking`, { waitUntil: 'networkidle' } );
		html = await page.content();
		ok( scanPhp( html ).length === 0, 'Aucune erreur PHP sur la page Suivi' );
		ok( has( html, 'ExpedieRecette' ), 'Suivi : la commande expédiée apparaît (le « déplacement »)' );
		ok( has( html, 'ZZ12TRACK001' ), 'Suivi : le numéro de suivi du colis est affiché' );
		ok( ! has( html, 'AConfirmerRecette' ), 'Suivi : la commande NON expédiée n’y figure PAS' );

		// ── Filtre par date (43.1) — les deux fixtures sont datées au 2024-06-15
		// par recette-seed-phase12.php, précisément pour ce contrôle : une plage
		// « maintenant » n'aurait pas pu distinguer inclusion et exclusion.
		await page.goto( `${ BASE }/gestion/orders?statut=all&date_from=2024-06-01&date_to=2024-06-30`, { waitUntil: 'networkidle' } );
		html = await page.content();
		ok( has( html, 'AConfirmerRecette' ), 'Commandes : filtre date 01/06→30/06 INCLUT la commande du 15/06' );

		await page.goto( `${ BASE }/gestion/orders?statut=all&date_from=2024-07-01&date_to=2024-07-31`, { waitUntil: 'networkidle' } );
		const videCmdDate = await page.locator( '.aod-cd-empty' ).count();
		ok( videCmdDate > 0, '🔴 ANCRAGE INVERSE : Commandes, filtre date de juillet EXCLUT la commande du 15/06 (état vide)' );

		await page.goto( `${ BASE }/gestion/orders?statut=all`, { waitUntil: 'networkidle' } );
		let hasResetLink = await page.locator( `a:has-text("Réinitialiser")` ).count();
		ok( hasResetLink === 0, 'Commandes : sans filtre date actif, pas de bouton « Réinitialiser » sur ce formulaire' );

		await page.goto( `${ BASE }/gestion/tracking?date_from=2024-06-01&date_to=2024-06-30`, { waitUntil: 'networkidle' } );
		html = await page.content();
		ok( has( html, 'ExpedieRecette' ), 'Suivi : filtre date 01/06→30/06 INCLUT le colis du 15/06' );

		await page.goto( `${ BASE }/gestion/tracking?date_from=2024-07-01&date_to=2024-07-31`, { waitUntil: 'networkidle' } );
		html = await page.content();
		ok( ! has( html, 'ExpedieRecette' ), '🔴 ANCRAGE INVERSE : Suivi, filtre date de juillet EXCLUT le colis du 15/06' );
		ok( has( html, 'Aucun colis ne correspond' ), 'Suivi : message de filtre vide affiché (pas le message générique)' );

		// Réinitialiser : retour à « tout » (patron identique à Suivi, cf. AC 2).
		//
		// 🚨 CETTE ASSERTION ÉTAIT VERTE PAR ACCIDENT DE DONNÉES — durcie le
		// 2026-07-30 en soldant D31. Elle a le MÊME patron que son jumeau de
		// `abandonnees.js`, qui rougissait : témoin daté de 2024, liste triée du
		// plus récent au plus ancien, paginée à 20, et une assertion qui ne
		// regardait que la première page. Si elle ne rougissait pas, c'est
		// uniquement parce que le Suivi ne liste que les colis EXPÉDIÉS et
		// qu'aucun jeu de données n'en avait encore produit vingt — pas parce
		// qu'elle était juste. Un vert qui dépend du volume de la boutique
		// deviendra rouge chez un client, là où plus personne ne peut diagnostiquer.
		//
		// 🚨 Et c'est ici que la leçon se propage : le défaut a été trouvé sur
		// l'autre porte. Le corps de recherche est PARTAGÉ (`chercheDansPages`),
		// pas recopié — sans quoi le prochain correctif n'atteindrait, encore,
		// qu'un des deux jumeaux.
		await page.goto( `${ BASE }/gestion/tracking?date_from=2024-07-01&date_to=2024-07-31`, { waitUntil: 'networkidle' } );
		await page.locator( `a:has-text("Réinitialiser")` ).first().click();
		await page.waitForLoadState( 'networkidle' );

		const urlResetSuivi = new URL( page.url() );
		ok(
			! urlResetSuivi.searchParams.get( 'date_from' ) && ! urlResetSuivi.searchParams.get( 'date_to' ),
			`Suivi : après « Réinitialiser », l’URL ne porte plus aucune borne de date (${ urlResetSuivi.search || 'aucun paramètre' })`
		);

		const colis = await chercheDansPages( page, `${ BASE }/gestion/tracking`, 'ExpedieRecette' );
		ok(
			colis.trouve,
			`Suivi : après « Réinitialiser », le colis du 15/06 redevient visible dans la liste sans filtre (page ${ colis.page }/${ colis.pages })`
		);

		/* ── 47.4 : DEUX politiques du fond, sur la MÊME modale, dans la MÊME page ──
		 *
		 * 🚨 POURQUOI ICI ET NULLE PART AILLEURS. `#aod-cd-modal` est imprimé UNE
		 * fois en pied de page et deux sections se le partagent. `/gestion/tracking`
		 * est la SEULE page où les deux consommateurs coexistent : la timeline (le
		 * fond ferme — rien ne s'y perd) et le détail d'une commande (le fond ne
		 * ferme pas — on y saisit). C'est exactement le cas qu'un pilote unique doit
		 * savoir tenir, et que deux binders concurrents ne pouvaient pas tenir.
		 *
		 * ⚠️ Et cette page est VIDE sur un clone au repos depuis que la 47.2 a
		 * débranché l'envoi automatique : sans les fixtures semées par run.sh, le
		 * bloc serait vert sans rien avoir ouvert. D'où les deux témoins chiffrés. */
		await page.goto( `${ BASE }/gestion/tracking`, { waitUntil: 'networkidle' } );
		await page.waitForTimeout( 300 );

		const nTimeline = await page.locator( '.aod-cd-track-detail' ).count();
		const nDetail   = await page.locator( '.aod-cd-order-detail' ).count();
		ok( nTimeline > 0, `TÉMOIN : ${ nTimeline } bouton(s) « Voir le suivi » sur /gestion/tracking (0 = rien vérifié)` );
		ok( nDetail > 0, `TÉMOIN : ${ nDetail } bouton(s) « Détail » sur la même page (0 = rien vérifié)` );

		if ( nTimeline > 0 && nDetail > 0 ) {
			const ouverte = () => page.evaluate( () => {
				const m = document.getElementById( 'aod-cd-modal' );
				return !! ( m && ! m.hidden );
			} );
			const clicFond = () => page.locator( '#aod-cd-modal .aod-cd-modal-backdrop' ).click( { position: { x: 5, y: 5 } } );
			const ouvrir = async ( sel ) => {
				await page.locator( sel ).first().click();
				await page.waitForSelector( '#aod-cd-modal:not([hidden])', { timeout: 8000 } );
				await page.waitForTimeout( 600 );
			};

			await ouvrir( '.aod-cd-track-detail' );
			await clicFond();
			await page.waitForTimeout( 300 );
			ok( ! ( await ouverte() ), '🔴 Suivi : le fond ferme TOUJOURS la timeline (comportement inchangé)' );

			await ouvrir( '.aod-cd-order-detail' );
			await clicFond();
			await page.waitForTimeout( 300 );
			ok( await ouverte(), '🔴 …et sur LA MÊME PAGE, le fond ne ferme PAS le détail de commande (on y saisit)' );
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 200 );
			ok( ! ( await ouverte() ), 'Échap reste l’issue commune aux deux' );
		}
	} catch ( e ) {
		fail++; console.log( '  \x1b[31m✗ EXCEPTION\x1b[0m ' + e.message );
	} finally {
		await browser.close();
	}
	console.log( `\n=== Commandes → Suivi : ${ pass } ✓ / ${ fail } ✗ ===` );
	process.exit( fail ? 1 : 0 );
} )();
