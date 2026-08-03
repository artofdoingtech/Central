/**
 * Recette « Commandes abandonnées » — clôture Phase 11 (étape 11.3).
 *
 * Prouve, connecté en gérant, que la section /gestion/abandoned présente les
 * prospects du formulaire COD restés `pending` (un visiteur qui a saisi son
 * téléphone sans cliquer « Commander ») avec de quoi les rappeler (Appeler /
 * WhatsApp), que le lead CONVERTI n'y figure jamais, et que onglets + recherche
 * filtrent bien.
 *
 * Fixtures semées par scripts/recette-seed-phase11.php (via run.sh) :
 *   AbandonRecette (pending, 1 h → « Abandonné »), EnCoursRecette (pending, < 30 min
 *   → « En cours »), ConvertiRecette (converted → jamais listé), FiltreDateRecette
 *   (43.1), et — pour l'assignation des prospects (43.3) — AssignRecetteA/B
 *   (assignés à 2 confirmateurs témoins) + AssignRecetteLibre (non assigné, cible
 *   de l'assignation au navigateur).
 *
 * Lancé par run.sh : `run abandonnees.js`. Sortie ≠ 0 = échec.
 */
const { execSync } = require( 'child_process' );
const path = require( 'path' );
const { launch, login, scanPhp, chercheDansPages, confirmerMaison } = require( './lib.js' );

const REPO = path.resolve( __dirname, '..', '..' );
/** wp-cli dans le conteneur, depuis l'HÔTE (même patron que panier-cod.js). */
const wp = ( cmd ) => execSync( 'docker compose exec -T wpcli wp ' + cmd, { cwd: REPO } ).toString().trim();

let pass = 0, fail = 0;
const ok = ( c, m ) => { if ( c ) { pass++; console.log( '  \x1b[32m✓\x1b[0m ' + m ); } else { fail++; console.log( '  \x1b[31m✗\x1b[0m ' + m ); } };
const has = ( html, s ) => html.indexOf( s ) !== -1;

( async () => {
	const { browser, ctx, page, BASE } = await launch();
	try {
		await login( page );

		// ── Onglet « Tous » ──
		await page.goto( `${ BASE }/gestion/abandoned`, { waitUntil: 'networkidle' } );
		let html = await page.content();
		ok( scanPhp( html ).length === 0, 'Aucune erreur PHP sur la page' );
		ok( has( html, 'AbandonRecette' ), 'Le prospect abandonné (>30 min) apparaît' );
		ok( has( html, 'EnCoursRecette' ), 'Le prospect en cours (<30 min) apparaît' );
		ok( ! has( html, 'ConvertiRecette' ), 'Le prospect CONVERTI n’apparaît PAS (plus abandonné)' );
		ok( has( html, 'Abandonné' ) && has( html, 'En cours' ), 'Les deux badges d’état sont présents' );
		ok( has( html, 'tel:0551110011' ), 'Bouton Appeler (tel:) présent' );
		ok( has( html, 'wa.me/213551110011' ), 'Bouton WhatsApp (0→213) présent' );

		// ── Les onglets, chacun cadré par une RECHERCHE sur le prospect attendu. ──
		//
		// 🚨 Le `&s=` n'est pas décoratif. La liste est paginée (PER_PAGE) et triée
		// par date décroissante : sur une boutique fournie, le prospect « abandonné »
		// (semé à -1 h) peut se retrouver en page 2, poussé par des prospects plus
		// récents. L'assertion « il est ABSENT de l'onglet En cours » devenait alors
		// vraie pour la mauvaise raison — pagination, pas filtrage — et la porte
		// verdissait même si le filtre était cassé. En cadrant la recherche sur le
		// prospect visé, il ne reste qu'une raison possible à son absence : le filtre.
		await page.goto( `${ BASE }/gestion/abandoned?filtre=abandoned&s=Recette`, { waitUntil: 'networkidle' } );
		html = await page.content();
		ok( has( html, 'AbandonRecette' ) && ! has( html, 'EnCoursRecette' ), 'Onglet « Abandonnés » : filtre le récent' );

		await page.goto( `${ BASE }/gestion/abandoned?filtre=recent&s=Recette`, { waitUntil: 'networkidle' } );
		html = await page.content();
		ok( has( html, 'EnCoursRecette' ) && ! has( html, 'AbandonRecette' ), 'Onglet « En cours » : filtre le vieux' );

		// ── Recherche par nom ──
		await page.goto( `${ BASE }/gestion/abandoned?s=EnCours`, { waitUntil: 'networkidle' } );
		html = await page.content();
		ok( has( html, 'EnCoursRecette' ) && ! has( html, 'AbandonRecette' ), 'Recherche « EnCours » isole le bon prospect' );

		// ── Recherche par téléphone ──
		await page.goto( `${ BASE }/gestion/abandoned?s=0551110011`, { waitUntil: 'networkidle' } );
		html = await page.content();
		ok( has( html, 'AbandonRecette' ) && ! has( html, 'EnCoursRecette' ), 'Recherche par téléphone' );

		/* ── Colonne « Montant » (43.6) ────────────────────────────────────
		 *
		 * 🚨 ASSERTION AUTO-ANCRÉE, et ce n'est pas de la prudence excessive :
		 * le seed EMPRUNTE le premier produit publié du clone
		 * (`recette-seed-phase11.php:113`). Sur un clone sans catalogue —
		 * exactement celui de développement — le prospect n'a donc pas de
		 * produit, et un tiret est la BONNE réponse. Asserter « un montant
		 * s'affiche » y échouerait sur du code correct ; asserter « un tiret
		 * s'affiche » passerait au vert sur un clone client sans rien prouver.
		 * On vérifie donc la COHÉRENCE entre les deux cellules : produit nommé
		 * ⇒ montant chiffré, produit absent ⇒ tiret. Vraie partout, informative
		 * partout.
		 *
		 * L'arithmétique exacte, elle, est prouvée par `test-montant-abandon.php`
		 * contre le code de production — ce n'est pas le rôle du navigateur. */
		await page.goto( `${ BASE }/gestion/abandoned?s=0551110011`, { waitUntil: 'networkidle' } );
		ok(
			has( await page.content(), 'Montant' ),
			'La colonne « Montant » est rendue dans l’en-tête du tableau'
		);
		const mnt = await page.evaluate( () => {
			const tr = Array.from( document.querySelectorAll( 'tbody tr' ) )
				.find( ( r ) => r.textContent.includes( 'AbandonRecette' ) );
			if ( ! tr ) return null;
			const td = tr.querySelector( '.aod-cd-cell-amount' );
			const prod = Array.from( tr.querySelectorAll( 'td' ) )
				.find( ( c ) => 'Produit' === c.getAttribute( 'data-label' ) );
			return {
				cellule: !! td,
				tiret: !! ( td && td.querySelector( '.aod-cd-muted' ) ),
				approx: !! ( td && td.querySelector( '.aod-cd-amount-approx' ) ),
				chiffres: td ? /\d/.test( td.textContent.replace( /\s/g, '' ) ) : false,
				produit: prod ? prod.textContent.trim() : '',
			};
		} );
		ok( !! mnt && mnt.cellule, 'ANCRAGE : la cellule montant existe sur la ligne du prospect' );
		if ( mnt && mnt.produit && '—' !== mnt.produit ) {
			ok( mnt.chiffres && ! mnt.tiret, `🔴 Prospect complet (produit « ${ mnt.produit } », wilaya, livraison) → un MONTANT est affiché` );
			ok( true, `      (montant lu : approché=${ mnt.approx })` );
		} else {
			ok( mnt && mnt.tiret, '🔴 Prospect sans produit sur ce clone → tiret, JAMAIS un 0 DA (cohérence produit ⇄ montant)' );
		}

		// ANCRAGE NÉGATIF INCONDITIONNEL : ce prospect-là pointe un produit qui
		// n'existe sur AUCUN clone (id 987654) — son montant est toujours un tiret.
		await page.goto( `${ BASE }/gestion/abandoned?s=ProduitSupprimeRecette`, { waitUntil: 'networkidle' } );
		const mntPS = await page.evaluate( () => {
			const tr = Array.from( document.querySelectorAll( 'tbody tr' ) )
				.find( ( r ) => r.textContent.includes( 'ProduitSupprimeRecette' ) );
			const td = tr ? tr.querySelector( '.aod-cd-cell-amount' ) : null;
			return td ? { tiret: !! td.querySelector( '.aod-cd-muted' ), texte: td.textContent.trim() } : null;
		} );
		ok(
			!! mntPS && mntPS.tiret,
			`🔴 ANCRAGE NÉGATIF : produit supprimé → tiret (lu : « ${ mntPS ? mntPS.texte : 'cellule absente' } ») — un prospect non chiffrable ne montre pas 0 DA`
		);

		// ── Filtre par date (43.1) — FiltreDateRecette est daté au 2024-06-15,
		// distinct au jour près de tous les autres prospects (semés « maintenant »
		// ou « il y a 1 h ») : une plage de juin l'inclut, une plage de juillet non.
		//
		// 🚨 PAS de `s=` ici, à dessein : le champ de recherche RÉAFFICHE sa propre
		// valeur (`value="…"`), donc `has(html, 'FiltreDateRecette')` serait TOUJOURS
		// vrai si on cherchait par ce nom — même leçon que commandes-suivi.js
		// (« le terme cherché est réaffiché dans le champ »). Compter les LIGNES DE
		// TABLEAU réelles, pas grep le HTML brut.
		await page.goto( `${ BASE }/gestion/abandoned?date_from=2024-06-01&date_to=2024-06-30`, { waitUntil: 'networkidle' } );
		let ligneDate = await page.locator( 'tr' ).filter( { hasText: 'FiltreDateRecette' } ).count();
		ok( ligneDate === 1, 'Filtre date 01/06→30/06 INCLUT le prospect du 15/06 (ligne de tableau)' );

		await page.goto( `${ BASE }/gestion/abandoned?date_from=2024-07-01&date_to=2024-07-31`, { waitUntil: 'networkidle' } );
		ligneDate = await page.locator( 'tr' ).filter( { hasText: 'FiltreDateRecette' } ).count();
		ok( ligneDate === 0, '🔴 ANCRAGE INVERSE : filtre date de juillet EXCLUT le prospect du 15/06 (0 ligne)' );

		// « Réinitialiser » : le lien efface TOUS les filtres (même patron que
		// Commandes/Suivi, cf. story AC 2/3) — le prospect redevient visible.
		//
		// 🚨 CE QUE CETTE ASSERTION A COÛTÉ (dette D31, soldée le 2026-07-30).
		// Elle tenait en une ligne — « le prospect redevient visible » — et
		// comptait les lignes de la PREMIÈRE page. Elle a rougi pendant deux
		// phases, et la dette a d'abord accusé le produit. Mesuré : la liste
		// portait 28 lignes pour un `PER_PAGE` de 20, et le témoin — daté de
		// 2024, donc le plus ancien d'un tri `updated_at DESC` — était au rang
		// 28. Le filtre ÉTAIT vidé ; il était en page 2.
		//
		// D'où la décomposition en trois : « le filtre est vidé » et « le témoin
		// est dans la liste » sont DEUX affirmations, et seule la première est
		// indépendante du volume. Les mesurer séparément dit lequel des deux a
		// lâché, au lieu de rendre un rouge qui ne désigne rien.
		await page.locator( `a:has-text("Réinitialiser")` ).first().click();
		await page.waitForLoadState( 'networkidle' );

		const urlReset = new URL( page.url() );
		ok(
			! urlReset.searchParams.get( 'date_from' ) && ! urlReset.searchParams.get( 'date_to' ),
			`Après « Réinitialiser », l’URL ne porte plus aucune borne de date (${ urlReset.search || 'aucun paramètre' })`
		);

		const champsDate = await page.evaluate( () => {
			const v = ( n ) => { const e = document.querySelector( `[name="${ n }"]` ); return e ? e.value : null; };
			return { from: v( 'date_from' ), to: v( 'date_to' ) };
		} );
		ok(
			'' === champsDate.from && '' === champsDate.to,
			`Après « Réinitialiser », les deux champs de date sont VIDES (from="${ champsDate.from }" to="${ champsDate.to }")`
		);

		// Volume-proof : on parcourt les pages au lieu de supposer la première.
		const retrouve = await chercheDansPages( page, `${ BASE }/gestion/abandoned`, 'FiltreDateRecette' );
		ok(
			retrouve.trouve,
			`Après « Réinitialiser », le prospect du 15/06 redevient visible dans la liste sans filtre (page ${ retrouve.page }/${ retrouve.pages })`
		);

		// ══ Assignation des prospects + filtre par confirmateur (Phase 43.3) ═══════
		//    Fixtures dédiées (AssignRecetteA/B/Libre), jamais touchées par les
		//    scénarios de conversion/suppression qui suivent — ces assertions ne
		//    dépendent donc pas de l'ordre d'exécution du fichier.
		const UID_A = process.env.AOD_P11_UID_A;
		const UID_B = process.env.AOD_P11_UID_B;

		await page.goto( `${ BASE }/gestion/abandoned`, { waitUntil: 'networkidle' } );
		ok( await page.locator( '#aod-cd-f-leadconfirmer' ).count() === 1, '43.3 : le filtre par confirmateur est présent (gérant)' );
		ok(
			await page.locator( 'tr' ).filter( { hasText: 'AssignRecetteA' } ).locator( '.aod-cd-leadassignsel' ).count() === 1,
			'43.3 : le sélecteur d’assignation est présent sur la ligne du prospect (gérant)'
		);
		// Le nom de l'assigné se lit dans la colonne « Assigné à », rendue serveur.
		ok(
			has( await page.content(), 'Recette aod_recette_conf11a' ),
			'43.3 : la colonne « Assigné à » affiche le nom du confirmateur assigné'
		);

		// ── Filtre : ancrage inverse dans les DEUX sens ──
		await page.goto( `${ BASE }/gestion/abandoned?confirmateur=${ UID_A }`, { waitUntil: 'networkidle' } );
		ok(
			await page.locator( 'tr' ).filter( { hasText: 'AssignRecetteA' } ).count() === 1
				&& await page.locator( 'tr' ).filter( { hasText: 'AssignRecetteB' } ).count() === 0,
			'43.3 : filtrer sur le confirmateur A isole SON prospect (B exclu)'
		);
		await page.goto( `${ BASE }/gestion/abandoned?confirmateur=${ UID_B }`, { waitUntil: 'networkidle' } );
		ok(
			await page.locator( 'tr' ).filter( { hasText: 'AssignRecetteB' } ).count() === 1
				&& await page.locator( 'tr' ).filter( { hasText: 'AssignRecetteA' } ).count() === 0,
			'🔴 43.3 : ANCRAGE INVERSE — filtrer sur B exclut A (un filtre qui ne rendrait jamais rien passerait le test précédent)'
		);

		// ── Assignation RÉELLE au navigateur, sur le prospect libre ──
		//    C'est le seul endroit qui prouve que le widget, son binder JS et le
		//    handler AJAX sont bien reliés ENTRE EUX (le cliquet, lui, appelle la
		//    méthode PHP en direct).
		await page.goto( `${ BASE }/gestion/abandoned?s=AssignRecetteLibre`, { waitUntil: 'networkidle' } );
		const libreRow = page.locator( 'tr' ).filter( { hasText: 'AssignRecetteLibre' } );
		const assignWidget = libreRow.locator( '.aod-cd-leadassignsel' );
		ok( await assignWidget.count() === 1, '43.3 : sélecteur présent sur le prospect NON assigné' );
		await assignWidget.locator( '.aod-cd-leadassignsel-btn' ).click();
		await page.locator( `.aod-cd-leadassignsel-opt[data-value="${ UID_A }"]` ).first().click();
		await page.waitForTimeout( 1200 );
		ok(
			( await assignWidget.locator( '.aod-cd-leadassignsel-btn .aod-cd-leadassignsel-text' ).innerText() ).indexOf( 'conf11a' ) !== -1,
			'43.3 : après clic, le sélecteur affiche le confirmateur choisi (retour AJAX appliqué)'
		);
		ok(
			( await libreRow.locator( '.aod-cd-cell-leadassigned' ).innerText() ).indexOf( 'conf11a' ) !== -1,
			'43.3 : la colonne « Assigné à » de la MÊME ligne suit sans rechargement (jamais deux valeurs contradictoires à l’écran)'
		);
		// …et EN BASE (une UI qui s'affiche juste n'a rien prouvé).
		const assignEnBase = wp( `eval 'global $wpdb;$t=AOD_COD_Leads::table();echo (int)$wpdb->get_var("SELECT assigned_confirmer FROM $t WHERE token=\\"ZZP11_asn\\"");'` );
		ok( assignEnBase.trim() === String( UID_A ), `43.3 : l’assignation est ÉCRITE EN BASE (colonne=${ assignEnBase.trim() }, attendu ${ UID_A })` );

		// ── Témoin d'identité : un CONFIRMATEUR ne voit ni le sélecteur, ni le
		//    filtre, ni le NOM d'un pair (leçon Phase 22/D13 : ne jamais éprouver
		//    une garde en gérant, qui les satisfait toutes).
		if ( process.env.AOD_P11_LOGIN_A && process.env.AOD_P11_PASS_A ) {
			await login( page, { user: process.env.AOD_P11_LOGIN_A, pass: process.env.AOD_P11_PASS_A } );
			await page.goto( `${ BASE }/gestion/abandoned?confirmateur=${ UID_B }`, { waitUntil: 'networkidle' } );
			const htmlConf = await page.content();
			ok( ! has( htmlConf, 'aod-cd-leadassignsel' ), '🔴 43.3 : AUCUN sélecteur d’assignation pour un confirmateur' );
			ok( ! has( htmlConf, 'id="aod-cd-f-leadconfirmer"' ), '🔴 43.3 : AUCUN filtre par confirmateur pour un confirmateur' );
			ok( ! has( htmlConf, 'Recette aod_recette_conf11b' ), '🔴 43.3 : le NOM d’un pair ne fuit jamais (règle 42.7 transposée aux prospects)' );
			ok( has( htmlConf, 'AssignRecetteB' ), '43.3 : …et le paramètre `confirmateur` posté est IGNORÉ (périmètre habituel, ni élargi ni restreint)' );
			await login( page ); // Retour en gérant pour la suite de la porte.
		} else {
			ok( false, '43.3 : identifiants du confirmateur témoin absents (AOD_P11_LOGIN_A/PASS_A) — le seed ne les a pas exposés' );
		}

		// ══ Conversion (Phase 39.1 → 39.2) : « Commander » crée le squelette, la ═══
		//    ligne disparaît, la modale d'édition s'ouvre avec le statut choisissable ;
		//    la commande est relue EN BASE ; un 2ᵉ appel prouve l'anti-doublon.
		//
		// 🚨 47.7 — IL N'Y A PLUS DE DIALOGUE NATIF À ACCEPTER. Cette porte posait
		// `page.on( 'dialog', d => d.accept() )` (défaut HARNESS trouvé en 39.2 :
		// Playwright headless REFUSE `window.confirm` par défaut). Les 13 dialogues
		// sont passés à la fenêtre maison : le gestionnaire ne se déclenche plus, et
		// sans clic sur « Confirmer » l'action ne part jamais — la porte rougissait
		// pour une bonne raison. `confirmerMaison()` (lib.js) fait le geste que
		// faisait l'utilisateur.

		await page.goto( `${ BASE }/gestion/abandoned?s=AbandonRecette`, { waitUntil: 'networkidle' } );
		const row = page.locator( 'tr' ).filter( { hasText: 'AbandonRecette' } );
		const convBtn = row.locator( '.aod-cd-convert' );
		ok( await convBtn.count() === 1, 'Gérant : le bouton « Commander » est présent sur AbandonRecette' );
		const leadId = await convBtn.getAttribute( 'data-lead-id' );

		await convBtn.click();
		await confirmerMaison( page ); // 47.7 — la question maison remplace window.confirm.
		// La ligne disparaît (le lead passe `converted`) ET la modale s'ouvre (39.2).
		await page.waitForSelector( '#aod-cd-modal:not([hidden])', { timeout: 8000 } ).catch( () => {} );
		ok( await page.locator( '#aod-cd-modal:not([hidden])' ).count() === 1, 'La modale d’édition s’ouvre après conversion (39.2)' );
		// 🚨 Le contenu (titre, widget de statut) est injecté APRÈS le fetch AJAX de la
		// modale, pas à son ouverture : attendre le widget, sinon on lit le placeholder
		// « Chargement du suivi… » (course pré-existante, révélée par un décalage de timing).
		await page.waitForSelector( '#aod-cd-modal .aod-cd-statussel', { timeout: 8000 } ).catch( () => {} );
		const modalTitle = ( await page.locator( '#aod-cd-modal-title' ).innerText().catch( () => '' ) ).trim();
		ok( /\d{3,}/.test( modalTitle ), `Titre de la modale = n° de commande (« ${ modalTitle } »)` );
		ok( await page.locator( '#aod-cd-modal .aod-cd-statussel' ).count() === 1, 'AC3 : le sélecteur de statut est choisissable DANS la modale (39.2, flux)' );
		ok( await page.locator( 'tr' ).filter( { hasText: 'AbandonRecette' } ).count() === 0, 'La ligne AbandonRecette a disparu (converti ⇒ plus un abandon)' );

		// Relire la commande EN BASE via le lead témoin (jamais un scan _aod_source
		// global : on suit l'order_id du lead ZZP11, donc jamais une vraie commande).
		const verif = wp( `eval-file /scripts/recette-verify-phase39.php check ${ leadId }` );
		const field = ( k ) => ( verif.match( new RegExp( '^' + k + '=(.*)$', 'm' ) ) || [] )[ 1 ] || '';
		ok( parseInt( field( 'ORDER' ), 10 ) > 0, 'La commande convertie existe en base (relue via le lead témoin)' );
		ok( field( 'STATUS' ) === 'pending', 'Squelette neutre : statut « pending » (aucun envoi transporteur)' );
		ok( field( 'SOURCE' ) === 'aod-abandoned', 'Meta _aod_source = « aod-abandoned » (témoin d’origine)' );
		ok( field( 'OKPRODUCT' ) === '1', 'La commande porte bien le produit du prospect' );
		ok( field( 'TRACKING' ) === '0', 'Aucun tracking posé (la commande reste dans Commandes tant que non « Confirmée »)' );
		ok( field( 'LEADSTATUS' ) === 'converted', 'Le lead est passé « converted » (mark_converted)' );

		// Anti-doublon (39.1) : un 2ᵉ appel sur le MÊME lead (déjà converti) → 409,
		// sans 2ᵉ commande. Forgé avec le vrai nonce `aod_cd` exposé par la coque.
		const dup = await page.evaluate( async ( lid ) => {
			const b = new URLSearchParams();
			b.append( 'action', 'aod_cd_convert_lead' );
			b.append( 'nonce', window.AOD_CD.nonce );
			b.append( 'lead_id', lid );
			const r = await fetch( window.AOD_CD.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: b } );
			const j = await r.json().catch( () => ( {} ) );
			return { status: r.status, success: !! ( j && j.success ) };
		}, leadId );
		ok( dup.status === 409 && ! dup.success, `2ᵉ conversion refusée — anti-doublon (HTTP ${ dup.status })` );
		// Le lead pointe toujours la MÊME commande : le 409 n'a créé aucun doublon.
		const verif2 = wp( `eval-file /scripts/recette-verify-phase39.php check ${ leadId }` );
		ok( ( verif2.match( /^ORDER=(.*)$/m ) || [] )[ 1 ] === field( 'ORDER' ), 'Aucune 2ᵉ commande créée (le lead pointe toujours la même)' );

		// ══ 39.4 : le TÉLÉPHONE seul suffit — un prospect dont le PRODUIT A DISPARU ══
		//    (product_id pointant un post inexistant) se convertit quand même : le
		//    bouton apparaît, la conversion réussit, et le squelette n'a AUCUNE ligne
		//    d'article (le confirmateur les compose au téléphone dans la modale).
		await page.goto( `${ BASE }/gestion/abandoned?s=ProduitSupprimeRecette`, { waitUntil: 'networkidle' } );
		const convPS = page.locator( 'tr' ).filter( { hasText: 'ProduitSupprimeRecette' } ).locator( '.aod-cd-convert' );
		ok( await convPS.count() === 1, '39.4 : bouton « Commander » présent malgré le produit supprimé (téléphone présent)' );
		const leadPS = await convPS.getAttribute( 'data-lead-id' );
		await convPS.click();
		await confirmerMaison( page ); // 47.7
		await page.waitForSelector( '#aod-cd-modal:not([hidden])', { timeout: 8000 } ).catch( () => {} );
		ok( await page.locator( '#aod-cd-modal:not([hidden])' ).count() === 1, '39.4 : la modale s’ouvre (plus jamais « Le produit de ce prospect n’existe plus »)' );
		ok( await page.locator( 'tr' ).filter( { hasText: 'ProduitSupprimeRecette' } ).count() === 0, '39.4 : la ligne a disparu (prospect converti)' );
		const vps = wp( `eval-file /scripts/recette-verify-phase39.php check ${ leadPS }` );
		const fps = ( k ) => ( vps.match( new RegExp( '^' + k + '=(.*)$', 'm' ) ) || [] )[ 1 ] || '';
		ok( parseInt( fps( 'ORDER' ), 10 ) > 0, '39.4 : la commande existe en base' );
		ok( fps( 'STATUS' ) === 'pending', '39.4 : statut pending' );
		ok( fps( 'SOURCE' ) === 'aod-abandoned', '39.4 : _aod_source = aod-abandoned' );
		ok( fps( 'ITEMS' ) === '0', '39.4 : ZÉRO ligne d’article (à composer au téléphone dans la modale)' );

		// ══ 39.5 : la modale d'une commande VIDE doit pouvoir la REMPLIR ═══════════
		//    (panier dans la modale : plusieurs produits, variantes, prix). Avant
		//    39.5, « Ajouter un article » + le champ Total étaient cachés à zéro
		//    article — on créait la commande sans pouvoir la remplir.
		// La modale est encore ouverte sur la commande vide qu'on vient de convertir.
		await page.waitForSelector( '#aod-cd-modal .aod-cd-od-edit-toggle', { timeout: 8000 } ).catch( () => {} );
		await page.locator( '#aod-cd-modal .aod-cd-od-edit-toggle' ).click();
		await page.waitForSelector( '#aod-cd-modal .aod-cd-od-edit:not([hidden])', { timeout: 5000 } ).catch( () => {} );
		ok( await page.locator( '#aod-cd-modal .aod-cd-od-addpick' ).count() === 1, '39.5 : « Ajouter un article » présent sur une commande VIDE' );
		ok( await page.locator( '#aod-cd-modal .aod-cd-od-items' ).count() === 1, '39.5 : le conteneur d’articles est rendu (le JS y empile les lignes)' );
		ok( await page.locator( '#aod-cd-modal .aod-cd-od-total' ).count() === 1, '39.5 : le champ Total est présent (prix modifiable)' );

		// Panier : ajouter DEUX produits distincts (pas un seul) via le sélecteur.
		const addOne = async ( idx ) => {
			const opts = await page.locator( '#aod-cd-modal .aod-cd-od-addpick option' ).evaluateAll( ( os ) => os.map( ( o ) => o.value ).filter( ( v ) => v ) );
			if ( opts.length <= idx ) { return false; }
			await page.selectOption( '#aod-cd-modal .aod-cd-od-addpick', opts[ idx ] );
			await page.locator( '#aod-cd-modal .aod-cd-od-additem-btn' ).click();
			return true;
		};
		const a1 = await addOne( 0 );
		const a2 = await addOne( 1 );
		ok( a1 && a2, '39.5 : deux produits distincts ajoutés au panier de la modale' );
		ok( await page.locator( '#aod-cd-modal .aod-cd-od-item.is-new' ).count() === 2, '39.5 : deux lignes d’article dans la modale (variantes + qté par ligne)' );

		// Enregistrer via le chemin existant (aod_cd_order_save_info, aucun AJAX neuf).
		await page.locator( '#aod-cd-modal .aod-cd-od-edit button[type="submit"]' ).click();
		await page.waitForTimeout( 1200 );
		const vps2 = wp( `eval-file /scripts/recette-verify-phase39.php check ${ leadPS }` );
		const items2 = ( vps2.match( /^ITEMS=(.*)$/m ) || [] )[ 1 ];
		ok( items2 === '2', `39.5 : la commande porte 2 articles après remplissage (ITEMS=${ items2 })` );

		// ══ 39.6 : sélection groupée + suppression (GÉRANT), comme la page Commandes ══
		//    Cadré sur EnCoursRecette (resté `pending` : jamais converti dans ce flux).
		//    Le dialog de confirmation est déjà auto-accepté (handler posé plus haut).
		await page.goto( `${ BASE }/gestion/abandoned?s=EnCoursRecette`, { waitUntil: 'networkidle' } );
		ok( await page.locator( '.aod-cd-leadcheck-all' ).count() === 1, '39.6 : case « tout sélectionner » présente (gérant)' );
		ok( await page.locator( 'tr' ).filter( { hasText: 'EnCoursRecette' } ).locator( '.aod-cd-leadcheck' ).count() === 1, '39.6 : case de sélection sur la ligne du prospect' );
		// 45.2 — la barre est désormais rendue en permanence : `:not([hidden])`
		// serait vrai avant même de cocher, donc vrai pour rien. On mesure ce qui
		// change réellement — l'action passe de désactivée à activable — et on
		// ancre les deux côtés, sinon une barre éternellement désactivée passerait.
		const actionAvant = await page.locator( '.aod-cd-lead-bulk-del' ).isDisabled();
		ok( actionAvant, '45.2 : ANCRAGE — rien de coché, « Supprimer » est désactivé (la garantie de 2026, portée autrement)' );
		await page.locator( '.aod-cd-leadcheck-all' ).check();
		ok( ! ( await page.locator( '.aod-cd-lead-bulk-del' ).isDisabled() ), '45.2 : une fois coché, « Supprimer » est armé' );
		ok( ( await page.locator( '#aod-cd-lead-bulk .aod-cd-bulkbar-n' ).innerText() ) === '1', '39.6 : le compteur reflète la sélection (1)' );
		await page.locator( '.aod-cd-lead-bulk-del' ).click();
		await confirmerMaison( page ); // 47.7
		await page.waitForTimeout( 1200 );
		ok( await page.locator( 'tr' ).filter( { hasText: 'EnCoursRecette' } ).count() === 0, '39.6 : la ligne a disparu après suppression' );
		const leftPending = wp( `eval 'global $wpdb;$t=AOD_COD_Leads::table();echo (int)$wpdb->get_var("SELECT COUNT(*) FROM $t WHERE name=\\"EnCoursRecette\\"");'` );
		ok( leftPending.trim() === '0', `39.6 : le prospect est supprimé EN BASE (reste ${ leftPending.trim() })` );

		// ── AR + RTL, mobile 390 px : la page rend (titre arabe) sans débordement ──
		await ctx.addCookies( [ { name: 'aod_lang', value: 'ar', url: BASE } ] );
		await page.setViewportSize( { width: 390, height: 780 } );
		await page.goto( `${ BASE }/gestion/abandoned`, { waitUntil: 'networkidle' } );
		html = await page.content();
		ok( has( html, 'الطلبات المهجورة' ), 'Site AR : le titre arabe « الطلبات المهجورة » s’affiche' );
		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		ok( overflow <= 1, `Pas de débordement horizontal à 390 px (overflow=${ overflow }px)` );
		await ctx.clearCookies();
	} catch ( e ) {
		fail++; console.log( '  \x1b[31m✗ EXCEPTION\x1b[0m ' + e.message );
	} finally {
		await browser.close();
	}
	console.log( `\n=== Commandes abandonnées : ${ pass } ✓ / ${ fail } ✗ ===` );
	process.exit( fail ? 1 : 0 );
} )();
